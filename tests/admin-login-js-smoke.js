/**
 *	Nino										A compact filesystembased php framework
 *	admin-login-js-smoke.js	What the workbench login says when it fails, and where. Only a
 *													401 means the credential pair was read and refused; every
 *													other answer never reached that point, and telling the
 *													operator to check their input then sends the one
 *													person who can fix it looking in the wrong place (see
 *													docs/deployment.md, "The login form is refused and
 *													the password is right"). What is wrong stands at the field:
 *													a sentence under it, the field marked aria-invalid and
 *													pointed at the sentence, the focus on it - the fields are the
 *													blocks of the workbench and the form checks itself, not the
 *													browser.
 *
 *	Usage: node tests/admin-login-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { markupWrites, check, done, load, source, fills, element, documentOf, findAll, fire } = require( './harness.js' );

// The sentences, read out of the shipped text files rather than written
// here - a key login.js asks for and no locale carries renders as an empty
// string, which is the failure this test exists to notice
const en = fills('_admin/text/en_US.php');
const de = fills('_admin/text/de_DE.php');

const KEYS = [ '/_admin/login/label/user', '/_admin/login/label/pw', '/_admin/login/label/submit', '/_admin/login/error/wrong', '/_admin/login/error/endpoint', '/_admin/login/error/csrf', '/_admin/login/msg/pending', '/_admin/common/error/missing' ];
const WRONG = en['/_admin/login/error/wrong'];
const ENDPOINT = en['/_admin/login/error/endpoint'];
const CSRF = en['/_admin/login/error/csrf'];

check( 'both languages carry every sentence the form says, and the endpoint one has a place for the status code and the stale-token one asks for a reload and has none',
	KEYS.every( key => en[key] !== undefined && en[key] !== '' && de[key] !== undefined && de[key] !== '' ) && ENDPOINT.indexOf('%s') !== -1 && de['/_admin/login/error/endpoint'].indexOf('%s') !== -1 && CSRF.indexOf('%s') === -1 );
check( '...and the sentences that were said above the form of the old build are gone: the form says what is missing with the one wording of the workbench', [ '/_admin/login/error/user', '/_admin/login/error/pw', '/_admin/login/msg/welcome' ].every( key => en[key] === undefined && de[key] === undefined ) );

// --- the page, as the template has it ---------------------------------------------------

const page = element('div');
const form = element('form');
form.id = 'form-login';
const holder = element('fieldset');
holder.id = 'login-fields';
const picker = element('select');
picker.id = 'admin-localepicker';
const pickerOption = element('option');
pickerOption.value = '?locale=de_DE';
picker.appendChild( pickerOption );
form.appendChild( holder );
form.appendChild( picker );
page.appendChild( form );

const doc = Object.assign( documentOf( page ), {
	documentElement : { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {}, classList : { add : function() {}, remove : function() {} } },
	body : element('body'),
	cookie : '',
	readyState : 'complete',
	addEventListener : function() {},
} );

const replaced = [];
let reloads = 0;

const sandbox = {
	console : console,
	document : doc,
	navigator : { userAgent : 'node', language : 'en-US' },
	location : { pathname : '/_admin', origin : 'https://example.com', search : '', hash : '#elements/team', href : 'https://example.com/_admin', replace : function( uri ) { replaced.push( uri ) }, reload : function() { reloads++ } },
	XMLHttpRequest : function() {},
	FormData : function() { this.append = function() {} },
	TextEncoder : TextEncoder,
	btoa : function( binary ) { return Buffer.from( binary, 'latin1' ).toString('base64') },
	setTimeout : setTimeout,
	clearTimeout : clearTimeout,
	addEventListener : function() {},
	matchMedia : function() { return { matches : false, addEventListener : function() {} } },
	// What the [jstext] shortcode puts on the page, keyed the way it keys it
	NinoJstext : en,
};
sandbox.window = sandbox;
sandbox.self = sandbox;

const context = vm.createContext( sandbox );
load( '_nino/Nino.js', context );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/assets/login.js', context );

check( 'login.js boots and takes the Nino.admin slot', typeof sandbox.Nino.admin.onReady === 'function' );

sandbox.Nino.admin.onReady();

const input = id => findAll( form, el => el.id === id )[0];
const label = id => input( id ).parentNode;
const errors = () => findAll( form, el => el.className === 'nino-admin-field-error' );
const submit = input('submit');
const submitForm = () => fire( form, 'submit' );

check( 'it drew the two fields and the one main button with the blocks of the workbench: a label with its star, an input of the kind of its value, a submit button of the kind of the main one', input('input-user') !== undefined && input('input-pw') !== undefined
	&& input('input-user').type === 'email' && input('input-pw').type === 'password' && input('input-user').autocomplete === 'username' && input('input-pw').autocomplete === 'current-password'
	&& label('input-user').tagName === 'LABEL' && label('input-user').className === 'nino-admin-field' && findAll( label('input-pw'), el => el.className === 'nino-admin-required' ).length === 1
	&& submit.type === 'submit' && submit.className.includes('nino-admin-btn-primary') && submit.textContent === en['/_admin/login/label/submit'] );
check( '...the fields are the grid of a form, so that the label stands at the left of its field where there is room', findAll( holder, el => el.className === 'nino-admin-fieldgrid' ).length === 1 && findAll( holder, el => el.className === 'nino-admin-fieldgrid' )[0].children.length === 2 );
check( '...the labels are the words of the text file, and the focus is in the first field', findAll( label('input-user'), el => el.tagName === '#TEXT' )[0].textContent === en['/_admin/login/label/user'] && input('input-user').focused === true );
check( '...the form is checked by the page and never by the browser: the template carries novalidate, and the fields have no pattern of their own', source('_admin/templates/page-login.tpl').includes('novalidate') === true );
check( '...no message of the form is written as markup', findAll( form, el => el.tagName === 'P' ).length === 1 );

const line = findAll( form, el => el.id === 'form-message' )[0];
check( '...and the line that says a request is on its way is a status the screen reader hears, empty until then', line !== undefined && line.getAttribute('role') === 'status' && line.getAttribute('aria-live') === 'polite' && line.textContent === '' );

// Drive one login attempt and hand back the sentence the form ended up with at its fields.
// The request itself is stubbed at Nino.http.sendRequest, so what is under
// test is the branch login.js takes on the answer - not the xhr
let sentTo = '';
function attempt( status, policy ) {
	sandbox.Nino.http.sendRequest = function( uri, method, callback ) {
		sentTo = uri;
		callback( {
			status : status,
			// Same-origin, so the form may read the response headers - which is
			// how it tells the kernel's own answer from the server's
			getResponseHeader : function( name ) {
				return name === 'Content-Security-Policy' && policy ? policy : null;
			},
		} );
	};
	submitForm();
	return errors().map( el => el.textContent ).join('|');
}

input('input-user').value = 'editor@example.com';
input('input-pw').value = 'correct horse battery staple';

check( 'a 401 is the one answer that means the password was wrong', attempt( 401 ) === WRONG );
check( '...it is said at the password field, which is marked in words as well as with a colour and which the focus is on', input('input-pw').getAttribute('aria-invalid') === 'true' && input('input-user').getAttribute('aria-invalid') === null
	&& input('input-pw').getAttribute('aria-describedby') === errors()[0].id && input('input-pw').focused === true );
check( '...and it is sent to the login endpoint', sentTo === '/.nino/auth/login' );

/*	A deep link survives the login: the page is loaded again where it stands -
	the same address, now with a session, so the #hash and the ?locale= on it
	are what comes back. Replacing the location with its own path and a
	fragment would be a navigation inside the document that requests nothing
	(the form would stay on screen), and a redirect parameter would be an open
	redirect to guard	*/
