/**
 *	Nino									A compact filesystembased php framework
 *	admin-api-js-smoke.js	DOM-light checks for the one request helper every
 *										panel talks to the server through (Nino.adminUi.api),
 *										the messages that say what failed (errorText(),
 *										format(), showError()), the status line and the
 *										upload limits.
 *
 *	Usage: node tests/admin-api-js-smoke.js
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

// --- the words: the real fills, as the workbench's own text files carry them

function fills( file ) {
	const out = {};
	for( const m of fs.readFileSync( path.join( __dirname, '..', file ), 'utf8' ).matchAll( /'\[\[(\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g ) )
		out[m[1]] = m[2].replace( /\\(.)/g, '$1' );
	return out;
}

const TEXT = { en_US : fills('_admin/text/en_US.php'), de_DE : fills('_admin/text/de_DE.php') };

// --- a stand-in element: enough of a DOM for the status line and the dialog's inputs

function element( tag ) {
	const classes = new Set();
	const el = {
		tagName : String( tag ).toUpperCase(),
		textContent : '',
		value : '',
		dataset : {},
		attributes : {},
		children : [],
		listeners : {},
		parent : null,
		focused : false,
		classList : {
			add : function( name ) { classes.add( name ) },
			remove : function( name ) { classes.delete( name ) },
			toggle : function( name, force ) { if( force === true ) classes.add( name ); else classes.delete( name ) },
			contains : function( name ) { return classes.has( name ) },
		},
		appendChild : function( child ) { el.children.push( child ); child.parent = el; return child },
		setAttribute : function( name, value ) {
			el.attributes[name] = String( value );
			if( name.indexOf( 'data-' ) === 0 )
				el.dataset[ name.slice( 5 ) ] = String( value );
		},
		getAttribute : function( name ) { return name in el.attributes ? el.attributes[name] : null },
		removeAttribute : function( name ) { delete el.attributes[name] },
		addEventListener : function( name, fn, options ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( { fn : fn, once : !!( options && options.once ) } );
		},
		focus : function() { el.focused = true },
		// The two selectors the status line asks for, and nothing else
		querySelector : function( selector ) {
			const wanted = Array.from( selector.matchAll( /\[(data-field|name)="([^"]+)"\]/g ) ).map( m => [ m[1], m[2] ] );
			let found = null;
			( function walk( node ) {
				node.children.forEach( function( child ) {
					if( found === null && wanted.some( w => ( w[0] === 'name' ? child.attributes.name : child.dataset.field ) === w[1] ) )
						found = child;
					walk( child );
				} );
			} )( el );
			return found;
		},
		closest : function() { return null },
		// The picker empties its lists by assigning innerHTML
		set innerHTML( value ) { el.children.length = 0 },
		// An event goes from its target up through the parents, as in a page
		dispatchEvent : function( event ) {
			for( let node = el; node !== null; node = node.parent )
				fire( node, event.type, el );
			return true;
		},
	};
	return el;
}

function fire( el, name, target ) {
	( el.listeners[name] || [] ).slice().forEach( function( entry ) {
		if( entry.once )
			el.listeners[name].splice( el.listeners[name].indexOf( entry ), 1 );
		entry.fn( { preventDefault : function() {}, target : target || el } );
	} );
}

// --- the sandbox

function sandboxFor( locale, options ) {

	options = options || {};

	const sent = [];
	const csrfInputs = [ element('input'), element('input') ];
	csrfInputs.forEach( function( input ) { input.value = 'stale'; input.attributes.name = '_csrf' } );
	const account = element('span');
	account.textContent = '  me@example.com ';
	const wrap = element('div');
	Object.assign( wrap.dataset, options.limits || { uploadBytes : '2097152', uploadPixels : '20000000' } );

	const Nino = {
		dir : '/sub',
		http : { sendRequest : function( uri, method, callback, data ) { sent.push( { uri : uri, method : method, data : data, callback : callback } ) } },
		content : { getText : function( key ) { return TEXT[locale][key] || '' } },
	};

	const sandbox = {
		console : console,
		document : {
			documentElement : null,
			body : null,
			createElement : element,
			getElementById : function( id ) { return id === 'admin-user-email' ? account : ( id === 'admin-page-wrap' ? wrap : null ) },
			querySelectorAll : function( selector ) { return selector === 'input[name="_csrf"]' ? csrfInputs : [] },
		},
		Nino : Nino,
		Event : function( type, init ) { this.type = type; this.bubbles = !!( init && init.bubbles ) },
	};
	sandbox.window = sandbox;

	vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ), vm.createContext( sandbox ), { filename : 'Nino.admin.js' } );

	return { sent : sent, Nino : Nino, sandbox : sandbox, csrfInputs : csrfInputs, account : account };
}

/** An xhr as Nino.http.sendRequest() hands it back: a server's answer has headers */
function answer( status, body ) {
	return { status : status, responseJSON : body, getAllResponseHeaders : function() { return 'content-type: application/json\r\n' } };
}

