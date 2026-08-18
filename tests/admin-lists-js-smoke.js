/**
 * Dependency-free structural checks for _admin's shared drill-down lists.
 *
 * Usage: node tests/admin-lists-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

let checks = 0;
let failures = 0;

function check( label, condition ) {
	checks++;
	if( condition ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

function asset( filename ) {
	return fs.readFileSync( path.join( __dirname, '../_admin/assets/', filename ), 'utf8' );
}

console.log('Admin drill-down lists');

[
	[ 'Element Types', 'elementtypes.js' ],
	[ 'Elements', 'elements.js' ],
	[ 'Text', 'text.js' ],
	[ 'Routes', 'pages.js' ],
	[ 'Navigations', 'navs.js' ],
	[ 'Images', 'images.js' ],
].forEach( function( entry ) {
	check( entry[0]+ ' uses the shared grouped-list component', asset( entry[1] ).includes( "ul.className = 'nino-admin-list'" ) );
} );

// Config is deliberately not one of them. It used to be a list of keys drilling
// down into a raw json textarea each; it is now a single grouped form, because
// nine settings are quicker to read on one screen than behind nine clicks - and
// because its two locale keys constrain each other and so have to be saved
// together.
const configSource = asset('config.js');
check( 'Config is one form rather than a drill-down list', configSource.includes( "ul.className = 'nino-admin-list'" ) === false );
// The switch itself lives in Nino.adminUi, because the Element Types editor
// needs the same control for its numbering option - two hand-rolled copies of
// one component is how the written on/off state gets dropped from one of them.
const adminUiSource = fs.readFileSync( path.join( __dirname, '../_nino/Nino.js' ), 'utf8' );
check( 'the switch is a shared component, not a copy per tool',
	adminUiSource.includes('switchField : function') && adminUiSource.includes( "'nino-admin-switch-track'" ) );
check( 'a switch states its condition in words, not by knob position alone',
	adminUiSource.includes( "'nino-admin-switch-state'" ) );
check( 'Config renders booleans with that shared switch', configSource.includes('Nino.adminUi.switchField(') );
check( 'Config relies on fieldset\'s own shared surface instead of applying card padding twice', configSource.includes( "fieldset.className = 'nino-admin-card'" ) === false );
check( 'Config uses only the shared pinned action bar for its single Save', configSource.includes( "'nino-admin-actionbar'" ) && configSource.includes( 'editor-form-actions' ) === false );
check( 'Config keeps its in-flow locale adder separate from fixed list actions', configSource.includes( "adderRow.className = 'admin-config-locale-adder'" ) && configSource.includes( "adderRow.className = 'nino-admin-list-actions'" ) === false );
check( 'Config lays out the locale input and its Add button as one in-flow row',
	/#admin-page-wrap \.admin-config-locale-adder \{[^}]*display: grid;[^}]*grid-template-columns: minmax\(0, 1fr\) auto;/s.test( asset('style.css') ) );
check( 'Config no longer edits routes, navigations or asset bundles', [ '/nino/http/routes', '/nino/html/navs', '/nino/html/assets' ].every( function( key ) { return configSource.includes( key ) === false } ) );
check( 'Config builds the native-language select from the ticked languages', configSource.includes( '_refreshNative' ) );
check( 'Config renders no raw json textarea any more', configSource.includes( 'config-form-value' ) === false );

// Adding a language writes its text file as a skeleton of the native
// language's keys. The row it produces must stay unticked: the keys are empty,
// so ticking it would put a language that serves blank pages one Save away -
// the exact failure the language group exists to warn about.
const addLocaleStart = configSource.indexOf('_addLocale : function()');
const addLocaleEnd = configSource.indexOf('_renderNative : function', addLocaleStart);
const addLocaleSource = configSource.slice( addLocaleStart, addLocaleEnd );
check( 'Config creates the text file through the backend rather than client-side only', addLocaleSource.includes( "_apiCall( 'addlocale'" ) );
check( 'Config does not switch a freshly created skeleton on', /entry\.active\s*=\s*true/.test( addLocaleSource ) === false );
check( 'Config still re-activates an already translated language directly', addLocaleSource.includes( 'existing.active = true' ) );
check( 'Config says what the Add button writes before it is pressed', configSource.includes( 'Creates text/<locale>.php' ) );

const textSource = asset('text.js');
const categoryStart = textSource.indexOf('_renderCategoryList : function()');
const categoryEnd = textSource.indexOf('_openGroup : function', categoryStart);
const categorySource = textSource.slice( categoryStart, categoryEnd );
check( 'Text renders accessible links instead of non-interactive div cards', categorySource.includes( "dc.createElement('a')" ) && categorySource.includes( "dc.createElement('div')" ) === false );
check( 'Text places its template scan before the category list', categorySource.indexOf('wrap.appendChild( scanBtn )') < categorySource.indexOf('wrap.appendChild( ul )') );

// The Navigations detail level reuses the same component one level deeper,
// for a row that doesn't drill down anywhere - so it carries the label and
// the ↑/↓/× cluster itself rather than an <a>
const navsSource = asset('navs.js');
check( 'Navigations reuses the list component for one menu\'s entries too', navsSource.split("ul.className = 'nino-admin-list'").length === 3 );
check( 'Navigations reuses the shared ↑/↓ cluster the Routes list has', navsSource.includes("moveWrap.className = 'admin-page-move'") );
check( 'Navigations uses the shared pinned form toolbar and action bar', navsSource.includes('Nino.admin.formToolbar( backLink )') && navsSource.includes("'nino-admin-actionbar'") && navsSource.includes('editor-form-actions') === false );
check( 'Navigations puts its list-level action in the shared bar', navsSource.includes('Nino.adminUi.listActions( [ addBtn ] )') );
check( 'Navigations carries no locale state or switch - a menu has nothing per-locale', navsSource.includes('_locales') === false && navsSource.includes('selectedLocale') === false && navsSource.includes('data-locale') === false );

const css = asset('style.css');
const sharedCss = fs.readFileSync( path.join( __dirname, '../_nino/Nino.admin.css' ), 'utf8' );
check( 'shared rows expose a right-facing drill-down arrow', sharedCss.includes('.nino-admin-list li > a::after') && sharedCss.includes('content: "›"') );
check( 'shared rows retain a keyboard focus treatment', sharedCss.includes('.nino-admin-list li > a:focus-visible') );
check( 'clickable Dashboard list rows expose the same arrow', css.includes('#admin-dashboard-types a::after') && css.includes('#admin-dashboard-summary a::after') );

check( 'empty list screens show guidance instead of an empty bordered panel',
	[ 'elementtypes.js', 'text.js', 'images.js', 'users.js' ]
		.every( file => asset( file ).includes("empty.className = 'nino-admin-hint'") ) );

const usersSource = asset('users.js');
check( 'the user and permissions forms expose real labels and live status text',
	usersSource.includes("mailLabel.className = 'nino-admin-field'") &&
	usersSource.includes("pwLabel.className = 'nino-admin-field'") &&
	usersSource.includes("textareaLabel.className = 'nino-admin-field'") &&
	usersSource.split("setAttribute( 'aria-live', 'polite' )").length >= 3 );

check( 'destructive Admin controls all use the shared danger treatment',
	[ 'elementtypes.js', 'users.js', 'restore.js' ]
		.every( file => asset( file ).includes("className = 'nino-admin-btn-danger'") ) );

console.log('\nDesign system contracts');

// --- design-system conventions (see AGENTS.md, "Designing an admin frontend")

const cssRoot = require('path').join(__dirname, '..');
const read = p => require('fs').readFileSync( require('path').join(cssRoot, p), 'utf8' );

const shared = sharedCss;

// Selectors only: hex colours and the prose in the file header are not.
const sharedRules = shared.replace(/\/\*[\s\S]*?\*\//g, '')
	.replace(/url\((?:[^)(]|\([^)]*\))*\)/g, '')
	.replace(/#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/g, '');

check( 'the shared design system uses no id selectors',
	/#[a-zA-Z][a-zA-Z0-9_-]*/.test( sharedRules ) === false );

