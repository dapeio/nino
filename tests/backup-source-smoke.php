<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	backup-source-smoke.php	Dependency-free smoke test for the files a backup packs and that go while it does: what an archive leaves out and says so
 *											(the companion of a database, the temporary file of a writer of the kernel) and what ends the run with the code
 *											backups_source and the name of the file (a real file of the project that is gone or changed), at each door a backup
 *											has - an archive made by hand, the daily run, the safety snapshot of the panel's restore and the one of the recovery page -;
 *											a file that is exchanged for a folder, a link or a named pipe, or written to in place, while it is packed; the files this request holds a lock on, which are not opened
 *											and are left out with the files beside them; the descriptors a pack leaves; the order a database and its companions are read in; the one more attempt the daily backup makes after such a
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

/** A project with the feature of the fixture, its files, an account that is signed in and the daily archive of the state 'A' of a text; the text is 'B' when it returns. With $hook 'throw' the callback of the feature fails from then on, with 'copy' it hands over a copy that does not fit, with 'keep' a copy whose database stays open in a transaction */
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

	if( $hook !== '' )
		\Nino\Callbacks::registerCallback( $appData, '/nino/admin/backup', [ \Nino\Modules\Ledger::class, [ 'throw' => 'callbackBackup', 'copy' => 'callbackCopy', 'keep' => 'callbackKeep', 'nothing' => 'callbackNothing' ][$hook] ] );

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

/**
 *	One door of a backup asked while a file of the project is exchanged or written to, at a moment of the packing: the facts of the run.
 *
 *	@param		string		$door					'now' or 'daily'
 *	@param		string		$name					The file's name in the archive, eg. 'assets/style.css'; a name that is not there is made
 *	@param		string		$to						What the file becomes: 'dir', 'link' (to a file outside), 'fifo', 'truncate', 'grow' (in place), 'rename' (a new file renamed over it), 'none'
 *	@param		string		$phase				'looked', 'read', 'opening' or 'opened' (see \Nino\Backup::$packing)
 *
 *	@return 	array											status, body, archive (its id, '' where none), files, stamp, told (the notices of what was left out), original (the bytes of the file), elapsed, clean
 */
function srcExchangeRun( string $door, string $name, string $to, string $phase ): array {

	$appData	= srcProject();
	$sandbox	= ninoSandboxDir( $appData );
	$file			= \Nino\Filesystem::path( $appData, '/'. $name );
	$outside	= $sandbox. '/outside.txt';
	$id				= date( 'Y-m-d' );

	@mkdir( dirname( $file ), 0755, true );

	if( is_file( $file ) === false )
		file_put_contents( $file, 'bytes that go' );

	file_put_contents( $outside, 'foreign content' );

	$original = (string) file_get_contents( $file );
	$others		= srcOthers( $appData );
	$temp			= srcTemp( $appData );
	$done			= false;
	$GLOBALS['srcSocketMade'] = true;

	$exchange = static function() use ( $to, $file, $outside ): void {

		if( $to === 'truncate' )
			file_put_contents( $file, '' );
		elseif( $to === 'grow' )
			file_put_contents( $file, ' and more', FILE_APPEND );
		elseif( $to === 'rewrite' ) {
			// The size stays, the bytes and the time of change are another: the whole second of the clock is not the one of the open
			file_put_contents( $file, str_repeat( 'x', (int) filesize( $file ) ) );
			touch( $file, time() + 10 );
		}
		elseif( $to === 'rename' ) {
			file_put_contents( $file. '.new', 'a new file that a writer renamed into place' );
			rename( $file. '.new', $file );
		}
		elseif( $to !== 'none' ) {

			unlink( $file );

			if( $to === 'dir' )
				mkdir( $file );
			elseif( $to === 'link' )
				symlink( $outside, $file );
			elseif( $to === 'dangling' )
				symlink( $outside. '.gone', $file );
			elseif( $to === 'socket' ) {

				// The path of a unix socket may have 108 bytes, which a long temporary folder exceeds: it is bound by its name, from the folder it lies in
				$here = (string) getcwd();
				chdir( dirname( $file ) );
				$GLOBALS['srcSocket'] = @stream_socket_server( 'unix://'. basename( $file ) );
				chdir( $here );

				if( $GLOBALS['srcSocket'] === false || @filetype( $file ) !== 'socket' )
					$GLOBALS['srcSocketMade'] = false;
			}
			else
				posix_mkfifo( $file, 0644 );
		}
	};

	\Nino\Backup::$packing = static function( string $at, int $try, string $named = '' ) use ( $phase, $name, $exchange, &$done ): void {

		if( $done === false && $at === $phase && ( $phase === 'read' || $named === $name ) ) {
			$done = true;
			$exchange();
		}
	};

	ninoWarnings();
	$began = microtime( true );

	try {
		[ $status, $body ] = srcDoor( $appData, $door, $id );
	}
	finally {
		\Nino\Backup::$packing = null;
	}

	$elapsed	= microtime( true ) - $began;
	$told			= array_values( array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'left out of the archive' ) ) );
	$new			= array_values( array_diff( srcOthers( $appData ), $others ) );
	$archive	= $door === 'daily' ? ( is_file( \Nino\Modules\Backups::dir( $appData ). '/'. $id. '.php' ) === true ? $id : '' ) : (string) ( $door === 'now' ? ( $body['id'] ?? '' ) : ( $new[0] ?? '' ) );
	$files		= $archive === '' ? [] : srcFiles( $appData, $archive );

	return [ 'made' => $GLOBALS['srcSocketMade'], 'status' => $status, 'body' => $body, 'archive' => $archive, 'files' => $files, 'stamp' => srcStamp( $files ), 'told' => $told, 'original' => $original, 'elapsed' => $elapsed,
		'clean' => srcTemp( $appData ) === $temp && ( glob( \Nino\Modules\Backups::dir( $appData ). '/*.tmp' ) ?: [] ) === [] && array_diff( $new, $archive === '' ? [] : [ $archive ] ) === [] ];
}

// The child process of a run that is asked of a named pipe (see below) is this file with the arguments of the run: it prints the facts and ends
if( ( $argv[1] ?? '' ) === 'exchange' ) {
	echo json_encode( srcExchangeRun( $argv[2], $argv[3], $argv[4], $argv[5] ), JSON_INVALID_UTF8_SUBSTITUTE );
	exit( 0 );
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

// --- A file that is gone at the open, and one that is gone and back ----------------------------------------------------------------------------------

echo "A file that is gone when it is opened, and one that is back as another file\n";

$appData = srcProject();
$file		 = \Nino\Filesystem::path( $appData, '/assets/style.css' );

\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $file ): void {
	if( $phase === 'read' )
		@unlink( $file );
};

