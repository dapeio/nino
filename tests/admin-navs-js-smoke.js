/**
 *	Nino										A compact filesystembased php framework
 *	admin-navs-js-smoke.js	DOM-light checks for the Navigations panel's script
 *													(_nino/Nino/Modules/Navigation/assets/admin.js).
 *
 *													A menu is a working copy: up, down, remove and Add
 *													change the entries on screen and write nothing, Save
 *													posts the whole running order in one request - and
 *													the name and the key with it -, and what was changed
 *													and not saved is never lost to a click on a row, to
 *													the panel being shown again, or to the back link. A
 *													menu has a name, which is optional, and its key: the
 *													list names it by the name and shows the key quietly
 *													beside it. Every view has an address (#navs,
 *													#navs/@new, #navs/<key>), and a save leaves the person
 *													in the menu. What is wrong is said at the field, the
 *													server's refusals too.
 *
 *	Usage: node tests/admin-navs-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const fs 		= require('fs');
const { check, done, fills, load, element, findAll, fire } = require( './harness.js' );

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
function build( shell, hash ) {

	// The pane the shell draws: the name of the panel in its head, and the two levels beside it
	const pane = element('div');
	pane.dataset.panel = 'navs';
	const head = element('div');
	head.className = 'admin-panel-head';
	const paneTitle = element('h2');
	paneTitle.className = 'admin-panel-title';
	head.appendChild( paneTitle );
	pane.appendChild( head );
	const list = element('div');
	list.id = 'navs-list';
	pane.appendChild( list );
	const form = element('div');
	form.id = 'navs-form';
	form.classList.add('admin-hidden');
	pane.appendChild( form );

	const requests = [];
	// What the panel said it wrote (see Nino.admin.touch() of the shell)
	const touched = [];
	const toasts = [];
	const questions = [];
	const written = [];
	const moved = [];
	const leaves = [];
	const box = { questionAnswer : false, hash : hash ?? { panel : 'navs', parts : [] } };

	const everything = function() {
		return [ list, form ].concat( findAll( list, function() { return true } ), findAll( form, function() { return true } ) );
	};

	const Nino = {
		admin : {
			touch : function( topic ) { touched.push( topic ) }, stamp : function() { return 0 },
			router : {
				current : function() { return box.hash },
				go : function( panel, parts ) { moved.push( JSON.stringify( [ panel ].concat( parts ) ) ) },
				set : function( panel, parts ) { written.push( JSON.stringify( [ panel ].concat( parts ) ) ) },
				leave : function( names, open, proceed, resync ) { leaves.push( { open : open, proceed : proceed, resync : resync } ) },
				href : function( name, parts ) { return '#'+ [ name ].concat( parts || [] ).map( encodeURIComponent ).join('/') },
			},
		},
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
			createElementNS : function( space, tag ) { return element( tag ) },
			createTextNode : function( content ) { return Object.assign( element('#text'), { textContent : String( content ) } ) },
			getElementById : function( id ) { return everything().find( function( el ) { return el.id === id } ) ?? null },
			documentElement : null,
			body : null,
			get activeElement() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null },
		},
		Nino : Nino,
	};
	sandbox.window = { Nino : Nino, location : { hash : '' }, setTimeout : function() { return 1 }, clearTimeout : function() {} };

	const context = vm.createContext( sandbox );
	load( '_admin/assets/Nino.admin.js', context );

	Nino.adminUi.say = function( words ) { toasts.push( words ) };
	Nino.adminUi.confirm = function( level, options ) {
		questions.push( Object.assign( { level : level }, options ) );
		options.onChoose( box.questionAnswer );
		return true;
	};
	load( '_nino/Nino/Modules/Navigation/assets/admin.js', context );

	const entryRows = function() {
		return findAll( form, function( el ) { return el.classList.contains('nino-admin-list-row') } );
	};

	return {
		panel : Nino.admin.navs,
		list : list,
		form : form,
		title : paneTitle,
		requests : requests,
		questions : questions,
		touched : touched,
		toasts : toasts,
		written : written,
		moved : moved,
		leaves : leaves,
		box : box,
		/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
		answer : function( status, body ) { requests[requests.length - 1].callback( { status : status, responseJSON : body } ) },
		field : function( id ) { return findAll( form, function( el ) { return el.id === id } )[0] },
		/** The rows of the menus on the list */
		menus : function() { return findAll( list, function( el ) { return el.classList.contains('nino-admin-list-row') } ) },
		/** The title and the quiet line of a row of the list */
		menuTitle : function( li ) { return findAll( li, function( el ) { return el.tagName === 'STRONG' } )[0].textContent },
		menuDetail : function( li ) { return findAll( li, function( el ) { return el.tagName === 'SMALL' } )[0].textContent },
		link : function( li ) { return findAll( li, function( el ) { return el.classList.contains('nino-admin-row-open') } )[0] },
		/** The entries on screen, as the title and the paths of each row */
		entries : function() { return entryRows().map( function( li ) { return findAll( li, function( el ) { return el.tagName === 'STRONG' } )[0].textContent+ ' ('+ findAll( li, function( el ) { return el.tagName === 'SMALL' } )[0].textContent+ ')' } ) },
		/** One entry's up, down or remove button */
		button : function( index, which ) {
			return findAll( entryRows()[index], function( el ) { return el.classList.contains('nino-admin-row-actions') } )[0].children[ { up : 0, down : 1, remove : 2 }[which] ];
		},
		theForm : function() { return findAll( form, function( el ) { return el.tagName === 'FORM' } )[0] },
		back : function() { return findAll( form, function( el ) { return el.classList.contains('nino-admin-back-link') } )[0] },
		bar : function() { return findAll( form, function( el ) { return el.classList.contains('nino-admin-actionbar') } )[0] },
		barButtons : function() { return findAll( form, function( el ) { return el.classList.contains('nino-admin-actionbar') } )[0].children.filter( function( el ) { return el.tagName === 'BUTTON' } ) },
		errors : function() { return findAll( form, function( el ) { return el.className === 'nino-admin-field-error' } ) },
		addButton : function() { return findAll( form, function( el ) { return el.id === 'navs-form-addbtn' } )[0] },
		newButton : function() { return findAll( list, function( el ) { return el.classList.contains('nino-admin-btn-primary') } )[0] },
	};
}

