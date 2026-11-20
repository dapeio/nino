<?php
declare(strict_types=1);

/**
 *	Nino
 *	packages-smoke.php	Contract test for \Nino\Packages - what comes from outside as a folder of files, a theme or a package of blocks:
 *
 *											A. a good theme and a good package of blocks pass, lie in their place and are what they should be - a svg as the picture
 *											   that was rebuilt, a package of blocks among the blocks with its origin and its label
 *											B. what is refused, with the path - the theme with a php file, with an @import, with a token of another group, and the
 *											   neighbours - and that nothing of it lies on the disk
 *											C. the exchange: put back where it fails, one change at a time, what a run that was cut off left is cleared, removal
 *											D. the places: the folders, the order of the roots of the blocks, a backup that does not carry a package
 *
 *	Usage: php tests/packages-smoke.php
 */

require __DIR__. '/harness.php';

$ignored = ninoWarnings();

// The pictures of the packages: a webp, a png and a jpeg of one pixel, written down, and a svg with a comment that a rebuild does not keep
$webp = (string) base64_decode( 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==' );
$png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
$svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><!-- the original --><rect id="a" width="10" height="10" fill="#f00"/></svg>';

/**
 *	A project with basis on, as \Nino\init() registers it
 *
 *	@return 	array										$appData
 */
function packagesSite(): array {

	$appData = ninoSandbox( 'packages' );
	$GLOBALS['packagesSandboxes'][] = ninoSandboxDir( $appData );
	$appData['./nino/filesystem/path'] = dirname( __DIR__ );
	$appData['/nino/modules'] = [ '\\Nino\\Modules\\Basis' ];
	$appData['/nino/html/navs'] = [ 'main', 'footer' ];

	\Nino\Features::registerComponents( $appData );

	foreach( [ 'template', 'assets', 'jstext', 'legal', 'privacy', 'json', 'imagevalues', 'csrf' ] as $shortcode )
		\Nino\Html::addShortcode( $appData, $shortcode, static fn(): string => '', null );

	return $appData;
}

/**
 *	The files of a block of the package, with a slot for a svg and one for a png
 *
 *	@param		string		$svg
 *	@param		string		$png
 *
 *	@return 	array										file below the folder of the block => bytes
 */
function packagesBlock( string $svg, string $png ): array {

	$words = static fn( string $de, string $en ): array => [ 'de_DE' => $de, 'en_US' => $en ];
	$slot = static fn( string $label ): array => [ 'placeholder' => '/nino/placeholder/landscape', 'width' => 800, 'height' => 600, 'label' => $words( $label, $label ) ];

	return [
		'block.json'				=> json_encode( [ 'name' => $words( 'Probe', 'Probe' ), 'description' => $words( 'Eine Probe', 'A probe' ), 'category' => 'content', 'rank' => 5,
			'images' => [ 'background' => $slot( 'Hintergrund' ), '1-photo' => $slot( 'Foto' ) ] ] ),
		'block.tpl'					=> "[section-wrap id=\"probe\" image=\"~background\"]\n\t[section-row]\n\t\t[section-col id=\"probe-1\"]\n\t\t\t[section-title ~title]\n\t\t\t[image ~photo alt=\"\"]\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]",
		'text/de_DE.json'		=> json_encode( [ '1-title' => 'Titel' ] ),
		'text/en_US.json'		=> json_encode( [ '1-title' => 'Title' ] ),
		'images/background.svg'	=> $svg,
		'images/1-photo.png'		=> $png,
	];
}

/**
 *	A theme that has nothing against it: the page, a button, a header with a variant and a block
 *
 *	@param		string		$version
 *	@param		array			$block				The files of its block
 *	@param		string		$preview
 *
 *	@return 	array										path => bytes
 */
function packagesTheme( string $version, array $block, string $preview ): array {

	$words = static fn( string $de, string $en ): array => [ 'de_DE' => $de, 'en_US' => $en ];
	$ladder = [ 'var(--page-radius)', 'calc( var(--page-radius) * 1.5 )', '999px', '999px', '999px' ];
	$files = [
		'theme.json'								=> json_encode( [ 'key' => 'craft-probe', 'version' => $version, 'name' => $words( 'Craft', 'Craft' ), 'description' => $words( 'Weich', 'Soft' ), 'brand' => [] ] ),
		'parts/page/part.json'			=> json_encode( [ 'contract' => 'kernel@^2.0', 'knobs' => [ 'radius' => [ 'label' => $words( 'Rundung', 'Radius' ), 'ladder' => [
			[ '--page-radius' => '.375rem' ], [ '--page-radius' => '.625rem' ], [ '--page-radius' => '1rem' ], [ '--page-radius' => '1.5rem' ], [ '--page-radius' => '2rem' ] ] ] ] ] ),
		'parts/button/part.json'		=> json_encode( [ 'contract' => 'basis@^1.0', 'ladders' => [ 'button.radius' => $ladder ] ] ),
		'parts/button/part.css'			=> "/*	buttons - a soft shadow	*/\n.button:not( .button--tone-quiet ) { box-shadow: 0 2px 6px rgb( 0 0 0 / .15 ); }\n",
		'parts/header/part.json'		=> json_encode( [ 'contract' => 'basis@^1.0', 'variants' => [ 'header-wrap' => [ 'stacked' => $words( 'Gestapelt', 'Stacked' ) ] ], 'standard' => [ 'header-wrap' => 'stacked' ] ] ),
		'parts/header/part.css'			=> "@media (min-width: 768px) {\n\t.header-wrap--variant-stacked .header-wrap-inner { flex-direction: column; }\n}\n",
		'preview.webp'							=> $preview,
		'LICENSE.txt'								=> "Probe licence\n",
		'README.md'									=> "# Craft\n",
	];

	foreach( $block as $file => $bytes )
		$files['blocks/probe/'. $file] = $bytes;

	return $files;
}

/**
 *	A package of blocks that has nothing against it
 *
 *	@param		string		$key
 *	@param		array			$block
 *	@param		string		$preview
 *
 *	@return 	array
 */
function packagesPack( string $key, array $block, string $preview ): array {

	$files = [
		'pack.json'			=> json_encode( [ 'key' => $key, 'version' => '1.0.0', 'name' => [ 'de_DE' => 'Probepaket', 'en_US' => 'Probe pack' ], 'description' => 'Probe' ] ),
		'preview.webp'	=> $preview,
		'LICENSE.txt'		=> "Probe licence\n",
	];

	foreach( $block as $file => $bytes )
		$files['probe/'. $file] = $bytes;

	return $files;
}

/**
 *	Everything that lies below a folder, as the paths below it
 *
 *	@param		string		$dir
 *
 *	@return 	array
 */
function packagesTree( string $dir ): array {

	$found = [];

	if( is_dir( $dir ) === false )
		return $found;

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST ) as $entry )
		$found[] = substr( $entry->getPathname(), strlen( $dir ) + 1 );

	sort( $found );

	return $found;
}

echo "Packages - a good theme and a good package of blocks\n";

$app = packagesSite();
$private = ninoSandboxDir( $app ). '/private';
$block = packagesBlock( $svg, $png );
$theme = packagesTheme( '1.0.0', $block, $webp );
$pack = packagesPack( 'probe-pack', $block, $webp );

check( 'a theme of the parts of the page, a button and a header with a block of two pictures passes', \Nino\Packages::check( $app, 'theme', $theme ) === null );
check( 'a package of blocks with the block passes', \Nino\Packages::check( $app, 'blocks', $pack ) === null );

