<?php
declare(strict_types=1);

/**
 *	Nino
 *	basis-blocks-smoke.php	Contract test for the blocks of the feature Basis, as \Nino\Blocks reads them:
 *
 *												A. the eight blocks of basis: listed, within their sizes, written back byte for byte, and drawn with their texts
 *												B. what is told of them where a block is listed: their origin and the label of it, the order of the roots - Basis first, then the
 *													 project, then the other features by key - and the variants two of them name
 *												C. the roots of a block that Basis brings: the three components, each with the side of its page
 *
 *												What a block is, what makes one fail, what is kept, what a frame reads and how a root is told is tests/blocks-smoke.php, on the blocks
 *												of the test feature.
 *
 *	Usage: php tests/basis-blocks-smoke.php
 */

// A project with no features of its own: the one feature of the run is the one the kernel delivers
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-basis-blocks-features-'. getmypid() );
mkdir( NINO_FEATURES_DIR, 0755, true );
register_shutdown_function( static function(): void {
	@rmdir( NINO_FEATURES_DIR );
} );

// Two features of the project's own that bring blocks: Aaa and Zzz, on where the test says so
foreach( [ 'Aaa' => 'aaa', 'Zzz' => 'zzz' ] as $directory => $key ) {

	mkdir( NINO_FEATURES_DIR. '/'. $directory, 0755, true );
	file_put_contents( NINO_FEATURES_DIR. '/'. $directory. '/'. $directory. ".php", "<?php\ndeclare(strict_types=1);\nnamespace Nino\\Modules {\n\tclass ". $directory. " {\n\t}\n}\n" );
	file_put_contents( NINO_FEATURES_DIR. '/'. $directory. '/feature.php', "<?php\nreturn [ 'key' => '". $key. "', 'name' => '". $directory. "', 'version' => '1.0.0' ];\n" );
}

