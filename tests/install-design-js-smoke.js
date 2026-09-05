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
		_attrs : {},
		hidden : false,
		// scaleFrame() measures the port and writes the frame's layout size
		_box : { width : 800, height : 600 },
		getBoundingClientRect : function() { return { width : el._box.width, height : el._box.height } },
		appendChild : function( child ) { el.children.push( child ); return child; },
		setAttribute : function( name, value ) { el._attrs[name] = String( value ); },
		getAttribute : function( name ) { return el._attrs[name] !== undefined ? el._attrs[name] : null; },
		addEventListener : function( type, handler ) { ( el._listeners[type] = el._listeners[type] || [] ).push( handler ); },
		fire : function( type ) { ( el._listeners[type] || [] ).forEach( function( handler ) { handler( {} ); } ); },
	};

	// A <label> reaches its own control - which is how a field built by
	// Nino.adminUi.selectField() is written back into without an id
	Object.defineProperty( el, 'control', {
		get : function() {
			return el.children.filter( function( child ) {
				return [ 'SELECT', 'INPUT', 'TEXTAREA' ].indexOf( child.tagName ) !== -1;
			} )[0] || null;
		},
	} );

	Object.defineProperty( el, 'innerHTML', {
		get : function() { return ''; },
		set : function() { el.children.length = 0; },
	} );

	return el;
}

const nodes = {};
[
	'design-controls', 'design-unavailable', 'design-msg', 'design-reset',
	'themes-design-primary', 'themes-design-primary-hex',
	'themes-design-secondary', 'themes-design-secondary-hex',
	'themes-design-primary-field', 'themes-design-secondary-field',
	'themes-design-knobs-colour', 'themes-design-knobs-raster',
	'themes-design-example', 'themes-design-example-port', 'themes-design-mode-light', 'themes-design-mode-dark',
	'themes-design-tab-colour', 'themes-design-tab-raster',
	'themes-design-panel-colour', 'themes-design-panel-raster',
	// The frame steps' own selects - what the design step re-reads on
	'themes-frame-header', 'themes-frame-footer',
].forEach( function( id ) { nodes[id] = element( id ); } );

// The two colour rows are markup rather than rendered, and their name is where
// the theme's own value gets written
[ 'themes-design-primary-field', 'themes-design-secondary-field' ].forEach( function( id ) {
	nodes[id].appendChild( element( '', 'span' ) );
} );

const posted = [];

// What /_design really returns for these settings, so the specimen is checked
// against the shape it actually gets rather than one invented here
const previewResponse = {
	example : '<!doctype html><html data-nino-mode="light"><body>preview</body></html>',
	raster : {
		text 		: { 1 : '1rem', 2 : '1.125rem', 3 : '1.25rem', 4 : '1.5rem', 5 : '1.8rem', 6 : '2.5rem' },
		space 	: { 1 : '0.5rem', 2 : '1rem', 3 : '2rem', 4 : '3rem', 5 : '5rem', 6 : '8rem' },
		radius 	: { 1 : '0.1rem', 2 : '0.2rem', 3 : '0.4rem' },
		lineHeight : '1.5',
	},
};

const knob = function( group, label, dflt ) {
	return { group : group, label : label, note : label+ ' note', 'default' : dflt, min : 1, max : 3,
	         hint : label+ ' hint', steps : [ 'less', 'as it is', 'more' ] };
};

