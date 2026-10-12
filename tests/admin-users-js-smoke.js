/**
 *	Nino										A compact filesystembased php framework
 *	admin-users-js-smoke.js	DOM-light checks for the Users panel's script
 *													(_admin/Nino/Modules/Users/assets/admin.js).
 *
 *													An account is one form with one Save: address,
 *													password and - for a manager editing somebody else -
 *													the role go out in one users/save, and a role that was
 *													not changed is not sent (a manager who only renames an
 *													account wider than their own role must not meet the
 *													checks of a role change). The list says which accounts
 *													are deactivated or locked and when each logged in; an
 *													account can be deactivated and activated from its form,
 *													never one's own.
 *
 *	Usage: node tests/admin-users-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, source, fills, load, element, findAll, fire } = require( './harness.js' );

// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Users/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const list = element('div');
list.id = 'users-list';
const form = element('div');
form.id = 'users-form';

function everything() {
	return [ list, form ].concat( findAll( list, function() { return true } ), findAll( form, function() { return true } ) );
}

const requests = [];
const confirms = [];
let confirmAnswer = true;

const Nino = {
	admin : {
		router : { current : function() { return { panel : 'users', parts : [] } }, go : function() {}, set : function() {}, leave : function() {} },
		formToolbar : function( back ) { const bar = element('div'); bar.appendChild( back ); return bar },
	},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		createTextNode : function( value ) { return { tagName : '#TEXT', textContent : value, attributes : {}, dataset : {}, children : [] } },
		getElementById : function( id ) { return everything().find( function( el ) { return el.id === id } ) ?? null },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, confirm : function( message ) { confirms.push( message ); return confirmAnswer }, location : { replace : function() {} } };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Users/assets/admin.js', context );

const panel = Nino.admin.users;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

function byId( id ) {
	return everything().find( function( el ) { return el.id === id } ) ?? null;
}

function buttons( root ) {
	return findAll( root, function( el ) { return el.tagName === 'BUTTON' } );
}

function rowText( mail ) {
	const row = findAll( list, function( el ) { return el.tagName === 'LI' } ).find( function( li ) {
		return findAll( li, function( el ) { return el.tagName === 'STRONG' && el.textContent.indexOf( mail ) === 0 } ).length === 1;
	} );
	return row === undefined ? null : findAll( row, function( el ) { return el.tagName === 'SMALL' } )[0].textContent;
}

const ROLES = [ { id : 'dev', label : 'Developer' }, { id : 'editor', label : 'Editor' } ];

const USERS = [
	{ mail : 'me@example.com', isSelf : true, role : 'dev', perms : [], status : 'active', locked : '', lastLogin : '2026-10-05 10:00' },
	{ mail : 'a@example.com', isSelf : false, role : 'editor', perms : [], status : 'disabled', locked : '2026-10-05 15:00', lastLogin : '' },
	{ mail : 'b@example.com', isSelf : false, role : 'editor', perms : [], status : 'active', locked : '', lastLogin : '2026-10-04 08:00' },
];

console.log('Admin Users');

panel.init();
answer( 200, { users : JSON.parse( JSON.stringify( USERS ) ), canManage : true, self : 'me@example.com', roles : ROLES } );

check( 'the list shows an active account with its role and its last login as text', rowText('b@example.com') === 'Editor · Last login: 2026-10-04 08:00' );
check( '...a deactivated and locked one says both, as words, and that it never logged in', rowText('a@example.com') === 'Editor · Deactivated · locked until 2026-10-05 15:00 · Never logged in' );
check( '...and your own account carries its login too', rowText('me@example.com') === 'Developer · Last login: 2026-10-05 10:00' );

// --- one form, one Save ------------------------------------------------------

panel._openUser( 'b@example.com' );
const edit = byId('users-edit-form');
check( 'the edit screen is one form with exactly one submit button', edit !== null
	&& buttons( edit ).filter( function( b ) { return b.type === 'submit' } ).length === 1
	&& findAll( form, function( el ) { return el.tagName === 'FORM' } ).length === 1 );
check( '...the role is a select inside it, not a form beside it', byId('users-form-role-select') !== null
	&& findAll( edit, function( el ) { return el.id === 'users-form-role-select' } ).length === 1 && byId('users-role-form') === null );
check( '...and says whether the account is deactivated or locked, and its last login', byId('users-form-state').textContent === 'Last login: 2026-10-04 08:00' );

byId('users-form-mail').value = 'c@example.com';
byId('users-form-pw').value = 'a-new-password';
byId('users-form-role-select').value = 'dev';
fire( edit, 'submit' );

check( 'one save sends address, password and the changed role in one users/save', requests.length === 2 && requests[1].action === 'users/save'
	&& requests[1].payload.username === 'b@example.com' && requests[1].payload.mail === 'c@example.com'
	&& requests[1].payload.pw === 'a-new-password' && requests[1].payload.role === 'dev' );
check( '...and no second request carries the role', requests.filter( function( r ) { return r.action === 'users/role' } ).length === 0 );

answer( 200, { mail : 'c@example.com', role : 'dev' } );
check( 'the saved text is shown in the action bar line', byId('users-form-msg').dataset.state === 'saved' && byId('users-form-msg').textContent !== '' );
check( '...the password field is emptied and the account follows its new address and role', byId('users-form-pw').value === ''
	&& panel._currentUser.mail === 'c@example.com' && panel._currentUser.role === 'dev' && byId('users-form-role-select').dataset.saved === 'dev' );
check( '...and the list is fetched again', requests.length === 3 && requests[2].action === 'users/list' );
answer( 200, { users : [ USERS[0], Object.assign( {}, USERS[2], { mail : 'c@example.com', role : 'dev' } ) ], canManage : true, self : 'me@example.com', roles : ROLES } );

// An unchanged role is not sent
byId('users-form-mail').value = 'd@example.com';
fire( byId('users-edit-form'), 'submit' );
check( 'a save that changes only the address does not send the role', requests.length === 4 && requests[3].action === 'users/save'
	&& requests[3].payload.mail === 'd@example.com' && 'role' in requests[3].payload === false );
answer( 500, { error : 'mail already in use' } );
check( '...and a refusal is shown in the same line', byId('users-form-msg').dataset.state === 'error' );

// A role id the config does not have any more is not an option of the select,
// which then shows none. What the server answers with must not make the next
// save look like a role change: it would post '' and strip the stored id
const select = byId('users-form-role-select');
let shown = select.value;
Object.defineProperty( select, 'value', {
	get : function() { return shown },
	set : function( value ) { shown = findAll( select, function( el ) { return el.tagName === 'OPTION' && el.value === value } ).length === 1 ? value : '' },
} );
byId('users-form-pw').value = 'another-password';
fire( byId('users-edit-form'), 'submit' );
answer( 200, { mail : 'c@example.com', role : 'ghost' } );
check( 'a stored role the config no longer has is not a change of the role on the next save', select.dataset.saved === select.value && panel._roleChanged() === false );

// --- deactivate and activate ---------------------------------------------------

const toggle = byId('users-form-status-toggle');
check( 'a manager gets a Deactivate button on somebody else\'s account', toggle !== null && toggle.textContent === 'Deactivate account'
	&& toggle.className.indexOf('nino-admin-btn-danger') === -1 );

confirmAnswer = false;
const before = requests.length;
fire( toggle, 'click' );
check( 'a deactivation is asked about first - and refused when the answer is no', confirms.length === 1 && requests.length === before );

confirmAnswer = true;
fire( toggle, 'click' );
check( 'a confirmed deactivation sends users/status for that account', requests.length === before + 1 && requests[before].action === 'users/status'
	&& requests[before].payload.username === 'c@example.com' && requests[before].payload.active === false );
check( '...the button is off while the request is on its way, so a second click sends nothing', toggle.disabled === true );
answer( 200, { status : 'disabled' } );
check( '...and on again when it is answered', toggle.disabled === false );
check( '...and the button, the state line and the list row say deactivated', toggle.textContent === 'Activate account'
	&& byId('users-form-state').textContent.indexOf('Deactivated') === 0 && rowText('c@example.com').indexOf('Deactivated') !== -1 );

const asked = confirms.length;
fire( toggle, 'click' );
check( 'activating needs no question', confirms.length === asked && requests[requests.length - 1].payload.active === true );
answer( 200, { status : 'active' } );
check( '...and takes the marker away again', toggle.textContent === 'Deactivate account' && byId('users-form-state').textContent.indexOf('Deactivated') === -1 );

// --- your own account ---------------------------------------------------------

panel._openUser( 'me@example.com' );
check( 'there is no Deactivate button on your own account', byId('users-form-status-toggle') === null );
check( '...and no role select either: the role is text', byId('users-form-role-select') === null );

// --- where the shell's "unsaved" marker goes -----------------------------------

/*	The shell puts the marker in the first action bar of the watched form unless
	the panel names one. #users-form holds more than the form: the marker has
	to go to the bar of the form being edited	*/
const stray = element('div');
stray.className = 'nino-admin-actionbar';
form.children.unshift( stray );
panel._openUser( 'b@example.com' );
form.children.unshift( stray );
const ownBar = panel._bar();
check( 'the Users panel names the action bar of the edit form, not the first one in #users-form', ownBar !== null && ownBar !== stray
	&& findAll( ownBar, function( el ) { return el.type === 'submit' } ).length === 1 && byId('users-edit-form').children.indexOf( ownBar ) !== -1 && form.querySelector('.nino-admin-actionbar') === stray );
panel._renderCreateForm();
form.children.unshift( stray );
const createBar = panel._bar();
check( '...and of the form that creates an account when that is the one on screen', createBar !== null && createBar !== stray && byId('users-create-form').children.indexOf( createBar ) !== -1 );
form.children.length = 0;
check( '...and none while no form is drawn', panel._bar() === null );
check( 'it is handed to the registry with the form', /Nino\.admin\.dirty\.watchForm\( 'users', function\(\) \{ return dc\.getElementById\('users-form'\) \}, Nino\.admin\.users\._saveOpen, Nino\.admin\.users\._bar \)/.test( source('_admin/Nino/Modules/Users/assets/admin.js') ) === true );

done();
