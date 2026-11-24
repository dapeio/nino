<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	backup-lexer-smoke.php	A differential check of the reader that judges whether a php file of an archive is
 *												data (\Nino\Backup::_isData(), see codeRefusal()): variants are made from pieces, with
 *												tricks and with pieces that are code, from a fixed seed, and every one the reader lets
 *												through must be - for php's own tokenizer - nothing but the allowed tokens. A file
 *												the reader refuses proves nothing here; the numbers of both are counted so that
 *												neither set is empty.
 *
 *	Usage: php tests/backup-lexer-smoke.php
 */

require __DIR__. '/harness.php';

$stream = new ReflectionMethod( '\Nino\Backup', '_isData' );

/** Whether the tokens of php's tokenizer are those of a data file, and nothing else */
function lexerTokensAreData( string $source ): bool {

	$tokens = token_get_all( $source );
	$prev 	= '';
	$ended	= false;
	$inDecl = false;

	foreach( $tokens as $i => $token ) {

		$id 	= is_array( $token ) ? $token[0] : $token;
		$text = is_array( $token ) ? $token[1] : $token;

		if( $id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT ) {
			if( $id === T_COMMENT && str_starts_with( $text, '#[' ) )
				return false;
			continue;
		}

		if( $ended === true )
			return false;

		if( $i === 0 ) {
			if( $id !== T_OPEN_TAG )
				return false;
			continue;
		}

		switch( true ) {
			case $id === T_DECLARE: $inDecl = true; break;
			case $id === T_RETURN:
			case $id === T_ARRAY:
			case $id === T_DOUBLE_ARROW:
			case $id === T_LNUMBER:
			case $id === T_DNUMBER:
				break;
			case $id === T_STRING:
				if( $inDecl === true ? $text !== 'strict_types' : preg_match( '/^(?:true|false|null|INF|NAN)$/i', $text ) !== 1 )
					return false;
				break;
			case $id === T_CONSTANT_ENCAPSED_STRING:
				if( $text[0] !== "'" && $text !== '"\0"' )
					return false;
				break;
			case $id === '(':
				if( $prev !== T_ARRAY && $prev !== T_DECLARE )
					return false;
				break;
			case $id === '=':
				if( $inDecl === false )
					return false;
				break;
			case $id === ';':
				if( $inDecl === true )
					$inDecl = false;
				else
					$ended = true;
				break;
			case $id === ')' || $id === '[' || $id === ']' || $id === ',' || $id === '-' || $id === '.':
				break;
			default:
				return false;
		}

		$prev = $id;
	}

	return $ended === true;
}

/**
 *	The reader as it was before it read a file in pieces, which was given the whole of the text: the judge of the reader that reads
 *	a window of it. Its three patterns are the constants of the class
 */
function lexerOracle( string $source ): bool {

	$comment 	= '~\G(?://|#(?!\[))(?:(?!\?>)[^\r\n])*+|\G/\*(?:(?!\*/)[\s\S])*+\*/~';
	$declare 	= ( new ReflectionClassConstant( '\Nino\Backup', 'DECLARE' ) )->getValue();
	$token 		= ( new ReflectionClassConstant( '\Nino\Backup', 'DATA_TOKEN' ) )->getValue();

	if( preg_match( '/^<\?php\s/', $source ) !== 1 )
		return false;

	$at 			= 5;
	$length 	= strlen( $source );
	$phase 		= 0;
	$declared = false;

	while( $at < $length ) {

		if( preg_match( $comment, $source, $found, 0, $at ) === 1 ) {
			$at += strlen( $found[0] );
			continue;
		}

		if( $phase === 0 ) {

			if( preg_match( '/\G\s++/', $source, $found, 0, $at ) === 1 )
				$at += strlen( $found[0] );
			else if( $declared === false && preg_match( $declare, $source, $found, 0, $at ) === 1 ) {
				$declared = true;
				$at += strlen( $found[0] );
			}
			else if( preg_match( '/\Greturn(?=\s)/', $source, $found, 0, $at ) === 1 ) {
				$phase = 1;
				$at += 6;
			}
			else
				return false;

			continue;
		}

		if( preg_match( $token, $source, $found, 0, $at ) !== 1 )
			return false;

		if( $phase === 2 && trim( $found[0] ) !== '' )
			return false;

		if( $found[0] === ';' )
			$phase = 2;

		$at += strlen( $found[0] );
	}

	return $phase === 2;
}

