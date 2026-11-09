/**
 *	Nino									A compact filesystembased php framework
 *	admin-follow-js-smoke.js	The follow window (/_admin/?follow, _admin/assets/follow.js):
 *										the website in a frame that loads anew when the workbench
 *										tells it on the channel something was written - once for
 *										the messages of one save, at the page a message names when
 *										the switch says to follow it, and at the page it shows
 *										otherwise. The page is a stand-in: a frame whose location
 *										counts its reloads, a channel the test speaks on, a timer
 *										the test advances, and a store for the switch.
 *
 *	Usage: node tests/admin-follow-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element } = require( './harness.js' );

/**
 *	The window as a browser has it, loaded and ready
 *
 *	@param		{Object}	options		{ dir, stored, theme, uriStored, search, dataSrc, channel: false for a browser without one, storage: 'throws' for one that refuses, reload: 'throws' for a frame the page cannot reach into, opener: 'refused' for one that cannot be let go of }
 *
 *	@return		{Object}
 */
function windowOf( options ) {

	const dir = options.dir ?? '';
	const world = { reloads : 0, timers : [], channels : [], store : {}, switchField : null, text : {} };

	if( options.stored !== undefined )
		world.store['nino-admin-follow'] = options.stored;
	if( options.theme !== undefined )
		world.store['nino-admin-theme'] = options.theme;
	if( options.uriStored !== undefined )
		world.store['nino-admin-follow-uri'] = options.uriStored;

	const nodes = {
		'admin-follow-wrap' 		: element('div'),
		'admin-follow-frame' 		: element('iframe'),
		'admin-follow-path' 		: element('span'),
		'admin-follow-reload' 	: element('button'),
		'admin-follow-switch' 	: element('span'),
	};
	nodes['admin-follow-wrap'].dataset.dir = dir;
	// The template names the first address and leaves src out; what is assigned to src is recorded
	nodes['admin-follow-frame'].dataset.src = options.dataSrc ?? dir+ '/';
	world.assigned = [];
	let address = '';
	Object.defineProperty( nodes['admin-follow-frame'], 'src', { get : function() { return address }, set : function( value ) { address = value; world.assigned.push( value ) } } );
	nodes['admin-follow-frame'].contentWindow = { location : {
		pathname : dir+ '/',
		reload : function() {
			if( options.reload === 'throws' )
				throw new Error( 'SecurityError' );
			world.reloads++;
		},
	} };

	world.root = { attributes : {}, setAttribute : function( name, value ) { this.attributes[name] = value }, removeAttribute : function( name ) { delete this.attributes[name] } };
	world.body = { className : '' };

	const page = {
		console : console,
		opener : { location : 'the workbench' },
		location : { search : options.search ?? '' },
		document : { getElementById : function( id ) { return nodes[id] ?? null }, documentElement : world.root, body : world.body },
		setTimeout : function( fn, ms ) { world.timers.push( { fn : fn, ms : ms } ); return world.timers.length },
		localStorage : options.storage === 'throws' ? { getItem : function() { throw new Error( 'denied' ) }, setItem : function() { throw new Error( 'denied' ) } } : {
			getItem : function( key ) { return world.store[key] ?? null },
			setItem : function( key, value ) { world.store[key] = String( value ) },
		},
	};

	if( options.opener === 'refused' )
		Object.defineProperty( page, 'opener', { get : function() { return {} }, set : function() { throw new Error( 'refused' ) } } );

	if( options.channel !== false )
		page.BroadcastChannel = function( name ) {
			this.name = name;
			this.listeners = [];
			this.addEventListener = function( type, fn ) { if( type === 'message' ) this.listeners.push( fn ) };
			world.channels.push( this );
		};

	page.window = page;
	page.Nino = {
		events : { bindCallback : function( name, fn ) { world.ready = fn } },
		content : { getText : function( key ) { world.text[key] = ( world.text[key] ?? 0 ) + 1; return key } },
		adminUi : { switchField : function( spec ) {
			// Enough of the switch: a label around a checkbox that says when it changes
			const label = element('label');
			const input = element('input');
			input.type = 'checkbox';
			input.checked = spec.checked === true;
			label.appendChild( input );
			world.switchField = { spec : spec, label : label, input : input };
			return label;
		} },
	};

	load( '_admin/assets/follow.js', vm.createContext( page ) );
	world.admin = page.Nino.admin;
	world.nodes = nodes;

	// Say something was written, on the channel
	world.say = function( data ) { world.channels.forEach( function( channel ) { channel.listeners.forEach( function( fn ) { fn( { data : data } ) } ) } ) };
	// Let the time of the oldest timer pass
	world.tick = function() { const timer = world.timers.shift(); if( timer !== undefined ) timer.fn() };
	// The frame has loaded the page at a path
	world.loaded = function( pathname ) {
		nodes['admin-follow-frame'].contentWindow.location.pathname = pathname;
		nodes['admin-follow-frame'].listeners.load.forEach( function( fn ) { fn( { type : 'load' } ) } );
	};
	// The switch is turned
	world.turn = function( on ) {
		world.switchField.input.checked = on;
		world.switchField.input.listeners.change.forEach( function( fn ) { fn.call( world.switchField.input, { type : 'change' } ) } );
	};

	world.ready();
	world.page = page;
	// What the start assigned to the frame, set apart from what a test makes of it
	world.started = world.assigned.slice();
	world.assigned.length = 0;

	return world;
}

