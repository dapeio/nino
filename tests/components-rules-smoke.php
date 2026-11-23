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
 *																	 once, depth, self, duplicate, free - at the position the trace uses; and the templates of the
 *																	 test feature, which have nothing against them
 *																C. Markup::layout(): a new tree written as by hand, content that is inline left alone, and the
 *																	 round trip through write() and parse()
 *
 *																The components are those of tests/fixtures/kit/Kit; what the rules say of Basis is tests/basis-rules-smoke.php.
 *
 *	Usage: php tests/components-rules-smoke.php
 */

// The one feature of the run is the test feature of the kernel
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/kit' );

require __DIR__. '/harness.php';

$ignored = ninoWarnings();
$root = dirname( __DIR__ );

/**
 *	A project with the test feature on, the menus the templates name, the shortcodes they call and, where $own, the components of the
 *	test: three that hold the flow, one inside the other, and a loop
 *
 *	@param		bool			$own
 *
 *	@return 	array										$appData
 */
function rulesSite( bool $own ): array {

	$appData = ninoSandbox( 'rules' );
	$appData['./nino/filesystem/path'] = dirname( __DIR__ );
	$appData['/nino/modules'] = [ '\\Nino\\Modules\\Kit' ];

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
	\Nino\Components::addComponent( $appData, 'rule-loop', [ 'label' => 'Loop', 'in' => [ 'page', 'flow', 'band-row' ], 'offers' => [ 'page', 'flow', 'band-row' ], 'through' => true, 'children' => 'nodes', 'template' => '' ], static fn(): string => '' );
	\Nino\Components::addComponent( $appData, 'rule-filter', [ 'label' => 'Filter', 'in' => [ 'page', 'flow', 'band-row' ], 'offers' => [ 'page', 'band-row' ], 'through' => true, 'children' => 'nodes', 'template' => '' ], static fn(): string => '' );
	// A container that draws nothing and offers nothing, and the one component that names it
	\Nino\Components::addComponent( $appData, 'rule-bare', [ 'label' => 'Bare', 'in' => [ 'band-row' ], 'offers' => [], 'through' => true, 'children' => 'nodes', 'template' => '' ], static fn(): string => '' );
	\Nino\Components::addComponent( $appData, 'rule-named', [ 'label' => 'Named', 'in' => [ 'band-row', 'rule-bare' ], 'template' => '<i></i>' ] );
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
$kit = rulesSite( false );

// --- A. offer() ----------------------------------------------------------------------------------------

echo "A. offer(): where a component may be put\n";

// The way from the page to each container of the test feature: the first chain that allowed() gives - through a section: what a wrap with a 'deep' place adds to the rows below it is for offer() alone
$chains = [ '' => [] ];
$containers = \Nino\Components::containers( $kit );
$queue = [ [] ];

while( $queue !== [] ) {

	$path = array_shift( $queue );

	foreach( \Nino\Components::allowed( $kit, $path === [] ? '' : $path[count( $path ) - 1] ) as $name )
		if( isset( $chains[$name] ) === false && isset( $containers[$name] ) === true && $containers[$name] === true && ( $name !== 'band-row' || in_array( $path[count( $path ) - 1] ?? '', [ 'crown-wrap', 'foot-wrap' ], true ) === false ) ) {
			$chains[$name] = array_merge( $path, [ $name ] );
			$queue[] = $chains[$name];
		}
}

$differs = [];

foreach( array_keys( $containers, true, true ) as $container ) {

	if( isset( $chains[$container] ) === false )
		continue;

	$offered = \Nino\Components::offer( $kit, $chains[$container], [] );
	$wrong = array_filter( $offered, static fn( mixed $answer, string $name ): bool => $answer !== ( $name === 'raw' ? 'html' : true ), ARRAY_FILTER_USE_BOTH );

	if( array_keys( $offered ) !== \Nino\Components::allowed( $kit, $container ) || $wrong !== [] )
		$differs[] = $container;
}

check( 'for each container of the test feature offer() names what allowed() names, each one true and html the reason html'. ( $differs === [] ? '' : ' - '. implode( ', ', $differs ) ), $differs === [] && count( $chains ) >= 8 );
check( 'the page offers the same as the empty path', array_keys( \Nino\Components::offer( $appData, [], [] ) ) === \Nino\Components::allowed( $appData, '' ) );

// A place a container offers to every container of the flow below it, however deep: the logo and the menu of a header stand in its columns, and in no other
$notes = [];
$ofHere = static fn( array $path ): array => array_keys( array_filter( \Nino\Components::offer( $kit, $path, [] ), static fn( mixed $answer ): bool => $answer === true ) );
$parts = static fn( array $path ): array => array_values( array_intersect( $ofHere( $path ), [ 'crown-logo', 'crown-nav', 'foot-logo', 'foot-nav' ] ) );
check( 'deep: the logo and the menu of the header are offered in a column below the header - and the logo and the menu of the footer in one below the footer - and in no column of a section',
	$parts( [ 'crown-wrap', 'band-row', 'band-col' ] ) === [ 'crown-logo', 'crown-nav' ] && $parts( [ 'foot-wrap', 'band-row', 'band-col' ] ) === [ 'foot-logo', 'foot-nav' ]
	&& $parts( [ 'band-wrap', 'band-row', 'band-col' ] ) === [] );
check( 'deep: the places go to a container of the flow and to nothing else: not to a row, not to the parts that name the header in their own in (they keep what they offer), and the own column of the footer keeps its logo',
	$parts( [ 'crown-wrap', 'band-row' ] ) === [] && $parts( [ 'crown-wrap', 'crown-actions' ] ) === [] && $parts( [ 'foot-wrap', 'foot-col' ] ) === [ 'foot-logo', 'foot-nav' ] && $parts( [ 'crown-wrap' ] ) === [ 'crown-logo', 'crown-nav' ] );
// ...but not below a part of the container itself: a container of the flow in crown-actions or in foot-col is no column of a row, and keeps what it had
$ofOwn = static fn( array $path ): array => array_values( array_intersect( array_keys( array_filter( \Nino\Components::offer( $appData, $path, [] ), static fn( mixed $answer ): bool => $answer === true ) ), [ 'crown-logo', 'crown-nav', 'foot-logo', 'foot-nav' ] ) );
check( 'deep: a container of the flow below crown-actions or below foot-col is offered no part of the header or the footer, and one below a column of a row is - and check() finds the part there at the wrong place, or not',
	$ofOwn( [ 'crown-wrap', 'crown-actions', 'rule-card' ] ) === [] && $ofOwn( [ 'foot-wrap', 'foot-col', 'rule-card' ] ) === [] && $ofOwn( [ 'foot-wrap', 'foot-col', 'rule-card', 'rule-deep' ] ) === []
	&& $ofOwn( [ 'crown-wrap', 'band-row', 'band-col', 'rule-card' ] ) === [ 'crown-logo', 'crown-nav' ] && $ofOwn( [ 'foot-wrap', 'band-row', 'band-col', 'rule-card', 'rule-deep' ] ) === [ 'foot-logo', 'foot-nav' ]
	&& rulesCodes( $appData, '[crown-wrap][crown-actions][rule-card][crown-logo /][/rule-card][/crown-actions][/crown-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ]
	&& rulesCodes( $appData, '[foot-wrap][foot-col][rule-card][foot-nav /][/rule-card][/foot-col][/foot-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ]
	&& rulesCodes( $appData, '[crown-wrap][band-row][band-col][rule-card][crown-logo /][/rule-card][/band-col][/band-row][/crown-wrap]' ) === [] );

// A container of the flow that offers a place deep offers it below itself and not in itself: the part stands in what the container holds, however deep, and not beside it
\Nino\Components::addComponent( $appData, 'rule-owner', [ 'label' => 'Owner', 'in' => [ 'flow' ], 'offers' => [ 'flow' ], 'deep' => [ 'rule-place' ], 'template' => '<div>[[content]]</div>' ] );
\Nino\Components::addComponent( $appData, 'rule-part', [ 'label' => 'Part', 'in' => [ 'rule-place' ], 'template' => '<i></i>' ] );
$ownerHolds = static fn( array $path ): bool => ( \Nino\Components::offer( $appData, $path, [] )['rule-part'] ?? null ) === true;
check( 'deep: a container of the flow offers its deep place to the containers of the flow below it and not to itself, and check() finds the part directly in it at the wrong place',
	$ownerHolds( [ 'band-wrap', 'band-row', 'band-col', 'rule-owner' ] ) === false && $ownerHolds( [ 'band-wrap', 'band-row', 'band-col', 'rule-owner', 'rule-card' ] ) === true
	&& $ownerHolds( [ 'band-wrap', 'band-row', 'band-col' ] ) === false
	&& rulesCodes( $appData, '[band-wrap][band-row][band-col][rule-owner][rule-part /][/rule-owner][/band-col][/band-row][/band-wrap]' ) === [ '0.0.0.0.0' => [ 'place' ] ]
	&& rulesCodes( $appData, '[band-wrap][band-row][band-col][rule-owner][rule-card][rule-part /][/rule-card][/rule-owner][/band-col][/band-row][/band-wrap]' ) === [] );

// the order is the one the components were registered in, whether a name comes by the container or by a deep place
$registered = array_keys( \Nino\Components::components( $kit ) );
$inOrder = true;

foreach( [ [ 'crown-wrap', 'band-row', 'band-col' ], [ 'foot-wrap', 'band-row', 'band-col' ], [ 'crown-wrap' ], [ 'band-wrap', 'band-row', 'band-col' ] ] as $orderPath ) {

	$names = array_keys( \Nino\Components::offer( $kit, $orderPath, [] ) );
	$inOrder = $inOrder && $names === array_values( array_intersect( $registered, $names ) );
}

check( 'offer() names the components in the order they were registered in, the ones of a deep place among them', $inOrder === true && in_array( 'crown-logo', array_keys( \Nino\Components::offer( $kit, [ 'crown-wrap', 'band-row', 'band-col' ], [] ) ), true ) === true );

$deepOnce = \Nino\Components::offer( $kit, [ 'crown-wrap', 'band-row', 'band-col' ], \Nino\Markup::parse( '[crown-logo /]', \Nino\Components::containers( $kit ), $notes ) );
check( 'deep: once is a rule of the container, so that two columns have a menu each', $deepOnce['crown-logo'] === 'once' && $deepOnce['crown-nav'] === true
	&& \Nino\Components::offer( $kit, [ 'crown-wrap', 'band-row', 'band-col' ], [] )['crown-logo'] === true );
check( 'deep: check() finds a logo in a column of a section at the wrong place, and none in a column of the header or of the footer for the footer logo',
	rulesCodes( $kit, '[band-wrap][band-row][band-col][crown-logo /][/band-col][/band-row][/band-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ]
	&& rulesCodes( $kit, '[crown-wrap][band-row][band-col][crown-logo /][crown-nav /][/band-col][band-col][crown-nav /][/band-col][/band-row][/crown-wrap]' ) === []
	&& rulesCodes( $kit, '[foot-wrap][band-row][band-col][foot-logo /][foot-nav /][/band-col][/band-row][/foot-wrap]' ) === []
	&& rulesCodes( $kit, '[foot-wrap][band-row][band-col][crown-logo /][/band-col][/band-row][/foot-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ] );

$schemaOf = static fn( array $more ): array|string => \Nino\Components::validate( 'deep-probe', [ 'label' => 'x', 'offers' => [ 'flow' ] ] + $more );
check( 'deep: a schema names it for a container, as a list of places that is not empty; it is kept as it is, and what is wrong with it is refused',
	is_array( $schemaOf( [ 'deep' => [ 'place' ] ] ) ) === true && $schemaOf( [ 'deep' => [ 'place' ] ] )['deep'] === [ 'place' ] && \Nino\Components::validate( 'deep-probe', $schemaOf( [ 'deep' => [ 'place' ] ] ) ) === $schemaOf( [ 'deep' => [ 'place' ] ] )
	&& array_filter( [ $schemaOf( [ 'deep' => 'place' ] ), $schemaOf( [ 'deep' => [] ] ), $schemaOf( [ 'deep' => [ 'Place' ] ] ), $schemaOf( [ 'deep' => [ 'a', 'a' ] ] ), $schemaOf( [ 'deep' => [ 1 ] ] ), \Nino\Components::validate( 'deep-probe', [ 'label' => 'x', 'deep' => [ 'place' ] ] ) ], 'is_string' ) !== []
	&& count( array_filter( [ $schemaOf( [ 'deep' => 'place' ] ), $schemaOf( [ 'deep' => [] ] ), $schemaOf( [ 'deep' => [ 'Place' ] ] ), $schemaOf( [ 'deep' => [ 'a', 'a' ] ] ), $schemaOf( [ 'deep' => [ 1 ] ] ), \Nino\Components::validate( 'deep-probe', [ 'label' => 'x', 'deep' => [ 'place' ] ] ), $schemaOf( [ 'deep' => null ] ), $schemaOf( [ 'deep' => [ 'page' ] ] ), $schemaOf( [ 'deep' => [ 'ok', 'flow' ] ] ) ], 'is_string' ) ) === 9 );


// block: the root of a block, of the side of a page the word says, for a container that stands in the page; and the two flags, markup and logo
$rootOf = static fn( array $more ): array|string => \Nino\Components::validate( 'block-probe', [ 'label' => 'x', 'in' => [ 'page' ], 'offers' => [] ] + $more );
$sidesTaken = [];
$sidesRefused = [];

foreach( \Nino\Components::SIDES as $side ) {

	$answer = $rootOf( [ 'block' => $side ] );

	if( is_array( $answer ) === false || $answer['block'] !== $side || \Nino\Components::validate( 'block-probe', $answer ) !== $answer )
		$sidesTaken[] = $side;
}

foreach( [ 'a word that is no side' => $rootOf( [ 'block' => 'sidebar' ] ), 'a list' => $rootOf( [ 'block' => [ 'page' ] ] ), 'true' => $rootOf( [ 'block' => true ] ), 'null' => $rootOf( [ 'block' => null ] ), 'an empty word' => $rootOf( [ 'block' => '' ] ),
	'a leaf' => \Nino\Components::validate( 'block-probe', [ 'label' => 'x', 'in' => [ 'page' ], 'block' => 'header' ] ), 'a container of the flow only' => \Nino\Components::validate( 'block-probe', [ 'label' => 'x', 'in' => [ 'flow' ], 'offers' => [], 'block' => 'footer' ] ),
	'a container nowhere' => \Nino\Components::validate( 'block-probe', [ 'label' => 'x', 'offers' => [], 'block' => 'page' ] ) ] as $label => $answer )
	if( is_string( $answer ) === false )
		$sidesRefused[] = $label;

check( 'block: a container that stands in the page names the side of its block, page, header or footer, and the clean schema keeps it; a word that is none, a value that is no word, and a component that is no container or does not stand in the page are refused'
	. ( $sidesTaken === [] && $sidesRefused === [] ? '' : ' - '. implode( ', ', array_merge( $sidesTaken, $sidesRefused ) ) ), $sidesTaken === [] && $sidesRefused === [] && \Nino\Components::SIDES === [ 'page', 'header', 'footer' ] && array_key_exists( 'block', (array) $rootOf( [] ) ) === false );
check( 'markup and logo: true is kept, false is none, anything else is refused, and markup is for a container',
	( $rootOf( [ 'markup' => true ] )['markup'] ?? false ) === true && array_key_exists( 'markup', (array) $rootOf( [ 'markup' => false ] ) ) === false && is_string( $rootOf( [ 'markup' => 'yes' ] ) ) === true
	&& is_string( \Nino\Components::validate( 'block-probe', [ 'label' => 'x', 'markup' => true ] ) ) === true && is_string( $rootOf( [ 'logo' => 1 ] ) ) === true
	&& ( \Nino\Components::validate( 'block-probe', [ 'label' => 'x', 'source' => 'image', 'logo' => true ] )['logo'] ?? false ) === true && array_key_exists( 'logo', (array) $rootOf( [ 'logo' => false ] ) ) === false );
check( 'a pair that means nothing is refused: a root that is markup, a root that is through, and a logo whose source is no image',
	is_string( $rootOf( [ 'block' => 'page', 'markup' => true ] ) ) === true && is_string( $rootOf( [ 'block' => 'page', 'through' => true, 'children' => 'nodes' ] ) ) === true
	&& is_string( \Nino\Components::validate( 'block-probe', [ 'label' => 'x', 'logo' => true ] ) ) === true && is_string( \Nino\Components::validate( 'block-probe', [ 'label' => 'x', 'source' => 'text', 'logo' => true ] ) ) === true
	&& is_array( $rootOf( [ 'block' => 'header', 'logo' => false ] ) ) === true );

// The component whose content is the markup of the page is the one whose schema says so, whatever it is called
$markup = rulesSite( false );
\Nino\Components::addComponent( $markup, 'rule-raw', [ 'label' => 'Raw', 'in' => [ 'page', 'flow' ], 'offers' => [], 'markup' => true, 'template' => '<div>[[content]]</div>' ] );
\Nino\Components::addComponent( $markup, 'html', [ 'label' => 'Not markup', 'in' => [ 'page', 'flow' ], 'offers' => [], 'template' => '<div>[[content]]</div>' ] );
$raw = \Nino\Components::offer( $markup, [], [] );
check( 'a component of another name that says markup is offered with the reason html, and the component called html that does not say it is offered as any other',
	$raw['rule-raw'] === \Nino\Components::CODE_HTML && $raw['html'] === true && $raw['band-wrap'] === true );
check( 'check() names the call of the component that says markup, keeps what it holds free of a finding, and finds html between the calls anywhere else - the name html alone is nothing',
	rulesCodes( $markup, '<p>[rule-raw]<b>x</b>[/rule-raw]' ) === [ '0' => [ 'free' ], '1' => [ 'html' ] ] && rulesCodes( $markup, '[html]<b>x</b>[/html]' ) === [ '0.0' => [ 'free' ] ]
	&& rulesCodes( $markup, '[rule-raw]<b>x</b>[/rule-raw]' ) === [ '0' => [ 'html' ] ] );

$notes = [];
$once = \Nino\Components::offer( $appData, [ 'crown-wrap' ], \Nino\Markup::parse( '[crown-logo][crown-nav /]', \Nino\Components::containers( $appData ), $notes ) );
check( 'a component that is once in a container is offered no more where one stands, and the others still are', $once['crown-logo'] === 'once' && $once['crown-nav'] === 'once' && $once['crown-actions'] === true );

$self = \Nino\Components::offer( $appData, [ 'band-wrap', 'band-row', 'band-col', 'rule-card' ], [] );
check( 'a container of the flow is no place for itself and the others are', $self['rule-card'] === 'self' && $self['rule-deep'] === true && $self['prose'] === true );

$deep = \Nino\Components::offer( $appData, [ 'band-wrap', 'band-row', 'band-col', 'rule-card', 'rule-deep' ], [] );
$deeper = \Nino\Components::offer( $appData, [ 'band-wrap', 'band-row', 'band-col', 'rule-card', 'rule-deep', 'rule-third' ], [] );
check( 'the sixth level is offered and the seventh is not', $deep['prose'] === true && $deeper['prose'] === 'depth' && $deeper['lever-group'] === 'depth' );

$through = \Nino\Components::offer( $appData, [ 'band-wrap', 'band-row', 'rule-filter', 'rule-loop' ], [] );
check( 'two containers that draw nothing, one in the other, offer what their row offers', array_keys( $through ) === array_keys( \Nino\Components::offer( $appData, [ 'band-wrap', 'band-row' ], [] ) ) && $through['band-col'] === true );

$bare = \Nino\Components::offer( $appData, [ 'band-wrap', 'band-row', 'rule-bare' ], [] );
check( 'a container that draws nothing and offers nothing holds only what names it', array_keys( $bare ) === [ 'rule-named' ] );

check( 'a container that draws nothing is not offered inside itself, however far up it stands', \Nino\Components::offer( $appData, [ 'band-wrap', 'band-row', 'rule-filter', 'rule-loop' ], [] )['rule-filter'] === 'self' );

$page = \Nino\Components::offer( $appData, [ 'rule-loop' ], [] );
check( 'a loop that stands directly in the page offers the places of the page', array_keys( $page ) === array_keys( \Nino\Components::offer( $appData, [], [] ) ) && isset( $page['band-col'] ) === false );

$flat = \Nino\Components::offer( $appData, [ 'band-wrap', 'band-row', 'band-col', 'rule-card', 'rule-deep', 'rule-loop' ], [] );
check( 'a container that draws nothing is no level', $flat['prose'] === true );

$column = \Nino\Components::offer( $appData, [ 'band-wrap', 'band-row', 'band-col' ], [] );
check( 'html is told by its name, the reason of a component that is otherwise offered', $column['raw'] === 'html' && $column['prose'] === true );

// --- B. check() ----------------------------------------------------------------------------------------

echo "\nB. check(): what is wrong with a tree\n";

check( 'a tree with nothing against it has no finding', rulesCodes( $appData, '[band-wrap id="a"][band-row][band-col size="100 m:50"][prose text="x" /][rule-card count="2" /][/band-col][/band-row][/band-wrap]' ) === [] );
check( 'F1: a name that nobody registered is unknown, at its position', rulesCodes( $appData, '[band-wrap][nobody][/band-wrap]' ) === [ '0.0' => [ 'unknown' ] ] );
check( 'a shortcode is a call that has no schema, and what it holds is not looked into', rulesCodes( $appData, '[rule-short]<b>[nobody][/rule-short]' ) === [ '0' => [ 'bare' ] ] );
check( 'F2: a variant the component does not know', rulesCodes( $appData, '[band-wrap variant="nope"]' ) === [ '0' => [ 'variant' ] ] );
check( 'F3: a value outside what the attribute takes, in a list and in a responsive one', rulesCodes( $appData, '[band-wrap ground="nope"][band-row][band-col size="100 q:50"][rule-card count="9" /][/band-col][band-col size="100 m:50 m:33" /][/band-row][/band-wrap]' ) === [ '0' => [ 'outside' ], '0.0.0' => [ 'outside' ], '0.0.0.0' => [ 'outside' ], '0.0.1' => [ 'outside' ] ] );
check( 'F3: an attribute that the schema does not name', rulesCodes( $appData, '[band-wrap class="x"]' ) === [ '0' => [ 'foreign' ] ] );
check( 'F7: a component at a place that does not offer one for it', rulesCodes( $appData, '[band-col /]' ) === [ '0' => [ 'place' ] ] );
check( 'K4: the second of a component that is once is found, not the first', rulesCodes( $appData, '[crown-wrap][crown-logo /][crown-logo /][/crown-wrap]' ) === [ '0.1' => [ 'once' ] ] );
check( 'self: a container of the flow in itself', rulesCodes( $appData, '[band-wrap][band-row][band-col][rule-card][rule-card][/rule-card][/rule-card][/band-col][/band-row][/band-wrap]' ) === [ '0.0.0.0.0' => [ 'self' ] ] );
check( 'K5: the seventh level is found once, and what stands below it is looked at all the same', rulesCodes( $appData, '[band-wrap][band-row][band-col][rule-card][rule-deep][rule-third][rule-fourth][prose /][nobody]<b>[/rule-fourth][/rule-third][/rule-deep][/rule-card][/band-col][/band-row][/band-wrap]' ) === [ '0.0.0.0.0.0.0' => [ 'depth' ], '0.0.0.0.0.0.0.1' => [ 'unknown' ], '0.0.0.0.0.0.0.2' => [ 'free' ] ] );
check( 'two containers that draw nothing in a row are checked against what the row offers', rulesCodes( $appData, '[band-wrap][band-row][rule-filter][rule-loop][band-col /][/rule-loop][/rule-filter][/band-row][/band-wrap]' ) === [] );
check( 'a column in a container that names no column is at the wrong place, what names it is not', rulesCodes( $appData, '[band-wrap][band-row][rule-bare][band-col /][rule-named /][/rule-bare][/band-row][/band-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ] );
check( 'a container that draws nothing in itself, through another, is found', rulesCodes( $appData, '[band-wrap][band-row][rule-filter][rule-loop][rule-filter][/rule-filter][/rule-loop][/rule-filter][/band-row][/band-wrap]' ) === [ '0.0.0.0.0' => [ 'self' ] ] );
check( 'a container that draws nothing in the page leaves the page its places', rulesCodes( $appData, '[rule-loop][band-col /][/rule-loop]' ) === [ '0.0' => [ 'place' ] ] );
check( 'a container that draws nothing is no level for K5', rulesCodes( $appData, '[band-wrap][band-row][band-col][rule-card][rule-deep][rule-loop][prose /][/rule-loop][/rule-deep][/rule-card][/band-col][/band-row][/band-wrap]' ) === [] );
check( 'an id that stands twice is found at the second', rulesCodes( $appData, '[band-wrap id="a"][/band-wrap][band-wrap id="a"][/band-wrap]' ) === [ '1' => [ 'duplicate' ] ] );
check( 'html between the calls is free, a call of html is told by its name, and what it holds is its content', rulesCodes( $appData, '<p>[raw]<b>x</b>[/raw]' ) === [ '0' => [ 'free' ], '1' => [ 'html' ] ] );

$source = '[band-wrap][band-row][nobody][/band-row][/band-wrap]';
$notes = [];
$found = \Nino\Components::check( $appData, \Nino\Markup::parse( $source, \Nino\Components::containers( $appData ), $notes ) );
check( 'a finding is a code and a detail, by the position of the trace', $found === [ '0.0.0' => [ [ 'code' => 'unknown', 'detail' => 'nobody' ] ] ] );

// What the test feature delivers: the demos of its groups and the templates of its blocks
$files = array_merge(
	glob( $root. '/tests/fixtures/kit/Kit/groups/*/demo.tpl' ) ?: [],
	glob( $root. '/tests/fixtures/kit/Kit/blocks/*/block.tpl' ) ?: []
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
check( 'the templates and the demos that come with the test feature have nothing against them: eleven files, no finding', count( $files ) === 11 && $codes === [] );

// --- C. layout() ---------------------------------------------------------------------------------------

echo "\nC. layout(): a new tree as by hand\n";

$containers = \Nino\Components::containers( $kit );
$by = "[band-wrap id=\"x\"]\n\t[band-row]\n\t\t[band-col]\n\t\t\t[band-title ~title]\n\t\t\t[raw]Some [[/a/b]] words[/raw]\n\t\t[/band-col]\n\t[/band-row]\n[/band-wrap]";
$notes = [];
$new = \Nino\Markup::parse( preg_replace( '/\s*\n\s*/', '', $by ) ?? '', $containers, $notes );

check( 'a tree that has no blank is written as the template that was written by hand', \Nino\Markup::write( \Nino\Markup::layout( $new, 0 ), $containers ) === $by );
check( 'the depth says how far the first level is in', \Nino\Markup::write( \Nino\Markup::layout( $new, 1 ), $containers ) === "[band-wrap id=\"x\"]\n\t\t[band-row]\n\t\t\t[band-col]\n\t\t\t\t[band-title ~title]\n\t\t\t\t[raw]Some [[/a/b]] words[/raw]\n\t\t\t[/band-col]\n\t\t[/band-row]\n\t[/band-wrap]" );

$inline = \Nino\Markup::parse( '[band-col][prose]a [b] c[/prose]<p>x[band-title ~t /]</p>[/band-col]', $containers, $notes );
check( 'a level with words or html in it is inline and stays as it is', \Nino\Markup::layout( $inline, 0 ) === $inline );

$raw = "[band-wrap]\n\t[band-row]\n\t[/band-row]\n[/band-wrap]\n[band-wrap][/band-wrap]";
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
