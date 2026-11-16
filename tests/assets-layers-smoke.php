<?php
declare(strict_types=1);

/**
 *	Nino
 *	assets-layers-smoke.php	Contract test for the layers of the site's style bundle
 *													(\Nino\Modules\Assets): the bundle begins with the four
 *													layers, Nino.css holds the first itself, every other
 *													source stands in the layer of the file it is, an @charset
 *													and an @import of a source break nothing, a source cannot
 *													leave its layer by its braces, and the workbench's bundles
 *													are left as they were.
 *
 *	Usage: php tests/assets-layers-smoke.php
 */

// Before the kernel loads: the fixture feature's style sheet is one of the sources
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );

require __DIR__. '/harness.php';

/**
 *	The top level of a stylesheet: its statements and its blocks, each block with the text in front of its brace and the
 *	text inside, comments left out and strings, escapes and unquoted url()s kept whole. null for a sheet whose braces do not balance
 *
 *	@param		string		$css
 *
 *	@return 	array|null
 */
function layersTopLevel( string $css ): ?array {

	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	preg_match_all( '/url\((?!\s*["\'])(?:[^)\\\\]|\\\\.)*\)|"(?:[^"\\\\\n]|\\\\.)*"|\'(?:[^\'\\\\\n]|\\\\.)*\'|\\\\.|[{};]|url\(|(?:(?!url\()[^{};"\'\\\\])+/s', $css, $tokens );

	$top		= [];
	$depth	= 0;
	$text		= '';
	$body		= '';
	$head		= '';

	foreach( $tokens[0] as $token ) {
		if( $token === '{' ) {
			if( $depth === 0 ) {
				$head = trim( $text );
				$text = '';
				$body = '';
			}
			else
				$body .= $token;
			$depth++;
		}
		elseif( $token === '}' ) {
			if( $depth === 0 )
				return null;
			$depth--;
			if( $depth === 0 )
				$top[] = [ 'head' => $head, 'body' => $body ];
			else
				$body .= $token;
		}
		elseif( $depth > 0 )
			$body .= $token;
		elseif( $token === ';' ) {
			$top[] = [ 'head' => trim( $text ), 'body' => null ];
			$text = '';
		}
		else
			$text .= $token;
	}

	return $depth === 0 && trim( $text ) === '' ? $top : null;
}

$appData = ninoSandbox( 'assets-layers' );
$sandbox = ninoSandboxDir( $appData );
$real		 = __DIR__. '/../_nino/Nino.css';
$nino		 = (string) file_get_contents( $real );
$order	 = [ 'nino.reset', 'nino.components', 'nino.theme', 'project' ];

// The kernel's stylesheet where the sandbox's project resolves '/_nino/Nino.css', the project's own below its assets
mkdir( $sandbox. '/_nino', 0755, true );
mkdir( $sandbox. '/_admin/assets', 0755, true );
file_put_contents( $sandbox. '/_nino/Nino.css', $nino );

/**
 *	Build a stylesheet bundle of the sandbox and read it back
 *
 *	@param		array			&$appData
 *	@param		string		$target			Eg. '/.cache/one.css'
 *	@param		array			$sources		Virtual path => content, written where the path resolves to ('/_nino/Nino.css' is the kernel's)
 *
 *	@return 	string
 */
function layersBundle( array &$appData, string $target, array $sources ): string {

	foreach( $sources as $path => $content )
		if( $path !== '/_nino/Nino.css' )
			\Nino\Filesystem::putFileContent( $appData, $path, $content );

	$appData['/nino/html/assets'][$target] = array_keys( $sources );
	\Nino\Modules\Assets::doShortcode( $appData, [ $target ] );

	return (string) @file_get_contents( \Nino\Filesystem::path( $appData, $target ) );
}

$layers = ( new ReflectionClassConstant( \Nino\Modules\Assets::class, 'LAYERS' ) )->getValue();

// --- Nino.css holds its layer -----------------------------------------------

echo "Nino.css - the reset\n";

check( 'the order of the layers is the four of the bundle, reset first and project last', $layers === '@layer nino.reset, nino.components, nino.theme, project;' );

