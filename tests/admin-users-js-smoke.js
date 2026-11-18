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
 *													never one's own. The forms check nothing by themselves
 *													(novalidate): what is missing or wrong is said under the
 *													field, what went well is a toast. Every view has its
 *													address, #users/new on a fresh load as well. The words
 *													the four scripts of the panel name (this one, roles,
 *													login protection, recovery password) are held here.
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

// The pane the shell draws: the name of the panel in its head, and the two levels beside it
const pane = element('div');
pane.dataset.panel = 'users';
const head = element('div');
head.className = 'admin-panel-head';
const paneTitle = element('h2');
paneTitle.className = 'admin-panel-title';
head.appendChild( paneTitle );
pane.appendChild( head );
const list = element('div');
list.id = 'users-list';
pane.appendChild( list );
const form = element('div');
form.id = 'users-form';
pane.appendChild( form );

function everything() {
	return [ list, form ].concat( findAll( list, function() { return true } ), findAll( form, function() { return true } ) );
}

const requests = [];
const confirms = [];
let confirmAnswer = true;
// The address the page was loaded on: what init() opens
let address = { panel : 'users', parts : [] };

const Nino = {
	admin : {
		router : { current : function() { return address }, go : function() {}, set : function() {}, leave : function() {},
			href : function( name, parts ) { return '#'+ [ name ].concat( parts || [] ).map( encodeURIComponent ).join('/') } },
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
		createElementNS : function( space, tag ) { return element( tag ) },
		createTextNode : function( value ) { return { tagName : '#TEXT', textContent : value, attributes : {}, dataset : {}, children : [] } },
		getElementById : function( id ) { return everything().find( function( el ) { return el.id === id } ) ?? null },
		documentElement : null,
		body : null,
		get activeElement() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null },
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, confirm : function( message ) { confirms.push( message ); return confirmAnswer }, location : { replace : function() {} } };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );

const toasts = [];
Nino.adminUi.say = function( words ) { toasts.push( words ) };
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

/** The sentences that say what is wrong under the fields of a form */
function fieldErrors( root ) {
	return findAll( root, function( el ) { return el.className === 'nino-admin-field-error' } );
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
check( '...and says whether the account is deactivated or locked, and its last login, in a line it shows and does not take', byId('users-form-state').textContent === 'Last login: 2026-10-04 08:00' && byId('users-form-state').tagName === 'P' );

byId('users-form-mail').value = 'c@example.com';
byId('users-form-pw').value = 'a-new-password';
byId('users-form-role-select').value = 'dev';
fire( edit, 'submit' );

check( 'one save sends address, password and the changed role in one users/save', requests.length === 2 && requests[1].action === 'users/save'
	&& requests[1].payload.username === 'b@example.com' && requests[1].payload.mail === 'c@example.com'
	&& requests[1].payload.pw === 'a-new-password' && requests[1].payload.role === 'dev' );
check( '...and no second request carries the role', requests.filter( function( r ) { return r.action === 'users/role' } ).length === 0 );

answer( 200, { mail : 'c@example.com', role : 'dev' } );
check( 'a save leaves the line of the action bar empty in its saved state', byId('users-form-msg').dataset.state === 'saved' && byId('users-form-msg').textContent === '' );
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
check( '...and the button, the state line and the list row say deactivated, and the toast says it', toggle.textContent === 'Activate account'
	&& byId('users-form-state').textContent.indexOf('Deactivated') === 0 && rowText('c@example.com').indexOf('Deactivated') !== -1 && toasts.pop() === text('/_admin/users/msg/deactivated') );

const asked = confirms.length;
fire( toggle, 'click' );
check( 'activating needs no question', confirms.length === asked && requests[requests.length - 1].payload.active === true );
answer( 200, { status : 'active' } );
check( '...and takes the marker away again', toggle.textContent === 'Deactivate account' && byId('users-form-state').textContent.indexOf('Deactivated') === -1 && toasts.pop() === text('/_admin/users/msg/activated') );

// --- your own account ---------------------------------------------------------

panel._openUser( 'me@example.com' );
check( 'there is no Deactivate button on your own account', byId('users-form-status-toggle') === null );
check( '...and no role select either: the role is text', byId('users-form-role-select') === null );

// --- the focus -----------------------------------------------------------------

/*	A row opens an account and writes its address, so it is a link with that
	address - not a link to nowhere. What is opened takes the focus into the form,
	Back gives it to the row, which is found again where the list was drawn anew
	behind the form (a save does that), and a delete leaves it nowhere to stand	*/
const linkOf = function( mail ) {
	const row = findAll( list, function( el ) { return el.tagName === 'LI' } ).find( function( li ) { return li.dataset.row === mail } );
	return row === undefined ? undefined : findAll( row, function( el ) { return el.tagName === 'A' } )[0];
};
const focusedNow = function() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null };
const backLinkOf = function() { return findAll( form, function( el ) { return el.className === 'nino-admin-back-link' } )[0] };

