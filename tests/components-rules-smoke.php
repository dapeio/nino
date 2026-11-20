<?php
declare(strict_types=1);

/**
 *	Nino
 *	components-rules-smoke.php	Contract test for the rules of a place as an API - what a tool may offer and what the server
 *															finds wrong in a tree - and for the layout of a tree that is new:
 *
 *																A. Components::offer(): the places of allowed(), and the rules on top of them: once, depth, a container
 *																	 of the flow in itself, a loop that is through more than one level, html as a reason of its own
 *																B. Components::check(): one finding for each rule - unknown, bare, variant, outside, foreign, place,
 *																	 once, depth, self, duplicate, free - at the position the trace uses; and the templates the kernel
 *																	 delivers, which have nothing against them but html that is free and calls that have no schema
 *																C. Markup::layout(): a new tree written as by hand, content that is inline left alone, and the
 *																	 round trip through write() and parse()
 *
 *	Usage: php tests/components-rules-smoke.php
 */

// A project with no features of its own: the one feature of the run is the one the kernel delivers
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-rules-features-'. getmypid() );
mkdir( NINO_FEATURES_DIR, 0755, true );
register_shutdown_function( static function(): void {
	@rmdir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';

$ignored = ninoWarnings();
$root = dirname( __DIR__ );

/**
 *	A project with basis on, the menus the templates name, the shortcodes they call and, where $own, the components of the
 *	test: three that hold the flow, one inside the other, and a loop
 *
 *	@param		bool			$own
 *
 *	@return 	array										$appData
 */
function rulesSite( bool $own ): array {

	$appData = ninoSandbox( 'rules' );
	$appData['./nino/filesystem/path'] = dirname( __DIR__ );
	$appData['/nino/modules'] = [ '\\Nino\\Modules\\Basis' ];

	$appData['/nino/html/navs'] = [ 'main', 'footer' ];

	\Nino\Features::registerComponents( $appData );

	// The calls of the templates that are no component: the names that the modules and the kernel register
	foreach( [ 'template', 'frame', 'assets', 'jstext', 'legal', 'privacy', 'json', 'imagevalues', 'csrf' ] as $shortcode )
		\Nino\Html::addShortcode( $appData, $shortcode, static fn(): string => '', null );

	if( $own === false )
		return $appData;

	foreach( [ 'rule-card', 'rule-deep', 'rule-third', 'rule-fourth' ] as $name )
		\Nino\Components::addComponent( $appData, $name, [ 'label' => $name, 'attributes' => [ 'count' => [ 'type' => 'int', 'default' => 1, 'min' => 1, 'max' => 3, 'label' => 'Count' ] ], 'in' => [ 'flow' ], 'offers' => [ 'flow' ], 'template' => '<div>[[content]]</div>' ] );

	// A loop: stands where a row, a column or the page has room, draws no element of its own
	\Nino\Components::addComponent( $appData, 'rule-loop', [ 'label' => 'Loop', 'in' => [ 'page', 'flow', 'section-row' ], 'offers' => [ 'page', 'flow', 'section-row' ], 'through' => true, 'children' => 'nodes', 'template' => '' ], static fn(): string => '' );
	\Nino\Components::addComponent( $appData, 'rule-filter', [ 'label' => 'Filter', 'in' => [ 'page', 'flow', 'section-row' ], 'offers' => [ 'page', 'section-row' ], 'through' => true, 'children' => 'nodes', 'template' => '' ], static fn(): string => '' );
	// A container that draws nothing and offers nothing, and the one component that names it
	\Nino\Components::addComponent( $appData, 'rule-bare', [ 'label' => 'Bare', 'in' => [ 'section-row' ], 'offers' => [], 'through' => true, 'children' => 'nodes', 'template' => '' ], static fn(): string => '' );
	\Nino\Components::addComponent( $appData, 'rule-named', [ 'label' => 'Named', 'in' => [ 'section-row', 'rule-bare' ], 'template' => '<i></i>' ] );
	\Nino\Html::addShortcode( $appData, 'rule-short', static fn(): string => '', true );

	return $appData;
}

/**
 *	The findings of a source as position => the codes, in the order they were found
 *
 *	@param		array 		&$appData			(reference)
 *	@param		string		$source
 *
 *	@return 	array
 */
function rulesCodes( array &$appData, string $source ): array {

	$notes = [];
	$found = \Nino\Components::check( $appData, \Nino\Markup::parse( $source, \Nino\Components::containers( $appData ), $notes ) );

	return array_map( static fn( array $list ): array => array_column( $list, 'code' ), $found );
}

$appData = rulesSite( true );
$basis = rulesSite( false );

// --- A. offer() ----------------------------------------------------------------------------------------

echo "A. offer(): where a component may be put\n";

// The way from the page to each container of basis: the first chain that allowed() gives
$chains = [ '' => [] ];
$containers = \Nino\Components::containers( $basis );
$queue = [ [] ];

while( $queue !== [] ) {

	$path = array_shift( $queue );

	foreach( \Nino\Components::allowed( $basis, $path === [] ? '' : $path[count( $path ) - 1] ) as $name )
		if( isset( $chains[$name] ) === false && isset( $containers[$name] ) === true && $containers[$name] === true ) {
			$chains[$name] = array_merge( $path, [ $name ] );
			$queue[] = $chains[$name];
		}
}

$differs = [];

foreach( array_keys( $containers, true, true ) as $container ) {

	if( isset( $chains[$container] ) === false )
		continue;

	$offered = \Nino\Components::offer( $basis, $chains[$container], [] );
	$wrong = array_filter( $offered, static fn( mixed $answer, string $name ): bool => $answer !== ( $name === 'html' ? 'html' : true ), ARRAY_FILTER_USE_BOTH );

	if( array_keys( $offered ) !== \Nino\Components::allowed( $basis, $container ) || $wrong !== [] )
		$differs[] = $container;
}

check( 'for each container of basis offer() names what allowed() names, each one true and html the reason html'. ( $differs === [] ? '' : ' - '. implode( ', ', $differs ) ), $differs === [] && count( $chains ) >= 8 );
check( 'the page offers the same as the empty path', array_keys( \Nino\Components::offer( $appData, [], [] ) ) === \Nino\Components::allowed( $appData, '' ) );

$notes = [];
$once = \Nino\Components::offer( $appData, [ 'header-wrap' ], \Nino\Markup::parse( '[header-logo][header-nav /]', \Nino\Components::containers( $appData ), $notes ) );
check( 'a component that is once in a container is offered no more where one stands, and the others still are', $once['header-logo'] === 'once' && $once['header-nav'] === 'once' && $once['header-actions'] === true );

$self = \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row', 'section-col', 'rule-card' ], [] );
check( 'a container of the flow is no place for itself and the others are', $self['rule-card'] === 'self' && $self['rule-deep'] === true && $self['text'] === true );

$deep = \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row', 'section-col', 'rule-card', 'rule-deep' ], [] );
$deeper = \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row', 'section-col', 'rule-card', 'rule-deep', 'rule-third' ], [] );
check( 'the sixth level is offered and the seventh is not', $deep['text'] === true && $deeper['text'] === 'depth' && $deeper['button-group'] === 'depth' );

$through = \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row', 'rule-filter', 'rule-loop' ], [] );
check( 'two containers that draw nothing, one in the other, offer what their row offers', array_keys( $through ) === array_keys( \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row' ], [] ) ) && $through['section-col'] === true );

$bare = \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row', 'rule-bare' ], [] );
check( 'a container that draws nothing and offers nothing holds only what names it', array_keys( $bare ) === [ 'rule-named' ] );

check( 'a container that draws nothing is not offered inside itself, however far up it stands', \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row', 'rule-filter', 'rule-loop' ], [] )['rule-filter'] === 'self' );

$page = \Nino\Components::offer( $appData, [ 'rule-loop' ], [] );
check( 'a loop that stands directly in the page offers the places of the page', array_keys( $page ) === array_keys( \Nino\Components::offer( $appData, [], [] ) ) && isset( $page['section-col'] ) === false );

$flat = \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row', 'section-col', 'rule-card', 'rule-deep', 'rule-loop' ], [] );
check( 'a container that draws nothing is no level', $flat['text'] === true );

$column = \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row', 'section-col' ], [] );
check( 'html is told by its name, the reason of a component that is otherwise offered', $column['html'] === 'html' && $column['text'] === true );

// --- B. check() ----------------------------------------------------------------------------------------

echo "\nB. check(): what is wrong with a tree\n";

check( 'a tree with nothing against it has no finding', rulesCodes( $appData, '[section-wrap id="a"][section-row][section-col size="100 m:50"][text text="x" /][rule-card count="2" /][/section-col][/section-row][/section-wrap]' ) === [] );
check( 'F1: a name that nobody registered is unknown, at its position', rulesCodes( $appData, '[section-wrap][nobody][/section-wrap]' ) === [ '0.0' => [ 'unknown' ] ] );
check( 'a shortcode is a call that has no schema, and what it holds is not looked into', rulesCodes( $appData, '[rule-short]<b>[nobody][/rule-short]' ) === [ '0' => [ 'bare' ] ] );
check( 'F2: a variant the component does not know', rulesCodes( $appData, '[section-wrap variant="nope"]' ) === [ '0' => [ 'variant' ] ] );
check( 'F3: a value outside what the attribute takes, in a list and in a responsive one', rulesCodes( $appData, '[section-wrap ground="nope"][section-row][section-col size="100 q:50"][rule-card count="9" /][/section-col][section-col size="100 m:50 m:33" /][/section-row][/section-wrap]' ) === [ '0' => [ 'outside' ], '0.0.0' => [ 'outside' ], '0.0.0.0' => [ 'outside' ], '0.0.1' => [ 'outside' ] ] );
check( 'F3: an attribute that the schema does not name', rulesCodes( $appData, '[section-wrap class="x"]' ) === [ '0' => [ 'foreign' ] ] );
check( 'F7: a component at a place that does not offer one for it', rulesCodes( $appData, '[section-col /]' ) === [ '0' => [ 'place' ] ] );
check( 'K4: the second of a component that is once is found, not the first', rulesCodes( $appData, '[header-wrap][header-logo /][header-logo /][/header-wrap]' ) === [ '0.1' => [ 'once' ] ] );
check( 'self: a container of the flow in itself', rulesCodes( $appData, '[section-wrap][section-row][section-col][rule-card][rule-card][/rule-card][/rule-card][/section-col][/section-row][/section-wrap]' ) === [ '0.0.0.0.0' => [ 'self' ] ] );
check( 'K5: the seventh level is found once, and what stands below it is looked at all the same', rulesCodes( $appData, '[section-wrap][section-row][section-col][rule-card][rule-deep][rule-third][rule-fourth][text /][nobody]<b>[/rule-fourth][/rule-third][/rule-deep][/rule-card][/section-col][/section-row][/section-wrap]' ) === [ '0.0.0.0.0.0.0' => [ 'depth' ], '0.0.0.0.0.0.0.1' => [ 'unknown' ], '0.0.0.0.0.0.0.2' => [ 'free' ] ] );
check( 'two containers that draw nothing in a row are checked against what the row offers', rulesCodes( $appData, '[section-wrap][section-row][rule-filter][rule-loop][section-col /][/rule-loop][/rule-filter][/section-row][/section-wrap]' ) === [] );
check( 'a column in a container that names no column is at the wrong place, what names it is not', rulesCodes( $appData, '[section-wrap][section-row][rule-bare][section-col /][rule-named /][/rule-bare][/section-row][/section-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ] );
check( 'a container that draws nothing in itself, through another, is found', rulesCodes( $appData, '[section-wrap][section-row][rule-filter][rule-loop][rule-filter][/rule-filter][/rule-loop][/rule-filter][/section-row][/section-wrap]' ) === [ '0.0.0.0.0' => [ 'self' ] ] );
check( 'a container that draws nothing in the page leaves the page its places', rulesCodes( $appData, '[rule-loop][section-col /][/rule-loop]' ) === [ '0.0' => [ 'place' ] ] );
check( 'a container that draws nothing is no level for K5', rulesCodes( $appData, '[section-wrap][section-row][section-col][rule-card][rule-deep][rule-loop][text /][/rule-loop][/rule-deep][/rule-card][/section-col][/section-row][/section-wrap]' ) === [] );
check( 'an id that stands twice is found at the second', rulesCodes( $appData, '[section-wrap id="a"][/section-wrap][section-wrap id="a"][/section-wrap]' ) === [ '1' => [ 'duplicate' ] ] );
check( 'html between the calls is free, a call of html is told by its name, and what it holds is its content', rulesCodes( $appData, '<p>[html]<b>x</b>[/html]' ) === [ '0' => [ 'free' ], '1' => [ 'html' ] ] );

$source = '[section-wrap][section-row][nobody][/section-row][/section-wrap]';
$notes = [];
$found = \Nino\Components::check( $appData, \Nino\Markup::parse( $source, \Nino\Components::containers( $appData ), $notes ) );
check( 'a finding is a code and a detail, by the position of the trace', $found === [ '0.0.0' => [ [ 'code' => 'unknown', 'detail' => 'nobody' ] ] ] );

// What the kernel delivers: the templates of the starter site, the demos of basis, the fixture page of the notation
$files = array_merge(
	glob( $root. '/_admin/install/library/*/templates/*.tpl' ) ?: [],
	glob( $root. '/_admin/install/library/pages/*/templates/*.tpl' ) ?: [],
	glob( $root. '/_nino/Nino/Modules/Basis/groups/*/demo.tpl' ) ?: [],
	glob( $root. '/_nino/Nino/Modules/Legal/install/templates/*.tpl' ) ?: [],
	[ $root. '/tests/fixtures/basis/page.tpl' ]
);

$codes = [];

foreach( $files as $file ) {

	$notes = [];
	$tree = \Nino\Markup::parse( (string) file_get_contents( $file ), \Nino\Components::containers( $appData ), $notes );

	foreach( \Nino\Components::check( $appData, $tree ) as $list )
		foreach( $list as $finding )
			$codes[$finding['code']] = ( $codes[$finding['code']] ?? 0 ) + 1;
}

ksort( $codes );
echo '  '. count( $files ). ' files, findings: '. json_encode( $codes ). "\n";
check( 'the templates and the demos that come with the kernel have nothing against them but free html, calls with no schema and the html component', count( $files ) >= 14 && array_diff( array_keys( $codes ), [ 'free', 'bare', 'html' ] ) === [] );

// --- C. layout() ---------------------------------------------------------------------------------------

echo "\nC. layout(): a new tree as by hand\n";

$containers = \Nino\Components::containers( $basis );
$by = "[section-wrap id=\"x\"]\n\t[section-row]\n\t\t[section-col]\n\t\t\t[section-title ~title]\n\t\t\t[html]Some [[/a/b]] words[/html]\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]";
$notes = [];
$new = \Nino\Markup::parse( preg_replace( '/\s*\n\s*/', '', $by ) ?? '', $containers, $notes );

check( 'a tree that has no blank is written as the template that was written by hand', \Nino\Markup::write( \Nino\Markup::layout( $new, 0 ), $containers ) === $by );
check( 'the depth says how far the first level is in', \Nino\Markup::write( \Nino\Markup::layout( $new, 1 ), $containers ) === "[section-wrap id=\"x\"]\n\t\t[section-row]\n\t\t\t[section-col]\n\t\t\t\t[section-title ~title]\n\t\t\t\t[html]Some [[/a/b]] words[/html]\n\t\t\t[/section-col]\n\t\t[/section-row]\n\t[/section-wrap]" );

$inline = \Nino\Markup::parse( '[section-col][text]a [b] c[/text]<p>x[section-title ~t /]</p>[/section-col]', $containers, $notes );
check( 'a level with words or html in it is inline and stays as it is', \Nino\Markup::layout( $inline, 0 ) === $inline );

$raw = "[section-wrap]\n\t[section-row]\n\t[/section-row]\n[/section-wrap]\n[section-wrap][/section-wrap]";
$tree = \Nino\Markup::parse( $raw, $containers, $notes );
$round = \Nino\Markup::parse( \Nino\Markup::write( \Nino\Markup::layout( $tree, 0 ), $containers ), $containers, $notes );
$bare = static function( array $nodes ) use ( &$bare ): array {

	$out = [];

	foreach( $nodes as $node ) {

		if( isset( $node['n'] ) === false ) {

			if( trim( (string) ( $node['t'] ?? '' ) ) !== '' || isset( $node['f'] ) === true )
				$out[] = $node;

			continue;
		}

		unset( $node['o'] );

		if( is_array( $node['c'] ) === true )
			$node['c'] = $bare( $node['c'] );

		$out[] = $node;
	}

	return $out;
};
check( 'parse( write( layout( x ) ) ) is the tree of x but for the blank', $bare( $round ) === $bare( $tree ) && $bare( $tree ) !== [] );

ninoDone( $appData );
