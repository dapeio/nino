<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Runtime				Session handling and global error/exception handling, including logging
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Runtime - session handling and global error/exception handling,
	// including logging
	class Runtime {

		private const int RETENTION_MONTHS = 3;

		// The levels our own trigger_error() calls use as an out-of-band "record
		// this and carry on" channel: Elements' "return ! trigger_error( ... )"
		// idiom and Modules\Newsletter's catch blocks ("a storage failure must
		// not turn a visitor-facing signup into a 500") are both written for a
		// handler that returns - without this they were 500s all the same, and
		// the statement after the trigger_error() was unreachable.
		//
		// Engine-raised levels are deliberately absent: an undefined array key
		// or a division by zero is a bug in here, and a handler that swallows
		// those hides exactly what it exists to surface. Anything that really
		// must stop the request says so explicitly with E_USER_ERROR (see
		// AppData::init(), Filesystem::init()).
		// E_DEPRECATED is the one engine level in here, and the reason is that
		// it is not a statement about this request: it says a future php will
		// do something differently, not that anything went wrong now. Stopping
		// on it meant a php minor upgrade took a site down - intermittently at
		// that, since a compile-time deprecation only fires on the run that
		// recompiles the file, ie. once per opcache lifetime. It is recorded
		// like every other non-fatal level, which is what makes it visible.
		private const array NON_FATAL_LEVELS = [ E_USER_NOTICE, E_USER_WARNING, E_USER_DEPRECATED, E_DEPRECATED ];

		// How many entries one month's log keeps. The file is one array,
		// rewritten whole on every entry under an exclusive lock, so a
		// template with a broken shortcode - one notice per view - grew it
		// with the traffic until the request that had to read all of it to
		// add a line was itself what took the site down. The newest are kept:
		// they are the ones somebody is reading
		public const int MAX_LOG_ENTRIES = 1000;

		// The levels php raises and stops on. set_error_handler() is never
		// called for any of them, so until handleShutdown() below existed they
		// produced a bare 500 with nothing in the log and nothing on the page,
		// whatever /nino/error/log and /nino/error/display said.
		//
		// What is left in this set is narrower than the list looks, and worth
		// knowing before reaching for it: on the php 8.4 Nino requires, a parse
		// error in a lazily autoloaded class and a call to a function that is
		// not there are a ParseError and an Error - thrown objects
		// handleException() has always caught, and they leave error_get_last()
		// empty. What no handler ever sees is an exhausted memory limit, an
		// expired max_execution_time (both E_ERROR) and a compile-time fatal
		// such as a redeclared class (E_COMPILE_ERROR). E_PARSE and E_CORE_ERROR
		// are in the set for completeness rather than for reach: an engine that
		// fails to start, or fails on the entry file itself, does so before the
		// line below that registers this handler.
		private const array SHUTDOWN_LEVELS = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ];

		// Headroom handleShutdown() grants itself to report an exhausted memory
		// limit - enough to read this month's log back in, append an entry and
		// write it out again. A figure rather than no limit at all ('-1'): a
		// request that has just proven it will take whatever it is given must
		// not be handed the machine on its way out
		private const int SHUTDOWN_MEMORY_RESERVE = 8 * 1024 * 1024;

		private static
			$_currentInstance = [];

		// Set once a fatal has been reported, by whichever handler reached it
		// first: handleError() ends a fatal request with exit(), and an exit()
		// runs shutdown functions as well, while a second Runtime::init() in one
		// process registers a second handleShutdown().
		//
		// Neither writes the same fatal twice without this, but for reasons
		// that are accidents rather than decisions: every level handleError()
		// is called for is outside SHUTDOWN_LEVELS, and a second
		// handleShutdown() finds error_get_last() already overwritten by the
		// log write the first one did. This is that same outcome, on purpose
		private static bool $_reported = false;

		public static function init( array &$appData ): void {

			// Set current instance
			self::$_currentInstance = &$appData;

			// Set errorhandler
			set_error_handler( [ self::class, 'handleError' ] );
			set_exception_handler( [ self::class, 'handleException' ] );
			register_shutdown_function( [ self::class, 'handleShutdown' ] );

			// Start session
			if( session_status() !== PHP_SESSION_ACTIVE ) {

				// Without strict mode php happily adopts any session id a client
				// sends, so an attacker can plant one before login and keep using
				// it afterwards. loginUser()'s session_regenerate_id() covers the
				// post-login half of that, but not the pre-login state living in
				// the same session - the csrf token above all.
				ini_set( 'session.use_strict_mode', '1' );

				session_set_cookie_params( [
					'lifetime'	=> 0,
					'path'			=> '/',
					'secure'		=> ( $appData['/nino/session/force-secure-cookie'] ?? false ) === true || ( ( $_SERVER['HTTPS'] ?? '' ) !== '' && ( $_SERVER['HTTPS'] ?? '' ) !== 'off' ),
					'httponly'	=> true,
					'samesite'	=> 'Lax',
				] );
				session_start();
			}
			if( isset( $_SESSION[$appData['./nino/uid']] ) === false )
				$_SESSION[$appData['./nino/uid']] = [];
		}

		public static function getSessionValue( array &$appData, string $key, mixed $return = null ): mixed {
			return $_SESSION[$appData['./nino/uid']][$key] ?? $return;
		}

		public static function setSessionValue( array &$appData, string $key, mixed $value ): void {
			$_SESSION[$appData['./nino/uid']] = $_SESSION[$appData['./nino/uid']] ?? [];
			$_SESSION[$appData['./nino/uid']][$key] = $value;
		}

		public static function unsetSessionValue( array &$appData, string $key ): void {
			unset( $_SESSION[$appData['./nino/uid']][$key] );
		}


		// Uncaught exceptions take the same path as a fatal error - handleError()
		// tells the two apart by its first argument being an object. Declared
		// separately so set_exception_handler() gets the (Throwable): void
		// signature it asks for
		public static function handleException( \Throwable $exception ): void {
			self::handleError( $exception );
		}

		// Global error/exception handler. Terminates the request with a 500 for
		// exceptions, for E_USER_ERROR and for every engine-raised level;
		// returns (script continues on the next statement) for a level in
		// NON_FATAL_LEVELS and for one filtered out by error_reporting().
		public static function handleError(): bool {

			// Get errorhandler
			$args = func_get_args();

			// PHP still calls a registered error handler under the @ operator - it's
			// on the handler to check error_reporting() itself and bail out, or @ has
			// no effect at all. Exceptions (an object here) aren't affected by @, so
			// they always fall through to the normal handling below.
			if( is_object( $args[0] ) === false && ( error_reporting() & $args[0] ) === 0 )
				return false;

			// Create error array (from exception or error)
			$errorArray = ( is_object( $args[0] ) === true ) ? [
				'type'		=> 'Exception',
				'message'	=> $args[0]->getMessage(),
				'file'		=> $args[0]->getFile(),
				'line'		=> $args[0]->getLine(),
			] : [
				'type'						=> $args[0],
				'message'					=> $args[1],
				'file'						=> $args[2],
				'line'						=> $args[3],
			];

			// An exception is always fatal, and so is anything php raised itself -
			// only the user levels above are survivable
			$fatal = is_object( $args[0] ) === true || in_array( $args[0], self::NON_FATAL_LEVELS, true ) === false;

			// Every way out of here for a fatal ends in an exit(), and an exit()
			// runs handleShutdown() - which must not then report the same failure
			// a second time. Set where $fatal is decided rather than next to the
			// exit()s: there are two of them, and the display branch below reaches
			// its own first. Not set for a non-fatal level, which returns into the
			// script - a flag left standing there would swallow the report of a
			// real fatal later in the same request
			if( $fatal === true )
				self::$_reported = true;

			// Check, if error/log and error/display are configured yet
			$configured = self::$_currentInstance !== null && isset( self::$_currentInstance['/nino/error/log'] ) === true && isset( self::$_currentInstance['/nino/error/display'] ) === true;

			// Log error
			if( $configured === true && self::$_currentInstance['/nino/error/log'] === true )
				self::_recordError( self::$_currentInstance, $errorArray );

			// Display error - only when the site explicitly asked for it, and
			// only once that choice is actually known. An error raised inside
			// the boot sequence itself, before AppData::init() has read
			// config.php (see \Nino\init()), must default to not displaying -
			// that's exactly the case a production install never opted into.
			if( $configured === true && self::$_currentInstance['/nino/error/display'] === true ) {

				// DEBUG_BACKTRACE_IGNORE_ARGS: the frames on this stack carry
				// $appData (every password hash, the session token), the request
				// headers incl. Cookie/Authorization, and - in the login path -
				// the plaintext password passed to loginUser(). None of that
				// belongs on a rendered page, not even a deliberately enabled
				// debug one, which is just as likely to be screenshotted into a
				// ticket as it is to be read by the developer who enabled it.

				// Dumped either way, but only fatal levels die here: a display-on
				// dev install that stopped on a notice the production install
				// survives would be the two behaving differently, which is the one
				// thing a debug switch must never do
				echo '<pre>';
				var_dump( $errorArray, debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) );
				echo '</pre>';

				if( $fatal === true )
					exit;
			}

			// Recorded and, where asked for, displayed - returning true is what
			// stops php from printing the message a second time on its own
			if( $fatal === false )
				return true;

			// Break current cycle - SERVER_PROTOCOL is absent on cli (and can be
			// absent behind an odd sapi), and an undefined-key warning raised
			// inside the error handler would re-enter this very method
			header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1' ). ' 500 Internal Server Error', true, 500 );
			exit;
		}

		/**
		 *	The other half of handleError(): the failures php never hands it.
		 *
		 *	An exhausted memory limit, an expired max_execution_time, a compile-
		 *	time fatal such as a redeclared class - the engine raises those and
		 *	stops, and set_error_handler() is not called at all. Everything the
		 *	framework offers for diagnosis hung off that handler, so the failure
		 *	that most needs explaining was the one that explained itself least:
		 *	a bare 500, an empty log, and /nino/error/display with no effect on
		 *	it. error_get_last() still holds what died, and a shutdown function
		 *	is the last point at which anything can be done with it.
		 *
		 *	Registered by init(), which is the earliest point Nino has one to
		 *	register at: a failure before that - php failing on Nino.php itself -
		 *	stays the webserver's to report, and is the one case this cannot show.
		 *
		 *	@return		void
		 */
		public static function handleShutdown(): void {

			// handleError() already reported this one and ended the request -
			// its exit() runs shutdown functions too
			if( self::$_reported === true )
				return;

			$last = error_get_last();

			if( $last === null || in_array( $last['type'], self::SHUTDOWN_LEVELS, true ) === false )
				return;

			self::$_reported = true;

			// An exhausted limit is still exhausted in here: nothing is freed
			// before shutdown functions run, so _recordError() has to fit its work
			// into what the dying request happened to leave. That amount is the
			// size of the allocation php just refused - it asked for a block it
			// could not have, and that much is still unused below the limit -
			// which has nothing to do with what writing the entry costs: reading
			// this month's log back in, appending to it and writing it out again.
			// Measured against a log a month into its life, that is the difference
			// between an entry and silence, and silence is what the request that
			// most needs explaining then leaves behind. Raising the limit for what
			// is left of a request that is over anyway costs nothing; the
			// alternative - a reserve buffer allocated in init() and freed here -
			// makes every healthy request carry it
			if( str_contains( $last['message'], 'Allowed memory size' ) === true )
				ini_set( 'memory_limit', (string) ( memory_get_usage( true ) + self::SHUTDOWN_MEMORY_RESERVE ) );

			$errorArray = [
				'type'		=> $last['type'],
				'message'	=> $last['message'],
				'file'		=> $last['file'],
				'line'		=> $last['line'],
			];

			// Same rule as handleError(): both choices have to be known, or this
			// is a boot-time failure that a production install never opted into
			$configured = self::$_currentInstance !== null
				&& isset( self::$_currentInstance['/nino/error/log'] ) === true
				&& isset( self::$_currentInstance['/nino/error/display'] ) === true;

			if( $configured === true && self::$_currentInstance['/nino/error/log'] === true )
				self::_recordError( self::$_currentInstance, $errorArray );

			// A fatal that reached the engine may still have sent nothing, in
			// which case the status is ours to set. Where output already went
			// out, php has set it already
			if( headers_sent() === false )
				header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1' ). ' 500 Internal Server Error', true, 500 );

			// No backtrace here, unlike handleError(): the stack this died on is
			// gone by the time a shutdown function runs, and error_get_last() is
			// everything php kept of it
			if( $configured === true && self::$_currentInstance['/nino/error/display'] === true ) {
				echo '<pre>';
				var_dump( $errorArray );
				echo '</pre>';
			}
		}

		// Append one error entry to this month's /data/logs.<Y-m>.php -
		// a plain, readable array file (Filesystem::getFileContent()/
		// putFileContent()'s native .php handling), same idea as
		// Modules\Form's forms.<Y-m>.php. The fixed, predictable filename is
		// safe because the complete /data tree lives under private/ and is
		// never served by the webserver.
		// Never thrown - a logging failure inside the error handler
		// itself must not recurse into another error
		private static function _recordError( array &$appData, array $entry ): void {

			try {

				$path = '/data/logs.'. date( 'Y-m' ). '.php';

				\Nino\Filesystem::mutate( $appData, $path, function( array $entries ) use ( $entry ): array {

					$entries[] = $entry + [ 'date' => date( 'Y-m-d H:i:s' ) ];

					// See MAX_LOG_ENTRIES - array_slice() rather than a check,
					// so a file that is already over the cap (written before
					// there was one, or by hand) comes back under it too
					return count( $entries ) > self::MAX_LOG_ENTRIES
						? array_values( array_slice( $entries, 0 - self::MAX_LOG_ENTRIES ) )
						: $entries;
				} );

				self::_pruneLogs( \Nino\Filesystem::path( $appData, '/data' ) );

			} catch( \Throwable $e ) {
				// Swallow - nothing left to log this failure to from inside the error handler itself
			}
		}

		private static function _pruneLogs( string $dir ): void {

			$cutoff = ( new \DateTime( 'first day of -'. self::RETENTION_MONTHS. ' months' ) )->setTime( 0, 0 );

			\Nino\RotatingLog::prune( $dir, 'logs.', 'Y-m', '.php', $cutoff );
		}
	}
}
