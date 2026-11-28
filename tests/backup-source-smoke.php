<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	backup-source-smoke.php	Dependency-free smoke test for the files a backup packs and that go while it does: what an archive leaves out and says so
 *											(the companion of a database, the temporary file of a writer of the kernel) and what ends the run with the code
 *											backups_source and the name of the file (a real file of the project that is gone or changed), at each door a backup
 *											has - an archive made by hand, the daily run, the safety snapshot of the panel's restore and the one of the recovery page -;
 *											the order a database and its companions are read in; the one more attempt the daily backup makes after such a
 *											failure and no other; and the codes of the snapshot's own failures. Runs against isolated sandbox directories,
 *											never touches the real project data.
 *
 *	Usage: php tests/backup-source-smoke.php
 */

// Before the kernel loads: the autoloader and Features::dir() read the same constant
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/backup-source' );

require __DIR__. '/harness.php';

// Whatever ends the run - a fatal error as well - the folders of the projects it made are taken away
register_shutdown_function( static function(): void {
	\Nino\Backup::$packing = null;
	foreach( (array) ( $GLOBALS['srcSandboxes'] ?? [] ) as $folder )
		\Nino\Filesystem::removeDir( (string) $folder );
} );

/** A project with the feature of the fixture, its files, an account that is signed in and the daily archive of the state 'A' of a text; the text is 'B' when it returns. With $hook 'throw' the callback of the feature fails from then on */
function srcProject( string $hook = '' ): array {

	$appData = ninoSandbox( 'backup-source' );
	$sandbox = ninoSandboxDir( $appData );

	$GLOBALS['srcSandboxes'][] = $sandbox;

	mkdir( $sandbox. '/private', 0755, true );
	mkdir( $sandbox. '/public', 0755, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [
		'/nino/error/display'			=> true,
		'/nino/locales/native'		=> $appData['/nino/locales/native'],
		'/nino/locales/available'	=> $appData['/nino/locales/available'],
	] );

	$appData['/nino/modules'] = [ '\\Nino\\Modules\\Ledger' ];

	foreach( [
		'data/ledger.sqlite' 				=> 'live ledger',
		'data/plain.sqlite' 				=> 'live plain',
		'data/ledger/db.sqlite' 		=> 'live db of the folder',
		'data/ledger/notes.php' 		=> "<?php return [ 'live' => 1 ];",
		'data/forms.contact.php' 		=> '<?php return [];',
		'assets/style.css' 					=> 'body { color: black }',
		'text/en_US.php'						=> "<?php return [ '[[_epoch]]' => 'A' ];",
	] as $file => $bytes ) {
		@mkdir( dirname( \Nino\Filesystem::path( $appData, '/'. $file ) ), 0755, true );
		file_put_contents( \Nino\Filesystem::path( $appData, '/'. $file ), $bytes );
	}

	\Nino\Modules::callModules( $appData, 'init' );
	ninoUser( $appData, 'dev@example.com' );

	\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[_epoch]]' => 'A' ] );
	\Nino\Modules\Backups::maybeRun( $appData );
	\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[_epoch]]' => 'B' ] );

	if( $hook === 'throw' || $hook === 'copy' )
		\Nino\Callbacks::registerCallback( $appData, '/nino/admin/backup', [ \Nino\Modules\Ledger::class, $hook === 'throw' ? 'callbackBackup' : 'callbackCopy' ] );

	return $appData;
}

/** The text of the project, 'A' or 'B' */
function srcEpoch( array &$appData ): string {
	return (string) ( include \Nino\Filesystem::path( $appData, '/text/de_DE.php' ) )['[[_epoch]]'];
}

/** What an archive holds: name => bytes. The id is one of the list or a snapshot */
function srcFiles( array &$appData, string $id ): array {

	static $count = 0;

	$sandbox = ninoSandboxDir( $appData );
	$plain 	 = ninoUnsealed( (string) file_get_contents( \Nino\Modules\Backups::dir( $appData ). '/'. $id. '.php' ), (string) base64_decode( (string) $appData['/nino/backup/key'], true ) );
	$into 	 = $sandbox. '/read-'. ++$count;
	$files 	 = [];

	mkdir( $into, 0755, true );
	file_put_contents( $into. '.tar.gz', (string) $plain );

	if( \Nino\Backup::unpack( $into. '.tar.gz', $into ) !== null )
		return [];

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $into, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY ) as $file )
		$files[str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $into ) + 1 ) )] = (string) file_get_contents( $file->getPathname() );

	ksort( $files );

	return $files;
}

/** The stamp of an archive, as the array it is */
function srcStamp( array $files ): array {

	$file = sys_get_temp_dir(). '/nino-src-stamp-'. bin2hex( random_bytes( 4 ) ). '.php';
	file_put_contents( $file, (string) ( $files['nino.php'] ?? '<?php return [];' ) );
	$stamp = (array) include $file;
	unlink( $file );

	return $stamp;
}

/** What the runs of this project leave in the temporary directory of the system: the files and folders of the runs, by the mark of the project */
function srcTemp( array &$appData ): array {

	$mark = substr( sha1( (string) ( realpath( \Nino\Filesystem::getContentPath( $appData ) ) ?: \Nino\Filesystem::getContentPath( $appData ) ) ), 0, 8 );

	return array_map( 'basename', glob( sys_get_temp_dir(). '/nino*-'. $mark. '-*' ) ?: [] );
}

/** The archives of the directory of a project that are no daily ones: the snapshots and the ones made by hand */
function srcOthers( array &$appData ): array {

	return array_map( static fn( string $file ): string => basename( $file, '.php' ), array_filter( glob( \Nino\Modules\Backups::dir( $appData ). '/*.php' ) ?: [], static fn( string $file ): bool => preg_match( '/^\d{4}-\d{2}-\d{2}\.php$/', basename( $file ) ) !== 1 && basename( $file ) !== 'attempt.php' && basename( $file ) !== 'run.php' ) );
}

