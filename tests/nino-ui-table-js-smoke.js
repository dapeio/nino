/**
 * Behaviour checks for the shared admin data table's pure half
 * (Nino.adminUi.tableModel in _nino/Nino.admin.js).
 *
 * The renderer needs a DOM; the model does not, so the parts that are easy to
 * get wrong - type-aware ordering, page clamping, which fields may appear at
 * all - are tested directly by loading the file into a stub window.
 *
 * Usage: node tests/nino-ui-table-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

let checks = 0;
let failures = 0;

function check( label, condition, detail ) {
	checks++;
	if( condition ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label+ ( detail ? '  ('+ detail+ ')' : '' ) );
}

// Nino.admin.js is an IIFE over (window, document, documentElement, body) and ends by
// bootstrapping itself, so it needs a context where `window` is the global -
// exactly what a browser gives it. The model half touches none of this.
const vm = require('vm');
const source = fs.readFileSync( path.join( __dirname, '../_nino/Nino.js' ), 'utf8' )
	+ fs.readFileSync( path.join( __dirname, '../_nino/Nino.admin.js' ), 'utf8' );

const noop = () => {};
const stubEl = () => ( { classList : { add : noop, remove : noop, toggle : noop, contains : () => false },
                         style : {}, dataset : {}, appendChild : noop, addEventListener : noop,
                         querySelectorAll : () => [], setAttribute : noop } );

const ctx = {
	document : { createElement : stubEl, addEventListener : noop, readyState : 'complete',
	             documentElement : stubEl(), body : stubEl(), cookie : '',
	             querySelectorAll : () => [], querySelector : () => null },
	location : { pathname : '/', origin : 'http://localhost', search : '' },
	navigator : { userAgent : 'node' },
	addEventListener : noop, matchMedia : () => ( { matches : false, addEventListener : noop } ),
	screen : { width : 1024 }, innerWidth : 1024, innerHeight : 768,
	setTimeout : noop, clearTimeout : noop, requestAnimationFrame : noop,
};
ctx.window = ctx;
ctx.globalThis = ctx;
vm.createContext( ctx );
vm.runInContext( source, ctx );

const model = ctx.Nino.adminUi.tableModel;

console.log('Shared admin data table (model)');

// --- which fields may be a column ----------------------------------------

check( 'a plain string is displayable', model.isDisplayable( { type : 'string' } ) === true );
check( 'numbers, booleans and dates are displayable',
	[ 'integer', 'double', 'boolean', 'date', 'datetime' ].every( t => model.isDisplayable( { type : t } ) === true ) );
check( 'an element reference is displayable - it is a uri, which is text',
	model.isDisplayable( { type : 'element' } ) === true );
check( 'an image is not - a cell cannot show a file', model.isDisplayable( { type : 'image' } ) === false );
check( 'an array is not', model.isDisplayable( { type : 'array' } ) === false );
// The one that is easy to miss: rich text is a string by type, but its value
// is markup, and the table renders text
check( 'a rich-text string is not displayable even though its type is "string"',
	model.isDisplayable( { type : 'string', html : true } ) === false );

// --- cell text ------------------------------------------------------------

check( 'an unset value renders as nothing, not as "null"',
	[ null, undefined, '' ].every( v => model.format( v, 'string' ) === '' ) );
check( 'a boolean renders as a glyph, so no caller has to translate it',
	model.format( true, 'boolean' ) === '✓' && model.format( false, 'boolean' ) === '–' );
check( 'zero is a value, not an absence', model.format( 0, 'integer' ) === '0' );
// An element reference that holds a list arrives as an array. String() would
// join it with a bare comma, which reads as one run-on uri
check( 'a list of references is joined readably',
	model.format( [ '/tag/php', '/tag/css' ], 'element' ) === '/tag/php, /tag/css' );
check( 'an empty list is as absent as a blank string, so sort() places it with them',
	model.isEmpty( [] ) === true && model.isEmpty( [ '/tag/php' ] ) === false );

// --- ordering -------------------------------------------------------------

const nums = [ { n : 10 }, { n : 9 }, { n : 100 } ];
check( 'numbers sort numerically, not lexically',
	model.sort( nums, 'n', 'integer', 1 ).map( r => r.n ).join() === '9,10,100' );

check( 'descending reverses it',
	model.sort( nums, 'n', 'integer', -1 ).map( r => r.n ).join() === '100,10,9' );

const bools = [ { b : true }, { b : false }, { b : true } ];
check( 'booleans sort false-first ascending, not by the words "true"/"false"',
	model.sort( bools, 'b', 'boolean', 1 ).map( r => r.b ).join() === 'false,true,true' );

const dates = [ { d : '2026-03-09' }, { d : '2026-03-10' }, { d : '2025-12-31' } ];
check( 'iso dates sort chronologically',
	model.sort( dates, 'd', 'date', 1 ).map( r => r.d ).join() === '2025-12-31,2026-03-09,2026-03-10' );

// An absent value is not "smallest" - it belongs at the end whichever way the
// column is sorted, or every descending sort opens on a screenful of blanks
const sparse = [ { s : 'b' }, { s : null }, { s : 'a' } ];
check( 'empty values sort last ascending',
	model.sort( sparse, 's', 'string', 1 ).map( r => r.s === null ? '-' : r.s ).join() === 'a,b,-' );
check( '...and last descending too',
	model.sort( sparse, 's', 'string', -1 ).map( r => r.s === null ? '-' : r.s ).join() === 'b,a,-' );

check( 'sorting returns a new array rather than reordering the caller\'s',
	( function() { const input = [ { n : 2 }, { n : 1 } ]; model.sort( input, 'n', 'integer', 1 ); return input[0].n === 2 } )() );

check( 'no sort key leaves the order alone',
	model.sort( nums, '', '', 1 ).map( r => r.n ).join() === '10,9,100' );

// --- search ---------------------------------------------------------------

const cols = [ { key : 'title', type : 'string' }, { key : 'views', type : 'integer' } ];
const rows = [ { title : 'Hallo Welt', views : 5 }, { title : 'Zweiter', views : 42 } ];

check( 'search matches across every visible column',
	model.filter( rows, cols, 'welt' ).length === 1 && model.filter( rows, cols, '42' ).length === 1 );
check( 'search is case-insensitive', model.filter( rows, cols, 'HALLO' ).length === 1 );
check( 'an empty query matches everything', model.filter( rows, cols, '   ' ).length === 2 );
check( 'a column that is not shown is not searched',
	model.filter( [ { title : 'x', hidden : 'secret' } ], cols, 'secret' ).length === 0 );

// --- paging ---------------------------------------------------------------

const many = Array.from( { length : 237 }, ( _, i ) => ( { n : i } ) );

const first = model.page( many, 50, 1 );
check( 'the first page reports its range from 1', first.from === 1 && first.to === 50 && first.total === 237 );
check( 'and carries exactly one page of rows', first.rows.length === 50 && first.pages === 5 );

const last = model.page( many, 50, 5 );
check( 'the last page is short rather than padded', last.rows.length === 37 && last.to === 237 );

// Filtering down to fewer results than the current page starts at must not
// leave the user staring at an empty table
check( 'a page number past the end clamps to the last page', model.page( many, 50, 99 ).page === 5 );
check( 'a page number below 1 clamps to the first', model.page( many, 50, 0 ).page === 1 );

const none = model.page( [], 50, 1 );
check( 'an empty set still has a page 1, and reports a range of zero',
	none.pages === 1 && none.page === 1 && none.from === 0 && none.to === 0 && none.total === 0 );

check( 'page size 0 cannot divide by zero', model.pageCount( 10, 0 ) >= 1 );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
