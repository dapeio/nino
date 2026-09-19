/**
 *	Nino									A compact filesystembased php framework
 *	nino-auth-js-smoke.js	What Nino.http.sendRequest() actually puts on the wire, and
 *												what it hands its caller back.
 *
 *												On the wire: HTTP Basic authorization. The pair is base64
 *												of the bytes the server decodes and compares against the
 *												account's hash (see \Nino\Http::_getBasicAuthCredentials()
 *												and kernel-smoke.php), so the encoding is the contract
 *												between the two halves - and a password is exactly where
 *												a non-ascii character shows up.
 *
 *												Back to the caller: one status and one body, whatever
 *												happened. Every _admin panel and the public form alike
 *												branch on that status and print it at the person, so the
 *												normalization is a contract too.
 *
 *	Usage: node tests/nino-auth-js-smoke.js
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

// Every header sendRequest() set on its xhr, and the body it sent
const sent = [];

function XMLHttpRequestStub() {
	this.header = {};
	this.served = 0;
	this.responseText = '';
}
XMLHttpRequestStub.prototype.open						= function() {};
XMLHttpRequestStub.prototype.setRequestHeader	= function( name, value ) { this.header[name] = value };
XMLHttpRequestStub.prototype.send						= function() { sent.push( this ) };

// The browser keeps .status behind a getter on XMLHttpRequest.prototype with
// no setter, which is the whole reason assigning to it does nothing. A stub
// holding it as a plain writable property would accept the assignment and
// report a pass for code that fails in every browser there is, so it is a
// getter here too - sendRequest() has to reach past it the way the real one
// must
Object.defineProperty( XMLHttpRequestStub.prototype, 'status', {
	get : function() { return this.served },
	configurable : true,
} );

function FormDataStub() { this.fields = {} }
FormDataStub.prototype.append = function( key, value ) { this.fields[key] = value };

const documentElement = { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {}, classList : { add : function() {}, remove : function() {} } };
const body = { classList : { add : function() {}, remove : function() {} }, scrollLeft : 0, scrollTop : 0 };
const document = {
	body : body,
	documentElement : documentElement,
	cookie : '',
	readyState : 'complete',
	getElementById : function() { return null },
	// No [csrf] input on this page - sendRequest() must not need one
	querySelector : function() { return null },
	querySelectorAll : function() { return [] },
	createElement : function() { return { classList : { add : function() {} }, style : {}, setAttribute : function() {}, appendChild : function() {} } },
	addEventListener : function() {},
};

const sandbox = {
	console : console,
	document : document,
	navigator : { userAgent : 'node', language : 'en-US' },
	location : { pathname : '/', origin : 'https://example.com', search : '', href : 'https://example.com/' },
	XMLHttpRequest : XMLHttpRequestStub,
	FormData : FormDataStub,
	TextEncoder : TextEncoder,
	btoa : function( binary ) { return Buffer.from( binary, 'latin1' ).toString('base64') },
	atob : function( b64 ) { return Buffer.from( b64, 'base64' ).toString('latin1') },
	setTimeout : setTimeout,
	clearTimeout : clearTimeout,
	addEventListener : function() {},
	matchMedia : function() { return { matches : false, addEventListener : function() {} } },
};
sandbox.window = sandbox;
sandbox.self = sandbox;

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_nino/Nino.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'Nino.js' }
);

check( 'Nino.js boots and exposes sendRequest', typeof sandbox.Nino.http.sendRequest === 'function' );

/**
 *	Send one request with a credential pair and hand back the Authorization
 *	header it produced
 */
function authHeader( user, pw ) {
	sent.length = 0;
	sandbox.Nino.http.sendRequest( '/.nino/auth/login', 'POST', function() {}, {}, { user : user, pw : pw } );
	return sent[0].header['Authorization'];
}

// The property the server relies on: base64 of the utf-8 bytes, for every
// credential pair - not one byte per code unit, which is what btoa() alone does
const cases = [
	[ 'plain ascii is unchanged',														'editor@example.com',	'correct horse battery staple' ],
	[ 'a password with an umlaut travels as utf-8',					'editor@example.com',	'Paßwort-über' ],
	[ 'a character above U+00FF neither throws nor is lost',	'editor@example.com',	'pay-€-now' ],
	[ 'a non-ascii account name too',												'björn@example.com',	'secret' ],
	[ 'a colon in the password survives',										'editor@example.com',	'secret:with-colons' ],
];

