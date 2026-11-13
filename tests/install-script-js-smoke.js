/**
 *	Nino							A compact filesystembased php framework
 *	install-script-js-smoke.js	DOM-light checks for the wizard's step list, the rail and
 *								the Back and Next of the shared script.
 *
 *	Usage: node tests/install-script-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
const vm 		= require('vm');
const { check, done, load, classList, element } = require( './harness.js' );

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
	'applications-msg' : { textContent : '' },
	'install-page-wrap' : { classList : classList() },
};
[ 'checks', 'setup', 'personalinfos', 'accounts', 'applications', 'finish' ].forEach( function( key ) {
	elements['install-nav-'+ key] = navSpan();
} );

const sandbox = {
	console : console,
	document : {
		documentElement : null,
		body : null,
		getElementById : function( id ) { return elements[id] ?? null },
		createElement : element,
	},
};
sandbox.window = sandbox;
sandbox.Nino = {
	events 	: { bindCallback : function() {} },
	// showStep() swaps the pane class through the shared primitive
	adminUi	: { setStateClass : function() {} },
};

const context = vm.createContext( sandbox );
load( '_admin/install/assets/script.js', context );

const install = sandbox.Nino.install;

/*	The wizard installs a site, it does not style one. Since 1.2 the look is
	the fixed theme base delivers - assets/theme.css, written by the base unit
	along with the header and footer templates it is drawn against - so the four
	steps that used to pick a theme, a header, a footer and a palette are gone
	from the wizard, and the Design feature is where that choice comes back. */
const stepKeys = install.STEPS.map( function( step ) { return step.key; } );
check( 'the wizard is the six steps that install a site', stepKeys.join(',') === 'checks,setup,personalinfos,accounts,applications,finish' );

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
check( 'the mark moves with the step', navOf('personalinfos').getAttribute('aria-current') === 'step' && navOf('checks').getAttribute('aria-current') === null );
check( '...and the class the stylesheet paints moves with it', navOf('personalinfos').classList.contains('active') === true && navOf('checks').classList.contains('active') === false );
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
install._index = 3;
install.back();
check( 'Back moves one step towards the first', shown === 2 );
install._index = 0;
shown = null;
install.back();
check( '...and not before it', shown === null );

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

/*	The bar carries one primary action: Next. Two buttons painted as primary on the
	same bar leave the eye without an answer to which one moves the wizard on	*/
check( 'the shared bar has exactly one primary action, Next', ( wizardTemplate.match( /nino-admin-btn-primary/g ) || [] ).length === 1
	&& /id="install-next" class="nino-admin-btn-primary"/.test( wizardTemplate ) === true );

/*	The step for applications has its four parts, as every step has: the rail, the pane, the script and the
	branch of the shared script that commits it	*/
check( 'the step for applications has its four: rail, pane, script and branch', wizardTemplate.includes( 'id="install-nav-applications"' ) && wizardTemplate.includes( 'id="install-content-applications"' )
	&& wizardTemplate.includes( 'assets/applications.js' ) && wizardScript.includes( "key === 'applications'" ) );
/*	A name the catalogue wrote may have no place to break: what shows one wraps it, or the page grows wide.
	By rule and by selector, in the stylesheet of the wizard	*/
const wrapping = ( installStyle.match( /([^{}]+)\{[^{}]*overflow-wrap:\s*anywhere[^{}]*\}/g ) || [] ).join( ' ' );
check( 'the button that ends the wizard, the links of the next steps, the rows of the choice and of the progress wrap a name that has no place to break',
	[ '#finish-submit', '.install-next-step-copy', '.install-app-copy', '.install-check-row-name' ].every( function( selector ) { return wrapping.includes( selector ) } ) );
check( 'the script tags of the template are the steps\' modules and nothing else', ( wizardTemplate.match( /install\/assets\/([a-z]+)\.js/g ) || [] ).map( function( src ) { return src.replace( /^.*\/|\.js$/g, '' ) } ).join( ',' ) === 'script,checks,setup,personalinfos,accounts,applications,finish' );

done();
