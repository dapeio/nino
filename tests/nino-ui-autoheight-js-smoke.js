/**
 *	Nino							A compact filesystembased php framework
 *	nino-ui-autoheight-js-smoke.js	DOM-light checks for the row equalizer and
 *									its per-element mobile opt-out.
 *
 *	Usage: node tests/nino-ui-autoheight-js-smoke.js
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

const sandbox = {
	console : console,
	document : document,
	innerHeight : 720,
	innerWidth : 390,
	location : { hash : '' },
	requestAnimationFrame : function() {},
	setTimeout : function() {},
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

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_nino/Nino.ui.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'Nino.ui.js' }
);

const ui = sandbox.Nino.ui;
ui.onReady();

check( 'autoheight initialization registers resize behavior for the collection', ui._onResize.length > 0 );
check( 'every element carries the group it was declared for', short.grp === 'row' && alone.grp === 'aside' );

ui.onResize();

check( 'the tallest measured card of the group sets the height of the group', short.style.height === '150px' && tall.style.height === '150px' );
check( 'the element that opted out of mobile is left without a height', typeof optedOut.style.height === 'undefined' );
check( '...and is not measured into its group either', short.style.height !== '180px' );
check( 'an opted-out element alone in its group is not handed its empty group', typeof alone.style.height === 'undefined' );

// The same page above the mobile break: the opt-out is per element and per
// client, so nothing it protects on a phone may be skipped on a desktop
sandbox.Nino.client.isMobile = false;
ui.onResize();

check( 'the opt-out does not survive onto a desktop client', optedOut.style.height === '180px' );
check( '...and the group follows the card that was left out of it before', short.style.height === '180px' && tall.style.height === '180px' );
check( '...and the single card is equalized against itself', alone.style.height === '200px' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