$placed = \Nino\Packages::place( $app, 'theme', 'craft-probe', $theme );
$folder = $private. '/packages/themes/craft-probe';
check( 'a theme is placed below private/packages/themes/<key>, and the staging folder is empty', $placed === true && is_file( $folder. '/theme.json' ) === true && packagesTree( $private. '/data/.packages' ) === [] );
check( 'every file is there byte for byte but the svg, which is the picture that was rebuilt: no comment, and it is still a drawing',
	array_diff_key( array_map( static fn( string $path ): string => (string) file_get_contents( $folder. '/'. $path ), array_combine( array_keys( $theme ), array_keys( $theme ) ) ), [ 'blocks/probe/images/background.svg' => 1 ] )
		=== array_diff_key( $theme, [ 'blocks/probe/images/background.svg' => 1 ] )
	&& str_contains( (string) file_get_contents( $folder. '/blocks/probe/images/background.svg' ), 'the original' ) === false
	&& str_contains( (string) file_get_contents( $folder. '/blocks/probe/images/background.svg' ), '<rect' ) === true );

$ignored = ninoWarnings();
check( 'a package of blocks is placed below private/packages/blocks/<key>', \Nino\Packages::place( $app, 'blocks', 'probe-pack', $pack ) === true && is_file( $private. '/packages/blocks/probe-pack/pack.json' ) === true );

$all = \Nino\Blocks::all( $app );
check( 'the block of the package is among the blocks with the key of the package as its origin, its name from pack.json as its label and the pictures it brings',
	isset( $all['probe'] ) === true && $all['probe']['origin'] === 'pack:probe-pack' && $all['probe']['label'] === [ 'de_DE' => 'Probepaket', 'en_US' => 'Probe pack' ]
	&& $all['probe']['pictures'] === [ '1-photo' => 'images/1-photo.png', 'background' => 'images/background.svg' ] );

$origins = array_column( \Nino\Blocks::roots( $app ), 'origin' );
check( 'the roots are Basis, the project, the packages, the other features - in that order', array_slice( $origins, 0, 3 ) === [ 'basis', 'project', 'pack:probe-pack' ] );

echo "\nPackages - what is refused, with the path, and nothing of it on the disk\n";

$app = packagesSite();
$private = ninoSandboxDir( $app ). '/private';
$ignored = ninoWarnings();
$block = packagesBlock( $svg, $png );
$good = packagesTheme( '1.0.0', $block, $webp );

// A fault in an otherwise good theme: what it is changed to, and the code and the path that say it
$faults = [
	'a php file'												=> [ [ 'evil.php' => '<?php echo 1;' ], 'not-allowed', 'evil.php' ],
	'a php file below a part'						=> [ [ 'parts/button/x.php' => '<?php' ], 'not-allowed', 'parts/button/x.php' ],
	'an @import'												=> [ [ 'parts/button/part.css' => "@import 'x.css';\n.button { color: red; }" ], 'css', 'parts/button/part.css' ],
	'a token of another group, read'		=> [ [ 'parts/button/part.css' => '.button { color: var(--header-wrap-ink); }' ], 'css', 'parts/button/part.css' ],
	'a token of another group, set'			=> [ [ 'parts/button/part.css' => ':root { --page-size: 1px; }' ], 'css', 'parts/button/part.css' ],
	'a class of another component'			=> [ [ 'parts/button/part.css' => '.header-wrap { color: red; }' ], 'css', 'parts/button/part.css' ],
	'a url()'														=> [ [ 'parts/button/part.css' => '.button { background: url(x.png); }' ], 'css', 'parts/button/part.css' ],
	'an !important'											=> [ [ 'parts/button/part.css' => '.button { color: red !important; }' ], 'css', 'parts/button/part.css' ],
	'an .htaccess'											=> [ [ '.htaccess' => 'Options +ExecCGI' ], 'not-allowed', '.htaccess' ],
	'a script'													=> [ [ 'parts/button/part.js' => 'alert(1)' ], 'not-allowed', 'parts/button/part.js' ],
	'a hidden file'											=> [ [ 'blocks/probe/.secret' => 'x' ], 'not-allowed', 'blocks/probe/.secret' ],
	'a file below a part that is no part file'	=> [ [ 'parts/button/part.json.bak' => '{}' ], 'not-allowed', 'parts/button/part.json.bak' ],
	'a path that climbs'								=> [ [ 'parts/../x.json' => '{}' ], 'path', 'parts/../x.json' ],
	'a second spelling of a name'				=> [ [ 'readme.md' => 'x' ], 'twice', 'readme.md' ],
	'a part.json that is too deep'			=> [ [ 'parts/button/part.json' => str_repeat( '{"a":', 9 ). '1'. str_repeat( '}', 9 ) ], 'json', 'parts/button/part.json' ],
	'a part.json that is too large'			=> [ [ 'parts/button/part.json' => json_encode( [ 'contract' => 'basis@^1.0', 'x' => str_repeat( 'x', \Nino\Packages::MAX_JSON ) ] ) ], 'long', 'parts/button/part.json' ],
	'a null byte in a text'							=> [ [ 'README.md' => "x\0y" ], 'text', 'README.md' ],
	'a block with [html]'								=> [ [ 'blocks/probe/block.tpl' => "[section-wrap id=\"probe\" image=\"~background\"]\n\t[html]<b>x</b>[/html]\n[/section-wrap]" ], 'block', 'blocks/probe' ],
	'a block with a source that is not its own'	=> [ [ 'blocks/probe/block.tpl' => "[section-wrap id=\"probe\" image=\"~background\"]\n\t[section-title /project/company/name]\n\t[image ~photo alt=\"\"]\n[/section-wrap]" ], 'block', 'blocks/probe' ],
	'a svg that is only a script, with no shape'	=> [ [ 'blocks/probe/images/background.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' ], 'block', 'blocks/probe' ],
	'a svg with a document type'				=> [ [ 'blocks/probe/images/background.svg' => '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "b">]><svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>' ], 'block', 'blocks/probe' ],
	'a picture that is not what its ending says'	=> [ [ 'blocks/probe/images/1-photo.png' => $webp ], 'block', 'blocks/probe' ],
	'a picture of no slot'							=> [ [ 'blocks/probe/images/nowhere.png' => $png ], 'block', 'blocks/probe' ],
	'a preview that is no webp'					=> [ [ 'preview.webp' => $png ], 'preview', 'preview.webp' ],
	'a theme.json with a field that is no key of a theme'	=> [ [ 'theme.json' => json_encode( [ 'key' => 'craft-probe', 'version' => '1.0.0', 'name' => 'x', 'description' => 'x', 'run' => 'x' ] ) ], 'file-key', 'theme.json' ],
	'a name with a tag'									=> [ [ 'theme.json' => json_encode( [ 'key' => 'craft-probe', 'version' => '1.0.0', 'name' => 'a <b>b</b>', 'description' => 'x' ] ) ], 'file-markup', 'theme.json' ],
	'a name in one language with a call'	=> [ [ 'theme.json' => json_encode( [ 'key' => 'craft-probe', 'version' => '1.0.0', 'name' => [ 'de_DE' => 'Gut', 'en_US' => 'a [[/project/company/name]]' ], 'description' => 'x' ] ) ], 'file-markup', 'theme.json' ],
	'a description with a closing bracket of a tag'	=> [ [ 'theme.json' => json_encode( [ 'key' => 'craft-probe', 'version' => '1.0.0', 'name' => 'x', 'description' => 'x>' ] ) ], 'file-markup', 'theme.json' ],
];

