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
mkdir( $sandbox. '/private/templates', 0777, true );
mkdir( $sandbox. '/private/text', 0777, true );
mkdir( $sandbox. '/private/elements', 0777, true );
mkdir( $sandbox. '/public/.cache', 0777, true );
mkdir( $sandbox. '/public/assets', 0777, true );
mkdir( $sandbox. '/public/fonts/text', 0777, true );
file_put_contents( $sandbox. '/public/.cache/style.css', '/* stale-template-preview-css */' );
file_put_contents( $sandbox. '/public/fonts/text/preview.woff2', 'preview-font' );
file_put_contents( $sandbox. '/public/assets/style.preview.css', '/* template-preview-project-css */ @font-face{font-family:Preview;src:url("[[/nino/public]]/fonts/text/preview.woff2") format("woff2")} @font-face{font-family:Remote;src:url("https://example.invalid/remote.woff2")} .nino-section{display:block}' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$appData = [ './nino/uid' => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData['./nino/filesystem/path'] = $sandbox;
$appData['./nino/filesystem/configpath'] = $sandbox. '/private';
$appData['./nino/filesystem/contentpath'] = $sandbox. '/private';
$appData['./nino/filesystem/privatepath'] = $sandbox. '/private';
$appData['./nino/filesystem/publicpath'] = $sandbox. '/public';
$appData['/nino/dir'] = '';
$appData['/nino/locales/native'] = 'en_US';
$appData['/nino/locales/available'] = [ 'en_US', 'de_DE' ];
$appData['./nino/html/shortcodes'] = [];
$appData['/nino/html/images'] = [];
$appData['/nino/html/assets'] = [ '/.cache/style.css' => [ '/assets/style.preview.css' ] ];

\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/html/images' => [] ] );
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [ '[[/webpage/contact/uri]]' => '/contact' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/blacklist.php', [ '/webpage/contact/uri' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/en_US.php', [ '[[/page-home/hero/title]]' => 'Old title' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/page-home/hero/title]]' => 'Alter Titel' ] );

echo "Sandbox: $sandbox\n\n";


// --- Presets and Composer --------------------------------------------------

echo "Library / Composer\n";

$presets = \Nino\Templates\Library::presets();
$modules = \Nino\Templates\Composer::modules();

check( 'ships exactly the maintained named-area presets', array_keys( $presets ) === [ 'articles-grid', 'contact-form', 'content-section', 'cta-banner', 'feature-split', 'filterable-grid', 'fullscreen-image', 'image-banner', 'logo-bar', 'media-split-areas', 'newsletter-form', 'pricing-plans', 'process-timeline', 'static-accordion', 'static-list', 'static-table', 'template-include' ] );

// Library::presets() swallows a broken manifest so one bad preset cannot take
// the whole catalog down. That is right at runtime and wrong here: the check
// above then just reports a shorter list, and the first test to reach for the
// missing preset dies on a null far from the cause. Re-normalize each shipped
// manifest outside the try/catch so the authoring mistake names itself.
$presetLoadErrors = [];
foreach( glob( dirname( __DIR__ ). '/_templates/library/*/manifest.php' ) ?: [] as $manifestPath ) {
	$presetKey = basename( dirname( $manifestPath ) );
	try {
		\Nino\Templates\AreaComposer::normalizePreset( $presetKey, include $manifestPath, dirname( $manifestPath ) );
	} catch( \Throwable $error ) {
		$presetLoadErrors[] = $presetKey. ': '. $error->getMessage();
	}
}
check( 'every shipped manifest normalizes'. ( $presetLoadErrors === [] ? '' : ' - '. implode( ' | ', $presetLoadErrors ) ), $presetLoadErrors === [] );
check( 'every preset has searchable metadata and a normalized v3 contract', array_filter( $presets, fn( array $preset ): bool => $preset['name'] === ''
	|| $preset['category'] === ''
	|| $preset['tags'] === []
	|| $preset['version'] !== 3
	|| $preset['areas'] === []
	|| $preset['layouts'] === []
	|| $preset['componentCatalog'] === [] ) === [] );
$libraryRequest = response();
\Nino\Templates\Library::apiList( $appData, $libraryRequest );
$libraryBody = $libraryRequest['/nino/http/response']['body'];
$publicPresets = $libraryBody['presets'];
check( 'library API supplies editable v3 defaults without leaking Layout source', array_filter( $publicPresets, fn( array $preset ): bool => $preset['version'] === 3
	&& ( !isset( $preset['defaults']['areas'] ) || isset( $preset['_layouts'] ) ) ) === [] );
check( 'library API refreshes and embeds project CSS for request-free previews', str_contains( $libraryBody['previewCss'], 'template-preview-project-css' )
	&& str_contains( $libraryBody['previewCss'], 'stale-template-preview-css' ) === false );
check( 'sandbox previews inline local fonts and discard unresolved remote font rules', str_contains( $libraryBody['previewCss'], 'data:font/woff2;base64,'. base64_encode('preview-font') )
	&& str_contains( $libraryBody['previewCss'], '/fonts/text/preview.woff2' ) === false
	&& str_contains( $libraryBody['previewCss'], 'example.invalid' ) === false );

$areaPresetDirectory = $sandbox. '/area-preset';
mkdir( $areaPresetDirectory );
file_put_contents( $areaPresetDirectory. '/section.tpl', "[[area:first]]\n[[area:second]]\n" );
$multiAreaManifest = [
	'name' => 'Two collections', 'description' => 'Two independent repeatable areas.',
	'category' => 'Test', 'tags' => [ 'two', 'collections' ], 'version' => 3,
	'layouts' => [ 'default' => [ 'label' => 'Default', 'template' => 'section.tpl' ] ],
	'areas' => [
		'first' => [
			'label' => 'First', 'source' => 'elements', 'allowed' => [ 'title' ],
			'model' => [ 'title' => [ 'type' => 'string', 'locale' => true ] ],
			'recommend' => [ 'components' => [ [ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ] ] ],
		],
		'second' => [
			'label' => 'Second', 'source' => 'elements', 'allowed' => [ 'title' ],
			'model' => [ 'title' => [ 'type' => 'string', 'locale' => true ] ],
			'recommend' => [ 'components' => [ [ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ] ] ],
		],
	],
];
$multiAreaPreset = \Nino\Templates\AreaComposer::normalizePreset( 'two-collections', $multiAreaManifest, $areaPresetDirectory );
$multiAreaResult = \Nino\Templates\AreaComposer::compose(
	\Nino\Templates\AreaComposer::defaults( $multiAreaPreset, 'home', 'related' ),
	$multiAreaPreset
);
check( 'one preset can compose several independent Elements Areas', count( $multiAreaResult['content']['collections'] ) === 2
	&& $multiAreaResult['content']['collections'][0]['elementType'] === 'home-related-first'
	&& $multiAreaResult['content']['collections'][1]['elementType'] === 'home-related-second' );
file_put_contents( $areaPresetDirectory. '/unsafe.tpl', "<?php echo 'unsafe'; ?>\n[[area:first]]\n[[area:second]]\n" );
$unsafeManifest = $multiAreaManifest;
$unsafeManifest['layouts']['default']['template'] = 'unsafe.tpl';
check( 'Area manifests reject executable Layout source', throwsInvalidArgument( fn() => \Nino\Templates\AreaComposer::normalizePreset( 'unsafe-layout', $unsafeManifest, $areaPresetDirectory ) ) );

$dataAttributeManifest = [
	'name' => 'Data attributes', 'description' => 'Frontend behavior configured by declared attributes.',
	'category' => 'Test', 'tags' => [ 'data', 'attributes' ], 'version' => 3,
	'data' => [ 'vpa-delay' => '150ms', 'cover-width' => '80' ],
	'layouts' => [ 'default' => [ 'label' => 'Default', 'template' => 'section.tpl', 'data' => [ 'vpa-delay' => '300ms' ] ] ],
	'areas' => [
		'first' => [
			'label' => 'Cards', 'source' => 'elements', 'allowed' => [ 'title', 'button' ],
			'item' => [ 'tag' => 'article', 'class' => 'nino-article nino-autoheight', 'data' => [ 'autoheight-group' => 'cards-[[section:id]]', 'data-autoheight-mobile' => 'skip' ] ],
			'model' => [ 'title' => [ 'type' => 'string', 'locale' => true ] ],
			'render' => [ 'button' => [ 'class' => 'nino-modal-trigger', 'data' => [ 'Modal-Target' => 'contact "one" & <two>' ] ] ],
			'recommend' => [ 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'more', 'type' => 'button', 'bindings' => [ 'label' => 'title', 'href' => 'title' ] ],
			] ],
		],
		'second' => [
			'label' => 'Tabs', 'source' => 'single', 'allowed' => [ 'title' ],
			'container' => [ 'class' => 'nino-grid-100 nino-tabs', 'data' => [ 'tabs-target' => 'panel-1' ] ],
			'recommend' => [ 'components' => [ [ 'id' => 'title', 'type' => 'title' ] ] ],
		],
	],
];
$dataAttributePreset = \Nino\Templates\AreaComposer::normalizePreset( 'data-attributes', $dataAttributeManifest, $areaPresetDirectory );
$dataAttributeInput = \Nino\Templates\AreaComposer::defaults( $dataAttributePreset, 'home', 'stage' );
$dataAttributeSource = \Nino\Templates\AreaComposer::compose( $dataAttributeInput, $dataAttributePreset )['source'];
$dataAttributeSection = strtok( $dataAttributeSource, "\n" );
check( 'a Layout data attribute overrides the preset default on the section element', str_contains( $dataAttributeSection, 'data-cover-width="80"' )
	&& str_contains( $dataAttributeSection, 'data-vpa-delay="300ms"' )
	&& substr_count( $dataAttributeSource, 'data-vpa-delay' ) === 1 );
