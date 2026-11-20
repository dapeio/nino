<?php
declare(strict_types=1);

/**
 *	Nino										A compact filesystembased php framework
 *	markup-perf-smoke.php		Dependency-free smoke test for what a page of 600 calls costs as a template
 *													file: read and drawn from nothing, drawn from the tree that is kept of it, and
 *													what the work does when the page grows from 150 calls to 600. The pins hold no time
 *													- a clock is no measure on a machine that is shared - but what the cost is made of:
 *													how often the text is read into a tree (a tree file that is written is a read), whether
 *													a page is drawn from the tree that is kept (the file's text is changed behind the
 *													tree's back, to the same size and the same time, and the page does not show it) or from
 *													the text (it does), once for the request however often the page is drawn in it, and
 *													that the calls read and drawn grow with the calls of the page, no faster. The times
 *													are told on every run and pinned nowhere. The components are probes of the suite's own,
 *													the page is the test page of the measurement that sized the notation: 600 calls as the
 *													notation counted them before, 960 marks, ten to a unit.
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

// The files of the kept trees, and a way to tell how often a text was read into a tree: a read writes the tree file, so a file that is written is a read and a file that is
// left as it was is none. The file is given a time long ago before the draw, and a write is the time it has afterwards
$treeFile = $trees. '/'. sha1( '/templates/page-bench.tpl' ). '.php';
$ago = 1000000000;

/**
 *	How many times the text of the page was read into a tree by a draw: 1 where the kept file was written, 0 where it was left as it was
 */
$reads = static function() use ( $treeFile, $ago ): int {
	clearstatcache();
	return is_file( $treeFile ) === true && filemtime( $treeFile ) !== $ago ? 1 : 0;
};

/**
 *	The page of a template file with another text of the very same size and the very same time as the one the tree was made of: what is drawn from the text shows it, what is
 *	drawn from the tree does not. The words are changed in place (a padding of 3 is the default one of 2, which has no class: one character)
 */
$swapped = static function( string $name ) use ( &$appData ): string {
	$path = \Nino\Filesystem::path( $appData, '/templates/'. $name. '.tpl' );
	$text = (string) file_get_contents( $path );
	$other = str_replace( 'padding="3"', 'padding="2"', $text );
	file_put_contents( $path, $other );
	touch( $path, 1767225600 );
	clearstatcache();
	return $other;
};

echo "What a draw of the page of 600 calls is made of\n";

$text = (string) file_get_contents( \Nino\Filesystem::path( $appData, '/templates/page-bench.tpl' ) );
$swappedText = str_replace( 'padding="3"', 'padding="2"', $text );
check( 'the swapped text of the page is the same size as the one the tree was made of, and has what the page shows swapped', strlen( $swappedText ) === strlen( $text ) && $swappedText !== $text && substr_count( $cold, 'padding-3' ) === 120  );

// (1) The switch is off: nothing is kept, the text is read at every draw
$appData['/nino/markup/cache'] = false;
\Nino\Filesystem::removeDir( $trees );
$first = $draw( 'page-bench' );
$swapped( 'page-bench' );
$second = $draw( 'page-bench' );
$appData['/nino/markup/cache'] = true;
check( 'with the switch off no tree is kept, and every draw reads the text: the page that is drawn after the text changed shows the change', is_file( $treeFile ) === false && $first === $cold && substr_count( $second, 'padding-3' ) === 0 );

// (2) The first request after the folder is emptied reads the text once and keeps the tree
\Nino\Filesystem::putFileContent( $appData, '/templates/page-bench.tpl', $text );
touch( \Nino\Filesystem::path( $appData, '/templates/page-bench.tpl' ), 1767225600 );
\Nino\Filesystem::removeDir( $trees );
$written = $draw( 'page-bench' );
check( 'the first request after the folder is emptied reads the text into a tree once and keeps it: one file, the same page', $written === $cold && count( glob( $trees. '/*.php' ) ?: [] ) === 1 && is_file( $treeFile ) === true );

// (3) The second request is drawn from the tree: the text behind it is changed, same size and time, and the page does not show it; the file of the tree is not written
touch( $treeFile, $ago );
$swapped( 'page-bench' );
$warm = $draw( 'page-bench' );
check( 'the second request draws the page from the tree that is kept: the text was changed behind it to the same size and time, and the page is the old one, the tree file is not written again', $warm === $cold && $reads() === 0 );

