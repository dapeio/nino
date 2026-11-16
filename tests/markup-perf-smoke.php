<?php
declare(strict_types=1);

/**
 *	Nino										A compact filesystembased php framework
 *	markup-perf-smoke.php		Dependency-free smoke test for what a page of 600 calls costs as a template
 *													file: read and drawn from nothing, drawn from the tree that is kept of it, and
 *													what the cost does when the page grows from 150 calls to 600. The times are
 *													reported on every run; the pins are a budget that is far from what is measured
 *													(a slow machine is no reason for a failure) and ratios, which a machine does
 *													not move. The components are probes of the suite's own, the page is the test
 *													page of the measurement that sized the notation: 600 calls as the notation
 *													counted them before, 960 marks, ten to a unit.
 *													Runs against an isolated sandbox directory, never touches the real project
 *													data.
 *
 *	Usage: php tests/markup-perf-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'markup-perf' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

mkdir( $sandbox. '/private/templates', 0777, true );
mkdir( $sandbox. '/private/text', 0777, true );
mkdir( $sandbox. '/public/images', 0777, true );

\Nino\Html::init( $appData );
\Nino\Modules\Template::init( $appData );

/*	The components of the test page: a grid, a card, the titles, the text and the button	*/
$schemas = [
	'section-wrap' => [ 'label' => 'Wrap', 'in' => [ 'page' ], 'offers' => [ 'section-wrap' ], 'variants' => [ 'band' ],
		'attributes' => [ 'id' => [ 'type' => 'id', 'default' => '' ],
			'ground' => [ 'type' => 'choice', 'options' => [ 'base', 'alt', 'tint', 'dark', 'primary' ], 'default' => 'base' ] ],
		'template' => '<section[[id]] class="[[class]]">[[content]]</section>' ],
	'section-row' => [ 'label' => 'Row', 'in' => [ 'section-wrap' ], 'offers' => [ 'section-row' ],
		'attributes' => [ 'gap' => [ 'type' => 'choice', 'options' => [ '0', '1', '2', '3', '4' ], 'default' => '2' ] ],
		'template' => '<div class="[[class]]">[[content]]</div>' ],
	'section-col' => [ 'label' => 'Column', 'in' => [ 'section-row' ], 'offers' => [ 'flow', 'section-col' ],
		'attributes' => [ 'size' => [ 'type' => 'choice', 'options' => [ '25', '33', '50', '66', '75', '100' ], 'default' => '100', 'responsive' => true ],
			'align' => [ 'type' => 'choice', 'options' => [ 'start', 'center', 'end' ], 'default' => 'start', 'responsive' => true ] ],
		'template' => '<div class="[[class]]">[[content]]</div>' ],
	'section-title' => [ 'label' => 'Title', 'in' => [ 'section-col' ], 'source' => 'text',
		'attributes' => [ 'tag' => [ 'type' => 'choice', 'options' => [ 'h1', 'h2', 'h3', 'div' ], 'default' => 'h2', 'class' => false ] ],
		'template' => '<[[tag]] class="[[class]]"[[lang]]>[[value]]</[[tag]]>' ],
	'section-subtitle' => [ 'label' => 'Subtitle', 'in' => [ 'section-col' ], 'source' => 'text',
		'template' => '<p class="[[class]]">[[value]]</p>' ],
	'card' => [ 'label' => 'Card', 'in' => [ 'flow' ], 'offers' => [ 'card' ],
		'attributes' => [ 'fill' => [ 'type' => 'bool', 'default' => false ],
			'padding' => [ 'type' => 'choice', 'options' => [ '0', '1', '2', '3', '4' ], 'default' => '2' ],
			'ground' => [ 'type' => 'choice', 'options' => [ 'base', 'counter', 'alt', 'tint', 'dark', 'primary' ], 'default' => 'base' ] ],
		'template' => '<article class="[[class]]">[[content]]</article>' ],
	'card-title' => [ 'label' => 'Card title', 'in' => [ 'card' ], 'once' => true, 'source' => 'text',
		'attributes' => [ 'tag' => [ 'type' => 'choice', 'options' => [ 'h3', 'h4', 'div' ], 'default' => 'h3', 'class' => false ] ],
		'template' => '<[[tag]] class="[[class]]">[[value]]</[[tag]]>' ],
	'card-text' => [ 'label' => 'Card text', 'in' => [ 'card' ], 'source' => 'text',
		'template' => '<div class="[[class]]">[[value]]</div>' ],
	'text' => [ 'label' => 'Text', 'in' => [ 'flow', 'card' ], 'source' => 'text',
		'template' => '<p class="[[class]]">[[value]]</p>' ],
	'button' => [ 'label' => 'Button', 'in' => [ 'flow', 'card' ], 'source' => 'text', 'variants' => [ 'primary', 'outline' ],
		'attributes' => [ 'href' => [ 'type' => 'link', 'default' => '', 'class' => false ] ],
		'template' => '<a class="[[class]]" href="[[href]]">[[value]]</a>' ],
];

