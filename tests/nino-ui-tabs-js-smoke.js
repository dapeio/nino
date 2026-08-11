/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-tabs-js-smoke.js	DOM-light checks for accessible tab setup.
 *
 *	Usage: node tests/nino-ui-tabs-js-smoke.js
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

function element( id, classes ) {
	return {
		id : id || '',
		hidden : false,
		tabIndex : 0,
		focused : false,
		attributes : {},
		listeners : {},
		classList : classList( classes ),
		setAttribute : function( name, value ) { this.attributes[name] = String( value ) },
		getAttribute : function( name ) { return this.attributes[name] ?? null },
		addEventListener : function( name, callback ) { this.listeners[name] = callback },
		focus : function() { this.focused = true },
	};
}

const nav = element();
const tabs = [ element(), element(), element() ];
const panels = [ element('demo-tab-0'), element('demo-tab-1'), element('demo-tab-2') ];
tabs.forEach( function( tab, index ) { tab.attributes['data-tabs-target'] = panels[index].id } );

const tabsWrap = {
	querySelectorAll : function( selector ) {
		if( selector === '.js-tabs-tab' ) return tabs;
		if( selector === '.js-tabs-panel' ) return panels;
		return [];
	},
	querySelector : function( selector ) { return selector === '.js-tabs-nav' ? nav : null },
};

const body = { classList : classList(), scrollLeft : 0, scrollTop : 0 };
const documentElement = { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {} };
const document = {
	body : body,
	documentElement : documentElement,
	getElementById : function( id ) { return panels.find( function( panel ) { return panel.id === id } ) || null },
	querySelectorAll : function( selector ) { return selector === '.js-tabs' ? [ tabsWrap ] : [] },
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

check( 'tab navigation receives its semantic role', nav.attributes.role === 'tablist' );
check( 'the first tab becomes active when markup has no active state', tabs[0].classList.contains('active') && tabs[0].attributes['aria-selected'] === 'true' );
check( 'inactive tabs leave the keyboard tab order', tabs[1].tabIndex === -1 && tabs[2].tabIndex === -1 );
check( 'the first panel is visible and linked to its tab', panels[0].hidden === false && panels[0].attributes['aria-labelledby'] === tabs[0].id );
check( 'inactive panels start hidden', panels[1].hidden === true && panels[2].hidden === true );

tabs[1].listeners.click.call( tabs[1] );
check( 'clicking a tab switches the active state', tabs[1].classList.contains('active') && tabs[0].classList.contains('active') === false );
check( 'clicking a tab switches the visible panel', panels[1].hidden === false && panels[0].hidden === true );

let prevented = false;
tabs[1].listeners.keydown.call( tabs[1], { key : 'ArrowRight', preventDefault : function() { prevented = true } } );
check( 'arrow navigation prevents page movement', prevented === true );
check( 'arrow navigation activates and focuses the next tab', tabs[2].classList.contains('active') && tabs[2].focused === true );

tabs[2].listeners.keydown.call( tabs[2], { key : 'Home', preventDefault : function() {} } );
check( 'Home returns to the first tab', tabs[0].classList.contains('active') && tabs[0].focused === true );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
