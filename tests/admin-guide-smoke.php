<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-guide-smoke.php	The workbench interface (docs/admin-ui.md) held by structure and by nothing
 *											it says - no word is compared. It reads the sources of _admin and pins:
 *											the scales of the design system are declared; every --nino-admin-* and
 *											--editor-* token that a stylesheet or a script of the workbench reads is
 *											declared; every nino-admin-* class that a script of the workbench names
 *											has a rule in style.css; style.css writes type, weight and space only
 *											through the tokens, apart from a short list of named exceptions; the word
 *											of a danger button holds 4.5 : 1 in both schemes; and the chapter is
 *											linked from the guide and the panel recipe and its own links land.
 *											A rule of the chapter that structure can hold is added here by the change
 *											that brings the block it needs.
 *											Reads the checkout and writes nothing.
 *
 *	Usage: php tests/admin-guide-smoke.php
 */

require __DIR__. '/harness.php';

$root = dirname( __DIR__ );

echo "Workbench interface: $root\n\n";

/*	The files of a directory tree with one of the extensions, as paths relative to the root	*/
$files = static function( string $base, array $extensions ) use ( $root ): array {

	$found = [];

	foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root. '/'. $base, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if( $file->isFile() === true && in_array( $file->getExtension(), $extensions, true ) === true )
			$found[] = substr( $file->getPathname(), strlen( $root ) + 1 );
	}

	sort( $found );

	return $found;
};

$read = static function( string $relative ) use ( $root ): string {
	return (string) file_get_contents( $root. '/'. $relative );
};

/*	A stylesheet or a script without its comments	*/
$bare = static function( string $source ): string {
	return (string) preg_replace( '~/\*.*?\*/~s', '', $source );
};

$style		= $bare( $read( '_admin/assets/style.css' ) );
$sheets		= $files( '_admin/assets', [ 'css' ] );
$scripts	= $files( '_admin', [ 'js' ] );

check( 'the stylesheets and scripts of the workbench are found', in_array( '_admin/assets/style.css', $sheets, true ) === true && count( $scripts ) > 20 );

// --- the scales are declared ---------------------------------------------------------------------

/*	The custom properties the design system declares: every name that stands before a colon in style.css	*/
preg_match_all( '/(?<![\w-])(--[a-z][a-z0-9-]*)\s*:/', $style, $declared );
$declared = array_flip( $declared[1] );

$scales = [];

foreach( [ 'xs', 'sm', 'md', 'lg', 'xl', '2xl' ] as $step )
	$scales[] = '--nino-admin-text-'. $step;

foreach( [ 'normal', 'medium', 'strong', 'heavy' ] as $step )
	$scales[] = '--nino-admin-weight-'. $step;

foreach( range( 1, 8 ) as $step )
	$scales[] = '--nino-admin-space-'. $step;

$scales = array_merge( $scales, [ '--nino-admin-space-legend', '--nino-admin-control-height', '--nino-admin-control-small', '--nino-admin-control-icon', '--nino-admin-control-icon-small', '--nino-admin-row-height', '--nino-admin-row-dense', '--nino-admin-row-compact', '--nino-admin-radius-pill' ] );

$missing = array_values( array_filter( $scales, static fn( string $name ): bool => isset( $declared[$name] ) === false ) );

check( 'the scales are declared: six steps of type, four of weight, eight of space and the room of a legend, the heights of a control and of a row, and the pill radius'. ( $missing === [] ? '' : ' - not '. implode( ', ', $missing ) ), $missing === [] );

// --- a token that is read is declared --------------------------------------------------------------

/*	What a script sets by hand on an element is declared where it is set	*/
$set = [];

foreach( $scripts as $script ) {
	preg_match_all( '/setProperty\(\s*[\'"](--[a-z][a-z0-9-]*)[\'"]/', $read( $script ), $names );
	foreach( $names[1] as $name )
		$set[$name] = true;
}

$undeclared = [];
$reads			= 0;

foreach( array_merge( $sheets, $scripts ) as $file ) {

	preg_match_all( '/var\(\s*(--(?:nino-admin|editor)-[a-z0-9-]+)/', $bare( $read( $file ) ), $names );

	foreach( $names[1] as $name ) {
		$reads++;

		if( isset( $declared[$name] ) === false && isset( $set[$name] ) === false )
			$undeclared[$name] = $file;
	}
}

check( 'the workbench reads tokens, and every --nino-admin-* and --editor-* token it reads is declared in style.css or set by a script'. ( $undeclared === [] ? '' : ' - not '. implode( ', ', array_keys( $undeclared ) ) ), $reads > 200 && $undeclared === [] );

// --- a class that a script names has a rule ---------------------------------------------------------

preg_match_all( '/\.(nino-admin-[a-z0-9_-]+)/', $style, $ruled );
$ruled = array_flip( $ruled[1] );

/*	Classes that only mark a part for a script and for the suites that look for it, and carry no style: the one place such a name is
	allowed without a rule. A name is added here when it marks a part and nothing about it is to look different	*/
$hooks = [
	'nino-admin-tile--',						// the tile of a panel: the name of the panel after it, for the Dashboard's script and its suite
	'nino-admin-meter-label',				// the label of a meter in the Dashboard
	'nino-admin-elementlist-add',		// the add button of an element list, found by its suite
	'nino-admin-table-wrap',				// a mount that holds a table(), so that it is destroyed once
	'nino-admin-split-title',				// the title of the preview in the bar of a split()
	'nino-admin-split-port',				// the frame of a preview; only its --scaled state has a rule
	'nino-admin-color-mark',				// the mark of a colour field, found by its suite
];

$classless = [];
$named		 = 0;

foreach( $scripts as $script ) {

	// A class is named where a script sets one: className, classList, the second argument of el(), a class attribute of a markup
	preg_match_all( '/(?:className\s*[=:]|classList\.\w+\(|\bel\(\s*[\'"][a-z0-9]+[\'"]\s*,|\bclass=\\?["\'])\s*([\'"])(.*?)\1/', $bare( $read( $script ) ), $literals );

	preg_match_all( '/(?<![\w.-])(nino-admin-[a-z0-9_-]+)/', implode( ' ', $literals[2] ), $names );

	foreach( array_unique( $names[1] ) as $name ) {
		$named++;

		// A name that ends on a hyphen is the beginning of one that is put together: some class begins with it
		$found = isset( $ruled[$name] ) === true
			|| ( str_ends_with( $name, '-' ) === true && array_filter( array_keys( $ruled ), static fn( string $class ): bool => str_starts_with( $class, $name ) ) !== [] );

		if( $found === false && in_array( $name, $hooks, true ) === false )
			$classless[$name] = $script;
	}
}

check( 'the scripts of the workbench name classes, and every nino-admin-* class they name has a rule in style.css'. ( $classless === [] ? '' : ' - not '. implode( ', ', array_keys( $classless ) ) ), $named > 50 && $classless === [] );

// --- style.css names type, weight and space through the tokens ---------------------------------------

/*	What a stylesheet writes for type, weight and space that is no token: the declarations that are no custom property - [ selector, property,
	value ] in the order they stand, a rule inside a media query by its own selector - read for font-size, font, font-weight and the properties of
	space, apart from the named exceptions, each [ selector, property, value ] at the place it stands. Answers [ sizes of type read, declarations
	of space read, what is stray ]	*/
