/**
 *	Nino									A compact filesystembased php framework
 *	install-themes-js-smoke.js		DOM checks for the install wizard's Theme,
 *									Header and Footer steps: the theme grid, separate frame
 *									panes, previews and independent apply posts. Design has
 *									its own test file.
 *
 *									The catalogue this step renders lives on disk, so the
 *									fixtures below are read from it rather than typed out
 *									here: a new frame unit or a theme that declares a
 *									different frame pair has to reach this file by itself,
 *									or the checks describe a wizard nobody ships any more.
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

const LIBRARY = path.join( __dirname, '../_admin/install/library' );

/*	Every frame unit the delivery ships, in the order the api reports them -
 *	one directory per unit, exactly what Install\Themes::_frames() scans	*/
function frameUnits( kind ) {
	return fs.readdirSync( path.join( LIBRARY, kind ) )
		.filter( function( name ) { return /^v[0-9]+$/.test( name ); } )
		.sort();
}

/*	The theme catalogue as the api would hand it over. The manifests are php,
 *	so the three values this step actually uses - the label and the two frame
 *	declarations - are read out of them rather than parsed properly	*/
function themeUnits() {

	const themes = {};

	fs.readdirSync( path.join( LIBRARY, 'themes' ) ).sort().forEach( function( key ) {

		const manifest = path.join( LIBRARY, 'themes', key, 'manifest.php' );
		if( fs.existsSync( manifest ) === false )
			return;

		const source 	= fs.readFileSync( manifest, 'utf8' );
		const read 		= function( name ) {
			const match = source.match( new RegExp( "'"+ name+ "'\\s*=>\\s*'([^']*)'" ) );
			return match === null ? '' : match[1];
		};

		themes[key] = {
			label 	: read('label') || key,
			preview : 'preview.svg',
			header 	: read('header'),
			footer 	: read('footer'),
		};
	} );

	return themes;
}

const frames = { header : frameUnits('header'), footer : frameUnits('footer') };
const catalogue = themeUnits();
const themeKeys = Object.keys( catalogue );

/*	Two themes that really disagree about their frames - picking the second
 *	has to move both selects, and a pair that happened to declare the same
 *	frames would let a broken _adoptTheme() pass	*/
const first = themeKeys[0];
const second = themeKeys.filter( function( key ) {
	return catalogue[key].header !== catalogue[first].header && catalogue[key].footer !== catalogue[first].footer;
} )[0];

check( 'the library ships frame units for both kinds', frames.header.length > 0 && frames.footer.length > 0 );
check( 'the library ships themes to pick from', themeKeys.length > 1 );
check( 'two of them declare different frames, so adopting one is observable', second !== undefined );

// A theme naming a frame the delivery does not contain would leave the wizard
// pre-selecting nothing on a fresh install - cheap to catch here, and this is
// the only test that reads both sides
const dangling = themeKeys.filter( function( key ) {
	return ( catalogue[key].header !== '' && frames.header.indexOf( catalogue[key].header ) === -1 )
		|| ( catalogue[key].footer !== '' && frames.footer.indexOf( catalogue[key].footer ) === -1 );
} );
check( 'every theme declares frames that exist on disk'+ ( dangling.length === 0 ? '' : ' - '+ dangling.join(', ') ), dangling.length === 0 );

/*	A stand-in for just the parts of the DOM this step touches: elements are
 *	looked up by id, options are appended to selects, and classList carries the
 *	install-hidden state the frame panels toggle	*/