check( 'Areas emit declared data attributes on their container and collection item', str_contains( $dataAttributeSource, '<div class="nino-grid-100 nino-tabs" data-tabs-target="panel-1">' )
	&& str_contains( $dataAttributeSource, 'class="nino-article nino-autoheight" data-autoheight-group="cards-stage" data-autoheight-mobile="skip"' ) );
check( 'component render overrides carry escaped data attributes', str_contains( $dataAttributeSource, 'data-modal-target="contact &quot;one&quot; &amp; &lt;two&gt;"' ) );
$injectedInput = $dataAttributeInput;
$injectedInput['data'] = [ 'injected' => 'yes' ];
$injectedInput['areas']['first']['item'] = [ 'tag' => 'article', 'data' => [ 'injected' => 'yes' ] ];
$injectedInput['areas']['second']['container'] = [ 'tag' => 'div', 'data' => [ 'injected' => 'yes' ] ];
$injectedInput['areas']['second']['components'][0]['data'] = [ 'injected' => 'yes' ];
check( 'browser data never adds an attribute the manifest did not declare', str_contains( \Nino\Templates\AreaComposer::compose( $injectedInput, $dataAttributePreset )['source'], 'injected' ) === false );
$dataAttributeRejects = function( array $data, string $target ) use ( $dataAttributeManifest, $areaPresetDirectory ): bool {
	$manifest = $dataAttributeManifest;
	match( $target ) {
		'preset' => $manifest['data'] = $data,
		'layout' => $manifest['layouts']['default']['data'] = $data,
		'item' => $manifest['areas']['first']['item']['data'] = $data,
		'render' => $manifest['areas']['first']['render']['button']['data'] = $data,
	};
	return throwsInvalidArgument( fn() => \Nino\Templates\AreaComposer::normalizePreset( 'data-attributes', $manifest, $areaPresetDirectory ) );
};
check( 'declared data attributes cannot break out of the attribute or the data- namespace', $dataAttributeRejects( [ 'x" onclick="alert(1)' => '1' ], 'item' )
	&& $dataAttributeRejects( [ 'group name' => '1' ], 'render' )
	&& $dataAttributeRejects( [ 'group' => [ 'unsupported' ] ], 'item' )
	&& $dataAttributeRejects( [ 'group' => "line\nbreak" ], 'item' )
	&& $dataAttributeRejects( [ 'group' => str_repeat( 'a', 241 ) ], 'item' ) );
check( 'the frame keeps ownership of the data attributes it writes itself', $dataAttributeRejects( [ 'cover-height' => '50' ], 'preset' )
	&& $dataAttributeRejects( [ 'data-cover-height' => '50' ], 'layout' ) );
// A '[[field]]' in a data value is resolved per record by the [elements] pass,
// which escapes an ordinary field for the attribute but runs an 'html' => true
// one through sanitizeHtml() - and that leaves '"' intact, so editor content
// would close the attribute and land a live event handler on the card
$richFieldManifest = $dataAttributeManifest;
$richFieldManifest['areas']['first']['model']['blurb'] = [ 'type' => 'string', 'html' => true ];
$richFieldRejects = function( array $data, string $target ) use ( $richFieldManifest, $areaPresetDirectory ): bool {
	$manifest = $richFieldManifest;
	match( $target ) {
		'item' => $manifest['areas']['first']['item']['data'] = $data,
		'render' => $manifest['areas']['first']['render']['button']['data'] = $data,
	};
	return throwsInvalidArgument( fn() => \Nino\Templates\AreaComposer::normalizePreset( 'rich-field-data', $manifest, $areaPresetDirectory ) );
};
check( 'a data attribute cannot carry a rich text field, whose value is sanitized for content rather than for an attribute', $richFieldRejects( [ 'filter-item' => '[[blurb]]' ], 'item' )
	&& $richFieldRejects( [ 'filter-item' => 'prefix [[blurb]] suffix' ], 'render' )
	// ...while an ordinary field and the compile token stay available
	&& $richFieldRejects( [ 'filter-item' => '[[title]]' ], 'item' ) === false
	&& $richFieldRejects( [ 'group' => 'cards-[[section:id]]' ], 'item' ) === false );
