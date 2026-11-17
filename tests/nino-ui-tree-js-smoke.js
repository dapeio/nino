/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-tree-js-smoke.js	DOM-light checks for Nino.adminUi.tree(), the tree of
 *											entries given as data: the roles and levels of the
 *											pattern, the one entry in the tab order, every key,
 *											the buttons of a row, a locked entry and the hint, and
 *											a redrawing that keeps the focus and what is open; the
 *											one drawing of every tree - icons, a loop's pattern, the
 *											field of a source, the buttons that show only where the
 *											pointer, the focus or the choice is, a chosen row with
 *											no shadow -, the line of the way, what is pointed at
 *											told and marked from outside, and hoverSync().
 *
 *	Usage: node tests/nino-ui-tree-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fills, documentOf, source } = require( './harness.js' );

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

// --- the drawing: icons, a loop, a source, the path ---------------------------------------------------------------------------

// What an icon is drawn of, to tell one from another: the attributes of its parts
function drawn( svg ) {
	return svg.children.map( function( part ) { return JSON.stringify( part.attributes ) } ).join();
}

function typeOf( tree, id ) {
	return findAll( entry( tree, id ).children[0], function( el ) { return el.className === 'nino-admin-tree-type' } )[0].children[0];
}

const iconed = adminUi.tree( { label : 'x', items : [
	{ id : 'h', kind : 'Title', name : 'Hello', icon : 'heading', meta : 'H2', actions : [ { key : 'up', icon : 'arrow-up', label : 'U', name : 'Up' }, { key : 'add', label : '+', name : 'Add' }, { key : 'odd', label : '?', name : 'Odd' }, { key : 'rm', label : '×', name : 'Remove', danger : true } ] },
	{ id : 'n', kind : 'Nothing' },
	{ id : 'u', kind : 'Unknown', icon : 'no-such-icon' },
] } );
const buttonsOf = findAll( entry( iconed, 'h' ).children[0], function( el ) { return el.tagName === 'BUTTON' } );

check( 'an entry has the icon of its type before its name, the neutral one where it names none or one the set has not',
	drawn( typeOf( iconed, 'h' ) ) === drawn( adminUi.icon( 'heading' ) ) && drawn( typeOf( iconed, 'n' ) ) === drawn( adminUi.icon( 'square' ) ) && drawn( typeOf( iconed, 'u' ) ) === drawn( adminUi.icon( 'square' ) ) );
function nameOf( tree, id ) {
	return entry( tree, id ).getAttribute('aria-labelledby').split(' ').map( function( ref ) { return findAll( entry( tree, id ), function( el ) { return el.id === ref } )[0].textContent } ).join( ' ' );
}

check( 'the name of an entry is its kind and what names it among its like - the content, or the field - and the note, the state and the hint are its description, not twice',
	nameOf( iconed, 'h' ) === 'Title Hello' && findAll( entry( iconed, 'h' ), function( el ) { return el.id === entry( iconed, 'h' ).getAttribute('aria-describedby') } )[0].textContent === 'H2'
		&& findAll( entry( iconed, 'h' ), function( el ) { return el.className === 'nino-admin-tree-name' } )[0].getAttribute('aria-hidden') === 'true' && nameOf( iconed, 'n' ) === 'Nothing' );
check( 'a button is an icon button with the icon it names, the one its glyph stands for, or - with neither - its own word; the one that destroys says so',
	drawn( buttonsOf[0].children[0] ) === drawn( adminUi.icon( 'arrow-up' ) ) && drawn( buttonsOf[1].children[0] ) === drawn( adminUi.icon( 'plus' ) )
		&& buttonsOf[2].textContent === '?' && buttonsOf[2].children.length === 0 && drawn( buttonsOf[3].children[0] ) === drawn( adminUi.icon( 'trash-2' ) )
		&& buttonsOf[3].className.indexOf( 'nino-admin-btn-danger' ) !== -1 && buttonsOf[0].className.indexOf( 'nino-admin-btn-icon' ) !== -1 );

