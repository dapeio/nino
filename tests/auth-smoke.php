<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	auth-smoke.php			Dependency-free smoke test for \Nino\Auth and \Nino\Csrf:
 *											insertUser(), loginUser() and deleteUser(), the lockout after
 *											maxtries failed attempts, sessions and disabled accounts,
 *											updateUser() and logoutAllSessions(), and the csrf token and its
 *											check on a request. Runs against an isolated sandbox directory,
 *											never touches the real project data.
 *
 *	Usage: php tests/auth-smoke.php
 */

require __DIR__. '/harness.php';

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$appData = ninoSandbox( 'auth' );
$sandbox = ninoSandboxDir( $appData );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData['/nino/auth/maxtries']	= 3;
$appData['/nino/auth/cooldown']	= 3600;

echo "Sandbox: $sandbox\n\n";


// --- Auth ------------------------------------------------------------

echo "Auth::insertUser / loginUser / deleteUser\n";

check( 'insertUser creates a new user', \Nino\Auth::insertUser( $appData, 'test@example.com', 'correct horse battery staple' ) === true );
check( 'insertUser rejects a duplicate user', \Nino\Auth::insertUser( $appData, 'test@example.com', 'whatever' ) === false );

$loginFail = \Nino\Auth::loginUser( $appData, 'test@example.com', 'wrong password' );
check( 'loginUser rejects a wrong password', $loginFail === false );

$loginOk = \Nino\Auth::loginUser( $appData, 'test@example.com', 'correct horse battery staple' );
check( 'loginUser accepts the right password', is_array( $loginOk ) === true );
check( 'loginUser sets the current user', \Nino\Auth::getCurrentUser( $appData ) !== false );
$loginSessions = \Nino\Auth::getUser( $appData, 'test@example.com' )['sessions'];
check( 'loginUser records a session token carrying the client ip', count( $loginSessions ) === 1 && array_values( $loginSessions )[0]['ip'] === '127.0.0.1' );

// Password hash rotation: force an outdated (low-cost) hash, then log in again and confirm it gets rotated
$appData['/nino/auth/user']['test@example.com']['pw'] = password_hash( 'correct horse battery staple', PASSWORD_BCRYPT, [ 'cost' => 4 ] );
$oldHash = $appData['/nino/auth/user']['test@example.com']['pw'];
\Nino\Auth::loginUser( $appData, 'test@example.com', 'correct horse battery staple' );
$newHash = $appData['/nino/auth/user']['test@example.com']['pw'];
check( 'loginUser rotates an outdated password hash', $newHash !== $oldHash && password_verify( 'correct horse battery staple', $newHash ) === true );

// Cooldown after too many failed attempts (maxtries = 3 in this sandbox)
\Nino\Auth::loginUser( $appData, 'test@example.com', 'wrong' );
\Nino\Auth::loginUser( $appData, 'test@example.com', 'wrong' );
\Nino\Auth::loginUser( $appData, 'test@example.com', 'wrong' );
check( 'loginUser locks out after maxtries failed attempts', \Nino\Auth::loginUser( $appData, 'test@example.com', 'correct horse battery staple' ) === false );

// A burst of parallel guesses has every request pass loginUser()'s cooldown
// check before the first of them locks the bucket, so the registrations
// after that one arrive at a bucket that is already locked. They used to
// count the lock as a fresh first try, which reopened the account the moment
// the maxtries'th attempt had closed it - a lockout that held for guesses
// made one after the other and never for the ones made at once. loginUser()
// itself never registers against a bucket it found locked, so the late
// registration is exercised directly (invokeArgs() with a reference - see
// the Mail::_hit section of form-mail-smoke.php for why)
$register			= new ReflectionMethod( '\Nino\Auth', '_registerFailedAttemp' );
$triesBefore	= \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] );
$register->invokeArgs( null, [ &$appData, [ 'ip:127.0.0.1', 'test@example.com' ] ] );
$triesAfter		= \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] );
check( 'a failed attempt registered against a bucket a parallel request has just locked leaves the lock as it is', $triesBefore['test@example.com'] < 0 && $triesAfter['test@example.com'] === $triesBefore['test@example.com'] );
check( '...and still counts the other buckets of the same attempt', $triesAfter['ip:127.0.0.1'] === $triesBefore['ip:127.0.0.1'] + 1 );
check( '...so the account stays locked', \Nino\Auth::loginUser( $appData, 'test@example.com', 'correct horse battery staple' ) === false );

check( 'deleteUser removes the user', \Nino\Auth::deleteUser( $appData, 'test@example.com' ) === true );
check( 'getUser no longer finds the deleted user', \Nino\Auth::getUser( $appData, 'test@example.com' ) === false );

// A successful login has to clear the buckets that were counting towards a
// lockout. They used to survive it - _dropTries() ran on deleteUser() only -
// so a user's occasional typos added up across months of correct logins until
// the maxtries'th one, years apart, tripped the cooldown
\Nino\Auth::insertUser( $appData, 'counter@example.com', 'correct horse battery staple' );
\Nino\Auth::loginUser( $appData, 'counter@example.com', 'wrong' );
\Nino\Auth::loginUser( $appData, 'counter@example.com', 'wrong' );
check( 'a login still succeeds with failed attempts on the record', is_array( \Nino\Auth::loginUser( $appData, 'counter@example.com', 'correct horse battery staple' ) ) === true );
check( '...and drops the account\'s bucket from auth-tries.php', isset( \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] )['counter@example.com'] ) === false );

