/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-color-js-smoke.js	DOM-light checks for Nino.adminUi.color() and its
 *											pure half, Nino.adminUi.colorModel: what is a colour
 *											code, the contrast of two of them and the line that
 *											says whether it is enough, what the field does with
 *											a code that is not one, and what it never lets into
 *											the page.
 *
 *	Usage: node tests/nino-ui-color-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, fills, documentOf, source } = require( './harness.js' );

// The clock of the page: what was asked to run later, until a test lets time pass
let pending = [];
let ticket = 0;

function flush() {
	const due = pending;
	pending = [];
	due.forEach( function( timer ) { timer.fn() } );
}

function world( locale ) {
	const sandbox = { console : console, document : documentOf( element('div') ),
		setTimeout : function( fn, ms ) { pending.push( { id : ++ticket, fn : fn, ms : ms } ); return ticket },
		clearTimeout : function( id ) { pending = pending.filter( function( timer ) { return timer.id !== id } ) } };
	sandbox.window = sandbox;
	const map = fills( '_admin/text/'+ locale+ '.php' );
	sandbox.Nino = { content : { getText : function( key ) { return map[key] ?? '' } } };
	load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
	return sandbox.Nino.adminUi;
}

const adminUi = world('en_US');
const model = adminUi.colorModel;

function byClass( root, name ) {
	return findAll( root, function( el ) { return String( el.className ).split(' ').indexOf( name ) !== -1 } )[0];
}

const told = [];

function build( ui, options ) {
	return ( ui || adminUi ).color( Object.assign( {
		label : 'Brand', value : '#2563eb', against : '#ffffff',
		onChange : function( value ) { told.push( 'change:'+ value ) },
		onInvalid : function( typed ) { told.push( 'invalid:'+ typed ) },
	}, options || {} ) );
}

// Typed, and the typing rests: the line is written
function type( field, text ) {
	hand( field, text );
	flush();
}

// Typed, and the typing goes on
function hand( field, text ) {
	field.input.value = text;
	fire( field.input, 'input' );
}

// What a screen reader is told: the copy of the line in the live region
function announced( field ) {
	return byClass( field.el, 'nino-admin-sr-only' ).textContent;
}

function line( field ) {
	return byClass( field.el, 'nino-admin-color-contrast' );
}

// What the line says: its words, after the mark
function said( field ) {
	return line( field ).children[1].textContent;
}

console.log( 'Nino.adminUi.color - a colour with its contrast\n' );

// --- what is a colour code ----------------------------------------------------------

check( 'a code is #rrggbb in lower case, however it was written',
	[ '#2563EB', '2563eb', ' #2563eb ', '#25EB63'.toLowerCase() ].map( model.parse ).join() === '#2563eb,#2563eb,#2563eb,#25eb63' );
check( 'a short code is the long one', model.parse('#abc') === '#aabbcc' && model.parse('F0A') === '#ff00aa' );
check( 'anything else is none: a name, a function, an alpha, a wrong length, a wrong digit, two codes, a number, nothing',
	[ '', 'red', 'rgb(1,2,3)', '#ff00ff80', '#12', '#12345', '#1234567', '#gggggg', '#fff\n#000', '##fff', '＃fff', 12, null, undefined, {}, [ '#fff' ] ].every( function( text ) { return model.parse( text ) === null } ) );
check( 'a null byte is not whitespace to be trimmed', model.parse('#fff\0') === null && model.parse('\0#fff') === null );

// --- the contrast -------------------------------------------------------------------------

check( 'black on white is 21 : 1 and a colour on itself 1 : 1', model.contrast( '#000', '#fff' ) === 21 && model.contrast( '#2563eb', '#2563eb' ) === 1 );
check( 'the order of the two does not matter', model.contrast( '#2563eb', '#ffffff' ) === model.contrast( '#ffffff', '#2563eb' ) );
check( '#767676 on white is the lightest grey that is enough, #777777 is not',
	model.contrast( '#767676', '#fff' ) >= model.ENOUGH && model.contrast( '#777777', '#fff' ) < model.ENOUGH );
check( 'a code that is none has no contrast', model.contrast( 'red', '#fff' ) === null && model.contrast( '#fff', '' ) === null && model.luminance( 'nope' ) === null );
check( 'the ratio that is enough is the one WCAG asks of ordinary text', model.ENOUGH === 4.5 );
check( 'a ratio is cut, never rounded up: 4.499 is 4.49 and 4.5 is 4.5, and the cut is not fooled by what a float cannot hold',
	model.truncate( 4.499 ) === 4.49 && model.truncate( 4.5 ) === 4.5 && model.truncate( 4.57 ) === 4.57 && model.truncate( 21 ) === 21 );

