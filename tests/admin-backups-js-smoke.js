/**
 *	Nino										A compact filesystembased php framework
 *	admin-backups-js-smoke.js	DOM-light checks for the Backups panel's script
 *													(_admin/Nino/Modules/Backups/assets/admin.js).
 *
 *													This is the screen somebody opens when the site is
 *													already broken, so what it does with a refusal
 *													matters more here than anywhere else: a restore the
 *													server did not carry out changed nothing, and every
 *													date on the list is still a date worth trying. The
 *													checks below are about the list surviving that.
 *
 *	Usage: node tests/admin-backups-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, documentOf, element, findAll, fire } = require( './harness.js' );

/** The restore buttons currently on screen, in the order they stand in */
function dateRows() {
	const list = findAll( mount, function( el ) { return el.id === 'backups-dates' } )[0];
	return list === undefined ? [] : list.children.map( function( li ) { return li.children[0].textContent } );
}

function restoreButton( date ) {
	const list = findAll( mount, function( el ) { return el.id === 'backups-dates' } )[0];
	if( list === undefined )
		return null;
	const row = list.children.find( function( li ) { return li.children[0].textContent === date } );
	return row === undefined ? null : row.children[1];
}

/**
 *	Press the restore button of a date and answer the question it asks - yes or
 *	no as `confirmAnswer` says. The question is a dialog of the design system
 *	(Nino.adminUi.confirm()), and it is taken out of the list the checks below
 *	count: they are about the dialog the restore ends in
 */
function pressRestore( date ) {
	const before = dialogs.length;
	fire( restoreButton( date ), 'click' );
	if( dialogs.length === before )
		return;
	const question = dialogs.pop();
	fire( findAll( question, function( el ) { return el.tagName === 'BUTTON' } )[confirmAnswer === true ? 0 : 1], 'click' );
}

function messageLine() {
	return findAll( mount, function( el ) { return el.id === 'backups-message' } )[0] ?? null;
}

function messageText() {
	const line = messageLine();
	return line === null ? '' : line.textContent;
}

function messageClass() {
	const line = messageLine();
	return line === null ? '' : line.className;
}


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Backups/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'backups-list';

const requests = [];
const alerts = [];
const dialogs = [];
let reloaded = 0;
let confirmAnswer = true;

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
	document : documentOf( mount ),
	Nino : Nino,
};
// The workbench root a dialog is put in, and a dialog that opens modally and closes the way the browser's does
sandbox.document.body = element('body');
sandbox.document.createElement = function( tag ) {
	const created = element( tag );
	if( tag === 'dialog' ) {
		created.open = false;
		created.showModal = function() { created.open = true };
		created.close = function() { created.open = false; fire( created, 'close' ) };
		dialogs.push( created );
	}
	return created;
};
sandbox.window = {
	Nino : Nino,
	confirm : function() { throw new Error( 'the restore asks in a dialog of the design system, not with the browser\'s confirm' ) },
	alert : function( message ) { alerts.push( message ) },
	location : { reload : function() { reloaded++ } },
};

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Backups/assets/admin.js', context );

const panel = Nino.admin.backups;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

console.log('Admin Backups');

panel.init();
check( 'the panel asks for the list of dates', requests.length === 1 && requests[0].action === 'backups/list' );

answer( 200, { dates : [ '2024-05-03', '2024-05-02', '2024-05-01' ] } );
check( 'every date is a row with a restore button', dateRows().join(',') === '2024-05-03,2024-05-02,2024-05-01'
	&& restoreButton('2024-05-02') !== null );
check( 'the list carries the line an error reports into', messageLine() !== null
	&& messageLine().attributes['aria-live'] === 'polite' && messageLine().textContent === '' );

/*	A restore the server refused. Nothing was overwritten - the snapshot the
	panel offers is still exactly as valid as it was a second ago - so the
	list has to survive it, or the one screen for getting a broken site back
	has replaced its backups with a sentence and only a reload undoes that	*/
pressRestore('2024-05-02');
check( 'the restore is sent for the date whose button was pressed', requests.length === 2
	&& requests[1].action === 'backups/restore' && requests[1].payload.date === '2024-05-02' );