$kernel = layersTopLevel( $nino );
check( 'Nino.css is one block and nothing outside it: the layer nino.reset, which it holds itself',
	$kernel !== null && array_column( $kernel, 'head' ) === [ '@layer nino.reset' ] && $kernel[0]['body'] !== null );
check( '...the tokens of the brand and of the page, the reset and the bare elements - and no rule of a component',
	str_contains( (string) $kernel[0]['body'], '--brand-color-1' ) === true && str_contains( (string) $kernel[0]['body'], 'box-sizing: border-box' ) === true
	&& str_contains( (string) $kernel[0]['body'], '.nino-grid-row' ) === false && str_contains( (string) $kernel[0]['body'], '@layer' ) === false );
check( '...with no @charset and no @import, which a block cannot hold',
	preg_match( '/@(?:charset|import)\b/i', (string) preg_replace( '#/\*.*?\*/#s', '', $nino ) ) === 0 );

/*	An !important turns the order of the layers round: of two important declarations the earlier layer wins. The
	reset holds none, so that nothing a layer later in the order says is overruled by one it was meant to overrule	*/
check( 'the reset layer holds no !important', str_contains( (string) $kernel[0]['body'], '!important' ) === false );

// --- The bundle ----------------------------------------------------------------

echo "The bundle - the layers first, each source in its own\n";

$sources = [
	'/_nino/Nino.css'														=> $nino,
	'/features/Sample/assets/sample.css'				=> file_get_contents( __DIR__. '/fixtures/features/Sample/assets/sample.css' ),
	'/assets/theme.css'													=> '.theme-mark { color: #111; }',
	'/assets/style.css'													=> '.project-mark { color: #222; }',
	'/assets/own.css'														=> '.own-mark { color: #333; }',
];
$bundle	= layersBundle( $appData, '/.cache/style.css', $sources );
$lines	= explode( "\n", $bundle );
$top		= layersTopLevel( $bundle );

check( 'the bundle begins with its hash line and then with the four layers, in one line, before any rule',
	preg_match( '#^/\*\*[0-9a-f]{40}\*\*/$#D', $lines[0] ) === 1 && $lines[1] === $layers && $top !== null && $top[0] === [ 'head' => '@layer nino.reset, nino.components, nino.theme, project', 'body' => null ] );

$stands = static function ( array $top, string $needle ): array {
	$in = [];
	foreach( $top as $block )
		if( $block['body'] !== null && str_contains( $block['body'], $needle ) === true )
			$in[] = $block['head'];
	return $in;
};

check( 'Nino.css stands in its layer, as it carries it',
	$stands( $top, 'box-sizing: border-box' ) === [ '@layer nino.reset' ] && $stands( $top, '.nino-sr-only' ) === [ '@layer nino.reset' ] );
check( 'the stylesheet of a feature stands in nino.components', $stands( $top, '.sample-notice' ) === [ '@layer nino.components' ] );
check( 'the theme of the project stands in nino.theme', $stands( $top, '.theme-mark' ) === [ '@layer nino.theme' ] );
check( 'the stylesheet of the project, and any other file of its assets, stand in project',
	$stands( $top, '.project-mark' ) === [ '@layer project' ] && $stands( $top, '.own-mark' ) === [ '@layer project' ] );
check( '...and the layers come in the order of the list, so that the blocks of a layer are never split by a block of another that stands before it',
	array_column( array_slice( $top, 1 ), 'head' ) === [ '@layer nino.reset', '@layer nino.components', '@layer nino.theme', '@layer project', '@layer project' ] );

/*	A rule of the project beats a rule of the kernel with a higher specificity, by the layer it stands in and not by what
	it says: the rank of the layer in the order the bundle declares is what the browser compares	*/