function looped() {
	return [
		{ id : 'sec', kind : 'Section', open : true, children : [
			{ id : 'coll', kind : 'Collection', open : true, loop : { detail : 'Team - 3 elements', type : 'Member' }, children : [
				{ id : 'ttl', kind : 'Title', source : '.title' },
				{ id : 'inner', kind : 'List', open : true, loop : { type : 'Skill' }, children : [ { id : 'sk', kind : 'Text', source : { field : '.name', label : 'Target' } } ] },
			] },
		] },
	];
}

const loops = adminUi.tree( { label : 'x', items : looped() } );
const pattern = findAll( entry( loops, 'coll' ), function( el ) { return el.getAttribute('role') === 'group' } )[0];
const tags = findAll( loops.el, function( el ) { return el.className === 'nino-admin-tree-each-tag' } );

check( 'a loop is drawn with the loop icon, its note, and its children as the pattern: a group that says so, with a tag over the first of them that is no entry',
	drawn( typeOf( loops, 'coll' ) ) === drawn( adminUi.icon( 'repeat' ) ) && entry( loops, 'coll' ).dataset.loop === 'true' && entry( loops, 'ttl' ).dataset.loop === 'false'
		&& pattern.className.indexOf( 'nino-admin-tree-group--each' ) !== -1 && pattern.children[0].className === 'nino-admin-tree-each' && pattern.children[0].getAttribute('role') === 'presentation'
		&& pattern.children[0].children[0] === tags[0] && findAll( loops.el, function( el ) { return el.getAttribute('role') === 'treeitem' } ).length === 5
		&& findAll( entry( loops, 'coll' ), function( el ) { return el.id === entry( loops, 'coll' ).getAttribute('aria-describedby') } )[0].textContent.indexOf( 'Team - 3 elements' ) !== -1 );
check( 'the tag of a loop names the type of its elements where the loop is in the pattern of another, and not at the top',
	tags.length === 2 && tags[0].children.every( function( part ) { return part.textContent.indexOf( 'Member' ) === -1 } ) && tags[1].children[1].textContent.indexOf( 'Skill' ) !== -1 && tags[0].children[1].textContent.indexOf( 'Skill' ) === -1 );

const sources = findAll( loops.el, function( el ) { return el.className === 'nino-admin-tree-source' } );

check( 'an entry that reads a field is named by its kind and the field, and the pattern of a loop is a group with a name that says it is repeated',
	nameOf( loops, 'ttl' ) === 'Title '+ fills( '_admin/text/en_US.php' )['/_admin/common/tree/source']+ ' .title' && nameOf( loops, 'sk' ) === 'Text Target .name'
		&& pattern.getAttribute('aria-label') === fills( '_admin/text/en_US.php' )['/_admin/common/tree/each-note'] && findAll( entry( loops, 'sec' ), function( el ) { return el.getAttribute('role') === 'group' } )[0].getAttribute('aria-label') === null );

check( 'the field an entry reads is a tag with the database icon and the whole name, with a word before it where the caller gives one, and the full text as its title',
	sources.length === 2 && drawn( sources[0].children[0] ) === drawn( adminUi.icon( 'database', { size : 13 } ) ) && sources[0].children[1].textContent === '.title'
		&& sources[1].children[0].textContent === 'Target' && sources[1].children[2].textContent === '.name' && sources[1].title === 'Target .name' );

function rules( css, part ) {
	const found = [];
	css.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /([^{}]+)\{([^{}]*)\}/g, function( all, selector, body ) { if( selector.indexOf( part ) !== -1 ) found.push( { selector : selector.replace( /\s+/g, ' ' ).trim(), body : body.replace( /\s+/g, ' ' ).trim() } ) } );
	return found;
}

const css = source( '_admin/assets/style.css' );

