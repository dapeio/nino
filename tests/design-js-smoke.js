/**
 *	Nino				A compact filesystembased php framework
 *	design-js-smoke.js	DOM contracts for the Design panel's four independent editors.
 *
 *	Usage: node tests/design-js-smoke.js
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

function element( id, tag ) {

	const el = {
		id : id,
		tagName : ( tag || 'div' ).toUpperCase(),
		className : '',
		value : '',
		textContent : '',
		title : '',
		disabled : false,
		checked : false,
		dataset : {},
		style : {},
		children : [],
		_listeners : {},
		classList : {
			_set : new Set(),
			add : function( name ) { el.classList._set.add( name ); },
			remove : function( name ) { el.classList._set.delete( name ); },
			contains : function( name ) { return el.classList._set.has( name ); },
			toggle : function( name, on ) {
				if( on === true ) el.classList.add( name );
				else el.classList.remove( name );
			},
		},
		_attrs : {},
		hidden : false,
		// scaleFrame() measures the port and writes the frame's layout size
		_box : { width : 800, height : 600 },
		getBoundingClientRect : function() { return { width : el._box.width, height : el._box.height } },
		appendChild : function( child ) { el.children.push( child ); return child; },
		setAttribute : function( name, value ) { el._attrs[name] = String( value ); },
		getAttribute : function( name ) { return el._attrs[name] !== undefined ? el._attrs[name] : null; },
		removeAttribute : function( name ) { if( name === 'srcdoc' ) el.srcdoc = null; delete el._attrs[name]; },
		addEventListener : function( type, handler ) { ( el._listeners[type] = el._listeners[type] || [] ).push( handler ); },
		fire : function( type ) {
			const event = { preventDefault : function() { event.prevented = true; }, prevented : false };
			( el._listeners[type] || [] ).forEach( function( handler ) {
				handler( event );
			} );
			return event;
		},
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
	'theme-page-wrap', 'theme-pane', 'theme-grid', 'theme-empty',
	'theme-action-status', 'theme-action-save', 'theme-design-reset',
	'theme-nav-theme', 'theme-nav-design', 'theme-nav-header', 'theme-nav-footer',
	'theme-design-controls', 'theme-design-example', 'theme-design-example-port',
	'theme-design-mode-light', 'theme-design-mode-dark',
	'theme-design-tab-colour', 'theme-design-tab-raster',
	'theme-design-panel-colour', 'theme-design-panel-raster',
	'theme-design-primary', 'theme-design-primary-hex', 'theme-design-secondary', 'theme-design-secondary-hex',
	'theme-design-primary-field', 'theme-design-secondary-field',
	'theme-design-knobs-colour', 'theme-design-knobs-raster',
	'theme-frame-header-panel', 'theme-frame-footer-panel', 'theme-frame-header-empty', 'theme-frame-footer-empty',
	'theme-frame-header', 'theme-frame-footer', 'theme-frame-header-preview', 'theme-frame-footer-preview',
].forEach( function( id ) { nodes[id] = element( id ); } );

nodes['theme-page-wrap'].classList.add('show-theme');

// The two colour rows are markup rather than rendered, and their name is
// where the saved value gets written
[ 'theme-design-primary-field', 'theme-design-secondary-field' ].forEach( function( id ) {
	nodes[id].appendChild( element( '', 'span' ) );
} );

let appearance = {
	themes : {
		basis : { label : 'Basis', description : 'Neutral starting point', preview : '/basis.svg', header : 'v1', footer : 'v1' },
		nocturne : { label : 'Nocturne', description : '<b>server text stays text</b>', preview : '/nocturne.svg', header : 'v5', footer : 'v2' },
	},
	activeTheme : 'basis',
	frames : { header : [ 'v1', 'v2', 'v5' ], footer : [ 'v1', 'v2' ] },
	activeFrames : { header : 'v1', footer : 'v1' },
};

const knob = function( group, label, dflt ) {
	return { group : group, label : label, note : label+ ' note', 'default' : dflt, min : 1, max : 3,
	         hint : label+ ' hint', steps : [ 'less', 'as it is', 'more' ] };
};

const designRead = {
	settings : { primary : '#b6a6ff', secondary : '', harmony : 1, temperature : 2, saturation : 3, contrast : 3, depth : 2, scale : 2, volume : 2, spacing : 2, shaping : 3 },
	groups : { colour : 'Colour', raster : 'Raster' },
	example : '<!doctype html><html data-nino-mode="light"><body>preview</body></html>',
	choices : {
		harmony : knob( 'colour', 'Harmony', 1 ),
		temperature : knob( 'colour', 'Temperature', 2 ),
		saturation : knob( 'colour', 'Saturation', 2 ),
		contrast : knob( 'colour', 'Contrast', 2 ),
		depth : knob( 'colour', 'Depth', 2 ),
		scale : knob( 'raster', 'Size', 2 ),
		volume : knob( 'raster', 'Headings', 2 ),
		spacing : knob( 'raster', 'Spacing', 2 ),
		shaping : knob( 'raster', 'Corners', 2 ),
		measure : knob( 'raster', 'Width', 2 ),
	},
	raster : {
		text : { 1 : '1rem', 4 : '1.5rem', 6 : '2.5rem' },
		space : { 1 : '0.5rem', 2 : '1rem', 3 : '2rem' },
		radius : { 1 : '0.1rem', 2 : '0.3rem', 3 : '0.7rem' },
		lineHeight : '1.5',
	},
};

const posts = [];

const sandbox = {
	console : console,
	location : { search : '' },
	URLSearchParams : URLSearchParams,
	setTimeout : function( callback ) { callback(); return 1; },
	clearTimeout : function() {},
	document : {
		getElementById : function( id ) { return nodes[id] || null; },
		createElement : function( tag ) { return element( '', tag ); },
	},
};
sandbox.window = sandbox;

// What the operator answers when the tool asks before discarding something
let confirmAnswer = true;
const confirmed = [];
sandbox.confirm = function( question ) { confirmed.push( question ); return confirmAnswer; };

const unload = [];
sandbox.addEventListener = function( type, handler ) {
	if( type === 'beforeunload' )
		unload.push( handler );
};
sandbox.Nino = {
	http : {
		sendRequest : function( uri, method, callback, data ) {
			const payload = JSON.parse( data.data || '{}' );
			// mode rides beside the payload rather than inside it, so the
			// stub has to record it separately or the distinction is untested
			posts.push( { uri : uri, method : method, action : data.action, mode : data.mode, payload : JSON.parse( JSON.stringify( payload ) ) } );

			let response = {};
			if( data.action === 'appearance/read' ) response = appearance;
			if( data.action === 'theme/apply' ) {
				appearance = Object.assign( {}, appearance, { activeTheme : payload.theme, activeFrames : { header : 'v5', footer : 'v2' } } );
				response = { theme : payload.theme, header : 'v5', footer : 'v2' };
			}
			if( data.action === 'design/read' ) response = designRead;
			if( data.action === 'design/preview' ) response = designRead;
			if( data.action === 'design/save' ) response = { settings : payload };
			if( data.action === 'frame/preview' ) response = { frame : payload.frame, html : '<!doctype html><body data-frame="'+ payload.kind+ '/'+ payload.frame+ '">' };
			if( data.action === 'frame/apply' ) response = { kind : payload.kind, frame : payload.frame };

			callback( { status : 200, responseJSON : response } );
		},
	},
	events : { bindCallback : function() {} },
	// The panel's words are fills (see the module's text/ and the workbench's
	// own); the test reads the English files, so the assertions below keep
	// checking real sentences rather than keys
	content : ( function() {
		const text = {};
		[ '../_admin/text/en_US.php', '../app/Nino/Modules/Design/text/en_US.php' ].forEach( function( file ) {
			const php = fs.readFileSync( path.join( __dirname, file ), 'utf8' );
			for( const m of php.matchAll( /'\[\[([^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g ) )
				text[ m[1] ] = m[2].replace( /\\(['\\])/g, '$1' );
		} );
		return { text : text, getText : function( key ) { return text[key] || ''; } };
	} )(),
	// The workbench's hash router (see _admin/assets/script.js): the panel
	// reads the editor to open from it and writes the open one back
	admin : {
		router : {
			current : function() { return { panel : 'design', parts : [] }; },
			set : function( panel, parts ) { hashes.push( [ panel ].concat( parts ).join('/') ); },
		},
	},
};
const hashes = [];

// The knobs are drawn by the design system's own control, so the test runs
// the real Nino.admin.js rather than stubbing it - a stub would pass while the
// component it stands in for is broken
const source = fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/Design/assets/design.js' ), 'utf8' );
const context = vm.createContext( sandbox );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ), context, { filename : 'Nino.admin.js' } );
vm.runInContext( source, context, { filename : 'design.js' } );

const theme = sandbox.Nino.admin.design;
theme.init();

check( 'the panel attaches to the workbench namespace and loads nothing until its tab is selected', typeof theme.showCurrent === 'function'
	&& posts.length === 0 );

theme.showCurrent();

check( 'the tool exposes exactly the four requested dialogs', JSON.stringify( theme.TABS ) === JSON.stringify( [ 'theme', 'design', 'header', 'footer' ] )
	&& nodes['theme-nav-theme']._listeners.click.length === 1
	&& nodes['theme-nav-footer']._listeners.click.length === 1 );
check( 'Theme opens first and loads the shared appearance catalogue', nodes['theme-page-wrap'].classList.contains('show-theme') === true
	&& posts[0].action === 'appearance/read'
	&& nodes['theme-grid'].children.length === 2 );
check( 'the applied Theme is also the selected Theme', theme._activeTheme === 'basis'
	&& theme._selectedTheme === 'basis'
	&& nodes['theme-grid'].children[0].classList.contains('theme-tile--active') === true
	&& nodes['theme-grid'].children[0].classList.contains('theme-tile--selected') === true );
check( 'API descriptions are inserted as text, never interpreted as markup', nodes['theme-grid'].children[1].children[2].children[1].textContent === '<b>server text stays text</b>' );

// Pick the second card through its radio and apply only that Theme choice.
const nocturneRadio = nodes['theme-grid'].children[1].children[0];
nocturneRadio.checked = true;
nocturneRadio.fire('change');
nodes['theme-action-save'].fire('click');

const themeApply = posts.filter( function( post ) { return post.action === 'theme/apply'; } )[0];
check( 'Theme apply posts only the picked Theme', themeApply !== undefined
	&& JSON.stringify( themeApply.payload ) === JSON.stringify( { theme : 'nocturne' } ) );
check( 'a successful Theme apply refreshes the complete baseline', theme._activeTheme === 'nocturne'
	&& theme._activeFrames.header === 'v5'
	&& theme._activeFrames.footer === 'v2'
	&& nodes['theme-action-status'].textContent.indexOf('recommended baseline') !== -1 );

// Design owns the generated settings and nothing else.
nodes['theme-nav-design'].fire('click');
check( 'Design has its own pane and action', nodes['theme-page-wrap'].classList.contains('show-design') === true
	&& nodes['theme-page-wrap'].classList.contains('show-theme') === false
	&& nodes['theme-action-save'].textContent === 'Save Design'
	&& posts.filter( function( post ) { return post.action === 'design/read'; } ).length === 1 );
// Every knob the server names gets a select, in the panel it named - the
// markup lists neither, so a knob added server-side cannot be missed here
const knobSelect = function( panel, index ) { return nodes[panel].children[index].children[1]; };

check( 'Design renders every server-owned choice into the panel it belongs to', nodes['theme-design-knobs-colour'].children.length === 5
	&& nodes['theme-design-knobs-raster'].children.length === 5 );
check( '...each opening on the stored position', knobSelect( 'theme-design-knobs-colour', 0 ).getAttribute('data-key') === 'harmony'
	&& knobSelect( 'theme-design-knobs-colour', 3 ).getAttribute('data-key') === 'contrast'
	&& knobSelect( 'theme-design-knobs-colour', 3 ).value === '3'
	&& knobSelect( 'theme-design-knobs-raster', 3 ).value === '3' );
// The option text is the position's name and its value is the number that
// gets stored, so neither side has to translate the other's vocabulary. The
// name is the panel's own fill where there is one - the schema in
// \Nino\Modules\Design\Tokens stays English because the setup wizard renders
// the same knobs and reaches no fills at all
check( '...with one option per position, named rather than numbered', knobSelect( 'theme-design-knobs-colour', 3 ).children.length === 3
	&& knobSelect( 'theme-design-knobs-colour', 3 ).children[2].textContent === 'Strong'
	&& knobSelect( 'theme-design-knobs-colour', 3 ).children[2].value === '3' );
// ...and the schema's own word wherever no fill names it, so a knob added to
// Tokens shows up here at once rather than as a raw key
check( '...falling back to the schema for a knob no fill names', sandbox.Nino.admin.design._knobText( 'nosuchknob', 'label', 'Schema word' ) === 'Schema word'
	&& sandbox.Nino.admin.design._knobText( 'contrast', 'label', 'Schema word' ) === 'Contrast' );

// The key is composed ('/_admin/design/knob/'+ knob+ '/'+ part), so nothing
// static sees it and no coverage check can either: a knob renamed in Tokens or
// a position added to it goes silently untranslated. Read the schema and walk
// every key it implies, in both languages
const tokens = fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/Design/Tokens/Tokens.php' ), 'utf8' );
const knobBody = tokens.slice( tokens.indexOf( 'KNOBS = [' ) );
const knobKeys = Array.from( knobBody.matchAll( /^\t\t\t'([a-z]+)' => \[/gm ) ).map( function( m ) { return m[1] } );

const knobFills = {};
[ 'en_US', 'de_DE' ].forEach( function( locale ) {
	const php = fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/Design/text/', locale+ '.php' ), 'utf8' );
	knobFills[locale] = new Set( Array.from( php.matchAll( /'\[\[([^\]]+)\]\]'/g ) ).map( function( m ) { return m[1] } ) );
} );

const missingKnobFills = [];
knobKeys.forEach( function( knob ) {
	const segment = knobBody.slice( knobBody.indexOf( "'"+ knob+ "' => [" ) );
	const steps = ( segment.match( /'steps'\s*=>\s*\[([^\]]*)\]/ ) || [ '', '' ] )[1].match( /'[^']*'/g ) || [];
	const parts = [ 'label', 'note', 'hint' ].concat( steps.map( function( _, index ) { return 'step/'+ ( index + 1 ) } ) );
	parts.forEach( function( part ) {
		[ 'en_US', 'de_DE' ].forEach( function( locale ) {
			const key = '/_admin/design/knob/'+ knob+ '/'+ part;
			if( knobFills[locale].has( key ) === false )
				missingKnobFills.push( key+ ' '+ locale );
		} );
	} );
} );
check( 'every knob the schema declares is named in both languages'+ ( missingKnobFills.length ? ' - missing: '+ missingKnobFills.slice( 0, 6 ).join( ', ' ) : '' ),
	knobKeys.length > 0 && missingKnobFills.length === 0 );
// One page rather than a strip of chips and three size specimens: a design
// decision cannot be judged one token at a time
check( 'the example is delivered whole, as a document into the sandboxed frame', nodes['theme-design-example'].srcdoc === designRead.example );
// A pane that matches the file has nothing to write and nothing to discard,
// and says so with both of its buttons rather than with neither
check( 'Design opens clean, with nothing to save and nothing to revert', nodes['theme-action-save'].disabled === true
	&& nodes['theme-design-reset'].hidden === true );

/*	A preview panel is around 800px wide and Nino's narrowest content ceiling
	is wider than that, so every Width setting used to render identically - the
	frame was narrower than the difference the operator was choosing between.
	The page is given a desktop's layout width and scaled into the panel
	instead, which keeps every proportion inside it exact.	*/