/** One door of a backup asked, with the file going while it packs: [ status, body ] - for the daily run 200 where today's archive exists, else 500, and what the panel is told of it */
function srcDoor( array &$appData, string $door, string $dailyId ): array {

	if( $door === 'panel' )
		return ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $dailyId ] );

	if( $door === 'recovery' ) {
		\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
		return ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $dailyId ], 'recovery/restore' );
	}

	if( $door === 'now' )
		return ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiNow' ], [] );

	@unlink( \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php' );
	@unlink( \Nino\Modules\Backups::dir( $appData ). '/attempt.php' );
	\Nino\Modules\Backups::maybeRun( $appData );

	return [ is_file( \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php' ) === true ? 200 : 500, \Nino\Modules\Backups::missed( $appData ) ];
}

/**
 *	One file that is there when the run lists it and gone when it packs, at one door: what the answer, the archive, the stamp, the notices and the project say.
 *
 *	@param		string		$what					What the file is, for the label
 *	@param		string		$door					'panel', 'recovery', 'now' or 'daily'
 *	@param		string		$name					The file's name in the archive, eg. 'data/ledger.sqlite-journal'
 *	@param		string		$expect				'left': the run goes through and the archive says what it left out; 'source': the run ends with backups_source and the name
 *	@param		string		$hook					'throw' for a feature whose callback fails, so that the snapshot holds its files as they lie
 *	@param		bool			$noMain				Whether the database the companion belongs to is not there: the snapshot lists the files beside it all the same
 *
 *	@return 	void
 */
function srcVanish( string $what, string $door, string $name, string $expect, string $hook = '', bool $noMain = false ): void {

	$appData = srcProject( $hook );
	$file 	 = \Nino\Filesystem::path( $appData, '/'. $name );
	$id 		 = date( 'Y-m-d' );
	$label	 = $door. ', '. $what;

	@mkdir( dirname( $file ), 0755, true );
	file_put_contents( $file, 'bytes that go' );

	if( $noMain === true )
		unlink( \Nino\Filesystem::path( $appData, '/data/ledger.sqlite' ) );

	$others	= srcOthers( $appData );
	$temp 	= srcTemp( $appData );

	ninoWarnings();

	\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $file ): void {
		if( $phase === 'read' && $try === 1 )
			@unlink( $file );
	};

	try {
		[ $status, $body ] = srcDoor( $appData, $door, $id );
	}
	finally {
		\Nino\Backup::$packing = null;
	}

	$warnings = ninoWarnings();
	$new 			= array_values( array_diff( srcOthers( $appData ), $others ) );
	$restore	= $door === 'panel' || $door === 'recovery';
	$archive	= $door === 'daily' ? $id : ( $door === 'now' ? (string) ( $body['id'] ?? '' ) : (string) ( $new[0] ?? '' ) );

	if( $expect === 'left' ) {

		$files = $archive === '' ? [] : srcFiles( $appData, $archive );
		$stamp = srcStamp( $files );
		$told	 = array_values( array_filter( $warnings, static fn( string $message ): bool => str_contains( $message, 'left out of the archive' ) ) );

		check( $label. ': the run goes through', $status === 200 && ( $restore === false || isset( $body['restoredDate'] ) === true ) );
		check( '...the archive is there and holds the project without the file', $archive !== '' && isset( $files['text/en_US.php'] ) === true && isset( $files[$name] ) === false );
		check( '...its stamp names the file as left out, and no other', ( $stamp['left'] ?? null ) === [ $name ] );
		check( '...and one notice names it', count( $told ) === 1 && str_contains( $told[0], $name ) === true );
		check( '...a restore that was made put the state of the archive back, and the run left nothing in the temporary directory', ( $restore === false || srcEpoch( $appData ) === 'A' ) && srcTemp( $appData ) === $temp );

		return;
	}

	$answer = $door === 'daily' ? ( is_array( $body ) === true ? $body : [] ) : (array) $body;

	if( $door === 'daily' ) {
		check( $label. ': no archive of the day is made', $status === 500 );
		check( '...the panel is told that a file could not be read, by its name, and that the next request tries once more', ( $answer['code'] ?? '' ) === 'source' && ( $answer['file'] ?? '' ) === $name && ( $answer['retry'] ?? null ) === true );
	}
	else {
		check( $label. ': the run ends with a 500 and the code of a file that could not be read', $status === 500 && ( $answer['code'] ?? '' ) === 'backups_source' );
		check( '...and the name of the file, which is no path', ( $answer['params'] ?? null ) === [ $name ] && str_contains( (string) ( $answer['error'] ?? '' ), \Nino\Filesystem::getContentPath( $appData ) ) === false );
	}

	check( '...no archive is made, the project is as it was and nothing is left in the temporary directory or the archive directory',
		srcOthers( $appData ) === $others && ( $restore === false || srcEpoch( $appData ) === 'B' ) && srcTemp( $appData ) === $temp && ( glob( \Nino\Modules\Backups::dir( $appData ). '/*.tmp' ) ?: [] ) === [] );
}

// --- The files that go while a backup packs, at every door ------------------------------------------------------------------------------------------

echo "A file that goes while a backup packs it, at each door\n";

$classes = [
	// what it is => [ name in the archive, expectation, whether it is a file of a database packed as it lies (the feature's callback fails, only a safety snapshot) ]
	'the -journal of a database that is packed as it lies'												=> [ 'data/ledger.sqlite-journal', 'left', true ],
	'the -wal of a database that is packed as it lies'														=> [ 'data/ledger.sqlite-wal', 'left', true ],
	'the -shm of a database that is packed as it lies'														=> [ 'data/ledger.sqlite-shm', 'left', true ],
	'the temporary file of a writer of the kernel in assets/'											=> [ 'assets/theme.css.0123456789ab.tmp', 'left', false ],
	'the temporary file of a writer in a folder a feature declares'								=> [ 'data/ledger/notes.php.0123456789ab.tmp', 'left', false ],
	'a temporary file whose file is not there at all'															=> [ 'data/ledger/ghost.php.0123456789ab.tmp', 'left', false ],
	'the -wal of a database in a folder a feature declares'												=> [ 'data/ledger/db.sqlite-wal', 'left', false ],
	'the -journal of a database in a folder a feature declares'										=> [ 'data/ledger/db.sqlite-journal', 'left', false ],
	'the -shm of a database in a folder a feature declares'												=> [ 'data/ledger/db.sqlite-shm', 'left', false ],
	'a real file in a folder a feature declares'																	=> [ 'data/ledger/notes.php', 'source', false ],
	'the submissions of a form'																										=> [ 'data/forms.contact.php', 'source', false ],
	'a file of the texts'																													=> [ 'text/en_US.php', 'source', false ],
	'a stylesheet'																																=> [ 'assets/style.css', 'source', false ],
	'a -wal that a feature declares as its own file, beside a database that is listed'			=> [ 'data/plain.sqlite-wal', 'source', false ],
	'a -wal that a feature declares, no database beside it'												=> [ 'data/foo-wal', 'source', false ],
	'a name of the shape of a temporary file that a feature declares as its own'				=> [ 'data/looks.0123456789ab.tmp', 'source', false ],
	'a file named x.tmp in a folder a feature declares'														=> [ 'data/ledger/x.tmp', 'source', false ],
	'a -wal in a folder whose database is not listed'															=> [ 'data/ledger/orphan.sqlite-wal', 'source', false ],
];

foreach( $classes as $what => [ $name, $expect, $raw ] )
	foreach( $raw === true ? [ 'panel', 'recovery' ] : [ 'panel', 'recovery', 'now', 'daily' ] as $door )
		srcVanish( $what, $door, $name, $expect, $raw === true ? 'throw' : '' );

// The companions that raw() lists are the safety snapshot's own proof, whether or not the database beside them is there to be listed
foreach( [ 'journal', 'wal', 'shm' ] as $suffix )
	foreach( [ 'panel', 'recovery' ] as $door )
		srcVanish( 'the -'. $suffix. ' of a database that is not there', $door, 'data/ledger.sqlite-'. $suffix, 'left', 'throw', true );

// Nothing goes: the control, which every door answers as it always did
foreach( [ 'panel', 'recovery', 'now', 'daily' ] as $door ) {

	$appData = srcProject();
	$others	 = srcOthers( $appData );
	ninoWarnings();
	[ $status, $body ] = srcDoor( $appData, $door, date( 'Y-m-d' ) );
	$new 		 = array_values( array_diff( srcOthers( $appData ), $others ) );
	$archive = $door === 'daily' ? date( 'Y-m-d' ) : ( $door === 'now' ? (string) ( $body['id'] ?? '' ) : (string) ( $new[0] ?? '' ) );
	$files	 = $archive === '' ? [] : srcFiles( $appData, $archive );

	check( $door. ', nothing goes: the run goes through, the archive holds the files and its stamp leaves nothing out', $status === 200 && isset( $files['data/ledger/db.sqlite'] ) === true && isset( srcStamp( $files )['left'] ) === false
		&& array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'left out of the archive' ) ) === [] );
}

