<?php
declare(strict_types=1);

/**
 *	Dependency-free backend smoke test for /_design.
 *	All writes stay inside an isolated temporary project.
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_admin/Admin.php';
require __DIR__. '/../_design/Design.php';

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

$sandbox = sys_get_temp_dir(). '/nino-design-smoke-'. uniqid();
mkdir( $sandbox. '/private', 0777, true );
mkdir( $sandbox. '/private/templates', 0777, true );
mkdir( $sandbox. '/private/assets', 0777, true );

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

$lightPalette = \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'secondary' => '#94703f' ], 'light' );

// brand is the one surface the generator does not get to move: whatever the
// picker returned has to come back byte for byte, in both modes and at every
// knob position, or the picker is lying about what it does
$originStable = [];
foreach( [ '#4faae8', '#767676', '#ffe600', '#0d2b2e', '#e2001a', '#ffffff', '#000000' ] as $brandHex )
	foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $knobStep )
		foreach( [ 'light', 'dark' ] as $paletteMode ) {
			$settings = \Nino\Design\Tokens::normalize( [ 'primary' => $brandHex, 'contrast' => $knobStep, 'saturation' => $knobStep, 'temperature' => $knobStep, 'depth' => $knobStep, 'harmony' => $knobStep ] );
			$got = \Nino\Design\Tokens::palette( $settings, $paletteMode )['brand']['bg'];
			if( $got !== $brandHex )
				$originStable[] = $brandHex. '/'. $knobStep. '/'. $paletteMode. ' -> '. $got;
		}

check( 'the brand surface is the picked colour itself, unmoved by any knob or mode', $originStable === []
	|| print_r( array_slice( $originStable, 0, 8 ), true ) === '' );

// What that costs, stated rather than hidden: brand is also the one surface
// whose contrast cannot be promised, and Design::brand() has to say so
$brandSafe = \Nino\Design\Tokens::brand( \Nino\Design\Tokens::normalize( [ 'primary' => '#4faae8' ] ) );
$brandRisk = \Nino\Design\Tokens::brand( \Nino\Design\Tokens::normalize( [ 'primary' => '#767676' ] ) );
check( 'the tool reports what the picked brand measures instead of leaving it to the eye',
	$brandSafe['light']['color'] === '#4faae8' && $brandSafe['light']['safe'] === true
	&& $brandRisk['light']['safe'] === false && $brandRisk['light']['ratio'] < $brandRisk['light']['target'] );
check( 'primary text is ink rather than the faintest passing grey', \Nino\Design\Tokens::contrast( $lightPalette['default']['on'], '#ffffff' ) > 15.0 );

/*	The whole feature is this promise, so it is checked across the full
	contrast x saturation grid, both modes and a set of brands chosen to break
	it: pure black and white have no hue to hold, mid grey has no chroma, and
	yellow is the classic case where a naive generator ships white-on-yellow.

	The floor is 4.5:1 at every position, body text and muted alike. That is
	WCAG 2.2 SC 1.4.3 and it is the reason the Contrast knob moves the ink
	rather than the target: a knob that could dial legibility below AA is not
	a design setting, it is a defect with a label on it.	*/
$designFailures = [];
$AA = 4.5;
foreach( [ '#4faae8', '#14595e', '#f50963', '#ffd400', '#b6a6ff', '#000000', '#ffffff', '#7f7f7f' ] as $brand )
	foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $contrastKnob )
		foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $saturationKnob )
			foreach( [ 'light', 'dark' ] as $mode ) {

				$palette = \Nino\Design\Tokens::palette( [ 'primary' => $brand, 'contrast' => $contrastKnob, 'saturation' => $saturationKnob ], $mode );
				$textTarget = max( $AA, [ 1 => 4.5, 2 => 4.5, 3 => 7.0 ][$contrastKnob] );
				$where = $brand. '/c'. $contrastKnob. '/s'. $saturationKnob. '/'. $mode;

				foreach( $palette as $surface => $values ) {

					// The two picked colours are excluded by construction, not
					// by convenience: they carry the hex the operator typed or
					// the wheel derived, so there is no lightness left to solve
					// with. brand-safe and accent-safe are those same colours
					// made safe, and are held to the target like everything else
					if( $surface === 'brand' || $surface === 'accent' )
						continue;

					$onRatio		= \Nino\Design\Tokens::contrast( $values['on'], $values['bg'] );
					$mutedRatio		= \Nino\Design\Tokens::contrast( $values['on-muted'], $values['bg'] );
					$borderRatio	= \Nino\Design\Tokens::contrast( $values['border'], $values['bg'] );
					$focusRatio		= \Nino\Design\Tokens::contrast( $values['focus'], $values['bg'] );

					if( $onRatio < $textTarget - 0.02 )
						$designFailures[] = $where. ' '. $surface. ' on '. round( $onRatio, 2 );
					// The muted tier is text, so AA is AA. It used to be let
					// down to 3.0, which only passes for large type
					if( $mutedRatio < $AA - 0.02 )
						$designFailures[] = $where. ' '. $surface. ' muted '. round( $mutedRatio, 2 );
					if( $borderRatio < 2.98 )
						$designFailures[] = $where. ' '. $surface. ' border '. round( $borderRatio, 2 );
					// SC 1.4.11, and the one value no knob may soften
					if( $focusRatio < 2.98 )
						$designFailures[] = $where. ' '. $surface. ' focus '. round( $focusRatio, 2 );
					// A subordinate tier that out-contrasts the text it sits
					// under is not subordinate
					if( $mutedRatio > $onRatio + 0.02 )
						$designFailures[] = $where. ' '. $surface. ' muted louder than on';
				}
			}

check( 'every solved pair clears WCAG AA, across 8 brands x 3 contrasts x 3 saturations x 2 modes', $designFailures === []
	|| print_r( array_slice( $designFailures, 0, 8 ), true ) === '' );

/*	Every knob has to be observable in the output, or it is a control that
	silently does nothing - the failure mode a generated palette invites, and
	the one both of the fixed knobs below were in.	*/

// Contrast used to name three positions and produce two results: 'soft' and
// 'default' were the same 4.5 target and the body ink never moved between
// them. What the reader feels is the ink, so that is what the knob moves now
$inkByStep = [];
foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $step )
	$inkByStep[$step] = \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'contrast' => $step ], 'light' )['default']['on'];

check( 'contrast moves the body ink at every position, not just at the top', count( array_unique( $inkByStep ) ) === \Nino\Design\Tokens::STEPS );
check( '...and it moves it monotonically toward black', ( static function( array $ink ): bool {
	$previous = 0.0;
	foreach( $ink as $hex ) {
		$ratio = \Nino\Design\Tokens::contrast( $hex, '#ffffff' );
		if( $ratio <= $previous )
			return false;
		$previous = $ratio;
	}
	return true;
} )( $inkByStep ) );

$soft = \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'contrast' => 1 ], 'light' );
$high = \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'contrast' => 3 ], 'light' );
check( 'the contrast knob moves the safe brand surface and leaves the picked one alone', $soft['brand-safe']['bg'] !== $high['brand-safe']['bg']
	&& $soft['brand']['bg'] === $high['brand']['bg']
	&& \Nino\Design\Tokens::contrast( $high['brand-safe']['on'], $high['brand-safe']['bg'] ) >= 6.98 );

/*	Saturation used to scale the brand and nothing else. The four surfaces a
	page is actually made of - the ground, the alternate band, and the two dark
	ones - came back byte-identical at all three of its old positions, as did
	every border and every link, because the neutral tint and the link floor
	were both constants the knob never touched.	*/
