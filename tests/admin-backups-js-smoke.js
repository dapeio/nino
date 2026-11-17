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

/** One entry of the list the server answers, from its id */
function archive( id, size ) {
	return { id : id, kind : id.indexOf('pre-restore-') === 0 ? 'snapshot' : ( /^\d{4}-\d{2}-\d{2}-\d{6}/.test( id ) ? 'manual' : 'daily' ), size : size || 2048 };
}

/** The restore buttons currently on screen, in the order they stand in */
function dateRows() {
	const list = findAll( mount, function( el ) { return el.id === 'backups-dates' } )[0];
	return list === undefined ? [] : list.children.map( function( li ) { return li.children[0].children[0].textContent } );
}

function restoreButton( date ) {
	const list = findAll( mount, function( el ) { return el.id === 'backups-dates' } )[0];
	if( list === undefined )
		return null;
	const row = list.children.find( function( li ) { return li.children[0].children[0].textContent === date } );
	return row === undefined ? null : row.children[1].children[1];
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
	fire( findAll( question, function( el ) { return el.tagName === 'BUTTON' } )[confirmAnswer === true ? 1 : 0], 'click' );
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
const posts = [];
let reloaded = 0;
let confirmAnswer = true;

const Nino = {
	admin : {},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), file : data.file, callback : callback } );
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
	// A form that posts into a frame: what it would have sent is kept
	if( tag === 'form' )
		created.submit = function() {
			const fields = {};
			created.children.forEach( function( input ) { fields[input.name] = input.value } );
			posts.push( { action : created.action, target : created.target, method : created.method, fields : fields } );
		};
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
	// The lists below are those of an account with full access, unless a check says otherwise
	if( body !== null && typeof body === 'object' && Array.isArray( body.archives ) === true && ( 'full' in body ) === false )
		body.full = true;
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

console.log('Admin Backups');

panel.init();
check( 'the panel asks for the list of dates', requests.length === 1 && requests[0].action === 'backups/list' );

answer( 200, { archives : [ archive('2024-05-03'), archive('2024-05-02'), archive('2024-05-01') ] } );
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
answer( 200, { archives : [ archive('2024-05-03-101500'), archive('2024-05-03'), archive('2024-05-02'), archive('2024-05-01') ], enabled : true } );
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
answer( 200, { archives : [], enabled : true } );
check( 'an empty list has the button too, and the line under it', nowButton() !== null && dateRows().length === 0 && messageLine() !== null );

// Backups switched off: the server would refuse, so there is nothing to press
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : false } );
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

// Today's automatic backup did not run: the panel says so above the list, with the
// reason the server logged, and the button that makes one is still there
const noticeOf = function() {
	return findAll( mount, function( el ) { return el.className === 'nino-admin-notice' } );
};
const bait = '<script>x</script> [[/a/b/c/d]] {x}';
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : true, missed : { reason : bait } } );
check( 'a missed daily backup is a notice above the list, with the reason as text', noticeOf().length === 1 && noticeOf()[0].textContent.includes( bait )
	&& noticeOf()[0].children.length === 0 && nowButton() !== null && dateRows().join(',') === '2024-05-03' );

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [], enabled : true, missed : { reason : '' } } );
check( 'a missed one that ended without a reason still says something', noticeOf().length === 1 && noticeOf()[0].textContent !== ''
	&& nowButton() !== null );

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : true, missed : null } );
check( 'with nothing missed there is no notice', noticeOf().length === 0 );

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : true, missed : { reason : 'x' } } );
fire( nowButton(), 'click' );
answer( 200, { id : '2024-05-03-120000' } );
answer( 200, { archives : [ archive('2024-05-03-120000'), archive('2024-05-03') ], enabled : true, missed : null } );
check( 'a backup made by hand takes the notice away with the list it re-reads', noticeOf().length === 0 && dateRows().length === 2 );

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [], enabled : false, missed : { reason : 'x' } } );
check( 'while backups are switched off nothing is said to be missed', noticeOf().length === 0 );

// A run that ended without an end of its own - the daily one or one by hand - is said once, with
// the time it began, the work it had and, where the server said so, what ended it
const stopped = function( cause, kind ) {
	return { kind : kind, started : 1700000000, files : 2701, bytes : 223346688, cause : cause, limit : cause === 'memory' ? 134217728 : 30 };
};
const numbers = [ '2701', '213' ];
const said = function( notices ) {
	return notices.length === 1 && numbers.every( function( n ) { return notices[0].textContent.includes( n ) } );
};
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : true, missed : null, stopped : stopped( 'time', 'manual' ) } );
const manualTime = noticeOf().length === 1 ? noticeOf()[0].textContent : '';
check( 'a run by hand that the time limit ended is a notice with the size and the number of files it had, and the limit', said( noticeOf() ) && noticeOf()[0].textContent.includes( '30' ) && nowButton() !== null );