// .main-title/.main-uri predate the namespace and are still shared
// markup; @layer names are not classes
check( 'the shared design system styles only nino-admin-* classes',
	( sharedRules.match(/\.(?!nino-admin)[a-zA-Z][a-zA-Z0-9_-]*/g) || [] )
		.filter( c => [ '.main-title', '.main-title--withuri', '.main-uri',
		                '.active', '.tool', '.system', '.local' ].includes( c ) === false )
		// State modifiers are the house convention (.is-dirty, .is-error, ...):
		// they qualify a design-system component rather than name one, so they
		// stay unprefixed
		.filter( c => /^\.is-/.test( c ) === false )
		.length === 0 );

check( 'the shared design system declares its own cascade layer',
	shared.includes('@layer nino.system {') );

const TOOL_ROOTS = {
	'_admin/assets/style.css'     : '#admin-page-wrap',
	'_editor/assets/style.css'    : '#editor-page-wrap',
	'_install/assets/style.css'   : '#install-page-wrap',
	'_templates/assets/style.css' : '#pd-app',
};

Object.keys( TOOL_ROOTS ).forEach( function( file ) {

	const css = read( file );

	check( file+ ' opens with the shared layer order',
		css.trimStart().startsWith('@layer nino.system, nino.tool, nino.local;') );

	check( file+ ' wraps its own rules in the tool layer',
		css.includes('@layer nino.tool {') && css.includes('@layer nino.local {') );

	// A rule naming a shared class without a scope reaches every screen its file
	// is loaded on - which is how _editor's shell grid once laid out the
	// Template Builder, back when three of the four tools cross-loaded it
	const unscoped = ( css.match(/^[\t ]*\.nino-admin[^{,\n]*[,{]\s*$/gm) || [] );
	check( file+ ' scopes every nino-admin-* rule to its own root', unscoped.length === 0,
		unscoped.slice(0, 3).join(' | ') );
} );

// An element-wide reset belongs to the design system, the one file all four
// tools load. A tool stylesheet defining one used to reach every other tool that
// cross-loaded the file: the select chevron lived in _editor's copy while the
// padding that keeps text off it came from the design system, and the two
// drifted apart. There is no allowed exception left.
const BASE_RESETS = {};

Object.keys( TOOL_ROOTS ).forEach( function( file ) {

	const bare = ( read( file ).match( /^[\t ]*(select|input|textarea|button|fieldset|legend|table|ul|ol|li|a|p|h[1-6])\s*[,{]/gm ) || [] )
		.map( s => s.replace( /[,{]\s*$/, '' ).trim() );

	const unexpected = bare.filter( s => ( BASE_RESETS[file] || [] ).includes( s ) === false );

	check( file+ ' adds no new bare element selector', unexpected.length === 0, unexpected.join(', ') );
} );

// A tool links its own stylesheet and the design system, nothing else. Borrowing
// another tool's complete stylesheet for a handful of its classes is what let
// unrelated rules reach screens they were never written for, and it made every
// /_install and /_templates request pay for two files it barely used. The
// Template Builder's login screen is the one deliberate exception - it renders
// /_admin's login markup with /_admin's own script.
const TOOL_TEMPLATES = {
	'_admin'     : [ '_admin/templates/page-index.tpl', '_admin/templates/page-login.tpl' ],
	'_editor'    : [ '_editor/templates/html-header.tpl' ],
	'_install'   : [ '_install/templates/page-wizard.tpl', '_install/templates/page-locked.tpl' ],
	'_templates' : [ '_templates/templates/page-index.tpl' ],
};

Object.keys( TOOL_TEMPLATES ).forEach( function( tool ) {
	TOOL_TEMPLATES[tool].forEach( function( file ) {

		const markup = read( file );
		const foreign = ( markup.match(/_(?:admin|editor|install|templates)\/assets\/style\.css/g) || [] )
			.filter( function( reference ) { return reference.startsWith( tool+ '/' ) === false } );

		check( file+ ' links no other tool\'s stylesheet', foreign.length === 0, foreign.join(', ') );
		check( file+ ' links the shared design system', markup.includes('_nino/Nino.admin.css') );
	} );
} );

// _editor used to ship a second copy of every design token and base control, in
// the same cascade layer as the design system but linked before it - so the copy
// silently lost every tie, while its higher-layer component overrides silently
// won and pulled the Editor away from the other three tools' look
check( 'no tool stylesheet redefines the shared design tokens',
	Object.keys( TOOL_ROOTS ).every( function( file ) { return read( file ).includes('--editor-blue:') === false } ) );

// The select indicator and the space reserved for it must stay in one file, or
// a later layer silently narrows the padding and the option text slides under
// the arrow
check( 'the design system owns both the select chevron and its padding',
	/:where\(\.nino-admin\) select \{[^}]*var\(--nino-admin-select-indicator\)[^}]*background-image:/s.test( shared ) );

check( 'the select chevron is one seam-free image rather than two touching gradients',
	shared.includes('--nino-admin-select-chevron: url("data:image/svg+xml') &&
	/:where\(\.nino-admin\) select \{[^}]*background-image: var\(--nino-admin-select-chevron\)/s.test( shared ) );

check( 'the locale switch is a shared class, not a per-tool id',
	shared.includes('.nino-admin-locale-select') &&
	[ '_admin/assets/style.css', '_editor/assets/style.css', '_install/assets/style.css' ]
		.every( f => /#(personalinfos|elements-form|text-form)-locale-select\s*[,{]/.test( read( f ) ) === false ) );

const adminTemplate = read('_admin/templates/page-index.tpl');
const adminLoginTemplate = read('_admin/templates/page-login.tpl');
check( 'Admin no longer depends on the complete Editor stylesheet',
	[ adminTemplate, adminLoginTemplate ].every( source => source.includes('_editor/assets/style.css') === false ) &&
	[ adminTemplate, adminLoginTemplate ].every( source => source.includes('_nino/Nino.admin.css') ) );

check( 'the shared tile is a complete surface rather than a spacing-only refinement',
	/:where\(\.nino-admin\) \.nino-admin-tile \{[^}]*display: flex;[^}]*border: 1px solid[^}]*background: var\(--editor-bg-elevated\);[^}]*color: var\(--editor-text\);/s.test( shared ) );

check( 'the shared field header owns its complete horizontal layout',
	/:where\(\.nino-admin\) \.nino-admin-field-header \{[^}]*display: flex;[^}]*align-items: baseline;[^}]*justify-content: space-between;/s.test( shared ) );

