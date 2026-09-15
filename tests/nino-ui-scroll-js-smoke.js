/**
 *	Nino									A compact filesystembased php framework
 *	nino-ui-scroll-js-smoke.js	The scroll round every public page runs: the
 *													parallax offsets, and the one-callback-round-per-frame
 *													gate they hang off. A callback that throws used to take
 *													the gate down with it - and with the gate stuck shut,
 *													every scroll behaviour on the page stopped for good.
 *
 *	Usage: node tests/nino-ui-scroll-js-smoke.js
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

function classList() {
	return { add : function() {}, remove : function() {}, contains : function() { return false; } };
}

/**
 *	One .nino-parallex box, with or without the picture inside it
 *
 *	@param		{Object|null}	image		The <img> the box holds, or null
 *	@param		{number}			top			Where the box sits in the viewport
 */
function parallex( image, top ) {
	return {
		style : {},
		classList : classList(),
		getBoundingClientRect : function() { return { top : top, bottom : top + 300 } },
		querySelector : function( selector ) { return selector === 'img' ? image : null },
	};
}

const picture = { style : {} };
const withPicture = parallex( picture, 120 );
// A box a project wrote without a picture in it yet - or with the picture in
// a wrapper this does not look into
const withoutPicture = parallex( null, 200 );

const frames = [];

const documentElement = { clientHeight : 800, clientWidth : 1440, scrollLeft : 0, scrollTop : 0, style : {} };
const body = { classList : classList(), scrollLeft : 0, scrollTop : 0 };
const document = {
	body : body,
	documentElement : documentElement,
	getElementById : function() { return null },
	querySelector : function() { return null },
	querySelectorAll : function( selector ) { return selector === '.nino-parallex' ? [ withPicture, withoutPicture ] : [] },
	createElement : function() { return { classList : classList(), style : {}, setAttribute : function() {}, appendChild : function() {} } },
};

const sandbox = {
	console : console,
	document : document,
	innerHeight : 800,
	innerWidth : 1440,
	location : { hash : '' },
	requestAnimationFrame : function( fn ) { frames.push( fn ); return frames.length },
	setTimeout : function() {},
	addEventListener : function() {},
	getComputedStyle : function() { return { getPropertyValue : function() { return '0px' } } },
};
sandbox.window = sandbox;
sandbox.Nino = {
	client : { isMobile : false },
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_nino/Nino.ui.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'Nino.ui.js' }
);

const ui = sandbox.Nino.ui;

/** Run the frame the last onScroll() asked for, if it asked for one */
function runFrame() {
	const queued = frames.splice( 0, frames.length );
	queued.forEach( function( fn ) { fn() } );
	return queued.length;
}

// One behaviour is registered on any page (the scrolled-header class); the
// parallax boxes add a second
ui.onReady();
check( 'the parallax boxes register a scroll behaviour of their own', ui._onScroll.length === 2 );

ui.onScroll();
let parallexThrew = null;
try { runFrame(); } catch( error ) { parallexThrew = error.constructor.name+ ': '+ error.message }
check( 'a box without a picture in it is skipped rather than throwing', parallexThrew === null );
check( '...while the box that has one is offset against the scroll', picture.style.top !== undefined && picture.style.top !== '' );

// The gate: one callback round per animation frame, and the next scroll
// after that round is taken again
ui.onScroll();
ui.onScroll();
check( 'a burst of scroll events asks for one frame, not one each', frames.length === 1 );
runFrame();
ui.onScroll();
check( 'and the next scroll after the round is taken again', frames.length === 1 );
runFrame();

// A callback that throws is a project's own, or a feature's - and it must not
// take the page's whole scroll handling with it. The gate used to be cleared
// after the callbacks ran, so a throw left it shut and nothing scrolled again
ui._onScroll.push( function() { throw new Error( 'a callback of somebody elses' ) } );

ui.onScroll();
try { runFrame(); } catch( error ) { /* the point is what happens after it */ }

let ranAfterThrow = 0;
ui._onScroll = [ function() { ranAfterThrow++ } ];
ui.onScroll();
runFrame();
check( 'a scroll callback that throws does not leave the gate shut', ranAfterThrow === 1 );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
