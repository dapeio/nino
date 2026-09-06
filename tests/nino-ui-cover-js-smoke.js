/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-cover-js-smoke.js	DOM-light checks for cover sizing inside both
 *								full-width and rail-reduced page content.
 *
 *	Usage: node tests/nino-ui-cover-js-smoke.js
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

function parent( width, paddingLeft, paddingRight ) {
	return {
		clientWidth : width,
		computed : {
			'padding-left' : paddingLeft+ 'px',
			'padding-right' : paddingRight+ 'px',
		},
	};
}

function cover( attributes, containingParent, margins, contentHeight ) {
	return {
		attributes : attributes,
		parentElement : containingParent,
		computed : {
			'margin-top' : ( margins.top || 0 )+ 'px',
			'margin-right' : ( margins.right || 0 )+ 'px',
			'margin-bottom' : ( margins.bottom || 0 )+ 'px',
			'margin-left' : ( margins.left || 0 )+ 'px',
		},
		style : {},
		getAttribute : function( name ) { return this.attributes[name] ?? null; },
		querySelector : function( selector ) { return selector === 'div' ? { offsetHeight : contentHeight } : null; },
	};
}

// Mirrors Header v6 at desktop width: the rail has already reduced <main> to
// 1120px, while the browser viewport remains 1440px.
const railMain = parent( 1120, 0, 0 );
const railCover = cover( { 'data-cover-height' : '100' }, railMain, {}, 180 );

// clientWidth includes padding; cover percentages are relative to the
// containing content box, and its own fixed margins still have to fit inside.
const paddedMain = parent( 900, 20, 30 );
const partialCover = cover( { 'data-cover-width' : '75', 'data-cover-height' : '50' }, paddedMain, { left : 7.25, right : 12.25 }, 120 );

// Detached elements have no containing block to measure. Retaining the
// viewport fallback keeps the helper deterministic during DOM transitions.
const detachedCover = cover( { 'data-cover-width' : '50' }, null, {}, 100 );
const covers = [ railCover, partialCover, detachedCover ];

const documentElement = { clientHeight : 800, clientWidth : 1440, scrollLeft : 0, scrollTop : 0, style : {} };
const body = { classList : classList(), scrollLeft : 0, scrollTop : 0 };
const document = {
	body : body,
	documentElement : documentElement,
	getElementById : function() { return null; },
	querySelector : function() { return null; },
	querySelectorAll : function( selector ) { return selector === '.nino-cover' ? covers : []; },
};

const sandbox = {
	console : console,
	document : document,
	innerHeight : 800,
	innerWidth : 1440,
	location : { hash : '' },
	requestAnimationFrame : function() {},
	setTimeout : function() {},
	addEventListener : function() {},
	getComputedStyle : function( element ) {
		return {
			getPropertyValue : function( property ) { return element.computed?.[property] ?? '0px'; },
		};
	},
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
ui.onReady();

check( 'cover initialization registers resize behavior for the collection', ui._onResize.length > 0 );
ui.onResize();

check( 'a full cover stops at Header v6 main width rather than using 100vw', railCover.style.width === '1120px' );
check( 'cover height remains based on the viewport', railCover.style.height === '800px' );
check( 'custom cover width uses the padded parent content box and precise fixed margins', partialCover.style.width === '618px' );
check( 'custom cover height remains an independent viewport percentage', partialCover.style.height === '400px' );
check( 'a detached cover retains the viewport-width fallback', detachedCover.style.width === '720px' );

railMain.clientWidth = 980;
documentElement.clientWidth = 1600;
ui.onResize();
check( 'a later resize follows the changed container rather than the wider viewport', railCover.style.width === '980px' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