\Nino\Auth::loginUser( $appData, 'counter@example.com', 'wrong' );
\Nino\Auth::loginUser( $appData, 'counter@example.com', 'wrong' );
check( '...so two more typos later do not add up to a lockout', is_array( \Nino\Auth::loginUser( $appData, 'counter@example.com', 'correct horse battery staple' ) ) === true );
\Nino\Auth::deleteUser( $appData, 'counter@example.com' );

/*	The ip bucket's own rules, none of which had an assertion: every login in
	this suite comes from 127.0.0.1, so the bucket was live in all of them and
	read by none of them. Each rule below was proven by breaking it in Auth.php
	and watching its check fail. The buckets are seeded through the file the
	way a burst of requests would have left them, rather than by thirty
	bcrypt rounds	*/
$triesFile	= '/data/auth-tries.php';
$ipFactor		= (int) ( new ReflectionClassConstant( '\Nino\Auth', 'IP_TRIES_FACTOR' ) )->getValue();
$seedTries	= static function( array $seed ) use ( &$appData, $triesFile ): void {
	\Nino\Filesystem::mutate( $appData, $triesFile, static function( array $state ) use ( $seed ): array {
		foreach( $seed as $key => $value )
			if( $value === null )
				unset( $state[$key] );
			else
				$state[$key] = $value;
		return $state;
	} );
};
$readTries	= static fn( string $key ): ?int => \Nino\Filesystem::getFileContent( $appData, $triesFile, [] )[$key] ?? null;

\Nino\Auth::insertUser( $appData, 'bucket@example.com', 'correct horse battery staple' );

// An ip in cooldown is refused before anything else is looked at - the
// right password included - and the refusal moves no account bucket
$seedTries( [ 'ip:127.0.0.1' => 0 - time() - 3600, 'bucket@example.com' => null ] );
check( 'an ip in cooldown is refused with the right password too', \Nino\Auth::loginUser( $appData, 'bucket@example.com', 'correct horse battery staple' ) === false );
check( '...and that refusal does not count against the account', $readTries( 'bucket@example.com' ) === null );

// The ip bucket counts guesses against accounts that do not exist - that is
// what it is for - and trips at maxtries times the factor, never at
// maxtries: a shared exit ip is not one person mistyping
$seedTries( [ 'ip:127.0.0.1' => $appData['/nino/auth/maxtries'] - 1 ] );
\Nino\Auth::loginUser( $appData, 'nobody@example.com', 'wrong' );
check( 'a guess against an account that does not exist counts against the ip', $readTries( 'ip:127.0.0.1' ) === $appData['/nino/auth/maxtries'] );
check( '...and at maxtries the ip is not locked', is_array( \Nino\Auth::loginUser( $appData, 'bucket@example.com', 'correct horse battery staple' ) ) === true );
check( 'a successful login clears the ip bucket along with the account\'s', $readTries( 'ip:127.0.0.1' ) === null );

$seedTries( [ 'ip:127.0.0.1' => $appData['/nino/auth/maxtries'] * $ipFactor - 1 ] );
\Nino\Auth::loginUser( $appData, 'nobody@example.com', 'wrong' );
check( 'at maxtries times the factor the ip is locked, and the right password no longer helps', $readTries( 'ip:127.0.0.1' ) < 0 && \Nino\Auth::loginUser( $appData, 'bucket@example.com', 'correct horse battery staple' ) === false );

// An account already in cooldown feeds no bucket: its owner retrying their
// own locked login must not take their whole ip out with it
$seedTries( [ 'ip:127.0.0.1' => null, 'bucket@example.com' => 0 - time() - 3600 ] );
\Nino\Auth::loginUser( $appData, 'bucket@example.com', 'wrong' );
check( 'a guess against an account in cooldown moves no bucket, the ip\'s included', $readTries( 'ip:127.0.0.1' ) === null && $readTries( 'bucket@example.com' ) < 0 );

// No client ip - the cli, a test - means no ip bucket, rather than one
// shared empty bucket every such caller falls into
$seedTries( [ 'ip:127.0.0.1' => null, 'ip:' => null, 'bucket@example.com' => null ] );
$_SERVER['REMOTE_ADDR'] = '';
\Nino\Auth::loginUser( $appData, 'bucket@example.com', 'wrong' );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
check( 'without a client ip there is no ip bucket at all', array_filter( array_keys( \Nino\Filesystem::getFileContent( $appData, $triesFile, [] ) ), static fn( string $key ): bool => str_starts_with( $key, 'ip:' ) ) === []
	&& $readTries( 'bucket@example.com' ) === 1 );

$seedTries( [ 'bucket@example.com' => null ] );
\Nino\Auth::deleteUser( $appData, 'bucket@example.com' );

// Which accounts are locked out right now, and lifting one lock. Only the
// account buckets are accounts: an ip bucket and a bucket whose account is
// gone are never listed, and a lock that has run out is not one any more
\Nino\Auth::insertUser( $appData, 'locked@example.com', 'correct horse battery staple' );
$seedTries( [ 'ip:127.0.0.1' => null, 'locked@example.com' => null ] );

for( $attempt = 0; $attempt < $appData['/nino/auth/maxtries']; $attempt++ )
	\Nino\Auth::loginUser( $appData, 'locked@example.com', 'wrong' );