// --- the field: a code that is one -----------------------------------------------------------

const field = build();
const input = field.input;

check( 'the text field holds what the field was given, the picker its colour, the surface shows it',
	input.value === '#2563eb' && byClass( field.el, 'nino-admin-color-picker' ).value === '#2563eb' && byClass( field.el, 'nino-admin-color-swatch' ).style.backgroundColor === '#2563eb' );
check( 'the picker is a colour input with a name that says what it picks', byClass( field.el, 'nino-admin-color-picker' ).type === 'color' && byClass( field.el, 'nino-admin-color-picker' ).getAttribute('aria-label') === 'Brand – Choose a colour' );
check( 'the surface shows the second colour as text on the first, and is not read out', byClass( field.el, 'nino-admin-color-swatch' ).style.color === '#ffffff' && byClass( field.el, 'nino-admin-color-swatch' ).getAttribute('aria-hidden') === 'true' );
check( 'the contrast is a number and a verdict, in words and with a mark, and is a live region',
	said( field ).indexOf('5.16 : 1') !== -1 && said( field ).indexOf('enough (from 4.5 : 1)') !== -1 && line( field ).dataset.state === 'ok'
	&& byClass( line( field ), 'nino-admin-color-mark' ).getAttribute('aria-hidden') === 'true' );
check( 'the text field is described by it', input.getAttribute('aria-describedby') === line( field ).id );
check( 'the line is no live region: the copy a screen reader is told is, and it is the same words',
	line( field ).hasAttribute('aria-live') === false && byClass( field.el, 'nino-admin-sr-only' ).getAttribute('aria-live') === 'polite' && announced( field ) === said( field ) && adminUi.COLOR_QUIET === 400 );

type( field, '#FFF' );
check( 'a valid code is taken at once, as #rrggbb, and told to the caller', field.get() === '#ffffff' && told[told.length - 1] === 'change:#ffffff' );
check( '...the picker and the surface follow', byClass( field.el, 'nino-admin-color-picker' ).value === '#ffffff' && byClass( field.el, 'nino-admin-color-swatch' ).style.backgroundColor === '#ffffff' );
check( '...what stands in the field is left as it was typed while the person types', input.value === '#FFF' );
fire( input, 'change' );
check( '...and is the long code when the field is left', input.value === '#ffffff' );

told.length = 0;
const picker = byClass( field.el, 'nino-admin-color-picker' );
picker.value = '#112233';
fire( picker, 'input' );
check( 'the picker writes into the field and tells the caller', input.value === '#112233' && field.get() === '#112233' && told.join() === 'change:#112233' );

// --- the field: a code that is not one -----------------------------------------------------------

told.length = 0;
type( field, '#12' );
const error = byClass( field.el, 'nino-admin-field-error' );
check( 'a code that is none says so at the field: marked invalid, the sentence pointed at',
	input.getAttribute('aria-invalid') === 'true' && error !== undefined && error.textContent === 'Enter a colour as #rrggbb or #rgb.' && input.getAttribute('aria-describedby').split(' ').indexOf( error.id ) !== -1 );
check( '...and only the sentence describes the field: the line that was true of the code before is hidden and not named',
	input.getAttribute('aria-describedby') === error.id && line( field ).hidden === true );
check( '...the caller is told what stands there, not given a colour', told.join() === 'invalid:#12' && field.get() === null );
check( '...nothing is shown of a colour that is none: no surface, no contrast', byClass( field.el, 'nino-admin-color-swatch' ).hidden === true && line( field ).hidden === true );
check( '...the picker keeps the last colour, it can show no other', byClass( field.el, 'nino-admin-color-picker' ).value === '#112233' );

const mandatory = build( undefined, { required : true } );
type( mandatory, '' );
check( 'an empty field is the required field\'s sentence, where the field is required', byClass( mandatory.el, 'nino-admin-field-error' ).textContent === 'This required field is empty.' && mandatory.input.getAttribute('aria-required') === 'true' );

told.length = 0;
type( field, '' );
check( 'and else it is no colour and no error: the caller is told an empty code',
	field.get() === null && byClass( field.el, 'nino-admin-field-error' ) === undefined && field.input.hasAttribute('aria-invalid') === false && told.join() === 'change:' );
type( field, '#12' );
check( 'one error stands at a time', findAll( field.el, function( el ) { return el.className === 'nino-admin-field-error' } ).length === 1 );

type( field, '#336699' );
check( 'a code that is one takes the sentence away and the mark with it',
	findAll( field.el, function( el ) { return el.className === 'nino-admin-field-error' } ).length === 0 && input.hasAttribute('aria-invalid') === false && input.getAttribute('aria-describedby') === line( field ).id && field.get() === '#336699' );

// --- what is pasted ---------------------------------------------------------------------------------------

