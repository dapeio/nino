<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Auth					Session login/logout, user management, and the granular permission system for admin accounts
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Auth - session login/logout, user management, and the granular
	// permission system for admin accounts
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

		public static function init( array &$appData ): void {

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
			// is_string() guards against a session that holds something else
			// under either key - a cookie outliving a deploy, or a hand-edited
			// session store. Used as an array key/isset() offset below, that
			// would be a TypeError rather than a miss, 500ing every request
			// from that client instead of just treating it as logged out
			$sessionMail 	= \Nino\Runtime::getSessionValue( $appData, './nino/auth/current', '' );
			$sessionToken	= \Nino\Runtime::getSessionValue( $appData, './nino/auth/token', '' );
			if( is_string( $sessionMail ) === true && $sessionMail !== '' && is_string( $sessionToken ) === true && $sessionToken !== '' )
				self::_resumeSession( $appData, $sessionMail, $sessionToken );

			$appData['/nino/http/routes']['POST://.nino/auth/login'] = [ 'uri' => '/.nino/auth/login' ];
			$appData['/nino/http/routes']['POST://.nino/auth/logout'] = [ 'uri' => '/.nino/auth/logout' ];
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.nino/auth/login', [ self::class, 'callbackLoginResponse' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.nino/auth/logout', [ self::class, 'callbackLogoutResponse' ] );
		}

		public static function callbackLoginResponse( array &$appData, array &$request ): void {

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

		public static function callbackLogoutResponse( array &$appData, array &$request ): void {

			if( ( $request['./nino/csrf/blocked'] ?? false ) === true )
				return;

			self::logoutUser( $appData );

			$request['/nino/http/response']['statusCode']	= 200;
			$request['/nino/http/response']['body']				= true;
		}


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

			// The attempt succeeded, so the buckets that counted towards a
			// lockout are cleared. Without this a sub-threshold counter is
			// never reset: a user's occasional typos accumulate across months
			// of successful logins until the maxtries'th one - years apart,
			// each followed by a correct password - trips the cooldown, and
			// everyone behind one nat shares that through the ip bucket.
			// The ip bucket goes too: whoever just proved a valid credential
			// is authenticated either way, so keeping their ip half-locked
			// only ever punishes the neighbours sharing it
			self::_clearTries( $appData, array_merge( $ipKeys, [ $username ] ) );

			// Rotate the session id + csrf token now that the session's identity
			// is changing (session-fixation defense) - the status guard keeps
			// cli callers (tests) without an active session working
			if( session_status() === PHP_SESSION_ACTIVE )
				session_regenerate_id( true );
			\Nino\Csrf::rotateToken( $appData );

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

			// Prune expired session entries opportunistically, on the one write
			// this method was already going to make - same "clean up on write"
			// idea as Mail::_hit(). The is_array() half is not about age: these
			// entries come out of config.php, which is a file a developer edits
			// by hand, and a scalar where the ['time','ip'] shape is expected
			// would be a TypeError rather than a stale entry.
			$now = time();
			foreach( $user['sessions'] as $sessionToken => $sessionData )
				if( is_array( $sessionData ) === false || ( $sessionData['time'] ?? 0 ) < $now - self::SESSION_TTL )
					unset( $user['sessions'][$sessionToken] );

			// Every login mints a new token, so there is never an "already have
			// a session for this key" case to skip the write for
			$user['sessions'][$token] = [ 'time' => $now, 'ip' => \Nino\Http::getClientIp() ];
			$appData['/nino/auth/user'][$user['mail']] = $user;
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			return $user;
		}


		public static function logoutUser( array &$appData ): void {


			// Logout session auth
			\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/current' );

			// Rotate the csrf token now that the session's identity is changing (session-fixation defense)
			\Nino\Csrf::rotateToken( $appData );

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


		public static function getCurrentUser( array &$appData ): array|false {

			return $appData['./nino/auth/current'] ?? false;
		}

		public static function getUser( array &$appData, string $username ): array|false {
			if( isset( $appData['/nino/auth/user'][$username] ) === false )
				return false;

			return $appData['/nino/auth/user'][$username] + [ 'mail' => $username ] ;
		}



		/**
		 *	The permissions an account actually holds: its own, plus those of
		 *	its role. A role is a named set of permissions kept under
		 *	'/nino/auth/roles' ([ id => [ 'label' => ..., 'perms' => [ ... ] ] ])
		 *	and edited in the workbench's Users panel; an account carries the
		 *	role's id under 'role'. A role the config no longer has grants
		 *	nothing, silently - the account keeps what it holds itself, and
		 *	the Users panel shows the dangling role for what it is
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$user					A user record (see getUser())
		 *
		 *	@return 	array										Permission strings, deduplicated
		 */
		public static function permissions( array &$appData, array $user ): array {

			$perms = is_array( $user['perms'] ?? null ) === true ? array_values( array_filter( $user['perms'], 'is_string' ) ) : [];
			$role  = (string) ( $user['role'] ?? '' );

			if( $role !== '' && is_array( $appData['/nino/auth/roles'][$role]['perms'] ?? null ) === true )
				$perms = array_merge( $perms, array_values( array_filter( $appData['/nino/auth/roles'][$role]['perms'], 'is_string' ) ) );

			return array_values( array_unique( $perms ) );
		}

		/**
		 *	Give an account a role, or none ('') - the role has to exist
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$username			The account's mail
		 *	@param		string		$role					A key of '/nino/auth/roles', or ''
		 *
		 *	@return 	bool										False for an unknown account or role
		 */
		public static function setRole( array &$appData, string $username, string $role ): bool {

			if( self::getUser( $appData, $username ) === false )
				return false;

			if( $role !== '' && isset( $appData['/nino/auth/roles'][$role] ) === false )
				return false;

			$appData['/nino/auth/user'][$username]['role'] = $role;
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			// The session's copy of its own record follows, so a role changed
			// on yourself applies to the very request that changed it
			if( ( $appData['./nino/auth/current']['mail'] ?? '' ) === $username )
				$appData['./nino/auth/current'] = self::getUser( $appData, $username );

			return true;
		}

		public static function insertUser( array &$appData, string $username, string $pw, array $perms = [], string $role = '' ): bool {

			if( self::getUser( $appData, $username ) !== false )
				return false;

			// A role the config does not have is a typo, not an account with
			// no rights - refused here rather than stored as a dangling name
			if( $role !== '' && isset( $appData['/nino/auth/roles'][$role] ) === false )
				return false;

			$appData['/nino/auth/user'][$username] = [
				'pw'				=> password_hash( $pw, PASSWORD_DEFAULT ),
				'status'		=> 2,
				'sessions'	=> [],
				'perms'			=> $perms,
				'role'			=> $role,
			];

			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

			// Run callback - eg. a welcome mail, or syncing the new account elsewhere.
			// getUser()'s result has to sit in a variable first: doCallbacks()'s
			// third parameter is by-reference, and passing a function call
			// straight through raises an "Only variables should be passed by
			// reference" notice - fatal here, since Runtime::handleError() is
			// live on every real request (unlike the CLI smoke tests, which
			// call these methods directly without ever booting it)
			$inserted = self::getUser( $appData, $username );
			\Nino\Callbacks::doCallbacks( $appData, '/nino/auth/user/insert', $inserted );

			return true;
		}


		public static function deleteUser( array &$appData, string $username ): bool {

			$user = self::getUser( $appData, $username );
			if( $user === false )
				return false;

			unset( $appData['/nino/auth/user'][$username] );

			// Only a deleted *own* account ends the session it is deleted
			// from - a manager removing someone else stays logged in for the
			// rest of the request, the same as the next request would find
			// them anyway (see _resumeSession())
			if( ( $appData['./nino/auth/current']['mail'] ?? null ) === $username )
				unset( $appData['./nino/auth/current'] );


			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );
			self::_dropTries( $appData, $username );

			// Run callback - eg. cleaning up the now-deleted account elsewhere
			\Nino\Callbacks::doCallbacks( $appData, '/nino/auth/user/delete', $user );

			return true;
		}


		// Update a user's mail and/or password. Perms/sessions/status are
		// left untouched - those stay a developer-only, direct-json task.
		// A tries counter (see TRIES_PATH) follows a mail change so an
		// in-progress cooldown survives a rename.
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

			// Run callback - eg. syncing the changed mail/password elsewhere.
			// See insertUser() for why this can't pass self::getUser(...) straight through.
			$updatedUser = self::getUser( $appData, $newUsername );
			\Nino\Callbacks::doCallbacks( $appData, '/nino/auth/user/update', $updatedUser );

			return self::getUser( $appData, $newUsername );
		}


		// Clear all of a user's active sessions ("log out everywhere"). If
		// that's the current user, also ends the current request's session.
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
				\Nino\Csrf::rotateToken( $appData );
				unset( $appData['./nino/auth/current'] );
			}

			return true;
		}


		public static function checkPermission( array &$appData, string $perm, string $username = '' ): bool {

			// Get current user data
			$user = ( $username !== '' ) ? self::getUser( $appData, $username ) : ( self::getCurrentUser( $appData ) ?? false );

			if( $user === false )
				return false;

			// The account's own permissions plus its role's - strings only:
			// without that a single truthy non-string in perms (a config typo
			// like 'perms' => [ true ]) loosely matches every permission there
			// is, ie. silently grants everything
			$perms = self::permissions( $appData, $user );

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


		// Restore the logged-in user behind a session token - if that token
		// is still listed on the user AND hasn't outlived SESSION_TTL. The
		// ttl used to be enforced in loginUser() only, ie. on write: tokens
		// were pruned when their owner logged in again, so an account that
		// never logs in again kept every token it ever handed out, forever.
		// An expired token is dropped right here rather than just ignored,
		// so it can't sit in config.php until that next login either.
		private static function _resumeSession( array &$appData, string $mail, string $token ): void {

			$user = self::getUser( $appData, $mail );

			if( $user === false || is_array( $user['sessions'][$token] ?? null ) === false )
				return;

			// The same 'status' gate loginUser() applies, enforced on read as
			// well as on write: an account that can no longer log in must not
			// keep the sessions it was handed before it was disabled.
			// Disabling one is a direct-json task (see updateUser()), and
			// without this the account stays fully authorised in every browser
			// still holding a listed token - up to SESSION_TTL later
			if( ( $user['status'] ?? 0 ) !== 2
				|| ( $user['sessions'][$token]['time'] ?? 0 ) < time() - self::SESSION_TTL ) {

				unset( $appData['/nino/auth/user'][$mail]['sessions'][$token] );
				\Nino\AppData::writeContentData( $appData, [ '/nino/auth/user' ] );

				\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/current' );
				\Nino\Runtime::unsetSessionValue( $appData, './nino/auth/token' );

				return;
			}

			$appData['./nino/auth/current'] = $user;
		}

		// Current tries counter for an username - same negative-timestamp
		// encoding _registerFailedAttemp() writes: zero/positive is a plain
		// attempt count, negative means "still cooling down until -tries"
		private static function _getTries( array &$appData, string $username ): int {

			$state = \Nino\Filesystem::getFileContent( $appData, self::TRIES_PATH, [] );

			return (int) ( $state[$username] ?? 0 );
		}

		// Whether a bucket (account mail or IP_KEY_PREFIX.ip) is still
		// cooling down, ie. holds a negative timestamp that lies in the
		// future - see _registerFailedAttemp()'s encoding
		private static function _inCooldown( array &$appData, string $key ): bool {

			return self::_getTries( $appData, $key ) < 0 - time();
		}

		// Remove a username's tries entry entirely - called on deleteUser()
		// so TRIES_PATH doesn't accumulate an orphaned entry for an
		// account that no longer exists
		private static function _dropTries( array &$appData, string $username ): void {

			\Nino\Filesystem::mutate( $appData, self::TRIES_PATH, function( array $state ) use ( $username ): ?array {

				if( isset( $state[$username] ) === false )
					return null;

				unset( $state[$username] );
				return $state;
			} );
		}

		// Clear several tries buckets in one read-modify-write - the success
		// counterpart to _registerFailedAttemp(). _dropTries() stays the
		// single-key version deleteUser() uses; null means "nothing to
		// change", so a login by an account with a clean record does not
		// rewrite the file at all
		private static function _clearTries( array &$appData, array $keys ): void {

			\Nino\Filesystem::mutate( $appData, self::TRIES_PATH, function( array $state ) use ( $keys ): ?array {

				$changed = false;

				foreach( $keys as $key )
					if( isset( $state[$key] ) === true ) {
						unset( $state[$key] );
						$changed = true;
					}

				return ( $changed === true ) ? $state : null;
			} );
		}

		// Move a username's tries entry to a new key - called on
		// updateUser()'s rename path so an in-progress cooldown survives a
		// mail change instead of being silently dropped (reset) or left
		// behind as an orphan under the old, now-unused mail
		private static function _renameTries( array &$appData, string $oldUsername, string $newUsername ): void {

			\Nino\Filesystem::mutate( $appData, self::TRIES_PATH, function( array $state ) use ( $oldUsername, $newUsername ): ?array {

				if( isset( $state[$oldUsername] ) === false )
					return null;

				$state[$newUsername] = $state[$oldUsername];
				unset( $state[$oldUsername] );
				return $state;
			} );
		}

		// Register one failed login attempt against every given bucket
		// (account mail, client ip, or both) in a single read-modify-write,
		// rather than one file rewrite per bucket
		private static function _registerFailedAttemp( array &$appData, array $keys ): false {

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
}
