/**
 *	Nino										A compact filesystembased php framework
 *	admin-recovery-js-smoke.js	DOM-light checks for the recovery page's script
 *													(_admin/assets/recovery.js, its table recovery-text.js).
 *
 *													The page is the way back in when the accounts are what
 *													is broken, so what it sends is what matters: the account
 *													a password is set for is picked from a list rather than
 *													typed, so a typo cannot name an account that is not
 *													there, and an account with full access is only asked for
 *													after the answer to a question - the server is told that
 *													it was asked. The question is the dialog of the design
 *													system and never the browser's; what is wrong is said at
 *													the field it is about and the focus goes there; the page
 *													speaks German or English, and a change of the language
 *													keeps what was typed.
 *
 *	Usage: node tests/admin-recovery-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, source, load, element, documentOf, findAll, fire } = require( './harness.js' );

// --- the page, as the template has it ---------------------------------------

const root = element('div');
const wrap = element('div');
wrap.id = 'recovery-wrap';
wrap.className = 'nino-admin-auth nino-admin-auth--form';
wrap.dataset.minPassword = '8';
const loginForm = element('form');
loginForm.id = 'recovery-login';
const loginFields = element('fieldset');
loginFields.id = 'recovery-login-fields';
loginForm.appendChild( loginFields );
const tools = element('div');
tools.id = 'recovery-tools';
tools.className = 'admin-hidden';
tools.dataset.open = '0';
const sections = element('div');
sections.id = 'recovery-sections';
const foot = element('div');
foot.id = 'recovery-foot';
const toolsHeading = element('h1');
toolsHeading.setAttribute( 'tabindex', '-1' );
tools.appendChild( toolsHeading );
tools.appendChild( sections );
tools.appendChild( foot );
wrap.appendChild( loginForm );
wrap.appendChild( tools );
const languageMount = element('div');
languageMount.id = 'page-language-mount';
root.appendChild( languageMount );
root.appendChild( wrap );

const template = source('_admin/templates/page-recovery.tpl');

const requests = [];
const dialogs = [];
const stored = {};
const sessionStored = {};
const readies = [];
const ready = function() { readies.forEach( function( fn ) { fn() } ) };
const reloads = [];

const Nino = {
	dir : '',
	events : { bindCallback : function( name, fn ) { if( name === 'ready' ) readies.push( fn ) } },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, action : data.action, payload : JSON.parse( data.data ), file : data.file, callback : callback } );
	} },
	content : { text : {}, getText : function( key ) { return Nino.content.text[key] || '' } },
};

const doc = documentOf( root );
doc.documentElement = element('html');
doc.body = element('body');
doc.title = '';
doc.addEventListener = function() {};
doc.removeEventListener = function() {};
doc.createElement = function( tag ) {
	const created = element( tag );
	if( tag === 'dialog' ) {
		created.open = false;
		created.showModal = function() { created.open = true };
		created.show = function() { created.open = true };
		created.close = function() { created.open = false; fire( created, 'close' ) };
		dialogs.push( created );
	}
	return created;
};

const sandbox = { console : console, document : doc, Nino : Nino };
sandbox.window = {
	Nino : Nino,
	// What a dialog of the design system is not: the browser's
	confirm : function() { throw new Error('the page asks with the dialog of the design system and never with the browser\'s confirm()') },
	alert : function() { throw new Error('alert') },
	prompt : function() { throw new Error('prompt') },
	matchMedia : function() { return { matches : false } },
	setTimeout : function() { return 0 },
	clearTimeout : function() {},
	location : { reload : function() { reloads.push( 1 ) } },
	localStorage : { getItem : function( key ) { return Object.prototype.hasOwnProperty.call( stored, key ) ? stored[key] : null }, setItem : function( key, value ) { stored[key] = String( value ) } },
};
sandbox.window.sessionStorage = { getItem : function( name ) { return Object.prototype.hasOwnProperty.call( sessionStored, name ) ? sessionStored[name] : null }, setItem : function( name, value ) { sessionStored[name] = String( value ) } };
sandbox.localStorage = sandbox.window.localStorage;
sandbox.sessionStorage = sandbox.window.sessionStorage;
const context = vm.createContext( sandbox );

[ '_admin/assets/Nino.admin.js', '_admin/assets/pagetext.js', '_admin/assets/recovery-text.js', '_admin/assets/recovery.js' ].forEach( function( file ) { load( file, context ) } );

const text = Nino.pageText;

/** Answer the request with that action, the way Nino.http.sendRequest() calls back */
function answer( action, status, body ) {
	const request = requests.slice().reverse().find( function( r ) { return r.action === action } );
	request.callback( { status : status, responseJSON : body } );
}