foreach( $faults as $label => [ $change, $code, $path ] ) {

	$problem = \Nino\Packages::check( $app, 'theme', array_merge( $good, $change ) );
	check( 'a theme with '. $label. ' is refused as a whole, by '. $code. ' and with the path '. $path, $problem !== null && $problem['code'] === $code && $problem['path'] === $path );
}

$problem = \Nino\Packages::check( $app, 'blocks', array_merge( packagesPack( 'probe-pack', $block, $webp ), [ 'probe/block.tpl' => '[html]x[/html]' ] ) );
check( 'a package of blocks with a block that is no block is refused with the folder of the block', $problem !== null && $problem['code'] === 'block' && $problem['path'] === 'probe' );

// The steps a part says its knobs are delivered at, and the free css of a theme
$withButton = static function( mixed $values ) use ( $good ): array {

	$json = json_decode( $good['parts/button/part.json'], true );
	$json['values'] = $values;

	return [ 'parts/button/part.json' => json_encode( $json ) ] + $good;
};
$page = json_decode( $good['parts/page/part.json'], true );
$withPage = static fn( mixed $values ): array => [ 'parts/page/part.json' => json_encode( $page + [ 'values' => $values ] ) ] + $good;

check( 'a theme without values and without theme.css is as good as it was, and a part may say the steps its knobs are delivered at: a knob of the group, a knob the part brings itself, a knob of the page, -2 to 2 and 0',
	\Nino\Packages::check( $app, 'theme', $good ) === null && \Nino\Packages::check( $app, 'theme', $withButton( [ 'button.radius' => 1, 'button.space' => -2, 'button.size' => 0, 'button.radius' => 2 ] ) ) === null
	&& \Nino\Packages::check( $app, 'theme', $withPage( [ 'radius' => -1 ] ) ) === null && \Nino\Packages::check( $app, 'theme', $withButton( [] ) ) === null && \Nino\Packages::check( $app, 'theme', $withPage( (object) [] ) ) === null );

$badValues = [];

foreach( [
	'a knob the part has not'					=> [ 'button.nope' => 1 ],
	'a knob of another group'					=> [ 'header-wrap.space' => 1 ],
	'a step above the ladder'					=> [ 'button.radius' => 3 ],
	'a step below the ladder'					=> [ 'button.radius' => -3 ],
	'a step that is a string'					=> [ 'button.radius' => '1' ],
	'a step that is a fraction'				=> [ 'button.radius' => 1.5 ],
	'a step that is a list'						=> [ 'button.radius' => [ 1 ] ],
	'a step that is nested'						=> [ 'button.radius' => [ 'a' => [ 'b' => 1 ] ] ],
	'a step that is null'							=> [ 'button.radius' => null ],
	'a step that is true'							=> [ 'button.radius' => true ],
	'a name with a semicolon'					=> [ 'button.radius; color: red' => 1 ],
	'a name that climbs'							=> [ '../button.radius' => 1 ],
	'a name with a control character'	=> [ "button.radius\n" => 1 ],
	'a name of digits'								=> [ '5' => 1 ],
	'a list in place of a map'				=> [ 1, 2 ],
	'a number'												=> 3,
	'a string'												=> 'button.radius',
	'null'														=> null,
	'a thousand entries'							=> array_combine( array_map( static fn( int $at ): string => 'button.k'. $at, range( 1, 1000 ) ), array_fill( 0, 1000, 1 ) ),
] as $label => $values )
	if( ( \Nino\Packages::check( $app, 'theme', $withButton( $values ) )['code'] ?? '' ) !== 'part-values' )
		$badValues[] = $label;

check( 'values that name a knob the part has not, a step outside the ladder, a step that is no whole number, a name that is no name, a list or a thousand of them are refused as part-values'. ( $badValues === [] ? '' : ' - taken: '. implode( ', ', $badValues ) ), $badValues === [] );
check( '...the part of the page is held to it as well, with the knobs it has', ( \Nino\Packages::check( $app, 'theme', $withPage( [ 'nope' => 1 ] ) )['code'] ?? '' ) === 'part-values' && ( \Nino\Packages::check( $app, 'theme', $withPage( [ 'radius' => 3 ] ) )['code'] ?? '' ) === 'part-values'
	&& ( \Nino\Packages::check( $app, 'theme', $withPage( [ 'radius' => 3 ] ) )['path'] ?? '' ) === 'parts/page/part.json' );
check( '...a group the project does not have is held to the knobs its part brings, and a part may carry the values of its own knob',
	\Nino\Packages::check( $app, 'theme', [ 'parts/strange/part.json' => json_encode( [ 'contract' => 'strange@^1.0', 'knobs' => [ 'strange.x-glow' => [ 'label' => 'Glow', 'ladder' => [ '0', '1', '2', '3', '4' ] ] ], 'values' => [ 'strange.x-glow' => 1 ] ] ) ] + $good ) === null );

$css = static fn( string $text ): array => [ 'theme.css' => $text ] + $good;
$fine = ":root { --button-radius: 2px; }\n/* a comment may say url( and @import and !important */\n.button { border-radius: var(--button-radius); }\n.header-wrap--sticky .header-logo { opacity: .9; }\n@media (min-width: 768px) { .button--size-l { padding: 1rem; } }\n";

check( 'a theme with a theme.css that keeps the rule of a part over the classes of every component is taken', \Nino\Packages::check( $app, 'theme', $css( $fine ) ) === null && \Nino\Packages::check( $app, 'theme', $css( '' ) ) === null );

$badCss = [];

foreach( [
	'url()'														=> ".button { background: url(x.png); }",
	'an @import'											=> "@import 'x.css';\n.button { color: red; }",
	'an !important'										=> ".button { color: red !important; }",
	'expression()'										=> ".button { width: expression(alert(1)); }",
	'the end of a style element'			=> ".button { content: 'x'; }\n</style><script>alert(1)</script>",
	'the end of a style element in a comment'	=> "/* </style> */\n.button { color: red; }",
	'the end of a style element in a string'	=> ".button::after { content: '</style>'; }",
	'the beginning of a comment of markup'	=> "<!-- .button { color: red; }",
	'an element selector'							=> "body { margin: 0; }",
	'a link selector'									=> "a { color: red; }",
	'the universal selector'					=> "* { box-sizing: border-box; }",
	'an id'														=> "#page { color: red; }",
	'an id with a class of a component'	=> ".button#page { color: red; }",
	'an attribute selector on the page'	=> "[data-x] { color: red; }",
	'the classes of the page by an attribute'	=> "[class*=\"button\"] { color: red; }",
	'@font-face'											=> "@font-face { font-family: X; src: local(Arial); }",
	'@namespace'											=> "@namespace svg url(http://x);",
	'@layer'													=> "@layer base { .button { color: red; } }",
	'an escape that hides url('				=> ".button { background: \\75 rl(x.png); }",
	'an escape in a selector'					=> ".bu\\74 ton { color: red; }",
	'a token that is none of a component\'s'	=> ":root { --page-size: 1px; }",
	'a token of the brand set'				=> ":root { --brand-color-1: red; }",
	'a comment that is not closed'		=> ".button { color: red; } /* x",
	'braces that do not balance'			=> ".button { color: red;",
	'behavior'												=> ".button { behavior: url(x.htc); }",
	'-moz-binding'										=> ".button { -moz-binding: x; }",
	'a way out of the rule by @media'	=> "@media print { body { margin: 0; } }",
] as $label => $text )
	if( ( \Nino\Packages::check( $app, 'theme', $css( $text ) )['code'] ?? '' ) !== 'theme-css' )
		$badCss[] = $label;

