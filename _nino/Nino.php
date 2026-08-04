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

		// Session cookie params are fixed the moment session_start() runs and
		// can't be retrofitted afterwards, so the keys Runtime::init() needs
		// have to be known before it. The full config load still happens in
		// AppData::init() below (it needs Filesystem::init() first) - this only
		// pulls the '/nino/session/' keys forward.
		\Nino\AppData::prepareSession( $appData );

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

		\Nino\Http::response( $appData, $request );

		// After Http::response(), not before: only there has the route been
		// resolved and merged, so only there is a route's own 'locale' known
		\Nino\Locales::response( $appData, $request );

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
				'./nino/filesystem/locks'			=> [],
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
		 *	Read the '/nino/session/' keys out of config.php ahead of the
		 *	regular init(), for the one consumer that runs before it:
		 *	Runtime::init() starts the php session, and a session cookie's
		 *	flags are set once at session_start() time. Reading them from an
		 *	appData that hasn't seen config.php yet meant
		 *	'/nino/session/force-secure-cookie' was always missing and always
		 *	fell back to false - so the option existed but could never take
		 *	effect, on exactly the tls-terminating-proxy setup it was built
		 *	for (no $_SERVER['HTTPS'], secure flag never set).
		 *	Deliberately narrow: only these keys, no merge of anything else -
		 *	init() below stays the single place the config is actually loaded.
		 *	A missing/unreadable config.php is not diagnosed here either; that
		 *	is init()'s job and its error message is the better one.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function prepareSession( array &$appData ): void {

			$staticAppData = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

			if( is_array( $staticAppData ) === false )
				return;

			foreach( $staticAppData as $key => $value )
				if( is_string( $key ) === true && str_starts_with( $key, '/nino/session/' ) === true )
					$appData[$key] = $value;
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
		 *	Filesystem::mutate() re-reads fresh under the lock for the same
		 *	reason - this only has to merge the given keys into that copy.
		 *
		 *	A failed lock is treated as a deployment problem (no /data/.locks,
		 *	no free file handles), not contention - unlike every other
		 *	mutate() caller, this surfaces it via trigger_error() rather than
		 *	giving up silently: a dropped config.php write can mean a
		 *	permission change, a route, or a user's login never actually saved.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$keys					Top-level appData keys this call changed
		 *
		 *	@return 	void
		 */
		public static function writeContentData( array &$appData, array $keys ): void {

			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false ) {
				trigger_error( 'AppData::writeContentData(): could not lock config.php for writing - refusing to write unserialized.', E_USER_ERROR );
				return;
			}

			\Nino\Filesystem::mutate( $appData, '/config.php', function( array $content, array &$appData ) use ( $keys ): array {

				foreach( $keys as $key )
					$content[$key] = ( $key === '/nino/auth/user' )
						? self::_mergeAuthUsers( $appData['./nino/auth/baseline'] ?? [], $content[$key] ?? [], $appData[$key] ?? [], $appData['./nino/auth/revoked'] ?? [] )
						: ( $appData[$key] ?? null );

				return $content;
			} );
		}

		/**
		 *	Three-way merge for '/nino/auth/user'. Re-reading the file only
		 *	protects *other* top-level keys - two parallel logins both write
		 *	the whole '/nino/auth/user' key from their own, by then outdated,
		 *	in-memory copy, so whoever writes second drops the other's session
		 *	and logs that user straight back out.
		 *	The three sides are: what this request saw at boot (baseline, see
		 *	Auth::init()), what is on disk right now, and what this request
		 *	decided. A session token that appeared on disk after boot can only
		 *	be someone else's parallel login - this request never saw it, so it
		 *	cannot have removed it - and is carried over. A token this request
		 *	removed (in the baseline, gone from memory) stays removed, so
		 *	logout and "log out everywhere" keep working.
		 *	Only the sessions map is merged: adding/removing users is an admin
		 *	action, not something two requests do to each other by accident.
		 *	$revoked overrides all of it. "Carry over what this request never
		 *	saw" is the wrong rule for a request that deliberately ended every
		 *	session of a user - a password change or "log out everywhere" would
		 *	otherwise hand back a token that a parallel login created in the
		 *	meantime, ie. exactly the session the user is trying to get rid of.
		 *	Auth::updateUser()/logoutAllSessions() record the revocation there.
		 *
		 *	@param		array 		$baseline			'/nino/auth/user' as read at boot
		 *	@param		array 		$onDisk				'/nino/auth/user' as it is on disk now
		 *	@param		array 		$inMemory			'/nino/auth/user' as this request wants it
		 *	@param		array 		$revoked			Mail => true for users whose sessions this request revoked
		 *
		 *	@return 	array
		 */
		private static function _mergeAuthUsers( array $baseline, array $onDisk, array $inMemory, array $revoked = [] ): array {

			foreach( $inMemory as $mail => $user ) {

				if( is_array( $user['sessions'] ?? null ) === false || is_array( $onDisk[$mail]['sessions'] ?? null ) === false )
					continue;

				// Nothing to carry over for a user this request revoked
				if( ( $revoked[$mail] ?? false ) === true )
					continue;

				$baseSessions = $baseline[$mail]['sessions'] ?? [];

				foreach( $onDisk[$mail]['sessions'] as $token => $session )
					if( isset( $baseSessions[$token] ) === false && isset( $user['sessions'][$token] ) === false )
						$inMemory[$mail]['sessions'][$token] = $session;
			}

			return $inMemory;
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

		// Failed attempts are counted per account *and* per client ip. The
		// account bucket on its own is a lockout weapon (five wrong guesses
		// against a known mail address and its owner is out for the cooldown)
		// and leaves guessing spread across many accounts unthrottled. The
		// prefix keeps ip keys from colliding with a mail address.
		private const string IP_KEY_PREFIX = 'ip:';

		// The ip bucket trips at maxtries * this, not at maxtries: a shared
		// exit ip (office nat, cgnat, a school) is one person mistyping their
		// password away from locking out everyone behind it otherwise. The
		// point of this bucket is to catch guessing spread across accounts,
		// which needs far more attempts than one user's typos.
		private const int IP_TRIES_FACTOR = 10;

		// bcrypt hash of a random value nobody holds, verified against when
		// the account doesn't exist or is disabled. Without it that path
		// returns before password_verify() ever runs, so an unknown user
		// answers measurably faster than a known one - and since only known
		// accounts got a tries entry, the cooldown was an oracle too
		private const string DUMMY_HASH = '$2y$12$.XrU56roB3Yw28vmlpzZN.I5lpI6kAPVytki6Mo1zm3w.WHYgeczq';

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
			// at this point (see \Nino\init()). Flagged here rather than
			// trigger_error()'d immediately: that runs on *every* request
			// incl. plain GETs, and Runtime's error handler always terminates
			// the request (see its docblock) - one missing module entry would
			// 500 the entire site, not just the login form. The flag is
			// checked once an actual POST hits login/logout below instead, so
			// this only ever blocks the one feature that's actually unsafe.
			$appData['./nino/auth/csrf-enabled'] = self::_csrfModuleEnabled( $appData );

			// Snapshot of the user records as this request found them, before
			// anything in it can have changed them - the third side of
			// AppData::writeContentData()'s merge, which is what keeps two
			// parallel logins from cancelling each other out
			$appData['./nino/auth/baseline'] = $appData['/nino/auth/user'] ?? [];

			// Read session user - the session token (not the client ip) is what
			// ties a php session to one entry in the user's 'sessions' array,
			// so a login survives an ip change (mobile network handover, CGNAT
			// rotation) and "log out everywhere" can't accidentally hit someone
			// else sharing the same NAT ip
			// Only mail + token live in $_SESSION (see loginUser()) - the user
			// array itself, pw hash included, is reloaded fresh from appData's
			// in-memory content on every request instead
			// is_string() guards both against a pre-migration or hand-edited
			// session that still holds an array under either key - used as an
			// array key/isset() offset below, that would be a TypeError rather
			// than a miss, 500ing every request from that client instead of
			// just treating it as logged out
			$sessionMail 	= \Nino\Runtime::getSessionValue( $appData, './nino/auth/current', '' );
			$sessionToken	= \Nino\Runtime::getSessionValue( $appData, './nino/auth/token', '' );
			if( is_string( $sessionMail ) === true && $sessionMail !== '' && is_string( $sessionToken ) === true && $sessionToken !== '' )
				self::_resumeSession( $appData, $sessionMail, $sessionToken );

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

			// Check user data - no client ip (cli, eg. the smoke tests) means no
			// ip bucket rather than one shared '' bucket everything falls into
			$user		= self::getUser( $appData, $username );
			$ip			= \Nino\Http::getClientIp();
			$ipKeys	= ( $ip !== '' ) ? [ self::IP_KEY_PREFIX. $ip ] : [];

			// Check cooldown - the ip bucket is checked regardless of whether
			// the account exists, so guessing spread across many accounts hits
			// a limit too. This one does return early: it is keyed by the
			// caller's own ip, so it tells them nothing they didn't know.
			foreach( $ipKeys as $ipKey )
				if( self::_inCooldown( $appData, $ipKey ) === true )
					return false;

			// Whether this attempt could succeed at all. An account that is
			// unknown, disabled or still cooling down cannot - but the check
			// must not short-circuit past the hash verification below, or the
			// response time answers the question the login form is refusing to
			// answer: a locked account would come back in microseconds while a
			// wrong password takes the full bcrypt cost.
			// "In cooldown" is kept apart from "unknown or disabled" on purpose.
			// An account that is already locked must not keep feeding the ip
			// bucket: a user stubbornly retrying their own locked login would
			// otherwise take out their whole ip - and everyone behind the same
			// nat with it - after maxtries * IP_TRIES_FACTOR clicks. Those
			// attempts also can't teach an attacker anything; the account is
			// locked either way.
			$cooling	= ( $user !== false && $user['status'] === 2 && self::_inCooldown( $appData, $username ) === true );
			$usable		= ( $user !== false && $user['status'] === 2 && $cooling === false );

			// Exactly one password_verify() on every path. DUMMY_HASH is a
			// bcrypt hash of a value nobody holds, at the cost PASSWORD_DEFAULT
			// currently produces - a site whose stored hashes still carry an
			// older, cheaper cost stays distinguishable in principle; login
			// rehashes those on the next successful login (see below).
			$verified = password_verify( $pw, ( $usable === true ) ? $user['pw'] : self::DUMMY_HASH );

			// After the verification, not before it - the point of the dummy
			// hash is that every rejected attempt costs the same
			if( $cooling === true )
				return false;

			if( $usable === false || $verified === false ) {

				// Counted against the account only when the account is a real,
				// currently usable one - counting a cooling account again on
				// every probe would let an attacker extend its lockout forever
				$keys = ( $usable === true ) ? array_merge( $ipKeys, [ $username ] ) : $ipKeys;

				return self::_registerFailedAttemp( $appData, $keys );
			}

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

			if( $pw !== '' ) {

				$user['pw'] = password_hash( $pw, PASSWORD_DEFAULT );

				// A password change has to end the sessions opened with the old
				// one - otherwise the single action taken after a compromise
				// ("change the password") leaves the attacker's session running.
				// The session performing the change keeps its own token, so
				// changing your own password doesn't log you out of the tab you
				// are doing it in.
				$currentToken	= \Nino\Runtime::getSessionValue( $appData, './nino/auth/token', '' );
				$isSelf				= ( $appData['./nino/auth/current']['mail'] ?? '' ) === $username;

				$user['sessions'] = ( $isSelf === true && is_string( $currentToken ) === true && isset( $user['sessions'][$currentToken] ) === true )
					? [ $currentToken => $user['sessions'][$currentToken] ]
					: [];

				// Tell the merge in AppData::writeContentData() that this is a
				// revocation, not just "these are the sessions I know about" -
				// otherwise a login that happened in a parallel request after
				// this one booted would be carried straight back in
				$appData['./nino/auth/revoked'][$newUsername]	= true;
				$appData['./nino/auth/revoked'][$username]			= true;
			}

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

			// See updateUser(): a revocation must not have tokens merged back
			// into it, not even ones this request never saw
			$appData['./nino/auth/revoked'][$username] = true;

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

			// Strict comparison: without it a single truthy non-string in perms
			// (a config typo like 'perms' => [ true ]) loosely matches every
			// permission there is, ie. silently grants everything
			$perms = ( is_array( $user['perms'] ?? null ) === true ) ? $user['perms'] : [];

			// Check exact perm
			if( in_array( $perm, $perms, true ) === true )
				return true;

			// Check perms recursive
			while( $perm !== '' ) {

				$separator = strrpos( $perm, '/' );

				// A perm without any '/' has no parent to walk up to. This used
				// to hand strrpos()'s false straight to substr(), which is a
				// TypeError under strict_types - a 500 instead of a denial.
				if( $separator === false )
					return false;

				$parentPerms = substr( $perm, 0, $separator );

				if( in_array( $parentPerms. '/*', $perms, true ) === true )
					return true;

				$perm = $parentPerms;
			}

			return false;
		}


		/**
		 *	Restore the logged-in user behind a session token - if that token
		 *	is still listed on the user AND hasn't outlived SESSION_TTL. The
		 *	ttl used to be enforced in loginUser() only, ie. on write: tokens
		 *	were pruned when their owner logged in again, so an account that
		 *	never logs in again kept every token it ever handed out, forever.
		 *	An expired token is dropped right here rather than just ignored,
		 *	so it can't sit in config.php until that next login either.
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$mail					Mail from the php session
		 *	@param		string				$token				Session token from the php session
		 *
		 *	@return 	void
		 */
		private static function _resumeSession( array &$appData, string $mail, string $token ): void {

			$user = self::getUser( $appData, $mail );

			if( $user === false || is_array( $user['sessions'][$token] ?? null ) === false )
				return;

			if( ( $user['sessions'][$token]['time'] ?? 0 ) < time() - self::SESSION_TTL ) {

				unset( $appData['/nino/auth/user'][$mail]['sessions'][$token] );
				\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

				\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/current' );
				\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/token' );

				return;
			}

			$appData['./nino/auth/current'] = $user;
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

			return (int) ( $state[$username] ?? 0 );
		}

		/**
		 *	Whether a bucket (account mail or IP_KEY_PREFIX.ip) is still
		 *	cooling down, ie. holds a negative timestamp that lies in the
		 *	future - see _registerFailedAttemp()'s encoding
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$key					Bucket key
		 *
		 *	@return 	bool
		 */
		private static function _inCooldown( array &$appData, string $key ): bool {

			return self::_getTries( $appData, $key ) < 0 - time();
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

			\Nino\Filesystem::mutate( $appData, self::TRIES_PATH, function( array $state ) use ( $username ): ?array {

				if( isset( $state[$username] ) === false )
					return null;

				unset( $state[$username] );
				return $state;
			} );
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

			\Nino\Filesystem::mutate( $appData, self::TRIES_PATH, function( array $state ) use ( $oldUsername, $newUsername ): ?array {

				if( isset( $state[$oldUsername] ) === false )
					return null;

				$state[$newUsername] = $state[$oldUsername];
				unset( $state[$oldUsername] );
				return $state;
			} );
		}

		/**
		 *	Register one failed login attempt against every given bucket
		 *	(account mail, client ip, or both) in a single read-modify-write,
		 *	rather than one file rewrite per bucket
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array 				$keys					Bucket keys to count this attempt against
		 *
		 *	@return 	false
		 */
		private static function _registerFailedAttemp( array &$appData, array $keys ): bool {

			// A dedicated file, not config.php, so this unauthenticated path
			// never triggers a config.php rewrite. A failed lock (see
			// Filesystem::mutate()) means this attempt goes unrecorded rather
			// than recorded unreliably - the caller gets false either way.
			\Nino\Filesystem::mutate( $appData, self::TRIES_PATH, function( array $state, array &$appData ) use ( $keys ): array {

				$now = time();

				// Drop buckets whose cooldown has already elapsed - with an ip
				// bucket per attacking client this file would otherwise only ever
				// grow. Same "clean up on the write we're doing anyway" idea as
				// Mail::_hit() and loginUser()'s session pruning.
				foreach( $state as $stateKey => $stateTries )
					if( (int) $stateTries < 0 && 0 - (int) $stateTries <= $now )
						unset( $state[$stateKey] );

				foreach( $keys as $key ) {

					$tries = (int) ( $state[$key] ?? 0 );

					if( $tries >= 0 )
						$tries++;
					else
						$tries = 1;

					// Check max tries - see IP_TRIES_FACTOR for why the ip bucket
					// gets a much longer leash than a single account does
					$maxTries = ( str_starts_with( $key, self::IP_KEY_PREFIX ) === true )
						? $appData['/nino/auth/maxtries'] * self::IP_TRIES_FACTOR
						: $appData['/nino/auth/maxtries'];

					if( $tries >= $maxTries )
						$tries = 0 - $now - $appData['/nino/auth/cooldown'];

					$state[$key] = $tries;
				}

				return $state;
			} );

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

			$appData['./nino/callbacks'][$name] = $appData['./nino/callbacks'][$name] ?? [[],[],[],[],[],[],[],[],[],[]];

			if( isset( $appData['./nino/callbacks'][$name][$prio] ) === false )
				$prio = 5;

			$appData['./nino/callbacks'][$name][$prio][] = $callback;
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

			// call_user_func_array() handles every callable shape
			// registerCallback() accepts (function name, [object, method],
			// [class, method], closure) uniformly - no per-shape branching
			// needed
			foreach( $appData['./nino/callbacks'][$name] AS $prioArray )
				foreach( $prioArray as $callback )
					$args = call_user_func_array( $callback, [ &$appData, &$args ] ) ?? $args;

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

			$path = $appData['./nino/filesystem/cache'][$filename]['path'];

			// No flock() on the read side: a LOCK_SH here would first downgrade,
			// then drop, an exclusive lock a caller may already hold on this
			// same handle mid read-modify-write (Elements does exactly that
			// between lockFile() and putFileContent()). Writes are atomic (see
			// _writeFile()), so a reader always sees either the whole old file
			// or the whole new one and needs no lock of its own.
			clearstatcache( true, $path );
			$stat = @stat( $path );

			if( $stat === false )
				return $default;

			// mtime alone has 1-second resolution; comparing size as well
			// catches most same-second rewrites. Callers that must not miss one
			// (AppData::writeContentData(), Auth's tries file) still drop their
			// cache slot explicitly before reading.
			$fingerprint = [ 'mtime' => $stat['mtime'], 'size' => $stat['size'] ];

			if( $appData['./nino/filesystem/cache'][$filename]['fstat'] !== $fingerprint ) {

				$appData['./nino/filesystem/cache'][$filename]['fstat'] 	= $fingerprint;
				$appData['./nino/filesystem/cache'][$filename]['content']	= ( substr( $filename, -4 ) === '.php' )
					? include $path
					: (string) @file_get_contents( $path );

				if( substr( $filename, -5 ) === '.json' )
					$appData['./nino/filesystem/cache'][$filename]['content'] = json_decode( $appData['./nino/filesystem/cache'][$filename]['content'], true );
			}

			return $appData['./nino/filesystem/cache'][$filename]['content'];
		}

		/**
		 *	Put content into file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename of requested file
		 *	@param		misc			$content			Content to put
		 *	@param		boolean		$nolock 			(optional) If the caller already holds the lock (see lockFile())
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

			$path = $appData['./nino/filesystem/cache'][$filename]['path'];

			// Take the lock unless the caller already holds it via lockFile()
			if( $nolock === false && self::lockFile( $appData, $filename ) === false )
				return false;

			// Update cache - for an append the cached value would be just the
			// chunk, not the file, so the slot is dropped and re-read instead
			if( $append === true )
				$appData['./nino/filesystem/cache'][$filename]['fstat'] = [];
			else
				$appData['./nino/filesystem/cache'][$filename]['content'] = $content;

			// Prepare content
			if( substr( $filename, -5 ) === '.json' )
				$content = json_encode( $content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			if( substr( $filename, -4 ) === '.php' )
				$content = '<?php return '. var_export( $content, true ). ';';

			$success = ( $append === true )
				? self::_appendFile( $path, (string) $content )
				: self::_writeFile( $path, (string) $content );

			// Released either way, including for $nolock === true: the write is
			// the end of the caller's read-modify-write sequence (lockFile() ->
			// read -> putFileContent()), which is exactly where the previous
			// implementation dropped its flock too. Holding on past it would
			// block the next sequence in the same process - two appData copies
			// in one request, or simply the next write of the same file.
			self::unlockFile( $appData, $filename );

			// Reset opcache
			if( function_exists( 'opcache_invalidate' ) === true && is_file( $path ) === true )
				opcache_invalidate( $path );

			// Refresh the fingerprint getFileContent() compares against, so the
			// write this call just made doesn't look like someone else's change
			clearstatcache( true, $path );
			$stat = @stat( $path );

			if( $append === false && $stat !== false )
				$appData['./nino/filesystem/cache'][$filename]['fstat'] = [ 'mtime' => $stat['mtime'], 'size' => $stat['size'] ];

			return $success;
		}

		/**
		 *	Replace a file's content atomically: write a temp file next to it,
		 *	then rename() over the target. A plain ftruncate()+fwrite() leaves
		 *	the file empty for the duration of the write and, if the process
		 *	dies in between (php timeout, oom kill, deploy restart), leaves it
		 *	empty for good - for config.php that is every route, every module
		 *	binding and every password hash gone. rename() within the same
		 *	directory is atomic, so a concurrent reader sees either the whole
		 *	old file or the whole new one, never a half-written one.
		 *
		 *	@param		string		$path					Absolute target path
		 *	@param		string		$content			Content to write
		 *
		 *	@return		bool										If successful
		 */
		private static function _writeFile( string $path, string $content ): bool {

			$temp		= $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			$handle	= fopen( $temp, 'wb' );

			if( $handle === false )
				return false;

			$written = fwrite( $handle, $content );

			// fflush() before closing: a short write (full disk, quota) has to
			// fail here, while the temp file is still the only thing affected
			if( $written === false || $written !== strlen( $content ) || fflush( $handle ) === false ) {
				fclose( $handle );
				@unlink( $temp );
				return false;
			}

			fclose( $handle );

			// Keep the existing file's mode - the temp file was created fresh
			// under the current umask, so without this a rewrite could quietly
			// widen (or narrow) permissions on eg. config.php
			$mode = @fileperms( $path );
			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( rename( $temp, $path ) === false ) {
				@unlink( $temp );
				return false;
			}

			return true;
		}

		/**
		 *	Append to a file. Kept separate from _writeFile(): appending is by
		 *	definition an in-place operation, there is nothing to swap in.
		 *
		 *	@param		string		$path					Absolute target path
		 *	@param		string		$content			Content to append
		 *
		 *	@return		bool										If successful
		 */
		private static function _appendFile( string $path, string $content ): bool {

			$handle = fopen( $path, 'a' );

			if( $handle === false )
				return false;

			// No ftruncate() here - it used to run unconditionally, so an
			// "append" emptied the file and wrote the chunk on its own
			$written = fwrite( $handle, $content );
			$flushed = fflush( $handle );

			fclose( $handle );

			return $written !== false && $written === strlen( $content ) && $flushed === true;
		}

		/**
		 *	Lock a file for a read-modify-write sequence. The lock lives on a
		 *	side-car file in /data/.locks rather than on the file itself: the
		 *	data file is replaced by rename() (see _writeFile()), and a lock
		 *	held on the replaced inode stops serializing anything the moment
		 *	that happens. The lock file is only ever created, never replaced.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename of requested file
		 *
		 *	@return		bool										If successful
		 */
		public static function lockFile( array &$appData, string $filename ): bool {

			self::_prepareFileCache( $appData, $filename );

			// Already holding it (eg. lockFile() followed by a putFileContent()
			// that takes its own lock) - flock() is per handle, so re-locking
			// the same handle is a no-op rather than a deadlock, but there is
			// no point in opening a second one
			if( is_resource( $appData['./nino/filesystem/locks'][$filename] ?? null ) === true )
				return true;

			self::forceDir( $appData, '/data/.locks' );

			$lockPath	= self::getPath( $appData ). '/data/.locks/'. sha1( $filename ). '.lock';
			$handle		= fopen( $lockPath, 'c' );

			if( $handle === false )
				return false;

			if( flock( $handle, LOCK_EX ) === false ) {
				fclose( $handle );
				return false;
			}

			// Deliberately NOT in the file's cache slot: several call sites drop
			// a slot to force a re-read (Auth's tries file, writeContentData),
			// and _dev drops the whole cache array at once - any of which would
			// take the only reference to this resource with it, closing the
			// handle and releasing the lock while the caller still believes it
			// holds one. Locks live in their own map for that reason.
			$appData['./nino/filesystem/locks'][$filename] = $handle;

			return true;
		}

		/**
		 *	Release a lock taken by lockFile()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Filename of requested file
		 *
		 *	@return		bool										If successful
		 */
		public static function unlockFile( array &$appData, string $filename ): bool {

			$handle = $appData['./nino/filesystem/locks'][$filename] ?? null;

			if( is_resource( $handle ) === false )
				return false;

			flock( $handle, LOCK_UN );
			fclose( $handle );

			unset( $appData['./nino/filesystem/locks'][$filename] );

			return true;
		}

		/**
		 *	Lock -> invalidate -> read -> write, in that order, around a
		 *	callback that computes the new state - the shape every
		 *	read-modify-write cycle on a file needs, written once. Reading
		 *	before locking leaves a window for exactly the concurrent write
		 *	the lock exists to exclude.
		 *
		 *	$fn returning null aborts without writing (eg. "nothing to
		 *	change") and still releases the lock, so a caller with an early
		 *	exit doesn't need its own unlockFile() call.
		 *
		 *	@param		array 			&$appData			(reference) Array with current app data
		 *	@param		string			$path					Filename to lock, read and write
		 *	@param		callable		$fn						fn( mixed $state, array &$appData ): mixed - the new
		 *																	state to write, or null to abort
		 *	@param		mixed				$default			Passed to getFileContent() as the not-found default
		 *
		 *	@return		bool										If a write happened and succeeded
		 */
		public static function mutate( array &$appData, string $path, callable $fn, mixed $default = [] ): bool {

			if( self::lockFile( $appData, $path ) === false )
				return false;

			$appData['./nino/filesystem/cache'][$path]['fstat'] = [];

			$state 	= self::getFileContent( $appData, $path, $default );
			$new 		= $fn( $state, $appData );

			if( $new === null ) {
				self::unlockFile( $appData, $path );
				return false;
			}

			return self::putFileContent( $appData, $path, $new, true );
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

			// 0755, not 0777: with the umask cleared (not unusual on shared
			// hosting, and the default in some cli/cron contexts) 0777 really
			// does mean world-writable - for /images, /data and the asset cache,
			// ie. directories the webserver serves from
			if( is_dir( $dirpath ) === false )
				mkdir( $dirpath, 0755, true );
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
		 *	Return the path config.php actually lives under - normally the
		 *	same as getPath(), but distinct when NINO_CONFIG_DIR moves it
		 *	outside the webroot (see \Nino\init()). Callers that build a list
		 *	of on-disk files by hand (Backup, Restore) must resolve config.php
		 *	through this, not getPath(), or they miss/misplace it entirely
		 *	under a hardened, out-of-webroot setup.
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *
		 *	@return		string											Current filesystem config path
		 */
		public static function getConfigPath( array &$appData ): string {

			return $appData['./nino/filesystem/configpath'];

		}

		/**
		 *	Absolute path -> archive name, for every file the admin panel
		 *	writes to at runtime: config.php, the text files and every
		 *	element type/image. Deliberately not developer code (_nino/,
		 *	templates, _admin/ itself, ...) - that's already versioned in
		 *	git and would just bloat every backup.
		 *
		 *	Shared by Admin\Backup::_create() and Dev\Restore::_safetySnapshot(),
		 *	which both need the exact same manifest for the exact same reason -
		 *	kept here rather than in either since Restore deliberately doesn't
		 *	depend on _admin/Admin.php (see that class' own docblock).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Absolute source path => name inside the archive
		 */
		public static function backupManifest( array &$appData ): array {

			// Defensive: a caller right after writing config.php
			// (Admin\Backup::_bootstrap()) needs the is_file() checks below to
			// see that write rather than a cached pre-write stat
			clearstatcache();

			$root 			= self::getPath( $appData );
			$configPath	= self::getConfigPath( $appData );
			$files 			= [];

			// config.php resolves against configPath, not root - see
			// getConfigPath()'s docblock. Every other file here is always
			// inside the regular project root.
			if( is_file( $configPath. '/config.php' ) === true )
				$files[$configPath. '/config.php'] = 'config.php';

			if( is_file( $root. '/text/global.php' ) === true )
				$files[$root. '/text/global.php'] = 'text/global.php';

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
				if( is_file( $root. '/text/'. $locale. '.php' ) === true )
					$files[$root. '/text/'. $locale. '.php'] = 'text/'. $locale. '.php';

			foreach( glob( $root. '/elements/*.php' ) ?: [] as $file )
				$files[$file] = 'elements/'. basename( $file );

			foreach( glob( $root. '/images/*' ) ?: [] as $file )
				if( is_file( $file ) === true )
					$files[$file] = 'images/'. basename( $file );

			return $files;
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

			// 0755, see forceDir() - this one copies backup/restore trees, so a
			// world-writable mode here would land on the backup directory too
			if( ! file_exists( dirname( $dest ) ) )
				mkdir( dirname( $dest ), 0755, true );

			if( is_file( $source ) )
				return copy( $source, $dest );

			if( ! is_dir( $dest ) )
				mkdir( $dest, 0755, true );

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

			// 'fstat' is the mtime/size fingerprint getFileContent() decides
			// staleness by; lock handles deliberately live outside this slot,
			// see lockFile()
			// Keyed on 'path', not on the slot as a whole: a caller that
			// invalidated the fingerprint (['fstat'] = []) leaves a slot behind
			// that exists but isn't set up yet
			if( isset( $appData['./nino/filesystem/cache'][$filename]['path'] ) === false )
				$appData['./nino/filesystem/cache'][$filename] = [
					'path'				=> $base. '/'. ltrim( $filename, '/' ),
					'content'			=> '',
					'fstat'				=> [],
				];

			return is_file( $appData['./nino/filesystem/cache'][$filename]['path'] );
		}

	}

	/**
	 *	Nino								A compact filesystembased php framework
	 *	RotatingLog					One prune() for every "delete dated files older
	 *											than a cutoff" sweep - Runtime's error log,
	 *											Modules\Form's submissions, Admin\Logs' activity
	 *											log and Admin\Backup's own retention each used
	 *											to carry their own copy
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class RotatingLog {

		/**
		 *	Delete files in $dir named "<prefix><date><suffix>" whose date is
		 *	older than $cutoff. A name whose date portion doesn't parse cleanly
		 *	is left alone, not deleted - not being one of this sweep's files is
		 *	not evidence of being stale, and "can't tell" must never mean
		 *	"delete it" (true of a backup directory most of all).
		 *
		 *	@param		string			$dir					Absolute path to the directory holding the dated files
		 *	@param		string			$prefix				Filename prefix before the date (eg. 'logs.', '')
		 *	@param		string			$dateFormat		'Y-m' (monthly) or 'Y-m-d' (daily) - the date portion's own format
		 *	@param		string			$suffix				Filename suffix after the date (eg. '.php')
		 *	@param		\DateTime		$cutoff				Files dated before this are deleted
		 *
		 *	@return 	void
		 */
		public static function prune( string $dir, string $prefix, string $dateFormat, string $suffix, \DateTime $cutoff ): void {

			foreach( glob( $dir. '/'. $prefix. '*'. $suffix ) ?: [] as $file ) {

				$datePart = substr( basename( $file ), strlen( $prefix ), -strlen( $suffix ) );

				// Always parsed as a full 'Y-m-d', padding a monthly 'Y-m' out
				// to the first of the month first - createFromFormat() would
				// otherwise default a bare 'Y-m''s missing day to *today's*,
				// drifting the cutoff boundary day to day and risking a
				// rollover into the next month entirely (eg. parsing "...-02"
				// on the 31st)
				$full = ( $dateFormat === 'Y-m' ) ? $datePart. '-01' : $datePart;
				$date = \DateTime::createFromFormat( 'Y-m-d', $full );

				// Round-tripped back through the same format rather than
				// matched against a regex first - stricter (createFromFormat()
				// alone tolerates eg. "2026-13-45" by rolling it into a real
				// date) and there is no separate regex to keep in sync with
				// $dateFormat
				if( $date === false || $date->format( 'Y-m-d' ) !== $full )
					continue;

				if( $date->setTime( 0, 0 ) < $cutoff )
					unlink( $file );
			}
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
				$kVal							= (string) $kVal;
				$kValClean				= trim( $kVal, '%' );
				$queryClean[$qKey]	= [ $kVal, $kValClean, strlen( $kValClean ) ];
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

					// Every query key has to match, not just the last one: $hit was
					// reset per key and only ever set on a match, so whatever the
					// final key decided was the result - query="cat=x&status=live"
					// filtered by status alone
					foreach( $queryClean as $qKey => [ $kVal, $kValClean, $kValLen ] )
						if( self::_matchesQueryValue( $elementData[$qKey] ?? null, $kVal, $kValClean, $kValLen ) === false ) {
							$hit = false;
							break;
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
		 *	Whether one element value satisfies one query value, including the
		 *	'%foo%' / '%foo' / 'foo%' wildcard forms
		 *
		 *	@param		mixed			$value				Element value, or null if the element has no such key
		 *	@param		string		$kVal					Query value as given, wildcards included
		 *	@param		string		$kValClean		Query value with the wildcards trimmed off
		 *	@param		int				$kValLen			Length of $kValClean
		 *
		 *	@return 	bool
		 */
		static private function _matchesQueryValue( mixed $value, string $kVal, string $kValClean, int $kValLen ): bool {

			if( is_scalar( $value ) === false )
				return false;

			$value = (string) $value;

			if( $value === $kValClean )
				return true;

			// An empty query value has no wildcard to read - $kVal[0] on '' is an
			// "uninitialized string offset" warning, and the error handler turns
			// that into a 500 for the whole page
			if( $kVal === '' )
				return false;

			$leading	= $kVal[0] === '%';
			$trailing	= $kVal[-1] === '%';

			// A bare '%' (or '%%') is "anything"
			if( $kValClean === '' )
				return $leading === true || $trailing === true;

			if( $leading === true && $trailing === true )
				return strpos( $value, $kValClean ) !== false;

			if( $leading === true )
				return substr( $value, 0 - $kValLen ) === $kValClean;

			if( $trailing === true )
				return substr( $value, 0, $kValLen ) === $kValClean;

			return false;
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

			// $outcome stays 'notfound' unless the callback below actually runs
			// and says otherwise - that covers both "type file missing" (the
			// callback sees a non-array $state and aborts) and "could not be
			// locked at all" (the callback never runs), same message either way
			$outcome = 'notfound';

			$success = \Nino\Filesystem::mutate( $appData, '/elements/'. $typeUri. '.php', function( mixed $typeData, array &$appData ) use ( $elementUri, $locale, $typeUri, &$outcome ): mixed {

				if( is_array( $typeData ) === false )
					return null;

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
				if( \Nino\Callbacks::doCallbacks( $appData, '/nino/elements/delete'. $typeUri, $typeData ) === false ) {
					$outcome = 'veto';
					return null;
				}

				$outcome = 'success';
				return $typeData;
			}, false );

			if( $outcome === 'notfound' )
				return ! trigger_error( 'Element type \''. $typeUri. '\' does not exist or could not be locked for writing.' );

			if( $outcome === 'veto' )
				return null;

			return true;
		}



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

			// $outcome stays 'notfound' unless the callback below actually runs
			// and says otherwise - that covers both "type file missing" (the
			// callback sees a non-array $state and aborts) and "could not be
			// locked at all" (the callback never runs), same message either way
			$outcome 		= 'notfound';
			$resultData	= null;

			$success = \Nino\Filesystem::mutate( $appData, '/elements/'. $typeUri. '.php', function( mixed $typeData, array &$appData ) use ( $uri, $data, $locale, $typeUri, $update, &$outcome, &$resultData ): mixed {

				if( is_array( $typeData ) === false )
					return null;

				// Run callbacks
				$callbacks	= [];
				$data['.uri'] 		= $uri;
				$data['.locale']	= $locale;

				foreach( $typeData['model'] AS $key => $field )
					if( isset( $field['callbacks'] ) === true && isset( $data[$key] ) )
						foreach( $field['callbacks'] AS $callbackUri )
							if( \Nino\Callbacks::doCallbacks( $appData, $callbackUri, $data ) === false ) {
								trigger_error( 'Callback \''. $callbackUri. '\' returns an error.' );
								$outcome = 'error';
								return null;
							}

				// Check new element data
				if( is_array( $data ) === false || isset( $data['.uri'] ) === false || isset( $data['.locale'] ) === false ) {
					$outcome = 'veto';
					return null;
				}

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
						if( $isEmpty === true ) {
							trigger_error( 'Missing required element key \''. $key. '\' in \''. $uri. '\'.' );
							$outcome = 'error';
							return null;
						}
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

					if( gettype( $data[$key] ) !== self::_expectedGettype( $field['type'] ) ) {
						trigger_error( 'Wrong var type \''. $key. '\' in \''. $uri. '\'. \''. $field['type']. '\' required, \''. gettype( $data[$key] ). '\' given.' );
						$outcome = 'error';
						return null;
					}

					// Whitelist
					if( isset( $field['whitelist'] ) === true && in_array( $data[$key], $field['whitelist'] ) === false ) {
						trigger_error( 'Element value \''. $key. '\' is not whitelisted.' );
						$outcome = 'error';
						return null;
					}

					// Blacklist
					if( isset( $field['blacklist'] ) === true && in_array( $data[$key], $field['blacklist'] ) === true ) {
						trigger_error( 'Element value \''. $key. '\' is blacklisted.' );
						$outcome = 'error';
						return null;
					}

					$targetArray = ( isset( $typeData['model'][$key]['locale'] ) === true && $typeData['model'][$key]['locale'] === true ) ? $data['.locale'] : '*';


					// Set value
					$typeData[$targetArray][$elementUri] = $typeData[$targetArray][$elementUri] ?? [];
					$typeData[$targetArray][$elementUri][$key] = $data[$key];
				}

				$typeData['*'][$elementUri] = $typeData['*'][$elementUri] ?? [];

				// Check uri change
				if( $uri !== $data['.uri'] ) {

					\Nino\Callbacks::doCallbacks( $appData, '/nino/elements'. $typeUri. '/update/uri', $data );

					// Same reasoning as deleteElement(): iterate the known locale
					// buckets, not $typeData's own keys. A type file also carries
					// non-locale top-level keys (its display 'title', a plain string),
					// and unset() on a string offset is a fatal Error - renaming an
					// element uri took the whole request down with it. The loop
					// variable was $locale too, overwriting the parameter for
					// everything that follows.
					$oldElementUri = self::getElementUriFromUri( $uri );

					foreach( array_merge( \Nino\Locales::getAvailableLocales( $appData ), [ '*' ] ) AS $typeLocale )
						unset( $typeData[$typeLocale][$oldElementUri] );
				}

				unset( $appData['./nino/elements/cache'] );

				// Run callback - lets a module react to (or veto, by returning
				// false) a save, same veto-capable shape as deleteElement()'s
				// own '/nino/elements/delete<typeUri>' callback below
				$callbackName = '/nino/elements'. $typeUri. ( $update === true ? '/update' : '/insert' );
				if( \Nino\Callbacks::doCallbacks( $appData, $callbackName, $typeData ) === false ) {
					$outcome = 'veto';
					return null;
				}

				$outcome 		= 'success';
				$resultData	= [ '.uri' => $data['.uri'], '.locale' => $data['.locale'] ];

				return $typeData;
			}, false );

			if( $outcome === 'notfound' )
				return ! trigger_error( 'Element type \''. $typeUri. '\' does not exist or could not be locked for writing.' );

			if( $outcome === 'error' )
				return false;

			if( $outcome === 'veto' )
				return null;

			return \Nino\Elements::getElement( $appData, $resultData['.uri'], $resultData['.locale'] );
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

		// How deep shortcode output may be re-rendered into more shortcodes
		// before _doShortcode() stops unrolling - see there
		private const int MAX_RENDER_DEPTH = 20;

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

			if( is_string( $value ) === false )
				return '';

			// A shortcode's output is rendered again (its own fills/shortcodes),
			// which is what makes [template] able to contain other shortcodes -
			// and also what makes a template including itself, directly or
			// through a second one, recurse until the memory limit kills the
			// request. Templates and textfills are editable from _dev/_admin, so
			// that is one typo away. The depth cap stops the recursion without
			// putting a rule on any single shortcode; MAX_RENDER_DEPTH is far
			// above what real nesting (page -> section -> element) reaches.
			$depth = $appData['./nino/html/depth'] ?? 0;

			if( $depth >= self::MAX_RENDER_DEPTH )
				return $value;

			$appData['./nino/html/depth'] = $depth + 1;
			$value = self::renderHtml( $appData, $value );
			$appData['./nino/html/depth'] = $depth;

			return $value;
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
					// frame-ancestors is what actually stops framing in a current
					// browser - X-Frame-Options below is the legacy fallback, and
					// only DENY/SAMEORIGIN are valid values there ('same-origin'
					// was silently ignored, ie. no clickjacking protection at all).
					// base-uri/form-action are not covered by default-src: without
					// them an injected <base>/<form action> escapes the policy -
					// and textfills (Html::_renderFills()) put admin-editable text
					// into the page unescaped, so the csp is load-bearing here.
					'Content-Security-Policy' 		=> 'default-src \'self\'; img-src *; style-src \'self\' \'unsafe-inline\'; frame-ancestors \'self\'; base-uri \'self\'; form-action \'self\'',
					'X-Frame-Options' 						=> 'SAMEORIGIN',
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
				// The method as it came in, before HEAD is folded into GET for
				// routing - output() needs it to send a HEAD response without a
				// body, and it keeps that fold from being invisible to anything
				// else that cares
				'rawMethod'			=> self::_cleanRawMethod( $request['REQUEST_METHOD'], [], false ),
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

			self::_finalizeResponse( $request );

			// Output header
			if( headers_sent() === false )
				foreach( $request['/nino/http/response']['header'] AS $headerKey => $headerValue )
					header( $headerKey. ': '. $headerValue );

			// Send status code
			http_response_code( $request['/nino/http/response']['statusCode'] );

			// Send body - a HEAD response carries the headers of the GET it
			// stands for and nothing else. The request's method has already been
			// folded to GET for routing, so the unmapped value is what decides
			// here (see _cleanRawMethod()). Most sapis would drop the body
			// anyway; not generating it is both cheaper and unambiguous.
			if( ( $request['/nino/http/request']['rawMethod'] ?? '' ) !== 'HEAD' )
				echo $request['/nino/http/response']['body'];

			exit;
		}

		/**
		 *	json-encode a non-string body, then merge in the default status/
		 *	header/etc. keys - the part of output() that decides what would
		 *	actually be sent, split out since output() itself exit()s and so
		 *	can't be called from a test.
		 *
		 *	Not run through filterHeaderFields(): that whitelist is built for
		 *	the request side (Accept, Cookie, ...) and silently drops
		 *	response-only headers a module has every right to set (Set-Cookie,
		 *	Content-Disposition, ETag, ...). Response header values are
		 *	framework/module-computed, not a direct echo of user input, and
		 *	header() itself already refuses to send a value containing a CR/LF.
		 *
		 *	@param		array					&$request			(reference) Current request
		 *
		 *	@return		void
		 */
		private static function _finalizeResponse( array &$request ): void {

			// Catch json output
			if( is_string( $request['/nino/http/response']['body'] ) === false ) {
				$request['/nino/http/response']['body'] = json_encode( $request['/nino/http/response']['body'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
				$request['/nino/http/response']['header']['Content-Type'] = 'application/json; charset=utf-8';
			}

			$request['/nino/http/response'] = array_merge( self::$_defaultResponse, $request['/nino/http/response'] );
			$request['/nino/http/response']['header'] = array_merge( self::$_defaultResponse['header'], $request['/nino/http/response']['header'] );
		}

		/**
		 *	Set a response's status code and error body in one call - the
		 *	statusCode/body pair a handler otherwise sets by hand on every
		 *	failure branch, with 'error' as the body key enforced rather
		 *	than just remembered by convention
		 *
		 *	@param		array 		&$request			(reference) Current request
		 *	@param		int				$statusCode
		 *	@param		string		$error
		 *
		 *	@return 	void
		 */
		public static function fail( array &$request, int $statusCode, string $error ): void {
			$request['/nino/http/response']['statusCode'] = $statusCode;
			$request['/nino/http/response']['body'] = [ 'error' => $error ];
		}

		/**
		 *	Set a response's success body - see fail()
		 *
		 *	@param		array 		&$request			(reference) Current request
		 *	@param		mixed			$body					Response body, defaults to a bare acknowledgement
		 *
		 *	@return 	void
		 */
		public static function ok( array &$request, mixed $body = [ 'ok' => true ] ): void {
			$request['/nino/http/response']['body'] = $body;
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
		 *	and normalizes to the whitelist's own casing - a naive exact match
		 *	would silently drop a header whose casing doesn't match the
		 *	whitelist's literal entry (eg. the request side's 'TE' normalized
		 *	to 'Te' via ucwords()).
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
		 *	@param		bool 		$mapHead				(optional) Whether HEAD resolves to GET (routing) or stays HEAD
		 *
		 *	@return 	string 									Cleaned method
		 */
		static private function _cleanRawMethod( string $rawMethod, array $legalMethods = [], bool $mapHead = true ): string {

			if( $legalMethods === [] )
				$legalMethods = ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH' ];

			$cleanMethod	= preg_replace( '/[^a-zA-Z]/', '', $rawMethod );
			$cleanMethod	= strtoupper( $cleanMethod );

			// Exact match rather than a substring test - 'XXGETXX' used to be
			// accepted as GET, since any legal method contained anywhere in the
			// value won
			if( in_array( $cleanMethod, $legalMethods, true ) === false )
				return '';

			// HEAD is a GET without a response body, but routes are only ever
			// registered for GET - so every HEAD request (uptime monitors, link
			// checkers, some crawlers) found no route and answered 404. The
			// unmapped value stays available as the request's 'rawMethod', which
			// is what output() suppresses the body by.
			return ( $mapHead === true && $cleanMethod === 'HEAD' ) ? 'GET' : $cleanMethod;
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

			// Registered rather than called directly out of \Nino\request(): a
			// route can declare its own 'statusCode' (eg. GET://_admin), and
			// Http::response() array_merge()s the route into the response array
			// *after* seeding it - calling this any earlier had the merge wipe
			// the 302 this sets right back to the route's own status, so the
			// Location header ended up in the response with a 200 and the
			// browser never followed it. Hooking '/nino/http/response' runs
			// this after that merge, same as Modules\Localepicker's own
			// locale-switch callback.
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ] );
		}

		/**
		 *	Switch locale via the '/_nino/locales/current' query param and
		 *	redirect back to the current uri in the new locale
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Catch locale change
			if( isset( $request['/nino/http/request']['query']['/_nino/locales/current'] ) === false )
				return;

			$locale = \Nino\Locales::setCurrentLocale( $appData, $request['/nino/http/request']['query']['/_nino/locales/current'] );
			$newUri = \Nino\Http::findRouteUri( $appData, $request['/nino/http/response']['uri'], $locale );

			// Redirect via the response array - a direct header() call would be
			// overwritten by Http::output()'s own http_response_code() pass
			if( $newUri !== null ) {
				$request['/nino/http/response']['statusCode'] 					= 302;
				$request['/nino/http/response']['header']['Location']	= str_replace( $request['/nino/http/request']['method']. ':/', '', $newUri );
			}
		}

		/**
		 *	Apply the locale of the resolved route, if it declares one (eg.
		 *	config.php's 'GET://rechtliches' => [ ..., 'locale' => 'de_DE' ]).
		 *
		 *	Has to run after Http::response(): the route - and with it its
		 *	'locale' - is only merged into the response array there, so
		 *	comparing response locale against current locale any earlier
		 *	only ever sees the seeded default, not the route's own choice.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current request
		 *
		 *	@return 	void
		 */
		public static function response( array &$appData, array &$request ): void {

			$locale = $request['/nino/http/response']['locale'] ?? '';

			if( is_string( $locale ) === false || $locale === '' || $locale === \Nino\Locales::getCurrentLocale( $appData ) )
				return;

			// setCurrentLocale() also persists into the session, same as the
			// ?/_nino/locales/current switch above - visiting a locale-specific
			// page is a locale choice like any other
			$request['/nino/http/response']['locale'] = \Nino\Locales::setCurrentLocale( $appData, $locale );
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
	 *	Text								The [[key]] textfill layer both _admin's Text panel
	 *												and _dev's own text editor sit on top of - reading
	 *												every known key out of /text/global.php + every
	 *												/text/{locale}.php, and batching a value save into
	 *												one lock/read/write per target file. Everything
	 *												that differs between the two UIs (blacklist
	 *												filtering vs. an editable blacklist flag, a
	 *												PERM-gated save vs. a session-gated one, shape
	 *												conversion) stays in _admin/Admin.php and _dev/Dev.php
	 *												- this only holds what was byte-for-byte identical
	 *												between them.
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Text {

		private const int MIN_MAXLENGTH 		= 150;
		private const int MAX_MAXLENGTH 		= 2000;
		private const int MAXLENGTH_BUFFER = 150;
		private const int HARD_MAXLENGTH 	= 20000;

		/**
		 *	Every known key across global.php + every locale file, with its
		 *	current value(s), whether it's global or per-locale, whether it
		 *	currently holds markup, a maxlength derived from its longest
		 *	current value, and whether it's blacklisted (see blacklist())
		 *
		 *	@param		array 		&$appData						(reference) Array with current app data
		 *	@param		bool			$includeBlacklisted	Whether a blacklisted key is included at all, not
		 *																			just flagged - _admin's Text panel hides them
		 *																			entirely, _dev's own editor needs to see them
		 *																			to be able to un-blacklist one
		 *
		 *	@return 	array											List of [ key, global, blacklisted, html, maxlength, values ]
		 */
		public static function entries( array &$appData, bool $includeBlacklisted = true ): array {

			$global 	= \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
			$locales 	= \Nino\Locales::getAvailableLocales( $appData );

			$localeData = [];
			foreach( $locales as $locale )
				$localeData[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			$blacklist = self::blacklist( $appData );

			$bracketKeys = array_keys( $global );
			foreach( $localeData as $data )
				$bracketKeys = array_merge( $bracketKeys, array_keys( $data ) );
			$bracketKeys = array_unique( $bracketKeys );

			$entries = [];

			foreach( $bracketKeys as $bracketKey ) {

				$key 					= trim( $bracketKey, '[]' );
				$isBlacklisted = isset( $blacklist[$key] ) === true;

				if( $isBlacklisted === true && $includeBlacklisted === false )
					continue;

				$isGlobal = array_key_exists( $bracketKey, $global );
				$values 	= $isGlobal
					? [ '*' => $global[$bracketKey] ]
					: array_map( fn( array $data ) => $data[$bracketKey] ?? null, $localeData );

				$longest 	= 0;
				$html 		= false;

				foreach( $values as $value ) {
					if( $value === null )
						continue;
					$longest 	= max( $longest, strlen( $value ) );
					$html 		= $html || \Nino\Html::containsHtml( $value );
				}

				$entries[] = [
					'key' 				=> $key,
					'global' 			=> $isGlobal,
					'blacklisted' => $isBlacklisted,
					'html' 				=> $html,
					'maxlength' 	=> min( self::MAX_MAXLENGTH, max( self::MIN_MAXLENGTH, $longest + self::MAXLENGTH_BUFFER ) ),
					'values' 			=> $values,
				];
			}

			usort( $entries, fn( array $a, array $b ) => strcmp( $a['key'], $b['key'] ) );

			return $entries;
		}

		/**
		 *	Find one key's entry (see entries())
		 *
		 *	@param		array 		&$appData						(reference) Array with current app data
		 *	@param		string		$key								Bare key path (eg. "/home/welcome/h2")
		 *	@param		bool			$includeBlacklisted	Same meaning as entries()'s own parameter
		 *
		 *	@return 	array | null							The entry, or null if unknown (or hidden by $includeBlacklisted)
		 */
		public static function entry( array &$appData, string $key, bool $includeBlacklisted = true ): array|null {

			foreach( self::entries( $appData, $includeBlacklisted ) as $entry )
				if( $entry['key'] === $key )
					return $entry;

			return null;
		}

		/**
		 *	Read the developer-maintained list of keys hidden from _admin's
		 *	Text panel (technical values, not content - uris, colors,
		 *	typography, ...)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Set of blacklisted keys (key => true)
		 */
		public static function blacklist( array &$appData ): array {
			return array_flip( \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ) );
		}

		/**
		 *	Add or remove one key from /text/blacklist.php - _dev-only,
		 *	_admin's Text panel only ever reads the list
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *	@param		bool			$blacklisted
		 *
		 *	@return 	void
		 */
		public static function setBlacklisted( array &$appData, string $key, bool $blacklisted ): void {

			\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $list ) use ( $key, $blacklisted ): ?array {

				$has = in_array( $key, $list, true );

				if( $blacklisted === true && $has === false )
					$list[] = $key;
				else if( $blacklisted === false && $has === true )
					$list = array_values( array_diff( $list, [ $key ] ) );
				else
					return null;

				return $list;
			} );
		}

		/**
		 *	Save several keys' values in one request, batched per target file
		 *	- a locale file gets one lock -> re-read -> write cycle no matter
		 *	how many of its keys changed, instead of one per key. That's not
		 *	just an optimization: two separate saves hitting the same file
		 *	concurrently would race (each reads the file before the other's
		 *	write lands, so one update gets silently lost) - batching removes
		 *	the race entirely by construction.
		 *
		 *	A per-item failure (unknown/blacklisted key, invalid locale)
		 *	doesn't fail the whole call - it's reported per key in the
		 *	returned results so the other, valid items still get saved.
		 *
		 *	@param		array 		&$appData						(reference) Array with current app data
		 *	@param		array 		$items							[ [ key, locale, value ], ... ]
		 *	@param		bool			$includeBlacklisted	Same meaning as entries()'s own parameter -
		 *																			whether a blacklisted key is a valid save target
		 *
		 *	@return 	array											key => [ ok, value ] or [ ok, error ]
		 */
		public static function saveBatch( array &$appData, array $items, bool $includeBlacklisted ): array {

			$results 		= [];
			$fileChanges = [];

			foreach( $items as $item ) {

				$key 		= (string) ( $item['key'] ?? '' );
				$locale = (string) ( $item['locale'] ?? '' );
				$value 	= (string) ( $item['value'] ?? '' );

				$entry = self::entry( $appData, $key, $includeBlacklisted );

				if( $entry === null ) {
					$results[$key] = [ 'ok' => false, 'error' => 'unknown key' ];
					continue;
				}

				if( $entry['global'] === false && \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
					$results[$key] = [ 'ok' => false, 'error' => 'invalid locale' ];
					continue;
				}

				$value = substr( $value, 0, self::HARD_MAXLENGTH );
				$value = ( $entry['html'] === true ) ? \Nino\Html::sanitizeHtml( $value ) : strip_tags( $value );

				$file = ( $entry['global'] === true ) ? '/text/global.php' : '/text/'. $locale. '.php';

				$fileChanges[$file]['[['. $key. ']]'] = $value;

				$results[$key] = [ 'ok' => true, 'value' => $value ];
			}

			foreach( $fileChanges as $file => $changes )
				\Nino\Filesystem::mutate( $appData, $file, function( array $content ) use ( $changes ): array {
					return array_merge( $content, $changes );
				} );

			return $results;
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

			if( self::_hit( $appData, \Nino\Http::getClientIp() ) === false ) {

				// Flagged rather than just reported through the return value, so
				// a caller can tell "we refused to send this" apart from "mail()
				// failed" - Form uses it to not record a submission whose mail
				// was never attempted (see its callbackResponse())
				$appData['./nino/mail/ratelimited'] = true;

				return false;
			}

			// Everything that ends up on a header line gets its CR/LF stripped.
			// $subject comes from an admin-editable textfill and send() is public
			// api, so without this a newline in any of them injects headers of
			// its own (a Bcc: to somewhere else being the obvious one).
			$to				= self::_headerValue( $to );
			$subject	= self::_headerValue( $subject );
			$replyTo	= self::_headerValue( $replyTo );

			if( $to === '' )
				return false;

			// Without a From: the mail goes out as the webserver user
			// (www-data@some-host), which is the single most reliable way to end
			// up in a spam folder. The envelope sender (-f) matters just as
			// much: it is what SPF is checked against, and PHP defaults it to
			// the same webserver user.
			$sender = self::_getSender( $appData );

			$headers = 'MIME-Version: 1.0';
			$headers .= "\r\n". 'Content-Type: text/html; charset=UTF-8';

			if( $sender !== '' )
				$headers .= "\r\n". 'From: '. $sender;

			if( $replyTo !== '' )
				$headers .= "\r\n". 'Reply-To: '. $replyTo;

			// Encoded after _headerValue() above, not before: the CR/LF strip
			// has to run against the raw, untrusted subject - encoding first
			// would mean sanitizing mb_encode_mimeheader()'s output instead of
			// the actual input
			$subject = mb_encode_mimeheader( $subject, 'UTF-8', 'B' );

			// -f only for an address that validated - the parameter goes to the
			// sendmail command line, so it must never carry anything unchecked
			return ( $sender !== '' )
				? mail( $to, $subject, $body, $headers, '-f'. $sender )
				: mail( $to, $subject, $body, $headers );
		}

		/**
		 *	A single header value with anything that could start a new header
		 *	line removed
		 *
		 *	@param		string		$value				Raw value
		 *
		 *	@return 	string
		 */
		private static function _headerValue( string $value ): string {

			return trim( str_replace( [ "\r", "\n" ], '', $value ) );
		}

		/**
		 *	The address to send as: '/nino/mail/sender' from config.php if set,
		 *	otherwise the site owner's address from the Text values. Configurable
		 *	because the envelope sender has to be an address the sending host is
		 *	allowed to send for (spf/dmarc), which is not necessarily the mailbox
		 *	replies should go to. Returns '' if neither is a valid address, in
		 *	which case send() simply omits From:/-f rather than passing something
		 *	unchecked to sendmail.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string
		 */
		private static function _getSender( array &$appData ): string {

			$sender = self::_headerValue( (string) ( $appData['/nino/mail/sender'] ?? '' ) );

			if( $sender === '' )
				$sender = self::_headerValue( \Nino\Html::renderHtml( $appData, '[[/form/email/owner]]' ) );

			return ( filter_var( $sender, FILTER_VALIDATE_EMAIL ) !== false ) ? $sender : '';
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
			$tries	= null;

			// An unlocked read-modify-write lets two parallel sends read the
			// same counter and write back the same +1, bypassing the cap by
			// simply firing requests concurrently - which is what a sending
			// burst looks like anyway. No reliable counter, no send: an
			// unreliable cap is worse than a closed one here.
			$written = \Nino\Filesystem::mutate( $appData, $path, function( array $state ) use ( $key, $now, &$tries ): array {

				foreach( $state as $stateKey => $entry )
					if( ( $entry['reset'] ?? 0 ) <= $now )
						unset( $state[$stateKey] );

				$entry 					= $state[$key] ?? [ 'tries' => 0, 'reset' => $now + self::WINDOW ];
				$entry['tries']	= (int) $entry['tries'] + 1;
				$state[$key] 		= $entry;
				$tries 					= $entry['tries'];

				return $state;
			} );

			return $written === true && $tries <= self::MAX_TRIES;
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

			// method_exists() autoloads $className itself if it isn't defined
			// yet (see the spl_autoload_register() call at the bottom of this
			// file) - config.php's own '/nino/modules' list only ever
			// controlled which modules get a method called on them here, not
			// which classes exist to be called at all, so there is nothing
			// left for this loop to load by hand
			foreach( $appData['/nino/modules'] ?? [] as $className )
				if( method_exists( $className, $method ) === true )
					$className::$method( $appData );
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
				echo '<pre>';
				var_dump( $errorArray, debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) );
				die('</pre>');
			}

			// Break current cycle - SERVER_PROTOCOL is absent on cli (and can be
			// absent behind an odd sapi), and an undefined-key warning raised
			// inside the error handler would re-enter this very method
			header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1' ). ' 500 Internal Server Error', true, 500 );
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

				$path = '/data/logs.'. date( 'Y-m' ). '.php';

				\Nino\Filesystem::mutate( $appData, $path, function( array $entries ) use ( $entry ): array {
					$entries[] = $entry + [ 'date' => date( 'Y-m-d H:i:s' ) ];
					return $entries;
				} );

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

			\Nino\RotatingLog::prune( $dir, 'logs.', 'Y-m', '.php', $cutoff );
		}
	}
}


namespace {

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules							Autoloads \Nino\Modules\* (one file per module, under
	 *											Nino/Modules/) and any project's own custom module
	 *											classes on first use, from the same
	 *											<namespace-as-path>/<basename>.php layout
	 *											Modules::callModules() itself derives a class' file
	 *											from - config.php's '/nino/modules' list only ever
	 *											controlled which modules get init()/request()/
	 *											response() called on them, never which classes exist
	 *											to be called at all, so a call site that references a
	 *											module class directly (tests among them), without
	 *											going through callModules() first, needs to resolve
	 *											the same way
	 */
	spl_autoload_register( function( string $className ): void {

		$relativePath	= str_replace( '\\', '/', $className );
		$filename			= __DIR__. '/'. $relativePath. '/'. basename( $relativePath ). '.php';

		if( is_file( $filename ) === true )
			require $filename;
	} );
}
