<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	concurrency-smoke.php	Does the file locking actually serialize anything?
 *												Two real processes hammer AppData::writeContentData()
 *												on the same config.php, each owning its own top-level
 *												key, each re-booting its appData from disk every
 *												round - exactly the "two overlapping requests" shape
 *												the re-read-under-lock exists for. If the lock is
 *												missing (or is taken and then dropped again, which is
 *												what happens when the handle lives somewhere a cache
 *												flush can reach), the two runs overwrite each other
 *												and entries go missing.
 *												Runs against an isolated sandbox directory, never
 *												touches the real project data.
 *
 *	Usage: php tests/concurrency-smoke.php
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_editor/Editor.php';

const ROUNDS = 50;

$failures = 0;
$checks		= 0;

/**
 *	Assert a condition and print the result
 *
 *	@param		string		$label				Description of the check
 *	@param		bool			$condition		Result to assert
 *
 *	@return		void
 */
function check( string $label, bool $condition ): void {
	global $failures, $checks;
	$checks++;
	if( $condition === false )
		$failures++;
	echo ( $condition === true ? '  ok  ' : 'FAIL  ' ), '- ', $label, "\n";
}

/**
 *	Boot an appData against the sandbox, the way a request would
 *
 *	@param		string		$sandbox			Sandbox path
 *
 *	@return		array
 */
function bootAppData( string $sandbox ): array {

	$appData = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $appData );
	$appData['./nino/filesystem/path'] = $sandbox;
	\Nino\AppData::init( $appData );

	return $appData;
}

/**
 *	One element worker: ROUNDS times, boot fresh and insert one element
 *	into the type file both workers share. Elements are the case two
 *	editors actually hit at the same time, and the write path reads the
 *	type file, changes it and writes it back - a lost update here silently
 *	drops someone else's article.
 *
 *	@param		string		$sandbox			Sandbox path
 *	@param		string		$prefix				Element uri prefix this worker owns
 *
 *	@return		void
 */
function runElementWorker( string $sandbox, string $prefix ): void {

	for( $round = 0; $round < ROUNDS; $round++ ) {

		$appData = bootAppData( $sandbox );
		$appData['/nino/locales/available']	= [ 'de_DE' ];
		$appData['/nino/locales/native']		= 'de_DE';

		\Nino\Elements::insertElement( $appData, '/news/'. $prefix. $round, [ 'title' => 'Item '. $round ], 'de_DE' );
	}
}

/**
 *	Run the editor's lazy daily backup from a separately-booted request.
 *	Two of these starting against a config without backup keys used to each
 *	generate a different directory/key pair.
 *
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function runBackupWorker( string $sandbox ): void {

	$appData = bootAppData( $sandbox );
	$appData['./nino/filesystem/configpath'] = $sandbox;
	$appData['./nino/filesystem/contentpath'] = $sandbox. '/content';
	$appData['/nino/editor/backups'] = true;

	\Nino\Editor\Backup::maybeRun( $appData );
}

/**
 *	One worker: ROUNDS times, boot a fresh appData from the shared
 *	config.php, append one entry to this worker's own key and persist it
 *
 *	@param		string		$sandbox			Sandbox path
 *	@param		string		$key					Top-level appData key this worker owns
 *
 *	@return		void
 */
function runWorker( string $sandbox, string $key ): void {

	for( $round = 0; $round < ROUNDS; $round++ ) {

		$appData = [ './nino/uid' => $sandbox ];
		\Nino\AppData::prepare( $appData );
		$appData['./nino/filesystem/path'] = $sandbox;
		\Nino\AppData::init( $appData );

		$entries					= $appData[$key] ?? [];
		$entries[]				= $round;
		$appData[$key]		= $entries;

		\Nino\AppData::writeContentData( $appData, [ $key ] );
	}
}

// Worker mode - re-entry of this same file, see below
if( ( $argv[1] ?? '' ) === 'worker' ) {
	runWorker( $argv[2], $argv[3] );
	exit( 0 );
}

if( ( $argv[1] ?? '' ) === 'element-worker' ) {
	runElementWorker( $argv[2], $argv[3] );
	exit( 0 );
}

if( ( $argv[1] ?? '' ) === 'backup-worker' ) {
	runBackupWorker( $argv[2] );
	exit( 0 );
}

/**
 *	Run two workers of the given mode in parallel and wait for both
 *
 *	@param		string		$mode					Worker mode ('worker' / 'element-worker')
 *	@param		string		$sandbox			Sandbox path
 *	@param		array			$args					One argument per worker
 *
 *	@return		void
 */