const ROUTES = [
	{ httpUri : '/', uri : '/home', label : 'Start', named : true, runtime : false },
	{ httpUri : '/about', uri : '/about', label : 'About', named : true, runtime : false },
	{ httpUri : '/contact', uri : '/contact', label : 'Contact', named : true, runtime : false },
	{ httpUri : '/blog', uri : '/blog', label : '/blog', named : false, runtime : true },
];

function payload( entries, name ) {
	return {
		navs : [ { key : 'main', name : name ?? '', entries : entries.map( function( uri ) { return ROUTES.find( function( route ) { return route.httpUri === uri } ) } ) }, { key : 'footer', name : '', entries : [] } ],
		routes : ROUTES,
	};
}

/** A panel that has read its list and has the menu "main" open */
function opened( shell, name ) {
	const t = build( shell );
	t.panel.init();
	t.answer( 200, payload( [ '/', '/about' ], name ) );
	fire( t.link( t.menus()[0] ), 'click' );
	return t;
}

console.log('Admin Navigations');


// --- reading ---------------------------------------------------------------

{
	const t = build( null );
	t.panel.init();
	check( 'the panel asks for the menus', t.requests.length === 1 && t.requests[0].action === 'navs/list' );
	t.answer( 200, payload( [ '/', '/about' ], 'Hauptmenü' ) );
	check( 'a menu is a row: its name over its key and the number of its entries, quietly', t.menus().length === 2 && t.menuTitle( t.menus()[0] ) === 'Hauptmenü' && t.menuDetail( t.menus()[0] ) === 'main · '+ text('/_admin/navs/list/entries').replace( '%d', '2' ) );
	check( '...a menu without a name is shown by its key, and says only how many entries it has', t.menuTitle( t.menus()[1] ) === 'footer' && t.menuDetail( t.menus()[1] ) === text('/_admin/navs/list/entries-none') && text('/_admin/navs/list/entries-none') !== '' );
	check( '...a row is a link with the address of its menu, which the click opens through the router', t.menus().every( function( li ) { return t.link( li ).tagName === 'A' && t.link( li ).href === '#navs/'+ li.dataset.row } ) );
	check( 'a list that is read shows the list, at its address', t.list.classList.contains('admin-hidden') === false && t.form.classList.contains('admin-hidden') === true && t.written.at(-1) === '["navs"]' );
	check( '...and the New navigation button is the one main button of the list', t.newButton() !== undefined && t.newButton().textContent === text('/_admin/navs/label/new') );

	// The panel is there only while the Navigation module is, so the list has
	// nothing to say about the module - whatever else an answer carries
	t.panel._apply( Object.assign( payload( [ '/' ] ), { active : false } ) );
	check( 'the list draws the menus and nothing about the module', findAll( t.list, function( el ) { return el.classList.contains('nino-admin-hint') } ).length === 0 );

	t.panel._apply( payload( [ '/' ] ) );
	check( '...a menu with one entry says so in the singular', t.menuDetail( t.menus()[0] ) === text('/_admin/navs/list/entries-one') && text('/_admin/navs/list/entries-one') !== text('/_admin/navs/list/entries') );

	t.panel._apply( { navs : [], routes : ROUTES } );
	check( 'a project with no menu has the empty state, as a box, and still the button', findAll( t.list, function( el ) { return el.classList.contains('nino-admin-empty') } ).length === 1 && t.newButton() !== undefined );
}