$muted	= \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'saturation' => 1 ], 'light' );
$rich	= \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'saturation' => 3 ], 'light' );

check( 'saturation reaches the page ground, not only the brand', $muted['default']['bg'] !== $rich['default']['bg']
	|| $muted['alt']['bg'] !== $rich['alt']['bg'] );
check( '...the links and the borders with it', $muted['default']['link'] !== $rich['default']['link']
	&& $muted['default']['border'] !== $rich['default']['border'] );
check( '...and it leaves the picked brand alone', $muted['brand']['bg'] === $rich['brand']['bg'] );

/*	Turning a knob up has to move in the direction it says. The old 1.5x
	position asked for more chroma than sRGB holds at the lightness the solver
	had already chosen, so hex() reduced it and Intense came back *less*
	saturated than Standard - measured 0.115 against 0.126 for the default
	brand blue, and 0.102 against 0.111 for yellow.	*/
$notMonotonicChroma = [];
foreach( [ '#4faae8', '#ffd400', '#e02329', '#215d63', '#b6a6ff' ] as $brand )
	foreach( [ 'light', 'dark' ] as $mode ) {

		$previous = -1.0;

		foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $step ) {

			[ , $chroma ] = \Nino\Design\Tokens::oklch( \Nino\Design\Tokens::palette( [ 'primary' => $brand, 'saturation' => $step ], $mode )['brand-safe']['bg'] );

			if( $chroma < $previous - 0.002 )
				$notMonotonicChroma[] = $brand. '/'. $mode. ' step '. $step. ' '. round( $chroma, 3 ). ' < '. round( $previous, 3 );

			$previous = max( $previous, $chroma );
		}
	}

check( 'turning saturation up never comes back less saturated'. ( $notMonotonicChroma === [] ? '' : ' - '. implode( ', ', array_slice( $notMonotonicChroma, 0, 4 ) ) ), $notMonotonicChroma === [] );
check( 'the saturation knob keeps its target while it does it', \Nino\Design\Tokens::contrast( $rich['brand-safe']['on'], $rich['brand-safe']['bg'] ) >= 4.48 );

// Temperature: which way the greys lean, and nothing else. The brand is the
// brand at every position
$cool = \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'temperature' => 2 ], 'light' );
$warm = \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'temperature' => 4 ], 'light' );
check( 'temperature moves the neutral surfaces', $cool['alt']['bg'] !== $warm['alt']['bg']
	&& $cool['black']['bg'] !== $warm['black']['bg'] );
check( '...and leaves the brand and the status hues where they are', $cool['brand']['bg'] === $warm['brand']['bg']
	&& $cool['danger']['bg'] === $warm['danger']['bg']
	&& $cool['success']['bg'] === $warm['success']['bg'] );

/*	...and the position that is not on that arc at all. A grey biased toward
	something reads as chosen - but that is a claim about a page whose brand is
	meant to be everywhere, and a design that wants its colour only where it is
	put had no way to say so while every ground carried a trace of it	*/
$neutral = \Nino\Design\Tokens::palette( [ 'primary' => '#f50963', 'temperature' => 1 ], 'light' );
$neutralFailures = [];

foreach( [ 'default', 'alt', 'dark', 'black' ] as $ground ) {
	[ , $chroma ] = \Nino\Design\Tokens::oklch( $neutral[$ground]['bg'] );
	if( $chroma > 0.0005 )
		$neutralFailures[] = $ground. ' '. round( $chroma, 4 );
}

check( 'Neutral takes the colour cast off the grounds entirely', $neutralFailures === []
	|| print_r( $neutralFailures, true ) === '' );
// Off the grounds, not off the page: the brand is still the brand, and the
// tint is still the surface that carries it
check( '...without touching the brand or the tint', $neutral['brand']['bg'] === '#f50963'
	&& \Nino\Design\Tokens::oklch( $neutral['tint']['bg'] )[1] > 0.01 );

/*	Harmony: where the second brand colour sits, as the four classical
	relationships rather than as a track. Monochrome is where there is no second
	colour - the accent is the brand, which is what the palette did before it
	had a second colour to place at all	*/
$angles = [];

foreach( [ 1, 2, 3, 4 ] as $position ) {
	[ , , $hue ] = \Nino\Design\Tokens::oklch( \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'harmony' => $position ], 'light' )['accent']['bg'] );
	$angles[$position] = round( fmod( rad2deg( $hue ) - rad2deg( \Nino\Design\Tokens::oklch( '#4faae8' )[2] ) + 360, 360 ) );
}

check( 'harmony places the second colour at the interval it names', $angles === [ 1 => 0.0, 2 => 30.0, 3 => 120.0, 4 => 180.0 ]
	|| print_r( $angles, true ) === '' );
$mono		= \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'harmony' => 1 ], 'light' );
$complement	= \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'harmony' => 4 ], 'light' );
check( '...monochrome is the brand itself, which is what no harmony meant before', $mono['accent']['bg'] === $mono['brand']['bg']
	&& $mono['accent-safe']['bg'] === $mono['brand-safe']['bg'] );
check( '...and it turns the second colour away from the brand at every other position', $mono['accent-safe']['bg'] !== $complement['accent-safe']['bg'] );
check( '...it never reaches the page ground or the status surfaces', $mono['default']['bg'] === $complement['default']['bg']
	&& $mono['alt']['bg'] === $complement['alt']['bg']
	&& $mono['danger']['bg'] === $complement['danger']['bg'] );
check( '...and a secondary colour of its own overrides it entirely', \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'secondary' => '#94703f', 'harmony' => 4 ], 'light' )['accent-safe']['bg']
	=== \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'secondary' => '#94703f', 'harmony' => 1 ], 'light' )['accent-safe']['bg'] );

/*	The defect the four roles exist for. With a Secondary set, the one safe
	surface the palette used to publish became the *second* colour made safe -
	so a theme's buttons quietly stopped being the brand, and there was no
	contrast-safe brand colour anywhere in the palette to put them back on	*/
$two = \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'secondary' => '#94703f' ], 'light' );
check( 'a second colour never takes the brand\'s place', $two['brand']['bg'] === '#4faae8'
	&& $two['accent']['bg'] === '#94703f'
	&& $two['brand-safe']['bg'] !== $two['accent-safe']['bg'] );
check( '...and both of them are safe at the same time', \Nino\Design\Tokens::contrast( $two['brand-safe']['on'], $two['brand-safe']['bg'] ) >= 4.48
	&& \Nino\Design\Tokens::contrast( $two['accent-safe']['on'], $two['accent-safe']['bg'] ) >= 4.48 );
// The safe brand follows the brand rather than whatever else is set: it is the
// hue and chroma that were picked, with only the lightness solved
check( '...the safe brand keeps the picked hue', abs( \Nino\Design\Tokens::oklch( $two['brand-safe']['bg'] )[2] - \Nino\Design\Tokens::oklch( '#4faae8' )[2] ) < 0.02 );

/*	The fifth ground: the page with the brand in it rather than a trace of it.
	Recognisably the brand rather than a warm grey, and still a surface body
	copy can sit on for a whole section	*/
$tinted = \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8' ], 'light' );
check( 'the tint ground carries the brand rather than a trace of it', \Nino\Design\Tokens::oklch( $tinted['tint']['bg'] )[1]
	> \Nino\Design\Tokens::oklch( $tinted['alt']['bg'] )[1] * 2 );
