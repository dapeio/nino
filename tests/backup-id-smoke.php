<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	backup-id-smoke.php			Dependency-free smoke test for the names of the archives: an archive made by hand and the safety snapshot of a restore are
 *											named by their second, and two of them in one second are two archives - the run waits for the next second, at most two,
 *											and answers as it does for a run that goes on where the next seconds are taken - and an archive is put in its place by a
 *											step that fails where the name is there, never by one that replaces. Two runs in one process, two processes that start
 *											together, two restores in one second, the retention of names one second apart. Runs against isolated sandbox directories,
 *											never touches the real project data.
 *
 *	Usage: php tests/backup-id-smoke.php
 */

require __DIR__. '/harness.php';

register_shutdown_function( static function(): void {
	foreach( (array) ( $GLOBALS['idSandboxes'] ?? [] ) as $folder )
		\Nino\Filesystem::removeDir( (string) $folder );
} );

/** A project with an account that is signed in and the daily archive of the state 'base' of a text; the text is 'live' when it returns */
function idProject(): array {

	$appData = ninoSandbox( 'backup-id' );
	$sandbox = ninoSandboxDir( $appData );

	$GLOBALS['idSandboxes'][] = $sandbox;

	mkdir( $sandbox. '/private', 0755, true );
	mkdir( $sandbox. '/public', 0755, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [
		'/nino/error/display'			=> true,
		'/nino/locales/native'		=> $appData['/nino/locales/native'],
		'/nino/locales/available'	=> $appData['/nino/locales/available'],
	] );

	ninoUser( $appData, 'dev@example.com' );
	idText( $appData, 'base' );
	\Nino\Modules\Backups::maybeRun( $appData );
	idText( $appData, 'live' );

	return $appData;
}

/** The text of the project is set to a state */
function idText( array &$appData, string $epoch ): void {
	\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[_epoch]]' => $epoch ] );
}

/** The text of the project */
function idEpoch( array &$appData ): string {
	return (string) ( include \Nino\Filesystem::path( $appData, '/text/de_DE.php' ) )['[[_epoch]]'];
}

/** The text an archive of the directory holds */
function idArchiveEpoch( array &$appData, string $id ): string {

	static $count = 0;

	$sandbox = ninoSandboxDir( $appData );
	$plain 	 = ninoUnsealed( (string) file_get_contents( \Nino\Modules\Backups::dir( $appData ). '/'. $id. '.php' ), (string) base64_decode( (string) $appData['/nino/backup/key'], true ) );
	$into 	 = $sandbox. '/read-'. ++$count;

	mkdir( $into, 0755, true );
	file_put_contents( $into. '.tar.gz', (string) $plain );

	if( $plain === false || \Nino\Backup::unpack( $into. '.tar.gz', $into ) !== null )
		return '';

	return (string) ( include $into. '/text/de_DE.php' )['[[_epoch]]'];
}

/** The start of a second: the two calls that follow are in one second unless they take longer than one */
function idFresh(): void {

	$second = time();

	while( time() === $second )
		usleep( 1000 );
}

/** The manual archives of a project, by id */
function idManual( array &$appData ): array {

	return array_map( static fn( string $file ): string => basename( $file, '.php' ), glob( \Nino\Modules\Backups::dir( $appData ). '/*-manual.php' ) ?: [] );
}

/** The snapshots of a project, by id */
function idSnapshots( array &$appData ): array {

	$names = array_map( static fn( string $file ): string => basename( $file, '.php' ), glob( \Nino\Modules\Backups::dir( $appData ). '/pre-restore-*.php' ) ?: [] );
	sort( $names );

	return $names;
}

// --- Two archives made by hand in one second -----------------------------------------------------------------------------------------------------

echo "Two archives made by hand in one second\n";

$appData = idProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );

idText( $appData, 'one' );
idFresh();
$began	= microtime( true );
$first	= \Nino\Modules\Backups::now( $appData );
idText( $appData, 'two' );
$second = \Nino\Modules\Backups::now( $appData );
$took		= microtime( true ) - $began;

check( 'two archives made by hand in one second are two archives with two ids, the second a second after the first', is_string( $first ) === true && is_string( $second ) === true && $first !== $second
	&& strtotime( substr( $second, 0, 10 ). ' '. substr( $second, 11, 2 ). ':'. substr( $second, 13, 2 ). ':'. substr( $second, 15, 2 ) ) - strtotime( substr( $first, 0, 10 ). ' '. substr( $first, 11, 2 ). ':'. substr( $first, 13, 2 ). ':'. substr( $first, 15, 2 ) ) === 1
	&& $took < 2.5 );
