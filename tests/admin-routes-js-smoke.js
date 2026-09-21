/**
 *	Nino										A compact filesystembased php framework
 *	admin-routes-js-smoke.js	DOM-light checks for the Routes panel's script
 *													(_admin/Nino/Modules/Routes/assets/admin.js).
 *
 *													The ↑/↓ buttons of that list reorder the persisted
 *													routes, and equal menu priorities follow route order -
 *													so this list is also what orders every navigation the
 *													pages appear in. A move the server refuses therefore
 *													has to be said out loud rather than swallowed, and
 *													what is on screen afterwards has to be what is on
 *													disk. The checks below are about exactly that.
 *
 *	Usage: node tests/admin-routes-js-smoke.js
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


// --- an element stand-in, the same shape the other panel tests build -------

function classList() {
	const values = new Set();
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
	};
}

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
		title : '',
		type : '',
		href : '',
		id : '',
		disabled : false,
		style : {},
		attributes : {},
		children : [],
		listeners : {},
		appendChild : function( child ) { el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
	};
	el.classList = classList();
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

// Tolerates a control that is not on screen, so a panel that threw its own
// buttons away reports as failed checks rather than as a crashed test
function fire( el, name ) {
	if( el === null || el === undefined )
		return;
	( el.listeners[name] || [] ).forEach( function( fn ) { fn( { preventDefault : function() {} } ) } );
}

/** The route rows on screen, in the order they stand in */
function rows() {
	return findAll( mount, function( el ) { return el.className === 'admin-page-row' } );
}

function rowLabels() {
	return rows().map( function( li ) { return li.children[0].children[0].textContent.split(' ')[0] } );
}

/** One row's ↑ or ↓ button */
function moveButton( httpUri, direction ) {
	const row = rows().find( function( li ) { return li.children[0].children[0].textContent.split(' ')[0] === httpUri } );
	if( row === undefined )
		return null;
	return row.children[1].children[ direction === 'up' ? 0 : 1 ];
}

function messageText() {
	const line = findAll( mount, function( el ) { return el.id === 'routes-list-msg' } )[0];
	return line === undefined ? null : line.textContent;
}

function messageClass() {
	const line = findAll( mount, function( el ) { return el.id === 'routes-list-msg' } )[0];
	return line === undefined ? null : line.className;
}


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Routes/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'routes-list';
const form = element('div');
form.id = 'routes-form';

const requests = [];

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
		getElementById : function( id ) {
			if( id === 'routes-list' ) return mount;
			if( id === 'routes-form' ) return form;
			return findAll( mount, function( el ) { return el.id === id } )[0] ?? null;
		},
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, location : { hash : '' } };

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
vm.runInContext( source('_admin/Nino/Modules/Routes/assets/admin.js'), context, { filename : 'admin.js' } );

const panel = Nino.admin.routes;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

function listing( order ) {
	return {
		pages 		: order.map( function( uri ) { return { httpUri : uri, uri : uri, template : 'page'+ uri.replace('/','-') } } ),
		templates : [],
		locales 	: [ 'de_DE' ],
		navs 			: [],
	};
}

console.log('Admin Routes');

panel.init();
check( 'the panel asks for the route list', requests.length === 1 && requests[0].action === 'routes/list' );

answer( 200, listing( [ '/', '/about', '/contact' ] ) );
check( 'every route is a row, in the persisted order', rowLabels().join(',') === '/,/about,/contact' );
check( 'the list carries the line a refused move reports into', messageText() === '' && messageClass() === '' );

/*	A move the server accepts: the answer is the new order, and the list is
	drawn from it rather than from a local guess	*/
fire( moveButton( '/contact', 'up' ), 'click' );
check( 'the move is sent for the row whose arrow was pressed', requests.length === 2
	&& requests[1].action === 'routes/move'
	&& requests[1].payload.httpUri === '/contact' && requests[1].payload.direction === 'up' );

answer( 200, { pages : listing( [ '/', '/contact', '/about' ] ).pages } );
check( 'an accepted move redraws the list in the order the server answers with', rowLabels().join(',') === '/,/contact,/about' );
check( '...and says nothing, because nothing went wrong', messageText() === '' );

/*	A move the server refuses. It used to end in a bare return: the row did
	not move, nothing appeared anywhere, and the arrow read as a button that
	does nothing at all. The order on screen is a guess from that moment on -
	somebody else may have deleted the very route this list still draws - so
	it is read back, and the reason is said.	*/
fire( moveButton( '/about', 'up' ), 'click' );
answer( 409, { error : 'route was removed in the meantime' } );

check( 'a refused move reads the list back from the server', requests.length === 4 && requests[3].action === 'routes/list' );
answer( 200, listing( [ '/', '/contact' ] ) );

check( '...so what is on screen afterwards is what is on disk', rowLabels().join(',') === '/,/contact' );
check( '...and the reason the server gave is under the list', messageText() === '(409) route was removed in the meantime'
	&& messageClass() === 'nino-admin-error' );

// An answer with no reason of its own still says what happened
fire( moveButton( '/contact', 'up' ), 'click' );
answer( 500, null );
answer( 200, listing( [ '/', '/contact' ] ) );
check( 'an answer with no reason falls back to the shared sentence', messageText() === '(500) '+ text('/_admin/common/error/save')
	&& text('/_admin/common/error/save') !== '' );
check( '...and the list is still a list of routes', rowLabels().join(',') === '/,/contact' );

// A reload that fails too must not leave the panel silent about the move
fire( moveButton( '/contact', 'up' ), 'click' );
answer( 403, { error : 'no permission' } );
answer( 403, null );
check( 'a move error survives a reload that fails as well', messageText() === '(403) no permission' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
