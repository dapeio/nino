/**
 *	Nino								A compact filesystembased php framework
 *	nino-ui-slider-js-smoke.js	DOM-light checks for slider touch direction
 *									locking and native vertical scrolling.
 *
 *	Usage: node tests/nino-ui-slider-js-smoke.js
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

function classList() {
	const values = new Set();
	return {
		add 	: function( value ) { values.add( value ); },
		remove 	: function( value ) { values.delete( value ); },
		contains: function( value ) { return values.has( value ); },
	};
}

function element( tagName ) {
	return {
		tagName 	: tagName,
		children 	: [],
		classList 	: classList(),
		listeners 	: {},
		style 		: {},
		addEventListener : function( type, callback, options ) {
			this.listeners[type] = { callback : callback, options : options };
		},
		appendChild : function( child ) {
			this.children.push( child );
			return child;
		},
	};
}

const slides = [ element('LI'), element('LI'), element('LI') ];
const stage = element('UL');
stage.getElementsByTagName = function( tagName ) { return tagName === 'li' ? slides : []; };
stage.getBoundingClientRect = function() { return { height : 180 }; };

const slider = element('DIV');
slider.attributes = { 'data-slider-pos' : '0' };
slider.getAttribute = function( name ) { return this.attributes[name] ?? null; };
slider.getBoundingClientRect = function() { return { width : 320 }; };
slider.getElementsByTagName = function( tagName ) { return tagName === 'ul' ? [ stage ] : []; };

const documentElement = { clientHeight : 640, clientWidth : 320, scrollLeft : 0, scrollTop : 0, style : {} };
const body = { classList : classList(), scrollLeft : 0, scrollTop : 0 };
const document = {
	body 				: body,
	documentElement 	: documentElement,
	createElement 		: function( tagName ) { return element( tagName ); },
	getElementById 		: function() { return null; },
	querySelectorAll 	: function( selector ) { return selector === '.nino-slider' ? [ slider ] : []; },
};

const sandbox = {
	console 			: console,
	document 		: document,
	innerHeight 	: 640,
	innerWidth 		: 320,
	location 		: { hash : '' },
	requestAnimationFrame : function() {},
	setTimeout 		: function() {},
	addEventListener : function() {},
};
sandbox.window = sandbox;
sandbox.Nino = {
	client 	: { isMobile : true },
	events 	: { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_nino/Nino.ui.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'Nino.ui.js' }
);

const ui = sandbox.Nino.ui;

check( 'movement below the tolerance stays undecided', ui._sliderTouchAxis( 7, 7 ) === null );
check( 'mostly horizontal movement selects the slider', ui._sliderTouchAxis( 20, 4 ) === 'x' );
check( 'mostly vertical movement selects page scrolling', ui._sliderTouchAxis( 4, 20 ) === 'y' );
check( 'equal diagonal movement favours page scrolling', ui._sliderTouchAxis( 12, 12 ) === 'y' );

ui.onReady();
ui._onResize[0]();

check( 'the slider explicitly permits vertical pan and pinch zoom', slider.style.touchAction === 'pan-y pinch-zoom' );
check( 'touchmove is registered as non-passive for horizontal preventDefault', slider.listeners.touchmove.options.passive === false );

function touch( type, clientX, clientY, touchCount ) {
	let prevented = false;
	const points = [];
	for( let i=0; i<( touchCount ?? 1 ); i++ )
		points.push( { clientX : clientX + i, clientY : clientY + i } );
	slider.listeners[type].callback.call( slider, {
		changedTouches : [ points[points.length - 1] ],
		touches 		: points,
		preventDefault : function() { prevented = true; },
	} );
	return prevented;
}

const restingLeft = stage.style.left;
touch( 'touchstart', 100, 100 );
check( 'a vertical move does not prevent native scrolling', touch( 'touchmove', 104, 120 ) === false );
check( 'a vertical move does not drag the slider track', stage.style.left === restingLeft );
touch( 'touchend', 106, 170 );
check( 'a vertical gesture does not change the active slide', slider.pos === 0 );

touch( 'touchstart', 200, 100 );
check( 'a horizontal move prevents the browser gesture', touch( 'touchmove', 165, 104 ) === true );
check( 'a horizontal move drags the slider track', stage.style.left === ( slider.posLeft - 35 ) +'px' );
check( 'horizontal dragging disables the track transition', slider.classList.contains('nino-is-touch') === true );
touch( 'touchend', 130, 105 );
check( 'a horizontal swipe changes the active slide', slider.pos === 1 );
check( 'touchend leaves the track centered and animated', slider.classList.contains('nino-is-touch') === false && stage.style.left === slider.posLeft +'px' );

touch( 'touchstart', 200, 100 );
touch( 'touchmove', 170, 103 );
slider.listeners.touchcancel.callback.call( slider );
check( 'touchcancel clears dragging mode', slider.classList.contains('nino-is-touch') === false );
check( 'touchcancel restores the centered track', stage.style.left === slider.posLeft +'px' );

touch( 'touchstart', 200, 100 );
touch( 'touchmove', 170, 103 );
touch( 'touchstart', 160, 100, 2 );
check( 'a second finger releases horizontal dragging', slider.classList.contains('nino-is-touch') === false );
check( 'a multi-touch move remains available to native pinch zoom', touch( 'touchmove', 150, 100, 2 ) === false );

// A slider that names no start position falls back to the middle slide.
// parseInt() answers NaN for the missing attribute, and NaN is not nullish,
// so the `?? Math.floor(...)` that used to stand here never ran: pos was NaN,
// and every sliderMove() computed from it a no-op.
// Last in the file on purpose - it re-runs onReady() on the same fixture
delete slider.attributes['data-slider-pos'];
ui.onReady();
check( 'a slider without data-slider-pos starts on the middle slide', slider.pos === 1 );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
