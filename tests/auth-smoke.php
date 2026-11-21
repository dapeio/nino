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

// The decoy that an account that is none is verified against: kept for the installation, made by the php that runs, with the cost of the hashes it stores
$login = ninoCalls( (string) file_get_contents( __DIR__. '/../_nino/Nino/Auth/Auth.php' ), 'loginUser' );
$decoyFile = \Nino\Filesystem::path( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/decoy.json' );
\Nino\Auth::loginUser( $appData, 'unknown-for-the-decoy@example.com', 'wrong' );
$decoy = (string) ( \Nino\Filesystem::getFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/decoy.json', [] )['hash'] ?? '' );
$made = password_get_info( \Nino\Auth::getUser( $appData, 'test@example.com' )['pw'] );
check( 'the first attempt of an account that is none keeps a decoy in the private directory: the hash of the algorithm and the cost this php hashes with, those of the hash a new account is stored with',
	is_file( $decoyFile ) === true && $decoy !== '' && password_needs_rehash( $decoy, PASSWORD_DEFAULT ) === false && password_get_info( $decoy )['algo'] === $made['algo'] && ( password_get_info( $decoy )['options']['cost'] ?? 0 ) === ( $made['options']['cost'] ?? 1 ) );
check( '...it is made once: the next attempts find it', \Nino\Auth::decoyHash( $appData ) === $decoy );
check( '...loginUser() asks for it once, before the one comparison - read as php reads the source, whatever its form - and the only password_hash() of it comes after the password was proven; no other way of comparing a secret stands in it',
	count( array_keys( $login, 'decoyHash', true ) ) === 1 && count( array_keys( $login, 'password_verify', true ) ) === 1 && array_search( 'decoyHash', $login, true ) < array_search( 'password_verify', $login, true )
	&& count( array_keys( $login, 'password_hash', true ) ) === 1 && array_search( 'password_hash', $login, true ) > array_search( '_clearTries', $login, true ) && array_intersect( $login, [ 'crypt', 'hash_equals', 'md5', 'sha1', 'strcmp' ] ) === [] );

// ...and on every way, which is what the behaviour says: the account that exists is asked as the one that does not, so each of them makes the decoy where it is not there
foreach( [ 'a known account with a wrong password' => [ 'test@example.com', 'a wrong password' ], 'an account that is none' => [ 'none-at-all@example.com', 'a wrong password' ] ] as $way => [ $who, $what ] ) {

	@unlink( $decoyFile );
	unset( $appData['./nino/filesystem/cache'][ \Nino\Filesystem::CONTENT_DIR. '/.auth/decoy.json' ] );
	clearstatcache();
	$appData['/nino/auth/maxtries'] = 50;
	\Nino\Auth::loginUser( $appData, $who, $what );
	check( 'the attempt of '. $way. ' finds no decoy and makes it: the decoy is asked on this way as on the others', is_file( $decoyFile ) === true );
}

$appData['/nino/auth/maxtries'] = 3;

// A decoy whose secret is known - somebody who can write the file wrote it - lets nobody in: not as an account that is none, not as one that is switched off
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/decoy.json', [ 'hash' => password_hash( 'the known secret', PASSWORD_DEFAULT ) ] );
\Nino\Auth::insertUser( $appData, 'off-for-the-decoy@example.com', 'another secret' );
\Nino\Auth::setStatus( $appData, 'off-for-the-decoy@example.com', false );
$sessionBefore = json_encode( \Nino\Auth::getCurrentUser( $appData ) );
check( 'the decoy lets nobody in, whatever its secret is: an account that is none and one that is switched off are refused with it, and no session is opened',
	\Nino\Auth::loginUser( $appData, 'none-at-all@example.com', 'the known secret' ) === false && \Nino\Auth::loginUser( $appData, 'off-for-the-decoy@example.com', 'the known secret' ) === false && json_encode( \Nino\Auth::getCurrentUser( $appData ) ) === $sessionBefore );
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/decoy.json', [ 'hash' => password_hash( 'x', PASSWORD_BCRYPT, [ 'cost' => 4 ] ) ] );
\Nino\Auth::loginUser( $appData, 'unknown-for-the-decoy@example.com', 'wrong' );
$remade = (string) ( \Nino\Filesystem::getFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/decoy.json', [] )['hash'] ?? '' );
check( 'a decoy of another cost - the php was changed - is made again by the attempt that finds it, at the cost this php hashes with, and kept', $remade !== '' && password_needs_rehash( $remade, PASSWORD_DEFAULT ) === false );

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
// registration is exercised directly (invokeArgs() with a reference, since a
// plain invoke() would run against a copy of $appData and never see this
// test's own prior writes)
$register			= new ReflectionMethod( '\Nino\Auth', '_registerFailedAttemp' );
// A counter is [ 'tries' => n, 'reset' => time ], a lock the negative time it ends: the number a bucket stands at
$countOf			= static fn( mixed $entry ): int => is_array( $entry ) === true ? (int) $entry['tries'] : (int) $entry;
$triesBefore	= \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] );
$register->invokeArgs( null, [ &$appData, [ 'ip:127.0.0.1', 'test@example.com' ] ] );
$triesAfter		= \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] );
check( 'a failed attempt registered against a bucket a parallel request has just locked leaves the lock as it is', $triesBefore['test@example.com'] < 0 && $triesAfter['test@example.com'] === $triesBefore['test@example.com'] );
check( '...and still counts the other buckets of the same attempt', $countOf( $triesAfter['ip:127.0.0.1'] ) === $countOf( $triesBefore['ip:127.0.0.1'] ) + 1 );
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
$readTries	= static function( string $key ) use ( &$appData, $triesFile, $countOf ): ?int {
	$entry = \Nino\Filesystem::getFileContent( $appData, $triesFile, [] )[$key] ?? null;
	return $entry === null ? null : $countOf( $entry );
};

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

