<?php
declare(strict_types=1);

/**
 *	Nino											A compact filesystembased php framework
 *	demo-catalogue-smoke.php	Dependency-free smoke test for the "Demo: Catalogue"
 *														page unit (_admin/install/library/pages/.demo-catalogue).
 *
 *														The unit makes one promise: it shows every Section
 *														Preset the Template Builder ships, in every layout
 *														that preset declares, and every nino-* class Nino.css
 *														defines. That promise is what this file measures - not
 *														the wording, which is free to change, and not the
 *														markup, which is generated from the library and moves
 *														with it.
 *
 *	Usage: php tests/demo-catalogue-smoke.php
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_admin/Admin.php';
require __DIR__. '/../_admin/install/Install.php';

$failures = 0;
$checks		= 0;

/**
 *	Assert a condition and print the result
 *
 *	@param		string		$label				Description of the check
 *	@param		bool			$condition		Result to assert
 *
 *	@return		void
 */
function check( string $label, bool $condition ): void {
	global $failures, $checks;
	$checks++;
	if( $condition === true ) {
		echo "  ok  - $label\n";
		return;
	}
	$failures++;
	echo "FAIL  - $label\n";
}

$unit 		= __DIR__. '/../_admin/install/library/pages/.demo-catalogue';
$template = $unit. '/templates/.demo-catalogue.tpl';


// --- The unit itself -------------------------------------------------------

echo "Unit\n";

$manifest = is_file( $unit. '/manifest.php' ) === true ? include $unit. '/manifest.php' : null;

check( 'the unit ships a manifest', is_array( $manifest ) === true );
check( 'it mounts itself on a dot-uri, out of the way of a project\'s own pages', array_key_first( (array) ( $manifest['routes'] ?? [] ) ) === 'GET://.demo-catalogue' );
check( 'the route renders the page template', ( array_values( (array) ( $manifest['routes'] ?? [] ) )[0]['body'] ?? '' ) === '[template /templates/.demo-catalogue]' );

foreach( (array) ( $manifest['templates'] ?? [] ) as $file )
	check( 'declared template exists: '. $file, is_file( $unit. '/templates/'. $file ) === true );

foreach( (array) ( $manifest['files'] ?? [] ) as $file )
	check( 'declared file exists: '. $file, file_exists( $unit. '/'. $file ) === true );

// Every image the page paints with has to be one the unit brings itself -
// pointing at something another unit installs is exactly how the previous
// demo page ended up rendering empty frames
$source = (string) file_get_contents( $template );
preg_match_all( '#\[\[/nino/public\]\](/images/[^"\']+)#', $source, $imageMatches );
$images = array_values( array_unique( $imageMatches[1] ) );

check( 'the page paints with images, and every one of them ships with the unit'. ( $images === [] ? ' - none referenced' : '' ),
	$images !== [] && array_filter( $images, static fn( string $image ): bool => is_file( $unit. $image ) === false ) === [] );

echo "\n";


// --- Section Presets -------------------------------------------------------

echo "Section Presets\n";

preg_match_all( '/data-demo-preset="([^"]+)"\s+data-demo-layout="([^"]+)"/', $source, $demoMatches, PREG_SET_ORDER );

$shown = [];
foreach( $demoMatches as $match )
	$shown[$match[1]][$match[2]] = ( $shown[$match[1]][$match[2]] ?? 0 ) + 1;

$presets = \Nino\Modules\Templates\Library::presets();

check( 'the page marks its specimens for this test to count', $demoMatches !== [] );
check( 'every preset in the library is shown', array_diff( array_keys( $presets ), array_keys( $shown ) ) === [] );

$missingLayouts = [];
foreach( $presets as $key => $preset )
	foreach( array_keys( $preset['layouts'] ) as $layout )
		if( isset( $shown[$key][$layout] ) === false )
			$missingLayouts[] = $key. '/'. $layout;

check( 'every layout of every preset is shown'. ( $missingLayouts === [] ? '' : ' - missing '. implode( ', ', $missingLayouts ) ), $missingLayouts === [] );

$unknown = array_diff( array_keys( $shown ), array_keys( $presets ) );
check( 'no specimen claims a preset the library does not have'. ( $unknown === [] ? '' : ' - '. implode( ', ', $unknown ) ), $unknown === [] );

// A preset whose areas or frame change its appearance is worth more than one
// specimen; these are the ones where a second variant is the point
foreach( [ 'articles-grid' => 4, 'pricing-plans' => 5, 'content-section' => 5 ] as $key => $least )
	check( $key. ' is shown in at least '. $least. ' variants', array_sum( $shown[$key] ?? [] ) >= $least );

echo "\n";


// --- Nino.css classes ------------------------------------------------------

echo "Nino.css classes\n";

$css = (string) file_get_contents( __DIR__. '/../_nino/Nino.css' );

// Comments carry the per-chapter class inventory, which would otherwise count
// as a definition - and .nino-grid, .nino-text and .nino-opacity exist only
// there, as the shorthand for their own families
$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

preg_match_all( '/\.(nino-[a-z0-9-]+)/', $css, $cssMatches );
$defined = array_values( array_unique( $cssMatches[1] ) );

preg_match_all( '/class="([^"]*)"/', $source, $classMatches );
$used = [];
foreach( $classMatches[1] as $attribute )
	foreach( preg_split( '/\s+/', trim( $attribute ) ) ?: [] as $class )
		if( str_starts_with( $class, 'nino-' ) === true )
			$used[$class] = true;