// Within a degree and a half, which is what fitting a requested chroma back
// into sRGB costs at a lightness this high
check( '...at the brand\'s own hue, not the one Temperature leans the greys to', abs( \Nino\Design\Tokens::oklch( $tinted['tint']['bg'] )[2] - \Nino\Design\Tokens::oklch( '#4faae8' )[2] ) < 0.03 );
// A brand with no colour in it has to produce a tint with no colour in it, or
// a grey corporate hex comes back as a beige band nobody asked for
check( '...and a colourless brand gets a colourless tint, not a beige one', \Nino\Design\Tokens::oklch( \Nino\Design\Tokens::palette( [ 'primary' => '#7f7f7f' ], 'light' )['tint']['bg'] )[1] < 0.002 );
check( '...and is a ground, not a brand surface: quiet enough to read a section on', \Nino\Design\Tokens::contrast( $tinted['tint']['bg'], $tinted['default']['bg'] ) < 1.35 );

// Depth: three ways of saying the same thing, moved by one control
$flat		= \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'depth' => 1 ], 'light' );
$layered	= \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'depth' => 3 ], 'light' );
check( 'depth separates the alternate surface from the page', \Nino\Design\Tokens::contrast( $layered['alt']['bg'], $layered['default']['bg'] )
	> \Nino\Design\Tokens::contrast( $flat['alt']['bg'], $flat['default']['bg'] ) );
check( '...draws the border more firmly with it', \Nino\Design\Tokens::contrast( $layered['default']['border'], $layered['default']['bg'] )
	> \Nino\Design\Tokens::contrast( $flat['default']['border'], $flat['default']['bg'] ) );
check( '...and casts a denser shadow', $flat['default']['shadow'] !== $layered['default']['shadow'] );
// A border identifies a control under SC 1.4.11 wherever Nino.css spends
// --color-border on one, and Nino.css spends it on form fields. Flat says what
// it has to say through the surface and the shadow, never by going below 3:1
check( '...but never lets the border fall below its 3:1 floor', \Nino\Design\Tokens::contrast( $flat['default']['border'], $flat['default']['bg'] ) >= 2.98
	&& \Nino\Design\Tokens::contrast( $flat['black']['border'], $flat['black']['bg'] ) >= 2.98 );

check( 'a secondary of its own reaches the accent surface', \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'secondary' => '#94703f' ], 'light' )['accent-safe']['bg']
	!== \Nino\Design\Tokens::palette( [ 'primary' => '#4faae8' ], 'light' )['accent-safe']['bg'] );

// Out-of-gamut OKLCH does not darken when it clips, it shifts hue - which is
// how a brand blue once came back as pale cyan
check( 'an out-of-gamut request is brought into sRGB instead of clipping to a different hue', \Nino\Design\Tokens::contrast( \Nino\Design\Tokens::hex( 0.6, 0.4, 4.2 ), '#ffffff' ) > 1.0
	&& preg_match( '/^#[0-9a-f]{6}$/', \Nino\Design\Tokens::hex( 0.6, 0.4, 4.2 ) ) === 1 );

check( 'a malformed brand falls back instead of emitting garbage', preg_match( '/^#[0-9a-f]{6}$/', \Nino\Design\Tokens::palette( [ 'primary' => 'not-a-colour' ], 'light' )['brand']['bg'] ) === 1 );
check( 'a three-digit hex is accepted', \Nino\Design\Tokens::palette( [ 'primary' => '#4ae' ], 'light' )['brand']['bg'] === \Nino\Design\Tokens::palette( [ 'primary' => '#44aaee' ], 'light' )['brand']['bg'] );

$designCss = \Nino\Design\Tokens::css( [ 'primary' => '#4faae8', 'secondary' => '#94703f' ] );
check( 'the stylesheet declares both modes and the three-state pattern', str_contains( $designCss, 'color-scheme: light dark' )
	&& str_contains( $designCss, '@media (prefers-color-scheme: dark)' )
	&& str_contains( $designCss, ':root:not([data-nino-mode="light"])' )
	&& str_contains( $designCss, ':root[data-nino-mode="dark"]' ) );
check( 'tokens name the surface after the on- prefix, modifiers last', str_contains( $designCss, '--nino-brand:' )
	&& str_contains( $designCss, '--nino-on-brand:' )
	&& str_contains( $designCss, '--nino-on-brand-muted:' )
	&& str_contains( $designCss, '--nino-brand-border:' ) );
// Every declaration has to be a six-digit hex or the one colour-scheme
// keyword. A NAN or an empty string out of the solver would otherwise ship as
// a variable nothing can resolve, and the page would fall back mid-cascade
preg_match_all( '/^\s*(--[a-z0-9-]+|color-scheme)\s*:\s*([^;]*);$/mi', $designCss, $designDeclarations, PREG_SET_ORDER );
$designMalformed = array_filter( $designDeclarations, static function( array $declaration ): bool {
	$value = trim( $declaration[2] );
	return match( true ) {
		$declaration[1] === 'color-scheme'	=> $value !== 'light dark',
		/*	The two names the palette published before the brand became four
			roles. Indirections rather than copies, so one line in the bare
			:root follows both dark blocks without being repeated in either -
			which is the whole reason they are var() and not a hex	*/
		(bool) preg_match( '/^--nino-(on-)?(origin|vibrant)/', $declaration[1] )	=> preg_match( '/^var\( *--nino-[a-z-]+ *\)$/', $value ) !== 1,
		// Shadows and the cover scrim are the non-hex colours: both carry an
		// alpha, which a solid colour cannot express
		str_ends_with( $declaration[1], '-shadow' )
		|| $declaration[1] === '--nino-scrim'	=> preg_match( '/^rgb\( *\d+ \d+ \d+ *\/ *\d+% *\)$/', $value ) !== 1,
		// The size raster - a length, or a bare ratio for line-height
		$declaration[1] === '--nino-line-height'	=> preg_match( '/^\d+(\.\d+)?$/', $value ) !== 1,
		// The root size is the one length that cannot be in rem: it is what
		// rem is measured against
		$declaration[1] === '--nino-base-size'	=> preg_match( '/^\d+(\.\d+)?px$/', $value ) !== 1,
		$declaration[1] === '--nino-measure'		=> preg_match( '/^\d+(\.\d+)?rem$/', $value ) !== 1,
		(bool) preg_match( '/^--nino-(text|space|radius)-/', $declaration[1] )	=> preg_match( '/^\d+(\.\d+)?rem$/', $value ) !== 1,
		default								=> preg_match( '/^#[0-9a-f]{6}$/', $value ) !== 1,
	};
} );
check( 'every declaration in the generated stylesheet is a resolvable value', $designMalformed === []
	// 12 surfaces x 10 values x 3 colour blocks, plus the one colour-scheme,
	// plus the two legacy aliases at 10 values each - once, not per mode -
	// plus the raster: the root size, line-height, the measure, 6 text, 6
	// space, 3 radius, the pill, and the wide-screen block's root size and 3
	// text steps. The raster is emitted once, not per mode either
	// ...plus the scrim, which is one value per colour block rather than one
	// per surface: a photograph is not a surface the palette can solve
	&& count( $designDeclarations ) === 12 * 10 * 3 + 3 + 1 + 20 + 23 );

/*	The scrim is the one ground solved against something the palette cannot
	see. The promise it makes is that the ink a theme puts on it stays
	readable over *any* photograph, so it is measured against the worst one
	there is - a white frame - with the ink at the weakest the framework ever
	paints it (.nino-section-subtitle carries opacity .8).

	Measured at every Contrast position and in both modes, since all three
	move it: the target, the ink and how deep this design's own black sits.	*/
