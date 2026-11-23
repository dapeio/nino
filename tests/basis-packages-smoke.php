<?php
declare(strict_types=1);

/**
 *	Nino
 *	basis-packages-smoke.php	Contract test for what \Nino\Blocks::roots() and \Nino\Packages say of the feature Basis: its blocks are the first root, before the
 *														project and the packages, and a package that is named like it has an origin of its own and takes nothing from it.
 *														What a package is, and where it lies, is tests/packages-smoke.php.
 *
 *	Usage: php tests/basis-packages-smoke.php
 */

// A project with no features of its own: the one feature of the run is the one the kernel delivers
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-basis-packages-features-'. getmypid() );
mkdir( NINO_FEATURES_DIR, 0755, true );
$GLOBALS['basisPackagesSandboxes'] = [];
register_shutdown_function( static function(): void {

	foreach( $GLOBALS['basisPackagesSandboxes'] as $sandbox )
		if( is_dir( $sandbox ) === true )
			\Nino\Filesystem::removeDir( $sandbox );

	@rmdir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';

$ignored = ninoWarnings();

$webp = (string) base64_decode( 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==' );
$png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
$svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect id="a" width="10" height="10" fill="#f00"/></svg>';

/**
 *	A project with basis on, as \Nino\init() registers it
 *
 *	@return 	array										$appData
 */
function basisPackagesSite(): array {

	$appData = ninoSandbox( 'basis-packages' );
	$GLOBALS['basisPackagesSandboxes'][] = ninoSandboxDir( $appData );
	$appData['./nino/filesystem/path'] = dirname( __DIR__ );
	$appData['/nino/modules'] = [ '\\Nino\\Modules\\Basis' ];
	$appData['/nino/html/navs'] = [ 'main', 'footer' ];

	\Nino\Features::registerComponents( $appData );

	foreach( [ 'template', 'assets', 'jstext', 'legal', 'privacy', 'json', 'imagevalues', 'csrf' ] as $shortcode )
		\Nino\Html::addShortcode( $appData, $shortcode, static fn(): string => '', null );

	return $appData;
}

/**
 *	The files of a package of blocks: the block of a section with a slot for a svg and one for a png
 *
 *	@param		string		$key
 *	@param		string		$svg
 *	@param		string		$png
 *	@param		string		$preview
 *
 *	@return 	array										path => bytes
 */
function basisPackagesPack( string $key, string $svg, string $png, string $preview ): array {

	$words = static fn( string $de, string $en ): array => [ 'de_DE' => $de, 'en_US' => $en ];
	$slot = static fn( string $label ): array => [ 'placeholder' => '/nino/placeholder/landscape', 'width' => 800, 'height' => 600, 'label' => $words( $label, $label ) ];

	return [
		'pack.json'								=> json_encode( [ 'key' => $key, 'version' => '1.0.0', 'name' => $words( 'Probepaket', 'Probe pack' ), 'description' => 'Probe' ] ),
		'preview.webp'						=> $preview,
		'LICENSE.txt'							=> "Probe licence\n",
		'probe/block.json'				=> json_encode( [ 'name' => $words( 'Probe', 'Probe' ), 'description' => $words( 'Eine Probe', 'A probe' ), 'category' => 'content', 'rank' => 5,
			'images' => [ 'background' => $slot( 'Hintergrund' ), '1-photo' => $slot( 'Foto' ) ] ] ),
		'probe/block.tpl'					=> "[section-wrap id=\"probe\" image=\"~background\"]\n\t[section-row]\n\t\t[section-col id=\"probe-1\"]\n\t\t\t[section-title ~title]\n\t\t\t[image ~photo alt=\"\"]\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]",
		'probe/text/de_DE.json'		=> json_encode( [ '1-title' => 'Titel' ] ),
		'probe/text/en_US.json'		=> json_encode( [ '1-title' => 'Title' ] ),
		'probe/images/background.svg'	=> $svg,
		'probe/images/1-photo.png'		=> $png,
	];
}

echo "Packages - the roots of the blocks with Basis on\n";

$app = basisPackagesSite();
\Nino\Packages::place( $app, 'blocks', 'probe-pack', basisPackagesPack( 'probe-pack', $svg, $png, $webp ) );
$ignored = ninoWarnings();
$origins = array_column( \Nino\Blocks::roots( $app ), 'origin' );
check( 'the roots are Basis, the project, the packages, the other features - in that order', array_slice( $origins, 0, 3 ) === [ 'basis', 'project', 'pack:probe-pack' ] );

$other = basisPackagesSite();
\Nino\Packages::place( $other, 'blocks', 'basis', basisPackagesPack( 'basis', $svg, $png, $webp ) );
$ignored = ninoWarnings();
$origins = array_column( \Nino\Blocks::roots( $other ), 'origin' );
check( 'a package named like a feature has an origin of its own and takes nothing from the feature', in_array( 'pack:basis', $origins, true ) === true && array_count_values( $origins )['basis'] === 1 && isset( \Nino\Blocks::all( $other )['hero-full'] ) === true && \Nino\Blocks::all( $other )['hero-full']['origin'] === 'basis' );

ninoDone( $app );
