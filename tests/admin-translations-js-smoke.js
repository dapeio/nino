/**
 * Dependency-free checks for Admin's batch translation UI and the shared
 * rich-text editor contract it relies on.
 *
 * Usage: node tests/admin-translations-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

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

function source( relative ) {
	return fs.readFileSync( path.join( __dirname, '..', relative ), 'utf8' );
}

const callbacks = [];
const Nino = {
	admin : {},
	events : { bindCallback : function( event, callback ) { if( event === 'ready' ) callbacks.push( callback ) } },
	http : { sendRequest : function() {} },
};
const context = vm.createContext( {
	window : { Nino : Nino },
	document : { getElementById : function() { return null } },
	Nino : Nino,
	console : console,
	JSON : JSON,
	TypeError : TypeError,
} );

vm.runInContext( source('_admin/assets/translations.js'), context, { filename : 'translations.js' } );

console.log('Admin Translations');

const parse = Nino.admin.translations._parseJson;
check( 'parses an ordinary translation object', parse('{"format":"nino.translation"}').format === 'nino.translation' );
check( 'accepts a fenced JSON block copied from chat', parse('```json\n{"version":1}\n```').version === 1 );

let rejectedArray = false;
try { parse('[]') } catch( error ) { rejectedArray = error instanceof TypeError }
check( 'rejects a JSON array at the document root', rejectedArray );

let rejectedInvalid = false;
try { parse('{broken') } catch( error ) { rejectedInvalid = true }
check( 'rejects malformed JSON', rejectedInvalid );
check( 'registers its ready callback', callbacks.length === 1 );

const editorText = source('_editor/assets/text.js');
const editorPhp = source('_editor/Editor.php');
check( 'Editor no longer renders the old text-only translation toolbar', editorText.includes('_renderTranslateTools') === false && editorText.includes('text-translate-tools') === false );
check( 'Editor no longer exposes batch translation endpoints', editorPhp.includes("'text/export'") === false && editorPhp.includes("'text/import'") === false );

const adminTemplate = source('_admin/templates/page-index.tpl');
check( 'Admin exposes Translations and labels the route module correctly', adminTemplate.includes('id="admin-nav-translations">Translations') && adminTemplate.includes('id="admin-nav-pages">Routes') );

console.log('\nShared HTML editor');

const htmlEditor = source('_editor/assets/html-editor.js');
// The rich-text surface is shared markup: /_admin and /_editor mount the same
// html-editor.js, so its rules belong to the design system rather than to one
// tool's stylesheet - a tool copy only ever reached one of the two callers
const editorCss = source('_nino/Nino.admin.css');
const kernel = source('_nino/Nino.php');
const editorElements = source('_editor/assets/elements.js');
const adminElements = source('_admin/assets/elements.js');
check( 'toolbar and server sanitizer both whitelist code', htmlEditor.includes("'span', 'code', 'a'") && kernel.includes("'span', 'code', 'a'") );
check( 'the editable surface explicitly restores drag selection', editorCss.includes('.nino-admin-richtext-content *') && editorCss.includes('-webkit-user-select: text') && editorCss.includes('user-select: text') );
check( 'rich text is not nested inside an interactive label', editorText.includes("dc.createElement( entry.html === true ? 'div' : 'label' )") && editorElements.includes("dc.createElement( isHtml ? 'div' : 'label' )") && adminElements.includes("dc.createElement( isHtml ? 'div' : 'label' )") );
check( 'code receives a readable monospace treatment', editorCss.includes('.nino-admin-richtext-content code') && editorCss.includes('ui-monospace') );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