$scrimOver = static function( string $hex, string $ground, float $alpha ): string {
	$out = '';
	foreach( [ 0, 2, 4 ] as $offset ) {
		$front = hexdec( substr( ltrim( $hex, '#' ), $offset, 2 ) );
		$back  = hexdec( substr( ltrim( $ground, '#' ), $offset, 2 ) );
		$out .= sprintf( '%02x', (int) round( $front * $alpha + $back * ( 1 - $alpha ) ) );
	}
	return '#'. $out;
};
$scrimFailures = [];
$scrimAlphas   = [];

foreach( [ 1, 2, 3 ] as $contrastStep ) {

	$scrimSettings = \Nino\Design\Tokens::normalize( [ 'primary' => '#4faae8', 'contrast' => $contrastStep ] );
	$scrimTarget   = [ 1 => 4.5, 2 => 4.5, 3 => 7.0 ][$contrastStep];
	// The bare :root carries light, both dark blocks carry the same dark value
	$scrimBlocks   = preg_split( '/@media \(prefers-color-scheme: dark\)/', \Nino\Design\Tokens::css( $scrimSettings ) );

	foreach( [ 'light' => 0, 'dark' => 1 ] as $scrimMode => $scrimBlock ) {

		if( preg_match( '/--nino-scrim: rgb\((\d+) (\d+) (\d+) \/ (\d+)%\)/', $scrimBlocks[$scrimBlock], $scrimMatch ) !== 1 ) {
			$scrimFailures[] = $scrimMode. '/'. $contrastStep. ': no scrim published';
			continue;
		}

		$scrimPalette = \Nino\Design\Tokens::palette( $scrimSettings, $scrimMode );
		$scrimColour  = sprintf( '#%02x%02x%02x', (int) $scrimMatch[1], (int) $scrimMatch[2], (int) $scrimMatch[3] );
		$scrimAlphas[] = (int) $scrimMatch[4];

		// It is the design's own deepest surface, not a black invented for it -
		// a warm page has to dim warm or the scrim reads as a lid dropped on it
		if( $scrimColour !== $scrimPalette['black']['bg'] )
			$scrimFailures[] = $scrimMode. '/'. $contrastStep. ': '. $scrimColour. ' is not the deepest surface '. $scrimPalette['black']['bg'];

		$scrimGround = $scrimOver( $scrimColour, '#ffffff', (int) $scrimMatch[4] / 100 );
		$scrimRatio  = \Nino\Design\Tokens::contrast( $scrimOver( $scrimPalette['black']['on'], $scrimGround, 0.8 ), $scrimGround );

		if( $scrimRatio < $scrimTarget - 0.02 )
			$scrimFailures[] = sprintf( '%s/%d: %.2f:1 over a white photograph, needs %.1f', $scrimMode, $contrastStep, $scrimRatio, $scrimTarget );
	}
}

check( 'the cover scrim carries its ink over the worst photograph it could be handed', $scrimFailures === [] );
// The point of solving it rather than tabling it: a scrim that came back the
// same at every setting would be the hand-written 78% the themes used to carry
check( '...at an alpha the settings actually move', count( array_unique( $scrimAlphas ) ) >= 4
	&& min( $scrimAlphas ) < max( $scrimAlphas ) );

echo "\n";



// --- the surfaces and values the extended vocabulary adds ---------------

echo "Design - surfaces, states and status\n";

$vocabulary = \Nino\Design\Tokens::palette( \Nino\Design\Tokens::normalize( [ 'primary' => '#4faae8' ] ), 'light' );

check( 'status surfaces come out of the same machinery as the brand ones', array_keys( $vocabulary ) === [ 'default', 'alt', 'tint', 'dark', 'black', 'brand', 'brand-safe', 'accent', 'accent-safe', 'success', 'warning', 'danger' ] );
check( 'every surface carries the full state set', array_keys( $vocabulary['default'] ) === [ 'bg', 'on', 'on-muted', 'link', 'border', 'focus', 'hover', 'active', 'disabled', 'shadow' ] );

// A link solved against the page ground and then dropped onto a dark section
// is the failure this value exists to prevent
check( 'a link is solved per surface, not once for the whole page', $vocabulary['default']['link'] !== $vocabulary['dark']['link']
	&& \Nino\Design\Tokens::contrast( $vocabulary['dark']['link'], $vocabulary['dark']['bg'] ) >= 4.48
	&& \Nino\Design\Tokens::contrast( $vocabulary['black']['link'], $vocabulary['black']['bg'] ) >= 4.48 );

// SC 1.4.11 - and the defect the audit found in Nino.css, where the ring is
// removed and replaced by a 1.87:1 border change
check( 'the focus ring clears 3:1 on every surface, in both modes', array_filter(
	array_merge(
		\Nino\Design\Tokens::palette( \Nino\Design\Tokens::normalize( [ 'primary' => '#4faae8' ] ), 'light' ),
		\Nino\Design\Tokens::palette( \Nino\Design\Tokens::normalize( [ 'primary' => '#4faae8' ] ), 'dark' )
	),
	static fn( array $values ): bool => \Nino\Design\Tokens::contrast( $values['focus'], $values['bg'] ) < 2.98
) === [] );

check( 'active is a visible step past hover, not the same nudge twice', $vocabulary['default']['hover'] !== $vocabulary['default']['active']
	&& \Nino\Design\Tokens::contrast( $vocabulary['default']['active'], $vocabulary['default']['bg'] )
		> \Nino\Design\Tokens::contrast( $vocabulary['default']['hover'], $vocabulary['default']['bg'] ) );

// Disabled is the one value that must NOT meet the text target - it has to
// read as unavailable rather than merely quiet
$disabledRatio = \Nino\Design\Tokens::contrast( $vocabulary['default']['disabled'], $vocabulary['default']['bg'] );
check( 'disabled sits below the text target on purpose, but stays perceivable', $disabledRatio < 4.5 && $disabledRatio > 1.9 );

check( 'the dark palette casts its own shadow instead of reusing the light one', $vocabulary['default']['shadow']
	!== \Nino\Design\Tokens::palette( \Nino\Design\Tokens::normalize( [ 'primary' => '#4faae8' ] ), 'dark' )['default']['shadow'] );

// Red has to stay red: the brand knobs must not be able to turn a danger
// surface into something reassuring
$brandA = \Nino\Design\Tokens::palette( \Nino\Design\Tokens::normalize( [ 'primary' => '#4faae8' ] ), 'light' );
$brandB = \Nino\Design\Tokens::palette( \Nino\Design\Tokens::normalize( [ 'primary' => '#2f6d4f', 'colors' => 'vibrant' ] ), 'light' );
check( 'status hues are fixed and survive a change of brand', $brandA['danger']['bg'] === $brandB['danger']['bg']
	&& $brandA['success']['bg'] === $brandB['success']['bg'] );

echo "\n";


// --- raster - the size half -------------------------------------------

echo "Design::raster\n";

$rem = static fn( string $value ): float => (float) rtrim( $value, 'rem' );

// The one property that makes the layer adoptable: turning it on without
// asking for anything must not move a project. These are Nino.css's own
// values, and they are here rather than derived so a drift in either shows up
$defaultRaster = \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [] ) );

check( 'the default raster is Nino.css\'s own scale, so switching the layer on moves nothing', array_values( $defaultRaster['text'] ) === [ '1rem', '1.125rem', '1.25rem', '1.5rem', '1.8rem', '2.5rem' ]
	&& array_values( $defaultRaster['space'] ) === [ '0.5rem', '1rem', '2rem', '3rem', '5rem', '8rem' ]
	&& array_values( $defaultRaster['textWide'] ) === [ '1.6rem', '2rem', '3rem' ]
	&& $defaultRaster['lineHeight'] === '1.5' );

