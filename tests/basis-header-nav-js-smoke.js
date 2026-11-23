/**
 *	Nino
 *	basis-header-nav-js-smoke.js	What header-nav.js of the feature Basis does over a stand-in of a page: that a menu which is open
 *									and an overlay (the stylesheet says so: the header is fixed over the window and the button is there) holds the page - what
 *									stands beside the header on every level up to the body is inert, the root element does not scroll
 *									and keeps the room of a classic scrollbar - and that Escape closes it and gives the button the
 *									focus; that nothing is held and no Escape is taken where the menu is open and is no overlay; that
 *									what was inert before stays so and what stood in the style of the root is put back; that the
 *									state follows the toggling of the details, the size of the window and the return from the cache of
 *									the browser; that a link to a place on the same page closes the menu and another link does not;
 *									and that several menus are held one at a time. What the stylesheet makes of it is seen in a
 *									browser, not here.
 *
 *									No jsdom, no dependency: the element stand-in of the kernel's harness.
 *
 *	Usage: node tests/basis-header-nav-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { check, done, element, findAll } = require( './harness.js' );

const source = fs.readFileSync( path.join( __dirname, '../_nino/Nino/Modules/Basis/assets/header-nav.js' ), 'utf8' );

/**
 *	The style of an element as the CSSOM has it: declarations with a priority, the shorthand overflow that is its two long forms (set, read - the
 *	value of a pair that is alike - and removed as both), a style attribute that is there once one was set, and how many times it was written
 */
function styleOf( held ) {

	const longs = { overflow : [ 'overflow-x', 'overflow-y' ] };
	const style = {
		values : {},
		writes : 0,
		setProperty : function( name, value, priority ) {
			style.writes++;
			held.styled = true;
			// An empty value takes the property away, as the CSSOM has it
			if( value === '' ) {
				( longs[name] || [ name ] ).forEach( function( long ) { delete style.values[long] } );
				return;
			}
			( longs[name] || [ name ] ).forEach( function( long ) { delete style.values[long]; style.values[long] = [ String( value ), priority || '' ] } );
		},
		getPropertyValue : function( name ) {
			const parts = ( longs[name] || [ name ] ).map( function( long ) { return long in style.values ? style.values[long] : null } );
			if( parts.some( function( part ) { return part === null } ) )
				return '';
			return parts.every( function( part ) { return part[0] === parts[0][0] } ) ? parts[0][0] : parts.map( function( part ) { return part[0] } ).join( ' ' );
		},
		getPropertyPriority : function( name ) {
			const parts = ( longs[name] || [ name ] ).map( function( long ) { return long in style.values ? style.values[long][1] : null } );
			return parts.every( function( part ) { return part === parts[0] } ) && parts[0] !== null ? parts[0] : '';
		},
		removeProperty : function( name ) { style.writes++; ( longs[name] || [ name ] ).forEach( function( long ) { delete style.values[long] } ) },
		// What the attribute would say, one declaration for each long form, with its priority: for a comparison before and after
		declarations : function() { return Object.keys( style.values ).sort().map( function( key ) { return key+ ': '+ style.values[key][0]+ ( style.values[key][1] === '' ? '' : ' !'+ style.values[key][1] ) } ).join( '; ' ) },
	};

	return style;
}

/**
 *	A header with its menu: header.header-wrap > div > nav > details > summary and a list of links
 */
function header( overlay ) {

	const wrap = element( 'header' );
	const inner = element( 'div' );
	const nav = element( 'nav' );
	const details = element( 'details' );
	const summary = element( 'summary' );
	const list = element( 'ul' );

	wrap.className = 'header-wrap';
	wrap.overlay = overlay;
	// Drawn, as a browser says it: not in a node that is hidden or has no box
	wrap.checkVisibility = function() { for( let node = wrap; node !== null; node = node.parentNode ) if( node.hidden === true ) return false; return true };
	details.className = 'header-nav-details';
	Object.defineProperty( details, 'open', {
		get : function() { return details.hasAttribute( 'open' ) },
		set : function( value ) { value === true ? details.setAttribute( 'open', '' ) : details.removeAttribute( 'open' ) },
	} );

	wrap.appendChild( inner );
	inner.appendChild( nav );
	nav.appendChild( details );
	details.appendChild( summary );
	details.appendChild( list );

	const links = {};

	[ '#about', '/other', '/other#top', '/here#top', '/here' ].forEach( function( href ) {
		const item = element( 'li' );
		const link = element( 'a' );
		link.setAttribute( 'href', href );
		link.href = 'https://example.test'+ href;
		link.target = '';
		item.appendChild( link );
		list.appendChild( item );
		links[href] = link;
	} );

	// What the browser makes of an address: against the page, a hash alone stays on the page
	links['#about'].href = 'https://example.test/here#about';

	// Where the focus is given, a page that scrolls to it is a page that moves: what the script asks for is written down
	const focus = summary.focus;
	summary.focusOptions = null;
	summary.focus = function( options ) { summary.focusOptions = options ?? null; focus.call( summary ) };

	return { wrap : wrap, details : details, summary : summary, links : links };
}