panel._showList();
panel._renderList( panel._users );
check( 'a row is a link with the address of the account it opens', linkOf('c@example.com').href === '#users/c%40example.com' && linkOf('me@example.com').href === '#users/me%40example.com' );

fire( linkOf('c@example.com'), 'click' );
check( 'opening an account shows its form and moves the focus into it', form.classList.contains('admin-hidden') === false && list.classList.contains('admin-hidden') === true && focusedNow() === backLinkOf() && backLinkOf() !== undefined );

panel._renderList( panel._users );
fire( backLinkOf(), 'click' );
check( 'Back shows the list and puts the focus on the row of the account - the one drawn now, not the one that was pressed', list.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true
	&& focusedNow() === linkOf('c@example.com') );

const addButton = findAll( list, function( el ) { return el.className === 'nino-admin-btn-primary' } )[0];
fire( addButton, 'click' );
check( 'a new account takes the focus into its first field', focusedNow() === byId('users-create-mail') );
fire( backLinkOf(), 'click' );
check( '...and Back returns to the New user button', focusedNow() === addButton && list.classList.contains('admin-hidden') === false );

fire( linkOf('c@example.com'), 'click' );
byId('users-form-mail').focus();
confirmAnswer = false;
const beforeDelete = requests.length;
panel._delete();
check( 'a delete that is answered No sends nothing', requests.length === beforeDelete );
confirmAnswer = true;
panel._delete();
check( '...and a Yes sends it', requests.length === beforeDelete + 1 && requests[beforeDelete].action === 'users/delete' );
answer( 200, {} );
check( 'a deleted account leaves the focus nothing to stand on in the form: it goes to the name of the panel', list.classList.contains('admin-hidden') === false && focusedNow() === paneTitle );

// --- logging an account out everywhere ----------------------------------------

panel._currentUser = { mail : 'd@example.com', isSelf : false };
confirmAnswer = false;
const beforeLogout = requests.length;
panel._logoutAll();
check( 'logging out everywhere is asked about first - and sends nothing when the answer is no', requests.length === beforeLogout );
confirmAnswer = true;
panel._logoutAll();
check( '...and a Yes sends users/logoutall for that account', requests.length === beforeLogout + 1 && requests[beforeLogout].action === 'users/logoutall' && requests[beforeLogout].payload.username === 'd@example.com' );

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

// The accounts the name pins draw, two of them with a name
const NAMED = [
	{ mail : 'me@example.com', name : 'Mia', isSelf : true, role : 'dev', perms : [], status : 'active', locked : '', lastLogin : '' },
	{ mail : 'b@example.com', name : 'Bo <i>B</i> [[/nino/dir]]', isSelf : false, role : 'editor', perms : [], status : 'active', locked : '', lastLogin : '' },
	{ mail : 'c@example.com', isSelf : false, role : 'editor', perms : [], status : 'active', locked : '', lastLogin : '' },
];

pinUsersNameColumn();
pinUsersNameCreate();
pinUsersNameEdit();