const pasted = build();
type( pasted, '#11223344' );
check( 'a code of eight digits is no colour, and is not cut to one', pasted.get() === null && pasted.input.value === '#11223344' && pasted.input.hasAttribute('aria-invalid') === true );
type( pasted, '  #2563EB  ' );
check( 'the space round a code is trimmed', pasted.get() === '#2563eb' && pasted.input.hasAttribute('aria-invalid') === false );
type( pasted, 'rgb(37, 99, 235)' );
check( 'rgb() is no colour: the sentence, and what was pasted stays whole in the field', pasted.get() === null && pasted.input.value === 'rgb(37, 99, 235)' && byClass( pasted.el, 'nino-admin-field-error' ) !== undefined );
check( 'the field has no length of its own to cut it by', pasted.input.maxLength === -1 );

// --- the line is written when the typing rests -----------------------------------------------------------------

const typing = build();
pending = [];
hand( typing, '#7' );
hand( typing, '#77' );
hand( typing, '#777777' );
check( 'a code typed on shows its contrast at every digit, and what is read out waits: nothing is told on the way, one timer of 400 ms',
	typing.get() === '#777777' && said( typing ).indexOf('4.47') !== -1 && announced( typing ) === '' && pending.length === 1 && pending[0].ms === 400 );
flush();
check( '...and is told once the typing has rested', announced( typing ) === said( typing ) && announced( typing ).indexOf('4.47') !== -1 );
hand( typing, '#76' );
check( 'a code that is not one hides the line and the copy at once and cancels the one that waits', line( typing ).hidden === true && announced( typing ) === '' && pending.length === 0 );

const quiet = build( undefined, { against : undefined } );
hand( quiet, '#777777' );
pending = [];
quiet.against( '#ffffff' );
check( 'a second colour put in from outside shows its line at once and is told after the rest', line( quiet ).hidden === false && said( quiet ).indexOf('4.47') !== -1 && announced( quiet ) === '' && pending.length === 1 );
for( let at = 0; at < 60; at++ )
	quiet.against( at % 2 === 0 ? '#000000' : '#ffffff' );
check( 'a second colour dragged through sixty values is one telling, of the last', pending.length === 1 && said( quiet ).indexOf('4.47') !== -1 );
flush();
check( '...which says the line as it ended', announced( quiet ) === said( quiet ) );
hand( quiet, '#000000' );
hand( quiet, '#ffffff' );
check( 'the line follows the typing, and the copy is the one that was told', said( quiet ).indexOf('1 : 1') !== -1 && announced( quiet ).indexOf('4.47') !== -1 );
flush();
check( '...until the typing rests', announced( quiet ).indexOf('1 : 1') !== -1 );


// --- the verdict at the line -----------------------------------------------------------------------------

const edge = build( undefined, { value : '#767676' } );
check( 'the lightest grey that is enough is enough', edge.el && line( edge ).dataset.state === 'ok' && said( edge ).indexOf('4.54 : 1') !== -1 );
type( edge, '#777777' );
check( 'the next one is not, in words, with a mark, and not by its colour alone',
	line( edge ).dataset.state === 'error' && said( edge ).indexOf('not enough (from 4.5 : 1)') !== -1 && byClass( line( edge ), 'nino-admin-color-mark' ).textContent === '✕' );
check( 'a number that reads as enough is enough, and one that does not read so is not: over a spread of greys',
	( function() {
		for( let at = 0; at < 256; at += 5 ) {
			const code = '#'+ at.toString( 16 ).padStart( 2, '0' ).repeat( 3 );
			type( edge, code );
			const shown = parseFloat( said( edge ).match( /(\d+\.?\d*) : 1/ )[1] );
			if( ( line( edge ).dataset.state === 'ok' ) !== ( shown >= 4.5 ) )
				return false;
		}
		return true;
	} )() );

const exact = build( undefined, { value : '#767676', minimum : model.contrast( '#767676', '#ffffff' ) } );
check( 'a ratio that is exactly the minimum is enough', line( exact ).dataset.state === 'ok' );

const large = build( undefined, { value : '#949494', minimum : 3 } );
check( 'the ratio that is enough can be the one of large text', line( large ).dataset.state === 'ok' && said( large ).indexOf('from 3 : 1') !== -1 );

// --- the second colour ---------------------------------------------------------------------------------------

const second = build( undefined, { value : '#2563eb', against : undefined } );
check( 'with no second colour there is no contrast to draw', line( second ).hidden === true );
second.against( '#000000' );
check( 'against() measures against another colour', line( second ).hidden === false && said( second ).indexOf('4.06 : 1') !== -1 && byClass( second.el, 'nino-admin-color-swatch' ).style.color === '#000000' );
second.against( 'nothing' );
check( '...and a colour that is none takes the contrast away', line( second ).hidden === true );

