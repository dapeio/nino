<?php
declare(strict_types=1);

/**
 *	Nino										A compact filesystembased php framework
 *	backup-restore-race-smoke.php	A restore puts the files back under the locks of the kernel's writers, beside the file and by rename.
 *													Real processes: writers that save through Filesystem::mutate() and Text::saveBatch() while the real
 *													Backups\Admin::restore() runs, a reader that includes the files, a process that holds a lock for longer
 *													than the restore waits, loops that take the locks of Pages and of the Roles in their own order, a file
 *													that cannot be replaced and a picture of 20 MB put back in 32 MB of memory.
 *
 *	Usage: php tests/backup-restore-race-smoke.php
 *	A probe may set RACE_ROUNDS (the rounds of the first part) and RACE_REPORT (a file one json line of each round is appended to)
 */

// Before the kernel loads: the feature of the fixture owns a database that no hook hands over
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/backup-restore-race' );

require __DIR__. '/harness.php';

const RACE_WRITERS = [ 'text', 'elements', 'template', 'data', 'batch' ];

/**
 *	The $appData of a process that works on the project in a sandbox, as a request of it has it
 *
 *	@param		string		$sandbox
 *
 *	@return 	array
 */
function raceBoot( string $sandbox, bool $roles = false ): array {

	$appData = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $appData );

	foreach( [ 'path' => $sandbox, 'configpath' => $sandbox. '/private', 'contentpath' => $sandbox. '/private', 'publicpath' => $sandbox. '/public' ] as $name => $path )
		$appData['./nino/filesystem/'. $name] = $path;

	$appData['./nino/locales/current']	= 'de_DE';
	$appData['/nino/locales/native']		= 'de_DE';
	$appData['/nino/locales/available']	= [ 'de_DE', 'en_US', 'fr_FR' ];
	$appData['/nino/dir']								= '';
	$appData['/nino/modules']						= [ '\\Nino\\Modules\\Navigation' ];
	$appData['/nino/html/navs']					= [ 'main', 'footer' ];
	$appData['./nino/test/sandbox']			= $sandbox;

	if( $roles === true )
		$appData['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $appData );

	return $appData;
}

/**
 *	The state of the files the writers save, in one epoch: every file says which it is in, and carries what a restore must bring back or take away
 *
 *	@param		array 		&$appData
 *	@param		string		$epoch				'A' (the archive) or 'B' (the project, as it is when the restore starts)
 *	@param		int				$length				The length of the values: the files of the archive are the longer ones
 *
 *	@return 	void
 */
function raceFill( array &$appData, string $epoch, int $length ): void {

	foreach( [ 'de_DE', 'en_US', 'global' ] as $file ) {

		$content = [ '[[_epoch]]' => $epoch ];

		for( $i = 0; $i < 40; $i++ )
			$content['[[k'. $i. ']]'] = $epoch. str_repeat( 'v', $length );

		if( $file === 'de_DE' )
			$content['[[/template/race/probe/save]]'] = $epoch;

		\Nino\Filesystem::putFileContent( $appData, '/text/'. $file. '.php', $content );
	}

	// The type file is the large one: a reader that is in the middle of it when it is put back in place is likely to see it
	$type = [ 'title' => 'News', 'model' => [ 'title' => [ 'type' => 'string', 'locale' => true ] ], '_epoch' => $epoch, 'de_DE' => [] ];

	for( $i = 0; $i < 300; $i++ )
		$type['de_DE']['item'. $i] = [ 'title' => $epoch. str_repeat( 'e', $length * 5 ) ];

	\Nino\Filesystem::putFileContent( $appData, '/elements/news.php', $type );
	\Nino\Filesystem::putFileContent( $appData, '/templates/part-race.tpl', 'epoch='. $epoch. "\n". str_repeat( $epoch. str_repeat( 't', $length ). "\n", 20 ) );
	\Nino\Filesystem::putFileContent( $appData, '/data/forms.race.php', [ 'epoch' => $epoch ] );
}

/**
 *	A project with a backup key, the files of 'A' backed up and the files of 'B' in their place
 *
 *	@param		string		$name
 *
 *	@return 	array											[ $appData, the id of the archive ]
 */
function raceProject( string $name ): array {

	$appData = ninoSandbox( $name );
	$sandbox = ninoSandboxDir( $appData );
	mkdir( $sandbox. '/private', 0755, true );
	mkdir( $sandbox. '/public', 0755, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [
		'/nino/error/display'			=> true,
		'/nino/locales/native'		=> 'de_DE',
		'/nino/locales/available'	=> [ 'de_DE', 'en_US', 'fr_FR' ],
	] );

	$appData['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $appData );
	ninoUser( $appData, 'dev@example.com' );
	\Nino\Modules\Backups::maybeRun( $appData );

	raceFill( $appData, 'A', 200 );

	$id = \Nino\Modules\Backups::now( $appData );

	$live = raceBoot( $sandbox );
	raceFill( $live, 'B', 150 );

	return [ $appData, (string) $id ];
}

/**
 *	What a project is, as a hash of every file but what a request writes in passing: the activity log and the locks (the archives are in it)
 *
 *	@param		string		$sandbox
 *
 *	@return 	string
 */
function raceTree( string $sandbox ): string {

	$lines = [];

	foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sandbox, FilesystemIterator::SKIP_DOTS ) ) as $file ) {

		$relative = substr( $file->getPathname(), strlen( $sandbox ) + 1 );

		if( preg_match( '#^private/(\.logs|data/\.locks)/#', $relative ) !== 1 )
			$lines[] = $relative. ' '. sha1_file( $file->getPathname() );
	}

	sort( $lines );

	return sha1( implode( "\n", $lines ) );
}

// ---------------------------------------------------------------------------------------------------------------------------------------
// The processes of the suite: this file, started with a role as its first argument
// ---------------------------------------------------------------------------------------------------------------------------------------

$role = $argv[1] ?? '';

