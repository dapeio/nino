/**
 *	Nino										A compact filesystembased php framework
 *	admin-login-js-smoke.js	What the workbench login says when it fails. Only a 401
 *													means the credential pair was read and refused; every
 *													other answer never reached that point, and telling the
 *													operator to check their input then sends the one
 *													person who can fix it looking in the wrong place (see
 *													docs/deployment.md, "The login form is refused and
 *													the password is right").
 *
 *	Usage: node tests/admin-login-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

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

// The two messages, read out of the shipped text file rather than written
// here - a key login.js asks for and no locale carries renders as an empty
// string, which is the failure this test exists to notice
const textFile	= fs.readFileSync( path.join( __dirname, '../_admin/text/en_US.php' ), 'utf8' );
const textKey		= function( key ) {
	const match = textFile.match( new RegExp( "'\\[\\[" + key.replace( /\//g, '\\/' ) + "\\]\\]'\\s*=>\\s*'([^']*)'" ) );
	return match === null ? null : match[1];
};

const WRONG			= textKey('/_admin/login/error/wrong');
const ENDPOINT	= textKey('/_admin/login/error/endpoint');

check( 'en_US carries the wrong-credentials message', typeof WRONG === 'string' && WRONG !== '' );
check( 'en_US carries the endpoint message', typeof ENDPOINT === 'string' && ENDPOINT !== '' );
check( '...and the endpoint message has a place for the status code', ENDPOINT !== null && ENDPOINT.indexOf('%s') !== -1 );

const de = fs.readFileSync( path.join( __dirname, '../_admin/text/de_DE.php' ), 'utf8' );
check( 'de_DE carries it too', de.indexOf('[[/_admin/login/error/endpoint]]') !== -1 && de.indexOf('%s') !== -1 );

// The form's dom, reduced to the five ids login.js reaches for
function field() {
	return {
		value : '', innerHTML : '', className : '',
		classList : { add : function() {}, remove : function() {} },
		focus : function() {},
		addEventListener : function( type, fn ) { this.handler = fn },
	};
}

const el = {
	'form-message'	: field(),
	'input-user'		: field(),
	'input-pw'			: field(),
	'submit'				: field(),
	'form-login'		: field(),
};

el['input-user'].value	= 'editor@example.com';
el['input-pw'].value		= 'correct horse battery staple';

const documentElement = { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {}, classList : { add : function() {}, remove : function() {} } };
const body = { classList : { add : function() {}, remove : function() {} }, scrollLeft : 0, scrollTop : 0 };
const document = {
	body : body,
	documentElement : documentElement,
	cookie : '',
	readyState : 'complete',
	getElementById : function( id ) { return el[id] || null },
	querySelector : function() { return null },
	querySelectorAll : function() { return [] },
	createElement : function() { return { classList : { add : function() {} }, style : {}, setAttribute : function() {}, appendChild : function() {} } },
	addEventListener : function() {},
};

const replaced = [];

const sandbox = {
	console : console,
	document : document,
	navigator : { userAgent : 'node', language : 'en-US' },
	location : { pathname : '/_admin', origin : 'https://example.com', search : '', href : 'https://example.com/_admin', replace : function( uri ) { replaced.push( uri ) } },
	XMLHttpRequest : function() {},
	FormData : function() { this.append = function() {} },
	TextEncoder : TextEncoder,
	btoa : function( binary ) { return Buffer.from( binary, 'latin1' ).toString('base64') },
	setTimeout : setTimeout,
	clearTimeout : clearTimeout,
	addEventListener : function() {},
	matchMedia : function() { return { matches : false, addEventListener : function() {} } },
	// What the [jstext] shortcode puts on the page, keyed the way it keys it
	NinoJstext : {
		'/_admin/login/error/user'		: 'Email is required.',
		'/_admin/login/error/pw'			: 'Password is required.',
		'/_admin/login/error/wrong'		: WRONG,
		'/_admin/login/error/endpoint': ENDPOINT,
		'/_admin/login/msg/pending'		: 'Checking.',
	},
};
sandbox.window = sandbox;
sandbox.self = sandbox;

const context = vm.createContext( sandbox );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_nino/Nino.js' ), 'utf8' ), context, { filename : 'Nino.js' } );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/login.js' ), 'utf8' ), context, { filename : 'login.js' } );

check( 'login.js boots and takes the Nino.admin slot', typeof sandbox.Nino.admin.onReady === 'function' );

sandbox.Nino.admin.onReady();
check( 'it wired the form up', typeof el['form-login'].handler === 'function' );

// Drive one login attempt and hand back the message the form ended up with.
// The request itself is stubbed at Nino.http.sendRequest, so what is under
// test is the branch login.js takes on the answer - not the xhr
let sentTo = '';
function attempt( status ) {
	sandbox.Nino.http.sendRequest = function( uri, method, callback ) {
		sentTo = uri;
		callback( { status : status } );
	};
	el['form-message'].innerHTML = '';
	el['form-login'].handler( { preventDefault : function() {} } );
	return el['form-message'].innerHTML;
}

check( 'a 401 is the one answer that means the password was wrong', attempt( 401 ) === WRONG );
check( '...and it is sent to the login endpoint', sentTo === '/.nino/auth/login' );

/*	Everything below is a server that answered before Nino could read the pair.
	403: the csrf guard, or a host that refuses a uri with a dot segment -
	/.nino/auth/login is one. 404: nothing forwards an unmatched address to
	index.php. 500: php died. Each has to say so rather than blame the typing */
[ 403, 404, 500, 502 ].forEach( function( status ) {
	const message = attempt( status );
	check( 'a '+ status +' says the endpoint answered, not the operator', message !== WRONG && message.indexOf( String( status ) ) !== -1 );
} );

check( 'the status is put into the sentence rather than appended to it', attempt( 404 ) === ENDPOINT.replace( '%s', '404' ) );


// The two field guards in front of all of that are unchanged
el['input-user'].value = '';
check( 'an empty email is still caught before any request', attempt( 401 ) === sandbox.NinoJstext['/_admin/login/error/user'] );
el['input-user'].value = 'editor@example.com';
el['input-pw'].value = '';
check( 'an empty password too', attempt( 401 ) === sandbox.NinoJstext['/_admin/login/error/pw'] );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