/*	Every setting, not just the ones a picker offers in the middle. Five knobs
	at three positions is 243 rasters, which is worth walking in full: these
	are pure arithmetic, and the property being checked - that no combination
	produces a scale that runs backwards or type below the base size - is
	exactly the kind that only breaks at a corner.	*/
$rasterSettings = [];
foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $scale )
	foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $volume )
		foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $spacing )
			foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $shaping )
				foreach( range( 1, \Nino\Design\Tokens::STEPS ) as $measure )
					$rasterSettings[] = [ 'scale' => $scale, 'volume' => $volume, 'spacing' => $spacing, 'shaping' => $shaping, 'measure' => $measure ];

$notMonotonic	= [];
$bodyTooSmall	= [];
$wideNotBigger	= [];
$rootTooSmall	= [];

foreach( $rasterSettings as $settings ) {

	$raster = \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( $settings ) );
	$label	= implode( '/', $settings );

	foreach( [ 'text', 'space' ] as $group ) {

		$values = array_values( $raster[$group] );

		for( $step = 1; $step < count( $values ); $step++ )
			if( $rem( $values[$step] ) <= $rem( $values[$step - 1] ) )
				$notMonotonic[] = $label. ' '. $group. ' step '. ( $step + 1 );
	}

	/*	Radius is the one scale allowed to stand still. Square is a real
		answer - a design with no rounding has three radii of 0 and they are
		not "wrong" for being equal - so the rule here is that it never runs
		backwards, not that it always climbs.	*/
	$radii = array_values( $raster['radius'] );

	for( $step = 1; $step < count( $radii ); $step++ )
		if( $rem( $radii[$step] ) < $rem( $radii[$step - 1] ) )
			$notMonotonic[] = $label. ' radius step '. ( $step + 1 );

	// Body copy is the size that is already right. A knob may fan the display
	// end in either direction, but it must never make reading harder
	if( $rem( $raster['text'][1] ) < 1.0 )
		$bodyTooSmall[] = $label. ': '. $raster['text'][1];

	// Scale is the one knob that can put body copy below 1rem in absolute
	// terms, since it moves what a rem is. 14px is the floor, and that is the
	// size the smallest position names
	if( (float) rtrim( $raster['baseSize'], 'px' ) < 14.0 )
		$rootTooSmall[] = $label. ': '. $raster['baseSize'];

	// The wide-screen block only ever overrides upwards - a step that came out
	// smaller on a big screen would read as a broken media query
	foreach( $raster['textWide'] as $step => $value )
		if( $rem( $value ) <= $rem( $raster['text'][$step] ) )
			$wideNotBigger[] = $label. ' text-'. $step;

	if( (float) rtrim( $raster['baseSizeWide'], 'px' ) < (float) rtrim( $raster['baseSize'], 'px' ) )
		$wideNotBigger[] = $label. ' base-size';
}

check( 'every scale is monotonic at every setting'. ( $notMonotonic === [] ? '' : ' - '. implode( ', ', array_slice( $notMonotonic, 0, 4 ) ) ), $notMonotonic === [] );
check( 'body copy never drops below the base size, whatever the volume'. ( $bodyTooSmall === [] ? '' : ' - '. implode( ', ', $bodyTooSmall ) ), $bodyTooSmall === [] );
check( 'the root size never drops below 14px, whatever the scale'. ( $rootTooSmall === [] ? '' : ' - '. implode( ', ', $rootTooSmall ) ), $rootTooSmall === [] );
check( 'the wide-screen steps only ever override upwards'. ( $wideNotBigger === [] ? '' : ' - '. implode( ', ', $wideNotBigger ) ), $wideNotBigger === [] );
check( 'all 243 combinations produce a complete raster', count( $rasterSettings ) === 243 );

// The knobs have to be separable, or a picker cannot explain them
$scaleOnly		= \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'scale' => 3 ] ) );
$volumeOnly		= \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'volume' => 3 ] ) );
$spacingOnly	= \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'spacing' => 3 ] ) );
$shapingOnly	= \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'shaping' => 3 ] ) );
$measureOnly	= \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'measure' => 3 ] ) );

check( 'Volume moves type and leaves spacing and radius alone', $volumeOnly['text'] !== $defaultRaster['text']
	&& $volumeOnly['space'] === $defaultRaster['space']
	&& $volumeOnly['radius'] === $defaultRaster['radius'] );
check( 'Spacing moves the gaps and the line height, not the type sizes', $spacingOnly['space'] !== $defaultRaster['space']
	&& $spacingOnly['lineHeight'] !== $defaultRaster['lineHeight']
	&& $spacingOnly['text'] === $defaultRaster['text'] );
check( 'Shaping moves the radii and nothing else', $shapingOnly['radius'] !== $defaultRaster['radius']
	&& $shapingOnly['text'] === $defaultRaster['text']
	&& $shapingOnly['space'] === $defaultRaster['space'] );

/*	Scale is the exception to that rule and has to be: it moves the root size
	rather than a step, so every rem above it changes meaning while the numbers
	stay put. That is the point of it - the proportions the other knobs set
	survive intact.	*/
check( 'Scale moves the root size and leaves the rem steps exactly where they are', $scaleOnly['baseSize'] !== $defaultRaster['baseSize']
	&& $scaleOnly['text'] === $defaultRaster['text']
	&& $scaleOnly['space'] === $defaultRaster['space']
	&& $scaleOnly['radius'] === $defaultRaster['radius'] );
check( 'Measure moves the layout ceiling and nothing else', $measureOnly['measure'] !== $defaultRaster['measure']
	&& $measureOnly['text'] === $defaultRaster['text']
	&& $measureOnly['space'] === $defaultRaster['space']
	&& $measureOnly['baseSize'] === $defaultRaster['baseSize'] );

// Volume is anchored at step 1: a scale grows by fanning out at the display
// end, not by pushing body copy up with it
check( 'Volume is anchored at body copy and fans out toward the display end', $volumeOnly['text'][1] === $defaultRaster['text'][1]
	&& $rem( $volumeOnly['text'][6] ) > $rem( $defaultRaster['text'][6] ) * 1.2 );

// A size has no light and dark variant, so it is emitted once
$rasterCss = \Nino\Design\Tokens::css( \Nino\Design\Tokens::normalize( [ 'shaping' => 3 ] ) );
check( 'the raster is emitted once, not repeated per colour mode', substr_count( $rasterCss, '--nino-space-3:' ) === 1
	&& substr_count( $rasterCss, '--nino-radius-2:' ) === 1
	&& substr_count( $rasterCss, '--nino-measure:' ) === 1
	&& substr_count( $rasterCss, '--nino-line-height:' ) === 1 );
check( '...and only its wide-screen half gets a media query of its own', substr_count( $rasterCss, '@media (min-width: 768px)' ) === 1
	&& substr_count( $rasterCss, '--nino-text-6:' ) === 2
	// The root size is the one raster value the wide block restates, and it
	// has to come first: every rem below resolves against it
	&& substr_count( $rasterCss, '--nino-base-size:' ) === 2 );
check( 'a pill is the same answer at every shaping setting', str_contains( $rasterCss, '--nino-radius-full: 999rem;' )
	&& str_contains( \Nino\Design\Tokens::css( \Nino\Design\Tokens::normalize( [ 'shaping' => 1 ] ) ), '--nino-radius-full: 999rem;' ) );

/*	Where a knob replaces one that shipped with three named values, its three
	positions are those exact values - so a theme drawn against the old
	vocabulary renders identically under the new one. Pinned here rather than
	described, since it is the whole reason the catalogue did not have to be
	redrawn.	*/