ninoWarnings();

try {
	[ $status, $body ] = srcDoor( $appData, 'now', date( 'Y-m-d' ) );
}
finally {
	\Nino\Backup::$packing = null;
}

check( 'An archive made by hand, where a listed file went while it was packed and is no file at the open, answers that it could not be read, by its name',
	$status === 500 && ( $body['code'] ?? '' ) === 'backups_source' && ( $body['params'] ?? null ) === [ 'assets/style.css' ] && str_contains( (string) ( $body['error'] ?? '' ), 'could not be read' ) === true );

// Back before the open as another file: it is the file the path names at the open, and it is packed whole
$appData = srcProject();
$file		 = \Nino\Filesystem::path( $appData, '/assets/style.css' );

\Nino\Backup::$packing = static function( string $phase, int $try ) use ( $file ): void {

	if( $phase === 'read' ) {
		unlink( $file );
		file_put_contents( $file, 'body { color: black; background: white }' );
	}
};

try {
	[ $status, $body ] = srcDoor( $appData, 'now', date( 'Y-m-d' ) );
}
finally {
	\Nino\Backup::$packing = null;
}

check( 'An archive made by hand, where a listed file went while it was packed and is back as another file before the open, holds the file that is there',
	$status === 200 && is_string( $body['id'] ?? null ) === true && ( srcFiles( $appData, (string) $body['id'] )['assets/style.css'] ?? '' ) === 'body { color: black; background: white }' );

