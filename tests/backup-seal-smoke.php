<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	backup-seal-smoke.php	Dependency-free smoke test for the file of a backup archive: the layout of format 2 and
 *												what writes and reads it (\Nino\Backup\Seal), read against a writer and a reader of the
 *												suite that know the layout and nothing of the product; an archive of format 1 that
 *												the code which wrote them made, still restored; every way to damage a file - a bit, a
 *												chunk moved, repeated, left out, an end cut, bytes added, a chunk of another file, a
 *												header that lies - refused at the three doors a file comes in at, with the project as
 *												it was; the unpacking of a restore; and a backup, an upload and a restore of data many
 *												times the memory limit of the process that runs them, as a child of this file.
 *
 *	Usage: php tests/backup-seal-smoke.php
 */

// Run as a child of itself: a project of more data than the memory limit is, backed up, uploaded and restored
if( ( $argv[1] ?? '' ) === 'child' ) {

	require __DIR__. '/harness.php';

	[ , , $megabytes ] = $argv;

	$appData = ninoSandbox( 'seal-child' );
	$sandbox = ninoSandboxDir( $appData );
	mkdir( $sandbox. '/private/assets', 0755, true );
	mkdir( $sandbox. '/public', 0755, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/error/display' => true, '/nino/locales/native' => $appData['/nino/locales/native'], '/nino/locales/available' => $appData['/nino/locales/available'] ] );
	$appData['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $appData );
	ninoUser( $appData, 'dev@example.com' );

	// Files of 8 MB that do not compress, written in pieces: the data that the old way of making an archive needed twice over
	for( $file = 0; $file < (int) $megabytes / 8; $file++ ) {
		$handle = fopen( $sandbox. '/private/assets/blob-'. $file. '.bin', 'wb' );
		for( $piece = 0; $piece < 8; $piece++ )
			fwrite( $handle, random_bytes( 1048576 ) );
		fclose( $handle );
	}

	$hash = static function() use ( $sandbox ): string {
		$lines = [];
		foreach( glob( $sandbox. '/private/assets/*' ) ?: [] as $file )
			$lines[] = basename( $file ). ' '. sha1_file( $file );
		return sha1( implode( "\n", $lines ) );
	};

	$before = $hash();
	$id 		= \Nino\Modules\Backups::now( $appData );

	if( is_string( $id ) === false )
		exit( 'create: '. json_encode( $id ) );

	$archive = $sandbox. '/private/.backups/'. $id. '.php';
	$copy 	 = $sandbox. '/uploaded.bin';
	copy( $archive, $copy );
	unlink( $archive );

	$_FILES['file'] = [ 'name' => $id. '.php', 'type' => 'application/octet-stream', 'tmp_name' => $copy, 'error' => 0, 'size' => filesize( $copy ) ];
	$uploaded = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiUpload' ] );

	if( $uploaded[0] !== 200 )
		exit( 'upload: '. json_encode( $uploaded[1] ) );

	// What is restored is what was archived: the files are changed first
	foreach( glob( $sandbox. '/private/assets/*' ) ?: [] as $file )
		file_put_contents( $file, 'changed' );

	$restored = \Nino\Modules\Backups\Admin::restore( $appData, $id );

	echo $restored === true && $hash() === $before ? 'survived' : 'restore: '. json_encode( $restored );
	exit;
}

// Run as a child of itself against a project that the parent made: one step, in a process of its own
if( in_array( $argv[1] ?? '', [ 'hold', 'now', 'now-blind', 'restore-blind', 'die-in-copy', 'upload', 'squeeze-upload', 'squeeze-restore' ], true ) === true ) {

	require __DIR__. '/harness.php';

	$mode 		= $argv[1];
	$project 	= $argv[2];
	$appData 	= [ './nino/uid' => $project ];
	\Nino\AppData::prepare( $appData );

	foreach( [ 'path' => $project, 'configpath' => $project. '/private', 'contentpath' => $project. '/private', 'publicpath' => $project. '/public' ] as $name => $path )
		$appData['./nino/filesystem/'. $name] = $path;

	$config = include $project. '/private/config.php';
	$appData['/nino/locales/native'] 		= 'de_DE';
	$appData['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
	$appData['/nino/dir'] 							= '';
	$appData['/nino/modules'] 					= [];
	$appData['/nino/backup/key'] 				= $config['/nino/backup/key'];
	$appData['/nino/auth/roles'] 				= \Nino\Modules\Users\Roles::defaults( $appData );

	if( $mode === 'hold' ) {
		\Nino\Filesystem::lockFile( $appData, \Nino\Modules\Backups::LOCK_PATH, false );
		echo "held\n";
		fflush( STDOUT );
		usleep( (int) ( (float) $argv[3] * 1000000 ) );
		exit;
	}

	// The check of the room is passed - a disk that another request fills is what that stands for - and the handler of the kernel is
	// the one that ends a request at a notice, as it does in a request of the workbench
	if( $mode === 'now-blind' || $mode === 'restore-blind' ) {
		$appData['./nino/backups/disk'] = 1 << 40;
		set_error_handler( [ \Nino\Runtime::class, 'handleError' ] );
	}

	if( $mode === 'restore-blind' ) {
		echo json_encode( \Nino\Modules\Backups\Admin::restore( $appData, $argv[3] ) );
		exit;
	}

	// A request that php ends once the archive is unpacked: the memory runs out in the callback a feature registers for a restore
	if( $mode === 'die-in-copy' ) {
		\Nino\Callbacks::registerCallback( $appData, '/nino/admin/restore', static function( array &$appData, array &$args ): void {
			ini_set( 'memory_limit', (string) ( memory_get_usage( true ) + 1048576 ) );
			$more = str_repeat( 'x', 8 * 1048576 );
		} );
		\Nino\Modules\Backups\Admin::restore( $appData, $argv[3] );
		echo 'survived';
		exit;
	}

	if( $mode === 'now' || $mode === 'now-blind' ) {
		echo json_encode( \Nino\Modules\Backups::now( $appData ) );
		exit;
	}

	if( $mode === 'upload' || $mode === 'squeeze-upload' ) {
		ninoUser( $appData, 'child@example.com' );
		$_FILES['file'] = [ 'name' => $argv[4], 'type' => 'application/octet-stream', 'tmp_name' => $argv[3], 'error' => 0, 'size' => filesize( $argv[3] ) ];

		if( $mode === 'squeeze-upload' )
			ini_set( 'memory_limit', (string) ( memory_get_usage( true ) + 1048576 ) );

		$answer = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiUpload' ] );
		echo 'survived '. $answer[0];
		exit;
	}

	// squeeze-restore
	ini_set( 'memory_limit', (string) ( memory_get_usage( true ) + 1048576 ) );
	\Nino\Modules\Backups\Admin::restore( $appData, $argv[3] );
	echo 'survived';
	exit;
}

require __DIR__. '/harness.php';

/** A project that is set up, with one developer account that is signed in and its first archive */
function sealProject( string $name, ?string $key = null ): array {

	$appData = ninoSandbox( $name );
	$sandbox = ninoSandboxDir( $appData );
	mkdir( $sandbox. '/private', 0755, true );
	mkdir( $sandbox. '/public', 0755, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', ( $key === null ? [] : [ '/nino/backup/key' => $key ] ) + [
		'/nino/error/display'			=> true,
		'/nino/locales/native'		=> $appData['/nino/locales/native'],
		'/nino/locales/available'	=> $appData['/nino/locales/available'],
	] );

	$appData['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $appData );
	ninoUser( $appData, 'dev@example.com' );
	\Nino\Modules\Backups::maybeRun( $appData );

	return $appData;
}

/** What the project is: a hash of every file below its directory but the archives, the activity log and the locks, which a request writes in passing */
function sealTree( string $sandbox ): string {

	$lines = [];

	foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sandbox, FilesystemIterator::SKIP_DOTS ) ) as $file ) {

		$relative = substr( $file->getPathname(), strlen( $sandbox ) + 1 );

		if( preg_match( '#^private/(\.backups|\.logs|data/\.locks)/#', $relative ) !== 1 && str_starts_with( $relative, 'upload-' ) === false )
			$lines[] = $relative. ' '. sha1_file( $file->getPathname() );
	}

	sort( $lines );

	return sha1( implode( "\n", $lines ) );
}

/** The stub around bytes that are not an archive's: what the base64 of a file holds, however it was made */
function sealFile( string $binary ): string {
	return "<?php http_response_code(403); exit; return '". base64_encode( $binary ). "';\n";
}

/** The bytes of a file in format 2 without its stub */
function sealBinary( string $file ): string {
	return (string) base64_decode( substr( $file, strlen( "<?php http_response_code(403); exit; return '" ), -strlen( "';\n" ) ), true );
}

/** What the one door of the product that takes any bytes answers, as a code */
function sealOpen( string $bytes, string $key ): ?string {

	$path = sys_get_temp_dir(). '/ninotest-seal-'. bin2hex( random_bytes( 6 ) );
	file_put_contents( $path, $bytes );

	try {
		return \Nino\Backup\Seal::open( $path, $key, null );
	}
	finally {
		unlink( $path );
	}
}

$key 	= random_bytes( 32 );
$seal = \Nino\Backup\Seal::class;

echo "Layout\n";

// What the product writes is read by a reader that knows the layout alone, and what that writer makes is read by the product
$chunk 		= \Nino\Backup\Seal::CHUNK;
$dir 			= sys_get_temp_dir(). '/ninotest-seal-'. bin2hex( random_bytes( 6 ) );
mkdir( $dir );

$written 	= static function( string $plain, string $key, int $size = \Nino\Backup\Seal::CHUNK, int $max = \Nino\Backup\Seal::MAX_CHUNKS ) use ( $dir ): string {
	file_put_contents( $dir. '/plain', $plain );
	$out = '';
	foreach( \Nino\Backup\Seal::pieces( $dir. '/plain', $key, $size, $max ) as $piece )
		$out .= $piece;
	return $out;
};

$sizes = [ 0, 1, 2, 3, $chunk - 1, $chunk, $chunk + 1, 2 * $chunk, 2 * $chunk + 5 ];
$both  = true;
$total = [];

foreach( $sizes as $size ) {

	$plain = $size === 0 ? '' : random_bytes( $size );
	$file 	= $written( $plain, $key );

	file_put_contents( $dir. '/seal.php', ninoSealed( $plain, $key ) );
	file_put_contents( $dir. '/mine.php', $file );

	$opened = \Nino\Backup\Seal::open( $dir. '/mine.php', $key, $dir. '/out', $head );
	$theirs = \Nino\Backup\Seal::open( $dir. '/seal.php', $key, $dir. '/out2' );

	if( ninoUnsealed( $file, $key ) !== $plain || $opened !== null || file_get_contents( $dir. '/out' ) !== $plain || $theirs !== null || file_get_contents( $dir. '/out2' ) !== $plain )
		$both = false;

	$total[$size] = strlen( sealBinary( $file ) );
}

check( 'what the product writes is read by the reader of the suite, and what the writer of the suite makes is read by the product: every size around a chunk', $both === true );

$plain = random_bytes( 2 * $chunk + 100 );
$file 	= $written( $plain, $key );
$binary = sealBinary( $file );

