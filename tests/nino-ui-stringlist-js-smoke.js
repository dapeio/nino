/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-stringlist-js-smoke.js	DOM-light checks for
 *											Nino.adminUi.stringList(), the control an element
 *											field of type array uses while its value is a plain
 *											list of texts - rows to type into, move and remove,
 *											instead of a JSON field.
 *
 *											Its value is a hidden input that carries the JSON,
 *											the way elementList()'s does, so the form reads,
 *											compares and saves it like every other field: that
 *											is what is pinned here - what the input holds at the
 *											start and after every change, that the rows carry no
 *											data-field of their own, and that the control owns
 *											no words.
 *
 *	Usage: node tests/nino-ui-stringlist-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let checks = 0;
let failures = 0;

function check( label, condition ) {
	checks++;
	if( condition === true ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

function classList( el ) {
	const values = new Set();
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
		toggle : function( value, force ) {
			if( force === true ) values.add( value );
			else if( force === false ) values.delete( value );
			else if( values.has( value ) ) values.delete( value );
			else values.add( value );
			return values.has( value );
		},
	};
}

// The control that has the focus, as the last focus() left it
let focused = null;

function element( tag ) {
	const el = {
		tagName : String( tag ).toUpperCase(),
		className : '',
		textContent : '',
		value : '',
		type : '',
		placeholder : '',
		title : '',
		disabled : false,
		dataset : {},
		attributes : {},
		children : [],
		listeners : {},
		appendChild : function( child ) { el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		focus : function() { focused = el },
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
	};
	el.classList = classList( el );
	// The control empties both lists by assigning innerHTML before redrawing
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

function fire( el, name ) {
	( el.listeners[name] || [] ).forEach( function( fn ) { fn() } );
}

/**
 *	Every descendant matching a predicate, in document order
 */
function findAll( root, predicate ) {
	const found = [];
	( function walk( node ) {
		( node.children || [] ).forEach( function( child ) {
			if( predicate( child ) === true )
				found.push( child );
			walk( child );
		} );
	} )( root );
	return found;
}

function byClass( root, name ) {
	return findAll( root, function( el ) { return String( el.className ).split(' ').indexOf( name ) !== -1 } );
}

const sandbox = {
	console : console,
	document : { createElement : element, documentElement : null, body : null },
};
sandbox.window = sandbox;
sandbox.Nino = {};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'Nino.admin.js' }
);

const adminUi = sandbox.Nino.adminUi;

const TEXT = { add : 'Add', remove : 'Remove', up : 'Up', down : 'Down', empty : 'No entries', item : 'Tags, entry %d' };

function build( options ) {
	return adminUi.stringList( Object.assign( { key : 'tags', label : 'Tags', value : [ 'a', 'b', 'c' ], text : TEXT }, options || {} ) );
}

function store( field ) {
	return findAll( field, function( el ) { return el.type === 'hidden' } )[0];
}

function stored( field ) {
	return store( field ).value;
}

function rows( field ) {
	return byClass( field, 'nino-admin-stringlist-rows' )[0].children;
}

function rowInput( field, index ) {
	return rows( field )[index].children[0];
}

function rowButtons( field, index ) {
	return rows( field )[index].children[1].children;
}

function addButton( field ) {
	return byClass( field, 'nino-admin-stringlist-add' )[0];
}

function type( input, text ) {
	input.value = text;
	fire( input, 'input' );
}

console.log( 'Nino.adminUi.stringList - a list of texts, as rows\n' );

// --- the value the form reads back ---------------------------------------

const seeded = build();
check( 'the value lives in a hidden input carrying data-field, typed as an array', store( seeded ).dataset.field === 'tags' && store( seeded ).dataset.type === 'array' );
check( 'it starts as exactly the json the value is stored as', stored( seeded ) === '["a","b","c"]' );
check( '...also for a value with characters json writes its own way', stored( build( { value : [ 'Zoë "Z"', 'a/b', '€' ] } ) ) === JSON.stringify( [ 'Zoë "Z"', 'a/b', '€' ] ) );
check( 'an absent value is an empty list, and says so rather than showing a blank box', stored( build( { value : undefined } ) ) === '[]' && rows( build( { value : undefined } ) )[0].textContent === 'No entries' );
check( 'one row per text, in the order it was stored', rows( seeded ).length === 3 && [ 0, 1, 2 ].every( function( i ) { return rowInput( seeded, i ).value === [ 'a', 'b', 'c' ][i] } ) );

// The form takes the first element under a key as the field (see
// _readFieldByKey()): a row input carrying it would be read instead of the list
check( 'the rows\' own inputs carry no data-field', findAll( seeded, function( el ) { return el.dataset !== undefined && el.dataset.field !== undefined } ).length === 1 );
check( 'every row is a named text input', [ 0, 1, 2 ].every( function( i ) { return rowInput( seeded, i ).type === 'text' && rowInput( seeded, i ).attributes['aria-label'] === 'Tags, entry '+ ( i + 1 ) } ) );
check( 'a row is named by the field when the caller gives no better word', rowInput( build( { text : { add : 'Add' } } ), 0 ).attributes['aria-label'] === 'Tags' );

// --- editing --------------------------------------------------------------

const editing = build();
type( rowInput( editing, 1 ), 'beta' );
check( 'typing in a row rewrites the value', stored( editing ) === '["a","beta","c"]' );
type( rowInput( editing, 0 ), '' );
check( 'a row that is empty is not an entry, and is left out of the value', stored( editing ) === '["beta","c"]' );
type( rowInput( editing, 0 ), '   ' );
check( '...white space alone is empty too', stored( editing ) === '["beta","c"]' );
type( rowInput( editing, 0 ), 'a' );
check( '...and the entry is back when something is typed', stored( editing ) === '["a","beta","c"]' );

// --- adding, moving, removing ---------------------------------------------

const adding = build( { value : [ 'a' ] } );
fire( addButton( adding ), 'click' );
check( 'the add button adds a row, which is not an entry until it holds a text', rows( adding ).length === 2 && stored( adding ) === '["a"]' );
type( rowInput( adding, 1 ), 'b' );
check( '...a text typed into it is one', stored( adding ) === '["a","b"]' );
check( 'the button says what it does through the caller\'s word', addButton( adding ).textContent === 'Add' );

const ordered = build();
check( 'the first row cannot move up, the last cannot move down', rowButtons( ordered, 0 )[0].disabled === true && rowButtons( ordered, 2 )[1].disabled === true );
fire( rowButtons( ordered, 1 )[0], 'click' );
check( 'up swaps a row with the one above it', stored( ordered ) === '["b","a","c"]' );
fire( rowButtons( ordered, 0 )[1], 'click' );
check( 'down swaps it back', stored( ordered ) === '["a","b","c"]' );
fire( rowButtons( ordered, 1 )[2], 'click' );
check( 'remove drops exactly its own row', stored( ordered ) === '["a","c"]' && rows( ordered ).length === 2 );
check( 'every button is named, not just drawn', rowButtons( ordered, 0 ).every( function( button ) { return button.attributes['aria-label'] !== undefined && button.attributes['aria-label'] !== '' } ) );

const emptied = build( { value : [ 'only' ] } );
fire( rowButtons( emptied, 0 )[2], 'click' );
check( 'the last row removed leaves an empty list, and the empty word', stored( emptied ) === '[]' && rows( emptied )[0].textContent === 'No entries' );

// --- the keyboard stays where it was ----------------------------------------
//
// Moving or removing a row draws the list again, which takes away the button
// that was pressed: the focus goes to the one in its place

const kept = build();
focused = null;
fire( rowButtons( kept, 2 )[0], 'click' );
check( 'moving a row up leaves the focus on the up button of the row, where it now stands', focused === rowButtons( kept, 1 )[0] );
fire( rowButtons( kept, 1 )[0], 'click' );
check( '...and at the top, where up has nowhere to go, on down', stored( kept ) === '["c","a","b"]' && focused === rowButtons( kept, 0 )[1] );
fire( rowButtons( kept, 0 )[1], 'click' );
check( 'moving down does the same', focused === rowButtons( kept, 1 )[1] );
fire( rowButtons( kept, 1 )[2], 'click' );
check( 'removing a row puts the focus on the remove button of the row that took its place', focused === rowButtons( kept, 1 )[2] );
fire( rowButtons( kept, 1 )[2], 'click' );
check( '...of the last one that is left, when it was the last', rows( kept ).length === 1 && focused === rowButtons( kept, 0 )[2] );
fire( rowButtons( kept, 0 )[2], 'click' );
check( 'and on the add button once no row is left', focused === addButton( kept ) );
fire( addButton( kept ), 'click' );
check( 'adding a row still puts the focus in its text', focused === rowInput( kept, 0 ) );

// --- the form is told -----------------------------------------------------

const told = [];
const watched = build( { onChange : function( value ) { told.push( JSON.stringify( value ) ) } } );
type( rowInput( watched, 0 ), 'x' );
fire( rowButtons( watched, 0 )[2], 'click' );
check( 'onChange hears every change with the value as the form will read it', told.join( '|' ) === '["x","b","c"]|["b","c"]' );
check( 'nothing is sent while nothing changes - an untouched list is not an edited one', told.length === 2 && stored( build() ) === '["a","b","c"]' );

// --- the caller owns every string -----------------------------------------

const source = fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' );
const control = source.slice( source.indexOf('stringList : function'), source.indexOf('tableModel : {') );
check( 'the control hard-codes no user-facing words', /textContent = '[A-Za-z]/.test( control ) === false && /aria-label', '[A-Za-z]/.test( control ) === false );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