// --- an account that holds what the signed-in one does not -------------------

{
	const BEYOND = { mail : 'w@example.com', isSelf : false, role : 'dev', name : 'Wide', perms : [], status : 'active', locked : '', lastLogin : '', canChange : false };
	panel._users = [ ...panel._users, BEYOND ];

	panel._openUser( 'w@example.com' );
	const shown = byId('users-edit-form');
	check( 'an account the signed-in one may not change has nothing to type in, no select and no button in its form', shown !== null
		&& findAll( shown, function( el ) { return el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'BUTTON' } ).length === 0
		&& byId('users-form-mail') === null && byId('users-form-pw') === null && byId('users-form-role-select') === null && byId('users-form-status-toggle') === null && panel._bar() === null );
	const lines = findAll( shown, function( el ) { return el.tagName === 'P' } ).map( function( el ) { return el.textContent } );
	check( '...it shows its name and its role, and says why nothing can be pressed in a notice', lines.includes( 'Wide' ) && lines.includes( 'Developer' )
		&& findAll( shown, function( el ) { return el.className === 'nino-admin-notice' && el.textContent === text('/_admin/users/label/beyond') } ).length === 1 );

	// A role the signed-in account may not hand out is no option, the role an account holds stays one
	panel._roles = [ { id : 'dev', label : 'Developer', canGrant : false }, { id : 'editor', label : 'Editor', canGrant : true }, { id : 'other', label : 'Other' } ];
	const optionsOf = function( id ) { return findAll( byId( id ), function( el ) { return el.tagName === 'OPTION' } ).map( function( el ) { return el.value } ) };
	panel._openUser( 'b@example.com' );
	check( 'the role select offers the roles that may be handed out and the one held', optionsOf('users-form-role-select').join() === 'editor,other' );
	panel._users = panel._users.map( function( u ) { return u.mail === 'b@example.com' ? Object.assign( {}, u, { role : 'dev' } ) : u } );
	panel._openUser( 'b@example.com' );
	check( '...a held role that may not be handed out stays an option', optionsOf('users-form-role-select').join() === 'dev,editor,other' );
	panel._renderCreateForm();
	check( '...and the new account starts with the first role that may be handed out', optionsOf('users-create-role').join() === 'editor,other' && byId('users-create-role').value === 'editor' );
	panel._roles = ROLES;
	panel._users = panel._users.map( function( u ) { return u.mail === 'b@example.com' ? Object.assign( {}, u, { role : 'editor' } ) : u } );

	panel._openUser( 'b@example.com' );
	check( 'an account that may be changed has its form as before', byId('users-form-mail') !== null && byId('users-form-pw') !== null && byId('users-form-status-toggle') !== null );

	check( 'the words of the refusal and of the form are both languages\'', [ 'users_account_beyond', 'users_account_beyond_scoped' ].every( function( code ) {
		return [ 'en_US', 'de_DE' ].every( function( locale ) { return fills('_admin/Nino/Modules/Users/text/'+ locale+ '.php')['/_admin/error/'+ code] !== undefined } );
	} ) && [ 'en_US', 'de_DE' ].every( function( locale ) { return fills('_admin/Nino/Modules/Users/text/'+ locale+ '.php')['/_admin/users/label/beyond'] !== undefined } ) );
}

// --- the forms of the guide -----------------------------------------------------

