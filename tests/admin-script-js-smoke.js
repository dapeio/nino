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

/*	The export's columns are the union of every row's keys, in the order
	they first appear - not the first row's alone. The Submissions panel
	deliberately lists several forms in one view ("All forms"), so the first
	entry's fields are not the file's columns: every field the other forms
	carry and it does not was dropped from the file without a word, and one
	entry recorded before ids existed took 'id' and 'form' down with it for
	every row below	*/
let exported = '';
sandbox.Blob = function( parts ) { exported = parts.join('') };
sandbox.URL = { createObjectURL : function() { return 'blob:x' }, revokeObjectURL : function() {} };
sandbox.document.createElement = function() { return { click : function() {}, remove : function() {} } };
sandbox.document.body = { appendChild : function() {} };

editor.exportCsv( 'submissions.csv', [
	{ id : '1', form : 'contact', name : 'Ada' },
	{ id : '2', form : 'quote', company : 'Acme' },
	{ note : 'written before ids existed' },
] );

check( 'a csv export carries every row\'s columns, not the first row\'s',
	exported === '\uFEFFid,form,name,company,note\r\n1,contact,Ada,,\r\n2,quote,,Acme,\r\n,,,,written before ids existed' );

/*	The rail and its panes, driven for real. Which panel is open was a class
	the stylesheet paints and nothing else, so the navigation read as a run of
	links with nothing to tell them apart; and every pane strip but the one
	the shell happened to open reported a tablist with no selected tab and one
	tab stop per tab.

	A second context rather than more of the one above: onReady() wants a dom,
	and the router checks up there want one that answers nothing	*/
function node( id, attributes ) {
	const classes = new Set();
	const el = {
		id : id || '', hidden : false, tabIndex : 0, focused : false,
		dataset : Object.assign( {}, attributes || {} ),
		attributes : {}, listeners : {}, children : [], parent : null,
		classList : {
			add : function( name ) { classes.add( name ) },
			remove : function( name ) { classes.delete( name ) },
			contains : function( name ) { return classes.has( name ) },
			toggle : function( name, force ) {
				if( force === true ) classes.add( name );
				else if( force === false ) classes.delete( name );
				else if( classes.has( name ) ) classes.delete( name );
				else classes.add( name );
				return classes.has( name );
			},
			forEach : function( fn ) { Array.from( classes ).forEach( fn ) },
			[Symbol.iterator] : function() { return classes[Symbol.iterator]() },
		},
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		removeAttribute : function( name ) { delete el.attributes[name] },
		getAttribute : function( name ) { return el.attributes[name] ?? null },
		addEventListener : function( name, fn ) { el.listeners[name] = fn },
		focus : function() { el.focused = true },
		closest : function() { return el.parent === null ? null : ( el.parent.dataset.panel !== undefined ? el.parent : el.parent.closest() ) },
		querySelectorAll : function( selector ) {
			if( selector === ':scope > div[data-tab]' )
				return el.children.filter( function( c ) { return c.dataset.tab !== undefined && c.isStrip !== true } );
			if( selector === ':scope > button[data-tab]' )
				return el.children.filter( function( c ) { return c.dataset.tab !== undefined } );
			if( selector === ':scope > .admin-panel-tabs > button[data-tab]' )
				return el.children.filter( function( c ) { return c.isStrip === true } ).flatMap( function( strip ) { return strip.children } );
			return [];
		},
	};
	return el;
}

const railLinks = { dashboard : node( 'admin-nav-dashboard', { panel : 'dashboard', layout : 'page' } ),
                    users 		: node( 'admin-nav-users', { panel : 'users', layout : 'page' } ) };

const rolesTab = node( 'admin-tabbutton-roles', { tab : 'roles' } );
const lockoutTab = node( 'admin-tabbutton-lockout', { tab : 'lockout' } );
const strip = node( '', {} );
strip.isStrip = true;
strip.children = [ rolesTab, lockoutTab ];
rolesTab.parent = strip;
lockoutTab.parent = strip;

const rolesPane = node( 'admin-tab-roles', { tab : 'roles' } );
const lockoutPane = node( 'admin-tab-lockout', { tab : 'lockout' } );
const usersPane = node( 'admin-content-users', { panel : 'users', layout : 'page' } );
usersPane.children = [ strip, rolesPane, lockoutPane ];
strip.parent = usersPane;

const dashboardPane = node( 'admin-content-dashboard', { panel : 'dashboard', layout : 'page' } );
const pageWrap = node( 'admin-page-wrap', {} );
const shellNodes = {
	'admin-page-wrap' : pageWrap,
	'admin-user-logout' : node( 'admin-user-logout', {} ),
	'admin-content-dashboard' : dashboardPane,
	'admin-content-users' : usersPane,
};

const shell = {
	console : console,
	location : { hash : '#users' },
	history : { replaceState : function() {} },
	addEventListener : function() {},
	document : {
		documentElement : null, body : null,
		getElementById : function( id ) { return shellNodes[id] ?? null },
		addEventListener : function() {},
		querySelectorAll : function( selector ) {
			if( selector === '#admin-nav-wrap a[data-panel]' )
				return [ railLinks.dashboard, railLinks.users ];
			if( selector === '#admin-content-wrap > [data-panel]' )
				return [ dashboardPane, usersPane ];
			if( selector === '#admin-content-wrap > [data-panel] > .admin-panel-tabs' )
				return [ strip ];
			return [];
		},
	},
};
shell.window = shell;
shell.Nino = { events : { bindCallback : function() {} } };

const shellContext = vm.createContext( shell );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ), shellContext, { filename : 'Nino.admin.js' } );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/script.js' ), 'utf8' ), shellContext, { filename : 'script.js' } );
shell.Nino.admin.onReady();

check( 'the rail says which panel is open rather than only painting it', railLinks.users.getAttribute('aria-current') === 'page' );
check( '...and no other link claims to be the current page', railLinks.dashboard.getAttribute('aria-current') === null );
railLinks.dashboard.listeners.click( { preventDefault : function() {} } );
check( 'the mark moves with the panel', railLinks.dashboard.getAttribute('aria-current') === 'page' && railLinks.users.getAttribute('aria-current') === null );

check( 'a pane strip starts with exactly one tab selected and one tab stop', rolesTab.getAttribute('aria-selected') === 'true'
	&& lockoutTab.getAttribute('aria-selected') === 'false' && rolesTab.tabIndex === 0 && lockoutTab.tabIndex === -1 );

rolesTab.listeners.keydown( { key : 'ArrowRight', preventDefault : function() {} } );
check( 'an arrow key opens the next tab of a pane strip', lockoutTab.getAttribute('aria-selected') === 'true'
	&& lockoutPane.hidden === false && rolesPane.hidden === true && lockoutTab.focused === true );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