// A write that fails is a write that failed: php opens no file by a path, so what it says of a file it could not write is no word about the file. A file that went or changed is
// found at its open and answered there (see the sections above and below)
$cases = [
	'php names a file that is as it was, another changed meanwhile'				=> [ 'assets/style.css', 'write' ],
	'php names a file that is as it was'																	=> [ 'assets/style.css', 'write' ],
	'php names no file, a file changed meanwhile'													=> [ '', 'write' ],
	'php names a companion that is as it was, whose contents it could not write'	=> [ 'data/ledger/db.sqlite-wal', 'write' ],
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

// --- A file that is exchanged between the look and the open, or written to in place while it is read ---------------------------------------------

echo "A file that is exchanged for a folder, a link or a pipe, or written to in place, while a backup packs it\n";

// The summary of the run is the facts of one door asked while a file of the project is exchanged; the checks read the facts, and so does the parent of a child process
// that was asked for a named pipe: a pipe that php opens by a path and waits for a writer on ends the request, so the run is made under a time limit of its own

$exchanges = [];

foreach( [ 'now', 'daily' ] as $door ) {

	// A real file that becomes something else, at each moment between the look and the read
	foreach( [ 'dir', 'link', 'fifo' ] as $to )
		foreach( $to === 'dir' ? [ 'looked', 'read', 'opening' ] : [ 'read', 'opening' ] as $phase )
			$exchanges[] = [ $door, 'assets/style.css', $to, $phase, 'source', [] ];

	// A socket or a link to nothing put there before the lstat is a file that changed; put there between the lstat and the open - which cannot open it - it is a file that could not be read
	foreach( [ 'socket', 'dangling' ] as $to ) {
		$exchanges[] = [ $door, 'assets/style.css', $to, 'read', 'source', [] ];
		$exchanges[] = [ $door, 'assets/style.css', $to, 'opening', 'unread', [] ];
	}

	// A name that may be left out is left out, and the archive is made
	foreach( [ 'dir', 'link', 'fifo' ] as $to )
		foreach( [ 'read', 'opening' ] as $phase )
			$exchanges[] = [ $door, 'assets/theme.css.0123456789ab.tmp', $to, $phase, 'left', [] ];

	foreach( [ 'socket', 'dangling' ] as $to )
		$exchanges[] = [ $door, 'assets/theme.css.0123456789ab.tmp', $to, 'opening', 'left', [] ];

	// Written to in place after the open: a torn entry ends the run, outside data/
	foreach( [ 'truncate', 'grow' ] as $to ) {
		$exchanges[] = [ $door, 'assets/style.css', $to, 'opened', 'source', [] ];
		$exchanges[] = [ $door, 'text/en_US.php', $to, 'opened', 'source', [] ];
	}

	// The same size and another time of change: a rewrite in place that the size does not show
	$exchanges[] = [ $door, 'assets/style.css', 'rewrite', 'opened', 'source', [] ];
	$exchanges[] = [ $door, 'text/en_US.php', 'rewrite', 'opened', 'source', [] ];
	$exchanges[] = [ $door, 'data/ledger/notes.php', 'rewrite', 'opened', 'lies', [ str_repeat( 'x', strlen( "<?php return [ 'live' => 1 ];" ) ) ] ];
	$exchanges[] = [ $door, 'assets/theme.css.0123456789ab.tmp', 'rewrite', 'opened', 'lies', [ str_repeat( 'x', strlen( 'bytes that go' ) ) ] ];

	// ... and is packed as it lies below data/, and where it may vanish
	$exchanges[] = [ $door, 'data/ledger/notes.php', 'truncate', 'opened', 'lies', [ '' ] ];
	$exchanges[] = [ $door, 'data/ledger/notes.php', 'grow', 'opened', 'lies', [ "<?php return [ 'live' => 1 ];". ' and more', "<?php return [ 'live' => 1 ];" ] ];
	$exchanges[] = [ $door, 'assets/theme.css.0123456789ab.tmp', 'grow', 'opened', 'lies', [ 'bytes that go and more', 'bytes that go' ] ];

	// A writer that renames a new file over the path: the entry is whole, the old file or the new, never mixed
	foreach( [ 'looked', 'opening', 'opened' ] as $phase )
		$exchanges[] = [ $door, 'assets/style.css', 'rename', $phase, 'whole', [ 'body { color: black }', 'a new file that a writer renamed into place' ] ];
}

foreach( $exchanges as [ $door, $name, $to, $phase, $expect, $accept ] ) {

	// A pipe is asked of a process of its own, under a time limit: a run that waits on it has no answer, which is a failure and does not end this suite
	if( $to === 'fifo' ) {
		$command = escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( __FILE__ ). ' exchange '. implode( ' ', array_map( 'escapeshellarg', [ $door, $name, $to, $phase ] ) );
		$facts	 = json_decode( (string) shell_exec( 'timeout 30 '. $command. ' 2>/dev/null' ), true );
		$facts	 = is_array( $facts ) === true ? $facts : null;
	}
	else
		$facts = srcExchangeRun( $door, $name, $to, $phase );

	$label = $door. ', '. $name. ' becomes '. [ 'dir' => 'a folder', 'link' => 'a link to a file outside', 'fifo' => 'a named pipe', 'truncate' => 'a file truncated in place', 'grow' => 'a file grown in place', 'rewrite' => 'a file rewritten in place with its size', 'socket' => 'a socket', 'dangling' => 'a link to nothing', 'rename' => 'a new file renamed into place' ][$to]. ' at '. $phase;

	if( $facts === null ) {
		check( $label. ': the run ends with an answer, and does not wait', false );
		continue;
	}

	if( $facts['made'] === false ) {
		echo "  not run  - ". $label. ": this system makes no socket in this folder\n";
		continue;
	}

	$answer = (array) $facts['body'];

	if( $expect === 'source' || $expect === 'unread' ) {

		check( $label. ': the run ends and says the file changed - no archive, nothing left behind',
			$facts['archive'] === '' && $facts['clean'] === true && $facts['status'] === 500 && $facts['elapsed'] < 10
			&& ( $door === 'daily' ? ( ( $answer['code'] ?? '' ) === 'source' && ( $answer['file'] ?? '' ) === $name && ( $answer['retry'] ?? null ) === true )
				: ( ( $answer['code'] ?? '' ) === 'backups_source' && ( $answer['params'] ?? null ) === [ $name ] && str_contains( (string) ( $answer['error'] ?? '' ), $expect === 'unread' ? 'could not be read' : 'changed while it was packed' ) === true ) ) );
		continue;
	}

	if( $expect === 'left' ) {

		check( $label. ': the archive is made without it, and the stamp and one notice say so',
			$facts['status'] === 200 && $facts['archive'] !== '' && isset( $facts['files']['text/en_US.php'] ) === true && isset( $facts['files'][$name] ) === false
			&& ( $facts['stamp']['left'] ?? null ) === [ $name ] && count( $facts['told'] ) === 1 && $facts['clean'] === true );
		continue;
	}

	check( $label. ': the archive is made and the entry is '. ( $expect === 'whole' ? 'whole' : 'as php read it' ),
		$facts['status'] === 200 && $facts['archive'] !== '' && in_array( $facts['files'][$name] ?? null, $accept, true ) === true && isset( $facts['stamp']['left'] ) === false && $facts['clean'] === true );
}

// The run that was told of a swap is told of nothing else: the control, which packs as it always did
$facts = srcExchangeRun( 'now', 'assets/style.css', 'none', 'read' );
check( 'nothing exchanged, the control: the archive is made and holds the file as it was', $facts['status'] === 200 && ( $facts['files']['assets/style.css'] ?? null ) === $facts['original'] );

// A read-only look at what php reads: a link that is swapped in is followed by nobody, and the foreign content is in no archive
foreach( [ 'now', 'daily' ] as $door ) {
	$facts = srcExchangeRun( $door, 'assets/style.css', 'link', 'opening' );
	check( $door. ', a link swapped in between the look and the open: the foreign content it points at is in no archive', in_array( 'foreign content', $facts['files'], true ) === false && $facts['archive'] === '' );
}

// --- The files this request holds a lock on ------------------------------------------------------------------------------------------------------------

echo "Files this request holds a lock on are not opened, and are left out with the files beside them\n";

/** The locks this process holds on a file, as the list of the system names them: [ 'POSIX WRITE 0-EOF', ... ], sorted */
function srcOwnLocks( string $file ): array {

	$own	= [];
	$stat = @stat( $file );

	if( $stat === false )
		return $own;

	foreach( explode( "\n", (string) @file_get_contents( '/proc/locks' ) ) as $line )
		if( preg_match( '/^\d+:\s+(POSIX|FLOCK|OFDLCK)\s+\S+\s+(\S+)\s+(-?\d+)\s+[0-9a-f]+:[0-9a-f]+:(\d+)\s+(\d+)\s+(\S+)/', $line, $match ) === 1 && (int) $match[3] === getmypid() && (int) $match[4] === $stat['ino'] )
			$own[] = $match[1]. ' '. $match[2]. ' '. $match[5]. '-'. $match[6];

	sort( $own );

	return $own;
}

/** What a second process finds of a database this one holds: a write transaction is refused, and a connection that wrote and closed does not take the log of writes away */
function srcOtherProcess( string $db, string $mode ): string {

	$code = $mode === 'wal'
		? '$p=new PDO("sqlite:".$argv[1],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("PRAGMA busy_timeout=0");try{$p->exec("INSERT INTO t VALUES(1)");$p=null;clearstatcache();echo is_file($argv[1]."-wal")?"wrote, log of writes kept":"wrote, log of writes taken away";}catch(Throwable $e){echo "refused";}'
		: '$p=new PDO("sqlite:".$argv[1],null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("PRAGMA busy_timeout=0");try{$p->exec("BEGIN EXCLUSIVE");echo "granted";$p->exec("ROLLBACK");}catch(Throwable $e){echo "refused";}';

	return trim( (string) shell_exec( escapeshellarg( PHP_BINARY ). ' -r '. escapeshellarg( $code ). ' '. escapeshellarg( $db ). ' 2>&1' ) );
}

/** The database of the feature, made and held by this process: $hold 'write' a write transaction, 'idle' a connection that read and is in no transaction */
function srcHold( string $db, string $mode, string $hold ): PDO {

	$pdo = new PDO( 'sqlite:'. $db, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
	$pdo->exec( 'PRAGMA journal_mode='. $mode );
	$pdo->exec( 'CREATE TABLE t(x)' );
	$pdo->exec( 'INSERT INTO t VALUES(randomblob(300))' );

	if( $hold === 'write' ) {
		$pdo->exec( 'BEGIN IMMEDIATE' );
		$pdo->exec( 'INSERT INTO t VALUES(randomblob(300))' );
	}
	else
		$pdo->query( 'SELECT count(*) FROM t' )->fetchColumn();

	return $pdo;
}

$database = [ 'data/ledger.sqlite', 'data/ledger.sqlite-wal', 'data/ledger.sqlite-shm', 'data/ledger.sqlite-journal' ];
$inFolder	= [ 'data/ledger/db.sqlite', 'data/ledger/db.sqlite-wal', 'data/ledger/db.sqlite-shm', 'data/ledger/db.sqlite-journal' ];

if( is_readable( '/proc/locks' ) === false )
	echo "  not run  - a request that holds a lock: this system does not list the locks of its processes\n";
else
	foreach( [ [ 'delete', 'write' ], [ 'wal', 'write' ], [ 'wal', 'idle' ] ] as [ $mode, $hold ] ) {

		// (a) The safety snapshot of a restore, after the hook of the feature failed: its files are packed as they lie, and the project's archive is the one that has no database
		$appData	= srcProject();
		$sandbox	= ninoSandboxDir( $appData );
		$db				= \Nino\Filesystem::path( $appData, '/data/ledger.sqlite' );
		unlink( $db );
		$archive	= \Nino\Modules\Backups::now( $appData );
		\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[_epoch]]' => 'C' ] );
		\Nino\Callbacks::registerCallback( $appData, '/nino/admin/backup', [ \Nino\Modules\Ledger::class, 'callbackBackup' ] );
		$own			= srcHold( $db, $mode, $hold );
		$names		= array_values( array_filter( $database, static fn( string $name ): bool => is_file( \Nino\Filesystem::path( $appData, '/'. $name ) ) ) );
		$label		= $mode. ', '. $hold;

		if( srcOwnLocks( $db ) === [] ) {
			echo "  not run  - ". $label. ": the system lists no lock of this process on the database\n";
			continue;
		}

		$locks		= [ srcOwnLocks( $db ), srcOwnLocks( $db. '-shm' ) ];
		$other		= srcOtherProcess( $db, $mode );
		$others		= srcOthers( $appData );
		ninoWarnings();
		[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => (string) $archive ] );
		$told			= array_values( array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'held open by this request' ) ) );
		$new			= array_values( array_diff( srcOthers( $appData ), $others ) );
		$files		= $new === [] ? [] : srcFiles( $appData, $new[0] );
		$held			= (array) ( srcStamp( $files )['held'] ?? [] );
		sort( $held );
		$sorted		= $names;
		sort( $sorted );

		check( 'safety snapshot ('. $label. '): the files of the database and those beside it are not in it, the rest of the project is', $new !== [] && array_intersect( $database, array_keys( $files ) ) === [] && isset( $files['text/en_US.php'] ) === true && isset( $files['assets/style.css'] ) === true );
		check( '...its stamp names them as held, and the log says so once', $held === $sorted && count( $told ) === 1 && str_contains( $told[0], 'data/ledger.sqlite' ) === true );
		check( '...the restore is made, and its answer carries the notice of the snapshot that lacks them', $status === 200 && srcEpoch( $appData ) === 'B' && ( $body['notice']['code'] ?? '' ) === 'backups_snapshot_held' && str_contains( (string) ( $body['notice']['params'][0] ?? '' ), 'data/ledger.sqlite' ) === true );
		check( '...the locks of this process on the files are the ones it held before, and a second process is refused as before', [ srcOwnLocks( $db ), srcOwnLocks( $db. '-shm' ) ] === $locks && srcOtherProcess( $db, $mode ) === $other && $other !== 'granted' && $other !== 'wrote, log of writes taken away' );

		// The recovery page restores through the same snapshot, and says the same (once: a second restore in the same second waits for the next one)
		if( $mode === 'delete' ) {
			[ $status, $body ] = srcDoor( $appData, 'recovery', (string) $archive );
			check( '...the recovery page, which makes the same snapshot, answers with the same notice', $status === 200 && ( $body['notice']['code'] ?? '' ) === 'backups_snapshot_held' && str_contains( (string) ( $body['notice']['params'][0] ?? '' ), 'data/ledger.sqlite' ) === true );
		}

		$committed = true;

		try {
			if( $hold === 'write' )
				$own->exec( 'COMMIT' );
		}
		catch( \Throwable $e ) {
			$committed = false;
		}

		$rows = (int) $own->query( 'SELECT count(*) FROM t' )->fetchColumn();
		check( '...the transaction commits, and the database is whole', $committed === true && $rows >= 2 && $own->query( 'PRAGMA integrity_check' )->fetchColumn() === 'ok' );
		$own = null;

		// (b) A feature that has no hook, through "Back up now" and the daily run: the live files are packed as they lie, which is where this request's locks are. The database of a
		// folder the feature declares is listed with the files beside it
		$appData	= srcProject();
		$db				= \Nino\Filesystem::path( $appData, '/data/ledger/db.sqlite' );
		unlink( $db );
		$own			= srcHold( $db, $mode, $hold );
		$names		= array_values( array_filter( $inFolder, static fn( string $name ): bool => is_file( \Nino\Filesystem::path( $appData, '/'. $name ) ) ) );
		$locks		= [ srcOwnLocks( $db ), srcOwnLocks( $db. '-shm' ) ];
		$other		= srcOtherProcess( $db, $mode );
		ninoWarnings();
		[ $status, $body ] = srcDoor( $appData, 'now', date( 'Y-m-d' ) );
		$told			= array_values( array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'held open by this request' ) ) );
		$files		= srcFiles( $appData, (string) ( $body['id'] ?? '' ) );
		$held			= (array) ( srcStamp( $files )['held'] ?? [] );
		sort( $held );
		$sorted		= $names;
		sort( $sorted );

		check( 'Back up now ('. $label. '): the archive is made, without the files of the database and those beside it', $status === 200 && is_string( $body['id'] ?? null ) === true && array_intersect( $inFolder, array_keys( $files ) ) === [] && isset( $files['text/en_US.php'] ) === true );
		check( '...the stamp names them as held, the log says so once, and the answer carries the notice', $held === $sorted && count( $told ) === 1 && ( $body['notice']['code'] ?? '' ) === 'backups_held' && str_contains( (string) ( $body['notice']['params'][0] ?? '' ), 'data/ledger/db.sqlite' ) === true );
		check( '...the locks of this process on the files are the ones it held before, and a second process is refused as before', [ srcOwnLocks( $db ), srcOwnLocks( $db. '-shm' ) ] === $locks && srcOtherProcess( $db, $mode ) === $other && $other !== 'granted' && $other !== 'wrote, log of writes taken away' );

		// The daily run says it in the stamp and in the log, and in nothing else: the day has its archive
		ninoWarnings();
		[ $status ] = srcDoor( $appData, 'daily', date( 'Y-m-d' ) );
		$told			= array_values( array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'held open by this request' ) ) );
		$daily		= $status === 200 ? srcFiles( $appData, date( 'Y-m-d' ) ) : [];
		$held			= (array) ( srcStamp( $daily )['held'] ?? [] );
		sort( $held );
		$missed		= \Nino\Modules\Backups::lacking( $appData );
		check( 'the daily run ('. $label. '): the archive of the day is made without them, and the stamp and one warning say so', $status === 200 && array_intersect( $inFolder, array_keys( $daily ) ) === [] && $held === $sorted && count( $told ) === 1 );
		check( '...lacking() names them for the archive of the day that is there, and missed(), which is the answer for a daily that failed, says nothing', str_contains( (string) ( $missed['file'] ?? '' ), 'data/ledger/db.sqlite' ) === true && is_file( \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php' ) === true && \Nino\Modules\Backups::missed( $appData ) === null );

		// The next request of the day makes nothing: the archive is there, and the feature would hold the file each time
		$reads = 0;
		\Nino\Backup::$packing = static function( string $phase ) use ( &$reads ): void {
			if( $phase === 'read' )
				$reads++;
		};
		\Nino\Modules\Backups::maybeRun( $appData );
		check( '...and the next request makes no second attempt, and the note stays', $reads === 0 && \Nino\Modules\Backups::lacking( $appData ) !== null );

		// A day's archive that was taken away is made once more: the note of an archive that lacks files is no attempt that failed
		unlink( \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php' );
		$goneLacking = \Nino\Modules\Backups::lacking( $appData );
		$goneMissed	 = \Nino\Modules\Backups::missed( $appData );
		\Nino\Modules\Backups::maybeRun( $appData );
		\Nino\Backup::$packing = null;
		check( '...an archive of the day that was taken away ends the note, and is made on the next request, which says so too', $goneLacking === null && $goneMissed === null && $reads === 1 && is_file( \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php' ) === true && \Nino\Modules\Backups::lacking( $appData ) !== null );

		// The note of an earlier day is no note of today, whatever archive is there
		$dailyNote = \Nino\Modules\Backups::dir( $appData ). '/attempt.php';
		$todayNote = (string) file_get_contents( $dailyNote );
		( new ReflectionMethod( '\Nino\Modules\Backups', '_noteAttempt' ) )->invoke( null, \Nino\Modules\Backups::dir( $appData ), '2020-01-01', 'the archive of the day lacks files that the request held open', 'held', 'data/ledger/db.sqlite' );
		check( '...the note of an earlier day is not shown as today\'s', \Nino\Modules\Backups::lacking( $appData ) === null );
		file_put_contents( $dailyNote, $todayNote );

		// The panel's list hands the note on
		[ $listStatus, $list ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
		check( '...and the list of the panel carries it under a key of its own, beside a missed that is null', $listStatus === 200 && $list['missed'] === null && str_contains( (string) ( $list['lacking']['file'] ?? '' ), 'data/ledger/db.sqlite' ) === true );

		// A daily that fails writes its own note and is never hidden by the one of an archive that lacks files; the retry that follows makes the archive and says what it lacks
		unlink( \Nino\Modules\Backups::dir( $appData ). '/'. date( 'Y-m-d' ). '.php' );
		$styleFile = \Nino\Filesystem::path( $appData, '/assets/style.css' );
		\Nino\Backup::$packing = static function( string $phase ) use ( $styleFile ): void {
			if( $phase === 'read' )
				@unlink( $styleFile );
		};
		\Nino\Modules\Backups::maybeRun( $appData );
		\Nino\Backup::$packing = null;
		$failed = \Nino\Modules\Backups::missed( $appData );
		check( '...a daily that fails says its failure by missed(), and lacking() says nothing for the archive that is not there', ( $failed['code'] ?? '' ) === 'source' && ( $failed['file'] ?? '' ) === 'assets/style.css' && \Nino\Modules\Backups::lacking( $appData ) === null );
		file_put_contents( $styleFile, 'body { color: black }' );
		\Nino\Modules\Backups::maybeRun( $appData );
		check( '...and the retry that goes through makes the archive, which lacks the files as before', \Nino\Modules\Backups::missed( $appData ) === null && \Nino\Modules\Backups::lacking( $appData ) !== null );

		$committed = true;

		try {
			if( $hold === 'write' )
				$own->exec( 'COMMIT' );
		}
		catch( \Throwable $e ) {
			$committed = false;
		}

		check( '...and the transaction commits', $committed === true && $own->query( 'PRAGMA integrity_check' )->fetchColumn() === 'ok' );

		// A whole "Back up now" leaves the note of the daily as it is
		$own = null;
		[ $status, $body ] = srcDoor( $appData, 'now', date( 'Y-m-d' ) );
		check( '...a whole "Back up now" leaves the note of the daily as it is', $status === 200 && isset( $body['notice'] ) === false && \Nino\Modules\Backups::lacking( $appData ) !== null );

		// The next day's daily, made by a request that holds nothing, is whole and takes the note away. The note of the day before is no word about today
		$own = null;
		$appData['./nino/backups/today'] = '2099-01-01';
		\Nino\Modules\Backups::maybeRun( $appData );
		unset( $appData['./nino/backups/today'] );
		check( '...the daily of a later day that is whole takes the note away, and says nothing', is_file( \Nino\Modules\Backups::dir( $appData ). '/2099-01-01.php' ) === true && is_file( \Nino\Modules\Backups::dir( $appData ). '/attempt.php' ) === false && \Nino\Modules\Backups::missed( $appData ) === null );

		// Closed, the database is in the archive: what was held is only what a request holds
		$own = null;
		[ $status, $body ] = srcDoor( $appData, 'now', date( 'Y-m-d' ) );
		$files = srcFiles( $appData, (string) ( $body['id'] ?? '' ) );
		check( 'Back up now ('. $label. ') once the connection is closed: the database is in the archive, and nothing is said', $status === 200 && isset( $files['data/ledger/db.sqlite'] ) === true && isset( srcStamp( $files )['held'] ) === false && isset( $body['notice'] ) === false );
	}

// The restore of an archive that lacks a file the project holds leaves the live file as it is
if( is_readable( '/proc/locks' ) === true ) {

	$appData	= srcProject();
	$db				= \Nino\Filesystem::path( $appData, '/data/ledger.sqlite' );
	unlink( $db );
	$archive	= \Nino\Modules\Backups::now( $appData );
	$own			= srcHold( $db, 'delete', 'write' );
	$own->exec( 'COMMIT' );
	$before		= (string) file_get_contents( $db );
	$own			= null;
	[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => (string) $archive ] );
	$check		= new PDO( 'sqlite:'. $db );
	check( 'the restore of an archive that lacks a database the project has leaves the live file as it is, and says nothing of it', $status === 200 && (string) file_get_contents( $db ) === $before && (int) $check->query( 'SELECT count(*) FROM t' )->fetchColumn() === 2 && $check->query( 'PRAGMA integrity_check' )->fetchColumn() === 'ok' );
	$check		= null;
}

// What a hook hands over is packed, whatever lock this request holds on it: the copy a feature keeps open is the copy the archive is to hold
if( is_readable( '/proc/locks' ) === true ) {

	$appData = srcProject( 'keep' );
	ninoWarnings();
	[ $status, $body ] = srcDoor( $appData, 'now', date( 'Y-m-d' ) );
	$told		 = array_values( array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'held open by this request' ) ) );
	$files	 = srcFiles( $appData, (string) ( $body['id'] ?? '' ) );
	$stamp	 = srcStamp( $files );
	check( 'a copy that the hook of a feature hands over and keeps a transaction open on is packed: the archive holds it, and nothing is held', $status === 200 && str_starts_with( (string) ( $files['data/plain.sqlite'] ?? '' ), 'SQLite format 3' ) === true
		&& ( $stamp['copies'] ?? null ) === [ 'data/plain.sqlite' ] && isset( $stamp['held'] ) === false && $told === [] && isset( $body['notice'] ) === false );
	\Nino\Modules\Ledger::$kept = null;
}

// The list of the locks is read from the place a test names, and only what ends with the descriptor counts: a lock of this process of the kind POSIX
$appData	= srcProject();
$sandbox	= ninoSandboxDir( $appData );
$style		= \Nino\Filesystem::path( $appData, '/assets/style.css' );
$text			= \Nino\Filesystem::path( $appData, '/text/en_US.php' );
$stat			= stat( $style );
$device		= sprintf( '%02x:%02x:%d', ( ( $stat['dev'] >> 8 ) & 0xfff ) | ( ( $stat['dev'] >> 32 ) & ~0xfff ), ( $stat['dev'] & 0xff ) | ( ( $stat['dev'] >> 12 ) & ~0xff ), $stat['ino'] );

/** One run of pack() with a list of locks of a test: the names it holds, and the names of the archive */
$locked		= static function( array $lines, array $manifest, array $notes = [] ) use ( &$appData, $sandbox ): array {

	static $run = 0;

	file_put_contents( $sandbox. '/locks.txt', implode( "\n", $lines ). "\n" );
	\Nino\Backup::$locksFile = $sandbox. '/locks.txt';
	$held = [];
	$tar	= $sandbox. '/locked-'. ++$run. '.tar';

	try {
		\Nino\Backup::pack( $appData, $manifest, $tar, $notes, $held );
	}
	finally {
		\Nino\Backup::$locksFile = null;
	}

	$into = $sandbox. '/locked-'. $run;
	mkdir( $into );
	copy( $tar. '.gz', $into. '.tar.gz' );
	\Nino\Backup::unpack( $into. '.tar.gz', $into );
	$names = array_keys( srcNames( $into ) );
	sort( $names );

	return [ $held, $names ];
};

/** The files of an unpacked directory: name => true */
function srcNames( string $root ): array {

	$files = [];

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY ) as $file )
		$files[str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $root ) + 1 ) )] = true;

	return $files;
}

