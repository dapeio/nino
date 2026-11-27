<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	appdata-runtime-keys-smoke.php	Dependency-free smoke test for the two kinds of keys of the app data: a key that
 *											begins with './' is of the running request alone, so config.php gives none to a
 *											request and none is written to it - what a file written by hand or carried by
 *											the archive of a restore holds is not read, and the kernel's own runtime keys
 *											stand. Every boot is made from the disk, as a request makes it. Runs in
 *											isolated sandbox directories, never touches the real project data.
 *
 *	Usage: php tests/appdata-runtime-keys-smoke.php
 */

require __DIR__. '/harness.php';

/** A request's boot from the disk of a sandbox, in the order of \Nino\init(): what the kernel sets, the session keys, the filesystem, config.php */
function runtimeKeysBoot( array $like ): array {

	$sandbox = ninoSandboxDir( $like );

	$appData = [
		'./nino/uid'										=> $sandbox,
		'./nino/filesystem/path'				=> $sandbox,
		'./nino/filesystem/configpath'	=> $sandbox. '/private',
		'./nino/filesystem/contentpath'	=> $sandbox. '/private',
		'./nino/filesystem/publicpath'	=> $sandbox. '/public',
	];

	\Nino\AppData::prepare( $appData );
	\Nino\AppData::prepareSession( $appData );
	\Nino\Filesystem::init( $appData );
	\Nino\AppData::init( $appData );

	return $appData;
}

/** config.php as it is on the disk, read in a process of its own so that no cache can answer */
function runtimeKeysFile( array $like ): array {

	return (array) ( static fn( string $path ): mixed => include $path )( ninoSandboxDir( $like ). '/private/config.php' );
}

/** The warnings of this suite's own subject, with what else the kernel said left out */
function runtimeKeysSaid(): array {

	return array_values( array_filter( ninoWarnings(), static fn( string $message ): bool => str_starts_with( $message, 'AppData::' ) ) );
}

$like			= ninoSandbox( 'runtime-keys' );
$sandbox	= ninoSandboxDir( $like );
$file			= $sandbox. '/private/config.php';

mkdir( $sandbox. '/private', 0755, true );
mkdir( $sandbox. '/public', 0755, true );

echo "Sandbox: $sandbox\n\n";


// --- Reading: a file that carries keys of the running request ----------------------------------------------

echo "Reading\n";

$stored = [
	'/nino/error/display'		=> true,
	'/nino/locales/native'	=> 'de_DE',
	'/nino/locales/available'	=> [ 'de_DE', 'en_US' ],
	'/nino/cache/ttl'				=> 7,
	'/project/kept'					=> 'a project key',
	'/project/nested'				=> [ './kept/inside' => 'nested', 'plain' => 1 ],
	'/nino/http/routes'			=> [ 'GET://hello' => [ 'uri' => '/hello', 'body' => 'hello from the file' ] ],
	'./probe/x'							=> 'secret-value-of-x',
	'./nino/backups/memory'	=> 1,
	'./nino/uid'						=> 'forged-uid',
	'./nino/filesystem/contentpath'	=> '/forged/content',
];

file_put_contents( $file, '<?php return '. var_export( $stored, true ). ';' );

$booted	= runtimeKeysBoot( $like );
$said		= runtimeKeysSaid();

check( 'a key of the file that begins with "./" is not in the app data of the request', array_key_exists( './probe/x', $booted ) === false );
check( '...the memory of a backup is not the file\'s', array_key_exists( './nino/backups/memory', $booted ) === false );
check( '...the key of the installation is the kernel\'s own, not the file\'s', $booted['./nino/uid'] === $sandbox );
check( '...and so is the path of the private directory the kernel set before it read the file', $booted['./nino/filesystem/contentpath'] === $sandbox. '/private' );
check( 'every key of the file that begins with "/" is read as before, over the defaults', $booted['/project/kept'] === 'a project key' && $booted['/nino/cache/ttl'] === 7
	&& $booted['/nino/cache/status'] === false && $booted['/nino/http/routes']['GET://hello']['body'] === 'hello from the file' );
