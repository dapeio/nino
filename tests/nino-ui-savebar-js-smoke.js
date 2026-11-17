/**
 *	Nino								A compact filesystembased php framework
 *	nino-ui-savebar-js-smoke.js	Nino.adminUi.saveBar(): the bar of a screen that is saved as a whole - the state, the
 *												status line, the buttons of the caller, Restore and Save; Save pressed once at a
 *												time and kept back by a reason; what is changed while a save is on its way
 *												staying a change; the entry the shell's registry of unsaved input takes.
 *
 *	Usage: node tests/nino-ui-savebar-js-smoke.js
 */

'use strict';

const vm = require('vm');
const fs = require('fs');
const path = require('path');
const { check, done, load, documentOf, element, findAll, fire, fills } = require( './harness.js' );

const timers = [];
const sandbox = { console : console, document : documentOf( element('div') ) };
sandbox.setTimeout = function( fn, ms ) { timers.push( { fn : fn, ms : ms, live : true } ); return timers.length - 1 };
sandbox.clearTimeout = function( id ) { if( timers[id] !== undefined ) timers[id].live = false };
sandbox.window = sandbox;
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

const refreshed = [];
sandbox.Nino.admin = { dirty : { refresh : function() { refreshed.push(1) } } };

function build( extra ) {

	const world = { saves : [], restored : [], pressed : [] };

	world.bar = adminUi.saveBar( Object.assign( {
		buttons : [ { key : 'undo', label : '↶', name : 'Undo', onPress : function() { world.pressed.push('undo') } } ],
		onSave : function( answer ) { world.saves.push( answer ) },
		onRestore : function( opener ) { world.restored.push( opener ) },
	}, extra || {} ) );

	return world;
}

const names = bar => findAll( bar.el, el => el.tagName === 'BUTTON' ).map( el => el.textContent );

