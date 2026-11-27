<?php
declare(strict_types=1);

/**
 *	Nino										A compact filesystembased php framework
 *	filesystem-atomic-smoke.php	\Nino\Filesystem::writeAtomic(): a file replaced as a whole -
 *													string and pieces, the mode of a file that is there, what a failure
 *													leaves (the target as it was, no temporary file, no php warning),
 *													and \Nino\Admin\Admin::writeFileAtomic() as its other name.
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

echo "\nopcache\n";

$errors = ninoErrors( function() use ( $dir, &$answer ): void { $answer = \Nino\Filesystem::writeAtomic( $dir. '/code.php', '<?php return 1;' ); } );
check( 'the call that drops the compiled copy is harmless: with opcache loaded or without it the write answers true and raises nothing', $answer === true && $errors === [] && ( include $dir. '/code.php' ) === 1 );

ninoDone( $appData );
