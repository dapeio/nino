/**
 *	Nino										A compact filesystembased php framework
 *	admin-navs-js-smoke.js	DOM-light checks for the Navigations panel's script
 *													(_nino/Nino/Modules/Navigation/assets/admin.js).
 *
 *													A menu is a working copy: ↑, ↓, × and Add change the
 *													entries on screen and write nothing, Save posts the
 *													whole running order in one request, and what was
 *													changed and not saved is never lost to a click on an
 *													arrow, to the panel being shown again, or to the back
 *													link. The checks below are about exactly that.
 *
 *	Usage: node tests/admin-navs-js-smoke.js
 */

'use strict';

const fs 		= require('fs');
const path	= require('path');
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

function source( relative ) {
	return fs.readFileSync( path.join( __dirname, '..', relative ), 'utf8' );
}

/**
 *	The fills of one text file, key => value - enough of the php to read
 *	a `'[[/key]]' => 'value',` line
 */
function fills( relative ) {
	const map = {};
	const re = /'\[\[(\/_admin\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g;
	const text = source( relative );
	let match;
	while( ( match = re.exec( text ) ) )
		map[match[1]] = match[2].replace( /\\'/g, "'" );
	return map;
}


// --- an element stand-in, the same shape the Routes test builds -------------

function classList() {
	const values = new Set();
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
	};
}

function findAll( root, predicate ) {
	const found = [];
	( function walk( node ) {
		( node.children || [] ).forEach( function( child ) {
			if( predicate( child ) === true )
				found.push( child );
			walk( child );
		} );
	} )( root );
	return found;
}

