/**
 * Dependency-free checks for Admin's batch translation UI and the shared
 * rich-text editor contract it relies on.
 *
 * Usage: node tests/admin-translations-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, source, load, fills, element, documentOf, findAll } = require( './harness.js' );

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

console.log('\nImport target');

// A language that has a text file is one a translation is imported into, whether the website serves it or not. The cards are written with
// innerHTML, which the document stand-in does not read: what they hold is put there the way the markup would
{
	const page = element('div');
	const wrap = element('div');
	wrap.id = 'translations-content';
	page.appendChild( wrap );
	const base = documentOf( page );
	const document = Object.assign( {}, base, { createElement : function( tag ) {
		const el = base.createElement( tag );
		return tag !== 'section' ? el : new Proxy( el, { set : function( target, name, value ) {
			if( name !== 'innerHTML' ) {
				target[name] = value;
				return true;
			}
			const copy = base.createElement('div');
			copy.appendChild( base.createElement('h2') );
			copy.appendChild( base.createElement('p') );
			target.appendChild( base.createElement('span') );
			target.appendChild( copy );
			return true;
		} } );
	} } );
	const words = fills('_admin/text/en_US.php');
	const Nino = { admin : {}, events : { bindCallback : function() {} }, http : { sendRequest : function() {} }, content : { getText : function( key ) { return words[key] ?? '' } }, adminUi : { format : function( text ) { return text } } };
	const drawn = vm.createContext( { window : { Nino : Nino }, document : document, Nino : Nino, console : console, JSON : JSON, TypeError : TypeError } );
	load( '_admin/Nino/Modules/Language/assets/translations.js', drawn );
	const draw = function( locales ) {
		Nino.admin.translations._info = { nativeLocale : 'de_DE', locales : locales, textCount : 3, elementCount : 4 };
		Nino.admin.translations._render();
		return findAll( page, function( el ) { return el.id === 'translations-target-locale' } )[0];
	};

	const target = draw( [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : true }, { locale : 'it_IT', active : false } ] );
	const word = words['/_admin/common/word/inactive'];
	check( 'the target select has an option for every language the answer names, the code its value', word !== undefined && word !== '' && target.children.map( function( o ) { return o.value } ).join() === 'de_DE,en_US,it_IT' );
	check( '...the one the website does not serve says so, the word of the workbench in parentheses behind the code - the others do not', target.children[2].textContent === 'it_IT ('+ word+ ')'
		&& target.children[1].textContent === 'en_US' && target.children[0].textContent.indexOf( word ) === -1 );
	check( '...and the first language that is not the native one is the one picked', target.value === 'en_US' );
	check( 'a site with the native language alone offers the native language', draw( [ { locale : 'de_DE', active : true } ] ).value === 'de_DE' );

	// Importing into the native language overwrites the source: asked first, and a No sends nothing
	const questions = [];
	let answer = false;
	Nino.adminUi.confirm = function( level, options ) { questions.push( level ); options.onChoose( answer ); return true };
	const sent = [];
	Nino.admin.translations._apiCall = function( endpoint ) { sent.push( endpoint ) };
	const importButton = findAll( page, function( el ) { return el.tagName === 'BUTTON' && el.className === 'nino-admin-btn-primary' } ).pop();
	const textarea = findAll( page, function( el ) { return el.tagName === 'TEXTAREA' } )[0];
	textarea.value = '{"format":"nino.translation"}';
	importButton.click();
	check( 'an import into the native language is a big question, and a No sends nothing', questions.join() === 'big' && sent.length === 0 );
	answer = true;
	importButton.click();
	check( '...and a Yes sends it', sent.join() === 'import' );
	draw( [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : true } ] );
	const other = findAll( page, function( el ) { return el.tagName === 'BUTTON' && el.className === 'nino-admin-btn-primary' } ).pop();
	findAll( page, function( el ) { return el.tagName === 'TEXTAREA' } )[0].value = '{"format":"nino.translation"}';
	other.click();
	check( 'an import into another language asks nothing', questions.length === 2 && sent.length === 2 );
}

console.log('\nShared HTML editor');

const htmlEditor = source('_admin/assets/html-editor.js');
const kernel = source('_nino/Nino/Html/Html.php');
check( 'toolbar and server sanitizer both whitelist code', htmlEditor.includes("'span', 'code', 'a'") && kernel.includes("'span', 'code', 'a'") );

done();
