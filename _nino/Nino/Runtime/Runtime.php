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
		private const array NON_FATAL_LEVELS = [ E_USER_NOTICE, E_USER_WARNING, E_USER_DEPRECATED ];

		private static
			$_currentInstance = [];

		public static function init( array &$appData ): void {

			// Set current instance
			self::$_currentInstance = &$appData;

			// Set errorhandler
			set_error_handler( [ self::class, 'handleError' ] );
			set_exception_handler( [ self::class, 'handleException' ] );

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
					return $entries;
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