/*	The build the workbench interface asks of a form (docs/admin-ui.md): novalidate, the name of a field at its left - which is the
	label standing directly in a fieldset -, what explains behind a (?), the bar with the dangerous button at its left and ONE main
	button at its right, what is wrong said under the field, what went well said as a toast	*/
{
	const hasClass = function( el, name ) { return typeof el.className === 'string' && el.className.split(/\s+/).indexOf( name ) !== -1 };
	const kindOf = function( button ) { return [ 'danger', 'primary', 'secondary' ].find( function( kind ) { return hasClass( button, 'nino-admin-btn-'+ kind ) } ) ?? 'plain' };
	const kinds = function( root ) {
		const bar = findAll( root, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } );
		return bar.length === 1 ? findAll( bar[0], function( el ) { return el.tagName === 'BUTTON' } ).map( kindOf ).join() : 'no bar';
	};

	panel._users = JSON.parse( JSON.stringify( USERS ) );
	panel._canManage = true;
	panel._roles = ROLES;
	panel._renderList( panel._users );

	panel._renderCreateForm();
	const created = byId('users-create-form');
	panel._openUser( 'b@example.com' );
	const edited = byId('users-edit-form');

	check( 'both forms check nothing by themselves', created !== null && created.noValidate === true && edited.noValidate === true );
	check( '...every field of them stands directly in a fieldset, which puts its name at its left', [ created, edited ].every( function( root ) {
		const fields = findAll( root, function( el ) { return el.tagName === 'LABEL' && hasClass( el, 'nino-admin-field' ) } );
		return fields.length > 3 && fields.every( function( el ) { return el.parentNode.tagName === 'FIELDSET' } );
	} ) );
	panel._renderCreateForm();
	check( '...what explains stands behind a (?) at the name, and no paragraph of explanation is in a form', [ byId('users-create-form'), edited ].every( function( root ) {
		return findAll( root, function( el ) { return hasClass( el, 'nino-admin-help-button' ) } ).length >= 3
			&& findAll( root, function( el ) { return hasClass( el, 'nino-admin-hint' ) } ).length === 0;
	} ) );
	check( '...the bar of a form of a new account has one main button, which is the submit', kinds( byId('users-create-form') ) === 'primary' );

	panel._openUser( 'b@example.com' );
	check( 'the bar of somebody else\'s account has Delete first, as the one dangerous button, two further buttons and the one main button last', kinds( byId('users-edit-form') ) === 'danger,secondary,secondary,primary' );
	panel._openUser( 'me@example.com' );
	check( '...and that of your own account has what only it may do: log out everywhere, and Save', kinds( byId('users-edit-form') ) === 'secondary,primary' );

	// What is wrong is said under the field before anything goes out
	panel._renderCreateForm();
	let before = requests.length;
	fire( byId('users-create-form'), 'submit' );
	check( 'an empty new account sends nothing and says under its address and its password that they are missing, the focus goes to the first', requests.length === before && fieldErrors( byId('users-create-form') ).length === 2
		&& byId('users-create-mail').attributes['aria-invalid'] === 'true' && byId('users-create-mail').focused === true && byId('users-form-msg').dataset.state === 'error' );
	byId('users-create-mail').value = 'not an address';
	byId('users-create-pw').value = 'short';
	fire( byId('users-create-form'), 'submit' );
	check( '...an address that is none and a password under the minimum are wrong as well, once each', requests.length === before && fieldErrors( byId('users-create-form') ).length === 2 );
	byId('users-create-mail').value = 'fresh@example.com';
	byId('users-create-pw').value = 'a-new-password';
	fire( byId('users-create-form'), 'submit' );
	check( '...and a good one is sent, with the sentences gone', requests.length === before + 1 && requests[before].action === 'users/create' && fieldErrors( byId('users-create-form') ).length === 0 );

	toasts.length = 0;
	answer( 200, { mail : 'fresh@example.com' } );
	answer( 200, { users : USERS.concat( [ { mail : 'fresh@example.com', isSelf : false, role : 'editor', perms : [], status : 'active', locked : '', lastLogin : '' } ] ), canManage : true, roles : ROLES } );
	check( 'a new account opens its form and says it is saved as a toast', byId('users-edit-form') !== null && panel._currentUser.mail === 'fresh@example.com' && toasts.length === 1 && toasts[0] === text('/_admin/common/msg/saved') );

	// Your own account: the current password is required
	panel._users = JSON.parse( JSON.stringify( USERS ) );
	panel._openUser( 'me@example.com' );
	before = requests.length;
	fire( byId('users-edit-form'), 'submit' );
	check( 'your own account asks for the current password: without it nothing is sent, and it is said under the field', requests.length === before && fieldErrors( byId('users-edit-form') ).length === 1
		&& byId('users-form-currentpw').attributes['aria-invalid'] === 'true' && byId('users-form-currentpw').focused === true && byId('users-form-currentpw').required === true );
	byId('users-form-currentpw').value = 'the current one';
	byId('users-form-pw').value = 'short';
	fire( byId('users-edit-form'), 'submit' );
	check( '...and a new password under the minimum is said under its field too', requests.length === before && fieldErrors( byId('users-edit-form') ).length === 1 && byId('users-form-pw').attributes['aria-invalid'] === 'true' );
	byId('users-form-pw').value = '';
	fire( byId('users-edit-form'), 'submit' );
	check( '...an empty new password is none: the rest is sent', requests.length === before + 1 && requests[before].payload.currentPassword === 'the current one' );
	answer( 401, { error : 'wrong current password', code : 'wrong_password', field : 'currentPassword' } );
	check( 'a refusal that names a field is said in the line and under that field', byId('users-form-msg').dataset.state === 'error' && fieldErrors( byId('users-edit-form') ).length === 1
		&& byId('users-form-currentpw').attributes['aria-invalid'] === 'true' );

	// What went well is a toast: logging out everywhere, deleting
	panel._users = JSON.parse( JSON.stringify( USERS ) );
	panel._openUser( 'b@example.com' );
	confirmAnswer = true;
	toasts.length = 0;
	panel._logoutAll();
	answer( 200, { loggedOutSelf : false } );
	check( 'logging an account out everywhere says it as a toast, the line stays as it was', toasts.length === 1 && toasts[0] === text('/_admin/users/msg/loggedout') && byId('users-form-msg').dataset.state !== 'error' );
	toasts.length = 0;
	panel._delete();
	answer( 200, {} );
	check( '...so does deleting the account', toasts.length === 1 && toasts[0] === text('/_admin/common/msg/deleted') );
}