$two			= [ $style => 'assets/style.css', $text => 'text/en_US.php' ];
$pid			= (string) getmypid();
$posix		= '1: POSIX  ADVISORY  WRITE '. $pid. ' '. $device. ' 0 EOF';

[ $held, $names ] = $locked( [ $posix ], $two );
check( 'a lock of kind POSIX that carries the pid of this process holds the file: it is not packed, and the run names it', $held === [ 'assets/style.css' ] && $names === [ 'nino.php', 'text/en_US.php' ] );

foreach( [
	'a lock of the kind FLOCK, which the kernel takes itself'		=> '1: FLOCK  ADVISORY  WRITE '. $pid. ' '. $device. ' 0 EOF',
	'a lock of the kind OFDLCK, which another close does not end'	=> '1: OFDLCK ADVISORY  WRITE -1 '. $device. ' 0 EOF',
	'a POSIX lock of another process'														=> '1: POSIX  ADVISORY  WRITE '. ( (int) $pid + 1 ). ' '. $device. ' 0 EOF',
	'a POSIX lock of this process that waits'										=> '1: -> POSIX  ADVISORY  WRITE '. $pid. ' '. $device. ' 0 EOF',
	'a POSIX lock of this process on another file'							=> '1: POSIX  ADVISORY  WRITE '. $pid. ' 00:00:1 0 EOF',
] as $what => $line ) {
	[ $held, $names ] = $locked( [ $line ], $two );
	check( $what. ' does not hold the file: it is packed', $held === [] && $names === [ 'assets/style.css', 'nino.php', 'text/en_US.php' ] );
}

