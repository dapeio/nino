<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	backup-hook-smoke.php		Dependency-free smoke test for '/nino/admin/backup': the hook a feature answers before the
 *											backup packs its files, so that what is packed is a copy of its data that is whole. What is asked,
 *											of whom and with what; that the copy is packed where the live file stood and the files an open
 *											database keeps beside it are not; what a feature may hand over and what ends the run (a link, a
 *											name that is not its own, a pipe, a hook that throws, a disk the copy fills); the lock, the
 *											snapshot before a restore and the files of a restore; and, where sqlite is there, a database in
 *											write-ahead mode written to by a second process while it is backed up and restored.
 *											Runs against isolated sandbox directories, never touches the real project data.
 *
 *	Usage: php tests/backup-hook-smoke.php
 */

// Before the kernel loads: the autoloader and Features::dir() read the same constant
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/backup-hook' );

require __DIR__. '/harness.php';
require_once __DIR__. '/fixtures/faultfs.php';

// Whatever ends the run - a fatal error as well - the folders of the projects it made are taken away
register_shutdown_function( static function(): void {
	foreach( (array) ( $GLOBALS['hookSandboxes'] ?? [] ) as $folder )
		\Nino\Filesystem::removeDir( (string) $folder );
} );

/** A project with the features of the fixture directory, the ones named switched on and their files as they lie */
function hookProject( string $name, array $modules = [ 'Other', 'Vault', 'Plain' ] ): array {

	$appData = ninoSandbox( $name );
	$sandbox = ninoSandboxDir( $appData );

	// Taken away at the end with the sandbox of the test: ninoDone() knows the one it is given
	$GLOBALS['hookSandboxes'][] = $sandbox;

	mkdir( $sandbox. '/private', 0755, true );
	mkdir( $sandbox. '/public', 0755, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [
		'/nino/error/display'			=> true,
		'/nino/locales/native'		=> $appData['/nino/locales/native'],
		'/nino/locales/available'	=> $appData['/nino/locales/available'],
	] );

	$appData['/nino/modules'] = array_map( static fn( string $module ): string => '\\Nino\\Modules\\'. $module, $modules );

	$data = \Nino\Filesystem::path( $appData, '/data' );
	mkdir( $data. '/vault-dir', 0755, true );

	foreach( [
		'vault.db' 								=> 'live vault',
		'other.db' 								=> 'live other',
		'plain.db' 								=> 'live plain',
		'plain.db-wal'						=> 'live plain wal',
		'vault.db-old' 						=> 'live old copy of vault, which is the project\'s and not of the feature that has a name that begins the same',
		'idle.db' 								=> 'live idle',
		'shared.db' 							=> 'live shared',
		'vault.php' 							=> "<?php return [ 'live' => 1 ];",
		'vault-dir/a.db' 					=> 'live a',
		'vault-dir/a.db-wal' 			=> 'live a wal',
		'vault-dir/a.db-shm' 			=> 'live a shm',
		'vault-dir/a.db-journal' 	=> 'live a journal',
		'vault-dir/b.db' 					=> 'live b',
		'vault-dir/b.db-wal' 			=> 'live b wal',
		'vault-dir/c.txt' 				=> 'live c',
		'vault-dir/c.txt-journal' => 'live c journal',
		'vault.db-journal' 				=> 'live vault journal, which is the project\'s own file of another feature',
	] as $file => $bytes )
		file_put_contents( $data. '/'. $file, $bytes );

	\Nino\Modules::callModules( $appData, 'init' );

	return $appData;
}

/** The files of an unpacked directory: name => bytes */
function hookFiles( string $root ): array {

	$files = [];

	if( is_dir( $root ) === false )
		return $files;

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY ) as $file )
		$files[str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $root ) + 1 ) )] = (string) file_get_contents( $file->getPathname() );

	ksort( $files );

	return $files;
}

/** What an archive of the project holds: name => bytes. The id is one of the list or a snapshot */
function hookArchive( array &$appData, string $id ): array {

	static $count = 0;

	$sandbox = ninoSandboxDir( $appData );
	$plain 	 = ninoUnsealed( (string) file_get_contents( \Nino\Modules\Backups::dir( $appData ). '/'. $id. '.php' ), (string) base64_decode( (string) $appData['/nino/backup/key'], true ) );
	$into 	 = $sandbox. '/read-'. ++$count;

	mkdir( $into, 0755, true );
	file_put_contents( $into. '.tar.gz', (string) $plain );

	return \Nino\Backup::unpack( $into. '.tar.gz', $into ) === null ? hookFiles( $into ) : [];
}

/** The stamp of an archive, as the array it is */
function hookStamp( array &$appData, array $files ): array {

	static $count = 0;

	$file = ninoSandboxDir( $appData ). '/stamp-'. ++$count. '.php';
	file_put_contents( $file, (string) ( $files['nino.php'] ?? '<?php return [];' ) );

	return (array) include $file;
}

/** What a project of this one's mark has left in the temporary directory: the files and folders of its runs */
function hookTemp( array &$appData ): array {

	$mark = ( new ReflectionMethod( \Nino\Backup::class, '_tempId' ) )->invokeArgs( null, [ &$appData ] );

	return glob( sys_get_temp_dir(). '/nino*-'. $mark. '-*' ) ?: [];
}

/** The snapshots of restores are named by the second: the ones there are taken away, so that the next one is the only one */
function hookSnapshotsGone( array &$appData ): void {
	foreach( glob( \Nino\Modules\Backups::dir( $appData ). '/pre-restore-*.php' ) ?: [] as $file )
		unlink( $file );
}

/** The archives of the list, by id */
function hookIds( array &$appData ): array {
	return array_map( static fn( string $file ): string => basename( $file, '.php' ), glob( \Nino\Modules\Backups::dir( $appData ). '/*.php' ) ?: [] );
}

$appData	= hookProject( 'backup-hook' );
$sandbox	= ninoSandboxDir( $appData );
$data			= \Nino\Filesystem::path( $appData, '/data' );
$tried		= [];

echo "Sandbox: $sandbox\n\n";

// --- A project with no feature that has a hook: the archive is what it was ---------------------------------

echo "Features without a hook\n";

$plain = hookProject( 'backup-hook-plain', [ 'Plain' ] );
$id 	 = \Nino\Modules\Backups::now( $plain );
$names = array_values( \Nino\Backup::manifest( $plain ) );
$files = is_string( $id ) === true ? hookArchive( $plain, $id ) : [];
$live	 = [];

foreach( \Nino\Backup::manifest( $plain ) as $absolute => $name )
	$live[$name] = (string) file_get_contents( $absolute );

unset( $files['nino.php'] );
ksort( $live );

check( 'a project where no feature has a hook is backed up as it lies: every file of the manifest, with the bytes it has, and no other', is_string( $id ) === true && $files === $live && $files !== [] );
check( '...the feature with a hook that is not switched on is not asked, and its file, and the ones of features that are off, lie in the archive as they are',
	( $files['data/idle.db'] ?? '' ) === 'live idle' && ( $files['data/vault.db'] ?? '' ) === 'live vault' && ( $files['data/plain.db'] ?? '' ) === 'live plain' && ( $plain['./vault/calls'] ?? 0 ) === 0 );
check( '...and the files of an open database lie beside the file that a feature without a hook owns, as they did: such an archive is not whole, and says nothing of it',
	array_key_exists( 'data/vault-dir/a.db-wal', $files ) === true && array_key_exists( 'data/vault-dir/a.db', $files ) === true );
check( '...no folder of the run is left in the temporary directory', hookTemp( $plain ) === [] );

// --- The hook: who is asked, with what -------------------------------------------------------------------

echo "\nThe hook\n";

$appData['./vault/files'] = [ 'vault.db' => 'copy of vault' ];
$before	= hookTemp( $appData );
$id			= \Nino\Modules\Backups::now( $appData );
$files	= is_string( $id ) === true ? hookArchive( $appData, $id ) : [];
$seen		= $appData['./vault/seen'][0] ?? [];

check( 'a feature that is switched on and has a hook is asked once for a run, and so is the second one', ( $appData['./vault/calls'] ?? 0 ) === 1 && ( $appData['./other/calls'] ?? 0 ) === 1 );
check( '...with { dataDir, staging }: the live data directory and a folder that is empty, closed to every other account, below the temporary directory and its own',
	array_keys( $seen ) === [ 'dataDir', 'staging', 'empty', 'perms' ] && $seen['dataDir'] === $data && str_starts_with( $seen['staging'], sys_get_temp_dir(). '/' ) === true
	&& $seen['empty'] === true && $seen['perms'] === 0700 && str_contains( $seen['staging'], $sandbox ) === false );
check( '...and the archive holds the copy where the live file stood, with the name of the live file, and the live files of what was not handed over',
	( $files['data/vault.db'] ?? '' ) === 'copy of vault' && ( $files['data/other.db'] ?? '' ) === 'copy of other' && ( $files['data/plain.db'] ?? '' ) === 'live plain'
	&& ( $files['data/vault-dir/b.db'] ?? '' ) === 'live b' && ( $files['data/shared.db'] ?? '' ) === 'live shared' );
check( '...the live files stay as they were, and the folder of the copies is gone with the run', file_get_contents( $data. '/vault.db' ) === 'live vault' && hookTemp( $appData ) === $before && is_dir( $seen['staging'] ) === false );
check( '...a name of the archive is there once, and the archive holds nothing that was not in the manifest but the stamp', array_diff( array_keys( $files ), array_values( \Nino\Backup::manifest( $appData ) ), [ 'nino.php' ] ) === [] );

// What the run counts before it asks: the files of the features with a hook and what an open database keeps beside them
$expected = 0;

foreach( [ 'vault.db', 'vault.db-journal', 'vault.php', 'shared.db', 'other.db', 'vault-dir/a.db', 'vault-dir/a.db-wal', 'vault-dir/a.db-shm', 'vault-dir/a.db-journal', 'vault-dir/b.db', 'vault-dir/b.db-wal', 'vault-dir/c.txt', 'vault-dir/c.txt-journal' ] as $name )
	$expected += strlen( (string) file_get_contents( $data. '/'. $name ) );

check( 'what the copies are counted at before the hooks are asked is the size of the live files of the features with a hook and of what an open database keeps beside them, once each',
	\Nino\Backup::copyBytes( $appData, \Nino\Backup::manifest( $appData ) ) === $expected && $expected > 0 );

// A callback that no feature owns, and one of a feature that is not switched on: neither is asked, one line in the log
$stray = hookProject( 'backup-hook-stray', [ 'Plain' ] );
$asked = new ArrayObject();
\Nino\Callbacks::registerCallback( $stray, '/nino/admin/backup', static function( array &$appData, array &$args ) use ( $asked ): void { $asked->append( 1 ); } );
\Nino\Callbacks::registerCallback( $stray, '/nino/admin/backup', [ \Nino\Modules\Idle::class, 'callbackBackup' ] );
ninoWarnings();
$id 		= \Nino\Modules\Backups::now( $stray );
$noted	= array_values( array_filter( ninoWarnings(), static fn( string $warning ): bool => str_contains( $warning, '/nino/admin/backup' ) === true ) );
check( 'a callback that no feature that is on owns is not asked - a closure of a test, a feature that is not switched on - and the run goes on with the files as they lie, saying so once',
	is_string( $id ) === true && $asked->count() === 0 && count( $noted ) === 1 && ( hookArchive( $stray, $id )['data/idle.db'] ?? '' ) === 'live idle' );

// --- What the copy is for: a database that is beside its companions -----------------------------------------

echo "\nThe files an open database keeps\n";

$appData['./vault/files'] = [ 'vault-dir/a.db' => 'copy of a' ];
$id			= \Nino\Modules\Backups::now( $appData );
$files	= is_string( $id ) === true ? hookArchive( $appData, $id ) : [];

check( 'of a database that was handed over the archive holds the copy and none of the files it keeps open beside it: its -wal, -shm and -journal are left out',
	( $files['data/vault-dir/a.db'] ?? '' ) === 'copy of a' && array_intersect( [ 'data/vault-dir/a.db-wal', 'data/vault-dir/a.db-shm', 'data/vault-dir/a.db-journal' ], array_keys( $files ) ) === [] );
check( '...and of one that was not handed over they stay, with its file as it lies: nothing is left out for a file that has no copy', ( $files['data/vault-dir/b.db'] ?? '' ) === 'live b' && ( $files['data/vault-dir/b.db-wal'] ?? '' ) === 'live b wal' );

// --- What a feature may hand over ---------------------------------------------------------------------------

echo "\nWhat a feature hands over\n";

$outside = $sandbox. '/outside.txt';
file_put_contents( $outside, 'outside the staging' );
mkdir( $sandbox. '/outside-dir' );
file_put_contents( $sandbox. '/outside-dir/x.db', 'inside a folder outside' );
$configBytes = (string) file_get_contents( \Nino\Filesystem::getConfigPath( $appData ). '/config.php' );

