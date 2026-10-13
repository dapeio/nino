/**
 *	Nino										A compact filesystembased php framework
 *	admin-fields-js-smoke.js	The small builders of Nino.adminUi that a panel
 *													assembles its screen from: hint(), textField(),
 *													el(), button() and localeSelect(). What they build
 *													is what a person sees and what a script reads back,
 *													so the checks read the elements: the classes, the
 *													attributes, the words as they were given, and what
 *													the events hand to the caller.
 *
 *	Usage: node tests/admin-fields-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, documentOf, element, findAll, fire } = require( './harness.js' );

const page = element('div');

const sandbox = { console : console, document : documentOf( page ), Nino : { content : { getText : function() { return '' } } } };
sandbox.window = { Nino : sandbox.Nino };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );

const ui = sandbox.Nino.adminUi;

const classes = function( el ) { return String( el.className ).split(/\s+/).filter( Boolean ) };


// --- hint ------------------------------------------------------------------

console.log( 'Nino.adminUi.hint - one line of explanation\n' );

const hint = ui.hint( 'Up to <b>60</b> letters, %s' );
check( 'a hint is a paragraph in the design system\'s hint type', hint.tagName === 'P' && hint.className === 'nino-admin-hint' );
check( '...whose words are text as they were given: no markup is read, no placeholder is filled', hint.textContent === 'Up to <b>60</b> letters, %s' && hint.children.length === 0 );

const refusal = ui.hint( 'The name is taken.', 'error' );
check( 'the error kind adds the error colour to the hint', classes( refusal ).join() === 'nino-admin-hint,nino-admin-error' && refusal.textContent === 'The name is taken.' );
check( '...any other kind is a plain hint', classes( ui.hint( 'x', 'warning' ) ).join() === 'nino-admin-hint' );


// --- textField -------------------------------------------------------------

console.log( '\nNino.adminUi.textField - a labelled line of text\n' );

const plain = ui.textField( { label : 'Name' } );
const plainInput = findAll( plain, function( el ) { return el.tagName === 'INPUT' } )[0];
check( 'a field is a label holding its name and its input', plain.tagName === 'LABEL' && plain.className === 'nino-admin-field' && plain.children.length === 2 && plain.children[0].tagName === 'SPAN' && plain.children[1] === plainInput );
check( '...the name is a text node of the words as they were given', plain.children[0].children.length === 1 && plain.children[0].children[0].textContent === 'Name' );
check( '...the input is a text input in the design system\'s input class, with nothing set that was not asked for', plainInput.type === 'text' && plainInput.className === 'nino-admin-input'
	&& plainInput.value === '' && plainInput.placeholder === '' && plainInput.maxLength === -1 && plainInput.id === '' && plainInput.required !== true
	&& Object.keys( plainInput.dataset ).length === 0 && 'minLength' in plainInput === false && 'autocomplete' in plainInput === false && 'aria-required' in plainInput.attributes === false );
check( '...and no star and no hint', findAll( plain, function( el ) { return el.className === 'nino-admin-required' } ).length === 0 && findAll( plain, function( el ) { return el.tagName === 'SMALL' } ).length === 0 );

const full = ui.textField( {
	key : 'title', type : 'search', value : 'Hello', placeholder : 'A title', label : '/_admin/words', hint : 'At most 60 letters',
	required : true, maxLength : 60, minLength : 2, autocomplete : 'off', id : 'hello-title', className : 'nino-admin-field nino-admin-field-wide',
} );
const fullInput = findAll( full, function( el ) { return el.tagName === 'INPUT' } )[0];
check( 'every option lands on the input: type, key, value, placeholder, lengths, autocomplete and id', fullInput.type === 'search' && fullInput.dataset.key === 'title' && fullInput.value === 'Hello' && fullInput.placeholder === 'A title'
	&& fullInput.maxLength === 60 && fullInput.minLength === 2 && fullInput.autocomplete === 'off' && fullInput.id === 'hello-title' );
check( '...the label takes the class it is given, and the words are used as they are - a fill key is the caller\'s to resolve', full.className === 'nino-admin-field nino-admin-field-wide' && full.children[0].children[0].textContent === '/_admin/words' );

const star = full.children[0].children[1];
check( 'a required field has its star behind the name, hidden from a screen reader, and says it is required on the input', star !== undefined && star.tagName === 'SPAN' && star.className === 'nino-admin-required' && star.attributes['aria-hidden'] === 'true' && star.textContent === '*'
	&& fullInput.required === true && fullInput.attributes['aria-required'] === 'true' );

const note = full.children[full.children.length - 1];
check( '...the hint is a small line of the design system\'s hint type, after the input', note.tagName === 'SMALL' && note.className === 'nino-admin-hint' && note.textContent === 'At most 60 letters' && full.children[1] === fullInput );

const typed = [];
const listening = ui.textField( { label : 'x', onInput : function( value ) { typed.push( value ) } } );
const listeningInput = findAll( listening, function( el ) { return el.tagName === 'INPUT' } )[0];
listeningInput.value = 'abc';
fire( listeningInput, 'input' );
listeningInput.value = 'abcd';
fire( listeningInput, 'input' );
check( 'typing calls onInput with the value in the input', typed.join() === 'abc,abcd' );

let silent = null;
try { fire( findAll( plain, function( el ) { return el.tagName === 'INPUT' } )[0], 'input' ) } catch( error ) { silent = error }
check( '...and a field without one is typed into without a word', silent === null );


// --- el --------------------------------------------------------------------

console.log( '\nNino.adminUi.el - an element, a class and a text\n' );

const bare = ui.el( 'section' );
check( 'el() makes the tag, and sets neither a class nor a text it was not given', bare.tagName === 'SECTION' && bare.className === '' && bare.textContent === '' );

const styled = ui.el( 'span', 'nino-admin-hint', 'Saved' );
check( '...with a class and a text it sets both', styled.className === 'nino-admin-hint' && styled.textContent === 'Saved' );
check( '...a class that is empty or no string is none', ui.el( 'p', '', 'x' ).className === '' && ui.el( 'p', null, 'x' ).className === '' && ui.el( 'p', 42, 'x' ).className === '' );
check( '...the text is set as text, and an empty text is a text', ui.el( 'p', undefined, '<i>x</i>' ).textContent === '<i>x</i>' && ui.el( 'p', undefined, '' ).textContent === '' && ui.el( 'p', 'a' ).textContent === '' );


// --- button ----------------------------------------------------------------

console.log( '\nNino.adminUi.button - a button, a name for a glyph\n' );

const clicks = [];
const save = ui.button( 'Save', function() { clicks.push( 'save' ) } );
check( 'a button is a real button that does not submit, showing what it was given', save.tagName === 'BUTTON' && save.type === 'button' && save.textContent === 'Save' && save.className === '' && save.disabled === false && save.id === '' );
check( '...and has neither a title nor an aria-label of its own', save.title === '' && 'aria-label' in save.attributes === false );

fire( save, 'click' );
check( '...a click calls what it was given', clicks.join() === 'save' );
check( '...without one it can be clicked all the same', fire( ui.button( 'No' ), 'click' ) === undefined );

const move = ui.button( '↑', function() {}, { className : 'nino-admin-btn-secondary nino-admin-btn--small', name : 'Move up', disabled : true, id : 'move-up' } );
check( 'the options set the class, the id and the disabled state', move.className === 'nino-admin-btn-secondary nino-admin-btn--small' && move.id === 'move-up' && move.disabled === true && move.textContent === '↑' );
check( '...and the name of a glyph is its title and its aria-label both', move.title === 'Move up' && move.attributes['aria-label'] === 'Move up' );
check( 'only true disables a button', ui.button( 'x', null, { disabled : 'yes' } ).disabled === false && ui.button( 'x', null, { disabled : false } ).disabled === false );
check( 'only submit makes a button submit', ui.button( 'Go', null, { type : 'submit' } ).type === 'submit' && ui.button( 'Go', null, { type : 'reset' } ).type === 'button' );


// --- localeSelect ----------------------------------------------------------

console.log( '\nNino.adminUi.localeSelect - which translation a form shows\n' );

const picked = [];
const locales = ui.localeSelect( { locales : [ 'de_DE', 'en_US', 'fr_FR' ], current : 'en_US', label : 'Language', id : 'text-form-locale-select', onChange : function( code ) { picked.push( code ) } } );
check( 'it is a select in the pill and context bar classes of the design system', locales.tagName === 'SELECT' && classes( locales ).join() === 'nino-admin-locale-select,nino-admin-contextbar-select' );
check( '...with an option for every code, the code being its value and its text', locales.children.length === 3 && locales.children.every( function( option, at ) { return option.tagName === 'OPTION' && option.value === [ 'de_DE', 'en_US', 'fr_FR' ][at] && option.textContent === option.value } ) );
check( '...the current one picked', locales.value === 'en_US' && locales.children[1].selected === true && locales.children[0].selected === false && locales.children[2].selected === false );
check( '...and the id and the name it was given', locales.id === 'text-form-locale-select' && locales.attributes['aria-label'] === 'Language' );

locales.value = 'fr_FR';
fire( locales, 'change' );
check( 'a change calls onChange with the code picked', picked.join() === 'fr_FR' );

const unnamed = ui.localeSelect( { locales : [ 'de_DE', 'en_US' ], current : 'de_DE' } );
check( 'without a label it has no aria-label, without an id none, and a change without a handler is nothing', unnamed.id === '' && 'aria-label' in unnamed.attributes === false && unnamed.value === 'de_DE' && fire( unnamed, 'change' ) === undefined );

done();