$rank = static fn( string $head ): int|false => array_search( substr( $head, strlen( '@layer ' ) ), $order, true );
$kernelRule	= '.nino-richtext';
$beaten			= array_filter( array_slice( $top, 1 ), static fn( array $block ): bool => str_contains( (string) $block['body'], $kernelRule ) === true );
$beatenRank	= $beaten === [] ? false : max( array_map( $rank, array_column( $beaten, 'head' ) ) );
check( 'a rule of the project stands in a layer of a higher rank than the rule of Nino.css it is meant to overrule, and the theme between them',
	$beatenRank !== false && $rank( '@layer project' ) > $beatenRank && $rank( '@layer nino.theme' ) > $beatenRank && $rank( '@layer nino.theme' ) < $rank( '@layer project' ) );

// --- @charset and @import ----------------------------------------------------

echo "The bundle - @charset and @import of a source\n";

$ignored = ninoWarnings();
$bundle	= layersBundle( $appData, '/.cache/imports.css', [
	'/_nino/Nino.css'				=> $nino,
	'/assets/theme.css'			=> "@charset \"utf-8\";\n@import url(\"fonts-theme.css\") screen;\n.theme-two { color: #444; }",
	'/assets/style.css'			=> "\xEF\xBB\xBF@charset 'utf-8';\n/* @import url(commented.css); */\n@import 'own.css' layer(other) supports(display: grid);\n.project-two { color: #555; }\n.late { }\n@import url(late.css);",
] );
$top		= layersTopLevel( $bundle );

check( 'a source with an @charset and an @import leaves the bundle whole: every brace balances and the layers stand where they stood',
	$top !== null && array_column( $top, 'head' )[0] === '@layer nino.reset, nino.components, nino.theme, project' && ninoWarnings() === [] );
check( '...no @charset is left in it, and the byte order mark of a source is gone',
	stripos( $bundle, '@charset' ) === false && str_contains( $bundle, "\xEF\xBB\xBF" ) === false );
$headIndex = static function ( array $top, string $head ): int|false {
	foreach( $top as $index => $block )
		if( $block['head'] === $head )
			return $index;
	return false;
};
check( '...the @import rules stand behind the layers and in front of every block, each with the layer of its file, a layer it named itself replaced, what it said about media and support kept',
	array_slice( array_column( $top, 'head' ), 1, 4 ) === [ '@import url("fonts-theme.css") layer(nino.theme) screen', '@import \'own.css\' layer(project) supports(display: grid)', '@import url(late.css) layer(project)', '@layer nino.reset' ]
	&& $headIndex( $top, '@layer nino.reset' ) === 4 && $headIndex( $top, '@layer nino.reset, nino.components, nino.theme, project' ) === 0 );
check( '...an @import in a comment is none',
	strpos( $bundle, 'commented.css' ) > strpos( $bundle, '@layer project {' ) && $headIndex( $top, '@import url(commented.css)' ) === false );
check( '...and the rules of the two files stand in their layers', $stands( $top, '.theme-two' ) === [ '@layer nino.theme' ] && $stands( $top, '.project-two' ) === [ '@layer project' ] );

// --- A source cannot leave its layer ---------------------------------------------

echo "The bundle - a source whose braces do not balance is repaired and stays in its layer\n";

/*	Read as a browser reads it: a } that closes nothing is skipped, a block that is open at the end is closed there, and what is open at
	the end - a comment, a string, a url() - ends with the source. The rest of the source stays in the block of its layer, the source
	behind it in its own, and the bundle balances	*/