answer( 500, { error : 'the backup key is missing' } );
check( 'a refused restore keeps every date on the list', dateRows().join(',') === '2024-05-03,2024-05-02,2024-05-01' );
check( '...and keeps the buttons that are the way to try again', restoreButton('2024-05-01') !== null );
check( '...and reports the status and the reason the server gave', messageText() === '(500) the backup key is missing'
	&& messageClass() === 'nino-admin-error' );
check( '...and does not pretend the restore happened', alerts.length === 0 && reloaded === 0 );

// An answer with no reason of its own falls back to a sentence of the
// workbench's own rather than to an empty line
pressRestore('2024-05-01');
answer( 503, null );
check( 'an answer with no reason still says what happened', messageText() === '(503) '+ text('/_admin/common/error/request')
	&& text('/_admin/common/error/request') !== '' );
check( '...and the list is still there after the second refusal too', dateRows().length === 3 );

// What the person is asked before it, and what is said after: the restore ends the session

const dialogText = function( dialog ) { return findAll( dialog, function( el ) { return el.id === 'nino-admin-choice-text' } )[0].textContent };
const dialogButtons = function( dialog ) { return findAll( dialog, function( el ) { return el.tagName === 'BUTTON' } ) };

// A restore that worked: everything the workbench has in memory is about the state that was
// just replaced, and the sessions with it - the reload lands on the login page. What it says
// is read in a dialog first, and the reload is the dialog's one choice, not a timer
pressRestore('2024-05-03');
answer( 200, { ok : true } );
check( 'a restore that succeeded opens a dialog with the date in its message - and neither an alert nor a reload', dialogs.length === 1 && dialogs[0].open === true && alerts.length === 0 && reloaded === 0
	&& dialogText( dialogs[0] ) === text('/_admin/backups/msg/restored').replace( '%s', '2024-05-03' ) );
const loginLabelDe = fills('_admin/Nino/Modules/Backups/text/de_DE.php')['/_admin/backups/label/login'];
check( '...whose one choice is the way to the login: the label the module has for it, in both languages', dialogButtons( dialogs[0] ).length === 1 && dialogButtons( dialogs[0] )[0].textContent === text('/_admin/backups/label/login')
	&& text('/_admin/backups/label/login') !== '' && typeof loginLabelDe === 'string' && loginLabelDe !== '' && loginLabelDe !== text('/_admin/backups/label/login') );
check( '...and the page stays as it is until it is chosen', reloaded === 0 && dialogs[0].open === true );
fire( dialogButtons( dialogs[0] )[0], 'click' );
check( 'the choice reloads the page, once, and closes the dialog', reloaded === 1 && dialogs[0].open === false );

// A backup of another version of Nino restores all the same. Its notice - a
// code and its params, worded the way a failure's is - is said with the restore
// in the one message the page shows before it reloads: a line in the panel would
// be gone with the reload
alerts.length = 0;
reloaded = 0;
pressRestore('2024-05-03');
answer( 200, { ok : true, restoredDate : '2024-05-03', notice : { code : 'backups_restored_other_version', params : [ '1.8.0', '1.9.0' ] } } );
check( 'a restore of a backup from another version says where it comes from, in the message of the restore, and reloads only on the choice', dialogs.length === 2 && reloaded === 0 && alerts.length === 0
	&& dialogText( dialogs[1] ) === text('/_admin/backups/msg/restored').replace( '%s', '2024-05-03' ) + ' '+ text('/_admin/error/backups_restored_other_version').replace( '%s', '1.8.0' ).replace( '%s', '1.9.0' ) );
fire( dialogButtons( dialogs[1] )[0], 'click' );
check( '...and then does', reloaded === 1 );
check( '...from the words of the Backups module, which has them for the code', text('/_admin/error/backups_restored_other_version') === 'The backup comes from Nino %s; this is %s.' );

/*	"Back up now": one more archive on request. It is on the list screen
	whatever the list holds, off while it runs, and says what it made once
	the list shows it	*/
