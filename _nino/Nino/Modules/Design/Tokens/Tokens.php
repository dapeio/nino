<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Design\Tokens	The generated design layer: a colour palette and a size raster
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Design {

	/**
	 *	The generated design layer: a colour palette and a size raster, both
	 *	produced from a handful of settings rather than written by hand.
	 *
	 *	A Theme decides which surface a component sits on ("this section is
	 *	alt"); Design decides what that surface and everything readable on it
	 *	actually are, and guarantees each pair meets a contrast target. Colours
	 *	are solved in OKLCH, whose L is perceptual, so a hue can be moved onto a
	 *	target ratio without changing what colour it reads as - sRGB and HSL
	 *	lightness cannot do that (yellow at 50% is far lighter than blue at
	 *	50%). Every emitted pair is measured against the real WCAG formula
	 *	rather than assumed from a fixed lightness, so the guarantee is one the
	 *	tests can check and not a convention.
	 */
	class Tokens {


		/*	A Theme addresses these, never a ramp step. default/alt/dark/black
			already exist as .nino-section--* and as Areas' frame choices, and
			tint is the fifth ground: the page ground carrying the brand hue
			rather than a near-grey.

			Then the brand, as four roles rather than two. Every one of them is
			one colour answering one of two questions - is it the brand or the
			second colour, and is it the one that was picked or the one text
			survives on:

				brand         the primary exactly as picked, never written on
				brand-safe    that same colour, lightness solved until it is
				accent        the second colour exactly as picked or derived
				accent-safe   that same colour, made safe the same way

			Two used to do this work, and the pair broke the moment a Secondary
			colour was set: the safe surface became the *accent* made safe, so a
			theme's buttons quietly stopped being the brand and there was no
			contrast-safe brand colour left anywhere in the palette. Four roles
			is what it takes for "brand" and "second colour" to both exist and
			both be usable.

			The three status surfaces are the same idea applied to meaning
			rather than brand - an alert is a surface with text on it like any
			other, so it comes out of the same machinery and carries the same
			promise.	*/
		public const array SURFACES = [
			'default', 'alt', 'tint', 'dark', 'black',
			'brand', 'brand-safe', 'accent', 'accent-safe',
			'success', 'warning', 'danger',
		];

		/*	Every knob is a step from 1 to 3, and step 2 is the framework
			itself: with every knob at 2 the generated stylesheet reproduces
			Nino.css's own values exactly. That is what makes the layer
			adoptable - turning it on must not move a project that has not
			asked for anything - and it is why the tables below are anchored on
			2 rather than on their midpoint by accident.

			Three rather than five because the in-between positions were not
			decisions anybody made. A setting that reads "less, as it is, more"
			is one an operator can hold in their head; asking them to choose
			between "Restrained" and "Even" is asking them to have an opinion
			they do not have. Where a knob replaces one that shipped with three
			named values, the three positions are those exact values - so a
			theme drawn against the old vocabulary looks identical under the new.

			Numbers rather than names because these are positions on a scale,
			not a vocabulary: "airy" invites the question what lies past it,
			where 3 of 3 does not. The name each position carries is a label
			for the operator, published in KNOBS, never a stored value.

			Not every decision is a scale, though, and the ones that are not
			say so. A knob is a 'scale' - three positions, 2 is the framework -
			or a 'choice': a named list of alternatives with no more and no less
			about it, and its own default. Harmony and Temperature are choices.
			Which hue the greys lean on is not "less" or "more" of anything, and
			the four classical harmonies are not a track you slide along; both
			were pretending to be scales, and the pane was telling the operator
			the middle position is always Nino's own while two knobs' were not.

			STEPS is therefore the length of a scale rather than of every knob.
			A choice publishes as many positions as it has names for.	*/
		public const int STEPS = 3;

		public const array GROUPS = [ 'colour' => 'Colour', 'raster' => 'Raster' ];

		/*	The knobs, and everything a picker needs to render one: which panel
			it belongs to, the terse note that goes beside its name, the long
			one behind it, and what each of its three positions is called. The
			frontend carries no copy of this - it draws whatever choices()
			hands it, so a knob added here appears in the Design panel and in the
			installer without either template gaining a line.

		Each position's name is the whole label an operator gets, so the three
			read as one sentence: less, as it is, more.	*/
		private const array KNOBS = [

			'harmony' => [
				'group'		=> 'colour',
				'label'		=> 'Harmony',
				'note'		=> 'where the second colour sits',
				'kind'		=> 'choice',
				'default'	=> 1,
				'steps'		=> [ 'Monochrome', 'Analogous', 'Triadic', 'Complementary' ],
				'hint'		=> 'Where the second brand colour sits on the wheel relative to the first - the four classical harmonies. A Secondary colour of your own overrides it.',
			],

			'temperature' => [
				'group'		=> 'colour',
				'label'		=> 'Temperature',
				'note'		=> 'how the greys lean',
				'kind'		=> 'choice',
				'default'	=> 3,
				'steps'		=> [ 'Neutral', 'Cool', 'Brand', 'Warm' ],
				'hint'		=> 'Which way the greys lean. At Brand they carry a trace of the brand hue itself; at Neutral they carry no colour at all.',
			],

			'saturation' => [
				'group'		=> 'colour',
				'label'		=> 'Saturation',
				'note'		=> 'how much colour',
				'default'	=> 2,
				'steps'		=> [ 'Muted', 'Standard', 'Rich' ],
				'hint'		=> 'How much colour every surface carries - the page ground, the borders and the links included, not only the brand.',
			],

			'contrast' => [
				'group'		=> 'colour',
				'label'		=> 'Contrast',
				'note'		=> 'how hard text reads',
				'default'	=> 2,
				'steps'		=> [ 'Soft', 'Standard', 'Strong' ],
				'hint'		=> 'How hard the type reads. Every position clears WCAG AA - the knob decides how far above it the ink sits.',
			],

			'depth' => [
				'group'		=> 'colour',
				'label'		=> 'Depth',
				'note'		=> 'surface separation',
				'default'	=> 2,
				'steps'		=> [ 'Flat', 'Standard', 'Raised' ],
				'hint'		=> 'How far a panel separates from the page - the alternate surface, the borders and the shadows move together.',
			],

			'scale' => [
				'group'		=> 'raster',
				'label'		=> 'Size',
				'note'		=> 'everything at once',
				'default'	=> 2,
				'steps'		=> [ 'Small', 'Standard', 'Large' ],
				'hint'		=> 'The root size every other size is measured in, so it moves type, spacing and radii together.',
			],

			'volume' => [
				'group'		=> 'raster',
				'label'		=> 'Headings',
				'note'		=> 'how far they grow',
				'default'	=> 2,
				'steps'		=> [ 'Calm', 'Standard', 'Bold' ],
				'hint'		=> 'How far the type scale fans out. Body copy never moves - a scale grows at the display end.',
			],

			'spacing' => [
				'group'		=> 'raster',
				'label'		=> 'Spacing',
				'note'		=> 'gaps and line height',
				'default'	=> 2,
				'steps'		=> [ 'Tight', 'Standard', 'Airy' ],
				'hint'		=> 'The gaps between things, and the line height that belongs with them.',
			],

			'shaping' => [
				'group'		=> 'raster',
				'label'		=> 'Corners',
				'note'		=> 'how round',
				'default'	=> 2,
				'steps'		=> [ 'Sharp', 'Standard', 'Round' ],
				'hint'		=> 'Corner radii, from a hard edge to a pill.',
			],

			'measure' => [
				'group'		=> 'raster',
				'label'		=> 'Width',
				'note'		=> 'how wide content runs',
				'default'	=> 2,
				'steps'		=> [ 'Narrow', 'Standard', 'Wide' ],
				'hint'		=> 'How wide the content may run before the layout stops growing.',
			],
		];

		// Measured off the colours Nino ships today rather than picked by eye,
		// so the generated status hues stay recognisably the project's own
		private const array STATUS = [
			'success'	=> [ 'hue' => 2.7938, 'chroma' => 0.081 ],	// 160.07 degrees
			'warning'	=> [ 'hue' => 1.4237, 'chroma' => 0.132 ],	//  81.57
			'danger'	=> [ 'hue' => 0.5010, 'chroma' => 0.178 ],	//  28.70
		];

		/*	Contrast, and the one line in this class that is not negotiable:
			4.5:1 is WCAG 2.2 SC 1.4.3 for body copy and it is the floor at
			every position, muted text included. The knob decides how far
			*above* the floor the type sits, which is what it always claimed to
			do and never did - 'soft' and 'default' used to name the same 4.5
			and the ink never moved between them.

			What moves now is the ink itself. Primary text is not solved to a
			target - solving it would put grey body copy on white at exactly
			4.5:1, legal and visibly washed out - so it is a lightness, and
			that lightness is what a reader actually feels. At 1 the dark ink
			is a soft near-black around 9:1 on white; at 3 it is black.	*/
		private const array INK_DARK	= [ 1 => 0.30, 2 => 0.16, 3 => 0.02 ];
		private const array INK_LIGHT	= [ 1 => 0.88, 2 => 0.99, 3 => 1.00 ];

		// Solved values - links, and the lightness a brand surface is moved to
		// so text survives on it. Never below AA.
		private const array TARGET_TEXT	= [ 1 => 4.5, 2 => 4.5, 3 => 7.0 ];

		// The secondary text tier is text. It used to be allowed down to 3.0,
		// which only passes for large type - a muted paragraph is not large
		// type, so the floor is the same 4.5 the body gets.
		private const array TARGET_MUTED = [ 1 => 4.5, 2 => 4.5, 3 => 7.0 ];

		// UI parts - WCAG 2.2 SC 1.4.11 asks 3:1 for them, and holding those
		// to the text target makes every border a heavy line. The focus ring
		// is held here at every setting: it is the one value no knob may soften
		private const float TARGET_UI = 3.0;

		/*	Saturation scales chroma only. Lightness stays with Contrast, or
			the two knobs fight over the same axis and neither is predictable.

			It reaches the neutral surfaces now. Scaling only the brand left
			the knob invisible on everything a page is actually made of: the
			page ground, the alternate band, the borders and the links came out
			byte-identical at all three of its old positions.	*/
		private const array CHROMA = [ 1 => 0.45, 2 => 1.00, 3 => 1.50 ];

		// A link carries more chroma than the body text it sits in - that is
		// what marks it as a link beyond the underline - so it has a floor of
		// its own rather than inheriting the surface's near-grey
		private const array LINK_C	= [ 1 => 0.06, 2 => 0.10, 3 => 0.16 ];
		private const array FOCUS_C	= [ 1 => 0.08, 2 => 0.12, 3 => 0.18 ];

		// How far the brand hue may tint a grey before it stops reading as a
		// grey. Scaled by Saturation, so the knob is visible on the page ground
		private const float NEUTRAL_C = 0.012;

		/*	Temperature: which way the greys lean. Mixed along the shortest arc
			toward a committed cool or warm hue, so a leaning grey stays related
			to the brand instead of jumping to an unrelated one, and Brand is
			the brand hue itself - what the class did before this knob existed.

			Neutral is the position that is not on that arc at all: no hue to
			lean on, because the answer is "none". A grey biased toward
			something reads as chosen and an unbiased one as unconsidered - but
			that is a claim about a page whose brand is meant to be everywhere,
			and a design that wants its colour to appear only where it is put
			has no way to say so while every ground carries a trace of it.
			TEMPERATURE holds null there, and neutralC answers it with zero.	*/
		private const float HUE_COOL	= 4.6077;	// 264 degrees, the blue a cool grey leans on
		private const float HUE_WARM	= 1.3090;	//  75 degrees, the amber a warm one leans on
		private const array TEMPERATURE	= [ 1 => null, 2 => -1.0, 3 => 0.0, 4 => 1.0 ];

		/*	Harmony: where the second brand colour sits, in degrees from the
			first. The four classical relationships, named as what they are -
			a designer asked for "analogous" knows what they are getting, and
			"Shifted" told them nothing they could check.

			Monochrome is the position where there is no second colour: the
			accent is the brand, which is what the palette did before it had a
			second colour to place at all. An explicit Secondary overrides the
			whole knob - somebody typing a second corporate hex has already
			answered this question.	*/
		private const array HARMONY = [ 1 => 0.0, 2 => 30.0, 3 => 120.0, 4 => 180.0 ];

		/*	Tint: the page ground with the brand in it rather than a trace of
			it. Close enough to the ground to sit behind body copy for a whole
			section, far enough from it to read as a different surface, and
			carrying enough chroma to be recognisably the brand rather than a
			warm grey - which is the surface a brand-coloured quiet band is
			made of and the one the palette had no answer for.

			The lightness is absolute per mode for the same reason dark and
			black are: a brand hue must not be able to drag a background
			somewhere text cannot follow.	*/
		private const array TINT_L = [ 'light' => 0.955, 'dark' => 0.255 ];
		private const float TINT_C = 0.055;

		/*	Depth: how far a panel separates from the page. Three things say
			that together, so one knob moves all three - the distance the alt
			surface keeps from the ground, how firmly a border draws, and how
			densely a shadow falls.

			The border target only ever rises. A border is load-bearing under
			SC 1.4.11 wherever it identifies a control, and Nino.css spends one
			--color-border on cards and form fields alike, so the floor stays
			3:1 and 'Flat' says what it has to say through the surface and the
			shadow instead.	*/
		private const array DEPTH_ALT = [
			'light'	=> [ 1 => 0.015, 2 => 0.035, 3 => 0.075 ],
			'dark'	=> [ 1 => 0.020, 2 => 0.045, 3 => 0.095 ],
		];
		private const array DEPTH_BORDER = [ 1 => 3.0, 2 => 3.0, 3 => 4.6 ];
		private const array DEPTH_SHADOW = [
			'light'	=> [ 1 => 0.05, 2 => 0.12, 3 => 0.24 ],
			'dark'	=> [ 1 => 0.30, 2 => 0.55, 3 => 0.82 ],
		];

		/*	The weakest the framework ever paints the ink it puts on a scrim.
			.nino-section-subtitle - the second line of every hero - carries
			opacity .8, so a scrim solved for the ink at full strength would
			hold the headline above the target and let the line under it fall
			below. The scrim is solved for the quieter of the two instead.	*/
		private const float SCRIM_INK = 0.8;

		// How far a surface sits from the page ground. dark and black are
		// deliberate steps a theme bands with and stay put; they are absolute
		// OKLCH lightnesses rather than offsets so a brand hue cannot drag
		// them somewhere unusable. alt is the one Depth moves.
		private const array SURFACE_L = [
			'light'	=> [ 'default' => 1.00, 'dark' => 0.30, 'black' => 0.18 ],
			'dark'	=> [ 'default' => 0.19, 'dark' => 0.30, 'black' => 0.13 ],
		];

		/*	The size raster. Same split as the colours: Design publishes a
			fixed set of steps, a theme decides which step a component uses.
			Numbered rather than named because a size has no meaning of its
			own - --nino-space-3 is a step, --nino-alt is a surface.

			The baselines below are Nino.css's own scale, so step 3 of every
			knob reproduces the framework exactly.	*/
		private const array BASE_TEXT		= [ 1.0, 1.125, 1.25, 1.5, 1.8, 2.5 ];
		private const array BASE_SPACE		= [ 0.5, 1.0, 2.0, 3.0, 5.0, 8.0 ];

		// Only the display end grows on a wide screen - keyed by step, since
		// the small steps are body copy and have nowhere to go
		private const array BASE_TEXT_WIDE = [ 4 => 1.6, 5 => 2.0, 6 => 3.0 ];

		// Mirrors Nino.css's own breakpoint. A second value here would split
		// the type scale from the layout it belongs to
		private const string BREAKPOINT = '768px';

		/*	Each of these is a pair of multipliers, applied to the first and
			last step and interpolated across the ones between.

			Volume is anchored at 1.0 for step 1 on purpose: body text is the
			one size that is already right, and a scale gets bigger by fanning
			out at the display end, not by pushing everything up. Spacing runs
			the other way - it bites hardest on the small steps, because the
			large ones are already section-sized and doubling them just adds
			scrolling.	*/
		private const array VOLUME = [
			1 => [ 1.0, 0.80 ],
			2 => [ 1.0, 1.00 ],
			3 => [ 1.0, 1.35 ],
		];

		private const array SPACING = [
			1 => [ 0.75, 0.85 ],
			2 => [ 1.00, 1.00 ],
			3 => [ 1.50, 1.15 ],
		];

		// Vertical rhythm belongs to Spacing, not Volume: it is the distance
		// between lines, not the size of them
		private const array LINE_HEIGHT = [ 1 => 1.45, 2 => 1.5, 3 => 1.65 ];

		// Absolute rather than multiplied: a radius has a floor (0) and a
		// ceiling (half the box) that a multiplier would sail straight past
		private const array SHAPING = [
			1 => [ 0.05, 0.10, 0.20 ],
			2 => [ 0.10, 0.20, 0.40 ],
			3 => [ 0.50, 1.50, 2.50 ],
		];

		/*	Scale is the root size, which is why it is the widest-reaching knob
			in the class: Nino.css sets html { font-size: var(--base-size) } and
			every step above is in rem, so one position here moves type,
			spacing and radii together and keeps their proportions exact.
			[ narrow screen, from the breakpoint up ], in px.	*/
		private const array BASE_SIZE = [
			1 => [ 14.0, 15.5 ],
			2 => [ 16.0, 18.0 ],
			3 => [ 18.0, 21.0 ],
		];

		// Measure is the ceiling the layout grows to, in rem. Step 3 is
		// Nino.css's own --grid-max-width
		private const array MEASURE = [ 1 => 88.0, 2 => 120.0, 3 => 160.0 ];

		// How finely the chroma recovery below walks the lightness axis, and
		// how much chroma has to be at stake before it is worth walking. Two
		// passes rather than one long one: a single grid fine enough to match
		// the bisection it has to stay comparable with would be 16k probes,
		// where a coarse sweep plus one refinement over the winning cell is 320
		private const int SURFACE_STEPS = 256;
		private const int SURFACE_REFINE = 64;
		private const float CHROMA_EPSILON = 0.0015;

		/**
		 *	The vocabulary a UI renders its controls from, so the frontend
		 *	never carries a second copy of these lists. Every knob is the same
		 *	shape - a run from min to max with a name per position - which is
		 *	what lets one control render all of them however many positions
		 *	they have.
		 *
		 *	How many that is comes from the names rather than from a constant:
		 *	a scale has three and a choice has as many alternatives as it has,
		 *	and a knob that publishes four names and three positions would be a
		 *	knob with a name nobody can pick.
		 *
		 *	@return 	array									knob => { group, label, hint, kind, steps, default, min, max }
		 */
		public static function choices(): array {

			$out = [];

			foreach( self::KNOBS as $knob => $meta )
				$out[$knob] = $meta + [ 'kind' => 'scale', 'min' => 1, 'max' => self::positions( $knob ) ];

			return $out;
		}

		/**
		 *	How many positions one knob has.
		 *
		 *	@param		string		$knob					Knob key
		 *
		 *	@return 	int
		 */
		private static function positions( string $knob ): int {
			return count( self::KNOBS[$knob]['steps'] );
		}

		/**
		 *	Bring posted settings into a complete, valid shape. Everything here
		 *	arrives from a browser, so nothing is trusted: a knob outside its
		 *	range falls back to its default and a colour that is not a hex is
		 *	refused rather than carried into the generator, where it would
		 *	silently become the fallback brand and look like the picker had
		 *	ignored the choice.
		 *
		 *	@param		array			$input				Raw posted settings
		 *
		 *	@return 	array									primary, secondary and one step per knob
		 */
		public static function normalize( array $input ): array {

			$out = [
				'primary'	=> self::color( $input['primary'] ?? '', '#4faae8' ),
				// An empty secondary is a legitimate answer, and the ordinary
				// one: it means "let Harmony solve the second accent", not
				// "fall back silently"
				'secondary'	=> self::color( $input['secondary'] ?? '', '' ),
			];

			foreach( array_keys( self::KNOBS ) as $knob )
				$out[$knob] = self::step( $input[$knob] ?? null, $knob );

			return $out;
		}

		/**
		 *	One posted knob, as a step within that knob's own range.
		 *
		 *	The type checks are the point: these arrive as decoded json, so a
		 *	value can be an array or an object as easily as a number, and php
		 *	raises rather than returning false when one is used as an array
		 *	offset - isset() included. Reached from the wire through
		 *	the Design panel's design/save and the wizard's design/apply, so an
		 *	unchecked one is a 500 anybody can ask for.
		 *
		 *	@param		mixed			$value				Straight off the wire
		 *	@param		string		$knob					Which knob it belongs to
		 *
		 *	@return 	int
		 */
		private static function step( mixed $value, string $knob ): int {

			$fallback = self::KNOBS[$knob]['default'];

			// A form post sends '4' where json sends 4
			if( is_string( $value ) === true )
				$value = is_numeric( $value ) === true ? (float) $value : null;

			if( is_int( $value ) === false && is_float( $value ) === false )
				return $fallback;

			$step = (int) round( (float) $value );

			return ( $step >= 1 && $step <= self::positions( $knob ) ) ? $step : $fallback;
		}

		/**
		 *	One posted colour, or the fallback. Three-digit hexes are expanded
		 *	so the rest of the class only ever sees one form.
		 */
		private static function color( mixed $value, string $fallback ): string {

			$value = strtolower( trim( (string) $value ) );
			$value = ltrim( $value, '#' );

			if( preg_match( '/^[0-9a-f]{3}$/', $value ) === 1 )
				$value = $value[0]. $value[0]. $value[1]. $value[1]. $value[2]. $value[2];

			return preg_match( '/^[0-9a-f]{6}$/', $value ) === 1 ? '#'. $value : $fallback;
		}

		/**
		 *	Build a complete palette for one mode.
		 *
		 *	@param		array			$settings			Design settings, normalized here if they are not already
		 *	@param		string		$mode					'light' or 'dark'
		 *
		 *	@return 	array									surface => [ bg, on, on-muted, link, border, focus, hover, active, disabled, shadow ]
		 */
		public static function palette( array $settings, string $mode = 'light' ): array {

			// Normalized here rather than trusted: palette() is public, and a
			// caller holding a half-filled array must not be able to index a
			// table with something that is not a step
			$settings	= self::normalize( $settings );
			$mode		= $mode === 'dark' ? 'dark' : 'light';
			$chroma		= self::CHROMA[$settings['saturation']];

			$primary = $settings['primary'];
			[ , $primaryC, $primaryH ] = self::oklch( $primary );

			// The neutral surfaces carry a trace of a hue rather than being
			// pure grey - a grey biased toward something reads as chosen, a
			// pure one as unconsidered. Which hue is Temperature's answer, how
			// much of it is Saturation's.
			$neutralH = self::neutralHue( $primaryH, $settings['temperature'] );
			$neutralC = self::neutralChroma( $primaryC, $settings['temperature'] ) * $chroma;

			$palette = [];

			foreach( self::surfaceL( $mode, self::DEPTH_ALT[$mode][$settings['depth']] ) as $surface => $lightness ) {
				$bg = self::hex( $lightness, $neutralC, $neutralH );
				$palette[$surface] = self::pair( $bg, $neutralH, $neutralC, $settings, $mode );
			}

			/*	The fifth ground, and the one that is not a grey: the page with
				the brand in it rather than a trace of it. It takes the brand's
				own hue rather than the neutral one - Temperature decides how
				the *greys* lean, and a tint that leaned away from the brand
				would be a brand surface answering a question about greys.

				Capped by the brand's own chroma rather than floored at a
				minimum: a brand with no colour in it has to produce a tint with
				no colour in it, or a grey corporate hex comes back as a beige
				band nobody asked for.	*/
			$tintC = min( self::TINT_C, $primaryC ) * $chroma;
			$tint = self::hex( self::TINT_L[$mode], $tintC, $primaryH );
			$palette['tint'] = self::pair( $tint, $primaryH, $tintC, $settings, $mode );

			/*	The second brand colour, before anything is done to it. An
				explicit Secondary is that colour exactly; without one it is the
				brand's own lightness and chroma carried round the wheel to
				where Harmony puts it, which is what makes Monochrome mean "the
				accent is the brand" rather than "there is no accent".	*/
			$secondary = $settings['secondary'];

			if( $secondary !== '' ) {
				[ $secondL, $secondC, $secondH ] = self::oklch( $secondary );
			} else {
				$secondL = null;
				$secondC = $primaryC;
				$secondH = $primaryH + deg2rad( self::HARMONY[$settings['harmony']] );
			}

			$accent = $secondL === null ? self::hex( self::oklch( $primary )[0], $secondC, $secondH ) : $secondary;

			/*	brand and accent are not solved values: they are the colours the
				picker returned (or the wheel derived), byte for byte, in light
				mode and in dark. Nothing here moves them - not the Saturation
				knob, not the contrast solver, not the mode. An operator who
				types their corporate hex has to find that hex in the
				stylesheet, or the picker is lying about what it does.

				What that costs is the one promise every other surface keeps:
				there is no lightness left to solve with, so --nino-on-brand is
				only the better of the two inks, not a guaranteed ratio. A brand
				colour sitting near the middle of the lightness range clears
				neither ink by much. That is a real limit, and it is why the two
				-safe roles exist rather than something a theme works around.	*/
			$palette['brand']	= self::pair( $primary, $primaryH, $primaryC, $settings, $mode );
			$palette['accent']	= self::pair( $accent, $secondH, $secondC, $settings, $mode );

			/*	...and the same two colours made safe: hue and chroma kept, the
				lightness solved until text on top clears the target. These are
				the surfaces a theme reaches for the moment something has to be
				legible on one - buttons, badges, filled sections - which is why
				the themes map --color-primary to brand-safe and keep brand
				itself for identity and decoration.

				brand-safe is the role the palette was missing. It used to
				publish one safe surface for whichever colour Harmony or a
				Secondary had settled on, so setting a second colour quietly
				replaced the brand everywhere a theme needed a legible one -
				there was no contrast-safe brand left to reach for at all.	*/
			$hued = [
				'brand-safe'	=> [ $primaryC, $primaryH ],
				'accent-safe'	=> [ $secondC, $secondH ],
			];

			// Status hues are fixed because red has to stay red - the brand
			// knobs must not be able to turn a danger surface into something
			// reassuring.
			foreach( self::STATUS as $surface => $status )
				$hued[$surface] = [ $status['chroma'], $status['hue'] ];

			foreach( $hued as $surface => [ $c, $h ] ) {
				$scaled	= $c * ( isset( self::STATUS[$surface] ) === true ? 1.0 : $chroma );
				$bg		= self::solveSurface( $h, $scaled, $mode, self::TARGET_TEXT[$settings['contrast']], $settings['contrast'] );
				$palette[$surface] = self::pair( $bg, $h, $scaled, $settings, $mode );
			}

			// Published in the order SURFACES names them, so the generated file
			// reads as the vocabulary rather than as the order it was built in
			$ordered = [];

			foreach( self::SURFACES as $surface )
				$ordered[$surface] = $palette[$surface];

			return $ordered;
		}

		/**
		 *	The four neutral grounds, in the order a theme bands with them.
		 *	Only alt moves: dark and black are the deliberate steps, and a
		 *	Depth setting that dragged them would turn a black band grey.
		 *
		 *	@param		string		$mode					'light' or 'dark'
		 *	@param		float			$alt					How far alt keeps from the page ground
		 *
		 *	@return 	array									surface => OKLCH lightness
		 */
		private static function surfaceL( string $mode, float $alt ): array {

			$base = self::SURFACE_L[$mode];

			return [
				'default'	=> $base['default'],
				// Light mode has nowhere to go but down from white, dark mode
				// nowhere but up from the ground
				'alt'		=> $mode === 'dark' ? $base['default'] + $alt : $base['default'] - $alt,
				'dark'		=> $base['dark'],
				'black'		=> $base['black'],
			];
		}

		/**
		 *	Which hue the greys lean on. Mixed along the shortest arc so a
		 *	middle position stays related to the brand rather than jumping to
		 *	an unrelated grey.
		 *
		 *	@param		float			$brand				Brand hue, in radians
		 *	@param		int				$temperature	Knob position
		 *
		 *	@return 	float
		 */
		private static function neutralHue( float $brand, int $temperature ): float {

			$mix = self::TEMPERATURE[$temperature];

			// Neutral carries no chroma, so its hue is never seen - the brand's
			// own is returned rather than a number picked to stand for nothing
			if( $mix === null || $mix === 0.0 )
				return $brand;

			return self::mixHue( $brand, $mix < 0.0 ? self::HUE_COOL : self::HUE_WARM, abs( $mix ) );
		}

		/**
		 *	How much of that hue the greys carry.
		 *
		 *	Capped against the brand's own chroma so a nearly grey brand cannot
		 *	produce grounds more colourful than itself - and zero at Neutral,
		 *	which is the whole of what that position means.
		 *
		 *	@param		float			$brand				Brand chroma
		 *	@param		int				$temperature	Knob position
		 *
		 *	@return 	float
		 */
		private static function neutralChroma( float $brand, int $temperature ): float {
			return self::TEMPERATURE[$temperature] === null ? 0.0 : min( self::NEUTRAL_C, $brand * 0.08 );
		}

		/**
		 *	Interpolate between two hues the short way round the circle.
		 *
		 *	@param		float			$from					Radians
		 *	@param		float			$to						Radians
		 *	@param		float			$amount				0 is $from, 1 is $to
		 *
		 *	@return 	float
		 */
		private static function mixHue( float $from, float $to, float $amount ): float {

			// + 3pi before the modulo keeps the dividend positive whatever the
			// two hues are, since php's fmod() takes its sign from it
			$delta = fmod( $to - $from + M_PI * 3, M_PI * 2 ) - M_PI;

			return $from + $delta * $amount;
		}

		/**
		 *	What the picked brand actually measures, per mode.
		 *
		 *	brand is the one surface the generator does not get to move, so it
		 *	is also the one whose contrast it cannot promise. Rather than leave
		 *	that as something a stylesheet author has to find out by eye, the
		 *	number is published: 'ratio' is what --nino-on-brand achieves on
		 *	--nino-brand, 'target' is what the current Contrast setting asks
		 *	for, and 'safe' is simply whether the first clears the second.
		 *
		 *	A false here is not a fault to correct. It is the case brand-safe
		 *	was built for, and a theme that maps its text-bearing roles there
		 *	has already handled it.
		 *
		 *	@param		array			$settings			Design settings
		 *
		 *	@return 	array									mode => { color, ratio, target, safe }
		 */
		public static function brand( array $settings ): array {

			$settings	= self::normalize( $settings );
			$target		= self::TARGET_TEXT[$settings['contrast']];
			$out		= [];

			foreach( [ 'light', 'dark' ] as $mode ) {

				$brand	= self::palette( $settings, $mode )['brand'];
				$ratio	= round( self::contrast( $brand['on'], $brand['bg'] ), 2 );

				$out[$mode] = [
					'color'	=> $brand['bg'],
					'ratio'	=> $ratio,
					'target'	=> $target,
					'safe'	=> $ratio >= $target,
				];
			}

			return $out;
		}

		/**
		 *	Every value a Theme may assign for one surface, each measured.
		 *
		 *	@param		string		$bg						The surface itself
		 *	@param		float			$hue					Hue of the tinted parts
		 *	@param		float			$chroma				Chroma of the tinted parts
		 *	@param		array			$settings			Normalized design settings
		 *	@param		string		$mode					'light' or 'dark'
		 *
		 *	@return 	array
		 */
		private static function pair( string $bg, float $hue, float $chroma, array $settings, string $mode ): array {

			$contrast	= $settings['contrast'];
			$saturation	= $settings['saturation'];

			// Primary text is ink, not the faintest thing that still passes.
			// Solving it to the target would put grey body copy on white at
			// exactly 4.5:1 - legal, and visibly washed out. Whichever of the
			// two inks wins takes it, and Contrast decides how hard it reads.
			[ $onLight, $onDark ] = self::inks( $hue, $chroma, $contrast );
			$on = self::contrast( $onDark, $bg ) >= self::contrast( $onLight, $bg ) ? $onDark : $onLight;

			// Everything below is measured against that decision, so a muted
			// value never crosses over to the other polarity
			$darker = $on === $onDark;

			return [
				'bg'			=> $bg,
				'on'			=> $on,
				// The muted tier is where the target is a target: it is meant to
				// sit as light as it may while staying legible. On a brand
				// surface there is barely room above the primary ink, so it is
				// capped there - a "muted" value that out-contrasts the text it
				// is subordinate to is not muted.
				'on-muted'	=> self::muted( $bg, $on, $hue, $chroma, self::TARGET_MUTED[$contrast], $darker ),
				// A link needs its own value on every surface. Reusing the brand
				// colour is exactly what breaks: that value is solved against the
				// page ground, and on a dark section it can be unreadable.
				// Carrying more chroma than the body text is what marks it as a
				// link beyond the underline.
				'link'		=> self::solve( $hue, max( $chroma, self::LINK_C[$saturation] ), $bg, self::TARGET_TEXT[$contrast], $darker ),
				'border'		=> self::solve( $hue, $chroma * 0.2, $bg, self::DEPTH_BORDER[$settings['depth']], $darker ),
				// The focus ring is held to SC 1.4.11's 3:1 against the surface
				// it sits on at every setting - Depth may draw a border more
				// firmly but never a ring less so - and carries full chroma so
				// it never reads as just another border
				'focus'		=> self::solve( $hue, max( $chroma, self::FOCUS_C[$saturation] ), $bg, self::TARGET_UI, $darker ),
				// Hover and active are nudges, not contrast promises - they only
				// have to be visible next to the resting surface, and active has
				// to be visibly past hover
				'hover'		=> self::nudge( $bg, self::luminance( $bg ) > 0.18, 1.0 ),
				'active'		=> self::nudge( $bg, self::luminance( $bg ) > 0.18, 2.1 ),
				// Disabled deliberately fails the text target: it is the one
				// state that must not look actionable. Held at 2.2:1 - readable
				// enough to identify, clearly not offered.
				'disabled'	=> self::solve( $hue, $chroma * 0.15, $bg, 2.2, $darker ),
				// Shadows are why a light-mode design collapses in dark mode:
				// black at 12% over a dark surface is invisible, so the dark
				// palette casts denser instead of reusing the light value
				'shadow'		=> self::shadow( $settings['depth'], $mode ),
			];
		}

		/**
		 *	One surface's shadow, at the density Depth asks for.
		 */
		private static function shadow( int $depth, string $mode ): string {

			$alpha = (int) round( self::DEPTH_SHADOW[$mode][$depth] * 100 );

			return $mode === 'dark' ? 'rgb(0 0 0 / '. $alpha. '%)' : 'rgb(15 23 42 / '. $alpha. '%)';
		}

		/**
		 *	The scrim over a cover photograph - the one ground the design layer
		 *	has to answer for without knowing what is underneath it.
		 *
		 *	A photograph can be anything, so the promise is made against the
		 *	worst thing it can be: a white frame. Composited on white the scrim
		 *	is as light as it will ever get, so an alpha that carries the ink
		 *	there carries it over any picture. The alpha is bisected rather
		 *	than tabled because what it has to clear moves with Contrast, with
		 *	the mode, and with how deep this design's own black sits.
		 *
		 *	Tinted rather than pure black: it is this design's deepest surface
		 *	at an alpha, so a warm page dims warm and a cool one cool, and the
		 *	scrim reads as part of the page rather than as a lid dropped on it.
		 *
		 *	A theme may paint its own scrim over this - a brand-coloured one,
		 *	or a heavier one for mood - but it starts from a value that already
		 *	keeps the promise, and it can only ever darken from here.
		 *
		 *	@param		array			$palette			The built palette for this mode
		 *	@param		int				$contrast			Knob position, which is what the ink has to clear
		 *
		 *	@return 	string								'rgb(r g b / n%)'
		 */
		private static function scrim( array $palette, int $contrast ): string {

			$deepest	= $palette['black']['bg'];
			$ink		= $palette['black']['on'];
			$target		= self::TARGET_TEXT[$contrast];

			$lo = 0.0;
			$hi = 1.0;

			/*	At alpha 1 the scrim *is* the black surface, where the ink was
				solved to clear this exact target - so the search always has an
				answer above it and only ever looks for the lightest one.	*/
			for( $i = 0; $i < 24; $i++ ) {

				$mid		= ( $lo + $hi ) / 2;
				$ground		= self::over( $deepest, '#ffffff', $mid );

				self::contrast( self::over( $ink, $ground, self::SCRIM_INK ), $ground ) >= $target
					? $hi = $mid
					: $lo = $mid;
			}

			// Rounded up, never down: the published percent has to be at least
			// the one that was solved for, or the value ships a hair under AA
			$alpha = min( 100, (int) ceil( $hi * 100 ) );

			return 'rgb('. implode( ' ', self::channels( $deepest ) ). ' / '. $alpha. '%)';
		}

		/**
		 *	One colour painted on another at an alpha, the way a browser paints
		 *	it: straight sRGB compositing, no colour space in between.
		 *
		 *	@param		string		$hex					The colour being painted
		 *	@param		string		$ground				What it is painted on
		 *	@param		float			$alpha				0..1
		 *
		 *	@return 	string
		 */
		private static function over( string $hex, string $ground, float $alpha ): string {

			$front	= self::channels( $hex );
			$back	= self::channels( $ground );
			$out	= '';

			foreach( $front as $index => $value )
				$out .= sprintf( '%02x', (int) round( $value * $alpha + $back[$index] * ( 1 - $alpha ) ) );

			return '#'. $out;
		}

		/**
		 *	A hex colour as its three 0..255 channels.
		 *
		 *	@param		string		$hex
		 *
		 *	@return 	array									[ r, g, b ]
		 */
		private static function channels( string $hex ): array {

			$hex = ltrim( $hex, '#' );

			return [
				(int) hexdec( substr( $hex, 0, 2 ) ),
				(int) hexdec( substr( $hex, 2, 2 ) ),
				(int) hexdec( substr( $hex, 4, 2 ) ),
			];
		}

		/**
		 *	The two inks every surface chooses between. Both the surface solver
		 *	and the pair builder read them here, so a surface is never solved
		 *	against one white and then painted with a slightly different one -
		 *	that gap put a brand button at 4.39:1 against a 4.5:1 target.
		 *
		 *	@param		float			$hue					Hue, for the barely-tinted dark ink
		 *	@param		float			$chroma				Chroma, capped hard
		 *	@param		int				$contrast			Knob position, which is what moves the ink
		 *
		 *	@return 	array									[ light ink, dark ink ]
		 */
		private static function inks( float $hue, float $chroma, int $contrast ): array {
			return [
				self::hex( self::INK_LIGHT[$contrast], 0.0, $hue ),
				self::hex( self::INK_DARK[$contrast], min( 0.006, $chroma ), $hue ),
			];
		}

		/**
		 *	The secondary text tier, never more prominent than the primary one.
		 */
		private static function muted( string $bg, string $on, float $hue, float $chroma, float $target, bool $darker ): string {

			$muted = self::solve( $hue, $chroma * 0.3, $bg, $target, $darker );

			return self::contrast( $muted, $bg ) > self::contrast( $on, $bg ) ? $on : $muted;
		}

		/**
		 *	One surface's own lightness, solved so that text of the required
		 *	target still fits on it. A brand color that is too light in dark
		 *	mode (or too dark in light mode) is moved until it works rather
		 *	than shipped as an unreadable button.
		 */
		private static function solveSurface( float $hue, float $chroma, string $mode, float $target, int $contrast ): string {

			// The text polarity is decided first, then one lightness is solved
			// for it. Accepting whichever of white/dark happens to clear the
			// target lets the search escape to the far end of the scale - that
			// is how a brand blue came back as near-white.
			[ $lightInk, $darkInk ] = self::inks( $hue, $chroma, $contrast );
			$ink	= $mode === 'dark' ? $darkInk : $lightInk;
			$lo		= 0.0;
			$hi		= 1.0;

			// Light mode: the darkest half carries white text, so take the
			// LIGHTEST lightness that still clears the target - the brand stays
			// as bright as it can while remaining readable. Dark mode inverts:
			// the surface carries dark text, so take the darkest that clears.
			for( $i = 0; $i < 40; $i++ ) {
				$mid = ( $lo + $hi ) / 2;
				$ok  = self::contrast( self::hex( $mid, $chroma, $hue ), $ink ) >= $target;

				if( $mode === 'dark' )
					$ok ? $hi = $mid : $lo = $mid;
				else
					$ok ? $lo = $mid : $hi = $mid;
			}

			$solved = self::hex( $mode === 'dark' ? $hi : $lo, $chroma, $hue );
			[ , $achieved ] = self::oklch( $solved );

			/*	The bisection answers "how light may this surface be", which is
				not the same question as "how much of the asked-for colour
				survives". sRGB's gamut narrows toward both ends of the
				lightness axis, so the extreme passing lightness is often the
				one where the least chroma fits - and turning Saturation up came
				back *less* saturated than leaving it alone: a brand blue
				measured 0.126 at Standard and 0.115 at Intense.

				Only worth a second look when chroma was actually lost. When the
				requested chroma fits - every setting at or below Standard, for
				most brands - the bisection above is already the answer, exactly
				as it was before.	*/
			if( $achieved >= $chroma - self::CHROMA_EPSILON )
				return $solved;

			return self::recoverChroma( $hue, $chroma, $ink, $target, $mode, $solved, $achieved );
		}

		/**
		 *	The most saturated lightness that still clears the target.
		 *
		 *	Walks the lightness axis rather than bisecting it: what is being
		 *	maximised is the chroma that survives the gamut, and that is not
		 *	monotonic in lightness, so a bisection has nothing to bisect on.
		 *
		 *	Two passes, and the second one is not a refinement for its own
		 *	sake. A surface whose chroma fits never gets here at all - it keeps
		 *	the exact answer solveSurface() bisected - so a coarse grid alone
		 *	would have the two paths disagreeing by the grid's own resolution,
		 *	and that disagreement is visible exactly where it must not be: as
		 *	one Saturation step measuring 0.002 below the step under it.
		 *
		 *	@param		float			$hue					Requested hue
		 *	@param		float			$chroma				Requested chroma, the ceiling for the search
		 *	@param		string		$ink					The text this surface has to carry
		 *	@param		float			$target				Ratio that text has to clear
		 *	@param		string		$mode					'light' or 'dark'
		 *	@param		string		$solved				The bisection's answer, and the floor for this one
		 *	@param		float			$achieved			The chroma that answer holds
		 *
		 *	@return 	string
		 */
		private static function recoverChroma( float $hue, float $chroma, string $ink, float $target, string $mode, string $solved, float $achieved ): string {

			$coarse	= self::scanChroma( $hue, $chroma, $ink, $target, $mode, 0.0, 1.0, self::SURFACE_STEPS, $solved, $achieved );
			$span		= 1.0 / self::SURFACE_STEPS;

			// Nothing on the grid beat the bisection, so there is no cell to
			// refine and the bisection stands
			if( $coarse['lightness'] === null )
				return $coarse['hex'];

			$fine = self::scanChroma( $hue, $chroma, $ink, $target, $mode,
				max( 0.0, $coarse['lightness'] - $span ),
				min( 1.0, $coarse['lightness'] + $span ),
				self::SURFACE_REFINE, $coarse['hex'], $coarse['chroma'] );

			return $fine['hex'];
		}

		/**
		 *	One sweep of the lightness axis, keeping the most saturated
		 *	candidate that clears the target. A tie goes to the end the mode
		 *	reaches for - as light as it can be in light mode, as dark as it
		 *	can be in dark mode - which is what the bisection was after in the
		 *	first place.
		 *
		 *	@param		float			$from					Where to start on the lightness axis
		 *	@param		float			$to						Where to stop
		 *	@param		int				$steps				How many probes in between
		 *	@param		string		$best					The candidate to beat
		 *	@param		float			$bestC				The chroma it holds
		 *
		 *	@return 	array									{ hex, chroma, lightness } - lightness null if nothing beat $best
		 */
		private static function scanChroma( float $hue, float $chroma, string $ink, float $target, string $mode, float $from, float $to, int $steps, string $best, float $bestC ): array {

			$preferLater	= $mode !== 'dark';
			$winner			= null;

			for( $i = 0; $i <= $steps; $i++ ) {

				$lightness	= $from + ( $to - $from ) * ( $i / $steps );
				$candidate	= self::hex( $lightness, $chroma, $hue );

				if( self::contrast( $candidate, $ink ) < $target )
					continue;

				[ , $candidateC ] = self::oklch( $candidate );

				$better	= $candidateC > $bestC + self::CHROMA_EPSILON;
				$tied		= abs( $candidateC - $bestC ) <= self::CHROMA_EPSILON;

				if( $better === false && ( $tied === false || $preferLater === false ) )
					continue;

				$best		= $candidate;
				$bestC	= max( $bestC, $candidateC );
				$winner	= $lightness;
			}

			return [ 'hex' => $best, 'chroma' => $bestC, 'lightness' => $winner ];
		}

		/**
		 *	Solve a foreground lightness against a fixed background until the
		 *	measured ratio meets the target. Binary search rather than a fixed
		 *	OKLCH lightness: fixed values land near the target, this lands on it.
		 */
		private static function solve( float $hue, float $chroma, string $against, float $target, bool $darker ): string {

			$lo = 0.0;
			$hi = 1.0;

			for( $i = 0; $i < 40; $i++ ) {
				$mid	= ( $lo + $hi ) / 2;
				$ratio	= self::contrast( self::hex( $mid, $chroma, $hue ), $against );

				if( $darker === true )
					$ratio >= $target ? $lo = $mid : $hi = $mid;
				else
					$ratio >= $target ? $hi = $mid : $lo = $mid;
			}

			return self::hex( $darker === true ? $lo : $hi, $chroma, $hue );
		}

		private static function nudge( string $hex, bool $lighter, float $steps ): string {
			[ $l, $c, $h ] = self::oklch( $hex );
			return self::hex( max( 0.0, min( 1.0, $l + ( $lighter ? -0.045 : 0.055 ) * $steps ) ), $c, $h );
		}

		/**
		 *	The generated :root block, ready to be written as a stylesheet.
		 *	Only Design writes these names; a Theme reads them and assigns its
		 *	own semantic tokens from them.
		 */
		public static function css( array $settings ): string {

			$settings = self::normalize( $settings );
			$raster = self::raster( $settings );

			$out = "/* Generated by the Design panel. Do not edit - every save rewrites this file. */\n";
			$out .= ":root {\n\tcolor-scheme: light dark;\n". self::block( $settings, 'light' ). self::sizes( $settings ). "}\n";

			/*	The raster has no dark variant, so it sits in the bare :root
				above and only its wide-screen half needs a block of its own.
				Placed before the two dark colour blocks because it overrides
				nothing they set - keeping the colour cascade one uninterrupted
				run is worth more than grouping by media query.

				--nino-base-size leads it: every length below is in rem, so the
				root size has to be the first thing the wide screen changes.	*/
			$out .= "\n@media (min-width: ". self::BREAKPOINT. ") {\n\t:root {\n";
			$out .= "\t\t--nino-base-size: ". $raster['baseSizeWide']. ";\n";

			foreach( $raster['textWide'] as $step => $value )
				$out .= "\t\t--nino-text-". $step. ': '. $value. ";\n";

			$out .= "\t}\n}\n";

			// Three states, not two: an explicit choice stamps data-nino-mode,
			// and the default "follow the system" setting stamps nothing at all -
			// so the media query has to carry the unstamped case while the
			// attribute rule wins in both directions when somebody has chosen
			$out .= "\n@media (prefers-color-scheme: dark) {\n\t:root:not([data-nino-mode=\"light\"]) {\n";
			$out .= self::block( $settings, 'dark', 2 );
			$out .= "\t}\n}\n";

			$out .= "\n:root[data-nino-mode=\"dark\"] {\n". self::block( $settings, 'dark' ). "}\n";

			return $out;
		}

		/**
		 *	The size raster: one fixed set of steps a theme assigns from, the
		 *	way the palette is one fixed set of surfaces.
		 *
		 *	Unlike the palette this has no light/dark variant - a heading is
		 *	the same size at night - so it is emitted once, in the bare :root,
		 *	and never repeated in the two dark blocks.
		 *
		 *	@param		array			$settings			Design settings
		 *
		 *	@return 	array									{ text, textWide, space, radius, lineHeight, baseSize, baseSizeWide, measure }
		 */
		public static function raster( array $settings ): array {

			$settings	= self::normalize( $settings );
			$volume		= self::VOLUME[$settings['volume']];
			$spacing	= self::SPACING[$settings['spacing']];

			$text		= [];
			$space		= [];
			$textWide	= [];

			foreach( self::BASE_TEXT as $index => $rem ) {

				$step = $index + 1;

				$text[$step] = self::rem( $rem * self::fan( $volume, $index, count( self::BASE_TEXT ) ) );

				if( isset( self::BASE_TEXT_WIDE[$step] ) === true )
					$textWide[$step] = self::rem( self::BASE_TEXT_WIDE[$step] * self::fan( $volume, $index, count( self::BASE_TEXT ) ) );
			}

			foreach( self::BASE_SPACE as $index => $rem )
				$space[$index + 1] = self::rem( $rem * self::fan( $spacing, $index, count( self::BASE_SPACE ) ) );

			$radius = [];
			foreach( self::SHAPING[$settings['shaping']] as $index => $rem )
				$radius[$index + 1] = self::rem( $rem );

			[ $base, $baseWide ] = self::BASE_SIZE[$settings['scale']];

			return [
				'text'					=> $text,
				'textWide'			=> $textWide,
				'space'					=> $space,
				'radius'				=> $radius,
				'lineHeight'		=> (string) self::LINE_HEIGHT[$settings['spacing']],
				'baseSize'			=> self::px( $base ),
				'baseSizeWide'	=> self::px( $baseWide ),
				'measure'				=> self::rem( self::MEASURE[$settings['measure']] ),
			];
		}

		/**
		 *	One knob's multiplier at one step, interpolated between the pair it
		 *	declares. Linear rather than exponential: a knob that compounds
		 *	turns a one-notch change at the display end into a different design
		 *
		 *	@param		array			$knob					[ multiplier at the first step, at the last ]
		 *	@param		int				$index				Zero-based step
		 *	@param		int				$steps				How many steps the scale has
		 *
		 *	@return 	float
		 */
		private static function fan( array $knob, int $index, int $steps ): float {
			return $steps < 2 ? $knob[0] : $knob[0] + ( $knob[1] - $knob[0] ) * ( $index / ( $steps - 1 ) );
		}

		/**
		 *	A rem value with no more precision than a browser can use and no
		 *	trailing zeroes - '1.5rem', not '1.500rem'
		 *
		 *	@param		float			$value
		 *
		 *	@return 	string
		 */
		private static function rem( float $value ): string {
			return self::trim( $value ). 'rem';
		}

		/**
		 *	The root size is the one length that cannot be in rem - it is what
		 *	rem is measured against
		 *
		 *	@param		float			$value
		 *
		 *	@return 	string
		 */
		private static function px( float $value ): string {
			return self::trim( $value ). 'px';
		}

		private static function trim( float $value ): string {
			return rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' );
		}

		/**
		 *	The raster as css declarations, for the bare :root block
		 *
		 *	@param		array			$settings			Design settings
		 *	@param		int				$depth				Indentation, in tabs
		 *
		 *	@return 	string
		 */
		private static function sizes( array $settings, int $depth = 1 ): string {

			$raster	= self::raster( $settings );
			$indent	= str_repeat( "\t", $depth );

			// Scale first: html { font-size } is what every rem below resolves
			// against, so it is the root of the raster rather than a member of it
			$out	= $indent. '--nino-base-size: '. $raster['baseSize']. ";\n";
			$out .= $indent. '--nino-line-height: '. $raster['lineHeight']. ";\n";
			$out .= $indent. '--nino-measure: '. $raster['measure']. ";\n";

			foreach( [ 'text', 'space', 'radius' ] as $group )
				foreach( $raster[$group] as $step => $value )
					$out .= $indent. '--nino-'. $group. '-'. $step. ': '. $value. ";\n";

			// A pill is not a step on the scale - it is "as round as this box
			// gets", which is the same answer at every shaping setting
			$out .= $indent. "--nino-radius-full: 999rem;\n";

			return $out;
		}

		private static function block( array $settings, string $mode, int $depth = 1 ): string {

			$indent		= str_repeat( "\t", $depth );
			$out		= '';
			$palette	= self::palette( $settings, $mode );

			foreach( $palette as $surface => $values )
				foreach( $values as $part => $hex )
					$out .= $indent. self::token( $surface, $part ). ': '. $hex. ";\n";

			$out .= $indent. '--nino-scrim: '. self::scrim( $palette, self::normalize( $settings )['contrast'] ). ";\n";

			return $out;
		}

		/**
		 *	The surface always follows an "on-" prefix directly, modifiers come
		 *	last: --nino-brand, --nino-on-brand, --nino-on-brand-muted. A
		 *	Theme reads these and assigns its own semantic names from them.
		 */
		private static function token( string $surface, string $part ): string {
			return match( true ) {
				$part === 'bg'					=> '--nino-'. $surface,
				$part === 'on'					=> '--nino-on-'. $surface,
				str_starts_with( $part, 'on-' )	=> '--nino-on-'. $surface. substr( $part, 2 ),
				default							=> '--nino-'. $surface. '-'. $part,
			};
		}

		// --- color math ------------------------------------------------------

		private static function toLinear( float $c ): float {
			return $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		}

		private static function fromLinear( float $c ): float {
			return $c <= 0.0031308 ? $c * 12.92 : 1.055 * ( $c ** ( 1 / 2.4 ) ) - 0.055;
		}

		public static function luminance( string $hex ): float {
			$hex = ltrim( $hex, '#' );
			return 0.2126 * self::toLinear( hexdec( substr( $hex, 0, 2 ) ) / 255 )
				+ 0.7152 * self::toLinear( hexdec( substr( $hex, 2, 2 ) ) / 255 )
				+ 0.0722 * self::toLinear( hexdec( substr( $hex, 4, 2 ) ) / 255 );
		}

		public static function contrast( string $a, string $b ): float {
			$la = self::luminance( $a );
			$lb = self::luminance( $b );
			return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
		}

		// sRGB hex -> OKLCH, using Björn Ottosson's published matrices
		public static function oklch( string $hex ): array {

			$hex = ltrim( trim( $hex ), '#' );
			if( strlen( $hex ) === 3 )
				$hex = $hex[0]. $hex[0]. $hex[1]. $hex[1]. $hex[2]. $hex[2];
			if( preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) !== 1 )
				$hex = '4faae8';

			$r = self::toLinear( hexdec( substr( $hex, 0, 2 ) ) / 255 );
			$g = self::toLinear( hexdec( substr( $hex, 2, 2 ) ) / 255 );
			$b = self::toLinear( hexdec( substr( $hex, 4, 2 ) ) / 255 );

			$l = ( 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b ) ** ( 1 / 3 );
			$m = ( 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b ) ** ( 1 / 3 );
			$s = ( 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b ) ** ( 1 / 3 );

			$okL = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
			$okA = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
			$okB = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

			return [ $okL, sqrt( $okA * $okA + $okB * $okB ), atan2( $okB, $okA ) ];
		}

		/**
		 *	OKLCH -> sRGB hex. Chroma is reduced until the color actually fits
		 *	in sRGB rather than letting the channels clip: clipping does not
		 *	darken a too-saturated color, it shifts its hue - an out-of-gamut
		 *	brand blue came back as pale cyan, which is neither the brand nor
		 *	a lightness the solver asked for.
		 *
		 *	The reduction bisects onto the gamut boundary rather than stepping
		 *	down by a fixed fraction. A multiplicative walk lands wherever its
		 *	stride happens to fall, which is not monotonic in what was asked
		 *	for: 0.238 stepped down by 15% at a time could end up below where
		 *	0.176 ended up, and that is what made turning Saturation up come
		 *	back less saturated. Bisecting returns the most chroma that fits,
		 *	which can only rise as the request does.
		 */
		public static function hex( float $L, float $C, float $H ): string {

			if( $C <= 0.0005 || self::inGamut( $L, $C, $H ) === true )
				return self::clip( $L, $C, $H );

			$lo = 0.0;
			$hi = $C;

			for( $i = 0; $i < 24; $i++ ) {
				$mid = ( $lo + $hi ) / 2;
				self::inGamut( $L, $mid, $H ) === true ? $lo = $mid : $hi = $mid;
			}

			return self::clip( $L, $lo, $H );
		}

		private static function inGamut( float $L, float $C, float $H ): bool {
			foreach( self::linearRgb( $L, $C, $H ) as $channel )
				if( $channel < -0.0001 || $channel > 1.0001 )
					return false;
			return true;
		}

		private static function clip( float $L, float $C, float $H ): string {

			$out = '#';
			foreach( self::linearRgb( $L, $C, $H ) as $channel )
				$out .= str_pad( dechex( (int) round( max( 0.0, min( 1.0, self::fromLinear( $channel ) ) ) * 255 ) ), 2, '0', STR_PAD_LEFT );

			return $out;
		}

		private static function linearRgb( float $L, float $C, float $H ): array {

			$a = $C * cos( $H );
			$b = $C * sin( $H );

			$l = ( $L + 0.3963377774 * $a + 0.2158037573 * $b ) ** 3;
			$m = ( $L - 0.1055613458 * $a - 0.0638541728 * $b ) ** 3;
			$s = ( $L - 0.0894841775 * $a - 1.2914855480 * $b ) ** 3;

			return [
				 4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
				-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
				-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
			];
		}
	}

}
