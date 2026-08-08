<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	templates-smoke.php	Dependency-free smoke test for the template builder
 *												(_templates/Templates.php). Runs against an isolated
 *												sandbox directory for everything that writes; the
 *												round-trip section additionally reads the real, git-
 *												tracked _install/library templates, without ever
 *												writing to them.
 *
 *												The load-parse-serialize round-trip is the property the
 *												whole builder rests on: opening a template and saving it
 *												again must reproduce the file byte for byte, or every
 *												save silently reformats markup nobody touched. It is
 *												asserted here against every template the library ships,
 *												so a parser change that breaks one shows up as a failing
 *												test rather than as a mangled site.
 *
 *	Usage: php tests/templates-smoke.php
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_admin/Admin.php';
require __DIR__. '/../_templates/Templates.php';

$failures = 0;
$checks		= 0;

/**
 *	Assert a condition and print the result
 *
 *	@param		string		$label				Description of the check
 *	@param		bool			$condition		Result to assert
 *
 *	@return		void
 */
function check( string $label, bool $condition ): void {
	global $failures, $checks;
	$checks++;
	if( $condition === true ) {
		echo "  ok  - $label\n";
		return;
	}
	$failures++;
	echo "FAIL  - $label\n";
}

set_error_handler( function() { return true; } );