/** One attempt: a run with the feature set to a mode; what came of it, and what it left */
$attempt = static function( array $set ) use ( &$appData ): array {

	foreach( [ 'files', 'mode', 'target', 'linked' ] as $key )
		unset( $appData['./vault/'. $key] );

	foreach( $set as $key => $value )
		$appData['./vault/'. $key] = $value;

	$ids			= hookIds( $appData );
	$temp			= hookTemp( $appData );
	$calls		= $appData['./vault/calls'] ?? 0;
	ninoWarnings();
	$result		= \Nino\Modules\Backups::now( $appData );

	return [ 'result' => $result, 'new' => array_values( array_diff( hookIds( $appData ), $ids ) ), 'temp' => array_values( array_diff( hookTemp( $appData ), $temp ) ), 'asked' => ( $appData['./vault/calls'] ?? 0 ) - $calls,
		'tmp' => glob( \Nino\Modules\Backups::dir( $appData ). '/*.tmp' ) ?: [], 'note' => ninoWarnings() ];
};

/** Whether an attempt ended the run as it should: the answer names the feature with the code of the panel, nothing was written, nothing is left */
$refused = static function( array $run ): bool {
	return is_array( $run['result'] ) === true && $run['result'][0] === 500 && ( $run['result'][2] ?? '' ) === 'backups_hook' && ( $run['result'][3] ?? [] ) === [ 'vault' ]
		&& str_contains( $run['result'][1], 'vault' ) === true && $run['new'] === [] && $run['temp'] === [] && $run['tmp'] === [] && $run['asked'] === 1;
};

$runs = [
	'a link to config.php'																=> [ 'mode' => 'link', 'target' => \Nino\Filesystem::getConfigPath( $appData ). '/config.php' ],
	'a link to a file outside the staging'								=> [ 'mode' => 'link', 'target' => $outside ],
	'a file of another feature'														=> [ 'files' => [ 'other.db' => 'taken from other' ] ],
	'a name above the staging (../x)'											=> [ 'files' => [ '../x' => 'above' ] ],
	'a name in the folder of another feature'							=> [ 'files' => [ '../other/other.db' => 'forged' ] ],
	'a name of its own that is not in its manifest'				=> [ 'files' => [ 'vault-extra.db' => 'extra' ] ],
	'a file of another feature that begins as an own name'	=> [ 'files' => [ 'vault.db-old' => 'taken' ] ],
	'a name of the transient files'												=> [ 'files' => [ 'auth-tries.php' => '<?php return [];' ] ],
	'a case twin of its own name'													=> [ 'files' => [ 'VAULT.db' => 'twin' ] ],
	'a name with a line break in it'											=> [ 'files' => [ "vault.db\nx" => 'break' ] ],
	'a folder instead of a file'													=> [ 'mode' => 'folder' ],
	'a file in a folder that is no folder of its manifest'	=> [ 'files' => [ 'elsewhere/vault.db' => 'deep' ] ],
	'a second name of a file of the project'							=> [ 'mode' => 'hardlink', 'target' => $data. '/vault.db' ],
	'a file that is the companion of another it hands over'	=> [ 'files' => [ 'vault-dir/a.db' => 'copy', 'vault-dir/a.db-wal' => 'log' ] ],
	'a file that another feature hands over as well'			=> [ 'files' => [ 'shared.db' => 'mine' ] ],
];

if( function_exists( 'posix_mkfifo' ) === true )
	$runs['a pipe'] = [ 'mode' => 'fifo' ];

foreach( $runs as $label => $set ) {

	if( $label === 'a file that another feature hands over as well' )
		$appData['./other/files'] = [ 'other.db' => 'copy of other', 'shared.db' => 'theirs' ];

	$run = $attempt( $set );
	unset( $appData['./other/files'] );

	if( $label === 'a second name of a file of the project' && ( $appData['./vault/linked'] ?? false ) !== true ) {
		echo "  --  - a second name could not be made here (another file system): not tried\n";
		continue;
	}

	$tried[$label] = $refused( $run ) === true ? 'the run ends, '. $run['result'][1] : 'NOT REFUSED';

	check( $label. ': the run ends and names the feature, no archive is written, nothing of the run is left', $refused( $run ) === true );
}

check( '...and what lies outside is where it was: config.php, the file a link pointed to, the live data', file_get_contents( \Nino\Filesystem::getConfigPath( $appData ). '/config.php' ) !== '' && file_get_contents( $outside ) === 'outside the staging'
	&& file_get_contents( $data. '/vault.db' ) === 'live vault' && is_file( $sandbox. '/x' ) === false );

$run = $attempt( [ 'files' => [ "vault.db\nx" => 'break' ] ] );
check( 'a name in an answer cannot break a line of the answer or of the log: what is not printable is a question mark', preg_match( '/^[\x20-\x7e]+$/D', (string) $run['result'][1] ) === 1 && str_contains( (string) $run['result'][1], 'vault.db?x' ) === true );

// A link to a folder, where the feature's own folder is a link
$run = $attempt( [ 'mode' => 'link', 'target' => $sandbox. '/outside-dir' ] );
check( 'a link to a folder is a link as well: the run ends', $refused( $run ) === true );

$run = $attempt( [ 'mode' => 'throw' ] );
$tried['a hook that throws'] = is_array( $run['result'] ) === true ? $run['result'][1] : '';
check( 'a hook that throws ends the run with the feature named, in the answer and the code the panel words it by, and no path of the feature', $refused( $run ) === true && str_contains( (string) $run['result'][1], '/secret/place' ) === false );
check( '...what the feature said is in the log', count( array_filter( $run['note'], static fn( string $line ): bool => str_contains( $line, 'vault broke while it was copying' ) === true && str_contains( $line, 'vault' ) === true ) ) === 1 );

$run = $attempt( [ 'mode' => 'error' ] );
check( 'an error that is no exception ends the run in the same way', $refused( $run ) === true );

// A run that ended in a hook leaves nothing that would stop the next
$run = $attempt( [ 'files' => [ 'vault.db' => 'copy of vault' ] ] );
check( 'the run after a refused one is made: the lock is let go, no record of a run stands', is_string( $run['result'] ) === true && \Nino\Modules\Backups::stopped( $appData ) === null );

// The daily run: the attempt is noted and the reason names the feature
$appData['./vault/mode'] = 'throw';
$dayFile = \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php';
@unlink( $dayFile );
@unlink( \Nino\Modules\Backups::dir( $appData ). '/'. \Nino\Modules\Backups::ATTEMPT_FILE );
ninoWarnings();
\Nino\Modules\Backups::maybeRun( $appData );
ninoWarnings();
$missed = \Nino\Modules\Backups::missed( $appData );
check( 'the daily backup that a hook ended is noted as missed, with the feature in the reason, and no archive of the day', is_file( $dayFile ) === false && is_array( $missed ) === true && str_contains( (string) $missed['reason'], 'vault' ) === true );
unset( $appData['./vault/mode'] );
@unlink( \Nino\Modules\Backups::dir( $appData ). '/'. \Nino\Modules\Backups::ATTEMPT_FILE );

// Code under a data name: the hook decides nothing about what a file is, and a restore that was typed a key judges it as it judges any
$appData['./vault/files'] = [ 'vault.php' => "<?php system( 'id' );" ];
$id	 = \Nino\Modules\Backups::now( $appData );
check( 'a php file is a data name like another: a hook can hand over what it likes under it, and the archive carries it', is_string( $id ) === true && ( hookArchive( $appData, $id )['data/vault.php'] ?? '' ) === "<?php system( 'id' );" );
$rawKey	 = (string) base64_decode( (string) $appData['/nino/backup/key'], true );
$notice	 = null;
$typed	 = \Nino\Modules\Backups\Admin::restore( $appData, (string) $id, $notice, $rawKey );
$tried['php under a data name'] = is_array( $typed ) === true ? 'a restore with a typed key refuses it: '. $typed[1] : 'RESTORED';
check( '...which a restore of an archive that was opened with a typed key refuses as code, before it touches the project', is_array( $typed ) === true && $typed[0] === 422 && str_contains( $typed[1], 'code' ) === true
	&& file_get_contents( $data. '/vault.php' ) === "<?php return [ 'live' => 1 ];" );
unset( $appData['./vault/files'] );

// --- Time and room ------------------------------------------------------------------------------------------

echo "\nRoom\n";

$appData['./vault/files'] = [ 'vault.db' => 'copy of vault' ];
$manifest	= \Nino\Backup::manifest( $appData );
$bytes		= 0;

foreach( array_keys( $manifest ) as $absolute )
	$bytes += (int) @filesize( $absolute );

$copies		= \Nino\Backup::copyBytes( $appData, $manifest );
$together	= ( $a = @stat( sys_get_temp_dir() ) ) !== false && ( $b = @stat( \Nino\Modules\Backups::dir( $appData ) ) ) !== false && $a['dev'] === $b['dev'];
$need			= \Nino\Backup::spaceNeed( $bytes, count( $manifest ), $together );

\Nino\Backup::$diskFree = $need + $copies - 1;
$run			= $attempt( [ 'files' => [ 'vault.db' => 'copy of vault' ] ] );
$short		= $run['result'];
check( 'the room is asked for before a feature is: the files and the copies they will make - a disk with room for the files and none for the copies is refused with the sizes, nothing is asked and nothing is made',
	is_array( $short ) === true && $short[0] === 507 && ( $short[2] ?? '' ) === 'backups_disk' && count( $short[3] ?? [] ) === 2 && $run['asked'] === 0 && $run['new'] === [] && $run['temp'] === [] );
\Nino\Backup::$diskFree = $need + $copies;
$run			= $attempt( [ 'files' => [ 'vault.db' => 'copy of vault' ] ] );
check( '...and one with room for both is backed up', is_string( $run['result'] ) === true );

// A copy of a size the disk has no room for after it is made: the sizes the run has are the true ones
\Nino\Backup::$diskFree = $need + $copies;
$run			= $attempt( [ 'files' => [ 'vault.db' => str_repeat( 'x', 3 * 1048576 ) ] ] );
$tried['a copy of 3 MB that the disk has no room for'] = is_array( $run['result'] ) === true ? $run['result'][1] : 'NOT REFUSED';
check( 'what the features wrote is counted again by what it is: a copy that the disk has no room for after it was made ends the run with the sizes, no archive and nothing left',
	is_array( $run['result'] ) === true && $run['result'][0] === 507 && ( $run['result'][2] ?? '' ) === 'backups_disk' && count( $run['result'][3] ?? [] ) === 2 && $run['asked'] === 1 && $run['new'] === [] && $run['temp'] === [] && $run['tmp'] === [] );
\Nino\Backup::$diskFree = null;

// --- The lock -----------------------------------------------------------------------------------------------

echo "\nThe lock\n";

// A second process asks while the first one is in the hook: another backup and a restore are answered busy
$child = $sandbox. '/child.php';
file_put_contents( $child, '<?php
declare(strict_types=1);
define( "NINO_FEATURES_DIR", $argv[3] );
require $argv[1]. "/tests/harness.php";
$appData = [ "./nino/uid" => $argv[2] ];
\Nino\AppData::prepare( $appData );
$appData["./nino/filesystem/path"] = $argv[2]; $appData["./nino/filesystem/configpath"] = $argv[2]. "/private"; $appData["./nino/filesystem/contentpath"] = $argv[2]. "/private"; $appData["./nino/filesystem/publicpath"] = $argv[2]. "/public";
$appData["/nino/dir"] = ""; $appData["/nino/install/texts"] = \Nino\VERSION; $appData["./nino/locales/current"] = "de_DE"; $appData["/nino/locales/native"] = "de_DE"; $appData["/nino/locales/available"] = [ "de_DE", "en_US" ];
$appData["/nino/backup/key"] = $argv[4];
$appData["/nino/modules"] = [ "\\\\Nino\\\\Modules\\\\Vault" ];
\Nino\Modules::callModules( $appData, "init" );
if( $argv[5] === "daily" ) {
	$appData["./vault/mode"] = "exit";
	set_time_limit( 1 );
	\Nino\Modules\Backups::maybeRun( $appData );
}
else if( $argv[5] === "exit" || $argv[5] === "spin" ) {
	$appData["./vault/mode"] = $argv[5];
	$appData["./nino/backups/answer"] = true;
	set_time_limit( 1 );
	\Nino\Modules\Backups::now( $appData );
}
else
	echo json_encode( [ \Nino\Modules\Backups::now( $appData ), \Nino\Modules\Backups\Admin::restore( $appData, "2000-01-01" ) ] );
' );

$ask = static function( string $what ) use ( $child, $sandbox, $appData, &$status ): string {
	$output = [];
	exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( $child ). ' '. escapeshellarg( dirname( __DIR__ ) ). ' '. escapeshellarg( $sandbox ). ' '. escapeshellarg( NINO_FEATURES_DIR ). ' '. escapeshellarg( (string) $appData['/nino/backup/key'] ). ' '. $what. ' 2>&1', $output, $status );
	return implode( "\n", $output );
};

$answers = '';
$appData['./vault/during'] = static function() use ( $ask, &$answers ): void { $answers = $ask( 'busy' ); };
$run = $attempt( [ 'mode' => 'during' ] );
$busy = json_decode( $answers, true );
check( 'the hook runs under the lock of the run: another backup and a restore that are asked from a second process meanwhile are answered busy, and the run is made with the copy',
	is_string( $run['result'] ) === true && is_array( $busy ) === true && ( $busy[0][0] ?? 0 ) === 409 && ( $busy[0][2] ?? '' ) === 'backups_busy' && ( $busy[1][0] ?? 0 ) === 409 && ( $busy[1][2] ?? '' ) === 'backups_busy'
	&& ( hookArchive( $appData, $run['result'] )['data/vault.db'] ?? '' ) === 'copy of vault' );
