<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	archive-smoke.php		Dependency-free smoke test for \Nino\Archive, the reader and the writer of a tar.gz in memory: what a good
 *											archive of one folder with folders in it answers, and one case for each thing it refuses - a link, a
 *											device, a path that climbs, a second folder, a depth, a name that a pax header or the GNU tar gives
 *											another way, a checksum, a stream that unpacks to a mountain, bytes behind the gzip stream and behind
 *											the tar, a name twice in the spelling a Mac folds together, the limits of the caller and the names it
 *											allows. The archives are written here byte by byte: no tar of the machine, no file on a disk. Then write(): what it
 *											writes is read back by read() as it was, is the same bytes for the same files, a package of blocks it writes is one
 *											\Nino\Packages takes, a tar of the machine lists it, and what read() refuses is refused at the writing.
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
	'FILE_SIZE' => 'file-size', 'TOTAL_SIZE' => 'total-size', 'LONG' => 'long',
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


echo "\nWriting an archive\n";

$write = static fn( array $files, string $folder = 'pack', int $count = 40, int $each = 4096, int $total = 65536, int $time = 0, string|Closure|null $allow = null ): string|array
	=> \Nino\Archive::write( $folder, $files, $count, $each, $total, $time, $allow );
$back = static function( string|array $bytes, string $folder = 'pack' ): array|string {
	$files = is_string( $bytes ) === true ? \Nino\Archive::read( $bytes, $folder, 40, 4096, 65536 ) : $bytes['code'];
	is_array( $files ) === true && ksort( $files, SORT_STRING );
	return $files;
};
$refused = static fn( string|array $answer ): string => is_array( $answer ) === true ? $answer['code'] : 'an archive';

$all = '';

for( $byte = 0; $byte < 256; $byte++ )
	$all .= chr( $byte );

$deepest = 'a/b/c/d/e/f.txt';
$set = [ 'pack.json' => '{"key":"pack"}', 'binary.bin' => $all, 'empty.txt' => '', 'umlaut.txt' => "Gr\u{fc}\u{df}e, \u{4e16}\u{754c}, \u{1f600}\n", $deepest => 'deep', 'parts/section/part.css' => ".s { color: red; }\n" ];
ksort( $set, SORT_STRING );
$bytes = $write( $set );
check( 'files of text, of all 256 bytes, an empty one, umlauts in the content and one at the deepest path come back from read() byte for byte, with no refusal', $back( $bytes ) === $set );
check( 'the same files give the same bytes, in whatever order they were handed over, and the time given is in them', $bytes === $write( array_reverse( $set ) ) && $bytes !== $write( $set, 'pack', 40, 4096, 65536, 1700000000 )
	&& $back( $write( $set, 'pack', 40, 4096, 65536, 1700000000 ) ) === $set );

$tar = (string) gzdecode( (string) $bytes );
$header = static fn( int $at ): array => [ 'name' => rtrim( substr( $tar, $at, 100 ), "\0" ), 'mode' => substr( $tar, $at + 100, 8 ), 'owner' => substr( $tar, $at + 108, 16 ), 'time' => substr( $tar, $at + 136, 12 ),
	'type' => $tar[$at + 156], 'magic' => substr( $tar, $at + 257, 8 ), 'names' => substr( $tar, $at + 265, 64 ) ];
$names = [];

for( $at = 0; $at < strlen( $tar ) && $tar[$at] !== "\0"; $at += 512 + (int) ceil( octdec( substr( $tar, $at + 124, 11 ) ) / 512 ) * 512 )
	$names[$header( $at )['name']] = $at;

check( 'the stream has a header that says nothing of a machine - no flag, no time, no name, no level, Unix - and the tar is ustar: the folder first at 0755, a file at 0644, owner 0 and no names, the time 0, whole blocks and two blocks of nothing at the end',
	substr( (string) $bytes, 0, 10 ) === "\x1f\x8b\x08\0\0\0\0\0\0\x03" && strlen( $tar ) % 512 === 0 && str_ends_with( $tar, str_repeat( "\0", 1024 ) )
	&& $header( $names['pack/'] )['name'] === 'pack/' && $header( $names['pack/'] )['type'] === '5' && $header( $names['pack/'] )['mode'] === "0000755\0" && $header( $names['pack/pack.json'] )['type'] === '0' && $header( $names['pack/pack.json'] )['mode'] === "0000644\0"
	&& $header( $names['pack/pack.json'] )['magic'] === "ustar\0". '00' && $header( $names['pack/pack.json'] )['owner'] === "0000000\0". "0000000\0" && $header( $names['pack/pack.json'] )['names'] === str_repeat( "\0", 64 ) && $header( $names['pack/pack.json'] )['time'] === "00000000000\0" );