check( '...both on disk, each holding the state it was made of, and the list has them both', is_string( $first ) === true && is_string( $second ) === true && idArchiveEpoch( $appData, $first ) === 'one' && idArchiveEpoch( $appData, $second ) === 'two'
	&& count( array_intersect( \Nino\Modules\Backups\Admin::dates( $appData ), [ $first, $second ] ) ) === 2 );

$restored = [];
foreach( [ $first, $second ] as $id ) {
	$restored[] = \Nino\Modules\Backups\Admin::restore( $appData, (string) $id ) === true ? idEpoch( $appData ) : 'not restored';
	\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[_epoch]]' => 'live' ] );
}
check( '...and both are restorable', $restored === [ 'one', 'two' ] );

// The answers of the panel carry their own ids
$appData = idProject();
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
idFresh();
[ $status1, $body1 ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiNow' ], [] );
[ $status2, $body2 ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiNow' ], [] );
check( 'two clicks in one second are answered 200 each with the id of its own archive', $status1 === 200 && $status2 === 200 && ( $body1['id'] ?? '' ) !== ( $body2['id'] ?? '' ) && count( idManual( $appData ) ) === 2
	&& is_file( \Nino\Modules\Backups::dir( $appData ). '/'. ( $body1['id'] ?? '-' ). '.php' ) === true && is_file( \Nino\Modules\Backups::dir( $appData ). '/'. ( $body2['id'] ?? '-' ). '.php' ) === true );

// --- Two processes that start together ----------------------------------------------------------------------------------------------------------

echo "Two processes that make an archive by hand at the same time\n";

$appData = idProject();
$sandbox = ninoSandboxDir( $appData );
$tree		 = dirname( __DIR__ );

// A process of its own: the project of the sandbox, the archive by hand, tried once more where a run that goes on is answered
$code = <<<'CODE'
require $argv[1]. '/tests/harness.php';
$sandbox = $argv[2];
$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path'] = $sandbox;
$appData['./nino/filesystem/configpath'] = $sandbox. '/private';
$appData['./nino/filesystem/contentpath'] = $sandbox. '/private';
$appData['./nino/filesystem/publicpath'] = $sandbox. '/public';
$appData['./nino/locales/current'] = 'de_DE';
$appData['/nino/locales/native'] = 'de_DE';
$appData['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
$appData['/nino/dir'] = '';
$appData['/nino/modules'] = [];
$appData['/nino/install/texts'] = \Nino\VERSION;
$appData['./nino/test/sandbox'] = $sandbox;
echo "ready\n";
fflush( STDOUT );
while( file_exists( $argv[3] ) === false )
	usleep( 500 );
for( $try = 0; $try < 200; $try++ ) {
	$answer = \Nino\Modules\Backups::now( $appData );
	if( is_array( $answer ) === true && ( $answer[2] ?? '' ) === 'backups_busy' ) {
		usleep( 5000 );
		continue;
	}
	break;
}
echo json_encode( $answer ), "\n";
CODE;

$go			= $sandbox. '/go';
$procs	= [];
$pipes	= [];

for( $child = 0; $child < 2; $child++ ) {
	$procs[$child] = proc_open( [ PHP_BINARY, '-d', 'display_errors=0', '-r', $code, $tree, $sandbox, $go ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'file', '/dev/null', 'a' ] ], $pipes[$child] );
	fgets( $pipes[$child][1] );
}

idFresh();
touch( $go );

$answers = [];
foreach( $procs as $child => $proc ) {
	$answers[] = json_decode( trim( (string) stream_get_contents( $pipes[$child][1] ) ), true );
	proc_close( $proc );
}

// What the two said: the ids of their archives
$ids = array_map( static fn( mixed $answer ): string => is_string( $answer ) === true ? $answer : '', $answers );
clearstatcache();

check( 'two processes that start together make two archives, each answered with the id of its own', count( array_filter( $ids ) ) === 2 && $ids[0] !== $ids[1] && count( idManual( $appData ) ) === 2
	&& is_file( \Nino\Modules\Backups::dir( $appData ). '/'. $ids[0]. '.php' ) === true && is_file( \Nino\Modules\Backups::dir( $appData ). '/'. $ids[1]. '.php' ) === true );
check( '...both can be restored', array_filter( $ids ) !== [] && array_reduce( $ids, static fn( bool $all, string $id ): bool => $all && \Nino\Modules\Backups\Admin::restore( $appData, $id ) === true, true ) );

// --- Two restores in one second ----------------------------------------------------------------------------------------------------------------

echo "Two restores in one second\n";

$appData = idProject();
$base		 = date( 'Y-m-d' );

idText( $appData, 'before-the-first-restore' );
idFresh();
$one = \Nino\Modules\Backups\Admin::restore( $appData, $base );
idText( $appData, 'before-the-second-restore' );
$two = \Nino\Modules\Backups\Admin::restore( $appData, $base );
$snapshots = idSnapshots( $appData );

check( 'two restores in one second make two safety snapshots', $one === true && $two === true && count( $snapshots ) === 2 && $snapshots[0] !== $snapshots[1] );
check( '...the first holds the state before the first restore and the second the state before the second', count( $snapshots ) === 2 && idArchiveEpoch( $appData, $snapshots[0] ) === 'before-the-first-restore' && idArchiveEpoch( $appData, $snapshots[1] ) === 'before-the-second-restore' );

// A restore of the snapshot that was made in the second the restore began: the snapshot is not replaced
$appData = idProject();
idText( $appData, 'live-state' );
idFresh();
\Nino\Modules\Backups\Admin::restore( $appData, date( 'Y-m-d' ) );
$snapshot = idSnapshots( $appData )[0] ?? '';
$hash			= hash_file( 'sha256', \Nino\Modules\Backups::dir( $appData ). '/'. $snapshot. '.php' );
$back			= \Nino\Modules\Backups\Admin::restore( $appData, $snapshot );
check( 'the snapshot that a restore made is restored in the second it was made, and stays the archive it was: the restore of it has a snapshot of its own',
	$back === true && idEpoch( $appData ) === 'live-state' && hash_file( 'sha256', \Nino\Modules\Backups::dir( $appData ). '/'. $snapshot. '.php' ) === $hash && count( idSnapshots( $appData ) ) === 2 );

// --- Names one second apart ----------------------------------------------------------------------------------------------------------------------

echo "Retention and pruning of names one second apart\n";

$appData = idProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );
$daily	 = date( 'Y-m-d' );
$stamp	 = strtotime( 'today 12:00:00' );
$ids		 = [];

foreach( [ 0, 1, 2 ] as $offset ) {
	$ids[$offset] = date( 'Y-m-d-His', $stamp + $offset ). '-manual';
	copy( $dir. '/'. $daily. '.php', $dir. '/'. $ids[$offset]. '.php' );
}

$appData['/nino/admin/backups-manual'] = 2;
check( 'of three archives one second apart the newest two stay and the oldest is the one that is dropped', \Nino\Modules\Backups::retention( $appData, $ids[0] )['drops'] === true
	&& \Nino\Modules\Backups::retention( $appData, $ids[1] )['drops'] === false && \Nino\Modules\Backups::retention( $appData, $ids[2] )['drops'] === false );
check( '...and the dates list them newest first', array_slice( \Nino\Modules\Backups\Admin::dates( $appData ), 0, 3 ) === [ $ids[2], $ids[1], $ids[0] ] );

( new ReflectionMethod( '\Nino\Modules\Backups', '_prune' ) )->invoke( null, $dir, 3, 2, $ids[2]. '.php' );
check( '...a run that writes the newest deletes the oldest and nothing else', is_file( $dir. '/'. $ids[0]. '.php' ) === false && is_file( $dir. '/'. $ids[1]. '.php' ) === true && is_file( $dir. '/'. $ids[2]. '.php' ) === true && is_file( $dir. '/'. $daily. '.php' ) === true );

// --- The seconds ahead are taken -----------------------------------------------------------------------------------------------------------------

echo "An archive of every second of the next seconds is there\n";

$appData = idProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );
$plant	 = [];