check( 'the outer positions are the values the old vocabulary named', array_values( \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'shaping' => 1 ] ) )['radius'] ) === [ '0.05rem', '0.1rem', '0.2rem' ]
	&& array_values( \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'shaping' => 3 ] ) )['radius'] ) === [ '0.5rem', '1.5rem', '2.5rem' ]
	&& \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'spacing' => 1 ] ) )['lineHeight'] === '1.45'
	&& \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'spacing' => 3 ] ) )['lineHeight'] === '1.65' );

// Same rule as the colours: nothing off the wire is trusted
check( 'an unknown size setting falls back rather than reaching the raster', \Nino\Design\Tokens::raster( \Nino\Design\Tokens::normalize( [ 'volume' => 'enormous', 'spacing' => [], 'shaping' => '../../etc', 'scale' => 99, 'measure' => -3 ] ) ) === $defaultRaster );

echo "\n";


// --- normalize - nothing posted is trusted ------------------------------

echo "Design::normalize\n";

check( 'a complete set comes back from nothing at all', array_keys( \Nino\Design\Tokens::normalize( [] ) )
	=== [ 'primary', 'secondary', 'harmony', 'temperature', 'saturation', 'contrast', 'depth', 'scale', 'volume', 'spacing', 'shaping', 'measure' ] );
check( 'an unknown knob position falls back rather than reaching the generator', \Nino\Design\Tokens::normalize( [ 'contrast' => 'enormous', 'saturation' => '../etc', 'depth' => [ 'x' ], 'scale' => 9, 'measure' => 0 ] )
	=== \Nino\Design\Tokens::normalize( [] ) );
// A number off a form post arrives as a string, and a step is still a step
check( 'a numeric string is a position, not a name that failed to match', \Nino\Design\Tokens::normalize( [ 'contrast' => '3' ] )['contrast'] === 3 );

/*	A stored config outlives a release. Every one of these was a valid setting
	before the knobs were numbered, and a project that never opens /_design
	again has to keep the look it saved - which is also why 'colors' still
	resolves after being renamed to 'saturation'.	*/
$legacy = \Nino\Design\Tokens::normalize( [ 'contrast' => 'high', 'colors' => 'clean', 'volume' => 'generous', 'spacing' => 'airy', 'shaping' => 'round' ] );
check( 'the old vocabulary still resolves to the position it described', $legacy['contrast'] === 3
	&& $legacy['saturation'] === 1
	&& $legacy['volume'] === 3
	&& $legacy['spacing'] === 3
	&& $legacy['shaping'] === 3 );
// 'soft' held muted text at 3.0:1, which is below AA for body copy. It lands
// on the nearest position that still passes rather than the one it named
check( '...and the one position that was below AA keeps its ink but not its sub-AA muted floor', \Nino\Design\Tokens::normalize( [ 'contrast' => 'soft' ] )['contrast'] === 1
	&& \Nino\Design\Tokens::contrast(
		\Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'contrast' => 1 ], 'light' )['default']['on-muted'],
		\Nino\Design\Tokens::palette( [ 'primary' => '#4faae8', 'contrast' => 1 ], 'light' )['default']['bg'] ) >= 4.48 );
check( 'the knobs a picker renders all carry a group, a note and a name per position', ( static function(): bool {
	foreach( \Nino\Design\Tokens::choices() as $knob => $meta )
		if( count( $meta['steps'] ) !== $meta['max'] || $meta['min'] !== 1 || ( $meta['note'] ?? '' ) === ''
			|| isset( \Nino\Design\Tokens::GROUPS[$meta['group']] ) === false
			|| $meta['default'] < $meta['min'] || $meta['default'] > $meta['max'] )
			return false;
	return true;
} )() );
/*	Not every decision is a scale, and the ones that are not say so. A scale is
	three positions with the framework's own in the middle; a choice is a named
	list with its own default - which is what Harmony and Temperature always
	were while the pane told the operator the middle position is Nino's own	*/
check( 'a scale is three positions and a choice is as many as it names', ( static function(): bool {
	foreach( \Nino\Design\Tokens::choices() as $knob => $meta ) {
		if( in_array( $meta['kind'], [ 'scale', 'choice' ], true ) === false )
			return false;
		if( $meta['kind'] === 'scale' && ( $meta['max'] !== \Nino\Design\Tokens::STEPS || $meta['default'] !== 2 ) )
			return false;
	}
	return true;
} )() );
check( '...and a position past the end of a choice falls back rather than indexing off it', \Nino\Design\Tokens::normalize( [ 'harmony' => 5 ] )['harmony'] === 1
	&& \Nino\Design\Tokens::normalize( [ 'temperature' => 9 ] )['temperature'] === 3
	&& \Nino\Design\Tokens::normalize( [ 'harmony' => 4 ] )['harmony'] === 4 );
check( 'a colour without its hash is still accepted', \Nino\Design\Tokens::normalize( [ 'primary' => '4faae8' ] )['primary'] === '#4faae8' );
check( 'a three-digit colour is expanded once, here', \Nino\Design\Tokens::normalize( [ 'primary' => '#4AE' ] )['primary'] === '#44aaee' );
check( 'a value that is not a colour is refused, not silently carried', \Nino\Design\Tokens::normalize( [ 'primary' => 'url(evil)' ] )['primary'] === '#4faae8' );
check( 'an empty secondary stays empty - it means "use the brand", not "fall back"', \Nino\Design\Tokens::normalize( [] )['secondary'] === '' );

echo "\n";


// --- Preview - the page a picker judges the settings on -----------------

echo "Design::Preview\n";

$appData['/nino/html/assets'] = [ '/.cache/style.css' => [
	'/_nino/Nino.css', '/assets/style.design.css', '/assets/style.theme.demo.css', '/assets/style.header.css',
] ];
\Nino\Filesystem::putFileContent( $appData, '/assets/style.theme.demo.css', ':root{--color-primary:var(--nino-brand-safe)}.demo-theme-marker{color:red}' );
\Nino\Filesystem::putFileContent( $appData, '/assets/style.header.css', '.demo-frame-marker{display:block}' );

$exampleSettings = \Nino\Design\Tokens::normalize( [ 'primary' => '#c81e2d', 'shaping' => 3 ] );
$example = \Nino\Design\Preview::document( $appData, $exampleSettings, 'light' );

check( 'the preview is a complete document, since it is delivered into a sandboxed frame', str_starts_with( $example, '<!doctype html>' )
	&& str_contains( $example, '</head><body>' )
	&& str_ends_with( $example, '</body></html>' ) );

// The whole reason it is a page: a design decision cannot be judged one token
// at a time, so everything the knobs move has to be on it at once
check( 'it exercises what the knobs actually change', str_contains( $example, 'nino-section--alt' )
	&& str_contains( $example, 'nino-section--dark' )
	&& str_contains( $example, 'nino-section--black' )
	&& str_contains( $example, 'nino-btn--primary' )
	&& str_contains( $example, 'nino-btn--outline' )
	&& str_contains( $example, 'nino-form-input' )
	&& str_contains( $example, 'nino-alert--warning' )
	&& str_contains( $example, 'nino-alert--error' ) );

// Framework first, generated tokens second, the project's own third - the same
// order Design::bundle() writes into a real project
$frameworkAt = strpos( $example, '.nino-section {' );
$generatedAt = strpos( $example, '--nino-brand:' );
$themeAt 		= strpos( $example, '.demo-theme-marker' );
$frameAt 		= strpos( $example, '.demo-frame-marker' );

