/**
 *	Nino									A compact filesystembased php framework
 *	install-accounts-js-smoke.js	DOM-free checks for the install wizard's
 *									Accounts step: the password is asked for twice
 *									and never posted when the two disagree.
 *
 *	Usage: node tests/install-accounts-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
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

const elements = {
	'accounts-add-mail' : { value : '' },
	'accounts-add-pw' : { value : '' },
	'accounts-add-pw2' : { value : '' },
	'accounts-add-msg' : { textContent : '' },
	'accounts-list' : { innerHTML : '', children : [], appendChild : function( child ) { this.children.push( child ) } },
};
const csrfField = { value : 'token-the-page-was-rendered-with' };

const sandbox = {
	console : console,
	document : {
		documentElement : null,
		body : null,
		getElementById : function( id ) { return elements[id] ?? null },
		querySelector : function( selector ) { return selector === 'input[name="_csrf"]' ? csrfField : null },
		createElement : function() { return { className : '', textContent : '', appendChild : function() {} } },
	},
};
sandbox.window = sandbox;
sandbox.Nino = {
	install : {},
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/install/assets/accounts.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'accounts.js' }
);

const accounts = sandbox.Nino.install.accounts;

// What the step posts, and what it answers
let calls = [];
let answer = { status : 200, response : { users : [ 'admin@example.com' ] } };
sandbox.Nino.install.apiCall = function( action, payload, callback ) {
	calls.push( { action : action, payload : payload } );
	callback( answer.status, answer.response );
};

const submit = function( mail, pw, pw2 ) {
	calls = [];
	elements['accounts-add-mail'].value = mail;
	elements['accounts-add-pw'].value = pw;
	elements['accounts-add-pw2'].value = pw2;
	accounts._create( { preventDefault : function() {} } );
};

submit( 'admin@example.com', 'a-long-enough-password', 'a-long-enough-passwore' );
check( 'a repeat that differs posts nothing', calls.length === 0 );
check( '...and says so, in the words the Finish step uses', elements['accounts-add-msg'].textContent === 'Passwords do not match.' );
check( '...and keeps what was typed, so the typo can be fixed', elements['accounts-add-pw'].value === 'a-long-enough-password' && elements['accounts-add-mail'].value === 'admin@example.com' );

submit( 'admin@example.com', 'a-long-enough-password', 'a-long-enough-password' );
check( 'a matching repeat makes exactly one accounts/create call', calls.length === 1 && calls[0].action === 'accounts/create' );
check( '...whose payload is still just the mail and the password', JSON.stringify( calls[0].payload ) === JSON.stringify( { mail : 'admin@example.com', pw : 'a-long-enough-password' } ) );
check( '...and after the 200 all three fields are empty', elements['accounts-add-mail'].value === '' && elements['accounts-add-pw'].value === '' && elements['accounts-add-pw2'].value === '' );
check( '...with the confirmation shown', elements['accounts-add-msg'].textContent === 'Created.' );

answer = { status : 400, response : { error : 'password must be at least 8 characters' } };
submit( 'admin@example.com', 'short', 'short' );
check( 'a refusal by the server keeps the typed values', elements['accounts-add-pw'].value === 'short' && elements['accounts-add-pw2'].value === 'short' );
check( '...and shows the server\'s reason with its status', elements['accounts-add-msg'].textContent === '(400) password must be at least 8 characters' );

// The server signs the account in as it creates it, which rotates the
// session's csrf token: the page's [csrf] field has to carry the new one,
// or every request after this one is refused
answer = { status : 200, response : { users : [ 'admin@example.com' ], csrf : 'the-rotated-token' } };
submit( 'admin@example.com', 'a-long-enough-password', 'a-long-enough-password' );
check( 'a created account hands the page the rotated csrf token', csrfField.value === 'the-rotated-token' );

answer = { status : 200, response : { users : [ 'admin@example.com' ] } };
submit( 'admin@example.com', 'a-long-enough-password', 'a-long-enough-password' );
check( 'an answer without one leaves the field as it is', csrfField.value === 'the-rotated-token' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