/**
 *	A page: body > [ aside, div#app > [ nav-of-the-site, main > article, header ], footer ], the script loaded over it
 *
 *	@param		{Object}	[options]		{ headers } how many headers, { width } window and root widths
 */
function page( options ) {

	options = Object.assign( { headers : 1, innerWidth : 1000, clientWidth : 985, open : false }, options || {} );

	const body = element( 'body' );
	const app = element( 'div' );
	const aside = element( 'aside' );
	const site = element( 'nav' );
	const main = element( 'main' );
	const footer = element( 'footer' );
	const headers = [];
	const listeners = { document : {}, window : {} };

	body.appendChild( aside );
	body.appendChild( app );
	app.appendChild( site );
	app.appendChild( main );
	main.appendChild( element( 'article' ) );

	for( let i = 0; i < options.headers; i++ ) {
		headers.push( header( true ) );
		app.appendChild( headers[i].wrap );
	}

	app.appendChild( footer );

	if( options.open === true )
		headers[0].details.open = true;

	const root = element( 'html' );
	root.style = styleOf( root );
	root.clientWidth = options.clientWidth;
	root.getAttribute = function( name ) { return name === 'style' ? ( root.styled === true ? root.style.declarations() : null ) : ( root.attributes[name] ?? null ) };
	root.removeAttribute = function( name ) { if( name === 'style' ) { root.styled = false; root.style.values = {} } else delete root.attributes[name] };

	const add = function( target ) {
		return function( type, fn, capture ) { ( listeners[target][type] = listeners[target][type] || [] ).push( { fn : fn, capture : capture === true } ) };
	};

	// What stands in the top layer ({ layer, manual, modal }) and a browser that does not know :popover-open: it throws at the selector and answers ':modal' alone
	const top = { layer : null, manual : false, modal : true, throws : false };
	const observers = [];

	const document = {
		documentElement : root,
		body : body,
		querySelectorAll : function( selector ) { return body.querySelectorAll( selector ) },
		querySelector : function( selector ) {
			if( selector === ':modal, :popover-open:not([popover=manual])' ) {
				if( top.throws === true )
					throw new SyntaxError( selector+ ' is no selector' );
				return top.manual === true ? null : top.layer;
			}
			if( selector === ':modal' )
				return top.modal === true ? top.layer : null;
			return body.querySelector( selector );
		},
		addEventListener : add( 'document' ),
	};

	// A MutationObserver that records what it is asked to watch; the suite calls it, as the browser does after a change
	function MutationObserver( callback ) {
		const watching = { callback : callback, target : null, options : null };
		observers.push( watching );
		this.observe = function( target, options ) { watching.target = target; watching.options = options };
		this.disconnect = function() { watching.target = null; watching.options = null };
	}

	const scrolls = [];
	const window = {
		innerWidth : options.innerWidth,
		scrollX : 0,
		scrollY : 0,
		scrollTo : function( to ) { scrolls.push( to ); window.scrollY = to.top },
		location : { href : 'https://example.test/here' },
		getComputedStyle : function( node ) { return { position : node.overlay === true ? 'fixed' : 'static', display : node.folded === false ? 'none' : 'block', backgroundColor : node.ground ?? 'rgb(255, 255, 255)' } },
		addEventListener : add( 'window' ),
	};

	let ready = null;
	const errors = [];

	vm.runInNewContext( source, Object.assign( window, { document : document, window : window, MutationObserver : MutationObserver, Nino : { events : { bindCallback : function( name, fn ) { ready = fn } } } } ), { filename : 'header-nav.js' } );

	try { ready(); } catch( error ) { errors.push( error ) }

	const q = {
		top : top, observers : observers, body : body, root : root, headers : headers, scrolls : scrolls, errors : errors, listeners : listeners, window : window, aside : aside, site : site, main : main, footer : footer, app : app,
		send : function( target, type, event ) {
			const out = Object.assign( { type : type, defaultPrevented : false, preventDefault : function() { out.defaultPrevented = true } }, event );
			( listeners[target][type] || [] ).forEach( function( held ) { held.fn( out ) } );
			return out;
		},
		// A details that is turned: the attribute, then the event the browser fires at it, which does not bubble
		turn : function( h, open ) { h.details.open = open; q.send( 'document', 'toggle', { target : h.details } ) },
		key : function( key, target ) { return q.send( 'document', 'keydown', { key : key, target : target ?? body } ) },
		press : function( h ) { return q.send( 'document', 'click', { target : h.summary, button : 0 } ) },
		click : function( link, event ) { return q.send( 'document', 'click', Object.assign( { target : link, button : 0 }, event ) ) },
		resize : function() { q.send( 'window', 'resize' ) },
		// The observers that watch something, asked as the browser asks them after a change
		watching : function() { return observers.filter( function( watching ) { return watching.target !== null } ) },
		changed : function() { q.watching().forEach( function( watching ) { watching.callback( [] ) } ) },
		inert : function() { return findAll( body, function( node ) { return node.inert === true } ) },
		styled : function() { return root.styled === true },
	};

	return q;
}