const T = sandboxFor( 'en_US' );
const api = T.Nino.adminUi.api;

// --- the request

let got = null;
api.call( 'users/save', { mail : 'a@b.c' }, function( status, body ) { got = [ status, body ] }, { file : 'FILE' } );
check( 'a request posts to the project directory + /_admin/ with its trailing slash', T.sent.length === 1 && T.sent[0].uri === '/sub/_admin/' && T.sent[0].method === 'POST' );
check( '...with the action, the payload as json in "data" and the extra multipart fields merged', T.sent[0].data.action === 'users/save' && T.sent[0].data.data === '{"mail":"a@b.c"}' && T.sent[0].data.file === 'FILE' );
T.sent[0].callback( answer( 200, { ok : true } ) );
check( 'the callback is called synchronously from the transport callback with ( status, body )', got !== null && got[0] === 200 && got[1].ok === true );

got = null;
api.call( 'x/y', undefined, function( status, body ) { got = [ status, body ] } );
check( 'a missing payload is an empty object', T.sent[1].data.data === '{}' );
T.sent[1].callback( answer( 400, { error : 'no' } ) );
check( 'a plain refusal is handed back as it is', got[0] === 400 && got[1].error === 'no' );

// --- no handler: the raw status, as in the wizard and in tests

got = null;
api.call( 'x/y', {}, function( status, body ) { got = [ status, body ] } );
T.sent[2].callback( answer( 401, { error : 'not logged in', code : 'session' } ) );
check( 'without a registered handler a session failure is handed back raw, and nothing is asked', got[0] === 401 && got[1].code === 'session' && T.sent.length === 3 );

// --- with a handler: only a session failure is a session failure

const handled = [];
api.onSessionLost( function( check, reason ) { handled.push( { check : check, reason : reason } ) } );

got = null;
api.call( 'x/y', {}, function( status, body ) { got = [ status, body ] } );
T.sent[3].callback( answer( 403, { error : 'not allowed' } ) );
check( 'a 403 "not allowed" goes straight to the callback, with no check', got[0] === 403 && T.sent.length === 4 && handled.length === 0 );

got = null;
api.call( 'users/save', {}, function( status, body ) { got = [ status, body ] } );
T.sent[4].callback( answer( 401, { error : 'wrong current password' } ) );
check( 'a 401 without the code is a wrong password, not a lost session - no check', got[0] === 401 && T.sent.length === 5 && handled.length === 0 );

got = null;
api.call( 'users/save', {}, function( status, body ) { got = [ status, body ] } );
T.sent[5].callback( answer( 401, { error : 'wrong current password', code : 'wrong_password', field : 'currentPassword' } ) );
check( '...and neither is a 401 with another code', got[0] === 401 && got[1].field === 'currentPassword' && T.sent.length === 6 && handled.length === 0 );

// --- the second-tab case: the token is not this session's, the account is

got = null;
api.call( 'elements/save', { a : 1 }, function( status, body ) { got = [ status, body ] } );
T.sent[6].callback( answer( 403, { error : 'x', code : 'csrf' } ) );
check( 'a csrf failure asks once whose session this is, with GET ?session=1 on the same endpoint', T.sent.length === 8 && T.sent[7].method === 'GET' && T.sent[7].uri === '/sub/_admin/?session=1' && got === null );
T.sent[7].callback( answer( 200, { user : 'me@example.com', csrf : 'fresh-token' } ) );
check( 'the fresh token is written into every csrf field of the page', T.csrfInputs.every( input => input.value === 'fresh-token' ) );
check( 'the same account: the request is sent again, once, as it was', T.sent.length === 9 && T.sent[8].data.action === 'elements/save' && T.sent[8].data.data === '{"a":1}' && handled.length === 0 );
T.sent[8].callback( answer( 200, { ok : 1 } ) );
check( '...and the replay answers the original caller', got !== null && got[0] === 200 && got[1].ok === 1 );