// --- Files that are gone before the packing begins -----------------------------------------------------------------------------------------------

echo "A file that is gone, or another request's temporary file, before the files are read\n";

$appData	= srcProject();
$sandbox	= ninoSandboxDir( $appData );
$base 		= \Nino\Backup::manifest( $appData );
$priv			= \Nino\Filesystem::path( $appData, '/assets' );

/** The names of an unpacked tar, in the order they stand in it */
$tarNames = static function( string $tarGz ): array {
	$names = [];
	$handle = gzopen( $tarGz, 'rb' );
	while( ( $block = gzread( $handle, 512 ) ) !== false && strlen( $block ) === 512 && trim( $block, "\0" ) !== '' ) {
		$size = (int) octdec( trim( substr( $block, 124, 12 ), "\0 " ) );
		$names[] = rtrim( substr( $block, 0, 100 ), "\0" );
		if( $size > 0 )
			gzread( $handle, (int) ceil( $size / 512 ) * 512 );
	}
	gzclose( $handle );
	return $names;
};

// The manifest as a run lists it, with names that are not there any more
$gone = $base + [
	$priv. '/gone.css.0123456789ab.tmp'							=> 'assets/gone.css.0123456789ab.tmp',
	\Nino\Filesystem::path( $appData, '/data' ). '/ledger/db.sqlite-wal'	=> 'data/ledger/db.sqlite-wal',
];

ninoWarnings();
\Nino\Backup::pack( $appData, $gone, $sandbox. '/gone.tar' );
$told	 = array_values( array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'left out of the archive' ) ) );
$files = [];
$plain = $sandbox. '/gone-read';
mkdir( $plain );
copy( $sandbox. '/gone.tar.gz', $plain. '.tar.gz' );
\Nino\Backup::unpack( $plain. '.tar.gz', $plain );
$stamp = srcStamp( [ 'nino.php' => (string) file_get_contents( $plain. '/nino.php' ) ] );
check( 'files that are gone at the start of the packing and may be - a temporary file of a writer, the -wal of a database that is listed - are left out, and the stamp and one notice say so',
	is_file( $sandbox. '/gone.tar.gz' ) === true && is_file( $sandbox. '/gone.tar' ) === false && ( $stamp['left'] ?? null ) === [ 'data/ledger/db.sqlite-wal', 'assets/gone.css.0123456789ab.tmp' ] && count( $told ) === 1 && is_file( $plain. '/data/ledger/db.sqlite' ) === true && is_file( $plain. '/data/ledger/db.sqlite-wal' ) === false );