unset( $appData['./vault/during'] );

// ...and the process that holds the lock has no way to be let in by it: a hook that asks for a backup is asked itself, and is refused
$run = $attempt( [ 'mode' => 'again' ] );
$again = $appData['./vault/again'] ?? null;
check( 'a hook that asks for a backup of its own, in the process that holds the lock, is refused: the run it was asked in goes on with its copy', is_array( $again ) === true && $again[0] === 500 && is_string( $run['result'] ) === true && ( $appData['./vault/calls'] ?? 0 ) >= 1 );

// --- A request that ends in the hook -------------------------------------------------------------------------

echo "\nA request that ends in the hook\n";

$before	= hookTemp( $appData );
$said = $ask( 'exit' );
$stopped = \Nino\Modules\Backups::stopped( $appData );
$answer = json_decode( $said, true );
check( 'a request that is ended while a feature is asked leaves the record of the run, which names the feature and says that the hook ended it, and no folder of the copies', $status === 3 && is_array( $stopped ) === true && $stopped['feature'] === 'vault' && $stopped['cause'] === 'hook' && hookTemp( $appData ) === $before );
check( '...and where nothing was sent, whoever asked is answered with a code and the feature, and not by an empty answer', is_array( $answer ) === true && ( $answer['code'] ?? '' ) === 'backups_hook' && ( $answer['params'] ?? [] ) === [ 'vault' ] );
check( '...and the lock is let go with the request: the next run is made', is_string( \Nino\Modules\Backups::now( $appData ) ) === true );
@unlink( \Nino\Modules\Backups::dir( $appData ). '/run.php' );

// ...and one that the time limit of the request ends: the record says so, with the limit and the feature
$before	= hookTemp( $appData );
$ask( 'spin' );
$stopped = \Nino\Modules\Backups::stopped( $appData );
check( 'the time limit of the request is the limit of a callback: a request that is ended in it leaves the record with the limit and the feature, and no folder of the copies',
	is_array( $stopped ) === true && $stopped['cause'] === 'time' && $stopped['limit'] === 1 && $stopped['feature'] === 'vault' && hookTemp( $appData ) === $before );
@unlink( \Nino\Modules\Backups::dir( $appData ). '/run.php' );