$locked = \Nino\Auth::lockedAccounts( $appData );
check( 'an account locked by maxtries failures is listed, until a time in the future', isset( $locked['locked@example.com'] ) === true && $locked['locked@example.com'] > time()
	&& $locked['locked@example.com'] <= time() + $appData['/nino/auth/cooldown'] );
check( '...and the right password no longer logs it in', \Nino\Auth::loginUser( $appData, 'locked@example.com', 'correct horse battery staple' ) === false );

$seedTries( [ 'locked@example.com' => 0 - time() + 5, 'ip:127.0.0.1' => 0 - time() - 3600, 'ghost@example.com' => 0 - time() - 3600 ] );
check( 'a lock that has run out is not listed', isset( \Nino\Auth::lockedAccounts( $appData )['locked@example.com'] ) === false );
check( '...nor is an ip bucket, locked as it is', array_filter( array_keys( \Nino\Auth::lockedAccounts( $appData ) ), static fn( string $key ): bool => str_starts_with( $key, 'ip:' ) ) === [] );
check( '...nor a locked bucket without an account', isset( \Nino\Auth::lockedAccounts( $appData )['ghost@example.com'] ) === false );
check( 'a count that is no lock yet is not listed either', ( function() use ( $seedTries, &$appData ): bool {
	$seedTries( [ 'locked@example.com' => 3 ] );
	return \Nino\Auth::lockedAccounts( $appData ) === [];
} )() === true );

$seedTries( [ 'locked@example.com' => 0 - time() - 3600, 'ghost@example.com' => null ] );
$ipBefore = $readTries( 'ip:127.0.0.1' );
check( 'unlock clears the account\'s bucket', \Nino\Auth::unlock( $appData, 'locked@example.com' ) === true && $readTries( 'locked@example.com' ) === null
	&& isset( \Nino\Auth::lockedAccounts( $appData )['locked@example.com'] ) === false );
check( '...and leaves the locked ip as it was', $readTries( 'ip:127.0.0.1' ) === $ipBefore && $ipBefore < 0 );
$seedTries( [ 'ip:127.0.0.1' => null ] );
check( '...so the right password logs in again', is_array( \Nino\Auth::loginUser( $appData, 'locked@example.com', 'correct horse battery staple' ) ) === true );
check( 'unlock of an account that is not locked is true and changes nothing', \Nino\Auth::unlock( $appData, 'locked@example.com' ) === true );
check( 'unlock of an unknown account is false', \Nino\Auth::unlock( $appData, 'nobody-at-all@example.com' ) === false );

// A tries file that cannot be locked: the lift did not happen and must not say
// it did. A directory where the file's sidecar lock goes is that - the same
// answer a read-only or full disk gives, without needing either
$seedTries( [ 'locked@example.com' => 0 - time() - 3600 ] );
$triesLockKey	= (string) ( new ReflectionMethod( '\Nino\Filesystem', '_canonicalPath' ) )->invokeArgs( null, [ &$appData, $triesFile ] );
$triesLock		= \Nino\Filesystem::path( $appData, '/data' ). '/.locks/'. sha1( $triesLockKey ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $triesLock );
@mkdir( $triesLock );
$blockedUnlock = \Nino\Auth::unlock( $appData, 'locked@example.com' );
@rmdir( $triesLock );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
check( 'unlock is false when the tries file cannot be written', $blockedUnlock === false );
check( '...and the lock is still there afterwards', $readTries( 'locked@example.com' ) < 0 );
$seedTries( [ 'locked@example.com' => null ] );
\Nino\Auth::deleteUser( $appData, 'locked@example.com' );

// An account written by hand. Perms beside a role are written into config.php
// by hand, and status was until the workbench could set it - so a record that is
// a hash and a permission list and nothing else is a thing a project has, and
// reading a key that is not there is a warning this framework treats as
// fatal: a 500 on the login form rather than a refusal
$appData['/nino/auth/user']['handwritten@example.com'] = [ 'pw' => password_hash( 'correct horse battery staple', PASSWORD_DEFAULT ), 'perms' => [ '/*' ] ];
ninoWarnings();
$handLogin = \Nino\Auth::loginUser( $appData, 'handwritten@example.com', 'correct horse battery staple' );
$handWarnings = ninoWarnings();
check( 'a hand-written account without a status is refused, not raised at', $handLogin === false && $handWarnings === [] );

$appData['/nino/auth/user']['handwritten@example.com']['status'] = 2;
ninoWarnings();
$handEnabled = \Nino\Auth::loginUser( $appData, 'handwritten@example.com', 'correct horse battery staple' );
$handWarnings = ninoWarnings();
check( '...and one with a status but no sessions list logs in', is_array( $handEnabled ) === true && $handWarnings === [] );
check( '...with the session it just opened', count( \Nino\Auth::getUser( $appData, 'handwritten@example.com' )['sessions'] ) === 1 );
\Nino\Auth::deleteUser( $appData, 'handwritten@example.com' );

// Disabling an account must end the sessions it already holds. _resumeSession()
// checked only that the token was listed and unexpired, so a disabled account
// stayed fully authorised in every browser holding one - for up to SESSION_TTL
ninoUser( $appData, 'disabled@example.com', [] );

unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( 'a live account is resumed from its session token on the next request', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === 'disabled@example.com' );

$appData['/nino/auth/user']['disabled@example.com']['status'] = 0;
unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( 'a disabled account is not resumed from that same token', \Nino\Auth::getCurrentUser( $appData ) === false );
check( '...and the token is dropped rather than left to age out', \Nino\Auth::getUser( $appData, 'disabled@example.com' )['sessions'] === [] );
\Nino\Auth::deleteUser( $appData, 'disabled@example.com' );