function runParallel( string $mode, string $sandbox, array $args ): void {

	$processes = [];

	foreach( $args as $arg )
		$processes[] = proc_open(
			[ PHP_BINARY, __FILE__, $mode, $sandbox, $arg ],
			[ 1 => [ 'file', '/dev/null', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ],
			$pipes
		);

	foreach( $processes as $process )
		if( is_resource( $process ) === true )
			proc_close( $process );
}

$sandbox = sys_get_temp_dir(). '/nino-concurrency-'. bin2hex( random_bytes( 6 ) );
mkdir( $sandbox. '/data', 0755, true );

file_put_contents( $sandbox. '/config.php', '<?php return '. var_export( [
	'/test/a' => [],
	'/test/b' => [],
], true ). ';' );

echo "AppData::writeContentData under real concurrency\n";

// Two separate php processes, so this is actual parallelism - flock() is
// per open file description, which a single process could satisfy by
// accident
runParallel( 'worker', $sandbox, [ '/test/a', '/test/b' ] );

$onDisk = include $sandbox. '/config.php';

check( 'config.php is still a readable array after '. ( 2 * ROUNDS ). ' concurrent writes', is_array( $onDisk ) === true );
check( 'no worker lost an entry of its own key (a)', count( $onDisk['/test/a'] ?? [] ) === ROUNDS );
check( 'no worker lost an entry of its own key (b)', count( $onDisk['/test/b'] ?? [] ) === ROUNDS );
check( 'both key sets are complete, ie. neither run overwrote the other', ( $onDisk['/test/a'] ?? [] ) === range( 0, ROUNDS - 1 ) && ( $onDisk['/test/b'] ?? [] ) === range( 0, ROUNDS - 1 ) );

echo "\n";


// --- Elements under real concurrency ------------------------------------------------------------

echo "Elements::insertElement under real concurrency\n";

// The same shape one level up: two editors adding entries to the same
// element type at the same time. Every insert is a read-modify-write of one
// type file, so an unserialized round drops whatever the other one just
// added - a published article silently disappearing.
mkdir( $sandbox. '/elements', 0755, true );
file_put_contents( $sandbox. '/elements/news.php', '<?php return '. var_export( [
	'title'	=> 'News',
	'model'	=> [ 'title' => [ 'type' => 'string', 'locale' => true ] ],
	'*'			=> [ '*' => [] ],
], true ). ';' );

runParallel( 'element-worker', $sandbox, [ 'a', 'b' ] );

$typeData	= include $sandbox. '/elements/news.php';
$stored		= array_diff( array_keys( $typeData['de_DE'] ?? [] ), [ '*' ] );

check( 'the type file is still a readable array after '. ( 2 * ROUNDS ). ' concurrent inserts', is_array( $typeData ) === true );
check( 'every element of the first editor survived', count( preg_grep( '/^a\d+$/', $stored ) ) === ROUNDS );
check( 'every element of the second editor survived', count( preg_grep( '/^b\d+$/', $stored ) ) === ROUNDS );
check( 'the type model itself is intact', ( $typeData['model']['title']['type'] ?? null ) === 'string' );

echo "\n";


// --- Daily backup bootstrap under real concurrency -----------------------------

echo "Editor\\Backup::maybeRun under real concurrency\n";

// Both workers boot before either is guaranteed to have persisted the lazy
// backup configuration. The stable backup lock must serialize the locked
// config re-read, key generation and today's existence check as one unit.
runParallel( 'backup-worker', $sandbox, [ '-', '-' ] );

$onDisk = include $sandbox. '/config.php';
$backupDirs = glob( $sandbox. '/content/.backups', GLOB_ONLYDIR ) ?: [];
$backupDir = $sandbox. '/content/.backups';
$today = $backupDir. '/'. date( 'Y-m-d' ). '.php';

check( 'parallel first-use requests persist one backup directory/key pair', count( $backupDirs ) === 1 && is_string( $onDisk['/nino/backup/key'] ?? null ) === true );
check( 'parallel first-use requests create exactly one usable daily backup', is_file( $today ) === true && count( glob( $backupDir. '/*.php' ) ?: [] ) === 1 );

if( is_file( $today ) === true ) {
	$prefix = "<?php http_response_code(403); exit; return '";
	$suffix = "';\n";
	$raw = file_get_contents( $today );
	$payload = base64_decode( substr( $raw, strlen( $prefix ), -strlen( $suffix ) ), true );
	$key = base64_decode( (string) ( $onDisk['/nino/backup/key'] ?? '' ), true );
	$plain = ( is_string( $payload ) && is_string( $key ) )
		? openssl_decrypt( substr( $payload, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $payload, 0, 12 ), substr( $payload, 12, 16 ) )
		: false;
	check( 'the surviving config key decrypts the concurrently-created backup', is_string( $plain ) === true && str_starts_with( $plain, "\x1f\x8b" ) === true );
} else {
	check( 'the surviving config key decrypts the concurrently-created backup', false );
}

\Nino\Filesystem::removeDir( $sandbox );

echo "\n", $checks, ' checks, ', $failures, " failed\n";

exit( $failures === 0 ? 0 : 1 );
