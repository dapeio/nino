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

// A rail span carries what step the wizard is on twice over: the class the
// stylesheet paints, and the attribute a screen reader reads
function navSpan() {
	const node = { classList : classList(), attributes : {} };
	node.setAttribute = function( name, value ) { node.attributes[name] = String( value ) };
	node.removeAttribute = function( name ) { delete node.attributes[name] };
	node.getAttribute = function( name ) { return node.attributes[name] ?? null };
	return node;
}

const elements = {
	'install-back' : { disabled : false, classList : classList() },
	'install-next' : { disabled : false, classList : classList() },
	'install-actions-msg' : { textContent : '' },
	'webpages-msg' : { textContent : '' },
	'install-page-wrap' : { classList : classList() },
};
[ 'checks', 'setup', 'webpages', 'personalinfos', 'accounts', 'finish' ].forEach( function( key ) {
	elements['install-nav-'+ key] = navSpan();
} );

// A recording element, for the parts of a step that build their own dom
function recorder( tag ) {
	const el = {
		tagName : String( tag ).toUpperCase(), className : '', textContent : '', value : '',
		type : '', placeholder : '', rows : 0,
		dataset : {}, attributes : {}, children : [], listeners : {},
		classList : classList(),
		appendChild : function( child ) { el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		getAttribute : function( name ) { return el.attributes[name] ?? null },
		addEventListener : function( name, fn ) { el.listeners[name] = fn },
	};
	return el;
}

const sandbox = {
	console : console,
	document : {
		documentElement : null,
		body : null,
		getElementById : function( id ) { return elements[id] ?? null },
		createElement : recorder,
	},
};
sandbox.window = sandbox;
sandbox.Nino = {
	events 	: { bindCallback : function() {} },
	// showStep() swaps the pane class through the shared primitive
	adminUi	: { setStateClass : function() {} },
};

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

/*	The rail is a progress display, and the progress was shown by the colour
	of one of six words and nothing else - so an operator who cannot see that
	colour has no way of reading which of the six steps the wizard is on.
	Driven rather than read: what matters is that the mark moves with the
	step and that it never sits on two of them at once	*/
const navOf = function( key ) { return elements['install-nav-'+ key] };
install.showStep( 0 );
check( 'the wizard says which step it is on, not only paints it', navOf('checks').getAttribute('aria-current') === 'step' );
check( '...and no other step claims to be the current one', stepKeys.slice( 1 ).every( function( key ) { return navOf( key ).getAttribute('aria-current') === null } ) );
install.showStep( 2 );
check( 'the mark moves with the step', navOf('webpages').getAttribute('aria-current') === 'step' && navOf('checks').getAttribute('aria-current') === null );
check( '...and the class the stylesheet paints moves with it', navOf('webpages').classList.contains('active') === true && navOf('checks').classList.contains('active') === false );
install.showStep( 0 );

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

/*	A route's per-locale row is a grid of a locale code and three boxes, with
	no room for a label over each of them - so all three carried a placeholder
	and nothing else. A placeholder is not a name: it is gone on the first
	keystroke and never reaches the accessibility tree as one, so a step with
	four locales on it offered twelve boxes a screen reader could only call
	"edit text". The locale is part of each name, because "Name" four times
	over says nothing about which language it is the name in	*/
const localeRow = webpages._localeRow( 'de_DE', { name : 'Start' } );
const boxes = localeRow.children.filter( function( child ) { return child.tagName === 'INPUT' || child.tagName === 'TEXTAREA' } );
check( 'a locale row draws its three boxes', boxes.length === 3 && boxes.map( function( b ) { return b.dataset.field } ).join(',') === 'name,title,description' );
check( '...each of them named, and named for its locale', boxes.every( function( b ) { return ( b.getAttribute('aria-label') || '' ).endsWith(' (de_DE)') } )
	&& boxes[0].getAttribute('aria-label') === 'Name (de_DE)' );
check( '...with the placeholder still there as the hint it always was, and the value untouched',
	boxes[0].placeholder === 'Name e.g. "Home"' && boxes[0].value === 'Start' && boxes[1].value === '' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