// The ip bucket is the address as the counters take it: the /64 of an IPv6
// client, so that guessing from its other addresses is one client's guessing
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:1::1';
$seedTries( [ 'ip:127.0.0.1' => null, 'bucket@example.com' => null, 'ip:'. \Nino\Http::clientBucket( '2001:db8:1:1::1' ) => $appData['/nino/auth/maxtries'] * $ipFactor - 1 ] );
\Nino\Auth::loginUser( $appData, 'nobody@example.com', 'wrong' );
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:1::2';
$foldedLogin = \Nino\Auth::loginUser( $appData, 'bucket@example.com', 'correct horse battery staple' );
check( 'a guess from another address of the /64 meets the cooldown the first one tripped', $readTries( 'ip:'. \Nino\Http::clientBucket( '2001:db8:1:1::1' ) ) < 0 && $foldedLogin === false );
check( '...and the bucket holds no whole IPv6 address', array_filter( array_keys( \Nino\Filesystem::getFileContent( $appData, $triesFile, [] ) ), static fn( string $key ): bool => str_starts_with( $key, 'ip:2001:db8:1:1::' ) && str_ends_with( $key, '/64' ) === false ) === [] );
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2::1';
$otherLogin = \Nino\Auth::loginUser( $appData, 'bucket@example.com', 'correct horse battery staple' );
check( 'another /64 is not in cooldown', $otherLogin !== false );
check( 'the address a session is listed under is the whole address, not its /64', in_array( '2001:db8:1:2::1', array_column( \Nino\Auth::getUser( $appData, 'bucket@example.com' )['sessions'], 'ip' ), true ) === true );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$seedTries( [ 'ip:'. \Nino\Http::clientBucket( '2001:db8:1:1::1' ) => null, 'ip:'. \Nino\Http::clientBucket( '2001:db8:1:2::1' ) => null, 'ip:127.0.0.1' => null, 'bucket@example.com' => null ] );

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

/*	What a stream of failed attempts leaves in the file. An ip costs an
	attacker nothing, so every wrong guess from a new address was an entry
	that nothing ever removed: a counter below the threshold had no end, and
	the number of entries had no bound. A counter now ends one cooldown after
	the attempt that wrote it, what is over goes with the next write, and the
	file keeps a fixed number of entries - the oldest counters leave first, and
	a lock never does, since a flood that pushed a lock out would be a way to
	lift it	*/
$triesCap			= (int) ( new ReflectionClassConstant( '\Nino\Auth', 'TRIES_MAX_ENTRIES' ) )->getValue();
$cooldown			= (int) $appData['/nino/auth/cooldown'];
$fileHolds		= static fn(): array => \Nino\Filesystem::getFileContent( $appData, $triesFile, [] );
$failFrom			= static function( string $key ) use ( $register, &$appData ): void { $register->invokeArgs( null, [ &$appData, [ $key ] ] ); };
$seedTries( array_fill_keys( array_keys( $fileHolds() ), null ) );