// What is typed or stored by somebody else is only ever text: the title of a form, the field a refusal names, the hints of a password manager
{
	const evil = '"><img/src=x/onerror=alert(1)>@example.com';

	panel._users = [ Object.assign( {}, USERS[2], { mail : evil, name : '<b>n</b>' } ), USERS[0] ];
	panel._renderList( panel._users );
	panel._openUser( evil );
	const title = findAll( form, function( el ) { return el.tagName === 'LEGEND' } )[0];
	check( 'the title of an account\'s form is its address as text, whatever it holds', title.textContent === evil && title.children.length === 0 );
	check( '...and the row of the list too', findAll( list, function( el ) { return el.tagName === 'STRONG' } )[0].textContent === evil );

	fire( byId('users-edit-form'), 'submit' );
	answer( 400, { error : 'x', code : 'users_invalid_mail', field : 'mail"], [data-field="name' } );
	check( 'a refusal that names a field which is no name marks no field', fieldErrors( byId('users-edit-form') ).length === 0 && byId('users-form-name').attributes['aria-invalid'] === undefined );

	panel._users = JSON.parse( JSON.stringify( USERS ) );
	panel._openUser( 'me@example.com' );
	check( 'the password fields tell a password manager which one they are: a new password is new, the confirmation is the current one', byId('users-form-pw').autocomplete === 'new-password' && byId('users-form-currentpw').autocomplete === 'current-password'
		&& ( panel._renderCreateForm(), byId('users-create-pw').autocomplete === 'new-password' ) );
	panel._openUser( 'me@example.com' );
	check( 'a line that is shown and not taken - the state, the role of your own account - is text: no input, no stop of the Tab key', [ 'users-form-state', 'users-form-role' ].every( function( id ) { return byId( id ) !== null && byId( id ).tagName === 'P' } )
		&& findAll( byId('users-edit-form'), function( el ) { return el.tagName === 'INPUT' && el.readOnly === true } ).length === 0 );
}

// --- every view has its address ---------------------------------------------------

