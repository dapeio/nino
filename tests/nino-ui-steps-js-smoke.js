/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-steps-js-smoke.js	DOM-light checks for Nino.adminUi.steps(), the strip
 *											of a few values of which one is chosen: the
 *											radiogroup's aria and its one tab stop, the keys that
 *											move the choice, the mark of the default, and a strip
 *											that cannot be operated.
 *
 *	Usage: node tests/nino-ui-steps-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, fills, documentOf } = require( './harness.js' );

const sandbox = { console : console, document : documentOf( element('div') ) };
sandbox.window = sandbox;
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

function radios( strip ) {
	return findAll( strip.el, function( el ) { return el.tagName === 'BUTTON' } );
}

function key( button, name ) {
	const event = { prevented : false };
	fire( button, 'keydown', { key : name, preventDefault : function() { event.prevented = true } } );
	return event;
}

function stops( strip ) {
	return radios( strip ).filter( function( button ) { return button.tabIndex === 0 } );
}

function checked( strip ) {
	return radios( strip ).map( function( button ) { return button.getAttribute('aria-checked') } ).join();
}

const chosen = [];

function build( options ) {
	return adminUi.steps( Object.assign( {
		label : 'Spacing', steps : [ -2, -1, 0, 1, 2 ], value : 0, standard : 0,
		onChange : function( value, at ) { chosen.push( value+ '@'+ at ) },
	}, options || {} ) );
}

console.log( 'Nino.adminUi.steps - a choice among a few steps\n' );

// --- the radiogroup ------------------------------------------------------------

const strip = build();
const steps = radios( strip );

check( 'the strip is a radiogroup with a name and its steps are radios',
	strip.el.getAttribute('role') === 'radiogroup' && strip.el.getAttribute('aria-label') === 'Spacing' && steps.length === 5 && steps.every( function( button ) { return button.getAttribute('role') === 'radio' && button.type === 'button' } ) );
check( 'aria-checked marks the chosen step alone', checked( strip ) === 'false,false,true,false,false' );
check( 'the chosen step is the one tab stop', stops( strip ).length === 1 && stops( strip )[0] === steps[2] );
check( 'a number is written with its sign, the minus a minus sign', steps.map( function( button ) { return button.children[0].textContent } ).join() === '−2,−1,0,+1,+2' );
check( 'a strip can be named by what is on the page instead', build( { label : undefined, labelledby : 'spacing-label' } ).el.getAttribute('aria-labelledby') === 'spacing-label' );

const none = build( { value : 'nothing' } );
check( 'where no step is chosen nothing is checked and the first step is the tab stop', checked( none ) === 'false,false,false,false,false' && stops( none )[0] === radios( none )[0] && stops( none ).length === 1 );
check( 'get() says so', none.get() === null && strip.get() === 0 );

// --- choosing -----------------------------------------------------------------------

fire( steps[4], 'click' );
check( 'a press chooses the step, moves the tab stop and tells the caller once', strip.get() === 2 && checked( strip ) === 'false,false,false,false,true' && stops( strip )[0] === steps[4] && chosen.join() === '2@4' );
fire( steps[4], 'click' );
check( 'a press on the chosen step tells nobody', chosen.length === 1 );
check( 'the chosen step is marked for the look as well', steps[4].classList.contains('is-active') === true && steps[2].classList.contains('is-active') === false );

// --- the keys ------------------------------------------------------------------------

const keyed = build( { value : 1 } );
const keys = radios( keyed );
chosen.length = 0;
keys[3].focus();

let event = key( keys[3], 'ArrowRight' );
check( 'Right chooses the next step, moves the focus with it and is kept from scrolling', keyed.get() === 2 && keys[4].focused === true && event.prevented === true );
key( keys[4], 'ArrowDown' );
check( 'Down is Right too, and the strip wraps from the last step to the first', keyed.get() === -2 && keys[0].focused === true );
key( keys[0], 'ArrowLeft' );
check( 'Left wraps from the first step to the last', keyed.get() === 2 );
key( keys[4], 'ArrowUp' );
check( 'Up is Left too', keyed.get() === 1 );
key( keys[3], 'Home' );
key( keys[0], 'End' );
check( 'Home and End go to the first and the last step', keyed.get() === 2 && keys[4].focused === true );
check( 'every move was told, with its index', chosen.join() === '2@4,-2@0,2@4,1@3,-2@0,2@4' );
event = key( keys[4], 'a' );
check( 'another key is left to the browser', event.prevented === false && keyed.get() === 2 );
check( 'the tab stop follows the choice', stops( keyed ).length === 1 && stops( keyed )[0] === keys[4] );

// --- the default ----------------------------------------------------------------------

