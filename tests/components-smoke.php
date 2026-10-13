<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	components-smoke.php	Dependency-free smoke test for \Nino\Modules\Components - the
 *											registry, the schemas, the six titles, the card, the stacks, cell()
 *											and escape() - and for what Nino.css hands a design layer: the hero,
 *											the font roles, the scale, the hooks and the grounds on three
 *											levels. Runs against an isolated sandbox directory, never touches
 *											the real project data.
 *
 *	Usage: php tests/components-smoke.php
 */

require __DIR__. '/harness.php';

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$appData = ninoSandbox( 'components' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";


// --- What Nino.css hands a design layer -----------------------------------

echo "Nino.css - the hero is the hero, the font roles, the scale and the hooks\n";

$hookCss = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( __DIR__. '/../_nino/Nino.css' ) );
check( 'the fold keeps its name: body.nino-scroll-atf is still what Nino.css and Nino.ui.js say', str_contains( $hookCss, 'body.nino-scroll-atf ' ) === true
	&& str_contains( (string) file_get_contents( __DIR__. '/../_nino/Nino.ui.js' ), "'nino-scroll-atf'" ) === true );
check( 'the arrow Nino.ui.js listens on is the one Nino.css draws', str_contains( (string) file_get_contents( __DIR__. '/../_nino/Nino.ui.js' ), "'.nino-hero-arrowdown'" ) === true
	&& str_contains( $hookCss, '.nino-hero-arrowdown {' ) === true );

/*	Three small readers of the stylesheet, comments gone: the declarations of
	every rule whose selector list is exactly the one given (the media query
	that states a size again is a rule too), the first :root and the one the
	768px query holds, and the value of a property in a block.	*/
$hookRules = static function( string $selector ) use ( $hookCss ): string {
	preg_match_all( '/(?:^|[{}])\s*'. preg_quote( $selector, '/' ). '\s*\{([^{}]*)\}/', $hookCss, $found );
	return implode( ' ', $found[1] );
};
$hookRoot			= preg_match( '/(?:^|\})\s*:root\s*\{([^{}]*)\}/', $hookCss, $found ) === 1 ? $found[1] : '';
$hookRootWide	= preg_match( '/@media\s*\(min-width:\s*768px\)\s*\{\s*:root\s*\{([^{}]*)\}/', $hookCss, $found ) === 1 ? $found[1] : '';
$hookValue		= static function( string $block, string $property ): ?string {
	return preg_match( '/(?<![\w-])'. preg_quote( $property, '/' ). '\s*:\s*([^;]+?)\s*(?:;|$)/', $block, $found ) === 1 ? $found[1] : null;
};

// The five roles: title, subtitle, text, button and code each have a family, a weight, a transform and a size
$hookRoles		= [ 'title', 'subtitle', 'text', 'button', 'code' ];
$hookMissing	= [];
foreach( $hookRoles as $hookRole )
	foreach( [ '--fontfamily-'. $hookRole, '--font-'. $hookRole. '-weight', '--font-'. $hookRole. '-transform', '--font-'. $hookRole. '-size' ] as $hookProperty )
		if( $hookValue( $hookRoot, $hookProperty ) === null )
			$hookMissing[] = $hookProperty;
check( 'Nino.css declares the five font roles in :root, each with a family, a weight, a transform and a size'. ( $hookMissing === [] ? '' : ' - missing: '. implode( ', ', $hookMissing ) ), $hookMissing === [] );
check( '...no role transforms anything until a design layer says so, and the button has the face of the text',
	array_filter( $hookRoles, static fn( string $role ): bool => $hookValue( $hookRoot, '--font-'. $role. '-transform' ) !== 'none' ) === []
	&& $hookValue( $hookRoot, '--fontfamily-button' ) === 'var(--fontfamily-text)' );

// What the rules had before there were roles
$hookDefaults = [
	'--font-title-weight' => 'bold', '--font-subtitle-weight' => 'normal', '--font-text-weight' => 'normal', '--font-button-weight' => 'bold', '--font-code-weight' => 'normal',
	'--font-title-size' => 'var(--text-4)', '--font-subtitle-size' => 'var(--text-2)', '--font-text-size' => '1rem', '--font-button-size' => 'var(--fontsize-btn)', '--font-code-size' => '.875rem',
];
$hookChanged = [];
foreach( $hookDefaults as $hookProperty => $hookExpected )
	if( $hookValue( $hookRoot, $hookProperty ) !== $hookExpected )
		$hookChanged[] = $hookProperty;
check( '...with the weights and the sizes the rules had before they were roles'. ( $hookChanged === [] ? '' : ' - changed: '. implode( ', ', $hookChanged ) ), $hookChanged === [] );

// Each rule reads the role it belongs to
$hookReads = [
	'body'											=> [ '--font-text-size', '--font-text-weight', '--font-text-transform' ],
	'h1, h2, h3, h4, h5, h6'		=> [ '--font-title-weight', '--font-title-transform' ],
	'.nino-section-title'				=> [ '--fontfamily-title', '--font-title-size', '--font-title-transform', '--section-title-scale' ],
	'.nino-card-title'					=> [ '--fontfamily-title', '--font-title-size', '--font-title-transform', '--card-title-scale' ],
	'.nino-hero-title'					=> [ '--fontfamily-title', '--font-title-size', '--font-title-transform', '--hero-title-scale' ],
	'.nino-section-subtitle'		=> [ '--fontfamily-subtitle', '--font-subtitle-size', '--font-subtitle-weight', '--font-subtitle-transform', '--section-subtitle-scale' ],
	'.nino-card-subtitle'			=> [ '--fontfamily-subtitle', '--font-subtitle-size', '--font-subtitle-weight', '--font-subtitle-transform', '--card-subtitle-scale' ],
	'.nino-hero-subtitle'				=> [ '--fontfamily-subtitle', '--font-subtitle-size', '--font-subtitle-weight', '--font-subtitle-transform', '--hero-text-scale' ],
	'.nino-btn'									=> [ '--fontfamily-button', '--font-button-size', '--font-button-weight', '--font-button-transform' ],
	'.nino-code'								=> [ '--fontfamily-code', '--font-code-size', '--font-code-weight', '--font-code-transform' ],
];
$hookUnread = [];
foreach( $hookReads as $hookSelector => $hookProperties )
	foreach( $hookProperties as $hookProperty )
		if( str_contains( $hookRules( $hookSelector ), 'var('. $hookProperty. ')' ) === false )
			$hookUnread[] = $hookSelector. ' does not read '. $hookProperty;
check( 'the page, the headings, the titles, the subtitles, the button and the code block read their role'. ( $hookUnread === [] ? '' : ' - '. implode( ', ', $hookUnread ) ), $hookUnread === [] );
check( '...the small and the big button scale the size of the role, and the filter button is as large as the button',
	str_contains( $hookRules( '.nino-btn--small' ), 'var(--font-button-size)' ) === true && str_contains( $hookRules( '.nino-btn--big' ), 'var(--font-button-size)' ) === true
	&& str_contains( $hookRules( '.nino-filter-btn' ), 'font-size: var(--font-button-size)' ) === true );
// A size in rem on body takes the browser's smaller size for a bare code, kbd, samp and pre away - measured: 13px beside text of 16px - so
// these say it themselves, relative to what they stand in: .8125em is 13 / 16, and the rule leaves the ones inside another of the four
// alone, which would otherwise shrink twice
check( '...and the page reads the size of the text role on body, which the code, kbd, samp and pre that have no class of their own answer with a size of their own, .8125em, once',
	preg_match( '/(?<![\w-])font-size\s*:\s*var\(--font-text-size\)/', $hookRules( 'body' ) ) === 1
	&& preg_match( '/(?<![\w-])font-size\s*:\s*\.8125em\s*;/', $hookRules( ':is(code, kbd, samp, pre):not(:is(code, kbd, samp, pre) *)' ) ) === 1
	&& preg_match_all( '/\.8125em/', $hookCss ) === 1 );
/*	The quirk is the browser's, and it stops where an element states its size in a length: the kernel's own sized classes for text, titles, prices, counts and labels - the text and the
	description, the titles and the subtitles of a section, a card and a hero, the title and the price of the pricing table, each with its variants, the price of a card,
	the stat counter, the message of a form, the button and the filter button - have the size of their text for a bare code, kbd, samp and pre, 1em (measured: a code in a [text]
	is 16px beside text of 16px, not 13, and one in a form message 14px beside 14, not 11.4). One rule says it, after the one above and with the same guard for the ones inside
	another of the four, and :where() keeps the classes out of its specificity, so that it is as specific as the one above and a class with a size of its own (.nino-code)
	still wins over both	*/
$hookSized = [ '.nino-btn', '.nino-filter-btn', '.nino-form-message', '.nino-stat-counter', '.nino-card-price' ];
foreach( [ 'section-text', 'card-descr', 'section-title', 'card-title', 'hero-title', 'section-subtitle', 'card-subtitle', 'hero-subtitle', 'pricing-title', 'pricing-price' ] as $hookClass )
	foreach( [ '', '--quiet', '--loud' ] as $hookVariant )
		$hookSized[] = '.nino-'. $hookClass. $hookVariant;
$hookGuard	= ':is(code, kbd, samp, pre):not(:is(code, kbd, samp, pre) *)';
$hookFirst	= preg_match( '/(?:^|[{}])\s*'. preg_quote( $hookGuard, '/' ). '\s*\{[^{}]*\}/', $hookCss, $hookFirstRule, PREG_OFFSET_CAPTURE ) === 1 ? $hookFirstRule[0][1] : -1;
$hookKeeps	= preg_match_all( '/(?:^|[{}])\s*:where\(([^()]*)\)\s*'. preg_quote( $hookGuard, '/' ). '\s*\{([^{}]*)\}/', $hookCss, $hookKept, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) === 1 ? $hookKept[0] : [];
$hookNamed	= isset( $hookKeeps[1] ) ? array_map( 'trim', explode( ',', $hookKeeps[1][0] ) ) : [];
check( '...and the kernel\'s sized classes - the text and the description, the titles and the subtitles with their variants, the title and the price of the pricing table with theirs, the price of a card, the stat counter, the message of a form, the button and the filter button - give them 1em, in one :where() rule after that one'. ( $hookKeeps === [] ? ' - no such rule' : '' ),
	$hookKeeps !== [] && $hookFirst > -1 && $hookKeeps[0][1] > $hookFirst
	&& count( $hookNamed ) === count( $hookSized ) && array_diff( $hookSized, $hookNamed ) === [] && array_diff( $hookNamed, $hookSized ) === []
	&& preg_match( '/^\s*font-size\s*:\s*1em\s*;\s*$/', $hookKeeps[2][0] ) === 1 );
check( '...the arrow of a hero is no text and is not in it, and the rule says nothing but the size',
	in_array( '.nino-hero-arrowdown', $hookNamed, true ) === false && substr_count( $hookKeeps[2][0] ?? '', ':' ) === 1 );
$hookUnsized = array_filter( $hookSized, static fn( string $class ): bool => preg_match( '/(?<![\w-])font-size\s*:/', $hookRules( $class ) ) !== 1 );
check( '...and every class it names states a size of its own, so that none of them is a name nothing sets any more'. ( $hookUnsized === [] ? '' : ' - none: '. implode( ', ', $hookUnsized ) ), $hookUnsized === [] && count( $hookSized ) === 35 );
check( '...and the code block keeps the size of the code role, and its code the font it inherits',
	str_contains( $hookRules( '.nino-code' ), 'var(--font-code-size)' ) === true && preg_match( '/font\s*:\s*inherit/', $hookRules( '.nino-code code' ) ) === 1 );

// The scale is relative to the text size, with the factors its rem values were
$hookScale = $hookValue( $hookRoot, '--text-1' ) === 'var(--font-text-size)';
foreach( [ 2 => '1.125', 3 => '1.25', 4 => '1.5', 5 => '1.8', 6 => '2.5' ] as $hookStep => $hookFactor )
	$hookScale = $hookScale && $hookValue( $hookRoot, '--text-'. $hookStep ) === 'calc(var(--font-text-size) * '. $hookFactor. ')';
foreach( [ 4 => '1.6', 5 => '2', 6 => '3' ] as $hookStep => $hookFactor )
	$hookScale = $hookScale && $hookValue( $hookRootWide, '--text-'. $hookStep ) === 'calc(var(--font-text-size) * '. $hookFactor. ')';
check( '--text-1 is the text size, and --text-2 to --text-6 are factors of it - the ones from 768px their own', $hookScale );

// The hero and the variants are numbers a design layer moves
check( 'the hero takes the title and the subtitle role times a number of its own: one below 768px and another from there',
	$hookValue( $hookRoot, '--hero-title-scale' ) === '1.2' && $hookValue( $hookRootWide, '--hero-title-scale' ) === '1.25'
	&& $hookValue( $hookRoot, '--hero-text-scale' ) === '1.2' && $hookValue( $hookRootWide, '--hero-text-scale' ) === 'calc(10 / 9)' );
check( 'a card title takes the title role times a number of its own: 5 / 6, what --text-3 was of --text-4, and 25 / 32 from 768px, where --text-4 steps up and --text-3 does not',
	$hookValue( $hookRoot, '--card-title-scale' ) === 'calc(5 / 6)' && $hookValue( $hookRootWide, '--card-title-scale' ) === 'calc(25 / 32)' );
check( 'a section title, a section subtitle and a card subtitle take their role times a number of their own, 1 at every width, and the rule under a section title is 0px until a design layer gives it a thickness',
	$hookValue( $hookRoot, '--section-title-scale' ) === '1' && $hookValue( $hookRoot, '--section-subtitle-scale' ) === '1' && $hookValue( $hookRoot, '--card-subtitle-scale' ) === '1' && $hookValue( $hookRoot, '--section-line' ) === '0px'
	&& $hookValue( $hookRootWide, '--section-title-scale' ) === null && $hookValue( $hookRootWide, '--section-subtitle-scale' ) === null && $hookValue( $hookRootWide, '--card-subtitle-scale' ) === null && $hookValue( $hookRootWide, '--section-line' ) === null );
// The rule, its thickness and the air under it, is a title's own and its quiet and loud variants carry it too: a page may use one without the class it varies
$hookUnruled = [];
foreach( [ '.nino-section-title', '.nino-section-title--quiet', '.nino-section-title--loud' ] as $hookTitle )
	foreach( [ 'border-bottom: var(--section-line) solid var(--color-border)', 'padding-bottom: calc( var(--section-line) * 6 )' ] as $hookDeclaration )
		if( str_contains( $hookRules( $hookTitle ), $hookDeclaration ) === false )
			$hookUnruled[] = $hookTitle. ' does not say '. $hookDeclaration;
check( 'a section title draws a rule of --section-line under itself, with six times that much air, and so do its quiet and its loud variant'. ( $hookUnruled === [] ? '' : ' - '. implode( '; ', $hookUnruled ) ), $hookUnruled === [] );
/*	A variant is a factor of its role: the size is the size of the class it varies - the role, and for every title and subtitle of a
	section, a card and a hero the scale that class has - times --scale-quiet or --scale-loud, and so is the leading where the variant
	states one. The variants of the pricing table are not: they stay on the ladder	*/
$hookVariants = [
	'.nino-section-title'			=> [ 'var(--font-title-size)', 'var(--section-title-scale)' ],
	'.nino-section-subtitle'	=> [ 'var(--font-subtitle-size)', 'var(--section-subtitle-scale)' ],
	'.nino-section-text'			=> [ 'var(--font-text-size)' ],
	'.nino-card-title'				=> [ 'var(--font-title-size)', 'var(--card-title-scale)' ],
	'.nino-card-subtitle'		=> [ 'var(--font-subtitle-size)', 'var(--card-subtitle-scale)' ],
	'.nino-card-descr'				=> [ 'var(--font-text-size)' ],
	'.nino-hero-title'				=> [ 'var(--font-title-size)', 'var(--hero-title-scale)' ],
	'.nino-hero-subtitle'			=> [ 'var(--font-subtitle-size)', 'var(--hero-text-scale)' ],
];
$hookUnscaled = [];
foreach( $hookVariants as $hookBase => $hookFactors )
	foreach( [ 'quiet', 'loud' ] as $hookKind ) {

		// The size is the step times the number, and so is the leading where the variant states one
		preg_match_all( '/(?<![\w-])(?:font-size|line-height)\s*:([^;]*);/', $hookRules( $hookBase. '--'. $hookKind ), $hookSizes );

		if( count( $hookSizes[1] ) < 1 )
			$hookUnscaled[] = $hookBase. '--'. $hookKind;
		foreach( $hookSizes[1] as $hookSize )
			foreach( array_merge( $hookFactors, [ 'var(--scale-'. $hookKind. ')' ] ) as $hookFactor )
				if( str_contains( $hookSize, $hookFactor ) === false )
					$hookUnscaled[] = $hookBase. '--'. $hookKind. ' does not read '. $hookFactor;
	}
check( 'the quiet and the loud variants of a section, a card and a hero are their role times the number of their class, where it has one, times --scale-quiet and --scale-loud, which are .85 and 1.2'. ( $hookUnscaled === [] ? '' : ' - not: '. implode( ', ', $hookUnscaled ) ),
	$hookUnscaled === [] && $hookValue( $hookRoot, '--scale-quiet' ) === '.85' && $hookValue( $hookRoot, '--scale-loud' ) === '1.2' );
// The three classes that took a number of their own are the same factors themselves: the size, and the leading of the two section classes, which moves with it
$hookUnscaledBase = [];
foreach( [ '.nino-section-title', '.nino-section-subtitle', '.nino-card-subtitle' ] as $hookBase ) {
	preg_match_all( '/(?<![\w-])(?:font-size|line-height)\s*:([^;]*);/', $hookRules( $hookBase ), $hookSizes );
	if( count( $hookSizes[1] ) < 1 )
		$hookUnscaledBase[] = $hookBase;
	foreach( $hookSizes[1] as $hookSize )
		foreach( $hookVariants[$hookBase] as $hookFactor )
			if( str_contains( $hookSize, $hookFactor ) === false )
				$hookUnscaledBase[] = $hookBase. ' does not read '. $hookFactor;
}
check( '...and so are a section title, a section subtitle and a card subtitle themselves - the leading of the two section classes with their size'. ( $hookUnscaledBase === [] ? '' : ' - not: '. implode( ', ', $hookUnscaledBase ) ), $hookUnscaledBase === [] );
$hookVariantRules = '';
foreach( array_keys( $hookVariants ) as $hookBase )
	$hookVariantRules .= $hookRules( $hookBase. '--quiet' ). ' '. $hookRules( $hookBase. '--loud' ). ' ';
check( '...none of the sixteen stands on a step of the ladder any more, and the pricing table\'s four still do',
	preg_match( '/var\(--text-\d\)/', $hookVariantRules ) !== 1
	&& str_contains( $hookRules( '.nino-pricing-title--quiet' ), 'var(--text-1)' ) === true && str_contains( $hookRules( '.nino-pricing-title--loud' ), 'var(--text-4)' ) === true
	&& str_contains( $hookRules( '.nino-pricing-price--quiet' ), 'var(--text-4)' ) === true && str_contains( $hookRules( '.nino-pricing-price--loud' ), 'var(--text-6)' ) === true );

// The gutter of a row and the shadow of a card
check( '--grid-gutter is the padding of a row: one step below 768px, the next from there, and no other rule sets it',
	$hookValue( $hookRoot, '--grid-gutter' ) === 'var(--space-2)' && $hookValue( $hookRootWide, '--grid-gutter' ) === 'var(--space-3)'
	&& preg_match_all( '/padding\s*:/', $hookRules( '.nino-grid-row' ) ) === 1 && str_contains( $hookRules( '.nino-grid-row' ), 'padding: 0 var(--grid-gutter)' ) === true );
check( '--shadow-card is none until a design layer says otherwise, and the two cards - .nino-card and .nino-pricing-item - read it as their box-shadow',
	$hookValue( $hookRoot, '--shadow-card' ) === 'none'
	&& str_contains( $hookRules( '.nino-card' ), 'box-shadow: var(--shadow-card)' ) === true
	&& str_contains( $hookRules( '.nino-pricing-item' ), 'box-shadow: var(--shadow-card)' ) === true );
check( '...a borderless card is without a surface, and a shadow is part of one: it keeps none', str_contains( $hookRules( '.nino-card--borderless' ), 'box-shadow: none' ) === true );

// What the card shadow leaves alone: no rule but these two writes a box-shadow of its own
$hookShadows = [];
preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $hookCss, $hookBlocks, PREG_SET_ORDER );
foreach( $hookBlocks as [ , $hookBlockSelector, $hookBlockBody ] )
	if( preg_match_all( '/(?<![\w-])box-shadow\s*:\s*([^;]+?)\s*(?:;|$)/', $hookBlockBody, $hookBlockShadows ) > 0 )
		foreach( $hookBlockShadows[1] as $hookBlockShadow )
			if( in_array( $hookBlockShadow, [ 'var(--shadow-card)', 'none' ], true ) === false )
				$hookShadows[] = trim( (string) preg_replace( '/\s+/', ' ', $hookBlockSelector ) );
check( 'the only box-shadows Nino.css writes itself are those of the middle card of .nino-pricing-row--feature-middle and of the toast'. ( $hookShadows === [] ? ' - none found' : ' - found: '. implode( ', ', $hookShadows ) ),
	$hookShadows === [ '.nino-pricing-row--feature-middle > .nino-pricing-item:nth-child(2)', '.nino-toast' ] );

echo "\n";


// --- Grounds on three levels ------------------------------------------------

