<?php
declare(strict_types=1);

/**
 *	Nino										A compact filesystembased php framework
 *	markup-components-smoke.php	Dependency-free smoke test for \Nino\Components: the register of the
 *													components (schema, validate(), addComponent(), components(), containers(),
 *													allowed(), version()) and the drawing of a tree \Nino\Markup read
 *													(render()). The fourteen cases of the prototype as drawn pages, the rules of
 *													fall-back F1, F2, F3, F7, F8 and F9, the relative key under nested ids, the
 *													places a component may stand in, what validate() turns down and why, the
 *													textfills against the fills of a page, and the values that must never be
 *													read as markup - a text, an attribute, a link, a class - in the shapes an
 *													editor could write them. The components are the suite's own probes.
 *													Runs against an isolated sandbox directory, never touches the real project
 *													data.
 *
 *	Usage: php tests/markup-components-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'markup-components' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

/*	What the site says: the words of two pages in two languages, and a few of the project's own	*/
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static fn( array $content ): array => $content + [
	'[[/template/page-x/hero/title]]'		=> 'Hallo & <Welt> [sic]',
	'[[/template/page-x/hero/subtitle]]'	=> 'Untertitel',
	'[[/template/page-x/a/t]]'					=> 'Text A',
	'[[/template/page-x/b/t]]'					=> 'Text B',
	'[[/template/page-x/start/t]]'			=> 'Start T',
	'[[/template/page-y/a/t]]'					=> 'Text Y',
	'[[/template/page-x/hero/nested]]'		=> 'Von [[/project/company/general/name]]',
	'[[/template/page-x/hero/blocks]]'		=> '<p>Eins</p><p onclick="x()">Zwei <strong>fett</strong></p><script>alert(1)</script>',
	'[[/template/page-x/hero/photo]]'		=> 'ignored',
	'[[/template/page-x/hero/empty]]'		=> '',
	'[[/template/page-x/hero/only-de]]'	=> 'Nur deutsch',
	'[[/template/page-x/hero/link]]'			=> '/kontakt?a=1&amp;b=2',
	'[[/template/page-x/hero/evil]]'			=> 'javascript:alert(1)',
	'[[/project/company/general/name]]'	=> 'ACME',
	'[[/project/company/general/tag]]'	=> 'Tag <b>in</b> "text"',
	'[[/project/company/general/url]]'	=> 'https://example.test/?q=1&amp;r=2',
	'[[/project/company/general/mail]]'	=> 'mailto:a@example.test',
	'[[/project/company/general/self]]'	=> 'Selbst [[/project/company/general/self]]',
	'[[/project/company/general/blank]]'	=> '',
	'[[/project/company/general/macro]]'	=> '[template /templates/x] und [probe-echo]',
	'[[/_nino/webpage/contact/uri]]'		=> '/kontakt',
	'[[/_nino/webpage/contact/title]]'	=> 'Kontakt <Titel>',
	'[[/_nino/webpage/ext/uri]]'				=> 'https://ext.test/x',
	'[[/project/company/general/tokens]]'	=> '[[content]] [[class]]',
	'[[/template/page-x/hero/pagelink]]'	=> 'page:/contact',
	'[[/project/company/general/pageref]]'	=> 'page:/contact',
	'[[/project/company/general/pageanchor]]'	=> 'page:/contact#form',
	'[[/project/company/general/chain0]]'	=> 'a [[/project/company/general/chain1]] b [[/nope/a/b/c]]',
	'[[/project/company/general/chain1]]'	=> '[[/project/company/general/name]]-[[/project/company/general/chain2]]',
	'[[/project/company/general/chain2]]'	=> 'end',
] );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', static fn( array $content ): array => $content + [
	'[[/template/page-x/hero/title]]'		=> 'Hello',
	'[[/_nino/webpage/contact/uri]]'		=> '/contact',
] );
\Nino\Html::addFills( $appData, [ '/nino/http/response/uri' => '/contact' ], '*' );

$appData['/nino/dir'] = '/shop';

/*	The pages of the site: a page in two languages, one in the native language only, one for every language, the pages of a subtree, an endpoint	*/
$appData['/nino/http/routes'] = [
	'GET://kontakt'		=> [ 'uri' => '/contact', 'locale' => 'de_DE' ],
	'GET://contact'		=> [ 'uri' => '/contact', 'locale' => 'en_US' ],
	'GET://only-de'		=> [ 'uri' => '/only', 'locale' => 'de_DE' ],
	'GET://'					=> [ 'uri' => '/home' ],
	'GET://blog/*'		=> [ 'uri' => '/blog' ],
	'POST://send'			=> [ 'uri' => '/send' ],
	'GET://quote'			=> [ 'uri' => '/quote' ],
	'GET://q"x'				=> [ 'uri' => '/quoted' ],
	'GET://odd1'			=> [ 'uri' => '/a/../b' ],
	'GET://odd2'			=> [ 'uri' => '/odd..name' ],
	'GET://odd3'			=> [ 'uri' => '/a b' ],
	'GET://odd4'			=> [ 'uri' => '/contact?x=1' ],
	'GET://odd5'			=> [ 'uri' => '/contact#a' ],
	'GET://odd6'			=> [ 'uri' => '/contact/' ],
	'GET://odd8'			=> [ 'uri' => 'https://x.test' ],
	'GET://odd9'			=> [ 'uri' => '//x.test' ],
	'GET://odd10'			=> [ 'uri' => 'javascript:alert(1)' ],
	'GET://odd11'			=> [ 'uri' => '' ],
	'GET://odd12'			=> [ 'uri' => '/../contact' ],
	'GET://odd13'			=> [ 'uri' => '../contact' ],
	'GET://odd14'			=> [ 'uri' => "/contact\n" ],
	'GET:///evil.test'	=> [ 'uri' => '/evil' ],
	'GET://\\evil.test'	=> [ 'uri' => '/evil2' ],
	'GET://_api/x'		=> [ 'uri' => '/api' ],
	'GET://.hidden'		=> [ 'uri' => '/hidden' ],
	'GET://a b'				=> [ 'uri' => '/spaced' ],
	"GET://tab\there"	=> [ 'uri' => '/tabbed' ],
];


// --- The probes ------------------------------------------------------------

