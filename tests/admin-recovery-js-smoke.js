/**
 *	Nino										A compact filesystembased php framework
 *	admin-recovery-js-smoke.js	DOM-light checks for the recovery page's script
 *													(_admin/assets/recovery.js).
 *
 *													The page is the way back in when the accounts are what
 *													is broken, so what it sends is what matters: the account
 *													a password is set for is picked from a list rather than
 *													typed, so a typo cannot name an account that is not
 *													there, and an account with full access is only asked for
 *													after the answer to a question - the server is told that
 *													it was asked.
 *
 *	Usage: node tests/admin-recovery-js-smoke.js
 */

'use strict';

const fs 		= require('fs');
const path	= require('path');
const vm 		= require('vm');

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

function source( relative ) {
	return fs.readFileSync( path.join( __dirname, '..', relative ), 'utf8' );
}


// --- an element stand-in ---------------------------------------------------

function element( tag ) {
	const values = new Set();
	const el = {
		tagName : String( tag ).toUpperCase(),
		className : '',
		textContent : '',
		type : '',
		id : '',
		value : '',
		disabled : false,
		dataset : {},
		children : [],
		listeners : {},
		appendChild : function( child ) { el.children.push( child ); return child },
		focus : function() {},
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
		classList : {
			add : function( value ) { values.add( value ) },
			remove : function( value ) { values.delete( value ) },
			contains : function( value ) { return values.has( value ) },
		},
	};
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

function fire( el, name ) {
	( el.listeners[name] || [] ).forEach( function( fn ) { fn( { preventDefault : function() {} } ) } );
}


// --- the page, as the template has it ---------------------------------------

const ids = [
	'recovery-login', 'recovery-login-msg', 'recovery-input-pw', 'recovery-tools', 'recovery-dates', 'recovery-restore-msg', 'recovery-reset', 'recovery-reset-mail', 'recovery-reset-pw',
	'recovery-reset-msg', 'recovery-reset-submit', 'recovery-create', 'recovery-create-mail', 'recovery-create-pw', 'recovery-create-repeat',
	'recovery-create-msg', 'recovery-logout',
];
const page = {};
ids.forEach( function( id ) {
	page[id] = element( id === 'recovery-reset-mail' ? 'select' : 'div' );
	page[id].id = id;
} );
page['recovery-tools'].dataset.open = '1';

const template = source('_admin/templates/page-recovery.tpl');

const requests = [];
let ready = null;
let confirmAnswer = true;
const confirms = [];

const Nino = {
	dir : '',
	events : { bindCallback : function( name, fn ) { if( name === 'ready' ) ready = fn } },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		getElementById : function( id ) { return page[id] ?? null },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = {
	Nino : Nino,
	confirm : function( message ) { confirms.push( message ); return confirmAnswer },
	location : { reload : function() {} },
};

vm.runInContext( source('_admin/assets/recovery.js'), vm.createContext( sandbox ), { filename : 'recovery.js' } );

/** Answer the request with that action, the way Nino.http.sendRequest() calls back */
function answer( action, status, body ) {
	const request = requests.slice().reverse().find( function( r ) { return r.action === action } );
	request.callback( { status : status, responseJSON : body } );
}

function sent( action ) {
	return requests.filter( function( r ) { return r.action === action } );
}

function options() {
	return page['recovery-reset-mail'].children.map( function( option ) { return option.value } );
}

console.log('Recovery page');

check( 'the template has a select, not a free text input, for the account a password is set for', /<select id="recovery-reset-mail"/.test( template ) && /id="recovery-reset-mail" type=/.test( template ) === false && template.indexOf('datalist') === -1 );
check( '...and a form of its own to create an account with full access, with a message paragraph that is a status', /id="recovery-create"/.test( template ) && /<p id="recovery-create-msg" role="status" aria-live="polite">/.test( template ) );
check( '...no inline handler anywhere', /\son[a-z]+=/.test( template ) === false );

ready();
check( 'the page asks for the backups and the accounts when the gate is open', sent('recovery/list').length === 1 );

answer( 'recovery/list', 200, { dates : [ '2026-10-02' ], users : [ 'a@example.com', 'b@example.com' ] } );
check( 'the select is filled from the list the server sent', options().join(',') === 'a@example.com,b@example.com' && page['recovery-reset-submit'].disabled === false );

// Set a password: the account is the one that is selected
page['recovery-reset-mail'].value = 'b@example.com';
page['recovery-reset-pw'].value = 'a new password';
fire( page['recovery-reset'], 'submit' );
check( 'setting a password sends the selected account', sent('recovery/reset').length === 1 && sent('recovery/reset')[0].payload.mail === 'b@example.com' && sent('recovery/reset')[0].payload.pw === 'a new password' );
answer( 'recovery/reset', 200, { mail : 'b@example.com', created : false } );
check( '...and says so, with the password field emptied', page['recovery-reset-msg'].textContent === 'Password set, every session of this account logged out.' && page['recovery-reset-pw'].value === '' );

page['recovery-reset-pw'].value = 'another password';
fire( page['recovery-reset'], 'submit' );
answer( 'recovery/reset', 404, { error : 'unknown account' } );
check( 'a refusal shows the status and the reason', page['recovery-reset-msg'].textContent === '(404) unknown account' );

// Create: only after the repeat matches and the question was answered yes
page['recovery-create-mail'].value = 'new@example.com';
page['recovery-create-pw'].value = 'a fresh password';
page['recovery-create-repeat'].value = 'a fresh passwort';
fire( page['recovery-create'], 'submit' );
check( 'a repeat that differs sends nothing and asks nothing', sent('recovery/create').length === 0 && confirms.length === 0 && page['recovery-create-msg'].textContent === 'The passwords do not match.' );

page['recovery-create-repeat'].value = 'a fresh password';
confirmAnswer = false;
fire( page['recovery-create'], 'submit' );
check( 'an account is not created when the question is answered no', confirms.length === 1 && sent('recovery/create').length === 0 );
check( '...the question names the address and says full access', confirms[0] === 'Create an account with FULL access for new@example.com?' );

confirmAnswer = true;
const listsBefore = sent('recovery/list').length;
fire( page['recovery-create'], 'submit' );
check( 'answered yes, it sends the account with confirm true', sent('recovery/create').length === 1 && sent('recovery/create')[0].payload.confirm === true
	&& sent('recovery/create')[0].payload.mail === 'new@example.com' && sent('recovery/create')[0].payload.pw === 'a fresh password' );
answer( 'recovery/create', 200, { mail : 'new@example.com', created : true } );
check( '...says it was created, with both password fields emptied', page['recovery-create-msg'].textContent === 'Account created with full access.'
	&& page['recovery-create-pw'].value === '' && page['recovery-create-repeat'].value === '' );
check( '...and asks for the list again, so the new account is there to pick', sent('recovery/list').length === listsBefore + 1 );
answer( 'recovery/list', 200, { dates : [], users : [ 'a@example.com', 'b@example.com', 'new@example.com' ] } );
check( '...which the select then offers', options().join(',') === 'a@example.com,b@example.com,new@example.com' );

fire( page['recovery-create'], 'submit' );
answer( 'recovery/create', 409, { error : 'account exists - use reset' } );
check( 'a refused create shows the status and the reason', page['recovery-create-msg'].textContent === '(409) account exists - use reset' );

// Nobody to pick
fire( page['recovery-logout'], 'click' );
requests.length = 0;
page['recovery-reset-pw'].value = 'x';
page['recovery-reset-mail'].value = '';
fire( page['recovery-reset'], 'submit' );
check( 'with no account selected nothing is sent', sent('recovery/reset').length === 0 );
ready();
answer( 'recovery/list', 200, { dates : [], users : [] } );
check( 'an empty list gives a select that says so and a button that is off', options().join(',') === '' && page['recovery-reset-mail'].children[0].textContent === 'No accounts' && page['recovery-reset-submit'].disabled === true );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
