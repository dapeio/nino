/**
 *	Nino									A compact filesystembased php framework
 *	nino-ui-nav-js-smoke.js	What the script does for the controls that fold a
 *													part of the page in and out, and for the names of the
 *													dialogs: the burger menu (Modules\Navigation) opens and
 *													closes on its button and takes the page behind it out of
 *													the reach of the keyboard while it is open, Escape closes
 *													it and the language picker (Modules\Localepicker) and
 *													hands the focus back, and a dialog is named by what it
 *													holds.
 *
 *	Usage: node tests/nino-ui-nav-js-smoke.js
 */

'use strict';

const vm = require('vm');
const fs = require('fs');
const path = require('path');
const { check, done, load, element, fire } = require( './harness.js' );

/**
 *	An element with its attributes and children, put together
 *
 *	@param		{string}			tag
 *	@param		{Object}			attributes		Name => value, id and class included
 *	@param		{Object[]}		children
 *
 *	@return		{Object}
 */
function node( tag, attributes, children ) {

	const el = element( tag );

	Object.keys( attributes || {} ).forEach( function( name ) { el.setAttribute( name, attributes[name] ) } );
	( children || [] ).forEach( function( child ) { el.appendChild( child ) } );

	return el;
}

// The page: a skip link, a header with the logo and a burger, the main part and the footer
const link = node( 'a', { href : '/contact' } );
const toggle = node( 'button', { type : 'button', class : 'nino-nav-toggle', 'aria-expanded' : 'false', 'aria-controls' : 'menu-list', hidden : '' } );
toggle.hidden = true;
const list = node( 'div', { class : 'nino-nav-content', id : 'menu-list' }, [ node( 'ul', {}, [ node( 'li', {}, [ link ] ) ] ) ] );
const burger = node( 'div', { class : 'nino-nav-wrap nino-nav-burger' }, [ toggle, node( 'div', { class : 'nino-nav-bg' } ), list ] );
const logo = node( 'a', { href : '/', class : 'nino-logo' } );
const header = node( 'header', {}, [ logo, node( 'nav', {}, [ burger ] ) ] );
const skip = node( 'a', { href : '#nino-main' } );
const main = node( 'main', { id : 'nino-main', tabindex : '-1' } );
const footer = node( 'footer' );

// The language picker in the footer: a checkbox the overlay hangs on, and a link in the list
const pickerLink = node( 'a', { href : '?/_nino/localepicker/current=de_DE' } );
const pickerBox = node( 'input', { type : 'checkbox' } );
const picker = node( 'div', { class : 'nino-localepicker-wrap' }, [ node( 'label', {}, [ pickerBox, node( 'div', {}, [ node( 'ul', {}, [ node( 'li', {}, [ pickerLink ] ) ] ) ] ) ] ) ] );
footer.appendChild( picker );

// Three dialogs that have a name to be given, one that has one already and one that has nothing to be named by
const heading = node( 'h3' );
heading.textContent = 'Terms';
const dialogWithHeading = node( 'dialog', { class : 'nino-modal' }, [ heading ] );
const dialogWithPicture = node( 'dialog', { class : 'nino-modal nino-modal--lightbox' }, [ node( 'img', { src : 'a.jpg', alt : 'A harbour at dawn' } ) ] );
const dialogWithVideo = node( 'dialog', { class : 'nino-modal nino-modal--video' }, [ node( 'iframe', { src : 'v', title : 'The film' } ) ] );
const dialogNamed = node( 'dialog', { class : 'nino-modal', 'aria-label' : 'Already named' }, [ node( 'h3' ) ] );
const dialogEmpty = node( 'dialog', { class : 'nino-modal' }, [ node( 'p' ) ] );
const dialogs = [ dialogWithHeading, dialogWithPicture, dialogWithVideo, dialogNamed, dialogEmpty ];

const body = node( 'body', {}, [ skip, header, main, footer ].concat( dialogs ) );
const everything = [ skip, logo, header, main, footer, burger, list, link, picker, pickerLink, toggle, pickerBox ];

const documentListeners = {};
const windowListeners = {};
let computedDisplay = 'flex';

