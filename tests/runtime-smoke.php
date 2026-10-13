<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	runtime-smoke.php		Dependency-free smoke test for the runtime under the modules:
 *											Filesystem (a rejected write, mutate(), path() and url()), AppData
 *											(writeContentData() under overlapping requests and with a key the
 *											request does not carry), Callbacks, the autoload roots, Runtime
 *											(handleError(), startSession(), handleShutdown()) and a project
 *											served from a subdirectory. Runs against an isolated sandbox
 *											directory, never touches the real project data.
 *
 *	Usage: php tests/runtime-smoke.php
 */

// The features root is the fixture directory: a checkout ships no feature of
// its own (they come from dapeio/nino-features), and the autoload check
// below needs one to resolve. Before the kernel loads, like every constant
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );

require __DIR__. '/harness.php';

/**
 *	Probe whether a Filesystem::lockFile()'d path's sidecar .lock file is
 *	currently free, via a second, independent file handle - flock() is per
 *	open-file-description, so this genuinely conflicts with a handle Nino
 *	itself is still holding open, the same way a second request would.
 *	Releases its own probe lock again before returning, so it never leaves
 *	the sidecar locked for whatever runs next.
 *
 *	The same probe is in elements-smoke.php: two suites call it, and it is
 *	no helper the harness should carry.
 *
 *	@param		string		$sandbox			Sandbox root (./nino/filesystem/path)
 *	@param		string		$filename			Locked path as passed to lockFile(), eg. '/elements/foo.php'
 *
 *	@return		bool										True if the lock was free (and has now been released again)
 */
function probeLockFree( string $sandbox, string $filename ): bool {
	$lockPath	= $sandbox. '/private/data/.locks/'. sha1( $filename ). '.lock';
	$probe		= @fopen( $lockPath, 'c' );
	if( $probe === false )
		return false;
	$free = flock( $probe, LOCK_EX | LOCK_NB );
	if( $free === true )
		flock( $probe, LOCK_UN );
	fclose( $probe );
	return $free;
}

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$appData = ninoSandbox( 'runtime' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";


// --- Filesystem cache after a rejected write ----------------------------

echo "Filesystem::putFileContent - failed writes never become cached state\n";

\Nino\Filesystem::putFileContent( $appData, '/data/cache-state.json', [ 'state' => 'old' ] );
check( 'the cache-write fixture starts with the persisted old value', \Nino\Filesystem::getFileContent( $appData, '/data/cache-state.json', [] ) === [ 'state' => 'old' ] );

$recursiveJson = [];
$recursiveJson['self'] =& $recursiveJson;
check( 'an unserializable JSON write is rejected', \Nino\Filesystem::putFileContent( $appData, '/data/cache-state.json', $recursiveJson ) === false );
unset( $recursiveJson );
check( 'a rejected write leaves the previous bytes on disk', json_decode( (string) file_get_contents( \Nino\Filesystem::path( $appData, '/data/cache-state.json' ) ), true ) === [ 'state' => 'old' ] );
check( 'a rejected write leaves the in-request read cache on the persisted value', \Nino\Filesystem::getFileContent( $appData, '/data/cache-state.json', [] ) === [ 'state' => 'old' ] );

/*	And a .json that does not decode: an empty file, a truncated one, one
	holding anything but json. json_decode() answers null for all of them, and
	that null was cached and handed back as the file's content - where
	$default is what every other file that cannot be read answers, and what
	mutate() promises its callback: one typed for the array it was given as
	the default met a TypeError instead	*/
file_put_contents( \Nino\Filesystem::path( $appData, '/data/broken.json' ), '{ "state": ' );
check( 'a .json that does not decode answers the default, like a file that is not there', \Nino\Filesystem::getFileContent( $appData, '/data/broken.json', [ 'fresh' => true ] ) === [ 'fresh' => true ] );

file_put_contents( \Nino\Filesystem::path( $appData, '/data/empty.json' ), '' );
check( '...and so does an empty one', \Nino\Filesystem::getFileContent( $appData, '/data/empty.json', [ 'fresh' => true ] ) === [ 'fresh' => true ] );

// Through a try/catch so the old answer is reported as a failed check rather
// than ending the suite: the callback is typed for an array, and null is not one
$mutatedBroken = ( static function() use ( &$appData ): string {
	try {
		return \Nino\Filesystem::mutate( $appData, '/data/broken.json', static fn( array $state ): array => $state + [ 'added' => true ], [ 'seed' => 1 ] ) === true ? 'written' : 'refused';
	}
	catch( \Throwable $e ) {
		return $e::class;
	}
} )();
check( 'mutate() starts its callback from the default it was given rather than from null', $mutatedBroken === 'written'
	&& \Nino\Filesystem::getFileContent( $appData, '/data/broken.json', [] ) === [ 'seed' => 1, 'added' => true ] );

echo "\n";


// --- Filesystem::mutate - the lock comes off however the callback leaves ------------------------------

echo "Filesystem::mutate releases its lock when what runs under it throws\n";

// mutate() releases on both of its own exits - a callback answering null,
// and the write at the end. A throwable had no exit at all: it walked past
// every unlockFile() there is, and lockFile() keeps the handle outside the
// cache slot on purpose, so nothing else dropped it either. The file then
// stayed locked against every other process for the rest of the request.
// Two ways in: the callback itself, and the include of a .php file that no
// longer parses - which throws from inside the same lock, one line earlier
$thrown = null;
try {
	\Nino\Filesystem::mutate( $appData, '/data/throwing.php', static function( mixed $state ): array {
		throw new \RuntimeException( 'the callback gave up' );
	} );
}
catch( \Throwable $e ) {
	$thrown = $e;
}
check( 'a callback\'s throwable carries on out of mutate unchanged', $thrown instanceof \RuntimeException && $thrown->getMessage() === 'the callback gave up' );
check( 'and the lock it walked out of is released', probeLockFree( $sandbox, '/data/throwing.php' ) === true );

file_put_contents( \Nino\Filesystem::path( $appData, '/data/unparseable.php' ), '<?php return [ ' );
$parseError = null;
try {
	\Nino\Filesystem::mutate( $appData, '/data/unparseable.php', static fn( mixed $state ): array => [ 'x' ] );
}
catch( \Throwable $e ) {
	$parseError = $e;
}
check( 'a .php file that no longer parses throws from inside the lock', $parseError instanceof \ParseError );
check( 'and that lock is released as well', probeLockFree( $sandbox, '/data/unparseable.php' ) === true );

echo "\n";


// --- Callbacks::doCallbacks - every callable shape registerCallback() accepts ---------------------------

echo "Callbacks::doCallbacks runs every registered callable shape\n";

// The kernel's own shortcodes are registered by Html::init(), which the
// sandbox never runs
\Nino\Html::init( $appData );

class KernelSmokeCallbackTarget {
	public array $seen = [];
	public function instanceMethod( array &$appData, mixed $args ): mixed { $this->seen[] = $args; return $args; }
	public static function staticMethod( array &$appData, mixed $args ): mixed { return ( is_array( $args ) ? $args : [] ) + [ 'static' => true ]; }
}
function kernelSmokeCallbackFunction( array &$appData, mixed $args ): mixed { return ( is_array( $args ) ? $args : [] ) + [ 'function' => true ]; }

$callbackTarget = new KernelSmokeCallbackTarget();

\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', [ $callbackTarget, 'instanceMethod' ] );
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', [ KernelSmokeCallbackTarget::class, 'staticMethod' ] );
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', 'kernelSmokeCallbackFunction' );
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', function( array &$appData, mixed $args ): mixed { return ( is_array( $args ) ? $args : [] ) + [ 'closure' => true ]; } );

$callbackArgs = [ 'start' => true ];
$callbackResult = \Nino\Callbacks::doCallbacks( $appData, 'test/callbackshapes', $callbackArgs );

check( 'an [object, method] callback actually ran, not just registered', count( $callbackTarget->seen ) === 1 );
check( 'a [class, method] static callback ran', ( $callbackResult['static'] ?? false ) === true );
check( 'a plain function-name callback ran', ( $callbackResult['function'] ?? false ) === true );
check( 'a closure callback ran', ( $callbackResult['closure'] ?? false ) === true );

/*	...and a callback that is not one says so. A hook is a string and a
	callable, and both are easy to get slightly wrong - a method renamed and one
	registration left behind, a typo in 'callbackRespones'. The registration
	returned in silence, the hook never fired, and nothing anywhere said why:
	the symptom is a feature that quietly does not work	*/
ninoWarnings();
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', [ KernelSmokeCallbackTarget::class, 'methodThatIsNotThere' ] );
$badCallbackWarnings = ninoWarnings();

check( 'a callback that is not callable is refused with a warning naming the hook', count( $badCallbackWarnings ) === 1
	&& str_contains( $badCallbackWarnings[0], "test/callbackshapes" ) === true
	&& str_contains( $badCallbackWarnings[0], 'not callable' ) === true );

$afterBad = [ 'start' => true ];
\Nino\Callbacks::doCallbacks( $appData, 'test/callbackshapes', $afterBad );
check( '...and is not registered, so firing the hook still works', ( $afterBad['closure'] ?? false ) === true && count( $callbackTarget->seen ) === 2 );

// The read-only side: who answers a hook, without firing it
$registered = \Nino\Callbacks::registered( $appData, 'test/callbackshapes' );
check( 'registered() lists the callables in the order they are called, and fires nothing', count( $registered ) === 4 && $registered[0] === [ $callbackTarget, 'instanceMethod' ]
	&& $registered[1] === [ KernelSmokeCallbackTarget::class, 'staticMethod' ] && $registered[2] === 'kernelSmokeCallbackFunction' && $registered[3] instanceof \Closure && count( $callbackTarget->seen ) === 2 );
\Nino\Callbacks::registerCallback( $appData, 'test/callbackshapes', [ KernelSmokeCallbackTarget::class, 'staticMethod' ], 2 );
check( '...a lower priority number first, as doCallbacks() runs it, and a name nobody registered has none', \Nino\Callbacks::registered( $appData, 'test/callbackshapes' )[0] === [ KernelSmokeCallbackTarget::class, 'staticMethod' ]
	&& count( \Nino\Callbacks::registered( $appData, 'test/callbackshapes' ) ) === 5 && \Nino\Callbacks::registered( $appData, 'test/nobody-registered-this' ) === [] );
\Nino\Html::addShortcode( $appData, 'shortcodelist-a', 'kernelSmokeCallbackFunction' );
\Nino\Html::addShortcode( $appData, 'shortcodelist-b', 'kernelSmokeCallbackFunction' );
check( 'Html::shortcodes() lists the registered names, the kernel\'s own among them, in the order they were added', array_slice( \Nino\Html::shortcodes( $appData ), -2 ) === [ 'shortcodelist-a', 'shortcodelist-b' ]
	&& in_array( 'json', \Nino\Html::shortcodes( $appData ), true ) === true );

echo "\n";


// --- AppData::writeContentData concurrency ------------------------------------------------------------

echo "AppData::writeContentData doesn't clobber a concurrent write to a different key\n";

// The snapshots below boot from a config.php, which has to be there
\Nino\Filesystem::putFileContent( $appData, '/config.php', [] );

// Two independently-"booted" appData snapshots, both built fresh from the same
// on-disk config.php before either has written - simulating two overlapping
// requests (eg. a login in one tab, an image-slot save in another)
$buildAppData = function() use ( $sandbox ) {
	$fresh = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $fresh );
	$fresh['./nino/filesystem/path'] 				= $sandbox;
	$fresh['./nino/filesystem/configpath'] 	= $sandbox. '/private';
	$fresh['./nino/filesystem/contentpath'] = $sandbox. '/private';
	$fresh['./nino/filesystem/publicpath'] 	= $sandbox. '/public';
	\Nino\AppData::init( $fresh );
	return $fresh;
};