foreach( $schemas as $name => $schema )
	\Nino\Components::addComponent( $appData, $name, $schema );

/**
 *	The test page of $units units, ten calls of the notation before it to a unit, sixteen marks of this one, and the
 *	words it asks for
 *
 *	@param		int				$units
 *	@param		string		$name					The template, which is the category of its words
 *
 *	@return 	array										[ text of the template, [ key => text ] ]
 */
function perfPage( int $units, string $name ): array {

	$page = '';
	$texts = [];

	for( $index = 1; $index <= $units; $index++ ) {

		$key = '/template/'. $name. '/s'. $index. '/';
		$texts[$key. 'title']				= 'Abschnitt '. $index. ': Was wir für Sie tun';
		$texts[$key. 'subtitle']		= 'Ein Satz, der den Abschnitt in Ruhe erklärt.';
		$texts[$key. 'first-title']	= 'Beratung';
		$texts[$key. 'first-text']	= 'Wir hören zu, bevor wir etwas vorschlagen. Das spart am Ende Zeit und Geld.';
		$texts[$key. 'second-title']	= 'Umsetzung';
		$texts[$key. 'second-text']	= 'Wir bauen, was besprochen wurde, und zeigen jeden Zwischenstand.';
		$texts[$key. 'text']				= 'Ein Absatz Fließtext mit einigen Wörtern, wie er auf einer gewöhnlichen Seite steht.';
		$texts[$key. 'button']			= 'Mehr erfahren';

		$page .= '[section-wrap id="s'. $index. '"'. ( $index % 2 === 0 ? ' ground="alt"' : '' ). ']'. "\n"
			. "\t". '[section-row gap="2"]'. "\n"
			. "\t\t". '[section-col size="100" align="center"]'. "\n"
			. "\t\t\t". '[section-title '. $key. 'title tag="h2"]'. "\n"
			. "\t\t\t". '[section-subtitle '. $key. 'subtitle]'. "\n"
			. "\t\t". '[/section-col]'. "\n"
			. "\t\t". '[section-col size="100 m:50"]'. "\n"
			. "\t\t\t". '[card fill padding="3" ground="counter"]'. "\n"
			. "\t\t\t\t". '[card-title '. $key. 'first-title tag="h3"]'. "\n"
			. "\t\t\t\t". '[card-text '. $key. 'first-text]'. "\n"
			. "\t\t\t". '[/card]'. "\n"
			. "\t\t". '[/section-col]'. "\n"
			. "\t\t". '[section-col size="100 m:50"]'. "\n"
			. "\t\t\t". '[card fill padding="3" ground="counter"]'. "\n"
			. "\t\t\t\t". '[card-title '. $key. 'second-title tag="h3"]'. "\n"
			. "\t\t\t\t". '[card-text '. $key. 'second-text]'. "\n"
			. "\t\t\t". '[/card]'. "\n"
			. "\t\t". '[/section-col]'. "\n"
			. "\t\t". '[section-col size="100"]'. "\n"
			. "\t\t\t". '[text '. $key. 'text]'. "\n"
			. "\t\t\t". '[button '. $key. 'button href="/" variant="primary"]'. "\n"
			. "\t\t". '[/section-col]'. "\n"
			. "\t". '[/section-row]'. "\n"
			. '[/section-wrap]'. "\n";
	}

	return [ $page, $texts ];
}

