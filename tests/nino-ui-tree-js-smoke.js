/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-tree-js-smoke.js	DOM-light checks for Nino.adminUi.tree(), the tree of
 *											entries given as data: the roles and levels of the
 *											pattern, the one entry in the tab order, every key,
 *											the buttons of a row, a locked entry and the hint, and
 *											a redrawing that keeps the focus and what is open.
 *
 *	Usage: node tests/nino-ui-tree-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fills, documentOf } = require( './harness.js' );

const mount = element('div');
const doc = documentOf( mount );
const scrolled = [];
let lastFocus = null;

// A page moves the focus on focus() and says so to the tree; the stand-in does not, and has no scrollIntoView
const made = doc.createElement;
doc.createElement = function( tag ) {
	const el = made( tag );
	const focus = el.focus;
	el.focus = function( options ) {
		focus();
		lastFocus = options ?? null;
		doc.activeElement = el;
		el.dispatchEvent( { type : 'focusin', bubbles : true } );
	};
	el.scrollIntoView = function() { scrolled.push( el ) };
	return el;
};

const sandbox = { console : console, document : doc };
sandbox.window = sandbox;
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

const told = [];

function data() {
	return [
		{ id : 'wrap', label : 'Wrap', detail : 'Section', open : true, actions : [ { key : 'add', label : '+', name : 'Add inside Wrap' }, { key : 'down', label : '↓', name : 'Move Wrap down', disabled : true } ], children : [
			{ id : 'row', label : 'Row', open : true, children : [
				{ id : 'a', label : 'Column A', open : true, children : [
					{ id : 'title', label : 'Title', detail : 'Heading' },
					{ id : 'text', label : 'Text', marked : true, actions : [ { key : 'remove', label : '×', name : 'Remove Text' } ] },
				] },
				{ id : 'b', label : 'Column B', children : [
					{ id : 'image', label : 'Image' },
					{ id : 'button', label : 'Button' },
				] },
			] },
			{ id : 'free', label : 'Free <b>HTML</b>', locked : true, hint : { text : 'Not editable here', state : 'muted' } },
			{ id : 'space', label : 'Spacer' },
		] },
		{ id : 'foot', label : 'Footer', children : [ { id : 'links', label : 'Links' } ] },
	];
}

function build( options ) {
	return adminUi.tree( Object.assign( {
		label : 'Components', items : data(), selected : 'title',
		onSelect : function( id ) { told.push( 'select '+ id ) },
		onToggle : function( id, on ) { told.push( ( on ? 'open ' : 'close ' )+ id ) },
		onAction : function( key, id ) { told.push( key+ ' '+ id ) },
	}, options || {} ) );
}

function entries( tree ) {
	return findAll( tree.el, function( el ) { return el.getAttribute('role') === 'treeitem' } );
}

function entry( tree, id ) {
	return entries( tree ).find( function( el ) { return el.dataset.id === id } );
}

function stops( tree ) {
	return entries( tree ).filter( function( el ) { return el.tabIndex === 0 } ).map( function( el ) { return el.dataset.id } );
}

function press( el, key ) {
	const event = { prevented : false };
	el.dispatchEvent( { type : 'keydown', key : key, bubbles : true, preventDefault : function() { event.prevented = true } } );
	return event.prevented;
}

function click( el ) {
	el.dispatchEvent( { type : 'click', bubbles : true } );
}

function focused( tree ) {
	return entries( tree ).filter( function( el ) { return el.focused === true } ).map( function( el ) { return el.dataset.id } ).join();
}

// The buttons of the row of an entry, not of the entries under it
function buttons( li ) {
	return findAll( li.children[0], function( el ) { return el.tagName === 'BUTTON' } );
}

console.log( 'Nino.adminUi.tree - entries as a tree\n' );

// --- the pattern ----------------------------------------------------------------------

const tree = build();
mount.appendChild( tree.el );
const all = entries( tree );

