<?php
declare(strict_types=1);

/**
 *	Nino
 *	basis-rules-smoke.php	Contract test for the rules of a place as the components of Basis keep them - what a tool may offer and what the server finds wrong
 *												in a tree of them:
 *
 *													A. offer() for each container of Basis, the logo and the menu of the header and the footer that a container offers in the columns
 *														 below it, and the ones that are once in it
 *													B. check() of the logo of the header at the wrong place, and the templates and the demos that come with the kernel, which
 *														 are written in the components of Basis
 *
 *												The rules themselves, on the components of a feature of the tests, are tests/components-rules-smoke.php.
 *
 *	Usage: php tests/basis-rules-smoke.php
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
	foreach( [ 'template', 'frame', 'assets', 'jstext', 'legal', 'privacy', 'json', 'imagevalues', 'link', 'csrf' ] as $shortcode )
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

// The way from the page to each container of basis: the first chain that allowed() gives - through a section: what a wrap with a 'deep' place adds to the rows below it is for offer() alone
$chains = [ '' => [] ];
$containers = \Nino\Components::containers( $basis );
$queue = [ [] ];

while( $queue !== [] ) {

	$path = array_shift( $queue );

	foreach( \Nino\Components::allowed( $basis, $path === [] ? '' : $path[count( $path ) - 1] ) as $name )
		if( isset( $chains[$name] ) === false && isset( $containers[$name] ) === true && $containers[$name] === true && ( $name !== 'section-row' || in_array( $path[count( $path ) - 1] ?? '', [ 'header-wrap', 'footer-wrap' ], true ) === false ) ) {
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

// A place a container offers to every container of the flow below it, however deep: the logo and the menu of a header stand in its columns, and in no other
$notes = [];
$ofHere = static fn( array $path ): array => array_keys( array_filter( \Nino\Components::offer( $basis, $path, [] ), static fn( mixed $answer ): bool => $answer === true ) );
$parts = static fn( array $path ): array => array_values( array_intersect( $ofHere( $path ), [ 'header-logo', 'header-nav', 'footer-logo', 'footer-nav' ] ) );
check( 'deep: the logo and the menu of the header are offered in a column below the header - and the logo and the menu of the footer in one below the footer - and in no column of a section',
	$parts( [ 'header-wrap', 'section-row', 'section-col' ] ) === [ 'header-logo', 'header-nav' ] && $parts( [ 'footer-wrap', 'section-row', 'section-col' ] ) === [ 'footer-logo', 'footer-nav' ]
	&& $parts( [ 'section-wrap', 'section-row', 'section-col' ] ) === [] );
check( 'deep: the places go to a container of the flow and to nothing else: not to a row, not to the parts that name the header in their own in (they keep what they offer), and the own column of the footer keeps its logo',
	$parts( [ 'header-wrap', 'section-row' ] ) === [] && $parts( [ 'header-wrap', 'header-actions' ] ) === [] && $parts( [ 'footer-wrap', 'footer-col' ] ) === [ 'footer-logo', 'footer-nav' ] && $parts( [ 'header-wrap' ] ) === [ 'header-logo', 'header-nav' ] );
// ...but not below a part of the container itself: a container of the flow in header-actions or in footer-col is no column of a row, and keeps what it had
$ofOwn = static fn( array $path ): array => array_values( array_intersect( array_keys( array_filter( \Nino\Components::offer( $appData, $path, [] ), static fn( mixed $answer ): bool => $answer === true ) ), [ 'header-logo', 'header-nav', 'footer-logo', 'footer-nav' ] ) );
check( 'deep: a container of the flow below header-actions or below footer-col is offered no part of the header or the footer, and one below a column of a row is - and check() finds the part there at the wrong place, or not',
	$ofOwn( [ 'header-wrap', 'header-actions', 'rule-card' ] ) === [] && $ofOwn( [ 'footer-wrap', 'footer-col', 'rule-card' ] ) === [] && $ofOwn( [ 'footer-wrap', 'footer-col', 'rule-card', 'rule-deep' ] ) === []
	&& $ofOwn( [ 'header-wrap', 'section-row', 'section-col', 'rule-card' ] ) === [ 'header-logo', 'header-nav' ] && $ofOwn( [ 'footer-wrap', 'section-row', 'section-col', 'rule-card', 'rule-deep' ] ) === [ 'footer-logo', 'footer-nav' ]
	&& rulesCodes( $appData, '[header-wrap][header-actions][rule-card][header-logo /][/rule-card][/header-actions][/header-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ]
	&& rulesCodes( $appData, '[footer-wrap][footer-col][rule-card][footer-nav /][/rule-card][/footer-col][/footer-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ]
	&& rulesCodes( $appData, '[header-wrap][section-row][section-col][rule-card][header-logo /][/rule-card][/section-col][/section-row][/header-wrap]' ) === [] );

$deepOnce = \Nino\Components::offer( $basis, [ 'header-wrap', 'section-row', 'section-col' ], \Nino\Markup::parse( '[header-logo /]', \Nino\Components::containers( $basis ), $notes ) );
check( 'deep: once is a rule of the container, so that two columns have a menu each', $deepOnce['header-logo'] === 'once' && $deepOnce['header-nav'] === true
	&& \Nino\Components::offer( $basis, [ 'header-wrap', 'section-row', 'section-col' ], [] )['header-logo'] === true );
check( 'deep: check() finds a logo in a column of a section at the wrong place, and none in a column of the header or of the footer for the footer logo',
	rulesCodes( $basis, '[section-wrap][section-row][section-col][header-logo /][/section-col][/section-row][/section-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ]
	&& rulesCodes( $basis, '[header-wrap][section-row][section-col][header-logo /][header-nav /][/section-col][section-col][header-nav /][/section-col][/section-row][/header-wrap]' ) === []
	&& rulesCodes( $basis, '[footer-wrap][section-row][section-col][footer-logo /][footer-nav /][/section-col][/section-row][/footer-wrap]' ) === []
	&& rulesCodes( $basis, '[footer-wrap][section-row][section-col][header-logo /][/section-col][/section-row][/footer-wrap]' ) === [ '0.0.0.0' => [ 'place' ] ] );

$notes = [];
$once = \Nino\Components::offer( $appData, [ 'header-wrap' ], \Nino\Markup::parse( '[header-logo][header-nav /]', \Nino\Components::containers( $appData ), $notes ) );
check( 'a component that is once in a container is offered no more where one stands, and the others still are', $once['header-logo'] === 'once' && $once['header-nav'] === 'once' && $once['header-actions'] === true );

// What the kernel delivers: the templates of the starter site, the demos of basis, the fixture page of the notation
$files = array_merge(
	glob( $root. '/_admin/install/library/*/templates/*.tpl' ) ?: [],
	glob( $root. '/_admin/install/library/pages/*/templates/*.tpl' ) ?: [],
	glob( $root. '/tests/fixtures/basis/starter/*/templates/*.tpl' ) ?: [],
	glob( $root. '/tests/fixtures/basis/starter/pages/*/templates/*.tpl' ) ?: [],
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

// The starter site in the components of Basis, kept as fixtures (tests/fixtures/basis/starter): the scans of the workbench read the words and the pictures of its home page through the register
$homeSource = (string) file_get_contents( $root. '/tests/fixtures/basis/starter/pages/home/templates/page-home.tpl' );
$basisSite = rulesSite( false );
$reads = \Nino\Components::sources( $basisSite, $homeSource, 'page-home' );
$readsOf = static fn( string $kind ): array => array_column( array_filter( $reads, static fn( array $read ): bool => $read['kind'] === $kind ), 'source' );
check( 'the scans read the home page of the starter fixtures through the register: its title and its text as the keys they are under the id of their section, and the picture behind the hero as a place of a picture',
	array_diff( [ '/template/page-home/hero/title', '/template/page-home/hero/subtitle', '/template/page-home/start-1/text', '/template/page-home/start-3/title' ], $readsOf( 'text' ) ) === []
	&& in_array( '/template/page-home/hero/background', $readsOf( 'image' ), true ) === true );

// ...and its pages are written in the components of Basis: a name line, the head, sections with an id that is a slug no section before has, the foot, and no section, header or footer drawn by hand
$shapeFailures = [];
$shapeCount = 0;

foreach( glob( $root. '/tests/fixtures/basis/starter/pages/*/templates/*.tpl' ) ?: [] as $shapeFile ) {

	$shapeSource = (string) file_get_contents( $shapeFile );

	if( str_contains( $shapeSource, '[section-wrap' ) === false )
		continue;

	$shapeCount++;
	$shapeIds = [];

	if( preg_match( '~\A<!-- nino:template-name [^\r\n<>]+ -->\n\[template /templates/html-header(?:-[a-z0-9]+)*\]\n\[section-wrap\b.*\n\[template /templates/html-footer(?:-[a-z0-9]+)*\]\n\z~s', $shapeSource ) !== 1 )
		$shapeFailures[] = basename( $shapeFile ). ': the name line, the head, the sections and the foot do not stand where they are read';

	if( preg_match( '~<(?:section|header|footer)\b|class="[^"]*\bnino-(?!form)~', $shapeSource ) === 1 )
		$shapeFailures[] = basename( $shapeFile ). ': a section, a header or a footer drawn by hand, or a class of the old stylesheet';

	preg_match_all( '~\[section-wrap\b([^\]]*)\]~', $shapeSource, $shapeTags );

	foreach( $shapeTags[1] as $shapeTag )
		if( preg_match( '~ id="([a-z0-9]+(?:-[a-z0-9]+)*)"~', $shapeTag, $shapeId ) !== 1 || isset( $shapeIds[$shapeId[1]] ) === true )
			$shapeFailures[] = basename( $shapeFile ). ': a section without an id that is a slug, or with one a section before it has';
		else
			$shapeIds[$shapeId[1]] = true;
}

check( 'the pages of the starter fixtures are written in the components of Basis ('. $shapeCount. ' templates)'. ( $shapeFailures === [] ? '' : ' - '. implode( ' | ', $shapeFailures ) ), $shapeFailures === [] && $shapeCount >= 3 );

ninoDone( $appData );
