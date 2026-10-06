/**
 *	Nino											A compact filesystembased php framework
 *	admin-maintenance-js-smoke.js	DOM-light checks for the Maintenance panel's script
 *														(_nino/Nino/Modules/Maintenance/assets/admin.js) and the
 *														notice it puts at the top of the workbench while the
 *														site shows its maintenance page.
 *
 *														The notice is for whoever works anywhere in the
 *														workbench, so it is not in the panel's own pane - it is
 *														in the content column above every panel - and it has to
 *														follow the state the server reports: there after the
 *														page loads or a save that switched maintenance on, gone
 *														after one that switched it off, and never twice.
 *
 *	Usage: node tests/admin-maintenance-js-smoke.js
 */

'use strict';

const fs 		= require('fs');
const path	= require('path');
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

function source( relative ) {
	return fs.readFileSync( path.join( __dirname, '..', relative ), 'utf8' );
}

/**
 *	The fills of one text file, key => value - enough of the php to read
 *	a `'[[/key]]' => 'value',` line
 */
function fills( relative ) {
	const map = {};
	const re = /'\[\[(\/_admin\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g;
	const text = source( relative );
	let match;
	while( ( match = re.exec( text ) ) )
		map[match[1]] = match[2].replace( /\\'/g, "'" );
	return map;
}


// --- an element stand-in: a tree that knows its parent ---------------------

function findAll( root, predicate ) {
	const found = [];
	( function walk( node ) {
		( node.children || [] ).forEach( function( child ) {
			if( predicate( child ) === true )
				found.push( child );
			walk( child );
		} );
	} )( root );
	return found;
}

function element( tag ) {
	const el = {
		tagName : String( tag ).toUpperCase(),
		className : '',
		textContent : '',
		id : '',
		href : '',
		type : '',
		value : '',
		checked : false,
		style : {},
		attributes : {},
		children : [],
		parent : null,
		listeners : {},
		appendChild : function( child ) { child.parent = el; el.children.push( child ); return child },
		insertBefore : function( child, ref ) {
			child.parent = el;
			const at = ref === null ? -1 : el.children.indexOf( ref );
			if( at < 0 )
				el.children.push( child );
			else
				el.children.splice( at, 0, child );
			return child;
		},
		insertAdjacentElement : function( where, child ) {
			if( where !== 'afterend' )
				throw new Error( 'only afterend is stood in for' );
			child.parent = el.parent;
			el.parent.children.splice( el.parent.children.indexOf( el ) + 1, 0, child );
			return child;
		},
		remove : function() {
			if( el.parent !== null )
				el.parent.children.splice( el.parent.children.indexOf( el ), 1 );
			el.parent = null;
		},
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
		querySelector : function( selector ) {
			if( selector === ':scope > h1' )
				return el.children.find( function( child ) { return child.tagName === 'H1' } ) ?? null;
			const key = /^\[data-key="([a-z]+)"\]$/.exec( selector );
			return key === null ? null : ( findAll( el, function( child ) { return child.attributes['data-key'] === key[1] } )[0] ?? null );
		},
	};
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

function banners( root ) {
	return findAll( root, function( el ) { return el.id === 'maintenance-banner' } );
}

function byId( root, id ) {
	return root.id === id ? root : ( findAll( root, function( el ) { return el.id === id } )[0] ?? null );
}


// --- the words -------------------------------------------------------------

const moduleEn = fills('_nino/Nino/Modules/Maintenance/text/en_US.php');
const moduleDe = fills('_nino/Nino/Modules/Maintenance/text/de_DE.php');

function text( key ) {
	return moduleEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

/** A workbench column as the shell draws it: the heading only a screen reader sees, then the panes */
function column() {
	const col = element('main');
	col.id = 'admin-content-wrap';
	const heading = element('h1');
	col.appendChild( heading );
	col.appendChild( element('div') );
	return col;
}

let content = column();
let form 		= null;
const requests = [];

function present( withForm ) {
	content = column();
	form = null;
	if( withForm === true ) {
		form = element('div');
		form.id = 'maintenance-form';
		content.appendChild( form );
	}
}

const Nino = {
	admin : {},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		createTextNode : function( value ) { return { nodeType : 3, textContent : String( value ), children : [] } },
		getElementById : function( id ) {
			if( id === 'admin-content-wrap' )
				return content;
			return byId( content, id );
		},
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino };

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );

// The form's own fields are the design system's business, and have their own
// tests - here they are only there. The save reads them back by their key
Nino.adminUi.switchField = function( options ) {
	const field = element('div');
	const input = element('input');
	input.attributes['data-key'] = options.key;
	input.checked = options.checked;
	field.appendChild( input );
	return field;
};
Nino.adminUi.numberField = function( options ) {
	const field = element('div');
	const input = element('input');
	input.attributes['data-key'] = options.key;
	input.value = String( options.value );
	input.min = options.min;
	input.max = options.max;
	field.appendChild( input );
	return field;
};

vm.runInContext( source('_nino/Nino/Modules/Maintenance/assets/admin.js'), context, { filename : 'admin.js' } );

const panel = Nino.admin.maintenance;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

console.log('Admin Maintenance');

// --- the panel is not there -------------------------------------------------

present( false );
panel.init();
check( 'an account without the panel has no form on the page: nothing is requested and nothing drawn', requests.length === 0 && banners( content ).length === 0 );

// --- the page loads ----------------------------------------------------------

present( true );
panel.init();
check( 'the panel asks for the state', requests.length === 1 && requests[0].action === 'maintenance/status' );
answer( 200, { status : false, retry : 3600 } );
check( 'a site that is online has no notice', banners( content ).length === 0 );

present( true );
requests.length = 0;
panel.init();
answer( 200, { status : true, retry : 3600 } );
let drawn = banners( content );
check( 'a site that shows its maintenance page gets one notice', drawn.length === 1 );
check( '...in the workbench\'s content column, right after the heading - above every panel, not in the form\'s pane', content.children[1] === drawn[0] && content.children[0].tagName === 'H1' && drawn[0].parent === content );
check( '...a status notice of the design system, saying so in the interface language', drawn[0].className === 'nino-admin-notice' && drawn[0].attributes.role === 'status'
	&& drawn[0].textContent === text('/_admin/maintenance/banner/text') && text('/_admin/maintenance/banner/text') !== '' );

const anchors = findAll( drawn[0], function( el ) { return el.tagName === 'A' } );
check( '...with a link to the panel, labelled in the interface language', anchors.length === 1 && anchors[0].href === '#maintenance'
	&& anchors[0].textContent === text('/_admin/maintenance/banner/link') && text('/_admin/maintenance/banner/link') !== '' );

// --- a save --------------------------------------------------------------------

requests.length = 0;
panel._save();
check( 'a save posts the switch and the seconds', requests.length === 1 && requests[0].action === 'maintenance/set' && requests[0].payload.retry === '3600' && requests[0].payload.status === true );
answer( 200, { status : true, retry : 3600 } );
check( 'a save that leaves maintenance on keeps one notice, not two', banners( content ).length === 1 );

requests.length = 0;
panel._save();
answer( 200, { status : false, retry : 3600 } );
check( 'a save that answers false takes the notice away, and nothing else of the column', banners( content ).length === 0 && content.children.length === 3 && content.children[0].tagName === 'H1' );

requests.length = 0;
panel._save();
answer( 200, { status : true, retry : 7200 } );
check( 'and one that answers true puts it back', banners( content ).length === 1 );

requests.length = 0;
panel._save();
answer( 500, { error : 'could not write' } );
check( 'a save that failed changes nothing about it', banners( content ).length === 1 );

// --- asking again --------------------------------------------------------------

requests.length = 0;
panel.init();
answer( 200, { status : true, retry : 3600 } );
check( 'asking again does not draw a second notice', banners( content ).length === 1 );
requests.length = 0;
panel.init();
answer( 200, { status : false, retry : 3600 } );
check( 'and an answer that says online removes it', banners( content ).length === 0 );

// --- a refusal -----------------------------------------------------------------

present( true );
requests.length = 0;
panel.init();
answer( 403, { error : 'no' } );
check( 'a refused status request draws no notice - the state is not known', banners( content ).length === 0 );

// --- the bounds ---------------------------------------------------------------

// The seconds field is bounded by what the server answers, not by a copy of it
present( true );
requests.length = 0;
panel.init();
answer( 200, { status : false, retry : 3600, min : 120, max : 86400 } );
const retryInput = form.querySelector('[data-key="retry"]');
check( 'the seconds field takes its bounds from the answer', retryInput !== null && retryInput.min === 120 && retryInput.max === 86400 );

// --- the words ---------------------------------------------------------------

check( 'both interface languages have the two words', [ moduleEn, moduleDe ].every( function( map ) {
	return map['/_admin/maintenance/banner/text'] !== undefined && map['/_admin/maintenance/banner/text'] !== ''
		&& map['/_admin/maintenance/banner/link'] !== undefined && map['/_admin/maintenance/banner/link'] !== '';
} ) );

console.log( '\n'+ checks +' checks, '+ failures +' failed' );
process.exit( failures > 0 ? 1 : 0 );