// The daily backup: the request that makes it ends in the hook, and the reason of the day's note names the feature all the same
@unlink( \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php' );
@unlink( \Nino\Modules\Backups::dir( $appData ). '/'. \Nino\Modules\Backups::ATTEMPT_FILE );
$ask( 'daily' );
$missed = \Nino\Modules\Backups::missed( $appData );
check( 'a daily backup that the end of the request in a hook leaves without an archive is missed with the feature in the reason, and the record says what ended it', is_array( $missed ) === true && str_contains( (string) $missed['reason'], 'vault' ) === true && ( \Nino\Modules\Backups::stopped( $appData )['cause'] ?? '' ) === 'hook' );
@unlink( \Nino\Modules\Backups::dir( $appData ). '/run.php' );
@unlink( \Nino\Modules\Backups::dir( $appData ). '/'. \Nino\Modules\Backups::ATTEMPT_FILE );

// What the note may name: a key, and nothing a file could say
$stub = \Nino\Backup\Seal::STUB_PREFIX. base64_encode( (string) json_encode( [ 'kind' => 'manual', 'started' => 1700000000, 'feature' => '../etc' ] ) ). \Nino\Backup\Seal::STUB_SUFFIX;
file_put_contents( \Nino\Modules\Backups::dir( $appData ). '/run.php', $stub );
check( 'the feature of a note is read as a key and as nothing else', ( \Nino\Modules\Backups::stopped( $appData )['feature'] ?? 'x' ) === '' );
@unlink( \Nino\Modules\Backups::dir( $appData ). '/run.php' );

// --- The snapshot before a restore and the files of a restore --------------------------------------------------

echo "\nRestore\n";

unset( $appData['./vault/mode'] );
$appData['./vault/files'] = [ 'vault-dir/a.db' => 'copy of a', 'vault.db' => 'copy of vault' ];
$id			= \Nino\Modules\Backups::now( $appData );
$made		= hookArchive( $appData, (string) $id );
$stamp	= hookStamp( $appData, $made );

check( 'the archive lists in its stamp the files that were handed over, by their names, and no file that was packed as it lay', ( $stamp['copies'] ?? null ) === [ 'data/other.db', 'data/vault-dir/a.db', 'data/vault.db' ] && ( $stamp['raw'] ?? null ) === [] );
check( 'a file that a feature names itself and that ends as a companion of a handed-over one is packed: it is the feature\'s own file, whoever has a hook; and so are a file that ends so by chance and a companion of a file that was not handed over',
	( $made['data/vault.db-journal'] ?? '' ) !== '' && ( $made['data/vault-dir/c.txt-journal'] ?? '' ) === 'live c journal' && ( $made['data/vault-dir/b.db-wal'] ?? '' ) === 'live b wal' && isset( $made['data/vault-dir/a.db-journal'] ) === false );

file_put_contents( $data. '/vault.db', 'live vault after' );
file_put_contents( $data. '/vault-dir/a.db', 'live a after' );
file_put_contents( $data. '/vault-dir/a.db-wal', 'live a wal after' );
file_put_contents( $data. '/vault-dir/c.txt-journal', 'live c journal after' );
file_put_contents( $data. '/vault.db-journal', 'live journal of another feature after' );
file_put_contents( $data. '/plain.db-wal', 'live plain wal after' );
$appData['./vault/files'] = [ 'vault.db' => 'snapshot copy of vault' ];
$calls	= $appData['./vault/calls'];
$ids		= hookIds( $appData );
$result	= \Nino\Modules\Backups\Admin::restore( $appData, (string) $id );
$snap		= array_values( array_diff( hookIds( $appData ), $ids ) );
$files	= count( $snap ) === 1 ? hookArchive( $appData, $snap[0] ) : [];

check( 'the safety snapshot before a restore asks the hook as a backup does, and holds the copy where the live file stood', $result === true && $appData['./vault/calls'] === $calls + 1 && ( $files['data/vault.db'] ?? '' ) === 'snapshot copy of vault' && ( $files['data/vault-dir/a.db'] ?? '' ) === 'live a after' );
check( '...and the restore puts the file that was handed over back as the one file: the files an open database keeps beside it are removed from the project',
	file_get_contents( $data. '/vault.db' ) === 'copy of vault' && file_get_contents( $data. '/vault-dir/a.db' ) === 'copy of a'
	&& is_file( $data. '/vault-dir/a.db-shm' ) === false && is_file( $data. '/vault-dir/a.db-journal' ) === false && is_file( $data. '/vault-dir/a.db-wal' ) === false );
check( '...and no other file: not the companions of a feature without a hook, not a file that another feature names and the archive carries, not a file that only ends so, not a companion of a file that was not handed over',
	file_get_contents( $data. '/plain.db-wal' ) === 'live plain wal after' && file_get_contents( $data. '/vault.db-journal' ) === 'live vault journal, which is the project\'s own file of another feature'
	&& file_get_contents( $data. '/vault-dir/c.txt-journal' ) === 'live c journal' && file_get_contents( $data. '/vault-dir/b.db-wal' ) === 'live b wal' );

// What a restore takes away is what the stamp of the archive says was handed over, and no more
file_put_contents( $data. '/vault-dir/a.db-wal', 'live a wal again' );
$stage = $sandbox. '/stage-a';
mkdir( $stage. '/data/vault-dir', 0755, true );
file_put_contents( $stage. '/data/vault-dir/a.db', 'x' );
check( 'an archive without the list takes nothing away, and a name the archive does not carry has no file taken away',
	\Nino\Backup::dropCompanions( $appData, $stage, [] ) === true && \Nino\Backup::dropCompanions( $appData, $stage, null ) === true && \Nino\Backup::dropCompanions( $appData, $stage, [ 'data/vault-dir/e.db' ] ) === true && is_file( $data. '/vault-dir/a.db-wal' ) === true );
file_put_contents( $stage. '/data/vault-dir/a.db-wal', 'in the archive' );
check( '...nor a companion that the archive carries itself', \Nino\Backup::dropCompanions( $appData, $stage, [ 'data/vault-dir/a.db' ] ) === true && is_file( $data. '/vault-dir/a.db-wal' ) === true );
foreach( [ 'x1', 'x2', 'x3', 'x4' ] as $more )
	file_put_contents( $stage. '/data/'. $more, 'x' );
file_put_contents( $stage. '/data/other.db', 'x' );
check( '...and a name of the list that is no name of an archive is read as none', \Nino\Backup::dropCompanions( $appData, $stage, [ 'data/../config.php', 'data//x', 7, "data/a\0b" ] ) === true && is_file( \Nino\Filesystem::getConfigPath( $appData ). '/config.php' ) === true );
file_put_contents( $data. '/other.db-wal', 'live other wal' );
check( 'a name with .. in it that is a name of the archive all the same takes nothing away', \Nino\Backup::dropCompanions( $appData, $stage, [ 'data/vault-dir/../other.db' ] ) === true && is_file( $data. '/other.db-wal' ) === true );
check( 'a list with more names than the archive has files below data/ is no stamp this code made: nothing is taken away', \Nino\Backup::dropCompanions( $appData, $stage, array_fill( 0, 20, 'data/other.db' ) ) === true && is_file( $data. '/other.db-wal' ) === true );
file_put_contents( $stage. '/data/plain.db', 'x' );
file_put_contents( $stage. '/data/forms.1.php', 'x' );
file_put_contents( $data. '/forms.1.php-journal', 'live journal of a file nobody owns' );
check( 'the list of the stamp is believed for the names of a feature that is switched on and has a hook, and for no other: a file of a feature without a hook, a file of no feature',
	\Nino\Backup::dropCompanions( $appData, $stage, [ 'data/plain.db', 'data/forms.1.php', 'data/vault.db-old' ] ) === true && is_file( $data. '/plain.db-wal' ) === true && is_file( $data. '/forms.1.php-journal' ) === true );
$left = 0;
check( 'of an archive of another project nothing is taken away, even for the name of a feature with a hook, and the count of the files that have companions is told',
	\Nino\Backup::dropCompanions( $appData, $stage, [ 'data/other.db' ], true, $left ) === true && is_file( $data. '/other.db-wal' ) === true && $left === 1 );
check( '...and the same list of an archive of this project takes them away', \Nino\Backup::dropCompanions( $appData, $stage, [ 'data/other.db' ], false, $left ) === true && is_file( $data. '/other.db-wal' ) === false && $left === 0 );
unlink( $stage. '/data/vault-dir/a.db-wal' );
check( '...and one that it carries and lists loses the companions that nobody names: only then', \Nino\Backup::dropCompanions( $appData, $stage, [ 'data/vault-dir/a.db' ] ) === true && is_file( $data. '/vault-dir/a.db-wal' ) === false );

// The restore of the recovery page opens the archive with a typed key and asks the same hook for its snapshot
$appData['./vault/files'] = [ 'vault.db' => 'copy for recovery' ];
$calls	= $appData['./vault/calls'];
file_put_contents( $data. '/vault-dir/a.db-wal', 'live a wal of a restore of another project' );
file_put_contents( $data. '/vault.db-wal', 'live vault wal of a restore of another project' );
$notice	= null;
$result	= \Nino\Modules\Backups\Admin::restore( $appData, (string) $id, $notice, $rawKey );
check( 'a restore that was opened with a typed key (the recovery page) asks the hook for its snapshot as well', $result === true && $appData['./vault/calls'] === $calls + 1 );
check( '...but it is not believed about what to take away: the files beside the databases stand, and the answer says beside how many files', is_file( $data. '/vault-dir/a.db-wal' ) === true && is_file( $data. '/vault.db-wal' ) === true
	&& ( $notice['code'] ?? '' ) === 'backups_companions_left' && ( $notice['params'] ?? [] ) === [ '2' ] );
unlink( $data. '/vault.db-wal' );

// A feature whose hook cannot do its work does not keep the project from being restored: the snapshot is the state as it is, with all the files
echo "\nA hook that fails when a restore is asked for\n";

$state = [ 'vault.db' => 'raw vault', 'vault-dir/a.db' => 'raw a', 'vault-dir/a.db-wal' => 'raw a wal', 'vault-dir/a.db-shm' => 'raw a shm', 'vault-dir/a.db-journal' => 'raw a journal' ];
$write = static function() use ( $data, $state ): void { foreach( $state as $name => $bytes ) file_put_contents( $data. '/'. $name, $bytes ); };
$read	 = static function() use ( $data, $state ): array { $now = []; foreach( $state as $name => $bytes ) $now[$name] = is_file( $data. '/'. $name ) === true ? (string) file_get_contents( $data. '/'. $name ) : null; return $now; };

foreach( [ 'throw' => 'a hook that throws', 'files' => 'a hook that puts what is not its own into its folder', 'diskfull' => 'a hook whose disk is full' ] as $mode => $label ) {

	$write();
	$appData['./vault/mode']	= $mode === 'files' ? 'files' : $mode;
	$appData['./vault/files']	= $mode === 'files' ? [ 'other.db' => 'foreign' ] : [];
	hookSnapshotsGone( $appData );
	$notice 	= null;
	ninoWarnings();
	$result		= \Nino\Modules\Backups\Admin::restore( $appData, (string) $id, $notice );
	$snap			= array_values( array_filter( hookIds( $appData ), static fn( string $name ): bool => str_starts_with( $name, 'pre-restore-' ) ) );
	$kept			= count( $snap ) === 1 ? hookArchive( $appData, $snap[0] ) : [];
	$stamp		= hookStamp( $appData, $kept );
	$warned		= array_filter( ninoWarnings(), static fn( string $line ): bool => str_contains( $line, 'as they lie' ) === true );

	check( $label. ': the restore goes on and is done, and says in its notice, in the log and in the stamp that the snapshot of the feature is the state as it was',
		$result === true && ( $notice['code'] ?? '' ) === 'backups_snapshot_raw' && ( $notice['params'] ?? [] ) === [ 'vault' ] && ( $stamp['raw'] ?? null ) === [ 'vault' ] && ( $stamp['copies'] ?? null ) === [ 'data/other.db' ] && count( $warned ) === 1 );
	$expect = [];
	foreach( $state as $name => $bytes )
		$expect['data/'. $name] = $bytes;
	$have = array_intersect_key( $kept, $expect );
	ksort( $have );
	ksort( $expect );
	check( '...the snapshot holds the files of the feature byte for byte as they lay, with the files an open database keeps beside them', $have === $expect );

	// ...and it restores that state, as it was
	unset( $appData['./vault/mode'], $appData['./vault/files'] );
	$result = \Nino\Modules\Backups\Admin::restore( $appData, $snap[0] ?? 'missing' );
	check( '...and restoring that snapshot brings the state back as it was, every file', $result === true && $read() === $state );
}

// The state as it is is whole where the main file is gone, and where it is a link
$write();
unlink( $data. '/vault.db' );
file_put_contents( $data. '/vault.db-wal', 'orphan wal' );
$appData['./vault/mode'] = 'throw';
hookSnapshotsGone( $appData );
$notice = null;
$result = \Nino\Modules\Backups\Admin::restore( $appData, (string) $id, $notice );
$kept		= hookArchive( $appData, (string) ( array_values( array_filter( hookIds( $appData ), static fn( string $name ): bool => str_starts_with( $name, 'pre-restore-' ) ) )[0] ?? 'missing' ) );
check( 'the snapshot of a feature that could not hand over a copy has the files beside a database whose main file is not there: the state as it is, whole', $result === true && ( $kept['data/vault.db-wal'] ?? '' ) === 'orphan wal' && isset( $kept['data/vault.db'] ) === false );

$write();
unlink( $data. '/vault.db' );
symlink( $sandbox. '/behind-a-link.db', $data. '/vault.db' );
file_put_contents( $data. '/vault.db-wal', 'wal beside a link' );
hookSnapshotsGone( $appData );
$notice = null;
$result = \Nino\Modules\Backups\Admin::restore( $appData, (string) $id, $notice );
$kept		= hookArchive( $appData, (string) ( array_values( array_filter( hookIds( $appData ), static fn( string $name ): bool => str_starts_with( $name, 'pre-restore-' ) ) )[0] ?? 'missing' ) );
$stamp	= hookStamp( $appData, $kept );
check( 'a main file that is a link is left out of the snapshot and named in its stamp, the files beside it are in, and the restore is not done in part for it', $result === true && isset( $kept['data/vault.db'] ) === false && ( $stamp['links'] ?? null ) === [ 'data/vault.db' ] && ( $kept['data/vault.db-wal'] ?? '' ) === 'wal beside a link' );
if( is_link( $data. '/vault.db' ) === true )
	unlink( $data. '/vault.db' );

// A feature that comes after another and changes what that one handed over: both are the state as it is
$write();
$liveOther = (string) file_get_contents( $data. '/other.db' );
$appData['./vault/mode'] = 'tamper';
hookSnapshotsGone( $appData );
$notice = null;
$result = \Nino\Modules\Backups\Admin::restore( $appData, (string) $id, $notice );
$kept		= hookArchive( $appData, (string) ( array_values( array_filter( hookIds( $appData ), static fn( string $name ): bool => str_starts_with( $name, 'pre-restore-' ) ) )[0] ?? 'missing' ) );
$stamp	= hookStamp( $appData, $kept );
check( 'what a feature did to the copy of one that was asked before it makes both of them the state as it is: the stamp names both, no copy is in the snapshot and the live file is, not what was forged',
	$result === true && ( $stamp['raw'] ?? [] ) === [ 'vault', 'other' ] && ( $stamp['copies'] ?? null ) === [] && ( $kept['data/other.db'] ?? '' ) === $liveOther && $liveOther !== 'forged' );

$appData['./vault/mode'] = 'throw';
$write();
$notice = null;
$result = \Nino\Modules\Backups\Admin::restore( $appData, (string) $id, $notice, $rawKey );
unset( $appData['./vault/mode'] );
check( 'the restore of the recovery page, which has the same snapshot, is done as well, and its notice says so', $result === true && ( $notice['code'] ?? '' ) === 'backups_snapshot_raw' );

// A hook that fails still ends a backup that is asked for: the snapshot is the one net that is tolerant, the backup is the work
$appData['./vault/mode'] = 'throw';
$run = \Nino\Modules\Backups::now( $appData );
unset( $appData['./vault/mode'] );
check( 'a backup that is asked for, and the daily one, still end with the feature named: only the snapshot of a restore goes on', is_array( $run ) === true && $run[0] === 500 && ( $run[2] ?? '' ) === 'backups_hook' );

// A hook that writes onto a disk that is full ends the run as a disk that is full does
$run = $attempt( [ 'mode' => 'diskfull' ] );
$full = \Nino\Modules\Backups::stopped( $appData );
check( 'a notice of a write that the disk refused in the hook is a run that the disk ended: 500 with the code of a write, the record says disk, nothing is left',
	is_array( $run['result'] ) === true && $run['result'][0] === 500 && ( $run['result'][2] ?? '' ) === 'backups_write' && is_array( $full ) === true && $full['cause'] === 'disk' && array_diff( $run['new'], [ 'run' ] ) === [] && $run['temp'] === [] );
@unlink( \Nino\Modules\Backups::dir( $appData ). '/run.php' );

// The first day: the example of the manual, as it is written there, when the database is not made yet
echo "\nThe example of the manual\n";

$manual = (string) file_get_contents( dirname( __DIR__ ). '/docs/features.md' );
preg_match( '/### Data, backup and restore.*?```php\n(.*?)```/s', $manual, $found );
check( 'the manual has the example', isset( $found[1] ) === true && str_contains( $found[1], 'VACUUM INTO' ) === true );
eval( 'namespace Nino\Modules; class DocHook { '. (string) ( $found[1] ?? '' ). ' }' );
$appData['./vault/example'] = [ \Nino\Modules\DocHook::class, 'callbackBackup' ];
$appData['./vault/mode']		= 'example';
@unlink( $data. '/crm.sqlite' );
$run = $attempt( [ 'mode' => 'example', 'example' => $appData['./vault/example'] ] );
check( 'on the first day, with no database yet, the example hands nothing over and the backup is made, with nothing of the database in it', is_string( $run['result'] ) === true && isset( hookArchive( $appData, $run['result'] )['data/crm.sqlite'] ) === false );

$run = $attempt( [ 'files' => [ 'crm.sqlite' => 'a database that was made at the same time' ] ] );
$first = is_array( $run['result'] ) === true ? explode( ':', (string) $run['result'][1] )[0] : '';
$run = $attempt( [ 'files' => [ 'nothing-of-its-own.db' => 'x' ] ] );
$other = is_array( $run['result'] ) === true ? explode( ':', (string) $run['result'][1] )[0] : '';
check( 'a feature that hands over a file the project does not have yet is told so, and not that its manifest does not give the name', $first !== '' && $other !== '' && $first !== $other && str_contains( $first, 'manifest' ) === false );
$appData['./vault/example'] = [ \Nino\Modules\DocHook::class, 'callbackBackup' ];

// --- A database of sqlite, written to by a second process --------------------------------------------------------

echo "\nA database in write-ahead mode\n";

if( extension_loaded( 'pdo_sqlite' ) === false ) {

	echo "  --  - pdo_sqlite is not there: the database of the end-to-end test is not made, the rules above hold with plain files\n";
}
else {

	$lite		= hookProject( 'backup-hook-sqlite', [ 'Vault' ] );
	$liteData	= \Nino\Filesystem::path( $lite, '/data' );
	$db			= $liteData. '/vault.db';
	@unlink( $db );

	$pdo = new \PDO( 'sqlite:'. $db );
	$pdo->exec( 'PRAGMA journal_mode=WAL' );
	$pdo->exec( 'CREATE TABLE t ( id INTEGER PRIMARY KEY, v TEXT )' );
	$pdo->beginTransaction();

	for( $row = 1; $row <= 100; $row++ )
		$pdo->exec( "INSERT INTO t ( v ) VALUES ( 'first-$row' )" );

	$pdo->commit();
	$pdo = null;

	$writer = $sandbox. '/writer.php';
	file_put_contents( $writer, '<?php
$db = new PDO( "sqlite:". $argv[1] );
$db->exec( "PRAGMA journal_mode=WAL" );
$db->exec( "PRAGMA wal_autocheckpoint=0" );
$write = static function( string $name, int $count ) use ( $db ): void { $db->beginTransaction(); for( $row = 1; $row <= $count; $row++ ) $db->exec( "INSERT INTO t ( v ) VALUES ( \'$name-$row\' )" ); $db->commit(); };
$write( "second", 50 );
echo "ready\n";
while( ( $line = fgets( STDIN ) ) !== false )
	if( trim( $line ) === "more" ) { $write( "third", 10 ); echo "done\n"; }
' );

	$process	= proc_open( [ PHP_BINARY, $writer, $db ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ] ], $pipes );
	$ready		= is_resource( $process ) === true ? fgets( $pipes[1] ) : false;

	$lite['./vault/mode']	= 'sqlite';
	$lite['./vault/after']	= static function() use ( $pipes ): void { fwrite( $pipes[0], "more\n" ); fgets( $pipes[1] ); };

	$naive = $sandbox. '/naive.db';
	copy( $db, $naive );
	$count = static function( string $file ): array {
		$open = new \PDO( 'sqlite:'. $file );
		$rows = (int) $open->query( 'SELECT COUNT(*) FROM t' )->fetchColumn();
		$ok		= (string) $open->query( 'PRAGMA integrity_check' )->fetchColumn();
		return [ $rows, $ok ];
	};

	$id = \Nino\Modules\Backups::now( $lite );

	check( 'the second process wrote while the backup was made: the file alone, as the backup copied it before, does not hold what is committed - the premise of the test', trim( (string) $ready ) === 'ready' && $count( $naive )[0] === 100 && is_file( $db. '-wal' ) === true && $count( $db )[0] === 160 );

	$archive = is_string( $id ) === true ? hookArchive( $lite, $id ) : [];
	$copy		 = $sandbox. '/archived.db';
	file_put_contents( $copy, $archive['data/vault.db'] ?? '' );
	check( 'the archive holds the database as the copy had it, whole: the integrity check is ok and the rows are those committed when the copy was made, not fewer and not what came after', is_string( $id ) === true && $count( $copy ) === [ 150, 'ok' ] && isset( $archive['data/vault.db-wal'] ) === false && isset( $archive['data/vault.db-shm'] ) === false );

	// The writer dies as a crash would leave it: the log of writes and the shared memory stay beside the file
	if( is_resource( $process ) === true ) {
		proc_terminate( $process, 9 );
		fclose( $pipes[0] );
		fclose( $pipes[1] );
		proc_close( $process );
	}

	$lite['./vault/mode'] = 'files';
	$lite['./vault/files'] = [];
	unset( $lite['./vault/after'] );
	$kept = is_file( $db. '-wal' ) === true;
	$result = \Nino\Modules\Backups\Admin::restore( $lite, (string) $id );

	check( 'the restore of that archive puts the one file back and takes the log of writes of the live one away: the integrity check is ok and the rows are the copy\'s, 150', $kept === true && $result === true && is_file( $db. '-wal' ) === false && is_file( $db. '-shm' ) === false && $count( $db ) === [ 150, 'ok' ] );

	// The hook of the manual, and a database that is broken: the restore of the good backup is what is wanted, and it is done
	$crm = $liteData. '/crm.sqlite';
	$pdo = new \PDO( 'sqlite:'. $crm );
	$pdo->exec( 'PRAGMA journal_mode=WAL' );
	$pdo->exec( 'CREATE TABLE t ( id INTEGER PRIMARY KEY, v TEXT )' );
	$pdo->exec( "INSERT INTO t ( v ) VALUES ( 'one' ), ( 'two' ), ( 'three' )" );
	$pdo = null;

	$lite['./vault/example']	= [ \Nino\Modules\DocHook::class, 'callbackBackup' ];
	$lite['./vault/mode']		= 'example';
	$good = \Nino\Modules\Backups::now( $lite );

	$damaged = random_bytes( 20000 );
	file_put_contents( $crm, $damaged );
	file_put_contents( $crm. '-wal', 'a log of writes that belongs to a database that is gone' );

	$notice	= null;
	hookSnapshotsGone( $lite );
	$result	= \Nino\Modules\Backups\Admin::restore( $lite, (string) $good, $notice );
	$snap		= array_values( array_filter( hookIds( $lite ), static fn( string $name ): bool => str_starts_with( $name, 'pre-restore-' ) ) );
	$kept		= count( $snap ) === 1 ? hookArchive( $lite, $snap[0] ) : [];

	check( 'a database that is broken, and the hook of the manual that throws on it: the restore of the good backup is done, and the database is whole again', is_string( $good ) === true && $result === true && ( $notice['code'] ?? '' ) === 'backups_snapshot_raw' && $count( $crm ) === [ 3, 'ok' ] && is_file( $crm. '-wal' ) === false );
	check( '...and the snapshot holds the broken state as it was, byte for byte', ( $kept['data/crm.sqlite'] ?? '' ) === $damaged );

	// ...and on the recovery page, which opens the archive with a key that was typed
	file_put_contents( $crm, $damaged );
	$result = \Nino\Modules\Backups\Admin::restore( $lite, (string) $good, $notice, (string) base64_decode( (string) $lite['/nino/backup/key'], true ) );
	check( '...and so is the restore of the recovery page', $result === true && $count( $crm ) === [ 3, 'ok' ] );
}

// --- A restore callback that throws, and the length of a copy -------------------------------------------------

echo "\nA restore callback that throws\n";

$cb				= hookProject( 'backup-hook-callback' );
$cbData		= \Nino\Filesystem::path( $cb, '/data' );
ninoUser( $cb, 'dev@example.com' );
$cb['./vault/files'] = [ 'vault.db' => 'copy of vault' ];
$cbArchive	= (string) \Nino\Modules\Backups::now( $cb );

/** The state a restore starts from: files that differ from the archive's, and a key in config.php that the archive does not have */
$cbLive = static function() use ( &$cb, $cbData ): void {

	file_put_contents( $cbData. '/vault.db', 'live vault' );
	file_put_contents( $cbData. '/other.db', 'live other' );
	file_put_contents( $cbData. '/vault-dir/a.db', 'live a' );
	$config = \Nino\Filesystem::getFileContent( $cb, '/config.php', [] );
	$config['/project/probe/epoch'] = 'live';
	\Nino\Filesystem::putFileContent( $cb, '/config.php', $config );
	unset( $cb['./other/restored'], $cb['./vault/restored'] );
	hookSnapshotsGone( $cb );
};

/** One restore by the real action of the Backups panel ('panel') or by the one of the recovery page ('recovery'): [ status, body ] */
$cbRestore = static function( string $door ) use ( &$cb, $cbArchive ): array {

	if( $door === 'panel' ) {
		\Nino\Auth::loginUser( $cb, 'dev@example.com', 'correct horse battery staple' );

		return ninoCall( $cb, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $cbArchive ] );
	}

	\Nino\Runtime::setSessionValue( $cb, \Nino\Admin\Recovery::SESSION_KEY, true );
	$answer = ninoCall( $cb, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $cbArchive ], 'recovery/restore' );
	\Nino\Runtime::unsetSessionValue( $cb, \Nino\Admin\Recovery::SESSION_KEY );

	return $answer;
};

