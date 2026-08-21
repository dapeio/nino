/**
 *	Nino									A compact filesystembased php framework
 *	install-design-js-smoke.js		DOM checks for the install wizard's Design
 *									step: the colour and size controls, the two
 *									specimens, and what design/apply carries.
 *
 *	Usage: node tests/install-design-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
const vm 		= require('vm');

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

/*	A stand-in for the parts of the DOM this step touches	*/
function element( id, tag ) {

	const el = {
		id : id,
		tagName : ( tag || 'div' ).toUpperCase(),
		value : '',
		textContent : '',
		title : '',
		style : {},
		children : [],
		_listeners : {},
		classList : {
			_set : new Set(),
			add : function( name ) { el.classList._set.add( name ); },
			remove : function( name ) { el.classList._set.delete( name ); },
			contains : function( name ) { return el.classList._set.has( name ); },
			toggle : function( name, on ) { if( on === true ) el.classList.add( name ); else el.classList.remove( name ); },
		},
		appendChild : function( child ) { el.children.push( child ); return child; },
		addEventListener : function( type, handler ) { ( el._listeners[type] = el._listeners[type] || [] ).push( handler ); },
		fire : function( type ) { ( el._listeners[type] || [] ).forEach( function( handler ) { handler( {} ); } ); },
	};

	Object.defineProperty( el, 'innerHTML', {
		get : function() { return ''; },
		set : function() { el.children.length = 0; },
	} );

	return el;
}

const nodes = {};
[
	'design-controls', 'design-unavailable', 'design-msg',
	'themes-design-preview', 'themes-design-sizes',
	'themes-design-primary', 'themes-design-primary-hex',
	'themes-design-secondary', 'themes-design-secondary-hex',
	'themes-design-contrast', 'themes-design-colors',
	'themes-design-volume', 'themes-design-spacing', 'themes-design-shaping',
].forEach( function( id ) { nodes[id] = element( id ); } );

const posted = [];

// What /_theme really returns for these settings, so the specimen is checked
// against the shape it actually gets rather than one invented here
const previewResponse = {
	palette : { light : {
		'default'	: { bg : '#ffffff', on : '#0b0e10', border : '#949596' },
		origin 		: { bg : '#147bb7', on : '#fcfcfc', border : '#c2d3df' },
	} },
	raster : {
		text 		: { 1 : '1rem', 2 : '1.125rem', 3 : '1.25rem', 4 : '1.5rem', 5 : '1.8rem', 6 : '2.5rem' },
		space 	: { 1 : '0.5rem', 2 : '1rem', 3 : '2rem', 4 : '3rem', 5 : '5rem', 6 : '8rem' },
		radius 	: { 1 : '0.1rem', 2 : '0.2rem', 3 : '0.4rem' },
		lineHeight : '1.5',
	},
};

let readResponse = {
	settings : { primary : '#4faae8', secondary : '', contrast : 'default', colors : 'default', volume : 'generous', spacing : 'airy', shaping : 'round' },
	choices  : {
		contrast : [ 'soft', 'default', 'high' ],
		colors 	 : [ 'clean', 'default', 'vibrant' ],
		volume 	 : [ 'compact', 'default', 'generous' ],
		spacing  : [ 'tight', 'default', 'airy' ],
		shaping  : [ 'sharp', 'default', 'round' ],
	},
};

const sandbox = {
	console : console,
	setTimeout : function( fn ) { fn(); return 1; },
	clearTimeout : function() {},
	document : {
		documentElement : null,
		body : null,
		getElementById : function( id ) { return nodes[id] || null; },
		createElement : function( tag ) { return element( '', tag ); },
		querySelectorAll : function() { return []; },
		addEventListener : function() {},
	},
};
sandbox.window = sandbox;
sandbox.Nino = {
	install : {
		apiCall : function( action, payload, callback ) {
			posted.push( { action : action, payload : JSON.parse( JSON.stringify( payload ) ) } );
			if( action === 'design/read' )		return callback( 200, readResponse );
			if( action === 'design/preview' )	return callback( 200, previewResponse );
			if( action === 'design/apply' )		return callback( 200, { settings : payload.design } );
		},
		showError : function() {},
	},
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_install/assets/design.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'design.js' }
);

const design = sandbox.Nino.install.design;

design.init();

// The whole point of the step order: it opens on what the theme just installed
check( 'the step opens on the settings the picked theme installed', design._settings.primary === '#4faae8'
	&& design._settings.volume === 'generous'
	&& design._settings.shaping === 'round' );
check( 'every knob the server names gets its control filled', nodes['themes-design-contrast'].children.length === 3
	&& nodes['themes-design-volume'].children.length === 3
	&& nodes['themes-design-shaping'].children.length === 3 );