/** The verdict of the reader that reads a file in pieces of the size given, over the bytes of a string */
function lexerWindowed( ReflectionMethod $stream, string $source, int $window ): bool {

	$handle = fopen( 'php://memory', 'r+b' );
	fwrite( $handle, $source );
	rewind( $handle );
	$verdict = $stream->invoke( null, $handle, $window );
	fclose( $handle );

	return $verdict;
}

mt_srand( 20261115 );

$pick = static fn( array $of ): string => $of[mt_rand( 0, count( $of ) - 1 )];

$opens 		= [ ...array_fill( 0, 12, "<?php\n" ), "<?php\n", '<?php ', "<?php\n", "<?php\r\n", '<?php ', "<?php\n", "<?php\n", '<?php ', "<?php\r\n", "<?php\t", '<?php', '<?PHP ', "<?=\n", "\n<?php\n", ' <?php ' ];
$declares = [ ...array_fill( 0, 10, 'declare(strict_types=1);' ), ...array_fill( 0, 10, '' ), '', '', '', '', 'declare(strict_types=1);', 'declare(strict_types=1);', 'declare( strict_types = 1 );', 'declare(strict_types=1);', 'declare( strict_types = 1 );', 'declare(strict_types=0);', 'declare(strict_types=2);', 'declare(strict_types=1) ;', 'declare(ticks=1);', 'declare(strict_types=1);declare(strict_types=1);' ];
$comments = [ ...array_fill( 0, 90, '' ), '', '', '', '', '', '', '', '', '', '', '', '', '', '', "// plain\n", "# hash\n", '/* block */', '/** doc */', "// ?> <?php system('id');\n", "// a\r", "// a\r\n", "// a\r system('id');\n", "/* unterminated ", "#[Attr]\n", "# ?>\n", '/* ?> */', "/* '*/ */", "// '\n", '/**/', "//\n", "// ?>\n" ];
$spaces 	= [ '', ' ', "\n", "\r\n", "\t", "  \n  " ];
$returns 	= [ ...array_fill( 0, 30, 'return' ), 'return', 'return', 'return', 'return', 'return', 'return', 'return', 'return', 'RETURN', 'returnx', 'ret'. 'urn', 'yield', 'exit' ];
$scalars 	= [ '1', '-2', '3.5', '-0.25', '1.0E+25', '1e-7', '0', 'true', 'false', 'null', 'NULL', 'INF', '-INF', 'NAN', "'a'", "'it\\'s'", "'back\\\\slash'", "'x ?> y'", "'/* no */'", "'// no'", "'\$var'", "'{\$x}'", "''. \"\\0\". ''" ];
$disturb 	= [ 'system(\'id\')', '$x', 'PHP_VERSION', '\\Nino\\Filesystem::CONTENT_DIR', '"double"', '"a$b"', "<<<X\nx\nX", '`id`', 'new stdClass', '?>', '?> text', "'system'('id')", '[ 1 ]()', 'array ( 1 )()', 'fn() => 1', 'function() {}', '@1', '!1', '1 + 1', '1 ? 2 : 3', '[ ...[ 1 ] ]', '$a = 1', 'print 1', 'include "x"', '__DIR__', 'static::X', '1;2', '"\\0"x', 'foo' ];

$array = static function( int $depth ) use ( &$array, $pick, $scalars, $disturb, $comments, $spaces ): string {

	$items = [];

	for( $n = mt_rand( 0, 4 ); $n > 0; $n-- ) {

		$value = $depth > 0 && mt_rand( 0, 3 ) === 0 ? $array( $depth - 1 ) : $pick( $scalars );

		if( mt_rand( 0, 60 ) === 0 )
			$value = $pick( $disturb );

		$key = mt_rand( 0, 1 ) === 0 ? '' : ( mt_rand( 0, 1 ) === 0 ? "'k". $n. "'" : (string) mt_rand( -3, 9 ) ). $pick( $spaces ). '=>'. $pick( $spaces );
		$items[] = $key. $value. $pick( $comments );
	}

	$inner = implode( ','. $pick( $spaces ), $items ). ( mt_rand( 0, 1 ) === 0 ? ',' : '' );

	return mt_rand( 0, 1 ) === 0 ? 'array ('. $pick( $spaces ). $inner. $pick( $spaces ). ')' : '['. $pick( $spaces ). $inner. $pick( $spaces ). ']';
};