check( 'the page renders at a desktop layout width, not at the panel\'s', nodes['theme-design-example'].style.width === String( theme.PREVIEW_WIDTH )+ 'px' );

/*	Two ways to fit, and PREVIEW_HEIGHT picks which. With a height both
	dimensions are fixed and the scale is whichever of the two fits, so the whole
	page is on screen; without one the page is given the panel's height solved
	back through the scale - as much document as the port can show, and the rest
	scrolls. The stub's port is 800x600.	*/
const scaled = function( width, height ) {
	const frame = element( 'probe-frame' );
	sandbox.Nino.adminUi.scaleFrame( frame, element( 'probe-port' ), width, height );
	return frame.style;
};

const whole = scaled( 2400, 2000 );
check( 'given a layout height, the whole page is fitted into the panel', whole.height === '2000px'
	&& whole.transform === 'scale('+ Math.min( 1, 800 / 2400, 600 / 2000 )+ ')' );
// A page is taller than it is wide and a panel is the other way round, so the
// room left over reads as a margin rather than as a page that failed to fill it
check( '...and centred in the room that leaves', whole.left !== '0px' && whole.left !== '0'
	&& whole.top !== '0' );

const tall = scaled( 2400, 0 );
check( 'without one it is given the panel\'s height, solved back through the scale', tall.transform === 'scale('+ ( 800 / 2400 )+ ')'
	&& tall.height === String( Math.round( 600 / ( 800 / 2400 ) ) )+ 'px'
	&& tall.left === '0' );