echo "Nino.css - grounds on three levels, the counter ground and the picture of a column\n";

/*	Every rule of the stylesheet by the selectors it names: a list is one rule, and each member of it is answered with the whole body,
	which is what asking the cascade about a class comes to - and a comma inside a :is() or a :not() does not part a selector	*/
$groundEach = [];
preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $hookCss, $groundBlocks, PREG_SET_ORDER );
foreach( $groundBlocks as [ , $groundSelectors, $groundBody ] )
	foreach( (array) preg_split( '/,(?![^()]*\))/', $groundSelectors ) as $groundSelector )
		$groundEach[ trim( (string) preg_replace( '/\s+/', ' ', (string) $groundSelector ) ) ][] = $groundBody;
$groundOf = static fn( string $selector ): string => implode( ' ', $groundEach[$selector] ?? [] );
$groundSets = static fn( string $body, string $property, string $value ): bool => preg_match( '/(?<![\w-])'. preg_quote( $property, '/' ). '\s*:\s*'. preg_quote( $value, '/' ). '\s*(?:;|$)/', $body ) === 1;

$groundNames		= [ 'default', 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt' ];
$groundParts		= [ 'bg', 'text', 'title', 'accent', 'focus', 'border' ];
$groundFamilies	= [ 'grid', 'grid-next', 'card' ];

// The counter pairs, and what each ground is made of - the surface, its ink, and the title, accent, focus ring and border that are solved against
// them (the ink of the two brand grounds is all of the first three) - written here, from the spec, not read from the stylesheet
$groundCounter	= [ 'default' => 'alt', 'alt' => 'default', 'tint' => 'default', 'dark' => 'black', 'black' => 'dark', 'primary' => 'brand-alt', 'brand-alt' => 'primary' ];
$groundSource		= [
	'default'		=> [ 'bg' => 'var(--color-section-default-bg)', 'text' => 'var(--color-section-default-text)', 'title' => 'var(--color-title-default)', 'accent' => 'var(--color-accent-default)', 'focus' => 'var(--color-focus-default)', 'border' => 'var(--color-border-default)' ],
	'primary'		=> [ 'bg' => 'var(--color-primary)', 'text' => 'var(--color-primary-text)', 'title' => 'var(--color-primary-text)', 'accent' => 'var(--color-primary-text)', 'focus' => 'var(--color-primary-text)', 'border' => 'var(--color-border-primary)' ],
	'brand-alt'	=> [ 'bg' => 'var(--color-brand-alt)', 'text' => 'var(--color-brand-alt-text)', 'title' => 'var(--color-brand-alt-text)', 'accent' => 'var(--color-brand-alt-text)', 'focus' => 'var(--color-brand-alt-text)', 'border' => 'var(--color-border-brand-alt)' ],
];
foreach( [ 'alt', 'tint', 'dark', 'black' ] as $ground )
	$groundSource[$ground] = [ 'bg' => 'var(--color-section-'. $ground. '-bg)', 'text' => 'var(--color-section-'. $ground. '-text)', 'title' => 'var(--color-section-'. $ground. '-text)',
		'accent' => 'var(--color-accent-'. $ground. ')', 'focus' => 'var(--color-focus-'. $ground. ')', 'border' => 'var(--color-border-'. $ground. ')' ];
$groundSurface	= array_map( static fn( array $source ): string => $source['bg'], $groundSource );

$groundMissing = [];
foreach( array_merge( [ '--color-ground', '--color-ink' ], array_map( static fn( string $ground ): string => '--color-border-'. $ground, $groundNames ) ) as $groundProperty )
	if( $hookValue( $hookRoot, $groundProperty ) === null )
		$groundMissing[] = $groundProperty;
foreach( $groundFamilies as $groundFamily )
	foreach( $groundParts as $groundPart )
		if( $hookValue( $hookRoot, '--counter-'. $groundFamily. '-'. $groundPart ) === null )
			$groundMissing[] = '--counter-'. $groundFamily. '-'. $groundPart;
check( ':root declares the ground and its ink, the border of each of the seven grounds, and the three families of the counter ground with six parts each'. ( $groundMissing === [] ? '' : ' - missing: '. implode( ', ', $groundMissing ) ), $groundMissing === [] );
$groundWrong = [];
foreach( [ 'grid' => 'alt', 'grid-next' => 'default', 'card' => 'alt' ] as $groundFamily => $groundTarget )
	foreach( $groundParts as $groundPart )
		if( $hookValue( $hookRoot, '--counter-'. $groundFamily. '-'. $groundPart ) !== $groundSource[$groundTarget][$groundPart] )
			$groundWrong[] = '--counter-'. $groundFamily. '-'. $groundPart. ' is not '. $groundSource[$groundTarget][$groundPart];
check( '...the page is the default ground, its columns stand out as alt, a card in such a column is default again and a card straight on the page is alt - all six parts of each - and the border of every ground is the page\'s border'. ( $groundWrong === [] ? '' : ' - '. implode( '; ', $groundWrong ) ),
	$groundWrong === [] && $hookValue( $hookRoot, '--color-ground' ) === 'var(--color-section-default-bg)' && $hookValue( $hookRoot, '--color-ink' ) === 'var(--color-text)'
	&& array_filter( $groundNames, static fn( string $ground ): bool => $hookValue( $hookRoot, '--color-border-'. $ground ) !== 'var(--color-border)' ) === [] );

// Values are other properties, never a colour of their own
preg_match_all( '/(--counter-[a-z-]+)\s*:\s*([^;}]+)/', $hookCss, $groundDeclared, PREG_SET_ORDER );
$groundLiteral = array_map( static fn( array $declared ): string => $declared[1]. ': '. trim( $declared[2] ), array_filter( $groundDeclared, static fn( array $declared ): bool => preg_match( '/^var\(--[a-z-]+\)$/', trim( $declared[2] ) ) !== 1 ) );
check( 'every value of the counter families is a var() of a source - there is no colour of its own in them'. ( $groundLiteral === [] ? '' : ' - found: '. implode( ', ', $groundLiteral ) ), $groundLiteral === [] && count( $groundDeclared ) > 100 );

// A section answers for its own ground with all three families, and the pairs are the fixed ones
$groundWrong = [];
foreach( array_slice( $groundNames, 1 ) as $ground ) {
	$groundBody = $groundOf( '.nino-section--'. $ground );
	foreach( [ 'grid' => $groundCounter[$ground], 'grid-next' => $groundCounter[ $groundCounter[$ground] ], 'card' => $groundCounter[$ground] ] as $groundFamily => $groundTarget )
		foreach( $groundParts as $groundPart )
			if( $groundSets( $groundBody, '--counter-'. $groundFamily. '-'. $groundPart, $groundSource[$groundTarget][$groundPart] ) === false )
				$groundWrong[] = '.nino-section--'. $ground. ' does not set --counter-'. $groundFamily. '-'. $groundPart. ' to '. $groundSource[$groundTarget][$groundPart];
}
check( 'every ground of a section sets the three families, all six parts of each, to the counter of its own ground (grid, card) and to the counter of that (grid-next)'. ( $groundWrong === [] ? '' : ' - '. implode( '; ', $groundWrong ) ), $groundWrong === [] );

// A column with a ground, or dimmed, hands the cards in it the counter of its ground
$groundWrong = [];
foreach( array_slice( $groundNames, 1 ) as $ground ) {
	$groundBody = $groundOf( '.nino-grid--'. $ground );
	foreach( $groundParts as $groundPart )
		if( $groundSets( $groundBody, '--counter-card-'. $groundPart, $groundSource[ $groundCounter[$ground] ][$groundPart] ) === false )
			$groundWrong[] = '.nino-grid--'. $ground. ' does not hand its cards '. $groundSource[ $groundCounter[$ground] ][$groundPart]. ' as --counter-card-'. $groundPart;
	if( preg_match( '/(?<![\w-])--counter-grid(?:-next)?-[a-z]+\s*:/', $groundBody ) === 1 )
		$groundWrong[] = '.nino-grid--'. $ground. ' sets a family of the section';
}
foreach( $groundParts as $groundPart )
	if( $groundSets( $groundOf( '.nino-grid--dim' ), '--counter-card-'. $groundPart, $groundSource['black'][$groundPart] ) === false )
		$groundWrong[] = '.nino-grid--dim does not hand its cards the counter of the dark ground as --counter-card-'. $groundPart;
check( 'every ground of a column sets all six parts of the card family to the counter of its ground, a dimmed column to that of the dark one, and none of them touches the families of the section'. ( $groundWrong === [] ? '' : ' - '. implode( '; ', $groundWrong ) ), $groundWrong === [] );

// The counter reads the families, part by part
$groundWrong = [];
foreach( [ 'bg' => '--color-ground', 'text' => '--color-ink', 'title' => '--color-title', 'accent' => '--color-accent', 'focus' => '--color-focus', 'border' => '--color-border' ] as $groundPart => $groundRole ) {
	if( $groundSets( $groundOf( '.nino-grid--counter' ), $groundRole, 'var(--counter-grid-'. $groundPart. ')' ) === false )
		$groundWrong[] = '.nino-grid--counter does not read --counter-grid-'. $groundPart;
	if( $groundSets( $groundOf( '.nino-grid--counter' ), '--counter-card-'. $groundPart, 'var(--counter-grid-next-'. $groundPart. ')' ) === false )
		$groundWrong[] = '.nino-grid--counter does not hand --counter-grid-next-'. $groundPart. ' to its cards';
	if( $groundSets( $groundOf( '.nino-card--counter' ), $groundRole, 'var(--counter-card-'. $groundPart. ')' ) === false )
		$groundWrong[] = '.nino-card--counter does not read --counter-card-'. $groundPart;
}
foreach( [ '.nino-grid--counter', '.nino-card--counter' ] as $groundSelector )
	if( $groundSets( $groundOf( $groundSelector ), 'background-color', 'var(--color-ground)' ) === false || $groundSets( $groundOf( $groundSelector ), 'color', 'var(--color-ink)' ) === false )
		$groundWrong[] = $groundSelector. ' does not paint the ground it took';
check( 'a counter column takes its ground from the grid family and hands the next family to its cards, a counter card takes its ground from the card family - all six parts, and paints it'. ( $groundWrong === [] ? '' : ' - '. implode( '; ', $groundWrong ) ), $groundWrong === [] );

// Every ground modifier of every level sets the pair and the roles, and paints the pair
$groundWrong = [];
foreach( array_slice( $groundNames, 1 ) as $ground )
	foreach( [ 'section', 'grid', 'card' ] as $groundLevel ) {
		$groundBody = $groundOf( '.nino-'. $groundLevel. '--'. $ground );
		foreach( [ '--color-ground' => 'bg', '--color-ink' => 'text', '--color-title' => 'title', '--color-subtitle' => 'text', '--color-accent' => 'accent', '--color-focus' => 'focus', '--color-border' => 'border' ] as $groundRole => $groundPart )
			if( $groundSets( $groundBody, $groundRole, $groundSource[$ground][$groundPart] ) === false )
				$groundWrong[] = '.nino-'. $groundLevel. '--'. $ground. ' does not set '. $groundRole. ' to '. $groundSource[$ground][$groundPart];
		if( $groundSets( $groundBody, 'background-color', 'var(--color-ground)' ) === false || $groundSets( $groundBody, 'color', 'var(--color-ink)' ) === false )
			$groundWrong[] = '.nino-'. $groundLevel. '--'. $ground. ' does not paint the pair it sets';
	}
check( 'a section, a column and a card of a ground set the same seven roles to the same sources and paint the same pair - the vocabulary is one'. ( $groundWrong === [] ? '' : ' - '. implode( '; ', $groundWrong ) ), $groundWrong === [] );
check( '...the plain section sets the pair of the default ground and paints it, as it did',
	$groundSets( $groundOf( '.nino-section' ), '--color-ground', 'var(--color-section-default-bg)' ) && $groundSets( $groundOf( '.nino-section' ), '--color-ink', 'var(--color-section-default-text)' )
	&& $groundSets( $groundOf( '.nino-section' ), 'background-color', 'var(--color-section-default-bg)' ) && $groundSets( $groundOf( '.nino-section' ), 'color', 'var(--color-section-default-text)' ) );

// Nothing compares one ground with another: no selector names a ground of a section or a column above a ground of a column or a card
$groundCompared = [];
foreach( array_keys( $groundEach ) as $groundSelector )
	if( preg_match( '/\.nino-(?:section|grid)--(?:alt|tint|dark|black|primary|brand-alt|counter|dim)(?![\w-])[^,]*[\s>+~]\.nino-(?:grid|card)--(?:alt|tint|dark|black|primary|brand-alt|counter|dim)(?![\w-])/', (string) $groundSelector ) === 1 )
		$groundCompared[] = (string) $groundSelector;
check( 'no rule compares the ground of a thing with the ground it sits on: no selector has a section or a column of a ground above a column or a card of one'. ( $groundCompared === [] ? '' : ' - '. implode( ' | ', $groundCompared ) ),
	$groundCompared === [] && isset( $groundEach['.nino-section--alt .nino-card--alt'] ) === false && count( $groundEach ) > 400 );

// A column that paints keeps its content off the colour and is rounded as a card is
$groundWrong = [];
foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter', 'dim' ] as $ground )
	if( $groundSets( $groundOf( '.nino-grid--'. $ground ), 'padding', 'var(--space-2)' ) === false || $groundSets( $groundOf( '.nino-grid--'. $ground ), 'border-radius', 'var(--radius)' ) === false )
		$groundWrong[] = '.nino-grid--'. $ground;
check( 'a column that paints a ground, the counter and a dimmed one have padding of one step and the radius of a card'. ( $groundWrong === [] ? '' : ' - not: '. implode( ', ', $groundWrong ) ), $groundWrong === [] );

// A card of any ground has the chrome the alt card had, and the borderless one still has none
$groundWrong = [];
foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter' ] as $ground )
	if( $groundSets( $groundOf( '.nino-card--'. $ground ), 'border', '1px solid var(--color-border)' ) === false || $groundSets( $groundOf( '.nino-card--'. $ground ), 'padding', 'var(--space-1)' ) === false )
		$groundWrong[] = '.nino-card--'. $ground;
check( 'a card of any ground, the counter included, has the border and the padding the alt card had'. ( $groundWrong === [] ? '' : ' - not: '. implode( ', ', $groundWrong ) ),
	$groundWrong === [] && $groundSets( $groundOf( '.nino-card' ), 'padding', '0 0 var(--space-1)' ) === true && str_contains( $groundOf( '.nino-card--borderless' ), 'border: 0' ) === true );

/*	A borderless card has no border, no shadow and no surface of its own - and a card that names a ground is painted with it all the same: the
	fill of every .nino-card--<ground> is declared after .nino-card--borderless, a rule of the same specificity, so it is the one that wins.
	Otherwise the card keeps the ink solved for its ground and loses the surface: white text on the page, measured	*/
$groundBorderlessAt = -1;
$groundFilledAt = [];
foreach( $groundBlocks as $groundAt => [ , $groundSelectors, $groundBody ] )
	foreach( (array) preg_split( '/,(?![^()]*\))/', $groundSelectors ) as $groundSelector ) {
		$groundSelector = trim( (string) preg_replace( '/\s+/', ' ', (string) $groundSelector ) );
		if( $groundSelector === '.nino-card--borderless' && preg_match( '/(?<![\w-])background(?:-color)?\s*:\s*transparent/', $groundBody ) === 1 )
			$groundBorderlessAt = $groundAt;
		if( $groundSets( $groundBody, 'background-color', 'var(--color-ground)' ) === true )
			$groundFilledAt[$groundSelector] = $groundAt;
	}
$groundWrong = [];
foreach( array_slice( $groundNames, 1 ) as $ground )
	if( ( $groundFilledAt['.nino-card--'. $ground] ?? -1 ) < $groundBorderlessAt )
		$groundWrong[] = '.nino-card--'. $ground;
if( ( $groundFilledAt['.nino-card--counter'] ?? -1 ) < $groundBorderlessAt )
	$groundWrong[] = '.nino-card--counter';
check( 'a borderless card that names a ground keeps its fill: every .nino-card--<ground>, the counter included, paints after .nino-card--borderless'. ( $groundWrong === [] ? '' : ' - painted before it: '. implode( ', ', $groundWrong ) ),
	$groundWrong === [] && $groundBorderlessAt > 0 && $groundSets( $groundOf( '.nino-card--borderless' ), 'box-shadow', 'none' ) === true && $groundSets( $groundOf( '.nino-card--borderless' ), 'border', '0' ) === true
	&& preg_match( '/(?<![\w-])background(?:-color)?\s*:\s*transparent/', $groundOf( '.nino-card--borderless' ) ) === 1 );

// The picture of a column
check( 'a column may carry a picture: .nino-grid-bg lies over the column and is cut at its corners, its image covers it, the column becomes the box it is placed in',
	$groundSets( $groundOf( '.nino-grid-bg' ), 'position', 'absolute' ) && $groundSets( $groundOf( '.nino-grid-bg' ), 'inset', '0' ) && $groundSets( $groundOf( '.nino-grid-bg' ), 'overflow', 'hidden' )
	&& $groundSets( $groundOf( '.nino-grid-bg' ), 'border-radius', 'inherit' ) && $groundSets( $groundOf( '.nino-grid-bg > img' ), 'object-fit', 'cover' )
	&& $groundSets( $groundOf( ':has(> .nino-grid-bg, > .nino-card-bg)' ), 'position', 'relative' ) && $groundSets( $groundOf( ':has(> .nino-grid-bg, > .nino-card-bg)' ), 'isolation', 'isolate' ) );
$groundWrong = [];
foreach( [ '--color-ground' => 'bg', '--color-ink' => 'text', '--color-title' => 'title', '--color-subtitle' => 'text', '--color-accent' => 'accent', '--color-focus' => 'focus', '--color-border' => 'border' ] as $groundRole => $groundPart )
	if( $groundSets( $groundOf( '.nino-grid--dim' ), $groundRole, $groundSource['dark'][$groundPart] ) === false )
		$groundWrong[] = $groundRole;
check( '...and .nino-grid--dim darkens it with the scrim of a dimmed cover, and is the dark ground - its roles, and the ink of it also where a rule reads the page\'s - and paints the pair under the picture'. ( $groundWrong === [] ? '' : ' - not: '. implode( ', ', $groundWrong ) ),
	$groundWrong === [] && $groundSets( $groundOf( '.nino-grid--dim > .nino-grid-bg::after' ), 'background-color', 'var(--color-background-dim)' )
	&& $groundSets( $groundOf( '.nino-grid--dim' ), '--color-text', 'var(--color-section-dark-text)' )
	&& $groundSets( $groundOf( '.nino-grid--dim' ), 'background-color', 'var(--color-ground)' ) && $groundSets( $groundOf( '.nino-grid--dim' ), 'color', 'var(--color-ink)' ) );

/*	A dimmed column is the dark ground whatever else it names, as a dimmed cover is what it is whatever its section is: the one block that gives
	.nino-grid--dim its ground and hands its cards theirs comes after the block of every ground of a column and after the counter's, rules of the same
	specificity, so it is the one that wins. In a list of the dark ground, ahead of the others, the column that says --dim and --alt, --tint, --primary or
	--counter kept the ink and the roles of that other ground, and wrote dark ink on its darkened picture - measured. The order is the check, and
	.nino-grid--dim is in no list of the grounds of a section, a column or a card	*/
$groundDimAt = [];
$groundLastAt = -1;
$groundDimListed = [];
foreach( $groundBlocks as $groundAt => [ , $groundSelectors, $groundBody ] ) {
	$groundList = array_map( static fn( string $groundSelector ): string => trim( (string) preg_replace( '/\s+/', ' ', $groundSelector ) ), (array) preg_split( '/,(?![^()]*\))/', $groundSelectors ) );
	if( preg_match( '/(?<![\w-])(?:--color-ground|--counter-card-bg)\s*:/', $groundBody ) !== 1 )
		continue;
	if( in_array( '.nino-grid--dim', $groundList, true ) === true ) {
		$groundDimAt[] = $groundAt;
		if( count( $groundList ) > 1 )
			$groundDimListed[] = implode( ', ', $groundList );
	}
	foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter' ] as $groundName )
		if( in_array( '.nino-grid--'. $groundName, $groundList, true ) === true )
			$groundLastAt = max( $groundLastAt, $groundAt );
}
check( 'a dimmed column is the dark ground whatever else it names: one block of its own gives it its ground, and it comes after every ground of a column and the counter'. ( $groundDimListed === [] ? '' : ' - in a list: '. implode( ' | ', $groundDimListed ) ),
	count( $groundDimAt ) === 1 && $groundLastAt > 0 && $groundDimAt[0] > $groundLastAt && $groundDimListed === [] );

// The cell of a stack is a div again: nothing lays a card into the row of a stack, and the slot is the one rule it always was
$groundStale = array_values( array_filter( array_keys( $groundEach ), static fn( mixed $selector ): bool => str_contains( (string) $selector, '.nino-stack > .nino-card' ) ) );
check( 'a stack\'s cell is a div: no rule lays a card into the row of a stack - the margin and the eighteen widths of the card as a cell are gone - and the slot of a stack is the one rule it was'
	. ( $groundStale === [] ? '' : ' - left: '. implode( ', ', $groundStale ) ),
	$groundStale === [] && $groundSets( $groundOf( '.nino-stack > *' ), 'padding', 'calc(var(--nino-stack-gap) / 2)' ) === true && $groundSets( $groundOf( '.nino-stack > *' ), 'align-content', 'start' ) === true
	&& $groundSets( $groundOf( '.nino-stack > *' ), 'display', 'grid' ) === true );

// The members of every rule, in the order of the stylesheet, which is the order the cascade breaks a tie in. A selector list is parted at the commas that are
// not inside a pair of brackets, however deep: a :is() that holds a :not() is one selector
$groundSplit = static function( string $selectors ): array {
	$parts = [];
	$depth = 0;
	$current = '';
	foreach( str_split( $selectors ) as $char ) {
		if( $char === '(' || $char === '[' )
			$depth++;
		if( $char === ')' || $char === ']' )
			$depth--;
		if( $char === ',' && $depth === 0 ) {
			$parts[] = $current;
			$current = '';
			continue;
		}
		$current .= $char;
	}
	$parts[] = $current;
	return array_map( static fn( string $part ): string => trim( (string) preg_replace( '/\s+/', ' ', $part ) ), $parts );
};
$groundMembers = array_map( static fn( array $groundBlock ): array => $groundSplit( $groundBlock[1] ), $groundBlocks );
$groundWhole = static function( string $selector ) use ( $groundMembers, $groundBlocks ): string {
	$bodies = [];
	foreach( $groundMembers as $groundAt => $groundList )
		if( in_array( $selector, $groundList, true ) === true )
			$bodies[] = $groundBlocks[$groundAt][2];
	return implode( ' ', $bodies );
};
$groundLastOf = static function( array $names, string $property = '' ) use ( $groundMembers, $groundBlocks ): int {
	$last = -1;
	foreach( $groundMembers as $groundAt => $groundList )
		if( array_intersect( $names, $groundList ) !== [] && ( $property === '' || preg_match( '/(?<![\w-])'. preg_quote( $property, '/' ). '\s*:/', $groundBlocks[$groundAt][2] ) === 1 ) )
			$last = $groundAt;
	return $last;
};

/*	A card in the cell of a stack takes no margin of its own: the half gap of the cell is the room between neighbours and the edges of the stack are the column's. Measured at 1280px with
	a gap of 2, the neighbours stood 36px apart where the gap is 18px, and the first card 9px in from the column's edge. It is the rule of a card in a cell, with more classes in its selector
	than any rule that gives a card a margin - the base one and the 768px one among them - so that it wins wherever it stands in the file; the margins of a card are for a card that stands
	in a column	*/
$groundCellCard = '.nino-stack > * > .nino-card';
$groundSlotAt = $groundLastOf( [ '.nino-stack > *' ], 'display' );
check( 'a card in the cell of a stack takes no margin of its own: '. $groundCellCard. ' says margin: 0, once and as the only margin it sets, has more classes than the rules of a card, and comes after the slot rule',
	$groundSets( $groundOf( $groundCellCard ), 'margin', '0' ) === true && count( $groundEach[$groundCellCard] ?? [] ) === 1
	&& preg_match( '/(?<![\w-])margin-(?:top|right|bottom|left)\s*:/', $groundOf( $groundCellCard ) ) !== 1
	&& substr_count( $groundCellCard, '.nino-' ) > substr_count( '.nino-card', '.nino-' ) && $groundSlotAt > 0 && $groundLastOf( [ $groundCellCard ] ) > $groundSlotAt );

/*	The cells of a stack on a phone: the classes of the grid are the only width a cell has - the -s- widths from 640px, the -m- ones from 768px, the -l- ones from 1024px - so below
	640px a cell was as wide as its content, and a loop of cards with short texts drew narrow cards side by side at 375px (55, 55 and 68px wide, measured). The cells are one below the
	other there, as wide as the row the stack is. The query ends where the -s- widths begin	*/
check( 'the cells of a stack are one below the other on a phone: below 640px, where the classes of the grid begin, .nino-stack > * is 100% wide - and the -s- widths are the first that apply from 640px',
	preg_match( '/@media\s*\(max-width:\s*639\.98px\)\s*\{\s*\.nino-stack\s*>\s*\*\s*\{\s*width:\s*100%;\s*\}\s*\}/', $hookCss ) === 1
	&& preg_match( '/@media\s*\(min-width:\s*640px\)\s*\{\s*\.nino-grid-s-25\s*\{[^{}]*\}/', $hookCss ) === 1
	&& preg_match( '/@media\s*\(min-width:\s*768px\)\s*\{\s*\.nino-grid-m-25\s*\{/', $hookCss ) === 1 && preg_match( '/@media\s*\(min-width:\s*1024px\)\s*\{\s*\.nino-grid-l-25\s*\{/', $hookCss ) === 1
	&& $groundLastOf( [ '.nino-stack > *' ], 'width' ) > $groundSlotAt );

/*	A card that fills: as tall as the column or the cell it stands in and with no margin at all. The bottom margin would push it out of that box by exactly that much, and from 768px the top one would
	stand it that far lower and the sides take it in from the edges (measured: 9px at 1280px). The 768px block gives a card all four margins and comes before, so the rule has to come after every rule
	of .nino-card for its margin to be the one that wins. The percentage resolves against a stretched flex item - a plain column - and against nothing in a grid that places its content at the
	start: so the grid that holds the card, the slot of a stack and a column with a stack class, stretches its row, which is the rule of that grid and not the card's	*/
$groundFillGrids = [
	'.nino-stack > :has(> .nino-card--fill)',
	'.nino-grid-row > :is(.nino-stack-start, .nino-stack-center, .nino-stack-end, [class*="nino-stack-gap-"]:not(.nino-stack)):has(> .nino-card--fill)',
];
$groundWrong = [];
foreach( $groundFillGrids as $groundSelector )
	if( $groundSets( $groundWhole( $groundSelector ), 'align-content', 'stretch' ) === false )
		$groundWrong[] = $groundSelector;
check( 'a card may fill its column or its cell: .nino-card--fill is as tall as the box it stands in and has no margin at all, and the grid that holds it stretches its row - the slot of a stack and a column with a stack class'
	. ( $groundWrong === [] ? '' : ' - not: '. implode( ' | ', $groundWrong ) ),
	$groundWrong === [] && $groundSets( $groundOf( '.nino-card--fill' ), 'height', '100%' ) === true && $groundSets( $groundOf( '.nino-card--fill' ), 'margin', '0' ) === true
	&& preg_match( '/(?<![\w-])margin-(?:top|right|bottom|left)\s*:/', $groundOf( '.nino-card--fill' ) ) !== 1 );
check( '...the rule of the card comes after every rule of .nino-card - the 768px one, which gives it all four margins, among them - and the stretching rules after the rules that place the content of those grids at the start, the middle and the end',
	$groundLastOf( [ '.nino-card--fill' ] ) > $groundLastOf( [ '.nino-card' ] ) && $groundLastOf( [ '.nino-card' ] ) > 0
	&& $groundLastOf( [ '.nino-stack > *', '.nino-grid-row > .nino-stack-start', '.nino-grid-row > .nino-stack-center', '.nino-grid-row > .nino-stack-end' ] ) > 0
	&& $groundLastOf( $groundFillGrids ) > $groundLastOf( [ '.nino-stack > *', '.nino-grid-row > .nino-stack-start', '.nino-grid-row > .nino-stack-center', '.nino-grid-row > .nino-stack-end' ] ) );

// Where the content stands in a card taller than it: a flex column's to say, and a button in it is as wide as its label, where the text stands
$groundWrong = [];
foreach( [ '.nino-card--middle', '.nino-card--bottom', '.nino-card--last-bottom' ] as $groundSelector )
	if( $groundSets( $groundOf( $groundSelector ), 'display', 'flex' ) === false || $groundSets( $groundOf( $groundSelector ), 'flex-direction', 'column' ) === false )
		$groundWrong[] = $groundSelector;
check( 'where the content stands in a card taller than it is a flex column\'s to say: the middle, the bottom, and the last element at the bottom behind an automatic margin above it'
	. ( $groundWrong === [] ? '' : ' - not a flex column: '. implode( ', ', $groundWrong ) ),
	$groundWrong === [] && $groundSets( $groundOf( '.nino-card--middle' ), 'justify-content', 'center' ) === true && $groundSets( $groundOf( '.nino-card--bottom' ), 'justify-content', 'flex-end' ) === true
	&& $groundSets( $groundOf( '.nino-card--last-bottom > :last-child' ), 'margin-top', 'auto' ) === true
	&& preg_match( '/(?<![\w-])justify-content\s*:/', $groundOf( '.nino-card--last-bottom' ) ) !== 1 );
$groundVertical = ':is(.nino-card--middle, .nino-card--bottom, .nino-card--last-bottom)';
check( '...a button in it is as wide as its label and sits where the text does, as in the column of a stack: at the start, in the middle for a centred text, at the end for a right-aligned one',
	$groundSets( $groundOf( $groundVertical. ' > .nino-btn' ), 'align-self', 'flex-start' ) === true
	&& $groundSets( $groundOf( $groundVertical. '.nino-text-center > .nino-btn' ), 'align-self', 'center' ) === true
	&& $groundSets( $groundOf( $groundVertical. '.nino-text-right > .nino-btn' ), 'align-self', 'flex-end' ) === true );

// The picture behind a card, after the pattern of the one behind a column: the same rules, and the rule that makes the parent the box it is placed in is one for both
$groundBgBody = static fn( string $selector ): string => trim( (string) preg_replace( '/\s+/', ' ', $groundOf( $selector ) ) );
check( 'a card may carry a picture, after the pattern of the column\'s: .nino-card-bg lies over the card, is cut at its corners and behind its content, its image covers it - the declarations of .nino-grid-bg',
	$groundSets( $groundOf( '.nino-card-bg' ), 'position', 'absolute' ) && $groundSets( $groundOf( '.nino-card-bg' ), 'inset', '0' ) && $groundSets( $groundOf( '.nino-card-bg' ), 'overflow', 'hidden' )
	&& $groundSets( $groundOf( '.nino-card-bg' ), 'border-radius', 'inherit' ) && $groundSets( $groundOf( '.nino-card-bg' ), 'z-index', '-1' )
	&& $groundSets( $groundOf( '.nino-card-bg > img' ), 'object-fit', 'cover' ) && $groundSets( $groundOf( '.nino-card-bg > img' ), 'width', '100%' ) && $groundSets( $groundOf( '.nino-card-bg > img' ), 'height', '100%' )
	&& $groundBgBody( '.nino-card-bg' ) === $groundBgBody( '.nino-grid-bg' ) && $groundBgBody( '.nino-card-bg > img' ) === $groundBgBody( '.nino-grid-bg > img' ) );
check( '...a card with a picture is the box it is placed in and a stacking context of its own by the rule of the column\'s - extended, not copied - and --dim darkens it with the scrim of a dimmed cover',
	isset( $groundEach[':has(> .nino-grid-bg)'] ) === false && isset( $groundEach[':has(> .nino-card-bg)'] ) === false
	&& $groundSets( $groundOf( ':has(> .nino-grid-bg, > .nino-card-bg)' ), 'position', 'relative' ) && $groundSets( $groundOf( ':has(> .nino-grid-bg, > .nino-card-bg)' ), 'isolation', 'isolate' )
	&& $groundSets( $groundOf( '.nino-card--dim > .nino-card-bg::after' ), 'background-color', 'var(--color-background-dim)' )
	&& $groundBgBody( '.nino-card--dim > .nino-card-bg::after' ) === $groundBgBody( '.nino-grid--dim > .nino-grid-bg::after' ) );

/*	A dimmed card is the dark ground whatever else it names, as a dimmed column is: its roles and the ink of the dark ground, also where a rule reads the page's, and the pair it
	paints under the picture - a borderless card too. It comes once, in a block of its own, after every ground of a card, after the counter and after the borderless card, rules of
	the same specificity, so the last one wins. A card holds no card: it hands no family of the counter ground on	*/
$groundWrong = [];
foreach( [ '--color-ground' => 'bg', '--color-ink' => 'text', '--color-title' => 'title', '--color-subtitle' => 'text', '--color-accent' => 'accent', '--color-focus' => 'focus', '--color-border' => 'border' ] as $groundRole => $groundPart )
	if( $groundSets( $groundOf( '.nino-card--dim' ), $groundRole, $groundSource['dark'][$groundPart] ) === false )
		$groundWrong[] = $groundRole;
check( 'a dimmed card is the dark ground: its roles, the ink of it also where a rule reads the page\'s, and the pair painted under the picture - and a card holds no card, so it hands no counter family on'
	. ( $groundWrong === [] ? '' : ' - not: '. implode( ', ', $groundWrong ) ),
	$groundWrong === [] && $groundSets( $groundOf( '.nino-card--dim' ), '--color-text', 'var(--color-section-dark-text)' ) === true
	&& $groundSets( $groundOf( '.nino-card--dim' ), 'background-color', 'var(--color-ground)' ) === true && $groundSets( $groundOf( '.nino-card--dim' ), 'color', 'var(--color-ink)' ) === true
	&& preg_match( '/(?<![\w-])--counter-/', $groundOf( '.nino-card--dim' ) ) !== 1 );
$groundDimAt = $groundLastOf( [ '.nino-card--dim' ], '--color-ground' );
$groundWrong = [];
foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter', 'borderless' ] as $groundName )
	if( $groundLastOf( [ '.nino-card--'. $groundName ] ) > $groundDimAt || $groundLastOf( [ '.nino-card--'. $groundName ] ) < 0 )
		$groundWrong[] = '.nino-card--'. $groundName;
// The blocks that give it its ground: its chrome is in the list of the rule that gives a card of a ground its border and its padding, below, and that block is not one of them
$groundDimGrounds = array_filter( $groundMembers, static fn( array $groundList, int $groundAt ): bool => in_array( '.nino-card--dim', $groundList, true ) === true
	&& preg_match( '/(?<![\w-])--color-ground\s*:/', $groundBlocks[$groundAt][2] ) === 1, ARRAY_FILTER_USE_BOTH );
$groundDimListed = array_filter( $groundDimGrounds, static fn( array $groundList ): bool => count( $groundList ) > 1 );
check( '...it comes after every ground of a card, after the counter and after the borderless card, in a block of its own'. ( $groundWrong === [] ? '' : ' - before: '. implode( ', ', $groundWrong ) ),
	$groundWrong === [] && $groundDimAt > 0 && $groundDimListed === [] && count( $groundDimGrounds ) === 1 );

/*	A dimmed card has the border and the padding of a card of a ground, as a dimmed column has the padding and the radius of a column of a ground: .nino-card--dim is in the list
	of the rule that gives them. Without them it was the one card of a dark surface that had neither - below 768px, where nothing else gives a card its padding, a [card dim] with a
	picture wrote its title flush against the edge of the dark card (0px in from it, measured at 375px; 9px now, the padding of 8px and the 1px border of the dark ground). The rule is the
	same block as the other grounds', and comes before .nino-card--borderless, so that a dimmed borderless card has the padding and no border, and before the padding of .nino-p-<n>, which wins	*/
$groundChrome = array_filter( $groundMembers, static fn( array $groundList ): bool => in_array( '.nino-card--dim', $groundList, true ) === true && in_array( '.nino-card--alt', $groundList, true ) === true );
$groundChromeAt = array_key_first( $groundChrome ) ?? -1;
$groundWrong = [];
foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter' ] as $groundName )
	if( in_array( '.nino-card--'. $groundName, $groundMembers[$groundChromeAt] ?? [], true ) === false )
		$groundWrong[] = '.nino-card--'. $groundName;
check( 'a dimmed card has the border and the padding of a card of a ground: .nino-card--dim is in the list of the rule that gives them, with the seven grounds'. ( $groundWrong === [] ? '' : ' - not in it: '. implode( ', ', $groundWrong ) ),
	count( $groundChrome ) === 1 && $groundWrong === [] && $groundSets( $groundOf( '.nino-card--dim' ), 'border', '1px solid var(--color-border)' ) === true
	&& $groundSets( $groundOf( '.nino-card--dim' ), 'padding', 'var(--space-1)' ) === true
	&& $groundChromeAt > 0 && $groundLastOf( [ '.nino-card--borderless' ] ) > $groundChromeAt );

// Full width, a dimmed card drops its side paddings as a card of a ground does: the :is() list of the full-width rule names it with the seven grounds
preg_match( '/\.nino-card--fullwidth:is\(([^)]*)\)/', $hookCss, $groundFullwidth );
$groundWrong = [];
foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter', 'dim' ] as $groundName )
	if( preg_match( '/(?<![\w-])\.nino-card--'. preg_quote( $groundName, '/' ). '(?![\w-])/', (string) ( $groundFullwidth[1] ?? '' ) ) !== 1 )
		$groundWrong[] = '.nino-card--'. $groundName;
check( '...and the full-width rule lists it with the seven grounds, so a full-width dimmed card has no side padding either'. ( $groundWrong === [] ? '' : ' - not in it: '. implode( ', ', $groundWrong ) ), $groundWrong === [] );

// The padding of a card is a utility's to say: .nino-p-<n> is the last chapter of the file, after the rules that give a card its own - the base, the 768px and the 1280px ones, and the ground's
$groundCardPaddingAt = -1;
foreach( $groundMembers as $groundAt => $groundList )
	if( array_intersect( [ '.nino-card', '.nino-card--alt', '.nino-card--tint', '.nino-card--dark', '.nino-card--black', '.nino-card--primary', '.nino-card--brand-alt', '.nino-card--counter' ], $groundList ) !== []
		&& preg_match( '/(?<![\w-])padding\s*:/', $groundBlocks[$groundAt][2] ) === 1 )
		$groundCardPaddingAt = $groundAt;
$groundWrong = [];
foreach( [ '0', '1', '2', '3', '4', '5', '6' ] as $groundStep )
	if( $groundLastOf( [ '.nino-p-'. $groundStep ] ) <= $groundCardPaddingAt )
		$groundWrong[] = '.nino-p-'. $groundStep;
check( 'the padding of a card is a step of the space scale or what its ground gives: .nino-p-<n> comes after every rule that gives a card its own padding, so it is the one that wins'. ( $groundWrong === [] ? '' : ' - before it: '. implode( ', ', $groundWrong ) ),
	$groundWrong === [] && $groundCardPaddingAt > 0 && $groundSets( $groundOf( '.nino-p-0' ), 'padding', '0' ) === true && $groundSets( $groundOf( '.nino-p-6' ), 'padding', 'var(--space-6)' ) === true );

/*	What a role carries is not said again by a rule of the ground a thing sits on: the title and the subtitle of a section and of a hero, its arrow, the outline button and the
	filter button take the colours of the nearest ground - a column, a card, a dimmed cover - because they read its roles, and a rule of a section above them would reach past a
	column or a card of another ground in between and write the ink of the section on it (measured: white on the alt ground of a column in a dark section, 1.08:1). So no selector
	has a ground of a section, a column, a card, a dimmed cover or a dimmed picture above one of them	*/
$groundSaid = [];
foreach( array_keys( $groundEach ) as $groundSelector )
	if( preg_match( '/\.nino-(?:section|grid|card|cover|img-background)--(?:alt|tint|dark|black|primary|brand-alt|counter|dim)(?![\w-])[^,]*[\s>+~]\.nino-(?:section-title|section-subtitle|hero-title|hero-subtitle|hero-arrowdown|btn--outline|filter-btn)(?![\w-])/', (string) $groundSelector ) === 1 )
		$groundSaid[] = (string) $groundSelector;
check( 'no rule of a section, a column, a card or a dimmed cover says again what a role carries: no selector has a ground above a title or a subtitle of a section or a hero, the arrow of a hero, an outline button or a filter button'. ( $groundSaid === [] ? '' : ' - '. implode( ' | ', $groundSaid ) ),
	$groundSaid === [] && isset( $groundEach['.nino-hero-title'], $groundEach['.nino-btn--outline'], $groundEach['.nino-filter-btn'], $groundEach['.nino-hero-arrowdown'] ) === true );
$groundWrong = [];
foreach( [
	[ '.nino-section-title', 'color', 'var(--color-title)' ], [ '.nino-hero-title', 'color', 'var(--color-title)' ],
	[ '.nino-section-subtitle', 'color', 'var(--color-subtitle)' ], [ '.nino-hero-subtitle', 'color', 'var(--color-subtitle)' ],
	[ '.nino-btn--outline', 'color', 'var(--color-accent)' ], [ '.nino-btn--outline', 'border', '1px solid var(--color-accent)' ],
	[ '.nino-hero-arrowdown', 'color', 'inherit' ], [ '.nino-filter-btn', 'color', 'inherit' ],
] as [ $groundSelector, $groundProperty, $groundValue ] )
	if( $groundSets( $groundOf( $groundSelector ), $groundProperty, $groundValue ) === false )
		$groundWrong[] = $groundSelector. ' does not set '. $groundProperty. ' to '. $groundValue;
// A dimmed cover and a dimmed picture are the dark ground for what stands in them, the subtitle included: that is what lets them go without a rule for each thing in them
foreach( [ '.nino-cover--dim', '.nino-img-background--dim' ] as $groundSelector )
	foreach( [ '--color-title', '--color-subtitle' ] as $groundProperty )
		if( $groundSets( $groundOf( $groundSelector ), $groundProperty, 'var(--color-section-black-text)' ) === false )
			$groundWrong[] = $groundSelector. ' does not set '. $groundProperty;
check( '...the roles carry them: a title reads --color-title and a subtitle --color-subtitle, the outline button --color-accent for its text and its border, the arrow of a hero and the filter button the ink they inherit, and a dimmed cover and picture set the title and the subtitle'
	. ( $groundWrong === [] ? '' : ' - '. implode( '; ', $groundWrong ) ), $groundWrong === [] );
// The pair each of them paints: the page's, the deepest ground's (a cover or a picture dimmed, the toast), the alt ground's (the striped rows) or a status's
$groundPage = [ 'bg' => 'var(--color-background)', 'text' => 'var(--color-text)' ];
$groundRestores = [
	'.nino-alert'																=> $groundPage,
	'.nino-modal'																=> $groundPage,
	'.nino-nav-burger input:checked ~ .nino-nav-content'	=> $groundPage,
	'.nino-localepicker-wrap input:checked + div'				=> $groundPage,
	'.nino-cover--dim'													=> $groundSource['black'],
	'.nino-img-background--dim'									=> $groundSource['black'],
	'.nino-toast'																=> $groundSource['black'],
	'.nino-alert--success'														=> [ 'bg' => 'var(--color-success)', 'text' => 'var(--color-success-text)' ],
	'.nino-alert--error'														=> [ 'bg' => 'var(--color-error)', 'text' => 'var(--color-error-text)' ],
	'.nino-alert--warning'														=> [ 'bg' => 'var(--color-warning)', 'text' => 'var(--color-warning-text)' ],
	'.nino-toast--success'														=> [ 'bg' => 'var(--color-success)', 'text' => 'var(--color-success-text)' ],
	'.nino-toast--error'														=> [ 'bg' => 'var(--color-error)', 'text' => 'var(--color-error-text)' ],
	'.nino-badge--success'													=> [ 'bg' => 'var(--color-success)', 'text' => 'var(--color-success-text)' ],
	'.nino-badge--error'														=> [ 'bg' => 'var(--color-error)', 'text' => 'var(--color-error-text)' ],
	'.nino-form.nino-is-success .nino-form-message'		=> [ 'bg' => 'var(--color-success)', 'text' => 'var(--color-success-text)' ],
	'.nino-form.nino-is-error .nino-form-message'		=> [ 'bg' => 'var(--color-error)', 'text' => 'var(--color-error-text)' ],
	'.nino-table--striped tbody tr:nth-child(even)'			=> $groundSource['alt'],
];
$groundWrong = [];
foreach( $groundRestores as $groundSelector => $groundPair ) {
	if( $groundSets( $groundOf( $groundSelector ), '--color-ground', $groundPair['bg'] ) === false )
		$groundWrong[] = $groundSelector. ' does not set --color-ground to '. $groundPair['bg'];
	if( $groundSets( $groundOf( $groundSelector ), '--color-ink', $groundPair['text'] ) === false )
		$groundWrong[] = $groundSelector. ' does not set --color-ink to '. $groundPair['text'];
}
// ...and there is no other rule outside the grounds that sets a ground without being in the list
$groundOthers = [];
foreach( $groundEach as $groundSelector => $groundBodies )
	if( preg_match( '/(?<![\w-])--color-ground\s*:/', implode( ' ', $groundBodies ) ) === 1
		&& preg_match( '/^(?::root|\.nino-section|\.nino-(?:section|grid|card)--[a-z-]+)$/', (string) $groundSelector ) !== 1 && isset( $groundRestores[$groundSelector] ) === false )
		$groundOthers[] = (string) $groundSelector;
check( 'what puts a ground back inside a section or paints a surface of its own - an alert, a toast, a badge and the message of a form with their status surfaces, a modal, the open burger menu and language picker, a dimmed cover and a dimmed picture, striped rows - also sets the pair it paints, and nothing else does'
	. ( $groundWrong === [] ? '' : ' - '. implode( '; ', $groundWrong ) ). ( $groundOthers === [] ? '' : ' - not in the list: '. implode( ', ', $groundOthers ) ), $groundWrong === [] && $groundOthers === [] );

/*	The status colours (Design concept 3.4): a status is a surface with its own ink, and the three surfaces are roles declared once, in :root. Nothing
	in a rule paints one with a colour of its own - the three literals of the roles are the only places they are written	*/
check( 'the status colours are painted by their roles: the literals of --color-error, --color-success and --color-warning are written once each, in :root, and their ink is the brand\'s',
	$hookValue( $hookRoot, '--color-error' ) === '#f44336' && $hookValue( $hookRoot, '--color-success' ) === '#4caf50' && $hookValue( $hookRoot, '--color-warning' ) === '#b45309'
	&& substr_count( $hookCss, '#f44336' ) === 1 && substr_count( $hookCss, '#4caf50' ) === 1 && substr_count( $hookCss, '#b45309' ) === 1
	&& array_filter( [ 'error', 'success', 'warning' ], static fn( string $status ): bool => $hookValue( $hookRoot, '--color-'. $status. '-text' ) !== 'var(--color-primary-text)' ) === [] );

echo "\n";


// --- Modules\Components ----------------------------------------------------

echo "Modules\\Components - the registry, the sources, the stacks\n";

// What init() registers before a page is drawn - the sandbox never runs it
\Nino\Modules\Components::init( $appData );
\Nino\Modules\Template::init( $appData );
\Nino\Modules\Elements::init( $appData );

// The registry the module's own init() fills, read before the test adds to it
check( 'the module registers the components of the kernel in the order a column offers them: a title and a subtitle for each of the three places, section, card and hero, then the text, the card, the button, the image, the html and the spacer', array_keys( \Nino\Modules\Components::components( $appData ) ) === [ 'title', 'subtitle', 'card-title', 'card-subtitle', 'hero-title', 'hero-subtitle', 'text', 'card', 'button', 'image', 'html', 'spacer' ] );
check( '...and a tool is answered in that order: the registry as json is an object keyed by name, which read again lists the same twelve in the same order',
	str_starts_with( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), '{"title":{' ) === true
	&& array_keys( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true ) ) === [ 'title', 'subtitle', 'card-title', 'card-subtitle', 'hero-title', 'hero-subtitle', 'text', 'card', 'button', 'image', 'html', 'spacer' ] );