function sent( action ) {
	return requests.filter( function( r ) { return r.action === action } );
}

const byId = id => findAll( root, el => el.id === id )[0];
const options = () => findAll( byId('recovery-reset-mail'), el => el.tagName === 'OPTION' ).map( option => option.value );
const errorsIn = scope => findAll( scope, el => el.className === 'nino-admin-field-error' ).map( el => el.textContent );
const dialog = () => dialogs[dialogs.length - 1];
const dialogButton = word => findAll( dialog(), el => el.tagName === 'BUTTON' && el.textContent === word )[0];
const dialogButtons = () => findAll( dialog(), el => el.tagName === 'BUTTON' ).map( el => el.textContent );

console.log('Recovery page');

check( 'the template has no inline handler anywhere, and no sentence: its words are keys of the table', /\son[a-z]+=/.test( template ) === false && /data-text="\/_admin\/recovery\//.test( template ) === true );
check( '...every form carries novalidate: the page checks, and the browser\'s own bubble never appears', /<form id="recovery-login"[^>]*\snovalidate/.test( template ) === true );
check( '...the page loads the blocks of the workbench and its own words before its script: the dialog, the fields and the picker of the language are the workbench\'s',
	template.indexOf('Nino.admin.js"') !== -1 && template.lastIndexOf('pagetext.js"') < template.lastIndexOf('recovery-text.js"') && template.lastIndexOf('recovery-text.js"') < template.lastIndexOf('assets/recovery.js"') );

// The page is German until somebody says otherwise: ready() draws it
ready();
check( 'the page speaks German by default: the lang of the page, the title, the main button of the secret form', text.language() === 'de' && doc.documentElement.lang === 'de' && doc.title === Nino.recovery.text.de['/_admin/page/title']
	&& byId('recovery-login-submit').textContent === Nino.recovery.text.de['/_admin/recovery/login/submit'] );
check( '...and offers the other language at the top right: a button of the kind of a link, named in that language', byId('page-language').textContent === 'English' && byId('page-language').getAttribute('lang') === 'en' && byId('page-language').className.includes('nino-admin-linkbutton') );

// The secret form: the field is a block, its error stands under it, the focus goes there
let pwField = byId('recovery-input-pw');
check( 'the secret is a masked field with a star, no autofill of a new password, and the focus', pwField.type === 'password' && pwField.autocomplete === 'current-password' && pwField.focused === true && pwField.getAttribute('aria-required') === 'true' );
fire( loginForm, 'submit' );
check( 'an empty secret is not sent: it is said at the field, which is marked and has the focus', sent('recovery/login').length === 0 && errorsIn( loginForm ).length === 1 && pwField.getAttribute('aria-invalid') === 'true' && pwField.focused === true );
pwField.value = 'wrong';
fire( loginForm, 'submit' );
check( 'a secret is sent as it is typed, and the page says that it is checking', sent('recovery/login').length === 1 && sent('recovery/login')[0].payload.password === 'wrong' && errorsIn( loginForm ).length === 0 );
answer( 'recovery/login', 401, { error : 'wrong password', code : 'recovery_wrong' } );
check( 'a refused secret is said at the field in the words of the table for its code - never the English of the server - and the field is emptied and has the focus',
	errorsIn( loginForm ).join() === Nino.recovery.text.de['/_admin/page/error/recovery-wrong'] && pwField.value === '' && pwField.focused === true );
text.set('en');
check( 'what is said at the field stays when the language changes, said in the other one, at the same field and with the mark', errorsIn( loginForm ).join() === Nino.recovery.text.en['/_admin/page/error/recovery-wrong'] && findAll( loginForm, el => el.tagName === 'INPUT' && el.getAttribute('aria-invalid') === 'true' ).length === 1 );
text.set('de');
check( '...and back', errorsIn( loginForm ).join() === Nino.recovery.text.de['/_admin/page/error/recovery-wrong'] );
pwField = byId('recovery-input-pw');
fire( loginForm, 'submit' );
pwField.value = 'again';
fire( loginForm, 'submit' );
answer( 'recovery/login', 429, { error : 'too many attempts', code : 'recovery_tries' } );
check( '...a lockout says its own sentence', errorsIn( loginForm ).join() === Nino.recovery.text.de['/_admin/page/error/recovery-tries'] );
pwField.value = 'right';
fire( loginForm, 'submit' );
answer( 'recovery/login', 200, { ok : true } );
check( 'the secret that is right reloads the page, which the server then renders with the gate open', reloads.length === 1 );

// The tools: shown only where the server opened the gate
check( 'before the list is there the tools hold nothing of the cards, and with the gate shut there is no request for one', sections.children.length === 0 && sent('recovery/list').length === 0 && tools.classList.contains('admin-hidden') === true );
tools.dataset.open = '1';
Nino.recovery.onReadyTools();
check( 'the page asks for the backups and the accounts when the gate is open', sent('recovery/list').length === 1 && tools.classList.contains('admin-hidden') === false && loginForm.classList.contains('admin-hidden') === true
	&& wrap.className.includes('nino-admin-auth--tools') );

answer( 'recovery/list', 200, { dates : [ '2026-10-02' ], users : [ 'a@example.com', 'b@example.com' ], uploadLimit : 2097152 } );
check( 'the view that was opened has the focus: its heading, so that a reader is not left on the body of a page that loaded again', toolsHeading.focused === true );
check( 'the cards are drawn from the list: a card for the restore, one for the password, one for the account, each a fieldset with its title and the (?) of its help', sections.children.map( el => el.tagName ).join() === 'FIELDSET,FIELDSET,FIELDSET'
	&& sections.children.every( card => findAll( card, el => el.tagName === 'LEGEND' ).length === 1 && findAll( card, el => el.className === 'nino-admin-help' ).length >= 1 ) );
check( 'the select is filled from the list the server sent: a select, so a typo cannot name an account that is not there', options().join(',') === 'a@example.com,b@example.com' && byId('recovery-reset-mail').tagName === 'SELECT' && byId('recovery-reset-submit').disabled === false );
check( '...the foot has the way back to the workbench, which is a link, and the end of the recovery session, which is a button of the kind of a link', byId('recovery-back').tagName === 'A' && byId('recovery-back').href === '/_admin/' && byId('recovery-logout').className.includes('nino-admin-linkbutton') );

// Restore: after the question that the dialog of the design system asks, and the page says what the server said
const restoreRow = findAll( byId('recovery-restore'), el => el.tagName === 'LI' )[0];
const restoreButton = findAll( restoreRow, el => el.tagName === 'BUTTON' )[0];
check( 'an archive of the server is a row of the list of the workbench, with its date and a small button in the red of what takes something away', restoreRow.dataset.row === '2026-10-02' && restoreButton.className.includes('nino-admin-btn-danger') && restoreButton.className.includes('nino-admin-btn--small') );
fire( restoreButton, 'click', { currentTarget : restoreButton } );
check( 'a restore is asked about with a dialog of the design system: a title, the answer worded as the title, Cancel at its left, and the focus opens on Cancel', dialogs.length === 1 && sent('recovery/restore').length === 0
	&& findAll( dialog(), el => el.tagName === 'H2' )[0].textContent === Nino.recovery.text.de['/_admin/recovery/restore/confirm-title'] && dialogButtons().join() === 'Abbrechen,'+ Nino.recovery.text.de['/_admin/recovery/restore/confirm-title']
	&& dialogButton('Abbrechen').focused === true && dialogButton( Nino.recovery.text.de['/_admin/recovery/restore/confirm-title'] ).className.includes('nino-admin-btn-danger') );
check( '...and it names the date it is about', findAll( dialog(), el => el.tagName === 'P' ).some( el => el.textContent.includes('2026-10-02') ) );
fire( dialogButton('Abbrechen'), 'click' );
check( 'Cancel asks nothing of the server, and the focus goes back to the button that asked', sent('recovery/restore').length === 0 && dialog().open === false && restoreButton.focused === true );
fire( restoreButton, 'click', { currentTarget : restoreButton } );
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/restore/confirm-title'] ), 'click' );
check( 'the answer that goes on sends the date of its row', sent('recovery/restore').length === 1 && sent('recovery/restore')[0].payload.date === '2026-10-02' && sent('recovery/restore')[0].payload.key === '' );
answer( 'recovery/restore', 200, { restoredDate : '2026-10-02' } );
check( 'a restore that went through is said by the toast, with the date and nothing more where the backup has nothing to add', findAll( doc.body, el => el.textContent === Nino.recovery.text.de['/_admin/recovery/restore/done'].replace( '%s', '2026-10-02' ) ).length >= 1 );
fire( restoreButton, 'click', { currentTarget : restoreButton } );
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/restore/confirm-title'] ), 'click' );
answer( 'recovery/restore', 200, { restoredDate : '2026-10-02', notice : { code : 'backups_restored_other_version', params : [ '1.8.0', '1.9.0' ] } } );
check( '...and with the notice of a backup from another version: where it comes from and what runs here', findAll( doc.body, el => el.textContent.includes( Nino.recovery.text.de['/_admin/recovery/restore/notice'].replace( '%s', '1.8.0' ).replace( '%s', '1.9.0' ) ) ).length >= 1 );
fire( restoreButton, 'click', { currentTarget : restoreButton } );
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/restore/confirm-title'] ), 'click' );
answer( 'recovery/restore', 200, { restoredDate : '2026-10-02', notice : { code : 'backups_snapshot_raw', params : [ 'vault' ] } } );
check( '...and with the notice that the safety copy of a feature is the state as it was, and with the notice of files that stand beside a database: said, in the sentence of the table of the page',
	findAll( doc.body, el => el.textContent.includes( Nino.recovery.text.de['/_admin/page/error/backups-snapshot-raw'].replace( '%s', 'vault' ) ) ).length >= 1 );
fire( restoreButton, 'click', { currentTarget : restoreButton } );
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/restore/confirm-title'] ), 'click' );
answer( 'recovery/restore', 500, { error : 'decryption failed on the server' } );
check( 'a refused restore without a code of its own says the general sentence with the status - in the line of its card, never the English of the server',
	findAll( byId('recovery-restore'), el => el.className.includes('nino-admin-status') ).map( el => el.textContent ).join('') === Nino.recovery.text.de['/_admin/page/error/failed'].replace( '%s', '500' ) );

// The key of an archive of another project: typed once, sent with the restore of the row, said at its own field
const key = 'A'.repeat( 43 )+ '=';
// What is looked for is the secret itself, as it is typed and as an address would carry it
const keyIn = value => String( value ).indexOf( key.slice( 0, 43 ) ) !== -1;
const carriers = function() {
	const found = [];
	[ root, doc.body ].forEach( top => findAll( top, () => true ).concat( [ top ] ).forEach( el => {
		Object.keys( el.attributes ).forEach( name => found.push( name+ '='+ el.attributes[name] ) );
		Object.keys( el.dataset ).forEach( name => found.push( 'data-'+ name+ '='+ el.dataset[name] ) );
		found.push( el.className ?? '', el.id ?? '', el.textContent ?? '' );
		if( el.type !== 'password' && el.type !== 'text' )
			found.push( el.value ?? '' );
	} ) );
	return found;
};
const keyField = byId('recovery-key');
keyField.value = key;
fire( restoreButton, 'click', { currentTarget : restoreButton } );
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/restore/confirm-title'] ), 'click' );
check( 'a restore from the list sends the key that was typed', sent('recovery/restore').slice( -1 )[0].payload.key === key && sent('recovery/restore').slice( -1 )[0].payload.date === '2026-10-02' );
answer( 'recovery/restore', 422, { error : 'the key does not open the archive', code : 'backups_key_wrong' } );
check( '...a key that did not open it stays in the field to be corrected, and the page says why at that field, where the focus goes', keyField.value === key
	&& errorsIn( byId('recovery-restore') ).join() === Nino.recovery.text.de['/_admin/page/error/backups-key-wrong'] && keyField.getAttribute('aria-invalid') === 'true' && keyField.focused === true );
fire( restoreButton, 'click', { currentTarget : restoreButton } );
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/restore/confirm-title'] ), 'click' );
answer( 'recovery/restore', 200, { restoredDate : '2026-10-02' } );
check( '...and a restore that went through empties it, so that nothing of it stays on a page that is left open', keyField.value === '' );
check( '...and nothing of it is left in the page: in no attribute, no data attribute, no text and no value of an element', carriers().length > 20 && carriers().every( value => keyIn( value ) === false ) );

const showKey = findAll( byId('recovery-restore'), el => el.id === 'recovery-key-show' )[0];
showKey.checked = true;
fire( showKey, 'change' );
check( 'the box shows the key as text', keyField.type === 'text' );
showKey.checked = false;
fire( showKey, 'change' );
check( '...and, off again, hides it', keyField.type === 'password' );

// An archive from a file: the file and the key in one request
const form = byId('recovery-file');
const fileInput = findAll( form, el => el.tagName === 'INPUT' && el.type === 'file' )[0];
const chooseFile = file => { fileInput.files = [ file ]; fire( fileInput, 'change' ) };
const submitFile = () => fire( byId('recovery-file-submit'), 'click' );
const requestsBefore = requests.length;
const fileError = () => errorsIn( form );

check( 'the form of a file is one form of the page, novalidate; its button is the one of what takes something away and is no submit button, which the stylesheet would paint as the main one', form.tagName === 'FORM' && form.noValidate === true
	&& byId('recovery-file-submit').className.includes('nino-admin-btn-danger') && byId('recovery-file-submit').type !== 'submit' );
submitFile();
check( 'no file chosen: nothing is sent, and the page says so at the field of the file', requests.length === requestsBefore && fileError().join() === Nino.recovery.text.de['/_admin/common/error/missing'].replace( '%s', Nino.recovery.text.de['/_admin/recovery/file/label'] ) );
chooseFile( { name : 'backup.php', size : 4096, type : 'application/octet-stream' } );
submitFile();
check( 'no key typed: nothing is sent, and the page says so at the field of the key, which has the focus', requests.length === requestsBefore && errorsIn( byId('recovery-restore') ).join() === Nino.recovery.text.de['/_admin/page/error/backups-key-missing'] && keyField.focused === true );
keyField.value = key;
chooseFile( { name : 'backup.php', size : 2097152, type : 'application/octet-stream' } );
check( 'a file over what the server takes is refused at the field, by the block, with the limit in megabytes - and is not taken', fileError().join() === Nino.recovery.text.de['/_admin/common/error/file-too-large'].replace( '%s', '2' ) );
check( '...the way round it is the note of the field and stays in view: SFTP, into private/.backups', findAll( form, el => el.className.includes('nino-admin-field-note') ).map( el => el.textContent ).join().includes('SFTP') );
chooseFile( { name : 'backup.php', size : 4096, type : 'application/octet-stream' } );
submitFile();
check( 'asked first, with the dialog and not the browser\'s question, and not sent before it is answered', requests.length === requestsBefore && dialog().open === true );
fire( dialogButton('Abbrechen'), 'click' );
check( '...a Cancel sends nothing', requests.length === requestsBefore );
submitFile();
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/file/confirm-title'] ), 'click' );
const fileRequest = sent('recovery/restore-file')[0];
check( 'sent as one request with the file and the key', fileRequest !== undefined && fileRequest.file.name === 'backup.php' && fileRequest.payload.key === key );
answer( 'recovery/restore-file', 413, { error : 'too large', code : 'backups_too_large', params : [ 2 ] } );
check( '...a refusal about the file is said at the file, with what the server put into it - a number - and keeps key and file for another try', fileError().join() === Nino.recovery.text.de['/_admin/page/error/backups-too-large'].replace( '%s', '2' ) && keyField.value === key );
submitFile();
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/file/confirm-title'] ), 'click' );
answer( 'recovery/restore-file', 422, { error : 'the key does not open the archive', code : 'backups_key_wrong' } );
check( '...one about the key is said at the key', errorsIn( byId('recovery-restore') ).join().includes( Nino.recovery.text.de['/_admin/page/error/backups-key-wrong'] ) );
submitFile();
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/file/confirm-title'] ), 'click' );
answer( 'recovery/restore-file', 200, { restoredDate : 'uploaded archive' } );
check( '...a restore that went through says so and empties the key and the file', keyField.value === '' && fileInput.value === '' );

// Set a password: the account is the one that is selected; what is wrong is said at the field
const resetForm = byId('recovery-reset');
const resetPw = byId('recovery-reset-pw');
byId('recovery-reset-mail').value = 'b@example.com';
fire( resetForm, 'submit' );
check( 'an empty password is said at its field in the wording of the workbench, and nothing is sent', sent('recovery/reset').length === 0 && errorsIn( resetForm ).join() === Nino.recovery.text.de['/_admin/common/error/missing'].replace( '%s', Nino.recovery.text.de['/_admin/recovery/reset/pw'] ) && resetPw.focused === true );
resetPw.value = 'short';
fire( resetForm, 'submit' );
check( '...a password under the length the server states in the page is said too, with the length', sent('recovery/reset').length === 0 && errorsIn( resetForm ).join() === Nino.recovery.text.de['/_admin/recovery/error/short'].replace( '%s', '8' ) );
resetPw.value = 'a new password';
fire( resetForm, 'submit' );
check( 'setting a password sends the selected account', sent('recovery/reset').length === 1 && sent('recovery/reset')[0].payload.mail === 'b@example.com' && sent('recovery/reset')[0].payload.pw === 'a new password' && errorsIn( resetForm ).length === 0 );
answer( 'recovery/reset', 200, { mail : 'b@example.com', created : false } );
check( '...and says so by the toast, with the password field emptied', resetPw.value === '' && findAll( doc.body, el => el.textContent === Nino.recovery.text.de['/_admin/recovery/reset/done'] ).length >= 1 );

resetPw.value = 'another password';
fire( resetForm, 'submit' );
answer( 'recovery/reset', 404, { error : 'unknown account', code : 'recovery_unknown_account' } );
check( 'a refusal is said in the line of its card, in the words of the table for its code', findAll( byId('recovery-reset-card'), el => el.className.includes('nino-admin-status') ).map( el => el.textContent ).join('') === Nino.recovery.text.de['/_admin/page/error/recovery-unknown-account'] );

// Create: only after the repeat matches and the question was answered yes
const createForm = byId('recovery-create');
const createMail = byId('recovery-create-mail');
const createPw = byId('recovery-create-pw');
const createRepeat = byId('recovery-create-repeat');
fire( createForm, 'submit' );
check( 'an empty form is said at every field that is empty, and the focus goes to the first', sent('recovery/create').length === 0 && errorsIn( createForm ).length === 3 && createMail.focused === true );
createMail.value = 'new@example.com';
createPw.value = 'a fresh password';
createRepeat.value = 'a fresh passwort';
fire( createForm, 'submit' );
check( 'a repeat that differs sends nothing and asks nothing: it is said at the repeat', sent('recovery/create').length === 0 && errorsIn( createForm ).join() === Nino.recovery.text.de['/_admin/recovery/error/mismatch'] && createRepeat.focused === true );

createRepeat.value = 'a fresh password';
const dialogsBefore = dialogs.length;
fire( createForm, 'submit' );
check( 'with the repeat right the dialog asks, and names the address and that it is full access', dialogs.length === dialogsBefore + 1 && findAll( dialog(), el => el.tagName === 'P' ).some( el => el.textContent === Nino.recovery.text.de['/_admin/recovery/create/confirm'].replace( '%s', 'new@example.com' ) ) );
fire( dialogButton('Abbrechen'), 'click' );
check( 'an account is not created when the question is answered no', sent('recovery/create').length === 0 );

fire( createForm, 'submit' );
const listsBefore = sent('recovery/list').length;
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/create/confirm-title'] ), 'click' );
check( 'answered yes, it sends the account with confirm true', sent('recovery/create').length === 1 && sent('recovery/create')[0].payload.confirm === true
	&& sent('recovery/create')[0].payload.mail === 'new@example.com' && sent('recovery/create')[0].payload.pw === 'a fresh password' );
answer( 'recovery/create', 200, { mail : 'new@example.com', created : true } );
check( '...says it was created, with both password fields emptied', createPw.value === '' && createRepeat.value === '' && findAll( doc.body, el => el.textContent === Nino.recovery.text.de['/_admin/recovery/create/done'] ).length >= 1 );
check( '...and asks for the list again, so the new account is there to pick', sent('recovery/list').length === listsBefore + 1 );
resetPw.value = 'typed before';
answer( 'recovery/list', 200, { dates : [], users : [ 'a@example.com', 'b@example.com', 'new@example.com' ] } );
check( '...which the select then offers, while what was typed in the other cards stays', options().join(',') === 'a@example.com,b@example.com,new@example.com' && byId('recovery-reset-pw').value === 'typed before' );

createMail.value = 'exists@example.com';
createPw.value = 'a fresh password';
createRepeat.value = 'a fresh password';
fire( createForm, 'submit' );
fire( dialogButton( Nino.recovery.text.de['/_admin/recovery/create/confirm-title'] ), 'click' );
answer( 'recovery/create', 409, { error : 'account exists - use reset', code : 'recovery_exists' } );
check( 'a refusal about the address is said at the address, which has the focus', errorsIn( createForm ).join() === Nino.recovery.text.de['/_admin/page/error/recovery-exists'] && createMail.focused === true );

// The language: the other one, drawn at once, kept over a reload, with what was typed
createMail.value = 'typed@example.com';
fire( byId('page-language'), 'click' );
check( 'the other language is drawn at once: the lang of the page, its title, the cards, the dialog\'s words - and what was typed stays', text.language() === 'en' && doc.documentElement.lang === 'en' && doc.title === Nino.recovery.text.en['/_admin/page/title']
	&& byId('recovery-create-submit').textContent === Nino.recovery.text.en['/_admin/recovery/create/action'] && byId('recovery-create-mail').value === 'typed@example.com' && byId('recovery-reset-pw').value === 'typed before' );
check( '...the choice is kept, and now it offers German', stored['nino-admin-page-language'] === 'en' && byId('page-language').textContent === 'Deutsch' && byId('page-language').getAttribute('lang') === 'de' );
fire( byId('recovery-restore').querySelectorAll('li')[0] ? findAll( byId('recovery-restore'), el => el.tagName === 'BUTTON' && el.className.includes('nino-admin-btn-danger') )[0] : null, 'click' );
check( '...and a question asked after it is asked in that language', dialogButtons().join() === 'Cancel,'+ Nino.recovery.text.en['/_admin/recovery/restore/confirm-title'] || sent('recovery/restore').length > 0 );

// What was typed as the key of an archive is in its field and in the one request it was typed for: not in the address, not in the storage of the browser, in no other request
check( 'nothing of the key is kept: not in the address of the page, not in the address of any request, not in the storage of the browser - local and session - and in no request but the two that restore',
	Object.keys( stored ).join() === 'nino-admin-page-language' && Object.keys( sessionStored ).length === 0 && Object.values( stored ).every( value => keyIn( value ) === false )
	&& Object.keys( sandbox.window.location ).join() === 'reload' && requests.every( r => keyIn( r.uri ) === false )
	&& requests.filter( r => r.action !== 'recovery/restore' && r.action !== 'recovery/restore-file' ).every( r => JSON.stringify( r.payload ).indexOf( key ) === -1 ) );
check( '...and still not after the page was drawn again in another language', carriers().every( value => keyIn( value ) === false ) );

// The logout
fire( byId('recovery-logout'), 'click' );
answer( 'recovery/logout', 200, { ok : true } );
check( 'closing the recovery session reloads the page, which the server renders with the gate shut', reloads.length === 2 );

// Nobody to pick
requests.length = 0;
Nino.recovery.onReadyTools();
answer( 'recovery/list', 200, { dates : [], users : [] } );
check( 'an empty list gives a select that says so and a button that is off, and a sentence in place of the list of archives', options().join(',') === '' && findAll( byId('recovery-reset-mail'), el => el.tagName === 'OPTION' )[0].textContent === Nino.recovery.text.en['/_admin/recovery/reset/accounts-none'] && byId('recovery-reset-submit').disabled === true );
check( '...and a list that could not be loaded says why in the line of the card of the restore', ( function() {
	requests.length = 0;
	Nino.recovery.onReadyTools();
	answer( 'recovery/list', 500, { error : 'English of the server' } );
	return findAll( byId('recovery-restore'), el => el.className.includes('nino-admin-status') ).map( el => el.textContent ).join('') === Nino.recovery.text.en['/_admin/page/error/failed'].replace( '%s', '500' );
} )() );

done();