// Which of the two this tool ships is one number, and the example is 4700-6200px
// tall at this width - too much page to fit a panel and still be looked at
check( '...which is what the pane uses, the example being a whole site', theme.PREVIEW_HEIGHT === 0
	&& nodes['theme-design-example'].style.transform === 'scale('+ ( 800 / theme.PREVIEW_WIDTH )+ ')' );

// Two tabs rather than one long column. Both panels stay in the DOM - the one
// not being looked at keeps its controls, their values and their listeners
check( 'the pane opens on Colour, with Raster present but hidden', nodes['theme-design-panel-colour'].hidden === false
	&& nodes['theme-design-panel-raster'].hidden === true
	&& nodes['theme-design-tab-colour'].classList.contains('is-active') === true );

posts.length = 0;
nodes['theme-design-tab-raster'].fire('click');
check( 'switching tabs swaps which settings are on screen', nodes['theme-design-panel-raster'].hidden === false
	&& nodes['theme-design-panel-colour'].hidden === true
	&& nodes['theme-design-tab-raster'].classList.contains('is-active') === true
	&& nodes['theme-design-tab-colour'].classList.contains('is-active') === false );
// A tab is a place in the panel below it, so the row says so to a screen
// reader as well as to the eye
check( '...and says so with aria-selected, not by the class alone', nodes['theme-design-tab-raster'].getAttribute('aria-selected') === 'true'
	&& nodes['theme-design-tab-colour'].getAttribute('aria-selected') === 'false' );