check( 'the folders below the folder are written before the first file in them, each once however many files it holds, in the order of the paths',
	array_keys( $names ) === [ 'pack/', 'pack/a/', 'pack/a/b/', 'pack/a/b/c/', 'pack/a/b/c/d/', 'pack/a/b/c/d/e/', 'pack/a/b/c/d/e/f.txt', 'pack/binary.bin', 'pack/empty.txt', 'pack/pack.json', 'pack/parts/', 'pack/parts/section/', 'pack/parts/section/part.css', 'pack/umlaut.txt' ] );
check( 'a path longer than a name field is carried by the prefix of the header, and read() reads it back; one that no prefix can carry is refused: long',
	$back( $write( [ str_repeat( 'a', 60 ). '/'. str_repeat( 'b', 60 ). '.txt' => 'x' ] ) ) === [ str_repeat( 'a', 60 ). '/'. str_repeat( 'b', 60 ). '.txt' => 'x' ]
	&& $refused( $write( [ str_repeat( 'a', 81 ). '/'. str_repeat( 'b', 81 ). '/'. str_repeat( 'c', 81 ). '/'. str_repeat( 'd', 70 ). '.txt' => 'x' ], str_repeat( 'f', 81 ) ) ) === \Nino\Archive::LONG );

// A tar of the machine, where there is one: it reads the archive from its standard input, so that no file is written
$listed = [];
$status = -1;
$pipes = [];
$process = is_executable( '/usr/bin/tar' ) === true || is_executable( '/bin/tar' ) === true
	? proc_open( [ is_executable( '/usr/bin/tar' ) === true ? '/usr/bin/tar' : '/bin/tar', '-tzf', '-' ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes ) : false;

if( is_resource( $process ) === false )
	echo "  (skipped: no tar on this machine)\n";
else {

	fwrite( $pipes[0], (string) $bytes );
	fclose( $pipes[0] );
	$listed = array_filter( explode( "\n", (string) stream_get_contents( $pipes[1] ) ) );
	$complaint = (string) stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$status = proc_close( $process );
	check( 'a tar of the machine lists what was written: the folder, the folders below it and the files, with no complaint',
		$status === 0 && $complaint === '' && in_array( 'pack/', $listed, true ) === true && in_array( 'pack/'. $deepest, $listed, true ) === true && in_array( 'pack/a/b/', $listed, true ) === true && count( $listed ) === 14 );
}

// What read() refuses is refused at the writing, by the same code
check( 'a path that climbs, one from the root, one of ./, one with an empty segment, a NUL byte or a backslash, and a hidden name, are no paths: path',
	$refused( $write( [ '../x.txt' => 'x' ] ) ) === \Nino\Archive::PATH && $refused( $write( [ '/etc/x.txt' => 'x' ] ) ) === \Nino\Archive::PATH && $refused( $write( [ './x.txt' => 'x' ] ) ) === \Nino\Archive::PATH
	&& $refused( $write( [ 'a//x.txt' => 'x' ] ) ) === \Nino\Archive::PATH && $refused( $write( [ "a.txt\0evil.php" => 'x' ] ) ) === \Nino\Archive::PATH && $refused( $write( [ 'a\\x.txt' => 'x' ] ) ) === \Nino\Archive::PATH
	&& $refused( $write( [ '.htaccess' => 'x' ] ) ) === \Nino\Archive::PATH && $refused( $write( [ 'a/' => 'x' ] ) ) === \Nino\Archive::PATH && $refused( $write( [ '' => 'x' ] ) ) === \Nino\Archive::PATH
	&& $refused( $write( [ 123 => 'x' ] ) ) === \Nino\Archive::PATH );
check( 'a folder that is no plain name is no folder: folder', $refused( $write( $set, '../pack' ) ) === \Nino\Archive::FOLDER && $refused( $write( $set, 'a/b' ) ) === \Nino\Archive::FOLDER && $refused( $write( $set, '' ) ) === \Nino\Archive::FOLDER );
check( 'a path of seven segments below the folder is too deep: depth', $refused( $write( [ 'a/b/c/d/e/f/g.txt' => 'x' ] ) ) === \Nino\Archive::DEPTH );
check( 'a name twice in the case alone is refused, for a file, for a folder and for a file against a folder: duplicate',
	$refused( $write( [ 'a.txt' => 'x', 'A.TXT' => 'y' ] ) ) === \Nino\Archive::DUPLICATE && $refused( $write( [ 'dir/a.txt' => 'x', 'Dir/b.txt' => 'y' ] ) ) === \Nino\Archive::DUPLICATE
	&& $refused( $write( [ 'dir' => 'x', 'DIR/b.txt' => 'y' ] ) ) === \Nino\Archive::DUPLICATE && $refused( $write( [ 'dir/a.txt' => 'x', 'DIR' => 'y' ] ) ) === \Nino\Archive::DUPLICATE );
check( 'a file that is a folder of another file is refused: duplicate - and a folder that holds two files is the one folder', $refused( $write( [ 'a' => 'x', 'a/b.txt' => 'y' ] ) ) === \Nino\Archive::DUPLICATE
	&& $refused( $write( [ 'a/b.txt' => 'x', 'a/c.txt' => 'y' ] ) ) === 'an archive' );
check( 'a content that is no string is no file: type', $refused( $write( [ 'a.txt' => 5 ] ) ) === \Nino\Archive::TYPE && $refused( $write( [ 'a.txt' => [ 'x' ] ] ) ) === \Nino\Archive::TYPE );
check( 'more entries than the caller allows - the folder, the folders below it and the files - are refused, and the number that read() counts is the one that passes: count',
	$refused( $write( $set, 'pack', 13 ) ) === \Nino\Archive::COUNT && $refused( $write( $set, 'pack', 14 ) ) === 'an archive' && is_array( \Nino\Archive::read( (string) $write( $set, 'pack', 14 ), 'pack', 14, 4096, 65536 ) ) === true );
check( 'a file larger than the caller allows, and files that are too large together, are refused: file-size, total-size - at the byte that read() would refuse them at',
	$refused( $write( [ 'a.txt' => str_repeat( 'x', 11 ) ], 'pack', 40, 10 ) ) === \Nino\Archive::FILE_SIZE && $refused( $write( [ 'a.txt' => str_repeat( 'x', 10 ) ], 'pack', 40, 10 ) ) === 'an archive'
	&& $refused( $write( [ 'a.txt' => 'xxxxx', 'b.txt' => 'xxxxx' ], 'pack', 40, 10, 9 ) ) === \Nino\Archive::TOTAL_SIZE && $refused( $write( [ 'a.txt' => 'xxxxx', 'b.txt' => 'xxxxx' ], 'pack', 40, 10, 10 ) ) === 'an archive' );
check( 'a file the pattern or the callback of the caller does not allow is refused: name', $refused( $write( $set, 'pack', 40, 4096, 65536, 0, '~\.json$~' ) ) === \Nino\Archive::NAME
	&& $refused( $write( $set, 'pack', 40, 4096, 65536, 0, static fn( string $path ): bool => $path !== 'umlaut.txt' ) ) === \Nino\Archive::NAME && $refused( $write( $set, 'pack', 40, 4096, 65536, 0, '~.~' ) ) === 'an archive' );
check( 'a time that no header can hold is refused: header', $refused( $write( $set, 'pack', 40, 4096, 65536, -1 ) ) === \Nino\Archive::HEADER && $refused( $write( $set, 'pack', 40, 4096, 65536, 8589934592 ) ) === \Nino\Archive::HEADER );
check( 'a refusal names the path it is about, and no archive comes with it', ( $write( [ 'ok.txt' => 'x', 'a/../b.txt' => 'x' ] )['path'] ?? '' ) === 'a/../b.txt' );

// The folders are held to the rules of the entries as the files are, and what write() takes read() reads: a fixed set of sets, from a seed
check( 'a folder of digits alone below the folder is no entry read() takes, and write() refuses it as it does a file of digits: path - deeper down it is a name like another',
	$refused( $write( [ '12/a.txt' => 'x' ] ) ) === \Nino\Archive::PATH && $refused( $write( [ '0/a.txt' => 'x' ] ) ) === \Nino\Archive::PATH && $refused( $write( [ 'a/2024/x.txt' => 'x', 'b/12' => 'y' ] ) ) === 'an archive'
	&& is_array( \Nino\Archive::read( (string) $write( [ 'a/2024/x.txt' => 'x' ] ), 'pack', 40, 4096, 65536 ) ) === true );
check( 'a limit of no entry holds not even the folder: count, for write() and for read() alike', $refused( $write( [], 'pack', 0 ) ) === \Nino\Archive::COUNT
	&& \Nino\Archive::read( (string) $write( [] ), 'pack', 0, 4096, 65536 ) === \Nino\Archive::COUNT && $refused( $write( [], 'pack', 1 ) ) === 'an archive' );
check( 'the folder at the top is a plain segment: of the names read() would take, one that begins with a dot or is longer than 81 characters is refused here: folder',
	$refused( $write( $set, '.hidden' ) ) === \Nino\Archive::FOLDER && $refused( $write( $set, str_repeat( 'f', 82 ) ) ) === \Nino\Archive::FOLDER );

// 300 sets of names from a fixed seed: whatever write() takes, read() reads without a refusal and gives the same files; the set that is refused is no archive
$seed = 262;
$next = static function( int $to ) use ( &$seed ): int {
	$seed = ( $seed * 1103515245 + 12345 ) & 0x7fffffff;
	return ( $seed >> 8 ) % $to;
};
$atoms = [ 'a', 'B', 'b', 'A', 'x1', '12', '0', '2024', 'dir', 'Dir', 'a.txt', 'A.TXT', '.hid', '..', 'a b', 'z_-9', 'f.json' ];
$taken = 0;
$mismatch = 0;

for( $round = 0; $round < 300; $round++ ) {

	$fuzz = [];

	for( $n = $next( 6 ); $n >= 0; $n-- ) {

		$segments = [];

		for( $d = 1 + $next( 4 ); $d > 0; $d-- )
			$segments[] = $atoms[$next( count( $atoms ) )];

		$fuzz[implode( '/', $segments )] = str_repeat( chr( $next( 256 ) ), $next( 3 ) * 300 );
	}

	$made = $write( $fuzz, 'pack', 12, 4096, 65536 );

	if( is_array( $made ) === true )
		continue;

	$taken++;
	$read = \Nino\Archive::read( $made, 'pack', 12, 4096, 65536 );
	is_array( $read ) === true && ksort( $read, SORT_STRING );
	ksort( $fuzz, SORT_STRING );
	$mismatch += $read === $fuzz ? 0 : 1;
}

echo "  ($taken of 300 sets taken, $mismatch not read back)\n";
check( 'of 300 sets from a fixed seed, those that write() takes are read back by read() without a refusal and byte for byte, and some are taken and some refused', $mismatch === 0 && $taken > 20 && $taken < 300 );

// The level of the gzip stream and the split of a long path, by what they leave in the bytes
$long = implode( '/', array_fill( 0, 5, str_repeat( 'q', 20 ) ) );
$tarLong = (string) gzdecode( (string) $write( [ $long => 'x' ] ) );
$at = 512 * 5;
check( 'the stream is the one the best level makes of the tar, and a path of 109 bytes is cut at its last slash: the prefix holds the folders, the name the file',
	$bytes === substr_replace( (string) gzencode( $tar, 9 ), "\0\0\0\0\0\x03", 4, 6 ) && substr( $tarLong, $at + 345, 5 + 4 * 21 - 1 ) === 'pack/'. implode( '/', array_fill( 0, 4, str_repeat( 'q', 20 ) ) )
	&& rtrim( substr( $tarLong, $at, 100 ), "\0" ) === str_repeat( 'q', 20 ) );

// A package the writer made is one the kernel takes: a package of blocks with a slot for a svg and one for a webp, its preview, its texts and its licence; and a theme
$app = ninoSandbox( 'archive' );
$app['./nino/filesystem/path'] = dirname( __DIR__ );
$app['/nino/modules'] = [ '\\Nino\\Modules\\Basis' ];
$app['/nino/html/navs'] = [ 'main', 'footer' ];
\Nino\Features::registerComponents( $app );

foreach( [ 'template', 'assets', 'jstext', 'legal', 'privacy', 'json', 'imagevalues', 'csrf' ] as $shortcode )
	\Nino\Html::addShortcode( $app, $shortcode, static fn(): string => '', null );

$webp = (string) base64_decode( 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==' );
$svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect id="a" width="10" height="10" fill="#f00"/></svg>';
$words = static fn( string $de, string $en ): array => [ 'de_DE' => $de, 'en_US' => $en ];
$slot = static fn( string $label ): array => [ 'placeholder' => '/nino/placeholder/landscape', 'width' => 800, 'height' => 600, 'label' => $words( $label, $label ) ];
$block = [
	'block.json'				=> json_encode( [ 'name' => $words( 'Probe', 'Probe' ), 'description' => $words( 'Eine Probe', 'A probe' ), 'category' => 'content', 'rank' => 5, 'images' => [ 'background' => $slot( 'Hintergrund' ), '1-photo' => $slot( 'Foto' ) ] ] ),
	'block.tpl'					=> "[section-wrap id=\"probe\" image=\"~background\"]\n\t[section-row]\n\t\t[section-col id=\"probe-1\"]\n\t\t\t[section-title ~title]\n\t\t\t[image ~photo alt=\"\"]\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]",
	'text/de_DE.json'		=> json_encode( [ '1-title' => 'Titel' ] ),
	'text/en_US.json'		=> json_encode( [ '1-title' => 'Title' ] ),
	'images/background.svg'	=> $svg,
	'images/1-photo.webp'		=> $webp,
];
$pack = [ 'pack.json' => json_encode( [ 'key' => 'probe-pack', 'version' => '1.0.0', 'name' => $words( 'Probepaket', 'Probe pack' ), 'description' => 'Probe' ] ), 'preview.webp' => $webp, 'LICENSE.txt' => "Probe licence\n" ];
$theme = [
	'theme.json'							=> json_encode( [ 'key' => 'craft-probe', 'version' => '1.0.0', 'name' => $words( 'Craft', 'Craft' ), 'description' => $words( 'Weich', 'Soft' ), 'brand' => [] ] ),
	'parts/page/part.json'		=> json_encode( [ 'contract' => 'kernel@^2.0', 'knobs' => [ 'radius' => [ 'label' => $words( 'Rundung', 'Radius' ), 'ladder' => [
		[ '--page-radius' => '.375rem' ], [ '--page-radius' => '.625rem' ], [ '--page-radius' => '1rem' ], [ '--page-radius' => '1.5rem' ], [ '--page-radius' => '2rem' ] ] ] ] ] ),
	'parts/button/part.json'	=> json_encode( [ 'contract' => 'basis@^1.0', 'ladders' => [ 'button.radius' => [ 'var(--page-radius)', 'calc( var(--page-radius) * 1.5 )', '999px', '999px', '999px' ] ] ] ),
	'parts/button/part.css'		=> "/*	buttons - a soft shadow	*/\n.button:not( .button--tone-quiet ) { box-shadow: 0 2px 6px rgb( 0 0 0 / .15 ); }\n",
	'preview.webp'						=> $webp,
	'LICENSE.txt'							=> "Probe licence\n",
	'README.md'								=> "# Craft\n",
];

foreach( $block as $file => $content ) {
	$pack['probe/'. $file] = $content;
	$theme['blocks/probe/'. $file] = $content;
}

foreach( [ \Nino\Packages::BLOCKS => [ 'probe-pack', $pack ], \Nino\Packages::THEME => [ 'craft-probe', $theme ] ] as $kind => [ $key, $files ] ) {

	$limits = \Nino\Packages::LIMITS[$kind];
	$archive = \Nino\Archive::write( $key, $files, $limits['files'], \Nino\Blocks::MAX_PICTURE, $limits['bytes'] );
	$read = is_string( $archive ) === true ? \Nino\Archive::read( $archive, $key, $limits['files'], \Nino\Blocks::MAX_PICTURE, $limits['bytes'] ) : 'not written';
	is_array( $read ) === true && ksort( $read, SORT_STRING );
	ksort( $files, SORT_STRING );
	check( 'a '. $kind. ' package with a svg and a webp slot, a preview, texts and a licence, written and read back, is the same files, is taken by Packages::check() and says the identity that was written, in an archive the catalogue takes',
		$read === $files && \Nino\Packages::check( $app, $kind, $read ) === null && \Nino\Packages::identity( $kind, $read ) === [ 'key' => $key, 'version' => $kind === \Nino\Packages::THEME ? '1.0.0' : '1.0.0' ]
		&& strlen( (string) $archive ) <= \Nino\Catalogue::ARCHIVE_BYTES[$kind] );
}

echo "\n";

ninoDone( $app );