$sandbox = sys_get_temp_dir(). '/nino-templates-smoke-'. uniqid();
mkdir( $sandbox. '/templates', 0777, true );
mkdir( $sandbox. '/assets', 0777, true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path'] 				= $sandbox;
$appData['./nino/filesystem/configpath'] 	= $sandbox;
$appData['/nino/dir'] 										= '';
$appData['./nino/html/shortcodes'] 				= [];

echo "Sandbox: $sandbox\n\n";


// --- Library ---------------------------------------------------------------

echo "Library::blocks\n";

$blocks = \Nino\Templates\Library::blocks();

check( 'reads every library/<key>/manifest.php', count( $blocks ) > 0 );
check( 'every block carries a name, category and tag for the palette', array_filter( $blocks, fn( array $b ): bool => $b['name'] === '' || $b['category'] === '' || $b['tag'] === '' ) === [] );
check( 'every block\'s match declaration is filled out to its full shape', array_filter( $blocks, fn( array $b ): bool => isset( $b['match']['tags'], $b['match']['classes'], $b['match']['classesAny'], $b['match']['attrs'], $b['match']['not'] ) === false ) === [] );
check( 'every block explicitly reports whether it belongs in the palette', array_filter( $blocks, fn( array $b ): bool => isset( $b['palette'] ) === false || is_bool( $b['palette'] ) === false ) === [] );
check( 'a shared setting group is folded into the blocks that ask for it', isset( $blocks['grid-col']['settings']['mb'] ) === true && isset( $blocks['grid-col']['settings']['align'] ) === true );
check( 'a block\'s own settings sit alongside them', isset( $blocks['grid-col']['settings']['width'] ) === true );
check( 'a block that asks for no shared group gets none', isset( $blocks['article-descr']['settings']['mb'] ) === false );
check( 'the grid column matches any of the six width classes', $blocks['grid-col']['match']['classesAny'] === [ 'ui-grid-25', 'ui-grid-33', 'ui-grid-50', 'ui-grid-66', 'ui-grid-75', 'ui-grid-100' ] );
check( 'a shortcode block matches on its placeholder\'s name attribute', $blocks['sc-elements']['match']['attrs'] === [ 'name' => 'elements' ] );

$catalog = [
	'accordion', 'alert', 'badge', 'breadcrumbs', 'content-list', 'feature-list',
	'gallery', 'image-background', 'logos', 'modal', 'pagination', 'pricing-row',
	'slider', 'table-wrap', 'tabs', 'timeline', 'video', 'video-poster',
];

check( 'the Nino.css component catalogue is present', array_diff( $catalog, array_keys( $blocks ) ) === [] );
check( 'nested recognition helpers stay out of the top-level palette', $blocks['table-cell']['palette'] === false && $blocks['tabs-panel']['palette'] === false && $blocks['modal-close']['palette'] === false );
check( 'native boolean attributes use the checkbox setting type', ( $blocks['accordion']['settings']['open']['type'] ?? '' ) === 'attrtoggle' && ( $blocks['form-input']['settings']['required']['type'] ?? '' ) === 'attrtoggle' );
check( 'article modifiers that can coexist are independent toggles', isset( $blocks['article']['settings']['alt'], $blocks['article']['settings']['fullwidth'] ) === true && isset( $blocks['article']['settings']['variant'] ) === false );

// Every block's starting markup has to be parseable by the same parser the
// documents go through - a block.tpl that isn't is a block "insert" would
// silently produce nothing
$unparseable = [];
foreach( $blocks as $key => $block )
	if( $block['html'] !== '' && \Nino\Templates\Parser::parse( $appData, $block['html'] ) === [] )
		$unparseable[] = $key;

check( 'every block\'s own starting markup parses', $unparseable === [] );

// ...and round-trips, for the same reason a document has to
$blockMismatch = [];
foreach( $blocks as $key => $block )
	if( $block['html'] !== '' && \Nino\Templates\Serializer::serialize( \Nino\Templates\Parser::parse( $appData, $block['html'] ) ) !== $block['html'] )
		$blockMismatch[] = $key;

check( 'every block\'s own starting markup round-trips unchanged', $blockMismatch === [] );

// Inserting a block goes through this rather than the client building
// markup of its own - one parser, one meaning of "what a template is"
$_POST['data'] = json_encode( [ 'block' => 'button' ] );
$parseRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Library::apiParse( $appData, $parseRequest );
$parseBody = $parseRequest['/nino/http/response']['body'];

check( 'library/parse returns a block\'s markup as a node tree', ( $parseBody['nodes'][0]['tag'] ?? '' ) === 'a' );
check( '...with its classes already separated out, ready to be edited', ( $parseBody['nodes'][0]['classes'] ?? [] ) === [ 'ui-btn', 'ui-btn--primary' ] );

$_POST['data'] = json_encode( [ 'block' => 'does-not-exist' ] );
$unknownParse = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Library::apiParse( $appData, $unknownParse );
check( 'library/parse rejects an unknown block with 404', $unknownParse['/nino/http/response']['statusCode'] === 404 );

$_POST['data'] = json_encode( [ 'block' => '../../../etc/passwd' ] );
$traversalParse = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Library::apiParse( $appData, $traversalParse );
check( 'library/parse rejects a block key trying to escape the library', $traversalParse['/nino/http/response']['statusCode'] === 404 );

echo "\n";


// --- Parser / Serializer ---------------------------------------------------

echo "Parser / Serializer\n";

$nodes = \Nino\Templates\Parser::parse( $appData, '<div class="ui-grid-row"><p>Hello</p></div>' );

check( 'parses an element into a node', ( $nodes[0]['type'] ?? '' ) === 'element' && ( $nodes[0]['tag'] ?? '' ) === 'div' );
check( 'keeps the class list ordered and separate from the attributes', ( $nodes[0]['classes'] ?? [] ) === [ 'ui-grid-row' ] && isset( $nodes[0]['attrs']['class'] ) === false );
check( 'walks into children', ( $nodes[0]['children'][0]['tag'] ?? '' ) === 'p' );
check( 'keeps text content', ( $nodes[0]['children'][0]['children'][0]['value'] ?? '' ) === 'Hello' );

$shortcode = \Nino\Templates\Parser::parse( $appData, '[elements /services limit="3"]<p>[[title]]</p>[/elements]' );

check( 'a wrapping shortcode becomes a node with children, not a string', ( $shortcode[0]['tag'] ?? '' ) === 'nino-sc' && count( $shortcode[0]['children'] ?? [] ) === 1 );
check( '...carrying its name and arguments', ( $shortcode[0]['attrs']['name'] ?? '' ) === 'elements' && ( $shortcode[0]['attrs']['args'] ?? '' ) === '/services limit="3"' );
check( 'a void shortcode is marked as one', ( \Nino\Templates\Parser::parse( $appData, '[csrf]' )[0]['attrs']['void'] ?? '' ) === '1' );

// A text fill is ordinary text wherever it appears - the parser must not
// treat it as syntax, in a text node or in an attribute value
$fills = \Nino\Templates\Parser::parse( $appData, '<a href="[[/nino/dir]]/x">[[/webpage[[/nino/http/response/uri]]/name]]</a>' );

check( 'a text fill in an attribute value survives', ( $fills[0]['attrs']['href'] ?? '' ) === '[[/nino/dir]]/x' );
check( 'a nested text fill in a text node survives', ( $fills[0]['children'][0]['value'] ?? '' ) === '[[/webpage[[/nino/http/response/uri]]/name]]' );

foreach( [
	'a comment' 														=> '<!-- keep me -->',
	'a boolean attribute, still bare' 			=> '<input type="text" required>',
	'a void element without a closing tag' 	=> '<img src="/x.jpg" alt="">',
	'an html entity, still spelled the same' => '<p>Sil&shy;be</p>',
	'a bare ampersand in an attribute' 			=> '<a href="/x?a=1&b=2">x</a>',
	'an encoded ampersand in an attribute' 	=> '<a href="/x?a=1&amp;b=2">x</a>',
	'attribute order, class included' 			=> '<a href="/x" class="ui-btn" id="y">x</a>',
	'exact whitespace between tags' 				=> "<div>\n\t<p>x</p>\n</div>",
] as $label => $source )
	check( 'round-trips '. $label, \Nino\Templates\Serializer::serialize( \Nino\Templates\Parser::parse( $appData, $source ) ) === $source );

// attrtoggle writes an enabled native boolean attribute into the node as an
// empty value. Serializer must turn that into the bare HTML spelling rather
// than required="" (or deleting it as an ordinary empty attr would).
$booleanNodes = \Nino\Templates\Parser::parse( $appData, '<details></details>' );
$booleanNodes[0]['attrs']['open'] = '';
check( 'serializes an enabled attrtoggle as a bare boolean attribute', \Nino\Templates\Serializer::serialize( $booleanNodes ) === '<details open></details>' );

echo "\n";


// --- Round-trip against the real, shipped templates ------------------------

echo "Round-trip (real _install/library templates)\n";

$library 	= __DIR__. '/../_install/library';
$sources 	= array_merge(
	glob( $library. '/pages/*/templates/*.tpl' ) ?: [],
	glob( $library. '/base/templates/*.tpl' ) ?: [],
	glob( $library. '/modules/*/templates/*.tpl' ) ?: []
);

check( 'found the shipped templates to test against', count( $sources ) > 0 );

// Only page-*/section-* are ever written back (see Documents' class
// docblock), so those are the ones that have to round-trip. html-header/
// html-footer deliberately do not - they are one structure split across two
// files - and the mail-*/sitemap-xml templates are not page markup at all
$mismatch = [];

foreach( $sources as $file ) {

	$name = basename( $file, '.tpl' );

	if( str_starts_with( $name, 'page-' ) === false && str_starts_with( $name, 'section-' ) === false )
		continue;

	$source = (string) file_get_contents( $file );

	if( \Nino\Templates\Serializer::serialize( \Nino\Templates\Parser::parse( $appData, $source ) ) !== $source )
		$mismatch[] = $name;
}

check( 'every shipped page template round-trips byte for byte'. ( $mismatch === [] ? '' : ' ('. implode( ', ', $mismatch ). ')' ), $mismatch === [] );

// The unbalanced pair is the reason the editable set is restricted at all -
// asserted rather than just documented, so nobody "fixes" the restriction
// without noticing what it was protecting
$header = (string) file_get_contents( $library. '/base/templates/html-header.tpl' );
check( 'html-header.tpl really is the unbalanced fragment that restriction exists for', \Nino\Templates\Serializer::serialize( \Nino\Templates\Parser::parse( $appData, $header ) ) !== $header );

echo "\n";


// --- Stylesheets -----------------------------------------------------------

echo "Stylesheets::styledIds\n";

file_put_contents( $sandbox. '/assets/style.css', "/* #commented { } */\n#hero { padding: 0 }\n.ui-section #inner a { color: red }\n.plain { color: blue }\n" );
$appData['/nino/html/assets'] = [
	'/.cache/style.css' => [ '/assets/style.css' ],
	'/.cache/script.js' => [ '/assets/script.js' ],
];

$styled = \Nino\Templates\Stylesheets::styledIds( $appData );

check( 'finds an id a rule targets directly', in_array( 'hero', $styled, true ) === true );
check( 'finds one nested in a descendant selector', in_array( 'inner', $styled, true ) === true );
check( 'ignores an id inside a comment', in_array( 'commented', $styled, true ) === false );
check( 'reports nothing for a stylesheet of plain class rules', in_array( 'plain', $styled, true ) === false );
check( 'never looks at a bundle that is not css', count( array_filter( $styled, fn( string $id ): bool => $id === 'script' ) ) === 0 );

echo "\n";


// --- Documents -------------------------------------------------------------

echo "Documents::apiList / apiLoad / apiSave\n";

copy( $library. '/pages/contact/templates/page-contact.tpl', $sandbox. '/templates/page-contact.tpl' );
copy( $library. '/base/templates/html-header.tpl', 					$sandbox. '/templates/html-header.tpl' );

$listRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiList( $appData, $listRequest );
$listBody = $listRequest['/nino/http/response']['body'];

check( 'lists every /templates/*.tpl on disk', array_column( $listBody['documents'], 'name' ) === [ 'html-header', 'page-contact' ] );
check( 'flags a page template as editable', ( $listBody['documents'][1]['editable'] ?? null ) === true );
check( 'flags html-header as not editable', ( $listBody['documents'][0]['editable'] ?? null ) === false );

$_POST['data'] = json_encode( [ 'name' => 'page-contact' ] );
$loadRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiLoad( $appData, $loadRequest );
$loadBody = $loadRequest['/nino/http/response']['body'];

check( 'loads a page template as a node tree', count( $loadBody['nodes'] ?? [] ) > 0 );
check( '...and reports it as writable', array_key_exists( 'readonly', $loadBody ) === true && $loadBody['readonly'] === null );
check( '...alongside the ids the project css targets', ( $loadBody['styledIds'] ?? null ) === $styled );

$_POST['data'] = json_encode( [ 'name' => 'html-header' ] );
$lockedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiLoad( $appData, $lockedRequest );
check( 'html-header loads, but read-only', ( $lockedRequest['/nino/http/response']['body']['readonly'] ?? null ) !== null );

$_POST['data'] = json_encode( [ 'name' => '../../config' ] );
$traversalRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiLoad( $appData, $traversalRequest );
check( 'rejects a template name trying to escape /templates', $traversalRequest['/nino/http/response']['statusCode'] === 404 );

// Save the tree straight back, unmodified: the file must not change by a
// single byte - the whole point of the round-trip guarantee
$before = (string) file_get_contents( $sandbox. '/templates/page-contact.tpl' );

$_POST['data'] = json_encode( [ 'name' => 'page-contact', 'nodes' => $loadBody['nodes'] ] );
$saveRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiSave( $appData, $saveRequest );

check( 'save succeeds', $saveRequest['/nino/http/response']['statusCode'] === 200 );
check( 'saving an unmodified tree leaves the file byte-identical', (string) file_get_contents( $sandbox. '/templates/page-contact.tpl' ) === $before );

// A real edit: swap the first grid column from ui-grid-l-50 to ui-grid-l-33
$edited = $loadBody['nodes'];
$found 	= false;

array_walk_recursive( $edited, function( &$value ) use ( &$found ): void {
	if( $found === false && $value === 'ui-grid-l-50' ) {
		$value = 'ui-grid-l-33';
		$found = true;
	}
} );

check( 'the test found a class to edit', $found === true );

$_POST['data'] = json_encode( [ 'name' => 'page-contact', 'nodes' => $edited ] );
$editRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiSave( $appData, $editRequest );
$after = (string) file_get_contents( $sandbox. '/templates/page-contact.tpl' );

check( 'an edited class lands in the file', str_contains( $after, 'ui-grid-100 ui-grid-l-33' ) === true );
check( '...and changes nothing else about it', $after !== $before && str_replace( 'ui-grid-l-33', 'ui-grid-l-50', $after ) === $before );

$_POST['data'] = json_encode( [ 'name' => 'html-header', 'nodes' => [] ] );
$lockedSave = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiSave( $appData, $lockedSave );
check( 'refuses to save a template that is not editable', $lockedSave['/nino/http/response']['statusCode'] === 403 );

$_POST['data'] = json_encode( [ 'name' => 'page-contact', 'nodes' => [] ] );
$emptySave = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiSave( $appData, $emptySave );
check( 'refuses to write an empty template', $emptySave['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'name' => 'page-contact', 'nodes' => [
	[ 'type' => 'element', 'tag' => 'script', 'attrs' => [], 'classes' => [], 'children' => [] ],
] ] );
$scriptSave = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiSave( $appData, $scriptSave );
check( 'refuses to write a <script> element', $scriptSave['/nino/http/response']['statusCode'] === 400 );