sandbox.Nino.http.sendRequest = function( uri, method, callback ) { callback( { status : 200 } ) };
submitForm();
check( 'a login the server accepts loads the page again, wherever it was', reloads === 1 && replaced.length === 0 );
check( '...and the hash is still the one it came with', sandbox.location.hash === '#elements/team' );
check( '...while a refused one reloads nothing', ( attempt( 401 ), reloads === 1 ) );

// The language picker: another language is another load of the same page
picker.value = '?locale=de_DE';
fire( picker, 'change' );
check( 'the language picker keeps the hash, so the person comes back to the page they were on', sandbox.location.href === '?locale=de_DE#elements/team' );

/*	Everything below is a server that answered before Nino could read the pair.
	403: the csrf guard, or a host that refuses a uri with a dot segment -
	/.nino/auth/login is one. 404: nothing forwards an unmatched address to
	index.php. 500: php died. Each has to say so rather than blame the typing */
[ 403, 404, 500, 502 ].forEach( function( status ) {
	const message = attempt( status );
	check( 'a '+ status +' says the endpoint answered, not the operator', message !== WRONG && message.indexOf( String( status ) ) !== -1 );
} );

check( 'the status is put into the sentence rather than appended to it', attempt( 404 ) === ENDPOINT.replace( '%s', '404' ) );

