<?php
declare(strict_types=1);

/**
 *	Nino										A compact filesystembased php framework
 *	filesystem-atomic-smoke.php	\Nino\Filesystem::writeAtomic(): a file replaced as a whole -
 *													string and pieces, the mode of a file that is there, what a failure
 *													leaves (the target as it was, no temporary file, no php warning),
 *													the failures php itself raises a notice or a warning for - a write past the size
 *													limit of the process, a rename the directory refuses - which end the
 *													call with false and not the request, and \Nino\Admin\Admin::writeFileAtomic()
 *													as its other name.
 *
 *	Usage: php tests/filesystem-atomic-smoke.php
 */

// Worker mode - this same file as a process of its own, with the kernel alone: writes of a content that is whole by its own sign, and reads of what is there
if( ( $argv[1] ?? '' ) === 'writer' ) {

	require __DIR__. '/../_nino/Nino.php';

	$bad = 0;

	for( $round = 0; $round < (int) $argv[4]; $round++ ) {

		// Two letters repeated to the length of 100 000: the writer and the round
		$sign = chr( 65 + (int) $argv[3] ). chr( 97 + $round % 26 );

		if( \Nino\Filesystem::writeAtomic( $argv[2], str_repeat( $sign, 50000 ) ) !== true )
			$bad++;

		$read = (string) @file_get_contents( $argv[2] );

		if( strlen( $read ) !== 100000 || $read !== str_repeat( substr( $read, 0, 2 ), 50000 ) )
			$bad++;
	}

	echo $bad;
	exit( 0 );
}

// Worker mode - this same file as a process of its own, with the kernel alone and the handler of errors a request has (\Nino\Runtime::handleError(), which
// ends the request on a notice or a warning php raised itself). It writes one file and says what came of it: the line "after" is not printed by a process that ended
// in the call. 'limit' runs under the size limit of the process (see the parent), 'drop' as the user nobody once the kernel is loaded. Arguments: mode, the
// tree of the kernel, the directory, the scenario
if( in_array( $argv[1] ?? '', [ 'limit', 'drop' ], true ) ) {

	require $argv[2]. '/_nino/Nino.php';
	class_exists( \Nino\Runtime::class );
	class_exists( \Nino\Filesystem::class );
	set_error_handler( [ \Nino\Runtime::class, 'handleError' ] );

	if( $argv[1] === 'drop' && ( posix_setgid( 65534 ) === false || posix_setuid( 65534 ) === false ) ) {
		echo "cannot become nobody\n";
		exit( 3 );
	}

	$content = match( $argv[4] ) {
		'pieces'	=> ( static function(): Generator { for( $piece = 0; $piece < 200; $piece++ ) yield str_repeat( 'x', 1000 ); } )(),
		default		=> str_repeat( 'x', 200000 ),
	};

	echo "before\n";
	echo 'after:'. json_encode( \Nino\Filesystem::writeAtomic( $argv[3]. '/target.txt', $content ) ). "\n";
	exit( 0 );
}

require __DIR__. '/harness.php';

/** A stream that takes half of what it is given: a full disk, as fwrite() reports it. It records what is done to the files of it */
class AtomicShortStream {

	public static array $done = [];
	public $context;

	public function stream_open( string $path, string $mode, int $options, ?string &$opened ): bool { self::$done[] = 'open '. $path; return true; }
	public function stream_write( string $data ): int { return intdiv( strlen( $data ), 2 ); }
	public function stream_flush(): bool { return true; }
	public function stream_close(): void {}
	public function stream_eof(): bool { return true; }
	public function url_stat( string $path, int $flags ): array|false { return false; }
	public function unlink( string $path ): bool { self::$done[] = 'unlink '. $path; return true; }
	public function rename( string $from, string $to ): bool { self::$done[] = 'rename '. $from. ' -> '. $to; return true; }
}

/** A stream that writes whole and fails where it is told to: its flush answers false, or its close throws. It records what is done to the files of it */
class AtomicModeStream {

	public static array $done = [];
	public static string $mode = '';
	public $context;

