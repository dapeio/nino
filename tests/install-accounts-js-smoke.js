/**
 *	Nino									A compact filesystembased php framework
 *	install-accounts-js-smoke.js	DOM-light checks for the install wizard's
 *									Accounts step: the password is asked for twice and
 *									never posted when the two disagree, what is wrong
 *									is said at the field it is about - never in a line
 *									over the form and never by the browser - and the
 *									focus goes there; what is typed when Next is
 *									pressed is the account that is meant.
 *
 *	Usage: node tests/install-accounts-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, load, element, documentOf, findAll, fire } = require( './harness.js' );

const root = element('div');
const wrap = element('div');
wrap.id = 'install-page-wrap';
wrap.dataset.minPassword = '8';
const content = element('div');
content.id = 'accounts-content';
const csrfField = element('input');
csrfField.setAttribute( 'name', '_csrf' );
csrfField.value = 'token-the-page-was-rendered-with';
wrap.appendChild( content );
root.appendChild( wrap );
root.appendChild( csrfField );

const doc = documentOf( root );
doc.documentElement = element('html');
doc.body = element('body');
doc.addEventListener = function() {};

const Nino = {
	dir : '',
	events : { bindCallback : function() {} },
	content : { text : {}, getText : function( key ) { return Nino.content.text[key] || '' } },
};
const sandbox = { console : console, document : doc, Nino : Nino };
sandbox.window = { Nino : Nino, matchMedia : function() { return { matches : false } }, setTimeout : function() { return 0 }, clearTimeout : function() {} };
const context = vm.createContext( sandbox );

[ '_admin/assets/Nino.admin.js', '_admin/assets/pagetext.js', '_admin/install/assets/text.js' ].forEach( file => load( file, context ) );

// The shell's part the step uses: the line of the bar, the way a request is made, the length a password has
const said = [];
Nino.install = {
	t : function( key, ...params ) { return Nino.pageText.t( key, ...params ) },
	minPassword : function() { return 8 },
	failure : function( status, response ) { return Nino.pageText.failure( status, response, 8 ) },
	say : function( words, error ) { said.push( [ words, error === true ] ) },
};
load( '_admin/install/assets/accounts.js', context );

const accounts = Nino.install.accounts;
Nino.pageText.onChange( function() { accounts.retext() } );
const text = Nino.pageText;
const en = text._tables.en;
const de = text._tables.de;

// What the step posts, and what it answers
let calls = [];
let answer = { status : 200, response : { users : [ 'admin@example.com' ], user : 'admin@example.com' } };
Nino.install.apiCall = function( action, payload, callback ) {
	calls.push( { action : action, payload : payload } );
	callback( answer.status, answer.response );
};

const remembered = [];
Nino.install.session = { remember : mail => remembered.push( mail ) };
const byId = id => findAll( root, el => el.id === id )[0];
const errors = () => findAll( content, el => el.className === 'nino-admin-field-error' ).map( el => el.textContent );
const typed = ( mail, pw, pw2, current = 'the-current-password' ) => {
	byId('accounts-add-current').value = current;
	byId('accounts-add-mail').value = mail;
	byId('accounts-add-pw').value = pw;
	byId('accounts-add-pw2').value = pw2;
};
const create = () => { calls = []; said.length = 0; fire( byId('accounts-add-submit'), 'click' ) };

text.set('en');
accounts.showCurrent();

check( 'the step draws a form of the blocks of the workbench: novalidate, three required fields with a star, the password with its (?), and one button of the second kind - the one main button is the bar\'s',
	byId('accounts-add-form').noValidate === true && [ 'accounts-add-mail', 'accounts-add-pw', 'accounts-add-pw2' ].every( id => byId(id).getAttribute('aria-required') === 'true' )
	&& byId('accounts-add-pw').type === 'password' && byId('accounts-add-mail').type === 'email' && findAll( content, el => el.className === 'nino-admin-help' ).length >= 2
	&& findAll( content, el => el.tagName === 'BUTTON' && el.className.includes('nino-admin-btn-primary') ).length === 0 && byId('accounts-add-submit').className.includes('nino-admin-btn-secondary') );
check( '...the list of the accounts is asked for and drawn with the list of the workbench', calls.length === 1 && calls[0].action === 'accounts/list' && findAll( content, el => el.dataset.row === 'admin@example.com' ).length === 1 );

typed( 'admin@example.com', 'a-long-enough-password', 'a-long-enough-passwore' );
create();
check( 'a repeat that differs posts nothing', calls.length === 0 );
check( '...and says so at the repeat, in the one wording of the workbench for what is not valid, and the focus is there', errors().join() === en['/_admin/install/error/mismatch'] && byId('accounts-add-pw2').getAttribute('aria-invalid') === 'true' && byId('accounts-add-pw2').focused === true );
check( '...the line of the bar says that there is something to fix, as an error, and it is no sentence of the server', said[said.length - 1][1] === true && said[said.length - 1][0] === en['/_admin/install/error/fields'] );
check( '...and keeps what was typed, so the typo can be fixed', byId('accounts-add-pw').value === 'a-long-enough-password' && byId('accounts-add-mail').value === 'admin@example.com' );

typed( '', '', '', '' );
create();
check( 'an empty form is said at every field that is empty - four where an account exists, the current password is asked then - in the wording of the workbench, the first of them has the focus, and nothing is posted', calls.length === 0 && errors().length === 4 && byId('accounts-add-mail').focused === true
	&& errors()[0] === en['/_admin/common/error/missing'].replace( '%s', en['/_admin/install/accounts/mail'] ) );

typed( 'admin@example.com', 'short', 'short' );
create();
check( 'a password that is shorter than the length the page states is said at its field, with the length, before anything is sent', calls.length === 0 && errors().join() === en['/_admin/install/error/password-short'].replace( '%s', '8' ) && byId('accounts-add-pw').focused === true );

typed( 'admin@example.com', 'a-long-enough-password', 'a-long-enough-password' );
create();
check( 'a matching repeat makes exactly one accounts/create call', calls.length === 1 && calls[0].action === 'accounts/create' );
check( '...whose payload is the mail, the password and the current one, which the server asks of an account that changes', JSON.stringify( calls[0].payload ) === JSON.stringify( { mail : 'admin@example.com', pw : 'a-long-enough-password', currentPassword : 'the-current-password' } ) );
check( '...where an account exists the form says it changes that account - its heading and its button - and asks for the current password, which is not asked before the first account',
	byId('accounts-add-current').hidden === false && byId('accounts-add-submit').textContent === en['/_admin/install/accounts/change'] && findAll( content, el => el.tagName === 'LEGEND' )[0].firstChild.textContent === en['/_admin/install/accounts/title-own'] );
check( '...and after the 200 all three fields are empty, the list shows the account and the form is ready for the next', byId('accounts-add-mail').value === '' && byId('accounts-add-pw').value === '' && byId('accounts-add-pw2').value === '' && byId('accounts-add-mail').focused === true && errors().length === 0 );
check( '...and the account the server names is the account of the page from then on, which is what keeps a request that was refused from being sent as another one (see session.js)', remembered.join() === 'admin@example.com' );
check( '...with the confirmation said as a toast', findAll( doc.body, el => el.textContent === en['/_admin/install/accounts/created-msg'] ).length >= 1 );

answer = { status : 400, response : { error : 'invalid email address', code : 'install_mail' } };
typed( 'admin@example.com', 'a-long-enough-password', 'a-long-enough-password' );
create();
check( 'a refusal of the server about the address is said at the address, in the words of the table for its code - never the English of the server', errors().join() === en['/_admin/page/error/install-mail'] && byId('accounts-add-mail').getAttribute('aria-invalid') === 'true' && byId('accounts-add-mail').focused === true );
check( '...and keeps the typed values', byId('accounts-add-pw').value === 'a-long-enough-password' && byId('accounts-add-pw2').value === 'a-long-enough-password' );
answer = { status : 500, response : { error : 'English of the server' } };
create();
check( 'a refusal that belongs to no field is said in the line of the bar, as an error, with the status, and is never the English of the server', said[said.length - 1][1] === true && said[said.length - 1][0] === en['/_admin/page/error/failed'].replace( '%s', '500' ) && errors().length === 0 );

// The server signs the account in as it creates it, which rotates the
// session's csrf token: the page's [csrf] field has to carry the new one,
// or every request after this one is refused
answer = { status : 200, response : { users : [ 'admin@example.com' ], csrf : 'the-rotated-token' } };
typed( 'admin@example.com', 'a-long-enough-password', 'a-long-enough-password' );
create();
check( 'a created account hands the page the rotated csrf token', csrfField.value === 'the-rotated-token' );

answer = { status : 200, response : { users : [ 'admin@example.com' ] } };
typed( 'admin@example.com', 'a-long-enough-password', 'a-long-enough-password' );
create();
check( 'an answer without one leaves the field as it is', csrfField.value === 'the-rotated-token' );

// The shared Next button's precondition
calls = [];
let advance = null;
accounts.checkCanAdvance( function( ok, message ) { advance = [ ok, message ] } );
check( 'with an account and nothing typed Next may go on: the list is asked for, and no account is made', advance[0] === true && calls.length === 1 && calls[0].action === 'accounts/list' );
answer = { status : 200, response : { users : [] } };
accounts.checkCanAdvance( function( ok, message ) { advance = [ ok, message ] } );
check( 'with no account Next may not, and says in the words of the table to make one first', advance[0] === false && advance[1] === en['/_admin/install/accounts/none-yet'] );
answer = { status : 200, response : { users : [ 'admin@example.com' ] } };
typed( 'new@example.com', 'a-long-enough-password', 'a-long-enough-password' );
calls = [];
accounts.checkCanAdvance( function( ok ) { advance = [ ok ] } );
check( 'what is typed when Next is pressed is the account that is meant: it is made first, and Next goes on when it was made', calls.length === 1 && calls[0].action === 'accounts/create' && calls[0].payload.mail === 'new@example.com' && advance[0] === true );
typed( 'new@example.com', 'short', 'short' );
accounts.checkCanAdvance( function( ok ) { advance = [ ok ] } );
check( '...and when it is not right Next stays and the field says why', advance[0] === false && errors().length === 1 );

// A refusal of the server while Next makes the account that is typed is what Next says: never "check the marked fields" with no field marked
answer = { status : 403, response : { error : 'English of the server' } };
typed( 'refused@example.com', 'a-long-enough-password', 'a-long-enough-password' );
advance = null;
accounts.checkCanAdvance( function( ok, message ) { advance = [ ok, message ] } );
check( 'a refusal that belongs to no field is handed to Next as the sentence the code is worded with, and no field is marked', advance[0] === false && advance[1] === en['/_admin/page/error/failed'].replace( '%s', '403' ) && errors().length === 0 );
answer = { status : 400, response : { error : 'invalid email address', code : 'install_mail' } };
accounts.checkCanAdvance( function( ok, message ) { advance = [ ok, message ] } );
check( '...and one that belongs to a field is said there, and Next says that something is to be fixed', advance[0] === false && advance[1] === en['/_admin/install/error/fields'] && errors().length === 1 );
answer = { status : 200, response : { users : [ 'admin@example.com' ] } };

// Enter in a field is the step's own action, as the button is
typed( 'enter@example.com', 'a-long-enough-password', 'a-long-enough-password' );
calls = [];
fire( byId('accounts-add-form'), 'keydown', { key : 'Enter', target : byId('accounts-add-pw') } );
check( 'Enter in a field makes the account, as the button does', calls.length === 1 && calls[0].action === 'accounts/create' );

// What is said at a field is said in the language the page has: the other language says the same thing, at the same field, in its own words
typed( '', 'abc', 'abc' );
create();
const before = errors().join();
text.set('de');
check( 'a sentence at a field stays when the language changes, and is said in the other language, at the same field and with the mark and the focus of before', before === en['/_admin/common/error/missing'].replace( '%s', en['/_admin/install/accounts/mail'] ) + ',' + en['/_admin/install/error/password-short'].replace( '%s', '8' )
	&& errors().join() === de['/_admin/common/error/missing'].replace( '%s', de['/_admin/install/accounts/mail'] ) + ',' + de['/_admin/install/error/password-short'].replace( '%s', '8' )
	&& byId('accounts-add-mail').getAttribute('aria-invalid') === 'true' && byId('accounts-add-pw').getAttribute('aria-invalid') === 'true' && byId('accounts-add-pw2').getAttribute('aria-invalid') === null );
text.set('en');

// The language: the form is drawn again, and what was typed stays
typed( 'typed@example.com', 'abc', '' );
text.set('de');
check( 'the other language draws the form again in that language, and what was typed stays', byId('accounts-add-submit').textContent === de['/_admin/install/accounts/change'] && byId('accounts-add-mail').value === 'typed@example.com' && byId('accounts-add-pw').value === 'abc' );
check( '...and the list of the accounts stays', findAll( content, el => el.dataset.row === 'admin@example.com' ).length === 1 );

done();