{
	const fresh = function( parts, canManage ) {
		address = { panel : 'users', parts : parts };
		panel._ready = false;
		panel._loading = false;
		panel._currentUser = undefined;
		panel.init();
		answer( 200, { users : JSON.parse( JSON.stringify( USERS ) ), canManage : canManage, roles : ROLES } );
	};

	fresh( [ 'new' ], true );
	check( 'the address of the new-account form opens it on a fresh load, as a click on New does', byId('users-create-form') !== null && form.classList.contains('admin-hidden') === false && list.classList.contains('admin-hidden') === true && panel._currentUser === null );
	fresh( [ 'new' ], false );
	check( '...for an account that may create one; for another it is the list', form.classList.contains('admin-hidden') === true && list.classList.contains('admin-hidden') === false );
	fresh( [ 'b@example.com' ], true );
	check( 'the address of an account opens its form', panel._currentUser !== null && panel._currentUser.mail === 'b@example.com' && byId('users-edit-form') !== null );
	address = { panel : 'users', parts : [] };
}

// --- the words the four scripts of the panel name -----------------------------------

{
	const moduleDe = fills('_admin/Nino/Modules/Users/text/de_DE.php');
	const workbenchDe = fills('_admin/text/de_DE.php');
	const keys = new Set();

	[ 'admin.js', 'roles.js', 'lockout.js', 'recoverypw.js' ].forEach( function( script ) {
		for( const match of source('_admin/Nino/Modules/Users/assets/'+ script).matchAll( /'(\/_admin\/[a-z0-9\/_-]*[a-z0-9])'/g ) )
			keys.add( match[1] );
	} );

	// A key the scripts put together (the name of a group) is not a word of its own
	const lacking = [ ...keys ].filter( function( key ) {
		return ( ( moduleEn[key] ?? workbenchEn[key] ?? '' ) === '' || ( moduleDe[key] ?? workbenchDe[key] ?? '' ) === '' );
	} );

	check( 'every word the scripts of the panel name is a fill in English and in German'+ ( lacking.length === 0 ? '' : ' - not '+ lacking.join(', ') ), keys.size > 80 && lacking.length === 0 );
}

done();




// --- the name of an account --------------------------------------------------

/** The second line of a row, where the name of an account leads when it has one */
function detailOfRow( mail ) {
	const row = findAll( list, function( el ) { return el.tagName === 'LI' } ).find( function( li ) { return li.dataset.row === mail } );
	return row === undefined ? null : findAll( row, function( el ) { return el.tagName === 'SMALL' } )[0];
}

/** The sentence a field is described by behind its (?), '' where it has none */
function helpOf( input ) {
	const tokens = String( input.attributes['aria-describedby'] ?? '' ).split(/\s+/);
	const sentence = findAll( form, function( el ) { return tokens.indexOf( el.id ) !== -1 && el.className === 'nino-admin-help-text' } )[0];
	return sentence === undefined ? '' : sentence.textContent;
}

/** The list names the account by its name where it has one: the first words of the second line, as text */
function pinUsersNameColumn() {
	panel._users = JSON.parse( JSON.stringify( NAMED ) );
	panel._canManage = true;
	panel._renderList( panel._users );

	check( 'an account with a name shows it first in the second line of its row', detailOfRow('me@example.com').textContent === 'Mia · Developer · Never logged in' );
	check( '...an account without one has none - the line begins with its role', detailOfRow('c@example.com').textContent === 'Editor · Never logged in' );
	check( '...a name with markup in it is only text', detailOfRow('b@example.com').textContent === 'Bo <i>B</i> [[/nino/dir]] · Editor · Never logged in' && detailOfRow('b@example.com').children.length === 0 );
}