	public function stream_open( string $path, string $mode, int $options, ?string &$opened ): bool { self::$done[] = 'open '. $path; return true; }
	public function stream_write( string $data ): int { return strlen( $data ); }
	public function stream_flush(): bool { return self::$mode !== 'flush'; }
	public function stream_close(): void { if( self::$mode === 'close' ) throw new RuntimeException( 'the stream could not be closed' ); }
	public function stream_eof(): bool { return true; }
	public function url_stat( string $path, int $flags ): array|false { return false; }
	public function unlink( string $path ): bool { self::$done[] = 'unlink '. $path; return true; }
	public function rename( string $from, string $to ): bool { self::$done[] = 'rename '. $from. ' -> '. $to; return true; }
}

$appData = ninoSandbox( 'atomic' );
$dir		 = ninoSandboxDir( $appData ). '/atomic';
mkdir( $dir, 0755, true );

$temps = static fn(): array => glob( $dir. '/*.tmp' ) ?: [];

echo "\\Nino\\Filesystem::writeAtomic - a string and pieces\n";

$answer = null;
$errors = ninoErrors( function() use ( $dir, &$answer ): void { $answer = \Nino\Filesystem::writeAtomic( $dir. '/string.txt', "bytes\nof a string" ); } );
check( 'a string is the file, byte for byte, and the call answers true and raises nothing', $answer === true && $errors === [] && file_get_contents( $dir. '/string.txt' ) === "bytes\nof a string" );

$pieces = static function(): Generator { yield 'first'; yield ''; yield 'second'; yield "\0third"; };
check( 'pieces from a generator are written in order, byte for byte', \Nino\Filesystem::writeAtomic( $dir. '/pieces.txt', $pieces() ) === true && file_get_contents( $dir. '/pieces.txt' ) === "firstsecond\0third" );
check( '...an array of pieces too, and nothing at all is an empty file', \Nino\Filesystem::writeAtomic( $dir. '/array.txt', [ 'a', 'b' ] ) === true && file_get_contents( $dir. '/array.txt' ) === 'ab'
	&& \Nino\Filesystem::writeAtomic( $dir. '/empty.txt', '' ) === true && file_get_contents( $dir. '/empty.txt' ) === '' );
check( '...an existing file is replaced as a whole, and no temporary file is left', \Nino\Filesystem::writeAtomic( $dir. '/string.txt', 'short' ) === true && file_get_contents( $dir. '/string.txt' ) === 'short' && $temps() === [] );
check( '...\\Nino\\Admin\\Admin::writeFileAtomic() is the same writer: string and pieces', \Nino\Admin\Admin::writeFileAtomic( $dir. '/admin-string.txt', 'one' ) === true && \Nino\Admin\Admin::writeFileAtomic( $dir. '/admin-pieces.txt', $pieces() ) === true
	&& file_get_contents( $dir. '/admin-string.txt' ) === 'one' && file_get_contents( $dir. '/admin-pieces.txt' ) === "firstsecond\0third" && $temps() === [] );

echo "\nThe mode of the file\n";

$modes = [];
foreach( [ 0640, 0600, 0664 ] as $mode ) {
	file_put_contents( $dir. '/mode.txt', 'old' );
	chmod( $dir. '/mode.txt', $mode );
	clearstatcache();
	\Nino\Filesystem::writeAtomic( $dir. '/mode.txt', 'new' );
	clearstatcache( true, $dir. '/mode.txt' );
	$modes[] = fileperms( $dir. '/mode.txt' ) & 0777;
}
check( 'a file that is there keeps its mode (0640, 0600, 0664) and gets the new bytes', $modes === [ 0640, 0600, 0664 ] && file_get_contents( $dir. '/mode.txt' ) === 'new' );
\Nino\Filesystem::writeAtomic( $dir. '/fresh.txt', 'x' );
check( '...a file that is not there has the mode the umask gives a new file', ( fileperms( $dir. '/fresh.txt' ) & 0777 ) === ( 0666 & ~umask() ) );

echo "\nThe target is replaced, not written in place\n";

file_put_contents( $dir. '/replaced.txt', 'the old bytes, whole' );
$reader	= fopen( $dir. '/replaced.txt', 'rb' );
$before	= fileinode( $dir. '/replaced.txt' );
\Nino\Filesystem::writeAtomic( $dir. '/replaced.txt', 'new' );
clearstatcache();
check( 'a file that is there is replaced by another file: its inode is another one', fileinode( $dir. '/replaced.txt' ) !== $before && file_get_contents( $dir. '/replaced.txt' ) === 'new' );
check( '...and a reader that holds the old file open reads the old bytes whole', stream_get_contents( $reader ) === 'the old bytes, whole' );
fclose( $reader );