$_POST['data'] = json_encode( [ 'name' => 'page-contact', 'nodes' => [
	[ 'type' => 'element', 'tag' => 'div', 'attrs' => [ 'onclick' => 'x()' ], 'classes' => [], 'children' => [] ],
] ] );
$handlerSave = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Templates\Documents::apiSave( $appData, $handlerSave );
check( 'refuses to write an event-handler attribute', $handlerSave['/nino/http/response']['statusCode'] === 400 );

check( 'none of the refused saves touched the file', (string) file_get_contents( $sandbox. '/templates/page-contact.tpl' ) === $after );

echo "\n";


// --- Templates::init -------------------------------------------------------

echo "Templates::init\n";

$appData['/nino/http/routes']['GET://_templates'] = [
	'uri' => '/_templates', 'body' => '[template /templates/page-stale]', 'statusCode' => 418,
];
$appData['/nino/http/routes']['POST://_templates'] = [ 'uri' => '/stale' ];

\Nino\Templates\Templates::init( $appData );

check( 'the runtime GET route replaces a stale persisted collision', $appData['/nino/http/routes']['GET://_templates']['body'] === '[template /_templates/templates/page-index]' && $appData['/nino/http/routes']['GET://_templates']['statusCode'] === 200 );
check( 'the runtime POST route replaces a stale persisted collision', $appData['/nino/http/routes']['POST://_templates'] === [ 'uri' => '/_templates' ] );

echo "\n";


// --- Templates::guard ------------------------------------------------------

echo "Templates::guard\n";

$notAuthed = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
check( 'guard rejects a request without an _admin session', \Nino\Templates\Templates::guard( $appData, $notAuthed ) === false );
check( '...with a 401', $notAuthed['/nino/http/response']['statusCode'] === 401 );

$alreadyFailed = [ '/nino/http/response' => [ 'statusCode' => 404 ] ];
check( 'guard rejects a request an earlier callback already failed', \Nino\Templates\Templates::guard( $appData, $alreadyFailed ) === false );

echo "\n";


// --- Cleanup ---------------------------------------------------------------

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";

exit( $failures > 0 ? 1 : 0 );