check( '...and its stacks', array_keys( \Nino\Modules\Components::stacks( $appData ) ) === [ 'stack', 'slider', 'filter', 'list' ] );
check( 'a registered schema is complete and carries its label in both languages', \Nino\Modules\Components::components( $appData )['title']['label'] === [ 'en_US' => 'Title (Section)', 'de_DE' => 'Titel (Sektion)' ]
	&& \Nino\Modules\Components::components( $appData )['title']['source'] === 'text'
	&& \Nino\Modules\Components::components( $appData )['title']['preview'] === 'title'
	&& \Nino\Modules\Components::components( $appData )['title']['loop'] === true
	&& array_keys( \Nino\Modules\Components::components( $appData )['title']['attributes'] ) === [ 'tag', 'style' ] );
check( '...the six titles are labelled by what they are and where they stand, and the card by its name, in both languages',
	array_map( static fn( array $schema ): array => $schema['label'], array_intersect_key( \Nino\Modules\Components::components( $appData ), array_flip( [ 'title', 'subtitle', 'card-title', 'card-subtitle', 'hero-title', 'hero-subtitle', 'card' ] ) ) ) === [
		'title'			=> [ 'en_US' => 'Title (Section)', 'de_DE' => 'Titel (Sektion)' ],
		'subtitle'		=> [ 'en_US' => 'Subtitle (Section)', 'de_DE' => 'Untertitel (Sektion)' ],
		'card-title'	=> [ 'en_US' => 'Title (Card)', 'de_DE' => 'Titel (Karte)' ],
		'card-subtitle'	=> [ 'en_US' => 'Subtitle (Card)', 'de_DE' => 'Untertitel (Karte)' ],
		'hero-title'	=> [ 'en_US' => 'Title (Hero)', 'de_DE' => 'Titel (Hero)' ],
		'hero-subtitle'	=> [ 'en_US' => 'Subtitle (Hero)', 'de_DE' => 'Untertitel (Hero)' ],
		'card'			=> [ 'en_US' => 'Card', 'de_DE' => 'Karte' ],
	] );