$reqA = $buildAppData();
$reqB = $buildAppData();

$reqA['/nino/auth/user']['race@example.com'] = [ 'marker' => 'FROM_A' ];
\Nino\AppData::writeContentData( $reqA, [ '/nino/auth/user' ] );

$reqB['/nino/html/images'] = [ 'marker' => 'FROM_B' ];
\Nino\AppData::writeContentData( $reqB, [ '/nino/html/images' ] );

$onDisk = include $sandbox. '/private/config.php';
check( 'a later write to a different key doesn\'t erase an earlier request\'s change', ( $onDisk['/nino/auth/user']['race@example.com']['marker'] ?? null ) === 'FROM_A' );
check( 'the later request\'s own change is still persisted', ( $onDisk['/nino/html/images']['marker'] ?? null ) === 'FROM_B' );

// Two overlapping logins of the *same* user: the second must not drop the
// first one's session token just because its own copy predates it
$sessionUser = 'parallel@example.com';
\Nino\Auth::insertUser( $appData, $sessionUser, 'correct horse battery staple' );

$sessions = function() use ( $sandbox, $sessionUser ): array {
	$onDisk = include $sandbox. '/private/config.php';
	return $onDisk['/nino/auth/user'][$sessionUser]['sessions'] ?? [];
};

$loginA = $buildAppData();
$loginB = $buildAppData();
foreach( [ $loginA, $loginB ] as $req )
	$req['./nino/auth/baseline'] = $req['/nino/auth/user'] ?? [];

$loginA['/nino/auth/user'][$sessionUser]['sessions']['tokenA'] = [ 'time' => time(), 'ip' => '10.0.0.1' ];
\Nino\AppData::writeContentData( $loginA, [ '/nino/auth/user' ] );

$loginB['/nino/auth/user'][$sessionUser]['sessions']['tokenB'] = [ 'time' => time(), 'ip' => '10.0.0.2' ];
\Nino\AppData::writeContentData( $loginB, [ '/nino/auth/user' ] );

check( 'a parallel login keeps its own session', isset( $sessions()['tokenB'] ) === true );
check( 'and does not log the other one out', isset( $sessions()['tokenA'] ) === true );

// ...but a revocation must not have those same "unseen" tokens merged back
// in: after a password change or a "log out everywhere" everything goes,
// including a session that appeared while the request was running
$revoke = $buildAppData();
$revoke['./nino/auth/baseline'] = $revoke['/nino/auth/user'] ?? [];

$sneaky = $buildAppData();
$sneaky['./nino/auth/baseline'] = $sneaky['/nino/auth/user'] ?? [];
$sneaky['/nino/auth/user'][$sessionUser]['sessions']['tokenC'] = [ 'time' => time(), 'ip' => '10.0.0.3' ];
\Nino\AppData::writeContentData( $sneaky, [ '/nino/auth/user' ] );

\Nino\Auth::logoutAllSessions( $revoke, $sessionUser );
check( 'log out everywhere really clears every session, including a parallel one', $sessions() === [] );

$pwChange = $buildAppData();
$pwChange['./nino/auth/baseline'] = $pwChange['/nino/auth/user'] ?? [];

$sneaky = $buildAppData();
$sneaky['./nino/auth/baseline'] = $sneaky['/nino/auth/user'] ?? [];
$sneaky['/nino/auth/user'][$sessionUser]['sessions']['tokenD'] = [ 'time' => time(), 'ip' => '10.0.0.4' ];
\Nino\AppData::writeContentData( $sneaky, [ '/nino/auth/user' ] );

\Nino\Auth::updateUser( $pwChange, $sessionUser, $sessionUser, 'a brand new password' );
check( 'a password change ends a session opened while it was running', $sessions() === [] );

/*	Only the sessions were merged, so everything else about an account was
	whatever the writing request had copied at boot: a login finishing after
	an administrator's change wrote its own stale copy of every record back
	over that change, and an account created in between vanished. The records
	are merged now too - this request's where it changed one, the file's
	where it did not	*/
$accounts = function() use ( $sandbox ): array {
	$onDisk = include $sandbox. '/private/config.php';
	return $onDisk['/nino/auth/user'] ?? [];
};

\Nino\Auth::insertUser( $appData, 'bystander@example.com', 'correct horse battery staple' );

// The slow request: booted, and about to write a session of its own
$slowLogin = $buildAppData();
$slowLogin['./nino/auth/baseline'] = $slowLogin['/nino/auth/user'] ?? [];

// Meanwhile: an administrator changes one account and creates another
$admin = $buildAppData();
$admin['./nino/auth/baseline'] = $admin['/nino/auth/user'] ?? [];
$admin['/nino/auth/user']['bystander@example.com']['marker'] = 'CHANGED_BY_THE_ADMIN';
$admin['/nino/auth/user']['brandnew@example.com'] = [ 'pw' => 'x', 'status' => 2, 'perms' => [], 'sessions' => [] ];
\Nino\AppData::writeContentData( $admin, [ '/nino/auth/user' ] );

$slowLogin['/nino/auth/user'][$sessionUser]['sessions']['tokenSlow'] = [ 'time' => time(), 'ip' => '10.0.0.5' ];
\Nino\AppData::writeContentData( $slowLogin, [ '/nino/auth/user' ] );

check( 'a login finishing later keeps its own session', isset( $sessions()['tokenSlow'] ) === true );
check( '...and does not write its stale copy over an account it never touched', ( $accounts()['bystander@example.com']['marker'] ?? null ) === 'CHANGED_BY_THE_ADMIN' );
check( '...nor drop an account created while it was running', isset( $accounts()['brandnew@example.com'] ) === true );

// The other direction: what this request did change is this request's
$changer = $buildAppData();
$changer['./nino/auth/baseline'] = $changer['/nino/auth/user'] ?? [];
$other = $buildAppData();
$other['./nino/auth/baseline'] = $other['/nino/auth/user'] ?? [];

$other['/nino/auth/user']['bystander@example.com']['marker'] = 'AND_AGAIN';
\Nino\AppData::writeContentData( $other, [ '/nino/auth/user' ] );

$changer['/nino/auth/user']['bystander@example.com']['marker'] = 'CHANGED_HERE';
\Nino\AppData::writeContentData( $changer, [ '/nino/auth/user' ] );
check( 'a record this request did change is written as this request left it', ( $accounts()['bystander@example.com']['marker'] ?? null ) === 'CHANGED_HERE' );

// ...and a deletion is a change like any other: the account stays gone
$deleter = $buildAppData();
$deleter['./nino/auth/baseline'] = $deleter['/nino/auth/user'] ?? [];
unset( $deleter['/nino/auth/user']['brandnew@example.com'] );
\Nino\AppData::writeContentData( $deleter, [ '/nino/auth/user' ] );
check( 'a deleted account is not carried back in from the file', isset( $accounts()['brandnew@example.com'] ) === false );

/*	A login writes its session and the time it happened into the account's own
	record. The time is not a change of the record - or a login finishing
	after an administrator had switched the account off would write the status
	it booted with back over that, and one finishing before would lose its
	timestamp to the administrator's copy. Both orders, with two copies booted
	from the same file	*/
\Nino\Auth::insertUser( $appData, 'merge@example.com', 'correct horse battery staple' );

$loginAt		= 1790000000;
$record 		= function() use ( $accounts ): array { return $accounts()['merge@example.com'] ?? []; };
$bootBoth		= function() use ( $buildAppData ): array {
	$admin = $buildAppData();
	$login = $buildAppData();
	foreach( [ &$admin, &$login ] as &$booted )
		$booted['./nino/auth/baseline'] = $booted['/nino/auth/user'] ?? [];
	unset( $booted );
	return [ $admin, $login ];
};
$doLogin		= function( array &$booted, string $token ) use ( $loginAt ): void {
	$booted['/nino/auth/user']['merge@example.com']['lastLogin'] = $loginAt;
	$booted['/nino/auth/user']['merge@example.com']['sessions'][$token] = [ 'time' => $loginAt, 'ip' => '10.0.0.9' ];
	\Nino\AppData::writeContentData( $booted, [ '/nino/auth/user' ] );
};

// The administrator first, the login finishing after
[ $adminCopy, $loginCopy ] = $bootBoth();
\Nino\Auth::setStatus( $adminCopy, 'merge@example.com', false );
$doLogin( $loginCopy, 'tokenLate' );
check( 'a login finishing after a deactivation does not bring the account back', ( $record()['status'] ?? null ) === \Nino\Auth::STATUS_DISABLED );
check( '...and keeps its own login time', ( $record()['lastLogin'] ?? null ) === $loginAt );

// The login first, the administrator's older copy writing after
\Nino\Auth::setStatus( $appData, 'merge@example.com', true );
[ $adminCopy, $loginCopy ] = $bootBoth();
$doLogin( $loginCopy, 'tokenEarly' );
\Nino\Auth::setStatus( $adminCopy, 'merge@example.com', false );
check( 'a deactivation written after a login keeps the login time', ( $record()['status'] ?? null ) === \Nino\Auth::STATUS_DISABLED && ( $record()['lastLogin'] ?? null ) === $loginAt );
check( '...and ends the session that login opened, as a revocation does', ( $record()['sessions'] ?? null ) === [] );