// An account is switched off and on from the Users panel, and says when it
// logged in last - in its own record, since a file of its own would be one
// more thing a project keeps beside data/ (the catalogue's Hello test holds
// data/ to the one file a feature writes)
\Nino\Auth::insertUser( $appData, 'switch@example.com', 'correct horse battery staple' );
check( 'a new account has never logged in', \Nino\Auth::lastLogin( \Nino\Auth::getUser( $appData, 'switch@example.com' ) ) === 0 );

$beforeLogin = time();
\Nino\Auth::loginUser( $appData, 'switch@example.com', 'correct horse battery staple' );
$switchRecord = \Nino\Auth::getUser( $appData, 'switch@example.com' );
check( 'a login stores the time on the account record', is_int( $switchRecord['lastLogin'] ?? null ) === true && $switchRecord['lastLogin'] >= $beforeLogin && $switchRecord['lastLogin'] <= time() );
check( '...which lastLogin() reads', \Nino\Auth::lastLogin( $switchRecord ) === $switchRecord['lastLogin'] );
check( '...in the record, not in a file of its own', array_filter( array_map( 'basename', glob( \Nino\Filesystem::path( $appData, '/data' ). '/*' ) ?: [] ), static fn( string $file ): bool => str_contains( $file, 'login' ) ) === [] );

$legacy = [ 'sessions' => [ 'old' => [ 'time' => 1000, 'ip' => 'x' ], 'newer' => [ 'time' => 5000, 'ip' => 'y' ], 'broken' => 'not a session' ] ];
check( 'lastLogin() falls back to the newest session for a record without the field', \Nino\Auth::lastLogin( $legacy ) === 5000 );
check( '...takes the later of the field and the sessions', \Nino\Auth::lastLogin( $legacy + [ 'lastLogin' => 9000 ] ) === 9000 && \Nino\Auth::lastLogin( $legacy + [ 'lastLogin' => 100 ] ) === 5000 );
check( '...and is 0 for a record with neither, or with junk in their place', \Nino\Auth::lastLogin( [] ) === 0 && \Nino\Auth::lastLogin( [ 'lastLogin' => 'yesterday', 'sessions' => 'none' ] ) === 0 );

$updates = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/auth/user/update', static function( array &$appData, array &$user ) use ( &$updates ): void { $updates[] = $user['mail']. ':'. $user['status']; } );

check( 'setStatus of an unknown account is false', \Nino\Auth::setStatus( $appData, 'nobody-at-all@example.com', false ) === false );
check( 'setStatus(false) disables the account', \Nino\Auth::setStatus( $appData, 'switch@example.com', false ) === true && \Nino\Auth::getUser( $appData, 'switch@example.com' )['status'] === \Nino\Auth::STATUS_DISABLED );
check( '...ends its sessions, the one this request holds included', \Nino\Auth::getUser( $appData, 'switch@example.com' )['sessions'] === [] && \Nino\Auth::getCurrentUser( $appData ) === false );
check( '...and keeps its last login', \Nino\Auth::lastLogin( \Nino\Auth::getUser( $appData, 'switch@example.com' ) ) === $switchRecord['lastLogin'] );
check( '...tells /nino/auth/user/update', $updates === [ 'switch@example.com:0' ] );
check( 'a disabled account is refused at the login', \Nino\Auth::loginUser( $appData, 'switch@example.com', 'correct horse battery staple' ) === false );
check( 'setStatus(true) lets it in again', \Nino\Auth::setStatus( $appData, 'switch@example.com', true ) === true && is_array( \Nino\Auth::loginUser( $appData, 'switch@example.com', 'correct horse battery staple' ) ) === true
	&& $updates === [ 'switch@example.com:0', 'switch@example.com:2' ] );

// A session resumed on a later request is dropped once the account is off
unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( 'a session of the account is resumed while it is active', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === 'switch@example.com' );
\Nino\Auth::setStatus( $appData, 'switch@example.com', false );
unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( '...and not once setStatus(false) has run', \Nino\Auth::getCurrentUser( $appData ) === false );

unset( $appData['/nino/callbacks']['/nino/auth/user/update'] );
\Nino\Auth::deleteUser( $appData, 'switch@example.com' );

echo "\n";


// --- Auth::updateUser / logoutAllSessions ------------------------------------------------------------

echo "Auth::updateUser / logoutAllSessions\n";

\Nino\Auth::insertUser( $appData, 'rename@example.com', 'correct horse battery staple' );
\Nino\Auth::insertUser( $appData, 'taken@example.com', 'whatever' );

check( 'updateUser rejects a mail already taken by another user', \Nino\Auth::updateUser( $appData, 'rename@example.com', 'taken@example.com' ) === false );

$renamed = \Nino\Auth::updateUser( $appData, 'rename@example.com', 'renamed@example.com' );
check( 'updateUser renames the mail (moves the array key)', is_array( $renamed ) === true && $renamed['mail'] === 'renamed@example.com' );
check( 'updateUser removes the old mail key', \Nino\Auth::getUser( $appData, 'rename@example.com' ) === false );
check( 'updateUser is reachable under the new mail', \Nino\Auth::getUser( $appData, 'renamed@example.com' ) !== false );