echo "\nSeveral writers of one target at once\n";

$target	= $dir. '/contended.txt';
$procs	= [];

for( $writer = 0; $writer < 4; $writer++ )
	$procs[$writer] = proc_open( [ PHP_BINARY, __FILE__, 'writer', $target, (string) $writer, '40' ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ], $pipes[$writer] );

$bad = 0;
foreach( $procs as $writer => $process ) {
	$bad += (int) stream_get_contents( $pipes[$writer][1] );
	proc_close( $process );
}

$final = (string) file_get_contents( $target );
check( 'four processes that write the same target 40 times each: no write answers false and every read in between is one writer\'s content whole', $bad === 0 );
check( '...the file at the end is one writer\'s content whole, and no temporary file is left', strlen( $final ) === 100000 && $final === str_repeat( substr( $final, 0, 2 ), 50000 ) && $temps() === [] );

echo "\nWhat a failure leaves\n";

mkdir( $dir. '/occupied.txt' );
$answer = null;
$errors = ninoErrors( function() use ( $dir, &$answer ): void { $answer = \Nino\Filesystem::writeAtomic( $dir. '/occupied.txt', 'bytes' ); } );
check( 'a directory in the place of the file: false, no php warning, the directory is still there and no temporary file is left', $answer === false && $errors === [] && is_dir( $dir. '/occupied.txt' ) === true && $temps() === [] );

$errors = ninoErrors( function() use ( $dir, &$answer ): void { $answer = \Nino\Filesystem::writeAtomic( $dir. '/missing/file.txt', 'bytes' ); } );
check( 'a directory that is not there: false and no php warning', $answer === false && $errors === [] && is_dir( $dir. '/missing' ) === false );

$failing = static function(): Generator { yield 'first'; throw new RuntimeException( 'the pieces ended' ); };
$thrown = null;
try { \Nino\Filesystem::writeAtomic( $dir. '/new-target.txt', $failing() ); } catch( RuntimeException $e ) { $thrown = $e->getMessage(); }
check( 'pieces that fail in the middle: the exception goes on to the caller, the target that was not there is not made, no temporary file is left', $thrown === 'the pieces ended' && is_file( $dir. '/new-target.txt' ) === false && $temps() === [] );

file_put_contents( $dir. '/kept.txt', 'as it was' );
$thrown = null;
try { \Nino\Filesystem::writeAtomic( $dir. '/kept.txt', $failing() ); } catch( RuntimeException $e ) { $thrown = $e->getMessage(); }
check( '...and a target that was there is as it was', $thrown === 'the pieces ended' && file_get_contents( $dir. '/kept.txt' ) === 'as it was' && $temps() === [] );

stream_wrapper_register( 'atomicshort', 'AtomicShortStream' );
AtomicShortStream::$done = [];
$answer = null;
$errors = ninoErrors( function() use ( &$answer ): void { $answer = \Nino\Filesystem::writeAtomic( 'atomicshort://disk/file.txt', 'twelve bytes' ); } );
check( 'a short write, as a full disk makes one: false, the temporary file is removed and the target is never renamed over', $answer === false && count( AtomicShortStream::$done ) === 2 && str_starts_with( AtomicShortStream::$done[0], 'open atomicshort://disk/file.txt.' )
	&& str_starts_with( AtomicShortStream::$done[1], 'unlink atomicshort://disk/file.txt.' ) && array_filter( AtomicShortStream::$done, static fn( string $step ): bool => str_starts_with( $step, 'rename' ) ) === [] );
stream_wrapper_unregister( 'atomicshort' );

stream_wrapper_register( 'atomicmode', 'AtomicModeStream' );
AtomicModeStream::$done = [];
AtomicModeStream::$mode = 'flush';
$answer = null;
$errors = ninoErrors( function() use ( &$answer ): void { $answer = \Nino\Filesystem::writeAtomic( 'atomicmode://disk/file.txt', 'twelve bytes' ); } );
check( 'a flush that fails: false, the temporary file is removed and the target is never renamed over', $answer === false && $errors === [] && count( AtomicModeStream::$done ) === 2 && str_starts_with( AtomicModeStream::$done[1], 'unlink atomicmode://disk/file.txt.' ) );

