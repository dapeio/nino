/**
 *	Nino							A compact filesystembased php framework
 *	install-session-js-smoke.js	DOM-light checks for what the setup wizard's page does when a request
 *								finds the session gone (_admin/install/assets/session.js and
 *								Nino.install.apiCall()): from the first account on every action asks for
 *								the session of an account, so a page that was reloaded without its cookie,
 *								or outlived its sign-in, asks the server whose session this is, asks for the
 *								account in the dialog of the workbench where nobody is signed in, and sends the
 *								request again - once, however many wait, and not for ever.
 *
 *	Usage: node tests/install-session-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, load, element, documentOf, findAll } = require( './harness.js' );

// --- the page: a token field, and the places a dialog draws into ---------------------------

const root = element('div');
const csrf = element('input');
csrf.attributes.name = '_csrf';
csrf.value = 'page-token';
root.appendChild( csrf );

// The root of the page carries the account it was drawn for
const wrap = element('div');
wrap.id = 'install-page-wrap';
wrap.dataset.account = 'owner@example.com';
root.appendChild( wrap );

const doc = documentOf( root );
doc.documentElement = element('html');
doc.body = element('body');
doc.title = '';
doc.addEventListener = function() {};
doc.removeEventListener = function() {};
doc.querySelectorAll = function( selector ) { return selector === 'input[name="_csrf"]' ? [ csrf ] : [] };

const requests = [];
const reloads = [];
let routes = [];

/**
 *	The server: the next answer for a request, by what it asks. An answer is [ status, body ]; a function is called with the request
 *
 *	@param		{Object}		request
 *
 *	@return		{Array}
 */
function serve( request ) {

	const at = routes.findIndex( function( route ) { return route.match( request ) } );
	if( at === -1 )
		throw new Error( 'a request nobody answers: '+ request.method+ ' '+ request.uri+ ' '+ JSON.stringify( request.data ) );

	const route = routes[at];
	if( route.once === true )
		routes.splice( at, 1 );

	return route.answer( request );
}

const Nino = {
	dir : '',
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data, auth ) {

		const request = { uri : uri, method : method, data : data || {}, auth : auth, token : csrf.value, action : ( data || {} ).action };
		requests.push( request );

		const answered = serve( request );
		callback( { status : answered[0], responseJSON : answered[1], getAllResponseHeaders : function() { return 'content-type: application/json' } } );
	} },
	content : { text : {}, getText : function( key ) { return Nino.content.text[key] || '' } },
};

const sandbox = { console : console, document : doc, Nino : Nino };
sandbox.window = {
	Nino : Nino, matchMedia : function() { return { matches : false } }, setTimeout : function() { return 0 }, clearTimeout : function() {},
	location : { hostname : 'example.test', href : 'http://example.test/_admin/', pathname : '/_admin/', search : '', reload : function() { reloads.push( 'reload' ) } },
	localStorage : { getItem : function() { return null }, setItem : function() {} },
};
sandbox.localStorage = sandbox.window.localStorage;
const context = vm.createContext( sandbox );

[ '_admin/assets/Nino.admin.js', '_admin/assets/pagetext.js', '_admin/install/assets/text.js', '_admin/install/assets/script.js', '_admin/install/assets/session.js' ].forEach( file => load( file, context ) );

/*	The dialog of the workbench, as far as the wizard uses it: the fields are the blocks of the workbench, the dialog is drawn by choiceDialog(), which this
	stands in for - it hands the wizard the handle it would hand it, and a test presses the answers	*/
const dialogs = [];
Nino.adminUi.choiceDialog = function( options ) {

	if( dialogs.some( dialog => dialog.open === true ) === true )
		return false;

	const dialog = { options : options, open : true, closed : [], handle : null };
	dialog.handle = {
		buttons : { login : { disabled : false }, close : { disabled : false } },
		close : function( value ) {
			if( dialog.open === false )
				return;
			dialog.open = false;
			dialog.closed.push( value );
			options.onChoose( value );
		},
	};
	dialogs.push( dialog );
	options.onOpen( dialog.handle );

	return true;
};

/**
 *	Press an answer of the open dialog the way the block does: the guard first, and a guard that says no keeps the dialog open
 *
 *	@param		{string}		value
 *
 *	@return		void
 */