let readResponse = {
	settings : { primary : '#4faae8', secondary : '', harmony : 1, temperature : 2, saturation : 2, contrast : 2, depth : 2, scale : 2, volume : 3, spacing : 3, shaping : 3, measure : 2 },
	groups   : { colour : 'Colour', raster : 'Raster' },
	example  : '<!doctype html><html data-nino-mode="light"><body>stored</body></html>',
	choices  : {
		harmony     : knob( 'colour', 'Harmony', 1 ),
		temperature : knob( 'colour', 'Temperature', 2 ),
		saturation  : knob( 'colour', 'Saturation', 2 ),
		contrast    : knob( 'colour', 'Contrast', 2 ),
		depth       : knob( 'colour', 'Depth', 2 ),
		scale       : knob( 'raster', 'Size', 2 ),
		volume      : knob( 'raster', 'Headings', 2 ),
		spacing     : knob( 'raster', 'Spacing', 2 ),
		shaping     : knob( 'raster', 'Corners', 2 ),
		measure     : knob( 'raster', 'Width', 2 ),
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
		apiCall : function( action, payload, callback, fields ) {
			// mode rides beside the payload rather than inside it, so the stub
			// has to record it separately or the distinction is untested
			posted.push( { action : action, mode : ( fields || {} ).mode, payload : JSON.parse( JSON.stringify( payload ) ) } );
			if( action === 'design/read' )		return callback( 200, readResponse );
			if( action === 'design/preview' )	return callback( 200, previewResponse );
			if( action === 'design/apply' )		return callback( 200, { settings : payload.design } );
		},
		showError : function() {},
		// The step before this one. Which theme it settled on is what decides
		// whether this step owes itself a re-read
		themes : { _selected : 'basis' },
	},
	events : { bindCallback : function() {} },
};

// The knobs are drawn by the design system's own control, so the test runs the
// real Nino.admin.js rather than stubbing it - and this step and /_design have
// to end up with the same control from the same source
const context = vm.createContext( sandbox );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ), context, { filename : 'Nino.admin.js' } );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/install/assets/design.js' ), 'utf8' ), context, { filename : 'design.js' } );

const design = sandbox.Nino.install.design;

design.init();

// The whole point of the step order: it opens on what the theme just installed
const knobSelect = function( panel, index ) { return nodes[panel].children[index].children[1]; };

check( 'the step opens on the settings the picked theme installed', design._settings.primary === '#4faae8'
	&& design._settings.volume === 3
	&& design._settings.shaping === 3 );
// The wizard markup lists no knob by name, so a knob added server-side reaches
// this step and /_design together rather than one of them silently missing it
check( 'every knob the server names gets a select in the panel it belongs to', nodes['themes-design-knobs-colour'].children.length === 5
	&& nodes['themes-design-knobs-raster'].children.length === 5 );
check( '...at the value that is actually in force', knobSelect( 'themes-design-knobs-raster', 1 ).value === '3'
	&& knobSelect( 'themes-design-knobs-raster', 3 ).value === '3'
	&& knobSelect( 'themes-design-knobs-colour', 3 ).value === '2' );
check( '...with one option per position, named rather than numbered', knobSelect( 'themes-design-knobs-raster', 1 ).children.length === 3
	&& knobSelect( 'themes-design-knobs-raster', 1 ).children[2].textContent === 'more'
	&& knobSelect( 'themes-design-knobs-raster', 1 ).children[2].value === '3' );
check( 'an unset Secondary shows the colour it falls back to rather than black', nodes['themes-design-secondary-hex'].value === ''
	&& nodes['themes-design-secondary'].value === '#4faae8' );
check( 'the controls are shown and the "no engine" note is not', nodes['design-controls'].classList.contains('install-hidden') === false
	&& nodes['design-unavailable'].classList.contains('install-hidden') === true );

// One page rather than a strip of chips and three size specimens - and it is
// /_design that builds it, so this step and /_design cannot drift apart
// design/read already returned the document for the settings it returned, so
// the step opens on it rather than asking a second time for the same answer
check( 'the example is delivered whole, as a document into the sandboxed frame', nodes['themes-design-example'].srcdoc === readResponse.example );

/*	A preview panel is around 800px wide and Nino's narrowest content ceiling
	is wider than that, so every Width setting used to render identically - the
	frame was narrower than the difference the operator was choosing between.
	The page is given a desktop's layout width and scaled into the panel
	instead, which keeps every proportion inside it exact.	*/
check( 'the page renders at a desktop layout width, not at the panel\'s', nodes['themes-design-example'].style.width === String( design.PREVIEW_WIDTH )+ 'px' );
/*	PREVIEW_HEIGHT picks how it fits: with one, both dimensions are fixed and the
	whole page is on screen; without one the page is given the panel's height
	solved back through the scale and the rest scrolls. This step ships without,
	the example being a whole site rather than a screen of one - and whichever it
	is, it has to be the same in both pickers or the same Design previews at two
	different layouts. The stub's port is 800x600.	*/