if( in_array( $role, [ 'writer', 'reader', 'holder', 'trylock', 'loop', 'restores', 'big', 'rogue', 'db', 'wedge' ], true ) === true ) {

	[ , , $sandbox ] = $argv;

	// A warning is an error in a writer and a reader: one that meets a half file is to say so
	if( in_array( $role, [ 'writer', 'reader' ], true ) === true )
		set_error_handler( static function( int $level, string $message ): never { throw new ErrorException( $message, 0, $level ); } );

	if( $role === 'writer' ) {

		[ , , , $kind, $id, $stop, $restored, $log ] = $argv;

		$logged	= fopen( $log, 'ab' );
		$files	= [ 'de_DE', 'en_US', 'global' ];
		$seq		= 0;
		$after	= 0;
		$told		= false;
		$put		= static function( array $line ) use ( $logged ): void { fwrite( $logged, json_encode( $line ). "\n" ); };

		while( file_exists( $stop ) === false ) {

			$seq++;
			$appData	= raceBoot( $sandbox );
			$length		= random_int( 0, 1500 );
			$value		= $id. ':'. $seq. ':'. str_repeat( 'x', $length );
			$file			= $kind === 'text' ? 'text/'. $files[ random_int( 0, 2 ) ] : ( [ 'elements' => 'elements/news', 'template' => 'templates/part-race', 'data' => 'data/forms.race', 'batch' => 'text/de_DE' ][$kind] );
			$start		= microtime( true );
			$was			= is_file( $restored );

			try {

				if( $kind === 'batch' ) {

					$answer = \Nino\Text::saveBatch( $appData, [ [ 'key' => '/template/race/probe/save', 'locale' => 'de_DE', 'value' => $value ] ], false );
					$ok			= ( $answer['/template/race/probe/save']['ok'] ?? false ) === true;
				}
				else if( $kind === 'template' ) {

					$ok = \Nino\Filesystem::mutate( $appData, '/templates/part-race.tpl', static function( mixed $content ) use ( $id, $value ): ?string {

						if( is_string( $content ) === false || str_starts_with( $content, 'epoch=' ) === false )
							return null;

						$lines = array_filter( explode( "\n", $content ), static fn( string $line ): bool => str_starts_with( $line, 'w'. $id. ':' ) === false );
						$lines[] = 'w'. $value;

						return implode( "\n", $lines );
					}, '' );
				}
				else {

					$path = '/'. $file. '.php';
					$key	= $kind === 'elements' || $kind === 'data' ? 'w'. $id : '[[w'. $id. ']]';
					$ok		= \Nino\Filesystem::mutate( $appData, $path, static function( mixed $content ) use ( $key, $value ): ?array {

						if( is_array( $content ) === false )
							return null;

						$content[$key] = $value;

						return $content;
					} );
				}

				$put( [ 'k' => $ok === true ? 'S' : 'F', 'w' => $kind, 'id' => $id, 'f' => $file, 's' => $seq, 'l' => $length, 't0' => $start, 't' => microtime( true ) ] );
			}
			catch( Throwable $e ) {
				$put( [ 'k' => 'E', 'w' => $kind, 'id' => $id, 'f' => $file, 's' => $seq, 't' => microtime( true ), 'm' => get_class( $e ). ': '. substr( $e->getMessage(), 0, 120 ) ] );
			}

			// Said to the suite: three saves are done (the restore may begin), and three saves after the restore returned
			if( $seq === 3 && $told === false ) {
				echo "ready\n";
				fflush( STDOUT );
				$told = true;
			}

			if( is_file( $restored ) === true && $was === true && ++$after === 3 ) {
				echo "done\n";
				fflush( STDOUT );
			}

			usleep( random_int( 1000, 4000 ) );
		}

		exit( 0 );
	}

	if( $role === 'reader' ) {

		[ , , , $stop, $log ] = $argv;

		$logged	= fopen( $log, 'ab' );
		$reads	= 0;
		echo "ready\n";
		fflush( STDOUT );

		while( file_exists( $stop ) === false ) {

			$appData	= raceBoot( $sandbox );
			$file		= [ '/text/de_DE.php', '/text/en_US.php', '/text/global.php', '/elements/news.php', '/data/forms.race.php' ][ $reads++ % 5 ];

			try {

				$content = \Nino\Filesystem::getFileContent( $appData, $file, [] );

				if( is_array( $content ) === false || isset( $content['[[_epoch]]'] ) === false && isset( $content['_epoch'] ) === false && isset( $content['epoch'] ) === false )
					fwrite( $logged, json_encode( [ 'f' => $file, 'what' => is_array( $content ) ? 'array without its epoch' : get_debug_type( $content ) ] ). "\n" );

				$template = (string) @file_get_contents( $sandbox. '/private/templates/part-race.tpl' );

				if( str_starts_with( $template, 'epoch=' ) === false )
					fwrite( $logged, json_encode( [ 'f' => '/templates/part-race.tpl', 'what' => 'a template of '. strlen( $template ). ' bytes' ] ). "\n" );
			}
			catch( Throwable $e ) {
				fwrite( $logged, json_encode( [ 'f' => $file, 'what' => get_class( $e ) ] ). "\n" );
			}

			usleep( 300 );
		}

		fwrite( $logged, json_encode( [ 'reads' => $reads ] ). "\n" );
		exit( 0 );
	}

	if( $role === 'holder' ) {

		// php suite holder <sandbox> <path> <release file> <seconds, 0 for until the file is there>
		[ , , , $path, $release, $seconds ] = $argv;

		$appData	= raceBoot( $sandbox );
		$held			= \Nino\Filesystem::lockFile( $appData, $path, false );
		$until		= microtime( true ) + (float) $seconds;

		echo $held === true ? "held\n" : "not held\n";
		fflush( STDOUT );

		while( $held === true && is_file( $release ) === false && ( (float) $seconds === 0.0 || microtime( true ) < $until ) )
			usleep( 2000 );

		\Nino\Filesystem::unlockFile( $appData, $path );
		exit( 0 );
	}

	if( $role === 'trylock' ) {

		// Which of the locks are free: php suite trylock <sandbox> <path>...
		$appData	= raceBoot( $sandbox );
		$free			= [];

		foreach( array_slice( $argv, 3 ) as $path ) {
			$free[$path] = \Nino\Filesystem::lockFile( $appData, $path, false );
			\Nino\Filesystem::unlockFile( $appData, $path );
		}

		echo json_encode( $free );
		exit( 0 );
	}

	if( $role === 'loop' ) {

		// The writers whose locks the restore meets in another order than its own: php suite loop <sandbox> <kind> <stop file> <log>
		[ , , , $kind, $stop, $log ] = $argv;

		$appData	= raceBoot( $sandbox, true );
		$mail			= 'loop-'. bin2hex( random_bytes( 3 ) ). '@example.com';
		$seen			= [];
		$rows			= static fn( string $name ): array => [ 'de_DE' => [ 'name' => $name, 'title' => $name ], 'en_US' => [ 'name' => $name, 'title' => $name ], 'fr_FR' => [ 'name' => $name, 'title' => $name ] ];
		$i				= 0;

		echo "ready\n";
		fflush( STDOUT );

		while( file_exists( $stop ) === false ) {

			$i++;

			$appData = raceBoot( $sandbox, $kind === 'roles' );

			try {

				if( $kind === 'pages-a' ) {
					$made		= \Nino\Pages::put( $appData, [ 'uri' => '/site-l'. ( $i % 6 ), 'httpUris' => [ '' => '/l'. ( $i % 6 ) ], 'template' => 'page-l'. ( $i % 6 ), 'text' => $rows( 'L'. $i ), 'navs' => [ 'main' ], 'source' => '<p>[[/template/page-l'. ( $i % 6 ). '/a/b]]</p>' ] );
					$gone		= \Nino\Pages::remove( $appData, 'page-l'. ( $i % 6 ) );
					$seen[]	= 'put '. ( $made['error']['code'] ?? 'done' ). ' remove '. ( $gone['error']['code'] ?? 'done' );
				}
				else if( $kind === 'pages-b' ) {
					$made		= \Nino\Pages::put( $appData, [ 'uri' => '/site-b'. ( $i % 6 ), 'httpUris' => [ '' => '/b'. ( $i % 6 ) ], 'template' => 'page-l'. ( $i % 3 ), 'text' => $rows( 'B'. $i ), 'navs' => [ 'main' ] ] );
					$seen[]	= 'put '. ( $made['error']['code'] ?? 'done' );
				}
				else {
					ninoUser( $appData, $mail );
					[ $status ] = ninoCall( $appData, [ \Nino\Modules\Users\Roles::class, 'apiSave' ], [ 'id' => 'loop'. ( $i % 4 ), 'label' => 'Loop '. $i, 'perms' => [] ] );
					$seen[]	= 'save '. $status;
				}
			}
			catch( Throwable $e ) {
				$seen[] = 'threw '. get_class( $e ). ': '. substr( $e->getMessage(), 0, 100 );
			}

			usleep( random_int( 500, 2500 ) );
		}

		file_put_contents( $log, json_encode( array_count_values( $seen ) ) );
		exit( 0 );
	}

	if( $role === 'restores' ) {

		// php suite restores <sandbox> <archive> <times> <lock wait>: the real restore, one after the other, and what each answered
		[ , , , $archive, $times, $wait ] = $argv;

		\Nino\Backup::$lockWait = (float) $wait;

		$appData	= raceBoot( $sandbox );
		$answers	= [];

		// A workbench request takes the lock of the daily backup for a moment, and a restore that does not get it at once is turned away (backups_busy): it asks once more
		$retries	= 0;
		$began		= microtime( true );

		for( $i = 0; $i < (int) $times; $i++ ) {

			do {
				$answer		= \Nino\Modules\Backups\Admin::restore( $appData, $archive );
				$turned		= is_array( $answer ) === true && ( $answer[2] ?? '' ) === 'backups_busy';
				$retries += $turned === true ? 1 : 0;
			} while( $turned === true && $retries < 400 );

			$answers[] = $answer === true ? 'true' : $answer[0]. ' '. ( $answer[2] ?? '' );
		}

		echo json_encode( array_count_values( $answers ) + [ 'retries' => $retries, 'seconds' => round( microtime( true ) - $began, 1 ) ] );
		exit( 0 );
	}

	if( $role === 'rogue' ) {

		// A writer that takes no lock: it replaces a file of the project by a file of the same size, over and over, by a rename - php suite rogue <sandbox> <stop file>
		[ , , , $stop ] = $argv;

		$garbage	= str_repeat( 'Z', 8 * 1048576 );
		$target		= $sandbox. '/private/assets/rogue.bin';

		echo "ready\n";
		fflush( STDOUT );

		while( file_exists( $stop ) === false ) {
			file_put_contents( $sandbox. '/rogue.tmp', $garbage );
			rename( $sandbox. '/rogue.tmp', $target );
		}

		exit( 0 );
	}

	if( $role === 'db' ) {

		// A process that has the database of a feature open across the restore, and commits to it once the restore is done - php suite db <sandbox> <go file>
		[ , , , $go ] = $argv;

		$file	= $sandbox. '/private/data/plain.sqlite';
		$pdo	= new PDO( 'sqlite:'. $file );
		$pdo->query( 'SELECT count(*) FROM t' )->fetchColumn();

		echo "ready\n";
		fflush( STDOUT );

		while( is_file( $go ) === false )
			usleep( 2000 );

		$pdo->exec( "INSERT INTO t ( v ) VALUES ( 'H' )" );
		echo json_encode( [ 'done' => true ] ), "\n";
		exit( 0 );
	}

	if( $role === 'wedge' ) {

		// A writer that holds the lock of one text and waits for another that the restore holds - php suite wedge <sandbox>
		$appData	= raceBoot( $sandbox );

		\Nino\Filesystem::lockFile( $appData, '/text/global.php' );
		echo "held\n";
		fflush( STDOUT );

		// The other is the restore's as soon as it cannot be had
		$until = hrtime( true ) + 20 * 1000000000;

		while( hrtime( true ) < $until && \Nino\Filesystem::lockFile( $appData, '/text/de_DE.php', false ) === true ) {
			\Nino\Filesystem::unlockFile( $appData, '/text/de_DE.php' );
			usleep( 1000 );
		}

		$began	= hrtime( true );
		$got		= \Nino\Filesystem::lockFile( $appData, '/text/de_DE.php' );

		echo json_encode( [ 'got' => $got, 'seconds' => round( ( hrtime( true ) - $began ) / 1e9, 1 ) ] ), "\n";
		\Nino\Filesystem::unlockFile( $appData, '/text/de_DE.php' );
		\Nino\Filesystem::unlockFile( $appData, '/text/global.php' );
		exit( 0 );
	}

	if( $role === 'big' ) {

		// php -d memory_limit=32M suite big <sandbox> <archive>
		[ , , , $archive ] = $argv;

		$appData	= raceBoot( $sandbox );
		$answer		= \Nino\Modules\Backups\Admin::restore( $appData, $archive );

		echo json_encode( [ $answer === true ? true : $answer, memory_get_peak_usage( true ) ] );
		exit( 0 );
	}
}