check( 'a theme.css that loads, imports, forces, runs, ends the style element it is put in, meets the page by an element, an id, a universal or an attribute selector, names a font, hides any of it in an escape or a comment, or does not close, is refused as theme-css'
	. ( $badCss === [] ? '' : ' - taken: '. implode( ', ', $badCss ) ), $badCss === [] );

// Whether a theme.css is valid does not depend on the site: a class is held to its form, and not to the components the site has on
$withGallery = $app;
$withGallery[\Nino\Components::REGISTRY]['gallery-grid'] = $app[\Nino\Components::REGISTRY]['button'];
$gallery = ".gallery-grid { gap: 0; }\n.gallery-grid--cols-3 .gallery-item:hover { --gallery-gap: 1rem; opacity: var(--brand-color-1, 1); }\n.no-feature-has-this_class-2 { color: red; }";

check( 'the register of this site has no gallery and the one of the other has: the same theme.css is as good in both, and a class that no component has here is no cause to refuse it',
	isset( $app[\Nino\Components::REGISTRY]['gallery-grid'] ) === false && isset( $withGallery[\Nino\Components::REGISTRY]['gallery-grid'] ) === true
	&& \Nino\Packages::check( $app, 'theme', $css( $gallery ) ) === null && \Nino\Packages::check( $withGallery, 'theme', $css( $gallery ) ) === null
	&& \Nino\Components::themeProblems( $gallery ) === [] );

$wrong = [];

foreach( [
	'a class that is not written like the ones of the components'	=> '.Gallery-grid { gap: 0; }',
	'a capital behind a good class'			=> '.a.Gallery-grid { gap: 0; }',
	'a function nested five deep'				=> '.a:is(.b:is(.c:is(.d:is(.e:is(.f))))) { gap: 0; }',
	'a class that starts with a digit'		=> '.3-up { gap: 0; }',
	'a class that starts with a hyphen'	=> '.-up { gap: 0; }',
	'an element with a class'						=> 'a.button { gap: 0; }',
	'an element behind a class'					=> '.button a { gap: 0; }',
	'an id behind a class'							=> '.button #x { gap: 0; }',
	'an attribute selector behind a class'	=> '.button[disabled] { gap: 0; }',
	'an attribute selector of the class'	=> '.button:where([class*="--x"]) { gap: 0; }',
	'the universal selector behind a class'	=> '.section-wrap * { display: none; }',
	'the universal selector behind ~'		=> '.section-wrap ~ * { display: none; }',
	'the universal selector behind +'		=> '.header-wrap + * { display: none; }',
	'the universal selector behind >'		=> '.header-wrap > * { display: none; }',
	'an element as the last part'				=> '.section-wrap ~ div { display: none; }',
	'an element as the last part behind +'	=> '.section-wrap .text + a { display: none; }',
	'an element in the middle'					=> '.section-wrap div .text { display: none; }',
	'the universal selector in a nesting'	=> '.section-wrap { & ~ * { display: none; } }',
	'an element in a nesting'						=> '.section-wrap { & + div { display: none; } }',
	'an element in a nesting without &'	=> '.section-wrap { > a { display: none; } }',
	'an element two levels down'				=> '.a { .b { & ~ p { color: red; } } }',
	'a part with only & at the top of the sheet'	=> '& .button { color: red; }',
	':root with a descendant'						=> ':root .button { color: red; }',
	'only a pseudo class'								=> ':hover { color: red; }',
	'only a pseudo element'							=> '::before { content: ""; }',
	'a part that is only :first-child'	=> '.button > :first-child { color: red; }',
	'a class that is only in :not()'		=> ':not(.button) { color: red; }',
	'a class that is only in :is()'			=> ':is(.a, .b) { color: red; }',
	'body in :is()'											=> '.a:is(.b, body) { color: red; }',
	'* in :where()'											=> ':where(*) .button { color: red; }',
	'body in :not()'										=> '.button:not(body) { color: red; }',
	'body in :has()'										=> '.button:has(body) { color: red; }',
	'a combinator and * in :has()'			=> '.button:has(> *) { color: red; }',
	'* behind ~ in :not()'							=> '.a:not(.b ~ *) { color: red; }',
	'* in :is() in :not()'							=> '.a:not(:is(.b, *)) { color: red; }',
	'an element in :nth-child( of )'		=> '.a:nth-child(2n+1 of div) { color: red; }',
	'a function that takes no selector here'	=> '.a::part(x) { color: red; }',
	'a function of no use'							=> '.a:host(.b) { color: red; }',
	'an empty :is()'										=> '.a:is() { color: red; }',
	'an empty member of a list'					=> '.a, { color: red; }',
	'a bad member of a list'						=> '.a, body { color: red; }',
	'a bad member behind a good one in a nesting'	=> '.a, .b { & ~ i, & ~ .c { color: red; } }',
	'a bad one in @media'								=> '@media print { .a ~ * { display: none; } }',
	'a bad one in @supports'						=> '@supports (display: grid) { .a ~ * { display: none; } }',
	'a bad one in @container'						=> '@container (min-width: 1px) { .a ~ * { display: none; } }',
] as $label => $text )
	if( \Nino\Components::themeProblems( $text ) === [] || ( \Nino\Packages::check( $app, 'theme', $css( $text ) )['code'] ?? '' ) !== 'theme-css' )
		$wrong[] = $label;

check( 'every compound of a selector of a theme.css has a class of its own outside of any function - the last one, the one behind ~, + or >, one under & and one in :is(), :where(), :not() or :has() - and meets nothing but classes: the rest is refused'
	. ( $wrong === [] ? '' : ' - taken: '. implode( ', ', $wrong ) ), $wrong === [] );

$wrong = [];

foreach( [
	'a descendant'											=> '.a .b { color: red; }',
	'the combinators'										=> '.a > .b + .c ~ .d .e { color: red; }',
	'no blank around the combinators'		=> '.a>.b+.c~.d { color: red; }',
	'pseudo classes and elements'				=> '.a:hover:not(.b):focus-visible::after, .a::before { color: red; }',
	'a list'														=> '.a, .b ~ .c { color: red; }',
	'a modifier'												=> '.section-col--bleed.section-col--link { color: red; }',
	':is() and :where() of classes'			=> '.a:is(.b, .c) .d:where(.e ~ .f) { color: red; }',
	':has() with a combinator in front'	=> '.a:has(> .b, + .c) { color: red; }',
	':not() with a combination'					=> '.a:not(.b .c) { color: red; }',
	'a nesting of the kinds'						=> '.a { & ~ .b { color: red; } & > .c { color: red; } > .d { color: red; } + .e { color: red; } .f { color: red; } &:hover { color: red; } }',
	'a nesting in a nesting'						=> '.a { .b { & ~ .c { color: red; } } }',
	'a nesting under a list'						=> '.a, .b { & ~ .c { color: red; } }',
	'a position'												=> '.a:nth-child(2n+1):nth-last-child(odd):nth-of-type(3) .b:nth-child(2 of .c, .d) { color: red; }',
	'a function nested four deep'				=> '.a:is(.b:is(.c:is(.d:is(.e)))) { gap: 0; }',
	'a language'												=> '.a:lang(de) .b:dir(rtl) { color: red; }',
	':root'															=> ':root { --gallery-gap: 1rem; }',
	'a rule in @media, @supports and @container'	=> '@media (min-width: 1px) { .a ~ .b { color: red; } } @supports (display: grid) { .a { display: grid; } } @container (min-width: 1px) { .a .b { color: red; } }',
	'keyframes'													=> '@keyframes spin { from { opacity: 0; } to { opacity: 1; } } .a { animation: spin 1s; }',
] as $label => $text )
	if( \Nino\Components::themeProblems( $text ) !== [] )
		$wrong[] = $label. ': '. implode( '; ', \Nino\Components::themeProblems( $text ) );