$failFrom( 'ip:203.0.113.1' );
$written = $fileHolds()['ip:203.0.113.1'] ?? null;
check( 'a counter written by a failed attempt has an end, one cooldown away', is_array( $written ) === true && $written['tries'] === 1
	&& $written['reset'] > time() && $written['reset'] <= time() + $cooldown );

$seedTries( [ 'ip:203.0.113.2' => [ 'tries' => 3, 'reset' => time() - 1 ], 'ip:203.0.113.3' => [ 'tries' => 3, 'reset' => time() + 600 ] ] );
$failFrom( 'ip:203.0.113.4' );
check( 'a counter whose window is over is dropped by the next write, one still running is kept', isset( $fileHolds()['ip:203.0.113.2'] ) === false
	&& $countOf( $fileHolds()['ip:203.0.113.3'] ?? 0 ) === 3 );

$seedTries( [ 'ip:203.0.113.5' => [ 'tries' => 2, 'reset' => time() + 600 ], 'ip:203.0.113.12' => [ 'tries' => 1, 'reset' => time() + 600 ] ] );
$failFrom( 'ip:203.0.113.5' );
check( 'another attempt against a counter counts on from it and moves its end', $countOf( $fileHolds()['ip:203.0.113.5'] ) === 3 && $fileHolds()['ip:203.0.113.5']['reset'] > time() + 600 );
check( '...and the file is in the order of the last attempt, the counter just touched at its end', array_key_last( $fileHolds() ) === 'ip:203.0.113.5' );

// What an earlier version wrote: a plain count that never ended
$seedTries( [ 'ip:203.0.113.6' => 4, 'legacy@example.com' => $appData['/nino/auth/maxtries'] - 1 ] );
$failFrom( 'ip:203.0.113.7' );
$stamped = $fileHolds()['ip:203.0.113.6'] ?? null;
check( 'a counter without an end gets one with the next write, and keeps its count', is_array( $stamped ) === true && $stamped['tries'] === 4
	&& $stamped['reset'] > time() && $stamped['reset'] <= time() + $cooldown );
$failFrom( 'legacy@example.com' );
check( 'one that was one try short of the lock is locked by the next attempt, as it was', ( $fileHolds()['legacy@example.com'] ?? 0 ) < 0 );

// The same entries, left alone for a cooldown: gone
$seedTries( [ 'ip:203.0.113.6' => [ 'tries' => 4, 'reset' => time() - 1 ] ] );
$failFrom( 'ip:203.0.113.8' );
check( '...and once that end has passed the counter is dropped like any other', isset( $fileHolds()['ip:203.0.113.6'] ) === false );

// A lock that has run out goes with the same write; one running stays
$seedTries( [ 'ip:203.0.113.9' => 0 - time() + 5, 'ip:203.0.113.10' => 0 - time() - 600 ] );
$failFrom( 'ip:203.0.113.11' );
check( 'a lock that has run out is dropped, one that has not is kept', isset( $fileHolds()['ip:203.0.113.9'] ) === false && ( $fileHolds()['ip:203.0.113.10'] ?? 0 ) < 0 );

// The flood. A running lock on an account and one on an ip are written into
// a file that is full of counters, then more failures arrive from addresses
// nobody has seen
\Nino\Auth::insertUser( $appData, 'flooded@example.com', 'correct horse battery staple' );
$seed = [ 'flooded@example.com' => 0 - time() - 3000, 'ip:127.0.0.1' => 0 - time() - 3000 ];

for( $counter = 0; $counter < $triesCap + 40; $counter++ )
	$seed[ 'ip:198.18.'. intdiv( $counter, 250 ). '.'. ( $counter % 250 ) ] = [ 'tries' => 1, 'reset' => time() + 600 ];

\Nino\Filesystem::putFileContent( $appData, $triesFile, $seed );
$_SERVER['REMOTE_ADDR'] = '203.0.113.200';

for( $flood = 1; $flood <= 60; $flood++ )
	$failFrom( 'ip:100.64.0.'. $flood );

