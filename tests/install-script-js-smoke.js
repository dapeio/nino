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
	fs.readFileSync( path.join( __dirname, '../_install/assets/script.js' ), 'utf8' ),
	context,
	{ filename : 'script.js' }
);

const install = sandbox.Nino.install;

/*	Frames before Design, because that is the direction the dependency runs: the
	Design step previews a whole page inside the project's own header and footer,
	so the frame decides its layout - a rail down the side is a different page
	from a bar across the top. The frame steps only preview the frame itself,
	whose structure the Design does not touch. Themes::apiApply() writes them in
	this order for the same reason.	*/
const stepKeys = install.STEPS.map( function( step ) { return step.key; } );
check( 'Header and Footer are separate steps, and both come before Design', stepKeys.join(',') === 'checks,setup,themes,header,footer,design,webpages,personalinfos,admin,finish' );

// The rail is read top to bottom, so it has to number the steps in the order
// they actually run
const wizardNav = fs.readFileSync( path.join( __dirname, '../_install/templates/page-wizard.tpl' ), 'utf8' );
check( '...and the rail numbers them in that order', stepKeys.every( function( key, index ) {
	return new RegExp( 'id="install-nav-'+ key+ '"[^>]*>'+ ( index + 1 )+ '\\.' ).test( wizardNav );
} ) );

let frameCommit = [];
install.header = { apply : function( callback ) { frameCommit.push('header'); callback( true ); } };
install.footer = { apply : function( callback ) { frameCommit.push('footer'); callback( true ); } };
install._commitStep( 'header', function() {} );
install._commitStep( 'footer', function() {} );
check( 'Next dispatches each new frame step to its own apply action', frameCommit.join(',') === 'header,footer' );

const wizardTemplate = fs.readFileSync( path.join( __dirname, '../_install/templates/page-wizard.tpl' ), 'utf8' );
check( 'the wizard template carries matching nav and content pairs for both tabs', wizardTemplate.includes('id="install-nav-header"')
	&& wizardTemplate.includes('id="install-content-header"')
	&& wizardTemplate.includes('id="install-nav-footer"')
	&& wizardTemplate.includes('id="install-content-footer"')
	&& wizardTemplate.includes('id="themes-frames"') === false );

const installStyle = fs.readFileSync( path.join( __dirname, '../_install/assets/style.css' ), 'utf8' );
check( 'the isolated frame previews use the taller viewport', installStyle.includes('--frame-height: 36rem;') );

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
	fs.readFileSync( path.join( __dirname, '../_install/assets/webpages.js' ), 'utf8' ),
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