got = null;
api.call( 'elements/save', {}, function( status, body ) { got = [ status, body ] } );
T.sent[9].callback( answer( 403, { error : 'x', code : 'csrf' } ) );
T.sent[10].callback( answer( 200, { user : 'me@example.com', csrf : 'again' } ) );
T.sent[11].callback( answer( 403, { error : 'x', code : 'csrf' } ) );
check( 'a second session failure is not sent a third time - it goes to the callback', got !== null && got[0] === 403 && got[1].code === 'csrf' && T.sent.length === 12 );

// --- three requests at once share one check

const results = [];
[ 'a', 'b', 'c' ].forEach( function( name ) { api.call( 'x/'+ name, {}, function( status ) { results.push( name+ status ) } ) } );
const base = T.sent.length - 3;
T.sent[base].callback( answer( 403, { code : 'csrf', error : 'x' } ) );
T.sent[base + 1].callback( answer( 403, { code : 'csrf', error : 'x' } ) );
T.sent[base + 2].callback( answer( 401, { code : 'session', error : 'x' } ) );
const checkRequests = T.sent.slice( base + 3 ).filter( r => r.method === 'GET' );
check( 'three parallel failures share one GET', checkRequests.length === 1 && T.sent.length === base + 4 );
T.sent[base + 3].callback( answer( 200, { user : 'me@example.com', csrf : 't3' } ) );
check( 'and all three are sent again', T.sent.length === base + 7 && T.sent.slice( base + 4 ).map( r => r.data.action ).join() === 'x/a,x/b,x/c' );
T.sent.slice( base + 4 ).forEach( r => r.callback( answer( 200, {} ) ) );
check( '...each answering its own caller', results.join() === 'a200,b200,c200' );

// a request sent while the check is open waits with the others
const late = [];
api.call( 'x/first', {}, function( status ) { late.push( 'first'+ status ) } );
T.sent[T.sent.length - 1].callback( answer( 403, { code : 'csrf', error : 'x' } ) );
const before = T.sent.length;
api.call( 'x/second', {}, function( status ) { late.push( 'second'+ status ) } );
check( 'a request made while the check is open is not sent yet', T.sent.length === before );
T.sent[before - 1].callback( answer( 200, { user : 'me@example.com', csrf : 't4' } ) );
check( '...it is sent with the replays', T.sent.slice( before ).map( r => r.data.action ).join() === 'x/first,x/second' );
T.sent.slice( before ).forEach( r => r.callback( answer( 200, {} ) ) );

// --- logged out: the handler decides, a login resumes

const loggedOut = [];
const handledOut = [];
api.onSessionLost( function( check, reason ) { handledOut.push( { check : check, reason : reason } ) } );
api.call( 'text/save', { k : 1 }, function( status, body ) { loggedOut.push( [ status, body ] ) } );
let n = T.sent.length;
T.sent[n - 1].callback( answer( 401, { error : 'not logged in', code : 'session' } ) );
T.sent[n].callback( answer( 200, { user : '', csrf : 'anonymous-token' } ) );
check( 'a logged-out session calls the handler once, with the reason and the check to call after a login', handledOut.length === 1 && handledOut[0].reason === 'expired' && typeof handledOut[0].check === 'function' );
check( '...the anonymous session\'s token is in the page for the login that follows, and nothing is sent again', T.csrfInputs.every( input => input.value === 'anonymous-token' ) && T.sent.length === n + 1 && loggedOut.length === 0 );
let outcome = null;
handledOut[0].check( function( what ) { outcome = what } );
T.sent[n + 1].callback( answer( 200, { user : 'me@example.com', csrf : 'after-login' } ) );
check( 'after a login of the same account the waiting request is sent again and the check says it resumed', outcome === 'resumed' && T.sent.length === n + 3 && T.sent[n + 2].data.action === 'text/save' && T.csrfInputs.every( input => input.value === 'after-login' ) );
T.sent[n + 2].callback( answer( 200, { ok : 1 } ) );
check( '...which answers the original caller', loggedOut.length === 1 && loggedOut[0][0] === 200 );

// --- another account: never replayed

const otherResults = [];
const otherHandled = [];
api.onSessionLost( function( check, reason ) { otherHandled.push( reason ) } );
api.call( 'text/save', {}, function( status ) { otherResults.push( status ) } );
n = T.sent.length;
T.sent[n - 1].callback( answer( 403, { error : 'x', code : 'csrf' } ) );
T.sent[n].callback( answer( 200, { user : 'someone@else.example', csrf : 'theirs' } ) );
check( 'another account is signed in: the request is not sent again, the handler is told "other"', T.sent.length === n + 1 && otherHandled.join() === 'other' && otherResults.length === 0 );
check( '...and the page keeps its own token: a request outside the api must not be saved by the other account', T.csrfInputs.every( input => input.value === 'after-login' ) );
check( '...and the request waits for the dialog: api.waiting() says so', api.waiting() === true );

