/**
 *	Nino									A compact filesystembased php framework
 *	nino-core-js-smoke.js	The two halves of Nino.js every page uses and no test
 *												covered: readQueryVars(), which reads the address a
 *												visitor arrived with, and the resize/scroll throttle
 *												every registered callback hangs off - one of them
 *												reading something a stranger wrote, the other running
 *												on every frame of a scroll.
 *
 *	Usage: node tests/nino-core-js-smoke.js
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

/**
 *	Nino.js in a page of its own: the listeners it registers are kept, and so
 *	are the animation frames it asks for, so a test can run them by hand
 *
 *	@param		{string}	search			The query string, '?a=b' style
 *
 *	@return		{Object}
 */
function page( search ) {

	const listeners = {};
	const frames = [];

	const documentElement = { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {}, classList : { add : function() {}, remove : function() {} } };
	const body = { classList : { add : function() {}, remove : function() {} }, scrollLeft : 0, scrollTop : 0 };

	const document = {
		body : body,
		documentElement : documentElement,
		cookie : '',
		readyState : 'complete',
		getElementById : function() { return null },
		querySelector : function() { return null },
		querySelectorAll : function() { return [] },
		createElement : function() { return { classList : { add : function() {} }, style : {}, setAttribute : function() {}, appendChild : function() {} } },
		addEventListener : function() {},
	};

	const sandbox = {
		console : console,
		document : document,
		navigator : { userAgent : 'node', language : 'en-US' },
		location : { pathname : '/', origin : 'https://example.com', search : search || '', href : 'https://example.com/' },
		XMLHttpRequest : function() {},
		FormData : function() {},
		TextEncoder : TextEncoder,
		btoa : function( binary ) { return Buffer.from( binary, 'latin1' ).toString('base64') },
		atob : function( b64 ) { return Buffer.from( b64, 'base64' ).toString('latin1') },
		setTimeout : setTimeout,
		clearTimeout : clearTimeout,
		addEventListener : function( type, fn ) { ( listeners[type] = listeners[type] || [] ).push( fn ) },
		requestAnimationFrame : function( fn ) { frames.push( fn ); return frames.length },
		matchMedia : function() { return { matches : false, addEventListener : function() {} } },
	};

	sandbox.window = sandbox;
	sandbox.self = sandbox;

	vm.runInContext(
		fs.readFileSync( path.join( __dirname, '../_nino/Nino.js' ), 'utf8' ),
		vm.createContext( sandbox ),
		{ filename : 'Nino.js' }
	);

	return {
		Nino : sandbox.Nino,
		fire : function( type, event ) { ( listeners[type] || [] ).forEach( function( fn ) { fn( event ) } ) },
		runFrames : function() { const queued = frames.splice( 0, frames.length ); queued.forEach( function( fn ) { fn() } ); return queued.length },
		frames : function() { return frames.length },
	};
}


// --- readQueryVars ---------------------------------------------------------

console.log( 'Nino.http.readQueryVars - the address a visitor arrived with\n' );

const plain = page( '?page=2&sort=name' ).Nino.http.readQueryVars();
check( 'reads the pairs of the query string', plain['page'] === '2' && plain['sort'] === 'name' );

const encoded = page( '?q=caf%C3%A9%20au%20lait' ).Nino.http.readQueryVars();
check( 'decodes what the browser encoded', encoded['q'] === 'café au lait' );

// A query variable is whatever somebody put in the address. Both of these
// used to reach decodeURIComponent() unguarded: a stray '%' is a URIError,
// which took out whatever called this, and a key without a value read the
// string 'undefined' - the one value a page would then act on
let broken = null;
try { broken = page( '?q=100%' ).Nino.http.readQueryVars(); }
catch( error ) { broken = 'threw '+ error.name; }
check( 'a value that is not valid percent-encoding is the raw text, not a thrown error', broken !== null && broken['q'] === '100%' );

const flag = page( '?flag&page=2' ).Nino.http.readQueryVars();
check( 'a key with no value is an empty string, not the word "undefined"', flag['flag'] === '' && flag['page'] === '2' );

const empty = page( '' ).Nino.http.readQueryVars();
check( 'no query at all is no variables', Object.keys( empty ).length === 0 );

const equals = page( '?filter=a=b' ).Nino.http.readQueryVars();
check( 'a value carrying its own "=" survives whole', equals['filter'] === 'a=b' );

console.log( '' );


// --- The resize and scroll throttle ----------------------------------------

console.log( 'Nino.events - one callback round per frame, not one per event\n' );

const scrolling = page( '' );
let scrollRuns = 0;
scrolling.Nino.events.bindCallback( 'scroll', function() { scrollRuns++ } );

scrolling.fire( 'scroll', { type : 'scroll', which : 1 } );
scrolling.fire( 'scroll', { type : 'scroll', which : 2 } );
scrolling.fire( 'scroll', { type : 'scroll', which : 3 } );
check( 'a burst of scroll events asks for one animation frame, not one each', scrolling.frames() === 1 );

scrolling.runFrames();
check( '...and the callbacks run once for the burst', scrollRuns === 1 );

scrolling.fire( 'scroll', { type : 'scroll', which : 4 } );
check( 'the next event after that frame is taken again', scrolling.frames() === 1 );
scrolling.runFrames();
check( '...and runs the callbacks again', scrollRuns === 2 );

const resizing = page( '' );
let resizeEvent = null;
resizing.Nino.events.bindCallback( 'resize', function( event ) { resizeEvent = event } );

resizing.fire( 'resize', { type : 'resize' } );
resizing.fire( 'resize', { type : 'resize' } );
check( 'a burst of resize events asks for one animation frame too', resizing.frames() === 1 );

resizing.runFrames();
check( '...and a resize callback is handed the resize event, not the last scroll', resizeEvent !== null && resizeEvent.type === 'resize' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