function press( value ) {

	const dialog = dialogs[dialogs.length - 1];
	if( dialog.options.guard( value, dialog.handle ) !== false )
		dialog.handle.close( value );
}

/**
 *	The two fields of the open dialog
 *
 *	@return		{Object}		{ user, pw } the inputs
 */
function fields() {

	const form = dialogs[dialogs.length - 1].options.content;
	const inputs = findAll( form, el => el.tagName === 'INPUT' );

	return { user : inputs[0], pw : inputs[1] };
}

const is = ( request, method, uri, action ) => request.method === method && request.uri === uri && ( action === undefined || request.action === action );
const checks = request => is( request, 'POST', '/_admin/', 'checks/run' );
const session = request => is( request, 'GET', '/_admin/?session=1' );
const login = request => is( request, 'POST', '/.nino/auth/login' );
const answerOnce = ( match, answer ) => ( { match : match, answer : typeof answer === 'function' ? answer : () => answer, once : true } );
const GONE = [ 401, { error : 'not logged in', code : 'session' } ];
const STALE = [ 403, { error : 'the session token is no longer valid - reload the page', code : 'csrf' } ];

console.log('Setup wizard session\n');

/**
 *	One call of the wizard's api: what its callback was given, and what went over the wire in the meantime
 *
 *	@return		{Object}
 */
function call() {

	requests.length = 0;
	let given = null;
	Nino.install.apiCall( 'checks/run', {}, function( status, body ) { given = [ status, body ] } );

	return { get given() { return given }, requests : requests };
}

const label = request => ( request.method === 'GET' ? 'session' : ( request.uri === '/.nino/auth/login' ? 'login' : request.action ) )+ ' '+ request.token;

// --- no session: the page asks for the account, and goes on ----------------------------------

routes = [
	answerOnce( checks, GONE ),
	answerOnce( session, [ 200, { user : '', csrf : 'anonymous-token' } ] ),
	answerOnce( login, [ 200, true ] ),
	answerOnce( session, [ 200, { user : 'owner@example.com', csrf : 'session-token' } ] ),
	answerOnce( checks, [ 200, { php : {} } ] ),
];

const first = call();
check( 'a request the server answers 401 \'session\' is not handed to its caller: the page asks whose session this is, and nobody is signed in, so it asks for the account in the dialog of the workbench',
	first.given === null && first.requests.length === 2 && session( first.requests[1] ) && dialogs.length === 1 && dialogs[0].open === true );
check( '...the token of the anonymous session is in the page for the sign-in that follows', csrf.value === 'anonymous-token' && first.requests[1].token === 'page-token' );

const asked = fields();
check( '...the dialog has the two fields of the sign-in, the mail and the password, and the answers Close and Sign in', asked.user.type === 'email' && asked.pw.type === 'password'
	&& dialogs[0].options.choices.map( choice => choice.value ).join() === 'close,login' );

asked.user.value = 'owner@example.com';
asked.pw.value = 'correct horse battery staple';
press( 'login' );
check( '...the sign-in is answered, the page asks for the token the sign-in rotated, puts it into its field, and sends the request that was refused again with it: its caller gets the answer of that one',
	first.requests.map( label ).join() === 'checks/run page-token,session page-token,login anonymous-token,session anonymous-token,checks/run session-token' && csrf.value === 'session-token'
	&& first.given !== null && first.given[0] === 200 && dialogs[0].open === false && dialogs[0].closed.join() === 'signed-in' );
check( '...with the account\'s own words: the credentials were sent as the user and the password typed', first.requests[2].auth.user === 'owner@example.com' && first.requests[2].auth.pw === 'correct horse battery staple' );

// --- a token that is not the session's: renewed and sent again, no dialog -----------------------

csrf.value = 'stale-token';
routes = [
	answerOnce( checks, STALE ),
	answerOnce( session, [ 200, { user : 'owner@example.com', csrf : 'held-token' } ] ),
	answerOnce( checks, [ 200, { php : {} } ] ),
];
const dialogsBefore = dialogs.length;
const stale = call();
check( 'a request refused 403 \'csrf\' asks for the token the session holds and goes out again with it, without a dialog where an account is signed in', stale.given !== null && stale.given[0] === 200
	&& stale.requests.map( label ).join() === 'checks/run stale-token,session stale-token,checks/run held-token' && dialogs.length === dialogsBefore );