$oldHash = \Nino\Auth::getUser( $appData, 'renamed@example.com' )['pw'];
\Nino\Auth::updateUser( $appData, 'renamed@example.com', 'renamed@example.com', 'a brand new password' );
$newHash = \Nino\Auth::getUser( $appData, 'renamed@example.com' )['pw'];
check( 'updateUser rotates the password hash when a new password is given', $newHash !== $oldHash && password_verify( 'a brand new password', $newHash ) === true );

\Nino\Auth::updateUser( $appData, 'renamed@example.com', 'renamed@example.com' );
check( 'updateUser keeps the existing hash when no new password is given', \Nino\Auth::getUser( $appData, 'renamed@example.com' )['pw'] === $newHash );

// A self-rename must keep the current session pointed at the (new) right identity
\Nino\Auth::loginUser( $appData, 'renamed@example.com', 'a brand new password' );
\Nino\Auth::updateUser( $appData, 'renamed@example.com', 'stillme@example.com' );
check( 'updateUser keeps the current session logged in as the new mail after a self-rename', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === 'stillme@example.com' );

// Regression: Auth::init() re-hydrates the current user straight from the raw
// stored record on every request, bypassing getUser()'s "add mail back if it's
// missing" fallback - a rename that left the raw record without a mail field
// would only break on the *next* request, not in updateUser()'s own return value
check( 'updateUser keeps a mail field in the raw stored record, not just via getUser()', $appData['/nino/auth/user']['stillme@example.com']['mail'] === 'stillme@example.com' );

unset( $appData['./nino/auth/current'] );
\Nino\Auth::init( $appData );
check( 'a fresh request (Auth::init) still resolves the current user\'s mail after a self-rename', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === 'stillme@example.com' );

// logoutAllSessions
\Nino\Auth::insertUser( $appData, 'multisession@example.com', 'correct horse battery staple' );
$appData['/nino/auth/user']['multisession@example.com']['sessions'] = [ '127.0.0.1' => time(), '10.0.0.1' => time() ];
check( 'logoutAllSessions clears every session entry', \Nino\Auth::logoutAllSessions( $appData, 'multisession@example.com' ) === true && \Nino\Auth::getUser( $appData, 'multisession@example.com' )['sessions'] === [] );

\Nino\Auth::loginUser( $appData, 'multisession@example.com', 'correct horse battery staple' );
check( 'logoutAllSessions on the current user also ends the current session', \Nino\Auth::logoutAllSessions( $appData, 'multisession@example.com' ) === true && \Nino\Auth::getCurrentUser( $appData ) === false );

// checkPermission
\Nino\Auth::insertUser( $appData, 'perms@example.com', 'correct horse battery staple', [ '/section/*' ] );
check( 'checkPermission grants a wildcard-covered permission', \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'perms@example.com' ) === true );
check( 'checkPermission denies one outside the wildcard', \Nino\Auth::checkPermission( $appData, '/other/thing/manage', 'perms@example.com' ) === false );
check( 'a permission without a slash is denied, not a fatal', \Nino\Auth::checkPermission( $appData, 'manage', 'perms@example.com' ) === false );

// A non-string truthy value in perms (a config typo) must not loosely match
// every permission there is
$appData['/nino/auth/user']['perms@example.com']['perms'] = [ true ];
check( 'a truthy non-string in perms grants nothing', \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'perms@example.com' ) === false );

\Nino\Auth::deleteUser( $appData, 'perms@example.com' );

