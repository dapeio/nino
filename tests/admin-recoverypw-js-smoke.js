/**
 *	Nino										A compact filesystembased php framework
 *	admin-recoverypw-js-smoke.js	DOM-light checks for the Recovery password tab's script
 *													(_admin/Nino/Modules/Users/assets/recoverypw.js).
 *
 *													The tab is one form: the old recovery password, the new
 *													one twice. What matters is what it sends and when - a
 *													repeat that differs sends nothing - and what it does
 *													with the answer: the three fields are emptied after a
 *													success, and kept after a failure so that a mistyped
 *													old password is typed once.
 *
 *	Usage: node tests/admin-recoverypw-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, documentOf, element, findAll, fire } = require( './harness.js' );

// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Users/text/en_US.php');
const moduleDe 		= fills('_admin/Nino/Modules/Users/text/de_DE.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'recoverypw-form';

const requests = [];

// A stand-in for the shell's registry (Nino.admin.dirty): what the tab registers and when it takes a snapshot
const dirty = {
	forms : {},
	snapshots : [],
	watchForm : function( name, getter, save ) { dirty.forms[name] = { getter : getter, save : save } },
	snapshot : function( name ) { dirty.snapshots.push( name ) },
};

const Nino = {
	admin : { dirty : dirty },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : Object.assign( documentOf( mount ), {
		querySelectorAll : function( selector ) { return mount.querySelectorAll( selector.replace( '#recoverypw-form ', '' ) ) },
	} ),
	Nino : Nino,
};
sandbox.window = { Nino : Nino };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Users/assets/recoverypw.js', context );

const tab = Nino.admin.recoverypw;

/** Answer the last request the tab made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

function field( name ) {
	return findAll( mount, function( el ) { return el.id === 'recoverypw-'+ name } )[0] ?? null;
}

function message() {
	return field('msg') === null ? '' : field('msg').textContent;
}

function type( current, fresh, repeat ) {
	field('current').value = current;
	field('new').value = fresh;
	field('repeat').value = repeat;
}

function submit() {
	fire( findAll( mount, function( el ) { return el.tagName === 'FORM' } )[0], 'submit' );
}

console.log('Admin Recovery password tab');

tab.init();
check( 'opening the tab asks the server for nothing - there is nothing of the server\'s in the form', requests.length === 0 );
check( 'the form has the three password fields, each a password input', [ 'current', 'new', 'repeat' ].every( function( name ) { return field( name ) !== null && field( name ).type === 'password' } ) );
check( '...with the autocomplete hints that keep a password manager from filling the wrong one', field('current').attributes.autocomplete === 'current-password'
	&& field('new').attributes.autocomplete === 'new-password' && field('repeat').attributes.autocomplete === 'new-password' );
check( '...the two new ones with the minimum length the server holds to', field('new').minLength === 8 && field('repeat').minLength === 8 );
check( 'the status line is the action bar\'s own', field('msg') !== null && field('msg').classList.contains('nino-admin-actionbar-status') );
check( 'the form is registered with the shell\'s dirty registry, and drawn means saved', dirty.forms.recoverypw !== undefined && dirty.forms.recoverypw.getter() === mount && dirty.snapshots.join() === 'recoverypw' );
check( 'the inputs carry the field names the server\'s refusals use', field('current').dataset.field === 'current' && field('new').dataset.field === 'pw' );
check( 'the tab shows again without being rebuilt', ( function() { const before = field('current'); tab.showCurrent(); return field('current') === before && requests.length === 0 } )() );

// A repeat that differs: nothing is sent, and the fields stay as they are
type( 'old secret', 'brand new secret', 'brand new secrat' );
submit();
check( 'a repeat that differs sends nothing', requests.length === 0 );
check( '...says why', message() === text('/_admin/recoverypw/error/mismatch') && text('/_admin/recoverypw/error/mismatch') !== '' );
check( '...and keeps what was typed', field('current').value === 'old secret' && field('new').value === 'brand new secret' );
check( '...a save asked for by the registry says so with done( false )', ( function() { let result = null; dirty.forms.recoverypw.save( function( ok ) { result = ok } ); return result === false && requests.length === 0 } )() );

// The request
type( 'old secret', 'brand new secret', 'brand new secret' );
submit();
check( 'a matching repeat sends the old and the new password to recoverypw/save', requests.length === 1 && requests[0].action === 'recoverypw/save'
	&& JSON.stringify( requests[0].payload ) === JSON.stringify( { current : 'old secret', pw : 'brand new secret' } ) );
check( '...with the save button off while it runs', findAll( mount, function( el ) { return el.type === 'submit' } )[0].disabled === true && message() === text('/_admin/common/msg/saving') );

answer( 401, { error : 'wrong current password', code : 'recoverypw_wrong', params : [], field : 'current' } );
check( 'a 401 shows the words of its code, not the server\'s English', message() === text('/_admin/error/recoverypw_wrong') && text('/_admin/error/recoverypw_wrong') !== '' );
check( '...and marks the old password as the field it is about', field('current').attributes['aria-invalid'] === 'true' );
check( '...keeps all three fields, so the old password is the only thing typed again', field('current').value === 'old secret' && field('new').value === 'brand new secret' && field('repeat').value === 'brand new secret' );
check( '...and gives the button back', findAll( mount, function( el ) { return el.type === 'submit' } )[0].disabled === false );

submit();
answer( 429, { error : 'too many attempts', code : 'recoverypw_locked', params : [] } );
check( 'a 429 shows the words of its code too, and keeps the fields', message() === text('/_admin/error/recoverypw_locked') && text('/_admin/error/recoverypw_locked') !== '' && field('new').value === 'brand new secret' );

submit();
answer( 503, null );
check( 'an answer with no reason falls back to the shared sentence', message() === '(503) '+ text('/_admin/common/error/save') && text('/_admin/common/error/save') !== '' );

submit();
check( 'the retry sends the same three again', requests.length === 4 && requests[3].payload.current === 'old secret' );
const snapshots = dirty.snapshots.length;
answer( 200, { ok : true } );
check( 'a success is the new baseline of the registry', dirty.snapshots.length === snapshots + 1 && dirty.snapshots[snapshots] === 'recoverypw' );
check( 'a success empties all three fields', field('current').value === '' && field('new').value === '' && field('repeat').value === '' );
check( '...says it is saved', message() === text('/_admin/recoverypw/msg/saved') && text('/_admin/recoverypw/msg/saved') !== '' );

// A save asked for by the registry (Save in the question before leaving) reports how it ended
type( 'old secret', 'brand new secret', 'brand new secret' );
let ended = [];
dirty.forms.recoverypw.save( function( ok ) { ended.push( ok ) } );
answer( 401, { error : 'wrong current password', code : 'recoverypw_wrong', params : [], field : 'current' } );
dirty.forms.recoverypw.save( function( ok ) { ended.push( ok ) } );
answer( 200, { ok : true } );
check( 'the registry\'s save reports done( false ) on a failed request and done( true ) on success', ended.join() === 'false,true' );

// Every word is a fill, in both languages
const keys = [ '/_admin/nav/recoverypw', '/_admin/recoverypw/intro', '/_admin/recoverypw/label/current', '/_admin/recoverypw/label/new', '/_admin/recoverypw/label/repeat', '/_admin/recoverypw/msg/saved', '/_admin/recoverypw/error/mismatch', '/_admin/error/recoverypw_wrong', '/_admin/error/recoverypw_password_short', '/_admin/error/recoverypw_locked', '/_admin/error/recoverypw_unset' ];
check( 'every word of the tab is worded in English and in German', keys.every( function( key ) { return typeof moduleEn[key] === 'string' && moduleEn[key] !== '' && typeof moduleDe[key] === 'string' && moduleDe[key] !== '' } ) );

done();
