/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-lists-js-smoke.js	DOM-light checks for the blocks of a list: Nino.adminUi.list()
 *											and its rows, rowActions(), emptyState(), table() with its search and
 *											its column of actions, chip(), filterBar() and tabs(). Held by structure -
 *											what opens, what is a tab stop, what is refused - and by no word.
 *
 *	Usage: node tests/nino-ui-lists-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, source, element, findAll, fire, fills, documentOf } = require( './harness.js' );

const mount = element('div');
const sandbox = { console : console, document : documentOf( mount ) };
sandbox.window = sandbox;
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

function all( root, tag, className ) {
	return findAll( root, function( el ) { return el.tagName === tag.toUpperCase() && ( className === undefined || el.className.split(/\s+/).includes( className ) ) } );
}

function rowsOf( group ) {
	return all( group, 'li' );
}

// The error comes from the context the script runs in, which has its own RangeError: it is told by its name
function thrown( fn, name ) {
	try { fn() } catch( e ) { return e.name === name }
	return false;
}

console.log( 'Nino.adminUi - the blocks of a list\n' );

// --- list() ---------------------------------------------------------------------------------------------

const opened = [];
const group = adminUi.list( [
	{ key : 'a', title : 'Alpha', detail : 'the first', onOpen : function( row ) { opened.push( row.key ) } },
	{ key : 'b', title : 'Beta', href : '#beta', onOpen : function( row ) { opened.push( row.key ) } },
	{ key : 'c', title : 'Gamma', badge : 'new' },
], { title : 'Letters', level : 3 } );
const rows = rowsOf( group );

check( 'a list is a group with a heading of the section-title class and one <ul> of rows, each carrying its key',
	group.tagName === 'SECTION' && all( group, 'h3', 'nino-admin-section-title' ).length === 1 && all( group, 'ul', 'nino-admin-list' ).length === 1
	&& rows.length === 3 && rows.map( function( li ) { return li.dataset.row } ).join() === 'a,b,c' );

const target = all( rows[0], 'button', 'nino-admin-row-open' )[0];

check( 'a row that opens is a button, the first thing in its line, and it holds the title and the second line',
	target !== undefined && target.type === 'button' && target.parentNode.children[0] === target
	&& findAll( target, function( el ) { return el.tagName === 'STRONG' && el.textContent === 'Alpha' } ).length === 1
	&& findAll( target, function( el ) { return el.tagName === 'SMALL' && el.textContent === 'the first' } ).length === 1 );

fire( target, 'click' );
check( 'a press on a row opens it with the row it was made from', opened.join() === 'a' );

const link = all( rows[1], 'a', 'nino-admin-row-open' )[0];
let prevented = false;
fire( link, 'click', { preventDefault : function() { prevented = true } } );
check( 'a row with an address is a link to it, and a press hands the opening to the caller', link.href === '#beta' && opened.join() === 'a,b' && prevented === true );
check( 'a button and a link are in the tab order, which the keyboard presses with Enter',
	[ target, link ].every( function( el ) { return el.tabIndex === 0 } ) );

check( 'a row that does not open has no target and no chevron, and one that opens has a chevron the screen reader skips',
	all( rows[2], 'button' ).length === 0 && all( rows[2], 'a' ).length === 0 && all( rows[2], 'span', 'nino-admin-row-chev' ).length === 0
	&& all( rows[0], 'span', 'nino-admin-row-chev' )[0].getAttribute('aria-hidden') === 'true' );
check( 'the badge stands after the title, the row with no second line is the dense one',
	findAll( rows[2], function( el ) { return el.tagName === 'STRONG' } )[0].children.some( function( el ) { return el.className.includes('nino-admin-badge') } )
	&& rows[2].classList.contains('is-dense') === true && rows[0].classList.contains('is-dense') === false );

const style = source( '_admin/assets/style.css' );
check( 'the height of a row is the token: the row of a second line the row height, the one without the dense one',
	/\.nino-admin-list-row > \.nino-admin-row \{[^}]*min-height: var\(--nino-admin-row-height\)/s.test( style )
	&& /\.nino-admin-list-row\.is-dense > \.nino-admin-row \{[^}]*min-height: var\(--nino-admin-row-dense\)/s.test( style ) );

// --- the actions of a row ---------------------------------------------------------------------------------

const small = function( text, kind ) { return adminUi.button( text, null, { kind : kind, small : true } ) };