$afterFlood = $fileHolds();
check( 'a flood of counters from new addresses leaves the file at the number it keeps', count( $afterFlood ) === $triesCap );
check( '...the oldest counters went, the newest are there', isset( $afterFlood['ip:198.18.0.0'] ) === false && isset( $afterFlood['ip:100.64.0.60'] ) === true );
check( '...and the running locks are still there, so a flood lifts none', ( $afterFlood['flooded@example.com'] ?? 0 ) < 0 && ( $afterFlood['ip:127.0.0.1'] ?? 0 ) < 0 );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
check( '...which is what holds: the right password is still refused', \Nino\Auth::loginUser( $appData, 'flooded@example.com', 'correct horse battery staple' ) === false );
check( '...and the locked account is still listed as locked', isset( \Nino\Auth::lockedAccounts( $appData )['flooded@example.com'] ) === true );

// Even when locks are all there is room for: they stay, the counters go
$seed = [];

for( $lockNo = 0; $lockNo < $triesCap + 5; $lockNo++ )
	$seed[ 'ip:192.0.2.'. intdiv( $lockNo, 250 ). '.'. ( $lockNo % 250 ) ] = 0 - time() - 3000;

\Nino\Filesystem::putFileContent( $appData, $triesFile, $seed );
$failFrom( 'ip:100.64.1.1' );
$onlyLocks = $fileHolds();
check( 'more running locks than the file keeps are all kept - only counters are ever pushed out', count( $onlyLocks ) === $triesCap + 5 && isset( $onlyLocks['ip:100.64.1.1'] ) === false );

$seedTries( array_fill_keys( array_keys( $fileHolds() ), null ) );
\Nino\Auth::deleteUser( $appData, 'flooded@example.com' );

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


// --- Auth::confirmPassword - the password an account gives to change its own account -------------------

echo "\nAuth::confirmPassword\n";

\Nino\Auth::insertUser( $appData, 'own@example.com', 'correct horse battery staple' );
\Nino\Auth::insertUser( $appData, 'neighbour@example.com', 'another correct horse' );
$max = (int) $appData['/nino/auth/maxtries'];
$bucket = 'confirm:own@example.com';
$seedTries( [ 'own@example.com' => null, $bucket => null, 'neighbour@example.com' => null, 'confirm:neighbour@example.com' => null, 'ip:127.0.0.1' => null ] );

check( 'the right password is confirmed, and counts nothing', \Nino\Auth::confirmPassword( $appData, 'own@example.com', 'correct horse battery staple' ) === true && $readTries( $bucket ) === null );
check( 'a wrong password is refused and counts as a failed attempt of the confirmation, in a bucket of its own beside the counters of the sign-in', \Nino\Auth::confirmPassword( $appData, 'own@example.com', 'wrong' ) === false && $readTries( $bucket ) === 1 && $readTries( 'own@example.com' ) === null );
check( '...and not against the ip: a session that is signed in is no guess of the ip', $readTries( 'ip:127.0.0.1' ) === null );
\Nino\Auth::confirmPassword( $appData, 'own@example.com', 'correct horse battery staple' );
check( 'a right password before the limit clears the bucket of the confirmation', $readTries( $bucket ) === null );

// Failed sign-ins from outside, with no session, lock the sign-in of the account and not the confirmation: nobody who is not signed in keeps the owner from changing the password
for( $guess = 0; $guess < $max; $guess++ )
	\Nino\Auth::loginUser( $appData, 'own@example.com', 'a stranger\'s guess '. $guess );
check( 'maxtries failed sign-ins lock the sign-in of the account', $readTries( 'own@example.com' ) < 0 && \Nino\Auth::loginUser( $appData, 'own@example.com', 'correct horse battery staple' ) === false );
check( '...and the confirmation of its password goes on: the right password is confirmed, and the bucket of the sign-in stays as it is', \Nino\Auth::confirmPassword( $appData, 'own@example.com', 'correct horse battery staple' ) === true && $readTries( 'own@example.com' ) < 0 && $readTries( $bucket ) === null );
$seedTries( [ 'own@example.com' => null, 'ip:127.0.0.1' => null ] );

$sessionBefore = json_encode( \Nino\Auth::getCurrentUser( $appData ) );
for( $guess = 0; $guess < $max; $guess++ )
	\Nino\Auth::confirmPassword( $appData, 'own@example.com', 'wrong '. $guess );
