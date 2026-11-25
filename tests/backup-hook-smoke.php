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

$appData['./nino/backups/disk'] = $need + $copies - 1;
$run			= $attempt( [ 'files' => [ 'vault.db' => 'copy of vault' ] ] );
$short		= $run['result'];
check( 'the room is asked for before a feature is: the files and the copies they will make - a disk with room for the files and none for the copies is refused with the sizes, nothing is asked and nothing is made',
	is_array( $short ) === true && $short[0] === 507 && ( $short[2] ?? '' ) === 'backups_disk' && count( $short[3] ?? [] ) === 2 && $run['asked'] === 0 && $run['new'] === [] && $run['temp'] === [] );
$appData['./nino/backups/disk'] = $need + $copies;
$run			= $attempt( [ 'files' => [ 'vault.db' => 'copy of vault' ] ] );
check( '...and one with room for both is backed up', is_string( $run['result'] ) === true );

// A copy of a size the disk has no room for after it is made: the sizes the run has are the true ones
$appData['./nino/backups/disk'] = $need + $copies;
$run			= $attempt( [ 'files' => [ 'vault.db' => str_repeat( 'x', 3 * 1048576 ) ] ] );
$tried['a copy of 3 MB that the disk has no room for'] = is_array( $run['result'] ) === true ? $run['result'][1] : 'NOT REFUSED';
check( 'what the features wrote is counted again by what it is: a copy that the disk has no room for after it was made ends the run with the sizes, no archive and nothing left',
	is_array( $run['result'] ) === true && $run['result'][0] === 507 && ( $run['result'][2] ?? '' ) === 'backups_disk' && count( $run['result'][3] ?? [] ) === 2 && $run['asked'] === 1 && $run['new'] === [] && $run['temp'] === [] && $run['tmp'] === [] );
unset( $appData['./nino/backups/disk'] );

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

echo "\nWhat was tried\n\n";

foreach( $tried as $label => $came )
	echo "  ". str_pad( $label, 58 ). $came. "\n";

foreach( array_slice( (array) ( $GLOBALS['hookSandboxes'] ?? [] ), 1 ) as $other )
	\Nino\Filesystem::removeDir( $other );

ninoDone( $appData );