check( 'a key that begins with "./" inside an array is data like any other and stays', $booted['/project/nested'] === [ './kept/inside' => 'nested', 'plain' => 1 ] );
check( 'the warning is one, and names the keys', count( $said ) === 1 && str_contains( $said[0], './probe/x' ) && str_contains( $said[0], './nino/backups/memory' ) && str_contains( $said[0], './nino/uid' ) && str_contains( $said[0], './nino/filesystem/contentpath' ) );
check( '...and no value: a name says what is wrong, a value may be a secret', str_contains( $said[0] ?? '', 'secret-value-of-x' ) === false && str_contains( $said[0] ?? '', 'forged' ) === false );

runtimeKeysBoot( $like );
check( 'the next boot within the hour is silent: one line of the log for a file that stays as it is', runtimeKeysSaid() === [] );

$visit = \Nino\request( $booted, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/hello', 'REMOTE_ADDR' => '127.0.0.1' ] );
check( 'the site answers: the route of the file is found and answered with a 200', ( $visit['/nino/http/response']['uri'] ?? '' ) === '/hello' && ( $visit['/nino/http/response']['statusCode'] ?? 0 ) === 200 );

$booted['/nino/modules'] = [];
$booted['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $booted );
ninoUser( $booted, 'dev@example.com' );
[ $status, $body ] = ninoCall( $booted, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
check( 'the workbench answers: the list of the archives is asked and given', $status === 200 && is_array( $body['archives'] ?? null ) === true );

check( 'a file with no such key is read without a word', ( static function() use ( $like, $file ): bool {
	file_put_contents( $file, '<?php return '. var_export( [ '/project/kept' => 'a project key' ], true ). ';' );
	$app = runtimeKeysBoot( $like );
	return runtimeKeysSaid() === [] && $app['/project/kept'] === 'a project key';
} )() );


// --- Writing: a key asked for that begins with "./" ----------------------------------------------------------

echo "\nWriting\n";

file_put_contents( $file, '<?php return '. var_export( [ '/project/nested' => [ './kept/inside' => 'nested' ], '/project/other' => 'as it was' ], true ). ';' );
$app = runtimeKeysBoot( $like );
runtimeKeysSaid();

$app['./probe/y']			= 'value-of-y-not-for-the-log';
$app['/project/y']		= 'stored';
$app['./probe/z']			= 'another';
$answer = \Nino\AppData::writeContentData( $app, [ './probe/y', '/project/y', './probe/z', '/project/nested' ] );
$said = runtimeKeysSaid();
$onDisk = runtimeKeysFile( $like );

check( 'a key of the request alone is not in the file, the keys with "/" are', array_key_exists( './probe/y', $onDisk ) === false && array_key_exists( './probe/z', $onDisk ) === false && ( $onDisk['/project/y'] ?? null ) === 'stored' );
check( '...the answer is the one of every write that was made: true', $answer === true );
check( '...the key stays in memory for the request', $app['./probe/y'] === 'value-of-y-not-for-the-log' && $app['./probe/z'] === 'another' );
check( '...a warning names the keys and none of their values', count( $said ) === 1 && str_contains( $said[0], './probe/y' ) && str_contains( $said[0], './probe/z' ) && str_contains( $said[0], 'value-of-y' ) === false );
check( '...a key inside an array that begins with "./" is written with the array', ( $onDisk['/project/nested'] ?? null ) === [ './kept/inside' => 'nested' ] && ( $onDisk['/project/other'] ?? null ) === 'as it was' );

\Nino\AppData::writeContentData( $app, [ '/project/y' ] );
check( 'a write that asks for no such key says nothing', runtimeKeysSaid() === [] );

// A key the file holds already
$handWritten = [ '/project/kept' => 'a project key', '/project/list' => [ 'b', 'a' ], './probe/hand' => 'by hand', './nino/backups/memory' => 1, '/project/nested' => [ './kept/inside' => 'nested' ] ];
file_put_contents( $file, '<?php return '. var_export( $handWritten, true ). ';' );
$app = runtimeKeysBoot( $like );
runtimeKeysSaid();
$app['/project/other'] = 'new';
\Nino\AppData::writeContentData( $app, [ '/project/other' ] );
$onDisk = runtimeKeysFile( $like );
$expected = $handWritten;
unset( $expected['./probe/hand'], $expected['./nino/backups/memory'] );
$expected['/project/other'] = 'new';

check( 'a key of the running request that the file holds goes with the next write of the file', array_key_exists( './probe/hand', $onDisk ) === false && array_key_exists( './nino/backups/memory', $onDisk ) === false );
check( '...every other key stands as the file had it', $onDisk === $expected );
check( '...and the write of a key that was asked for says nothing of it', runtimeKeysSaid() === [] );

// A file without any: the bytes
$plain = [ '/nino/modules' => [ '\\Nino\\Modules\\Form' ], '/project/text' => "line\n'quoted'", '/nino/auth/roles' => [ 'a' => [ 'x' => true ] ], '/project/nested' => [ './kept/inside' => 'nested' ] ];
file_put_contents( $file, '<?php return '. var_export( $plain, true ). ';' );
$app = runtimeKeysBoot( $like );
$app['/project/added'] = [ 1, 2, 3 ];
\Nino\AppData::writeContentData( $app, [ '/project/added', '/project/text' ] );
$reference = ninoSandbox( 'runtime-keys-reference' );
mkdir( ninoSandboxDir( $reference ). '/private', 0755, true );
\Nino\Filesystem::putFileContent( $reference, '/config.php', array_merge( $plain, [ '/project/added' => [ 1, 2, 3 ] ] ) );
check( 'a config.php without such keys is written as the filesystem writes the same data, byte for byte', file_get_contents( $file ) === file_get_contents( ninoSandboxDir( $reference ). '/private/config.php' ) && runtimeKeysSaid() === [] );
\Nino\Filesystem::removeDir( ninoSandboxDir( $reference ) );


// A write that names nothing but keys of the running request leaves the file as it is
$handFile = [ '/project/kept' => 'a project key', './probe/hand' => 'by hand' ];
file_put_contents( $file, '<?php return '. var_export( $handFile, true ). ';' );
$app = runtimeKeysBoot( $like );
runtimeKeysSaid();
$app['./probe/only'] = 'x';
clearstatcache();
$before = [ fileinode( $file ), filemtime( $file ), filesize( $file ), sha1_file( $file ) ];
\Nino\AppData::writeContentData( $app, [ './probe/only' ] );
clearstatcache();
$said = runtimeKeysSaid();
check( 'a write that names nothing but a key of the running request does not touch the file: the same file, the same bytes, the hand-written key still in it', $before === [ fileinode( $file ), filemtime( $file ), filesize( $file ), sha1_file( $file ) ] && ( runtimeKeysFile( $like )['./probe/hand'] ?? null ) === 'by hand' );
check( '...and it says so, with the name', count( $said ) === 1 && str_contains( $said[0], './probe/only' ) );
$app['/project/other'] = 'written';
\Nino\AppData::writeContentData( $app, [ './probe/only', '/project/other' ] );
runtimeKeysSaid();
check( '...the next write with a key of its own takes the hand-written key out', runtimeKeysFile( $like ) === [ '/project/kept' => 'a project key', '/project/other' => 'written' ] );

// The warning of a write is said when the lock of config.php is given back
$app['./probe/late'] = 'x';
$lockedWhenSaid = [];
set_error_handler( static function( int $level, string $message ) use ( &$app, &$lockedWhenSaid ): bool {
	if( str_starts_with( $message, 'AppData::writeContentData()' ) === true )
		$lockedWhenSaid[] = count( array_filter( (array) ( $app['./nino/filesystem/locks'] ?? [] ), 'is_resource' ) );
	return true;
} );
\Nino\AppData::writeContentData( $app, [ './probe/late', '/project/other' ] );
restore_error_handler();
check( 'the warning of a write is raised once the lock of config.php is given back', $lockedWhenSaid === [ 0 ] );

// Keys that only look like keys of the running request
$like2 = [ '/x/./y' => 1, '.x' => 2, '../x' => 3, ' ./x' => 4, '/project/kept' => 5 ];
file_put_contents( $file, '<?php return '. var_export( $like2, true ). ';' );
$app = runtimeKeysBoot( $like );
check( 'a top-level key that does not begin with "./" is a key like any other: "/x/./y", ".x", "../x" and " ./x" are read, and nothing is said', $app['/x/./y'] === 1 && $app['.x'] === 2 && $app['../x'] === 3 && $app[' ./x'] === 4 && runtimeKeysSaid() === [] );
$app['/project/kept'] = 6;
$app['.x'] = 7;
\Nino\AppData::writeContentData( $app, [ '/project/kept', '.x', '../x', ' ./x' ] );
check( '...and written as they are, the others of the file kept', runtimeKeysFile( $like ) === [ '/x/./y' => 1, '.x' => 7, '../x' => 3, ' ./x' => 4, '/project/kept' => 6 ] && runtimeKeysSaid() === [] );

// The names in the warning
$many = [ '/project/kept' => 1, "./a\x01b\nc" => 'v' ];
for( $i = 1; $i <= 12; $i++ )
	$many[ sprintf( './k%02d', $i ) ] = 'v';
file_put_contents( $file, '<?php return '. var_export( $many, true ). ';' );
runtimeKeysBoot( $like );
$said = runtimeKeysSaid();
check( 'a name is cleaned of what is no printable character, and only the first ten are listed, the rest counted', count( $said ) === 1 && str_contains( $said[0], './a?b?c' ) && preg_match( '/[\x00-\x08\x0a-\x1f]/', $said[0] ) === 0
	&& str_contains( $said[0], './k09' ) && str_contains( $said[0], './k10' ) === false && str_contains( $said[0], 'and 3 more' ) && str_contains( $said[0], '13 keys that begin' ) );

// Once an hour costs one write: the hour is asked without a lock and without a write
$counted = static function() use ( $like ): array {
	$files = [];
	foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ninoSandboxDir( $like ). '/private/data', FilesystemIterator::SKIP_DOTS ) ) as $entry )
		if( $entry->isFile() === true && str_contains( $entry->getPathname(), '/logs.' ) === false )
			$files[ $entry->getPathname() ] = [ $entry->getInode(), $entry->getMTime(), $entry->getSize(), sha1_file( $entry->getPathname() ) ];
	ksort( $files );
	return $files;
};
file_put_contents( $file, '<?php return '. var_export( [ '/project/kept' => 1, './probe/hourly' => 'v' ], true ). ';' );
$app = runtimeKeysBoot( $like );
runtimeKeysSaid();
clearstatcache();
$first = $counted();
$triesFirst = ninoCounter( $app, 'config-runtime-keys' );
usleep( 1100000 );
runtimeKeysBoot( $like );
runtimeKeysBoot( $like );
clearstatcache();
check( 'with a key of the running request in config.php the second and every later request of the hour writes no file under data/ and counts no try', $counted() === $first && ninoCounter( $app, 'config-runtime-keys' ) === $triesFirst && count( $triesFirst ) > 0 && runtimeKeysSaid() === [] );