check( 'a file is the stub, the base64 of the layout and nothing else', str_starts_with( $file, \Nino\Backup\Seal::STUB_PREFIX ) === true && str_ends_with( $file, \Nino\Backup\Seal::STUB_SUFFIX ) === true
	&& substr_count( $file, "\n" ) === 1 && preg_match( '/^[A-Za-z0-9+\/]+={0,2}$/D', substr( $file, strlen( \Nino\Backup\Seal::STUB_PREFIX ), -strlen( \Nino\Backup\Seal::STUB_SUFFIX ) ) ) === 1 );
check( '...a header of 24 bytes: the magic and the version, the size of a chunk, the nonce', substr( $binary, 0, 8 ) === "NinoBak\x02" && unpack( 'N', substr( $binary, 8, 4 ) )[1] === $chunk && strlen( substr( $binary, 12, 12 ) ) === 12 );
check( '...and chunks that are the bytes of the archive and a tag of 16 bytes each, the last one the rest', strlen( $binary ) === 24 + 3 * 16 + strlen( $plain ) && $total[2 * $chunk + 5] === 24 + 3 * 16 + 2 * $chunk + 5 );
check( '...the nonce is new for every file', substr( sealBinary( $written( $plain, $key ) ), 12, 12 ) !== substr( $binary, 12, 12 ) );
check( 'the same archive is sealed to other bytes every time, and to one file that is no copy of the other', sealBinary( $written( $plain, $key ) ) !== $binary );
check( 'the stub is one string in the kernel, the module and the workbench shell', ( new ReflectionClassConstant( $seal, 'STUB_PREFIX' ) )->getValue() === "<?php http_response_code(403); exit; return '"
	&& ( new ReflectionClassConstant( $seal, 'STUB_SUFFIX' ) )->getValue() === "';\n" && \Nino\Modules\Backups::STUB_PREFIX === \Nino\Backup\Seal::STUB_PREFIX && \Nino\Admin\Recovery::STUB_PREFIX === \Nino\Backup\Seal::STUB_PREFIX
	&& \Nino\Modules\Backups::STUB_SUFFIX === \Nino\Backup\Seal::STUB_SUFFIX && \Nino\Admin\Recovery::STUB_SUFFIX === \Nino\Backup\Seal::STUB_SUFFIX );
check( 'a file is told from format 1 by what its first bytes are, and from none by the stub', \Nino\Backup\Seal::format( $dir. '/mine.php' ) === 2 && \Nino\Backup\Seal::format( $dir. '/plain' ) === null
	&& file_put_contents( $dir. '/old.php', ninoSealedOld( 'x', $key ) ) > 0 && \Nino\Backup\Seal::format( $dir. '/old.php' ) === 1 );

// Format 1 is read: whole, and to the same bytes
$oldPlain = random_bytes( 5000 );
file_put_contents( $dir. '/old.php', ninoSealedOld( $oldPlain, $key ) );
check( 'an archive of format 1 is opened to the bytes it holds', \Nino\Backup\Seal::open( $dir. '/old.php', $key, $dir. '/old.out', $head ) === null && file_get_contents( $dir. '/old.out' ) === $oldPlain && $head === substr( $oldPlain, 0, 2 ) );

check( 'a file of the writer is read with no other key, and no key that is not 32 bytes', sealOpen( $written( $plain, $key ), random_bytes( 32 ) ) === \Nino\Backup\Seal::AUTH && sealOpen( $written( $plain, $key ), 'short' ) === \Nino\Backup\Seal::AUTH );

// What the sizes of the layout are bound to
check( 'the size of a chunk the writer takes is bound, and so is the key', ( static function() use ( $dir, $key ): bool {
	$failures = 0;
	foreach( [ [ $key, \Nino\Backup\Seal::CHUNK_MIN - 1 ], [ $key, \Nino\Backup\Seal::CHUNK_MAX + 1 ], [ $key, 0 ], [ 'short', \Nino\Backup\Seal::CHUNK ] ] as [ $k, $size ] ) {
		try {
			foreach( \Nino\Backup\Seal::pieces( $dir. '/plain', $k, $size ) as $ignored );
		}
		catch( \RuntimeException ) {
			$failures++;
		}
	}
	return $failures === 4;
} )() === true );

check( 'an archive that cannot be read is no archive: the writer says so and the reader says so', ( static function() use ( $dir, $key ): bool {
	$said = 0;
	try { foreach( \Nino\Backup\Seal::pieces( $dir. '/not-there', $key ) as $ignored ); } catch( \RuntimeException ) { $said++; }
	try { \Nino\Backup\Seal::open( $dir. '/not-there', $key ); } catch( \RuntimeException ) { $said++; }
	return $said === 2;
} )() === true );


echo "\nA file damaged\n";

// The file as it is written with chunks of 1024 bytes, so that there are many; every damage is made to the bytes under the base64
$plain 	= random_bytes( 1024 * 6 + 300 );
$good 	= ninoSealed( $plain, $key, 1024 );
$binary = sealBinary( $good );
$other 	= sealBinary( ninoSealed( random_bytes( 1024 * 6 + 300 ), $key, 1024 ) );
$header = substr( $binary, 0, 24 );
$slice 	= static fn( string $bytes, int $number ): string => substr( $bytes, 24 + $number * 1040, 1040 );
$flip 	= static function( string $bytes, int $at ): string { $bytes[$at] = chr( ord( $bytes[$at] ) ^ 0x01 ); return $bytes; };
$chunks = static fn( string $bytes ): array => str_split( substr( $bytes, 24 ), 1040 );
$join 	= static fn( string $bytes, array $parts ): string => substr( $bytes, 0, 24 ). implode( '', $parts );
$parts 	= $chunks( $binary );

$f1 			= ninoSealedOld( 'whatever an archive of the first format holds', $key );

// An empty final chunk after a full one: both tags hold, and it is not what the writer makes
$nonce 	= substr( $binary, 12, 12 );
$headerEmpty = "NinoBak\x02". pack( 'N', 1024 ). $nonce;
$canon 	= static function( string $partA, string $partB ) use ( $key, $headerEmpty, $nonce ): string {
	$body = $headerEmpty;
	foreach( [ $partA, $partB ] as $number => $part ) {
		$tag = '';
		$cipher = openssl_encrypt( $part, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, ninoNonce( $nonce, $number ), $tag, $headerEmpty. pack( 'N', $number ). ( $number === 1 ? "\x01" : "\x00" ), 16 );
		$body .= $cipher. $tag;
	}
	return $body;
};

$attacks = [
	'a bit of the magic'													=> $flip( $binary, 3 ),
	'a bit of the version'												=> $flip( $binary, 7 ),
	'a bit of the size of a chunk'								=> $flip( $binary, 10 ),
	'a bit of the nonce'													=> $flip( $binary, 12 ),
	'a bit of the first chunk'										=> $flip( $binary, 24 + 10 ),
	'a bit of a chunk in the middle'							=> $flip( $binary, 24 + 3 * 1040 + 500 ),
	'a bit of the last chunk'											=> $flip( $binary, strlen( $binary ) - 20 ),
	'a bit of the tag of the first chunk'					=> $flip( $binary, 24 + 1024 + 3 ),
	'a bit of the tag of the last chunk'					=> $flip( $binary, strlen( $binary ) - 1 ),
	'two chunks of the middle changed places'			=> $join( $binary, [ $parts[0], $parts[2], $parts[1], $parts[3], $parts[4], $parts[5], $parts[6] ] ),
	'the first chunk and the one after it'				=> $join( $binary, [ $parts[1], $parts[0], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6] ] ),
	'a chunk twice'																=> $join( $binary, [ $parts[0], $parts[1], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6] ] ),
	'a chunk of the middle left out'							=> $join( $binary, [ $parts[0], $parts[1], $parts[3], $parts[4], $parts[5], $parts[6] ] ),
	'the first chunk left out'										=> $join( $binary, array_slice( $parts, 1 ) ),
	'the last chunk left out, an end on a boundary'	=> $join( $binary, array_slice( $parts, 0, 6 ) ),
	'the last two chunks left out'								=> $join( $binary, array_slice( $parts, 0, 5 ) ),
	'the end cut in the last chunk'								=> substr( $binary, 0, -5 ),
	'the end cut in a chunk of the middle'				=> substr( $binary, 0, 24 + 4 * 1040 + 100 ),
	'the end cut to a tag'												=> substr( $binary, 0, 24 + 16 ),
	'the end cut inside the header'								=> substr( $binary, 0, 20 ),
	'nothing after the header'										=> $header,
	'a byte after the end'												=> $binary. 'x',
	'sixteen bytes after the end'									=> $binary. str_repeat( "\0", 16 ),
	'a whole chunk after the end'									=> $binary. $parts[0],
	'a chunk of the size of the last and more after the end'	=> $binary. random_bytes( 1040 ),
	'a chunk of another file of the same key in the middle'	=> $join( $binary, [ $parts[0], $parts[1], $slice( $other, 2 ), $parts[3], $parts[4], $parts[5], $parts[6] ] ),
	'the first half of this file and the second of another'	=> $join( $binary, array_merge( array_slice( $parts, 0, 3 ), array_slice( $chunks( $other ), 3 ) ) ),
	'the header of another file on the chunks of this'			=> substr( $other, 0, 24 ). substr( $binary, 24 ),
	'the header of this file on the chunks of another'			=> $header. substr( $other, 24 ),
	'a file of format 1 behind a header of format 2'				=> $header. sealBinary( $f1 ),
	'a file of format 1 behind the magic alone'							=> "NinoBak\x02". sealBinary( $f1 ),
	'a size of a chunk of zero'										=> "NinoBak\x02". pack( 'N', 0 ). substr( $binary, 12 ),
	'a size of a chunk of four billion'						=> "NinoBak\x02". pack( 'N', 4294967295 ). substr( $binary, 12 ),
	'a size of a chunk below the least'						=> "NinoBak\x02". pack( 'N', 1023 ). substr( $binary, 12 ),
	'a size of a chunk above the most'						=> "NinoBak\x02". pack( 'N', 4194305 ). substr( $binary, 12 ),
	'a size of a chunk that is no other than the tags are made for' => "NinoBak\x02". pack( 'N', 2048 ). substr( $binary, 12 ),
	'an empty last chunk after a full one'				=> $canon( random_bytes( 1024 ), '' ),
	'nothing at all'															=> '',
];

$refusedAll = true;
$codes 			= [];
foreach( $attacks as $label => $bytes ) {
	$result = sealOpen( sealFile( $bytes ), $key );
	$codes[$label] = $result;
	if( $result === null )
		$refusedAll = false;
}