check( 'the stylesheet never cuts the field of a source: it neither shrinks nor ends in an ellipsis, a row that is too narrow for it goes on a second line, and what is beside it gives way - the content shrinks, the kind is cut only where it is wider than the line, both with an ellipsis',
	( function() {
		const own = rules( css, '.nino-admin-tree-source' ).filter( function( rule ) { return /\.nino-admin-tree-source$/.test( rule.selector ) } );
		const factor = function( part ) { const found = rules( css, part ).map( function( rule ) { return rule.body.match( /flex: 0 ([\d.]+) auto/ ) } ).filter( Boolean )[0]; return found === undefined ? 0 : Number( found[1] ) };
		const column = rules( css, '.nino-admin-tree-text' ).filter( function( rule ) { return /\.nino-admin-tree-text$/.test( rule.selector ) } );
		const label = rules( css, '.nino-admin-tree-label' ).filter( function( rule ) { return rule.selector === ':where(.nino-admin) .nino-admin-tree-label' } );
		return own.length === 1 && /flex: 0 0 auto/.test( own[0].body ) && /overflow|text-overflow/.test( own[0].body ) === false && column.length === 1 && /flex-wrap: wrap/.test( column[0].body )
			&& rules( css, '.nino-admin-tree-source-field' ).some( function( rule ) { return /overflow-wrap: anywhere/.test( rule.body ) } ) && factor( '.nino-admin-tree-name' ) >= 1 && label.length === 1 && /flex: 0 0 auto/.test( label[0].body ) && /max-width: 100%/.test( label[0].body ) && /text-overflow: ellipsis/.test( label[0].body );
	} )() );
check( 'the line of the way is one line, which scrolls where the way is long, and not one that grows',
	( function() {
		const line = rules( css, '.nino-admin-tree-path' ).filter( function( rule ) { return /\.nino-admin-tree-path$/.test( rule.selector ) } );
		return line.length === 1 && /flex-wrap: nowrap/.test( line[0].body ) && /overflow-x: auto/.test( line[0].body );
	} )() );
check( 'the chosen row has no shadow: no rule of a chosen row of a tree sets a box-shadow, and it has a hairline of the accent colour instead',
	( function() {
		const chosen = rules( css, '.nino-admin-tree-row.is-selected' );
		return chosen.length > 0 && chosen.every( function( rule ) { return rule.body.indexOf( 'box-shadow' ) === -1 } ) && chosen.some( function( rule ) { return /border-color: var\(--editor-blue\)/.test( rule.body ) } );
	} )() );
