<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	archive-smoke.php		Dependency-free smoke test for \Nino\Archive::read(), the reader of a tar.gz in memory: what a good
 *											archive of one folder with folders in it answers, and one case for each thing it refuses - a link, a
 *											device, a path that climbs, a second folder, a depth, a name that a pax header or the GNU tar gives
 *											another way, a checksum, a stream that unpacks to a mountain, bytes behind the gzip stream and behind
 *											the tar, a name twice in the spelling a Mac folds together, the limits of the caller and the names it
 *											allows. The archives are written here byte by byte: no tar of the machine, no file on a disk.
 *
 *	Usage: php tests/archive-smoke.php
 */

require __DIR__. '/harness.php';

/**
 *	One entry of a tar, written by hand
 *
 *	@param		string		$name
 *	@param		string		$content
 *	@param		string		$type					The type flag: '0' file, '5' folder, '1' hard link, '2' symbolic link, '3' device, 'L' long name of the GNU tar, 'x' pax
 *	@param		string		$link
 *	@param		string		$prefix
 *	@param		int|null	$size					What the header says, where it is not the length of the content
 *	@param		string		$magic				The magic and the version of the header: POSIX, or the GNU format, which has no prefix
 *
 *	@return 	string
 */
function archiveEntry( string $name, string $content = '', string $type = '0', string $link = '', string $prefix = '', ?int $size = null, string $magic = "ustar\0". '00' ): string {

	$head = str_pad( $name, 100, "\0" ). sprintf( '%07o', 0644 ). "\0". sprintf( '%07o', 0 ). "\0". sprintf( '%07o', 0 ). "\0"
		. sprintf( '%011o', $size ?? strlen( $content ) ). "\0". sprintf( '%011o', 0 ). "\0". '        '. $type. str_pad( $link, 100, "\0" )
		. $magic. str_repeat( "\0", 32 + 32 + 16 ). str_pad( $prefix, 155, "\0" ). str_repeat( "\0", 12 );
	$sum = array_sum( unpack( 'C*', $head ) );

	return substr( $head, 0, 148 ). sprintf( '%06o', $sum ). "\0 ". substr( $head, 156 ). $content. str_repeat( "\0", ( 512 - strlen( $content ) % 512 ) % 512 );
}

/**
 *	A tar from entries, ended as a tar is
 *
 *	@param		array 		$entries			The strings archiveEntry() makes
 *
 *	@return 	string
 */
function archiveTar( array $entries ): string {
	return implode( '', $entries ). str_repeat( "\0", 1024 );
}

/**
 *	The records of a pax header, each with its length in front
 *
 *	@param		array 		$records			key => value
 *
 *	@return 	string
 */
function archivePax( array $records ): string {

	$out = '';

	foreach( $records as $key => $value ) {
		$body = ' '. $key. '='. $value. "\n";
		$length = strlen( $body ) + 1;
		$length = strlen( $body ) + strlen( (string) $length );
		$out .= $length. $body;
	}

	return $out;
}

$read = static fn( array $entries, ?string $folder = 'pack', int $count = 40, int $each = 4096, int $total = 65536, string|Closure|null $allow = null ): array|string
	=> \Nino\Archive::read( (string) gzencode( archiveTar( $entries ) ), $folder, $count, $each, $total, $allow );

$good = [
	archiveEntry( 'pack/', '', '5' ),
	archiveEntry( 'pack/pack.json', '{"key":"pack"}' ),
	archiveEntry( 'pack/parts/', '', '5' ),
	archiveEntry( 'pack/parts/section/part.css', ".section { color: red; }\n" ),
	archiveEntry( 'pack/parts/section/part.json', '{}' ),
	archiveEntry( 'pack/text/en_US.json', str_repeat( 'x', 700 ) ),
];

echo "The codes\n";

// The words a caller switches on are the values of these constants, not the names
$codes = [];

foreach( ( new ReflectionClass( \Nino\Archive::class ) )->getReflectionConstants( ReflectionClassConstant::IS_PUBLIC ) as $constant )
	if( is_string( $constant->getValue() ) === true )
		$codes[$constant->getName()] = $constant->getValue();