// A password an administrator changed is not undone by a login in parallel
\Nino\Auth::setStatus( $appData, 'merge@example.com', true );
[ $adminCopy, $loginCopy ] = $bootBoth();
\Nino\Auth::updateUser( $adminCopy, 'merge@example.com', 'merge@example.com', 'the administrator chose this one' );
$doLogin( $loginCopy, 'tokenParallel' );
check( 'an administrator\'s password change is not undone by a parallel login', password_verify( 'the administrator chose this one', (string) ( $record()['pw'] ?? '' ) ) === true );
check( '...and the login keeps its time', ( $record()['lastLogin'] ?? null ) === $loginAt );

// The later of two times wins, whichever copy holds it
[ $adminCopy, $loginCopy ] = $bootBoth();
$adminCopy['/nino/auth/user']['merge@example.com']['lastLogin'] = $loginAt - 500;
$adminCopy['/nino/auth/user']['merge@example.com']['role'] = '';
\Nino\AppData::writeContentData( $adminCopy, [ '/nino/auth/user' ] );
check( 'a record written with an older login time does not turn the clock back', ( $record()['lastLogin'] ?? null ) === $loginAt );
\Nino\Auth::deleteUser( $appData, 'merge@example.com' );

// Regression: mutate()'s return value used to be silently discarded here -
// a failed config.php write (disk full, permission denied, ...) had no
// observable effect anywhere. Forced via a configpath whose base is a
// plain file, not a directory: nothing can be created "inside" it
// (ENOTDIR), which fails the write even for root, unlike a chmod-based
// denial. lockFile() itself still succeeds - it locks a sidecar file
// under the (perfectly normal) main filesystem path, not configpath - so
// this exercises mutate()'s own failure, not writeContentData()'s
// pre-existing "could not lock" check.
$writeFailDir = sys_get_temp_dir(). '/nino-runtime-smoke-writefail-'. uniqid();
mkdir( $writeFailDir, 0777, true );
$brokenConfigBase = $writeFailDir. '/not-a-directory';
file_put_contents( $brokenConfigBase, 'x' );

$writeFailAppData = [ './nino/uid' => $writeFailDir ];
\Nino\AppData::prepare( $writeFailAppData );
$writeFailAppData['./nino/filesystem/path']				= $writeFailDir;
$writeFailAppData['./nino/filesystem/configpath']	= $brokenConfigBase;
$writeFailAppData['/nino/auth/user'] = [ 'someone@example.com' => [] ];

$capturedErrors = ninoErrors( function() use ( &$writeFailAppData ) {
	\Nino\AppData::writeContentData( $writeFailAppData, [ '/nino/auth/user' ] );
} );

$sawWriteFailure = count( array_filter( $capturedErrors, fn( $e ) => $e[0] === E_USER_ERROR && str_contains( $e[1], 'failed to write config.php' ) ) ) === 1;
check( 'writeContentData() surfaces a failed config.php write via trigger_error(E_USER_ERROR)', $sawWriteFailure );

\Nino\Filesystem::removeDir( $writeFailDir );

echo "\n";


// --- AppData::writeContentData - a key the request does not carry -------------

echo "AppData::writeContentData - a key the request does not carry\n";

/*	Regression: the loop persisted '$appData[$key] ?? null' for every key it
	was handed, so a caller naming one its own appData never carried stored an
	explicit null - and a stored null is not an absent key. AppData::init()
	merges config.php *over* DEFAULTS key by key, so that null shadowed the
	framework default on every later boot, for the life of the file	*/
$absentKey = $buildAppData();
unset( $absentKey['/nino/cache/ttl'] );
\Nino\AppData::writeContentData( $absentKey, [ '/nino/cache/ttl' ] );

$absentOnDisk = include $sandbox. '/private/config.php';
check( 'a key the request does not carry is not persisted as null', array_key_exists( '/nino/cache/ttl', $absentOnDisk ) === false );
check( '...so the next boot still reads the framework default', ( $buildAppData()['/nino/cache/ttl'] ?? null ) === \Nino\AppData::DEFAULTS['/nino/cache/ttl'] );

// The other half of the same rule: naming a key that is gone from the
// request is how a value is taken back out of config.php - absent in
// memory, absent in the file
$storedTtl = $buildAppData();
$storedTtl['/nino/cache/ttl'] = 60;
\Nino\AppData::writeContentData( $storedTtl, [ '/nino/cache/ttl' ] );
$withTtl = include $sandbox. '/private/config.php';

$droppedTtl = $buildAppData();
unset( $droppedTtl['/nino/cache/ttl'] );
\Nino\AppData::writeContentData( $droppedTtl, [ '/nino/cache/ttl' ] );
$withoutTtl = include $sandbox. '/private/config.php';

check( 'a value the request dropped is removed from the file rather than nulled', ( $withTtl['/nino/cache/ttl'] ?? null ) === 60
	&& array_key_exists( '/nino/cache/ttl', $withoutTtl ) === false );

// The accounts are deliberately not in that rule - '/nino/auth/user' is
// merged three ways, where a record missing from this request's copy is a
// deletion the merge decides about rather than an absence
$accountsBefore	= ( include $sandbox. '/private/config.php' )['/nino/auth/user'] ?? [];
$noAccounts			= $buildAppData();
unset( $noAccounts['/nino/auth/user'] );
\Nino\AppData::writeContentData( $noAccounts, [ '/nino/auth/user' ] );
$accountsAfter = ( include $sandbox. '/private/config.php' )['/nino/auth/user'] ?? null;

check( 'the accounts key is still written by its own merge, not removed', $accountsBefore !== [] && is_array( $accountsAfter ) === true );

echo "\n";


// --- Autoloading - project root, override, kernel guard and fallback ------

echo "Autoloading resolves project classes separately from the Nino kernel\n";

$projectAppRoot = __DIR__. '/../app';
$appDummyRoot = $projectAppRoot. '/KernelSmokeDummyModules';
$legacyDummyRoot = __DIR__. '/../_nino/KernelSmokeDummyModules';
$projectAppRootExisted = is_dir( $projectAppRoot );
$projectAppNinoRootExisted = is_dir( $projectAppRoot. '/Nino' );

$dummyModuleDir = $appDummyRoot. '/DummyCallModulesFix';
mkdir( $dummyModuleDir, 0777, true );
file_put_contents( $dummyModuleDir. '/DummyCallModulesFix.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class DummyCallModulesFix {
		public static bool $called = false;
		public static function init( array &$appData ): void {
			self::$called = true;
		}
	}
}
PHP
);

// No leading backslash - config.php can write either form, both name the
// same class, and PHP normalizes it away before the autoloader ever sees
// the name (see the spl_autoload_register() call at the bottom of Nino.php).
$appData['/nino/modules'] = [ 'KernelSmokeDummyModules\DummyCallModulesFix' ];
\Nino\Modules::callModules( $appData, 'init' );
check( 'a configured project module loads from the default app/ root without a leading backslash', \KernelSmokeDummyModules\DummyCallModulesFix::$called === true );

unset( $appData['/nino/modules'] );
\Nino\Modules::callModules( $appData, 'init' );
check( 'callModules() does not warn/crash when /nino/modules is entirely unset', true );

// Modules::collect() - the read-only twin: every active module answers one
// question, the answers are merged in module order, and a module without
// the method contributes nothing rather than failing the call
$collectDummyDir = $appDummyRoot. '/DummyCollect';
mkdir( $collectDummyDir, 0777, true );
file_put_contents( $collectDummyDir. '/DummyCollect.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class DummyCollect {
		public static function adminPanels( array &$appData ): array {
			return [ 'DummyCollectPanelA', 'DummyCollectPanelB' ];
		}
	}
}
PHP
);

$appData['/nino/modules'] = [ 'KernelSmokeDummyModules\DummyCallModulesFix', '\\KernelSmokeDummyModules\\DummyCollect', '\\KernelSmokeDummyModules\\DummyCollect' ];
check( 'collect() merges every module\'s answer in module order and skips modules without the method', \Nino\Modules::collect( $appData, 'adminPanels' ) === [ 'DummyCollectPanelA', 'DummyCollectPanelB', 'DummyCollectPanelA', 'DummyCollectPanelB' ] );
check( 'collect() answers an empty list for a question no module implements', \Nino\Modules::collect( $appData, 'somethingNobodyImplements' ) === [] );

unset( $appData['/nino/modules'] );
check( 'collect() answers an empty list when /nino/modules is entirely unset', \Nino\Modules::collect( $appData, 'adminPanels' ) === [] );

// A second project class is never listed in '/nino/modules': activation and
// class existence are independent, so a direct reference has to autoload it.
$directDummyDir = $appDummyRoot. '/DummyDirectAutoload';
mkdir( $directDummyDir, 0777, true );
file_put_contents( $directDummyDir. '/DummyDirectAutoload.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class DummyDirectAutoload {
		public static function ping(): string {
			return 'pong';
		}
	}
}
PHP
);

check( 'a project class in app/ autoloads on a direct reference without going through callModules()', \KernelSmokeDummyModules\DummyDirectAutoload::ping() === 'pong' );

/*	The one namespace every root serves: Nino\Modules\* is looked for below
	_nino/ first - where the runtime modules Nino ships live, the optional
	ones (Form, Navigation, Localepicker, Legal, Maintenance) among them -
	then below _admin/, then below features/, where a project installs a
	feature as one directory (see \Nino\Features; Design and the Builder
	come from the catalogue that way), then below the application root.	*/
check( 'an optional kernel module below _nino/Nino/Modules/ autoloads through the Nino\Modules namespace', class_exists( '\Nino\Modules\Navigation\Admin' ) === true
	&& class_exists( '\Nino\Modules\Form' ) === true );
check( 'a feature below features/ autoloads through the same namespace, the directory standing for the Nino/Modules prefix', class_exists( '\Nino\Modules\Sample' ) === true
	&& class_exists( '\Nino\Modules\Sample\Admin' ) === true && class_exists( '\Nino\Modules\Helper' ) === true );

/*	A class outside Nino\ resolves against the application root and nowhere
	else. _nino/ is not a second place to look: that is what keeps the kernel
	replaceable wholesale, and what makes a project class accidentally dropped
	in there fail loudly instead of being found and then vanishing on the next
	update.	*/
$strayDir = $legacyDummyRoot. '/StraySource';
mkdir( $strayDir, 0777, true );
file_put_contents( $strayDir. '/StraySource.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class StraySource {
		public static function source(): string {
			return 'kernel';
		}
	}
}
PHP
);
check( 'a project class below _nino/ does not autoload', class_exists( 'KernelSmokeDummyModules\StraySource' ) === false );

check( 'the autoload class-path allowlist rejects traversal segments', class_exists( 'KernelSmokeDummyModules\..\PrioritySource' ) === false );