// --- set from outside -------------------------------------------------------------------------------------------

told.length = 0;
type( field, 'x' );
field.set( '#abcdef' );
check( 'set() writes a colour, clears an error and tells nobody', field.get() === '#abcdef' && input.value === '#abcdef' && findAll( field.el, function( el ) { return el.className === 'nino-admin-field-error' } ).length === 0 && input.hasAttribute('aria-invalid') === false && told.join() === 'invalid:x' );
field.set( 'junk' );
check( '...and a value that is no colour leaves the field with none', field.get() === null );
check( '...and says so at the field as a typed one does, without telling the caller', byClass( field.el, 'nino-admin-field-error' ) !== undefined && field.input.getAttribute('aria-invalid') === 'true' && told.join() === 'invalid:x' );
field.set( '' );
check( '...an empty value is no error where the field is not required', byClass( field.el, 'nino-admin-field-error' ) === undefined );

const junk = build( undefined, { value : 'junk' } );
check( 'a field that starts with what is no colour says so', junk.get() === null && byClass( junk.el, 'nino-admin-field-error' ) !== undefined );
check( '...and one that starts empty does not', byClass( build( undefined, { value : '' } ).el, 'nino-admin-field-error' ) === undefined );

// --- what is put in ----------------------------------------------------------------------------------------------------

const hostile = '<img src=x onerror=alert(1)>';
const evil = build( undefined, { label : hostile } );
check( 'the label is text, whatever it holds', findAll( evil.el, function( el ) { return el.tagName === 'IMG' } ).length === 0 && findAll( evil.el, function( el ) { return el.textContent === hostile } ).length === 1 );

const surface = byClass( evil.el, 'nino-admin-color-swatch' );
[ 'red; background:url(//evil.test/x)', '#fff" onload="alert(1)', '#fff\0', 'url(javascript:alert(1))', 'expression(alert(1))', '<script>alert(1)</script>' ].forEach( function( bad ) {
	type( evil, '#2563eb' );
	type( evil, bad );
} );
check( 'only a code that was parsed reaches the style of the surface: nothing else was ever written there',
	/^#[0-9a-f]{6}$/.test( surface.style.backgroundColor ) && /^#[0-9a-f]{6}$/.test( surface.style.color ) && evil.get() === null && surface.hidden === true );

const big = 'a'.repeat( 1000000 );
type( evil, big );
// "Soon" is no matter of a clock: the pattern that reads a colour has no quantifier without a bound - only the {3} and {6} of the two forms and one optional # - so that what it
// takes is bounded by the pattern and not by the length of what it is given, and a million characters are refused after the seventh. A pattern that repeats a group, or one
// that has a + or a * in it, would be read here
const modelSource = String( source( '_admin/assets/Nino.admin.js' ) ).split( 'colorModel : {' )[1].split( 'luminance :' )[0];
const pattern = /\/\^([^\n]*?)\$\/i\.exec\(/.exec( modelSource );
check( 'an input of a million characters is one that is not a colour, and is refused as soon as any other: the pattern has no unbounded quantifier and no repeated group',
	evil.get() === null && pattern !== null && /[*+]/.test( pattern[1] ) === false && /\)[*+?]|\)\{/.test( pattern[1] ) === false && ( pattern[1].match( /\{\d+\}/g ) || [] ).length === 2 );
check( '...and a code that is a colour is read by it, in the forms it has', model.parse( '#ABC' ) === '#aabbcc' && model.parse( '1a2b3c' ) === '#1a2b3c' && model.parse( big ) === null );

// --- the words ------------------------------------------------------------------------------------------------------------

const german = build( world('de_DE'), { value : '#767676' } );
check( 'the interface language\'s words, and its decimal mark', said( german ) === 'Kontrast 4,54 : 1 · reicht (ab 4,5 : 1)' );
type( german, '#777777' );
check( 'the verdict that it is not enough is said in it as well', said( german ) === 'Kontrast 4,47 : 1 · reicht nicht (ab 4,5 : 1)' );
type( german, 'x' );
check( 'the error is said in it as well', byClass( german.el, 'nino-admin-field-error' ).textContent === 'Gib eine Farbe als #rrggbb oder #rgb ein.' );

const worded = build( undefined, { text : { invalid : 'No colour', pick : 'Pick', contrast : 'Ratio %s', enough : 'ok at %s', short : 'low at %s' } } );
type( worded, '#777777' );
check( 'the words can be said differently', said( worded ) === 'Ratio 4.47 · low at 4.5' && byClass( worded.el, 'nino-admin-color-picker' ).getAttribute('aria-label') === 'Brand – Pick' );

done();