check( 'repeatable articles recommend a localized CTA label', ( $modules['articles']['model']['linkLabel']['locale'] ?? false ) === true );
check( 'every curated preset composes with its defaults', array_filter( array_keys( $presets ), function( string $key ): bool {
	try {
		\Nino\Templates\Composer::compose( [ 'preset' => $key, 'pageId' => 'test', 'id' => str_replace( '_', '-', $key ) ] );
		return false;
	} catch( \Throwable ) {
		return true;
	}
} ) === [] );

$heroInput = \Nino\Templates\AreaComposer::defaults( $presets['fullscreen-image'], 'home', 'main-hero' );
$heroInput['pageMotion'] = 'on';
$heroInput['areas']['content']['components'][3]['bindings']['href'] = '/webpage/contact/uri';
$heroInput['areas']['content']['components'][3]['bindingSources']['href'] = 'textfill';
$hero = \Nino\Templates\Composer::compose( $heroInput );

check( 'composes one ordinary section', str_starts_with( $hero['source'], '<section' ) && str_contains( $hero['source'], '</section>' ) );
check( 'writes stable section metadata inside generated source', str_contains( $hero['source'], '<!-- nino:section {' ) );
check( 'derives textfill keys from page and section ids', in_array( '/page-home/main-hero/title', array_column( $hero['fields'], 'key' ), true ) && str_contains( $hero['source'], '[[/page-home/main-hero/title]]' ) );
check( 'reports the generated background image slot', ( $hero['images'][0]['slot'] ?? '' ) === 'background' );
check( 'inherits page motion into generated nino-vpa markup', preg_match( '/class="(?=[^"]*\bnino-grid-row\b)(?=[^"]*\bnino-vpa\b)[^"]*"/', $hero['source'] ) === 1 );
check( 'applies Area alignment without forcing it onto the section shell', str_contains( $hero['source'], 'nino-text-center' ) && str_contains( strtok( $hero['source'], "\n" ), 'nino-text-center' ) === false );
$contactBinding = array_values( array_filter( $hero['fields'], fn( array $field ): bool => $field['key'] === '/webpage/contact/uri' ) )[0] ?? null;
check( 'single-Area actions can reuse technical textfills without creating a new field', str_contains( $hero['source'], 'href="[[/webpage/contact/uri]]"' )
	&& ( $contactBinding['mode'] ?? '' ) === 'existing'
	&& ( $hero['spec']['areas']['content']['components'][3]['bindingSources']['href'] ?? '' ) === 'textfill' );
$missingHeroSources = \Nino\Templates\AreaComposer::defaults( $presets['fullscreen-image'], 'home', 'missing-sources' );
unset( $missingHeroSources['areas']['content']['components'][0]['bindingSources'] );
check( 'section metadata must declare every Single-Area binding source', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( $missingHeroSources ) ) );

$heroPreview = \Nino\Templates\Composer::preview( [
	'preset' => 'fullscreen-image', 'pageId' => 'preview', 'id' => 'motion-hero', 'pageMotion' => 'on',
] );
check( 'preview strips VPA classes that would stay hidden without client scripts', $heroPreview !== null && str_contains( $heroPreview, 'nino-vpa' ) === false );
check( 'preview-only VPA cleanup never changes composed template source', str_contains( $hero['source'], 'nino-vpa' ) && str_contains( $hero['source'], 'nino-vpa--visible' ) === false );

$articleInput = \Nino\Templates\AreaComposer::defaults( $presets['articles-grid'], 'home', 'services' );
$articleInput['areas']['articles']['style'] = 'four-columns';
$articleInput['areas']['articles']['source'] = [
	'elementMode' => 'existing',
	'elementType' => 'services',
	'shortcode' => [ 'locale' => '', 'callback' => '', 'limit' => 4, 'query' => '' ],
];
$articleInput['areas']['articles']['components'][0]['bindings'] = [ 'src' => 'photo', 'alt' => 'name' ];
$articleInput['areas']['articles']['components'][1]['bindings'] = [ 'text' => 'name' ];
$articleInput['areas']['articles']['components'][2]['bindings'] = [ 'text' => 'copy' ];
$articleInput['areas']['articles']['components'][3]['bindings'] = [ 'label' => 'buttonLabel', 'href' => 'href' ];
$articles = \Nino\Templates\Composer::compose( $articleInput );

check( 'binds a repeatable Area to a chosen element type with all shortcode arguments', str_contains( $articles['source'], '[elements /services locale="" callback="" limit="4" query=""]' ) );
check( 'maps ordered components to compatible existing model fields', str_contains( $articles['source'], '[[name]]' )
	&& str_contains( $articles['source'], '[[photo]]' )
	&& str_contains( $articles['source'], '[[buttonLabel]]' ) );
check( 'returns each independently creatable collection and the complete recommended schema', isset( $articles['content']['collections'][0]['model']['image'], $articles['content']['collections'][0]['model']['linkLabel'] ) );
check( 'generated Elements images use Nino image storage under the public content prefix', str_contains( $articles['source'], '[[/nino/public]]/images/[[photo]]' ) && str_contains( $articles['source'], '/uploads/' ) === false );
$missingArticleSources = $articleInput;
unset( $missingArticleSources['areas']['articles']['components'][1]['bindingSources'] );
check( 'section metadata must declare every Elements-Area binding source', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( $missingArticleSources ) ) );

$sharedActionInput = $articleInput;
$sharedActionInput['id'] = 'shared-action';
$sharedActionInput['areas']['articles']['components'][3]['bindings']['href'] = '/webpage/contact/uri';
$sharedActionInput['areas']['articles']['components'][3]['bindingSources']['href'] = 'textfill';
$sharedActionInput['areas']['articles']['components'][3]['bindings']['label'] = 'Contact [now]';
$sharedActionInput['areas']['articles']['components'][3]['bindingSources']['label'] = 'fixed';
$sharedAction = \Nino\Templates\Composer::compose( $sharedActionInput );
check( 'repeatable components can combine Element fields, shared textfills and escaped fixed values', str_contains( $sharedAction['source'], 'href="[[/webpage/contact/uri]]"' )
	&& str_contains( $sharedAction['source'], 'Contact &#91;now&#93;' )
	&& str_contains( $sharedAction['source'], '[[name]]' ) );
$unsafeActionInput = $sharedActionInput;
$unsafeActionInput['areas']['articles']['components'][3]['bindings']['href'] = 'javascript:alert(1)';
$unsafeActionInput['areas']['articles']['components'][3]['bindingSources']['href'] = 'fixed';
check( 'fixed URL bindings reject executable schemes', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( $unsafeActionInput ) ) );
$obfuscatedUnsafeActionInput = $unsafeActionInput;
$obfuscatedUnsafeActionInput['areas']['articles']['components'][3]['bindings']['href'] = "java\nscript:alert(1)";
check( 'fixed URL bindings reject control-character scheme obfuscation', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( $obfuscatedUnsafeActionInput ) ) );
$invalidImageSourceInput = $articleInput;
$invalidImageSourceInput['areas']['articles']['components'][0]['bindings']['src'] = '/page-home/services/shared-image';
$invalidImageSourceInput['areas']['articles']['components'][0]['bindingSources']['src'] = 'textfill';
check( 'Elements images cannot bypass compatible field mappings', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( $invalidImageSourceInput ) ) );

