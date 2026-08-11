<?php
declare(strict_types=1);

/**
 *	Dependency-free backend smoke test for /_templates.
 *	All writes stay inside an isolated temporary project.
 */

require __DIR__. '/../_nino/Nino.php';
require __DIR__. '/../_admin/Admin.php';
require __DIR__. '/../_templates/Templates.php';

$failures = 0;
$checks = 0;

function check( string $label, bool $condition ): void {
	global $failures, $checks;
	$checks++;
	if( $condition ) {
		echo "  ok  - $label\n";
		return;
	}
	$failures++;
	echo "FAIL  - $label\n";
}

function response(): array {
	return [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
}

function post( array $data ): void {
	$_POST['data'] = json_encode( $data );
}

function throwsInvalidArgument( callable $callback ): bool {
	try {
		$callback();
	} catch( \InvalidArgumentException ) {
		return true;
	}
	return false;
}

set_error_handler( function() { return true; } );

$sandbox = sys_get_temp_dir(). '/nino-templates-smoke-'. uniqid();
mkdir( $sandbox. '/templates', 0777, true );
mkdir( $sandbox. '/text', 0777, true );
mkdir( $sandbox. '/elements', 0777, true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path'] = $sandbox;
$appData['./nino/filesystem/configpath'] = $sandbox;
$appData['/nino/dir'] = '';
$appData['/nino/locales/native'] = 'en_US';
$appData['/nino/locales/available'] = [ 'en_US', 'de_DE' ];
$appData['./nino/html/shortcodes'] = [];
$appData['/nino/html/images'] = [];

\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/html/images' => [] ] );
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [] );
\Nino\Filesystem::putFileContent( $appData, '/text/blacklist.php', [] );
\Nino\Filesystem::putFileContent( $appData, '/text/en_US.php', [ '[[/page-home/hero/title]]' => 'Old title' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/page-home/hero/title]]' => 'Alter Titel' ] );

echo "Sandbox: $sandbox\n\n";


// --- Presets and Composer --------------------------------------------------

echo "Library / Composer\n";

$presets = \Nino\Templates\Library::presets();
$modules = \Nino\Templates\Composer::modules();

check( 'ships a broad initial Section Library', count( $presets ) >= 43 );
check( 'includes a flexible blank section and curated hero', isset( $presets['blank'], $presets['hero-centered'] ) );
check( 'covers reusable demo-element content patterns', isset( $presets['list-check'], $presets['list-numbered'], $presets['content-tabs'], $presets['data-table'], $presets['media-slider'], $presets['video-embed'], $presets['notice-callout'], $presets['badge-cloud'] ) );
check( 'every preset has searchable metadata and normalized choices', array_filter( $presets, fn( array $preset ): bool => $preset['name'] === '' || $preset['category'] === '' || $preset['tags'] === [] || $preset['allow'] === [] ) === [] );
check( 'repeatable articles recommend a localized CTA label', ( $modules['articles']['model']['linkLabel']['locale'] ?? false ) === true );
check( 'every curated preset composes with its defaults', array_filter( array_keys( $presets ), function( string $key ): bool {
	try {
		\Nino\Templates\Composer::compose( [ 'preset' => $key, 'pageId' => 'test', 'id' => str_replace( '_', '-', $key ) ] );
		return false;
	} catch( \Throwable ) {
		return true;
	}
} ) === [] );

$hero = \Nino\Templates\Composer::compose( [
	'preset' => 'hero-centered', 'pageId' => 'home', 'id' => 'main-hero', 'pageMotion' => 'on',
] );

check( 'composes one ordinary section', str_starts_with( $hero['source'], '<section' ) && str_contains( $hero['source'], '</section>' ) );
check( 'writes stable section metadata inside generated source', str_contains( $hero['source'], '<!-- nino:section {' ) );
check( 'derives textfill keys from page and section ids', in_array( 'title', $hero['fields'], true ) && str_contains( $hero['source'], '[[/page-home/main-hero/title]]' ) );
check( 'reports the generated background image slot', $hero['imageSlots'] === [ 'background' ] );
check( 'inherits page motion into generated js-vpa markup', str_contains( $hero['source'], 'class="ui-grid-row js-vpa"' ) );
check( 'applies heading alignment without forcing it through nested content', str_contains( $hero['source'], 'ui-grid-100 ui-mb-3 ui-text-center' ) && str_contains( strtok( $hero['source'], "\n" ), 'ui-text-center' ) === false );

$articles = \Nino\Templates\Composer::compose( [
	'preset' => 'articles-grid', 'pageId' => 'home', 'id' => 'services', 'elementType' => 'services',
	'content' => 'articles-image', 'layout' => '4',
] );

check( 'binds repeatable modules to a chosen element type', str_contains( $articles['source'], '[elements /services limit="3"]' ) );
check( 'uses the dedicated element CTA label instead of repeating its title', str_contains( $articles['source'], '[[linkLabel]]' ) );
check( 'returns the exact schema needed to create that type', isset( $articles['elementSchema']['image'], $articles['elementSchema']['linkLabel'] ) );
check( 'generated Elements images use Nino image storage', str_contains( $articles['source'], '[[/nino/dir]]/images/[[image]]' ) && str_contains( $articles['source'], '/uploads/' ) === false );

$articlePreview = \Nino\Templates\Composer::preview( [
	'preset' => 'articles-image-grid', 'pageId' => 'preview', 'id' => 'articles', 'elementType' => 'preview-items', 'limit' => 2,
] );
check( 'renders real preview HTML with deterministic text and image fixtures', $articlePreview !== null && str_contains( $articlePreview, '<section' ) && str_contains( $articlePreview, 'data:image/svg+xml' ) && str_contains( $articlePreview, 'Thoughtful item 1' ) );
check( 'preview HTML contains no unresolved project shortcodes', $articlePreview !== null && str_contains( $articlePreview, '[[' ) === false && str_contains( $articlePreview, '[elements' ) === false && str_contains( $articlePreview, '[image' ) === false );

$list = \Nino\Templates\Composer::compose( [
	'preset' => 'list-check', 'pageId' => 'home', 'id' => 'benefits', 'elementType' => 'benefits', 'layout' => 'check-2',
] );
check( 'composes a repeatable two-column checklist', str_contains( $list['source'], 'ui-list--check ui-list--content ui-list--columns' ) && isset( $list['elementSchema']['title'] ) );

$tabs = \Nino\Templates\Composer::compose( [
	'preset' => 'content-tabs', 'pageId' => 'home', 'id' => 'details', 'elementType' => 'details',
] );
check( 'composes tabs with stable element-derived panel ids', str_contains( $tabs['source'], 'data-tabs-target="details-tab-[[.id]]"' ) && str_contains( $tabs['source'], 'role="tabpanel"' ) );

$table = \Nino\Templates\Composer::compose( [
	'preset' => 'data-table', 'pageId' => 'home', 'id' => 'services-table', 'elementType' => 'service-data',
] );
check( 'composes a styled general data table', str_contains( $table['source'], 'ui-table--striped ui-table--bordered' ) && array_diff( [ 'column-a', 'column-b', 'column-c' ], $table['fields'] ) === [] );

$mediaSlider = \Nino\Templates\Composer::compose( [
	'preset' => 'media-slider', 'pageId' => 'home', 'id' => 'highlights', 'elementType' => 'highlights',
] );
check( 'composes an image-led content slider', str_contains( $mediaSlider['source'], 'js-slider ui-media-slider' ) && isset( $mediaSlider['elementSchema']['image'], $mediaSlider['elementSchema']['linkLabel'] ) );

$videoEmbed = \Nino\Templates\Composer::compose( [
	'preset' => 'video-embed', 'pageId' => 'home', 'id' => 'demo-video', 'layout' => '4-3',
] );
check( 'composes a direct responsive video embed', str_contains( $videoEmbed['source'], 'ui-video ui-video--4-3' ) && str_contains( $videoEmbed['source'], 'js-modal' ) === false );

$notice = \Nino\Templates\Composer::compose( [
	'preset' => 'notice-callout', 'pageId' => 'home', 'id' => 'important', 'layout' => 'success',
] );
check( 'composes a native-textfill notice variant', str_contains( $notice['source'], 'ui-alert--success' ) && in_array( 'content', $notice['fields'], true ) );

$badges = \Nino\Templates\Composer::compose( [
	'preset' => 'badge-cloud', 'pageId' => 'home', 'id' => 'technologies', 'elementType' => 'technologies',
] );
check( 'composes a repeatable pill cloud', str_contains( $badges['source'], 'ui-badge-cloud' ) && str_contains( $badges['source'], 'ui-badge--pill' ) );

$dualCta = \Nino\Templates\Composer::compose( [
	'preset' => 'closing-cta', 'pageId' => 'home', 'id' => 'closing', 'action' => 'dual-buttons',
] );
check( 'offers primary plus outline CTA as one supported action', str_contains( $dualCta['source'], 'ui-btn--primary' ) && str_contains( $dualCta['source'], 'ui-btn--outline' ) );
check( 'dual CTA exposes all four native textfills', array_diff( [ 'cta-label', 'cta-uri', 'secondary-cta-label', 'secondary-cta-uri' ], $dualCta['fields'] ) === [] );

$clamped = \Nino\Templates\Composer::compose( [
	'preset' => 'faq-accordion', 'pageId' => 'home', 'id' => 'faq', 'content' => 'slider', 'layout' => '4',
] );
check( 'a curated preset rejects out-of-profile choices by falling back to its manifest', $clamped['spec']['content'] === 'accordion' && in_array( $clamped['spec']['layout'], [ 'narrow', 'wide' ], true ) );
check( 'rejects invalid page ids', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( [ 'preset' => 'blank', 'pageId' => '../home', 'id' => 'intro' ] ) ) );
check( 'rejects invalid element type paths', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( [ 'preset' => 'articles-grid', 'pageId' => 'home', 'id' => 'intro', 'elementType' => '../items' ] ) ) );