// --- a message is a load ------------------------------------------------------

let w = windowOf( {} );
check( 'the window listens on the channel of the installation: the directory it is in, empty at the root', w.channels.length === 1 && w.channels[0].name === 'nino-admin:' );
check( '...and on a subdirectory\'s own, so that two installations of one origin do not hear each other', windowOf( { dir : '/sites/a' } ).channels[0].name === 'nino-admin:/sites/a' );
w.say( { kind : 'texts', uri : null, at : 1 } );
check( 'a message does not load the frame at once: it waits for the others of the same save', w.reloads === 0 && w.timers.length === 1 );
check( '...two hundred milliseconds', w.timers[0].ms === 200 && w.admin.DEBOUNCE === 200 );
w.tick();
check( 'after them it loads the page the frame shows, once', w.reloads === 1 && w.nodes['admin-follow-frame'].src === '/' );

w = windowOf( {} );
w.say( { kind : 'texts', uri : null, at : 1 } );
w.say( { kind : 'templates', uri : null, at : 2 } );
w.say( { kind : 'routes', uri : null, at : 3 } );
check( 'three messages within the time are one wait', w.timers.length === 1 && w.reloads === 0 );
w.tick();
check( '...and one load', w.reloads === 1 && w.timers.length === 0 );
w.say( { kind : 'texts', uri : null, at : 4 } );
w.tick();
check( 'the next save is a load of its own', w.reloads === 2 );

w = windowOf( {} );
[ null, 'text', 42, [], {} ].forEach( function( data ) { w.say( data ) } );
w.tick();
check( 'a message that is no { kind, uri, at } is still the word that something was written, and throws nothing', w.reloads === 1 && w.timers.length === 0 );

// --- a message that names a page ----------------------------------------------

w = windowOf( {} );
w.say( { kind : 'routes', uri : '/blog/post', at : 1 } );
w.tick();
check( 'with the switch on - as it is to begin with - a message that names a page takes the frame there', w.nodes['admin-follow-frame'].src === '/blog/post' && w.reloads === 0 );

w = windowOf( { dir : '/sites/a' } );
w.say( { kind : 'routes', uri : '/blog/post', at : 1 } );
w.tick();
check( '...below the directory the installation is in', w.nodes['admin-follow-frame'].src === '/sites/a/blog/post' );

w = windowOf( {} );
w.say( { kind : 'routes', uri : '/blog/post', at : 1 } );
w.say( { kind : 'texts', uri : null, at : 2 } );
w.say( { kind : 'routes', uri : '/about', at : 3 } );
w.say( { kind : 'templates', uri : null, at : 4 } );
w.tick();
check( 'of the pages the messages of one save name the last counts, and one that names none does not take it back', w.nodes['admin-follow-frame'].src === '/about' && w.reloads === 0 );

w = windowOf( {} );
w.turn( false );
w.say( { kind : 'routes', uri : '/blog/post', at : 1 } );
w.tick();
check( 'with the switch off the frame loads again the page it shows, and does not go', w.reloads === 1 && w.nodes['admin-follow-frame'].src === '/' );