$cache = ( new ReflectionClassConstant( \Nino\Html::class, 'CACHE' ) )->getValue();
$trees = $sandbox. '/private/data/cache/trees';

/**
 *	The page of a template file as a request draws it: nothing of the one before in memory
 *
 *	@return 	string
 */
$draw = static function( string $name ) use ( &$appData, $cache ): string {
	unset( $appData[$cache] );
	return \Nino\Html::renderFile( $appData, '/templates/'. $name. '.tpl', [ 'template' => $name ] );
};

/**
 *	The median of $runs runs, in milliseconds, each run after $before() - which is not timed
 */
$median = static function( callable $run, ?callable $before = null, int $runs = 30 ): float {

	$times = [];

	for( $index = 0; $index < $runs; $index++ ) {

		if( $before !== null )
			$before();

		clearstatcache();
		$started = hrtime( true );
		$run();
		$times[] = ( hrtime( true ) - $started ) / 1e6;
	}

	sort( $times );

	return $times[ intdiv( $runs, 2 ) ];
};

$numbers = [];

/*	The two sizes: 600 calls of the notation before (60 units) and a fourth of it	*/
$pages = [];

foreach( [ 'page-small' => 15, 'page-bench' => 60 ] as $name => $units ) {

	[ $pages[$name], $texts ] = perfPage( $units, $name );
	\Nino\Html::addFills( $appData, $texts, '*' );
	$path = \Nino\Filesystem::path( $appData, '/templates/'. $name. '.tpl' );
	file_put_contents( $path, $pages[$name] );
	touch( $path, 1767225600 );
}

$big = $pages['page-bench'];

// The same page whatever the way it is drawn: the first request after the folder is emptied writes the trees
\Nino\Filesystem::removeDir( $trees );
$cold = $draw( 'page-bench' );
check( 'the page is drawn whole: 120 cards, 60 titles and no call or fill left', substr_count( $cold, '<article' ) === 120 && substr_count( $cold, '<h2 class="section-title">' ) === 60 && strpos( $cold, '[' ) === false );
check( 'the first request wrote the tree and the second is the same page, drawn from it', is_file( $trees. '/'. sha1( '/templates/page-bench.tpl' ). '.php' ) === true && $draw( 'page-bench' ) === $cold );
check( 'the page is 960 marks of the notation and 600 calls as it counted them before, ten to a unit', preg_match_all( '/^\t*\[[a-z]/m', $big ) === 960 && 60 * 10 === 600 );

echo "Times of a page of 600 calls (median of 30)\n";

$appData['/nino/markup/cache'] = false;
$read = $median( static fn() => $draw( 'page-bench' ) );
$appData['/nino/markup/cache'] = true;
$numbers['600 calls, read and drawn, nothing kept (switch off)'] = $read;

$kept = $median( static fn() => $draw( 'page-bench' ), static fn() => \Nino\Filesystem::removeDir( $trees ) );
$numbers['600 calls, read, drawn and the tree written (first request)'] = $kept;

$draw( 'page-bench' );
$warm = $median( static fn() => $draw( 'page-bench' ) );
$numbers['600 calls, drawn from the tree that is kept (second request)'] = $warm;

$treeOnly = $median( static function() use ( &$appData, $cache ): void {
	unset( $appData[$cache] );
	\Nino\Html::fileTree( $appData, '/templates/page-bench.tpl' );
} );
$numbers['600 calls, the tree alone from the file kept'] = $treeOnly;

$parseOnly = $median( static function() use ( &$appData, $big ): void {
	\Nino\Markup::parse( $big, \Nino\Components::containers( $appData ) );
} );
$numbers['600 calls, the text read into a tree alone'] = $parseOnly;