$ignored = ninoWarnings();
$forms = [
	'a } that closes no block'				=> [ ".ok-1 { color: #1; }\n} .more-1 { color: #1; }", 'ok-1', 'more-1' ],
	'a } that closes no block, several'	=> [ ".ok-2 { color: #2; }\n} } } .more-2 { color: #2; } }", 'ok-2', 'more-2' ],
	'a { that is never closed'			=> [ ".ok-3 { color: #3; }\n.more-3 { color: #3;", 'ok-3', 'more-3' ],
	'a comment that does not end'		=> [ ".ok-4 { color: #4; }\n/* the rest is a comment }", 'ok-4', null ],
	'a string that does not end'		=> [ ".ok-5 { color: #5; }\n.more-5::after { content: \"open }", 'ok-5', 'more-5::after' ],
	'a url() that does not end'			=> [ ".ok-6 { color: #6; }\n.more-6 { background: url(data:image/svg+xml;utf8,<svg> }", 'ok-6', 'more-6' ],
];
$n = 0;
foreach( $forms as $fault => [ $css, $kept, $tail ] ) {
	$n++;
	$bundle	= layersBundle( $appData, '/.cache/repaired-'. $n. '.css', [ '/_nino/Nino.css' => $nino, '/assets/theme.css' => $css, '/assets/style.css' => '.after-'. $n. ' { color: #f; }' ] );
	$top		= layersTopLevel( $bundle );
	$said		= ninoWarnings();

	check( $fault. ': the bundle balances and has its four blocks in order - the source stays in its layer',
		$top !== null && array_column( array_slice( $top, 1 ), 'head' ) === [ '@layer nino.reset', '@layer nino.theme', '@layer project' ] );
	check( '...the rules of the source before the fault, and the one after it where a browser keeps it, stand in nino.theme',
		$stands( (array) $top, '.'. $kept. ' {' ) === [ '@layer nino.theme' ] && ( $tail === null || $stands( (array) $top, '.'. $tail ) === [ '@layer nino.theme' ] ) );
	check( '...the source behind it stands whole in project, and one warning names the file and the fault',
		$stands( (array) $top, '.after-'. $n. ' { color: #f; }' ) === [ '@layer project' ]
		&& count( $said ) === 1 && str_contains( $said[0], '/assets/theme.css' ) === true && str_contains( $said[0], explode( ',', $fault )[0] ) === true );
}

// The attempt to get out: what closes the block of the project's file and opens a layer of the kernel's
$bundle	= layersBundle( $appData, '/.cache/escape.css', [ '/_nino/Nino.css' => $nino, '/assets/theme.css' => '.theme-esc { color: #5; }', '/assets/style.css' => ".esc-ok { color: #6; }\n} @layer nino.reset { .escaped { color: red; } } @layer nino.components { .escaped-too { color: red; }" ] );
$top		= layersTopLevel( $bundle );
$said		= ninoWarnings();
check( 'a project file with a } and a layer of the kernel behind it stays in project: no block of nino.reset or nino.components holds a rule of it, the bundle balances',
	$top !== null && array_column( array_slice( $top, 1 ), 'head' ) === [ '@layer nino.reset', '@layer nino.theme', '@layer project' ]
	&& $stands( $top, '.escaped ' ) === [ '@layer project' ] && $stands( $top, '.escaped-too ' ) === [ '@layer project' ] && $stands( $top, '.esc-ok ' ) === [ '@layer project' ]
	&& $stands( $top, '.theme-esc ' ) === [ '@layer nino.theme' ] && count( $said ) === 1 );

// A warning is raised when the bundle is built and not at every request
$ignored = ninoWarnings();
\Nino\Modules\Assets::doShortcode( $appData, [ '/.cache/escape.css' ] );
check( '...and a request that finds the bundle built raises none', ninoWarnings() === [] );

$ignored = ninoWarnings();
$bundle	= layersBundle( $appData, '/.cache/balanced.css', [
	'/_nino/Nino.css'				=> $nino,
	'/assets/style.css'			=> ".g-rule::after { content: \"}{\"; }\n/* } { */\n.h-rule { background: url(data:image/svg+xml;utf8,<svg>{}</svg>); }\n.i\\}rule { color: #7; }\n.j-rule { background: url( 'a}b.png' ); }\n@media (min-width: 40rem) { .k-rule { color: #8; } }",
] );
$top		= layersTopLevel( $bundle );

check( 'a brace in a string, in a comment, in an unquoted url(), in a quoted one or behind a backslash is no brace: the source is taken whole',
	$top !== null && ninoWarnings() === [] && count( $stands( $top, '.g-rule' ) ) === 1 && $stands( $top, '.g-rule' ) === [ '@layer project' ]
	&& $stands( $top, '.h-rule' ) === [ '@layer project' ] && $stands( $top, '.i\\}rule' ) === [ '@layer project' ] && $stands( $top, '.k-rule' ) === [ '@layer project' ] );