$textOnlyInput = $articleInput;
$textOnlyInput['id'] = 'plain-services';
$textOnlyInput['areas']['articles']['components'] = array_values( array_filter(
	$textOnlyInput['areas']['articles']['components'],
	fn( array $component ): bool => in_array( $component['type'], [ 'title', 'description' ], true )
) );
$textOnly = \Nino\Templates\Composer::compose( $textOnlyInput );
check( 'component order changes markup independently from the four-column Area Style', str_contains( $textOnly['source'], 'nino-grid-m-25' )
	&& str_contains( $textOnly['source'], '[[name]]' )
	&& str_contains( $textOnly['source'], '<img' ) === false
	&& str_contains( $textOnly['source'], '<a ' ) === false );
check( 'one v3 metadata comment preserves the complete graphical area model', substr_count( $textOnly['source'], '<!-- nino:section ' ) === 1
	&& $textOnly['spec']['version'] === 3
	&& count( $textOnly['spec']['areas']['articles']['components'] ) === 2 );
check( 'the Articles preset equalizes its card text per section so the calls to action line up', str_contains( $articles['source'], '<h3 class="nino-article-title nino-autoheight" data-autoheight-group="article-title-services" data-autoheight-mobile="skip">' )
	&& str_contains( $articles['source'], '<div class="nino-article-descr nino-autoheight" data-autoheight-group="article-descr-services" data-autoheight-mobile="skip">' )
	&& str_contains( $textOnly['source'], 'data-autoheight-group="article-title-plain-services"' )
	&& str_contains( $textOnly['source'], 'article-title-services"' ) === false );

$articlePreview = \Nino\Templates\Composer::preview( [
	'preset' => 'articles-grid', 'pageId' => 'preview', 'id' => 'articles',
	'areas' => [ 'articles' => [ 'style' => 'two-columns', 'source' => [ 'shortcode' => [ 'limit' => 2 ] ] ] ],
] );
check( 'renders real preview HTML with deterministic text and image fixtures', $articlePreview !== null && str_contains( $articlePreview, '<section' ) && str_contains( $articlePreview, 'data:image/svg+xml' ) && str_contains( $articlePreview, 'Thoughtful item 1' ) );
check( 'preview HTML contains no unresolved project shortcodes', $articlePreview !== null && str_contains( $articlePreview, '[[' ) === false && str_contains( $articlePreview, '[elements' ) === false && str_contains( $articlePreview, '[image' ) === false );
$twoColumnPreview = \Nino\Templates\Composer::preview( [
	'preset' => 'articles-grid', 'pageId' => 'preview', 'id' => 'two-columns',
	'areas' => [ 'articles' => [ 'style' => 'two-columns' ] ],
] );
$fourColumnPreview = \Nino\Templates\Composer::preview( [
	'preset' => 'articles-grid', 'pageId' => 'preview', 'id' => 'four-columns',
	'areas' => [ 'articles' => [ 'style' => 'four-columns' ] ],
] );
check( 'named-area preview mirrors the selected column count', substr_count( $twoColumnPreview ?? '', '<article' ) === 2
	&& substr_count( $fourColumnPreview ?? '', '<article' ) === 4 );

// --- filterable-grid: the [elementvalues]-driven category filter --------

$filterInput = \Nino\Templates\AreaComposer::defaults( $presets['filterable-grid'], 'home', 'services' );
$filterInput['areas']['elements']['source'] = [
	'elementMode' => 'existing',
	'elementType' => 'services',
	'shortcode' => [ 'locale' => '', 'callback' => '', 'limit' => -1, 'query' => '' ],
];
$filterSection = \Nino\Templates\Composer::compose( $filterInput );

check( 'filterable-grid composes one ordinary section with both areas resolved', str_starts_with( $filterSection['source'], '<section' )
	&& str_contains( $filterSection['source'], '</section>' )
	&& str_contains( $filterSection['source'], '[[area:' ) === false );
check( 'the grid Area binds to the chosen collection with no limit, so the filter has the whole set to work with', str_contains( $filterSection['source'], '[elements /services locale="" callback="" limit="-1" query=""]' ) );
check( 'the hand-written filter block survives compilation untouched, [elementvalues] included', str_contains( $filterSection['source'], '[elementvalues /services key="category" sort="value"]' )
	&& str_contains( $filterSection['source'], 'class="nino-filter-nav"' )
	&& str_contains( $filterSection['source'], 'data-filter-value=""' ) );
check( 'each card is stamped with its own category as still-unresolved [[category]] text - the ordinary per-request [elements] render pass fills it in, not the compiler (AGENTS.md §10.3)', str_contains( $filterSection['source'], 'data-filter-item="[[category]]"' ) );
check( 'the card keeps its nino-article family styling, including the image class the catalog default omits', str_contains( $filterSection['source'], 'class="nino-article-img nino-article-img--maxheight"' )
	&& str_contains( $filterSection['source'], 'class="nino-article-descr"' ) );

// The button loop and the card loop have to read one collection, and the
// static block cannot know its name: a new Area mints '<page>-<section>-<area>'
// and Edit Section can rebind it later. [[section:collection:<area>]] resolves
// to whatever the Area is actually bound to, so all three cases agree - the
// plain first insert included, which a hard-coded slug always got wrong.
$collectionOf = function( string $source ): array {
	preg_match( '#\[elements /([a-z0-9_-]+) #', $source, $cards );
	preg_match( '#\[elementvalues /([a-z0-9_-]+) #', $source, $buttons );
	return [ $cards[1] ?? 'cards?', $buttons[1] ?? 'buttons?' ];
};
$defaultInsert = $collectionOf( \Nino\Templates\Composer::compose( [ 'preset' => 'filterable-grid', 'pageId' => 'home', 'id' => 'work' ] )['source'] );
$reboundInput = $filterInput;
$reboundInput['id'] = 'services-rebound';
$reboundInput['areas']['elements']['source']['elementType'] = 'consulting';
$rebound = $collectionOf( \Nino\Templates\Composer::compose( $reboundInput )['source'] );
check( 'the filter buttons read the same collection as the cards - on a new Area, an existing one, and after a rebind', $defaultInsert === [ 'home-work-elements', 'home-work-elements' ]
	&& $collectionOf( $filterSection['source'] ) === [ 'services', 'services' ]
	&& $rebound === [ 'consulting', 'consulting' ] );
check( 'a collection token naming something that is not an Elements area of the preset is refused', throwsInvalidArgument( function() use ( $areaPresetDirectory, $multiAreaManifest ): void {
	file_put_contents( $areaPresetDirectory. '/bad-collection.tpl', "[[area:first]]\n[[section:collection:nope]]\n[[area:second]]\n" );
	$manifest = $multiAreaManifest;
	$manifest['layouts']['default']['template'] = 'bad-collection.tpl';
	\Nino\Templates\AreaComposer::normalizePreset( 'bad-collection', $manifest, $areaPresetDirectory );
} ) );