check( '...at the value that is actually in force', nodes['themes-design-volume'].value === 'generous'
	&& nodes['themes-design-spacing'].value === 'airy'
	&& nodes['themes-design-contrast'].value === 'default' );
check( 'an unset Secondary shows the colour it falls back to rather than black', nodes['themes-design-secondary-hex'].value === ''
	&& nodes['themes-design-secondary'].value === '#4faae8' );
check( 'the controls are shown and the "no engine" note is not', nodes['design-controls'].classList.contains('install-hidden') === false
	&& nodes['design-unavailable'].classList.contains('install-hidden') === true );

// Both specimens are painted from one preview response
check( 'each surface chip is a real pair, not a background on its own', nodes['themes-design-preview'].children.length === 2
	&& nodes['themes-design-preview'].children[1].style.backgroundColor === '#147bb7'
	&& nodes['themes-design-preview'].children[1].style.color === '#fcfcfc' );

const specimen = nodes['themes-design-sizes'].children;
check( 'the size specimen is drawn at the generated sizes, not listed as numbers', specimen.length === 3
	&& specimen[0].children[0].style.fontSize === '2.5rem'
	&& specimen[0].children[2].style.fontSize === '1rem'
	&& specimen[0].style.lineHeight === '1.5' );
check( 'spacing is drawn as one bar per step, width carrying the value', specimen[1].children.length === 6
	&& specimen[1].children[0].children[0].style.width === '0.5rem'
	&& specimen[1].children[5].children[0].style.width === '8rem' );
check( '...each labelled with the value it stands for', specimen[1].children[3].children[1].textContent === '3rem' );
check( 'radii stay boxes - a corner only reads against the edges it joins', specimen[2].children.length === 3
	&& specimen[2].children[2].style.borderRadius === '0.4rem' );

// A knob change re-asks rather than recomputing here
posted.length = 0;
nodes['themes-design-volume'].value = 'compact';
nodes['themes-design-volume'].fire('change');

check( 'changing a size knob asks the server what it produces', design._settings.volume === 'compact'
	&& posted.filter( function( p ) { return p.action === 'design/preview' } ).length === 1
	&& posted[posted.length - 1].payload.design.volume === 'compact' );

// The hex field holds half-typed values on the way to a full colour
nodes['themes-design-primary-hex'].value = '#c81';
nodes['themes-design-primary-hex'].fire('input');
check( 'a half-typed hex is not reported as a colour', design._settings.primary === '#4faae8' );

nodes['themes-design-primary-hex'].value = '#C81E2D';
nodes['themes-design-primary-hex'].fire('input');
check( 'a complete hex is adopted, normalized, and mirrored into the picker', design._settings.primary === '#c81e2d'
	&& nodes['themes-design-primary'].value === '#C81E2D' );

nodes['themes-design-secondary-hex'].value = '';
nodes['themes-design-secondary-hex'].fire('input');
check( 'clearing Secondary is a real value - it then follows Primary', design._settings.secondary === '' );

// Revisiting re-reads: the operator may have gone back and picked another theme
readResponse = { settings : Object.assign( {}, readResponse.settings, { primary : '#8b5cf6', volume : 'compact' } ), choices : readResponse.choices };
design.showCurrent();
check( 'coming back re-reads, so a changed theme is not shown stale', design._settings.primary === '#8b5cf6'
	&& nodes['themes-design-primary-hex'].value === '#8b5cf6'
	&& nodes['themes-design-volume'].value === 'compact' );

// Binding once: a revisit must not stack a second listener on every control
posted.length = 0;
nodes['themes-design-shaping'].value = 'sharp';
nodes['themes-design-shaping'].fire('change');
check( 'a revisit does not stack a second listener on each control', posted.filter( function( p ) { return p.action === 'design/preview' } ).length === 1 );

posted.length = 0;
let applied = null;
design.apply( function( ok ) { applied = ok; } );

const applyPost = posted.filter( function( post ) { return post.action === 'design/apply' } )[0];

check( 'Next writes the settings, colour and size together', applied === true
	&& applyPost !== undefined
	&& applyPost.payload.design.primary === '#8b5cf6'
	&& applyPost.payload.design.shaping === 'sharp'
	&& applyPost.payload.design.volume === 'compact' );

// A delivery without /_theme has to step aside, not block the wizard
nodes['design-controls'].classList.remove('install-hidden');
readResponse = { settings : null, choices : {} };
design.showCurrent();

check( 'with no design engine the controls go and the note appears', nodes['design-controls'].classList.contains('install-hidden') === true
	&& nodes['design-unavailable'].classList.contains('install-hidden') === false );

posted.length = 0;
let advanced = null;
design.apply( function( ok ) { advanced = ok; } );

check( '...and Next advances without posting anything', advanced === true
	&& posted.filter( function( post ) { return post.action === 'design/apply' } ).length === 0 );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