const names = function( list ) { return list.map( function( node ) { return node.tagName.toLowerCase() } ).join( ' ' ) };

// --- the start ----------------------------------------------------------------------------------------------

let q = page();
check( 'the script starts with the ready event of Nino.js and, with no menu open, holds nothing: no inert, no style at the root', q.errors.length === 0 && q.inert().length === 0 && q.styled() === false );
check( 'it listens to the toggling of a details in the capture phase (the event does not bubble), to the resize of the window and to the return from the cache', q.listeners.document.toggle.length === 1 && q.listeners.document.toggle[0].capture === true
	&& q.listeners.window.resize.length === 1 && q.listeners.window.pageshow.length === 1 );

q.key( 'Escape' );
check( 'Escape with no menu open is no concern of the script', q.errors.length === 0 && q.inert().length === 0 );

// --- the overlay ---------------------------------------------------------------------------------------------

q = page();
let [ h ] = q.headers;
q.turn( h, true );
check( 'an open menu that is an overlay makes inert what stands beside the header and on each level above it, up to the body', names( q.inert() ) === 'aside nav main footer' && q.inert().indexOf( q.app ) === -1 );
check( '...the header, the way to it, the menu and its list stay as they are', [ h.wrap, h.details, h.summary, q.app, q.body ].every( function( node ) { return node.inert !== true } ) && findAll( h.wrap, function( node ) { return node.inert === true } ).length === 0 );
check( '...and the root does not scroll, with the room of the scrollbar kept: overflow hidden, scrollbar-gutter stable', q.root.style.getPropertyValue( 'overflow' ) === 'hidden' && q.root.style.getPropertyValue( 'scrollbar-gutter' ) === 'stable' );

q.turn( h, false );
check( 'closed, nothing is left: no inert, no property and no style attribute at the root', q.inert().length === 0 && q.root.style.getPropertyValue( 'overflow' ) === '' && q.root.style.getPropertyValue( 'scrollbar-gutter' ) === '' && q.styled() === false );

q = page( { clientWidth : 1000 } );
q.turn( q.headers[0], true );
check( 'where the scrollbar takes no room (an overlay one, or none) no gutter is asked for', q.root.style.getPropertyValue( 'overflow' ) === 'hidden' && q.root.style.getPropertyValue( 'scrollbar-gutter' ) === '' );

// --- an element that has no inert -----------------------------------------------------------------------------------
// A picture of svg or a formula beside the header has no property inert: the browser does not hold it, and the script does not write the attribute at it - one that it
// wrote would stay, since it could not tell afterwards that it was its own

q = page();
h = q.headers[0];
delete q.aside.inert;
q.turn( h, true );
q.changed();
check( 'an element beside the header that has no inert of its own - svg, a formula - is not given the attribute, and the others are held', q.aside.hasAttribute( 'inert' ) === false && names( q.inert() ) === 'nav main footer' );
q.turn( h, false );
check( '...and nothing of it is left when the menu is closed', q.aside.hasAttribute( 'inert' ) === false && q.inert().length === 0 && findAll( q.body, function( node ) { return node.hasAttribute( 'inert' ) } ).length === 0 );

// --- what was there before ----------------------------------------------------------------------------------------

q = page();
h = q.headers[0];
q.aside.inert = true;
q.root.style.setProperty( 'overflow', 'clip', 'important' );
q.root.style.setProperty( 'scrollbar-gutter', 'auto' );
q.root.style.setProperty( 'color', 'red' );
q.turn( h, true );
q.turn( h, false );
check( 'what was inert before stays so after the menu is closed, and is not counted as ours', q.aside.inert === true && names( q.inert() ) === 'aside' );
check( 'what stood in the style of the root - value and priority - is put back, and what else was there is not touched', q.root.style.getPropertyValue( 'overflow' ) === 'clip' && q.root.style.getPropertyPriority( 'overflow' ) === 'important'
	&& q.root.style.getPropertyValue( 'scrollbar-gutter' ) === 'auto' && q.root.style.getPropertyValue( 'color' ) === 'red' && q.styled() === true );

// --- Escape ----------------------------------------------------------------------------------------------

q = page();
h = q.headers[0];
q.turn( h, true );
h.links['/other'].focus();
let event = q.key( 'Escape' );
check( 'Escape closes the overlay and gives the focus to the button', h.details.open === false && h.summary.focused === true && event.defaultPrevented === true );
check( '...and the page does not scroll to the button: the focus is given with preventScroll, the scroll position after the close is the one before', h.summary.focusOptions !== null && h.summary.focusOptions.preventScroll === true );
check( '...and lets everything go at once, before the toggle event comes', q.inert().length === 0 && q.styled() === false );

q.turn( h, true );
event = q.key( 'a' );
check( 'another key does nothing', h.details.open === true && event.defaultPrevented === false );