check( 'a stack\'s schema says whether it draws a grid', \Nino\Modules\Components::stacks( $appData )['stack']['grid'] === true
	&& \Nino\Modules\Components::stacks( $appData )['slider']['grid'] === false
	&& \Nino\Modules\Components::stacks( $appData )['filter']['grid'] === true
	&& \Nino\Modules\Components::stacks( $appData )['list']['grid'] === false );
check( 'every component and stack of the kernel is a shortcode', array_diff( [ 'title', 'subtitle', 'card-title', 'card-subtitle', 'hero-title', 'hero-subtitle', 'text', 'image', 'button', 'html', 'spacer', 'card', 'stack', 'slider', 'filter', 'list' ], \Nino\Html::shortcodes( $appData ) ) === [] );
\Nino\Modules\Components::init( $appData );
check( 'the module registers again without registering twice - a second init leaves one callback per shortcode', count( \Nino\Callbacks::registered( $appData, '/nino/html/shortcode/title' ) ) === 1
	&& count( \Nino\Callbacks::registered( $appData, '/nino/html/shortcode/image' ) ) === 1 );

// Defaults come from the schema and nowhere else, as the strings a shortcode carries
check( 'defaults() are the schema\'s, as strings, with the class every component takes', \Nino\Modules\Components::defaults( $appData, 'title' ) === [ 'tag' => 'h3', 'style' => '', 'class' => '' ]
	&& \Nino\Modules\Components::defaults( $appData, 'text' )['format'] === 'blocks'
	&& \Nino\Modules\Components::defaults( $appData, 'spacer' ) === [ 'size' => '2', 'class' => '' ] );
check( '...a stack has those of the loop, of its grid where it has one, and its own - and none that says what a cell looks like: the card is a component in its content', \Nino\Modules\Components::defaults( $appData, 'stack', true ) === [ 'sort' => '', 'offset' => '0', 'limit' => '0', 'query' => '', 'callback' => '', 'locale' => '', 'id' => '', 'cols' => '100 50 33', 'gap' => '2', 'autoheight' => '0', 'class' => '' ]
	&& isset( \Nino\Modules\Components::defaults( $appData, 'slider', true )['cols'] ) === false
	&& array_filter( [ 'stack', 'slider', 'filter', 'list' ], static fn( string $name ): bool => array_key_exists( 'card', \Nino\Modules\Components::defaults( $appData, $name, true ) ) === true ) === []
	&& \Nino\Modules\Components::defaults( $appData, 'slider', true )['width'] === '75%'
	&& \Nino\Modules\Components::defaults( $appData, 'filter', true )['autoheight'] === '0' );
check( '...and nothing for a name that is not registered', \Nino\Modules\Components::defaults( $appData, 'nope' ) === [] && \Nino\Modules\Components::defaults( $appData, 'title', true ) === [] );

// The schema is checked where the component is registered
// php 8.4 adds a deprecation of its own to every E_USER_ERROR: not what is asked about here
$errorsOf = static fn( callable $run ): array => array_values( array_filter( ninoErrors( $run ), static fn( array $error ): bool => $error[0] !== E_DEPRECATED ) );
$noop = static fn( array &$appData, array $args ): string => '';
$goodSchema = [ 'label' => 'Probe', 'source' => 'text', 'attributes' => [ 'tone' => [ 'type' => 'select', 'options' => [ 'a', 'b' ], 'default' => 'a' ] ] ];
// An icon is the parts of a 24 by 24 picture, each a tag and the attributes of that tag
$goodIcon = [ [ 'path', [ 'd' => 'M6 12h12' ] ] ];
$withIcon = static fn( mixed $icon ): array => [ 'label' => 'x', 'source' => 'text', 'icon' => $icon ];
$badSchemas = [
	'a name that is no slug'										=> [ 'Not A Slug', $goodSchema, 'a slug' ],
	'a schema without a label'									=> [ 'nolabel', [ 'source' => 'text' ], '"label"' ],
	'a label with an empty language'						=> [ 'emptylabel', [ 'label' => [ 'en_US' => '' ], 'source' => 'text' ], '"label"' ],
	'a source that is none of the five'					=> [ 'weirdsource', [ 'label' => 'x', 'source' => 'weird' ], '"source" must be one of text, image, href, content, none' ],
	'a preview that is not one of the pictures'	=> [ 'weirdpreview', [ 'label' => 'x', 'source' => 'text', 'preview' => 'sparkles' ], '"preview"' ],
	'an attribute of an unknown type'						=> [ 'unknowntype', [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'tone' => [ 'type' => 'color', 'default' => '#fff' ] ] ], 'unknown type "color"' ],
	'a select without options'									=> [ 'nooptions', [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'tone' => [ 'type' => 'select', 'default' => 'a' ] ] ], 'needs "options"' ],
	'a select whose default is not an option'		=> [ 'baddefault', [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'tone' => [ 'type' => 'select', 'options' => [ 'a' ], 'default' => 'z' ] ] ], 'not one of the options' ],
	'an attribute without a default'						=> [ 'nodefault', [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'tone' => [ 'type' => 'string' ] ] ], 'no "default"' ],
	'an int outside its own bounds'							=> [ 'badint', [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'n' => [ 'type' => 'int', 'min' => 1, 'max' => 5, 'default' => 9 ] ] ], 'within its bounds' ],
	'a bool whose default is no bool'						=> [ 'badbool', [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'on' => [ 'type' => 'bool', 'default' => 'yes' ] ] ], 'true or false' ],
	'an attribute the kernel reads itself'			=> [ 'reserved', [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'content' => [ 'type' => 'string', 'default' => '' ] ] ], 'reads itself' ],
	'an attribute that is no identifier'				=> [ 'badname', [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'Not-Camel' => [ 'type' => 'string', 'default' => '' ] ] ], 'lowerCamel' ],
	'a place that is none of the three'				=> [ 'badplace', [ 'label' => 'x', 'source' => 'text', 'in' => [ 'page' ] ], '"in" lists only section, card, hero' ],
	'a place that was the cell of a loop'			=> [ 'cellplace', [ 'label' => 'x', 'source' => 'text', 'in' => [ 'cell' ] ], '"in" lists only section, card, hero' ],
	'a place twice'												=> [ 'twiceplace', [ 'label' => 'x', 'source' => 'text', 'in' => [ 'card', 'card' ] ], '"in" names a place twice' ],
	'no place at all'											=> [ 'noplace', [ 'label' => 'x', 'source' => 'text', 'in' => [] ], '"in" must be a non-empty list' ],
	'a place that is no list'							=> [ 'wordplace', [ 'label' => 'x', 'source' => 'text', 'in' => 'card' ], '"in" must be a non-empty list' ],
	'a holding of a place that is none'		=> [ 'badholds', [ 'label' => 'x', 'source' => 'content', 'holds' => 'page' ], '"holds" names a place: section, card or hero' ],
	'a holding of a list of places'				=> [ 'listholds', [ 'label' => 'x', 'source' => 'content', 'holds' => [ 'card' ] ], '"holds" names a place' ],
	'a holding that is a number'					=> [ 'intholds', [ 'label' => 'x', 'source' => 'content', 'holds' => 5 ], '"holds" names a place' ],
	'a holding that is empty'							=> [ 'emptyholds', [ 'label' => 'x', 'source' => 'content', 'holds' => '' ], '"holds" names a place' ],
	'a holding of a source that is not the content'	=> [ 'textholds', [ 'label' => 'x', 'source' => 'text', 'holds' => 'card' ], '"holds" needs the source content' ],
	'a drawing with a character outside its set'	=> [ 'baddraws', [ 'label' => 'x', 'source' => 'text', 'draws' => 'p > .x' ], '"draws" must be a non-empty string of at most 64 characters' ],
	'a drawing of 65 characters'					=> [ 'longdraws', [ 'label' => 'x', 'source' => 'text', 'draws' => 'p .'. str_repeat( 'x', 62 ) ], '"draws" must be a non-empty string of at most 64 characters' ],
	'an empty drawing'								=> [ 'emptydraws', [ 'label' => 'x', 'source' => 'text', 'draws' => '' ], '"draws" must be a non-empty string' ],
	'a drawing with a placeholder no attribute has'	=> [ 'lostdraws', [ 'label' => 'x', 'source' => 'text', 'draws' => '{tag} .x' ], '"draws" has the placeholder {tag}' ],
	'a drawing with a brace that is none'			=> [ 'bracedraws', [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'tone' => [ 'type' => 'string', 'default' => '' ] ], 'draws' => 'p .x{tone}}' ], '"draws" has a brace' ],
	'an icon that is no list'						=> [ 'wordicon', $withIcon( 'M6 12h12' ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon that is a map'							=> [ 'mapicon', $withIcon( [ 'main' => $goodIcon[0] ] ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon with a gap in its numbering'			=> [ 'gapicon', $withIcon( [ 0 => $goodIcon[0], 2 => $goodIcon[0] ] ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon of no part'							=> [ 'emptyicon', $withIcon( [] ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon part that is no list'					=> [ 'wordparticon', $withIcon( [ 'path' ] ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon part that is a map'					=> [ 'mapparticon', $withIcon( [ [ 'tag' => 'path', 'attributes' => [ 'd' => 'M6 12h12' ] ] ] ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon part that is a tag alone'				=> [ 'tagicon', $withIcon( [ [ 'path' ] ] ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon part of three'							=> [ 'threeicon', $withIcon( [ [ 'path', [ 'd' => 'M6 12h12' ], 'x' ] ] ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon part whose tag is no string'			=> [ 'numbertagicon', $withIcon( [ [ 5, [ 'd' => 'M6 12h12' ] ] ] ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon part whose attributes are no map'		=> [ 'wordattricon', $withIcon( [ [ 'path', 'M6 12h12' ] ] ), '"icon" is a list of parts, each a tag and its attributes' ],
	'an icon tag that is none of the seven'			=> [ 'scripticon', $withIcon( [ $goodIcon[0], [ 'script', [ 'd' => 'M6 12h12' ] ] ] ), '"icon" part 2 names "script", which is no tag of an icon' ],
	'an icon tag in the wrong case'					=> [ 'caseicon', $withIcon( [ [ 'Path', [ 'd' => 'M6 12h12' ] ] ] ), '"icon" part 1 names "Path", which is no tag of an icon' ],
	'an icon attribute that is none of its tag'		=> [ 'clickicon', $withIcon( [ [ 'circle', [ 'cx' => '9', 'cy' => '9', 'r' => '2', 'onclick' => 'alert(1)' ] ] ] ), '"icon" part 1: "onclick" is none of a circle' ],
	'an icon attribute of another tag'				=> [ 'radiusicon', $withIcon( [ [ 'path', [ 'd' => 'M6 12h12', 'r' => '2' ] ] ] ), '"icon" part 1: "r" is none of a path' ],
	'an icon ellipse with the radius of a circle'	=> [ 'ellipseicon', $withIcon( [ [ 'ellipse', [ 'cx' => '9', 'cy' => '9', 'rx' => '2', 'ry' => '1', 'r' => '2' ] ] ] ), '"icon" part 1: "r" is none of an ellipse' ],
	'an icon attribute its tag needs, left out'		=> [ 'lostattricon', $withIcon( [ [ 'circle', [ 'cx' => '9', 'cy' => '9' ] ] ] ), '"icon" part 1: a circle needs "r"' ],
	'an icon rect without its width'				=> [ 'widthicon', $withIcon( [ [ 'rect', [ 'x' => '3', 'y' => '3', 'height' => '18', 'rx' => '2' ] ] ] ), '"icon" part 1: a rect needs "width"' ],
	'an icon ellipse without a radius'				=> [ 'lostradiusicon', $withIcon( [ [ 'ellipse', [ 'cx' => '9', 'cy' => '9', 'rx' => '2' ] ] ] ), '"icon" part 1: an ellipse needs "ry"' ],
	'an icon value that is no string'				=> [ 'intvalueicon', $withIcon( [ [ 'circle', [ 'cx' => 9, 'cy' => '9', 'r' => '2' ] ] ] ), '"icon" part 1: "cx" is not a number' ],
	'an icon number with a unit'					=> [ 'unitvalueicon', $withIcon( [ [ 'circle', [ 'cx' => '9', 'cy' => '9', 'r' => '2px' ] ] ] ), '"icon" part 1: "r" is not a number' ],
	'an icon path with a character outside its set'	=> [ 'badpathicon', $withIcon( [ $goodIcon[0], $goodIcon[0], [ 'path', [ 'd' => 'M6 12h12"' ] ] ] ), '"icon" part 3: "d" is not a path' ],
	'an icon path with a line break at its end'		=> [ 'newlineicon', $withIcon( [ [ 'path', [ 'd' => "M6 12h12\n" ] ] ] ), '"icon" part 1: "d" is not a path' ],
	'an icon list of points with a letter in it'	=> [ 'badpointsicon', $withIcon( [ [ 'polyline', [ 'points' => '3 3 a' ] ] ] ), '"icon" part 1: "points" is not a list of points' ],
	'an icon number with a line break at its end'	=> [ 'lfnumbericon', $withIcon( [ [ 'circle', [ 'cx' => "9\n", 'cy' => '9', 'r' => '2' ] ] ] ), '"icon" part 1: "cx" is not a number' ],
	'an icon number in exponent notation'			=> [ 'expnumbericon', $withIcon( [ [ 'circle', [ 'cx' => '9', 'cy' => '9', 'r' => '1e1' ] ] ] ), '"icon" part 1: "r" is not a number' ],
	'an icon list of points with a line break at its end'	=> [ 'lfpointsicon', $withIcon( [ [ 'polyline', [ 'points' => "3 3\n" ] ] ] ), '"icon" part 1: "points" is not a list of points' ],
	'an icon number of 13 characters'				=> [ 'longnumbericon', $withIcon( [ [ 'circle', [ 'cx' => '9', 'cy' => '9', 'r' => '-1234567.1234' ] ] ] ), '"icon" part 1: "r" is longer than 12 characters' ],
	'an icon path of 513 characters'				=> [ 'longpathicon', $withIcon( [ [ 'path', [ 'd' => 'M'. str_repeat( '1', 512 ) ] ] ] ), '"icon" part 1: "d" is longer than 512 characters' ],
	'an icon list of points of 257 characters'		=> [ 'longpointsicon', $withIcon( [ [ 'polygon', [ 'points' => str_repeat( '1', 257 ) ] ] ] ), '"icon" part 1: "points" is longer than 256 characters' ],
	'an icon of 13 parts'							=> [ 'manyicon', $withIcon( array_fill( 0, 13, $goodIcon[0] ) ), '"icon" holds more than 12 parts' ],
	'an icon of 2049 characters in all'				=> [ 'heavyicon', $withIcon( array_merge( array_fill( 0, 4, [ 'path', [ 'd' => 'M'. str_repeat( '1', 511 ) ] ] ), [ [ 'path', [ 'd' => 'M' ] ] ] ) ), '"icon" is longer than 2048 characters' ],
];
foreach( $badSchemas as $what => [ $name, $schema, $why ] ) {
	$schemaErrors = $errorsOf( function() use ( &$appData, $noop, $name, $schema ) { \Nino\Modules\Components::addComponent( $appData, $name, $noop, $schema ); } );
	check( 'addComponent() refuses '. $what. ' with an E_USER_ERROR that says why, and registers nothing', count( $schemaErrors ) === 1 && $schemaErrors[0][0] === E_USER_ERROR
		&& str_contains( $schemaErrors[0][1], $why ) === true && isset( \Nino\Modules\Components::components( $appData )[$name] ) === false );
}
$schemaErrors = $errorsOf( function() use ( &$appData, $noop ) { \Nino\Modules\Components::addStack( $appData, 'badstack', $noop, [ 'label' => 'x', 'grid' => 'yes' ] ); } );
check( 'addStack() refuses a stack whose "grid" is no bool', count( $schemaErrors ) === 1 && $schemaErrors[0][0] === E_USER_ERROR && isset( \Nino\Modules\Components::stacks( $appData )['badstack'] ) === false );
$schemaErrors = $errorsOf( function() use ( &$appData, $noop ) { \Nino\Modules\Components::addStack( $appData, 'loopattr', $noop, [ 'label' => 'x', 'attributes' => [ 'sort' => [ 'type' => 'string', 'default' => '' ] ] ] ); } );
check( '...and one that declares an attribute of the loop as its own', count( $schemaErrors ) === 1 && str_contains( $schemaErrors[0][1], 'reads itself' ) === true );
$schemaErrors = $errorsOf( function() use ( &$appData, $noop ) { \Nino\Modules\Components::addStack( $appData, 'badassets', $noop, [ 'label' => 'x', 'assets' => [ 'css' => [ '../x.css' ] ] ] ); } );
check( '...and one whose assets climb out of the project', count( $schemaErrors ) === 1 && str_contains( $schemaErrors[0][1], 'assets' ) === true );
check( 'the registry was left alone by every refusal', array_keys( \Nino\Modules\Components::components( $appData ) ) === [ 'title', 'subtitle', 'card-title', 'card-subtitle', 'hero-title', 'hero-subtitle', 'text', 'card', 'button', 'image', 'html', 'spacer' ]
	&& array_keys( \Nino\Modules\Components::stacks( $appData ) ) === [ 'stack', 'slider', 'filter', 'list' ] );
check( 'a schema that validates is returned complete, a stack with its assets', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'Rows', 'grid' => true, 'assets' => [ 'css' => [ '/features/Rows/assets/rows.css' ] ] ], true ) === [
	'label' => 'Rows', 'grid' => true, 'preview' => 'cells', 'attributes' => [], 'assets' => [ 'css' => [ '/features/Rows/assets/rows.css' ] ],
] );
$placed = [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'tag' => [ 'type' => 'select', 'options' => [ 'div', 'h3' ], 'default' => 'h3' ] ] ];
check( 'validate() takes the places a component stands in and what it draws, and carries them in the clean schema; a schema that says neither has neither',
	( \Nino\Modules\Components::validate( 'probe', $placed + [ 'in' => [ 'section', 'card', 'hero' ], 'draws' => '{tag} .nino-probe' ] )['in'] ?? null ) === [ 'section', 'card', 'hero' ]
	&& ( \Nino\Modules\Components::validate( 'probe', $placed + [ 'in' => [ 'card' ], 'draws' => '{tag} .nino-probe' ] )['draws'] ?? null ) === '{tag} .nino-probe'
	&& ( \Nino\Modules\Components::validate( 'probe', $placed + [ 'draws' => 'p .'. str_repeat( 'x', 61 ) ] )['draws'] ?? null ) === 'p .'. str_repeat( 'x', 61 )
	&& array_key_exists( 'in', \Nino\Modules\Components::validate( 'probe', $placed ) ) === false && array_key_exists( 'draws', \Nino\Modules\Components::validate( 'probe', $placed ) ) === false );
check( '...and gives the sentence of the first thing wrong with them: a place outside the three, a place twice, none, not a list, a drawing outside its characters, empty, too long, with a placeholder nobody declares, with a brace that is none',
	array_filter( [
		[ 'in', [ 'page' ] ], [ 'in', [ 'cell' ] ], [ 'in', [ 'card', 'card' ] ], [ 'in', [] ], [ 'in', 'card' ], [ 'in', [ 'a' => 'card' ] ], [ 'in', [ 0 ] ], [ 'in', [ [ 'card' ] ] ],
		[ 'draws', 'P .x' ], [ 'draws', 'p > .x' ], [ 'draws', 'p .x_y' ], [ 'draws', '' ], [ 'draws', 'p .'. str_repeat( 'x', 62 ) ], [ 'draws', 'h{nope} .x' ], [ 'draws', 'h{} .x' ], [ 'draws', '{{tag}}' ], [ 'draws', 'p .x{' ], [ 'draws', 5 ], [ 'draws', [ 'p' ] ],
	], static fn( array $wrong ): bool => is_string( \Nino\Modules\Components::validate( 'probe', $placed + [ $wrong[0] => $wrong[1] ] ) ) === false
		|| str_contains( \Nino\Modules\Components::validate( 'probe', $placed + [ $wrong[0] => $wrong[1] ] ), '"'. $wrong[0]. '"' ) === false ) === [] );
check( '...a stack is not a place a component stands in, and holds nothing: its clean schema has none of the three keys',
	array_key_exists( 'in', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'in' => [ 'card' ], 'draws' => 'p .x', 'holds' => 'card' ], true ) ) === false
	&& array_key_exists( 'draws', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'in' => [ 'card' ], 'draws' => 'p .x', 'holds' => 'card' ], true ) ) === false
	&& array_key_exists( 'holds', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'in' => [ 'card' ], 'draws' => 'p .x', 'holds' => 'card' ], true ) ) === false );