check( '...without asking the server for anything', posts.length === 0 );

// The knobs are rendered into the panels, so they have to survive the switch
check( 'the settings behind the other tab are still there', nodes['theme-design-knobs-colour'].children.length === 5
	&& nodes['theme-design-knobs-raster'].children.length === 5 );
// Which mode is previewed is a server question - the frame is sandboxed, so
// nothing out here can reach in and stamp the attribute on it
posts.length = 0;
nodes['theme-design-mode-dark'].fire('click');
check( 'the mode buttons re-ask rather than trying to reach into the frame', posts.length === 1
	&& posts[0].action === 'design/preview'
	&& posts[0].mode === 'dark'
	&& nodes['theme-design-mode-dark'].classList.contains('is-active') === true
	&& nodes['theme-design-mode-light'].classList.contains('is-active') === false );
check( '...and the mode never becomes a design setting', typeof posts[0].payload.mode === 'undefined' );

posts.length = 0;
const spacingKnob = knobSelect( 'theme-design-knobs-raster', 2 );
spacingKnob.value = '1';
spacingKnob.fire('change');
check( 'a Design change asks the server for a preview without changing Theme or frames', posts.length === 1
	&& posts[0].action === 'design/preview'
	&& posts[0].payload.spacing === 1
	&& typeof posts[0].payload.design === 'undefined' );