/*	The schema of the prototype: the grid, the card, the titles, the text and the button	*/
$variant = [ 'type' => 'choice', 'options' => [ 'regular' ], 'default' => 'regular' ];
$schemas = [
	'section-wrap' => [ 'label' => 'Wrap', 'in' => [ 'page' ], 'offers' => [ 'section-wrap' ], 'variants' => [ 'band' ],
		'attributes' => [ 'id' => [ 'type' => 'id', 'default' => '' ],
			'ground' => [ 'type' => 'choice', 'options' => [ 'base', 'alt', 'tint', 'dark', 'primary' ], 'default' => 'base' ] ],
		'template' => '<section[[id]] class="[[class]]">[[content]]</section>' ],
	'section-row' => [ 'label' => 'Row', 'in' => [ 'section-wrap' ], 'offers' => [ 'section-row' ],
		'attributes' => [ 'gap' => [ 'type' => 'choice', 'options' => [ '0', '1', '2', '3', '4' ], 'default' => '2' ],
			'width' => [ 'type' => 'choice', 'options' => [ 'regular', 'narrow', 'wide' ], 'default' => 'regular' ] ],
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

/*	What a renderer is handed, for the probes that have one	*/
$seen = new \ArrayObject();

$knob = [
	'mode'		=> [ 'type' => 'choice', 'options' => [ 'a', 'b', 'c' ], 'default' => 'a' ],
	'size'		=> [ 'type' => 'choice', 'options' => [ '25', '50', '100' ], 'default' => '100', 'responsive' => true ],
	'tag'			=> [ 'type' => 'choice', 'options' => [ 'p', 'h2' ], 'default' => 'p', 'class' => false ],
	'on'			=> [ 'type' => 'bool', 'default' => false ],
	'quiet'		=> [ 'type' => 'bool', 'default' => false ],
	'flag'		=> [ 'type' => 'bool', 'default' => false, 'class' => false ],
	'count'		=> [ 'type' => 'int', 'default' => 3, 'min' => 1, 'max' => 9 ],
	'label'		=> [ 'type' => 'string', 'default' => 'L' ],
	'href'		=> [ 'type' => 'link', 'default' => '' ],
	'pic'			=> [ 'type' => 'image', 'default' => '' ],
	'anchor'	=> [ 'type' => 'id', 'default' => '' ],
];

$probes = [
	'knob' => [ 'label' => 'Knob', 'offers' => [], 'variants' => [ 'soft' ], 'attributes' => $knob,
		'template' => '<i class="[[class]]"[[id]][[lang]] t="[[tag]]" f="[[flag]]" n="[[count]]" l="[[label]]" h="[[href]]" p="[[pic]]" a="[[anchor]]">[[value]][[content]]</i>' ],
	'titled' => [ 'label' => 'Titled', 'offers' => [], 'source' => 'text', 'attributes' => [ 'note' => [ 'type' => 'string', 'default' => 'a "b" <c> &' ] ], 'template' => '<h title="[[note]]">[[value]]|[[content]]</h>' ],
	'anchored' => [ 'label' => 'Anchored', 'offers' => [], 'attributes' => [ 'anchor' => [ 'type' => 'id', 'default' => 'start' ] ], 'template' => '<a[[id]]>[[content]]</a>' ],
	'linked' => [ 'label' => 'Linked', 'attributes' => [ 'href' => [ 'type' => 'link', 'default' => '/start' ], 'src' => [ 'type' => 'image', 'default' => '/default-image' ] ], 'template' => '<a href="[[href]]" data-src="[[src]]"></a>' ],
	'rows' => [ 'label' => 'Rows', 'attributes' => [ 'align' => [ 'type' => 'choice', 'options' => [ '', 'left', 'right' ], 'default' => '', 'responsive' => true ] ], 'template' => '<r class="[[class]]"></r>' ],
	'tabs' => [ 'label' => 'Tabs', 'offers' => [], 'template' => '<tabs>[[content]]</tabs>' ],
	'tab' => [ 'label' => 'Tab', 'in' => [ 'tabs' ], 'template' => '<tab></tab>' ],
	'badge' => [ 'label' => 'Badge', 'in' => [ 'button' ], 'template' => '<badge></badge>' ],
	'go' => [ 'label' => 'Go', 'source' => 'link', 'template' => '<a href="[[value]]">go</a>' ],
	'pic' => [ 'label' => 'Pic', 'source' => 'image', 'attributes' => [ 'alt' => [ 'type' => 'string', 'default' => '' ] ], 'template' => '<img src="[[value]]" alt="[[alt]]">' ],
	'rich' => [ 'label' => 'Rich', 'source' => 'text', 'format' => 'blocks', 'template' => '<div>[[value]]</div>' ],
	'inline' => [ 'label' => 'Inline', 'source' => 'text', 'format' => 'inline', 'template' => '<span>[[value]]</span>' ],
	'slotted' => [ 'label' => 'Slotted', 'offers' => [], 'template' => '<div>[[content:card-title]]<i>[[content]]</i><b>[[content:text]]</b>[[content:none-here]]</div>' ],
	'lamp' => [ 'label' => 'Lamp', 'attributes' => [ 'lit' => [ 'type' => 'bool', 'default' => true ] ], 'template' => '<lamp class="[[class]]"></lamp>' ],
	'free' => [ 'label' => 'Free', 'offers' => [], 'template' => '[[class]]|[[content]]|[[value]]|[[id]]|[[lang]]' ],
	'optioned' => [ 'label' => 'Optioned', 'attributes' => [ 'as' => [ 'type' => 'choice', 'options' => '\Nino\Format::options', 'default' => '', 'class' => false ] ], 'template' => '<u data-as="[[as]]"></u>' ],
	'sidebar-box' => [ 'label' => 'Side', 'offers' => [ 'sidebar' ], 'in' => [ 'page' ], 'template' => '<aside>[[content]]</aside>' ],
	'widget' => [ 'label' => 'Widget', 'in' => [ 'sidebar' ], 'template' => '<w></w>' ],
	'wild' => [ 'label' => 'Wild', 'attributes' => [ 'x' => [ 'type' => 'choice', 'options' => [ 'a"><script>alert(1)</script>', 'ok' ], 'default' => 'ok', 'class' => false ] ], 'template' => '<u data-x="[[x]]"></u>' ],
	'scroll' => [ 'label' => 'Scroll', 'offers' => [], 'template' => '<a aria-label="[[/project/company/general/name]]" data-n="[[/project/company/general/nobody]]" title="[[/project/company/general/tokens]]" data-m="[[/project/company/general/macro]]" data-t="[[/project/company/general/tag]]">[[content]]</a>' ],
	'header-logo' => [ 'label' => 'Logo', 'in' => [ 'flow' ], 'once' => true, 'template' => '<logo></logo>' ],
];

foreach( $probes as $name => $schema )
	\Nino\Components::addComponent( $appData, $name, $schema );

$echo = static function( array &$appData, array $args, string $content, array $context ) use ( $seen ): string {
	$seen[] = [ $args, $content, $context ];
	return '<echo>'. $content. '</echo>';
};

/*	The probes that have a renderer: name => [ schema, renderer ]	*/
$renderers = [
	'probe-echo' => [ [ 'label' => 'Echo', 'offers' => [], 'source' => 'text', 'variants' => [ 'soft' ], 'attributes' => $knob ], $echo ],
	'boom' => [ [ 'label' => 'Boom', 'offers' => [] ], static function( array &$appData, array $args, string $content, array $context ): string {
		throw new \RuntimeException( 'broken'. "\n". str_repeat( 'x', 500 ) );
	} ],
	'nothing' => [ [ 'label' => 'Nothing' ], static function( array &$appData, array $args, string $content, array $context ) {
		return null;
	} ],
	'listing' => [ [ 'label' => 'Listing' ], static function( array &$appData, array $args, string $content, array $context ): array {
		return [ 'not', 'a string' ];
	} ],
	'number' => [ [ 'label' => 'Number' ], static function( array &$appData, array $args, string $content, array $context ): int {
		return 42;
	} ],
	'raw' => [ [ 'label' => 'Raw' ], static function( array &$appData, array $args, string $content, array $context ): string {
		return '[template /templates/x] [[/project/company/general/name]] [card]';
	} ],
	'wild-echo' => [ [ 'label' => 'Wild echo', 'attributes' => [ 'x' => [ 'type' => 'choice', 'options' => [ 'a"><script>alert(1)</script>', 'ok' ], 'default' => 'ok', 'class' => false ] ] ], $echo ],
	'anon' => [ [ 'label' => 'Anon' ], static function( array &$appData, array $args, string $content, array $context ): string {
		throw new class( 'anonymous' ) extends \RuntimeException {};
	} ],
	'crash' => [ [ 'label' => 'Crash' ], static function( array &$appData, array $args, string $content, array $context ): string {
		return str_repeat( 'x', -1 );
	} ],
	'filler' => [ [ 'label' => 'Filler' ], static function( array &$appData, array $args, string $content, array $context ): string {
		\Nino\Html::addFills( $appData, [ '/project/company/general/late' => 'Spät' ] );
		return '';
	} ],
];

foreach( $renderers as $name => [ $schema, $renderer ] )
	\Nino\Components::addComponent( $appData, $name, $schema, $renderer );

$page = [ 'template' => 'page-x' ];

/*	The errors of one call, but for the deprecation php 8.4 adds to every E_USER_ERROR: not what is asked about here	*/
$errorsOf = static fn( callable $run ): array => array_values( array_filter( ninoErrors( $run ), static fn( array $error ): bool => $error[0] !== E_DEPRECATED ) );

/*	A source read and drawn: the document, the context it is drawn in; the notes of the read are the third argument	*/
$draw = static function( string $source, array $context = [ 'template' => 'page-x' ], ?array &$notes = null ) use ( &$appData ): string {
	$notes = [];
	return \Nino\Components::render( $appData, \Nino\Markup::parse( $source, \Nino\Components::containers( $appData ), $notes ), $context );
};

/*	The log of one draw: the levels and the messages it raised	*/
$logged = static function( callable $run ) use ( &$appData ): array {
	unset( $appData[\Nino\Components::NOTICED] );
	ninoWarnings();
	$run();
	return ninoWarnings();
};

$element = [
	'uri'			=> '/post',
	'id'			=> 3,
	'element'	=> [ '.uri' => '/post/hello-world', '.lang' => 'de_DE', 'title' => 'T & <b>x</b> [sic]', 'body' => '<p>Hi <em>there</em></p><script>x()</script>', 'photo' => 'a.jpg', 'cover' => '', 'bad' => 'javascript:alert(1)', 'target' => '/blog/x?a=1&b=2', 'empty' => '', 'list' => [ 'a' ] ],
	'model'		=> [ 'title' => [ 'type' => 'string' ], 'body' => [ 'type' => 'string', 'html' => true, 'blocks' => true ], 'photo' => [ 'type' => 'image' ], 'cover' => [ 'type' => 'image' ], 'bad' => [ 'type' => 'string' ], 'target' => [ 'type' => 'string' ], 'empty' => [ 'type' => 'string' ], 'list' => [ 'type' => 'string' ] ],
];


// --- The register ------------------------------------------------------------

echo "The register - addComponent(), components(), containers(), version()\n";

$registered = \Nino\Components::components( $appData );
check( 'components() lists the names in the order they were registered', array_slice( array_keys( $registered ), 0, 10 ) === array_keys( $schemas ) && array_keys( $registered ) === array_merge( array_keys( $schemas ), array_keys( $probes ), array_keys( $renderers ) ) );

$keysOk = true;
foreach( $registered as $name => $clean )
	foreach( [ 'label', 'in', 'once', 'source', 'attributes', 'variants', 'template', 'preview' ] as $key )
		$keysOk = $keysOk && array_key_exists( $key, $clean );
check( 'a clean schema has every key but the optional ones: label, in, once, source, attributes, variants, template and preview', $keysOk === true );
check( 'the optional ones are there only where the schema said so: offers for a container, format for a text, group and icon when given', array_key_exists( 'offers', $registered['card'] ) === true && array_key_exists( 'offers', $registered['text'] ) === false
	&& $registered['text']['format'] === 'plain' && $registered['rich']['format'] === 'blocks' && array_key_exists( 'format', $registered['card'] ) === false
	&& array_key_exists( 'group', $registered['card'] ) === false && array_key_exists( 'icon', $registered['card'] ) === false );
check( 'the defaults: no place, once off, no source, the preview block, the label of an attribute its name, and every choice has its class and its responsive flag',
	$registered['card']['in'] === [ 'flow' ] && $registered['free']['in'] === [] && $registered['free']['once'] === false && $registered['free']['source'] === 'none' && $registered['free']['preview'] === 'block'
	&& $registered['card']['attributes']['fill']['label'] === 'fill' && $registered['card']['attributes']['fill']['class'] === true && $registered['section-title']['attributes']['tag']['class'] === false
	&& $registered['section-col']['attributes']['size']['responsive'] === true && $registered['card']['attributes']['padding']['responsive'] === false && array_key_exists( 'responsive', $registered['card']['attributes']['fill'] ) === false
	&& $registered['knob']['attributes']['count']['class'] === false && $registered['knob']['attributes']['label']['class'] === false && $registered['knob']['attributes']['anchor']['class'] === false );
check( 'regular is always a variant and stands first, once', $registered['card']['variants'] === [ 'regular' ] && $registered['button']['variants'] === [ 'regular', 'primary', 'outline' ]
	&& \Nino\Components::validate( 'v', [ 'label' => 'V', 'variants' => [ 'x', 'regular', 'x', 'y' ] ] )['variants'] === [ 'regular', 'x', 'y' ] );
check( 'a clean schema is a schema validate() accepts again, as it is - the same, key for key', array_filter( (array) $appData[\Nino\Components::REGISTRY], static fn( array $clean, string $name ): bool => \Nino\Components::validate( $name, $clean ) !== $clean, ARRAY_FILTER_USE_BOTH ) === [] );
check( 'once is in the schema: the card title and the logo are once in a container, the others are not', $registered['card-title']['once'] === true && $registered['header-logo']['once'] === true && $registered['card']['once'] === false );

$containers = \Nino\Components::containers( $appData );
check( 'containers() says true for a name with offers - also an empty list - and false for the rest', $containers['section-wrap'] === true && $containers['card'] === true && $containers['knob'] === true && $containers['free'] === true
	&& $containers['text'] === false && $containers['button'] === false && $containers['go'] === false && array_keys( $containers ) === array_keys( $registered ) );
$notes = [];
$tree = \Nino\Markup::parse( '[card][text ~a][/text][/card][knob]x', $containers, $notes );
check( 'the parser takes the list: a leaf has no end - its [/text] is a stray end - and a container without one is closed', array_column( $notes, 'code' ) === [ 'stray-end', 'unclosed' ] && $tree[0]['c'][0]['c'] === null && $tree[1]['c'] !== null );

$version = \Nino\Components::version( $appData );
check( 'version() is the sha1 of the schemas: the same again and again', preg_match( '/^[0-9a-f]{40}$/D', $version ) === 1 && \Nino\Components::version( $appData ) === $version
	&& $version === sha1( serialize( (array) $appData[\Nino\Components::REGISTRY] ) ) );

$other = ninoSandbox( 'markup-components-other' );
foreach( $schemas + $probes as $name => $schema )
	\Nino\Components::addComponent( $other, $name, $schema );
foreach( $renderers as $name => [ $schema, $renderer ] )
	\Nino\Components::addComponent( $other, $name, $schema, static fn( array &$appData, array $args, string $content, array $context ): string => 'other' );
check( 'it is the same in another request that registered the same schemas', \Nino\Components::version( $other ) === $version );

\Nino\Components::addComponent( $other, 'extra', [ 'label' => 'Extra', 'template' => '<x></x>' ] );
$versionExtra = \Nino\Components::version( $other );
check( 'a component that comes changes it', $versionExtra !== $version );
$changed = $schemas['card'];
$changed['attributes']['padding']['default'] = '3';
\Nino\Components::addComponent( $other, 'card', $changed );
check( 'so does a schema that changes - one default - and the one before is not the one it was', \Nino\Components::version( $other ) !== $versionExtra && \Nino\Components::version( $other ) !== $version );
\Nino\Components::addComponent( $other, 'card', $schemas['card'] );
check( 'and the schema it was gives the version it was', \Nino\Components::version( $other ) === $versionExtra );
check( 'a renderer is no part of it: the schemas are', ( static function() use ( &$other, $versionExtra ): bool {
	\Nino\Components::addComponent( $other, 'raw', [ 'label' => 'Raw' ], static fn( array &$appData, array $args, string $content, array $context ): string => 'other' );
	return \Nino\Components::version( $other ) === $versionExtra;
} )() );

$countBefore = count( \Nino\Components::components( $other ) );
$order = array_keys( \Nino\Components::components( $other ) );
$errors = $errorsOf( static function() use ( &$other ): void {
	\Nino\Components::addComponent( $other, 'card', [ 'label' => 'Card', 'in' => 'flow', 'template' => '<x></x>' ] );
} );
check( 'a schema that is none is an E_USER_ERROR that names the component and the key - what the register of the modules does - and nothing is registered or changed',
	count( $errors ) === 1 && $errors[0][0] === E_USER_ERROR && str_contains( $errors[0][1], 'Components::addComponent()' ) === true && str_contains( $errors[0][1], '"card"' ) === true && str_contains( $errors[0][1], '"in"' ) === true
	&& \Nino\Components::components( $other )['card'] === $registered['card'] && \Nino\Components::version( $other ) === $versionExtra );
$errors = $errorsOf( static function() use ( &$other ): void {
	\Nino\Components::addComponent( $other, 'fresh', [ 'label' => 'Fresh', 'in' => [ 'Bad Place' ] ] );
	\Nino\Components::addComponent( $other, 'fresh', [ 'label' => 'Fresh' ] );
} );
check( 'a name that is registered by a schema that is none is not registered; a component with neither a template nor a renderer is refused as well',
	count( $errors ) === 2 && $errors[0][0] === E_USER_ERROR && $errors[1][0] === E_USER_ERROR && str_contains( $errors[1][1], '"template"' ) === true
	&& isset( \Nino\Components::components( $other )['fresh'] ) === false && count( \Nino\Components::components( $other ) ) === $countBefore );

\Nino\Components::addComponent( $other, 'twice', [ 'label' => 'First', 'in' => [ 'flow' ], 'template' => '<one></one>' ] );
\Nino\Components::addComponent( $other, 'after', [ 'label' => 'After', 'template' => '<a></a>' ] );
$said = $logged( static function() use ( &$other ): void {
	\Nino\Components::addComponent( $other, 'twice', [ 'label' => 'Second', 'in' => [ 'card' ], 'template' => '<two></two>' ], static fn( array &$appData, array $args, string $content, array $context ): string => '<renderer>' );
} );
$names = array_keys( \Nino\Components::components( $other ) );
check( 'a name that is registered twice is the second: its schema and its renderer, at the place of the first, said nowhere',
	$said === [] && \Nino\Components::components( $other )['twice']['label'] === 'Second' && \Nino\Components::components( $other )['twice']['in'] === [ 'card' ]
	&& array_search( 'twice', $names, true ) === array_search( 'after', $names, true ) - 1 && count( array_keys( $names, 'twice', true ) ) === 1
	&& \Nino\Components::render( $other, [ [ 'n' => 'twice', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ] ] ) === '<renderer>' );
\Nino\Components::addComponent( $other, 'twice', [ 'label' => 'Third', 'template' => '<three></three>' ] );
check( '...and a third registration without a renderer draws the template again', \Nino\Components::render( $other, [ [ 'n' => 'twice', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ] ] ) === '<three></three>' );

echo "\n";


// --- The places - allowed() and once -----------------------------------------

echo "The places a component may stand in\n";

check( 'allowed( page ) are the components that say page, and the empty string is the page as well', \Nino\Components::allowed( $appData, 'page' ) === [ 'section-wrap', 'sidebar-box' ] && \Nino\Components::allowed( $appData, '' ) === \Nino\Components::allowed( $appData, 'page' ) );
check( 'a container offers its own name: a section row stands in a wrap, a column in a row, and the wrap in a wrap is no place of it', \Nino\Components::allowed( $appData, 'section-wrap' ) === [ 'section-row' ] && \Nino\Components::allowed( $appData, 'section-row' ) === [ 'section-col' ] );
check( 'a container that offers flow takes what is in flow and what is in its own name, in the order they were registered',
	\Nino\Components::allowed( $appData, 'section-col' ) === [ 'section-title', 'section-subtitle', 'card', 'text', 'button', 'header-logo' ] );
check( 'a card offers itself: what says card, and no flow', \Nino\Components::allowed( $appData, 'card' ) === [ 'card-title', 'card-text', 'text', 'button' ] );
check( 'the place of the kernel flow is asked for as the place it is', \Nino\Components::allowed( $appData, 'flow' ) === [ 'card', 'text', 'button', 'header-logo' ] );
check( 'a place that only a feature opened is one: asked for directly and by the container that offers it', \Nino\Components::allowed( $appData, 'sidebar' ) === [ 'widget' ] && \Nino\Components::allowed( $appData, 'sidebar-box' ) === [ 'widget' ] && \Nino\Components::allowed( $appData, 'nobody' ) === [] );
check( 'a container offers its own name and no more: a tab stands in tabs, which offers nothing else', \Nino\Components::allowed( $appData, 'tabs' ) === [ 'tab' ] );
check( 'a leaf offers nothing, whatever places say its name - a badge says it stands in a button', \Nino\Components::allowed( $appData, 'button' ) === [] && \Nino\Components::allowed( $appData, 'card-title' ) === [] && \Nino\Components::components( $appData )['badge']['in'] === [ 'button' ] );
check( 'a component with no place stands nowhere, and is drawn where it is written all the same', !in_array( 'free', \Nino\Components::allowed( $appData, 'page' ), true ) && !in_array( 'free', \Nino\Components::allowed( $appData, 'flow' ), true )
	&& $draw( '[card-title text="x"]' ) === '<h3 class="card-title">x</h3>' );

echo "\n";


// --- The fourteen cases of the prototype ------------------------------------

echo "The fourteen cases of the prototype, drawn\n";

$out = $draw( '[section-col size="100"]a[section-col size="50"]b[/section-col]c[/section-col]' );
check( 'components of one name nest', $out === '<div class="section-col">a<div class="section-col section-col--size-50">b</div>c</div>' );

$out = $draw( '[button text="a ] b \"c\" \\\\ d" href="/x?a=1&b=2"]' );
check( 'a ] and a masked quote in an attribute value; the address is escaped once', $out === '<a class="button" href="/x?a=1&amp;b=2">a ] b &quot;c&quot; \\ d</a>' );

$out = $draw( '[section-col size="100 m:50 l:33" align="center"][/section-col]' );
check( 'a responsive value: the first for every width, m: from 768, l: from 1024', $out === '<div class="section-col section-col--m-size-50 section-col--l-size-33 section-col--align-center"></div>' );

$out = $draw( '[section-col size="100 x:50 m:77"][/section-col]' );
check( 'an unknown breakpoint and a value outside the options fall away', $out === '<div class="section-col"></div>' );

$out = $draw( '[card fill][/card][card fill="no"][/card]' );
check( 'a bool as a bare word and as fill="no"', $out === '<article class="card card--fill"></article><article class="card"></article>' );

$out = $draw( '[section-wrap id="hero"][section-row][section-col][section-title ~title][/section-col][/section-row][/section-wrap]' );
check( 'the relative key ~title reads the key of the page and the id above it; the text is escaped and not read again', $out === '<section id="hero" class="section-wrap"><div class="section-row"><div class="section-col"><h2 class="section-title">Hallo &amp; &lt;Welt&gt; [sic]</h2></div></div></section>' );

$out = $draw( '[text /project/company/general/name]' );
check( 'a key with its four segments', $out === '<p class="text">ACME</p>' );

$said = $logged( static function() use ( $draw, &$out ): void {
	$out = $draw( 'a [gallery album="x"] b [slider]c[/slider] d' );
} );
check( 'an unknown component draws nothing, with an end or without - and says so once for each name', $out === 'a  b  d' && count( $said ) === 2 && str_contains( $said[0], 'gallery' ) === true && str_contains( $said[1], 'slider' ) === true );

$out = $draw( '[section-wrap variant="band"][/section-wrap][section-wrap variant="poster"][/section-wrap]' );
check( 'a variant the component knows, and one it does not (the standard)', $out === '<section class="section-wrap section-wrap--variant-band"></section><section class="section-wrap"></section>' );

$out = $draw( '[card][text text="a"]', $page, $notes );
check( 'a start without an end is closed at the end of the document and noted', $out === '<article class="card"><p class="text">a</p></article>' && array_column( $notes, 'code' ) === [ 'unclosed' ] && $notes[0]['name'] === 'card' );

$out = $draw( 'a[/card]b', $page, $notes );
check( 'an end without a start is skipped and noted', $out === 'ab' && array_column( $notes, 'code' ) === [ 'stray-end' ] );

$out = $draw( '[card][section-col]x[/card]y', $page, $notes );
check( 'an end that closes what is inside it closes that too', $out === '<article class="card"><div class="section-col">x</div></article>y' && array_column( $notes, 'name' ) === [ 'section-col' ] );

$out = $draw( 'Preis \[netto] und [text text="ok"]' );
check( 'a \[ is the text [ in front of what would be a mark', $out === 'Preis [netto] und <p class="text">ok</p>' );

$out = $draw( '[button text="x" href="/" farbe="rot" variant="gibtsnicht"]' );
check( 'a foreign attribute and a value outside the options', $out === '<a class="button" href="/">x</a>' );

echo "\n";


// --- Fall-back F1, F2, F3, F7, F8, F9 ---------------------------------------

echo "The rules of fall-back\n";

$said = $logged( static function() use ( $draw, &$out ): void {
	$out = $draw( '[gallery][card]x[/card][/gallery]y[gallery]' );
} );
check( 'F1: what is inside an unknown component is not drawn, a component in it is not, and the log has one line for the name', $out === 'y' && count( $said ) === 1 && str_contains( $said[0], 'gallery' ) === true );
$said = $logged( static function() use ( $draw ): void {
	$draw( '[gallery][/gallery]' );
	$draw( '[gallery]' );
} );
check( 'F1: ...once in a request, however many documents', count( $said ) === 1 );
$said = $logged( static function() use ( $draw ): void {
	$draw( '[gallery][slider][/slider][other]' );
} );
check( 'F1: one line for each name', count( $said ) === 3 );
check( 'F1: the line is a notice, the level the kernel logs and goes on from', ( static function() use ( &$appData, $draw, $errorsOf ): bool {
	unset( $appData[\Nino\Components::NOTICED] );
	$errors = $errorsOf( static function() use ( $draw ): void { $draw( '[gallery]' ); } );
	return count( $errors ) === 1 && $errors[0][0] === E_USER_NOTICE;
} )() );

\Nino\Callbacks::registerCallback( $appData, \Nino\Components::VARIANTS, static function( array &$appData, mixed &$args ) use ( $seen ): ?array {
	$seen[] = $args;
	if( $args['name'] === 'card' )
		return [ 'name' => 'card', 'variants' => array_merge( $args['variants'], [ 'poster' ] ), 'standard' => 'poster' ];
	if( $args['name'] === 'text' )
		return [ 'name' => 'text', 'variants' => [ 'regular', 'Bad Word' ], 'standard' => 'regular' ];
	if( $args['name'] === 'card-text' )
		return [ 'name' => 'card-text', 'variants' => [ 'regular', 'soft' ], 'standard' => 'missing' ];
	if( $args['name'] === 'card-title' )
		return [ 'name' => 'card-title', 'variants' => [ 'soft' ], 'standard' => 'soft' ];
	if( $args['name'] === 'button' )
		return [ 'name' => 'button', 'variants' => 'regular', 'standard' => 'regular' ];
	if( $args['name'] === 'section-subtitle' )
		return [ 'name' => 'section-subtitle', 'variants' => [ 'regular' => 'regular', 'x' => 'x' ], 'standard' => 'regular' ];
	if( $args['name'] === 'section-row' )
		return [ 'name' => 'section-row', 'variants' => [ 'regular', 'a', 'a' ], 'standard' => 'a' ];
	return null;
} );
$seen->exchangeArray( [] );
$out = $draw( '[card][/card][card variant="regular"][/card][card variant="poster"][/card][card variant="nope"][/card]' );
check( 'F2/V2: the callback adds a variant and a standard; a call that names none, one that names an unknown one and the standard itself have it, regular is a class of none',
	$out === '<article class="card card--variant-poster"></article><article class="card"></article><article class="card card--variant-poster"></article><article class="card card--variant-poster"></article>' );
check( 'F2: ...the callback is given the name, the variants of the schema and regular as the standard, once for the name in a draw', count( $seen ) === 1 && $seen[0] === [ 'name' => 'card', 'variants' => [ 'regular' ], 'standard' => 'regular' ] );
$out = $draw( '[section-wrap variant="band"][/section-wrap][section-wrap variant="poster"][/section-wrap]' );
check( 'F2: a component the callback leaves alone has the variants of its schema - one the schema names is a class, one it does not is regular', $out === '<section class="section-wrap section-wrap--variant-band"></section><section class="section-wrap"></section>' );
$said = $logged( static function() use ( $draw, &$out ): void {
	$out = $draw( '[text text="a"][card][card-text text="b" variant="soft"][/card][button text="c" variant="primary"]' );
} );
check( 'F2: an answer that is no list of words with regular and a standard in it leaves the schema, and says so: a bad word, a standard that is not there, a list that is none',
	$out === '<p class="text">a</p><article class="card card--variant-poster"><div class="card-text">b</div></article><a class="button button--variant-primary" href="">c</a>' && count( $said ) === 3 );
$out = $draw( '[card-title text="t" variant="soft"][/card-title]' );
check( 'F2: ...and one that has no regular is none', $out === '<h3 class="card-title">t</h3>' );
$out = $draw( '[section-subtitle text="s" variant="x"][section-row variant="a"][/section-row]' );
check( 'F2: ...and a map of words and a list with a word twice are no lists of words', $out === '<p class="section-subtitle">s</p><div class="section-row"></div>' );
unset( $appData['./nino/callbacks'][\Nino\Components::VARIANTS] );
$out = $draw( '[card variant="poster"][/card]' );
check( 'F2/V3: no callback, no variant of it - the call keeps what it wrote in the document, and it is drawn without it', $out === '<article class="card"></article>' );

$out = $draw( '[section-col size="77" align="up" farbe="rot" class="x" id="y" lang="fr" value="v"][/section-col]' );
check( 'F3: a value outside the options is the default, a foreign attribute falls away - class, id, lang and value among them', $out === '<div class="section-col"></div>' );
$out = $draw( '[card fill="maybe" padding="9"][/card][card fill="TRUE"][/card][card fill="Off"][/card][card fill="1"][/card][card fill=""][/card]' );
check( 'F3: a value is an option as the characters are: 0.0, 00, 1e0, a blank in front and a plus are not the options 0 and 1', $draw( '[card padding="0.0"][/card][card padding="00"][/card][card padding="1e0"][/card][card padding=" 1"][/card][card padding="+1"][/card][card padding="1 "][/card]' ) === str_repeat( '<article class="card"></article>', 6 ) );
check( 'F3: a bool reads its words without regard to case, and what is none of them is the default', $out === '<article class="card"></article><article class="card card--fill"></article><article class="card"></article><article class="card card--fill"></article><article class="card"></article>' );
check( 'F3: a bare word is true for a bool and the default for any other type', $draw( '[card padding ground fill][/card]' ) === '<article class="card card--fill"></article>' );

$out = $draw( '[button text="x"]' ) . $draw( '[section-title text="y"][/section-title]' );
check( 'F7: a component is drawn where it stands whether the place fits or not: a button in the page, a title in nothing', $out === '<a class="button" href="">x</a><h2 class="section-title">y</h2>' );
check( 'F7: ...a component inside a leaf-like place and in a container that does not offer its place as well', $draw( '[card][section-row][card-title text="t"][/section-row][/card]' ) === '<article class="card"><div class="section-row"><h3 class="card-title">t</h3></div></article>' );

$appData['./nino/locales/current'] = 'en_US';
unset( $appData['./nino/html/filecache'] );
$out = $draw( '[section-wrap id="hero"][section-title ~title][section-subtitle ~subtitle][section-title ~empty][section-title ~nothing][section-subtitle ~only-de][/section-wrap]' );
check( 'F8: a text the language of the page has is its own; one it has not is the text of the native language with the language it is in; one nobody has - or one that is empty - is not drawn at all',
	$out === '<section id="hero" class="section-wrap"><h2 class="section-title">Hello</h2><p class="section-subtitle">Untertitel</p><p class="section-subtitle">Nur deutsch</p></section>' );
$out = $draw( '[section-wrap id="hero"][section-title ~subtitle][/section-wrap]' );
check( 'F8: ...the lang attribute is [[lang]] of the template: lang="de-DE", with a space in front', $out === '<section id="hero" class="section-wrap"><h2 class="section-title" lang="de-DE">Untertitel</h2></section>' );
check( 'F8: a text of the language of the page has no lang, a fixed text has none, and a field of an element has none', $draw( '[section-wrap id="hero"][section-title ~title][/section-wrap]' ) === '<section id="hero" class="section-wrap"><h2 class="section-title">Hello</h2></section>'
	&& $draw( '[section-title text="Fest"]' ) === '<h2 class="section-title">Fest</h2>'
	&& $draw( '[section-title .title]', [ 'template' => 'page-x', 'element' => $element ] ) === '<h2 class="section-title">T &amp; &lt;b&gt;x&lt;/b&gt; [sic]</h2>' );
$appData['./nino/locales/current'] = 'de_DE';
unset( $appData['./nino/html/filecache'] );
check( 'F8: in the native language the same key has no lang, and a key of an empty text is no text', $draw( '[section-wrap id="hero"][section-title ~title][section-title ~empty][/section-wrap]' ) === '<section id="hero" class="section-wrap"><h2 class="section-title">Hallo &amp; &lt;Welt&gt; [sic]</h2></section>' );

$out = $draw( '[card]' . "\n" . '[card-title text="a"]' . "\n" . '[/card]' . "\n" . '[/card] [/nothing] [/card]' . "\n" . '[section-row]', $page, $notes );
check( 'F9: a start without an end, an end without a start and a stray end after a close - the page is whole, and every one is noted at its place in the document',
	array_column( $notes, 'code' ) === [ 'stray-end', 'stray-end', 'stray-end', 'unclosed' ] && str_contains( $out, '<h3 class="card-title">a</h3>' ) === true && substr_count( $out, '<article' ) === 1 && substr_count( $out, '<div class="section-row"></div>' ) === 1
	&& $notes[0]['offset'] < $notes[1]['offset'] && $notes[1]['offset'] < $notes[2]['offset'] && $notes[2]['offset'] < $notes[3]['offset'] );
$source = '[card][section-col][section-row][text text="in"]';
$out = $draw( $source, $page, $notes );
check( 'F9: three open at the end are closed from the inside out and all three drawn', $out === '<article class="card"><div class="section-col"><div class="section-row"><p class="text">in</p></div></div></article>' && array_column( $notes, 'code' ) === [ 'unclosed', 'unclosed', 'unclosed' ] );

echo "\n";


// --- The relative key ----------------------------------------------------------

echo "The relative key ~name\n";

$out = $draw( '[section-wrap id="a"][section-title ~t][section-wrap id="b"][section-title ~t][/section-wrap][section-title ~t][/section-wrap]' );
check( 'it reads the id of the nearest ancestor that has one, and the id above the inner one again after it', $out === '<section id="a" class="section-wrap"><h2 class="section-title">Text A</h2><section id="b" class="section-wrap"><h2 class="section-title">Text B</h2></section><h2 class="section-title">Text A</h2></section>' );
$out = $draw( '[section-wrap id="a"][section-row][section-col][card][card-title ~t][/card][/section-col][/section-row][/section-wrap]' );
check( 'it is the id of an ancestor however many levels up, and a component without an id is none', str_contains( $out, '<h3 class="card-title">Text A</h3>' ) === true );
$out = $draw( '[section-wrap id="a"][section-title ~t][/section-wrap]', [ 'template' => 'page-y' ] );
check( 'the template is the one the context names: another template, the words of that one', $out === '<section id="a" class="section-wrap"><h2 class="section-title">Text Y</h2></section>' );
$out = $draw( '[section-wrap id="a"][section-title ~t][/section-wrap]', [ 'template' => 'page-x', 'scope' => 'b' ] );
check( 'a scope the context brings is the id above the first node: the scope of a template that is included inside an id', $out === '<section id="a" class="section-wrap"><h2 class="section-title">Text A</h2></section>'
	&& $draw( '[section-title ~t]', [ 'template' => 'page-x', 'scope' => 'b' ] ) === '<h2 class="section-title">Text B</h2>' );
$said = $logged( static function() use ( $draw, &$out ): void {
	$out = $draw( '[section-title ~t][section-title ~t][section-title ~other]' );
} );
check( 'without an id above it there is no key: nothing is drawn, and the log has a line - once for the place and not for each call', $out === '' && count( $said ) === 2 && str_contains( $said[0], '~t' ) === true );
$said = $logged( static function() use ( $draw, &$out ): void {
	$out = $draw( '[section-wrap id="a"][section-title ~t][/section-wrap]', [] );
} );
check( 'without a template there is none either: the context with nothing in it names none', $out === '<section id="a" class="section-wrap"></section>' && count( $said ) === 1 );
check( 'a key written in full is no concern of the scope: it reads without an id and without a template', $draw( '[text /project/company/general/name]', [] ) === '<p class="text">ACME</p>' );

echo "\n";


// --- The source ------------------------------------------------------------------

echo "The source of a value\n";

check( 'a fixed text is read by a component of a text only: a link and an image take the text of a key, and a field that cannot be one is no field', $draw( '[go text="/x"][pic text="/y"][go .1x][pic .1x]' ) === '' );
check( 'a fixed text is the value, and it wins over the source; it is read as a plain text is - an entity is its character - and escaped once', $draw( '[text /project/company/general/name text="A &amp; B <i>"]' ) === '<p class="text">A &amp; B &lt;i&gt;</p>' );
check( 'a bare text is no text, and the source stands', $draw( '[text /project/company/general/name text]' ) === '<p class="text">ACME</p>' );
check( 'an empty fixed text is a value of nothing: the component is not drawn', $draw( '[text /project/company/general/name text=""]' ) === '' );
check( 'no source and no text: nothing is drawn, and what is inside it either', $draw( '[text][text text="x"]' ) === '<p class="text">x</p>' && $draw( '[titled][text text="in"][/titled]' ) === '' && $draw( '[titled text="k"][text text="in"][/titled]' ) === '<h title="a &quot;b&quot; &lt;c&gt; &amp;">k|<p class="text">in</p></h>' );
check( 'a key nobody has a text for is no value', $draw( '[text /project/company/general/nobody]' ) === '' && $draw( '[text /project/company/general/blank]' ) === '' );
check( 'a text with its quotes as entities is plain text', $draw( '[text /project/company/general/tag]' ) === '<p class="text">Tag &lt;b&gt;in&lt;/b&gt; &quot;text&quot;</p>' );
check( 'the format says what a text may hold: blocks keep paragraphs and the tags of a line, inline keeps the tags of a line and no paragraph',
	$draw( '[rich /template/page-x/hero/blocks]' ) === '<div><p>Eins</p><p>Zwei <strong>fett</strong></p></div>' && $draw( '[inline /template/page-x/hero/blocks]' ) === '<span>Eins Zwei <strong>fett</strong></span>' );
$rich = $draw( '[rich /template/page-x/hero/blocks][inline /template/page-x/hero/blocks][rich text="<p>[x]</p><iframe src=y></iframe>"]' );
check( 'the format: no script, no handler, no iframe in what comes out, and a [ is a [', str_contains( $rich, 'script' ) === false && str_contains( $rich, 'onclick' ) === false && str_contains( $rich, 'iframe' ) === false && str_contains( $rich, '<p>[x]</p>' ) === true );
check( 'a field of the element: the text of its type, escaped; .id the number in the loop, .uri and .slug', $draw( '[text .title][text .id][text .uri][text .slug]', [ 'template' => 'page-x', 'element' => $element ] )
	=== '<p class="text">T &amp; &lt;b&gt;x&lt;/b&gt; [sic]</p><p class="text">3</p><p class="text">/post/hello-world</p><p class="text">hello-world</p>' );
check( 'a field that holds html is written as its model says it: sanitized to its format, whatever format the component has', $draw( '[text .body]', [ 'template' => 'page-x', 'element' => $element ] ) === '<p class="text"><p>Hi <em>there</em></p></p>' );
$said = $logged( static function() use ( $draw, $element, &$out ): void {
	$out = $draw( '[text .empty][text .nothing][text .list][text .a/b][text .1x]', [ 'template' => 'page-x', 'element' => $element ] );
} );
check( '...a field with no value, no field, a field that is a list, and a name that is no field: nothing - and nothing said', $out === '' && $said === [] );
check( 'without an element a field is nothing', $draw( '[text .title][text .id]' ) === '' && $draw( '[text .title]', [ 'template' => 'page-x', 'element' => [ 'id' => 1 ] ] ) === '' && $draw( '[text .title]', [ 'template' => 'page-x', 'element' => 'x' ] ) === '' );
check( 'an element is the context of the children: a card in a loop reads the field of its element', $draw( '[card][card-title .title][/card]', [ 'template' => 'page-x', 'element' => $element ] ) === '<article class="card"><h3 class="card-title">T &amp; &lt;b&gt;x&lt;/b&gt; [sic]</h3></article>' );

$out = $draw( '[go /project/company/general/url][go /project/company/general/mail][go /_nino/webpage/contact/uri][go ~evil][go /project/company/general/nobody]' );
check( 'a link: the text of the key as an address - read as a plain text, an & once - a mail, the address of a page with the directory of the site in front, a key of no text as the address it is, a relative key of none as nothing',
	$out === '<a href="https://example.test/?q=1&amp;r=2">go</a><a href="mailto:a@example.test">go</a><a href="/shop/kontakt">go</a><a href="/project/company/general/nobody">go</a>' );
$appData['./nino/locales/current'] = 'en_US';
unset( $appData['./nino/html/filecache'] );
check( 'the address of a page is the one of the language of the page', $draw( '[go /_nino/webpage/contact/uri]' ) === '<a href="/shop/contact">go</a>' );
$appData['./nino/locales/current'] = 'de_DE';
unset( $appData['./nino/html/filecache'] );
check( 'a page whose address is another site\'s is that address, with no directory in front of it', $draw( '[go /_nino/webpage/ext/uri]' ) === '<a href="https://ext.test/x">go</a>' );
check( 'a link from a field that is a script is no address', $draw( '[go .bad][go .target]', [ 'template' => 'page-x', 'element' => $element ] ) === '<a href="/blog/x?a=1&amp;b=2">go</a>' );
check( 'a link from a field of the element: the address of the field, .id, .uri and .slug as addresses', $draw( '[go .target][go .id][go .uri][go .slug][go .empty][go .title]', [ 'template' => 'page-x', 'element' => $element ] )
	=== '<a href="/blog/x?a=1&amp;b=2">go</a><a href="3">go</a><a href="/post/hello-world">go</a><a href="hello-world">go</a><a href="T &amp; &lt;b&gt;x&lt;/b&gt; [sic]">go</a>' );

check( 'an image is the reference to the picture: the key of the slot as it is, escaped, and not looked up', $draw( '[section-wrap id="hero"][pic ~photo][pic /logo][/section-wrap]' )
	=== '<section id="hero" class="section-wrap"><img src="/template/page-x/hero/photo" alt=""><img src="/logo" alt=""></section>'
	&& \Nino\Components::render( $appData, [ [ 'n' => 'pic', 's' => '/a/b"c<d', 'a' => [], 'c' => null, 'o' => 0 ] ] ) === '<img src="/a/b&quot;c&lt;d" alt="">' );
check( '...a field of the element that is an image is the file; a field of another type, an image field without a file and a name that is no field are no image', $draw( '[pic .photo][pic .title][pic .empty][pic .cover][pic .nothing][pic ~photo]', [ 'template' => 'page-x', 'element' => $element ] ) === '<img src="a.jpg" alt="">' );
$wild = [ 'template' => 'page-x', 'element' => [ 'uri' => '/post', 'id' => 1, 'element' => [ '.uri' => '/post/<x>&"y' ], 'model' => [] ] ];
check( 'the uri and the slug of the element are written escaped: they are what the element is called', $draw( '[text .uri][text .slug]', $wild ) === '<p class="text">/post/&lt;x&gt;&amp;&quot;y</p><p class="text">&lt;x&gt;&amp;&quot;y</p>' );
check( 'an element with no model is none: a field of it is nothing, a textfill of it too', $draw( '[text .title][text .id]', [ 'template' => 'page-x', 'element' => [ 'id' => 1, 'element' => [ 'title' => 'x' ] ] ] ) === '' && $draw( '[[title]]|', [ 'template' => 'page-x', 'element' => [ 'id' => 1, 'element' => [ 'title' => 'x' ] ] ] ) === '[[title]]|' );

echo "\n";


// --- The attributes ----------------------------------------------------------------

echo "The attributes and the classes\n";

check( 'a choice: a value in the options that is not the default is a class, the default is none, a value outside is the default', $draw( '[card padding="3"][/card][card padding="2"][/card][card padding="x"][/card]' ) === '<article class="card card--padding-3"></article><article class="card"></article><article class="card"></article>' );
check( 'the classes come in the order of the schema, whatever the order of the call', $draw( '[card ground="dark" padding="0" fill][/card]' ) === '<article class="card card--fill card--padding-0 card--ground-dark"></article>' );
check( 'a bool with a default of true is on where the call says nothing, and is a class then; off is none, and so is a word that is none of the two lists - the default', $draw( '[lamp]' ) === '<lamp class="lamp lamp--lit"></lamp>' && $draw( '[lamp lit="no"]' ) === '<lamp class="lamp"></lamp>'
	&& $draw( '[lamp lit="OFF"]' ) === '<lamp class="lamp"></lamp>' && $draw( '[lamp lit="maybe"]' ) === '<lamp class="lamp lamp--lit"></lamp>' && $draw( '[lamp lit]' ) === '<lamp class="lamp lamp--lit"></lamp>' );
check( 'an int is within its bounds, or the default: too big is the bound, too small is the bound, a word is the default, a number with a sign and one that is too long are read as they are',
	$draw( '[knob count="5"][/knob]' ) === str_replace( 'n="3"', 'n="5"', $draw( '[knob][/knob]' ) ) && str_contains( $draw( '[knob count="99"][/knob]' ), 'n="9"' ) === true && str_contains( $draw( '[knob count="-4"][/knob]' ), 'n="1"' ) === true
	&& str_contains( $draw( '[knob count="x"][/knob]' ), 'n="3"' ) === true && str_contains( $draw( '[knob count="1.5"][/knob]' ), 'n="3"' ) === true && str_contains( $draw( '[knob count="12345678901"][/knob]' ), 'n="3"' ) === true && str_contains( \Nino\Components::render( $appData, [ [ 'n' => 'knob', 's' => '', 'a' => [ 'count' => "5\n" ], 'c' => null, 'o' => 0 ] ] ), 'n="3"' ) === true
	&& str_contains( $draw( '[knob count][/knob]' ), 'n="3"' ) === true );
check( 'a string is escaped, a bare word is the default, an attribute that is no class is [[name]] of the template', str_contains( $draw( '[knob label="a &quot; <b> \" \' &"][/knob]' ), 'l="a &amp;quot; &lt;b&gt; &quot; &#039; &amp;"' ) === true && str_contains( $draw( '[knob label][/knob]' ), 'l="L"' ) === true
	&& str_contains( $draw( '[knob tag="h2"][/knob]' ), 't="h2"' ) === true && str_contains( $draw( '[knob tag="h7"][/knob]' ), 't="p"' ) === true && str_contains( $draw( '[knob tag="h2"][/knob]' ), 'knob--tag' ) === false );
check( 'a bool that is no class is [[name]] as 1 or 0, and its default is 0 here', str_contains( $draw( '[knob flag][/knob]' ), 'f="1"' ) === true && str_contains( $draw( '[knob][/knob]' ), 'f="0"' ) === true && str_contains( $draw( '[knob flag="no"][/knob]' ), 'f="0"' ) === true && str_contains( $draw( '[knob flag][/knob]' ), 'knob--flag' ) === false );
check( 'an id is a word of lower case letters, digits and hyphens that starts with a letter; anything else is none, and the tag has no id', str_contains( $draw( '[knob anchor="x-1"][/knob]' ), ' id="x-1"' ) === true && str_contains( $draw( '[knob anchor="x-1"][/knob]' ), 'a="x-1"' ) === true
	&& str_contains( $draw( '[knob anchor="1x"][/knob]' ), ' id=' ) === false && str_contains( $draw( '[knob anchor="X"][/knob]' ), ' id=' ) === false && str_contains( $draw( '[knob anchor="a b"][/knob]' ), ' id=' ) === false
	&& str_contains( $draw( '[knob anchor="a"b"][/knob]' ), 'onclick' ) === false && str_contains( $draw( '[knob anchor][/knob]' ), ' id=' ) === false && str_contains( $draw( '[knob anchor="x-"][/knob]' ), ' id="x-"' ) === true && str_contains( $draw( '[knob anchor="x_y"][/knob]' ), ' id=' ) === false );
check( 'an id that has a default is that where the call says nothing or a bare word; a call that writes an empty or a bad one has none, and the scope follows', $draw( '[anchored][/anchored]' ) === '<a id="start"></a>' && $draw( '[anchored anchor][/anchored]' ) === '<a id="start"></a>' && $draw( '[anchored anchor=""][/anchored]' ) === '<a></a>' && $draw( '[anchored anchor="Bad"][/anchored]' ) === '<a></a>'
	&& $draw( '[anchored anchor="x"][/anchored]' ) === '<a id="x"></a>' && $draw( '[anchored][section-title ~t][/anchored]' ) === '<a id="start"><h2 class="section-title">Start T</h2></a>' );
check( 'an id is the scope of what is inside, the one of the attribute whatever the attribute is called', $draw( '[knob anchor="a"][section-title ~t][/knob]' ) === '<i class="knob" id="a" t="p" f="0" n="3" l="L" h="" p="" a="a"><h2 class="section-title">Text A</h2></i>' );
check( 'an attribute of the type id with an invalid value gives no scope, and the scope above stays', $draw( '[section-wrap id="a"][knob anchor="1"][section-title ~t][/knob][/section-wrap]' ) === '<section id="a" class="section-wrap"><i class="knob" t="p" f="0" n="3" l="L" h="" p="" a=""><h2 class="section-title">Text A</h2></i></section>' );

echo "\n";


// --- Responsive values -----------------------------------------------------------

echo "Responsive values\n";

$size = static fn( string $value ): string => $draw( '[section-col size="'. $value. '"][/section-col]' );
check( 'the first part is for every width and a class where it is not the default', $size( '50' ) === '<div class="section-col section-col--size-50"></div>' && $size( '100' ) === '<div class="section-col"></div>' );
check( 'a part with a breakpoint is a class whatever the value is - the default as well', $size( '100 m:100' ) === '<div class="section-col section-col--m-size-100"></div>' && $size( '50 m:50 l:50' ) === '<div class="section-col section-col--size-50 section-col--m-size-50 section-col--l-size-50"></div>' );
check( 'where the value starts with a breakpoint, the value for every width is the default - and the breakpoints stand', $size( 'm:50' ) === '<div class="section-col section-col--m-size-50"></div>' && $size( 'm:50 l:25' ) === '<div class="section-col section-col--m-size-50 section-col--l-size-25"></div>' );
check( 'a second part without a breakpoint falls away', $size( '50 33' ) === '<div class="section-col section-col--size-50"></div>' && $size( 'm:50 33' ) === '<div class="section-col section-col--m-size-50"></div>' );
check( 'a breakpoint nobody knows, an empty value and a value outside the options fall away', $size( '50 s:25' ) === '<div class="section-col section-col--size-50"></div>' && $size( '50 m:' ) === '<div class="section-col section-col--size-50"></div>' && $size( '50 m:99' ) === '<div class="section-col section-col--size-50"></div>'
	&& $size( '50 :25' ) === '<div class="section-col section-col--size-50"></div>' && $size( '50 M:25' ) === '<div class="section-col section-col--size-50"></div>' && $size( '50 mm:25' ) === '<div class="section-col section-col--size-50"></div>' );
check( 'a responsive choice that has the empty word among its options: the empty word is the default, and a breakpoint with nothing behind it is none', $draw( '[rows align="left"]' ) === '<r class="rows rows--align-left"></r>' && $draw( '[rows align="m:"]' ) === '<r class="rows"></r>' && $draw( '[rows align="left m:right l:"]' ) === '<r class="rows rows--align-left rows--m-align-right"></r>'
	&& $draw( '[rows align=""]' ) === '<r class="rows"></r>' && $draw( '[rows align="m:left"]' ) === '<r class="rows rows--m-align-left"></r>' && $draw( '[rows]' ) === '<r class="rows"></r>' );
check( 'a breakpoint that is named twice has the first value', $size( '50 m:25 m:75' ) === '<div class="section-col section-col--size-50 section-col--m-size-25"></div>' );
check( 'the classes are in the order they are written, and blanks around and between the parts are nothing', $size( '50 l:25 m:75' ) === '<div class="section-col section-col--size-50 section-col--l-size-25 section-col--m-size-75"></div>' && $size( '  50   m:25  ' ) === '<div class="section-col section-col--size-50 section-col--m-size-25"></div>' );
check( 'a value outside the options as the first part is no part: the default for every width, and what follows it stands', $size( '77 m:50' ) === '<div class="section-col section-col--m-size-50"></div>' );
check( 'an attribute that is no responsive one reads the whole string as one value', $draw( '[card padding="3 m:2"][/card]' ) === '<article class="card"></article>' && $draw( '[section-row gap="1 m:2"][/section-row]' ) === '<div class="section-row"></div>' );
check( 'the breakpoints are those of Markup::BREAKPOINTS', ( static function() use ( $size ): bool {
	foreach( array_keys( \Nino\Markup::BREAKPOINTS ) as $width )
		if( $size( '50 '. $width. ':25' ) !== '<div class="section-col section-col--size-50 section-col--'. $width. '-size-25"></div>' )
			return false;
	return true;
} )() );

echo "\n";


// --- A class carries nothing the schema did not name -----------------------------

echo "What a class can carry\n";

$evil = 'x" onclick="alert(1)';
$out = $draw( '[card padding="'. str_replace( '"', '\\"', $evil ). '" ground="x onclick=y" fill="'. str_replace( '"', '\\"', $evil ). '" variant="'. str_replace( '"', '\\"', $evil ). '"][/card][section-col size="'. str_replace( '"', '\\"', $evil ). '" align="center m:'. str_replace( '"', '\\"', $evil ). '"][/section-col]' );
check( 'a value that is no option is the default, in every kind of attribute, a responsive one and a variant included: nothing of it is in a class', $out === '<article class="card"></article><div class="section-col section-col--align-center"></div>' && str_contains( $out, 'onclick' ) === false );
check( 'a class is the name, the classes of the schema and the variant - a call that writes a class has none of it', $draw( '[card class="evil" style="x"][/card]' ) === '<article class="card"></article>' );
$bad = [ [ 'a b' ], [ 'a"b' ], [ 'A' ], [ 'a_b' ], [ 'a.b' ], [ '-a' ], [ 'a--b' ], [ 'a/b' ] ];
$refused = 0;
foreach( $bad as [ $word ] )
	if( is_string( \Nino\Components::validate( 'w', [ 'label' => 'W', 'attributes' => [ 'x' => [ 'type' => 'choice', 'options' => [ 'a', $word ], 'default' => 'a' ] ] ] ) ) === true )
		$refused++;
check( 'an option that is written as a class is a word: with a space, a quote, a capital, an underscore, a dot, a slash, a hyphen in the wrong place it is not accepted', $refused === count( $bad ) );
check( '...an option that is not written as a class may be anything printable', is_array( \Nino\Components::validate( 'w', [ 'label' => 'W', 'attributes' => [ 'x' => [ 'type' => 'choice', 'options' => [ 'a b', 'A"b', 'a_b' ], 'default' => 'a b', 'class' => false ] ] ] ) ) === true );
check( 'the value that is no class is escaped where it is written', str_contains( $draw( '[section-title text="x" tag="h2"][/section-title]' ), '<h2 class' ) === true && str_contains( \Nino\Components::render( $appData, [ [ 'n' => 'section-title', 's' => '', 'a' => [ 'text' => 'x', 'tag' => '"><script>' ], 'c' => null, 'o' => 0 ] ] ), 'script' ) === false );

echo "\n";


// --- The variants of a template -----------------------------------------------------

echo "The classes of a variant and of the template\n";

check( 'a variant that is not regular is a class after the others, one that is is none', $draw( '[button text="x" variant="outline"]' ) === '<a class="button button--variant-outline" href="">x</a>' && $draw( '[button text="x" variant="regular"]' ) === '<a class="button" href="">x</a>' && $draw( '[button text="x" variant]' ) === '<a class="button" href="">x</a>' );
check( 'the template is filled: [[class]], [[content]], [[value]], [[id]], [[lang]]; a textfill in the page is the page\'s and is put in before', $draw( '[free]a[/free]' ) === 'free|a|||' && $draw( '[free]x [[nothing]] [[/project/company/general/name]][/free]' ) === 'free|x [[nothing]] ACME|||' );
check( 'a token of the template that is none stays as it is - one of an attribute that is a class too - and a token inside a value is no token: one pass', ( static function() use ( &$appData ): bool {
	\Nino\Components::addComponent( $appData, 'tokens', [ 'label' => 'Tokens', 'source' => 'text', 'attributes' => [ 'x' => [ 'type' => 'string', 'default' => '' ], 'g' => [ 'type' => 'choice', 'options' => [ 'a', 'b' ], 'default' => 'a' ] ], 'template' => '[[none]]|[[x]]|[[value]]|[[class]]|[[x]]|[[g]]' ] );
	return \Nino\Components::render( $appData, [ [ 'n' => 'tokens', 's' => '', 'a' => [ 'text' => '[[x]][[class]]', 'x' => '[[value]]', 'g' => 'b' ], 'c' => null, 'o' => 0 ] ] ) === '[[none]]|[[value]]|[[x]][[class]]|tokens tokens--g-b|[[value]]|[[g]]';
} )() );
check( 'the slots of the template: what the children of that name drew, in their order, and the rest where [[content]] is - the text between them too', $draw( '[slotted]a[text text="1"]b[card-title text="T"]c[text text="2"][card-title text="U"][card][/card][/slotted]' )
	=== '<div><h3 class="card-title">T</h3><h3 class="card-title">U</h3><i>abc<article class="card"></article></i><b><p class="text">1</p><p class="text">2</p></b></div>' );
check( 'a slot nobody fills is nothing, an empty container draws its template, a leaf draws it with no content', $draw( '[slotted][/slotted]' ) === '<div><i></i><b></b></div>' && $draw( '[free /]' ) === 'free||||' );
check( 'a child that is not drawn - an unknown one, a text of no value - is nothing in its slot', $draw( '[slotted][card-title ~nothing][gallery][card-title text="T"][/slotted]' ) === '<div><h3 class="card-title">T</h3><i></i><b></b></div>' );

echo "\n";


// --- The renderer ---------------------------------------------------------------------

echo "The renderer\n";

$seen->exchangeArray( [] );
$out = $draw( '[section-wrap id="a"][probe-echo /project/company/general/name mode="b" size="50 m:25" tag="h2" on flag count="7" label="L<" href="/x" pic="~photo" anchor="z" variant="soft"]in[/probe-echo][/section-wrap]' );
[ $args, $content, $given ] = $seen[0] ?? [ [], '', [] ];
check( 'a renderer is asked: its answer is the component, and what the children drew is its content', $out === '<section id="a" class="section-wrap"><echo>in</echo></section>' && count( $seen ) === 1 && $content === 'in' );
ksort( $args );
check( 'the arguments: every attribute as a string - a bool 1 or 0, an int its digits, a string, a link and an image escaped, the value for every width of a responsive one - and value, source, class, variant and lang - the slug of the id as the attribute says it, the tag form is the token of a template', $args === [
	'anchor' => 'z', 'class' => 'probe-echo probe-echo--mode-b probe-echo--size-50 probe-echo--m-size-25 probe-echo--on probe-echo--variant-soft', 'count' => '7', 'flag' => '1', 'href' => '/x', 'label' => 'L&lt;',
	'lang' => '', 'mode' => 'b', 'on' => '1', 'pic' => '/template/page-x/a/photo', 'quiet' => '0', 'size' => '50', 'source' => '/project/company/general/name', 'tag' => 'h2', 'value' => 'ACME', 'variant' => 'soft' ] );
check( 'the context it is given is the one the call stands in - the scope above the call and not the one it makes - and the children are drawn one deeper at its place', $given === [ 'template' => 'page-x', 'scope' => 'a', 'place' => 'section-wrap', 'element' => null, 'fills' => [], 'depth' => 1 ] );
$seen->exchangeArray( [] );
$draw( '[probe-echo ~title][card][probe-echo .title][/card][/probe-echo]', [ 'template' => 'page-x', 'scope' => 'hero', 'place' => 'flow', 'depth' => 3, 'element' => $element ] );
check( 'the children of a call have its name as their place, one more depth, the scope, the template and the element of the loop', count( $seen ) === 2 && $seen[0][2] === [ 'template' => 'page-x', 'scope' => 'hero', 'place' => 'card', 'element' => $element, 'fills' => [], 'depth' => 5 ]
	&& $seen[1][2] === [ 'template' => 'page-x', 'scope' => 'hero', 'place' => 'flow', 'element' => $element, 'fills' => [], 'depth' => 3 ] && $seen[1][0]['source'] === '~title' && $seen[1][0]['value'] === 'Hallo &amp; &lt;Welt&gt; [sic]' );
$seen->exchangeArray( [] );
$draw( '[probe-echo /project/company/general/name size="m:25" /][probe-echo /project/company/general/name size="77 l:50" /]' );
check( 'the argument of a responsive choice is the value for every width: the default where the call starts with a breakpoint or with a value that is none', $seen[0][0]['size'] === '100' && $seen[1][0]['size'] === '100' && $seen[0][0]['class'] === 'probe-echo probe-echo--m-size-25' && $seen[1][0]['class'] === 'probe-echo probe-echo--l-size-50' );
$seen->exchangeArray( [] );
$draw( '[probe-echo /project/company/general/name]' );
check( 'the arguments of a call that writes nothing: the defaults, no id, no class but its name and the variant regular', $seen[0][0]['count'] === '3' && $seen[0][0]['mode'] === 'a' && $seen[0][0]['on'] === '0' && $seen[0][0]['quiet'] === '0' && $seen[0][0]['anchor'] === '' && array_key_exists( 'id', $seen[0][0] ) === false && $seen[0][0]['variant'] === 'regular' && $seen[0][0]['class'] === 'probe-echo' && $seen[0][0]['size'] === '100' );
$seen->exchangeArray( [] );
$appData['./nino/locales/current'] = 'en_US';
unset( $appData['./nino/html/filecache'] );
$draw( '[probe-echo /template/page-x/hero/subtitle]' );
$appData['./nino/locales/current'] = 'de_DE';
unset( $appData['./nino/html/filecache'] );
check( 'the argument lang says the language of the text as the template does', $seen[0][0]['lang'] === ' lang="de-DE"' );

$out = $draw( '[raw][number][listing][nothing][raw]' );
check( 'what a renderer answers is the end of it: a [ and a [[ in it are what they are, a number is its digits, a list or nothing is nothing', $out === '[template /templates/x] [[/project/company/general/name]] [card]42[template /templates/x] [[/project/company/general/name]] [card]' );
$said = $logged( static function() use ( $draw, &$out ): void {
	$out = $draw( 'a[boom]in[/boom]b[boom /]c[card][boom][/boom][/card]' );
} );
check( 'a renderer that throws is a warning with its name and the kind of the error - and a hole in the page, the rest is drawn', $out === 'abc<article class="card"></article>' && count( $said ) === 1 && str_contains( $said[0], '"boom"' ) === true && str_contains( $said[0], 'RuntimeException' ) === true && strlen( $said[0] ) < 200 );
$said = $logged( static function() use ( $draw, &$out ): void {
	$out = $draw( 'a[anon /]b[boom /]c' );
} );
check( 'a renderer that throws an exception of an anonymous class is a line of a name and no path or null byte, and one line for each renderer', $out === 'abc' && count( $said ) === 2 && !str_contains( $said[0], "\0" ) && !str_contains( $said[0], '/' ) && str_contains( $said[0], 'Exception' ) === true && strlen( $said[0] ) < 200 );
unset( $appData[\Nino\Components::NOTICED] );
$levels = $errorsOf( static function() use ( $draw ): void { $draw( '[boom /]' ); } );
check( 'the line about a renderer is a warning, the level of what is broken, where an unknown name is a notice', count( $levels ) === 1 && $levels[0][0] === E_USER_WARNING );
check( 'a renderer that fails with an error - a type that is wrong - is no exception: the error goes on up', ( static function() use ( $draw ): bool {
	try {
		$draw( '[crash /]' );
	}
	catch( \Error $error ) {
		return true;
	}
	return false;
} )() );
check( 'the children of a call that has a renderer are drawn before it, and a renderer that throws takes them with it', $draw( '[boom][text text="x"][/boom]' ) === '' );
$out = $draw( '[text /project/company/general/name][filler]x[text /project/company/general/late]' );
check( 'a fill a renderer adds is seen by the nodes after it, though the fills were read before it', $out === '<p class="text">ACME</p>x<p class="text">Spät</p>' );

echo "\n";


// --- The textfills -----------------------------------------------------------------

echo "Textfills\n";

$fills = [ '/project/company/general/name', '/project/company/general/tag', '/project/company/general/url', '/project/company/general/blank', '/project/company/general/macro',
	'/template/page-x/hero/nested', '/template/page-x/hero/title', '/nope/a/b/c', '/project/company/general/nobody', '/_nino/webpage/contact/title', '/nino/http/response/uri', '/nino/dir' ];
$same = true;
$samples = [];
foreach( $fills as $key ) {
	$tree = \Nino\Markup::parse( 'A[['. $key. ']]B', [], $notes );
	$ours = \Nino\Components::render( $appData, $tree, $page );
	$page1 = \Nino\Html::renderHtml( $appData, 'A[['. $key. ']]B' );
	$samples[$key] = $ours;
	$same = $same && $ours === $page1;
}
check( 'a textfill is what a page puts there: the value as it is, markup and quotes and all, the fills inside it resolved - for known keys, unknown keys, empty ones, a value that refers to itself and the runtime fills',
	$same === true && $samples['/project/company/general/tag'] === 'ATag <b>in</b> "text"B' && $samples['/project/company/general/name'] === 'AACMEB' && $samples['/template/page-x/hero/nested'] === 'AVon ACMEB' );
check( 'an unknown key stays written, as a page keeps it; an empty text is nothing', $samples['/nope/a/b/c'] === 'A[[/nope/a/b/c]]B' && $samples['/project/company/general/nobody'] === 'A[[/project/company/general/nobody]]B' && $samples['/project/company/general/blank'] === 'AB' );
check( 'a value that holds markup is the page\'s: not read as a call by the drawing - [template /templates/x] is a text here', $samples['/project/company/general/macro'] === 'A[template /templates/x] und [probe-echo]B' );
check( 'a value that refers to itself ends - at most ten passes, those of resolveTextfill()', \Nino\Components::render( $appData, [ [ 'f' => '/project/company/general/self' ] ], $page ) === \Nino\Html::resolveTextfill( $appData, '/project/company/general/self' )
	&& substr_count( \Nino\Components::render( $appData, [ [ 'f' => '/project/company/general/self' ] ], $page ), 'Selbst' ) === 11 );
$tree = \Nino\Markup::parse( '[[/_nino/webpage[[/nino/http/response/uri]]/title]]|[[/_nino/webpage[[/nope/a/b/c]]/title]]|[[/_nino/webpage/contact[[/nino/dir]]/x]]', [], $notes );
check( 'a fill inside the key is put in first - the one level the templates of the kernel use - and what is not there stays as it is, the part of it that was put in as well',
	\Nino\Components::render( $appData, $tree, $page ) === \Nino\Html::renderHtml( $appData, '[[/_nino/webpage[[/nino/http/response/uri]]/title]]|[[/_nino/webpage[[/nope/a/b/c]]/title]]|[[/_nino/webpage/contact[[/nino/dir]]/x]]' )
	&& str_starts_with( \Nino\Components::render( $appData, $tree, $page ), 'Kontakt <Titel>|' ) === true );
check( 'a textfill is looked up in the language of the page', ( static function() use ( &$appData, $draw ): bool {
	$appData['./nino/locales/current'] = 'en_US';
	unset( $appData['./nino/html/filecache'] );
	$out = \Nino\Components::render( $appData, [ [ 'f' => '/template/page-x/hero/title' ], [ 't' => ' ' ], [ 'f' => '/_nino/webpage/contact/uri' ] ], [ 'template' => 'page-x' ] );
	$appData['./nino/locales/current'] = 'de_DE';
	unset( $appData['./nino/html/filecache'] );
	return $out === 'Hello /contact';
} )() );
check( 'a fill added at runtime is a fill, and the fills of a drawing are those of the moment it starts', ( static function() use ( &$appData ): bool {
	\Nino\Html::addFills( $appData, [ '/project/company/general/later' => 'Später' ] );
	return \Nino\Components::render( $appData, [ [ 'f' => '/project/company/general/later' ] ] ) === 'Später';
} )() );
check( 'a text that is a call is a text: a node of a call written by hand in a fill is not a call', \Nino\Components::render( $appData, [ [ 'f' => '/project/company/general/macro' ] ] ) === '[template /templates/x] und [probe-echo]' );

$tree = \Nino\Markup::parse( '[[title]]|[[.id]]|[[.uri]]|[[.slug]]|[[.lang]]|[[body]]|[[nothing]]|[[empty]]', [], $notes );
$out = \Nino\Components::render( $appData, $tree, [ 'template' => 'page-x', 'element' => $element ] );
$fields = \Nino\Modules\Elements::fills( $element['element'], $element['model'], 3 );
check( 'a key without a slash is a field of the element: as a cell of a stack writes it, with the brackets of the text the characters they are; one the element has no field for stays written', $out === 'T &amp; &lt;b&gt;x&lt;/b&gt; [sic]|3|/post/hello-world|hello-world| lang="de_DE"|'. str_replace( '&#91;', '[', $fields['[[body]]'] ). '|[[nothing]]|'
	&& str_contains( $out, 'script' ) === false );
$written = '[[title]]|[[.id]]|[[.uri]]|[[.slug]]|[[.lang]]|[[body]]|[[nothing]]|[[empty]]';
check( 'without an element a key without a slash stays written, as an unknown fill does - the code that draws the page may fill it later - exactly as a page keeps it', \Nino\Components::render( $appData, $tree, $page ) === $written && \Nino\Components::render( $appData, $tree ) === $written
	&& $written === \Nino\Html::renderHtml( $appData, $written ) && $draw( 'a [[url]] b [[/nope/a/b/c]]' ) === \Nino\Html::renderHtml( $appData, 'a [[url]] b [[/nope/a/b/c]]' ) );
check( 'a field of the element in the middle of a page is the text of the page around it', $draw( 'Before [[title]] [card][[.slug]][/card] after', [ 'template' => 'page-x', 'element' => $element ] ) === 'Before T &amp; &lt;b&gt;x&lt;/b&gt; [sic] <article class="card">hello-world</article> after' );

echo "\n";


// --- What validate() turns down ---------------------------------------------------

echo "validate() - what a schema must be, and what is wrong with it\n";

$ok = [ 'label' => 'Ok', 'template' => '<x>[[content]]</x>' ];
$attribute = static fn( array $declared, string $name = 'a' ): array => $ok + [ 'attributes' => [ $name => $declared ] ];
$choice = [ 'type' => 'choice', 'options' => [ 'a', 'b' ], 'default' => 'a' ];

mkdir( $sandbox. '/features/Demo', 0755, true );
file_put_contents( $sandbox. '/features/Demo/card.tpl', '<div class="[[class]]">[[content]]</div>' );
file_put_contents( $sandbox. '/features/Demo/big.tpl', str_repeat( 'x', 65537 ) );
file_put_contents( $sandbox. '/features/Demo/nul.tpl', "a\0b" );
file_put_contents( $sandbox. '/features/outside.tpl', '<outside>' );
file_put_contents( $sandbox. '/features/Demo/.hidden.tpl', '<hidden>' );
file_put_contents( $sandbox. '/features/Demo/a b.tpl', '<blank>' );
file_put_contents( $sandbox. '/features/Demo/DemoX.php', '<?php' );
mkdir( $sandbox. '/features/Demo/sub', 0755, true );
file_put_contents( $sandbox. '/features/Demo/sub/inner.tpl', '<inner>' );
mkdir( $sandbox. '/features/Demo/dir.tpl', 0755, true );
$directory = $sandbox. '/features/Demo';
$linked = @symlink( $sandbox. '/features/outside.tpl', $directory. '/link.tpl' );

/*	[ what is wrong, name, schema, what the message must name, feature, directory ]	*/
$refused = [
	[ 'a name with a capital', 'Bad', $ok, 'name' ],
	[ 'a name that starts with a digit', '1a', $ok, 'name' ],
	[ 'a name with an underscore', 'a_b', $ok, 'name' ],
	[ 'a name with two hyphens in a row', 'a--b', $ok, 'name' ],
	[ 'a name that ends in a hyphen', 'a-', $ok, 'name' ],
	[ 'an empty name', '', $ok, 'name' ],
	[ 'a name that is too long', str_repeat( 'a', 41 ), $ok, 'name' ],
	[ 'a name with a null byte', "a\0b", $ok, 'name' ],
	[ 'the name of the page', 'page', $ok, '"page"' ],
	[ 'the name of the flow', 'flow', $ok, '"flow"' ],
	[ 'a schema that is no array', 'x', 'string', 'schema' ],
	[ 'a schema that is null', 'x', null, 'schema' ],
	[ 'a key nobody knows', 'x', $ok + [ 'holds' => 'card' ], '"holds"' ],
	[ 'a key of the old schema: by', 'x', $ok + [ 'by' => 'nino' ], '"by"' ],
	[ 'a numeric key', 'x', $ok + [ 5 => 'a' ], 'key' ],
	[ 'no label', 'x', [ 'template' => '<x>' ], '"label"' ],
	[ 'an empty label', 'x', [ 'label' => ' ', 'template' => '<x>' ], '"label"' ],
	[ 'a label that is a list', 'x', [ 'label' => [ 'a' ], 'template' => '<x>' ], '"label"' ],
	[ 'a label of a locale with nothing in it', 'x', [ 'label' => [ 'de_DE' => '' ], 'template' => '<x>' ], '"label"' ],
	[ 'a label with a null byte', 'x', [ 'label' => "a\0b", 'template' => '<x>' ], '"label"' ],
	[ 'a group with a capital', 'x', $ok + [ 'group' => 'Basis' ], '"group"' ],
	[ 'a group that is no string', 'x', $ok + [ 'group' => 5 ], '"group"' ],
	[ 'in that is a string', 'x', $ok + [ 'in' => 'flow' ], '"in"' ],
	[ 'in with a map', 'x', $ok + [ 'in' => [ 'a' => 'flow' ] ], '"in"' ],
	[ 'in with a place that is no slug', 'x', $ok + [ 'in' => [ 'Flow' ] ], '"in"' ],
	[ 'in with a place twice', 'x', $ok + [ 'in' => [ 'flow', 'flow' ] ], '"in"' ],
	[ 'in with a place that is no string', 'x', $ok + [ 'in' => [ 5 ] ], '"in"' ],
	[ 'offers that is a string', 'x', $ok + [ 'offers' => 'flow' ], '"offers"' ],
	[ 'offers with a place that is too long', 'x', $ok + [ 'offers' => [ str_repeat( 'a', 41 ) ] ], '"offers"' ],
	[ 'once that is no bool', 'x', $ok + [ 'once' => 'yes' ], '"once"' ],
	[ 'a source that is none of them', 'x', $ok + [ 'source' => 'content' ], '"source"' ],
	[ 'a source that is no string', 'x', $ok + [ 'source' => [ 'text' ] ], '"source"' ],
	[ 'a format for a component that is no text', 'x', $ok + [ 'format' => 'plain' ], '"format"' ],
	[ 'a format that is none of them', 'x', $ok + [ 'source' => 'text', 'format' => 'html' ], '"format"' ],
	[ 'a preview that is none of them', 'x', $ok + [ 'preview' => 'poster' ], '"preview"' ],
	[ 'variants that are no list', 'x', $ok + [ 'variants' => 'a' ], '"variants"' ],
	[ 'variants that are a map', 'x', $ok + [ 'variants' => [ 'a' => 'b' ] ], '"variants"' ],
	[ 'a variant with a capital', 'x', $ok + [ 'variants' => [ 'Band' ] ], 'variant' ],
	[ 'a variant that is not a string', 'x', $ok + [ 'variants' => [ 5 ] ], 'variant' ],
	[ 'attributes that are no map', 'x', $ok + [ 'attributes' => 'a' ], '"attributes"' ],
	[ 'an attribute of a number', 'x', $ok + [ 'attributes' => [ 'a' ] ], 'attribute' ],
	[ 'an attribute with a capital to start', 'x', $attribute( $choice, 'Mode' ), 'attribute' ],
	[ 'an attribute with a hyphen', 'x', $attribute( $choice, 'a-b' ), 'attribute' ],
	[ 'an attribute with an underscore', 'x', $attribute( $choice, 'a_b' ), 'attribute' ],
	[ 'an attribute that is too long', 'x', $attribute( $choice, str_repeat( 'a', 41 ) ), 'attribute' ],
	[ 'an attribute that is no array', 'x', $ok + [ 'attributes' => [ 'a' => 'x' ] ], 'attribute "a"' ],
	[ 'an attribute of an unknown type', 'x', $attribute( [ 'type' => 'select', 'default' => 'a' ] ), 'type' ],
	[ 'an attribute of the old type href', 'x', $attribute( [ 'type' => 'href', 'default' => '' ] ), 'type' ],
	[ 'an attribute with no type', 'x', $attribute( [ 'default' => 'a' ] ), 'type' ],
	[ 'an attribute with no default', 'x', $attribute( [ 'type' => 'string' ] ), '"default"' ],
	[ 'an attribute with a key nobody knows', 'x', $attribute( $choice + [ 'required' => true ] ), '"required"' ],
	[ 'an attribute with a tab of the old schema', 'x', $attribute( $choice + [ 'tab' => 'content' ] ), '"tab"' ],
	[ 'a label of an attribute that is a list', 'x', $attribute( $choice + [ 'label' => [ 'a' ] ] ), '"label"' ],
	[ 'a hint of an attribute that is a number', 'x', $attribute( $choice + [ 'hint' => 5 ] ), '"hint"' ],
	[ 'an attribute called value', 'x', $attribute( $choice, 'value' ), '"value"' ],
	[ 'an attribute called source', 'x', $attribute( $choice, 'source' ), '"source"' ],
	[ 'an attribute called content', 'x', $attribute( $choice, 'content' ), '"content"' ],
	[ 'an attribute called class', 'x', $attribute( $choice, 'class' ), '"class"' ],
	[ 'an attribute called text', 'x', $attribute( $choice, 'text' ), '"text"' ],
	[ 'an attribute called variant', 'x', $attribute( $choice, 'variant' ), '"variant"' ],
	[ 'an attribute called lang', 'x', $attribute( $choice, 'lang' ), '"lang"' ],
	[ 'an attribute called id that is no id', 'x', $attribute( [ 'type' => 'string', 'default' => '' ], 'id' ), '"id"' ],
	[ 'two attributes of the type id', 'x', $ok + [ 'attributes' => [ 'id' => [ 'type' => 'id', 'default' => '' ], 'anchor' => [ 'type' => 'id', 'default' => '' ] ] ], 'attribute "anchor"' ],
	[ 'a choice with no options', 'x', $attribute( [ 'type' => 'choice', 'default' => 'a' ] ), 'options' ],
	[ 'a choice with an empty list of options', 'x', $attribute( [ 'type' => 'choice', 'options' => [], 'default' => 'a' ] ), 'options' ],
	[ 'a choice with options that are a map', 'x', $attribute( [ 'type' => 'choice', 'options' => [ 'k' => 'a' ], 'default' => 'a' ] ), 'options' ],
	[ 'a choice with an option that is a list', 'x', $attribute( [ 'type' => 'choice', 'options' => [ 'a', [ 'b' ] ], 'default' => 'a' ] ), 'options' ],
	[ 'a choice with an option twice', 'x', $attribute( [ 'type' => 'choice', 'options' => [ 'a', 'a' ], 'default' => 'a' ] ), 'options' ],
	[ 'a choice with a null byte in an option', 'x', $attribute( [ 'type' => 'choice', 'options' => [ "a\0", 'b' ], 'default' => 'b', 'class' => false ] ), 'options' ],
	[ 'a choice whose default is not an option', 'x', $attribute( [ 'type' => 'choice', 'options' => [ 'a', 'b' ], 'default' => 'c' ] ), 'default' ],
	[ 'a choice whose default is a bool', 'x', $attribute( [ 'type' => 'choice', 'options' => [ 'a', 'b' ], 'default' => true ] ), 'default' ],
	[ 'a choice whose options are the name of a method that is none', 'x', $attribute( [ 'type' => 'choice', 'options' => 'not a method', 'default' => '' ] ), 'options' ],
	[ 'a choice whose options are a method of a class there is none of', 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\NoSuchClass::options', 'default' => '' ] ), 'options' ],
	[ 'a choice whose options are a method that is not there', 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\Format::nothing', 'default' => '' ] ), 'options' ],
	[ 'a choice whose options are a method that is not public', 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\Components::_ask', 'default' => '' ] ), 'options' ],
	[ 'a choice whose options are a method and whose default is no string', 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\Format::options', 'default' => 5 ] ), 'default' ],
	[ 'a choice whose options are a method and an input of icons', 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\Format::options', 'default' => '', 'input' => 'icons' ] ), 'input' ],
	[ 'a choice whose options are a method of a class that is no one of the feature', 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\Format::options', 'default' => '' ] ), 'options', 'demo', $directory ],
	[ 'a choice whose options are a method of the feature that has no file', 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\Modules\Demo\Missing::options', 'default' => '' ] ), 'options', 'demo', $directory ],
	[ 'options for a string', 'x', $attribute( [ 'type' => 'string', 'options' => [ 'a' ], 'default' => 'a' ] ), 'options' ],
	[ 'min for a string', 'x', $attribute( [ 'type' => 'string', 'min' => 1, 'default' => 'a' ] ), 'min' ],
	[ 'max for a choice', 'x', $attribute( $choice + [ 'max' => 1 ] ), 'max' ],
	[ 'an int whose default is a string', 'x', $attribute( [ 'type' => 'int', 'default' => '3' ] ), 'default' ],
	[ 'an int whose default is below its minimum', 'x', $attribute( [ 'type' => 'int', 'default' => 0, 'min' => 1 ] ), 'default' ],
	[ 'an int whose default is above its maximum', 'x', $attribute( [ 'type' => 'int', 'default' => 5, 'max' => 3 ] ), 'default' ],
	[ 'an int whose minimum is above its maximum', 'x', $attribute( [ 'type' => 'int', 'default' => 2, 'min' => 3, 'max' => 1 ] ), '"min"' ],
	[ 'an int whose maximum is a string', 'x', $attribute( [ 'type' => 'int', 'default' => 2, 'max' => '4' ] ), '"max"' ],
	[ 'a bool whose default is no bool', 'x', $attribute( [ 'type' => 'bool', 'default' => 'no' ] ), 'default' ],
	[ 'a bool whose default is 0', 'x', $attribute( [ 'type' => 'bool', 'default' => 0 ] ), 'default' ],
	[ 'a string whose default is a number', 'x', $attribute( [ 'type' => 'string', 'default' => 5 ] ), 'default' ],
	[ 'a string whose default holds a null byte', 'x', $attribute( [ 'type' => 'string', 'default' => "a\0" ] ), 'default' ],
	[ 'a link whose default is a list', 'x', $attribute( [ 'type' => 'link', 'default' => [] ] ), 'default' ],
	[ 'an image whose default is null', 'x', $attribute( [ 'type' => 'image', 'default' => null ] ), 'default' ],
	[ 'an id whose default is not an id', 'x', $attribute( [ 'type' => 'id', 'default' => 'A b' ], 'anchor' ), 'default' ],
	[ 'responsive for a string', 'x', $attribute( [ 'type' => 'string', 'default' => '', 'responsive' => true ] ), 'responsive' ],
	[ 'responsive for a bool', 'x', $attribute( [ 'type' => 'bool', 'default' => false, 'responsive' => true ] ), 'responsive' ],
	[ 'responsive that is no bool', 'x', $attribute( $choice + [ 'responsive' => 'yes' ] ), 'responsive' ],
	[ 'responsive for a choice that is no class', 'x', $attribute( $choice + [ 'responsive' => true, 'class' => false ] ), 'responsive' ],
	[ 'a class that is no bool', 'x', $attribute( $choice + [ 'class' => 'yes' ] ), '"class"' ],
	[ 'a class for a string', 'x', $attribute( [ 'type' => 'string', 'default' => '', 'class' => true ] ), 'class' ],
	[ 'a class for an int', 'x', $attribute( [ 'type' => 'int', 'default' => 1, 'class' => true ] ), 'class' ],
	[ 'an input for a string', 'x', $attribute( [ 'type' => 'string', 'default' => '', 'input' => 'select' ] ), 'input' ],
	[ 'an input of a switch for a choice', 'x', $attribute( $choice + [ 'input' => 'switch' ] ), 'input' ],
	[ 'an input of a select for a bool', 'x', $attribute( [ 'type' => 'bool', 'default' => false, 'input' => 'select' ] ), 'input' ],
	[ 'icons for a choice that is no alignment', 'x', $attribute( $choice + [ 'input' => 'icons' ] ), 'input' ],
	[ 'a template that is a number', 'x', [ 'label' => 'X', 'template' => 5 ], '"template"' ],
	[ 'a template that is a file with a way out', 'x', [ 'label' => 'X', 'template' => '../x.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file with a way out in the middle', 'x', [ 'label' => 'X', 'template' => 'sub/../../outside.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file with a slash to start', 'x', [ 'label' => 'X', 'template' => '/etc/hosts.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file of a drive', 'x', [ 'label' => 'X', 'template' => 'C:\x.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file with a backslash', 'x', [ 'label' => 'X', 'template' => 'a\b.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file with a null byte', 'x', [ 'label' => 'X', 'template' => "card.tpl\0.txt.tpl" ], '"template"', null, $directory ],
	[ 'a template that is a file with a null byte at the end', 'x', [ 'label' => 'X', 'template' => "card\0.tpl" ], '"template"', null, $directory ],
	[ 'a template that is a file with a space', 'x', [ 'label' => 'X', 'template' => 'a b.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file with a dot to start', 'x', [ 'label' => 'X', 'template' => '.hidden.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file that is not there', 'x', [ 'label' => 'X', 'template' => 'missing.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file that is a directory', 'x', [ 'label' => 'X', 'template' => 'dir.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file and no directory to read it from', 'x', [ 'label' => 'X', 'template' => 'card.tpl' ], '"template"' ],
	[ 'a template that is a file that is too long', 'x', [ 'label' => 'X', 'template' => 'big.tpl' ], '"template"', null, $directory ],
	[ 'a template that is a file with a null byte in it', 'x', [ 'label' => 'X', 'template' => 'nul.tpl' ], '"template"', null, $directory ],
	[ 'a template that is markup with a null byte', 'x', [ 'label' => 'X', 'template' => "<x>\0</x>" ], '"template"' ],
	[ 'a template that is too long', 'x', [ 'label' => 'X', 'template' => str_repeat( '<', 65537 ) ], '"template"' ],
	[ 'an icon that is a string', 'x', $ok + [ 'icon' => 'star' ], '"icon"' ],
	[ 'an icon of an unknown tag', 'x', $ok + [ 'icon' => [ [ 'script', [ 'd' => 'M0 0' ] ] ] ], '"icon"' ],
	[ 'an icon with an attribute the tag does not take', 'x', $ok + [ 'icon' => [ [ 'path', [ 'd' => 'M0 0', 'onload' => '1' ] ] ] ], '"icon"' ],
	[ 'an icon with a path that has a letter it may not', 'x', $ok + [ 'icon' => [ [ 'path', [ 'd' => 'M0 0 <x>' ] ] ] ], '"icon"' ],
	[ 'an icon of too many parts', 'x', $ok + [ 'icon' => array_fill( 0, 13, [ 'path', [ 'd' => 'M0 0' ] ] ) ], '"icon"' ],
	[ 'a name with a line break at its end', "x\n", $ok, 'name' ],
	[ 'an attribute with a line break at its end', 'x', $attribute( $choice, "a\n" ), 'attribute' ],
	[ 'a group with a line break at its end', 'x', $ok + [ 'group' => "g\n" ], '"group"' ],
	[ 'a place with a line break at its end', 'x', $ok + [ 'in' => [ "flow\n" ] ], '"in"' ],
	[ 'a variant with a line break at its end', 'x', $ok + [ 'variants' => [ "soft\n" ] ], 'variant' ],
	[ 'an id as a default that is too long', 'x', $attribute( [ 'type' => 'id', 'default' => str_repeat( 'a', 65 ) ], 'anchor' ), 'default' ],
	[ 'an id as a default with a line break at its end', 'x', $attribute( [ 'type' => 'id', 'default' => "a\n" ], 'anchor' ), 'default' ],
	[ 'a label with a control character', 'x', [ 'label' => "a\x07", 'template' => '<x>' ], '"label"' ],
	[ 'a default with an escape character', 'x', $attribute( [ 'type' => 'string', 'default' => "a\x1B" ] ), 'default' ],
	[ 'an option with a control character', 'x', $attribute( [ 'type' => 'choice', 'options' => [ "a\x1F", 'b' ], 'default' => 'b', 'class' => false ] ), 'options' ],
	[ 'a template named by a file in a directory that has a name like it, with a space', 'x', [ 'label' => 'X', 'template' => 'a b.tpl' ], '"template"', null, $directory ],
	[ 'a template named by a hidden file', 'x', [ 'label' => 'X', 'template' => '.hidden.tpl' ], '"template"', null, $directory ],
	[ 'a method of a class whose name starts with the one of the feature', 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\Modules\DemoX::options', 'default' => '' ] ), 'options', 'demo', $directory ],
	[ 'an icon whose part is a string', 'x', $ok + [ 'icon' => [ 'path' ] ], '"icon"' ],
	[ 'an icon whose part holds a tag that is none and nothing else', 'x', $ok + [ 'icon' => [ [ 'path' ] ] ], '"icon"' ],
	[ 'an icon whose circle has no radius', 'x', $ok + [ 'icon' => [ [ 'circle', [ 'cx' => '1', 'cy' => '1' ] ] ] ], '"icon"' ],
	[ 'an icon whose value is no number', 'x', $ok + [ 'icon' => [ [ 'circle', [ 'cx' => '1', 'cy' => '1', 'r' => 'a' ] ] ] ], '"icon"' ],
	[ 'an icon whose path is too long', 'x', $ok + [ 'icon' => [ [ 'path', [ 'd' => str_repeat( 'M', 513 ) ] ] ] ], '"icon"' ],
	[ 'an icon whose values are too long all together', 'x', $ok + [ 'icon' => array_fill( 0, 5, [ 'path', [ 'd' => str_repeat( 'M', 512 ) ] ] ) ], '"icon"' ],
	[ 'an icon with no part', 'x', $ok + [ 'icon' => [] ], '"icon"' ],
	[ 'a feature that is no slug', 'x', $ok, 'feature', 'Demo Feature' ],
];

$refusedAll = [];
$unnamed = [];
foreach( $refused as $case ) {

	[ $what, $name, $schema, $field ] = $case;
	$answer = \Nino\Components::validate( $name, $schema, $case[4] ?? null, $case[5] ?? null );

	if( is_string( $answer ) === false )
		$refusedAll[] = $what;
	else if( str_contains( strtolower( $answer ), strtolower( $field ) ) === false )
		$unnamed[] = $what. ' ('. $answer. ')';
}
check( 'validate() turns down each of the '. count( $refused ). ' schemas that is wrong - a name, a key, a label, places, a source, a format, attributes, options, defaults, a template, an icon'. ( $refusedAll === [] ? '' : ' - not: '. implode( '; ', $refusedAll ) ), $refusedAll === [] );
check( '...and says which field it is: the message names the key, the attribute or the name'. ( $unnamed === [] ? '' : ' - not: '. implode( '; ', $unnamed ) ), $unnamed === [] );
check( 'a message is short, whatever it was given: a name of a million characters and a null byte are not in it', ( static function(): bool {
	$long = \Nino\Components::validate( str_repeat( "a\0", 500000 ), [ 'label' => 'X' ] );
	$key = \Nino\Components::validate( 'x', [ 'label' => 'X', str_repeat( "k\0", 500000 ) => 1 ] );
	return is_string( $long ) === true && is_string( $key ) === true && strlen( $long ) < 300 && strlen( $key ) < 300 && !str_contains( $long, "\0" ) && !str_contains( $key, "\0" );
} )() );
check( 'a message that names the feature says it', str_contains( (string) \Nino\Components::validate( 'x', [ 'label' => 'X', 'once' => 1 ], 'demo' ), 'feature "demo"' ) === true && str_contains( (string) \Nino\Components::validate( 'x', [ 'label' => 'X', 'once' => 1 ] ), 'feature' ) === false );

$accepted = [
	'the least a schema is: a label and a template' => [ 'x', $ok, null, null ],
	'a container that offers nothing' => [ 'x', $ok + [ 'offers' => [] ], null, null ],
	'all the keys' => [ 'x', [ 'label' => [ 'de_DE' => 'X', 'en_US' => 'X' ], 'group' => 'basis', 'in' => [ 'flow', 'card' ], 'offers' => [ 'flow' ], 'once' => true, 'source' => 'text', 'format' => 'lines', 'variants' => [ 'soft' ],
		'preview' => 'button', 'icon' => [ [ 'path', [ 'd' => 'M0 0L1 1' ] ], [ 'rect', [ 'x' => '1', 'y' => '1', 'width' => '2', 'height' => '2' ] ] ], 'template' => '<x>[[value]]</x>',
		'attributes' => [ 'size' => $choice + [ 'responsive' => true, 'input' => 'segment', 'label' => [ 'de_DE' => 'Größe' ], 'hint' => 'How big' ], 'on' => [ 'type' => 'bool', 'default' => true, 'input' => 'checkbox' ], 'n' => [ 'type' => 'int', 'default' => 2, 'min' => 1, 'max' => 3 ],
			's' => [ 'type' => 'string', 'default' => "a\nb" ], 'l' => [ 'type' => 'link', 'default' => '/x' ], 'i' => [ 'type' => 'image', 'default' => '' ], 'id' => [ 'type' => 'id', 'default' => 'start' ] ] ], null, null ],
	'options that are numbers' => [ 'x', $attribute( [ 'type' => 'choice', 'options' => [ 1, 2, '3' ], 'default' => 2 ] ), null, null ],
	'options that are the name of a method' => [ 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\Format::options', 'default' => '' ] ), null, null ],
	'an alignment as icons' => [ 'x', $attribute( [ 'type' => 'choice', 'options' => [ '', 'left', 'center' ], 'default' => '', 'input' => 'icons' ] ), null, null ],
	'a name of forty characters' => [ str_repeat( 'a', 40 ), $ok, null, null ],
	'a name with digits and hyphens' => [ 'a1-b2-c3', $ok, null, null ],
	'an attribute of lowerCamel' => [ 'x', $attribute( $choice, 'imageAlt' ), null, null ],
	'a template that is a file of the feature' => [ 'x', [ 'label' => 'X', 'template' => 'card.tpl' ], 'demo', $directory ],
	'a template that is a file in a directory of the feature' => [ 'x', [ 'label' => 'X', 'template' => 'sub/inner.tpl' ], 'demo', $directory ],
	'a template that is markup and ends in .tpl' => [ 'x', [ 'label' => 'X', 'template' => '<b>see card.tpl' ], null, null ],
	'a template that holds a textfill and ends in .tpl' => [ 'x', [ 'label' => 'X', 'template' => '[[value]].tpl' ], null, null ],
	'a method of the class of the feature' => [ 'x', $attribute( [ 'type' => 'choice', 'options' => '\Nino\Modules\Demo::options', 'default' => '' ] ), 'demo', $directory ],
];
file_put_contents( $directory. '/Demo.php', '<?php' );
$rejected = [];
foreach( $accepted as $what => [ $name, $schema, $feature, $dir ] )
	if( is_array( \Nino\Components::validate( $name, $schema, $feature, $dir ) ) === false )
		$rejected[] = $what. ': '. \Nino\Components::validate( $name, $schema, $feature, $dir );
check( 'validate() accepts what a schema is allowed to be'. ( $rejected === [] ? '' : ' - not: '. implode( '; ', $rejected ) ), $rejected === [] );
check( 'every picture of a tool is a preview', array_filter( \Nino\Components::PREVIEWS, static fn( string $preview ): bool => is_array( \Nino\Components::validate( 'x', $ok + [ 'preview' => $preview ] ) ) === false ) === [] && \Nino\Components::PREVIEWS === [ 'title', 'text', 'image', 'button', 'block', 'cells', 'table' ] );
$full = \Nino\Components::validate( 'x', $accepted['all the keys'][1] );
check( 'the clean schema of a schema with every key is every key: group, places, offers, once, source, format, preview, icon, variants - regular first - attributes in their order, and the template',
	is_array( $full ) === true && array_keys( $full ) === [ 'label', 'group', 'in', 'offers', 'once', 'source', 'format', 'preview', 'icon', 'variants', 'attributes', 'template' ] && $full['group'] === 'basis' && $full['in'] === [ 'flow', 'card' ] && $full['offers'] === [ 'flow' ] && $full['once'] === true
	&& $full['format'] === 'lines' && $full['preview'] === 'button' && $full['icon'] === $accepted['all the keys'][1]['icon'] && $full['variants'] === [ 'regular', 'soft' ] && array_keys( $full['attributes'] ) === [ 'size', 'on', 'n', 's', 'l', 'i', 'id' ]
	&& $full['attributes']['size'] === [ 'type' => 'choice', 'label' => [ 'de_DE' => 'Größe' ], 'hint' => 'How big', 'input' => 'segment', 'class' => true, 'responsive' => true, 'options' => [ 'a', 'b' ], 'default' => 'a' ]
	&& $full['attributes']['n'] === [ 'type' => 'int', 'label' => 'n', 'hint' => '', 'class' => false, 'min' => 1, 'max' => 3, 'default' => 2 ] && $full['template'] === '<x>[[value]]</x>' );
check( 'a file is read: the template of the clean schema is its markup, and the feature has no part in the schema', \Nino\Components::validate( 'x', [ 'label' => 'X', 'template' => 'card.tpl' ], 'demo', $directory )['template'] === '<div class="[[class]]">[[content]]</div>'
	&& \Nino\Components::validate( 'x', [ 'label' => 'X', 'template' => 'sub/inner.tpl' ], 'demo', $directory )['template'] === '<inner>' && array_key_exists( 'feature', \Nino\Components::validate( 'x', $ok, 'demo' ) ) === false );
check( 'a file that is a link out of the directory is none of it', $linked === false || is_string( \Nino\Components::validate( 'x', [ 'label' => 'X', 'template' => 'link.tpl' ], 'demo', $directory ) ) === true );
check( 'a file that is changed is another version: the template in the schema is what the file held when it was read', ( static function() use ( $directory ): bool {
	$before = \Nino\Components::validate( 'x', [ 'label' => 'X', 'template' => 'card.tpl' ], 'demo', $directory );
	file_put_contents( $directory. '/card.tpl', '<section>[[content]]</section>' );
	$after = \Nino\Components::validate( 'x', [ 'label' => 'X', 'template' => 'card.tpl' ], 'demo', $directory );
	file_put_contents( $directory. '/card.tpl', '<div class="[[class]]">[[content]]</div>' );
	return $before['template'] !== $after['template'] && $after['template'] === '<section>[[content]]</section>';
} )() );
check( 'the clean schema of a choice whose options are numbers has them as strings, the default as a string', \Nino\Components::validate( 'x', $attribute( [ 'type' => 'choice', 'options' => [ 1, 2, '3' ], 'default' => 2 ] ) )['attributes']['a']['options'] === [ '1', '2', '3' ]
	&& \Nino\Components::validate( 'x', $attribute( [ 'type' => 'choice', 'options' => [ 1, 2, '3' ], 'default' => 2 ] ) )['attributes']['a']['default'] === '2' );
check( 'a schema that was checked is the schema that is checked again - with a file for the template read, and the same schema comes out; a method of the feature is the one thing that needs its directory', ( static function() use ( $accepted ): bool {
	foreach( $accepted as $what => [ $name, $schema, $feature, $dir ] ) {
		if( str_starts_with( $what, 'a method of the class of the feature' ) === true )
			continue;
		$clean = \Nino\Components::validate( $name, $schema, $feature, $dir );
		if( \Nino\Components::validate( $name, $clean ) !== $clean )
			return false;
	}
	return true;
} )() );

echo "\n";


// --- Options by a method ------------------------------------------------------------

echo "Options that a method answers\n";

$optioned = \Nino\Components::components( $appData )['optioned']['attributes']['as'];
check( 'components() hands out the answer in the place of the name: the values and the labels', is_array( $optioned['options'] ) === true && in_array( '', $optioned['options'], true ) === true && in_array( 'number', $optioned['options'], true ) === true
	&& array_keys( $optioned['optionLabels'] ) === array_map( 'strval', array_keys( $optioned['optionLabels'] ) ) && $optioned['type'] === 'choice' );
check( 'the register itself holds the name of the method, which the version is made of', $appData[\Nino\Components::REGISTRY]['optioned']['attributes']['as']['options'] === '\Nino\Format::options' );
check( 'a value of the answer is a value, one outside it is the default', $draw( '[optioned as="number"]' ) === '<u data-as="number"></u>' && $draw( '[optioned as="nope"]' ) === '<u data-as=""></u>' && $draw( '[optioned]' ) === '<u data-as=""></u>' );

class MarkupComponentsOptions {
	public static function words( array &$appData, string $attribute ): array {
		return [ 'one' => 'One', 'two' => [ 'en_US' => 'Two' ] ];
	}
	public static function bad( array &$appData, string $attribute ): array {
		return [ 'Bad Word' => 'Bad', 'ok' => 'Ok' ];
	}
	public static function labels( array &$appData, string $attribute ): array {
		return [ 'one' => [] ];
	}
	public static function nothing( array &$appData, string $attribute ): string {
		return 'no';
	}
	public static function throws( array &$appData, string $attribute ): array {
		throw new \LogicException( 'no' );
	}
	public static function empty( array &$appData, string $attribute ): array {
		return [];
	}
	public static function wild( array &$appData, string $attribute ): array {
		return [ 'a"><script>alert(1)</script>' => 'Wild', 'ok' => 'Ok' ];
	}
	public static function numbers( array &$appData, string $attribute ): array {
		return [ 10 => 'Ten', 20 => 'Twenty' ];
	}
}
foreach( [ 'words', 'bad', 'labels', 'nothing', 'throws', 'empty', 'numbers' ] as $method )
	\Nino\Components::addComponent( $appData, 'by-'. $method, [ 'label' => 'By', 'attributes' => [ 'x' => [ 'type' => 'choice', 'options' => '\MarkupComponentsOptions::'. $method, 'default' => 'one' ] ], 'template' => '<b>[[class]]</b>' ] );
\Nino\Components::addComponent( $appData, 'by-wild', [ 'label' => 'By', 'attributes' => [ 'x' => [ 'type' => 'choice', 'options' => '\MarkupComponentsOptions::wild', 'default' => 'ok', 'class' => false ] ], 'template' => '<u data-x="[[x]]"></u>' ] );
$said = $logged( static function() use ( $draw, &$out ): void {
	$out = $draw( '[by-words x="two"][by-words x="three"][by-bad x="ok"][by-labels x="one"][by-nothing x="Anything"][by-throws x="y"][by-empty x="z"]' );
} );
check( 'a method answers the options: a value of the answer is a class and one outside is the default - an answer that is a map of words with a bad one, labels that are none, a string, a throw is a warning, and the attribute is a string',
	$out === '<b>by-words by-words--x-two</b><b>by-words</b><b>by-bad</b><b>by-labels</b><b>by-nothing</b><b>by-throws</b><b>by-empty</b>' && count( $said ) === 4 );
check( 'the values of the answer are strings, whatever the keys of the map are: the number 20 is the option "20"', $draw( '[by-numbers x="20"][by-numbers x="10"][by-numbers x="30"]' ) === '<b>by-numbers by-numbers--x-20</b><b>by-numbers by-numbers--x-10</b><b>by-numbers</b>' );
check( '...asked once in a request: a second draw says nothing and the answer stays', $logged( static function() use ( $draw ): void { $draw( '[by-bad x="ok"][by-words x="two"]' ); } ) === [] );
$one = \Nino\Components::components( $appData );
check( 'components() hands a method that could not be read out as a string - without options, input and class', $one['by-bad']['attributes']['x']['type'] === 'string' && array_key_exists( 'options', $one['by-bad']['attributes']['x'] ) === false && $one['by-bad']['attributes']['x']['class'] === false
	&& $one['by-nothing']['attributes']['x']['type'] === 'string' && $one['by-throws']['attributes']['x']['type'] === 'string' && $one['by-labels']['attributes']['x']['type'] === 'string' && $one['by-numbers']['attributes']['x']['options'] === [ '10', '20' ]
	&& $one['by-words']['attributes']['x']['options'] === [ 'one', 'two' ] && $one['by-words']['attributes']['x']['optionLabels']['two'] === [ 'en_US' => 'Two' ] && $one['by-empty']['attributes']['x']['options'] === [] );

echo "\n";


// --- Depth and hand-built trees -------------------------------------------------------

echo "Depth, and trees nobody read\n";

$seen->exchangeArray( [] );
\Nino\Components::render( $appData, [ [ 'n' => 'probe-echo', 's' => '/project/company/general/name', 'a' => [], 'c' => null, 'o' => 0 ] ] );
\Nino\Components::render( $appData, [ [ 'n' => 'probe-echo', 's' => '/project/company/general/name', 'a' => [], 'c' => null, 'o' => 0 ] ], [ 'template' => [ 'x' ], 'scope' => 5, 'place' => null, 'element' => 'x', 'depth' => 'deep' ] );
\Nino\Components::render( $appData, [ [ 'n' => 'probe-echo', 's' => '/project/company/general/name', 'a' => [], 'c' => null, 'o' => 0 ] ], [ 'depth' => -4, 'element' => [ 'x' ] ] );
check( 'a context with nothing in it is the page: no template, no scope, the place page, no element, depth 0 - and what is of the wrong type is the same', $seen[0][2] === [ 'template' => '', 'scope' => '', 'place' => 'page', 'element' => null, 'fills' => [], 'depth' => 0 ]
	&& $seen[1][2] === $seen[0][2] && $seen[2][2] === [ 'template' => '', 'scope' => '', 'place' => 'page', 'element' => [ 'x' ], 'fills' => [], 'depth' => 0 ] );
check( 'a depth below nothing is none: the limit is the same', substr_count( \Nino\Components::render( $appData, [ [ 'n' => 'card', 's' => '', 'a' => [], 'c' => [ [ 'n' => 'card', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ] ], 'o' => 0 ] ], [ 'depth' => -100 ] ), '<article' ) === 2 );
check( 'a text is drawn as it is: markup, quotes, brackets and all - it is the template\'s own', \Nino\Components::render( $appData, [ [ 't' => '<b class="x">a & [b] [[c]] \\[d]</b>' ] ] ) === '<b class="x">a & [b] [[c]] \\[d]</b>' );

$deep = static function( int $levels ): array {
	$node = [ 't' => 'x' ];
	$nodes = [ $node ];
	for( $level = 0; $level < $levels; $level++ )
		$nodes = [ [ 'n' => 'card', 's' => '', 'a' => [], 'c' => $nodes, 'o' => 0 ] ];
	return $nodes;
};
$out = \Nino\Components::render( $appData, $deep( 16 ) );
check( 'a tree can be as deep as the parser lets it be: sixteen containers in each other, the text in the innermost one', substr_count( $out, '<article' ) === 16 && str_contains( $out, 'x' ) === true );
$out = \Nino\Components::render( $appData, $deep( 17 ) );
check( 'what stands one deeper is not drawn: the seventeenth container is, the text in it is not', substr_count( $out, '<article' ) === 17 && str_contains( $out, 'x' ) === false );
$out = \Nino\Components::render( $appData, $deep( 40 ) );
check( 'and nothing is thrown for a tree that is deeper still: the containers below the seventeenth are not drawn', substr_count( $out, '<article' ) === 17 && str_contains( $out, 'x' ) === false );
check( 'a depth below nothing is none: a tree of 20 levels is drawn to the seventeenth', substr_count( \Nino\Components::render( $appData, $deep( 20 ), [ 'depth' => -5 ] ), '<article' ) === 17 );
check( 'the depth of the context counts: a draw that starts at 16 draws one level, one that starts above draws nothing', substr_count( \Nino\Components::render( $appData, $deep( 3 ), [ 'depth' => 16 ] ), '<article' ) === 1 && \Nino\Components::render( $appData, $deep( 3 ), [ 'depth' => 17 ] ) === '' && \Nino\Components::render( $appData, [ [ 't' => 'a' ] ], [ 'depth' => 17 ] ) === '' );
$source = str_repeat( '[card]', 40 ). 'x'. str_repeat( '[/card]', 40 );
$out = $draw( $source, $page, $notes );
check( 'a document of forty levels: the parser stops at sixteen and notes it, the rest is a sibling, and the drawing ends', substr_count( $out, '<article' ) === 40 && array_column( $notes, 'code' )[0] === 'depth' && str_contains( $out, 'x' ) === true );
$junk = [ 'a', 5, null, [ 't' => [ 'x' ] ], [ 't' => 5 ], [ 'f' => [ 'k' ] ], [ 'f' => '' ], [ 'n' => 5 ], [ 'n' => [ 'card' ] ], [ 'x' => 1 ], [], [ 'n' => 'card' ], [ 'n' => 'card', 'a' => 'x', 's' => 5, 'c' => 'y' ], [ 'n' => 'section-title', 's' => [ 'x' ], 'a' => [ 'text' => 5 ] ],
	[ 'n' => 'section-title', 'a' => [ 'text' => 'ok', 'tag' => [ 'h1' ] ], 'c' => 5 ], [ 'n' => 'card', 'c' => [ 'a', [ 'n' => 'text', 'a' => [ 'text' => 'in' ] ] ] ] ];
$said = $logged( static function() use ( &$appData, $junk, &$out ): void {
	$out = \Nino\Components::render( $appData, $junk, [ 'template' => [ 'x' ], 'scope' => 5, 'place' => null, 'element' => 'x', 'depth' => 'deep' ] );
} );
check( 'a tree built by hand with what no parser makes - nodes that are no array, keys that are no string, a context of the wrong types - is drawn as far as it makes sense and never throws',
	$out === '<article class="card"></article><article class="card"></article><h2 class="section-title">ok</h2><article class="card"><p class="text">in</p></article>' && $said === [] );

echo "\n";


// --- Values that are never read as markup - a text, an attribute, a link --------------

echo "Safety: a value is a value\n";

$payloads = [
	'a call'						=> '[template /templates/x]',
	'a textfill'				=> '[[/project/company/general/name]]',
	'an injection'			=> '"><script>alert(1)</script>',
	'a bracket'					=> ']',
	'an open bracket'		=> '[',
	'a call that is open'	=> '[card ',
	'a null byte'				=> "a\0b",
	'a card with an end'	=> '[card]x[/card]',
	'a token'						=> '[[content]] [[class]] [[value]] [[id]] [[lang]] [[content:card-title]]',
	'a backslash and a quote'	=> '\\" \\[card]',
	'a line break'			=> "a\nb [card\n]",
	'a byte that is no utf-8'	=> "a\xFFb",
];
$megabyte = str_repeat( 'ab[card]\\"]]', 87382 );
$payloads['one megabyte'] = $megabyte;

\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static function( array $content ) use ( $payloads ): array {
	foreach( array_keys( $payloads ) as $index => $what )
		$content[ '[[/project/company/payload/p'. $index. ']]' ] = $payloads[$what];
	return $content;
} );

$allSame = [ 'key' => [], 'fixed' => [], 'attribute' => [], 'notice' => [] ];

foreach( array_keys( $payloads ) as $index => $what ) {

	$value = $payloads[$what];

	// A textfill of the site in a value is put in, and what is put in is a text like the rest
	$shown = str_replace( '[[/project/company/general/name]]', 'ACME', $value );
	$expected = '<p class="text">'. \Nino\Components::escape( $shown ). '</p>';

	unset( $appData[\Nino\Components::NOTICED] );
	ninoWarnings();

	$text = \Nino\Components::render( $appData, [ [ 'n' => 'text', 's' => '/project/company/payload/p'. $index, 'a' => [], 'c' => null, 'o' => 0 ] ] );
	$fixed = \Nino\Components::render( $appData, [ [ 'n' => 'text', 's' => '', 'a' => [ 'text' => $value ], 'c' => null, 'o' => 0 ] ] );
	$attribute = \Nino\Components::render( $appData, [ [ 'n' => 'knob', 's' => '', 'a' => [ 'label' => $value ], 'c' => [ [ 't' => 'in' ] ], 'o' => 0 ] ] );

	$allSame['key'][$what] = $text === $expected;
	$allSame['fixed'][$what] = $fixed === $expected;
	$allSame['attribute'][$what] = $attribute === '<i class="knob" t="p" f="0" n="3" l="'. \Nino\Components::escape( $shown ). '" h="" p="" a="">in</i>';
	$allSame['notice'][$what] = ninoWarnings() === [];
}
$bad = static fn( array $results ): string => implode( ', ', array_keys( array_filter( $results, static fn( bool $same ): bool => $same === false ) ) );
check( 'a text of a key is its text, escaped, whatever it holds: a call, a textfill, a script, a bracket, a null byte, a token, a megabyte - never a call, and the log has nothing to say', $bad( $allSame['key'] ) === '' && $bad( $allSame['notice'] ) === '' );
check( 'a fixed text is the same', $bad( $allSame['fixed'] ) === '' );
check( 'the value of a string attribute is the same - escaped into the template and no token of it is filled', $bad( $allSame['attribute'] ) === '' );
check( 'a [ is a [: no &#91; in what is drawn, and no ] as an entity', ( static function() use ( $draw ): bool {
	$out = $draw( '[text text="a [b] [[c]]"][text /project/company/payload/p0]' );
	return !str_contains( $out, '&#91;' ) && !str_contains( $out, '&#93;' ) && str_contains( $out, 'a [b] [[c]]' );
} )() );
$out = $draw( '[text text="[template /templates/x] [[/project/company/general/name]] \"><script>alert(1)</script>"]' );
check( 'a text read from a document is a text, a call in it is a text and the quote that ends it is its own - the textfill of the site is put in', $out === '<p class="text">[template /templates/x] ACME &quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;</p>' );
check( 'a text that is a key has the fills in it put in, as a textfill node does', $draw( '[text /template/page-x/hero/nested]' ) === '<p class="text">Von ACME</p>' && $draw( '[[/template/page-x/hero/nested]]' ) === 'Von ACME' );
$same = true;
foreach( [ 'name', 'self', 'chain0', 'chain1', 'macro', 'tag', 'nobody', 'blank', 'tokens' ] as $word ) {
	$key = '/project/company/general/'. $word;
	$resolved = \Nino\Html::resolveTextfill( $appData, $key );
	$same = $same && ( $resolved === null ? $draw( '[text '. $key. ']' ) === '' : $draw( '[text '. $key. ']' ) === ( $resolved === '' ? '' : '<p class="text">'. \Nino\Components::escape( html_entity_decode( $resolved, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ). '</p>' ) );
}
check( 'the text of a key has its fills put in as resolveTextfill() puts them in: known, unknown, nested, one that refers to itself, one that is empty, one that is none', $same === true && $draw( '[text /project/company/general/chain0]' ) === '<p class="text">a ACME-end b [[/nope/a/b/c]]</p>' );
\Nino\Html::addFills( $appData, array_combine( array_map( static fn( int $i ): string => '/project/company/chain/c'. $i, range( 0, 13 ) ), array_map( static fn( int $i ): string => $i === 13 ? 'bottom' : '[['. '/project/company/chain/c'. ( $i + 1 ). ']]', range( 0, 13 ) ) ), '*' );
check( '...and at most ten passes: a chain of fourteen is cut and ends, one of four is whole', $draw( '[text /project/company/chain/c9]' ) === '<p class="text">bottom</p>' && str_contains( $draw( '[text /project/company/chain/c0]' ), '[[/project/company/chain/c' ) === true && str_contains( $draw( '[text /project/company/chain/c0]' ), 'bottom' ) === false );
check( 'what is put in is a text: a call in a fill is the characters of a call, in a key, a fixed text and an attribute alike', $draw( '[text /project/company/general/macro][text text="[[/project/company/general/macro]]"]' ) === str_repeat( '<p class="text">[template /templates/x] und [probe-echo]</p>', 2 )
	&& str_contains( $draw( '[knob label="[[/project/company/general/macro]]"][/knob]' ), 'l="[template /templates/x] und [probe-echo]"' ) === true && str_contains( $draw( '[knob label="[[/project/company/general/tag]]"][/knob]' ), 'l="Tag &lt;b&gt;in&lt;/b&gt; &quot;text&quot;"' ) === true );
check( 'a fixed text and a string attribute have their fills put in: a key nobody has a text for is nothing, a key without a slash is no fill of the site, and the text is escaped as a whole',
	$draw( '[text text="a [[/project/company/general/name]] b [[/project/company/general/nobody]] c [[title]] d"]' ) === '<p class="text">a ACME b  c [[title]] d</p>' && $draw( '[text text="&lt;[[/project/company/general/tag]]"]' ) === '<p class="text">&lt;Tag &lt;b&gt;in&lt;/b&gt; &quot;text&quot;</p>' );
check( 'the fill of a field of an element is not put in: the text of the editor is what the field says', $draw( '[text .title]', [ 'template' => 'page-x', 'element' => [ 'uri' => '/p', 'id' => 0, 'element' => [ 'title' => '[[/project/company/general/name]]' ], 'model' => [ 'title' => [ 'type' => 'string' ] ] ] ] ) === '<p class="text">[[/project/company/general/name]]</p>'
	&& $draw( '[[title]]', [ 'template' => 'page-x', 'element' => [ 'uri' => '/p', 'id' => 0, 'element' => [ 'title' => '[[/project/company/general/name]]' ], 'model' => [ 'title' => [ 'type' => 'string' ] ] ] ] ) === '[[/project/company/general/name]]' );
check( 'a token in a value is no token: the value of a text that is [[content]] stays, with a child and a class around it', $draw( '[titled text="[[content]][[class]]"]kid[/titled]' ) === '<h title="a &quot;b&quot; &lt;c&gt; &amp;">[[content]][[class]]|kid</h>' && $draw( '[titled text="x" note="[[value]] \"q\""][/titled]' ) === '<h title="[[value]] &quot;q&quot;">x|</h>' );
check( 'a null byte in a value: drawn as it is, escaped; in a name of a call or a source: a call nobody has, nothing is thrown', ( static function() use ( &$appData ): bool {
	$out = \Nino\Components::render( $appData, [ [ 'n' => "card\0", 's' => '', 'a' => [], 'c' => [ [ 't' => 'x' ] ], 'o' => 0 ], [ 'n' => 'text', 's' => "/a/b\0/c", 'a' => [], 'c' => null, 'o' => 0 ], [ 'n' => 'text', 's' => "~a\0", 'a' => [], 'c' => null, 'o' => 0 ] ], [ 'template' => 'page-x', 'scope' => 'a' ] );
	return $out === '';
} )() );

$hrefs = [ 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', " javascript:alert(1)", "java\tscript:alert(1)", "java\nscript:alert(1)", "\x01javascript:alert(1)", "java\0script:alert(1)", 'data:text/html;base64,PHNjcmlwdD4=', 'vbscript:x',
	"\x1Fjavascript:alert(1)", "\x7Fjavascript:alert(1)", "\x0Cjavascript:alert(1)", "\x1Bjavascript:alert(1)",
	'//evil.test/x', '/\\evil.test', '\\\\evil.test', '&#106;avascript:alert(1)', '&#x6A;avascript:alert(1)', 'javascript&colon;alert(1)', 'file:///etc/passwd', 'blob:x', 'ftp://x.test', 'view-source:x' ];
$hrefOk = true;
$hrefSaid = [];
foreach( $hrefs as $index => $href ) {
	\Nino\Html::addFills( $appData, [ '/project/company/hrefs/h'. $index => $href ], '*' );
	$viaAttribute = \Nino\Components::render( $appData, [ [ 'n' => 'button', 's' => '', 'a' => [ 'text' => 'x', 'href' => $href ], 'c' => null, 'o' => 0 ] ] );
	$viaKey = \Nino\Components::render( $appData, [ [ 'n' => 'go', 's' => '/project/company/hrefs/h'. $index, 'a' => [], 'c' => null, 'o' => 0 ] ] );
	$hrefOk = $hrefOk && $viaAttribute === '<a class="button" href="">x</a>';
	if( $viaAttribute !== '<a class="button" href="">x</a>' )
		$hrefSaid[] = $href;
	$hrefOk = $hrefOk && $viaKey === '';
}
check( 'a link that could be a script - a scheme that is none of http, https, mailto and tel, hidden by a break, a tab, a control character, an entity, a protocol-relative one - is no address: the attribute is empty and a component of a link source is not drawn', $hrefOk === true && $hrefSaid === [] );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static fn( array $content ): array => $content + [ '[[/project/company/payload/js]]' => 'javascript:alert(1)', '[[/project/company/payload/ok]]' => 'https://a.test/x?a=1&amp;b=2#c' ] );
check( 'the text of a key that is such an address is none either; a good one is escaped once', $draw( '[go /project/company/payload/js]' ) === '' && $draw( '[go /project/company/payload/ok]' ) === '<a href="https://a.test/x?a=1&amp;b=2#c">go</a>' );
check( 'the schemes a link may have, in whatever case, and a path, a query, an anchor, a relative address and mail', ( static function() use ( &$appData ): bool {
	foreach( [ 'https://a.test/' => 'https://a.test/', 'HTTP://a.test/' => 'HTTP://a.test/', 'mailto:a@b.test' => 'mailto:a@b.test', 'tel:+4912345' => 'tel:+4912345', '/kontakt?a=1&b=2' => '/kontakt?a=1&amp;b=2', '#top' => '#top', 'kontakt' => 'kontakt', '?a=1' => '?a=1', '../x' => '../x', '/a"b' => '/a&quot;b' ] as $href => $written )
		if( \Nino\Components::render( $appData, [ [ 'n' => 'button', 's' => '', 'a' => [ 'text' => 'x', 'href' => $href ], 'c' => null, 'o' => 0 ] ] ) !== '<a class="button" href="'. $written. '">x</a>' )
			return false;
	return true;
} )() );
check( 'a line break or a tab in an address is nothing: a browser drops it too', $draw( '[button text="x" href="/kon\ttakt"]' ) !== '' && \Nino\Components::render( $appData, [ [ 'n' => 'button', 's' => '', 'a' => [ 'text' => 'x', 'href' => "/kon\ttakt\n" ], 'c' => null, 'o' => 0 ] ] ) === '<a class="button" href="/kontakt">x</a>'
	&& \Nino\Components::render( $appData, [ [ 'n' => 'button', 's' => '', 'a' => [ 'text' => 'x', 'href' => " /kontakt " ], 'c' => null, 'o' => 0 ] ] ) === '<a class="button" href="/kontakt">x</a>' );
check( 'an address with its entities is read as the address it is, and escaped once', $draw( '[button text="x" href="/a?b=1&amp;c=2"]' ) === '<a class="button" href="/a?b=1&amp;c=2">x</a>' && $draw( '[button text="x" href="/a?b=1&c=2"]' ) === '<a class="button" href="/a?b=1&amp;c=2">x</a>' );
check( 'a link and an image that have a default have it where the call says nothing or only the bare word - and none where it says something that is no address or nothing', $draw( '[linked]' ) === '<a href="/start" data-src="/default-image"></a>' && $draw( '[linked href="javascript:x()" src="x"]' ) === '<a href="" data-src=""></a>'
	&& $draw( '[linked href="" src=""]' ) === '<a href="" data-src=""></a>' && $draw( '[linked href src]' ) === '<a href="/start" data-src="/default-image"></a>' && $draw( '[linked href="/b" src="/c"]' ) === '<a href="/b" data-src="/c"></a>' );
check( 'a link that is a key: its text, and a link with a key that is no text is none', $draw( '[button text="x" href="~link"]', [ 'template' => 'page-x', 'scope' => 'hero' ] ) === '<a class="button" href="/kontakt?a=1&amp;b=2">x</a>'
	&& $draw( '[button text="x" href="~nothing"]', [ 'template' => 'page-x', 'scope' => 'hero' ] ) === '<a class="button" href="">x</a>' && $draw( '[button text="x" href="~evil"]', [ 'template' => 'page-x', 'scope' => 'hero' ] ) === '<a class="button" href="">x</a>' );
check( 'a link that is a field of the element', $draw( '[button text="x" href=".target"]', [ 'template' => 'page-x', 'element' => $element ] ) === '<a class="button" href="/blog/x?a=1&amp;b=2">x</a>' && $draw( '[button text="x" href=".target"]' ) === '<a class="button" href="">x</a>' );
check( 'an image attribute is the reference, escaped: a key of the slot, a field of the element, anything else is none', $draw( '[knob pic="~photo"][/knob]', [ 'template' => 'page-x', 'scope' => 'hero' ] ) === '<i class="knob" t="p" f="0" n="3" l="L" h="" p="/template/page-x/hero/photo" a=""></i>'
	&& str_contains( $draw( '[knob pic=".photo"][/knob]', [ 'template' => 'page-x', 'element' => $element ] ), 'p="a.jpg"' ) === true && str_contains( $draw( '[knob pic="x.jpg"][/knob]' ), 'p=""' ) === true && str_contains( $draw( '[knob pic="/a\"b"][/knob]' ), 'p="/a&quot;b"' ) === true );

$said = $logged( static function() use ( &$appData ): void {
	\Nino\Components::render( $appData, [ [ 'n' => str_repeat( 'x', 1000000 ), 's' => '', 'a' => [], 'c' => null, 'o' => 0 ] ] );
	foreach( range( 1, 100 ) as $index )
		\Nino\Components::render( $appData, [ [ 'n' => 'unknown'. $index, 's' => '', 'a' => [], 'c' => null, 'o' => 0 ] ] );
} );
check( 'a page of names nobody has is not a log of its own: a line each for the first twenty only, and no name is longer in the line than a name may be', count( $said ) === 20 && max( array_map( 'strlen', $said ) ) < 250 );
$said = $logged( static function() use ( &$appData ): void {
	\Nino\Components::render( $appData, [ [ 'n' => 'text', 's' => '~'. str_repeat( 'x', 1000000 ), 'a' => [], 'c' => null, 'o' => 0 ] ], [ 'template' => 'page-x' ] );
} );
check( 'a source of a million characters that has no scope to be read in is one line, and a short one', count( $said ) === 1 && strlen( $said[0] ) < 300 );

echo "\n";


// --- Links to a key, to a page, with a fill in the source; what an option and a template hold ------------

echo "Links, pages and the words of the site in a value\n";

$button = static fn( string $href, array $context = [ 'template' => 'page-x' ] ): string => $draw( '[button text="x" href="'. $href. '"]', $context );
check( 'a link attribute that is a key is looked up, as the first word of a call is: the address of a page of the site has the directory in front, an ordinary key is its text, and a key nobody has is the address it is',
	$button( '/_nino/webpage/contact/uri' ) === '<a class="button" href="/shop/kontakt">x</a>' && $button( '/project/company/general/url' ) === '<a class="button" href="https://example.test/?q=1&amp;r=2">x</a>'
	&& $button( '/x/y/z/w' ) === '<a class="button" href="/x/y/z/w">x</a>' && $button( '/_nino/webpage/ext/uri' ) === '<a class="button" href="https://ext.test/x">x</a>' && $button( '/project/company/general/mail' ) === '<a class="button" href="mailto:a@example.test">x</a>' );
check( 'a link attribute that is an address has the fills of the site put in: https://x.test/ACME', $button( 'https://x.test/[[/project/company/general/name]]' ) === '<a class="button" href="https://x.test/ACME">x</a>' && $button( 'https://x.test/[[/project/company/general/nobody]]' ) === '<a class="button" href="https://x.test/">x</a>' );
check( 'an image attribute that is a key is the reference as it is written, and a link that is a key with a text that is a script is none', \Nino\Components::render( $appData, [ [ 'n' => 'linked', 's' => '', 'a' => [ 'src' => '/project/company/general/name' ], 'c' => null, 'o' => 0 ] ] ) === '<a href="/start" data-src="/project/company/general/name"></a>'
	&& $button( '/template/page-x/hero/evil' ) === '<a class="button" href="">x</a>' );

$page1 = static fn( string $path ): string => $button( 'page:'. $path );
check( 'page:/contact is the address of that page in the language of the page, with the directory of the site in front - the way a page of a module writes a link to one of its own',
	$page1( '/contact' ) === '<a class="button" href="/shop/kontakt">x</a>' && $page1( '/home' ) === '<a class="button" href="/shop/">x</a>' );
$appData['./nino/locales/current'] = 'en_US';
check( '...in the other language the address of the other language, and a page that only the native language has is the native one', $page1( '/contact' ) === '<a class="button" href="/shop/contact">x</a>' && $page1( '/only' ) === '<a class="button" href="/shop/only-de">x</a>' );
$appData['./nino/locales/current'] = 'de_DE';
$appData['/nino/dir'] = '';
check( '...and with no directory it is the address of the page', $page1( '/contact' ) === '<a class="button" href="/kontakt">x</a>' && $page1( '/home' ) === '<a class="button" href="/">x</a>' );
$appData['/nino/dir'] = '/shop';
$noPage = true;
foreach( [ '/unknown', '/blog', '/send', '/a/../b', '/odd..name', '/../contact', '../contact', '/contact/..', 'https://x.test', 'javascript:alert(1)', '//x.test', '/a b', '', '/contact/', '/con\\tact', '?x=1', '#a' ] as $path )
	$noPage = $noPage && $page1( $path ) === '<a class="button" href="">x</a>';
foreach( [ "page:/contact\n", 'page:/contact"', "page:/contact\0" ] as $href )
	$noPage = $noPage && \Nino\Components::render( $appData, [ [ 'n' => 'button', 's' => '', 'a' => [ 'text' => 'x', 'href' => $href ], 'c' => null, 'o' => 0 ] ] ) === '<a class="button" href="">x</a>';
check( 'a page nobody routes, one that is a subtree or an endpoint, a path that leaves the site, a scheme, a query, an anchor and anything that is no name of a page are no address', $noPage === true );
check( 'the address of a page is written escaped, and the name of a page may be an entity: page:/&#99;ontact is /contact', $page1( '/quoted' ) === '<a class="button" href="/shop/q&quot;x">x</a>' && $page1( '/&#99;ontact' ) === '<a class="button" href="/shop/kontakt">x</a>' && $page1( '/&amp;' ) === '<a class="button" href="">x</a>' );
foreach( [ 'GET:///evil.test' => '/evil', 'GET://\\evil.test' => '/evil2', 'GET://_api/x' => '/api', 'GET://.hidden' => '/hidden', 'GET://a b' => '/spaced', "GET://tab\there" => '/tabbed' ] as $routeKey => $uri )
	foreach( [ '', '/shop' ] as $dir ) {
		$appData['/nino/dir'] = $dir;
		$noPage = $noPage && $page1( $uri ) === '<a class="button" href="">x</a>';
	}
$appData['/nino/dir'] = '/shop';
check( 'a route that is no address of the site is no address: //x.test, /\\x.test, below /_ and /., with a space or a tab - with the directory of the site and without it', $noPage === true );
check( 'page:/contact keeps what follows the name of the page: an anchor, a query, both - escaped, and a space, a tab or a control character in it is no address',
	$page1( '/contact#form' ) === '<a class="button" href="/shop/kontakt#form">x</a>' && $page1( '/contact?a=1' ) === '<a class="button" href="/shop/kontakt?a=1">x</a>' && $page1( '/contact?a=1&b=2#f' ) === '<a class="button" href="/shop/kontakt?a=1&amp;b=2#f">x</a>'
	&& $page1( '/unknown#form' ) === '<a class="button" href="">x</a>' && $page1( '/contact#a b' ) === '<a class="button" href="">x</a>' && $page1( '/contact?#' ) === '<a class="button" href="/shop/kontakt?#">x</a>'
	&& \Nino\Components::render( $appData, [ [ 'n' => 'button', 's' => '', 'a' => [ 'text' => 'x', 'href' => 'page:/contact#a"b' ], 'c' => null, 'o' => 0 ] ] ) === '<a class="button" href="/shop/kontakt#a&quot;b">x</a>'
	&& \Nino\Components::render( $appData, [ [ 'n' => 'button', 's' => '', 'a' => [ 'text' => 'x', 'href' => "page:/contact#a\tb" ], 'c' => null, 'o' => 0 ] ] ) === '<a class="button" href="">x</a>'
	&& \Nino\Components::render( $appData, [ [ 'n' => 'button', 's' => '', 'a' => [ 'text' => 'x', 'href' => "page:/contact#a\x01" ], 'c' => null, 'o' => 0 ] ] ) === '<a class="button" href="">x</a>' );
check( 'a link attribute whose fill is page:/contact is that page, as the text of a key is', $button( '[[/project/company/general/pageref]]' ) === '<a class="button" href="/shop/kontakt">x</a>' && $button( '[[/project/company/general/pageanchor]]' ) === '<a class="button" href="/shop/kontakt#form">x</a>'
	&& $button( '[[/project/company/general/nobody]]' ) === '<a class="button" href="">x</a>' && $button( '/project/company/general/pageref' ) === '<a class="button" href="/shop/kontakt">x</a>' );
check( 'page:/contact is a value of a link wherever a link is: the text of a key, and the first word of a call', $button( '~pagelink', [ 'template' => 'page-x', 'scope' => 'hero' ] ) === '<a class="button" href="/shop/kontakt">x</a>'
	&& \Nino\Components::render( $appData, [ [ 'n' => 'go', 's' => 'page:/contact', 'a' => [], 'c' => null, 'o' => 0 ] ] ) === '<a href="/shop/kontakt">go</a>' && \Nino\Components::render( $appData, [ [ 'n' => 'go', 's' => 'page:/nowhere', 'a' => [], 'c' => null, 'o' => 0 ] ] ) === '' );
$routes = $appData['/nino/http/routes'];
unset( $appData['/nino/http/routes'] );
check( 'a site with no table of routes has no page', $page1( '/contact' ) === '<a class="button" href="">x</a>' );
$appData['/nino/http/routes'] = $routes;

check( 'a fill in the first word of a call is put in before the key is read, one level, as in a textfill: the address of the page the request is for', $draw( '[go /_nino/webpage[[/nino/http/response/uri]]/uri]' ) === '<a href="/shop/kontakt">go</a>'
	&& $draw( '[text /template/page-x[[/nino/http/response/uri]]/title]' ) === '' && $draw( '[go /_nino/webpage[[/nino/http/response/uri]]/uri]' ) === $draw( '[go /_nino/webpage/contact/uri]' ) && $draw( '[button text="x" href="/_nino/webpage[[/nino/http/response/uri]]/uri"]' ) === '<a class="button" href="/shop/kontakt">x</a>' );
\Nino\Html::addFills( $appData, [ '/template/page-x/contact/title' => 'Titel der Seite' ], '*' );
\Nino\Html::addFills( $appData, [ '/nino/test/word' => 'second', '/template/page-x/hero/second' => 'Zwei' ], '*' );
check( '...the same for a relative key: ~[[/nino/test/word]] under the id hero is the key of that name', $draw( '[section-wrap id="hero"][text ~[[/nino/test/word]]][/section-wrap]' ) === '<section id="hero" class="section-wrap"><p class="text">Zwei</p></section>' );
check( '...a text, a key under the scope, a link attribute, and a fill nobody has stays written in the key and finds nothing', $draw( '[text /template/page-x[[/nino/http/response/uri]]/title]' ) === '<p class="text">Titel der Seite</p>' && $draw( '[text /template/page-x[[/nope/a/b/c]]/title]' ) === ''
	&& $draw( '[section-wrap id="contact"][section-title ~t[[/nino/http/response/uri]]][/section-wrap]' ) === '<section id="contact" class="section-wrap"></section>' );

check( 'the options of a choice that is no class are written escaped, whatever the options are: a script in an option of the schema and in the answer of a method, as the value and as the default',
	\Nino\Components::render( $appData, [ [ 'n' => 'wild', 's' => '', 'a' => [ 'x' => 'a"><script>alert(1)</script>' ], 'c' => null, 'o' => 0 ] ] ) === '<u data-x="a&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"></u>'
	&& \Nino\Components::render( $appData, [ [ 'n' => 'by-wild', 's' => '', 'a' => [ 'x' => 'a"><script>alert(1)</script>' ], 'c' => null, 'o' => 0 ] ] ) === '<u data-x="a&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"></u>'
	&& $draw( '[wild x="ok"][wild x="other"][by-wild]' ) === '<u data-x="ok"></u><u data-x="ok"></u><u data-x="ok"></u>' );
$seen->exchangeArray( [] );
\Nino\Components::render( $appData, [ [ 'n' => 'wild-echo', 's' => '', 'a' => [ 'x' => 'a"><script>alert(1)</script>' ], 'c' => null, 'o' => 0 ] ] );
check( '...and in the arguments of a renderer', $seen[0][0]['x'] === 'a&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;' );
$wilder = \Nino\Components::validate( 'x', [ 'label' => 'X', 'template' => '<x>', 'attributes' => [ 'x' => [ 'type' => 'choice', 'options' => [ 'a"><script>', 'ok' ], 'default' => 'ok' ] ] ] );
check( '...while a choice that is a class takes no such option at all', is_string( $wilder ) === true );

check( 'the template of a component may hold the words of the site: the text of each key as a plain text, nothing for a key nobody has a text for, one pass - what a text holds is not a token', $draw( '[scroll]kid[/scroll]' )
	=== '<a aria-label="ACME" data-n="" title="[[content]] [[class]]" data-m="[template /templates/x] und [probe-echo]" data-t="Tag &lt;b&gt;in&lt;/b&gt; &quot;text&quot;">kid</a>' );
$appData['./nino/locales/current'] = 'en_US';
\Nino\Html::addFills( $appData, [ '/project/company/general/name' => 'ACME en' ], 'en_US' );
check( '...in the language of the page', $draw( '[scroll][/scroll]' ) === '<a aria-label="ACME en" data-n="" title="[[content]] [[class]]" data-m="[template /templates/x] und [probe-echo]" data-t="Tag &lt;b&gt;in&lt;/b&gt; &quot;text&quot;"></a>' );
$appData['./nino/locales/current'] = 'de_DE';

check( 'an id is at most 64 characters', str_contains( $draw( '[anchored anchor="'. str_repeat( 'a', 64 ). '"][/anchored]' ), ' id="'. str_repeat( 'a', 64 ). '"' ) === true && $draw( '[anchored anchor="'. str_repeat( 'a', 65 ). '"][/anchored]' ) === '<a></a>' );

echo "\n";


// --- The drawing of a page and its time ---------------------------------------------------

echo "A page of 960 calls\n";

$units = 60;
$page960 = '';
$bench = [];
for( $index = 1; $index <= $units; $index++ ) {

	$key = '/template/page-bench/s'. $index. '/';
	$bench[$key. 'title']				= 'Abschnitt '. $index. ': Was wir für Sie tun';
	$bench[$key. 'subtitle']		= 'Ein Satz, der den Abschnitt in Ruhe erklärt.';
	$bench[$key. 'first-title']	= 'Beratung';
	$bench[$key. 'first-text']	= 'Wir hören zu, bevor wir etwas vorschlagen. Das spart am Ende Zeit und Geld.';
	$bench[$key. 'second-title']	= 'Umsetzung';
	$bench[$key. 'second-text']	= 'Wir bauen, was besprochen wurde, und zeigen jeden Zwischenstand.';
	$bench[$key. 'text']				= 'Ein Absatz Fließtext mit einigen Wörtern, wie er auf einer gewöhnlichen Seite steht.';
	$bench[$key. 'button']			= 'Mehr erfahren';

	$page960 .= '[section-wrap id="s'. $index. '"'. ( $index % 2 === 0 ? ' ground="alt"' : '' ). ']'. "\n"
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
for( $index = 0; $index < 1200; $index++ )
	$bench['/template/page-other/part'. $index. '/text'] = 'Ein anderer Text '. $index;
\Nino\Html::addFills( $appData, $bench );

$notes = [];
$tree = \Nino\Markup::parse( $page960, \Nino\Components::containers( $appData ), $notes );
$out = \Nino\Components::render( $appData, $tree, [ 'template' => 'page-bench' ] );
check( 'the page is drawn whole: no note of the read, none of the log, 480 cards\' worth of columns, every title and text found, and nothing of a call or a fill left', $notes === [] && ninoWarnings() === []
	&& substr_count( $out, '<article' ) === 120 && substr_count( $out, '<a class="button' ) === $units && substr_count( $out, '<h2 class="section-title">' ) === $units && substr_count( $out, 'Beratung' ) === $units && strpos( $out, '[' ) === false );
check( 'the page is the same drawn again from the same tree, and drawn from a read of its own text', \Nino\Components::render( $appData, $tree, [ 'template' => 'page-bench' ] ) === $out && \Nino\Components::render( $appData, \Nino\Markup::parse( \Nino\Markup::write( $tree ), \Nino\Components::containers( $appData ) ), [ 'template' => 'page-bench' ] ) === $out );

$read = $draw = $both = [];
for( $run = 0; $run < 30; $run++ ) {
	$started = hrtime( true );
	$tree = \Nino\Markup::parse( $page960, \Nino\Components::containers( $appData ), $notes );
	$middle = hrtime( true );
	\Nino\Components::render( $appData, $tree, [ 'template' => 'page-bench' ] );
	$ended = hrtime( true );
	$read[] = ( $middle - $started ) / 1e6;
	$draw[] = ( $ended - $middle ) / 1e6;
	$both[] = ( $ended - $started ) / 1e6;
}
sort( $read );
sort( $draw );
sort( $both );
$withFill = [];
foreach( $bench as $key => $text )
	if( str_starts_with( $key, '/template/page-bench/' ) === true )
		$withFill[ str_replace( '/template/page-bench/', '/template/page-benchfill/', $key ) ] = $text. ' [[/project/company/general/name]]';
\Nino\Html::addFills( $appData, $withFill );
$pageFill = str_replace( '/template/page-bench/', '/template/page-benchfill/', $page960 );
$treeFill = \Nino\Markup::parse( $pageFill, \Nino\Components::containers( $appData ), $notes );
$outFill = \Nino\Components::render( $appData, $treeFill, [ 'template' => 'page-benchfill' ] );
check( 'the page with a fill in each of its 480 texts is drawn whole, the fill put in every time', substr_count( $outFill, ' ACME' ) === 480 && strpos( $outFill, '[[' ) === false && ninoWarnings() === [] );
$both2 = [];
for( $run = 0; $run < 30; $run++ ) {
	$started = hrtime( true );
	$treeFill = \Nino\Markup::parse( $pageFill, \Nino\Components::containers( $appData ), $notes );
	\Nino\Components::render( $appData, $treeFill, [ 'template' => 'page-benchfill' ] );
	$both2[] = ( hrtime( true ) - $started ) / 1e6;
}
sort( $both2 );
printf( "  Time (not a check) - the same page with a fill in each of its 480 texts, read and drawn: %.2f ms\n", $both2[15] );
printf( "  Time (not a check) - 600 calls of today's counting, 960 of this notation, median of 30: read %.2f ms, draw %.2f ms, both %.2f ms\n", $read[15], $draw[15], $both[15] );

echo "\n";

\Nino\Filesystem::removeDir( ninoSandboxDir( $other ) );

ninoDone( $appData );