h.summary.focused = false;
event = q.send( 'document', 'keydown', { key : 'Escape', defaultPrevented : true, target : q.body } );
check( 'an Escape that somebody has taken already is left alone', h.details.open === true && h.summary.focused === false );

q = page();
h = q.headers[0];
h.wrap.overlay = false;
q.turn( h, true );
event = q.key( 'Escape' );
check( 'an open menu that is no overlay - a wide screen, "never" - holds nothing, and its Escape is not taken: no preventDefault, still open', q.inert().length === 0 && q.styled() === false && event.defaultPrevented === false && h.details.open === true );

// --- the state follows what it finds ------------------------------------------------------------------------------

q = page();
h = q.headers[0];
q.turn( h, true );
h.wrap.overlay = false;
q.resize();
check( 'the menu is open at a narrow window and the window is made wide (the stylesheet no longer says overlay): everything is free, the details is still open', h.details.open === true && q.inert().length === 0 && q.styled() === false );
h.wrap.overlay = true;
q.resize();
check( '...and narrow again, with the menu still open: held again', names( q.inert() ) === 'aside nav main footer' && q.root.style.getPropertyValue( 'overflow' ) === 'hidden' );
q.resize();
q.resize();
check( 'a resize that changes nothing changes nothing: the same elements, once', names( q.inert() ) === 'aside nav main footer' );
h.wrap.overlay = false;
q.send( 'window', 'pageshow', { persisted : true } );
check( 'the page that comes back from the cache of the browser is made from what it finds', q.inert().length === 0 && q.styled() === false );

q = page();
h = q.headers[0];
h.details.open = true;
q.send( 'window', 'pageshow', { persisted : true } );
check( 'a page restored with an open menu that is an overlay is held', names( q.inert() ) === 'aside nav main footer' );

q = page( { open : true } );
check( 'a page that is made with an open menu that is an overlay is held from the start', names( q.inert() ) === 'aside nav main footer' && q.root.style.getPropertyValue( 'overflow' ) === 'hidden' );

// --- links ----------------------------------------------------------------------------------------------------

q = page();
h = q.headers[0];
q.turn( h, true );
event = q.click( h.links['#about'] );
check( 'a link to a place of this page closes the menu and lets the page go, and the click goes on (no preventDefault)', h.details.open === false && q.inert().length === 0 && q.styled() === false && event.defaultPrevented === false );

q.turn( h, true );
q.click( h.links['/here#top'] );
check( '...so does a link with the address of this page and a place', h.details.open === false && q.inert().length === 0 );

q.turn( h, true );
q.click( h.links['/other'] );
check( 'a link to another page leaves the menu as it is: the page goes away with it', h.details.open === true && q.inert().length > 0 );
q.click( h.links['/other#top'] );
check( '...and so does a link to a place of another page', h.details.open === true );
q.click( h.links['/here'] );
check( '...and so does a link to this page with no place: it loads the page again', h.details.open === true );
q.click( h.links['#about'], { ctrlKey : true } );
q.click( h.links['#about'], { metaKey : true } );
q.click( h.links['#about'], { shiftKey : true } );
q.click( h.links['#about'], { button : 1 } );
h.links['#about'].target = '_blank';
q.click( h.links['#about'] );
h.links['#about'].target = '';
check( 'a click that opens the link elsewhere - a modifier key, the middle button, a target - leaves the menu open', h.details.open === true );
q.click( h.links['#about'], { defaultPrevented : true } );
check( 'a click somebody has taken is left alone', h.details.open === true );
const outsideLink = element( 'a' );
outsideLink.setAttribute( 'href', '#x' );
outsideLink.href = 'https://example.test/here#x';
outsideLink.target = '';
q.main.appendChild( outsideLink );
q.click( outsideLink );
check( 'a link outside the menu does not close it', h.details.open === true );

q = page();
h = q.headers[0];
h.wrap.overlay = false;
h.details.open = true;
q.click( h.links['#about'] );
check( 'where the menu is no overlay a link does not close it', h.details.open === true );

// --- where the page was ---------------------------------------------------------------------------------------

q = page();
h = q.headers[0];
q.window.scrollY = 500;
q.press( h );
q.window.scrollY = 420;
q.turn( h, true );
q.turn( h, false );
check( 'a page that was scrolled to its end moves when the header leaves the flow: closing the menu puts it back where it was when the button was pressed', q.scrolls.length === 1 && q.scrolls[0].top === 500 && q.window.scrollY === 500 );

q = page();
h = q.headers[0];
q.window.scrollY = 500;
q.press( h );
q.turn( h, true );
q.turn( h, false );
check( '...and a page that did not move is not scrolled', q.scrolls.length === 0 );

q = page();
h = q.headers[0];
q.window.scrollY = 500;
q.press( h );
q.window.scrollY = 420;
q.turn( h, true );
h.wrap.overlay = false;
q.resize();
check( 'a menu that stays open while the window is made wide leaves the page where it is: only a menu that is closed puts it back', q.scrolls.length === 0 && q.inert().length === 0 );