check( '...and a selector of classes with pseudo classes, pseudo elements and the combinators, in lists, in functions that take selectors and in nestings, is taken'. ( $wrong === [] ? '' : ' - refused: '. implode( ' | ', $wrong ) ), $wrong === [] );

$wrong = [];

foreach( [
	'--gallery-gap'											=> [ ':root { --gallery-gap: 1px; }', true ],
	'--a-b_c'														=> [ ':root { --a-b_c: 1px; }', true ],
	'a token without a hyphen behind its name'	=> [ ':root { --gallery: 1px; }', false ],
	'--page-space-1'										=> [ ':root { --page-space-1: 1px; }', false ],
	'--brand-color-1'										=> [ ':root { --brand-color-1: red; }', false ],
	'a token with a capital'						=> [ ':root { --Gallery-gap: 1px; }', false ],
	'reading --page-size'								=> [ '.a { width: var(--page-size); }', true ],
	'reading a token of no name'				=> [ '.a { width: var(--x); }', false ],
	'reading a token of a component'		=> [ '.a { width: var(--gallery-gap); }', true ],
] as $label => [ $text, $ok ] )
	if( ( \Nino\Components::themeProblems( $text ) === [] ) !== $ok )
		$wrong[] = $label;

check( 'a token is set by a theme.css where it is written as the token of a component, whichever components the site has - neither --brand-... nor --page-... - and read where it is that, or a --brand-... or a --page-...'. ( $wrong === [] ? '' : ' - taken wrongly: '. implode( ', ', $wrong ) ), $wrong === [] );

// What no stylesheet put into a page may do, in every spelling
$wrong = [];

foreach( [
	'@font-face in capitals'						=> "@FONT-FACE { font-family: X; }",
	'@Font-Face mixed'									=> "@Font-Face { font-family: X; }",
	'behavior in capitals'							=> ".a { BEHAVIOR: url(x.htc); }",
	'behavior mixed with a blank'				=> ".a { Behavior : x; }",
	'-moz-binding in capitals'					=> ".a { -MOZ-BINDING: x; }",
	'expression in capitals'						=> ".a { width: EXPRESSION(1); }",
	'@property'													=> "@property --gallery-gap { syntax: '<length>'; inherits: false; initial-value: 0px; }",
	'@property of the brand'						=> "@property --brand-color-1 { syntax: '*'; inherits: false; initial-value: red; }",
	'@PROPERTY in capitals'							=> "@PROPERTY --page-size { syntax: '*'; inherits: false; }",
	'image()'														=> ".a { background: image('x.png'); }",
	'element()'													=> ".a { background: element(#x); }",
	'-moz-element()'										=> ".a { background: -moz-element(#x); }",
	'cross-fade()'											=> ".a { background: cross-fade(var(--page-size), var(--page-size), 50%); }",
	'IMAGE() in capitals'								=> ".a { background: IMAGE('x.png'); }",
] as $label => $text )
	if( \Nino\Components::themeProblems( $text ) === [] )
		$wrong[] = $label;

check( 'a theme.css that uses @font-face, behavior, -moz-binding or expression() in any case of letters, an @property, or image(), element() and cross-fade() is refused'. ( $wrong === [] ? '' : ' - taken: '. implode( ', ', $wrong ) ), $wrong === [] );

check( '...what a comment says is no use of it: @font-face, behavior:, -moz-binding and expression( in a comment are taken, whatever the case',
	\Nino\Components::themeProblems( "/* @font-face { } behavior: x; -moz-binding: y; expression( 1 ) */\n.a { color: red; }\n/* a\n@FONT-FACE */" ) === []);

// part.css: the same list of what loads, and no @property; a part keeps its own rule over the classes of its group
$wrong = [];

foreach( [ '@property --probe-x { syntax: "*"; inherits: false; }', '.probe { background: image("x.png"); }', '.probe { background: element(#x); }', '.probe { background: cross-fade(red, blue, 50%); }', '.probe { background: -webkit-cross-fade(red, blue, 50%); }' ] as $text )
	if( \Nino\Components::partProblems( $text, [ 'probe' ] ) === [] )
		$wrong[] = $text;

check( 'a part.css refuses @property, image(), element() and cross-fade() as it does image-set() and src()'. ( $wrong === [] ? '' : ' - taken: '. implode( ', ', $wrong ) ), $wrong === []
	&& \Nino\Components::partProblems( '.probe-x :is(th, td) > :first-child { color: red; } .probe:not(:last-child)::after { content: ""; }', [ 'probe' ] ) === [] );

$long = \Nino\Packages::check( $app, 'theme', $css( str_repeat( ' ', \Nino\Packages::MAX_CSS + 1 ) ) );
$problem = \Nino\Packages::check( $app, 'theme', $css( ".button { color: red; }\n.button { color: red; }\n</style>" ) );
check( 'a theme.css above the limit of a part.css is too long, and what is wrong in one is said with the line it stands on',
	( $long['code'] ?? '' ) === 'long' && ( $long['path'] ?? '' ) === 'theme.css' && ( $problem['path'] ?? '' ) === 'theme.css' && str_contains( implode( ' ', $problem['params'] ?? [] ), 'line 3' ) === true && str_contains( \Nino\Packages::why( $problem ), 'theme.css' ) === true );
check( 'a theme.css may be as long as a part.css: 40 KB of rules are taken - the limit of a json is not its limit - and a rule found wrong below a comment of several lines says its line',
	\Nino\Packages::check( $app, 'theme', $css( str_repeat( ".button { color: red; }\n", 1600 ) ) ) === null
	&& str_contains( implode( ' ', \Nino\Packages::check( $app, 'theme', $css( "/* one\ntwo\nthree */\n.button { color: red; }\n@font-face { font-family: X; }" ) )['params'] ?? [] ), 'line 5' ) === true );
check( 'a text that is not utf-8 or holds a null byte is no theme.css', ( \Nino\Packages::check( $app, 'theme', $css( "a\0b" ) )['code'] ?? '' ) === 'text' && ( \Nino\Packages::check( $app, 'theme', $css( "\xff\xfe" ) )['code'] ?? '' ) === 'text' );
check( 'theme.css is a file of a theme and of nothing else: a package of blocks has none, and a theme has the one at its root and none in a folder',
	( \Nino\Packages::check( $app, 'blocks', [ 'theme.css' => $fine ] + packagesPack( 'probe-pack', $block, $webp ) )['code'] ?? '' ) === 'not-allowed'
	&& ( \Nino\Packages::check( $app, 'theme', [ 'parts/button/theme.css' => $fine ] + $good )['code'] ?? '' ) === 'not-allowed' && ( \Nino\Packages::check( $app, 'theme', [ 'blocks/probe/theme.css' => $fine ] + $good )['code'] ?? '' ) === 'not-allowed'
	&& ( \Nino\Packages::check( $app, 'theme', [ 'Theme.css' => $fine ] + $good )['code'] ?? '' ) !== null );
