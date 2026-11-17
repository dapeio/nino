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
];

foreach( $faults as $label => [ $change, $code, $path ] ) {

	$problem = \Nino\Packages::check( $app, 'theme', array_merge( $good, $change ) );
	check( 'a theme with '. $label. ' is refused as a whole, by '. $code. ' and with the path '. $path, $problem !== null && $problem['code'] === $code && $problem['path'] === $path );
}

$problem = \Nino\Packages::check( $app, 'blocks', array_merge( packagesPack( 'probe-pack', $block, $webp ), [ 'probe/block.tpl' => '[html]x[/html]' ] ) );
check( 'a package of blocks with a block that is no block is refused with the folder of the block', $problem !== null && $problem['code'] === 'block' && $problem['path'] === 'probe' );

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

foreach( $GLOBALS['packagesSandboxes'] as $sandbox )
	\Nino\Filesystem::removeDir( $sandbox );

ninoDone( $app );