check( 'the tree has the role and a name, and its parents hold their children in a group',
	tree.el.getAttribute('role') === 'tree' && tree.el.getAttribute('aria-label') === 'Components'
		&& findAll( tree.el, function( el ) { return el.getAttribute('role') === 'group' } ).length === 5 );
check( 'every entry is a treeitem with its level, and the levels are the data\'s',
	all.map( function( el ) { return el.dataset.id+ ':'+ el.getAttribute('aria-level') } ).join() === 'wrap:1,row:2,a:3,title:4,text:4,b:3,image:4,button:4,free:2,space:2,foot:1,links:2' );
check( 'aria-expanded is on the entries that have children only, and says what is open',
	all.map( function( el ) { return el.getAttribute('aria-expanded') ?? '-' } ).join() === 'true,true,true,-,-,false,-,-,-,-,false,-' );
check( 'aria-selected is true on the chosen entry and false on every other',
	all.filter( function( el ) { return el.getAttribute('aria-selected') === 'true' } ).map( function( el ) { return el.dataset.id } ).join() === 'title' && all.every( function( el ) { return el.getAttribute('aria-selected') !== null } ) );
check( 'a closed group is hidden, an open one is not', findAll( entry( tree, 'b' ), function( el ) { return el.getAttribute('role') === 'group' } )[0].hidden === true && findAll( entry( tree, 'a' ), function( el ) { return el.getAttribute('role') === 'group' } )[0].hidden === false );
check( 'the name of an entry is its label alone, and a label is text, not markup',
	entry( tree, 'free' ).getAttribute('aria-labelledby') !== null && findAll( entry( tree, 'free' ), function( el ) { return el.id === entry( tree, 'free' ).getAttribute('aria-labelledby') } )[0].textContent === 'Free <b>HTML</b>'
		&& findAll( entry( tree, 'free' ), function( el ) { return el.tagName === 'B' } ).length === 0 );

// --- one tab stop ------------------------------------------------------------------------

check( 'one entry is in the tab order, the chosen one', stops( tree ).join() === 'title' );
check( 'the buttons of a row are in the tab order only in the row that has the stop',
	buttons( entry( tree, 'wrap' ) ).every( function( b ) { return b.tabIndex === -1 } ) && buttons( entry( tree, 'text' ) ).every( function( b ) { return b.tabIndex === -1 } ) );
check( 'a tree with nothing chosen has the first entry as its stop', stops( build( { selected : undefined } ) ).join() === 'wrap' );

// --- the keys -----------------------------------------------------------------------------

entry( tree, 'title' ).focus();
check( 'Down walks to the next entry that can be seen, moves the stop with the focus and is kept from scrolling',
	press( entry( tree, 'title' ), 'ArrowDown' ) === true && focused( tree ) === 'text' && stops( tree ).join() === 'text' );
press( entry( tree, 'text' ), 'ArrowDown' );
press( entry( tree, 'b' ), 'ArrowDown' );
check( 'Down passes what a closed entry hides', focused( tree ) === 'free' );
press( entry( tree, 'free' ), 'ArrowUp' );
check( 'Up goes back the same way', focused( tree ) === 'b' );

press( entry( tree, 'b' ), 'ArrowRight' );
check( 'Right opens a closed entry, tells the caller and keeps the focus', entry( tree, 'b' ).getAttribute('aria-expanded') === 'true' && focused( tree ) === 'b' && told.join() === 'open b' );
press( entry( tree, 'b' ), 'ArrowRight' );
check( 'Right on an open entry goes to its first child', focused( tree ) === 'image' );
press( entry( tree, 'image' ), 'ArrowRight' );
check( 'Right on an entry with no children does nothing', focused( tree ) === 'image' && told.length === 1 );
press( entry( tree, 'image' ), 'ArrowLeft' );
check( 'Left on an entry that is not open goes to the parent', focused( tree ) === 'b' );
press( entry( tree, 'b' ), 'ArrowLeft' );
check( 'Left on an open entry closes it and tells the caller', entry( tree, 'b' ).getAttribute('aria-expanded') === 'false' && told.join() === 'open b,close b' );