q = page();
h = q.headers[0];
q.window.scrollY = 500;
q.press( h );
q.window.scrollY = 420;
q.turn( h, true );
q.key( 'Escape' );
check( 'Escape puts the page back as well', q.scrolls.length === 1 && q.scrolls[0].top === 500 );

q = page();
h = q.headers[0];
h.wrap.overlay = false;
q.window.scrollY = 100;
q.press( h );
q.turn( h, true );
h.wrap.overlay = true;
q.window.scrollY = 300;
q.resize();
q.window.scrollY = 250;
q.turn( h, false );
check( 'a press that made no overlay leaves nothing behind for the next one: the place is the one where the page was held', q.scrolls.length === 1 && q.scrolls[0].top === 300 );

q = page();
h = q.headers[0];
q.window.scrollY = 500;
q.press( h );
q.window.scrollY = 420;
q.turn( h, true );
q.press( h );
q.turn( h, false );
check( 'the press that closes the menu does not take the place again: it is the place of the press that opened it', q.scrolls.length === 1 && q.scrolls[0].top === 500 );

q = page();
h = q.headers[0];
q.window.scrollY = 500;
q.press( h );
q.window.scrollY = 420;
q.turn( h, true );
q.turn( h, false );
q.window.scrollY = 300;
q.turn( h, true );
q.window.scrollY = 250;
q.turn( h, false );
check( 'a menu that is opened without a press (a link, a script) is put back to the place it was held at, not to the place of the menu before', q.scrolls.length === 2 && q.scrolls[1].top === 300 );

// --- nothing is left behind: the document changes while the page is held ----------------------------------------------

q = page();
h = q.headers[0];
check( 'nothing is watched while nothing is held', q.watching().length === 0 );
q.turn( h, true );
check( 'while the page is held the document is watched: the tree, and the attributes class, open, hidden, inert and style', q.watching().length === 1 && q.watching()[0].options.subtree === true && q.watching()[0].options.childList === true
	&& q.watching()[0].options.attributes === true && [ 'class', 'open', 'hidden', 'inert', 'style' ].every( function( name ) { return q.watching()[0].options.attributeFilter.indexOf( name ) !== -1 } ) );
q.turn( h, false );
check( '...and no longer once it is let go', q.watching().length === 0 );

const lost = function( how ) {
	const p = page();
	const g = p.headers[0];
	p.turn( g, true );
	how( p, g );
	return p;
};

q = lost( function( p, g ) { g.details.remove(); p.changed() } );
check( 'the menu that is held is taken out of the document: the observer lets the page go - no inert, no style, nothing watched', q.inert().length === 0 && q.styled() === false && q.watching().length === 0 );

q = lost( function( p, g ) { g.wrap.remove(); p.changed() } );
check( 'the header that is held is taken out of the document: the same', q.inert().length === 0 && q.styled() === false && q.watching().length === 0 );

q = lost( function( p, g ) {
	const fresh = header( true );
	fresh.details.open = false;
	g.wrap.replaceWith( fresh.wrap );
	p.changed();
} );
check( 'the header is drawn anew (a closed menu in a new header): the page is let go', q.inert().length === 0 && q.styled() === false );

q = lost( function( p, g ) { g.wrap.overlay = false; p.changed() } );
check( 'the class of the menu becomes "never" while it is open - the header is no overlay any more: the page is let go, the menu is still open', q.inert().length === 0 && q.styled() === false && q.headers[0].details.open === true );

q = lost( function( p, g ) { g.wrap.hidden = true; p.changed() } );
check( 'the header is hidden while the menu is open: let go', q.inert().length === 0 && q.styled() === false );

q = lost( function( p, g ) { g.wrap.remove() } );
q.key( 'a' );
check( 'with nobody to say so, any key asks: the page is let go by the next key press', q.inert().length === 0 && q.styled() === false && q.watching().length === 0 );

q = lost( function( p, g ) { g.wrap.remove() } );
q.send( 'document', 'pointerdown', { target : q.body } );
check( '...and a press with the pointer, which listens in the capture phase', q.inert().length === 0 && q.styled() === false && q.listeners.document.pointerdown[0].capture === true );

q = page();
q.key( 'a' );
q.send( 'document', 'pointerdown', { target : q.body } );
check( 'a key and a press with nothing held change nothing and write nothing', q.root.style.writes === 0 && q.inert().length === 0 );

q = page();
h = q.headers[0];
q.turn( h, true );
const written = q.root.style.writes;
q.changed();
q.changed();
q.resize();
q.resize();
q.send( 'window', 'pageshow', { persisted : true } );
q.key( 'a' );
check( 'what the script changes itself sets the observer off and nothing comes of it: the same state is no new write at the root, and the same elements are inert (once each)', q.root.style.writes === written && names( q.inert() ) === 'aside nav main footer' );

// --- what comes beside the header after it was held ----------------------------------------------------------------