check( '...and at the same one /_design uses, or Width means two things', design.PREVIEW_WIDTH === 2400
	&& design.PREVIEW_HEIGHT === 0 );
check( '...given the panel\'s height, solved back through the scale', nodes['themes-design-example'].style.transform === 'scale('+ ( 800 / design.PREVIEW_WIDTH )+ ')'
	&& nodes['themes-design-example'].style.height === String( Math.round( 600 / ( 800 / design.PREVIEW_WIDTH ) ) )+ 'px' );

// Two tabs rather than one long column, the same pair /_design has
check( 'the step opens on Colour, with Raster present but hidden', nodes['themes-design-panel-colour'].hidden === false
	&& nodes['themes-design-panel-raster'].hidden === true );

posted.length = 0;
nodes['themes-design-tab-raster'].fire('click');
check( 'switching tabs swaps which settings are on screen', nodes['themes-design-panel-raster'].hidden === false
	&& nodes['themes-design-panel-colour'].hidden === true
	&& nodes['themes-design-tab-raster'].getAttribute('aria-selected') === 'true'
	&& nodes['themes-design-tab-colour'].getAttribute('aria-selected') === 'false' );
check( '...without asking the server for anything', posted.length === 0 );
check( 'the settings behind the other tab are still there', nodes['themes-design-knobs-colour'].children.length === 5
	&& nodes['themes-design-knobs-raster'].children.length === 5 );

// Which mode is previewed is a server question: the frame is sandboxed, so
// nothing out here can reach in and stamp the attribute on it
posted.length = 0;
nodes['themes-design-mode-dark'].fire('click');
check( 'the mode buttons re-ask rather than trying to reach into the frame', posted.length === 1
	&& posted[0].action === 'design/preview'
	&& posted[0].mode === 'dark'
	&& nodes['themes-design-mode-dark'].classList.contains('is-active') === true );
check( '...and the mode never becomes a design setting', typeof posted[0].payload.design.mode === 'undefined' );

// A knob change re-asks rather than recomputing here
posted.length = 0;
const volumeKnob = knobSelect( 'themes-design-knobs-raster', 1 );
volumeKnob.value = '2';
volumeKnob.fire('change');

check( 'changing a size knob asks the server what it produces', design._settings.volume === 2
	&& posted.filter( function( p ) { return p.action === 'design/preview' } ).length === 1
	&& posted[posted.length - 1].payload.design.volume === 2 );

// The hex field holds half-typed values on the way to a full colour
nodes['themes-design-primary-hex'].value = '#c81';
nodes['themes-design-primary-hex'].fire('input');
check( 'a half-typed hex is not reported as a colour', design._settings.primary === '#4faae8' );
/*	It used to be dropped in silence, which reads as "accepted" - and the field
	was left showing a value the design does not have. Said in the field, in
	aria-invalid and in words, because a red border on its own is a colour
	telling a colour-blind operator that their colour is wrong	*/
check( '...and is refused out loud rather than in silence', nodes['themes-design-primary-hex'].getAttribute('aria-invalid') === 'true'
	&& nodes['design-msg'].textContent.indexOf('#rrggbb') !== -1 );

nodes['themes-design-primary-hex'].fire('blur');
check( '...and leaving the field puts back the value the design does have', nodes['themes-design-primary-hex'].value === '#4faae8'
	&& nodes['themes-design-primary-hex'].getAttribute('aria-invalid') === 'false'
	&& nodes['design-msg'].classList.contains('is-error') === false );

nodes['themes-design-primary-hex'].value = '#C81E2D';
nodes['themes-design-primary-hex'].fire('input');
check( 'a complete hex is adopted, normalized, and mirrored into the picker', design._settings.primary === '#c81e2d'
	&& nodes['themes-design-primary'].value === '#C81E2D' );

nodes['themes-design-secondary-hex'].value = '';
nodes['themes-design-secondary-hex'].fire('input');
check( 'clearing Secondary is a real value - it then follows Primary', design._settings.secondary === '' );

/*	Nothing is written until Next, so what a setting is measured against here is
	what the theme handed over - and naming that value is what makes the mark a
	comparison rather than a warning light	*/