// What a component holds: one of the places, which is what its content is the calls of - and only where its source is its content
$holding = [ 'label' => 'x', 'source' => 'content', 'attributes' => [ 'tag' => [ 'type' => 'select', 'options' => [ 'div', 'h3' ], 'default' => 'h3' ] ] ];
$holdsAnswer = static function( array $schema ): string {
	$answer = \Nino\Modules\Components::validate( 'box', $schema );
	return is_string( $answer ) === true ? $answer : '';
};
check( 'validate() takes what a component holds - one of the three places, with the source content - and carries it in the clean schema; a schema that says nothing holds nothing',
	array_filter( [ 'section', 'card', 'hero' ], static fn( string $place ): bool => ( \Nino\Modules\Components::validate( 'box', $holding + [ 'holds' => $place ] )['holds'] ?? null ) !== $place ) === []
	&& array_key_exists( 'holds', \Nino\Modules\Components::validate( 'box', $holding ) ) === false );
check( '...and gives the sentence of the first thing wrong with it: a place that is none (the cell of a loop is not one any more), a list, a number, an empty word - and a source that is not the content',
	array_filter( [ 'page', 'cell', 'Card', [ 'card' ], 5, '', true ], static fn( mixed $wrong ): bool => str_contains( $holdsAnswer( $holding + [ 'holds' => $wrong ] ), '"holds" names a place: section, card or hero' ) === false ) === []
	&& array_filter( [ 'text', 'image', 'href', 'none' ], static fn( string $source ): bool => str_contains( $holdsAnswer( [ 'source' => $source, 'holds' => 'card' ] + $holding ), '"holds" needs the source content' ) === false ) === []
	&& $holdsAnswer( [ 'holds' => 'weird', 'source' => 'text' ] + $holding ) !== '' && str_contains( $holdsAnswer( [ 'holds' => 'weird', 'source' => 'text' ] + $holding ), 'names a place' ) === true );
// An icon: the parts of a 24 by 24 picture that a tool draws with a stroke, which validate() carries into the clean schema as it was given - the refusals are in the table above
$iconAnswer = static fn( mixed $icon ): array|string => \Nino\Modules\Components::validate( 'probe', $placed + [ 'icon' => $icon ] );
$iconSeven = [
	[ 'path', [ 'd' => 'M6 12h12' ] ],
	[ 'circle', [ 'cx' => '9', 'cy' => '9', 'r' => '2' ] ],
	[ 'ellipse', [ 'cx' => '12', 'cy' => '12', 'rx' => '8', 'ry' => '4' ] ],
	[ 'rect', [ 'x' => '3', 'y' => '3', 'width' => '18', 'height' => '18' ] ],
	[ 'line', [ 'x1' => '3', 'y1' => '3', 'x2' => '21', 'y2' => '21' ] ],
	[ 'polyline', [ 'points' => '3 3 12 12 21 3' ] ],
	[ 'polygon', [ 'points' => '12 2.5 22 21.5 2 21.5' ] ],
];
$iconAnyOrder = [ [ 'rect', [ 'height' => '18', 'width' => '18', 'y' => '3', 'x' => '3' ] ], [ 'circle', [ 'r' => '2', 'cy' => '9', 'cx' => '9' ] ] ];
$iconRect = static fn( array $radii ): array => [ [ 'rect', [ 'x' => '3', 'y' => '3', 'width' => '18', 'height' => '18' ] + $radii ] ];
check( 'validate() takes an icon with parts of each of the seven tags and carries it in the clean schema as it was given: part by part, the attributes of a part in the order given, every value the string it was',
	( $iconAnswer( $iconSeven )['icon'] ?? null ) === $iconSeven && ( $iconAnswer( $iconAnyOrder )['icon'] ?? null ) === $iconAnyOrder );
check( '...a schema that says no icon has none, and a rect has both of its radii, one of them or none, where every other tag has all the attributes it takes',
	array_key_exists( 'icon', \Nino\Modules\Components::validate( 'probe', $placed ) ) === false
	&& array_filter( [ [ 'rx' => '2', 'ry' => '2' ], [ 'rx' => '2' ], [ 'ry' => '2' ], [] ], static fn( array $radii ): bool => is_array( $iconAnswer( $iconRect( $radii ) ) ) === false ) === [] );
check( '...up to the limits - 12 parts, a number of 12 characters, a path of 512, a list of points of 256 and 2048 characters in all - of each of which the table above refuses one more',
	is_array( $iconAnswer( array_fill( 0, 12, $goodIcon[0] ) ) ) === true
	&& is_array( $iconAnswer( [ [ 'circle', [ 'cx' => '9', 'cy' => '9', 'r' => '-1234567.123' ] ] ] ) ) === true
	&& is_array( $iconAnswer( [ [ 'path', [ 'd' => 'M'. str_repeat( '1', 511 ) ] ] ] ) ) === true
	&& is_array( $iconAnswer( [ [ 'polygon', [ 'points' => str_repeat( '1', 256 ) ] ] ] ) ) === true
	&& is_array( $iconAnswer( array_fill( 0, 4, [ 'path', [ 'd' => 'M'. str_repeat( '1', 511 ) ] ] ) ) ) === true );
$iconRows = array_filter( $badSchemas, static fn( array $row ): bool => isset( $row[1]['icon'] ) === true );
check( '...and the message of every icon that is refused starts with the name of the component and says that it is the icon, which is how a manifest names what it is refused for',
	$iconRows !== [] && array_filter( $iconRows, static fn( array $row ): bool => str_starts_with( (string) \Nino\Modules\Components::validate( $row[0], $row[1] ), 'component "'. $row[0]. '": "icon" ' ) === false ) === [] );
check( '...a stack has no icon: the key is dropped from its clean schema, a good one and a bad one alike, and the stack is registered all the same',
	array_key_exists( 'icon', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'icon' => $iconSeven ], true ) ) === false
	&& is_array( \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'icon' => 'not an icon' ], true ) ) === true
	&& array_key_exists( 'icon', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'icon' => 'not an icon' ], true ) ) === false
	&& ( static function() use ( &$appData, $noop, $iconSeven ): bool {
		\Nino\Modules\Components::addStack( $appData, 'pictured-rows', $noop, [ 'label' => 'x', 'icon' => $iconSeven ] );
		return isset( \Nino\Modules\Components::stacks( $appData )['pictured-rows'] ) === true && array_key_exists( 'icon', \Nino\Modules\Components::stacks( $appData )['pictured-rows'] ) === false;
	} )() );
// A component registered with an icon hands it on: to a tool that asks the registry for it, and to one that reads the json of the registry
$pictured = [ [ 'circle', [ 'cx' => '12', 'cy' => '12', 'r' => '4' ] ], [ 'path', [ 'd' => 'M4 12h16' ] ], [ 'rect', [ 'rx' => '2', 'x' => '3', 'y' => '3', 'width' => '18', 'height' => '18' ] ] ];
\Nino\Modules\Components::addComponent( $appData, 'pictured', $noop, [ 'label' => 'Pictured', 'source' => 'none', 'icon' => $pictured ] );
check( 'a component registered with an icon carries it in the registry as it was given',
	( \Nino\Modules\Components::components( $appData )['pictured']['icon'] ?? null ) === $pictured
	&& array_key_exists( 'icon', \Nino\Modules\Components::components( $appData )['pictured'] ) === true );
check( '...and in the json of the registry, which a tool reads: the parts are lists and the attributes of a part maps, in the order given',
	str_contains( (string) json_encode( \Nino\Modules\Components::components( $appData )['pictured'] ), '"icon":[["circle",{"cx":"12","cy":"12","r":"4"}],["path",{"d":"M4 12h16"}],["rect",{"rx":"2","x":"3","y":"3","width":"18","height":"18"}]]' ) === true
	&& ( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true ) )['pictured']['icon'] === $pictured );
// Where a tool offers the six titles, and what each of them draws
$kernelPlaces = [ 'title' => [ 'section' ], 'card-title' => [ 'card' ], 'hero-title' => [ 'hero' ], 'subtitle' => [ 'section' ], 'card-subtitle' => [ 'card' ], 'hero-subtitle' => [ 'hero' ] ];
$kernelDraws = [ 'title' => '{tag} .nino-section-title', 'card-title' => '{tag} .nino-card-title', 'hero-title' => '{tag} .nino-hero-title',
	'subtitle' => '{tag} .nino-section-subtitle', 'card-subtitle' => '{tag} .nino-card-subtitle', 'hero-subtitle' => '{tag} .nino-hero-subtitle', 'button' => 'a .nino-btn' ];
// The tags each of the six may be drawn as, with the one it is where the shortcode names none first: a title in a hero is an h2, a section's an h3, a card's an h4
$sixTags = [ 'title' => [ 'h3', [ 'div', 'h3', 'h4' ] ], 'subtitle' => [ 'div', [ 'div', 'p', 'h4', 'h5' ] ], 'card-title' => [ 'h4', [ 'div', 'h3', 'h4' ] ], 'card-subtitle' => [ 'p', [ 'div', 'p', 'h5', 'h6' ] ],
	'hero-title' => [ 'h2', [ 'div', 'h2', 'h3' ] ], 'hero-subtitle' => [ 'p', [ 'div', 'p', 'h3', 'h4' ] ] ];
check( 'the registry says where each of the six titles is offered, and what it draws - the class is the one the component is named for',
	array_filter( $kernelPlaces, static fn( array $places, string $name ): bool => ( \Nino\Modules\Components::components( $appData )[$name]['in'] ?? null ) !== $places, ARRAY_FILTER_USE_BOTH ) === []
	&& array_filter( $kernelDraws, static fn( string $draws, string $name ): bool => ( \Nino\Modules\Components::components( $appData )[$name]['draws'] ?? null ) !== $draws, ARRAY_FILTER_USE_BOTH ) === [] );
check( '...the button draws and is offered everywhere; text, image, html and spacer declare neither, and neither does a stack',
	array_key_exists( 'in', \Nino\Modules\Components::components( $appData )['button'] ) === false
	&& array_filter( [ 'text', 'image', 'html', 'spacer' ], static fn( string $name ): bool => array_key_exists( 'in', \Nino\Modules\Components::components( $appData )[$name] ) === true
		|| array_key_exists( 'draws', \Nino\Modules\Components::components( $appData )[$name] ) === true ) === []
	&& array_filter( \Nino\Modules\Components::stacks( $appData ), static fn( array $stack ): bool => array_key_exists( 'in', $stack ) === true || array_key_exists( 'draws', $stack ) === true ) === [] );
check( '...and every title has a tag of its own - the tags it may be drawn as, and the one it is - and a style, and nothing else',
	array_filter( $sixTags, static fn( array $tags, string $name ): bool => array_keys( \Nino\Modules\Components::components( $appData )[$name]['attributes'] ) !== [ 'tag', 'style' ]
		|| \Nino\Modules\Components::components( $appData )[$name]['attributes']['tag']['type'] !== 'select'
		|| \Nino\Modules\Components::components( $appData )[$name]['attributes']['tag']['options'] !== $tags[1]
		|| \Nino\Modules\Components::components( $appData )[$name]['attributes']['tag']['default'] !== $tags[0]
		|| \Nino\Modules\Components::components( $appData )[$name]['attributes']['tag']['label'] !== [ 'en_US' => 'Tag', 'de_DE' => 'Tag' ], ARRAY_FILTER_USE_BOTH ) === []
	&& array_filter( array_keys( $kernelPlaces ), static fn( string $name ): bool => \Nino\Modules\Components::components( $appData )[$name]['label'] === \Nino\Modules\Components::components( $appData )['title']['label']
		|| \Nino\Modules\Components::components( $appData )[$name]['preview'] !== 'title' || \Nino\Modules\Components::components( $appData )[$name]['source'] !== 'text' ) === [ 'title' ] );
// The icons of the twelve: each component of the kernel is shown by the parts of a 24 by 24 icon of Lucide, which the registry hands to a tool
$kernelTwelve = [ 'title', 'subtitle', 'card-title', 'card-subtitle', 'hero-title', 'hero-subtitle', 'text', 'card', 'button', 'image', 'html', 'spacer' ];
$iconTags = [ 'path' => [ [ 'd' ], [] ], 'circle' => [ [ 'cx', 'cy', 'r' ], [] ], 'ellipse' => [ [ 'cx', 'cy', 'rx', 'ry' ], [] ], 'rect' => [ [ 'x', 'y', 'width', 'height' ], [ 'rx', 'ry' ] ],
	'line' => [ [ 'x1', 'y1', 'x2', 'y2' ], [] ], 'polyline' => [ [ 'points' ], [] ], 'polygon' => [ [ 'points' ], [] ] ];
$iconOf = static fn( string $name ): mixed => \Nino\Modules\Components::components( $appData )[$name]['icon'] ?? null;
$iconWrong = [];
foreach( $kernelTwelve as $iconName ) {
	$iconParts = $iconOf( $iconName );
	if( is_array( $iconParts ) === false || array_is_list( $iconParts ) === false || count( $iconParts ) < 1 || count( $iconParts ) > 5 ) {
		$iconWrong[] = $iconName. ' has no list of one to five parts';
		continue;
	}
	foreach( $iconParts as $iconNumber => $iconPart ) {
		$iconPart = is_array( $iconPart ) === true ? $iconPart : [];
		$iconRule = is_string( $iconPart[0] ?? null ) === true ? ( $iconTags[ $iconPart[0] ] ?? null ) : null;
		$iconAttributes = is_array( $iconPart[1] ?? null ) === true ? $iconPart[1] : null;
		if( $iconRule === null || $iconAttributes === null || array_diff( $iconRule[0], array_keys( $iconAttributes ) ) !== [] || array_diff( array_keys( $iconAttributes ), $iconRule[0], $iconRule[1] ) !== []
			|| array_filter( $iconAttributes, 'is_string' ) !== $iconAttributes )
			$iconWrong[] = $iconName. ' part '. ( $iconNumber + 1 ). ' is no complete part of an icon';
	}
}
check( 'each of the twelve components of the kernel carries an icon: a list of one to five parts, each a tag among the seven and a map of strings that holds the attributes of that tag'. ( $iconWrong === [] ? '' : ' - '. implode( ', ', $iconWrong ) ), $iconWrong === [] );
check( '...the title is drawn by the three paths of the heading of Lucide, the card by a rect and a path, and the image by a rect, a circle and a path',
	$iconOf( 'title' ) === [ [ 'path', [ 'd' => 'M6 12h12' ] ], [ 'path', [ 'd' => 'M6 20V4' ] ], [ 'path', [ 'd' => 'M18 20V4' ] ] ]
	&& array_column( (array) $iconOf( 'card' ), 0 ) === [ 'rect', 'path' ] && array_column( (array) $iconOf( 'image' ), 0 ) === [ 'rect', 'circle', 'path' ] );