function nowButton() {
	return findAll( mount, function( el ) { return el.id === 'backups-now' } )[0] ?? null;
}

check( 'the list carries a Back up now button, the primary action', nowButton() !== null && nowButton().className === 'nino-admin-btn-primary'
	&& nowButton().textContent === text('/_admin/backups/label/now') && text('/_admin/backups/label/now') === 'Back up now' );
check( '...in the action bar the other panels put theirs in', findAll( mount, function( el ) { return el.className === 'nino-admin-actionbar nino-admin-list-actions' } ).length === 1 );

requests.length = 0;
fire( nowButton(), 'click' );
check( 'a click sends backups/now, with the button off while it runs', requests.length === 1 && requests[0].action === 'backups/now' && nowButton().disabled === true );

answer( 200, { id : '2024-05-03-101500' } );
check( 'on 200 the list is requested again', requests.length === 2 && requests[1].action === 'backups/list' && nowButton().disabled === false );
answer( 200, { dates : [ '2024-05-03-101500', '2024-05-03', '2024-05-02', '2024-05-01' ], enabled : true } );
check( '...and shows the new archive first, and says what was made', dateRows().join(',') === '2024-05-03-101500,2024-05-03,2024-05-02,2024-05-01'
	&& messageText() === 'Backup created: 2024-05-03-101500' );

requests.length = 0;
fire( nowButton(), 'click' );
answer( 500, { error : 'backup encryption key is invalid' } );
check( 'a refused backup keeps the list and shows the status and the reason', dateRows().length === 4 && messageText() === '(500) backup encryption key is invalid'
	&& messageClass() === 'nino-admin-error' && requests.length === 1 );
check( '...and gives the button back for another try', nowButton() !== null && nowButton().disabled === false );
fire( nowButton(), 'click' );
check( 'the next attempt clears the old error first', messageText() === '' && messageClass() === '' );
answer( 409, { error : 'backups are switched off' } );
check( 'a refusal with another status says that one', messageText() === '(409) backups are switched off' );

// An empty list: still the way to make the first archive
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { dates : [], enabled : true } );
check( 'an empty list has the button too, and the line under it', nowButton() !== null && dateRows().length === 0 && messageLine() !== null );

// Backups switched off: the server would refuse, so there is nothing to press
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { dates : [ '2024-05-03' ], enabled : false } );
check( 'while backups are switched off there is no button, and the list is as it was', nowButton() === null && dateRows().join(',') === '2024-05-03' );

// The other failure, which is not this one: a list that could not be loaded
// has nothing to keep, so the error takes the place of it
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 403, { error : 'no permission' } );
check( 'a list that could not be loaded is replaced by the error, having nothing to keep',
	mount.children.length === 1 && mount.children[0].className === 'nino-admin-error'
	&& mount.children[0].textContent === '(403) no permission' );

// A restore ends in a reload of the page and every form on it: whatever the
// workbench holds unsaved is asked about first, after the restore was confirmed
mount.innerHTML = '';
requests.length = 0;
alerts.length = 0;
reloaded = 0;
panel.init();
answer( 200, { dates : [ '2024-05-03' ] } );
const asked = [];
Nino.admin.dirty = { guard : function( names, proceed ) { asked.push( { names : names, proceed : proceed } ) } };
confirmAnswer = false;
pressRestore('2024-05-03');
check( 'a restore that was not confirmed asks the shell nothing', asked.length === 0 && requests.length === 1 );
confirmAnswer = true;
pressRestore('2024-05-03');
check( 'a confirmed restore asks about unsaved input anywhere in the workbench before it is sent', asked.length === 1 && asked[0].names === null && requests.length === 1 );
asked[0].proceed();
check( '...and is sent once the question is answered', requests.length === 2 && requests[1].action === 'backups/restore' );
answer( 200, { ok : true } );
check( '...and reloads only on the choice of the dialog it ends in', dialogs.length === 3 && reloaded === 0 );
fire( dialogButtons( dialogs[2] )[0], 'click' );
check( '...which is the one that reloads', reloaded === 1 );
delete Nino.admin.dirty;

done();