// This second and the four after it: the run waits for what it may, and says so
for( $offset = -1; $offset <= 4; $offset++ ) {
	$file = $dir. '/'. date( 'Y-m-d-His', time() + $offset ). '-manual.php';
	file_put_contents( $file, 'an archive of another second '. $offset );
	$plant[$file] = 'an archive of another second '. $offset;
}

$began	= microtime( true );
$answer = \Nino\Modules\Backups::now( $appData );
$took		= microtime( true ) - $began;
$same		= true;

foreach( $plant as $file => $bytes )
	$same = $same && is_file( $file ) === true && file_get_contents( $file ) === $bytes;

check( 'where the seconds ahead are taken the archive by hand is refused with words of its own - the archives carry a later time than the clock - after the wait', is_array( $answer ) === true && $answer[0] === 409 && ( $answer[2] ?? '' ) === 'backups_clock' && $took > 1.0 && $took < 3.5 );
check( '...no archive of those that are there is replaced, none is made, and the lock is let go', $same === true && count( idManual( $appData ) ) === count( $plant ) && \Nino\Filesystem::lockFile( $appData, \Nino\Modules\Backups::LOCK_PATH, false ) === true );
\Nino\Filesystem::unlockFile( $appData, \Nino\Modules\Backups::LOCK_PATH );