// A request made while the dialog is open waits with the first one
const waited = [];
api.call( 'x/waits', {}, function( status, body ) { waited.push( [ status, body ] ) } );
check( 'a request made while the dialog is open is held back', T.sent.length === n + 1 && waited.length === 0 );

// The person closes the dialog: nobody can log in as the account the form was filled in for
api.dismiss();
check( 'closing the dialog releases the request that was answered: it gets the answer it already had, and is not sent again', otherResults.length === 1 && otherResults[0] === 403 && api.waiting() === false );
check( '...the one that only waited is sent once as it is, and no dialog opens when it too finds the session gone', T.sent.length === n + 2 && T.sent[n + 1].data.action === 'x/waits' );
T.sent[n + 1].callback( answer( 403, { error : 'x', code : 'csrf' } ) );
check( '...its answer goes to its caller, not into another check', waited.length === 1 && waited[0][0] === 403 && T.sent.length === n + 2 && otherHandled.join() === 'other' && api.waiting() === false );
api.dismiss();
check( 'dismissing when nothing waits does nothing', T.sent.length === n + 2 );

// --- a login that cannot happen: the person gives up on an expired session

const gaveUp = [];
const expiredHandled = [];
api.onSessionLost( function( check, reason ) { expiredHandled.push( { check : check, reason : reason } ) } );
api.call( 'users/save', { a : 1 }, function( status, body ) { gaveUp.push( [ status, body ] ) } );
n = T.sent.length;
T.sent[n - 1].callback( answer( 401, { error : 'not logged in', code : 'session' } ) );
T.sent[n].callback( answer( 200, { user : '', csrf : 'anonymous-2' } ) );
check( 'an expired session waits for the login', expiredHandled.length === 1 && expiredHandled[0].reason === 'expired' && api.waiting() === true && gaveUp.length === 0 );
api.dismiss();
check( '...and when the person closes the dialog instead (the account is gone, the password changed) the request gets its 401 "session" and the panel can show it', gaveUp.length === 1 && gaveUp[0][0] === 401 && gaveUp[0][1].code === 'session' && api.waiting() === false && T.sent.length === n + 1 );

// --- a login answered after the requests were given up still teaches the page its new token

const afterGiveUp = [];
expiredHandled[0].check( function( what, status ) { afterGiveUp.push( [ what, status ] ) } );
check( 'the check asks even when nothing waits any more', T.sent.length === n + 2 && T.sent[n + 1].method === 'GET' && T.sent[n + 1].uri === '/sub/_admin/?session=1' );
T.sent[n + 1].callback( answer( 200, { user : 'me@example.com', csrf : 'rotated-by-login' } ) );
check( '...the page takes the token the login rotated to, and the check says it resumed', afterGiveUp.length === 1 && afterGiveUp[0][0] === 'resumed' && T.csrfInputs.every( input => input.value === 'rotated-by-login' ) && T.sent.length === n + 2 );

// --- a check that fails: the requests get the answers they had

const failed = [];
api.onSessionLost( function() {} );
api.call( 'text/save', {}, function( status, body ) { failed.push( [ status, body ] ) } );
n = T.sent.length;
T.sent[n - 1].callback( answer( 401, { error : 'not logged in', code : 'session' } ) );
T.sent[n].callback( answer( 500, null ) );
check( 'when the check itself fails the request gets the answer it already had', failed.length === 1 && failed[0][0] === 401 && failed[0][1].code === 'session' && api.waiting() === false );

// --- offline is not a server error

const offline = [];
api.call( 'x/y', {}, function( status, body ) { offline.push( [ status, body ] ) } );
T.sent[T.sent.length - 1].callback( { status : 500, responseJSON : '', getAllResponseHeaders : function() { return '' } } );
check( 'a request that never got an answer (no response headers) is told as offline', offline[0][0] === 500 && offline[0][1].code === 'offline' );
api.call( 'x/y', {}, function( status, body ) { offline.push( [ status, body ] ) } );
T.sent[T.sent.length - 1].callback( answer( 500, { error : 'boom' } ) );
check( 'a server\'s own 500 keeps its body', offline[1][0] === 500 && offline[1][1].error === 'boom' );
check( 'an xhr stand-in without the method is an answer', ( function() { let r = null; api.call( 'x/y', {}, function( s, b ) { r = b } ); T.sent[T.sent.length - 1].callback( { status : 500, responseJSON : { error : 'x' } } ); return r.error === 'x' } )() );