const memoryText = function() { return noticeOf()[0].textContent };
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : true, missed : null, stopped : stopped( 'memory', 'manual' ) } );
check( '...the memory limit is told in megabytes', said( noticeOf() ) && memoryText().includes( '128' ) );

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : true, missed : null, stopped : stopped( '', 'manual' ) } );
const openText = memoryText();
check( '...and a run that nothing says why it ended is told as one that did not end', said( noticeOf() ) && openText !== '' );

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : true, missed : { reason : '' }, stopped : stopped( 'time', 'daily' ) } );
const dailyText = memoryText();
check( 'a missed daily backup that has no reason of its own is that run, said once, with the way out', said( noticeOf() ) && dailyText.includes( '30' ) && dailyText.length > manualTime.length );

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : true, missed : { reason : 'the backup needs about 443 MB' }, stopped : stopped( 'time', 'daily' ) } );
check( 'a missed one with a reason of its own and a run that ended are two notices', noticeOf().length === 2 );

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : false, missed : null, stopped : stopped( 'time', 'manual' ) } );
check( 'while backups are switched off no run is said to have ended', noticeOf().length === 0 );

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ], enabled : true, missed : null, stopped : null } );
check( 'nothing stopped, nothing said', noticeOf().length === 0 );

// A restore ends in a reload of the page and every form on it: whatever the
// workbench holds unsaved is asked about first, after the restore was confirmed
mount.innerHTML = '';
requests.length = 0;
alerts.length = 0;
reloaded = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03') ] } );
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

// One restore at a time: while one runs nothing on the list can start another,
// a refusal gives the list back, and the 409 of a restore already running says
// so in the words of the module
function listButtons() {
	return findAll( mount, function( el ) { return el.tagName === 'BUTTON' } );
}

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03'), archive('2024-05-02') ] } );
pressRestore('2024-05-03');
check( 'while a restore runs every button of the list is off', requests.length === 2 && requests[1].action === 'backups/restore'
	&& listButtons().length === 6 && listButtons().every( function( button ) { return button.disabled === true } ) );
answer( 409, { error : 'a backup or a restore is running', code : 'backups_busy' } );
check( '...and a refusal gives them back', listButtons().every( function( button ) { return button.disabled !== true } ) );
check( '...saying that one is running, in the words of the module', text('/_admin/error/backups_busy') !== ''
	&& messageText().indexOf( text('/_admin/error/backups_busy') ) !== -1 && messageClass() === 'nino-admin-error' );

pressRestore('2024-05-02');
answer( 200, { ok : true, restoredDate : '2024-05-02' } );
check( 'a restore that went through leaves them off for the reload', listButtons().every( function( button ) { return button.disabled === true } ) );

// --- Download, upload, and what the rows say ----------------------------------

mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive( '2024-05-03-101500-manual', 3145728 ), archive( '2024-05-02-090000', 51200 ), archive( '2024-05-03', 700 ), archive( 'pre-restore-2024-05-03-101600', 1048576 ) ], enabled : true, uploadLimit : 2097152 } );

const rowOf = function( id ) {
	const list = findAll( mount, function( el ) { return el.id === 'backups-dates' } )[0];
	return list.children.find( function( li ) { return li.children[0].children[0].textContent === id } );
};
const metaOf = function( id ) { return rowOf( id ).children[0].children[1].textContent };

check( 'a row says how the archive came to be in words, with the name that is its date and time, and how large the file is', metaOf('2024-05-03-101500-manual').split(' · ')[0] === text('/_admin/backups/kind/manual')
	&& metaOf('2024-05-03-101500-manual').split(' · ')[2] === '3 MB' && text('/_admin/backups/kind/manual') === 'Manual' );
check( '...a daily one as daily, in kilobytes below a megabyte, and a snapshot as the one before a restore', metaOf('2024-05-03').split(' · ')[0] === 'Daily' && metaOf('2024-05-03').split(' · ')[2] === '1 KB'
	&& metaOf('2024-05-02-090000').split(' · ')[0] === 'Manual' && metaOf('2024-05-02-090000').split(' · ')[2] === '50 KB' && metaOf('pre-restore-2024-05-03-101600').split(' · ')[0] === text('/_admin/backups/kind/snapshot') );
