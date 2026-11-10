/**
 *	Nino							A compact filesystembased php framework
 *	nino-ui-autoheight-js-smoke.js	DOM-light checks for the row equalizer and
 *									its per-element mobile opt-out.
 *
 *	Usage: node tests/nino-ui-autoheight-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, classList } = require( './harness.js' );

/**
 *	One .nino-autoheight element: its own content height is what it measures
 *	while no height is forced on it, which is exactly the state the equalizer
 *	puts it in before reading it
 *
 *	@param		{Object}	attributes			The data-autoheight-* attributes it carries
 *	@param		{number}	contentHeight		The height its own content comes to
 *
 *	@return		{Object}
 */
function card( attributes, contentHeight ) {
	return {
		attributes : attributes,
		style : {},
		getAttribute : function( name ) { return this.attributes[name] ?? null; },
		getBoundingClientRect : function() {
			const forced = parseFloat( this.style.height );
			return { height : Number.isFinite( forced ) ? forced : contentHeight };
		},
	};
}

// One row of three cards of uneven length, the middle one opted out of the
// equalizer on mobile - and one card alone in a group of its own that is
// opted out too, so on mobile its group is never measured at all.
const short = card( { 'data-autoheight-group' : 'row' }, 120 );
const optedOut = card( { 'data-autoheight-group' : 'row', 'data-autoheight-mobile' : '1' }, 180 );
const tall = card( { 'data-autoheight-group' : 'row' }, 150 );
const alone = card( { 'data-autoheight-group' : 'aside', 'data-autoheight-mobile' : 'yes' }, 200 );
const cards = [ short, optedOut, tall, alone ];

const documentElement = { clientHeight : 720, clientWidth : 390, scrollLeft : 0, scrollTop : 0, style : {} };
const body = { classList : classList(), scrollLeft : 0, scrollTop : 0 };
const document = {
	body : body,
	documentElement : documentElement,
	getElementById : function() { return null; },
	querySelector : function() { return null; },
	querySelectorAll : function( selector ) { return selector === '.nino-autoheight' ? cards : []; },
};

const timers = [];
const sandbox = {
	console : console,
	document : document,
	innerHeight : 720,
	innerWidth : 390,
	location : { hash : '' },
	requestAnimationFrame : function() {},
	setTimeout : function( fn, delay ) { timers.push( { fn : fn, delay : delay } ) },
	addEventListener : function() {},
	getComputedStyle : function() {
		return { getPropertyValue : function() { return '0px'; } };
	},
};
sandbox.window = sandbox;
sandbox.Nino = {
	client : { isMobile : true },
	events : { bindCallback : function() {} },
};

load( '_nino/Nino.ui.js', vm.createContext( sandbox ) );

const ui = sandbox.Nino.ui;
ui.onReady();

check( 'autoheight initialization registers resize behavior for the collection', ui._onResize.length > 0 );
check( 'every element carries the group it was declared for', short.grp === 'row' && alone.grp === 'aside' );

ui.onResize();

check( 'the tallest measured card of the group sets the height of the group', short.style.height === '150px' && tall.style.height === '150px' );
check( 'the element that opted out of mobile is left without a height', typeof optedOut.style.height === 'undefined' );
check( '...and is not measured into its group either', short.style.height !== '180px' );
check( 'an opted-out element alone in its group is not handed its empty group', typeof alone.style.height === 'undefined' );

// A phone rotated: the width changed, the text wraps otherwise, the rows are measured again
short.style.height = 'stale';
sandbox.innerWidth = 844;
documentElement.clientWidth = 844;
documentElement.clientHeight = 390;
tall.style.height = 'stale';
ui.onResize();
check( 'a width change on a phone equalizes the rows again', tall.style.height === '150px' && short.style.height === '150px' );

// The address bar of a phone showing or hiding changes the height alone
short.style.height = 'stale';
documentElement.clientHeight = 450;
ui.onResize();
check( 'a height change alone on a phone leaves the rows as they are', short.style.height === 'stale' );
documentElement.clientHeight = 390;

// ...and back to portrait: a narrower screen is a width change too
short.style.height = 'stale';
sandbox.innerWidth = 390;
documentElement.clientWidth = 390;
ui.onResize();
check( 'a width change to a narrower screen equalizes the rows as well', short.style.height === '150px' );

// The measurement that settles after the ready event (late layout: fonts, images) runs although a resize has recorded the width by then
const settle = timers.filter( function( timer ) { return timer.delay === 200 } )[0];
short.style.height = 'stale';
settle.fn();
check( 'the settled measurement after ready sizes the rows on a phone although the width has been seen', short.style.height === '150px' );

// The same page above the mobile break: the opt-out is per element and per
// client, so nothing it protects on a phone may be skipped on a desktop
sandbox.Nino.client.isMobile = false;
ui.onResize();

check( 'the opt-out does not survive onto a desktop client', optedOut.style.height === '180px' );
check( '...and the group follows the card that was left out of it before', short.style.height === '180px' && tall.style.height === '180px' );
check( '...and the single card is equalized against itself', alone.style.height === '200px' );

done();
