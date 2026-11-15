<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	guide-smoke.php			Dependency-free smoke test for the developer guide in docs/guide/:
 *											the overview lists every tutorial and every tutorial is listed, the
 *											tutorials are numbered without a gap and share one outline, every
 *											php block of a tutorial passes php -l - a whole file as it stands,
 *											a fragment as the members of a class, the entries of an array or the
 *											statements of a method -, every js block passes node --check, and
 *											every relative link of the guide lands on a file that is there and,
 *											where it names one, on a heading of that file. It holds the guide to its structure and to nothing it
 *											says: no word of it is compared.
 *											Reads the checkout and writes its fragments to the temp directory,
 *											never touches a project.
 *
 *	Usage: php tests/guide-smoke.php
 */

require __DIR__. '/harness.php';

$root		= dirname( __DIR__ );
$guide	= $root. '/docs/guide';

echo "Guide: $guide\n\n";

/*	A document without its fenced blocks, which hold code and no link or heading of the document	*/
$prose = static function( string $markdown ): string {
	return (string) preg_replace( '/^```.*?^```[^\n]*$/ms', '', $markdown );
};

/*	The headings of a document as the anchors a link names: lower case, everything but letters, digits,
	spaces, hyphens and underscores dropped, a space a hyphen, and a number behind one that stands twice	*/
$anchors = static function( string $markdown ) use ( $prose ): array {

	$found = [];

	preg_match_all( '/^#{1,6}[ \t]+(.+?)[ \t]*$/m', $prose( $markdown ), $headings );

	foreach( $headings[1] as $heading ) {

		$slug = str_replace( ' ', '-', (string) preg_replace( '/[^\p{L}\p{N} _-]/u', '', mb_strtolower( $heading, 'UTF-8' ) ) );
		$name = $slug;

		for( $twin = 1; in_array( $name, $found, true ) === true; $twin++ )
			$name = $slug. '-'. $twin;

		$found[] = $name;
	}

	return $found;
};

/*	The headings of the second level, in their order	*/
$sections = static function( string $markdown ) use ( $prose ): array {
	preg_match_all( '/^##[ \t]+(.+?)[ \t]*$/m', $prose( $markdown ), $headings );
	return $headings[1];
};

/*	Whether php -l takes a piece of php: the file is written to the temp directory and removed	*/
$lints = static function( string $code ): bool {

	$file = tempnam( sys_get_temp_dir(), 'nino-guide-' );

	if( $file === false || file_put_contents( $file, $code ) === false )
		return false;

	$output = [];
	$status = 1;
	exec( escapeshellarg( PHP_BINARY ). ' -l '. escapeshellarg( $file ). ' 2>&1', $output, $status );
	unlink( $file );

	return $status === 0;
};

/*	A block that opens with the php tag is a file. Any other is a fragment, and has to be one of the three
	things a tutorial shows a part of: members of a class, entries of an array, statements of a method	*/
$parses = static function( string $code ) use ( $lints ): bool {

	if( str_starts_with( ltrim( $code ), '<?php' ) === true )
		return $lints( $code );

	foreach( [ "<?php\nclass GuideFragment {\n%s\n}\n", "<?php\nreturn [\n%s\n];\n", "<?php\nclass GuideFragment {\nfunction fragment() {\n%s\n}\n}\n" ] as $frame )
		if( $lints( sprintf( $frame, $code ) ) === true )
			return true;

	return false;
};

// ---- The overview and the tutorials ---------------------------------------------------------------

$files = glob( $guide. '/*.md' );
$files = $files === false ? [] : array_map( 'basename', $files );
sort( $files );

$tutorials = array_values( array_filter( $files, static fn( string $file ): bool => preg_match( '/^\d\d-[a-z0-9-]+\.md$/D', $file ) === 1 ) );

check( 'the guide is an overview and tutorials, and nothing else', in_array( 'README.md', $files, true ) === true && count( $tutorials ) >= 10 && count( $files ) === count( $tutorials ) + 1 );

$numbers = array_map( static fn( string $file ): int => (int) substr( $file, 0, 2 ), $tutorials );
check( 'the tutorials are numbered from 01 on without a gap', $numbers === range( 1, count( $tutorials ) ) );

$overview = is_file( $guide. '/README.md' ) === true ? (string) file_get_contents( $guide. '/README.md' ) : '';
preg_match_all( '/^\d+\.[ \t]+\[[^\]]+\]\(([^)#]+)\)[ \t]*$/m', $prose( $overview ), $listed );
check( 'the overview lists every tutorial once, in the order of their numbers', $listed[1] === $tutorials );

$manual = (string) file_get_contents( $root. '/docs/development.md' );
check( 'the developer manual names the guide in its head', preg_match( '/\]\(guide\/README\.md\)/', (string) substr( $manual, 0, (int) strpos( $manual, "\n## " ) ) ) === 1 );