echo "\n";


// --- Lossless SectionDocument --------------------------------------------

echo "SectionDocument\n";

$source = "[template /templates/html-header]\n"
	. "<!-- <section id=\"not-real\"> -->\n"
	. "<?php \$sample = '<section>not markup either</section>'; ?>\n"
	. "<script>const sample = '</scripture><section>not markup</section>';</script>\n"
	. "<section id=\"hero\" class=\"ui-section\">\n\t<section class=\"nested\"><p>Nested</p></section>\n</section>\n"
	. "<section id='content'>[[/page-home/content/title]]</section>\n"
	. "[template /templates/html-footer]\n";

$parsed = \Nino\Templates\SectionDocument::split( $source );
$joined = implode( '', array_column( $parsed['segments'], 'source' ) );
$sections = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'section' ) );
$templateSections = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'template' ) );

check( 'recognizes only top-level sections', $parsed['sectionCount'] === 2 );
check( 'promotes standalone template shortcodes to canvas components', count( $templateSections ) === 2 && $templateSections[0]['template'] === 'html-header' && $templateSections[1]['template'] === 'html-footer' );
check( 'counts HTML and template sections together as canvas components', $parsed['componentCount'] === 4 );
check( 'ignores section-like text in comments, PHP and raw script bodies', count( $sections ) === 2 );
check( 'keeps a nested section inside its top-level parent', str_contains( $sections[0]['source'], 'class="nested"' ) );
check( 'extracts ids, fills and bindings for the UI', $sections[1]['htmlId'] === 'content' && $sections[1]['fills'] === [ '/page-home/content/title' ] );
check( 'rejoining untouched segments is byte-identical', $joined === $source );
check( 'reports an unmatched section instead of guessing', \Nino\Templates\SectionDocument::split( '<section><p>x</p>' )['error'] !== null );
check( 'rejects a self-closing section because HTML does not close it there', \Nino\Templates\SectionDocument::split( '<section />' )['error'] !== null );
check( 'code inspection accepts exactly one complete section', \Nino\Templates\SectionDocument::inspectSection( "<section id=\"x\"></section>\n" )['valid'] === true );
check( 'code inspection rejects source around the section', \Nino\Templates\SectionDocument::inspectSection( "<div>x</div><section></section>" )['valid'] === false );
check( 'template inspection accepts one standalone shortcode', \Nino\Templates\SectionDocument::inspectTemplate( "[template /templates/html-header]\n" )['valid'] === true );
check( 'template inspection rejects mixed or nested source', \Nino\Templates\SectionDocument::inspectTemplate( "<section>[template /templates/inside]</section>\n" )['valid'] === false );
$nestedTemplate = \Nino\Templates\SectionDocument::split( "<section>[template /templates/inside]</section>\n" );
check( 'a template shortcode inside an HTML section stays inside that section', $nestedTemplate['componentCount'] === 1 && $nestedTemplate['segments'][0]['type'] === 'section' );
$rawNestedTemplate = \Nino\Templates\SectionDocument::split( "<div>\n[template /templates/inside-div]\n</div>\n" );
check( 'a template shortcode inside an arbitrary raw DOM parent stays locked', $rawNestedTemplate['componentCount'] === 0 && count( $rawNestedTemplate['segments'] ) === 1 );
$commentedTemplate = \Nino\Templates\SectionDocument::split( "<!--\n[template /templates/commented]\n-->\n" );
check( 'a template-like line inside a comment stays locked', $commentedTemplate['componentCount'] === 0 && count( $commentedTemplate['segments'] ) === 1 );
$wrappedAcrossSection = \Nino\Templates\SectionDocument::split( "<main>\n<section></section>\n[template /templates/inside-main]\n</main>\n" );
check( 'raw parent depth is retained across visual section segments', $wrappedAcrossSection['componentCount'] === 1 && count( array_filter( $wrappedAcrossSection['segments'], fn( array $segment ): bool => $segment['type'] === 'template' ) ) === 0 );
$emptyFrame = \Nino\Templates\SectionDocument::split( "[template /templates/html-header]\n[template /templates/html-footer]\n" );
check( 'unmarked header and footer remain ordinary template components outside page normalization', count( $emptyFrame['segments'] ) === 2 && $emptyFrame['segments'][0]['type'] === 'template' && $emptyFrame['segments'][1]['type'] === 'template' );