$filterPreview = \Nino\Templates\Composer::preview( [ 'preset' => 'filterable-grid', 'pageId' => 'preview', 'id' => 'grid' ] );
check( 'renders a real preview with sample filter buttons, no raw shortcode text left visible', $filterPreview !== null
	&& str_contains( $filterPreview, '[elementvalues' ) === false
	&& str_contains( $filterPreview, '[[' ) === false
	&& substr_count( $filterPreview, 'nino-filter-btn' ) === 4  // "All" + 3 sample categories
	&& str_contains( $filterPreview, '<article' ) === true );

$fullscreen = \Nino\Templates\Composer::compose( [
	'preset' => 'fullscreen-image', 'pageId' => 'home', 'id' => 'stage', 'layout' => 'parallax',
] );
check( 'Layout changes real markup and can recommend a matching frame', str_contains( $fullscreen['source'], 'nino-parallex' )
	&& $fullscreen['effective']['layout'] === 'parallax'
	&& $fullscreen['effective']['frame']['background'] === 'parallax'
	&& ( $fullscreen['images'][0]['key'] ?? '' ) === '/page-home/stage/background' );

$backgroundInput = \Nino\Templates\AreaComposer::defaults( $presets['fullscreen-image'], 'home', 'stage' );
$backgroundInput['frame']['backgroundImageSource'] = 'image';
$backgroundInput['frame']['backgroundImage'] = '/shared/hero-image';
$existingBackground = \Nino\Templates\Composer::compose( $backgroundInput );
check( 'a chosen existing image slot survives into the composed background', str_contains( $existingBackground['source'], '[image /shared/hero-image alt=""]' )
	&& ( $existingBackground['images'][0]['key'] ?? '' ) === '/shared/hero-image'
	&& ( $existingBackground['images'][0]['mode'] ?? '' ) === 'existing' );
$fixedBackgroundInput = $backgroundInput;
$fixedBackgroundInput['frame']['backgroundImageSource'] = 'fixed';
$fixedBackgroundInput['frame']['backgroundImage'] = '[[/nino/public]]/images/demo-00.jpg';
$fixedBackground = \Nino\Templates\Composer::compose( $fixedBackgroundInput );
check( 'a fixed background writes a plain image tag and requests no image slot', str_contains( $fixedBackground['source'], '<img src="[[/nino/public]]/images/demo-00.jpg" alt="">' )
	&& $fixedBackground['images'] === []
	&& str_contains( $fixedBackground['source'], '[image ' ) === false );
check( 'the fixed background choice round-trips through the section metadata', ( $fixedBackground['spec']['frame']['backgroundImageSource'] ?? '' ) === 'fixed'
	&& \Nino\Templates\Composer::compose( $fixedBackground['spec'] )['source'] === $fixedBackground['source'] );
$missingBackgroundSource = $backgroundInput;
unset( $missingBackgroundSource['frame']['backgroundImageSource'] );
check( 'a supplied background value requires its explicit source', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( $missingBackgroundSource ) ) );
$rejectsBackground = function( string $source, string $value ) use ( $backgroundInput ): bool {
	$input = $backgroundInput;
	$input['frame']['backgroundImageSource'] = $source;
	$input['frame']['backgroundImage'] = $value;
	return throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( $input ) );
};
check( 'a fixed background rejects executable schemes, markup and shortcode brackets', $rejectsBackground( 'fixed', 'javascript:alert(1)' )
	&& $rejectsBackground( 'fixed', '/hero.jpg" onerror="alert(1)' )
	&& $rejectsBackground( 'fixed', '[elements /x]' )
	&& $rejectsBackground( 'fixed', '/images/../../private/config.php' )
	&& $rejectsBackground( 'fixed', '//example.invalid/hero.jpg' )
	&& $rejectsBackground( 'image', '/shared/../etc' ) );
check( 'a fixed background still allows the public prefix and ordinary project paths', str_contains( $fixedBackground['source'], '[[/nino/public]]' )
	&& $rejectsBackground( 'fixed', '/images/hero.jpg' ) === false
	&& $rejectsBackground( 'fixed', 'https://cdn.example.com/hero.jpg' ) === false );

$templateInput = \Nino\Templates\AreaComposer::defaults( $presets['articles-grid'], 'home', 'with-form' );
$templateInput['areas']['action']['components'][] = [
	'id' => 'form', 'type' => 'template', 'style' => 'auto', 'settings' => [ 'target' => 'same' ],
	'bindings' => [ 'path' => '/templates/contact-form' ],
	'bindingSources' => [ 'path' => 'template' ],
];
$templateSection = \Nino\Templates\Composer::compose( $templateInput );
check( 'Template is an ordered Area input rather than a gallery pseudo-section', str_contains( $templateSection['source'], '[template /templates/contact-form]' ) );

$includeInput = \Nino\Templates\AreaComposer::defaults( $presets['template-include'], 'home', 'contact-form' );
$includeInput['areas']['include']['components'][0]['bindings']['path'] = '/templates/contact-form';
$includeSection = \Nino\Templates\Composer::compose( $includeInput );
check( 'the focused reusable-template preset emits one normal managed section', str_contains( $includeSection['source'], '[template /templates/contact-form]' )
	&& substr_count( $includeSection['source'], '<section' ) === 1 );

$splitInput = \Nino\Templates\AreaComposer::defaults( $presets['media-split-areas'], 'home', 'story' );
$splitInput['layout'] = 'media-right';
$splitSection = \Nino\Templates\Composer::compose( $splitInput );
$splitPreview = \Nino\Templates\Composer::preview( [ 'preset' => 'media-split-areas', 'pageId' => 'preview', 'id' => 'story' ] );
check( 'a bound alt text does not leave its shortcode tail standing in the preview', $splitPreview !== null
	&& str_contains( $splitPreview, ']"]' ) === false
	&& str_contains( $splitPreview, '[image' ) === false
	&& substr_count( $splitPreview, '<img ' ) === 1 );
check( 'media split Layouts change semantic Area order without duplicating presets', strpos( $splitSection['source'], 'nino-p-2' ) !== false
	&& strpos( $splitSection['source'], 'nino-img-cover' ) !== false
	&& strpos( $splitSection['source'], 'nino-p-2' ) < strpos( $splitSection['source'], 'nino-img-cover' )
	&& count( $splitSection['images'] ) === 1 );

$everyLayout = [];
foreach( $presets as $presetKey => $preset )
	foreach( array_keys( $preset['layouts'] ) as $layoutKey )
		try {
			$everyLayout[$presetKey. '/'. $layoutKey] = \Nino\Templates\Composer::compose( [ 'preset' => $presetKey, 'pageId' => 'home', 'id' => $presetKey, 'layout' => $layoutKey ] )['source'];
		} catch( \Throwable $exception ) {
			$everyLayout[$presetKey. '/'. $layoutKey] = 'FAILED: '. $exception->getMessage();
		}
check( 'every Layout of every preset composes one section without an unresolved token', array_filter( $everyLayout, fn( string $source ): bool => str_starts_with( $source, '<section' ) === false
	|| substr_count( $source, '<section' ) !== 1
	|| str_contains( $source, '[[area:' )
	|| str_contains( $source, '[[section:id]]' ) ) === [] );