function element( tag ) {
	const el = {
		tagName : String( tag ).toUpperCase(),
		className : '',
		textContent : '',
		title : '',
		type : '',
		href : '',
		id : '',
		disabled : false,
		style : {},
		attributes : {},
		dataset : {},
		children : [],
		listeners : {},
		value : '',
		appendChild : function( child ) { el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		querySelector : function( selector ) { return el.querySelectorAll( selector )[0] ?? null },
		querySelectorAll : function( selector ) {
			// Only the class selector the shell's bar lookup uses
			const match = /^\.([\w-]+)$/.exec( selector );
			return findAll( el, function( child ) { return match !== null && child.className === match[1] } );
		},
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
	};
	el.classList = classList();
	// A select's value is its selected option's, the way a browser reads it
	if( el.tagName === 'SELECT' )
		Object.defineProperty( el, 'value', {
			get : function() {
				const chosen = el.children.filter( function( option ) { return option.selected === true } ).pop() ?? el.children[0];
				return chosen === undefined ? '' : chosen.value;
			},
			set : function( value ) { el.children.forEach( function( option ) { option.selected = option.value === value } ) },
		} );
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

// Tolerates a control that is not on screen, so a panel that threw its own
// buttons away reports as failed checks rather than as a crashed test
function fire( el, name ) {
	if( el === null || el === undefined )
		return;
	( el.listeners[name] || [] ).forEach( function( fn ) { fn( { preventDefault : function() {} } ) } );
}


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_nino/Nino/Modules/Navigation/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

/**
 *	One panel in its own context. `shell` is the registry the workbench's
 *	script.js would provide, or null for a shell that has none
 */
function load( shell ) {

	const list = element('div');
	list.id = 'navs-list';
	const form = element('div');
	form.id = 'navs-form';
	form.classList.add('admin-hidden');

	const requests = [];
	const confirms = [];
	const box = { confirmAnswer : false };

	const Nino = {
		admin : { formToolbar : function( backLink ) { const bar = element('div'); bar.appendChild( backLink ); return bar } },
		events : { bindCallback : function() {} },
		http : { sendRequest : function( uri, method, callback, data ) {
			requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
		} },
		content : { getText : text },
	};
	if( shell !== null )
		Nino.admin.dirty = shell;

	const sandbox = {
		console : console,
		document : {
			createElement : element,
			getElementById : function( id ) {
				if( id === 'navs-list' ) return list;
				if( id === 'navs-form' ) return form;
				return findAll( list, function( el ) { return el.id === id } )[0] ?? findAll( form, function( el ) { return el.id === id } )[0] ?? null;
			},
			documentElement : null,
			body : null,
		},
		Nino : Nino,
	};
	sandbox.window = { Nino : Nino, location : { hash : '' }, confirm : function( message ) { confirms.push( message ); return box.confirmAnswer } };

	const context = vm.createContext( sandbox );
	vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
	vm.runInContext( source('_nino/Nino/Modules/Navigation/assets/admin.js'), context, { filename : 'admin.js' } );

	return {
		panel : Nino.admin.navs,
		list : list,
		form : form,
		requests : requests,
		confirms : confirms,
		box : box,
		/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
		answer : function( status, body ) { requests[requests.length - 1].callback( { status : status, responseJSON : body } ) },
		field : function( id ) { return findAll( form, function( el ) { return el.id === id } )[0] },
		byClass : function( className ) { return findAll( form, function( el ) { return el.className === className } ) },
		/** The entries on screen, as their row labels without the position */
		entries : function() { return findAll( form, function( el ) { return el.className === 'admin-page-row' } ).map( function( li ) { return li.children[0].textContent.replace( /^\d+\. /, '' ).replace( /\s+/g, ' ' ).trim() } ) },
		/** One entry's ↑, ↓ or × */
		button : function( index, which ) {
			const row = findAll( form, function( el ) { return el.className === 'admin-page-row' } )[index];
			return row.children[1].children[ { up : 0, down : 1, remove : 2 }[which] ];
		},
	};
}

const ROUTES = [
	{ httpUri : '/', uri : '/home', label : 'Start', named : true, runtime : false },
	{ httpUri : '/about', uri : '/about', label : 'About', named : true, runtime : false },
	{ httpUri : '/contact', uri : '/contact', label : 'Contact', named : true, runtime : false },
	{ httpUri : '/blog', uri : '/blog', label : '/blog', named : false, runtime : true },
];

function payload( entries ) {
	return {
		navs : [ { key : 'main', entries : entries.map( function( uri ) { return ROUTES.find( function( route ) { return route.httpUri === uri } ) } ) }, { key : 'footer', entries : [] } ],
		routes : ROUTES,
		active : true,
	};
}

/** A panel that has read its list and has the menu "main" open */
function opened( shell ) {
	const t = load( shell );
	t.panel.init();
	t.answer( 200, payload( [ '/', '/about' ] ) );
	t.panel._openForm( t.panel._navs[0] );
	return t;
}

console.log('Admin Navigations');


// --- reading ---------------------------------------------------------------

{
	const t = load( null );
	t.panel.init();
	check( 'the panel asks for the menus', t.requests.length === 1 && t.requests[0].action === 'navs/list' );
	t.answer( 200, payload( [ '/', '/about' ] ) );
	check( 'a menu is a row with the number of its entries', findAll( t.list, function( el ) { return el.tagName === 'A' } ).map( function( a ) { return a.textContent } ).join() === 'main (2),footer (0)' );
}


// --- the working copy ------------------------------------------------------

{
	const t = opened( null );
	const sent = t.requests.length;

	check( 'an opened menu shows its entries in their running order', t.entries().join() === 'Start (/),About (/about)' );
	check( 'the status line says nothing while nothing is changed', t.field('navs-form-msg').textContent === '' && t.panel._isDirty() === false );

	// A key typed above the entries must survive a click below
	t.field('navs-form-key').value = 'primary';

	t.button( 1, 'up' ).listeners.click[0]();
	check( '↑ moves the entry on screen', t.entries().join() === 'About (/about),Start (/)' );
	t.button( 0, 'down' ).listeners.click[0]();
	check( '↓ moves it back', t.entries().join() === 'Start (/),About (/about)' );
	check( 'the first entry cannot go up, the last cannot go down', t.button( 0, 'up' ).disabled === true && t.button( 1, 'down' ).disabled === true );

	t.button( 0, 'remove' ).listeners.click[0]();
	check( '× takes the entry out on screen', t.entries().join() === 'About (/about)' );

	const addSelect = t.field('navs-form-add');
	check( 'the picker offers the routes that are not in the menu yet - the ones only a feature registers among them - and the one already in it is not', addSelect.children.map( function( option ) { return option.value } ).join() === ',/,/contact,/blog' );
	addSelect.value = '/contact';
	addSelect.listeners.change[0]();
	findAll( t.form, function( el ) { return el.textContent === text('/_admin/navs/label/addbtn') } )[0].listeners.click[0]();
	check( 'Add puts the chosen route at the end of the copy', t.entries().join() === 'About (/about),Contact (/contact)' );

	check( 'not one of these touched the server', t.requests.length === sent );
	check( 'the key that was typed survived every one of them', t.field('navs-form-key').value === 'primary' );
	check( 'the status line says there is something to save', t.field('navs-form-msg').textContent === text('/_admin/navs/msg/unsaved') && text('/_admin/navs/msg/unsaved') !== '' );
	check( 'the panel knows its copy differs from what is saved', t.panel._isDirty() === true );

	// Save: the whole order, one request
	fire( findAll( t.form, function( el ) { return el.tagName === 'FORM' } )[0], 'submit' );
	check( 'Save sends one request with the complete order, under the key that was typed', t.requests.length === sent + 1 && t.requests[sent].action === 'navs/save'
		&& JSON.stringify( t.requests[sent].payload ) === JSON.stringify( { originalKey : 'main', key : 'primary', entries : [ '/about', '/contact' ] } ) );
	t.answer( 200, { navs : [ { key : 'primary', entries : [ ROUTES[1], ROUTES[2] ] }, { key : 'footer', entries : [] } ], routes : ROUTES, active : true } );
	check( 'the answer is drawn: the copy is the saved menu now', t.entries().join() === 'About (/about),Contact (/contact)' && t.panel._isDirty() === false && t.panel._currentKey === 'primary' );
	check( '...and says it was saved', t.field('navs-form-msg').textContent === text('/_admin/common/msg/saved') );
}


// --- a save that fails ----------------------------------------------------

{
	const t = opened( null );
	t.button( 1, 'up' ).listeners.click[0]();
	fire( findAll( t.form, function( el ) { return el.tagName === 'FORM' } )[0], 'submit' );
	t.answer( 404, { error : 'unknown route: "/about"', code : 'navs_unknown_route', params : [ '/about' ] } );
	check( 'a refused save keeps the copy as it is and says why', t.entries().join() === 'About (/about),Start (/)' && t.panel._isDirty() === true
		&& t.field('navs-form-msg').textContent.includes('/about') );
}


// --- the picker -------------------------------------------------------------

{
	const t = opened( null );
	const select = t.field('navs-form-add');
	const addBtn = findAll( t.form, function( el ) { return el.textContent === text('/_admin/navs/label/addbtn') } )[0];
	check( 'the picker starts on an empty choice, not on the first route', select.children[0].value === '' && select.children[0].selected === true && select.value === ''
		&& select.children[0].textContent === text('/_admin/navs/label/choose') && text('/_admin/navs/label/choose') !== '' );
	check( '...and Add waits for a choice', addBtn.disabled === true );
	select.value = '/blog';
	select.listeners.change[0]();
	check( '...which enables it', addBtn.disabled === false );
	select.value = '';
	select.listeners.change[0]();
	check( '...and choosing nothing again disables it', addBtn.disabled === true );
	check( 'a route that is not named yet is offered with the marker, a runtime one included', select.children.some( function( option ) { return option.value === '/blog' && option.textContent.includes( text('/_admin/navs/label/unnamed-short') ) } ) );
}


// --- the back link ---------------------------------------------------------

{
	const t = opened( null );
	const back = t.byClass('nino-admin-back-link')[0] ?? findAll( t.form, function( el ) { return el.className === 'nino-admin-back-link' } )[0];

	fire( back, 'click' );
	check( 'the back link leaves a menu that holds nothing unsaved without asking', t.confirms.length === 0 && t.form.classList.contains('admin-hidden') === true );

	t.panel._openForm( t.panel._navs[0] );
	t.button( 1, 'up' ).listeners.click[0]();
	t.box.confirmAnswer = false;
	fire( findAll( t.form, function( el ) { return el.className === 'nino-admin-back-link' } )[0], 'click' );
	check( 'with changes that are not saved it asks first, in the workbench\'s words', t.confirms.length === 1 && t.confirms[0] === text('/_admin/navs/confirm/discard') && text('/_admin/navs/confirm/discard') !== '' );
	check( '...and stays on the menu, copy and all, when the answer is no', t.form.classList.contains('admin-hidden') === false && t.entries().join() === 'About (/about),Start (/)' );

	t.box.confirmAnswer = true;
	fire( findAll( t.form, function( el ) { return el.className === 'nino-admin-back-link' } )[0], 'click' );
	check( '...and leaves, with the copy dropped, when it is yes', t.form.classList.contains('admin-hidden') === true && t.panel._dirty === false && t.panel._entries.length === 0 );

	// A typed id is unsaved input as well
	t.confirms.length = 0;
	t.box.confirmAnswer = false;
	t.panel._openForm( t.panel._navs[0] );
	t.field('navs-form-key').value = 'other';
	fire( findAll( t.form, function( el ) { return el.className === 'nino-admin-back-link' } )[0], 'click' );
	check( 'an id that was typed and not saved asks as well', t.confirms.length === 1 );
}


// --- showing the panel again -----------------------------------------------

{
	const t = opened( null );
	const before = t.requests.length;

	t.panel.showCurrent();
	check( 'showing the panel again reads the menus and routes again', t.requests.length === before + 1 && t.requests[before].action === 'navs/list' );
	t.answer( 200, { navs : [ { key : 'main', entries : [ ROUTES[1] ] }, { key : 'footer', entries : [] } ], routes : ROUTES.concat( [ { httpUri : '/news', uri : '/news', label : 'News', named : true, runtime : true } ] ), active : true } );
	check( '...and draws the open menu from the answer, when it holds nothing unsaved', t.entries().join() === 'About (/about)'
		&& t.field('navs-form-add').children.some( function( option ) { return option.value === '/news' } ) );

	// ...but never over a copy somebody changed
	t.button( 0, 'remove' ).listeners.click[0]();
	t.field('navs-form-key').value = 'typed';
	const again = t.requests.length;
	t.panel.showCurrent();
	t.answer( 200, { navs : [ { key : 'main', entries : [ ROUTES[0], ROUTES[1], ROUTES[2] ] }, { key : 'footer', entries : [] } ], routes : ROUTES.concat( [ { httpUri : '/shop', uri : '/shop', label : 'Shop', named : true, runtime : true } ] ), active : true } );
	check( 'a copy with changes is read over nothing: entries and typed id stay', t.requests.length === again + 1 && t.entries().join() === '' && t.field('navs-form-key').value === 'typed' && t.panel._isDirty() === true );
	check( '...while the routes it can still pick from are the new ones', t.field('navs-form-add').children.some( function( option ) { return option.value === '/shop' } ) && t.field('navs-form-add').children.some( function( option ) { return option.value === '/news' } ) === false );
	check( '...and the list of menus has the saved state', findAll( t.list, function( el ) { return el.tagName === 'A' } ).map( function( a ) { return a.textContent } )[0] === 'main (3)' );

	// A read that fails changes nothing
	t.panel.showCurrent();
	t.answer( 500, null );
	check( 'a read that fails leaves the copy alone too', t.entries().join() === '' && t.panel._isDirty() === true );
}


// --- the shell -------------------------------------------------------------

{
	const registered = {};
	const refreshes = [];
	const shell = { register : function( name, entry ) { registered[name] = entry }, refresh : function() { refreshes.push( 1 ) } };
	const t = opened( shell );

	check( 'the panel registers with the shell under its own name, with the four things the shell asks for', typeof registered.navs === 'object'
		&& [ 'isDirty', 'save', 'discard', 'bar' ].every( function( name ) { return typeof registered.navs[name] === 'function' } ) );
	check( 'nothing is unsaved after the menu is opened', registered.navs.isDirty() === false );

	t.button( 1, 'up' ).listeners.click[0]();
	check( 'a change to the copy is unsaved input for the shell, and the shell is told', registered.navs.isDirty() === true && refreshes.length > 0 );
	check( '...and the panel does not say "unsaved" a second time next to the shell\'s own marker', t.field('navs-form-msg').textContent === '' );
	check( 'the marker goes in the action bar of the form', registered.navs.bar() === t.byClass('nino-admin-actionbar')[0] );

	// With the shell there, it asks in its own dialog, so the panel does not ask a second time
	t.box.confirmAnswer = false;
	fire( findAll( t.form, function( el ) { return el.className === 'nino-admin-back-link' } )[0], 'click' );
	check( 'a shell that has the registry has asked already - the panel does not ask again', t.confirms.length === 0 && t.form.classList.contains('admin-hidden') === true );

	// Save from the shell reports how it went
	t.panel._openForm( t.panel._navs[0] );
	t.button( 1, 'up' ).listeners.click[0]();
	const outcomes = [];
	registered.navs.save( function( ok ) { outcomes.push( ok ) } );
	t.answer( 500, null );
	check( 'the shell\'s Save that fails reports false and keeps the copy', outcomes.join() === 'false' && registered.navs.isDirty() === true );
	registered.navs.save( function( ok ) { outcomes.push( ok ) } );
	t.answer( 200, payload( [ '/about', '/' ] ) );
	check( '...one that goes through reports true, and nothing is unsaved afterwards', outcomes.join() === 'false,true' && registered.navs.isDirty() === false );

	t.button( 1, 'up' ).listeners.click[0]();
	registered.navs.discard();
	check( 'Discard lets the changes go', registered.navs.isDirty() === false );
}

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