$markedFrameSource = \Nino\Templates\SectionDocument::slotSource( 'header', '/templates/html-header' )
	. \Nino\Templates\SectionDocument::slotSource( 'footer', '' );
$markedFrame = \Nino\Templates\SectionDocument::split( $markedFrameSource );
$markedSlots = array_values( array_filter( $markedFrame['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
check( 'recognizes marked header and footer includes as fixed page slots', count( $markedSlots ) === 2 && $markedSlots[0]['slot'] === 'header' && $markedSlots[0]['template'] === 'html-header' && $markedSlots[1]['slot'] === 'footer' && $markedSlots[1]['path'] === '' );
check( 'fixed page slots are excluded from the canvas component count', $markedFrame['componentCount'] === 0 );
check( 'marked slot parsing remains byte-identical', implode( '', array_column( $markedFrame['segments'], 'source' ) ) === $markedFrameSource );
check( 'slot inspection accepts an include or None but rejects the wrong slot', \Nino\Templates\SectionDocument::inspectSlot( \Nino\Templates\SectionDocument::slotSource( 'header', '/templates/site-header' ), 'header' )['valid'] === true
	&& \Nino\Templates\SectionDocument::inspectSlot( \Nino\Templates\SectionDocument::slotSource( 'footer' ), 'footer' )['valid'] === true
	&& \Nino\Templates\SectionDocument::inspectSlot( \Nino\Templates\SectionDocument::slotSource( 'footer' ), 'header' )['valid'] === false );

echo "\n";


// --- Documents ------------------------------------------------------------

echo "Documents\n";

$page = "[template /templates/html-header]\n<!-- locked project source -->\n". $hero['source']. $articles['source']. "[template /templates/html-footer]\n";
file_put_contents( $sandbox. '/templates/page-home.tpl', $page );
file_put_contents( $sandbox. '/templates/section-card.tpl', '<section></section>' );
file_put_contents( $sandbox. '/templates/html-header.tpl', '<!doctype html>' );

$listRequest = response();
\Nino\Templates\Documents::apiList( $appData, $listRequest );
$listed = $listRequest['/nino/http/response']['body']['documents'];

check( 'lists page-*.tpl files only', array_column( $listed, 'name' ) === [ 'page-home' ] );
check( 'reports filename, display name, page id and inherited VPA', $listed[0]['filename'] === 'page-home.tpl' && $listed[0]['displayName'] === 'Home' && $listed[0]['sections'] === 2 && $listed[0]['pageId'] === 'home' && $listed[0]['pageMotion'] === 'on' );
check( 'excludes the automatically recognized header/footer shell from the canvas-item count', $listed[0]['components'] === 2 );

$includesRequest = response();
\Nino\Templates\Documents::apiIncludes( $appData, $includesRequest );
$includes = $includesRequest['/nino/http/response']['body']['includes'];
check( 'include library always starts with html-header and html-footer', array_column( array_slice( $includes, 0, 2 ), 'name' ) === [ 'html-header', 'html-footer' ] );
check( 'include library offers section templates but excludes page templates', in_array( 'section-card', array_column( $includes, 'name' ), true ) && in_array( 'page-home', array_column( $includes, 'name' ), true ) === false );

file_put_contents( $sandbox. '/templates/page-2026.home.tpl', $page );
$variantListRequest = response();
\Nino\Templates\Documents::apiList( $appData, $variantListRequest );
$variant = array_values( array_filter( $variantListRequest['/nino/http/response']['body']['documents'], fn( array $document ): bool => $document['name'] === 'page-2026.home' ) )[0] ?? [];
check( 'derives a valid distinct id from dotted or number-prefixed page names', ( $variant['pageId'] ?? '' ) === 'p-2026-home' );
unlink( $sandbox. '/templates/page-2026.home.tpl' );

$bareSource = "<section id=\"bare\"></section>\n";
file_put_contents( $sandbox. '/templates/page-bare.tpl', $bareSource );
post( [ 'name' => 'page-bare' ] );
$bareLoadRequest = response();
\Nino\Templates\Documents::apiLoad( $appData, $bareLoadRequest );
$bare = $bareLoadRequest['/nino/http/response']['body'];
$bareSlots = array_values( array_filter( $bare['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
check( 'a page without shell includes receives editable None placeholders', count( $bareSlots ) === 2 && $bareSlots[0]['path'] === '' && $bareSlots[1]['path'] === '' );
post( [ 'name' => 'page-bare', 'revision' => $bare['revision'], 'segments' => $bare['segments'] ] );
$bareSaveRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $bareSaveRequest );
check( 'None placeholders persist as markers without inventing template includes', $bareSaveRequest['/nino/http/response']['statusCode'] === 200
	&& substr_count( (string) file_get_contents( $sandbox. '/templates/page-bare.tpl' ), 'nino:template-slot' ) === 2
	&& str_starts_with( (string) file_get_contents( $sandbox. '/templates/page-bare.tpl' ), '<!-- nino:template-name Bare -->' )
	&& str_contains( (string) file_get_contents( $sandbox. '/templates/page-bare.tpl' ), '[template ' ) === false );
unlink( $sandbox. '/templates/page-bare.tpl' );

post( [ 'name' => 'page-home' ] );
$loadRequest = response();
\Nino\Templates\Documents::apiLoad( $appData, $loadRequest );
$loaded = $loadRequest['/nino/http/response']['body'];

check( 'loads lossless segments with an optimistic revision', count( $loaded['segments'] ) >= 4 && strlen( $loaded['revision'] ) === 64 );
check( 'loads a valid page as editable', $loaded['readonly'] === null );
check( 'legacy templates receive a stable fallback display name and VPA', $loaded['displayName'] === 'Home' && $loaded['pageMotion'] === 'on' );
$loadedSlots = array_values( array_filter( $loaded['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
check( 'promotes an exact legacy html-header/html-footer frame into fixed settings slots', count( $loadedSlots ) === 2 && $loadedSlots[0]['slot'] === 'header' && $loadedSlots[1]['slot'] === 'footer' );

post( [ 'name' => 'page-home', 'revision' => $loaded['revision'], 'segments' => $loaded['segments'] ] );
$roundTripRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $roundTripRequest );
check( 'saving untouched segments succeeds', $roundTripRequest['/nino/http/response']['statusCode'] === 200 );
check( 'the first deliberate save adds inert markers around legacy shell includes', str_contains( (string) file_get_contents( $sandbox. '/templates/page-home.tpl' ), '<!-- nino:template-slot header -->' )
	&& str_contains( (string) file_get_contents( $sandbox. '/templates/page-home.tpl' ), '<!-- nino:template-slot footer -->' )
	&& str_starts_with( (string) file_get_contents( $sandbox. '/templates/page-home.tpl' ), "<!-- nino:template-name Home -->\n<!-- nino:template-vpa on -->\n" ) );
$loaded['revision'] = $roundTripRequest['/nino/http/response']['body']['revision'];

$sectionSlots = array_keys( array_filter( $loaded['segments'], fn( array $segment ): bool => $segment['type'] === 'section' ) );
$reordered = $loaded['segments'];
[ $reordered[$sectionSlots[0]], $reordered[$sectionSlots[1]] ] = [ $reordered[$sectionSlots[1]], $reordered[$sectionSlots[0]] ];
post( [ 'name' => 'page-home', 'revision' => $loaded['revision'], 'segments' => $reordered ] );
$reorderRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $reorderRequest );
$reorderedSource = file_get_contents( $sandbox. '/templates/page-home.tpl' );
check( 'reordering complete sections succeeds', $reorderRequest['/nino/http/response']['statusCode'] === 200 );
check( 'reordering leaves metadata and marked header/footer frame source in place', str_starts_with( $reorderedSource, '<!-- nino:template-name Home -->' )
	&& strpos( $reorderedSource, '<!-- nino:template-name Home -->' ) < strpos( $reorderedSource, '<!-- nino:template-slot header -->' )
	&& str_contains( $reorderedSource, "[template /templates/html-header]\n" )
	&& str_ends_with( $reorderedSource, "[template /templates/html-footer]\n" ) );
check( 'the selected section order reaches the file', strpos( $reorderedSource, 'id="services"' ) < strpos( $reorderedSource, 'id="main-hero"' ) );

post( [ 'name' => 'page-home' ] );
$freshLoadRequest = response();
\Nino\Templates\Documents::apiLoad( $appData, $freshLoadRequest );
$fresh = $freshLoadRequest['/nino/http/response']['body'];
$missingSlot = array_values( array_filter( $fresh['segments'], fn( array $segment ): bool => !( $segment['type'] === 'slot' && $segment['slot'] === 'footer' ) ) );
post( [ 'name' => 'page-home', 'revision' => $fresh['revision'], 'segments' => $missingSlot ] );
$missingSlotRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $missingSlotRequest );
check( 'refuses to save a payload without both fixed shell slots', $missingSlotRequest['/nino/http/response']['statusCode'] === 400 );

$duplicateSlot = $fresh['segments'];
$headerSlot = array_values( array_filter( $fresh['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' && $segment['slot'] === 'header' ) )[0];
$duplicateSlot[] = $headerSlot;
post( [ 'name' => 'page-home', 'revision' => $fresh['revision'], 'segments' => $duplicateSlot ] );
$duplicateSlotRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $duplicateSlotRequest );
check( 'refuses duplicate shell slots', $duplicateSlotRequest['/nino/http/response']['statusCode'] === 400 );

$tampered = $fresh['segments'];
foreach( $tampered as &$segment )
	if( $segment['type'] === 'raw' ) {
		$segment['source'] .= 'tampered';
		break;
	}
unset( $segment );
post( [ 'name' => 'page-home', 'revision' => $fresh['revision'], 'segments' => $tampered ] );
$tamperRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $tamperRequest );
check( 'rejects changes to locked page-frame source', $tamperRequest['/nino/http/response']['statusCode'] === 400 );

post( [ 'name' => 'page-home', 'revision' => $fresh['revision'], 'segments' => [ 'not-an-array' ] ] );
$shapeRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $shapeRequest );
check( 'rejects malformed segment shapes without a type error', $shapeRequest['/nino/http/response']['statusCode'] === 400 );

$duplicates = $fresh['segments'];
$duplicateSlots = array_keys( array_filter( $duplicates, fn( array $segment ): bool => $segment['type'] === 'section' ) );
$duplicates[$duplicateSlots[1]]['source'] = preg_replace( '/\bid=("|\')[^"\']+\1/', 'id="services"', $duplicates[$duplicateSlots[1]]['source'], 1 );
post( [ 'name' => 'page-home', 'revision' => $fresh['revision'], 'segments' => $duplicates ] );
$duplicateRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $duplicateRequest );
check( 'rejects duplicate non-empty section ids', $duplicateRequest['/nino/http/response']['statusCode'] === 409 );

file_put_contents( $sandbox. '/templates/page-home.tpl', $reorderedSource. "\n<!-- external edit -->\n" );
post( [ 'name' => 'page-home', 'revision' => $fresh['revision'], 'segments' => $fresh['segments'] ] );
$staleRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $staleRequest );
check( 'rejects stale saves after an external edit', $staleRequest['/nino/http/response']['statusCode'] === 409 );

post( [ 'name' => '../../config' ] );
$traversalRequest = response();
\Nino\Templates\Documents::apiLoad( $appData, $traversalRequest );
check( 'rejects template path traversal', $traversalRequest['/nino/http/response']['statusCode'] === 404 );

post( [
	'filename' => 'page-services.tpl',
	'displayName' => 'Services Overview',
	'header' => '/templates/section-card',
	'footer' => '',
	'pageMotion' => 'on',
] );
$createRequest = response();
\Nino\Templates\Documents::apiCreate( $appData, $createRequest );
$createdPage = file_get_contents( $sandbox. '/templates/page-services.tpl' );
check( 'creates a new page template on demand', $createRequest['/nino/http/response']['statusCode'] === 200 && $createdPage !== false );
$createdSegments = array_values( array_filter( \Nino\Templates\SectionDocument::split( (string) $createdPage )['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
check( 'new templates start with name/VPA metadata and chosen header/footer slots', str_starts_with( (string) $createdPage, "<!-- nino:template-name Services Overview -->\n<!-- nino:template-vpa on -->\n" )
	&& count( $createdSegments ) === 2 && $createdSegments[0]['template'] === 'section-card' && $createdSegments[1]['path'] === '' );
post( [ 'name' => 'page-services' ] );
$createdLoadRequest = response();
\Nino\Templates\Documents::apiLoad( $appData, $createdLoadRequest );
$createdLoad = $createdLoadRequest['/nino/http/response']['body'];
check( 'loads persisted template name and VPA outside the canvas', $createdLoad['displayName'] === 'Services Overview' && $createdLoad['pageMotion'] === 'on' && count( $createdLoad['segments'] ) === 2 );
post( [ 'filename' => 'page-services.tpl', 'displayName' => 'Duplicate' ] );
$duplicateCreateRequest = response();
\Nino\Templates\Documents::apiCreate( $appData, $duplicateCreateRequest );
check( 'refuses to overwrite an existing page template', $duplicateCreateRequest['/nino/http/response']['statusCode'] === 409 );
post( [ 'filename' => '../unsafe.tpl', 'displayName' => 'Unsafe' ] );
$invalidCreateRequest = response();
\Nino\Templates\Documents::apiCreate( $appData, $invalidCreateRequest );
check( 'rejects an unsafe new-template filename', $invalidCreateRequest['/nino/http/response']['statusCode'] === 400 );
post( [ 'name' => 'page-services', 'confirmName' => 'page-other', 'revision' => $createdLoad['revision'] ] );
$unconfirmedDeleteRequest = response();
\Nino\Templates\Documents::apiDelete( $appData, $unconfirmedDeleteRequest );
check( 'requires an exact template name before deleting a file', $unconfirmedDeleteRequest['/nino/http/response']['statusCode'] === 400 && is_file( $sandbox. '/templates/page-services.tpl' ) );
post( [ 'name' => 'page-services', 'confirmName' => 'page-services', 'revision' => $createdLoad['revision'] ] );
$deleteRequest = response();
\Nino\Templates\Documents::apiDelete( $appData, $deleteRequest );
check( 'deletes exactly the revision the user confirmed', $deleteRequest['/nino/http/response']['statusCode'] === 200 && is_file( $sandbox. '/templates/page-services.tpl' ) === false );

echo "\n";


// --- Native content -------------------------------------------------------

echo "Content\n";

post( [ 'keys' => [ '/page-home/hero/title', '/page-home/hero/subtitle' ] ] );
$fieldsRequest = response();
\Nino\Templates\Content::apiFields( $appData, $fieldsRequest );
$fields = $fieldsRequest['/nino/http/response']['body'];
check( 'reads existing and missing native textfill values together', $fields['nativeLocale'] === 'en_US' && $fields['fields'][0]['value'] === 'Old title' && $fields['fields'][1]['exists'] === false );

post( [ 'items' => [
	[ 'key' => '/page-home/hero/title', 'value' => 'New title' ],
	[ 'key' => '/page-home/hero/subtitle', 'value' => 'New subtitle' ],
] ] );
$contentSaveRequest = response();
\Nino\Templates\Content::apiSave( $appData, $contentSaveRequest );
$nativeText = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$germanText = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'quick fill updates the native value and creates missing keys', $nativeText['[[/page-home/hero/title]]'] === 'New title' && $nativeText['[[/page-home/hero/subtitle]]'] === 'New subtitle' );
check( 'quick fill leaves translations untouched', $germanText['[[/page-home/hero/title]]'] === 'Alter Titel' && isset( $germanText['[[/page-home/hero/subtitle]]'] ) === false );

post( [ 'items' => [ [ 'key' => '../../config', 'value' => 'x' ] ] ] );
$invalidContentRequest = response();
\Nino\Templates\Content::apiSave( $appData, $invalidContentRequest );
check( 'rejects content keys outside /page-*/section/suffix', $invalidContentRequest['/nino/http/response']['statusCode'] === 400 );

\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', true );

post( [ 'module' => 'articles', 'uri' => 'home-services', 'title' => 'Home Services' ] );
$createTypeRequest = response();
\Nino\Templates\Content::apiCreateType( $appData, $createTypeRequest );
$createdType = \Nino\Filesystem::getFileContent( $appData, '/elements/home-services.php', [] );
check( 'creates a recommended Element Type through the shared Admin API', $createTypeRequest['/nino/http/response']['statusCode'] === 200 && isset( $createdType['model']['title'], $createdType['model']['linkLabel'] ) );

post( [ 'uri' => '/page-home/main-hero/background', 'label' => 'Main Hero Background' ] );
$createImageRequest = response();
\Nino\Templates\Content::apiCreateImage( $appData, $createImageRequest );
check( 'creates a background image slot with safe recommended dimensions', $createImageRequest['/nino/http/response']['statusCode'] === 200 && $appData['/nino/html/images']['/page-home/main-hero/background']['width'] === 1920 && $appData['/nino/html/images']['/page-home/main-hero/background']['height'] === 1080 );

post( [ 'uri' => '/arbitrary/slot', 'label' => 'Unsafe' ] );
$invalidImageRequest = response();
\Nino\Templates\Content::apiCreateImage( $appData, $invalidImageRequest );
check( 'refuses to create image slots outside a generated page section', $invalidImageRequest['/nino/http/response']['statusCode'] === 400 );

\Nino\Runtime::setSessionValue( $appData, './nino/admin/authed', false );

echo "\n";


// --- Bootstrap ------------------------------------------------------------

echo "Templates::init / guard\n";

$appData['/nino/http/routes']['GET://_templates'] = [ 'uri' => '/stale', 'statusCode' => 418 ];
$appData['/nino/http/routes']['POST://_templates'] = [ 'uri' => '/stale' ];
\Nino\Templates\Templates::init( $appData );
check( 'runtime GET route replaces persisted collisions', $appData['/nino/http/routes']['GET://_templates']['body'] === '[template /_templates/templates/page-index]' );
check( 'runtime POST route replaces persisted collisions', $appData['/nino/http/routes']['POST://_templates'] === [ 'uri' => '/_templates' ] );

$notAuthed = response();
check( 'guard shares the Admin session and rejects unauthenticated requests', \Nino\Templates\Templates::guard( $appData, $notAuthed ) === false && $notAuthed['/nino/http/response']['statusCode'] === 401 );

echo "\n";

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";
exit( $failures > 0 ? 1 : 0 );
