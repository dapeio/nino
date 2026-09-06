/**
 *	Nino									A compact filesystembased php framework
 *	nino-auth-js-smoke.js	What Nino.http.sendRequest() actually puts on the wire as
 *												HTTP Basic authorization. The pair is base64 of the bytes
 *												the server decodes and compares against the account's
 *												hash (see \Nino\Http::_getBasicAuthCredentials() and
 *												kernel-smoke.php), so the encoding is the contract
 *												between the two halves - and a password is exactly where
 *												a non-ascii character shows up.
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
}
XMLHttpRequestStub.prototype.open						= function() {};
XMLHttpRequestStub.prototype.setRequestHeader	= function( name, value ) { this.header[name] = value };
XMLHttpRequestStub.prototype.send						= function() { sent.push( this ) };

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

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