[ $held, $names ] = $locked( [ $posix ], $two, [ 'copies' => [ 'assets/style.css' ] ] );
check( 'a file that is a copy a hook handed over is packed whatever lock this process holds on it', $held === [] && $names === [ 'assets/style.css', 'nino.php', 'text/en_US.php' ] );

// The pid of this process is the one the system says it is: the process the link of the process points to, where it says one
$self = $sandbox. '/self-link';
symlink( '4242', $self );
\Nino\Backup::$selfLink = $self;
[ $held ] = $locked( [ '1: POSIX  ADVISORY  WRITE 4242 '. $device. ' 0 EOF' ], $two );
check( 'where the system names the process by a pid that getmypid() does not answer - a pid namespace that sees the procfs of its host - the locks of that pid are the ones held', $held === [ 'assets/style.css' ] );
[ $held ] = $locked( [ $posix ], $two );
check( '...and the locks of the pid that getmypid() answers are not', $held === [] );
\Nino\Backup::$selfLink = $sandbox. '/no-such-link';
[ $held ] = $locked( [ $posix ], $two );
[ $other ] = $locked( [ '1: POSIX  ADVISORY  WRITE 4242 '. $device. ' 0 EOF' ], $two );
\Nino\Backup::$selfLink = null;
check( 'where the system names no process, getmypid() is the pid', $held === [ 'assets/style.css' ] && $other === [] );