$placeApp = packagesSite();
$placed = \Nino\Packages::place( $placeApp, 'theme', 'craft-probe', $css( $fine ) );
check( 'place() puts the file with the others and a theme that has it is installed as the theme it is', $placed === true && file_get_contents( ninoSandboxDir( $placeApp ). '/private/packages/themes/craft-probe/theme.css' ) === $fine && \Nino\Packages::installed( $placeApp, 'theme' ) === [ 'craft-probe' => '1.0.0' ] );
\Nino\Filesystem::removeDir( ninoSandboxDir( $placeApp ) );

// A frame may read the texts of the project, a block of the pages may not: the package follows the rule of \Nino\Blocks by itself, as a theme or as a package of blocks
$frameJson = json_encode( [ 'name' => [ 'de_DE' => 'Fuss', 'en_US' => 'Foot' ], 'description' => [ 'de_DE' => 'Ein Fuss', 'en_US' => 'A foot' ], 'category' => 'frame', 'rank' => 20, 'images' => (object) [] ] );
$frameFiles = static fn( string $tpl, string $json ): array => [ 'block.json' => $json, 'block.tpl' => $tpl, 'text/de_DE.json' => '{}', 'text/en_US.json' => '{}' ];
$footerTpl = "[footer-wrap id=\"footer\"]\n\t[footer-col size=\"100\"]\n\t\t[footer-logo /logo]\n\t\t[text /project/company/general/description format=\"inline\"]\n\t[/footer-col]\n[/footer-wrap]";
$withFrame = array_merge( $good, [ 'blocks/footer/block.json' => $frameJson, 'blocks/footer/block.tpl' => $footerTpl, 'blocks/footer/text/de_DE.json' => '{}', 'blocks/footer/text/en_US.json' => '{}' ] );
$inPage = array_merge( $good, [ 'blocks/footer/block.json' => json_encode( [ 'category' => 'content' ] + json_decode( $frameJson, true ) ), 'blocks/footer/block.tpl' => "[section-wrap id=\"footer\"]\n\t[section-row]\n\t\t[section-col]\n\t\t\t[text /project/company/general/description]\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]", 'blocks/footer/text/de_DE.json' => '{}', 'blocks/footer/text/en_US.json' => '{}' ] );
check( 'a theme with a frame that reads a text of the project and the logo is taken, the same source in a block of the pages is refused with the folder of the block, and so is it in a package of blocks',
	\Nino\Packages::check( $app, 'theme', $withFrame ) === null && ( \Nino\Packages::check( $app, 'theme', $inPage )['code'] ?? '' ) === 'block' && ( \Nino\Packages::check( $app, 'theme', $inPage )['path'] ?? '' ) === 'blocks/footer'
	&& \Nino\Packages::check( $app, 'blocks', packagesPack( 'probe-pack', $frameFiles( $footerTpl, $frameJson ), $webp ) ) === null
	&& ( \Nino\Packages::check( $app, 'blocks', packagesPack( 'probe-pack', $frameFiles( "[section-wrap id=\"footer\"][section-row][section-col][text /project/company/general/description][/section-col][/section-row][/section-wrap]", json_encode( [ 'category' => 'content' ] + json_decode( $frameJson, true ) ) ), $webp ) )['code'] ?? '' ) === 'block' );

$problem = \Nino\Packages::check( $app, 'blocks', array_merge( packagesPack( 'probe-pack', $block, $webp ), [ 'pack.json' => json_encode( [ 'key' => 'probe-pack', 'version' => '1.0.0', 'name' => '<script>x</script>', 'description' => 'Probe' ] ) ] ) );
check( 'the name of a package of blocks is text as well: a tag in it is refused, and the good name of the same package is none', $problem !== null && $problem['code'] === 'file-markup' && $problem['path'] === 'pack.json' && \Nino\Packages::check( $app, 'blocks', packagesPack( 'probe-pack', $block, $webp ) ) === null );

$problem = \Nino\Packages::check( $app, 'blocks', array_merge( packagesPack( 'probe-pack', $block, $webp ), [ 'README.md' => 'x' ] ) );
check( 'a file of a theme is no file of a package of blocks', $problem !== null && $problem['code'] === 'not-allowed' && $problem['path'] === 'README.md' );

$problem = \Nino\Packages::check( $app, 'feature', [ 'Alpha.php' => '<?php class Alpha {}', 'feature.php' => '<?php return [];' ] );
check( 'a feature is code, and the rules of the archive are all', $problem === null );

$problem = \Nino\Packages::check( $app, 'feature', [ 'a/../b.php' => '<?php' ] );
check( 'a feature has no path that climbs', $problem !== null && $problem['code'] === 'path' );

$refused = [];

foreach( $faults as $label => [ $change ] )
	$refused[] = \Nino\Packages::place( $app, 'theme', 'craft-probe', array_merge( $good, $change ) ) !== true;

$ignored = ninoWarnings();
check( 'place() refuses every one of them as check() does, and nothing lies on the disk - no folder of a package, no staging folder', array_unique( $refused ) === [ true ] && packagesTree( $private. '/packages' ) === [] && packagesTree( $private. '/data/.packages' ) === [] );

// A svg that has a shape and a script is taken and written as the drawing without the script: what cleanSvg() does not take is refused, the rest is rebuilt
$live = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect width="10" height="10" fill="#f00" onclick="alert(3)"/></svg>';
$withLive = array_merge( $good, [ 'blocks/probe/images/background.svg' => $live ] );
check( 'a svg with a shape, a script and an onload passes the check', \Nino\Packages::check( $app, 'theme', $withLive ) === null );
$written = \Nino\Packages::place( $app, 'theme', 'craft-probe', $withLive ) === true ? (string) file_get_contents( $private. '/packages/themes/craft-probe/blocks/probe/images/background.svg' ) : 'not placed';
check( 'the file that was written has the shape and neither the script nor the onload', str_contains( $written, '<rect' ) === true && str_contains( $written, 'script' ) === false && str_contains( $written, 'onload' ) === false && str_contains( $written, 'onclick' ) === false );
\Nino\Packages::remove( $app, 'theme', 'craft-probe' );
$ignored = ninoWarnings();

$many = $good;

for( $i = 0; $i < 41; $i++ )
	$many['parts/group-'. $i. '/part.json'] = json_encode( [ 'contract' => 'nowhere@^1.0' ] );

$problem = \Nino\Packages::check( $app, 'theme', $many );
check( 'a theme of more parts than the reader of themes takes is refused', $problem !== null && $problem['code'] === 'parts-many' );

$problem = \Nino\Packages::check( $app, 'feature', [ 'x' => 'a', 'x/y' => 'b' ] );
check( 'a name that is a file and a folder is refused, whatever the kind', $problem !== null && $problem['code'] === 'path' && $problem['path'] === 'x/y' );

$problem = \Nino\Packages::place( $app, 'theme', 'other-key', $good );
$ignored = ninoWarnings();
check( 'a theme that says it is another key than it is installed as is refused', $problem !== true && $problem['code'] === 'identity' && packagesTree( $private. '/packages' ) === [ 'themes' ] );