const document = {
	body : body,
	documentElement : { clientHeight : 640, clientWidth : 390, scrollLeft : 0, scrollTop : 0, style : {} },
	getElementById : function() { return null },
	createElement : element,
	querySelectorAll : function( selector ) {
		if( selector === '.nino-nav-burger' ) return [ burger ];
		if( selector === '.nino-localepicker-wrap' ) return [ picker ];
		if( selector === '.nino-localepicker-wrap input' ) return [ pickerBox ];
		if( selector === '.nino-modal' ) return dialogs;
		return [];
	},
	addEventListener : function( name, fn ) { ( documentListeners[name] = documentListeners[name] || [] ).push( fn ) },
};
Object.defineProperty( document, 'activeElement', { get : function() { return everything.find( function( el ) { return el.focused === true } ) ?? null } } );

const sandbox = {
	console : console,
	document : document,
	innerHeight : 640,
	innerWidth : 390,
	location : { hash : '' },
	requestAnimationFrame : function() {},
	setTimeout : function() {},
	addEventListener : function( name, fn ) { ( windowListeners[name] = windowListeners[name] || [] ).push( fn ) },
	getComputedStyle : function() { return { display : computedDisplay } },
};
sandbox.window = sandbox;
sandbox.Nino = {
	client : { isMobile : true },
	events : { bindCallback : function() {} },
};

load( '_nino/Nino.ui.js', vm.createContext( sandbox ) );
sandbox.Nino.ui.onReady();

/**
 *	@param		{Object}		el
 *
 *	@return		{boolean}									Whether the element is inert
 */
function inert( el ) {
	return el.hasAttribute( 'inert' );
}

/**
 *	Escape, as the document hears it
 *
 *	@param		{boolean}		prevented			Whether something else took the key already
 *
 *	@return		{void}
 */
function escape( prevented ) {
	( documentListeners.keydown || [] ).forEach( function( fn ) { fn( { key : 'Escape', defaultPrevented : prevented === true } ) } );
}

// --- the burger ------------------------------------------------------------

check( 'the button that the markup carries hidden is shown once the script runs, and says the menu is closed',
	toggle.hidden === false && toggle.getAttribute( 'aria-expanded' ) === 'false' && burger.classList.contains( 'nino-is-open' ) === false );

fire( toggle, 'click' );
check( 'pressing it opens the menu: aria-expanded says so and the wrapper carries a class for it',
	toggle.getAttribute( 'aria-expanded' ) === 'true' && burger.classList.contains( 'nino-is-open' ) === true );
check( '...and what is not the menu cannot be reached while it is open: the skip link, the logo, the main part and the footer are inert, the menu and the button are not',
	[ skip, logo, main, footer ].every( inert ) === true && [ header, burger, list, link, toggle ].some( inert ) === false && inert( body ) === false );

fire( toggle, 'click' );
check( 'pressing it again closes the menu, and the page is reachable again',
	toggle.getAttribute( 'aria-expanded' ) === 'false' && burger.classList.contains( 'nino-is-open' ) === false && [ skip, logo, main, footer ].some( inert ) === false );

footer.setAttribute( 'inert', '' );
fire( toggle, 'click' );
fire( toggle, 'click' );
check( 'what was inert before the menu opened stays inert when it closes: only what the menu made inert is given back',
	inert( footer ) === true && inert( main ) === false );
footer.removeAttribute( 'inert' );

toggle.dispatchEvent( { type : 'click', bubbles : true } );
check( 'a press on the button reaches the wrapper too and does not close what it just opened', toggle.getAttribute( 'aria-expanded' ) === 'true' );

link.focus();
fire( burger, 'click', { target : link } );
check( 'a click on a link in the open menu closes it, and the focus stays where the click took it',
	toggle.getAttribute( 'aria-expanded' ) === 'false' && toggle.focused === false && inert( main ) === false );

fire( toggle, 'click' );
link.focus();
escape( true );
check( 'Escape that another handler took already leaves the menu open', toggle.getAttribute( 'aria-expanded' ) === 'true' );
escape( false );
check( 'Escape closes the menu and hands the focus to the button that opened it',
	toggle.getAttribute( 'aria-expanded' ) === 'false' && toggle.focused === true && inert( main ) === false && burger.classList.contains( 'nino-is-open' ) === false );

link.focus();
escape( false );
check( 'Escape with nothing open does nothing: the focus stays where it is', link.focused === true && toggle.getAttribute( 'aria-expanded' ) === 'false' );