check( 'the buttons of a row are out of sight by default and shown by the pointer - where there is one to hover with -, by the focus in the row and by the choice',
	( function() {
		const base = rules( css, '.nino-admin-tree-actions' ).filter( function( rule ) { return rule.selector === ':where(.nino-admin) .nino-admin-tree-actions' } );
		const shows = rules( css, '.nino-admin-tree-actions' ).filter( function( rule ) { return /display: inline-flex/.test( rule.body ) && /display: none/.test( rule.body ) === false } ).map( function( rule ) { return rule.selector } ).join( ' | ' );
		return base.length === 1 && /display: none/.test( base[0].body ) && /\.is-selected > \.nino-admin-tree-actions/.test( shows ) && /:focus-within > \.nino-admin-tree-actions/.test( shows )
			&& /@media \(hover: hover\) \{[^@]*\.nino-admin-tree-row:hover > \.nino-admin-tree-actions/.test( css );
	} )() );
check( 'the buttons of a row can all be reached by the keyboard from its row, and by no key from any other', buttons( entry( tree, 'wrap' ) ).length === 2 && stops( tree ).length === 1 && ( function() {
	entry( tree, 'wrap' ).focus();
	return buttons( entry( tree, 'wrap' ) ).every( function( b ) { return b.tabIndex === 0 } ) && buttons( entry( tree, 'text' ) ).every( function( b ) { return b.tabIndex === -1 } );
} )() );

// the line of the way

const walked = [];
const pathed = adminUi.tree( { label : 'x', path : true, items : looped(), onSelect : function( id ) { walked.push( id ) } } );
mount.appendChild( pathed.path );
mount.appendChild( pathed.el );

check( 'a tree asked for a path makes its line, which is not in the tree and is hidden while nothing is chosen; without the option there is none',
	pathed.path.tagName === 'NAV' && pathed.path.hidden === true && pathed.path.parentNode === mount && pathed.el.children.indexOf( pathed.path ) === -1 && adminUi.tree( { label : 'x', items : looped() } ).path === null );
pathed.select( 'sk' );
check( 'it names the kinds from the top to the chosen entry, the entries above as buttons and the chosen one as the place, a loop with its word',
	pathed.path.hidden === false && pathed.path.attributes['aria-label'] === fills( '_admin/text/en_US.php' )['/_admin/common/tree/path']
		&& findAll( pathed.path, function( el ) { return el.tagName === 'BUTTON' } ).map( function( b ) { return b.textContent } ).join() === 'Section,Collection · '+ fills( '_admin/text/en_US.php' )['/_admin/common/tree/each']+ ',List · '+ fills( '_admin/text/en_US.php' )['/_admin/common/tree/each']
		&& findAll( pathed.path, function( el ) { return el.attributes['aria-current'] === 'location' } )[0].textContent === 'Text' );
click( findAll( pathed.path, function( el ) { return el.tagName === 'BUTTON' } )[1] );
check( 'a name in the line chooses its entry, tells the caller, takes the stop and the focus into the tree, and the line follows',
	walked.join() === 'coll' && pathed.get().selected === 'coll' && focused( pathed ) === 'coll'
		&& findAll( pathed.path, function( el ) { return el.attributes['aria-current'] === 'location' } )[0].textContent.indexOf( 'Collection' ) === 0 );
pathed.select( null );
check( 'with nothing chosen the line is gone again', pathed.path.hidden === true && pathed.path.children.length === 0 );

// --- what is pointed at: told, and marked from outside ----------------------------------------------------------------------

const pointed = [];
const hover = build( { onHover : function( id ) { pointed.push( id ) } } );
mount.appendChild( hover.el );
const over = function( id ) { entry( hover, id ).children[0].dispatchEvent( { type : 'pointerover', bubbles : true } ) };

over( 'text' );
over( 'text' );
over( 'title' );
hover.el.dispatchEvent( { type : 'pointerleave' } );
check( 'the pointer over an entry is told with its id, once for each change, and null where it leaves them all', pointed.join() === 'text,title,' );

pointed.length = 0;
entry( hover, 'space' ).focus();
over( 'text' );
hover.el.dispatchEvent( { type : 'pointerleave' } );
check( 'the focus in an entry is told the same way, and what moved last is what is pointed at: when the pointer has gone nothing is, the focus that stands in the tree does not speak for it', pointed.join() === 'space,text,' );

// A page takes the focus from a row that is taken away, and says so: the tree that is drawn again is not news for the one that is told what is pointed at
const takeAway = hover.el.removeChild;
hover.el.removeChild = function( child ) {
	hover.el.dispatchEvent( { type : 'focusout', relatedTarget : null } );
	return takeAway.call( hover.el, child );
};
pointed.length = 0;
hover.update( data() );
hover.el.removeChild = takeAway;
check( 'a redrawing that leaves the pointer and the focus where they were tells nobody, though the page took the focus from the rows it replaced, and neither does a call from outside',
	pointed.length === 0 && ( hover.highlight( 'links' ), hover.select( 'title' ), pointed.length === 0 ) );

scrolled.length = 0;
const keptFocus = focused( hover );
hover.highlight( [ 'title', 'foot' ] );
const lit = function( tree ) { return entries( tree ).filter( function( el ) { return el.children[0].className.split( ' ' ).indexOf( 'is-lit' ) !== -1 } ).map( function( el ) { return el.dataset.id } ).join(); };
check( 'highlight() marks the entries it is given, and only those, moves neither the focus nor the page, and answers whether one is there',
	lit( hover ) === 'title,foot' && focused( hover ) === keptFocus && scrolled.length === 0 && hover.highlight( 'nothing' ) === false && lit( hover ) === '' && hover.highlight( 'text' ) === true );
hover.highlight( null );
const closedBefore = entry( hover, 'b' ).getAttribute( 'aria-expanded' );
hover.highlight( 'button', { scroll : true } );
check( 'with scroll it opens what is above the first one and rolls it into view, and null takes every mark away',
	closedBefore === 'false' && entry( hover, 'b' ).getAttribute( 'aria-expanded' ) === 'true' && scrolled.length === 1 && lit( hover ) === 'button' && ( hover.highlight( null ), lit( hover ) === '' ) );
hover.highlight( 'text' );
hover.update( data() );
check( 'a redrawing keeps what is marked from outside', lit( hover ) === 'text' );

// --- the keyboard reaches the buttons of the row that has the stop ------------------------------------------------------------------

const rowOf = function( tree, id ) { return entry( tree, id ).children[0] };
const classesOf = function( el ) { return el.className.split( ' ' ) };
const stopRows = function( tree ) { return entries( tree ).filter( function( el ) { return classesOf( rowOf( tree, el.dataset.id ) ).indexOf( 'is-stop' ) !== -1 } ).map( function( el ) { return el.dataset.id } ).join(); };

const keyed = build();
mount.appendChild( keyed.el );

check( 'the row that has the stop carries the class the stylesheet shows its buttons by, and it moves with the stop',
	stopRows( keyed ) === 'title' && ( press( entry( keyed, 'title' ), 'ArrowDown' ), stopRows( keyed ) === 'text' ) && ( keyed.focus( 'wrap' ), stopRows( keyed ) === 'wrap' ) );

const outside = element( 'button' );
entry( keyed, 'wrap' ).focus();
check( 'the tree knows the focus is in it while it is on a row or on a button of one - Tab from the row to its button does not leave it - and not once it has gone out',
	classesOf( keyed.el ).indexOf( 'is-inside' ) !== -1
		&& ( keyed.el.dispatchEvent( { type : 'focusout', relatedTarget : buttons( entry( keyed, 'wrap' ) )[0] } ), classesOf( keyed.el ).indexOf( 'is-inside' ) !== -1 )
		&& ( keyed.el.dispatchEvent( { type : 'focusout', relatedTarget : outside } ), classesOf( keyed.el ).indexOf( 'is-inside' ) === -1 )
		&& ( entry( keyed, 'foot' ).focus(), classesOf( keyed.el ).indexOf( 'is-inside' ) !== -1 ) );
check( 'a redrawing, which takes the focus from the rows for a moment, does not take it from the tree - not for a moment either: the button the focus goes back to must be shown when it is asked for it',
	( function() {
		const before = keyed.el.removeChild;
		const seen = [];
		keyed.el.removeChild = function( child ) { keyed.el.dispatchEvent( { type : 'focusout', relatedTarget : null } ); seen.push( classesOf( keyed.el ).indexOf( 'is-inside' ) !== -1 ); return before.call( keyed.el, child ) };
		keyed.update( data() );
		keyed.el.removeChild = before;
		return seen.length > 0 && seen.every( function( inside ) { return inside } ) && classesOf( keyed.el ).indexOf( 'is-inside' ) !== -1 && stopRows( keyed ) === 'foot';
	} )() );

check( 'the stylesheet shows the buttons of the row that has the stop while the focus is in the tree, and no rule makes them depend on :focus-visible, which the first Tab takes away',
	( function() {
		const own = rules( css, '.nino-admin-tree-actions' );
		return own.some( function( rule ) { return /\.nino-admin-tree\.is-inside \.nino-admin-tree-row\.is-stop > \.nino-admin-tree-actions/.test( rule.selector ) && /display: inline-flex/.test( rule.body ) } )
			&& own.every( function( rule ) { return rule.selector.indexOf( 'focus-visible' ) === -1 } );
	} )() );

// the focus after an action: the same button of the moved row, the next row of one that went, the new row

function flowItems( list ) {
	const buttonsOf = [ { key : 'up', icon : 'arrow-up', name : 'Up' }, { key : 'down', icon : 'arrow-down', name : 'Down' }, { key : 'remove', icon : 'trash-2', name : 'Remove', danger : true } ];
	return list.map( function( id ) { return id === 'g' ? { id : 'g', label : 'G', open : true, actions : [ { key : 'add', icon : 'plus', name : 'Add' } ], children : [ { id : 'k', label : 'K', actions : buttonsOf } ] } : { id : id, label : id.toUpperCase(), actions : buttonsOf } } );
}

let order = [ 'p', 'q', 'r', 'g' ];
const kids = [];
const flow = adminUi.tree( { label : 'x', items : flowItems( order ), onAction : function( key, id ) {

	if( key === 'down' )
		order.splice( order.indexOf( id ), 2, order[order.indexOf( id ) + 1], id );
	else if( key === 'remove' )
		order.splice( order.indexOf( id ), 1 );
	else if( key === 'add' )
		kids.push( 'n'+ kids.length );

	const items = flowItems( order );
	items[items.length - 1].children = items[items.length - 1].children.concat( kids.map( function( id ) { return { id : id, label : id } } ) );
	flow.update( items );
} } );
mount.appendChild( flow.el );

const press2 = function( id, index ) { const button = buttons( entry( flow, id ) )[index]; button.focus(); click( button ) };

entry( flow, 'q' ).focus();
press2( 'p', 1 );
check( 'after a move the focus is on the same button of the row that was moved, where it is not chosen', order.join() === 'q,p,r,g' && buttons( entry( flow, 'p' ) )[1].focused === true && classesOf( flow.el ).indexOf( 'is-inside' ) !== -1 );
press2( 'p', 2 );
check( 'after a removal the focus is on the next row, else the one before, else the parent', order.join() === 'q,r,g' && focused( flow ) === 'r' );
press2( 'r', 2 );
press2( 'q', 2 );
check( 'and where the last one of a level goes, on the one before it', order.join() === 'g' && focused( flow ) === 'g' );
press2( 'g', 0 );
const withLate = function( there ) { return flowItems( order ).map( function( item ) { return Object.assign( item, { children : item.children.concat( [ { id : 'n0', label : 'n0' } ], there === true ? [ { id : 'late', label : 'late' } ] : [] ) } ) } ) };
check( 'a new row is there after the redrawing the action made: the focus is on it where the caller asks for it',
	order.join() === 'g' && focused( flow ) === 'n0' || ( flow.focus( 'n0' ) === true && focused( flow ) === 'n0' ) );
check( 'an entry that is not there yet is asked the focus for before it is drawn, and has it once it is - and the ask is not kept for a later redrawing',
	flow.focus( 'late' ) === false && ( flow.update( withLate( true ) ), focused( flow ) === 'late' )
		&& ( flow.focus( 'gone' ), flow.update( withLate( true ) ), entry( flow, 'g' ).focus(), flow.update( withLate( true ) ), focused( flow ) === 'g' ) );
check( 'focus( id, key ) goes to the button of the row with that key, and to the row where the button is not there or is not to be pressed',
	( flow.update( flowItems( [ 'p', 'g' ] ) ), flow.focus( 'p', 'down' ) === true && buttons( entry( flow, 'p' ) )[1].focused === true && flow.focus( 'p', 'nothing' ) === true && focused( flow ) === 'p' ) );

// --- the switchboard ---------------------------------------------------------------------------------------------------------

const board = adminUi.hoverSync();
const heard = { a : [], b : [], c : [] };
const memberA = board.join( function( key ) { heard.a.push( key ) } );
const memberB = board.join( function( key ) { heard.b.push( key ) } );
board.join( function( key ) { heard.c.push( key ) } );

memberA.set( 'x' );
memberA.set( 'x' );
memberB.set( [ 'x', 'y' ] );
memberB.set( [ 'x', 'y' ] );
memberA.set( null );
check( 'what one member reports every other is told, once, and the reporter never; a key reported again, or a list of the same keys, tells nobody - and a member that holds no key cannot take away the one another holds',
	heard.a.join( '|' ) === 'x,y' && heard.b.join( '|' ) === 'x' && heard.c.join( '|' ) === 'x|x,y' && board.get().join() === 'x,y' );
memberB.set( null );
memberB.set( 'z' );
memberB.leave();
check( 'the member that holds the key takes it away with null or by leaving, and is told nothing after', board.get() === null && heard.b.length === 1 && heard.a.join( '|' ) === 'x,y||z|' && ( memberB.set( 'q' ), board.get() === null ) );

// the tree and two views through one board

const wired = adminUi.hoverSync();
const other = [];
const treeSide = build();
mount.appendChild( treeSide.el );
const asMember = wired.join( function( key ) { treeSide.highlight( key ) } );
treeSide.update( data() );
wired.join( function( key ) { other.push( key ) } ).set( 'text' );
check( 'a tree and another view through one switchboard mark one another: what the tree highlights is what the other reported', lit( treeSide ) === 'text' && other.length === 0 );
asMember.set( 'title' );
check( 'and what the tree reports - the hook of onHover - reaches the other and is not marked back on the tree', other.join() === 'title' && lit( treeSide ) === 'text' );

done();