$iconKinds = [ 'title', 'subtitle', 'text', 'card', 'button', 'image', 'html', 'spacer' ];
check( '...the three titles are drawn alike and so are the three subtitles, and the title, the subtitle, the text, the card, the button, the image, the html and the spacer each have a picture of their own',
	$iconOf( 'title' ) === $iconOf( 'card-title' ) && $iconOf( 'title' ) === $iconOf( 'hero-title' )
	&& $iconOf( 'subtitle' ) === $iconOf( 'card-subtitle' ) && $iconOf( 'subtitle' ) === $iconOf( 'hero-subtitle' )
	&& count( array_unique( array_map( static fn( string $name ): string => (string) json_encode( $iconOf( $name ) ), $iconKinds ) ) ) === count( $iconKinds ) );
// Which icon of Lucide a component carries shows in the first part of it: the eight icons begin with eight different parts
$iconFirst = [
	'title' => [ 'path', [ 'd' => 'M6 12h12' ] ], 'subtitle' => [ 'path', [ 'd' => 'M12 4v16' ] ], 'text' => [ 'path', [ 'd' => 'M21 5H3' ] ],
	'card' => [ 'rect', [ 'width' => '18', 'height' => '18', 'x' => '3', 'y' => '3', 'rx' => '2' ] ], 'button' => [ 'path', [ 'd' => 'M14 4.1 12 6' ] ],
	'image' => [ 'rect', [ 'width' => '18', 'height' => '18', 'x' => '3', 'y' => '3', 'rx' => '2', 'ry' => '2' ] ], 'html' => [ 'path', [ 'd' => 'm16 18 6-6-6-6' ] ], 'spacer' => [ 'path', [ 'd' => 'm16 16-4 4-4-4' ] ],
];
$iconKindOf = [ 'title' => 'title', 'card-title' => 'title', 'hero-title' => 'title', 'subtitle' => 'subtitle', 'card-subtitle' => 'subtitle', 'hero-subtitle' => 'subtitle',
	'text' => 'text', 'card' => 'card', 'button' => 'button', 'image' => 'image', 'html' => 'html', 'spacer' => 'spacer' ];
check( '...the titles are drawn as the heading of Lucide, the subtitles as its type, the text as lines, the card as a panel, the button as a pointer, the image as a picture, the html as code and the spacer as a separator',
	array_filter( $iconKindOf, static fn( string $kind, string $name ): bool => ( $iconOf( $name )[0] ?? null ) !== $iconFirst[$kind], ARRAY_FILTER_USE_BOTH ) === [] );
check( '...and the json of the registry gives each of the twelve back as it is: the parts as lists and the attributes of a part as maps',
	array_filter( $kernelTwelve, static fn( string $name ): bool => ( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true ) )[$name]['icon'] !== $iconOf( $name ) ) === [] );
// The card: a component that holds components. What the registry says of it, and of nothing else of the kernel
$cardSchema = \Nino\Modules\Components::components( $appData )['card'];
check( 'the card is declared as the registry says: a label in both languages, the source content, the picture of a block, its icon, offered in a section and a hero, drawing the <article> with its class, and holding the components of a card',
	$cardSchema['label'] === [ 'en_US' => 'Card', 'de_DE' => 'Karte' ] && $cardSchema['source'] === 'content' && $cardSchema['loop'] === true && $cardSchema['preview'] === 'block'
	&& $cardSchema['in'] === [ 'section', 'hero' ] && $cardSchema['draws'] === 'article .nino-card' && $cardSchema['holds'] === 'card'
	&& array_keys( $cardSchema ) === [ 'label', 'source', 'loop', 'preview', 'icon', 'attributes', 'in', 'draws', 'holds' ] );
check( '...its attributes are nine, in the order the Builder writes them: the ground, the padding, whether it fills, where its content stands, how it is aligned, the picture, its focus, the dim and the border',
	array_keys( $cardSchema['attributes'] ) === [ 'ground', 'padding', 'fill', 'vertical', 'align', 'background', 'focus', 'dim', 'borderless' ] );