check( 'the unharmed file is opened, to the archive it was made from', sealOpen( $good, $key ) === null && ninoUnsealed( $good, $key ) === $plain );
check( 'every one of the '. count( $attacks ). ' damages of a file is refused with a code, and no archive comes out of it', $refusedAll === true );
check( '...a bit anywhere, a chunk moved, repeated or left out, an end cut and a tail added end in a tag that does not hold', ( static function() use ( $codes ): bool {
	foreach( [ 'a bit of the nonce', 'a bit of the first chunk', 'a bit of a chunk in the middle', 'a bit of the last chunk', 'a bit of the tag of the first chunk', 'a bit of the tag of the last chunk', 'two chunks of the middle changed places',
		'a chunk twice', 'a chunk of the middle left out', 'the first chunk left out', 'the last chunk left out, an end on a boundary', 'the last two chunks left out', 'the end cut in the last chunk', 'a byte after the end',
		'a whole chunk after the end', 'a chunk of another file of the same key in the middle', 'the first half of this file and the second of another', 'the header of another file on the chunks of this', 'a bit of the size of a chunk',
		'a size of a chunk that is no other than the tags are made for' ] as $label )
		if( ( $codes[$label] ?? '' ) !== \Nino\Backup\Seal::AUTH )
			return false;
	return true;
} )() === true );
check( '...a size of a chunk that no writer makes is a header that is none, and a file that stops where a chunk cannot is no file', $codes['a size of a chunk of zero'] === \Nino\Backup\Seal::HEADER_BAD && $codes['a size of a chunk of four billion'] === \Nino\Backup\Seal::HEADER_BAD
	&& $codes['a size of a chunk below the least'] === \Nino\Backup\Seal::HEADER_BAD && $codes['a size of a chunk above the most'] === \Nino\Backup\Seal::HEADER_BAD
	&& $codes['nothing after the header'] === \Nino\Backup\Seal::SHAPE && $codes['the end cut inside the header'] === \Nino\Backup\Seal::SHAPE && $codes['an empty last chunk after a full one'] === \Nino\Backup\Seal::SHAPE );
check( 'a file of format 1 that has a header of format 2 put before it is read as format 2, and no tag holds', $codes['a file of format 1 behind a header of format 2'] === \Nino\Backup\Seal::AUTH );
check( 'the key that is not the key opens nothing', sealOpen( $good, random_bytes( 32 ) ) === \Nino\Backup\Seal::AUTH );

// The stub around the bytes
$stubs = [
	'a prefix that is none'							=> 'x'. $good,
	'no suffix'													=> substr( $good, 0, -strlen( "';\n" ) ),
	'a suffix of another kind'					=> substr( $good, 0, -strlen( "';\n" ) ). "';?>\n",
	'a line break inside the base64'		=> substr_replace( $good, "\n", strlen( \Nino\Backup\Seal::STUB_PREFIX ) + 40, 0 ),
	'a space inside the base64'					=> substr_replace( $good, ' ', strlen( \Nino\Backup\Seal::STUB_PREFIX ) + 40, 0 ),
	'a character that is no base64'			=> substr_replace( $good, '*', strlen( \Nino\Backup\Seal::STUB_PREFIX ) + 40, 1 ),
	'a quote inside the base64'					=> substr_replace( $good, "'.phpinfo().'", strlen( \Nino\Backup\Seal::STUB_PREFIX ) + 40, 4 ),
	'padding in the middle'							=> substr_replace( $good, '==', strlen( \Nino\Backup\Seal::STUB_PREFIX ) + 40, 2 ),
	'a length that is none of base64'		=> substr( $good, 0, -strlen( "';\n" ) - 1 ). "';\n",
	'the stub alone'										=> \Nino\Backup\Seal::STUB_PREFIX. \Nino\Backup\Seal::STUB_SUFFIX,
];
$stubbed = true;
foreach( $stubs as $bytes )
	if( sealOpen( $bytes, $key ) === null )
		$stubbed = false;
check( 'a stub that is damaged or has anything but base64 inside is refused: '. count( $stubs ). ' ways', $stubbed === true );

// The number of a chunk is four bytes, and a file has no more chunks than that can name
$four = $written( random_bytes( 4 * 1024 ), $key, 1024 );
file_put_contents( $dir. '/four.php', $four );
check( 'a file of four chunks is read where three are the most a file may have and is refused: more chunks than a number can name', \Nino\Backup\Seal::open( $dir. '/four.php', $key, null, $head, 4 ) === null && \Nino\Backup\Seal::open( $dir. '/four.php', $key, null, $head, 3 ) === \Nino\Backup\Seal::TOO_MANY );
check( '...and the writer ends where the number is spent, before it writes a chunk it cannot number', ( static function() use ( $dir, $key ): bool {
	try { foreach( \Nino\Backup\Seal::pieces( $dir. '/plain', $key, 1024, 3 ) as $ignored ); return false; }
	catch( \RuntimeException ) { return strlen( (string) file_get_contents( $dir. '/plain' ) ) > 3 * 1024; }
} )() === true );
check( 'the number of the nonce wraps at 96 bits and the writer and the reader of the suite add alike', ninoNonce( str_repeat( "\xff", 12 ), 1 ) === str_repeat( "\0", 12 ) && ninoNonce( str_repeat( "\0", 8 ). "\xff\xff\xff\xff", 1 ) === str_repeat( "\0", 7 ). "\x01". str_repeat( "\0", 4 ) );
check( 'what a chunk is sealed with is the number of it: the nonces of the first chunks are the nonce and the numbers after it', ( static function() use ( $binary ): bool {
	$base = substr( $binary, 12, 12 );
	return ninoNonce( $base, 0 ) === $base && ninoNonce( $base, 1 ) !== $base && ninoNonce( $base, 256 ) !== ninoNonce( $base, 1 );
} )() === true );

// An archive of no bytes is one a file may be made of, and no archive of a restore
file_put_contents( $dir. '/empty.php', $written( '', $key ) );
check( 'an archive of no bytes is a file with one chunk that is empty, and is opened to no bytes - by the reader of the suite too', \Nino\Backup\Seal::open( $dir. '/empty.php', $key, null, $head ) === null && $head === '' && ninoUnsealed( (string) file_get_contents( $dir. '/empty.php' ), $key ) === '' );


echo "\nThe three doors\n";

// A project, its archive and what each door does with each damage: the upload of the panel, the restore of an archive of the list, the restore of a file at recovery.php
$appData 	= sealProject( 'seal-doors' );
$doorsProject = $appData;
$sandbox 	= ninoSandboxDir( $appData );
$backups 	= $sandbox. '/private/.backups';
$rawKey 	= (string) base64_decode( (string) $appData['/nino/backup/key'], true );

\Nino\Filesystem::putFileContent( $appData, '/text/probe.php', [ 'a' => 'archived' ] );
@mkdir( $sandbox. '/private/templates', 0755, true );
@mkdir( $sandbox. '/private/assets', 0755, true );
file_put_contents( $sandbox. '/private/templates/page-probe.tpl', "<p>archived</p>\n" );
file_put_contents( $sandbox. '/private/assets/style.css', "body { color: black }\n" );
file_put_contents( $sandbox. '/private/assets/noise.bin', random_bytes( 12000 ) );

$tar = $sandbox. '/doors.tar';
\Nino\Backup::pack( $appData, \Nino\Backup::manifest( $appData ), $tar );
$gz 	= (string) file_get_contents( $tar. '.gz' );
$real = ninoSealed( $gz, $rawKey, 1024 );
$realBinary = sealBinary( $real );
$realOther  = sealBinary( ninoSealed( $gz, $rawKey, 1024 ) );
$realParts  = str_split( substr( $realBinary, 24 ), 1040 );
$count 			= count( $realParts );

check( 'the archive of the three doors has chunks enough that every damage below changes it: '. $count. ' chunks', $count >= 8 && $count === (int) ceil( ( strlen( $realBinary ) - 24 ) / 1040 ) );

// The state of the project the archive is not, to be restored over
\Nino\Filesystem::putFileContent( $appData, '/text/probe.php', [ 'a' => 'live' ] );
file_put_contents( $sandbox. '/private/templates/page-probe.tpl', "<p>live</p>\n" );

$doors = [
	'a bit of the middle'														=> $flip( $realBinary, 24 + intdiv( $count, 2 ) * 1040 + 7 ),
	'a bit of the last chunk'												=> $flip( $realBinary, strlen( $realBinary ) - 30 ),
	'a bit of the nonce'														=> $flip( $realBinary, 15 ),
	'two chunks changed places'											=> substr( $realBinary, 0, 24 ). $realParts[1]. $realParts[0]. implode( '', array_slice( $realParts, 2 ) ),
	'a chunk twice'																	=> substr( $realBinary, 0, 24 ). $realParts[0]. implode( '', $realParts ),
	'a chunk left out'															=> substr( $realBinary, 0, 24 ). implode( '', array_slice( $realParts, 0, 1 ) ). implode( '', array_slice( $realParts, 2 ) ),
	'the end cut on a chunk'												=> substr( $realBinary, 0, 24 ). implode( '', array_slice( $realParts, 0, $count - 1 ) ),
	'the end cut inside a chunk'										=> substr( $realBinary, 0, -9 ),
	'a tail of garbage'															=> $realBinary. random_bytes( 64 ),
	'a chunk of another file of the same key'				=> substr( $realBinary, 0, 24 ). $realParts[0]. substr( $realOther, 24 + 1040, 1040 ). implode( '', array_slice( $realParts, 2 ) ),
	'a file of format 1 behind a header of format 2'	=> substr( $realBinary, 0, 24 ). sealBinary( ninoSealedOld( $gz, $rawKey ) ),
	'a size of a chunk of zero'											=> "NinoBak\x02". pack( 'N', 0 ). substr( $realBinary, 12 ),
	'a size of a chunk of four billion'							=> "NinoBak\x02". pack( 'N', 4294967295 ). substr( $realBinary, 12 ),
	'no chunk'																			=> substr( $realBinary, 0, 24 ),
	'an archive of no bytes'												=> sealBinary( ninoSealed( '', $rawKey ) ),
	'an archive of the first format that holds no bytes'	=> sealBinary( ninoSealedOld( '', $rawKey ) ),
	'an archive that is no gzip'										=> sealBinary( ninoSealed( 'this is not a gzip stream, and it is sealed with the right key', $rawKey ) ),
	'another key'																		=> sealBinary( ninoSealed( $gz, random_bytes( 32 ) ) ),
];

$put = static function( array &$appData, string $name, string $file ): array {
	$path = ninoSandboxDir( $appData ). '/upload-'. bin2hex( random_bytes( 4 ) ). '.bin';
	file_put_contents( $path, $file );
	$_FILES['file'] = [ 'name' => $name, 'type' => 'application/octet-stream', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen( $file ) ];
	$result = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiUpload' ] );
	@unlink( $path );
	return $result;
};

check( 'and no damage of the doors is the file itself', count( array_filter( $doors, static fn( string $bytes ): bool => $bytes === $realBinary ) ) === 0 );

$treeBefore 	= sealTree( $sandbox );
$listBefore 	= glob( $backups. '/*' ) ?: [];
// What the runs of THIS project leave in the temporary directory, which is the mark of the project in the name: the directory is shared with whatever else runs on the machine,
// and a backup of another project at the same time is none of this suite's
$tempMark 		= ( new ReflectionMethod( \Nino\Backup::class, '_tempId' ) )->invokeArgs( null, [ &$appData ] );
$tempFiles 		= static fn(): array => array_merge( glob( sys_get_temp_dir(). '/ninobackup-'. $tempMark. '-*' ) ?: [], glob( sys_get_temp_dir(). '/ninorestore-'. $tempMark. '-*' ) ?: [], glob( sys_get_temp_dir(). '/ninosnapshot-'. $tempMark. '-*' ) ?: [] );
$tempBefore 	= $tempFiles();
$paths 				= static fn( string $text ): bool => str_contains( $text, $sandbox ) === true || str_contains( $text, sys_get_temp_dir() ) === true || str_contains( $text, $dir ) === true;
$unharmed 		= true;
$doorResults 	= [ 'upload' => 0, 'list' => 0, 'file' => 0 ];
$nopaths 			= true;

