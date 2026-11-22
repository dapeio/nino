<?php
declare(strict_types=1);

/**
 *	Nino
 *	blocks-smoke.php	Contract test for \Nino\Blocks - the pieces of a page a tool puts in, as data of a folder:
 *
 *											A. the eight blocks of basis: listed, within their sizes, written back byte for byte, and drawn with their texts
 *											B. one rule of a block each: what makes a block fail, as a fault in an otherwise good block
 *											C. what a folder may hold - code, links, a name that is none - read from the project, a hook and a feature
 *											D. what is kept: the cache of what was read, the log that says it once, a file that changed
 *											E. variants: block.json names them, the register holds their values, a tree is told its variant and is put into another
 *
 *	Usage: php tests/blocks-smoke.php
 */

// A project with no features of its own: the one feature of the run is the one the kernel delivers
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-blocks-features-'. getmypid() );
mkdir( NINO_FEATURES_DIR, 0755, true );
register_shutdown_function( static function(): void {
	@rmdir( NINO_FEATURES_DIR );
} );

// Three features of the project's own: Aaa and Zzz that bring blocks (on where the test says so), and Gone that brings a component and is off
foreach( [ 'Aaa' => 'aaa', 'Zzz' => 'zzz', 'Gone' => 'gone' ] as $directory => $key ) {

	mkdir( NINO_FEATURES_DIR. '/'. $directory, 0755, true );
	file_put_contents( NINO_FEATURES_DIR. '/'. $directory. '/'. $directory. ".php", "<?php\ndeclare(strict_types=1);\nnamespace Nino\\Modules {\n\tclass ". $directory. " {\n\t}\n}\n" );
	file_put_contents( NINO_FEATURES_DIR. '/'. $directory. '/feature.php', "<?php\nreturn [ 'key' => '". $key. "', 'name' => '". $directory. "', 'version' => '1.0.0' ];\n" );
}

