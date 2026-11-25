<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	feature-counter-smoke.php	The counters of \Nino\Feature::count(): a folder of files, one
 *												for each part of the clients, so that a try reads and writes one
 *												small file; at most SHARD_ROOM open entries in a file, and what
 *												happens when an attacker fills one; real processes at once.
 *
 *	Usage: php tests/feature-counter-smoke.php
 */

require __DIR__. '/harness.php';

/**
 *	A project directory booted as a request would boot it, for the workers
 *
 *	@param		string		$sandbox
 *
 *	@return		array
 */
function counterBoot( string $sandbox ): array {

	$appData = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $appData );
	$appData['./nino/filesystem/path']				= $sandbox;
	$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/publicpath']	= $sandbox. '/public';

	return $appData;
}

// Seed mode - the key of the installation as one process gets it, printed
if( ( $argv[1] ?? '' ) === 'seed' ) {

	$appData = counterBoot( $argv[2] );
	// The classes are loaded before the line is printed: what is left to do after it is reading the file and asking for the lock
	\Nino\Filesystem::getFileContent( $appData, '/data/.counters/seed.php', [] );
	\Nino\Filesystem::lockUsable( $appData, '/data/.counters/seed.php' );
	class_exists( '\Nino\Feature' );
	fwrite( STDOUT, "start\n" );
	fflush( STDOUT );
	echo (string) ( new ReflectionMethod( '\Nino\Feature', '_seed' ) )->invokeArgs( null, [ &$appData, true ] );
	exit( 0 );
}

// Worker mode - this same file once more, as a process of its own
if( ( $argv[1] ?? '' ) === 'worker' ) {

	for( $round = 0; $round < (int) $argv[4]; $round++ ) {
		$appData = counterBoot( $argv[2] );
		\Nino\Feature::count( $appData, 'workers', $argv[3], 1000, 3600 );
	}

	exit( 0 );
}

/**
 *	One call, with the errors it raised
 *
 *	@param		array 		&$appData
 *	@param		callable	$run
 *
 *	@return		array										[ answer, errors ]
 */
function counterCall( array &$appData, callable $run ): array {

	$answer	= null;
	$errors	= ninoErrors( function() use ( &$appData, $run, &$answer ) { $answer = $run( $appData ); } );

	return [ $answer, $errors ];
}

/**
 *	Every entry of a counter, whichever file it is in
 *
 *	@param		array 		&$appData
 *	@param		string		$name
 *
 *	@return		array
 */
function counterEntries( array &$appData, string $name ): array {

	$all = [];

	foreach( glob( \Nino\Filesystem::path( $appData, '/data/.counters/'. $name ). '/*.php' ) ?: [] as $file )
		$all += (array) include $file;

	return $all;
}

$appData	= ninoSandbox( 'feature-counter' );
$sandbox	= ninoSandboxDir( $appData );
$room			= ( new ReflectionClassConstant( '\Nino\Feature', 'SHARD_ROOM' ) )->getValue();
$count		= 16 ** ( new ReflectionClassConstant( '\Nino\Feature', 'SHARD_LENGTH' ) )->getValue();
$shardOf	= ( new ReflectionMethod( '\Nino\Feature', '_shardPath' ) );
$seedOf		= static fn( array &$app ): string => (string) \Nino\Filesystem::getFileContent( $app, '/data/.counters/seed.php', [] )['seed'];
$fileOf		= static fn( array &$app, string $name, string $key, ?string $seed = null ): string => (string) $shardOf->invoke( null, $name, hash( 'sha256', \Nino\Http::clientBucket( $key ) ), $seed ?? $seedOf( $app ) );

check( 'the numbers of the layout: 256 files of 512 open entries, which hold the 100,000 clients that the counter was measured with', $count === 256 && $room === 512 && $count * $room >= 100000 );

echo "\\Nino\\Feature::count() - the layout\n";

for( $i = 0; $i < 300; $i++ )
	\Nino\Feature::count( $appData, 'layout', 'client-'. $i, 5, 3600 );

$layoutFiles	= glob( \Nino\Filesystem::path( $appData, '/data/.counters/layout' ). '/*' ) ?: [];
$layoutNames	= array_map( 'basename', $layoutFiles );
$layoutRight	= true;
$layoutSeed		= $seedOf( $appData );

foreach( $layoutFiles as $layoutFile ) {
	$layoutIn = array_keys( (array) include $layoutFile );
	for( $i = 0; $i < 300; $i++ )
		if( in_array( hash( 'sha256', 'client-'. $i ), $layoutIn, true ) === true && \Nino\Filesystem::path( $appData, $fileOf( $appData, 'layout', 'client-'. $i, $layoutSeed ) ) !== $layoutFile )
			$layoutRight = false;
}