foreach( $doors as $label => $binaryOfFile ) {

	$file = sealFile( $binaryOfFile );

	// The upload of the panel
	[ $status, $body ] = $put( $appData, 'backup-example.com-2025-03-04-050607-manual.php', $file );
	if( $status >= 400 && $status < 500 && ( $body['code'] ?? '' ) !== '' )
		$doorResults['upload']++;
	if( $paths( json_encode( $body ) ) === true )
		$nopaths = false;

	// The restore of an archive that is in the list
	file_put_contents( $backups. '/2025-03-04-050607-manual.php', $file );
	[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2025-03-04-050607-manual' ] );
	if( $status === 422 && ( $body['code'] ?? '' ) !== '' )
		$doorResults['list']++;
	if( $paths( json_encode( $body ) ) === true )
		$nopaths = false;
	unlink( $backups. '/2025-03-04-050607-manual.php' );

	// The restore of a file at recovery.php, with the key that was typed
	$path = $sandbox. '/upload-'. bin2hex( random_bytes( 4 ) ). '.bin';
	file_put_contents( $path, $file );
	$notice = null;
	$result = \Nino\Modules\Backups\Admin::restoreFile( $appData, $path, $rawKey, $notice );
	unlink( $path );
	if( is_array( $result ) === true && in_array( $result[0], [ 400, 422 ], true ) === true && ( $result[2] ?? '' ) !== '' )
		$doorResults['file']++;
	if( is_array( $result ) === true && $paths( (string) json_encode( $result ) ) === true )
		$nopaths = false;

	if( sealTree( $sandbox ) !== $treeBefore || ( glob( $backups. '/*' ) ?: [] ) !== $listBefore )
		$unharmed = false;
}

check( 'the upload of the panel refuses each of the '. count( $doors ). ' damages with a client error and a code', $doorResults['upload'] === count( $doors ) );
check( 'a restore of an archive of the list refuses each with a 422 and a code', $doorResults['list'] === count( $doors ) );
check( 'a restore of a file at recovery.php with the key typed refuses each with a 400 or a 422 and a code', $doorResults['file'] === count( $doors ) );
check( '...and after every one the project is as it was - the hash of every file of it is the same - and the list of the archives, with no safety snapshot made', $unharmed === true && glob( $backups. '/pre-restore-*' ) === [] );
check( '...none of the answers names a path of the project or of the temporary directory', $nopaths === true );
check( '...and no file of a restore stays in the temporary directory', $tempFiles() === $tempBefore );

// The same file, unharmed, goes through all three doors: what the refusals above are is the damage and nothing else
[ $status, $body ] = $put( $appData, 'backup-example.com-2025-03-04-050607-manual.php', $real );
check( 'the unharmed file is taken by the upload', $status === 200 && ( $body['id'] ?? '' ) === '2025-03-04-050607-manual' && is_file( $backups. '/2025-03-04-050607-manual.php' ) === true );
$path = $sandbox. '/upload-ok.bin';
file_put_contents( $path, $real );
$notice = null;
$result = \Nino\Modules\Backups\Admin::restoreFile( $appData, $path, $rawKey, $notice );
unlink( $path );
check( 'the unharmed file is restored at recovery.php: the project is the archive, byte for byte, and a snapshot of what it was is kept', $result === true
	&& \Nino\Filesystem::getFileContent( $appData, '/text/probe.php', [] ) === [ 'a' => 'archived' ] && (string) file_get_contents( $sandbox. '/private/templates/page-probe.tpl' ) === "<p>archived</p>\n"
	&& ( glob( $backups. '/pre-restore-*' ) ?: [] ) !== [] );
\Nino\Filesystem::putFileContent( $appData, '/text/probe.php', [ 'a' => 'live' ] );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2025-03-04-050607-manual' ] );
check( 'and by the restore of the list', $status === 200 && \Nino\Filesystem::getFileContent( $appData, '/text/probe.php', [] ) === [ 'a' => 'archived' ] );


$doorsProject 		= $appData;
$sandboxOfDoors = $sandbox;
$backupsOfDoors = $backups;
$rawKeyOfDoors 	= $rawKey;
\Nino\Auth::loginUser( $doorsProject, 'dev@example.com', 'correct horse battery staple' );

echo "\nA round trip\n";

// A project with files of many sizes - a few chunks of the writer among them - made into an archive by the product and restored: the files are the files
$appData 	= sealProject( 'seal-trip' );
$sandbox 	= ninoSandboxDir( $appData );
$backups 	= $sandbox. '/private/.backups';
@mkdir( $sandbox. '/private/assets/nested', 0755, true );
@mkdir( $sandbox. '/public/images/elements/news', 0755, true );

foreach( [ 'a.bin' => 0, 'b.bin' => 1, 'c.bin' => 255, 'd.bin' => 262144, 'nested/e.bin' => 262145, 'nested/f.bin' => 3 * 262144 + 17 ] as $name => $size )
	file_put_contents( $sandbox. '/private/assets/'. $name, $size === 0 ? '' : random_bytes( $size ) );
file_put_contents( $sandbox. '/public/images/elements/news/p.png', random_bytes( 70000 ) );
\Nino\Filesystem::putFileContent( $appData, '/text/trip.php', [ 'a' => 'text with ümläuts and a quote \' in it' ] );

// What a run that was killed left in the directory of the archives, a day old, and what another that goes on has there now
file_put_contents( $backups. '/2026-01-01.php.0123456789ab.tmp', 'x' );
file_put_contents( $backups. '/2026-01-02.php.0123456789ab.tmp', 'x' );
touch( $backups. '/2026-01-01.php.0123456789ab.tmp', time() - 90000 );

$before = sealTree( $sandbox );
$id 		= \Nino\Modules\Backups::now( $appData );
check( 'an archive is made of it, in format 2', is_string( $id ) === true && \Nino\Backup\Seal::format( $backups. '/'. $id. '.php' ) === 2 );
check( 'a run sweeps the temporary file of an archive that a run left a day ago, and leaves the one of a run that may go on', is_file( $backups. '/2026-01-01.php.0123456789ab.tmp' ) === false && is_file( $backups. '/2026-01-02.php.0123456789ab.tmp' ) === true );
unlink( $backups. '/2026-01-02.php.0123456789ab.tmp' );

foreach( glob( $sandbox. '/private/assets/*.bin' ) ?: [] as $file )
	file_put_contents( $file, 'changed' );
file_put_contents( $sandbox. '/private/assets/nested/e.bin', 'changed' );
unlink( $sandbox. '/public/images/elements/news/p.png' );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $id ] );
check( 'restored, the project is what it was: the hash of every file of it is the same as before the archive was made', $status === 200 && sealTree( $sandbox ) === $before );

// The archive that was made is read by the reader of the suite as well, and is a tar.gz of the names of the project
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
$plainOfFile = ninoUnsealed( (string) file_get_contents( $backups. '/'. $id. '.php' ), (string) base64_decode( (string) $appData['/nino/backup/key'], true ) );
$names = [];
foreach( new RecursiveIteratorIterator( new PharData( ( static function() use ( $plainOfFile, $dir ): string { file_put_contents( $dir. '/trip.tar.gz', $plainOfFile ); return $dir. '/trip.tar.gz'; } )() ) ) as $entry )
	$names[] = substr( $entry->getPathname(), strlen( 'phar://'. $dir. '/trip.tar.gz/' ) );
check( 'the archive holds the files of the project by their names, and the note of the state it comes from', in_array( 'assets/nested/f.bin', $names, true ) && in_array( 'images/elements/news/p.png', $names, true ) && in_array( 'nino.php', $names, true ) && in_array( 'config.php', $names, true ) );

// What a project of an older Nino has: an archive of format 1 that the code of that Nino wrote, with the key of that project
echo "\nAn archive of format 1\n";

$fixture 	= json_decode( (string) file_get_contents( __DIR__. '/fixtures/backup-format1/expected.json' ), true );
$old 			= sealProject( 'seal-old', $fixture['key'] );
$oldDir 	= ninoSandboxDir( $old );

copy( __DIR__. '/fixtures/backup-format1/'. $fixture['id']. '.php', $oldDir. '/private/.backups/'. $fixture['id']. '.php' );
check( 'the archive that the code of format 1 wrote is of format 1, and is in the list', \Nino\Backup\Seal::format( $oldDir. '/private/.backups/'. $fixture['id']. '.php' ) === 1 && in_array( $fixture['id'], \Nino\Modules\Backups\Admin::dates( $old ), true ) );

[ $status, $body ] = ninoCall( $old, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $fixture['id'] ] );
$same = true;
foreach( $fixture['files'] as $relative => $sha )
	if( is_file( $oldDir. '/'. $relative ) === false || sha1_file( $oldDir. '/'. $relative ) !== $sha )
		$same = false;
check( 'it is restored: every file of the project it was made from is back, byte for byte', $status === 200 && $same === true );

// And uploaded, and opened at recovery.php with its key
$other = sealProject( 'seal-old-upload', $fixture['key'] );
[ $status, $body ] = $put( $other, 'backup-example.com-'. $fixture['id']. '.php', (string) file_get_contents( __DIR__. '/fixtures/backup-format1/'. $fixture['id']. '.php' ) );
check( 'it is taken by the upload of a project with that key', $status === 200 && ( $body['id'] ?? '' ) === $fixture['id'] );
$third = sealProject( 'seal-old-file' );
$notice = null;
$result = \Nino\Modules\Backups\Admin::restoreFile( $third, __DIR__. '/fixtures/backup-format1/'. $fixture['id']. '.php', (string) base64_decode( $fixture['key'], true ), $notice );
$same = true;
foreach( $fixture['files'] as $relative => $sha )
	if( is_file( ninoSandboxDir( $third ). '/'. $relative ) === false || sha1_file( ninoSandboxDir( $third ). '/'. $relative ) !== $sha )
		$same = false;
check( 'and opened at recovery.php by a project that has none of its own with the key typed: the same files', $result === true && $same === true );

[ $status, $body ] = $put( $appData, 'backup-example.com-'. $fixture['id']. '.php', (string) file_get_contents( __DIR__. '/fixtures/backup-format1/'. $fixture['id']. '.php' ) );
check( 'with the key of another project it is refused as an archive that cannot be opened with it', $status === 422 && ( $body['code'] ?? '' ) === 'backups_upload_key' );


echo "\nThe unpacking\n";