$cardTypes = [ 'ground' => 'select', 'padding' => 'select', 'fill' => 'bool', 'vertical' => 'select', 'align' => 'select', 'background' => 'image', 'focus' => 'select', 'dim' => 'bool', 'borderless' => 'bool' ];
$cardOptions = [
	'ground'		=> [ '', 'counter', 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt' ],
	'padding'		=> [ '', '0', '1', '2', '3', '4', '5', '6' ],
	'vertical'	=> [ '', 'middle', 'bottom', 'last-bottom' ],
	'align'			=> [ '', 'left', 'center', 'right' ],
	'focus'			=> [ '', '1', '2', '3', '4', '5', '6', '7', '8', '9' ],
];
$cardWrong = [];
foreach( $cardSchema['attributes'] as $cardName => $cardAttribute ) {
	if( $cardAttribute['type'] !== $cardTypes[$cardName] )
		$cardWrong[] = $cardName. ' is a '. $cardAttribute['type'];
	if( ( $cardAttribute['options'] ?? null ) !== ( $cardOptions[$cardName] ?? null ) )
		$cardWrong[] = $cardName. ' has other options';
	if( $cardAttribute['default'] !== ( $cardAttribute['type'] === 'bool' ? false : '' ) )
		$cardWrong[] = $cardName. ' has another default';
	foreach( [ 'label', 'hint' ] as $cardWords )
		if( is_array( $cardAttribute[$cardWords] ) === false || array_keys( $cardAttribute[$cardWords] ) !== [ 'en_US', 'de_DE' ] || in_array( '', $cardAttribute[$cardWords], true ) === true )
			$cardWrong[] = $cardName. ' has no '. $cardWords. ' in both languages';
}
check( '...each with its type, its options and its default - the padding a step of the space scale or none, a bool off - and a label and a hint in both languages'. ( $cardWrong === [] ? '' : ' - '. implode( ', ', $cardWrong ) ), $cardWrong === [] );
check( '...the focus is the focus of [image], word for word, and the other labels are the ones of the owner\'s decision',
	$cardSchema['attributes']['focus']['label'] === \Nino\Modules\Components::components( $appData )['image']['attributes']['focus']['label']
	&& $cardSchema['attributes']['focus']['hint'] === \Nino\Modules\Components::components( $appData )['image']['attributes']['focus']['hint']
	&& $cardSchema['attributes']['ground']['label'] === [ 'en_US' => 'Ground', 'de_DE' => 'Fläche' ] && $cardSchema['attributes']['padding']['label'] === [ 'en_US' => 'Padding', 'de_DE' => 'Innenabstand' ]
	&& $cardSchema['attributes']['fill']['label'] === [ 'en_US' => 'Full height', 'de_DE' => 'Volle Höhe' ] && $cardSchema['attributes']['vertical']['label'] === [ 'en_US' => 'Vertical', 'de_DE' => 'Vertikal' ]
	&& $cardSchema['attributes']['align']['label'] === [ 'en_US' => 'Horizontal', 'de_DE' => 'Horizontal' ] && $cardSchema['attributes']['background']['label'] === [ 'en_US' => 'Picture', 'de_DE' => 'Bild' ]
	&& $cardSchema['attributes']['dim']['label'] === [ 'en_US' => 'Dim the picture', 'de_DE' => 'Bild abdunkeln' ] && $cardSchema['attributes']['borderless']['label'] === [ 'en_US' => 'Without border', 'de_DE' => 'Ohne Rahmen' ] );
check( '...the card is the one component of the kernel that holds components, and no stack does',
	array_keys( array_filter( \Nino\Modules\Components::components( $appData ), static fn( array $schema ): bool => array_key_exists( 'holds', $schema ) === true ) ) === [ 'card' ]
	&& array_filter( \Nino\Modules\Components::stacks( $appData ), static fn( array $stack ): bool => array_key_exists( 'holds', $stack ) === true ) === []
	&& \Nino\Modules\Components::defaults( $appData, 'card' ) === [ 'ground' => '', 'padding' => '', 'fill' => '0', 'vertical' => '', 'align' => '', 'background' => '', 'focus' => '', 'dim' => '0', 'borderless' => '0', 'class' => '' ] );
// The variants are factors of the role now (Nino.css), and the hint of the style says so - the one hint all seven share
$styleHint = [ 'en_US' => 'Quieter or louder than the role: the size times the quiet or the loud factor.', 'de_DE' => 'Leiser oder lauter als die Rolle: die Größe mal dem Quiet- oder dem Loud-Faktor.' ];
check( '...and the style of the six titles and of the text says what its variants are in both languages: quieter or louder than the role, the size times the quiet or the loud factor',
	array_filter( [ 'title', 'card-title', 'hero-title', 'subtitle', 'card-subtitle', 'hero-subtitle', 'text' ], static fn( string $name ): bool => ( \Nino\Modules\Components::components( $appData )[$name]['attributes']['style']['hint'] ?? null ) !== $styleHint ) === [] );
check( 'a clean schema validates again as it is - what a manifest hands over after Features::manifest() has read it', \Nino\Modules\Components::validate( 'title', \Nino\Modules\Components::components( $appData )['title'] ) === \Nino\Modules\Components::components( $appData )['title']
	&& \Nino\Modules\Components::validate( 'filter', \Nino\Modules\Components::stacks( $appData )['filter'], true ) === \Nino\Modules\Components::stacks( $appData )['filter'] );
check( '...icon and all: each of the twelve, and a component registered with an icon of its own, validates again as it is, the parts and the maps of the icon in the order they were in',
	array_filter( array_merge( $kernelTwelve, [ 'pictured' ] ), static fn( string $name ): bool => \Nino\Modules\Components::validate( $name, \Nino\Modules\Components::components( $appData )[$name] ) !== \Nino\Modules\Components::components( $appData )[$name] ) === [] );
\Nino\Callbacks::registerCallback( $appData, '/test/removable', static fn( array &$appData, mixed &$args ): string => 'x' );
\Nino\Callbacks::registerCallback( $appData, '/test/removable', static fn( array &$appData, mixed &$args ): string => 'y' );
check( 'Callbacks::removeCallbacks() takes every callable under a name away, and only those', count( \Nino\Callbacks::registered( $appData, '/test/removable' ) ) === 2
	&& ( static function() use ( &$appData ): bool {
		\Nino\Callbacks::removeCallbacks( $appData, '/test/removable' );
		return \Nino\Callbacks::registered( $appData, '/test/removable' ) === [] && count( \Nino\Callbacks::registered( $appData, '/nino/html/shortcode/title' ) ) === 1;
	} )() );

// What the pages below read: an element type with the fields a stack draws, two
// elements - one with a title that tries to be markup and a summary with a fill
// in it - the slots of a hero, and the texts
$appData['/nino/dir'] = '';
\Nino\Elements::insertElementType( $appData, '/services', [
	'title'			=> [ 'type' => 'string', 'locale' => true ],
	'summary'		=> [ 'type' => 'string', 'locale' => true, 'html' => true, 'blocks' => true ],
	'category'	=> [ 'type' => 'string', 'options' => [ 'web', 'app' ] ],
	'image'			=> [ 'type' => 'image', 'width' => 400, 'height' => 300, 'alt' => 'imageAlt' ],
	'imageAlt'	=> [ 'type' => 'string', 'locale' => true ],
] );
\Nino\Elements::insertElement( $appData, '/services/web', [ 'title' => 'Web <b>[x]</b>', 'summary' => 'Hello [[/k]] [button x]', 'category' => 'web', 'image' => 'services/web/image.webp', 'imageAlt' => 'Alt "q"' ], 'de_DE' );
\Nino\Elements::insertElement( $appData, '/services/app', [ 'title' => 'App', 'summary' => 'Two', 'category' => 'app' ], 'de_DE' );
$appData['/nino/html/images']['/template/page-home/hero/background'] = [ 'label' => 'Hero', 'width' => 1600, 'height' => 900, 'filename' => 'bg.1600x900.jpg' ];
$appData['/nino/html/images']['/template/page-home/hero/empty'] = [ 'label' => 'Empty', 'width' => 10, 'height' => 10, 'filename' => null ];
\Nino\Html::addFills( $appData, [
	'/template/page-home/hero/title'			=> 'Hi [there]',
	'/template/page-home/hero/subtitle'		=> 'Sub',
	'/template/page-home/services/title'	=> 'Services',
	'/template/page-home/services/text'		=> '<p>One</p><ul><li>a</li></ul>',
	'/template/page-home/contact/title'		=> 'Contact',
	'/template/page-home/empty/title'			=> '',
	'/_nino/webpage/contact/name'					=> 'Contact us',
	'/_nino/webpage/contact/uri'					=> '/contact',
], '*' );
\Nino\Filesystem::putFileContent( $appData, '/templates/html-header.tpl', '<header>Header</header>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/html-footer.tpl', '<footer>Footer</footer>' );

$render = static function( string $html ) use ( &$appData ): string {
	return \Nino\Html::renderHtml( $appData, $html );
};

// value(): the table of the sources, one row at a time. Outside a stack first
check( 'value(): a key is its text in the current language, escaped, with its [ written as &#91;', \Nino\Modules\Components::value( $appData, '/template/page-home/hero/title', 'text' ) === 'Hi &#91;there]' );
check( 'value(): a key nobody wrote is null - and one that is empty is empty, where a component then renders nothing', \Nino\Modules\Components::value( $appData, '/template/page-home/nope/title', 'text' ) === null
	&& \Nino\Modules\Components::value( $appData, '/template/page-home/empty/title', 'text' ) === '' );
check( 'value(): .id, .uri and a field name mean nothing without a stack', \Nino\Modules\Components::value( $appData, '.id', 'text' ) === null && \Nino\Modules\Components::value( $appData, '.uri', 'text' ) === null
	&& \Nino\Modules\Components::value( $appData, 'title', 'text' ) === null && \Nino\Modules\Components::element( $appData ) === null );
check( 'value(): text="..." is the value instead of the argument - escaped, and its [ written as &#91;', \Nino\Modules\Components::value( $appData, '', 'text', 'plain', '<b>"Go" [now' ) === '&lt;b&gt;&quot;Go&quot; &#91;now'
	&& \Nino\Modules\Components::value( $appData, '/template/page-home/hero/title', 'text', 'plain', 'fixed' ) === 'fixed' );
check( 'value(): a key read as a format is sanitized to it, not escaped', \Nino\Modules\Components::value( $appData, '/template/page-home/services/text', 'text', 'blocks' ) === '<p>One</p><ul><li>a</li></ul>'
	&& \Nino\Modules\Components::value( $appData, '/template/page-home/services/text', 'text', 'inline' ) === 'One a' );
check( 'value(): no argument is nothing, and a component without a source says its piece anyway', \Nino\Modules\Components::value( $appData, '', 'text' ) === null && \Nino\Modules\Components::value( $appData, '', 'none' ) === '' );
check( 'value(): an address passes, a scheme that is none a link may take does not, nor does a protocol-relative one',
	\Nino\Modules\Components::value( $appData, '#top', 'href' ) === '#top'
	&& \Nino\Modules\Components::value( $appData, 'https://example.com/a?b=1&c=2', 'href' ) === 'https://example.com/a?b=1&amp;c=2'
	&& \Nino\Modules\Components::value( $appData, 'mailto:a@example.com', 'href' ) === 'mailto:a@example.com'
	&& \Nino\Modules\Components::value( $appData, 'javascript:alert(1)', 'href' ) === '#'
	&& \Nino\Modules\Components::value( $appData, '//evil.example', 'href' ) === '#'
	&& \Nino\Modules\Components::value( $appData, '/_nino/webpage/contact/uri', 'href' ) === '/contact'
	&& \Nino\Modules\Components::value( $appData, '/not-a-key', 'href' ) === '/not-a-key' );
check( 'value(): a fixed text="..." of an href source is checked like a key', \Nino\Modules\Components::value( $appData, '', 'href', 'plain', 'javascript:alert(1)' ) === '#'
	&& \Nino\Modules\Components::value( $appData, '', 'href', 'plain', '/contact' ) === '/contact' );
check( 'value(): a control character in front of a scheme - a browser drops it before it reads the address - is a #, wherever in the address it stands',
	\Nino\Modules\Components::value( $appData, "\x01javascript:alert(1)", 'href' ) === '#'
	&& \Nino\Modules\Components::value( $appData, "\x1fjavascript:alert(1)", 'href' ) === '#'
	&& \Nino\Modules\Components::value( $appData, "\x08 javascript:alert(1)", 'href' ) === '#'
	&& \Nino\Modules\Components::value( $appData, "/a\x7fb", 'href' ) === '#'
	&& \Nino\Modules\Components::value( $appData, "java\tscript:alert(1)", 'href' ) === '#'
	&& \Nino\Modules\Components::value( $appData, "\x01#top", 'href' ) === '#'
	&& \Nino\Modules\Components::value( $appData, '\\\\evil.example', 'href' ) === '#' );

// A plain value as the panels store it: through Text::sanitizeValue(), with its
// quotes and brackets as entities. A visitor has to read the characters
\Nino\Html::addFills( $appData, [
	'/template/page-home/stored/title'	=> \Nino\Text::sanitizeValue( 'Let\'s talk about "us" [today]', 'plain' ),
	'/template/page-home/stored/link'		=> \Nino\Text::sanitizeValue( '/a?b="c"&d=1', 'plain' ),
], '*' );
check( 'value(): a stored plain value is decoded before it is escaped once, not escaped on top of its entities', \Nino\Modules\Components::value( $appData, '/template/page-home/stored/title', 'text' ) === 'Let&#039;s talk about &quot;us&quot; &#91;today]'
	&& \Nino\Modules\Components::value( $appData, '/template/page-home/stored/link', 'href' ) === '/a?b=&quot;c&quot;&amp;d=1' );
check( '[title] and a [button] label of a stored value show the characters', $render( '[title /template/page-home/stored/title]' ) === '<h3 class="nino-section-title">Let&#039;s talk about &quot;us&quot; &#91;today]</h3>'
	&& $render( '[button /template/page-home/stored/title href="#x"]' ) === '<a class="nino-btn" href="#x">Let&#039;s talk about &quot;us&quot; &#91;today]</a>' );

// Two components written the way a project's own are: one that says what it
// sees, one that reads value() inside a stack
\Nino\Modules\Components::addComponent( $appData, 'probe', static function( array &$appData, array $args ): string {
	$element = \Nino\Modules\Components::element( $appData );
	return $element === null ? 'outside' : 'inside:'. $element['uri']. '#'. $element['id']. '#'. $element['element']['.uri']. '#'. ( $element['model']['title']['type'] ?? '-' );
}, [ 'label' => 'Probe', 'source' => 'none' ] );
\Nino\Modules\Components::addComponent( $appData, 'values', static function( array &$appData, array $args ): string {
	$value = \Nino\Modules\Components::value( $appData, $args['of'], $args['kind'] );
	return '<i>'. ( $value === null ? 'NULL' : $value ). '</i>';
}, [ 'label' => 'Values', 'source' => 'none', 'attributes' => [
	'of'		=> [ 'type' => 'string', 'default' => '' ],
	'kind'	=> [ 'type' => 'select', 'options' => [ 'text', 'image', 'href' ], 'default' => 'text' ],
] ] );

check( 'the loop context: a component in a stack sees its element, its place and the type\'s model', $render( '[stack /services sort="title" gap="1"][probe][/stack]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-1">'
	. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33">inside:/services#0#/services/app#string</div>'
	. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33">inside:/services#1#/services/web#string</div></div>' );
check( '...and after the stack it does not: the context is gone, and a component outside sees none', \Nino\Modules\Components::element( $appData ) === null && $render( '[probe]' ) === 'outside' );

\Nino\Filesystem::putFileContent( $appData, '/templates/probe-stack.tpl', '[stack /services sort="title" limit="1"][probe][/stack]' );
check( 'a stack in a template that a stack includes leaves the outer one as it found it', $render( '[stack /services sort="title" gap="1"][template /templates/probe-stack][probe][/stack]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-1">'
	. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><div class="nino-grid-row nino-stack nino-stack-gap-2"><div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33">inside:/services#0#/services/app#string</div></div>inside:/services#0#/services/app#string</div>'
	. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><div class="nino-grid-row nino-stack nino-stack-gap-2"><div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33">inside:/services#0#/services/app#string</div></div>inside:/services#1#/services/web#string</div></div>'
	&& \Nino\Modules\Components::element( $appData ) === null );

// value() inside a stack: a field after its type, .id, .uri, and what the model does not know
check( 'value(): in a stack, a field is drawn after its type, .id is the place in the loop and .uri the element\'s uri, a name the model does not know is nothing',
	$render( '[stack /services sort="title" offset="1" limit="1" cols="100"][values of="title"][values of="summary"][values of=".id"][values of=".uri"][values of="nope"][values of="category"][values of="image" kind="image"][values of="image"][values of="title" kind="href"][values of=".uri" kind="href"][values of="#top" kind="href"][/stack]' )
	=== '<div class="nino-grid-row nino-stack nino-stack-gap-2"><div class="nino-grid-s-100 nino-grid-m-100 nino-grid-l-100">'
	. '<i>Web &lt;b&gt;&#91;x]&lt;/b&gt;</i><i><p>Hello &#91;&#91;/k]] &#91;button x]</p></i><i>0</i><i>/services/web</i><i>NULL</i><i>web</i>'
	. '<i>services/web/image.webp</i><i>services/web/image.webp</i><i>Web &lt;b&gt;&#91;x]&lt;/b&gt;</i><i>/services/web</i><i>#top</i></div></div>' );

// The components, one by one
check( '[title] is a heading with the class of a section title, a tag and a style', $render( '[title /template/page-home/hero/title tag="h4" style="loud"]' ) === '<h4 class="nino-section-title nino-section-title--loud">Hi &#91;there]</h4>'
	&& $render( '[title /template/page-home/hero/title]' ) === '<h3 class="nino-section-title">Hi &#91;there]</h3>'
	&& $render( '[title /template/page-home/hero/title tag="div"]' ) === '<div class="nino-section-title">Hi &#91;there]</div>' );
check( '[title] takes a class of its own at the end of its classes, escaped', $render( '[title /template/page-home/hero/title class="a b&c"]' ) === '<h3 class="nino-section-title a b&amp;c">Hi &#91;there]</h3>' );
check( '[subtitle] is a div with the class of a subtitle, and the tag it is told to be', $render( '[subtitle /template/page-home/hero/subtitle style="quiet"]' ) === '<div class="nino-section-subtitle nino-section-subtitle--quiet">Sub</div>'
	&& $render( '[subtitle /template/page-home/hero/subtitle tag="p"]' ) === '<p class="nino-section-subtitle">Sub</p>'
	&& $render( '[subtitle /template/page-home/hero/subtitle tag="h5" style="quiet"]' ) === '<h5 class="nino-section-subtitle nino-section-subtitle--quiet">Sub</h5>' );
check( '[card-title] and [hero-title] are headings of their own class, with a tag, a style and a class of their own, as [title] is',
	$render( '[card-title /template/page-home/hero/title]' ) === '<h4 class="nino-card-title">Hi &#91;there]</h4>'
	&& $render( '[card-title /template/page-home/hero/title tag="h3" style="quiet"]' ) === '<h3 class="nino-card-title nino-card-title--quiet">Hi &#91;there]</h3>'
	&& $render( '[card-title /template/page-home/hero/title style="loud" class="a b&c"]' ) === '<h4 class="nino-card-title nino-card-title--loud a b&amp;c">Hi &#91;there]</h4>'
	&& $render( '[hero-title /template/page-home/hero/title]' ) === '<h2 class="nino-hero-title">Hi &#91;there]</h2>'
	&& $render( '[hero-title /template/page-home/hero/title tag="h3" style="loud"]' ) === '<h3 class="nino-hero-title nino-hero-title--loud">Hi &#91;there]</h3>'
	&& $render( '[hero-title /template/page-home/hero/title style="quiet" class="x"]' ) === '<h2 class="nino-hero-title nino-hero-title--quiet x">Hi &#91;there]</h2>' );
check( '[card-subtitle] and [hero-subtitle] are paragraphs of their own class, with a tag, a style and a class of their own, as [subtitle] is',
	$render( '[card-subtitle /template/page-home/hero/subtitle]' ) === '<p class="nino-card-subtitle">Sub</p>'
	&& $render( '[card-subtitle /template/page-home/hero/subtitle style="quiet"]' ) === '<p class="nino-card-subtitle nino-card-subtitle--quiet">Sub</p>'
	&& $render( '[card-subtitle /template/page-home/hero/subtitle tag="h6"]' ) === '<h6 class="nino-card-subtitle">Sub</h6>'
	&& $render( '[hero-subtitle /template/page-home/hero/subtitle]' ) === '<p class="nino-hero-subtitle">Sub</p>'
	&& $render( '[hero-subtitle /template/page-home/hero/subtitle tag="h3"]' ) === '<h3 class="nino-hero-subtitle">Sub</h3>'
	&& $render( '[hero-subtitle /template/page-home/hero/subtitle style="loud" class="x"]' ) === '<p class="nino-hero-subtitle nino-hero-subtitle--loud x">Sub</p>'
	&& $render( '[subtitle /template/page-home/hero/subtitle class="x"]' ) === '<div class="nino-section-subtitle x">Sub</div>' );
// Each of the six draws the class it is named for and the two variants of it, in the tag it is by default and in every tag it offers, and Nino.css
// styles the class and both variants of each - a style that names a class no rule styles would be a choice in a tool that does nothing
$sixClasses = [ 'title' => 'nino-section-title', 'card-title' => 'nino-card-title', 'hero-title' => 'nino-hero-title', 'subtitle' => 'nino-section-subtitle', 'card-subtitle' => 'nino-card-subtitle', 'hero-subtitle' => 'nino-hero-subtitle' ];
$sixWrong = [];
foreach( $sixClasses as $sixName => $sixClass ) {
	foreach( [ '' => '', 'quiet' => '--quiet', 'loud' => '--loud' ] as $sixStyle => $sixModifier ) {
		$sixHtml = $render( '['. $sixName. ' /template/page-home/hero/title style="'. $sixStyle. '"]' );
		$sixExpected = '<'. $sixTags[$sixName][0]. ' class="'. $sixClass. ( $sixModifier !== '' ? ' '. $sixClass. $sixModifier : '' ). '">Hi &#91;there]</'. $sixTags[$sixName][0]. '>';
		if( $sixHtml !== $sixExpected )
			$sixWrong[] = $sixName. ' '. $sixStyle. ': '. $sixHtml;
		if( $sixModifier !== '' && $hookRules( '.'. $sixClass. $sixModifier ) === '' )
			$sixWrong[] = $sixClass. $sixModifier. ' has no rule in Nino.css';
	}
	foreach( $sixTags[$sixName][1] as $sixTag )
		if( $render( '['. $sixName. ' /template/page-home/hero/title tag="'. $sixTag. '"]' ) !== '<'. $sixTag. ' class="'. $sixClass. '">Hi &#91;there]</'. $sixTag. '>' )
			$sixWrong[] = $sixName. ' tag="'. $sixTag. '"';
	if( preg_match( '/(?<![\w-])\.'. preg_quote( $sixClass, '/' ). '\s*[,{]/', $hookCss ) !== 1 )
		$sixWrong[] = $sixClass. ' has no rule in Nino.css';
}
check( 'each of the six titles draws the class it is named for, with its quiet and its loud variant, in the tag it is by default and in every tag it offers, and Nino.css has the class and both variants of each'. ( $sixWrong === [] ? '' : ' - '. implode( ' | ', $sixWrong ) ), $sixWrong === [] );
check( '...a tag or a style outside what a title offers is the default one, and a source that resolves to nothing renders nothing, for each of the six',
	$render( '[card-title /template/page-home/hero/title tag="h9" style="weird"]' ) === '<h4 class="nino-card-title">Hi &#91;there]</h4>'
	&& $render( '[hero-title /template/page-home/hero/title tag="h0" style="weird"]' ) === '<h2 class="nino-hero-title">Hi &#91;there]</h2>'
	&& $render( '[hero-subtitle /template/page-home/hero/subtitle style="weird"]' ) === '<p class="nino-hero-subtitle">Sub</p>'
	&& array_filter( [ 'title', 'card-title', 'hero-title', 'subtitle', 'card-subtitle', 'hero-subtitle' ], static fn( string $name ): bool => $render( '['. $name. ' /template/page-home/nope/title]' ) !== ''
		|| $render( '['. $name. ' /template/page-home/empty/title]' ) !== '' || $render( '['. $name. ']' ) !== '' ) === [] );
// A tag is written into the markup as it stands, so nothing but the options of its select may reach it: another heading level, another case, another element,
// markup, a tag with an attribute behind it and none at all are each the default one - for each of the six
$tagWrong = [];
foreach( $sixTags as $tagName => [ $tagDefault, $tagOptions ] )
	foreach( [ 'h1', 'h2', 'h7', 'H3', 'DIV', 'span', 'script', '<script>', 'h3 onclick=x', 'h3>', '' ] as $tagUnknown )
		if( in_array( $tagUnknown, $tagOptions, true ) === false && $render( '['. $tagName. ' /template/page-home/hero/title tag="'. $tagUnknown. '"]' ) !== '<'. $tagDefault. ' class="'. $sixClasses[$tagName]. '">Hi &#91;there]</'. $tagDefault. '>' )
			$tagWrong[] = $tagName. ' tag="'. $tagUnknown. '"';
check( 'a tag a title does not offer is the default one, for each of the six - another heading level, the wrong case, another element, markup, a tag with an attribute behind it, none - so that nothing but the options of the select reaches the markup'. ( $tagWrong === [] ? '' : ' - '. implode( ', ', $tagWrong ) ), $tagWrong === [] );
check( '[text] keeps the paragraphs and lists of its key and marks the wrapper as rich text', $render( '[text /template/page-home/services/text]' ) === '<div class="nino-section-text nino-richtext"><p>One</p><ul><li>a</li></ul></div>' );
check( '[text format="inline"] sanitizes to that format', $render( '[text /template/page-home/services/text format="inline" style="quiet"]' ) === '<div class="nino-section-text nino-section-text--quiet">One a</div>' );
check( '[spacer] is an empty box with a margin of the size it says', $render( '[spacer size="4"]' ) === '<div class="nino-mt-4"></div>' && $render( '[spacer]' ) === '<div class="nino-mt-2"></div>' );
check( '[html] is its content as rich text, and nothing without content', $render( '[html]<p>Hi</p><script>alert(1)</script>[/html]' ) === '<p>Hi</p>' && $render( '[html][/html]' ) === '' );
check( '[button] is a link drawn as one: label from a key, address from a key, a style', $render( '[button /_nino/webpage/contact/name href="/_nino/webpage/contact/uri" style="primary"]' ) === '<a class="nino-btn nino-btn--primary" href="/contact">Contact us</a>' );
check( '[button] takes a fixed label, a size, a target - and a target that is another window says it is no concern of the opener', $render( '[button text="Go" href="https://example.com" size="big" target="_blank"]' ) === '<a class="nino-btn nino-btn--big" href="https://example.com" target="_blank" rel="noopener">Go</a>' );
check( '[button] with a fixed label reads its first argument as the address', $render( '[button /_nino/webpage/contact/uri text="Mehr"]' ) === '<a class="nino-btn" href="/contact">Mehr</a>' );
check( '[button] with a scheme that is no safe one is a link to nowhere, and its fixed label is escaped', $render( '[button text="<i>[x" href="javascript:alert(1)"]' ) === '<a class="nino-btn" href="#">&lt;i&gt;&#91;x</a>' );

// The card: the <article> of a component that holds components. What stands between its tags is the template's own markup, rendered with the output
check( '[card] renders nothing without content, and the <article> with its class around what it holds',
	$render( '[card][/card]' ) === '' && $render( '[card]  [/card]' ) === '' && $render( '[card ground="alt"][/card]' ) === ''
	&& $render( '[card]x[/card]' ) === '<article class="nino-card">x</article>'
	&& $render( '[card][card-title /template/page-home/hero/title][card-subtitle /template/page-home/hero/subtitle][/card]' ) === '<article class="nino-card"><h4 class="nino-card-title">Hi &#91;there]</h4><p class="nino-card-subtitle">Sub</p></article>' );
check( '...a card whose components all render nothing is an empty <article>, as a section with nothing in it is a section',
	$render( '[card][title /template/page-home/nope/title][/card]' ) === '<article class="nino-card"></article>' );
// Every attribute is its class, and the classes follow the fragment: the ground, the dim, the border, the fill, where the content stands, the alignment, the padding and the class of its own
$cardClasses = [];
foreach( [ 'counter', 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt' ] as $cardGround )
	$cardClasses['ground="'. $cardGround. '"'] = ' nino-card--'. $cardGround;
foreach( [ 'middle', 'bottom', 'last-bottom' ] as $cardVertical )
	$cardClasses['vertical="'. $cardVertical. '"'] = ' nino-card--'. $cardVertical;
foreach( [ 'left', 'center', 'right' ] as $cardAlign )
	$cardClasses['align="'. $cardAlign. '"'] = ' nino-text-'. $cardAlign;
foreach( [ '0', '1', '2', '3', '4', '5', '6' ] as $cardPadding )
	$cardClasses['padding="'. $cardPadding. '"'] = ' nino-p-'. $cardPadding;
$cardClasses['fill="1"'] = ' nino-card--fill';
$cardClasses['dim="1"'] = ' nino-card--dim';
$cardClasses['borderless="1"'] = ' nino-card--borderless';
$cardClasses['class="a b"'] = ' a b';
$cardWrong = [];
foreach( $cardClasses as $cardAttribute => $cardClass )
	if( $render( '[card '. $cardAttribute. ']x[/card]' ) !== '<article class="nino-card'. $cardClass. '">x</article>' )
		$cardWrong[] = $cardAttribute;
check( '[card] writes each of its attributes as the class of it: a ground, where the content stands, the alignment, the padding of every step - the 0 among them - the fill, the dim, the border, and a class of its own'. ( $cardWrong === [] ? '' : ' - not: '. implode( ', ', $cardWrong ) ), $cardWrong === [] && count( $cardClasses ) === 24 );
check( '...all at once, in the order of the fragment, and a class of its own last, escaped',
	$render( '[card ground="alt" padding="3" fill="1" vertical="last-bottom" align="right" dim="1" borderless="1" class="mine"]x[/card]' )
		=== '<article class="nino-card nino-card--alt nino-card--dim nino-card--borderless nino-card--fill nino-card--last-bottom nino-text-right nino-p-3 mine">x</article>'
	&& $render( '[card class="a b&c"]x[/card]' ) === '<article class="nino-card a b&amp;c">x</article>'
	&& $render( '[card ground="counter" class="x"]x[/card]' ) === '<article class="nino-card nino-card--counter x">x</article>' );
check( '...a word outside what an attribute offers is the default, and so is a bool that is none: the class is not written',
	$render( '[card ground="weird" padding="9" vertical="weird" align="weird" fill="maybe" dim="x" borderless="2" focus="12"]x[/card]' ) === '<article class="nino-card">x</article>'
	&& $render( '[card ground="ALT" align="Center" padding="-1"]x[/card]' ) === '<article class="nino-card">x</article>'
	&& $render( '[card ground="alt&quot; onclick=&quot;x"]x[/card]' ) === '<article class="nino-card">x</article>'
	&& $render( '[card bogus="x" onclick="y"]x[/card]' ) === '<article class="nino-card">x</article>'
	&& $render( '[card fill="true" dim="yes" borderless="on"]x[/card]' ) === '<article class="nino-card nino-card--dim nino-card--borderless nino-card--fill">x</article>' );
// The picture behind it
$appData['/nino/html/images']['/template/page-home/hero/withalt'] = [ 'label' => 'With alt', 'width' => 800, 'height' => 600, 'filename' => 'alt.800x600.jpg', 'alt' => [ 'de_DE' => 'Stored "alt" [x]' ] ];
check( '[card] with a picture - a slot by its uri - has the picture in a div of its own before the content, as an img with an empty alt text, and the focus is a class of that div',
	$render( '[card background="/template/page-home/hero/background" focus="3"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-bg nino-img-focus--3"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""></div>x</article>'
	&& $render( '[card background="/template/page-home/hero/background" ground="alt" dim="1"]x[/card]' ) === '<article class="nino-card nino-card--alt nino-card--dim"><div class="nino-card-bg"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""></div>x</article>' );
check( '...the alt text is none even where the slot has one: behind a card the picture is decoration - and [image] of the same slot still says it',
	str_contains( $render( '[card background="/template/page-home/hero/withalt"]x[/card]' ), '<img src="/public/images/alt.800x600.jpg" width="800" height="600" alt="">' ) === true
	&& str_contains( $render( '[image /template/page-home/hero/withalt]' ), 'alt="Stored &quot;alt&quot; &#91;x]"' ) === true );
check( '...no picture, no div: a slot with no file, a slot there is none of, an empty reference and a focus without a picture write none',
	$render( '[card background="/template/page-home/hero/empty" focus="5"]x[/card]' ) === '<article class="nino-card">x</article>'
	&& $render( '[card background="/template/page-home/nope/nope" focus="5"]x[/card]' ) === '<article class="nino-card">x</article>'
	&& $render( '[card background="" focus="5"]x[/card]' ) === '<article class="nino-card">x</article>'
	&& $render( '[card background="image"]x[/card]' ) === '<article class="nino-card">x</article>' );
// In a stack: a card to an element, the cell a div and the card in it, and the element is the context of what the card holds
check( '[card] in a [stack]: one card to an element in the div of the cell, and the components in it see their element - their values escaped as anywhere, the [ of a title written as &#91;',
	$render( '[stack /services sort="title"][card ground="counter"][card-title title][card-subtitle category][/card][/stack]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-2">'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--counter"><h4 class="nino-card-title">App</h4><p class="nino-card-subtitle">app</p></article></div>'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--counter"><h4 class="nino-card-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h4><p class="nino-card-subtitle">web</p></article></div></div>'
	&& str_contains( $render( '[stack /services sort="title" limit="1" offset="1"][card][card-title title][/card][/stack]' ), '&#91;x]' ) === true
	&& str_contains( $render( '[stack /services sort="title" limit="1" offset="1"][card][card-title title][/card][/stack]' ), '[x]' ) === false );
$cardStack = $render( '[stack /services sort="title"][card background="image" focus="2" ground="alt"][card-title title][/card][/stack]' );
check( '...background="image" takes the picture of the element\'s image field, with an empty alt text - the field names one - and an element whose field holds no file has no div',
	str_contains( $cardStack, '<article class="nino-card nino-card--alt"><div class="nino-card-bg nino-img-focus--2"><img src="/public/images/services/web/image.webp" width="400" height="300" alt=""></div><h4 class="nino-card-title">Web' ) === true
	&& substr_count( $cardStack, 'nino-card-bg' ) === 1 && str_contains( $cardStack, '<article class="nino-card nino-card--alt"><h4 class="nino-card-title">App</h4></article>' ) === true
	&& str_contains( $cardStack, 'Alt' ) === false );
check( '...a slot works there as well, a card outside a stack sees no element, and the context is gone after the stack',
	str_contains( $render( '[stack /services limit="1"][card background="/template/page-home/hero/background"][probe][/card][/stack]' ), '<article class="nino-card"><div class="nino-card-bg"><img src="/public/images/bg.1600x900.jpg"' ) === true
	&& $render( '[card][probe][/card]' ) === '<article class="nino-card">outside</article>' && \Nino\Modules\Components::element( $appData ) === null );
check( '...the cards of a stack are drawn the same each time, and each card is the first thing in its cell',
	$render( '[stack /services sort="title"][card fill="1"]x[/card][/stack]' ) === $render( '[stack /services sort="title"][card fill="1"]x[/card][/stack]' )
	&& substr_count( $render( '[stack /services sort="title"][card fill="1"]x[/card][/stack]' ), '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--fill">x</article></div>' ) === 2 );
check( 'the markup of the card and of its picture is a property like that of any component, and what the content holds is never looked at again for a token of the fragment',
	str_contains( (string) \Nino\Modules\Components::$html['card'], '[[background]][[inner]]</article>' ) === true && \Nino\Modules\Components::$html['card-bg'] === '<div class="nino-card-bg[[focus]]">[[img]]</div>'
	&& $render( '[card ground="alt"]x [[class]] [[background]] [[ground]][/card]' ) === '<article class="nino-card nino-card--alt">x [[class]] [[background]] [[ground]]</article>' );

// Whatever a hand-written file gets wrong still renders
check( 'an attribute that is not declared is ignored', $render( '[title /template/page-home/hero/title bogus="x" onclick="y"]' ) === $render( '[title /template/page-home/hero/title]' ) );
check( 'a select outside its options is the default', $render( '[title /template/page-home/hero/title tag="h9" style="weird"]' ) === '<h3 class="nino-section-title">Hi &#91;there]</h3>'
	&& $render( '[spacer size="99"]' ) === '<div class="nino-mt-2"></div>' && $render( '[text /template/page-home/services/text format="nonsense"]' ) === $render( '[text /template/page-home/services/text]' ) );
check( 'a source that resolves to nothing renders nothing - not a heading with nothing in it', $render( '[title /template/page-home/nope/title]' ) === '' && $render( '[subtitle /template/page-home/nope/title]' ) === ''
	&& $render( '[text /template/page-home/nope/title]' ) === '' && $render( '[button /template/page-home/nope/title href="#"]' ) === '' && $render( '[title]' ) === ''
	&& $render( '[title /template/page-home/empty/title]' ) === '' && $render( '[title title]' ) === '' );
check( 'a fill in the first argument is not a thing: the source is the key, not its value', str_contains( $render( '[title [[/template/page-home/hero/title]]]' ), 'nino-section-title' ) === false );

// [image] is a component now, and what it did it still does
check( '[image] of a slot: the same <img> as before', $render( '[image /template/page-home/hero/background alt="A"]' ) === '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="A">' );
check( '[image] as before in the named form, with the alt of the template', $render( '[image uri="/template/page-home/hero/background" alt="B"]' ) === '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="B">' );
check( '[image] with content as before: the content, filled, and nothing for a slot with no file', $render( '[image /template/page-home/hero/background]<meta content="[[src]]" data-size="[[width]]x[[height]]">[/image]' ) === '<meta content="/public/images/bg.1600x900.jpg" data-size="1600x900">'
	&& $render( '[image /template/page-home/hero/empty]<meta content="[[src]]">[/image]' ) === '' && $render( '[image /template/page-home/hero/empty]' ) === '' );
check( '[image] with a focus, a ratio or a class of its own is framed - the frame is what crops', $render( '[image /template/page-home/hero/background focus="5" ratio="16-9" class="x"]' ) === '<div class="nino-image nino-image--16-9 nino-img-focus--5 x"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""></div>'
	&& $render( '[image /template/page-home/hero/background focus="12" ratio="2-1"]' ) === '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="">' );
$image = $render( '[stack /services sort="title" offset="1" limit="1"][image image focus="2"][image /template/page-home/hero/background][image nope][/stack]' );
check( '[image] in a stack: an image field is the file it holds, its size is the field\'s, its alt text the field the type names', str_contains( $image, '<div class="nino-image nino-img-focus--2"><img src="/public/images/services/web/image.webp" width="400" height="300" alt="Alt &quot;q&quot;"></div>' ) === true );
check( '...a slot still works there, and a name that is neither a field nor a slot renders nothing', str_contains( $image, '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="">' ) === true && substr_count( $image, '<img' ) === 2 );
check( '...and an image field with no file renders nothing', substr_count( $render( '[stack /services sort="title" limit="1"][image image][/stack]' ), '<img' ) === 0 );

// A project's own component replaces the one of the kernel, and nothing else answers for it
\Nino\Modules\Components::addComponent( $appData, 'title', static function( array &$appData, array $args ): string {
	return '<'. $args['tag']. ' class="mine">'. $args['value']. '</'. $args['tag']. '>';
}, [ 'label' => 'Title', 'source' => 'text', 'attributes' => [ 'tag' => [ 'type' => 'select', 'options' => [ 'h1', 'h2', 'h3' ], 'default' => 'h3', 'label' => 'Tag' ] ] ] );
check( 'a component registered again replaces the one before it: its renderer, its defaults, its options', $render( '[title /template/page-home/hero/title]' ) === '<h3 class="mine">Hi &#91;there]</h3>'
	&& $render( '[title /template/page-home/hero/title tag="h4"]' ) === '<h3 class="mine">Hi &#91;there]</h3>'
	&& \Nino\Modules\Components::defaults( $appData, 'title' ) === [ 'tag' => 'h3', 'class' => '' ] && count( \Nino\Callbacks::registered( $appData, '/nino/html/shortcode/title' ) ) === 1 );
\Nino\Modules\Components::init( $appData );
check( '...and the kernel takes it back by registering its own again', $render( '[title /template/page-home/hero/title]' ) === '<h3 class="nino-section-title">Hi &#91;there]</h3>' );

// The four stacks of the kernel
check( '[stack] draws a nested grid row, one cell to an element, in the widths of cols, with the gap as a class of the row',
	$render( '[stack /services sort="title" cols="100 50" gap="4" id="svc"][title title tag="h4"][/stack]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-4" id="svc">'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><h4 class="nino-section-title">App</h4></div>'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><h4 class="nino-section-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h4></div></div>' );
// The cell of a stack is the div of the grid, and a card is a component in its content - a property of the content, written once in the template
check( '[stack] draws a div to the cell and the card in it: the stack brings the grid, the content brings the card',
	$render( '[stack /services sort="title" cols="100 50" gap="4" id="svc"][card ground="alt"][title title tag="h4"][/card][/stack]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-4" id="svc">'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--alt"><h4 class="nino-section-title">App</h4></article></div>'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--alt"><h4 class="nino-section-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h4></article></div></div>' );
check( '...a stack has no attribute for what a cell looks like: card="..." is ignored as any attribute that is not declared is, and the cell stays a div whatever the shortcode says',
	array_filter( [ 'card', 'counter', 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'weird', '' ], static fn( string $word ): bool =>
		$render( '[stack /services limit="1" card="'. $word. '"][probe][/stack]' ) !== $render( '[stack /services limit="1"][probe][/stack]' )
		|| $render( '[filter /services limit="1" card="'. $word. '"][probe][/filter]' ) !== $render( '[filter /services limit="1"][probe][/filter]' ) ) === []
	&& str_contains( $render( '[stack /services limit="1" card="alt"][probe][/stack]' ), '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33">' ) === true
	&& str_contains( $render( '[stack /services limit="1" card="alt"][probe][/stack]' ), 'nino-card' ) === false );
check( '...the autoheight group is an attribute of the div, and the id and the gap stay on the row',
	str_contains( $render( '[stack /services limit="1" autoheight="1" id="svc"][card ground="dark"][probe][/card][/stack]' ), '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33 nino-autoheight" data-autoheight-group="svc"><article class="nino-card nino-card--dark">' ) === true
	&& str_starts_with( $render( '[stack /services limit="1" gap="3" id="svc"][card ground="dark"][probe][/card][/stack]' ), '<div class="nino-grid-row nino-stack nino-stack-gap-3" id="svc"><div class="nino-grid-s-100' ) );
$cardFilter = $render( '[filter /services sort="title" by="category" id="f"][card ground="counter"][probe][/card][/filter]' );
check( '[filter] draws the same: the cell is the div that carries the class and the value the filter needs, and the card is in it',
	str_contains( $cardFilter, '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33 nino-filter-item" data-filter-item="app"><article class="nino-card nino-card--counter">inside:/services#0#/services/app#string</article></div>' ) === true
	&& str_contains( $cardFilter, '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33 nino-filter-item" data-filter-item="web"><article class="nino-card nino-card--counter">inside:/services#1#/services/web#string</article></div>' ) === true
	&& substr_count( $cardFilter, '<article' ) === 2 && substr_count( $cardFilter, '</article>' ) === 2 );
check( '[slider] and [list] draw what they drew, and a card is as much a content in them as in a stack',
	$render( '[slider /services sort="title" card="alt"][probe][/slider]' ) === $render( '[slider /services sort="title"][probe][/slider]' )
	&& $render( '[list /services sort="title" card="alt"][probe][/list]' ) === $render( '[list /services sort="title"][probe][/list]' )
	&& str_contains( $render( '[slider /services sort="title" limit="1"][card][probe][/card][/slider]' ), '<li><article class="nino-card">inside:/services#0#/services/app#string</article></li>' ) === true
	&& str_contains( $render( '[list /services sort="title" limit="1"][card][probe][/card][/list]' ), '<li><article class="nino-card">inside:/services#0#/services/app#string</article></li>' ) === true );
$gridAttributes = (array) ( new ReflectionClassConstant( '\Nino\Modules\Components', 'GRID' ) )->getValue();
check( 'the grid of a stack is three attributes - the widths of the cells, the gap between them and whether they are one height - and nothing about a card; the constant of the cards is gone, and the fragment of a cell is a div',
	array_keys( $gridAttributes ) === [ 'cols', 'gap', 'autoheight' ]
	&& ( new ReflectionClass( '\Nino\Modules\Components' ) )->hasConstant( 'CARDS' ) === false
	&& isset( \Nino\Modules\Components::$html['cell'] ) === true && str_starts_with( \Nino\Modules\Components::$html['cell'], '<div' ) === true );
check( '[stack] with autoheight marks every cell as one group, named by the id', substr_count( $render( '[stack /services autoheight="1" id="svc"][probe][/stack]' ), 'nino-autoheight" data-autoheight-group="svc"' ) === 2 );
preg_match_all( '/data-autoheight-group="(stack-\d+)"/', $render( '[stack /services autoheight="1"][probe][/stack]' ), $groups );
preg_match( '/data-autoheight-group="(stack-\d+)"/', $render( '[stack /services autoheight="1"][probe][/stack]' ), $other );
check( '...and a stack without an id gets a group of its own that no other stack shares', count( $groups[1] ) === 2 && $groups[1][0] === $groups[1][1] && ( $other[1] ?? '' ) !== '' && $other[1] !== $groups[1][0] );
check( 'cols that are no widths of the grid are the default of their place, and one value is all three', str_contains( $render( '[stack /services limit="1" cols="100 7 evil&quot;"][probe][/stack]' ), 'nino-grid-s-100 nino-grid-m-7 ' ) === false
	&& str_contains( $render( '[stack /services limit="1" cols="100 7 evil&quot;"][probe][/stack]' ), 'nino-grid-s-100 nino-grid-m-50 nino-grid-l-33' )
	&& str_contains( $render( '[stack /services limit="1" cols="50"][probe][/stack]' ), 'nino-grid-s-50 nino-grid-m-50 nino-grid-l-50' ) );
check( '[stack] of a type with no elements, or of none, is nothing - and so is one with a query nothing matches', $render( '[stack /nothing][probe][/stack]' ) === '' && $render( '[stack][probe][/stack]' ) === ''
	&& $render( '[stack /services query="category=nope"][probe][/stack]' ) === '' );
check( '[stack] reads sort, offset, limit and query as [elements] does', substr_count( $render( '[stack /services sort="-title" limit="1"][title title tag="h4"][/stack]' ), '<h4' ) === 1
	&& str_contains( $render( '[stack /services sort="-title" limit="1"][title title tag="h4"][/stack]' ), 'Web' ) === true
	&& str_contains( $render( '[stack /services sort="-title" offset="1"][title title tag="h4"][/stack]' ), '>App<' ) === true
	&& str_contains( $render( '[stack /services query="category=app"][title title tag="h4"][/stack]' ), '>App<' ) === true
	&& str_contains( $render( '[stack /services query="category=app"][title title tag="h4"][/stack]' ), 'Web' ) === false );
\Nino\Callbacks::registerCallback( $appData, '/test/stack/reverse', static function( array &$appData, array &$hits ): array { return array_reverse( $hits ); } );
check( '...and the callback, which sees the sorted hits before the window is cut, as [elements] does',
	$render( '[elements /services sort="title" callback="/test/stack/reverse" limit="1"][[.id]]:[[title]][/elements]' ) === '0:Web &lt;b&gt;&#91;x]&lt;/b&gt;'
	&& str_contains( $render( '[stack /services sort="title" callback="/test/stack/reverse" limit="1"][title title tag="h4"][/stack]' ), 'Web &lt;b&gt;' ) === true );

check( '[slider] is a .nino-slider around a list of its cells, which is what Nino.ui.js wires up',
	$render( '[slider /services sort="title" width="60%" min="240px" id="sl"][title title tag="h4"][/slider]' ) === '<div class="nino-slider" id="sl" data-slider-width="60%" data-slider-min="240px"><ul>'
		. '<li><h4 class="nino-section-title">App</h4></li><li><h4 class="nino-section-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h4></li></ul></div>' );
check( '...a width that is no size is left out, and a slider with no elements is nothing', $render( '[slider /services sort="title" limit="1" width="12em"][probe][/slider]' ) === '<div class="nino-slider"><ul><li>inside:/services#0#/services/app#string</li></ul></div>'
	&& $render( '[slider /nothing][probe][/slider]' ) === '' );
check( '[list] is a .nino-list of items, in the style it names, with no grid',
	$render( '[list /services sort="title" style="check" id="faq"][title title tag="h4"][/list]' ) === '<ul class="nino-list nino-list--check" id="faq"><li><h4 class="nino-section-title">App</h4></li><li><h4 class="nino-section-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h4></li></ul>'
	&& $render( '[list /services sort="title" limit="1" style="weird"][probe][/list]' ) === '<ul class="nino-list"><li>inside:/services#0#/services/app#string</li></ul>' );
$filter = $render( '[filter /services sort="title" by="category" id="f"][title title tag="h4"][/filter]' );
check( '[filter] is a .nino-filter: the buttons, the first of them for everything, and the cells that carry the value they are filtered by',
	str_starts_with( $filter, '<div class="nino-filter" id="f"><nav class="nino-filter-nav" aria-label="Filter">' )
	&& str_contains( $filter, '<button type="button" class="nino-filter-btn nino-is-active" data-filter-value="" aria-pressed="true">Alle <span class="nino-filter-count">(2)</span></button>' ) === true
	&& str_contains( $filter, '<button type="button" class="nino-filter-btn" data-filter-value="app" aria-pressed="false">app <span class="nino-filter-count">(1)</span></button>' ) === true
	&& str_contains( $filter, '<button type="button" class="nino-filter-btn" data-filter-value="web" aria-pressed="false">web <span class="nino-filter-count">(1)</span></button>' ) === true
	&& str_contains( $filter, '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33 nino-filter-item" data-filter-item="app"><h4 class="nino-section-title">App</h4></div>' ) === true
	&& str_contains( $filter, '</nav><div class="nino-grid-row nino-stack nino-stack-gap-2">' ) === true );
check( '...the label of the first button is the attribute, else the key /template/common/filter/all, else the word of the page\'s language',
	str_contains( $render( '[filter /services by="category" all="Everything"][probe][/filter]' ), 'aria-pressed="true">Everything <span' ) === true
	&& ( static function() use ( &$appData, $render ): bool {
		\Nino\Html::addFills( $appData, [ '/template/common/filter/all' => 'Sämtliche' ], '*' );
		$own = str_contains( $render( '[filter /services by="category"][probe][/filter]' ), 'aria-pressed="true">Sämtliche <span' );
		unset( $appData['./nino/html/fills']['*']['[[/template/common/filter/all]]'] );
		$appData['./nino/locales/current'] = 'en_US';
		$english = str_contains( $render( '[filter /services by="category"][probe][/filter]' ), 'aria-pressed="true">All <span' );
		$appData['./nino/locales/current'] = 'de_DE';
		return $own === true && $english === true;
	} )() );
check( '...and without a field to filter by there are the cells and no row of buttons', str_contains( $render( '[filter /services][probe][/filter]' ), 'nino-filter-nav' ) === false
	&& str_contains( $render( '[filter /services][probe][/filter]' ), 'nino-filter-item' ) === true );

// The page of the Builder, as the Builder feature would write it, rendered by the kernel alone
$home = (string) file_get_contents( __DIR__. '/fixtures/builder-page-home.tpl' );
$page = $render( $home );
$foreign = '<!-- nino:html -->'. "\n". '<section id="map" class="nino-section nino-section--alt">'. "\n". '	<div class="nino-grid-row"><div class="nino-grid-100">[osm lat="48.1" lon="11.5" zoom="12"]</div></div>'. "\n". '</section>'. "\n". '<!-- /nino:html -->';
check( 'a file of the Builder renders by the kernel alone: no shortcode of it is left standing', str_contains( str_replace( $foreign, '', $page ), '[' ) === false );
check( '...the section, the row and the columns are the markup of the file', str_contains( $page, '<section id="hero" class="nino-section nino-section--fullwidth nino-section--black nino-cover nino-cover--dim nino-vpa" data-cover-height="100">' ) === true
	&& str_contains( $page, '<div class="nino-section-bg nino-img-focus--5"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""></div>' ) === true
	&& str_contains( $page, '<div class="nino-grid-s-100 nino-grid-m-100 nino-grid-l-66 nino-text-left">' ) === true
	&& str_contains( $page, '<section id="contact" class="nino-section nino-section--primary nino-text-center">' ) === true
	&& str_starts_with( $page, '<!-- nino:template-name Home -->'. "\n". '<header>Header</header>' ) && str_ends_with( rtrim( $page ), '<footer>Footer</footer>' ) );
check( '...the statics are drawn from their keys: a heading with its style, a button with the address of a key', str_contains( $page, '<h3 class="nino-section-title nino-section-title--loud">Hi &#91;there]</h3>' ) === true
	&& str_contains( $page, '<a class="nino-btn nino-btn--primary" href="/contact">Contact us</a>' ) === true && str_contains( $page, '<a class="nino-btn nino-btn--light" href="/contact">Contact us</a>' ) === true );
check( '...the stack is two cells for the two elements, in the order of sort, with the title escaped and in the tag the file names', substr_count( $page, 'nino-autoheight" data-autoheight-group="stack-' ) === 2
	&& str_contains( $page, '<h3 class="nino-section-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h3>' ) === true && strpos( $page, '>App<' ) < strpos( $page, 'Web &lt;b&gt;' )
	&& str_contains( $page, '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-50 nino-autoheight"' ) === true
	&& str_contains( $page, '<a class="nino-btn" href="/services/web">Mehr</a>' ) === true && str_contains( $page, 'FILLED' ) === false );
check( '...and the block of somebody\'s own is untouched, byte for byte', str_contains( $page, $foreign ) === true );
check( '...and it renders the same each time', preg_replace( '/stack-\d+/', 'stack-N', $render( $home ) ) === preg_replace( '/stack-\d+/', 'stack-N', $page ) );

check( '...a [button] in a stack whose href field holds a control character before a scheme is a #', ( static function() use ( $render, &$appData ): bool {
	\Nino\Elements::insertElementType( $appData, '/links', [ 'title' => [ 'type' => 'string' ], 'link' => [ 'type' => 'string' ] ] );
	\Nino\Elements::insertElement( $appData, '/links/a', [ 'title' => 'A', 'link' => "\x01javascript:alert(1)" ], 'de_DE' );
	\Nino\Elements::insertElement( $appData, '/links/b', [ 'title' => 'B', 'link' => "\x1fjavascript:alert(1)" ], 'de_DE' );
	$out = $render( '[stack /links sort="title"][button title href="link"][/stack]' );
	return substr_count( $out, 'href="#"' ) === 2 && str_contains( $out, 'javascript' ) === false;
} )() );

// The label of the filter button for everything, stored the way the panels store it
\Nino\Html::addFills( $appData, [ '/template/common/filter/all' => \Nino\Text::sanitizeValue( 'All "of" it', 'plain' ) ], '*' );
check( '[filter] says a stored label as the characters it is', str_contains( $render( '[filter /services by="category"][probe][/filter]' ), 'aria-pressed="true">All &quot;of&quot; it <span' ) === true );

// class: every component takes it, and the renderer puts it on its outer element
check( '[spacer] and the four stacks carry the class they are given on their outer element',
	$render( '[spacer class="x"]' ) === '<div class="nino-mt-2 x"></div>'
	&& str_contains( $render( '[stack /services class="x"][probe][/stack]' ), '<div class="nino-grid-row nino-stack nino-stack-gap-2 x">' ) === true
	&& str_contains( $render( '[slider /services class="x"][probe][/slider]' ), '<div class="nino-slider x" data-slider-width="75%"><ul>' ) === true
	&& str_contains( $render( '[filter /services class="x"][probe][/filter]' ), '<div class="nino-filter x">' ) === true
	&& str_contains( $render( '[list /services class="x"][probe][/list]' ), '<ul class="nino-list x"><li>' ) === true
	&& str_contains( $render( '[stack /services class="a b"][probe][/stack]' ), 'stack-gap-2 a b"' ) === true );

// An image's value is the escaped reference, and the picture is looked up by the reference itself
check( 'value(): the value of an image is escaped, and the slot is still found by its uri',
	\Nino\Modules\Components::value( $appData, '"><x>[', 'image' ) === '&quot;&gt;&lt;x&gt;&#91;'
	&& str_contains( $render( '[image /template/page-home/hero/background]' ), 'src="/public/images/bg.1600x900.jpg"' ) === true
	&& $render( '[image "><x>]' ) === '' );

// A stack that draws its cells itself is handed the dressing of the grid in the last argument of its cell function - the classes of the widths and the
// attributes of the group - and nothing about a card: with a grid, and with none
$seenCells = [];
\Nino\Modules\Components::addStack( $appData, 'cellprobe', static function( array &$appData, array $args ) use ( &$seenCells ): string {
	return \Nino\Modules\Components::renderStack( $appData, $args, static function( string $inner, array $element, int $index, array $cell ) use ( &$seenCells ): string {
		$seenCells[] = $cell;
		return '';
	} );
}, [ 'label' => 'Probe', 'grid' => true ] );
\Nino\Modules\Components::addStack( $appData, 'rowprobe', static function( array &$appData, array $args ) use ( &$seenCells ): string {
	return \Nino\Modules\Components::renderStack( $appData, $args, static function( string $inner, array $element, int $index, array $cell ) use ( &$seenCells ): string {
		$seenCells[] = $cell;
		return '';
	} );
}, [ 'label' => 'Probe' ] );
$seenCells = [];
$render( '[cellprobe /services limit="1" card="alt" autoheight="1" id="grp"][probe][/cellprobe]' );
$gridCell = $seenCells[0] ?? null;
$seenCells = [];
$render( '[rowprobe /services limit="1" card="alt"][probe][/rowprobe]' );
$rowCell = $seenCells[0] ?? null;
// A caller that is no shortcode - a stack of a feature, a test - hands renderStack() its arguments as it likes: a word for a card is no more heard there
$directCells = [];
foreach( [ 'alt', 'weird', 5, null ] as $directCard )
	\Nino\Modules\Components::renderStack( $appData, [ '/services', 'limit' => 1, 'grid' => true, 'card' => $directCard ], static function( string $inner, array $element, int $index, array $cell ) use ( &$directCells ): string {
		$directCells[] = $cell;
		return '';
	} );
check( 'renderStack() hands the dressing of the grid to its cell function - class and attributes, and no card - and a stack with no grid hands both empty',
	$gridCell === [ 'class' => 'nino-grid-s-100 nino-grid-m-50 nino-grid-l-33 nino-autoheight', 'attributes' => ' data-autoheight-group="grp"' ]
	&& $rowCell === [ 'class' => '', 'attributes' => '' ]
	&& array_filter( $directCells, static fn( array $cell ): bool => array_keys( $cell ) !== [ 'class', 'attributes' ] || $cell['class'] !== 'nino-grid-s-100 nino-grid-m-50 nino-grid-l-33' ) === [] && count( $directCells ) === 4 );

check( 'cell() draws a div, always: the classes and the attributes it is given and the dressing of the grid, and nothing of a card - not for a word for one in the cell it is handed either',
	\Nino\Modules\Components::cell( [], 'in' ) === '<div>in</div>'
	&& \Nino\Modules\Components::cell( [ 'class' => 'a', 'attributes' => ' data-x="1"' ], 'in' ) === '<div class="a" data-x="1">in</div>'
	&& \Nino\Modules\Components::cell( [ 'class' => 'a' ], 'in', 'b', ' id="c"' ) === '<div class="a b" id="c">in</div>'
	&& \Nino\Modules\Components::cell( [ 'class' => 'a', 'card' => 'card' ], 'in', 'b', ' id="c"' ) === '<div class="a b" id="c">in</div>'
	&& \Nino\Modules\Components::cell( [ 'card' => 'brand-alt' ], 'in' ) === '<div>in</div>' );

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
