/**
 *	Nino									A compact filesystembased php framework
 *	install-themes-js-smoke.js		DOM checks for the install wizard's Theme,
 *									Header and Footer steps: separate panes, previews and
 *									independent apply posts. Design has its own test file.
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
 *	install-hidden state the frame panels toggle	*/
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
	'themes-grid', 'themes-msg', 'header-msg', 'footer-msg',
	'themes-frame-header-panel', 'themes-frame-footer-panel',
	'themes-frame-header-unavailable', 'themes-frame-footer-unavailable',
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
			else if( action === 'themes/apply' )
				callback( 200, { theme : payload.theme } );
			else if( action === 'themes/frame/apply' )
				callback( 200, { kind : payload.kind, frame : payload.frame } );
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

// At the Theme step Design has not loaded yet. Its settings become available
// before Header opens and that transition has to refresh the hidden preview.
sandbox.Nino.install.design = { _settings : null };

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
check( 'Header and Footer each expose their own panel', nodes['themes-frame-header-panel'].classList.contains('install-hidden') === false
	&& nodes['themes-frame-footer-panel'].classList.contains('install-hidden') === false );
check( 'the unavailable notes stay hidden while variants exist', nodes['themes-frame-header-unavailable'].classList.contains('install-hidden') === true
	&& nodes['themes-frame-footer-unavailable'].classList.contains('install-hidden') === true );

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
sandbox.Nino.install.themes._ready = true;
sandbox.Nino.install.design._settings = { primary : '#c81e2d', spacing : 'tight' };
sandbox.Nino.install.header.showCurrent();

check( 'opening Header refreshes its preview with the Design just chosen', posted.length === 1
	&& posted[0].action === 'themes/frame'
	&& posted[0].payload.design.primary === '#c81e2d'
	&& posted[0].payload.design.spacing === 'tight' );

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
	&& posted[0].payload.theme === 'agency'
	&& posted[0].payload.design.primary === '#c81e2d'
	&& posted[0].payload.design.spacing === 'tight' );

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

// Themes no longer owns the frame controls: each following tab persists its
// own choice without recopying the theme or resetting Design.
posted.length = 0;
themes._ready = true;
nodes['themes-frame-header'].value = 'v2';
nodes['themes-frame-footer'].value = 'v1';

let applied = null;
themes.apply( function( success ) { applied = success; } );

const applyPost = posted.filter( function( post ) { return post.action === 'themes/apply' } )[0];

check( 'the Theme tab posts only its own choice', applied === true
	&& applyPost !== undefined
	&& Object.keys( applyPost.payload ).length === 1
	&& applyPost.payload.theme === 'nighty' );

let headerApplied = null;
let footerApplied = null;
sandbox.Nino.install.header.apply( function( success ) { headerApplied = success; } );
sandbox.Nino.install.footer.apply( function( success ) { footerApplied = success; } );

const frameApplyPosts = posted.filter( function( post ) { return post.action === 'themes/frame/apply' } );
check( 'Header and Footer are registered as independent wizard modules', typeof sandbox.Nino.install.header.showCurrent === 'function'
	&& typeof sandbox.Nino.install.footer.showCurrent === 'function' );
check( 'each frame tab posts only its own kind and selected variant', headerApplied === true && footerApplied === true
	&& frameApplyPosts.length === 2
	&& JSON.stringify( frameApplyPosts[0].payload ) === JSON.stringify( { kind : 'header', frame : 'v2' } )
	&& JSON.stringify( frameApplyPosts[1].payload ) === JSON.stringify( { kind : 'footer', frame : 'v1' } ) );

// A stripped delivery may omit one frame kind. The pane explains that and
// remains skippable instead of blocking the linear wizard.
themes._frames.footer = [];
themes._renderFrames( {} );
posted.length = 0;
footerApplied = null;
sandbox.Nino.install.footer.apply( function( success ) { footerApplied = success; } );

check( 'a missing frame kind hides its panel and shows the explanatory note', nodes['themes-frame-footer-panel'].classList.contains('install-hidden') === true
	&& nodes['themes-frame-footer-unavailable'].classList.contains('install-hidden') === false );
check( 'a missing frame kind is a valid no-op on Next', footerApplied === true
	&& posted.filter( function( post ) { return post.action === 'themes/frame/apply' } ).length === 0 );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