check( 'ordinary shared field labels are block rows above their controls',
	/:where\(\.nino-admin\) \.nino-admin-field > span,\s*:where\(\.nino-admin\) \.nino-admin-field-name \{[^}]*display: block;/s.test( shared ) );

check( 'Admin form toolbars use the shared context bar without a dead local class',
	asset('script.js').includes('Nino.adminUi.contextBar( backLink )') &&
	asset('script.js').includes('admin-form-toolbar') === false );

check( 'the shared context bar stays flush at the top of the scrolling pane',
	/\.nino-admin \.nino-admin-contextbar \{[^}]*position: sticky;[^}]*top: 0;[^}]*margin: -1rem calc\(var\(--nino-admin-content-inline\) \* -1\) 1rem;/s.test( shared ) );

check( 'the shared action bar is fixed to the viewport and clears the common rail',
	/\.nino-admin \.nino-admin-actionbar \{[^}]*position: fixed;/s.test( shared ) &&
	/@media \(min-width: 64rem\)[\s\S]*?\.nino-admin \.nino-admin-actionbar \{[^}]*left: var\(--nino-admin-sidebar-width\);/s.test( shared ) );

check( 'Admin does not replace shared action bars, danger buttons, tiles or field headers',
	css.includes('.nino-admin-actionbar') === false &&
	/\.nino-admin-btn-danger[^\{]*\{[^}]*(?:background|color|box-shadow):/s.test( css ) === false &&
	/\.nino-admin-tile[^\{]*\{[^}]*(?:display|background|box-shadow):/s.test( css ) === false &&
	/\.nino-admin-field-header[^\{]*\{[^}]*(?:display|align-items|justify-content):/s.test( css ) === false );

check( 'Admin carries no obsolete sticky action bar or second rail width',
	css.includes('editor-form-actions') === false &&
	css.includes('position: sticky') === false &&
	css.includes('17rem') === false );

const adminAssets = [ 'config.js', 'text.js', 'pages.js', 'elements.js', 'images.js', 'navs.js', 'elementtypes.js' ]
	.map( asset ).join('\n');
check( 'Admin forms use the shared action and back-link vocabulary',
	adminAssets.includes('editor-form-actions') === false &&
	adminAssets.includes("className = 'back-link'") === false &&
	adminAssets.includes("'nino-admin-actionbar'") &&
	adminAssets.includes("'nino-admin-back-link'") );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