check( 'a counter is a folder of files named by two hex characters: 300 clients make more than one, and at most as many as the counter allows', count( $layoutFiles ) > 1 && count( $layoutFiles ) <= $count
	&& preg_grep( '/^[0-9a-f]{2}\.php$/D', $layoutNames ) === $layoutNames );
check( '...every client is in the file that its key chooses, and all of them are in one of the files', $layoutRight === true && count( counterEntries( $appData, 'layout' ) ) === 300 );
check( '...and no file of the earlier form is made', is_file( \Nino\Filesystem::path( $appData, '/data/layout.php' ) ) === false );

$layoutBefore = array_map( 'sha1_file', $layoutFiles );
\Nino\Feature::count( $appData, 'layout', 'client-7', 5, 3600 );
$layoutAfter = array_map( 'sha1_file', $layoutFiles );
check( 'a try writes the one file of its client: of all the files of the counter exactly one is not as it was', count( array_diff_assoc( $layoutAfter, $layoutBefore ) ) === 1 );

// A fresh request - nothing cached - that only asks reads one file of the counter
$layoutRead		= $appData;
unset( $layoutRead['./nino/filesystem/cache'] );
\Nino\Feature::spent( $layoutRead, 'layout', 'client-7', 5 );
$layoutCached	= array_keys( $layoutRead['./nino/filesystem/cache'] ?? [] );
check( 'spent() reads the file of its client and no other of the counter', count( preg_grep( '#^/data/\.counters/layout/#', $layoutCached ) ) === 1 );

echo "\nthe file of an earlier form\n";

\Nino\Filesystem::putFileContent( $appData, '/data/legacy.php', [ hash( 'sha256', 'old-client' ) => [ 'tries' => 99, 'reset' => time() + 3000 ] ] );
check( 'spent() does not read it', \Nino\Feature::spent( $appData, 'legacy', 'old-client', 5 ) === false && is_file( \Nino\Filesystem::path( $appData, '/data/legacy.php' ) ) === true );
check( 'the first count() starts the client from nothing, and the file is gone', \Nino\Feature::count( $appData, 'legacy', 'old-client', 5, 3600 ) === true
	&& is_file( \Nino\Filesystem::path( $appData, '/data/legacy.php' ) ) === false && counterEntries( $appData, 'legacy' )[ hash( 'sha256', 'old-client' ) ]['tries'] === 1 );

// A file of the name of a counter that is no counter is not touched: the counter is its folder, and the two do not disturb each other
$clashes = [
	'an index' => [ 'files' => [ 'a' => [ 'name' => 'x' ] ], 'next' => 3 ],
	'entries with more in them' => [ hash( 'sha256', 'k' ) => [ 'tries' => 1, 'reset' => time() + 60, 'name' => 'x' ] ],
	'a key that is no hash' => [ 'k' => [ 'tries' => 1, 'reset' => time() + 60 ] ],
	'something that is no array' => 'text',
];
$clashed = true;

foreach( array_values( $clashes ) as $clashIndex => $clashContent ) {
	$clashName = 'clash'. $clashIndex;
	\Nino\Filesystem::putFileContent( $appData, '/data/'. $clashName. '.php', $clashContent );
	$clashBefore = (string) file_get_contents( \Nino\Filesystem::path( $appData, '/data/'. $clashName. '.php' ) );
	[ $clashCounted, $clashErrors ]		= counterCall( $appData, static fn( array &$app ): bool => \Nino\Feature::count( $app, $clashName, 'x', 5, 60 ) );
	[ , $clashErrors2 ]								= counterCall( $appData, static fn( array &$app ): bool => \Nino\Feature::count( $app, $clashName, 'x', 5, 60 ) );

	if( $clashCounted !== true || (string) file_get_contents( \Nino\Filesystem::path( $appData, '/data/'. $clashName. '.php' ) ) !== $clashBefore || count( $clashErrors ) !== 1 || $clashErrors2 !== [] || str_contains( $clashErrors[0][1], 'data/'. $clashName. '.php' ) === false )
		$clashed = false;
}

check( 'a file of the name of a counter that is no counter - an index, entries with more in them, a key that is no hash, no array - is left as it is, the counter counts, and the file is named in a warning once', $clashed === true );