check( 'maxtries wrong confirmations lock the confirmation: the cooldown is in the bucket of the confirmation', $readTries( $bucket ) < 0 );
check( '...then the right password is refused as well', \Nino\Auth::confirmPassword( $appData, 'own@example.com', 'correct horse battery staple' ) === false );
$locked = $readTries( $bucket );
for( $guess = 0; $guess < $max + 2; $guess++ )
	\Nino\Auth::confirmPassword( $appData, 'own@example.com', 'more '. $guess );
check( '...and nothing is counted while it is locked: more guesses, wrong or right, leave the end of the lock where it was, so it cannot be made longer', $readTries( $bucket ) === $locked && $readTries( $bucket ) < 0 );
check( '...the sign-in of the account is not locked by it: the owner signs in with the right password, and the session that was there stays until then', json_encode( \Nino\Auth::getCurrentUser( $appData ) ) === $sessionBefore && $readTries( 'own@example.com' ) === null && is_array( \Nino\Auth::loginUser( $appData, 'own@example.com', 'correct horse battery staple' ) ) === true );
\Nino\Auth::logoutUser( $appData );
check( 'another account is not touched: its buckets are as they were and its password is confirmed', $readTries( 'neighbour@example.com' ) === null && $readTries( 'confirm:neighbour@example.com' ) === null && \Nino\Auth::confirmPassword( $appData, 'neighbour@example.com', 'another correct horse' ) === true );

// The lock runs out: the bucket is seeded as the end of the cooldown has left it, and the password is confirmed again
$seedTries( [ $bucket => 0 - ( time() - 10 ) ] );
check( 'a lock that has run out lets the right password through, and a success clears what is left', \Nino\Auth::confirmPassword( $appData, 'own@example.com', 'correct horse battery staple' ) === true && $readTries( $bucket ) === null );

// An account that is lifted of its lock has the confirmation lifted too
$seedTries( [ $bucket => 0 - ( time() + 3600 ) ] );
\Nino\Auth::unlock( $appData, 'own@example.com' );
check( 'unlocking an account lifts the lock of its confirmation as well, and deleting it takes the bucket away', $readTries( $bucket ) === null && ( static function() use ( &$appData, $seedTries, $readTries ): bool {
	\Nino\Auth::insertUser( $appData, 'gone-soon@example.com', 'correct horse battery staple' );
	$seedTries( [ 'confirm:gone-soon@example.com' => 2 ] );
	\Nino\Auth::deleteUser( $appData, 'gone-soon@example.com' );
	return $readTries( 'confirm:gone-soon@example.com' ) === null;
} )() );