check( 'no Layout nests a second grid row inside the one the compiler writes', array_filter( $everyLayout, fn( string $source ): bool => substr_count( $source, 'class="nino-grid-row' ) > 1 ) === [] );
// One namespace, everywhere: the Builder, the presets and the design system itself
// all speak nino-*. The lookbehind keeps '-ui-'/'-js-' inside identifiers out of it,
// the lookahead the CSS system font keywords that merely look like classes.
$legacyClass = '/(?<![-\\w])(?:ui|js|sc)-(?!monospace|sans-serif|serif|rounded)[a-z0-9]/';
check( 'the Builder, the presets and the design system carry no legacy class prefix', array_filter( $everyLayout, fn( string $source ): bool => preg_match( $legacyClass, $source ) === 1 ) === []
	&& preg_match( $legacyClass, (string) file_get_contents( __DIR__. '/../_nino/Nino.css' ) ) === 0
	&& array_filter( $presets, fn( array $preset ): bool => preg_match( $legacyClass, (string) json_encode( $preset ) ) === 1 ) === [] );

// filterable-grid's wrapper has to carry a layout rule of its own: it sits
// between .nino-grid-row and the cards, so without one the cards stop being
// flex children and their .nino-grid-m-* widths render as stacked blocks. A
// string assertion cannot see that, but it can see the rule is there at all -
// unlike a pure behaviour hook (.nino-autoheight, .nino-filter-item), which
// Nino.ui.js drives and which correctly has no rule.
check( 'the filter wrapper carries the layout rule its nested cards depend on', preg_match( '/\.nino-filter\s*\{[^}]*display:\s*flex/', (string) file_get_contents( __DIR__. '/../_nino/Nino.css' ) ) === 1 );
check( 'a component step is a modifier of whichever class the preset gave it', str_contains( \Nino\Templates\Composer::compose( array_merge(
	\Nino\Templates\AreaComposer::defaults( $presets['fullscreen-image'], 'home', 'loud-hero' ),
	[ 'areas' => [ 'content' => [ 'components' => [ [ 'id' => 'title', 'type' => 'title', 'style' => 'loud', 'bindings' => [ 'text' => 'title' ], 'bindingSources' => [ 'text' => 'new' ] ] ] ] ] ]
) )['source'], '<h2 class="nino-atf-title nino-atf-title--loud"' )
	&& str_contains( \Nino\Templates\Composer::compose( array_merge(
		\Nino\Templates\AreaComposer::defaults( $presets['content-section'], 'home', 'loud-copy' ),
		[ 'areas' => [ 'heading' => [ 'components' => [ [ 'id' => 'title', 'type' => 'title', 'style' => 'loud', 'bindings' => [ 'text' => 'title' ], 'bindingSources' => [ 'text' => 'new' ] ] ] ] ] ]
	) )['source'], '<h2 class="nino-section-title nino-section-title--loud"' )
	&& \Nino\Templates\AreaComposer::catalog()['description']['styles'] === [ 'auto', 'quiet', 'loud' ]
	&& str_contains( json_encode( $presets ), 'nino-font-big' ) === false );
check( 'the scrim is one choice per image layer rather than three levels of its own', \Nino\Templates\AreaComposer::choices()['overlay'] === [ 'auto', 'none', 'dim' ] );
check( 'the shipped image presets use that current overlay vocabulary directly', ( include __DIR__. '/../_templates/library/fullscreen-image/manifest.php' )['recommend']['frame']['overlay'] === 'dim'
	&& ( include __DIR__. '/../_templates/library/image-banner/manifest.php' )['recommend']['frame']['overlay'] === 'dim' );
check( 'overlay values outside the current vocabulary are rejected', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( [
	'preset' => 'fullscreen-image', 'pageId' => 'home', 'id' => 'invalid-overlay', 'frame' => [ 'overlay' => 'strong' ],
] ) ) );
check( 'every preset card is measured against the same preview viewport', array_filter( $presets, fn( array $preset ): bool => isset( $preset['previewHeight'] ) ) === [] );

$timeline = \Nino\Templates\Composer::compose( [ 'preset' => 'process-timeline', 'pageId' => 'home', 'id' => 'process' ] );
check( 'the timeline numbers its steps from the ordered list instead of storing the ordinal as content', str_contains( $timeline['source'], '<ol class="nino-timeline nino-timeline--counted">' )
	&& str_contains( $timeline['source'], '<li class="nino-timeline-step"><h4>[[title]]</h4>' )
	&& isset( $timeline['content']['collections'][0]['model']['step'] ) === false );
$stacked = \Nino\Templates\Composer::compose( [ 'preset' => 'process-timeline', 'pageId' => 'home', 'id' => 'process', 'layout' => 'stacked' ] );
check( 'its second Layout restacks the same steps instead of restyling the item', str_contains( $stacked['source'], 'nino-timeline--stacked' )
	&& str_contains( $stacked['source'], '<li class="nino-timeline-step">' ) );

$staticTable = \Nino\Templates\Composer::compose( [ 'preset' => 'static-table', 'pageId' => 'home', 'id' => 'hours', 'layout' => 'striped-elements' ] );
check( 'a static block reaches the section exactly as the Layout wrote it, loop and all', str_contains( $staticTable['source'], '<table class="nino-table nino-table--striped">' )
	&& str_contains( $staticTable['source'], '<tr><th>Service</th><th>Duration</th></tr>' )
	&& str_contains( $staticTable['source'], '[elements /example-rows limit="10"]' )
	&& str_contains( $staticTable['source'], '<tr><td>[[columnA]]</td><td>[[columnB]]</td></tr>' )
	&& $staticTable['content']['collections'] === [] );
check( 'its intro stays an ordinary textfill Area while the outro renders nothing at all', str_contains( $staticTable['source'], '[[/page-home/hours/title]]' )
	&& str_contains( $staticTable['source'], 'nino-mt-3' ) === false
	&& preg_match( '/\n[\t ]*\n/', $staticTable['source'] ) !== 1 );
$staticOutro = \Nino\Templates\Composer::compose( [
	'preset' => 'static-table', 'pageId' => 'home', 'id' => 'hours',
	'areas' => [ 'outro' => [ 'components' => [ [
		'id' => 'action', 'type' => 'button', 'style' => 'primary',
		'bindings' => [ 'label' => '', 'href' => '' ], 'bindingSources' => [ 'label' => 'new', 'href' => 'new' ],
	] ] ] ],
] );
check( 'and carries a closing action as soon as the outro gets one', str_contains( $staticOutro['source'], 'nino-mt-3' )
	&& str_contains( $staticOutro['source'], '[[/page-home/hours/action-label]]' ) );
$accordion = \Nino\Templates\Composer::compose( [ 'preset' => 'static-accordion', 'pageId' => 'home', 'id' => 'faq' ] );
check( 'a static block resolves [[section:id]], so two of them on one page stay independent', str_contains( $accordion['source'], 'name="faq-faq"' )
	&& str_contains( $accordion['source'], '[[section:id]]' ) === false );