// Roles: a named set of permissions under '/nino/auth/roles' an account
// holds one of - what it may do is its own permissions plus the role's
$appData['/nino/auth/roles'] = [ 'section' => [ 'label' => 'Section', 'perms' => [ '/section/*', 42 ] ] ];
check( 'insertUser refuses a role the config does not have', \Nino\Auth::insertUser( $appData, 'role@example.com', 'correct horse battery staple', [], 'nope' ) === false && \Nino\Auth::getUser( $appData, 'role@example.com' ) === false );
check( 'insertUser stores a known role', \Nino\Auth::insertUser( $appData, 'role@example.com', 'correct horse battery staple', [ '/own/thing' ], 'section' ) === true && \Nino\Auth::getUser( $appData, 'role@example.com' )['role'] === 'section' );
check( 'permissions() is the account\'s own plus the role\'s, strings only', \Nino\Auth::permissions( $appData, \Nino\Auth::getUser( $appData, 'role@example.com' ) ) === [ '/own/thing', '/section/*' ] );
check( 'checkPermission grants through the role', \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'role@example.com' ) === true );
check( 'setRole refuses an unknown role and an unknown account', \Nino\Auth::setRole( $appData, 'role@example.com', 'nope' ) === false && \Nino\Auth::setRole( $appData, 'nobody@example.com', 'section' ) === false && \Nino\Auth::getUser( $appData, 'role@example.com' )['role'] === 'section' );
check( 'setRole to none takes the role\'s permissions away, the own ones stay', \Nino\Auth::setRole( $appData, 'role@example.com', '' ) === true && \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'role@example.com' ) === false && \Nino\Auth::checkPermission( $appData, '/own/thing', 'role@example.com' ) === true );
\Nino\Auth::setRole( $appData, 'role@example.com', 'section' );
check( 'the role is persisted with the account', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/user']['role@example.com']['role'] === 'section' );
unset( $appData['/nino/auth/roles']['section'] );
check( 'a role the config no longer has grants nothing', \Nino\Auth::checkPermission( $appData, '/section/thing/manage', 'role@example.com' ) === false );
$appData['/nino/auth/roles']['section'] = [ 'label' => 'Section', 'perms' => [ '/section/*' ] ];
\Nino\Auth::loginUser( $appData, 'role@example.com', 'correct horse battery staple' );
check( 'the signed-in account holds its role\'s permissions', \Nino\Auth::checkPermission( $appData, '/section/thing/manage' ) === true );
\Nino\Auth::setRole( $appData, 'role@example.com', '' );
check( 'a role changed on the signed-in account applies to the very request that changed it', \Nino\Auth::checkPermission( $appData, '/section/thing/manage' ) === false );
\Nino\Auth::logoutUser( $appData );
\Nino\Auth::deleteUser( $appData, 'role@example.com' );
$appData['/nino/auth/roles'] = [];

echo "\n";


// --- Csrf ------------------------------------------------------------

echo "Csrf::getToken / rotateToken / callbackResponse (kernel, required) / Modules\\Csrf::doShortcode (optional)\n";

$token1 = \Nino\Csrf::getToken( $appData );
check( 'getToken creates a token', is_string( $token1 ) === true && strlen( $token1 ) > 0 );
check( 'getToken returns the same token on a second call', \Nino\Csrf::getToken( $appData ) === $token1 );

\Nino\Csrf::rotateToken( $appData );
$token2 = \Nino\Csrf::getToken( $appData );
check( 'rotateToken replaces the token', $token2 !== $token1 );

$_POST['_csrf'] = 'wrong-token';
$fakeRequest = [ '/nino/http/request' => [ 'method' => 'POST' ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Csrf::callbackResponse( $appData, $fakeRequest );
check( 'callbackResponse rejects a wrong token', $fakeRequest['/nino/http/response']['statusCode'] === 403 );
check( 'callbackResponse sets the blocked flag on rejection', ( $fakeRequest['./nino/csrf/blocked'] ?? false ) === true );

$_POST['_csrf'] = $token2;
$fakeRequest = [ '/nino/http/request' => [ 'method' => 'POST' ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Csrf::callbackResponse( $appData, $fakeRequest );
check( 'callbackResponse accepts the current token', $fakeRequest['/nino/http/response']['statusCode'] === 200 );

check( 'Modules\Csrf::doShortcode renders a hidden input with the current token, reading the kernel token', str_contains( \Nino\Modules\Csrf::doShortcode( $appData, [] ), 'value="'. $token2. '"' ) === true );

// Same as the [image] fragment in images-smoke.php: the hidden input is a property, so a
// project that needs a different field name sets the entry rather than
// patching the module
$shippedCsrf = \Nino\Modules\Csrf::$html['input'];
\Nino\Modules\Csrf::$html['input'] = '<input type="hidden" name="authenticity_token" value="[[token]]">';
$replacedCsrf = \Nino\Modules\Csrf::doShortcode( $appData, [] );
\Nino\Modules\Csrf::$html['input'] = $shippedCsrf;
check( 'the hidden input is the property, so replacing it replaces what the shortcode renders', $replacedCsrf === '<input type="hidden" name="authenticity_token" value="'. $token2. '">' );
check( '...and the shipped fragment names the field the kernel checks', str_contains( \Nino\Modules\Csrf::doShortcode( $appData, [] ), 'name="_csrf"' ) === true );


/*	Everything above drives one path: a POST carrying the token in
	$_POST['_csrf']. The guard has four more, every one of them written for a
	failure that had already happened once, and none of them measured:

	- which methods are checked. It used to be POST alone, so a route
	  registered for PUT, DELETE or PATCH was unprotected, and a method the
	  kernel does not recognize ('') stays on the checked side deliberately.
	- the X-CSRF-Token header.
	- the token in a json body - $_POST is empty for a json request, so the
	  field alone 403s every json POST whatever token it carries.
	- the per-route opt-out, and the one thing it must not do: a POST to an
	  address no route is registered for resolves to the /404 route, and a
	  'csrf' => false there would wave through every POST to every
	  unregistered uri site-wide.

	One helper, because each of these is the same call with one thing
	different	*/
function csrfCheck( array &$appData, string $method, string $uri = '/api/thing', array $post = [], array $header = [], string $body = '' ): array {
	$_POST = $post;
	$request = [
		'/nino/http/request'	=> [ 'method' => $method, 'uri' => $uri, 'header' => $header, 'body' => $body ],
		'/nino/http/response'	=> [ 'statusCode' => 200 ],
	];
	\Nino\Csrf::callbackResponse( $appData, $request );
	return $request;
}

/** Whether one such request was let through */
function csrfPassed( array $request ): bool {
	return $request['/nino/http/response']['statusCode'] === 200 && ( $request['./nino/csrf/blocked'] ?? false ) === false;
}

$csrfRoutesBefore = $appData['/nino/http/routes'] ?? null;
$appData['/nino/http/routes'] = [
	'POST://api/thing'	=> [ 'uri' => '/api/thing' ],
	'POST://webhook'		=> [ 'uri' => '/webhook', 'csrf' => false ],
	'POST://hooks/*'		=> [ 'uri' => '/hooks', 'csrf' => false ],
	'POST://plain'			=> [ 'uri' => '/plain', 'csrf' => false ],
	// The trap: a 404 page is GET-only and may reasonably say it needs no token
	'GET://404'					=> [ 'uri' => '/404', 'csrf' => false ],
];

// A safe method carries no token and needs none
check( 'GET, HEAD and OPTIONS pass without a token', csrfPassed( csrfCheck( $appData, 'GET' ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'HEAD' ) ) === true && csrfPassed( csrfCheck( $appData, 'OPTIONS' ) ) === true );

// Every method that writes is checked, not POST alone
check( 'POST, PUT, DELETE and PATCH without a token are all refused', csrfPassed( csrfCheck( $appData, 'POST' ) ) === false
	&& csrfPassed( csrfCheck( $appData, 'PUT' ) ) === false
	&& csrfPassed( csrfCheck( $appData, 'DELETE' ) ) === false
	&& csrfPassed( csrfCheck( $appData, 'PATCH' ) ) === false );
check( '...and all four pass with the current token', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => $token2 ] ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'PUT', '/api/thing', [ '_csrf' => $token2 ] ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'DELETE', '/api/thing', [ '_csrf' => $token2 ] ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'PATCH', '/api/thing', [ '_csrf' => $token2 ] ) ) === true );

// A method the kernel does not recognize is '' by the time this runs, and it
// has no business writing anything either
check( 'a method the kernel does not recognize is on the checked side', csrfPassed( csrfCheck( $appData, '' ) ) === false );

// The header, which is the only source a cross-site form cannot set
check( 'the token is taken from X-CSRF-Token', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [ 'X-CSRF-Token' => $token2 ] ) ) === true );
check( '...and a wrong one there is refused like any other', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [ 'X-CSRF-Token' => 'nope' ] ) ) === false );

