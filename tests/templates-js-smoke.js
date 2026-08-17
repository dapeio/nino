/**
 *	Dependency-free tests for Template Builder's pure client-side model helpers.
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let failures = 0;
let checks = 0;

function check( label, condition ) {
	checks++;
	if( condition ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

const callbacks = [];
const documentStub = {
	getElementById : function() { return null },
	querySelectorAll : function() { return [] },
	createElement : function() { return {} },
};
const Nino = {
	events : { bindCallback : function( event, callback ) { if( event === 'ready' ) callbacks.push( callback ) } },
	http : { sendRequest : function() {} },
};
const context = vm.createContext( {
	window : { Nino : Nino, clearTimeout : clearTimeout, setTimeout : setTimeout, confirm : function() { return true } },
	document : documentStub,
	Nino : Nino,
	console : console,
	Promise : Promise,
	Set : Set,
	URLSearchParams : URLSearchParams,
} );

[ 'script.js', 'sections.js', 'composer.js', 'area-composer.js' ].forEach( function( file ) {
	vm.runInContext( fs.readFileSync( path.join( __dirname, '../_templates/assets/', file ), 'utf8' ), context, { filename : file } );
} );

const model = Nino.templates.model;

console.log('Template Builder model');

const raw = { type : 'raw', source : '<!-- locked -->\n' };
const header = { type : 'slot', slot : 'header', path : '/templates/html-header', source : model.slotSource( 'header', '/templates/html-header' ) };
const footer = { type : 'slot', slot : 'footer', path : '/templates/html-footer', source : model.slotSource( 'footer', '/templates/html-footer' ) };
const include = { type : 'template', template : 'section-nav', source : '[template /templates/section-nav]\n', _clientId : 'include' };
const first = { type : 'section', source : '<section id="one"></section>', htmlId : 'one', _clientId : 'one' };
const second = { type : 'section', source : '<section id="two"></section>', htmlId : 'two', _clientId : 'two' };
const segments = [ header, raw, first, second, include, footer ];

check( 'finds HTML and ordinary template sections but excludes page slots', JSON.stringify( model.sectionIndices( segments ) ) === JSON.stringify( [ 2, 3, 4 ] ) );
check( 'moves canvas objects while locked raw and shell slots stay fixed', model.moveSection( segments, 'one', 1 ) && segments[0] === header && segments[1] === raw && segments[2] === second && segments[3] === first && segments[5] === footer );
check( 'moves ordinary template sections through the component model', model.moveSection( segments, 'include', -1 ) && segments[3] === include && segments[4] === first );
check( 'refuses to move beyond the canvas list', model.moveSection( segments, 'second', -1 ) === false );

const inserted = { type : 'section', source : '<section id="three"></section>', htmlId : 'three', _clientId : 'three' };
model.insertSection( segments, inserted, 'two' );
check( 'inserts after the intended section', segments.indexOf( inserted ) === segments.indexOf( second ) + 1 && segments.indexOf( raw ) === 1 );
check( 'removes a section without touching locked raw segments', model.removeSection( segments, 'three' ) && segments.includes( raw ) && segments.includes( footer ) );

const emptyPage = [ Object.assign( {}, header ), Object.assign( {}, footer ) ];
model.insertSection( emptyPage, inserted, null );
check( 'puts a first HTML section between fixed header and footer slots', emptyPage[0].slot === 'header' && emptyPage[1] === inserted && emptyPage[2].slot === 'footer' );
const rawOnly = [ raw ];
model.insertSection( rawOnly, inserted, null );
check( 'puts a first section after a lone locked raw frame', rawOnly[0] === raw && rawOnly[1] === inserted );
check( 'serializes an optional shell template as an ordinary marked shortcode', model.slotSource( 'footer', '' ) === '<!-- nino:template-slot footer -->\n' && model.slotSource( 'header', '/templates/site-header' ).includes( '[template /templates/site-header]' ) );
check( 'creates a stable unique section id', model.nextId( segments, 'Main Hero') === 'main-hero' && model.nextId( segments.concat( [ { type : 'section', htmlId : 'main-hero' } ] ), 'Main Hero') === 'main-hero-2' );
check( 'document search covers display name, filename and page id', model.matchesDocument( { name : 'page-about-us', filename : 'page-about-us.tpl', displayName : 'About the studio', pageId : 'about-us' }, 'studio' ) && model.matchesDocument( { name : 'page-about-us', filename : 'page-about-us.tpl', displayName : 'About the studio', pageId : 'about-us' }, '.tpl' ) && !model.matchesDocument( { name : 'page-home', displayName : 'Homepage', pageId : 'home' }, 'contact' ) );
check( 'validates real page template filenames without hiding prefix or suffix', model.validFilename('page-error-404.tpl') && !model.validFilename('error-404') && !model.validFilename('page-../config.tpl') );
check( 'derives a readable initial name from the filename', model.displayNameFromFilename('page-error-404.tpl') === 'Error 404' );
check( 'rejects names that cannot be safely stored in an HTML comment', model.validDisplayName('Error 404') && !model.validDisplayName('Broken --> comment') );
check( 'HTML+ editing detaches generated composer metadata', Nino.templates.sectionsUI.detachMetadata( '<section>\n\t<!-- nino:section {"preset":"blank"} -->\n\t<p>Kept</p>\n</section>' ) === '<section>\n\t<p>Kept</p>\n</section>' );

console.log('\nSection Library filtering');

const matches = Nino.templates.composer.matchesPreset;
const preset = { name : 'FAQ — Accordion', description : 'Questions and answers', category : 'Content', tags : [ 'faq', 'support' ] };
check( 'matches preset names and tags case-insensitively', matches( preset, 'accordion', 'All' ) && matches( preset, 'SUPPORT', 'All' ) );
check( 'applies category and text filters together', matches( preset, 'questions', 'Content' ) && !matches( preset, 'questions', 'Hero' ) );
check( 'empty search keeps the selected category visible', matches( preset, '', 'Content' ) );
check( 'the library accepts named-area presets only', Nino.templates.composer.isAreaPreset( { version : 3 } ) && !Nino.templates.composer.isAreaPreset( { version : 1 } ) );
Nino.templates._library.previewCss = '/* project-preview-css */ .ui-section{display:block}';
const previewDocument = Nino.templates.composer.previewDocument( '<section id="sample"></section>' );
check( 'preview documents inline the project bundle without another stylesheet request', previewDocument.includes( 'project-preview-css' )
	&& previewDocument.includes( '<section id="sample"></section>' )
	&& !previewDocument.includes( '<link rel="stylesheet"' )
	&& !previewDocument.includes( '/.cache/style.css' ) );