$strays = static function( string $css, array $exceptions ): array {

	$declarations = [];

	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER );

	foreach( $rules as $rule ) {
		foreach( explode( ';', $rule[2] ) as $declaration ) {

			$parts = explode( ':', $declaration, 2 );

			if( count( $parts ) === 2 && str_starts_with( trim( $parts[0] ), '--' ) === false )
				$declarations[] = [ trim( (string) preg_replace( '/\s+/', ' ', $rule[1] ) ), trim( $parts[0] ), trim( (string) preg_replace( '/\s+/', ' ', $parts[1] ) ) ];
		}
	}

	$stray = [];
	$typed = 0;
	$spaced = 0;

	foreach( $declarations as [ $selector, $property, $value ] ) {

		$named = in_array( [ $selector, $property, $value ], $exceptions, true );

		if( $property === 'font-size' ) {
			$typed++;

			if( preg_match( '/^(var\(--nino-admin-text-(xs|sm|md|lg|xl|2xl)\)|inherit|0)$/', $value ) !== 1 && $named === false )
				$stray[] = "$selector: font-size: $value";
		}

		if( $property === 'font' && preg_match( '/^(var\(--nino-admin-text-(xs|sm|md|lg|xl|2xl)\)|inherit)(\/|$)/', $value ) !== 1 )
			$stray[] = "$selector: font: $value";

		if( $property === 'font-weight' && preg_match( '/^(var\(--nino-admin-weight-(normal|medium|strong|heavy)\)|inherit)$/', $value ) !== 1 )
			$stray[] = "$selector: font-weight: $value";

		if( preg_match( '/^(gap|row-gap|column-gap|(margin|padding)(-[a-z]+)*)$/', $property ) === 1 ) {
			$spaced++;

			if( $named === true )
				continue;

			// What is left of a value without its tokens: a length of a rem that is a step or more, or of more than 4px, is a space written by hand
			$rest = (string) preg_replace( '/var\(--[a-z0-9-]+\)/', '', $value );

			preg_match_all( '/(\d*\.?\d+)(rem|px)/', $rest, $lengths, PREG_SET_ORDER );

			foreach( $lengths as $length ) {
				if( ( $length[2] === 'rem' && (float) $length[1] >= .125 ) || ( $length[2] === 'px' && (float) $length[1] > 4 ) ) {
					$stray[] = "$selector: $property: $value";
					break;
				}
			}
		}
	}

	return [ $typed, $spaced, $stray ];
};

/*	The named exceptions of the scale, by the place they stand - selector, property, value. Each is written at its place with a comment
	that says why, and a value that stands anywhere else is not one	*/
$exceptions = [
	[ 'html', 'font-size', '15px' ],																																						// the root size, which the steps count from
	[ 'body', 'font-size', '15px' ],
	[ ':where(.nino-admin) .nino-admin-tile-value', 'font-size', '1.6rem' ],																		// a date of ten characters wraps at 390px one step higher
	[ ':where(.nino-admin) .nino-admin-manual code', 'font-size', 'max( .92em, 12px )' ],												// code inside a text follows the text
	[ ':where(.nino-admin) .nino-admin-richtext-content code', 'font-size', 'max( .92em, 12px )' ],
	[ ':where(.nino-admin) .nino-admin-checkbox-fieldset', 'font-size', 'max( .9em, 12px )' ],
	[ ':where(.nino-admin) :is(.nino-admin-dialog--form, .nino-admin-dialog--wide) .nino-admin-dialog-fields > .nino-admin-field-error', 'margin-left', 'calc(min(max(7rem, 28%), 11rem) + var(--nino-admin-space-5))' ],	// the label column of a dialog's form
	[ '.nino-admin-auth', 'margin', 'clamp(2rem, 9vh, 6rem) auto' ],																						// the top of the login card
	[ '#admin-page-wrap #admin-localepicker, #form-login #admin-localepicker', 'padding', '.1rem 14px .1rem 0' ],	// the room of the chevron in the login's language select
];

[ $typed, $spaced, $stray ] = $strays( $style, $exceptions );

check( 'style.css has sizes of type and declarations of space to read', $typed > 80 && $spaced > 250 );
check( 'style.css writes type, weight and space only through the tokens, apart from the named exceptions'. ( $stray === [] ? '' : ' - '. implode( '; ', array_slice( $stray, 0, 8 ) ) ), $stray === [] );

// --- the word of a danger button holds 4.5 : 1 --------------------------------------------------------

/*	The custom properties of the block that begins at the selector, as name => value	*/
$block = static function( string $selector ) use ( $style ): array {

	$at = strpos( $style, $selector );

	if( $at === false )
		return [];

	$open	= (int) strpos( $style, '{', $at );
	$body	= substr( $style, $open + 1, (int) strpos( $style, '}', $open ) - $open - 1 );

	preg_match_all( '/(--[a-z0-9-]+)\s*:\s*([^;]+);/', $body, $pairs, PREG_SET_ORDER );

	$map = [];

	foreach( $pairs as $pair )
		$map[$pair[1]] = trim( $pair[2] );

	return $map;
};