const acted = adminUi.list( [ { key : 'x', title : 'X', onOpen : function() {}, actions : [ small( 'Delete', 'danger' ), small( 'Copy' ), small( 'Edit', 'secondary' ) ] } ] );
const buttons = all( all( acted, 'span', 'nino-admin-row-actions' )[0], 'button' );

check( 'a row holds at most three actions and the dangerous one stands last, whatever the order it was given in',
	buttons.length === 3 && buttons[2].textContent === 'Delete' && buttons[0].textContent === 'Copy' && buttons[1].textContent === 'Edit' );
check( 'a fourth action is refused', thrown( function() { adminUi.rowActions( [ small('a'), small('b'), small('c'), small('d') ] ) }, 'RangeError' )
	&& thrown( function() { adminUi.list( [ { title : 'X', actions : [ small('a'), small('b'), small('c'), small('d') ] } ] ) }, 'RangeError' ) );
check( 'the actions stand outside the target, so a press on one is not a press on the row',
	all( all( acted, 'button', 'nino-admin-row-open' )[0], 'button' ).length === 0 );

// --- emptyState() -----------------------------------------------------------------------------------------

const lone = adminUi.emptyState( 'Nothing' );
const made = adminUi.button( 'New', null, { kind : 'primary' } );
const guided = adminUi.emptyState( 'Nothing', made );

check( 'an empty state is a sentence, and with a next step the sentence and its button',
	lone.className === 'nino-admin-empty' && lone.textContent === 'Nothing' && guided.className.includes('nino-admin-empty') && guided.children.length === 2 && guided.children[1] === made );

// --- chip() and filterBar() ---------------------------------------------------------------------------------

const toggled = [];
const chip = adminUi.chip( 'Empty', { onToggle : function( on ) { toggled.push( on ) } } );

check( 'a chip is a button that says whether it is pressed, and a press turns it over and tells the caller',
	chip.tagName === 'BUTTON' && chip.type === 'button' && chip.getAttribute('aria-pressed') === 'false' );
fire( chip, 'click' );
check( '...on, and off again', chip.getAttribute('aria-pressed') === 'true' && ( fire( chip, 'click' ), chip.getAttribute('aria-pressed') === 'false' ) && toggled.join() === 'true,false' );

const typed = [];
const bar = adminUi.filterBar( { search : { value : 'x', label : 'Search', onInput : function( value ) { typed.push( value ) } }, items : [ chip ] } );

check( 'a filter bar is a search with a field that has a name, and the chips after it',
	bar.el.getAttribute('role') === 'search' && bar.input.type === 'search' && bar.input.value === 'x' && bar.input.getAttribute('aria-label') === 'Search' && bar.el.children[1] === chip );
bar.input.value = 'ab';
fire( bar.input, 'input' );
check( '...and typing is told to the caller', typed.join() === 'ab' );

// --- tabs() ---------------------------------------------------------------------------------------------------

const panes = [ element('div'), element('div'), element('div') ];
const picked = [];
const strip = adminUi.tabs( [ { key : 'one', label : 'One', panel : panes[0] }, { key : 'two', label : 'Two', panel : panes[1] }, { key : 'three', label : 'Three', panel : panes[2] } ], { onSelect : function( key ) { picked.push( key ) } } );
const tabs = all( strip.el, 'button' );

check( 'tabs are a tablist of tabs, the first is selected and the one stop of the Tab key',
	strip.el.getAttribute('role') === 'tablist' && tabs.every( function( tab ) { return tab.getAttribute('role') === 'tab' } )
	&& tabs.map( function( tab ) { return tab.getAttribute('aria-selected') } ).join() === 'true,false,false' && tabs.map( function( tab ) { return tab.tabIndex } ).join() === '0,-1,-1' );
check( 'each tab names its pane and the other panes are hidden',
	panes.every( function( pane, at ) { return pane.getAttribute('role') === 'tabpanel' && pane.getAttribute('aria-labelledby') === tabs[at].id && tabs[at].getAttribute('aria-controls') === pane.id } )
	&& panes.map( function( pane ) { return pane.hidden } ).join() === 'false,true,true' );