// Where the counters cannot be kept nothing is said, and nothing of the counter either
$broken = ninoSandbox( 'runtime-keys-broken' );
$brokenDir = ninoSandboxDir( $broken );
mkdir( $brokenDir. '/private', 0755, true );
mkdir( $brokenDir. '/public', 0755, true );
file_put_contents( $brokenDir. '/private/data', 'a file where the folder of the data should be' );
file_put_contents( $brokenDir. '/private/config.php', '<?php return '. var_export( [ '/nino/error/display' => true, './probe/x' => 'v' ], true ). ';' );
ninoWarnings();
$app = runtimeKeysBoot( $broken );
check( 'where the counters cannot be read or written the key is left out and no warning is raised - none of AppData, none of a counter', array_key_exists( './probe/x', $app ) === false && ninoWarnings() === [] );
\Nino\Filesystem::removeDir( $brokenDir );


// --- A unit's config is no stored file that gives a key of the running request --------------------------------

echo "\nUnit\n";

$unitDir = $sandbox. '/unit';
mkdir( $unitDir, 0755, true );
file_put_contents( $unitDir. '/manifest.php', '<?php return '. var_export( [ 'label' => 'Probe', 'config' => [ '/probe/unit' => 'kept', './probe/from-manifest' => 'secret-of-manifest' ] ], true ). ';' );
file_put_contents( $file, '<?php return '. var_export( [ '/project/kept' => 1 ], true ). ';' );
$app = runtimeKeysBoot( $like );
runtimeKeysSaid();
$unitRoutes = $unitBlacklist = $unitConfig = [];
$result = null;
$errors = ninoErrors( function() use ( &$app, $unitDir, &$unitRoutes, &$unitBlacklist, &$unitConfig, &$result ) { $result = \Nino\Features::applyUnit( $app, $unitDir, [ 'de_DE' ], $unitRoutes, $unitBlacklist, $unitConfig, true ); } );
check( 'a unit whose config names a key of the running request: the unit is applied, the key is not set and not handed on to be written, the others are', $result === true && array_key_exists( './probe/from-manifest', $app ) === false && $unitConfig === [ '/probe/unit' => 'kept' ] && ( $app['/probe/unit'] ?? null ) === 'kept' );
check( '...and the log says it with the name of the unit and the key, and no value', count( $errors ) === 1 && $errors[0][0] === E_USER_WARNING && str_contains( $errors[0][1], 'unit' ) && str_contains( $errors[0][1], './probe/from-manifest' ) && str_contains( $errors[0][1], 'secret-of-manifest' ) === false );
\Nino\AppData::writeContentData( $app, array_keys( $unitConfig ) );
check( '...so config.php has none of it after the write of the wizard', array_filter( array_keys( runtimeKeysFile( $like ) ), static fn( $key ): bool => str_starts_with( (string) $key, './' ) ) === [] && ( runtimeKeysFile( $like )['/probe/unit'] ?? null ) === 'kept' );


