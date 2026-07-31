<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	const VERSION = '0.9.0-beta';

	/**
	 *	Init nino
	 *
	 *	@return 	array
	 */
	function init(): array {

		$appData = [
			'./nino/uid'	=> dirname(__DIR__),
			// config.php holds the password hashes and route/module wiring -
			// letting it live outside the webroot means a webserver
			// misconfiguration that serves raw .php source (the exact case
			// the go-live checklist warns about) can't leak it. Defaults to
			// the project root (old, in-webroot behaviour) unless the site's
			// index.php defines NINO_CONFIG_DIR before requiring this file.
			'./nino/filesystem/configpath'	=> defined( 'NINO_CONFIG_DIR' ) ? NINO_CONFIG_DIR : dirname(__DIR__),
		];

		\Nino\AppData::prepare( $appData );

		\Nino\Runtime::init( $appData );

		// Only for an explicitly-set NINO_CONFIG_DIR - the default (project
		// root) is already covered by Filesystem::init()'s own checks below.
		// Checked right after Runtime::init() so this goes through the same
		// custom error handler as everything else, rather than falling
		// through to AppData::init()'s later "config.php not found" with no
		// indication of why the path was wrong in the first place.
		if( defined( 'NINO_CONFIG_DIR' ) === true && ( is_dir( NINO_CONFIG_DIR ) === false || is_writable( NINO_CONFIG_DIR ) === false ) )
			trigger_error( 'NINO_CONFIG_DIR (\''. NINO_CONFIG_DIR. '\') does not exist or is not writable.', E_USER_ERROR );

		\Nino\Filesystem::init( $appData );
		\Nino\AppData::init( $appData );
		\Nino\Locales::init( $appData );
		\Nino\Auth::init( $appData );
		\Nino\Modules::callModules( $appData, 'init' );

		return $appData;
	}

	/**
	 *	Handle a request
	 *
	 *	@param		array 		&$appData			(reference) Array with current app data
	 *	@param		array 		$request			Output to handle
	 *
	 *	@return 	array
	 */
	function request( array &$appData, array $request ): array {

		\Nino\Http::request( $appData, $request );
		\Nino\Locales::request( $appData, $request );

		\Nino\Http::response( $appData, $request );
		$currentUser = \Nino\Auth::getCurrentUser( $appData );
		\Nino\Html::addFills( $appData, [
			'[[/nino/http/request/uri]]'			=> $request['/nino/http/request']['uri'],
			'[[/nino/http/response/uri]]'		=> $request['/nino/http/response']['uri'],
			'[[/nino/http/response/locale]]'	=> $request['/nino/http/response']['locale'],
			'[[/nino/auth/user]]'						=> ( ( $currentUser !== false ) ? $currentUser['mail'] : '' ),
			'[[/nino/dir]]'				=> \Nino\Filesystem::getDir( $appData ),
			'[[/date/year]]'									=> date('Y'),
		], '*' );

		\Nino\Html::response( $appData, $request );

		return $request;
	}



	/**
	 *	Output a request
	 *
	 *	@param		array 		&$appData			(reference) Array with current app data
	 *	@param		array 		$request			Output to handle
	 *
	 *	@return 	void
	 */
	function output( array &$appData, array $request ): void {

		\Nino\Http::output( $appData, $request );
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	AppData 						Handle appData array
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class AppData {

		private static
			$_initialInstance = [
				'./nino/callbacks'							=> [],
				'./nino/filesystem/cache'			=> [],
				'./nino/filesystem/path'				=> '',
				'./nino/elements/cache'				=> [],
				'./nino/html/shortcodes'				=> [],
				'./nino/html/fills'						=> [],
				'./nino/html/cache'						=> [],
				'./nino/http/requests'					=> [],
				'./nino/locales/current'				=> 'de_DE',
				'./nino/auth/currentUser'			=> [],
			];

		/**
		 *	Prepare appData
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function prepare( array &$appData ): void {

			$appData = self::_merge( self::$_initialInstance, $appData );
		}


		/**
		 *	Init appData
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// getFileContent()'s $default is itself an array ([]), so the old
			// is_array() check below never actually caught a missing file -
			// this checks existence directly, so a typo'd NINO_CONFIG_DIR (or
			// a fresh install before the first config.php write) fails loud
			// and specific instead of silently booting with an empty config
			if( \Nino\Filesystem::fileExists( $appData, '/config.php' ) === false )
				trigger_error( 'AppData::init(): config.php was not found under \''. ( $appData['./nino/filesystem/configpath'] ?? $appData['./nino/filesystem/path'] ). '\' - check NINO_CONFIG_DIR if it is set.', E_USER_ERROR );

			$staticAppData = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			if( is_array( $staticAppData ) === false )
				trigger_error( 'AppData::init(): config.php exists but did not return an array.', E_USER_ERROR );

			$appData = self::_merge( $appData, $staticAppData );
		}

		/**
		 *	Merge $overlay into $base: associative arrays are merged
		 *	key-by-key recursively; a plain list (array_is_list(), eg.
		 *	'/nino/modules') on either side is replaced wholesale, not
		 *	merged index-by-index; anything else (including two conflicting
		 *	scalars) is overwritten by $overlay's value. Unlike
		 *	array_merge_recursive(), which turns two scalars sharing a key
		 *	into an array instead of replacing, and appends rather than
		 *	replaces list sub-arrays. The convention keeping runtime ('./')
		 *	and persistent ('/') keys from colliding today means this rarely
		 *	matters yet, but a module writing a persistent key that already
		 *	exists in the defaults would otherwise silently become an array
		 *	a callsite discovers far away from here.
		 *
		 *	@param		array 		$base					Lower-priority array
		 *	@param		array 		$overlay			Higher-priority array, wins on conflicts
		 *
		 *	@return 	array
		 */
		private static function _merge( array $base, array $overlay ): array {

			foreach( $overlay as $key => $value ) {

				$baseValue = $base[$key] ?? null;
				$bothMergeableArrays = is_array( $value ) === true && array_is_list( $value ) === false
					&& is_array( $baseValue ) === true && array_is_list( $baseValue ) === false;

				$base[$key] = ( $bothMergeableArrays === true ) ? self::_merge( $baseValue, $value ) : $value;
			}

			return $base;
		}

		/**
		 *	Persist a set of top-level appData keys into config.php - only
		 *	those keys, not a dump of the whole in-memory appData. That
		 *	distinction matters: appData is loaded once at request boot, so
		 *	blindly re-serializing all of it would silently discard whatever
		 *	a concurrent request (eg. a second admin session, or Auth's own
		 *	failed-attempt tracking) wrote to config.php in the meantime.
		 *	Re-reading fresh right before writing - same "read, then lock,
		 *	then put with nolock" shape Elements already uses for its own
		 *	per-type files - keeps this call's changes from clobbering
		 *	anyone else's. The cache entry is dropped first rather than just
		 *	calling getFileContent() as-is: its staleness check compares
		 *	mtimes at 1-second resolution, so a concurrent write landing in
		 *	the same wall-clock second (the exact case this exists to guard
		 *	against) would otherwise go undetected and this call would still
		 *	work from a stale copy.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$keys					Top-level appData keys this call changed
		 *
		 *	@return 	void
		 */
		public static function writeContentData( array &$appData, array $keys ): void {

			unset( $appData['./nino/filesystem/cache']['/config.php'] );
			$content = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			foreach( $keys as $key )
				$content[$key] = $appData[$key] ?? null;

			\Nino\Filesystem::lockFile( $appData, '/config.php' );
			\Nino\Filesystem::putFileContent( $appData, '/config.php', $content, true );
		}
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Auth								User authentification
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Auth {

		// How long an unused session token survives in a user's 'sessions'
		// array before loginUser() prunes it - without this, closing the
		// browser without logging out leaves a token entry that (unlike the
		// old ip-keyed scheme, which reused the same key on the next login
		// from that ip) is never removed on its own
		private const int SESSION_TTL = 60 * 60 * 24 * 30;

		// Failed-attempt counters live here, not in config.php - a login
		// storm from an unauthenticated attacker would otherwise force a
		// config.php rewrite (routes, module wiring, every user's hash) on
		// every single failure. Same layout Mail::_hit() already uses for
		// its own rate limit.
		private const string TRIES_PATH = '/data/auth-tries.php';

		/**
		 *	Init auth
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// Login/logout are only safe against CSRF if the Csrf module is
			// actually enabled - config.php's module list is already loaded
			// at this point (see \Nino\init()). This used to trigger_error()
			// right here, but that runs on *every* request incl. plain GETs,
			// and Runtime's error handler always terminates the request (see
			// its docblock) - one missing module entry would 500 the entire
			// site, not just the login form. The flag is checked once an
			// actual POST hits login/logout below instead, so this only ever
			// blocks the one feature that's actually unsafe.
			$appData['./nino/auth/csrf-enabled'] = self::_csrfModuleEnabled( $appData );

			// Read session user - the session token (not the client ip) is what
			// ties a php session to one entry in the user's 'sessions' array,
			// so a login survives an ip change (mobile network handover, CGNAT
			// rotation) and "log out everywhere" can't accidentally hit someone
			// else sharing the same NAT ip
			// Only mail + token live in $_SESSION (see loginUser()) - the user
			// array itself, pw hash included, is reloaded fresh from appData's
			// in-memory content on every request instead
			// is_string() guards a pre-migration session that still holds the
			// old full user array under this key (see P1-1/P1-2 above) - an
			// array used as an array key below would be a TypeError, not a
			// harmless miss, and would 500 every request from anyone who was
			// logged in at deploy time instead of just treating them as
			// logged out
			$sessionMail 	= \Nino\Runtime::getSessionValue( $appData, './nino/auth/current', '' );
			$sessionToken	= \Nino\Runtime::getSessionValue( $appData, './nino/auth/token', '' );
			if( is_string( $sessionMail ) === true && $sessionMail !== '' && isset( $appData['/nino/auth/user'][$sessionMail] ) === true && $sessionToken !== '' && isset( $appData['/nino/auth/user'][$sessionMail]['sessions'][$sessionToken] ) === true )
				$appData['./nino/auth/current'] = $appData['/nino/auth/user'][$sessionMail];

			$appData['/nino/http/routes']['POST://.nino/auth/login'] = [ 'uri' => '/.nino/auth/login' ];
			$appData['/nino/http/routes']['POST://.nino/auth/logout'] = [ 'uri' => '/.nino/auth/logout' ];
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.nino/auth/login', [ self::class, 'callbackLoginResponse' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.nino/auth/logout', [ self::class, 'callbackLogoutResponse' ] );
		}

		/**
		 *	Catch login response
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array 				$request			Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackLoginResponse( array &$appData, array &$request ): void {

			if( ( $appData['./nino/auth/csrf-enabled'] ?? false ) === false )
				trigger_error( 'Auth::callbackLoginResponse(): refused a login POST because the Csrf module is not enabled in config.php\'s /nino/modules - add "\Nino\Modules\Csrf" to run login/logout at all.', E_USER_ERROR );

			if( ( $request['./nino/csrf/blocked'] ?? false ) === true )
				return;

			self::loginUser( $appData, $request['/nino/http/request']['user'],  $request['/nino/http/request']['pw'] );

			$request['/nino/http/response']['statusCode']	= 401;
			$request['/nino/http/response']['body']				= false;

			if( self::getCurrentUser( $appData ) === false )
				return;

			$request['/nino/http/response']['statusCode']	= 200;
			$request['/nino/http/response']['body']				= true;
		}

		/**
		 *	Catch logout response
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array 				$request			Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackLogoutResponse( array &$appData, array &$request ): void {

			if( ( $appData['./nino/auth/csrf-enabled'] ?? false ) === false )
				trigger_error( 'Auth::callbackLogoutResponse(): refused a logout POST because the Csrf module is not enabled in config.php\'s /nino/modules - add "\Nino\Modules\Csrf" to run login/logout at all.', E_USER_ERROR );

			if( ( $request['./nino/csrf/blocked'] ?? false ) === true )
				return;

			self::logoutUser( $appData );

			$request['/nino/http/response']['statusCode']	= 200;
			$request['/nino/http/response']['body']				= true;
		}


		/**
		 *	Auth and login an user
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$username 		Username
		 *	@param		string				$pw 					Password
		 *
		 *	@return 	array | false 							New user array or false (on error)
		 */
		public static function loginUser( array &$appData, string $username, string $pw ): array|false {

			// Check user data
			$user	= self::getUser( $appData, $username );

			// Check status / cooldown
			if( $user === false || $user['status'] !== 2 || self::_getTries( $appData, $username ) < 0 - time() )
				return false;

			// Verify and login
			if( password_verify( $pw, $user['pw'] ) === false )
				return self::_registerFailedAttemp( $appData, $user );

			// Rotate the session id + csrf token now that the session's identity
			// is changing (session-fixation defense) - the status guard keeps
			// cli callers (tests) without an active session working
			if( session_status() === PHP_SESSION_ACTIVE )
				session_regenerate_id( true );
			\Nino\Modules\Csrf::rotateToken( $appData );

			// Rotate the hash if it was created with an outdated algorithm/cost -
			// computed now (while $user still holds the verified pw context),
			// applied further down to the freshly re-read $user
			$rehash 	= password_needs_rehash( $user['pw'], PASSWORD_DEFAULT );
			$newHash	= ( $rehash === true ) ? password_hash( $pw, PASSWORD_DEFAULT ) : null;

			// Login - logoutUser() removes the previous session's token from
			// this same user's persisted 'sessions' entry, so $user has to be
			// re-read afterwards: reusing the pre-logout copy below would
			// write that just-deleted token straight back in
			if( isset( $appData['./nino/auth/current'] ) === true && is_array( $appData['./nino/auth/current'] ) === true )
				self::logoutUser( $appData );

			$user = self::getUser( $appData, $username );
			if( $user === false )
				return false;
			if( $rehash === true )
				$user['pw'] = $newHash;

			// Issue a fresh, unguessable session token - this (not the client ip)
			// is the key under which the session lives in $user['sessions'], so
			// the login stays valid across ip changes. The ip is still recorded,
			// but purely as a display value for the admin's session list.
			$token = bin2hex( random_bytes( 32 ) );
			\Nino\Runtime::setSessionValue( $appData, './nino/auth/token', $token );

			// Update runtime website data - only the mail goes into $_SESSION,
			// never the user array (it carries the pw hash, and session files
			// are often world-readable on the shared hosting this targets)
			$appData['./nino/auth/current'] = $user;
			\Nino\Runtime::setSessionValue( $appData, './nino/auth/current', $user['mail'] );

			// Run callback - the one hook a module needs to react to a login
			// (eg. an activity log entry) without having to piggyback on
			// some unrelated per-route callback instead
			\Nino\Callbacks::doCallbacks( $appData, '/nino/auth/login', $user );

			// Prune stale/legacy session entries opportunistically, on the
			// one write this method was already going to make - same "clean
			// up on write" idea as Mail::_hit(). is_array() also catches any
			// pre-migration ip-keyed entry (a plain int timestamp, not the
			// current ['time','ip'] shape) left over from before this fix.
			$now = time();
			foreach( $user['sessions'] as $sessionToken => $sessionData )
				if( is_array( $sessionData ) === false || ( $sessionData['time'] ?? 0 ) < $now - self::SESSION_TTL )
					unset( $user['sessions'][$sessionToken] );

			// Every login mints a new token, so - unlike the old ip-keyed
			// bucket - there is no "already have a session for this key" case
			// to skip the write for
			$user['sessions'][$token] = [ 'time' => $now, 'ip' => \Nino\Http::getClientIp() ];
			$appData['/nino/auth/user'][$user['mail']] = $user;
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			return $user;
		}


		/**
		 *	Logout current user
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function logoutUser( array &$appData ): void {


			// Logout session auth
			\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/current' );

			// Rotate the csrf token now that the session's identity is changing (session-fixation defense)
			\Nino\Modules\Csrf::rotateToken( $appData );

			$user 	= self::getCurrentUser( $appData );
			$token	= \Nino\Runtime::getSessionValue( $appData, './nino/auth/token', '' );
			\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/token' );

			if( $user === false )
				return;

			// Run callback - same reasoning as loginUser()'s '/nino/auth/login'
			\Nino\Callbacks::doCallbacks( $appData, '/nino/auth/logout', $user );

			// Drop this one session token, not "whatever key matches the
			// current ip" - the whole point is that the two no longer coincide
			if( $token !== '' && isset( $user['sessions'][$token] ) === true ) {
				unset( $user['sessions'][$token] );
				$appData['/nino/auth/user'][$user['mail']] = $user;
				\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );
			}

			// Logout user in appData
			unset( $appData['./nino/auth/current'] );
		}


		/**
		 *	Get current user data
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return 	array | false
		 */
		public static function getCurrentUser( array &$appData ): array|false {

			return $appData['./nino/auth/current'] ?? false;
		}

		/**
		 *	Get user data per mail
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$username			Mail of requested user
		 *
		 *	@return 	array | false
		 */
		public static function getUser( array &$appData, string $username ): array|false {
			if( isset( $appData['/nino/auth/user'][$username] ) === false )
				return false;

			return $appData['/nino/auth/user'][$username] + [ 'mail' => $username ] ;
		}



		/**
		 *	Insert an user
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$username 		Username
		 *	@param		string				$pw 					Password
		 *	@param		array 				$perms				(optional) Permissions array
		 *
		 *	@return 	boolean											If successful
		 */
		public static function insertUser( array &$appData, string $username, string $pw, array $perms = [] ): bool {

			if( self::getUser( $appData, $username ) !== false )
				return false;

			$appData['/nino/auth/user'][$username] = [
				'pw'				=> password_hash( $pw, PASSWORD_DEFAULT ),
				'status'		=> 2,
				'sessions'	=> [],
				'perms'			=> $perms,
			];

			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			// Run callback - eg. a welcome mail, or syncing the new account elsewhere
			\Nino\Callbacks::doCallbacks( $appData, '/nino/auth/user/insert', self::getUser( $appData, $username ) );

			return true;
		}


		/**
		 *	Delete an user
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$username 		Username
		 *
		 *	@return 	boolean											If succesful
		 */
		public static function deleteUser( array &$appData, string $username ): bool {

			$user = self::getUser( $appData, $username );
			if( $user === false )
				return false;

			unset( $appData['/nino/auth/user'][$username] );
			unset( $appData['./nino/auth/current'] );

			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );
			self::_dropTries( $appData, $username );

			// Run callback - eg. cleaning up the now-deleted account elsewhere
			\Nino\Callbacks::doCallbacks( $appData, '/nino/auth/user/delete', $user );

			return true;
		}


		/**
		 *	Update a user's mail and/or password. Perms/sessions/status are
		 *	left untouched - those stay a developer-only, direct-json task.
		 *	A tries counter (see TRIES_PATH) follows a mail change so an
		 *	in-progress cooldown survives a rename.
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$username 		Current mail
		 *	@param		string				$newUsername	New mail (pass the same value to leave it unchanged)
		 *	@param		string				$pw 					(optional) New password - empty string keeps the current hash
		 *
		 *	@return 	array | false					Updated user array, or false (unknown user / new mail already taken)
		 */
		public static function updateUser( array &$appData, string $username, string $newUsername, string $pw = '' ): array|false {

			$user = self::getUser( $appData, $username );
			if( $user === false )
				return false;

			if( $newUsername !== $username && self::getUser( $appData, $newUsername ) !== false )
				return false;

			$user['mail'] = $newUsername;

			if( $pw !== '' )
				$user['pw'] = password_hash( $pw, PASSWORD_DEFAULT );

			unset( $appData['/nino/auth/user'][$username] );
			$appData['/nino/auth/user'][$newUsername] = $user;

			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );
			if( $newUsername !== $username )
				self::_renameTries( $appData, $username, $newUsername );

			// Keep the current session pointed at the right identity if this was a self-rename
			if( isset( $appData['./nino/auth/current']['mail'] ) === true && $appData['./nino/auth/current']['mail'] === $username ) {
				$updated = self::getUser( $appData, $newUsername );
				$appData['./nino/auth/current'] = $updated;
				\Nino\Runtime::setSessionValue( $appData, './nino/auth/current', $updated['mail'] );
			}

			// Run callback - eg. syncing the changed mail/password elsewhere
			\Nino\Callbacks::doCallbacks( $appData, '/nino/auth/user/update', self::getUser( $appData, $newUsername ) );

			return self::getUser( $appData, $newUsername );
		}


		/**
		 *	Clear all of a user's active sessions ("log out everywhere"). If
		 *	that's the current user, also ends the current request's session.
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$username 		Username
		 *
		 *	@return 	boolean											If successful
		 */
		public static function logoutAllSessions( array &$appData, string $username ): bool {

			if( self::getUser( $appData, $username ) === false )
				return false;

			$appData['/nino/auth/user'][$username]['sessions'] = [];
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			if( isset( $appData['./nino/auth/current']['mail'] ) === true && $appData['./nino/auth/current']['mail'] === $username ) {
				\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/current' );
				\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/token' );
				\Nino\Modules\Csrf::rotateToken( $appData );
				unset( $appData['./nino/auth/current'] );
			}

			return true;
		}


		/**
		 *	Check, if current user has a permission
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$perm 				Permission to check
		 *	@param		string				$username 		(optional) Username, otherwise current
		 *
		 *	@return 	boolean 										If user has permission
		 */
		public static function checkPermission( array &$appData, string $perm, string $username = '' ): bool {

			// Get current user data
			$user = ( $username !== '' ) ? self::getUser( $appData, $username ) : ( self::getCurrentUser( $appData ) ?? false );

			if( $user === false )
				return false;

			// Check exact perm
			if( in_array( $perm, $user['perms'] ) === true )
				return true;

			// Check perms recursive
			while( $perm !== '' ) {
				$parentPerms	= substr( $perm, 0, strrpos( $perm, '/' ) );

				if( in_array( $parentPerms. '/*', $user['perms'] ) === true )
					return true;

				$perm = $parentPerms;
			}

			return false;
		}


		/**
		 *	Whether the Csrf module is listed in config.php's /nino/modules -
		 *	case-insensitively, since PHP class names are too (Modules::
		 *	callModules() resolves them via class_exists(), which doesn't
		 *	care about case either - a strict, case-sensitive match here
		 *	could report the module "missing" even though it actually loads
		 *	and runs fine).
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		private static function _csrfModuleEnabled( array &$appData ): bool {

			foreach( $appData['/nino/modules'] ?? [] as $className )
				if( strcasecmp( ltrim( $className, '\\' ), ltrim( \Nino\Modules\Csrf::class, '\\' ) ) === 0 )
					return true;

			return false;
		}

		/**
		 *	Current tries counter for an username - same negative-timestamp
		 *	encoding _registerFailedAttemp() writes: zero/positive is a plain
		 *	attempt count, negative means "still cooling down until -tries"
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$username 		Username
		 *
		 *	@return 	int
		 */
		private static function _getTries( array &$appData, string $username ): int {

			$state = \Nino\Filesystem::getFileContent( $appData, self::TRIES_PATH, [] );

			return $state[$username] ?? 0;
		}

		/**
		 *	Remove a username's tries entry entirely - called on deleteUser()
		 *	so TRIES_PATH doesn't accumulate an orphaned entry for an
		 *	account that no longer exists
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$username 		Username
		 *
		 *	@return 	void
		 */
		private static function _dropTries( array &$appData, string $username ): void {

			unset( $appData['./nino/filesystem/cache'][self::TRIES_PATH] );
			$state = \Nino\Filesystem::getFileContent( $appData, self::TRIES_PATH, [] );

			if( isset( $state[$username] ) === false )
				return;

			unset( $state[$username] );
			\Nino\Filesystem::lockFile( $appData, self::TRIES_PATH );
			\Nino\Filesystem::putFileContent( $appData, self::TRIES_PATH, $state, true );
		}

		/**
		 *	Move a username's tries entry to a new key - called on
		 *	updateUser()'s rename path so an in-progress cooldown survives a
		 *	mail change instead of being silently dropped (reset) or left
		 *	behind as an orphan under the old, now-unused mail
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$oldUsername 	Previous mail
		 *	@param		string				$newUsername 	New mail
		 *
		 *	@return 	void
		 */
		private static function _renameTries( array &$appData, string $oldUsername, string $newUsername ): void {

			unset( $appData['./nino/filesystem/cache'][self::TRIES_PATH] );
			$state = \Nino\Filesystem::getFileContent( $appData, self::TRIES_PATH, [] );

			if( isset( $state[$oldUsername] ) === false )
				return;

			$state[$newUsername] = $state[$oldUsername];
			unset( $state[$oldUsername] );
			\Nino\Filesystem::lockFile( $appData, self::TRIES_PATH );
			\Nino\Filesystem::putFileContent( $appData, self::TRIES_PATH, $state, true );
		}

		/**
		 *	Register a failed login attempt
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array 				$user					User data
		 *
		 *	@return 	false
		 */
		private static function _registerFailedAttemp( array &$appData, array $user ): bool {

			// Drop the cache entry, re-read, then lock and write with nolock -
			// the exact same shape AppData::writeContentData() uses for
			// config.php (see its docblock). A plain unlocked read-modify-
			// write here would let two concurrent failed attempts (eg. a
			// brute-force script hammering the login endpoint from several
			// connections at once) both read the same starting count and
			// overwrite each other's increment - exactly the case this file
			// exists to catch.
			unset( $appData['./nino/filesystem/cache'][self::TRIES_PATH] );
			$state = \Nino\Filesystem::getFileContent( $appData, self::TRIES_PATH, [] );
			$tries = $state[$user['mail']] ?? 0;

			if( $tries >= 0 )
				$tries++;
			else
				$tries = 1;

			// Check max tries
			if( $tries >= $appData['/nino/auth/maxtries'] )
				$tries = 0 - time() - $appData['/nino/auth/cooldown'];

			// Write data change - a dedicated file, not config.php, so this
			// unauthenticated path never triggers a config.php rewrite
			$state[$user['mail']] = $tries;
			\Nino\Filesystem::lockFile( $appData, self::TRIES_PATH );
			\Nino\Filesystem::putFileContent( $appData, self::TRIES_PATH, $state, true );

			return false;
		}
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Callbacks						Simple callback helper
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Callbacks {

		/**
		 *	Register a callback in appData
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		string		$name							Name of callback
		 *	@param		callable	$callback					Callback function
		 *
		 *	@return 	void
		 */
		public static function registerCallback( array &$appData, string $name, mixed $callback, int $prio = 5 ): void {

			if( is_callable( $callback ) === false )
				return;

			$type = 'else';
			if( is_string( $callback ) === true && function_exists( $callback ) === true )
				$type = 'function';
			else if( is_array( $callback ) && is_object( $callback[0] ) === true && method_exists( $callback[0], $callback[1] ) )
				$type = 'objectMethod';
			else if( is_array( $callback ) && is_string( $callback[0] ) && class_exists( $callback[0] ) && method_exists( $callback[0], $callback[1] ) )
				$type = 'classMethod';

			$appData['./nino/callbacks'][$name] = $appData['./nino/callbacks'][$name] ?? [[],[],[],[],[],[],[],[],[],[]];

			if( isset( $appData['./nino/callbacks'][$name][$prio] ) === false )
				$prio = 5;

			$appData['./nino/callbacks'][$name][$prio][] = [
				'type'			=> $type,
				'callback'	=> $callback,
			];
		}

		/**
		 *	Run a callback
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		string		$name							Name of callback
		 *	@param		misc			$args							(optional) arguments for callback
		 *
		 *	@return 	misc												Return value
		 */
		public static function doCallbacks( array &$appData, string $name, mixed &$args = null ): mixed {

			// Check registered callback
			if( isset( $appData['./nino/callbacks'][$name] ) === false )
				return $args;

			// Do callbacks
			foreach( $appData['./nino/callbacks'][$name] AS $prioArray ) {
				foreach( $prioArray as $callback ) {
					if( $callback['type'] === 'objectMethod' )
						$args = $callback['callback'][0]->$callback['callback'][1]( $appData, $args ) ?? $args;
					else if( $callback['type'] === 'classMethod' )
						$args = $callback['callback'][0]::{$callback['callback'][1]}( $appData, $args ) ?? $args;
					else if( $callback['type'] === 'function' )
						$args = $callback['callback']( $appData, $args ) ?? $args;
					else
						$args = call_user_func_array( $callback['callback'], [ &$appData, &$args ] ) ?? $args;
				}
			}

			return $args;
		}
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Filesystem 					Handle all filesystem operations
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Filesystem {


		/**
		 *	Init filesystem
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			$path = $appData['./nino/uid'];
			if( is_dir( $path ) === false ) {
				trigger_error( 'Filesystem path \''. $path. '\' does not exists.' );
				return;
			}
			if( is_writable( $path ) === false ) {
				trigger_error( 'Filesystem path \''. $path. '\' is not writable.' );
				return;
			}
			if( strpos( __DIR__, $path ) !== 0 ) {
				trigger_error( 'Filesystem path \''. $path. '\' is not inside root dir.' );
				return;
			}

			$appData['./nino/filesystem/path'] 	= $path;

		}

		/**
		 *	Read content from file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename of requested file
		 *	@param		misc			$default			(optional) Return value, if file not found
		 *
		 *	@return		string | false					Filecontent or false
		 */
		public static function getFileContent( array &$appData, string $filename, mixed $default = false ): mixed {

			// Every call site is expected to already validate $filename against its
			// own whitelist (element type/uri, locale, image slot, ...) - this ".."
			// rejection is defense-in-depth only, so a call site that forgets to
			// validate its input can't turn into a path-traversal read.
			if( str_contains( $filename, '..' ) === true )
				return $default;

			if( self::_prepareFileCache( $appData, $filename ) === false )
				return $default;

			// Check handle and content
			if( $appData['./nino/filesystem/cache'][$filename]['handle'] === null )
				$appData['./nino/filesystem/cache'][$filename]['handle'] = fopen( $appData['./nino/filesystem/cache'][$filename]['path'], 'r' );

			// Check if we have to read the filecontent
			$stats = fstat( $appData['./nino/filesystem/cache'][$filename]['handle'] );
			if( isset( $appData['./nino/filesystem/cache'][$filename]['fstat']['mtime'] ) === false || $stats['mtime'] !== $appData['./nino/filesystem/cache'][$filename]['fstat']['mtime'] ) {

				flock( $appData['./nino/filesystem/cache'][$filename]['handle'], LOCK_SH );

				// This handle may be the one putFileContent() just wrote
				// through (its own cache slot, reused here) - fwrite() left
				// it positioned at end-of-file, not the start, so fread()
				// below would silently return '' instead of the real
				// content. clearstatcache() matters for the same reason:
				// filesize() is path-based, and PHP's stat cache can still
				// report the pre-write size otherwise, even though fstat()
				// above (handle-based) already sees the fresh mtime.
				rewind( $appData['./nino/filesystem/cache'][$filename]['handle'] );
				clearstatcache( true, $appData['./nino/filesystem/cache'][$filename]['path'] );

				$appData['./nino/filesystem/cache'][$filename]['fstat']['mtime'] = $stats['mtime'];
				$size = filesize( $appData['./nino/filesystem/cache'][$filename]['path'] );

				if( substr( $filename, -4 ) === '.php' )
					$appData['./nino/filesystem/cache'][$filename]['content'] = include $appData['./nino/filesystem/cache'][$filename]['path'];
				else
					$appData['./nino/filesystem/cache'][$filename]['content']	= ( $size > 0 ) ? fread( $appData['./nino/filesystem/cache'][$filename]['handle'], $size ) : '';
				if( substr( $filename, -5 ) === '.json' )
					$appData['./nino/filesystem/cache'][$filename]['content'] = json_decode( $appData['./nino/filesystem/cache'][$filename]['content'], true );

				flock( $appData['./nino/filesystem/cache'][$filename]['handle'], LOCK_UN );
			}

			return $appData['./nino/filesystem/cache'][$filename]['content'];
		}

		/**
		 *	Put content into file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename of requested file
		 *	@param		misc			$content			Content to put
		 *	@param		boolean		$nolock 			(optional) If flock LOCK_EX should be ignored
		 *	@param		boolean		$append 			(optional) Append to file
		 *
		 *	@return		bool										If successful
		 */
		public static function putFileContent( array &$appData, string $filename, mixed $content, bool $nolock = false, bool $append = false ): bool {

			// See getFileContent() - same defense-in-depth ".." rejection, on the write side
			if( str_contains( $filename, '..' ) === true )
				return false;

			self::_prepareFileCache( $appData, $filename );

			self::forceDir( $appData, dirname( ltrim( $filename, '/' ) ) );

			// Create handle - reuse one already locked via lockFile() instead of
			// opening a fresh one: lockFile() stores its locked handle in this same
			// cache slot, and overwriting it here would drop the only reference to
			// that resource, closing it and silently releasing the flock before this
			// write happens. For a fresh handle, open with 'c+' (create, don't
			// truncate) rather than 'w+' - 'w+' truncates the file the instant it's
			// opened, before flock() below runs, so a concurrent reader/writer could
			// observe 0 bytes even though nothing is locked yet.
			$existingHandle = $appData['./nino/filesystem/cache'][$filename]['handle'] ?? null;
			$handle = ( $nolock === true && is_resource( $existingHandle ) === true )
				? $existingHandle
				: fopen( $appData['./nino/filesystem/cache'][$filename]['path'], ( $append === true ) ? 'a' : 'c+' );

			$appData['./nino/filesystem/cache'][$filename]['handle'] = $handle;

			// Lock file
			if( $nolock === false )
				flock( $handle, LOCK_EX );

			// Update cache
			$appData['./nino/filesystem/cache'][$filename]['content']	= $content;

			// Prepare content
			if( substr( $filename, -5 ) === '.json' )
				$content = json_encode( $content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			if( substr( $filename, -4 ) === '.php' )
				$content = '<?php return '. var_export( $content, true ). ';';

			// Write file - fflush() before unlocking matters: fwrite() alone can leave
			// data sitting in PHP's own stream buffer rather than actually on disk yet,
			// so a *different* read of the same path (a different handle/process, or
			// even this same request via a function that doesn't share this handle)
			// can still see the pre-write content/size until it's flushed
			ftruncate( $handle, 0 );
			fwrite( $handle, $content );
			fflush( $handle );

			// Captured after the write, not before - the pre-write mtime
			// used to make getFileContent() think its cache was stale on
			// every single next read of this same file (see its docblock
			// for the actually broken half of that: a stale-cache read
			// reusing this handle without rewinding first)
			$appData['./nino/filesystem/cache'][$filename]['fstat'] = fstat( $handle );
			flock( $handle, LOCK_UN );

			// Reset opcache
			if( function_exists( 'opcache_invalidate' ) === true && is_file( $appData['./nino/filesystem/cache'][$filename]['path'] ) === true )
				opcache_invalidate( $appData['./nino/filesystem/cache'][$filename]['path'] );

			return true;
		}

		/**
		 *	Lock a file for writing via flock
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename of requested file
		 *
		 *	@return		bool										If successful
		 */
		public static function lockFile( array &$appData, string $filename ): bool {

			// Prepare file cache and force parent dir
			if( self::_prepareFileCache( $appData, $filename ) === false )
				return false;

			self::forceDir( $appData, dirname( '/'. ltrim( $filename, '/' ) ) );

			// Create handle
			$appData['./nino/filesystem/cache'][$filename]['handle'] = fopen( $appData['./nino/filesystem/cache'][$filename]['path'], 'r+' );
			$lock = flock( $appData['./nino/filesystem/cache'][$filename]['handle'], LOCK_EX );

			return $lock;
		}

		/**
		 *	Unlock a file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename of requested file
		 *
		 *	@return		bool										If successful
		 */
		public static function unlockFile( array &$appData, string $filename ): bool {

			// Prepare file array cache
			if( self::_prepareFileCache( $appData, $filename ) === false || isset( $appData['./nino/filesystem/cache'][$filename]['handle'] ) === false )
				return false;

			// Unlock
			flock( $appData['./nino/filesystem/cache'][$filename]['handle'], LOCK_UN );

			return true;
		}

		/**
		 *	Check, if a file exists
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename of requested file
		 *
		 *	@return		bool										If file exists
		 */
		public static function fileExists( array &$appData, string $filename ): bool {

			// Force file array cache
			if( self::_prepareFileCache( $appData, $filename ) === false )
				return false;

			// Check file
			return is_file( $appData['./nino/filesystem/cache'][$filename]['path'] );
		}


		/**
		 *	Create a directory, if needed
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$dirpath			Path to required dir
		 *
		 *	@return		void
		 */
		public static function forceDir( array &$appData, string $dirpath ): void {

			$dirpath = $appData['./nino/filesystem/path']. '/'. $dirpath;

			if( is_dir( $dirpath ) === false )
				mkdir( $dirpath, 0777, true );
		}

		/**
		 *	Return current filesystem path
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return		string											Current filesystem path
		 */
		public static function getPath( array &$appData ): string {

			return $appData['./nino/filesystem/path'];

		}

		/**
		 *	Return current filesystem dir
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return		string											Current filesystem dir
		 */
		public static function getDir( array &$appData ): string {

			return $appData['/nino/dir'];

		}

		/**
		 *	Copy recursive files and folder
		 *
		 *	@param		string 			$source				Source file or folder
		 *	@param		string			$dest					Target destination path
		 *
		 *	@return 	bool									If successful
		 */
		public static function copyDir( string $source, string $dest ): bool {

			if( ! file_exists( dirname( $dest ) ) )
				mkdir( dirname( $dest ), 0777, true );

			if( is_file( $source ) )
				return copy( $source, $dest );

			if( ! is_dir( $dest ) )
				mkdir( $dest, 0777, true );

			foreach( scandir( $source ) as $object ) {
				if( $object === '.' || $object === '..' )
					continue;

				self::copyDir( $source. '/'. $object, $dest. '/'. $object );
			}

			return true;
		}


		/**
		 *	Delete recursive files and folder
		 *
		 *	@param		string 	$target				Target file or folder to delete
		 */
		public static function removeDir( string $target ): void {

			if( is_file( $target ) ) {
				unlink( $target );
				return;
			}

			if( ! is_dir( $target ) || is_link( $target ) )
				return;

			foreach( scandir( $target ) as $object ) {
				if( $object === '.' || $object === '..' )
					continue;

				self::removeDir( $target. '/'. $object );
			}

			rmdir( $target );
		}


		/**
		 *	Create a file cache array with handle and infos
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Relative filename
		 *
		 *	@return		bool 										If succesful
		 */
		private static function _prepareFileCache( array &$appData, string $filename ): bool {

			// Check, if already exists - config.php alone resolves against the
			// (optionally outside-webroot) configpath instead of the regular
			// project path, see \Nino\init()
			$base = ( $filename === '/config.php' && ( $appData['./nino/filesystem/configpath'] ?? '' ) !== '' )
				? $appData['./nino/filesystem/configpath']
				: $appData['./nino/filesystem/path'];

			if( isset( $appData['./nino/filesystem/cache'][$filename] ) === false )
				$appData['./nino/filesystem/cache'][$filename] = [
					'handle'			=> null,
					'path'				=> $base. '/'. ltrim( $filename, '/' ),
					'content'			=> '',
					'fstat'				=> [],
				];

			return is_file( $appData['./nino/filesystem/cache'][$filename]['path'] );
		}

	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Elements 						A simple filebased node/element/post integration
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Elements {

		/**
		 *	Get an element from file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uri					Uri of child element
		 *	@param		string		$locale 			Required locale
		 *	@param		misc			$return 			(optional) Return value, if element does not exists.
		 *
		 *	@return 	array | false						Element array or false
		 */
		static public function getElement( array &$appData, string $uri, string $locale = '', mixed $return = false ): mixed {

			// Verify locale
			if( $locale !== '*' && ( $locale === '' || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) )
				$locale = \Nino\Locales::getCurrentLocale( $appData );

			// Add element type / element to cache
			self::_cacheElement( $appData, $uri, $locale );

			return $appData['./nino/elements/cache'][ $uri ][ $locale ] ?? $return;
		}

		/**
		 *	Get an element from file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$typeUri			Type of element
		 *	@param		array			$query 				Search query ( 'key' => 'value' )
		 *	@param		string		$locale 			(optional) Element locale
		 *	@param		misc			$return 			(optional) Return value, if element does not exists.
		 *
		 *	@return 	array | false						Element array or false
		 */
		static public function queryElements( array &$appData, string $typeUri, array $query, string $locale = '', mixed $return = false ): mixed {

			// Verify locale
			if( $locale !== '*' && ( $locale === '' || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) )
				$locale = \Nino\Locales::getCurrentLocale( $appData );

			// Get element type data from file
			$typeData = \Nino\Elements::getElementFile( $appData, $typeUri );
			if( $typeData === false )
				return $return;

			unset( $typeData['model'] );

			// '*' means "any locale" - an element only needs data in ONE locale to
			// exist at all (eg. mid-translation, or a type with no global fields
			// at all, so nothing of it ever lives in the '*' bucket itself). Every
			// available locale has to be considered, not just '*' - treating '*' as
			// if it were a normal locale key here (as $typeData[$locale] with
			// $locale === '*') only ever looked at the '*' bucket against itself,
			// silently missing any element that only has locale-specific data.
			// Deliberately not array_keys($typeData) - besides '*' and one entry
			// per locale, a type file also has non-locale top-level keys (eg. its
			// own display "title", a plain string rather than a data bucket)
			$locales = ( $locale === '*' ) ? \Nino\Locales::getAvailableLocales( $appData ) : [ $locale ];

			$elementUris = array_keys( $typeData['*'] ?? [] );
			foreach( $locales as $l )
				$elementUris = array_merge( $elementUris, array_keys( $typeData[$l] ?? [] ) );
			$elementUris = array_diff( array_unique( $elementUris ), ['*'] );

			// Pre-compute the wildcard-trimmed value/length per query key once,
			// instead of once per element/locale below
			$queryClean = [];
			foreach( $query as $qKey => $kVal ) {
				$kValClean = trim( $kVal, '%' );
				$queryClean[$qKey] = [ $kValClean, strlen( $kValClean ) ];
			}

			// Loop through elements
			$hits = [];
			foreach( $elementUris as $elementUri ) {

				// A hit if the query matches using ANY one of the locales being
				// searched (for a single real locale, $locales has just the one)
				$hit = true;
				foreach( $locales as $l ) {

					$elementData = array_merge( ( $typeData['*']['*'] ?? [] ), ( $typeData[$l]['*'] ?? [] ), ( $typeData['*'][$elementUri] ?? [] ), ( $typeData[$l][$elementUri] ?? [] ) );

					$hit = true;

					foreach( $query as $qKey => $kVal ) {

						[ $kValClean, $kValLen ] = $queryClean[$qKey];

						$hit = false;

						if( isset( $elementData[$qKey] ) === true ) {
							if( $elementData[$qKey] === $kValClean )
								$hit = true;
							else if( $kVal[0] === '%' && $kVal[-1] === '%' && strpos( $elementData[$qKey], $kValClean ) !== false )
								$hit = true;
							else if( $kVal[0] === '%' && substr( $elementData[$qKey], 0-$kValLen ) === $kValClean )
								$hit = true;
							else if( $kVal[-1] === '%' && substr( $elementData[$qKey], 0, $kValLen ) === $kValClean )
								$hit = true;
						}
					}

					if( $hit === true )
						break;
				}

				if( $hit === true )
					$hits[] = \Nino\Elements::getElement( $appData, $typeUri. '/'. $elementUri, $locale );

			}

			return $hits ?? $return;
		}

		/**
		 *	Update an element
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		string		$uri		 					Uri of child element
		 *	@param		array			$data							Data to update
		 *	@param		string		$locale 					Required locale
		 *
		 *	@return 	boolean											If successful
		 */
		static public function updateElement( array &$appData, string $uri, array $data, string $locale = '' ): mixed {

			$element = \Nino\Elements::getElement( $appData, $uri, $locale );

			if( $element === false )
				return ! trigger_error( 'Element \''. $uri. '\' does not exists.' );

			return self::_writeElementData( $appData, $uri, $data, $locale, true );
		}

		/**
		 *	Update an element
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		string		$uri		 					Uri of child element
		 *	@param		array			$data							Data to update
		 *	@param		string		$locale 					Required locale
		 *
		 *	@return 	boolean											If successful
		 */
		static public function insertElement( array &$appData, string $uri, array $data, string $locale = '' ): mixed {

			if( \Nino\Elements::getElement( $appData, $uri, $locale ) !== false )
				return ! trigger_error( 'Element \''. $uri. '\' does already exists.' );

			return self::_writeElementData( $appData, $uri, $data, $locale );
		}

		/**
		 *	Delete an element
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		string		$uri		 					Uri of child element
		 *	@param		string		$locale 					Required locale
		 *
		 *	@return 	boolean											If successful
		 */
		static public function deleteElement( array &$appData, string $uri, string $locale = '' ): mixed {

			// Verify locale
			if( $locale !== '*' && ( $locale === '' || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) )
				$locale = \Nino\Locales::getCurrentLocale( $appData );

			// Flush, reload and lock element file
			$elementUri 	= self::getElementUriFromUri( $uri );
			$typeUri			= self::getElementTypeFromUri( $uri );
			$typeData			= \Nino\Elements::getElementFile( $appData, $typeUri );

			if( $typeData === false )
				return ! trigger_error( 'Element type \''. $typeUri. '\' does not exist.' );

			// Lock file for writing
			\Nino\Filesystem::lockFile( $appData, '/elements/'. $typeUri. '.php' );

			// Unset locale data
			unset( $typeData[$locale][$elementUri] );
			if( empty( $typeData[$locale] ) === true )
				unset( $typeData[$locale] );

			// Delete all (*) - deliberately not iterating $typeData's own keys,
			// as a type file also has non-locale top-level keys (eg. its own
			// display "title", a plain string rather than a data bucket) that
			// would crash unset() on a string offset
			if( $locale === '*' )
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $l )
					unset( $typeData[$l][$elementUri] );

			// Delete last * (only if no other locale still references this element)
			$lastEntry = true;
			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $l )
				if( isset( $typeData[$l][$elementUri] ) === true )
					$lastEntry = false;

			if( $lastEntry === true )
				unset( $typeData['*'][$elementUri] );

			unset( $appData['./nino/elements/cache'] );

			// Run callback
			if( \Nino\Callbacks::doCallbacks( $appData, '/nino/elements/delete'. $typeUri, $typeData ) === false )
				return null;

			// Put element file
			\Nino\Filesystem::putFileContent( $appData, '/elements/'. $typeUri. '.php', $typeData, true );

			return true;
		}



		/**
		 *	Get element file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$typeUri			Uri of required element type
		 *
		 *	@return 	array | false
		 */
		private static function getElementFile( array &$appData, string $typeUri ): array|false {

			// Check filecache
			$appData['./nino/elements/cache'][$typeUri] = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', false );

			if( $appData['./nino/elements/cache'][$typeUri] === false )
				trigger_error( 'Invalid element php file \''. '/elements/'. $typeUri. '.php\'' );

			return $appData['./nino/elements/cache'][$typeUri];
		}

		/**
		 *	Insert an element type
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		string		$typeUri					Uri of element type
		 *	@param		array			$model						Type model
		 *
		 *	@return 	boolean											If successful
		 */
		static public function insertElementType( array &$appData, string $typeUri, array $model ): mixed {

			$typeUri = '/'. ltrim( $typeUri, '/' );

			// Check if element exists
			if( \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', '' ) !== '' )
				return ! trigger_error( 'Element type \''. $typeUri. '\' already exists.' );

			$typeData = [
				'model'			=> [],
				'*'					=> [
					'*'				=> [],
				],
			];

			// Fill model
			foreach( $model AS $key => $data ) {

				if( isset( $data['type'] ) === false || in_array( $data['type'], [ 'string', 'integer', 'array', 'boolean', 'double', 'date', 'datetime', 'image' ] ) === false )
					continue;

				if( isset( $data['required'] ) === true && $data['required'] === true && isset( $data['default'] ) === true && gettype( $data['default'] ) !== self::_expectedGettype( $data['type'] ) )
					continue;

				$typeData['model'][$key] = $data;

				if( isset( $data['default'] ) === true )
					$typeData['*']['*'][$key] = $data['default'];
			}

			\Nino\Filesystem::putFileContent( $appData, '/elements/'. $typeUri. '.php', $typeData );

			return true;
		}


		/**
		 *	Returns an element type model
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		string		$typeUri					Uri of element type
		 *
		 *	@return 	array												Type model
		 */
		static public function getElementModel( array &$appData, string $typeUri ): array {
			$typeData = self::getElementFile( $appData, $typeUri );
			return ( $typeData === false || is_array( $typeData['model'] ?? null ) === false )
				? []
				: $typeData['model'];
		}

		/**
		 *	Get element type from element uri
		 *
		 *	@param		string		$uri					Uri of element
		 *
		 *	@return 	string									Element type
		 */
		static public function getElementTypeFromUri( string $uri ): string {
			$pos = strpos( substr( $uri, 1 ), '/' );
			if( $pos === false ) {
				trigger_error( 'Element uri \''. $uri. '\' has no type separator (expected \'/type/slug\').' );
				return '';
			}
			return substr( $uri, 0, $pos + 1 );
		}

		/**
		 *	Get element part from element uri
		 *
		 *	@param		string		$uri					Uri of element
		 *
		 *	@return 	string									Element uri
		 */
		static public function getElementUriFromUri( string $uri ): string {
			$pos = strpos( substr( $uri, 1 ), '/' );
			if( $pos === false ) {
				trigger_error( 'Element uri \''. $uri. '\' has no type separator (expected \'/type/slug\').' );
				return '';
			}
			return substr( $uri, $pos + 2 );
		}

		/**
		 *	The PHP gettype() a model field's declared type is expected to hold
		 *	as - 'date'/'datetime' values are plain ISO strings (php has no
		 *	native date type) and an 'image' field stores its uploaded file's
		 *	generated filename, also a string; everything else matches its
		 *	type name directly
		 *
		 *	@param		string		$type					Model field type (eg. "date")
		 *
		 *	@return 	string									A gettype() return value (eg. "string")
		 */
		static private function _expectedGettype( string $type ): string {
			return in_array( $type, [ 'date', 'datetime', 'image' ], true ) ? 'string' : $type;
		}

		/**
		 *	Write element data into file content
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uri					Uri of child element
		 *	@param		array 		$data					New element data
		 *	@param		string		$locale 			Required locale
		 *	@param		bool			$update				Update?
		 *
		 *	@return 	void
		 */
		static private function _writeElementData( array &$appData, string $uri, array $data, string $locale, bool $update = false ): mixed {

			// Verify locale
			if( $locale !== '*' && ( $locale === '' || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) )
				$locale = \Nino\Locales::getCurrentLocale( $appData );

			// Flush, reload and lock element file
			$typeUri			= self::getElementTypeFromUri( $uri );
			$typeData			= \Nino\Elements::getElementFile( $appData, $typeUri );

			if( $typeData === false )
				return ! trigger_error( 'Element type \''. $typeUri. '\' does not exist.' );

			// Lock file for writing
			\Nino\Filesystem::lockFile( $appData, '/elements/'. $typeUri. '.php' );

			// Run callbacks
			$callbacks	= [];
			$data['.uri'] 		= $uri;
			$data['.locale']	= $locale;

			foreach( $typeData['model'] AS $key => $field )
				if( isset( $field['callbacks'] ) === true && isset( $data[$key] ) )
					foreach( $field['callbacks'] AS $callbackUri )
						if( \Nino\Callbacks::doCallbacks( $appData, $callbackUri, $data ) === false )
							return ! trigger_error( 'Callback \''. $callbackUri. '\' returns an error.' );

			// Check new element data
			if( is_array( $data ) === false || isset( $data['.uri'] ) === false || isset( $data['.locale'] ) === false )
				return null;

			// Combine old and new data
			if( $update === true )
			$data = $data + \Nino\Elements::getElement( $appData, $uri, '*' );

			// Render/write new element
			$elementUri = self::getElementUriFromUri( $data['.uri'] );

			foreach( $typeData['model'] AS $key => $field ) {

				// Required - a plain empty() would also reject a legitimate 0/false
				// value on a boolean/integer/double field, so presence alone is
				// enough for those; string/array still need an actual non-empty value
				if( isset( $field['required'] ) === true && $field['required'] === true ) {
					$isEmpty = match( true ) {
						isset( $data[$key] ) === false 																								=> true,
						in_array( $field['type'], [ 'boolean', 'integer', 'double' ], true ) === true => false,
						is_array( $data[$key] ) === true 																							=> count( $data[$key] ) === 0,
						default 																																				=> $data[$key] === '',
					};
					if( $isEmpty === true )
						return ! trigger_error( 'Missing required element key \''. $key. '\' in \''. $uri. '\'.' );
				}

				// Default
				if( isset( $field['default'] ) === true && ( isset( $data[$key] ) === false || $field['default'] === $data[$key] ) )
					continue;

				// Check key
				if( isset( $data[$key] ) === false )
					continue;

				// Var type (a whole-number 'double' round-trips through json as an 'integer', accept and coerce it)
				if( $field['type'] === 'double' && gettype( $data[$key] ) === 'integer' )
					$data[$key] = (float) $data[$key];

				if( gettype( $data[$key] ) !== self::_expectedGettype( $field['type'] ) )
					return ! trigger_error( 'Wrong var type \''. $key. '\' in \''. $uri. '\'. \''. $field['type']. '\' required, \''. gettype( $data[$key] ). '\' given.' );

				// Whitelist
				if( isset( $field['whitelist'] ) === true && in_array( $data[$key], $field['whitelist'] ) === false )
					return ! trigger_error( 'Element value \''. $key. '\' is not whitelisted.' );

				// Blacklist
				if( isset( $field['blacklist'] ) === true && in_array( $data[$key], $field['blacklist'] ) === true )
					return ! trigger_error( 'Element value \''. $key. '\' is blacklisted.' );

				$targetArray = ( isset( $typeData['model'][$key]['locale'] ) === true && $typeData['model'][$key]['locale'] === true ) ? $data['.locale'] : '*';


				// Set value
				$typeData[$targetArray][$elementUri] = $typeData[$targetArray][$elementUri] ?? [];
				$typeData[$targetArray][$elementUri][$key] = $data[$key];
			}

			$typeData['*'][$elementUri] = $typeData['*'][$elementUri] ?? [];

			// Check uri change
			if( $uri !== $data['.uri'] ) {
				\Nino\Callbacks::doCallbacks( $appData, '/nino/elements'. $typeUri. '/update/uri', $data );
				$elementUri = self::getElementUriFromUri( $uri );
				foreach( $typeData AS $locale => $localeData )
					unset( $typeData[$locale][$elementUri] );
			}

			unset( $appData['./nino/elements/cache'] );

			// Run callback - lets a module react to (or veto, by returning
			// false) a save, same veto-capable shape as deleteElement()'s
			// own '/nino/elements/delete<typeUri>' callback below
			$callbackName = '/nino/elements'. $typeUri. ( $update === true ? '/update' : '/insert' );
			if( \Nino\Callbacks::doCallbacks( $appData, $callbackName, $typeData ) === false )
				return null;

			// Put element file
			\Nino\Filesystem::putFileContent( $appData, '/elements/'. $typeUri. '.php', $typeData, true );

			return \Nino\Elements::getElement( $appData, $data['.uri'], $data['.locale'] );
		}

		/**
		 *	Load an element into app cache. Resolves '*' to the actual locale
		 *	it found data in (reference), so the caller can look up that same
		 *	cache slot afterwards.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uri					Uri of child element
		 *	@param		string		&$locale 			(reference) Requested locale, resolved in place if '*'
		 *
		 *	@return 	void
		 */
		static private function _cacheElement( array &$appData, string $uri, string &$locale ): void {

			// Get uris
			$typeUri		= self::getElementTypeFromUri( $uri );
			$elementUri	= self::getElementUriFromUri( $uri );

			if( $locale !== '*' && isset( $appData['./nino/elements/cache'][$uri][$locale] ) === true )
				return;

			// Get element type data from file
			$typeData = \Nino\Elements::getElementFile( $appData, $typeUri );
			if( $typeData === false )
				return;

			// Catch native/fallback locale: find the first locale that actually
			// has data for this element, else leave $locale at '*' (global-only)
			if( $locale === '*' )
				foreach( $typeData AS $typeLocale => $typeElement )
					if( $typeLocale !== 'model' && $typeLocale !== '*' && isset( $typeElement[$elementUri] ) === true ) {
						$locale = $typeLocale;
						break;
					}

			if( isset( $typeData[$locale][$elementUri] ) === false && isset( $typeData['*'][$elementUri] ) === false )
				return;

			// Create element
			$defaults 				= [ '.locale' => $locale, '.uri' => $uri ];
			$localeDefaults		= $typeData[$locale]['*'] ?? [];
			$globalDefaults		= $typeData['*']['*'] ?? [];
			$localeData 			= $typeData[$locale][$elementUri] ?? [];
			$globalData				= $typeData['*'][$elementUri] ?? [];


			// Combine data
			$appData['./nino/elements/cache'][$uri] = $appData['./nino/elements/cache'][$uri] ?? [];
			$appData['./nino/elements/cache'][$uri][$locale] = $localeData + $globalData + $localeDefaults + $globalDefaults + $defaults;
		}

	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Html 								All html related methods
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Html {

		private const array HTML_TAGS = [ 'strong', 'em', 'span', 'a' ];

		/**
		 *	Prepare a response
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					&$request			(reference) Current request
		 *
		 *	@return		array | null 							Webpage array or null
		 */
		public static function response( array &$appData, array &$request ): void {
			if( is_string( $request['/nino/http/response']['body'] ) === true )
				$request['/nino/http/response']['body'] = self::renderHtml( $appData, $request['/nino/http/response']['body'] );
		}

		/**
		 *	Render text and shortcode replacements on a html text
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$html					Html to render
		 *
		 *	@return 	null
		 */
		public static function renderHtml( array &$appData, string $html ): string {

			$html = self::_renderFills( $appData, $html );
			$html = self::_renderShortcodes( $appData, $html );

			$html = \Nino\Callbacks::doCallbacks( $appData, '/nino/html/render', $html );

			return $html;
		}

		/**
		 *	Render a textfills in current language
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$fill					Textfill to render
		 *
		 *	@return 	string											Rendered fill
		 */
		public static function renderTextfill( array &$appData, string $fill ): string {

			$fills = self::getFills( $appData );

			return $fills[ '[['. $fill. ']]'] ?? '';
		}

		/**
		 *	Add an asset to global list
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$library			Target assets library
		 *	@param		string				$assetfile		Path to asset file
		 *
		 *	@return 	void
		 */
		public static function addAsset( array &$appData, string $library, string $assetfile ): void {

			$appData['/nino/html/assets'][$library] = $appData['/nino/html/assets'][$library] ?? [];

			if( in_array( $assetfile, $appData['/nino/html/assets'][$library] ) === false )
				$appData['/nino/html/assets'][$library][] = $assetfile;
		}

		/**
		 *	Bind a shortcode to an event
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$shortcode		Name of text tag
		 *	@param									$callback			Target callback
		 *
		 *	@return 	void
		 */
		public static function addShortcode( array &$appData, string $shortcode, mixed $callback ): void {

			// Add shortcode in appData
			$appData['./nino/html/shortcodes'][$shortcode] = $shortcode;

			// Add callback and clear cache
			\Nino\Callbacks::registerCallback( $appData, '/nino/html/shortcode/'. $shortcode, $callback );
			$appData['./nino/html/cache'] = false;
		}

		/**
		 *	Add textfills
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$fills				Textfills to add
		 *	@param		string		$locale 			(optional) Target locale or current
		 *
		 *	@return 	void
		 */
		public static function addFills( array &$appData, array $fills, string $locale = ''  ): void {

			// Check locale
			if( $locale === '' )
				$locale = \Nino\Locales::getCurrentLocale( $appData );
			else if( $locale !== '*' && \Nino\Locales::verifyLocale( $appData, $locale ) === false )
				return;

			$appData['./nino/html/fills'][$locale] = $appData['./nino/html/fills'][$locale] ?? [];

			foreach( $fills as $fillKey => $fillValue )
				$appData['./nino/html/fills'][$locale]['[['. trim( $fillKey, '[[]]' ). ']]'] = $fillValue;
		}

		/**
		 *	Return all current assets with an extension
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$library			Target assets library
		 *
		 *	@return 	array 											Asset files
		 */
		public static function getAssets( array &$appData, string $library ): array {
			return $appData['/nino/html/assets'][$library] ?? [];
		}

		/**
		 *	Return all current textfills
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return 	array 											Fill data
		 */
		public static function getFills( array &$appData ): array {

			$locale = \Nino\Locales::getCurrentLocale( $appData );

			return array_merge(
				\Nino\Filesystem::getFileContent( $appData, $appData['/nino/locales/textfiles']. '/global.php', [] ),
				\Nino\Filesystem::getFileContent( $appData, $appData['/nino/locales/textfiles']. '/'. $locale. '.php', [] ),
				( $appData['./nino/html/fills'][$locale] ?? [] ),
				( $appData['./nino/html/fills']['*'] ?? [] )
			);
		}

		/**
		 *	Render shortcodes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$html 				Html to render
		 *
		 *	@return 	string									Application event return value or empty string
		 */
		private static function _renderShortcodes( array &$appData, string $html ): string {

			// Check html text for [ or [[
			if( $html === '' || strpos( $html, '[' ) === false || empty( $appData['./nino/html/shortcodes'] ) === true )
				return $html;

			// Run shortcodes
			if( $appData['./nino/html/cache'] === false )
				$appData['./nino/html/cache'] = implode( '|', array_keys( ( $appData['./nino/html/shortcodes'] ?? [] ) ) );

			// A closure capturing $appData by reference, not the previous
			// static-property-as-callback-target approach: preg_replace_callback()
			// only accepts a callable, but binding $appData to a class property
			// so the static _doShortcode() could reach it meant any addAsset()/
			// addFills()/addShortcode() call a shortcode's own callback made
			// was silently lost - the property held a copy, not the caller's
			// actual array. The closure sidesteps that entirely: it's a real
			// reference to $appData, exactly like passing it as a parameter
			// anywhere else in this file.
			$callback = function( array $pregArgs ) use ( &$appData ): string {
				return self::_doShortcode( $appData, $pregArgs );
			};
			$html = preg_replace_callback( '/\[('. $appData['./nino/html/cache'] . ')(?: ([^\]]*))?\](?:([^\[]*+(?:\[(?!\/\1\])[^\[]*+)*+)(?:\[\/(?:\1)\]))?/', $callback, $html );

			return $html;
		}

		/**
		 *	Render textfills
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$html 				Html to render
		 *
		 *	@return 	string									Application event return value or empty string
		 */
		private static function _renderFills( array &$appData, string $html ): string {

			if( substr_count( $html, '[[' ) === 0 )
				return $html;

			$fills			= self::getFills( $appData );
			$fillKeys		= array_keys( $fills );
			$fillValues	= array_values( $fills );

			// Comparing the rendered string itself (not just its '[[' count)
			// catches fill values that reference another fill of their own
			// (eg. /form/subject/owner containing [[/website/url]]) - a
			// same-count swap would otherwise look "stable" after one pass.
			// The pass cap guards against a fill value that references
			// itself (possible via _admin's Text editor, not just the
			// developer-authored defaults).
			for( $pass = 0; $pass < 10; $pass++ ) {
				$rendered = str_replace( $fillKeys, $fillValues, $html );
				if( $rendered === $html )
					break;
				$html = $rendered;
			}

			return $html;
		}

		/**
		 *	Method
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$pregArgs			Arguments from preg_replace_callback()
		 *
		 *	@return 	string									Application event return value or empty string
		 */
		private static function _doShortcode( array &$appData, array $pregArgs ): string {

			// Read preg arguments
			$shortcode	= $pregArgs[1];
			$content		= $pregArgs[3] ?? '';

			// Check shortcode
			if( isset( $appData['./nino/html/shortcodes'][$shortcode] ) === false )
				return '';

			// Split shortcode arguments
			$args = [];
			if( isset( $pregArgs[2] ) === true && $pregArgs[2] !== '' ) {
				preg_match_all( '/\ ([^\ \=]*)(?:\=[\"]([^\"]*)[\"])?/i', ' '.$pregArgs[2].' ', $attr );

				foreach( $attr[1] AS $id => $key ) {

					$value = ( $attr[2][$id] !== '' ) ? str_replace( '\'', '"', $attr[2][$id] ) : $attr[1][$id];

					if( $value === $key )
						$args[] 		= substr( $value, 0 );
					else
						$args[$key] = $value;
				}

				array_pop( $args );
			}

			// Modify arguments
			if( strlen( $content ) > 0 )
				$args['content'] = $content;

			$value = \Nino\Callbacks::doCallbacks( $appData, '/nino/html/shortcode/'. $shortcode, $args );
			$value = self::renderHtml( $appData, $value );

			// Return value or empty string
			return ( is_string( $value ) === true ) ? $value : '';
		}

		/**
		 *	Whether a value currently contains one of the allowed inline tags -
		 *	used to auto-decide whether a field/key gets the html editor.
		 *	Shared by every domain class with a model/entry 'html' flag
		 *	(_admin's Text/Elements, _dev's Text).
		 *
		 *	@param		string		$value				Value to check
		 *
		 *	@return 	bool
		 */
		public static function containsHtml( string $value ): bool {
			return preg_match( '/<(?:'. implode( '|', self::HTML_TAGS ). ')[ >]/i', $value ) === 1;
		}

		/**
		 *	Rebuild a html value, keeping only whitelisted inline tags (strong/
		 *	em/span/a) one level deep and a safe href scheme on links. Never
		 *	trust the client's html: the editor's "no nesting" toolbar rule is
		 *	enforced here too, against a client that bypasses it entirely.
		 *
		 *	@param		string		$html					Raw html from the client
		 *
		 *	@return 	string									Sanitized html
		 */
		public static function sanitizeHtml( string $html ): string {

			if( trim( $html ) === '' )
				return '';

			$doc = new \DOMDocument();

			libxml_use_internal_errors( true );
			$doc->loadHTML( '<?xml encoding="utf-8"?><div>'. $html. '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );
			libxml_clear_errors();

			$wrap = $doc->getElementsByTagName( 'div' )->item( 0 );

			return $wrap === null ? '' : self::_sanitizeChildren( $wrap, false );
		}

		/**
		 *	Recursively rebuild a node's children, keeping only whitelisted
		 *	inline tags one level deep - a whitelisted tag found while already
		 *	inside another one is unwrapped (kept as plain content)
		 *
		 *	@param		\DOMNode	$node					Node whose children to rebuild
		 *	@param		bool			$insideInline	Whether an ancestor is already a whitelisted inline tag
		 *
		 *	@return 	string									Rebuilt html
		 */
		private static function _sanitizeChildren( \DOMNode $node, bool $insideInline ): string {

			$out = '';

			foreach( iterator_to_array( $node->childNodes ) as $child ) {

				if( $child->nodeType === XML_TEXT_NODE ) {
					// ENT_NOQUOTES: this is text content, not an attribute value - quotes need no escaping here
					$out .= htmlspecialchars( $child->textContent, ENT_NOQUOTES, 'UTF-8' );
					continue;
				}

				if( $child->nodeType !== XML_ELEMENT_NODE )
					continue;

				$tag = strtolower( $child->nodeName );

				if( in_array( $tag, self::HTML_TAGS, true ) === false || $insideInline === true ) {
					$out .= self::_sanitizeChildren( $child, $insideInline );
					continue;
				}

				$inner = self::_sanitizeChildren( $child, true );

				if( $tag === 'a' ) {
					$href = self::_safeHref( $child->getAttribute( 'href' ) );
					$out .= ( $href === null ) ? $inner : '<a href="'. htmlspecialchars( $href, ENT_QUOTES, 'UTF-8' ). '">'. $inner. '</a>';
					continue;
				}

				$out .= '<'. $tag. '>'. $inner. '</'. $tag. '>';
			}

			return $out;
		}

		/**
		 *	Validate a link href: only relative/fragment uris or a handful of
		 *	safe schemes - blocks javascript: and similar injection vectors
		 *
		 *	@param		string		$href					Raw href attribute value
		 *
		 *	@return 	string | null						The href if safe, else null
		 */
		private static function _safeHref( string $href ): string|null {

			$href = trim( $href );

			if( $href === '' )
				return null;

			if( $href[0] === '/' || $href[0] === '#' )
				return $href;

			return preg_match( '#^(https?|mailto|tel):#i', $href ) === 1 ? $href : null;
		}
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Http 								All http related methods
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Http {

		private static
			$_defaultResponse = [
				'statusCode'	=> 404,
				'header'			=> [
					'Strict-Transport-Security' 	=> 'max-age=31536000; includeSubDomains',
					'Content-Security-Policy' 		=> 'default-src \'self\'; img-src *; style-src \'self\' \'unsafe-inline\'',
					'X-Frame-Options' 						=> 'same-origin',
					'X-Content-Type-Options'			=> 'nosniff',
				],
				'body'				=> '',
				'uri'					=> '',
			];

		/**
		 *	Prepare a request
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					&$request			(reference) Current request
		 *
		 *	@return		array | null 							Webpage array or null
		 */
		public static function request( array &$appData, array &$request ): void {

			$currentLocale = \Nino\Locales::getCurrentLocale( $appData );

			// Add request/response values
			$request['/nino/http/request'] = [
				'method'				=> self::_cleanRawMethod( $request['REQUEST_METHOD'] ),
				'uri'						=> self::cleanUri( $request['REQUEST_URI'] ),
				'query'					=> self::_getRequestQueryVarsPart( $request['REQUEST_URI'] ),
				'header'				=> self::_filterRequestHeaderFields( $request ),
				'body'					=> file_get_contents( 'php://input' ),
				'user'					=> ( $request['PHP_AUTH_USER'] ?? '' ),
				'pw'						=> ( $request['PHP_AUTH_PW'] ?? '' ),
				'ip'						=> self::getClientIp(),
			];
			// Seed the response header with the default security headers, so a
			// callback that extends one of them (eg. Jstext appending its
			// nonce to the Content-Security-Policy) works on the real value
			// instead of an empty string that would later shadow the default
			$request['/nino/http/response'] = [
				'uri'					=> $request['/nino/http/request']['uri'],
				'locale'			=> $currentLocale,
				'header'			=> self::$_defaultResponse['header'],
				'body'				=> '',
				'statusCode'	=> 200,
			];

			array_unshift( $appData['./nino/http/requests'], $request );

			\Nino\Callbacks::doCallbacks( $appData, '/nino/http/request', $request );
		}

		/**
		 *	Prepare a get response
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					&$request			(reference) Current request
		 *
		 *	@return		array | null 							Webpage array or null
		 */
		public static function response( array &$appData, array &$request ): void {

			// Find current route
			$routeData = self::requestRoute( $appData, $request['/nino/http/request']['uri'], $request['/nino/http/request']['method'] ) ??
				self::requestRoute( $appData, '/404', 'GET' ) ??
				[ '.uri' => '/404', 'statusCode' => 404 ];

			// A route's own header fields extend the seeded response header
			// (see request()) instead of replacing the whole array - eg.
			// robots.txt's Content-Type must not wipe the security defaults
			if( isset( $routeData['header'] ) === true )
				$routeData['header'] = array_merge( $request['/nino/http/response']['header'], $routeData['header'] );

			$request['/nino/http/response'] = array_merge( $request['/nino/http/response'], $routeData );

			\Nino\Callbacks::doCallbacks( $appData, '/nino/http/response', $request );
			\Nino\Callbacks::doCallbacks( $appData, '/nino/http/response/'. $request['/nino/http/request']['method']. ':/'. $request['/nino/http/response']['uri'], $request );
		}



		/**
		 *	Output a http response
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					&$request			(reference) Current request
		 *
		 *	@return		array | null 							Webpage array or null
		 */
		public static function output( array &$appData, array &$request ): never {

			// Catch json output
			if( is_string( $request['/nino/http/response']['body'] ) === false ) {
				$request['/nino/http/response']['body'] = json_encode( $request['/nino/http/response']['body'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
				$request['/nino/http/response']['header']['Content-Type'] = 'application/json; charset=utf-8';
			}

			// Add default response keys
			$request['/nino/http/response'] = array_merge( self::$_defaultResponse, $request['/nino/http/response'] );
			$request['/nino/http/response']['header'] = array_merge( self::$_defaultResponse['header'], self::filterHeaderFields( $request['/nino/http/response']['header'] ) );

			// Output header
			if( headers_sent() === false )
				foreach( $request['/nino/http/response']['header'] AS $headerKey => $headerValue )
					header( $headerKey. ':'. $headerValue );

			// Send status code
			http_response_code( $request['/nino/http/response']['statusCode'] );

			// Send body
			echo $request['/nino/http/response']['body'];
			exit;
		}

		/**
		 *	Search a route
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$uri					Requested uri
		 *	@param		string				$method				Request method
		 *
		 *	@return		array | null 							Webpage array or null
		 */
		public static function requestRoute( array &$appData, string $uri, string $method ): ?array {

			$result 		= $appData['/nino/http/routes'][$method. ':/'. $uri] ?? null;
			$parentUri	= $uri;

			while( $parentUri !== '/' && $result === null ) {
				$parentUri	= dirname( $parentUri );
				$result 		= $appData['/nino/http/routes'][$method. ':/'. $parentUri.'/*'] ?? null;
			}

			return $result;
		}

		/**
		 *	Find the route key (eg. "GET://about") that renders a given
		 *	response uri in a specific locale, eg. to redirect to the
		 *	locale-specific variant of the current page
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$responseUri	Uri as currently set in the response (eg. "/about")
		 *	@param		string				$locale				Required locale
		 *
		 *	@return		string | null							Route key, or null if none found
		 */
		public static function findRouteUri( array &$appData, string $responseUri, string $locale ): ?string {

			foreach( $appData['/nino/http/routes'] as $routeUri => $routeData )
				if( $routeData['uri'] === $responseUri && ( isset( $routeData['locale'] ) === false || $routeData['locale'] === $locale ) )
					return $routeUri;

			return null;
		}

		/**
		 *	Search a route
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		int						$offset				Offset of requested request in request array
		 *
		 *	@return		array | false 							Request array or null
		 */
		public static function getRequest( array &$appData, int $offset = 0 ): array|false {
			return $appData['./nino/http/requests'][$offset] ?? false;
		}

		/**
		 *	Return clean uri
		 *
		 *	@param		string 	$rawUri				Raw uri string
		 *
		 *	@return 	string								Clean uri
		 */
		static private function cleanUri( string $rawUri ): string {

			// Clean uri
			$cleanUri = strtok( $rawUri, '#' );
			$cleanUri = strtok( $cleanUri, '?' );
			$cleanUri = preg_replace( '/[^a-zA-Z0-9:\/\.~_\-%]/', '', $cleanUri );
			$cleanUri = rtrim( $cleanUri, '/' );

			if( $cleanUri === '' )
				$cleanUri = '/';

			return $cleanUri;
		}

		/**
		 *	Return current client ip address. Only the actual TCP peer address
		 *	(REMOTE_ADDR) is trusted - Client-Ip/X-Forwarded-For are ordinary
		 *	request headers any client can set to an arbitrary value, and Nino
		 *	has no trusted-proxy configuration to verify them against. Trusting
		 *	them here would let a session's ip-pinning check (see Auth) and the
		 *	activity log's ip field both be spoofed by the request itself.
		 *
		 *	@return 	string											Current ip address
		 */
		public static function getClientIp(): string {

			return $_SERVER['REMOTE_ADDR'] ?? '';
		}

		/**
		 *	Build the request-side header array from $_SERVER (passed in as
		 *	$rawServer). PHP exposes request headers as HTTP_FOO_BAR keys
		 *	(Content-Type/-Length are the CGI-spec exception, without the
		 *	HTTP_ prefix) - filterHeaderFields() expects real header names
		 *	like 'Foo-Bar' as keys, which is what the response side already
		 *	has, so this normalizes the request side to match before reusing
		 *	it. The exact case reached here doesn't matter - filterHeaderFields()
		 *	now matches case-insensitively and returns its own whitelist's
		 *	casing, since header names are case-insensitive per HTTP anyway.
		 *
		 *	@param		array 	$rawServer					Raw $_SERVER-shaped array
		 *
		 *	@return 	array												Filtered array, real header names as keys
		 */
		static private function _filterRequestHeaderFields( array $rawServer ): array {

			$normalized = [];

			foreach( $rawServer as $key => $value ) {

				if( str_starts_with( $key, 'HTTP_' ) === true )
					$headerName = substr( $key, 5 );
				elseif( in_array( $key, [ 'CONTENT_TYPE', 'CONTENT_LENGTH' ], true ) === true )
					$headerName = $key;
				else
					continue;

				$normalized[str_replace( '_', '-', $headerName )] = $value;
			}

			return self::filterHeaderFields( $normalized );
		}

		/**
		 *	Filter all non-http keys from an array. Matches case-insensitively
		 *	and normalizes to the whitelist's own casing - a naive exact
		 *	match previously turned eg. the request side's 'TE' into 'Te' via
		 *	ucwords() and then silently dropped it, since that no longer
		 *	matched the whitelist's literal 'TE' entry.
		 *
		 *	@param		array 	$headerArray				Raw array with request header fields
		 *
		 *	@return 	array												Filtered array
		 */
		static public function filterHeaderFields( array $headerArray ): array {

			$allowed = [ 'Accept', 'Accept-Charset', 'Accept-Encoding', 'Accept-Language', 'Authorization', 'Cache-Control', 'Connection', 'Content-Length', 'Content-Type', 'Cookie', 'Date', 'Expect', 'From', 'Host', 'If-Modified-Since', 'If-None-Match', 'Location', 'Max-Forwards', 'Origin', 'Pragma', 'Proxy-Authorization', 'Range', 'Referer', 'TE', 'User-Agent', 'Upgrade', 'Via', 'Warning', 'X-CSRF-Token', 'X-Frame-Options', 'X-Content-Type-Options', 'Strict-Transport-Security', 'Content-Security-Policy', 'Referrer-Policy', 'Feature-Policy', 'Permissions-Policy' ];

			$filteredArray = [];

			foreach( $headerArray as $key => $value )
				foreach( $allowed as $canonical )
					if( strcasecmp( $key, $canonical ) === 0 ) {
						$filteredArray[$canonical] = $value;
						break;
					}

			return $filteredArray;
		}


		/**
		 *	Return query get vars from get request uri string
		 *
		 *	@param		string 	$rawRequest		Raw request
		 *
		 *	@return 	array									All query get vars
		 */
		private static function _getRequestQueryVarsPart( string $rawRequest ): array {

			// Parse query vars
			$queryVars = [];
			$parsedUrl = parse_url( $rawRequest, PHP_URL_QUERY ) ?? '';
			parse_str( $parsedUrl, $queryVars );

			return $queryVars;
		}


		/**
		 *	Clean request methods
		 *
		 *	@param		string 	$rawMethod			Raw requested method
		 *	@param		array 	$legalMethods		(optional) Array with all legal methods
		 *
		 *	@return 	string 									Cleaned method
		 */
		static private function _cleanRawMethod( string $rawMethod, array $legalMethods = [] ): string {

			if( $legalMethods === [] )
				$legalMethods = ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH' ];

			$cleanMethod	= preg_replace( '/[^a-zA-Z]/', '', $rawMethod );
			$cleanMethod	= strtoupper( $cleanMethod );

			foreach( $legalMethods AS $method )
				if( strpos( $cleanMethod, $method ) !== false )
					return $method;

			return '';
		}
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Images							Upload -> validate -> centered crop/resize -> store,
	 *											shared by Elements' "image" field type and any
	 *											developer-fixed image slot (eg. a future Admin
	 *											media area). Every image is re-encoded via gd from
	 *											scratch, never the uploaded bytes as-is - besides
	 *											giving a predictable output format, that also
	 *											discards anything a crafted file might carry beyond
	 *											actual pixel data.
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Images {

		private const int MAX_UPLOAD_BYTES 		= 8 * 1024 * 1024;
		private const int MAX_SOURCE_PIXELS 		= 8000;
		private const string UPLOAD_DIR 				= '/images';

		/**
		 *	Validate, center-crop and resize raw uploaded image bytes to exactly
		 *	$targetWidth x $targetHeight, then store the result at $basePath
		 *	with an extension appended for the chosen output format - the
		 *	caller picks $basePath deterministically (eg. "elements/<type>/<uri>"),
		 *	so re-uploading the same slot overwrites in place rather than
		 *	accumulating orphaned files
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$bytes				Raw uploaded file content
		 *	@param		int				$targetWidth	Target width in pixels
		 *	@param		int				$targetHeight	Target height in pixels
		 *	@param		string		$basePath			Deterministic path (relative to /images), without extension
		 *
		 *	@return 	string|false						The stored filename (relative to /images, incl. extension), or false
		 */
		public static function process( array &$appData, string $bytes, int $targetWidth, int $targetHeight, string $basePath ): string|false {

			if( $bytes === '' || strlen( $bytes ) > self::MAX_UPLOAD_BYTES || $targetWidth < 1 || $targetHeight < 1 || $basePath === '' )
				return false;

			// $basePath is built from a type/uri/key that are already each individually
			// validated by the caller, but never trust a path past this point regardless
			if( str_contains( $basePath, '..' ) === true || str_starts_with( $basePath, '/' ) === true )
				return false;

			$info = @getimagesizefromstring( $bytes );
			if( $info === false || in_array( $info[2], [ IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP ], true ) === false )
				return false;

			if( $info[0] > self::MAX_SOURCE_PIXELS || $info[1] > self::MAX_SOURCE_PIXELS )
				return false;

			$source = @imagecreatefromstring( $bytes );
			if( $source === false )
				return false;

			$sourceWidth 	= imagesx( $source );
			$sourceHeight	= imagesy( $source );

			// Centered crop: the largest rectangle matching the target aspect ratio
			// that fits inside the source, centered, then resized down/up onto it
			$targetRatio = $targetWidth / $targetHeight;
			$sourceRatio = $sourceWidth / $sourceHeight;

			if( $sourceRatio > $targetRatio ) {
				$cropHeight	= $sourceHeight;
				$cropWidth	= (int) round( $sourceHeight * $targetRatio );
			} else {
				$cropWidth	= $sourceWidth;
				$cropHeight	= (int) round( $sourceWidth / $targetRatio );
			}

			$cropX = (int) round( ( $sourceWidth - $cropWidth ) / 2 );
			$cropY = (int) round( ( $sourceHeight - $cropHeight ) / 2 );

			// Alpha-aware output: png (with transparency preserved) for a source that
			// might carry it, jpeg otherwise - keeps photos small, logos crisp
			$keepAlpha = in_array( $info[2], [ IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP ], true );

			$canvas = imagecreatetruecolor( $targetWidth, $targetHeight );

			if( $keepAlpha === true ) {
				imagealphablending( $canvas, false );
				imagesavealpha( $canvas, true );
				imagefill( $canvas, 0, 0, imagecolorallocatealpha( $canvas, 0, 0, 0, 127 ) );
			}

			imagecopyresampled( $canvas, $source, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $cropWidth, $cropHeight );
			imagedestroy( $source );

			ob_start();
			if( $keepAlpha === true ) {
				imagepng( $canvas, null, 8 );
			} else {
				// Progressive encoding (renders a low-res pass immediately, then
				// sharpens - faster perceived load on a slow connection) and quality
				// 82 rather than 85 - the standard sweet spot for web photos, smaller
				// files with no visible difference at normal viewing sizes
				imageinterlace( $canvas, true );
				imagejpeg( $canvas, null, 82 );
			}
			$encoded = ob_get_clean();
			imagedestroy( $canvas );

			if( $encoded === false || $encoded === '' )
				return false;

			$filename = $basePath. ( '.'. $targetWidth. 'x'. $targetHeight ). ( $keepAlpha ? '.png' : '.jpg' );

			if( \Nino\Filesystem::putFileContent( $appData, self::UPLOAD_DIR. '/'. $filename, $encoded ) === false )
				return false;

			return $filename;
		}

		/**
		 *	Delete a previously stored image
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename as returned by process()
		 *
		 *	@return 	void
		 */
		public static function delete( array &$appData, string $filename ): void {

			// process() only ever hands out names it generated itself (nested under
			// our own upload dir, eg. "elements/<type>/<uri>.jpg"), but a stored
			// value could in theory have been hand-edited, so never trust it blindly
			if( $filename === '' || str_contains( $filename, '..' ) === true || str_starts_with( $filename, '/' ) === true )
				return;

			$path = \Nino\Filesystem::getPath( $appData ). self::UPLOAD_DIR. '/'. $filename;
			if( is_file( $path ) === true )
				unlink( $path );
		}

		/**
		 *	Public url for a stored image
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename as returned by process()
		 *
		 *	@return 	string
		 */
		public static function getUrl( array &$appData, string $filename ): string {
			return \Nino\Filesystem::getDir( $appData ). self::UPLOAD_DIR. '/'. $filename;
		}

		/**
		 *	Every developer-fixed image slot ("/nino/html/images" in config.php) -
		 *	unlike an Element's "image" field, slots themselves can't be added or
		 *	removed from the admin, only the file each currently points to changes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Slot uri => [ label, width, height, filename ]
		 */
		public static function getSlots( array &$appData ): array {
			return $appData['/nino/html/images'] ?? [];
		}

		/**
		 *	One image slot's definition
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uri					Slot uri
		 *
		 *	@return 	array|false
		 */
		public static function getSlot( array &$appData, string $uri ): array|false {
			return self::getSlots( $appData )[$uri] ?? false;
		}

		/**
		 *	Record a new filename for an existing slot and persist it - only
		 *	the /nino/html/images key of config.php, same as Auth::updateUser()
		 *	only persists /nino/auth/user (see AppData::writeContentData())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uri					Slot uri
		 *	@param		string		$filename			Filename as returned by process()
		 *
		 *	@return 	bool										If successful (false if the slot doesn't exist)
		 */
		public static function setSlotFilename( array &$appData, string $uri, string $filename ): bool {

			if( self::getSlot( $appData, $uri ) === false )
				return false;

			$appData['/nino/html/images'][$uri]['filename'] = $filename;
			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			return true;
		}
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Locales 						Handles the framework locale
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Locales {

		/**
		 *	Init module
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// Get current locale
			$currentLocale = \Nino\Runtime::getSessionValue( $appData, './nino/locales/current' );

			if( $currentLocale !== null )
				\Nino\Locales::setCurrentLocale( $appData, $currentLocale );
		}

		/**
		 *	Init module
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current request
		 *
		 *	@return 	void
		 */
		public static function request( array &$appData, array &$request ): void {

			// Change locale
			if( $request['/nino/http/response']['locale'] !== \Nino\Locales::getCurrentLocale( $appData ) )
				\Nino\Locales::setCurrentLocale( $appData, $request['/nino/http/response']['locale'] );

			// Catch locale change
			if( isset( $request['/nino/http/request']['query']['/_nino/locales/current'] ) === false )
				return;

			$locale = \Nino\Locales::setCurrentLocale( $appData, $request['/nino/http/request']['query']['/_nino/locales/current'] );

			// This runs before Http::response(), so the response uri still is
			// the raw request uri - resolve the route first to get the
			// response uri findRouteUri() actually matches against
			$route	= \Nino\Http::requestRoute( $appData, $request['/nino/http/request']['uri'], $request['/nino/http/request']['method'] );
			$newUri = \Nino\Http::findRouteUri( $appData, $route['uri'] ?? $request['/nino/http/response']['uri'], $locale );

			// Redirect via the response array (statusCode + Location header) -
			// a direct header() call would be overwritten by Http::output()'s
			// own http_response_code()/header() pass. The route key carries
			// the method prefix (eg. "GET://legal"), strip it for the url
			$request['/nino/http/response']['statusCode'] 					= 302;
			$request['/nino/http/response']['header']['Location']	= ( $newUri !== null ) ? str_replace( $request['/nino/http/request']['method']. ':/', '', $newUri ) : '/';
		}

		/**
		 *	Get current locale
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return 	string											Current locale
		 */
		public static function getCurrentLocale( array &$appData ): string {
			return $appData['./nino/locales/current'];
		}

		/**
		 *	Get native locale
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return 	string											Current locale
		 */
		public static function getNativeLocale( array &$appData ): string {
			return $appData['/nino/locales/native'];
		}

		/**
		 *	Get available locales
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return 	array												Available locale
		 */
		public static function getAvailableLocales( array &$appData ): array {
			return $appData['/nino/locales/available'];
		}

		/**
		 *	Verify, if a locale is available
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$locale 			Locale to verify
		 *
		 *	@return 	bool												If locale is available
		 */
		public static function verifyLocale( array &$appData, string $locale ): bool {

			return in_array( $locale, \Nino\Locales::getAvailableLocales( $appData ) );
		}

		/**
		 *	Set a new locale
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$locale				New locale
		 *
		 *	@return 	string											Current locale
		 */
		public static function setCurrentLocale( array &$appData, string $locale ): string {

			// Verify, if requested locale is available
			$appData['./nino/locales/current'] = ( \Nino\Locales::verifyLocale( $appData, $locale ) === true ) ? $locale : \Nino\Locales::getCurrentLocale( $appData );
			$locale = $appData['./nino/locales/current'];

			\Nino\Runtime::setSessionValue( $appData, './nino/locales/current', $locale );

			return $locale;
		}
	}


	/**
	 *	Nino								A compact filesystembased php framework
	 *	Mail								Thin wrapper around mail() - centralizes the
	 *												Content-Type/Reply-To header building, so a
	 *												project-wide change (envelope sender, BCC, logging
	 *												a failure) has one place to happen instead of one
	 *												per caller (Form, Newsletter). Also the one choke
	 *												point both of those callers actually share, so a
	 *												simple per-ip send cap lives here too rather than
	 *												duplicated in each caller: a fixed-window counter
	 *												(5 mails/hour/ip, state in /data/ratelimit.php - same
	 *												plain, human-readable php-array convention as
	 *												/data/forms.<Y-m>.php and /data/newsletter.php),
	 *												checked before every send. Once a client is over
	 *												budget, send() just returns false - the same outcome
	 *												as any other mail() failure, which both callers
	 *												already treat as "don't surface this to the visitor,
	 *												the form/signup itself still succeeded" (see their
	 *												own comments) - so a rate-limited burst degrades to
	 *												silently-missing mail rather than a visible error
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Mail {

		private const int MAX_TRIES 	= 5;
		private const int WINDOW 		= 3600;

		/**
		 *	Send an html mail with a Reply-To header, unless the current
		 *	client ip has hit the send cap for this window
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$to							Recipient
		 *	@param		string		$subject				Subject
		 *	@param		string		$body						Rendered html body
		 *	@param		string		$replyTo				Reply-To address
		 *
		 *	@return		bool										True on success (same as mail()), false on failure or when rate-limited
		 */
		public static function send( array &$appData, string $to, string $subject, string $body, string $replyTo ): bool {

			if( self::_hit( $appData, \Nino\Http::getClientIp() ) === false )
				return false;

			return mail( $to, $subject, $body, "Content-Type:text/html\r\nReply-To: ". $replyTo. "\r\n" );
		}

		/**
		 *	Register one send attempt for $key and report whether it's
		 *	still within budget for the current window. Any key (not just
		 *	the one being checked) whose window has already elapsed is
		 *	dropped on write, so the state file can't grow without bound.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Bucket, the sending client's ip
		 *
		 *	@return 	bool										False once $key has used up MAX_TRIES within the current window
		 */
		private static function _hit( array &$appData, string $key ): bool {

			$path 	= '/data/ratelimit.php';
			$now 		= time();
			$state 	= \Nino\Filesystem::getFileContent( $appData, $path, [] );

			foreach( $state as $stateKey => $entry )
				if( ( $entry['reset'] ?? 0 ) <= $now )
					unset( $state[$stateKey] );

			$entry 					= $state[$key] ?? [ 'tries' => 0, 'reset' => $now + self::WINDOW ];
			$entry['tries']	= (int) $entry['tries'] + 1;
			$state[$key] 		= $entry;

			\Nino\Filesystem::putFileContent( $appData, $path, $state );

			return $entry['tries'] <= self::MAX_TRIES;
		}
	}


	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules 						Handle optional modules
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Modules {

		/**
		 *	Call a method at all module
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$method 			Name of required modules
		 *
		 *	@return 	void
		 */
		public static function callModules( array &$appData, string $method ): void {
			foreach( $appData['/nino/modules'] as $key => $className ) {

				$filename = __DIR__. str_replace( '\\', '/', $className );
				$filename .= '/'. basename( $filename ). '.php';

				if( class_exists( $className ) === false && is_file( $filename ) )
					require_once $filename;

				if( method_exists( $className, $method ) === true )
					$className::$method( $appData );

			}
		}
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Runtime 						Handle the php runtime
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Runtime {

		private const int RETENTION_MONTHS = 3;

		private static
			$_currentInstance = [];

		/**
		 *	Init php runtime
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// Set current instance
			self::$_currentInstance = &$appData;

			// Set errorhandler
			set_error_handler( [ self::class, 'handleError' ] );
			set_exception_handler( [ self::class, 'handleError' ] );

			// Start session
			if( session_status() !== PHP_SESSION_ACTIVE ) {
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

		/**
		 *	Write a value in current session
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Key in session array
		 *	@param		misc			$return				(optional) Return value
		 *
		 *	@return 	void
		 */
		public static function getSessionValue( array &$appData, string $key, mixed $return = null ): mixed {
			return $_SESSION[$appData['./nino/uid']][$key] ?? $return;
		}

		/**
		 *	Write a value in current session
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Key in session array
		 *	@param		misc			$value				New value
		 *
		 *	@return 	void
		 */
		public static function setSessionValue( array &$appData, string $key, mixed $value ): void {
			$_SESSION[$appData['./nino/uid']] = $_SESSION[$appData['./nino/uid']] ?? [];
			$_SESSION[$appData['./nino/uid']][$key] = $value;
		}

		/**
		 *	Remove a value in current session
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					Key in session array
		 *
		 *	@return 	void
		 */
		public static function unsetSessionValue( array &$appData, string $key ): void {
			unset( $_SESSION[$appData['./nino/uid']][$key] );
		}


		/**
		 *	Do event on global error and break execution
		 *
		 *	@return 	bool										False to let a suppressed (@) error continue
		 *																			normally; every other path terminates the script.
		 */
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

			// Check, if error/log and error/display are configured yet
			$configured = self::$_currentInstance !== null && isset( self::$_currentInstance['/nino/error/log'] ) === true && isset( self::$_currentInstance['/nino/error/display'] ) === true;

			// Log error
			if( $configured === true && self::$_currentInstance['/nino/error/log'] === true )
				self::_recordError( self::$_currentInstance, $errorArray );

			// Display error (always, if not configured yet)
			if( $configured === false || self::$_currentInstance['/nino/error/display'] === true ) {
				echo '<pre>';
				var_dump( $errorArray, debug_backtrace() );
				die('</pre>');
			}

			// Break current cycle
			header( $_SERVER['SERVER_PROTOCOL']. ' 500 Internal Server Error', true, 500 );
			exit;
		}

		/**
		 *	Append one error entry to this month's /data/logs.<Y-m>.php -
		 *	a plain, readable array file (Filesystem::getFileContent()/
		 *	putFileContent()'s native .php handling), same idea as
		 *	Modules\Form's forms.<Y-m>.php. Fixed, predictable path -
		 *	no random directory name, unlike _admin's own backups/activity
		 *	log (see docs/developer.md's "Encryption / stub conventions"):
		 *	this is public-site kernel data, not a credential-adjacent
		 *	secret, so it doesn't need that protection.
		 *	Never thrown - a logging failure inside the error handler
		 *	itself must not recurse into another error
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$entry				One error ( type, message, file, line )
		 *
		 *	@return 	void
		 */
		private static function _recordError( array &$appData, array $entry ): void {

			try {

				$path 		= '/data/logs.'. date( 'Y-m' ). '.php';
				$entries 	= \Nino\Filesystem::getFileContent( $appData, $path, [] );

				$entries[] = $entry + [ 'date' => date( 'Y-m-d H:i:s' ) ];

				\Nino\Filesystem::putFileContent( $appData, $path, $entries );

				self::_pruneLogs( \Nino\Filesystem::getPath( $appData ). '/data' );

			} catch( \Throwable $e ) {
				// Swallow - nothing left to log this failure to from inside the error handler itself
			}
		}

		/**
		 *	Delete monthly log files older than RETENTION_MONTHS
		 *
		 *	@param		string		$dir					Absolute path to the /data directory
		 *
		 *	@return 	void
		 */
		private static function _pruneLogs( string $dir ): void {

			$cutoff = ( new \DateTime( 'first day of -'. self::RETENTION_MONTHS. ' months' ) )->setTime( 0, 0 );

			foreach( glob( $dir. '/logs.*.php' ) ?: [] as $file ) {

				$month = substr( basename( $file, '.php' ), 5 );

				if( preg_match( '/^\d{4}-\d{2}$/', $month ) !== 1 )
					continue;

				$date = \DateTime::createFromFormat( 'Y-m-d', $month. '-01' );

				if( $date === false || $date < $cutoff )
					unlink( $file );
			}
		}
	}
}


/**
 *	Nino								A compact filesystembased php framework
 *	Modules							All optional Modules
 */
namespace Nino\Modules {



	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules 						Optional Modules
	 *	Assets							A html shortcode for including assets
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Assets {

		private static
			$_template				= [
				'css'							=> '<link rel="stylesheet" href="[[filename]]" type="text/css" />',
				'js'							=> '<script src="[[filename]]"></script>',
			],
			$_minifyRegex 		= [
				'css'							=> [
					[
						'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
						'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
						'#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
						'#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
						'#(background-position):0(?=[;\}])#si',
						'#(?<=[\s:,\-])0+\.(\d+)#s',
						'#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
						'#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
						'#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
						'#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
						'#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s',
					], [
						'$1',
						'$1$2$3$4$5$6$7',
						'$1',
						':0',
						'$1:0 0',
						'.$1',
						'$1$3',
						'$1$2$4$5',
						'$1$2$3',
						'$1:0',
						'$1$2',
					],
				],
				'js'				=> [
					[
						'#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
						'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
						'#;+\}#',
						'#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
						'#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i',
					], [
						'$1',
						'$1$2',
						'}',
						'$1$3',
						'$1.$3',
					]
				]
			];

		/**
		 *	Init module
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'assets', [ self::class, 'doShortcode' ] );
		}


		/**
		 *	Execute assets shortcode
		 *
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		array			$args 						Shortcode arguments
		 *
		 *	@return 	string											Rendered html code
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$targetFile		= $args[0] ?? '';
			$pathinfo			= pathinfo( $targetFile );
			$assetFiles 	= \Nino\Html::getAssets( $appData, $targetFile );

			// A missing/extension-less argument (eg. a bare "[assets]") renders
			// nothing instead of erroring on the absent pathinfo key
			if( isset( $pathinfo['extension'] ) === false || isset( self::$_template[$pathinfo['extension']] ) === false )
				return '';

			// Compare hash / create cache
			if( empty( $assetFiles ) === false && self::_getFileHashes( $appData, $assetFiles ) !== self::_readHashLine( $appData, $targetFile ) )
				self::_createCachefile( $appData, $pathinfo, $assetFiles );

			// Fill template
			$content	= \Nino\Callbacks::doCallbacks( $appData, '/nino/shortcodes/assets/output/'. $pathinfo['extension'], self::$_template[$pathinfo['extension']] );

			return str_replace( [
				'[[filename]]',
			], [
				rtrim( \Nino\Filesystem::getDir( $appData ), '/'). $targetFile,
			], $content );
		}

		/**
		 *	Create a unique filehash for all files in a asset dir
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					$assetFiles		Asset files array
		 *
		 *	@return 	string											Filehash
		 */
		private static function _getFileHashes( array &$appData, array $assetFiles ): string {

				$hash 	= '';
				$path 	= \Nino\Filesystem::getPath( $appData );

				// Loop through files and collect file details
				foreach( $assetFiles AS $file ) {
					$filepath = $path. $file;

					if( is_file( $path. $file ) === true )
						$hash .= $filepath. '|'. filesize( $filepath ). '|'. filemtime(  $filepath );
				}

				return sha1( $hash );
		}


		/**
		 *	Read first line of an rendered assetfile
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$targetFile		Target asset file to hash
		 *
		 *	@return 	string											Filehash
		 */
		private static function _readHashLine( array &$appData, string $targetFile ): string {

				$path 	= \Nino\Filesystem::getPath( $appData ). $targetFile;

				if( is_file( $path ) === false )
					return '';

				$handle = fopen( $path , 'r' );

				if( $handle === false )
					return '';

				$line = fgets( $handle );
				fclose( $handle );

				return ( $line !== false ) ? $line : '';
		}

		/**
		 *	Write all asset files from source dir into an target file
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					$pathinfo			Pathinfo of target asset file
		 *	@param		array					$assetFiles		Asset files array
		 *
		 *	@return 	void
		 */
		private static function _createCachefile( array &$appData, array $pathinfo, array $assetFiles ): void {


			// Loop through files and collect content
			$content = '';
			foreach( $assetFiles AS $file )
				$content .= \Nino\Filesystem::getFileContent( $appData, $file, '' );

			// Deliberately NOT Html::renderHtml(): that pipeline also runs admin-
			// editable textfills (text/global.php, per-locale text files) and every
			// registered shortcode - including ones that take admin-controlled
			// arguments. Since this content is written out as a static file served
			// straight from the webroot, that would turn any admin-editable fill
			// into stored XSS on every page that includes the bundle. Only the one
			// developer/kernel-controlled token the shipped assets actually use is
			// substituted here.
			$content = str_replace( '[[/nino/dir]]', \Nino\Filesystem::getDir( $appData ), $content );

			// Minify
			if( substr( $pathinfo['filename'], -4 ) === '.min' )
				$content = trim( preg_replace( self::$_minifyRegex[$pathinfo['extension']][0], self::$_minifyRegex[$pathinfo['extension']][1], $content ), ';' );

			// Add prefix and write file
			$content			= '/**'. self::_getFileHashes( $appData, $assetFiles ). '**/'. PHP_EOL. trim( $content, ';' );
			\Nino\Filesystem::putFileContent( $appData, $pathinfo['dirname']. '/'. $pathinfo['basename'], $content );
		}
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules							All optional modules
	 *	Csrf								Cross-site-request-forgery protection
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Csrf {

		/**
		 *	Module initiating. Registered at priority 1 so the check runs
		 *	before any other global POST handler (eg. Auth's login/logout).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'csrf', [ self::class, 'doShortcode' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ], 1 );
		}

		/**
		 *	Return the current session's csrf token, creating one if missing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									Current csrf token
		 */
		public static function getToken( array &$appData ): string {

			$token = \Nino\Runtime::getSessionValue( $appData, './nino/csrf/token' );

			if( is_string( $token ) === false || $token === '' ) {
				$token = bin2hex( random_bytes( 32 ) );
				\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', $token );
			}

			return $token;
		}

		/**
		 *	Replace the current session's csrf token with a fresh one, eg.
		 *	after login/logout to defend against session fixation
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function rotateToken( array &$appData ): void {
			\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', bin2hex( random_bytes( 32 ) ) );
		}

		/**
		 *	Reject a POST request with a missing or wrong csrf token. Sets a
		 *	dedicated './nino/csrf/blocked' flag in addition to the status
		 *	code, since doCallbacks() always runs every registered callback
		 *	regardless of outcome - callbacks that run after this one (eg.
		 *	Auth) must check that flag themselves before acting.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			if( $request['/nino/http/request']['method'] !== 'POST' )
				return;

			$given = self::_extractToken( $request );

			if( $given !== '' && hash_equals( self::getToken( $appData ), $given ) === true )
				return;

			$request['./nino/csrf/blocked']									= true;
			$request['/nino/http/response']['statusCode']	= 403;
			$request['/nino/http/response']['body']					= false;
		}

		/**
		 *	Read the csrf token from wherever the caller put it: the classic
		 *	hidden form field, the X-CSRF-Token header, or the parsed JSON
		 *	body - $_POST is always empty for a json request, so relying on
		 *	it alone 403s every json POST regardless of the token sent.
		 *
		 *	@param		array 		$request			Current server request
		 *
		 *	@return 	string									Token, or '' if none was found
		 */
		private static function _extractToken( array $request ): string {

			if( is_string( $_POST['_csrf'] ?? null ) === true && $_POST['_csrf'] !== '' )
				return $_POST['_csrf'];

			$header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
			if( is_string( $header ) === true && $header !== '' )
				return $header;

			$body = $request['/nino/http/request']['body'] ?? '';
			if( is_string( $body ) === true && $body !== '' ) {
				$decoded = json_decode( $body, true );
				if( is_array( $decoded ) === true && is_string( $decoded['_csrf'] ?? null ) === true )
					return $decoded['_csrf'];
			}

			return '';
		}

		/**
		 *	Replace shortcode with a hidden csrf input field
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string									Hidden input html
		 */
		public static function doShortcode( array &$appData, array $args ): string {
			return '<input type="hidden" name="_csrf" value="'. htmlspecialchars( self::getToken( $appData ), ENT_QUOTES, 'UTF-8' ). '">';
		}
	}


	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules							All optional modules
	 *	Elements						A html shortcode for including Elements
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */


	class Elements {

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'element', [ self::class, 'doShortcodeElement' ] );
			\Nino\Html::addShortcode( $appData, 'elements', [ self::class, 'doShortcodeElements' ] );
		}


		/**
		 *	Replace element shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcodeElement( array &$appData, array $args ): string {

			$uri			= $args[0] ?? '';
			$content	= $args['content'] ?? '';
			$locale 	= $args['locale'] ?? '';
			$callback	= $args['callback'] ?? '';

			$element	= \Nino\Elements::getElement( $appData, $uri, $locale );
			$model = \Nino\Elements::getElementModel( $appData, \Nino\Elements::getElementTypeFromUri( $uri ) );

			if( $element === null || $element === false )
				return '';

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $element );

			$fills = [
				[],
				[],
			];

			$element['.id'] = 0;

			foreach( $element as $key => $value ) {
				if( is_scalar( $value ) === false )
					continue;

				$isHtml = ( isset( $model[$key] ) && isset( $model[$key]['html'] ) && $model[$key]['html'] === true );

				$fills[0][] = '[['. $key. ']]';
				$fills[1][] = self::_escapeFieldValue( $value, $isHtml );
			}

			return str_replace( $fills[0], $fills[1], $content );
		}

		/**
		 *	Escape a single element field value before it is substituted into a
		 *	template. Values are editor content, not developer-authored markup -
		 *	htmlspecialchars() neutralizes raw HTML/script (matches
		 *	Modules\Images::doShortcode()'s existing pattern), and the extra '['
		 *	swap stops an editor-supplied "[[...]]" or "[shortcode]" string from
		 *	being interpreted when the surrounding content is re-rendered by
		 *	Html::_doShortcode() right after this callback returns.
		 *
		 *	@param		mixed			$value				Raw scalar field value
		 *
		 *	@return 	string									Safe-to-substitute value
		 */
		private static function _escapeFieldValue( mixed $value, bool $isHtml ): string {
		    $safe = $isHtml === true
		        ? \Nino\Html::sanitizeHtml( strval( $value ) )
		        : htmlspecialchars( strval( $value ), ENT_QUOTES, 'UTF-8' );
		    return str_replace( '[', '&#91;', $safe );
		}

		/**
		 *	Replace elements shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcodeElements( array &$appData, array $args ): string {

			$uri			= $args[0] ?? '';
			$content	= $args['content'] ?? '';
			$locale 	= $args['locale'] ?? '';
			$callback	= $args['callback'] ?? '';
			$limit		= (int) ( $args['limit'] ?? -1 );
			$query		= $args['query'] ?? '';
			$queryArr	= [];

			// Parse query array
			if( $query !== '' )
			foreach( explode( '&', $query ) as $qParts ) {
				$qSubparts = explode( '=', $qParts );
				$queryArr[$qSubparts[0]] = $qSubparts[1];
			}

			$result = \Nino\Elements::queryElements( $appData, $uri, $queryArr, $locale, [] );
			if( $result === [] )
				return '';

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $result );

			if( $limit > 0 )
				$result = array_slice( $result, 0, $limit );

			$html = '';

			$id = 0;

			$model = \Nino\Elements::getElementModel( $appData, $uri );

			foreach( $result as $element ) {

				$fills = [
					['[[.id]]'],
					[$id],
				];

				foreach( $element as $key => $value ) {
					if( is_scalar( $value ) === false )
						continue;

					$isHtml = ( isset( $model[$key] ) && isset( $model[$key]['html'] ) && $model[$key]['html'] === true );

					$fills[0][] = '[['. $key. ']]';
					$fills[1][] = self::_escapeFieldValue( $value, $isHtml );
				}

				$html .= str_replace( $fills[0], $fills[1], $content );

				$id++;
			}

			return $html;
		}
	}


	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules 						Optional Modules
	 *	Form								Handles the contact form's POST /.form - sanitizes/validates
	 *												the posted fields, sends the owner/user mail pair
	 *												(recipient/subject/message stay Text/template driven -
	 *												see /form/email/owner, /form/subject/owner, /form/subject/
	 *												user, /templates/mail-owner.tpl, /templates/mail-user.tpl -
	 *												the user mail renders in the visitor's current locale, the
	 *												owner mail always in the site's native locale),
	 *												and records every successful submission so it's visible in
	 *												_admin (in addition to the mail itself) - see
	 *												Admin\Submissions in _admin/Admin.php, which reads this
	 *												module's storage independently (same shape as Dev\Restore
	 *												reading Admin\Backup's output).
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Form {

		private const int MAX_FIELD_LENGTH 	= 1000;
		private const int RETENTION_MONTHS 	= 3;

		/**
		 *	Register the POST / handler
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
    	$appData['/nino/http/routes']['POST://.form'] = [ 'uri' => '/.form' ];
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', [ self::class, 'callbackResponse' ] );
		}

		/**
		 *	Validate and send the contact form, then record it for _admin -
		 *	same field set/rules the form has always used
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Respect a rejection from the earlier global Csrf callback - same guard
			// Auth::callbackResponse uses, see its comment for why this is a
			// dedicated flag rather than checking statusCode
			if( ( $request['./nino/csrf/blocked'] ?? false ) === true )
				return;

			$_POST['name'] 			= htmlspecialchars( substr( trim( $_POST['name'] ?? '' ), 0, self::MAX_FIELD_LENGTH ), ENT_QUOTES, 'UTF-8' );
			$_POST['email'] 		= htmlspecialchars( substr( trim( $_POST['email'] ?? '' ), 0, self::MAX_FIELD_LENGTH ), ENT_QUOTES, 'UTF-8' );
			$_POST['message'] 	= htmlspecialchars( substr( trim( $_POST['message'] ?? '' ), 0, self::MAX_FIELD_LENGTH ), ENT_QUOTES, 'UTF-8' );
			$_POST['location'] 	= htmlspecialchars( substr( trim( $_POST['location'] ?? '' ), 0, self::MAX_FIELD_LENGTH ), ENT_QUOTES, 'UTF-8' );
			$_POST['cat'] 			= htmlspecialchars( substr( trim( $_POST['cat'] ?? '' ), 0, self::MAX_FIELD_LENGTH ), ENT_QUOTES, 'UTF-8' );

			// Fields empty
			if( empty( $_POST['name'] ) === true || empty( $_POST['email'] ) === true || empty( $_POST['message'] ) === true || filter_var( $_POST['email'], FILTER_VALIDATE_EMAIL ) === false )
				$request['/nino/http/response']['statusCode'] = 400;

			// Honeypot is filled
			if( empty( $_POST['location'] ) === false )
				$request['/nino/http/response']['statusCode'] = 418;

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return;

			// Send emails
			// [[/nino/dir]] is resolved manually here (not via the normal
			// Text-fill mechanism), since this callback runs inside
			// Http::response() - before \Nino\request() adds it as a fill
			$fields = [
				'[[name]]'					=> $_POST['name'],
				'[[email]]'					=> $_POST['email'],
				'[[message]]'				=> nl2br( $_POST['message'] ),
				'[[subject]]'				=> $_POST['cat'],
				'[[date]]'					=> date("Y-m-d H:i:s"),
				'[[/nino/dir]]'		=> \Nino\Filesystem::getDir( $appData ),
			];

			// Recipient/subject are Text values ([[/form/...]], editable via _admin) -
			// only the message structure itself (the .tpl files) is developer territory
			$emailOwner = \Nino\Html::renderHtml( $appData, '[[/form/email/owner]]' );

			// The visitor's own confirmation mail stays in whichever locale
			// they filled out the form in (already the current locale)
			$tpl_user 		= \Nino\Html::renderHtml( $appData, '[template /templates/mail-user]' );
			$tpl_user 		= str_replace( array_keys( $fields ), array_values( $fields ), $tpl_user );
			$subjectUser 	= \Nino\Html::renderHtml( $appData, '[[/form/subject/user]]' );

			// ...but the owner notification always goes out in the site's
			// native locale, regardless of which locale the visitor used
			$visitorLocale = \Nino\Locales::getCurrentLocale( $appData );
			\Nino\Locales::setCurrentLocale( $appData, \Nino\Locales::getNativeLocale( $appData ) );

			$tpl_owner 		= \Nino\Html::renderHtml( $appData, '[template /templates/mail-owner]' );
			$tpl_owner 		= str_replace( array_keys( $fields ), array_values( $fields ), $tpl_owner );
			$subjectOwner = \Nino\Html::renderHtml( $appData, '[[/form/subject/owner]]' );

			\Nino\Locales::setCurrentLocale( $appData, $visitorLocale );

			\Nino\Mail::send( $appData, $emailOwner, $subjectOwner, $tpl_owner, $_POST['email'] );

			\Nino\Mail::send( $appData, $_POST['email'], $subjectUser, $tpl_user, $emailOwner );

			$request['/nino/http/response']['statusCode'] = 200;

			self::_record( $appData, [
				'date'		=> date( 'Y-m-d H:i:s' ),
				'name'		=> $_POST['name'],
				'email'		=> $_POST['email'],
				'message'	=> $_POST['message'],
				'cat'			=> $_POST['cat'],
				'ip'			=> \Nino\Http::getClientIp(),
			] );
		}

		/**
		 *	Append one submission to this month's /data/forms.<Y-m>.php,
		 *	then prune months past RETENTION_MONTHS. Never thrown - a
		 *	missing record must not turn a successfully-sent contact mail
		 *	into a 500 for the visitor. Read independently by
		 *	Admin\Submissions in _admin/Admin.php (same shape as
		 *	Dev\Restore reading Admin\Backup's output). Plain
		 *	"<?php return [...];" array files (Filesystem::getFileContent()/
		 *	putFileContent()'s native .php handling), same as config.php/
		 *	text/elements - readable directly (eg. in an emergency without
		 *	_admin installed at all) rather than needing a decoder. Lives
		 *	in /data, not under _admin/, since this is public-site kernel
		 *	data _admin merely happens to read - same fixed-path reasoning
		 *	as Runtime::_recordError()'s own docblock.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$entry				One submission ( date, name, email, message, cat, ip )
		 *
		 *	@return 	void
		 */
		private static function _record( array &$appData, array $entry ): void {

			try {

				$path = '/data/forms.'. date( 'Y-m' ). '.php';

				$entries 	= \Nino\Filesystem::getFileContent( $appData, $path, [] );
				$entries[] = $entry;

				\Nino\Filesystem::putFileContent( $appData, $path, $entries );

				self::_prune( \Nino\Filesystem::getPath( $appData ). '/data' );

			} catch( \Throwable $e ) {
				trigger_error( 'Submission log write failed: '. $e->getMessage() );
			}
		}

		/**
		 *	Delete monthly files older than RETENTION_MONTHS - a longer
		 *	window than the admin activity log (see Admin\Logs): these are
		 *	real business inquiries, not just an operational safety net
		 *
		 *	@param		string		$dir					Absolute path to the /data directory
		 *
		 *	@return 	void
		 */
		private static function _prune( string $dir ): void {

			$cutoff = ( new \DateTime( 'first day of -'. self::RETENTION_MONTHS. ' months' ) )->setTime( 0, 0 );

			foreach( glob( $dir. '/forms.*.php' ) ?: [] as $file ) {

				$month = substr( basename( $file, '.php' ), 6 );

				if( preg_match( '/^\d{4}-\d{2}$/', $month ) !== 1 )
					continue;

				$date = \DateTime::createFromFormat( 'Y-m-d', $month. '-01' );

				if( $date === false || $date < $cutoff )
					unlink( $file );
			}
		}
	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules							All optional modules
	 *	Images							A html shortcode for including a developer-fixed image slot
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Images {

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'image', [ self::class, 'doShortcode' ] );
		}

		/**
		 *	Replace shortcode with an <img> tag for the given slot uri - eg.
		 *	[image hero] or [image uri="hero" alt="..."]. Renders nothing if
		 *	the slot doesn't exist or has no image uploaded yet.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$uri 	= (string) ( $args[0] ?? ( $args['uri'] ?? '' ) );
			$slot	= \Nino\Images::getSlot( $appData, $uri );

			if( $slot === false || empty( $slot['filename'] ) === true )
				return '';

			$url = \Nino\Images::getUrl( $appData, $slot['filename'] );
			$alt = (string) ( $args['alt'] ?? ( $slot['label'] ?? '' ) );

			return '<img src="'. htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ). '" width="'. (int) ( $slot['width'] ?? 0 ). '" height="'. (int) ( $slot['height'] ?? 0 ). '" alt="'. htmlspecialchars( $alt, ENT_QUOTES, 'UTF-8' ). '">';
		}
	}


		/**
		 *	Nino								A compact filesystembased php framework
		 *	Modules							All optional modules
		 *	Jstext							Provide JS with text translations
		 *
		 *	@package						Dape/Nino
		 *	@author							David Perchermeier <mail@dape.io>
		 *	@link								https://github.com/dapeio/nino
		 */

	class Jstext {

		private static
			$_tpl = [
				'script'	=> '<script nonce="[[nonce]]">NinoJstext=[[content]];</script>',
			];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			$appData['./nino/jstext/nonce'] = base64_encode(random_bytes(16));

			\Nino\Html::addShortcode( $appData, 'jstext', [ self::class, 'doShortcode' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ] );
		}


		/**
		 *	Replace shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$fills = ['/nino/jstext/nonce'=>$appData['./nino/jstext/nonce']];
			foreach( \Nino\Html::getFills( $appData ) AS $key => $value )
				$fills[ substr( $key, 2, -2 ) ] = $value;

			return str_replace(
				[
					'[[content]]',
					'[[nonce]]',
				], [
					json_encode( $fills ),
					$appData['./nino/jstext/nonce'],
				],
				self::$_tpl['script'] );
		}

		/**
		 *	Prepare a http request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$request			Current request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Append to the seeded default policy (see Http::request()) - never
			// start from an empty string, that would drop default-src & co
			$csp = trim( $request['/nino/http/response']['header']['Content-Security-Policy'] ?? '', '; ' );

			$request['/nino/http/response']['header']['Content-Security-Policy'] = ( $csp === '' ? '' : $csp. '; ' ). "script-src 'self' 'nonce-". $appData['./nino/jstext/nonce'] ."'";
		}
	}

		/**
		 *	Nino								A compact filesystembased php framework
		 *	Modules							All optional modules
		 *	Localepicker				A simple localepicker shortcode
		 *
		 *	@package						Dape/Nino
		 *	@author							David Perchermeier <mail@dape.io>
		 *	@link								https://github.com/dapeio/nino
		 */

	class Localepicker {

		private static
			$_tpl = [
				'ul'	=> '<div class="sc-localepicker-wrap"><label><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 16" width="32" height="32" color="currentColor" aria-hidden="true"><g><path fill="currentColor" fill-rule="evenodd" d="M.017 7.482a8 8 0 0 1 15.967 0q.025.115.01.225a8 8 0 1 1-15.99 0 .6.6 0 0 1 .013-.225m1.247.951a6.75 6.75 0 0 0 4.197 5.823 7 7 0 0 1-.416-.781c-.555-1.213-.92-2.787-1.018-4.518a29 29 0 0 1-2.763-.524m2.739-.742a28 28 0 0 1-2.7-.535A6.76 6.76 0 0 1 5.46 1.744q-.229.372-.416.781c-.623 1.363-1.006 3.18-1.042 5.166Zm1.286 1.413c.109 1.516.436 2.852.893 3.85.59 1.292 1.28 1.796 1.818 1.796s1.228-.504 1.818-1.795c.457-1 .784-2.335.893-3.85-1.803.17-3.619.17-5.422 0Zm5.46-1.26a27.5 27.5 0 0 1-5.498 0c.018-1.904.38-3.596.93-4.799C6.774 1.755 7.462 1.25 8 1.25s1.228.504 1.818 1.795c.55 1.203.913 2.895.931 4.8Zm1.224 1.113c-.099 1.731-.463 3.305-1.018 4.518a7 7 0 0 1-.416.781 6.75 6.75 0 0 0 4.197-5.823q-1.372.33-2.763.524m2.725-1.801q-1.341.336-2.7.535c-.037-1.985-.42-3.803-1.043-5.166a7 7 0 0 0-.416-.781 6.76 6.76 0 0 1 4.159 5.412" clip-rule="evenodd"></path></g></svg><input type="checkbox"><div><h6>[[/nino/locales/title]]:</h6><ul>[[li]]</ul></div><div class="sc-localepicker-bg"></div></label></div>',
				'li'	=> '<li class="[[active]]"><a[[link]]>[[/nino/locales/locale/[[locale]]]]</a></li>',
			];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'localepicker', [ self::class, 'doShortcode' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ] );
		}

		/**
		 *	Prepare a http request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$request			Current request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			if( isset( $request['/nino/http/request']['query']['/_nino/localepicker/current'] ) === false )
				return;

			$locale = \Nino\Locales::setCurrentLocale( $appData, $request['/nino/http/request']['query']['/_nino/localepicker/current'] );
			$newUri = \Nino\Http::findRouteUri( $appData, $request['/nino/http/response']['uri'], $locale );

			// Redirect via the response array - a direct header() call would be
			// overwritten by Http::output()'s own http_response_code() pass
			if( $newUri !== null ) {
				$request['/nino/http/response']['statusCode'] 					= 302;
				$request['/nino/http/response']['header']['Location']	= str_replace( $request['/nino/http/request']['method']. ':/', '', $newUri );
			}
		}

		/**
		 *	Replace shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$callback	= $args['callback'] ?? '';

			$htmlLi 				= '';
			$currentLocale	= \Nino\Locales::getCurrentLocale( $appData );

			foreach( \Nino\Locales::getAvailableLocales( $appData ) AS $locale )
				if( $currentLocale === $locale )
					$actLi = str_replace( [ '[[locale]]', '[[active]]', '[[link]]' ], [ $locale, ' active', '' ], self::$_tpl['li'] );
				else
					$htmlLi .= str_replace( [ '[[locale]]', '[[active]]', '[[link]]' ], [ $locale, '', ' href="?/_nino/localepicker/current='. $locale.'"' ], self::$_tpl['li'] );

			$content = str_replace( [ '[[li]]' ], [ $actLi. $htmlLi ], self::$_tpl['ul'] );

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $content );

			return $content;
		}
	}


	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules							All optional modules
	 *	Navigation					A quick & dirty nav renderer
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Navigation {

		public static
			$html = [
				'li'					=> '<li><a href="[[uri]]"[[attributes]]>[[title]]</a></li>',
				'nav-burger'	=> '<div class="sc-nav-wrap sc-nav-fullscreen sc-nav-burger [[class]]" id="[[id]]"><label><input type="checkbox"><div class="sc-nav-bg"></div><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><rect y="3" width="24" height="3"/><rect y="10" width="24" height="3"/><rect y="17" width="24" height="3"/></svg><div class="sc-nav-content">[[content]]</div></label></div>',
				'nav-regular'	=> '<div class="sc-nav-wrap sc-nav-fullscreen sc-nav-regular [[class]]" id="[[id]]"><div class="sc-nav-content">[[content]]</div></div>',
				'ul'					=> '<ul>[[content]]</ul>',
				'div'					=> '<div>[[content]]</div>',
			];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'navigation', [ self::class, 'doShortcode' ] );
		}

		/**
		 *	Replace shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$content	= $args['content'] ?? '';
			$callback	= $args['callback'] ?? '';
			$id				= $args['id'] ?? '';
			$class		= $args['class'] ?? '';
			$html 		= '';

			// Render list elements
			$lis	= '';

			if( $content === '' )
				return '';

			$lines = explode( PHP_EOL, $content );

			foreach( $lines as $line ) {

				if( $line === '' )
					continue;

				if( strpos( $line, ':' ) === false ) {
					$html .= str_replace( '[[content]]', $line, self::$html['div'] );
					continue;
				}

				$element		= explode( ':', $line );
				$uri				= \Nino\Http::getRequest( $appData )['/nino/http/request']['uri'] ?? '/';
				$attributes = $element[2] ?? '';
				$element[0]	= trim( $element[0] );

				$attributes	.= ( $uri === $element[0] ) ? ' class="active"' : '';
				$lis				.= str_replace( [ '[[uri]]', '[[attributes]]', '[[title]]' ], [ $element[0]	, $attributes, $element[1] ], self::$html['li'] );
			}
			$html .= str_replace( '[[content]]', $lis, self::$html['ul'] );


			$template = ( in_array( 'burger', $args ) === true ) ? 'burger' : 'regular';
			$result = str_replace( [ '[[content]]', '[[id]]', '[[class]]' ], [ $html, $id, $class ], self::$html['nav-'. $template] );

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $result );

			return $result;
		}
	}

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Newsletter				Double opt-in newsletter signup, everything under the
	 *										/.newsletter uri: POST /.newsletter validates the address
	 *										and mails a confirmation link - nothing is subscribed
	 *										until GET /.newsletter?confirm=<token> is visited. The
	 *										same per-subscriber token drives the self-service
	 *										unsubscribe (GET /.newsletter?unsubscribe=<token>, url
	 *										via getUnsubscribeLink() - append it to any outgoing
	 *										mail). Persisted as one growing, email-deduped
	 *										/data/newsletter.php - no per-month bucketing (unlike
	 *										Form::_record()'s forms.<Y-m>.php) since a subscriber
	 *										list isn't naturally date-bucketed the way individual
	 *										contact inquiries are. Read independently by
	 *										Admin\Newsletter in _admin/Admin.php.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Newsletter {

		private const int MAX_FIELD_LENGTH = 1000;

		private const string PATH = '/data/newsletter.php';

		/**
		 *	Register both /.newsletter routes and their handlers - the routes
		 *	are registered here rather than hand-declared in config.php
		 *	(unlike the page GET routes, which are genuinely per-project
		 *	content): this module owns the endpoints it needs, so a project
		 *	enabling Shortcodes\Newsletter (see config.php's /nino/modules)
		 *	gets a working signup + confirm/unsubscribe flow without also
		 *	having to remember to wire up the routes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			$appData['/nino/http/routes']['POST://.newsletter']	= [ 'uri' => '/.newsletter' ];
			$appData['/nino/http/routes']['GET://.newsletter']		= [ 'uri' => '/.newsletter', 'body' => '[template '. ( $appData['/nino/newsletter/page-template'] ?? '/templates/page-newsletter' ). ']' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.newsletter', [ self::class, 'callbackResponse' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://.newsletter', [ self::class, 'callbackAction' ] );
		}

		/**
		 *	Validate a signup request and mail the confirmation link - the
		 *	actual subscription only happens once that link is visited
		 *	(double opt-in, see callbackAction())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Respect a rejection from the earlier global Csrf callback - same guard
			// Form/Auth::callbackResponse use
			if( ( $request['./nino/csrf/blocked'] ?? false ) === true )
				return;

			$email 		= mb_strtolower( htmlspecialchars( substr( trim( $_POST['email'] ?? '' ), 0, self::MAX_FIELD_LENGTH ), ENT_QUOTES, 'UTF-8' ) );
			$location = htmlspecialchars( substr( trim( $_POST['location'] ?? '' ), 0, self::MAX_FIELD_LENGTH ), ENT_QUOTES, 'UTF-8' );

			// Email missing/invalid
			if( empty( $email ) === true || filter_var( $email, FILTER_VALIDATE_EMAIL ) === false )
				$request['/nino/http/response']['statusCode'] = 400;

			// Honeypot is filled
			if( empty( $location ) === false )
				$request['/nino/http/response']['statusCode'] = 418;

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return;

			$status = self::_requestSignup( $appData, $email );

			$request['/nino/http/response']['statusCode'] = 200;
			$request['/nino/http/response']['body'] 			= [ 'status' => $status ];
		}

		/**
		 *	Handle a visited confirm/unsubscribe link (GET /.newsletter
		 *	?confirm=<token> / ?unsubscribe=<token>) and prepare the fills
		 *	the page template renders the outcome with. An unknown or
		 *	missing token answers 404 - the page stays a friendly html page
		 *	either way
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackAction( array &$appData, array &$request ): void {

			$query 	= $request['/nino/http/request']['query'] ?? [];
			$result = 'invalid';

			if( isset( $query['confirm'] ) === true )
				$result = ( self::_confirm( $appData, (string) $query['confirm'] ) === true ) ? 'confirmed' : 'invalid';
			else if( isset( $query['unsubscribe'] ) === true )
				$result = ( self::_unsubscribe( $appData, (string) $query['unsubscribe'] ) === true ) ? 'unsubscribed' : 'invalid';

			if( $result === 'invalid' )
				$request['/nino/http/response']['statusCode'] = 404;

			// Nested fills - the outer key is what the page template uses,
			// the inner one resolves per-locale from text/*.php
			\Nino\Html::addFills( $appData, [
				'[[/newsletter/page/title]]'	=> '[[/newsletter/page/'. $result. '/title]]',
				'[[/newsletter/page/text]]'		=> '[[/newsletter/page/'. $result. '/text]]',
			], '*' );
		}

		/**
		 *	Build the absolute self-service unsubscribe url for a subscribed
		 *	(or still pending) email - append it to any outgoing newsletter
		 *	mail. Same https://[[/website/url]] convention the sitemap
		 *	template uses for absolute urls
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$email				Subscriber to build the url for
		 *
		 *	@return 	string | false							Unsubscribe url, or false for an unknown email
		 */
		public static function getUnsubscribeLink( array &$appData, string $email ): string|false {

			$email = mb_strtolower( trim( $email ) );

			foreach( \Nino\Filesystem::getFileContent( $appData, self::PATH, [] ) as $entry )
				if( ( $entry['email'] ?? null ) === $email && empty( $entry['token'] ) === false )
					return self::_getActionUrl( $appData, 'unsubscribe', $entry['token'] );

			return false;
		}

		/**
		 *	Record a pending signup and mail its confirmation link - never
		 *	thrown, same reasoning as Form::_record: a storage failure must
		 *	not turn a visitor-facing signup into a 500. An email that is
		 *	already subscribed reports 'existing' (no mail); a still-pending
		 *	one gets its mail again with the same token (the first one may
		 *	simply never have arrived). Entries written before the double
		 *	opt-in flow (no status/token fields) count as subscribed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$email				Validated email address
		 *
		 *	@return 	string									'new' when a confirmation mail went out, 'existing' otherwise
		 */
		private static function _requestSignup( array &$appData, string $email ): string {

			try {

				$entries = \Nino\Filesystem::getFileContent( $appData, self::PATH, [] );
				$token 	 = null;

				foreach( $entries as $entryKey => $entry ) {

					if( ( $entry['email'] ?? null ) !== $email )
						continue;

					if( ( $entry['status'] ?? 'subscribed' ) === 'subscribed' )
						return 'existing';

					$token = $entry['token'];
					$entries[$entryKey]['date'] = date( 'Y-m-d H:i:s' );
				}

				if( $token === null ) {
					$token 		 = bin2hex( random_bytes( 16 ) );
					$entries[] = [
						'email'		=> $email,
						'token'		=> $token,
						'status'	=> 'pending',
						'date'		=> date( 'Y-m-d H:i:s' ),
						'ip'			=> \Nino\Http::getClientIp(),
					];
				}

				\Nino\Filesystem::putFileContent( $appData, self::PATH, $entries );

				self::_sendConfirmMail( $appData, $email, $token );

				return 'new';

			} catch( \Throwable $e ) {
				trigger_error( 'Newsletter signup write failed: '. $e->getMessage() );
				return 'existing';
			}
		}

		/**
		 *	Send the confirmation mail carrying the signup link, in the
		 *	visitor's own current locale (no owner-locale juggling needed
		 *	here, unlike Form - there's no separate owner notification to
		 *	send). Template path is config-driven (/nino/newsletter/
		 *	confirm-template) so a project can swap it; recipient/subject/
		 *	body copy stay Text-driven same as Form's mail-user/mail-owner.
		 *	A mail failure is not surfaced to the visitor - the pending
		 *	entry is already recorded, a re-submit simply re-sends
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$email				Recipient
		 *	@param		string		$token				The pending entry's confirm token
		 *
		 *	@return 	void
		 */
		private static function _sendConfirmMail( array &$appData, string $email, string $token ): void {

			$template = $appData['/nino/newsletter/confirm-template'] ?? '/templates/mail-newsletter-confirm';

			\Nino\Html::addFills( $appData, [ '[[/newsletter/confirm/url]]' => self::_getActionUrl( $appData, 'confirm', $token ) ], '*' );

			$tpl 			= \Nino\Html::renderHtml( $appData, '[template '. $template. ']' );
			$subject 	= \Nino\Html::renderHtml( $appData, '[[/mail/newsletter/subject]]' );
			$replyTo 	= \Nino\Html::renderHtml( $appData, '[[/form/email/owner]]' );

			\Nino\Mail::send( $appData, $email, $subject, $tpl, $replyTo );
		}

		/**
		 *	Flip a pending entry to subscribed for a visited confirm link.
		 *	Idempotent - confirming an already-subscribed token stays true,
		 *	so a twice-clicked mail link doesn't scare the visitor with an
		 *	error
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$token				Token from the visited link
		 *
		 *	@return 	bool										False for an unknown/empty token or a failed write
		 */
		private static function _confirm( array &$appData, string $token ): bool {

			if( $token === '' )
				return false;

			try {

				$entries = \Nino\Filesystem::getFileContent( $appData, self::PATH, [] );

				foreach( $entries as $entryKey => $entry ) {

					if( hash_equals( (string) ( $entry['token'] ?? '' ), $token ) === false )
						continue;

					if( ( $entry['status'] ?? '' ) !== 'subscribed' ) {
						$entries[$entryKey]['status']	= 'subscribed';
						$entries[$entryKey]['date']		= date( 'Y-m-d H:i:s' );
						\Nino\Filesystem::putFileContent( $appData, self::PATH, $entries );
					}

					return true;
				}

			} catch( \Throwable $e ) {
				trigger_error( 'Newsletter confirm failed: '. $e->getMessage() );
			}

			return false;
		}

		/**
		 *	Remove the entry a visited unsubscribe link's token belongs to -
		 *	works for subscribed and still-pending entries alike
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$token				Token from the visited link
		 *
		 *	@return 	bool										False for an unknown/empty token or a failed write
		 */
		private static function _unsubscribe( array &$appData, string $token ): bool {

			if( $token === '' )
				return false;

			try {

				$entries = \Nino\Filesystem::getFileContent( $appData, self::PATH, [] );

				foreach( $entries as $entryKey => $entry )
					if( hash_equals( (string) ( $entry['token'] ?? '' ), $token ) === true ) {
						unset( $entries[$entryKey] );
						\Nino\Filesystem::putFileContent( $appData, self::PATH, array_values( $entries ) );
						return true;
					}

			} catch( \Throwable $e ) {
				trigger_error( 'Newsletter unsubscribe failed: '. $e->getMessage() );
			}

			return false;
		}

		/**
		 *	Build an absolute /.newsletter action url (confirm/unsubscribe)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$action				Query key: 'confirm' or 'unsubscribe'
		 *	@param		string		$token				The entry's token
		 *
		 *	@return 	string									Absolute url
		 */
		private static function _getActionUrl( array &$appData, string $action, string $token ): string {

			return 'https://'. \Nino\Html::renderHtml( $appData, '[[/website/url]]' ). '/.newsletter?'. $action. '='. rawurlencode( $token );
		}
	}


	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules							All optional modules
	 *	Template						A html shortcode for including templates
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */


	class Template {

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'template', [ self::class, 'doShortcode' ] );
		}


		/**
		 *	Replace template shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcode( array &$appData, array $args ): string {
			$html = \Nino\Filesystem::getFileContent( $appData, ( $args[0] ?? '' ). '.tpl', '' );
			return \Nino\Callbacks::doCallbacks( $appData, '/nino/html/render', $html );
		}
	}

}