// Nino\ is kernel-owned. Even if a project creates the matching app/ path,
// it must not be able to shadow a class shipped by the kernel.
$appKernelGuardDir = $projectAppRoot. '/Nino/KernelSmokeAutoloadGuard';
$kernelGuardDir = __DIR__. '/../_nino/Nino/KernelSmokeAutoloadGuard';
mkdir( $appKernelGuardDir, 0777, true );
mkdir( $kernelGuardDir, 0777, true );
file_put_contents( $appKernelGuardDir. '/KernelSmokeAutoloadGuard.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Nino {
	class KernelSmokeAutoloadGuard {
		public static function source(): string {
			return 'app';
		}
	}
}
PHP
);
file_put_contents( $kernelGuardDir. '/KernelSmokeAutoloadGuard.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Nino {
	class KernelSmokeAutoloadGuard {
		public static function source(): string {
			return 'kernel';
		}
	}
}
PHP
);
check( 'the Nino namespace resolves only inside _nino/ and cannot be shadowed from app/', \Nino\KernelSmokeAutoloadGuard::source() === 'kernel' );

// NINO_APP_DIR replaces the default app/ root when it is defined before the
// kernel is required - it is the only root a project class resolves against,
// so what app/ held before is no longer found.
$overrideAppRoot = $sandbox. '/custom-app';
$overrideDummyDir = $overrideAppRoot. '/KernelSmokeDummyModules/OverrideSource';
$defaultOverrideDir = $appDummyRoot. '/OverrideSource';
$defaultOnlyDir = $appDummyRoot. '/DefaultOnly';
mkdir( $overrideDummyDir, 0777, true );
mkdir( $defaultOverrideDir, 0777, true );
mkdir( $defaultOnlyDir, 0777, true );
file_put_contents( $overrideDummyDir. '/OverrideSource.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class OverrideSource {
		public static function source(): string {
			return 'override';
		}
	}
}
PHP
);
file_put_contents( $defaultOverrideDir. '/OverrideSource.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class OverrideSource {
		public static function source(): string {
			return 'default';
		}
	}
}
PHP
);
file_put_contents( $defaultOnlyDir. '/DefaultOnly.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace KernelSmokeDummyModules {
	class DefaultOnly {}
}
PHP
);

$overrideResult = runIsolated(
	'echo \KernelSmokeDummyModules\OverrideSource::source(). "|".
		( class_exists( "KernelSmokeDummyModules\\DefaultOnly" ) === true ? "loaded" : "missing" );',
	'define( "NINO_APP_DIR", '. var_export( $overrideAppRoot, true ). ' );'
);
check( 'NINO_APP_DIR replaces the default app/ root outright', trim( $overrideResult['stdout'] ) === 'override|missing' && $overrideResult['exitCode'] === 0 );

\Nino\Filesystem::removeDir( $appDummyRoot );
\Nino\Filesystem::removeDir( $legacyDummyRoot );
\Nino\Filesystem::removeDir( $appKernelGuardDir );
\Nino\Filesystem::removeDir( $kernelGuardDir );
if( $projectAppNinoRootExisted === false )
	@rmdir( $projectAppRoot. '/Nino' );
if( $projectAppRootExisted === false )
	@rmdir( $projectAppRoot );

echo "\n";


// --- Runtime::handleError() - severity-aware, not unconditionally fatal ---

echo "Runtime::handleError() - a NON_FATAL_LEVELS trigger_error() returns instead of exiting\n";