check( 'the codes of a refusal are the literals the callers will name, one for each thing that is refused', $codes === [
	'UNSUPPORTED' => 'unsupported', 'GZIP' => 'gzip', 'BOMB' => 'bomb', 'TRAILING' => 'trailing', 'TRUNCATED' => 'truncated', 'HEADER' => 'header', 'TYPE' => 'type',
	'PAX' => 'pax', 'PATH' => 'path', 'DEPTH' => 'depth', 'FOLDER' => 'folder', 'DUPLICATE' => 'duplicate', 'NAME' => 'name', 'COUNT' => 'count',
	'FILE_SIZE' => 'file-size', 'TOTAL_SIZE' => 'total-size',
] && \Nino\Archive::DEPTH_MAX === 6 );
$codeOnly = implode( '', array_map( static fn( array|string $token ): string => is_array( $token ) === true && in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT ], true ) === false ? $token[1] : ( is_string( $token ) === true ? $token : '' ),
	token_get_all( (string) file_get_contents( __DIR__. '/../_nino/Nino/Archive/Archive.php' ) ) ) );
check( 'the reader asks for the functions of zlib that inflate and for nothing the C library of a host has of its own', \Nino\Archive::supported() === true
	&& preg_match( '/PharData|ZipArchive|\bexec\s*\(|shell_exec|proc_open|passthru|popen\s*\(|\bsystem\s*\(|GLOB_BRACE|file_put_contents|fopen\s*\(|\btempnam|iconv/', $codeOnly ) === 0 );


echo "\nA good archive\n";

$files = $read( $good );
check( 'the files of a good archive come back by their path below the folder, with their bytes and in the order of the archive - the folders are no file, and folders hold folders',
	is_array( $files ) === true && array_keys( $files ) === [ 'pack.json', 'parts/section/part.css', 'parts/section/part.json', 'text/en_US.json' ]
	&& $files['parts/section/part.css'] === ".section { color: red; }\n" && $files['text/en_US.json'] === str_repeat( 'x', 700 ) );
check( 'the folder may be left to the archive, may lack its own entry, may be written with ./ before it, and an extension of pax that names nothing is passed over',
	$read( $good, null ) === $files && $read( array_slice( $good, 1 ) ) === $files
	&& $read( array_map( static fn( string $entry ): string => archiveEntry( './'. rtrim( substr( $entry, 0, strcspn( $entry, "\0" ) ), '/' ), (string) substr( $entry, 512, (int) octdec( substr( $entry, 124, 11 ) ) ), $entry[156] ), $good ) ) === $files
	&& $read( array_merge( array_slice( $good, 0, 2 ), [ archiveEntry( 'PaxHeaders/pack.json', archivePax( [ 'mtime' => '1700000000.5', 'comment' => 'x' ] ), 'x' ) ], array_slice( $good, 2 ) ) ) === $files );
check( 'a file may stand as deep as the depth allows, at six segments below the folder', is_array( $read( [ archiveEntry( 'pack/a/b/c/d/e/f.txt', 'x' ) ] ) ) === true );
check( 'the prefix of a header is the field of the POSIX format and of no other: where the GNU format keeps the times of a file, nothing is read as a part of a name',
	$read( [ archiveEntry( 'pack/a.txt', 'x', '0', '', 'zz', null, "ustar  \0" ) ] ) === [ 'a.txt' => 'x' ] && $read( [ archiveEntry( 'a.txt', 'x', '0', '', 'pack' ) ] ) === [ 'a.txt' => 'x' ] );
check( 'a gzip stream the zlib of a host may end differently - a tar with no block of nothing at its end - is as complete as one that has it',
	is_array( \Nino\Archive::read( (string) gzencode( implode( '', $good ) ), 'pack', 40, 4096, 65536 ) ) === true );


echo "\nWhat is refused\n";

$refusal = static fn( array|string $answer ): string => is_string( $answer ) === true ? $answer : 'a good archive';