/*	A colour of a token as [ r, g, b, a ], a token that points at another one followed through the schemes' blocks	*/
$colour = static function( string $value, array $schemes ) use ( &$colour ): ?array {

	if( preg_match( '/^var\((--[a-z0-9-]+)\)$/', $value, $pointer ) === 1 ) {
		foreach( $schemes as $map ) {
			if( isset( $map[$pointer[1]] ) === true )
				return $colour( $map[$pointer[1]], $schemes );
		}

		return null;
	}

	if( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $short ) === 1 )
		return [ hexdec( $short[1]. $short[1] ), hexdec( $short[2]. $short[2] ), hexdec( $short[3]. $short[3] ), 1.0 ];

	if( preg_match( '/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $value, $hex ) === 1 )
		return [ hexdec( $hex[1] ), hexdec( $hex[2] ), hexdec( $hex[3] ), 1.0 ];

	if( preg_match( '/^rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)$/', $value, $rgba ) === 1 )
		return [ (int) $rgba[1], (int) $rgba[2], (int) $rgba[3], (float) $rgba[4] ];

	return null;
};

$over = static function( array $tint, array $ground ): array {
	return array_map( static fn( int $i ): int => (int) round( $tint[$i] * $tint[3] + $ground[$i] * ( 1 - $tint[3] ) ), [ 0, 1, 2 ] );
};

$luminance = static function( array $rgb ): float {

	$channel = static function( int $value ): float {
		$value /= 255;
		return $value <= .03928 ? $value / 12.92 : ( ( $value + .055 ) / 1.055 ) ** 2.4;
	};

	return .2126 * $channel( $rgb[0] ) + .7152 * $channel( $rgb[1] ) + .0722 * $channel( $rgb[2] );
};

$contrast = static function( array $a, array $b ) use ( $luminance ): float {
	$high	= max( $luminance( $a ), $luminance( $b ) );
	$low	= min( $luminance( $a ), $luminance( $b ) );
	return ( $high + .05 ) / ( $low + .05 );
};

$light	= $block( ':root {' );
$system	= $block( ':root:not([data-theme="light"])' );
$chosen	= $block( ':root[data-theme="dark"]' );

foreach( [ 'light' => [ $light, [ $light ] ], 'dark (system)' => [ $system, [ $system, $light ] ], 'dark (chosen)' => [ $chosen, [ $chosen, $light ] ] ] as $name => [ $own, $schemes ] ) {

	$ink	= $colour( $own['--nino-admin-danger-ink'] ?? ( $light['--nino-admin-danger-ink'] ?? '' ), $schemes );
	$soft	= $colour( $own['--editor-red-soft'] ?? $light['--editor-red-soft'], $schemes );
	$page	= $colour( $own['--editor-bg'] ?? $light['--editor-bg'], $schemes );
	$card	= $colour( $own['--editor-bg-elevated'] ?? $light['--editor-bg-elevated'], $schemes );

	check( "$name: the tokens of the danger button are colours", $ink !== null && $soft !== null && $page !== null && $card !== null );

	if( $ink === null || $soft === null || $page === null || $card === null )
		continue;

	foreach( [ 'the page' => $page, 'a card' => $card ] as $ground => $rgb )
		check( "$name: the word of a danger button holds 4.5 : 1 on its pale red over $ground", $contrast( $ink, $over( $soft, $rgb ) ) >= 4.5 );
}

// --- the heads of a table hold 4.5 : 1, in every scheme and in every state -------------------------------

/*	The surface of the column heads is a mix of two tokens: color-mix( in srgb, A p%, B ), as the scheme writes it	*/
$mixed = static function( string $value, array $own, array $schemes ) use ( $colour ): ?array {

	if( preg_match( '/^color-mix\(in srgb, var\((--[a-z0-9-]+)\) (\d+)%, var\((--[a-z0-9-]+)\)\)$/', $value, $mix ) !== 1 )
		return null;

	$a = $colour( $own[$mix[1]] ?? ( $schemes[0][$mix[1]] ?? ( $schemes[1][$mix[1]] ?? '' ) ), $schemes );
	$b = $colour( $own[$mix[3]] ?? ( $schemes[0][$mix[3]] ?? ( $schemes[1][$mix[3]] ?? '' ) ), $schemes );

	if( $a === null || $b === null )
		return null;

	$share = (int) $mix[2] / 100;

	return array_map( static fn( int $i ): int => (int) round( $a[$i] * $share + $b[$i] * ( 1 - $share ) ), [ 0, 1, 2 ] );
};

$sortRule	= static function( string $selector ) use ( $style ): string {
	return preg_match( '/'. preg_quote( $selector, '/' ). ' \{([^}]*)\}/', $style, $found ) === 1 ? $found[1] : '';
};

check( 'a head of a column is the word in the secondary colour, a sorted one in the blue, one under the pointer in the colour of text', str_contains( $sortRule( ':where(.nino-admin) .nino-admin-table-sort' ), 'color: var(--editor-text-secondary)' )
	&& str_contains( $sortRule( ':where(.nino-admin) .nino-admin-table th.is-sorted .nino-admin-table-sort' ), 'color: var(--editor-blue)' )
	&& str_contains( $sortRule( ':where(.nino-admin) .nino-admin-table-sort:hover:not(:disabled)' ), 'color: var(--editor-text)' )
	&& str_contains( $sortRule( ':where(.nino-admin) .nino-admin-table thead th' ), 'background: var(--nino-admin-surface-muted)' ) );

foreach( [ 'light' => [ $light, [ $light ] ], 'dark (system)' => [ $system, [ $system, $light ] ], 'dark (chosen)' => [ $chosen, [ $chosen, $light ] ] ] as $name => [ $own, $schemes ] ) {

	$ground	= $mixed( (string) ( $own['--nino-admin-surface-muted'] ?? $light['--nino-admin-surface-muted'] ?? '' ), $own, $schemes );
	$tint		= $colour( $own['--editor-blue-soft'] ?? $light['--editor-blue-soft'], $schemes );
	$words	= [];

	foreach( [ 'secondary' => '--editor-text-secondary', 'blue' => '--editor-blue', 'text' => '--editor-text' ] as $label => $token )
		$words[$label] = $colour( $own[$token] ?? $light[$token], $schemes );

	check( "$name: the surface of a head and its words are colours", $ground !== null && $tint !== null && $words['secondary'] !== null && $words['blue'] !== null && $words['text'] !== null );

	if( $ground === null || $tint === null || $words['secondary'] === null || $words['blue'] === null || $words['text'] === null )
		continue;

	check( "$name: the words of a head of a column hold 4.5 : 1 on its surface (". round( $contrast( $words['secondary'], $ground ), 2 ). ")", $contrast( $words['secondary'], $ground ) >= 4.5 );
	check( "$name: so does a sorted one, in the blue (". round( $contrast( $words['blue'], $ground ), 2 ). ")", $contrast( $words['blue'], $ground ) >= 4.5 );
	check( "$name: and one under the pointer, on the tint over the surface (". round( $contrast( $words['text'], $over( $tint, $ground ) ), 2 ). ")", $contrast( $words['text'], $over( $tint, $ground ) ) >= 4.5 );
}

// --- a table keeps its actions in reach, a switch its words in the spelling of the words, a link its size ---

check( 'the column of the actions of a table sticks to the right edge at every width: its rule stands outside a media query', preg_match( '/\n:where\(\.nino-admin\) \.nino-admin-table :is\(th, td\)\.nino-admin-table-actions \{[^}]*position: sticky;[^}]*right: 0;/', "\n". $style ) === 1 );
check( '...and on a phone a cell the frame cuts ends in an ellipsis, a cell with a sentence breaks its lines instead', preg_match( '/@media \(max-width: 40rem\) \{\s*:where\(\.nino-admin\) \.nino-admin-table td:not\([^)]*\.is-wrap[^)]*\) \{[^}]*overflow: hidden;[^}]*text-overflow: ellipsis;/', $style ) === 1 );
check( 'the written state of a switch is not turned into capitals by the stylesheet', $sortRule( ':where(.nino-admin) .nino-admin-switch-state' ) !== '' && str_contains( $sortRule( ':where(.nino-admin) .nino-admin-switch-state' ), 'text-transform' ) === false );

$dashboard = $bare( $read( '_admin/Nino/Modules/Dashboard/assets/admin.css' ) );
$smallest = $light['--nino-admin-control-icon-small'] ?? '';

check( 'a link of the Dashboard is a target of 24 px at least: the token it reads for its height is 24 px or more', preg_match( '/^(\d+)px$/', $smallest, $px ) === 1 && (int) $px[1] >= 24
	&& preg_match( '/#admin-dashboard-elements a \{[^}]*min-height: var\(--nino-admin-control-icon-small\)/', $dashboard ) === 1
	&& preg_match( '/\.admin-dashboard-more \{[^}]*min-height: var\(--nino-admin-control-icon-small\)/', $dashboard ) === 1 );

// --- a save that went well is said by the toast and the badge, in no line --------------------------------------

$spoken = [];

foreach( array_merge( $files( '_admin/Nino/Modules', [ 'js' ] ), $files( '_nino/Nino/Modules', [ 'js' ] ) ) as $script ) {

	if( str_contains( $script, '/assets/' ) === false )
		continue;

	// The success sentence set as the text of a line: the status of a bar, the line of a form, an element of its own
	if( preg_match( '/\.textContent\s*=\s*Nino\.content\.getText\(\s*\'[^\']*\/msg\/saved\'\s*\)|_pendingMsg/', $bare( $read( $script ) ) ) === 1 )
		$spoken[] = $script;
}

check( 'no script of a panel writes "Saved." as the text of a line, or keeps one to write after a reload'. ( $spoken === [] ? '' : ' - '. implode( ', ', $spoken ) ), $spoken === [] );
$leading = [];

foreach( array_merge( $files( '_admin/Nino/Modules', [ 'js' ] ), $files( '_nino/Nino/Modules', [ 'js' ] ) ) as $script ) {
	if( str_contains( $script, '/assets/' ) === true && preg_match( '/adminUi\.hint\([^)]*,\s*\'lead\'\s*\)/', $bare( $read( $script ) ) ) === 1 )
		$leading[] = $script;
}

check( 'no panel opens a view with a paragraph of its own: the lead of hint() is the setup wizard\'s alone'. ( $leading === [] ? '' : ' - '. implode( ', ', $leading ) ), $leading === [] );
check( 'a status line that was saved has no words of its own in the shared fills: there is no "saved at" fill', str_contains( $read( '_admin/text/en_US.php' ), 'msg/savedat' ) === false && str_contains( $read( '_admin/text/de_DE.php' ), 'msg/savedat' ) === false );

// --- every block of the second part of the forms is built, guided, put into a recipe and tested ---------------------

$blocks = [ 'localeRows', 'checkField', 'checklist', 'fileField', 'codeField', 'dateTime' ];
$script = $bare( $read( '_admin/assets/Nino.admin.js' ) );
$recipe = $read( 'docs/recipes/admin-panel.md' );
$suites = $read( 'tests/nino-ui-fields-js-smoke.js'). $read( 'tests/nino-ui-dialog-js-smoke.js' );
$unbuilt = [];

foreach( $blocks as $block ) {
	if( preg_match( '/^\t\t'. $block. ' : function\(/m', $script ) !== 1 || str_contains( $read( 'docs/admin-ui.md' ), '`'. $block. '(' ) === false || str_contains( $recipe, 'Nino.adminUi.'. $block. '(' ) === false || str_contains( $suites, 'ui.'. $block. '(' ) === false )
		$unbuilt[] = $block;
}

check( 'localeRows, checkField, checklist, fileField, codeField and dateTime are in the script, in the chapter, in the recipe and in a suite'. ( $unbuilt === [] ? '' : ' - not '. implode( ', ', $unbuilt ) ), $unbuilt === [] );
check( 'a dialog of a form takes each of them as a type of field, and a question takes the list of what it is about', preg_match( '/field\.type === \'locales\'/', $script ) === 1 && preg_match( '/field\.type === \'datetime\'/', $script ) === 1 && str_contains( $script, 'options.details' ) === true && str_contains( $read( 'docs/admin-ui.md' ), 'details' ) === true );

// --- the panels that have gone over to the blocks of the bar ------------------------------------------

/*	The panels that draw their bar and their way back with actionBar() and contextBar() and not by hand. The list grows: the
	patch that brings another panel over adds its script here, and from then on the panel keeps to it	*/
$converted = [
	'_admin/Nino/Modules/Routes/assets/admin.js',
	'_nino/Nino/Modules/Navigation/assets/admin.js',
	'_admin/Nino/Modules/Users/assets/admin.js',
	'_admin/Nino/Modules/Users/assets/roles.js',
	'_admin/Nino/Modules/Images/assets/slots.js',
	'_admin/Nino/Modules/Elements/assets/admin.js',
	'_admin/Nino/Modules/Elements/assets/types.js',
];

$byHand = [];
$lacking = [];

foreach( $converted as $script ) {

	$source = $bare( $read( $script ) );

	if( preg_match( '/className\s*=\s*[\'"][^\'"]*nino-admin-(actionbar|btn-primary|btn-danger|back-link)|listActions\(|formToolbar\(/', $source ) === 1 )
		$byHand[] = $script;

	if( str_contains( $source, 'adminUi.actionBar(' ) === false || str_contains( $source, 'adminUi.contextBar(' ) === false || str_contains( $source, 'adminUi.say(' ) === false )
		$lacking[] = $script;
}

check( 'the panels that have gone over to the blocks of the bar build neither the bar, its buttons by class, the way back nor the list bar by hand'. ( $byHand === [] ? '' : ' - '. implode( ', ', $byHand ) ), $byHand === [] );
check( '...and use actionBar(), contextBar() and say()'. ( $lacking === [] ? '' : ' - not '. implode( ', ', $lacking ) ), $lacking === [] );

// --- the panels that have gone over to the blocks of a list ------------------------------------------------

/*	The panels whose lists are drawn by the blocks: the groups of Text and Keys by list(), their filter by filterBar() and
	chip(), the three tabs of Features by table(). Each script names the block it draws with, and no script or stylesheet of
	them names a class of a list row, a chip, a search bar or a group of a panel's own. The list grows: the patch that brings
	another panel over adds its script here	*/
$listed = [
	'_admin/Nino/Modules/Text/assets/admin.js'		=> 'adminUi.list(',
	'_admin/Nino/Modules/Text/assets/keys.js'			=> 'adminUi.list(',
	'_admin/Nino/Modules/Text/assets/textkeys.js'	=> 'adminUi.filterBar(',
	'_admin/Nino/Modules/Features/assets/admin.js'	=> 'adminUi.table(',
	'_admin/Nino/Modules/Users/assets/admin.js'		=> 'adminUi.list(',
	'_admin/Nino/Modules/Users/assets/roles.js'		=> 'adminUi.list(',
	'_admin/Nino/Modules/Users/assets/lockout.js'	=> 'adminUi.list(',
	'_admin/Nino/Modules/Routes/assets/admin.js'		=> 'adminUi.list(',
	'_nino/Nino/Modules/Navigation/assets/admin.js'	=> 'adminUi.list(',
	'_admin/Nino/Modules/Logs/assets/admin.js'			=> 'adminUi.table(',
	'_admin/Nino/Modules/Backups/assets/admin.js'		=> 'adminUi.list(',
	'_nino/Nino/Modules/Form/assets/admin.js'				=> 'adminUi.filterBar(',
	'_admin/Nino/Modules/Images/assets/admin.js'		=> 'adminUi.list(',
	'_admin/Nino/Modules/Images/assets/slots.js'		=> 'adminUi.list(',
	'_admin/Nino/Modules/Elements/assets/admin.js'	=> 'adminUi.table(',
	'_admin/Nino/Modules/Elements/assets/types.js'	=> 'adminUi.list(',
];

$ownClass = '/admin-type-btn|admin-view-button-chev|admin-text-chip|admin-text-search|admin-text-block|nino-admin-list-buttons/';
$unlisted = [];
$ownLists = [];

foreach( $listed as $script => $block ) {

	$source = $bare( $read( $script ) );

	if( str_contains( $source, $block ) === false )
		$unlisted[] = $script;

	if( preg_match( $ownClass, $source ) === 1 )
		$ownLists[] = $script;
}

foreach( [ '_admin/Nino/Modules/Text/assets/admin.css', '_admin/Nino/Modules/Features/assets/admin.css' ] as $sheet ) {
	if( preg_match( $ownClass, $bare( $read( $sheet ) ) ) === 1 )
		$ownLists[] = $sheet;
}

check( 'the panels that have gone over to the blocks of a list draw with them'. ( $unlisted === [] ? '' : ' - not '. implode( ', ', $unlisted ) ), $unlisted === [] );
check( '...and name no list row, chip, search bar or group of their own, in script or stylesheet'. ( $ownLists === [] ? '' : ' - '. implode( ', ', $ownLists ) ), $ownLists === [] );
check( 'the chip and the search bar of the Text panel are the blocks', str_contains( $bare( $read( '_admin/Nino/Modules/Text/assets/textkeys.js' ) ), 'adminUi.chip(' ) === true );

// --- the forms drawn from a schema keep their help behind the (?) ------------------------------------------

/*	The scripts that draw a form from a schema - the Config panel, Maintenance, the login protection, Language and the
	settings of a feature. The sentence of a field is its help (behind the (?) at the name, see fieldHelp()), never a line
	under the field: no script of the list names a hint for a field, builds a small hint line of its own for one, or writes the explanation of a section as a line under its title. The list
	grows: the patch that brings another form over adds its script here	*/
$schemaForms = [
	'_admin/Nino/Modules/Config/assets/admin.js',
	'_nino/Nino/Modules/Maintenance/assets/admin.js',
	'_admin/Nino/Modules/Users/assets/lockout.js',
	'_admin/Nino/Modules/Language/assets/admin.js',
	'_admin/Nino/Modules/Features/assets/admin.js',
	'_admin/Nino/Modules/Routes/assets/admin.js',
	'_nino/Nino/Modules/Navigation/assets/admin.js',
	'_admin/Nino/Modules/Images/assets/admin.js',
	'_admin/Nino/Modules/Images/assets/slots.js',
	'_admin/Nino/Modules/Images/assets/favicon.js',
	'_admin/Nino/Modules/Elements/assets/admin.js',
	'_admin/Nino/Modules/Elements/assets/types.js',
];

$underneath = [];

foreach( $schemaForms as $script ) {

	// Without the comments of both kinds: a comment may say the word
	$source = (string) preg_replace( '~^\s*//.*$~m', '', $bare( $read( $script ) ) );

	if( preg_match( '/\bhint\s*:|\bconst\s+hint\s*=\s*dc\.createElement\(\s*\'small\'/', $source ) === 1 )
		$underneath[] = $script;

	// The Features panel's own intro is the one of its list of applications, no section of a settings form
	if( str_contains( $script, '/Features/' ) === false && preg_match( '/\bintro\.className\s*=\s*\'nino-admin-hint\'/', $source ) === 1 )
		$underneath[] = $script;
}

check( 'no form drawn from a schema puts the sentence of a field, or the explanation of a section, under it'. ( $underneath === [] ? '' : ' - '. implode( ', ', $underneath ) ), $underneath === [] );

// --- the forms of Users, Roles, Login protection and the Recovery password are built from the blocks ----------------

/*	The four scripts of the Users panel draw their forms as the chapter asks: every form is novalidate, every field is a block (a
	label, a select, an input of their own is none), the bar is the block's with one main button, what is wrong is said under the
	field, and what went well is a toast. The list grows: the patch that brings another form over adds its script here	*/
$guideForms = [
	'_admin/Nino/Modules/Users/assets/admin.js',
	'_admin/Nino/Modules/Users/assets/roles.js',
	'_admin/Nino/Modules/Users/assets/lockout.js',
	'_admin/Nino/Modules/Users/assets/recoverypw.js',
	'_admin/Nino/Modules/Routes/assets/admin.js',
	'_nino/Nino/Modules/Navigation/assets/admin.js',
	'_admin/Nino/Modules/Images/assets/slots.js',
	'_admin/Nino/Modules/Images/assets/favicon.js',
	'_admin/Nino/Modules/Elements/assets/admin.js',
	'_admin/Nino/Modules/Elements/assets/types.js',
];

/*	The comments of a script that stand at the beginning of a line: a script that writes the string '/*' (the permission of full access) is no comment	*/
$comments = static function( string $source ): string {
	return (string) preg_replace( '~^[ \t]*/\*.*?\*/~ms', '', $source );
};

$handmade		= [];
$unchecked	= [];
$unbarred		= [];

foreach( $guideForms as $script ) {

	$source = $comments( $read( $script ) );
	$forms	= substr_count( $source, "createElement('form')" );

	// A form that the browser checks speaks in its own language, and a field that is built by hand is none of the blocks
	if( $forms < 1 || substr_count( $source, '.noValidate = true' ) !== $forms || preg_match( "/createElement\(\s*'(label|input|select|textarea|button)'\s*\)/", $source ) === 1 )
		$handmade[] = $script;

	if( str_contains( $source, 'adminUi.fieldError(' ) === false || str_contains( $source, 'adminUi.clearFieldErrors(' ) === false )
		$unchecked[] = $script;

	// The bar is the block's: a status line, one main button, and nothing is shown as a bar of its own
	if( str_contains( $source, 'adminUi.actionBar(' ) === false || preg_match( '/(className\s*=|classList\.(add|toggle)\()\s*[\'"][^\'"]*nino-admin-(actionbar|btn-)/', $source ) === 1 )
		$unbarred[] = $script;
}

check( 'every form of the panels that follow the guide here (Users, Routes, Navigations) is novalidate and made of the blocks of the workbench'. ( $handmade === [] ? '' : ' - '. implode( ', ', $handmade ) ), $handmade === [] );
check( '...says what is wrong under the field and clears it again'. ( $unchecked === [] ? '' : ' - '. implode( ', ', $unchecked ) ), $unchecked === [] );
check( '...and has the one bar of the blocks, with no class of a button or a bar written in the script'. ( $unbarred === [] ? '' : ' - '. implode( ', ', $unbarred ) ), $unbarred === [] );

// The permissions of a role are lists of ticks: a target of the list is the height of a control, never the 23 px of the picker's buttons
$rolesScript = $comments( $read( '_admin/Nino/Modules/Users/assets/roles.js' ) );
$rowRule = $sortRule( ':where(.nino-admin) .nino-admin-checklist label' );

check( 'the permissions of a role are lists of ticks, not the picker with its small buttons', str_contains( $rolesScript, 'adminUi.checklist(' ) === true && str_contains( $rolesScript, 'adminUi.elementList(' ) === false );
check( '...a row of such a list is at least 28 px high: its rule reads the height of a control, and that token is 28 px or more', preg_match( '/min-height: var\(--nino-admin-control-height\)/', $rowRule ) === 1
	&& preg_match( '/^(\d+)px$/', $light['--nino-admin-control-height'] ?? '', $rowHeight ) === 1 && (int) $rowHeight[1] >= 28 );
check( '...and full access is a switch', str_contains( $rolesScript, 'adminUi.switchField(' ) === true );
// --- the panels of the kernel that have gone over to the guide: Backups, Config, Maintenance, Language, Translations, Log, Requests, Dashboard ---

/*	The forms and lists of these eight, and of Routes and Navigations, keep to what the guide asks of a panel, held by structure: the bar of a form or a list is the block's and its
	one main button is the block's too, no sentence stands open over or under a field, a date is written by the one helper, a language is named by the
	one block, and the stylesheets of the panels that still have one name type, weight and space through the tokens. The list grows: the patch that
	brings another panel over adds its script here	*/
$barred = [
	'_admin/Nino/Modules/Backups/assets/admin.js',
	'_admin/Nino/Modules/Config/assets/admin.js',
	'_nino/Nino/Modules/Maintenance/assets/admin.js',
	'_admin/Nino/Modules/Language/assets/admin.js',
	'_admin/Nino/Modules/Language/assets/translations.js',
	'_nino/Nino/Modules/Form/assets/admin.js',
	'_admin/Nino/Modules/Routes/assets/admin.js',
	'_nino/Nino/Modules/Navigation/assets/admin.js',
	'_admin/Nino/Modules/Images/assets/slots.js',
	'_admin/Nino/Modules/Images/assets/favicon.js',
	'_admin/Nino/Modules/Elements/assets/admin.js',
	'_admin/Nino/Modules/Elements/assets/types.js',
];

$withoutBar = [];
$byHandAgain = [];
$hinted = [];

foreach( $barred as $script ) {

	// Without the comments of both kinds: a comment may say the word
	$source = (string) preg_replace( '~^\s*//.*$~m', '', $bare( $read( $script ) ) );

	if( str_contains( $source, 'adminUi.actionBar(' ) === false )
		$withoutBar[] = $script;

	if( preg_match( '/className\s*=\s*[\'"][^\'"]*nino-admin-(actionbar|btn-primary|btn-danger|back-link)|classList\.add\(\s*[\'"]nino-admin-btn-(primary|danger)|listActions\(|formToolbar\(/', $source ) === 1 )
		$byHandAgain[] = $script;

	// The Requests panel keeps its cards, which say what an entry holds as quiet text; no other of them has a line of hint of its own
	if( str_contains( $script, '/Form/' ) === false && preg_match( '/nino-admin-hint|adminUi\.hint\(/', $source ) === 1 )
		$hinted[] = $script;
}

check( 'the forms and lists that have gone over draw their bar with actionBar()'. ( $withoutBar === [] ? '' : ' - not '. implode( ', ', $withoutBar ) ), $withoutBar === [] );
$unchecked = [];

foreach( [ '_admin/Nino/Modules/Config/assets/admin.js', '_nino/Nino/Modules/Maintenance/assets/admin.js', '_admin/Nino/Modules/Language/assets/admin.js', '_admin/Nino/Modules/Language/assets/translations.js', '_admin/Nino/Modules/Images/assets/slots.js', '_admin/Nino/Modules/Images/assets/favicon.js', '_admin/Nino/Modules/Elements/assets/admin.js', '_admin/Nino/Modules/Elements/assets/types.js' ] as $script ) {
	if( str_contains( $bare( $read( $script ) ), 'noValidate = true' ) === false )
		$unchecked[] = $script;
}

check( 'the forms that have gone over are checked by the panel, never by the browser: each carries novalidate'. ( $unchecked === [] ? '' : ' - not '. implode( ', ', $unchecked ) ), $unchecked === [] );
check( '...and name no bar, main button or danger button class of their own, nor listActions()'. ( $byHandAgain === [] ? '' : ' - '. implode( ', ', $byHandAgain ) ), $byHandAgain === [] );
check( '...and write no open sentence of their own: what a field or a section does is behind its (?)'. ( $hinted === [] ? '' : ' - '. implode( ', ', $hinted ) ), $hinted === [] );

$dated = [
	'_admin/Nino/Modules/Backups/assets/admin.js',
	'_admin/Nino/Modules/Dashboard/assets/admin.js',
	'_admin/Nino/Modules/Logs/assets/admin.js',
	'_nino/Nino/Modules/Form/assets/admin.js',
	'_admin/Nino/Modules/Images/assets/favicon.js',
];

$ownDates = [];
$noHelper = [];

foreach( $dated as $script ) {

	$source = $bare( $read( $script ) );

	if( preg_match( '/toLocale(Date|Time)?String|Intl\.DateTimeFormat|toISOString|\.slice\(\s*0\s*,\s*1[06]\s*\)/', $source ) === 1 )
		$ownDates[] = $script;

	if( str_contains( $source, 'adminUi.dateText(' ) === false )
		$noHelper[] = $script;
}

$shellScripts = $read( '_admin/Admin.php' );

check( 'a date of the panels that show one is written by dateText() and by no formatting of the browser'. ( $ownDates === [] && $noHelper === [] ? '' : ' - '. implode( ', ', array_merge( $ownDates, $noHelper ) ) ), $ownDates === [] && $noHelper === [] );
check( '...which is a file of its own, bundled after the design system and before the shell and every panel', preg_match( '/^\t\tdateText : function\(/m', $bare( $read( '_admin/assets/datetext.js' ) ) ) === 1
	&& strpos( $shellScripts, "'/_admin/assets/Nino.admin.js'," ) !== false && strpos( $shellScripts, "'/_admin/assets/datetext.js'," ) !== false && strpos( $shellScripts, "'/_admin/assets/script.js'," ) !== false
	&& strpos( $shellScripts, "'/_admin/assets/Nino.admin.js'," ) < strpos( $shellScripts, "'/_admin/assets/datetext.js'," ) && strpos( $shellScripts, "'/_admin/assets/datetext.js'," ) < strpos( $shellScripts, "'/_admin/assets/script.js'," ) );

$unnamed = [];

foreach( [ '_admin/Nino/Modules/Language/assets/admin.js', '_admin/Nino/Modules/Language/assets/translations.js' ] as $script ) {

	$source = $bare( $read( $script ) );

	if( str_contains( $source, 'adminUi.localeName(' ) === false || preg_match( '/\blocale\.name\b|\bentry\.name\b/', $source ) === 1 )
		$unnamed[] = $script;
}

check( 'a language of the Language panel is named by localeName(), never by the name the server sends or by its code alone'. ( $unnamed === [] ? '' : ' - '. implode( ', ', $unnamed ) ), $unnamed === [] );
check( 'the Translations tab is built from the blocks of a form: the file, the code field and the select, and no card of its own', str_contains( $bare( $read( '_admin/Nino/Modules/Language/assets/translations.js' ) ), 'adminUi.fileField(' ) === true
	&& str_contains( $bare( $read( '_admin/Nino/Modules/Language/assets/translations.js' ) ), 'adminUi.codeField(' ) === true && str_contains( $bare( $read( '_admin/Nino/Modules/Language/assets/translations.js' ) ), 'adminUi.selectField(' ) === true
	&& preg_match( '/admin-translation|admin-step-number|nino-admin-card|<h2>/', $bare( $read( '_admin/Nino/Modules/Language/assets/translations.js' ) ) ) === 0 );
check( 'the Dashboard has the head the shell draws for every list: its panel does not answer head() false', str_contains( $bare( $read( '_admin/Nino/Modules/Dashboard/Admin/Admin.php' ) ), 'function head()' ) === false );

$sheeted = [ '_admin/Nino/Modules/Logs/assets/admin.css', '_admin/Nino/Modules/Dashboard/assets/admin.css', '_admin/Nino/Modules/Language/assets/admin.css', '_nino/Nino/Modules/Form/assets/admin.css', '_nino/Nino/Modules/Maintenance/assets/admin.css', '_admin/Nino/Modules/Images/assets/admin.css', '_admin/Nino/Modules/Elements/assets/admin.css' ];
$untoken = [];

foreach( $sheeted as $sheet ) {
	[ , , $stray ] = $strays( $bare( $read( $sheet ) ), [] );

	foreach( $stray as $found )
		$untoken[] = $sheet. ': '. $found;
}

$unshipped = array_values( array_filter( $sheeted, static fn( string $sheet ): bool => str_contains( $read( dirname( $sheet, 2 ). '/Admin/Admin.php' ), "'/assets/admin.css'" ) === false ) );

check( 'every one of those stylesheets is named by the assets() of its panel, which is what puts it in the bundle'. ( $unshipped === [] ? '' : ' - not '. implode( ', ', $unshipped ) ), $unshipped === [] );
check( 'the stylesheets of the panels that have gone over name type, weight and space only through the tokens'. ( $untoken === [] ? '' : ' - '. implode( '; ', array_slice( $untoken, 0, 6 ) ) ), $untoken === [] );

// --- Routes and Navigations: the build the guide asks of a list, a form and an address ---------------------------

/*	The two scripts are held by the lists above (bar, list, form, no open sentence). What is held here is what is theirs alone: the
	choice of a block for a tick, an icon and a language, the address of every view, no stylesheet of their own and no question
	of the browser's. Held by structure - the call of the block, never a word	*/
$routing = [ '_admin/Nino/Modules/Routes/assets/admin.js', '_nino/Nino/Modules/Navigation/assets/admin.js' ];
$ticked = [];
$iconless = [];
$unaddressed = [];
$native = [];

foreach( $routing as $script ) {

	$source = (string) preg_replace( '~^\s*(//|\*|/\*).*$~m', '', $bare( $read( $script ) ) );

	if( preg_match( '/type\s*=\s*[\'"]checkbox|nino-admin-checkbox-field|nino-admin-switch\b/', $source ) === 1 )
		$ticked[] = $script;

	if( str_contains( $source, 'adminUi.iconButton(' ) === false || preg_match( '/[↑↓×↗]/u', $source ) === 1 )
		$iconless[] = $script;

	if( str_contains( $source, 'router.set(' ) === false || str_contains( $source, 'router.href(' ) === false || str_contains( $source, 'router.go(' ) === false || str_contains( $source, 'router.leave(' ) === false )
		$unaddressed[] = $script;

	if( preg_match( '/\b(window|wn)\.(confirm|alert|prompt)\(|(?<![\w.])(alert|prompt|confirm)\(/', $source ) === 1 || str_contains( $source, 'adminUi.confirm(' ) === false )
		$native[] = $script;
}

check( 'Routes and Navigations draw no tick of their own: a yes or no is the switch, several of a set the checklist'. ( $ticked === [] ? '' : ' - '. implode( ', ', $ticked ) ), $ticked === [] );
check( '...an icon in a row is the block\'s, with a name, and no arrow or cross character stands for one'. ( $iconless === [] ? '' : ' - '. implode( ', ', $iconless ) ), $iconless === [] );
check( '...every view has an address: the router is told it, links to it, moves by it and is asked before it is left'. ( $unaddressed === [] ? '' : ' - '. implode( ', ', $unaddressed ) ), $unaddressed === [] );
check( '...a question is the block\'s, never the browser\'s'. ( $native === [] ? '' : ' - '. implode( ', ', $native ) ), $native === [] );

$routesScript = (string) preg_replace( '~^\s*(//|\*|/\*).*$~m', '', $bare( $read( '_admin/Nino/Modules/Routes/assets/admin.js' ) ) );

check( 'the values of every language of a route - name, title and address - are rows of localeRows(), the description follows the working language and says so', substr_count( $routesScript, 'adminUi.localeRows(' ) >= 3
	&& str_contains( $routesScript, 'adminUi.localeBadge(' ) === true && preg_match( '/workLocale\.follow\(\s*\'routes\'/', $routesScript ) === 1 && str_contains( $routesScript, 'adminUi.checklist(' ) === true && str_contains( $routesScript, 'adminUi.switchField(' ) === true );

$withStyle = array_values( array_filter( [ '_admin/Nino/Modules/Routes', '_nino/Nino/Modules/Navigation' ], static fn( string $dir ): bool => is_file( $root. '/'. $dir. '/assets/admin.css' ) === true
	|| str_contains( $read( $dir. '/Admin/Admin.php' ), "'/assets/admin.css'" ) === true ) );

check( 'neither panel ships a stylesheet of its own: what they draw is the blocks\' and the design system\'s'. ( $withStyle === [] ? '' : ' - '. implode( ', ', $withStyle ) ), $withStyle === [] );
// --- the Images panel and its two tabs are built of the blocks, and every action of them is said as the guide says it ---------------

/*	The Images panel is the guide's third model - nothing is saved by a button, an upload and an alt text are said by a toast and what is
	wrong at the field -, the Image Slots tab a list and a form, the Favicon tab a form with the colour block and the file block. Held by
	structure: which block draws a part, and that no part is drawn by hand, asked with the browser's dialogs or styled with a number	*/
$images = [
	'_admin/Nino/Modules/Images/assets/admin.js'		=> [ 'adminUi.localeRows(', 'adminUi.fileField(', 'adminUi.contextBar(', 'adminUi.confirm(', 'adminUi.say(', 'adminUi.list(' ],
	'_admin/Nino/Modules/Images/assets/slots.js'		=> [ 'adminUi.textField(', 'adminUi.checkField(', 'adminUi.confirm(', 'adminUi.say(' ],
	'_admin/Nino/Modules/Images/assets/favicon.js'	=> [ 'adminUi.color(', 'adminUi.fileField(', 'adminUi.confirm(', 'adminUi.dateText(', 'adminUi.sectionHelp(' ],
];

$unblocked = [];
$browser = [];
$bareFields = [];

foreach( $images as $script => $blocks ) {

	$source = (string) preg_replace( '~^\s*//.*$~m', '', $bare( $read( $script ) ) );

	foreach( $blocks as $block ) {
		if( str_contains( $source, $block ) === false )
			$unblocked[] = $script. ': '. $block;
	}

	if( preg_match( '/\b(?:wn|window)\.(?:alert|confirm|prompt)\(|\bcreateElement\(\s*\'(?:label|input|select|textarea)\'\s*\)|formToolbar\(|\.type\s*=\s*\'file\'|nino-admin-field-image-msg/', $source ) === 1 )
		$browser[] = $script;

	if( preg_match( '/\bhint\s*:|\.className\s*=\s*\'nino-admin-hint/', $source ) === 1 )
		$bareFields[] = $script;
}

check( 'the three scripts of the Images panel draw with the blocks the guide names for each task'. ( $unblocked === [] ? '' : ' - not '. implode( ', ', $unblocked ) ), $unblocked === [] );
check( '...and ask with no dialog of the browser, build no label, input or select by hand, no bare file input, no toolbar of the old build and no line of the image message of the old build'. ( $browser === [] ? '' : ' - '. implode( ', ', $browser ) ), $browser === [] );
check( '...and put the sentence of no field under it: the help is the option of its block'. ( $bareFields === [] ? '' : ' - '. implode( ', ', $bareFields ) ), $bareFields === [] );

$imagesSource = $bare( $read( '_admin/Nino/Modules/Images/assets/admin.js' ) );

check( 'the Images panel has no bar and no Save, since every action of it is immediate: no actionBar(), no submit, and no form', str_contains( $imagesSource, 'actionBar(' ) === false && str_contains( $imagesSource, "createElement('form')" ) === false && str_contains( $imagesSource, "'submit'" ) === false );
check( '...an alt text is saved when its field is left: the script listens for the blur of the control of each language', preg_match( '/addEventListener\(\s*\'blur\'\s*,[^;]{0,80}_saveAlt\(/', $imagesSource ) === 1 );

$faviconSource = $bare( $read( '_admin/Nino/Modules/Images/assets/favicon.js' ) );

check( 'Undo and Reset of the favicon are asked about as the guide asks for what is overwritten - the set that stands is not kept by Undo, and Reset takes the kept one with it - in development as well', preg_match_all( '/adminUi\.confirm\(\s*\'destructive\'/', $faviconSource ) === 2 && preg_match_all( '/adminUi\.confirm\(\s*\'big\'/', $faviconSource ) === 0 );
check( '...and the colour fields are the colour block, not a text field of their own', preg_match_all( '/adminUi\.color\(/', $faviconSource ) === 1 && str_contains( $faviconSource, 'maxLength = 7' ) === true && str_contains( $faviconSource, "type = 'text'" ) === false );

$faviconSheet = $bare( $read( '_admin/Nino/Modules/Images/assets/favicon.css' ) );
[ , , $faviconStray ] = $strays( $faviconSheet, [] );

check( 'the stylesheet of the Favicon tab is named by its tab, and names type, weight and space only through the tokens'. ( $faviconStray === [] ? '' : ' - '. implode( '; ', array_slice( $faviconStray, 0, 6 ) ) ), $faviconStray === [] && str_contains( $read( '_admin/Nino/Modules/Images/Favicon/Favicon.php' ), "'/assets/favicon.css'" ) === true );

// --- the Elements panel and the Element Types tab are built of the blocks, and every view has an address ------------------------------

/*	The two scripts are held by the lists above (bar, list, form, no open sentence, novalidate). What is held here is what is theirs alone: the
	block that draws each kind of field, no language switch of their own - the language is the workbench's and the form follows it -, no
	control of the browser's own kind, no character for an icon, an address for every view that is written, linked to and asked about
	before it is left, and no stylesheet that restyles a block. Held by structure - the call of the block, never a word	*/
$elementsPanel = [
	'_admin/Nino/Modules/Elements/assets/admin.js'	=> [ 'adminUi.switchField(', 'adminUi.selectField(', 'adminUi.textareaField(', 'adminUi.codeField(', 'adminUi.fileField(', 'adminUi.dateTime(', 'adminUi.stringList(', 'adminUi.elementList(', 'adminUi.accordion(', 'adminUi.fillsButton(', 'adminUi.localeBadge(', 'adminUi.contextBar(', 'adminUi.table(', 'adminUi.confirm(' ],
	'_admin/Nino/Modules/Elements/assets/types.js'	=> [ 'adminUi.selectField(', 'adminUi.checklist(', 'adminUi.numberField(', 'adminUi.switchField(', 'adminUi.iconButton(', 'adminUi.formDialog(', 'adminUi.confirm(', 'adminUi.fieldHelp(', 'adminUi.sectionTitle(' ],
];

$unblocked = [];
$ownKinds = [];
$unaddressed = [];

foreach( $elementsPanel as $script => $blocks ) {

	$source = (string) preg_replace( '~^\s*(//|\*|/\*).*$~m', '', $bare( $read( $script ) ) );

	foreach( $blocks as $block ) {
		if( str_contains( $source, $block ) === false )
			$unblocked[] = $script. ': '. $block;
	}

	// A language switch of the panel's own, the old pair of radios, a bare file input, the browser's dialogs, and the characters that stood for an icon
	if( preg_match( '/localeSelect\(|type\s*=\s*\'(?:radio|file)\'|nino-admin-field-radio|\b(?:wn|window)\.(?:alert|confirm|prompt)\(|[↑↓✕]/u', $source ) === 1 )
		$ownKinds[] = $script;

	if( str_contains( $source, 'router.set(' ) === false || str_contains( $source, 'router.href(' ) === false || str_contains( $source, 'router.go(' ) === false || str_contains( $source, 'router.leave(' ) === false || str_contains( $source, "'@new'" ) === false )
		$unaddressed[] = $script;
}

check( 'the Elements panel and the Element Types tab draw with the blocks the guide names for each task'. ( $unblocked === [] ? '' : ' - not '. implode( ', ', $unblocked ) ), $unblocked === [] );
check( '...with no language switch of their own, no radios, no bare file input, no dialog of the browser and no character for an icon'. ( $ownKinds === [] ? '' : ' - '. implode( ', ', $ownKinds ) ), $ownKinds === [] );
check( '...and every view has an address - the new one is @new, which no element or type can be called - that is written, linked to, moved by and asked before it is left'. ( $unaddressed === [] ? '' : ' - '. implode( ', ', $unaddressed ) ), $unaddressed === [] );

$elementsSource = (string) preg_replace( '~^\s*(//|\*|/\*).*$~m', '', $bare( $read( '_admin/Nino/Modules/Elements/assets/admin.js' ) ) );

check( 'the form of an element follows the language the workbench edits content in, and shows it in its bar', preg_match( '/workLocale\.follow\(\s*\'elements\'/', $elementsSource ) === 1 && str_contains( $elementsSource, 'adminUi.localeBadge(' ) === true && str_contains( $elementsSource, 'workLocale.set(' ) === false );

$typesSource = (string) preg_replace( '~^\s*(//|\*|/\*).*$~m', '', $bare( $read( '_admin/Nino/Modules/Elements/assets/types.js' ) ) );

check( 'the label of a field of a type is at the left of it like every label: no placeholder stands for a name, and the question for a deletion asks for the identifier to be typed', preg_match( '/placeholder\s*:\s*text\(\s*\'\/_admin\/types\/placeholder\/(?!options)/', $typesSource ) === 0
	&& preg_match( '/typed\s*:\s*uri/', $typesSource ) === 1 );

$elementsSheet = $bare( $read( '_admin/Nino/Modules/Elements/assets/admin.css' ) );

check( 'the stylesheet of the Elements panel restyles no block: it names the ids of its own views only, and no number for type, weight or space', preg_match( '/^(?!#elements-form|#types-form|#types-fields|\s|\/|@|\}|$)/m', $elementsSheet ) === 0 );

// --- the chapter is linked and its links land ----------------------------------------------------------

$chapter = $read( 'docs/admin-ui.md' );

/*	The anchors of a document's headings, as guide-smoke.php reads them	*/
$prose = (string) preg_replace( '/^```.*?^```[^\n]*$/ms', '', $chapter );
preg_match_all( '/^#{1,6}[ \t]+(.+?)[ \t]*$/m', $prose, $headings );

$anchors = [];

foreach( $headings[1] as $heading )
	$anchors[] = str_replace( ' ', '-', (string) preg_replace( '/[^\p{L}\p{N} _-]/u', '', mb_strtolower( $heading, 'UTF-8' ) ) );

preg_match_all( '/\]\(#([^)\s]+)\)/', $prose, $own );

$dead = array_values( array_diff( array_unique( $own[1] ), $anchors ) );

check( 'the chapter has links within itself, and each lands on a heading'. ( $dead === [] ? '' : ' - not '. implode( ', ', $dead ) ), $own[1] !== [] && $dead === [] );
check( 'the agent guide and the panel recipe link to the chapter', str_contains( $read( 'AGENTS.md' ), '(docs/admin-ui.md)' ) === true && str_contains( $read( 'docs/recipes/admin-panel.md' ), '(../admin-ui.md)' ) === true );

$none = [];
ninoDone( $none );
