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
	// The panel's words are fills; the parser's one message is one of them
	content : { getText : function( key ) { return key } },
};
const context = vm.createContext( {
	window : { Nino : Nino },
	document : { getElementById : function() { return null } },
	Nino : Nino,
	console : console,
	JSON : JSON,
	TypeError : TypeError,
} );

vm.runInContext( source('_admin/Nino/Modules/Language/assets/translations.js'), context, { filename : 'translations.js' } );

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

const editorText = source('_admin/Nino/Modules/Text/assets/admin.js');
const editorPhp = source('_admin/Nino/Modules/Text/Admin/Admin.php');
check( 'Editor no longer renders the old text-only translation toolbar', editorText.includes('_renderTranslateTools') === false && editorText.includes('text-translate-tools') === false );
check( 'Editor no longer exposes batch translation endpoints', editorPhp.includes("'text/export'") === false && editorPhp.includes("'text/import'") === false );

// The shell's nav is rendered from each panel's nav() - see \Nino\Panels
const adminPhp = source('_admin/Nino/Modules/Language/Translations/Translations.php')+ source('_admin/Nino/Modules/Routes/Admin/Admin.php');
check( 'Admin exposes Translations as a tab of the Language panel and labels both it and Routes with fills', /\[ 'translations', '\/_admin\/nav\/translations', \d+, 'system' \]/.test( adminPhp ) && source('_admin/Nino/Modules/Language/Admin/Admin.php').includes( 'return [ Translations::class ];' ) && /\[ 'routes', '\/_admin\/nav\/routes', \d+, 'structure' \]/.test( adminPhp ) );
check( 'the tab renders into its own mount point, not the pane it shares', source('_admin/Nino/Modules/Language/assets/translations.js').includes( "getElementById('translations-content')" ) && source('_admin/Nino/Modules/Language/assets/translations.js').includes( 'admin-content-translations' ) === false && /return \[ 'translations-content' \];/.test( adminPhp ) );

console.log('\nShared HTML editor');

const htmlEditor = source('_admin/assets/html-editor.js');
// The rich-text surface is shared markup, so its rules belong to the design
// system half of the workbench stylesheet rather than to one panel's own
const editorCss = source('_admin/assets/style.css');
const kernel = source('_nino/Nino.php');
const editorElements = source('_admin/Nino/Modules/Elements/assets/admin.js');
const adminElements = source('_admin/Nino/Modules/Elements/assets/admin.js');
check( 'toolbar and server sanitizer both whitelist code', htmlEditor.includes("'span', 'code', 'a'") && kernel.includes("'span', 'code', 'a'") );
check( 'the editable surface explicitly restores drag selection', editorCss.includes('.nino-admin-richtext-content *') && editorCss.includes('-webkit-user-select: text') && editorCss.includes('user-select: text') );
check( 'rich text is not nested inside an interactive label', editorText.includes("dc.createElement( entry.html === true ? 'div' : 'label' )") && editorElements.includes("dc.createElement( isHtml ? 'div' : 'label' )") && adminElements.includes("dc.createElement( isHtml ? 'div' : 'label' )") );
check( 'code receives a readable monospace treatment', editorCss.includes('.nino-admin-richtext-content code') && editorCss.includes('ui-monospace') );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
