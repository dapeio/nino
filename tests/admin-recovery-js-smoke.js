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

const vm 		= require('vm');
const { check, done, source, load, element, fire } = require( './harness.js' );

// --- the page, as the template has it ---------------------------------------

const ids = [
	'recovery-login', 'recovery-login-msg', 'recovery-input-pw', 'recovery-tools', 'recovery-dates', 'recovery-restore-msg', 'recovery-reset', 'recovery-reset-mail', 'recovery-reset-pw',
	'recovery-reset-msg', 'recovery-reset-submit', 'recovery-create', 'recovery-create-mail', 'recovery-create-pw', 'recovery-create-repeat',
	'recovery-create-msg', 'recovery-logout', 'recovery-key', 'recovery-key-show', 'recovery-file', 'recovery-file-input',
];
const page = {};
ids.forEach( function( id ) {
	page[id] = element( id === 'recovery-reset-mail' ? 'select' : 'div' );
	page[id].id = id;
} );
page['recovery-tools'].dataset.open = '1';
page['recovery-key'].type = 'password';
page['recovery-key-show'].checked = false;
page['recovery-file-input'].files = [];

const template = source('_admin/templates/page-recovery.tpl');

const requests = [];
let ready = null;
let confirmAnswer = true;
const confirms = [];

const Nino = {
	dir : '',
	events : { bindCallback : function( name, fn ) { if( name === 'ready' ) ready = fn } },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), file : data.file, callback : callback } );
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

load( '_admin/assets/recovery.js', vm.createContext( sandbox ) );

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
check( 'the recovery key is typed into a masked field with no name and no autofill, which a box shows on request', /<input id="recovery-key" type="password" autocomplete="off"(?![^>]*\sname=)/.test( template ) && /id="recovery-key-show"/.test( template ) );
check( '...and the archive of a file has a form of its own with a file input and a button that submits', /<form id="recovery-file">/.test( template ) && /<input id="recovery-file-input" type="file"/.test( template ) && /<button type="submit" id="recovery-file-submit"/.test( template ) );

ready();
check( 'the page asks for the backups and the accounts when the gate is open', sent('recovery/list').length === 1 );

answer( 'recovery/list', 200, { dates : [ '2026-10-02' ], users : [ 'a@example.com', 'b@example.com' ], uploadLimit : 2097152 } );
check( 'the select is filled from the list the server sent', options().join(',') === 'a@example.com,b@example.com' && page['recovery-reset-submit'].disabled === false );

// Restore: after the question, and the page says what the server said - and, for a
// backup of another version of Nino, where it comes from, in the page's own English
const restoreButton = page['recovery-dates'].children[0].children[1];
fire( restoreButton, 'click' );
check( 'a restore is asked about, and sends the date of its row', confirms.length === 1 && sent('recovery/restore').length === 1 && sent('recovery/restore')[0].payload.date === '2026-10-02' );
answer( 'recovery/restore', 200, { restoredDate : '2026-10-02' } );
check( '...and says it was restored, with nothing more where the backup has nothing to add', page['recovery-restore-msg'].textContent === 'Restored 2026-10-02.' );
fire( restoreButton, 'click' );
answer( 'recovery/restore', 200, { restoredDate : '2026-10-02', notice : { code : 'backups_restored_other_version', params : [ '1.8.0', '1.9.0' ] } } );
check( '...and with the notice of a backup from another version: where it comes from and what runs here', page['recovery-restore-msg'].textContent === 'Restored 2026-10-02. The backup comes from Nino 1.8.0; this is 1.9.0.' );
fire( restoreButton, 'click' );
answer( 'recovery/restore', 500, { error : 'decryption failed' } );
check( 'a refused restore shows the status and the reason', page['recovery-restore-msg'].textContent === '(500) decryption failed' );
confirms.length = 0;

// The key of an archive of another project: typed once, sent with the restore of the row, gone from the page where it was used
const key = 'A'.repeat( 43 )+ '=';
page['recovery-key'].value = key;
fire( restoreButton, 'click' );
check( 'a restore from the list sends the key that was typed', sent('recovery/restore').slice( -1 )[0].payload.key === key && sent('recovery/restore').slice( -1 )[0].payload.date === '2026-10-02' );
answer( 'recovery/restore', 422, { error : 'the key does not open the archive', code : 'backups_key_wrong' } );
check( '...a key that did not open it stays in the field to be corrected, and the page says why', page['recovery-key'].value === key && page['recovery-restore-msg'].textContent === '(422) the key does not open the archive' );
fire( restoreButton, 'click' );
answer( 'recovery/restore', 200, { restoredDate : '2026-10-02' } );
check( '...and a restore that went through empties it', page['recovery-key'].value === '' );
confirms.length = 0;
fire( page['recovery-key-show'], 'change', { target : { checked : true } } );
check( 'the box shows the key as text', page['recovery-key'].type === 'text' );
fire( page['recovery-key-show'], 'change', { target : { checked : false } } );
check( '...and, off again, hides it', page['recovery-key'].type === 'password' );

// An archive from a file: the file and the key in one request
const submitFile = function() { fire( page['recovery-file'], 'submit', { preventDefault : function() {} } ) };
const before = requests.length;
submitFile();
check( 'no file chosen: nothing is sent, and the page says so', requests.length === before && /file/.test( page['recovery-restore-msg'].textContent ) );
page['recovery-file-input'].files = [ { name : 'backup.php', size : 4096 } ];
submitFile();
check( 'no key typed: nothing is sent, and the page says so', requests.length === before && /key/.test( page['recovery-restore-msg'].textContent ) );
page['recovery-key'].value = key;
page['recovery-file-input'].files = [ { name : 'backup.php', size : 2097152 } ];
submitFile();
check( 'a file over what the server takes: nothing is sent, and the page names the limit and the way round it', requests.length === before && /2 MB/.test( page['recovery-restore-msg'].textContent ) && /SFTP/.test( page['recovery-restore-msg'].textContent ) );
page['recovery-file-input'].files = [ { name : 'backup.php', size : 4096 } ];
confirmAnswer = false;
submitFile();
check( 'asked first, and not sent on no', confirms.length === 1 && requests.length === before );
confirmAnswer = true;
const chosen = page['recovery-file-input'].files[0];
submitFile();
const fileRequest = sent('recovery/restore-file')[0];
check( 'sent as one request with the file and the key', fileRequest !== undefined && fileRequest.file === chosen && fileRequest.payload.key === key );
answer( 'recovery/restore-file', 422, { error : 'the key does not open the archive' } );
check( '...a refusal shows the status and the reason and keeps key and file for another try', page['recovery-restore-msg'].textContent === '(422) the key does not open the archive' && page['recovery-key'].value === key );
submitFile();
answer( 'recovery/restore-file', 200, { restoredDate : 'uploaded archive' } );
check( '...a restore that went through says so and empties the key and the file', page['recovery-restore-msg'].textContent === 'Restored the archive.' && page['recovery-key'].value === '' && page['recovery-file-input'].value === '' );
confirms.length = 0;

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

done();