/*	The two 403s are not the same morning. Every answer the kernel composes
	carries a Content-Security-Policy and a server's own error page carries
	none, so the header says whether the request reached php at all: with one,
	the csrf guard refused a stale token and a reload is the whole fix; without
	one, the address never got there and no reload will help	*/
check( 'a 403 the kernel composed is the csrf guard, and asks for a reload', attempt( 403, "default-src 'self'" ) === CSRF );
check( '...while a 403 without the kernel\'s own header is the server refusing the address', attempt( 403 ) === ENDPOINT.replace( '%s', '403' ) );
check( '...and the header alone never excuses another status', attempt( 404, "default-src 'self'" ) === ENDPOINT.replace( '%s', '404' )
	&& attempt( 500, "default-src 'self'" ) === ENDPOINT.replace( '%s', '500' ) );
check( '...nor turns a 401 into anything but a wrong password', attempt( 401, "default-src 'self'" ) === WRONG );

// What is wrong with a field is said at that field, before any request, in the words of the workbench
const sentBefore = [];
sandbox.Nino.http.sendRequest = function() { sentBefore.push( 1 ) };

input('input-user').value = '';
input('input-pw').value = 'x';
submitForm();
check( 'an empty email is caught before any request, at its own field, in the one wording of the workbench for what is missing', sentBefore.length === 0
	&& errors().map( el => el.textContent ).join('|') === en['/_admin/common/error/missing'].replace( '%s', en['/_admin/login/label/user'] )
	&& input('input-user').getAttribute('aria-invalid') === 'true' && input('input-pw').getAttribute('aria-invalid') === null && input('input-user').focused === true );

input('input-user').value = 'editor@example.com';
input('input-pw').value = '';
submitForm();
check( '...and an empty password at its', sentBefore.length === 0 && errors().map( el => el.textContent ).join('|') === en['/_admin/common/error/missing'].replace( '%s', en['/_admin/login/label/pw'] ) && input('input-pw').focused === true );

/*	The mark belongs to the attempt, not to the form: the email that was empty
	a moment ago has been filled in, so nothing may still point at it, and the
	sentence of the attempt before is gone - one sentence at a time	*/
check( 'the field that was filled in carries no mark and no sentence of an attempt before, and only the one that is wrong now is marked', errors().length === 1 && input('input-user').getAttribute('aria-invalid') === null
	&& input('input-user').getAttribute('aria-describedby') === null && input('input-pw').getAttribute('aria-invalid') === 'true' );

// Both filled in again: a submit that reaches the server leaves nothing marked, and says that the request is pending
input('input-pw').value = 'correct horse battery staple';
submitForm();
check( 'a submit that gets past the checks clears every sentence and mark', errors().length === 0 && input('input-pw').getAttribute('aria-invalid') === null && input('input-pw').getAttribute('aria-describedby') === null );
check( '...and the line says the request is pending, in the words of the text file, and nothing else', line.textContent === en['/_admin/login/msg/pending'] );

// ...and once the answer is in, the request is over with it
attempt( 401 );
check( 'the answer takes the pending sentence away rather than leaving it beside the refusal', line.textContent === '' && errors().length === 1 );

// The words are written as text, never as markup
check( '...and nothing a person typed or the server said is written as markup: no element of the page was given anything but nothing as its innerHTML, in the whole run', markupWrites.length === 0 );

// Nino.js leaves its ready callback behind, one millisecond out, and that one
// reaches for an Event this context has not got: the suite ends here
done();
process.exit();
