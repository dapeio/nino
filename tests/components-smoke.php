<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	components-smoke.php	Dependency-free smoke test for \Nino\Modules\Components - the
 *											registry, the schemas, the six titles, the card, the stacks, cell(),
 *											escape() and the lang attribute of a text that is another language's
 *											- and for what Nino.css hands a design layer: the hero,
 *											the font roles, the scale, the hooks and the grounds on three
 *											levels, and the radius of a surface. Runs against an isolated
 *											sandbox directory, never touches the real project data.
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

// What the framework ships: the weights the rules had before there were roles, and the sizes of the redesign - a title on --text-5, a subtitle on --text-3, a button of 1.1rem
$hookDefaults = [
	'--font-title-weight' => 'bold', '--font-subtitle-weight' => 'normal', '--font-text-weight' => 'normal', '--font-button-weight' => 'bold', '--font-code-weight' => 'normal',
	'--font-title-size' => 'var(--text-5)', '--font-subtitle-size' => 'var(--text-3)', '--font-text-size' => '1rem', '--font-button-size' => 'var(--fontsize-btn)', '--fontsize-btn' => '1.1rem', '--font-code-size' => '.875rem',
];
$hookChanged = [];
foreach( $hookDefaults as $hookProperty => $hookExpected )
	if( $hookValue( $hookRoot, $hookProperty ) !== $hookExpected )
		$hookChanged[] = $hookProperty;
check( '...with the weights the rules had before they were roles and the sizes the framework ships: a title on --text-5 of the scale, a subtitle on --text-3, a button of 1.1rem'. ( $hookChanged === [] ? '' : ' - changed: '. implode( ', ', $hookChanged ) ), $hookChanged === [] );

// Each rule reads the role it belongs to
$hookReads = [
	'body'											=> [ '--font-text-size', '--font-text-weight', '--font-text-transform' ],
	'h1, h2, h3, h4, h5, h6'		=> [ '--font-title-weight', '--font-title-transform' ],
	'.nino-section-title'				=> [ '--fontfamily-title', '--font-title-size', '--font-title-transform', '--section-title-scale' ],
	'.nino-card-title'					=> [ '--fontfamily-title', '--font-title-size', '--font-title-transform', '--card-title-scale' ],
	'.nino-hero-title'					=> [ '--fontfamily-title', '--font-title-size', '--font-title-transform', '--scale-loud' ],
	'.nino-section-subtitle'		=> [ '--fontfamily-subtitle', '--font-subtitle-size', '--font-subtitle-weight', '--font-subtitle-transform', '--section-subtitle-scale' ],
	'.nino-card-subtitle'			=> [ '--fontfamily-subtitle', '--font-subtitle-size', '--font-subtitle-weight', '--font-subtitle-transform', '--card-subtitle-scale' ],
	'.nino-hero-subtitle'				=> [ '--fontfamily-subtitle', '--font-subtitle-size', '--font-subtitle-weight', '--font-subtitle-transform', '--scale-loud' ],
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

// The hero is the loud variant of a section's title and subtitle, and the variants are numbers a design layer moves
$hookHeroWrong = [];
foreach( [ '.nino-hero-title' => '--font-title-size', '.nino-hero-subtitle' => '--font-subtitle-size' ] as $hookHeroClass => $hookHeroRole ) {
	preg_match_all( '/(?<![\w-])font-size\s*:\s*([^;]+?)\s*;/', $hookRules( $hookHeroClass ), $hookHeroSizes );
	if( count( $hookHeroSizes[1] ) < 1 )
		$hookHeroWrong[] = $hookHeroClass. ' states no size';
	foreach( $hookHeroSizes[1] as $hookHeroSize )
		if( preg_replace( '/\s+/', '', $hookHeroSize ) !== 'calc(var('. $hookHeroRole. ')*var(--scale-loud))' )
			$hookHeroWrong[] = $hookHeroClass. ' is '. $hookHeroSize;
}
check( 'the hero is the loud variant of a section\'s title and subtitle: wherever it states its size, below 768px and from there, it is the size of the title or of the subtitle role times --scale-loud, and no number of its own'
	. ( $hookHeroWrong === [] ? '' : ' - '. implode( ', ', $hookHeroWrong ) ), $hookHeroWrong === [] );
preg_match_all( '/(?<![\w-])(--[\w-]+)\s*:/', $hookCss, $hookDeclared );
$hookHeroReads = [];
foreach( [ '.nino-hero-title', '.nino-hero-subtitle' ] as $hookHeroClass )
	foreach( [ '', '--quiet', '--loud' ] as $hookHeroVariant )
		if( preg_match_all( '/var\((--[\w-]+)/', $hookRules( $hookHeroClass. $hookHeroVariant ), $hookHeroFound ) > 0 )
			$hookHeroReads = array_merge( $hookHeroReads, $hookHeroFound[1] );
check( '...and every custom property the rules of the hero read is one that Nino.css declares'. ( array_diff( $hookHeroReads, $hookDeclared[1] ) === [] ? '' : ' - not: '. implode( ', ', array_unique( array_diff( $hookHeroReads, $hookDeclared[1] ) ) ) ),
	count( $hookHeroReads ) > 0 && array_diff( $hookHeroReads, $hookDeclared[1] ) === [] );
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
	section, a card and a hero the scale that class has, the hero's being --scale-loud itself - times --scale-quiet or --scale-loud, and so
	is the leading where the variant states one: a loud hero title is the role times --scale-loud twice. The variants of the pricing table
	are not: they stay on the ladder	*/
$hookVariants = [
	'.nino-section-title'			=> [ 'var(--font-title-size)', 'var(--section-title-scale)' ],
	'.nino-section-subtitle'	=> [ 'var(--font-subtitle-size)', 'var(--section-subtitle-scale)' ],
	'.nino-section-text'			=> [ 'var(--font-text-size)' ],
	'.nino-card-title'				=> [ 'var(--font-title-size)', 'var(--card-title-scale)' ],
	'.nino-card-subtitle'		=> [ 'var(--font-subtitle-size)', 'var(--card-subtitle-scale)' ],
	'.nino-card-descr'				=> [ 'var(--font-text-size)' ],
	'.nino-hero-title'				=> [ 'var(--font-title-size)', 'var(--scale-loud)' ],
	'.nino-hero-subtitle'			=> [ 'var(--font-subtitle-size)', 'var(--scale-loud)' ],
];
$hookUnscaled = [];
foreach( $hookVariants as $hookBase => $hookFactors )
	foreach( [ 'quiet', 'loud' ] as $hookKind ) {

		// The size is the step times the number, and so is the leading where the variant states one
		preg_match_all( '/(?<![\w-])(?:font-size|line-height)\s*:([^;]*);/', $hookRules( $hookBase. '--'. $hookKind ), $hookSizes );

		if( count( $hookSizes[1] ) < 1 )
			$hookUnscaled[] = $hookBase. '--'. $hookKind;
		foreach( $hookSizes[1] as $hookSize )
			foreach( array_count_values( array_merge( $hookFactors, [ 'var(--scale-'. $hookKind. ')' ] ) ) as $hookFactor => $hookTimes )
				if( substr_count( $hookSize, (string) $hookFactor ) < $hookTimes )
					$hookUnscaled[] = $hookBase. '--'. $hookKind. ' does not read '. $hookFactor. ( $hookTimes > 1 ? ' '. $hookTimes. ' times' : '' );
	}
check( 'the quiet and the loud variants of a section, a card and a hero are their role times the number of their class, where it has one, times --scale-quiet and --scale-loud, which are .85 and 1.2'. ( $hookUnscaled === [] ? '' : ' - not: '. implode( ', ', $hookUnscaled ) ),
	$hookUnscaled === [] && $hookValue( $hookRoot, '--scale-quiet' ) === '.85' && $hookValue( $hookRoot, '--scale-loud' ) === '1.2' );
// The classes that took a number of their own are the same factors themselves: the size, and the leading of the two section classes and of the hero, which moves with it
$hookUnscaledBase = [];
foreach( [ '.nino-section-title', '.nino-section-subtitle', '.nino-card-subtitle', '.nino-hero-title', '.nino-hero-subtitle' ] as $hookBase ) {
	preg_match_all( '/(?<![\w-])(?:font-size|line-height)\s*:([^;]*);/', $hookRules( $hookBase ), $hookSizes );
	if( count( $hookSizes[1] ) < 1 )
		$hookUnscaledBase[] = $hookBase;
	foreach( $hookSizes[1] as $hookSize )
		foreach( $hookVariants[$hookBase] as $hookFactor )
			if( str_contains( $hookSize, $hookFactor ) === false )
				$hookUnscaledBase[] = $hookBase. ' does not read '. $hookFactor;
}
check( '...and so are a section title, a section subtitle, a card subtitle and the title and the subtitle of a hero themselves - the leading of the two section classes and of the hero with their size'. ( $hookUnscaledBase === [] ? '' : ' - not: '. implode( ', ', $hookUnscaledBase ) ), $hookUnscaledBase === [] );
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

// What the shadows a design layer hands out - the card's and the one the frame of a picture casts while the effect shows, each a custom property - leave alone: no rule but these writes a box-shadow of its own
$hookShadows = [];
preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $hookCss, $hookBlocks, PREG_SET_ORDER );
foreach( $hookBlocks as [ , $hookBlockSelector, $hookBlockBody ] )
	if( preg_match_all( '/(?<![\w-])box-shadow\s*:\s*([^;]+?)\s*(?:;|$)/', $hookBlockBody, $hookBlockShadows ) > 0 )
		foreach( $hookBlockShadows[1] as $hookBlockShadow )
			if( in_array( $hookBlockShadow, [ 'var(--shadow-card)', 'var(--image-hover-shadow)', 'none' ], true ) === false )
				$hookShadows[] = trim( (string) preg_replace( '/\s+/', ' ', $hookBlockSelector ) );
check( 'the only box-shadows Nino.css writes itself are those of the middle card of .nino-pricing-row--feature-middle and of the toast - and the second line of two focus rings, the one of a button on a cover or a hero and the one of the dot of a slider'. ( $hookShadows === [] ? ' - none found' : ' - found: '. implode( ', ', $hookShadows ) ),
	$hookShadows === [ ':is(.nino-cover, .nino-parallex, .nino-hero) .nino-btn:focus-visible', '.nino-pricing-row--feature-middle > .nino-pricing-item:nth-child(2)', '.nino-slider-point:focus-visible', '.nino-toast' ] );

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

// A section answers for its own ground with all three families, and the pairs are the fixed ones - and so does a row that paints one, for what is in it
$groundWrong = [];
foreach( array_slice( $groundNames, 1 ) as $ground )
	foreach( [ '.nino-section--', '.nino-grid-row--' ] as $groundLevelClass ) {
		$groundBody = $groundOf( $groundLevelClass. $ground );
		foreach( [ 'grid' => $groundCounter[$ground], 'grid-next' => $groundCounter[ $groundCounter[$ground] ], 'card' => $groundCounter[$ground] ] as $groundFamily => $groundTarget )
			foreach( $groundParts as $groundPart )
				if( $groundSets( $groundBody, '--counter-'. $groundFamily. '-'. $groundPart, $groundSource[$groundTarget][$groundPart] ) === false )
					$groundWrong[] = $groundLevelClass. $ground. ' does not set --counter-'. $groundFamily. '-'. $groundPart. ' to '. $groundSource[$groundTarget][$groundPart];
	}
check( 'every ground of a section and of a row sets the three families, all six parts of each, to the counter of its own ground (grid, card) and to the counter of that (grid-next)'. ( $groundWrong === [] ? '' : ' - '. implode( '; ', $groundWrong ) ), $groundWrong === [] );

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
$groundCounterRule = static fn( string $selector ): array => array_values( array_filter( $groundEach[$selector] ?? [], static fn( string $body ): bool => str_contains( $body, '--counter-card-bg' ) === true ) );
check( '...and a counter row is the counter column for a row: the one rule, so that the two stay one',
	count( $groundCounterRule( '.nino-grid-row--counter' ) ) === 1 && $groundCounterRule( '.nino-grid-row--counter' ) === $groundCounterRule( '.nino-grid--counter' ) );

// Every ground modifier of every level sets the pair and the roles, and paints the pair
$groundWrong = [];
foreach( array_slice( $groundNames, 1 ) as $ground )
	foreach( [ 'section', 'grid-row', 'grid', 'card' ] as $groundLevel ) {
		$groundBody = $groundOf( '.nino-'. $groundLevel. '--'. $ground );
		foreach( [ '--color-ground' => 'bg', '--color-ink' => 'text', '--color-title' => 'title', '--color-subtitle' => 'text', '--color-accent' => 'accent', '--color-focus' => 'focus', '--color-border' => 'border' ] as $groundRole => $groundPart )
			if( $groundSets( $groundBody, $groundRole, $groundSource[$ground][$groundPart] ) === false )
				$groundWrong[] = '.nino-'. $groundLevel. '--'. $ground. ' does not set '. $groundRole. ' to '. $groundSource[$ground][$groundPart];
		if( $groundSets( $groundBody, 'background-color', 'var(--color-ground)' ) === false || $groundSets( $groundBody, 'color', 'var(--color-ink)' ) === false )
			$groundWrong[] = '.nino-'. $groundLevel. '--'. $ground. ' does not paint the pair it sets';
	}
check( 'a section, a row, a column and a card of a ground set the same seven roles to the same sources and paint the same pair - the vocabulary is one'. ( $groundWrong === [] ? '' : ' - '. implode( '; ', $groundWrong ) ), $groundWrong === [] );
check( '...the plain section sets the pair of the default ground and paints it, as it did',
	$groundSets( $groundOf( '.nino-section' ), '--color-ground', 'var(--color-section-default-bg)' ) && $groundSets( $groundOf( '.nino-section' ), '--color-ink', 'var(--color-section-default-text)' )
	&& $groundSets( $groundOf( '.nino-section' ), 'background-color', 'var(--color-section-default-bg)' ) && $groundSets( $groundOf( '.nino-section' ), 'color', 'var(--color-section-default-text)' ) );

/*	The logo: [logo] draws a picture for a light ground and one for a dark ground, and the ground a thing stands on says which is seen. The pair of properties that says it,
	--logo-light and --logo-dark, is set wherever --color-ground is - by every rule of the stylesheet that sets that, from the roles of the ground it paints - and the three
	families of the counter ground carry it as they carry the other six parts. The role a ground names is read off the ground itself here, not off the stylesheet's answer	*/
$logoRole = static function( string $ground, string $kind ): string {
	if( preg_match( '/^var\((--color-section-(?:default|alt|tint|dark|black))-bg\)$/', $ground, $found ) === 1 )
		return 'var('. $found[1]. '-logo-'. $kind. ')';
	if( preg_match( '/^var\((--color-(?:primary|brand-alt|error|success|warning))\)$/', $ground, $found ) === 1 )
		return 'var('. $found[1]. '-logo-'. $kind. ')';
	if( $ground === 'var(--color-background)' )
		return 'var(--color-logo-'. $kind. ')';
	if( preg_match( '/^var\((--counter-(?:grid|grid-next|card))-bg\)$/', $ground, $found ) === 1 )
		return 'var('. $found[1]. '-logo-'. $kind. ')';
	return '';
};
$logoWrong = [];
$logoGrounds = 0;
$logoFamilies = 0;
foreach( $groundBlocks as [ , $logoSelectors, $logoBody ] ) {
	$logoName = trim( (string) preg_replace( '/\s+/', ' ', (string) $logoSelectors ) );
	$logoGround = $hookValue( $logoBody, '--color-ground' );
	if( $logoGround !== null ) {
		$logoGrounds++;
		foreach( [ 'light', 'dark' ] as $logoKind )
			if( $logoRole( $logoGround, $logoKind ) === '' || $hookValue( $logoBody, '--logo-'. $logoKind ) !== $logoRole( $logoGround, $logoKind ) )
				$logoWrong[] = $logoName. ' sets --color-ground to '. $logoGround. ' and --logo-'. $logoKind. ' to '. ( $hookValue( $logoBody, '--logo-'. $logoKind ) ?? 'nothing' );
	}
	foreach( [ 'grid', 'grid-next', 'card' ] as $logoFamily ) {
		$logoBg = $hookValue( $logoBody, '--counter-'. $logoFamily. '-bg' );
		if( $logoBg === null )
			continue;
		$logoFamilies++;
		foreach( [ 'light', 'dark' ] as $logoKind )
			if( $logoRole( $logoBg, $logoKind ) === '' || $hookValue( $logoBody, '--counter-'. $logoFamily. '-logo-'. $logoKind ) !== $logoRole( $logoBg, $logoKind ) )
				$logoWrong[] = $logoName. ' sets --counter-'. $logoFamily. '-bg to '. $logoBg. ' and --counter-'. $logoFamily. '-logo-'. $logoKind. ' to '. ( $hookValue( $logoBody, '--counter-'. $logoFamily. '-logo-'. $logoKind ) ?? 'nothing' );
	}
}
check( 'every rule that sets --color-ground sets the pair of the logo beside it, --logo-light and --logo-dark, to the roles of the ground it paints - the sections, the rows, the columns and the cards of a ground, the counter, the dimmed ones, the statuses of an alert, a badge, a toast and a message, the table stripe, and the overlays and the modal that put the page\'s ground back with the page\'s pair'
	. ( $logoWrong === [] ? '' : ' - '. implode( '; ', $logoWrong ) ), $logoWrong === [] && $logoGrounds >= 29 );
check( '...and every rule that sets a family of the counter ground sets its logo-light and logo-dark beside the border, from the same ground as its bg - :root, the grounds of a section and of a row, of a column, and the counter itself',
	$logoWrong === [] && $logoFamilies >= 29 );

// The roles: the pair of each ground, in :root - literal where a ground is written on in one ink for good, another pair where Nino.css collapses one ground onto another, as it does its ink
$logoRoles = [ '--color-logo-' => 'page', '--color-section-default-logo-' => 'page', '--color-section-alt-logo-' => 'page', '--color-section-tint-logo-' => 'alt', '--color-section-dark-logo-' => 'dark', '--color-section-black-logo-' => 'dark',
	'--color-primary-logo-' => 'dark', '--color-brand-alt-logo-' => 'primary', '--color-error-logo-' => 'primary', '--color-success-logo-' => 'primary', '--color-warning-logo-' => 'primary', '--color-footer-logo-' => 'dark' ];
$logoWrong = [];
foreach( $logoRoles as $logoRole => $logoFollows ) {
	$logoLight = $hookValue( $hookRoot, $logoRole. 'light' );
	$logoDark = $hookValue( $hookRoot, $logoRole. 'dark' );
	if( $logoLight === null || $logoDark === null ) {
		$logoWrong[] = $logoRole. ' is not declared as a pair';
		continue;
	}
	$logoPair = [ $logoLight, $logoDark ];
	if( $logoFollows === 'alt' )
		$logoExpected = [ 'var(--color-section-alt-logo-light)', 'var(--color-section-alt-logo-dark)' ];
	elseif( $logoFollows === 'primary' )
		$logoExpected = [ 'var(--color-primary-logo-light)', 'var(--color-primary-logo-dark)' ];
	else
		$logoExpected = $logoFollows === 'page' ? [ 'block', 'none' ] : [ 'none', 'block' ];
	if( $logoPair !== $logoExpected )
		$logoWrong[] = $logoRole. ' is '. implode( ' / ', $logoPair ). ', not '. implode( ' / ', $logoExpected );
}
check( ':root declares the pair of every ground as a role, twelve of them: the page, the five grounds of a section, the two brand grounds, the three statuses and the footer - block and none where the ground is written on in one ink, the page and the light grounds showing the picture for a light ground and the dark, black, primary and footer the one for a dark ground, and the grounds Nino.css collapses onto another (tint onto alt, brand-alt and the statuses onto primary) following it'
	. ( $logoWrong === [] ? '' : ' - '. implode( '; ', $logoWrong ) ), $logoWrong === [] && count( $logoRoles ) === 12
	&& $hookValue( $hookRoot, '--logo-light' ) === 'var(--color-section-default-logo-light)' && $hookValue( $hookRoot, '--logo-dark' ) === 'var(--color-section-default-logo-dark)' );
$logoCounterWrong = [];
foreach( $groundFamilies as $logoFamily )
	foreach( [ 'light', 'dark' ] as $logoKind )
		if( $hookValue( $hookRoot, '--counter-'. $logoFamily. '-logo-'. $logoKind ) !== 'var(--color-section-'. ( $logoFamily === 'grid-next' ? 'default' : 'alt' ). '-logo-'. $logoKind. ')' )
			$logoCounterWrong[] = '--counter-'. $logoFamily. '-logo-'. $logoKind;
check( '...the page\'s counter families carry the pair as they carry the other six parts: the column is alt, a card in it default again, a card on the page alt'. ( $logoCounterWrong === [] ? '' : ' - not: '. implode( ', ', $logoCounterWrong ) ), $logoCounterWrong === [] );
check( '...a counter column and a counter card read the pair from their family, and the column hands the next one to its cards',
	$groundSets( $groundOf( '.nino-grid--counter' ), '--logo-light', 'var(--counter-grid-logo-light)' ) && $groundSets( $groundOf( '.nino-grid--counter' ), '--logo-dark', 'var(--counter-grid-logo-dark)' )
	&& $groundSets( $groundOf( '.nino-grid--counter' ), '--counter-card-logo-light', 'var(--counter-grid-next-logo-light)' ) && $groundSets( $groundOf( '.nino-grid--counter' ), '--counter-card-logo-dark', 'var(--counter-grid-next-logo-dark)' )
	&& $groundSets( $groundOf( '.nino-card--counter' ), '--logo-light', 'var(--counter-card-logo-light)' ) && $groundSets( $groundOf( '.nino-card--counter' ), '--logo-dark', 'var(--counter-card-logo-dark)' ) );
check( '...the overlay of the burger and the modal put the page\'s ground back, and with it the page\'s pair',
	$groundSets( $groundOf( '.nino-nav-burger > button[aria-expanded="true"] ~ .nino-nav-content' ), '--logo-light', 'var(--color-logo-light)' ) && $groundSets( $groundOf( '.nino-nav-burger > button[aria-expanded="true"] ~ .nino-nav-content' ), '--logo-dark', 'var(--color-logo-dark)' )
	&& $groundSets( $groundOf( '.nino-modal' ), '--logo-light', 'var(--color-logo-light)' ) && $groundSets( $groundOf( '.nino-modal' ), '--logo-dark', 'var(--color-logo-dark)' ) );
check( '...a footer sets the pair of its bands, which are the dark ground in Nino.css and in the delivered theme, and a frame that paints another ground says so after it; the logo of a footer is the set, with the opacity and the width it always had',
	$groundSets( $groundOf( 'footer' ), '--logo-light', 'var(--color-footer-logo-light)' ) && $groundSets( $groundOf( 'footer' ), '--logo-dark', 'var(--color-footer-logo-dark)' )
	&& $groundSets( $groundOf( '.nino-footer-logo' ), 'opacity', '.7' ) && $groundSets( $groundOf( '.nino-footer-logo' ), 'max-width', '8rem' ) );

// Print: the paper is white whatever a frame painted on the screen, so the pair is the page's on every element in a print media rule that wins against every ground
check( 'in print the pair is the page\'s on every element: an @media print rule sets --logo-light to block and --logo-dark to none on :root and everything under it, important, so that no ground a frame painted on the screen puts the picture for a dark ground on white paper',
	preg_match( '/@media\s+print\s*\{\s*:root,\s*:root\s*\*\s*\{\s*--logo-light:\s*block\s*!important;\s*--logo-dark:\s*none\s*!important;\s*\}\s*\}/', $hookCss ) === 1 );

// The switch: each picture is displayed as the pair says, and falls back to the page's own - the picture for a light ground is seen, the other not - so that a page without a ground to say is not blank
check( 'the set switches on the pair: the picture for a light ground is displayed as --logo-light says, block where nothing does, and the one for a dark ground as --logo-dark says, none where nothing does',
	$groundSets( $groundOf( '.nino-logo-set .nino-logo-light' ), 'display', 'var(--logo-light, block)' ) && $groundSets( $groundOf( '.nino-logo-set .nino-logo-dark' ), 'display', 'var(--logo-dark, none)' )
	&& $groundSets( $groundOf( '.nino-logo-set' ), 'display', 'block' ) );
check( '...a set of one picture has nothing to switch: the only child is shown whichever ground it stands on, in a rule heavier than the two that switch, a class, a type and a pseudo-class against two classes - whichever order they stand in',
	$groundSets( $groundOf( '.nino-logo-set > img:only-child' ), 'display', 'block' ) );
check( '...no rule of the switch writes a colour or a size of its own: the set is a box and the frame\'s .nino-logo img sizes what is in it',
	preg_match( '/(?<![\w-])(?:color|width|height|opacity|filter)\s*:/', $groundOf( '.nino-logo-set' ). ' '. $groundOf( '.nino-logo-set .nino-logo-light' ). ' '. $groundOf( '.nino-logo-set .nino-logo-dark' ) ) !== 1
	&& $groundSets( $groundOf( '.nino-logo img' ), 'width', '100%' ) && $groundSets( $groundOf( '.nino-logo img' ), 'height', 'auto' ) );

// Nothing compares one ground with another: no selector names a ground of a section or a column above a ground of a column or a card
$groundCompared = [];
foreach( array_keys( $groundEach ) as $groundSelector )
	if( preg_match( '/\.nino-(?:section|grid)--(?:alt|tint|dark|black|primary|brand-alt|counter|dim)(?![\w-])[^,]*[\s>+~]\.nino-(?:grid|card)--(?:alt|tint|dark|black|primary|brand-alt|counter|dim)(?![\w-])/', (string) $groundSelector ) === 1 )
		$groundCompared[] = (string) $groundSelector;
check( 'no rule compares the ground of a thing with the ground it sits on: no selector has a section or a column of a ground above a column or a card of one'. ( $groundCompared === [] ? '' : ' - '. implode( ' | ', $groundCompared ) ),
	$groundCompared === [] && isset( $groundEach['.nino-section--alt .nino-card--alt'] ) === false && count( $groundEach ) > 400 );

// A column that paints keeps its content off the colour
$groundWrong = [];
foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter', 'dim' ] as $ground )
	if( $groundSets( $groundOf( '.nino-grid--'. $ground ), 'padding', 'var(--space-2)' ) === false )
		$groundWrong[] = '.nino-grid--'. $ground;
check( 'a column that paints a ground, the counter and a dimmed one have padding of one step'. ( $groundWrong === [] ? '' : ' - not: '. implode( ', ', $groundWrong ) ), $groundWrong === [] );

// A card of any ground has the chrome the alt card had, and the borderless one still has none
$groundWrong = [];
foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter' ] as $ground )
	if( $groundSets( $groundOf( '.nino-card--'. $ground ), 'border', '1px solid var(--color-border)' ) === false || $groundSets( $groundOf( '.nino-card--'. $ground ), 'padding', 'calc(var(--space-1) * var(--whitespace-scale))' ) === false )
		$groundWrong[] = '.nino-card--'. $ground;
check( 'a card of any ground, the counter included, has the border and the padding the alt card had - the space step times the whitespace'. ( $groundWrong === [] ? '' : ' - not: '. implode( ', ', $groundWrong ) ),
	$groundWrong === [] && $groundSets( $groundOf( '.nino-card' ), 'padding', '0 0 calc(var(--space-1) * var(--whitespace-scale))' ) === true && str_contains( $groundOf( '.nino-card--borderless' ), 'border: 0' ) === true );

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
check( 'a column may carry a picture: .nino-grid-bg lies over the column and is cut at the radius of the column\'s kind, its image covers it, the column becomes the box it is placed in',
	$groundSets( $groundOf( '.nino-grid-bg' ), 'position', 'absolute' ) && $groundSets( $groundOf( '.nino-grid-bg' ), 'inset', '0' ) && $groundSets( $groundOf( '.nino-grid-bg' ), 'overflow', 'hidden' )
	&& $groundSets( $groundOf( '.nino-grid-bg' ), 'border-radius', 'var(--radius-column)' ) && $groundSets( $groundOf( '.nino-grid-bg > img' ), 'object-fit', 'cover' )
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
	$groundStale === [] && $groundSets( $groundOf( '.nino-stack > *' ), 'padding', 'calc(var(--nino-stack-gap-scaled) / 2)' ) === true && $groundSets( $groundOf( '.nino-stack > *' ), 'align-content', 'start' ) === true
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
/*	The conditions a rule stands in: for each rule whose selector list is exactly the one given, the at-rules that hold it, the outermost first and their spaces squeezed - [] for a rule at the
	top of the file, [ '@media (min-width: 768px)' ] for one in the 768px query	*/
$groundConditions = static function( string $selector ) use ( $hookCss ): array {
	$found = [];
	$open = [];
	$head = '';
	foreach( (array) preg_split( '/([{};])/', $hookCss, -1, PREG_SPLIT_DELIM_CAPTURE ) as $part )
		if( $part === '{' ) {
			$open[] = trim( (string) preg_replace( '/\s+/', ' ', $head ) );
			if( $open[ count( $open ) - 1 ] === $selector )
				$found[] = array_values( array_map( static fn( string $rule ): string => (string) preg_replace( '/\s*:\s*/', ': ', $rule ), array_filter( array_slice( $open, 0, -1 ), static fn( string $rule ): bool => str_starts_with( $rule, '@' ) ) ) );
			$head = '';
		} elseif( $part === '}' ) {
			array_pop( $open );
			$head = '';
		} elseif( $part === ';' )
			$head = '';
		else
			$head .= $part;
	return $found;
};

/*	A card in the cell of a stack takes no margin of its own: the half gap of the cell is the room between neighbours and the edges of the stack are the column's. Measured at 1280px with
	a gap of 2, the neighbours stood 36px apart where the gap is 18px, and the first card 9px in from the column's edge. The rule is as heavy as the rules that give a card a margin - one
	class, the cell being asked for in :where() - so that the order decides: it comes after every rule of .nino-card, the 768px one among them, and before the margin classes, which win
	over it. The margin of a card that a column of the Builder writes (nino-mt-2) lost to the rule when it weighed two classes; with the whole selector in :where() it weighed nothing
	and the margin of .nino-card won in the cell. The margins of a card are for a card that stands in a column	*/
$groundCellCard = ':where(.nino-stack > *) > .nino-card';
$groundSlotAt = $groundLastOf( [ '.nino-stack > *' ], 'display' );
$groundHeavy = array_values( array_filter( array_keys( $groundEach ), static fn( mixed $selector ): bool => str_contains( (string) $selector, '.nino-card' ) === true
	&& str_contains( (string) preg_replace( '/:where\([^()]*\)/', '', (string) $selector ), '.nino-stack' ) === true && preg_match( '/(?<![\w-])margin\s*:/', $groundOf( (string) $selector ) ) === 1 ) );
$groundWrong = [];
foreach( [ 'm', 'mt', 'mb', 'ml', 'mr' ] as $groundSide )
	foreach( [ 0, 1, 2, 3, 4, 5, 6 ] as $groundStep )
		if( $groundLastOf( [ $groundCellCard ] ) >= $groundLastOf( [ '.nino-'. $groundSide. '-'. $groundStep ] ) )
			$groundWrong[] = '.nino-'. $groundSide. '-'. $groundStep;
check( 'a card in the cell of a stack takes no margin of its own: '. $groundCellCard. ' says margin: 0, once and as the only margin it sets, and no rule that gives a card in a cell a margin asks for the cell outside :where()'
	. ( $groundHeavy === [] ? '' : ' - heavier: '. implode( ' | ', $groundHeavy ) ),
	$groundSets( $groundOf( $groundCellCard ), 'margin', '0' ) === true && count( $groundEach[$groundCellCard] ?? [] ) === 1
	&& preg_match( '/(?<![\w-])margin-(?:top|right|bottom|left)\s*:/', $groundOf( $groundCellCard ) ) !== 1 && $groundHeavy === []
	&& substr_count( (string) preg_replace( '/:where\([^()]*\)/', '', $groundCellCard ), '.nino-' ) === substr_count( '.nino-card', '.nino-' ) );
check( '...it stands after every rule of .nino-card, the 768px one among them, so that it wins over them at the same weight - and before every margin class, which wins over it: a card in a cell that says nino-mt-2 has that room'
	. ( $groundWrong === [] ? '' : ' - not before: '. implode( ', ', $groundWrong ) ),
	$groundWrong === [] && $groundLastOf( [ '.nino-card' ] ) > 0 && $groundLastOf( [ $groundCellCard ] ) > $groundLastOf( [ '.nino-card' ] ) && $groundLastOf( [ $groundCellCard ] ) > $groundSlotAt && $groundSlotAt > 0 );

// The room [spacer] leaves is a height of its own: nino-spacer-1 to -6, the steps of the margin classes, and no margin - which a margin class written on it would compete with
$groundWrong = [];
foreach( [ 1, 2, 3, 4, 5, 6 ] as $groundStep ) {
	$groundSpacer = $groundOf( '.nino-spacer-'. $groundStep );
	if( count( $groundEach['.nino-spacer-'. $groundStep] ?? [] ) !== 1 || $groundSets( $groundSpacer, 'height', 'var(--space-'. $groundStep. ')' ) === false || preg_match( '/(?<![\w-])margin/', $groundSpacer ) === 1
		|| $groundSets( $groundOf( '.nino-mt-'. $groundStep ), 'margin-top', 'var(--space-'. $groundStep. ')' ) === false )
		$groundWrong[] = '.nino-spacer-'. $groundStep;
}
check( 'the room of a [spacer] is a height of its own: .nino-spacer-1 to -6 say height: var(--space-<n>), the step the margin class of that number has, once each and with no margin'
	. ( $groundWrong === [] ? '' : ' - not: '. implode( ', ', $groundWrong ) ), $groundWrong === [] );

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
check( '...a card that [card] draws holds its picture and a div of the content: with last-bottom the div takes the room that is left and is a flex column itself, so that it is its last element that is pushed down and the picture stays above - and a button in it is placed as one in the card is',
	$groundSets( $groundOf( '.nino-card--last-bottom > .nino-card-content' ), 'flex', '1 1 auto' ) === true && $groundSets( $groundOf( '.nino-card--last-bottom > .nino-card-content' ), 'display', 'flex' ) === true
	&& $groundSets( $groundOf( '.nino-card--last-bottom > .nino-card-content' ), 'flex-direction', 'column' ) === true
	&& $groundSets( $groundOf( '.nino-card--last-bottom > .nino-card-content > :last-child' ), 'margin-top', 'auto' ) === true
	&& $groundSets( $groundOf( '.nino-card--last-bottom > .nino-card-content > .nino-btn' ), 'align-self', 'flex-start' ) === true
	&& $groundSets( $groundOf( '.nino-card--last-bottom.nino-text-center > .nino-card-content > .nino-btn' ), 'align-self', 'center' ) === true
	&& $groundSets( $groundOf( '.nino-card--last-bottom.nino-text-right > .nino-card-content > .nino-btn' ), 'align-self', 'flex-end' ) === true );
check( '...the block pads the content only where the card does not pad it - a card with no padding, a fullwidth card, a counter card - and the margins of a button in a centred or right-aligned card reach the button inside the block',
	$groundSets( $groundOf( '.nino-card:where(:not(.nino-p-0, .nino-card--fullwidth, .nino-card--counter)) > .nino-card-content' ), 'padding', '0' ) === true
	&& $groundSets( $groundOf( '.nino-text-center > .nino-card-content > .nino-btn' ), 'margin-left', 'calc(var(--space-1) / 2)' ) === true
	&& $groundSets( $groundOf( '.nino-text-right > .nino-card-content > .nino-btn' ), 'margin-left', 'var(--space-1)' ) === true );

// The picture behind a card, after the pattern of the one behind a column: the same rules but for the radius - the column's picture reads the column's kind, the card's and the row's take
// the corners of the card and of the row they lie in - and the rule that makes the parent the box it is placed in is one for both
$groundBgBody = static fn( string $selector ): string => trim( (string) preg_replace( '/\s+/', ' ', (string) preg_replace( '/(?<![\w-])border-radius\s*:[^;]*;/', '', $groundOf( $selector ) ) ) );
check( 'a card may carry a picture, after the pattern of the column\'s: .nino-card-bg lies over the card, is cut at its corners and behind its content, its image covers it - the declarations of .nino-grid-bg, the radius apart',
	$groundSets( $groundOf( '.nino-card-bg' ), 'position', 'absolute' ) && $groundSets( $groundOf( '.nino-card-bg' ), 'inset', '0' ) && $groundSets( $groundOf( '.nino-card-bg' ), 'overflow', 'hidden' )
	&& $groundSets( $groundOf( '.nino-card-bg' ), 'border-radius', 'inherit' ) && $groundSets( $groundOf( '.nino-card-bg' ), 'z-index', '-1' )
	&& $groundSets( $groundOf( '.nino-card-bg > img' ), 'object-fit', 'cover' ) && $groundSets( $groundOf( '.nino-card-bg > img' ), 'width', '100%' ) && $groundSets( $groundOf( '.nino-card-bg > img' ), 'height', '100%' )
	&& $groundBgBody( '.nino-card-bg' ) === $groundBgBody( '.nino-grid-bg' ) && $groundBgBody( '.nino-card-bg > img' ) === $groundBgBody( '.nino-grid-bg > img' ) );
check( '...a card with a picture is the box it is placed in and a stacking context of its own by the rule of the column\'s - extended, not copied - and --dim darkens it with the scrim of a dimmed cover',
	preg_match( '/(?<![\w-])(?:position|isolation)\s*:/', $groundOf( ':has(> .nino-grid-bg)' ) ) !== 1 && isset( $groundEach[':has(> .nino-card-bg)'] ) === false
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
	&& $groundSets( $groundOf( '.nino-card--dim' ), 'padding', 'calc(var(--space-1) * var(--whitespace-scale))' ) === true
	&& $groundChromeAt > 0 && $groundLastOf( [ '.nino-card--borderless' ] ) > $groundChromeAt );

// Full width, a dimmed card drops its side paddings as a card of a ground does: the :is() list of the full-width rule names it with the seven grounds
preg_match( '/\.nino-card--fullwidth:is\(([^)]*)\)/', $hookCss, $groundFullwidth );
$groundWrong = [];
foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter', 'dim' ] as $groundName )
	if( preg_match( '/(?<![\w-])\.nino-card--'. preg_quote( $groundName, '/' ). '(?![\w-])/', (string) ( $groundFullwidth[1] ?? '' ) ) !== 1 )
		$groundWrong[] = '.nino-card--'. $groundName;
check( '...and the full-width rule lists it with the seven grounds, so a full-width dimmed card has no side padding either'. ( $groundWrong === [] ? '' : ' - not in it: '. implode( ', ', $groundWrong ) ), $groundWrong === [] );

/*	A card is the link of a component that says stretched, the stretched way: the card is no <a>, since it holds buttons, and one link in it is stretched over it by its ::after. The card is the box the
	overlay is placed in and a stacking context of its own, so that what is lifted stays in it. The overlay is the pseudo-element of the <a> that carries the class nino-stretch - the link the
	component draws, the kernel's wrapper for the title or a feature's own <a> - which is the one element that is placed by no rule, or the overlay would be as large as the link. It lies over everything
	that is not lifted, a framed picture that is placed itself among it, and what else in the card can take focus is lifted over it: a position and a z-index above the overlay's, at the specificity of
	one class (:where()), so that an element that places itself and says so later keeps its place. The one stop of the keyboard shows on the card, and the link has no ring of its own	*/
$linkedLink = '.nino-card--linked a.nino-stretch';
$linkedOverlay = $groundOf( $linkedLink. '::after' );
$linkedLiftAt = array_values( array_filter( array_keys( $groundEach ), static fn( string $selector ): bool => str_starts_with( $selector, '.nino-card--linked :where(' ) === true ) );
$linkedLift = $groundOf( $linkedLiftAt[0] ?? '' );
$linkedZ = static fn( string $body ): int => (int) ( $hookValue( $body, 'z-index' ) ?? PHP_INT_MIN );
// What is left of a selector when its :where() groups are taken out: what counts for its specificity
$linkedFlat = static function( string $selector ): string {
	$flat = '';
	$depth = 0;
	for( $at = 0; $at < strlen( $selector ); $at++ ) {
		if( $depth === 0 && substr( $selector, $at, 7 ) === ':where(' ) {
			$depth = 1;
			$at += 6;
		}
		elseif( $depth > 0 )
			$depth += $selector[$at] === '(' ? 1 : ( $selector[$at] === ')' ? -1 : 0 );
		else
			$flat .= $selector[$at];
	}
	return trim( $flat );
};
$linkedOwn = array_map( static fn( string $declaration ): string => trim( explode( ':', $declaration )[0] ), array_filter( explode( ';', $groundOf( '.nino-card--linked' ) ), static fn( string $declaration ): bool => trim( $declaration ) !== '' ) );
check( 'a card may be the link of a component that says stretched, the stretched way: .nino-card--linked is the box the overlay is placed in - placed, and a stacking context of its own - and says nothing else of the card, so that a card with no such link is as without it',
	$groundSets( $groundOf( '.nino-card--linked' ), 'position', 'relative' ) === true && $groundSets( $groundOf( '.nino-card--linked' ), 'isolation', 'isolate' ) === true
	&& array_values( $linkedOwn ) === [ 'position', 'isolation' ] && count( $groundEach['.nino-card--linked'] ?? [] ) === 1 );
check( '...the overlay is the pseudo-element of the <a> of the class nino-stretch - the link a component that says stretched draws - an empty box over the whole card with a z-index of its own; and no rule places that link',
	$groundSets( $linkedOverlay, 'content', "''" ) === true && $groundSets( $linkedOverlay, 'position', 'absolute' ) === true && $groundSets( $linkedOverlay, 'inset', '0' ) === true && $linkedZ( $linkedOverlay ) > 0
	&& preg_match( '/(?<![\w-])(?:position|z-index|inset|top|right|bottom|left)\s*:/', $groundOf( $linkedLink ) ) !== 1
	&& str_starts_with( \Nino\Modules\Components::$html['link'], '<a[[class]]' ) === true );
check( '...what else in the card is there to be used for its own sake is lifted over it - a link, a button, a field and its label, a disclosure, a frame, a player with controls, whatever has a tabindex, but the link of the class nino-stretch - by a position and a z-index above the overlay\'s, at the specificity of one class'
	. ( $linkedLiftAt === [] ? ' - no such rule' : '' ),
	count( $linkedLiftAt ) === 1 && $groundSets( $linkedLift, 'position', 'relative' ) === true && $linkedZ( $linkedLift ) > $linkedZ( $linkedOverlay )
	&& array_filter( [ 'a', 'button', 'iframe', 'input', 'label', 'select', 'summary', 'textarea', '[controls]', '[tabindex]' ], static fn( string $element ): bool => preg_match( '/:where\((?:[^()]*, )?'. preg_quote( $element, '/' ). '(?:,|\))/', $linkedLiftAt[0] ?? '' ) !== 1 ) === []
	&& str_ends_with( $linkedLiftAt[0] ?? '', ':where(:not(.nino-stretch))' ) === true && $linkedFlat( $linkedLiftAt[0] ?? '' ) === '.nino-card--linked' );
check( '...the one stop of the keyboard shows on the card, inside it, and the link of the class nino-stretch has no ring of its own',
	$groundSets( $groundOf( '.nino-card--linked a.nino-stretch:focus-visible' ), 'outline', 'none' ) === true
	&& $groundSets( $groundOf( '.nino-card--linked:has(a.nino-stretch:focus-visible)' ), 'outline', '2px solid var(--color-focus)' ) === true
	&& (int) $hookValue( $groundOf( '.nino-card--linked:has(a.nino-stretch:focus-visible)' ), 'outline-offset' ) < 0 );

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
// A dimmed cover and a dimmed picture are the dark ground for what stands in them, the subtitle included: that is what lets them go without a rule for each thing in them -
// and a plain section and a row with a picture dimmed are the same
foreach( [ '.nino-cover--dim', '.nino-parallex--dim', '.nino-section--dim', '.nino-grid-row--dim', '.nino-img-background--dim' ] as $groundSelector )
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
	'.nino-nav-burger > button[aria-expanded="true"] ~ .nino-nav-content'	=> $groundPage,
	'.nino-localepicker-wrap input:checked + div'				=> $groundPage,
	'.nino-cover--dim'													=> $groundSource['black'],
	'.nino-parallex--dim'											=> $groundSource['black'],
	'.nino-section--dim'												=> $groundSource['black'],
	'.nino-grid-row--dim'												=> $groundSource['black'],
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
		&& preg_match( '/^(?::root|\.nino-section|\.nino-(?:section|grid-row|grid|card)--[a-z-]+)$/', (string) $groundSelector ) !== 1 && isset( $groundRestores[$groundSelector] ) === false )
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

// --- The whitespace, the picture of a card and of a section, the padded cover, the animation --

echo "Nino.css - the whitespace, the picture of a card and of a section, the padded cover and the animation\n";

/*	The whitespace is one number with three strengths. --whitespace-scale is 1 - the layout of every page that never heard of it - and the room
	between pieces and the room under text are derived from it, each exactly 1 where the number is 1 and moved a part of the way from there: the
	text less than the gap, and neither the whole way. A section and a card say the two again, because a property that is worked out from another
	is worked out where it is declared. What reads which is pinned rule by rule, and nowhere else reads any: the areas read the number itself,
	the pieces of a stack the gap and the texts theirs - and not the width of the page, a line height, a button or a utility class	*/
$whitespaceStrength = static fn( string $formula ): ?float => preg_match( '/^calc\(\s*1\s*\+\s*\(\s*var\(--whitespace-scale\)\s*-\s*1\s*\)\s*\*\s*(\d*\.\d+)\s*\)$/', $formula, $whitespacePart ) === 1 ? (float) $whitespacePart[1] : null;
$whitespaceGap = $whitespaceStrength( (string) $hookValue( $hookRoot, '--whitespace-gap' ) );
$whitespaceText = $whitespaceStrength( (string) $hookValue( $hookRoot, '--whitespace-text' ) );
check( ':root declares --whitespace-scale as 1 and, from it, the room between pieces and the room under text as 1 + ( the number - 1 ) times a part: exactly 1 where the number is 1, the text moving less than the gap and neither all of the way',
	$hookValue( $hookRoot, '--whitespace-scale' ) === '1' && $whitespaceGap !== null && $whitespaceText !== null && $whitespaceText > 0 && $whitespaceText < $whitespaceGap && $whitespaceGap < 1 );
check( '...a section and a card say the two strengths again, as the same formulas and with no number of their own, so that what stands in one that sets a number of its own takes it',
	array_filter( [ '.nino-section', '.nino-card' ], static fn( string $selector ): bool => preg_match( '/(?<![\w-])--whitespace-scale\s*:/', $groundOf( $selector ) ) === 1
		|| $hookValue( $groundOf( $selector ), '--whitespace-gap' ) !== $hookValue( $hookRoot, '--whitespace-gap' ) || $hookValue( $groundOf( $selector ), '--whitespace-text' ) !== $hookValue( $hookRoot, '--whitespace-text' ) ) === [] );
$whitespaceReads = [ '--whitespace-scale' => [], '--whitespace-gap' => [], '--whitespace-text' => [] ];
foreach( $groundBlocks as [ , $whitespaceSelectors, $whitespaceBody ] ) {
	$whitespaceSelectors = trim( (string) preg_replace( '/\s+/', ' ', (string) $whitespaceSelectors ) );
	if( $whitespaceSelectors === ':root' || $whitespaceSelectors === '.nino-section, .nino-card, .nino-whitespace--least, .nino-whitespace--less, .nino-whitespace--more, .nino-whitespace--most' )
		continue;
	preg_match_all( '/(?<![\w-])([\w-]+)\s*:[^;]*var\((--whitespace-(?:scale|gap|text))\)/', (string) $whitespaceBody, $whitespaceUses, PREG_SET_ORDER );
	foreach( $whitespaceUses as [ , $whitespaceProperty, $whitespaceToken ] )
		$whitespaceReads[$whitespaceToken][$whitespaceSelectors][$whitespaceProperty] = true;
}
$whitespaceWhere = [
	'--whitespace-scale' => [
		'.nino-section', '.nino-card', '.nino-card--alt, .nino-card--tint, .nino-card--dark, .nino-card--black, .nino-card--primary, .nino-card--brand-alt, .nino-card--counter, .nino-card--dim',
		'.nino-card--wide', '.nino-card-content', '.nino-card--fullwidth .nino-card-content',
		'.nino-grid-row--alt, .nino-grid-row--tint, .nino-grid-row--dark, .nino-grid-row--black, .nino-grid-row--primary, .nino-grid-row--brand-alt, .nino-grid-row--counter, .nino-grid-row--dim, .nino-grid-row:where(:has(> .nino-grid-row-bg))',
		'.nino-card-cols', '.nino-card-cols-s', '.nino-card-cols-m', '.nino-card-cols-l', '.nino-card-cols-xl',
		'.nino-card--grid.nino-grid-m-25', '.nino-card--grid.nino-grid-m-33', '.nino-card--grid.nino-grid-m-50', '.nino-cover--padded',
		'.nino-cover:not(.nino-cover--padded, .nino-pl-0) > .nino-grid-row:not(.nino-pl-0, .nino-grid-row--full)',
		'.nino-cover:not(.nino-cover--padded, .nino-pr-0) > .nino-grid-row:not(.nino-pr-0, .nino-grid-row--full)',
		':where(.nino-tiles) > .nino-grid-row > *',
	],
	'--whitespace-gap' => [
		'.nino-grid-row > .nino-stack-start, .nino-grid-row > .nino-stack-center, .nino-grid-row > .nino-stack-end, .nino-grid-row > [class*="nino-stack-gap-"]:not(.nino-stack)',
		'.nino-stack', '.nino-stack > *',
		'.nino-grid-gap-1', '.nino-grid-gap-2', '.nino-grid-gap-3', '.nino-grid-gap-4', '.nino-grid-gap-5', '.nino-grid-gap-6',
		':where(.nino-tiles) > .nino-grid-row > *',
	],
	'--whitespace-text' => [
		'.nino-hero-title', '.nino-hero-subtitle', '.nino-section-title', '.nino-section-subtitle', '.nino-section-text', '.nino-richtext p, .nino-richtext ul, .nino-richtext ol',
		'.nino-legal-section > h3', '.nino-legal-section + .nino-legal-section', '.nino-card-title', '.nino-card-subtitle', '.nino-card-descr', '.nino-card-img',
	],
];
$whitespaceWrong = [];
foreach( $whitespaceWhere as $whitespaceToken => $whitespaceExpected ) {
	$whitespaceFound = array_keys( $whitespaceReads[$whitespaceToken] );
	sort( $whitespaceFound );
	sort( $whitespaceExpected );
	if( $whitespaceFound !== $whitespaceExpected )
		$whitespaceWrong[] = $whitespaceToken. ' - read where it should not be: '. implode( ' | ', array_diff( $whitespaceFound, $whitespaceExpected ) ). '; not read where it should be: '. implode( ' | ', array_diff( $whitespaceExpected, $whitespaceFound ) );
}
check( 'the areas read --whitespace-scale itself - the padding of a section and of a tile, the margin and the padding of a card and of its content, the gap between the columns of a row of cards, the widths that give a card\'s margin up, the sides of the row of a cover - the stack its gap, a tile the gap of its words, the row of a section the gap between its columns and the texts theirs, and no other rule reads any of the three'
	. ( $whitespaceWrong === [] ? '' : ' - '. implode( '; ', $whitespaceWrong ) ), $whitespaceWrong === [] );
$whitespaceMay = [ '--whitespace-scale' => [ 'padding', 'padding-top', 'margin', 'column-gap', 'width', 'left', 'right', '--cover-padding-y', '--cover-padding-x' ], '--whitespace-gap' => [ 'gap', '--nino-stack-gap-scaled', '--nino-grid-gap' ], '--whitespace-text' => [ 'margin', 'margin-top', 'margin-bottom' ] ];
$whitespaceUsed = [];
foreach( $whitespaceReads as $whitespaceToken => $whitespaceRules )
	foreach( $whitespaceRules as $whitespaceSelectors => $whitespaceProperties )
		foreach( array_keys( $whitespaceProperties ) as $whitespaceProperty )
			if( in_array( $whitespaceProperty, $whitespaceMay[$whitespaceToken], true ) === false )
				$whitespaceUsed[] = $whitespaceSelectors. ' says '. $whitespaceProperty. ' by '. $whitespaceToken;
check( '...each in the properties it is for: the areas in padding, margin, the gap between columns and a width, the sides of the row of a cover in left and right, the gap of a stack in gap, the gap of a row in the property its columns give back, the text in margin - never a line height, a size or a colour'. ( $whitespaceUsed === [] ? '' : ' - '. implode( '; ', $whitespaceUsed ) ), $whitespaceUsed === [] );

// The picture of a card, and the frame that cuts a picture
check( 'the picture of a card is as wide as the card and never wider, and --inline is the other way round - in line, at its own size, capped by the card all the same',
	$hookValue( $groundOf( '.nino-card-img' ), 'width' ) === '100%' && $hookValue( $groundOf( '.nino-card-img' ), 'max-width' ) === '100%' && $hookValue( $groundOf( '.nino-card-img' ), 'display' ) === null
	&& $hookValue( $groundOf( '.nino-card-img--inline' ), 'display' ) === 'inline-block' && $hookValue( $groundOf( '.nino-card-img--inline' ), 'width' ) === 'auto'
	&& $hookValue( $groundOf( '.nino-card-img--inline' ), 'max-width' ) === null );
check( '...and it sits on the bottom of its line, so the room below an --inline picture is its margin and not the margin and the descender of the line',
	$hookValue( $groundOf( '.nino-card-img--inline' ), 'vertical-align' ) === 'bottom' );
check( '...and the cap of its height, nino-card-img--maxheight, holds the picture in the frame as it holds the frame and the bare <img>: capped itself, the framed picture is cropped at its middle by object-fit and not cut off at the bottom by the frame',
	$hookValue( $groundOf( '.nino-card-img--maxheight' ), 'max-height' ) === 'var(--card-img-max-height)'
	&& $hookValue( $groundOf( '.nino-image.nino-card-img--maxheight > img' ), 'max-height' ) === 'var(--card-img-max-height)' );
check( 'it is the frame that cuts a picture: .nino-image has the overflow, so the corners it has are the picture\'s',
	$hookValue( $groundOf( '.nino-image' ), 'overflow' ) === 'hidden' );

// A section with a picture behind it lays its row out in the middle of itself
$groundPictured = '.nino-section:not(.nino-cover, .nino-parallex):has(> .nino-section-bg)';
check( 'a section with a picture behind it that is no cover and no parallax is a flex column that centres its row, and nino-cover-top and nino-cover-bottom keep the row at the top and at the bottom, as they do for a cover',
	$groundSets( $groundOf( $groundPictured ), 'display', 'flex' ) === true && $groundSets( $groundOf( $groundPictured ), 'flex-direction', 'column' ) === true && $groundSets( $groundOf( $groundPictured ), 'justify-content', 'center' ) === true
	&& $groundSets( $groundOf( $groundPictured. '.nino-cover-top' ), 'justify-content', 'flex-start' ) === true && $groundSets( $groundOf( $groundPictured. '.nino-cover-bottom' ), 'justify-content', 'flex-end' ) === true
	&& $groundSets( $groundOf( '.nino-cover.nino-cover-top > .nino-grid-row' ), 'top', '0' ) === true );

/*	What a cover centres - the row of a cover and of a parallax, the content of a hand-written one, the arrow of a hero - is centred by the translate property and not by transform. The viewport animation of
	the same element (nino-vpa, which the Builder writes on the row) is a transform, and of two rules for one property one wins: in the standard mode the row stayed centred and did not slide in, in the repeat
	mode it stood half its own height too low (measured: 54.9px for a row of 109.8px at 1280). translate composes with transform instead. The rules that put the row or the content at the top or at the bottom
	take the centring back with translate: none, and not one of these rules says transform - nor does the bounce of the arrow say how it is centred	*/
$centredBy = [
	'.nino-cover > .nino-grid-row' => '0 -50%', '.nino-parallex > .nino-grid-row' => '0 -50%',
	'.nino-cover > .nino-cover-content' => '0 -50%', '.nino-parallex > .nino-cover-content' => '0 -50%',
	'.nino-hero-arrowdown' => '-50% 0',
];
foreach( [ '.nino-cover', '.nino-parallex' ] as $centredCover )
	foreach( [ 'top', 'bottom' ] as $centredEdge )
		foreach( [ '.nino-grid-row', '.nino-cover-content' ] as $centredPart )
			$centredBy[ $centredCover. '.nino-cover-'. $centredEdge. ' > '. $centredPart ] = 'none';
$centredWrong = [];
foreach( $centredBy as $centredSelector => $centredValue )
	if( $groundSets( $groundOf( $centredSelector ), 'translate', $centredValue ) === false || preg_match( '/(?<![\w-])transform\s*:/', $groundOf( $centredSelector ) ) === 1 )
		$centredWrong[] = $centredSelector;
preg_match( '/@keyframes\s+nino-hero-arrowdown-bounce\s*\{((?:[^{}]|\{[^{}]*\})*)\}/', $hookCss, $centredBounce );
check( 'what a cover centres is centred by translate and not by transform, so that a viewport animation of the same element composes with the centring instead of replacing it: the row of a cover and of a parallax and the content of a hand-written one by 0 -50%, the arrow of a hero by -50% 0, and the rules that put the row or the content at the top or at the bottom say translate: none - not one of them says transform, and the bounce of the arrow moves it up and down and says nothing of how it is centred'
	. ( $centredWrong === [] ? '' : ' - not so: '. implode( ' | ', $centredWrong ) ),
	$centredWrong === [] && str_contains( $centredBounce[1] ?? '', 'translateY' ) === true && str_contains( $centredBounce[1] ?? '', 'translateX' ) === false );

/*	The row of a cover - a direct child of the section, which no hand-written cover has: its row stands in .nino-cover-content - has what the content of a hand-written cover has: the air above
	it, a step of the space scale, and from 768px the side padding of the section, which a placed box does not stand in. The air is in a rule one class heavy, everything but the one class of the
	row being asked for in :where(), that stands after the base rule of every row and before the padding classes, so that a nino-pt-<n> on the row says what it says. A row that paints a ground has
	the room above and below of its own, the space step times the whitespace, from the rule of the grounds, and is left out of the air: the unscaled step above it replaced the top of such a row and
	left the other three sides to follow the whitespace (measured with nino-whitespace--most: 36px above and 72px below at 1280, 32px and 64px at 390, where a plain section gives 72px and 64px
	both ways). What the air leaves out is every selector of the rule of the grounds, as that rule lists them, read from the stylesheet - a ground it gains is left out in the same change. The
	sides are left and right, the space step times the whitespace, in one rule each - and what keeps the edge it had is left out of it: the section that says nino-pl-0 or nino-pr-0, the row that
	says one of them, the row with no maximum width, and the padded cover, which has its offsets from --cover-padding-x. The width is what the two offsets leave, and not the 100% every row says	*/
$coverAirBase = ':where(.nino-parallex, .nino-cover) > .nino-grid-row';
$coverAirRules = [];
foreach( $groundMembers as $airIndex => $airList )
	foreach( $airList as $airMember )
		if( str_starts_with( $airMember, $coverAirBase ) === true )
			$coverAirRules[$airIndex] = substr( $airMember, strlen( $coverAirBase ) );
$airAt = array_key_first( $coverAirRules ) ?? -1;
$airRest = $coverAirRules[$airAt] ?? '';
$airLeft = [];
if( preg_match_all( '/:not\(((?:[^()]|\([^()]*\))*)\)/', $airRest, $airNots ) > 0 )
	foreach( $airNots[1] as $airNot )
		$airLeft = array_merge( $airLeft, $groundSplit( $airNot ) );
$airGround = array_map( static fn( string $member ): string => (string) preg_replace( '/^\.nino-grid-row:where\((.*)\)$/', '$1', $member ), $groundMembers[ $groundLastOf( [ '.nino-grid-row--alt' ], 'padding' ) ] ?? [] );
$airWrong = [];
if( count( $coverAirRules ) !== 1 )
	$airWrong[] = count( $coverAirRules ). ' rules say the air';
if( $groundSets( $groundBlocks[$airAt][2] ?? '', 'padding-top', 'var(--space-3)' ) === false )
	$airWrong[] = 'the air is not the space step';
if( preg_match( '/^:where\((?::not\((?:[^()]|\([^()]*\))*\))+\)$/', $airRest ) !== 1 )
	$airWrong[] = 'what it leaves out is not asked for in one :where(), so that the rule is one class heavy: '. $airRest;
if( $groundLastOf( [ '.nino-grid-row' ], 'padding' ) >= $airAt || $airAt >= $groundLastOf( [ '.nino-pt-0' ] ) )
	$airWrong[] = 'it does not stand after the base rule of the row and before the padding classes';
if( $airGround === [] || array_diff( $airGround, $airLeft ) !== [] || array_diff( $airLeft, $airGround ) !== [] )
	$airWrong[] = 'it does not leave out the selectors of the rule of the grounds, '. implode( ' | ', $airGround ). ' - it leaves out '. implode( ' | ', $airLeft );
check( 'the row of a cover has a step of the space scale above its content, as the content of a hand-written cover has - in a rule one class heavy that stands after the base rule of the row and before the padding classes, so that a nino-pt-<n> on the row says what it says - and a row that paints a ground, which has the room above and below of its own, the space step times the whitespace, is left out of it: every selector of the rule of the grounds'
	. ( $airWrong === [] ? '' : ' - '. implode( '; ', $airWrong ) ), $airWrong === [] );
$coverInset = [];
foreach( $groundEach as $insetSelector => $insetBodies )
	if( preg_match( '/^\.nino-cover:not\(([^()]*)\) > \.nino-grid-row:not\(([^()]*)\)$/', (string) $insetSelector, $insetFound ) === 1 )
		foreach( [ 'left', 'right' ] as $insetSide )
			if( preg_match( '/(?<![\w-])'. $insetSide. '\s*:/', implode( ' ', $insetBodies ) ) === 1 )
				$coverInset[$insetSide] = [ 'cover' => array_map( 'trim', explode( ',', $insetFound[1] ) ), 'row' => array_map( 'trim', explode( ',', $insetFound[2] ) ), 'body' => implode( ' ', $insetBodies ), 'selector' => (string) $insetSelector ];
$insetWrong = [];
foreach( [ 'left' => 'pl', 'right' => 'pr' ] as $insetSide => $insetClass ) {
	$inset = $coverInset[$insetSide] ?? null;
	if( $inset === null ) {
		$insetWrong[] = 'no rule says '. $insetSide;
		continue;
	}
	if( $groundSets( $inset['body'], $insetSide, 'calc(var(--space-3) * var(--whitespace-scale))' ) === false )
		$insetWrong[] = $insetSide. ' is not the space step times the whitespace';
	foreach( [ 'cover' => [ '.nino-cover--padded', '.nino-'. $insetClass. '-0' ], 'row' => [ '.nino-'. $insetClass. '-0', '.nino-grid-row--full' ] ] as $insetPart => $insetKept )
		if( array_diff( $insetKept, $inset[$insetPart] ) !== [] )
			$insetWrong[] = $insetSide. ' does not leave out '. implode( ', ', array_diff( $insetKept, $inset[$insetPart] ) );
	if( $groundConditions( $inset['selector'] ) !== [ [ '@media (min-width: 768px)' ] ] )
		$insetWrong[] = $insetSide. ' is not for the cover from 768px alone';
}
check( 'from 768px the row of a cover - a parallax with a cover height is one - stands inside the side padding of the section - the space step times the whitespace on the left and on the right, as wide as the two leave it - and a side keeps the edge it had where the section says nino-pl-0 or nino-pr-0 and where the row does, for the row with no maximum width and for the padded cover'
	. ( $insetWrong === [] ? '' : ' - '. implode( '; ', $insetWrong ) ),
	$insetWrong === [] && count( $coverInset ) === 2 && $groundSets( $groundOf( '.nino-cover > .nino-grid-row' ), 'width', 'auto' ) === true
	&& in_array( [ '@media (min-width: 768px)' ], $groundConditions( '.nino-cover > .nino-grid-row' ), true ) === true );

/*	A parallax that is no cover - no cover height, so no JS height - sizes itself to what it holds: a flex column that centres its row in the 60vh it asks for at least, and keeps it at the top or at
	the bottom with nino-cover-top and nino-cover-bottom, as the section with a picture behind it does. Its row and the content of a hand-written one stand in the flow, over the picture and the scrim -
	position: relative for the z-index, 101 - and take back every offset, centring and bottom of the placed ones, the padded cover's included, which is why the rule stands after the last of them. From
	768px the row stands in the side padding of the section, which is the inset the placed row had as an offset, and the offsets of the placed row are the cover's alone. A parallax that is
	a cover as well is a cover: its row stays placed, absolute and centred by translate, and the cover is no flex box. The old rule took the row out of the flow of a section that has no height but its
	minimum, and a row taller than that was cut (measured: a row of 1140px in a section of 60vh = 540px at 900px of window, 600px of the row gone)	*/
$flowSection = '.nino-parallex:not(.nino-cover)';
$flowInsetWrong = [];
foreach( array_keys( $groundEach ) as $flowEach )
	if( str_contains( (string) $flowEach, '.nino-parallex' ) === true && $groundConditions( (string) $flowEach ) === [ [ '@media (min-width: 768px)' ] ] )
		$flowInsetWrong[] = $flowEach. ' is a rule of the parallax from 768px';
foreach( [ 'left', 'right' ] as $flowSide )
	if( preg_match( '/(?<![\w-])'. $flowSide. '\s*:\s*calc\(/', implode( ' ', array_map( $groundOf, array_filter( array_keys( $groundEach ), static fn( mixed $selector ): bool => str_contains( (string) $selector, '.nino-parallex' ) === true ) ) ) ) === 1 )
		$flowInsetWrong[] = 'a rule of the parallax says '. $flowSide. ' by a calculation, an offset of the placed row';
$flowFull = '.nino-parallex:not(.nino-cover, .nino-cover--padded):has(> .nino-grid-row--full)';
if( $groundSets( $groundOf( $flowFull ), 'padding-left', '0' ) === false || $groundSets( $groundOf( $flowFull ), 'padding-right', '0' ) === false )
	$flowInsetWrong[] = 'a row with no maximum width does not go to the edge of a parallax that is no cover: the section does not give up its side padding for it';
if( $groundSets( $groundOf( '.nino-parallex.nino-hero--fullscreen' ), 'min-height', '100vh' ) === false || $groundLastOf( [ '.nino-parallex.nino-hero--fullscreen' ] ) <= $groundLastOf( [ '.nino-parallex' ], 'min-height' ) )
	$flowInsetWrong[] = 'a fullscreen hero that is a parallax is not a screen high: its minimum height does not come after the 60vh of the parallax';
$flowRows = [ $flowSection. ' > .nino-grid-row', $flowSection. ' > .nino-cover-content' ];
$flowWrong = [];
foreach( [ '' => 'center', '.nino-cover-top' => 'flex-start', '.nino-cover-bottom' => 'flex-end' ] as $flowVariant => $flowJustify )
	if( $groundSets( $groundOf( $flowSection. $flowVariant ), 'justify-content', $flowJustify ) === false )
		$flowWrong[] = $flowSection. $flowVariant. ' does not say justify-content: '. $flowJustify;
foreach( [ 'display' => 'flex', 'flex-direction' => 'column' ] as $flowProperty => $flowValue )
	if( $groundSets( $groundOf( $flowSection ), $flowProperty, $flowValue ) === false )
		$flowWrong[] = $flowSection. ' does not say '. $flowProperty. ': '. $flowValue;
foreach( $flowRows as $flowRow ) {
	foreach( [ 'position' => 'relative', 'top' => 'auto', 'bottom' => 'auto', 'left' => '0', 'translate' => 'none', 'z-index' => '101' ] as $flowProperty => $flowValue )
		if( $groundSets( $groundOf( $flowRow ), $flowProperty, $flowValue ) === false )
			$flowWrong[] = $flowRow. ' does not say '. $flowProperty. ': '. $flowValue;
	if( $groundLastOf( [ $flowRow ], 'position' ) <= $groundLastOf( [ '.nino-cover--padded.nino-cover-bottom > .nino-cover-content', '.nino-cover--padded.nino-cover-bottom > .nino-grid-row' ], 'bottom' ) )
		$flowWrong[] = $flowRow. ' does not stand after the last rule that places a row';
}
if( $groundSets( $groundOf( $flowSection. ' > .nino-cover-content' ), 'padding-left', '0' ) === false || $groundSets( $groundOf( $flowSection. ' > .nino-cover-content' ), 'padding-right', '0' ) === false )
	$flowWrong[] = 'the content of a hand-written one keeps the padding of the section it inherits, which it stands inside already';
if( $groundSets( $groundOf( $flowSection. ' > .nino-grid-row' ), 'width', '100%' ) === false )
	$flowWrong[] = 'the row of the flow has no width of 100% to set against the auto of the padded cover';
if( $groundConditions( $flowSection ) !== [ [] ] || $groundConditions( $flowSection. ' > .nino-grid-row' ) !== [ [] ] )
	$flowWrong[] = 'the section and its row are not for every width';
check( 'a parallax that is no cover sizes itself to what it holds - a flex column that centres its row, nino-cover-top and nino-cover-bottom at the start and the end - and its row and its content stand in the flow, relative and over the picture, with the offsets, the centring and the bottom of the placed ones taken back by rules that stand after them'
	. ( $flowWrong === [] ? '' : ' - '. implode( '; ', $flowWrong ) ), $flowWrong === [] );
check( '...and it takes no part in the offsets of the placed row from 768px, which would shift a row in the flow by them: the side padding of the section is the inset it stands in, and the rules of the offsets name the cover alone - a row with no maximum width goes to the edge, the section giving its side padding up for it, and a fullscreen hero that is a parallax is a screen high'
	. ( $flowInsetWrong === [] ? '' : ' - '. implode( '; ', $flowInsetWrong ) ), $flowInsetWrong === [] );
$coverStays = [ 'position' => 'absolute', 'top' => '50%', 'translate' => '0 -50%', 'z-index' => '101' ];
$coverWrong = [];
foreach( $coverStays as $coverProperty => $coverValue )
	if( $groundSets( $groundOf( '.nino-cover > .nino-grid-row' ), $coverProperty, $coverValue ) === false )
		$coverWrong[] = 'the row of a cover does not say '. $coverProperty. ': '. $coverValue;
if( preg_match( '/(?<![\w-])display\s*:/', $groundOf( '.nino-cover' ) ) === 1 || preg_match( '/(?<![\w-])display\s*:/', $groundOf( '.nino-parallex' ) ) === 1 )
	$coverWrong[] = 'a cover or the parallax itself is a flex box';
if( $groundSets( $groundOf( '.nino-parallex' ), 'min-height', '60vh' ) === false || $groundSets( $groundOf( '.nino-parallex' ), 'overflow', 'hidden' ) === false && $groundSets( $groundOf( '.nino-parallex, .nino-cover' ), 'overflow', 'hidden' ) === false )
	$coverWrong[] = 'the parallax lost its minimum height or its overflow';
check( '...and a cover - a parallax that says nino-cover is one - is unchanged: its row is placed, absolute at the middle and centred by translate over the height the JS gives it, and neither it nor the parallax as such is a flex box'
	. ( $coverWrong === [] ? '' : ' - '. implode( '; ', $coverWrong ) ), $coverWrong === [] );
$demoPage = (string) file_get_contents( __DIR__. '/../_admin/install/library/pages/.demo-catalogue/templates/.demo-catalogue.tpl' );
check( '...and the demo shows both shapes: a parallax with a cover height - nino-cover and data-cover-height, placed and sized by the script - and one without, which has neither and whose row stands in the flow of a section that grows with it',
	preg_match( '/<section id="hero-parallax" class="[^"]*\bnino-parallex\b[^"]*\bnino-cover\b[^"]*" data-cover-height="100"/', $demoPage ) === 1
	&& preg_match( '/<section id="parallax-grows" class="(?![^"]*\bnino-cover\b)[^"]*(?<![\w-])nino-parallex(?![\w-])[^"]*"(?![^>]*data-cover-height)[^>]*>\s*<img [^>]*>\s*<div class="nino-grid-row/', $demoPage ) === 1 );

/*	A section is the containing block of what is placed in it - the arrow at its foot, the picture behind it - whichever kind of section it is. A plain section and one whose picture stands in its row had
	none, so that the arrow lay at the foot of the first screen of the page (measured: 788px to 828px at 390px, under a section that begins 1177px down), as the cover, the parallax and the section with a
	picture behind it, which were placed, never had it. The section is placed and makes no stacking context - no z-index, no isolation, no property that creates one - so that the layers inside it order in
	the context round it, as they did when it was not placed. Every rule of the section element is asked - the one of its own class, those of its grounds and its borders
	(.nino-section--<x>) and those of a cover, a parallax and a hero, which are classes of the same element, each alone or compounded with a class or a pseudo-class; a
	pseudo-element of a section (the scrim of a dimmed cover) is a box of its own, which may order what it holds	*/
$sectionLayers = [];
foreach( $groundEach as $sectionSelector => $sectionBodies )
	if( str_contains( (string) $sectionSelector, '::' ) === false && preg_match( '/^\.nino-(?:section|cover|parallex|hero)(?:--[\w-]+)?(?:[:.\[][^\s>+~]*)*$/', (string) preg_replace( '/\((?:[^()]|\([^()]*\))*\)/', '()', (string) $sectionSelector ) ) === 1 )
		foreach( [ 'z-index', 'isolation', 'transform', 'translate', 'rotate', 'scale', 'opacity', 'filter', 'backdrop-filter', 'perspective', 'contain', 'will-change', 'mix-blend-mode', 'clip-path', 'mask-image' ] as $sectionLayer )
			if( preg_match( '/(?<![\w-])'. preg_quote( $sectionLayer, '/' ). '\s*:/', implode( ' ', $sectionBodies ) ) === 1 )
				$sectionLayers[] = $sectionSelector. ' says '. $sectionLayer;
$sectionArrow = $groundOf( '.nino-hero-arrowdown' );
check( 'a section is placed, once, in the rule of the section itself - position: relative - so that the arrow at its foot, which is absolute and has a bottom and no top, stands at the foot of its own section on every kind of section',
	count( array_filter( $groundEach['.nino-section'] ?? [], static fn( string $body ): bool => $groundSets( $body, 'position', 'relative' ) ) ) === 1
	&& $groundSets( $sectionArrow, 'position', 'absolute' ) === true && $hookValue( $sectionArrow, 'bottom' ) !== null && $hookValue( $sectionArrow, 'top' ) === null );
check( '...and no rule of a section - of its own class, of a ground or a border of it, of a cover, a parallax or a hero, alone or compounded - makes it a stacking context: not a z-index, an isolation, a transform, an opacity or a filter, which would take what is inside it out of the order of the page round it'
	. ( $sectionLayers === [] ? '' : ' - '. implode( '; ', $sectionLayers ) ), $sectionLayers === [] && isset( $groundEach['.nino-section'] ) === true );

// The padded cover
$paddedPair = 'var(--cover-padding-y) var(--cover-padding-x)';
$paddedPicture = [ '.nino-cover--padded > .nino-section-bg', '.nino-cover--padded.nino-cover--dim::before', '.nino-cover--padded.nino-parallex--dim::before', '.nino-cover--padded:not(.nino-parallex) > img' ];
$paddedWrong = array_filter( $paddedPicture, static fn( string $selector ): bool => $groundSets( $groundOf( $selector ), 'border-radius', 'var(--radius-cover)' ) === false );
check( 'a padded cover keeps the padding of its section and lays its picture inside it: --cover-padding-y and --cover-padding-x are its padding and place the picture of a Builder section, the image straight in a cover and the scrim of a dimmed cover, and the picture has the radius of the cover kind'
	. ( $paddedWrong === [] ? '' : ' - square: '. implode( ', ', $paddedWrong ) ),
	$paddedWrong === [] && $groundSets( $groundOf( '.nino-cover--padded' ), 'padding', $paddedPair ) === true
	&& $groundSets( $groundOf( '.nino-cover--padded > .nino-section-bg' ), 'inset', $paddedPair ) === true && $groundSets( $groundOf( '.nino-cover--padded.nino-cover--dim::before' ), 'inset', $paddedPair ) === true
	&& $groundSets( $groundOf( '.nino-cover--padded.nino-parallex--dim::before' ), 'inset', $paddedPair ) === true
	&& $groundSets( $groundOf( '.nino-cover--padded:not(.nino-parallex) > img' ), 'top', 'var(--cover-padding-y)' ) === true && $groundSets( $groundOf( '.nino-cover--padded:not(.nino-parallex) > img' ), 'left', 'var(--cover-padding-x)' ) === true );
// What the section's padding is, said in the two properties: the same distances at the same widths - and what the section leaves out, the cover leaves out
$paddingParts = static function( string $value ): array {
	$parts = [];
	$depth = 0;
	$current = '';
	foreach( str_split( trim( $value ) ) as $char ) {
		if( $char === '(' )
			$depth++;
		if( $char === ')' )
			$depth--;
		if( $char === ' ' && $depth === 0 ) {
			$parts[] = $current;
			$current = '';
			continue;
		}
		$current .= $char;
	}
	$parts[] = $current;
	return $parts;
};
$sectionPadding = [];
foreach( $groundEach['.nino-section'] ?? [] as $groundBody )
	if( preg_match( '/(?<![\w-])padding\s*:\s*([^;]+);/', $groundBody, $paddingFound ) === 1 )
		$sectionPadding[] = $paddingParts( $paddingFound[1] );
$coverPadding = [];
foreach( $groundEach['.nino-cover--padded'] ?? [] as $groundBody )
	if( preg_match( '/--cover-padding-y\s*:\s*([^;]+);/', $groundBody, $coverY ) === 1 && preg_match( '/--cover-padding-x\s*:\s*([^;]+);/', $groundBody, $coverX ) === 1 )
		$coverPadding[] = [ trim( $coverY[1] ), trim( $coverX[1] ) ];
check( '...its distances are the section\'s - above and below, and at the sides from 768px; below 768px the section has no side padding and the cover has none',
	count( $sectionPadding ) === 2 && count( $coverPadding ) === 2
	&& $sectionPadding[0][1] === '0' && $coverPadding[0] === [ $sectionPadding[0][0], '0px' ] && $coverPadding[1] === [ $sectionPadding[1][0], $sectionPadding[1][1] ] );
/*	The frame follows the content to the edge. A row that stands at both edges, nino-pl-0 and nino-pr-0, has no side padding of the section round it, and a section with no padding above and below,
	nino-pt-0 and nino-pb-0, none there: the inset of the picture, the scrim and the row is that padding, so each takes it away - both of a direction, since the two sides are one property. The sides
	at every width or from 768px, where the section has its padding; above and below from 768px only, as the padding of the section there is	*/
$sideConditions = [ [ [] ], [ [ '@media (min-width: 768px)' ] ] ];
$frameSides = '.nino-cover--padded:has(> .nino-grid-row.nino-pl-0.nino-pr-0)';
$frameAbove = '.nino-cover--padded.nino-pt-0.nino-pb-0';
check( '...and its frame goes with the content to the edge: no inset at the sides where its row says nino-pl-0 and nino-pr-0, at every width the section has one, and none above and below where its section says nino-pt-0 and nino-pb-0, from 768px',
	$groundSets( $groundOf( $frameSides ), '--cover-padding-x', '0px' ) === true && in_array( $groundConditions( $frameSides ), $sideConditions, true ) === true
	&& $groundSets( $groundOf( $frameAbove ), '--cover-padding-y', '0px' ) === true && $groundConditions( $frameAbove ) === [ [ '@media (min-width: 768px)' ] ] );
/*	Nothing else takes a section or its cover to the edge. A distance of the padded cover that is 0 is the cover's own at the sides below 768px and the two frames above; a side of a section's padding that is
	0 is the section's own below 768px and the edge rule of each side; and the one row without a maximum width is the row that says so in a class. A rule that does the same under any other selector - a class of
	the section, a pair of classes of the cover, a row inside a section - is found here, whatever it is called, and so is a second rule under one of the selectors that may:
	rules are counted, not selectors	*/
$zeroSides = static fn( string $body ): bool => preg_match( '/(?<![\w-])padding-(?:top|right|bottom|left)\s*:\s*0(?:px)?\s*(?:;|$)/', $body ) === 1
	|| ( preg_match( '/(?<![\w-])padding\s*:\s*([^;]+)/', $body, $zeroShort ) === 1 && array_intersect( $paddingParts( $zeroShort[1] ), [ '0', '0px' ] ) !== [] );
$zeroFound = [ 'cover-x' => [], 'cover-y' => [], 'section' => [], 'row' => [] ];
foreach( $groundEach as $zeroSelector => $zeroBodies )
	foreach( $zeroBodies as $zeroBody ) {
		foreach( [ 'x', 'y' ] as $zeroAxis )
			if( preg_match( '/(?<![\w-])--cover-padding-'. $zeroAxis. '\s*:\s*0(?:px)?\s*(?:;|$)/', $zeroBody ) === 1 )
				$zeroFound['cover-'. $zeroAxis][] = (string) $zeroSelector;
		if( preg_match( '/\.nino-section(?:--[\w-]+)?(?![\w-])/', (string) $zeroSelector ) === 1 && $zeroSides( $zeroBody ) === true )
			$zeroFound['section'][] = (string) $zeroSelector;
		if( preg_match( '/\.nino-grid-row(?:--[\w-]+)?(?![\w-])/', (string) $zeroSelector ) === 1 && $groundSets( $zeroBody, 'max-width', 'none' ) === true )
			$zeroFound['row'][] = (string) $zeroSelector;
	}
$zeroWrong = [];
foreach( [ 'cover-x' => [ '.nino-cover--padded', $frameSides ], 'cover-y' => [ $frameAbove ],
	'section' => [ '.nino-section', '.nino-section:has(> .nino-grid-row.nino-pl-0)', '.nino-section:has(> .nino-grid-row.nino-pr-0)' ], 'row' => [ '.nino-grid-row--full', ':where(.nino-tiles) > .nino-grid-row' ] ] as $zeroKey => $zeroList ) {
	$zeroGot = $zeroFound[$zeroKey];
	sort( $zeroGot );
	sort( $zeroList );
	if( $zeroGot !== $zeroList ) {
		$zeroMore = $zeroGot;
		foreach( $zeroList as $zeroOne ) {
			$zeroAt = array_search( $zeroOne, $zeroMore, true );
			if( $zeroAt !== false )
				unset( $zeroMore[$zeroAt] );
		}
		$zeroWrong[] = $zeroKey. ' - also: '. implode( ' | ', $zeroMore ). '; not: '. implode( ' | ', array_diff( $zeroList, $zeroGot ) );
	}
}
check( '...and nothing else takes a section or its cover to the edge: a distance of the cover that is 0 is its own below 768px and the two frames, a side of a section\'s padding that is 0 is the section\'s own below 768px and the edge rule of each side, and the rows with no maximum width are .nino-grid-row--full and the row of a section of tiles'
	. ( $zeroWrong === [] ? '' : ' - '. implode( '; ', $zeroWrong ) ), $zeroWrong === [] );
check( '...and the content stands over the picture as it does in any cover: the row and the content of a hand-written cover keep to the picture\'s width - the row as wide as the two offsets leave it, not the 100% every row says - and to its top or its bottom edge where the cover says so',
	$groundSets( $groundOf( '.nino-cover--padded > .nino-grid-row' ), 'left', 'var(--cover-padding-x)' ) === true && $groundSets( $groundOf( '.nino-cover--padded > .nino-grid-row' ), 'right', 'var(--cover-padding-x)' ) === true
	&& $hookValue( $groundOf( '.nino-cover--padded > .nino-grid-row' ), 'width' ) === 'auto'
	&& $groundSets( $groundOf( '.nino-cover--padded > .nino-cover-content' ), 'left', 'var(--cover-padding-x)' ) === true && $groundSets( $groundOf( '.nino-cover--padded > .nino-cover-content' ), 'right', 'var(--cover-padding-x)' ) === true
	&& $groundSets( $groundOf( '.nino-cover--padded.nino-cover-top > .nino-grid-row' ), 'top', 'var(--cover-padding-y)' ) === true && $groundSets( $groundOf( '.nino-cover--padded.nino-cover-top > .nino-cover-content' ), 'top', 'var(--cover-padding-y)' ) === true
	&& $groundSets( $groundOf( '.nino-cover--padded.nino-cover-bottom > .nino-grid-row' ), 'bottom', 'var(--cover-padding-y)' ) === true && $groundSets( $groundOf( '.nino-cover--padded.nino-cover-bottom > .nino-cover-content' ), 'bottom', 'var(--cover-padding-y)' ) === true );

// The four positions of the whitespace as classes: the outer steps of the ladder around the 1 of the page, on any element
$whitespaceClasses = [ 'least' => '.75', 'less' => '.9', 'more' => '1.5', 'most' => '2' ];
$whitespaceWrong = [];
foreach( $whitespaceClasses as $whitespaceWord => $whitespaceNumber ) {
	$whitespaceBody = $groundOf( '.nino-whitespace--'. $whitespaceWord );
	if( $hookValue( $whitespaceBody, '--whitespace-scale' ) !== $whitespaceNumber )
		$whitespaceWrong[] = '.nino-whitespace--'. $whitespaceWord. ' is not '. $whitespaceNumber;
	if( $hookValue( $whitespaceBody, '--whitespace-gap' ) !== $hookValue( $hookRoot, '--whitespace-gap' ) || $hookValue( $whitespaceBody, '--whitespace-text' ) !== $hookValue( $hookRoot, '--whitespace-text' ) )
		$whitespaceWrong[] = '.nino-whitespace--'. $whitespaceWord. ' does not say the two strengths again';
	if( $groundLastOf( [ '.nino-whitespace--'. $whitespaceWord ], '--whitespace-scale' ) < 0 )
		$whitespaceWrong[] = '.nino-whitespace--'. $whitespaceWord. ' is in no rule';
}
check( 'the whitespace has four classes, .nino-whitespace--least, --less, --more and --most: the number of their position - .75, .9, 1.5 and 2, the outer steps around the 1 of the page - and the two strengths said again as the formulas of :root, so that they work on any element and what stands in it takes the number'
	. ( $whitespaceWrong === [] ? '' : ' - '. implode( '; ', $whitespaceWrong ) ), $whitespaceWrong === [] && $groundLastOf( [ '.nino-whitespace--most' ] ) > $groundLastOf( [ ':root' ], '--whitespace-scale' ) );

// A row that paints a ground, or has a picture behind it: the grounds of a column for a row, the room above and below the section's step times the whitespace, the gutter at the sides left to the row
$rowWrong = [];
foreach( [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter', 'dim' ] as $rowGround )
	if( $groundSets( $groundOf( '.nino-grid-row--'. $rowGround ), 'padding', 'calc(var(--space-3) * var(--whitespace-scale)) var(--grid-gutter)' ) === false || $groundSets( $groundOf( '.nino-grid-row--'. $rowGround ), 'border-radius', 'var(--radius-row)' ) === false )
		$rowWrong[] = '.nino-grid-row--'. $rowGround;
check( 'a row that paints a ground, the counter and a dimmed one have the section\'s step above and below, times the whitespace, the gutter of a row at the sides, and the radius of the row kind - and so has a row with a picture behind it'
	. ( $rowWrong === [] ? '' : ' - not: '. implode( ', ', $rowWrong ) ),
	$rowWrong === [] && $groundSets( $groundOf( '.nino-grid-row:where(:has(> .nino-grid-row-bg))' ), 'padding', 'calc(var(--space-3) * var(--whitespace-scale)) var(--grid-gutter)' ) === true
	&& $groundSets( $groundOf( '.nino-grid-row:where(:has(> .nino-grid-row-bg))' ), 'border-radius', 'var(--radius-row)' ) === true
	&& $groundLastOf( [ '.nino-grid-row--alt' ], 'padding' ) > $groundLastOf( [ '.nino-grid-row' ], 'padding' ) && preg_match( '/(?<![\w-])max-width\s*:/', $groundOf( '.nino-grid-row--alt' ) ) !== 1 );
check( 'a row may carry a picture, after the pattern of the column\'s: .nino-grid-row-bg has the declarations of .nino-grid-bg, the radius apart, and its image those of the column\'s - lies over the row, cut at its corners and behind its columns - and the row with one is the box it is placed in, a stacking context of its own, and cut at its edge',
	$groundBgBody( '.nino-grid-row-bg' ) !== '' && $groundBgBody( '.nino-grid-row-bg' ) === $groundBgBody( '.nino-grid-bg' ) && $groundBgBody( '.nino-grid-row-bg > img' ) === $groundBgBody( '.nino-grid-bg > img' )
	&& $groundSets( $groundOf( '.nino-grid-row-bg' ), 'border-radius', 'inherit' ) === true
	&& $groundSets( $groundOf( '.nino-grid-row-bg' ), 'z-index', '-1' ) === true && $groundSets( $groundOf( '.nino-grid-row:has(> .nino-grid-row-bg)' ), 'position', 'relative' ) === true
	&& $groundSets( $groundOf( '.nino-grid-row:has(> .nino-grid-row-bg)' ), 'isolation', 'isolate' ) === true && $groundSets( $groundOf( '.nino-grid-row:where(:has(> .nino-grid-row-bg))' ), 'overflow', 'clip' ) === true
	&& preg_match( '/(?<![\w-])overflow\s*:/', $groundOf( '.nino-grid-row:has(> .nino-grid-row-bg)' ) ) !== 1 );
$groundInkRule = static fn( string $selector ): array => array_values( array_filter( $groundEach[$selector] ?? [], static fn( string $body ): bool => str_contains( $body, '--color-subtitle' ) === true ) );
check( 'a dimmed row darkens the picture of its block with the scrim of a dimmed column, a plain section with a picture does the same, and both are the dimmed cover for what is written on them: the one rule of its ink',
	$groundSets( $groundOf( '.nino-grid-row--dim > .nino-grid-row-bg::after' ), 'background-color', 'var(--color-background-dim)' ) === true
	&& $groundBgBody( '.nino-grid-row--dim > .nino-grid-row-bg::after' ) === $groundBgBody( '.nino-grid--dim > .nino-grid-bg::after' )
	&& $groundSets( $groundOf( '.nino-section--dim > .nino-section-bg::after' ), 'background-color', 'var(--color-background-dim)' ) === true
	&& $groundBgBody( '.nino-section--dim > .nino-section-bg::after' ) === $groundBgBody( '.nino-grid--dim > .nino-grid-bg::after' )
	&& $groundInkRule( '.nino-cover--dim' ) !== [] && $groundInkRule( '.nino-cover--dim' ) === $groundInkRule( '.nino-section--dim' ) && $groundInkRule( '.nino-cover--dim' ) === $groundInkRule( '.nino-grid-row--dim' ) && $groundInkRule( '.nino-parallex--dim' ) === $groundInkRule( '.nino-cover--dim' ) );

/*	A row says its maximum width, its place and its padding in classes. --full has no maximum width and keeps the gutter; --left and --right say the margin at one side 0 and
	nothing else, since the row has an auto margin at both sides and the one at the other side takes the room that is left; and the three come after the rule of the row, a rule
	of the same specificity, so that they are the ones that win	*/
$rowBaseAt = $groundLastOf( [ '.nino-grid-row' ], 'max-width' );
$rowWrong = [];
foreach( [ '.nino-grid-row--full' => [ 'max-width', 'none' ], '.nino-grid-row--left' => [ 'margin-left', '0' ], '.nino-grid-row--right' => [ 'margin-right', '0' ] ] as $rowClass => [ $rowProperty, $rowValue ] ) {
	if( $groundSets( $groundOf( $rowClass ), $rowProperty, $rowValue ) === false )
		$rowWrong[] = $rowClass. ' does not say '. $rowProperty. ': '. $rowValue;
	if( $groundLastOf( [ $rowClass ] ) <= $rowBaseAt )
		$rowWrong[] = $rowClass. ' is not after the rule of the row';
}
check( 'a row says its width and its place in classes: .nino-grid-row--full has no maximum width and no padding of its own, --left and --right say the margin at one side 0 and no other, and all three come after the rule of the row'
	. ( $rowWrong === [] ? '' : ' - '. implode( '; ', $rowWrong ) ),
	$rowWrong === [] && $rowBaseAt > 0 && preg_match( '/(?<![\w-])padding/', $groundOf( '.nino-grid-row--full' ) ) !== 1
	&& preg_match( '/(?<![\w-])margin(?!-left)/', $groundOf( '.nino-grid-row--left' ) ) !== 1 && preg_match( '/(?<![\w-])margin(?!-right)/', $groundOf( '.nino-grid-row--right' ) ) !== 1 );

// The sides of a padding have the scale of the margins - 0 and the space steps one to six, the declaration of the class and nothing else - and come after every rule that gives a row its padding
$sideAfter = max( $groundLastOf( [ '.nino-grid-row' ], 'padding' ), $groundLastOf( [ '.nino-grid-row--alt' ], 'padding' ), $groundLastOf( [ '.nino-grid-row:where(:has(> .nino-grid-row-bg))' ], 'padding' ) );
$sideWrong = [];
foreach( [ 'pl' => 'left', 'pr' => 'right' ] as $sideKey => $sideName )
	foreach( [ '0', '1', '2', '3', '4', '5', '6' ] as $sideStep ) {
		$sideClass = '.nino-'. $sideKey. '-'. $sideStep;
		$sideValue = $sideStep === '0' ? '0' : 'var(--space-'. $sideStep. ')';
		if( $groundBgBody( $sideClass ) !== 'padding-'. $sideName. ': '. $sideValue. ';' || $groundSets( $groundOf( '.nino-m'. $sideName[0]. '-'. $sideStep ), 'margin-'. $sideName, $sideValue ) === false )
			$sideWrong[] = $sideClass;
		elseif( $groundLastOf( [ $sideClass ] ) <= $sideAfter )
			$sideWrong[] = $sideClass. ' is before a rule that gives a row its padding';
	}
check( 'the sides of a padding are .nino-pl-<n> and .nino-pr-<n>, 0 to 6, the scale of the margins .nino-ml-<n> and .nino-mr-<n>, and come after every rule that gives a row its padding - the base rule, the grounds and the picture - so that they win'
	. ( $sideWrong === [] ? '' : ' - not: '. implode( '; ', $sideWrong ) ), $sideWrong === [] && $sideAfter > 0 );

/*	A row at the edge takes the section's side padding with it: from 768px the section has one of its own, and the section that holds a row that says nino-pl-0 or nino-pr-0 gives up the
	padding at that side. The selector counts the classes of the row too, so that the rule is heavier than the padding of the section wherever it stands - and it stands where the padding does, in
	the 768px query or in none: in a query of another width a row at the edge would keep the padding of its section from 768px up to that width	*/
$edgeWrong = [];
foreach( [ 'pl' => 'left', 'pr' => 'right' ] as $edgeKey => $edgeName ) {
	$edgeSelector = '.nino-section:has(> .nino-grid-row.nino-'. $edgeKey. '-0)';
	if( $groundBgBody( $edgeSelector ) !== 'padding-'. $edgeName. ': 0;' )
		$edgeWrong[] = $edgeSelector. ' does not say padding-'. $edgeName. ': 0';
	if( in_array( $groundConditions( $edgeSelector ), $sideConditions, true ) === false )
		$edgeWrong[] = $edgeSelector. ' does not stand once, in the 768px query or in none';
}
check( 'a row at the edge takes the section\'s side padding with it: the section with a row that says nino-pl-0 has no padding at the left, the one with nino-pr-0 none at the right, at every width it has one'
	. ( $edgeWrong === [] ? '' : ' - '. implode( '; ', $edgeWrong ) ), $edgeWrong === [] );

/*	The padding of a row with a picture behind it is the rule of the grounds, and no rule that gives it padding is heavier than one class: the base rule of the row, the grounds in the list and the
	padding classes are one class each, so that the order decides. The picture is asked for in :where(), which counts for nothing, and the rule comes after the base rule of the row and ahead of every
	padding class, so that nino-pt-<n> and nino-pl-<n> on such a row are what they say. The other rule of the picture, which makes the row the box it is placed in, has no padding and keeps the two classes of :has()	*/
$innerSelector = '.nino-grid-row:where(:has(> .nino-grid-row-bg))';
$innerWeight = static fn( string $selector ): int => substr_count( (string) preg_replace( '/:where\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', '', $selector ), '.nino-' );
$innerHeavy = [];
foreach( $groundEach as $groundSelector => $groundBodies )
	if( str_contains( (string) $groundSelector, 'nino-grid-row-bg' ) === true && preg_match( '/(?<![\w-])padding/', implode( ' ', $groundBodies ) ) === 1 && $innerWeight( (string) $groundSelector ) > 1 )
		$innerHeavy[] = (string) $groundSelector;
$innerAt = $groundLastOf( [ $innerSelector ], 'padding' );
$innerClassAt = -1;
foreach( $groundMembers as $groundAt => $groundList )
	foreach( $groundList as $groundMember )
		if( preg_match( '/^\.nino-p[tblr]?-\d$/', $groundMember ) === 1 && ( $innerClassAt < 0 || $groundAt < $innerClassAt ) )
			$innerClassAt = $groundAt;
check( 'a row with a picture has its padding of a rule one class heavy, as the rule of the row, the grounds and the padding classes are - the picture asked for in :where() - that comes after the base rule of the row and ahead of the first padding class of the file, so that nino-pt-<n> and nino-pl-<n> on such a row are what they say'
	. ( $innerHeavy === [] ? '' : ' - heavier: '. implode( ' | ', $innerHeavy ) ),
	$groundSets( $groundOf( $innerSelector ), 'padding', 'calc(var(--space-3) * var(--whitespace-scale)) var(--grid-gutter)' ) === true && $innerHeavy === [] && $innerWeight( $innerSelector ) === 1
	&& $groundLastOf( [ '.nino-grid-row' ], 'padding' ) > 0 && $innerAt > $groundLastOf( [ '.nino-grid-row' ], 'padding' ) && $innerClassAt > $innerAt );

// The ink of a text: two classes, the inks a ground resolves, after every rule that sets the colour of a title, a subtitle or a text
$inkAfter = $groundLastOf( [ '.nino-section-title', '.nino-section-subtitle', '.nino-section-text', '.nino-card-title', '.nino-card-subtitle', '.nino-card-descr', '.nino-hero-title', '.nino-hero-subtitle',
	'.nino-section-title--quiet', '.nino-section-title--loud', '.nino-hero-title--quiet', '.nino-hero-subtitle--quiet', '.nino-card-title--quiet' ], 'color' );
check( 'the colour of a text is one of two inks - .nino-ink--muted is the ink the text inherits at four fifths of its strength, mixed with nothing, which fades the colour and not the box as an opacity would, and .nino-ink--accent the accent of the ground, a role - and no light, no dark and no status colour is among them; both come after every rule that sets the colour of a title, a subtitle or a text, so that they win'
	. ( $inkAfter > 0 ? '' : ' - no rule to come after' ),
	$groundSets( $groundOf( '.nino-ink--muted' ), 'color', 'color-mix(in srgb, currentColor 80%, transparent)' ) === true && preg_match( '/(?<![\w-])opacity\s*:/', $groundOf( '.nino-ink--muted' ) ) !== 1
	&& $groundSets( $groundOf( '.nino-ink--accent' ), 'color', 'var(--color-accent)' ) === true
	&& preg_match_all( '/\.nino-ink--[a-z-]+\s*[,{]/', $hookCss ) === 2 && $inkAfter > 0 && $groundLastOf( [ '.nino-ink--muted' ] ) > $inkAfter && $groundLastOf( [ '.nino-ink--accent' ] ) > $inkAfter );

/*	The rule under a section: the strength is its thickness and says nothing of its colour. The colour is --section-border-color, the faint dark line where nothing sets it, and the words that colour it set
	that variable and no property of the border: the faint light line of a dark and a black section, with the strength asked for in :where(), and the brand colour of nino-section--border-primary - each
	one class heavy and the brand colour after the other, so that the colour that comes later wins whichever way round the classes are written. Two classes for the dark line were once heavier than the
	one class of the brand colour, and a primary rule under a dark section came out faint light	*/
$borderStrengths = [ '.nino-section--border-1' => '1px', '.nino-section--border-2' => '.25rem', '.nino-section--border-3' => '.5rem' ];
$borderWrong = [];
foreach( $borderStrengths as $borderClass => $borderWidth ) {
	$borderBody = $groundOf( $borderClass );
	if( $groundSets( $borderBody, 'border-bottom-width', $borderWidth ) === false || $groundSets( $borderBody, 'border-bottom-style', 'solid' ) === false )
		$borderWrong[] = $borderClass. ' does not say its width and its style';
	if( $groundSets( $borderBody, 'border-bottom-color', 'var(--section-border-color, rgba(0,0,0,.1))' ) === false || preg_match_all( '/(?<![\w-])border-bottom-color\s*:/', $borderBody ) !== 1 )
		$borderWrong[] = $borderClass. ' does not take its colour from --section-border-color, and from nowhere else';
	if( preg_match( '/(?<![\w-])border(?:-bottom|-color)?\s*:/', $borderBody ) === 1 )
		$borderWrong[] = $borderClass. ' says the border as a shorthand';
}
$borderSetters = [];
foreach( $groundBlocks as [ , $borderList, $borderBody ] )
	if( preg_match( '/(?<![\w-])--section-border-color\s*:/', $borderBody ) === 1 )
		foreach( $groundSplit( $borderList ) as $borderSelector )
			$borderSetters[$borderSelector] = trim( $borderBody );
$borderDark = ':where('. implode( ', ', array_keys( $borderStrengths ) ). ')';
$borderExpected = [
	'.nino-section--dark'. $borderDark => '--section-border-color: rgba(255,255,255,.1);',
	'.nino-section--black'. $borderDark => '--section-border-color: rgba(255,255,255,.1);',
	'.nino-section--border-primary' => '--section-border-color: var(--color-primary);',
];
ksort( $borderSetters );
ksort( $borderExpected );
if( $borderSetters !== $borderExpected )
	$borderWrong[] = 'the rules that set --section-border-color are '. implode( ' | ', array_keys( $borderSetters ) );
foreach( array_keys( $borderExpected ) as $borderSelector )
	if( $innerWeight( $borderSelector ) !== 1 )
		$borderWrong[] = $borderSelector. ' is not one class heavy';
$borderLight = [ '.nino-section--dark'. $borderDark, '.nino-section--black'. $borderDark ];
check( 'the rule under a section: the strengths, nino-section--border-1 to -3, say its width and its style and no colour of their own - the colour is --section-border-color, the faint dark line where nothing sets it - and the words that colour it set that variable and nothing else: the faint light line of a dark and a black section and the brand colour of nino-section--border-primary, each one class heavy and the brand colour after the other, so that the colour wins whichever way round the classes stand'
	. ( $borderWrong === [] ? '' : ' - '. implode( '; ', $borderWrong ) ),
	$borderWrong === [] && $groundLastOf( [ '.nino-section--border-primary' ] ) > $groundLastOf( $borderLight ) && $groundLastOf( $borderLight ) > 0 );

// The width of a button: as wide as the box it stands in, centred, and said after the sizes, whose display it replaces
check( 'a full-width button is a block as wide as the box it stands in, its label in the middle, and says stretch to the placements of a stack and a flex card - after the small and the big one, which are flex and block of a width of their own',
	$groundSets( $groundOf( '.nino-btn--fullwidth' ), 'display', 'block' ) === true && $groundSets( $groundOf( '.nino-btn--fullwidth' ), 'width', '100%' ) === true && $groundSets( $groundOf( '.nino-btn--fullwidth' ), 'text-align', 'center' ) === true
	&& $groundSets( $groundOf( '.nino-btn--fullwidth' ), 'justify-self', 'stretch' ) === true && $groundSets( $groundOf( '.nino-btn--fullwidth' ), 'align-self', 'stretch' ) === true
	&& $groundLastOf( [ '.nino-btn--fullwidth' ] ) > $groundLastOf( [ '.nino-btn--small', '.nino-btn--big' ] ) && $groundLastOf( [ '.nino-btn--small' ] ) > 0 );

// The arrow of a hero: its chevron is a mask over the colour it inherits, in the two forms a browser reads one by, and no picture of its own colour - on the ::before of the arrow, since a mask clips
// everything its element paints, and the focus ring is an outline outside the box
$arrowBody = $groundOf( '.nino-hero-arrowdown' );
$chevronBody = $groundOf( '.nino-hero-arrowdown::before' );
check( 'the chevron of the arrow of a hero is a mask over currentColor on a ::before that fills the arrow - the one picture in the -webkit- form and the standard one, placed, sized and not repeated alike - and the arrow takes the colour around it: it is no white background image',
	$hookValue( $chevronBody, 'content' ) === '""' && $hookValue( $chevronBody, 'position' ) === 'absolute' && $hookValue( $chevronBody, 'inset' ) === '0'
	&& $hookValue( $chevronBody, 'background-color' ) === 'currentColor' && $hookValue( $arrowBody, 'color' ) === 'inherit' && preg_match( '/(?<![\w-])background-image\s*:/', $arrowBody. ' '. $chevronBody ) !== 1
	&& $hookValue( $chevronBody, 'mask-image' ) !== null && $hookValue( $chevronBody, '-webkit-mask-image' ) === $hookValue( $chevronBody, 'mask-image' ) && str_starts_with( (string) $hookValue( $chevronBody, 'mask-image' ), 'url("data:image/svg+xml,' ) === true
	&& $hookValue( $chevronBody, 'mask-size' ) === 'contain' && $hookValue( $chevronBody, '-webkit-mask-size' ) === 'contain' && $hookValue( $chevronBody, 'mask-position' ) === 'center' && $hookValue( $chevronBody, '-webkit-mask-position' ) === 'center'
	&& $hookValue( $chevronBody, 'mask-repeat' ) === 'no-repeat' && $hookValue( $chevronBody, '-webkit-mask-repeat' ) === 'no-repeat' );
check( '...and the arrow itself wears no mask and paints no ground of its own: a mask clips the focus ring with the rest of what its element paints (measured: a keyboard focus changed no pixel of an arrow that carried the mask), so the arrow is the box and the ring of the page is drawn around it',
	preg_match( '/(?<!\w)(?:-webkit-)?mask(?:-[a-z-]+)?\s*:/', $arrowBody ) !== 1 && $hookValue( $arrowBody, 'background-color' ) === 'transparent' && $hookValue( $arrowBody, 'outline' ) === null );

/*	What the arrow does on its own is bounded: SC 2.2.2 allows five seconds of motion nobody asked for, and a bounce that never ended moved for as long as the page was open.
	Two runs of 1.8s are 3.6s; the bounce effect runs once. Neither takes the ring away - the arrow is a control, and its rule says nothing about the outline	*/
$arrowRun = preg_match( '/^nino-hero-arrowdown-bounce\s+([\d.]+)s\s+[a-z-]+\s+(\d+)$/', (string) $hookValue( $arrowBody, 'animation' ), $arrowFound ) === 1 ? (float) $arrowFound[1] * (int) $arrowFound[2] : -1.0;
check( 'the arrow of a hero stops moving after a few runs, five seconds in all at the most (SC 2.2.2), and so does the bounce effect, which runs once - nothing in the rule of the arrow takes its outline away',
	$arrowRun > 0 && $arrowRun <= 5 && preg_match( '/\binfinite\b/', $arrowBody ) !== 1 && preg_match( '/\binfinite\b/', $groundOf( '.nino-fx-vertical-bounce' ) ) !== 1
	&& preg_match( '/(?<![\w-])outline\s*:\s*(?:none|0)/', $arrowBody. ' '. $chevronBody ) !== 1 );

/*	The accessibility pass, as the stylesheet says it. An opacity moves a colour that a design layer has solved: the muted role is 4.5:1 against its ground, and at .8 of it the words
	read at 3.1:1, where a quieter variant is smaller and no more transparent	*/
$a11yFaded = array_filter( [ '.nino-section-subtitle', '.nino-section-subtitle--quiet', '.nino-section-text--quiet' ], static fn( string $class ): bool => preg_match( '/(?<![\w-])opacity\s*:/', $groundOf( $class ) ) === 1 );
check( 'a subtitle of a section and the quiet variants of a subtitle and a text are no more transparent than their colour is: an opacity put the muted role, solved to 4.5:1, at 3.1:1 - quieter is smaller'. ( $a11yFaded === [] ? '' : ' - faded: '. implode( ', ', $a11yFaded ) ),
	$a11yFaded === [] && $groundSets( $groundOf( '.nino-section-subtitle' ), 'color', 'var(--color-subtitle)' ) === true
	&& str_contains( $groundOf( '.nino-section-subtitle--quiet' ), 'var(--scale-quiet)' ) === true && str_contains( $groundOf( '.nino-section-text--quiet' ), 'var(--scale-quiet)' ) === true );

// The pointer dims the two brand buttons and the focus does not - an opacity takes the ring of the focus with the rest of the element
$a11yDims = [];
foreach( $groundBlocks as [ , $a11ySelectors, $a11yBody ] )
	if( preg_match( '/\.nino-btn--(?:primary|brand-alt)(?![\w-])/', (string) $a11ySelectors ) === 1 && preg_match( '/(?<![\w-])opacity\s*:\s*(?!1\s*;)/', (string) $a11yBody ) === 1 )
		$a11yDims[] = trim( (string) preg_replace( '/\s+/', ' ', (string) $a11ySelectors ) );
check( 'the two brand buttons are dimmed by the pointer and by nothing else: the one rule each has an opacity in says :hover and never :focus, and keeps the dimming off a button the keyboard has focused, so that the ring is never dimmed - in :where(), which leaves the rule as specific as it was for a design layer that takes the dimming back',
	count( $a11yDims ) === 2 && array_filter( $a11yDims, static fn( string $selector ): bool => str_contains( $selector, ':hover' ) === false || preg_match( '/:focus(?!-visible)/', $selector ) === 1
		|| str_contains( $selector, ':where(:not(:focus-visible))' ) === false ) === [] );
check( '...the ring is the one outline of everything that can take the focus, and on a picture it has a second line - the ink of the surface, the colour its words are read in on that picture - for the button of a cover, a parallax and a hero',
	$groundSets( $groundOf( ':where(a, area, button, input, label[tabindex], select, summary, textarea, [contenteditable], [tabindex]):focus-visible' ), 'outline', '2px solid var(--color-focus)' ) === true
	&& $groundSets( $groundOf( ':is(.nino-cover, .nino-parallex, .nino-hero) .nino-btn:focus-visible' ), 'box-shadow', '0 0 0 2px var(--color-ink)' ) === true );

// What a field shows before anything is written in it is the muted role of the ground it stands on, at the strength it was solved for, on a field that has that ground and its ink
// ...the field is painted in --forms-fill, the fill of the form block, whose default on every element is the ground that element stands on - the property a theme moves, and the ground where it moves nothing
$a11yField = array_filter( [ '.nino-form-input', '.nino-form-textarea', '.nino-form-select' ], static fn( string $selector ): bool => $groundSets( $groundOf( $selector ), 'background-color', 'var(--forms-fill)' ) === false || $groundSets( $groundOf( $selector ), 'color', 'var(--color-ink)' ) === false );

if( $groundSets( $groundOf( ':where(*)' ), '--forms-fill', 'var(--color-ground)' ) === false )
	$a11yField[] = ':where(*) - the default of --forms-fill is not the ground';
$a11yHint = array_filter( [ '.nino-form-input::placeholder', '.nino-form-textarea::placeholder' ], static fn( string $selector ): bool => $groundSets( $groundOf( $selector ), 'color', 'var(--color-subtitle)' ) === false || $groundSets( $groundOf( $selector ), 'opacity', '1' ) === false );
check( 'the placeholder of a field is the muted role at full strength, and the field is painted in the ground it stands on and written in its ink: the browser\'s own grey was 2.4:1 on the dark field of a dark page, and the role is measured against a ground'
	. ( $a11yField === [] ? '' : ' - not so: '. implode( ', ', $a11yField ) ). ( $a11yHint === [] ? '' : ' - not so: '. implode( ', ', $a11yHint ) ), $a11yField === [] && $a11yHint === [] );
check( 'a badge is written in the ink of its ground, painted in that ground and edged with its border: the pair a ground promises, whichever it is - the border role is solved to 3:1 and is no surface for text, which black ink on it was on a black section',
	$groundSets( $groundOf( '.nino-badge' ), 'background-color', 'var(--color-ground)' ) === true && $groundSets( $groundOf( '.nino-badge' ), 'color', 'var(--color-ink)' ) === true
	&& $groundSets( $groundOf( '.nino-badge' ), 'border', '1px solid var(--color-border)' ) === true && preg_match( '/(?<![\w-])opacity\s*:/', $groundOf( '.nino-badge' ) ) !== 1 );

// The dot of a slider is a target of 24px round a dot of 12 (SC 2.5.8) with room for its neighbours, and the fade of an inactive slide stays off the button in it, where it took the ring along
$dotSize = (int) $hookValue( $groundOf( '.nino-slider-points li' ), 'width' );
$dotInset = preg_match( '/^-(\d+)px$/', (string) $hookValue( $groundOf( '.nino-slider-point' ), 'inset' ), $dotFound ) === 1 ? (int) $dotFound[1] : 0;
$dotMargin = preg_match( '/^0 (\d+)px$/', (string) $hookValue( $groundOf( '.nino-slider-points li' ), 'margin' ), $dotFound ) === 1 ? (int) $dotFound[1] : 0;
check( 'the dot of a slider is a target of 24px round a dot of 12px, its neighbours leave it the room, and the ring of its button is drawn with a line of ink inside it and in full strength: the opacity of an inactive slide does not reach it',
	$dotSize === 12 && $dotSize + 2 * $dotInset >= 24 && $dotSize + 2 * $dotMargin >= $dotSize + 2 * $dotInset && $groundSets( $groundOf( '.nino-slider-points li' ), 'opacity', '1' ) === true
	&& $groundSets( $groundOf( '.nino-slider-point:focus-visible' ), 'box-shadow', 'inset 0 0 0 2px var(--color-ink)' ) === true );
check( 'the close button of a dialog is a target of 24px each way at least (SC 2.5.8), where it was as wide as the cross it draws',
	preg_match( '/^(\d+)px$/', (string) $hookValue( $groundOf( '.nino-modal-close' ), 'min-width' ), $dotFound ) === 1 && (int) $dotFound[1] >= 24
	&& preg_match( '/^(\d+)px$/', (string) $hookValue( $groundOf( '.nino-modal-close' ), 'min-height' ), $dotFound ) === 1 && (int) $dotFound[1] >= 24 );

// What is written for the keyboard shows itself when it has the focus: a link that hides in the way of .nino-sr-only takes back what hides it, above the fixed header, and the target of the skip link has no ring round the whole of the main part
$skipShown = array_filter( [ [ 'width', 'auto' ], [ 'height', 'auto' ], [ 'clip', 'auto' ], [ 'overflow', 'visible' ], [ 'margin', '0' ], [ 'position', 'fixed' ] ], static fn( array $property ): bool => $groundSets( $groundOf( 'a.nino-sr-only:focus' ), $property[0], $property[1] ) === false );
check( 'a link that is hidden as .nino-sr-only hides it shows itself when it has the focus - its size, its clip, its overflow and its margin taken back, fixed in the corner above the header - and the target of a skip link has no ring round the whole of the main part'. ( $skipShown === [] ? '' : ' - still hidden: '. implode( ', ', array_column( $skipShown, 0 ) ) ),
	$skipShown === [] && (int) $hookValue( $groundOf( 'a.nino-sr-only:focus' ), 'z-index' ) > (int) $hookValue( $groundOf( 'header' ), 'z-index' ) && (int) $hookValue( $groundOf( 'header' ), 'z-index' ) > 0
	&& $groundSets( $groundOf( 'main[tabindex="-1"]:focus-visible' ), 'outline', 'none' ) === true );
check( 'the name of the site written out in the link of the logo is hidden when the link holds a picture, and is the name of the site that a screen reader is spared',
	$groundSets( $groundOf( '.nino-logo:has(img) > span[aria-hidden="true"]' ), 'display', 'none' ) === true );

// The animation
$vpaSeconds = static fn( string $selector ): float => (float) $hookValue( $groundOf( $selector ), '--vpa-duration' );
check( 'a viewport animation lasts a second unless it says otherwise: --speed-medium says the same, --speed-fast is under half of it and --speed-slow twice it',
	$vpaSeconds( ':root' ) > 0 && $vpaSeconds( '.nino-vpa--speed-medium' ) === $vpaSeconds( ':root' ) && $vpaSeconds( '.nino-vpa--speed-fast' ) < $vpaSeconds( ':root' ) / 2 && $vpaSeconds( '.nino-vpa--speed-slow' ) === 2 * $vpaSeconds( ':root' ) );
// Named for the motion the visitor sees: a slide-left starts to the right of its place and slides left, a slide-up starts below it and slides up
$vpaWrong = [];
foreach( [ 'left' => [ 'X', 1 ], 'right' => [ 'X', -1 ], 'up' => [ 'Y', 1 ], 'down' => [ 'Y', -1 ] ] as $vpaDirection => [ $vpaAxis, $vpaSign ] )
	foreach( [ 'soft' => 1.5, 'medium' => 3, 'hard' => 6 ] as $vpaStrength => $vpaRem ) {
		$vpaBody = $groundOf( '.nino-vpa--slide-'. $vpaDirection. '-'. $vpaStrength );
		if( $hookValue( $vpaBody, '--vpa-t-hidden' ) !== 'translate'. $vpaAxis. '('. ( $vpaSign * $vpaRem ). 'rem)' || $hookValue( $vpaBody, '--vpa-t-visible' ) !== 'translate'. $vpaAxis. '(0)' )
			$vpaWrong[] = 'slide-'. $vpaDirection. '-'. $vpaStrength;
	}
check( 'the slides are named for the motion the visitor sees: left and up start at a positive translate of their axis - to the right of their place, below it - right and down at a negative one, at 1.5, 3 and 6rem, and all of them end at 0'
	. ( $vpaWrong === [] ? '' : ' - not: '. implode( ', ', $vpaWrong ) ), $vpaWrong === [] );

echo "\n";


// --- A gap between the columns of a row --------------------------------------

echo "Nino.css - a gap between the columns of a row: nino-grid-gap-<n> on the row, the width classes give it back\n";

/*	The row draws --nino-grid-gap between its columns in both directions and declares 0px, so that a row without a class is what it was. A stack stands in a column of such a row and says 0px as
	well: the property is inherited, and the cells of a stack are columns by their width classes. The seven classes come after the row and the stack, which declare the property, and after the
	classes of the gap of a stack, in their manner: the step of the scale times the whitespace between pieces, the property and nothing else	*/
check( 'the row declares --nino-grid-gap as 0px and draws it as its gap, in both directions - gap and not column-gap',
	$groundSets( $groundOf( '.nino-grid-row' ), '--nino-grid-gap', '0px' ) === true && $groundSets( $groundOf( '.nino-grid-row' ), 'gap', 'var(--nino-grid-gap)' ) === true
	&& preg_match( '/(?<![\w-])(?:column|row)-gap\s*:/', $groundOf( '.nino-grid-row' ) ) !== 1 );
check( '...and the stack resets it to 0px, so that the gap of a section\'s row does not reach the cells of a stack that stands in one of its columns',
	$groundSets( $groundOf( '.nino-stack' ), '--nino-grid-gap', '0px' ) === true );
$gapWrong = [];
foreach( [ 0, 1, 2, 3, 4, 5, 6 ] as $gapStep ) {
	$gapClass = '.nino-grid-gap-'. $gapStep;
	$gapBody = $groundOf( $gapClass );
	if( count( $groundEach[$gapClass] ?? [] ) !== 1 || substr_count( $gapBody, ':' ) !== 1
		|| $groundSets( $gapBody, '--nino-grid-gap', $gapStep === 0 ? '0px' : 'calc(var(--space-'. $gapStep. ') * var(--whitespace-gap))' ) === false )
		$gapWrong[] = $gapClass;
	elseif( $groundLastOf( [ $gapClass ] ) <= $groundLastOf( [ '.nino-grid-row' ] ) || $groundLastOf( [ $gapClass ] ) <= $groundLastOf( [ '.nino-stack' ] ) || $groundLastOf( [ $gapClass ] ) <= $groundLastOf( [ '.nino-stack-gap-6' ] ) )
		$gapWrong[] = $gapClass. ' is not after the row, the stack and the gap classes of a stack';
}
check( '.nino-grid-gap-0 to -6 set --nino-grid-gap to 0px and to the step of the space scale times --whitespace-gap, once each and nothing else, after the row, the stack and the classes of the gap of a stack'
	. ( $gapWrong === [] ? '' : ' - not: '. implode( ', ', $gapWrong ) ), $gapWrong === [] && $groundLastOf( [ '.nino-stack-gap-6' ] ) > 0 );

/*	What the shares come to on a page, measured in headless Chromium at 1280px and at 390px in a row of the grid, the base stylesheet beside this one: with no gap class every rect of
	a row of four quarters, of a third and two thirds and of three thirds is the one the base stylesheet gives (302px a quarter at 1280px, 89.5px at 390px; 402.656px and 805.328px
	for a third and two thirds at 1280px). With nino-grid-gap-2 the gap is 18px at 1280px and 16px at 390px, the quarters are 288.5px and 77.5px, the thirds 390.656px and 108.656px,
	two thirds 799.328px and 233.328px, every row stays on one line and the last column ends at the content edge of the row to within 0.03px. Three columns of 100% below 1024px and
	a third from there stand a gap above one another on a phone, as wide as the row. The stylesheet is read here, the page is not: nothing in the kernel's suites drives a browser	*/
/*	Every width class gives the gap back, at its tier: the base ones at the top of the file, the -s- ones from 640px, the -m- from 768px, the -l- from 1024px, the -xl- from 1280px. The share is that of
	a column of the width in a row of equal columns, (k - 1) / k of a gap: 3/4, 2/3, 1/2, 1/3, 1/4 - and a whole row gives nothing back	*/
$gapShare = [
	'25'	=> 'calc(25% - .75 * var(--nino-grid-gap, 0px))',
	'33'	=> 'calc(100% / 3 - var(--nino-grid-gap, 0px) * 2 / 3)',
	'50'	=> 'calc(50% - .5 * var(--nino-grid-gap, 0px))',
	'66'	=> 'calc(100% / 1.5 - var(--nino-grid-gap, 0px) / 3)',
	'75'	=> 'calc(75% - .25 * var(--nino-grid-gap, 0px))',
	'100'	=> '100%',
];
$gapTiers = [ '' => [], 's-' => [ '@media (min-width: 640px)' ], 'm-' => [ '@media (min-width: 768px)' ], 'l-' => [ '@media (min-width: 1024px)' ], 'xl-' => [ '@media (min-width: 1280px)' ] ];
$gapWrong = [];
foreach( $gapTiers as $gapTier => $gapCondition )
	foreach( $gapShare as $gapWidth => $gapValue ) {
		$gapClass = '.nino-grid-'. $gapTier. $gapWidth;
		if( count( $groundEach[$gapClass] ?? [] ) !== 1 || $groundSets( $groundOf( $gapClass ), 'width', $gapValue ) === false || $groundConditions( $gapClass ) !== [ $gapCondition ] )
			$gapWrong[] = $gapClass;
	}
check( 'the width classes give the gap back in the five tiers - mobile-first, the base ones and the -s-, -m-, -l- and -xl- ones from 640, 768, 1024 and 1280px - 3/4 of it for a quarter, 2/3 for a third, 1/2, 1/3 and 1/4, and nothing for 100'
	. ( $gapWrong === [] ? '' : ' - not: '. implode( ', ', $gapWrong ) ), $gapWrong === [] );

// A card that is a column of the 768px tier has the margins to give up and the share of the gap to give back, in the same calc: three of them in a row with a gap stand on one line
$gapCards = [
	'25'	=> 'calc(25% - var(--space-1) * var(--whitespace-scale) - var(--space-1) * var(--whitespace-scale) - .75 * var(--nino-grid-gap, 0px))',
	'33'	=> 'calc((100% / 3) - var(--space-1) * var(--whitespace-scale) - var(--space-1) * var(--whitespace-scale) - var(--nino-grid-gap, 0px) * 2 / 3)',
	'50'	=> 'calc(50% - var(--space-1) * var(--whitespace-scale) - var(--space-1) * var(--whitespace-scale) - .5 * var(--nino-grid-gap, 0px))',
];
$gapWrong = [];
foreach( $gapCards as $gapWidth => $gapValue ) {
	$gapClass = '.nino-card--grid.nino-grid-m-'. $gapWidth;
	if( count( $groundEach[$gapClass] ?? [] ) !== 1 || $groundSets( $groundOf( $gapClass ), 'width', $gapValue ) === false || $groundConditions( $gapClass ) !== [ [ '@media (min-width: 768px)' ] ] )
		$gapWrong[] = $gapClass;
}
check( '...and so does a card that is a column, from 768px: its margins and its share of the gap, so that three of them in a row with a gap stay on one line'
	. ( $gapWrong === [] ? '' : ' - not: '. implode( ', ', $gapWrong ) ), $gapWrong === [] );

echo "\n";


// --- The radius of a surface ------------------------------------------------

echo "Nino.css - the radius of a surface: two strengths, a token and a marker for each kind, and a class that takes the other\n";

/*	The corners of a surface are the page's: two strengths for the whole page and six kinds of surface, each with a token that points at one of them and a marker that
	says which, as a word - for a tool that wants the default of a kind without resolving a length. What reads a token and what the class of one element sets is pinned by
	selector and never by a length: a design layer says the lengths	*/
$radiusKinds = [ 'cover', 'row', 'column', 'tile', 'card', 'image' ];
$radiusPoints = [ 'standard' => 'var(--radius-surface)', 'alternative' => 'var(--radius-surface-alt)' ];
// A length of the root in rem, a var() followed through the declarations of the root
$radiusLength = static function( string $property ) use ( &$radiusLength, $hookRoot, $hookValue ): ?float {
	$value = $hookValue( $hookRoot, $property );
	if( $value !== null && preg_match( '/^var\((--[\w-]+)\)$/', $value, $radiusRef ) === 1 )
		return $radiusLength( $radiusRef[1] );
	return $value !== null && preg_match( '/^(\d*\.?\d+)rem$/', $value, $radiusNumber ) === 1 ? (float) $radiusNumber[1] : null;
};
$radiusWrong = [];
foreach( [ '--radius-surface', '--radius-surface-alt' ] as $radiusStrength )
	if( $radiusLength( $radiusStrength ) === null )
		$radiusWrong[] = $radiusStrength. ' is no length';
foreach( $radiusKinds as $radiusKind ) {
	$radiusToken = $hookValue( $hookRoot, '--radius-'. $radiusKind );
	$radiusMarker = (string) $hookValue( $hookRoot, '--radius-'. $radiusKind. '-default' );
	if( in_array( $radiusToken, $radiusPoints, true ) === false )
		$radiusWrong[] = '--radius-'. $radiusKind. ' points at none of the two strengths';
	if( ( $radiusPoints[$radiusMarker] ?? null ) !== $radiusToken )
		$radiusWrong[] = '--radius-'. $radiusKind. '-default does not say which strength the token points at';
}
check( ':root declares the two strengths of the radius of a surface - lengths, the alternative the larger - and for each of the six kinds, cover, row, column, tile, card and image, a token that points at one of them and a marker that says which, as the word standard or alternative'
	. ( $radiusWrong === [] ? '' : ' - '. implode( '; ', $radiusWrong ) ), $radiusWrong === [] && $radiusLength( '--radius-surface' ) < $radiusLength( '--radius-surface-alt' ) );
check( '...and a marker draws nothing: no rule of the stylesheet reads one',
	preg_match( '/var\(\s*--radius-(?:'. implode( '|', $radiusKinds ). ')-default/', $hookCss ) !== 1 );

// Each surface reads the token of its kind as its border-radius, and no other rule reads one
$radiusGrounds = [ 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'counter', 'dim' ];
$radiusReaders = [
	'cover'		=> [ '.nino-cover--padded > .nino-section-bg', '.nino-cover--padded.nino-cover--dim::before', '.nino-cover--padded.nino-parallex--dim::before', '.nino-cover--padded:not(.nino-parallex) > img' ],
	'row'			=> array_merge( array_map( static fn( string $ground ): string => '.nino-grid-row--'. $ground, $radiusGrounds ), [ '.nino-grid-row:where(:has(> .nino-grid-row-bg))' ] ),
	'column'	=> array_merge( array_map( static fn( string $ground ): string => '.nino-grid--'. $ground, $radiusGrounds ), [ '.nino-grid-bg', ':has(> .nino-grid-bg)' ] ),
	'tile'		=> [ ':where(.nino-tiles) > .nino-grid-row > *', ':where(.nino-tiles) > .nino-grid-row > * > .nino-grid-bg' ],
	'card'		=> [ '.nino-card', '.nino-card-img', '.nino-card--fullwidth .nino-card-img' ],
	'image'		=> [ '.nino-image:not(.nino-card-img)' ],
];
$radiusWrong = [];
foreach( $radiusReaders as $radiusKind => $radiusExpected ) {
	$radiusFound = [];
	foreach( $groundBlocks as [ , $radiusList, $radiusBody ] )
		if( preg_match( '/(?<![\w-])border-radius\s*:[^;]*var\(\s*--radius-'. $radiusKind. '\s*\)/', $radiusBody ) === 1 )
			$radiusFound = array_merge( $radiusFound, $groundSplit( $radiusList ) );
	sort( $radiusFound );
	sort( $radiusExpected );
	if( $radiusFound !== $radiusExpected )
		$radiusWrong[] = '--radius-'. $radiusKind. ' is read where it should not be: '. implode( ' | ', array_diff( $radiusFound, $radiusExpected ) ). '; not read where it should be: '. implode( ' | ', array_diff( $radiusExpected, $radiusFound ) );
}
check( 'every surface reads the token of its kind as its border-radius - the picture, the box of a parallax and the scrim of a padded cover, a row that paints a ground or holds a picture, a column that paints a ground or holds a picture and its picture, a tile and its picture, a card and its picture, the frame of the image component - and no other rule reads one'
	. ( $radiusWrong === [] ? '' : ' - '. implode( '; ', $radiusWrong ) ), $radiusWrong === [] );

// The exception of one element: two classes, and a rule for each kind and each class, on the element of its kind and setting the token of that kind and no other
$radiusElement = [
	'cover'		=> [ '.nino-cover--padded' ],
	'row'			=> [ '.nino-grid-row' ],
	'column'	=> array_merge( array_map( static fn( string $ground ): string => '.nino-grid--'. $ground, $radiusGrounds ), [ ':has(> .nino-grid-bg)' ] ),
	'tile'		=> [ '.nino-tiles' ],
	'card'		=> [ '.nino-card' ],
	'image'		=> [ '.nino-image' ],
];
$radiusClasses = [ '.nino-radius' => 'var(--radius-surface)', '.nino-radius-alt' => 'var(--radius-surface-alt)' ];
$radiusRules = [];
foreach( $groundBlocks as [ , $radiusList, $radiusBody ] )
	foreach( $groundSplit( $radiusList ) as $radiusSelector )
		if( preg_match( '/\.nino-radius(?:-alt)?$/', $radiusSelector ) === 1 )
			$radiusRules[] = [ $radiusSelector, trim( $radiusBody ) ];
$radiusWrong = [];
$radiusSeen = [];
foreach( $radiusRules as [ $radiusSelector, $radiusBody ] ) {
	$radiusClass = str_ends_with( $radiusSelector, '-alt' ) === true ? '.nino-radius-alt' : '.nino-radius';
	if( preg_match( '/^--radius-('. implode( '|', $radiusKinds ). ')\s*:\s*(var\(--radius-surface(?:-alt)?\))\s*;?$/', $radiusBody, $radiusParts ) !== 1 ) {
		$radiusWrong[] = $radiusSelector. ' sets more than the token of one kind';
		continue;
	}
	$radiusOuter = substr( $radiusSelector, 0, -strlen( $radiusClass ) );
	$radiusSeen[$radiusParts[1]][] = $radiusClass;
	if( $radiusParts[2] !== $radiusClasses[$radiusClass] )
		$radiusWrong[] = $radiusSelector. ' sets the other strength';
	foreach( $radiusElement[$radiusParts[1]] as $radiusNeedle )
		if( str_contains( $radiusOuter, $radiusNeedle ) === false )
			$radiusWrong[] = $radiusSelector. ' does not name '. $radiusNeedle;
	if( preg_match( '/[\s>+~]/', (string) preg_replace( '/\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', '', $radiusOuter ) ) === 1 )
		$radiusWrong[] = $radiusSelector. ' reaches below its element';
}
foreach( $radiusKinds as $radiusKind ) {
	$radiusOnes = $radiusSeen[$radiusKind] ?? [];
	sort( $radiusOnes );
	if( $radiusOnes !== [ '.nino-radius', '.nino-radius-alt' ] )
		$radiusWrong[] = '--radius-'. $radiusKind. ' has the classes: '. implode( ' ', $radiusOnes );
}
check( 'the exception of one element is two classes, nino-radius and nino-radius-alt: twelve rules, one for each of the six kinds and each class, on the element of its kind - a column is one that paints any ground or holds a picture, a tile is a column of a section of tiles - setting the token of that kind and no other - to the strength the class names - so that a class on a column reaches no card in it'
	. ( $radiusWrong === [] ? '' : ' - '. implode( '; ', $radiusWrong ) ), $radiusWrong === [] && count( $radiusRules ) === 12 );
$radiusWrong = [];
foreach( $groundBlocks as [ , $radiusList, $radiusBody ] )
	if( preg_match( '/var\(\s*--radius-surface(?:-alt)?\s*\)/', $radiusBody ) === 1 )
		foreach( $groundSplit( $radiusList ) as $radiusSelector )
			if( $radiusSelector !== ':root' && preg_match( '/\.nino-radius(?:-alt)?$/', $radiusSelector ) !== 1 )
				$radiusWrong[] = $radiusSelector;
check( '...a surface reads its kind and never a strength: the two strengths are read by the tokens of the kinds in :root and by the twelve rules of the exception, and by no other rule'
	. ( $radiusWrong === [] ? '' : ' - '. implode( ' | ', $radiusWrong ) ), $radiusWrong === [] );

// The frame of the image component has the corners of its kind and no other: a rule that names it, or a modifier of it, and says a radius says the token of the image kind
$radiusWrong = [];
foreach( $groundBlocks as [ , $radiusList, $radiusBody ] )
	foreach( $groundSplit( $radiusList ) as $radiusSelector )
		if( preg_match( '/\.nino-image(?!\w)/', $radiusSelector ) === 1 && preg_match( '/(?<![\w-])border-radius\s*:/', $radiusBody ) === 1
			&& preg_match( '/(?<![\w-])border-radius\s*:\s*var\(\s*--radius-image\s*\)\s*(?:;|$)/', $radiusBody ) !== 1 )
			$radiusWrong[] = $radiusSelector;
check( 'a picture is a surface like the others: the frame of the image component has no radius of its own, and no modifier of it has one - every rule that names the frame and says a border-radius says the token of the image kind'
	. ( $radiusWrong === [] ? '' : ' - '. implode( ' | ', $radiusWrong ) ), $radiusWrong === [] );

/*	A surface cuts what is in it at its radius: a picture or a ground of a child that reaches its edge (nino-p-0) stood out of the corner, square. The column and the row that paint a ground, the row
	that holds a picture, the column that holds one and the card say overflow clip - clip and not hidden, which would make a box of its own for the margins and the floats inside - once each, and the
	column that holds a picture has the radius of its kind to cut at, as the one that paints a ground has. The section is none of these: the arrow of a hero and the elements lifted over its edge stand out
	of it. The picture of a padded cover is cut at the radius of the cover by its own block, but what stands on it is not: the section is a cover and clips at its own edge, which is the picture's only where
	the padding is 0 and is square - so the row of a padded cover cuts what it holds at the corners of the picture, and only at the two of the side where its edge is the picture's: the top with nino-cover-top,
	the bottom with nino-cover-bottom. It does so with a clip-path that leaves the other side open, and not with an overflow, which cuts on all four sides: the other two corners stand in the middle of the
	picture, and the arrow of a hero and what is lifted out of the row stand out of the open side	*/
$clipSelectors = array_merge( array_map( static fn( string $ground ): string => '.nino-grid--'. $ground, $radiusGrounds ), array_map( static fn( string $ground ): string => '.nino-grid-row--'. $ground, $radiusGrounds ),
	[ '.nino-grid-row:where(:has(> .nino-grid-row-bg))', ':has(> .nino-grid-bg)', '.nino-card' ] );
$clipWrong = [];
foreach( $clipSelectors as $clipSelector )
	if( preg_match_all( '/(?<![\w-])overflow\s*:\s*clip\s*(?:;|$)/', $groundOf( $clipSelector ) ) !== 1 || preg_match_all( '/(?<![\w-])overflow\s*:/', $groundOf( $clipSelector ) ) !== 1 )
		$clipWrong[] = $clipSelector;
check( 'a surface cuts what is in it at its radius: the column and the row that paint a ground, the row and the column that hold a picture and the card say overflow: clip, once each - not hidden - and the column that holds a picture has the radius of the column kind'
	. ( $clipWrong === [] ? '' : ' - not: '. implode( ', ', $clipWrong ) ),
	$clipWrong === [] && count( $clipSelectors ) === 19 && $groundSets( $groundOf( ':has(> .nino-grid-bg)' ), 'border-radius', 'var(--radius-column)' ) === true );
check( '...the section is not one of them, its arrow and its lifted elements stand out of it - and the pictures themselves are still cut by the block that holds them, which is the box of the picture and nothing else',
	preg_match( '/(?<![\w-])overflow\s*:/', $groundOf( '.nino-section' ) ) !== 1 && preg_match( '/(?<![\w-])overflow\s*:/', $groundOf( '.nino-section--dim' ) ) !== 1
	&& $groundSets( $groundOf( '.nino-grid-bg' ), 'overflow', 'hidden' ) === true && $groundSets( $groundOf( '.nino-card-bg' ), 'overflow', 'hidden' ) === true && $groundSets( $groundOf( '.nino-grid-row-bg' ), 'overflow', 'hidden' ) === true );
$coverRows = [
	':where(.nino-cover--padded.nino-cover-top > .nino-grid-row)'		=> 'inset(0 0 -100vh 0 round var(--radius-cover) var(--radius-cover) 0 0) margin-box',
	':where(.nino-cover--padded.nino-cover-bottom > .nino-grid-row)'	=> 'inset(-100vh 0 0 0 round 0 0 var(--radius-cover) var(--radius-cover)) margin-box',
];
$coverWrong = [];
foreach( $coverRows as $coverSelector => $coverShape )
	if( trim( $groundOf( $coverSelector ) ) !== 'clip-path: '. $coverShape. ';' || $innerWeight( $coverSelector ) !== 0 )
		$coverWrong[] = $coverSelector;
check( 'the row of a padded cover is cut where its edge is the picture\'s edge, and there only: with nino-cover-top at the two corners of its top, with nino-cover-bottom at the two of its bottom - a clip-path at the radius of the cover kind, laid on the margin box, which spans the picture where a maximum width centres the row, and open by 100vh on the other side - in rules that have no weight of their own'
	. ( $coverWrong === [] ? '' : ' - not: '. implode( ' | ', $coverWrong ) ), $coverWrong === [] && count( $coverRows ) === 2 );
$coverRest = [];
foreach( $groundEach as $coverSelector => $coverBodies )
	if( str_contains( (string) $coverSelector, '.nino-cover--padded' ) === true && isset( $coverRows[$coverSelector] ) === false && in_array( $coverSelector, $paddedPicture, true ) === false
		&& preg_match( '/(?<![\w-])(?:border-radius|overflow|clip-path)\s*:/', implode( ' ', $coverBodies ) ) === 1 )
		$coverRest[] = (string) $coverSelector;
check( '...no other rule of the padded cover or of its row says a radius, an overflow or a clip, and the cover has no radius, which a section that clips would have: a row in the middle is not cut, a column with a ground at the corner of the picture is cut with it, and the arrow of a hero and what is lifted out of the row are not'
	. ( $coverRest === [] ? '' : ' - found: '. implode( ' | ', $coverRest ) ),
	$coverRest === [] && preg_match( '/(?<![\w-])border-radius\s*:/', $groundOf( '.nino-cover' ) ) !== 1 );

/*	Where the picture behind an element starts and ends at the sides: nino-bg-pl-<n> and nino-bg-pr-<n>, 0 to 3, take the block of a row, a column or a card in from the left or from the right by a step of
	the space scale - 0 is the edge it lies at without a class. The rule asks for the block as a child of the element with the class, so that a picture behind an element inside keeps its own sides; it
	is a class heavier than the rule that lays the block over the whole element, so that the order of the two decides nothing	*/
$offsetWrong = [];
$offsetCount = 0;
foreach( [ 'pl' => 'left', 'pr' => 'right' ] as $offsetKey => $offsetSide )
	foreach( [ '0', '1', '2', '3' ] as $offsetStep ) {
		$offsetSelector = '.nino-bg-'. $offsetKey. '-'. $offsetStep. ' > :is(.nino-grid-bg, .nino-card-bg, .nino-grid-row-bg)';
		$offsetCount++;
		if( trim( $groundOf( $offsetSelector ) ) !== $offsetSide. ': '. ( $offsetStep === '0' ? '0' : 'var(--space-'. $offsetStep. ')' ). ';' )
			$offsetWrong[] = $offsetSelector;
	}
check( 'the picture behind a row, a column or a card starts and ends where a class says: nino-bg-pl-0 to -3 set the left of its block and nino-bg-pr-0 to -3 the right, to a step of the space scale and to 0 for 0 - eight rules, each for the block as a child of the element with the class'
	. ( $offsetWrong === [] ? '' : ' - not: '. implode( ' | ', $offsetWrong ) ),
	$offsetWrong === [] && $offsetCount === 8 && $groundSets( $groundOf( '.nino-grid-bg' ), 'inset', '0' ) === true && $groundSets( $groundOf( '.nino-card-bg' ), 'inset', '0' ) === true && $groundSets( $groundOf( '.nino-grid-row-bg' ), 'inset', '0' ) === true );

// The slider says its width: the track is out of the flow, so a box that sizes the slider by its content - an item of a flex row, a grid that places its content at the start - made it as wide as nothing
check( 'a slider is as wide as the box it stands in: width 100%, min-width 0 so that nothing holds it wider, and stretched in a flex column - its track is out of the flow, and Nino.ui.js measures the slider to size the slides',
	$groundSets( $groundOf( '.nino-slider' ), 'width', '100%' ) === true && $groundSets( $groundOf( '.nino-slider' ), 'min-width', '0' ) === true && $groundSets( $groundOf( '.nino-slider' ), 'align-self', 'stretch' ) === true );

echo "\n";


// --- A section of tiles -----------------------------------------------------

echo "Nino.css - a section of tiles: six tokens, a section without padding, a row at the whole width, a tile with a padding, a height and a radius of its own, and a link over the whole of a column\n";

/*	The tiles are the second kind of section: .nino-tiles on a section, its row the whole width and its columns tiles. What wins over what is pinned by weight and by place in the file, as for the
	other surfaces: every rule is one class heavy - the selectors that name more of the chain say it in :where() - and stands where the order decides, so that the spacing classes of chapter 09 win
	over the padding of the section, the row and a tile, and a ground or a picture of a column does not take a tile's padding or radius back. The tile itself says no overflow: a grid item that is not
	visible loses the automatic minimum size that lets it grow past its ratio, and the one block that is cut at the radius of a tile is its picture	*/
$tileRoot = [ '--tiles-padding' => 'var(--space-3)', '--tiles-gap' => '0px', '--tiles-place' => 'center', '--tiles-title-scale' => '1', '--tiles-subtitle-scale' => '1', '--tiles-text-scale' => '1' ];
$tileWrong = [];
foreach( $tileRoot as $tileProperty => $tileValue )
	if( $hookValue( $hookRoot, $tileProperty ) !== $tileValue )
		$tileWrong[] = $tileProperty;
check( ':root declares the six tokens of the tiles: the padding of a tile, the gap between tiles - none -, where the words stand - in the middle -, and the title, the subtitle and the text of a tile at the size of their role'
	. ( $tileWrong === [] ? '' : ' - not: '. implode( ', ', $tileWrong ) ), $tileWrong === [] );
check( '...the padding of a tile is a step wider from 768px, and nothing else of the tiles moves there',
	$hookValue( $hookRootWide, '--tiles-padding' ) === 'var(--space-4)' && preg_match_all( '/--tiles-/', $hookRootWide ) === 1 );
$tileSelectors = [];
foreach( array_keys( $groundEach ) as $tileSelector )
	if( str_contains( (string) $tileSelector, 'nino-tiles' ) === true )
		$tileSelectors[] = (string) $tileSelector;
sort( $tileSelectors );
$tileExpected = [ ':is(.nino-tiles > .nino-grid-row > *).nino-radius', ':is(.nino-tiles > .nino-grid-row > *).nino-radius-alt', '.nino-tiles', ':where(.nino-tiles) > .nino-grid-row', ':where(.nino-tiles) > .nino-grid-row > *',
	':where(.nino-tiles) > .nino-grid-row > * > .nino-btn', ':where(.nino-tiles) > .nino-grid-row > * > .nino-grid-bg',
	':where(.nino-tiles) > .nino-grid-row > * > .nino-section-text:where(:not(.nino-section-text--quiet, .nino-section-text--loud))', ':where(.nino-tiles) > .nino-grid-row > .nino-text-center > .nino-btn',
	':where(.nino-tiles) > .nino-grid-row > .nino-text-right > .nino-btn' ];
foreach( [ '1-1', '4-3', '3-2', '16-9', '21-9', '4-1', '5-1' ] as $tileRatio )
	$tileExpected[] = '.nino-tiles--'. $tileRatio. ' > .nino-grid-row > *';
sort( $tileExpected );
check( 'nothing in Nino.css addresses a section of tiles but these rules: the section, its row, a tile, its words and its button, its picture, the seven ratios and the radius classes of a tile',
	$tileSelectors === $tileExpected );

$tileFirstUtility = -1;
foreach( $groundMembers as $groundAt => $groundList )
	foreach( $groundList as $groundMember )
		if( preg_match( '/^\.nino-p[tblr]?-\d$/', $groundMember ) === 1 && ( $tileFirstUtility < 0 || $groundAt < $tileFirstUtility ) )
			$tileFirstUtility = $groundAt;
$tileAt = static function( string $selector ) use ( $groundMembers ): int {
	foreach( $groundMembers as $groundAt => $groundList )
		if( in_array( $selector, $groundList, true ) === true )
			return $groundAt;
	return -1;
};
// The section: no padding on any side, one class heavy, after every padding the section has - the base rule and the one of the 768px query - and ahead of the utilities
check( 'a section of tiles has no padding on any side: .nino-tiles says padding 0, once, one class heavy, after the section\'s own padding rules - the base rule and the one of the 768px query - and ahead of the first padding class of chapter 09, so that nino-pt-<n> on it says what it says',
	$groundSets( $groundOf( '.nino-tiles' ), 'padding', '0' ) === true && count( $groundEach['.nino-tiles'] ) === 1 && $innerWeight( '.nino-tiles' ) === 1 && $groundConditions( '.nino-tiles' ) === [ [] ]
	&& $tileAt( '.nino-tiles' ) > $groundLastOf( [ '.nino-section' ], 'padding' ) && $groundLastOf( [ '.nino-section' ], 'padding' ) > 0 && $tileAt( '.nino-tiles' ) < $tileFirstUtility && $tileFirstUtility > 0 );
// The row: the whole width, the gap of the section where the row names none
$tileRowSelector = ':where(.nino-tiles) > .nino-grid-row';
$tileRowAt = $tileAt( $tileRowSelector );
$tileRowBody = $groundOf( $tileRowSelector );
check( 'the row of a section of tiles has no maximum width, no place and no padding, and its gap is the section\'s: one class heavy, after nino-grid-row--full and the other width classes of the row, which say nothing on it, and ahead of the gap classes, which win over --tiles-gap',
	$groundSets( $tileRowBody, 'max-width', 'none' ) === true && $groundSets( $tileRowBody, 'margin', '0' ) === true && $groundSets( $tileRowBody, 'padding', '0' ) === true && $groundSets( $tileRowBody, '--nino-grid-gap', 'var(--tiles-gap)' ) === true
	&& count( $groundEach[$tileRowSelector] ) === 1 && $innerWeight( $tileRowSelector ) === 1
	&& $tileRowAt > max( $tileAt( '.nino-grid-row' ), $tileAt( '.nino-grid-row--narrow' ), $tileAt( '.nino-grid-row--wide' ), $tileAt( '.nino-grid-row--full' ), $tileAt( '.nino-grid-row--left' ), $tileAt( '.nino-grid-row--right' ) )
	&& $tileRowAt < min( array_map( static fn( int $step ): int => $tileAt( '.nino-grid-gap-'. $step ), [ 0, 1, 2, 3, 4, 5, 6 ] ) ) && $tileAt( '.nino-grid-gap-0' ) > 0 );
// A tile: its padding, its stack, its radius, its minimum height and its overflow
$tileSelector = ':where(.nino-tiles) > .nino-grid-row > *';
$tileBody = $groundOf( $tileSelector );
$tileGrounds = array_map( static fn( string $ground ): string => '.nino-grid--'. $ground, $radiusGrounds );
$tileGroundsAt = max( array_map( $tileAt, array_merge( $tileGrounds, [ ':has(> .nino-grid-bg)', '.nino-grid-bg' ] ) ) );
check( 'a tile is a column of the row with a padding of its own: --tiles-padding times the whitespace, one class heavy, after every ground of a column, its picture and its radius - which would take it back - and ahead of the first padding class, so that nino-p-<n> says what it says',
	$groundSets( $tileBody, 'padding', 'calc(var(--tiles-padding) * var(--whitespace-scale))' ) === true && $groundSets( $tileBody, 'border-radius', 'var(--radius-tile)' ) === true
	&& count( $groundEach[$tileSelector] ) === 1 && $innerWeight( $tileSelector ) === 1 && $tileAt( $tileSelector ) > $tileGroundsAt && $tileGroundsAt > 0 && $tileAt( $tileSelector ) < $tileFirstUtility );
check( '...it is a stack by default - a grid whose words stand at --tiles-place, a gap of a stack apart - the box the stretched link and the picture are placed in, and it sets the scales of the title and the subtitle on itself, the size of the words being its own times --tiles-text-scale',
	$groundSets( $tileBody, 'display', 'grid' ) === true && $groundSets( $tileBody, 'align-content', 'var(--tiles-place)' ) === true && $groundSets( $tileBody, 'position', 'relative' ) === true && $groundSets( $tileBody, 'isolation', 'isolate' ) === true
	&& $groundSets( $tileBody, 'gap', 'calc(var(--nino-stack-gap, var(--space-2)) * var(--whitespace-gap))' ) === true
	&& $groundSets( $tileBody, '--section-title-scale', 'var(--tiles-title-scale)' ) === true && $groundSets( $tileBody, '--section-subtitle-scale', 'var(--tiles-subtitle-scale)' ) === true
	&& $groundSets( $tileBody, 'font-size', 'calc(var(--font-text-size) * var(--tiles-text-scale))' ) === true );
$tileFormula = 'calc( ( 100cqw * var(--nino-grid-share, 1) - var(--nino-grid-gap, 0px) * ( 1 - var(--nino-grid-share, 1) ) ) * var(--nino-tile-ratio, 0) )';
check( '...the tile says its own minimum height - the width the width classes give it (the row\'s 100cqw times the share, less the share of the gap that the width classes give back) times --nino-tile-ratio, 0 without a ratio class - and overflow visible, once each, and no aspect-ratio: a flex item with an aspect ratio has no content-based minimum on WebKit and keeps its ratio height, its centred words cut by the clip of a column with a ground or a picture',
	$groundSets( $tileBody, 'min-height', $tileFormula ) === true && preg_match_all( '/(?<![\w-])min-height\s*:/', $tileBody ) === 1
	&& $groundSets( $tileBody, 'overflow', 'visible' ) === true && preg_match_all( '/(?<![\w-])overflow\s*:/', $tileBody ) === 1
	&& preg_match( '/aspect-ratio/', $tileBody ) !== 1 && preg_match( '/aspect-ratio/', $groundOf( '.nino-tiles' ) ) !== 1 && preg_match( '/aspect-ratio/', $tileRowBody ) !== 1
	&& preg_match( '/(?<![\w-])overflow/', $groundOf( '.nino-tiles' ) ) !== 1 && preg_match( '/(?<![\w-])overflow/', $tileRowBody ) !== 1 );
check( '...the row of a section of tiles is a size container, container-type inline-size, so that 100cqw in the tile is the width of the row, and the picture block alone is cut: overflow clip and the radius of the tile, so that the zoomed picture of a linked tile stays inside its corners',
	$groundSets( $tileRowBody, 'container-type', 'inline-size' ) === true && preg_match_all( '/(?<![\w-])container-type\s*:/', $tileRowBody ) === 1
	&& $groundSets( $groundOf( ':where(.nino-tiles) > .nino-grid-row > * > .nino-grid-bg' ), 'overflow', 'clip' ) === true && $groundSets( $groundOf( ':where(.nino-tiles) > .nino-grid-row > * > .nino-grid-bg' ), 'border-radius', 'var(--radius-tile)' ) === true
	&& $innerWeight( ':where(.nino-tiles) > .nino-grid-row > * > .nino-grid-bg' ) === 2 && $tileAt( ':where(.nino-tiles) > .nino-grid-row > * > .nino-grid-bg' ) > $tileAt( '.nino-grid-bg' ) );
// The share: every width class says its share of the row in its tier, the minimum height of a tile reads it
$shareWords = [ '25' => '.25', '33' => 'calc( 1 / 3 )', '50' => '.5', '66' => 'calc( 2 / 3 )', '75' => '.75', '100' => '1' ];
$shareWrong = [];
$shareCount = 0;
foreach( [ '', 's-', 'm-', 'l-', 'xl-' ] as $shareTier )
	foreach( $shareWords as $shareWidth => $shareValue ) {
		$shareFound = preg_match_all( '/^[ \t]*\.nino-grid-'. $shareTier. $shareWidth. '\s*\{([^{}]*)\}/m', $hookCss, $shareRules );
		$shareOk = $shareFound === 1 && preg_match_all( '/(?<![\w-])width\s*:/', $shareRules[1][0] ) === 1 && preg_match( '/(?<![\w-])--nino-grid-share:\s*'. preg_quote( $shareValue, '/' ). '\s*;/', $shareRules[1][0] ) === 1;
		$shareCount += $shareOk ? 1 : 0;
		if( !$shareOk )
			$shareWrong[] = $shareTier. $shareWidth;
	}
check( 'every width class of the grid says its share of the row beside its width, in its own tier - 25, 33, 50, 66, 75 and 100 in the base rules and in the s, m, l and xl queries, thirty rules - so that the share follows the active tier as the width does'
	. ( $shareWrong === [] ? '' : ' - not: '. implode( ', ', $shareWrong ) ),
	$shareWrong === [] && $shareCount === 30 );
// The height: the ratio of the section on every tile of its row as a number, the rows of a line stretched
$tileHeightWrong = [];
foreach( [ '1-1' => '1', '4-3' => '.75', '3-2' => 'calc( 2 / 3 )', '16-9' => 'calc( 9 / 16 )', '21-9' => 'calc( 9 / 21 )', '4-1' => '.25', '5-1' => '.2' ] as $tileRatio => $tileValue )
	if( $groundSets( $groundOf( '.nino-tiles--'. $tileRatio. ' > .nino-grid-row > *' ), '--nino-tile-ratio', $tileValue ) !== true || preg_match( '/aspect-ratio/', $groundOf( '.nino-tiles--'. $tileRatio. ' > .nino-grid-row > *' ) ) === 1 )
		$tileHeightWrong[] = $tileRatio;
check( 'the height of a tile is the ratio its section names, for every tile of its row: the seven ratio classes say --nino-tile-ratio, height over width, and nothing else, and no rule of a tile or its row sets a height or aligns the items of the row - the tiles of a line stay stretched, as high as the highest'
	. ( $tileHeightWrong === [] ? '' : ' - not: '. implode( ', ', $tileHeightWrong ) ),
	$tileHeightWrong === [] && preg_match( '/(?<![\w-])(?:height|max-height|align-items|align-self)\s*:/', $tileBody. ' '. $tileRowBody ) !== 1 && preg_match( '/(?<![\w-])min-height\s*:/', $tileRowBody ) !== 1
	&& $groundSets( $groundOf( '.nino-grid-row' ), 'display', 'flex' ) === true && preg_match( '/align-items/', $groundOf( '.nino-grid-row' ) ) !== 1 );
// The linked column: the rules of the linked card, selector for selector
$linkedCard = [];
foreach( $groundEach as $linkedSelector => $linkedBodies )
	if( str_starts_with( (string) $linkedSelector, '.nino-card--linked' ) === true && str_contains( (string) $linkedSelector, '.nino-image' ) === false )
		$linkedCard[(string) $linkedSelector] = $linkedBodies;
$linkedWrong = [];
foreach( $linkedCard as $linkedSelector => $linkedBodies ) {
	$linkedMirror = str_replace( '.nino-card--linked', '.nino-grid--linked', $linkedSelector );
	if( ( $groundEach[$linkedMirror] ?? null ) !== $linkedBodies )
		$linkedWrong[] = $linkedMirror;
}
$linkedColumn = array_values( array_filter( array_keys( $groundEach ), static fn( mixed $selector ): bool => str_starts_with( (string) $selector, '.nino-grid--linked' ) === true ) );
check( 'a column can be a link the way a card can: the rules of .nino-grid--linked are those of .nino-card--linked selector for selector and declaration for declaration - the stretched link over the whole column, everything else interactive above it, the ring on the column - and there are as many'
	. ( $linkedWrong === [] ? '' : ' - not: '. implode( ', ', $linkedWrong ) ), $linkedWrong === [] && count( $linkedCard ) === 5 && count( $linkedColumn ) === 5 );
// The class of a link is the one place: nothing but these rules reads nino-stretch
check( '...and a title that is a link draws the class they stretch, and in a column outside a linked one it means nothing: no other rule of the stylesheet reads nino-stretch',
	count( array_filter( array_keys( $groundEach ), static fn( mixed $selector ): bool => str_contains( (string) $selector, 'nino-stretch' ) === true && str_contains( (string) $selector, '--linked' ) === false ) ) === 0 );

echo "\n";


// --- The hover of a picture -------------------------------------------------

echo "Nino.css - the hover of a picture: five handles on :root, the class on the frame, the linked card, less motion and the coarse pointer\n";

/*	The effect is the class nino-hover on the frame of a picture - the box that crops it, which a bare <img> has not - and what it does is said by five custom properties that a design layer
	or a template sets: the kernel's own are a zoom and nothing else. What the rules read, where they stand and what they leave out is pinned by selector and by the query they stand in,
	never by a length: the values are the handles'	*/
$hoverHandles = [ '--image-hover-scale', '--image-hover-lift', '--image-hover-brightness', '--image-hover-shadow', '--image-hover-duration' ];
$hoverRoots = [];
preg_match_all( '/(?:^|\})\s*:root\s*\{([^{}]*)\}/', $hookCss, $hoverBlocks );
foreach( $hoverBlocks[1] as $hoverBlock )
	if( str_contains( $hoverBlock, '--image-hover-' ) === true )
		$hoverRoots[] = $hoverBlock;
$hoverRoot = count( $hoverRoots ) === 1 ? $hoverRoots[0] : '';
$hoverMissing = array_values( array_filter( $hoverHandles, static fn( string $handle ): bool => $hookValue( $hoverRoot, $handle ) === null ) );
check( 'Nino.css declares the five handles of the hover of a picture in one :root block - the scale, the lift, the brightness, the shadow and the duration, each --image-hover-<name> - and no other of that name'
	. ( $hoverMissing === [] ? '' : ' - missing: '. implode( ', ', $hoverMissing ) ),
	count( $hoverRoots ) === 1 && $hoverMissing === [] && preg_match_all( '/--image-hover-[\w-]+\s*:/', $hookCss ) === count( $hoverHandles ) );
check( '...a zoom by default and nothing else: the scale is above 1, the lift 0px, the brightness 1 and the shadow none, and the duration is a time that is not nothing',
	(float) $hookValue( $hoverRoot, '--image-hover-scale' ) > 1 && $hookValue( $hoverRoot, '--image-hover-lift' ) === '0px'
	&& $hookValue( $hoverRoot, '--image-hover-brightness' ) === '1' && $hookValue( $hoverRoot, '--image-hover-shadow' ) === 'none'
	&& preg_match( '/^(?:\d*\.)?\d+m?s$/', (string) $hookValue( $hoverRoot, '--image-hover-duration' ) ) === 1 && (float) $hookValue( $hoverRoot, '--image-hover-duration' ) > 0 );

// The four places the effect shows - under the pointer, under the keyboard where the picture is a link, and in a linked card for its hover and for the focus of its stretched link - for the frame, and for the picture in it
$hoverFrames = [ '.nino-image.nino-hover:hover', 'a:focus-visible > .nino-image.nino-hover', '.nino-card--linked:hover .nino-image.nino-hover', '.nino-card--linked:has(a.nino-stretch:focus-visible) .nino-image.nino-hover' ];
$hoverPictures = array_map( static fn( string $selector ): string => $selector. ' > img', $hoverFrames );
// The bodies of the rules whose list of selectors is exactly the one given, squeezed, in the order of the stylesheet
$hoverBodies = static function( array $members ) use ( $groundBlocks, $groundSplit ): array {
	$found = [];
	foreach( $groundBlocks as [ , $hoverList, $hoverBody ] )
		if( $groundSplit( $hoverList ) === $members )
			$found[] = trim( (string) preg_replace( '/\s+/', ' ', (string) $hoverBody ) );
	return $found;
};
$hoverFrameBodies = $hoverBodies( $hoverFrames );
$hoverPictureBodies = $hoverBodies( $hoverPictures );
check( 'under the pointer the frame takes translateY( the lift ) and the shadow and the picture in it scale( the scale ) and brightness( the brightness ) - a rule for .nino-image.nino-hover:hover and one for .nino-image.nino-hover:hover > img that read the handles and say no number of their own',
	$groundSets( $groundOf( '.nino-image.nino-hover:hover' ), 'transform', 'translateY(var(--image-hover-lift))' ) === true && $groundSets( $groundOf( '.nino-image.nino-hover:hover' ), 'box-shadow', 'var(--image-hover-shadow)' ) === true
	&& $groundSets( $groundOf( '.nino-image.nino-hover:hover > img' ), 'transform', 'scale(var(--image-hover-scale))' ) === true && $groundSets( $groundOf( '.nino-image.nino-hover:hover > img' ), 'filter', 'brightness(var(--image-hover-brightness))' ) === true
	&& preg_match( '/\d/', implode( ' ', array_merge( $hoverFrameBodies, $hoverPictureBodies ) ) ) !== 1 );
check( '...each transitioning over the duration: the frame its transform and its box-shadow, the picture its transform and its filter, on the frame that says the class - the rules of the frame itself say nothing of the effect, so that a frame without the class draws as it did',
	$hookValue( $groundOf( '.nino-image.nino-hover' ), 'transition-property' ) === 'transform, box-shadow' && $hookValue( $groundOf( '.nino-image.nino-hover' ), 'transition-duration' ) === 'var(--image-hover-duration)'
	&& $hookValue( $groundOf( '.nino-image.nino-hover > img' ), 'transition-property' ) === 'transform, filter' && $hookValue( $groundOf( '.nino-image.nino-hover > img' ), 'transition-duration' ) === 'var(--image-hover-duration)'
	&& preg_match( '/(?<![\w-])(?:transition[\w-]*|transform|filter|box-shadow)\s*:/', $groundOf( '.nino-image' ). ' '. $groundOf( '.nino-image > img' ) ) !== 1 );
// The class is the frame's: no selector names it without the frame - a bare <img> that carries it has no rule
$hoverBare = [];
foreach( $groundBlocks as [ , $hoverList ] )
	foreach( $groundSplit( $hoverList ) as $hoverSelector )
		if( preg_match_all( '/\.nino-hover(?![\w-])/', $hoverSelector ) !== preg_match_all( '/\.nino-image\.nino-hover(?![\w-])/', $hoverSelector ) )
			$hoverBare[] = $hoverSelector;
check( '...and the class is the frame\'s, as nino-radius is: every selector that names nino-hover names the frame it stands on, .nino-image.nino-hover'. ( $hoverBare === [] ? '' : ' - not: '. implode( ' | ', $hoverBare ) ),
	$hoverBare === [] && isset( $groundEach['.nino-image.nino-hover'], $groundEach['.nino-image.nino-hover > img'] ) === true );

// A linked card is no <a>: the pointer stands on the overlay its stretched link lays over the card, never on the frame, and the keyboard rests on that link
$hoverCards = [ '.nino-card--linked:hover .nino-image.nino-hover', '.nino-card--linked:has(a.nino-stretch:focus-visible) .nino-image.nino-hover', '.nino-card--linked:hover .nino-image.nino-hover > img', '.nino-card--linked:has(a.nino-stretch:focus-visible) .nino-image.nino-hover > img' ];
check( 'in a linked card the picture follows the card - its hover and the focus of its stretched link, the selector the ring of the card is drawn for - the same rules for the frame and for the picture, one list each: the pointer, the keyboard on a link, and the card',
	count( $hoverFrameBodies ) === 2 && count( $hoverPictureBodies ) === 2 && array_diff( $hoverCards, array_keys( $groundEach ) ) === []
	&& isset( $groundEach['.nino-card--linked:has(a.nino-stretch:focus-visible)'] ) === true );

// A coarse pointer and less motion: the effect stands in a query that leaves the coarse pointer out, and the block that takes the motion back for less motion names the fine pointer and none, a list in the form the rest of the file gives its queries - a tap changes nothing, and no engine drops the block
$hoverFramesWhere = $groundConditions( implode( ', ', $hoverFrames ) );
$hoverPicturesWhere = $groundConditions( implode( ', ', $hoverPictures ) );
$hoverOther = [];
foreach( $groundBlocks as [ , $hoverList, $hoverBody ] )
	if( str_contains( $hoverList, '.nino-hover' ) === true && $groundSplit( $hoverList ) !== $hoverFrames && $groundSplit( $hoverList ) !== $hoverPictures
		&& preg_match( '/(?<![\w-])(?:transform|filter|box-shadow)\s*:/', (string) $hoverBody ) === 1 )
		$hoverOther[] = trim( (string) preg_replace( '/\s+/', ' ', $hoverList ) );
check( 'a coarse pointer has none of it: a hover that sticks after a tap is no effect anybody asked for, so the rules that give the frame and the picture the effect stand in a query that leaves the coarse pointer out, no other rule of the class declares it, and the block that takes the motion back for less motion stands for the fine pointer and for none, in the form the rest of the file gives its queries - a tap changes nothing, not even a shadow that the frame has at rest'
	. ( $hoverOther === [] ? '' : ' - declared by: '. implode( ' | ', $hoverOther ) ),
	$hoverFramesWhere === [ [ '@media not all and (pointer: coarse)' ], [ '@media (prefers-reduced-motion: reduce) and (pointer: fine), (prefers-reduced-motion: reduce) and (pointer: none)' ] ]
	&& $hoverPicturesWhere === [ [ '@media not all and (pointer: coarse)' ], [ '@media (prefers-reduced-motion: reduce) and (pointer: fine), (prefers-reduced-motion: reduce) and (pointer: none)' ] ]
	&& str_contains( $hoverFrameBodies[0] ?? '', 'translateY(' ) === true && str_contains( $hoverPictureBodies[0] ?? '', 'scale(' ) === true && $hoverOther === [] );
check( 'less motion keeps the frame in its place and the picture at its size: the block that follows the effect says transform: none for both and nothing else - it takes neither the shadow nor the filter back, as the brightness and the shadow are no motion and may still change',
	( $hoverFrameBodies[1] ?? '' ) === 'transform: none;' && ( $hoverPictureBodies[1] ?? '' ) === 'transform: none;'
	&& preg_match( '/(?<![\w-])filter\s*:/', $hoverPictureBodies[1] ?? '' ) !== 1 && str_contains( $hoverPictureBodies[0] ?? '', 'filter: brightness(var(--image-hover-brightness))' ) === true );

echo "\n";


// --- Modules\Components ----------------------------------------------------

echo "Modules\\Components - the registry, the sources, the stacks\n";

// What init() registers before a page is drawn - the sandbox never runs it
\Nino\Modules\Components::init( $appData );
\Nino\Modules\Template::init( $appData );
\Nino\Modules\Elements::init( $appData );

// The registry the module's own init() fills, read before the test adds to it
$kernelAll = [ 'title', 'subtitle', 'text', 'line', 'card-title', 'card-subtitle', 'card-text', 'card-image', 'hero-title', 'hero-subtitle', 'card', 'button', 'image', 'logo', 'html', 'spacer', 'scroll-hint', 'col' ];
$kernelStacks = [ 'stack', 'slider', 'filter', 'list', 'table', 'sections' ];
check( 'the module registers the components of the kernel in the order a column offers them: the title, the subtitle, the text and the line of a section, the title, the subtitle, the text and the image of a card, the title and the subtitle of a hero, then the card, the button, the image, the logo, the html, the spacer and the scroll hint, and the column of a table last', array_keys( \Nino\Modules\Components::components( $appData ) ) === $kernelAll );
check( '...and a tool is answered in that order: the registry as json is an object keyed by name, which read again lists the same eighteen in the same order',
	str_starts_with( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), '{"title":{' ) === true
	&& array_keys( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true ) ) === $kernelAll );
check( '...and its stacks', array_keys( \Nino\Modules\Components::stacks( $appData ) ) === $kernelStacks );
check( 'every component of the kernel says which group a tool offers it under, one word of GROUPS - the registry hands it over beside where the component stands and who registered it, in the json a tool reads as well - and no stack says one',
	array_filter( \Nino\Modules\Components::components( $appData ), static fn( array $schema ): bool => in_array( $schema['group'] ?? null, \Nino\Modules\Components::GROUPS, true ) === false ) === []
	&& array_column( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true ), 'group' ) === array_column( \Nino\Modules\Components::components( $appData ), 'group' )
	&& array_filter( \Nino\Modules\Components::stacks( $appData ), static fn( array $stack ): bool => array_key_exists( 'group', $stack ) === true ) === [] );
check( 'a registered schema is complete and carries its label in both languages', \Nino\Modules\Components::components( $appData )['title']['label'] === [ 'en_US' => 'Title (Section)', 'de_DE' => 'Titel (Sektion)' ]
	&& \Nino\Modules\Components::components( $appData )['title']['source'] === 'text'
	&& \Nino\Modules\Components::components( $appData )['title']['preview'] === 'title'
	&& \Nino\Modules\Components::components( $appData )['title']['loop'] === true
	&& array_keys( \Nino\Modules\Components::components( $appData )['title']['attributes'] ) === [ 'tag', 'style', 'color' ] );
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
check( 'every component and stack of the kernel is a shortcode', array_diff( array_merge( $kernelAll, $kernelStacks ), \Nino\Html::shortcodes( $appData ) ) === [] );
\Nino\Modules\Components::init( $appData );
check( 'the module registers again without registering twice - a second init leaves one callback per shortcode', count( \Nino\Callbacks::registered( $appData, '/nino/html/shortcode/title' ) ) === 1
	&& count( \Nino\Callbacks::registered( $appData, '/nino/html/shortcode/image' ) ) === 1 );

// Defaults come from the schema and nowhere else, as the strings a shortcode carries
check( 'defaults() are the schema\'s, as strings, with the link of a component that says so before the class every component takes', \Nino\Modules\Components::defaults( $appData, 'title' ) === [ 'tag' => 'h3', 'style' => '', 'color' => '', 'href' => '', 'target' => '', 'class' => '' ]
	&& \Nino\Modules\Components::defaults( $appData, 'text' )['format'] === 'blocks'
	&& \Nino\Modules\Components::defaults( $appData, 'spacer' ) === [ 'size' => '2', 'class' => '' ] );
check( '...a stack has those of the loop, of its grid where it has one, and its own - and none that says what a cell looks like: the card is a component in its content', \Nino\Modules\Components::defaults( $appData, 'stack', true ) === [ 'sort' => '', 'offset' => '0', 'limit' => '0', 'query' => '', 'callback' => '', 'locale' => '', 'id' => '', 'cols' => '100 50 33', 'gap' => '2', 'autoheight' => '0', 'class' => '' ]
	&& isset( \Nino\Modules\Components::defaults( $appData, 'slider', true )['cols'] ) === false
	&& array_filter( $kernelStacks, static fn( string $name ): bool => array_key_exists( 'card', \Nino\Modules\Components::defaults( $appData, $name, true ) ) === true ) === []
	&& \Nino\Modules\Components::defaults( $appData, 'slider', true )['width'] === '75%'
	&& \Nino\Modules\Components::defaults( $appData, 'filter', true )['autoheight'] === '0' );
check( '...and nothing for a name that is not registered', \Nino\Modules\Components::defaults( $appData, 'nope' ) === [] && \Nino\Modules\Components::defaults( $appData, 'title', true ) === [] );

/*	sources(): the keys and the slots a template names as the source of a
	component, which is what the scans of the templates read. Found as the
	rendering finds the calls - the ones in a card or a stack included - and
	kept where the component takes a text or an image and the first argument
	begins with a slash: a field of the element, a text="..." that has no key to
	look up, a source with a fill in it, a component of another kind, a
	shortcode that is none and one nobody registered are not	*/
$sourcesTemplate = implode( "\n", [
	'[title /template/page-x/intro/title]',
	'[card padding="3"]',
	'	[card-image /template/page-x/cards/one-picture style="inline"]',
	'	[card-text /template/page-x/cards/one-text format="inline"]',
	'	[button /template/page-x/cards/one-button href="/more"]',
	'[/card]',
	'[image uri="/template/page-x/viauri/picture" alt=""]',
	'[image /template/page-x/bare/picture]',
	'[stack /deltas sort="title"]',
	'	[card-title title]',
	'	[image image focus="5"]',
	'	[card-text /template/page-x/stack/one-text]',
	'[/stack]',
	'[button /template/page-x/fixed/label text="Fixed words" href="/more"]',
	'[title /template/[[/nino/http/response/locale]]/dynamic/title]',
	'[spacer size="3"]',
	'[html]<b>/template/page-x/html/not-a-source</b>[/html]',
	'[template /templates/page-y]',
	'[unregistered /template/page-x/unregistered/source]',
] );
check( 'sources() lists the keys and the slots a template names as the source of a component, in the order the calls stand, the ones inside a card or a stack among them',
	\Nino\Modules\Components::sources( $appData, $sourcesTemplate ) === [
		[ 'name' => 'title', 'kind' => 'text', 'source' => '/template/page-x/intro/title' ],
		[ 'name' => 'card-image', 'kind' => 'image', 'source' => '/template/page-x/cards/one-picture' ],
		[ 'name' => 'card-text', 'kind' => 'text', 'source' => '/template/page-x/cards/one-text' ],
		[ 'name' => 'button', 'kind' => 'text', 'source' => '/template/page-x/cards/one-button' ],
		[ 'name' => 'image', 'kind' => 'image', 'source' => '/template/page-x/viauri/picture' ],
		[ 'name' => 'image', 'kind' => 'image', 'source' => '/template/page-x/bare/picture' ],
		[ 'name' => 'card-text', 'kind' => 'text', 'source' => '/template/page-x/stack/one-text' ],
	] );
check( '...and nothing else of it: not the field of an element, not the type a stack loops, not a label that has a fixed text, not a source with a fill in it, not a component of another kind and not a shortcode nobody registered',
	array_intersect( array_column( \Nino\Modules\Components::sources( $appData, $sourcesTemplate ), 'source' ), [ 'title', 'image', 'summary', '/deltas', '/template/page-x/fixed/label', '/template/[[/nino/http/response/locale]]/dynamic/title',
		'/template/page-x/html/not-a-source', '/templates/page-y', '/template/page-x/unregistered/source' ] ) === [] );
check( '...nothing for a text without a shortcode in it, and nothing where no call has a source', \Nino\Modules\Components::sources( $appData, '' ) === [] && \Nino\Modules\Components::sources( $appData, 'a [ lone bracket and [[/a/fill]]' ) === []
	&& \Nino\Modules\Components::sources( $appData, '[card padding="3"][/card][spacer size="1"][card-text summary]' ) === [] );

/*	A slot is also named by an attribute of the type image - the background of a
	card - whatever the component's own source is. It is one more entry of its
	call, after the first argument's, and the attribute is named. The same rule
	keeps it: it begins with a slash and holds no [ - so the image field of the
	element in a stack is none, nor is an empty one	*/
$backgroundTemplate = implode( "\n", [
	'[card background="/template/page-x/cards/one-background" dim="1"]',
	'	[card-text /template/page-x/cards/one-text]',
	'[/card]',
	'[stack /deltas]',
	'	[card background="image"]',
	'		[card-title title]',
	'	[/card]',
	'[/stack]',
	'[card background="/template/[[/nino/http/response/locale]]/dynamic/background"][/card]',
	'[card background=""][/card]',
	'[card padding="3"][/card]',
] );
check( 'sources() lists the slot an attribute of the type image names: the background of a card, with the attribute, before the calls inside it',
	\Nino\Modules\Components::sources( $appData, $backgroundTemplate ) === [
		[ 'name' => 'card', 'kind' => 'image', 'source' => '/template/page-x/cards/one-background', 'attribute' => 'background' ],
		[ 'name' => 'card-text', 'kind' => 'text', 'source' => '/template/page-x/cards/one-text' ],
	] );
check( '...and not the image field of the element in a stack, a background with a fill in it, an empty one or a card with none',
	array_intersect( array_column( \Nino\Modules\Components::sources( $appData, $backgroundTemplate ), 'source' ), [ 'image', 'title', '', '/template/[[/nino/http/response/locale]]/dynamic/background' ] ) === [] );

// What the rule is made of is the registry's: the kind a schema says. A component a feature registers is read the same, and the kernel's own registry knows nothing of it
$sourcesApp = $appData;
$sourceNoop = static fn( array &$appData, array $args ): string => '';
\Nino\Modules\Components::addComponent( $sourcesApp, 'wordsof', $sourceNoop, [ 'label' => 'Words', 'source' => 'text', 'by' => 'probe' ] );
\Nino\Modules\Components::addComponent( $sourcesApp, 'pictureof', $sourceNoop, [ 'label' => 'Picture', 'source' => 'image', 'by' => 'probe' ] );
\Nino\Modules\Components::addComponent( $sourcesApp, 'linkof', $sourceNoop, [ 'label' => 'Link', 'source' => 'href', 'by' => 'probe' ] );
\Nino\Modules\Components::addComponent( $sourcesApp, 'bannerof', $sourceNoop, [ 'label' => 'Banner', 'source' => 'href', 'by' => 'probe', 'attributes' => [ 'picture' => [ 'type' => 'image', 'default' => '' ], 'caption' => [ 'type' => 'key', 'default' => '' ] ] ] );
$sourcesOwn = '[wordsof /feature/probe/words/one][pictureof /feature/probe/pictures/one][pictureof uri="/feature/probe/pictures/two"][linkof /feature/probe/links/one]';
check( 'the components of a feature are read by the kind their schema says: a text and an image count, an address does not', \Nino\Modules\Components::sources( $sourcesApp, $sourcesOwn ) === [
		[ 'name' => 'wordsof', 'kind' => 'text', 'source' => '/feature/probe/words/one' ],
		[ 'name' => 'pictureof', 'kind' => 'image', 'source' => '/feature/probe/pictures/one' ],
		[ 'name' => 'pictureof', 'kind' => 'image', 'source' => '/feature/probe/pictures/two' ],
	] && \Nino\Modules\Components::sources( $appData, $sourcesOwn ) === [] );
$sourcesBanner = '[bannerof /feature/probe/links/two picture="/feature/probe/pictures/three" caption="/feature/probe/words/two"]';
check( '...and its image attributes by their type, whatever its own source is: the slot of a picture counts, the key of a caption does not',
	\Nino\Modules\Components::sources( $sourcesApp, $sourcesBanner ) === [ [ 'name' => 'bannerof', 'kind' => 'image', 'source' => '/feature/probe/pictures/three', 'attribute' => 'picture' ] ]
	&& \Nino\Modules\Components::sources( $appData, $sourcesBanner ) === [] );

// The schema is checked where the component is registered
// php 8.4 adds a deprecation of its own to every E_USER_ERROR: not what is asked about here
$errorsOf = static fn( callable $run ): array => array_values( array_filter( ninoErrors( $run ), static fn( array $error ): bool => $error[0] !== E_DEPRECATED ) );
$noop = static fn( array &$appData, array $args ): string => '';
$goodSchema = [ 'label' => 'Probe', 'source' => 'text', 'attributes' => [ 'tone' => [ 'type' => 'select', 'options' => [ 'a', 'b' ], 'default' => 'a' ] ] ];
// An icon is the parts of a 24 by 24 picture, each a tag and the attributes of that tag
$goodIcon = [ [ 'path', [ 'd' => 'M6 12h12' ] ] ];
$withIcon = static fn( mixed $icon ): array => [ 'label' => 'x', 'source' => 'text', 'icon' => $icon ];
$withAttribute = static fn( array $declared ): array => [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'look' => $declared ] ];
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
	'a holding of a place that is none'		=> [ 'badholds', [ 'label' => 'x', 'source' => 'content', 'holds' => 'page' ], '"holds" names a place: section, card, hero or table' ],
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
	'a variant without options'								=> [ 'novariant', $withAttribute( [ 'type' => 'variant', 'default' => '' ] ), 'a variant needs "options"' ],
	'a variant whose default is not an option'	=> [ 'baddefaultvariant', $withAttribute( [ 'type' => 'variant', 'options' => [ '', 'big' ], 'default' => 'small' ] ), 'not one of the options' ],
	'a variant with an option that is no word'	=> [ 'wordvariant', $withAttribute( [ 'type' => 'variant', 'options' => [ '', 'Big' ], 'default' => '' ] ), 'the options of a variant are words, and "Big" is none' ],
	'a variant with an option of two classes'	=> [ 'classesvariant', $withAttribute( [ 'type' => 'variant', 'options' => [ '', 'big wide' ], 'default' => '' ] ), 'the options of a variant are words, and "big wide" is none' ],
	'a variant with a prefix that is no start of a class'	=> [ 'badprefix', $withAttribute( [ 'type' => 'variant', 'options' => [ '', 'big' ], 'default' => '', 'prefix' => 'nino-x-' ] ), '"prefix" is the start of a class' ],
	'a variant with a prefix of upper case letters'	=> [ 'caseprefix', $withAttribute( [ 'type' => 'variant', 'options' => [ '', 'big' ], 'default' => '', 'prefix' => 'Nino--' ] ), '"prefix" is the start of a class' ],
	'a variant with a prefix that is no string'	=> [ 'intprefix', $withAttribute( [ 'type' => 'variant', 'options' => [ '', 'big' ], 'default' => '', 'prefix' => 5 ] ), '"prefix" is the start of a class' ],
	'a prefix on a select'											=> [ 'selectprefix', $withAttribute( [ 'type' => 'select', 'options' => [ '', 'big' ], 'default' => '', 'prefix' => 'nino-x--' ] ), 'only a variant has a "prefix"' ],
	'an input of a string'											=> [ 'stringinput', $withAttribute( [ 'type' => 'string', 'default' => '', 'input' => 'segment' ] ), 'the type "string" has no "input"' ],
	'an input of an image slot'									=> [ 'imageinput', $withAttribute( [ 'type' => 'image', 'default' => '', 'input' => 'select' ] ), 'the type "image" has no "input"' ],
	'an input that is none of a select\'s'			=> [ 'switchinput', $withAttribute( [ 'type' => 'select', 'options' => [ '', 'a' ], 'default' => '', 'input' => 'switch' ] ), '"input" of a select must be one of select, segment, icons' ],
	'an input that is none of a variant\'s'			=> [ 'checkinput', $withAttribute( [ 'type' => 'variant', 'options' => [ '', 'a' ], 'default' => '', 'input' => 'checkbox' ] ), '"input" of a variant must be one of select, segment, icons' ],
	'an input that is none of a bool\'s'				=> [ 'segmentinput', $withAttribute( [ 'type' => 'bool', 'default' => false, 'input' => 'segment' ] ), '"input" of a bool must be one of switch, checkbox' ],
	'an input that is no word'									=> [ 'listinput', $withAttribute( [ 'type' => 'select', 'options' => [ '', 'a' ], 'default' => '', 'input' => [ 'icons' ] ] ), '"input" of a select must be one of' ],
	'icons for words that are no alignment'			=> [ 'wordsicons', $withAttribute( [ 'type' => 'select', 'options' => [ '', 'a', 'b' ], 'default' => '', 'input' => 'icons' ] ), '"input" icons is for an alignment: the options are "" and words of left|center|right or top|middle|bottom|last-bottom or start|center|end' ],
	'icons for the words of two alignments'			=> [ 'mixedicons', $withAttribute( [ 'type' => 'select', 'options' => [ '', 'left', 'top' ], 'default' => '', 'input' => 'icons' ] ), '"input" icons is for an alignment' ],
	'icons for options without the empty word'	=> [ 'emptyicons', $withAttribute( [ 'type' => 'select', 'options' => [ 'left', 'right' ], 'default' => 'left', 'input' => 'icons' ] ), '"input" icons is for an alignment' ],
	'icons for the empty word alone'						=> [ 'aloneicons', $withAttribute( [ 'type' => 'variant', 'options' => [ '' ], 'default' => '', 'input' => 'icons' ] ), '"input" icons is for an alignment' ],
	'a link that is no bool'										=> [ 'badlink', [ 'label' => 'x', 'source' => 'text', 'link' => 'yes' ], '"link" must be true or false' ],
	'a link of a component that has an href of its own'	=> [ 'hreflink', [ 'label' => 'x', 'source' => 'text', 'link' => true, 'attributes' => [ 'href' => [ 'type' => 'href', 'default' => '' ] ] ], '"link" gives the component "href" and "target", and the schema declares "href" itself' ],
	'a link of a component that has a target of its own'	=> [ 'targetlink', [ 'label' => 'x', 'source' => 'text', 'link' => true, 'attributes' => [ 'target' => [ 'type' => 'string', 'default' => '' ] ] ], 'and the schema declares "target" itself' ],
	'a registrar that is no slug'								=> [ 'badby', [ 'label' => 'x', 'source' => 'text', 'by' => 'Not A Slug' ], '"by" is a slug' ],
	'a registrar that is no string'							=> [ 'intby', [ 'label' => 'x', 'source' => 'text', 'by' => 5 ], '"by" is a slug' ],
	'a component of the kernel or of a project that names no group'	=> [ 'nogroup', [ 'label' => 'x', 'source' => 'text' ], '"group" must be one of section, hero, card, global' ],
	'a group that is none of the four'					=> [ 'fifthgroup', [ 'label' => 'x', 'source' => 'text', 'group' => 'footer' ], '"group" must be one of section, hero, card, global' ],
	'a group that is a list'										=> [ 'listgroup', [ 'label' => 'x', 'source' => 'text', 'group' => [ 'section' ] ], '"group" must be one of section, hero, card, global' ],
	'a component that says nino itself and names no group'	=> [ 'ninogroup', [ 'label' => 'x', 'source' => 'text', 'by' => 'nino' ], '"group" must be one of section, hero, card, global' ],
	'a group named by the component of a feature'	=> [ 'ownedgroup', [ 'label' => 'x', 'source' => 'text', 'by' => 'hello', 'group' => 'section' ], 'a feature\'s components are offered under the feature, not under a group' ],
	'a tab that is none of the words'						=> [ 'badtab', $withAttribute( [ 'type' => 'string', 'default' => '', 'tab' => 'sidebar' ] ), '"tab" must be one of '. implode( ', ', \Nino\Modules\Components::TABS ) ],
	'a tab that is an empty word'							=> [ 'emptytab', $withAttribute( [ 'type' => 'string', 'default' => '', 'tab' => '' ] ), '"tab" must be one of' ],
	'a tab in capitals'										=> [ 'captab', $withAttribute( [ 'type' => 'bool', 'default' => false, 'tab' => strtoupper( \Nino\Modules\Components::TABS[0] ) ] ), '"tab" must be one of' ],
	'a tab that is the list of the words'				=> [ 'listtab', $withAttribute( [ 'type' => 'select', 'options' => [ '', 'a' ], 'default' => '', 'tab' => \Nino\Modules\Components::TABS ] ), '"tab" must be one of' ],
	'a tab that is a number'								=> [ 'numtab', $withAttribute( [ 'type' => 'int', 'default' => 0, 'tab' => 5 ] ), '"tab" must be one of' ],
	'a stretched that is no bool'								=> [ 'stretchyes', [ 'label' => 'x', 'source' => 'text', 'group' => 'card', 'link' => true, 'stretched' => 'yes' ], '"stretched" must be true or false' ],
	'a stretched that is a number'							=> [ 'stretchone', [ 'label' => 'x', 'source' => 'text', 'group' => 'card', 'stretched' => 1 ], '"stretched" must be true or false' ],
	'a stretched of a component that holds components'	=> [ 'stretchholder', [ 'label' => 'x', 'source' => 'content', 'holds' => 'card', 'group' => 'card', 'stretched' => true ], '"stretched" is for a component that draws a link, and a component that holds components is none' ],
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
check( 'the registry was left alone by every refusal', array_keys( \Nino\Modules\Components::components( $appData ) ) === $kernelAll
	&& array_keys( \Nino\Modules\Components::stacks( $appData ) ) === $kernelStacks );
check( 'a schema that validates is returned complete, a stack with its assets', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'Rows', 'grid' => true, 'assets' => [ 'css' => [ '/features/Rows/assets/rows.css' ] ] ], true ) === [
	'label' => 'Rows', 'grid' => true, 'preview' => 'cells', 'attributes' => [], 'assets' => [ 'css' => [ '/features/Rows/assets/rows.css' ] ],
] );
$placed = [ 'label' => 'x', 'source' => 'text', 'group' => 'global', 'attributes' => [ 'tag' => [ 'type' => 'select', 'options' => [ 'div', 'h3' ], 'default' => 'h3' ] ] ];
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
check( '...a stack is not a place a component stands in: its clean schema has neither \'in\' nor \'draws\' - and it holds what its schema says, one of the places, and nothing where it says nothing',
	array_key_exists( 'in', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'in' => [ 'card' ], 'draws' => 'p .x', 'holds' => 'card' ], true ) ) === false
	&& array_key_exists( 'draws', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'in' => [ 'card' ], 'draws' => 'p .x', 'holds' => 'card' ], true ) ) === false
	&& \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'holds' => 'table' ], true )['holds'] === 'table'
	&& array_key_exists( 'holds', \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x' ], true ) ) === false
	&& str_contains( (string) \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'holds' => 'page' ], true ), '"holds" names a place' ) === true );
// What a component holds: one of the places, which is what its content is the calls of - and only where its source is its content
$holding = [ 'label' => 'x', 'source' => 'content', 'group' => 'global', 'attributes' => [ 'tag' => [ 'type' => 'select', 'options' => [ 'div', 'h3' ], 'default' => 'h3' ] ] ];
$holdsAnswer = static function( array $schema ): string {
	$answer = \Nino\Modules\Components::validate( 'box', $schema );
	return is_string( $answer ) === true ? $answer : '';
};
check( 'validate() takes what a component holds - one of the four places, with the source content - and carries it in the clean schema; a schema that says nothing holds nothing',
	array_filter( [ 'section', 'card', 'hero', 'table' ], static fn( string $place ): bool => ( \Nino\Modules\Components::validate( 'box', $holding + [ 'holds' => $place ] )['holds'] ?? null ) !== $place ) === []
	&& array_key_exists( 'holds', \Nino\Modules\Components::validate( 'box', $holding ) ) === false );
check( '...and gives the sentence of the first thing wrong with it: a place that is none (the cell of a loop is not one any more), a list, a number, an empty word - and a source that is not the content',
	array_filter( [ 'page', 'cell', 'Card', [ 'card' ], 5, '', true ], static fn( mixed $wrong ): bool => str_contains( $holdsAnswer( $holding + [ 'holds' => $wrong ] ), '"holds" names a place: section, card, hero or table' ) === false ) === []
	&& array_filter( [ 'text', 'image', 'href', 'none' ], static fn( string $source ): bool => str_contains( $holdsAnswer( [ 'source' => $source, 'holds' => 'card' ] + $holding ), '"holds" needs the source content' ) === false ) === []
	&& $holdsAnswer( [ 'holds' => 'weird', 'source' => 'text' ] + $holding ) !== '' && str_contains( $holdsAnswer( [ 'holds' => 'weird', 'source' => 'text' ] + $holding ), 'names a place' ) === true );
check( 'validate() carries stretched in the clean schema: true where a component that draws a link says so - with link and without - and false where the schema says nothing or says false',
	( \Nino\Modules\Components::validate( 'box', $holding + [ 'stretched' => true ] )['stretched'] ?? null ) === true
	&& ( \Nino\Modules\Components::validate( 'box', $holding + [ 'link' => true, 'stretched' => true ] )['stretched'] ?? null ) === true
	&& ( \Nino\Modules\Components::validate( 'box', $holding )['stretched'] ?? null ) === false
	&& ( \Nino\Modules\Components::validate( 'box', $holding + [ 'stretched' => false ] )['stretched'] ?? null ) === false
	&& ( \Nino\Modules\Components::validate( 'box', $holding + [ 'link' => true ] )['stretched'] ?? null ) === false );
check( '...a holder is no link and has none to give: stretched on a component that holds components is refused with the sentence, saying false or nothing is not, and so is a value that is no bool',
	str_contains( $holdsAnswer( $holding + [ 'holds' => 'card', 'stretched' => true ] ), '"stretched" is for a component that draws a link' ) === true
	&& is_array( \Nino\Modules\Components::validate( 'box', $holding + [ 'holds' => 'card', 'stretched' => false ] ) ) === true && is_array( \Nino\Modules\Components::validate( 'box', $holding + [ 'holds' => 'card' ] ) ) === true
	&& array_filter( [ 'yes', 1, 0, 'true', [ true ] ], static fn( mixed $wrong ): bool => str_contains( $holdsAnswer( $holding + [ 'stretched' => $wrong ] ), '"stretched" must be true or false' ) === false ) === [] );
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
\Nino\Modules\Components::addComponent( $appData, 'pictured', $noop, [ 'label' => 'Pictured', 'source' => 'none', 'group' => 'global', 'icon' => $pictured ] );
check( 'a component registered with an icon carries it in the registry as it was given',
	( \Nino\Modules\Components::components( $appData )['pictured']['icon'] ?? null ) === $pictured
	&& array_key_exists( 'icon', \Nino\Modules\Components::components( $appData )['pictured'] ) === true );
check( '...and in the json of the registry, which a tool reads: the parts are lists and the attributes of a part maps, in the order given',
	str_contains( (string) json_encode( \Nino\Modules\Components::components( $appData )['pictured'] ), '"icon":[["circle",{"cx":"12","cy":"12","r":"4"}],["path",{"d":"M4 12h16"}],["rect",{"rx":"2","x":"3","y":"3","width":"18","height":"18"}]]' ) === true
	&& ( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true ) )['pictured']['icon'] === $pictured );
// Where a tool offers the six titles, and what each of them draws: the title and the subtitle of a section are offered in the hero as well, and the hero's own in a section
$kernelPlaces = [ 'title' => [ 'hero', 'section' ], 'card-title' => [ 'card' ], 'hero-title' => [ 'hero', 'section' ], 'subtitle' => [ 'hero', 'section' ], 'card-subtitle' => [ 'card' ], 'hero-subtitle' => [ 'hero', 'section' ] ];
$kernelDraws = [ 'title' => '{tag} .nino-section-title', 'card-title' => '{tag} .nino-card-title', 'hero-title' => '{tag} .nino-hero-title',
	'subtitle' => '{tag} .nino-section-subtitle', 'card-subtitle' => '{tag} .nino-card-subtitle', 'hero-subtitle' => '{tag} .nino-hero-subtitle', 'button' => 'a .nino-btn' ];
// The tags each of the six may be drawn as, with the one it is where the shortcode names none first: a title in a hero is an h2, a section's an h3, a card's an h4
$sixTags = [ 'title' => [ 'h3', [ 'div', 'h2', 'h3', 'h4' ] ], 'subtitle' => [ 'div', [ 'div', 'p', 'h4', 'h5' ] ], 'card-title' => [ 'h4', [ 'div', 'h3', 'h4' ] ], 'card-subtitle' => [ 'p', [ 'div', 'p', 'h5', 'h6' ] ],
	'hero-title' => [ 'h2', [ 'div', 'h2', 'h3' ] ], 'hero-subtitle' => [ 'p', [ 'div', 'p', 'h3', 'h4' ] ] ];
check( 'the registry says where each of the six titles is offered, and what it draws - the class is the one the component is named for',
	array_filter( $kernelPlaces, static fn( array $places, string $name ): bool => ( \Nino\Modules\Components::components( $appData )[$name]['in'] ?? null ) !== $places, ARRAY_FILTER_USE_BOTH ) === []
	&& array_filter( $kernelDraws, static fn( string $draws, string $name ): bool => ( \Nino\Modules\Components::components( $appData )[$name]['draws'] ?? null ) !== $draws, ARRAY_FILTER_USE_BOTH ) === [] );
check( '...the button draws and is offered everywhere; text, image, html and spacer declare neither, and neither does a stack',
	array_key_exists( 'in', \Nino\Modules\Components::components( $appData )['button'] ) === false
	&& array_filter( [ 'text', 'image', 'html', 'spacer' ], static fn( string $name ): bool => array_key_exists( 'in', \Nino\Modules\Components::components( $appData )[$name] ) === true
		|| array_key_exists( 'draws', \Nino\Modules\Components::components( $appData )[$name] ) === true ) === []
	&& array_filter( \Nino\Modules\Components::stacks( $appData ), static fn( array $stack ): bool => array_key_exists( 'in', $stack ) === true || array_key_exists( 'draws', $stack ) === true ) === [] );
check( '...and every title has a tag of its own - the tags it may be drawn as, and the one it is - a style and a colour, and nothing else',
	array_filter( $sixTags, static fn( array $tags, string $name ): bool => array_keys( \Nino\Modules\Components::components( $appData )[$name]['attributes'] ) !== [ 'tag', 'style', 'color' ]
		|| \Nino\Modules\Components::components( $appData )[$name]['attributes']['tag']['type'] !== 'select'
		|| \Nino\Modules\Components::components( $appData )[$name]['attributes']['tag']['options'] !== $tags[1]
		|| \Nino\Modules\Components::components( $appData )[$name]['attributes']['tag']['default'] !== $tags[0]
		|| \Nino\Modules\Components::components( $appData )[$name]['attributes']['tag']['label'] !== [ 'en_US' => 'HTML tag', 'de_DE' => 'HTML-Tag' ], ARRAY_FILTER_USE_BOTH ) === []
	&& array_filter( array_keys( $kernelPlaces ), static fn( string $name ): bool => \Nino\Modules\Components::components( $appData )[$name]['label'] === \Nino\Modules\Components::components( $appData )['title']['label']
		|| \Nino\Modules\Components::components( $appData )[$name]['preview'] !== 'title' || \Nino\Modules\Components::components( $appData )[$name]['source'] !== 'text' ) === [ 'title' ] );
// The text and the image of a card: offered in a card alone, drawing the class a card styles them by, and made as the text and the image are
$registryOf = static fn( string $name ): array => \Nino\Modules\Components::components( $appData )[$name];
check( 'the registry has the text of a card: labelled in both languages, a text read from a key or a field, offered in a card alone, drawing the <div> of nino-card-descr',
	$registryOf( 'card-text' )['label'] === [ 'en_US' => 'Text (Card)', 'de_DE' => 'Text (Karte)' ] && $registryOf( 'card-text' )['source'] === 'text' && $registryOf( 'card-text' )['preview'] === 'text'
	&& $registryOf( 'card-text' )['in'] === [ 'card' ] && $registryOf( 'card-text' )['draws'] === 'div .nino-card-descr' && array_key_exists( 'holds', $registryOf( 'card-text' ) ) === false );
check( '...with the attributes of [text], format, style and colour, word for word - [text] has the tag besides, after the format, which a text in a card has none of - and [text] is still offered everywhere and draws nothing',
	array_keys( $registryOf( 'card-text' )['attributes'] ) === [ 'format', 'style', 'color' ] && $registryOf( 'card-text' )['attributes'] === array_diff_key( $registryOf( 'text' )['attributes'], [ 'tag' => 1 ] )
	&& array_keys( $registryOf( 'text' )['attributes'] ) === [ 'format', 'tag', 'style', 'color' ]
	&& array_key_exists( 'in', $registryOf( 'text' ) ) === false && array_key_exists( 'draws', $registryOf( 'text' ) ) === false );
check( 'the registry has the image of a card: labelled in both languages, a picture of a slot or an image field, offered in a card alone, drawing the <img> of nino-card-img',
	$registryOf( 'card-image' )['label'] === [ 'en_US' => 'Image (Card)', 'de_DE' => 'Bild (Karte)' ] && $registryOf( 'card-image' )['source'] === 'image' && $registryOf( 'card-image' )['preview'] === 'image'
	&& $registryOf( 'card-image' )['in'] === [ 'card' ] && $registryOf( 'card-image' )['draws'] === 'img .nino-card-img' && array_key_exists( 'holds', $registryOf( 'card-image' ) ) === false );
check( '...its style is the one choice of a card\'s picture, a variant that is none or inline - the class nino-card-img--inline - and its alt text, focus and ratio are those of [image] word for word, the hints with them',
	array_keys( $registryOf( 'card-image' )['attributes'] ) === [ 'style', 'alt', 'focus', 'ratio' ]
	&& $registryOf( 'card-image' )['attributes']['style'] === [ 'type' => 'variant', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ], 'hint' => '', 'options' => [ '', 'inline' ], 'prefix' => 'nino-card-img--', 'default' => '' ]
	&& array_diff_key( $registryOf( 'card-image' )['attributes'], [ 'style' => 1 ] ) === $registryOf( 'image' )['attributes'] );
check( '[image] has no style: its attributes are the alt text, the focus and the ratio, in that order, and its defaults say none - the radius of its frame is the one of its kind, or the other strength where a class of its own says so',
	array_keys( $registryOf( 'image' )['attributes'] ) === [ 'alt', 'focus', 'ratio' ]
	&& \Nino\Modules\Components::defaults( $appData, 'image' ) === [ 'alt' => '', 'focus' => '', 'ratio' => '', 'href' => '', 'target' => '', 'class' => '' ]
	&& \Nino\Modules\Components::defaults( $appData, 'card-image' ) === [ 'style' => '', 'alt' => '', 'focus' => '', 'ratio' => '', 'href' => '', 'target' => '', 'class' => '' ] );
// The frame of [image] is drawn by Nino.css for what the component can write: every modifier the stylesheet has for it is a ratio of the registry, and every ratio has its modifier
preg_match_all( '/\.nino-image--([\w-]+)/', $hookCss, $imageModifiers );
$imageModifiers = array_values( array_unique( $imageModifiers[1] ) );
$imageRatios = array_values( array_filter( $registryOf( 'image' )['attributes']['ratio']['options'], static fn( string $ratio ): bool => $ratio !== '' ) );
sort( $imageModifiers );
sort( $imageRatios );
check( '...and the modifiers of its frame in Nino.css are the ratios it can write, no more and no fewer: the stylesheet and the registry say the same words'
	. ( $imageModifiers === $imageRatios ? '' : ' - the stylesheet says '. implode( ' ', $imageModifiers ). ', the registry '. implode( ' ', $imageRatios ) ), $imageRatios !== [] && $imageModifiers === $imageRatios );
preg_match_all( '/\.nino-tiles--([\w-]+) > \.nino-grid-row > \*/', $hookCss, $tileModifiers );
$tileModifiers = array_values( array_unique( $tileModifiers[1] ) );
sort( $tileModifiers );
check( '...and so are the ratio classes of a section of tiles: .nino-tiles--<ratio> for the words of the ratio attribute, no more and no fewer',
	$imageRatios !== [] && $tileModifiers === $imageRatios );
// The ratio of a frame is the ratio of the picture in it: the picture fills the frame at a height of 100%, which is worked out against the height the frame has from its ratio, and where it is not worked out
// - a frame in a box that sizes it by its content - the picture would have the ratio of its own, in a frame of another. So the <img> directly in the frame says the ratio as the frame does
$ratioWrong = [];
foreach( $imageRatios as $imageRatio )
	foreach( [ '.nino-image--'. $imageRatio, '.nino-image--'. $imageRatio. ' > img' ] as $ratioSelector )
		if( $groundSets( $groundOf( $ratioSelector ), 'aspect-ratio', str_replace( '-', ' / ', $imageRatio ) ) === false )
			$ratioWrong[] = $ratioSelector;
check( 'every ratio of the frame is said by the frame and by the <img> directly in it - .nino-image--<x>-<y> and .nino-image--<x>-<y> > img, aspect-ratio: x / y - so that the picture has the ratio of the frame whether or not its height of 100% is worked out'
	. ( $ratioWrong === [] ? '' : ' - not: '. implode( ', ', $ratioWrong ) ), $ratioWrong === [] && $imageRatios !== [] );
// The icons of the sixteen: each component of the kernel is shown by the parts of a 24 by 24 icon of Lucide, which the registry hands to a tool
$iconTags = [ 'path' => [ [ 'd' ], [] ], 'circle' => [ [ 'cx', 'cy', 'r' ], [] ], 'ellipse' => [ [ 'cx', 'cy', 'rx', 'ry' ], [] ], 'rect' => [ [ 'x', 'y', 'width', 'height' ], [ 'rx', 'ry' ] ],
	'line' => [ [ 'x1', 'y1', 'x2', 'y2' ], [] ], 'polyline' => [ [ 'points' ], [] ], 'polygon' => [ [ 'points' ], [] ] ];
$iconOf = static fn( string $name ): mixed => \Nino\Modules\Components::components( $appData )[$name]['icon'] ?? null;
$iconWrong = [];
foreach( $kernelAll as $iconName ) {
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
check( 'each of the sixteen components of the kernel carries an icon: a list of one to five parts, each a tag among the seven and a map of strings that holds the attributes of that tag'. ( $iconWrong === [] ? '' : ' - '. implode( ', ', $iconWrong ) ), $iconWrong === [] );
check( '...the title is drawn by the three paths of the heading of Lucide, the card by a rect and a path, the image by a rect, a circle and a path and the logo by a circle and a path, the circle half filled',
	$iconOf( 'title' ) === [ [ 'path', [ 'd' => 'M6 12h12' ] ], [ 'path', [ 'd' => 'M6 20V4' ] ], [ 'path', [ 'd' => 'M18 20V4' ] ] ]
	&& array_column( (array) $iconOf( 'card' ), 0 ) === [ 'rect', 'path' ] && array_column( (array) $iconOf( 'image' ), 0 ) === [ 'rect', 'circle', 'path' ]
	&& array_column( (array) $iconOf( 'logo' ), 0 ) === [ 'circle', 'path' ] );
$iconKinds = [ 'title', 'subtitle', 'text', 'card', 'button', 'image', 'logo', 'html', 'spacer', 'scroll-hint' ];
check( '...the three titles are drawn alike and so are the three subtitles, the two texts and the two images, and the title, the subtitle, the text, the card, the button, the image, the logo, the html, the spacer and the scroll hint each have a picture of their own',
	$iconOf( 'title' ) === $iconOf( 'card-title' ) && $iconOf( 'title' ) === $iconOf( 'hero-title' )
	&& $iconOf( 'subtitle' ) === $iconOf( 'card-subtitle' ) && $iconOf( 'subtitle' ) === $iconOf( 'hero-subtitle' )
	&& $iconOf( 'text' ) === $iconOf( 'card-text' ) && $iconOf( 'image' ) === $iconOf( 'card-image' )
	&& count( array_unique( array_map( static fn( string $name ): string => (string) json_encode( $iconOf( $name ) ), $iconKinds ) ) ) === count( $iconKinds ) );
// Which icon of Lucide a component carries shows in the first part of it: the ten icons begin with ten different parts
$iconFirst = [
	'title' => [ 'path', [ 'd' => 'M6 12h12' ] ], 'subtitle' => [ 'path', [ 'd' => 'M12 4v16' ] ], 'text' => [ 'path', [ 'd' => 'M21 5H3' ] ],
	'card' => [ 'rect', [ 'width' => '18', 'height' => '18', 'x' => '3', 'y' => '3', 'rx' => '2' ] ], 'button' => [ 'path', [ 'd' => 'M14 4.1 12 6' ] ],
	'image' => [ 'rect', [ 'width' => '18', 'height' => '18', 'x' => '3', 'y' => '3', 'rx' => '2', 'ry' => '2' ] ], 'logo' => [ 'circle', [ 'cx' => '12', 'cy' => '12', 'r' => '10' ] ], 'html' => [ 'path', [ 'd' => 'm16 18 6-6-6-6' ] ], 'spacer' => [ 'path', [ 'd' => 'm16 16-4 4-4-4' ] ],
	'scroll-hint' => [ 'path', [ 'd' => 'm6 9 6 6 6-6' ] ],
];
$iconKindOf = [ 'title' => 'title', 'card-title' => 'title', 'hero-title' => 'title', 'subtitle' => 'subtitle', 'card-subtitle' => 'subtitle', 'hero-subtitle' => 'subtitle',
	'text' => 'text', 'card-text' => 'text', 'card' => 'card', 'button' => 'button', 'image' => 'image', 'card-image' => 'image', 'logo' => 'logo', 'html' => 'html', 'spacer' => 'spacer', 'scroll-hint' => 'scroll-hint' ];
check( '...the titles are drawn as the heading of Lucide, the subtitles as its type, the texts as lines, the card as a panel, the button as a pointer, the images as a picture, the logo as a half-filled circle, the html as code, the spacer as a separator and the scroll hint as a chevron',
	array_filter( $iconKindOf, static fn( string $kind, string $name ): bool => ( $iconOf( $name )[0] ?? null ) !== $iconFirst[$kind], ARRAY_FILTER_USE_BOTH ) === [] && $iconOf( 'scroll-hint' ) === [ $iconFirst['scroll-hint'] ] );
check( '...and the json of the registry gives each of the sixteen back as it is: the parts as lists and the attributes of a part as maps',
	array_filter( $kernelAll, static fn( string $name ): bool => ( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true ) )[$name]['icon'] !== $iconOf( $name ) ) === [] );
// The card: a component that holds components. What the registry says of it, and of nothing else of the kernel
$cardSchema = \Nino\Modules\Components::components( $appData )['card'];
check( 'the card is declared as the registry says: a label in both languages, the source content, the picture of a block, its icon, offered in a section and a hero, drawing the <article> with its class, and holding the components of a card',
	$cardSchema['label'] === [ 'en_US' => 'Card', 'de_DE' => 'Karte' ] && $cardSchema['source'] === 'content' && $cardSchema['loop'] === true && $cardSchema['preview'] === 'block'
	&& $cardSchema['in'] === [ 'section', 'hero' ] && $cardSchema['draws'] === 'article .nino-card' && $cardSchema['holds'] === 'card'
	&& array_keys( $cardSchema ) === [ 'label', 'source', 'loop', 'preview', 'icon', 'attributes', 'in', 'draws', 'holds', 'stretched', 'link', 'by', 'group' ] );
check( '...it says stretched itself no more than a holder can - false, and the registry gives it back in json - and its schema has no key for the component whose address it takes: the title says that of itself, \'card-title\' and \'title\' are the two components of the kernel that do',
	$cardSchema['stretched'] === false && ( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true ) )['card']['stretched'] === false
	&& array_key_exists( 'stretches', $cardSchema ) === false
	&& ( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true ) )['card-title']['stretched'] === true
	&& array_keys( array_filter( \Nino\Modules\Components::components( $appData ), static fn( array $schema ): bool => $schema['stretched'] === true ) ) === [ 'title', 'card-title' ]
	&& array_filter( \Nino\Modules\Components::components( $appData ), static fn( array $schema ): bool => array_key_exists( 'stretched', $schema ) === false ) === []
	&& \Nino\Modules\Components::components( $appData )['card-title']['link'] === true && \Nino\Modules\Components::components( $appData )['card-title']['in'] === [ 'card' ]
	&& \Nino\Modules\Components::components( $appData )['title']['link'] === true && \Nino\Modules\Components::components( $appData )['title']['stretched'] === true );
check( '...its attributes are ten, in the order the form shows them: whether it fills, the padding, where its content stands, how it is aligned, the ground, the border, whether the whole card is the link of its title, the picture, its focus and the dim',
	array_keys( $cardSchema['attributes'] ) === [ 'fill', 'padding', 'vertical', 'align', 'ground', 'borderless', 'linked', 'background', 'focus', 'dim' ] );
$cardTypes = [ 'ground' => 'select', 'padding' => 'select', 'fill' => 'bool', 'vertical' => 'select', 'align' => 'select', 'background' => 'image', 'focus' => 'select', 'dim' => 'bool', 'borderless' => 'bool', 'linked' => 'bool' ];
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
	// A label in both languages always, a hint in both where the attribute has one - a select whose label says it all has none, and the registry hands that on as an empty hint
	foreach( [ 'label', 'hint' ] as $cardWords )
		if( ( $cardWords === 'label' || ( $cardAttribute['hint'] ?? '' ) !== '' ) && ( is_array( $cardAttribute[$cardWords] ) === false || array_keys( $cardAttribute[$cardWords] ) !== [ 'en_US', 'de_DE' ] || in_array( '', $cardAttribute[$cardWords], true ) === true ) )
			$cardWrong[] = $cardName. ' has no '. $cardWords. ' in both languages';
}
check( '...each with its type, its options and its default - the padding a step of the space scale or none, a bool off - and a label in both languages, and a hint in both where it has one'. ( $cardWrong === [] ? '' : ' - '. implode( ', ', $cardWrong ) ), $cardWrong === [] );
check( '...the focus is the focus of [image], word for word, and the other labels are the ones of the owner\'s decision',
	$cardSchema['attributes']['focus']['label'] === \Nino\Modules\Components::components( $appData )['image']['attributes']['focus']['label']
	&& $cardSchema['attributes']['focus']['hint'] === \Nino\Modules\Components::components( $appData )['image']['attributes']['focus']['hint']
	&& $cardSchema['attributes']['ground']['label'] === [ 'en_US' => 'Background colour', 'de_DE' => 'Hintergrund-Farbe' ] && $cardSchema['attributes']['padding']['label'] === [ 'en_US' => 'Padding', 'de_DE' => 'Padding' ]
	&& $cardSchema['attributes']['fill']['label'] === [ 'en_US' => 'Full height', 'de_DE' => 'Volle Höhe' ] && $cardSchema['attributes']['vertical']['label'] === [ 'en_US' => 'Vertical element alignment', 'de_DE' => 'Vertikale Element-Ausrichtung' ]
	&& $cardSchema['attributes']['align']['label'] === [ 'en_US' => 'Horizontal text alignment', 'de_DE' => 'Horizontale Text-Ausrichtung' ] && $cardSchema['attributes']['background']['label'] === [ 'en_US' => 'Background picture', 'de_DE' => 'Hintergrund-Bild' ]
	&& $cardSchema['attributes']['dim']['label'] === [ 'en_US' => 'Dim the picture', 'de_DE' => 'Bild abdunkeln' ] && $cardSchema['attributes']['borderless']['label'] === [ 'en_US' => 'Without border', 'de_DE' => 'Ohne Rahmen' ] );
check( '...the card is the one component of the kernel that holds components, and the table the one stack - which holds the place table',
	array_keys( array_filter( \Nino\Modules\Components::components( $appData ), static fn( array $schema ): bool => array_key_exists( 'holds', $schema ) === true ) ) === [ 'card' ]
	&& array_keys( array_filter( \Nino\Modules\Components::stacks( $appData ), static fn( array $stack ): bool => array_key_exists( 'holds', $stack ) === true ) ) === [ 'table' ]
	&& \Nino\Modules\Components::defaults( $appData, 'card' ) === [ 'fill' => '0', 'padding' => '', 'vertical' => '', 'align' => '', 'ground' => '', 'borderless' => '0', 'linked' => '0', 'background' => '', 'focus' => '', 'dim' => '0', 'class' => '' ] );
// The variants are factors of the role (Nino.css), and the label of the style says it all: none of the seven carries a hint
check( '...and the style of the six titles and of the two texts has no hint under its name, in neither language',
	array_filter( [ 'title', 'card-title', 'hero-title', 'subtitle', 'card-subtitle', 'hero-subtitle', 'text', 'card-text' ], static fn( string $name ): bool => ( \Nino\Modules\Components::components( $appData )[$name]['attributes']['style']['hint'] ?? '' ) !== '' ) === [] );
// A variant: a select whose words are the modifiers of a class - the style of the text and of the pictures, the colour of a text
$lookOf = static fn( array $declared ): array|string => \Nino\Modules\Components::validate( 'probe', [ 'label' => 'x', 'source' => 'text', 'group' => 'global', 'attributes' => [ 'look' => $declared ] ] );
$lookGood = [ 'type' => 'variant', 'options' => [ '', 'big', 'extra-big' ], 'default' => 'big' ];
check( 'validate() takes a variant - the options of a select, the default among them, and the empty word one of them or not - and its clean schema is a select\'s with the prefix, where it has one, between the options and the default',
	( $lookOf( $lookGood )['attributes']['look'] ?? null ) === [ 'type' => 'variant', 'label' => 'look', 'hint' => '', 'options' => [ '', 'big', 'extra-big' ], 'default' => 'big' ]
	&& ( $lookOf( [ 'options' => [ 'a', 'b' ], 'default' => 'b', 'prefix' => 'x-y--' ] + $lookGood )['attributes']['look'] ?? null ) === [ 'type' => 'variant', 'label' => 'look', 'hint' => '', 'options' => [ 'a', 'b' ], 'prefix' => 'x-y--', 'default' => 'b' ]
	&& is_array( $lookOf( [ 'options' => [ '' ], 'default' => '' ] + $lookGood ) ) === true && is_array( $lookOf( [ 'options' => [ '', '1', '2x' ], 'default' => '1' ] + $lookGood ) ) === true );
check( '...a variant outside its options is the default, as a select is, and the default is what defaults() hands out',
	( static function() use ( &$appData ): bool {
		\Nino\Modules\Components::addComponent( $appData, 'lookprobe', static fn( array &$appData, array $args ): string => $args['look'], [ 'label' => 'x', 'source' => 'none', 'group' => 'global', 'attributes' => [ 'look' => [ 'type' => 'variant', 'options' => [ 'a', 'b' ], 'default' => 'b' ] ] ] );
		return \Nino\Html::renderHtml( $appData, '[lookprobe look="a"][lookprobe look="c"][lookprobe]' ) === 'abb' && \Nino\Modules\Components::defaults( $appData, 'lookprobe' ) === [ 'look' => 'b', 'class' => '' ];
	} )() );

// How a tool may draw an attribute: a hint the clean schema keeps right after the hint of the attribute and the renderer never reads
$inputOf = static fn( array $declared ): array => (array) ( $lookOf( $declared )['attributes']['look'] ?? [] );
$inputWords = [ '', 'left', 'center', 'right' ];
check( 'validate() takes how a tool draws an attribute - a select or a variant as a select, a row of buttons or the icons of an alignment, a bool as a switch or a checkbox - and keeps the hint right after the hint of the attribute; an attribute that says none has no such key',
	array_filter( [ 'select', 'segment', 'icons' ], static fn( string $input ): bool => array_keys( $inputOf( [ 'type' => 'select', 'options' => $inputWords, 'default' => '', 'input' => $input ] ) ) !== [ 'type', 'label', 'hint', 'input', 'options', 'default' ]
		|| array_keys( $inputOf( [ 'type' => 'variant', 'options' => $inputWords, 'default' => '', 'input' => $input ] ) ) !== [ 'type', 'label', 'hint', 'input', 'options', 'default' ] ) === []
	&& array_filter( [ 'switch', 'checkbox' ], static fn( string $input ): bool => array_keys( $inputOf( [ 'type' => 'bool', 'default' => false, 'input' => $input ] ) ) !== [ 'type', 'label', 'hint', 'input', 'default' ] ) === []
	&& array_key_exists( 'input', $inputOf( [ 'type' => 'select', 'options' => $inputWords, 'default' => '' ] ) ) === false && array_key_exists( 'input', $inputOf( [ 'type' => 'bool', 'default' => false ] ) ) === false );
check( '...the icons are drawn for an alignment: the empty word and words of left|center|right, of top|middle|bottom|last-bottom or of start|center|end - some of them, as a card\'s vertical alignment has no top, the empty word is it',
	array_filter( [ [ '', 'left', 'right' ], [ '', 'center' ], [ '', 'top', 'middle', 'bottom' ], [ '', 'middle', 'bottom', 'last-bottom' ], [ '', 'start', 'center', 'end' ], [ 'center', '', 'end' ] ],
		static fn( array $options ): bool => is_array( $lookOf( [ 'type' => 'select', 'options' => $options, 'default' => '', 'input' => 'icons' ] ) ) === false ) === [] );

// On which tab of its dialog a tool draws an attribute: a hint of the same kind as the input - one word of TABS, for an attribute of any type, kept by the clean schema, handed on by the registry and read by nothing that renders
$tabOf = static fn( array $declared ): array => (array) ( $lookOf( $declared )['attributes']['look'] ?? [] );
$tabRest = [
	'string'	=> [ 'default' => '' ],
	'int'			=> [ 'default' => 0 ],
	'bool'		=> [ 'default' => false ],
	'select'	=> [ 'options' => [ '', 'a' ], 'default' => '' ],
	'variant'	=> [ 'options' => [ '', 'a' ], 'default' => '' ],
	'lines'		=> [ 'default' => '' ],
	'key'			=> [ 'default' => '' ],
	'image'		=> [ 'default' => '' ],
	'href'		=> [ 'default' => '' ],
];
$tabWrong = [];
foreach( \Nino\Modules\Components::TYPES as $tabType )
	foreach( \Nino\Modules\Components::TABS as $tabWord )
		if( isset( $tabRest[$tabType] ) === false || ( $tabOf( [ 'type' => $tabType, 'tab' => $tabWord ] + $tabRest[$tabType] )['tab'] ?? null ) !== $tabWord
			|| array_key_exists( 'tab', $tabOf( [ 'type' => $tabType ] + $tabRest[$tabType] ) ) === true )
			$tabWrong[] = $tabType. ' '. $tabWord;
check( 'validate() takes the tab a tool draws an attribute on - one word of TABS, for an attribute of any type - and keeps it in the clean schema; an attribute that says none has no such key'. ( $tabWrong === [] ? '' : ' - not: '. implode( ', ', $tabWrong ) ),
	$tabWrong === [] && \Nino\Modules\Components::TABS !== [] && array_is_list( \Nino\Modules\Components::TABS ) === true
	&& array_filter( \Nino\Modules\Components::TABS, static fn( string $word ): bool => preg_match( '/^[a-z]+(?:-[a-z]+)*$/D', $word ) !== 1 ) === [] );
$tabSchema = static fn( array $tab ): array => [ 'label' => 'x', 'source' => 'none', 'group' => 'global', 'attributes' => [
	'look' => [ 'type' => 'select', 'options' => [ 'a', 'b' ], 'default' => 'a' ] + $tab,
	'more' => [ 'type' => 'string', 'default' => '' ],
] ];
$tabRender = static fn( array &$appData, array $args ): string => $args['look']. $args['more'];
\Nino\Modules\Components::addComponent( $appData, 'tabbed', $tabRender, $tabSchema( [ 'tab' => \Nino\Modules\Components::TABS[0] ] ) );
\Nino\Modules\Components::addComponent( $appData, 'tabless', $tabRender, $tabSchema( [] ) );
$tabJson = (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $appData ) ), true );
check( 'a component registered with a tab on one attribute carries it in the registry and in the json a tool reads - on that attribute and on no other - and the component that says none has none',
	( \Nino\Modules\Components::components( $appData )['tabbed']['attributes']['look']['tab'] ?? null ) === \Nino\Modules\Components::TABS[0]
	&& array_key_exists( 'tab', \Nino\Modules\Components::components( $appData )['tabbed']['attributes']['more'] ) === false
	&& ( $tabJson['tabbed']['attributes']['look']['tab'] ?? null ) === \Nino\Modules\Components::TABS[0] && array_key_exists( 'tab', $tabJson['tabbed']['attributes']['more'] ) === false
	&& array_filter( $tabJson['tabless']['attributes'], static fn( array $declared ): bool => array_key_exists( 'tab', $declared ) === true ) === [] );
check( '...the rendering reads nothing of it: the defaults, and what the two components draw, are the same with the tab and without',
	\Nino\Modules\Components::defaults( $appData, 'tabbed' ) === \Nino\Modules\Components::defaults( $appData, 'tabless' )
	&& \Nino\Html::renderHtml( $appData, '[tabbed look="b" more="x"][tabbed look="c"]' ) === \Nino\Html::renderHtml( $appData, '[tabless look="b" more="x"][tabless look="c"]' )
	&& \Nino\Html::renderHtml( $appData, '[tabbed look="b" more="x"][tabbed look="c"]' ) === 'bxa' );
check( '...a clean schema with a tab validates again as it is, and the component of a feature and the attribute of a stack may say one like the kernel\'s',
	\Nino\Modules\Components::validate( 'tabbed', \Nino\Modules\Components::components( $appData )['tabbed'] ) === \Nino\Modules\Components::components( $appData )['tabbed']
	&& ( \Nino\Modules\Components::validate( 'probe', array_diff_key( $tabSchema( [ 'tab' => \Nino\Modules\Components::TABS[0] ] ), [ 'group' => 1 ] ), false, 'hello' )['attributes']['look']['tab'] ?? null ) === \Nino\Modules\Components::TABS[0]
	&& ( \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'attributes' => [ 'look' => [ 'type' => 'string', 'default' => '', 'tab' => \Nino\Modules\Components::TABS[0] ] ] ], true )['attributes']['look']['tab'] ?? null ) === \Nino\Modules\Components::TABS[0] );

// A link on a component, who registered it: the clean schema says both, after the keys of its place, and a stack has neither
$linkable = [ 'label' => 'x', 'source' => 'text', 'attributes' => [ 'tag' => [ 'type' => 'select', 'options' => [ 'div', 'h3' ], 'default' => 'h3' ] ] ];
check( 'validate() takes whether a component can be a link and who registered it - false and nino where the schema says nothing - and the clean schema has the two last, after holds; a stack has neither, whatever it says',
	array_keys( \Nino\Modules\Components::validate( 'probe', $linkable + [ 'in' => [ 'card' ], 'draws' => '{tag} .x', 'link' => true, 'by' => 'hello' ] ) ) === [ 'label', 'source', 'loop', 'preview', 'attributes', 'in', 'draws', 'stretched', 'link', 'by' ]
	&& ( \Nino\Modules\Components::validate( 'probe', $linkable + [ 'link' => true, 'by' => 'hello' ] )['link'] ?? null ) === true && ( \Nino\Modules\Components::validate( 'probe', $linkable + [ 'link' => true, 'by' => 'hello' ] )['by'] ?? null ) === 'hello'
	&& ( \Nino\Modules\Components::validate( 'probe', $linkable + [ 'group' => 'global' ] )['link'] ?? null ) === false && ( \Nino\Modules\Components::validate( 'probe', $linkable + [ 'group' => 'global' ] )['by'] ?? null ) === 'nino'
	&& array_diff_key( (array) \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'link' => true, 'by' => 'hello' ], true ), [ 'label' => 1, 'grid' => 1, 'preview' => 1, 'attributes' => 1 ] ) === []
	&& \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'link' => 'yes', 'by' => 'Not A Slug' ], true ) === \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x' ], true ) );
$linkAttributes = (array) ( new ReflectionClassConstant( '\Nino\Modules\Components', 'LINK' ) )->getValue();
\Nino\Modules\Components::addComponent( $appData, 'linkprobe', static fn( array &$appData, array $args ): string => '<i>'. $args['value']. '</i>', $linkable + [ 'link' => true, 'by' => 'hello', 'source' => 'text' ] );
check( 'a component that says link has the address and the window of a link after the attributes it declares and before its class - which the registry does not list among them - and says who registered it',
	array_keys( \Nino\Modules\Components::defaults( $appData, 'linkprobe' ) ) === [ 'tag', 'href', 'target', 'class' ] && \Nino\Modules\Components::defaults( $appData, 'linkprobe' ) === [ 'tag' => 'h3', 'href' => '', 'target' => '', 'class' => '' ]
	&& array_keys( \Nino\Modules\Components::components( $appData )['linkprobe']['attributes'] ) === [ 'tag' ] && \Nino\Modules\Components::components( $appData )['linkprobe']['by'] === 'hello'
	&& array_keys( $linkAttributes ) === [ 'href', 'target' ] && $linkAttributes['href']['type'] === 'href' && $linkAttributes['target']['options'] === [ '', '_blank' ] );

// The group a tool offers a component under follows from who registered it: one of the four words from the kernel's own and from a project's, none from a feature's
$ungrouped = array_diff_key( $placed, [ 'group' => 1 ] );
$groupSentence = 'component "probe": "group" must be one of section, hero, card, global';
$featureSentence = 'component "probe": a feature\'s components are offered under the feature, not under a group';
check( 'validate() takes the group a component is offered under, one of the four words, and the clean schema carries it as its last key, after who registered it; a stack has none, whatever it says',
	array_filter( \Nino\Modules\Components::GROUPS, static fn( string $group ): bool => ( \Nino\Modules\Components::validate( 'probe', $ungrouped + [ 'group' => $group ] )['group'] ?? null ) !== $group ) === []
	&& array_keys( \Nino\Modules\Components::validate( 'probe', $placed ) ) === [ 'label', 'source', 'loop', 'preview', 'attributes', 'stretched', 'link', 'by', 'group' ]
	&& array_key_exists( 'group', (array) \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'group' => 'section' ], true ) ) === false
	&& \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'group' => 'footer' ], true ) === \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x' ], true ) );
check( '...and gives the sentence for a component of the kernel or of a project that names none, says nino and none, or names a word that is none of the four: a fifth word, one in capitals, one with a space, an empty word, a number, a list',
	array_filter( [ [], [ 'by' => 'nino' ], [ 'group' => 'footer' ], [ 'group' => 'Section' ], [ 'group' => ' section' ], [ 'group' => '' ], [ 'group' => null ], [ 'group' => 5 ], [ 'group' => true ], [ 'group' => [ 'section' ] ] ],
		static fn( array $wrong ): bool => \Nino\Modules\Components::validate( 'probe', $ungrouped + $wrong ) !== $groupSentence ) === [] );
check( '...a feature\'s components are offered under the feature: one that is registered by another than nino has no group in its clean schema, and a schema that names one - a word of the four or not - is refused with the sentence for it',
	array_key_exists( 'group', (array) \Nino\Modules\Components::validate( 'probe', $ungrouped + [ 'by' => 'hello' ] ) ) === false && is_array( \Nino\Modules\Components::validate( 'probe', $ungrouped + [ 'by' => 'hello' ] ) ) === true
	&& array_filter( [ 'section', 'hero', 'card', 'global', 'footer', '', 5, [ 'section' ] ], static fn( mixed $group ): bool => \Nino\Modules\Components::validate( 'probe', $ungrouped + [ 'by' => 'hello', 'group' => $group ] ) !== $featureSentence ) === [] );
check( '...and the manifest of a feature is read for the feature: handed its key, validate() makes the component the feature\'s own whatever by the schema says and offers it under no group - a schema that names one is refused - while the by the schema says is read all the same, and a stack is read as before',
	( \Nino\Modules\Components::validate( 'probe', $ungrouped, false, 'hello' )['by'] ?? null ) === 'hello' && array_key_exists( 'group', (array) \Nino\Modules\Components::validate( 'probe', $ungrouped, false, 'hello' ) ) === false
	&& ( \Nino\Modules\Components::validate( 'probe', $ungrouped + [ 'by' => 'nino' ], false, 'hello' )['by'] ?? null ) === 'hello'
	&& \Nino\Modules\Components::validate( 'probe', $placed, false, 'hello' ) === $featureSentence
	&& str_contains( (string) \Nino\Modules\Components::validate( 'probe', $ungrouped + [ 'by' => 'Not A Slug' ], false, 'hello' ), '"by" is a slug' ) === true
	&& \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x', 'group' => 'section' ], true, 'hello' ) === \Nino\Modules\Components::validate( 'rows', [ 'label' => 'x' ], true ) );
$groupApp = $appData;
\Nino\Modules\Components::addComponent( $groupApp, 'grouped', $noop, $ungrouped + [ 'group' => 'hero' ] );
\Nino\Modules\Components::addComponent( $groupApp, 'featured', $noop, $ungrouped + [ 'by' => 'hello' ] );
check( '...the registry has the group a component said and none for a feature\'s, as the registry and as the json a tool reads, where the two are told apart by who registered them',
	( \Nino\Modules\Components::components( $groupApp )['grouped']['group'] ?? null ) === 'hero' && array_key_exists( 'group', \Nino\Modules\Components::components( $groupApp )['featured'] ) === false
	&& ( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $groupApp ) ), true ) )['grouped']['group'] === 'hero'
	&& array_key_exists( 'group', (array) ( (array) json_decode( (string) json_encode( \Nino\Modules\Components::components( $groupApp ) ), true ) )['featured'] ) === false
	&& \Nino\Modules\Components::components( $groupApp )['grouped']['by'] === 'nino' && \Nino\Modules\Components::components( $groupApp )['featured']['by'] === 'hello' );

// What the kernel says with them: ten components can be a link, every one is the kernel's, and four attributes say how they are drawn
$kernelLinked = [ 'title', 'subtitle', 'text', 'card-title', 'card-subtitle', 'card-text', 'card-image', 'hero-title', 'hero-subtitle', 'image' ];
check( 'ten components of the kernel can be a link - the titles, the subtitles, the texts and the two pictures - and the card, the button, the html, the spacer and the scroll hint cannot: the button has an address of its own, the card holds buttons, and no link stands in another',
	array_keys( array_filter( \Nino\Modules\Components::components( $appData ), static fn( array $schema, string $name ): bool => $schema['link'] === true && in_array( $name, $kernelAll, true ) === true, ARRAY_FILTER_USE_BOTH ) ) === $kernelLinked );
check( '...every component of the kernel is its own - by nino - and a stack has neither key',
	array_filter( array_intersect_key( \Nino\Modules\Components::components( $appData ), array_flip( $kernelAll ) ), static fn( array $schema ): bool => $schema['by'] !== 'nino' ) === []
	&& array_filter( \Nino\Modules\Components::stacks( $appData ), static fn( array $stack ): bool => array_key_exists( 'by', $stack ) === true || array_key_exists( 'link', $stack ) === true ) === [] );
$kernelInputs = [];
foreach( array_intersect_key( \Nino\Modules\Components::components( $appData ), array_flip( $kernelAll ) ) as $inputName => $inputSchema )
	foreach( $inputSchema['attributes'] as $inputAttribute => $inputDeclared )
		if( isset( $inputDeclared['input'] ) === true )
			$kernelInputs[$inputName. '.'. $inputAttribute] = $inputDeclared['input'];
check( 'the kernel says how a tool draws an attribute where it matters: the alignment of a card as icons, the format of a text and the size and the width of a button as a row of buttons - and says nothing else',
	$kernelInputs === [ 'text.format' => 'segment', 'card-text.format' => 'segment', 'card.vertical' => 'icons', 'card.align' => 'icons', 'button.size' => 'segment', 'button.width' => 'segment' ] );
$kernelTabs = [];
foreach( array_intersect_key( \Nino\Modules\Components::components( $appData ), array_flip( $kernelAll ) ) as $kernelTabName => $kernelTabSchema )
	foreach( $kernelTabSchema['attributes'] as $kernelTabAttribute => $kernelTabDeclared )
		if( isset( $kernelTabDeclared['tab'] ) === true )
			$kernelTabs[$kernelTabName. '.'. $kernelTabAttribute] = $kernelTabDeclared['tab'];
check( '...and on which tab where it matters: the colour, the picture, the focus and the dimming of a card on the tab of the background - and says nothing else',
	$kernelTabs === [ 'card.ground' => 'background', 'card.background' => 'background', 'card.focus' => 'background', 'card.dim' => 'background' ]
	&& array_diff( $kernelTabs, \Nino\Modules\Components::TABS ) === [] );
$eightTexts = [ 'title', 'subtitle', 'text', 'card-title', 'card-subtitle', 'card-text', 'hero-title', 'hero-subtitle' ];
check( 'the style of the eight text components is a variant - none, loud or quiet - of the class each is named for, and their colour another, the last attribute after the style: none, muted or accent, written as nino-ink--<word>, the ink of the ground the text stands on',
	array_filter( $eightTexts, static fn( string $name ): bool => ( $registryOf( $name )['attributes']['style'] ?? null ) !== [ 'type' => 'variant', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ], 'hint' => '', 'options' => [ '', 'loud', 'quiet' ], 'default' => '' ]
		|| ( $registryOf( $name )['attributes']['color'] ?? null ) !== [ 'type' => 'variant', 'label' => [ 'en_US' => 'Text colour', 'de_DE' => 'Text-Farbe' ], 'hint' => '', 'options' => [ '', 'muted', 'accent' ], 'prefix' => 'nino-ink--', 'default' => '' ]
		|| array_slice( array_keys( $registryOf( $name )['attributes'] ), -2 ) !== [ 'style', 'color' ] ) === []
	&& array_keys( array_filter( array_intersect_key( \Nino\Modules\Components::components( $appData ), array_flip( $kernelAll ) ), static fn( array $schema ): bool => isset( $schema['attributes']['color'] ) === true ) ) === $eightTexts );
check( '...the width of a button comes after its size, none or full, and the button has its address and its target from the one place the link of a component is declared: word for word what a link gives a component',
	array_keys( $registryOf( 'button' )['attributes'] ) === [ 'href', 'style', 'size', 'width', 'target' ]
	&& $registryOf( 'button' )['attributes']['width'] === [ 'type' => 'select', 'label' => [ 'en_US' => 'Width', 'de_DE' => 'Breite' ], 'hint' => '', 'input' => 'segment', 'options' => [ '', 'full' ], 'default' => '' ]
	&& array_diff_key( $registryOf( 'button' )['attributes']['href'], [ 'label' => 1, 'hint' => 1 ] ) === [ 'type' => 'href', 'default' => '' ] && $registryOf( 'button' )['attributes']['href']['label'] === $linkAttributes['href']['label'] && $registryOf( 'button' )['attributes']['href']['hint'] === $linkAttributes['href']['hint']
	&& $registryOf( 'button' )['attributes']['target']['label'] === $linkAttributes['target']['label'] && $registryOf( 'button' )['attributes']['target']['options'] === $linkAttributes['target']['options'] );
check( 'the scroll hint is the arrow of a hero: labelled in both languages, no source, offered in a hero and a section, drawing the <a> of nino-hero-arrowdown, with the id of the section to scroll to as its one attribute - and the registry says it is the kernel\'s and no link',
	$registryOf( 'scroll-hint' )['label'] === [ 'en_US' => 'Scroll hint', 'de_DE' => 'Scroll-Hinweis' ] && $registryOf( 'scroll-hint' )['source'] === 'none' && $registryOf( 'scroll-hint' )['preview'] === 'block'
	&& $registryOf( 'scroll-hint' )['in'] === [ 'hero', 'section' ] && $registryOf( 'scroll-hint' )['draws'] === 'a .nino-hero-arrowdown' && array_key_exists( 'holds', $registryOf( 'scroll-hint' ) ) === false
	&& array_keys( $registryOf( 'scroll-hint' )['attributes'] ) === [ 'to' ] && $registryOf( 'scroll-hint' )['attributes']['to']['type'] === 'string' && $registryOf( 'scroll-hint' )['attributes']['to']['default'] === ''
	&& $registryOf( 'scroll-hint' )['link'] === false && \Nino\Modules\Components::defaults( $appData, 'scroll-hint' ) === [ 'to' => '', 'class' => '' ] );
check( 'the sections are a stack with a loop and no grid: a section to an element, so that it has the attributes of every loop and none of a grid - cols, gap and autoheight are left to the stacks that draw cells',
	\Nino\Modules\Components::stacks( $appData )['sections']['grid'] === false && \Nino\Modules\Components::stacks( $appData )['sections']['label'] === [ 'en_US' => 'Sections', 'de_DE' => 'Sektionen' ]
	&& \Nino\Modules\Components::defaults( $appData, 'sections', true ) === [ 'sort' => '', 'offset' => '0', 'limit' => '0', 'query' => '', 'callback' => '', 'locale' => '', 'id' => '', 'alternate' => '0', 'class' => '' ] );
$sectionsAlternate = \Nino\Modules\Components::stacks( $appData )['sections']['attributes']['alternate'] ?? [];
check( '...and the one attribute it has of its own is alternate, a bool that is off - the switch that mirrors every second section - with a label and a hint in both languages for a tool to draw it by',
	array_keys( \Nino\Modules\Components::stacks( $appData )['sections']['attributes'] ) === [ 'alternate' ] && ( $sectionsAlternate['type'] ?? '' ) === 'bool' && ( $sectionsAlternate['default'] ?? null ) === false
	&& array_filter( [ 'label', 'hint' ], static fn( string $words ): bool => array_keys( (array) ( $sectionsAlternate[$words] ?? [] ) ) !== [ 'en_US', 'de_DE' ] || in_array( '', $sectionsAlternate[$words], true ) === true ) === [] );

check( 'a clean schema validates again as it is - what a manifest hands over after Features::manifest() has read it', \Nino\Modules\Components::validate( 'title', \Nino\Modules\Components::components( $appData )['title'] ) === \Nino\Modules\Components::components( $appData )['title']
	&& \Nino\Modules\Components::validate( 'filter', \Nino\Modules\Components::stacks( $appData )['filter'], true ) === \Nino\Modules\Components::stacks( $appData )['filter'] );
check( '...icon and all: each of the sixteen, and a component registered with an icon of its own, validates again as it is, the parts and the maps of the icon in the order they were in',
	array_filter( array_merge( $kernelAll, [ 'pictured' ] ), static fn( string $name ): bool => \Nino\Modules\Components::validate( $name, \Nino\Modules\Components::components( $appData )[$name] ) !== \Nino\Modules\Components::components( $appData )[$name] ) === [] );
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

// The address of a page - a key under /_nino/webpage/, which the Routes step writes within the project - has the directory the project is served from in front of it, as the navigation writes it
\Nino\Html::addFills( $appData, [
	'/_nino/webpage/home/uri'				=> '/',
	'/_nino/webpage/elsewhere/uri'			=> 'https://example.org/x',
	'/template/page-home/dir/link'			=> '/x',
], '*' );
$appData['/nino/dir'] = '/shop';
check( 'value(): the address of a page, a key under /_nino/webpage/, has the directory the project is served from in front of it - /shop/contact for a page, /shop/ for the home page - and so has the link of a [button] that names it',
	\Nino\Modules\Components::value( $appData, '/_nino/webpage/contact/uri', 'href' ) === '/shop/contact' && \Nino\Modules\Components::value( $appData, '/_nino/webpage/home/uri', 'href' ) === '/shop/'
	&& $render( '[button /_nino/webpage/contact/name href="/_nino/webpage/contact/uri" style="primary"]' ) === '<a class="nino-btn nino-btn--primary" href="/shop/contact">Contact us</a>' );
check( '...and nothing else has it: a fixed address, an anchor, a key outside /_nino/webpage/ whose fill is an address, a key under it whose fill is a full address and not one within the project, and the key of a page that has no fill, which stays the key',
	\Nino\Modules\Components::value( $appData, '', 'href', 'plain', '/contact' ) === '/contact' && $render( '[button text="Go" href="/contact"]' ) === '<a class="nino-btn" href="/contact">Go</a>'
	&& \Nino\Modules\Components::value( $appData, '#top', 'href' ) === '#top' && \Nino\Modules\Components::value( $appData, '/template/page-home/dir/link', 'href' ) === '/x'
	&& \Nino\Modules\Components::value( $appData, '/_nino/webpage/elsewhere/uri', 'href' ) === 'https://example.org/x'
	&& \Nino\Modules\Components::value( $appData, '/_nino/webpage/nowhere/uri', 'href' ) === '/_nino/webpage/nowhere/uri' );
$appData['/nino/dir'] = '';
check( '...and a project at the domain root has the address as the Routes step wrote it', \Nino\Modules\Components::value( $appData, '/_nino/webpage/contact/uri', 'href' ) === '/contact'
	&& \Nino\Modules\Components::value( $appData, '/_nino/webpage/home/uri', 'href' ) === '/' );
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
check( 'value(): a fixed plain text is read as a stored one is - an entity is the character it stands for, escaped once, and a bracket that was an entity is written inert after it is decoded, never before',
	\Nino\Modules\Components::value( $appData, '', 'text', 'plain', 'Let&#039;s &amp; "go" &#91;now&#93;' ) === 'Let&#039;s &amp; &quot;go&quot; &#91;now]'
	&& \Nino\Modules\Components::value( $appData, '', 'text', 'plain', 'Fish & Chips' ) === 'Fish &amp; Chips' && \Nino\Modules\Components::value( $appData, '', 'text', 'plain', '&amp;lt;' ) === '&amp;lt;'
	&& \Nino\Modules\Components::value( $appData, '', 'href', 'plain', '/a?b=&quot;c&quot;&amp;d=1' ) === '/a?b=&quot;c&quot;&amp;d=1'
	&& \Nino\Modules\Components::value( $appData, '', 'href', 'plain', 'javascript&#58;alert(1)' ) === '#' );
check( '...and the other formats are sanitized as they stand: an entity in a fixed text of the format inline is text, as it is in a key of that format, and no tag that was typed is left',
	\Nino\Modules\Components::value( $appData, '', 'text', 'inline', 'x &lt;b&gt;y&lt;/b&gt;' ) === 'x &lt;b&gt;y&lt;/b&gt;' && \Nino\Modules\Components::value( $appData, '', 'text', 'inline', 'x <b>y</b>' ) === 'x y' );

// Two components written the way a project's own are: one that says what it
// sees, one that reads value() inside a stack
\Nino\Modules\Components::addComponent( $appData, 'probe', static function( array &$appData, array $args ): string {
	$element = \Nino\Modules\Components::element( $appData );
	return $element === null ? 'outside' : 'inside:'. $element['uri']. '#'. $element['id']. '#'. $element['element']['.uri']. '#'. ( $element['model']['title']['type'] ?? '-' );
}, [ 'label' => 'Probe', 'source' => 'none', 'group' => 'global' ] );
\Nino\Modules\Components::addComponent( $appData, 'values', static function( array &$appData, array $args ): string {
	$value = \Nino\Modules\Components::value( $appData, $args['of'], $args['kind'] );
	return '<i>'. ( $value === null ? 'NULL' : $value ). '</i>';
}, [ 'label' => 'Values', 'source' => 'none', 'group' => 'global', 'attributes' => [
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

check( 'value(): .slug is the last segment of the element\'s uri - the name alone - as a text and as an address, and nothing without a stack',
	$render( '[stack /services sort="title" offset="1" limit="1" cols="100"][values of=".slug"][values of=".slug" kind="href"][values of=".uri"][/stack]' )
	=== '<div class="nino-grid-row nino-stack nino-stack-gap-2"><div class="nino-grid-s-100 nino-grid-m-100 nino-grid-l-100"><i>web</i><i>web</i><i>/services/web</i></div></div>'
	&& \Nino\Modules\Components::value( $appData, '.slug', 'text' ) === null && \Nino\Modules\Components::value( $appData, '.slug', 'href' ) === null );
$appData[\Nino\Modules\Components::CONTEXT] = [ 'uri' => '/services', 'id' => 0, 'element' => [ '.uri' => '/services/a"b[c' ], 'model' => [] ];
$slugTextHref = [ \Nino\Modules\Components::value( $appData, '.slug', 'text' ), \Nino\Modules\Components::value( $appData, '.slug', 'href' ) ];
$appData[\Nino\Modules\Components::CONTEXT]['element'] = [ '.uri' => '/services/javascript:go' ];
$slugScheme = [ \Nino\Modules\Components::value( $appData, '.slug', 'text' ), \Nino\Modules\Components::value( $appData, '.slug', 'href' ) ];
$appData[\Nino\Modules\Components::CONTEXT]['element'] = [];
$slugNone = [ \Nino\Modules\Components::value( $appData, '.slug', 'text' ), \Nino\Modules\Components::value( $appData, '.slug', 'href' ) ];
unset( $appData[\Nino\Modules\Components::CONTEXT] );
check( '...escaped as the uri is - the text with its quote and its [ inert, the address a "#" where it names a scheme that is not allowed - and, for an element that has no uri, the empty text and the empty address',
	$slugTextHref === [ 'a&quot;b&#91;c', 'a&quot;b&#91;c' ] && $slugScheme === [ 'javascript:go', '#' ] && $slugNone === [ '', '' ] && \Nino\Modules\Components::element( $appData ) === null );

// The fills of the element in the cell of a stack: [[.id]], [[.uri]], [[.slug]] and the fields, as [elements] fills them
\Nino\Elements::insertElementType( $appData, '/fillbox', [
	'title'	=> [ 'type' => 'string', 'locale' => true ],
	'slug'	=> [ 'type' => 'string' ],
	'link'	=> [ 'type' => 'string' ],
	'note'	=> [ 'type' => 'string', 'locale' => true ],
] );
\Nino\Elements::insertElement( $appData, '/fillbox/one', [ 'title' => 'a&b "c" [x]', 'slug' => 'first', 'link' => '/p?a=1&b="c"' ], 'de_DE' );
\Nino\Elements::insertElement( $appData, '/fillbox/two', [ 'title' => 'Two', 'slug' => 'second', 'link' => '/two' ], 'de_DE' );
$fillCell = '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33">';
$fillRow = '<div class="nino-grid-row nino-stack nino-stack-gap-2">';
$fillOf = static fn( string $content ): string => $render( '[stack /fillbox sort="uri"]'. $content. '[/stack]' );
$fillCells = static fn( string ...$cells ): string => $fillRow. $fillCell. implode( '</div>'. $fillCell, $cells ). '</div></div>';

check( 'a stack\'s cell is filled with the element: [[.id]], [[.uri]] - the uri of the element, /<type>/<name> - [[.lang]] and [[title]] in running text are the element\'s - the field escaped once, with its [ and ] inert - and a field of the model without a value is nothing',
	$fillOf( '<i>[[.id]]|[[.uri]]|[[.lang]]|[[title]]|[[note]]|[[link]]</i>' ) === $fillCells( '<i>0|/fillbox/one||a&amp;b &quot;c&quot; &#91;x&#93;||/p?a=1&amp;b=&quot;c&quot;</i>', '<i>1|/fillbox/two||Two||/two</i>' ) );
check( '[[.slug]] in a cell is the name of the element, the last segment of its uri, whatever a field of the type called slug holds: in running text and in the address of a call',
	$fillOf( '<i>[[.slug]]|[[slug]]|[[.uri]]</i>' ) === $fillCells( '<i>one|first|/fillbox/one</i>', '<i>two|second|/fillbox/two</i>' )
	&& $fillOf( '[button text="More" href="/blog/[[.slug]]"]' ) === $fillCells( '<a class="nino-btn" href="/blog/one">More</a>', '<a class="nino-btn" href="/blog/two">More</a>' )
	&& $fillOf( '[button text="[[.slug]]" href="[[.slug]]"]' ) === $fillCells( '<a class="nino-btn" href="one">one</a>', '<a class="nino-btn" href="two">two</a>' ) );
check( '...a call whose source is .slug writes the name too',
	$fillOf( '[button .slug text="More"]' ) === $fillCells( '<a class="nino-btn" href="one">More</a>', '<a class="nino-btn" href="two">More</a>' ) );
check( '...inside the value of a call as in text: the call is whole and its address the element\'s',
	$fillOf( '[button text="More" href="/blog/[[slug]]"]' ) === $fillCells( '<a class="nino-btn" href="/blog/first">More</a>', '<a class="nino-btn" href="/blog/second">More</a>' )
	&& $fillOf( '[button text="[[.id]]: [[title]]" href="[[.uri]]"]' ) === $fillCells( '<a class="nino-btn" href="/fillbox/one">0: a&amp;b &quot;c&quot; &#91;x]</a>', '<a class="nino-btn" href="/fillbox/two">1: Two</a>' ) );
check( '...a token of a field no language has a value for is nothing in a call as well: not the token, and not a "[]" the shortcode pass makes of it',
	$fillOf( '[button text="Go" href="/n/[[note]]"]|[[note]]|' ) === $fillCells( '<a class="nino-btn" href="/n/">Go</a>||', '<a class="nino-btn" href="/n/">Go</a>||' ) );
check( '...a [ in a field is written &#91; and a ] &#93; whether it stands in text or in a call, so that a value an editor wrote opens no fill and no shortcode and ends no call',
	str_contains( $fillOf( '[[title]]' ), 'a&amp;b &quot;c&quot; &#91;x&#93;' ) === true && str_contains( $fillOf( '[[title]]' ), '[' ) === false
	&& str_contains( $fillOf( '[title title href="/t/[[title]]"]' ), '<a class="nino-stretch" href="/t/a&amp;b &quot;c&quot; &#91;x]">' ) === true
	&& substr_count( $fillOf( '[title title href="/t/[[title]]"]' ), '</a>' ) === 2 && str_contains( $fillOf( '[title title href="/t/[[title]]"]' ), '"]' ) === false );
check( '...the page\'s fills come first: a key of the page between double brackets is the page\'s in the cell, a token that is the element\'s is the element\'s, and what neither knows stays as it is',
	$fillOf( '[[/_nino/webpage/contact/uri]]|[[.uri]]|[[nothing]]' ) === $fillCells( '/contact|/fillbox/one|[[nothing]]', '/contact|/fillbox/two|[[nothing]]' ) );

// The & of a field is escaped once in an address: the fill wrote it &amp;, the address is read as a text is and escaped by _href() the one time
check( 'an address carries every character of a field once: the & of a query is &amp; and a " is &quot;, whether it comes from a fill, from a field of the element or from the author',
	$fillOf( '[button text="Go" href="/x/[[link]]"]' ) === $fillCells( '<a class="nino-btn" href="/x//p?a=1&amp;b=&quot;c&quot;">Go</a>', '<a class="nino-btn" href="/x//two">Go</a>' )
	&& str_contains( $fillOf( '[button text="Go" href="/x/[[title]]"]' ), 'href="/x/a&amp;b &quot;c&quot; &#91;x]"' ) === true
	&& str_contains( $fillOf( '[button text="Go" href="link"]' ), 'href="/p?a=1&amp;b=&quot;c&quot;"' ) === true
	&& str_contains( $fillOf( '[button text="Go" href="/x?a=1&amp;b=2&c=3"]' ), 'href="/x?a=1&amp;b=2&amp;c=3"' ) === true );
check( 'value(): an address is read as a text is - an entity in it is the character it stands for, escaped once - wherever it comes from: a key that has no fill, a fixed text, an address that is no key',
	\Nino\Modules\Components::value( $appData, '/x/a&amp;b&quot;c&quot;', 'href' ) === '/x/a&amp;b&quot;c&quot;'
	&& \Nino\Modules\Components::value( $appData, '/x/a&b "c"', 'href' ) === '/x/a&amp;b &quot;c&quot;'
	&& \Nino\Modules\Components::value( $appData, '#a&amp;b', 'href' ) === '#a&amp;b' && \Nino\Modules\Components::value( $appData, '#a&b', 'href' ) === '#a&amp;b'
	&& \Nino\Modules\Components::value( $appData, '', 'href', 'plain', 'a&amp;b &quot;c&quot;' ) === 'a&amp;b &quot;c&quot;'
	&& \Nino\Modules\Components::value( $appData, '#x&copy=1', 'href' ) === '#x&amp;copy=1'
	&& \Nino\Modules\Components::value( $appData, 'javascript&#58;alert(1)', 'href' ) === '#' && \Nino\Modules\Components::value( $appData, '/x/&#91;y&#93;', 'href' ) === '/x/&#91;y]' );

// Stacks in stacks: the cell is filled by plain replacement over what is written in it, the outer element first
\Nino\Elements::insertElementType( $appData, '/fillnest', [ 'title' => [ 'type' => 'string', 'locale' => true ], 'tag' => [ 'type' => 'string' ] ] );
\Nino\Elements::insertElement( $appData, '/fillnest/in', [ 'title' => 'Inner', 'tag' => 'T' ], 'de_DE' );
\Nino\Filesystem::putFileContent( $appData, '/templates/fill-inner.tpl', '[stack /fillnest]in:[[.id]]|[[title]]|[[.uri]][/stack]' );
$fillTemplate = $render( '[stack /fillbox sort="uri" limit="1"]out:[[title]][template /templates/fill-inner][/stack]' );
check( 'a stack in a template that a stack includes has the fills of its own element - the template is not part of what the outer cell is filled over - and the outer keeps its own',
	$fillTemplate === $fillRow. $fillCell. 'out:a&amp;b &quot;c&quot; &#91;x&#93;'. $fillRow. $fillCell. 'in:0|Inner|/fillnest/in</div></div></div></div>' );
$fillList = $render( '[stack /fillbox sort="uri" limit="1"]out:[[title]][list /fillnest]in:[[title]]|[[tag]]|[[.uri]]|[[.id]][/list][/stack]' );
check( 'a call of another stack written in the cell keeps its own element: what stands between its tags is not filled with the outer one - not a field the two types share, not .uri or .id - and is rendered with the inner',
	str_contains( $fillList, 'out:a&amp;b &quot;c&quot; &#91;x&#93;' ) === true && str_contains( $fillList, 'in:Inner|T|/fillnest/in|0' ) === true && substr_count( $fillList, 'a&amp;b' ) === 1 );

// A loop written in the cell is the loop's: [elements] draws each of its elements with its own values, and its opening tag is the cell's to fill
\Nino\Elements::insertElementType( $appData, '/fillpost', [ 'title' => [ 'type' => 'string', 'locale' => true ], 'cat' => [ 'type' => 'string' ] ] );
\Nino\Elements::insertElement( $appData, '/fillpost/p1', [ 'title' => 'Post 1', 'cat' => 'first' ], 'de_DE' );
\Nino\Elements::insertElement( $appData, '/fillpost/p2', [ 'title' => 'Post 2', 'cat' => 'second' ], 'de_DE' );
\Nino\Elements::insertElement( $appData, '/fillpost/p3', [ 'title' => 'Post 3', 'cat' => 'first' ], 'de_DE' );
$fillLoop = '<a href="[[.uri]]">[[.id]] [[title]]</a>';
check( '[elements] in the cell of a stack draws each of its elements with its own .uri, .id and fields, not the outer element\'s',
	$fillOf( '[elements /fillpost sort="uri"]'. $fillLoop. '[/elements]' ) === $fillCells(
		'<a href="/fillpost/p1">0 Post 1</a><a href="/fillpost/p2">1 Post 2</a><a href="/fillpost/p3">2 Post 3</a>', '<a href="/fillpost/p1">0 Post 1</a><a href="/fillpost/p2">1 Post 2</a><a href="/fillpost/p3">2 Post 3</a>' ) );
check( '...[[.slug]] between the tags of an [elements] written in the cell is the name of the loop\'s element, not the cell\'s',
	$fillOf( '[elements /fillpost sort="uri" limit="2"]<u>[[.slug]]</u>[/elements]|[[.slug]]' ) === $fillCells( '<u>p1</u><u>p2</u>|one', '<u>p1</u><u>p2</u>|two' ) );
check( '...its opening tag is the cell\'s: a query by a field of the outer element takes it, one element\'s posts to the next, while the block between the tags stays the loop\'s',
	$fillOf( '[elements /fillpost sort="uri" query="cat=[[slug]]"]<b>[[title]]</b>[/elements]' ) === $fillCells( '<b>Post 1</b><b>Post 3</b>', '<b>Post 2</b>' ) );
check( '...a lone [ in a quoted value of its opening tag does not make it the cell\'s: the call ends at the first ], as the shortcode pass reads it, and the loop keeps its own element',
	$fillOf( '[elements /fillpost sort="uri" limit="1" class="a[b"]<u>[[.uri]]|[[title]]</u>[/elements]' ) === $fillCells( '<u>/fillpost/p1|Post 1</u>', '<u>/fillpost/p1|Post 1</u>' ) );
check( '...and what stands between the tags of a component is still the cell\'s, a loop in it keeping its own',
	str_contains( $fillOf( '[card]<i>[[.uri]]</i>[elements /fillpost sort="uri" limit="1"]<u>[[.uri]]</u>[/elements]<i>[[.id]]</i>[/card]' ), '<i>/fillbox/one</i><u>/fillpost/p1</u><i>0</i>' ) === true
	&& str_contains( $fillOf( '[card]<i>[[.uri]]</i>[elements /fillpost sort="uri" limit="1"]<u>[[.uri]]</u>[/elements]<i>[[.id]]</i>[/card]' ), '<i>/fillbox/two</i><u>/fillpost/p1</u><i>1</i>' ) === true );

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
check( 'the title of a section may be an h2 as well as an h3 - the h3 it is where the call names no tag - which is what a page needs under the h1 of its header',
	$render( '[title /template/page-home/hero/title tag="h2"]' ) === '<h2 class="nino-section-title">Hi &#91;there]</h2>' && $render( '[title /template/page-home/hero/title]' ) === '<h3 class="nino-section-title">Hi &#91;there]</h3>'
	&& in_array( 'h2', \Nino\Modules\Components::components( $appData )['title']['attributes']['tag']['options'], true ) === true );
check( '[text] keeps the paragraphs and lists of its key and marks the wrapper as rich text', $render( '[text /template/page-home/services/text]' ) === '<div class="nino-section-text nino-richtext"><p>One</p><ul><li>a</li></ul></div>' );
check( '[text format="inline"] sanitizes to that format', $render( '[text /template/page-home/services/text format="inline" style="quiet"]' ) === '<p class="nino-section-text nino-section-text--quiet">One a</p>' );
check( '[text] keeps the classes it always had, in the order it always had them: the class, the mark as rich text, the style, its own',
	$render( '[text /template/page-home/services/text style="loud" class="x"]' ) === '<div class="nino-section-text nino-richtext nino-section-text--loud x"><p>One</p><ul><li>a</li></ul></div>'
	&& $render( '[text /template/page-home/services/text format="lines" class="x y"]' ) === '<p class="nino-section-text x y">One<br>a</p>' );
// The tag of [text]: a p unless the call says a div or a span - and a div for the paragraphs and lists of the format blocks, whatever it says, which a p or a span cannot hold
$textTag = $registryOf( 'text' )['attributes']['tag'] ?? [];
check( '[text] has a tag, a choice of p, div and span, the p where the call names none: the labelled select of the registry, with a hint that says what the format blocks does with it - and the text of a card has none',
	( $textTag['type'] ?? null ) === 'select' && ( $textTag['options'] ?? null ) === [ 'p', 'div', 'span' ] && ( $textTag['default'] ?? null ) === 'p'
	&& ( $textTag['label'] ?? null ) === [ 'en_US' => 'HTML tag', 'de_DE' => 'HTML-Tag' ] && array_keys( (array) ( $textTag['hint'] ?? null ) ) === [ 'en_US', 'de_DE' ]
	&& \Nino\Modules\Components::defaults( $appData, 'text' ) === [ 'format' => 'blocks', 'tag' => 'p', 'style' => '', 'color' => '', 'href' => '', 'target' => '', 'class' => '' ]
	&& array_key_exists( 'tag', $registryOf( 'card-text' )['attributes'] ) === false );
check( '...the format inline and the format lines are written in the element that was asked for - the p by default - with the classes they always had, the colour and the class of its own behind them',
	$render( '[text /template/page-home/services/text format="inline"]' ) === '<p class="nino-section-text">One a</p>'
	&& $render( '[text /template/page-home/services/text format="inline" tag="div"]' ) === '<div class="nino-section-text">One a</div>'
	&& $render( '[text /template/page-home/services/text format="lines" tag="span" style="quiet" color="muted" class="x"]' ) === '<span class="nino-section-text nino-section-text--quiet nino-ink--muted x">One<br>a</span>'
	&& $render( '[text text="Fixed [x" format="inline" tag="p"]' ) === '<p class="nino-section-text">Fixed &#91;x</p>' );
check( '...the paragraphs and lists of the format blocks, which is the default, are held by a div whatever the tag says: a p or a span cannot hold a p, and a browser closes it ahead of the content - and a tag that is none of the three is the p',
	$render( '[text /template/page-home/services/text tag="p"]' ) === '<div class="nino-section-text nino-richtext"><p>One</p><ul><li>a</li></ul></div>'
	&& $render( '[text /template/page-home/services/text tag="span" format="blocks"]' ) === '<div class="nino-section-text nino-richtext"><p>One</p><ul><li>a</li></ul></div>'
	&& $render( '[text /template/page-home/services/text format="inline" tag="h1"]' ) === '<p class="nino-section-text">One a</p>'
	&& $render( '[text /template/page-home/services/text format="inline" tag="DIV"]' ) === '<p class="nino-section-text">One a</p>'
	&& $render( '[text /template/page-home/services/text format="inline" tag="p onclick=x"]' ) === '<p class="nino-section-text">One a</p>' );
check( '[card-text] is the text of a card: [text] in the class nino-card-descr - the paragraphs and lists of a key in a wrapper marked as rich text, the format, the style behind the class it varies and a class of its own last',
	$render( '[card-text /template/page-home/services/text]' ) === '<div class="nino-card-descr nino-richtext"><p>One</p><ul><li>a</li></ul></div>'
	&& $render( '[card-text /template/page-home/services/text format="inline" style="quiet"]' ) === '<div class="nino-card-descr nino-card-descr--quiet">One a</div>'
	&& $render( '[card-text /template/page-home/services/text style="loud" class="x"]' ) === '<div class="nino-card-descr nino-richtext nino-card-descr--loud x"><p>One</p><ul><li>a</li></ul></div>'
	&& $render( '[card-text text="Fixed [x"]' ) === '<div class="nino-card-descr nino-richtext"><p>Fixed &#91;x</p></div>' );
check( '...a source that resolves to nothing renders nothing, a style outside its options is the default one, and in a card it is the text of the card',
	$render( '[card-text /template/page-home/nope/title]' ) === '' && $render( '[card-text /template/page-home/empty/title]' ) === '' && $render( '[card-text]' ) === ''
	&& $render( '[card-text /template/page-home/services/text style="weird" format="nonsense"]' ) === $render( '[card-text /template/page-home/services/text]' )
	&& $render( '[card][card-title /template/page-home/hero/title][card-text /template/page-home/services/text format="inline"][/card]' )
		=== '<article class="nino-card"><div class="nino-card-content"><h4 class="nino-card-title">Hi &#91;there]</h4><div class="nino-card-descr">One a</div></div></article>' );
check( '...in a stack it reads the field of its element, as [text] does, and the class of the text of a card is the one Nino.css styles',
	str_contains( $render( '[stack /services sort="title" limit="1"][card][card-text summary][/card][/stack]' ), '<article class="nino-card"><div class="nino-card-content"><div class="nino-card-descr nino-richtext"><p>Two</p></div></div></article>' ) === true
	&& $hookRules( '.nino-card-descr' ) !== '' && $hookRules( '.nino-card-descr--quiet' ) !== '' && $hookRules( '.nino-card-descr--loud' ) !== '' );

/*	A link in a text is the brand as ink on the ground the text stands on - --color-accent, which a section, a row, a column and a card hand on to what is in them - and is underlined where the pointer is
	over it or the keyboard is on it. The stylesheet names the classes [text] and [card-text] write, the first class of their element whatever the format, the size and the colour of the text, and leaves out
	a link that is a button. The kernel's links stay as the kernel set them, the colour of what they stand in and no underline: it is this rule that says otherwise, for these two classes and for none
	of the titles	*/
$linkTexts = array_map( static fn( string $call ): string => preg_match( '/^<[a-z]+ class="([a-z-]+)[" ]/', $render( $call ), $linkFound ) === 1 ? $linkFound[1] : '', [ '[text text="x"]', '[card-text text="x"]' ] );
$linkRule = ':where(.'. implode( ', .', $linkTexts ). ') a:where(:not(.nino-btn))';
$linkTitles = array_filter( array_keys( $groundEach ), static fn( string $selector ): bool => preg_match( '/\.nino-(?:section|card|hero)-(?:title|subtitle)(?![\w-]).*\sa(?![\w-])/', $selector ) === 1 );
check( 'a link in a text takes the accent ink of its ground: one rule for the classes [text] and [card-text] write, which leaves out a link that is a button and says color: var(--color-accent) and nothing else'
	. ( count( array_filter( $linkTexts ) ) === 2 ? '' : ' - the two texts write no class' ),
	count( array_filter( $linkTexts ) ) === 2 && $linkTexts[0] !== $linkTexts[1] && count( $groundEach[$linkRule] ?? [] ) === 1
	&& $groundSets( $groundOf( $linkRule ), 'color', 'var(--color-accent)' ) === true && substr_count( $groundOf( $linkRule ), ':' ) === 1 );
check( '...and it is underlined where the pointer is over it or the keyboard is on it, :hover and :focus-visible in one rule that says nothing else, and not at rest - while a stays the colour of what it stands in and has no underline, and no selector of a title has a link in it',
	$groundSets( $groundOf( $linkRule. ':is(:hover, :focus-visible)' ), 'text-decoration', 'underline' ) === true && substr_count( $groundOf( $linkRule. ':is(:hover, :focus-visible)' ), ':' ) === 1
	&& preg_match( '/(?<![\w-])text-decoration/', $groundOf( $linkRule ) ) !== 1
	&& $groundSets( $groundOf( 'a' ), 'color', 'inherit' ) === true && $groundSets( $groundOf( 'a' ), 'text-decoration', 'none' ) === true && $linkTitles === [] );
check( '[spacer] is an empty box of a class of its own, the step of the size it says - 1 to 6, 2 where it says none',
	array_filter( [ 1, 2, 3, 4, 5, 6 ], fn( int $step ): bool => $render( '[spacer size="'. $step. '"]' ) !== '<div class="nino-spacer nino-spacer-'. $step. '"></div>' ) === []
	&& $render( '[spacer]' ) === '<div class="nino-spacer nino-spacer-2"></div>' );
check( '...and the markup it is made from has no margin class: the margin a column writes on it is room beside its height and does not compete with its size',
	str_contains( \Nino\Modules\Components::$html['spacer'], 'nino-mt-' ) === false && str_contains( $render( '[spacer size="3" class="nino-mt-2"]' ), 'nino-spacer-3 nino-mt-2"' ) === true );
check( '[html] is its content as rich text, and nothing without content', $render( '[html]<p>Hi</p><script>alert(1)</script>[/html]' ) === '<p>Hi</p>' && $render( '[html][/html]' ) === '' );
check( '[button] is a link drawn as one: label from a key, address from a key, a style', $render( '[button /_nino/webpage/contact/name href="/_nino/webpage/contact/uri" style="primary"]' ) === '<a class="nino-btn nino-btn--primary" href="/contact">Contact us</a>' );
check( '[button] takes a fixed label, a size, a target - and a target that is another window says it is no concern of the opener', $render( '[button text="Go" href="https://example.com" size="big" target="_blank"]' ) === '<a class="nino-btn nino-btn--big" href="https://example.com" target="_blank" rel="noopener">Go</a>' );
check( '[button] with a fixed label reads its first argument as the address', $render( '[button /_nino/webpage/contact/uri text="Mehr"]' ) === '<a class="nino-btn" href="/contact">Mehr</a>' );
check( '[button] with no address is no link: the same classes on a <span>, since an <a> without an href is no stop of the Tab key and has nothing to press - a target means nothing without one, and a button with an address is the link it was',
	$render( '[button text="Go"]' ) === '<span class="nino-btn">Go</span>'
	&& $render( '[button text="Go" style="primary" size="small" class="x" target="_blank"]' ) === '<span class="nino-btn nino-btn--primary nino-btn--small x">Go</span>'
	&& $render( '[button /template/page-home/stored/title]' ) === '<span class="nino-btn">Let&#039;s talk about &quot;us&quot; &#91;today]</span>'
	&& $render( '[button text="Go" href="#x"]' ) === '<a class="nino-btn" href="#x">Go</a>' && str_contains( $render( '[button text="Go"]' ), '<a' ) === false );
check( '[button] with a scheme that is no safe one is a link to nowhere, and its fixed label is escaped', $render( '[button text="<i>[x" href="javascript:alert(1)"]' ) === '<a class="nino-btn" href="#">&lt;i&gt;&#91;x</a>' );

// The card: the <article> of a component that holds components. What stands between its tags is the template's own markup, rendered with the output
check( '[card] renders nothing without content, and the <article> with its class around what it holds',
	$render( '[card][/card]' ) === '' && $render( '[card]  [/card]' ) === '' && $render( '[card ground="alt"][/card]' ) === ''
	&& $render( '[card]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>'
	&& $render( '[card][card-title /template/page-home/hero/title][card-subtitle /template/page-home/hero/subtitle][/card]' ) === '<article class="nino-card"><div class="nino-card-content"><h4 class="nino-card-title">Hi &#91;there]</h4><p class="nino-card-subtitle">Sub</p></div></article>' );
check( '...a card whose components all render nothing is an <article> with an empty div of the content, as a section with nothing in it is a section',
	$render( '[card][title /template/page-home/nope/title][/card]' ) === '<article class="nino-card"><div class="nino-card-content"></div></article>' );
// Every attribute is its class, and the classes follow the fragment: the ground, the dim, the border, the fill, the link, where the content stands, the alignment, the padding and the class of its own
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
$cardClasses['linked="1"'] = ' nino-card--linked';
$cardClasses['class="a b"'] = ' a b';
$cardWrong = [];
foreach( $cardClasses as $cardAttribute => $cardClass )
	if( $render( '[card '. $cardAttribute. ']x[/card]' ) !== '<article class="nino-card'. $cardClass. '"><div class="nino-card-content">x</div></article>' )
		$cardWrong[] = $cardAttribute;
check( '[card] writes each of its attributes as the class of it: a ground, where the content stands, the alignment, the padding of every step - the 0 among them - the fill, the dim, the border, the link of the title, and a class of its own'. ( $cardWrong === [] ? '' : ' - not: '. implode( ', ', $cardWrong ) ), $cardWrong === [] && count( $cardClasses ) === 25 );
check( '...all at once, in the order of the fragment, and a class of its own last, escaped',
	$render( '[card ground="alt" padding="3" fill="1" linked="1" vertical="last-bottom" align="right" dim="1" borderless="1" class="mine"]x[/card]' )
		=== '<article class="nino-card nino-card--alt nino-card--dim nino-card--borderless nino-card--fill nino-card--linked nino-card--last-bottom nino-text-right nino-p-3 mine"><div class="nino-card-content">x</div></article>'
	&& $render( '[card class="a b&c"]x[/card]' ) === '<article class="nino-card a b&amp;c"><div class="nino-card-content">x</div></article>'
	&& $render( '[card ground="counter" class="x"]x[/card]' ) === '<article class="nino-card nino-card--counter x"><div class="nino-card-content">x</div></article>' );
check( '...a word outside what an attribute offers is the default, and so is a bool that is none: the class is not written',
	$render( '[card ground="weird" padding="9" vertical="weird" align="weird" fill="maybe" dim="x" borderless="2" linked="2" focus="12"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>'
	&& $render( '[card ground="ALT" align="Center" padding="-1"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>'
	&& $render( '[card ground="alt&quot; onclick=&quot;x"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>'
	&& $render( '[card bogus="x" onclick="y"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>'
	&& $render( '[card fill="true" dim="yes" borderless="on" linked="yes"]x[/card]' ) === '<article class="nino-card nino-card--dim nino-card--borderless nino-card--fill nino-card--linked"><div class="nino-card-content">x</div></article>' );
/*	The card is no <a>, whatever it says: it holds buttons, and a link in a link is no markup. [card linked] writes the class and nothing else, and the link it makes of the card is the one of
	its title - the <a class="nino-stretch"> that [card-title] draws, which is what Nino.css stretches over the card - so that a card whose title has no address has no link at all	*/
check( '[card linked] is no link itself: the <article> with the class, and the link of its title - the <a> around the title - and the button after it as they are anywhere; a title with no address is no link, and the card has none',
	$render( '[card linked="1"][card-title /template/page-home/hero/title href="/contact"][button text="More" href="#x"][/card]' )
		=== '<article class="nino-card nino-card--linked"><div class="nino-card-content"><a class="nino-stretch" href="/contact"><h4 class="nino-card-title">Hi &#91;there]</h4></a><a class="nino-btn" href="#x">More</a></div></article>'
	&& $render( '[card linked="1"][card-title /template/page-home/hero/title][/card]' ) === '<article class="nino-card nino-card--linked"><div class="nino-card-content"><h4 class="nino-card-title">Hi &#91;there]</h4></div></article>'
	&& str_contains( $render( '[card linked="1"][card-title /template/page-home/hero/title][/card]' ), '</a>' ) === false
	&& str_contains( $render( '[card linked="1"][card-title /template/page-home/hero/title][/card]' ), 'nino-stretch' ) === false
	&& str_starts_with( $render( '[card linked="1"]x[/card]' ), '<article' ) === true && str_starts_with( \Nino\Modules\Components::$html['link'], '<a' ) === true );
// The picture behind it
$appData['/nino/html/images']['/template/page-home/hero/withalt'] = [ 'label' => 'With alt', 'width' => 800, 'height' => 600, 'filename' => 'alt.800x600.jpg', 'alt' => [ 'de_DE' => 'Stored "alt" [x]' ] ];
check( '[card] with a picture - a slot by its uri - has the picture in a div of its own before the content, as an img with an empty alt text, and the focus is a class of that div',
	$render( '[card background="/template/page-home/hero/background" focus="3"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-bg nino-img-focus--3"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""></div><div class="nino-card-content">x</div></article>'
	&& $render( '[card background="/template/page-home/hero/background" ground="alt" dim="1"]x[/card]' ) === '<article class="nino-card nino-card--alt nino-card--dim"><div class="nino-card-bg"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""></div><div class="nino-card-content">x</div></article>' );
check( '...the alt text is none even where the slot has one: behind a card the picture is decoration - and [image] of the same slot still says it',
	str_contains( $render( '[card background="/template/page-home/hero/withalt"]x[/card]' ), '<img src="/public/images/alt.800x600.jpg" width="800" height="600" alt="">' ) === true
	&& str_contains( $render( '[image /template/page-home/hero/withalt]' ), 'alt="Stored &quot;alt&quot; &#91;x]"' ) === true );
check( '...no picture, no div: a slot with no file, a slot there is none of, an empty reference and a focus without a picture write none',
	$render( '[card background="/template/page-home/hero/empty" focus="5"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>'
	&& $render( '[card background="/template/page-home/nope/nope" focus="5"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>'
	&& $render( '[card background="" focus="5"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>'
	&& $render( '[card background="image"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>' );
// In a stack: a card to an element, the cell a div and the card in it, and the element is the context of what the card holds
check( '[card] in a [stack]: one card to an element in the div of the cell, and the components in it see their element - their values escaped as anywhere, the [ of a title written as &#91;',
	$render( '[stack /services sort="title"][card ground="counter"][card-title title][card-subtitle category][/card][/stack]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-2">'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--counter"><div class="nino-card-content"><h4 class="nino-card-title">App</h4><p class="nino-card-subtitle">app</p></div></article></div>'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--counter"><div class="nino-card-content"><h4 class="nino-card-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h4><p class="nino-card-subtitle">web</p></div></article></div></div>'
	&& str_contains( $render( '[stack /services sort="title" limit="1" offset="1"][card][card-title title][/card][/stack]' ), '&#91;x]' ) === true
	&& str_contains( $render( '[stack /services sort="title" limit="1" offset="1"][card][card-title title][/card][/stack]' ), '[x]' ) === false );
$cardStack = $render( '[stack /services sort="title"][card background="image" focus="2" ground="alt"][card-title title][/card][/stack]' );
check( '...background="image" takes the picture of the element\'s image field, with an empty alt text - the field names one - and an element whose field holds no file has no div',
	str_contains( $cardStack, '<article class="nino-card nino-card--alt"><div class="nino-card-bg nino-img-focus--2"><img src="/public/images/services/web/image.webp" width="400" height="300" alt=""></div><div class="nino-card-content"><h4 class="nino-card-title">Web' ) === true
	&& substr_count( $cardStack, 'nino-card-bg' ) === 1 && str_contains( $cardStack, '<article class="nino-card nino-card--alt"><div class="nino-card-content"><h4 class="nino-card-title">App</h4></div></article>' ) === true
	&& str_contains( $cardStack, 'Alt' ) === false );
check( '...a slot works there as well, a card outside a stack sees no element, and the context is gone after the stack',
	str_contains( $render( '[stack /services limit="1"][card background="/template/page-home/hero/background"][probe][/card][/stack]' ), '<article class="nino-card"><div class="nino-card-bg"><img src="/public/images/bg.1600x900.jpg"' ) === true
	&& $render( '[card][probe][/card]' ) === '<article class="nino-card"><div class="nino-card-content">outside</div></article>' && \Nino\Modules\Components::element( $appData ) === null );
check( '...the cards of a stack are drawn the same each time, and each card is the first thing in its cell, its content in the div of its own',
	$render( '[stack /services sort="title"][card fill="1"]x[/card][/stack]' ) === $render( '[stack /services sort="title"][card fill="1"]x[/card][/stack]' )
	&& substr_count( $render( '[stack /services sort="title"][card fill="1"]x[/card][/stack]' ), '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--fill"><div class="nino-card-content">x</div></article></div>' ) === 2 );
check( 'the markup of the card and of its picture is a property like that of any component, and what the content holds is never looked at again for a token of the fragment',
	str_contains( (string) \Nino\Modules\Components::$html['card'], '[[background]][[inner]]</article>' ) === true && \Nino\Modules\Components::$html['card-bg'] === '<div class="nino-card-bg[[focus]]">[[img]]</div>'
	&& $render( '[card ground="alt"]x [[class]] [[background]] [[ground]][/card]' ) === '<article class="nino-card nino-card--alt"><div class="nino-card-content">x [[class]] [[background]] [[ground]]</div></article>' );

// The card sorts its children: the first picture stands first in the <article>, after the picture behind the card, and every other child stands behind it in the div of the content
\Nino\Modules\Components::addComponent( $appData, 'imageprobe', static fn( array &$appData, array $args ): string => '<b>'. $args['value']. '</b>', [ 'label' => 'x', 'source' => 'image', 'group' => 'global' ] );
$cardPicture = '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="" class="nino-card-img">';
$cardTitle = '<h4 class="nino-card-title">Hi &#91;there]</h4>';
$cardText = '<div class="nino-card-descr nino-richtext"><p>One</p><ul><li>a</li></ul></div>';
check( '[card] sorts what it holds: the first child that is a picture - a [card-image], an [image], any component whose source is an image - stands first in the <article>, wherever among the others it was written, and every other child stands behind it in a <div class="nino-card-content">, in the order it was written in',
	$render( '[card][card-image /template/page-home/hero/background][card-title /template/page-home/hero/title][card-text /template/page-home/services/text][/card]' )
		=== '<article class="nino-card">'. $cardPicture. '<div class="nino-card-content">'. $cardTitle. $cardText. '</div></article>'
	&& $render( '[card][card-title /template/page-home/hero/title][card-image /template/page-home/hero/background][card-text /template/page-home/services/text][/card]' )
		=== '<article class="nino-card">'. $cardPicture. '<div class="nino-card-content">'. $cardTitle. $cardText. '</div></article>'
	&& $render( '[card][card-title /template/page-home/hero/title][image /template/page-home/hero/background][/card]' ) === '<article class="nino-card"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""><div class="nino-card-content">'. $cardTitle. '</div></article>'
	&& $render( '[card][card-title /template/page-home/hero/title][imageprobe /template/page-home/hero/background][/card]' ) === '<article class="nino-card"><b>/template/page-home/hero/background</b><div class="nino-card-content">'. $cardTitle. '</div></article>' );
check( '...white space between the children is the div\'s: the picture is the first thing in the <article> and the div the second, with nothing between them or around them, and the lines of the template stay between the children of the div',
	$render( "[card]\n\t[card-image /template/page-home/hero/background]\n\t[card-title /template/page-home/hero/title]\n\t[card-text /template/page-home/services/text]\n[/card]" )
		=== '<article class="nino-card">'. $cardPicture. "<div class=\"nino-card-content\">". $cardTitle. "\n\t". $cardText. '</div></article>' );
check( '...a card without a picture has the div alone, and one with nothing but a picture has no div - the picture behind the card is before both, and the stack draws the same card for every element',
	$render( '[card][card-title /template/page-home/hero/title][/card]' ) === '<article class="nino-card"><div class="nino-card-content">'. $cardTitle. '</div></article>'
	&& $render( '[card][card-image /template/page-home/hero/background][/card]' ) === '<article class="nino-card">'. $cardPicture. '</article>'
	&& $render( '[card background="/template/page-home/hero/background" ground="dark"][card-image /template/page-home/hero/background][card-title /template/page-home/hero/title][/card]' )
		=== '<article class="nino-card nino-card--dark"><div class="nino-card-bg"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""></div>'. $cardPicture. '<div class="nino-card-content">'. $cardTitle. '</div></article>'
	&& str_contains( $render( '[stack /services sort="title" offset="1" limit="1"][card][card-title title][card-image image][/card][/stack]' ),
		'<article class="nino-card"><img src="/public/images/services/web/image.webp" width="400" height="300" alt="Alt &quot;q&quot;" class="nino-card-img"><div class="nino-card-content"><h4 class="nino-card-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h4></div></article>' ) === true );
check( '...only the first picture is the card\'s: a second one is a child like the others and stands in the div, a picture that renders nothing is still the first, and a picture inside the content of another call is that call\'s',
	$render( '[card][card-image /template/page-home/hero/background][card-title /template/page-home/hero/title][card-image /template/page-home/hero/background class="x"][/card]' )
		=== '<article class="nino-card">'. $cardPicture. '<div class="nino-card-content">'. $cardTitle. '<div class="nino-image nino-card-img x"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""></div></div></article>'
	&& $render( '[card][card-image /template/page-home/hero/empty][card-title /template/page-home/hero/title][card-image /template/page-home/hero/background][/card]' )
		=== '<article class="nino-card"><div class="nino-card-content">'. $cardTitle. $cardPicture. '</div></article>'
	&& $render( '[card][html]<p>x</p>[card-image /template/page-home/hero/background][/html][card-title /template/page-home/hero/title][/card]' ) === '<article class="nino-card"><div class="nino-card-content"><p>x</p><p>'. $cardPicture. '</p>'. $cardTitle. '</div></article>' );
check( '...the markup of the div is a property like the card\'s, and what the card holds is put into it once: the token of the fragment is never looked at in what the card holds',
	\Nino\Modules\Components::$html['card-content'] === '<div class="nino-card-content">[[inner]]</div>'
	&& $render( '[card]x [[inner]] [[class]][/card]' ) === '<article class="nino-card"><div class="nino-card-content">x [[inner]] [[class]]</div></article>' );

// Whatever a hand-written file gets wrong still renders
check( 'an attribute that is not declared is ignored', $render( '[title /template/page-home/hero/title bogus="x" onclick="y"]' ) === $render( '[title /template/page-home/hero/title]' ) );
check( 'a select outside its options is the default', $render( '[title /template/page-home/hero/title tag="h9" style="weird"]' ) === '<h3 class="nino-section-title">Hi &#91;there]</h3>'
	&& $render( '[spacer size="99"]' ) === '<div class="nino-spacer nino-spacer-2"></div>' && $render( '[text /template/page-home/services/text format="nonsense"]' ) === $render( '[text /template/page-home/services/text]' ) );
check( 'a source that resolves to nothing renders nothing - not a heading with nothing in it', $render( '[title /template/page-home/nope/title]' ) === '' && $render( '[subtitle /template/page-home/nope/title]' ) === ''
	&& $render( '[text /template/page-home/nope/title]' ) === '' && $render( '[button /template/page-home/nope/title href="#"]' ) === '' && $render( '[title]' ) === ''
	&& $render( '[title /template/page-home/empty/title]' ) === '' && $render( '[title title]' ) === '' );
check( 'a fill in the first argument is not a thing: the source is the key, not its value', str_contains( $render( '[title [[/template/page-home/hero/title]]]' ), 'nino-section-title' ) === false );

// The fixed text of a call that carries a [[key]] on a page: the fills of a page are put in before its shortcodes run, so the call gets text="..." with the text of the key in it as the fills hold it -
// a plain text with its quotes and brackets as entities, as the panels store it - and reads it as it reads the text of a key: an entity is the character it stands for, written once. So does the
// call of an author who types an entity, as in any attribute of html. The renderers have no say in it
\Nino\Html::addFills( $appData, [
	'/template/page-home/stored/call'		=> \Nino\Text::sanitizeValue( '[title /template/page-home/hero/title]', 'plain' ),
	'/template/page-home/stored/amp'		=> \Nino\Text::sanitizeValue( 'Müller & Söhne', 'plain' ),
	'/template/page-home/stored/entity'	=> 'Müller &amp; Söhne',
	'/template/page-home/probe/entities'	=> 'x &lt;b&gt;y&lt;/b&gt; &amp; z',
	'/template/page-home/probe/doubled'	=> '&amp;lt;b&amp;gt;',
], '*' );
check( 'the fixed text of a call on a page shows the text of a key once, as the key shows it as the source of the same component: the apostrophe, the quotes and the brackets of a stored plain value are the characters they were typed as, not an entity escaped again - in a title, in the middle of a text and in a button',
	$render( '[title text="[[/template/page-home/stored/title]]"]' ) === $render( '[title /template/page-home/stored/title]' )
	&& $render( '[title text="[[/template/page-home/stored/title]]"]' ) === '<h3 class="nino-section-title">Let&#039;s talk about &quot;us&quot; &#91;today]</h3>'
	&& $render( '[title text="So: [[/template/page-home/stored/title]]!"]' ) === '<h3 class="nino-section-title">So: Let&#039;s talk about &quot;us&quot; &#91;today]!</h3>'
	&& $render( '[button text="[[/template/page-home/stored/title]]" href="#x"]' ) === $render( '[button /template/page-home/stored/title href="#x"]' ) );
check( '...an ampersand is one: a key that holds Müller & Söhne and a key that holds it written Müller &amp; Söhne both show it once - not as the &amp;amp; that escaping the entity again made',
	$render( '[title text="[[/template/page-home/stored/amp]]"]' ) === '<h3 class="nino-section-title">Müller &amp; Söhne</h3>' && $render( '[title text="[[/template/page-home/stored/entity]]"]' ) === '<h3 class="nino-section-title">Müller &amp; Söhne</h3>'
	&& $render( '[title text="[[/template/page-home/stored/amp]]"]' ) === $render( '[title /template/page-home/stored/amp]' ) && $render( '[title text="[[/template/page-home/stored/entity]]"]' ) === $render( '[title /template/page-home/stored/entity]' ) );
check( '...an entity that an author types in the fixed text is the character, as in an attribute of html, and a character that is none stays one: Fish &amp; Chips and Fish & Chips are the same words, &lt;b&gt; is text and no element, and &amp;lt; is the five characters it spells',
	$render( '[title text="Fish &amp; Chips"]' ) === '<h3 class="nino-section-title">Fish &amp; Chips</h3>' && $render( '[title text="Fish & Chips"]' ) === '<h3 class="nino-section-title">Fish &amp; Chips</h3>'
	&& $render( '[title text="&lt;b&gt;x&lt;/b&gt;"]' ) === '<h3 class="nino-section-title">&lt;b&gt;x&lt;/b&gt;</h3>' && $render( '[title text="&amp;lt;"]' ) === '<h3 class="nino-section-title">&amp;lt;</h3>' );
check( '...a bracket written as an entity is a bracket once the text is decoded, and it is written inert with the rest: a stored value that spells a call, or a fixed text that does, is shown and opens nothing',
	$render( '[title text="[[/template/page-home/stored/call]]"]' ) === '<h3 class="nino-section-title">&#91;title /template/page-home/hero/title]</h3>'
	&& $render( '[title text="&#91;title text=&quot;x&quot;&#93;"]' ) === '<h3 class="nino-section-title">&#91;title text=&quot;x&quot;]</h3>' );
check( '...the other formats are not decoded: the fixed text of [text format="inline"] is sanitized as typed, as the text of a key of that format is - an &lt;b&gt; in it is text, and no element',
	$render( '[text text="x &lt;b&gt;y&lt;/b&gt;" format="inline"]' ) === '<p class="nino-section-text">x &lt;b&gt;y&lt;/b&gt;</p>'
	&& $render( '[text text="[[/template/page-home/probe/entities]]" format="inline"]' ) === $render( '[text /template/page-home/probe/entities format="inline"]' )
	&& $render( '[text text="[[/template/page-home/probe/entities]]" format="inline"]' ) === '<p class="nino-section-text">x &lt;b&gt;y&lt;/b&gt; &amp; z</p>' );

// A call that comes from elsewhere has none put in: the call is made to the callback of the shortcode as a page makes it, with the arguments as they were written, and dispatch() puts the fills in
// of the fixed text as the pass of a page does - the text of the key as the fills hold it, read by value() as above - so that the two are the same call
\Nino\Html::addFills( $appData, [
	'/template/page-home/probe/tag'		=> 'h2',
	'/template/page-home/probe/class'	=> 'one two',
	'/template/page-home/probe/outer'	=> 'Outer [[/template/page-home/hero/title]]',
	'/template/page-home/probe/key'		=> '/template/page-home/hero/title',
], '*' );
$direct = static fn( string $shortcode, array $args ): string => (string) \Nino\Callbacks::doCallbacks( $appData, '/nino/html/shortcode/'. $shortcode, $args );
check( 'a [[key]] in the fixed text of a call is the text of the key, written once - and the call a page makes with the fill put in is the same call',
	$direct( 'title', [ 'text' => '[[/template/page-home/hero/title]]' ] ) === '<h3 class="nino-section-title">Hi &#91;there]</h3>'
	&& $direct( 'title', [ 'text' => 'A [[/template/page-home/hero/subtitle]] B' ] ) === '<h3 class="nino-section-title">A Sub B</h3>'
	&& $direct( 'title', [ 'text' => 'A [[/template/page-home/hero/subtitle]] B' ] ) === $render( '[title text="A [[/template/page-home/hero/subtitle]] B"]' )
	&& $direct( 'button', [ 'text' => '[[/template/page-home/hero/subtitle]]', 'href' => '#' ] ) === '<a class="nino-btn" href="#">Sub</a>' );
check( '...a plain value stored with its quotes and brackets as entities shows the characters it was typed with, as value() shows a key - and as the page does: the two paths are one call, decoded once, whatever the text holds',
	$direct( 'title', [ 'text' => '[[/template/page-home/stored/title]]' ] ) === $render( '[title /template/page-home/stored/title]' )
	&& $direct( 'title', [ 'text' => '[[/template/page-home/stored/title]]' ] ) === $render( '[title text="[[/template/page-home/stored/title]]"]' )
	&& $direct( 'title', [ 'text' => '[[/template/page-home/probe/doubled]]' ] ) === $render( '[title /template/page-home/probe/doubled]' ) && $direct( 'title', [ 'text' => '[[/template/page-home/probe/doubled]]' ] ) === '<h3 class="nino-section-title">&amp;lt;b&amp;gt;</h3>'
	&& $direct( 'text', [ 'text' => '[[/template/page-home/probe/entities]]', 'format' => 'inline' ] ) === $render( '[text /template/page-home/probe/entities format="inline"]' )
	&& $direct( 'title', [ 'text' => '[[/template/page-home/stored/call]]' ] ) === $render( '[title text="[[/template/page-home/stored/call]]"]' ) );
check( '...a key with no text is nothing, and a fill inside the text of a fill is not resolved: it is written as the text it is, with its brackets escaped like any other',
	$direct( 'title', [ 'text' => 'A [[/template/page-home/nope/nope]] B' ] ) === '<h3 class="nino-section-title">A  B</h3>'
	&& $direct( 'title', [ 'text' => '[[/template/page-home/probe/outer]]' ] ) === '<h3 class="nino-section-title">Outer &#91;&#91;/template/page-home/hero/title]]</h3>' );
check( '...and nothing else of a call is read like that: the attributes are taken as they stand - a select with a fill in it is outside its options, a class writes the brackets inert -, the first argument is no source with a fill in it, not even where the text of the fill is the key of a text, and the content is left to the rendering that follows',
	$direct( 'title', [ '/template/page-home/hero/title', 'tag' => '[[/template/page-home/probe/tag]]' ] ) === '<h3 class="nino-section-title">Hi &#91;there]</h3>'
	&& $direct( 'title', [ '/template/page-home/hero/title', 'class' => 'a [[/template/page-home/probe/class]] b' ] ) === '<h3 class="nino-section-title a &#91;&#91;/template/page-home/probe/class]] b">Hi &#91;there]</h3>'
	&& $direct( 'title', [ '[[/template/page-home/hero/title]]' ] ) === '' && $direct( 'title', [ '[[/template/page-home/probe/key]]' ] ) === ''
	&& $direct( 'title', [ '/template/page-home/probe/key' ] ) === '<h3 class="nino-section-title">/template/page-home/hero/title</h3>'
	&& $direct( 'html', [ 'content' => '<p>[[/template/page-home/hero/title]]</p>' ] ) === '<p>[[/template/page-home/hero/title]]</p>' );

// [image] is a component now, and what it did it still does
check( '[image] of a slot: the same <img> as before', $render( '[image /template/page-home/hero/background alt="A"]' ) === '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="A">' );
check( '[image] as before in the named form, with the alt of the template', $render( '[image uri="/template/page-home/hero/background" alt="B"]' ) === '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="B">' );
check( '[image] with content as before: the content, filled, and nothing for a slot with no file', $render( '[image /template/page-home/hero/background]<meta content="[[src]]" data-size="[[width]]x[[height]]">[/image]' ) === '<meta content="/public/images/bg.1600x900.jpg" data-size="1600x900">'
	&& $render( '[image /template/page-home/hero/empty]<meta content="[[src]]">[/image]' ) === '' && $render( '[image /template/page-home/hero/empty]' ) === '' );
// [[url]] is the absolute address of the file in the content form: the directory the project is served from stands once, since the address of the website carries it and the path is given without it
$urlForm = '[image /template/page-home/hero/background]<meta content="[[url]]" data-src="[[src]]">[/image]';
\Nino\Html::addFills( $appData, [ '/project/website/general/url' => 'host.example/sub/' ], '*' );
$appData['/nino/dir'] = '/sub';
$underSub = $render( $urlForm );
\Nino\Html::addFills( $appData, [ '/project/website/general/url' => 'host.example' ], '*' );
$appData['/nino/dir'] = '';
$atRoot = $render( $urlForm );
\Nino\Html::removeFill( $appData, '/project/website/general/url' );
$appData['/nino/dir'] = '/sub';
$noAddress = $render( $urlForm );
$appData['/nino/dir'] = '';
check( '[image] with content: [[url]] is the absolute address of the file - https://host/sub/public/... for a project under /sub whose website is host/sub, https://host/public/... at the root - and [[src]] is the path with the directory, as before',
	$underSub === '<meta content="https://host.example/sub/public/images/bg.1600x900.jpg" data-src="/sub/public/images/bg.1600x900.jpg">'
	&& $atRoot === '<meta content="https://host.example/public/images/bg.1600x900.jpg" data-src="/public/images/bg.1600x900.jpg">' );
check( '...where the website has no address [[url]] is the path alone, as [[src]] has it, and not an empty string; and a slot with no file is still nothing, [card-image] has it too',
	$noAddress === '<meta content="/sub/public/images/bg.1600x900.jpg" data-src="/sub/public/images/bg.1600x900.jpg">'
	&& $render( '[image /template/page-home/hero/empty]<meta content="[[url]]">[/image]' ) === ''
	&& $render( '[card-image /template/page-home/hero/background]<meta content="[[url]]">[/card-image]' ) === '<meta content="/public/images/bg.1600x900.jpg">' );
// The templates that ship write it: the head of a page under /sub carries og:image and twitter:image with the directory once
$appData['/nino/html/images']['/logo'] = [ 'label' => 'Logo', 'width' => 500, 'height' => 100, 'filename' => 'logo.500x100.png' ];
$shipped = [ 'head' => '/_admin/install/library/base/templates/html-header.tpl', 'mail' => '/_nino/Nino/Modules/Form/install/templates/mail-header.tpl' ];
$shippedHead = (string) file_get_contents( dirname( __DIR__ ). $shipped['head'] );
$shippedMail = (string) file_get_contents( dirname( __DIR__ ). $shipped['mail'] );
\Nino\Html::addFills( $appData, [ '/project/website/general/url' => 'host.example/sub' ], '*' );
$appData['/nino/dir'] = '/sub';
$headUnder = \Nino\Html::renderHtml( $appData, $shippedHead );
$mailUnder = \Nino\Html::renderHtml( $appData, $shippedMail );
\Nino\Html::addFills( $appData, [ '/project/website/general/url' => 'host.example' ], '*' );
$appData['/nino/dir'] = '';
$headRoot = \Nino\Html::renderHtml( $appData, $shippedHead );
$mailRoot = \Nino\Html::renderHtml( $appData, $shippedMail );
\Nino\Html::removeFill( $appData, '/project/website/general/url' );
unset( $appData['/nino/html/images']['/logo'] );
check( 'the head of a page that ships, rendered for a project under /sub, carries og:image and twitter:image with /sub once - and the mail header of the Form module the logo with /sub once; at the root with none',
	str_contains( $headUnder, '<meta property="og:image" content="https://host.example/sub/public/images/logo.500x100.png">' ) === true
	&& str_contains( $headUnder, '<meta name="twitter:image" content="https://host.example/sub/public/images/logo.500x100.png">' ) === true
	&& str_contains( $mailUnder, '<img src="https://host.example/sub/public/images/logo.500x100.png" width="180"' ) === true
	&& str_contains( $headRoot, '<meta property="og:image" content="https://host.example/public/images/logo.500x100.png">' ) === true
	&& str_contains( $headRoot, '<meta name="twitter:image" content="https://host.example/public/images/logo.500x100.png">' ) === true
	&& str_contains( $mailRoot, '<img src="https://host.example/public/images/logo.500x100.png" width="180"' ) === true );
check( '[image] with a focus, a ratio or a class of its own is framed - the frame is what crops', $render( '[image /template/page-home/hero/background focus="5" ratio="16-9" class="x"]' ) === '<div class="nino-image nino-image--16-9 nino-img-focus--5 x"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt=""></div>'
	&& $render( '[image /template/page-home/hero/background focus="12" ratio="2-1"]' ) === '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="">' );
$image = $render( '[stack /services sort="title" offset="1" limit="1"][image image focus="2"][image /template/page-home/hero/background][image nope][/stack]' );
check( '[image] in a stack: an image field is the file it holds, its size is the field\'s, its alt text the field the type names', str_contains( $image, '<div class="nino-image nino-img-focus--2"><img src="/public/images/services/web/image.webp" width="400" height="300" alt="Alt &quot;q&quot;"></div>' ) === true );
check( '...a slot still works there, and a name that is neither a field nor a slot renders nothing', str_contains( $image, '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="">' ) === true && substr_count( $image, '<img' ) === 2 );
check( '...and an image field with no file renders nothing', substr_count( $render( '[stack /services sort="title" limit="1"][image image][/stack]' ), '<img' ) === 0 );

// [logo]: the pictures of two slots in one set, the ground says which is seen
$logoUrl = '/public/images/logo.500x100.png';
$logoDarkUrl = '/public/images/logo-dark.500x100.png';
$appData['/nino/html/images']['/logo'] = [ 'label' => 'Logo', 'width' => 500, 'height' => 100, 'filename' => 'logo.500x100.png' ];
$appData['/nino/html/images']['/logo/dark'] = [ 'label' => 'Logo, dark', 'width' => 500, 'height' => 100, 'filename' => 'logo-dark.500x100.png' ];
$logoImg = static fn( string $url, string $ground, string $alt = '' ): string => '<img src="'. $url. '" width="500" height="100" alt="'. $alt. '" class="nino-logo-'. $ground. '">';
check( 'the logo is a component of the kernel: labelled in both languages, no source of its own, the picture of a block, offered in a section and a hero and under global, drawing the set, no link - and a half-filled circle as its icon',
	$registryOf( 'logo' )['label'] === [ 'en_US' => 'Logo', 'de_DE' => 'Logo' ] && $registryOf( 'logo' )['source'] === 'none' && $registryOf( 'logo' )['preview'] === 'block'
	&& $registryOf( 'logo' )['in'] === [ 'hero', 'section' ] && $registryOf( 'logo' )['group'] === 'global' && $registryOf( 'logo' )['draws'] === 'span .nino-logo-set'
	&& array_key_exists( 'holds', $registryOf( 'logo' ) ) === false && $registryOf( 'logo' )['link'] === false && $registryOf( 'logo' )['stretched'] === false && $registryOf( 'logo' )['by'] === 'nino'
	&& array_column( (array) $registryOf( 'logo' )['icon'], 0 ) === [ 'circle', 'path' ] );
check( '...its attributes are the two slots and the alt text, in that order: light and dark are of the type image and default to /logo and /logo/dark, the alt text is a string that is empty, and the defaults are those, with the class',
	array_keys( $registryOf( 'logo' )['attributes'] ) === [ 'light', 'dark', 'alt' ]
	&& $registryOf( 'logo' )['attributes']['light']['type'] === 'image' && $registryOf( 'logo' )['attributes']['light']['default'] === '/logo'
	&& $registryOf( 'logo' )['attributes']['dark']['type'] === 'image' && $registryOf( 'logo' )['attributes']['dark']['default'] === '/logo/dark'
	&& $registryOf( 'logo' )['attributes']['alt']['type'] === 'string' && $registryOf( 'logo' )['attributes']['alt']['default'] === ''
	&& \Nino\Modules\Components::defaults( $appData, 'logo' ) === [ 'light' => '/logo', 'dark' => '/logo/dark', 'alt' => '', 'class' => '' ]
	&& array_filter( [ 'light', 'dark', 'alt' ], static fn( string $name ): bool => array_keys( (array) $registryOf( 'logo' )['attributes'][$name]['label'] ) !== [ 'en_US', 'de_DE' ] ) === [] );
check( '[logo] draws both pictures in one set, the one for a light ground first, each with the size of its slot and the class of its ground',
	$render( '[logo]' ) === '<span class="nino-logo-set">'. $logoImg( $logoUrl, 'light' ). $logoImg( $logoDarkUrl, 'dark' ). '</span>' );
check( '...[logo dark=""] only the light one and [logo light=""] only the dark one - a set of one picture, which Nino.css shows on every ground - and a slot that has no file draws no <img> either',
	$render( '[logo dark=""]' ) === '<span class="nino-logo-set">'. $logoImg( $logoUrl, 'light' ). '</span>'
	&& $render( '[logo light=""]' ) === '<span class="nino-logo-set">'. $logoImg( $logoDarkUrl, 'dark' ). '</span>'
	&& $render( '[logo light="/template/page-home/hero/empty"]' ) === '<span class="nino-logo-set">'. $logoImg( $logoDarkUrl, 'dark' ). '</span>' );
$appData['/nino/html/images']['/logo/dark']['filename'] = null;
$logoOne = $render( '[logo]' );
$appData['/nino/html/images']['/logo/dark']['filename'] = 'logo-dark.500x100.png';
$appData['/nino/html/images']['/logo']['filename'] = null;
$logoOther = $render( '[logo]' );
$appData['/nino/html/images']['/logo/dark']['filename'] = null;
$logoNone = [ $render( '[logo]' ), $render( '[logo alt="Acme" class="x"]' ), $render( '[logo light="" dark=""]' ), $render( '[logo light="/template/page-home/nope/nope"]' ) ];
$appData['/nino/html/images']['/logo']['filename'] = 'logo.500x100.png';
$appData['/nino/html/images']['/logo/dark']['filename'] = 'logo-dark.500x100.png';
check( '...with the file of one slot missing the set holds the other, with the file of none it draws nothing at all - an empty string, which keeps the wrapper of the navigation :empty and lets a frame\'s link write the name of the site - however it is called',
	$logoOne === '<span class="nino-logo-set">'. $logoImg( $logoUrl, 'light' ). '</span>' && $logoOther === '<span class="nino-logo-set">'. $logoImg( $logoDarkUrl, 'dark' ). '</span>'
	&& $logoNone === [ '', '', '', '' ] );
$appData['/nino/html/images']['/logo']['alt'] = [ 'de_DE' => 'Stored "alt" [x]' ];
check( '...the alt text is the one stored for the slot, then the one of the call, then none - found as [image] finds it, on each of the two pictures, escaped and with its [ written as &#91;',
	$render( '[logo alt="Call"]' ) === '<span class="nino-logo-set">'. $logoImg( $logoUrl, 'light', 'Stored &quot;alt&quot; &#91;x]' ). $logoImg( $logoDarkUrl, 'dark', 'Call' ). '</span>'
	&& $render( '[logo alt="Call"]' ) === $render( '[logo alt="Call" light="/logo" dark="/logo/dark"]' )
	&& $render( '[logo]' ) === '<span class="nino-logo-set">'. $logoImg( $logoUrl, 'light', 'Stored &quot;alt&quot; &#91;x]' ). $logoImg( $logoDarkUrl, 'dark' ). '</span>' );
unset( $appData['/nino/html/images']['/logo']['alt'] );
check( '...a class of the call goes on the set and nowhere else, after the class of the set, and the set writes no empty class attribute',
	$render( '[logo class="nino-footer-logo"]' ) === '<span class="nino-logo-set nino-footer-logo">'. $logoImg( $logoUrl, 'light' ). $logoImg( $logoDarkUrl, 'dark' ). '</span>'
	&& $render( '[logo class="x &quot;y"]' ) === '<span class="nino-logo-set x &amp;quot;y">'. $logoImg( $logoUrl, 'light' ). $logoImg( $logoDarkUrl, 'dark' ). '</span>'
	&& preg_match( '/\sclass=""/', $render( '[logo]' ) ) !== 1 );
check( '...a call that names slots of its own draws those, with their size - and a name that is neither a slot nor a field is no picture',
	$render( '[logo light="/template/page-home/hero/background" dark="/logo"]' ) === '<span class="nino-logo-set"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="" class="nino-logo-light">'. $logoImg( $logoUrl, 'dark' ). '</span>'
	&& $render( '[logo light="nope" dark="nope"]' ) === '' );
check( '...it has no link, and the content form is not one: the set stands as it is where the call is written with a closing tag, and an href is an attribute it does not declare',
	$render( '[logo href="#x" dark=""]' ) === $render( '[logo dark=""]' ) && $registryOf( 'logo' )['link'] === false && str_contains( $render( '[logo]' ), '<a ' ) === false
	&& $render( '[logo dark=""][/logo]' ) === $render( '[logo dark=""]' ) );
$logoStack = $render( '[stack /services sort="title" offset="1" limit="1"][logo light="image" dark=""][/stack]' );
check( '...in a stack an image field is the file it holds, with its size and the alt text its type names; a field with no file draws nothing',
	str_contains( $logoStack, '<span class="nino-logo-set"><img src="/public/images/services/web/image.webp" width="400" height="300" alt="Alt &quot;q&quot;" class="nino-logo-light"></span>' ) === true
	&& substr_count( $render( '[stack /services sort="title" limit="1"][logo light="image" dark=""][/stack]' ), '<img' ) === 0 );
check( '...a card does not take it for its picture, as it does a component whose source is an image: it stands in the content of the card, in the order it was written',
	$render( '[card][logo dark=""][card-title text="T"][/card]' ) === '<article class="nino-card"><div class="nino-card-content"><span class="nino-logo-set">'. $logoImg( $logoUrl, 'light' ). '</span>'. $render( '[card-title text="T"]' ). '</div></article>' );
check( '...the scan of a template finds the two slots a call names, those it leaves to their defaults included, and none that it leaves out',
	\Nino\Modules\Components::sources( $appData, '[logo alt=""]' ) === [ [ 'name' => 'logo', 'kind' => 'image', 'source' => '/logo', 'attribute' => 'light' ], [ 'name' => 'logo', 'kind' => 'image', 'source' => '/logo/dark', 'attribute' => 'dark' ] ]
	&& \Nino\Modules\Components::sources( $appData, '[logo dark=""]' ) === [ [ 'name' => 'logo', 'kind' => 'image', 'source' => '/logo', 'attribute' => 'light' ] ]
	&& \Nino\Modules\Components::sources( $appData, '[logo light="/brand/mark" dark=""]' ) === [ [ 'name' => 'logo', 'kind' => 'image', 'source' => '/brand/mark', 'attribute' => 'light' ] ]
	&& \Nino\Modules\Components::sources( $appData, '[logo light="image" dark=""]' ) === [] && \Nino\Modules\Components::sources( $appData, '[card background="/x"]a[/card]' ) === [ [ 'name' => 'card', 'kind' => 'image', 'source' => '/x', 'attribute' => 'background' ] ] );
unset( $appData['/nino/html/images']['/logo'], $appData['/nino/html/images']['/logo/dark'] );

// The class of [image]: the frame is what cuts a picture, so a class makes the frame, and is the frame's
$slotImg = '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="">';
check( '[image] with a class of its own is framed, the frame carries it - behind the ratio and the focus - and the <img> in it has none: nino-image is the element the exception of the image kind names, nino-radius and nino-radius-alt the classes it is written as',
	$render( '[image /template/page-home/hero/background class="x"]' ) === '<div class="nino-image x">'. $slotImg. '</div>'
	&& $render( '[image /template/page-home/hero/background ratio="16-9" focus="5" class="nino-radius-alt"]' ) === '<div class="nino-image nino-image--16-9 nino-img-focus--5 nino-radius-alt">'. $slotImg. '</div>'
	&& isset( $groundEach['.nino-image.nino-radius'], $groundEach['.nino-image.nino-radius-alt'] ) === true );
check( '...[image] has no style: one written on it is an attribute that is not declared and is ignored, the picture is the bare <img> as without it - and the style of a card\'s picture is none of [image]\'s',
	$render( '[image /template/page-home/hero/background style="weird"]' ) === $slotImg
	&& $render( '[image /template/page-home/hero/background style="inline"]' ) === $slotImg
	&& $render( '[image /template/page-home/hero/background style="weird" alt="B"]' ) === '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="B">' );
// [card-image]: the picture of a card is the <img> of nino-card-img - or, for a focus, a ratio or a class of its own, the frame, which carries the class
check( '[card-image] is the <img> of the slot with the class nino-card-img - the class attribute is there because there is a class to write - and the class of the style follows it',
	$render( '[card-image /template/page-home/hero/background alt="A"]' ) === '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="A" class="nino-card-img">'
	&& $render( '[card-image /template/page-home/hero/background style="inline"]' ) === '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="" class="nino-card-img nino-card-img--inline">' );
check( '...a focus, a ratio or a class of its own puts it in the frame of [image], which carries nino-card-img - with the class of the style and the class of its own - and holds an <img> with no class, so that what styles the picture of a card styles the frame',
	$render( '[card-image /template/page-home/hero/background ratio="4-3" focus="3"]' ) === '<div class="nino-image nino-image--4-3 nino-img-focus--3 nino-card-img">'. $slotImg. '</div>'
	&& $render( '[card-image /template/page-home/hero/background focus="5" style="inline" class="x"]' ) === '<div class="nino-image nino-img-focus--5 nino-card-img nino-card-img--inline x">'. $slotImg. '</div>'
	&& $render( '[card-image /template/page-home/hero/background ratio="21-9"]' ) === '<div class="nino-image nino-image--21-9 nino-card-img">'. $slotImg. '</div>'
	&& $render( '[card-image /template/page-home/hero/background class="x &quot;y"]' ) === '<div class="nino-image nino-card-img x &amp;quot;y">'. $slotImg. '</div>'
	&& $render( '[card-image /template/page-home/hero/background style="inline" class="x"]' ) === '<div class="nino-image nino-card-img nino-card-img--inline x">'. $slotImg. '</div>' );
// A style is no call for the frame: it is the class of the box and goes where that goes, on the <img> as long as nothing else asks for a frame
$cardStyles = array_values( array_filter( (array) $registryOf( 'card-image' )['attributes']['style']['options'], static fn( string $word ): bool => $word !== '' ) );
$cardStyleClass = static fn( string $word ): string => 'nino-card-img '. $registryOf( 'card-image' )['attributes']['style']['prefix']. $word;
check( '...and a style alone is no call for it: each style a card\'s picture offers, with nothing else on the call, leaves it the bare <img> with the class of the box and the class of the style, outside a card and in one',
	$cardStyles !== [] && array_filter( $cardStyles, static fn( string $word ): bool => $render( '[card-image /template/page-home/hero/background style="'. $word. '"]' ) !== '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="" class="'. $cardStyleClass( $word ). '">'
		|| $render( '[card][card-image /template/page-home/hero/background style="'. $word. '"][/card]' ) !== '<article class="nino-card"><img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="" class="'. $cardStyleClass( $word ). '"></article>' ) === [] );
check( '...a style outside its options is none, a focus or a ratio that is none is none, and the alt text, the slot with no file and the content form are those of [image]',
	$render( '[card-image /template/page-home/hero/background style="weird" focus="12" ratio="2-1"]' ) === $render( '[card-image /template/page-home/hero/background]' )
	&& $render( '[card-image /template/page-home/hero/withalt alt="Own"]' ) === '<img src="/public/images/alt.800x600.jpg" width="800" height="600" alt="Stored &quot;alt&quot; &#91;x]" class="nino-card-img">'
	&& $render( '[card-image /template/page-home/hero/empty]' ) === '' && $render( '[card-image /template/page-home/nope/nope]' ) === ''
	&& $render( '[card-image /template/page-home/hero/background]<meta content="[[src]]" data-size="[[width]]x[[height]]">[/card-image]' ) === '<meta content="/public/images/bg.1600x900.jpg" data-size="1600x900">' );
$cardImage = $render( '[stack /services sort="title" offset="1" limit="1"][card][card-image image focus="2"][card-image /template/page-home/hero/background][card-image nope][/card][/stack]' );
check( '...the frame of a card\'s picture carries nino-image as well, and the rule of the image kind leaves it out: its radius is the card\'s',
	str_contains( $render( '[card-image /template/page-home/hero/background focus="5"]' ), '<div class="nino-image nino-img-focus--5 nino-card-img">' ) === true
	&& isset( $groundEach['.nino-image:not(.nino-card-img)'] ) === true );
check( '...in a stack the image field is the file it holds, with its alt text, framed and bare, and a name that is neither a field nor a slot renders nothing',
	str_contains( $cardImage, '<div class="nino-image nino-img-focus--2 nino-card-img"><img src="/public/images/services/web/image.webp" width="400" height="300" alt="Alt &quot;q&quot;"></div>' ) === true
	&& str_contains( $cardImage, '<img src="/public/images/bg.1600x900.jpg" width="1600" height="900" alt="" class="nino-card-img">' ) === true && substr_count( $cardImage, '<img' ) === 2 );
// The class of the hover of a picture is the frame's, as nino-radius is: the stylesheet names .nino-image.nino-hover (see the hover of a picture above), and the renderers put the class where the frame is
check( 'the class nino-hover stands on the frame, behind the ratio and the focus - [image] and [card-image] frame a picture for any class, the card\'s with nino-card-img before the class - and the <img> in it has none, and an <a> wraps the frame where there is an address',
	$render( '[image /template/page-home/hero/background class="nino-hover" ratio="3-2"]' ) === '<div class="nino-image nino-image--3-2 nino-hover">'. $slotImg. '</div>'
	&& $render( '[image /template/page-home/hero/background class="nino-hover"]' ) === '<div class="nino-image nino-hover">'. $slotImg. '</div>'
	&& $render( '[card-image /template/page-home/hero/background class="nino-hover" ratio="3-2"]' ) === '<div class="nino-image nino-image--3-2 nino-card-img nino-hover">'. $slotImg. '</div>'
	&& $render( '[image /template/page-home/hero/background class="nino-hover" href="#x"]' ) === '<a href="#x"><div class="nino-image nino-hover">'. $slotImg. '</div></a>' );
check( '...and the picture of a card that has the class and no ratio or focus is framed as well, with nino-card-img before the class on the frame and an <img> with none in it: the class has the frame to stand on that it has in [image]',
	$render( '[card-image /template/page-home/hero/background class="nino-hover"]' ) === '<div class="nino-image nino-card-img nino-hover">'. $slotImg. '</div>' );
// The class attribute is written where there is a class, and nowhere else: not on a bare picture, not on the one behind a card, not on the one in a frame
$emptyClass = static fn( string $html ): bool => preg_match( '/\sclass=""/', $html ) === 1;
$classForms = [
	'[image /template/page-home/hero/background]', '[image uri="/template/page-home/hero/background" alt="B"]', '[image /template/page-home/hero/background focus="5" ratio="16-9" class="x"]',
	'[image /template/page-home/hero/background class="nino-radius-alt"]', '[card-image /template/page-home/hero/background]', '[card-image /template/page-home/hero/background ratio="4-3"]',
	'[card background="/template/page-home/hero/background" focus="3"]x[/card]', '[stack /services sort="title"][card background="image"][image image][card-image image][/card][/stack]',
];
check( 'no form of [image], [card-image] or [card] writes an empty class attribute: it is there where there is a class to write and nowhere else',
	array_filter( $classForms, static fn( string $form ): bool => $emptyClass( $render( $form ) ) === true ) === [] && str_contains( $render( $classForms[7] ), '<img' ) === true );
$shippedImg = \Nino\Modules\Components::$html['img'];
\Nino\Modules\Components::$html['img'] = str_replace( '[[class]]', ' class="[[class]]"', $shippedImg );
$mutated = [ $render( '[image /template/page-home/hero/background]' ), $render( '[card background="/template/page-home/hero/background"]x[/card]' ) ];
\Nino\Modules\Components::$html['img'] = $shippedImg;
check( '...and that check can see one: a fragment that writes the attribute whether or not there is a class makes it fail, for the bare picture and for the one behind a card',
	array_filter( $mutated, $emptyClass ) === $mutated && $emptyClass( $render( '[image /template/page-home/hero/background]' ) ) === false );

// The colour of a text, the ink of the ground it stands on: a variant, the class nino-ink--<word> behind the class of the style and before the class of its own
$colourBase = [ 'title' => 'nino-section-title', 'subtitle' => 'nino-section-subtitle', 'card-title' => 'nino-card-title', 'card-subtitle' => 'nino-card-subtitle', 'hero-title' => 'nino-hero-title', 'hero-subtitle' => 'nino-hero-subtitle' ];
$colourWrong = [];
foreach( $colourBase as $colourName => $colourClass ) {
	$colourTag = $sixTags[$colourName][0];
	foreach( [ 'muted', 'accent' ] as $colourWord ) {
		if( $render( '['. $colourName. ' /template/page-home/hero/title style="loud" color="'. $colourWord. '" class="x"]' ) !== '<'. $colourTag. ' class="'. $colourClass. ' '. $colourClass. '--loud nino-ink--'. $colourWord. ' x">Hi &#91;there]</'. $colourTag. '>' )
			$colourWrong[] = $colourName. ' '. $colourWord;
		if( $render( '['. $colourName. ' /template/page-home/hero/title color="'. $colourWord. '"]' ) !== '<'. $colourTag. ' class="'. $colourClass. ' nino-ink--'. $colourWord. '">Hi &#91;there]</'. $colourTag. '>' )
			$colourWrong[] = $colourName. ' '. $colourWord. ' alone';
	}
	foreach( [ 'weird', 'light', 'dark', 'error', 'nino-ink--muted', 'MUTED' ] as $colourOther )
		if( $render( '['. $colourName. ' /template/page-home/hero/title color="'. $colourOther. '"]' ) !== $render( '['. $colourName. ' /template/page-home/hero/title]' ) )
			$colourWrong[] = $colourName. ' '. $colourOther;
}
check( 'the colour of a title or a subtitle is the class nino-ink--<word> behind the class of the style and before the class of its own, for each of the six: muted and accent are the words, and any other - light, dark or a status among them - is none'. ( $colourWrong === [] ? '' : ' - '. implode( ', ', $colourWrong ) ), $colourWrong === [] );
check( '...and so is the colour of the two texts: behind the style, before the class of its own, and a word outside the options is none',
	$render( '[text /template/page-home/services/text style="loud" color="muted" class="x"]' ) === '<div class="nino-section-text nino-richtext nino-section-text--loud nino-ink--muted x"><p>One</p><ul><li>a</li></ul></div>'
	&& $render( '[card-text /template/page-home/services/text format="inline" color="accent"]' ) === '<div class="nino-card-descr nino-ink--accent">One a</div>'
	&& $render( '[text /template/page-home/services/text color="light"]' ) === $render( '[text /template/page-home/services/text]' ) && $render( '[card-text /template/page-home/services/text color="dark"]' ) === $render( '[card-text /template/page-home/services/text]' ) );

// A link: what a component that says so drew, in the <a> of its address - for each of the ten
$linkOpens = [
	'title' => '[title /template/page-home/hero/title', 'subtitle' => '[subtitle /template/page-home/hero/subtitle', 'text' => '[text /template/page-home/services/text',
	'card-title' => '[card-title /template/page-home/hero/title', 'card-subtitle' => '[card-subtitle /template/page-home/hero/subtitle', 'card-text' => '[card-text /template/page-home/services/text',
	'card-image' => '[card-image /template/page-home/hero/background', 'hero-title' => '[hero-title /template/page-home/hero/title', 'hero-subtitle' => '[hero-subtitle /template/page-home/hero/subtitle',
	'image' => '[image /template/page-home/hero/background',
];
$linkWrong = [];
foreach( $linkOpens as $linkName => $linkOpen ) {
	$linkPlain = $render( $linkOpen. ']' );
	if( $linkPlain === '' || in_array( $linkName, $kernelLinked, true ) === false )
		$linkWrong[] = $linkName. ' is no link or draws nothing';
	$linkClass = in_array( $linkName, [ 'card-title', 'title' ], true ) ? ' class="nino-stretch"' : '';
	if( $render( $linkOpen. ' href="/_nino/webpage/contact/uri"]' ) !== '<a'. $linkClass. ' href="/contact">'. $linkPlain. '</a>' )
		$linkWrong[] = $linkName. ' with the key of an address';
	if( $render( $linkOpen. ' href="https://example.com/a" target="_blank"]' ) !== '<a'. $linkClass. ' href="https://example.com/a" target="_blank" rel="noopener">'. $linkPlain. '</a>' )
		$linkWrong[] = $linkName. ' with an address and a target';
	if( $render( $linkOpen. ' href="javascript:alert(1)"]' ) !== '<a'. $linkClass. ' href="#">'. $linkPlain. '</a>' )
		$linkWrong[] = $linkName. ' with a scheme that is none';
	if( $render( $linkOpen. ' target="_blank"]' ) !== $linkPlain || $render( $linkOpen. ' href=""]' ) !== $linkPlain || str_contains( $linkPlain, 'nino-stretch' ) === true )
		$linkWrong[] = $linkName. ' without an address';
}
check( 'each of the ten components that can be a link draws what it draws in an <a> that has the address of its href - a key is its text, an address is itself, a scheme that is none a #, and _blank says the new window has no business with the opener - and without an address it is no link, with a target alone either; the <a> of the two that say stretched, the title and the card\'s title, has the class nino-stretch before its address, the other eight none'
	. ( $linkWrong === [] ? '' : ' - '. implode( ', ', $linkWrong ) ), $linkWrong === [] && count( $linkOpens ) === 10 );
check( '...the address is written as it is made safe, once: the & of a query is &amp;, not &amp;amp; - what it holds written as a key\'s value is what it is - and what a link wraps is the frame of a picture, not its <img>',
	$render( '[title /template/page-home/hero/title href="/template/page-home/stored/link"]' ) === '<a class="nino-stretch" href="/a?b=&quot;c&quot;&amp;d=1"><h3 class="nino-section-title">Hi &#91;there]</h3></a>'
	&& $render( '[image /template/page-home/hero/background ratio="4-3" href="#x"]' ) === '<a href="#x"><div class="nino-image nino-image--4-3">'. $slotImg. '</div></a>'
	&& $render( '[card-image /template/page-home/hero/background class="x" href="#x"]' ) === '<a href="#x"><div class="nino-image nino-card-img x">'. $slotImg. '</div></a>' );
check( '...a source that resolves to nothing is nothing, not an empty link, and in a stack the address is a field of the element or the place in the loop, drawn once per element',
	$render( '[title /template/page-home/nope/title href="#x"]' ) === '' && $render( '[image /template/page-home/hero/empty href="#x"]' ) === ''
	&& $render( '[stack /services sort="title" limit="1"][title title href=".uri" target="_blank"][/stack]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-2"><div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><a class="nino-stretch" href="/services/app" target="_blank" rel="noopener"><h3 class="nino-section-title">App</h3></a></div></div>' );
check( '...the others take no link: the card holds buttons, the button has an address of its own, and the html, the spacer and the scroll hint do not name one - an href on them is an attribute nobody declared, and ignored',
	$render( '[card href="#x" target="_blank"]x[/card]' ) === '<article class="nino-card"><div class="nino-card-content">x</div></article>' && $render( '[spacer href="#x"]' ) === '<div class="nino-spacer nino-spacer-2"></div>'
	&& $render( '[html href="#x"]<p>Hi</p>[/html]' ) === '<p>Hi</p>' && $render( '[button text="Go" href="#x" target="_blank"]' ) === '<a class="nino-btn" href="#x" target="_blank" rel="noopener">Go</a>'
	&& $render( '[scroll-hint href="#x" target="_blank"]' ) === $render( '[scroll-hint]' ) );
check( '...and a component of a project that says link is wrapped the same way, a component that does not is not',
	$render( '[linkprobe /template/page-home/hero/title href="#x" target="_blank"]' ) === '<a href="#x" target="_blank" rel="noopener"><i>Hi &#91;there]</i></a>' && $render( '[linkprobe /template/page-home/hero/title]' ) === '<i>Hi &#91;there]</i>'
	&& ( static function() use ( &$appData, $render ): bool {
		\Nino\Modules\Components::addComponent( $appData, 'plainprobe', static fn( array &$appData, array $args ): string => '<i>'. $args['value']. '</i>', [ 'label' => 'x', 'source' => 'text', 'group' => 'global' ] );
		return $render( '[plainprobe /template/page-home/hero/title href="#x"]' ) === '<i>Hi &#91;there]</i>';
	} )() );
// A component that says stretched gives the <a> of its address the class nino-stretch, in a card and out of one: the renderer reads no context. One that draws its own <a> gives it the class itself, and the kernel adds none
\Nino\Modules\Components::addComponent( $appData, 'stretchprobe', static fn( array &$appData, array $args ): string => '<i>'. $args['value']. '</i>', $linkable + [ 'link' => true, 'stretched' => true, 'by' => 'hello', 'source' => 'text' ] );
\Nino\Modules\Components::addComponent( $appData, 'ownprobe', static fn( array &$appData, array $args ): string => '<a class="own-probe" href="#own"><i>'. $args['value']. '</i></a>', $linkable + [ 'stretched' => true, 'by' => 'hello', 'source' => 'text' ] );
check( '...and a component of a project that says stretched has the class on the <a> the kernel makes of its address - in a card and out of one, first, before the address and the window - and none where it has no address; one that says stretched and draws its own <a> has that <a> as it drew it',
	$render( '[stretchprobe /template/page-home/hero/title href="#x" target="_blank"]' ) === '<a class="nino-stretch" href="#x" target="_blank" rel="noopener"><i>Hi &#91;there]</i></a>'
	&& $render( '[card linked="1"][stretchprobe /template/page-home/hero/title href="#x"][/card]' ) === '<article class="nino-card nino-card--linked"><div class="nino-card-content"><a class="nino-stretch" href="#x"><i>Hi &#91;there]</i></a></div></article>'
	&& $render( '[stretchprobe /template/page-home/hero/title]' ) === '<i>Hi &#91;there]</i>'
	&& $render( '[ownprobe /template/page-home/hero/title href="#x"]' ) === '<a class="own-probe" href="#own"><i>Hi &#91;there]</i></a>'
	&& \Nino\Modules\Components::components( $appData )['ownprobe']['stretched'] === true && array_keys( \Nino\Modules\Components::defaults( $appData, 'ownprobe' ) ) === [ 'tag', 'class' ] );

// The width of a button: full is the class nino-btn--fullwidth, after the size
check( '[button width="full"] is the class nino-btn--fullwidth after the style and the size and before its own class; none is no class, and a word outside the options is none',
	$render( '[button text="Go" width="full"]' ) === '<span class="nino-btn nino-btn--fullwidth">Go</span>'
	&& $render( '[button text="Go" style="primary" size="big" width="full" class="x" href="#y"]' ) === '<a class="nino-btn nino-btn--primary nino-btn--big nino-btn--fullwidth x" href="#y">Go</a>'
	&& $render( '[button text="Go" width=""]' ) === $render( '[button text="Go"]' ) && $render( '[button text="Go" width="fullwidth"]' ) === $render( '[button text="Go"]' ) && $render( '[button text="Go" width="weird"]' ) === $render( '[button text="Go"]' ) );

// The scroll hint: a real link, the arrow at the foot of a hero, named by a text of its own - the next section, which is not the next slide of a slider
\Nino\Html::addFills( $appData, [ '/template/common/slider/next' => 'Vor', '/template/common/navigation/next-section' => 'Zum Abschnitt' ], '*' );
$baseWords = static fn( string $key ): array => array_map( static fn( string $locale ): string => (string) ( ( include __DIR__. '/../_admin/install/library/base/text/'. $locale. '.php' )['[[/template/common/'. $key. ']]'] ?? '' ), [ 'de_DE', 'en_US' ] );
$nextWords = $baseWords( 'navigation/next-section' );
$slideWords = $baseWords( 'slider/next' );
check( '[scroll-hint] is an <a> of nino-hero-arrowdown named for a screen reader by a text of its own, not the word of the next control of a slider, which the base unit ships in both languages: a # marked data-arrow-next for the script to scroll to the next section, or the id of the section it is told to go to',
	$render( '[scroll-hint]' ) === '<a class="nino-hero-arrowdown" href="#" data-arrow-next aria-label="Zum Abschnitt"></a>'
	&& $render( '[scroll-hint to="news-2" class="x y"]' ) === '<a class="nino-hero-arrowdown x y" href="#news-2" aria-label="Zum Abschnitt"></a>'
	&& in_array( '', $nextWords, true ) === false && $nextWords[0] !== $nextWords[1] && $nextWords[0] !== $slideWords[0] && $nextWords[1] !== $slideWords[1] );
check( '...a target that is no id is none - it never reaches the address - and the arrow is a link and no button',
	$render( '[scroll-hint to="9 bad&quot; onclick=&quot;x"]' ) === $render( '[scroll-hint]' ) && $render( '[scroll-hint to="a-b_c"]' ) === '<a class="nino-hero-arrowdown" href="#a-b_c" aria-label="Zum Abschnitt"></a>'
	&& $render( '[scroll-hint class="a&quot;b"]' ) === '<a class="nino-hero-arrowdown a&amp;quot;b" href="#" data-arrow-next aria-label="Zum Abschnitt"></a>' && str_contains( $render( '[scroll-hint]' ), '<button' ) === false );
check( 'the base unit says the name of the menu button and the words of the skip link in both languages, each in the words of its own: what a screen reader is told is a text of the page and never a key',
	array_filter( [ 'navigation/menu', 'navigation/skip' ], static fn( string $key ): bool => in_array( '', $baseWords( $key ), true ) === true || $baseWords( $key )[0] === $baseWords( $key )[1] ) === [] );

// The sections: a stack whose cell is a whole section
\Nino\Elements::insertElementType( $appData, '/posts', [ 'title' => [ 'type' => 'string', 'locale' => true ] ] );
foreach( [ 'p1' => 'One', 'p2' => 'Two', 'p3' => 'Three' ] as $postUri => $postTitle )
	\Nino\Elements::insertElement( $appData, '/posts/'. $postUri, [ 'title' => $postTitle ], 'de_DE' );
$postsRow = '<div class="nino-grid-row"><div class="nino-grid-100">[title title]</div></div>';
check( '[sections] draws a section to an element, in the order of the loop: the id of the call and the number of the element, counted from 1, the class of the call after nino-section - and the fields of each element resolved inside its own',
	$render( '[sections /posts sort="-title" id="news" class="nino-section--alt nino-pt-2"]'. $postsRow. '[/sections]' )
		=== '<section id="news-1" class="nino-section nino-section--alt nino-pt-2"><div class="nino-grid-row"><div class="nino-grid-100"><h3 class="nino-section-title">Two</h3></div></div></section>'
		. '<section id="news-2" class="nino-section nino-section--alt nino-pt-2"><div class="nino-grid-row"><div class="nino-grid-100"><h3 class="nino-section-title">Three</h3></div></div></section>'
		. '<section id="news-3" class="nino-section nino-section--alt nino-pt-2"><div class="nino-grid-row"><div class="nino-grid-100"><h3 class="nino-section-title">One</h3></div></div></section>' );
check( '...without an id the section has none, and nothing stands round the sections: they stand where the call stood, one after the other, so that the rules about the first section of a page keep working',
	$render( '<main>[sections /posts sort="title" limit="2"][title title][/sections]</main>' ) === '<main><section class="nino-section"><h3 class="nino-section-title">One</h3></section><section class="nino-section"><h3 class="nino-section-title">Three</h3></section></main>'
	&& $render( '[sections /posts sort="title" offset="2" id="a b"][title title][/sections]' ) === '<section class="nino-section"><h3 class="nino-section-title">Two</h3></section>' );
check( '...reads sort, offset, limit and query as every stack does, is nothing for a type with no elements, for none and for a query nothing matches, and its elements are the context of what its content holds - which is gone after the call',
	$render( '[sections /posts sort="title" limit="1" offset="1" id="s"][title title][/sections]' ) === '<section id="s-1" class="nino-section"><h3 class="nino-section-title">Three</h3></section>'
	&& $render( '[sections /nothing][title title][/sections]' ) === '' && $render( '[sections][title title][/sections]' ) === '' && $render( '[sections /posts query="title=Nope"][title title][/sections]' ) === ''
	&& str_contains( $render( '[sections /services sort="title" limit="1"][probe][/sections]' ), 'inside:/services#0#/services/app#string' ) === true && \Nino\Modules\Components::element( $appData ) === null );
check( '...a section draws no cell: the elements of the loop are the sections, with no div of a grid between them, and no attribute of a grid - cols, gap or autoheight - is heard',
	$render( '[sections /posts sort="title" limit="1" cols="50" gap="4" autoheight="1"][title title][/sections]' ) === '<section class="nino-section"><h3 class="nino-section-title">One</h3></section>' );
check( '...an id is a slug that starts with a letter, the whole of it: one with a line break behind it is none - for the sections, for a stack and for the arrow of a hero alike - and does not reach an attribute',
	$render( "[sections /posts sort=\"title\" offset=\"2\" id=\"a\n\"][title title][/sections]" ) === '<section class="nino-section"><h3 class="nino-section-title">Two</h3></section>'
	&& str_starts_with( $render( "[stack /services limit=\"1\" gap=\"3\" id=\"svc\n\"][probe][/stack]" ), '<div class="nino-grid-row nino-stack nino-stack-gap-3"><div class="nino-grid-s-100' ) === true
	&& $render( "[scroll-hint to=\"a\n\"]" ) === $render( '[scroll-hint]' ) );

// The sections that alternate: the class that turns the row of every second one around is written by the renderer, which counts the loop it draws
$sectionOf = static fn( string $title, string $classes = '', string $id = '' ): string => '<section'. $id. ' class="nino-section'. $classes. '"><h3 class="nino-section-title">'. $title. '</h3></section>';
check( '[sections alternate="1"] gives every second section the class nino-section--mirrored: the second of three and no other, after the classes of the call and the id - the first and the third are as they were',
	$render( '[sections /posts sort="title" id="n" class="nino-section--alt nino-pt-2" alternate="1"][title title][/sections]' )
		=== $sectionOf( 'One', ' nino-section--alt nino-pt-2', ' id="n-1"' ). $sectionOf( 'Three', ' nino-section--alt nino-pt-2 nino-section--mirrored', ' id="n-2"' ). $sectionOf( 'Two', ' nino-section--alt nino-pt-2', ' id="n-3"' )
	&& $render( '[sections /posts sort="title" alternate="1"][title title][/sections]' ) === $sectionOf( 'One' ). $sectionOf( 'Three', ' nino-section--mirrored' ). $sectionOf( 'Two' ) );
\Nino\Elements::insertElementType( $appData, '/stripes', [ 'title' => [ 'type' => 'string', 'locale' => true ] ] );
foreach( [ 'a', 'b', 'c', 'd', 'e' ] as $stripe )
	\Nino\Elements::insertElement( $appData, '/stripes/'. $stripe, [ 'title' => strtoupper( $stripe ) ], 'de_DE' );
check( '...every second one and not the second alone: of five it is the second and the fourth, which are the elements 1 and 3 of the loop counted from 0',
	$render( '[sections /stripes sort="title" alternate="1"][title title][/sections]' )
		=== $sectionOf( 'A' ). $sectionOf( 'B', ' nino-section--mirrored' ). $sectionOf( 'C' ). $sectionOf( 'D', ' nino-section--mirrored' ). $sectionOf( 'E' ) );
$sectionsPlain = '<section class="nino-section"><h3 class="nino-section-title">One</h3></section><section class="nino-section"><h3 class="nino-section-title">Three</h3></section><section class="nino-section"><h3 class="nino-section-title">Two</h3></section>';
check( '...without the attribute no section carries the class and the output is what it was, byte for byte - as it is with the attribute off and with a word that is none of a bool\'s',
	$render( '[sections /posts sort="title"][title title][/sections]' ) === $sectionsPlain && $render( '[sections /posts sort="title" alternate="0"][title title][/sections]' ) === $sectionsPlain
	&& $render( '[sections /posts sort="title" alternate="maybe"][title title][/sections]' ) === $sectionsPlain );
check( '...it counts the sections as the loop draws them, and each loop for itself: after an offset the first section drawn is the first, and a second loop on the page starts again - which no rule of a stylesheet could tell',
	$render( '[sections /posts sort="title" offset="1" alternate="1"][title title][/sections]' ) === $sectionOf( 'Three' ). $sectionOf( 'Two', ' nino-section--mirrored' )
	&& $render( '[sections /posts sort="title" limit="1" alternate="1"][title title][/sections][sections /posts sort="title" offset="1" alternate="1"][title title][/sections]' )
		=== $sectionOf( 'One' ). $sectionOf( 'Three' ). $sectionOf( 'Two', ' nino-section--mirrored' ) );
/*	From the tablet breakpoint the row that is a child of a mirrored section stands the other way round, and that is all the stylesheet does about it: one rule, which asks for the class and for nothing
	of how it got there, so that a section that carries it by hand is turned the same way as one the renderer wrote it on. Below 768px no rule names it - the columns keep the order of the markup, and
	a picture stands above its text in every section on a phone	*/
$mirrorQuery = '/@media\s*\(min-width:\s*768px\)\s*(\{(?:[^{}]++|(?1))*\})/';
$outsideMirrorQuery = preg_replace( $mirrorQuery, '', $hookCss );
check( 'the row of a mirrored section is turned around by one rule - row-reverse, in the query of the tablet breakpoint - and no rule names the class anywhere else',
	count( $groundEach['.nino-section--mirrored > .nino-grid-row'] ?? [] ) === 1 && $groundSets( $groundOf( '.nino-section--mirrored > .nino-grid-row' ), 'flex-direction', 'row-reverse' ) === true
	&& $groundConditions( '.nino-section--mirrored > .nino-grid-row' ) === [ [ '@media (min-width: 768px)' ] ]
	&& $outsideMirrorQuery !== null && substr_count( $outsideMirrorQuery, 'nino-section--mirrored' ) === 0 );

/*	The order of a row turned round, per width. --reverse is the base (every width) and --<tier>-reverse / --<tier>-forward are in the media query of the tier's breakpoint, the tiers in the order
	of the width classes and each tier's pair after that tier's width classes and before the next tier's. A reversed row says row-reverse AND wrap-reverse (the whole reading order turned round),
	a forward one row and wrap. Each of the nine says again what the alignment classes mean: a row that is not centred packs its lines at the end under row-reverse (flex-end) and at the start
	under forward, and under wrap-reverse top is flex-end and bottom flex-start (forward: the other way round) - the rules weigh two classes, every one the same, so that the order decides and a
	later tier wins; the justify rule asks for a row that is not centred, so that nino-grid-center is not touched and keeps its centre	*/
$turnTiers = [ '' => [], 's' => [ '@media (min-width: 640px)' ], 'm' => [ '@media (min-width: 768px)' ], 'l' => [ '@media (min-width: 1024px)' ], 'xl' => [ '@media (min-width: 1280px)' ] ];
$turnWrong = [];
$turnLastAt = $groundLastOf( [ '.nino-grid-center' ], 'justify-content' );
$turnPrevious = max( $groundLastOf( [ '.nino-grid-row' ], 'flex-wrap' ), $turnLastAt, $groundLastOf( [ '.nino-grid-top' ] ), $groundLastOf( [ '.nino-grid-bottom' ] ) );
foreach( $turnTiers as $turnTier => $turnConditions ) {
	$turnPrefix = '.nino-grid-row--'. ( $turnTier === '' ? '' : $turnTier. '-' );
	$turnDirections = $turnTier === '' ? [ 'reverse' ] : [ 'reverse', 'forward' ];
	foreach( $turnDirections as $turnDirection ) {
		$turnReverse = $turnDirection === 'reverse';
		$turnClass = $turnPrefix. $turnDirection;
		$turnExpected = [
			$turnClass => [ [ 'flex-direction', $turnReverse ? 'row-reverse' : 'row' ], [ 'flex-wrap', $turnReverse ? 'wrap-reverse' : 'wrap' ] ],
			$turnClass. ':not(.nino-grid-center)' => [ [ 'justify-content', $turnReverse ? 'flex-end' : 'flex-start' ] ],
			$turnClass. '.nino-grid-top' => [ [ 'align-items', $turnReverse ? 'flex-end' : 'flex-start' ] ],
			$turnClass. '.nino-grid-bottom' => [ [ 'align-items', $turnReverse ? 'flex-start' : 'flex-end' ] ],
		];
		foreach( $turnExpected as $turnSelector => $turnDeclarations ) {
			$turnBody = $groundOf( $turnSelector );
			if( count( $groundEach[$turnSelector] ?? [] ) !== 1 )
				$turnWrong[] = $turnSelector. ' is not one rule';
			foreach( $turnDeclarations as [ $turnProperty, $turnValue ] )
				if( $groundSets( $turnBody, $turnProperty, $turnValue ) === false )
					$turnWrong[] = $turnSelector. ' does not say '. $turnProperty. ': '. $turnValue;
			if( preg_match_all( '/;/', $turnBody ) !== count( $turnDeclarations ) )
				$turnWrong[] = $turnSelector. ' says more or less than it should';
			if( $groundConditions( $turnSelector ) !== [ $turnConditions ] )
				$turnWrong[] = $turnSelector. ' is not in '. ( $turnConditions === [] ? 'no query' : $turnConditions[0] );
			$turnAt = $groundLastOf( [ $turnSelector ] );
			if( $turnAt <= $turnPrevious )
				$turnWrong[] = $turnSelector. ' is not after the rules before it';
			$turnPrevious = $turnAt;
		}
	}
	if( $turnTier !== '' && $groundLastOf( [ $turnPrefix. 'reverse' ] ) <= $groundLastOf( [ '.nino-grid-'. $turnTier. '-100' ] ) )
		$turnWrong[] = $turnTier. ' tier stands before its width classes';
	if( $turnTier !== '' && $groundLastOf( [ $turnPrefix. 'forward.nino-grid-bottom' ] ) >= $groundLastOf( [ '.nino-grid-'. [ 's' => 'm', 'm' => 'l', 'l' => 'xl', 'xl' => 'zz' ][$turnTier]. '-25' ] ) && $turnTier !== 'xl' )
		$turnWrong[] = $turnTier. ' tier stands after the width classes of the next';
	$turnPrevious = max( $turnPrevious, $turnTier === '' ? 0 : $groundLastOf( [ '.nino-grid-'. $turnTier. '-100' ] ) );
}
$turnNames = array_values( array_filter( array_keys( $groundEach ), static fn( mixed $selector ): bool => str_contains( (string) $selector, 'reverse' ) === true || str_contains( (string) $selector, 'forward' ) === true ) );
check( 'a row is turned round by nine classes and their rules - --reverse on every width, --s|m|l|xl-reverse and --s|m|l|xl-forward from their breakpoint - each with the stack and the lines, the packing and the cross axis'
	. ( $turnWrong === [] ? '' : ' - '. implode( '; ', $turnWrong ) ),
	$turnWrong === [] && count( $turnNames ) === 4 + 4 * 8 && $turnLastAt > 0 );
check( '...the alignment classes themselves are as they were - top, middle, bottom and centre say their one declaration and nothing about the direction - and nothing turns a row through a class that is not its own',
	$groundSets( $groundOf( '.nino-grid-top' ), 'align-items', 'flex-start' ) && $groundSets( $groundOf( '.nino-grid-middle' ), 'align-items', 'center' ) && $groundSets( $groundOf( '.nino-grid-bottom' ), 'align-items', 'flex-end' )
	&& $groundSets( $groundOf( '.nino-grid-center' ), 'justify-content', 'center' ) && preg_match( '/direction|reverse/', $groundOf( '.nino-grid-center' ). $groundOf( '.nino-grid-top' ). $groundOf( '.nino-grid-bottom' ). $groundOf( '.nino-grid-middle' ) ) !== 1
	&& $groundOf( '.nino-grid-row--reverse.nino-grid-middle' ) === '' && $groundOf( '.nino-grid-row--reverse.nino-grid-center' ) === '' && preg_match( '/\.nino-grid-row--(?:[a-z]+-)?(?:reverse|forward)(?![\w:.-])[^{]*,/', $hookCss ) !== 1
	&& substr_count( $hookCss, 'nino-section--mirrored' ) === 1 );

// A project's own component replaces the one of the kernel, and nothing else answers for it
\Nino\Modules\Components::addComponent( $appData, 'title', static function( array &$appData, array $args ): string {
	return '<'. $args['tag']. ' class="mine">'. $args['value']. '</'. $args['tag']. '>';
}, [ 'label' => 'Title', 'source' => 'text', 'group' => 'section', 'attributes' => [ 'tag' => [ 'type' => 'select', 'options' => [ 'h1', 'h2', 'h3' ], 'default' => 'h3', 'label' => 'Tag' ] ] ] );
check( 'a component registered again replaces the one before it: its renderer, its defaults, its options', $render( '[title /template/page-home/hero/title]' ) === '<h3 class="mine">Hi &#91;there]</h3>'
	&& $render( '[title /template/page-home/hero/title tag="h4"]' ) === '<h3 class="mine">Hi &#91;there]</h3>'
	&& \Nino\Modules\Components::defaults( $appData, 'title' ) === [ 'tag' => 'h3', 'class' => '' ] && count( \Nino\Callbacks::registered( $appData, '/nino/html/shortcode/title' ) ) === 1 );
\Nino\Modules\Components::init( $appData );
check( '...and the kernel takes it back by registering its own again', $render( '[title /template/page-home/hero/title]' ) === '<h3 class="nino-section-title">Hi &#91;there]</h3>' );

// The words a bool attribute is read by are the two tables of the class, which
// \Nino\Feature::flag() reads a flag of a shortcode without a schema by as well
\Nino\Modules\Components::addComponent( $appData, 'wordprobe', static fn( array &$appData, array $args ): string => $args['wide'], [ 'label' => 'Probe', 'source' => 'none', 'group' => 'global', 'attributes' => [ 'wide' => [ 'type' => 'bool', 'default' => false ] ] ] );
\Nino\Modules\Components::addComponent( $appData, 'worddefault', static fn( array &$appData, array $args ): string => $args['wide'], [ 'label' => 'Probe', 'source' => 'none', 'group' => 'global', 'attributes' => [ 'wide' => [ 'type' => 'bool', 'default' => true ] ] ] );
check( 'every word of ON reads as on and every word of OFF as off, whatever the case of its letters', array_filter( \Nino\Modules\Components::ON, static fn( string $word ): bool => $render( '[worddefault wide="'. $word. '"]' ) !== '1' || $render( '[wordprobe wide="'. strtoupper( $word ). '"]' ) !== '1' ) === []
	&& array_filter( \Nino\Modules\Components::OFF, static fn( string $word ): bool => $render( '[wordprobe wide="'. $word. '"]' ) !== '0' || $render( '[worddefault wide="'. strtoupper( $word ). '"]' ) !== '0' ) === [] );
check( '...no word is in both tables, and any other word is the default', array_intersect( \Nino\Modules\Components::ON, \Nino\Modules\Components::OFF ) === [] && $render( '[wordprobe wide="maybe"]' ) === '0' && $render( '[worddefault wide="maybe"]' ) === '1' );
check( '...a word with a space around it is not one: what the Builder writes is read as it is written, and \Nino\Feature::flag() is the one that trims', $render( '[wordprobe wide=" yes "]' ) === '0' && $render( '[worddefault wide=" no "]' ) === '1' );

// The four stacks of the kernel
check( '[stack] draws a nested grid row, one cell to an element, in the widths of cols, with the gap as a class of the row',
	$render( '[stack /services sort="title" cols="100 50" gap="4" id="svc"][title title tag="h4"][/stack]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-4" id="svc">'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><h4 class="nino-section-title">App</h4></div>'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><h4 class="nino-section-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h4></div></div>' );
// The cell of a stack is the div of the grid, and a card is a component in its content - a property of the content, written once in the template
check( '[stack] draws a div to the cell and the card in it: the stack brings the grid, the content brings the card',
	$render( '[stack /services sort="title" cols="100 50" gap="4" id="svc"][card ground="alt"][title title tag="h4"][/card][/stack]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-4" id="svc">'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--alt"><div class="nino-card-content"><h4 class="nino-section-title">App</h4></div></article></div>'
		. '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33"><article class="nino-card nino-card--alt"><div class="nino-card-content"><h4 class="nino-section-title">Web &lt;b&gt;&#91;x]&lt;/b&gt;</h4></div></article></div></div>' );
check( '...a stack has no attribute for what a cell looks like: card="..." is ignored as any attribute that is not declared is, and the cell stays a div whatever the shortcode says',
	array_filter( [ 'card', 'counter', 'alt', 'tint', 'dark', 'black', 'primary', 'brand-alt', 'weird', '' ], static fn( string $word ): bool =>
		$render( '[stack /services limit="1" card="'. $word. '"][probe][/stack]' ) !== $render( '[stack /services limit="1"][probe][/stack]' )
		|| $render( '[filter /services limit="1" card="'. $word. '"][probe][/filter]' ) !== $render( '[filter /services limit="1"][probe][/filter]' ) ) === []
	&& str_contains( $render( '[stack /services limit="1" card="alt"][probe][/stack]' ), '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33">' ) === true
	&& str_contains( $render( '[stack /services limit="1" card="alt"][probe][/stack]' ), 'nino-card' ) === false );
check( '...the autoheight group is an attribute of the div, and the id and the gap stay on the row',
	str_contains( $render( '[stack /services limit="1" autoheight="1" id="svc"][card ground="dark"][probe][/card][/stack]' ), '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33 nino-autoheight" data-autoheight-group="svc"><article class="nino-card nino-card--dark"><div class="nino-card-content">' ) === true
	&& str_starts_with( $render( '[stack /services limit="1" gap="3" id="svc"][card ground="dark"][probe][/card][/stack]' ), '<div class="nino-grid-row nino-stack nino-stack-gap-3" id="svc"><div class="nino-grid-s-100' ) );
$cardFilter = $render( '[filter /services sort="title" by="category" id="f"][card ground="counter"][probe][/card][/filter]' );
check( '[filter] draws the same: the cell is the div that carries the class and the value the filter needs, and the card is in it',
	str_contains( $cardFilter, '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33 nino-filter-item" data-filter-item="app"><article class="nino-card nino-card--counter"><div class="nino-card-content">inside:/services#0#/services/app#string</div></article></div>' ) === true
	&& str_contains( $cardFilter, '<div class="nino-grid-s-100 nino-grid-m-50 nino-grid-l-33 nino-filter-item" data-filter-item="web"><article class="nino-card nino-card--counter"><div class="nino-card-content">inside:/services#1#/services/web#string</div></article></div>' ) === true
	&& substr_count( $cardFilter, '<article' ) === 2 && substr_count( $cardFilter, '</article>' ) === 2 );
check( '[slider] and [list] draw what they drew, and a card is as much a content in them as in a stack',
	$render( '[slider /services sort="title" card="alt"][probe][/slider]' ) === $render( '[slider /services sort="title"][probe][/slider]' )
	&& $render( '[list /services sort="title" card="alt"][probe][/list]' ) === $render( '[list /services sort="title"][probe][/list]' )
	&& str_contains( $render( '[slider /services sort="title" limit="1"][card][probe][/card][/slider]' ), '<li><article class="nino-card"><div class="nino-card-content">inside:/services#0#/services/app#string</div></article></li>' ) === true
	&& str_contains( $render( '[list /services sort="title" limit="1"][card][probe][/card][/list]' ), '<li><article class="nino-card"><div class="nino-card-content">inside:/services#0#/services/app#string</div></article></li>' ) === true );
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

// A loop as a table and a line of fields: [table] with a [col] for each column, and [line]
echo "\nModules\\Components - a loop as a table: [table], [col] and [line]\n";

\Nino\Elements::insertElementType( $appData, '/prices', [
	'title'			=> [ 'type' => 'string', 'locale' => true ],
	'price'			=> [ 'type' => 'double' ],
	'since'			=> [ 'type' => 'date' ],
	'live'			=> [ 'type' => 'boolean' ],
	'note'			=> [ 'type' => 'string' ],
	'photo'			=> [ 'type' => 'image', 'width' => 400, 'height' => 300, 'alt' => 'photoAlt' ],
	'photoAlt'	=> [ 'type' => 'string', 'locale' => true ],
] );
\Nino\Elements::insertElement( $appData, '/prices/a', [ 'title' => 'Alpha <i>&"', 'price' => 12.5, 'since' => '2026-01-05', 'live' => true, 'note' => 'x [[title]] <b>', 'photo' => 'prices/a/photo.webp', 'photoAlt' => 'Alt "p"' ], 'de_DE' );
\Nino\Elements::insertElement( $appData, '/prices/b', [ 'title' => 'Beta', 'price' => 3, 'since' => '2025-12-31', 'live' => false, 'note' => '' ], 'de_DE' );
\Nino\Html::addFills( $appData, [ '/template/page-home/prices/head' => 'Price list', '/template/page-home/prices/unit' => 'EUR &amp; &quot;more&quot; &#91;1&#93;', '/template/page-home/prices/markup' => '<b>Bold</b> & "q"' ], '*' );

$tableStack = \Nino\Modules\Components::stacks( $appData )['table'];
check( 'the stack table is registered with the style as its one attribute, no grid, the preview table and the place table as what it holds - and the style is a select of none, striped, bordered and both',
	$tableStack['grid'] === false && $tableStack['preview'] === 'table' && $tableStack['holds'] === 'table' && array_keys( $tableStack['attributes'] ) === [ 'style' ]
	&& $tableStack['attributes']['style']['type'] === 'select' && $tableStack['attributes']['style']['options'] === [ '', 'striped', 'bordered', 'striped-bordered' ]
	&& in_array( 'table', \Nino\Modules\Components::PREVIEWS, true ) === true && in_array( 'table', \Nino\Modules\Components::PLACES, true ) === true );
$colSchema = \Nino\Modules\Components::components( $appData )['col'];
$lineSchema = \Nino\Modules\Components::components( $appData )['line'];
check( '...[col] stands in a table and nowhere else, has no source, a field and an alignment and is a global component; [line] stands wherever [text] does, takes a text as [text] does and has a tag of p, span or div',
	$colSchema['in'] === [ 'table' ] && $colSchema['source'] === 'none' && array_keys( $colSchema['attributes'] ) === [ 'field', 'align' ] && $colSchema['attributes']['align']['options'] === [ '', 'right', 'center' ]
	&& $colSchema['group'] === 'global' && array_key_exists( 'holds', $colSchema ) === false
	&& array_key_exists( 'in', $lineSchema ) === false && array_key_exists( 'in', \Nino\Modules\Components::components( $appData )['text'] ) === false
	&& $lineSchema['source'] === 'text' && $lineSchema['attributes']['tag']['options'] === [ 'p', 'span', 'div' ] && $lineSchema['attributes']['tag']['default'] === 'p' && $lineSchema['group'] === 'global' );
check( '...validate() takes the place table for what a component is in and refuses the places there are none of',
	( \Nino\Modules\Components::validate( 'probecol', [ 'label' => 'x', 'source' => 'none', 'group' => 'global', 'in' => [ 'table' ] ] )['in'] ?? null ) === [ 'table' ]
	&& str_contains( (string) \Nino\Modules\Components::validate( 'probecol', [ 'label' => 'x', 'source' => 'none', 'group' => 'global', 'in' => [ 'row' ] ] ), '"in" lists only section, card, hero, table' ) === true );

$tableCols = '[col /template/page-home/prices/head field="title"][col field="price" align="right"][col field="since"]';
check( '[table] is the wrap, the table, a header row of th and a body row of td for each element - in the order of the loop, with the classes of the style and the id and the class of the call',
	$render( '[table /prices sort="title" style="striped-bordered" id="pl" class="x"]'. $tableCols. '[/table]' ) === '<div class="nino-table-wrap"><table class="nino-table nino-table--striped nino-table--bordered x" id="pl">'
		. '<thead><tr><th>Price list</th><th></th><th></th></tr></thead><tbody>'
		. '<tr><td>Alpha &lt;i&gt;&amp;&quot;</td><td class="nino-table__cell--right">12.5</td><td>2026-01-05</td></tr>'
		. '<tr><td>Beta</td><td class="nino-table__cell--right">3</td><td>2025-12-31</td></tr></tbody></table></div>'
	&& $render( '[table /prices sort="-title" limit="1"][col field="title"][/table]' ) === '<div class="nino-table-wrap"><table class="nino-table"><tbody><tr><td>Beta</td></tr></tbody></table></div>' );
check( '...the style is none, striped, bordered or both and any other word is none',
	array_map( static fn( string $style ): string => (string) preg_match( '/<table class="([^"]*)"/', $render( '[table /prices style="'. $style. '"][col field="title"][/table]' ), $found ) . $found[1], [ '', 'striped', 'bordered', 'striped-bordered', 'weird' ] )
		=== [ '1nino-table', '1nino-table nino-table--striped', '1nino-table nino-table--bordered', '1nino-table nino-table--striped nino-table--bordered', '1nino-table' ] );
check( '...the header row is written when one column has a title and not otherwise, a column with none has an empty th; the number of th and of td in a row is the number of columns',
	str_contains( $render( '[table /prices][col field="title"][/table]' ), '<thead>' ) === false
	&& str_contains( $render( '[table /prices][col field="title"][col /template/page-home/prices/nokey field="price"][/table]' ), '<thead>' ) === false
	&& str_contains( $render( '[table /prices][col field="title"][col /template/page-home/prices/head field="price"][/table]' ), '<thead><tr><th></th><th>Price list</th></tr></thead>' ) === true
	&& substr_count( $render( '[table /prices][col field="title"][col field="price"][col][/table]' ), '<td' ) === 6
	&& substr_count( $render( '[table /prices][col field="title"][col field="price"][col][/table]' ), '<tr>' ) === 2 );
check( '...the title of a column is text in the th: a key whose text holds markup, an ampersand and quotes is written escaped',
	str_contains( $render( '[table /prices limit="1"][col /template/page-home/prices/markup field="title"][/table]' ), '<thead><tr><th>&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;q&quot;</th></tr></thead>' ) === true );
check( '...the loop is the loop of every stack: the query and the offset are honoured, an empty loop and a table with no column are nothing, and what stands between the tags besides the columns is not drawn',
	$render( '[table /prices sort="title" query="live=0"][col field="title"][/table]' ) === '<div class="nino-table-wrap"><table class="nino-table"><tbody><tr><td>Beta</td></tr></tbody></table></div>'
	&& $render( '[table /prices sort="title" offset="1"][col field="title"][/table]' ) === '<div class="nino-table-wrap"><table class="nino-table"><tbody><tr><td>Beta</td></tr></tbody></table></div>'
	&& $render( '[table /prices query="title=Nobody"][col field="title"][/table]' ) === ''
	&& $render( '[table /prices][/table]' ) === '' && $render( '[table /prices]just text[/table]' ) === ''
	&& $render( '[table /prices limit="1"]stray [title /template/page-home/prices/head][col field="title"]more[/table]' ) === '<div class="nino-table-wrap"><table class="nino-table"><tbody><tr><td>Alpha &lt;i&gt;&amp;&quot;</td></tr></tbody></table></div>' );
$cellOf = static fn( string $col, string $element = 'Alpha' ): string => (string) preg_replace( '#^.*?<tr><td[^>]*>(.*?)</td></tr>.*$#s', '$1', $render( '[table /prices sort="title" '. ( $element === 'Beta' ? 'offset="1" ' : '' ). 'limit="1"]'. $col. '[/table]' ) );
check( 'a cell shows a field as [text] shows it: a string escaped, a number as it is, a date and a boolean as the kernel writes them - the same characters [text] puts in a stack',
	$cellOf( '[col field="title"]' ) === 'Alpha &lt;i&gt;&amp;&quot;' && $cellOf( '[col field="price"]' ) === '12.5' && $cellOf( '[col field="price"]', 'Beta' ) === '3'
	&& $cellOf( '[col field="since"]' ) === '2026-01-05' && $cellOf( '[col field="live"]' ) === '1' && $cellOf( '[col field="live"]', 'Beta' ) === ''
	&& array_filter( [ 'title', 'price', 'since', 'live', 'note' ], static fn( string $field ): bool => str_contains( $render( '[stack /prices sort="title" limit="1"][text '. $field. ' format="inline"][/stack]' ), '>'. $cellOf( '[col field="'. $field. '"]' ). '<' ) === false ) === []
	&& $cellOf( '[col field="note"]' ) === 'x &#91;&#91;title]] &lt;b&gt;' && $cellOf( '[col field="nothere"]' ) === '' && $cellOf( '[col]' ) === '' );
check( '...an image field is the picture as [image] draws it, in the class nino-table__img and with the alt text the type names for it; an element that has no picture has an empty cell',
	$cellOf( '[col field="photo"]' ) === '<div class="nino-image nino-table__img"><img src="/public/images/prices/a/photo.webp" width="400" height="300" alt="Alt &quot;p&quot;"></div>'
	&& $cellOf( '[col field="photo"]', 'Beta' ) === '' );
check( '...a text is the cell instead of the field, with the fields of the element in it: filled escaped, the characters of the text itself escaped, a [[name]] of no field kept as it was written, a site text filled',
	$cellOf( '[col text="[[price]],- EUR"]' ) === '12.5,- EUR'
	&& $cellOf( '[col field="title" text="[[title]] / [[price]]"]' ) === 'Alpha &lt;i&gt;&amp;&quot; / 12.5'
	&& $cellOf( '[col text="a < b & c"]' ) === 'a &lt; b &amp; c'
	&& $cellOf( '[col text="[[note]]"]' ) === 'x &#91;&#91;title]] &lt;b&gt;'
	&& $cellOf( '[col text="[[nope]] and [[.slug]]"]' ) === '&#91;&#91;nope]] and a'
	&& $cellOf( '[col text="[[/template/page-home/prices/unit]]"]' ) === 'EUR &amp; &quot;more&quot; &#91;1]'
	&& $cellOf( '[col text="[[/template/page-home/prices/gone]]!"]' ) === '&#91;&#91;/template/page-home/prices/gone]]!'
	&& $cellOf( '[col text=""]' ) === '' );
check( '...a field that is markup is text in the cell, the free text is never read as markup or as a shortcode, and a value holding a fill or a bracket is not filled again',
	$cellOf( '[col text="<b>[[title]]</b> {{x}}"]' ) === '&lt;b&gt;Alpha &lt;i&gt;&amp;&quot;&lt;/b&gt; {{x}}'
	&& $cellOf( '[col text="&lt;i&gt;"]' ) === '&lt;i&gt;'
	&& str_contains( $render( '[table /prices][col text="[[note]]"][/table]' ), '<b>' ) === false );
check( '...text wins where a column has both a text and a field, the alignment is a class of the cell and a word that is none is none, and a cell stands in a table and nowhere else',
	$cellOf( '[col field="title" text="T"]' ) === 'T'
	&& str_contains( $render( '[table /prices][col field="title" align="center"][/table]' ), '<td class="nino-table__cell--center">' ) === true
	&& str_contains( $render( '[table /prices][col field="title" align="left"][/table]' ), '<td>' ) === true
	&& $render( '[col field="title"]' ) === '' && $render( '[stack /prices][col field="title"][/stack]' ) === $render( '[stack /prices][/stack]' )
	&& $render( '[table /prices limit="1"][col field="title"][/table]|[col field="title"]' ) === '<div class="nino-table-wrap"><table class="nino-table"><tbody><tr><td>Alpha &lt;i&gt;&amp;&quot;</td></tr></tbody></table></div>|' );

$lineOf = static fn( string $line ): string => $render( '[stack /prices sort="title" limit="1" cols="100"]'. $line. '[/stack]' );
check( '[line] is a p of the class nino-line with the text of the call - span and div on the tag, the class of the call after its own - and the fields of the element filled in',
	$lineOf( '[line text="[[price]],- EUR"]' ) === '<div class="nino-grid-row nino-stack nino-stack-gap-2"><div class="nino-grid-s-100 nino-grid-m-100 nino-grid-l-100"><p class="nino-line">12.5,- EUR</p></div></div>'
	&& str_contains( $lineOf( '[line text="[[title]]" tag="span" class="x"]' ), '<span class="nino-line x">Alpha &lt;i&gt;&amp;&quot;</span>' ) === true
	&& str_contains( $lineOf( '[line text="a" tag="div"]' ), '<div class="nino-line">a</div>' ) === true
	&& str_contains( $lineOf( '[line text="a" tag="h1"]' ), '<p class="nino-line">a</p>' ) === true );
check( '...the fields of the element are filled escaped and a [[name]] of no field stays as it is; the text itself is escaped, a site text is filled, and the same code serves a column',
	str_contains( $lineOf( '[line text="<b>[[note]]</b>"]' ), '<p class="nino-line">&lt;b&gt;x &#91;&#91;title]] &lt;b&gt;&lt;/b&gt;</p>' ) === true
	&& str_contains( $lineOf( '[line text="[[nope]]|[[.id]]|[[since]]"]' ), '<p class="nino-line">&#91;&#91;nope]]|0|2026-01-05</p>' ) === true
	&& str_contains( $lineOf( '[line text="[[/template/page-home/prices/head]]: [[title]]"]' ), '<p class="nino-line">Price list: Alpha &lt;i&gt;&amp;&quot;</p>' ) === true
	&& str_contains( $lineOf( '[line text="[[price]] / [[/template/page-home/prices/unit]] <i>"]' ), '<p class="nino-line">12.5 / EUR &amp; &quot;more&quot; &#91;1] &lt;i&gt;</p>' ) === true
	&& $cellOf( '[col text="[[price]] / [[/template/page-home/prices/unit]] <i>"]' ) === '12.5 / EUR &amp; &quot;more&quot; &#91;1] &lt;i&gt;' );
check( '...outside a loop it is its text with the fills only: the site texts are in, a field means nothing and the line is nothing where it comes out empty; with no text it shows its first argument as [text] does',
	$render( '[line text="Cost: [[/template/page-home/prices/head]] <b>"]' ) === '<p class="nino-line">Cost: Price list &lt;b&gt;</p>'
	&& $render( '[line text=""]' ) === '' && $render( '[line /template/page-home/prices/head]' ) === '<p class="nino-line">Price list</p>' && $render( '[line /template/page-home/prices/gone]' ) === ''
	&& str_contains( $lineOf( '[line price]' ), '<p class="nino-line">12.5</p>' ) === true );
check( '...it is the row of a list: one item to an element, the line in it',
	$render( '[list /prices sort="title" style="check"][line text="[[title]]: [[price]]"][/list]' ) === '<ul class="nino-list nino-list--check"><li><p class="nino-line">Alpha &lt;i&gt;&amp;&quot;: 12.5</p></li><li><p class="nino-line">Beta: 3</p></li></ul>'
	&& str_contains( $render( '[stack /prices limit="1"][card][line text="[[price]]"][/card][/stack]' ), '<div class="nino-card-content"><p class="nino-line">12.5</p></div>' ) === true );
$appData[\Nino\Modules\Components::CONTEXT] = [ 'uri' => '/prices', 'id' => 0, 'element' => [ 'title' => 'T<b>', 'price' => 2, 'tags' => [ 'a' ], 'photo' => 'f.webp' ], 'model' => [ 'title' => [], 'price' => [], 'tags' => [], 'photo' => [ 'type' => 'image' ] ] ];
$directLine = ( static fn( array $args ): string => (string) \Nino\Callbacks::doCallbacks( $appData, '/nino/html/shortcode/line', $args ) )(  [ 'text' => '[[title]]|[[price]]|[[nope]]|[[tags]]|[[photo]]|[[/template/page-home/prices/unit]]|[[/template/page-home/prices/gone]]|<i>' ] );
$directEmpty = ( static fn( array $args ): string => (string) \Nino\Callbacks::doCallbacks( $appData, '/nino/html/shortcode/line', $args ) )( [ 'text' => '[[photo]]' ] );
unset( $appData[\Nino\Modules\Components::CONTEXT] );
check( '...a call that comes from elsewhere than a page - a tool calling the shortcode itself - is filled by the line alone: the fields of the element escaped, the site texts of the language, and whatever is no fill of either - a field there is none of, a key nobody wrote, a field that holds no scalar - as it was written, escaped',
	$directLine === '<p class="nino-line">T&lt;b&gt;|2|&#91;&#91;nope]]|&#91;&#91;tags]]||EUR &amp; &quot;more&quot; &#91;1]|&#91;&#91;/template/page-home/prices/gone]]|&lt;i&gt;</p>' );
check( 'the demo page shows a loop as a table - the striped table of three columns, a header row and three rows of three cells - and a list whose row is a line of the class nino-line',
	preg_match( '#<section id="tabelle-loop-columns".*?<table class="nino-table nino-table--striped">\s*<thead>\s*<tr>(?:<th>[^<]*</th>){3}</tr>\s*</thead>\s*<tbody>((?:\s*<tr>(?:<td>[^<]*</td>){3}</tr>){3})\s*</tbody>#s', $demoPage ) === 1
	&& preg_match( '#<p class="nino-section-subtitle">A &\#91;table&\#93; with a &\#91;col&\#93; for each column#', $demoPage ) === 1 && str_contains( $demoPage, '<p class="nino-section-subtitle">A [table]' ) === false
	&& preg_match( '#<section id="liste-loop-lines".*?<ul class="nino-list nino-list--check">(?:\s*<li><p class="nino-line">[^<]*</p></li>){3}\s*</ul>#s', $demoPage ) === 1 );
check( '...an image field prints nothing in a line or a cell - the path of a file is no text - and a line that comes out empty is nothing, no empty p',
	$lineOf( '[line text="[[photo]]"]' ) === $render( '[stack /prices sort="title" limit="1" cols="100"][/stack]' )
	&& str_contains( $lineOf( '[line text="a [[photo]]b"]' ), '<p class="nino-line">a b</p>' ) === true && str_contains( $lineOf( '[line text="[[photo]]"]' ), 'nino-line' ) === false
	&& $cellOf( '[col text="x[[photo]]y"]' ) === 'xy' && str_contains( $cellOf( '[col text="[[photo]]"]' ), 'photo' ) === false
	&& str_contains( $directLine, 'photo' ) === false && $directEmpty === '' );
check( 'Nino.css has the three rules a table needs besides the ones it has: the cell aligned right, the cell aligned centre, and a picture in a cell 6rem wide, which a table that scrolls does not squeeze to nothing',
	$hookRules( '.nino-table__cell--right' ) !== '' && $hookValue( $hookRules( '.nino-table__cell--right' ), 'text-align' ) === 'right'
	&& $hookValue( $hookRules( '.nino-table__cell--center' ), 'text-align' ) === 'center'
	&& $hookValue( $hookRules( '.nino-table__img' ), 'width' ) === '6rem' && $hookValue( $hookRules( '.nino-table__img' ), 'height' ) === 'auto' );

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
check( '...the section, the row and the columns are the markup of the file', str_contains( $page, '<section id="hero" class="nino-section nino-section--black nino-cover nino-cover--dim nino-vpa" data-cover-height="100">' ) === true
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
	$render( '[spacer class="x"]' ) === '<div class="nino-spacer nino-spacer-2 x"></div>'
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

// --- The options of an attribute that a method of the feature answers --------------

echo "Modules\\Components - the options of a select may be the name of a method of the feature\n";

require_once __DIR__. '/fixtures/component-options/Picker/Picker.php';

$pick = ninoSandbox( 'components-options' );
$pick['/nino/dir'] = '';
\Nino\Modules\Components::init( $pick );
ninoWarnings();

// Registered the way a feature's manifest is: read by Features::manifest(), then each schema as it was read
$pickManifest = \Nino\Features::manifest( __DIR__. '/fixtures/component-options/Picker' );
foreach( [ 'components' => 'addComponent', 'stacks' => 'addStack' ] as $pickKind => $pickAdd )
	foreach( (array) ( $pickManifest[$pickKind] ?? [] ) as $pickName => $pickSchema )
		\Nino\Modules\Components::$pickAdd( $pick, (string) $pickName, [ \Nino\Modules\Picker::class, ( $pickKind === 'stacks' ? 'stack' : 'component' ). str_replace( ' ', '', ucwords( str_replace( '-', ' ', (string) $pickName ) ) ) ], $pickSchema );
$pickRegistered = ninoWarnings();
$pickRender = static function( string $html ) use ( &$pick ): string {
	return \Nino\Html::renderHtml( $pick, $html );
};

check( 'a manifest whose select names a method is read without a warning and registers its components and its stack: nothing is asked while it is',
	is_array( $pickManifest ) === true && $pickRegistered === [] && isset( $pick[\Nino\Modules\Components::REGISTRY]['picker-form'], $pick[\Nino\Modules\Components::REGISTRY]['picker-broken'], $pick[\Nino\Modules\Components::STACKS]['picker-rows'] )
	&& \Nino\Modules\Picker::$asked === []
	&& $pick[\Nino\Modules\Components::REGISTRY]['picker-form']['attributes']['form']['options'] === '\Nino\Modules\Picker::forms' );
check( '...and neither the defaults nor the question whether a shortcode is the module\'s ask it: the default is the string it was written as',
	\Nino\Modules\Components::defaults( $pick, 'picker-form' ) === [ 'form' => '', 'tone' => '', 'size' => '', 'class' => '' ]
	&& \Nino\Feature::shortcode( $pick, 'picker-form', static fn( array &$appData, array $args ): string => '' ) === false
	&& \Nino\Modules\Picker::$asked === [] );

// The first render asks, and a value of the list is the value, one the list lacks is the default
check( 'a value that is in the list the method answers renders, one that is not falls back to the default - and the first render is the first time it is asked, with the name of the attribute',
	$pickRender( '[picker-form form="contact"]' ) === '<p class="picker-form" data-form="contact"></p>'
	&& $pickRender( '[picker-form form="nope"]' ) === '<p class="picker-form" data-form=""></p>'
	&& $pickRender( '[picker-form]' ) === '<p class="picker-form" data-form=""></p>'
	&& $pickRender( '[picker-form form="news"]' ) === '<p class="picker-form" data-form="news"></p>'
	&& \Nino\Modules\Picker::$asked === [ 'forms' => 1, 'formsFor' => 'form' ] );

// The schema that is handed out carries the list
$pickIssued = \Nino\Modules\Components::components( $pick )['picker-form']['attributes'];
check( 'the schema a tool is handed carries the list the method answered, as the values and, value by value, the label: a string or a locale map that localized() reads',
	$pickIssued['form']['type'] === 'select' && $pickIssued['form']['options'] === [ '', 'contact', 'news' ] && $pickIssued['form']['default'] === ''
	&& array_keys( $pickIssued['form']['optionLabels'] ) === [ '', 'contact', 'news' ]
	&& \Nino\Features::localized( $pickIssued['form']['optionLabels'][''], 'de_DE' ) === 'Keines' && \Nino\Features::localized( $pickIssued['form']['optionLabels']['news'], 'de_DE' ) === 'Rundbrief'
	&& \Nino\Features::localized( $pickIssued['form']['optionLabels']['news'], 'fr_FR' ) === 'Newsletter' && \Nino\Features::localized( $pickIssued['form']['optionLabels']['contact'], 'de_DE' ) === 'Contact'
	&& str_contains( json_encode( \Nino\Modules\Components::components( $pick ) ), 'Picker' ) === false );
check( '...a list of its own stays what it was, with no labels, and a variant\'s words are checked and carry theirs',
	$pickIssued['size']['options'] === [ '', 'big' ] && array_key_exists( 'optionLabels', $pickIssued['size'] ) === false
	&& $pickIssued['tone']['type'] === 'variant' && $pickIssued['tone']['options'] === [ '', 'loud', 'soft-grey' ] && $pickIssued['tone']['prefix'] === 'picker-form--'
	&& $pickRender( '[picker-form tone="soft-grey"]' ) === '<p class="picker-form picker-form--soft-grey" data-form=""></p>' && $pickRender( '[picker-form tone="other"]' ) === '<p class="picker-form" data-form=""></p>' );

// Once for each attribute in a request, however often the schema is handed out and the shortcode rendered
for( $pickCount = 0; $pickCount < 20; $pickCount++ ) {
	\Nino\Modules\Components::components( $pick );
	$pickRender( '[picker-form form="contact"]' );
}
check( 'the method is asked once in a request: the schema handed out a score of times and a page of twenty shortcodes ask it no more',
	\Nino\Modules\Picker::$asked['forms'] === 1 );

// A stack takes the same
$pickStack = \Nino\Modules\Components::stacks( $pick )['picker-rows']['attributes']['form'];
check( 'a stack\'s attribute takes its options from a method as a component\'s does: the list in the schema, the default for a value outside it - asked once more, for the stack',
	$pickStack['options'] === [ '', 'contact', 'news' ] && \Nino\Features::localized( $pickStack['optionLabels']['news'], 'en_US' ) === 'Newsletter'
	&& $pickRender( '[picker-rows /nothing form="news"][/picker-rows]' ) === '<div class="picker-rows" data-form="news"></div>' && $pickRender( '[picker-rows /nothing form="nope"][/picker-rows]' ) === '<div class="picker-rows" data-form=""></div>'
	&& \Nino\Modules\Picker::$asked['forms'] === 2 );

// A method that answers nothing that can be read
$pickBroken = \Nino\Modules\Components::components( $pick )['picker-broken']['attributes'];
$pickWarned = ninoWarnings();
check( 'a method that does not answer an array is a warning and the attribute a string: no options, no input, the default kept - and what a shortcode says goes through',
	$pickBroken['flat']['type'] === 'string' && array_key_exists( 'options', $pickBroken['flat'] ) === false && array_key_exists( 'input', $pickBroken['flat'] ) === false && $pickBroken['flat']['default'] === 'a'
	&& count( array_filter( $pickWarned, static fn( string $warning ): bool => str_contains( $warning, 'component "picker-broken", attribute "flat": \Nino\Modules\Picker::flat() did not answer an array' ) && str_contains( $warning, 'treated as a string' ) ) ) === 1
	&& $pickRender( '[picker-broken flat="anything at all"]' ) === '<p class="picker-broken" data-flat="anything at all" data-thrown="a" data-label="a" data-word=""></p>' );
check( '...so is one that throws, one whose label is no string or map, and a variant\'s answer that is no word - each a warning that names the attribute, and a string',
	$pickBroken['thrown']['type'] === 'string' && $pickBroken['label']['type'] === 'string' && $pickBroken['word']['type'] === 'string' && array_key_exists( 'prefix', $pickBroken['word'] ) === false
	&& count( array_filter( $pickWarned, static fn( string $warning ): bool => str_contains( $warning, 'attribute "thrown": \Nino\Modules\Picker::thrown() threw RuntimeException: no forms today' ) ) ) === 1
	&& count( array_filter( $pickWarned, static fn( string $warning ): bool => str_contains( $warning, 'attribute "label": \Nino\Modules\Picker::unlabelled() answers no string or locale => string map as the label of "b"' ) ) ) === 1
	&& count( array_filter( $pickWarned, static fn( string $warning ): bool => str_contains( $warning, 'attribute "word": \Nino\Modules\Picker::notWords() answers "Loud Word", which is no word of a variant' ) ) ) === 1
	&& $pickRender( '[picker-broken thrown="x" label="y" word="z"]' ) === '<p class="picker-broken" data-flat="a" data-thrown="x" data-label="y" data-word="z"></p>' );
check( '...and it is said once in a request, not for every shortcode and every time the schema is handed out; the method that threw was asked once',
	ninoWarnings() === [] && count( $pickWarned ) === 4 && \Nino\Modules\Picker::$asked['thrown'] === 1 && \Nino\Modules\Picker::$asked['flat'] === 1 );
check( 'a method that asks for the registry itself is not asked again by it: it is asked once, and its answer is the list',
	\Nino\Modules\Picker::$asked['again'] === 1 && $pickBroken['again']['type'] === 'select' && $pickBroken['again']['options'] === [ '', 'x' ] );

// Another method under the same name is asked again
$pickAgain = $pick;
$pickAgain[\Nino\Modules\Components::REGISTRY]['picker-form']['attributes']['form']['options'] = '\Nino\Modules\Picker::tones';
check( 'the list is kept for the method that was asked: a registry that names another method for the attribute asks that one',
	\Nino\Modules\Components::components( $pickAgain )['picker-form']['attributes']['form']['options'] === [ '', 'loud', 'soft-grey' ] && $pickIssued['form']['options'] === [ '', 'contact', 'news' ] );

// What is refused when the manifest is read: on paper alone, nothing is loaded. A directory name stands for the class a feature brings
$pickDir = ninoSandboxDir( $pick ). '/refused';
$pickWrite = static function( string $name, array $attribute, bool $withClass = true ) use ( $pickDir ): string {

	// '@' is the method of the feature's own class, whatever its directory is called
	if( ( $attribute['options'] ?? null ) === '@' )
		$attribute['options'] = '\Nino\Modules\\'. $name. '::forms';

	@mkdir( $pickDir. '/'. $name, 0755, true );
	file_put_contents( $pickDir. '/'. $name. '/feature.php', '<?php return '. var_export( [ 'name' => $name, 'version' => '1.0.0', 'components' => [ 'probe' => [ 'label' => 'Probe', 'source' => 'none', 'attributes' => [ 'which' => $attribute ] ] ] ], true ). ';' );
	file_put_contents( $pickDir. '/'. $name. '/'. $name. '.php', '<?php' );
	ninoWarnings();

	return $pickDir. '/'. $name;
};
$pickRefused = static function( string $name, array $attribute ) use ( $pickWrite ): string {

	$manifest = \Nino\Features::manifest( $pickWrite( $name, $attribute ) );
	$warnings = ninoWarnings();

	return $manifest === null && count( $warnings ) === 1 ? $warnings[0] : 'accepted or said otherwise: '. implode( ' | ', $warnings );
};
$pickSelect = [ 'type' => 'select', 'default' => '' ];
$pickWhere = 'component "probe", attribute "which": ';

// The class of this manifest is nowhere loaded and the directory is none of the features directory's: a staging directory, a feature that is off, the previous version of the class loaded
$pickLazy = \Nino\Features::manifest( $pickWrite( 'Lazy', $pickSelect + [ 'options' => '@' ] ) );
$pickLazyWarned = ninoWarnings();
check( 'a manifest that names a method of its own class is accepted from a directory outside features/ with the class not loaded - and nothing is loaded to read it',
	is_array( $pickLazy ) === true && $pickLazyWarned === [] && class_exists( 'Nino\Modules\Lazy', false ) === false
	&& $pickLazy['components']['probe']['attributes']['which']['options'] === '\Nino\Modules\Lazy::forms'
	&& is_array( \Nino\Features::manifest( $pickWrite( 'Nested', $pickSelect + [ 'options' => 'Nino\Modules\Nested\Lists\Source::forms' ] ) ) ) === false
	&& str_contains( ninoWarnings()[0] ?? '', 'whose file is not in the feature' ) );
@mkdir( $pickDir. '/Nested/Lists/Source', 0755, true );
file_put_contents( $pickDir. '/Nested/Lists/Source/Source.php', '<?php' );
check( '...a class below the feature\'s own is looked for where the autoloader looks for it, and is accepted when its file is there',
	is_array( \Nino\Features::manifest( $pickWrite( 'Nested', $pickSelect + [ 'options' => 'Nino\Modules\Nested\Lists\Source::forms' ] ) ) ) === true && ninoWarnings() === [] );
check( 'a method of a class outside \Nino\Modules\<Directory> is refused when the manifest is read - the kernel\'s static methods are not for a schema to ask - and so is a class whose file the feature lacks',
	str_contains( $pickRefused( 'Outside', $pickSelect + [ 'options' => '\Nino\Modules\NoSuchClass::forms' ] ), $pickWhere. '"options" names a static method of the feature\'s own class, \Nino\Modules\Outside or below it: "\Nino\Modules\NoSuchClass::forms" is not' )
	&& str_contains( $pickRefused( 'Kernel', $pickSelect + [ 'options' => '\Nino\Filesystem::forceDir' ] ), $pickWhere. '"options" names a static method of the feature\'s own class, \Nino\Modules\Kernel or below it' )
	&& str_contains( $pickRefused( 'Sibling', $pickSelect + [ 'options' => '\Nino\Modules\SiblingExtra::forms' ] ), 'or below it: "\Nino\Modules\SiblingExtra::forms" is not' )
	&& str_contains( $pickRefused( 'NoFile', $pickSelect + [ 'options' => '\Nino\Modules\NoFile\Missing::forms' ] ), $pickWhere. '"options" names a class whose file is not in the feature: "\Nino\Modules\NoFile\Missing::forms"' ) );
check( '...so does a name that is not Class::method, and a list that is none',
	str_contains( $pickRefused( 'NoForm', $pickSelect + [ 'options' => 'forms' ] ), $pickWhere. '"options" is a list of values or the name of a static method, written \Namespace\Class::method - "forms" is none' )
	&& str_contains( $pickRefused( 'NoStatic', $pickSelect + [ 'options' => '\Nino\Modules\NoStatic::forms()' ] ), '"options" is a list of values or the name of a static method' )
	&& str_contains( $pickRefused( 'NoList', $pickSelect + [ 'options' => [] ] ), $pickWhere. 'a select needs "options", a list of values or the name of a static method' ) );
check( '...and a default that is no string, and icons, which no one can check to be an alignment before the list is asked for',
	str_contains( $pickRefused( 'IntDefault', [ 'type' => 'select', 'default' => 1, 'options' => '@' ] ), $pickWhere. 'the default of a select whose options are a method must be a string' )
	&& str_contains( $pickRefused( 'Icons', $pickSelect + [ 'options' => '@', 'input' => 'icons' ] ), $pickWhere. '"input" icons is for an alignment' ) );
check( '...a variant\'s default is a word or the empty word, as its list is',
	str_contains( $pickRefused( 'BadVariant', [ 'type' => 'variant', 'default' => 'Not A Word', 'options' => '@' ] ), $pickWhere. 'the default of a variant is a word' )
	&& is_array( \Nino\Features::manifest( $pickWrite( 'GoodVariant', [ 'type' => 'variant', 'default' => 'loud', 'options' => '@' ] ) ) ) && ninoWarnings() === [] );

// A module registers through addComponent(): there the class is checked itself
$pickFull = static fn( string $options ): string => (string) \Nino\Modules\Components::validate( 'probe', [ 'label' => 'Probe', 'source' => 'none', 'group' => 'global', 'attributes' => [ 'which' => $pickSelect + [ 'options' => $options ] ] ] );
check( 'a module\'s registration is checked as a whole: the class has to load, and the method has to be a public static one - a private method of the Components class itself is none',
	str_contains( $pickFull( '\Nino\Modules\NoSuchClass::forms' ), $pickWhere. '"options" names a method of a class that cannot be loaded: "\Nino\Modules\NoSuchClass::forms"' )
	&& str_contains( $pickFull( '\Nino\Modules\Picker::missing' ), $pickWhere. '"options" names a method that is no public static method: "\Nino\Modules\Picker::missing"' )
	&& str_contains( $pickFull( '\Nino\Modules\Components::_resolve' ), $pickWhere. '"options" names a method that is no public static method' )
	&& is_array( \Nino\Modules\Components::validate( 'probe', [ 'label' => 'Probe', 'source' => 'none', 'group' => 'global', 'attributes' => [ 'which' => $pickSelect + [ 'options' => '\Nino\Modules\Picker::forms' ] ] ] ) ) === true );
check( 'a select with a list of its own is read as before: the old refusals stay and the default has to be one of the options',
	str_contains( $pickRefused( 'OldDefault', [ 'type' => 'select', 'default' => 'z', 'options' => [ 'a', 'b' ] ] ), $pickWhere. 'the default is not one of the options' )
	&& str_contains( $pickRefused( 'OldWord', [ 'type' => 'variant', 'default' => '', 'options' => [ '', 'Big Word' ] ] ), $pickWhere. 'the options of a variant are words, and "Big Word" is none' ) );

// What the asking finds out, a manifest having passed: a class that does not load or a method that is none is a warning and a string, as an answer nobody can read is
$pickLate = $pick;
$pickLate[\Nino\Modules\Components::REGISTRY]['picker-form']['attributes']['form']['options'] = '\Nino\Modules\NoSuchClass::forms';
$pickLate[\Nino\Modules\Components::REGISTRY]['picker-form']['attributes']['tone']['options'] = '\Nino\Modules\Picker::missing';
ninoWarnings();
$pickLateAttributes = \Nino\Modules\Components::components( $pickLate )['picker-form']['attributes'];
$pickLateWarned = ninoWarnings();
check( 'a method whose class does not load, or that the class lacks, is found out when it is asked for: one warning each and a string, not a fatal error',
	$pickLateAttributes['form']['type'] === 'string' && array_key_exists( 'options', $pickLateAttributes['form'] ) === false && $pickLateAttributes['tone']['type'] === 'string' && count( $pickLateWarned ) === 2
	&& str_contains( $pickLateWarned[0], 'component "picker-form", attribute "form": "options" names a method of a class that cannot be loaded: "\Nino\Modules\NoSuchClass::forms" - treated as a string' )
	&& str_contains( $pickLateWarned[1], 'attribute "tone": "options" names a method that is no public static method: "\Nino\Modules\Picker::missing" - treated as a string' ) );
check( '...and a variant whose method could not be read keeps what a variant is: the empty word and words pass, anything else is the default - while the schema handed out says a plain string',
	$pickRender( '[picker-broken word="loud"]' ) === '<p class="picker-broken" data-flat="a" data-thrown="a" data-label="a" data-word="loud"></p>'
	&& $pickRender( '[picker-broken word="Not A Word"]' ) === '<p class="picker-broken" data-flat="a" data-thrown="a" data-label="a" data-word=""></p>'
	&& $pickRender( '[picker-broken word="x\' onclick=\'y"]' ) === '<p class="picker-broken" data-flat="a" data-thrown="a" data-label="a" data-word=""></p>'
	&& array_key_exists( 'words', $pickBroken['word'] ) === false && $pickBroken['word']['type'] === 'string' );

// registered() is the registry as it was registered
ninoWarnings();
$pickAsked = \Nino\Modules\Picker::$asked['forms'];
$pickRegistry = \Nino\Modules\Components::registered( $pick );
check( 'registered() answers the registry as it was registered, with the name of the method where the options are one, and asks nothing; the stacks are the second argument',
	$pickRegistry['picker-form']['attributes']['form']['options'] === '\Nino\Modules\Picker::forms' && array_key_exists( 'optionLabels', $pickRegistry['picker-form']['attributes']['form'] ) === false
	&& \Nino\Modules\Components::registered( $pick, true )['picker-rows']['attributes']['form']['options'] === '\Nino\Modules\Picker::forms'
	&& array_keys( $pickRegistry ) === array_keys( \Nino\Modules\Components::components( $pick ) ) && \Nino\Modules\Picker::$asked['forms'] === $pickAsked );
check( '...and what it answers registers again: a component handed back to addComponent() keeps its method',
	( static function() use ( &$pick, $pickRegistry ): bool {
		$again = ninoSandbox( 'components-options-again' );
		$again['/nino/dir'] = '';
		\Nino\Modules\Components::init( $again );
		\Nino\Modules\Components::addComponent( $again, 'picker-copy', [ \Nino\Modules\Picker::class, 'componentPickerForm' ], $pickRegistry['picker-form'] );
		$kept = \Nino\Modules\Components::registered( $again )['picker-copy']['attributes']['form']['options'] === '\Nino\Modules\Picker::forms';
		\Nino\Filesystem::removeDir( ninoSandboxDir( $again ) );
		return $kept;
	} )() === true );

// A feature that is listed is registered with the check of a module: a class that does not load is a warning and the component is left out, no fatal error on every request
$pickFeature = ninoSandbox( 'components-options-feature' );
$pickFeature['/nino/dir'] = '';
\Nino\Modules\Components::init( $pickFeature );
$pickGood = $pickManifest['components']['picker-broken'];
$pickBad = $pickManifest['components']['picker-form'];
$pickBad['attributes']['form']['options'] = '\Nino\Modules\Picker::missing';
$pickFeature['/nino/modules'] = [ '\Nino\Modules\Picker' ];
$pickFeature['./nino/features/listed'] = [ 'modules' => $pickFeature['/nino/modules'], 'manifests' => [ [ 'key' => 'picker', 'dir' => '/features/Picker', 'module' => '\Nino\Modules\Picker', 'components' => [ 'picker-form' => $pickBad, 'picker-broken' => $pickGood ], 'stacks' => [] ] ] ];
ninoWarnings();
$pickRegisterErrors = ninoErrors( static function() use ( &$pickFeature ): void {
	\Nino\Features::registerComponents( $pickFeature );
} );
check( 'a listed feature whose method is not there is registered without that component and with a warning, not a fatal error - the others of its manifest are registered',
	count( $pickRegisterErrors ) === 1 && $pickRegisterErrors[0][0] === E_USER_WARNING
	&& str_contains( $pickRegisterErrors[0][1], 'component "picker-form", attribute "form": "options" names a method that is no public static method: "\Nino\Modules\Picker::missing" - not registered' )
	&& isset( $pickFeature[\Nino\Modules\Components::REGISTRY]['picker-form'] ) === false && isset( $pickFeature[\Nino\Modules\Components::REGISTRY]['picker-broken'] ) === true );

// ...and one whose class does not even parse: loading it for the check throws, which is the same warning and not a 500 for every request. The class stands in the app root
// the autoloader reads last, a directory of the sandbox
$pickApp = ninoSandboxDir( $pickFeature ). '/app';
@mkdir( $pickApp. '/Nino/Modules/Parsebroken', 0755, true );
file_put_contents( $pickApp. '/Nino/Modules/Parsebroken/Parsebroken.php', "<?php\nnamespace Nino\\Modules { class Parsebroken { public static function forms( array &\$appData, string \$attribute ): array { return [ ; } }\n" );
defined( 'NINO_APP_DIR' ) === true || define( 'NINO_APP_DIR', $pickApp );
$pickParse = ninoSandbox( 'components-options-parse' );
$pickParse['/nino/dir'] = '';
\Nino\Modules\Components::init( $pickParse );
$pickRows = $pickManifest['stacks']['picker-rows'];
$pickRows['attributes']['form']['options'] = '\Nino\Modules\Parsebroken::forms';
$pickParse['/nino/modules'] = [ '\Nino\Modules\Picker' ];
$pickParse['./nino/features/listed'] = [ 'modules' => $pickParse['/nino/modules'], 'manifests' => [ [ 'key' => 'picker', 'dir' => '/features/Picker', 'module' => '\Nino\Modules\Picker', 'components' => [ 'picker-broken' => $pickGood ], 'stacks' => [ 'picker-rows' => $pickRows ] ] ] ];
ninoWarnings();
$pickThrown = null;
$pickParseErrors = ninoErrors( static function() use ( &$pickParse, &$pickThrown ): void {
	try {
		\Nino\Features::registerComponents( $pickParse );
	}
	catch( \Throwable $thrown ) {
		$pickThrown = $thrown;
	}
} );
check( 'a listed feature whose options class does not parse is one warning and the stack is left out, the others of its manifest registered - nothing thrown into the request',
	$pickThrown === null && count( $pickParseErrors ) === 1 && $pickParseErrors[0][0] === E_USER_WARNING
	&& str_contains( $pickParseErrors[0][1], 'stack "picker-rows": "options": ParseError: ' ) && str_ends_with( $pickParseErrors[0][1], ' - not registered' )
	&& isset( $pickParse[\Nino\Modules\Components::STACKS]['picker-rows'] ) === false && isset( $pickParse[\Nino\Modules\Components::REGISTRY]['picker-broken'] ) === true );
\Nino\Filesystem::removeDir( ninoSandboxDir( $pickParse ) );
\Nino\Filesystem::removeDir( ninoSandboxDir( $pickFeature ) );

\Nino\Filesystem::removeDir( ninoSandboxDir( $pick ) );

echo "\n";

// --- What a block says of the language of its text ----------------------------------

echo "Modules\\Components - a text that is the words of another language says so with a lang attribute\n";

// English is what the page is in; the native language is German, and French is a file only. A key has a text in a language, none, or only a fill of the code
$tag = ninoSandbox( 'components-lang' );
$tag['/nino/dir'] = '';
\Nino\Modules\Components::init( $tag );
\Nino\Filesystem::putFileContent( $tag, '/text/de_DE.php', [ '[[/template/page-lang/probe/own]]' => 'Eigen', '[[/template/page-lang/probe/other]]' => 'Fremd', '[[/template/page-lang/probe/runtime]]' => 'Laufzeit' ] );
\Nino\Filesystem::putFileContent( $tag, '/text/en_US.php', [ '[[/template/page-lang/probe/own]]' => 'Own', '[[/template/page-lang/probe/other]]' => '', '[[/template/page-lang/probe/runtime]]' => ' ', '[[/template/page-lang/probe/none]]' => '' ] );
\Nino\Filesystem::putFileContent( $tag, '/text/fr_FR.php', [ '[[/template/page-lang/probe/french]]' => 'Francais' ] );
\Nino\Html::addFills( $tag, [ '/template/page-lang/probe/runtime' => 'Runtime' ], 'en_US' );
$tag['./nino/locales/current'] = 'en_US';
$tagRender = static function( string $html ) use ( &$tag ): string {
	return \Nino\Html::renderHtml( $tag, $html );
};
// The attribute stands in the opening tag of the outer element, once
$tagOuter = static function( string $html, string $lang ): bool {
	return preg_match( '/^<[a-z0-9]+\s[^>]*\slang="'. preg_quote( $lang, '/' ). '"[^>]*>/', $html ) === 1 && substr_count( $html, ' lang=' ) === 1;
};

$tagTexts = array_keys( array_filter( \Nino\Modules\Components::components( $tag ), static fn( array $schema ): bool => $schema['source'] === 'text' ) );
$tagWrong = [];
foreach( $tagTexts as $tagName ) {
	$tagFallback = $tagRender( '['. $tagName. ' /template/page-lang/probe/other]' );
	if( $tagOuter( $tagFallback, 'de-DE' ) === false )
		$tagWrong[] = $tagName. ' says nothing of the native text: '. $tagFallback;
	if( $tagOuter( $tagRender( '['. $tagName. ' /template/page-lang/probe/french]' ), 'fr-FR' ) === false )
		$tagWrong[] = $tagName. ' does not name the language of a file that is only a file';
	foreach( [ 'own' => '[%s /template/page-lang/probe/own]', 'runtime' => '[%s /template/page-lang/probe/runtime]', 'fixed' => '[%s /template/page-lang/probe/other text="Fixed"]', 'none' => '[%s /template/page-lang/probe/none]' ] as $tagCase => $tagCall )
		if( str_contains( $tagRender( sprintf( $tagCall, $tagName ) ), ' lang=' ) === true )
			$tagWrong[] = $tagName. ' has a lang attribute for '. $tagCase;
}
check( 'every component of the registry whose text is a key writes a lang attribute on its outer element - once - where the page shows the text of another language, naming it'. ( $tagWrong === [] ? '' : ' - '. implode( ' | ', $tagWrong ) ), $tagTexts !== [] && $tagWrong === [] );
check( '...and none for a text of the page\'s own language, one a fill of the code gives, a text the call fixes, or none at all - which draws nothing', $tagWrong === [] && $tagRender( '[title /template/page-lang/probe/none]' ) === '' );

check( '[button] says it on the link and on the span it is without an address, and a card that holds one is not a text and says nothing itself',
	$tagOuter( $tagRender( '[button /template/page-lang/probe/other href="/x"]' ), 'de-DE' ) === true && str_starts_with( $tagRender( '[button /template/page-lang/probe/other href="/x"]' ), '<a ' ) === true
	&& $tagOuter( $tagRender( '[button /template/page-lang/probe/other]' ), 'de-DE' ) === true && str_starts_with( $tagRender( '[button /template/page-lang/probe/other]' ), '<span ' ) === true
	&& str_contains( $tagRender( '[card][card-title /template/page-lang/probe/other][/card]' ), ' lang="de-DE"' ) === true && preg_match( '/^<[a-z]+\s[^>]*\slang=/', $tagRender( '[card][card-title /template/page-lang/probe/other][/card]' ) ) === 0 );
check( 'a component that has no text of its own to say - a picture, a spacer, the html, the arrow of a hero - writes none',
	array_filter( array_keys( array_filter( \Nino\Modules\Components::components( $tag ), static fn( array $schema ): bool => $schema['source'] !== 'text' ) ),
		static fn( string $name ): bool => str_contains( $tagRender( '['. $name. ' /template/page-lang/probe/other]' ), ' lang=' ) === true ) === [] );
check( 'the language is the one the text was taken from, and it is the page\'s when the page is in the native language', ( static function() use ( &$tag, $tagRender ): bool {
	$tag['./nino/locales/current'] = 'de_DE';
	$native = str_contains( $tagRender( '[title /template/page-lang/probe/other]' ), ' lang=' ) === false;
	$tag['./nino/locales/current'] = 'en_US';
	return $native;
} )() === true && \Nino\Html::fillOrigin( $tag, '/template/page-lang/probe/other' ) === 'de_DE' && \Nino\Html::fillOrigin( $tag, '/template/page-lang/probe/french' ) === 'fr_FR' );

\Nino\Filesystem::removeDir( ninoSandboxDir( $tag ) );

echo "\n";

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
