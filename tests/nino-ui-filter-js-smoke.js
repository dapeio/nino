/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-filter-js-smoke.js	DOM-light checks for the nino-filter category
 *											filter behavior.
 *
 *	Usage: node tests/nino-ui-filter-js-smoke.js
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

function classList( initial ) {
	const values = new Set( initial || [] );
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
		toggle : function( value, force ) {
			if( force === true ) values.add( value );
			else if( force === false ) values.delete( value );
			else if( values.has( value ) ) values.delete( value );
			else values.add( value );
			return values.has( value );
		},
	};
}

function element() {
	return {
		hidden : false,
		attributes : {},
		listeners : {},
		classList : classList(),
		setAttribute : function( name, value ) { this.attributes[name] = String( value ) },
		getAttribute : function( name ) { return this.attributes[name] ?? null },
		addEventListener : function( name, callback ) { this.listeners[name] = callback },
	};
}

function makeFilterRoot( buttons, items ) {
	return {
		querySelectorAll : function( selector ) {
			if( selector === '.nino-filter-btn' ) return buttons;
			if( selector === '.nino-filter-item' ) return items;
			return [];
		},
	};
}

// A normal filter: "Alle" starts active/pressed, two category buttons, three
// cards - two "Design", one "Consulting" - none hidden yet (markup default).
const allBtn = element();
allBtn.attributes['data-filter-value'] = '';
allBtn.classList.add('nino-is-active');
allBtn.attributes['aria-pressed'] = 'true';

const designBtn = element();
designBtn.attributes['data-filter-value'] = 'Design';
designBtn.attributes['aria-pressed'] = 'false';

const consultingBtn = element();
consultingBtn.attributes['data-filter-value'] = 'Consulting';
consultingBtn.attributes['aria-pressed'] = 'false';

const cardA = element(); cardA.attributes['data-filter-item'] = 'Design';
const cardB = element(); cardB.attributes['data-filter-item'] = 'Design';
const cardC = element(); cardC.attributes['data-filter-item'] = 'Consulting';

const filterRoot = makeFilterRoot( [ allBtn, designBtn, consultingBtn ], [ cardA, cardB, cardC ] );

// A second root with buttons but no cards yet - must be skipped without
// wiring any click handler and without throwing.
const strayBtn = element();
const emptyRoot = makeFilterRoot( [ strayBtn ], [] );

const body = { classList : classList(), scrollLeft : 0, scrollTop : 0 };
const documentElement = { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {} };
const document = {
	body : body,
	documentElement : documentElement,
	getElementById : function() { return null },
	querySelectorAll : function( selector ) { return selector === '.nino-filter' ? [ filterRoot, emptyRoot ] : [] },
	createElement : function() { return element() },
};

const sandbox = {
	console : console,
	document : document,
	innerHeight : 640,
	innerWidth : 1024,
	location : { hash : '' },
	requestAnimationFrame : function() {},
	setTimeout : function() {},
	addEventListener : function() {},
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

sandbox.Nino.ui.onReady();

// Anchor first: every "onReady leaves X alone" check below is worthless
// unless onReady actually wired this root, since an untouched DOM trivially
// satisfies them - deleting the whole nino-filter block used to pass three
// checks before the first click threw.
check( 'onReady wires a click handler onto every button of a complete filter', typeof allBtn.listeners.click === 'function'
	&& typeof designBtn.listeners.click === 'function'
	&& typeof consultingBtn.listeners.click === 'function' );
check( '...but skips a filter root that has buttons and no cards yet', typeof strayBtn.listeners.click === 'undefined' );
check( 'onReady does not force an initial selection - markup keeps deciding the active button', allBtn.classList.contains('nino-is-active') === true
	&& designBtn.classList.contains('nino-is-active') === false
	&& allBtn.attributes['aria-pressed'] === 'true' );
check( '...and does not hide any card before the first click', cardA.hidden === false && cardB.hidden === false && cardC.hidden === false );

designBtn.listeners.click.call( designBtn );
check( 'clicking a category button activates it and deactivates the previous one', designBtn.classList.contains('nino-is-active') === true && allBtn.classList.contains('nino-is-active') === false );
check( '...and updates aria-pressed on both', designBtn.attributes['aria-pressed'] === 'true' && allBtn.attributes['aria-pressed'] === 'false' );
check( 'matching cards stay visible', cardA.hidden === false && cardB.hidden === false );
check( 'non-matching cards are hidden', cardC.hidden === true );

consultingBtn.listeners.click.call( consultingBtn );
check( 'clicking a different category switches the visible cards', cardC.hidden === false && cardA.hidden === true && cardB.hidden === true );

allBtn.listeners.click.call( allBtn );
check( '"Alle" (data-filter-value="") shows every card again', cardA.hidden === false && cardB.hidden === false && cardC.hidden === false );
check( '...and re-activates the "Alle" button', allBtn.classList.contains('nino-is-active') === true && consultingBtn.classList.contains('nino-is-active') === false );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