foreach( [ 'panel', 'recovery' ] as $door ) {

	$label = $door === 'panel' ? 'the Backups panel' : 'the recovery page';

	// A callback that throws before it changed anything
	$cbLive();
	$cb['./vault/restore'] = 'throw';
	ninoWarnings();
	[ $status, $body ] = $cbRestore( $door );
	$warned = implode( "\n", ninoWarnings() );
	$snapshots = array_values( array_filter( hookIds( $cb ), static fn( string $name ): bool => str_starts_with( $name, 'pre-restore-' ) ) );
	$config = \Nino\Filesystem::getFileContent( $cb, '/config.php', [] );

	check( $label. ': a restore callback that throws does not end the restore: the answer is a 500 with the code of a restore that is done in part, the key of the feature in the sentence and in a field of its own, and the archive that holds the state before',
		$status === 500 && ( $body['code'] ?? '' ) === 'backups_restore_callback' && ( $body['params'] ?? [] ) === [ 'vault', $snapshots[0] ?? '-' ] && ( $body['features'] ?? [] ) === [ 'vault' ] && count( $snapshots ) === 1 );
	check( '...it names no path of what the feature said, and the log holds it with the feature',
		str_contains( json_encode( $body ), '/secret' ) === false && str_contains( $warned, 'the feature "vault" threw RuntimeException while it restored its data: vault could not restore /secret/place/vault.db' ) === true );
	check( '...config.php is the archive\'s, and the files of the feature are put back as if it had no callback: what was staged is copied',
		isset( $config['/project/probe/epoch'] ) === false && file_get_contents( $cbData. '/vault.db' ) === 'copy of vault' );
	check( '...and the callback of the feature that comes after it ran, with the staged files of the archive, as did every other file of the restore',
		count( $cb['./other/restored'] ?? [] ) === 1 && ( $cb['./other/restored'][0]['staged'] ?? false ) === true && file_get_contents( $cbData. '/other.db' ) === 'copy of other' && hookTemp( $cb ) === [] );

	// ...before and after it changed something: what it rewrote in the staging is what is copied, what it put into the live file stays
	$cbLive();
	$cb['./vault/restore'] = 'throw-after';
	[ $status, $body ] = $cbRestore( $door );
	check( $label. ': a callback that rewrote its staged file and threw: the answer is the same, and the file in the staging as it is then is what is copied',
		$status === 500 && ( $body['code'] ?? '' ) === 'backups_restore_callback' && file_get_contents( $cbData. '/vault.db' ) === 'rewritten by the feature' && file_get_contents( $cbData. '/other.db' ) === 'copy of other' );

	$cbLive();
	$cb['./vault/restore'] = 'took-over';
	[ $status, $body ] = $cbRestore( $door );
	check( $label. ': a callback that restored its file itself, took it out of the staging and threw: the file it restored is left as it made it, and the answer names the feature',
		$status === 500 && ( $body['code'] ?? '' ) === 'backups_restore_callback' && ( $body['params'][0] ?? '' ) === 'vault' && file_get_contents( $cbData. '/vault.db' ) === 'restored by the feature itself' && file_get_contents( $cbData. '/other.db' ) === 'copy of other' );

	// An error is caught as an exception is
	$cbLive();
	$cb['./vault/restore'] = 'error';
	[ $status, $body ] = $cbRestore( $door );
	check( $label. ': a TypeError of the callback is caught as an exception is', $status === 500 && ( $body['code'] ?? '' ) === 'backups_restore_callback' && file_get_contents( $cbData. '/vault.db' ) === 'copy of vault' );

	// A callback that does its work is no failure, and the file it took out of the staging is not copied
	$cbLive();
	$cb['./vault/restore'] = 'take';
	[ $status, $body ] = $cbRestore( $door );
	check( $label. ': a callback that restored its file and took it out of the staging is a restore that is done: 200, and the file is as the feature made it',
		$status === 200 && ( $door === 'panel' ? ( $body['ok'] ?? false ) === true : ( $body['restoredDate'] ?? '' ) !== '' ) && file_get_contents( $cbData. '/vault.db' ) === 'restored by the feature itself' && file_get_contents( $cbData. '/other.db' ) === 'copy of other' );
}

// What a callback changes in its arguments is what the next one is handed, as doCallbacks() does
$cbLive();
$cb['./vault/restore'] = 'args';
$cbRestore( 'panel' );
check( 'a callback that sets a key of its arguments is seen by the callback that comes after it: the arguments are handed on by reference', ( $cb['./other/restored'][0]['marker'] ?? null ) === 'set by vault' );

// A restore that went through and left something out ends as one that succeeded does: the session ends, the token is a new one, the notices are in the
// answer, and the tries of the key that was typed are let go. One that stopped halfway ends where it is
$cbKey = (string) $cb['/nino/backup/key'];

foreach( [ 'panel', 'recovery' ] as $door ) {

	$label = $door === 'panel' ? 'the Backups panel' : 'the recovery page';

	$cbLive();
	$cb['./vault/restore']	= 'throw';
	$cb['./vault/mode']			= 'throw';
	$token = \Nino\Csrf::getToken( $cb );
	[ $status, $body ] = $door === 'panel' ? $cbRestore( 'panel' ) : ( static function() use ( &$cb, $cbArchive, $cbKey ): array {
		\Nino\Runtime::setSessionValue( $cb, \Nino\Admin\Recovery::SESSION_KEY, true );
		$answer = ninoCall( $cb, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $cbArchive, 'key' => $cbKey ], 'recovery/restore' );
		\Nino\Runtime::unsetSessionValue( $cb, \Nino\Admin\Recovery::SESSION_KEY );
		return $answer;
	} )();

	check( $label. ': a restore that went through and failed in its callback ends as one that succeeded does: the notice of the snapshot that is the state as it was is in the answer of the 500',
		$status === 500 && ( $body['code'] ?? '' ) === 'backups_restore_callback' && ( $body['notice']['code'] ?? '' ) === 'backups_snapshot_raw' && ( $body['notice']['params'] ?? [] ) === [ 'vault' ] );

	if( $door === 'panel' )
		check( '...the session of the account has ended and the csrf token is a new one', \Nino\Auth::getCurrentUser( $cb ) === false && \Nino\Runtime::getSessionValue( $cb, './nino/auth/current' ) === null && \Nino\Csrf::getToken( $cb ) !== $token );
	else
		check( '...the tries of the key that was typed are let go', \Nino\Feature::spent( $cb, 'recovery-key', 'recovery', 1 ) === false );

	// ...and one that stopped halfway keeps the session and the tries
	$cbLive();
	$cb['./vault/restore']	= 'faults';
	$cb['./vault/faults']		= [ 'cut' => static fn( string $path ): bool => $path === $cbData. '/vault.db' ];

	try {
		[ $status, $body ] = $door === 'panel' ? $cbRestore( 'panel' ) : ( static function() use ( &$cb, $cbArchive, $cbKey ): array {
			\Nino\Runtime::setSessionValue( $cb, \Nino\Admin\Recovery::SESSION_KEY, true );
			$answer = ninoCall( $cb, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $cbArchive, 'key' => $cbKey ], 'recovery/restore' );
			\Nino\Runtime::unsetSessionValue( $cb, \Nino\Admin\Recovery::SESSION_KEY );
			return $answer;
		} )();
	}
	finally {
		FaultFs::off();
	}

	check( $label. ': a restore that stopped halfway (a file that is cut) ends where it is: no notice, the session of the account stays, the tries of the key are kept',
		$status === 500 && ( $body['code'] ?? '' ) === 'backups_restore_partial' && isset( $body['notice'] ) === false
		&& ( $door === 'panel' ? \Nino\Auth::getCurrentUser( $cb ) !== false : \Nino\Feature::spent( $cb, 'recovery-key', 'recovery', 1 ) === true ) );
	\Nino\Feature::forget( $cb, 'recovery-key', 'recovery' );
}

unset( $cb['./vault/mode'], $cb['./vault/faults'] );

// The log line of the failed restore says which feature and which archive holds the state before
$cbLive();
$cb['./vault/restore'] = 'throw';
\Nino\Auth::loginUser( $cb, 'dev@example.com', 'correct horse battery staple' );
$cbResult = \Nino\Modules\Backups\Admin::restore( $cb, $cbArchive );
\Nino\Modules\Backups\Admin::recordRestore( $cb, 'dev@example.com', $cbArchive, $cbResult );
$cbLogged = implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $cb, 3 ) );
check( 'the line of the activity log says that the restore failed in part, the archive that holds the state before and the feature whose callback failed',
	is_array( $cbResult ) === true && str_contains( $cbLogged, 'in part, the state before is the archive pre-restore-' ) === true && str_contains( $cbLogged, ', the restore callback of vault failed' ) === true );

// A request that ends the way \Nino\Runtime::fail() ends it is the request's to end: it is not caught
$cbLive();
$cb['./vault/restore'] = 'fail';
$ended = null;
try {
	\Nino\Modules\Backups\Admin::restore( $cb, $cbArchive );
}
catch( \Nino\Failure $failure ) {
	$ended = $failure->getMessage();
}
$cbConfig = \Nino\Filesystem::getFileContent( $cb, '/config.php', [] );
check( 'what ends the request as \Nino\Runtime::fail() does is not caught by the restore, and the lock and the temporary files are gone: config.php is the archive\'s by then and the files of data/ are as they were, which the manual says',
	$ended === 'vault cannot go on' && hookTemp( $cb ) === [] && \Nino\Filesystem::lockFile( $cb, \Nino\Modules\Backups::LOCK_PATH, false ) === true && isset( $cbConfig['/project/probe/epoch'] ) === false && file_get_contents( $cbData. '/vault.db' ) === 'live vault' );
\Nino\Filesystem::unlockFile( $cb, \Nino\Modules\Backups::LOCK_PATH );

// --- A file that grew after it was put back, and a copy that fell short ----------------------------------------------

echo "\nThe length of what was put back\n";