// An address that is no path of this site counts as none: the frame loads again
[ '//evil.example/x', 'http://x/', 'https://x', 'javascript:alert(1)', '/a b', '/a<b', '/a"b', "/a'b", '/a\\b', "/a\n", 'blog', '', 42, null, undefined, {}, [ '/blog' ] ].forEach( function( bad ) {
	const bound = windowOf( {} );
	bound.say( { kind : 'routes', uri : bad, at : 1 } );
	bound.tick();
	check( 'an address that is no path of this site is no address: '+ JSON.stringify( bad ), bound.reloads === 1 && bound.nodes['admin-follow-frame'].src === '/' );
} );
check( 'a path with a query and a fragment is one', w.admin.isPath( '/blog?x=1#top' ) === true && w.admin.isPath( '/' ) === true );

w = windowOf( { reload : 'throws' } );
w.nodes['admin-follow-frame'].src = '/blog/post';
w.assigned.length = 0;
w.say( { kind : 'texts', uri : null, at : 1 } );
w.tick();
check( 'a frame the window cannot reach into - it has left the site - is gone to again at the address it has, and nothing throws', w.assigned.join() === '/blog/post' );

// --- the bar ----------------------------------------------------------------------

w = windowOf( {} );
w.loaded( '/blog/post' );
check( 'a loaded page is named in the bar by its path', w.nodes['admin-follow-path'].textContent === '/blog/post' );
w = windowOf( { dir : '/sites/a' } );
w.loaded( '/sites/a/blog/post' );
check( '...without the directory the installation is in', w.nodes['admin-follow-path'].textContent === '/blog/post' );
w.loaded( '/sites/a' );
check( '...and the home page of a subdirectory is the slash', w.nodes['admin-follow-path'].textContent === '/' );
w.loaded( '/<b>x</b>"' );
check( '...as text: an address the website chose is not markup', w.nodes['admin-follow-path'].textContent === '/<b>x</b>"' && w.nodes['admin-follow-path'].children.length === 0 );
w.nodes['admin-follow-frame'].contentWindow = { get location() { throw new Error( 'SecurityError' ) } };
w.nodes['admin-follow-frame'].listeners.load.forEach( function( fn ) { fn( { type : 'load' } ) } );
check( 'a page the window cannot read - another site, a closed one - leaves no path in the bar rather than the one before, and throws nothing', w.nodes['admin-follow-path'].textContent === '' );

w = windowOf( {} );
w.nodes['admin-follow-reload'].listeners.click.forEach( function( fn ) { fn( { type : 'click' } ) } );
check( 'Reload loads the frame at once, without waiting for a message', w.reloads === 1 && w.timers.length === 0 );

// --- the switch ---------------------------------------------------------------------

w = windowOf( {} );
check( 'the switch is the design system\'s, in the bar, on to begin with, with the words of the window',
	w.switchField !== null && w.nodes['admin-follow-switch'].children[0] === w.switchField.label && w.switchField.spec.checked === true
	&& w.switchField.spec.label === '/_admin/follow/label/switch' && w.switchField.label.getAttribute('title') === '/_admin/follow/hint/switch' );
w.turn( false );
check( 'turned off, it keeps that in the browser', w.store['nino-admin-follow'] === '0' );
w.turn( true );
check( '...and on again', w.store['nino-admin-follow'] === '1' );

w = windowOf( { stored : '0' } );
check( 'a window opened later finds it as it was left: off', w.switchField.spec.checked === false );
w.say( { kind : 'routes', uri : '/blog', at : 1 } );
w.tick();
check( '...and behaves so from the first message', w.reloads === 1 && w.nodes['admin-follow-frame'].src === '/' );
check( 'a store that holds anything else is on', windowOf( { stored : '1' } ).switchField.spec.checked === true && windowOf( { stored : 'x' } ).switchField.spec.checked === true );

w = windowOf( { storage : 'throws' } );
check( 'a browser that keeps nothing starts on', w.switchField.spec.checked === true );
w.turn( false );
w.say( { kind : 'routes', uri : '/blog', at : 1 } );
w.tick();
check( '...and still switches, until the page is loaded again, without a word', w.reloads === 1 );

// --- a browser without the channel ----------------------------------------------------

w = windowOf( { channel : false } );
check( 'a browser without BroadcastChannel keeps a frame with a button: nothing listens, nothing throws', w.channels.length === 0 && w.nodes['admin-follow-switch'].children.length === 1 );
w.nodes['admin-follow-reload'].listeners.click.forEach( function( fn ) { fn( { type : 'click' } ) } );
check( '...and the button loads the frame', w.reloads === 1 );

// --- the window lets go of its opener -----------------------------------------------