csrf.value = 'stale-token';
routes = [
	answerOnce( checks, STALE ),
	answerOnce( session, [ 200, { user : '', csrf : 'anonymous-2' } ] ),
	answerOnce( checks, GONE ),
	answerOnce( session, [ 200, { user : '', csrf : 'anonymous-2' } ] ),
];
const cookieGone = call();
check( 'where no cookie is left the token is refused first and the session after it: the token is renewed, sent again, and the 401 \'session\' that answers asks for the account',
	cookieGone.given === null && dialogs.length === dialogsBefore + 1 && dialogs[dialogs.length - 1].open === true && csrf.value === 'anonymous-2'
	&& cookieGone.requests.map( label ).join() === 'checks/run stale-token,session stale-token,checks/run anonymous-2,session anonymous-2' );

// --- two requests wait for one sign-in ---------------------------------------------------------

routes = [
	answerOnce( checks, GONE ),
	answerOnce( session, [ 200, { user : '', csrf : 'anonymous-2' } ] ),
];
const second = call();
check( 'a second request that finds the session gone while the dialog is open waits with the first: there is one dialog', second.given === null && dialogs.filter( dialog => dialog.open === true ).length === 1 );

// A wrong password is said at the field, and nothing is sent again
const open = dialogs[dialogs.length - 1];
const typed = fields();
typed.user.value = 'owner@example.com';
typed.pw.value = 'wrong';
routes = [ answerOnce( login, [ 401, false ] ) ];
requests.length = 0;
press( 'login' );
check( 'a sign-in that is refused 401 stays in the dialog and says so at the password field: the request that waits is not sent, and the button is free again', open.open === true && requests.map( label ).join() === 'login anonymous-2'
	&& open.handle.buttons.login.disabled === false && findAll( open.options.content, el => el.attributes !== undefined && el.attributes['aria-invalid'] === 'true' ).length >= 1 );

// A sign-in whose token is refused renews the token for the next try
routes = [ answerOnce( login, [ 403, false ] ), answerOnce( session, [ 200, { user : '', csrf : 'anonymous-3' } ] ) ];
requests.length = 0;
press( 'login' );
check( '...one that is refused 403 asks for the token of the session, so that the next try is sent with it', requests.map( label ).join() === 'login anonymous-2,session anonymous-2' && csrf.value === 'anonymous-3' && open.open === true );

// Left empty, nothing goes out
requests.length = 0;
typed.pw.value = '';
press( 'login' );
check( '...a field left empty is said at the field and nothing is sent', requests.length === 0 && open.open === true );

// Closing it gives the requests the answer they had
requests.length = 0;
press( 'close' );
check( 'closing the dialog gives every request that waited the answer it had - 401 \'session\' - and sends nothing again',
	[ second.given, cookieGone.given ].every( given => given !== null && given[0] === 401 && given[1].code === 'session' ) && open.open === false && requests.length === 0 );

// --- another account than the page is for: nothing is sent as it, the page is loaded anew -----------

csrf.value = 'stale-token';
routes = [
	answerOnce( checks, STALE ),
	answerOnce( session, [ 200, { user : 'other@example.com', csrf : 'theirs' } ] ),
];
reloads.length = 0;
const swapped = call();
check( 'a request refused for the token whose renewal says another account is signed in now - a second tab signed in as that one - is not sent as it: what was typed here was typed for the account of the page, so the page is loaded anew, which draws it for the one that is signed in, and the caller gets the refusal it had',
	swapped.given !== null && swapped.given[0] === 403 && swapped.requests.length === 2 && reloads.length === 1 );

Nino.install.session.remember( 'a@example.com' );
csrf.value = 'stale-token';
routes = [ answerOnce( checks, STALE ), answerOnce( session, [ 200, { user : 'a@example.com', csrf : 'mine' } ] ), answerOnce( checks, [ 200, {} ] ) ];
reloads.length = 0;
const same = call();
check( '...the account the step made or changed is the account of the page from then on: its own session is sent again, without a reload', same.given !== null && same.given[0] === 200 && reloads.length === 0 );
Nino.install.session.remember( 'owner@example.com' );