const fieldMark = function( key ) {
	const field = design._fields[key];
	return field !== undefined && field.changeMark !== undefined && field.changeMark.hidden === false
		&& field.classList.contains('is-changed') === true;
};

check( 'a setting moved off the theme is marked, and one left alone is not', fieldMark('primary') === true
	&& fieldMark('volume') === true
	&& fieldMark('shaping') === false
	&& nodes['design-reset'].hidden === false );

/*	The step used to re-read on every visit. That kept a changed theme from
	being shown stale - and it also meant a trip back to correct something two
	steps earlier threw away every setting made here, without a word	*/
posted.length = 0;
design.showCurrent();
check( 'coming back to the same theme keeps what was set rather than re-reading over it',
	posted.filter( function( p ) { return p.action === 'design/read' } ).length === 0
	&& design._settings.primary === '#c81e2d'
	&& design._settings.volume === 2
	&& nodes['themes-design-primary-hex'].value === '#C81E2D' );

// The other half of a comparison: a way back that is not "remember where the
// theme had ten settings"
posted.length = 0;
nodes['design-reset'].fire('click');
check( 'Reset puts every control back where the theme had it', design._settings.primary === '#4faae8'
	&& design._settings.volume === 3
	&& nodes['themes-design-primary-hex'].value === '#4faae8'
	&& knobSelect( 'themes-design-knobs-raster', 1 ).value === '3'
	&& fieldMark('primary') === false
	&& nodes['design-reset'].hidden === true );
check( '...and asks for the picture that produces', posted.filter( function( p ) { return p.action === 'design/preview' } ).length === 1 );

// A different theme is a different starting point, so that one does re-read
sandbox.Nino.install.themes._selected = 'nocturne';
readResponse = { settings : Object.assign( {}, readResponse.settings, { primary : '#8b5cf6', volume : 2 } ), groups : readResponse.groups, choices : readResponse.choices, example : readResponse.example };
design.showCurrent();
check( 'picking another theme two steps back is followed here', design._settings.primary === '#8b5cf6'
	&& nodes['themes-design-primary-hex'].value === '#8b5cf6'
	&& knobSelect( 'themes-design-knobs-raster', 1 ).value === '2' );
// A reload the operator did not ask for is one they get told about: it is
// their own values that were replaced
check( '...and said out loud, because it replaced values that were set here', nodes['design-msg'].textContent.indexOf('changed') !== -1 );
check( '...by rebuilding the selects rather than re-filling them, so a revisit cannot stack listeners on one',
	nodes['themes-design-knobs-raster'].children.length === 5 );

/*	The frames are part of the starting point too: the Header and Footer steps
	run before this one, and the example is a whole page drawn inside what they
	settled. A Design judged inside the bar the operator just replaced is a
	Design judged in a layout that no longer exists.	*/
posted.length = 0;
nodes['themes-frame-header'].value = 'v5';
design.showCurrent();
check( 'changing a frame one step back is followed here as well',
	posted.filter( function( p ) { return p.action === 'design/read' } ).length === 1 );

posted.length = 0;
design.showCurrent();
check( '...and once it has been, coming back again reads nothing',
	posted.filter( function( p ) { return p.action === 'design/read' } ).length === 0 );

// The colour inputs are markup rather than rebuilt controls, so they are the
// ones a revisit could bind twice
posted.length = 0;
nodes['themes-design-primary-hex'].value = '#123456';
nodes['themes-design-primary-hex'].fire('input');
check( 'a revisit does not stack a second listener on the colour inputs', posted.filter( function( p ) { return p.action === 'design/preview' } ).length === 1 );

posted.length = 0;
let applied = null;
design.apply( function( ok ) { applied = ok; } );

const applyPost = posted.filter( function( post ) { return post.action === 'design/apply' } )[0];

check( 'Next writes the settings, colour and size together', applied === true
	&& applyPost !== undefined
	&& applyPost.payload.design.primary === '#123456'
	&& applyPost.payload.design.shaping === 3
	&& applyPost.payload.design.volume === 2 );

// A delivery without /_design has to step aside, not block the wizard
nodes['design-controls'].classList.remove('install-hidden');
sandbox.Nino.install.themes._selected = 'basis';
readResponse = { settings : null, choices : {}, groups : {} };
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
