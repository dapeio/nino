/**
 *	Nino										A compact filesystembased php framework
 *	admin-language-js-smoke.js	DOM-light checks for the Language panel's adder
 *													(_admin/Nino/Modules/Language/assets/admin.js).
 *
 *													A language is added from one text field. What is wrong
 *													with the code that was typed is said where the person
 *													is - at the field, which is marked and described, and in
 *													the line under it, which announces it - and the focus is
 *													back in the field. The checks below are about exactly
 *													that, and about the line that carries the other
 *													sentences of the adder.
 *
 *	Usage: node tests/admin-language-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, element, findAll, fire } = require( './harness.js' );

// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Language/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

/** The panel in its own context, with the form's mount in a page that has one focus */
function build() {

	const mount = element('div');
	mount.id = 'language-form';

	const requests = [];

	const Nino = {
		admin : {},
		events : { bindCallback : function() {} },
		content : { getText : text },
	};
	const sandbox = {
		console : console,
		document : {
			createElement : element,
			createTextNode : function( value ) { return Object.assign( element('#text'), { textContent : String( value ) } ) },
			getElementById : function( id ) { return id === 'language-form' ? mount : ( findAll( mount, function( el ) { return el.id === id } )[0] ?? null ) },
			documentElement : null,
			body : null,
			get activeElement() { return findAll( mount, function( el ) { return el.focused === true } )[0] ?? null },
		},
		Nino : Nino,
	};
	sandbox.window = { Nino : Nino };

	const context = vm.createContext( sandbox );
	load( '_admin/assets/Nino.admin.js', context );
	load( '_admin/Nino/Modules/Language/assets/admin.js', context );

	const panel = Nino.admin.language;
	panel._apiCall = function( endpoint, payload, callback ) { requests.push( { endpoint : endpoint, payload : payload, callback : callback } ) };

	return {
		panel : panel,
		mount : mount,
		requests : requests,
		/** Answer the last request the panel made */
		answer : function( status, body ) { requests[requests.length - 1].callback( status, body ) },
		field : function( id ) { return findAll( mount, function( el ) { return el.id === id } )[0] },
		/** What a person has in front of them */
		focused : function() { return findAll( mount, function( el ) { return el.focused === true } )[0] ?? null },
	};
}

const LOCALES = [
	{ code : 'de_DE', name : 'Deutsch', hasText : true, keys : 120, active : true },
	{ code : 'en_US', name : 'English', hasText : true, keys : 120, active : true },
	{ code : 'fr_FR', name : 'Français', hasText : false, keys : 0, active : false },
];

/** A panel that has read its list and drawn the form */
function opened() {
	const t = build();
	t.panel.init();
	t.answer( 200, {
		intro : '/_admin/language/intro',
		fields : [
			{ type : 'locales', label : '/_admin/language/label/available', hint : '/_admin/language/hint/available' },
			{ type : 'native', key : '/nino/locales/native', label : '/_admin/language/label/native', hint : '/_admin/language/hint/native', value : 'de_DE' },
		],
		locales : LOCALES.map( function( locale ) { return Object.assign( {}, locale ) } ),
	} );
	return t;
}

console.log('Admin Language');


// --- the adder: a code that is none ----------------------------------------

