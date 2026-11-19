/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-menu-js-smoke.js	DOM-light checks for Nino.adminUi.menuButton(), the
 *								menu of the workbench (WAI-ARIA "Menu Button"), and
 *								for the row of a table that has more actions than it
 *								shows: the first two stand, the rest are in the menu.
 *
 *								Pinned: the roles and states of the trigger and the list,
 *								the keys that open it, walk it and close it with the focus
 *								given back, a press beside it, one open at a time, what
 *								takes something away last and in red, a disabled item that
 *								is read and not pressed, and that a row with up to three
 *								actions is as it was.
 *
 *	Usage: node tests/nino-ui-menu-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, documentOf } = require( './harness.js' );

const page = element('div');
const doc = documentOf( page );
const listeners = {};

doc.createElement = function( tag ) {
	const created = element( tag );
	created.getBoundingClientRect = function() { return { top : 100, bottom : 124, left : 500, right : 524, width : 180, height : 90 } };
	return created;
};
doc.addEventListener = function( name, fn ) { ( listeners[name] = listeners[name] || [] ).push( fn ) };
doc.removeEventListener = function( name, fn ) { listeners[name] = ( listeners[name] || [] ).filter( held => held !== fn ) };

const sandbox = { console : console, document : doc };
sandbox.window = sandbox;
sandbox.innerHeight = 800;
sandbox.addEventListener = function() {};
sandbox.removeEventListener = function() {};
sandbox.Nino = { content : { getText : function( key ) { return key } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );

const adminUi = sandbox.Nino.adminUi;
const key = ( node, name ) => { const event = { prevented : false }; node.dispatchEvent( { type : 'keydown', bubbles : true, key : name, preventDefault : function() { event.prevented = true }, stopPropagation : function() {} } ); return event };
const items = menu => findAll( menu.list, el => el.tagName === 'BUTTON' );
const attempt = fn => { try { return fn() } catch( error ) { return false } };
const thrown = fn => { try { fn() } catch( error ) { return error.name } return null };

console.log( 'Nino.adminUi.menuButton - the menu\n' );

const pressed = [];
const menu = adminUi.menuButton( { label : 'More actions', items : [
	{ label : 'Remove', danger : true, onClick : function() { pressed.push( 'remove' ) } },
	{ label : 'Copy', onClick : function() { pressed.push( 'copy' ) } },
	{ label : 'Locked', disabled : true, onClick : function() { pressed.push( 'locked' ) } },
	{ label : 'Rename', onClick : function() { pressed.push( 'rename' ) } },
] } );
page.appendChild( menu.el );

check( 'the trigger is a button that names the menu, has a name and says it is closed; the list is a menu of menuitems, hidden',
	menu.button.getAttribute('aria-haspopup') === 'menu' && menu.button.getAttribute('aria-expanded') === 'false' && menu.button.getAttribute('aria-label') === 'More actions' && menu.button.getAttribute('aria-controls') === menu.list.id
	&& menu.list.getAttribute('role') === 'menu' && menu.list.hidden === true && items( menu ).every( item => item.getAttribute('role') === 'menuitem' && item.tabIndex === -1 ) );
check( 'what takes something away is last, whatever the order it was given in, and red; the others keep their order',
	items( menu ).map( item => item.textContent ).join() === 'Copy,Locked,Rename,Remove' && items( menu )[3].className.split(' ').includes('nino-admin-menu-item--danger') === true );
check( 'a menu without a name for its button is refused', thrown( () => adminUi.menuButton( { label : ' ', items : [] } ) ) === 'TypeError' );

menu.button.click();
check( 'a press opens it on the first item and says so', menu.isOpen() === true && menu.button.getAttribute('aria-expanded') === 'true' && items( menu )[0].focused === true );

key( items( menu )[0], 'ArrowDown' );
check( 'Down and Up walk the items and wrap round', items( menu )[1].focused === true && ( key( items( menu )[1], 'ArrowUp' ), key( items( menu )[0], 'ArrowUp' ), items( menu )[3].focused === true ) && ( key( items( menu )[3], 'ArrowDown' ), items( menu )[0].focused === true ) );
key( items( menu )[0], 'End' );
const atEnd = items( menu )[3].focused === true;
key( items( menu )[3], 'Home' );
check( 'Home and End go to the ends', atEnd === true && items( menu )[0].focused === true );

const escape = key( items( menu )[0], 'Escape' );
check( 'Escape closes it, is not passed on, and gives the focus back to the trigger', menu.isOpen() === false && menu.button.focused === true && escape.prevented === true && menu.button.getAttribute('aria-expanded') === 'false' );

key( menu.button, 'ArrowUp' );
check( 'the Up arrow on the trigger opens it on the last item, the Down arrow on the first', menu.isOpen() === true && items( menu )[3].focused === true && ( menu.close( false ), key( menu.button, 'ArrowDown' ), items( menu )[0].focused === true ) );

menu.close( false );
menu.open();
key( items( menu )[0], 'r' );
const onRename = items( menu )[2].focused === true;
key( items( menu )[2], 'R' );
const onRemove = items( menu )[3].focused === true;
key( items( menu )[3], 'c' );
check( 'a letter goes to the next item that begins with it, whatever its case, round the list, and a letter nothing begins with leaves the focus where it is', onRename === true && onRemove === true && items( menu )[0].focused === true && ( key( items( menu )[0], 'z' ), items( menu )[0].focused === true ) );
menu.close( false );
menu.open();
key( items( menu )[0], 'Tab' );
check( 'Tab closes it and leaves the focus to the Tab key', menu.isOpen() === false );

menu.open();
items( menu )[1].click();
check( 'a disabled item is read and not pressed: it says aria-disabled and does nothing, and the menu stays', items( menu )[1].getAttribute('aria-disabled') === 'true' && pressed.length === 0 && menu.isOpen() === true );

items( menu )[2].click();
check( 'a choice closes it, gives the focus back to the trigger and calls the item', menu.isOpen() === false && menu.button.focused === true && pressed.join() === 'rename' );

menu.open();
( listeners.click || [] ).forEach( fn => fn( { target : element('div') } ) );
check( 'a press beside it closes it - and a press inside it does not', menu.isOpen() === false && ( menu.open(), ( listeners.click || [] ).forEach( fn => fn( { target : items( menu )[0] } ) ), menu.isOpen() === true ) );

const other = adminUi.menuButton( { label : 'Other', items : [ { label : 'One' } ] } );
page.appendChild( other.el );
other.open();
check( 'one menu is open at a time: another that opens closes the first', menu.isOpen() === false && other.isOpen() === true );
other.close( false );

// --- the row of a table ------------------------------------------------------------

const button = ( name, danger ) => adminUi.iconButton( 'x', name, function() { pressed.push( name ) }, { small : true, danger : danger === true } );
const spans = ( row ) => findAll( row, el => String( el.className ).split(' ').includes('nino-admin-menu') );
const row3 = adminUi._rowActionsMenu( [ button('a'), button('b'), button('c', true) ] );

check( 'a row with up to three actions is as it was: three buttons, no menu', spans( row3 ).length === 0 && findAll( row3, el => el.tagName === 'BUTTON' ).length === 3 );

const row5 = attempt( () => adminUi._rowActionsMenu( [ button('a'), button('b'), button('c'), button('d', true), button('e') ] ) );
const row5Menu = row5 === false ? element('div') : spans( row5 )[0];
const row5Items = findAll( row5Menu, el => el.getAttribute('role') === 'menuitem' );

check( 'with more the first two stand and the rest are in a menu named "More actions" - the one that takes something away last - and an item presses the button it stands for',
	row5 !== false && row5.children.slice( 0, 2 ).map( el => el.getAttribute('aria-label') ).join() === 'a,b' && row5.children[2] === row5Menu && row5Items.map( el => el.textContent ).join() === 'c,e,d'
	&& findAll( row5Menu, el => el.getAttribute('aria-haspopup') === 'menu' ).length === 1 && ( row5Items[2].click(), pressed[pressed.length - 1] === 'd' ) );
check( 'a table that says two stand inline puts the third in the menu as well; a number outside 2 to 3 is the one nearest',
	spans( adminUi._rowActionsMenu( [ button('a'), button('b'), button('c') ], 2 ) ).length === 1 && spans( adminUi._rowActionsMenu( [ button('a'), button('b'), button('c') ], 9 ) ).length === 0 && spans( adminUi._rowActionsMenu( [ button('a'), button('b'), button('c') ], 0 ) ).length === 0 );

done();