$through = 0;
$refused = 0;
$wrong 	 = [];
$kinds 	 = [];
$sources = [];

for( $variant = 0; $variant < 1200; $variant++ ) {

	$body = mt_rand( 0, 5 ) === 0 ? $pick( $scalars ) : $array( 2 );

	if( mt_rand( 0, 30 ) === 0 )
		$body = $pick( $disturb );

	$tail = $pick( [ ...array_fill( 0, 20, ';' ), ';', ';', ';', ';', ';', ';', ';', ';', ';'. $pick( $spaces ), ';'. $pick( $spaces ), ';'. $pick( $comments ), '', ';;', '; echo 1;', ";\n?>\nhtml", '; ?>', ";\n// end ?> <?php 1;" ] );

	$source = $pick( $opens ). $pick( $comments ). $pick( $declares ). $pick( $spaces ). $pick( $comments ). $pick( $returns ). ( mt_rand( 0, 9 ) === 0 ? '' : ' ' ). $pick( $comments ). $body. $pick( $spaces ). $tail;

	$sources[] = $source;

	if( lexerWindowed( $stream, $source, 65536 ) === true ) {
		$through++;

		if( lexerTokensAreData( $source ) === false )
			$wrong[] = $source;
	}
	else
		$refused++;
}

check( 'the variants are made from a fixed seed, and both sets are there: '. $through. ' let through and '. $refused. ' refused of 1200', $through >= 100 && $refused >= 100 && $through + $refused === 1200 );
check( 'every variant the reader lets through is, for php\'s own tokenizer, nothing but the allowed tokens'. ( $wrong === [] ? '' : ' - first: '. json_encode( $wrong[0] ) ), $wrong === [] );

// The reader that reads a window of the file says what the reader of the whole text says, whatever the size of the window - tokens, strings and comments that stand across the end of a window included
$apart = [];
foreach( $sources as $source ) {

	$expected = lexerOracle( $source );

	foreach( [ 1, 2, 3, 5, 8, 13, 40, 65536 ] as $window )
		if( lexerWindowed( $stream, $source, $window ) !== $expected ) {
			$apart[] = [ $window, $source ];
			break;
		}
}
check( 'the reader of a window and the reader of the whole text agree on every one of the '. count( $sources ). ' variants, at eight sizes of the window'. ( $apart === [] ? '' : ' - first: '. json_encode( $apart[0] ) ), $apart === [] );

// What is long: a string, a comment and a run of blanks that are many windows
$long = [
	'a string of 300 KB'													=> "<?php return [ 'a' => '". str_repeat( 'x', 300000 ). "' ];",
	'a string with escapes across the windows'		=> "<?php return [ '". str_repeat( "ab\\'cd\\\\ef", 3000 ). "' ];",
	'a string that ends in a backslash and a quote' => "<?php return [ 'x\\' ];",
	'a string with a backslash before a line break' => "<?php return [ 'x\\\ny' ];",
	'a string after the semicolon'								=> "<?php return []; 'x'",
	'a string that is not closed'									=> "<?php return [ 'x". str_repeat( 'y', 70000 ). "];",
	'a block comment of 200 KB'										=> "<?php /* ". str_repeat( '*/ ', 1 ). str_repeat( 'c', 200000 ). " */ return [];",
	'a block comment that is not closed'					=> "<?php return []; /* ". str_repeat( 'c', 100000 ),
	'a line comment of 200 KB with the data behind it' => "<?php // ". str_repeat( 'c', 200000 ). "\nreturn [];",
	'a line comment that a closing tag ends, far in' => "<?php return []; // ". str_repeat( 'c', 70000 ). "?> [];",
	'a line comment to the end of the file'				=> "<?php return []; // ". str_repeat( 'c', 70000 ),
	'a run of blanks of 200 KB in an array'				=> "<?php return array". str_repeat( ' ', 200000 ). "( 1 );",
	'a run of blanks of 2 MB, which is refused'		=> "<?php return array". str_repeat( ' ', 2 * 1048576 ). "( 1 );",
	'a number of 100000 digits'										=> "<?php return [ ". str_repeat( '7', 100000 ). " ];",
	'a lot of small entries'											=> "<?php return [ ". str_repeat( "'k' => 1, ", 40000 ). "];",
];
$longWrong = [];
foreach( $long as $what => $source ) {

	$expected = $what === 'a run of blanks of 2 MB, which is refused' ? false : lexerOracle( $source );

	foreach( [ 4096, 65536 ] as $window )
		if( lexerWindowed( $stream, $source, $window ) !== $expected )
			$longWrong[] = $what. ' at '. $window;
}
check( 'what is long is read to the end in windows, and says what the reader of the whole says: '. count( $long ). ' sources'. ( $longWrong === [] ? '' : ' - '. implode( ', ', $longWrong ) ), $longWrong === [] );