// The files beside a database go with it, whichever of them is the one that is locked
$base		= \Nino\Filesystem::path( $appData, '/data/ledger' );
$beside = [];

foreach( [ '', '-wal', '-shm', '-journal' ] as $suffix ) {
	file_put_contents( $base. '/db.sqlite'. $suffix, 'a database'. $suffix );
	$beside[$base. '/db.sqlite'. $suffix] = 'data/ledger/db.sqlite'. $suffix;
}

// Files whose names begin as the database's does, and that are no companion of it: they are packed
$neighbours = [];

foreach( [ 'db.sqlite2', 'db.sqlite2-wal', 'db.sqlite-wal-old', 'db.sqlite.bak' ] as $neighbour ) {
	file_put_contents( $base. '/'. $neighbour, 'a neighbour' );
	$neighbours[$base. '/'. $neighbour] = 'data/ledger/'. $neighbour;
}

$listed = $two + $beside + $neighbours;
$all		= [ 'data/ledger/db.sqlite', 'data/ledger/db.sqlite-journal', 'data/ledger/db.sqlite-shm', 'data/ledger/db.sqlite-wal' ];
$packed = array_merge( [ 'assets/style.css', 'nino.php', 'text/en_US.php' ], array_values( $neighbours ) );
sort( $packed );