/*	_extractToken() reads that header off the already normalized request
	header rather than $_SERVER, and says so: it is "the one every other
	header read in the kernel already goes through". Every check above hands
	callbackResponse() a header array built by hand, so all of them stay
	green whether or not the kernel actually carries the header that far -
	Http::filterHeaderFields() keeps an allowlist, and a name missing from it
	is dropped in silence, which would leave the whole documented path dead
	with nothing to say so. Driven from $_SERVER through Http::request(), the
	way a real request arrives	*/
function csrfCheckRaw( array &$appData, string $header ): array {

	$_POST = [];
	$request = [
		'REQUEST_METHOD'		=> 'POST',
		'REQUEST_URI'				=> '/api/thing',
		'REMOTE_ADDR'				=> '127.0.0.1',
		'HTTP_X_CSRF_TOKEN'	=> $header,
	];

	\Nino\Http::request( $appData, $request );
	\Nino\Csrf::callbackResponse( $appData, $request );

	return $request;
}

check( 'a client\'s X-CSRF-Token header survives normalization and reaches the guard', csrfPassed( csrfCheckRaw( $appData, $token2 ) ) === true );
check( '...and a wrong one sent that way is refused, so the header is read rather than trusted', csrfPassed( csrfCheckRaw( $appData, 'nope' ) ) === false );

// The json body: $_POST is empty for one of these, so without this path every
// json POST was a 403 whatever token it carried
check( 'the token is taken from a json body', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], (string) json_encode( [ '_csrf' => $token2 ] ) ) ) === true );
check( '...and a wrong one there is refused', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], (string) json_encode( [ '_csrf' => 'nope' ] ) ) ) === false );
check( '...and a body that is no json at all is not a token', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], 'not json' ) ) === false );

// Anything a client can send that is not a string is not a token. '_csrf[]=x'
// parses to an array, and json carries types of its own
check( 'a _csrf field that is an array is no token', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => [ $token2 ] ] ) ) === false );
check( '...nor is a json _csrf that is an array', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], (string) json_encode( [ '_csrf' => [ $token2 ] ] ) ) ) === false );
check( '...nor a json body that is a list rather than an object', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [], [], (string) json_encode( [ $token2 ] ) ) ) === false );

// ...and an empty field falls through to the next source rather than counting
// as an answer, while a wrong one is the answer
check( 'an empty _csrf field falls through to the header', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => '' ], [ 'X-CSRF-Token' => $token2 ] ) ) === true );
check( '...and a wrong one does not', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => 'nope' ], [ 'X-CSRF-Token' => $token2 ] ) ) === false );

// hash_equals(), so neither end of the token is enough
check( 'a prefix of the token is refused', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => substr( $token2, 0, 20 ) ] ) ) === false );
check( '...and so is the token with anything appended', csrfPassed( csrfCheck( $appData, 'POST', '/api/thing', [ '_csrf' => $token2. 'x' ] ) ) === false );

// The per-route opt-out a public endpoint needs
check( "a route's own 'csrf' => false lets it through", csrfPassed( csrfCheck( $appData, 'POST', '/webhook' ) ) === true );
check( '...for the method it is registered for, and no other', csrfPassed( csrfCheck( $appData, 'PUT', '/webhook' ) ) === false );

/*	And the thing it must not do. A POST to an address no route is registered
	for is looked up here on its own uri and method, which correctly finds
	nothing - not the /404 route Http::response() has already merged into the
	response array, looked up with method GET. Reading that merged value
	instead would let one 'csrf' => false on the 404 page wave through every
	POST to every unregistered uri on the site	*/
check( 'a POST to an unregistered uri does not inherit the 404 page\'s opt-out', csrfPassed( csrfCheck( $appData, 'POST', '/nowhere' ) ) === false );

// A wildcard route opts out what it declares: its children, not itself, and
// a plain parent route opts out nothing but itself
check( 'a wildcard opt-out covers the children it declares', csrfPassed( csrfCheck( $appData, 'POST', '/hooks/stripe' ) ) === true
	&& csrfPassed( csrfCheck( $appData, 'POST', '/hooks/stripe/v2' ) ) === true );