check( 'a symbolic link, a hard link and a device are no entries of an archive: type',
	$refusal( $read( array_merge( $good, [ archiveEntry( 'pack/l', '', '2', '/etc/passwd' ) ] ) ) ) === \Nino\Archive::TYPE
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/h', '', '1', 'pack/pack.json' ) ] ) ) ) === \Nino\Archive::TYPE
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/d', '', '3' ) ] ) ) ) === \Nino\Archive::TYPE );
check( 'the long name of the GNU tar is none, whatever it says: type - nobody reads a name the header does not hold',
	$refusal( $read( array_merge( [ archiveEntry( '././@LongLink', 'pack/../../x'. "\0", 'L' ) ], $good ) ) ) === \Nino\Archive::TYPE );
check( 'a pax header that names a path, a link or a size is refused: pax - what it says is what another reader would take instead of the header',
	$refusal( $read( array_merge( array_slice( $good, 0, 2 ), [ archiveEntry( 'PaxHeaders/x', archivePax( [ 'path' => 'pack/../../evil.php' ] ), 'x' ) ], array_slice( $good, 2 ) ) ) ) === \Nino\Archive::PAX
	&& $refusal( $read( array_merge( [ archiveEntry( 'PaxHeaders/x', archivePax( [ 'size' => '1' ] ), 'g' ) ], $good ) ) ) === \Nino\Archive::PAX
	&& $refusal( $read( array_merge( [ archiveEntry( 'PaxHeaders/x', "garbage without a length\n", 'x' ) ], $good ) ) ) === \Nino\Archive::PAX );
check( 'a pax header of a key that is not on the list of what says nothing of a name or a size - the sparse name of the GNU tar, the real size of Schily - is refused: pax, and the keys of the list pass',
	$refusal( $read( array_merge( [ archiveEntry( 'PaxHeaders/x', archivePax( [ 'GNU.sparse.name' => 'pack/evil.php' ] ), 'x' ) ], $good ) ) ) === \Nino\Archive::PAX
	&& $refusal( $read( array_merge( [ archiveEntry( 'PaxHeaders/x', archivePax( [ 'SCHILY.realsize' => '1' ] ), 'x' ) ], $good ) ) ) === \Nino\Archive::PAX
	&& $refusal( $read( array_merge( [ archiveEntry( 'PaxHeaders/x', archivePax( [ 'unheard.of' => '1' ] ), 'x' ) ], $good ) ) ) === \Nino\Archive::PAX
	&& is_array( $read( array_merge( [ archiveEntry( 'PaxHeaders/x', archivePax( [ 'uid' => '501', 'LIBARCHIVE.creationtime' => '1', 'SCHILY.xattr.user.a' => 'b' ] ), 'x' ) ], $good ) ) ) === true );
check( 'a name of digits alone would be an integer as the key of the answer, and the answer promises strings: path', $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/123', 'x' ) ] ) ) ) === \Nino\Archive::PATH
	&& array_filter( array_keys( (array) $files ), 'is_int' ) === [] && is_array( $read( array_merge( $good, [ archiveEntry( 'pack/a/123', 'x' ) ] ) ) ) === true );
check( 'limits as large as an integer can be do not throw', is_array( $read( $good, 'pack', PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX ) ) === true );
check( 'a name that climbs, a name from the root and a name with a backslash are no paths: path',
	$refusal( $read( array_merge( $good, [ archiveEntry( 'pack/../x.txt', 'x' ) ] ) ) ) === \Nino\Archive::PATH
	&& $refusal( $read( array_merge( $good, [ archiveEntry( '/etc/cron.d/x', 'x' ) ] ) ) ) === \Nino\Archive::PATH
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack\\..\\x.txt', 'x' ) ] ) ) ) === \Nino\Archive::PATH );
check( 'a name with a NUL byte and bytes behind it, a segment that is hidden and one that is a dot are no plain names: path',
	$refusal( $read( array_merge( $good, [ archiveEntry( "pack/a.txt\0evil.php", 'x' ) ] ) ) ) === \Nino\Archive::PATH
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/.htaccess', 'x' ) ] ) ) ) === \Nino\Archive::PATH
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/./a.txt', 'x' ) ] ) ) ) === \Nino\Archive::PATH );
check( 'a prefix of the header that climbs is a path that climbs: path', $refusal( $read( array_merge( $good, [ archiveEntry( 'x.txt', 'x', '0', '', 'pack/../..' ) ] ) ) ) === \Nino\Archive::PATH );
check( 'a second folder at the top, a file at the top and a folder that is not the one that was asked for are refused: folder',
	$refusal( $read( array_merge( $good, [ archiveEntry( 'other/x.txt', 'x' ) ] ) ) ) === \Nino\Archive::FOLDER
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'x.txt', 'x' ) ] ) ) ) === \Nino\Archive::FOLDER
	&& $refusal( $read( $good, 'another' ) ) === \Nino\Archive::FOLDER
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'PACK/x.txt', 'x' ) ] ), null ) ) === \Nino\Archive::FOLDER );
check( 'a path of seven segments below the folder is too deep: depth', $refusal( $read( [ archiveEntry( 'pack/a/b/c/d/e/f/g.txt', 'x' ) ] ) ) === \Nino\Archive::DEPTH );
check( 'a header whose checksum does not hold is no header: header',
	$refusal( \Nino\Archive::read( (string) gzencode( substr_replace( archiveTar( $good ), '0000000', 148, 7 ) ), 'pack', 40, 4096, 65536 ) ) === \Nino\Archive::HEADER );