// ---- One outline ----------------------------------------------------------------------------------

$outline = null;

foreach( $tutorials as $tutorial ) {

	$markdown	= (string) file_get_contents( $guide. '/'. $tutorial );
	$own			= $sections( $markdown );
	$tail			= array_slice( $own, -3 );

	// The first tutorial says how a tutorial ends: what is seen, what goes wrong, where to read on
	$outline ??= $tail;

	check( "$tutorial has steps and ends on the three sections every tutorial ends on", count( $own ) >= 4 && $tail === $outline );

	// The section before the last one lists the mistakes: two to four of them
	$from		= strpos( $markdown, "\n## ". ( $tail[1] ?? "\0" ) );
	$until	= strpos( $markdown, "\n## ". ( $tail[2] ?? "\0" ) );
	$count	= $from === false || $until === false ? 0 : preg_match_all( '/^- /m', substr( $markdown, $from, $until - $from ) );
	check( "...and names two to four mistakes", $count >= 2 && $count <= 4 );

	// The head of a tutorial leads to the overview and to its neighbours
	$head		= (string) substr( $markdown, 0, (int) strpos( $markdown, "\n## " ) );
	$index	= (int) array_search( $tutorial, $tutorials, true );
	$wanted	= array_filter( [ 'README.md', $tutorials[$index - 1] ?? null, $tutorials[$index + 1] ?? null ] );
	check( "...and links the overview and the tutorials beside it", array_filter( $wanted, static fn( string $file ): bool => str_contains( $head, ']('. $file. ')' ) === false ) === [] );
}

// ---- The php of the tutorials ---------------------------------------------------------------------

foreach( $tutorials as $tutorial ) {

	preg_match_all( '/^```php[^\n]*\n(.*?)^```[ \t]*$/ms', (string) file_get_contents( $guide. '/'. $tutorial ), $blocks );

	$broken = [];
	foreach( $blocks[1] as $number => $code )
		if( $parses( $code ) === false )
			$broken[] = $number + 1;

	check( "$tutorial shows php, and php -l takes all of it". ( $broken === [] ? '' : ' - not block '. implode( ', ', $broken ) ), $blocks[1] !== [] && $broken === [] );
}

// ---- The scripts of the tutorials -----------------------------------------------------------------

$nodeOutput = [];
$nodeStatus = 1;
exec( 'node --version 2>&1', $nodeOutput, $nodeStatus );

if( $nodeStatus !== 0 )
	echo "  --  - node is not here: the js blocks are not checked\n";
else
	foreach( $tutorials as $tutorial ) {

		preg_match_all( '/^```js[^\n]*\n(.*?)^```[ \t]*$/ms', (string) file_get_contents( $guide. '/'. $tutorial ), $blocks );

		if( $blocks[1] === [] )
			continue;

		$broken = [];

		foreach( $blocks[1] as $number => $code ) {

			$file = sys_get_temp_dir(). '/nino-guide-'. uniqid(). '.js';

			if( file_put_contents( $file, $code ) === false ) {
				$broken[] = $number + 1;
				continue;
			}

			$output = [];
			$status = 1;
			exec( 'node --check '. escapeshellarg( $file ). ' 2>&1', $output, $status );
			unlink( $file );

			if( $status !== 0 )
				$broken[] = $number + 1;
		}

		check( "node --check takes every js block of $tutorial". ( $broken === [] ? '' : ' - not block '. implode( ', ', $broken ) ), $broken === [] );
	}

// ---- The links ------------------------------------------------------------------------------------

$known = [];

foreach( $files as $file ) {

	$markdown = (string) file_get_contents( $guide. '/'. $file );
	preg_match_all( '/\]\(([^)\s]+)\)/', $prose( $markdown ), $links );

	$dead = [];

	foreach( array_unique( $links[1] ) as $link ) {

		// An address of the web is not the guide's to answer for
		if( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $link ) === 1 )
			continue;

		[ $path, $anchor ] = array_pad( explode( '#', $link, 2 ), 2, '' );

		$target = $path === '' ? $guide. '/'. $file : $guide. '/'. $path;

		if( is_file( $target ) === false ) {
			$dead[] = $link;
			continue;
		}

		if( $anchor === '' )
			continue;

		$real = (string) realpath( $target );
		$known[$real] ??= $anchors( (string) file_get_contents( $real ) );

		if( in_array( $anchor, $known[$real], true ) === false )
			$dead[] = $link;
	}

	check( "every relative link of $file lands on a file and a heading". ( $dead === [] ? '' : ' - not '. implode( ', ', $dead ) ), $links[1] !== [] && $dead === [] );
}

$none = [];
ninoDone( $none );
