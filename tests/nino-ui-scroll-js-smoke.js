/**
 *	Nino									A compact filesystembased php framework
 *	nino-ui-scroll-js-smoke.js	The scroll round every public page runs: the
 *													parallax offsets, and the one-callback-round-per-frame
 *													gate they hang off. A callback that throws used to take
 *													the gate down with it - and with the gate stuck shut,
 *													every scroll behaviour on the page stopped for good. And
 *													the header that grows a row: --nino-header-grow, the part
 *													of the bar above its min-height.
 *
 *	Usage: node tests/nino-ui-scroll-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { check, done, load, classList } = require( './harness.js' );

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

/**
 *	The bar of the header, as the observer measures it: the height it has at the
 *	moment, the min-height its frame gives it, the transitions it is running
 *	(the list is the test's to fill) and the listeners it was given
 */
const bar = {
	height : 80, minHeight : '80px', animations : [], listeners : [],
	getBoundingClientRect : function() { return { height : bar.height } },
	getAnimations : function() { return bar.animations },
	addEventListener : function( name, listener ) { bar.listeners.push( { name : name, listener : listener } ) },
};

/**
 *	ResizeObserver, as far as the script uses it: it remembers what it watches,
 *	and fires when the test says the size changed
 */
const observers = [];
function ResizeObserver( callback ) {
	this.callback = callback;
	this.watching = [];
	observers.push( this );
}
ResizeObserver.prototype.observe = function( element ) { this.watching.push( element ) };
ResizeObserver.prototype.fire = function() { this.callback( this.watching.map( function( target ) { return { target : target } } ) ) };

/**
 *	Something an arrow can scroll to: it records how it was scrolled to
 *
 *	@return		{Object}
 */
function scrollTarget() {
	return { scrolled : [], scrollIntoView : function( options ) { this.scrolled.push( options.behavior ) } };
}

/**
 *	The arrow of a hero: it takes a click listener, answers the two questions
 *	the script asks it - its data attributes and its section - and clicks, with
 *	an event that remembers whether it was told not to follow its link
 *
 *	@param		{Object}			attributes		{ target: the selector of data-arrow-target, next: whether it has data-arrow-next }
 *	@param		{Object|null}	section				The section the arrow is in, none for an arrow that is in no section
 *
 *	@return		{Object}
 */
function arrow( attributes, section ) {

	const node = {
		dataset : attributes.target === undefined ? {} : { arrowTarget : attributes.target },
		listeners : [],
		addEventListener : function( name, listener ) { if( name === 'click' ) node.listeners.push( listener ) },
		hasAttribute : function( name ) { return name === 'data-arrow-next' && attributes.next === true },
		closest : function( selector ) { return selector === 'section' ? section : null },
		click : function() {
			const event = { prevented : false, preventDefault : function() { event.prevented = true } };
			node.listeners.forEach( function( listener ) { listener.call( node, event ) } );
			return event;
		},
	};

	return node;
}

const nextSection = scrollTarget();
const targeted = scrollTarget();
const heroSection = { nextElementSibling : nextSection };
const lastSection = { nextElementSibling : null };
const arrows = {
	next : arrow( { next : true }, heroSection ), targeted : arrow( { target : '#targeted' }, heroSection ), both : arrow( { target : '#targeted', next : true }, heroSection ),
	last : arrow( { next : true }, lastSection ), nowhere : arrow( { next : true }, null ), plain : arrow( {}, heroSection ),
};

const frames = [];

/** The inline style of <html>, which takes the custom properties the script writes */
function rootStyle() {
	const properties = {};
	return { properties : properties, setProperty : function( name, value ) { properties[name] = value } };
}

const documentElement = { clientHeight : 800, clientWidth : 1440, scrollLeft : 0, scrollTop : 0, style : rootStyle() };
// The body records what is written onto it, so the namespace check below can
// read it back
const bodyClasses = new Set();
const body = {
	classList : {
		add : function( value ) { bodyClasses.add( value ) },
		remove : function( value ) { bodyClasses.delete( value ) },
		contains : function( value ) { return bodyClasses.has( value ) },
	},
	scrollLeft : 0, scrollTop : 0,
};
const document = {
	body : body,
	documentElement : documentElement,
	getElementById : function() { return null },
	querySelector : function( selector ) { return selector === '#targeted' ? targeted : null },
	querySelectorAll : function( selector ) {
		if( selector === '.nino-hero-arrowdown' )
			return Object.values( arrows );
		if( selector === 'header .nino-scroll-header' )
			return [ bar ];
		return selector === '.nino-parallex' ? [ withPicture, withoutPicture ] : [];
	},
	createElement : function() { return { classList : classList(), style : {}, setAttribute : function() {}, appendChild : function() {} } },
};

// What the visitor asked their system for, answerable either way from the
// test - the whole point of the checks at the end of this file
let reducedMotion = false;