api.onSessionLost( null );

// --- words

const ui = T.Nino.adminUi;

check( 'format() fills the placeholders in order', ui.format( '%s of %d (%n)', 'a', 2, 3 ) === 'a of 2 (3)' );
check( 'format() reads "$&" and "$$" in a param as typed - they are what somebody typed', ui.format( 'Key %s', 'a$&b$$c' ) === 'Key a$&b$$c' );
check( 'format() leaves a placeholder without a param as it is', ui.format( '%s and %s', 'one' ) === 'one and %s' );
check( 'format() takes a missing text for an empty one', ui.format( undefined ) === '' );
check( 'a number with a fraction is written the way the language writes it', ui.format( '%s MB', 0.5 ) === '0.5 MB' && sandboxFor('de_DE').Nino.adminUi.format( '%s MB', 0.5 ) === '0,5 MB' && sandboxFor('de_DE').Nino.adminUi.format( '%s MB', 8 ) === '8 MB' && sandboxFor('de_DE').Nino.adminUi.format( '%s', '0.5' ) === '0.5' );

function errorText( locale, status, response, key ) {
	const s = sandboxFor( locale );
	return s.Nino.adminUi.api.errorText( status, response, key );
}

check( 'a code the interface has words for is said in them, with its params', errorText( 'en_US', 400, { error : 'image too large', code : 'image_too_large', params : [ 8 ] } ) === 'The image is larger than 8 MB.' );
check( '...in German too', errorText( 'de_DE', 400, { error : 'x', code : 'image_too_many_pixels', params : [ 20 ] } ) === 'Das Bild hat mehr als 20 Megapixel.' );
check( '...without the status number: the sentence says what happened', errorText( 'en_US', 413, { error : 'x', code : 'upload_too_large', params : [ 2 ] } ).includes( '(413)' ) === false );
check( 'params are never read as replacement patterns', errorText( 'en_US', 400, { error : 'x', code : 'image_too_large', params : [ '$&' ] } ) === 'The image is larger than $& MB.' );
check( 'a code without words falls to the server\'s own message, with the status', errorText( 'en_US', 400, { error : 'a veto the project wrote', code : 'no_such_code_yet' }, '/_admin/common/error/save' ) === '(400) a veto the project wrote' );
check( 'a body without a message falls to the panel\'s own sentence, with the status', errorText( 'en_US', 500, null, '/_admin/common/error/save' ) === '(500) Failed to save.' );
check( '...a key or literal text alike', errorText( 'en_US', 500, {}, 'Could not do that.' ) === '(500) Could not do that.' );
check( '...and the generic request failure when it has neither', errorText( 'en_US', 502, undefined ) === '(502) Request failed.' );
check( 'a request that never arrived is told as that, without a status', errorText( 'en_US', 500, { code : 'offline' }, '/_admin/common/error/save' ) === TEXT.en_US['/_admin/common/error/offline'] );
check( 'a code that is not a slug is never looked up', errorText( 'en_US', 400, { error : 'x', code : '../common/error/save' }, '' ) === '(400) x' );

// showError() writes the error text into a container, replacing what stood there
const container = element('div');
container.appendChild( element('span') );
Object.defineProperty( container, 'innerHTML', { set : function() { container.children.length = 0 }, get : function() { return '' } } );
const shown = T.Nino.adminUi.showError( container, 500, { error : 'boom' }, '/_admin/common/error/load' );
check( 'showError() replaces the container\'s content with one error paragraph', container.children.length === 1 && container.children[0] === shown && shown.className === 'nino-admin-error' && shown.textContent === '(500) boom' );

// --- the status line

const model = ui.statusModel;
check( 'the model knows idle, dirty, saving, saved and error', [ 'idle', 'dirty', 'saving', 'saved', 'error' ].every( s => model( s ).state === s ) && model( 'whatever' ).state === 'idle' );
check( 'saved carries the zero-padded 24-hour time of the date it is given', model( 'saved', new Date( 2026, 0, 5, 9, 4 ) ).params[0] === '09:04' && model( 'saved', new Date( 2026, 0, 5, 17, 30 ) ).params[0] === '17:30' );
check( '...and the keys are fills of the shared text, in both languages', [ 'saving', 'dirty', 'saved' ].every( s => [ 'en_US', 'de_DE' ].every( l => TEXT[l][ model( s, new Date() ).key ] !== undefined ) ) );
check( 'saved says "Saved at %s." / "Gespeichert um %s."', TEXT.en_US['/_admin/common/msg/savedat'] === 'Saved at %s.' && TEXT.de_DE['/_admin/common/msg/savedat'] === 'Gespeichert um %s.' );