function element( id, tag ) {

	const el = {
		id : id,
		tagName : ( tag || 'div' ).toUpperCase(),
		type : '',
		name : '',
		checked : false,
		src : '',
		alt : '',
		loading : '',
		className : '',
		textContent : '',
		srcdoc : null,
		dataset : {},
		style : {},
		children : [],
		_value : '',
		_listeners : {},
		classList : {
			_set : new Set(),
			add : function( name ) { el.classList._set.add( name ); },
			remove : function( name ) { el.classList._set.delete( name ); },
			contains : function( name ) { return el.classList._set.has( name ); },
			toggle : function( name, on ) { if( on === true ) el.classList.add( name ); else el.classList.remove( name ); },
		},
		appendChild : function( child ) { el.children.push( child ); child._parent = el; return child; },
		removeAttribute : function( name ) { if( name === 'srcdoc' ) el.srcdoc = null; },
		addEventListener : function( type, handler ) { ( el._listeners[type] = el._listeners[type] || [] ).push( handler ); },
		// Reports whether a handler cancelled the event: inside a <label> that
		// is the difference between enlarging a preview and picking the theme
		// under it
		fire : function( type ) {
			let prevented = false;
			( el._listeners[type] || [] ).forEach( function( handler ) {
				handler( { preventDefault : function() { prevented = true; } } );
			} );
			return prevented;
		},
		// The real one walks the subtree, and _tile()'s change handler marks
		// the active tile with exactly that
		contains : function( node ) {
			if( node === el )
				return true;
			return el.children.some( function( child ) { return child.contains( node ); } );
		},
	};

	Object.defineProperty( el, 'innerHTML', {
		get : function() { return ''; },
		set : function() { el.children.length = 0; },
	} );

	/*	A <select> only takes a value one of its options carries; anything else
		leaves it empty. Without that, a fallback that picks a frame the list
		does not contain would look like it worked	*/
	Object.defineProperty( el, 'value', {
		get : function() { return el._value; },
		set : function( value ) {
			if( el.tagName !== 'SELECT' ) {
				el._value = value;
				return;
			}
			el._value = el.children.some( function( option ) { return option.value === value; } ) ? value : '';
		},
	} );

	return el;
}

const nodes = {};
[
	'themes-msg', 'header-msg', 'footer-msg',
	'themes-frame-header-panel', 'themes-frame-footer-panel',
	'themes-frame-header-unavailable', 'themes-frame-footer-unavailable',
	'themes-frame-header-preview', 'themes-frame-footer-preview',
	'themes-lightbox', 'themes-lightbox-image', 'themes-lightbox-caption',
].forEach( function( id ) { nodes[id] = element( id ); } );

