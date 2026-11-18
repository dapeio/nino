/**
 * Dependency-free checks for Admin's batch translation UI and the shared
 * rich-text editor contract it relies on.
 *
 * Usage: node tests/admin-translations-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, source, load, fills, element, documentOf, findAll, fire } = require( './harness.js' );

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

console.log('\nThe form');

// The tab as the other panels' forms are built: two sections and one bar, whose main button is the import. What the
// import asks, what it says at its field and in its line, and what the shell is told about what was pasted
{
	const page = element('div');
	const wrap = element('div');
	wrap.id = 'translations-content';
	page.appendChild( wrap );
	const base = documentOf( page );
	const body = element('body');
	const document = Object.assign( {}, base, { body : body, addEventListener : function() {}, removeEventListener : function() {} } );
	const words = Object.assign( {}, fills('_admin/text/en_US.php'), fills('_admin/Nino/Modules/Language/text/en_US.php') );
	const Nino = { admin : {}, events : { bindCallback : function() {} }, http : { sendRequest : function() {} }, content : { getText : function( key ) { return words[key] ?? '' } } };
	const drawn = vm.createContext( {
		window : { Nino : Nino, setTimeout : function() { return 1 }, clearTimeout : function() {}, matchMedia : function() { return { matches : false } } },
		document : document, Nino : Nino, console : console, JSON : JSON, TypeError : TypeError,
		Blob : function( parts ) { this.parts = parts },
		URL : { createObjectURL : function() { return 'blob:x' }, revokeObjectURL : function() {} },
	} );
	load( '_admin/assets/Nino.admin.js', drawn );
	Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English (US)', it_IT : 'Italiano' } };

	// What the shell has: the registry of unsaved input, which the script registers with when it loads
	const entries = {};
	let refreshed = 0;
	Nino.admin.dirty = { register : function( name, entry ) { entries[name] = entry }, refresh : function() { refreshed++ } };
	load( '_admin/Nino/Modules/Language/assets/translations.js', drawn );

	const panel = Nino.admin.translations;
	const requests = [];
	panel._apiCall = function( endpoint, payload, callback ) { requests.push( { endpoint : endpoint, payload : payload, callback : callback } ) };

	const draw = function( locales ) {
		panel._info = { nativeLocale : 'de_DE', locales : locales, textCount : 3, elementCount : 4 };
		panel._render();
	};
	const byId = id => findAll( page, el => el.id === id )[0];
	const toasts = () => findAll( body, el => el.classList.contains('nino-admin-toast-text') ).map( el => el.textContent );
	const target = () => byId('translations-target-locale');
	const json = () => byId('translations-json');
	const bar = () => findAll( page, el => el.classList.contains('nino-admin-actionbar') );
	const main = () => findAll( page, el => el.classList.contains('nino-admin-btn-primary') );
	const questions = [];
	const messages = [];
	let answer = false;
	Nino.adminUi.confirm = function( level, options ) { questions.push( level ); messages.push( options.message ); options.onChoose( answer ); return true };

	draw( [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : true }, { locale : 'it_IT', active : false } ] );

	check( 'the tab is two sections, each a fieldset whose title carries the (?) that explains it, and no title of its own over them', findAll( page, el => el.tagName === 'FIELDSET' ).length === 2
		&& findAll( page, el => el.tagName === 'LEGEND' ).every( legend => findAll( legend, el => el.classList.contains('nino-admin-help-button') ).length === 1 )
		&& findAll( page, el => el.tagName === 'H1' || el.tagName === 'H2' ).length === 0 && findAll( page, el => el.classList.contains('nino-admin-hint') ).length === 0 );
	check( 'the export is one small button of the second kind in its section, and not the main button', byId('translations-export') !== null
		&& byId('translations-export').classList.contains('nino-admin-btn--small') && byId('translations-export').classList.contains('nino-admin-btn-secondary') && main().indexOf( byId('translations-export') ) === -1 );
	check( 'a form that stores nothing starts with no state of saving in its bar: the badge is the one of a thing never saved', findAll( page, el => el.classList.contains('nino-admin-actionbar-state') )[0].dataset.fresh === 'true' );
	check( 'the import is the one main button of the view, the last in the one bar, and it submits the form', bar().length === 1 && main().length === 1
		&& bar()[0].children[bar()[0].children.length - 1] === main()[0] && main()[0].type === 'submit' );

	const word = words['/_admin/common/word/inactive'];
	check( 'the target select has an option for every language the answer names, the code its value, each named as the workbench names it', word !== undefined && word !== '' && target().children.map( o => o.value ).join() === 'de_DE,en_US,it_IT'
		&& target().children[1].textContent === 'English (US)' );
	check( '...the native one says so and the one the website does not serve says so, in the words of the workbench', target().children[0].textContent.indexOf( words['/_admin/translations/label/native'] ) !== -1 && target().children[0].textContent.indexOf( word ) === -1
		&& target().children[2].textContent === 'Italiano ('+ word+ ')' );
	check( '...and the first language that is not the native one is the one picked', target().value === 'en_US' );
	draw( [ { locale : 'de_DE', active : true } ] );
	check( 'a site with the native language alone offers the native language', target().value === 'de_DE' );
	draw( [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : true } ] );

	// What is said at the field, before anything is sent
	const submit = () => fire( panel._fields.form, 'submit' );
	submit();
	check( 'a form with no document says so at the JSON field - marked, described, with the focus - and in the line, and sends nothing', requests.length === 0 && json().attributes['aria-invalid'] === 'true'
		&& findAll( page, el => el.classList.contains('nino-admin-field-error') ).length === 1 && json().focused === true && panel._line.state === 'error' );
	json().value = '{broken';
	submit();
	check( '...so does a document that is no JSON, said once, with the reason the parser gave', requests.length === 0 && findAll( page, el => el.classList.contains('nino-admin-field-error') ).length === 1
		&& findAll( page, el => el.classList.contains('nino-admin-field-error') )[0].textContent.indexOf( 'JSON' ) !== -1 );

	// Importing into the native language overwrites the source: asked first, and a No sends nothing
	target().value = 'de_DE';
	json().value = '{"format":"nino.translation"}';
	submit();
	check( 'an import that overwrites asks first, on the level of a destructive action, and a No sends nothing and clears nothing', questions.join() === 'destructive' && requests.length === 0 && json().value !== '' );
	answer = true;
	submit();
	check( '...and a Yes sends it, once, with the language and the document', requests.length === 1 && requests[0].endpoint === 'import' && requests[0].payload.targetLocale === 'de_DE' && requests[0].payload.translation.format === 'nino.translation' );
	check( '...with the main button off while it is on its way', main()[0].disabled === true );
	submit();
	check( '...and a second press is not a second send', requests.length === 1 );

	requests[0].callback( 200, { targetLocale : 'de_DE', text : { imported : 2, skipped : 1 }, elements : { imported : 3, skipped : 0 }, errors : [] } );
	check( 'a clean import is a toast with what was imported and what was skipped, and the document is cleared - nothing is left unsent', toasts().length === 1
		&& toasts()[0] === words['/_admin/translations/msg/imported'].replace( '%d', '5' ).replace( '%s', 'Deutsch' ).replace( '%n', '1' ) && json().value === '' && main()[0].disabled === false );
	check( '...and the shell is told that nothing is unsaved any more', refreshed > 0 && panel._holds() === false );

	// A partial import keeps the document and the errors in the line
	target().value = 'en_US';
	json().value = '{"format":"nino.translation","text":{"a":"x"}}';
	questions.length = 0;
	submit();
	check( '...into any other language too, whatever the mode, with how many values it may touch in the message - and a Yes sends', questions.join() === 'destructive' && requests.length === 2
		&& messages[messages.length - 1].indexOf( '1' ) !== -1 && messages[messages.length - 1].indexOf( words['/_admin/translations/confirm/native'] ) === -1 );
	check( '...and the question of the native language adds what that means: it is the source of the others', messages[0].indexOf( words['/_admin/translations/confirm/native'] ) !== -1 );
	check( 'the number is what the document holds: its texts and every text of its elements', panel._countValues( { text : { a : 'x', b : 'y' }, elements : { t : [ { f : 'z', g : { de : 'q' } } ] } } ) === 4 );
	requests[1].callback( 200, { targetLocale : 'en_US', text : { imported : 1, skipped : 0 }, elements : { imported : 0, skipped : 0 }, errors : [ 'No such key.' ] } );
	check( 'what the import turned away stays in the line of the bar, as an alert, and the document stays to be put right', panel._line.state === 'error' && panel._line.element.textContent === 'No such key.' && json().value !== '' );
	requests.length = 0;
	submit();
	requests[0].callback( 500, { error : 'no' } );
	check( 'a refused import is said in the line, and the button is back', panel._line.state === 'error' && main()[0].disabled === false && json().value !== '' );

	// The shell: what is pasted is unsaved input, the question's Save is the import and its Discard forgets the document
	check( 'the tab registers with the shell, and a pasted document is unsaved input', typeof entries.translations === 'object' && entries.translations.isDirty() === true );
	json().value = '';
	check( '...and an empty field is not', entries.translations.isDirty() === false );
	json().value = '{"format":"nino.translation"}';
	requests.length = 0;
	let ended = null;
	entries.translations.save( function( ok ) { ended = ok } );
	requests[0].callback( 200, { targetLocale : 'en_US', text : { imported : 1, skipped : 0 }, elements : { imported : 0, skipped : 0 }, errors : [] } );
	check( '...whose Save is the import, and ends with done( true )', ended === true && entries.translations.isDirty() === false );
	json().value = '{}';
	entries.translations.discard();
	check( '...and whose Discard forgets the document', json().value === '' && entries.translations.isDirty() === false );
	ended = null;
	json().value = '{broken';
	entries.translations.save( function( ok ) { ended = ok } );
	check( 'a Save that is refused before it is sent still ends, with done( false )', ended === false );
	target().value = 'de_DE';
	json().value = '{"format":"nino.translation"}';
	answer = false;
	ended = null;
	requests.length = 0;
	entries.translations.save( function( ok ) { ended = ok } );
	check( '...and so does one into the native language that the person declined', ended === false && requests.length === 0 );
	answer = true;
	target().value = 'en_US';
	json().value = '';

	// The export: a file, said as a toast; a refusal in the line
	panel._line.idle();
	requests.length = 0;
	fire( byId('translations-export'), 'click' );
	check( 'the export asks the server, with the button off while it does', requests.length === 1 && requests[0].endpoint === 'export' && byId('translations-export').disabled === true );
	requests[0].callback( 500, null );
	check( 'a refused export is said in the line, and the button is back', panel._line.state === 'error' && byId('translations-export').disabled === false );
}

console.log('\nShared HTML editor');

const htmlEditor = source('_admin/assets/html-editor.js');
const kernel = source('_nino/Nino/Html/Html.php');
check( 'toolbar and server sanitizer both whitelist code', htmlEditor.includes("'span', 'code', 'a'") && kernel.includes("'span', 'code', 'a'") );

done();