const line = element('p');
const form = element('form');
const mail = element('input');
mail.dataset.field = 'mail';
const pw = element('input');
pw.attributes.name = 'currentPassword';
form.appendChild( mail );
form.appendChild( pw );
const status = ui.status( line );
status.bind( form );
status.saved( new Date( 2026, 0, 5, 9, 4 ) );
check( 'saved: the text, data-state and a polite status role', line.textContent === 'Saved at 09:04.' && line.dataset.state === 'saved' && line.attributes.role === 'status' && line.attributes['aria-live'] === 'polite' && line.classList.contains('nino-admin-status') );
fire( form, 'input' );
check( 'typing into the form turns saved into unsaved changes', line.dataset.state === 'dirty' && line.textContent === 'Unsaved changes' );
const upload = element('input');
upload.type = 'file';
status.saved( new Date( 2026, 0, 5, 9, 4 ) );
fire( form, 'change', upload );
check( 'a picked file does not turn saved into unsaved - the upload saves it', line.dataset.state === 'saved' );
const searchBox = element('input');
searchBox.type = 'search';
fire( form, 'input', searchBox );
check( 'a search box (the element picker) is not an edit', line.dataset.state === 'saved' );

// ...but what the picker chooses is: add, move and remove each write the hidden value and say so
function findIn( node, test ) {
	let found = null;
	( function walk( n ) { n.children.forEach( function( c ) { if( found === null && test( c ) ) found = c; walk( c ) } ) } )( node );
	return found;
}
function buttons( node, glyph ) {
	const hits = [];
	( function walk( n ) { n.children.forEach( function( c ) { if( c.tagName === 'BUTTON' && c.textContent === glyph ) hits.push( c ); walk( c ) } ) } )( node );
	return hits;
}
function click( el ) { ( el.listeners.click || [] ).forEach( function( entry ) { entry.fn( { preventDefault : function() {}, target : el } ) } ) }
const picker = ui.elementList( { key : 'related', label : 'Related', value : [], limit : 0, text : {},
	options : [ { value : '/posts/a', label : 'Alpha' }, { value : '/posts/b', label : 'Beta' } ] } );
form.appendChild( picker );
const pickerSearch = findIn( picker, c => c.type === 'search' );
status.saved( new Date( 2026, 0, 5, 9, 41 ) );
pickerSearch.value = 'alp';
fire( pickerSearch, 'input' );
check( 'typing into the picker\'s search box alone leaves saved as it is', line.dataset.state === 'saved' );
click( buttons( picker, '+' )[0] );
check( 'adding a reference turns saved into unsaved changes', line.dataset.state === 'dirty' && findIn( picker, c => c.dataset.field === 'related' ).value === '["/posts/a"]' );
pickerSearch.value = '';
fire( pickerSearch, 'input' );
click( buttons( picker, '+' )[0] );
status.saved( new Date( 2026, 0, 5, 9, 42 ) );
click( buttons( picker, '↓' )[0] );
check( 'moving a reference turns saved into unsaved changes', line.dataset.state === 'dirty' && findIn( picker, c => c.dataset.field === 'related' ).value === '["/posts/b","/posts/a"]' );
status.saved( new Date( 2026, 0, 5, 9, 43 ) );
click( buttons( picker, '✕' )[0] );
check( 'removing a reference turns saved into unsaved changes', line.dataset.state === 'dirty' && findIn( picker, c => c.dataset.field === 'related' ).value === '["/posts/a"]' );
status.saved( new Date( 2026, 0, 5, 9, 4 ) );
fire( form, 'input', pw );
status.saving();
fire( form, 'change' );
check( 'a save in flight stays "saving" whatever is typed', line.dataset.state === 'saving' && line.textContent === 'Saving …' );
status.saved( new Date( 2026, 0, 5, 9, 5 ) );
check( '...but what was typed meanwhile is not in what was saved: the line ends in unsaved changes', line.dataset.state === 'dirty' && line.textContent === 'Unsaved changes' );
status.saving();
status.saved( new Date( 2026, 0, 5, 9, 6 ) );
check( 'a save nothing was typed during ends in saved, as before', line.dataset.state === 'saved' && line.textContent === 'Saved at 09:06.' );
status.saving();
fire( form, 'input' );
status.saving();
status.saved( new Date( 2026, 0, 5, 9, 7 ) );
check( '...and what was typed before a later save is forgotten with it', line.dataset.state === 'saved' );
status.saving();
fire( form, 'input' );
status.error( 500, { error : 'boom' } );
status.saving();
status.saved( new Date( 2026, 0, 5, 9, 8 ) );
check( '...as is what was typed during a save that failed, once the next save starts', line.dataset.state === 'saved' );

