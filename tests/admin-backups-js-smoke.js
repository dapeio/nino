/**
 *	Nino										A compact filesystembased php framework
 *	admin-backups-js-smoke.js	DOM-light checks for the Backups panel's script
 *													(_admin/Nino/Modules/Backups/assets/admin.js).
 *
 *													This is the screen somebody opens when the site is
 *													already broken, so what it does with a refusal
 *													matters more here than anywhere else: a restore the
 *													server did not carry out changed nothing, and every
 *													date on the list is still a date worth trying. The
 *													checks below are about the list surviving that.
 *
 *	Usage: node tests/admin-backups-js-smoke.js
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
		type : '',
		id : '',
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

/** The restore buttons currently on screen, in the order they stand in */
function dateRows() {
	const list = findAll( mount, function( el ) { return el.id === 'backups-dates' } )[0];
	return list === undefined ? [] : list.children.map( function( li ) { return li.children[0].textContent } );
}

function restoreButton( date ) {
	const list = findAll( mount, function( el ) { return el.id === 'backups-dates' } )[0];
	if( list === undefined )
		return null;
	const row = list.children.find( function( li ) { return li.children[0].textContent === date } );
	return row === undefined ? null : row.children[1];
}

function messageLine() {
	return findAll( mount, function( el ) { return el.id === 'backups-message' } )[0] ?? null;
}

function messageText() {
	const line = messageLine();
	return line === null ? '' : line.textContent;
}

function messageClass() {
	const line = messageLine();
	return line === null ? '' : line.className;
}


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Backups/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'backups-list';

const requests = [];
const alerts = [];
let reloaded = 0;
let confirmAnswer = true;

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
			return id === 'backups-list' ? mount : findAll( mount, function( el ) { return el.id === id } )[0] ?? null;
		},
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = {
	Nino : Nino,
	confirm : function() { return confirmAnswer },
	alert : function( message ) { alerts.push( message ) },
	location : { reload : function() { reloaded++ } },
};

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
vm.runInContext( source('_admin/Nino/Modules/Backups/assets/admin.js'), context, { filename : 'admin.js' } );

const panel = Nino.admin.backups;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

console.log('Admin Backups');

panel.init();
check( 'the panel asks for the list of dates', requests.length === 1 && requests[0].action === 'backups/list' );

answer( 200, { dates : [ '2024-05-03', '2024-05-02', '2024-05-01' ] } );
check( 'every date is a row with a restore button', dateRows().join(',') === '2024-05-03,2024-05-02,2024-05-01'
	&& restoreButton('2024-05-02') !== null );
check( 'the list carries the line an error reports into', messageLine() !== null
	&& messageLine().attributes['aria-live'] === 'polite' && messageLine().textContent === '' );

/*	A restore the server refused. Nothing was overwritten - the snapshot the
	panel offers is still exactly as valid as it was a second ago - so the
	list has to survive it, or the one screen for getting a broken site back
	has replaced its backups with a sentence and only a reload undoes that	*/
fire( restoreButton('2024-05-02'), 'click' );
check( 'the restore is sent for the date whose button was pressed', requests.length === 2
	&& requests[1].action === 'backups/restore' && requests[1].payload.date === '2024-05-02' );

answer( 500, { error : 'the backup key is missing' } );
check( 'a refused restore keeps every date on the list', dateRows().join(',') === '2024-05-03,2024-05-02,2024-05-01' );
check( '...and keeps the buttons that are the way to try again', restoreButton('2024-05-01') !== null );
check( '...and reports the status and the reason the server gave', messageText() === '(500) the backup key is missing'
	&& messageClass() === 'nino-admin-error' );
check( '...and does not pretend the restore happened', alerts.length === 0 && reloaded === 0 );

// An answer with no reason of its own falls back to a sentence of the
// workbench's own rather than to an empty line
fire( restoreButton('2024-05-01'), 'click' );
answer( 503, null );
check( 'an answer with no reason still says what happened', messageText() === '(503) '+ text('/_admin/common/error/request')
	&& text('/_admin/common/error/request') !== '' );
check( '...and the list is still there after the second refusal too', dateRows().length === 3 );

// A restore that worked: the page reloads, because everything the workbench
// has in memory is about the state that was just replaced
fire( restoreButton('2024-05-03'), 'click' );
answer( 200, { ok : true } );
check( 'a restore that succeeded says so and reloads the page', alerts.length === 1 && reloaded === 1 );

// The other failure, which is not this one: a list that could not be loaded
// has nothing to keep, so the error takes the place of it
mount.innerHTML = '';
requests.length = 0;
panel.init();
answer( 403, { error : 'no permission' } );
check( 'a list that could not be loaded is replaced by the error, having nothing to keep',
	mount.children.length === 1 && mount.children[0].className === 'nino-admin-error'
	&& mount.children[0].textContent === '(403) no permission' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