check( 'it loads what a real page loads, in the order a real page loads it', $frameworkAt !== false
	&& $generatedAt !== false && $themeAt !== false && $frameAt !== false
	&& $frameworkAt < $generatedAt && $generatedAt < $themeAt && $themeAt < $frameAt );

// The settings being previewed, not the ones on disk - taking the saved copy
// would show the operator the design they are trying to change
check( 'the tokens are the settings it was handed', str_contains( $example, '--nino-brand: #c81e2d;' )
	&& str_contains( $example, '--nino-radius-3: 2.5rem;' ) );

/*	The example is an ordinary page or it is not evidence. Every class on it
	has to be one Nino.css defines - the moment the preview needs a rule of
	its own it has stopped showing what a project gets and started showing
	what /_design can draw, and a component the framework is missing gets
	hidden behind a local copy instead of being noticed.	*/
$exampleMarkup = (string) file_get_contents( __DIR__. '/../_design/templates/preview-example.tpl' );
$frameworkCss  = (string) file_get_contents( __DIR__. '/../_nino/Nino.css' );

preg_match_all( '/class="([^"]+)"/', $exampleMarkup, $exampleClasses );
$ownClasses = [];

foreach( $exampleClasses[1] as $attribute )
	foreach( preg_split( '/\s+/', trim( $attribute ) ) as $class )
		if( $class !== '' && preg_match( '/(^|[^a-z0-9-])\.'. preg_quote( $class, '/' ). '([^a-z0-9-]|$)/', $frameworkCss ) !== 1 )
			$ownClasses[] = $class;

check( 'the example is written in the design system, with no class of its own'. ( $ownClasses === [] ? '' : ' - '. implode( ', ', array_unique( $ownClasses ) ) ), $ownClasses === [] );

check( 'no unresolved fill is left in a value', str_contains( $example, '[[' ) === false );

/*	The example is a page, not a strip of specimens - a bar, a hero, cards, a
	split band and a footer, in the markup a real one uses. The bar and the
	footer are the framework's own <header> and <footer>, which is what lets
	them be here without a frame unit; the two rules every frame in the library
	does carry - the padding that keeps a fixed bar off the first heading, and
	the row its nav links sit in - come from the scaffold, so no length in the
	markup is one this preview invented.	*/
foreach( [ 'nino-atf-title', 'nino-article', 'nino-section--dark', 'nino-alert--error' ] as $part )
	check( 'the example is a page, not a specimen strip: '. $part, str_contains( $example, $part ) );

/*	A delivery may ship without /_install, and then there are no frame units to
	have. The example stands on its own, and the two rules a header unit would
	have brought - the padding that keeps a fixed bar off the first heading, and
	the row its nav links sit in - come from the scaffold instead.	*/
check( 'without /_install the example stands on its own, and the scaffold stands in', str_contains( $example, 'body main{padding-top:' )
	&& str_contains( $example, 'header .nino-nav-content ul{display:flex' ) );

// A srcdoc frame fetches nothing, so the photographs travel as data - and a
// grey placeholder is honest about being one
preg_match_all( '~<img\b[^>]*\ssrc="([^"]*)"~i', $example, $exampleImages );
check( '...and every image in it is carried, not requested', $exampleImages[1] !== []
	&& array_filter( $exampleImages[1], static fn( string $src ): bool => str_starts_with( $src, 'data:image/' ) === false ) === [] );

/*	Typography is the one part of a theme the preview does not show. A srcdoc
	iframe has an opaque origin, so every @font-face in it is a request that
	cannot be made - and a family that resolves to nothing puts the whole page
	in the browser's serif default, which is a worse lie than a system stack.
	The rules go, and the scaffold names faces the frame can actually resolve.	*/
\Nino\Filesystem::putFileContent( $appData, '/fonts/demo.woff2', 'not really a font, but a file that exists' );
\Nino\Filesystem::putFileContent( $appData, '/assets/style.theme.demo.css',
	'@font-face{font-family:\'Demo\';src:local(\'Demo\'),url(\'[[/nino/public]]/fonts/demo.woff2\') format(\'woff2\')}'
	. ':root{--color-primary:var(--nino-brand-safe)}.demo-theme-marker{color:red}' );

$withFonts = \Nino\Design\Preview::document( $appData, $exampleSettings, 'light' );

check( 'a face the frame could never fetch is dropped rather than left to fail', str_contains( $withFonts, '@font-face' ) === false
	&& str_contains( $withFonts, 'demo.woff2' ) === false );
check( '...and the scaffold names one it can resolve instead', str_contains( $withFonts, '--fontfamily-text:system-ui' ) );
// Nothing off the disk travels into the document either: the file above exists
// and is still not in it
check( '...without carrying the file into the document', str_contains( $withFonts, 'data:font/' ) === false
	&& str_contains( $withFonts, 'not really a font' ) === false );

// A url with a scheme or a traversal in it is not a file this project ships,
// and goes the same way as the rest
\Nino\Filesystem::putFileContent( $appData, '/assets/style.theme.demo.css',
	'@font-face{font-family:\'Remote\';src:url(\'https://example.com/x.woff2\')}'
	. '@font-face{font-family:\'Up\';src:url(\'[[/nino/public]]/../../etc/passwd\')}'
	. ':root{--color-primary:var(--nino-brand-safe)}.demo-theme-marker{color:red}' );

$foreign = \Nino\Design\Preview::document( $appData, $exampleSettings, 'light' );

check( 'a foreign url never reaches the document', str_contains( $foreign, '@font-face' ) === false
	&& str_contains( $foreign, 'example.com' ) === false
	&& str_contains( $foreign, 'passwd' ) === false );

@unlink( (string) \Nino\Filesystem::path( $appData, '/fonts/demo.woff2' ) );

// ...and back to the stylesheet the ordering check above reads
\Nino\Filesystem::putFileContent( $appData, '/assets/style.theme.demo.css', ':root{--color-primary:var(--nino-brand-safe)}.demo-theme-marker{color:red}' );

$dark = \Nino\Design\Preview::document( $appData, $exampleSettings, 'dark' );
check( 'the mode is stamped on the document, since nothing can reach into the frame to do it', str_contains( $example, 'data-nino-mode="light"' )
	&& str_contains( $dark, 'data-nino-mode="dark"' )
	&& $dark !== $example );
// Anything but 'dark' is light, same rule the rest of the class follows
check( 'an unknown mode is light rather than a third state', \Nino\Design\Preview::document( $appData, $exampleSettings, '../etc' ) === $example );

echo "\n";
// --- Theme::write - the stylesheet and its place in the bundle ----------

echo "Theme::write / bundle\n";

$appData['/nino/html/assets'] = [ '/.cache/style.css' => [ '/_nino/Nino.css', '/assets/style.theme.basis.css', '/assets/style.custom.css' ] ];

check( 'writing succeeds', \Nino\Design\Design::write( $appData, \Nino\Design\Tokens::normalize( [ 'primary' => '#14595e' ] ) ) === true );
check( 'the stylesheet lands where a project file belongs', \Nino\Filesystem::fileExists( $appData, '/assets/style.design.css' ) === true );

$written = (string) \Nino\Filesystem::getFileContent( $appData, '/assets/style.design.css', '' );
check( 'it carries the generated palette', str_contains( $written, '--nino-brand:' ) && str_contains( $written, '--nino-on-danger:' ) );
check( 'it says plainly that it is generated and will be overwritten', str_contains( $written, 'Generated by /_design' ) && str_contains( $written, 'Do not edit' ) );

$bundle = $appData['/nino/html/assets']['/.cache/style.css'];
check( 'it sits directly behind the framework stylesheet, ahead of everything that reads it', $bundle === [
	'/_nino/Nino.css', '/assets/style.design.css', '/assets/style.theme.basis.css', '/assets/style.custom.css' ] );