foreach( [ 'a real file of the project' => [ 'data/ledger/gone.php', 'data/ledger/gone.php' ], 'a file of a declared name' => [ 'data/plain.sqlite-wal', 'data/plain.sqlite-wal' ], 'a file that is a folder' => [ 'assets', 'assets/style.css' ] ] as $what => [ $path, $name ] ) {

	$failure = null;
	$target	 = \Nino\Filesystem::path( $appData, '/'. $path );
	$listed	 = $base + [ $target => $name ];

	if( $what === 'a file that is a folder' ) {
		unset( $listed[$priv. '/style.css'] );
		$listed[$priv] = 'assets/style.css';
	}

	try {
		\Nino\Backup::pack( $appData, $listed, $sandbox. '/real.tar' );
	}
	catch( \RuntimeException $e ) {
		$failure = $e;
	}

	check( $what. ' that is gone ends the packing with the code of a file that could not be read, and its name', $failure !== null && $failure->getCode() === \Nino\Backup::SOURCE_FAILED && \Nino\Backup::sourceName( $failure ) === $name && str_contains( $failure->getMessage(), $sandbox ) === false );
	check( '...and leaves no tar behind', is_file( $sandbox. '/real.tar' ) === false && is_file( $sandbox. '/real.tar.gz' ) === false );
}

// A companion that is found there and is gone at the next look: each file is looked at once, and what the look found decides
$appData = srcProject();
$sandbox = ninoSandboxDir( $appData );
$journal = \Nino\Filesystem::path( $appData, '/data/ledger/db.sqlite-journal' );
file_put_contents( $journal, 'a journal' );
\Nino\Backup::$packing = static function( string $phase, int $try, string $name = '' ) use ( $journal ): void {
	if( $phase === 'looked' && $name === 'data/ledger/db.sqlite-journal' )
		unlink( $journal );
};
$failure = null;
try {
	\Nino\Backup::pack( $appData, \Nino\Backup::manifest( $appData ), $sandbox. '/looked.tar' );
}
catch( \Throwable $e ) {
	$failure = $e;
}
\Nino\Backup::$packing = null;
$plain = $sandbox. '/looked-read';
mkdir( $plain );
@copy( $sandbox. '/looked.tar.gz', $plain. '.tar.gz' );
\Nino\Backup::unpack( $plain. '.tar.gz', $plain );
check( 'a -journal that was found there and is gone before the next thing is done with it is left out, and the packing goes through', $failure === null && is_file( $plain. '/data/ledger/db.sqlite' ) === true && is_file( $plain. '/data/ledger/db.sqlite-journal' ) === false
	&& ( srcStamp( [ 'nino.php' => (string) @file_get_contents( $plain. '/nino.php' ) ] )['left'] ?? null ) === [ 'data/ledger/db.sqlite-journal' ] );

// The read of the sizes that comes first: the same classes
$plan = new ReflectionMethod( '\Nino\Modules\Backups', '_plan' );
$full = $plan->invoke( null, $appData, $base );
$some = $plan->invoke( null, $appData, $gone );
check( 'the count of the files and of the bytes does not carry a file that is gone and may be', $some['files'] === $full['files'] && $some['bytes'] === $full['bytes'] );

$failure = null;
try {
	$plan->invoke( null, $appData, $base + [ \Nino\Filesystem::path( $appData, '/data' ). '/ledger/gone.php' => 'data/ledger/gone.php' ] );
}
catch( \RuntimeException $e ) {
	$failure = $e;
}
check( '...and one that is gone and is a real file ends it with the code and the name', $failure !== null && $failure->getCode() === \Nino\Backup::SOURCE_FAILED && \Nino\Backup::sourceName( $failure ) === 'data/ledger/gone.php' );

// A file that is there and cannot be read: not where the process may read everything
if( function_exists( 'posix_geteuid' ) === true && posix_geteuid() !== 0 ) {

	chmod( $priv. '/style.css', 0000 );
	$failure = null;

	try {
		\Nino\Backup::pack( $appData, $base, $sandbox. '/unreadable.tar' );
	}
	catch( \RuntimeException $e ) {
		$failure = $e;
	}

	chmod( $priv. '/style.css', 0644 );
	check( 'a real file that is there and cannot be read ends the packing with the code and its name', $failure !== null && \Nino\Backup::sourceName( $failure ) === 'assets/style.css' );
}
else
	echo "  not run  - a real file that is there and cannot be read: this process is root and reads every file\n";

// --- A file that looks like a temporary file and is there ----------------------------------------------------------------------------------------

echo "A file named like a temporary file that is there is packed like any file\n";

$appData = srcProject();
$sandbox = ninoSandboxDir( $appData );

$named = [
	'images/photo'													=> 'a picture',
	'images/photo.0123456789ab.tmp'					=> 'a picture of the user named like a temporary file',
	'assets/style.css.0123456789ab.tmp'			=> 'an asset named like a temporary file of a file that is listed',
	'data/ledger/notes.php.0123456789ab.tmp'	=> 'a file of a folder a feature declares, named like one',
	'assets/orphan.0123456789ab.tmp'				=> 'the shape of a temporary file, no file of that name listed',
	'assets/x.tmp'													=> 'a file that is named .tmp',
	'data/looks.0123456789ab.tmp'						=> 'a file a feature declares by that name',
	'data/ledger/db.sqlite-wal'							=> 'the -wal of a database that is listed, there',
];

