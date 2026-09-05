/**
 *	Nino									A compact filesystembased php framework
 *	admin-text-js-smoke.js	DOM-free checks for the Text editor's
 *										multi-locale save planning, plus the Text Keys
 *										tab's scan form - which answers three questions
 *										per key and must send all three, or a row the
 *										operator meant to retire quietly comes back.
 *
 *	Usage: node tests/admin-text-js-smoke.js
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

const sandbox = {
	console : console,
	document : { documentElement : null, body : null },
};
sandbox.window = sandbox;
sandbox.Nino = {
	editor : {},
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/assets/admin.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'text.js' }
);

const text = sandbox.Nino.admin.text;

text._dirtyLocales = [ 'de_DE', 'en_US' ];
text._selectedLocale = 'en_US';
check( 'Save includes every locale edited before the visible one', JSON.stringify( text._saveLocales() ) === JSON.stringify( [ 'de_DE', 'en_US' ] ) );

text._dirtyLocales = [];
check( 'the visible locale carries a global-only save', JSON.stringify( text._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );

text._dirtyLocales = [ 'en_US' ];
check( 'the visible locale is not queued twice', JSON.stringify( text._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );


// --- the scan form's three answers ----------------------------------------
//
// A row is created (it has a value), left for later (it is empty) or retired
// (it is ticked). Which one is the server's call, so the request has to carry
// every row - the version before this filtered the ignored ones out client-
// side, which is exactly why an ignored key came back on the next scan.

const keysSandbox = {
	console : console,
	document : { documentElement : null, body : null, getElementById : function() { return { textContent : '' } } },
};
keysSandbox.window = keysSandbox;
keysSandbox.Nino = {
	admin : {},
	content : { getText : function( key ) { return key } },
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/assets/keys.js' ), 'utf8' ),
	vm.createContext( keysSandbox ),
	{ filename : 'keys.js' }
);

const keys = keysSandbox.Nino.admin.keys;

let sent = null;
keys._apiCall = function( endpoint, payload ) { sent = { endpoint : endpoint, payload : payload } };

keys._saveScanResults( [
	{ key : '/a/one', 	valueInput : { value : 'Value' }, ignoreCheck : { checked : false } },
	{ key : '/a/two', 	valueInput : { value : '' }, 			ignoreCheck : { checked : false } },
	{ key : '/a/three', valueInput : { value : '' }, 			ignoreCheck : { checked : true 	} },
] );

check( 'the whole form goes to the server in one request', sent !== null && sent.endpoint === 'scanapply' && sent.payload.rows.length === 3 );
check( '...including the rows left empty, which the server passes over', sent.payload.rows[1].key === '/a/two' && sent.payload.rows[1].value === '' && sent.payload.rows[1].ignore === false );
check( '...and the ignored ones, which is how they get retired at all', sent.payload.rows[2].key === '/a/three' && sent.payload.rows[2].ignore === true );
check( 'a filled row carries its value', sent.payload.rows[0].value === 'Value' );

const keysSource = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/assets/keys.js' ), 'utf8' );
// Three outcomes are not obvious from three controls, so the form says them
check( 'the form explains what an empty row and an ignored row each mean',
	keysSource.includes( "Nino.content.getText('/_admin/keys/scan/hint')" ) );
check( '...and the checkbox says the ignore is permanent',
	keysSource.includes( "Nino.content.getText('/_admin/keys/label/scan-ignore')" ) );
// The old flow ended in an alert() built from a hardcoded English sentence -
// the one string in this module that no locale file could reach
check( 'the outcome is reported as a fill rather than an untranslatable alert',
	keysSource.includes( "Nino.content.getText('/_admin/keys/scan/result')" ) && keysSource.includes( "' created. Failed: '" ) === false );


// --- a key this account may not write -------------------------------------
//
// apiKeys() flags each key; the panel shows what it may not change as
// read-only. The flag is absent for anyone unrestricted, so "not false" is
// the test - "=== true" would lock every key of every install that has no
// scoped permissions at all.

check( 'a key with no flag is writable', text._writable( { key : '/a/one' } ) === true );
check( 'a key flagged writable is writable', text._writable( { key : '/a/one', writable : true } ) === true );
check( 'a key flagged unwritable is not', text._writable( { key : '/a/one', writable : false } ) === false );

const textSource = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/assets/admin.js' ), 'utf8' );
check( 'a read-only key is shown rather than hidden',
	textSource.includes( "view.className = 'admin-text-readonly'" ) );
// Snapshotting a field that isn't there reads '' back and would overwrite the
// real value on the next locale switch
check( 'read-only keys are left out of the locale snapshot and the save',
	textSource.split( '&& Nino.admin.text._writable( e ) === true' ).length === 4 );
// ...and nowhere else: filtering them out of the render too would hide the
// text a writable field is being edited next to, which is the point of
// editing a whole category at once
check( '...but still rendered, so the group reads as one list',
	textSource.includes( 'const globalEntries = entries.filter( function( e ) { return e.global === true } );' )
	&& textSource.includes( 'const localeEntries = entries.filter( function( e ) { return e.global === false } );' ) );
check( 'a group with nothing writable in it is not offered a Save button',
	textSource.includes( 'if( writable === true ) {' ) );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