check( 'the folder of a kind is the folder of its key, and a key that is no name of a folder has none',
	\Nino\Packages::dir( $app, 'theme', 'craft' ) === $private. '/packages/themes/craft' && \Nino\Packages::dir( $app, 'blocks', 'x-y' ) === $private. '/packages/blocks/x-y'
	&& \Nino\Packages::dir( $app, 'theme', '../x' ) === '' && \Nino\Packages::dir( $app, 'theme', 'Craft' ) === '' && \Nino\Packages::dir( $app, 'feature', 'Alpha' ) === \Nino\Features::dir(). '/Alpha' && \Nino\Packages::dir( $app, 'run', 'x' ) === '' );

echo "\nPackages - the exchange, one change at a time, what a cut run left, removal\n";

$app = packagesSite();
$private = ninoSandboxDir( $app ). '/private';
$folder = $private. '/packages/themes/craft-probe';
$first = packagesTheme( '1.0.0', $block, $webp );
$second = packagesTheme( '1.1.0', $block, $webp );
$ignored = ninoWarnings();

\Nino\Packages::place( $app, 'theme', 'craft-probe', $first );
$app['./nino/packages/move'] = static fn( string $from, string $to ): bool => false;
$answer = \Nino\Packages::place( $app, 'theme', 'craft-probe', $second );
unset( $app['./nino/packages/move'] );
$ignored = ninoWarnings();
check( 'an exchange that fails puts the old folder back: the version is the old one, nothing is left aside and the staging folder is gone',
	$answer !== true && $answer['code'] === 'swap' && str_contains( (string) file_get_contents( $folder. '/theme.json' ), '"1.0.0"' ) === true
	&& array_diff( scandir( dirname( $folder ) ) ?: [], [ '.', '..', 'craft-probe' ] ) === [] && packagesTree( $private. '/data/.packages' ) === [] );

check( 'an exchange that works replaces the folder, and nothing is left of the old one', \Nino\Packages::place( $app, 'theme', 'craft-probe', $second ) === true
	&& str_contains( (string) file_get_contents( $folder. '/theme.json' ), '"1.1.0"' ) === true && array_diff( scandir( dirname( $folder ) ) ?: [], [ '.', '..', 'craft-probe' ] ) === [] );

$holder = $app;
$holder['./nino/filesystem/locks'] = [];
unset( $holder['./nino/features/lock'], $holder['./nino/features/all'] );
$before = packagesTree( $private. '/packages' );
check( 'another holder takes the lock', \Nino\Features::lock( $holder ) === true );
$busy = [ \Nino\Packages::place( $app, 'theme', 'craft-probe', $first ), \Nino\Packages::remove( $app, 'theme', 'craft-probe' ), \Nino\Packages::sweep( $app ) ];
check( 'a change that finds the lock taken is not made - place(), remove() and sweep() answer busy and nothing changed',
	array_column( $busy, 'code' ) === [ 'busy', 'busy', 'busy' ] && packagesTree( $private. '/packages' ) === $before && str_contains( (string) file_get_contents( $folder. '/theme.json' ), '"1.1.0"' ) === true );
\Nino\Features::unlock( $holder );

$ignored = ninoWarnings();

// What a run that was cut off left: a staging folder that is old and one that is young, a folder put aside whose package is gone and one whose package
// is there (the old one of a finished exchange), and what a removal put aside
$stage = $private. '/data/.packages';
$left = [
	$stage. '/theme-old-aaaaaaaaaaaa'												=> 'old',
	$stage. '/theme-young-bbbbbbbbbbbb'											=> 'young',
	dirname( $folder ). '/.previous-lost-cccccccccccc'			=> 'old',
	dirname( $folder ). '/.previous-craft-probe-dddddddddddd'	=> 'old',
	dirname( $folder ). '/.removing-gone-eeeeeeeeeeee'			=> 'old',
	dirname( $folder ). '/.incoming-craft-probe-ffffffffffff'	=> 'old',
	$stage. '/theme-ahead-aaaaaaaaaaab'											=> 'ahead',
];

foreach( $left as $path => $age ) {

	mkdir( $path, 0755, true );
	file_put_contents( $path. '/marker.txt', $path );
	touch( $path, $age === 'old' ? time() - 2 * \Nino\Packages::WORK_AGE : ( $age === 'ahead' ? time() + 2 * \Nino\Packages::WORK_AGE : time() ) );
}

check( 'sweep() takes the lock and gives it back', \Nino\Packages::sweep( $app ) === true && \Nino\Features::lock( $holder ) === true );
\Nino\Features::unlock( $holder );
check( 'sweep() removes a staging folder that is old, not one that is young, and what was put aside by a removal',
	is_dir( $stage. '/theme-old-aaaaaaaaaaaa' ) === false && is_dir( $stage. '/theme-young-bbbbbbbbbbbb' ) === true && is_dir( dirname( $folder ). '/.removing-gone-eeeeeeeeeeee' ) === false );
check( 'sweep() never puts back what a removal put aside - it may be half deleted - and removes a copy that was never moved in, and a staging folder from the future',
	is_dir( dirname( $folder ). '/gone' ) === false && is_dir( dirname( $folder ). '/.incoming-craft-probe-ffffffffffff' ) === false && is_dir( $stage. '/theme-ahead-aaaaaaaaaaab' ) === false );
check( 'sweep() puts a folder back whose package is gone, and removes the one of a package that is there',
	is_file( dirname( $folder ). '/lost/marker.txt' ) === true && is_dir( dirname( $folder ). '/.previous-lost-cccccccccccc' ) === false && is_dir( dirname( $folder ). '/.previous-craft-probe-dddddddddddd' ) === false
	&& str_contains( (string) file_get_contents( $folder. '/theme.json' ), '"1.1.0"' ) === true );

mkdir( dirname( $folder ). '/.previous-craft-probe-999999999999' );
touch( dirname( $folder ). '/.previous-craft-probe-999999999999', time() );
$gone = \Nino\Packages::remove( $app, 'theme', 'craft-probe' ) === true;
\Nino\Packages::sweep( $app );
check( 'a folder an exchange put aside is taken away with the package and does not come back', $gone === true && array_diff( scandir( dirname( $folder ) ) ?: [], [ '.', '..', 'lost' ] ) === [] );
\Nino\Packages::place( $app, 'theme', 'craft-probe', $second );

check( 'a theme is taken away, whatever uses it, and nothing is left aside; a second time it is not there', \Nino\Packages::remove( $app, 'theme', 'craft-probe' ) === true && is_dir( $folder ) === false
	&& array_filter( scandir( dirname( $folder ) ) ?: [], static fn( string $name ): bool => str_starts_with( $name, '.removing' ) ) === [] && ( \Nino\Packages::remove( $app, 'theme', 'craft-probe' )['code'] ?? '' ) === 'absent' );
check( 'only a theme and a package of blocks are taken away by remove()', ( \Nino\Packages::remove( $app, 'feature', 'Alpha' )['code'] ?? '' ) === 'kind' );
$ignored = ninoWarnings();

echo "\nPackages - the places\n";

$app = packagesSite();
$private = ninoSandboxDir( $app ). '/private';
$before = isset( \Nino\Blocks::all( $app )['probe'] );
\Nino\Packages::place( $app, 'blocks', 'probe-pack', packagesPack( 'probe-pack', $block, $webp ) );
check( 'a package of blocks put in place is among the blocks in the same request, and gone from them when it is removed', $before === false && isset( \Nino\Blocks::all( $app )['probe'] ) === true
	&& \Nino\Packages::remove( $app, 'blocks', 'probe-pack' ) === true && isset( \Nino\Blocks::all( $app )['probe'] ) === false );