tabs[0].focus();
fire( tabs[0], 'keydown', { key : 'ArrowRight' } );
check( 'the right arrow selects the next tab and moves the focus to it', picked.join() === 'two' && tabs[1].focused === true && tabs[1].getAttribute('aria-selected') === 'true' && panes[1].hidden === false && panes[0].hidden === true );
fire( tabs[1], 'keydown', { key : 'ArrowLeft' } );
fire( tabs[0], 'keydown', { key : 'ArrowLeft' } );
check( 'the left arrow goes back, and from the first tab it wraps to the last', picked.join() === 'two,one,three' && tabs[2].getAttribute('aria-selected') === 'true' );
check( 'a strip wraps into a second row where it does not fit, and never scrolls sideways', strip.el.classList.contains('nino-admin-tabs--wrap') === true
	&& /\.nino-admin-tabs--wrap \{[^}]*flex-wrap: wrap;/s.test( style ) );

// --- table(): the search, the column of actions, the empty answer ------------------------------------------------

const table = element('div');
const pressed = [];
const clicked = [];

adminUi.table( {
	mount : table,
	search : true,
	rowKey : 'key',
	columns : [ { key : 'name', label : 'Name', type : 'string' }, { key : 'version', label : 'Version', type : 'string' } ],
	rows : [ { key : 'a', name : 'Alpha', version : '1.0' }, { key : 'b', name : 'Beta', version : '2.0' }, { key : 'c', name : 'Gamma', version : '3.0' } ],
	labels : { search : 'Search', empty : 'Nothing yet', noMatch : 'No hit' },
	actions : function( row ) { return [ adminUi.button( 'Delete', function() { pressed.push( row.key ) }, { kind : 'danger', small : true } ), adminUi.button( 'Edit', null, { small : true } ) ] },
	onRowClick : function( row ) { clicked.push( row.key ) },
} );

const body = function() { return all( table, 'tbody' )[0].children };
const field = all( table, 'input' )[0];
const empty = all( table, 'p', 'nino-admin-empty' )[0];

check( 'a table with a search has the field over the full width, and the rows under it', all( table, 'div', 'nino-admin-table-toolbar--wide' ).length === 1 && field.type === 'search' && body().length === 3 );
check( 'the column of the actions has no head and no sort button, the labelled ones have both',
	all( table, 'th' ).length === 3 && all( table, 'th' )[2].textContent === '' && all( all( table, 'th' )[2], 'button' ).length === 0 && all( table, 'button', 'nino-admin-table-sort' ).length === 2 );
check( 'every row has its actions in its last cell, the dangerous one last', body().every( function( tr ) {
	const cell = tr.children[tr.children.length - 1];
	return cell.className.includes('nino-admin-table-actions') && all( cell, 'button' ).length === 2 && all( cell, 'button' )[1].textContent === 'Delete';
} ) );

field.value = 'bet';
fire( field, 'input' );
check( 'the search keeps the rows that match and draws only those, with no request', body().length === 1 && body()[0].dataset.row === 'b' );

field.value = 'zzz';
fire( field, 'input' );
check( 'a search that finds nothing shows the empty state of the table with the word for it, and no rows',
	body().length === 0 && empty.textContent === 'No hit' && empty.classList.contains('nino-admin-hidden') === false );

field.value = '';
fire( field, 'input' );
const first = body()[0];
all( first, 'button' )[1].dispatchEvent( { type : 'click', bubbles : true } );
first.dispatchEvent( { type : 'click', bubbles : true } );
check( 'a press on the row opens it, a press on a button of the row does not', clicked.join() === 'a' );

// --- table(): more text to search, a search that is given and told, the chevron of a row that opens

const second = element('div');
const typedInto = [];

adminUi.table( {
	mount : second, search : true, rowKey : 'key', query : 'hidden',
	columns : [ { key : 'name', label : 'Name', type : 'string' } ],
	rows : [ { key : 'a', name : 'Alpha', note : 'a hidden word' }, { key : 'b', name : 'Beta', note : '' } ],
	labels : { search : 'Search', empty : 'None', noMatch : 'No hit' },
	searchText : function( row ) { return row.note },
	onQuery : function( query ) { typedInto.push( query ) },
	onRowClick : function() {},
} );

const body2 = function() { return all( second, 'tbody' )[0].children };
const field2 = all( second, 'input' )[0];

check( 'a table draws with the search it is given, and reads what searchText says of a row beside its columns', field2.value === 'hidden' && body2().length === 1 && body2()[0].dataset.row === 'a' );
field2.value = 'bet';
fire( field2, 'input' );
check( 'what is typed is told to the caller, so that a table drawn again can be given it back', typedInto.join() === 'bet' && body2().length === 1 && body2()[0].dataset.row === 'b' );
check( 'a row that opens has the chevron the screen reader skips, in the last cell',
	all( body2()[0], 'span', 'nino-admin-row-chev' ).length === 1 && all( body2()[0], 'span', 'nino-admin-row-chev' )[0].getAttribute('aria-hidden') === 'true' );