/** One entry of a tar by hand: the header and the data in blocks */
$tarEntry = static function( string $name, string $content, string $type = '0', ?int $size = null, ?string $prefix = null ): string {

	$header = str_pad( $name, 100, "\0" ). sprintf( '%07o', 0644 ). "\0". sprintf( '%07o', 0 ). "\0". sprintf( '%07o', 0 ). "\0". sprintf( '%011o', $size ?? strlen( $content ) ). "\0". sprintf( '%011o', 0 ). "\0". '        '. $type. str_repeat( "\0", 100 ). "ustar\0". '00'. str_repeat( "\0", 32 ). str_repeat( "\0", 32 ). str_repeat( "\0", 16 ). str_pad( $prefix ?? '', 155, "\0" ). str_repeat( "\0", 12 );
	$sum 		= array_sum( array_map( 'ord', str_split( $header ) ) );

	return substr( $header, 0, 148 ). sprintf( '%06o', $sum ). "\0 ". substr( $header, 156 ). $content. str_repeat( "\0", ( 512 - strlen( $content ) % 512 ) % 512 );
};
$tarFile = static function( string $tar ) use ( $dir ): string {
	$path = $dir. '/u-'. bin2hex( random_bytes( 4 ) ). '.tar.gz';
	file_put_contents( $path, gzencode( $tar. str_repeat( "\0", 1024 ) ) );
	return $path;
};
$unpack = static function( string $tar ) use ( $dir, $tarFile ): array {
	$into = $dir. '/into-'. bin2hex( random_bytes( 4 ) );
	mkdir( $into );
	$why = \Nino\Backup::unpack( $tarFile( $tar ), $into );
	return [ $why, $into ];
};
$tree = static function( string $root ): array {
	$files = [];
	foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST ) as $item )
		$files[substr( $item->getPathname(), strlen( $root ) + 1 )] = $item->isDir() === true ? '/' : sha1_file( $item->getPathname() );
	ksort( $files );
	return $files;
};

// A real archive: the unpacking of PharData and this one make the same tree
$big = random_bytes( 2 * 1048576 + 333 );
$src = $dir. '/real';
mkdir( $src. '/templates/crm-portal', 0755, true );
file_put_contents( $src. '/config.php', "<?php return [];\n" );
file_put_contents( $src. '/templates/crm-portal/list.tpl', "list\n" );
file_put_contents( $src. '/templates/big.tpl', $big );
file_put_contents( $src. '/templates/empty.tpl', '' );
$pack = new PharData( $dir. '/real.tar' );
$pack->buildFromDirectory( $src );
$pack->compress( Phar::GZ );
unset( $pack );
unlink( $dir. '/real.tar' );
mkdir( $dir. '/by-us' );
$why = \Nino\Backup::unpack( $dir. '/real.tar.gz', $dir. '/by-us' );
check( 'an archive is unpacked to the tree it was made from, a file of two megabytes and one of no bytes among them', $why === null && $tree( $dir. '/by-us' ) === $tree( $src ) && $tree( $dir. '/by-us' )['templates/big.tpl'] === sha1( $big ) );

// A name of more than a hundred characters is split over the two fields of the header and is one name again
$long = 'templates/'. str_repeat( 'd', 60 ). '/'. str_repeat( 'e', 60 ). '/page.tpl';
[ $why, $into ] = $unpack( $tarEntry( str_repeat( 'e', 60 ). '/page.tpl', 'long', '0', null, 'templates/'. str_repeat( 'd', 60 ) ) );
check( 'a name in the two fields of the header is read as one', $why === null && is_file( $into. '/'. $long ) === true );

// What an archive may not do
$escape = $dir. '/escape';
mkdir( $escape );
$refusedNames = true;
foreach( [ '../outside.txt', 'templates/../../outside.txt', '/absolute.txt', 'templates//x.tpl', './x.tpl', 'templates/./x.tpl', "a\0b/c.tpl" ] as $name ) {
	$into = $escape. '/'. bin2hex( random_bytes( 3 ) );
	mkdir( $into );
	$why = \Nino\Backup::unpack( $tarFile( $tarEntry( $name, 'x' ) ), $into );
	if( $why === null || is_file( $escape. '/outside.txt' ) === true || is_file( $into. '/../outside.txt' ) === true || is_file( '/absolute.txt' ) === true )
		$refusedNames = false;
}
check( 'a name that leaves the directory or is none of plain segments is refused, and nothing is written outside', $refusedNames === true && is_file( $dir. '/outside.txt' ) === false );
[ $why ] = $unpack( $tarEntry( 'templates/x.tpl', '', '2' ) );
check( 'a link is refused', $why !== null );
[ $why ] = $unpack( $tarEntry( 'templates/x.tpl', '', '3' ) );
check( '...and a device', $why !== null );
[ $why ] = $unpack( $tarEntry( 'templates/x.tpl', str_repeat( 'x', 100 ), '0', 5000 ) );
check( 'a file that ends before its size says is refused', $why !== null );
$broken = $tarEntry( 'templates/x.tpl', 'x' );
$broken[300] = $broken[300] === 'a' ? 'b' : 'a';
$broken = $tarEntry( 'templates/y.tpl', 'y' ). substr_replace( $tarEntry( 'templates/x.tpl', 'x' ), 'x', 150, 1 );
[ $why ] = $unpack( $broken );
check( 'a header whose checksum does not hold is refused', $why !== null );
[ $why ] = $unpack( $tarEntry( 'templates', '', '5' ). $tarEntry( 'templates/x.tpl', 'x' ) );
check( 'a directory is made and a file below it', $why === null );
check( 'a file that is no tar is refused, and no path is in the answer', ( static function() use ( $dir ): bool {
	file_put_contents( $dir. '/notar.tar.gz', gzencode( str_repeat( 'this is not a tar archive at all. ', 100 ) ) );
	mkdir( $dir. '/into-notar' );
	$why = \Nino\Backup::unpack( $dir. '/notar.tar.gz', $dir. '/into-notar' );
	return $why !== null && str_contains( $why, $dir ) === false;
} )() === true );
check( 'and one that is no gzip', ( static function() use ( $dir ): bool {
	file_put_contents( $dir. '/nogz.tar.gz', 'plain bytes' );
	mkdir( $dir. '/into-nogz' );
	return \Nino\Backup::unpack( $dir. '/nogz.tar.gz', $dir. '/into-nogz' ) !== null;
} )() === true );

// A file the writer is in the middle of is no archive, whichever way the run ends
echo "\nA run that ends\n";
$partial = new ReflectionMethod( '\Nino\Modules\Backups', '_writeRawAtomic' );
$target = $dir. '/target.php';
$gen = static function(): Generator { yield 'first'; throw new RuntimeException( 'the pieces ended' ); };
try { $partial->invoke( null, $target, $gen() ); } catch( RuntimeException $e ) {}
$failing = static function(): Generator { yield 'first'; throw new RuntimeException( 'the pieces ended' ); };
try { \Nino\Admin\Admin::writeFileAtomic( $dir. '/snapshot.php', $failing() ); } catch( RuntimeException $e ) {}
check( 'the writer of the safety snapshot leaves no file either where the pieces fail in the middle', is_file( $dir. '/snapshot.php' ) === false && glob( $dir. '/snapshot.php.*.tmp' ) === [] );
check( 'pieces that fail in the middle leave no file: not the archive, not the temporary one', is_file( $target ) === false && glob( $dir. '/target.php.*.tmp' ) === [] );
$run = new stdClass();
$run->partial = '';
$halves = static function() use ( $run ): Generator { yield 'first'; check( 'the file that is being written is named in the record of the run, for the end that no catch sees', $run->partial !== '' && is_file( $run->partial ) === true ); yield 'second'; };
$partial->invoke( null, $target, $halves(), $run );
check( '...and is not, once it was renamed into place', $run->partial === '' && (string) file_get_contents( $target ) === 'firstsecond' );

// The end of a run that no catch sees: the file being written is removed with the other temporary files of the run
$ended = new stdClass();
$ended->partial = $dir. '/half.php.0123456789ab.tmp';
$ended->temp = '';
file_put_contents( $ended->partial, 'half an archive' );
( new ReflectionMethod( '\Nino\Modules\Backups', '_dropTemp' ) )->invoke( null, $ended );
check( 'the end of a run removes the file of the archive it was writing, and forgets it', is_file( $dir. '/half.php.0123456789ab.tmp' ) === false && $ended->partial === '' );

// A temporary file that a run left in the directory of the archives goes when it is a day old, and not before
@mkdir( $dir. '/swept', 0755, true );
file_put_contents( $dir. '/swept/2026-01-01.php.0123456789ab.tmp', 'x' );
file_put_contents( $dir. '/swept/upload-0123456789ab.tmp', 'x' );
file_put_contents( $dir. '/swept/2026-01-02.php.0123456789ab.tmp', 'x' );
file_put_contents( $dir. '/swept/2026-01-02.php', 'archive' );
touch( $dir. '/swept/2026-01-01.php.0123456789ab.tmp', time() - 90000 );
touch( $dir. '/swept/upload-0123456789ab.tmp', time() - 90000 );
( new ReflectionMethod( '\Nino\Modules\Backups', '_sweepPartial' ) )->invoke( null, $dir. '/swept' );
check( 'a temporary file of a day is swept from the directory of the archives, a fresh one and the archives are left', is_file( $dir. '/swept/2026-01-01.php.0123456789ab.tmp' ) === false && is_file( $dir. '/swept/upload-0123456789ab.tmp' ) === false
	&& is_file( $dir. '/swept/2026-01-02.php.0123456789ab.tmp' ) === true && is_file( $dir. '/swept/2026-01-02.php' ) === true );


echo "\nMemory\n";

// Data of 64 MB that does not compress, in a process that may have 16: the backup, the upload and the restore of it
$command = escapeshellarg( PHP_BINARY ). ' -d memory_limit=16M -d sys_temp_dir='. escapeshellarg( $dir ). ' '. escapeshellarg( __FILE__ ). ' child 64 2>&1';
$output 	= (string) shell_exec( $command );
check( 'a project of 64 MB that does not compress is backed up, uploaded and restored in a process with a memory limit of 16 MB, byte for byte', str_contains( $output, 'survived' ) === true );
if( str_contains( $output, 'survived' ) === false )
	echo '        '. substr( trim( $output ), -300 ). "\n";

// The record of the sizes, for whoever reads the numbers
check( 'the chunk is what the memory is measured in: a run holds a few of them', \Nino\Backup\Seal::CHUNK === 262144 && \Nino\Backup\Seal::CHUNK_MIN <= \Nino\Backup\Seal::CHUNK && \Nino\Backup\Seal::CHUNK <= \Nino\Backup\Seal::CHUNK_MAX );


echo "\nThe chunk counter, the header, the end of a tar\n";

// A fixed vector, made by another implementation (python, cryptography): a nonce that carries across four bytes into a fifth where the
// third chunk is sealed, three chunks of 1024, 1024 and 452 bytes
$vectorKey 		= implode( '', array_map( 'chr', range( 0, 31 ) ) );
$vectorPlain 	= implode( '', array_map( static fn( int $i ): string => chr( ( $i * 7 + 3 ) % 256 ), range( 0, 2499 ) ) );
$vectorFile 	= "<?php http_response_code(403); exit; return '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';\n";
file_put_contents( $dir. '/vector.php', $vectorFile );
check( 'a file that another implementation made, with a nonce that carries across the bytes of its counter, is opened to the bytes it was made from', \Nino\Backup\Seal::open( $dir. '/vector.php', $vectorKey, $dir. '/vector.out', $head ) === null && file_get_contents( $dir. '/vector.out' ) === $vectorPlain );
check( '...and the reader of the suite opens it, and writes it byte for byte when it is given that nonce', ninoUnsealed( $vectorFile, $vectorKey ) === $vectorPlain && ninoSealed( $vectorPlain, $vectorKey, 1024, substr( sealBinary( $vectorFile ), 12, 12 ) ) === $vectorFile );
$carry = new ReflectionMethod( '\Nino\Backup\Seal', '_nonce' );
check( 'the nonce of a chunk is the nonce of the header plus its number: the carry runs through the bytes, the words and the end of the 96 bits', ( static function() use ( $carry ): bool {
	$base = str_repeat( "\0", 7 ). "\xff\xff\xff\xff\xfe";
	return bin2hex( $carry->invoke( null, $base, 1 ) ) === '00000000000000ffffffffff' && bin2hex( $carry->invoke( null, $base, 2 ) ) === '000000000000010000000000'
		&& bin2hex( $carry->invoke( null, str_repeat( "\xff", 12 ), 1 ) ) === str_repeat( '00', 12 ) && bin2hex( $carry->invoke( null, str_repeat( "\0", 11 ). "\xff", 257 ) ) === '000000000000000000000200'
		&& $carry->invoke( null, $base, 0 ) === $base;
} )() === true );

