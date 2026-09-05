/**
 *	Nino									A compact filesystembased php framework
 *	admin-script-js-smoke.js	DOM-free checks for shared editor routing
 *										and CSV safety helpers.
 *
 *	Usage: node tests/admin-script-js-smoke.js
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
	location : { hash : '' },
	history : { replaceState : function() {} },
	// The shell rendered one pane (users) holding one tab pane (roles) - the
	// DOM is the router's list of panels, see router.exists()
	document : { documentElement : null, body : null, getElementById : function( id ) { return [ 'admin-content-users', 'admin-tab-roles' ].indexOf( id ) !== -1 ? {} : null } },
};
sandbox.window = sandbox;
sandbox.Nino = {
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/assets/script.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'script.js' }
);

const editor = sandbox.Nino.admin;

sandbox.location.hash = '#elements/demo%20type/item';
let route = editor.router.current();
check( 'valid hash components are decoded', route.panel === 'elements' && route.parts[0] === 'demo type' && route.parts[1] === 'item' );

sandbox.location.hash = '#elements/bad%hash';
route = editor.router.current();
check( 'a malformed percent escape falls back safely', route.panel === '' && route.parts.length === 0 );

check( 'a pane the shell rendered is a panel the router knows', editor.router.exists('users') === true );
check( 'a tab of a pane is a panel of its own to the router', editor.router.exists('roles') === true );
check( 'a name without a pane, or one that is not a slug, is no panel', editor.router.exists('nope') === false && editor.router.exists('Roles') === false && editor.router.exists( 42 ) === false );

editor.decodeEntities = function( value ) { return value };
check( 'a leading equals sign is exported as explicit text', editor.csvCell('=2+2') === "'=2+2" );
check( 'a formula marker hidden behind whitespace is neutralized too', editor.csvCell(' \t+SUM(A1:A2)') === "' \t+SUM(A1:A2)" );
check( 'a regular value is unchanged', editor.csvCell('hello') === 'hello' );
check( 'CSV quoting still applies after neutralization', editor.csvCell('=1,2') === '"\'=1,2"' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
