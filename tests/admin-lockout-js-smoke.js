/**
 *	Nino										A compact filesystembased php framework
 *	admin-lockout-js-smoke.js	DOM-light checks for the Login protection tab's script
 *													(_admin/Nino/Modules/Users/assets/lockout.js).
 *
 *													The tab is a form with two numbers and, below them,
 *													the accounts that are locked out with a button that
 *													lifts each lock. The lift is a request of its own and
 *													must not touch the form: a number typed and not yet
 *													saved is still there afterwards, and nothing of the
 *													locked list carries a data-key, since the save posts
 *													every data-key of the form.
 *
 *	Usage: node tests/admin-lockout-js-smoke.js
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

/** The fills of one text file, key => value - enough of the php to read a `'[[/key]]' => 'value',` line */
function fills( relative ) {
	const map = {};
	const re = /'\[\[(\/_admin\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g;
	const text = source( relative );
	let match;
	while( ( match = re.exec( text ) ) )
		map[match[1]] = match[2].replace( /\\'/g, "'" );
	return map;
}


// --- an element stand-in: enough tree to find things in -------------------

function classList( el ) {
	const values = new Set();
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
		toggle : function( value, force ) { ( force === undefined ? values.has( value ) === false : force ) ? values.add( value ) : values.delete( value ) },
	};
}

function matches( el, selector ) {
	return selector.split(',').some( function( part ) {
		part = part.trim();
		const attribute = /^\[([a-z-]+)="([^"]*)"\]$/.exec( part );
		if( attribute !== null )
			return el.attributes[attribute[1]] === attribute[2] || ( attribute[1].indexOf('data-') === 0 && el.dataset[attribute[1].slice(5)] === attribute[2] );
		if( part.charAt(0) === '#' )
			return el.id === part.slice(1);
		return el.tagName === part.toUpperCase();
	} );
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
		value : '',
		disabled : false,
		style : {},
		dataset : {},
		attributes : {},
		children : [],
		listeners : {},
		parentNode : null,
		appendChild : function( child ) { child.parentNode = el; el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ); if( name === 'id' ) el.id = String( value ) },
		removeAttribute : function( name ) { delete el.attributes[name] },
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
		querySelector : function( selector ) { return findAll( el, function( node ) { return matches( node, selector ) } )[0] ?? null },
		querySelectorAll : function( selector ) { return findAll( el, function( node ) { return matches( node, selector ) } ) },
		closest : function() { return null },
		focus : function() {},
	};
	el.classList = classList( el );
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

function fire( el, name ) {
	if( el === null || el === undefined )
		return;
	( el.listeners[name] || [] ).forEach( function( fn ) { fn( { preventDefault : function() {} } ) } );
}


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Users/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'lockout-form';

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
		createTextNode : function( value ) { return { tagName : '#TEXT', textContent : value, attributes : {}, dataset : {}, children : [] } },
		getElementById : function( id ) {
			return id === 'lockout-form' ? mount : findAll( mount, function( el ) { return el.id === id } )[0] ?? null;
		},
		querySelectorAll : function( selector ) { return mount.querySelectorAll( selector.replace( '#lockout-form ', '' ) ) },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino };

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
vm.runInContext( source('_admin/Nino/Modules/Users/assets/lockout.js'), context, { filename : 'lockout.js' } );

const tab = Nino.admin.lockout;