// --- the addresses ---------------------------------------------------------

{
	const t = build( null, { panel : 'navs', parts : [ 'main' ] } );
	t.panel.init();
	t.answer( 200, payload( [ '/', '/about' ] ) );
	check( 'a fresh load on #navs/<key> opens the menu, at its address, with its entries', t.form.classList.contains('admin-hidden') === false && t.field('navs-form-key').value === 'main' && t.entries().length === 2 && t.written.at(-1) === '["navs","main"]' );
}

{
	const t = build( null, { panel : 'navs', parts : [ '@new' ] } );
	t.panel.init();
	t.answer( 200, payload( [ '/' ] ) );
	check( 'the main button of a form that creates is worded as creating, another fill than Save', t.barButtons().at(-1).textContent === text('/_admin/navs/label/create') && text('/_admin/navs/label/create') !== text('/_admin/common/label/save') && text('/_admin/navs/label/create') !== '' );
	check( 'a fresh load on #navs/@new opens the form of a new menu: an empty key, no running order yet, nothing to delete', t.form.classList.contains('admin-hidden') === false && t.field('navs-form-key').value === '' && t.entries().length === 0
		&& t.field('navs-form-add') === undefined && t.barButtons().length === 1 && t.written.at(-1) === '["navs","@new"]' );
}

{
	const t = build( null, { panel : 'navs', parts : [ 'new' ] } );
	t.panel.init();
	t.answer( 200, { navs : [ { key : 'new', name : '', entries : [] } ], routes : ROUTES } );
	check( 'a menu called new has an address of its own: a fresh load on #navs/new opens that menu, not the form of a new one', t.menus()[0].dataset.row === 'new' && t.link( t.menus()[0] ).href === '#navs/new'
		&& t.field('navs-form-key').value === 'new' && t.field('navs-form-add') !== undefined );
}

{
	const t = build( null, { panel : 'navs', parts : [ 'nothing' ] } );
	t.panel.init();
	t.answer( 200, payload( [ '/' ] ) );
	check( 'an address that names no menu is the list', t.list.classList.contains('admin-hidden') === false && t.written.at(-1) === '["navs"]' );
}

{
	const t = build( null );
	t.panel.init();
	t.answer( 200, payload( [ '/', '/about' ] ) );
	fire( t.link( t.menus()[1] ), 'click' );
	check( 'a row opens its menu through the router and writes its address, and the focus goes into the form', t.moved.at(-1) === '["navs","footer"]' && t.written.at(-1) === '["navs","footer"]' && t.form.classList.contains('admin-hidden') === false
		&& t.list.classList.contains('admin-hidden') === true && t.back().focused === true );
	check( '...the menu of that row is the one that is open', t.field('navs-form-key').value === 'footer' );

	fire( t.back(), 'click' );
	check( 'Back shows the list, writes its address and puts the focus on the row that was opened', t.form.classList.contains('admin-hidden') === true && t.link( t.menus()[1] ).focused === true && t.moved.at(-1) === '["navs"]' && t.panel._currentKey === null );

	fire( t.newButton(), 'click' );
	check( 'a new menu opens the same way, at #navs/@new', t.moved.at(-1) === '["navs","@new"]' && findAll( t.form, function( el ) { return el.focused === true } ).length === 1 );
	fire( t.back(), 'click' );
	check( '...and Back returns to the New navigation button', t.newButton().focused === true );

	// A step through the history: the router is asked to leave, and the panel moves when it is let go
	t.box.hash = { panel : 'navs', parts : [ 'main' ] };
	t.panel.showCurrent();
	check( 'a showing whose address names a menu asks the router to leave the level on screen and moves when it is let go', t.leaves.length === 1 && t.leaves[0].open === false && t.form.classList.contains('admin-hidden') === true );
	t.leaves[0].proceed();
	check( '...to the menu the address names', t.form.classList.contains('admin-hidden') === false && t.field('navs-form-key').value === 'main' );
	t.leaves.length = 0;
	t.panel.showCurrent();
	check( 'an address that is the level on screen asks nothing', t.leaves.length === 0 );
	t.box.hash = { panel : 'users', parts : [] };
	t.panel.showCurrent();
	check( 'an address of another panel leaves the level in memory as it is', t.leaves.length === 0 && t.form.classList.contains('admin-hidden') === false );
}


// --- the name --------------------------------------------------------------