const sandbox = {
	console : console,
	document : document,
	innerHeight : 800,
	innerWidth : 1440,
	location : { hash : '' },
	requestAnimationFrame : function( fn ) { frames.push( fn ); return frames.length },
	setTimeout : function() {},
	addEventListener : function() {},
	getComputedStyle : function( element ) { return { getPropertyValue : function( name ) { return element === bar && name === 'min-height' ? bar.minHeight : '0px' } } },
	ResizeObserver : ResizeObserver,
	matchMedia : function( query ) { return { matches : query === '(prefers-reduced-motion: reduce)' && reducedMotion } },
};
sandbox.window = sandbox;
sandbox.Nino = {
	client : { isMobile : false },
	events : { bindCallback : function() {} },
};

load( '_nino/Nino.ui.js', vm.createContext( sandbox ) );

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


// --- The header that grows a row --------------------------------------------

/*	The header is fixed and main clears it with the bar's min-height; a bar
	that wraps its links onto a second row is taller than that, and the part
	above the min-height is --nino-header-grow, which the padding of main adds	*/
const growOf = function() { return documentElement.style.properties['--nino-header-grow'] };
check( 'the bar of the header is watched by one ResizeObserver', observers.length === 1 && observers[0].watching.length === 1 && observers[0].watching[0] === bar );
observers[0].fire();
check( 'a bar of one row writes 0px onto <html>', growOf() === '0px' );
bar.height = 128.4;
observers[0].fire();
check( 'a bar that grew a row writes what it is taller than its min-height, rounded up', growOf() === '49px' );
bar.height = 60;
observers[0].fire();
check( '...and never less than 0px, for a bar shorter than its min-height', growOf() === '0px' );

bar.height = 176;
observers[0].fire();
bodyClasses.add( 'nino-scroll-down' );
bar.height = 0;
bar.minHeight = '0px';
observers[0].fire();
check( 'while the page is scrolled down the folded bar leaves the padding where it was', growOf() === '96px' );
bodyClasses.delete( 'nino-scroll-down' );
bar.height = 152;
bar.minHeight = '80px';
observers[0].fire();
check( '...and the bar open again is measured again', growOf() === '72px' );

/*	Opening again, max-height travels up from 0 while the min-height is back at
	once: for a moment the box is no taller than its min-height, and measured then
	the padding would drop and come back. So the bar is measured when the
	transition has ended, and not while it runs	*/
const transitionEnd = function( target, propertyName ) {
	bar.listeners.filter( function( entry ) { return entry.name === 'transitionend' } ).forEach( function( entry ) { entry.listener( { target : target, propertyName : propertyName } ) } );
};
bar.animations = [ { transitionProperty : 'max-height' } ];
bar.height = 80;
observers[0].fire();
check( 'a bar whose max-height is on its way writes nothing, however tall it is at that moment', growOf() === '72px' );
bar.height = 176;
observers[0].fire();
check( '...not for the heights on the way either', growOf() === '72px' );
bar.animations = [];
transitionEnd( bar, 'opacity' );
transitionEnd( {}, 'max-height' );
check( '...and the end of another transition, or of one of something else, measures nothing', growOf() === '72px' );
transitionEnd( bar, 'max-height' );
check( '...the end of its max-height measures it', growOf() === '96px' );
bar.animations = [ { transitionProperty : 'opacity' } ];
bar.height = 152;
observers[0].fire();
check( 'a transition of anything but the max-height does not hold the measuring back', growOf() === '72px' );
bar.animations = [];

let writes = 0;
documentElement.style.setProperty = function( name, value ) { writes++; documentElement.style.properties[name] = value };
observers[0].fire();
observers[0].fire();
check( 'a height that has not changed is not written again', writes === 0 );
bar.height = 176;
observers[0].fire();
check( '...a changed one is', writes === 1 && growOf() === '96px' );

bar.minHeight = 'auto';
observers[0].fire();
check( 'a bar whose min-height is no length is no bar to measure against: 0px', growOf() === '0px' );
bar.minHeight = '80px';


// --- A visitor who asked for less motion ---------------------------------

/*	Nino.css has said this since the parallax was written, and the three
	scrolls this file starts said the opposite: scrollIntoView and scrollTo
	take the behaviour as an option, and an option wins over whatever the
	stylesheet says - so a hash link, the down arrow and "back to top" all
	rode smoothly however the system was set	*/
check( 'a scroll asks the system before it decides how to travel', ui._scrollBehavior() === 'smooth' );
reducedMotion = true;
check( '...and a visitor who asked for less motion is simply put there', ui._scrollBehavior() === 'auto' );
reducedMotion = false;

const uiSource = fs.readFileSync( path.join( __dirname, '../_nino/Nino.ui.js' ), 'utf8' );
check( 'no scroll in the file hardcodes the ride', /behavior:\s*'smooth'/.test( uiSource.replace( /^\s*\*.*$/gm, '' ) ) === false );


// --- The arrow of a hero -------------------------------------------------

/*	The link [scroll-hint] writes has a # for an address and says data-arrow-next:
	the script takes it to what follows the section it is in - a # that followed
	itself would take the page to its top - and the hand-written arrow, a button
	with data-arrow-target, goes where it always went	*/
