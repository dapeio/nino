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

$data = new ReflectionMethod( '\Nino\Backup', '_isData' );

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

for( $variant = 0; $variant < 1200; $variant++ ) {

	$body = mt_rand( 0, 5 ) === 0 ? $pick( $scalars ) : $array( 2 );

	if( mt_rand( 0, 30 ) === 0 )
		$body = $pick( $disturb );

	$tail = $pick( [ ...array_fill( 0, 20, ';' ), ';', ';', ';', ';', ';', ';', ';', ';', ';'. $pick( $spaces ), ';'. $pick( $spaces ), ';'. $pick( $comments ), '', ';;', '; echo 1;', ";\n?>\nhtml", '; ?>', ";\n// end ?> <?php 1;" ] );

	$source = $pick( $opens ). $pick( $comments ). $pick( $declares ). $pick( $spaces ). $pick( $comments ). $pick( $returns ). ( mt_rand( 0, 9 ) === 0 ? '' : ' ' ). $pick( $comments ). $body. $pick( $spaces ). $tail;

	if( $data->invoke( null, $source ) === true ) {
		$through++;

		if( lexerTokensAreData( $source ) === false )
			$wrong[] = $source;
	}
	else
		$refused++;
}

check( 'the variants are made from a fixed seed, and both sets are there: '. $through. ' let through and '. $refused. ' refused of 1200', $through >= 100 && $refused >= 100 && $through + $refused === 1200 );
check( 'every variant the reader lets through is, for php\'s own tokenizer, nothing but the allowed tokens'. ( $wrong === [] ? '' : ' - first: '. json_encode( $wrong[0] ) ), $wrong === [] );

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
	check( $what. ': '. ( $expected === true ? 'data' : 'refused' ), $data->invoke( null, $source ) === $expected && ( $expected === false || lexerTokensAreData( $source ) === true ) );

$appData = [];
ninoDone( $appData );