check( 'preview documents block scripts, forms and third-party network access', previewDocument.includes( 'Content-Security-Policy' ) && previewDocument.includes( "script-src 'none'" ) && previewDocument.includes( "form-action 'none'" ) );
check( 'script-free previews reproduce configured cover heights and a stable parallax image', previewDocument.includes( '[data-cover-height="100"]{min-height:100vh!important}' )
	&& previewDocument.includes( '.js-parallex>img{top:0!important;height:100%!important;transform:none!important}' ) );
const hostilePreview = Nino.templates.composer.previewDocument( '<script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">Safe</a><a href=javascript:alert(4)>Still safe</a><img src=x onerror=alert(5)>' );
check( 'preview documents remove executable markup before assigning srcdoc', !hostilePreview.includes( '<script' )
	&& !hostilePreview.includes( 'javascript:' )
	&& !/\son[a-z]+=/i.test( hostilePreview ) );

const composerSource = fs.readFileSync( path.join( __dirname, '../_templates/assets/composer.js' ), 'utf8' );
const areaComposerSource = fs.readFileSync( path.join( __dirname, '../_templates/assets/area-composer.js' ), 'utf8' );
const sectionsSource = fs.readFileSync( path.join( __dirname, '../_templates/assets/sections.js' ), 'utf8' );
const scriptSource = fs.readFileSync( path.join( __dirname, '../_templates/assets/script.js' ), 'utf8' );
const styleSource = fs.readFileSync( path.join( __dirname, '../_templates/assets/style.css' ), 'utf8' );
const ninoCssSource = fs.readFileSync( path.join( __dirname, '../_nino/Nino.css' ), 'utf8' );
const articlesManifestSource = fs.readFileSync( path.join( __dirname, '../_templates/library/articles-grid/manifest.php' ), 'utf8' );
const templateMarkup = fs.readFileSync( path.join( __dirname, '../_templates/templates/page-index.tpl' ), 'utf8' );
const templatesPhpSource = fs.readFileSync( path.join( __dirname, '../_templates/Templates.php' ), 'utf8' );
const sandboxAssignments = composerSource.match( /iframe\.setAttribute\(\s*'sandbox',\s*PREVIEW_SANDBOX\s*\)/g ) || [];
check( 'the backend refreshes the configured CSS bundle before embedding it', /Assets::doShortcode\(\s*\$appData,\s*\[\s*'\/\.cache\/style\.css'\s*\]/.test( templatesPhpSource ) );
check( 'gallery and detail previews use an opaque sandbox while CSP still denies scripts', composerSource.includes( "const PREVIEW_SANDBOX = 'allow-scripts'" )
	&& !composerSource.includes( "const PREVIEW_SANDBOX = 'allow-same-origin'" )
	&& sandboxAssignments.length === 2 );
check( 'the initial detail preview uses the same opaque sandbox', templateMarkup.includes( 'sandbox="allow-scripts"' )
	&& !templateMarkup.includes( 'sandbox="allow-same-origin"' )
	&& templateMarkup.includes( 'sandbox=""' ) === false );
check( 'new-template UI asks for filename, name, shell slots and VPA', [ 'pd-create-filename', 'pd-create-name', 'pd-create-header', 'pd-create-footer', 'pd-create-vpa' ].every( function( id ) { return templateMarkup.includes( 'id="'+ id+ '"' ) } ) );
check( 'the primary toolbar exposes one Add Section entry point', templateMarkup.includes( 'id="pd-add-section"' ) && templateMarkup.includes( 'id="pd-add-template"' ) === false );
check( 'Add Section is the final workspace control instead of a template setting',
	/<div id="pd-canvas"[^>]*><\/div>\s*<button[^>]*id="pd-add-section"[^>]*>[\s\S]*?<\/button>\s*<\/main>/.test( templateMarkup ) );
check( 'Delete and Save stay together at the right of the real topbar', templateMarkup.indexOf( 'id="pd-top-actions"' ) < templateMarkup.indexOf( 'id="pd-delete-template"' )
	&& templateMarkup.indexOf( 'id="pd-delete-template"' ) < templateMarkup.indexOf( 'id="pd-save"' )
	&& scriptSource.includes( "appendChild( topActions )" ) === false );
check( 'template VPA shares the labeled settings row and uses joined controls', templateMarkup.includes( 'class="pd-slot-setting pd-vpa-setting"' )
	&& templateMarkup.includes( 'id="pd-page-motion"' ) );
check( 'dialog close controls use the shared stroke SVG instead of text glyphs', ( templateMarkup.match( /class="pd-icon-button pd-[^"]+-close"[^>]*><svg/g ) || [] ).length === 4
	&& templateMarkup.includes( '<path d="M18 6 6 18"/>' ) );
check( 'Add Section lists presets only while reusable templates remain Area data inputs', composerSource.includes( 'const includes = []' )
	&& areaComposerSource.includes( "propertyDefinition.kind === 'template'" )
	&& areaComposerSource.includes( "include.kind !== 'Page frame'" ) );
check( 'the removed Classic switch cannot reappear in the library UI', !templateMarkup.includes( 'pd-library-scope' )
	&& !composerSource.includes( 'matchesScope' )
	&& !composerSource.includes( 'selectScope' ) );
check( 'dialogs share the #pd-app design scope instead of sitting beside it', /<div id="pd-app"[\s\S]*<dialog id="pd-composer"[\s\S]*<div id="pd-toast"[\s\S]*<\/div>\s*<\/div>\s*<script/.test( templateMarkup ) );

console.log('\nNamed area composer');

check( 'the Area editor loads after the established composer and exposes bounded pure helpers', templateMarkup.indexOf( 'composer.js' ) < templateMarkup.indexOf( 'area-composer.js' )
	&& typeof Nino.templates.areaComposer.nextComponentId === 'function'
	&& typeof Nino.templates.areaComposer.moveComponent === 'function' );
const componentList = [ { id : 'title' }, { id : 'title-2' }, { id : 'image' } ];
check( 'new component IDs remain stable and unique within an Area', Nino.templates.areaComposer.nextComponentId( componentList, 'title' ) === 'title-3'
	&& Nino.templates.areaComposer.nextComponentId( componentList, 'button' ) === 'button' );
const movedComponents = Nino.templates.areaComposer.moveComponent( componentList, 2, -1 );
check( 'ordered components move without mutating the previous state', movedComponents[1].id === 'image'
	&& componentList[1].id === 'title-2'
	&& Nino.templates.areaComposer.moveComponent( componentList, 0, -1 ) === componentList );
check( 'the editor keeps Area-level Design/Data views and independent collection creation', [ "[ 'design', 'data' ]", "'Content areas'", 'collection.area', 'image.component' ].every( function( marker ) { return areaComposerSource.includes( marker ) } ) );
check( 'Add Section uses a reduced combined component/data view while Edit keeps fine tuning', [
	'function quickMode()', 'function renderQuickArea(', "'Components and data'", "if( !quick ) {",
].every( function( marker ) { return areaComposerSource.includes( marker ) } )
	&& composerSource.includes( "step === 'library' && pd.composer._context && pd.composer._context.mode === 'replace'" )
	&& styleSource.includes( '.pd-composer-dialog.is-edit .pd-stepper' ) );
check( 'Add Section omits visual frame and stack styles without dropping background or data controls', /if\( !quick \) \{[\s\S]*?'Height'[\s\S]*?'Width'[\s\S]*?'Margin top \/ bottom'[\s\S]*?'Padding top \/ bottom'[\s\S]*?\}\s*grid\.appendChild\( formField\( 'Background'/.test( areaComposerSource )
	&& areaComposerSource.includes( "formField( 'Area style'" )
	&& areaComposerSource.includes( 'renderBindingFields( group' ) );
check( 'binding controls expose collection fields, existing textfills and fixed values', [
	"{ value : 'field', label : 'Collection field' }", "{ value : 'textfill', label : 'Existing textfill' }", "{ value : 'fixed', label : 'Fixed value' }",
].every( function( marker ) { return areaComposerSource.includes( marker ) } ) );
check( 'new-section key regeneration preserves manifest-recommended shared and fixed bindings', areaComposerSource.includes( "bindingSource( area, component, property, definition.properties[property] ) !== 'new'" ) );
check( 'blacklisted textfills remain selectable in a separate technical group', areaComposerSource.includes( "label : 'Technical values'" )
	&& templatesPhpSource.includes( "'blacklisted' => ( $entry['blacklisted'] ?? false ) === true" ) );
check( 'named Areas render as semantic tabs above one Design/Data workspace', [ "'pd-v3-area-workspace'", "setAttribute( 'role', 'tablist' )", "setAttribute( 'role', 'tabpanel' )" ].every( function( marker ) { return areaComposerSource.includes( marker ) } ) );
check( 'the config pane gives steps, Area tabs, components, sources and bindings explicit UI structure', [
	'pd-v3-panel-copy', 'pd-v3-area-index', 'pd-v3-area-tab-copy', 'pd-v3-component-copy',
	'pd-v3-section-label', 'pd-v3-source-panel', 'pd-v3-binding-heading', 'pd-v3-generated-value',
].every( function( marker ) { return areaComposerSource.includes( marker ) } ) );
check( 'named-area rules use maintainable component specificity in the normal tool layer', /@layer nino\.tool \{\s*#pd-composer-settings/.test( styleSource )
	&& styleSource.includes( '.pd-v3-area-tabs button' )
	&& styleSource.includes( '.pd-v3-component-identity' )
	&& !styleSource.includes( '#pd-app .pd-v3-' ) );
check( 'Area navigation stays horizontal so the editor body keeps the full config-pane width', /\.pd-v3-area-workspace\s*\{[\s\S]*?grid-template-columns:\s*minmax\(0,\s*1fr\)/.test( styleSource )
	&& /\.pd-v3-area-tabs\s*\{[\s\S]*?display:\s*flex;[\s\S]*?overflow-x:\s*auto;/.test( styleSource )
	&& !styleSource.includes( 'grid-template-columns: 10.5rem minmax(0, 1fr)' ) );
check( 'canvas cards and the inspector read named Areas instead of legacy section axes', [ 'isAreaSpec( spec )', 'areaPreview( spec, areaPreset )', "[ 'Areas'", "[ 'Collections'" ].every( function( marker ) { return sectionsSource.includes( marker ) } ) );
check( 'the Articles preset keeps every margin-bearing card inside its selected grid row', articlesManifestSource.includes( 'nino-article-grid-item' )
	&& [ '25', '33', '50' ].every( function( width ) { return new RegExp( '\\.nino-article-grid-item\\.ui-grid-m-'+ width+ '\\s*\\{[^}]*width:\\s*calc\\(' ).test( ninoCssSource ) } ) );
const resourceSpec = { version : 3, preset : 'sample', pageId : 'home', id : 'services', areas : { copy : { components : [ { id : 'visual', type : 'image', bindings : { src : '/page-home/services/visual' } } ] } } };
const resourcePreset = { areas : { copy : { label : 'Copy', source : 'single' } } };
check( 'v3 image creation is limited to generated background and declared Area image slots',
	Nino.templates.sectionsUI.areaImageRequest( resourceSpec, resourcePreset, '/page-home/services/background' ).slot === 'background'
	&& Nino.templates.sectionsUI.areaImageRequest( resourceSpec, resourcePreset, '/page-home/services/visual' ).component === 'visual'
	&& Nino.templates.sectionsUI.areaImageRequest( resourceSpec, resourcePreset, '/shared/existing-image' ) === null );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures > 0 ? 1 : 0 );