const nextClick = arrows.next.click();
check( 'the arrow marked data-arrow-next scrolls to what follows its section, as the visitor travels, and its # is not followed', nextSection.scrolled.join() === 'smooth' && nextClick.prevented === true );
reducedMotion = true;
arrows.next.click();
reducedMotion = false;
check( '...and a visitor who asked for less motion is put there', nextSection.scrolled.join() === 'smooth,auto' );
const targetClick = arrows.targeted.click();
check( 'the arrow with a data-arrow-target scrolls to its target, as it did, and has nothing to prevent', targeted.scrolled.join() === 'smooth' && targetClick.prevented === false && nextSection.scrolled.length === 2 );
arrows.both.click();
check( '...and where it says both, the target it names wins', targeted.scrolled.length === 2 && nextSection.scrolled.length === 2 );
const lastClick = arrows.last.click();
const nowhereClick = arrows.nowhere.click();
check( 'an arrow with no section after its own, or in none, scrolls nowhere - and its # still does not take the page to the top', nextSection.scrolled.length === 2 && targeted.scrolled.length === 2 && lastClick.prevented === true && nowhereClick.prevented === true );
const plainClick = arrows.plain.click();
check( 'an arrow with neither attribute is a link like any other: the script does nothing for it', plainClick.prevented === false && nextSection.scrolled.length === 2 && targeted.scrolled.length === 2 );

/*	How an ordinary anchor travels is the stylesheet's to say: Nino.css sets
	html's scroll-behavior and takes it back for a visitor who asked for less
	motion, and a project's own stylesheet can say otherwise. An inline style
	on <html> is the one declaration neither can overrule without !important,
	so the script writes none, whatever the visitor asked for	*/
documentElement.style = rootStyle();
reducedMotion = true;
ui.onReady();
reducedMotion = false;
ui.onReady();
check( 'the script leaves html\'s scroll-behavior to the stylesheets, either way the visitor asked', documentElement.style.scrollBehavior === undefined );

/*	Everything the script writes onto <body> is a hook a project's stylesheet
	may style, and the namespace is the promise that it will not collide with a
	project's own class	*/
check( 'every class the script writes onto <body> is in the nino- namespace'+ ( Array.from( bodyClasses ).every( function( name ) { return name.startsWith('nino-') } ) ? '' : ' - '+ Array.from( bodyClasses ).join( ', ' ) ),
	bodyClasses.size > 0 && Array.from( bodyClasses ).every( function( name ) { return name.startsWith('nino-') } ) );

/*	And the stylesheet's own half: the block used to stop three named
	animations and one transition, while the twenty-four other transitions the
	file declares kept their full travel - the slider's 600ms slide, the
	burger menu, the toast, the preloader's one-second fade. A clamp on every
	element is what covers the next component too	*/
const publicCss = fs.readFileSync( path.join( __dirname, '../_nino/Nino.css' ), 'utf8' );
const reduceBlock = ( publicCss.match( /@media \(prefers-reduced-motion: reduce\) \{(.*?)\n\}/s ) || [ '', '' ] )[1];
check( 'the stylesheet clamps every transition and animation, not a named few',
	/\*,\s*\*::before,\s*\*::after \{[^}]*animation-duration: \.01ms !important;[^}]*transition-duration: \.01ms !important;/s.test( reduceBlock ) );
check( '...and the three that move on their own are still stopped outright, after the clamp',
	reduceBlock.indexOf('animation: none !important;') > reduceBlock.indexOf('transition-duration: .01ms !important;')
	&& reduceBlock.includes('.nino-hero-arrowdown') && reduceBlock.includes('.nino-fx-vertical-bounce') );

/*	The picture of a parallax stands at 180% of the section, room for the travel. In a window under
	768px that is a picture many times wider than the screen and a few percent of it in view, so the
	return to the size of the section - the one a visitor who asked for less motion, or a touch screen,
	has - holds for the narrow window too, for the image of the section and for the one in its background	*/
const parallaxReturns = publicCss.match( /@media ([^{]*)\{\s*\.nino-parallex > (?:\.nino-section-bg > )?img \{[^}]*height: 100%/g ) || [];
check( 'both rules that give a parallax picture its own size back - the picture of the section and the one in its background - are asked for in a window under 768px as well as for reduced motion and a touch screen',
	parallaxReturns.length === 2 && parallaxReturns.every( function( rule ) { return rule.includes( 'prefers-reduced-motion: reduce' ) && rule.includes( 'pointer: coarse' ) && rule.includes( 'max-width: 767.98px' ) } ) );

/*	The base site ships no cookie banner. Consent is the Consent feature's job:
	it owns the choice, its storage and the event. A kernel that kept a second
	copy of that state would answer a question the feature is there to answer	*/
check( 'the kernel keeps no consent state of its own - that is the Consent feature', ui.cookieConsent === undefined );
check( '...and neither the script nor the stylesheet still drives or styles a banner', /cookie-banner|cookieConsent|data-cookie-consent/.test( uiSource ) === false && publicCss.includes('cookie-banner') === false );

done();