nodes['themes-grid'] 					= element( 'themes-grid', 'div' );
nodes['themes-frame-header'] 	= element( 'themes-frame-header', 'select' );
nodes['themes-frame-footer'] 	= element( 'themes-frame-footer', 'select' );

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
		// Only the tiles are ever queried, and only to mark the active one
		querySelectorAll : function( selector ) {
			if( selector !== '.install-theme-tile' )
				return [];
			return nodes['themes-grid'].children;
		},
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
	fs.readFileSync( path.join( __dirname, '../_admin/install/assets/themes.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'themes.js' }
);

const themes = sandbox.Nino.install.themes;

// At the Theme step Design has not loaded yet. Its settings become available
// before Header opens and that transition has to refresh the hidden preview.
sandbox.Nino.install.design = { _settings : null };

themes._themes 	= catalogue;
themes._frames 	= { header : frames.header.slice(), footer : frames.footer.slice() };
themes._selected = first;


// --- The theme grid --------------------------------------------------------

themes._render();

const tiles = nodes['themes-grid'].children;

check( 'the grid renders one tile per theme in the catalogue', tiles.length === themeKeys.length );

const radios = tiles.map( function( tile ) {
	return tile.children.filter( function( child ) { return child.tagName === 'INPUT'; } )[0];
} );

check( 'every tile carries the radio that picks its theme', radios.every( function( radio ) {
	return radio !== undefined && radio.type === 'radio' && radio.name === 'install-theme';
} ) && radios.map( function( radio ) { return radio.value; } ).join(',') === themeKeys.join(',') );

check( 'the tile of the pre-selected theme is the one marked', radios.filter( function( radio ) { return radio.checked === true; } ).length === 1
	&& radios[themeKeys.indexOf( first )].checked === true
	&& tiles[themeKeys.indexOf( first )].classList.contains('install-theme-tile--active') === true );

check( 'each tile shows the label its manifest declares', tiles.every( function( tile, index ) {
	const body = tile.children.filter( function( child ) { return child.className === 'install-theme-body'; } )[0];
	return body !== undefined && body.children[0].textContent === catalogue[themeKeys[index]].label;
} ) );

// The preview is a click target of its own: the tile is a <label>, so without
// preventDefault() enlarging a preview would also pick the theme under it
const previewImage = tiles[0].children.filter( function( child ) { return child.className === 'install-theme-preview'; } )[0];
const previewPrevented = previewImage.fire('click');
check( 'clicking a preview opens the lightbox instead of picking the theme', previewPrevented === true
	&& nodes['themes-lightbox'].classList.contains('install-hidden') === false
	&& nodes['themes-lightbox-caption'].textContent === catalogue[themeKeys[0]].label
	&& themes._selected === first );


// --- The two frame steps ---------------------------------------------------

themes._renderFrames( {} );

check( 'the frame selects offer every unit on disk', nodes['themes-frame-header'].children.length === frames.header.length
	&& nodes['themes-frame-footer'].children.length === frames.footer.length
	&& nodes['themes-frame-header'].children.map( function( option ) { return option.value; } ).join(',') === frames.header.join(',')
	&& nodes['themes-frame-footer'].children.map( function( option ) { return option.value; } ).join(',') === frames.footer.join(',') );
check( 'a fresh install pre-selects the frames the picked theme was drawn against', nodes['themes-frame-header'].value === catalogue[first].header
	&& nodes['themes-frame-footer'].value === catalogue[first].footer );
check( 'Header and Footer each expose their own panel', nodes['themes-frame-header-panel'].classList.contains('install-hidden') === false
	&& nodes['themes-frame-footer-panel'].classList.contains('install-hidden') === false );
check( 'the unavailable notes stay hidden while variants exist', nodes['themes-frame-header-unavailable'].classList.contains('install-hidden') === true
	&& nodes['themes-frame-footer-unavailable'].classList.contains('install-hidden') === true );

// A revisit has to show what is installed, not what the theme would suggest.
// Deliberately the frames of the *other* theme, so "installed wins" is what
// the values prove rather than a coincidence
const installed = { header : catalogue[second].header, footer : catalogue[second].footer };
themes._renderFrames( installed );
check( 'an already-installed frame wins over the theme\'s declaration on a revisit', nodes['themes-frame-header'].value === installed.header
	&& nodes['themes-frame-footer'].value === installed.footer );

// A version number says nothing about what a frame looks like, and unlike a
// theme there is nothing to open in a lightbox - so each select carries the
// rendered thing beneath it
check( 'each select renders its frame as soon as the step opens', nodes['themes-frame-header-preview'].srcdoc === '<!doctype html><body data-frame="header/'+ installed.header+ '">'
	&& nodes['themes-frame-footer-preview'].srcdoc === '<!doctype html><body data-frame="footer/'+ installed.footer+ '">' );

/*	A frame the project has recorded but the delivery no longer contains is
	not a value a select can hold: the render has to step down to the theme's
	own declaration rather than leave the step on an empty select, which is
	what the operator would then apply.	*/
themes._renderFrames( { header : 'v99', footer : 'v99' } );
check( 'a frame the delivery no longer has steps down to the theme\'s declaration', nodes['themes-frame-header'].value === catalogue[first].header
	&& nodes['themes-frame-footer'].value === catalogue[first].footer
	&& nodes['themes-frame-header'].value !== ''
	&& nodes['themes-frame-footer'].value !== '' );

themes._renderFrames( installed );

posted.length = 0;
themes._ready = true;
sandbox.Nino.install.design._settings = { primary : '#c81e2d', spacing : 'tight' };
sandbox.Nino.install.header.showCurrent();

check( 'opening Header refreshes its preview with the Design just chosen', posted.length === 1
	&& posted[0].action === 'themes/frame'
	&& posted[0].payload.design.primary === '#c81e2d'
	&& posted[0].payload.design.spacing === 'tight' );

posted.length = 0;
const otherHeader = frames.header.filter( function( key ) { return key !== installed.header; } )[0];
nodes['themes-frame-header'].value = otherHeader;
nodes['themes-frame-header'].fire('change');

check( 'changing a select re-renders that preview, and only that one', nodes['themes-frame-header-preview'].srcdoc === '<!doctype html><body data-frame="header/'+ otherHeader+ '">'
	&& nodes['themes-frame-footer-preview'].srcdoc === '<!doctype html><body data-frame="footer/'+ installed.footer+ '">'
	&& posted.filter( function( p ) { return p.action === 'themes/frame' } ).length === 1 );

// The document is built server-side: it needs the library's templates, its
// text files and the theme's stylesheet, none of which the browser has
check( 'the preview is asked for, not assembled here', posted[0].payload.kind === 'header'
	&& posted[0].payload.frame === otherHeader
	&& posted[0].payload.theme === first
	&& posted[0].payload.design.primary === '#c81e2d'
	&& posted[0].payload.design.spacing === 'tight' );

// _renderFrames() runs again on every visit to the step, and a second
// listener would make every later change fire twice
themes._renderFrames( installed );
posted.length = 0;
nodes['themes-frame-header'].fire('change');
check( 'a revisit does not stack a second listener on each select', posted.filter( function( p ) { return p.action === 'themes/frame' } ).length === 1 );


// --- Picking a theme -------------------------------------------------------

/*	Through the radio, which is the only way an operator has: its change
	handler is what sets _selected, moves the tile marker and adopts the
	theme's frames. Calling _adoptTheme() here instead would test the half
	that cannot break on its own.

	Back to the first theme's own frames first - with the selects already on
	the frames the second theme names, "picking it moves them" is true before
	anything is picked, and the check would pass on a handler that adopts
	nothing at all.	*/
themes._renderFrames( {} );
check( 'the frame selects start on the picked theme\'s frames, so adopting another is observable', nodes['themes-frame-header'].value === catalogue[first].header
	&& nodes['themes-frame-footer'].value === catalogue[first].footer );

posted.length = 0;
radios[themeKeys.indexOf( second )].fire('change');

check( 'picking a theme in the grid selects it', themes._selected === second );
check( '...and moves the marker to its tile', tiles[themeKeys.indexOf( second )].classList.contains('install-theme-tile--active') === true
	&& tiles[themeKeys.indexOf( first )].classList.contains('install-theme-tile--active') === false );
check( '...and moves the frame selects to the ones it names', nodes['themes-frame-header'].value === catalogue[second].header
	&& nodes['themes-frame-footer'].value === catalogue[second].footer );
// Setting .value does not fire 'change', so the preview would keep showing
// the frame the previous theme named
check( '...and the preview moves with them', nodes['themes-frame-header-preview'].srcdoc === '<!doctype html><body data-frame="header/'+ catalogue[second].header+ '">' );


// --- Applying --------------------------------------------------------------

// Themes no longer owns the frame controls: each following tab persists its
// own choice without recopying the theme or resetting Design.
posted.length = 0;
themes._ready = true;
nodes['themes-frame-header'].value = frames.header[0];
nodes['themes-frame-footer'].value = frames.footer[0];

let applied = null;
themes.apply( function( success ) { applied = success; } );

const applyPost = posted.filter( function( post ) { return post.action === 'themes/apply' } )[0];

check( 'the Theme tab posts only its own choice', applied === true
	&& applyPost !== undefined
	&& Object.keys( applyPost.payload ).length === 1
	&& applyPost.payload.theme === second );

let headerApplied = null;
let footerApplied = null;
sandbox.Nino.install.header.apply( function( success ) { headerApplied = success; } );
sandbox.Nino.install.footer.apply( function( success ) { footerApplied = success; } );

const frameApplyPosts = posted.filter( function( post ) { return post.action === 'themes/frame/apply' } );
check( 'Header and Footer are registered as independent wizard modules', typeof sandbox.Nino.install.header.showCurrent === 'function'
	&& typeof sandbox.Nino.install.footer.showCurrent === 'function' );
check( 'each frame tab posts only its own kind and selected variant', headerApplied === true && footerApplied === true
	&& frameApplyPosts.length === 2
	&& JSON.stringify( frameApplyPosts[0].payload ) === JSON.stringify( { kind : 'header', frame : frames.header[0] } )
	&& JSON.stringify( frameApplyPosts[1].payload ) === JSON.stringify( { kind : 'footer', frame : frames.footer[0] } ) );

/*	Both guards on the way out. A step that answers "yes, applied" without
	posting anything would let the wizard walk past a theme nobody chose, and
	the operator would find out on the last page.	*/
posted.length = 0;
themes._selected = null;
let nothingSelected = null;
themes.apply( function( success ) { nothingSelected = success; } );
check( 'applying without a theme fails instead of posting', nothingSelected === false
	&& posted.length === 0
	&& nodes['themes-msg'].textContent !== '' );

themes._ready = false;
themes._selected = second;
let notLoaded = null;
themes.apply( function( success ) { notLoaded = success; } );
check( 'applying before the catalogue loaded fails instead of posting', notLoaded === false
	&& posted.length === 0 );

themes._ready = true;


// --- A delivery without one frame kind -------------------------------------

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