// An account that is none: refused, no bucket made for it, and the decoy asked all the same: the way is the one the sign-in has, so the file of the decoy is made by it
$decoyKept = \Nino\Filesystem::path( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/decoy.json' );
@unlink( $decoyKept );
unset( $appData['./nino/filesystem/cache'][ \Nino\Filesystem::CONTENT_DIR. '/.auth/decoy.json' ] );
clearstatcache();
check( 'a name that is no account is refused, gets no bucket, and the decoy is asked for it: the file that was gone is made', \Nino\Auth::confirmPassword( $appData, 'nobody-at-all@example.com', 'wrong' ) === false && $readTries( 'confirm:nobody-at-all@example.com' ) === null && is_file( $decoyKept ) === true );
$source = ninoCalls( (string) file_get_contents( __DIR__. '/../_nino/Nino/Auth/Auth.php' ), 'confirmPassword' );
check( 'exactly one password_verify() on every way, with the decoy asked before it, and nothing else that compares a secret', count( array_keys( $source, 'password_verify', true ) ) === 1 && array_search( 'decoyHash', $source, true ) < array_search( 'password_verify', $source, true )
	&& array_intersect( $source, [ 'crypt', 'hash_equals', 'md5', 'sha1', 'strcmp', 'password_hash' ] ) === [] );

// --- the decoy that cannot be kept is said in the log ---------------------------------------------------------

echo "\nAuth::decoyHash - a private directory that cannot be written\n";

/**
 *	A project whose .auth is a file where a directory is meant - the decoy cannot be kept - and, where asked, whose data directory is one as well, so that nothing that is written can be
 *	(as a directory that is not writable cannot be had as root, the file is what stands for it)
 */
$decoySite = static function( string $name, bool $dataToo = false ): array {
	$site = ninoSandbox( $name );
	$GLOBALS['decoySites'][] = ninoSandboxDir( $site );
	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
	mkdir( ninoSandboxDir( $site ). '/private', 0777, true );
	file_put_contents( ninoSandboxDir( $site ). '/private/.auth', 'a file, no directory' );

	if( $dataToo === true )
		file_put_contents( ninoSandboxDir( $site ). '/private/data', 'a file, no directory' );

	$site['/nino/auth/maxtries'] = 3;
	$site['/nino/auth/cooldown'] = 3600;

	return $site;
};
$decoySaid = static fn( array $warnings ): array => array_values( array_filter( $warnings, static fn( string $line ): bool => str_contains( $line, 'decoy hash cannot be kept' ) === true ) );

ninoWarnings();
$stuck = $decoySite( 'auth-decoy-stuck' );
$first = \Nino\Auth::decoyHash( $stuck );
$said = $decoySaid( ninoWarnings() );
check( 'a decoy that cannot be kept is said in the log, and is the hash of this request all the same', count( $said ) === 1 && password_needs_rehash( $first, PASSWORD_DEFAULT ) === false );
check( '...with no hash, no password and no account in what is said', str_contains( $said[0] ?? '', $first ) === false && str_contains( $said[0] ?? '', 'example.com' ) === false && str_contains( $said[0] ?? '', '$2y$' ) === false );

// The next attempt of the process and the next request of the hour say nothing more: the counter holds the hour, the key of the process the process
\Nino\Auth::decoyHash( $stuck );
check( 'the second attempt of the same request says nothing', $decoySaid( ninoWarnings() ) === [] );
unset( $stuck['./nino/auth/decoy-said'] );
\Nino\Auth::decoyHash( $stuck );
check( 'the next request within the hour says nothing either: the counter that holds the hour is in the data directory', $decoySaid( ninoWarnings() ) === [] );

// Where nothing at all can be written the process is the unit: said once for it, and once more for the next process
$nothing = $decoySite( 'auth-decoy-nothing', true );
\Nino\Auth::decoyHash( $nothing );
$first = $decoySaid( ninoWarnings() );
\Nino\Auth::decoyHash( $nothing );
$again = $decoySaid( ninoWarnings() );
unset( $nothing['./nino/auth/decoy-said'] );
\Nino\Auth::decoyHash( $nothing );
$next = $decoySaid( ninoWarnings() );
check( 'where not even the counter can be written it is said once for the process, and not never: once for the first attempt, none for the second, one for the next process', count( $first ) === 1 && $again === [] && count( $next ) === 1 );

// A decoy that was kept is no news
$kept = ninoSandbox( 'auth-decoy-kept' );
$GLOBALS['decoySites'][] = ninoSandboxDir( $kept );
\Nino\Auth::decoyHash( $kept );
\Nino\Auth::decoyHash( $kept );
check( 'a decoy that is kept is said of in no way, at the first attempt or after it', $decoySaid( ninoWarnings() ) === [] );

// The saying hangs on the keeping of the decoy and on nothing the attempt is: a known account, an unknown one and one that is locked get the one same sentence, once each
$sentences = [];

foreach( [ 'a known account' => [ 'known@example.com', 'wrong' ], 'an account that is none' => [ 'none@example.com', 'wrong' ], 'an account that is locked' => [ 'locked@example.com', 'correct horse battery staple' ] ] as $way => [ $who, $what ] ) {

	$site = $decoySite( 'auth-decoy-way' );
	$site['./nino/filesystem/path'] = ninoSandboxDir( $site );
	\Nino\Auth::insertUser( $site, 'known@example.com', 'correct horse battery staple' );
	\Nino\Auth::insertUser( $site, 'locked@example.com', 'correct horse battery staple' );
	\Nino\Filesystem::mutate( $site, '/data/auth-tries.php', static fn( array $state ): array => $state + [ 'locked@example.com' => 0 - time() - 3600 ] );
	ninoWarnings();
	$answer = \Nino\Auth::loginUser( $site, $who, $what );
	$sentences[$way] = [ $answer === false, $decoySaid( ninoWarnings() ) ];
}

check( 'a known account, an account that is none and one that is locked are each refused and each say the same one sentence of the decoy: the saying makes no difference between them', count( $sentences ) === 3
	&& count( array_unique( array_map( 'json_encode', $sentences ) ) ) === 1 && count( $sentences['a known account'][1] ) === 1 && $sentences['a known account'][0] === true );
check( '...and recovery says it as the sign-in does: the one decoy, asked before the lock', in_array( 'decoyHash', ninoCalls( (string) file_get_contents( __DIR__. '/../_admin/Admin.php' ), 'verify' ), true ) === true );

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

// A post from a visitor with no session holds nothing to match: it is refused,
// and refusing it does not give the visitor a token, a session and a cookie
$sessionOfCsrf = $_SESSION[ $appData['./nino/uid'] ] ?? [];
unset( $_SESSION[ $appData['./nino/uid'] ] );
$_POST['_csrf'] = 'wrong-token';
$fakeRequest = [ '/nino/http/request' => [ 'method' => 'POST' ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Csrf::callbackResponse( $appData, $fakeRequest );
check( 'a wrong token from a visitor with no session is refused', $fakeRequest['/nino/http/response']['statusCode'] === 403 );
check( '...without minting a token for them', \Nino\Runtime::getSessionValue( $appData, './nino/csrf/token' ) === null );
$_POST['_csrf'] = '';
$fakeRequest = [ '/nino/http/request' => [ 'method' => 'POST' ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Csrf::callbackResponse( $appData, $fakeRequest );
check( '...nor does a post with no token at all', $fakeRequest['/nino/http/response']['statusCode'] === 403 && \Nino\Runtime::getSessionValue( $appData, './nino/csrf/token' ) === null );
$_SESSION[ $appData['./nino/uid'] ] = $sessionOfCsrf;

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

// Protection is the required \Nino\Csrf kernel class, always active, not a
// module: Auth::callbackLoginResponse()/callbackLogoutResponse() refuse
// nothing for want of the Csrf module in '/nino/modules'. This sandbox's
// $appData never lists it anywhere in this file, and a login or logout POST
// here is answered by the two callbacks cooperating through the shared
// './nino/csrf/blocked' flag, with no module in the loop at all.
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

authResumeChecks( $appData );

echo "\n";

// --- Cleanup ------------------------------------------------------------

foreach( $GLOBALS['decoySites'] ?? [] as $decoyDir )
	\Nino\Filesystem::removeDir( $decoyDir );

ninoDone( $appData );

/**
 *	Auth::resumeUser() opens the session of an account the way loginUser() does after the password, and asks for no password: the session id and the token rotate, a session of an
 *	earlier sign-in ends, a token is kept with the ip, the callback '/nino/auth/login' runs, the time of the login is written - for an account that is active and none other
 *
 *	@param		array 		&$appData
 *
 *	@return 	void
 */
function authResumeChecks( array &$appData ): void {

	echo "\nAuth::resumeUser\n";

	$logins = [];
	\Nino\Callbacks::registerCallback( $appData, '/nino/auth/login', static function( array &$appData, array &$user ) use ( &$logins ): void { $logins[] = $user['mail']; } );

	foreach( [ 'back@example.com', 'by-password@example.com', 'off-resume@example.com', 'other@example.com' ] as $mail )
		\Nino\Auth::insertUser( $appData, $mail, 'correct horse battery staple' );

	$logouts = [];
	\Nino\Callbacks::registerCallback( $appData, '/nino/auth/logout', static function( array &$appData, array &$user ) use ( &$logouts ): void { $logouts[] = $user['mail']; } );

	\Nino\Auth::loginUser( $appData, 'by-password@example.com', 'correct horse battery staple' );
	$csrfBefore = \Nino\Csrf::getToken( $appData );
	$byPassword = \Nino\Auth::getUser( $appData, 'by-password@example.com' );
	$passwordLogins = $logins;
	$logins = [];
	$tokenBefore = \Nino\Runtime::getSessionValue( $appData, './nino/auth/token', '' );

	// An account with an outdated hash: nothing on this path touches the password
	$appData['/nino/auth/user']['back@example.com']['pw'] = password_hash( 'correct horse battery staple', PASSWORD_BCRYPT, [ 'cost' => 4 ] );
	$hashBefore = $appData['/nino/auth/user']['back@example.com']['pw'];
	$resumed = \Nino\Auth::resumeUser( $appData, 'back@example.com' );
	$stored = \Nino\Auth::getUser( $appData, 'back@example.com' );
	$token = \Nino\Runtime::getSessionValue( $appData, './nino/auth/token', '' );
	$csrfAfter = \Nino\Csrf::getToken( $appData );

	check( 'resumeUser opens a session for an active account with no password: the user comes back and is the current one', is_array( $resumed ) === true && $resumed['mail'] === 'back@example.com' && ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? '' ) === 'back@example.com' );
	check( '...with a token of its own in the session and in the record of the account, with the client ip - and the token is not the one of the sign-in before', is_string( $token ) === true && strlen( $token ) === 64 && $token !== $tokenBefore && array_keys( $stored['sessions'] ) === [ $token ]
		&& $stored['sessions'][$token]['ip'] === '127.0.0.1' && abs( $stored['sessions'][$token]['time'] - time() ) < 5 );
	check( '...the time of the login is written, the callback of a login ran once for the account, and the password hash is left as it was (no rehash)', abs( $stored['lastLogin'] - time() ) < 5 && $logins === [ 'back@example.com' ] && $stored['pw'] === $hashBefore );
	check( '...the sign-in that was there is ended by it - its logout callback ran once for that account, as for a login - and the csrf token is a new one', $logouts === [ 'by-password@example.com' ] && $csrfBefore !== $csrfAfter && strlen( $csrfAfter ) > 0 );
	$endedByResume = count( \Nino\Auth::getUser( $appData, 'by-password@example.com' )['sessions'] );

	// Byte for byte the same record as a login makes
	\Nino\Auth::loginUser( $appData, 'other@example.com', 'correct horse battery staple' );
	$other = \Nino\Auth::getUser( $appData, 'other@example.com' );
	check( '...what happens to the session of the account that was signed in before is what a login does: the same number of sessions are left', count( \Nino\Auth::getUser( $appData, 'back@example.com' )['sessions'] ) === $endedByResume );
	check( 'loginUser still makes the record it made: the same keys as a resumed one, a session with time and ip, the time of the login, the callback once', array_keys( $other ) === array_keys( $stored ) && array_keys( array_values( $other['sessions'] )[0] ) === array_keys( array_values( $stored['sessions'] )[0] )
		&& is_int( $other['lastLogin'] ) === true && $passwordLogins === [ 'by-password@example.com' ] && array_keys( $byPassword['sessions'] ) !== [] && array_keys( $byPassword ) === array_keys( $stored ) );

	// With no sign-in before it the token of the session is a new one too (session fixation)
	\Nino\Auth::logoutUser( $appData );
	$csrfVisitor = \Nino\Csrf::getToken( $appData );
	\Nino\Auth::resumeUser( $appData, 'back@example.com' );
	check( '...and where nobody was signed in the csrf token of the session is replaced as well, so that a token known before the sign-in is of no use after it', \Nino\Csrf::getToken( $appData ) !== $csrfVisitor );
	\Nino\Auth::loginUser( $appData, 'other@example.com', 'correct horse battery staple' );

	// Not an account to resume
	\Nino\Auth::setStatus( $appData, 'off-resume@example.com', false );
	$logins = [];
	$sessionBefore = \Nino\Auth::getUser( $appData, 'other@example.com' )['sessions'];
	check( 'an unknown account and an account that is switched off are false: no session, no callback, nothing written, the session that was there stays', \Nino\Auth::resumeUser( $appData, 'nobody-at-all@example.com' ) === false && \Nino\Auth::resumeUser( $appData, 'off-resume@example.com' ) === false
		&& $logins === [] && \Nino\Auth::getUser( $appData, 'off-resume@example.com' )['sessions'] === [] && ( \Nino\Auth::getCurrentUser( $appData )['mail'] ?? '' ) === 'other@example.com' && \Nino\Auth::getUser( $appData, 'other@example.com' )['sessions'] === $sessionBefore );

	\Nino\Auth::logoutUser( $appData );
	foreach( [ 'back@example.com', 'by-password@example.com', 'off-resume@example.com', 'other@example.com' ] as $mail )
		\Nino\Auth::deleteUser( $appData, $mail );
}