foreach( $named as $name => $bytes ) {
	@mkdir( dirname( \Nino\Filesystem::path( $appData, '/'. $name ) ), 0755, true );
	file_put_contents( \Nino\Filesystem::path( $appData, '/'. $name ), $bytes );
}

ninoWarnings();
$id	 = \Nino\Modules\Backups::now( $appData );
$told = array_values( array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'left out of the archive' ) ) );
$files = is_string( $id ) === true ? srcFiles( $appData, $id ) : [];

check( 'a backup carries every one of them, whatever it is named, and leaves nothing out: no entry in the stamp, no notice', is_string( $id ) === true && array_diff_key( $named, $files ) === [] && isset( srcStamp( $files )['left'] ) === false && $told === [] );

foreach( $named as $name => $bytes )
	@unlink( \Nino\Filesystem::path( $appData, '/'. $name ) );

[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => (string) $id ] );
$back = true;
foreach( $named as $name => $bytes )
	$back = $back && is_file( \Nino\Filesystem::path( $appData, '/'. $name ) ) === true && file_get_contents( \Nino\Filesystem::path( $appData, '/'. $name ) ) === $bytes;
check( '...and a restore puts every one of them back', $status === 200 && $back === true );

// --- The order the files are read in -----------------------------------------------------------------------------------------------------------------

echo "A database is read after its companions\n";

$appData = srcProject();
$sandbox = ninoSandboxDir( $appData );
$data		 = \Nino\Filesystem::path( $appData, '/data' ). '/ledger';

foreach( [ '-wal', '-shm', '-journal' ] as $suffix )
	file_put_contents( $data. '/db.sqlite'. $suffix, 'beside the database'. $suffix );

// The manifest as the safety snapshot makes it for a database it packs as it lies: the file first, what is beside it added behind
$text			= \Nino\Filesystem::path( $appData, '/text/en_US.php' );
$listed		= [ $text => 'text/en_US.php', $data. '/db.sqlite' => 'data/ledger/db.sqlite', $data. '/db.sqlite-wal' => 'data/ledger/db.sqlite-wal', $data. '/db.sqlite-shm' => 'data/ledger/db.sqlite-shm', $data. '/db.sqlite-journal' => 'data/ledger/db.sqlite-journal' ];

\Nino\Backup::pack( $appData, $listed, $sandbox. '/order.tar' );
$names = $tarNames( $sandbox. '/order.tar.gz' );
$at		 = array_flip( $names );

check( 'the log of writes and the files beside a database stand in the archive before the database', isset( $at['data/ledger/db.sqlite'] ) === true && $at['data/ledger/db.sqlite-wal'] < $at['data/ledger/db.sqlite'] && $at['data/ledger/db.sqlite-shm'] < $at['data/ledger/db.sqlite'] && $at['data/ledger/db.sqlite-journal'] < $at['data/ledger/db.sqlite'] );
check( '...and every other name keeps its place, nino.php last', $names === [ 'text/en_US.php', 'data/ledger/db.sqlite-wal', 'data/ledger/db.sqlite-shm', 'data/ledger/db.sqlite-journal', 'data/ledger/db.sqlite', 'nino.php' ] );

// The log goes between the listing and the read, and what it held is in the main file by then: the archive holds the main file as it is after
\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $data ): void {
	if( $phase === 'read' && $try === 1 ) {
		@unlink( $data. '/db.sqlite-wal' );
		file_put_contents( $data. '/db.sqlite', 'live db of the folder, with what the log held' );
	}
};

ninoWarnings();

try {
	\Nino\Backup::pack( $appData, $listed, $sandbox. '/wal.tar' );
}
finally {
	\Nino\Backup::$packing = null;
}

$plain = $sandbox. '/wal-read';
mkdir( $plain );
copy( $sandbox. '/wal.tar.gz', $plain. '.tar.gz' );
\Nino\Backup::unpack( $plain. '.tar.gz', $plain );
check( 'a -wal that goes after the files were looked at is left out, and the archive holds the database as it is after the log went into it',
	is_file( $plain. '/data/ledger/db.sqlite-wal' ) === false && file_get_contents( $plain. '/data/ledger/db.sqlite' ) === 'live db of the folder, with what the log held' && ( srcStamp( [ 'nino.php' => (string) file_get_contents( $plain. '/nino.php' ) ] )['left'] ?? null ) === [ 'data/ledger/db.sqlite-wal' ]
	&& is_file( $plain. '/data/ledger/db.sqlite-shm' ) === true );

// --- A file that is gone and back --------------------------------------------------------------------------------------------------------------------

echo "A file that is gone and back is no full disk\n";

$appData = srcProject();
$file		 = \Nino\Filesystem::path( $appData, '/assets/style.css' );

\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $file ): void {

	if( $phase === 'read' )
		@unlink( $file );

	// Back, another file, by the time the run looks at the files once more
	if( $phase === 'failed' )
		file_put_contents( $file, 'body { color: black; background: white }' );
};

ninoWarnings();

try {
	[ $status, $body ] = srcDoor( $appData, 'now', date( 'Y-m-d' ) );
}
finally {
	\Nino\Backup::$packing = null;
}

check( 'An archive made by hand, where a listed file went while it was packed and is back as another file, answers that it changed - and not that there was no room',
	$status === 500 && ( $body['code'] ?? '' ) === 'backups_source' && ( $body['params'] ?? null ) === [ 'assets/style.css' ] && str_contains( (string) ( $body['error'] ?? '' ), 'changed while it was packed' ) === true );

