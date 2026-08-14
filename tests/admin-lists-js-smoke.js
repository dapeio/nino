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
	[ 'Config', 'config.js' ],
].forEach( function( entry ) {
	check( entry[0]+ ' uses the shared grouped-list component', asset( entry[1] ).includes( "ul.className = 'nino-admin-list'" ) );
} );

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
check( 'Navigations uses the shared pinned form toolbar and action bar', navsSource.includes('Nino.admin.formToolbar( backLink )') && navsSource.includes("'editor-form-actions nino-admin-actionbar'") );
check( 'Navigations puts its list-level action in the shared bar', navsSource.includes('Nino.adminUi.listActions( [ addBtn ] )') );
check( 'Navigations carries no locale state or switch - a menu has nothing per-locale', navsSource.includes('_locales') === false && navsSource.includes('selectedLocale') === false && navsSource.includes('data-locale') === false );

const css = asset('style.css');
check( 'shared rows expose a right-facing drill-down arrow', css.includes('.nino-admin-list li a::after') && css.includes('content: "›"') );
check( 'shared rows retain a keyboard focus treatment', css.includes('.nino-admin-list li a:focus-visible') );
check( 'clickable Dashboard list rows expose the same arrow', css.includes('#admin-dashboard-types a::after') && css.includes('#admin-dashboard-summary a::after') );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;

// --- design-system conventions (see AGENTS.md, "Designing an admin frontend")

const cssRoot = require('path').join(__dirname, '..');
const read = p => require('fs').readFileSync( require('path').join(cssRoot, p), 'utf8' );

const shared = read('_nino/Nino.admin.css');

// Selectors only: hex colours and the prose in the file header are not.
const sharedRules = shared.replace(/\/\*[\s\S]*?\*\//g, '')
	.replace(/#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/g, '');

check( 'the shared design system uses no id selectors',
	/#[a-zA-Z][a-zA-Z0-9_-]*/.test( sharedRules ) === false );

// .main-title/.main-uri/.back-link predate the namespace and are still shared
// markup; @layer names are not classes
check( 'the shared design system styles only nino-admin-* classes',
	( sharedRules.match(/\.(?!nino-admin)[a-zA-Z][a-zA-Z0-9_-]*/g) || [] )
		.filter( c => [ '.main-title', '.main-title--withuri', '.main-uri', '.back-link',
		                '.active', '.tool', '.system', '.local' ].includes( c ) === false )
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
		css.trimStart().startsWith('@layer nino.tool, nino.system, nino.local;') );

	check( file+ ' wraps its own rules in the tool layer',
		css.includes('@layer nino.tool {') && css.includes('@layer nino.local {') );

	// Every tool stylesheet is loaded by at least one *other* tool, so a rule
	// naming a shared class without a scope reaches screens it was never
	// written for - which is how _editor's shell grid once laid out the
	// Template Builder
	const unscoped = ( css.match(/^[\t ]*\.nino-admin[^{,]*[,{]/gm) || [] );
	check( file+ ' scopes every nino-admin-* rule to its own root', unscoped.length === 0,
		unscoped.slice(0, 3).join(' | ') );
} );
