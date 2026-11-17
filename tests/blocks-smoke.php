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

\Nino\Filesystem::removeDir( ninoSandboxDir( $looped ) );
\Nino\Filesystem::removeDir( ninoSandboxDir( $linkSite ) );
ninoDone( $appData );