$tree = \Nino\Markup::parse( $big, \Nino\Components::containers( $appData ) );
$paint = $median( static function() use ( &$appData, $tree ): void {
	\Nino\Components::render( $appData, $tree, [ 'template' => 'page-bench' ] );
} );
$numbers['600 calls, a tree drawn alone'] = $paint;

foreach( $numbers as $what => $ms )
	printf( "  %-62s %7.2f ms\n", $what, $ms );

$opcache = function_exists( 'opcache_get_status' ) === true && ( @opcache_get_status( false )['opcache_enabled'] ?? false ) === true;
printf( "  opcache for this run: %s\n", $opcache === true ? 'on' : 'off' );

echo "\n";

// The budget of the notation is 0.015 ms a call, twice for the reserve: 18 ms for this page, and a quiet machine takes 6 to 8 ms, told above on every
// run. The pin is ten times the measure, so that the runner of a shared service, several times slower from one run to the next, is no failure
check( 'a page of 600 calls is read and drawn in at most 70 ms', $read <= 70.0 );
check( 'and read, drawn and kept in at most 70 ms as well', $kept <= 70.0 );

check( 'a page drawn from the tree that is kept is drawn in at most 70 ms as well', $warm <= 70.0 );

/*	What the tree that is kept saves is the read of the text into a tree (about two fifths of the time of the page, measured
	here: the number above); what it cannot save is the drawing, which is the rest. Half of the time of the page is not
	reached - the drawing is more than half of it - and what is is told in the numbers. The load of the file is where
	opcache holds it, as every request of a site does: without it php reads the file of the tree as source, which takes more
	than the text takes to be read, and the line says so instead of pinning it	*/
if( $opcache === true )
	check( 'with opcache the tree from its file takes at most a tenth of what the text takes to be read into a tree', $treeOnly <= 0.1 * $parseOnly );
else
	echo "  (not pinned: opcache is off in this run, and the file of a tree is read as source then)\n";

echo "\nThe page grows with its calls\n";

/*	Four times the calls, four times the time: the ratio of the best of 30 runs of each size, which the load of the machine does
	not move, as the two sizes are run one after the other in the same loop	*/
$grow = static function( bool $cache ) use ( &$appData, $draw ): array {

	$appData['/nino/markup/cache'] = $cache;
	$draw( 'page-small' );
	$draw( 'page-bench' );
	$small = $big = [];

	for( $run = 0; $run < 30; $run++ ) {

		clearstatcache();
		$started = hrtime( true );
		$draw( 'page-small' );
		$middle = hrtime( true );
		$draw( 'page-bench' );
		$small[] = ( $middle - $started ) / 1e6;
		$big[] = ( hrtime( true ) - $middle ) / 1e6;
	}

	sort( $small );
	sort( $big );
	$appData['/nino/markup/cache'] = true;

	return [ $small[0], $big[0], $small[15], $big[15] ];
};

$growRead = $grow( false );
$growWarm = $grow( true );

printf( "  150 calls, read and drawn: %.2f ms; 600 calls: %.2f ms; times %.2f (median of 30: %.2f ms, %.2f ms, times %.2f)\n", $growRead[0], $growRead[1], $growRead[1] / $growRead[0], $growRead[2], $growRead[3], $growRead[3] / $growRead[2] );
printf( "  150 calls, from the tree: %.2f ms; 600 calls: %.2f ms; times %.2f (median of 30: %.2f ms, %.2f ms, times %.2f)\n", $growWarm[0], $growWarm[1], $growWarm[1] / $growWarm[0], $growWarm[2], $growWarm[3], $growWarm[3] / $growWarm[2] );

check( 'a page of four times the calls takes at most 4.5 times as long, read and drawn', $growRead[1] <= 4.5 * $growRead[0] );
check( '...and drawn from its tree', $growWarm[1] <= 4.5 * $growWarm[0] );

echo "\n";

\Nino\Filesystem::removeDir( $sandbox );

$empty = [];
ninoDone( $empty );
