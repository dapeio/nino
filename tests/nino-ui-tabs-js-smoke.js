/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-tabs-js-smoke.js	DOM-light checks for accessible tab setup.
 *
 *	Usage: node tests/nino-ui-tabs-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, classList, element, fire } = require( './harness.js' );

const nav = element('div');
const tabs = [ element('button'), element('button'), element('button') ];
const panels = [ element('div'), element('div'), element('div') ];
panels.forEach( function( panel, index ) { panel.id = 'demo-tab-'+ index } );
tabs.forEach( function( tab, index ) { tab.attributes['data-tabs-target'] = panels[index].id } );

const tabsWrap = {
	querySelectorAll : function( selector ) {
		if( selector === '.nino-tabs-tab' ) return tabs;
		if( selector === '.nino-tabs-panel' ) return panels;
		return [];
	},
	querySelector : function( selector ) { return selector === '.nino-tabs-nav' ? nav : null },
};

const body = { classList : classList(), scrollLeft : 0, scrollTop : 0 };
const documentElement = { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {} };
const document = {
	body : body,
	documentElement : documentElement,
	getElementById : function( id ) { return panels.find( function( panel ) { return panel.id === id } ) || null },
	querySelectorAll : function( selector ) { return selector === '.nino-tabs' ? [ tabsWrap ] : [] },
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

check( 'tab navigation receives its semantic role', nav.attributes.role === 'tablist' );
check( 'the first tab becomes active when markup has no active state', tabs[0].classList.contains('nino-is-active') && tabs[0].attributes['aria-selected'] === 'true' );
check( 'inactive tabs leave the keyboard tab order', tabs[1].tabIndex === -1 && tabs[2].tabIndex === -1 );
check( 'the first panel is visible and linked to its tab', panels[0].hidden === false && panels[0].attributes['aria-labelledby'] === tabs[0].id );
check( 'inactive panels start hidden', panels[1].hidden === true && panels[2].hidden === true );

fire( tabs[1], 'click' );
check( 'clicking a tab switches the active state', tabs[1].classList.contains('nino-is-active') && tabs[0].classList.contains('nino-is-active') === false );
check( 'clicking a tab switches the visible panel', panels[1].hidden === false && panels[0].hidden === true );

let prevented = false;
fire( tabs[1], 'keydown', { key : 'ArrowRight', preventDefault : function() { prevented = true } } );
check( 'arrow navigation prevents page movement', prevented === true );
check( 'arrow navigation activates and focuses the next tab', tabs[2].classList.contains('nino-is-active') && tabs[2].focused === true );

fire( tabs[2], 'keydown', { key : 'Home', preventDefault : function() {} } );
check( 'Home returns to the first tab', tabs[0].classList.contains('nino-is-active') && tabs[0].focused === true );

done();