// A file of 40 MB is read with the memory of a window
$handle = fopen( 'php://temp/maxmemory:1048576', 'r+b' );
fwrite( $handle, "<?php return [ 'a' => '" );
for( $piece = 0; $piece < 40; $piece++ )
	fwrite( $handle, str_repeat( 'x', 1048576 ) );
fwrite( $handle, "' ];" );
rewind( $handle );
memory_reset_peak_usage();
$base = memory_get_usage();
$verdict = $stream->invoke( null, $handle );
$peak = memory_get_peak_usage() - $base;
fclose( $handle );
check( 'a data file of 40 MB is read as data with a peak of a few windows', $verdict === true && $peak < 8 * 1048576 );

// The checker itself: it finds what it is there to find
$caught = [ "<?php return [ system('id') ];", "<?php return [ \$x ];", "<?php return \"a\";", "<?php return [];\n?>x", "<?php return [] ; echo 1;", "<?php #[A]\nreturn [];", "<?php return [ 'a' ]();" ];
$missed = array_filter( $caught, static fn( string $source ): bool => lexerTokensAreData( $source ) === true );
check( 'the checker refuses what it is there to find: a call, a variable, a double-quoted string, text after the tag, a statement after the semicolon, an attribute, a called array'. ( $missed === [] ? '' : ' - took: '. json_encode( $missed ) ), $missed === [] );
check( '...and takes what a data file is', lexerTokensAreData( "<?php\ndeclare(strict_types=1);\n// c\nreturn array (\n  'a' => \n  array (\n    0 => -1.5,\n  ),\n);\n" ) === true );

echo "\n". $through. " let through, ". $refused. " refused\n";

// Regressions the checker found or the review named: fixed cases
$cases = [
	'a line comment that a closing tag ends, valid data behind it' 			=> [ "<?php return []; // x ?> [ 1 ];", false ],
	'a line comment that a closing tag ends, a declare behind it' 			=> [ "<?php // x ?> declare(strict_types=1); return [];", false ],
	'a closing tag in a block comment is no closing tag' 								=> [ "<?php /* ?> */ return [];", true ],
	'the spread operator is three dots, and a dot is a concatenation only' 	=> [ "<?php return [ ...[ 1 ] ];", false ],
	'a second declare' 																									=> [ "<?php declare(strict_types=1); declare(strict_types=1); return [];", false ],
	'strict_types=2' 																										=> [ "<?php declare(strict_types=2); return [];", false ],
	'strict_types=0' 																										=> [ "<?php declare(strict_types=0); return [];", false ],
	'strict_types=10' 																									=> [ "<?php declare(strict_types=10); return [];", false ],
	'strict_types=1' 																										=> [ "<?php declare(strict_types=1); return [];", true ],
	'var_export writes => at the end of a line' 												=> [ "<?php return array (\n  'a' => \n  array (\n    'b' => \n    array (\n    ),\n  ),\n);", true ],
];
foreach( $cases as $what => [ $source, $expected ] )
	check( $what. ': '. ( $expected === true ? 'data' : 'refused' ), lexerWindowed( $stream, $source, 65536 ) === $expected && ( $expected === false || lexerTokensAreData( $source ) === true ) );

$appData = [];
ninoDone( $appData );