check( 'the size of a folder is no size: header - a folder holds no data, and a reader that jumps over it finds no entry that another reader has',
	$refusal( $read( [ archiveEntry( 'pack/', '', '5', '', '', 512 ), archiveEntry( 'pack/hidden.txt', 'x' ) ] ) ) === \Nino\Archive::HEADER );
check( 'a tar that ends inside a file, and one that ends inside a header, are cut: truncated',
	$refusal( \Nino\Archive::read( (string) gzencode( substr( archiveTar( $good ), 0, 512 * 3 + 100 ) ), 'pack', 40, 4096, 65536 ) ) === \Nino\Archive::TRUNCATED
	&& $refusal( \Nino\Archive::read( (string) gzencode( substr( archiveTar( [ archiveEntry( 'pack/a.txt', 'x' ) ] ), 0, 512 + 512 + 100 ) ), 'pack', 40, 4096, 65536 ) ) === \Nino\Archive::TRUNCATED );
check( 'what is no gzip stream, a stream cut before its end and a stream that is damaged are no archive: gzip',
	$refusal( \Nino\Archive::read( archiveTar( $good ), 'pack', 40, 4096, 65536 ) ) === \Nino\Archive::GZIP
	&& $refusal( \Nino\Archive::read( substr( (string) gzencode( archiveTar( $good ) ), 0, -4 ), 'pack', 40, 4096, 65536 ) ) === \Nino\Archive::GZIP
	&& $refusal( \Nino\Archive::read( '', 'pack', 40, 4096, 65536 ) ) === \Nino\Archive::GZIP );

// Bytes behind the gzip stream - a second stream, or anything - and behind the end of the tar
$stream = (string) gzencode( archiveTar( $good ) );
check( 'a second gzip stream behind the first, and anything behind it, are bytes no two readers agree on: trailing',
	$refusal( \Nino\Archive::read( $stream. gzencode( archiveTar( [ archiveEntry( 'pack/x.txt', 'x' ) ] ) ), 'pack', 40, 4096, 65536 ) ) === \Nino\Archive::TRAILING
	&& $refusal( \Nino\Archive::read( $stream. 'more', 'pack', 40, 4096, 65536 ) ) === \Nino\Archive::TRAILING );
check( 'an entry behind the block of nothing that ends the tar is one a tar that goes on reads and one that stops does not: trailing',
	$refusal( \Nino\Archive::read( (string) gzencode( archiveTar( $good ). archiveEntry( 'pack/late.txt', 'x' ) ), 'pack', 40, 4096, 65536 ) ) === \Nino\Archive::TRAILING );