q = page();
h = q.headers[0];
q.turn( h, true );
const banner = element( 'div' );
q.body.appendChild( banner );
q.changed();
check( 'something that comes beside the header after the menu was opened (a notice at the end of the body) is inert too', banner.inert === true );
q.turn( h, false );
check( '...and is let go with the rest', banner.inert === false && q.inert().length === 0 );

q = page();
h = q.headers[0];
const late = element( 'section' );
late.inert = true;
q.turn( h, true );
q.body.appendChild( late );
q.changed();
q.turn( h, false );
check( 'what comes later and is inert already is not ours: it stays so', late.inert === true );

q = page();
h = q.headers[0];
q.turn( h, true );
q.footer.inert = false;
const syncs = q.root.style.writes;
for( let round = 0; round < 5; round++ ) {
	q.changed();
	q.footer.inert = false;
}
check( 'an element that a script of the page makes free again is left to it: the observer does not make it inert again, however often it looks, and the rest stays held', q.footer.inert === false && q.aside.inert === true && q.root.style.writes === syncs && q.watching().length === 1 );

q.footer.inert = true;
q.changed();
q.turn( h, false );
check( '...and what that script makes inert itself afterwards is not taken back when the page is let go', q.footer.inert === true && q.aside.inert === false );

q = page();
h = q.headers[0];
q.footer.inert = true;
q.turn( h, true );
q.footer.inert = false;
q.changed();
check( 'what was inert before and is freed by somebody else while the page is held is not made inert by the script', q.footer.inert === false );

// A script that frees what the script made inert, over and over, and the observer that looks: the number of runs is the number of changes, no more
q = page();
h = q.headers[0];
q.turn( h, true );
let looks = 0;
const asked = q.watching()[0].callback;
q.watching()[0].callback = function() { looks++; asked() };
for( let round = 0; round < 20; round++ ) {
	q.main.inert = false;
	q.changed();
}
check( 'twenty changes by a script that frees an element are twenty looks, and the page is held all the same', looks === 20 && q.main.inert === false && q.styled() === true );

// --- an element that cannot be made inert ------------------------------------------------------------------------------

q = page();
h = q.headers[0];
const stubborn = element( 'section' );
stubborn.toggleAttribute = function() { throw new Error( 'no inert for this one' ) };
q.body.insertBefore( stubborn, q.aside );
q.turn( h, true );
check( 'an element that throws when it is made inert leaves the rest held: the others are inert and the root is locked, and nothing is thrown', q.errors.length === 0 && q.aside.inert === true && q.footer.inert === true && q.root.style.getPropertyValue( 'overflow' ) === 'hidden' );
event = q.key( 'Escape' );
check( '...and Escape still closes the menu and lets the others go', h.details.open === false && event.defaultPrevented === true && q.aside.inert === false && q.footer.inert === false && q.styled() === false );


// --- what stands in the top layer ---------------------------------------------------------------------------------

q = page();
h = q.headers[0];
q.turn( h, true );
const dialog = element( 'dialog' );
q.main.appendChild( dialog );
q.top.layer = dialog;
q.changed();
check( 'a modal dialog or a popover that opens outside the header while the menu is held closes the menu and lets the page go: it must not lie dead', h.details.open === false && q.inert().length === 0 && q.styled() === false );

q = page();
h = q.headers[0];
const inHeader = element( 'dialog' );
h.wrap.appendChild( inHeader );
q.turn( h, true );
q.top.layer = inHeader;
q.changed();
event = q.key( 'Escape', inHeader );
check( 'one that is open in the header leaves the menu as it is, and Escape is the top layer\'s: the script does nothing and takes nothing', h.details.open === true && event.defaultPrevented === false && q.inert().length > 0 );
q.top.layer = null;
event = q.key( 'Escape' );
check( '...the next Escape, with nothing above, closes the menu', h.details.open === false && event.defaultPrevented === true );

q = page();
h = q.headers[0];
const popover = element( 'div' );
q.main.appendChild( popover );
q.top.layer = popover;
q.turn( h, true );
check( 'with a popover open already, a menu that is pressed open is closed at once: the page is never held under a top layer', h.details.open === false && q.inert().length === 0 );

q = page();
h = q.headers[0];
const toast = element( 'div' );
q.main.appendChild( toast );
q.top.layer = toast;
q.top.manual = true;
q.turn( h, true );
check( 'a popover that is manual (a toast) and open outside the header is no layer over the menu: the menu opens and is held', h.details.open === true && names( q.inert() ) === 'aside nav main footer' );
q.changed();
check( '...and stays open and held when the document changes', h.details.open === true && q.inert().length > 0 );
q.key( 'Escape' );
check( '...and Escape closes it', h.details.open === false && q.inert().length === 0 );

q = page();
h = q.headers[0];
const toastIn = element( 'div' );
h.wrap.appendChild( toastIn );
q.top.layer = toastIn;
q.top.manual = true;
q.turn( h, true );
event = q.key( 'Escape' );
check( 'a manual popover in the header does not make Escape without effect for good: the menu is closed', h.details.open === false && event.defaultPrevented === true && q.inert().length === 0 );

