/**
 *	Nino									A compact filesystembased php framework
 *	nino-core-js-smoke.js	The resize/scroll throttle of Nino.js, which every page
 *												uses and no test covered: every registered callback
 *												hangs off it, and it runs on every frame of a scroll.
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
 *	@return		{Object}
 */
function page() {

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
		location : { pathname : '/', origin : 'https://example.com', href : 'https://example.com/' },
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


// --- The resize and scroll throttle ----------------------------------------

console.log( 'Nino.events - one callback round per frame, not one per event\n' );

const scrolling = page();
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

const resizing = page();
let resizeEvent = null;
resizing.Nino.events.bindCallback( 'resize', function( event ) { resizeEvent = event } );

resizing.fire( 'resize', { type : 'resize' } );
resizing.fire( 'resize', { type : 'resize' } );
check( 'a burst of resize events asks for one animation frame too', resizing.frames() === 1 );

resizing.runFrames();
check( '...and a resize callback is handed the resize event, not the last scroll', resizeEvent !== null && resizeEvent.type === 'resize' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