$contact = \Nino\Templates\Composer::compose( [ 'preset' => 'contact-form', 'pageId' => 'home', 'id' => 'reach-us', 'layout' => 'split' ] );
check( 'the shipped forms keep their CSRF token, honeypot and per-section field ids', str_contains( $contact['source'], '[csrf]' )
	&& str_contains( $contact['source'], 'name="location"' )
	&& str_contains( $contact['source'], 'id="reach-us-email"' )
	&& str_contains( $contact['source'], 'for="reach-us-email"' )
	&& str_contains( $contact['source'], 'style="' ) === false );

$pricingLayouts = array_keys( $presets['pricing-plans']['layouts'] );
check( 'pricing offers the three- and four-column Layouts as real compositions', $pricingLayouts === [ 'equal', 'feature-middle', 'four', 'four-feature-first', 'four-feature-last' ]
	&& str_contains( $everyLayout['pricing-plans/four'], 'nino-pricing-row nino-pricing-row--four"' )
	&& str_contains( $everyLayout['pricing-plans/four-feature-first'], 'nino-pricing-row--four-first' )
	&& str_contains( $everyLayout['pricing-plans/four-feature-last'], 'nino-pricing-row--four-last' ) );

$pricing = \Nino\Templates\Composer::compose( [ 'preset' => 'pricing-plans', 'pageId' => 'home', 'id' => 'plans', 'layout' => 'feature-middle' ] );
check( 'pricing emphasis is a Layout, not a second collection or a hidden item class', str_contains( $pricing['source'], 'nino-pricing-row nino-pricing-row--feature-middle' )
	&& str_contains( $pricing['source'], '<div class="nino-pricing-item">' )
	&& str_contains( $pricing['source'], '<div class="nino-pricing-price"><strong>[[price]]</strong><span>[[suffix]]</span></div>' ) );

check( 'the banner uses the static background layer rather than the scripted cover', str_contains( $everyLayout['image-banner/plain'], 'nino-img-background' )
	&& str_contains( $everyLayout['image-banner/plain'], 'nino-cover' ) === false
	&& str_contains( $everyLayout['image-banner/plain'], 'data-cover-height' ) === false );
check( 'list and table tags are available to Areas that need them, scripts and media are not', throwsInvalidArgument( fn() => \Nino\Templates\AreaComposer::normalizePreset( 'unsafe-tag', array_replace_recursive( $multiAreaManifest, [ 'areas' => [ 'first' => [ 'item' => [ 'tag' => 'iframe' ] ] ] ] ), $areaPresetDirectory ) )
	&& \Nino\Templates\AreaComposer::normalizePreset( 'list-tag', array_replace_recursive( $multiAreaManifest, [ 'areas' => [ 'first' => [ 'item' => [ 'tag' => 'li' ] ] ] ] ), $areaPresetDirectory )['areas']['first']['item']['tag'] === 'li' );

check( 'rejects invalid page ids', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( [ 'preset' => 'articles-grid', 'pageId' => '../home', 'id' => 'intro' ] ) ) );
check( 'rejects invalid Area styles and element type paths', throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( [
	'preset' => 'articles-grid', 'pageId' => 'home', 'id' => 'intro',
	'areas' => [ 'articles' => [ 'style' => 'unknown' ] ],
] ) ) && throwsInvalidArgument( fn() => \Nino\Templates\Composer::compose( [
	'preset' => 'articles-grid', 'pageId' => 'home', 'id' => 'intro',
	'areas' => [ 'articles' => [ 'source' => [ 'elementType' => '../items' ] ] ],
] ) ) );

echo "\n";


// --- Lossless SectionDocument --------------------------------------------

echo "SectionDocument\n";

$source = "[template /templates/html-header]\n"
	. "<!-- <section id=\"not-real\"> -->\n"
	. "<?php \$sample = '<section>not markup either</section>'; ?>\n"
	. "<script>const sample = '</scripture><section>not markup</section>';</script>\n"
	. "<section id=\"hero\" class=\"nino-section\">\n\t<section class=\"nested\"><p>Nested</p></section>\n</section>\n"
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
file_put_contents( $sandbox. '/private/templates/page-home.tpl', $page );
file_put_contents( $sandbox. '/private/templates/section-card.tpl', '<section></section>' );
file_put_contents( $sandbox. '/private/templates/html-header.tpl', '<!doctype html>' );

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

file_put_contents( $sandbox. '/private/templates/page-2026.home.tpl', $page );
$variantListRequest = response();
\Nino\Templates\Documents::apiList( $appData, $variantListRequest );
$variant = array_values( array_filter( $variantListRequest['/nino/http/response']['body']['documents'], fn( array $document ): bool => $document['name'] === 'page-2026.home' ) )[0] ?? [];
check( 'derives a valid distinct id from dotted or number-prefixed page names', ( $variant['pageId'] ?? '' ) === 'p-2026-home' );
unlink( $sandbox. '/private/templates/page-2026.home.tpl' );

$bareSource = "<section id=\"bare\"></section>\n";
file_put_contents( $sandbox. '/private/templates/page-bare.tpl', $bareSource );
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
	&& substr_count( (string) file_get_contents( $sandbox. '/private/templates/page-bare.tpl' ), 'nino:template-slot' ) === 2
	&& str_starts_with( (string) file_get_contents( $sandbox. '/private/templates/page-bare.tpl' ), '<!-- nino:template-name Bare -->' )
	&& str_contains( (string) file_get_contents( $sandbox. '/private/templates/page-bare.tpl' ), '[template ' ) === false );
unlink( $sandbox. '/private/templates/page-bare.tpl' );

post( [ 'name' => 'page-home' ] );
$loadRequest = response();
\Nino\Templates\Documents::apiLoad( $appData, $loadRequest );
$loaded = $loadRequest['/nino/http/response']['body'];