// A write that fails is a write that failed. The answer is a file only where the file that php names - it says the entry it could not open or whose contents it could not
// write - is gone or is another file, or where a listed real file is gone: a file that changed elsewhere meanwhile proves nothing
$cases = [
	'php names a file that is as it was, another changed meanwhile'				=> [ 'assets/style.css', 'write' ],
	'php names a file that is as it was'																	=> [ 'assets/style.css', 'write' ],
	'php names no file, a file changed meanwhile'													=> [ '', 'write' ],
	'php names a companion that is as it was, whose contents it could not write'	=> [ 'data/ledger/db.sqlite-wal', 'write' ],
	'php names a file that is gone'																				=> [ 'assets/style.css', 'assets/style.css' ],
	'php names a file that was written to meanwhile'											=> [ 'assets/style.css', 'assets/style.css' ],
	'php names no file, another real file is gone'												=> [ '', 'text/en_US.php' ],
];

foreach( $cases as $what => [ $named, $expect ] ) {

	$appData = srcProject();
	$a			 = \Nino\Filesystem::path( $appData, '/assets/style.css' );
	$b			 = \Nino\Filesystem::path( $appData, '/text/en_US.php' );
	$wal		 = \Nino\Filesystem::path( $appData, '/data/ledger/db.sqlite-wal' );
	file_put_contents( $wal, 'a log of writes' );
	$sandbox = ninoSandboxDir( $appData );
	$base		 = \Nino\Backup::manifest( $appData );

	// The change is the one of the case, made on this project's files, in the moment the write fails
	$act		= static function() use ( $what, $a, $b, $wal ): void {
		match( $what ) {
			'php names a file that is as it was, another changed meanwhile'					=> file_put_contents( $b, "<?php return [ '[[_epoch]]' => 'C' ];" ),
			'php names no file, a file changed meanwhile'														=> file_put_contents( $b, "<?php return [ '[[_epoch]]' => 'D' ];" ),
			'php names a companion that is as it was, whose contents it could not write'	=> file_put_contents( $wal, 'a longer log of writes' ),
			'php names a file that is gone'																					=> unlink( $a ),
			'php names a file that was written to meanwhile'												=> file_put_contents( $a, 'body { color: black; background: white; margin: 0 }' ),
			'php names no file, another real file is gone'													=> unlink( $b ),
			default																																	=> null,
		};
	};

	\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $named, $act ): void {
		if( $phase === 'read' && $try === 1 ) {
			$act();
			throw new \PharException( 'phar error: '. ( $named === '' ? 'could not write the archive' : 'contents of file "'. $named. '" could not be written' ) );
		}
	};

	$failure = null;

	try {
		\Nino\Backup::pack( $appData, $base, $sandbox. '/w.tar' );
	}
	catch( \RuntimeException $e ) {
		$failure = $e;
	}

	\Nino\Backup::$packing = null;

	check( 'a write that failed, '. $what. ': '. ( $expect === 'write' ? 'the answer is a write that failed' : 'the file is named: '. $expect ),
		$failure !== null && ( $expect === 'write' ? $failure->getCode() === \Nino\Backup::WRITE_FAILED : \Nino\Backup::sourceName( $failure ) === $expect ) );
}

// php could not open a real file that is there and is as it was: the answer names the file
$appOpen = srcProject();
$sandOpen = ninoSandboxDir( $appOpen );
$aOpen = \Nino\Filesystem::path( $appOpen, '/assets/style.css' );

\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $aOpen ): void {
	if( $phase === 'read' && $try === 1 )
		throw new \UnexpectedValueException( 'Iterator ArrayIterator returned a file that could not be opened "'. $aOpen. '"' );
};

$failure = null;

try {
	\Nino\Backup::pack( $appOpen, \Nino\Backup::manifest( $appOpen ), $sandOpen. '/o.tar' );
}
catch( \RuntimeException $e ) {
	$failure = $e;
}

\Nino\Backup::$packing = null;
check( 'a real file that is there, as it was, and that php says it could not open ends the packing with the code and its name', $failure !== null && \Nino\Backup::sourceName( $failure ) === 'assets/style.css' );

// The snapshot of a restore where the copies a feature handed over leave the disk without room: the files are packed as they lie, with the companions that raw() adds, and one
// of them - of a database that is not there - goes meanwhile
$appCopy = srcProject( 'copy' );
unlink( \Nino\Filesystem::path( $appCopy, '/data/ledger.sqlite' ) );
$wal		 = \Nino\Filesystem::path( $appCopy, '/data/ledger.sqlite-wal' );
file_put_contents( $wal, 'a log of writes' );
$others	 = srcOthers( $appCopy );
ninoWarnings();

\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $wal ): void {
	if( $phase === 'read' && $try === 1 )
		@unlink( $wal );
};

[ $status, $body ] = ninoCall( $appCopy, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => date( 'Y-m-d' ) ] );
\Nino\Backup::$packing = null;
\Nino\Backup::$diskFree = null;
$new		 = array_values( array_diff( srcOthers( $appCopy ), $others ) );

check( 'a safety snapshot that falls back to the files as they lie because the copies left no room is made with a companion that went meanwhile left out, and the restore begins',
	$status === 200 && count( $new ) === 1 && srcEpoch( $appCopy ) === 'A' && ( srcStamp( srcFiles( $appCopy, $new[0] ) )['left'] ?? null ) === [ 'data/ledger.sqlite-wal' ] );