// An empty source adds no block of its own
$bundle	= layersBundle( $appData, '/.cache/empty.css', [ '/assets/theme.css' => "  \n", '/assets/style.css' => '.l-rule { color: #9; }' ] );
$top		= layersTopLevel( $bundle );
check( 'a source that holds nothing adds no block', $top !== null && array_column( $top, 'head' ) === [ '@layer nino.reset, nino.components, nino.theme, project', '@layer project' ] );

// --- The other bundles ------------------------------------------------------------

echo "The bundles that are not the site's\n";

$workbench = layersBundle( $appData, '/_admin/.cache/style.css', [ '/_admin/assets/style.css' => ".wb-rule { color: #a; }\n@charset \"utf-8\"" ] );
check( 'a bundle of the workbench begins with the hash line and its first source and has no layer in it',
	str_contains( $workbench, '@layer' ) === false && str_contains( $workbench, ".wb-rule { color: #a; }\n@charset \"utf-8\"" ) === true );

$script = layersBundle( $appData, '/.cache/script.js', [ '/assets/site.js' => 'window.x = 1;' ] );
check( 'a script bundle has no layer in it', str_contains( $script, '@layer' ) === false && str_contains( $script, 'window.x = 1' ) === true );

$minified = layersBundle( $appData, '/.cache/site.min.css', [
	'/_nino/Nino.css'	=> $nino,
	'/assets/theme.css'	=> ".theme-min {\n\tcolor: #bbbbbb;\n}\n",
	'/assets/style.css'	=> ".project-min {\n\tcolor: #cccccc;\n}\n",
] );
$top = layersTopLevel( $minified );
check( 'a bundle that is minified keeps the layers: the declaration is the first rule and each source is in its block',
	$top !== null && preg_replace( '/\s+/', '', $top[0]['head'] ) === '@layernino.reset,nino.components,nino.theme,project' && $stands( $top, '.theme-min' ) === [ '@layer nino.theme' ] && $stands( $top, '.project-min' ) === [ '@layer project' ]
	&& array_slice( array_column( $top, 'head' ), 1 ) === [ '@layer nino.reset', '@layer nino.theme', '@layer project' ] );

// what a part calculates with survives the minifier: a zero keeps its unit, and the value of a token is not folded
$calc = layersBundle( $appData, '/.cache/site.min.css', [
	'/_nino/Nino.css'	=> $nino,
	'/assets/style.css'	=> ":root { --min-gap: 0px; --min-pair: 0 0; --min-half: 0.5rem; }\n.min-calc { gap: calc( var(--min-gap) * var(--min-f, 1) ); width: calc( 100% - 0px ); margin: 0 0 0 0; padding: 0 0; inset: 0px auto auto 0px; }\n",
] );
check( 'a minified bundle keeps the unit of a zero and the value of a token as it was written, and still folds the zeros of a property',
	str_contains( $calc, '--min-gap:0px' ) === true && str_contains( $calc, '--min-pair:0 0' ) === true && str_contains( $calc, '--min-half:.5rem' ) === true
	&& str_contains( $calc, 'gap:calc(var(--min-gap) * var(--min-f,1))' ) === true && str_contains( $calc, 'width:calc(100% - 0px)' ) === true
	&& str_contains( $calc, 'margin:0;' ) === true && str_contains( $calc, 'padding:0;' ) === true && str_contains( $calc, 'inset:0px auto auto 0px' ) === true );

// --- A bundle that was built before the layers --------------------------------------

echo "The cache - a bundle of the time before the layers is built again\n";