\Nino\Filesystem::putFileContent( $appData, '/data/emptyold.php', [] );
\Nino\Feature::count( $appData, 'emptyold', 'x', 5, 60 );
check( 'an empty file is a counter of the earlier form too, and goes', is_file( \Nino\Filesystem::path( $appData, '/data/emptyold.php' ) ) === false );

\Nino\Filesystem::putFileContent( $appData, '/data/legacy.php', [ hash( 'sha256', 'late' ) => [ 'tries' => 1, 'reset' => time() + 60 ] ] );
\Nino\Feature::count( $appData, 'legacy', 'old-client', 5, 3600 );
check( 'the file of an earlier form is removed where the folder is made, and not by a later try: one that comes back stays, read by nothing', is_file( \Nino\Filesystem::path( $appData, '/data/legacy.php' ) ) === true );

echo "\nthe key of the installation\n";

$seedFile	= \Nino\Filesystem::path( $appData, '/data/.counters/seed.php' );
$seedOne	= $seedOf( $appData );
\Nino\Feature::count( $appData, 'layout', 'client-8', 5, 3600 );
check( 'it is 64 hex characters, made once and kept by every later try', preg_match( '/^[0-9a-f]{64}$/D', $seedOne ) === 1 && $seedOf( $appData ) === $seedOne );

$seedMoved = 0;

for( $i = 0; $i < 64; $i++ )
	if( $fileOf( $appData, 'layout', 'client-'. $i ) !== $fileOf( $appData, 'layout', 'client-'. $i, str_repeat( 'a', 64 ) ) )
		$seedMoved++;

check( 'the file of a client depends on it: another key puts the clients into other files', $seedMoved > 32 );

\Nino\Filesystem::putFileContent( $appData, '/data/.counters/seed.php', 'broken' );
check( 'a key that is anything else is made again, and the counter goes on', \Nino\Feature::count( $appData, 'afterseed', 'x', 5, 3600 ) === true && preg_match( '/^[0-9a-f]{64}$/D', $seedOf( $appData ) ) === 1 );
file_put_contents( $seedFile, '<?php this does not parse' );
clearstatcache();
check( '...also one that does not parse', \Nino\Feature::count( $appData, 'afterseed', 'y', 5, 3600 ) === true && preg_match( '/^[0-9a-f]{64}$/D', $seedOf( $appData ) ) === 1 );
check( 'the key is readable by its owner alone: 0600', ( fileperms( $seedFile ) & 0777 ) === 0600 );

unlink( $seedFile );
unset( $appData['./nino/filesystem/cache'] );
\Nino\Feature::spent( $appData, 'nothing', 'x', 5 );
\Nino\Feature::forget( $appData, 'nothing', 'x' );
check( 'spent() and forget() of a counter that was never tried make no key and no folder for it', file_exists( $sandbox. '/private/data/.counters/nothing' ) === false && file_exists( $seedFile ) === false );
\Nino\Feature::count( $appData, 'nothing', 'x', 0, 60 );
\Nino\Feature::spent( $appData, 'nothing', 'x', 0 );
check( 'a limit of 0 touches nothing: no key is made for it', file_exists( $seedFile ) === false && file_exists( $sandbox. '/private/data/.counters/nothing' ) === false );

mkdir( $seedFile );
[ $nokeyCounted, $nokeyErrors ] = counterCall( $appData, static fn( array &$app ): bool => \Nino\Feature::count( $app, 'nokey', 'x', 5, 60 ) );
check( 'a key that cannot be made says why in a warning: who owns the folder and the key, and who runs the request', count( $nokeyErrors ) === 1 && str_contains( $nokeyErrors[0][1], 'owner' ) === true && str_contains( $nokeyErrors[0][1], 'runs as user' ) === true );
check( 'a key that cannot be made is a counter that cannot count: false, spent() false, and no folder for the counter', $nokeyCounted === false && \Nino\Feature::spent( $appData, 'nokey', 'x', 5 ) === false
	&& file_exists( $sandbox. '/private/data/.counters/nokey' ) === false );
rmdir( $seedFile );
check( '...and it counts again where it can be made', \Nino\Feature::count( $appData, 'nokey', 'x', 5, 60 ) === true && preg_match( '/^[0-9a-f]{64}$/D', $seedOf( $appData ) ) === 1 );