/** Answer the last request the tab made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

const FIELDS = [
	{ key : '/nino/auth/maxtries', type : 'int', min : 1, max : 100, label : '/_admin/lockout/label/maxtries', hint : '/_admin/lockout/hint/maxtries', value : 5 },
	{ key : '/nino/auth/cooldown', type : 'int', min : 60, max : 604800, unit : 'seconds', label : '/_admin/lockout/label/cooldown', hint : '/_admin/lockout/hint/cooldown', value : 3600 },
];

function lockedBox() {
	return findAll( mount, function( el ) { return el.id === 'lockout-locked' } )[0] ?? null;
}

function rows() {
	const box = lockedBox();
	return box === null ? [] : findAll( box, function( el ) { return el.tagName === 'LI' } );
}

function numberInputs() {
	return findAll( mount, function( el ) { return el.tagName === 'INPUT' } );
}

function lockedText() {
	const box = lockedBox();
	return box === null ? '' : findAll( box, function( el ) { return el.textContent !== '' } ).map( function( el ) { return el.textContent } ).join(' | ');
}

function statusText() {
	const box = lockedBox();
	const line = box === null ? undefined : findAll( box, function( el ) { return el.attributes['aria-live'] !== undefined } )[0];
	return line === undefined ? '' : line.textContent;
}


console.log('Admin Lockout - the locked accounts');

tab.init();
check( 'the tab asks for the numbers and the locked accounts in one list', requests.length === 1 && requests[0].action === 'lockout/list' );

answer( 200, { fields : FIELDS, locked : [ { mail : 'a@example.com', until : '2026-10-05 14:30' }, { mail : 'b@example.com', until : '2026-10-05 15:00' } ] } );
check( 'a fieldset below the numbers lists every locked account with its time', lockedBox() !== null && rows().length === 2
	&& lockedText().includes( 'a@example.com' ) && lockedText().includes( 'locked until 2026-10-05 14:30' ) );
check( '...with a Lift lock button on each row', rows().every( function( row ) {
	return findAll( row, function( el ) { return el.tagName === 'BUTTON' && el.textContent === 'Lift lock' } ).length === 1;
} ) );
check( 'nothing of the locked list carries a data-key - the save posts every one of the form', findAll( lockedBox(), function( el ) {
	return el.attributes['data-key'] !== undefined || el.dataset.key !== undefined;
} ).length === 0 );
check( 'the two numbers are still the form\'s own data-key fields', mount.querySelectorAll('[data-key="/nino/auth/maxtries"]').length === 1 );

/*	A number typed and not saved. Lifting a lock is a request of its own: it
	must not rebuild the form, or the edit is gone without a word	*/
const maxtries = mount.querySelector('[data-key="/nino/auth/maxtries"]');
maxtries.value = '9';

const button = findAll( rows()[0], function( el ) { return el.tagName === 'BUTTON' } )[0];
fire( button, 'click' );
check( 'a click sends the unlock for the account of the row', requests.length === 2 && requests[1].action === 'lockout/unlock' && requests[1].payload.username === 'a@example.com' );
check( '...and switches the button off while the request is on its way', button.disabled === true );

answer( 200, { locked : [ { mail : 'b@example.com', until : '2026-10-05 15:00' } ] } );
check( 'the answer redraws the list from what the server still holds', rows().length === 1
	&& findAll( rows()[0], function( el ) { return el.textContent === 'b@example.com' } ).length === 1 );
check( '...and says which lock was lifted', statusText() === 'The lock on a@example.com has been lifted.' );
check( 'the number form was not rebuilt: what was typed is still there', mount.querySelector('[data-key="/nino/auth/maxtries"]') === maxtries && maxtries.value === '9' );

// A refusal keeps the row and says why
const second = findAll( rows()[0], function( el ) { return el.tagName === 'BUTTON' } )[0];
fire( second, 'click' );
answer( 500, { error : 'could not lift the lock' } );
check( 'a refused unlock keeps the row', rows().length === 1 && lockedText().includes( 'b@example.com' ) );
check( '...and the button is usable again', second.disabled === false );
check( '...and the status line says what happened, with the status', statusText() === '(500) could not lift the lock' );

// Last one: the empty state takes the list's place
fire( second, 'click' );
answer( 200, { locked : [] } );
check( 'with nobody left locked the list says so', rows().length === 0 && lockedText().includes( 'No account is locked.' ) );
check( '...and the numbers still were not rebuilt', mount.querySelector('[data-key="/nino/auth/maxtries"]') === maxtries && maxtries.value === '9' );

// Coming back to the tab: only the locked fieldset is fetched again
requests.length = 0;
tab.showCurrent();
check( 'showing the tab again asks for the list', requests.length === 1 && requests[0].action === 'lockout/list' );
answer( 200, { fields : FIELDS, locked : [ { mail : 'c@example.com', until : '2026-10-05 16:00' } ] } );
check( '...and redraws only the locked accounts', rows().length === 1 && lockedText().includes( 'c@example.com' )
	&& mount.querySelector('[data-key="/nino/auth/maxtries"]') === maxtries && maxtries.value === '9' );
check( 'the numbers\' inputs are the same two as before', numberInputs().length >= 2 );

check( 'the tab answers every word it says in both languages', [ 'locked/title', 'locked/empty', 'locked/until', 'label/unlock', 'msg/unlocked', 'error/unlock' ].every( function( key ) {
	return [ 'en_US', 'de_DE' ].every( function( locale ) { return fills('_admin/Nino/Modules/Users/text/'+ locale+ '.php')['/_admin/lockout/'+ key] !== undefined } );
} ) );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