AtomicModeStream::$done = [];
AtomicModeStream::$mode = 'close';
$thrown = null;
try { \Nino\Filesystem::writeAtomic( 'atomicmode://disk/file.txt', 'twelve bytes' ); } catch( RuntimeException $e ) { $thrown = $e->getMessage(); }
check( 'a stream that throws when it is closed: the exception goes on to the caller, the temporary file is removed and the target is never renamed over', $thrown === 'the stream could not be closed'
	&& count( AtomicModeStream::$done ) === 2 && str_starts_with( AtomicModeStream::$done[1], 'unlink atomicmode://disk/file.txt.' ) );
AtomicModeStream::$mode = '';
stream_wrapper_unregister( 'atomicmode' );

echo "\nA write that php refuses, as php raises it\n";

// A request in which php itself raises the notice of a failed write is ended by the kernel's handler. The failures below are real ones,
// produced in a process of its own that has that handler: the limit on the size of a file (php does not ignore the signal the system sends past
// it, so the process ignores it first and the write fails with a notice of php's own, errno 27, at level E_NOTICE), and a directory that refuses the rename
$real = static function( string $tree, string $mode, string $scenario, string $where ): array {

	$command = $mode === 'limit'
		? [ 'sh', '-c', 'trap "" XFSZ; ulimit -f 8; exec "$0" "$@"', PHP_BINARY, __FILE__, 'limit', $tree, $where, $scenario ]
		: [ PHP_BINARY, __FILE__, 'drop', $tree, $where, $scenario ];

	$process	= proc_open( $command, [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	$out			= (string) stream_get_contents( $pipes[1] );
	stream_get_contents( $pipes[2] );

	return [ 'out' => $out, 'status' => proc_close( $process ) ];
};

$root			= dirname( __DIR__ );
$expected	= "before\nafter:false\n";

foreach( [ 'string' => 'a string', 'pieces' => 'pieces of 1000 bytes' ] as $scenario => $label ) {

	$where = $dir. '/limit-'. $scenario;
	mkdir( $where );
	file_put_contents( $where. '/target.txt', 'old' );

	$ran = $real( $root, 'limit', $scenario, $where );
	check( 'a write past the size limit of the process ('. $label. '): the process ends normally and the line after the call runs, the answer is false', $ran['status'] === 0 && $ran['out'] === $expected );
	check( '...no temporary file is left and the target is as it was', glob( $where. '/*.tmp' ) === [] && file_get_contents( $where. '/target.txt' ) === 'old' );
}

if( function_exists( 'posix_geteuid' ) === false || posix_geteuid() !== 0 )
	echo "  skip  - a directory that refuses the write to nobody: this run is not root, which the change to the user nobody needs\n";
else {

	// Read-only for nobody: the temporary file cannot be made
	$where = $dir. '/readonly';
	mkdir( $where );
	file_put_contents( $where. '/target.txt', 'old' );
	chmod( $where, 0555 );

	$ran = $real( $root, 'drop', 'string', $where );
	check( 'a directory nobody cannot write to: the process ends normally, the answer is false, no temporary file is left and the target is as it was', $ran['status'] === 0 && $ran['out'] === $expected
		&& glob( $where. '/*.tmp' ) === [] && file_get_contents( $where. '/target.txt' ) === 'old' );

	// Sticky, with a target of root's: the temporary file is made and written, and rename() over a file of another owner is refused
	$where = $dir. '/sticky';
	mkdir( $where );
	file_put_contents( $where. '/target.txt', 'old' );
	chmod( $where, 01777 );

	$ran = $real( $root, 'drop', 'string', $where );
	check( 'a directory that refuses the rename over a file of another owner: the process ends normally, the answer is false, the temporary file is removed and the target is as it was', $ran['status'] === 0 && $ran['out'] === $expected
		&& glob( $where. '/*.tmp' ) === [] && file_get_contents( $where. '/target.txt' ) === 'old' );
}

echo "\nopcache\n";

$errors = ninoErrors( function() use ( $dir, &$answer ): void { $answer = \Nino\Filesystem::writeAtomic( $dir. '/code.php', '<?php return 1;' ); } );
check( 'the call that drops the compiled copy is harmless: with opcache loaded or without it the write answers true and raises nothing', $answer === true && $errors === [] && ( include $dir. '/code.php' ) === 1 );

ninoDone( $appData );
