/**
 * Behaviour checks for the shared admin data table's pure half
 * (Nino.adminUi.tableModel in _admin/assets/Nino.admin.js).
 *
 * The renderer needs a DOM; the model does not, so the parts that are easy to
 * get wrong - type-aware ordering and page clamping - are tested directly by
 * loading the file into a stub window.
 *
 * Usage: node tests/nino-ui-table-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { check, done, element, fire } = require( './harness.js' );

// Nino.admin.js is an IIFE over (window, document, documentElement, body) and ends by
// bootstrapping itself, so it needs a context where `window` is the global -
// exactly what a browser gives it. The model half touches none of this.
const vm = require('vm');
const source = fs.readFileSync( path.join( __dirname, '../_nino/Nino.js' ), 'utf8' )
	+ fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' );

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

// --- the renderer's own controls ------------------------------------------

/*	The model half is what the rest of this file is about; these four are the
	renderer's, and the reason they are here is that three of them are drawn
	as glyphs. A button whose face is '‹' is announced as "button ‹", a select
	of bare numbers as "combo box", and the search box's placeholder is gone
	the moment somebody types in it - so the table had four controls and one
	name between them. The component owns no strings, so the names come from
	the caller where there is a word and from the workbench's shared fills
	where there is not	*/
function descendants( root ) {
	const found = [];
	( function walk( node ) {
		( node.children || [] ).forEach( function( child ) { found.push( child ); walk( child ) } );
	} )( root );
	return found;
}

// A recording element, rather than the no-op stub the model checks share:
// what is under test is exactly which attributes the renderer sets
ctx.document.createElement = element;
ctx.Nino.content = { getText : function( key ) { return 'fill:'+ key } };

const mount = element('div');
ctx.Nino.adminUi.table( {
	mount 	: mount,
	columns	: [ { key : 'uri', label : 'Uri', type : 'string' } ],
	rows 		: [ { uri : '/a' }, { uri : '/b' } ],
	labels 	: { search : 'Search rows', empty : 'Nothing here', noMatch : 'No match' },
} );

const drawn 		= descendants( mount );
const searchBox	= drawn.filter( el => el.type === 'search' )[0];
const steps 		= drawn.filter( el => el.className === 'nino-admin-table-step' );
const sizeSelect= drawn.filter( el => el.className === 'nino-admin-table-size' )[0];

check( 'the table drew its toolbar, its pager and its size select', searchBox !== undefined && steps.length === 2 && sizeSelect !== undefined );
check( 'the search box is named, not only hinted at', searchBox.getAttribute('aria-label') === 'Search rows' );
check( 'both pager arrows carry a name rather than only a glyph',
	steps[0].getAttribute('aria-label') === 'fill:/_admin/common/label/prevpage'
	&& steps[1].getAttribute('aria-label') === 'fill:/_admin/common/label/nextpage'
	&& steps[0].textContent === '\u2039' && steps[1].textContent === '\u203a' );
check( '...and so does the rows-per-page select', sizeSelect.getAttribute('aria-label') === 'fill:/_admin/common/label/perpage' );

// A caller with a word of its own is still the one that decides
const named = element('div');
ctx.Nino.adminUi.table( {
	mount 	: named,
	columns	: [ { key : 'uri', label : 'Uri', type : 'string' } ],
	rows 		: [ { uri : '/a' } ],
	labels 	: { search : 'Search', empty : '', noMatch : '', prev : 'Zurück', next : 'Weiter', pageSize : 'Zeilen' },
} );
const namedSteps = descendants( named ).filter( el => el.className === 'nino-admin-table-step' );
check( 'a caller\'s own words win over the shared fills', namedSteps[0].getAttribute('aria-label') === 'Zurück' && namedSteps[1].getAttribute('aria-label') === 'Weiter' );

/*	Which column the table is ordered by was drawn and not said: a ::after
	glyph on the sorted header, plus a class. A generated glyph is exactly
	what a screen reader is not handed, so the header says it in the attribute
	the pattern has for it - and an unsorted header saying "none" is what
	tells the reader the column can be sorted at all	*/
const sortable = element('div');
ctx.Nino.adminUi.table( {
	mount 	: sortable,
	columns	: [ { key : 'uri', label : 'Uri', type : 'string' }, { key : 'n', label : 'N', type : 'integer' } ],
	rows 		: [ { uri : '/b', n : 2 }, { uri : '/a', n : 1 } ],
	labels 	: { search : 'Search', empty : '', noMatch : '' },
} );
const headers = descendants( sortable ).filter( el => el.tagName === 'TH' );
check( 'an unsorted table says every column may be sorted', headers.length === 2 && headers.every( th => th.getAttribute('aria-sort') === 'none' ) );