/** The new-account form asks for the name beside the address, says what it is for behind the (?), and sends it trimmed */
function pinUsersNameCreate() {
	panel._renderCreateForm();
	const input = byId('users-create-name');

	check( 'the form that creates an account has a name field, optional, of at most sixty characters', input !== null && input.required !== true && input.maxLength === 60 && input.dataset.field === 'name' );
	check( '...what the name is for stands behind its (?), which the field is described by', helpOf( input ) === text('/_admin/users/hint/name') && text('/_admin/users/hint/name') !== '' );

	byId('users-create-mail').value = 'new@example.com';
	byId('users-create-pw').value = 'a-new-password';
	input.value = '  Nina  ';
	const before = requests.length;
	fire( byId('users-create-form'), 'submit' );
	check( 'it is sent with the rest, trimmed', requests.length === before + 1 && requests[before].action === 'users/create' && requests[before].payload.name === 'Nina' && requests[before].payload.mail === 'new@example.com' );

	answer( 400, { error : 'a name has at most 60 characters', code : 'users_name_long', params : [ 60 ], field : 'name' } );
	check( 'a refusal is shown in the line of the form and under the field it names, and does not leave the form', byId('users-form-msg').dataset.state === 'error' && byId('users-create-form') !== null
		&& byId('users-create-name').attributes['aria-invalid'] === 'true' && fieldErrors( byId('users-create-form') ).length === 1 );
}

/** The edit form shows the stored name; only a changed name is sent, and an emptied one as '' */
function pinUsersNameEdit() {
	panel._users = JSON.parse( JSON.stringify( NAMED ) );
	panel._canManage = true;
	panel._renderList( panel._users );
	panel._openUser( 'b@example.com' );
	const input = byId('users-form-name');

	check( 'the edit form holds the name the account has, in a field that is no more than a field', input !== null && input.value === 'Bo <i>B</i> [[/nino/dir]]' && input.maxLength === 60 && input.dataset.field === 'name'
		&& helpOf( input ) === text('/_admin/users/hint/name') );
	check( '...untouched it is no change', panel._nameChanged() === false );

	const before = requests.length;
	fire( byId('users-edit-form'), 'submit' );
	check( 'a save that leaves the name alone does not send it', requests[before].action === 'users/save' && 'name' in requests[before].payload === false );
	answer( 200, { mail : 'b@example.com', role : 'editor' } );
	answer( 200, { users : JSON.parse( JSON.stringify( NAMED ) ), canManage : true, roles : ROLES } );

	panel._openUser( 'b@example.com' );
	byId('users-form-name').value = '  Bea  ';
	check( 'a changed name is a change of the form, so the form is unsaved', panel._nameChanged() === true );
	const saved = requests.length;
	let ended = null;
	panel._saveOpen( function( ok ) { ended = ok } );
	check( '...it is sent trimmed, alone', requests[saved].action === 'users/save' && requests[saved].payload.name === 'Bea' && requests[saved].payload.username === 'b@example.com' && 'role' in requests[saved].payload === false );
	answer( 200, { mail : 'b@example.com', role : 'editor', name : 'Bea' } );
	check( '...and the account and the field follow what the server answers, the save ends well', ended === true && panel._currentUser.name === 'Bea' && byId('users-form-name').value === 'Bea' && panel._nameChanged() === false );
	answer( 200, { users : [ Object.assign( {}, NAMED[1], { name : 'Bea' } ) ], canManage : true, roles : ROLES } );

	byId('users-form-name').value = '';
	const emptied = requests.length;
	fire( byId('users-edit-form'), 'submit' );
	check( 'an emptied name is sent as an empty text: that takes it away', requests[emptied].payload.name === '' );
	answer( 200, { mail : 'b@example.com', role : 'editor', name : '' } );
	check( '...and the account has none then', panel._currentUser.name === '' );

	byId('users-form-name').value = 'x'.repeat( 61 );
	const refused = requests.length;
	fire( byId('users-edit-form'), 'submit' );
	answer( 400, { error : 'a name has at most 60 characters', code : 'users_name_long', params : [ 60 ], field : 'name' } );
	check( 'a refusal of the server stays on the form with what was typed in it', byId('users-form-msg').dataset.state === 'error' && byId('users-form-name').value.length === 61 && requests.length === refused + 1 );
}