/*	Classes no page template can carry, with the reason each one is here.
	Nothing else belongs in this list: if a class is missing from the catalogue
	and it is not owned by a frame or written by Nino.ui.js, the catalogue is
	incomplete and this test is the place that says so.	*/
$elsewhere = [
	// The header and footer units own these - every page renders them, none
	// declares them (see _admin/install/library/header|footer/<key>/template.tpl)
	'nino-logo', 'nino-headernav-logo', 'nino-nav-burger', 'nino-nav-content', 'nino-nav-bg',
	'nino-footer-main', 'nino-footer-legal', 'nino-footer-title', 'nino-footer-logo',
	'nino-footer-getintouch', 'nino-footer-localepicker', 'nino-localepicker-wrap', 'nino-localepicker-bg',
	'nino-scroll-header',
	// Nino.ui.js writes these onto the page while it runs: page chrome it
	// creates itself, and the state classes it toggles
	'nino-preloader', 'nino-back-to-top', 'nino-cookie-banner', 'nino-cookie-banner--visible', 'nino-cookie-banner-actions',
	'nino-toast', 'nino-toast--success', 'nino-toast--error', 'nino-toast--visible', 'nino-toast-container',
	'nino-slider-controls', 'nino-slider-button', 'nino-slider-points',
	'nino-scroll-atf', 'nino-scroll-btf', 'nino-scroll-down',
	'nino-is-touch', 'nino-is-existing', 'nino-vpa--visible', 'nino-vpa--visible-once',
];

$uncovered = [];
foreach( $defined as $class )
	if( isset( $used[$class] ) === false && in_array( $class, $elsewhere, true ) === false )
		$uncovered[] = $class;

check( 'every class Nino.css defines is on the page'. ( $uncovered === [] ? '' : ' - missing '. implode( ', ', $uncovered ) ), $uncovered === [] );

$stale = [];
foreach( $elsewhere as $class )
	if( in_array( $class, $defined, true ) === false )
		$stale[] = $class;

check( 'the "rendered elsewhere" list carries no class Nino.css dropped'. ( $stale === [] ? '' : ' - '. implode( ', ', $stale ) ), $stale === [] );

echo "\n";


// --- What the page must not contain ----------------------------------------

echo "Rendering\n";

/*	The unit ships no textfills of its own, so every fill left on the page has
	to come from somewhere every installation of it has: the base unit, or a
	module the manifest actually requires. A fill from anywhere else renders as
	its own key on a project that did not happen to pick that module.	*/
preg_match_all( '/\[\[([^\]]+)\]\]/', $source, $fillMatches );
$fills = array_values( array_unique( array_filter( $fillMatches[1], static fn( string $fill ): bool => $fill !== '/nino/public' ) ) );

$library 	= __DIR__. '/../_admin/install/library';
// A module's unit sits beside the module itself - Setup::units() knows where
$units 		= \Nino\Install\Setup::units();
$available = [];
$fragments = glob( $library. '/base/text/*.php' ) ?: [];
foreach( (array) ( $manifest['requiresModules'] ?? [] ) as $module )
	$fragments = array_merge( $fragments, isset( $units[$module] ) === true ? ( glob( $units[$module]. '/text/*.php' ) ?: [] ) : [] );
foreach( $fragments as $fragment )
	foreach( array_keys( (array) ( include $fragment ) ) as $key )
		$available[trim( (string) $key, '[]' )] = true;

$unprovided = array_values( array_filter( $fills, static fn( string $fill ): bool => isset( $available[$fill] ) === false ) );

check( 'every textfill the page leaves comes from the base unit or a required module'. ( $unprovided === [] ? '' : ' - '. implode( ', ', $unprovided ) ), $unprovided === [] );
check( 'and the modules those fills need are declared', $fills === [] || ( $manifest['requiresModules'] ?? [] ) !== [] );

preg_match_all( '/\[(elements|elementvalues|image|nav|localepicker)\b/', $source, $shortcodeMatches );
check( 'no shortcode is left that needs content this unit does not ship'. ( $shortcodeMatches[0] === [] ? '' : ' - '. implode( ', ', array_unique( $shortcodeMatches[0] ) ) ), $shortcodeMatches[0] === [] );

// [template] is allowed exactly three times: the two page slots and the one
// reusable include the "template-include" preset demonstrates
preg_match_all( '/\[template\s+([^\]]+)\]/', $source, $templateMatches );
check( 'the page includes the shell and the one demonstrated template, nothing else',
	$templateMatches[1] === [ '/templates/html-header', '/templates/demo-catalogue-include', '/templates/html-footer' ] );

// Inline styles are how a demo page hides a missing class. If a specimen
// needs one, the framework is what should change
check( 'no specimen falls back to an inline style', preg_match( '/\sstyle="/i', $source ) !== 1 );

$parsed = \Nino\Modules\Templates\SectionDocument::split( $source );
check( 'the page parses as one well-formed document'. ( $parsed['error'] === null ? '' : ' - '. $parsed['error'] ), $parsed['error'] === null );
check( 'it is built from sections, not from one big block', $parsed['sectionCount'] > 50 );

echo "\n";

echo $checks. ' checks, '. $failures. " failed\n";
exit( $failures === 0 ? 0 : 1 );
