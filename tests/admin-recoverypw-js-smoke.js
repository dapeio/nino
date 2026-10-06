/**
 *	Nino										A compact filesystembased php framework
 *	admin-recoverypw-js-smoke.js	DOM-light checks for the Recovery password tab's script
 *													(_admin/Nino/Modules/Users/assets/recoverypw.js).
 *
 *													The tab is one form: the old recovery password, the new
 *													one twice. What matters is what it sends and when - a
 *													repeat that differs sends nothing - and what it does
 *													with the answer: the three fields are emptied after a
 *													success, and kept after a failure so that a mistyped
 *													old password is typed once.
 *
 *	Usage: node tests/admin-recoverypw-js-smoke.js
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
const moduleDe 		= fills('_admin/Nino/Modules/Users/text/de_DE.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'recoverypw-form';

const requests = [];

// A stand-in for the shell's registry (Nino.admin.dirty): what the tab registers and when it takes a snapshot
const dirty = {
	forms : {},
	snapshots : [],
	watchForm : function( name, getter, save ) { dirty.forms[name] = { getter : getter, save : save } },
	snapshot : function( name ) { dirty.snapshots.push( name ) },
};

const Nino = {
	admin : { dirty : dirty },
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
			return id === 'recoverypw-form' ? mount : findAll( mount, function( el ) { return el.id === id } )[0] ?? null;
		},
		querySelectorAll : function( selector ) { return mount.querySelectorAll( selector.replace( '#recoverypw-form ', '' ) ) },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino };

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
vm.runInContext( source('_admin/Nino/Modules/Users/assets/recoverypw.js'), context, { filename : 'recoverypw.js' } );

const tab = Nino.admin.recoverypw;

/** Answer the last request the tab made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

function field( name ) {
	return findAll( mount, function( el ) { return el.id === 'recoverypw-'+ name } )[0] ?? null;
}

function message() {
	return field('msg') === null ? '' : field('msg').textContent;
}

function type( current, fresh, repeat ) {
	field('current').value = current;
	field('new').value = fresh;
	field('repeat').value = repeat;
}

function submit() {
	fire( findAll( mount, function( el ) { return el.tagName === 'FORM' } )[0], 'submit' );
}

console.log('Admin Recovery password tab');

tab.init();
check( 'opening the tab asks the server for nothing - there is nothing of the server\'s in the form', requests.length === 0 );
check( 'the form has the three password fields, each a password input', [ 'current', 'new', 'repeat' ].every( function( name ) { return field( name ) !== null && field( name ).type === 'password' } ) );
check( '...with the autocomplete hints that keep a password manager from filling the wrong one', field('current').attributes.autocomplete === 'current-password'
	&& field('new').attributes.autocomplete === 'new-password' && field('repeat').attributes.autocomplete === 'new-password' );
check( '...the two new ones with the minimum length the server holds to', field('new').minLength === 8 && field('repeat').minLength === 8 );
check( 'the status line is the action bar\'s own', field('msg') !== null && field('msg').className === 'nino-admin-actionbar-status' );
check( 'the form is registered with the shell\'s dirty registry, and drawn means saved', dirty.forms.recoverypw !== undefined && dirty.forms.recoverypw.getter() === mount && dirty.snapshots.join() === 'recoverypw' );
check( 'the inputs carry the field names the server\'s refusals use', field('current').dataset.field === 'current' && field('new').dataset.field === 'pw' );
check( 'the tab shows again without being rebuilt', ( function() { const before = field('current'); tab.showCurrent(); return field('current') === before && requests.length === 0 } )() );

// A repeat that differs: nothing is sent, and the fields stay as they are
type( 'old secret', 'brand new secret', 'brand new secrat' );
submit();
check( 'a repeat that differs sends nothing', requests.length === 0 );
check( '...says why', message() === text('/_admin/recoverypw/error/mismatch') && text('/_admin/recoverypw/error/mismatch') !== '' );
check( '...and keeps what was typed', field('current').value === 'old secret' && field('new').value === 'brand new secret' );
check( '...a save asked for by the registry says so with done( false )', ( function() { let result = null; dirty.forms.recoverypw.save( function( ok ) { result = ok } ); return result === false && requests.length === 0 } )() );

// The request
type( 'old secret', 'brand new secret', 'brand new secret' );
submit();
check( 'a matching repeat sends the old and the new password to recoverypw/save', requests.length === 1 && requests[0].action === 'recoverypw/save'
	&& JSON.stringify( requests[0].payload ) === JSON.stringify( { current : 'old secret', pw : 'brand new secret' } ) );
check( '...with the save button off while it runs', findAll( mount, function( el ) { return el.type === 'submit' } )[0].disabled === true && message() === text('/_admin/common/msg/saving') );

answer( 401, { error : 'wrong current password', code : 'recoverypw_wrong', params : [], field : 'current' } );
check( 'a 401 shows the words of its code, not the server\'s English', message() === text('/_admin/error/recoverypw_wrong') && text('/_admin/error/recoverypw_wrong') !== '' );
check( '...and marks the old password as the field it is about', field('current').attributes['aria-invalid'] === 'true' );
check( '...keeps all three fields, so the old password is the only thing typed again', field('current').value === 'old secret' && field('new').value === 'brand new secret' && field('repeat').value === 'brand new secret' );
check( '...and gives the button back', findAll( mount, function( el ) { return el.type === 'submit' } )[0].disabled === false );

submit();
answer( 429, { error : 'too many attempts', code : 'recoverypw_locked', params : [] } );
check( 'a 429 shows the words of its code too, and keeps the fields', message() === text('/_admin/error/recoverypw_locked') && text('/_admin/error/recoverypw_locked') !== '' && field('new').value === 'brand new secret' );

submit();
answer( 503, null );
check( 'an answer with no reason falls back to the shared sentence', message() === '(503) '+ text('/_admin/common/error/save') && text('/_admin/common/error/save') !== '' );

submit();
check( 'the retry sends the same three again', requests.length === 4 && requests[3].payload.current === 'old secret' );
const snapshots = dirty.snapshots.length;
answer( 200, { ok : true } );
check( 'a success is the new baseline of the registry', dirty.snapshots.length === snapshots + 1 && dirty.snapshots[snapshots] === 'recoverypw' );
check( 'a success empties all three fields', field('current').value === '' && field('new').value === '' && field('repeat').value === '' );
check( '...says it is saved', message() === text('/_admin/recoverypw/msg/saved') && text('/_admin/recoverypw/msg/saved') !== '' );

// A save asked for by the registry (Save in the question before leaving) reports how it ended
type( 'old secret', 'brand new secret', 'brand new secret' );
let ended = [];
dirty.forms.recoverypw.save( function( ok ) { ended.push( ok ) } );
answer( 401, { error : 'wrong current password', code : 'recoverypw_wrong', params : [], field : 'current' } );
dirty.forms.recoverypw.save( function( ok ) { ended.push( ok ) } );
answer( 200, { ok : true } );
check( 'the registry\'s save reports done( false ) on a failed request and done( true ) on success', ended.join() === 'false,true' );

// Every word is a fill, in both languages
const keys = [ '/_admin/nav/recoverypw', '/_admin/recoverypw/intro', '/_admin/recoverypw/label/current', '/_admin/recoverypw/label/new', '/_admin/recoverypw/label/repeat', '/_admin/recoverypw/msg/saved', '/_admin/recoverypw/error/mismatch', '/_admin/error/recoverypw_wrong', '/_admin/error/recoverypw_password_short', '/_admin/error/recoverypw_locked', '/_admin/error/recoverypw_unset' ];
check( 'every word of the tab is worded in English and in German', keys.every( function( key ) { return typeof moduleEn[key] === 'string' && moduleEn[key] !== '' && typeof moduleDe[key] === 'string' && moduleDe[key] !== '' } ) );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