check( '...and the date and the time of its name in the language of the page', metaOf('2024-05-03-101500-manual').split(' · ')[1] !== '' && metaOf('2024-05-03').split(' · ')[1] !== '' && /10/.test( metaOf('2024-05-03-101500-manual').split(' · ')[1] ) );
check( 'every row has its two buttons, download and restore', rowOf('2024-05-03').children[1].children.length === 2
	&& rowOf('2024-05-03').children[1].children[0].textContent === text('/_admin/backups/label/download') && rowOf('2024-05-03').children[1].children[1].textContent === text('/_admin/common/label/restore') );

const hintTexts = findAll( mount, function( el ) { return el.className === 'nino-admin-hint' } ).map( function( el ) { return el.textContent } );
check( 'the list tells that an archive is encrypted and where the key is kept, once', hintTexts.filter( function( t ) { return t === text('/_admin/backups/hint/key') } ).length === 1 && text('/_admin/backups/hint/key').indexOf('private/.auth/backup-key.php') !== -1 );
check( '...and how large an upload can be, with the way round it', hintTexts.indexOf( text('/_admin/backups/hint/upload').replace( '%s', '2' ) ) !== -1 && text('/_admin/backups/hint/upload').indexOf('SFTP') !== -1 );

// Download: the workbench posts, with its token, into a frame; nothing is read into the page
const token = element('input');
token.setAttribute( 'name', '_csrf' );
token.value = 'token-of-the-page';
mount.appendChild( token );
fire( rowOf('2024-05-02-090000').children[1].children[0], 'click' );
check( 'a click on Download posts the action and the id to the workbench, into a frame of its own, and sends no request through the api', posts.length === 1 && requests.length === 1
	&& posts[0].method === 'post' && posts[0].target === 'backups-download' && posts[0].fields.action === 'backups/download' && JSON.parse( posts[0].fields.data ).date === '2024-05-02-090000' );
check( '...with the token of the page, which the guard of the workbench asks of every post', posts[0].fields._csrf === 'token-of-the-page' );
check( '...the form is not left in the page', findAll( sandbox.document.body, function( el ) { return el.tagName === 'FORM' } ).length === 0 );

// What the frame loads when the server refuses: the sentence of its code, under the list, which keeps standing
const frame = element('iframe');
frame.id = 'backups-download';
frame.contentWindow = { location : { href : 'http://localhost/_admin/' } };
frame.contentDocument = { body : { textContent : JSON.stringify( { error : 'unknown backup date', code : 'backups_gone' } ) } };
mount.appendChild( frame );
panel._downloading = true;
panel._downloaded();
check( 'a refused download is said below the list, in the words of its code, and the list stays', messageText() === text('/_admin/error/backups_gone') && text('/_admin/error/backups_gone') !== '' && messageClass() === 'nino-admin-error' && dateRows().length === 4 );
frame.contentDocument = { body : { textContent : JSON.stringify( { error : 'x', code : 'no_such_code_here' } ) } };
panel._downloading = true;
panel._downloaded();
check( '...a code the words do not know is the sentence for a request that failed, never the raw text', messageText() === text('/_admin/common/error/request') );
frame.contentWindow = { location : { href : 'about:blank' } };
frame.contentDocument = { body : { textContent : '' } };
panel._downloading = true;
panel._downloaded();
check( 'the empty page a frame starts with is no answer: the frame is waited for', panel._downloading === true );
panel._downloading = false;
panel._downloaded();
check( 'a frame that loads when nothing was asked for says nothing', messageText() === text('/_admin/common/error/request') );

// Upload
const fileInput = function() { return findAll( mount, function( el ) { return el.id === 'backups-file' } )[0] };
const uploadButton = function() { return findAll( mount, function( el ) { return el.id === 'backups-upload' } )[0] };
let clicked = 0;
fileInput().click = function() { clicked++ };
fire( uploadButton(), 'click' );
check( 'the upload button opens the file choice, which is a file input that takes the files of php and is no tab stop of its own', clicked === 1 && fileInput().type === 'file' && fileInput().attributes.accept === '.php' && fileInput().hidden === true );
check( '...beside Back up now in the action bar', findAll( mount, function( el ) { return el.className === 'nino-admin-actionbar nino-admin-list-actions' } )[0].children.length === 2 );

const choose = function( name, size ) {
	fileInput().files = [ { name : name, size : size } ];
	fileInput().value = 'C:\\fakepath\\'+ name;
	fire( fileInput(), 'change' );
};