press( entry( tree, 'b' ), 'Home' );
check( 'Home goes to the first entry', focused( tree ) === 'wrap' );
press( entry( tree, 'wrap' ), 'End' );
check( 'End goes to the last entry that can be seen', focused( tree ) === 'foot' );

told.length = 0;
press( entry( tree, 'foot' ), 'Enter' );
press( entry( tree, 'foot' ), ' ' );
check( 'Enter and Space choose - the entry that is chosen already as well - and tell the caller each time',
	told.join() === 'select foot,select foot' && entry( tree, 'foot' ).getAttribute('aria-selected') === 'true' && entry( tree, 'title' ).getAttribute('aria-selected') === 'false' );

press( entry( tree, 'foot' ), 'f' );
check( 'a letter goes to the next entry that begins with it, wrapping', focused( tree ) === 'free' );
press( entry( tree, 'free' ), 'f' );
check( 'and on, round to the first', focused( tree ) === 'foot' );
check( 'a letter that begins no entry does nothing, and another key is left to the browser',
	press( entry( tree, 'foot' ), 'q' ) === false && press( entry( tree, 'foot' ), 'Tab' ) === false && focused( tree ) === 'foot' );
check( 'a key with Ctrl is not the tree\'s', ( entry( tree, 'foot' ).dispatchEvent( { type : 'keydown', key : 'ArrowUp', ctrlKey : true, bubbles : true } ), focused( tree ) === 'foot' ) );

// --- the mouse ------------------------------------------------------------------------------

told.length = 0;
click( findAll( entry( tree, 'foot' ), function( el ) { return el.className === 'nino-admin-tree-toggle' } )[0] );
check( 'a click on the arrow opens the entry and does not choose it', entry( tree, 'foot' ).getAttribute('aria-expanded') === 'true' && told.join() === 'open foot' );
click( entry( tree, 'links' ).children[0] );
check( 'a click on the row chooses it and takes the stop', told.join() === 'open foot,select links' && stops( tree ).join() === 'links' && entry( tree, 'links' ).focused === true );

// --- the buttons of a row ---------------------------------------------------------------------

told.length = 0;
const add = buttons( entry( tree, 'wrap' ) )[0];
check( 'a button has a name a screen reader says, and a disabled one says so',
	add.getAttribute('aria-label') === 'Add inside Wrap' && buttons( entry( tree, 'wrap' ) )[1].disabled === true );
add.focus();
click( add );
check( 'a press tells the caller the key and the entry, and does not choose the row', told.join() === 'add wrap' && entry( tree, 'wrap' ).getAttribute('aria-selected') === 'false' );
check( 'the buttons of the row that was reached are in the tab order and the other row\'s are not', buttons( entry( tree, 'wrap' ) ).every( function( b ) { return b.tabIndex === 0 } ) && buttons( entry( tree, 'text' ) ).every( function( b ) { return b.tabIndex === -1 } ) && stops( tree ).join() === 'wrap' );

// --- a locked entry and a hint -------------------------------------------------------------------

told.length = 0;
press( entry( tree, 'free' ), 'Enter' );
click( entry( tree, 'free' ).children[0] );
check( 'a locked entry says so and cannot be chosen, by key or by click, and carries no aria-disabled, which would reach its buttons', entry( tree, 'free' ).dataset.locked === 'true' && told.length === 0 && entry( tree, 'free' ).getAttribute('aria-selected') === 'false' && entry( tree, 'free' ).getAttribute('aria-disabled') === null && tree.select( 'free' ) === false );
check( 'the hint and the word for a locked entry are read as its description, and the visible copies are for the eye',
	findAll( entry( tree, 'free' ), function( el ) { return el.id === entry( tree, 'free' ).getAttribute('aria-describedby') } )[0].textContent === fills( '_admin/text/en_US.php' )['/_admin/common/tree/locked']+ ', Not editable here'
		&& findAll( entry( tree, 'free' ), function( el ) { return el.className === 'nino-admin-tree-hint' } )[0].getAttribute('aria-hidden') === 'true' );
