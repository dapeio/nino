/**
 *	Nino											A compact filesystembased php framework
 *	nino-ui-directory-js-smoke.js	DOM-light checks for the standard directory of the workbench:
 *																Nino.adminUi.directory() and what it is made of -
 *																filterBar( { filters, collapse } ), table( { filters, rowFilter,
 *																state, onState, cell } ) and its handle, the counter of a field,
 *																and the three blocks a record is read with (readLine(),
 *																messageBox(), imageFrame()).
 *
 *																Pinned: the order of the toolbar (category, search, filters)
 *																whatever order a caller gives them in; the button of a narrow
 *																screen that counts the selects that narrow the list; that the
 *																selects are applied by the directory, together with the search,
 *																and that the choice of the rows outlives them; that what a
 *																person chose (search, selects, sort, page) is told and is taken
 *																back when the directory is drawn again; the order of the rows
 *																across the pages, which a record walks; the kinds of cell; the
 *																page of 25 for a table of pictures and of 50 for any other;
 *																that nothing a row holds is ever set as markup.
 *
 *	Usage: node tests/nino-ui-directory-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, documentOf, element, findAll, fire, markupWrites, source } = require( './harness.js' );

const hasClass = ( el, name ) => String( el.className ).split(/\s+/).indexOf( name ) !== -1;
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );
const byClass = ( root, name ) => findAll( root, el => hasClass( el, name ) );

function world( extra ) {

	const page = element('div');
	const doc = documentOf( page );
	const Nino = { events : { bindCallback : function() {} }, content : { getText : function( key ) { return key } } };

	doc.body = element('body');
	doc.documentElement = { lang : 'en' };

	const sandbox = { console : console, document : doc, Nino : Nino };

	Object.assign( sandbox, extra ?? {} );
	sandbox.window = { Nino : Nino, matchMedia : function() { return { matches : false } } };
	load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );

	return { ui : Nino.adminUi, page : page, doc : doc };
}

// The rows of the table that are drawn, by their key
const shown = mount => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined ).map( el => el.dataset.row );
const sorter = ( mount, key ) => findAll( mount, el => el.tagName === 'TH' && el.dataset.key === key )[0];

console.log('Standard directory - filterBar, table, directory, and the blocks of a record\n');


// --- the filter bar: more selects, and the button of a narrow screen -----------------------------

{
	const w = world();
	const changes = [];
	const bar = w.ui.filterBar( {
		filter : { id : 'cat', label : 'Group', options : [ { value : '', label : 'All groups' }, { value : 'a', label : 'A' } ], value : '', onChange : v => changes.push( [ 'cat', v ] ) },
		search : { label : 'Search things', onInput : v => changes.push( [ 'q', v ] ) },
		filters : [
			{ id : 'f1', label : 'State', options : [ { value : '', label : 'All' }, { value : 'x', label : 'X' } ], value : '', onChange : v => changes.push( [ 'f1', v ] ) },
			{ id : 'f2', label : 'Kind', options : [ { value : '', label : 'All' }, { value : 'y', label : 'Y' } ], value : '', onChange : v => changes.push( [ 'f2', v ] ) },
		],
		collapse : { label : 'Filters' },
	} );

	check( 'the bar draws the select of the category, the search, then the further selects, in the order they are given',
		bar.el.children.slice( 0, 4 ).map( el => el.tagName+ ':'+ ( el.id || el.attributes['aria-label'] ) ).join('|') === 'SELECT:cat|INPUT:Search things|SELECT:f1|SELECT:f2' );
	check( 'every select is named for a screen reader and answers with its own value',
		bar.selects.length === 3 && bar.selects.every( el => String( el.attributes['aria-label'] ) !== '' ) && ( () => { bar.selects[1].value = 'x'; fire( bar.selects[1], 'change' ); return changes.pop().join() === 'f1,x' } )() );

	check( 'a collapsed bar has one button, after the selects, closed, named by the caller, that says how many selects narrow the list',
		bar.toggle !== null && bar.toggle.tagName === 'BUTTON' && bar.toggle.attributes['aria-expanded'] === 'true' && bar.el.dataset.collapsible === 'true' );
	check( '...it counts the selects that hold another value than their first option, and no number for none',
		( () => { bar.selects[1].value = ''; fire( bar.selects[1], 'change' ); return bar.toggle.dataset.active === '0' && byClass( bar.toggle, 'nino-admin-filterbar-count' )[0].hidden === true } )()
		&& ( () => { bar.selects[0].value = 'a'; bar.selects[2].value = 'y'; fire( bar.selects[0], 'change' ); fire( bar.selects[2], 'change' ); return bar.toggle.dataset.active === '2' && byClass( bar.toggle, 'nino-admin-filterbar-count' )[0].textContent === '2' } )() );
	check( '...and a select that narrows is not left out of sight: the bar opens by itself', bar.el.dataset.open === 'true' );
	fire( bar.toggle, 'click' );
	check( 'the button closes and opens the selects, and says so (aria-expanded)', bar.el.dataset.open === 'false' && bar.toggle.attributes['aria-expanded'] === 'false' );

	const plain = w.ui.filterBar( { search : { label : 's' }, filters : [ { id : 'x', label : 'x', options : [ { value : '', label : 'a' } ] } ] } );
	check( 'a bar without `collapse` has no button and keeps its selects in view', plain.toggle === null && plain.el.dataset.collapsible === undefined );
}


// --- the cells ---------------------------------------------------------------

const ROWS = [];
for( let i = 1; i <= 60; i++ ) {
	const key = 'r'+ String( i ).padStart( 2, '0' );
	ROWS.push( {
		key : key,
		name : 'Item '+ i,
		group : i % 3 === 0 ? 'b' : 'a',
		state : i % 4 === 0 ? 'off' : 'on',
		size : 100 + i,
		thumb : i % 5 === 0 ? '' : '/images/'+ key+ '.jpg',
		alts : [ { locale : 'de_DE', label : 'DE', name : 'Deutsch', ok : i % 2 === 0 }, { locale : 'en_US', label : 'EN', name : 'English (US)', ok : i % 7 !== 0 } ],
		length : 790 + i,
		text : 'A long text of the row '+ i+ ' that does not fit in a line',
		when : '2026-11-'+ String( 1 + ( i % 28 ) ).padStart( 2, '0' )+ ' 10:00:00',
	} );
}

const COLUMNS = [
	{ key : 'thumb', label : 'Preview', cell : 'thumb' },
	{ key : 'name', label : 'Name', type : 'string' },
	{ key : 'alts', label : 'Alt text', cell : 'locales', words : { ok : 'has one', missing : 'is missing' } },
	{ key : 'size', label : 'Size', type : 'integer' },
	{ key : 'length', label : 'Length', cell : 'limit', limit : () => 800, over : 'Too long', type : 'integer' },
	{ key : 'text', label : 'Text', cell : 'clamp', max : '12rem' },
	{ key : 'when', label : 'Date', cell : 'date', type : 'date' },
	{ key : 'state', label : 'State', cell : 'badge', render : undefined },
];

{
	const w = world();
	const mount = element('div');
	const markupBefore = markupWrites.length;

	const pictures = w.ui.table( { mount : mount, columns : COLUMNS, rows : ROWS, rowKey : 'key', labels : { search : 'Search', empty : 'none', noMatch : 'no match' } } );

	const first = findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r01' )[0];
	const cells = first.children;

	const picture = byClass( cells[0], 'nino-admin-thumb' )[0];
	check( 'a picture is a frame of one size with the picture in it, no name of its own (the name stands in the cell beside it), loaded when it comes near',
		picture !== undefined && tag( picture, 'img' )[0].src === '/images/r01.jpg' && tag( picture, 'img' )[0].alt === '' && tag( picture, 'img' )[0].loading === 'lazy' );
	const noPicture = byClass( findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r05' )[0], 'nino-admin-thumb' )[0];
	check( 'a thing with no picture has the sign of "none" in the same frame', hasClass( noPicture, 'is-none' ) && tag( noPicture, 'img' ).length === 0 && tag( noPicture, 'svg' ).length === 1 );

	const bad = world();
	const m2 = element('div');
	bad.ui.table( { mount : m2, columns : [ { key : 'thumb', label : 'P', cell : 'thumb' } ], rows : [ { key : 'a', thumb : 'javascript:alert(1)' }, { key : 'b', thumb : 'data:text/html,<b>' }, { key : 'c', thumb : 'https://example.org/x.png' }, { key : 'd', thumb : 'data:image/png;base64,AAAA' } ], rowKey : 'key', labels : {} } );
	const ctl = element('div');
	bad.ui.table( { mount : ctl, columns : [ { key : 'thumb', label : 'P', cell : 'thumb' } ], rows : [ { key : 'a', thumb : '/images/a\n.png' }, { key : 'b', thumb : '/images/\u0000x.png' }, { key : 'c', thumb : '/images/ok.png' } ], rowKey : 'key', labels : {} } );
	check( 'an address with a line break or a control character in it is none', tag( ctl, 'img' ).map( el => el.src ).join('|') === '/images/ok.png' );
	check( 'an address that is no picture - a script, a document - is none; the web and an image given as data are pictures',
		tag( m2, 'img' ).map( el => el.src ).join('|') === 'https://example.org/x.png|data:image/png;base64,AAAA' && byClass( m2, 'is-none' ).length === 2 );

	const states = byClass( cells[2], 'nino-admin-badge' );
	check( 'the state in each language is one badge for each, "DE ✓" and "EN –": a sign and not the colour says which; the name of the language is its tooltip, and what it says',
		states.map( el => el.textContent ).join('|') === 'DE –|EN ✓' && states[0].title === 'Deutsch: is missing' && states[1].title === 'English (US): has one' && states[0].dataset.state === 'warn' && states[1].dataset.state === 'ok' );
	check( '...and wraps where there are many languages, so that nothing is cut', hasClass( byClass( cells[2], 'nino-admin-localestates' )[0], 'nino-admin-localestates' ) && hasClass( cells[2], 'nino-admin-table-cell-locales' ) );

	check( 'a number column has its cells and its head at the right', hasClass( cells[3], 'nino-admin-table-num' ) && hasClass( sorter( mount, 'size' ), 'nino-admin-table-num' ) );
	check( 'a number against a limit reads "791 / 800" and is not marked below it', byClass( cells[4], 'nino-admin-table-limit' )[0].textContent === '791 / 800' && byClass( cells[4], 'nino-admin-table-limit' )[0].dataset.over === undefined );
	const over = byClass( findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r20' )[0], 'nino-admin-table-limit' )[0];
	check( '...exactly at the limit it is not over', byClass( findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r10' )[0], 'nino-admin-table-limit' )[0].textContent === '800 / 800' && byClass( findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r10' )[0], 'nino-admin-table-limit' )[0].dataset.over === undefined );
	check( '...over it the cell says so in a word as well as in its colour', over.dataset.over === 'true' && byClass( over, 'nino-admin-badge' )[0].textContent === 'Too long' );

	const clamped = byClass( cells[5], 'nino-admin-table-clamp' )[0];
	check( 'a text that is one line is cut by an ellipsis, in the width the column gives, and is whole in its tooltip', clamped.title === ROWS[0].text && clamped.style['--nino-admin-clamp'] === '12rem' && clamped.textContent === ROWS[0].text );

	check( 'a date is written as the workbench writes a moment', cells[6].textContent === w.ui.dateText( ROWS[0].when ) && cells[6].textContent !== ROWS[0].when );
	check( 'a badge is the word of the value', byClass( cells[7], 'nino-admin-badge' )[0] !== undefined && byClass( cells[7], 'nino-admin-badge' )[0].textContent === 'on' );

	check( 'a picture and the states of the languages are heads that are words and no buttons - no order is made of them',
		tag( sorter( mount, 'thumb' ), 'button' ).length === 0 && tag( sorter( mount, 'alts' ), 'button' ).length === 0 && tag( sorter( mount, 'name' ), 'button' ).length === 1 );

	// What the search reads is what a cell shows
	const search = findAll( mount, el => el.tagName === 'INPUT' )[0];
	search.value = 'deutsch ✓';
	fire( search, 'input' );
	check( 'the search reads the cell as it is shown - the state of a language by the name of the language', pictures.rows().length === 30 && pictures.rows()[0].key === 'r02' );
	search.value = w.ui.dateText( '2026-11-05 10:00:00' );
	fire( search, 'input' );
	check( '...and a date as it is written', pictures.rows().length > 0 && pictures.rows().every( row => w.ui.dateText( row.when ) === w.ui.dateText( '2026-11-05 10:00:00' ) ) );

	check( 'nothing a row holds was written as markup', markupWrites.length === markupBefore );

	// Markup in every cell is shown as the letters it is
	const hostile = '<img src=x onerror=alert(1)>';
	const m3 = element('div');
	w.ui.table( { mount : m3, columns : [ { key : 'a', label : 'a', cell : 'clamp' }, { key : 'b', label : 'b', cell : 'badge' }, { key : 'c', label : 'c', cell : 'limit', limit : () => 1, over : hostile }, { key : 'd', label : 'd', cell : 'locales', words : { ok : hostile, missing : hostile } } ],
		rows : [ { key : 'x', a : hostile, b : { text : hostile, state : 'warn' }, c : 5, d : [ { label : hostile, name : hostile, ok : false } ] } ], rowKey : 'key', labels : {} } );
	check( 'a value that spells markup is shown as text in every kind of cell', tag( m3, 'img' ).length === 0 && markupWrites.length === markupBefore && byClass( m3, 'nino-admin-table-clamp' )[0].textContent === hostile );
}


// --- a cell with a value that is empty keeps the mark of an empty cell, a cell of a kind does not ------------------------

{
	const w = world();
	const mount = element('div');

	w.ui.table( { mount : mount, columns : [
		{ key : 'a', label : 'A', render : function( value ) { return element('span') } },
		{ key : 'b', label : 'B' },
		{ key : 'c', label : 'C', cell : 'thumb' },
	], rows : [ { key : 'x', a : '', b : '', c : '' } ], rowKey : 'key', labels : {} } );

	const cells = findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'x' )[0].children;

	check( 'the cell of an empty value is marked as empty whether it shows an element or text - as a table that has no kinds of cell has always drawn it - and a picture frame is not marked', hasClass( cells[0], 'is-empty' ) && hasClass( cells[1], 'is-empty' ) && hasClass( cells[2], 'is-empty' ) === false );

	let thrown = null;

	try {
		w.ui.table( { mount : element('div'), columns : [ { key : 'a', label : 'A', cell : 'nothing' } ], rows : [ { key : 'x', a : 1 } ], rowKey : 'key', labels : {} } );
	} catch( error ) {
		thrown = error.name;
	}

	check( 'a kind of cell that is none is an error of the caller, and `text` is the value as text', thrown === 'TypeError' && w.ui._cell( { key : 'a', cell : 'text' }, { a : 5 } ).text === '5' );

	// Every kind the guide names is one the table draws
	const guide = source('docs/admin-ui.md');
	const named = guide.slice( guide.indexOf('The cells of a column are text unless') ).split('\n')[0];
	const kinds = Array.from( named.matchAll( /`(clamp|limit|thumb|locales|date|badge|text)`/g ) ).map( match => match[1] );

	check( 'the guide names the seven kinds of cell, and each of them is drawn without an error', new Set( kinds ).size === 7 && kinds.every( kind => {
		try {
			w.ui._cell( { key : 'v', cell : kind, limit : () => 5 }, { v : kind === 'locales' ? [] : '2026-11-01' } );
			return true;
		} catch( error ) {
			return false;
		}
	} ) );
}


// --- the table: the selects, the state, the order of the rows ------------------------

{
	const w = world();
	const mount = element('div');
	const told = [];
	const cat = { id : 'cat', label : 'Group', options : [ { value : '', label : 'All' }, { value : 'a', label : 'A' }, { value : 'b', label : 'B' } ], value : '' };
	let group = '';
	cat.onChange = v => { group = v; handle.refilter() };

	const handle = w.ui.table( {
		mount : mount, columns : [ { key : 'name', label : 'Name', type : 'string' }, { key : 'size', label : 'Size', type : 'integer' } ], rows : ROWS, rowKey : 'key',
		labels : { search : 'Search', empty : 'none', noMatch : 'no match', range : '%s to %s of %s' },
		filter : cat, filters : [ { id : 'state', label : 'State', options : [ { value : '', label : 'All' } ] } ],
		select : { actions : [] },
		rowFilter : row => group === '' || row.group === group,
		onState : s => told.push( s ),
	} );

	const toolbar = byClass( mount, 'nino-admin-table-toolbar' )[0];
	check( 'the toolbar of a table with selects is the one bar: the category, the search, the further selects', toolbar.children.map( el => el.tagName ).join() === 'SELECT,INPUT,SELECT' && hasClass( toolbar, 'nino-admin-table-toolbar--filtered' ) );
	check( 'a table of 60 rows pages by 50 and says where it is in the words the caller gave', shown( mount ).length === 50 && byClass( mount, 'nino-admin-table-range' )[0].textContent === '1 to 50 of 60' );
	check( 'the rows the table leaves are all of them, in the order they have, across the pages', handle.rows().length === 60 && handle.rows()[55].key === 'r56' );

	group = 'b';
	handle.refilter();
	check( 'the caller\'s rowFilter narrows the rows and the table starts again at the first page', shown( mount ).length === 20 && handle.rows().length === 20 && handle.state().page === 1 );

	group = '';
	handle.refilter();
	const size = findAll( mount, el => el.tagName === 'SELECT' && hasClass( el, 'nino-admin-table-size' ) )[0];
	size.value = '100';
	fire( size, 'change' );
	told.length = 0;

	// Sorted, and moved to the second page
	handle.setRows( ROWS );
	fire( tag( sorter( mount, 'size' ), 'button' )[0], 'click' );
	fire( tag( sorter( mount, 'size' ), 'button' )[0], 'click' );
	check( 'a sort is told, with its direction', told.length === 2 && told[1].sort.key === 'size' && told[1].sort.dir === -1 );
	check( 'the rows in order are the sorted ones: the largest first', handle.rows()[0].key === 'r60' && handle.rows()[59].key === 'r01' );

	const m2 = element('div');
	const t2 = w.ui.table( { mount : m2, columns : [ { key : 'name', label : 'Name', type : 'string' } ], rows : ROWS, rowKey : 'key', labels : { search : 's' }, pageSize : 25, pageSizes : [ 25, 50 ], state : { sort : { key : 'name', dir : -1 }, page : 2, pageSize : 25 }, query : 'item', onState : s => told.push( s ) } );
	check( 'a table drawn again with the state it had is as it was: the sort, the search, the size of the page and the page',
		JSON.stringify( t2.state() ) === JSON.stringify( { query : 'item', sort : { key : 'name', dir : -1 }, page : 2, pageSize : 25 } ) && shown( m2 ).length === 25 );
	t2.reveal( 'r01' );
	check( 'reveal() brings the page the row is on to the screen, by the order the table has, and says so', t2.state().page !== 2 || shown( m2 ).indexOf( 'r01' ) !== -1 );
	check( '...a row that is in the rows and not among those shown is not revealed', t2.reveal( 'nope' ) === false );
	t2.focusRow( 'r01' );
	check( 'focusRow() gives the row the focus - a row that holds no control is no stop of the Tab key, and takes it all the same', findAll( m2, el => el.tagName === 'TR' && el.dataset.row === 'r01' )[0].focused === true );

	// Keep: the rows are taken without going back, and the choice of the rows that are still there stays
	const m3 = element('div');
	const t3 = w.ui.table( { mount : m3, columns : [ { key : 'name', label : 'Name', type : 'string' } ], rows : ROWS, rowKey : 'key', labels : { search : 's' }, pageSize : 25, pageSizes : [ 25, 50 ], select : { actions : [] } } );
	const box = tag( findAll( m3, el => el.tagName === 'TR' && el.dataset.row === 'r02' )[0], 'input' )[0];
	box.checked = true;
	fire( box, 'click' );
	fire( byClass( m3, 'nino-admin-table-step' )[1], 'click' );
	t3.setRows( ROWS.filter( r => r.key !== 'r30' ), { keep : true } );
	check( 'setRows( rows, { keep : true } ) stays on the page and keeps the choice of the rows that are still there', t3.state().page === 2 && t3.selected().join() === 'r02' );
	t3.setRows( ROWS.filter( r => r.key !== 'r02' ) );
	check( '...and without keep the table is at the first page and the row that is gone is no longer chosen', t3.state().page === 1 && t3.selected().length === 0 );
}

// A table of pictures pages by 25, every other by 50
{
	const w = world();
	const a = element('div');
	const b = element('div');
	w.ui.table( { mount : a, columns : [ { key : 'thumb', label : 'P', cell : 'thumb' }, { key : 'name', label : 'N' } ], rows : ROWS, rowKey : 'key', labels : {} } );
	w.ui.table( { mount : b, columns : [ { key : 'name', label : 'N' } ], rows : ROWS, rowKey : 'key', labels : {} } );
	check( 'a table with a column of pictures pages by 25, one without by 50', shown( a ).length === 25 && shown( b ).length === 50
		&& findAll( a, el => hasClass( el, 'nino-admin-table-size' ) )[0].children.map( o => o.value ).join() === '25,50,100' );
}


// --- the directory ----------------------------------------------------------------

function directoryOver( extra ) {

	const w = world();
	const mount = element('div');
	const states = [];
	const options = Object.assign( {
		mount : mount, columns : COLUMNS.slice( 0, 4 ), rowKey : 'key', rows : undefined,
		labels : { search : 'Search items', empty : 'No items yet.', noMatch : 'No item found.', filter : 'Filters' },
		category : { id : 'cat', label : 'Group', value : '', options : [ { value : '', label : 'All groups' }, { value : 'a', label : 'A' }, { value : 'b', label : 'B' } ], match : ( row, value ) => value === '' || row.group === value },
		filters : [
			{ id : 'state', label : 'State', value : '', options : [ { value : '', label : 'All states' }, { value : 'on', label : 'On' }, { value : 'off', label : 'Off' } ], match : ( row, value ) => value === '' || row.state === value },
			{ id : 'pic', label : 'Picture', value : '', options : [ { value : '', label : 'All' }, { value : 'none', label : 'None' } ], match : ( row, value ) => value === '' || row.thumb === '' },
		],
		select : { actions : [ { key : 'x', label : 'Do', run : () => {} } ] },
		onState : s => states.push( s ),
	}, extra ?? {} );

	const directory = w.ui.directory( options );

	directory.setRows( ROWS );

	return { w, mount, directory, states };
}

{
	const { mount, directory, states } = directoryOver();
	const bar = byClass( mount, 'nino-admin-table-toolbar' )[0];
	const selects = tag( bar, 'select' );

	check( 'the directory draws in one order: the category, the search, the filters, the select of the sort of a card - and, on a narrow screen, the button of the filters',
		bar.children.map( el => el.tagName+ ( el.id !== '' && el.id.indexOf('nino-admin-table-sort-') !== 0 ? '#'+ el.id : '' ) ).join() === 'SELECT#cat,INPUT,SELECT#state,SELECT#pic,SELECT,BUTTON' && bar.dataset.collapsible === 'true' );
	check( 'the table has the box to choose rows by, in the head and in every row, and the paging of 25 for a table with pictures',
		byClass( mount, 'nino-admin-table-select' ).length > 20 && ( () => { const d = directoryOver( { columns : COLUMNS.slice( 0, 4 ) } ); return shown( d.mount ).length === 25 } )() );

	const toldBefore = states.length;
	selects[0].value = 'b';
	fire( selects[0], 'change' );
	check( 'a change of the category is told as one: the state that comes is the one with the category', states.length === toldBefore + 1 && states[states.length - 1].category === 'b' );
	check( 'the category narrows the rows - by the directory, not by the caller', shown( mount ).length === 20 && directory.rows().every( row => row.group === 'b' ) );

	selects[1].value = 'off';
	fire( selects[1], 'change' );
	check( '...and a filter narrows what is left, the two together', directory.rows().every( row => row.group === 'b' && row.state === 'off' ) && directory.rows().length === 5 );

	const search = tag( bar, 'input' )[0];
	search.value = 'item 12';
	fire( search, 'input' );
	check( '...and the search narrows what is left of that', directory.rows().length === 1 && directory.rows()[0].key === 'r12' );

	check( 'what a person chose is told with every choice: the search, the selects, the sort and the page', states[states.length - 1].query === 'item 12' && states[states.length - 1].category === 'b' && states[states.length - 1].filters.state === 'off' && states[states.length - 1].filters.pic === '' );
	check( '...and is what state() answers, to be given to a directory that is drawn again',
		JSON.stringify( directory.state() ) === JSON.stringify( states[states.length - 1] ) && directory.state().sort.key === '' );

	const again = directoryOver( { state : directory.state() } );
	check( 'a directory drawn again with that state shows the same rows, with the selects, the search and the button as they were',
		again.directory.rows().length === 1 && tag( byClass( again.mount, 'nino-admin-table-toolbar' )[0], 'select' ).map( el => el.value ).join('|') === 'b|off||' && tag( byClass( again.mount, 'nino-admin-table-toolbar' )[0], 'input' )[0].value === 'item 12'
		&& byClass( again.mount, 'nino-admin-table-toolbar' )[0].dataset.open === 'true' );
}

{
	const { mount, directory } = directoryOver();
	const bar = byClass( mount, 'nino-admin-table-toolbar' )[0];

	// The choice of the rows outlives the selects that hide them
	const box = tag( findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r03' )[0], 'input' )[0];
	box.checked = true;
	fire( box, 'click' );
	tag( bar, 'select' )[0].value = 'a';
	fire( tag( bar, 'select' )[0], 'change' );
	check( 'a select that changes the rows gives up the choice of the set that was: what is chosen is always in view, so no action reaches a row a select hides', directory.selected().length === 0 && directory.rows().every( row => row.key !== 'r03' ) );
	const again = tag( findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r01' )[0], 'input' )[0];
	again.checked = true;
	fire( again, 'click' );
	fire( byClass( mount, 'nino-admin-table-step' )[1], 'click' );
	check( 'a page of the same rows keeps it', directory.selected().join() === 'r01' );
	const search2 = tag( byClass( mount, 'nino-admin-table-toolbar' )[0], 'input' )[0];
	search2.value = 'item 1';
	fire( search2, 'input' );
	check( 'a search is another set of rows and gives the choice up as well: the rule is the directory\'s', directory.selected().length === 0 );
	search2.value = '';
	fire( search2, 'input' );
	directory.clearSelection();
	check( 'clearSelection() takes it away', directory.selected().length === 0 );
	check( 'the directory answers the order of the rows to walk: the ones shown, across the pages', directory.rows().length === 40 && directory.rows()[39].key === 'r59' );

	// Reaching a row on the second page
	check( 'reveal() brings the page of a row to the screen, and focusRow() brings it and gives it the focus', directory.reveal( 'r58' ) === true && shown( mount ).indexOf( 'r58' ) !== -1 && directory.focusRow( 'r01' ) === true && findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r01' )[0].focused === true );
	check( '...a row the selects hide is not reached', directory.reveal( 'r03' ) === false && directory.focusRow( 'r03' ) === false );

	// A row that was changed
	const changed = ROWS.map( row => row.key === 'r01' ? Object.assign( {}, row, { name : 'Renamed' } ) : row );
	directory.update( changed, 'r01' );
	const tr = findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r01' )[0];
	check( 'update() takes the rows where the directory stands - same page - and the changed row is drawn anew and marked as fresh', findAll( tr, el => el.textContent === 'Renamed' ).length === 1 && hasClass( tr, 'is-fresh' ) && directory.state().page === 1 );

	// The slot of a notice, loading and failing
	directory.notice( 'This does not exist.' );
	check( 'a notice stands over the table and is taken away with an empty word', byClass( mount, 'nino-admin-notice' ).length === 1 && byClass( mount, 'nino-admin-notice' )[0].textContent === 'This does not exist.' );
	directory.notice('');
	check( '...', byClass( mount, 'nino-admin-notice' ).length === 0 );

	directory.loading( 'Items are loading ...' );
	const loading = byClass( mount, 'nino-admin-loading' )[0];
	check( 'while it loads the sentence stands in place of the table and the place says it is busy', loading.textContent === 'Items are loading ...' && byClass( mount, 'nino-admin-directory-body' )[0].attributes['aria-busy'] === 'true' && tag( mount, 'table' ).length === 0 );
	directory.setRows( ROWS );
	check( 'the rows come in as the table, with what the person chose still chosen', tag( mount, 'table' ).length === 1 && byClass( mount, 'nino-admin-directory-body' )[0].attributes['aria-busy'] === undefined && tag( byClass( mount, 'nino-admin-table-toolbar' )[0], 'select' )[0].value === 'a' );

	directory.fail( 500, null, 'Failed to load.' );
	check( 'a load that failed stands in place of the table, as the workbench says an error', byClass( mount, 'nino-admin-error' ).length === 1 && tag( mount, 'table' ).length === 0 );
}

{
	// No category, no filters: the toolbar is the search alone and has no button
	const w = world();
	const mount = element('div');
	const directory = w.ui.directory( { mount : mount, columns : [ { key : 'name', label : 'Name' } ], rowKey : 'key', labels : { search : 'Search', empty : 'none', noMatch : 'no' } } );
	directory.setRows( ROWS.slice( 0, 3 ) );
	const bar = byClass( mount, 'nino-admin-table-toolbar' )[0];
	check( 'a directory with no category and no filter draws the search, and the select of the sort of a card behind the button - and nothing else', bar.children.map( el => el.tagName ).join() === 'INPUT,SELECT,BUTTON' && byClass( bar, 'nino-admin-table-sortby' ).length === 1 );
	const unsorted = w.ui.directory( { mount : element('div'), columns : [ { key : 'name', label : 'Name', sortable : false } ], rowKey : 'key', labels : { search : 'Search' } } );
	unsorted.setRows( ROWS.slice( 0, 3 ) );
	check( '...a directory none of whose columns is sorted has the search alone, and no button for selects it has not', ( () => { const only = byClass( unsorted.el, 'nino-admin-table-toolbar' )[0]; return only.children.length === 1 && only.children[0].tagName === 'INPUT' && only.dataset.collapsible === undefined } )() );
	directory.setRows( [] );
	check( 'a directory with no rows says that there are none, in the sentence of the caller', byClass( mount, 'nino-admin-empty' )[0].textContent === 'none' );
}



// --- the cards: the rows of a directory under 40 rem, from the same columns -------------------------

const CARD_COLUMNS = [
	{ key : 'thumb', label : 'Preview', cell : 'thumb' },
	{ key : 'when', label : 'Date', cell : 'date', type : 'date' },
	{ key : 'name', label : 'Name', type : 'string', primary : true },
	{ key : 'alts', label : 'Alt text', cell : 'locales' },
	{ key : 'text', label : 'Text', cell : 'clamp', narrow : 'second' },
	{ key : 'size', label : 'Size', type : 'integer', narrow : false },
	{ key : 'state', label : '', type : 'string' },
];

function cardsOver( extra ) {

	const w = world();
	const mount = element('div');
	const opened = [];
	const options = Object.assign( {
		mount : mount, columns : CARD_COLUMNS, rowKey : 'key',
		labels : { search : 'Search', empty : 'None yet.', noMatch : 'No match.', sortBy : 'Sort by', sortNone : 'As listed', sortUp : '%s, up', sortDown : '%s, down' },
		select : { label : 'Choose the rows of the page', actions : [ { key : 'x', label : 'Do', run : () => {} } ] },
		actions : row => [ w.ui.iconButton( 'eye', 'Open '+ row.key, () => {}, { small : true } ), w.ui.iconButton( 'trash-2', 'Delete '+ row.key, () => {}, { small : true, danger : true } ) ],
		onOpen : row => opened.push( row.key ),
	}, extra ?? {} );
	const directory = w.ui.directory( options );

	directory.setRows( ROWS );

	return { w, mount, directory, opened };
}

{
	const { mount, directory, opened } = cardsOver();
	const tr = findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r01' )[0];
	const kinds = tr.children.filter( el => el.dataset.card !== undefined ).map( el => el.dataset.card );

	check( 'a row is drawn once, from the columns, and each cell says what it is in a card: the picture, the name the column names, the second line, the lines, and what is left out',
		kinds.join() === 'thumb,line,primary,line,second,omit,line' );
	check( '...a line says the word of its column; the name, the second line and a column with no word say none',
		tr.children.filter( el => el.dataset.card !== undefined ).map( el => el.dataset.label ?? '' ).join() === ',Date,,Alt text,,,' );

	const first = cardsOver( { columns : CARD_COLUMNS.map( c => Object.assign( {}, c, { primary : undefined } ) ) } );
	const plain = findAll( first.mount, el => el.tagName === 'TR' && el.dataset.row === 'r01' )[0];
	check( '...where no column says it is the name, the first that is no picture and no group of badges is', plain.children.filter( el => el.dataset.card === 'primary' ).length === 1 && plain.children.find( el => el.dataset.card === 'primary' ).textContent.indexOf('2026') !== -1 );

	check( 'the table keeps its roles - table, row, cell - as attributes, so that a screen reader that no longer takes the table for one still hears each entry as a row of cells',
		tag( mount, 'table' )[0].attributes.role === 'table' && tr.attributes.role === 'row' && tr.children.every( el => el.attributes.role === 'cell' ) && tag( mount, 'tbody' )[0].attributes.role === 'rowgroup' );

	const name = tr.children.find( el => el.dataset.card === 'primary' );
	const foot = tr.children.find( el => hasClass( el, 'nino-admin-table-actions' ) );
	check( 'the sign that the card opens ends the name, and the cell of the buttons holds the buttons alone, each with its name',
		byClass( name, 'nino-admin-row-chev' ).length === 1 && byClass( foot, 'nino-admin-row-chev' ).length === 0 && tag( foot, 'button' ).map( b => b.attributes['aria-label'] ).join() === 'Open r01,Delete r01' );

	fire( tr, 'click' );
	fire( tag( foot, 'button' )[0], 'click', { target : tag( foot, 'button' )[0] } );
	check( 'the whole card opens the row, and a press on one of its buttons is the button\'s and nothing else', opened.join() === 'r01' );

	const box = byClass( tr, 'nino-admin-table-check' )[0];
	box.checked = true;
	fire( box, 'click', { shiftKey : false } );
	check( 'the box that chooses the row stays on the card, and the bar over the cards says how many are chosen, as over the table',
		directory.selected().join() === 'r01' && byClass( mount, 'nino-admin-table-selection' ).length === 1 && hasClass( byClass( mount, 'nino-admin-table-selection' )[0], 'nino-admin-hidden' ) === false );
	check( '...and the box of the head has its words beside it, for the strip over the cards: a label that is the target as well',
		byClass( tag( mount, 'thead' )[0], 'nino-admin-table-selecttext' )[0].textContent === 'Choose the rows of the page' && byClass( tag( mount, 'thead' )[0], 'nino-admin-table-selectcell' )[0].children.some( el => hasClass( el, 'nino-admin-table-selecttext' ) ) );
}

{
	const { mount, directory } = cardsOver();
	const bar = byClass( mount, 'nino-admin-table-toolbar' )[0];
	const sortby = byClass( bar, 'nino-admin-table-sortby' )[0];

	check( 'a card has no head to sort by: the select "Sort by" has the order of the list and every column that is sorted by its head, up and down - and no picture, no states of languages, no column with no word',
		sortby.attributes['aria-label'] === 'Sort by' && sortby.children.map( o => o.value ).join() === ',when:up,when:down,name:up,name:down,text:up,text:down,size:up,size:down'
		&& sortby.children[0].textContent === 'As listed' && sortby.children[1].textContent === 'Date, up' && sortby.children[4].textContent === 'Name, down' );

	sortby.value = 'size:down';
	fire( sortby, 'change' );
	check( 'a choice of the select orders the rows as the head would, and is told as what the person chose', directory.rows()[0].key === 'r60' && directory.state().sort.key === 'size' && directory.state().sort.dir === -1 );

	fire( sorter( mount, 'name' ).children[0], 'click' );
	check( '...and a sort by a head - a wide screen - is what the select says, whoever set it', sortby.value === 'name:up' );

	sortby.value = '';
	fire( sortby, 'change' );
	check( '...the first option is the order the rows come in', directory.state().sort.key === '' && directory.rows()[0].key === 'r01' );
	check( 'the select does not narrow the list: the button of the filters does not count it and the bar is not held open by it',
		bar.dataset.collapsible === 'true' && ( () => { sortby.value = 'size:up'; fire( sortby, 'change' ); return byClass( bar, 'nino-admin-filterbar-toggle' )[0].dataset.active === '0' && bar.dataset.open === 'false' } )() );

	const again = cardsOver( { state : directory.state() } );
	check( '...and a directory drawn again with its state shows the order in the select - which is no filter: no count on the button, the bar closed', byClass( again.mount, 'nino-admin-table-sortby' )[0].value === 'size:up'
		&& byClass( again.mount, 'nino-admin-filterbar-toggle' )[0].dataset.active === '0' && byClass( again.mount, 'nino-admin-table-toolbar' )[0].dataset.open === 'false' );
}

{
	// The states are the table's: nothing yet, nothing found, loading, a failed load
	const { mount, directory } = cardsOver();
	directory.setRows( [] );
	check( 'cards with no rows say that there are none, in the sentence of the caller, and draw no card', byClass( mount, 'nino-admin-empty' )[0].textContent === 'None yet.' && hasClass( byClass( mount, 'nino-admin-table-scroll' )[0], 'nino-admin-hidden' ) );
	directory.setRows( ROWS );
	const search = findAll( mount, el => el.tagName === 'INPUT' && el.type === 'search' )[0];
	search.value = 'no such row';
	fire( search, 'input' );
	check( '...and a search that finds none says so, and the cards are gone with the rows', byClass( mount, 'nino-admin-empty' )[0].textContent === 'No match.' && shown( mount ).length === 0 );
	directory.loading( 'Loading ...' );
	check( '...loading is a sentence in place of the cards', byClass( mount, 'nino-admin-loading' )[0].textContent === 'Loading ...' && tag( mount, 'table' ).length === 0 );
}

{
	// A table that is no card is as it was drawn: no cards, no roles, no select of the sort
	const w = world();
	const mount = element('div');
	w.ui.table( { mount : mount, columns : COLUMNS, rows : ROWS, rowKey : 'key', labels : { search : 'Search' }, actions : row => [ w.ui.button( 'Edit', () => {}, { small : true } ) ], onRowClick : () => {} } );
	check( 'a table that is not asked for cards has none of it: no role of its own, no mark on its cells, no select to sort by, no class of cards',
		tag( mount, 'table' )[0].attributes.role === undefined && findAll( mount, el => el.dataset !== undefined && el.dataset.card !== undefined ).length === 0 && byClass( mount, 'nino-admin-table-sortby' ).length === 0
		&& byClass( mount, 'nino-admin-table--cards' ).length === 0 && byClass( mount, 'nino-admin-table-scroll--cards' ).length === 0 );
	const row = findAll( mount, el => el.tagName === 'TR' && el.dataset.row === 'r01' )[0];
	check( '...and the sign that its row opens stands in the cell of the buttons, as it did', byClass( row.children[row.children.length - 1], 'nino-admin-row-chev' ).length === 1 );
}

{
	// Under 40 rem the cell of the buttons of a table sticks to the right edge only while the columns before it keep their room
	const watchers = [];
	const w = world( { ResizeObserver : class { constructor( fn ) { this.fn = fn; this.gone = false; watchers.push( this ) } observe( el ) { this.seen = el } disconnect() { this.gone = true } } } );
	const mount = element('div');
	const t = w.ui.table( { mount : mount, columns : [ { key : 'name', label : 'Name' }, { key : 'size', label : 'Size', type : 'integer' } ], rows : ROWS.slice( 0, 3 ), rowKey : 'key', labels : {}, actions : () => [] } );
	const frame = byClass( mount, 'nino-admin-table-scroll' )[0];
	const heads = tag( mount, 'thead' )[0].children[0].children;
	const measure = ( width, name, actions ) => { frame.clientWidth = width; heads[0].offsetWidth = name; heads[2].offsetWidth = actions };

	measure( 353, 180, 186 );
	watchers[0].fn();
	check( 'where the first column and the buttons do not both fit in the frame the cell of the buttons rolls with the rest - 180 px and 186 px in 353 px', hasClass( frame, 'is-roll' ) === true );
	measure( 353, 148, 123 );
	watchers[0].fn();
	check( '...where they do it sticks - 148 px and 123 px in 353 px - and a frame that was drawn wider is measured again', hasClass( frame, 'is-roll' ) === false );
	measure( 0, 180, 186 );
	watchers[0].fn();
	check( '...a table that is not shown has no layout and keeps what it had', hasClass( frame, 'is-roll' ) === false );
	check( '...the frame is the one that is watched', watchers[0].seen === frame );
	// The box of the rows stands before the first column: it is part of what must have its room beside the buttons
	const boxMount = element('div');
	w.ui.table( { mount : boxMount, columns : [ { key : 'name', label : 'Name' } ], rows : ROWS.slice( 0, 3 ), rowKey : 'key', labels : {}, actions : () => [], select : { actions : [] } } );
	const boxFrame = byClass( boxMount, 'nino-admin-table-scroll' )[0];
	const boxHeads = tag( boxMount, 'thead' )[0].children[0].children;
	boxFrame.clientWidth = 300; boxHeads[0].offsetWidth = 33; boxHeads[1].offsetWidth = 148; boxHeads[2].offsetWidth = 123;
	watchers[1].fn();
	check( '...the box of the rows counts with the first column: 33 + 148 px beside 123 px do not fit in 300 px', hasClass( boxFrame, 'is-roll' ) === true );
	t.destroy();
	check( '...and a table that is taken away stops watching its frame', watchers[0].gone === true );

	const card = cardsOver();
	const cardFrame = byClass( card.mount, 'nino-admin-table-scroll' )[0];
	cardFrame.clientWidth = 353;
	tag( card.mount, 'thead' )[0].children[0].children.forEach( th => { th.offsetWidth = 200 } );
	card.directory.setRows( ROWS );
	check( 'a card never sticks, whatever its widths: where a table of the same widths would roll, the frame of the cards is not marked', hasClass( cardFrame, 'is-roll' ) === false );
}


// --- the counter of a field --------------------------------------------------------

{
	const w = world();
	const field = w.ui.textField( { label : 'Alt text', value : 'abc', maxLength : 10, counter : true } );
	const counter = byClass( field, 'nino-admin-char-counter' )[0];
	const input = tag( field, 'input' )[0];

	check( 'a field with a limit has its counter under the control, "3 / 10"', counter !== undefined && counter.textContent === '3 / 10' && field.children.indexOf( counter ) > field.children.indexOf( input ) );
	input.value = 'abcdefghij';
	fire( input, 'input' );
	check( '...it follows what is typed and is marked where the limit is reached', counter.textContent === '10 / 10' && hasClass( counter, 'is-limit' ) );
	input.value = 'ab';
	fire( input, 'input' );
	check( '...and is not marked below it', hasClass( counter, 'is-limit' ) === false );

	const area = w.ui.textareaField( { label : 'Text', value : 'x'.repeat( 810 ), counter : 800 } );
	check( 'a counter given as a number is a limit the control does not enforce: over it, it is marked', byClass( area, 'nino-admin-char-counter' )[0].textContent === '810 / 800' && hasClass( byClass( area, 'nino-admin-char-counter' )[0], 'is-limit' ) );
	check( 'a field with no limit has no counter', byClass( w.ui.textField( { label : 'x' } ), 'nino-admin-char-counter' ).length === 0 && byClass( w.ui.textField( { label : 'x', counter : true } ), 'nino-admin-char-counter' ).length === 0 );
	const emoji = w.ui.textField( { label : 'e', value : '😀😀', maxLength : 5, counter : true } );
	check( 'a character counts once, whatever it is made of', byClass( emoji, 'nino-admin-char-counter' )[0].textContent === '2 / 5' );
}


// --- the blocks a record is read with ---------------------------------------------------

{
	const w = world();
	const before = markupWrites.length;
	const hostile = '<script>alert(1)</script>\n<b>bold</b>';

	const line = w.ui.readLine( { label : 'Sender', value : hostile } );
	check( 'a line to read is a group named by its label, the value as text', line.attributes.role === 'group' && line.attributes['aria-labelledby'] === byClass( line, 'nino-admin-readline-label' )[0].id && byClass( line, 'nino-admin-readline-value' )[0].textContent === hostile && tag( line, 'script' ).length === 0 );
	check( 'a value that is empty is a quiet dash and not nothing', byClass( w.ui.readLine( { label : 'x', value : '' } ), 'nino-admin-readline-value' )[0].textContent === '–' );

	const mail = w.ui.readLine( { label : 'Mail', value : 'a@example.org', href : 'mailto:a@example.org' } );
	check( 'an address that leads to mail, a phone or the web is a link', tag( mail, 'a' )[0].href === 'mailto:a@example.org' );
	check( 'any other address is no link - a script is shown as the text it is',
		[ 'javascript:alert(1)', 'data:text/html,x', '//evil.example', 'vbscript:x' ].every( href => tag( w.ui.readLine( { label : 'x', value : 'v', href : href } ), 'a' ).length === 0 ) );

	const box = w.ui.messageBox( hostile, { label : 'Message' } );
	check( 'a message is a box of its own, set as text with its breaks, a stop of the Tab key because it rolls, and named', box.textContent === hostile && box.tabIndex === 0 && box.attributes['aria-label'] === 'Message' && box.attributes.role === 'region' && tag( box, 'b' ).length === 0 );

	const figure = w.ui.imageFrame( { src : '/images/a.jpg', alt : 'A house', caption : 'a.jpg - 12 KB' } );
	check( 'a large picture is whole in a frame, with what it says for those who cannot see it, and its line', tag( figure, 'img' )[0].src === '/images/a.jpg' && tag( figure, 'img' )[0].alt === 'A house' && byClass( figure, 'nino-admin-imageframe-caption' )[0].textContent === 'a.jpg - 12 KB' );
	const none = w.ui.imageFrame( { src : '', none : 'No image yet.' } );
	check( 'a frame with no picture says so in the caller\'s sentence', hasClass( byClass( none, 'nino-admin-imageframe-frame' )[0], 'is-none' ) && tag( none, 'img' ).length === 0 && findAll( byClass( none, 'nino-admin-imageframe-frame' )[0], el => el.textContent === 'No image yet.' ).length === 1 );
	check( 'a picture whose address is a script is none', tag( w.ui.imageFrame( { src : 'javascript:alert(1)', alt : 'x' } ), 'img' ).length === 0 );
	check( 'none of them wrote markup', markupWrites.length === before );
}

done();
