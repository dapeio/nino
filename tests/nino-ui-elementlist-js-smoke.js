/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-elementlist-js-smoke.js	DOM-light checks for
 *											Nino.adminUi.elementList(), the control an element
 *											field uses once its model lets it hold more than one
 *											reference.
 *
 *											Both element forms render it (see _admin/assets/
 *											elements.js and _editor/assets/elements.js), so its
 *											behaviour is pinned once here rather than twice
 *											over there: what the hidden input holds after every
 *											move, the cap, and that a target deleted since is
 *											still shown rather than dropped.
 *
 *	Usage: node tests/nino-ui-elementlist-js-smoke.js
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
	fs.readFileSync( path.join( __dirname, '../_nino/Nino.admin.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'Nino.admin.js' }
);

const adminUi = sandbox.Nino.adminUi;

const TEXT = {
	search : 'Search', empty : 'Nothing chosen', noMatches : 'No match',
	more : '%d matches', missing : 'missing', up : 'Up', down : 'Down',
	remove : 'Remove', add : 'Add', full : 'Full',
};

const CATALOGUE = [
	{ value : '/tag/php', 	label : 'PHP' },
	{ value : '/tag/css', 	label : 'CSS' },
	{ value : '/tag/html', 	label : 'HTML' },
];

function build( options ) {
	return adminUi.elementList( Object.assign( {
		key : 'tags', label : 'Tags', value : [], limit : 0,
		options : CATALOGUE, text : TEXT,
	}, options || {} ) );
}

function store( field ) {
	return findAll( field, function( el ) { return el.type === 'hidden' } )[0];
}

function stored( field ) {
	return JSON.parse( store( field ).value );
}

function chosenRows( field ) {
	return byClass( field, 'nino-admin-elementlist-chosen' )[0].children;
}

function resultRows( field ) {
	return byClass( field, 'nino-admin-elementlist-results' )[0].children;
}

function addButtons( field ) {
	return byClass( field, 'nino-admin-elementlist-add' );
}

console.log( 'Nino.adminUi.elementList - the multi-reference control\n' );

// --- the value the form reads back ---------------------------------------

// Every other field in both forms is read out of the dom by [data-field]. A
// control keeping its value in closure state instead would be the one field
// that breaks locale switching, dirty tracking and save alike
const empty = build();
check( 'the value lives in a hidden input carrying data-field', store( empty ).dataset.field === 'tags' );
check( '...typed as an element reference, marked as a list', store( empty ).dataset.type === 'element' && store( empty ).dataset.multiple === '0' );
check( 'an untouched control already holds valid json', stored( empty ).length === 0 );
check( 'nothing chosen says so rather than showing a blank box', chosenRows( empty )[0].textContent === 'Nothing chosen' );

const seeded = build( { value : [ '/tag/css', '/tag/php' ] } );
check( 'a stored value is offered in the order it was stored', JSON.stringify( stored( seeded ) ) === '["/tag/css","/tag/php"]' );
check( '...and each row is named by its label, not its uri', chosenRows( seeded )[0].children[0].textContent === 'CSS' );
check( '...with the uri still reachable as the row title', chosenRows( seeded )[0].children[0].title === '/tag/css' );

// --- adding ---------------------------------------------------------------

const adding = build();
check( 'everything on offer is listed before a search is typed', addButtons( adding ).length === 3 );
fire( addButtons( adding )[1], 'click' );
check( 'the + button adds that element', JSON.stringify( stored( adding ) ) === '["/tag/css"]' );
check( '...and it leaves the offered list, so it cannot be added twice', addButtons( adding ).length === 2 );
fire( addButtons( adding )[0], 'click' );
check( 'a second add appends rather than replaces', JSON.stringify( stored( adding ) ) === '["/tag/css","/tag/php"]' );

// --- ordering -------------------------------------------------------------

// Order is the whole reason this is a list and not a multi-select: it is the
// order a template renders the referenced elements in
const ordered = build( { value : [ '/tag/php', '/tag/css', '/tag/html' ] } );
const rowButtons = function( field, index ) { return chosenRows( field )[index].children[1].children };

check( 'the first row cannot move up', rowButtons( ordered, 0 )[0].disabled === true );
check( 'the last row cannot move down', rowButtons( ordered, 2 )[1].disabled === true );

fire( rowButtons( ordered, 1 )[0], 'click' );
check( 'up swaps a row with the one above it', JSON.stringify( stored( ordered ) ) === '["/tag/css","/tag/php","/tag/html"]' );

fire( rowButtons( ordered, 0 )[1], 'click' );
check( 'down swaps it back', JSON.stringify( stored( ordered ) ) === '["/tag/php","/tag/css","/tag/html"]' );

fire( rowButtons( ordered, 1 )[2], 'click' );
check( 'remove drops exactly its own row', JSON.stringify( stored( ordered ) ) === '["/tag/php","/tag/html"]' );
check( '...and the removed element is on offer again', addButtons( ordered ).length === 1 );

// --- the cap --------------------------------------------------------------

// The model's promise, so the control refuses past it rather than letting the
// save be the thing that says no. The kernel checks it again either way
const capped = build( { limit : 2, value : [ '/tag/php' ] } );
check( 'a capped control counts against its ceiling', byClass( capped, 'nino-admin-elementlist-count' )[0].textContent === '1 / 2' );
fire( addButtons( capped )[0], 'click' );
check( 'the cap is reached at exactly its number', stored( capped ).length === 2 );
check( '...and nothing more is offered', addButtons( capped ).length === 0 );
check( '...the reason being said rather than left to a dead search box', resultRows( capped )[0].textContent === 'Full' );
check( '...and the search field is disabled while it holds', byClass( capped, 'nino-admin-elementlist-search' )[0].disabled === true );

const unlimited = build( { limit : 0, value : [ '/tag/php', '/tag/css', '/tag/html' ] } );
check( 'an uncapped control shows a plain count, not a ratio', byClass( unlimited, 'nino-admin-elementlist-count' )[0].textContent === '3' );

// --- searching ------------------------------------------------------------

// Filtered over the options the form already loaded for the single-reference
// select - a request per keystroke would ask a second time for what is here
const searching = build();
const searchBox = byClass( searching, 'nino-admin-elementlist-search' )[0];

searchBox.value = 'ss';
fire( searchBox, 'input' );
check( 'the search matches a label case-insensitively', addButtons( searching ).length === 1 && resultRows( searching )[0].children[1].textContent === 'CSS' );

searchBox.value = '/tag/ht';
fire( searchBox, 'input' );
check( '...and matches the uri too, which is what a schema author knows', addButtons( searching ).length === 1 );

searchBox.value = 'nothing here';
fire( searchBox, 'input' );
check( 'no hits says so rather than showing an empty box', resultRows( searching )[0].textContent === 'No match' );

searchBox.value = '';
fire( searchBox, 'input' );
check( 'clearing the search restores the full offer', addButtons( searching ).length === 3 );

// A truncated result list that said nothing would read as "there is nothing
// else", which is the one thing it must not mean
const many = [];
for( let i = 0; i < adminUi.ELEMENTLIST_RESULTS + 5; i++ )
	many.push( { value : '/tag/t'+ i, label : 'Tag '+ i } );

const large = build( { options : many } );
check( 'a long catalogue draws at most ELEMENTLIST_RESULTS rows', addButtons( large ).length === adminUi.ELEMENTLIST_RESULTS );
check( '...and says how many it actually found', resultRows( large )[adminUi.ELEMENTLIST_RESULTS].textContent === String( many.length )+ ' matches' );

// --- a target deleted since ------------------------------------------------

// Dropping it here would turn "this points at something gone" into a silent
// data loss the next save makes permanent - the same rule the single
// reference select follows
const dangling = build( { value : [ '/tag/php', '/tag/removed' ] } );
check( 'a reference whose target is gone keeps its place in the value', JSON.stringify( stored( dangling ) ) === '["/tag/php","/tag/removed"]' );
check( '...and is shown as its uri, marked missing', chosenRows( dangling )[1].children[0].textContent === '/tag/removed (missing)' );

// --- the caller owns every string -----------------------------------------

const source = fs.readFileSync( path.join( __dirname, '../_nino/Nino.admin.js' ), 'utf8' );
const control = source.slice( source.indexOf('elementList : function'), source.indexOf('tableModel : {') );
check( 'the control hard-codes no user-facing words', /textContent = '[A-Za-z]/.test( control ) === false );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