{
	const t = opened( null, 'Hauptmenü' );
	check( 'the form of a menu has its name and its key, the key required, and both behind a (?) with their help and no sentence of their own under them', t.field('navs-form-name').value === 'Hauptmenü' && t.field('navs-form-key').value === 'main'
		&& t.field('navs-form-key').attributes['aria-required'] === 'true' && t.field('navs-form-name').attributes['aria-required'] === undefined
		&& findAll( t.form, function( el ) { return el.classList.contains('nino-admin-help') } ).length >= 2 && findAll( t.form, function( el ) { return el.classList.contains('nino-admin-hint') } ).length === 0 );
	const server = fs.readFileSync( __dirname+ '/../_nino/Nino/Modules/Navigation/Admin/Admin.php', 'utf8' ).match( /public const int NAME_MAX = (\d+);/ )[1];
	check( 'the name takes as many characters as the server does', t.field('navs-form-name').maxLength === Number( server ) );

	t.field('navs-form-name').value = '  Kopfmenü ';
	fire( t.theForm(), 'submit' );
	const sent = t.requests.at(-1);
	check( 'Save sends the name, trimmed, with the key - and no order: the entries were not touched, so the routes keep their memberships as they are', sent.action === 'navs/save' && JSON.stringify( sent.payload ) === JSON.stringify( { originalKey : 'main', key : 'main', name : 'Kopfmenü' } ) );
	t.answer( 200, payload( [ '/', '/about' ], 'Kopfmenü' ) );
	check( 'a name that is saved is the name of the list', t.menuTitle( t.menus()[0] ) === 'Kopfmenü' && t.field('navs-form-name').value === 'Kopfmenü' );

	fire( t.button( 1, 'up' ), 'click' );
	fire( t.theForm(), 'submit' );
	check( 'a save after a move sends the whole order', JSON.stringify( t.requests.at(-1).payload.entries ) === '["/about","/"]' );
	t.answer( 200, payload( [ '/about', '/' ], 'Kopfmenü' ) );

	t.field('navs-form-name').value = '';
	fire( t.theForm(), 'submit' );
	check( 'an empty name is sent as the empty name: it takes the name away', t.requests.at(-1).payload.name === '' );
	t.answer( 200, payload( [ '/', '/about' ], '' ) );
	check( '...and the menu is shown by its key again', t.menuTitle( t.menus()[0] ) === 'main' );

	t.field('navs-form-name').value = 'x\ny';
	fire( t.theForm(), 'submit' );
	t.answer( 400, { error : 'invalid navigation name', code : 'navs_invalid_name', params : [ 80 ], field : 'name' } );
	check( 'a refusal that names the name is said under the name, with the focus there, and in the line of the bar', t.field('navs-form-name').attributes['aria-invalid'] === 'true' && t.field('navs-form-name').focused === true
		&& t.errors().length === 1 && t.errors()[0].textContent === text('/_admin/error/navs_invalid_name').replace( '%s', '80' ) && t.field('navs-form-msg').textContent === t.errors()[0].textContent );
}


// --- the working copy ------------------------------------------------------