\Nino\Packages::place( $app, 'blocks', 'probe-pack', packagesPack( 'probe-pack', $block, $webp ) );

$other = packagesSite();
\Nino\Packages::place( $other, 'blocks', 'basis', packagesPack( 'basis', $block, $webp ) );
$ignored = ninoWarnings();
$origins = array_column( \Nino\Blocks::roots( $other ), 'origin' );
check( 'a package named like a feature has an origin of its own and takes nothing from the feature', in_array( 'pack:basis', $origins, true ) === true && array_count_values( $origins )['basis'] === 1 && isset( \Nino\Blocks::all( $other )['hero-full'] ) === true && \Nino\Blocks::all( $other )['hero-full']['origin'] === 'basis' );
check( 'the files that stand beside the blocks of a package are no blocks', array_filter( array_keys( \Nino\Blocks::rejected( $other ) ), static fn( string $where ): bool => str_starts_with( $where, 'pack:basis/' ) ) === [] );

// A link in the place of a package is neither followed nor replaced; removing drops the link and leaves what it points at
mkdir( $private. '/elsewhere' );
file_put_contents( $private. '/elsewhere/keep.txt', 'x' );
symlink( $private. '/elsewhere', $private. '/packages/themes-link' );
@mkdir( $private. '/packages/themes', 0755, true );
symlink( $private. '/elsewhere', $private. '/packages/themes/craft-probe' );
$answer = \Nino\Packages::place( $app, 'theme', 'craft-probe', packagesTheme( '1.0.0', $block, $webp ) );
$ignored = ninoWarnings();
check( 'a link in the place of a theme is not exchanged and what it points at is left', $answer !== true && $answer['code'] === 'swap' && is_link( $private. '/packages/themes/craft-probe' ) === true && packagesTree( $private. '/elsewhere' ) === [ 'keep.txt' ] );
check( 'removing it drops the link only', \Nino\Packages::remove( $app, 'theme', 'craft-probe' ) === true && is_link( $private. '/packages/themes/craft-probe' ) === false && is_file( $private. '/elsewhere/keep.txt' ) === true );
unlink( $private. '/packages/themes-link' );

// A block of the project's own is in a backup; a package of blocks beside it is not
mkdir( $private. '/blocks/own/images', 0755, true );
file_put_contents( $private. '/blocks/own/block.json', '{}' );
file_put_contents( $private. '/blocks/own/images/background.png', $png );
$files = \Nino\Backup::manifest( $app );
check( 'a backup carries the blocks of the project, the pictures of their slots among them, and not the packages', in_array( 'blocks/own/block.json', $files, true ) === true && in_array( 'blocks/own/images/background.png', $files, true ) === true && array_filter( $files, static fn( string $name ): bool => str_contains( $name, 'packages' ) || str_contains( $name, 'probe' ) ) === [] );

$folder = $private. '/packages/blocks/probe-pack/probe/images';
unset( $app['./nino/blocks/state'] );
file_put_contents( $folder. '/payload.php', '<?php echo 1;' );
unset( $app['./nino/blocks/state'], $app['./nino/blocks/said'] );
$rejected = \Nino\Blocks::rejected( $app );
$ignored = ninoWarnings();
check( 'a block whose images/ holds a php file, put there by hand, is not listed', isset( \Nino\Blocks::all( $app )['probe'] ) === false && isset( $rejected['pack:probe-pack/probe'] ) === true );

echo "\nPackages - what the catalogue asks of it: what is installed, and a check of the files that only a disk can make\n";

$app = packagesSite();
$private = ninoSandboxDir( $app ). '/private';
$ignored = ninoWarnings();
$block = packagesBlock( $svg, $png );

check( 'nothing is installed where nothing was placed', \Nino\Packages::installed( $app, 'theme' ) === [] && \Nino\Packages::installed( $app, 'blocks' ) === [] && \Nino\Packages::installed( $app, 'feature' ) === [] );
\Nino\Packages::place( $app, 'theme', 'craft-probe', packagesTheme( '1.2.0', $block, $webp ) );
\Nino\Packages::place( $app, 'blocks', 'probe-pack', packagesPack( 'probe-pack', $block, $webp ) );
mkdir( $private. '/packages/themes/stray', 0755, true );
file_put_contents( $private. '/packages/themes/stray/theme.json', json_encode( [ 'key' => 'other', 'version' => '1.0.0' ] ) );
mkdir( $private. '/packages/themes/empty', 0755, true );
check( 'the installed ones are the folders whose theme.json or pack.json says the key of the folder and a version - a folder that says another key, or nothing, is not one',
	\Nino\Packages::installed( $app, 'theme' ) === [ 'craft-probe' => '1.2.0' ] && \Nino\Packages::installed( $app, 'blocks' ) === [ 'probe-pack' => '1.0.0' ] );

// The closure is asked of the folder with the files written, named as the folder is to be, and what it answers stops the change
$asked = new \ArrayObject();
$answer = \Nino\Packages::place( $app, 'theme', 'later', [ 'theme.json' => json_encode( [ 'key' => 'later', 'version' => '1.0.0', 'name' => 'Later', 'description' => 'Later' ] ) ] + packagesTheme( '1.0.0', $block, $webp ),
	static function( string $folder ) use ( $asked ): ?array {
		$asked[] = [ basename( $folder ), is_file( $folder. '/theme.json' ), is_file( $folder. '/parts/page/part.json' ) ];
		return [ 'code' => 'manifest', 'path' => '', 'params' => [] ];
	} );
$ignored = ninoWarnings();
check( 'a closure is asked of the staging folder, which has the files and the name of the folder of the package, and its answer is the answer of place() with nothing exchanged',
	$asked->getArrayCopy() === [ [ 'later', true, true ] ] && $answer['code'] === 'manifest' && is_dir( $private. '/packages/themes/later' ) === false && packagesTree( $private. '/data/.packages' ) === [] );
$answer = \Nino\Packages::place( $app, 'theme', 'later', [ 'theme.json' => json_encode( [ 'key' => 'later', 'version' => '1.0.0', 'name' => 'Later', 'description' => 'Later' ] ) ] + packagesTheme( '1.0.0', $block, $webp ), static fn( string $folder ): ?array => null );
check( '...and a closure that finds nothing wrong lets it through', $answer === true && is_file( $private. '/packages/themes/later/theme.json' ) === true );

// A part of a group the project does not have says which components are its own: not one that the register holds, of whichever group
$held = \Nino\Components::groups( $app )['header']['components'][0] ?? 'header-wrap';
$borrow = static fn( string $name ): array => [ 'parts/cards/part.json' => json_encode( [ 'contract' => 'cards@^1.0', 'variants' => [ $name => [ 'soft' => 'Soft' ] ] ] ),
	'parts/cards/part.css' => '.'. $name. ' { color: var(--'. $name. '-ink); }' ] + packagesTheme( '1.0.0', $block, $webp );
$ignored = ninoWarnings();
$answer = \Nino\Packages::check( $app, 'theme', $borrow( $held ) );
check( 'a part of a group the project has not, that names a component of the header as its own, is refused with the name', $answer !== null && $answer['code'] === 'part-claims' && $answer['path'] === 'parts/cards/part.json' && $answer['params'] === [ $held ] );
check( '...and one that names a component that nobody has is not', \Nino\Packages::check( $app, 'theme', $borrow( 'card-soft' ) ) === null );

foreach( $GLOBALS['packagesSandboxes'] as $sandbox )
	\Nino\Filesystem::removeDir( $sandbox );

ninoDone( $app );
