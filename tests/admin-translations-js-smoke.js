/**
 * Dependency-free checks for Admin's batch translation UI and the shared
 * rich-text editor contract it relies on.
 *
 * Usage: node tests/admin-translations-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, source, load } = require( './harness.js' );

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

load( '_admin/Nino/Modules/Language/assets/translations.js', context );

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

console.log('\nShared HTML editor');

const htmlEditor = source('_admin/assets/html-editor.js');
const kernel = source('_nino/Nino/Html/Html.php');
check( 'toolbar and server sanitizer both whitelist code', htmlEditor.includes("'span', 'code', 'a'") && kernel.includes("'span', 'code', 'a'") );

done();