w = windowOf( {} );
check( 'the window does not keep the workbench that opened it: a page in the frame that leaves the site by target="_top" cannot take the workbench along', w.page.opener === null );
check( '...and a browser that will not let go of it costs nothing', ( function() { try { windowOf( { opener : 'refused' } ) } catch( e ) { return false } return true } )() );

// --- the theme of the workbench -------------------------------------------------------

w = windowOf( { theme : 'dark' } );
check( 'the window is in the theme the workbench was left in: dark', w.root.attributes['data-theme'] === 'dark' && w.body.className === 'theme-dark' );
w = windowOf( { theme : 'light' } );
check( '...or light', w.root.attributes['data-theme'] === 'light' && w.body.className === 'theme-light' );
check( '...and the system\'s own where none was chosen, or anything else was stored, or the browser keeps nothing', [ windowOf( {} ), windowOf( { theme : '' } ), windowOf( { theme : 'sepia' } ), windowOf( { storage : 'throws' } ) ].every( function( bound ) { return 'data-theme' in bound.root.attributes === false && bound.body.className === '' } ) );

// --- the page the frame starts at ------------------------------------------------------

w = windowOf( { dataSrc : '/', uriStored : '/blog/post' } );
check( 'opened without an address, the frame starts at the page it showed last - one assignment, so one request', w.started.join() === '/blog/post' );
w = windowOf( { dir : '/sites/a', dataSrc : '/sites/a/', uriStored : '/blog/post' } );
check( '...below the directory the installation is in', w.started.join() === '/sites/a/blog/post' );
w = windowOf( { dataSrc : '/contact', uriStored : '/blog/post', search : '?follow&uri=/contact' } );
check( 'an address that was asked for wins over the page shown last', w.started.join() === '/contact' );
w = windowOf( { dataSrc : '/', uriStored : '/blog/post', search : '?x=1&uri=/' } );
check( '...wherever the query holds it, and the home page is an address too', w.started.join() === '/' );
check( 'with no page kept the frame starts at the address the page names, the home page', windowOf( { dataSrc : '/' } ).started.join() === '/' && windowOf( { dataSrc : '/', storage : 'throws' } ).started.join() === '/' );
check( 'a page kept that is no path of this site - or the workbench - is not gone to',
	[ '//evil.example/x', 'http://x/', '/_admin/follow', '/_admin', '', 'blog', '/a<b' ].every( function( bad ) { return windowOf( { dataSrc : '/', uriStored : bad } ).started.join() === '/' } ) );

w = windowOf( {} );
w.loaded( '/about' );
check( 'the page the frame has loaded is kept, as a path', w.store['nino-admin-follow-uri'] === '/about' );
w = windowOf( { dir : '/sites/a' } );
w.loaded( '/sites/a/about' );
check( '...without the directory the installation is in', w.store['nino-admin-follow-uri'] === '/about' );
w = windowOf( {} );
w.loaded( '/about' );
[ '/_admin/follow', '/_admin', '/a b', 'blank' ].forEach( function( bad ) { w.loaded( bad ) } );
check( '...and what is none to go to - the workbench itself, about:blank - is not kept in its place', w.store['nino-admin-follow-uri'] === '/about' );
w.loaded( 'blank' );
check( '...and a page that is no path - about:blank - leaves no path in the bar', w.nodes['admin-follow-path'].textContent === '' );
w = windowOf( { storage : 'throws' } );
w.loaded( '/about' );
check( 'a browser that keeps nothing keeps nothing, and the bar still says the page', w.nodes['admin-follow-path'].textContent === '/about' );

// --- the workbench's own address is no page of the website --------------------------------

[ '/_admin', '/_admin/', '/_admin/follow', '/_admin?x=1', '/_admin#top', '/_ADMIN/x', "/a\x01b", "/a\x1fb", "/a\x7fb", "/a\x00b" ].forEach( function( bad ) {
	check( 'a message that names it, or a control character, names no page: '+ JSON.stringify( bad ), w.admin.isPath( bad ) === false );
} );
check( '...while a page that only starts like it is one', w.admin.isPath( '/_administrator' ) === true && w.admin.isPath( '/blog/_admin' ) === true && w.admin.isPath( '/aäb' ) === true );
w = windowOf( {} );
w.say( { kind : 'routes', uri : '/_admin/follow', at : 1 } );
w.tick();
check( 'a message that names the workbench loads the page the frame shows, and does not take the frame there', w.reloads === 1 && w.assigned.length === 0 );

done();