foreach( [ 'panel', 'recovery' ] as $door ) {

	$label = $door === 'panel' ? 'the Backups panel' : 'the recovery page';
	$grew	 = 0;
	$busy	 = false;

	// A process that appends to the file at the moment the copy closes it: no other can come closer
	$cbLive();
	$cb['./vault/restore'] = 'faults';
	$cb['./vault/faults']	 = [ 'closed' => static function( string $path, string $mode ) use ( $cbData, &$grew, &$busy ): void {

		if( $busy === false && $path === $cbData. '/vault.db' && str_starts_with( $mode, 'w' ) === true ) {
			$busy = true;
			file_put_contents( $path, ' - written to after the copy', FILE_APPEND );
			$grew++;
			$busy = false;
		}
	} ];

	try {
		[ $status, $body ] = $cbRestore( $door );
	}
	finally {
		FaultFs::off();
	}

	check( $label. ': a database that a process wrote to right after it was put back is not a restore that is done in part: 200, and the file is the archive\'s with what was written after it',
		$grew === 1 && $status === 200 && file_get_contents( $cbData. '/vault.db' ) === 'copy of vault - written to after the copy' );

	// A copy that answers that it wrote everything and wrote half
	$cbLive();
	$cb['./vault/faults'] = [ 'cut' => static fn( string $path ): bool => $path === $cbData. '/vault.db' ];

	try {
		[ $status, $body ] = $cbRestore( $door );
	}
	finally {
		FaultFs::off();
	}

	$snapshots = array_values( array_filter( hookIds( $cb ), static fn( string $name ): bool => str_starts_with( $name, 'pre-restore-' ) ) );

	check( $label. ': a file that is shorter than its source after the copy is a restore that is done in part, with the archive that holds the state before',
		$status === 500 && ( $body['code'] ?? '' ) === 'backups_restore_partial' && ( $body['params'] ?? [] ) === [ $snapshots[0] ?? '-' ] && strlen( (string) file_get_contents( $cbData. '/vault.db' ) ) === 6 );
}

unset( $cb['./vault/restore'], $cb['./vault/faults'] );

// --- A database that is in use ---------------------------------------------------------------------------------------

echo "\nA database that is in use\n";

$lockList = '/proc/locks';

if( extension_loaded( 'pdo_sqlite' ) === false )
	echo "  --  - pdo_sqlite is not there: the databases that processes hold open are not made, the restore of a database in use is not tried\n";
else if( is_readable( $lockList ) === false )
	echo "  --  - /proc/locks cannot be read here: the restore sees no locks, and the rule that it puts the files back as it does any other is what the test below holds\n";