// The size of a chunk a header names is what a reader holds before any tag is checked: nothing above a megabyte
check( 'a reader holds at most a megabyte for a chunk that a header of a file names: more is no archive of this format, before anything is allocated', \Nino\Backup\Seal::CHUNK_MAX === 1048576
	&& sealOpen( sealFile( "NinoBak\x02". pack( 'N', 1048577 ). str_repeat( 'x', 12 + 100 ) ), $key ) === \Nino\Backup\Seal::HEADER_BAD && sealOpen( sealFile( "NinoBak\x02". pack( 'N', 4194304 ). str_repeat( 'x', 12 + 100 ) ), $key ) === \Nino\Backup\Seal::HEADER_BAD );

// A tar that has no end, and a gzip that is two members or has bytes behind it: what PharData refused, no reader of this kernel joins
$tarEntryRaw = static function( string $name, string $content ): string {
	$header = str_pad( $name, 100, "\0" ). sprintf( '%07o', 0644 ). "\0". sprintf( '%07o', 0 ). "\0". sprintf( '%07o', 0 ). "\0". sprintf( '%011o', strlen( $content ) ). "\0". sprintf( '%011o', 0 ). "\0". '        '. '0'. str_repeat( "\0", 100 ). "ustar\0". '00'. str_repeat( "\0", 32 ). str_repeat( "\0", 32 ). str_repeat( "\0", 16 ). str_repeat( "\0", 155 ). str_repeat( "\0", 12 );
	$sum 		= array_sum( array_map( 'ord', str_split( $header ) ) );
	return substr( $header, 0, 148 ). sprintf( '%06o', $sum ). "\0 ". substr( $header, 156 ). $content. str_repeat( "\0", ( 512 - strlen( $content ) % 512 ) % 512 );
};
$tarOk = $tarEntryRaw( 'templates/a.tpl', 'a' ). $tarEntryRaw( 'templates/b.tpl', 'b' ). str_repeat( "\0", 1024 );
file_put_contents( $dir. '/whole.tar.gz', gzencode( $tarOk ) );
file_put_contents( $dir. '/cut.tar.gz', gzencode( $tarEntryRaw( 'templates/a.tpl', 'a' ). $tarEntryRaw( 'templates/b.tpl', 'b' ) ) );
file_put_contents( $dir. '/two.tar.gz', gzencode( substr( $tarOk, 0, 1024 ) ). gzencode( substr( $tarOk, 1024 ) ) );
file_put_contents( $dir. '/tail.tar.gz', gzencode( $tarOk ). 'bytes behind the end' );
mkdir( $dir. '/into-whole' );
mkdir( $dir. '/into-cut' );
check( 'a tar that stops before its two blocks of zeros is refused by the unpacking: it was cut', \Nino\Backup::unpack( $dir. '/whole.tar.gz', $dir. '/into-whole' ) === null && \Nino\Backup::unpack( $dir. '/cut.tar.gz', $dir. '/into-cut' ) !== null );
check( 'a gzip is one stream and nothing else: two members, bytes behind its end, a stream that does not end and a file that is none are refused', \Nino\Backup::gzipOnly( $dir. '/whole.tar.gz' ) === true
	&& \Nino\Backup::gzipOnly( $dir. '/two.tar.gz' ) === false && \Nino\Backup::gzipOnly( $dir. '/tail.tar.gz' ) === false && \Nino\Backup::gzipOnly( $dir. '/plain' ) === false
	&& file_put_contents( $dir. '/short.gz', substr( gzencode( $tarOk ), 0, 20 ) ) > 0 && \Nino\Backup::gzipOnly( $dir. '/short.gz' ) === false );