\Nino\Feature::count( $appData, 'blocked', 'x', 5, 60 );
mkdir( \Nino\Filesystem::path( $appData, $fileOf( $appData, 'blocked', 'y' ) ) );
$blockedKey = 'y';
[ $blockedCounted, $blockedErrors ] = counterCall( $appData, static fn( array &$app ): bool => \Nino\Feature::count( $app, 'blocked', 'y', 5, 60 ) );
rmdir( \Nino\Filesystem::path( $appData, $fileOf( $appData, 'blocked', 'y' ) ) );
check( 'a file of a counter that cannot be written is a try that is turned away, with a warning that says who owns what and who runs the request', $blockedCounted === false && count( $blockedErrors ) === 1 && str_contains( $blockedErrors[0][1], 'cannot be written' ) === true && str_contains( $blockedErrors[0][1], 'owner' ) === true );

echo "\na file that is full\n";

$full		= 'crowded';
\Nino\Feature::count( $appData, $full, 'victim-0', 2, 3600 );
$seed		= $seedOf( $appData );
$target	= $fileOf( $appData, $full, 'victim-0', $seed );
$mates	= [];

for( $i = 0; count( $mates ) < $room - 1; $i++ )
	if( $fileOf( $appData, $full, 'mate-'. $i, $seed ) === $target )
		$mates[] = 'mate-'. $i;

$open			= [];
foreach( $mates as $mate )
	$open[ hash( 'sha256', $mate ) ] = [ 'tries' => 1, 'reset' => time() + 3000 ];
// SHARD_ROOM open entries in all: the one of the first client is among them, so that it is a client that is in
$open[ hash( 'sha256', 'victim-0' ) ] = [ 'tries' => 1, 'reset' => time() + 3000 ];
\Nino\Filesystem::putFileContent( $appData, $target, $open );
$fullBytes = (string) file_get_contents( \Nino\Filesystem::path( $appData, $target ) );

$newcomer	= 'newcomer';

for( $i = 0; $fileOf( $appData, $full, $newcomer, $seed ) !== $target; $i++ )
	$newcomer = 'newcomer-'. $i;

[ $fullCounted, $fullErrors ]	= counterCall( $appData, static fn( array &$app ): bool => \Nino\Feature::count( $app, 'crowded', $newcomer, 5, 3600 ) );
[ $fullAgain, $fullErrors2 ]	= counterCall( $appData, static fn( array &$app ): bool => \Nino\Feature::count( $app, 'crowded', $newcomer, 5, 3600 ) );
check( 'a client with no entry whose file holds SHARD_ROOM open ones is turned away: false, and spent() says the same where count() would', $fullCounted === false && $fullAgain === false && \Nino\Feature::spent( $appData, $full, $newcomer, 5 ) === true );
check( '...nothing is written for it, and no entry of the others is forgotten to make room', (string) file_get_contents( \Nino\Filesystem::path( $appData, $target ) ) === $fullBytes && count( (array) include \Nino\Filesystem::path( $appData, $target ) ) === $room );
check( '...and it is said once, as a warning that names the folder of the counter - the way out of it - however often a client is turned away', count( $fullErrors ) === 1 && $fullErrors[0][0] === E_USER_WARNING && str_contains( $fullErrors[0][1], 'data/.counters/'. $full. '/' ) === true && $fullErrors2 === [] );

check( 'a client that is in goes on counting in a full file, up to its limit: true, then false', \Nino\Feature::count( $appData, $full, 'victim-0', 2, 3600 ) === true && \Nino\Feature::count( $appData, $full, 'victim-0', 2, 3600 ) === false
	&& \Nino\Feature::spent( $appData, $full, 'victim-0', 2 ) === true );

$otherKey = 'elsewhere';

for( $i = 0; $fileOf( $appData, $full, $otherKey, $seed ) === $target; $i++ )
	$otherKey = 'elsewhere-'. $i;

check( 'a client of another file is not touched by it', \Nino\Feature::count( $appData, $full, $otherKey, 5, 3600 ) === true && \Nino\Feature::spent( $appData, $full, $otherKey, 5 ) === false );

$open = (array) include \Nino\Filesystem::path( $appData, $target );
$open[ hash( 'sha256', $mates[0] ) ]['reset'] = time() - 1;
\Nino\Filesystem::putFileContent( $appData, $target, $open );
check( 'an entry whose window runs out makes room: the client that was turned away is counted, and the file is back at SHARD_ROOM', \Nino\Feature::spent( $appData, $full, $newcomer, 5 ) === false
	&& \Nino\Feature::count( $appData, $full, $newcomer, 5, 3600 ) === true && count( (array) include \Nino\Filesystem::path( $appData, $target ) ) === $room );

$late = 'late';

for( $i = 0; $fileOf( $appData, $full, $late, $seed ) !== $target; $i++ )
	$late = 'late-'. $i;

