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

const documentElement = { clientHeight : 800, clientWidth : 1440, scrollLeft : 0, scrollTop : 0, style : {} };
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
	getComputedStyle : function() { return { getPropertyValue : function() { return '0px' } } },
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
documentElement.style = {};
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
check( '...and the three that run forever are still stopped outright, after the clamp',
	reduceBlock.indexOf('animation: none !important;') > reduceBlock.indexOf('transition-duration: .01ms !important;')
	&& reduceBlock.includes('.nino-hero-arrowdown') && reduceBlock.includes('.nino-fx-vertical-bounce') );

/*	The base site ships no cookie banner. Consent is the Consent feature's job:
	it owns the choice, its storage and the event. A kernel that kept a second
	copy of that state would answer a question the feature is there to answer	*/
check( 'the kernel keeps no consent state of its own - that is the Consent feature', ui.cookieConsent === undefined );
check( '...and neither the script nor the stylesheet still drives or styles a banner', /cookie-banner|cookieConsent|data-cookie-consent/.test( uiSource ) === false && publicCss.includes('cookie-banner') === false );

done();