/*	Saved against current, per field. A count on its own says something is
	different without saying what, which leaves discarding everything as the
	only way back to a position the operator recognises	*/
const fieldMark = function( key ) {
	const field = theme._designFields[key];
	return field !== undefined && field.changeMark !== undefined && field.changeMark.hidden === false
		&& field.classList.contains('is-changed') === true;
};

check( 'an unsaved change is counted in the bar and named in its own field', nodes['theme-action-status'].textContent === '1 unsaved design change'
	&& nodes['theme-action-status'].classList.contains('theme-status-dirty') === true
	&& fieldMark('spacing') === true
	&& fieldMark('shaping') === false );
check( '...and the way back out is offered at the top of the settings it undoes', nodes['theme-design-reset'].hidden === false
	&& nodes['theme-action-save'].disabled === false );

/*	The defect this pane had: opening another dialog and coming back re-read
	the file and put it over whatever had been set here, without a word	*/
posts.length = 0;
nodes['theme-nav-header'].fire('click');
nodes['theme-nav-design'].fire('click');
check( 'leaving Design and returning keeps the unsaved settings rather than re-reading over them',
	posts.filter( function( post ) { return post.action === 'design/read'; } ).length === 0
	&& theme._designSettings.spacing === 1
	&& knobSelect( 'theme-design-knobs-raster', 2 ).value === '1'
	&& nodes['theme-action-status'].textContent === '1 unsaved design change' );

