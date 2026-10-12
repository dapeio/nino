/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-filter-js-smoke.js	DOM-light checks for the nino-filter category
 *											filter behavior.
 *
 *	Usage: node tests/nino-ui-filter-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, classList, element, fire } = require( './harness.js' );

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
const allBtn = element('button');
allBtn.attributes['data-filter-value'] = '';
allBtn.classList.add('nino-is-active');
allBtn.attributes['aria-pressed'] = 'true';

const designBtn = element('button');
designBtn.attributes['data-filter-value'] = 'Design';
designBtn.attributes['aria-pressed'] = 'false';

const consultingBtn = element('button');
consultingBtn.attributes['data-filter-value'] = 'Consulting';
consultingBtn.attributes['aria-pressed'] = 'false';

const cardA = element('div'); cardA.attributes['data-filter-item'] = 'Design';
const cardB = element('div'); cardB.attributes['data-filter-item'] = 'Design';
const cardC = element('div'); cardC.attributes['data-filter-item'] = 'Consulting';

const filterRoot = makeFilterRoot( [ allBtn, designBtn, consultingBtn ], [ cardA, cardB, cardC ] );

// A second root with buttons but no cards yet - must be skipped without
// wiring any click handler and without throwing.
const strayBtn = element('button');
const emptyRoot = makeFilterRoot( [ strayBtn ], [] );

const body = { classList : classList(), scrollLeft : 0, scrollTop : 0 };
const documentElement = { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {} };
const document = {
	body : body,
	documentElement : documentElement,
	getElementById : function() { return null },
	querySelectorAll : function( selector ) { return selector === '.nino-filter' ? [ filterRoot, emptyRoot ] : [] },
	createElement : element,
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

load( '_nino/Nino.ui.js', vm.createContext( sandbox ) );

sandbox.Nino.ui.onReady();

// Anchor first: every "onReady leaves X alone" check below is worthless
// unless onReady actually wired this root, since an untouched DOM trivially
// satisfies them - deleting the whole nino-filter block used to pass three
// checks before the first click threw.
check( 'onReady wires a click handler onto every button of a complete filter', typeof allBtn.listeners.click?.[0] === 'function'
	&& typeof designBtn.listeners.click?.[0] === 'function'
	&& typeof consultingBtn.listeners.click?.[0] === 'function' );
check( '...but skips a filter root that has buttons and no cards yet', strayBtn.listeners.click === undefined );
check( 'onReady does not force an initial selection - markup keeps deciding the active button', allBtn.classList.contains('nino-is-active') === true
	&& designBtn.classList.contains('nino-is-active') === false
	&& allBtn.attributes['aria-pressed'] === 'true' );
check( '...and does not hide any card before the first click', cardA.hidden === false && cardB.hidden === false && cardC.hidden === false );

fire( designBtn, 'click' );
check( 'clicking a category button activates it and deactivates the previous one', designBtn.classList.contains('nino-is-active') === true && allBtn.classList.contains('nino-is-active') === false );
check( '...and updates aria-pressed on both', designBtn.attributes['aria-pressed'] === 'true' && allBtn.attributes['aria-pressed'] === 'false' );
check( 'matching cards stay visible', cardA.hidden === false && cardB.hidden === false );
check( 'non-matching cards are hidden', cardC.hidden === true );

fire( consultingBtn, 'click' );
check( 'clicking a different category switches the visible cards', cardC.hidden === false && cardA.hidden === true && cardB.hidden === true );

fire( allBtn, 'click' );
check( '"Alle" (data-filter-value="") shows every card again', cardA.hidden === false && cardB.hidden === false && cardC.hidden === false );
check( '...and re-activates the "Alle" button', allBtn.classList.contains('nino-is-active') === true && consultingBtn.classList.contains('nino-is-active') === false );

done();