$ignored = ninoWarnings();
$files		= [ '/assets/theme.css' => '.theme-old { color: #d; }', '/assets/style.css' => '.project-old { color: #e; }' ];
$old			= layersBundle( $appData, '/.cache/old.css', $files );
$path			= \Nino\Filesystem::path( $appData, '/.cache/old.css' );
$hashOld	= '';
foreach( [ '/assets/theme.css', '/assets/style.css' ] as $file ) {
	$resolved	= \Nino\Filesystem::path( $appData, $file );
	$hashOld .= $resolved. '|'. filesize( $resolved ). '|'. filemtime( $resolved );
}
// What the bundle was before: the sources in a row under the hash of their path, size and time
file_put_contents( $path, '/**'. sha1( $hashOld ). '**/'. PHP_EOL. '.theme-old { color: #d; }.project-old { color: #e; }' );
\Nino\Modules\Assets::doShortcode( $appData, [ '/.cache/old.css' ] );
$rebuilt = (string) file_get_contents( $path );
check( 'a bundle on disk whose hash is the one of the files alone is not taken for current: it is built again, with the layers', explode( "\n", $rebuilt )[1] === $layers && str_contains( $rebuilt, '@layer nino.theme {' ) === true );

$stamp = (string) file_get_contents( $path );
\Nino\Modules\Assets::doShortcode( $appData, [ '/.cache/old.css' ] );
check( '...and a bundle that is current is left as it is', (string) file_get_contents( $path ) === $stamp && $old !== '' );

// --- The bundle in memory -------------------------------------------------------------

echo "stylesheet() - the bundle as a string, files put in place of others\n";

$ignored	= ninoWarnings();
$memory		= [
	'/_nino/Nino.css'				=> $nino,
	'/features/Sample/assets/sample.css'	=> (string) file_get_contents( __DIR__. '/fixtures/features/Sample/assets/sample.css' ),
	'/assets/theme.css'			=> '.theme-disk { color: #111; }',
	'/assets/style.css'			=> '.project-disk { color: #222; }',
];
$onDisk		= layersBundle( $appData, '/.cache/memory.css', $memory );
$publicPath	= \Nino\Filesystem::path( $appData, '/.cache/memory.css' );
$cacheDir	= dirname( $publicPath );
$listing	= static fn(): array => array_map( static fn( string $file ): string => $file. '|'. filesize( $cacheDir. '/'. $file ). '|'. filemtime( $cacheDir. '/'. $file ), (array) scandir( $cacheDir ) );
$before		= $listing();
$stampOf	= static fn( string $css ): string => explode( "\n", $css )[0];

$plain = \Nino\Modules\Assets::stylesheet( $appData, '/.cache/memory.css' );
check( 'with nothing to put in place the string is the bytes of the bundle file, stamp line included', $plain !== '' && $plain === $onDisk );
check( '...an empty $swap is the same', \Nino\Modules\Assets::stylesheet( $appData, '/.cache/memory.css', [] ) === $onDisk );

$swapped	= \Nino\Modules\Assets::stylesheet( $appData, '/.cache/memory.css', [ '/assets/theme.css' => '.theme-swap { color: #abc; }', '/_nino/Nino.css' => '@layer nino.reset { .reset-swap { margin: 0; } }' ] );
$top		= layersTopLevel( $swapped );
check( 'a file put in place of one stands in the layer of the file it replaces: the theme in nino.theme, Nino.css in the layer it holds itself',
	$top !== null && $stands( $top, '.theme-swap' ) === [ '@layer nino.theme' ] && $stands( $top, '.reset-swap' ) === [ '@layer nino.reset' ]
	&& array_slice( array_column( $top, 'head' ), 1 ) === [ '@layer nino.reset', '@layer nino.components', '@layer nino.theme', '@layer project' ] );
check( '...and the file it replaces is gone from the bundle, the others stand as they were',
	str_contains( $swapped, '.theme-disk' ) === false && str_contains( $swapped, '.project-disk' ) === true && $stands( $top, '.project-disk' ) === [ '@layer project' ] );
check( '...with the stamp of another bundle, which no public bundle can be taken for', $stampOf( $swapped ) !== $stampOf( $onDisk ) && preg_match( '#^/\*\*[0-9a-f]{40}\*\*/$#', $stampOf( $swapped ) ) === 1 );