// Signing in as another account in the dialog is the same: the page is loaded anew, the request that waited is not sent
routes = [ answerOnce( checks, GONE ), answerOnce( session, [ 200, { user : '', csrf : 'anonymous-4' } ] ) ];
const strange = call();
const strangeFields = fields();
strangeFields.user.value = 'other@example.com';
strangeFields.pw.value = 'a password';
routes = [ answerOnce( login, [ 200, true ] ), answerOnce( session, [ 200, { user : 'other@example.com', csrf : 'theirs-2' } ] ) ];
reloads.length = 0;
requests.length = 0;
press( 'login' );
check( 'the sign-in of another account than the page is for loads the page anew and does not send the request that waited as it: the caller gets the answer it had', strange.given !== null && strange.given[0] === 401 && reloads.length === 1 && requests.filter( checks ).length === 0 );

// A page that was drawn for nobody is for whoever signs in
Nino.install.session.remember( '' );
wrap.dataset.account = '';
Nino.install.session._known = null;
routes = [ answerOnce( checks, GONE ), answerOnce( session, [ 200, { user : '', csrf : 'anonymous-5' } ] ) ];
const fresh = call();
const freshFields = fields();
freshFields.user.value = 'first@example.com';
freshFields.pw.value = 'a password';
routes = [ answerOnce( login, [ 200, true ] ), answerOnce( session, [ 200, { user : 'first@example.com', csrf : 'first-token' } ] ), answerOnce( checks, [ 200, {} ] ) ];
reloads.length = 0;
press( 'login' );
check( 'a page that was drawn for nobody is for whoever signs in from it: the request is sent again as that account, and no reload', fresh.given !== null && fresh.given[0] === 200 && reloads.length === 0 && Nino.install.session.account() === 'first@example.com' );
Nino.install.session.remember( 'owner@example.com' );

// --- what is not the end of a session is not mended --------------------------------------------

routes = [ answerOnce( checks, [ 401, { error : 'wrong current password', code : 'recoverypw_wrong' } ] ) ];
const otherRefusal = call();
check( 'a 401 with another code, or a 403 with another reason, is the step\'s own refusal: it goes to its caller as it is, with nothing asked', otherRefusal.given !== null && otherRefusal.given[0] === 401 && otherRefusal.requests.length === 1 );

routes = [ answerOnce( checks, [ 403, { error : 'not allowed', code : 'install_not_allowed' } ] ) ];
const notAllowed = call();
check( '...an account that is signed in and may not do it is told so, and not asked to sign in again: 403 \'install_not_allowed\' goes to the step', notAllowed.given !== null && notAllowed.given[1].code === 'install_not_allowed' && notAllowed.requests.length === 1 );

// --- no end to asking --------------------------------------------------------------------------

routes = [ { match : checks, answer : () => STALE, once : false }, { match : session, answer : () => [ 200, { user : 'owner@example.com', csrf : 'same' } ], once : false } ];
const endless = call();
check( 'a session the server refuses however often it is asked for is not asked for for ever: the request goes out four times at most, and its caller gets the refusal', endless.given !== null && endless.given[0] === 403
	&& endless.requests.filter( request => checks( request ) ).length === 4 );

// --- the question of the session that cannot be answered ----------------------------------------

routes = [ answerOnce( checks, GONE ), answerOnce( session, [ 500, null ] ) ];
const unanswered = call();
check( 'a page that cannot ask whose session this is - the server does not answer - gives its caller the refusal it had, with no dialog', unanswered.given !== null && unanswered.given[0] === 401 && unanswered.requests.length === 2 );

// --- the words ------------------------------------------------------------------------------------

const tables = Nino.pageText._tables;
const sessionKeys = key => key.startsWith( '/_admin/install/session/' ) || [ '/_admin/page/error/session', '/_admin/page/error/csrf', '/_admin/page/error/install-not-allowed', '/_admin/page/error/install-mail-in-use', '/_admin/page/error/install-wrong-password' ].includes( key );
check( 'every sentence of the sign-in, and of the refusals the page can be told of the session and of the account it changes, is there in both languages, none empty', [ 'de', 'en' ].every( lang => Object.keys( tables[lang] ).filter( sessionKeys ).length === 13 && Object.keys( tables[lang] ).filter( sessionKeys ).every( key => tables[lang][key] !== '' ) )
	&& Object.keys( tables.de ).filter( sessionKeys ).sort().join() === Object.keys( tables.en ).filter( sessionKeys ).sort().join() );

done();