// A disk that is really full - a tar that may not grow beyond a limit - while another process rewrites a file of the project: a write that failed
$code = <<<'CODE'
require $argv[1]. '/tests/harness.php';
$appData = ninoSandbox( 'ulimit' );
$d = ninoSandboxDir( $appData );
$manifest = [];
for( $i = 0; $i < 4; $i++ ) {
	file_put_contents( $d. '/b'. $i. '.bin', random_bytes( 1048576 ) );
	$manifest[$d. '/b'. $i. '.bin'] = 'assets/b'. $i. '.bin';
}
file_put_contents( $d. '/t.txt', 'text' );
$manifest[$d. '/t.txt'] = 'text/t.txt';
\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $d ): void {
	if( $phase === 'read' && $try === 1 )
		file_put_contents( $d. '/t.txt', 'text that another request wrote' );
};
try {
	\Nino\Backup::pack( $appData, $manifest, $d. '/x.tar' );
	echo "packed\n";
}
catch( \RuntimeException $e ) {
	echo $e->getCode(), ' ', \Nino\Backup::sourceName( $e ) ?? '-', "\n";
}
\Nino\Filesystem::removeDir( $d );
CODE;
$small = trim( (string) shell_exec( 'sh -c '. escapeshellarg( 'trap "" XFSZ; ulimit -f 2500; exec php -d display_errors=0 -r '. escapeshellarg( $code ). ' '. escapeshellarg( dirname( __DIR__ ) ) ). ' 2>/dev/null' ) );
check( 'a tar that may not grow beyond 2500 blocks, a file rewritten meanwhile: a write that failed, '. ( $small === '' ? 'no answer' : '"'. $small. '"' ), $small === \Nino\Backup::WRITE_FAILED. ' -' );

// The code a failure of the disk keeps: the files are as they were and the disk says no
\Nino\Backup::$diskFree = 1024;
[ $status, $body ] = srcDoor( $appData, 'now', date( 'Y-m-d' ) );
\Nino\Backup::$diskFree = null;
check( '...and a disk that has no room is still the answer of a disk', $status === 507 && ( $body['code'] ?? '' ) === 'backups_disk' );

// --- The daily backup: one more attempt after a file that could not be read, and after nothing else ------------------------------------------

echo "The daily backup after a file that could not be read\n";

/** Requests of the workbench that make the daily backup: how many tries reached the reading of the files, and what the next request is told */
$requests = static function( array &$appData, int $count, callable $between ): array {

	$tries = 0;
	$told	 = [];

	\Nino\Backup::$packing = static function( string $phase, int $try ) use ( &$tries, &$appData ): void {
		if( $phase === 'read' && $try === 1 ) {
			$tries++;
			@unlink( \Nino\Filesystem::path( $appData, '/assets/style.css' ) );
		}
	};

	try {
		for( $at = 1; $at <= $count; $at++ ) {
			$between( $at );
			\Nino\Modules\Backups::maybeRun( $appData );
			$told[] = \Nino\Modules\Backups::missed( $appData );
		}
	}
	finally {
		\Nino\Backup::$packing = null;
	}

	return [ $tries, $told ];
};

$appData = srcProject();
$today	 = \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php';
unlink( $today );
ninoWarnings();

[ $tries, $told ] = $requests( $appData, 4, static function( int $at ) use ( &$appData ): void {
	file_put_contents( \Nino\Filesystem::path( $appData, '/assets/style.css' ), 'body { color: black }' );
} );

check( 'a daily run that ends on a file that could not be read makes no archive, and the first request says so with the file and that there is one more try', is_file( $today ) === false && ( $told[0]['code'] ?? '' ) === 'source' && ( $told[0]['file'] ?? '' ) === 'assets/style.css' && ( $told[0]['retry'] ?? null ) === true );
check( 'three requests after it make exactly one more attempt: four requests, two attempts', $tries === 2 );
check( '...and after the second the note says there is no more attempt today, and keeps the file', ( $told[1]['code'] ?? '' ) === 'source' && ( $told[1]['retry'] ?? null ) === false && ( $told[3]['file'] ?? '' ) === 'assets/style.css' && is_file( $today ) === false );

// The cause is gone at the second attempt: the archive is made, the note goes, and nothing is tried once more
$appData = srcProject();
$today	 = \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php';
unlink( $today );
$vanishOnce = true;
\Nino\Backup::$packing = static function( string $phase, int $try ) use ( &$appData, &$vanishOnce ): void {
	if( $phase === 'read' && $try === 1 && $vanishOnce === true ) {
		$vanishOnce = false;
		@unlink( \Nino\Filesystem::path( $appData, '/assets/style.css' ) );
	}
};
\Nino\Modules\Backups::maybeRun( $appData );
$first = \Nino\Modules\Backups::missed( $appData );
file_put_contents( \Nino\Filesystem::path( $appData, '/assets/style.css' ), 'body { color: black }' );
\Nino\Modules\Backups::maybeRun( $appData );
\Nino\Backup::$packing = null;
check( 'a retry that goes through makes the archive of the day and takes the note away', ( $first['code'] ?? '' ) === 'source' && is_file( $today ) === true && is_file( \Nino\Modules\Backups::dir( $appData ). '/attempt.php' ) === false && \Nino\Modules\Backups::missed( $appData ) === null );

// A failure of another kind - a callback that throws - is one attempt a day
$appData = srcProject( 'throw' );
$today	 = \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php';
unlink( $today );
ninoWarnings();
for( $at = 1; $at <= 4; $at++ )
	\Nino\Modules\Backups::maybeRun( $appData );
$missed = \Nino\Modules\Backups::missed( $appData );
check( 'a failure that is no file that could not be read - a feature that cannot hand over its data - is one attempt a day: four requests, one attempt', ( $appData['./ledger/calls'] ?? 0 ) === 1 && is_file( $today ) === false && isset( $missed['code'] ) === false );

