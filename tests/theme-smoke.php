<?php
declare(strict_types=1);

/**
 *	Dependency-free backend smoke test for /_theme.
 *	All writes stay inside an isolated temporary project.
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_admin/Admin.php';
require __DIR__. '/../_theme/Theme.php';

$failures = 0;
$checks = 0;

function check( string $label, bool $condition ): void {
	global $failures, $checks;
	$checks++;
	if( $condition ) {
		echo "  ok  - $label\n";
		return;
	}
	$failures++;
	echo "FAIL  - $label\n";
}

function response(): array {
	return [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
}

set_error_handler( function() { return true; } );

$sandbox = sys_get_temp_dir(). '/nino-theme-smoke-'. uniqid();
mkdir( $sandbox. '/private', 0777, true );
mkdir( $sandbox. '/private/templates', 0777, true );
mkdir( $sandbox. '/public/assets', 0777, true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path'] = $sandbox;
$appData['./nino/filesystem/configpath'] = $sandbox. '/private';
$appData['./nino/filesystem/contentpath'] = $sandbox. '/private';
$appData['./nino/filesystem/privatepath'] = $sandbox. '/private';
$appData['./nino/filesystem/publicpath'] = $sandbox. '/public';
$appData['/nino/dir'] = '';
$appData['/nino/locales/native'] = 'en_US';
$appData['/nino/locales/available'] = [ 'en_US' ];

\Nino\Filesystem::putFileContent( $appData, '/config.php', [] );

echo "Sandbox: $sandbox\n\n";

// --- Design - generated palettes that keep their contrast promise -------

echo "Design::palette / css\n";

$lightPalette = \Nino\Theme\Design::palette( [ 'primary' => '#4faae8', 'secondary' => '#94703f' ], 'light' );

// origin is the one surface the generator does not get to move: whatever the
// picker returned has to come back byte for byte, in both modes and at every
// knob position, or the picker is lying about what it does
$originStable = [];
foreach( [ '#4faae8', '#767676', '#ffe600', '#0d2b2e', '#e2001a', '#ffffff', '#000000' ] as $brandHex )
	foreach( [ 'soft', 'default', 'high' ] as $contrastKnob )
		foreach( [ 'clean', 'default', 'vibrant' ] as $colorsKnob )
			foreach( [ 'light', 'dark' ] as $paletteMode ) {
				$settings = \Nino\Theme\Design::normalize( [ 'primary' => $brandHex, 'contrast' => $contrastKnob, 'colors' => $colorsKnob ] );
				$got = \Nino\Theme\Design::palette( $settings, $paletteMode )['origin']['bg'];
				if( $got !== $brandHex )
					$originStable[] = $brandHex. '/'. $contrastKnob. '/'. $colorsKnob. '/'. $paletteMode. ' -> '. $got;
			}

check( 'the brand surface is the picked colour itself, unmoved by any knob or mode', $originStable === []
	|| print_r( array_slice( $originStable, 0, 8 ), true ) === '' );

// What that costs, stated rather than hidden: origin is also the one surface
// whose contrast cannot be promised, and Design::brand() has to say so
$brandSafe = \Nino\Theme\Design::brand( \Nino\Theme\Design::normalize( [ 'primary' => '#4faae8' ] ) );
$brandRisk = \Nino\Theme\Design::brand( \Nino\Theme\Design::normalize( [ 'primary' => '#767676' ] ) );
check( 'the tool reports what the picked brand measures instead of leaving it to the eye',
	$brandSafe['light']['color'] === '#4faae8' && $brandSafe['light']['safe'] === true
	&& $brandRisk['light']['safe'] === false && $brandRisk['light']['ratio'] < $brandRisk['light']['target'] );
check( 'primary text is ink rather than the faintest passing grey', \Nino\Theme\Design::contrast( $lightPalette['default']['on'], '#ffffff' ) > 15.0 );

// The whole feature is this promise, so it is checked across every knob
// position, both modes and a set of brands chosen to break it: pure black and
// white have no hue to hold, mid grey has no chroma, and yellow is the classic
// case where a naive generator ships white-on-yellow
$designFailures = [];
foreach( [ '#4faae8', '#14595e', '#f50963', '#ffd400', '#b6a6ff', '#000000', '#ffffff', '#7f7f7f' ] as $brand )
	foreach( [ 'soft', 'default', 'high' ] as $contrastKnob )
		foreach( [ 'clean', 'default', 'vibrant' ] as $colorsKnob )
			foreach( [ 'light', 'dark' ] as $mode ) {

				$palette = \Nino\Theme\Design::palette( [ 'primary' => $brand, 'contrast' => $contrastKnob, 'colors' => $colorsKnob ], $mode );
				$textTarget  = $contrastKnob === 'high' ? 7.0 : 4.5;
				$mutedTarget = $contrastKnob === 'soft' ? 3.0 : 4.5;

				foreach( $palette as $surface => $values ) {

					// origin is excluded by construction, not by convenience:
					// it carries the picked colour, so there is no lightness
					// left to solve with. vibrant is the same brand made safe,
					// and it is held to the target like everything else
					if( $surface === 'origin' )
						continue;

					$onRatio 		= \Nino\Theme\Design::contrast( $values['on'], $values['bg'] );
					$mutedRatio	= \Nino\Theme\Design::contrast( $values['on-muted'], $values['bg'] );
					$borderRatio	= \Nino\Theme\Design::contrast( $values['border'], $values['bg'] );

					if( $onRatio < $textTarget - 0.02 )
						$designFailures[] = $brand. '/'. $contrastKnob. '/'. $mode. ' '. $surface. ' on '. round( $onRatio, 2 );
					if( $mutedRatio < $mutedTarget - 0.02 )
						$designFailures[] = $brand. '/'. $contrastKnob. '/'. $mode. ' '. $surface. ' muted '. round( $mutedRatio, 2 );
					if( $borderRatio < 2.98 )
						$designFailures[] = $brand. '/'. $contrastKnob. '/'. $mode. ' '. $surface. ' border '. round( $borderRatio, 2 );
					// A subordinate tier that out-contrasts the text it sits
					// under is not subordinate
					if( $mutedRatio > $onRatio + 0.02 )
						$designFailures[] = $brand. '/'. $contrastKnob. '/'. $mode. ' '. $surface. ' muted louder than on';
				}
			}

check( 'every solved pair meets its target, across 8 brands x 3 contrasts x 3 colours x 2 modes', $designFailures === []
	|| print_r( array_slice( $designFailures, 0, 8 ), true ) === '' );

// Every knob has to be observable in the output, or it is a control that
// silently does nothing - the failure mode a generated palette invites
$soft = \Nino\Theme\Design::palette( [ 'primary' => '#4faae8', 'contrast' => 'soft' ], 'light' );
$high = \Nino\Theme\Design::palette( [ 'primary' => '#4faae8', 'contrast' => 'high' ], 'light' );
check( 'the contrast knob moves the safe brand surface and leaves the picked one alone', $soft['vibrant']['bg'] !== $high['vibrant']['bg']
	&& $soft['origin']['bg'] === $high['origin']['bg']
	&& \Nino\Theme\Design::contrast( $high['vibrant']['on'], $high['vibrant']['bg'] ) >= 6.98 );

$clean 	= \Nino\Theme\Design::palette( [ 'primary' => '#4faae8', 'colors' => 'clean' ], 'light' );
$vibrant	= \Nino\Theme\Design::palette( [ 'primary' => '#4faae8', 'colors' => 'vibrant' ], 'light' );
check( 'the colours knob changes saturation without breaking the target', $clean['vibrant']['bg'] !== $vibrant['vibrant']['bg']
	&& $clean['origin']['bg'] === $vibrant['origin']['bg']
	&& \Nino\Theme\Design::contrast( $vibrant['vibrant']['on'], $vibrant['vibrant']['bg'] ) >= 4.48 );

check( 'a secondary of its own reaches the vibrant surface', \Nino\Theme\Design::palette( [ 'primary' => '#4faae8', 'secondary' => '#94703f' ], 'light' )['vibrant']['bg']
	!== \Nino\Theme\Design::palette( [ 'primary' => '#4faae8' ], 'light' )['vibrant']['bg'] );

// Out-of-gamut OKLCH does not darken when it clips, it shifts hue - which is
// how a brand blue once came back as pale cyan
check( 'an out-of-gamut request is brought into sRGB instead of clipping to a different hue', \Nino\Theme\Design::contrast( \Nino\Theme\Design::hex( 0.6, 0.4, 4.2 ), '#ffffff' ) > 1.0
	&& preg_match( '/^#[0-9a-f]{6}$/', \Nino\Theme\Design::hex( 0.6, 0.4, 4.2 ) ) === 1 );

check( 'a malformed brand falls back instead of emitting garbage', preg_match( '/^#[0-9a-f]{6}$/', \Nino\Theme\Design::palette( [ 'primary' => 'not-a-colour' ], 'light' )['origin']['bg'] ) === 1 );
check( 'a three-digit hex is accepted', \Nino\Theme\Design::palette( [ 'primary' => '#4ae' ], 'light' )['origin']['bg'] === \Nino\Theme\Design::palette( [ 'primary' => '#44aaee' ], 'light' )['origin']['bg'] );

$designCss = \Nino\Theme\Design::css( [ 'primary' => '#4faae8', 'secondary' => '#94703f' ] );
check( 'the stylesheet declares both modes and the three-state pattern', str_contains( $designCss, 'color-scheme: light dark' )
	&& str_contains( $designCss, '@media (prefers-color-scheme: dark)' )
	&& str_contains( $designCss, ':root:not([data-nino-mode="light"])' )
	&& str_contains( $designCss, ':root[data-nino-mode="dark"]' ) );
check( 'tokens name the surface after the on- prefix, modifiers last', str_contains( $designCss, '--nino-origin:' )
	&& str_contains( $designCss, '--nino-on-origin:' )
	&& str_contains( $designCss, '--nino-on-origin-muted:' )
	&& str_contains( $designCss, '--nino-origin-border:' ) );
// Every declaration has to be a six-digit hex or the one colour-scheme
// keyword. A NAN or an empty string out of the solver would otherwise ship as
// a variable nothing can resolve, and the page would fall back mid-cascade
preg_match_all( '/^\s*(--[a-z0-9-]+|color-scheme)\s*:\s*([^;]*);$/mi', $designCss, $designDeclarations, PREG_SET_ORDER );
$designMalformed = array_filter( $designDeclarations, static function( array $declaration ): bool {
	$value = trim( $declaration[2] );
	return match( true ) {
		$declaration[1] === 'color-scheme'	=> $value !== 'light dark',
		// Shadows are the one non-hex colour: they carry an alpha, which a
		// solid colour cannot express
		str_ends_with( $declaration[1], '-shadow' )	=> preg_match( '/^rgb\( *\d+ \d+ \d+ *\/ *\d+% *\)$/', $value ) !== 1,
		// The size raster - a length, or a bare ratio for line-height
		$declaration[1] === '--nino-line-height'	=> preg_match( '/^\d+(\.\d+)?$/', $value ) !== 1,
		(bool) preg_match( '/^--nino-(text|space|radius)-/', $declaration[1] )	=> preg_match( '/^\d+(\.\d+)?rem$/', $value ) !== 1,
		default								=> preg_match( '/^#[0-9a-f]{6}$/', $value ) !== 1,
	};
} );
check( 'every declaration in the generated stylesheet is a resolvable value', $designMalformed === []
	// 9 surfaces x 10 values x 3 colour blocks, plus the one colour-scheme,
	// plus the raster: line-height, 6 text, 6 space, 3 radius, the pill, and
	// the 3 wide-screen text steps. The raster is emitted once, not per mode
	&& count( $designDeclarations ) === 271 + 20 );

echo "\n";



// --- the surfaces and values the extended vocabulary adds ---------------

echo "Design - surfaces, states and status\n";

$vocabulary = \Nino\Theme\Design::palette( \Nino\Theme\Design::normalize( [ 'primary' => '#4faae8' ] ), 'light' );

check( 'status surfaces come out of the same machinery as the brand ones', array_keys( $vocabulary ) === [ 'default', 'alt', 'dark', 'black', 'origin', 'vibrant', 'success', 'warning', 'danger' ] );
check( 'every surface carries the full state set', array_keys( $vocabulary['default'] ) === [ 'bg', 'on', 'on-muted', 'link', 'border', 'focus', 'hover', 'active', 'disabled', 'shadow' ] );

// A link solved against the page ground and then dropped onto a dark section
// is the failure this value exists to prevent
check( 'a link is solved per surface, not once for the whole page', $vocabulary['default']['link'] !== $vocabulary['dark']['link']
	&& \Nino\Theme\Design::contrast( $vocabulary['dark']['link'], $vocabulary['dark']['bg'] ) >= 4.48
	&& \Nino\Theme\Design::contrast( $vocabulary['black']['link'], $vocabulary['black']['bg'] ) >= 4.48 );

// SC 1.4.11 - and the defect the audit found in Nino.css, where the ring is
// removed and replaced by a 1.87:1 border change
check( 'the focus ring clears 3:1 on every surface, in both modes', array_filter(
	array_merge(
		\Nino\Theme\Design::palette( \Nino\Theme\Design::normalize( [ 'primary' => '#4faae8' ] ), 'light' ),
		\Nino\Theme\Design::palette( \Nino\Theme\Design::normalize( [ 'primary' => '#4faae8' ] ), 'dark' )
	),
	static fn( array $values ): bool => \Nino\Theme\Design::contrast( $values['focus'], $values['bg'] ) < 2.98
) === [] );

check( 'active is a visible step past hover, not the same nudge twice', $vocabulary['default']['hover'] !== $vocabulary['default']['active']
	&& \Nino\Theme\Design::contrast( $vocabulary['default']['active'], $vocabulary['default']['bg'] )
		> \Nino\Theme\Design::contrast( $vocabulary['default']['hover'], $vocabulary['default']['bg'] ) );

// Disabled is the one value that must NOT meet the text target - it has to
// read as unavailable rather than merely quiet
$disabledRatio = \Nino\Theme\Design::contrast( $vocabulary['default']['disabled'], $vocabulary['default']['bg'] );
check( 'disabled sits below the text target on purpose, but stays perceivable', $disabledRatio < 4.5 && $disabledRatio > 1.9 );

check( 'the dark palette casts its own shadow instead of reusing the light one', $vocabulary['default']['shadow']
	!== \Nino\Theme\Design::palette( \Nino\Theme\Design::normalize( [ 'primary' => '#4faae8' ] ), 'dark' )['default']['shadow'] );

// Red has to stay red: the brand knobs must not be able to turn a danger
// surface into something reassuring
$brandA = \Nino\Theme\Design::palette( \Nino\Theme\Design::normalize( [ 'primary' => '#4faae8' ] ), 'light' );
$brandB = \Nino\Theme\Design::palette( \Nino\Theme\Design::normalize( [ 'primary' => '#2f6d4f', 'colors' => 'vibrant' ] ), 'light' );
check( 'status hues are fixed and survive a change of brand', $brandA['danger']['bg'] === $brandB['danger']['bg']
	&& $brandA['success']['bg'] === $brandB['success']['bg'] );

echo "\n";


// --- raster - the size half -------------------------------------------

echo "Design::raster\n";

$rem = static fn( string $value ): float => (float) rtrim( $value, 'rem' );

// The one property that makes the layer adoptable: turning it on without
// asking for anything must not move a project. These are Nino.css's own
// values, and they are here rather than derived so a drift in either shows up
$defaultRaster = \Nino\Theme\Design::raster( \Nino\Theme\Design::normalize( [] ) );

check( 'the default raster is Nino.css\'s own scale, so switching the layer on moves nothing', array_values( $defaultRaster['text'] ) === [ '1rem', '1.125rem', '1.25rem', '1.5rem', '1.8rem', '2.5rem' ]
	&& array_values( $defaultRaster['space'] ) === [ '0.5rem', '1rem', '2rem', '3rem', '5rem', '8rem' ]
	&& array_values( $defaultRaster['textWide'] ) === [ '1.6rem', '2rem', '3rem' ]
	&& $defaultRaster['lineHeight'] === '1.5' );

// Every setting, not just the ones a picker offers in the middle
$rasterSettings = [];
foreach( \Nino\Theme\Design::choices()['volume'] as $volume )
	foreach( \Nino\Theme\Design::choices()['spacing'] as $spacing )
		foreach( \Nino\Theme\Design::choices()['shaping'] as $shaping )
			$rasterSettings[] = [ 'volume' => $volume, 'spacing' => $spacing, 'shaping' => $shaping ];

$notMonotonic 	= [];
$bodyTooSmall 	= [];
$wideNotBigger 	= [];

foreach( $rasterSettings as $settings ) {

	$raster = \Nino\Theme\Design::raster( \Nino\Theme\Design::normalize( $settings ) );
	$label 	= $settings['volume']. '/'. $settings['spacing']. '/'. $settings['shaping'];

	foreach( [ 'text', 'space', 'radius' ] as $group ) {

		$values = array_values( $raster[$group] );

		for( $step = 1; $step < count( $values ); $step++ )
			if( $rem( $values[$step] ) <= $rem( $values[$step - 1] ) )
				$notMonotonic[] = $label. ' '. $group. ' step '. ( $step + 1 );
	}

	// Body copy is the size that is already right. A knob may fan the display
	// end in either direction, but it must never make reading harder
	if( $rem( $raster['text'][1] ) < 1.0 )
		$bodyTooSmall[] = $label. ': '. $raster['text'][1];

	// The wide-screen block only ever overrides upwards - a step that came out
	// smaller on a big screen would read as a broken media query
	foreach( $raster['textWide'] as $step => $value )
		if( $rem( $value ) <= $rem( $raster['text'][$step] ) )
			$wideNotBigger[] = $label. ' text-'. $step;
}

check( 'every scale is strictly monotonic at every setting'. ( $notMonotonic === [] ? '' : ' - '. implode( ', ', array_slice( $notMonotonic, 0, 4 ) ) ), $notMonotonic === [] );
check( 'body copy never drops below the base size, whatever the volume'. ( $bodyTooSmall === [] ? '' : ' - '. implode( ', ', $bodyTooSmall ) ), $bodyTooSmall === [] );
check( 'the wide-screen steps only ever override upwards'. ( $wideNotBigger === [] ? '' : ' - '. implode( ', ', $wideNotBigger ) ), $wideNotBigger === [] );
check( 'all 27 combinations produce a complete raster', count( $rasterSettings ) === 27 );

// The three knobs have to be separable, or a picker cannot explain them
$volumeOnly 	= \Nino\Theme\Design::raster( \Nino\Theme\Design::normalize( [ 'volume' => 'generous' ] ) );
$spacingOnly	= \Nino\Theme\Design::raster( \Nino\Theme\Design::normalize( [ 'spacing' => 'airy' ] ) );
$shapingOnly	= \Nino\Theme\Design::raster( \Nino\Theme\Design::normalize( [ 'shaping' => 'round' ] ) );

check( 'Volume moves type and leaves spacing and radius alone', $volumeOnly['text'] !== $defaultRaster['text']
	&& $volumeOnly['space'] === $defaultRaster['space']
	&& $volumeOnly['radius'] === $defaultRaster['radius'] );
check( 'Spacing moves the gaps and the line height, not the type sizes', $spacingOnly['space'] !== $defaultRaster['space']
	&& $spacingOnly['lineHeight'] !== $defaultRaster['lineHeight']
	&& $spacingOnly['text'] === $defaultRaster['text'] );
check( 'Shaping moves the radii and nothing else', $shapingOnly['radius'] !== $defaultRaster['radius']
	&& $shapingOnly['text'] === $defaultRaster['text']
	&& $shapingOnly['space'] === $defaultRaster['space'] );

// Volume is anchored at step 1: a scale grows by fanning out at the display
// end, not by pushing body copy up with it
check( 'Volume is anchored at body copy and fans out toward the display end', $volumeOnly['text'][1] === $defaultRaster['text'][1]
	&& $rem( $volumeOnly['text'][6] ) > $rem( $defaultRaster['text'][6] ) * 1.2 );

// A size has no light and dark variant, so it is emitted once
$rasterCss = \Nino\Theme\Design::css( \Nino\Theme\Design::normalize( [ 'shaping' => 'round' ] ) );
check( 'the raster is emitted once, not repeated per colour mode', substr_count( $rasterCss, '--nino-space-3:' ) === 1
	&& substr_count( $rasterCss, '--nino-radius-2:' ) === 1
	&& substr_count( $rasterCss, '--nino-line-height:' ) === 1 );
check( '...and only its wide-screen half gets a media query of its own', substr_count( $rasterCss, '@media (min-width: 768px)' ) === 1
	&& substr_count( $rasterCss, '--nino-text-6:' ) === 2 );
check( 'a pill is the same answer at every shaping setting', str_contains( $rasterCss, '--nino-radius-full: 999rem;' )
	&& str_contains( \Nino\Theme\Design::css( \Nino\Theme\Design::normalize( [ 'shaping' => 'sharp' ] ) ), '--nino-radius-full: 999rem;' ) );

// Same rule as the colours: nothing off the wire is trusted
check( 'an unknown size setting falls back rather than reaching the raster', \Nino\Theme\Design::raster( \Nino\Theme\Design::normalize( [ 'volume' => 'enormous', 'spacing' => [], 'shaping' => '../../etc' ] ) ) === $defaultRaster );

echo "\n";


// --- normalize - nothing posted is trusted ------------------------------

echo "Design::normalize\n";

check( 'a complete set comes back from nothing at all', array_keys( \Nino\Theme\Design::normalize( [] ) ) === [ 'primary', 'secondary', 'contrast', 'colors', 'volume', 'spacing', 'shaping' ] );
check( 'an unknown knob position falls back rather than reaching the generator', \Nino\Theme\Design::normalize( [ 'contrast' => 'enormous', 'colors' => '../etc' ] ) ['contrast'] === 'default' );
check( 'a colour without its hash is still accepted', \Nino\Theme\Design::normalize( [ 'primary' => '4faae8' ] )['primary'] === '#4faae8' );
check( 'a three-digit colour is expanded once, here', \Nino\Theme\Design::normalize( [ 'primary' => '#4AE' ] )['primary'] === '#44aaee' );
check( 'a value that is not a colour is refused, not silently carried', \Nino\Theme\Design::normalize( [ 'primary' => 'url(evil)' ] )['primary'] === '#4faae8' );
check( 'an empty secondary stays empty - it means "use the brand", not "fall back"', \Nino\Theme\Design::normalize( [] )['secondary'] === '' );

echo "\n";


// --- Theme::write - the stylesheet and its place in the bundle ----------

echo "Theme::write / bundle\n";

$appData['/nino/html/assets'] = [ '/.cache/style.css' => [ '/_nino/Nino.css', '/assets/style.theme.basis.css', '/assets/style.custom.css' ] ];

check( 'writing succeeds', \Nino\Theme\Theme::write( $appData, \Nino\Theme\Design::normalize( [ 'primary' => '#14595e' ] ) ) === true );
check( 'the stylesheet lands where a project file belongs', \Nino\Filesystem::fileExists( $appData, '/assets/style.design.css' ) === true );

$written = (string) \Nino\Filesystem::getFileContent( $appData, '/assets/style.design.css', '' );
check( 'it carries the generated palette', str_contains( $written, '--nino-origin:' ) && str_contains( $written, '--nino-on-danger:' ) );
check( 'it says plainly that it is generated and will be overwritten', str_contains( $written, 'Generated by /_theme' ) && str_contains( $written, 'Do not edit' ) );

$bundle = $appData['/nino/html/assets']['/.cache/style.css'];
check( 'it sits directly behind the framework stylesheet, ahead of everything that reads it', $bundle === [
	'/_nino/Nino.css', '/assets/style.design.css', '/assets/style.theme.basis.css', '/assets/style.custom.css' ] );

// A header/footer variant, a second theme, a project stylesheet - the bundle
// grows, and the generated file has to keep its place without being counted in
$appData['/nino/html/assets']['/.cache/style.css'] = [
	'/_nino/Nino.css', '/assets/style.design.css', '/assets/style.header.v2.css', '/assets/style.theme.basis.css' ];
\Nino\Theme\Theme::write( $appData, \Nino\Theme\Design::normalize( [ 'primary' => '#8f2d56' ] ) );
check( 'a second write does not add a second entry', $appData['/nino/html/assets']['/.cache/style.css'] === [
	'/_nino/Nino.css', '/assets/style.design.css', '/assets/style.header.v2.css', '/assets/style.theme.basis.css' ] );

// The whole point of the tool: the values stay editable after the install
$reloaded = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the settings are persisted, so they can be reopened and changed later', ( $reloaded['/nino/theme/design']['primary'] ?? '' ) === '#8f2d56' );
check( 'and the bundle entry is persisted with them', in_array( '/assets/style.design.css', $reloaded['/nino/html/assets']['/.cache/style.css'] ?? [], true ) === true );

// Written twice: the file has to be replaced, not grown, and has to carry the
// second set of settings rather than both
$firstWrite = (string) \Nino\Filesystem::getFileContent( $appData, '/assets/style.design.css', '' );
\Nino\Theme\Theme::write( $appData, \Nino\Theme\Design::normalize( [ 'primary' => '#14595e' ] ) );
$secondWrite = (string) \Nino\Filesystem::getFileContent( $appData, '/assets/style.design.css', '' );
check( 'rewriting regenerates rather than appending', strlen( $firstWrite ) === strlen( $secondWrite )
	&& $firstWrite !== $secondWrite
	&& substr_count( $secondWrite, '/* Generated by /_theme' ) === 1
	&& substr_count( $secondWrite, '--nino-space-1:' ) === 1 );

// A hand-emptied bundle still has to end up with the generated file first,
// since everything else reads from it
$emptyBundle = $appData;
$emptyBundle['/nino/html/assets'] = [ '/.cache/style.css' => [ '/assets/style.custom.css' ] ];
check( 'with no framework stylesheet in the bundle it still leads', \Nino\Theme\Theme::bundle( $emptyBundle )['/.cache/style.css']
	=== [ '/assets/style.design.css', '/assets/style.custom.css' ] );

echo "\n";


// --- the tool's own surface --------------------------------------------

echo "Theme::init / guard\n";

check( 'the post-install tool owns appearance changes without loading the removable installer',
	class_exists( '\\Nino\\Install\\Themes', false ) === false
	&& is_dir( __DIR__. '/../_install/library/themes' ) === true );

$routes = $appData;
\Nino\Theme\Theme::init( $routes );
check( 'the tool owns its routes', ( $routes['/nino/http/routes']['GET://_theme']['uri'] ?? '' ) === '/_theme'
	&& ( $routes['/nino/http/routes']['POST://_theme']['uri'] ?? '' ) === '/_theme' );

$authenticatedPage = (string) file_get_contents( __DIR__. '/../_theme/templates/page-index.tpl' );
check( 'the authenticated page carries the CSRF field its POST API needs', substr_count( $authenticatedPage, '[csrf]' ) === 1 );

$anonymous = response();
check( 'guard rejects an unauthenticated request', \Nino\Theme\Theme::guard( $appData, $anonymous ) === false
	&& $anonymous['/nino/http/response']['statusCode'] === 401 );

foreach( [ 'appearance/read', 'theme/apply', 'frame/preview', 'frame/apply', 'design/read', 'design/preview', 'design/save' ] as $action ) {
	$request = response();
	$_POST = [ 'action' => $action, 'data' => '{}' ];
	\Nino\Theme\Theme::handlePost( $appData, $request );
	check( $action. ' requires an authed session of its own', $request['/nino/http/response']['statusCode'] === 401 );
}

\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

$appearance = response();
$_POST = [ 'action' => 'appearance/read', 'data' => '{}' ];
\Nino\Theme\Theme::handlePost( $appData, $appearance );
$appearanceBody = $appearance['/nino/http/response']['body'] ?? [];

check( 'the authenticated tool exposes Theme, Header and Footer choices from the shared appearance library',
	array_keys( $appearanceBody['themes'] ?? [] ) === [ 'basis', 'docs', 'editorial', 'nocturne', 'rail', 'signal', 'soft', 'studio' ]
	&& ( $appearanceBody['frames']['header'] ?? [] ) === [ 'v1', 'v2', 'v3', 'v4', 'v5', 'v6' ]
	&& ( $appearanceBody['frames']['footer'] ?? [] ) === [ 'v1', 'v2', 'v3', 'v4', 'v5', 'v6' ] );

$badTheme = response();
$_POST = [ 'action' => 'theme/apply', 'data' => json_encode( [ 'theme' => '../basis' ] ) ];
\Nino\Theme\Theme::handlePost( $appData, $badTheme );
check( 'a Theme key cannot leave the shared catalogue', $badTheme['/nino/http/response']['statusCode'] === 400 );

$badFrame = response();
$_POST = [ 'action' => 'frame/apply', 'data' => json_encode( [ 'kind' => 'header', 'frame' => '../v1' ] ) ];
\Nino\Theme\Theme::handlePost( $appData, $badFrame );
check( 'a frame key cannot leave its kind catalogue', $badFrame['/nino/http/response']['statusCode'] === 400 );

$themeApply = response();
$_POST = [ 'action' => 'theme/apply', 'data' => json_encode( [ 'theme' => 'basis' ] ) ];
\Nino\Theme\Theme::handlePost( $appData, $themeApply );
$afterTheme = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'Theme applies the manifest as one complete baseline', $themeApply['/nino/http/response']['statusCode'] === 200
	&& ( $afterTheme['/nino/install/theme'] ?? '' ) === 'basis'
	&& ( $afterTheme['/nino/install/header'] ?? '' ) === 'v1'
	&& ( $afterTheme['/nino/install/footer'] ?? '' ) === 'v1'
	&& ( $afterTheme['/nino/theme/design']['primary'] ?? '' ) === '#4faae8' );

$designSave = response();
$_POST = [ 'action' => 'design/save', 'data' => json_encode( [ 'primary' => '#14595e', 'spacing' => 'tight' ] ) ];
\Nino\Theme\Theme::handlePost( $appData, $designSave );
$afterDesign = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'Design changes only its generated values, not Theme or either frame', ( $afterDesign['/nino/theme/design']['primary'] ?? '' ) === '#14595e'
	&& ( $afterDesign['/nino/theme/design']['spacing'] ?? '' ) === 'tight'
	&& ( $afterDesign['/nino/install/theme'] ?? '' ) === 'basis'
	&& ( $afterDesign['/nino/install/header'] ?? '' ) === 'v1'
	&& ( $afterDesign['/nino/install/footer'] ?? '' ) === 'v1' );

$framePreview = response();

// A legacy text file may still carry Windows-1252 bytes, and company names
// commonly contain characters that are syntax in the shortcode argument and
// the generated HTML attribute. The preview has to recover the text and quote
// those boundaries while leaving the visible spelling unchanged.
$appData['/nino/locales/textfiles'] = '/text';
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [
	'[[/company/name]]' => "M\xFCller & S\xF6hne \"Studio\"",
] );

$_POST = [ 'action' => 'frame/preview', 'data' => json_encode( [ 'kind' => 'header', 'frame' => 'v3', 'theme' => 'basis' ] ) ];
\Nino\Theme\Theme::handlePost( $appData, $framePreview );
check( 'Header and Footer previews remain inert server-rendered documents', $framePreview['/nino/http/response']['statusCode'] === 200
	&& str_starts_with( (string) ( $framePreview['/nino/http/response']['body']['html'] ?? '' ), '<!doctype html>' )
	&& str_contains( (string) ( $framePreview['/nino/http/response']['body']['html'] ?? '' ), '<script' ) === false );
check( 'Header previews preserve special characters without breaking their HTML boundaries',
	str_contains(
		(string) ( $framePreview['/nino/http/response']['body']['html'] ?? '' ),
		"M\u{00FC}ller &amp; S\u{00F6}hne &quot;Studio&quot;"
	) );

$frameApply = response();
$_POST = [ 'action' => 'frame/apply', 'data' => json_encode( [ 'kind' => 'header', 'frame' => 'v3' ] ) ];
\Nino\Theme\Theme::handlePost( $appData, $frameApply );
$afterFrame = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'a Header apply changes only Header and preserves the settled Theme, Design and Footer',
	( $afterFrame['/nino/install/header'] ?? '' ) === 'v3'
	&& ( $afterFrame['/nino/install/footer'] ?? '' ) === 'v1'
	&& ( $afterFrame['/nino/install/theme'] ?? '' ) === 'basis'
	&& ( $afterFrame['/nino/theme/design'] ?? [] ) === ( $afterDesign['/nino/theme/design'] ?? [] ) );

$unknown = response();
$_POST = [ 'action' => 'design/../../etc', 'data' => '{}' ];
\Nino\Theme\Theme::handlePost( $appData, $unknown );
check( 'an unknown action is refused before anything else happens', $unknown['/nino/http/response']['statusCode'] === 400 );

echo "\n". $checks. ' checks, '. $failures. " failed\n";

exit( $failures === 0 ? 0 : 1 );