// The same for the snapshot of a restore: it does not begin
$appData = idProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );
$plant	 = [];

for( $offset = -1; $offset <= 4; $offset++ ) {
	$file = $dir. '/pre-restore-'. date( 'Y-m-d-His', time() + $offset ). '.php';
	file_put_contents( $file, 'a snapshot of another second '. $offset );
	$plant[$file] = 'a snapshot of another second '. $offset;
}

$began	= microtime( true );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => date( 'Y-m-d' ) ] );
$took		= microtime( true ) - $began;
$same		= true;

foreach( $plant as $file => $bytes )
	$same = $same && is_file( $file ) === true && file_get_contents( $file ) === $bytes;

check( 'a restore that finds the snapshots of the seconds ahead there is answered with the words of the clock, and has not begun: the project is as it was and no snapshot is replaced',
	$status === 409 && ( $body['code'] ?? '' ) === 'backups_clock' && idEpoch( $appData ) === 'live' && $same === true && count( idSnapshots( $appData ) ) === count( $plant ) && $took < 4.5 );

// --- The step that puts an archive in its place -------------------------------------------------------------------------------------------------

echo "An archive is put in its place and never over another\n";

$appData = idProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );
$target	 = $dir. '/2026-01-02-030405-manual.php';

\Nino\Modules\Backups::publish( $target, [ 'first ', 'piece' ] );
$failure = null;

try {
	\Nino\Modules\Backups::publish( $target, 'another archive' );
}
catch( \RuntimeException $e ) {
	$failure = $e;
}

check( 'an archive is written by pieces and is the file it was written as, behind no other name', file_get_contents( $target ) === 'first piece' && ( stat( $target )['nlink'] ?? 0 ) === 1 && glob( $dir. '/*.tmp' ) === [] );
check( '...and a second one of the name is refused with a code of its own, and the first is as it was', $failure !== null && $failure->getCode() === \Nino\Backup::NAME_TAKEN && file_get_contents( $target ) === 'first piece' && glob( $dir. '/*.tmp' ) === [] );

// A folder or a link to nothing in the place of the name is no archive to wait for: the archive cannot be put there, which is a write that failed
symlink( '/nonexistent/target', $dir. '/2026-01-02-030406-manual.php' );
mkdir( $dir. '/2026-01-02-030407-manual.php' );
$codes = [];

foreach( [ '2026-01-02-030406-manual', '2026-01-02-030407-manual' ] as $name ) {
	try {
		\Nino\Modules\Backups::publish( $dir. '/'. $name. '.php', 'another archive' );
		$codes[] = 0;
	}
	catch( \RuntimeException $e ) {
		$codes[] = $e->getCode();
	}
}
check( '...a link to nothing or a folder in the place of the name is a write that failed, and stays what it is', $codes === [ \Nino\Backup::WRITE_FAILED, \Nino\Backup::WRITE_FAILED ] && is_link( $dir. '/2026-01-02-030406-manual.php' ) === true && is_dir( $dir. '/2026-01-02-030407-manual.php' ) === true && glob( $dir. '/*.tmp' ) === [] );

// --- The id of a snapshot is taken before the writers are locked out -------------------------------------------------------------------

echo "A restore takes the id of its snapshot before it locks a writer\n";

$appData = idProject();
$sandbox = ninoSandboxDir( $appData );
$dir		 = \Nino\Modules\Backups::dir( $appData );
$tree		 = dirname( __DIR__ );
$lock		 = \Nino\Backup::lockPath( 'text/de_DE.php' );

// A process of a writer: it asks for the lock of the text file the restore puts back, every millisecond, and says when it is first refused
$watch = <<<'CODE'
require $argv[1]. '/tests/harness.php';
$sandbox = $argv[2];
$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path'] = $sandbox;
$appData['./nino/filesystem/configpath'] = $sandbox. '/private';
$appData['./nino/filesystem/contentpath'] = $sandbox. '/private';
$appData['./nino/filesystem/publicpath'] = $sandbox. '/public';
$appData['/nino/dir'] = '';
$appData['/nino/modules'] = [];
echo "ready\n";
fflush( STDOUT );
$until = microtime( true ) + 20;
while( microtime( true ) < $until ) {
	if( \Nino\Filesystem::lockFile( $appData, $argv[3], false ) === false ) {
		echo sprintf( '%.3f', microtime( true ) ), "\n";
		exit;
	}
	\Nino\Filesystem::unlockFile( $appData, $argv[3] );
	usleep( 1000 );
}
echo "never\n";
CODE;