// ---------------------------------------------------------------------------------------------------------------------------------------
// The suite
// ---------------------------------------------------------------------------------------------------------------------------------------

/**
 *	Start a process of this file, with the memory limit php has or one the caller names
 *
 *	@param		array 		$arguments		After the file: the role and what it needs
 *	@param		array|null	&$pipes				Its standard output is the first
 *	@param		string		$memory				The memory limit of the process, '' for the one php has
 *
 *	@return 	resource|false
 */
function raceStart( array $arguments, ?array &$pipes, string $memory = '' ) {

	$command = [ 'timeout', '120', PHP_BINARY ];

	if( $memory !== '' )
		array_push( $command, '-d', 'memory_limit='. $memory );

	return proc_open( array_merge( $command, [ __FILE__ ], $arguments ), [ 0 => [ 'file', '/dev/null', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ], $pipes );
}

/**
 *	Read the lines of what a log of the writers holds
 *
 *	@param		array 		$files
 *
 *	@return 	array											The decoded lines
 */
function raceLines( array $files ): array {

	$lines = [];

	foreach( $files as $file )
		foreach( file( $file ) ?: [] as $line )
			$lines[] = json_decode( $line, true );

	return $lines;
}

/**
 *	One round: the writers and the reader run, the real restore runs, the writers go on for three more saves, and the state is judged
 *
 *	@param		array 		&$project			[ $appData, archive id ] of raceProject()
 *	@param		array 		$kinds				The writers that run
 *
 *	@return 	array											What the round found
 */
function raceRound( array &$project, array $kinds ): array {

	[ $appData, $id ] = $project;

	$sandbox	= ninoSandboxDir( $appData );
	$stop			= $sandbox. '/race-stop';
	$restored	= $sandbox. '/race-restored';
	$procs		= [];
	$pipes		= [];
	$logs			= [];

	@unlink( $stop );
	@unlink( $restored );

	foreach( $kinds as $n => $kind ) {

		$logs['w'. $n]	= $sandbox. '/race-w'. $n. '.log';
		$procs['w'. $n]	= raceStart( [ 'writer', $sandbox, $kind, (string) ( $n + 1 ), $stop, $restored, $logs['w'. $n] ], $pipes['w'. $n] );
	}

	$logs['r']	= $sandbox. '/race-r.log';
	$procs['r']	= raceStart( [ 'reader', $sandbox, $stop, $logs['r'] ], $pipes['r'] );

	foreach( $pipes as $pipe )
		fgets( $pipe[1] );

	$t1 			= 0.0;
	$notice		= null;
	$callback = static function( array &$appData, array &$args ) use ( &$t1 ): void { $t1 = microtime( true ); };
	$live			= raceBoot( $sandbox );

	\Nino\Callbacks::registerCallback( $live, '/nino/admin/restore', $callback );

	$result = \Nino\Modules\Backups\Admin::restore( $live, $id, $notice );
	$t2 		= microtime( true );

	touch( $restored );

	// Three saves of each writer after the restore returned
	foreach( $kinds as $n => $kind )
		while( ( $line = fgets( $pipes['w'. $n][1] ) ) !== false && trim( $line ) !== 'done' );

	touch( $stop );

	foreach( $procs as $n => $proc ) {
		stream_get_contents( $pipes[$n][1] );
		fclose( $pipes[$n][1] );
		proc_close( $proc );
	}

	clearstatcache();

	$found	= [ 'answer' => $result === true ? 'ok' : $result[0]. ' '. ( $result[2] ?? '' ), 'stale' => [], 'lost' => [], 'foreign' => [], 'errors' => [], 'odd' => [], 'reads' => 0, 'saves' => 0 ];
	$state	= [];

	foreach( [ 'text/de_DE', 'text/en_US', 'text/global', 'elements/news', 'data/forms.race' ] as $file ) {

		try {
			$state[$file] = ( static function( string $path ): mixed { return include $path; } )( $sandbox. '/private/'. $file. '.php' );
		}
		catch( Throwable $e ) {
			$state[$file] = 'broken: '. $e->getMessage();
		}
	}

	$template = (string) file_get_contents( $sandbox. '/private/templates/part-race.tpl' );
	$epochs		= [
		'text/de_DE' => $state['text/de_DE']['[[_epoch]]'] ?? '-', 'text/en_US' => $state['text/en_US']['[[_epoch]]'] ?? '-', 'text/global' => $state['text/global']['[[_epoch]]'] ?? '-',
		'elements/news' => $state['elements/news']['_epoch'] ?? '-', 'data/forms.race' => $state['data/forms.race']['epoch'] ?? '-', 'templates/part-race' => substr( strtok( $template, "\n" ), 6 ),
	];

	// A file that is not what the archive holds is the state before the restore
	foreach( $epochs as $file => $epoch )
		if( $epoch !== 'A' )
			$found['stale'][] = $file;

	// What the writers did: the last save of a writer to a file decides what the file holds of it after the restore - the archive has none of their keys. A save
	// that was done before the restore took its locks is gone with the state it was made on; one that began after it is there; one that straddles it is either
	$saved = [];

	foreach( raceLines( array_filter( $logs, static fn( string $key ): bool => $key !== 'r', ARRAY_FILTER_USE_KEY ) ) as $line ) {

		if( $line['k'] === 'E' )
			$found['errors'][] = $line['w']. ': '. $line['m'];
		else if( $line['k'] === 'F' )
			$found['errors'][] = $line['w']. ' was refused: the file was not what a writer is given';
		else {
			$saved[ $line['id']. ' '. $line['f'] ][] = $line;
			$found['saves']++;
		}
	}

	foreach( $saved as $group => $lines ) {

		$last		= end( $lines );
		$value	= $last['id']. ':'. $last['s']. ':'. str_repeat( 'x', $last['l'] );
		$file		= $last['f'];
		$key		= in_array( $last['w'], [ 'elements', 'data' ], true ) === true ? 'w'. $last['id'] : '[[w'. $last['id']. ']]';

		if( $last['w'] === 'template' ) {

			$has = [];

			foreach( explode( "\n", $template ) as $one )
				if( str_starts_with( $one, 'w'. $last['id']. ':' ) === true )
					$has[] = substr( $one, 1 );
		}
		else if( $last['w'] === 'batch' )
			$has = isset( $state['text/de_DE']['[[/template/race/probe/save]]'] ) && $state['text/de_DE']['[[/template/race/probe/save]]'] !== 'A' ? [ $state['text/de_DE']['[[/template/race/probe/save]]'] ] : [];
		else
			$has = isset( $state[$file][$key] ) ? [ $state[$file][$key] ] : [];

		if( $last['t'] < $t1 && $has !== [] )
			$found['foreign'][] = $group. ' holds a save of before the restore';
		else if( $last['t0'] > $t1 && $has !== [ $value ] )
			$found['lost'][] = $group. ' lost the save '. $last['s']. ' that began after the restore took its locks';
		else if( $has !== [] && $has !== [ $value ] )
			$found['foreign'][] = $group. ' holds a value that is no save of its writer';
	}

	foreach( raceLines( [ $logs['r'] ] ) as $line )
		if( isset( $line['reads'] ) === true )
			$found['reads'] += $line['reads'];
		else
			$found['odd'][] = $line['f']. ': '. $line['what'];

	foreach( $kinds as $n => $kind )
		@unlink( $logs['w'. $n] );

	@unlink( $logs['r'] );

	return $found + [ 't1' => $t1, 't2' => $t2 ];
}

// ---- The rounds -----------------------------------------------------------------------------------------------------------------------

echo "A restore with writers that save while it runs\n";

$rounds		= (int) ( getenv( 'RACE_ROUNDS' ) ?: 20 );
$project	= raceProject( 'race' );
$sandbox	= ninoSandboxDir( $project[0] );

$totals		= [ 'stale' => 0, 'lost' => 0, 'foreign' => 0, 'errors' => 0, 'odd' => 0, 'answers' => [], 'saves' => 0, 'reads' => 0 ];
$first		= '';

for( $round = 0; $round < $rounds; $round++ ) {

	// The live project is the state before, and the same archive is restored every round
	$live = raceBoot( $sandbox );
	raceFill( $live, 'B', 150 );

	$found = raceRound( $project, RACE_WRITERS );

	foreach( [ 'stale', 'lost', 'foreign', 'errors', 'odd' ] as $what )
		$totals[$what] += count( $found[$what] );

	$totals['answers'][$found['answer']] = ( $totals['answers'][$found['answer']] ?? 0 ) + 1;
	$totals['saves'] += $found['saves'];
	$totals['reads'] += $found['reads'];

	if( $first === '' && ( $found['stale'] !== [] || $found['lost'] !== [] || $found['foreign'] !== [] || $found['errors'] !== [] || $found['odd'] !== [] || $found['answer'] !== 'ok' ) )
		$first = 'round '. $round. ': '. json_encode( [ $found['answer'], $found['stale'], $found['lost'], $found['foreign'], array_slice( $found['errors'], 0, 2 ), array_slice( $found['odd'], 0, 2 ) ] );

	if( getenv( 'RACE_REPORT' ) !== false )
		file_put_contents( (string) getenv( 'RACE_REPORT' ), json_encode( array_diff_key( $found, [ 't1' => 0, 't2' => 0 ] ) + [ 'round' => $round ] ). "\n", FILE_APPEND );
}

echo '  '. $rounds. ' rounds, '. $totals['saves']. ' saves, '. $totals['reads']. ' reads of the reader, answers '. json_encode( $totals['answers'] ). ( $first !== '' ? ', the first that went wrong: '. $first : '' ). "\n";

check( 'every round of the restore is answered 200', $totals['answers'] === [ 'ok' => $rounds ] );
check( 'no file of the project is in its state from before the restore - text, elements, template and data are the archive\'s, whatever a writer read before it', $totals['stale'] === 0 );
check( 'every save that began after the restore took its locks is in the file: a save that waited for the restore is written on top of the state that was put back', $totals['lost'] === 0 );
check( 'no file holds a save that was made on the state before the restore', $totals['foreign'] === 0 );
check( 'no reader met a file that is no array, a template that is cut or a parse error, and no writer was handed one', $totals['odd'] === 0 && $totals['errors'] === 0 && $totals['reads'] > 100 );

echo "\n";

// ---- A lock that is held ---------------------------------------------------------------------------------------------------------------

echo "A lock another process holds for longer than the restore waits\n";

[ $appData, $id ]	= $project;
$sandbox					= ninoSandboxDir( $appData );
$release					= $sandbox. '/race-release';

$before		= raceTree( $sandbox );
$snapshots = array_map( 'basename', glob( $sandbox. '/private/.backups/pre-restore-*.php' ) ?: [] );

@unlink( $release );
$holder = raceStart( [ 'holder', $sandbox, '/text/en_US.php', $release, '6' ], $holderPipes );
$said		= trim( (string) fgets( $holderPipes[1] ) );

\Nino\Backup::$lockWait = 1.0;
$live		= raceBoot( $sandbox );
$began	= microtime( true );
$answer	= \Nino\Modules\Backups\Admin::restore( $live, $id );
$waited = microtime( true ) - $began;
\Nino\Backup::$lockWait = 10.0;

check( 'a restore that cannot have the lock of a file within the time it waits is refused with 409 and the code backups_restore_busy, and says which file', $said === 'held' && is_array( $answer ) === true && $answer[0] === 409 && ( $answer[2] ?? '' ) === 'backups_restore_busy' && ( $answer[3] ?? [] ) === [ '/text/en_US.php' ] );
check( '...after it waited for the time it is given, and not much beyond it', $waited >= 0.9 && $waited < 3.0 );
check( '...with nothing changed under the project: not a file of it, the archives and snapshots included by name', raceTree( $sandbox ) === $before && array_map( 'basename', glob( $sandbox. '/private/.backups/pre-restore-*.php' ) ?: [] ) === $snapshots );

$freed	= raceStart( [ 'trylock', $sandbox, '/config.php', '/text/de_DE.php', '/text/global.php', '/elements/news.php', '/templates/part-race.tpl', '/data/forms.race.php', '/text/en_US.php' ], $freedPipes );
$free		= json_decode( (string) stream_get_contents( $freedPipes[1] ), true );
proc_close( $freed );

check( '...and every lock it had taken is let go: the files before the one that was held can be locked by another process, the one that is held is still held', is_array( $free ) === true && array_filter( array_diff_key( $free, [ '/text/en_US.php' => 0 ] ) ) === array_diff_key( $free, [ '/text/en_US.php' => 0 ] ) && ( $free['/text/en_US.php'] ?? true ) === false );

touch( $release );
stream_get_contents( $holderPipes[1] );
proc_close( $holder );

$answer = \Nino\Modules\Backups\Admin::restore( raceBoot( $sandbox ), $id );

check( 'a second restore, once the holder let go, succeeds', $answer === true );

// Held for less than the restore waits: it waits for it and goes through
@unlink( $release );
$holder = raceStart( [ 'holder', $sandbox, '/text/en_US.php', $release, '1.2' ], $holderPipes );
fgets( $holderPipes[1] );

$restoring	= raceStart( [ 'restores', $sandbox, $id, '1', '10' ], $restoringPipes );
$answers		= json_decode( (string) stream_get_contents( $restoringPipes[1] ), true );
proc_close( $restoring );
stream_get_contents( $holderPipes[1] );
proc_close( $holder );

check( 'a lock that is held for less than the restore waits is waited for, and the restore goes through', ( $answers['true'] ?? 0 ) === 1 && ( $answers['retries'] ?? 1 ) === 0 );

echo "\n";

// ---- The writers that take their locks in their own order ---------------------------------------------------------------------------------

echo "A restore against Pages::put(), Pages::remove() and the saves of the Roles, in processes of their own\n";

$stop			= $sandbox. '/race-loop-stop';
@unlink( $stop );
$loops		= [];
$loopLogs	= [];

foreach( [ 'pages-a', 'pages-b', 'roles' ] as $kind ) {
	$loopLogs[$kind]	= $sandbox. '/race-loop-'. $kind. '.log';
	$loops[$kind]			= raceStart( [ 'loop', $sandbox, $kind, $stop, $loopLogs[$kind] ], $loopPipes[$kind] );
	fgets( $loopPipes[$kind][1] );
}

$restoring	= raceStart( [ 'restores', $sandbox, $id, '30', '10' ], $restoringPipes );
$answers		= json_decode( (string) stream_get_contents( $restoringPipes[1] ), true );
proc_close( $restoring );

touch( $stop );
$loopsEnded = true;
$seen = [];

foreach( $loops as $kind => $loop ) {
	stream_get_contents( $loopPipes[$kind][1] );
	fclose( $loopPipes[$kind][1] );
	$loopsEnded = proc_close( $loop ) === 0 && $loopsEnded;
	$seen[$kind] = json_decode( (string) @file_get_contents( $loopLogs[$kind] ), true );
}

$threw = [];

foreach( $seen as $kind => $counts )
	foreach( (array) $counts as $what => $count )
		if( str_contains( (string) $what, 'threw' ) === true )
			$threw[] = $kind. ': '. $what;

echo '  '. json_encode( $answers ). ' answered by the restores, the loops saw '. json_encode( $seen ). "\n";

check( '30 restores while pages are put and removed and roles are saved all end - nothing waits for another for ever', is_array( $answers ) === true && array_sum( array_diff_key( $answers, [ 'retries' => 0, 'seconds' => 0 ] ) ) === 30 && $loopsEnded === true );
check( '...each with 200 or with the refusal that says another request is writing', is_array( $answers ) === true && array_diff_key( $answers, [ 'true' => 0, '409 backups_restore_busy' => 0, 'retries' => 0, 'seconds' => 0 ] ) === [] && ( $answers['true'] ?? 0 ) >= 25 );
check( '...and no writer was thrown out of its call by it', $threw === [] && is_array( $seen['roles'] ?? null ) === true && is_array( $seen['pages-a'] ?? null ) === true );

echo "\n";

// ---- A file that cannot be replaced ---------------------------------------------------------------------------------------------------------

echo "A file that is a directory where the archive puts one\n";

$stray		= raceProject( 'race-partial' );
$strayDir	= ninoSandboxDir( $stray[0] );
$put			= raceBoot( $strayDir );

raceFill( $put, 'A', 200 );
\Nino\Filesystem::putFileContent( $put, '/assets/a.css', 'archived a' );
\Nino\Filesystem::putFileContent( $put, '/assets/b.css', 'archived b' );
$archived = \Nino\Modules\Backups::now( $stray[0] );

raceFill( $put, 'B', 150 );
\Nino\Filesystem::putFileContent( $put, '/assets/a.css', 'live a' );
unlink( $strayDir. '/private/assets/b.css' );
mkdir( $strayDir. '/private/assets/b.css' );
file_put_contents( $strayDir. '/private/assets/b.css/inner.txt', 'live inner' );

$answer = \Nino\Modules\Backups\Admin::restore( raceBoot( $strayDir ), (string) $archived );
$text		= include $strayDir. '/private/text/de_DE.php';

check( 'the file that cannot be put back is named in a restore that is done in part, with the code of that', is_array( $answer ) === true && $answer[0] === 500 && ( $answer[2] ?? '' ) === 'backups_restore_partial' && ( $answer[4]['cut'] ?? [] ) === [ 'assets/b.css' ] );
check( '...the others are put back: the other file, the texts, the template - and the one that was not is as it was, with no temporary file beside it', file_get_contents( $strayDir. '/private/assets/a.css' ) === 'archived a' && ( $text['[[_epoch]]'] ?? '' ) === 'A' && str_starts_with( (string) file_get_contents( $strayDir. '/private/templates/part-race.tpl' ), 'epoch=A' ) && file_get_contents( $strayDir. '/private/assets/b.css/inner.txt' ) === 'live inner' && ( glob( $strayDir. '/private/assets/*.tmp' ) ?: [] ) === [] );

\Nino\Filesystem::removeDir( $strayDir );

echo "\n";

// ---- A writer that takes no lock -----------------------------------------------------------------------------------------------------------

echo "A file that a writer without a lock replaced after it was put back\n";

$rogue		= raceProject( 'race-rogue' );
$rogueDir	= ninoSandboxDir( $rogue[0] );
$put			= raceBoot( $rogueDir );

raceFill( $put, 'A', 200 );
\Nino\Filesystem::putFileContent( $put, '/assets/rogue.bin', str_repeat( 'A', 8 * 1048576 ) );
$archived	= \Nino\Modules\Backups::now( $rogue[0] );
$stop			= $rogueDir. '/race-rogue-stop';

$rogueProc	= raceStart( [ 'rogue', $rogueDir, $stop ], $roguePipes );
fgets( $roguePipes[1] );

$answers = [];

for( $attempt = 0; $attempt < 5 && ( $answers[count( $answers ) - 1] ?? '' ) !== 'partial'; $attempt++ ) {

	$answer		= \Nino\Modules\Backups\Admin::restore( raceBoot( $rogueDir ), (string) $archived );
	$answers[] = $answer === true ? 'true' : ( ( $answer[2] ?? '' ) === 'backups_restore_partial' && ( $answer[4]['cut'] ?? [] ) === [ 'assets/rogue.bin' ] ? 'partial' : json_encode( $answer ) );
}

touch( $stop );
stream_get_contents( $roguePipes[1] );
proc_close( $rogueProc );

echo '  '. count( $answers ). ' restore(s): '. implode( ', ', $answers ). "\n";

check( 'a file that was replaced by a writer without a lock right after it was put back is not the archive\'s file, and the restore says so: done in part, with the file named', end( $answers ) === 'partial' );

\Nino\Filesystem::removeDir( $rogueDir );

echo "\n";

// ---- Files that are no php, held locks and the rest of the files ---------------------------------------------------------------------

echo "A database of a feature without a hook, which a process has open across the restore\n";

$base			= raceProject( 'race-db' );
$baseDir	= ninoSandboxDir( $base[0] );
$dbFile		= $baseDir. '/private/data/plain.sqlite';
$pdo			= new PDO( 'sqlite:'. $dbFile );
$pdo->exec( 'CREATE TABLE t ( v TEXT )' );
$pdo->exec( "INSERT INTO t ( v ) VALUES ( 'A' )" );
unset( $pdo );

$archived	= \Nino\Modules\Backups::now( $base[0] );
$pdo			= new PDO( 'sqlite:'. $dbFile );
$pdo->exec( "INSERT INTO t ( v ) VALUES ( 'B' )" );
unset( $pdo );

$go				= $baseDir. '/race-db-go';
$dbProc		= raceStart( [ 'db', $baseDir, $go ], $dbPipes );
fgets( $dbPipes[1] );

clearstatcache();
$inode		= fileinode( $dbFile );
$answer		= \Nino\Modules\Backups\Admin::restore( raceBoot( $baseDir ), (string) $archived );
clearstatcache();
$inodeNow	= fileinode( $dbFile );

touch( $go );
stream_get_contents( $dbPipes[1] );
proc_close( $dbProc );

$pdo			= new PDO( 'sqlite:'. $dbFile );
$rows			= $pdo->query( 'SELECT v FROM t ORDER BY rowid' )->fetchAll( PDO::FETCH_COLUMN );
unset( $pdo );

check( 'a file below data/ that is no php is written over in place, as it always was: the restore is answered 200 and the file is the one it was (same inode)', $answer === true && $inode === $inodeNow );
check( '...so that a process that has the database open commits into the file the others read: its row is there after the restore, with the archive\'s and not the state before', $rows === [ 'A', 'H' ] );

\Nino\Filesystem::removeDir( $baseDir );

echo "\n";

// ---- Pictures have no lock --------------------------------------------------------------------------------------------------------------

echo "300 pictures, and the lock of one of them held by another process\n";

$pics			= raceProject( 'race-pics' );
$picsDir	= ninoSandboxDir( $pics[0] );
$put			= raceBoot( $picsDir );

raceFill( $put, 'A', 200 );
mkdir( $picsDir. '/public/images/elements', 0755, true );

for( $i = 0; $i < 300; $i++ )
	file_put_contents( $picsDir. '/public/images/elements/p'. $i. '.bin', 'archived '. $i );

$archived	= \Nino\Modules\Backups::now( $pics[0] );

raceFill( $put, 'B', 150 );

for( $i = 0; $i < 300; $i++ )
	file_put_contents( $picsDir. '/public/images/elements/p'. $i. '.bin', 'live '. $i );

$locksDir	= $picsDir. '/private/data/.locks';
$before		= count( glob( $locksDir. '/*.lock' ) ?: [] );

@unlink( $release );
$release	= $picsDir. '/race-release';
$holder		= raceStart( [ 'holder', $picsDir, '/images/elements/p17.bin', $release, '6' ], $holderPipes );
fgets( $holderPipes[1] );

\Nino\Backup::$lockWait = 3.0;
$began		= microtime( true );
$answer		= \Nino\Modules\Backups\Admin::restore( raceBoot( $picsDir ), (string) $archived );
$took			= microtime( true ) - $began;
\Nino\Backup::$lockWait = 10.0;

touch( $release );
stream_get_contents( $holderPipes[1] );
proc_close( $holder );

$restored	= 0;
$locked		= 0;

for( $i = 0; $i < 300; $i++ ) {
	$restored += file_get_contents( $picsDir. '/public/images/elements/p'. $i. '.bin' ) === 'archived '. $i ? 1 : 0;
	$locked		+= $i !== 17 && is_file( $locksDir. '/'. sha1( '/images/elements/p'. $i. '.bin' ). '.lock' ) ? 1 : 0;
}

$after		= count( glob( $locksDir. '/*.lock' ) ?: [] );

check( 'a picture whose lock another process holds is put back without waiting for it: 200, and every picture is the archive\'s', $answer === true && $restored === 300 && $took < 2.0 );
check( '...and the pictures have no lock file: the locks the restore made are those of config.php, the texts, the elements, the template and the data', $locked === 0 && $after - $before <= 8 );

\Nino\Filesystem::removeDir( $picsDir );

echo "\n";

// ---- More locks than a restore holds ----------------------------------------------------------------------------------------------------

echo "More files with a lock than the restore holds locks for, and the lock of a late one held for longer than it waits\n";

$many			= raceProject( 'race-many' );
$manyDir	= ninoSandboxDir( $many[0] );
$release	= $manyDir. '/race-release';

@unlink( $release );
$holder		= raceStart( [ 'holder', $manyDir, '/data/forms.race.php', $release, '6' ], $holderPipes );
fgets( $holderPipes[1] );

\Nino\Backup::$locksHeld	= 3;
\Nino\Backup::$lockWait		= 1.0;
$began		= microtime( true );
$answer		= \Nino\Modules\Backups\Admin::restore( raceBoot( $manyDir ), $many[1] );
$took			= microtime( true ) - $began;
\Nino\Backup::$locksHeld	= null;
\Nino\Backup::$lockWait		= 10.0;

$forms		= include $manyDir. '/private/data/forms.race.php';
$text			= include $manyDir. '/private/text/global.php';
$type			= include $manyDir. '/private/elements/news.php';

check( 'a file beyond the locks the restore holds, whose lock is held for longer than the restore waits for it, is answered with a code of its own - not the disk that is full - and names the file and the archive that holds the state before', is_array( $answer ) === true && $answer[0] === 500 && ( $answer[2] ?? '' ) === 'backups_restore_waiting' && ( $answer[3][0] ?? 0 ) === 1 && str_starts_with( (string) ( $answer[3][1] ?? '' ), 'pre-restore-' ) && ( $answer[4]['waiting'] ?? [] ) === [ 'data/forms.race.php' ] && ( $answer[4]['cut'] ?? [] ) === [] );
check( '...the file is as it was, every other file is the archive\'s, and the restore waited the time it is given for it', ( $forms['epoch'] ?? '' ) === 'B' && ( $text['[[_epoch]]'] ?? '' ) === 'A' && ( $type['_epoch'] ?? '' ) === 'A' && str_starts_with( (string) file_get_contents( $manyDir. '/private/templates/part-race.tpl' ), 'epoch=A' ) && $took >= 0.9 && $took < 4.0 );

touch( $release );
stream_get_contents( $holderPipes[1] );
proc_close( $holder );

$answer		= \Nino\Modules\Backups\Admin::restore( raceBoot( $manyDir ), $many[1] );
$forms		= include $manyDir. '/private/data/forms.race.php';

check( '...and a restore once the holder let go puts that file back too', $answer === true && ( $forms['epoch'] ?? '' ) === 'A' );

// The same through the panel: a restore that left files as they were went through all the same, and ends the session as one that went through does
$release		= $manyDir. '/race-release';
@unlink( $release );
$put = raceBoot( $manyDir );
raceFill( $put, 'B', 150 );
$holder		= raceStart( [ 'holder', $manyDir, '/data/forms.race.php', $release, '6' ], $holderPipes );
fgets( $holderPipes[1] );

\Nino\Backup::$locksHeld	= 3;
\Nino\Backup::$lockWait		= 1.0;
\Nino\Auth::loginUser( $many[0], 'dev@example.com', 'correct horse battery staple' );
[ $status, $body ] = ninoCall( $many[0], [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $many[1] ] );
\Nino\Backup::$locksHeld	= null;
\Nino\Backup::$lockWait		= 10.0;

touch( $release );
stream_get_contents( $holderPipes[1] );
proc_close( $holder );

check( 'a restore that left a file as it was because another request held its lock is answered with its code and names the file, and the session of the account that ran it is ended, as a restore that went through does', $status === 500 && ( $body['code'] ?? '' ) === 'backups_restore_waiting' && ( $body['waiting'] ?? [] ) === [ 'data/forms.race.php' ] && \Nino\Runtime::getSessionValue( $many[0], './nino/auth/current', '' ) === '' && isset( $many[0]['./nino/auth/current'] ) === false );
check( '...and the recovery page, which asks the same question of the answer, ends it as well: the code is one of a restore that went through', \Nino\Modules\Backups\Admin::wentThrough( [ 500, '', 'backups_restore_waiting' ] ) === true && \Nino\Modules\Backups\Admin::wentThrough( [ 500, '', 'backups_restore_partial' ] ) === false );

\Nino\Filesystem::removeDir( $manyDir );

echo "\n";

// ---- A callback that writes through the kernel -----------------------------------------------------------------------------------------

echo "A callback of the restore that writes a file the restore holds the lock of\n";

$cb				= raceProject( 'race-callback' );
$cbDir		= ninoSandboxDir( $cb[0] );
$release	= $cbDir. '/race-release';
$live			= raceBoot( $cbDir );
$holder		= null;

@unlink( $release );

// A write of the kernel lets go of the lock of that path, the restore's included: another process takes it, and the restore asks for it once more
\Nino\Callbacks::registerCallback( $live, '/nino/admin/restore', static function( array &$appData, array &$args ) use ( &$holder, &$holderPipes, $cbDir, $release ): void {

	\Nino\Filesystem::mutate( $appData, '/text/en_US.php', static function( array $content ): array { $content['[[cb]]'] = 'written by the callback'; return $content; } );

	$holder = raceStart( [ 'holder', $cbDir, '/text/en_US.php', $release, '1.2' ], $holderPipes );
	fgets( $holderPipes[1] );
} );

$began		= microtime( true );
$answer		= \Nino\Modules\Backups\Admin::restore( $live, $cb[1] );
$took			= microtime( true ) - $began;
$text			= include $cbDir. '/private/text/en_US.php';

if( $holder !== null ) {
	stream_get_contents( $holderPipes[1] );
	proc_close( $holder );
}

check( 'a file whose lock a callback of the restore let go of is put back under its lock all the same: the restore waits for the process that took it, and the file is the archive\'s', $answer === true && $took >= 1.0 && ( $text['[[_epoch]]'] ?? '' ) === 'A' && isset( $text['[[cb]]'] ) === false );

\Nino\Filesystem::removeDir( $cbDir );

echo "\n";

// ---- A writer waits for what the restore holds, and the restore for what that writer holds -----------------------------------------------------

echo "A writer that holds one lock and waits for another that the restore holds, while the restore waits for the first\n";

$wedge		= raceProject( 'race-wedge' );
$wedgeDir	= ninoSandboxDir( $wedge[0] );
$writer		= raceStart( [ 'wedge', $wedgeDir ], $writerPipes );
fgets( $writerPipes[1] );

$restoring	= raceStart( [ 'restores', $wedgeDir, $wedge[1], '1', '10' ], $restoringPipes );
$answers		= json_decode( (string) stream_get_contents( $restoringPipes[1] ), true );
proc_close( $restoring );
$said				= json_decode( (string) stream_get_contents( $writerPipes[1] ), true );
proc_close( $writer );

check( 'the restore lets go of what it holds when it waits for a lock for a fifth of its time, so that the writer gets the lock it waits for within seconds and not when the restore gives up', is_array( $said ) === true && ( $said['got'] ?? false ) === true && ( $said['seconds'] ?? 99 ) < 4.5 );
check( '...and the restore is answered 200', ( $answers['true'] ?? 0 ) === 1 && ( $answers['seconds'] ?? 99 ) < 8.0 );

\Nino\Filesystem::removeDir( $wedgeDir );

echo "\n";

// ---- What a restore that was ended left -------------------------------------------------------------------------------------------------

echo "Copies that a restore left beside the files when php ended it\n";

$sweep		= raceProject( 'race-sweep' );
$sweepDir	= ninoSandboxDir( $sweep[0] );
$put			= raceBoot( $sweepDir );

raceFill( $put, 'A', 200 );
\Nino\Filesystem::putFileContent( $put, '/assets/a.css', 'archived a' );
mkdir( $sweepDir. '/public/images/elements', 0755, true );
file_put_contents( $sweepDir. '/public/images/elements/pic.bin', 'archived pic' );
$archived	= \Nino\Modules\Backups::now( $sweep[0] );

$gone			= [ 'private/assets/a.css.abcdefabcdef.tmp', 'public/images/elements/pic.bin.0123456789ab.tmp', 'private/templates/part-race.tpl.abcdefabcdef.tmp', 'private/config.php.abcdefabcdef.tmp', 'private/data/forms.race.php.abcdefabcdef.tmp' ];
$stays		= [ 'private/assets/x.tmp', 'public/images/elements/photo.abcdefabcdef.tmpl', 'public/images/elements/user.ABCDEFABCDEF.tmp', 'private/assets/.work/a.css.abcdefabcdef.tmp', 'private/assets/a.css.fedcbafedcba.tmp', 'private/assets/other.css.abcdefabcdef.tmp', 'public/images/elements/holiday.0123456789ab.tmp', 'private/data/ledger/export.0123456789ab.tmp', 'private/assets/a.css.abcdefabcdef.tmp.keep' ];

mkdir( $sweepDir. '/private/assets/.work', 0755, true );
mkdir( $sweepDir. '/private/data/ledger', 0755, true );

foreach( array_merge( $gone, $stays ) as $one ) {
	file_put_contents( $sweepDir. '/'. $one, 'left' );
	if( str_contains( $one, 'fedcba' ) === false )
		touch( $sweepDir. '/'. $one, time() - 7200 );
}

$answer		= \Nino\Modules\Backups\Admin::restore( raceBoot( $sweepDir ), (string) $archived );

check( 'the restore takes away the copies of its own name an ended restore left in the directories it puts files into - and only those, of the files it puts back: a file of another name, one that a person or a feature named like a copy, a copy of a file the archive has not, a hidden folder, a copy that is not old - a writer at work - stay', $answer === true && array_filter( $gone, static fn( string $one ): bool => file_exists( $sweepDir. '/'. $one ) ) === [] && array_filter( $stays, static fn( string $one ): bool => file_exists( $sweepDir. '/'. $one ) === false ) === [] );

\Nino\Filesystem::removeDir( $sweepDir );

echo "\n";

// ---- The room of the copy beside the file ---------------------------------------------------------------------------------------------------------

echo "A disk with room for the files but not for the copy beside the largest of them\n";

$room			= raceProject( 'race-room' );
$roomDir	= ninoSandboxDir( $room[0] );
$staged		= $roomDir. '/staged';

mkdir( $roomDir. '/private/assets', 0755, true );
mkdir( $staged. '/assets', 0755, true );
mkdir( $staged. '/data', 0755, true );
file_put_contents( $roomDir. '/private/assets/big.bin', str_repeat( 'b', 4 * 1048576 ) );
file_put_contents( $staged. '/assets/big.bin', str_repeat( 'a', 4 * 1048576 ) );
file_put_contents( $roomDir. '/private/data/plain.sqlite', str_repeat( 'b', 4 * 1048576 ) );
file_put_contents( $staged. '/data/plain.sqlite', str_repeat( 'a', 4 * 1048576 ) );

$asked		= static function( int $bytes ) use ( $room, $staged ): ?array {
	\Nino\Backup::$diskFree = $bytes;
	$short = \Nino\Backup::restoreRoom( $room[0], $staged );
	\Nino\Backup::$diskFree = null;
	return $short;
};

// The file that replaces one of its size needs the copy beside it as well as the reserve; the database that is written in place needs neither
check( 'a restore of a file as large as the one it replaces needs the room of a copy of it beside the file: a disk that has the reserve and not that is refused before anything is copied, with the numbers of the room', $asked( 8 * 1048576 - 1 ) === [ 8, 7 ] && $asked( 8 * 1048576 ) === null );

unlink( $staged. '/assets/big.bin' );
check( '...and a file that is written in place - a database below data/ - needs the reserve alone', $asked( 4 * 1048576 ) === null && is_array( $asked( 4 * 1048576 - 1 ) ) === true );

\Nino\Filesystem::removeDir( $roomDir );

echo "\n";

// ---- A picture of 20 MB ----------------------------------------------------------------------------------------------------------------

echo "A picture of 20 MB put back in 32 MB of memory\n";

$large		= raceProject( 'race-large' );
$largeDir	= ninoSandboxDir( $large[0] );

mkdir( $largeDir. '/public/images/elements', 0755, true );

$handle = fopen( $largeDir. '/public/images/elements/large.bin', 'wb' );

for( $piece = 0; $piece < 20; $piece++ )
	fwrite( $handle, random_bytes( 1048576 ) );

fclose( $handle );

$bigHash		= sha1_file( $largeDir. '/public/images/elements/large.bin' );
$archived		= \Nino\Modules\Backups::now( $large[0] );

file_put_contents( $largeDir. '/public/images/elements/large.bin', 'cut short' );

$bigRestore	= raceStart( [ 'big', $largeDir, (string) $archived ], $bigPipes, '32M' );
$bigAnswer	= json_decode( (string) stream_get_contents( $bigPipes[1] ), true );
proc_close( $bigRestore );

check( 'a picture of 20 MB is put back whole in a process that may use 32 MB, and no temporary file is left beside it', is_array( $bigAnswer ) === true && ( $bigAnswer[0] ?? null ) === true && sha1_file( $largeDir. '/public/images/elements/large.bin' ) === $bigHash && ( glob( $largeDir. '/public/images/elements/*.tmp' ) ?: [] ) === [] );

\Nino\Filesystem::removeDir( $largeDir );

echo "\n";

ninoDone( $project[0] );