fire( toggle, 'click' );
computedDisplay = 'flex';
( windowListeners.resize || [] ).forEach( function( fn ) { fn( {} ) } );
check( 'a viewport that stays narrow leaves the open menu open', toggle.getAttribute( 'aria-expanded' ) === 'true' );
computedDisplay = 'none';
( windowListeners.resize || [] ).forEach( function( fn ) { fn( {} ) } );
check( '...and one that becomes wide - where the button is gone, and nothing is left to close the menu with - closes it and gives the page back',
	toggle.getAttribute( 'aria-expanded' ) === 'false' && [ skip, logo, main, footer ].some( inert ) === false );

// --- what the stylesheet does with the lock ---------------------------------

const css = fs.readFileSync( path.join( __dirname, '../_nino/Nino.css' ), 'utf8' );
const lockRule = ( css.match( /^html:has\(\.nino-nav-burger > button\[aria-expanded="true"\]\)\s*\{([^}]*)\}/m ) || [ '', '' ] )[1];
check( 'while the button of any burger says aria-expanded the document does not scroll: html:has(.nino-nav-burger > button[aria-expanded="true"]) says overflow hidden and a stable scrollbar gutter',
	/overflow:\s*hidden\s*;/.test( lockRule ) && /scrollbar-gutter:\s*stable\s*;/.test( lockRule ));
const openRule = ( css.match( /^\.nino-nav-burger > button\[aria-expanded="true"\] ~ \.nino-nav-content\s*\{([^}]*)\}/m ) || [ '', '' ] )[1];
check( '...and the open menu scrolls by itself, without handing the scroll on to the page behind it: overflow-y auto and overscroll-behavior contain',
	/overflow-y:\s*auto\s*;/.test( openRule ) && /overscroll-behavior:\s*contain\s*;/.test( openRule ) );

// --- the language picker ---------------------------------------------------

pickerBox.checked = true;
pickerLink.focus();
escape( false );
check( 'Escape closes an open language picker and hands the focus to its control when the focus was in it',
	pickerBox.checked === false && pickerBox.focused === true );

pickerBox.checked = true;
link.focus();
escape( false );
check( '...and when the focus was somewhere else it closes it and leaves the focus alone', pickerBox.checked === false && pickerBox.focused === false && link.focused === true );

// --- the dialogs -----------------------------------------------------------

check( 'a dialog is named by its heading where it has one: the heading gets an id, and the dialog points at it',
	dialogWithHeading.getAttribute( 'aria-labelledby' ) !== null && dialogWithHeading.getAttribute( 'aria-labelledby' ) === heading.id && heading.id !== '' );
check( '...a lightbox by the alt text of its picture and a video by the title of its frame',
	dialogWithPicture.getAttribute( 'aria-label' ) === 'A harbour at dawn' && dialogWithVideo.getAttribute( 'aria-label' ) === 'The film' );
check( '...a name that the markup carries is kept, and a dialog with nothing to be named by is left as it is',
	dialogNamed.getAttribute( 'aria-label' ) === 'Already named' && dialogNamed.hasAttribute( 'aria-labelledby' ) === false
	&& dialogEmpty.hasAttribute( 'aria-label' ) === false && dialogEmpty.hasAttribute( 'aria-labelledby' ) === false );

// --- a page with none of it ------------------------------------------------

const bareListeners = {};
const bareSandbox = {
	console : console,
	document : Object.assign( {}, document, {
		querySelectorAll : function() { return [] },
		addEventListener : function( name ) { bareListeners[name] = true },
	} ),
	innerHeight : 640,
	innerWidth : 1024,
	location : { hash : '' },
	requestAnimationFrame : function() {},
	setTimeout : function() {},
	addEventListener : function( name ) { bareListeners['window:'+ name] = true },
};
bareSandbox.window = bareSandbox;
bareSandbox.Nino = { client : { isMobile : false }, events : { bindCallback : function() {} } };
load( '_nino/Nino.ui.js', vm.createContext( bareSandbox ) );
bareSandbox.Nino.ui.onReady();
check( 'a page without a burger menu or a language picker listens for no key and no resize on their account', bareListeners.keydown === undefined && bareListeners['window:resize'] === undefined );

done();