const marked = steps.filter( function( button ) { return button.dataset.standard === 'true' } );
check( 'the default step is marked, and one only', marked.length === 1 && marked[0] === steps[2] );
check( 'a screen reader gets its name: the step and the word for the default',
	findAll( steps[2], function( el ) { return el.className === 'nino-admin-sr-only' } )[0].textContent === ' (Default)' );
check( 'a strip with no default marks no step', radios( build( { standard : undefined } ) ).every( function( button ) { return button.dataset.standard === undefined } ) );

const german = { console : console, document : documentOf( element('div') ) };
german.window = german;
german.Nino = { content : { getText : function( key ) { return fills( '_admin/text/de_DE.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( german ) );
check( 'the word is the interface language\'s',
	findAll( german.Nino.adminUi.steps( { label : 'x', steps : [ 0, 1 ], standard : 0 } ).el, function( el ) { return el.className === 'nino-admin-sr-only' } )[0].textContent === ' (Vorgabe)' );

// --- named steps ------------------------------------------------------------------------

const named = adminUi.steps( { label : 'Size', steps : [ { value : 's', label : 'Small' }, { value : 'm', label : 'Medium', title : 'Medium size' }, { value : 'l' } ], value : 'm', wide : true } );
const namedSteps = radios( named );
check( 'steps may be named: the label is shown, the value is what is reported', namedSteps[1].children[0].textContent === 'Medium' && named.get() === 'm' );
check( 'a step with no label shows its value', namedSteps[2].children[0].textContent === 'l' );
check( 'a title is set where there is one and nowhere else', namedSteps[1].title === 'Medium size' && namedSteps[0].title === '' );
check( 'wide says so in a class', named.el.className.indexOf('nino-admin-steps--wide') !== -1 );

// --- set from outside --------------------------------------------------------------------

const outside = build( { value : 0 } );
chosen.length = 0;
outside.set( 2 );
check( 'set() chooses a step and reports nothing', outside.get() === 2 && checked( outside ) === 'false,false,false,false,true' && chosen.length === 0 );
outside.set( 'nothing' );
check( '...a value that is no step chooses none', outside.get() === null && stops( outside )[0] === radios( outside )[0] );

// A value that points at a step that cannot be chosen: the first step that can is the one tab stop
const blocked = build( { value : 1, steps : [ -1, 0, { value : 1, label : '+1', disabled : true }, 2 ], standard : undefined } );
check( 'the value is a disabled step: nothing is checked that cannot be, and the first free step is the tab stop',
	stops( blocked ).length === 1 && stops( blocked )[0] === radios( blocked )[0] );
const blockedFirst = build( { value : undefined, steps : [ { value : 0, label : '0', disabled : true }, 1, 2 ], standard : undefined } );
check( '...and a first step that is disabled is not the tab stop', stops( blockedFirst ).length === 1 && stops( blockedFirst )[0] === radios( blockedFirst )[1] );

// --- a strip that cannot be operated ---------------------------------------------------------

const off = build( { disabled : true, value : 1 } );
const offSteps = radios( off );
chosen.length = 0;
fire( offSteps[0], 'click' );
key( offSteps[3], 'ArrowRight' );
check( 'a disabled strip says so, and no press or key changes it', off.el.getAttribute('aria-disabled') === 'true' && offSteps.every( function( button ) { return button.disabled === true } ) && off.get() === 1 && chosen.length === 0 );
off.disable( false );
fire( offSteps[0], 'click' );
check( 'disable( false ) makes it operable once more', off.el.hasAttribute('aria-disabled') === false && offSteps.every( function( button ) { return button.disabled === false } ) && off.get() === -2 );

const partly = build( { value : 0, steps : [ -1, { value : 0, label : '0' }, { value : 1, disabled : true, label : '+1' }, 2 ], standard : undefined } );
const partlySteps = radios( partly );
partlySteps[1].focus();
key( partlySteps[1], 'ArrowRight' );
check( 'a step that is disabled is passed by the arrows', partly.get() === 2 && partlySteps[3].focused === true );
key( partlySteps[3], 'End' );
check( '...and is not the end', partly.get() === 2 );
fire( partlySteps[2], 'click' );
check( '...and cannot be pressed', partly.get() === 2 );

// --- what is put in ------------------------------------------------------------------------------

const hostile = '<img src=x onerror=alert(1)>';
const evil = adminUi.steps( { label : hostile, steps : [ { value : 'a', label : hostile, title : hostile } ], value : 'a' } );
check( 'a label and a title are text, whatever they hold',
	radios( evil )[0].children[0].textContent === hostile && findAll( evil.el, function( el ) { return el.tagName === 'IMG' } ).length === 0 );

done();