check( 'the file is full again: the next one is turned away', \Nino\Feature::count( $appData, $full, $late, 5, 3600 ) === false );
\Nino\Feature::forget( $appData, $full, $mates[1] );
check( 'forget() makes room as well', \Nino\Feature::count( $appData, $full, $late, 5, 3600 ) === true );

echo "\ndifferent files do not wait for each other\n";

$held			= $fileOf( $appData, 'held', 'holder', $seed );
$holder		= $appData;
$heldKey	= 'passer';

for( $i = 0; $fileOf( $appData, 'held', $heldKey, $seed ) === $held; $i++ )
	$heldKey = 'passer-'. $i;

\Nino\Feature::count( $appData, 'held', 'holder', 5, 3600 );
$taken = \Nino\Filesystem::lockFile( $holder, $held );
$other = \Nino\Filesystem::lockFile( $appData, $held, false );
check( 'the lock of one file of a counter is the lock of that file: it is held, and another handle is refused', $taken === true && $other === false );
check( '...and a try of a client of another file goes through while it is held', \Nino\Feature::count( $appData, 'held', $heldKey, 5, 3600 ) === true );
\Nino\Filesystem::unlockFile( $holder, $held );

echo "\nprocesses at once\n";

$workers = [];

foreach( [ 'same', 'same', 'same', 'same' ] as $worker )
	$workers[] = proc_open( [ PHP_BINARY, __FILE__, 'worker', $sandbox, $worker, '25' ], [ 1 => [ 'file', '/dev/null', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ], $pipes );

foreach( $workers as $worker )
	if( is_resource( $worker ) === true )
		proc_close( $worker );

check( 'four processes that try for the same client 25 times each count 100', counterEntries( $appData, 'workers' )[ hash( 'sha256', 'same' ) ]['tries'] === 100 );

echo "\nthe first key, made while another process waits\n";

/*	A process meets an installation that has no key while another is making it. The test is the other: it holds the lock of the key, as the process that
	makes it does, and the process of its own is let start - it prints a line, and then reads the file, finds nothing, and waits at the lock. The test makes the
	key, as that process would, and lets go; the one that waited has to look again under the lock and find it, not make another. The line is the only
	sign that it is on its way, and the making of the key takes more than the few statements it needs to read the file	*/
$race			= $sandbox. '/race';
$raceApp	= counterBoot( $race );
\Nino\Filesystem::lockFile( $raceApp, '/data/.counters/seed.php' );
$racer		= proc_open( [ PHP_BINARY, __FILE__, 'seed', $race ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ], $racePipes );
$raceLine	= fgets( $racePipes[1] );
$raceMade	= str_repeat( 'c', 64 );
\Nino\Filesystem::putFileContent( $raceApp, '/data/.counters/seed.php', [ 'seed' => $raceMade ], true, 0600 );
$raceSeen	= trim( (string) stream_get_contents( $racePipes[1] ) );
proc_close( $racer );
check( 'a process that found no key and waits at its lock finds the key that was made meanwhile and takes it, and the key in the file is that one', $raceLine === "start\n" && $raceSeen === $raceMade
	&& (string) ( include \Nino\Filesystem::path( $raceApp, '/data/.counters/seed.php' ) )['seed'] === $raceMade );

echo "\nthe backup\n";

$manifest = \Nino\Backup::manifest( $appData );
check( 'the folder of the counters is in no backup: no archive name and no path of the manifest is below it', preg_grep( '#\.counters#', array_merge( array_values( $manifest ), array_keys( $manifest ) ) ) === [] );
check( '...whatever a manifest of a feature names below /data/: a file of the folder is transient', ( new ReflectionMethod( '\Nino\Backup', '_transient' ) )->invoke( null, '.counters/layout/ab.php' ) === true
	&& ( new ReflectionMethod( '\Nino\Backup', '_transient' ) )->invoke( null, '.counters/seed.php' ) === true );

echo "\nthe name of a counter\n";

$names = [];

foreach( [ '../x', 'a/b', '.counters', "x\0", 'Seed', 'seed.php' ] as $name ) {
	[ $named, $namedErrors ] = counterCall( $appData, static fn( array &$app ): bool => \Nino\Feature::count( $app, $name, 'k', 5, 60 ) );
	if( $named !== false || count( $namedErrors ) !== 1 )
		$names[] = $name;
}

check( 'a name that is no slug is no folder: refused with a warning, whatever it would reach', $names === [] && glob( $sandbox. '/private/data/.counters/*', GLOB_ONLYDIR ) !== false
	&& is_dir( $sandbox. '/private/data/x' ) === false && is_dir( $sandbox. '/private/x' ) === false );

ninoDone( $appData );