// --- A restore brings the config.php of an archive --------------------------------------------------------

echo "\nRestore\n";

$project = ninoSandbox( 'runtime-keys-restore' );
$projectDir = ninoSandboxDir( $project );
mkdir( $projectDir. '/private', 0755, true );
mkdir( $projectDir. '/public', 0755, true );
\Nino\Filesystem::putFileContent( $project, '/config.php', [
	'/nino/error/display'			=> true,
	'/nino/locales/native'		=> $project['/nino/locales/native'],
	'/nino/locales/available'	=> $project['/nino/locales/available'],
] );
$project['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $project );
ninoUser( $project, 'dev@example.com' );
\Nino\Modules\Backups::maybeRun( $project );

// The archive of a project whose config.php carries a key of the running request: the memory a backup may use is 1 byte
$projectFile = $projectDir. '/private/config.php';
$kept = (array) include $projectFile;
file_put_contents( $projectFile, '<?php return '. var_export( $kept + [ './nino/backups/memory' => 1 ], true ). ';' );
$archiveId = \Nino\Modules\Backups::now( $project );
file_put_contents( $projectFile, '<?php return '. var_export( $kept, true ). ';' );
unset( $project['./nino/filesystem/cache'] );

$dir = $projectDir. '/private/.backups';
$rawKey = (string) base64_decode( (string) $project['/nino/backup/key'], true );
$oldFormat = ninoSealedOld( (string) ninoUnsealed( (string) file_get_contents( $dir. '/'. $archiveId. '.php' ), $rawKey ), $rawKey );
[ $status ] = ninoCall( $project, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $archiveId ] );
$onDisk = runtimeKeysFile( $project );
check( 'the archive is restored, and its config.php is the archive\'s', $status === 200 && ( $onDisk['./nino/backups/memory'] ?? null ) === 1 );

$after = runtimeKeysBoot( $project );
$said = runtimeKeysSaid();
check( 'the request after the restore does not have the key of the archive, and says so once', array_key_exists( './nino/backups/memory', $after ) === false && count( $said ) === 1 && str_contains( $said[0], './nino/backups/memory' ) );

\Nino\Auth::loginUser( $after, 'dev@example.com', 'correct horse battery staple' );
file_put_contents( $dir. '/2001-02-03.php', $oldFormat );
[ $status, $body ] = ninoCall( $after, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2001-02-03' ] );
check( 'backups run: an archive of the first format is opened and restored, where a memory of 1 byte would refuse it', $status === 200 && ( $body['code'] ?? '' ) !== 'backups_upload_memory' );

\Nino\Auth::loginUser( $after, 'dev@example.com', 'correct horse battery staple' );
$after['./nino/backups/memory'] = 1;
file_put_contents( $dir. '/2001-02-04.php', $oldFormat );
[ $status, $body ] = ninoCall( $after, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2001-02-04' ] );
check( '...and set in memory the key still refuses it: the seam of a test is what it was', $status === 413 && ( $body['code'] ?? '' ) === 'backups_upload_memory' );

\Nino\Filesystem::removeDir( $projectDir );

ninoDone( $like );
