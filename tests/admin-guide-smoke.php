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

/*	The declarations of style.css that are no custom property: [ selector, property, value ] in the order they stand, a rule inside a
	media query by its own selector	*/
$declarations = [];

preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $style, $rules, PREG_SET_ORDER );

foreach( $rules as $rule ) {
	foreach( explode( ';', $rule[2] ) as $declaration ) {

		$parts = explode( ':', $declaration, 2 );

		if( count( $parts ) === 2 && str_starts_with( trim( $parts[0] ), '--' ) === false )
			$declarations[] = [ trim( (string) preg_replace( '/\s+/', ' ', $rule[1] ) ), trim( $parts[0] ), trim( (string) preg_replace( '/\s+/', ' ', $parts[1] ) ) ];
	}
}

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

// --- the panels that have gone over to the blocks of the bar ------------------------------------------

/*	The panels that draw their bar and their way back with actionBar() and contextBar() and not by hand. The list grows: the
	patch that brings another panel over adds its script here, and from then on the panel keeps to it	*/
$converted = [
	'_admin/Nino/Modules/Routes/assets/admin.js',
	'_nino/Nino/Modules/Navigation/assets/admin.js',
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