{
	const t = opened( null );
	const sent = t.requests.length;

	check( 'an opened menu shows its entries in their running order, the paths quietly under each', t.entries().join() === 'Start (/),About (/about)' );
	check( 'the status line says nothing while nothing is changed', t.field('navs-form-msg').textContent === '' && t.panel._isDirty() === false );

	// A key typed above the entries must survive a click below
	t.field('navs-form-key').value = 'primary';

	fire( t.button( 1, 'up' ), 'click' );
	check( 'up moves the entry on screen, and the focus stays on the button of the entry that moved', t.entries().join() === 'About (/about),Start (/)' && t.button( 0, 'up' ).disabled === true && t.button( 0, 'down' ).focused === true );
	fire( t.button( 0, 'down' ), 'click' );
	check( 'down moves it back', t.entries().join() === 'Start (/),About (/about)' && t.button( 1, 'down' ).disabled === true && t.button( 1, 'up' ).focused === true );
	check( 'the first entry cannot go up, the last cannot go down', t.button( 0, 'up' ).disabled === true && t.button( 1, 'down' ).disabled === true );
	check( 'every button of a row is an icon with a name of its own, the remove one in the colour of what takes something away', [ 'up', 'down', 'remove' ].every( function( which ) {
		const button = t.button( 0, which );
		return button.classList.contains('nino-admin-btn-icon') && button.attributes['aria-label'] === text( '/_admin/navs/label/'+ ( which === 'up' ? 'moveup' : ( which === 'down' ? 'movedown' : 'remove' ) ) ) && button.attributes['aria-label'] !== '';
	} ) && t.button( 0, 'remove' ).classList.contains('nino-admin-btn-danger') && t.button( 0, 'up' ).classList.contains('nino-admin-btn-danger') === false );

	fire( t.button( 0, 'remove' ), 'click' );
	check( 'remove takes the entry out on screen, and the focus goes to the entry that stands in its place', t.entries().join() === 'About (/about)' && t.button( 0, 'remove' ).focused === true );

	const addSelect = t.field('navs-form-add');
	check( 'the picker offers the routes that are not in the menu yet - the ones only a feature registers among them - and the one already in it is not', addSelect.children.map( function( option ) { return option.value } ).join() === ',/,/contact,/blog' );
	addSelect.value = '/contact';
	fire( t.addButton(), 'click' );
	check( 'Add puts the chosen route at the end of the copy', t.entries().join() === 'About (/about),Contact (/contact)' );

	check( 'not one of these touched the server', t.requests.length === sent );
	check( 'the key that was typed survived every one of them', t.field('navs-form-key').value === 'primary' );
	check( 'the panel knows its copy differs from what is saved', t.panel._isDirty() === true );

	// Save: the whole order, one request
	fire( t.theForm(), 'submit' );
	check( 'Save sends one request with the complete order, under the key that was typed, with the name', t.requests.length === sent + 1 && t.requests[sent].action === 'navs/save'
		&& JSON.stringify( t.requests[sent].payload ) === JSON.stringify( { originalKey : 'main', key : 'primary', name : '', entries : [ '/about', '/contact' ] } ) );
	t.touched.length = 0;
	t.answer( 200, { navs : [ { key : 'primary', name : '', entries : [ ROUTES[1], ROUTES[2] ] }, { key : 'footer', name : '', entries : [] } ], routes : ROUTES } );
	check( 'the answer is drawn: the copy is the saved menu now', t.entries().join() === 'About (/about),Contact (/contact)' && t.panel._isDirty() === false && t.panel._currentKey === 'primary' );
	check( '...and says it was saved as a toast, not as a line that stays', t.toasts.length === 1 && t.toasts[0] === text('/_admin/common/msg/saved') && t.field('navs-form-msg').textContent === '' );
	check( '...the person stays in the menu, which has the key it has now as its address and the button that was pressed', t.form.classList.contains('admin-hidden') === false && t.written.at(-1) === '["navs","primary"]' && t.list.classList.contains('admin-hidden') === true );
	check( '...and tells the other panels that navigations were written', t.touched.join() === 'navigations' );
}


// --- a save that fails ----------------------------------------------------

{
	const t = opened( null );
	fire( t.button( 1, 'up' ), 'click' );
	fire( t.theForm(), 'submit' );
	t.answer( 404, { error : 'unknown route: "/about"', code : 'navs_unknown_route', params : [ '/about' ] } );
	check( 'a refused save keeps the copy as it is and says why, in the line of the bar as an alert', t.entries().join() === 'About (/about),Start (/)' && t.panel._isDirty() === true
		&& t.field('navs-form-msg').textContent.includes('/about') && t.field('navs-form-msg').attributes.role === 'alert' );
	check( '...and wrote nothing the other panels need to know of, and no toast', t.touched.length === 0 && t.toasts.length === 0 );
}


// --- the key is required -----------------------------------------------------