status.error( 401, { error : 'wrong current password', code : 'wrong_password', field : 'currentPassword' }, '/_admin/users/error/save' );
check( 'an error says what failed, as an alert', line.dataset.state === 'error' && line.textContent === 'The current password is wrong.' && line.attributes.role === 'alert' && line.attributes['aria-live'] === 'assertive' );
check( '...marks the field the server named (by name or data-field) invalid and focuses it', pw.attributes['aria-invalid'] === 'true' && pw.focused === true && mail.attributes['aria-invalid'] === undefined );
fire( pw, 'input' );
check( '...and lets it go at the next thing typed', pw.attributes['aria-invalid'] === undefined );
check( '...while the refusal itself stays on screen until the next save', line.dataset.state === 'error' && line.textContent === 'The current password is wrong.' );

status.error( 400, { error : 'x', code : 'users_invalid_mail', field : 'mail' } );
check( 'a field is found by data-field too', mail.attributes['aria-invalid'] === 'true' );
status.saved();
check( 'a later state lets go of the mark', mail.attributes['aria-invalid'] === undefined );

status.error( 400, { error : 'x', field : 'x"] , body [data-field="' } );
check( 'a field name that is not a plain key is never put into a selector', line.dataset.state === 'error' );
status.fail( 'Fill in the title.' );
check( 'a refusal the panel made itself shows its own words as an error', line.textContent === 'Fill in the title.' && line.dataset.state === 'error' );
status.dirty( 'A copy - name it and save.' );
check( 'unsaved can carry the panel\'s own words', line.textContent === 'A copy - name it and save.' && line.dataset.state === 'dirty' );
status.idle();
check( 'idle is empty', line.textContent === '' && line.dataset.state === 'idle' );

const labelled = ui.status( element('p'), { saved : 'Stored at %s', dirty : 'Changed' } );
labelled.saved( new Date( 2026, 0, 5, 1, 2 ) );
check( 'a caller\'s labels replace the shared words', labelled.element.textContent === 'Stored at 01:02' );
labelled.dirty();
check( '...for every state they name', labelled.element.textContent === 'Changed' );

const narrowed = ui.status( element('p') );
const narrowedForm = element('form');
let dirtyAnswer = false;
narrowed.bind( narrowedForm, function() { return dirtyAnswer } );
narrowed.saved();
fire( narrowedForm, 'input' );
check( 'bind() asks the panel whether it is dirty - nothing is held twice', narrowed.state === 'saved' );
dirtyAnswer = true;
fire( narrowedForm, 'input' );
check( '...and follows the answer', narrowed.state === 'dirty' );
dirtyAnswer = false;
fire( narrowedForm, 'input' );
check( '...back as well, when what was typed is undone', narrowed.state === 'idle' );

// --- the limits

check( 'limits() reads what the shell wrote onto its wrapper', JSON.stringify( ui.limits() ) === '{"bytes":2097152,"pixels":20000000}' );
check( 'limits() of a page that carries none are 0 - no check', JSON.stringify( sandboxFor( 'en_US', { limits : { uploadBytes : '', uploadPixels : 'x' } } ).Nino.adminUi.limits() ) === '{"bytes":0,"pixels":0}' );
check( 'megabytes() rounds to one decimal', ui.megabytes( 8388608 ) === 8 && ui.megabytes( 2097152 ) === 2 && ui.megabytes( 524288 ) === 0.5 );

const hint = ui.uploadHint();
check( 'the upload hint names the size and the megapixels, permanently', hint.textContent === 'Up to 2 MB and 20 megapixels.' && hint.className === 'nino-admin-hint' );
check( '...and is not drawn where the limits are unknown', sandboxFor( 'en_US', { limits : { uploadBytes : '0', uploadPixels : '0' } } ).Nino.adminUi.uploadHint() === null );
check( '...in German too', sandboxFor( 'de_DE' ).Nino.adminUi.uploadHint().textContent === 'Bis zu 2 MB und 20 Megapixel.' );

let rejected = 'unset';
ui.checkImage( { size : 3 * 1048576 }, function( r ) { rejected = r } );
check( 'a file above the byte limit is refused before it is sent, with the server\'s own code', rejected !== null && rejected.code === 'image_too_large' && rejected.params[0] === 2 );
ui.checkImage( { size : 1000 }, function( r ) { rejected = r } );
check( 'a file within it passes where the browser cannot count pixels', rejected === null );

