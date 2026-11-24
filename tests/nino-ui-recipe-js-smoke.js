/**
 *	Nino										A compact filesystembased php framework
 *	nino-ui-recipe-js-smoke.js	The recipe of the guide runs: the code block of "Recipe: a panel with a
 *											directory and a record" in docs/admin-ui.md is taken out of that file and
 *											run as the panel it is - over the real router and the real registry of
 *											unsaved input of the workbench - and the directory and the record it
 *											draws are held to what the guide promises.
 *
 *											A recipe that does not run is worse than none: this is the page a panel
 *											of the catalogue or of a project is copied from.
 *
 *	Usage: node tests/nino-ui-recipe-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, source, adminPage, element, findAll, fire } = require( './harness.js' );

const hasClass = ( el, name ) => String( el.className ).split(/\s+/).indexOf( name ) !== -1;
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );
const byClass = ( root, name ) => findAll( root, el => hasClass( el, name ) );

// The block, as it stands in the guide: the first fenced js block after the heading of the recipe
const guide = source('docs/admin-ui.md');
const from = guide.indexOf('### Recipe: ');
const fence = guide.indexOf( '```js\n', from );
const block = guide.slice( fence + 6, guide.indexOf( '\n```', fence ) );

console.log('The recipe of the guide, run as a panel\n');

check( 'the guide has a recipe with a block of code', from !== -1 && fence !== -1 && block.length > 1000 );

const structure = { things : [] };
const h = adminPage( structure );
const mount = element('div');
const requests = [];

mount.id = 'things-list';
h.page.appendChild( mount );
h.show('things');
h.router.start();
h.router.set( 'things', [] );
h.Nino.http = { sendRequest : function( uri, method, callback, data ) {
	requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
} };
h.Nino.content.getText = key => key;

let rows = [];
for( let i = 1; i <= 60; i++ )
	rows.push( { key : 'thing-'+ i, name : 'Thing '+ i, size : i } );

const answer = ( status, body ) => requests[requests.length - 1].callback( { status : status, responseJSON : body } );
const dialog = () => h.dialogs.filter( d => d.open === true ).pop() ?? null;
const rowEls = () => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined );
const closeButton = () => tag( dialog(), 'button' ).find( b => b.attributes['aria-label'] === '/_admin/common/label/close' );

let ran = null;

try {
	vm.runInContext( block, h.context, { filename : 'docs/admin-ui.md (recipe)' } );
	ran = true;
} catch( error ) {
	ran = error.message;
}

check( 'the block runs as it is written and makes the panel, whose script the shell calls: showCurrent', ran === true && typeof h.admin.things === 'object' && typeof h.admin.things.showCurrent === 'function' );

h.admin.things.showCurrent();
check( 'showing the panel reads the rows from the server', requests.length === 1 && requests[0].action === 'things/list' );
answer( 200, { rows : rows } );

check( 'the directory is drawn with the rows - the first page, 50 of 60 - and the record is not open', rowEls().length === 50 && dialog() === null );
check( 'the columns are the ones of the recipe, the message column fills the room and its text is cut by an ellipsis', findAll( mount, el => el.tagName === 'TH' && el.dataset.key !== undefined ).map( th => th.dataset.key ).join() === 'name,size' && byClass( mount, 'nino-admin-table-cell-fill' ).length === 50 );

// Open
fire( rowEls()[2], 'click', { target : rowEls()[2] } );
const d = dialog();

check( 'a click on a row opens its record at the address of the entry - #things/things/<key> - the large dialog with the name, the key, the place and the form', d !== null && h.hash() === '#things/things/thing-3' && hasClass( d, 'nino-admin-dialog--large' )
	&& byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Thing 3' && byClass( d, 'nino-admin-dialog-subtitle' )[0].textContent === 'thing-3' && byClass( d, 'nino-admin-record-count' )[0].textContent.length > 0 && tag( d, 'input' ).length === 1 );
check( '...with the counter of the field, the focus in it, the destructive action at the left and "Save" at the right and no Cancel', byClass( d, 'nino-admin-char-counter' )[0].textContent === '7 / 80' && tag( d, 'input' )[0].focused === true
	&& byClass( d, 'nino-admin-actionbar' )[0].children[0].textContent === '/_admin/things/label/remove' && byClass( d, 'nino-admin-actionbar' )[0].children.filter( el => el.tagName === 'BUTTON' ).pop().textContent === '/_admin/common/label/save' && tag( d, 'button' ).every( b => b.textContent !== '/_admin/common/label/cancel' ) );

// Save
const input = tag( d, 'input' )[0];
input.value = 'Renamed';
fire( input, 'input' );
check( 'what is typed is unsaved input of the registry of the shell', h.dirty.isDirty( [ 'things' ] ) === true );
fire( tag( byClass( d, 'nino-admin-actionbar' )[0], 'button' ).pop(), 'click' );
check( 'Save sends the entry to the server', requests[requests.length - 1].action === 'things/save' && requests[requests.length - 1].payload.key === 'thing-3' && requests[requests.length - 1].payload.name === 'Renamed' );
answer( 200, { saved : true } );
check( '...and the view stays: open, at its address, saved, with the new name in the head and the row drawn anew in the directory behind it',
	dialog() === d && h.dirty.isDirty( [ 'things' ] ) === false && byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Renamed' && hasClass( rowEls()[2], 'is-fresh' ) && findAll( rowEls()[2], el => el.textContent === 'Renamed' ).length > 0 );

// Step and close: the history is one entry, the directory is not read again, the focus is on the row
const entries = h.entries.length;
fire( tag( d, 'button' ).find( b => b.attributes['aria-label'] === '/_admin/common/record/next' ), 'click' );
check( 'a step shows the next entry and writes over the address; the history has the one entry of the record', byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Thing 4' && h.hash() === '#things/things/thing-4' && h.entries.length === entries );

const asked = requests.length;
fire( closeButton(), 'click' );
check( 'the cross closes it and takes its entry off the history', dialog() === null && h.went.join() === '-1' );
h.back();
h.router.arrive();
h.admin.things.showCurrent();
check( 'the step back is answered by the record: the rows are not read again, the table is not drawn again and the row has the focus', requests.length === asked && rowEls()[3].focused === true && h.hash() === '#things' );
h.admin.things.showCurrent();
check( 'the panel shown again - entered from another - reads the rows, and keeps what it shows', requests.length === asked + 1 && requests[requests.length - 1].action === 'things/list' );
answer( 200, { rows : rows } );

// Remove
fire( rowEls()[0], 'click', { target : rowEls()[0] } );
fire( tag( byClass( dialog(), 'nino-admin-actionbar' )[0], 'button' )[0], 'click' );
const question = dialog();
check( 'what takes an entry away asks first, on the record, with the name of the entry', byClass( question, 'nino-admin-dialog-title' )[0].textContent === '/_admin/things/label/remove' && h.dialogs.filter( x => x.open ).length === 2 );
fire( tag( question, 'button' )[1], 'click' );
check( 'the answer yes sends the delete', requests[requests.length - 1].action === 'things/delete' && requests[requests.length - 1].payload.key === 'thing-1' );
answer( 200, { deleted : true } );
check( 'the entry is out of the rows, the record is gone and the focus is on the row that took its place', dialog() === null && rowEls()[0].dataset.row === 'thing-2' && rowEls()[0].focused === true );

// An address that names an entry
const reload = adminPage( structure );
const mount2 = element('div');
const requests2 = [];

mount2.id = 'things-list';
reload.page.appendChild( mount2 );
reload.show('things');
reload.router.start();
reload.Nino.http = { sendRequest : function( uri, method, callback, data ) { requests2.push( { action : data.action, callback : callback } ) } };
reload.Nino.content.getText = key => key;
reload.box.location.hash = '#things/things/thing-58';
vm.runInContext( block, reload.context );
reload.admin.things.showCurrent();
requests2[0].callback( { status : 200, responseJSON : { rows : rows } } );

const opened = reload.dialogs.filter( x => x.open ).pop();

check( 'a reload with an address that names an entry - on the second page of the rows - opens the record once the rows are there, and the page behind it is the one of the entry',
	opened !== undefined && findAll( opened, el => hasClass( el, 'nino-admin-dialog-title' ) )[0].textContent === 'Thing 58' && findAll( mount2, el => el.tagName === 'TR' && el.dataset.row === 'thing-58' ).length === 1 );

reload.box.location.hash = '#things/things/nothing';
reload.admin.things.showCurrent();
check( 'an address that names an entry that is not there leaves the directory with a notice and the address of the directory', reload.dialogs.filter( x => x.open ).length === 0 && findAll( mount2, el => hasClass( el, 'nino-admin-notice' ) ).length === 1 && reload.hash() === '#things' );

reload.box.location.hash = '#things/things/thing-3/extra';
reload.admin.things.showCurrent();
check( 'an address with a part too many is the directory\'s: no record, and the bar says the address of the directory', reload.dialogs.filter( x => x.open ).length === 0 && reload.hash() === '#things' );

// A panel of another address
const foreign = adminPage( { things : [], logs : [] } );
const mount3 = element('div');

mount3.id = 'things-list';
foreign.page.appendChild( mount3 );
foreign.show('things');
foreign.router.start();
foreign.Nino.http = { sendRequest : function( uri, method, callback ) { callback( { status : 200, responseJSON : { rows : rows } } ) } };
foreign.Nino.content.getText = key => key;
vm.runInContext( block, foreign.context );
foreign.admin.things.showCurrent();
fire( findAll( mount3, el => el.tagName === 'TR' && el.dataset.row !== undefined )[0], 'click' );
foreign.box.location.hash = '#logs';
foreign.hashchange();
check( 'a record stands over no other panel: where the address goes to another one it is closed', foreign.dialogs.filter( x => x.open ).length === 0 );
foreign.admin.things.showCurrent();
check( '...and the panel, shown with an address that is not its own, opens nothing', foreign.dialogs.filter( x => x.open ).length === 0 );

done();