// Knowing something is unsaved is only useful next to a way back that is not
// "remember where ten settings were"
posts.length = 0;
nodes['theme-design-reset'].fire('click');
check( 'the way back out puts every control back on the stored value and clears the marks', theme._designSettings.spacing === 2
	&& knobSelect( 'theme-design-knobs-raster', 2 ).value === '2'
	&& fieldMark('spacing') === false
	&& nodes['theme-design-reset'].hidden === true
	&& nodes['theme-action-save'].disabled === true );
check( '...and asks for the picture that produces', posts.length === 1
	&& posts[0].action === 'design/preview'
	&& posts[0].payload.spacing === 2 );

/*	A half-typed colour is not a colour and cannot be sent - but silence at
	that point reads as "accepted", and the field is left showing a value the
	design does not have	*/
posts.length = 0;
const hex = nodes['theme-design-primary-hex'];
hex.value = '#b6a';
hex.fire('input');
check( 'an incomplete colour is refused out loud rather than dropped in silence', posts.length === 0
	&& theme._designSettings.primary === '#b6a6ff'
	&& hex.getAttribute('aria-invalid') === 'true'
	&& nodes['theme-action-status'].textContent.indexOf('#rrggbb') !== -1
	&& nodes['theme-action-status'].classList.contains('theme-status-error') === true );
// Said in words as well as in colour - a red border alone is a colour telling
// a colour-blind operator that their colour is wrong
check( '...in words, not by the border alone', nodes['theme-action-status'].textContent.length > 20 );

hex.fire('blur');
check( '...and leaving the field puts back the value the design does have', hex.value === '#b6a6ff'
	&& hex.getAttribute('aria-invalid') === 'false'
	&& nodes['theme-action-status'].classList.contains('theme-status-error') === false );

hex.value = '#101820';
hex.fire('input');
check( 'a complete one is taken, and counted', theme._designSettings.primary === '#101820'
	&& nodes['theme-design-primary'].value === '#101820'
	&& fieldMark('primary') === true );

// The one loss this pane cannot undo afterwards
const leaving = unload.map( function( handler ) {
	const event = { prevented : false, preventDefault : function() { event.prevented = true; } };
	handler( event );
	return event;
} );
check( 'closing the tab on unsaved settings is stopped rather than silently taken', leaving.length === 1
	&& leaving[0].prevented === true
	&& leaving[0].returnValue === '' );