// A snapshot of this second is there, so the id has to wait for the next one
idFresh();
$second = time();
file_put_contents( $dir. '/pre-restore-'. date( 'Y-m-d-His', $second ). '.php', 'a snapshot of this second' );
$proc = proc_open( [ PHP_BINARY, '-d', 'display_errors=0', '-r', $watch, $tree, $sandbox, $lock ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'file', '/dev/null', 'a' ] ], $pipes );
fgets( $pipes[1] );
$result	= \Nino\Modules\Backups\Admin::restore( $appData, date( 'Y-m-d' ) );
$locked	= (float) trim( (string) stream_get_contents( $pipes[1] ) );
proc_close( $proc );
$made		= array_values( array_diff( idSnapshots( $appData ), [ 'pre-restore-'. date( 'Y-m-d-His', $second ) ] ) );

check( 'a restore whose snapshot has to wait for the next second locks no writer out before that second has begun', $result === true && count( $made ) === 1 && $made[0] === 'pre-restore-'. date( 'Y-m-d-His', $second + 1 ) && $locked >= $second + 1 - 0.01 );

// --- A name that is a folder, a link, or an archive that appears meanwhile --------------------------------------------------------------------------

echo "Names that are taken in other ways\n";

$appData = idProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );
$target	 = $dir. '/target.bin';
file_put_contents( $target, 'what a link points at' );
$second	 = time();
$link		 = $dir. '/'. date( 'Y-m-d-His', $second ). '-manual.php';
symlink( $target, $link );
$answer	 = \Nino\Modules\Backups::now( $appData );

check( 'a link to a file in the place of the name is taken: nothing is written through it or over it, and the archive is named by the next second', is_string( $answer ) === true && $answer !== substr( $link, strlen( $dir ) + 1, -4 )
	&& is_link( $link ) === true && file_get_contents( $target ) === 'what a link points at' );

$appData = idProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );
idFresh();
$second	 = time();
mkdir( $dir. '/'. date( 'Y-m-d-His', $second ). '-manual.php' );
file_put_contents( $dir. '/'. date( 'Y-m-d-His', $second ). '-manual.php/in-the-way', 'x' );
$began	 = microtime( true );
$answer	 = \Nino\Modules\Backups::now( $appData );
check( 'a folder in the place of the name is no archive to wait for: the archive cannot be put there, which is a write that failed, at once and with the folder as it was',
	is_array( $answer ) === true && $answer[0] === 500 && ( $answer[2] ?? '' ) === 'backups_write' && microtime( true ) - $began < 0.9 && is_file( $dir. '/'. date( 'Y-m-d-His', $second ). '-manual.php/in-the-way' ) === true );

$appData = idProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );
idFresh();
$second	 = time();
mkdir( $dir. '/pre-restore-'. date( 'Y-m-d-His', $second ) . '.php' );
mkdir( $dir. '/pre-restore-'. date( 'Y-m-d-His', $second + 1 ) . '.php' );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => date( 'Y-m-d' ) ] );
check( 'a folder in the place of the name of a snapshot is a write that failed, and the restore has not begun', $status === 500 && ( $body['code'] ?? '' ) === 'backups_write' && idEpoch( $appData ) === 'live' && is_dir( $dir. '/pre-restore-'. date( 'Y-m-d-His', $second ) . '.php' ) === true );

// A snapshot of the name that is made while the restore packs: the one that puts the snapshot in its place refuses the name, and the other is as it was
$appData = idProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );
$plant	 = [];

\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $dir, &$plant ): void {

	if( $phase === 'read' && $try === 1 )
		for( $offset = -3; $offset <= 1; $offset++ ) {
			$file = $dir. '/pre-restore-'. date( 'Y-m-d-His', time() + $offset ). '.php';
			file_put_contents( $file, 'a snapshot that was made meanwhile '. $offset );
			$plant[$file] = 'a snapshot that was made meanwhile '. $offset;
		}
};

[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => date( 'Y-m-d' ) ] );
\Nino\Backup::$packing = null;
$same = true;

foreach( $plant as $file => $bytes )
	$same = $same && file_get_contents( $file ) === $bytes;

check( 'a snapshot whose name was taken while the restore packed is not replaced: the restore has not begun and answers that a run goes on', $status === 409 && ( $body['code'] ?? '' ) === 'backups_busy' && $same === true && $plant !== [] && idEpoch( $appData ) === 'live' );

ninoDone( $appData );