// A mountain made of nothing is stopped while it is inflated, and what it would have been is never in memory
$deflate = deflate_init( ZLIB_ENCODING_GZIP, [ 'level' => 9 ] );
$mountain = '';
$block = str_repeat( "\0", 1048576 );

for( $round = 0; $round < 32; $round++ )
	$mountain .= deflate_add( $deflate, $block, ZLIB_NO_FLUSH );

$mountain .= deflate_add( $deflate, '', ZLIB_FINISH );
unset( $deflate, $block );
memory_reset_peak_usage();
$floor = memory_get_usage();
$answer = \Nino\Archive::read( $mountain, 'pack', 40, 4096, 65536 );
$grown = memory_get_peak_usage() - $floor;
echo '  ('. round( strlen( $mountain ) / 1024 ). ' KB of stream unpack to 32 MB; refused with the memory '. round( $grown / 1048576, 1 ). " MB over where it stood)\n";
check( 'a stream that unpacks to 32 megabytes where the limits allow a few is stopped where it passes them, not read to its end: bomb, and the memory grows by less than half of what it would unpack to',
	$answer === \Nino\Archive::BOMB && $grown < 16 * 1048576 );
unset( $mountain );

// The names that are one name to a file system that folds the case
check( 'two names that differ in the case alone are one name for a Mac: duplicate - in a file, in a folder and in a file against a folder',
	$refusal( $read( array_merge( $good, [ archiveEntry( 'pack/PACK.JSON', 'x' ) ] ) ) ) === \Nino\Archive::DUPLICATE
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/Parts/', '', '5' ) ] ) ) ) === \Nino\Archive::DUPLICATE
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/PARTS', 'x' ) ] ) ) ) === \Nino\Archive::DUPLICATE
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/pack.json', 'x' ) ] ) ) ) === \Nino\Archive::DUPLICATE );
check( 'a file below a file is a name twice as well: duplicate', $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/pack.json/inner.txt', 'x' ) ] ) ) ) === \Nino\Archive::DUPLICATE );
check( 'the folder twice is refused: duplicate', $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/', '', '5' ) ] ) ) ) === \Nino\Archive::DUPLICATE );

// The limits of the caller
$bytes = array_sum( array_map( 'strlen', (array) $files ) );
check( 'more entries than the caller allows - folders count - are refused at the one that is too many: count',
	$refusal( $read( $good, 'pack', 6 ) ) === 'a good archive' && $refusal( $read( $good, 'pack', 5 ) ) === \Nino\Archive::COUNT );
check( 'a file larger than the caller allows, and files that are too large together, are refused: file-size, total-size',
	$refusal( $read( $good, 'pack', 40, 700 ) ) === 'a good archive' && $refusal( $read( $good, 'pack', 40, 699 ) ) === \Nino\Archive::FILE_SIZE
	&& $refusal( $read( $good, 'pack', 40, 4096, $bytes ) ) === 'a good archive' && $refusal( $read( $good, 'pack', 40, 4096, $bytes - 1 ) ) === \Nino\Archive::TOTAL_SIZE );

// The names the caller allows
$only = static fn( string $path ): bool => str_ends_with( $path, '.json' ) === true || str_ends_with( $path, '.css' ) === true;
check( 'a file the pattern or the callback of the caller does not allow is refused whatever else it is: name - and the files that they allow pass',
	$refusal( $read( $good, 'pack', 40, 4096, 65536, '~\.(?:json|css)$~' ) ) === 'a good archive' && $refusal( $read( $good, 'pack', 40, 4096, 65536, $only ) ) === 'a good archive'
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/evil.php', '<?php' ) ] ), 'pack', 40, 4096, 65536, '~\.(?:json|css)$~' ) ) === \Nino\Archive::NAME
	&& $refusal( $read( array_merge( $good, [ archiveEntry( 'pack/parts/evil.php', '<?php' ) ] ), 'pack', 40, 4096, 65536, $only ) ) === \Nino\Archive::NAME );

echo "\n";

ninoDone( ninoSandbox( 'archive' ) );