/*	Applying a Theme writes the design its manifest declares, so it is the one
	place in this tool where an unsaved change cannot be kept - and therefore
	the one place it has to be asked about	*/
posts.length = 0;
confirmAnswer = false;
nodes['theme-nav-theme'].fire('click');
nodes['theme-action-save'].fire('click');
check( 'applying a Theme over unsaved design changes asks first, and takes no for an answer', confirmed.length === 1
	&& confirmed[0].indexOf('Discard') !== -1
	&& posts.filter( function( post ) { return post.action === 'theme/apply'; } ).length === 0 );
confirmAnswer = true;
nodes['theme-nav-design'].fire('click');

posts.length = 0;
nodes['theme-action-save'].fire('click');
const designSave = posts.filter( function( post ) { return post.action === 'design/save'; } )[0];
check( 'Design save posts the settings directly and only to design/save', designSave !== undefined
	&& designSave.payload.spacing === 2
	&& designSave.payload.primary === '#101820'
	&& typeof designSave.payload.theme === 'undefined'
	&& typeof designSave.payload.header === 'undefined' );
check( 'a written Design is a clean one', nodes['theme-action-status'].textContent === 'Design written.'
	&& nodes['theme-action-save'].disabled === true
	&& nodes['theme-design-reset'].hidden === true
	&& fieldMark('primary') === false );

// Header and Footer each preview and apply one frame only.
posts.length = 0;
nodes['theme-nav-header'].fire('click');
check( 'Header opens on the active frame and renders it server-side', nodes['theme-page-wrap'].classList.contains('show-header') === true
	&& nodes['theme-frame-header'].value === 'v5'
	&& nodes['theme-frame-header-preview'].srcdoc === '<!doctype html><body data-frame="header/v5">'
	&& posts[0].action === 'frame/preview'
	&& posts[0].payload.theme === 'nocturne'
	&& posts[0].payload.design.spacing === 2
	&& posts[0].payload.design.primary === '#101820' );

posts.length = 0;
nodes['theme-frame-header'].value = 'v2';
nodes['theme-frame-header'].fire('change');
nodes['theme-action-save'].fire('click');
const headerApply = posts.filter( function( post ) { return post.action === 'frame/apply'; } )[0];
check( 'Header applies only kind and frame', JSON.stringify( headerApply.payload ) === JSON.stringify( { kind : 'header', frame : 'v2' } )
	&& theme._activeFrames.header === 'v2'
	&& theme._activeFrames.footer === 'v2' );

posts.length = 0;
nodes['theme-nav-footer'].fire('click');
nodes['theme-frame-footer'].value = 'v1';
nodes['theme-frame-footer'].fire('change');
nodes['theme-action-save'].fire('click');
const footerApply = posts.filter( function( post ) { return post.action === 'frame/apply'; } )[0];
check( 'Footer is an independent pane and applies only its own frame', nodes['theme-page-wrap'].classList.contains('show-footer') === true
	&& nodes['theme-action-save'].textContent === 'Apply Footer'
	&& JSON.stringify( footerApply.payload ) === JSON.stringify( { kind : 'footer', frame : 'v1' } )
	&& theme._activeFrames.header === 'v2'
	&& theme._activeFrames.footer === 'v1' );

const template = fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/Design/templates/panel.tpl' ), 'utf8' );
const css = fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/Design/assets/style.css' ), 'utf8' );
const adminTemplate = fs.readFileSync( path.join( __dirname, '../_admin/templates/page-index.tpl' ), 'utf8' );
const templatesTemplate = fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/Templates/templates/panel.tpl' ), 'utf8' );

check( 'template navigation and content contain one matching pair per dialog', [ 'theme', 'design', 'header', 'footer' ].every( function( tab ) {
	return template.indexOf('id="theme-nav-'+ tab+ '"') !== -1 && template.indexOf('id="theme-content-'+ tab+ '"') !== -1;
} ) );
check( 'one shared action bar changes responsibility with the active dialog - its words fills of the module\'s own', ( template.match(/id="theme-action-save"/g) || [] ).length === 1
	&& source.indexOf("theme \t: '/_admin/design/label/apply-theme'") !== -1
	&& source.indexOf("design \t: '/_admin/design/label/save-design'") !== -1
	&& template.indexOf('class="nino-admin-btn-primary" id="theme-action-save">[[/_admin/design/label/apply-theme]]</button>') !== -1
	&& sandbox.Nino.content.getText('/_admin/design/label/save-design') === 'Save Design' );
