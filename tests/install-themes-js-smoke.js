/**
 *	Nino									A compact filesystembased php framework
 *	install-themes-js-smoke.js		DOM checks for the install wizard's Themes
 *									step: the frame selects and what they put into the
 *									themes/apply post. The Design step is its own file.
 *
 *	Usage: node tests/install-themes-js-smoke.js
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

/*	A stand-in for just the parts of the DOM this step touches: elements are
 *	looked up by id, options are appended to selects, and classList carries the
 *	install-hidden state the two panels toggle	*/
function element( id, tag ) {

	const el = {
		id : id,
		tagName : ( tag || 'div' ).toUpperCase(),
		value : '',
		textContent : '',
		innerHTML : '',
		srcdoc : null,
		dataset : {},
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
		removeAttribute : function( name ) { if( name === 'srcdoc' ) el.srcdoc = null; },
		addEventListener : function( type, handler ) { ( el._listeners[type] = el._listeners[type] || [] ).push( handler ); },
		fire : function( type ) { ( el._listeners[type] || [] ).forEach( function( handler ) { handler( { preventDefault : function() {} } ); } ); },
		contains : function() { return false; },
	};

	Object.defineProperty( el, 'innerHTML', {
		get : function() { return ''; },
		set : function() { el.children.length = 0; },
	} );

	return el;
}

const nodes = {};
[
	'themes-grid', 'themes-msg', 'themes-frames',
	'themes-frame-header', 'themes-frame-footer',
	'themes-frame-header-preview', 'themes-frame-footer-preview',
].forEach( function( id ) { nodes[id] = element( id ); } );

const posted = [];

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
			if( action === 'themes/frame' )
				callback( 200, { frame : payload.frame, html : '<!doctype html><body data-frame="'+ payload.kind+ '/'+ payload.frame+ '">' } );
		},
		showError : function() {},
	},
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_install/assets/themes.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'themes.js' }
);

const themes = sandbox.Nino.install.themes;

themes._themes = {
	agency 	: { label : 'Agency', header : 'v1', footer : 'v1', design : { primary : '#4faae8', secondary : '', contrast : 'default', colors : 'default' } },
	nighty 	: { label : 'Nighty', header : 'v4', footer : 'v2', design : { primary : '#8b5cf6', secondary : '#22d3ee', contrast : 'high', colors : 'vibrant' } },
};
themes._frames 	= { header : [ 'v1', 'v2', 'v3', 'v4' ], footer : [ 'v1', 'v2' ] };
// A theme is always picked by the time the frames render - the grid
// pre-selects one, and the preview is rendered against it
themes._selected = 'agency';
themes._renderFrames( {} );

check( 'the frame selects offer every unit on disk', nodes['themes-frame-header'].children.length === 4
	&& nodes['themes-frame-footer'].children.length === 2 );
check( 'a fresh install pre-selects the frames the picked theme was drawn against', nodes['themes-frame-header'].value === 'v1'
	&& nodes['themes-frame-footer'].value === 'v1' );
check( 'the frames panel is shown once there is something to pick', nodes['themes-frames'].classList.contains('install-hidden') === false );

// A revisit has to show what is installed, not what the theme would suggest
themes._renderFrames( { header : 'v3', footer : 'v2' } );
check( 'an already-installed frame wins over the theme\'s declaration on a revisit', nodes['themes-frame-header'].value === 'v3'
	&& nodes['themes-frame-footer'].value === 'v2' );

// A version number says nothing about what a frame looks like, and unlike a
// theme there is nothing to open in a lightbox - so each select carries the
// rendered thing beneath it
check( 'each select renders its frame as soon as the step opens', nodes['themes-frame-header-preview'].srcdoc === '<!doctype html><body data-frame="header/v3">'
	&& nodes['themes-frame-footer-preview'].srcdoc === '<!doctype html><body data-frame="footer/v2">' );

posted.length = 0;
nodes['themes-frame-header'].value = 'v2';
nodes['themes-frame-header'].fire('change');

check( 'changing a select re-renders that preview, and only that one', nodes['themes-frame-header-preview'].srcdoc === '<!doctype html><body data-frame="header/v2">'
	&& nodes['themes-frame-footer-preview'].srcdoc === '<!doctype html><body data-frame="footer/v2">'
	&& posted.filter( function( p ) { return p.action === 'themes/frame' } ).length === 1 );

// The document is built server-side: it needs the library's templates, its
// text files and the theme's stylesheet, none of which the browser has
check( 'the preview is asked for, not assembled here', posted[0].payload.kind === 'header'
	&& posted[0].payload.frame === 'v2'
	&& posted[0].payload.theme === 'agency' );

// _renderFrames() runs again on every visit to the step, and a second
// listener would make every later change fire twice
themes._renderFrames( { header : 'v2', footer : 'v2' } );
posted.length = 0;
nodes['themes-frame-header'].fire('change');
check( 'a revisit does not stack a second listener on each select', posted.filter( function( p ) { return p.action === 'themes/frame' } ).length === 1 );

// Picking a theme has to move the frames its preview was rendered with, or
// the tile is promising a look the install will not produce. _adoptTheme() is
// the second half of the radio's change handler - the first half sets
// _selected, so the test has to do both to be the real path
themes._selected = 'nighty';
themes._adoptTheme( 'nighty' );

check( 'picking a theme moves the frame selects to the ones it names', nodes['themes-frame-header'].value === 'v4'
	&& nodes['themes-frame-footer'].value === 'v2' );
// Setting .value does not fire 'change', so the preview would keep showing
// the frame the previous theme named
check( '...and the preview moves with them', nodes['themes-frame-header-preview'].srcdoc === '<!doctype html><body data-frame="header/v4">' );

// One post, because the two picks are one decision
posted.length = 0;
themes._ready = true;
nodes['themes-frame-header'].value = 'v2';
nodes['themes-frame-footer'].value = 'v1';

let applied = null;
themes.apply( function( success ) { applied = success; } );

const applyPost = posted.filter( function( post ) { return post.action === 'themes/apply' } )[0];

check( 'theme and both frames go out in one apply', applyPost !== undefined
	&& applyPost.payload.theme === 'nighty'
	&& applyPost.payload.header === 'v2'
	&& applyPost.payload.footer === 'v1' );
check( 'the colours are not this step\'s to send - the Design step writes those', Object.prototype.hasOwnProperty.call( applyPost.payload, 'design' ) === false );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