check( 'the state of an entry is a word on the row and the hint a sentence under the label, both for the eye alone',
	findAll( entry( tree, 'free' ).children[0], function( el ) { return el.className === 'nino-admin-tree-state' } )[0].textContent === fills( '_admin/text/en_US.php' )['/_admin/common/tree/locked']
		&& findAll( entry( tree, 'free' ), function( el ) { return el.className === 'nino-admin-tree-hint-text' } )[0].textContent === 'Not editable here' );
check( 'a marked entry says so in words as well', findAll( entry( tree, 'text' ), function( el ) { return el.id === entry( tree, 'text' ).getAttribute('aria-describedby') } )[0].textContent === fills( '_admin/text/en_US.php' )['/_admin/common/tree/marked'] );
check( 'the words are fills in both languages', [ '_admin/text/en_US.php', '_admin/text/de_DE.php' ].every( function( file ) { return fills( file )['/_admin/common/tree/locked'] !== undefined && fills( file )['/_admin/common/tree/marked'] !== undefined } ) );

// --- a redrawing --------------------------------------------------------------------------------

const opened = data();
opened[0].children[0].children[1].open = true;
const second = build( { items : opened } );
mount.appendChild( second.el );
press( entry( second, 'b' ), 'ArrowLeft' );
press( entry( second, 'foot' ), 'ArrowRight' );
const before = entry( second, 'b' );
const button = buttons( entry( second, 'text' ) )[0];
button.focus();
told.length = 0;

const next = data();
next[0].children[0].children[1].open = true;
next[0].children[0].children.push( { id : 'c', label : 'Column C', open : true, children : [ { id : 'deep', label : 'Deep' } ] } );
second.update( next );

check( 'a redrawing makes new elements, and tells nobody', entry( second, 'b' ) !== before && told.length === 0 );
check( 'an id it has seen keeps what the hand did, closed or open, and a new id takes what its item says',
	entry( second, 'b' ).getAttribute('aria-expanded') === 'false' && entry( second, 'foot' ).getAttribute('aria-expanded') === 'true' && entry( second, 'c' ).getAttribute('aria-expanded') === 'true' );
check( 'the focus stays on the button it was on, and the stop with it', buttons( entry( second, 'text' ) )[0].focused === true && stops( second ).join() === 'text' && buttons( entry( second, 'text' ) )[0].tabIndex === 0 );
check( 'the chosen entry stays chosen', entry( second, 'title' ).getAttribute('aria-selected') === 'true' );

second.update( [ { id : 'wrap', label : 'Wrap', children : [ { id : 'space', label : 'Spacer' } ] } ] );
check( 'an entry that is gone is not chosen, and the stop goes to one that is there', second.get().selected === null && stops( second ).join() === 'wrap' && focused( second ) === 'wrap' );

// --- from outside ----------------------------------------------------------------------------------

const outer = build( { items : data().map( function( item ) { return Object.assign( item, { open : false } ) } ), selected : undefined } );
mount.appendChild( outer.el );
told.length = 0;
scrolled.length = 0;
check( 'select() opens what is above the entry, chooses it, scrolls it into view and tells nobody',
	outer.select( 'title' ) === true && entry( outer, 'wrap' ).getAttribute('aria-expanded') === 'true' && entry( outer, 'a' ).getAttribute('aria-expanded') === 'true'
		&& entry( outer, 'title' ).getAttribute('aria-selected') === 'true' && stops( outer ).join() === 'title' && scrolled.length === 1 && told.length === 0 );
