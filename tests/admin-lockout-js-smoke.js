/**
 *	Nino										A compact filesystembased php framework
 *	admin-lockout-js-smoke.js	DOM-light checks for the Login protection tab's script
 *													(_admin/Nino/Modules/Users/assets/lockout.js).
 *
 *													The tab is a form with two numbers and, below them,
 *													the accounts that are locked out with a button that
 *													lifts each lock. The lift is a request of its own and
 *													must not touch the form: a number typed and not yet
 *													saved is still there afterwards, and nothing of the
 *													locked list carries a data-key, since the save posts
 *													every data-key of the form.
 *
 *	Usage: node tests/admin-lockout-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, documentOf, element, findAll, fire } = require( './harness.js' );

// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Users/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'lockout-form';

const requests = [];

const Nino = {
	admin : {},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : Object.assign( documentOf( mount ), {
		createTextNode : function( value ) { return { tagName : '#TEXT', textContent : value, attributes : {}, dataset : {}, children : [] } },
		querySelectorAll : function( selector ) { return mount.querySelectorAll( selector.replace( '#lockout-form ', '' ) ) },
	} ),
	Nino : Nino,
};
sandbox.window = { Nino : Nino };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Users/assets/lockout.js', context );

const tab = Nino.admin.lockout;

/** Answer the last request the tab made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

const FIELDS = [
	{ key : '/nino/auth/maxtries', type : 'int', min : 1, max : 100, label : '/_admin/lockout/label/maxtries', hint : '/_admin/lockout/hint/maxtries', value : 5 },
	{ key : '/nino/auth/cooldown', type : 'int', min : 60, max : 604800, unit : 'seconds', label : '/_admin/lockout/label/cooldown', hint : '/_admin/lockout/hint/cooldown', value : 3600 },
];

function lockedBox() {
	return findAll( mount, function( el ) { return el.id === 'lockout-locked' } )[0] ?? null;
}

function rows() {
	const box = lockedBox();
	return box === null ? [] : findAll( box, function( el ) { return el.tagName === 'LI' } );
}

function numberInputs() {
	return findAll( mount, function( el ) { return el.tagName === 'INPUT' } );
}

function lockedText() {
	const box = lockedBox();
	return box === null ? '' : findAll( box, function( el ) { return el.textContent !== '' } ).map( function( el ) { return el.textContent } ).join(' | ');
}

function statusText() {
	const box = lockedBox();
	const line = box === null ? undefined : findAll( box, function( el ) { return el.attributes['aria-live'] !== undefined } )[0];
	return line === undefined ? '' : line.textContent;
}


console.log('Admin Lockout - the locked accounts');

tab.init();
check( 'the tab asks for the numbers and the locked accounts in one list', requests.length === 1 && requests[0].action === 'lockout/list' );

answer( 200, { fields : FIELDS, locked : [ { mail : 'a@example.com', until : '2026-10-05 14:30' }, { mail : 'b@example.com', until : '2026-10-05 15:00' } ] } );
check( 'a fieldset below the numbers lists every locked account with its time', lockedBox() !== null && rows().length === 2
	&& lockedText().includes( 'a@example.com' ) && lockedText().includes( 'locked until 2026-10-05 14:30' ) );
check( '...with a Lift lock button on each row', rows().every( function( row ) {
	return findAll( row, function( el ) { return el.tagName === 'BUTTON' && el.textContent === 'Lift lock' } ).length === 1;
} ) );
check( 'nothing of the locked list carries a data-key - the save posts every one of the form', findAll( lockedBox(), function( el ) {
	return el.attributes['data-key'] !== undefined || el.dataset.key !== undefined;
} ).length === 0 );
check( 'the two numbers are still the form\'s own data-key fields', mount.querySelectorAll('[data-key="/nino/auth/maxtries"]').length === 1 );

/*	A number typed and not saved. Lifting a lock is a request of its own: it
	must not rebuild the form, or the edit is gone without a word	*/
const maxtries = mount.querySelector('[data-key="/nino/auth/maxtries"]');
maxtries.value = '9';

const button = findAll( rows()[0], function( el ) { return el.tagName === 'BUTTON' } )[0];
fire( button, 'click' );
check( 'a click sends the unlock for the account of the row', requests.length === 2 && requests[1].action === 'lockout/unlock' && requests[1].payload.username === 'a@example.com' );
check( '...and switches the button off while the request is on its way', button.disabled === true );

answer( 200, { locked : [ { mail : 'b@example.com', until : '2026-10-05 15:00' } ] } );
check( 'the answer redraws the list from what the server still holds', rows().length === 1
	&& findAll( rows()[0], function( el ) { return el.textContent === 'b@example.com' } ).length === 1 );
check( '...and says which lock was lifted', statusText() === 'The lock on a@example.com has been lifted.' );
check( 'the number form was not rebuilt: what was typed is still there', mount.querySelector('[data-key="/nino/auth/maxtries"]') === maxtries && maxtries.value === '9' );

// A refusal keeps the row and says why
const second = findAll( rows()[0], function( el ) { return el.tagName === 'BUTTON' } )[0];
fire( second, 'click' );
answer( 500, { error : 'could not lift the lock' } );
check( 'a refused unlock keeps the row', rows().length === 1 && lockedText().includes( 'b@example.com' ) );
check( '...and the button is usable again', second.disabled === false );
check( '...and the status line says what happened, with the status', statusText() === '(500) could not lift the lock' );

// Last one: the empty state takes the list's place
fire( second, 'click' );
answer( 200, { locked : [] } );
check( 'with nobody left locked the list says so', rows().length === 0 && lockedText().includes( 'No account is locked.' ) );
check( '...and the numbers still were not rebuilt', mount.querySelector('[data-key="/nino/auth/maxtries"]') === maxtries && maxtries.value === '9' );

// Coming back to the tab: only the locked fieldset is fetched again
requests.length = 0;
tab.showCurrent();
check( 'showing the tab again asks for the list', requests.length === 1 && requests[0].action === 'lockout/list' );
answer( 200, { fields : FIELDS, locked : [ { mail : 'c@example.com', until : '2026-10-05 16:00' } ] } );
check( '...and redraws only the locked accounts', rows().length === 1 && lockedText().includes( 'c@example.com' )
	&& mount.querySelector('[data-key="/nino/auth/maxtries"]') === maxtries && maxtries.value === '9' );
check( 'the numbers\' inputs are the same two as before', numberInputs().length >= 2 );

check( 'the tab answers every word it says in both languages', [ 'locked/title', 'locked/empty', 'locked/until', 'label/unlock', 'msg/unlocked', 'error/unlock' ].every( function( key ) {
	return [ 'en_US', 'de_DE' ].every( function( locale ) { return fills('_admin/Nino/Modules/Users/text/'+ locale+ '.php')['/_admin/lockout/'+ key] !== undefined } );
} ) );

done();
