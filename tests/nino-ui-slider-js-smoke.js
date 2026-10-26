/**
 *	Nino								A compact filesystembased php framework
 *	nino-ui-slider-js-smoke.js	DOM-light checks for slider touch direction
 *									locking and native vertical scrolling.
 *
 *	Usage: node tests/nino-ui-slider-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, load, classList, element, fire } = require( './harness.js' );

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

load( '_nino/Nino.ui.js', vm.createContext( sandbox ) );

const ui = sandbox.Nino.ui;

check( 'movement below the tolerance stays undecided', ui._sliderTouchAxis( 7, 7 ) === null );
check( 'mostly horizontal movement selects the slider', ui._sliderTouchAxis( 20, 4 ) === 'x' );
check( 'mostly vertical movement selects page scrolling', ui._sliderTouchAxis( 4, 20 ) === 'y' );
check( 'equal diagonal movement favours page scrolling', ui._sliderTouchAxis( 12, 12 ) === 'y' );

ui.onReady();
ui._onResize[0]();

check( 'the slider explicitly permits vertical pan and pinch zoom', slider.style.touchAction === 'pan-y pinch-zoom' );
check( 'touchmove is registered as non-passive for horizontal preventDefault', slider.listenerOptions.touchmove.passive === false );

function touch( type, clientX, clientY, touchCount ) {
	let prevented = false;
	const points = [];
	for( let i=0; i<( touchCount ?? 1 ); i++ )
		points.push( { clientX : clientX + i, clientY : clientY + i } );
	fire( slider, type, {
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
fire( slider, 'touchcancel' );
check( 'touchcancel clears dragging mode', slider.classList.contains('nino-is-touch') === false );
check( 'touchcancel restores the centered track', stage.style.left === slider.posLeft +'px' );

touch( 'touchstart', 200, 100 );
touch( 'touchmove', 170, 103 );
touch( 'touchstart', 160, 100, 2 );
check( 'a second finger releases horizontal dragging', slider.classList.contains('nino-is-touch') === false );
check( 'a multi-touch move remains available to native pinch zoom', touch( 'touchmove', 150, 100, 2 ) === false );

// The controls a visitor operates. They were DIVs and bare LIs with a click
// listener: reachable with a pointer and with nothing else - no tab stop, no
// Enter, no Space, and a screen reader announcing '‹'
const controls = slider.children.filter( function( c ) { return c.className === 'nino-slider-controls' } )[0];
const prev = controls.children[0];
const dotList = controls.children[1];
const next = controls.children[2];

check( 'the previous and next controls are real buttons', prev.tagName === 'BUTTON' && next.tagName === 'BUTTON'
	&& prev.type === 'button' && next.type === 'button' );
check( '...with a name a screen reader can read out, not just an arrow', ( prev.attributes['aria-label'] ?? '' ) !== ''
	&& ( next.attributes['aria-label'] ?? '' ) !== '' && prev.textContent === '‹' );
check( 'every dot is a button too', dotList.children.length === 3
	&& dotList.children.every( function( li ) { return ( li.children[0] ?? {} ).tagName === 'BUTTON' } ) );
check( '...each one saying which slide it goes to', ( dotList.children[1].children[0].attributes['aria-label'] ?? '' ).includes( '2' ) );
const dotState = dotList.children.map( function( li ) { return li.children[0].attributes['aria-current'] } );
check( '...and exactly the one showing says that it is', dotState.filter( function( v ) { return v === 'true' } ).length === 1
	&& dotState[ slider.pos ] === 'true' );

// The page's own language decides the word, where the project has none of
// its own
check( 'the words follow the language the page declares', ( prev.attributes['aria-label'] ?? '' ) === 'Previous' );

// A slider that names no start position falls back to the middle slide.
// parseInt() answers NaN for the missing attribute, and NaN is not nullish,
// so the `?? Math.floor(...)` that used to stand here never ran: pos was NaN,
// and every sliderMove() computed from it a no-op.
// Last in the file on purpose - it re-runs onReady() on the same fixture
delete slider.attributes['data-slider-pos'];
ui.onReady();
check( 'a slider without data-slider-pos starts on the middle slide', slider.pos === 1 );

// A word the project has published to [jstext] under /template/common/slider/
// wins over the dictionary built into the script
sandbox.Nino.content = { getText : function( key ) { return key === '/template/common/slider/prev' ? 'Zurück' : '' } };
ui.onReady();
const ownControls = slider.children.filter( function( c ) { return c.className === 'nino-slider-controls' } ).pop();
check( 'a word of the project, under /template/common/slider/, wins over the built-in one', ( ownControls.children[0].attributes['aria-label'] ?? '' ) === 'Zurück'
	&& ( ownControls.children[2].attributes['aria-label'] ?? '' ) === 'Next' );

// A slider is markup a project writes by hand, so it can be markup that is
// not finished: a <ul> with nothing in it yet, or no <ul> at all. Reading the
// slide at the start position threw a TypeError out of onReady(), and
// everything onReady() had not wired yet - every tab strip, form and filter
// after it on the page - stayed unwired
const emptyStage = element('UL');
emptyStage.getElementsByTagName = function() { return [] };
emptyStage.getBoundingClientRect = function() { return { height : 0 } };

const emptySlider = element('DIV');
emptySlider.attributes = {};
emptySlider.getAttribute = function( name ) { return this.attributes[name] ?? null };
emptySlider.getBoundingClientRect = function() { return { width : 320 } };
emptySlider.getElementsByTagName = function( tagName ) { return tagName === 'ul' ? [ emptyStage ] : [] };

const noStage = element('DIV');
noStage.attributes = {};
noStage.getAttribute = function( name ) { return this.attributes[name] ?? null };
noStage.getBoundingClientRect = function() { return { width : 320 } };
noStage.getElementsByTagName = function() { return [] };

document.querySelectorAll = function( selector ) { return selector === '.nino-slider' ? [ emptySlider, noStage, slider ] : [] };

let readyThrew = null;
try { ui.onReady(); } catch( error ) { readyThrew = error.constructor.name+ ': '+ error.message }
check( 'a slider with no slides in it is left alone rather than throwing out of onReady', readyThrew === null );
check( '...and one with no list at all too - nothing appended, nothing wired', readyThrew === null && noStage.children.length === 0 && noStage.pos === undefined );
check( '...while the finished slider beside them is still wired', slider.pos === 1 );

// The widths of the slides: data-slider-width and data-slider-min are lengths, and toPx() reads them - a number is pixels, with the unit px or with none, a number with a % is that share of the
// width of the slider, and what is no such length - no number, a number below 0, a number in any other unit - is read as the fallback by the same rules: '75%' for the width, '300' for the least
// width. A slider whose width is not known yet is left as it is
function sized( attributes, width, count ) {
	const lis = [];
	for( let i = 0; i < ( count ?? 3 ); i++ )
		lis.push( element('LI') );
	const track = element('UL');
	track.getElementsByTagName = function( tagName ) { return tagName === 'li' ? lis : []; };
	track.getBoundingClientRect = function() { return { height : 90 }; };
	const wrap = element('DIV');
	wrap.attributes = Object.assign( { 'data-slider-pos' : '0' }, attributes );
	wrap.getAttribute = function( name ) { return this.attributes[name] ?? null; };
	wrap.measured = width;
	wrap.getBoundingClientRect = function() { return { width : this.measured }; };
	wrap.getElementsByTagName = function( tagName ) { return tagName === 'ul' ? [ track ] : []; };
	wrap.slides = lis;
	wrap.track = track;
	return wrap;
}
function resized( wraps ) {
	document.querySelectorAll = function( selector ) { return selector === '.nino-slider' ? wraps : []; };
	ui.onReady();
	ui._onResize[ ui._onResize.length - 1 ]();
	return wraps.map( function( wrap ) { return wrap.slides.map( function( li ) { return li.style.width; } ); } );
}
function slideWidth( attributes, width ) {
	return resized( [ sized( attributes, width ?? 400 ) ] )[0][0];
}

check( 'a slide is three quarters of the slider where the markup names no width, and a width in percent is that share of the slider',
	slideWidth( {} ) === '300px' && slideWidth( { 'data-slider-width' : '50%' } ) === '200px' && slideWidth( { 'data-slider-width' : '12.5%' } ) === '50px' && slideWidth( { 'data-slider-width' : '75%' }, 640 ) === '480px' );
check( '...a width in pixels is those pixels, with the unit or with none - 300 is 300px, not the 3 that cutting two characters off it left - and a decimal is read whole',
	slideWidth( { 'data-slider-width' : '320px' } ) === '320px' && slideWidth( { 'data-slider-width' : '320' } ) === '320px' && slideWidth( { 'data-slider-width' : '250.5px' } ) === '250.5px'
	&& slideWidth( { 'data-slider-width' : '  60%  ' } ) === '240px' && slideWidth( { 'data-slider-width' : '12px' } ) === '12px' && slideWidth( { 'data-slider-width' : '320PX' } ) === '320px' );
check( '...a width that is no length - not a number, nothing, a % alone, a number below 0, one that is not finite, a number in a unit that is neither px nor % - is the three quarters of the slider, by the same rules: 40rem is not 40 pixels, and on a slider of 640 it is the three quarters of that one and not the 300 pixels that are the fallback of the least width',
	[ 'wide', '', '%', 'px', '-20px', '-5%', 'Infinity', 'NaN', '40rem', '2em', '50vw', '320 px' ].every( function( value ) { return slideWidth( { 'data-slider-width' : value } ) === '300px'; } )
	&& slideWidth( { 'data-slider-width' : '40rem' }, 640 ) === '480px' );
check( '...the least width keeps a slide from the share of the slider that would be narrower, in pixels or in percent, and has no effect where it is the narrower of the two',
	slideWidth( { 'data-slider-width' : '25%', 'data-slider-min' : '150px' } ) === '150px' && slideWidth( { 'data-slider-width' : '25%', 'data-slider-min' : '150' } ) === '150px'
	&& slideWidth( { 'data-slider-width' : '25%', 'data-slider-min' : '60%' } ) === '240px' && slideWidth( { 'data-slider-width' : '75%', 'data-slider-min' : '100px' } ) === '300px'
	&& slideWidth( { 'data-slider-width' : '25%', 'data-slider-min' : '0' } ) === '100px' );
check( '...none named is none, and a least width that is no length is 300 pixels, by the same rules as the width\'s',
	slideWidth( { 'data-slider-width' : '25%' } ) === '100px'
	&& [ 'big', '', '%', '-20px', '-5%', 'Infinity', '40rem', '2em', '50vw' ].every( function( value ) { return slideWidth( { 'data-slider-width' : '25%', 'data-slider-min' : value } ) === '300px'; } ) );
check( '...and a slide is never wider than the slider, whichever of the two asks for more',
	slideWidth( { 'data-slider-width' : '150%' } ) === '400px' && slideWidth( { 'data-slider-width' : '900px' } ) === '400px' && slideWidth( { 'data-slider-width' : '25%', 'data-slider-min' : '700px' } ) === '400px'
	&& slideWidth( { 'data-slider-width' : '25%', 'data-slider-min' : 'x' }, 200 ) === '200px' );

// The track is as wide as its slides and stands around the active one, the height is the track's
const placed = sized( { 'data-slider-width' : '50%', 'data-slider-pos' : '1' }, 400, 4 );
resized( [ placed ] );
check( 'the track is as wide as all its slides, stands with the active slide in the middle of the slider, and the slider is as tall as the track',
	placed.track.style.width === '800px' && placed.posLeft === 0 - ( 200 * 1 - ( 400 - 200 ) / 2 ) && placed.track.style.left === placed.posLeft + 'px' && placed.style.height === '90px' );

// A slider that measures 0 wide - hidden, or not laid out yet - keeps the sizes it has, and is measured when the window resizes after it was shown
const hidden = sized( {}, 400 );
const shown = sized( { 'data-slider-width' : '50%' }, 400 );
document.querySelectorAll = function( selector ) { return selector === '.nino-slider' ? [ hidden, shown ] : []; };
ui.onReady();
const resizeBoth = ui._onResize[ ui._onResize.length - 1 ];
resizeBoth();
const before = [ hidden.slides[0].style.width, hidden.track.style.left, hidden.track.style.width, hidden.style.height ];
hidden.measured = 0;
shown.measured = 800;
let zeroThrew = null;
try { resizeBoth(); } catch( error ) { zeroThrew = error.constructor.name+ ': '+ error.message }
check( 'a slider that measures 0 wide is left as it is - no slide of 0px or NaN, no track moved - while the slider beside it is measured again',
	zeroThrew === null && JSON.stringify( [ hidden.slides[0].style.width, hidden.track.style.left, hidden.track.style.width, hidden.style.height ] ) === JSON.stringify( before ) && shown.slides[0].style.width === '400px' );
hidden.measured = 200;
resizeBoth();
check( '...and is sized once it has a width', hidden.slides[0].style.width === '150px' && hidden.slides.every( function( li ) { return li.style.width === '150px'; } ) );

// A slider that was not wired - no list, or a list with nothing in it - has nothing to size: it is left out of the resize, which neither throws nor writes into it, and the sliders beside it are sized
const noList = element('DIV');
noList.attributes = {};
noList.getAttribute = function( name ) { return this.attributes[name] ?? null };
noList.getBoundingClientRect = function() { return { width : 400 } };
noList.getElementsByTagName = function() { return [] };
const emptyList = element('UL');
emptyList.getElementsByTagName = function() { return [] };
emptyList.getBoundingClientRect = function() { return { height : 0 } };
const noSlides = element('DIV');
noSlides.attributes = {};
noSlides.getAttribute = function( name ) { return this.attributes[name] ?? null };
noSlides.getBoundingClientRect = function() { return { width : 400 } };
noSlides.getElementsByTagName = function( tagName ) { return tagName === 'ul' ? [ emptyList ] : [] };
const neighbour = sized( {}, 400 );
document.querySelectorAll = function( selector ) { return selector === '.nino-slider' ? [ noList, noSlides, neighbour ] : []; };
ui.onReady();
let skipThrew = null;
try { ui._onResize[ ui._onResize.length - 1 ](); } catch( error ) { skipThrew = error.constructor.name+ ': '+ error.message }
check( 'a slider without a list is left out of the resize rather than throwing out of it - and the one beside it, wired after it, is sized',
	skipThrew === null && noList.lis === undefined && Object.keys( noList.style ).length === 0 && neighbour.slides[0].style.width === '300px' );
check( '...and one with a list with no slide in it too, whose list is not given a left or a width of NaN',
	skipThrew === null && noSlides.lis.length === 0 && emptyList.style.left === undefined && emptyList.style.width === undefined && noSlides.style.height === undefined );

done();