{
	const t = opened();
	const input = t.field('language-locale-new');
	const line = t.field('language-locale-msg');

	check( 'the adder\'s field is named by the words above it, not by the example in it', input.attributes['aria-labelledby'] === 'language-locale-new-name' && t.field('language-locale-new-name').textContent === text('/_admin/language/label/add') );
	check( 'its line is a status from the start: announced when it speaks, empty until it does', line.attributes.role === 'status' && line.attributes['aria-live'] === 'polite' && line.textContent === '' );

	input.value = 'xx';
	t.panel._addLocale();

	const error = t.field('language-locale-error');
	check( 'a code that is none is refused before anything is sent', t.requests.length === 1 && t.requests[0].endpoint === 'list' );
	check( '...the field is marked, and described by the sentence under it', input.attributes['aria-invalid'] === 'true' && input.attributes['aria-describedby'] === 'language-locale-error'
		&& error !== undefined && error.className === 'nino-admin-field-error' && error.textContent === text('/_admin/language/error/id') );
	check( '...the sentence stands under the row of the field, not in it', error !== undefined && error.parentNode === input.parentNode.parentNode && error.parentNode.children.indexOf( error ) === error.parentNode.children.indexOf( input.parentNode ) + 1 );
	check( '...the line under it announces that the value is not valid, as an alert - the sentence at the field says what a code looks like', line.attributes.role === 'alert' && line.attributes['aria-live'] === 'assertive' && line.textContent === text('/_admin/error/invalid_value') && line.textContent !== error.textContent );
	check( '...and the focus is in the field, where it is typed again', t.focused() === input );

	// A second refusal does not stack a second sentence
	t.panel._addLocale();
	check( 'a second refusal says it once', t.mount.children.length > 0 && findAll( t.mount, function( el ) { return el.className === 'nino-admin-field-error' } ).length === 1 );

	input.value = 'fr';
	fire( input, 'input' );
	check( 'typing the code again lets the refusal go: the mark, the sentence and the alert', input.attributes['aria-invalid'] === undefined && input.attributes['aria-describedby'] === undefined
		&& t.field('language-locale-error') === undefined && line.attributes.role === 'status' && line.textContent === '' );
}


// --- the adder: a code that is one -----------------------------------------

{
	const t = opened();
	const input = t.field('language-locale-new');
	const line = t.field('language-locale-msg');

	input.value = 'it_IT';
	t.panel._addLocale();

	const request = t.requests[t.requests.length - 1];
	check( 'a code is sent as it is typed', request.endpoint === 'addlocale' && request.payload.locale === 'it_IT' );
	check( '...and while the file is written the line says so, politely', line.attributes.role === 'status' && line.textContent === text('/_admin/language/msg/creating').replace( '%s', 'it_IT' ) );

	t.answer( 500, { error : 'refused' } );
	check( 'a request the server refused is an alert in the same line, in the words it gave', line.attributes.role === 'alert' && line.textContent.indexOf( 'refused' ) !== -1 && t.field('language-locale-error') === undefined );

	input.value = 'it_IT';
	t.panel._addLocale();
	t.answer( 200, { created : true, keys : 3, from : 'de_DE' } );
	const added = t.panel._locales.find( function( locale ) { return locale.code === 'it_IT' } );
	check( 'a language that was made is listed, switched off - a skeleton of empty keys is not served', added !== undefined && added.hasText === true && added.active === false && input.value === '' );
	check( '...and the line says what was made, politely', line.attributes.role === 'status' && line.textContent.indexOf( 'it_IT' ) !== -1 );
}


// --- the line also carries the warning about a language without a file -------

{
	const t = opened();
	const line = t.field('language-locale-msg');
	const french = t.field('language-locales').children.find( function( row ) { return row.children[0].dataset.locale === 'fr_FR' } ).children[0];

	check( 'while every language that is on has its file the line is empty', line.textContent === '' && line.attributes.role === 'status' );

	french.checked = true;
	fire( french, 'change' );
	check( 'switching one on that has none is announced at once: what it will render, and where to get the file', line.attributes.role === 'alert' && line.textContent === text('/_admin/language/error/missing').replace( '%s', 'fr_FR' ) );

	french.checked = false;
	fire( french, 'change' );
	check( '...and switching it off again takes the warning away', line.attributes.role === 'status' && line.textContent === '' );

	// The sentence of a refused code does not outlast the warning it replaced: it goes with the typing, and the warning is back
	french.checked = true;
	fire( french, 'change' );
	const input = t.field('language-locale-new');
	input.value = 'xx';
	t.panel._addLocale();
	check( 'a refused code takes the line from the warning', line.textContent === text('/_admin/error/invalid_value') );
	input.value = 'xxx';
	fire( input, 'input' );
	check( '...and the warning is back when the code is typed again', line.textContent === text('/_admin/language/error/missing').replace( '%s', 'fr_FR' ) && line.attributes.role === 'alert' );
}

done();