// A request that ended in the run, which left its note and no word
$appData = srcProject();
$today	 = \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php';
unlink( $today );
( new ReflectionMethod( '\Nino\Modules\Backups', '_noteAttempt' ) )->invoke( null, \Nino\Modules\Backups::dir( $appData ), date( 'Y-m-d' ), '' );
[ $tries ] = $requests( $appData, 3, static function( int $at ): void {} );
check( 'a run whose request ended with no word of its own - out of memory, out of time - is not tried once more: three requests, no attempt', $tries === 0 && is_file( $today ) === false );

// The note of a file that could not be read is read as one by the panel's own call, which words it: after the second attempt there is none to come
$appData = srcProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );
unlink( $dir. '/'. date( 'Y-m-d' ). '.php' );
( new ReflectionMethod( '\Nino\Modules\Backups', '_noteAttempt' ) )->invoke( null, $dir, date( 'Y-m-d' ), 'backup source could not be read: assets/style.css', 'source', 'assets/style.css', true );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
check( 'the list of the panel carries the failure: the code, the file, and that no more attempt is made today', $status === 200 && ( $body['missed']['code'] ?? '' ) === 'source' && ( $body['missed']['file'] ?? '' ) === 'assets/style.css' && ( $body['missed']['retry'] ?? null ) === false && is_file( $dir. '/'. date( 'Y-m-d' ). '.php' ) === false );

// --- The stamp, read by the restore and the list --------------------------------------------------------------------------------------------------------

echo "The stamp that says what was left out\n";

$appData = srcProject();
$file		 = \Nino\Filesystem::path( $appData, '/assets/theme.css.0123456789ab.tmp' );
file_put_contents( $file, 'bytes that go' );
\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $file ): void {
	if( $phase === 'read' && $try === 1 )
		@unlink( $file );
};
$id = \Nino\Modules\Backups::now( $appData );
\Nino\Backup::$packing = null;
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
check( 'an archive that left a file out is in the list of the panel like any other', is_string( $id ) === true && in_array( $id, array_column( $body['archives'] ?? [], 'id' ), true ) === true );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $id ] );
check( '...and is restored: the restore reads the version and the files it knows of the stamp and takes the rest as the note it is', $status === 200 && ( $body['restoredDate'] ?? null ) === $id && srcEpoch( $appData ) === 'B' );

// --- Real writers -------------------------------------------------------------------------------------------------------------------------------------

echo "Writers of a database that is packed while they write\n";

$appData = srcProject();
$sandbox = ninoSandboxDir( $appData );
$db			 = \Nino\Filesystem::path( $appData, '/data/ledger/db.sqlite' );
unlink( $db );
$pdo = new PDO( 'sqlite:'. $db );
$pdo->exec( 'CREATE TABLE t(w, s, p)' );
$pdo = null;

$stop	 = $sandbox. '/stop';
$code	 = '$db=$argv[1];$stop=$argv[2];echo "ready\n";fflush(STDOUT);$n=0;while(!file_exists($stop)){try{$p=new PDO("sqlite:".$db,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("PRAGMA busy_timeout=3000");for($i=0;$i<3;$i++){$p->exec("BEGIN IMMEDIATE");for($j=0;$j<20;$j++)$p->exec("INSERT INTO t VALUES(1,$n,randomblob(400))");usleep(random_int(500,3000));$p->exec("COMMIT");$n++;}$p=null;}catch(Throwable $x){$p=null;usleep(2000);}}echo "$n\n";';
$procs = [];
$pipes = [];

for( $writer = 0; $writer < 3; $writer++ ) {
	$procs[$writer] = proc_open( [ 'timeout', '120', PHP_BINARY, '-r', $code, $db, $stop ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'file', '/dev/null', 'a' ] ], $pipes[$writer] );
	fgets( $pipes[$writer][1] );
}

$failed	 = [];

for( $run = 0; $run < 20; $run++ ) {

	try {
		// A name of its own for each: php keeps what it knows of an archive it made by the name of its file
		\Nino\Backup::pack( $appData, \Nino\Backup::manifest( $appData ), $sandbox. '/writers-'. $run. '.tar' );
		@unlink( $sandbox. '/writers-'. $run. '.tar.gz' );
	}
	catch( \Throwable $e ) {
		$failed[] = get_class( $e ). ' '. $e->getCode(). ' '. $e->getMessage();
	}
}

for( $run = 0; $run < 3; $run++ ) {
	$answer = \Nino\Modules\Backups::now( $appData );

	if( is_string( $answer ) === false )
		$failed[] = json_encode( $answer );
}

touch( $stop );

foreach( $procs as $writer => $proc ) {
	stream_get_contents( $pipes[$writer][1] );
	proc_close( $proc );
}

check( 'twenty packings and three backups of a project whose database is written to by three processes: none ends, with a code or without'. ( $failed === [] ? '' : ' - '. implode( '; ', array_slice( $failed, 0, 3 ) ) ), $failed === [] );

// --- The safety snapshot that cannot be written --------------------------------------------------------------------------------------------------------

echo "A safety snapshot that cannot be written\n";

$appData = srcProject();
$dir		 = \Nino\Modules\Backups::dir( $appData );

// The directory of the archives is a file for a moment, as a disk that cannot take a file is
\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $dir ): void {
	if( $phase === 'read' && $try === 1 ) {
		rename( $dir, $dir. '-away' );
		file_put_contents( $dir, 'no folder' );
	}
};
ninoWarnings();
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => date( 'Y-m-d' ) ] );
\Nino\Backup::$packing = null;
unlink( $dir );
rename( $dir. '-away', $dir );
check( 'where the snapshot before a restore cannot be written, the restore does not begin and the answer is a 500 with the code of a write', $status === 500 && ( $body['code'] ?? '' ) === 'backups_write' && srcEpoch( $appData ) === 'B' && srcOthers( $appData ) === [] );

\Nino\Backup::$packing = null;

ninoDone( $appData );