// --- cards() --------------------------------------------------------------------------------------------

console.log( '\nNino.adminUi.cards - things whose look is the content\n' );

{
	const picture = element('div');
	const open = adminUi.button( 'Edit', null, { small : true } );
	const copy = adminUi.button( 'Duplicate', null, { small : true } );
	const cards = adminUi.cards( [
		{ key : 'nino', title : 'Nino with a name that is much too long for a line', picture : picture, badges : [ { text : 'Active', state : 'ok' }, 'Package' ], actions : [ open, copy ] },
		{ key : 'plain', title : 'Plain' },
	], { label : 'Themes' } );
	const items = all( cards, 'li' );
	const first = all( items[0], 'article' )[0];
	const head = all( first, 'div', 'nino-admin-cards-head' )[0];

	check( 'cards are a named list with one card for each item, a card of the system carrying its key', cards.tagName === 'UL' && cards.getAttribute('aria-label') === 'Themes' && items.length === 2 && first.className.split(/\s+/).includes('nino-admin-card') && first.dataset.key === 'nino' );
	check( 'a card is the picture, then ONE line of the name and its badges, then the line of buttons',
		first.children.map( el => el.className ).join() === 'nino-admin-cards-picture,nino-admin-cards-head,nino-admin-cards-actions' && first.children[0].children[0] === picture
		&& head.children[0].tagName === 'H3' && head.children.slice( 1 ).map( el => el.textContent ).join() === 'Active,Package' && head.children[1].dataset.state === 'ok' && first.children[2].children.join() === [ open, copy ].join() );
	check( '...the whole name is in the title of the heading, which the line cuts', head.children[0].title === 'Nino with a name that is much too long for a line' );
	check( 'a card without a picture, badges or buttons has none of those parts', all( items[1], 'article' )[0].children.map( el => el.className ).join() === 'nino-admin-cards-head' );
	check( 'a card with more than three buttons is refused, and a button that is cut is whole in its own title', thrown( () => adminUi.cards( [ { title : 'x', actions : [ 1, 2, 3, 4 ].map( () => adminUi.button( 'x' ) ) } ] ), 'RangeError' ) && open.title === 'Edit' );

	const sheet = source( '_admin/assets/style.css' ).replace( /\/\*[\s\S]*?\*\//g, '' );
	const rule = ( selector ) => ( sheet.split('}').map( text => text.split('{') ).find( parts => parts.length === 2 && parts[0].trim() === selector ) ?? [ '', '' ] )[1];

	check( 'the head of a card does not wrap: the name has the half of the line at least and is cut, the badges give way first - cut or gone, never the name',
		/flex-wrap:\s*nowrap/.test( rule(':where(.nino-admin) .nino-admin-cards-head') ) && /white-space:\s*nowrap/.test( rule(':where(.nino-admin) .nino-admin-cards-title') ) && /text-overflow:\s*ellipsis/.test( rule(':where(.nino-admin) .nino-admin-cards-title') )
		&& /min-width:\s*50%/.test( rule(':where(.nino-admin) .nino-admin-cards-title') ) && /flex:\s*0 1 auto/.test( rule(':where(.nino-admin) .nino-admin-cards-head > .nino-admin-badge') ) && /min-width:\s*0/.test( rule(':where(.nino-admin) .nino-admin-cards-head > .nino-admin-badge') ) );
	check( '...the whole name shows while the pointer or the focus is in the card, which the keyboard reaches by its buttons',
		/white-space:\s*normal/.test( rule(':where(.nino-admin) .nino-admin-cards-card:is(:hover, :focus-within) .nino-admin-cards-title') ) );
	check( 'the buttons stand in one line that stays in the card: they share its width and shrink, none is wider than its room; every card is as high as the highest',
		/flex-wrap:\s*nowrap/.test( rule(':where(.nino-admin) .nino-admin-cards-actions') ) && /flex:\s*1 1 auto/.test( rule(':where(.nino-admin) .nino-admin-cards-actions > button') ) && /min-width:\s*0/.test( rule(':where(.nino-admin) .nino-admin-cards-actions > button') )
		&& /grid-auto-rows:\s*1fr/.test( rule(':where(.nino-admin) .nino-admin-cards') ) );
}

done();