cases.forEach( function( entry ) {
	const label = entry[0], user = entry[1], pw = entry[2];
	const expected = 'Basic '+ Buffer.from( user+ ':'+ pw, 'utf8' ).toString('base64');
	check( label, authHeader( user, pw ) === expected );
} );

// ...and the other half of the contract: what php reads back out of it
const roundTrip = authHeader( 'björn@example.com', 'Paßwort-über' );
const decoded = Buffer.from( roundTrip.slice(6), 'base64' ).toString('utf8');
check( 'the header decodes back to the exact pair that was typed', decoded === 'björn@example.com:Paßwort-über' );

// A latin-1 encoding would have produced these bytes instead - the check above
// only means something if the two actually differ
check( 'and that is not what btoa() alone would have sent',
	roundTrip !== 'Basic '+ Buffer.from( 'björn@example.com:Paßwort-über', 'latin1' ).toString('base64') );

// No credentials, no header - the same call shape every other request uses
sent.length = 0;
sandbox.Nino.http.sendRequest( '/.nino/some/endpoint', 'POST', function() {}, {} );
check( 'a request without a credential pair sets no Authorization header', sent[0].header['Authorization'] === undefined );


// --- what comes back ------------------------------------------------------

console.log( '\nThe result sendRequest() hands its caller\n' );

/**
 *	Send one request, answer it the way the browser would - with a load, error,
 *	timeout or abort event - and hand back the xhr the callback was given
 *
 *	@param		{string}	event			Event type, 'load' / 'error' / 'timeout' / 'abort'
 *	@param		{number}	served			The status the server sent (0 for a failure)
 *	@param		{string}	body				The response text
 *
 *	@return		{Object}
 */
function answer( event, served, body ) {
	sent.length = 0;
	let given = null;
	sandbox.Nino.http.sendRequest( '/.nino/some/endpoint', 'POST', function( xhr ) { given = xhr }, {} );
	const xhr = sent[0];
	xhr.served = served;
	xhr.responseText = body;
	xhr[ 'on'+ event ]( { type : event } );
	return given;
}

// A request that never reached a server has no status - the browser reports 0
// for a dropped connection, a timeout and an abort alike. Every caller reads
// xhr.status and several print it: _admin's login panel says "the login
// endpoint answered %s - the credentials were never checked", so a person on a
// flaky connection was told their login endpoint had answered 0
check( 'a network error comes back as 500',	typeof answer( 'error', 0, '' ).status === 'number' && answer( 'error', 0, '' ).status === 500 );
check( 'a timeout comes back as 408',					answer( 'timeout', 0, '' ).status === 408 );
check( 'an abort comes back as 499',					answer( 'abort', 0, '' ).status === 499 );

// ...and the three are told apart, which is the point of mapping them at all
const failed = [ answer( 'error', 0, '' ).status, answer( 'timeout', 0, '' ).status, answer( 'abort', 0, '' ).status ];
check( 'the three failures are three different codes', new Set( failed ).size === 3 );

// A served answer keeps the status php sent it - the mapping must not touch
// one. 403 is the csrf refusal _admin/assets/login.js tells apart from a wrong
// password, and 400 is the field validation the public form repeats
check( 'a 200 stays 200',											answer( 'load', 200, '{"ok":true}' ).status === 200 );
check( 'a 403 stays 403',											answer( 'load', 403, '{"error":"csrf"}' ).status === 403 );
check( 'a 400 stays 400',											answer( 'load', 400, '{"error":"field"}' ).status === 400 );

// The body half of the same contract: json parsed, anything else verbatim -
// a server's own 502 page is html, and JSON.parse() throws on it
const parsed = answer( 'load', 200, '{"ok":true,"count":2}' ).responseJSON;
check( 'a json body comes back parsed', parsed !== null && typeof parsed === 'object' && parsed.count === 2 );
check( 'a body that is not json comes back as its text', answer( 'load', 502, '<html>Bad Gateway</html>' ).responseJSON === '<html>Bad Gateway</html>' );
check( 'an empty body comes back empty, not as a thrown parse error', answer( 'error', 0, '' ).responseJSON === '' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