check( 'the two module panels are fragments the workbench renders into its pane, with no chrome of their own',
	template.includes('<html') === false
	&& template.includes('nino-admin-rail') === false
	&& templatesTemplate.includes('<html') === false
	&& templatesTemplate.includes('nino-admin-rail') === false
	&& adminTemplate.includes('[[/_admin/panes]]')
	&& adminTemplate.includes('admin-tools') === false );
check( 'the four editors are a tab strip inside the pane - the shell\'s own tab bar, not a second rail', template.includes('class="nino-admin-tabs nino-admin-tabs--bar theme-tabs" role="tablist"')
	&& ( template.match(/<button[^>]*id="theme-nav-[a-z]+"[^>]*class="nino-admin-tab[ "]/g) || [] ).length === 4
	&& nodes['theme-nav-footer'].classList.contains('is-active') === true
	&& nodes['theme-nav-footer'].getAttribute('aria-selected') === 'true'
	&& nodes['theme-nav-theme'].getAttribute('aria-selected') === 'false' );
check( 'the open editor is written to the hash the way every panel deep-links', hashes[0] === 'design/theme'
	&& hashes[hashes.length - 1] === 'design/footer' );
check( 'the local stylesheet has an explicit visibility contract for all four panes', [ 'theme', 'design', 'header', 'footer' ].every( function( tab ) {
	return css.indexOf('#theme-page-wrap.show-'+ tab+ ' #theme-content-'+ tab) !== -1;
} ) );

/*	The two places this tool used to differ from the same settings in
	/_install, both of them things the operator reads rather than presses:
	where the way back out sits, and whether a moved setting says so in its
	own row	*/
const installCss 			= fs.readFileSync( path.join( __dirname, '../_admin/install/assets/style.css' ), 'utf8' );
const installTemplate = fs.readFileSync( path.join( __dirname, '../_admin/install/templates/page-wizard.tpl' ), 'utf8' );

check( 'the way back out sits in the Design pane, not in the shared action bar', template.indexOf('id="theme-design-reset"') !== -1
	&& template.indexOf('id="theme-design-reset"') < template.indexOf('class="nino-admin-actionbar"')
	&& ( template.match(/id="theme-action-save"/g) || [] ).length === 1 );
// ...at the far end of the mode row, the same way /_install places its own
check( '...pushed to the far end of the mode row, as /_install places it', css.indexOf('.theme-design-modes #theme-design-reset') !== -1
	&& installCss.indexOf('.install-design-modes #design-reset') !== -1 );
check( '...and carries the same words the wizard\'s does - as a fill, since the panel speaks the interface language', template.indexOf('>[[/_admin/design/label/reset]]</button>') !== -1
	&& sandbox.Nino.content.getText('/_admin/design/label/reset') === 'Back to the theme\u2019s values'
	&& installTemplate.indexOf('Back to the theme&rsquo;s values') !== -1 );

/*	Marked in the field, not only counted in the bar. Nino.adminUi.
	fieldChange() sets .is-changed on every field this tool renders, so the
	mark is a stylesheet rule rather than anything the pane has to remember	*/
check( 'a changed setting is marked in its own row, in the orange /_install uses', css.indexOf('.theme-field.is-changed > span:first-child:before') !== -1
	&& /\.theme-field\.is-changed > span:first-child:before \{[^}]*content: *"\*"/.test( css ) === true
	&& /\.theme-field\.is-changed > span:first-child:before \{[^}]*#ffa834/.test( css ) === true
	&& /\.install-theme-field\.is-changed > span:first-child:before \{[^}]*#ffa834/.test( installCss ) === true );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