foreach( [ '' => 'the main file', '-wal' => 'the log of writes', '-shm' => 'the shared memory', '-journal' => 'the journal' ] as $suffix => $what ) {

	$stat		= stat( $base. '/db.sqlite'. $suffix );
	$line		= '1: POSIX  ADVISORY  WRITE '. $pid. ' '. sprintf( '%02x:%02x:%d', ( ( $stat['dev'] >> 8 ) & 0xfff ) | ( ( $stat['dev'] >> 32 ) & ~0xfff ), ( $stat['dev'] & 0xff ) | ( ( $stat['dev'] >> 12 ) & ~0xff ), $stat['ino'] ). ' 0 EOF';
	[ $held, $names ] = $locked( [ $line ], $listed );
	sort( $held );
	check( 'a lock on '. $what. ' of a database holds the main file and every file beside it, and no other file: the files named as it begins are packed', $held === $all && $names === $packed );
}

// A lock of the kind that is no lock of this process, as the system really lists it: a file that this process holds a flock() on is read and packed
if( is_readable( '/proc/locks' ) === true ) {

	$handle = fopen( $style, 'rb' );
	flock( $handle, LOCK_EX );
	$real		= array_values( array_filter( explode( "\n", (string) file_get_contents( '/proc/locks' ) ), static fn( string $line ): bool => str_contains( $line, ' FLOCK ' ) && str_contains( $line, ' '. getmypid(). ' ' ) ) );
	$held		= [];
	\Nino\Backup::pack( $appData, $two, $sandbox. '/flock.tar', [], $held );
	flock( $handle, LOCK_UN );
	fclose( $handle );
	check( 'a file that this process holds a flock() on - what the kernel does itself - is packed', $real !== [] && $held === [] );
}

\Nino\Backup::$locksFile = null;

// --- The one notice of a restore ----------------------------------------------------------------------------------------------------------------------------

echo "A restore says the strongest of the notices that apply\n";

/**
 *	A restore of a forged archive while this request holds a database in a transaction, or not, with the notices that apply set by the case: the answer and the line of the
 *	activity log.
 *
 *	@param		array			$on						'held' (the safety snapshot lacks a database this request holds), 'other' (the archive comes from another version of Nino), 'raw' (the hook of
 *														the feature throws, so its files are packed as they lie), 'companions' (files beside a database stand, which the archive of another project may not take away)
 *	@param		string		$door					'panel', 'recovery', or 'typed' for the recovery page with the key typed in
 *
 *	@return 	array											[ status, body, the lines of the activity log of the restore ]
 */
function srcNoticeCase( array $on, string $door ): array {

	$appData = srcProject( in_array( 'raw', $on, true ) === true ? 'throw' : 'nothing' );
	$db			 = \Nino\Filesystem::path( $appData, '/data/ledger.sqlite' );
	$id			 = date( 'Y-m-d' ). '-000001';

	if( in_array( 'held', $on, true ) === true ) {
		unlink( $db );
		$GLOBALS['srcOwn'] = srcHold( $db, 'delete', 'write' );
	}

	if( in_array( 'companions', $on, true ) === true )
		file_put_contents( \Nino\Filesystem::path( $appData, '/data/ledger/db.sqlite-wal' ), 'live log of writes' );

	// The archive: a database of the feature, and the stamp
	$base		 = ninoSandboxDir( $appData ). '/forged';
	$tar		 = new PharData( $base. '.tar' );
	$tar->addFromString( 'data/ledger/db.sqlite', 'an archive of a database' );
	$tar->addFromString( 'nino.php', '<?php return '. var_export( [ 'nino' => in_array( 'other', $on, true ) === true ? '0.9.1' : \Nino\VERSION, 'features' => [], 'copies' => in_array( 'companions', $on, true ) === true ? [ 'data/ledger/db.sqlite' ] : [] ], true ). ';' );
	$tar->compress( Phar::GZ );
	unset( $tar );
	file_put_contents( \Nino\Modules\Backups::dir( $appData ). '/'. $id. '.php', ninoSealed( (string) file_get_contents( $base. '.tar.gz' ), (string) base64_decode( (string) $appData['/nino/backup/key'], true ) ) );

	ninoWarnings();

	if( $door === 'panel' )
		[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $id ] );
	elseif( $door === 'recovery' )
		[ $status, $body ] = srcDoor( $appData, 'recovery', $id );
	else {
		\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
		[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $id, 'key' => (string) $appData['/nino/backup/key'] ], 'recovery/restore' );
	}

	unset( $GLOBALS['srcOwn'] );

	return [ $status, $body, array_values( array_filter( \Nino\Modules\Logs\Admin::recentLines( $appData, 40 ), static fn( string $line ): bool => str_contains( $line, '(backups_' ) ) ) ];
}

if( is_readable( '/proc/locks' ) === false )
	echo "  not run  - the notices of a restore that lacks a held file: this system does not list the locks of its processes\n";