const sortButtons = descendants( sortable ).filter( el => el.className === 'nino-admin-table-sort' );
fire( sortButtons[1], 'click' );
check( 'the column the table is ordered by says so, and in which direction', headers[1].getAttribute('aria-sort') === 'ascending' && headers[0].getAttribute('aria-sort') === 'none' );
fire( sortButtons[1], 'click' );
check( '...and says it again the other way round on a second press', headers[1].getAttribute('aria-sort') === 'descending' );
fire( sortButtons[0], 'click' );
check( '...and the column that lost the sort goes back to none', headers[1].getAttribute('aria-sort') === 'none' && headers[0].getAttribute('aria-sort') === 'ascending' );


/*	A column with no label is a column of a row's own buttons: its sort button
	would be announced as nothing and offer an order nobody can name. A row is a
	stop of the Tab key on its own only where it holds no control - the one that
	stands in it is the stop, and a row that was one as well made two for the
	same thing	*/
ctx.document.createElement = function( tag ) {
	const el = element( tag );
	// A row is no stop of the Tab key in a browser until it is given a tabindex
	if( tag === 'tr' )
		el.tabIndex = -1;
	return el;
};

const opened = [];
const actions = element('div');
ctx.Nino.adminUi.table( {
	mount 	: actions,
	columns	: [
		{ key : 'uri', label : 'Uri', type : 'string' },
		{ key : 'actions', label : '', type : 'string', render : function( value, row ) {
			if( row.uri === '/plain' )
				return 'text';
			// A node, as the table takes one into a cell as it is: it asks whether the
			// cell is an Object of its own context, which a stand-in made here is not
			const open = element('button');
			Object.setPrototypeOf( open, vm.runInContext( 'Object.prototype', ctx ) );
			open.nodeType = 1;
			open.textContent = 'Open';
			return open;
		} },
		{ key : 'forced', label : '', sortable : true, type : 'string' },
		{ key : 'quiet', label : 'Quiet', sortable : false, type : 'string' },
	],
	rows 		: [ { uri : '/button' }, { uri : '/plain' } ],
	labels 	: { search : 'Search', empty : '', noMatch : '' },
	onRowClick : function( row ) { opened.push( row.uri ) },
} );

const actionHeads = descendants( actions ).filter( el => el.tagName === 'TH' );
const actionSorts = descendants( actions ).filter( el => el.className === 'nino-admin-table-sort' );
check( 'a column with a label is sorted by its button, and so is one that says it may be sorted', actionHeads[0].querySelector('button') !== null && actionHeads[2].querySelector('button') !== null && actionSorts.length === 2 );
check( '...a column without a label has no sort button, nor has one that says it may not', actionHeads[1].querySelector('button') === null && actionHeads[3].querySelector('button') === null );
check( '...and it claims no sort order either: aria-sort says "none" only of a column that can be sorted', actionHeads[0].getAttribute('aria-sort') === 'none' && actionHeads[1].getAttribute('aria-sort') === null && actionHeads[3].getAttribute('aria-sort') === null );

const bodyRows = descendants( actions ).filter( el => el.tagName === 'TR' && el.classList.contains('is-clickable') );
check( 'a row that holds a button of its own is not a stop of the Tab key: the button is', bodyRows.length === 2 && bodyRows[0].tabIndex === -1 );
check( '...a row that holds none is, so that it can be opened from the keyboard', bodyRows[1].tabIndex === 0 );
check( 'a row carries the identity of its record - the row key - for the caller that finds it again after the table was drawn anew', bodyRows.map( row => row.dataset.row ).join() === '/button,/plain' );

fire( bodyRows[1], 'keydown', { key : 'Enter' } );
check( 'Enter on the row itself opens it', opened.join() === '/plain' );
fire( bodyRows[0], 'keydown', { key : 'Enter', target : descendants( bodyRows[0] ).filter( el => el.tagName === 'BUTTON' )[0] } );
check( '...Enter on a button inside the row is the button\'s and does not open the row as well', opened.join() === '/plain' );
fire( bodyRows[0], 'click' );
check( 'a click on a row opens it as before', opened.join() === '/plain,/button' );

done();