// Regression: handleError() used to exit() for every error level alike,
// including the default E_USER_NOTICE trigger_error() calls. Every
// "return ! trigger_error( ... )" in Elements and every "catch
// (\Throwable $e) { trigger_error(...); return ...; }" in Newsletter/Form
// is written assuming the handler returns and execution continues past the
// trigger_error() call - before this fix, none of them ever did outside
// Admin\Elements::apiSave()'s own temporary set_error_handler() override,
// which shadows the real one. A genuine exit() can't be observed from
// inside this same process without taking the whole suite down with it, so
// this spawns a child php process per case and checks what actually
// survived to run.
function runIsolated( string $body, string $beforeRequire = '' ): array {
	// php -r's code is implicitly already inside a php open/close pair,
	// unlike a regular script file - a literal '<?php' prefix here is a
	// syntax error
	$script = $beforeRequire. ' require '. var_export( __DIR__. '/../_nino/Nino.php', true ). '; '. $body;
	$descriptors = [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
	// display_errors=stderr: with display_errors=On, which php.ini-development
	// and every Homebrew/Herd/MAMP php carry, the cli writes a fatal to stdout
	// and the check against what the script printed reads the message too
	$process = proc_open( [ PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script ], $descriptors, $pipes );
	$stdout = stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$exitCode = proc_close( $process );
	return [ 'stdout' => $stdout, 'exitCode' => $exitCode ];
}

$invalidContentDir = runIsolated( '
	define( "NINO_PRIVATE_DIR", "/definitely/not/a/nino-private-directory" );
	echo "before\n";
	\Nino\init();
	echo "after\n";
' );
check( 'an invalid NINO_PRIVATE_DIR fails before boot instead of falling back elsewhere', trim( $invalidContentDir['stdout'] ) === 'before' && $invalidContentDir['exitCode'] !== 0 );

/*	Booting with no project at all. The checkout itself is that case now -
	it ships no private/config.php - so these run against the real root.

	The site half stops, and stops *quietly*: Runtime::handleError() cannot
	know '/nino/error/display' before config.php has been read, so it
	defaults to not displaying and the visitor gets a bare 500. That is the
	deliberate answer. Somebody who stumbles onto an unfinished install must
	not be told that an installer exists, let alone where - the person doing
	the installing came to /_install on purpose.	*/
$noProject = runIsolated( '
	echo "before\n";
	\Nino\init();
	echo "after\n";
' );
/*	Judged by what stopped running, not by the exit code: this failure lands
	*after* Runtime::init() has installed Nino's own handler, which answers
	with a 500 header and a bare exit() - status 0. The NINO_PRIVATE_DIR case
	above fails one step earlier, before the handler exists, so php's own
	fatal handling gives it a non-zero code. Both stop; only one can say so
	in $?.	*/
check( 'a site with no config.php stops at boot', trim( $noProject['stdout'] ) === 'before' );
check( '...without naming the file, the path or the installer', preg_match( '/config\.php|_install|NINO_CONFIG_DIR/i', $noProject['stdout'] ) !== 1 );

// ...and the installer half boots on the defaults alone, which is the whole
// reason the flag exists: it is what has to write the first config.php
$installing = runIsolated( '
	$appData = \Nino\init( true );
	echo json_encode( [
		"modules"	=> $appData["/nino/modules"] ?? null,
		"routes"		=> $appData["/nino/http/routes"] ?? null,
		"ttl"			=> $appData["/nino/cache/ttl"] ?? null,
		"display"	=> $appData["/nino/error/display"] ?? null,
	] );
' );
$installingData = json_decode( $installing['stdout'], true ) ?? [];

check( 'the installer boots without one', $installing['exitCode'] === 0 && $installingData !== [] );
check( '...on the framework defaults', ( $installingData['ttl'] ?? null ) === 3600
	&& ( $installingData['display'] ?? null ) === false
	&& in_array( '\\Nino\\Modules\\Assets', $installingData['modules'] ?? [], true ) === true );
/*	Nothing the project has decided is present - the routes it does carry are
	the kernel's own, registered at runtime by Auth::init() rather than read
	from a file. Not one of them renders a template, which is what a page
	route is: on a fresh install every url falls through to a 404 the wizard
	has not installed either.	*/
$installingPages = array_filter( $installingData['routes'] ?? [], static fn( array $route ): bool => str_contains( (string) ( $route['body'] ?? '' ), '[template' ) );
check( '...carrying no page the project has not made yet', $installingPages === [] );

/*	And the half of the split that every existing project depends on: the
	defaults sit *under* config.php, so a file that omits a key gets the
	default and a file that carries one wins. That is what lets a config.php
	written before the defaults existed keep working unchanged - it simply
	overrides each of them with the same value it always had.	*/
$partialRoot = $sandbox. '/partial';
mkdir( $partialRoot. '/private', 0777, true );
file_put_contents( $partialRoot. '/private/config.php', '<?php return '. var_export( [
	'/nino/cache/ttl'			=> 99,
	'/nino/locales/available'	=> [ 'en_US', 'fr_FR' ],
], true ). ';' );

$partial = [ './nino/uid' => $partialRoot ];
\Nino\AppData::prepare( $partial );
$partial['./nino/filesystem/path'] 				= $partialRoot;
$partial['./nino/filesystem/configpath'] 	= $partialRoot. '/private';
$partial['./nino/filesystem/contentpath'] = $partialRoot. '/private';
$partial['./nino/filesystem/publicpath'] 	= $partialRoot. '/public';
\Nino\AppData::init( $partial );

check( 'a config.php that omits a key gets the framework default', ( $partial['/nino/auth/maxtries'] ?? null ) === 5
	&& ( $partial['/nino/error/log'] ?? null ) === true );
check( '...and a key it does carry overrides that default', ( $partial['/nino/cache/ttl'] ?? null ) === 99 );
// A list is replaced wholesale rather than merged - see AppData::_merge(). A
// project that drops a locale has to actually lose it
check( '...including a list, which is replaced rather than appended to', ( $partial['/nino/locales/available'] ?? null ) === [ 'en_US', 'fr_FR' ] );

// A config.php written before Components: it lists Images, and a project module
// that registers a component of a kernel name. Components has to stand where
// Images stood, so that the project's module boots after it and its markup wins
class KernelSmokeTitleOverride {
	public static function init( array &$appData ): void {
		\Nino\Modules\Components::addComponent( $appData, 'title', static fn( array &$appData, array $args ): string => '<b class="mine">'. $args['value']. '</b>', [ 'label' => 'Title', 'source' => 'text' ] );
	}
}
$migrations = [
	'with Images'		=> [ [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Template', '\\Nino\\Modules\\Jstext', '\\Nino\\Modules\\Csrf', '\\Nino\\Modules\\Images', '\\Nino\\Modules\\Cache', '\\KernelSmokeTitleOverride' ], 5 ],
	'without Images'	=> [ [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Cache', '\\KernelSmokeTitleOverride', '\\Nino\\Modules\\Form' ], 3 ],
];
foreach( $migrations as $label => [ $modules, $position ] ) {

	$oldRoot = $sandbox. '/oldmodules-'. strlen( $label );
	mkdir( $oldRoot. '/private', 0777, true );
	file_put_contents( $oldRoot. '/private/config.php', '<?php return '. var_export( [ '/nino/modules' => $modules ], true ). ';' );

	$old = [ './nino/uid' => $oldRoot ];
	\Nino\AppData::prepare( $old );
	$old['./nino/filesystem/path'] 				= $oldRoot;
	$old['./nino/filesystem/configpath'] 	= $oldRoot. '/private';
	$old['./nino/filesystem/contentpath'] = $oldRoot. '/private';
	$old['./nino/filesystem/publicpath'] 	= $oldRoot. '/public';
	\Nino\AppData::init( $old );

	check( 'a config.php of 1.5 ('. $label. '): Components is listed once, where it takes the place before the project\'s own modules', array_search( '\\Nino\\Modules\\Components', $old['/nino/modules'], true ) === $position
		&& count( array_keys( $old['/nino/modules'], '\\Nino\\Modules\\Components', true ) ) === 1 && in_array( '\\Nino\\Modules\\Images', $old['/nino/modules'], true ) === false
		&& array_search( '\\KernelSmokeTitleOverride', $old['/nino/modules'], true ) > $position );

	\Nino\Modules::callModules( $old, 'init' );
	\Nino\Html::addFills( $old, [ '/t/k' => 'Hi' ], '*' );
	check( '...and the project\'s [title] is the one that renders', \Nino\Html::renderHtml( $old, '[title /t/k]' ) === '<b class="mine">Hi</b>' );
}

$bootstrap = '
	$sandbox = sys_get_temp_dir(). "/nino-handleerror-". bin2hex( random_bytes( 4 ) );
	mkdir( $sandbox, 0755, true );
	$appData = [ "./nino/uid" => $sandbox ];
	\Nino\AppData::prepare( $appData );
	$appData["./nino/filesystem/path"] = $sandbox;
	$appData["./nino/filesystem/configpath"] = $sandbox;
	$appData["/nino/error/log"] = false;
	$appData["/nino/error/display"] = false;
	\Nino\Runtime::init( $appData );
';

$notice = runIsolated( $bootstrap. '
	echo "before\n";
	trigger_error( "a plain notice", E_USER_NOTICE );
	echo "after\n";
' );
check( 'a default-level (E_USER_NOTICE) trigger_error() lets execution continue past it', trim( $notice['stdout'] ) === "before\nafter" );
check( 'and the request does not exit early', $notice['exitCode'] === 0 );

$warning = runIsolated( $bootstrap. '
	echo "before\n";
	trigger_error( "a plain warning", E_USER_WARNING );
	echo "after\n";
' );
check( 'an E_USER_WARNING trigger_error() also lets execution continue past it', trim( $warning['stdout'] ) === "before\nafter" );

$fatal = runIsolated( $bootstrap. '
	echo "before\n";
	trigger_error( "a real failure", E_USER_ERROR );
	echo "after\n";
' );
check( 'an E_USER_ERROR trigger_error() still terminates the request', trim( $fatal['stdout'] ) === 'before' );

$engineWarning = runIsolated( $bootstrap. '
	echo "before\n";
	$undefined[0];
	echo "after\n";
' );
check( 'an engine-raised warning (not one of our own E_USER_* calls) still terminates the request', trim( $engineWarning['stdout'] ) === 'before' );

// The one engine level that is not a bug in the code it is raised for: a
// deprecation says a future php will do this differently, not that this
// request is wrong. It used to stop the request like any other engine
// level, so a php minor upgrade took a site down - intermittently, since a
// compile-time deprecation only fires on the run that recompiles the file
$engineDeprecated = runIsolated( $bootstrap. '
	echo "before\n";
	trigger_error( "as the engine raises one", E_USER_DEPRECATED );
	// Not under @, and with every level reported: the handler bails out on a
	// level error_reporting() masks before it decides anything, so a masked
	// call would pass here whatever the handler did with a deprecation
	error_reporting( E_ALL );
	\Nino\Runtime::handleError( E_DEPRECATED, "a deprecation the engine raised", __FILE__, __LINE__ );
	echo "after\n";
' );
check( 'an engine-raised deprecation is recorded and the request carries on', trim( $engineDeprecated['stdout'] ) === "before\nafter" );

echo "\n";


// --- Runtime::startSession() - a session php refuses to start ----------------

echo "Runtime::startSession() - a session that cannot start says so\n";

/*	Regression: Runtime::init() starts the session a visitor already carries,
	and it runs before AppData::init() has read config.php - the cookie flags
	are fixed at session_start() time and cannot be retrofitted afterwards.
	php raises a warning of its own when session_start() fails, an engine level
	is fatal in here, and handleError() knew neither '/nino/error/log' nor
	'/nino/error/display' that early: an unusable session.save_path was a bare
	500 with nothing on the page, nothing on stderr and nothing in the log, on
	every request that carried a session cookie	*/
$sessionFailRoot = $sandbox. '/session-fail';
mkdir( $sessionFailRoot, 0777, true );
file_put_contents( $sessionFailRoot. '/config.php', '<?php return '. var_export( [ '/nino/modules' => [] ], true ). ';' );

// No output before \Nino\init() in any of these: on the cli headers_sent()
// is true the moment anything has been echoed, and startSession() answers
// false for that before it reaches php at all
$sessionFail = runIsolated(
	'$appData = \Nino\init();
	echo json_encode( [ "status" => session_status(), "csrf" => strlen( \Nino\Csrf::getToken( $appData ) ) ] );',
	'define( "NINO_PRIVATE_DIR", '. var_export( $sessionFailRoot, true ). ' );
	ini_set( "session.save_path", "/definitely/not/a/nino-session-directory" );
	$_COOKIE[ session_name() ] = "0123456789abcdef0123456789abcdef";'
);

$sessionFailData	= json_decode( $sessionFail['stdout'], true );
$sessionFailLog		= glob( $sessionFailRoot. '/data/logs.*.php' ) ?: [];
$sessionFailEntries	= ( $sessionFailLog !== [] ) ? (array) ( include $sessionFailLog[0] ) : [];
$sessionFailReasons	= implode( "\n", array_map( static fn( mixed $entry ): string => is_array( $entry ) === true ? (string) ( $entry['message'] ?? '' ) : '', $sessionFailEntries ) );

check( 'a session php refuses to start does not end the request', is_array( $sessionFailData ) === true && $sessionFail['exitCode'] === 0 );
check( '...and the request carries on without one, which fails a csrf check rather than passing it', ( $sessionFailData['status'] ?? null ) === PHP_SESSION_NONE
	&& ( $sessionFailData['csrf'] ?? 0 ) === 64 );
check( '...and the reason php gave is in the log, where a bare 500 left nothing at all', str_contains( $sessionFailReasons, 'startSession' ) === true
	&& str_contains( $sessionFailReasons, 'session_start()' ) === true );

// The other side of it: a session that can start still starts, cookie
// flags and all - the silencing above must not swallow the normal path
$sessionOkRoot = $sandbox. '/session-ok';
mkdir( $sessionOkRoot. '/sessions', 0777, true );
file_put_contents( $sessionOkRoot. '/config.php', '<?php return '. var_export( [ '/nino/modules' => [] ], true ). ';' );

$sessionOk = runIsolated(
	'$appData = \Nino\init();
	echo json_encode( [ "status" => session_status() ] );',
	'define( "NINO_PRIVATE_DIR", '. var_export( $sessionOkRoot, true ). ' );
	ini_set( "session.save_path", '. var_export( $sessionOkRoot. '/sessions', true ). ' );
	$_COOKIE[ session_name() ] = "0123456789abcdef0123456789abcdef";'
);

check( 'a session that can start is started as it always was', ( json_decode( $sessionOk['stdout'], true )['status'] ?? null ) === PHP_SESSION_ACTIVE );
check( '...and that boot logs nothing', glob( $sessionOkRoot. '/data/logs.*.php' ) === [] );

echo "\n";


// A month's log is one file, rewritten whole on every entry. A template with
// a broken shortcode raises one notice per view, so the file grew with the
// traffic - and every entry rewrote all of it under an exclusive lock, until
// the request that had to read a megabyte of php to add a line was the thing
// taking the site down
$logSandbox = $sandbox. '/logcap';
@mkdir( $logSandbox. '/data', 0777, true );
$logApp = [ './nino/uid' => 'logcap' ];
\Nino\AppData::prepare( $logApp );
$logApp['./nino/filesystem/path'] = $logSandbox;
$logApp['./nino/filesystem/contentpath'] = $logSandbox;
$logApp['./nino/filesystem/configpath'] = $logSandbox;
$logApp['/nino/error/log'] = true;
$logApp['/nino/error/display'] = false;

$record = new ReflectionMethod( '\Nino\Runtime', '_recordError' );
$record->setAccessible( true );

for( $i = 1; $i <= \Nino\Runtime::MAX_LOG_ENTRIES + 20; $i++ )
	$record->invokeArgs( null, [ &$logApp, [ 'type' => E_USER_NOTICE, 'message' => 'entry '. $i, 'file' => 'x.php', 'line' => $i ] ] );

$logged = \Nino\Filesystem::getFileContent( $logApp, '/data/logs.'. date( 'Y-m' ). '.php', [] );
check( 'a month\'s log stops growing at its cap', count( $logged ) === \Nino\Runtime::MAX_LOG_ENTRIES );
check( '...keeping the newest entries, which are the ones being read', ( end( $logged )['message'] ?? '' ) === 'entry '. ( \Nino\Runtime::MAX_LOG_ENTRIES + 20 )
	&& ( $logged[0]['message'] ?? '' ) === 'entry 21' );

echo "\n";


// --- Runtime::handleShutdown() - the fatals the error handler never sees ---

echo "Runtime::handleShutdown() - a fatal php never hands the handler still reaches the log\n";

/*	Regression: everything Nino offers for diagnosis hung off
	set_error_handler(), and php never calls that for the levels it raises
	and stops on. An exhausted memory limit, an expired max_execution_time
	and a compile-time fatal produced a bare 500 with an empty log and no
	effect from /nino/error/display - the failures that most need explaining
	were the ones that explained themselves least.

	Narrower than it sounds, and the reason these cases are the ones tested:
	on php 8.4 a parse error in a lazily autoloaded class and a call to a
	missing function are a ParseError and an Error, thrown objects
	handleException() has always caught. What is left is what is below.	*/
$shutdownBootstrap = static function( bool $log, bool $display, int $history = 0 ): string {
	return '
		$sandbox = sys_get_temp_dir(). "/nino-handleshutdown-". bin2hex( random_bytes( 4 ) );
		mkdir( $sandbox. "/private", 0755, true );
		$history = '. var_export( $history, true ). ';
		if( $history > 0 ) {
			mkdir( $sandbox. "/private/data", 0755, true );
			$seed = [];
			for( $i = 0; $i < $history; $i++ )
				$seed[] = [ "type" => E_WARNING, "message" => "an earlier warning, number ". $i. ", ". str_repeat( "y", 300 ), "file" => "/templates/page.tpl", "line" => $i, "date" => "2026-09-01 12:00:00" ];
			file_put_contents( $sandbox. "/private/data/logs.". date( "Y-m" ). ".php", "<?php return ". var_export( $seed, true ). ";" );
		}
		$appData = [ "./nino/uid" => $sandbox ];
		\Nino\AppData::prepare( $appData );
		$appData["./nino/filesystem/path"] = $sandbox;
		$appData["./nino/filesystem/configpath"] = $sandbox. "/private";
		$appData["./nino/filesystem/contentpath"] = $sandbox. "/private";
		$appData["./nino/filesystem/publicpath"] = $sandbox. "/public";
		$appData["/nino/error/log"] = '. var_export( $log, true ). ';
		$appData["/nino/error/display"] = '. var_export( $display, true ). ';
		\Nino\Runtime::init( $appData );
		// Announced after init(), not before it: output sent first means
		// headers_sent(), session_start() raises a plain E_WARNING about that,
		// and the handler ends the child on it long before its own fatal
		echo "sandbox:". $sandbox. "\n";
	';
};

/**
 *	Read back the month's log the child process wrote, and take its sandbox
 *	with it - the child announces the directory before it dies, which is the
 *	only way the parent can know where a randomly named sandbox went.
 *
 *	@param		array			$run			runIsolated() result
 *
 *	@return		array<int, array<string, mixed>>		The entries, or [] for no log at all
 */
function shutdownLogEntries( array $run ): array {

	if( preg_match( '/^sandbox:(.+)$/m', $run['stdout'], $match ) !== 1 )
		return [];

	$sandbox	= rtrim( $match[1] );
	$logs		= glob( $sandbox. '/private/data/logs.*.php' ) ?: [];
	$entries	= $logs === [] ? [] : ( include $logs[0] );

	\Nino\Filesystem::removeDir( $sandbox );

	return is_array( $entries ) === true ? $entries : [];
}

/*	The memory case, which is the one that does not work by simply asking
	error_get_last(): nothing is freed before a shutdown function runs, so
	_recordError() has to fit reading this month's log back in, appending
	to it and writing it out again into whatever the dying request left
	below the limit. What that is comes down to the block php just refused
	- it asked for one it could not have, and that much is still unused -
	which has nothing to do with what the write costs. handleShutdown()'s
	ini_set() is what decides whether either of these lands at all.	*/
$memoryFatal = runIsolated( $shutdownBootstrap( true, false ). '
	ini_set( "memory_limit", (string) ( memory_get_usage( true ) + 4 * 1024 * 1024 ) );
	$eat = [];
	while( true )
		$eat[] = str_repeat( "x", 1024 );
' );
$memoryEntries = shutdownLogEntries( $memoryFatal );

check( 'an exhausted memory limit is written to the log', count( $memoryEntries ) === 1
	&& ( $memoryEntries[0]['type'] ?? null ) === E_ERROR
	&& str_contains( (string) ( $memoryEntries[0]['message'] ?? '' ), 'Allowed memory size' ) === true );
check( '...with the file and line it died on, and the date it happened', ( $memoryEntries[0]['line'] ?? null ) > 0
	&& ( $memoryEntries[0]['file'] ?? '' ) !== ''
	&& ( $memoryEntries[0]['date'] ?? '' ) !== '' );

/*	The same failure against a log that has been collecting all month, which
	is the ordinary case and the one with no doubt left in it: what the entry
	costs is the size of that file, read back in and written out again, while
	what the request left behind is the size of the block that did not fit.
	Measured, a fatal refused 132 KiB of hash table against a 600 KiB log
	wrote nothing at all - and left the month's log intact, so not even a
	damaged file said that an entry had gone missing.	*/
$memoryWithHistory = runIsolated( $shutdownBootstrap( true, false, 1200 ). '
	ini_set( "memory_limit", (string) ( memory_get_usage( true ) + 4 * 1024 * 1024 ) );
	$eat = [];
	while( true )
		$eat[] = str_repeat( "x", 1024 );
' );
$historyEntries	= shutdownLogEntries( $memoryWithHistory );
$historyLast	= $historyEntries === [] ? [] : end( $historyEntries );

// A file seeded over the cap comes back under it on the next write (see
// MAX_LOG_ENTRIES), and the entry this run had to leave is the last one
check( '...and appended to a log that has been collecting all month, which costs more room than the request left', count( $historyEntries ) === \Nino\Runtime::MAX_LOG_ENTRIES
	&& ( $historyLast['type'] ?? null ) === E_ERROR
	&& str_contains( (string) ( $historyLast['message'] ?? '' ), 'Allowed memory size' ) === true );

// A compile-time fatal - the other kind php raises and stops on, and the one
// an autoloader can still walk into on a single route
$compileFatal = runIsolated( $shutdownBootstrap( true, false ). '
	eval( "class TwiceDeclared {}" );
	eval( "class TwiceDeclared {}" );
' );
$compileEntries = shutdownLogEntries( $compileFatal );

check( 'a compile-time fatal (a redeclared class) is written to the log', count( $compileEntries ) === 1
	&& ( $compileEntries[0]['type'] ?? null ) === E_COMPILE_ERROR );

// One entry per fatal, whatever is registered to report it: handleError()
// ends a fatal request with an exit(), and an exit() runs shutdown
// functions too, so both handlers are reached by the same failure. The
// outcome is what is pinned here - $_reported is what guarantees it, but
// not the only reason it holds today (see the property's own note)
$handledFatal = runIsolated( $shutdownBootstrap( true, false ). '
	trigger_error( "a real failure", E_USER_ERROR );
' );
$handledEntries = shutdownLogEntries( $handledFatal );

check( 'a fatal the error handler does see is logged once, not once per handler', count( $handledEntries ) === 1
	&& ( $handledEntries[0]['type'] ?? null ) === E_USER_ERROR );

// The same outcome where two handlers really are registered: a second
// Runtime::init() in one process adds a second handleShutdown(), and both
// are called with the same error_get_last() to read. $_reported is what
// makes one of them the reporter; without it the write the first one does
// happens to overwrite what the second would have found, which is luck
// rather than a rule
$twiceInitialised = runIsolated( $shutdownBootstrap( true, false ). '
	\Nino\Runtime::init( $appData );
	eval( "class TwiceDeclared {}" );
	eval( "class TwiceDeclared {}" );
' );
check( '...and once per fatal, not once per registered shutdown function', count( shutdownLogEntries( $twiceInitialised ) ) === 1 );

// The switch still governs: a shutdown that reports is one the site asked for
$memoryUnlogged = runIsolated( $shutdownBootstrap( false, false ). '
	ini_set( "memory_limit", (string) ( memory_get_usage( true ) + 4 * 1024 * 1024 ) );
	$eat = [];
	while( true )
		$eat[] = str_repeat( "x", 1024 );
' );
check( '...and nothing is logged when /nino/error/log is off', shutdownLogEntries( $memoryUnlogged ) === [] );

// And /nino/error/display reaches the fatals it never reached before. No
// backtrace with it: the stack this died on is gone by the time a shutdown
// function runs, and error_get_last() is all php kept
$memoryShown = runIsolated( $shutdownBootstrap( true, true ). '
	ini_set( "memory_limit", (string) ( memory_get_usage( true ) + 4 * 1024 * 1024 ) );
	$eat = [];
	while( true )
		$eat[] = str_repeat( "x", 1024 );
' );
check( 'a display-on install is shown the fatal instead of a bare 500', str_contains( $memoryShown['stdout'], 'Allowed memory size' ) === true
	&& count( shutdownLogEntries( $memoryShown ) ) === 1 );

// The quiet half: a request that ends normally must leave no trace at all,
// whatever warnings error_get_last() still holds from the way there
$noFatal = runIsolated( $shutdownBootstrap( true, false ). '
	@file_get_contents( $sandbox. "/definitely-not-there" );
	echo "done\n";
' );
check( 'a request that ends cleanly writes no entry, even after a suppressed warning', str_contains( $noFatal['stdout'], 'done' ) === true
	&& shutdownLogEntries( $noFatal ) === [] );

echo "\n";


// --- Filesystem's own I/O calls stay non-fatal under the real handler -----

echo "Filesystem - a real fopen()/mkdir()/unlink() failure returns false, doesn't 500\n";

// Regression: fopen()/fwrite()/fflush()/rename()/mkdir()/unlink() raise a
// plain E_WARNING on failure, which - same as the engine-raised warning
// above - is fatal under the real handler unless the call itself is @-
// silenced. Without @, the warning kills the request before the
// "=== false" checks right below each call ever run: _writeFile()'s "short
// write has to fail here" comment, lockFile()'s "could not lock" return,
// and writeContentData()'s own error message were all unreachable the same
// way the Newsletter catch blocks were - just one layer down, at the
// engine level instead of trigger_error()
$writeFailure = runIsolated( $bootstrap. '
	echo "before\n";
	$brokenBase = $sandbox. "/not-a-dir";
	file_put_contents( $brokenBase, "x" );
	$appData["./nino/filesystem/configpath"] = $brokenBase;
	$result = \Nino\Filesystem::putFileContent( $appData, "/config.php", [ "a" => 1 ] );
	echo "after:". var_export( $result, true );
' );
check( 'a real fopen() failure (configpath base is a file, not a dir) returns false instead of terminating the request', trim( $writeFailure['stdout'] ) === 'before' . "\n" . 'after:false' );

// forceDir()'s own is_dir() check can't be driven into the race from a
// single synchronous script (by definition: TOCTOU is the gap between two
// processes, not two lines in one) - this proves the underlying mechanism
// forceDir()'s @mkdir() relies on instead: mkdir() on a directory that
// exists because something else (another request) created it first
$mkdirRace = runIsolated( $bootstrap. '
	mkdir( $sandbox. "/already-there", 0755, true );
	echo "before\n";
	@mkdir( $sandbox. "/already-there", 0755, true );
	echo "after\n";
' );
check( '@mkdir() on a directory another request already created (forceDir()\'s TOCTOU gap) does not terminate the request', trim( $mkdirRace['stdout'] ) === "before\nafter" );

// The exact race RotatingLog::prune() (and removeDir(), Images::delete())
// guard against - two concurrent deletes reaching the same since-removed
// file - cannot be forced deterministically without actually forking a
// second process at the right instant; this proves the underlying
// mechanism their shared @unlink() relies on instead: unlink() on a file
// that is already gone by the time it runs
$unlinkRace = runIsolated( $bootstrap. '
	$f = $sandbox. "/already-gone.txt";
	file_put_contents( $f, "x" );
	unlink( $f );
	echo "before\n";
	@unlink( $f );
	echo "after\n";
' );
check( '@unlink() on a file another request already removed (the race prune()/removeDir()/Images::delete() all guard against) does not terminate the request', trim( $unlinkRace['stdout'] ) === "before\nafter" );

echo "\n";


// --- Filesystem::path - public vs private roots --------------------------

echo "Filesystem::path - which root a virtual path resolves against\n";

// Naming the split is the whole point of this: a webserver-facing directory
// and a never-served one are always separate paths.
$pathAppData = $appData;
$pathAppData['./nino/filesystem/path'] 				= '/srv/site';
$pathAppData['./nino/filesystem/configpath'] 	= '/srv/site/private';
$pathAppData['./nino/filesystem/contentpath'] = '/srv/site/private';
$pathAppData['./nino/filesystem/publicpath'] 	= '/srv/site/public';

foreach( [ '/images/hero.jpg', '/fonts/text.woff2', '/.cache/script.js' ] as $public )
	check( "$public stays on the public root", \Nino\Filesystem::path( $pathAppData, $public ) === '/srv/site/public'. $public );

/*	The one that reads like public content and is not. Nothing ever requests an
	/assets file - Modules\Assets reads them off disk and links only the bundle
	it writes into /.cache - so the sources sit with the templates and the text
	they belong to, and the webroot holds only what a browser actually loads.	*/
check( 'the asset sources are private, the bundle they build is not', \Nino\Filesystem::path( $pathAppData, '/assets/style.custom.css' ) === '/srv/site/private/assets/style.custom.css'
	&& \Nino\Filesystem::path( $pathAppData, '/.cache/style.css' ) === '/srv/site/public/.cache/style.css' );

check( 'tool code stays on the project root', \Nino\Filesystem::path( $pathAppData, '/_admin/x' ) === '/srv/site/_admin/x' );
check( 'the installed features resolve against Features::dir(), wherever that is', \Nino\Filesystem::path( $pathAppData, '/features/Sample/assets/x.css' ) === \Nino\Features::dir(). '/Sample/assets/x.css' && \Nino\Filesystem::path( $pathAppData, '/features' ) === \Nino\Features::dir() && \Nino\Filesystem::path( $pathAppData, '/featuresets/x' ) === '/srv/site/featuresets/x' );

foreach( \Nino\Filesystem::PRIVATE_DIRS as $private )
	check( "$private resolves against the private root", \Nino\Filesystem::path( $pathAppData, $private ) === '/srv/site/private'. $private );

check( 'a path under a private directory follows it', \Nino\Filesystem::path( $pathAppData, '/text/de_DE.php' ) === '/srv/site/private/text/de_DE.php' );

/*	The '..' rejection is documented as a layer under this whole class, and
	only the two content calls made it: every other door - the one that says
	whether a file is there, the one that builds a path for somebody else to
	read, the one that creates a directory, the one that takes a lock, the one
	that builds a url - resolved a traversal and handed it on. Every call site
	is still expected to validate its own input; this is what catches the one
	that forgot	*/
ninoWarnings();

$refused = [
	'path'				=> \Nino\Filesystem::path( $pathAppData, '/text/../../etc/passwd' ),
	'url'					=> \Nino\Filesystem::url( $pathAppData, '/images/../../etc/passwd' ),
	'fileExists'	=> \Nino\Filesystem::fileExists( $appData, '/text/../../etc/passwd' ),
	'lockFile'		=> \Nino\Filesystem::lockFile( $appData, '/data/../../escape.lock' ),
	'read'				=> \Nino\Filesystem::getFileContent( $appData, '/text/../../etc/passwd', 'the default' ),
	'write'				=> \Nino\Filesystem::putFileContent( $appData, '/data/../../escape.php', [ 'x' ] ),
	'mutate'			=> \Nino\Filesystem::mutate( $appData, '/data/../../escape.php', static fn( array $state ): array => [ 'x' ] ),
];

\Nino\Filesystem::forceDir( $appData, '/data/../../escape-dir' );
$traversalWarnings = ninoWarnings();

/*	A nul byte is the same rule with a sharper edge. Every i/o call in the
	class carries an @ so a failure comes back as false - but @ does not
	suppress an exception, and mkdir()/fopen()/rename()/glob() throw a
	ValueError for a path containing one. So where a '..' answered false, a
	nul was an uncaught 500, for any caller whose own allowlist let one
	through	*/
ninoWarnings();

$nulRefused = [
	'path'				=> \Nino\Filesystem::path( $pathAppData, "/text/de_DE\0.php" ),
	'url'					=> \Nino\Filesystem::url( $pathAppData, "/images/x\0.png" ),
	'fileExists'	=> \Nino\Filesystem::fileExists( $appData, "/text/de_DE\0.php" ),
	'lockFile'		=> \Nino\Filesystem::lockFile( $appData, "/data/x\0.lock" ),
	'read'				=> \Nino\Filesystem::getFileContent( $appData, "/text/de_DE\0.php", 'the default' ),
	'write'				=> \Nino\Filesystem::putFileContent( $appData, "/data/nul\0.php", [ 'x' ] ),
	'mutate'			=> \Nino\Filesystem::mutate( $appData, "/data/nul\0.php", static fn( array $state ): array => [ 'x' ] ),
];

\Nino\Filesystem::forceDir( $appData, "/data/nul\0dir" );
$nulWarnings = ninoWarnings();

check( 'a nul byte is refused at every door too, rather than thrown out of', $nulRefused === [
	'path' => '', 'url' => '', 'fileExists' => false, 'lockFile' => false, 'read' => 'the default', 'write' => false, 'mutate' => false,
] );

check( 'every door of the filesystem refuses a traversal, not just the two that read and write content', $refused === [
	'path' => '', 'url' => '', 'fileExists' => false, 'lockFile' => false, 'read' => 'the default', 'write' => false, 'mutate' => false,
] );
check( '...and says so, so a call site that forgot to validate is findable', count( $traversalWarnings ) >= 3 );
check( '...and the directory it refused does not exist', is_dir( dirname( $sandbox, 2 ). '/escape-dir' ) === false );
check( 'a directory merely starting with a private name does not', \Nino\Filesystem::path( $pathAppData, '/textures/x.png' ) === '/srv/site/textures/x.png' );
check( 'everything under /private follows the private root', \Nino\Filesystem::path( $pathAppData, '/private/.auth/pw.php' ) === '/srv/site/private/.auth/pw.php' );
check( 'the old /content prefix is not a private-path alias', \Nino\Filesystem::path( $pathAppData, '/content/.auth/pw.php' ) === '/srv/site/content/.auth/pw.php' );
check( 'the typo /privat prefix is not a private-path alias', \Nino\Filesystem::path( $pathAppData, '/privat/.auth/pw.php' ) === '/srv/site/privat/.auth/pw.php' );

// Moving the private root moves every private path with it, and nothing else
$pathAppData['./nino/filesystem/contentpath'] = '/var/nino-private';

check( 'config.php keeps following its own configpath, not the private root', \Nino\Filesystem::path( $pathAppData, '/config.php' ) === '/srv/site/private/config.php' );
check( 'a moved private root takes the templates with it', \Nino\Filesystem::path( $pathAppData, '/templates/page-home.tpl' ) === '/var/nino-private/templates/page-home.tpl' );
check( '...and text, elements and data', [
	\Nino\Filesystem::path( $pathAppData, '/text' ),
	\Nino\Filesystem::path( $pathAppData, '/elements' ),
	\Nino\Filesystem::path( $pathAppData, '/data' ),
] === [ '/var/nino-private/text', '/var/nino-private/elements', '/var/nino-private/data' ] );
check( '...and the asset sources, which are private content like the rest', \Nino\Filesystem::path( $pathAppData, '/assets/style.design.css' ) === '/var/nino-private/assets/style.design.css' );
/*	...and what is addressed through the '/private' prefix rather than as one
	of the PRIVATE_DIRS. Those were two keys resolved as two roots, so moving
	the private root moved the templates, the text and the data and left
	everything under the prefix - the recovery secret, the backups, the logs -
	where it had been. Nothing did move them apart in practice, because
	\Nino\init() wrote both from one value; there is one key now	*/
check( '...and everything addressed through the /private prefix, which used to stay behind', [
	\Nino\Filesystem::path( $pathAppData, '/private/.auth/pw.php' ),
	\Nino\Filesystem::path( $pathAppData, '/private/data/forms.php' ),
] === [ '/var/nino-private/.auth/pw.php', '/var/nino-private/data/forms.php' ] );
check( 'but leaves the public ones where the webserver reaches them', \Nino\Filesystem::path( $pathAppData, '/images/hero.jpg' ) === '/srv/site/public/images/hero.jpg' );

// config.php keeps its own, older override - it wins over the private root
$pathAppData['./nino/filesystem/configpath'] = '/etc/nino';
check( 'NINO_CONFIG_DIR still wins for config.php alone', \Nino\Filesystem::path( $pathAppData, '/config.php' ) === '/etc/nino/config.php' );

// --- and the url side, which has to split exactly the same way ----------
//
// A tool bundles its own login css into /_admin/.cache/ - that is code
// shipped with the tool, not this project's public content. Giving every
// /.cache path the public prefix pointed /_admin's stylesheet and login
// script at /public/_admin/.cache/... and left the login page unstyled
// with its script 404ing, which no unit assertion here noticed
$pathAppData['./nino/filesystem/publicpath'] = '/srv/site/public';
$pathAppData['/nino/dir'] = '';

check( 'public content is reached under the public prefix', \Nino\Filesystem::url( $pathAppData, '/images/hero.jpg' ) === '/public/images/hero.jpg' );
check( '...including the generated bundle', \Nino\Filesystem::url( $pathAppData, '/.cache/style.css' ) === '/public/.cache/style.css' );
check( "a tool's own bundle keeps resolving next to the tool", \Nino\Filesystem::url( $pathAppData, '/_admin/.cache/login.js' ) === '/_admin/.cache/login.js' );
check( '...and so does any other tool file', \Nino\Filesystem::url( $pathAppData, '/_admin/assets/script.js' ) === '/_admin/assets/script.js' );

/*	The two lists have to stay disjoint or path() answers whichever it tests
	first, and a directory that is private on disk but public by url is the
	worst of both: written where nothing serves it, linked where nothing is.	*/
check( 'nothing is on both sides of the split', array_intersect( \Nino\Filesystem::PRIVATE_DIRS, \Nino\Filesystem::PUBLIC_DIRS ) === [] );
// The asset sources are the one thing that looks public and is not - and the
// invariant that lets them be private is that no caller ever builds a url for
// one. If that ever changes, this is where it breaks first
check( 'an asset source has no public url to be reached under', \Nino\Filesystem::url( $pathAppData, '/assets/style.custom.css' ) === '/assets/style.custom.css' );

$pathAppData['/nino/dir'] = '/subdir';
check( 'a subdirectory install carries into both', [
	\Nino\Filesystem::url( $pathAppData, '/images/hero.jpg' ),
	\Nino\Filesystem::url( $pathAppData, '/_admin/assets/script.js' ),
] === [ '/subdir/public/images/hero.jpg', '/subdir/_admin/assets/script.js' ] );

echo "\n";


// --- A project served from a subdirectory ---------------------------------

echo "A subdirectory install - the router reads past the directory, every address the kernel writes carries it\n";

/*	Routes are keyed from the project's root ('GET://about'), and every
	address a page writes puts [[/nino/dir]] in front of one. The request
	path was looked up as it came in, though, so a site at example.com/shop/
	answered every one of its own addresses with the 404 page. Measured
	before the router read past the directory: with '/nino/dir' = '/sub',
	'/sub/page' answered 404 and '/page' 200	*/
$subAppData = $appData;
$subAppData['/nino/dir'] = '/sub';
$subAppData['/nino/http/routes'] = [
	'GET://'				=> [ 'uri' => '/home', 'body' => 'home' ],
	'GET://page'		=> [ 'uri' => '/page', 'body' => 'page', 'locale' => 'de_DE' ],
	'GET://en/page'	=> [ 'uri' => '/page', 'body' => 'page', 'locale' => 'en_US', 'navs' => [ 'main' => 5 ] ],
];
$subAppData['./nino/http/requests'] = [];

$subRequest = ninoVisit( $subAppData, '/sub/page' );
\Nino\Http::response( $subAppData, $subRequest );
check( 'the request path is read without the directory, and the route is found', $subRequest['/nino/http/request']['uri'] === '/page' && $subRequest['/nino/http/response']['uri'] === '/page' && $subRequest['/nino/http/response']['statusCode'] === 200 );
check( 'the directory alone is the root', ninoVisit( $subAppData, '/sub' )['/nino/http/request']['uri'] === '/' );
check( '...with a trailing slash too', ninoVisit( $subAppData, '/sub/' )['/nino/http/request']['uri'] === '/' );
check( 'a path that merely begins with the same letters is left as it is', ninoVisit( $subAppData, '/subway' )['/nino/http/request']['uri'] === '/subway' );
check( 'the query part is read as before', ninoVisit( $subAppData, '/sub/page?x=1' )['/nino/http/request']['query'] === [ 'x' => '1' ] );
check( 'a root install reads the path as it came', ninoVisit( $appData, '/page' )['/nino/http/request']['uri'] === '/page' );

// The route key a locale switch redirects to is project-relative too, and
// the browser is sent to the address, not the key
$switchRequest = ninoVisit( $subAppData, '/sub/page?lang=en_US' );
\Nino\Locales::switchFromQuery( $subAppData, $switchRequest, 'lang' );
check( 'a locale switch redirects under the directory', ( $switchRequest['/nino/http/response']['header']['Location'] ?? '' ) === '/sub/en/page' && $switchRequest['/nino/http/response']['statusCode'] === 302 );

// A menu entry names its page the way the routes do - and links to where
// the page is; what a hand-written line names elsewhere stays as written.
// The entry for the requested page is the active one, compared before the
// directory goes in front
$subAppData['./nino/http/requests'] = [];
ninoVisit( $subAppData, '/sub/page' );
$subMenu = \Nino\Modules\Navigation::doShortcode( $subAppData, [ 'content' => "/page:Page\n/other:Other\n//example.com/x:Elsewhere\n#top:Top" ] );
check( 'a menu entry links under the directory', str_contains( $subMenu, 'href="/sub/page"' ) === true && str_contains( $subMenu, 'href="/sub/other"' ) === true );
check( '...and the requested page is still the active entry', preg_match( '/href="\/sub\/page" class="nino-is-active"/', $subMenu ) === 1 );
check( '...while a protocol-relative url and a fragment are written as they stand', str_contains( $subMenu, 'href="//example.com/x"' ) === true && str_contains( $subMenu, 'href="#top"' ) === true );
\Nino\Html::addFills( $subAppData, [ '/_nino/webpage/page/name' => 'Page' ], 'en_US' );
\Nino\Locales::setCurrentLocale( $subAppData, 'en_US' );
check( 'a generated entry links under the directory too', str_contains( \Nino\Modules\Navigation::doShortcode( $subAppData, [ 'nav' => 'main' ] ), 'href="/sub/en/page"' ) === true );

/*	Where the directory comes from. config.php may name it; before config.php
	exists the wizard runs from that directory already and posts to an
	endpoint under it, so the entry script's own address is read: the part
	of SCRIPT_NAME that is not the script's path inside the project	*/
if( is_dir( $sandbox. '/_admin' ) === false )
	mkdir( $sandbox. '/_admin', 0777, true );
file_put_contents( $sandbox. '/_admin/index.php', '' );
file_put_contents( $sandbox. '/index.php', '' );
$entry = function( string $name, string $file ): array { return [ 'SCRIPT_NAME' => $name, 'SCRIPT_FILENAME' => $file ]; };

check( "the directory is what stands before the entry script's path inside the project", \Nino\Filesystem::deriveDir( $appData, $entry( '/shop/_admin/index.php', $sandbox. '/_admin/index.php' ) ) === '/shop' );
check( "...for the site's own index.php too", \Nino\Filesystem::deriveDir( $appData, $entry( '/shop/index.php', $sandbox. '/index.php' ) ) === '/shop' );
check( '...and two levels deep', \Nino\Filesystem::deriveDir( $appData, $entry( '/sites/shop/index.php', $sandbox. '/index.php' ) ) === '/sites/shop' );
check( 'a root install answers the empty directory', \Nino\Filesystem::deriveDir( $appData, $entry( '/_admin/index.php', $sandbox. '/_admin/index.php' ) ) === '' );
check( 'a script outside the project answers nothing', \Nino\Filesystem::deriveDir( $appData, $entry( '/shop/index.php', __FILE__ ) ) === '' );
check( 'a name that does not end in the script\'s path answers nothing', \Nino\Filesystem::deriveDir( $appData, $entry( '/shop/other.php', $sandbox. '/index.php' ) ) === '' );
check( 'a request without the pair answers nothing', \Nino\Filesystem::deriveDir( $appData, [] ) === '' );

$seedAppData = $subAppData;
$seedAppData['/nino/dir'] = '';
$seedRequest = \Nino\request( $seedAppData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/shop/page', 'REMOTE_ADDR' => '127.0.0.1', 'SCRIPT_NAME' => '/shop/index.php', 'SCRIPT_FILENAME' => $sandbox. '/index.php' ] );
check( 'a request seeds the directory config.php left empty, and is routed under it', $seedAppData['/nino/dir'] === '/shop' && $seedRequest['/nino/http/response']['uri'] === '/page' && $seedRequest['/nino/http/response']['statusCode'] === 200 );
$namedAppData = $subAppData;
$namedAppData['/nino/dir'] = '/named';
\Nino\request( $namedAppData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/named/page', 'REMOTE_ADDR' => '127.0.0.1', 'SCRIPT_NAME' => '/shop/index.php', 'SCRIPT_FILENAME' => $sandbox. '/index.php' ] );
check( 'a directory config.php names is kept over the derived one', $namedAppData['/nino/dir'] === '/named' );

/*	And the other half: nothing the kernel ships sends a browser to an address
	from the domain root. A bundled script writes the [[/nino/dir]] fill
	Modules\Assets substitutes, the two scripts a page loads as they are on
	disk (recovery.js, the wizard's) read Nino.dir, and a template writes
	the fill. The workbench's panels, its login, its logout, the recovery
	page and the wizard all posted to '/_admin/' or '/.nino/auth/...' as it
	stood - twenty-eight places, none of which a site at /shop reached	*/
$kernelRoot = dirname( __DIR__ );
$rootAbsoluteRequests = [];
foreach( array_merge( glob( $kernelRoot. '/_nino/*.js' ), glob( $kernelRoot. '/_admin/assets/*.js' ), glob( $kernelRoot. '/_admin/install/assets/*.js' ), glob( $kernelRoot. '/_admin/Nino/Modules/*/assets/*.js' ), glob( $kernelRoot. '/_nino/Nino/Modules/*/assets/*.js' ) ) as $shippedScript ) {
	$shippedSource = (string) file_get_contents( $shippedScript );
	// Over the whole file, not line by line: a call may put its first
	// argument on the next line, and Nino.js's own two did
	preg_match_all( '/\b(?:sendRequest|logout|replace|assign)\(\s*\'\/(?!\/)|\blogin\(\s*[^;]*?,\s*\'\/[^\']*\'\s*,\s*function/', $shippedSource, $rootAbsoluteHits, PREG_OFFSET_CAPTURE );
	foreach( $rootAbsoluteHits[0] as $hit )
		$rootAbsoluteRequests[] = substr( $shippedScript, strlen( $kernelRoot ) + 1 ). ':'. ( substr_count( $shippedSource, "\n", 0, $hit[1] ) + 1 );
}
check( 'no shipped script sends a request to, or a browser to, an address from the domain root'. ( $rootAbsoluteRequests === [] ? '' : ' - '. implode( ', ', $rootAbsoluteRequests ) ), $rootAbsoluteRequests === [] );

$rootAbsoluteTemplates = [];
foreach( array_merge( glob( $kernelRoot. '/_admin/templates/*.tpl' ), glob( $kernelRoot. '/_admin/install/templates/*.tpl' ), glob( $kernelRoot. '/_nino/Nino/Modules/*/templates/*.tpl' ) ) as $shippedTemplate )
	if( preg_match( '/\b(?:action|href|src)="\/(?!\/)/', (string) file_get_contents( $shippedTemplate ) ) === 1 )
		$rootAbsoluteTemplates[] = substr( $shippedTemplate, strlen( $kernelRoot ) + 1 );
check( 'no template the kernel ships writes an address from the domain root'. ( $rootAbsoluteTemplates === [] ? '' : ' - '. implode( ', ', $rootAbsoluteTemplates ) ), $rootAbsoluteTemplates === [] );

echo "\n";

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