check( '...and not the address it is written under', csrfPassed( csrfCheck( $appData, 'POST', '/hooks' ) ) === false );
check( 'a plain route\'s opt-out reaches no child of it', csrfPassed( csrfCheck( $appData, 'POST', '/plain/child' ) ) === false );

// What a refusal leaves behind: the status, the flag every later callback has
// to read for itself, and a body that is not a handler's own
$csrfRefused = csrfCheck( $appData, 'POST', '/api/thing' );
check( 'a refusal is a 403 with the blocked flag and no body', $csrfRefused['/nino/http/response']['statusCode'] === 403
	&& ( $csrfRefused['./nino/csrf/blocked'] ?? false ) === true
	&& $csrfRefused['/nino/http/response']['body'] === false );

$_POST = [];
if( $csrfRoutesBefore === null )
	unset( $appData['/nino/http/routes'] );
else
	$appData['/nino/http/routes'] = $csrfRoutesBefore;

// Regression: Auth::callbackLoginResponse()/callbackLogoutResponse() used
// to hard-refuse (trigger_error(E_USER_ERROR)) unless the Csrf module was
// listed in '/nino/modules' - protection is now the required \Nino\Csrf
// kernel class instead, always active, so that refusal is gone. This
// sandbox's $appData never lists Csrf under '/nino/modules' anywhere in
// this file, which used to make every login/logout POST here
// a 500 - the two callbacks cooperating correctly via the shared
// './nino/csrf/blocked' flag, with no module in the loop at all, is
// exactly the property this split is for.
$csrfUser = 'csrfpipeline@example.com';
\Nino\Auth::insertUser( $appData, $csrfUser, 'correct horse battery staple' );

// Wrong token - same order the real pipeline runs in: Csrf at priority 1
// sets the blocked flag before Auth's own route callback ever sees it
$_POST['_csrf'] = 'not-the-real-token';
$badLoginRequest = [
	'/nino/http/request' 	=> [ 'method' => 'POST', 'user' => $csrfUser, 'pw' => 'correct horse battery staple' ],
	'/nino/http/response'	=> [ 'statusCode' => 200 ],
];
\Nino\Csrf::callbackResponse( $appData, $badLoginRequest );
\Nino\Auth::callbackLoginResponse( $appData, $badLoginRequest );

check( 'callbackLoginResponse still respects a Csrf rejection (403, not the 401/200 it sets itself)', $badLoginRequest['/nino/http/response']['statusCode'] === 403 );
check( 'a Csrf-blocked login request never reaches loginUser()', \Nino\Auth::getCurrentUser( $appData ) === false );

// Valid token - the full pipeline succeeds end-to-end, still with no
// module involved anywhere. Use the same Authorization header Nino.js sends
// instead of injecting normalized user/pw values: CGI/FastCGI often exposes
// only HTTP_AUTHORIZATION, which used to leave both values empty and make
// this route answer 401 for every correct login.
$_POST['_csrf'] = \Nino\Csrf::getToken( $appData );
$goodLoginRequest = [
	'REQUEST_METHOD' 			=> 'POST',
	'REQUEST_URI' 				=> '/.nino/auth/login',
	'REMOTE_ADDR' 				=> '127.0.0.1',
	'HTTP_AUTHORIZATION'	=> 'Basic '. base64_encode( $csrfUser. ':correct horse battery staple' ),
];
\Nino\Http::request( $appData, $goodLoginRequest );
\Nino\Csrf::callbackResponse( $appData, $goodLoginRequest );
\Nino\Auth::callbackLoginResponse( $appData, $goodLoginRequest );

check( 'callbackLoginResponse succeeds end-to-end with a Basic header and valid token', $goodLoginRequest['/nino/http/response']['statusCode'] === 200 );
check( 'and actually logs the user in', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === $csrfUser );

/*	...and a refused attempt while that session is still live. The answer
	used to be decided by getCurrentUser(), which answers for the session as
	a whole: loginUser() leaves a resumed session alone when it refuses, so a
	wrong password posted from such a tab was answered 200/true while the
	attempt itself was counted as failed. \Nino.js takes any 200 for a login
	and redirects, so that tab walked into the workbench as the identity it
	already had	*/
$_POST['_csrf'] = \Nino\Csrf::getToken( $appData );
$wrongWhileLoggedIn = [
	'REQUEST_METHOD' 			=> 'POST',
	'REQUEST_URI' 				=> '/.nino/auth/login',
	'REMOTE_ADDR' 				=> '127.0.0.1',
	'HTTP_AUTHORIZATION'	=> 'Basic '. base64_encode( 'nobody@example.com:WRONG' ),
];
\Nino\Http::request( $appData, $wrongWhileLoggedIn );
\Nino\Csrf::callbackResponse( $appData, $wrongWhileLoggedIn );
\Nino\Auth::callbackLoginResponse( $appData, $wrongWhileLoggedIn );

check( 'a refused login answers 401 even while another session is live', $wrongWhileLoggedIn['/nino/http/response']['statusCode'] === 401
	&& $wrongWhileLoggedIn['/nino/http/response']['body'] === false );
check( '...and the session that was live is still the one that is', ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? null ) === $csrfUser );

\Nino\Auth::logoutUser( $appData );
\Nino\Auth::deleteUser( $appData, $csrfUser );
unset( $_POST['_csrf'] );

echo "\n";

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