q = page();
h = q.headers[0];
q.top.throws = true;
const old = element( 'dialog' );
q.main.appendChild( old );
q.turn( h, true );
q.top.layer = old;
q.changed();
check( 'in a browser that does not know :popover-open only modal dialogs are asked for, and no error comes of it: a modal one closes the menu', h.details.open === false && q.inert().length === 0 && q.errors.length === 0 );

q = page();
h = q.headers[0];
q.top.throws = true;
q.top.modal = false;
const open = element( 'dialog' );
q.main.appendChild( open );
q.top.layer = open;
q.turn( h, true );
event = q.key( 'Escape' );
check( '...and a dialog that is open and not modal, and always, is no layer: the menu opens and is held, Escape closes it', h.details.open === false && event.defaultPrevented === true && q.inert().length === 0 );
q.top.modal = true;
q.top.throws = false;
q.top.layer = null;

q = page();
h = q.headers[0];
const field = element( 'input' );
field.type = 'search';
field.value = 'abc';
h.wrap.appendChild( field );
q.turn( h, true );
event = q.key( 'Escape', field );
check( 'Escape in a search field that has text in it is the browser\'s - it clears the field: the menu stays open and nothing is taken', h.details.open === true && event.defaultPrevented === false );
field.value = '';
event = q.key( 'Escape', field );
check( '...and once the field is empty the next Escape closes the menu', h.details.open === false && event.defaultPrevented === true );

q = page();
h = q.headers[0];
const plain = element( 'input' );
plain.type = 'text';
plain.value = 'abc';
h.wrap.appendChild( plain );
q.turn( h, true );
event = q.key( 'Escape', plain );
check( 'any other field with text does not hold Escape back', h.details.open === false && event.defaultPrevented === true );

// --- drawn or not ------------------------------------------------------------------------------------------------

q = page();
h = q.headers[0];
q.app.hidden = true;
q.turn( h, true );
check( 'a header in something that is not drawn (a hidden ancestor) is no overlay: nothing is held', q.inert().length === 0 && q.styled() === false );

q = page();
h = q.headers[0];
q.turn( h, true );
h.wrap.checkVisibility = function() { return false };
q.changed();
check( 'a header that says it is not drawn (checkVisibility() false: display none, content-visibility) is no overlay: the page is let go', q.inert().length === 0 && q.styled() === false );

q = page();
h = q.headers[0];
h.wrap.inert = true;
q.turn( h, true );
check( 'a header that is inert itself is no overlay', q.styled() === false && h.details.open === true );

q = page();
h = q.headers[0];
q.app.inert = true;
q.turn( h, true );
check( 'a header in something that is inert is no overlay either', q.styled() === false && h.details.open === true );

q = page();
h = q.headers[0];
q.app.inert = true;
q.footer.inert = true;
q.app.inert = false;
q.turn( h, true );
q.turn( h, false );
check( 'an inert node of the page that is a neighbour of the header does not stop the page from being held, and stays inert', q.footer.inert === true && q.styled() === false );

// --- the preview of the workbench ----------------------------------------------------------------------------------

q = page();
h = q.headers[0];
q.root.setAttribute( 'data-nino-preview', 'loose' );
q.turn( h, true );
event = q.key( 'Escape' );
check( 'a page that the workbench shows as a preview (data-nino-preview at the document) holds nothing and takes no Escape: the tools of the editor stay free', q.inert().length === 0 && q.styled() === false && event.defaultPrevented === false && h.details.open === true );

q = page();
h = q.headers[0];
q.turn( h, true );
q.root.setAttribute( 'data-nino-preview', 'loose' );
q.resize();
check( '...and where the mark comes after the page was held, the page is let go', q.inert().length === 0 && q.styled() === false );

// --- the style of the root, as it was ------------------------------------------------------------------------------------

const style = function( declare ) {
	const p = page();
	declare( p.root.style );
	const was = p.root.getAttribute( 'style' );
	p.turn( p.headers[0], true );
	const during = p.root.style.getPropertyValue( 'overflow' );
	p.turn( p.headers[0], false );
	return { was : was, during : during, after : p.root.getAttribute( 'style' ), p : p };
};

let r = style( function( st ) { st.setProperty( 'overflow-y', 'scroll' ) } );
check( 'a root with the long form overflow-y alone is the same after the menu is closed: overflow-y scroll, no overflow-x written in', r.during === 'hidden' && r.after === r.was && r.after === 'overflow-y: scroll' );

r = style( function( st ) { st.setProperty( 'overflow-x', 'hidden', 'important' ); st.setProperty( 'overflow-y', 'auto' ) } );
check( 'overflow-x with !important and overflow-y beside it come back, each with its own priority', r.after === r.was && r.after === 'overflow-x: hidden !important; overflow-y: auto' );