requests.length = 0;
choose( 'backup-x-2024-01-01.php', 3 * 1048576 );
check( 'a file larger than the server takes is not sent: the way round it is said at once, with the limit', requests.length === 0 && messageClass() === 'nino-admin-error'
	&& messageText() === text('/_admin/error/backups_too_large').replace( '%s', '2' ) && text('/_admin/error/backups_too_large').indexOf('SFTP') !== -1 && fileInput().value === '' );
check( '...and the file is let go, so the same one can be chosen again', fileInput().value === '' );

requests.length = 0;
choose( 'backup-x-2024-01-01.php', 4096 );
check( 'a file the server takes is sent as the file of backups/upload, with every button off while it runs', requests.length === 1 && requests[0].action === 'backups/upload' && requests[0].file.name === 'backup-x-2024-01-01.php'
	&& listButtons().every( function( button ) { return button.disabled === true } ) && fileInput().disabled === true && messageText() === text('/_admin/backups/msg/uploading') );
answer( 422, { error : 'x', code : 'backups_upload_key' } );
check( 'a refusal says why in the words of its code, keeps the list and gives the buttons back', messageText() === text('/_admin/error/backups_upload_key') && dateRows().length === 4 && messageClass() === 'nino-admin-error'
	&& listButtons().every( function( button ) { return button.disabled !== true } ) && fileInput().disabled !== true );
check( '...in both languages', Object.keys( fills('_admin/Nino/Modules/Backups/text/de_DE.php') ).filter( function( key ) { return key.indexOf('/_admin/error/backups_upload') !== -1 } ).length === 6
	&& Object.keys( fills('_admin/Nino/Modules/Backups/text/en_US.php') ).filter( function( key ) { return key.indexOf('/_admin/error/backups_upload') !== -1 } ).length === 6 );

requests.length = 0;
choose( '2024-01-01.php', 4096 );
answer( 200, { id : '2024-01-01', kind : 'daily', size : 4096, drops : true, keep : 3 } );
check( 'an upload that went through asks for the list again', requests.length === 2 && requests[1].action === 'backups/list' );
answer( 200, { archives : [ archive('2024-05-03'), archive('2024-01-01', 4096) ], enabled : true, uploadLimit : 2097152 } );
check( '...shows the archive in it and says so, with the warning that the next backup deletes it, since it is over the limit', dateRows().join(',') === '2024-05-03,2024-01-01'
	&& messageText() === text('/_admin/backups/msg/uploaded').replace( '%s', '2024-01-01' )+ ' '+ text('/_admin/backups/msg/drops').replace( '%s', '3' ) );

requests.length = 0;
choose( '2024-01-02.php', 4096 );
answer( 200, { id : '2024-01-02', kind : 'daily', size : 4096, drops : false, keep : 3 } );
answer( 200, { archives : [ archive('2024-05-03'), archive('2024-01-02', 4096) ], enabled : true } );
check( 'an upload that stays says that and nothing more', messageText() === text('/_admin/backups/msg/uploaded').replace( '%s', '2024-01-02' ) );
check( 'an answer that has no limit says the way round for an archive that is too large for an upload', findAll( mount, function( el ) { return el.className === 'nino-admin-hint' } ).map( function( el ) { return el.textContent } ).indexOf( text('/_admin/backups/hint/upload-sftp') ) !== -1 );

// An account with the permission of the panel alone: the list and the restore, no download, no upload, no hint about them
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [ archive('2024-05-03'), archive('2024-05-02-090000') ], enabled : true, uploadLimit : 2097152, full : false } );
check( 'without full access a row has the restore button alone, and the list no download button', findAll( mount, function( el ) { return el.className === 'backups-download' } ).length === 0
	&& rowOf('2024-05-03').children[1].children.length === 1 && rowOf('2024-05-03').children[1].children[0].textContent === text('/_admin/common/label/restore') );
check( '...no upload and no hints about keys and uploads, and Back up now stays', uploadButton() === undefined && fileInput() === undefined && nowButton() !== null
	&& findAll( mount, function( el ) { return el.className === 'nino-admin-hint' } ).length === 0 );

// While backups are off an archive can be put back, which is what a restore is for
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 200, { archives : [], enabled : false } );
check( 'with backups off there is no Back up now and there is the upload, and the empty list says so', nowButton() === null && uploadButton() !== null && findAll( mount, function( el ) { return el.className === 'nino-admin-empty' } ).length === 1 );


done();