check( 'loads lossless segments with an optimistic revision', count( $loaded['segments'] ) >= 4 && strlen( $loaded['revision'] ) === 64 );
check( 'loads a valid page as editable', $loaded['readonly'] === null );
check( 'an unmarked hand-written template receives a stable display name and inherited VPA', $loaded['displayName'] === 'Home' && $loaded['pageMotion'] === 'on' );
$loadedSlots = array_values( array_filter( $loaded['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
check( 'recognizes an exact html-header/html-footer frame as fixed settings slots', count( $loadedSlots ) === 2 && $loadedSlots[0]['slot'] === 'header' && $loadedSlots[1]['slot'] === 'footer' );

post( [ 'name' => 'page-home', 'revision' => $loaded['revision'], 'segments' => $loaded['segments'] ] );
$roundTripRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $roundTripRequest );
check( 'saving untouched segments succeeds', $roundTripRequest['/nino/http/response']['statusCode'] === 200 );
check( 'the first deliberate save adds inert markers around recognized shell includes', str_contains( (string) file_get_contents( $sandbox. '/private/templates/page-home.tpl' ), '<!-- nino:template-slot header -->' )
	&& str_contains( (string) file_get_contents( $sandbox. '/private/templates/page-home.tpl' ), '<!-- nino:template-slot footer -->' )
	&& str_starts_with( (string) file_get_contents( $sandbox. '/private/templates/page-home.tpl' ), "<!-- nino:template-name Home -->\n<!-- nino:template-vpa on -->\n" ) );
$loaded['revision'] = $roundTripRequest['/nino/http/response']['body']['revision'];

$sectionSlots = array_keys( array_filter( $loaded['segments'], fn( array $segment ): bool => $segment['type'] === 'section' ) );
$reordered = $loaded['segments'];
[ $reordered[$sectionSlots[0]], $reordered[$sectionSlots[1]] ] = [ $reordered[$sectionSlots[1]], $reordered[$sectionSlots[0]] ];
post( [ 'name' => 'page-home', 'revision' => $loaded['revision'], 'segments' => $reordered ] );
$reorderRequest = response();
\Nino\Templates\Documents::apiSave( $appData, $reorderRequest );
$reorderedSource = file_get_contents( $sandbox. '/private/templates/page-home.tpl' );
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

file_put_contents( $sandbox. '/private/templates/page-home.tpl', $reorderedSource. "\n<!-- external edit -->\n" );
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
$createdPage = file_get_contents( $sandbox. '/private/templates/page-services.tpl' );
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
post( [ 'id' => 'page-old-field', 'displayName' => 'Missing filename' ] );
$missingFilenameRequest = response();
\Nino\Templates\Documents::apiCreate( $appData, $missingFilenameRequest );
check( 'requires the current filename field when creating a template', $missingFilenameRequest['/nino/http/response']['statusCode'] === 400 );
post( [ 'filename' => '../unsafe.tpl', 'displayName' => 'Unsafe' ] );
$invalidCreateRequest = response();
\Nino\Templates\Documents::apiCreate( $appData, $invalidCreateRequest );
check( 'rejects an unsafe new-template filename', $invalidCreateRequest['/nino/http/response']['statusCode'] === 400 );
post( [ 'name' => 'page-services', 'confirmName' => 'page-other', 'revision' => $createdLoad['revision'] ] );
$unconfirmedDeleteRequest = response();
\Nino\Templates\Documents::apiDelete( $appData, $unconfirmedDeleteRequest );
check( 'requires an exact template name before deleting a file', $unconfirmedDeleteRequest['/nino/http/response']['statusCode'] === 400 && is_file( $sandbox. '/private/templates/page-services.tpl' ) );
post( [ 'name' => 'page-services', 'confirmName' => 'page-services', 'revision' => $createdLoad['revision'] ] );
$deleteRequest = response();
\Nino\Templates\Documents::apiDelete( $appData, $deleteRequest );
check( 'deletes exactly the revision the user confirmed', $deleteRequest['/nino/http/response']['statusCode'] === 200 && is_file( $sandbox. '/private/templates/page-services.tpl' ) === false );

echo "\n";


// --- Native content -------------------------------------------------------

echo "Content\n";

post( [] );
$keysRequest = response();
\Nino\Templates\Content::apiKeys( $appData, $keysRequest );
$listedTextfills = $keysRequest['/nino/http/response']['body']['entries'];
$technicalTextfill = array_values( array_filter( $listedTextfills, fn( array $entry ): bool => $entry['key'] === '/webpage/contact/uri' ) )[0] ?? null;
check( 'lists content and blacklisted technical textfills for reusable Area bindings', in_array( '/page-home/hero/title', array_column( $listedTextfills, 'key' ), true )
	&& ( $technicalTextfill['blacklisted'] ?? false ) === true );
check( 'a page uri written by /_install or /_admin is offered as a global technical value', ( $technicalTextfill['global'] ?? false ) === true
	&& ( $technicalTextfill['value'] ?? '' ) === '/contact' );

post( [ 'keys' => [ '/page-home/hero/title', '/page-home/hero/subtitle', '/webpage/contact/uri' ] ] );
$fieldsRequest = response();
\Nino\Templates\Content::apiFields( $appData, $fieldsRequest );
$fields = $fieldsRequest['/nino/http/response']['body'];
check( 'reads existing, missing and technical native textfill values together', $fields['nativeLocale'] === 'en_US'
	&& $fields['fields'][0]['value'] === 'Old title'
	&& $fields['fields'][1]['exists'] === false
	&& $fields['fields'][2]['value'] === '/contact' );

post( [ 'items' => [
	[ 'key' => '/page-home/hero/title', 'value' => 'New title' ],
	[ 'key' => '/page-home/hero/subtitle', 'value' => 'New subtitle', 'create' => true ],
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

post( [ 'preset' => 'articles-grid', 'area' => 'articles', 'uri' => 'home-area-services', 'title' => 'Area Services' ] );
$createAreaTypeRequest = response();
\Nino\Templates\Content::apiCreateType( $appData, $createAreaTypeRequest );
$createdAreaType = \Nino\Filesystem::getFileContent( $appData, '/elements/home-area-services.php', [] );
check( 'creates only the Elements model declared by the requested Area', $createAreaTypeRequest['/nino/http/response']['statusCode'] === 200
	&& isset( $createdAreaType['model']['title'], $createdAreaType['model']['linkLabel'], $createdAreaType['model']['image'] ) );

post( [ 'uri' => '/page-home/main-hero/background', 'label' => 'Main Hero Background' ] );
$createImageRequest = response();
\Nino\Templates\Content::apiCreateImage( $appData, $createImageRequest );
check( 'creates a background image slot with safe recommended dimensions', $createImageRequest['/nino/http/response']['statusCode'] === 200 && $appData['/nino/html/images']['/page-home/main-hero/background']['width'] === 1920 && $appData['/nino/html/images']['/page-home/main-hero/background']['height'] === 1080 );

post( [
	'preset' => 'fullscreen-image', 'slot' => 'background',
	'uri' => '/page-home/area-stage/background', 'label' => 'Area Stage Background',
] );
$createAreaImageRequest = response();
\Nino\Templates\Content::apiCreateImage( $appData, $createAreaImageRequest );
check( 'creates a v3 frame image only for the selected preset slot', $createAreaImageRequest['/nino/http/response']['statusCode'] === 200
	&& $appData['/nino/html/images']['/page-home/area-stage/background']['width'] === 1920 );

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

$templateGet = response();
$templateGet['/nino/http/response']['header']['Content-Security-Policy'] = "default-src 'self'; style-src 'self' 'unsafe-inline'";
\Nino\Templates\Templates::handleGet( $appData, $templateGet );
check( 'Template Builder permits its sandboxed data-font previews without widening the global CSP', str_contains( $templateGet['/nino/http/response']['header']['Content-Security-Policy'], "font-src 'self' data:" ) );

$notAuthed = response();
check( 'guard shares the Admin session and rejects unauthenticated requests', \Nino\Templates\Templates::guard( $appData, $notAuthed ) === false && $notAuthed['/nino/http/response']['statusCode'] === 401 );

echo "\n";

\Nino\Filesystem::removeDir( $sandbox );

echo "$checks checks, $failures failed\n";
exit( $failures > 0 ? 1 : 0 );