r = style( function( st ) { st.setProperty( 'overflow', 'auto', 'important' ); st.setProperty( 'scrollbar-gutter', 'stable both-edges' ); st.setProperty( 'color', 'red' ) } );
check( 'the short form with !important, a gutter of its own and a colour come back as they were', r.after === r.was && r.p.root.style.getPropertyValue( 'color' ) === 'red' && r.p.root.style.getPropertyPriority( 'overflow' ) === 'important' );

r = style( function( st ) { st.setProperty( 'scrollbar-gutter', 'auto', 'important' ) } );
check( 'scrollbar-gutter auto with !important comes back with it', r.after === r.was );

r = style( function( st ) { st.setProperty( 'background-color', 'rgb(1, 2, 3)', 'important' ) } );
check( 'a background colour of the root comes back with its priority', r.after === r.was && r.after === 'background-color: rgb(1, 2, 3) !important' );

r = style( function() {} );
check( 'a root with no style attribute has none after', r.was === null && r.after === null );

q = page();
h = q.headers[0];
q.turn( h, true );
q.turn( h, false );
q.root.style.setProperty( 'overflow-y', 'scroll' );
q.turn( h, true );
q.turn( h, false );
check( 'what the style of the root holds between two holds is not touched by the first: the second hold gives back what it found', q.root.getAttribute( 'style' ) === 'overflow-y: scroll' );

// --- the strip of a classic scrollbar ------------------------------------------------------------------------------------

q = page();
h = q.headers[0];
h.wrap.ground = 'rgb(21, 23, 27)';
q.turn( h, true );
check( 'with a classic scrollbar the room of it stays and what stands in it is the colour of the header: the root takes the background colour of the header', q.root.style.getPropertyValue( 'scrollbar-gutter' ) === 'stable' && q.root.style.getPropertyValue( 'background-color' ) === 'rgb(21, 23, 27)' );
q.turn( h, false );
check( '...and gives it back', q.root.style.getPropertyValue( 'background-color' ) === '' && q.styled() === false );

q = page( { clientWidth : 1000 } );
h = q.headers[0];
h.wrap.ground = 'rgb(21, 23, 27)';
q.turn( h, true );
check( 'with a scrollbar that takes no room nothing is coloured: no gutter, no background colour', q.root.style.getPropertyValue( 'background-color' ) === '' && q.root.style.getPropertyValue( 'scrollbar-gutter' ) === '' );

// --- links and a held menu: the cases that stay as they are ------------------------------------------------------------------

q = page();
h = q.headers[0];
h.links['#about'].target = '_self';
q.turn( h, true );
q.click( h.links['#about'] );
check( 'a link whose target is _self is no link elsewhere: it closes the menu', h.details.open === false && q.inert().length === 0 );

// --- several menus ---------------------------------------------------------------------------------------------

q = page( { headers : 2 } );
const [ first, second ] = q.headers;
q.turn( first, true );
check( 'with two headers the open one is held and the other header is inert with the rest', q.inert().indexOf( second.wrap ) !== -1 && q.inert().indexOf( first.wrap ) === -1 );
q.turn( second, true );
check( 'with both open the last one in the document is held: the first header is inert, the second is not', q.inert().indexOf( first.wrap ) !== -1 && q.inert().indexOf( second.wrap ) === -1 );
q.key( 'Escape' );
check( 'Escape closes the one that is held, and the other is held then', second.details.open === false && first.details.open === true && q.inert().indexOf( second.wrap ) !== -1 && q.inert().indexOf( first.wrap ) === -1 );
q.key( 'Escape' );
check( '...and the next Escape closes that one: nothing is left', first.details.open === false && q.inert().length === 0 && q.styled() === false );

q = page( { headers : 2 } );
q.headers[0].wrap.overlay = false;
q.turn( q.headers[0], true );
q.turn( q.headers[1], true );
q.turn( q.headers[1], false );
check( 'an open menu that is no overlay beside one that is: only the overlay holds the page, and it lets it go', q.inert().length === 0 && q.styled() === false && q.headers[0].details.open === true );

q = page();
h = q.headers[0];
h.summary.folded = false;
q.turn( h, true );
event = q.key( 'Escape' );
check( 'a header that is fixed and has no button (a theme that fixes it, "never") is no overlay: nothing held, Escape not taken', q.inert().length === 0 && q.styled() === false && event.defaultPrevented === false && h.details.open === true );

q = page( { headers : 1 } );
const stray = element( 'details' );
stray.className = 'header-nav-details';
stray.setAttribute( 'open', '' );
stray.appendChild( element( 'summary' ) );
q.main.appendChild( stray );
q.turn( { details : stray }, true );
q.key( 'Escape' );
check( 'a menu that stands in no header (the component can stand in a container of another name) is no overlay and breaks nothing', q.errors.length === 0 && q.inert().length === 0 && stray.hasAttribute( 'open' ) === true );

q = page( { headers : 0 } );
q.turn( { details : element( 'details' ) }, true );
q.resize();
q.key( 'Escape' );
check( 'a page with no header at all: no error from any event', q.errors.length === 0 );

done();