check( 'an id the tree has not is refused', outer.select( 'nothing' ) === false && outer.focus( 'nothing' ) === false && outer.get().selected === 'title' );
check( 'focus() puts the focus on the entry, the stop by default', outer.focus() === true && focused( outer ) === 'title' && outer.focus( 'space' ) === true && focused( outer ) === 'space' );
press( entry( outer, 'space' ), 'ArrowUp' );
press( entry( outer, 'free' ), 'ArrowUp' );
press( entry( outer, 'b' ), 'ArrowUp' );
check( 'where an entry is closed with the focus below it the stop goes up to it', ( function() {
	click( findAll( entry( outer, 'wrap' ), function( el ) { return el.className === 'nino-admin-tree-toggle' } )[0] );
	return focused( outer ) === 'wrap' && stops( outer ).join() === 'wrap' && entry( outer, 'wrap' ).getAttribute('aria-expanded') === 'false';
} )() );
check( 'get() is the chosen entry, the stop and the open entries', outer.get().selected === 'title' && outer.get().stop === 'wrap' && outer.get().open.slice().sort().join() === 'a,row' );

// --- the pattern's counts, the letters, Space ----------------------------------------------------------

check( 'an entry says how many there are at its level and which one it is', entry( tree, 'image' ) !== undefined && entry( tree, 'space' ).getAttribute('aria-setsize') === '3' && entry( tree, 'space' ).getAttribute('aria-posinset') === '3' );
entry( tree, 'wrap' ).focus();
check( 'Space is kept from scrolling the page', press( entry( tree, 'wrap' ), ' ' ) === true );
press( entry( tree, 'wrap' ), 'p' );
check( 'a letter finds only a label that begins with it, not one that has it inside', focused( tree ) === 'wrap' );
check( 'an id with a space is a token for the ids made of it', adminUi.tree( { id : 'a b', label : 'x', items : data() } ).el.id === 'a-b' );
check( 'a button can be drawn as one that destroys', ( function() {
	const t = adminUi.tree( { label : 'x', items : [ { id : 'q', label : 'Q', actions : [ { key : 'x', label : '×', name : 'Remove', danger : true }, { key : 'y', label : '+', name : 'Add' } ] } ] } );
	return buttons( entry( t, 'q' ) ).map( function( b ) { return b.className.indexOf('nino-admin-tree-action--danger') !== -1 } ).join() === 'true,false';
} )() );

// --- the focus where the walk and the redrawing take it ----------------------------------------------------

scrolled.length = 0;
entry( tree, 'wrap' ).focus();
press( entry( tree, 'wrap' ), 'ArrowDown' );
check( 'a key that moves the focus scrolls the row, not the entry, into view and keeps the focus from scrolling by itself',
	scrolled.length === 1 && scrolled[0] === entry( tree, 'row' ).children[0] && lastFocus !== null && lastFocus.preventScroll === true );

function small() {
	return [
		{ id : 'p', label : 'P', actions : [ { key : 'up', label : '↑', name : 'Up' }, { key : 'down', label : '↓', name : 'Down' } ] },
		{ id : 'q', label : 'Q' },
		{ id : 'r', label : 'R' },
		{ id : 'g', label : 'G', open : true, children : [ { id : 'k', label : 'K' } ] },
	];
}

const moved = [];
const third = adminUi.tree( { label : 'x', items : small(), onFocus : function( id ) { moved.push( id ) } } );
mount.appendChild( third.el );
entry( third, 'p' ).focus();
buttons( entry( third, 'p' ) )[0].focus();
let edited = small();
edited[0].actions[0].disabled = true;
third.update( edited );
check( 'where the button the focus stood on cannot be pressed the focus goes to its row', focused( third ) === 'p' && stops( third ).join() === 'p' );

entry( third, 'q' ).focus();
moved.length = 0;
edited = small().filter( function( item ) { return item.id !== 'q' } );
third.update( edited );
check( 'where the focused entry is gone the focus goes to its next sibling, and the caller is told', focused( third ) === 'r' && moved.join() === 'r' );
entry( third, 'r' ).focus();
third.update( [ { id : 'p', label : 'P' }, { id : 'q', label : 'Q' } ] );
check( 'with no next sibling it goes to the previous one', focused( third ) === 'p' );
third.update( small().slice( 3 ) );
entry( third, 'k' ).focus();
third.update( [ { id : 'g', label : 'G' } ] );
check( 'with no sibling at all it goes to the parent', focused( third ) === 'g' && stops( third ).join() === 'g' );

done();