mkdir( NINO_FEATURES_DIR. '/Gone/components/gone-card', 0755, true );

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
check( 'they are listed by category and rank, the frames last, each with its words in both languages', array_column( $all, 'category' ) === [ 'intro', 'intro', 'intro', 'content', 'content', 'content', 'frame', 'frame' ]
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
	$starter = (string) file_get_contents( $root. '/_admin/install/library/base/templates/frame-'. ( $frameWrap === 'header-wrap' ? 'header' : 'footer' ). '.tpl' );
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

// --- B. one rule each --------------------------------------------------------------------------------

echo "\nB. What a block must keep\n";

check( 'a block with nothing against it is one, and lists the picture slot it reads', is_array( \Nino\Blocks::validate( $appData, 'probe', blocksParts() ) ) && array_keys( \Nino\Blocks::validate( $appData, 'probe', blocksParts() )['images'] ) === [ 'background' ] );

$template = static function( string $from, string $to ): callable {
	return static function( array $parts ) use ( $from, $to ): array {
		$parts['tpl'] = str_replace( $from, $to, $parts['tpl'] );
		return $parts;
	};
};
$json = static function( callable $change ): callable {
	return static function( array $parts ) use ( $change ): array {
		$meta = json_decode( $parts['json'], true );
		$parts['json'] = json_encode( $change( $meta ) );
		return $parts;
	};
};
$text = static function( string $locale, callable $change ): callable {
	return static function( array $parts ) use ( $locale, $change ): array {
		$parts['texts'][$locale] = json_encode( $change( json_decode( $parts['texts'][$locale], true ) ) );
		return $parts;
	};
};

$faults = [
	'the template is not read as html+ (a call that is never closed)'							=> $template( '[/section-row]', '' ),
	'a name nobody registered'																											=> $template( '[section-title ~title]', '[nobody ~title]' ),
	'a call that has no schema ([template])'																				=> $template( '[section-title ~title]', '[section-title ~title][template /templates/html-header]' ),
	'[html] with the content in it'																									=> $template( '[section-title ~title]', '[section-title ~title][html]<b>x</b>[/html]' ),
	'words between the calls, with no < in them'																		=> $template( '[section-title ~title]', '[section-title ~title]hello world' ),
	'html between the calls'																												=> $template( '[section-title ~title]', '<script>alert(1)</script>[section-title ~title]' ),
	'a textfill between the calls'																									=> $template( '[section-title ~title]', '[section-title ~title][[/project/company/general/name]]' ),
	'a textfill in the value of an attribute'																				=> $template( '[section-title ~title]', '[section-title ~title href="[[/project/company/general/name]]"]' ),
	'a component at a place that is not meant for it'																=> $template( '[section-row]', '[section-row][section-wrap][/section-wrap]' ),
	'a value outside what the attribute takes'																			=> $template( '[section-title ~title]', '[section-title ~title size="huge"]' ),
	'a source that is a text of the project'																				=> $template( '[section-title ~title]', '[section-title /project/company/general/name]' ),
	'a source that is a slot of a page'																							=> $template( '[section-title ~title]', '[section-title ~title][image /template/page-home/hero/background]' ),
	'a picture that is no placeholder of the kernel'																=> static fn( array $parts ): array => $json( static fn( array $meta ): array => [ 'images' => [] ] + $meta )( $template( 'image="~background"', 'image="/logo"' )( $parts ) ),
	'a link that is a key of the project\'s'																				=> $template( '[section-title ~title]', '[section-title ~title href="/project/company/general/name"]' ),
	'an id inside that does not begin with the id of the root'											=> $template( 'id="probe-1"', 'id="other-1"' ),
	'an id inside, of a call that reads nothing, that does not begin with the id of the root'		=> $template( '[section-row]', '[section-row][section-col id="other"][/section-col]' ),
	'a template that is read and not written back as it stands (two spaces)'						=> $template( '[section-wrap id="probe"', '[section-wrap  id="probe"' ),
	'an id that stands twice'																												=> $template( '[section-row]', '[section-row][section-col id="probe-1"][/section-col]' ),
	'no id at the root'																															=> $template( 'id="probe" ', '' ),
	'two roots'																																			=> static fn( array $parts ): array => [ 'tpl' => $parts['tpl']. $parts['tpl'] ] + $parts,
	'a root that is no root'																												=> static fn( array $parts ): array => [ 'tpl' => '[section-row id="probe"][/section-row]' ] + $parts,
	'a template that is longer than 16 KB'																					=> static fn( array $parts ): array => [ 'tpl' => $parts['tpl']. str_repeat( ' ', 16384 ) ] + $parts,
	'a language that has other names for its texts than the first'									=> $text( 'de_DE', static fn( array $words ): array => [ 'other' => 'x' ] ),
	'a text, in both languages, that no source reads'																=> static fn( array $parts ): array => $text( 'de_DE', static fn( array $words ): array => $words + [ 'extra' => 'x' ] )( $text( 'en_US', static fn( array $words ): array => $words + [ 'extra' => 'x' ] )( $parts ) ),
	'a source that has no text in any language'																			=> static fn( array $parts ): array => [ 'texts' => [ 'de_DE' => '{}', 'en_US' => '{}' ] ] + $parts,
	'no text of the language every block has'																				=> static fn( array $parts ): array => [ 'texts' => [ 'de_DE' => $parts['texts']['de_DE'] ] ] + $parts,
	'a name of a text that jumps out of its folder'																	=> static fn( array $parts ): array => $text( 'de_DE', static fn( array $words ): array => $words + [ '../title' => 'x' ] )( $text( 'en_US', static fn( array $words ): array => $words + [ '../title' => 'x' ] )( $parts ) ),
	'markup in a text'																															=> $text( 'de_DE', static fn( array $words ): array => [ '1-title' => '<script>x</script>' ] ),
	'a null byte in a text'																													=> $text( 'de_DE', static fn( array $words ): array => [ '1-title' => "a\0b" ] ),
	'an address that runs a script'																									=> $template( '[section-title ~title]', '[section-title ~title href="javascript:alert(1)"]' ),
	'an address that runs a script, written with an entity'													=> $template( '[section-title ~title]', '[section-title ~title href="java&#115;cript:alert(1)"]' ),
	'a text a link reads that is an address that runs a script'											=> static fn( array $parts ): array => $text( 'de_DE', static fn( array $words ): array => [ '1-title' => 'javascript:alert(1)' ] + $words )( $template( '[section-title ~title]', '[section-title ~title href="~title"]' )( $parts ) ),
	'a textfill in a text'																													=> $text( 'de_DE', static fn( array $words ): array => [ '1-title' => '[[/project/company/general/name]]' ] ),
	'json that is nested too deep'																									=> $text( 'de_DE', static fn( array $words ): array => [ '1-title' => [ [ [ [ [ [ 'x' ] ] ] ] ] ] ] ),
	'a slot that the template reads no picture of'																	=> $json( static function( array $meta ): array { $meta['images']['more'] = $meta['images']['background']; return $meta; } ),
	'a slot of a placeholder that is not the kernel\'s'															=> $json( static function( array $meta ): array { $meta['images']['background']['placeholder'] = '/logo'; return $meta; } ),
	'a name of a slot that jumps out of its folder'																	=> $json( static function( array $meta ): array { $meta['images']['../x'] = $meta['images']['background']; return $meta; } ),
	'a category that is none'																												=> $json( static fn( array $meta ): array => [ 'category' => 'misc' ] + $meta ),
	'a frame category for a section'																								=> $json( static fn( array $meta ): array => [ 'category' => 'frame' ] + $meta ),
	'a key of block.json that is none'																							=> $json( static fn( array $meta ): array => $meta + [ 'script' => 'x' ] ),
	'a name in one language only'																										=> $json( static function( array $meta ): array { unset( $meta['name']['en_US'] ); return $meta; } ),
];

$passed = [];

foreach( $faults as $label => $change )
	if( is_string( \Nino\Blocks::validate( $appData, 'probe', $change( blocksParts() ) ) ) === false )
		$passed[] = $label;

check( 'each of '. count( $faults ). ' faults, one at a time in a good block, makes it no block'. ( $passed === [] ? '' : ' - not found: '. implode( '; ', $passed ) ), $passed === [] );
check( 'a key that is no slug is no key', is_string( \Nino\Blocks::validate( $appData, '../probe', blocksParts() ) ) && is_string( \Nino\Blocks::validate( $appData, 'Probe', blocksParts() ) ) );

$looped = blocksSite();
\Nino\Components::addComponent( $looped, 'blocks-loop', [ 'label' => 'Loop', 'in' => [ 'flow' ], 'offers' => [ 'flow' ], 'through' => true, 'children' => 'nodes', 'template' => '' ], static fn(): string => '' );
$loop = static function( string $inner ): array {

	$parts = blocksParts();
	$parts['tpl'] = '[section-wrap id="probe"][section-row][section-col]'. $inner. '[/section-col][/section-row][/section-wrap]';
	$parts['json'] = json_encode( [ 'images' => [] ] + json_decode( $parts['json'], true ) );
	$parts['texts'] = [ 'de_DE' => '{}', 'en_US' => '{}' ];

	return $parts;
};
check( 'a type of elements of the kernel is read as the source of a loop, and a field of its model below it - and neither where the block does not stand in one', is_array( \Nino\Blocks::validate( $looped, 'probe', $loop( '[blocks-loop /_placeholder][text .title][/blocks-loop]' ) ) ) === true
	&& is_string( \Nino\Blocks::validate( $looped, 'probe', $loop( '[text .title]' ) ) ) === true && is_string( \Nino\Blocks::validate( $looped, 'probe', $loop( '[blocks-loop /_placeholder][text .nope][/blocks-loop]' ) ) ) === true
	&& is_string( \Nino\Blocks::validate( $looped, 'probe', $loop( '[blocks-loop /project/items][text .title][/blocks-loop]' ) ) ) === true );

$variant = blocksParts();
$variant['tpl'] = str_replace( 'id="probe-1"', 'id="probe-1" variant="soft"', $variant['tpl'] );
check( 'a variant the register does not have is no fault: the block names it and the tool says what it appears as', \Nino\Blocks::validate( $appData, 'probe', $variant )['variants'] === [ 'section-col' => [ 'soft' ] ] );

// --- E. variants ---------------------------------------------------------------------------------------

echo "\nE. Variants\n";

$varSite = blocksSite();
$varDir = ninoSandboxDir( $varSite ). '/private/blocks';
$containers = \Nino\Components::containers( $varSite );
$label = static fn( string $de, string $en ): array => [ 'de_DE' => $de, 'en_US' => $en ];
$varied = static function( array $variants ): array {

	$parts = blocksParts();
	$meta = json_decode( $parts['json'], true );
	$meta['variants'] = $variants;
	$parts['json'] = json_encode( $meta );

	return $parts;
};

// The places of the template of blocksParts(): 'root' is the section, '1' its row, '1.1' the column, '1.1.1' the title - the blanks between the calls count
$good = [
	'plain'	=> [ 'label' => $label( 'Schlicht', 'Plain' ), 'set' => [] ],
	'wide'	=> [ 'label' => $label( 'Weit', 'Wide' ), 'set' => [ 'root' => [ 'height' => '100' ], '1' => [ 'gap' => '4', 'reverse' => 'on' ] ] ],
	'calm'	=> [ 'label' => $label( 'Ruhig', 'Calm' ), 'set' => [ 'root' => [ 'height' => '60' ] ] ],
];
$refused = static function( array $variants ) use ( $varSite, $varied ): string {

	$answer = \Nino\Blocks::validate( $varSite, 'probe', $varied( $variants ) );

	return is_string( $answer ) === true ? $answer : '';
};
$says = static fn( string $reason, string $name ): bool => str_starts_with( $reason, 'block.json, variants, "'. $name. '": ' ) || str_starts_with( $reason, 'block.json, variants: "'. $name. '"' ) || str_starts_with( $reason, 'block.json: "variants"' );

blocksWrite( $varDir, 'varied', $varied( $good ) );
$block = \Nino\Blocks::get( $varSite, 'varied' );
check( 'a block that names variants is a block, and get() and all() hand them back in the order of the file: name, label of every language and the set by place', is_array( $block ) === true && $block['alternatives'] === $good
	&& \Nino\Blocks::all( $varSite )['varied']['alternatives'] === $good && \Nino\Blocks::rejected( $varSite ) === [] && ninoWarnings() === []
	&& \Nino\Blocks::validate( $varSite, 'probe', $varied( $good ) )['alternatives'] === $good && $all['hero-full']['alternatives'] !== [] && \Nino\Blocks::all( $varSite )['varied']['variants'] === [] );
check( 'a block with no variants has none: an empty list', \Nino\Blocks::validate( $varSite, 'probe', blocksParts() )['alternatives'] === [] && $all['three-columns']['alternatives'] === [] );

blocksRequest( $varSite );
$kept = \Nino\Blocks::get( $varSite, 'varied' );
check( 'what was read is kept with its variants: the next request has them from the file of the cache, and says nothing', is_file( \Nino\Filesystem::path( $varSite, \Nino\Blocks::CACHE ) ) === true && $kept['alternatives'] === $good && ninoWarnings() === [] );

$reasons = [
	'a key of a variant that is none'																								=> [ 'wide', [ 'wide' => $good['wide'] + [ 'markup' => '<b>x</b>' ] ] ],
	'a place the template has no call at'																						=> [ 'wide', [ 'wide' => [ 'set' => [ '1.1.1.0' => [ 'size' => 'l' ] ] ] + $good['wide'] ] ],
	'a place that is no place'																											=> [ 'wide', [ 'wide' => [ 'set' => [ '../1' => [ 'gap' => '4' ] ] ] + $good['wide'] ] ],
	'an attribute the component has not'																						=> [ 'wide', [ 'wide' => [ 'set' => [ '1' => [ 'columns' => '4' ] ] ] + $good['wide'] ] ],
	'a value the attribute does not take'																						=> [ 'wide', [ 'wide' => [ 'set' => [ '1' => [ 'gap' => '9' ] ] ] + $good['wide'] ] ],
	'a name that is no word'																												=> [ 'Wide', [ 'Wide' => $good['wide'] ] ],
	'a label that lacks a language'																									=> [ 'wide', [ 'wide' => [ 'label' => [ 'en_US' => 'Wide' ] ] + $good['wide'] ] ],
];

foreach( $reasons as $what => [ $name, $variants ] ) {

	$why = $refused( $variants );
	check( 'a variant with '. $what. ' makes the block no block, with a sentence that names the variant', $why !== '' && $says( $why, $name ) === true );
}

// A block with a picture and a button, whose places are 1.1.1 and 1.1.3: what a variant must not set is tried on each kind of attribute
$media = blocksParts();
$media['tpl'] = "[section-wrap id=\"probe\"]\n\t[section-row]\n\t\t[section-col id=\"probe-1\"]\n\t\t\t[image ~pic alt=\"~alt\"]\n\t\t\t[button ~btn href=\"~link\"]\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]";
$mediaMeta = json_decode( $media['json'], true );
$mediaMeta['images'] = [ '1-pic' => $mediaMeta['images']['background'] ];
$media['json'] = json_encode( $mediaMeta );
$mediaWords = [ '1-alt' => 'Words', '1-btn' => 'Go', '1-link' => 'page:/start' ];
$media['texts'] = [ 'de_DE' => json_encode( $mediaWords ), 'en_US' => json_encode( $mediaWords ) ];
$mediaRefused = static function( array $set ) use ( $varSite, $good, $media ): string {

	$meta = json_decode( $media['json'], true );
	$meta['variants'] = [ 'wide' => [ 'label' => $good['wide']['label'], 'set' => $set ] ];
	$answer = \Nino\Blocks::validate( $varSite, 'probe', [ 'json' => json_encode( $meta ) ] + $media );

	return is_string( $answer ) === true ? $answer : '';
};
check( 'the block with a picture and a button is a block, and a set of a look at both is one', $mediaRefused( [] ) === '' && $mediaRefused( [ '1.1.1' => [ 'ratio' => '1-1' ], '1.1.3' => [ 'tone' => 'quiet' ] ] ) === '' );

$crossing = [
	'a source (~name) as the value'													=> [ '1.1.1' => [ 'ratio' => '~title' ] ],
	'a key of the project as the value'											=> [ 'root' => [ 'height' => '/project/company/general/name' ] ],
	'an attribute that reads a picture'											=> [ 'root' => [ 'image' => '/nino/placeholder/landscape' ] ],
	'an attribute that reads a text (the alt text of a picture)'	=> [ '1.1.1' => [ 'alt' => 'Injected words here' ] ],
	'an attribute that reads a link (a link target of a button)'		=> [ '1.1.3' => [ 'href' => 'tel:0800123' ] ],
	'a link target of a picture'														=> [ '1.1.1' => [ 'href' => 'mailto:x' ] ],
	'a switch that is no class (a picture that is no content)'			=> [ '1.1.1' => [ 'decorative' => 'on' ] ],
	'a choice that is no class (where a link opens)'					=> [ '1.1.3' => [ 'target' => 'blank' ] ],
	'the id of the call'																		=> [ 'root' => [ 'id' => 'other' ] ],
	'the class'																							=> [ 'root' => [ 'class' => 'evil' ] ],
	'a variant of the component'														=> [ 'root' => [ 'variant' => 'band' ] ],
	'a value that closes the call and opens another'				=> [ 'root' => [ 'height' => '100"][html]<b>' ] ],
	'a value that is not a string'													=> [ 'root' => [ 'dim' => true ] ],
];
$passed = [];

foreach( $crossing as $what => $set )
	if( $mediaRefused( $set ) === '' )
		$passed[] = $what;

check( 'a set that would bring a source, a key, a picture, a text, an id, the class, markup or a variant of a component into a page makes the block no block'. ( $passed === [] ? '' : ' - not found: '. implode( '; ', $passed ) ), $passed === [] );
check( 'a set at a component that reads its words is allowed where it sets a look, and an empty set is one: the block is a block', $refused( [ 'one' => [ 'label' => $label( 'Eins', 'One' ), 'set' => [ '1.1.1' => [ 'size' => 'xl' ] ] ], 'two' => $good['plain'] ] ) === '' );

$many = [];

for( $i = 1; $i <= \Nino\Blocks::MAX_VARIANTS + 1; $i++ )
	$many['v'. $i] = $good['plain'];

check( 'at most eight variants: eight are a block, nine are not, and the limit is the literal', ( new ReflectionClassConstant( \Nino\Blocks::class, 'MAX_VARIANTS' ) )->getValue() === 8
	&& $refused( array_slice( $many, 0, 8 ) ) === '' && $says( $refused( $many ), 'x' ) === true && $refused( $many ) !== '' );

$withTag = $refused( [ 'one' => [ 'label' => $label( 'Eins', 'One' ), 'set' => [ '1.1.1' => [ 'tag' => 'div' ] ] ] ] );
check( 'a choice of a component that is not written as a class of it (tag of a title, where it is a div or a heading) is no look: the block is refused', $withTag !== '' && $says( $withTag, 'one' ) === true
	&& $refused( [ 'one' => [ 'label' => $label( 'Eins', 'One' ), 'set' => [ '1.1.1' => [ 'size' => 'xl' ] ] ] ] ) === '' );
check( 'a place is canonical and a call as the template is put into a page: no leading zero, no blank between calls, no call that is not there', $refused( [ 'one' => [ 'label' => $label( 'Eins', 'One' ), 'set' => [ '01' => [ 'gap' => '4' ] ] ] ] ) !== ''
	&& $refused( [ 'one' => [ 'label' => $label( 'Eins', 'One' ), 'set' => [ '0' => [ 'gap' => '4' ] ] ] ] ) !== '' && $refused( [ 'one' => [ 'label' => $label( 'Eins', 'One' ), 'set' => [ '1.0' => [ 'gap' => '4' ] ] ] ] ) !== '' );
// json_encode() writes a list for a map whose only key is 0, so the file is written out
$raw = static function( string $variants ): array {

	$parts = blocksParts();
	$parts['json'] = str_replace( '"category"', '"variants":'. $variants. ',"category"', $parts['json'] );

	return $parts;
};
$zero = \Nino\Blocks::validate( $varSite, 'probe', $raw( '{"0":{"label":{"de_DE":"N","en_US":"N"},"set":{}}}' ) );
$zeroPlace = \Nino\Blocks::validate( $varSite, 'probe', $raw( '{"a":{"label":{"de_DE":"N","en_US":"N"},"set":{"0":{"gap":"4"}}}}' ) );
check( 'a name that is 0 is a name, and a place that is 0 is a place that is no call, with the sentence for that and not the one for a list', is_array( $zero ) === true && array_keys( $zero['alternatives'] ) === [ 0 ]
	&& is_string( $zeroPlace ) === true && str_contains( $zeroPlace, 'no call' ) === true );
check( 'variants that are null or a list are no map: the block is refused, and none at all is a block', is_string( \Nino\Blocks::validate( $varSite, 'probe', $raw( 'null' ) ) ) === true && is_string( \Nino\Blocks::validate( $varSite, 'probe', $raw( '["x"]' ) ) ) === true
	&& is_array( \Nino\Blocks::validate( $varSite, 'probe', blocksParts() ) ) === true );

// A tree as a page holds it, changed as a person changes it: the notation is written, changed in its text and read again
$edit = static fn( array $tree, string $from, string $to ): array => \Nino\Markup::parse( str_replace( $from, $to, \Nino\Markup::write( $tree, $containers ) ), $containers );
$tree = $block['tree'];
$wide = \Nino\Blocks::setVariant( $block, $tree, 'wide' );
$calm = \Nino\Blocks::setVariant( $block, $tree, 'calm' );

check( 'which variant a tree has: the one whose set holds at all its places - the template is the first with an empty set, the others are told by what they set', \Nino\Blocks::variantOf( $block, $tree ) === 'plain'
	&& \Nino\Blocks::variantOf( $block, $wide ) === 'wide' && \Nino\Blocks::variantOf( $block, $calm ) === 'calm' );
check( 'a tree whose person changed an attribute that a variant sets, to a value none of them has, is its own: null', \Nino\Blocks::variantOf( $block, $edit( $wide, 'gap="4"', 'gap="1"' ) ) === null
	&& \Nino\Blocks::variantOf( $block, $edit( $tree, '[section-row]', '[section-row valign="end" gap="3"]' ) ) === null );
check( 'a tree keeps its variant when a person changes a text, a source or an attribute that no variant sets', \Nino\Blocks::variantOf( $block, $edit( $wide, '[section-title ~title]', '[section-title ~other text="Hello" tag="h2"]' ) ) === 'wide'
	&& \Nino\Blocks::variantOf( $block, $edit( $tree, '[section-wrap id="probe"', '[section-wrap id="probe" ground="dark"' ) ) === 'plain' );

$twice = $block;
$twice['alternatives'] = [ 'same' => $good['plain'] ] + $good;
check( 'two variants that hold are told by the first in the order of the file', \Nino\Blocks::variantOf( $twice, $tree ) === 'same' && \Nino\Blocks::variantOf( $block, $tree ) === 'plain'
	&& \Nino\Blocks::variantOf( [ 'tree' => $tree ], $tree ) === null );

$mine = $edit( $edit( $tree, '[section-row]', '[section-row halign="center"]' ), '[section-title ~title]', '[section-title ~other text="Hello" tag="h2"]' );
check( 'setting a variant and setting the first again gives the tree it began with, to the byte, and so do setting two variants one after the other', \Nino\Blocks::setVariant( $block, $wide, 'plain' ) === $tree
	&& \Nino\Blocks::setVariant( $block, \Nino\Blocks::setVariant( $block, $mine, 'wide' ), 'plain' ) === $mine && \Nino\Blocks::setVariant( $block, $calm, 'wide' ) === $wide && \Nino\Blocks::setVariant( $block, $wide, 'calm' ) === $calm
	&& \Nino\Markup::write( \Nino\Blocks::setVariant( $block, $wide, 'plain' ), $containers ) === $block['source'] );

$set = \Nino\Blocks::setVariant( $block, $mine, 'wide' );
$strip = static function( array $nodes ) use ( &$strip ): array {

	foreach( $nodes as $at => $node )
		if( isset( $node['n'] ) === true ) {

			$nodes[$at]['a'] = array_diff_key( $node['a'], [ 'height' => 1, 'gap' => 1, 'reverse' => 1 ] );
			$nodes[$at]['c'] = is_array( $node['c'] ) === true ? $strip( $node['c'] ) : null;
		}

	return $nodes;
};
check( 'a variant changes what it sets and nothing else: texts, sources, children and the attributes of a person that no variant names stay as they were', $strip( $set ) === $strip( $mine ) && $set !== $mine
	&& \Nino\Markup::write( \Nino\Blocks::setVariant( $block, $edit( $wide, 'gap="4"', 'gap="1"' ), 'calm' ), $containers ) === \Nino\Markup::write( $calm, $containers ) );

$cut = $tree;
$cut[0]['c'] = [ $tree[0]['c'][0] ];
$other = $tree;
$other[0]['c'][1]['n'] = 'section-col';
$grown = \Nino\Blocks::setVariant( $block, $cut, 'wide' );
check( 'a place the tree has not, or where another component stands, is passed over by setVariant and makes the tree its own for variantOf, which variantsFit says; a name that is none gives the tree back', $grown === array_replace_recursive( $cut, [ 0 => [ 'a' => [ 'height' => '100' ] ] ] )
	&& \Nino\Blocks::setVariant( $block, $other, 'wide' )[0]['c'][1] === $other[0]['c'][1] && \Nino\Blocks::variantOf( $block, $cut ) === null && \Nino\Blocks::variantOf( $block, $other ) === null
	&& \Nino\Blocks::variantOf( $block, array_replace( $tree, [ 0 => array_replace( $tree[0], [ 'n' => 'section-col' ] ) ] ) ) === null && \Nino\Blocks::setVariant( $block, array_replace( $tree, [ 0 => array_replace( $tree[0], [ 'n' => 'section-col' ] ) ] ), 'wide' )[0]['a'] === $tree[0]['a']
	&& \Nino\Blocks::variantsFit( $block, $tree ) === true && \Nino\Blocks::variantsFit( $block, $cut ) === false && \Nino\Blocks::variantsFit( $block, $other ) === false && \Nino\Blocks::setVariant( $block, $tree, 'nobody' ) === $tree );

$added = $edit( $tree, "\n\t[section-row]", "\n\t[section-row]\n\t[/section-row]\n\t[section-row]" );
$moved = $edit( $tree, '[section-row]', '[section-col]' );
check( 'a call put in before the place of a set, at any level on the way to it, makes the tree its own: nothing is told, nothing is set - and the root alone has no level to differ in', \Nino\Blocks::variantOf( $block, $added ) === null && \Nino\Blocks::variantsFit( $block, $added ) === false
	&& \Nino\Blocks::setVariant( $block, $added, 'wide' )[0]['c'] === $added[0]['c'] && \Nino\Blocks::setVariant( $block, $added, 'wide' )[0]['a']['height'] === '100'
	&& \Nino\Blocks::variantOf( $block, [ [ 'n' => 'section-wrap', 's' => '', 'a' => [ 'height' => '60' ], 'c' => null ] ] ) === null
	&& \Nino\Blocks::variantOf( $block, $moved ) === null );

$page = array_merge( [ [ 't' => "Intro\n" ] ], $wide );
check( 'a tree that has words before its root has the root found as its first call, and the places are counted from it', \Nino\Blocks::variantOf( $block, $page ) === 'wide' && \Nino\Blocks::setVariant( $block, $page, 'calm' )[0] === $page[0] && \Nino\Blocks::variantOf( $block, \Nino\Blocks::setVariant( $block, $page, 'calm' ) ) === 'calm'
	&& \Nino\Blocks::variantOf( $block, [] ) === null && \Nino\Blocks::variantOf( $block, [ [ 't' => 'x' ] ] ) === null && \Nino\Blocks::variantsFit( $block, [] ) === false );

$shifted = $edit( $tree, '[section-wrap id="probe" image="~background"', '[section-wrap id="probe" height="80" image="~background"' );
check( 'an attribute that stands in the call is set where it stands, and one that is not is put at its end: the bytes of the rest are the person\'s', str_contains( \Nino\Markup::write( \Nino\Blocks::setVariant( $block, $shifted, 'calm' ), $containers ), 'id="probe" height="60" image="~background"' ) === true
	&& str_contains( \Nino\Markup::write( \Nino\Blocks::setVariant( $block, $shifted, 'wide' ), $containers ), '[section-row gap="4" reverse="on"]' ) === true );

$stack = $good + [ 'stack' => [ 'label' => $label( 'Gestapelt', 'Stacked' ), 'set' => [ '1' => [ 'gap' => '2' ] ] ] ];
$defaulted = \Nino\Blocks::validate( $varSite, 'probe', $varied( $stack ) ) + [ 'tree' => $block['tree'] ];
check( 'an attribute that is not written has the default of its schema: one that is written as the default is the same for variantOf, and setVariant takes the attribute out where the value is the default', is_array( $defaulted ) === true
	&& \Nino\Blocks::variantOf( $defaulted, $edit( $tree, '[section-row]', '[section-row gap="2"]' ) ) === 'plain' && \Nino\Blocks::variantOf( $defaulted, $edit( $tree, '[section-wrap id="probe"', '[section-wrap id="probe" dim="off"' ) ) === 'plain'
	&& \Nino\Markup::write( \Nino\Blocks::setVariant( $defaulted, $wide, 'stack' ), $containers ) === $block['source'] );

$broken = [
	[ [ 'alternatives' => 'x', 'tree' => $tree ], $tree ],
	[ [ 'alternatives' => [ 'v' => 'x' ], 'tree' => $tree ], $tree ],
	[ [ 'alternatives' => [ 'v' => [ 'set' => [ 'root' => 'x' ] ] ], 'tree' => $tree ], $tree ],
	[ [ 'alternatives' => [ 'v' => [ 'set' => [ 'root' => [ 'height' => 5 ] ] ] ], 'tree' => 'x' ], $tree ],
	[ $block, [ [ 'n' => 'section-wrap', 'a' => 'text', 'c' => [] ] ] ],
	[ $block, [ [ 'n' => 'section-wrap', 'a' => [ 'height' => '60' ], 'c' => [ 'x', 5, null ] ] ] ],
	[ $block, [ 'x', 5, null ] ],
];
$threw = 0;

foreach( $broken as [ $odd, $oddTree ] )
	try {

		\Nino\Blocks::variantOf( $odd, $oddTree );
		\Nino\Blocks::variantsFit( $odd, $oddTree );
		\Nino\Blocks::setVariant( $odd, $oddTree, 'v' );
		\Nino\Blocks::setVariant( $odd, $oddTree, 'wide' );
	}
	catch( \Throwable ) {
		$threw++;
	}

check( 'nothing throws for a block or a tree that is of no form the kernel makes: hand-built alternatives, a tree whose attributes are no list, words in the place of calls', $threw === 0 );

$laid = static fn( array $block ): array => \Nino\Markup::layout( [ $block['tree'][0] ], 0 );
$compactTpl = '[section-wrap id="probe"][section-row][section-col id="probe-1"][section-title ~title][/section-col][/section-row][section-row][section-col id="probe-2"][section-title ~two][/section-col][/section-row][/section-wrap]';
$compact = blocksParts();
$compact['tpl'] = $compactTpl;
$compactMeta = json_decode( $compact['json'], true );
$compactMeta['images'] = [];
$compactMeta['variants'] = [ 'a' => [ 'label' => $label( 'A', 'A' ), 'set' => [] ], 'b' => [ 'label' => $label( 'B', 'B' ), 'set' => [ '3' => [ 'reverse' => 'on' ] ] ] ];
$compact['json'] = json_encode( $compactMeta );
$compact['texts'] = [ 'de_DE' => json_encode( [ '1-title' => 'T', '2-two' => 'Z' ] ), 'en_US' => json_encode( [ '1-title' => 'T', '2-two' => 'Z' ] ) ];
blocksWrite( $varDir, 'compact', $compact );
blocksRequest( $varSite );
$cb = \Nino\Blocks::get( $varSite, 'compact' );
$put = is_array( $cb ) === true ? \Nino\Blocks::setVariant( $cb, $laid( $cb ), 'b' ) : [];
check( 'a template with no blanks between its calls is read in the form the block is put into a page in: the places are those of that form, the second row is "3", the variant is set on it and told, and the first row is left', is_array( $cb ) === true
	&& substr_count( \Nino\Markup::write( $put, $containers ), 'reverse="on"' ) === 1 && str_contains( \Nino\Markup::write( $put, $containers ), "\n\t[section-row]\n\t\t[section-col id=\"probe-2\"]" ) === false
	&& \Nino\Blocks::variantOf( $cb, $put ) === 'b' && \Nino\Blocks::variantOf( $cb, $laid( $cb ) ) === 'a' && \Nino\Blocks::variantOf( $cb, $cb['tree'] ) === null );

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

\Nino\Filesystem::removeDir( ninoSandboxDir( $varSite ) );

// --- C. what a folder may hold -------------------------------------------------------------------------

echo "\nC. Folders\n";

$theme = ninoSandboxDir( $appData ). '/theme-blocks';
blocksWrite( $own, 'mine', blocksParts() );
blocksWrite( $theme, 'themed', blocksParts() );
blocksWrite( $theme, 'mine', blocksParts() );
$code = blocksWrite( $own, 'with-code', blocksParts() );
file_put_contents( $code. '/helper.php', '<?php echo 1;' );
file_put_contents( blocksWrite( $own, 'hidden-code', blocksParts() ). '/.y.php', '<?php' );
file_put_contents( blocksWrite( $own, 'hidden-text-code', blocksParts() ). '/text/.x.php', '<?php' );
file_put_contents( blocksWrite( $own, 'upper-code', blocksParts() ). '/.z.PHP', '<?php' );
file_put_contents( blocksWrite( $own, 'seven-code', blocksParts() ). '/.q.php7', '<?php' );
file_put_contents( blocksWrite( $own, 'with-extra', blocksParts() ). '/readme.txt', 'x' );
blocksWrite( $own, 'Capital', blocksParts() );
$linked = blocksWrite( $own, 'linked-file', blocksParts() );
$jump = blocksWrite( ninoSandboxDir( $appData ). '/elsewhere', 'jump', blocksParts() );
$links = @symlink( $jump, $own. '/linked-folder' ) && @unlink( $linked. '/block.tpl' ) && @symlink( $jump. '/block.tpl', $linked. '/block.tpl' );
blocksWrite( $own, 'dot.name', blocksParts() );
\Nino\Callbacks::registerCallback( $appData, '/nino/blocks', static function( array &$appData, array &$roots ) use ( $theme ): array {

	$roots[] = [ 'dir' => $theme, 'origin' => 'theme-probe', 'label' => [ 'de_DE' => 'Theme-Probe', 'en_US' => 'Theme probe' ] ];
	$roots[] = [ 'dir' => $theme. '/none', 'origin' => 'label-bad', 'label' => "two\nlines" ];
	$roots[] = [ 'dir' => $theme. '/none', 'origin' => 'label-less' ];
	$roots[] = [ 'dir' => 'relative/path', 'origin' => 'bad' ];
	$roots[] = 'not a root';

	return $roots;
} );

blocksRequest( $appData );
$all = \Nino\Blocks::all( $appData );
$rejected = \Nino\Blocks::rejected( $appData );
$said = ninoWarnings();

check( 'a block of the project and one a hook adds are listed with the origin they come from, beside those of basis', ( $all['mine']['origin'] ?? '' ) === 'project' && ( $all['themed']['origin'] ?? '' ) === 'theme-probe' && ( $all['hero-full']['origin'] ?? '' ) === 'basis' );
$labels = array_column( \Nino\Blocks::roots( $appData ), 'label', 'origin' );
$features = \Nino\Features::all( $appData );
check( 'a block carries the label of its origin: the name of the feature, the word for the project, what the hook gave, and the origin where the hook gave none or a bad one', $all['hero-full']['label'] === $features['basis']['name']
	&& $all['mine']['label'] === \Nino\Blocks::PROJECT && $all['themed']['label'] === [ 'de_DE' => 'Theme-Probe', 'en_US' => 'Theme probe' ]
	&& $labels['label-bad'] === 'label-bad' && $labels['label-less'] === 'label-less' );
check( 'a key that an earlier root has is the earlier one: the later is refused', ( $rejected['theme-probe/mine'] ?? '' ) !== '' );
check( 'a folder with php in it - in plain sight, hidden at its top, hidden in text/ - and one with a file that is none of the three, and a name that is no slug, are no blocks', array_intersect( [ 'with-code', 'hidden-code', 'hidden-text-code', 'upper-code', 'seven-code', 'with-extra', 'Capital', 'dot.name' ], array_keys( $all ) ) === []
	&& count( array_intersect_key( $rejected, array_flip( [ 'project/with-code', 'project/hidden-code', 'project/hidden-text-code', 'project/upper-code', 'project/seven-code', 'project/with-extra', 'project/Capital', 'project/dot.name' ] ) ) ) === 8 );
check( 'a folder that is a link, and a file of a block that is one, are no blocks'. ( $links === true ? '' : ' (no links here: not run)' ), $links === false || ( isset( $all['linked-folder'] ) === false && isset( $all['linked-file'] ) === false && isset( $rejected['project/linked-folder'], $rejected['project/linked-file'] ) === true ) );
check( 'a block that is refused is said once, with its reason, and nothing else is said but an entry of the hook that is none', count( array_filter( $said, static fn( string $line ): bool => str_contains( $line, 'with-code' ) ) ) === 1
	&& count( array_filter( $said, static fn( string $line ): bool => str_contains( $line, '"/nino/blocks"' ) ) ) === 3 && count( array_filter( $said, static fn( string $line ): bool => str_contains( $line, '"label"' ) ) ) === 1 && count( $said ) === count( $rejected ) + 3 );

// What decides between two blocks of one key: Basis, the project, the other features by their keys
blocksWrite( NINO_FEATURES_DIR. '/Aaa/blocks', 'hero-full', blocksParts() );
blocksWrite( NINO_FEATURES_DIR. '/Zzz/blocks', 'pricing', blocksParts() );
blocksWrite( $own, 'pricing', blocksParts() );
$appData['/nino/modules'] = [ '\\Nino\\Modules\\Zzz', '\\Nino\\Modules\\Basis', '\\Nino\\Modules\\Aaa' ];
blocksRequest( $appData );
$all = \Nino\Blocks::all( $appData );
$rejected = \Nino\Blocks::rejected( $appData );
ninoWarnings();
check( 'the order of the roots is Basis, the project, the other features by key, the hook: a block of the project is not put out by a feature that comes later, and none puts out one of Basis, and what is put out says by whom',
	array_column( \Nino\Blocks::roots( $appData ), 'origin' ) === [ 'basis', 'project', 'aaa', 'zzz', 'theme-probe', 'label-bad', 'label-less' ] && $all['hero-full']['origin'] === 'basis' && $all['pricing']['origin'] === 'project'
	&& str_contains( (string) ( $rejected['aaa/hero-full'] ?? '' ), '"basis"' ) && str_contains( (string) ( $rejected['zzz/pricing'] ?? '' ), '"project"' ) );

// A component that a feature which is off brings: the block is listed and needs that feature
$needs = blocksParts();
$needs['tpl'] = str_replace( '[section-title ~title]', '[gone-card ~title]', $needs['tpl'] );
blocksWrite( $own, 'needs-gone', $needs );
$strange = blocksParts();
$strange['tpl'] = str_replace( '[section-title ~title]', '[nobody ~title]', $strange['tpl'] );
blocksWrite( $own, 'needs-nobody', $strange );
blocksRequest( $appData );
$all = \Nino\Blocks::all( $appData );
$gone = \Nino\Blocks::get( $appData, 'needs-gone' );
check( 'a block that calls a component of a feature that is off is listed with the keys of those features in missing and in features - and is no block of its own to draw: get() answers what all() says and no tree, source, texts or slots; one that calls a name no feature brings is refused',
	( $all['needs-gone']['missing'] ?? null ) === [ 'gone' ] && in_array( 'gone', $all['needs-gone']['features'], true ) === true && $all['hero-full']['missing'] === [] && $gone === $all['needs-gone']
	&& array_intersect( [ 'tree', 'source', 'texts', 'slots' ], array_keys( $gone ) ) === [] && isset( $all['needs-nobody'] ) === false && isset( \Nino\Blocks::rejected( $appData )['project/needs-nobody'] ) === true );
ninoWarnings();

// A root that is itself a link is not read
$linkSite = blocksSite();
$elsewhere = ninoSandboxDir( $linkSite ). '/elsewhere';
blocksWrite( $elsewhere, 'probe', blocksParts() );
mkdir( ninoSandboxDir( $linkSite ). '/private', 0755, true );
$rootLink = @symlink( $elsewhere, ninoSandboxDir( $linkSite ). '/private/blocks' );
check( 'a folder of blocks that is a link is not read'. ( $rootLink === true ? '' : ' (no links here: not run)' ), $rootLink === false || isset( \Nino\Blocks::all( $linkSite )['probe'] ) === false );
ninoWarnings();

// --- D. what is kept -------------------------------------------------------------------------------------

echo "\nD. The cache\n";

$file = \Nino\Filesystem::path( $appData, \Nino\Blocks::CACHE );
check( 'what was read is kept in a file of the cache of the project', is_file( $file ) === true );

blocksRequest( $appData );
$all = \Nino\Blocks::all( $appData );
$rejected = \Nino\Blocks::rejected( $appData );
ninoWarnings();
blocksRequest( $appData );
$again = \Nino\Blocks::all( $appData );
check( 'the next request has the same blocks and the same reasons from the file, and says nothing', $again === $all && \Nino\Blocks::rejected( $appData ) === $rejected && array_filter( ninoWarnings(), static fn( string $line ): bool => str_contains( $line, 'not listed' ) ) === [] );

unlink( $own. '/with-code/helper.php' );
file_put_contents( $own. '/mine/text/en_US.json', json_encode( [ '1-title' => 'Changed' ] ) );
blocksRequest( $appData );
$now = \Nino\Blocks::get( $appData, 'mine' );
check( 'a file that changed is read again, and a folder that is clean now is a block', ( $now['texts']['en_US']['1-title'] ?? '' ) === 'Changed' && isset( \Nino\Blocks::all( $appData )['with-code'] ) === true );

\Nino\Html::dropTrees( $appData );
check( 'the file goes with the trees of the templates, and is made again by the next read', is_file( $file ) === false && ( blocksRequest( $appData ) ?? true ) === true && count( \Nino\Blocks::all( $appData ) ) > 0 && is_file( $file ) === true );

unlink( $file );
$appData['/nino/markup/cache'] = false;
blocksRequest( $appData );
\Nino\Blocks::all( $appData );
check( 'with the cache of the trees turned off nothing is written', is_file( $file ) === false );

foreach( [ $own. '/linked-folder', $own. '/linked-file/block.tpl', ninoSandboxDir( $linkSite ). '/private/blocks' ] as $link )
	if( is_link( $link ) === true )
		unlink( $link );

// --- F. a frame reads the texts of the project ------------------------------------------------------------

echo "\nF. A frame may read the texts of the project and the logo, and nothing else of the project\n";

$frameSite = blocksSite();
$frame = static function( string $tpl, string $category = 'frame' ): array {

	$words = static fn( string $de, string $en ): array => [ 'de_DE' => $de, 'en_US' => $en ];

	return [
		'json'	=> json_encode( [ 'name' => $words( 'Rahmen', 'Frame' ), 'description' => $words( 'Ein Rahmen', 'A frame' ), 'category' => $category, 'rank' => 5, 'images' => (object) [] ] ),
		'tpl'		=> $tpl,
		'texts'	=> [ 'de_DE' => '{}', 'en_US' => '{}' ],
	];
};
$footer = static fn( string $inside ): string => "[footer-wrap id=\"footer\"]\n\t[footer-col size=\"100\"]\n\t\t". $inside. "\n\t[/footer-col]\n[/footer-wrap]";
$good = \Nino\Blocks::validate( $frameSite, 'footer', $frame( $footer( "[footer-logo /logo]\n\t\t[text /project/company/general/description format=\"inline\" size=\"s\"]" ) ) );

check( 'a footer that reads the logo of the site and a text of the project is a block: the standard footer of a site can be one', is_array( $good ) === true && $good['wrap'] === 'footer-wrap' && $good['category'] === 'frame' );
check( '...it carries the KEY and never the text; the sources it reads are no slot of the block, nor a text of it, so no file of it - the template, the texts, an export - holds what the site says',
	is_array( $good ) === true && str_contains( $good['source'], '/project/company/general/description' ) === true && $good['slots'] === [] && $good['texts'] === [ 'de_DE' => [], 'en_US' => [] ] );

$header = \Nino\Blocks::validate( $frameSite, 'header', $frame( "[header-wrap id=\"header\"]\n\t[header-logo]\n\t[header-actions]\n\t\t[button /project/company/general/name href=\"page:/contact\"]\n\t[/header-actions]\n[/header-wrap]" ) );
check( 'a header may do it as well, in any call whose first word is a text', is_array( $header ) === true );

// Every key of the text store is read, whatever root it is under: the owner answers for what a frame shows
$every = [ '/template/page-x/main/secret', '/template/page-members/main/secret', '/feature/newsletter/confirm/url', '/module/form/info/name', '/project/mail/address/owner', '/project/mail/address/envelope', '/project/mail/color/primary', '/project/company/general/description' ];
check( 'a frame reads the text of a page - one behind a password too -, the words of a feature and of a module, the mailbox of the forms, the sender and the look of the mails: every key of the four roots of the text store',
	array_filter( $every, static fn( string $key ): bool => is_array( \Nino\Blocks::validate( $frameSite, 'footer', $frame( $footer( '[text '. $key. ']' ) ) ) ) === false ) === [] );

// ...and the owner can close a prefix again with one entry of FRAME_TEXT_EXCEPT: shown on a copy of the class that has one, in a process of its own
$copyDir = ninoSandboxDir( $frameSite );
$blocksCode = (string) file_get_contents( $root. '/_nino/Nino/Blocks/Blocks.php' );
$closed = str_replace( 'FRAME_TEXT_EXCEPT = [];', "FRAME_TEXT_EXCEPT = [ '/project/mail/' ];", $blocksCode );
$askScript = <<<'PHP'
<?php
require '@ROOT@/tests/harness.php';
require '@COPY@/BlocksCopy.php';
$appData = ninoSandbox( 'blocks-copy' );
$appData['./nino/filesystem/path'] = '@ROOT@';
$appData['/nino/modules'] = [ '\Nino\Modules\Basis' ];
$appData['/nino/html/navs'] = [ 'main', 'footer' ];
\Nino\Features::registerComponents( $appData );
$words = [ 'de_DE' => 'x', 'en_US' => 'x' ];
$parts = [
	'json' => json_encode( [ 'name' => $words, 'description' => $words, 'category' => 'frame', 'rank' => 5, 'images' => (object) [] ] ),
	'tpl' => '[footer-wrap id="footer"][footer-col size="100"][text @KEY@][/footer-col][/footer-wrap]',
	'texts' => [ 'de_DE' => '{}', 'en_US' => '{}' ],
];
$answer = is_array( \Nino\Blocks::validate( $appData, 'footer', $parts ) ) ? 'read' : 'refused';
\Nino\Filesystem::removeDir( ninoSandboxDir( $appData ) );
echo 'ANSWER:'. $answer;
PHP;
$ask = static function( string $code, string $key ) use ( $copyDir, $root, $askScript ): string {

	file_put_contents( $copyDir. '/BlocksCopy.php', $code );
	file_put_contents( $copyDir. '/ask.php', strtr( $askScript, [ '@ROOT@' => $root, '@COPY@' => $copyDir, '@KEY@' => $key ] ) );
	preg_match( '/ANSWER:(\w+)/', (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( $copyDir. '/ask.php' ). ' 2>/dev/null' ), $found );

	return $found[1] ?? '';
};
check( 'one prefix in FRAME_TEXT_EXCEPT closes it for a frame: the mailbox is read by the class as it is, and refused by a copy that has /project/mail/ there - and the company text is read by both',
	$ask( $blocksCode, '/project/mail/address/owner' ) === 'read' && $ask( $closed, '/project/mail/address/owner' ) === 'refused' && $ask( $closed, '/project/company/general/description' ) === 'read' );

// The limit is 200 bytes of the key: the key of 200 is read, one byte more is not
$edge = static fn( int $length ): string => '/project/company/general/'. str_repeat( 'a', $length - strlen( '/project/company/general/' ) );
check( 'a key of 200 bytes is read by a frame and one of 201 is not', strlen( $edge( 200 ) ) === 200 && strlen( $edge( 201 ) ) === 201
	&& is_array( \Nino\Blocks::validate( $frameSite, 'footer', $frame( $footer( '[text '. $edge( 200 ). ']' ) ) ) ) === true && is_string( \Nino\Blocks::validate( $frameSite, 'footer', $frame( $footer( '[text '. $edge( 201 ). ']' ) ) ) ) === true );

$refusedKeys = [];

foreach( [
	'a way up'							=> '/project/company/../name',
	'two slashes'						=> '/project//general/name',
	'a backslash'						=> '/project/company\\general/name',
	'a control character'		=> "/project/company/general/na\x01me",
	'a bracket'							=> '/project/company/general/na[[me',
	'a bracket that closes'	=> '/project/company/general/na]]me',
	'a space'								=> '/project/company/general/na me',
	'a capital'							=> '/project/Company/general/name',
	'a dot'									=> '/project/company/general/na.me',
	'too few words'					=> '/project/company/name',
	'too many words'				=> '/project/company/general/name/more',
	'too long'							=> '/project/company/general/'. str_repeat( 'a', 200 ),
	'the configuration'			=> '/nino/config/mail/password',
	'the workbench'					=> '/_admin/error/some/thing',
	'the system'						=> '/_nino/webpage/home/name',
	'no root of the text'		=> '/accounts/dev/mail/address',
	'a field of an element'	=> '.title',
	'a slot of the block'		=> '~title',
] as $label => $key )
	if( is_array( \Nino\Blocks::validate( $frameSite, 'footer', $frame( $footer( '[text '. $key. ']' ) ) ) ) === true )
		$refusedKeys[] = $label;

check( 'a key of the wrong form or outside the four roots of the text store is read by no frame: a way up, two slashes, a backslash, a control character, a bracket, a space, a capital, a dot, too few or too many words, too long, the configuration, the workbench, the system, no root of the text'
	. ( $refusedKeys === [] ? '' : ' - taken: '. implode( ', ', $refusedKeys ) ), $refusedKeys === [] );

$notQuite = [];

foreach( [
	'another picture'								=> $footer( '[footer-logo /logo/other]' ),
	'a text as a picture'						=> $footer( '[footer-logo /project/company/general/description]' ),
	'the logo as a text'						=> $footer( '[text /logo]' ),
	'the logo for a component that is no logo'	=> $footer( '[image /logo]' ),
	'a text of the project in an attribute'	=> $footer( '[footer-logo /logo alt="/project/company/general/name"]' ),
	'a text of the project as the address of a link'	=> $footer( '[button ~go href="/project/company/general/name"]' ),
	'html'													=> $footer( '[html]<b>x</b>[/html]' ),
	'a textfill'										=> $footer( '[text ~about]' ). '[[/project/company/general/name]]',
] as $label => $tpl )
	if( is_array( \Nino\Blocks::validate( $frameSite, 'footer', $frame( $tpl ) ) ) === true )
		$notQuite[] = $label;

check( 'what a frame may read is the first word of a call and no more: no other picture, no text as a picture, no logo as a text or for another component, no source in an attribute, no html, no textfill'
	. ( $notQuite === [] ? '' : ' - taken: '. implode( ', ', $notQuite ) ), $notQuite === [] );

$page = static fn( string $inside, string $category = 'content' ): array => $frame( "[section-wrap id=\"probe\"]\n\t[section-row]\n\t\t[section-col]\n\t\t\t". $inside. "\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]", $category );
check( 'a block of the pages reads none of it: a text of the project and the logo are as refused as they were, whatever its category says',
	array_filter( [ $page( '[text /project/company/general/description]' ), $page( '[text /project/company/general/description]', 'frame' ), $page( '[image /logo]' ) ], static fn( array $parts ): bool => is_array( \Nino\Blocks::validate( $frameSite, 'probe', $parts ) ) === true ) === []
	&& is_array( \Nino\Blocks::validate( $frameSite, 'probe', array_merge( $page( '[text ~title]' ), [ 'texts' => [ 'de_DE' => '{"title":"x"}', 'en_US' => '{"title":"x"}' ] ] ) ) ) === true );
check( 'a footer-wrap whose category is not frame is no block, nor is a section-wrap whose category is frame, with a text of the project or without', is_string( \Nino\Blocks::validate( $frameSite, 'footer', $frame( $footer( '[text /project/company/general/description]' ), 'content' ) ) ) === true
	&& is_string( \Nino\Blocks::validate( $frameSite, 'footer', $frame( $footer( '[text /project/company/general/description]' ), 'intro' ) ) ) === true && is_string( \Nino\Blocks::validate( $frameSite, 'footer', $frame( $footer( '[text /project/company/general/description]' ), 'misc' ) ) ) === true );

// Drawn: what a page draws for the call, with the same care, and for a key that is not there as a page does - without a word in the log
\Nino\Html::addFills( $frameSite, [ '/project/company/general/description' => 'Acme <b>Ltd</b> <script>alert(1)</script>' ], '*' );
ninoWarnings();
$drawn = is_array( $good ) === true ? basisLessDraw( $frameSite, $good['source'] ) : '';
$onAPage = basisLessDraw( $frameSite, '[text /project/company/general/description format="inline" size="s"]' );
check( 'a frame draws the text of the site as a page draws the same call, with the same cleaning: the markup of the text that is allowed stays, a script does not stand', str_contains( $drawn, 'Acme' ) === true && str_contains( $drawn, '<script' ) === false && str_contains( $drawn, $onAPage ) === true );

$absent = blocksSite();
ninoWarnings();
$noText = basisLessDraw( $absent, $good['source'] );
$noTextOnAPage = basisLessDraw( $absent, '[text /project/company/general/description format="inline" size="s"]' );
check( 'where the site has no such text - a package of another site - the frame is drawn without it, as a page draws a missing text, and nothing is said in the log, once or for each call', str_contains( $noText, $noTextOnAPage ) === true && str_contains( $noText, 'Acme' ) === false && ninoWarnings() === [] );

// What a frame reads, for a tool to show before the block is put in: the keys of its texts and the logo, each once; a block of the pages reads none
check( 'reads() lists the keys a frame names - texts and logo, sorted, each once - and nothing of the block\'s own slots, and is empty for a block of the pages',
	is_array( $good ) === true && \Nino\Blocks::reads( $good + [ 'tree' => \Nino\Markup::parse( $good['source'], \Nino\Components::containers( $frameSite ) ) ] ) === [ '/logo', '/project/company/general/description' ]
	&& \Nino\Blocks::reads( [ 'wrap' => 'footer-wrap', 'tree' => \Nino\Markup::parse( $footer( '[text /project/a/b/c][text /module/x/y/z][text /project/a/b/c][text ~title][text /nino/placeholder/landscape]' ), \Nino\Components::containers( $frameSite ) ) ] ) === [ '/module/x/y/z', '/project/a/b/c' ]
	&& \Nino\Blocks::reads( [ 'wrap' => 'section-wrap', 'tree' => \Nino\Markup::parse( '[section-wrap id="x"][text /project/a/b/c][/section-wrap]', \Nino\Components::containers( $frameSite ) ) ] ) === [] && \Nino\Blocks::reads( [] ) === [] );

\Nino\Filesystem::removeDir( ninoSandboxDir( $frameSite ) );
\Nino\Filesystem::removeDir( ninoSandboxDir( $absent ) );

\Nino\Filesystem::removeDir( ninoSandboxDir( $looped ) );
\Nino\Filesystem::removeDir( ninoSandboxDir( $linkSite ) );
ninoDone( $appData );