// ...at the door: a restore of such an archive, sealed with the right key, is refused and the project is as it was
$twoMembers = ninoSealed( gzencode( substr( $tarOk, 0, 1024 ) ). gzencode( substr( $tarOk, 1024 ) ), $rawKeyOfDoors, 1024 );
$treeNow 		= sealTree( $sandboxOfDoors );
file_put_contents( $backupsOfDoors. '/2025-03-05-050607-manual.php', $twoMembers );
[ $status, $body ] = ninoCall( $doorsProject, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2025-03-05-050607-manual' ] );
unlink( $backupsOfDoors. '/2025-03-05-050607-manual.php' );
check( 'a restore of an archive that is two gzip members is refused with a 422 and the project is as it was', $status === 422 && ( $body['code'] ?? '' ) === 'backups_upload_format' && sealTree( $sandboxOfDoors ) === $treeNow );


echo "\nThe disk\n";

// The room a restore and an upload ask for: injected, with the numbers in the answer, and nothing made or left
$room 			= [ 'upload' => 0, 'restore' => 0 ];
$treeNow 		= sealTree( $sandboxOfDoors );
$listNow 		= glob( $backupsOfDoors. '/*' ) ?: [];
$doorsProject['./nino/backups/disk'] = 1048576;
[ $status, $body ] = $put( $doorsProject, 'backup-example.com-2025-03-06-050607-manual.php', $real );
$room['upload'] = $status === 507 && ( $body['code'] ?? '' ) === 'backups_disk' && count( $body['params'] ?? [] ) === 2 && $body['params'][1] === 1 ? 1 : 0;
file_put_contents( $backupsOfDoors. '/2025-03-06-050607-manual.php', $real );
[ $status, $body ] = ninoCall( $doorsProject, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2025-03-06-050607-manual' ] );
$room['restore'] = $status === 507 && ( $body['code'] ?? '' ) === 'backups_disk' && count( $body['params'] ?? [] ) === 2 ? 1 : 0;
unlink( $backupsOfDoors. '/2025-03-06-050607-manual.php' );
$noticeNone = null;
$typed 			= \Nino\Modules\Backups\Admin::restoreFile( $doorsProject, $sandboxOfDoors. '/upload-ok.bin', $rawKeyOfDoors, $noticeNone );
unset( $doorsProject['./nino/backups/disk'] );
check( 'an upload, a restore and a restore of a file at recovery.php are refused with a 507 and the numbers where the disk has no room for them, and nothing is made', $room === [ 'upload' => 1, 'restore' => 1 ] && ( is_array( $typed ) === false || $typed[0] === 507 ) && sealTree( $sandboxOfDoors ) === $treeNow && ( glob( $backupsOfDoors. '/*' ) ?: [] ) === $listNow );
$doorsProject['./nino/backups/disk'] = 0;
$small = \Nino\Modules\Backups::restoreShort( $doorsProject, 0 );
$large = \Nino\Modules\Backups::restoreShort( $doorsProject, 400 * 1048576 );
check( 'a restore asks for twice the plain archive, which is three quarters of the file, and the room of the snapshot on top: 400 MB of archive ask for 600 MB more than none', is_array( $small ) === true && is_array( $large ) === true && abs( $large[0] - $small[0] - 600 ) <= 1 && $small[0] >= 4 );
$doorsProject['./nino/backups/disk'] = 1 << 40;
check( '...and a disk that has the room is not asked', \Nino\Modules\Backups::restoreShort( $doorsProject, 400 * 1048576 ) === null );
unset( $doorsProject['./nino/backups/disk'] );

// A disk that fills up, for real: a tmpfs of a size, where this process may mount one (root does; the check says so where it does not)
$mount = $dir. '/mnt';
mkdir( $mount );
exec( 'mount -t tmpfs -o size=70m tmpfs '. escapeshellarg( $mount ). ' 2>&1', $ignored, $mounted );

if( $mounted === 0 ) {

	$fullProject = sealProject( 'seal-full' );
	$fullDir 		 = ninoSandboxDir( $fullProject );
	@mkdir( $fullDir. '/private/assets', 0755, true );
	$blob = fopen( $fullDir. '/private/assets/blob.bin', 'wb' );
	for( $piece = 0; $piece < 40; $piece++ )
		fwrite( $blob, random_bytes( 1048576 ) );
	fclose( $blob );
	@unlink( $fullDir. '/private/.backups/run.php' );

	// The room the check asks for is more than the 70 MB there are for 40 MB of files, so it refuses before anything is made
	$childNow = escapeshellarg( PHP_BINARY ). ' -d sys_temp_dir='. escapeshellarg( $mount ). ' '. escapeshellarg( __FILE__ ). ' now '. escapeshellarg( $fullDir ). ' 2>&1';
	$refusedAnswer = json_decode( (string) shell_exec( $childNow ), true );
	check( 'a temporary directory of 70 MB for 40 MB of files is refused before anything is made, with the code the panel words it by and the sizes', is_array( $refusedAnswer ) === true && ( $refusedAnswer[2] ?? '' ) === 'backups_disk' && ( $refusedAnswer[3][1] ?? 0 ) <= 70 && ( $refusedAnswer[3][0] ?? 0 ) > 80
		&& ( glob( $mount. '/*' ) ?: [] ) === [] );

	// ...and where the check lets it through - a disk that somebody else is filling is what that is - the run ends with the notice of a write that failed, the record of the run says the disk, and nothing half is left
	exec( 'umount '. escapeshellarg( $mount ) );
	exec( 'mount -t tmpfs -o size=60m tmpfs '. escapeshellarg( $mount ) );
	$childFull = escapeshellarg( PHP_BINARY ). ' -d sys_temp_dir='. escapeshellarg( $mount ). ' '. escapeshellarg( __FILE__ ). ' now-blind '. escapeshellarg( $fullDir ). ' 2>&1';
	$full = json_decode( (string) shell_exec( $childFull ), true );
	$record = \Nino\Modules\Backups::stopped( $fullProject );
	check( 'a disk that fills up while the archive is made ends the run with the notice of a write that failed: a 500, the code of a write, and no path in what is said', is_array( $full ) === true && ( $full[0] ?? 0 ) === 500 && ( $full[2] ?? '' ) === 'backups_write'
		&& str_contains( (string) ( $full[1] ?? '' ), $fullDir ) === false && str_contains( (string) ( $full[1] ?? '' ), $mount ) === false && str_contains( (string) ( $full[1] ?? '' ), 'unpack' ) === false );
	check( '...the record of the run says that the disk ended it, and no half archive or temporary file of the run is left', ( $record['cause'] ?? '' ) === 'disk' && ( $record['files'] ?? 0 ) >= 1 && ( glob( $fullDir. '/private/.backups/*.tmp' ) ?: [] ) === []
		&& ( glob( $fullDir. '/private/.backups/2*-manual.php' ) ?: [] ) === [] && ( glob( $mount. '/ninobackup*' ) ?: [] ) === [] );
	// The unpacking of a tar that is larger than the disk it is unpacked to says that a write failed, once, and names no path
	$bigTar = gzopen( $dir. '/big.tar.gz', 'wb1' );
	gzwrite( $bigTar, $tarEntry( 'templates/big.tpl', '', '0', 90 * 1048576 ) );
	for( $piece = 0; $piece < 90; $piece++ )
		gzwrite( $bigTar, str_repeat( "\0", 1048576 ) );
	gzwrite( $bigTar, str_repeat( "\0", 1024 ) );
	gzclose( $bigTar );
	mkdir( $mount. '/unpacked' );
	$unwrittenBig = false;
	$whyBig 			= \Nino\Backup::unpack( $dir. '/big.tar.gz', $mount. '/unpacked', $unwrittenBig );
	check( 'the unpacking of a tar that is larger than its disk says that a write failed, in a sentence that is not said twice by what asks for it, and no path', $unwrittenBig === true && is_string( $whyBig ) === true && str_contains( $whyBig, 'could not be unpacked' ) === false && str_contains( $whyBig, $mount ) === false );
	exec( 'umount '. escapeshellarg( $mount ) );
	\Nino\Filesystem::removeDir( $fullDir );
}
else
	echo "  skip - no tmpfs can be mounted here (". trim( implode( ' ', $ignored ) ). "): the disk that fills up is not tried for real\n";


echo "\nOne at a time, and no plain archive left\n";

// "Back up now" takes the lock of the daily run and of a restore: another process holds it, and neither is started
$lockProject = sealProject( 'seal-lock' );
$lockDir 		 = ninoSandboxDir( $lockProject );
$holder 		 = proc_open( [ PHP_BINARY, __FILE__, 'hold', $lockDir, '4' ], [ 1 => [ 'pipe', 'w' ] ], $pipes );
fgets( $pipes[1] );
$busy 			 = \Nino\Modules\Backups::now( $lockProject );
[ $status, $body ] = ninoCall( $lockProject, [ \Nino\Modules\Backups\Admin::class, 'apiNow' ] );
$restoreBusy = \Nino\Modules\Backups\Admin::restore( $lockProject, date( 'Y-m-d' ) );
check( 'while another process holds the lock of the daily run and of a restore, "Back up now" is answered with a 409 and a code, in the panel too, and a restore is as well', is_array( $busy ) === true && $busy[0] === 409 && ( $busy[2] ?? '' ) === 'backups_busy'
	&& $status === 409 && ( $body['code'] ?? '' ) === 'backups_busy' && $restoreBusy[0] === 409 );
proc_terminate( $holder );
proc_close( $holder );
$free = \Nino\Modules\Backups::now( $lockProject );
check( '...and when it lets go, the lock is free for the next one: the lock is given back by every way out of it', is_string( $free ) === true && is_string( \Nino\Modules\Backups::now( $lockProject ) ) === true );

// Two clicks at the same moment, in two processes, over data that takes a moment to pack: one archive is made and the other is told
@mkdir( $lockDir. '/private/assets', 0755, true );
@mkdir( $lockDir. '/tmp', 0777, true );
$bulk = fopen( $lockDir. '/private/assets/bulk.bin', 'wb' );
for( $piece = 0; $piece < 96; $piece++ )
	fwrite( $bulk, random_bytes( 1048576 ) );
fclose( $bulk );
$procs = [];
for( $each = 0; $each < 2; $each++ )
	$procs[$each] = proc_open( [ PHP_BINARY, __FILE__, 'now', $lockDir ], [ 1 => [ 'pipe', 'w' ] ], $pipes2[$each] );
$answers = [];
foreach( $procs as $each => $process ) {
	$answers[] = json_decode( (string) stream_get_contents( $pipes2[$each][1] ), true );
	proc_close( $process );
}
$made = array_filter( $answers, 'is_string' );
$told = array_filter( $answers, static fn( mixed $answer ): bool => is_array( $answer ) === true && ( $answer[2] ?? '' ) === 'backups_busy' );
check( 'two "Back up now" at the same moment make one archive and tell the other, which is none of an archive of the same id twice', count( $made ) === 1 && count( $told ) === 1 );

// A restore that php ends - the memory here - leaves no plain archive in the temporary directory, and an upload that php ends leaves no copy of the file in the directory of the archives
$archiveId = (string) array_values( $made )[0];
$squeezed 	= escapeshellarg( PHP_BINARY ). ' -d sys_temp_dir='. escapeshellarg( $lockDir. '/tmp' ). ' '. escapeshellarg( __FILE__ ). ' squeeze-restore '. escapeshellarg( $lockDir ). ' '. escapeshellarg( $archiveId ). ' 2>&1';
$outcome 		= (string) shell_exec( $squeezed );
check( 'a restore that php ends in the middle of opening the archive leaves nothing of it in the temporary directory, the plain archive least', str_contains( $outcome, 'survived' ) === false && glob( $lockDir. '/tmp/*' ) === [] );
$uploadCopy = $lockDir. '/to-upload.bin';
copy( $lockDir. '/private/.backups/'. $archiveId. '.php', $uploadCopy );
unlink( $lockDir. '/private/.backups/'. $archiveId. '.php' );
$squeezed 	= escapeshellarg( PHP_BINARY ). ' -d sys_temp_dir='. escapeshellarg( $lockDir. '/tmp' ). ' '. escapeshellarg( __FILE__ ). ' squeeze-upload '. escapeshellarg( $lockDir ). ' '. escapeshellarg( $uploadCopy ). ' '. escapeshellarg( $archiveId. '.php' ). ' 2>&1';
$outcome 		= (string) shell_exec( $squeezed );
check( 'an upload that php ends in the middle of its check leaves no copy of the file in the directory of the archives', str_contains( $outcome, 'survived' ) === false && glob( $lockDir. '/private/.backups/upload-*.tmp' ) === [] );

// A file that names a chunk of four megabytes, and nothing else: refused as no archive, with the memory limit of a process that cannot carry such a chunk
file_put_contents( $lockDir. '/garbage-4m.bin', sealFile( "NinoBak\x02". pack( 'N', 4194304 ). random_bytes( 12 ). random_bytes( 4194304 + 16 ) ) );
$squeezed 	= escapeshellarg( PHP_BINARY ). ' -d memory_limit=16M -d sys_temp_dir='. escapeshellarg( $lockDir. '/tmp' ). ' '. escapeshellarg( __FILE__ ). ' squeeze-upload '. escapeshellarg( $lockDir ). ' '. escapeshellarg( $lockDir. '/garbage-4m.bin' ). ' backup-example.com-2025-04-04.php 2>&1';
$answered 		= (string) shell_exec( str_replace( ' squeeze-upload ', ' upload ', $squeezed ) );
check( 'a file with a header of four megabytes the chunk is refused as none of the format in a process of 16 MB - answered, with a 422, not ended - and nothing of it stays', str_contains( $answered, 'survived 422' ) === true && glob( $lockDir. '/private/.backups/upload-*.tmp' ) === [] );

\Nino\Filesystem::removeDir( $lockDir );


echo "\nA full disk in the middle, and a restore that does not copy back\n";

// The step of packing runs with errors that are exceptions, and the handler that was there is back
$before = set_error_handler( static fn(): bool => false );
restore_error_handler();
$thrown = null;
try { \Nino\Backup::raising( static function(): void { trigger_error( 'Write of 8192 bytes failed with errno=28 No space left on device', E_USER_NOTICE ); } ); }
catch( \ErrorException $caught ) { $thrown = $caught; }
$handlerAfter = set_error_handler( static fn(): bool => false );
restore_error_handler();
$silenced = \Nino\Backup::raising( static fn(): mixed => @file_get_contents( '/nonexistent/'. bin2hex( random_bytes( 4 ) ) ) );
check( 'a notice in the step of packing is an exception, the handler of the kernel is back after it - after one that threw too - and what the code silenced is no exception', $thrown !== null && str_contains( $thrown->getMessage(), 'errno=28' ) === true && $handlerAfter === $before && $silenced === false );

// The room on the disk the archives are kept on, where it is none of the temporary directory's: said before anything is made
$need = 100 * 1048576;
$probe = [];
$probe['./nino/backups/disk'] = (int) ceil( $need * 4 / 3 ) + 4 * 1048576 - 1;
$tooLittle = \Nino\Backup::archiveShort( $probe, $need, $dir );
$probe['./nino/backups/disk'] = (int) ceil( $need * 4 / 3 ) + 4 * 1048576;
$enough = \Nino\Backup::archiveShort( $probe, $need, $dir );
check( 'the disk of the archives is asked for a third more than the bytes and the reserve: one byte less is refused, that much is enough', is_array( $tooLittle ) === true && $enough === null );

// What was copied is what there was to copy: a file that is shorter than its source is not, a directory of such files is not either
file_put_contents( $dir. '/whole-src', 'abcdef' );
file_put_contents( $dir. '/whole-short', 'abc' );
file_put_contents( $dir. '/whole-same', 'ghijkl' );
@mkdir( $dir. '/whole-dir-src/in', 0755, true );
@mkdir( $dir. '/whole-dir-ok/in', 0755, true );
@mkdir( $dir. '/whole-dir-cut/in', 0755, true );
file_put_contents( $dir. '/whole-dir-src/in/f', '0123456789' );
file_put_contents( $dir. '/whole-dir-ok/in/f', 'abcdefghij' );
file_put_contents( $dir. '/whole-dir-cut/in/f', '01234' );
$whole = new ReflectionMethod( '\Nino\Modules\Backups\Admin', '_whole' );
check( 'a file that is as long as its source, a directory of them, are whole; one that is shorter or not there is not', $whole->invoke( null, $dir. '/whole-src', $dir. '/whole-same' ) === true && $whole->invoke( null, $dir. '/whole-src', $dir. '/whole-short' ) === false
	&& $whole->invoke( null, $dir. '/whole-src', $dir. '/whole-none' ) === false && $whole->invoke( null, $dir. '/whole-dir-src', $dir. '/whole-dir-ok' ) === true && $whole->invoke( null, $dir. '/whole-dir-src', $dir. '/whole-dir-cut' ) === false );

// The room the files need where they go: what they grow by, the files they replace taken off
$grow = sealProject( 'seal-grow' );
$growDir = ninoSandboxDir( $grow );
@mkdir( $growDir. '/private/assets', 0755, true );
file_put_contents( $growDir. '/private/assets/a.bin', str_repeat( 'a', 2 * 1048576 ) );
@mkdir( $dir. '/stg/assets', 0755, true );
file_put_contents( $dir. '/stg/assets/a.bin', str_repeat( 'b', 3 * 1048576 ) );
$grow['./nino/backups/disk'] = 1048576 + 4194304;
$fits = \Nino\Backup::restoreRoom( $grow, $dir. '/stg' );
$grow['./nino/backups/disk'] = 1048576 + 4194304 - 1;
$short = \Nino\Backup::restoreRoom( $grow, $dir. '/stg' );
check( 'the room a restore asks of the disk of the files is what they grow by - 3 MB for the file that replaces one of 2 MB is 1 MB - and the reserve: one byte less is refused', $fits === null && is_array( $short ) === true );
\Nino\Filesystem::removeDir( $growDir );

// A site of files that are cut: the project of two files, one of which cannot be written where it goes
$partial 		= sealProject( 'seal-partial' );
$partialDir = ninoSandboxDir( $partial );
@mkdir( $partialDir. '/private/assets', 0755, true );
@mkdir( $partialDir. '/private/templates', 0755, true );
file_put_contents( $partialDir. '/private/assets/a.css', 'archived a' );
file_put_contents( $partialDir. '/private/assets/b.css', 'archived b' );
file_put_contents( $partialDir. '/private/templates/page-x.tpl', 'archived page' );
$archiveId = \Nino\Modules\Backups::now( $partial );

// The state the restore starts from: a file of the archive is a directory here, which no copy can replace
file_put_contents( $partialDir. '/private/assets/a.css', 'live a' );
unlink( $partialDir. '/private/assets/b.css' );
mkdir( $partialDir. '/private/assets/b.css' );
file_put_contents( $partialDir. '/private/assets/b.css/inner.txt', 'live inner' );
file_put_contents( $partialDir. '/private/templates/page-x.tpl', 'live page' );
$stateBefore 	= sealTree( $partialDir );
$result 			= \Nino\Modules\Backups\Admin::restore( $partial, (string) $archiveId );
$snapshots 		= array_map( static fn( string $file ): string => basename( $file, '.php' ), glob( $partialDir. '/private/.backups/pre-restore-*.php' ) ?: [] );
check( 'a restore that could not copy a file back is no 200: it answers with a code and the archive of the list that holds the state before, and names no path', is_array( $result ) === true && $result[0] === 500 && ( $result[2] ?? '' ) === 'backups_restore_partial'
	&& count( $snapshots ) === 1 && ( $result[3] ?? [] ) === $snapshots && str_contains( (string) $result[1], $partialDir ) === false && str_contains( (string) $result[1], sys_get_temp_dir() ) === false );
check( '...the files that were copied are the archive\'s and the one that was not is as it was: the project is in part restored, which the answer says', file_get_contents( $partialDir. '/private/assets/a.css' ) === 'archived a' && file_get_contents( $partialDir. '/private/assets/b.css/inner.txt' ) === 'live inner' );
$back = \Nino\Modules\Backups\Admin::restore( $partial, $snapshots[0] ?? 'pre-restore-none' );
check( '...and the archive it names restores the state before, byte for byte: the hash of every file of the project is the one it had', $back === true && sealTree( $partialDir ) === $stateBefore );
\Nino\Modules\Backups\Admin::recordRestore( $partial, 'tester@example.com', (string) $archiveId, $result );
$logged = implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $partial, 5 ) );
check( '...and the line of the log says that the restore failed in part and which archive holds the state before', str_contains( $logged, 'in part' ) === true && str_contains( $logged, $snapshots[0] ?? '-' ) === true );