{
	const t = build( null );
	t.panel.init();
	t.answer( 200, payload( [ '/' ] ) );
	fire( t.newButton(), 'click' );

	const key 	= t.field('navs-form-key');
	const sent 	= t.requests.length;
	const submit = function() { fire( t.theForm(), 'submit' ) };

	check( 'a new menu opens with an empty key: nothing is suggested that a Save would take for a decision', key.value === '' );

	submit();
	check( 'Save on the blank form sends nothing', t.requests.length === sent && t.touched.length === 0 );
	check( '...the field is marked and says at itself that it is missing', key.attributes['aria-invalid'] === 'true' && t.errors().length === 1 && t.errors()[0].textContent.length > 0 && key.attributes['aria-describedby'].split(' ').indexOf( t.errors()[0].id ) !== -1 );
	check( '...the focus is there, and the line of the bar is the alert that says so', key.focused === true && t.field('navs-form-msg').dataset.state === 'error' && t.field('navs-form-msg').textContent === text('/_admin/navs/error/fields') );

	key.value = '   ';
	submit();
	check( 'a key of blanks is no key', t.requests.length === sent && t.errors().length === 1 );

	key.value = 'Main Menu';
	submit();
	check( 'a key the server would refuse is not sent either, and the sentence is the invalid-key one, with the key in it', t.requests.length === sent && t.errors().length === 1
		&& t.errors()[0].textContent === text('/_admin/error/navs_invalid_id').replace( '%s', 'Main Menu' ) && key.attributes['aria-invalid'] === 'true' );

	key.value = 'a.b';
	submit();
	check( 'a dot is no part of a key, as on the server', t.requests.length === sent && t.errors().length === 1 );
	check( 'the form is not validated by the browser, whose bubble would say it in other words', t.theForm().noValidate === true );

	// One pattern for both sides: the client's is the server's, written out
	const server = fs.readFileSync( __dirname+ '/../_nino/Nino/Modules/Navigation/Admin/Admin.php', 'utf8' ).match( /private static function isValidKey[\s\S]*?preg_match\( '#(.+?)#'/ )[1];
	const client = fs.readFileSync( __dirname+ '/../_nino/Nino/Modules/Navigation/assets/admin.js', 'utf8' ).match( /\/(\^\[a-z\][^\/]*\$)\/\.test\( key \)/ )[1];
	check( 'the pattern the client checks a key against is the one the server does', server === client );

	key.value = 'side-nav_2';
	submit();
	check( 'a valid key goes out with the first Save, and the marks of the attempts before it are gone', t.requests.length === sent + 1 && t.requests[sent].action === 'navs/save'
		&& JSON.stringify( t.requests[sent].payload ) === JSON.stringify( { originalKey : '', key : 'side-nav_2', name : '' } ) && t.errors().length === 0 && key.attributes['aria-invalid'] === undefined );

	t.answer( 409, { error : 'navigation id already taken', code : 'navs_id_taken', params : [ 'side-nav_2' ], field : 'key' } );
	check( 'a key the server refuses is marked at the field as well, with the server\'s sentence', t.errors().length === 1 && t.errors()[0].textContent === text('/_admin/error/navs_id_taken').replace( '%s', 'side-nav_2' ) && key.attributes['aria-invalid'] === 'true' );

	// A new menu that was written: the form is the menu's own now, with its running order and its Delete, at its address
	submit();
	t.touched.length = 0;
	t.answer( 200, { navs : [ { key : 'side-nav_2', name : '', entries : [] }, { key : 'main', name : '', entries : [] } ], routes : ROUTES } );
	check( 'a new menu that was saved is open as a menu: at its address, with its running order to fill and Delete at the left of the bar', t.written.at(-1) === '["navs","side-nav_2"]' && t.field('navs-form-add') !== undefined
		&& t.barButtons().length === 2 && t.barButtons()[0].classList.contains('nino-admin-btn-danger') && t.toasts.length === 1 && t.touched.join() === 'navigations' );
	check( '...with the focus on the main button that was pressed', t.barButtons().at(-1).focused === true );
}


// --- a menu deleted -------------------------------------------------------

{
	const t = opened( null );
	const sent = t.requests.length;

	t.box.questionAnswer = false;
	fire( t.barButtons()[0], 'click' );
	const asked = t.questions.at(-1);
	check( 'a delete is a question of the destructive level, with a title the answer is worded like', t.questions.length === 1 && asked.level === 'destructive' && asked.title === asked.yes && asked.title === text('/_admin/navs/confirm/delete-title') && asked.title !== '' );
	check( '...it names what goes - the menu and its place in the routes - and what stays', asked.details.length === 2 && asked.details[0].title === text('/_admin/navs/confirm/deleted') && asked.details[0].lines.length === 2
		&& asked.details[0].lines[0].includes('main') && asked.details[0].lines[1].includes('2') && asked.details[1].title === text('/_admin/navs/confirm/kept') && asked.details[1].lines.length === 2 );
	check( 'a delete that is not confirmed sends nothing and tells nobody anything', t.requests.length === sent && t.touched.length === 0 );

	t.box.questionAnswer = true;
	fire( t.barButtons()[0], 'click' );
	check( 'a confirmed one asks the server to delete the menu that is open', t.requests.at(-1).action === 'navs/delete' && t.requests.at(-1).payload.key === 'main' );
	t.answer( 403, { error : 'no permission' } );
	check( 'a refused delete says so, in the line of the bar, and nothing was written', t.field('navs-form-msg').textContent.includes('no permission') && t.touched.length === 0 && t.form.classList.contains('admin-hidden') === false );

	fire( t.barButtons()[0], 'click' );
	t.answer( 200, { navs : [ { key : 'footer', name : '', entries : [] } ], routes : ROUTES } );
	check( 'one that went through tells the other panels that navigations were written, says so as a toast, and shows the list of the menus that are left, at its address', t.touched.join() === 'navigations' && t.toasts.length === 1 && t.toasts[0] === text('/_admin/common/msg/deleted')
		&& t.form.classList.contains('admin-hidden') === true && t.menus().length === 1 && t.written.at(-1) === '["navs"]' );
	check( '...the focus goes to a row that is left', t.link( t.menus()[0] ).focused === true );
}

{
	const t = opened( null, 'Hauptmenü' );
	t.box.questionAnswer = false;
	fire( t.barButtons()[0], 'click' );
	check( 'the question names the menu by its name, and its key in the list of what goes', t.questions.at(-1).message.includes('Hauptmenü') && t.questions.at(-1).details[0].lines[0].includes('main') );
}

{
	const t = opened( null );
	t.box.questionAnswer = true;
	fire( t.barButtons()[0], 'click' );
	t.answer( 200, { navs : [], routes : ROUTES } );
	check( 'a menu that was deleted with no menu left leaves the focus no row to stand on: it goes to the name of the panel', t.form.classList.contains('admin-hidden') === true && t.title.focused === true );
}


// --- the picker -------------------------------------------------------------

{
	const t = opened( null );
	const select = t.field('navs-form-add');
	check( 'the picker starts on an empty choice, not on the first route', select.children[0].value === '' && select.children[0].selected === true && select.value === ''
		&& select.children[0].textContent === text('/_admin/navs/label/choose') && text('/_admin/navs/label/choose') !== '' );
	const before = t.entries().length;
	check( '...and Add is not locked while nothing is chosen', t.addButton().disabled === false );
	fire( t.addButton(), 'click' );
	check( '...it says at the picker what is missing, with the focus, and adds nothing', t.entries().length === before && select.attributes['aria-invalid'] === 'true' && select.focused === true && t.errors().length === 1 && t.panel._isDirty() === false );
	select.value = '/blog';
	fire( t.addButton(), 'click' );
	check( 'a choice is added, and the sentence at the picker is gone', t.entries().length === before + 1 && t.errors().length === 0 && t.panel._isDirty() === true );
	check( 'a route that is not named yet is offered with the marker, a runtime one included, and shown in the list with a badge', ( function() {
		fire( t.button( before, 'remove' ), 'click' );
		return t.field('navs-form-add').children.some( function( option ) { return option.value === '/blog' && option.textContent.includes( text('/_admin/navs/label/unnamed-short') ) } );
	} )() );
	t.field('navs-form-add').value = '/blog';
	fire( t.addButton(), 'click' );
	check( '...the badge says it is not named yet', findAll( t.form, function( el ) { return el.classList.contains('nino-admin-badge') && el.textContent === text('/_admin/navs/label/unnamed') } ).length === 1 );
}


// --- the back link ---------------------------------------------------------

{
	const t = opened( null );
	fire( t.back(), 'click' );
	check( 'the back link leaves a menu and shows the list - the question about what is unsaved is the shell\'s, not the panel\'s', t.questions.length === 0 && t.form.classList.contains('admin-hidden') === true && t.panel._dirty === false && t.panel._entries.length === 0 );
}


// --- showing the panel again -----------------------------------------------

{
	const t = opened( null );
	const before = t.requests.length;

	t.panel.showCurrent();
	check( 'showing the panel again reads the menus and routes again', t.requests.length === before + 1 && t.requests[before].action === 'navs/list' );
	t.answer( 200, { navs : [ { key : 'main', name : '', entries : [ ROUTES[1] ] }, { key : 'footer', name : '', entries : [] } ], routes : ROUTES.concat( [ { httpUri : '/news', uri : '/news', label : 'News', named : true, runtime : true } ] ) } );
	check( '...and draws the open menu from the answer, when it holds nothing unsaved', t.entries().join() === 'About (/about)'
		&& t.field('navs-form-add').children.some( function( option ) { return option.value === '/news' } ) );

	// ...but never over a copy somebody changed
	fire( t.button( 0, 'remove' ), 'click' );
	t.field('navs-form-key').value = 'typed';
	const again = t.requests.length;
	t.panel.showCurrent();
	t.answer( 200, { navs : [ { key : 'main', name : '', entries : [ ROUTES[0], ROUTES[1], ROUTES[2] ] }, { key : 'footer', name : '', entries : [] } ], routes : ROUTES.concat( [ { httpUri : '/shop', uri : '/shop', label : 'Shop', named : true, runtime : true } ] ) } );
	check( 'a copy with changes is read over nothing: entries and typed key stay', t.requests.length === again + 1 && t.entries().join() === '' && t.field('navs-form-key').value === 'typed' && t.panel._isDirty() === true );
	check( '...while the routes it can still pick from are the new ones', t.field('navs-form-add').children.some( function( option ) { return option.value === '/shop' } ) && t.field('navs-form-add').children.some( function( option ) { return option.value === '/news' } ) === false );
	check( '...and the list of menus has the saved state', t.menuDetail( t.menus()[0] ) === text('/_admin/navs/list/entries').replace( '%d', '3' ) );

	// A read that fails changes nothing
	t.panel.showCurrent();
	t.answer( 500, null );
	check( 'a read that fails leaves the copy alone too', t.entries().join() === '' && t.panel._isDirty() === true );
}


// --- a page with a route per language is one entry -------------------------------

{
	// The Legal module's pages: one Element-URI, a path in each language. The
	// server sends the entry once, with the first of its paths and all of them
	const IMPRINT = { httpUri : '/impressum', uri : '/legal/imprint', label : 'Impressum', named : true, runtime : true, paths : [ '/impressum', '/imprint' ] };
	const PRIVACY = { httpUri : '/datenschutz', uri : '/legal/privacy', label : 'Datenschutz', named : true, runtime : true, paths : [ '/datenschutz', '/privacy' ] };
	const t = build( null );
	t.panel.init();
	t.answer( 200, { navs : [ { key : 'legal', name : '', entries : [ IMPRINT ] }, { key : 'main', name : '', entries : [ ROUTES[0] ] } ], routes : ROUTES.concat( [ IMPRINT, PRIVACY ] ) } );
	fire( t.link( t.menus()[0] ), 'click' );

	check( 'an entry that has a route per language is one row, with every path of it', t.entries().join() === 'Impressum (/impressum, /imprint)' );
	check( '...and the picker offers the other page once, with its paths', t.field('navs-form-add').children.filter( function( option ) { return option.value === '/datenschutz' } ).length === 1
		&& t.field('navs-form-add').children.some( function( option ) { return option.textContent === 'Datenschutz (/datenschutz, /privacy)' } )
		&& t.field('navs-form-add').children.every( function( option ) { return option.value !== '/imprint' && option.value !== '/privacy' } ) );
	t.field('navs-form-add').value = '/datenschutz';
	fire( t.addButton(), 'click' );
	check( 'Add puts the other page in as one entry too', t.entries().join() === 'Impressum (/impressum, /imprint),Datenschutz (/datenschutz, /privacy)' );
	fire( t.theForm(), 'submit' );
	check( 'Save sends each page once, by the first of its paths: the server finds the Element-URI by it', JSON.stringify( t.requests.at(-1).payload.entries ) === '["/impressum","/datenschutz"]' );
	t.answer( 200, { navs : [ { key : 'legal', name : '', entries : [ IMPRINT, PRIVACY ] }, { key : 'main', name : '', entries : [ ROUTES[0] ] } ], routes : ROUTES.concat( [ IMPRINT, PRIVACY ] ) } );
	check( '...and the saved menu is drawn the same way', t.entries().join() === 'Impressum (/impressum, /imprint),Datenschutz (/datenschutz, /privacy)' );
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

	fire( t.button( 1, 'up' ), 'click' );
	check( 'a change to the copy is unsaved input for the shell, and the shell is told', registered.navs.isDirty() === true && refreshes.length > 0 );
	check( '...and the panel does not say "unsaved" a second time next to the shell\'s own marker', t.field('navs-form-msg').textContent === '' );
	check( 'the marker goes in the action bar of the form', registered.navs.bar() === t.bar() );

	t.field('navs-form-name').value = 'Typed';
	fire( t.back(), 'click' );
	check( 'a name that was typed is unsaved input as well', registered.navs.isDirty() === false && t.form.classList.contains('admin-hidden') === true );

	fire( t.link( t.menus()[0] ), 'click' );
	check( '...and the form of a menu that was opened is clean', registered.navs.isDirty() === false );
	t.field('navs-form-name').value = 'Typed';
	check( '...a name typed into it is not', registered.navs.isDirty() === true );
	registered.navs.discard();
	check( 'Discard lets the changes go', registered.navs.isDirty() === false );

	// Save from the shell reports how it went
	fire( t.button( 1, 'up' ), 'click' );
	const outcomes = [];
	registered.navs.save( function( ok ) { outcomes.push( ok ) } );
	t.answer( 500, null );
	check( 'the shell\'s Save that fails reports false and keeps the copy', outcomes.join() === 'false' && registered.navs.isDirty() === true );
	registered.navs.save( function( ok ) { outcomes.push( ok ) } );
	t.answer( 200, payload( [ '/about', '/' ] ) );
	check( '...one that goes through reports true, and nothing is unsaved afterwards', outcomes.join() === 'false,true' && registered.navs.isDirty() === false );

	t.field('navs-form-key').value = '';
	registered.navs.save( function( ok ) { outcomes.push( ok ) } );
	check( 'one with no key ends in false as well, without a request', outcomes.join() === 'false,true,false' && t.requests.at(-1).action === 'navs/save' && t.errors().length === 1 );
}

done();