register_shutdown_function( static function(): void {
	\Nino\Filesystem::removeDir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';

$ignored = ninoWarnings();
$root = dirname( __DIR__ );

/**
 *	A project with basis on, as \Nino\init() registers it, and the calls the templates of a page make that are no component
 *
 *	@return 	array										$appData
 */
function blocksSite(): array {

	$appData = ninoSandbox( 'blocks' );
	$appData['./nino/filesystem/path'] = dirname( __DIR__ );
	$appData['/nino/modules'] = [ '\\Nino\\Modules\\Basis' ];
	$appData['/nino/html/navs'] = [ 'main', 'footer' ];

	\Nino\Features::registerComponents( $appData );

	foreach( [ 'template', 'assets', 'jstext', 'legal', 'privacy', 'json', 'imagevalues', 'csrf' ] as $shortcode )
		\Nino\Html::addShortcode( $appData, $shortcode, static fn(): string => '', null );

	return $appData;
}

/**
 *	The parts of a block that has nothing against it: a section with a title, a picture behind it and two languages
 *
 *	@return 	array										What \Nino\Blocks::validate() takes
 */
function blocksParts(): array {

	$words = static fn( string $de, string $en ): array => [ 'de_DE' => $de, 'en_US' => $en ];

	return [
		'json'	=> json_encode( [
			'name'				=> $words( 'Probe', 'Probe' ),
			'description'	=> $words( 'Eine Probe', 'A probe' ),
			'category'		=> 'content',
			'rank'				=> 5,
			'images'			=> [ 'background' => [ 'placeholder' => '/nino/placeholder/landscape', 'width' => 800, 'height' => 600, 'label' => $words( 'Bild', 'Picture' ) ] ],
		] ),
		'tpl'		=> "[section-wrap id=\"probe\" image=\"~background\"]\n\t[section-row]\n\t\t[section-col id=\"probe-1\"]\n\t\t\t[section-title ~title]\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]",
		'texts'	=> [ 'de_DE' => json_encode( [ '1-title' => 'Titel' ] ), 'en_US' => json_encode( [ '1-title' => 'Title' ] ) ],
	];
}

/**
 *	A block as files in a folder of blocks
 *
 *	@param		string		$dir					The folder of blocks
 *	@param		string		$key
 *	@param		array			$parts				See blocksParts()
 *
 *	@return 	string										The folder of the block
 */
function blocksWrite( string $dir, string $key, array $parts ): string {

	mkdir( $dir. '/'. $key. '/text', 0755, true );
	file_put_contents( $dir. '/'. $key. '/block.json', $parts['json'] );
	file_put_contents( $dir. '/'. $key. '/block.tpl', $parts['tpl'] );

	foreach( $parts['texts'] as $locale => $text )
		file_put_contents( $dir. '/'. $key. '/text/'. $locale. '.json', $text );

	return $dir. '/'. $key;
}

/**
 *	What the next request would do: nothing of the last one is kept in the app data
 *
 *	@param		array 		&$appData			(reference)
 *
 *	@return 	void
 */
function blocksRequest( array &$appData ): void {

	unset( $appData['./nino/blocks/state'], $appData['./nino/blocks/said'], $appData['./nino/blocks/absent'], $appData['./nino/features/all'], $appData['./nino/features/listed'] );
}

/**
 *	A source drawn in a page of the test
 *
 *	@param		array 		&$appData			(reference)
 *	@param		string		$source
 *
 *	@return 	string
 */
function basisLessDraw( array &$appData, string $source ): string {

	return \Nino\Components::render( $appData, \Nino\Markup::parse( $source, \Nino\Components::containers( $appData ) ), [ 'template' => 'page-x' ] );
}

$appData = blocksSite();
$own = ninoSandboxDir( $appData ). '/private/blocks';

// --- A. the blocks of basis ----------------------------------------------------------------------------

echo "\nA. The blocks that basis brings\n";

$all = \Nino\Blocks::all( $appData );
$wanted = [ 'hero-full', 'hero-centered', 'page-header', 'split-image-text', 'text-narrow', 'three-columns', 'header-line', 'footer-columns' ];

check( 'the eight blocks of basis are listed, all of basis and made of its components, and nothing is refused or said', array_keys( $all ) === array_values( array_filter( $wanted, static fn( string $key ): bool => isset( $all[$key] ) ) )
	&& count( $all ) === 8 && array_unique( array_column( $all, 'origin' ) ) === [ 'basis' ] && array_unique( array_merge( ...array_column( $all, 'features' ) ) ) === [ 'basis' ]
	&& \Nino\Blocks::rejected( $appData ) === [] && ninoWarnings() === [] );
check( 'they are listed by category and rank, the header and the footer last, each with its words in both languages', array_column( $all, 'category' ) === [ 'intro', 'intro', 'intro', 'content', 'content', 'content', 'header', 'footer' ]
	&& array_unique( array_map( static fn( array $block ): string => implode( ',', array_keys( $block['name'] ) ), $all ) ) === [ 'hero-full' => 'de_DE,en_US' ] );
check( 'a variant the block names is told, with the component that has it', $all['three-columns']['variants'] === [ 'section-col' => [ 'card' ] ] && $all['hero-full']['variants'] === [] );

$bytes = 0;
$tight = true;
$back = true;
$drawn = [];
$buttons = [];
$appData['/nino/http/routes'] = [ 'GET://contact' => [ 'uri' => '/contact' ] ];

foreach( $all as $key => $listed ) {

	$block = \Nino\Blocks::get( $appData, $key );
	$dir = $root. '/_nino/Nino/Modules/Basis/blocks/'. $key;
	$source = (string) file_get_contents( $dir. '/block.tpl' );
	$bytes += strlen( $source );
	$tight = $tight && strlen( $source ) <= 2048 && filesize( $dir. '/text/de_DE.json' ) <= 1024 && filesize( $dir. '/text/en_US.json' ) <= 1024;
	$back = $back && $block['source'] === $source && \Nino\Markup::write( $block['tree'], \Nino\Components::containers( $appData ) ) === $source;

	// Put in a page as a tool does: the words of each source under the page and the id of the call that holds it
	$template = 'page-'. $key;
	$lines = [];

	foreach( $block['slots'] as $slot ) {

		$scope = $block['id']. ( $slot['scope'] === '' ? '' : '-'. $slot['scope'] );

		if( $slot['kind'] === 'image' )
			$appData['/nino/html/images']['/template/'. $template. '/'. $scope. '/'. substr( $slot['source'], 1 )] = [ 'label' => 'x', 'width' => 800, 'height' => 600, 'filename' => 'x.jpg', 'alt' => [] ];
		else
			$lines['/template/'. $template. '/'. $scope. '/'. substr( $slot['source'], 1 )] = $block['texts']['de_DE'][$slot['name']];
	}

	\Nino\Html::addFills( $appData, $lines, 'de_DE' );
	$html = \Nino\Components::render( $appData, $block['tree'], [ 'template' => $template ] );
	$drawn[$key] = $html !== '' && ninoWarnings() === [];

	$links = array_column( array_filter( $block['slots'], static fn( array $slot ): bool => $slot['kind'] === 'link' ), 'name' );

	foreach( $block['texts']['de_DE'] as $name => $text )
		$drawn[$key] = $drawn[$key] && ( in_array( $name, $links, true ) === true || str_contains( $html, htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) ) );

	$buttons[$key] = [ preg_match_all( '/<(?:a|span) class="button[ "]/', $html ), preg_match_all( '/<a class="button[ "]/', $html ) ];

	$drawn[$key] = $drawn[$key] && ( $block['images'] === [] || str_contains( $html, '<img' ) );
}

check( 'each is at most 2 KB of template and 1 KB of text for a language, and the eight templates together at most 16 KB', $tight === true && $bytes <= 16384 );
check( 'each is written back byte for byte from its tree, the same text a tool is handed', $back === true );
check( 'each is drawn with its texts and its picture slot as a page that holds it, and says nothing in the log', array_keys( array_filter( $drawn ) ) === $wanted );

check( 'the buttons of the blocks are links whose target is a text of the block, and are links where the page it names is there', array_sum( array_column( $buttons, 0 ) ) === 5 && array_sum( array_column( $buttons, 1 ) ) === 5
	&& preg_match_all( '/<a class="button/', basisLessDraw( $appData, '[button text="x" href="page:/nowhere" /]' ) ) === 0 );

$cards = \Nino\Blocks::get( $appData, 'three-columns' );
check( 'the sources of a block are named by the id above them: the words of the root by their name, those of a call with an id by the part of that id behind the root and the name', array_column( $cards['slots'], 'name' ) === [ 'title', 'text', 'a-title', 'a-text', 'b-title', 'b-text', 'c-title', 'c-text' ]
	&& $cards['slots'][2]['scope'] === 'a' && $cards['slots'][2]['source'] === '~title' && array_keys( $cards['texts'] ) === [ 'de_DE', 'en_US' ] && \Nino\Blocks::get( $appData, 'nobody' ) === null );

// The frames are made of rows and columns as a page is: the register finds nothing against them, nor against the templates of the starter site, and each holds its row
$frames = [];

foreach( [ 'header-line' => 'header-wrap', 'footer-columns' => 'footer-wrap' ] as $frameKey => $frameWrap ) {

	$frame = \Nino\Blocks::get( $appData, $frameKey );
	$starter = (string) file_get_contents( $root. '/tests/fixtures/basis/starter/base/templates/frame-'. ( $frameWrap === 'header-wrap' ? 'header' : 'footer' ). '.tpl' );
	$frames[$frameKey] = [ \Nino\Components::check( $appData, $frame['tree'] ), \Nino\Components::check( $appData, \Nino\Markup::parse( $starter, \Nino\Components::containers( $appData ) ) ),
		str_contains( $frame['source'], '[section-row' ), str_contains( $starter, '[section-row' ) ];
}

check( 'the header and the footer of the blocks and of the starter site are made of section-row and section-col, and the register finds nothing against any of the four', $frames['header-line'] === [ [], [], true, true ] && $frames['footer-columns'] === [ [], [], true, true ] );

// A component of the test that says the root of the context it is drawn in: put into every column of a frame it is asked there, in the columns of the header and of the footer alike
$roots = new \ArrayObject();
\Nino\Components::addComponent( $appData, 'root-echo', [ 'label' => 'Root echo', 'in' => [ 'flow' ], 'offers' => [] ],
	static function( array &$appData, array $args, string $content, array $context ) use ( $roots ): string {
		$roots[] = [ $context['root'], $context['place'] ];
		return '';
	} );

$beside = static function( array $nodes ) use ( &$beside ): array {

	foreach( $nodes as $at => $node ) {

		if( is_array( $node['c'] ?? null ) === true )
			$nodes[$at]['c'] = $beside( $node['c'] );

		if( ( $node['n'] ?? '' ) === 'section-col' )
			$nodes[$at]['c'] = array_merge( (array) ( $nodes[$at]['c'] ?? [] ), [ [ 'n' => 'root-echo', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ] ] );
	}

	return $nodes;
};
$answered = [];

foreach( [ 'header-line' => 'header-wrap', 'footer-columns' => 'footer-wrap' ] as $frameKey => $frameWrap ) {

	$roots->exchangeArray( [] );
	\Nino\Components::render( $appData, $beside( \Nino\Blocks::get( $appData, $frameKey )['tree'] ), [ 'template' => 'page-x' ] );
	$answered[$frameKey] = [ array_unique( array_column( $roots->getArrayCopy(), 0 ) ), array_unique( array_column( $roots->getArrayCopy(), 1 ) ) ];
}

check( 'a column of the header block is drawn under the root header-wrap and one of the footer block under footer-wrap, the place of both being the column', $answered === [ 'header-line' => [ [ 'header-wrap' ], [ 'section-col' ] ], 'footer-columns' => [ [ 'footer-wrap' ], [ 'section-col' ] ] ] );

// --- B. what is told of a block of basis ---------------------------------------------------------------------

echo "\nB. Origin, order of the roots, variants\n";

$features = \Nino\Features::all( $appData );
check( 'a block of basis is listed with the origin basis and the label of its origin: the name of the feature', ( $all['hero-full']['origin'] ?? '' ) === 'basis' && $all['hero-full']['label'] === $features['basis']['name'] );
check( 'the blocks that name variants say them: hero-full has some, and three-columns has none', $all['hero-full']['alternatives'] !== [] && $all['three-columns']['alternatives'] === [] );

// What decides between two blocks of one key: Basis, the project, the other features by their keys
blocksWrite( NINO_FEATURES_DIR. '/Aaa/blocks', 'hero-full', blocksParts() );
blocksWrite( NINO_FEATURES_DIR. '/Zzz/blocks', 'pricing', blocksParts() );
blocksWrite( $own, 'pricing', blocksParts() );
$appData['/nino/modules'] = [ '\\Nino\\Modules\\Zzz', '\\Nino\\Modules\\Basis', '\\Nino\\Modules\\Aaa' ];
blocksRequest( $appData );
$all = \Nino\Blocks::all( $appData );
$rejected = \Nino\Blocks::rejected( $appData );
ninoWarnings();
check( 'the order of the roots is Basis, the project, the other features by key: a block of the project is not put out by a feature that comes later, and none puts out one of Basis, and what is put out says by whom',
	array_column( \Nino\Blocks::roots( $appData ), 'origin' ) === [ 'basis', 'project', 'aaa', 'zzz' ] && $all['hero-full']['origin'] === 'basis' && $all['pricing']['origin'] === 'project'
	&& str_contains( (string) ( $rejected['aaa/hero-full'] ?? '' ), '"basis"' ) && str_contains( (string) ( $rejected['zzz/pricing'] ?? '' ), '"project"' ) );

$containers = \Nino\Components::containers( $appData );

$laid = static fn( array $block ): array => \Nino\Markup::layout( [ $block['tree'][0] ], 0 );

$split = \Nino\Blocks::get( $appData, 'split-image-text' );
$right = \Nino\Blocks::setVariant( $split, $laid( $split ), 'image-right' );
$extra = \Nino\Markup::parse( str_replace( "\n\t[section-row gap=\"3\"", "\n\t[section-row]\n\t\t[section-col]\n\t\t\t[text text=\"New\"]\n\t\t[/section-col]\n\t[/section-row]\n\t[section-row gap=\"3\"", \Nino\Markup::write( $right, $containers ) ), $containers );
check( 'a row put in before the picture row is a tree of its own: nothing is told of it, image-left changes nothing, and the picture row is not turned by image-right set on a left tree', \Nino\Blocks::variantOf( $split, $extra ) === null && \Nino\Blocks::setVariant( $split, $extra, 'image-left' ) === $extra
	&& \Nino\Blocks::variantsFit( $split, $extra ) === false && \Nino\Blocks::variantsFit( $split, $right ) === true );

$names = [ 'hero-full' => [ 'image-full', 'image-compact', 'plain' ], 'split-image-text' => [ 'image-left', 'image-right' ] ];
$told = true;
$moved = true;

foreach( $names as $key => $variants ) {

	$basis = \Nino\Blocks::get( $appData, $key );
	$told = $told && array_keys( $basis['alternatives'] ) === $variants && \Nino\Blocks::variantOf( $basis, $basis['tree'] ) === $variants[0] && $basis['alternatives'][$variants[1]]['label']['de_DE'] !== '' && $basis['alternatives'][$variants[1]]['label']['en_US'] !== '';

	foreach( $variants as $name ) {

		$put = \Nino\Blocks::setVariant( $basis, $basis['tree'], $name );
		$told = $told && \Nino\Blocks::variantOf( $basis, $put ) === $name && ( $name !== 'plain' || ( str_contains( \Nino\Markup::write( $put, $containers ), ' picture="off"' ) === true && str_contains( \Nino\Markup::write( $put, $containers ), 'image="~background"' ) === true ) );
		$moved = $moved && ( $name === $variants[0] ) === ( $put === $basis['tree'] ) && \Nino\Blocks::setVariant( $basis, $put, $variants[0] ) === $basis['tree'];
	}
}

check( 'hero-full and split-image-text load with their variants, each is set on the tree of the block and told again, and the second one is a tree of its own', $told === true && $moved === true );

// --- C. the roots of a block that basis brings -----------------------------------------------------------

echo "\nC. The three roots\n";

$wraps = \Nino\Blocks::wraps( $appData );
ksort( $wraps );
check( 'the roots of a block that the feature Basis says are three, each with its side: the section of a page, the header and the footer', $wraps === [ 'footer-wrap' => 'footer', 'header-wrap' => 'header', 'section-wrap' => 'page' ] );

ninoDone( $appData );
