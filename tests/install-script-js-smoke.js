/**
 *	Nino							A compact filesystembased php framework
 *	install-script-js-smoke.js	DOM-light checks for wizard navigation and
 *								open Webpage form commits.
 *
 *	Usage: node tests/install-script-js-smoke.js
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

function classList() {
	const values = new Set();
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
		toggle : function( value, force ) {
			if( force === true ) values.add( value );
			else if( force === false ) values.delete( value );
			else if( values.has( value ) ) values.delete( value );
			else values.add( value );
		},
	};
}

const elements = {
	'install-back' : { disabled : false, classList : classList() },
	'install-next' : { disabled : false, classList : classList() },
	'install-actions-msg' : { textContent : '' },
	'webpages-msg' : { textContent : '' },
};

const sandbox = {
	console : console,
	document : {
		documentElement : null,
		body : null,
		getElementById : function( id ) { return elements[id] ?? null },
	},
};
sandbox.window = sandbox;
sandbox.Nino = { events : { bindCallback : function() {} } };

const context = vm.createContext( sandbox );
vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/install/assets/script.js' ), 'utf8' ),
	context,
	{ filename : 'script.js' }
);

const install = sandbox.Nino.install;

/*	The wizard installs a site, it does not style one. Since 1.2 the look is
	the fixed theme base delivers - assets/theme.css, written by the base unit
	along with the header and footer templates it is drawn against - so the four
	steps that used to pick a theme, a header, a footer and a palette are gone
	from the wizard, and the Design feature is where that choice comes back. */
const stepKeys = install.STEPS.map( function( step ) { return step.key; } );
check( 'the wizard is the six steps that install a site', stepKeys.join(',') === 'checks,setup,webpages,personalinfos,accounts,finish' );

// The rail is read top to bottom, so it has to number the steps in the order
// they actually run
const wizardNav = fs.readFileSync( path.join( __dirname, '../_admin/install/templates/page-wizard.tpl' ), 'utf8' );
check( '...and the rail numbers them in that order', stepKeys.every( function( key, index ) {
	return new RegExp( 'id="install-nav-'+ key+ '"[^>]*>'+ ( index + 1 )+ '\\.' ).test( wizardNav );
} ) );
check( '...and numbers nothing beyond them', ( wizardNav.match( /<span id="install-nav-/g ) || [] ).length === stepKeys.length );

/*	A step is a nav span, a content pane, a script tag and a _commitStep branch.
	Removing one means removing all four: a leftover pane is dead markup the
	pane-class switcher can never show, and a leftover branch dispatches to a
	module the page no longer loads. */
const wizardTemplate = fs.readFileSync( path.join( __dirname, '../_admin/install/templates/page-wizard.tpl' ), 'utf8' );
const wizardScript 	= fs.readFileSync( path.join( __dirname, '../_admin/install/assets/script.js' ), 'utf8' );
const installStyle 	= fs.readFileSync( path.join( __dirname, '../_admin/install/assets/style.css' ), 'utf8' );
check( 'every step the rail names has its content pane', stepKeys.every( function( key ) {
	return wizardTemplate.includes( 'id="install-content-'+ key+ '"' );
} ) );
check( '...and the four appearance steps left nothing of themselves behind', [ 'themes', 'header', 'footer', 'design' ].every( function( key ) {
	return wizardTemplate.includes( 'install-nav-'+ key ) === false
		&& wizardTemplate.includes( 'install-content-'+ key ) === false
		&& wizardTemplate.includes( 'assets/'+ key+ '.js' ) === false
		&& wizardScript.includes( "'"+ key+ "'" ) === false
		&& installStyle.includes( 'show-'+ key ) === false;
} ) );
check( '...and neither did the theme lightbox', wizardTemplate.includes('themes-lightbox') === false && installStyle.includes('themes-lightbox') === false );

install._setBusy( true );
check( 'a pending commit disables both shared navigation buttons', elements['install-back'].disabled === true && elements['install-next'].disabled === true );

let shown = null;
install.showStep = function( index ) { shown = index; install._index = index };
install._index = 2;
install.back();
check( 'Back cannot move the wizard while a commit is pending', shown === null );

install._setBusy( false );
let beforeLeaveCalls = 0;
install.webpages = { beforeLeave : function() { beforeLeaveCalls++; return false } };
// Looked up rather than hardcoded: a step inserted ahead of Routes would
// otherwise turn this into a test of whichever step landed on index 3
const webpagesIndex = install.STEPS.findIndex( function( step ) { return step.key === 'webpages' } );
install._index = webpagesIndex;
install.back();
check( 'a step can stop Back when its open editor is invalid', beforeLeaveCalls === 1 && shown === null );

install.webpages.beforeLeave = function() { beforeLeaveCalls++; return true };
install.back();
check( 'Back advances only after the current step has preserved its local state', beforeLeaveCalls === 2 && shown === webpagesIndex - 1 );

let commitCallback = null;
install._index = 1;
install._commitStep = function( key, callback ) { commitCallback = callback };
install.next();
check( 'Next enters the busy state before waiting for its asynchronous commit', install._busy === true && typeof commitCallback === 'function' );

// Simulate an unrelated index mutation: the callback must still advance from
// the step that started the request, not from this later value.
install._index = 0;
commitCallback( true );
check( 'a successful callback advances from its captured starting index', shown === 2 );
check( 'navigation unlocks after the commit callback', install._busy === false && elements['install-back'].disabled === false && elements['install-next'].disabled === false );

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/install/assets/webpages.js' ), 'utf8' ),
	context,
	{ filename : 'webpages.js' }
);

const webpages = sandbox.Nino.install.webpages;
let apiCalls = 0;
let callbackResult = null;
webpages.beforeLeave = function() { return true };
sandbox.Nino.install.apiCall = function() { apiCalls++ };
webpages.apply( function( ok ) { callbackResult = ok } );
check( 'Next cannot post an empty list before Webpages has finished loading', apiCalls === 0 && callbackResult === false );

webpages._ready = true;
webpages.beforeLeave = function() { return false };
sandbox.Nino.install.apiCall = function() { apiCalls++ };
webpages.apply( function( ok ) { callbackResult = ok } );
check( 'Next does not post an older page list while the open form is invalid', apiCalls === 0 && callbackResult === false );

webpages.beforeLeave = function() { return true };
sandbox.Nino.install.apiCall = function( action, payload, callback ) {
	apiCalls++;
	callback( 200, { webpages : payload.webpages } );
};
webpages.apply( function( ok ) { callbackResult = ok } );
check( 'a preserved open form is included before the page list is posted', apiCalls === 1 && callbackResult === true );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