// On a phone the line is a row of its own only while it holds words, and the registry's mark is not drawn beside the badge
const stylesheet = fs.readFileSync( path.join( __dirname, '../_admin/assets/style.css' ), 'utf8' );
check( 'on a phone the bar of a saveBar shows its line only where it is not empty and does not draw the registry\'s mark of unsaved changes',
	/@media \(max-width: 38rem\) \{[^@]*\.nino-admin-actionbar\.nino-admin-savebar > \.nino-admin-status:not\(:empty\) \{\s*display: block;[^@]*\.nino-admin-actionbar\.nino-admin-savebar > \.nino-admin-actionbar-dirty \{\s*display: none;/.test( stylesheet ) === true );
check( 'on a phone the line is the row above the badge and the buttons, which are compact: 30 px and 12 px words', /@media \(max-width: 38rem\) \{[^@]*\.nino-admin-savebar > button \{\s*min-height: var\(--nino-admin-control-small\);[^}]*font-size: var\(--nino-admin-text-xs\)[^@]*\.nino-admin-savebar > \.nino-admin-status:not\(:empty\) \{[^}]*order: -3;[^}]*flex: 1 0 100%/.test( stylesheet ) === true );
check( 'the sentence of a first change is kept off the screen by the stylesheet, and only while the line still says it', /\.nino-admin-savebar > \.nino-admin-status\[data-once\]\[data-state="dirty"\] \{[^}]*clip-path: inset\(50%\)/.test( stylesheet ) === true );

console.log( 'Nino.adminUi.saveBar - the bar of a screen that is saved as a whole\n' );

{
	const w = build();

	check( 'the bar is an action bar of the system: the state, the status line, the buttons of the caller, then Restore and Save', w.bar.el.className.split(' ').includes('nino-admin-actionbar') === true
		&& w.bar.el.children[0] === w.bar.state && w.bar.el.children[1] === w.bar.line.element && names( w.bar ).join() === '↶,Restore the last state,Save' );
	check( '...the status line is a status that is announced, the state a word and a mark', w.bar.line.element.getAttribute('role') === 'status' && w.bar.line.element.getAttribute('aria-live') === 'polite' && w.bar.state.textContent === '✓ Saved' );
	check( 'at the start there is nothing to save and no last state: Save is off and Restore is not on the screen', w.bar.save.disabled === true && w.bar.restore.hidden === true );
	check( 'a button of the caller is a button with its name, and is the caller\'s to enable', w.bar.buttons.undo.getAttribute('aria-label') === 'Undo' && ( fire( w.bar.buttons.undo, 'click' ), w.pressed.join() === 'undo' ) );

	w.bar.set( { dirty : true } );
	check( 'a change turns the state to "Changed" with its mark - never the colour alone - and Save on', w.bar.state.textContent === '● Changed' && w.bar.state.dataset.state === 'warn' && w.bar.save.disabled === false );
	check( '...and the shell is told, where it has a registry of unsaved input', refreshed.length > 0 );
}

{
	const w = build();

	w.bar.set( { dirty : true, restorable : true } );
	check( 'Restore is on the screen once there is a last state, and waits while changes are unsaved, with the reason as its title', w.bar.restore.hidden === false && w.bar.restore.disabled === true && w.bar.restore.title === 'Save or discard the changes first.' );

	w.bar.set( { dirty : false } );
	fire( w.bar.restore, 'click' );
	check( '...and with none it is pressed, and given to the caller as the opener of what it asks', w.bar.restore.disabled === false && w.restored[0] === w.bar.restore );

	const none = build( { restore : false } );
	none.bar.set( { restorable : true } );
	check( 'a bar that has no last state to go back to has no Restore', names( none.bar ).join() === '↶,Save' );
}

{
	const w = build();

	w.bar.set( { dirty : true } );
	fire( w.bar.save, 'click' );
	fire( w.bar.save, 'click' );
	check( 'Save is pressed once at a time: Save is off, and a second press is not a second save', w.saves.length === 1 && w.bar.save.disabled === true );
	check( '...the line says "saving" only when the save has taken a moment: not at the press, where the caller may still be asking something', w.bar.line.state !== 'saving' && timers[timers.length - 1].ms === 400 );
	timers[timers.length - 1].fn();
	check( '...and then it does', w.bar.line.state === 'saving' );

	w.bar.set( { dirty : true } );
	w.saves.shift()( true );
	check( 'what was changed while the save was on its way is not in what was sent: it stays a change', w.bar.state.dataset.state === 'warn' && w.bar.line.state === 'dirty' && w.bar.save.disabled === false );

	fire( w.bar.save, 'click' );
	w.saves.shift()( true );
	check( 'a save that ends well is "Saved", on the line and in the state, and Save is off again', w.bar.line.state === 'saved' && w.bar.state.textContent === '✓ Saved' && w.bar.save.disabled === true );

	w.bar.set( { dirty : true } );
	fire( w.bar.save, 'click' );
	w.saves.shift()( false );
	check( 'a save that ends badly leaves the changes where they are, and Save can be pressed again', w.bar.state.dataset.state === 'warn' && w.bar.save.disabled === false );
}

{
	const w = build();
	const ended = [];

	w.bar.set( { dirty : true, blocked : 'The name is taken.' } );
	check( 'a reason that keeps the save back is said on the line, as an error that is read out at once, and Save waits', w.bar.line.state === 'error' && w.bar.line.element.textContent === 'The name is taken.'
		&& w.bar.line.element.getAttribute('role') === 'alert' && w.bar.save.disabled === true );

	w.bar.press( function( ok ) { ended.push( ok ) } );
	check( '...and pressing Save by the keyboard is no save, and says it was none', w.saves.length === 0 && ended.join() === 'false' );

	w.bar.set( { blocked : '' } );
	check( '...it goes when the cause does, and the line says what is the case then', w.bar.line.state === 'dirty' && w.bar.save.disabled === false );
}

{
	const w = build();
	const entry = w.bar.entry( { discard : function() {} } );

	w.bar.set( { dirty : true } );
	const results = [];
	entry.save( function( ok ) { results.push( ok ) } );
	w.saves.shift()( true );
	check( 'entry() is what the shell\'s registry of unsaved input takes: it knows whether there is something, saves and ends with done on both ways, and has the bar the marker is drawn in',
		entry.isDirty() === false && results.join() === 'true' && entry.bar() === w.bar.el && typeof entry.discard === 'function' );
}

{
	const w = build();

	w.bar.set( { dirty : true } );
	check( 'the first change of a saved screen is said once on the line, a status that is read out', w.bar.line.state === 'dirty' && w.bar.line.element.getAttribute('role') === 'status' && w.bar.line.element.textContent !== '' );
	w.bar.line.idle( 'The caller said something else.' );
	w.bar.set( { dirty : true } );
	check( '...and not again while the changes stand', w.bar.line.element.textContent === 'The caller said something else.' );
	w.bar.set( { dirty : false } );
	w.bar.set( { dirty : true } );
	check( '...but again at the next first change', w.bar.line.state === 'dirty' );

	w.bar.set( { dirty : false, calm : 'Applied' } );
	check( 'a quiet state of its own is said with its word and a mark', w.bar.state.textContent === '\u2713 Applied' && w.bar.state.dataset.state === 'muted' );
	w.bar.set( { dirty : true } );
	check( '...and a change takes it over again with the mark of a change', w.bar.state.dataset.state === 'warn' );
	w.bar.set( { dirty : false, calm : '' } );
	check( 'and with no word it is the saved one', w.bar.state.textContent === '\u2713 Saved' && w.bar.state.dataset.state === 'ok' );
}

{
	const w = build();

	timers.length = 0;
	w.bar.set( { dirty : true } );
	w.bar.set( { dirty : true } );
	check( 'the sentence of the first change is marked as said once and is taken back after a few seconds, once', w.bar.line.element.dataset.once === 'dirty' && timers.length === 1 && timers[0].ms === 4000 );
	timers[0].fn();
	check( '...the line is empty then, the badge still says the state and the changes stand', w.bar.line.state === 'idle' && w.bar.line.element.textContent === '' && w.bar.line.element.dataset.once === undefined && w.bar.state.dataset.state === 'warn' && w.bar.save.disabled === false );

	w.bar.set( { dirty : false } );
	w.bar.set( { dirty : true } );
	w.bar.line.fail( 'Said by the caller.' );
	timers[timers.length - 1].fn();
	check( 'what the caller says in the meantime stays where it is: a timer that finds other words takes nothing back', w.bar.line.state === 'error' && w.bar.line.element.textContent === 'Said by the caller.' );

	w.bar.line.idle();
	w.bar.set( { dirty : false } );
	w.bar.set( { dirty : true } );
	w.bar.line.dirty( 'Not named yet.' );
	timers[timers.length - 1].fn();
	check( '...and so do words of its own that the caller gave in the state of changes', w.bar.line.state === 'dirty' && w.bar.line.element.textContent === 'Not named yet.' );
}

{
	const w = build();

	w.bar.set( { dirty : true } );
	fire( w.bar.save, 'click' );
	const quick = timers[timers.length - 1];
	w.saves.shift()( true );
	quick.fn();
	check( 'a save that ended before the moment is over says "saved" and is not told "saving" after it', w.bar.line.state === 'saved' );
}

{
	const w = build();

	w.bar.set( { dirty : true } );
	fire( w.bar.save, 'click' );
	w.saves.shift()( false );
	check( 'a save that ends badly does not leave "Saving" on the line: it is back to the changes', w.bar.line.state === 'dirty' );

	fire( w.bar.save, 'click' );
	w.bar.line.error( 409, { error : 'It changed.' } );
	w.saves.shift()( false );
	check( '...and what the caller said of the failure before it ended the save stays', w.bar.line.state === 'error' );
}

{
	const w = build();

	w.bar.set( { dirty : true, blocked : '' } );
	check( 'the first change is said when the caller hands over an empty reason with every change', w.bar.line.state === 'dirty' );
	w.bar.set( { dirty : false, blocked : '' } );
	check( 'a screen that is back to what is saved has no line about changes', w.bar.line.state === 'idle' && w.bar.line.element.textContent === '' );
	w.bar.set( { dirty : true } );
	w.bar.line.fail( 'Said by the caller.' );
	w.bar.set( { dirty : false } );
	check( '...but what the caller said of something else stays', w.bar.line.state === 'error' );
}

done();