if( extension_loaded( 'pdo_sqlite' ) === true ) {

	$use			= hookProject( 'backup-hook-inuse', [ 'Other', 'Vault' ] );
	$useDir		= ninoSandboxDir( $use );
	$useData	= \Nino\Filesystem::path( $use, '/data' );
	$useDb		= $useData. '/crm.sqlite';
	$use['./vault/example'] = [ \Nino\Modules\DocHook::class, 'callbackBackup' ];
	ninoUser( $use, 'dev@example.com' );

	// The process that holds a database in the way a request does: 'txn' an open transaction that has written (rollback journal), 'wal' an open
	// connection of a database in write-ahead mode, 'dead' a writer that was killed in the middle of a transaction. It says 'ready', waits for a
	// line, ends what it holds and says how that went
	$holder = $useDir. '/holder.php';
	file_put_contents( $holder, '<?php
[ , $file, $mode ] = $argv;
$say = static function( string $line ): void { echo $line, "\n"; fflush( STDOUT ); };
$db = new PDO( $file === "" ? "sqlite::memory:" : "sqlite:". $file, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
$db->exec( "PRAGMA busy_timeout = 4000" );
$db->exec( "PRAGMA journal_mode = ". ( $mode === "wal" ? "WAL" : "DELETE" ) );
$rows = static function( int $count ) use ( $db ): void { $put = $db->prepare( "INSERT INTO t ( v ) VALUES ( ? )" ); for( $i = 0; $i < $count; $i++ ) $put->execute( [ str_repeat( "w", 400 ) ] ); };
if( $mode === "wal" ) {
	$db->exec( "PRAGMA wal_autocheckpoint = 0" );
	$rows( 20 );
	$db->query( "SELECT COUNT(*) FROM t" )->fetchColumn();
	$say( "ready" );
	fgets( STDIN );
	$rows( 20 );
	$say( "written" );
	exit;
}
if( $mode === "dead" )
	$db->exec( "PRAGMA cache_size = 5" );
$db->exec( "BEGIN IMMEDIATE" );
$rows( $mode === "dead" ? 2000 : 20 );
if( $mode === "slow" || $mode === "long" ) {
	$say( "ready" );
	usleep( $mode === "long" ? 6500000 : 600000 );
	$db->exec( "COMMIT" );
	$say( "committed" );
	exit;
}
$say( "ready" );
fgets( STDIN );
if( $mode === "dead" ) {
	posix_kill( getmypid(), SIGKILL );
	sleep( 5 );
}
try { $db->exec( "COMMIT" ); $say( "committed" ); } catch( Throwable $e ) { $say( "COMMIT failed: ". $e->getMessage() ); }
' );

	/** A database of the project: epoch and rows, in the journal mode named */
	$useMake = static function( string $mode, string $epoch ) use ( $useDb ): void {

		foreach( [ '', '-wal', '-shm', '-journal' ] as $suffix )
			@unlink( $useDb. $suffix );

		$pdo = new \PDO( 'sqlite:'. $useDb, null, null, [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ] );
		$pdo->exec( 'PRAGMA journal_mode = '. ( $mode === 'wal' ? 'WAL' : 'DELETE' ) );
		$pdo->exec( 'CREATE TABLE meta ( k TEXT PRIMARY KEY, v TEXT )' );
		$pdo->exec( 'CREATE TABLE t ( id INTEGER PRIMARY KEY, v TEXT )' );
		$pdo->exec( "INSERT INTO meta VALUES ( 'epoch', '". $epoch. "' )" );
		$pdo->beginTransaction();

		for( $row = 1; $row <= 200; $row++ )
			$pdo->exec( "INSERT INTO t ( v ) VALUES ( '". str_repeat( 'a', 400 ). "' )" );

		$pdo->commit();
		$pdo = null;
	};

	/** What a database holds: [ integrity, epoch, rows ] - opened by a connection of its own, which closes before it answers */
	$useRead = static function() use ( $useDb ): array {

		try {
			$pdo = new \PDO( 'sqlite:'. $useDb, null, null, [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ] );
			$pdo->exec( 'PRAGMA busy_timeout = 4000' );
			$read = [ (string) $pdo->query( 'PRAGMA integrity_check' )->fetchColumn(), (string) $pdo->query( "SELECT v FROM meta WHERE k = 'epoch'" )->fetchColumn(), (int) $pdo->query( 'SELECT COUNT(*) FROM t' )->fetchColumn() ];
			$pdo = null;

			return $read;
		}
		catch( \Throwable $e ) {
			return [ 'unreadable: '. $e->getMessage(), '', 0 ];
		}
	};

	/** Start the process that holds the database: [ process, pipes ], waiting for its 'ready' */
	$useHold = static function( string $mode ) use ( $holder, $useDb ): array {

		$process = proc_open( [ PHP_BINARY, $holder, $useDb, $mode ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ] ], $pipes );
		$ready	 = is_resource( $process ) === true ? trim( (string) fgets( $pipes[1] ) ) : '';

		return [ $process, $pipes, $ready ];
	};

	/** End what the process holds, and what it says of that */
	$useEnd = static function( array $held ): string {

		[ $process, $pipes ] = $held;
		@fwrite( $pipes[0], "go\n" );
		$said = trim( (string) fgets( $pipes[1] ) );
		fclose( $pipes[0] );
		fclose( $pipes[1] );
		proc_close( $process );

		return $said;
	};

	/** The restore of the archive of the project, by the real action: [ status, body ] */
	$useRestore = static function( string $door, string $date ) use ( &$use ): array {

		if( $door === 'panel' ) {
			\Nino\Auth::loginUser( $use, 'dev@example.com', 'correct horse battery staple' );

			return ninoCall( $use, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $date ] );
		}

		\Nino\Runtime::setSessionValue( $use, \Nino\Admin\Recovery::SESSION_KEY, true );
		$answer = ninoCall( $use, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $date ], 'recovery/restore' );
		\Nino\Runtime::unsetSessionValue( $use, \Nino\Admin\Recovery::SESSION_KEY );

		return $answer;
	};

	// The archive: the state 'A' of the database, a copy that is whole, and the file of the other feature
	$useMake( 'delete', 'A' );
	$use['./vault/mode'] = 'example';
	$useArchive = (string) \Nino\Modules\Backups::now( $use );
	$useStamp		= hookStamp( $use, hookArchive( $use, $useArchive ) );
	check( 'the archive of the database lists it as handed over, the premise of what follows', ( $useStamp['copies'] ?? [] ) === [ 'data/other.db', 'data/crm.sqlite' ] && $useRead() === [ 'ok', 'A', 200 ] );

	// What the locks of a list say, whatever process holds them: the list is read for the device and the inode of the live file
	$useStat = stat( $useDb );
	$useMajor = ( ( $useStat['dev'] >> 8 ) & 0xfff ) | ( ( $useStat['dev'] >> 32 ) & ~0xfff );
	$useMinor = ( $useStat['dev'] & 0xff ) | ( ( $useStat['dev'] >> 12 ) & ~0xff );
	$useLine	= static fn( int $number, string $kind, int $ino, string $arrow = '' ): string => sprintf( '%d: %s%s ADVISORY  WRITE 4242 %02x:%02x:%d 1073741824 1073741824', $number, $arrow, $kind, $useMajor, $useMinor, $ino );
	$useStage = $useDir. '/stage-locks';
	mkdir( $useStage. '/data', 0755, true );
	file_put_contents( $useStage. '/data/crm.sqlite', 'x' );
	file_put_contents( $useStage. '/data/other.db', 'x' );
	$useList = $useDir. '/locks-list';
	$useSees = static function( string $text ) use ( &$use, $useStage, $useList ): array {

		file_put_contents( $useList, $text );
		\Nino\Backup::$locksFile = $useList;
		$names = \Nino\Backup::inUse( $use, $useStage, [ 'data/other.db', 'data/crm.sqlite' ] );
		\Nino\Backup::$locksFile = null;

		return $names;
	};
	check( 'a list of locks that holds a POSIX lock on the device and inode of the file names it, and nothing else does: not a lock of another kind (flock), not another inode, not a line that is none',
		$useSees( $useLine( 1, 'POSIX', $useStat['ino'] ). "\n". $useLine( 2, 'FLOCK', $useStat['ino'] + 1 ). "\nnonsense\n\n" ) === [ 'data/crm.sqlite' ]
		&& $useSees( $useLine( 1, 'FLOCK', $useStat['ino'] ). "\n". $useLine( 2, 'POSIX', $useStat['ino'] + 7 ). "\n" ) === [] && $useSees( '' ) === [] );
	check( '...a process that waits for a lock on the file shows in the list as well, and a list that is not there says nothing, which is none: the files are put back',
		$useSees( $useLine( 3, 'POSIX', $useStat['ino'], '-> ' ). "\n" ) === [ 'data/crm.sqlite' ] && $useSees( $useLine( 1, 'POSIX', $useStat['ino'] ). "\n" ) !== []
		&& ( static function() use ( &$use, $useStage ): array { \Nino\Backup::$locksFile = '/nowhere/locks'; $names = \Nino\Backup::inUse( $use, $useStage, [ 'data/crm.sqlite' ] ); \Nino\Backup::$locksFile = null; return $names; } )() === [] );
	check( '...an archive of another project is not believed about what it hands over: nothing is named for it, and no name of a file that is none of the feature\'s',
		( static function() use ( &$use, $useStage, $useList, $useLine, $useStat ): array { file_put_contents( $useList, $useLine( 1, 'POSIX', $useStat['ino'] ). "\n" ); \Nino\Backup::$locksFile = $useList; $names = \Nino\Backup::inUse( $use, $useStage, [ 'data/crm.sqlite' ], true ); \Nino\Backup::$locksFile = null; return $names; } )() === []
		&& $useSees( $useLine( 1, 'POSIX', $useStat['ino'] ). "\n" ) === [ 'data/crm.sqlite' ] );

	// What config.php can hold - every key of it is merged into the app data, an archive's among them - does not point the check of the locks elsewhere nor name the room of the disk
	file_put_contents( $useList, $useLine( 1, 'POSIX', $useStat['ino'] ). "\n" );
	$use['./nino/backup/locks']	 = $useList;
	$use['./nino/backups/disk']	 = 0;
	check( 'a key of the app data that names a list of locks, or a number of free bytes, is not read: the real list is asked, and the real disk',
		\Nino\Backup::inUse( $use, $useStage, [ 'data/crm.sqlite' ] ) === [] && ( \Nino\Backup::freeSpace( $use, $useDir ) ?? 1 ) > 0 );
	unset( $use['./nino/backup/locks'], $use['./nino/backups/disk'] );

	// A file that no feature with a callback of '/nino/admin/backup' owns is not asked about, whatever the list of the archive says and whatever lies on it
	$plainStat = stat( $useData. '/plain.db' );
	file_put_contents( $useStage. '/data/plain.db', 'x' );
	file_put_contents( $useList, $useLine( 1, 'POSIX', $useStat['ino'] ). "\n". $useLine( 2, 'POSIX', $plainStat['ino'] ). "\n" );
	\Nino\Backup::$locksFile = $useList;
	$ownedOnly = \Nino\Backup::inUse( $use, $useStage, [ 'data/crm.sqlite', 'data/plain.db' ] );
	\Nino\Backup::$locksFile = null;
	check( 'a file of a feature without a callback of \'/nino/admin/backup\' is not asked about the locks, though the archive lists it and a lock lies on it: it is put back as any file is', $ownedOnly === [ 'data/crm.sqlite' ] );

	if( is_readable( $lockList ) === true ) {

		// An open transaction that has written, in rollback mode; and a connection of a database in write-ahead mode
		foreach( [ 'txn' => 'delete', 'wal' => 'wal' ] as $mode => $journal ) {

			$useMake( $journal, 'A' );
			$use['./vault/mode'] = 'example';
			$useArchive = (string) \Nino\Modules\Backups::now( $use );

			$pdo = new \PDO( 'sqlite:'. $useDb );
			$pdo->exec( "UPDATE meta SET v = 'B' WHERE k = 'epoch'" );
			$pdo = null;
			file_put_contents( $useData. '/other.db', 'live other' );
			$held		= $useHold( $mode );
			$before	= sha1_file( $useDb );
			$beside	= array_values( array_filter( [ '-wal', '-shm', '-journal' ], static fn( string $suffix ): bool => is_file( $useDb. $suffix ) ) );

			check( 'a process holds the database ('. ( $mode === 'txn' ? 'an open transaction that has written, rollback journal' : 'a connection that is open, write-ahead log' ). '): the premise, with the files beside it as that mode has them',
				$held[2] === 'ready' && $beside === ( $mode === 'txn' ? [ '-journal' ] : [ '-wal', '-shm' ] ) );

			foreach( [ 'panel', 'recovery' ] as $door ) {

				$label = $door === 'panel' ? 'the Backups panel' : 'the recovery page';
				hookSnapshotsGone( $use );
				ninoWarnings();
				[ $status, $body ] = $useRestore( $door, $useArchive );
				$warned		 = implode( "\n", ninoWarnings() );
				$snapshots = array_values( array_filter( hookIds( $use ), static fn( string $name ): bool => str_starts_with( $name, 'pre-restore-' ) ) );

				check( $label. ', '. $mode. ': the file is not put back and the answer says so: a 500 with the code of files that are in use, their number in the sentence, their names in a field of their own and the archive that holds the state before',
					$status === 500 && ( $body['code'] ?? '' ) === 'backups_restore_inuse' && ( $body['params'] ?? [] ) === [ 1, $snapshots[0] ?? '-' ] && ( $body['files'] ?? [] ) === [ 'data/crm.sqlite' ] && str_contains( $warned, 'data/crm.sqlite is in use by another process and was left as it is' ) === true );
				check( '...the file is the bytes it was and the files beside it are where they were',
					sha1_file( $useDb ) === $before && array_values( array_filter( [ '-wal', '-shm', '-journal' ], static fn( string $suffix ): bool => is_file( $useDb. $suffix ) ) ) === $beside );
				check( '...and everything else of the archive is restored: the file of the other feature, and config.php',
					file_get_contents( $useData. '/other.db' ) === 'copy of other' && hookTemp( $use ) === [] );
				check( '...the line of the activity log names the file that was left as it is, and the archive that holds the state before',
					str_contains( implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $use, 3 ) ), 'in part, the state before is the archive '. ( $snapshots[0] ?? '-' ). ', data/crm.sqlite in use and left as it is' ) === true );
				check( '...and the restore ended as one that went through does: the session of the account that ran it has ended (the Backups panel)',
					$door === 'recovery' || ( \Nino\Auth::getCurrentUser( $use ) === false && \Nino\Runtime::getSessionValue( $use, './nino/auth/current' ) === null ) );
				file_put_contents( $useData. '/other.db', 'live other' );
			}

			$said = $useEnd( $held );
			check( '...the process that held the database ends its transaction without a failure, and the database is whole and has what the process wrote: nothing was written under its feet',
				$said === ( $mode === 'txn' ? 'committed' : 'written' ) && $useRead() === [ 'ok', 'B', $mode === 'txn' ? 220 : 240 ] );

			// Nothing holds it any more: the same restore puts it back
			hookSnapshotsGone( $use );
			[ $status, $body ] = $useRestore( 'panel', $useArchive );
			check( 'the restore, repeated when the process is gone, puts the file back and removes the files beside it: the database is whole and is the archive\'s',
				$status === 200 && ( $body['ok'] ?? false ) === true && $useRead() === [ 'ok', 'A', 200 ] && is_file( $useDb. '-journal' ) === false && is_file( $useDb. '-wal' ) === false );
		}

		// A writer that was killed in the middle of a transaction: a hot journal and no lock
		$useMake( 'delete', 'A' );
		$use['./vault/mode'] = 'example';
		$useArchive = (string) \Nino\Modules\Backups::now( $use );
		$pdo = new \PDO( 'sqlite:'. $useDb );
		$pdo->exec( "UPDATE meta SET v = 'B' WHERE k = 'epoch'" );
		$pdo = null;
		$held = $useHold( 'dead' );
		$useEnd( $held );
		clearstatcache();
		check( 'a writer that was killed in the middle of a transaction leaves a hot journal and no lock on the file: the premise', $held[2] === 'ready' && is_file( $useDb. '-journal' ) === true && filesize( $useDb. '-journal' ) > 0 );

		foreach( [ 'panel', 'recovery' ] as $door ) {

			if( $door === 'recovery' ) {
				$useMake( 'delete', 'A' );
				$pdo = new \PDO( 'sqlite:'. $useDb );
				$pdo->exec( "UPDATE meta SET v = 'B' WHERE k = 'epoch'" );
				$pdo = null;
				$held = $useHold( 'dead' );
				$useEnd( $held );
			}

			hookSnapshotsGone( $use );
			[ $status, $body ] = $useRestore( $door, $useArchive );
			check( ( $door === 'panel' ? 'the Backups panel' : 'the recovery page' ). ': a file that no process holds is put back, and the hot journal of the writer that died is removed: 200, and the database is whole and is the archive\'s',
				$status === 200 && $useRead() === [ 'ok', 'A', 200 ] && is_file( $useDb. '-journal' ) === false );
		}

		// Where the list of the locks cannot be read nothing is known: the file is put back as every other file is
		$useMake( 'delete', 'A' );
		$use['./vault/mode'] = 'example';
		$useArchive = (string) \Nino\Modules\Backups::now( $use );
		$archived		= hookArchive( $use, $useArchive )['data/crm.sqlite'] ?? '';
		$pdo = new \PDO( 'sqlite:'. $useDb );
		$pdo->exec( "UPDATE meta SET v = 'B' WHERE k = 'epoch'" );
		$pdo = null;
		$held = $useHold( 'txn' );
		\Nino\Backup::$locksFile = $useDir. '/no-such-list';
		hookSnapshotsGone( $use );
		[ $status, $body ] = $useRestore( 'panel', $useArchive );
		\Nino\Backup::$locksFile = null;
		$putBack = file_get_contents( $useDb );
		$useEnd( $held );
		check( 'where the list of the locks cannot be read the restore says nothing of them and puts the file back, as it does any other file: 200, the bytes are the archive\'s',
			$held[2] === 'ready' && $status === 200 && ( $body['ok'] ?? false ) === true && $putBack === $archived );
	}

	// --- What happens together: a callback that fails, files that are in use, a file that is cut, a file that is taken out and one that is held late ---

	if( is_readable( $lockList ) === true ) {

		echo "\nOutcomes that meet\n";

		/** A process that holds the database $file in the way $mode says: [ process, pipes, what it said first ] */
		$holdAt = static function( string $file, string $mode ) use ( $holder ): array {

			$process = proc_open( [ PHP_BINARY, $holder, $file, $mode ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ] ], $pipes );

			return [ $process, $pipes, is_resource( $process ) === true ? trim( (string) fgets( $pipes[1] ) ) : '' ];
		};

		/**
		 *	One restore of the database of the manual's feature with what $o says: 'restore' the mode of the restore callback of the feature, 'hold' the
		 *	process that holds the database while it runs, 'faults' a function that makes the faults of FaultFs and may start a process into $box['held'],
		 *	'stale' a journal that lies beside the database. The snapshot of the restore is the state as it was (the feature's backup callback throws), so
		 *	its notice is in every answer
		 */
		$meet = static function( string $door, array $o ) use ( &$use, $useMake, $useDb, $useData, $useRestore, $useEnd, $holdAt ): array {

			$useMake( 'delete', 'A' );
			$use['./vault/mode']	= 'example';
			$use['./vault/example'] = [ \Nino\Modules\DocHook::class, 'callbackBackup' ];
			hookSnapshotsGone( $use );
			$date = (string) \Nino\Modules\Backups::now( $use );
			$pdo	= new \PDO( 'sqlite:'. $useDb );
			$pdo->exec( "UPDATE meta SET v = 'B' WHERE k = 'epoch'" );
			$pdo	= null;
			file_put_contents( $useData. '/other.db', 'live other' );

			if( isset( $o['stale'] ) === true )
				file_put_contents( $useDb. '-journal', str_repeat( "\0", 512 ) );

			$use['./vault/mode']		= 'throw';
			$use['./vault/restore']	= $o['restore'] ?? '';
			$box										= [ 'held' => [] ];

			if( isset( $o['faults'] ) === true ) {
				$use['./vault/restore']	= 'faults';
				$use['./vault/faults']	= $o['faults']( $box );
			}

			if( isset( $o['hold'] ) === true )
				$box['held'][] = $holdAt( $useDb, $o['hold'] );

			$before = sha1_file( $useDb );
			$token	= \Nino\Csrf::getToken( $use );
			ninoWarnings();

			try {
				[ $status, $body ] = $useRestore( $door, $date );
			}
			finally {
				FaultFs::off();
			}

			$warned	= implode( "\n", ninoWarnings() );
			$logged	= implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $use, 3 ) );
			$after	= sha1_file( $useDb );
			$ended	= \Nino\Auth::getCurrentUser( $use ) === false && \Nino\Csrf::getToken( $use ) !== $token;
			$said		= [];

			foreach( $box['held'] as $held )
				$said[] = $useEnd( $held );

			unset( $use['./vault/restore'], $use['./vault/faults'], $use['./vault/mode'] );

			$snapshots = array_values( array_filter( hookIds( $use ), static fn( string $name ): bool => str_starts_with( $name, 'pre-restore-' ) ) );

			return [ 'status' => $status, 'body' => $body, 'warned' => $warned, 'logged' => $logged, 'same' => $before === $after, 'ended' => $ended, 'said' => $said, 'snapshot' => $snapshots[0] ?? '-', 'other' => (string) file_get_contents( $useData. '/other.db' ) ];
		};

		foreach( [ 'panel', 'recovery' ] as $door ) {

			$label = $door === 'panel' ? 'the Backups panel' : 'the recovery page';

			// A callback that throws and a file in use: the answer says both, and the file is named
			$r = $meet( $door, [ 'restore' => 'throw', 'hold' => 'txn' ] );
			check( $label. ': a callback that throws and a database in use: the answer is a 500 with the code that says both, the feature and the number of the files in its sentence, and the names in both of its fields',
				$r['status'] === 500 && ( $r['body']['code'] ?? '' ) === 'backups_restore_both' && ( $r['body']['params'] ?? [] ) === [ 'vault', 1, $r['snapshot'] ] && ( $r['body']['features'] ?? [] ) === [ 'vault' ] && ( $r['body']['files'] ?? [] ) === [ 'data/crm.sqlite' ] );
			check( '...the file is as it was, the rest of the archive is restored, and the line of the activity log says both',
				$r['same'] === true && $r['other'] === 'copy of other' && str_contains( $r['logged'], ', the restore callback of vault failed' ) === true && str_contains( $r['logged'], ', data/crm.sqlite in use and left as it is' ) === true );
			check( '...and the restore ended as one that went through does: the notice is in the answer'. ( $door === 'panel' ? ', the session has ended and the token is a new one' : '' ),
				( $r['body']['notice']['code'] ?? '' ) === 'backups_snapshot_raw' && ( $door === 'recovery' || $r['ended'] === true ) );

			// A callback that takes a file out of the staging and then fails: the file is named as one that was not put back
			$r = $meet( $door, [ 'restore' => 'took-crm' ] );
			check( $label. ': a callback that took the database out of the staging and then threw: the answer is the one of a callback that failed, and the file is named in a field of its own as not put back by the restore',
				$r['status'] === 500 && ( $r['body']['code'] ?? '' ) === 'backups_restore_callback' && ( $r['body']['features'] ?? [] ) === [ 'vault' ] && ( $r['body']['taken'] ?? [] ) === [ 'data/crm.sqlite' ] && $r['same'] === true && $r['other'] === 'copy of other'
				&& str_contains( $r['logged'], ', data/crm.sqlite not put back by the restore: the callback of the feature took it out and failed' ) === true );

			// A file that is cut and a file in use: the restore that stopped halfway still names the file it left
			$r = $meet( $door, [ 'hold' => 'txn', 'faults' => static fn( array &$box ): array => [ 'cut' => static fn( string $path ): bool => str_ends_with( $path, '/data/other.db' ) ] ] );
			check( $label. ': a file that is cut and a database in use: the answer is the one of a restore that stopped halfway, and names the file that was left as it is',
				$r['status'] === 500 && ( $r['body']['code'] ?? '' ) === 'backups_restore_partial' && ( $r['body']['params'] ?? [] ) === [ $r['snapshot'] ] && ( $r['body']['files'] ?? [] ) === [ 'data/crm.sqlite' ] && $r['same'] === true
				&& str_contains( $r['logged'], 'in part, the state before is the archive '. $r['snapshot']. ', data/crm.sqlite in use and left as it is' ) === true && isset( $r['body']['notice'] ) === false && ( $door === 'recovery' || $r['ended'] === false ) );

			// A process that takes its lock after the first look: the second look, right before data/ is copied, finds it
			$r = $meet( $door, [ 'stale' => true, 'faults' => static function( array &$box ) use ( $useDb, $holdAt ): array {
				return [ 'unlink' => static function( string $path ) use ( &$box, $useDb, $holdAt ): bool {
					if( $path === $useDb. '-journal' && $box['held'] === [] )
						$box['held'][] = $holdAt( $useDb, 'txn' );
					return false;
				} ];
			} ] );
			check( $label. ': a process that takes its lock after the first look and before data/ is copied: the second look finds it, the file is left as it is and named',
				$r['status'] === 500 && ( $r['body']['code'] ?? '' ) === 'backups_restore_inuse' && ( $r['body']['files'] ?? [] ) === [ 'data/crm.sqlite' ] && $r['same'] === true && $r['other'] === 'copy of other' && ( $r['said'][0] ?? '' ) !== '' );

			// A process that takes its lock while the file is copied: it cannot be undone, and the answer says that the file may be damaged
			$r = $meet( $door, [ 'faults' => static function( array &$box ) use ( $useDb, $holdAt ): array {
				return [ 'closed' => static function( string $path, string $mode ) use ( &$box, $useDb, $holdAt ): void {
					if( $path === $useDb && str_starts_with( $mode, 'w' ) === true && $box['held'] === [] )
						$box['held'][] = $holdAt( $useDb, 'txn' );
				} ];
			} ] );
			check( $label. ': a process that takes its lock while the file is copied: the answer has the code of a file that was written while it was in use, the name in a field of its own, and the line of the log says so',
				$r['status'] === 500 && ( $r['body']['code'] ?? '' ) === 'backups_restore_written' && ( $r['body']['params'] ?? [] ) === [ 1, $r['snapshot'] ] && ( $r['body']['written'] ?? [] ) === [ 'data/crm.sqlite' ]
				&& str_contains( $r['logged'], ', data/crm.sqlite written while in use and possibly damaged' ) === true && str_contains( $r['warned'], 'data/crm.sqlite was in use by another process while it was put back and may be damaged' ) === true );
			check( '...and it ends as a restore that went through does: the notice is in the answer'. ( $door === 'panel' ? ', the session has ended and the token is a new one' : '' ),
				( $r['body']['notice']['code'] ?? '' ) === 'backups_snapshot_raw' && ( $door === 'recovery' || $r['ended'] === true ) );

			// ...and where a copy fell short as well, the restore stopped halfway: that is the answer, with the file that was written, and it ends where it is
			$r = $meet( $door, [ 'faults' => static function( array &$box ) use ( $useDb, $holdAt ): array {
				return [ 'cut' => static fn( string $path ): bool => str_ends_with( $path, '/data/other.db' ), 'closed' => static function( string $path, string $mode ) use ( &$box, $useDb, $holdAt ): void {
					if( $path === $useDb && str_starts_with( $mode, 'w' ) === true && $box['held'] === [] )
						$box['held'][] = $holdAt( $useDb, 'txn' );
				} ];
			} ] );
			check( $label. ': a file that was written while it was in use and a copy that fell short: the answer is the one of a restore that stopped halfway, with the file that was written and the entry that is not whole in fields of their own, and the restore ends where it is',
				$r['status'] === 500 && ( $r['body']['code'] ?? '' ) === 'backups_restore_partial' && ( $r['body']['params'] ?? [] ) === [ $r['snapshot'] ] && ( $r['body']['written'] ?? [] ) === [ 'data/crm.sqlite' ] && ( $r['body']['cut'] ?? [] ) === [ 'data' ]
				&& isset( $r['body']['notice'] ) === false && ( $door === 'recovery' || $r['ended'] === false ) );
			check( '...the line of the activity log and the log of errors name both: the copy that is not whole and the file that was written while it was in use',
				str_contains( $r['logged'], 'in part, the state before is the archive '. $r['snapshot'] ) === true && str_contains( $r['logged'], ', data/crm.sqlite written while in use and possibly damaged' ) === true && str_contains( $r['logged'], ', the copy of data was not whole' ) === true
				&& str_contains( $r['warned'], 'the copy of data is not whole' ) === true && str_contains( $r['warned'], 'data/crm.sqlite was in use by another process while it was put back' ) === true );
		}

		// A file that is held takes nothing from the files beside another file that was handed over: the guard on the number of files is the archive's
		$duo = hookProject( 'backup-hook-duo', [ 'Other', 'Vault' ] );
		$duoData = \Nino\Filesystem::path( $duo, '/data' );

		foreach( array_diff( scandir( $duoData ) ?: [], [ '.', '..' ] ) as $left )
			\Nino\Filesystem::removeDir( $duoData. '/'. $left );

		ninoUser( $duo, 'dev@example.com' );
		$pdo = new \PDO( 'sqlite:'. $duoData. '/crm.sqlite', null, null, [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ] );
		$pdo->exec( 'CREATE TABLE t ( id INTEGER PRIMARY KEY, v TEXT )' );
		$pdo = null;
		file_put_contents( $duoData. '/other.db', 'live other' );
		$duo['./vault/example']	= [ \Nino\Modules\DocHook::class, 'callbackBackup' ];
		$duo['./vault/mode']		= 'example';
		$duoId = (string) \Nino\Modules\Backups::now( $duo );
		$duo['./vault/mode']		= 'throw';
		file_put_contents( $duoData. '/other.db-shm', 'live shm' );
		file_put_contents( $duoData. '/other.db-journal', 'live journal' );
		$held = $holdAt( $duoData. '/crm.sqlite', 'txn' );
		\Nino\Auth::loginUser( $duo, 'dev@example.com', 'correct horse battery staple' );
		[ $status, $body ] = ninoCall( $duo, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $duoId ] );
		$useEnd( $held );
		check( 'where the archive hands over two files and one is held, the files beside the other are taken away as they are for a file that is put back: what the restore leaves out is no change in what it counts',
			( $body['code'] ?? '' ) === 'backups_restore_inuse' && is_file( $duoData. '/other.db-shm' ) === false && is_file( $duoData. '/other.db-journal' ) === false && file_get_contents( $duoData. '/other.db' ) === 'copy of other' );
	}

	// --- A feature that restores its database itself, with the example of the manual -------------------------------------
	echo "\nThe restore callback of the manual\n";

	if( extension_loaded( 'sqlite3' ) === false )
		echo "  --  - ext/sqlite3 is not there: the online backup of the manual's callback cannot be run; the callback leaves the staged file in the staging, which is the rule of the files in use above\n";
	else {

		$manual = (string) file_get_contents( dirname( __DIR__ ). '/docs/features.md' );
		preg_match( '/### Data, backup and restore(.*?)\n## /s', $manual, $section );
		preg_match_all( '/```php\n(.*?)```/s', (string) ( $section[1] ?? '' ), $blocks );
		$restoreExample = '';

		foreach( $blocks[1] as $block )
			if( str_contains( $block, 'function callbackRestore' ) === true )
				$restoreExample = $block;

		check( 'the manual has the example of the restore callback', $restoreExample !== '' && str_contains( $restoreExample, 'backup(' ) === true && str_contains( $restoreExample, 'unlink( $staged )' ) === true );
		eval( 'namespace Nino\Modules; class DocRestore { '. $restoreExample. ' }' );

		// The database of the feature of the manual is crm.sqlite; the backup callback of the manual makes the copy
		$crm = $useData. '/crm.sqlite';

		foreach( [ 'delete', 'wal' ] as $journal ) {

			foreach( [ '', '-wal', '-shm', '-journal' ] as $suffix )
				@unlink( $crm. $suffix );

			$pdo = new \PDO( 'sqlite:'. $crm, null, null, [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ] );
			$pdo->exec( 'PRAGMA journal_mode = '. ( $journal === 'wal' ? 'WAL' : 'DELETE' ) );
			$pdo->exec( 'CREATE TABLE t ( id INTEGER PRIMARY KEY, v TEXT )' );
			$pdo->exec( "INSERT INTO t ( v ) VALUES ( 'one' ), ( 'two' ), ( 'three' )" );
			$pdo = null;

			$use['./vault/example']	= [ \Nino\Modules\DocHook::class, 'callbackBackup' ];
			$use['./vault/mode']		= 'example';
			$docArchive = (string) \Nino\Modules\Backups::now( $use );

			$pdo = new \PDO( 'sqlite:'. $crm, null, null, [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ] );
			$pdo->exec( "INSERT INTO t ( v ) VALUES ( 'four' )" );
			$pdo = null;

			// Another process holds the database in a transaction that it ends by itself after a while
			$crmHolder = $useDir. '/holder-crm.php';
			file_put_contents( $crmHolder, '<?php
$db = new PDO( "sqlite:". $argv[1], null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
$db->exec( "PRAGMA busy_timeout = 4000" );
$db->exec( "BEGIN IMMEDIATE" );
$db->exec( "INSERT INTO t ( v ) VALUES ( \'five\' )" );
echo "ready\n"; fflush( STDOUT );
usleep( 500000 );
try { $db->exec( "COMMIT" ); echo "committed\n"; } catch( Throwable $e ) { echo "COMMIT failed: ", $e->getMessage(), "\n"; }
' );
			$process	= proc_open( [ PHP_BINARY, $crmHolder, $crm ], [ 1 => [ 'pipe', 'w' ] ], $crmPipes );
			$ready		= trim( (string) fgets( $crmPipes[1] ) );

			$use['./vault/restore']					= 'example';
			$use['./vault/example-restore']	= [ \Nino\Modules\DocRestore::class, 'callbackRestore' ];
			unset( $use['./vault/mode'] );
			$use['./vault/files'] = [];
			hookSnapshotsGone( $use );
			ninoWarnings();
			$result = \Nino\Modules\Backups\Admin::restore( $use, $docArchive );
			$said		= trim( (string) fgets( $crmPipes[1] ) );
			fclose( $crmPipes[1] );
			proc_close( $process );
			$open = new \PDO( 'sqlite:'. $crm, null, null, [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ] );
			$state = [ (string) $open->query( 'PRAGMA integrity_check' )->fetchColumn(), (int) $open->query( 'SELECT COUNT(*) FROM t' )->fetchColumn(), (string) $open->query( 'PRAGMA journal_mode' )->fetchColumn() ];
			$open = null;
			unset( $use['./vault/restore'], $use['./vault/example-restore'] );

			check( 'the callback of the manual, '. $journal. ' mode, a process that holds the database in a transaction for a while: the restore is done, the callback took its file out of the staging, the database is whole and is the archive\'s (3 rows), the journal mode is what it was',
				$ready === 'ready' && $result === true && $said === 'committed' && $state === [ 'ok', 3, $journal ] && is_file( $crm. '-journal' ) === false && ( $use['./vault/staged-after'] ?? true ) === false );
		}
		// The callback of the manual where a process holds the database longer than its busy timeout (5 s): it throws, the restore goes on, and the file that
		// is held and still staged is left as it is - the answer says both, which it must not say as one of them alone
		if( isset( $meet ) === true ) {

			$use['./vault/example-restore'] = [ \Nino\Modules\DocRestore::class, 'callbackRestore' ];

			foreach( [ 'panel', 'recovery' ] as $door ) {

				$r = $meet( $door, [ 'restore' => 'example', 'hold' => 'long' ] );
				check( ( $door === 'panel' ? 'the Backups panel' : 'the recovery page' ). ': the callback of the manual and a process that holds the database for longer than the callback waits: the answer says that the feature failed and that the file was left as it is, the file is as it was, the process ended its transaction',
					$r['status'] === 500 && ( $r['body']['code'] ?? '' ) === 'backups_restore_both' && ( $r['body']['features'] ?? [] ) === [ 'vault' ] && ( $r['body']['files'] ?? [] ) === [ 'data/crm.sqlite' ] && $r['same'] === true && $r['said'] === [ 'committed' ]
					&& str_contains( $r['warned'], 'the feature "vault" threw' ) === true && str_contains( $r['logged'], ', data/crm.sqlite in use and left as it is' ) === true && $r['other'] === 'copy of other' );
			}

			unset( $use['./vault/example-restore'] );
		}

	}

	// One process holds a database that a feature without a callback of '/nino/admin/restore' keeps: the files of the feature do not change what the kernel does
	@unlink( $useData. '/crm.sqlite' );
}

echo "\nWhat was tried\n\n";

foreach( $tried as $label => $came )
	echo "  ". str_pad( $label, 58 ). $came. "\n";

foreach( array_slice( (array) ( $GLOBALS['hookSandboxes'] ?? [] ), 1 ) as $other )
	\Nino\Filesystem::removeDir( $other );

ninoDone( $appData );