// The file way and the memory way are one code: the same sources put in files give the same bundle
$twin		= layersBundle( $appData, '/.cache/twin.css', [ '/assets/theme.css' => '.theme-swap { color: #abc; }' ] + $memory );
$memory2	= \Nino\Modules\Assets::stylesheet( $appData, '/.cache/twin.css', [ '/assets/theme.css' => '.theme-swap { color: #abc; }' ] );
check( 'what a swap puts in place of a file is built as the file is: the bundle of files that hold the same text equals it after the stamp line',
	preg_replace( '/^[^\n]*\n/', '', $twin ) === preg_replace( '/^[^\n]*\n/', '', $memory2 ) && preg_replace( '/^[^\n]*\n/', '', $twin ) !== '' );
\Nino\Filesystem::putFileContent( $appData, '/assets/theme.css', $memory['/assets/theme.css'] );
$onDisk		= layersBundle( $appData, '/.cache/memory.css', $memory );
// An hour back, so that a write of the same bytes shows in the time of the file
touch( $publicPath, time() - 3600 );
clearstatcache();
$before		= $listing();

// A source whose braces do not balance is repaired as a file is, and says so
$bad		= new ArrayObject();
set_error_handler( static function( int $no, string $message ) use ( $bad ): bool { $bad[] = $message; return true; }, E_USER_WARNING );
$repaired	= \Nino\Modules\Assets::stylesheet( $appData, '/.cache/memory.css', [ '/assets/theme.css' => '.t-open { color: #1; } } .t-more { color: #2; ' ] );
restore_error_handler();
$top		= layersTopLevel( $repaired );
check( 'a replacement whose braces do not balance is repaired and stays in its layer: the file after it is not swallowed',
	$top !== null && $stands( $top, '.t-open' ) === [ '@layer nino.theme' ] && $stands( $top, '.t-more' ) === [ '@layer nino.theme' ] && $stands( $top, '.project-disk' ) === [ '@layer project' ] );
check( '...one warning names the file and the faults', count( $bad ) === 1 && str_contains( $bad[0], '/assets/theme.css' ) && str_contains( $bad[0], 'a } that closes no block' ) && str_contains( $bad[0], 'a { that is never closed' ) );

// Refused: a path the bundle does not list, one outside the project, a replacement that is no string, a target that is no stylesheet
$refused	= [
	'a file the bundle does not hold'			=> [ '/assets/other.css' => '.x { }' ],
	'a path below the project'						=> [ '/assets/../../outside.css' => '.x { }' ],
	'an absolute path outside the project'	=> [ '/etc/passwd' => '.x { }' ],
	'the spelling of a listed file that is not the listed one'	=> [ 'assets/theme.css' => '.x { }' ],
	'a path with a null byte'							=> [ "/assets/theme.css\0.png" => '.x { }' ],
	'a listed file beside a stranger'		=> [ '/assets/theme.css' => '.ok { }', '/assets/stranger.css' => '.x { }' ],
	'a replacement that is no string'			=> [ '/assets/theme.css' => [ '.x { }' ] ],
];
foreach( $refused as $label => $swap ) {
	$caught = new ArrayObject();
	set_error_handler( static function( int $no, string $message ) use ( $caught ): bool { $caught[] = $message; return true; }, E_USER_WARNING );
	$answer = \Nino\Modules\Assets::stylesheet( $appData, '/.cache/memory.css', $swap );
	restore_error_handler();
	check( 'refused: '. $label. ' - an empty string and one warning', $answer === '' && count( $caught ) === 1 && str_contains( $caught[0], 'refused' ) && str_contains( $caught[0], "\0" ) === false );
}
check( 'a target that is no stylesheet and one with no files give an empty string',
	\Nino\Modules\Assets::stylesheet( $appData, '/.cache/script.js' ) === '' && \Nino\Modules\Assets::stylesheet( $appData, '/.cache/nothing.css' ) === '' && \Nino\Modules\Assets::stylesheet( $appData, '' ) === '' );

check( 'the public bundle and the folder it stands in are as they were after all of it: no file written, none touched, the stamp the same',
	$listing() === $before && (string) file_get_contents( $publicPath ) === $onDisk && $stampOf( $onDisk ) === $stampOf( $plain ) );

ninoDone( $appData );