else {

	// [ the notices that apply, the doors, the code that is said ]
	$strongest = [
		'the files beside a database, and a snapshot that lacks a held file'					=> [ [ 'companions', 'held' ], [ 'typed' ], 'backups_companions_left' ],
		'a snapshot that lacks a held file, and the version of another Nino'					=> [ [ 'held', 'other' ], [ 'panel', 'recovery' ], 'backups_snapshot_held' ],
		'the version of another Nino, and a snapshot that holds files as they were'		=> [ [ 'other', 'raw' ], [ 'panel', 'recovery' ], 'backups_restored_other_version' ],
		'a snapshot that holds files as they were'																		=> [ [ 'raw' ], [ 'panel', 'recovery' ], 'backups_snapshot_raw' ],
		'the files beside a database, and the version of another Nino'								=> [ [ 'companions', 'other' ], [ 'typed' ], 'backups_companions_left' ],
	];

	foreach( $strongest as $what => [ $on, $doors, $code ] )
		foreach( $doors as $door ) {
			[ $status, $body, $lines ] = srcNoticeCase( $on, $door );
			check( $door. ', '. $what. ': the restore is made and the answer carries '. $code. ', and so does the line of the activity log of the panel', $status === 200 && ( $body['notice']['code'] ?? '' ) === $code && ( $door !== 'panel' || ( count( $lines ) === 1 && str_contains( $lines[0], '('. $code. ')' ) === true ) ) );
		}

	[ $status, $body, $lines ] = srcNoticeCase( [ 'held' ], 'panel' );
	check( 'the activity log says of a snapshot that lacks a held file what it lacks, in one line', $status === 200 && count( $lines ) === 1 && str_contains( $lines[0], 'safety snapshot lacks the state of data/ledger.sqlite' ) === true );
}

// --- Files that are not the database's own, a pipe that is not opened, a file that is written to while it is read -------------------------------------

echo "A named pipe is never opened\n";

$appData	= srcProject();
$sandbox	= ninoSandboxDir( $appData );
$style		= \Nino\Filesystem::path( $appData, '/assets/style.css' );
$mark			= $sandbox. '/writer-got-in';
$writer		= null;

// A named pipe that a writer waits on, put there before the lstat: a writer is let through by any open for reading, so it is through if the pack opened the pipe
\Nino\Backup::$packing = static function( string $phase, int $try, string $name = '' ) use ( $style, $mark, &$writer ): void {

	if( $phase === 'looked' && $name === 'assets/style.css' ) {
		unlink( $style );
		posix_mkfifo( $style, 0644 );
		$writer = proc_open( [ 'timeout', '5', 'sh', '-c', 'exec 3>"$1"; touch "$2"; sleep 1', 'sh', $style, $mark ], [], $pipes );
		usleep( 100000 );
	}
};

try {
	[ $status, $body ] = srcDoor( $appData, 'now', date( 'Y-m-d' ) );
}
finally {
	\Nino\Backup::$packing = null;
}

usleep( 300000 );
clearstatcache();
check( 'a named pipe that a writer waits on is never opened by the pack: the run ends "changed", and the writer is not let through', $status === 500 && ( $body['code'] ?? '' ) === 'backups_source' && is_file( $mark ) === false );

if( is_resource( $writer ) === true ) {
	proc_terminate( $writer );
	proc_close( $writer );
}

// --- Descriptors -------------------------------------------------------------------------------------------------------------------------------------

echo "The packing leaves no descriptor open\n";

if( is_dir( '/proc/self/fd' ) === false )
	echo "  not run  - the descriptors of the process: this system does not list them\n";
else {

	$appData	= srcProject();
	$sandbox	= ninoSandboxDir( $appData );
	$many			= [];

	mkdir( $sandbox. '/many' );

	for( $i = 0; $i < 300; $i++ ) {
		file_put_contents( $sandbox. '/many/f'. $i. '.txt', 'file '. $i );
		$many[$sandbox. '/many/f'. $i. '.txt'] = 'assets/f'. str_pad( (string) $i, 3, '0', STR_PAD_LEFT ). '.txt';
	}

	$open = static fn(): int => count( array_diff( (array) scandir( '/proc/self/fd' ), [ '.', '..' ] ) );
	$run	= static function( ?callable $hook ) use ( &$appData, $many, $sandbox, $open ): array {

		static $count = 0;

		$failure = null;
		$before	 = $open();

		\Nino\Backup::$packing = $hook;

		try {
			\Nino\Backup::pack( $appData, $many, $sandbox. '/many-'. ++$count. '.tar' );
		}
		catch( \RuntimeException $e ) {
			$failure = $e;
		}
		finally {
			\Nino\Backup::$packing = null;
		}

		return [ $open() - $before, $failure ];
	};

	[ $left, $failure ] = $run( null );
	check( 'a pack of three hundred files leaves no descriptor open', $failure === null && $left === 0 );

	[ $left, $failure ] = $run( static function( string $phase, int $try, string $name = '' ) use ( $sandbox ): void {
		if( $phase === 'looked' && $name === 'assets/f150.txt' ) {
			unlink( $sandbox. '/many/f150.txt' );
			mkdir( $sandbox. '/many/f150.txt' );
		}
	} );
	check( '...a pack that ends halfway in a file that is no file any more leaves none open either', $failure !== null && \Nino\Backup::sourceName( $failure ) === 'assets/f150.txt' && $left === 0 );
	rmdir( $sandbox. '/many/f150.txt' );
	file_put_contents( $sandbox. '/many/f150.txt', 'file 150' );

	[ $left, $failure ] = $run( static function( string $phase, int $try, string $name = '' ) use ( $sandbox ): void {
		if( $phase === 'opened' && $name === 'assets/f150.txt' )
			file_put_contents( $sandbox. '/many/f150.txt', 'file 150 and more' );
	} );
	check( '...so does one that ends in a file that changed while it was read', $failure !== null && \Nino\Backup::sourceName( $failure ) === 'assets/f150.txt' && $left === 0 );
	file_put_contents( $sandbox. '/many/f150.txt', 'file 150' );

	[ $left, $failure ] = $run( static function( string $phase, int $try, string $name = '' ): void {
		if( $phase === 'opened' && $name === 'assets/f150.txt' )
			throw new \PharException( 'phar error: could not write the archive' );
	} );
	check( '...and one that ends in a write that failed while a file is open', $failure !== null && $failure->getCode() === \Nino\Backup::WRITE_FAILED && $left === 0 );
}

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