// (4) The same request draws the page a second time from what it holds in memory, whatever the file says now
$appData['/nino/markup/cache'] = true;
unset( $appData[$cache] );
\Nino\Html::renderFile( $appData, '/templates/page-bench.tpl', [ 'template' => 'page-bench' ] );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-bench.tpl', $text. ' ' );
$again = \Nino\Html::renderFile( $appData, '/templates/page-bench.tpl', [ 'template' => 'page-bench' ] );
check( 'a file is read into a tree once for a request: a page drawn twice in it is drawn twice from the one tree, though the file changed between the two', $again === $cold && count( $appData[$cache]['files'] ) === 1 );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-bench.tpl', $text );
touch( \Nino\Filesystem::path( $appData, '/templates/page-bench.tpl' ), 1767225600 );

// (5) The tree of the file is the tree of the text, and the one that is read: the tree from the file saves the read and nothing is lost
unset( $appData[$cache] );
$fromFile = \Nino\Html::fileTree( $appData, '/templates/page-bench.tpl' );
$tree = \Nino\Markup::parse( $big, \Nino\Components::containers( $appData ) );
check( 'the tree that is kept is the very tree the text is read into: a draw from the file and a draw of the text are one page', $fromFile === $tree && ( static function() use ( &$appData, $tree, $cold ): bool {
	return \Nino\Components::render( $appData, $tree, [ 'template' => 'page-bench' ] ) === $cold;
} )() );

echo "\nThe page grows with its calls\n";

/*	Four times the calls, four times the work: counted, not timed. The calls the text is read into, the elements the page is drawn into, and the reads of the file - one for each size, however
	many calls it has - all follow the calls of the page, with no more than their number; a clock would tell the same with the noise of the machine in it	*/
$count = static function( string $name ) use ( &$appData, $draw, $pages ): array {

	$tree = \Nino\Markup::parse( $pages[$name], \Nino\Components::containers( $appData ) );
	$nodes = 0;
	$walk = static function( array $list ) use ( &$walk, &$nodes ): void {
		foreach( $list as $node )
			if( isset( $node['n'] ) === true ) {
				$nodes++;
				$walk( (array) ( $node['c'] ?? [] ) );
			}
	};
	$walk( $tree );
	$html = $draw( $name );

	return [ 'calls' => $nodes, 'cards' => substr_count( $html, '<article' ), 'titles' => substr_count( $html, '<h2 class="section-title">' ), 'bytes' => strlen( $html ), 'marks' => preg_match_all( '/^\t*\[[a-z]/m', $pages[$name] ) ];
};

$small = $count( 'page-small' );
$bench = $count( 'page-bench' );

echo '  150 calls: '. json_encode( $small ). "\n  600 calls: ". json_encode( $bench ). "\n";

check( 'a page of four times the units has four times the calls, four times the cards and titles drawn, and the marks of its text in the same ratio: what is read and what is drawn is counted by the page, no faster',
	$bench['calls'] === 4 * $small['calls'] && $bench['cards'] === 4 * $small['cards'] && $bench['titles'] === 4 * $small['titles'] && $bench['marks'] === 4 * $small['marks'] && $small['calls'] === 240 );
check( '...and the bytes it is drawn into follow the calls, within the width of the numbers of the units (s1 .. s60)', $bench['bytes'] > 3.9 * $small['bytes'] && $bench['bytes'] < 4.2 * $small['bytes'] );

// A draw of each size reads the text into a tree once and the second draw not at all: the work of a page of any size is one read and a draw
$sizes = [];

foreach( [ 'page-small', 'page-bench' ] as $name ) {

	$file = $trees. '/'. sha1( '/templates/'. $name. '.tpl' ). '.php';
	\Nino\Filesystem::removeDir( $trees );
	$draw( $name );
	$sizes[$name] = [ is_file( $file ) === true, ( static function() use ( $file, $ago, $draw, $name ): bool { touch( $file, $ago ); $draw( $name ); clearstatcache(); return filemtime( $file ) === $ago; } )() ];
}

check( 'a page of either size is read into a tree once and drawn from it after: the tree is written by the first draw and left alone by the second', $sizes === [ 'page-small' => [ true, true ], 'page-bench' => [ true, true ] ] );

// What the clock says is told, not pinned
$appData['/nino/markup/cache'] = false;
$told = [ '600 calls, read and drawn, nothing kept (switch off)' => $median( static fn() => $draw( 'page-bench' ) ) ];
$appData['/nino/markup/cache'] = true;
$draw( 'page-bench' );
$told['600 calls, drawn from the tree that is kept (second request)'] = $median( static fn() => $draw( 'page-bench' ) );
$told['600 calls, the text read into a tree alone'] = $median( static function() use ( &$appData, $big ): void {
	\Nino\Markup::parse( $big, \Nino\Components::containers( $appData ) );
} );

echo "\nTold on every run and pinned nowhere (median of 30)\n";

foreach( $told as $what => $ms )
	printf( "  %-62s %7.2f ms\n", $what, $ms );

echo "\n";

\Nino\Filesystem::removeDir( $sandbox );

$empty = [];
ninoDone( $empty );