// A header/footer variant, a second theme, a project stylesheet - the bundle
// grows, and the generated file has to keep its place without being counted in
$appData['/nino/html/assets']['/.cache/style.css'] = [
	'/_nino/Nino.css', '/assets/style.design.css', '/assets/style.header.v2.css', '/assets/style.theme.basis.css' ];
\Nino\Design\Design::write( $appData, \Nino\Design\Tokens::normalize( [ 'primary' => '#8f2d56' ] ) );
check( 'a second write does not add a second entry', $appData['/nino/html/assets']['/.cache/style.css'] === [
	'/_nino/Nino.css', '/assets/style.design.css', '/assets/style.header.v2.css', '/assets/style.theme.basis.css' ] );

// The whole point of the tool: the values stay editable after the install
$reloaded = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the settings are persisted, so they can be reopened and changed later', ( $reloaded['/nino/theme/design']['primary'] ?? '' ) === '#8f2d56' );
check( 'and the bundle entry is persisted with them', in_array( '/assets/style.design.css', $reloaded['/nino/html/assets']['/.cache/style.css'] ?? [], true ) === true );

// Written twice: the file has to be replaced, not grown, and has to carry the
// second set of settings rather than both
$firstWrite = (string) \Nino\Filesystem::getFileContent( $appData, '/assets/style.design.css', '' );
\Nino\Design\Design::write( $appData, \Nino\Design\Tokens::normalize( [ 'primary' => '#14595e' ] ) );
$secondWrite = (string) \Nino\Filesystem::getFileContent( $appData, '/assets/style.design.css', '' );
// Line for line rather than byte for byte: the scrim's alpha is a percent and
// its colour three decimal channels, so two different brands legitimately
// write two different lengths of the same stylesheet
check( 'rewriting regenerates rather than appending', substr_count( $firstWrite, "\n" ) === substr_count( $secondWrite, "\n" )
	&& $firstWrite !== $secondWrite
	&& substr_count( $secondWrite, '/* Generated by /_design' ) === 1
	&& substr_count( $secondWrite, '--nino-space-1:' ) === 1 );

// A hand-emptied bundle still has to end up with the generated file first,
// since everything else reads from it
$emptyBundle = $appData;
$emptyBundle['/nino/html/assets'] = [ '/.cache/style.css' => [ '/assets/style.custom.css' ] ];
check( 'with no framework stylesheet in the bundle it still leads', \Nino\Design\Design::bundle( $emptyBundle )['/.cache/style.css']
	=== [ '/assets/style.design.css', '/assets/style.custom.css' ] );

echo "\n";


// --- the tool's own surface --------------------------------------------

echo "Theme::init / guard\n";

check( 'the post-install tool owns appearance changes without loading the removable installer',
	class_exists( '\\Nino\\Install\\Themes', false ) === false
	&& is_dir( __DIR__. '/../_install/library/themes' ) === true );

$routes = $appData;
\Nino\Design\Design::init( $routes );
check( 'the tool owns its routes', ( $routes['/nino/http/routes']['GET://_design']['uri'] ?? '' ) === '/_design'
	&& ( $routes['/nino/http/routes']['POST://_design']['uri'] ?? '' ) === '/_design' );

$authenticatedPage = (string) file_get_contents( __DIR__. '/../_design/templates/page-index.tpl' );
check( 'the authenticated page carries the CSRF field its POST API needs', substr_count( $authenticatedPage, '[csrf]' ) === 1 );

$anonymous = response();
check( 'guard rejects an unauthenticated request', \Nino\Design\Design::guard( $appData, $anonymous ) === false
	&& $anonymous['/nino/http/response']['statusCode'] === 401 );

foreach( [ 'appearance/read', 'theme/apply', 'frame/preview', 'frame/apply', 'design/read', 'design/preview', 'design/save' ] as $action ) {
	$request = response();
	$_POST = [ 'action' => $action, 'data' => '{}' ];
	\Nino\Design\Design::handlePost( $appData, $request );
	check( $action. ' requires an authed session of its own', $request['/nino/http/response']['statusCode'] === 401 );
}

\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

$appearance = response();
$_POST = [ 'action' => 'appearance/read', 'data' => '{}' ];
\Nino\Design\Design::handlePost( $appData, $appearance );
$appearanceBody = $appearance['/nino/http/response']['body'] ?? [];

$badTheme = response();
$_POST = [ 'action' => 'theme/apply', 'data' => json_encode( [ 'theme' => '../basis' ] ) ];
\Nino\Design\Design::handlePost( $appData, $badTheme );
check( 'a Theme key cannot leave the shared catalogue', $badTheme['/nino/http/response']['statusCode'] === 400 );

$badFrame = response();
$_POST = [ 'action' => 'frame/apply', 'data' => json_encode( [ 'kind' => 'header', 'frame' => '../v1' ] ) ];
\Nino\Design\Design::handlePost( $appData, $badFrame );
check( 'a frame key cannot leave its kind catalogue', $badFrame['/nino/http/response']['statusCode'] === 400 );

$themeApply = response();
$_POST = [ 'action' => 'theme/apply', 'data' => json_encode( [ 'theme' => 'basis' ] ) ];
\Nino\Design\Design::handlePost( $appData, $themeApply );
$afterTheme = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'Theme applies the manifest as one complete baseline', $themeApply['/nino/http/response']['statusCode'] === 200
	&& ( $afterTheme['/nino/install/theme'] ?? '' ) === 'basis'
	&& ( $afterTheme['/nino/install/header'] ?? '' ) === 'v1'
	&& ( $afterTheme['/nino/install/footer'] ?? '' ) === 'v1'
	&& ( $afterTheme['/nino/theme/design']['primary'] ?? '' ) === '#4faae8' );

$designSave = response();
$_POST = [ 'action' => 'design/save', 'data' => json_encode( [ 'primary' => '#14595e', 'spacing' => 1, 'measure' => 3 ] ) ];
\Nino\Design\Design::handlePost( $appData, $designSave );
$afterDesign = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'Design changes only its generated values, not Theme or either frame', ( $afterDesign['/nino/theme/design']['primary'] ?? '' ) === '#14595e'
	&& ( $afterDesign['/nino/theme/design']['spacing'] ?? '' ) === 1
	&& ( $afterDesign['/nino/theme/design']['measure'] ?? '' ) === 3
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
\Nino\Design\Design::handlePost( $appData, $framePreview );

$frameApply = response();
$_POST = [ 'action' => 'frame/apply', 'data' => json_encode( [ 'kind' => 'header', 'frame' => 'v3' ] ) ];
\Nino\Design\Design::handlePost( $appData, $frameApply );
$afterFrame = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

check( 'a Header apply changes only Header and preserves the settled Theme, Design and Footer',
	( $afterFrame['/nino/install/header'] ?? '' ) === 'v3'
	&& ( $afterFrame['/nino/install/footer'] ?? '' ) === 'v1'
	&& ( $afterFrame['/nino/install/theme'] ?? '' ) === 'basis'
	&& ( $afterFrame['/nino/theme/design'] ?? [] ) === ( $afterDesign['/nino/theme/design'] ?? [] ) );

$unknown = response();
$_POST = [ 'action' => 'design/../../etc', 'data' => '{}' ];
\Nino\Design\Design::handlePost( $appData, $unknown );
check( 'an unknown action is refused before anything else happens', $unknown['/nino/http/response']['statusCode'] === 400 );

echo "\n". $checks. ' checks, '. $failures. " failed\n";

exit( $failures === 0 ? 0 : 1 );