const pixels = sandboxFor( 'en_US' );
const big = { size : 1000, w : 6000, h : 4000 };
const small = { size : 1000, w : 800, h : 600 };
const unreadable = { size : 1000 };
pixels.sandbox.createImageBitmap = function( file ) { return file.w === undefined ? Promise.reject( new Error('no') ) : Promise.resolve( { width : file.w, height : file.h, close : function() { file.closed = true } } ) };
Promise.all( [ big, small, unreadable ].map( file => new Promise( function( resolve ) { pixels.Nino.adminUi.checkImage( file, function( r ) { resolve( r ) } ) } ) ) ).then( function( answers ) {
	check( 'above the pixel limit it is refused with the megapixels', answers[0] !== null && answers[0].code === 'image_too_many_pixels' && answers[0].params[0] === 20 && big.closed === true );
	check( '...below it passes', answers[1] === null && small.closed === true );
	check( '...and a file the browser cannot decode is left to the server', answers[2] === null );

	// --- the words the server sends exist in the interface languages
	const codes = [];
	function walk( dir ) {
		fs.readdirSync( dir, { withFileTypes : true } ).forEach( function( entry ) {
			const full = path.join( dir, entry.name );
			if( entry.isDirectory() )
				return walk( full );
			if( entry.name.endsWith('.php') === false || full.includes( path.sep+ 'install'+ path.sep ) )
				return;
			const source = fs.readFileSync( full, 'utf8' );
			for( const m of source.matchAll( /Http::fail\(\s*\$request,\s*[^,]+,\s*(?:'(?:[^'\\]|\\.)*'|[^,()]+(?:\([^()]*\))?[^,()]*),\s*'([a-z][a-z0-9_]*)'/g ) )
				codes.push( m[1] );
		} );
	}
	[ '../_admin', '../_nino' ].forEach( dir => walk( path.join( __dirname, dir ) ) );
	const unique = Array.from( new Set( codes ) );
	const all = { en_US : Object.assign( {}, TEXT.en_US ), de_DE : Object.assign( {}, TEXT.de_DE ) };
	const textFiles = [];
	( function find( dir ) {
		fs.readdirSync( dir, { withFileTypes : true } ).forEach( function( entry ) {
			const full = path.join( dir, entry.name );
			if( entry.isDirectory() )
				return find( full );
			if( /[\\/]text[\\/](en_US|de_DE)\.php$/.test( full ) )
				textFiles.push( full );
		} );
	} )( path.join( __dirname, '../_admin' ) );
	textFiles.concat( fs.readdirSync( path.join( __dirname, '../_nino/Nino/Modules' ) ).map( m => path.join( __dirname, '../_nino/Nino/Modules', m, 'text' ) ).filter( d => fs.existsSync( d ) ).flatMap( d => fs.readdirSync( d ).map( f => path.join( d, f ) ) ) ).forEach( function( file ) {
		const locale = path.basename( file, '.php' );
		if( all[locale] === undefined )
			return;
		Object.assign( all[locale], fills( path.relative( path.join( __dirname, '..' ), file ) ) );
	} );
	const unworded = unique.filter( code => [ 'en_US', 'de_DE' ].some( l => all[l]['/_admin/error/'+ code] === undefined ) );
	check( 'every code the kernel sends has words in English and in German'+ ( unworded.length ? ' - missing: '+ unworded.join(', ') : '' ), unique.length > 30 && unworded.length === 0 );
	// Codes named by variable ('$direction === 'up' ? 'already_top' : ...') or built in a helper
	const helperCodes = [ 'session', 'csrf', 'post_too_large', 'upload_too_large', 'upload_partial', 'upload_missing', 'upload_server', 'image_too_large', 'image_type', 'image_too_many_pixels', 'image_unreadable', 'int_range', 'bool', 'lines', 'lines_ip', 'invalid_value', 'already_top', 'already_bottom', 'wrong_password' ];
	check( 'the shared codes have words too, with the placeholders their params fill', helperCodes.every( code => [ 'en_US', 'de_DE' ].every( l => all[l]['/_admin/error/'+ code] !== undefined ) )
		&& all.en_US['/_admin/error/int_range'].split( '%s' ).length === 3 && all.de_DE['/_admin/error/int_range'].split( '%s' ).length === 3
		&& all.en_US['/_admin/error/upload_too_large'].includes( '%s' ) && all.de_DE['/_admin/error/image_too_large'].includes( '%s' ) );

	console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
	process.exitCode = failures === 0 ? 0 : 1;
} );