// The disks, for real: the site on a disk that is too small, the archives on another
$mountTwo = $dir. '/mnt2';
mkdir( $mountTwo );
exec( 'mount -t tmpfs -o size=30m tmpfs '. escapeshellarg( $mountTwo ). ' 2>&1', $ignoredTwo, $mountedTwo );

if( $mountedTwo === 0 ) {

	$site 		= sealProject( 'seal-site' );
	$siteDir 	= ninoSandboxDir( $site );
	@mkdir( $siteDir. '/public/images', 0755, true );
	$images = fopen( $siteDir. '/public/images/big.jpg', 'wb' );
	for( $piece = 0; $piece < 40; $piece++ )
		fwrite( $images, random_bytes( 1048576 ) );
	fclose( $images );
	$siteArchive = \Nino\Modules\Backups::now( $site );

	// The pictures now lie on a disk of 30 MB: where the archive's 40 MB cannot go
	unlink( $siteDir. '/public/images/big.jpg' );
	rmdir( $siteDir. '/public/images' );
	mkdir( $mountTwo. '/images' );
	symlink( $mountTwo. '/images', $siteDir. '/public/images' );
	$tooSmall = \Nino\Modules\Backups\Admin::restore( $site, (string) $siteArchive );
	check( 'a restore of 40 MB of pictures to a disk of 30 MB is refused before anything is copied, with a 507 and the numbers of the disk of the site', is_array( $tooSmall ) === true && $tooSmall[0] === 507 && ( $tooSmall[2] ?? '' ) === 'backups_disk' && ( $tooSmall[3][1] ?? 99 ) <= 30 && ( $tooSmall[3][0] ?? 0 ) >= 40
		&& ( glob( $siteDir. '/private/.backups/pre-restore-*.php' ) ?: [] ) === [] && ( glob( $mountTwo. '/images/*' ) ?: [] ) === [] );

	// ...and where the room is said to be there and the disk fills up all the same: the child, with the check passed and the handler of the kernel
	$blind 	= escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( __FILE__ ). ' restore-blind '. escapeshellarg( $siteDir ). ' '. escapeshellarg( (string) $siteArchive ). ' 2>&1';
	$cut 		= json_decode( (string) shell_exec( $blind ), true );
	check( 'a disk that fills up while the files are copied back is a 500 with the code of a restore in part and the archive that holds the state before - never a 200 - and the files that are cut are named by neither', is_array( $cut ) === true && ( $cut[0] ?? 0 ) === 500 && ( $cut[2] ?? '' ) === 'backups_restore_partial' && str_starts_with( (string) ( $cut[3][0] ?? '' ), 'pre-restore-' ) === true );

	// The archives on a disk of their own, which is small: refused before a byte is packed
	exec( 'umount '. escapeshellarg( $mountTwo ). ' 2>&1' );
	exec( 'mount -t tmpfs -o size=20m tmpfs '. escapeshellarg( $mountTwo ) );
	$away 		= sealProject( 'seal-away' );
	$awayDir 	= ninoSandboxDir( $away );
	@mkdir( $awayDir. '/private/assets', 0755, true );
	$bulk = fopen( $awayDir. '/private/assets/bulk.bin', 'wb' );
	for( $piece = 0; $piece < 40; $piece++ )
		fwrite( $bulk, random_bytes( 1048576 ) );
	fclose( $bulk );
	\Nino\Filesystem::removeDir( $awayDir. '/private/.backups' );
	symlink( $mountTwo, $awayDir. '/private/.backups' );
	$refusedAway = \Nino\Modules\Backups::now( $away );
	check( 'archives that are kept on a disk of 20 MB for 40 MB of files are refused at once, with a 507 and the numbers of that disk, before anything is packed', is_array( $refusedAway ) === true && $refusedAway[0] === 507 && ( $refusedAway[2] ?? '' ) === 'backups_disk' && ( $refusedAway[3][1] ?? 99 ) <= 20
		&& ( glob( $mountTwo. '/*' ) ?: [] ) === [] );
	unlink( $awayDir. '/private/.backups' );
	exec( 'umount '. escapeshellarg( $mountTwo ). ' 2>&1' );
	\Nino\Filesystem::removeDir( $awayDir );
	\Nino\Filesystem::removeDir( $siteDir );
}
else
	echo "  skip - no tmpfs can be mounted here (". trim( implode( ' ', $ignoredTwo ) ). "): the disks that are too small are not tried for real\n";

// The step of packing in a request, with the handler of the kernel in it: a disk that fills while the tar is made is an honest end
if( $mounted === 0 ) {

	exec( 'mount -t tmpfs -o size=60m tmpfs '. escapeshellarg( $mount ). ' 2>&1' );
	$tarProject = sealProject( 'seal-tar' );
	$tarDir 		= ninoSandboxDir( $tarProject );
	@mkdir( $tarDir. '/private/assets', 0755, true );
	$tarBulk = fopen( $tarDir. '/private/assets/bulk.bin', 'wb' );
	for( $piece = 0; $piece < 40; $piece++ )
		fwrite( $tarBulk, random_bytes( 1048576 ) );
	fclose( $tarBulk );
	@unlink( $tarDir. '/private/.backups/run.php' );
	$tarChild = escapeshellarg( PHP_BINARY ). ' -d sys_temp_dir='. escapeshellarg( $mount ). ' '. escapeshellarg( __FILE__ ). ' now-blind '. escapeshellarg( $tarDir ). ' 2>&1';
	$tarAnswer = json_decode( (string) shell_exec( $tarChild ), true );
	$tarRecord = \Nino\Modules\Backups::stopped( $tarProject );
	check( 'a disk that fills while PharData makes the tar, in a request whose handler of errors ends it at a notice, is a 500 with the code of a write, and the record of the run says the disk', is_array( $tarAnswer ) === true && ( $tarAnswer[0] ?? 0 ) === 500 && ( $tarAnswer[2] ?? '' ) === 'backups_write'
		&& ( $tarRecord['cause'] ?? '' ) === 'disk' && ( glob( $mount. '/ninobackup*' ) ?: [] ) === [] );

	// ...and the same for the snapshot of a restore: the site is as it was, and the answer is a code
	$tarId = \Nino\Modules\Backups::now( $tarProject );
	exec( 'umount '. escapeshellarg( $mount ). ' 2>&1' );
	exec( 'mount -t tmpfs -o size=100m tmpfs '. escapeshellarg( $mount ). ' 2>&1' );
	$treeTar = sealTree( $tarDir );
	$tarRestore = escapeshellarg( PHP_BINARY ). ' -d sys_temp_dir='. escapeshellarg( $mount ). ' '. escapeshellarg( __FILE__ ). ' restore-blind '. escapeshellarg( $tarDir ). ' '. escapeshellarg( (string) $tarId ). ' 2>&1';
	$tarResult = json_decode( (string) shell_exec( $tarRestore ), true );
	check( 'a disk that fills while the snapshot of a restore is packed is a 500 with the code of a write, the project is as it was and nothing of the restore is left in the temporary directory', is_array( $tarResult ) === true && ( $tarResult[0] ?? 0 ) === 500 && ( $tarResult[2] ?? '' ) === 'backups_write'
		&& sealTree( $tarDir ) === $treeTar && ( glob( $mount. '/*' ) ?: [] ) === [] );
	exec( 'umount '. escapeshellarg( $mount ). ' 2>&1' );
	\Nino\Filesystem::removeDir( $tarDir );
}

// A request that php ends after the archive is unpacked leaves nothing of it in the temporary directory, the staging directory of the files included
$endProject = sealProject( 'seal-end' );
$endDir 		= ninoSandboxDir( $endProject );
@mkdir( $endDir. '/tmp', 0777, true );
@mkdir( $endDir. '/private/assets', 0755, true );
file_put_contents( $endDir. '/private/assets/a.css', 'archived' );
$endId = \Nino\Modules\Backups::now( $endProject );
$ended = (string) shell_exec( escapeshellarg( PHP_BINARY ). ' -d sys_temp_dir='. escapeshellarg( $endDir. '/tmp' ). ' '. escapeshellarg( __FILE__ ). ' die-in-copy '. escapeshellarg( $endDir ). ' '. escapeshellarg( (string) $endId ). ' 2>&1' );
check( 'a restore that php ends once the archive is unpacked leaves nothing in the temporary directory: not the plain archive, not the staging directory with the files of the site in it', str_contains( $ended, 'survived' ) === false && str_contains( $ended, 'Allowed memory size' ) === true && glob( $endDir. '/tmp/*' ) === [] );
\Nino\Filesystem::removeDir( $endDir );
\Nino\Filesystem::removeDir( $partialDir );

foreach( [ $doorsProject, $old, $other, $third ] as $project )
	\Nino\Filesystem::removeDir( ninoSandboxDir( $project ) );

\Nino\Filesystem::removeDir( $dir );

ninoDone( $appData );
