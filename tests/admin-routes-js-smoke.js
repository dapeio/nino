/**
 *	Nino										A compact filesystembased php framework
 *	admin-routes-js-smoke.js	DOM-light checks for the Routes panel's script
 *													(_admin/Nino/Modules/Routes/assets/admin.js) over the real
 *													shell (adminPage() of the harness): the panel is the standard
 *													directory of the project's pages in the order they are
 *													stored in, a page is edited in the record over it, a new
 *													route is made in the same dialog, and the pages that
 *													features route are a list under it with a record of their
 *													own.
 *
 *													Every view has an address - the directory, a page, a new
 *													route, a page a feature routes - which survives a reload,
 *													and a save leaves the person in the record it was made in.
 *													The order is saved at once, which a toast says; the arrows
 *													of a row are locked while a search or a sort shows another
 *													order than the stored one; a move the server refuses has
 *													to be said out loud rather than swallowed, and what is on
 *													screen afterwards has to be what is on disk.
 *
 *													A page has one address for every language or one for
 *													each: a switch says which, the record draws the one field
 *													or a row per language, and what the fields hold is what is
 *													sent - and nothing is sent where nothing was changed. The
 *													name, the title and the addresses are one row per
 *													language; the description is the one of the language the
 *													workbench edits content in. What is wrong - here or in the
 *													server's answer - is said at the field.
 *
 *	Usage: node tests/admin-routes-js-smoke.js
 */

'use strict';

const { check, done, source, fills, load, element, findAll, fire, shellRouter, adminPage } = require( './harness.js' );

// What the panel writes into the address goes through the router's own codec: every state written, every link drawn, is read back
const codec = shellRouter();

const moduleEn	= fills('_admin/Nino/Modules/Routes/text/en_US.php');
const moduleDe	= fills('_admin/Nino/Modules/Routes/text/de_DE.php');
const workbench	= fills('_admin/text/en_US.php');
const workbenchDe = fills('_admin/text/de_DE.php');

const text = key => moduleEn[key] ?? workbench[key] ?? '';

const has = ( el, name ) => el.classList.contains( name );
const byClass = ( root, name ) => findAll( root, el => has( el, name ) );
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );
const errorsOf = root => byClass( root, 'nino-admin-field-error' );

( async function() {

// --- what the server answers --------------------------------------------------------------------------------------------

const NAMES = { '/' : 'Start', '/about' : 'Über uns', '/contact' : 'Kontakt' };

function page( uri, extra ) {
	return Object.assign( { httpUri : uri, httpUris : { '' : uri }, uri : uri, template : uri === '/' ? 'page-home' : 'page'+ uri.replace('/','-'), body : '[template /templates/'+ ( uri === '/' ? 'page-home' : 'page'+ uri.replace('/','-') )+ ']', navs : [], statusCode : 200,
		text : { de_DE : { name : NAMES[uri] || uri+ ' DE', title : ( NAMES[uri] || uri )+ ' - Titel', description : '' }, en_US : { name : ( NAMES[uri] || uri )+ ' EN', title : ( NAMES[uri] || uri )+ ' EN title', description : '' } } }, extra || {} );
}

function listing( order, extra ) {
	return Object.assign( {
		pages 					: order.map( uri => page( uri ) ),
		runtime 				: [],
		templates 			: [ 'page-about', 'page-blank', 'page-contact', 'page-home' ],
		defaultTemplate : '',
		locales 				: [ 'de_DE', 'en_US' ],
		localeNames 		: { de_DE : 'Deutsch', en_US : 'English' },
		selectedLocale 	: 'de_DE',
		navs 						: [],
		navNames 				: {},
	}, extra || {} );
}

// A page called by both languages, with everything the table shows: an address for each language, a template, navigations, a status, a description in one language
function fullListing() {

	const home = page( '/', { template : 'page-blank', navs : [ 'main' ] } );
	const about = page( '/about', { httpUri : '/ueber-uns', httpUris : { de_DE : '/ueber-uns', en_US : '/about' }, template : 'page-about', navs : [ 'main', 'footer' ] } );
	const missing = page( '/missing', { statusCode : 404, template : 'page-contact' } );
	const legal = page( '/legal', { template : '', body : '[template /templates/page-legal.[[/nino/http/response/locale]]]' } );
	const long = page( '/long-name', { httpUri : '/a-very-long-address/that/goes/on/and/on/and/on/and/on', httpUris : { '' : '/a-very-long-address/that/goes/on/and/on/and/on/and/on' } } );

	home.text.de_DE.description = 'Willkommen.';
	home.text.en_US.description = 'Welcome.';
	home.text.en_US.name = 'Homepage';
	about.text.de_DE.description = 'Über uns.';
	long.text.de_DE.name = 'A page with a name that is a good deal longer than any column of a table would like it to be';

	return listing( [], { pages : [ home, about, missing, legal, long ], templates : [ 'page-about', 'page-blank', 'page-contact', 'page-legal', 'page-blank' ], defaultTemplate : 'page-blank', navs : [ 'main', 'footer' ], navNames : { main : 'Hauptmenü' } } );
}

const FEATURES = [
	{ uri : '/blog/post', httpUri : '/blog/*', httpUris : [ '/blog/*' ], body : '[template /templates/page-post]', text : { de_DE : { name : 'Beitrag', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } },
	{ uri : '/newsletter', httpUri : '/.newsletter', httpUris : [ '/.newsletter' ], body : '[template /templates/page-newsletter]', text : { de_DE : { name : '', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } },
];

// --- the page ----------------------------------------------------------------

function world( hash ) {

	const h = adminPage();
	const mount = element('div');
	const requests = [];
	const touches = [];
	const toasts = [];

	mount.id = 'routes-list';
	h.page.appendChild( mount );
	h.show('routes');
	h.router.start();
	h.router.set( 'routes', [] );
	h.Nino.content.getText = text;
	h.Nino.http = { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} };
	h.ui.locales.names = { de_DE : 'Deutsch', en_US : 'English' };
	h.ui.locales.native = 'de_DE';
	h.admin.workLocale.current = 'de_DE';

	// What the panel says it wrote, and what it says as a toast
	const touch = h.admin.touch;
	h.admin.touch = function( topic, uri ) { touches.push( [ topic, uri ] ); return touch.call( this, topic, uri ) };
	const say = h.ui.say;
	h.ui.say = function( words, options ) { toasts.push( words ); return say.call( this, words, options ) };

	// Every address the panel writes or links to is read back by the router as the same screen and the same parts
	[ 'set', 'go', 'href' ].forEach( name => {
		const real = h.router[name];
		h.router[name] = function( screen, parts ) { codec.check( screen, parts ); return real.call( this, screen, parts ) };
	} );

	if( hash !== undefined )
		h.box.location.hash = hash;

	load( '_admin/Nino/Modules/Routes/assets/admin.js', h.context );

	const panel = h.admin.routes;
	const answer = ( status, body ) => { const request = requests[requests.length - 1]; request.callback( { status : status, responseJSON : body } ); return request };
	const open = answered => { panel.showCurrent(); answer( 200, answered ?? listing( [ '/', '/about', '/contact' ] ) ) };
	const rows = () => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined );
	const dialog = () => h.dialogs.filter( d => d.open ).pop() ?? null;

	const w = { h, panel, mount, requests, touches, answer, open, rows, dialog,
		ids : () => rows().map( el => el.dataset.row ),
		row : key => rows().find( el => el.dataset.row === key ),
		cells : key => w.row( key ).children.filter( el => el.tagName === 'TD' ),
		search : () => tag( mount, 'input' ).find( el => el.type === 'search' ),
		heads : () => findAll( mount, el => el.tagName === 'TH' && el.dataset.key !== undefined ),
		head : key => w.heads().find( el => el.dataset.key === key ),
		// The buttons at the end of a row, in the order they stand in
		buttons : key => byClass( w.row( key ), 'nino-admin-row-actions' )[0].children,
		move : ( key, direction ) => tag( w.row( key ), 'button' ).find( b => b.attributes['aria-label'] === text( direction === 'up' ? '/_admin/routes/label/moveup' : '/_admin/routes/label/movedown' ) ),
		edit : key => w.buttons( key )[0],
		bar : () => byClass( mount, 'nino-admin-actionbar' ).filter( el => has( el, 'nino-admin-list-actions' ) )[0],
		newButton : () => tag( w.bar(), 'button' )[0],
		features : () => byClass( mount, 'nino-admin-list-row' ),
		featureLink : key => byClass( w.features().find( li => li.dataset.row === key ), 'nino-admin-row-open' )[0],
		button : ( d, key ) => tag( d, 'button' ).find( b => b.textContent === text( key ) || b.attributes['aria-label'] === text( key ) ),
		stateOf : d => byClass( d, 'nino-admin-actionbar-state' )[0],
		lineOf : d => byClass( d, 'nino-admin-status' )[0],
		titleOf : d => byClass( d, 'nino-admin-dialog-title' )[0]?.textContent ?? null,
		subtitleOf : d => byClass( d, 'nino-admin-dialog-subtitle' )[0]?.textContent ?? '',
		field : ( d, name ) => findAll( d, el => el.dataset.field === name )[0],
		fieldById : ( d, id ) => findAll( d, el => el.id === id )[0],
		rowControl : ( d, name, locale ) => findAll( d, el => el.dataset.field === name && el.dataset.locale === locale )[0],
		addressControls : d => findAll( d, el => el.dataset.address !== undefined ),
		addressOf : ( d, locale ) => findAll( d, el => el.dataset.address === locale )[0],
		oneSwitch : d => findAll( d, el => el.dataset.key === 'one-address' )[0],
		navTicks : d => findAll( d, el => el.dataset.key === 'navs' ),
		form : d => tag( d, 'form' )[0],
		openRow : key => { fire( w.edit( key ), 'click', { currentTarget : w.edit( key ) } ); return w.dialog() },
		cross : d => tag( d, 'button' ).find( b => has( b, 'nino-admin-dialog-close' ) ),
		// The cross, and the step back through the history that it asks the browser for (the stand-in only notes it): the shell arrives and the panel is shown
		close : d => { const asked = h.went.length; fire( w.cross( d ), 'click' ); if( h.went.length > asked && w.dialog() === null ) w.back() },
		fillTexts : d => { Object.keys( w.panel._locales.reduce( ( map, l ) => Object.assign( map, { [l] : 1 } ), {} ) ).forEach( l => { w.type( w.rowControl( d, 'name', l ), 'N '+ l ); w.type( w.rowControl( d, 'title', l ), 'T '+ l ) } ) },
		// The question that stands on top of everything - about unsaved input, about a delete - and the answer to it by the word on the button
		asking : () => { const top = dialog(); return top !== null && has( top, 'nino-admin-record' ) === false ? top : null },
		answerAsk : key => { const top = dialog(); fire( tag( top, 'button' ).find( b => b.textContent === text( key ) ), 'click' ); return top },
		submit : d => fire( tag( d, 'form' )[0], 'submit' ),
		type : ( el, value ) => {
			el.value = value;
			// A field that stands in a form tells the form: what listens for the state of the bar and the shell's registry does
			let up = el;
			while( up !== null && up !== undefined && up.tagName !== 'FORM' )
				up = up.parentNode;
			fire( up ?? el, 'input', { target : el } );
			h.dirty.refresh();
		},
		toasts : () => toasts,
		focused : () => findAll( h.page, el => el.focused === true )[0] ?? null,
		tree : () => requests.map( r => r.action ).join(),
		// A step through the history to an address: the shell arrives, and the panel is shown
		// An address that is typed or followed: a new entry of the history, which the shell arrives at and the panel is shown
		typed : address => { h.box.history.pushState( { nino : h.index() + 1 }, '', address ); h.router.arrive(); panel.showCurrent(); h.hashchange() },
		// Cancel to the question about unsaved input of a typed address: the shell takes the step back with the history, which the stand-in only notes
		cancel : () => { const asked = h.went.length; fire( tag( w.asking(), 'button' ).find( b => b.textContent === text('/_admin/common/label/cancel') ), 'click' ); if( h.went.length > asked ) w.back() },
		step : address => { h.box.location.hash = address; h.router.arrive(); panel.showCurrent(); h.hashchange() },
		back : () => { h.back(); h.router.arrive(); panel.showCurrent(); h.hashchange() },
		forward : () => { h.forward(); h.router.arrive(); panel.showCurrent(); h.hashchange() },
	};

	return w;
}

console.log('Admin Routes');

{
	const w = world();

	w.panel.showCurrent();
	check( 'showing the panel asks the server for the route list, in one request, and for nothing else', w.requests.length === 1 && w.requests[0].action === 'routes/list' && JSON.stringify( w.requests[0].payload ) === '{}' );
	check( 'while it loads the sentence stands in place of the table, and says it is busy', byClass( w.mount, 'nino-admin-loading' )[0].textContent === text('/_admin/routes/loading') && text('/_admin/routes/loading') !== ''
		&& byClass( w.mount, 'nino-admin-directory-body' )[0].attributes['aria-busy'] === 'true' );

	w.answer( 200, listing( [ '/', '/about', '/contact' ] ) );
	check( 'every route is a row of the standard directory, in the order the server gave', w.ids().join() === '/,/about,/contact' && byClass( w.mount, 'nino-admin-directory' ).length === 1 );
}

// --- the directory: the cells, the badges, the search ---------------------------------------------------------------------

{
	const w = world();

	w.open( fullListing() );

	const heads = w.heads().map( el => el.dataset.key );
	check( 'the columns are the name, the addresses, the template, the navigations, the status and the description - and a box to choose rows by, a category or a select, none', heads.join() === 'name,paths,templateText,navText,statusText,states'
		&& tag( w.mount, 'input' ).filter( el => el.type === 'checkbox' ).length === 0 && tag( w.mount, 'select' ).filter( el => has( el, 'nino-admin-filterbar-select' ) && has( el, 'nino-admin-table-sortby' ) === false ).length === 0 );
	check( '...the rows are in the order the server stored them in, and no column is sorted', w.ids().join() === '/,/about,/missing,/legal,/long-name' && w.heads().every( el => el.attributes['aria-sort'] === undefined || el.attributes['aria-sort'] === 'none' ) );

	const home = w.cells('/');
	check( 'a row says the name of the page strong with its Element URI under it, as text', byClass( home[0], 'routes-name-label' )[0].tagName === 'STRONG' && byClass( home[0], 'routes-name-label' )[0].textContent === 'Start' && byClass( home[0], 'routes-name-key' )[0].textContent === '/' );
	check( '...the address of a page that has one for every language is that one, one with an address per language names every one of them', byClass( home[1], 'nino-admin-table-clamp' )[0].textContent === '/' && byClass( w.cells('/about')[1], 'nino-admin-table-clamp' )[0].textContent === '/ueber-uns, /about' );
	check( '...the template is named, and a page whose body picks it when it is asked for says so in the word of the panel', home[2].textContent === 'page-blank' && w.cells('/legal')[2].textContent === text('/_admin/routes/label/runtime') && text('/_admin/routes/label/runtime') !== '' );
	check( '...the navigations by their names - the key where a navigation has none - comma separated', home[3].textContent === 'Hauptmenü' && w.cells('/about')[3].textContent === 'Hauptmenü, footer' && w.cells('/missing')[3].textContent === '' );
	check( '...the status is a badge only where the code is not 200 - and says it with the number, in a state that warns - else nothing', byClass( w.cells('/missing')[4], 'nino-admin-badge' )[0].textContent === '404' && byClass( w.cells('/missing')[4], 'nino-admin-badge' )[0].dataset.state === 'warn'
		&& byClass( home[4], 'nino-admin-badge' ).length === 0 && home[4].textContent === '' );
	check( '...and the status column is headed "Status"', w.head('statusText').textContent === text('/_admin/routes/col/status') && text('/_admin/routes/col/status') === 'Status' );

	// The description: the state one would otherwise see only after opening the page, language by language, as a sign and not the colour alone
	const states = cells => byClass( cells[5], 'nino-admin-badge' ).map( badge => badge.textContent+ ':'+ badge.dataset.state );
	check( 'the description is one badge for each language - a tick where the page has a description in it, a dash where not', states( home ).join() === 'DE ✓:ok,EN ✓:ok' && states( w.cells('/about') ).join() === 'DE ✓:ok,EN –:warn' && states( w.cells('/missing') ).join() === 'DE –:warn,EN –:warn' );
	check( '...each named by its language in the tooltip and by the word that says what it states', byClass( w.cells('/about')[5], 'nino-admin-badge' ).map( b => b.title ).join() === 'Deutsch: '+ text('/_admin/routes/col/description-ok')+ ',English: '+ text('/_admin/routes/col/description-missing') );

	// The address is the column that gives way: its text is clamped to a width of its own, so that the name keeps the room (the name cell has its own minimum in the stylesheet)
	check( 'the addresses of a row are clamped to 12rem, whole in their tooltip - the column that gives way - and the name is not', byClass( home[1], 'nino-admin-table-clamp' )[0].style['--nino-admin-clamp'] === '12rem' && byClass( home[1], 'nino-admin-table-clamp' )[0].title === '/'
		&& byClass( home[0], 'routes-name' )[0].style['--nino-admin-clamp'] === undefined && source('_admin/Nino/Modules/Routes/assets/admin.css').includes('min-width: 11rem') );

	// A name that is longer than its column is cut by an ellipsis, and whole in its tooltip
	check( 'a long name is one line with its whole text as the tooltip', byClass( w.cells('/long-name')[0], 'routes-name-label' )[0].title === byClass( w.cells('/long-name')[0], 'routes-name-label' )[0].textContent );

	// The search: a name in another language, an address, a template, the Element URI
	const found = query => { w.type( w.search(), query ); return w.ids().join() };
	check( 'the search finds a page by its name in the language the workbench is on', found( 'über' ) === '/about' );
	check( '...by a name in another language', found( 'homepage' ) === '/' );
	check( '...by an address - one of the addresses of a page that has one for each language', found( '/about' ) === '/about' && found( 'ueber-uns' ) === '/about' && found( 'goes/on' ) === '/long-name' );
	check( '...by a template', found( 'page-contact' ) === '/missing' );
	check( '...by the Element URI', found( 'legal' ) === '/legal' );
	check( '...and says that it finds nothing in its own words', found( 'zzz' ) === '' && byClass( w.mount, 'nino-admin-empty' ).filter( el => el.textContent === text('/_admin/routes/nomatch') ).length === 1 && text('/_admin/routes/nomatch') !== '' );
	w.type( w.search(), '' );

	check( 'the bar of the list has one button, the main one: New route', tag( w.bar(), 'button' ).length === 1 && has( w.newButton(), 'nino-admin-btn-primary' ) && w.newButton().textContent === text('/_admin/routes/label/new') );
	check( 'the search says what it searches in its own words', w.search().attributes['aria-label'] === text('/_admin/routes/label/search') && text('/_admin/routes/label/search') === 'Search routes' );
}

{
	// A name in another language: a page named in one language only is called by that one
	const w = world();
	const only = listing( [ '/', '/about' ] );
	only.pages[0].text.de_DE.name = '';
	only.pages[0].text.en_US.name = 'Home';
	w.open( only );
	check( 'a page with a name only in another language is called by that one, and the search finds it by it', byClass( w.cells('/')[0], 'routes-name-label' )[0].textContent === 'Home' && ( w.type( w.search(), 'home' ), w.ids().join() === '/' ) );

	const none = listing( [ '/x' ] );
	none.pages[0].text.de_DE.name = '';
	none.pages[0].text.en_US.name = '';
	const v = world();
	v.open( none );
	check( 'a page nobody named in any language is called by its address', byClass( v.cells('/x')[0], 'routes-name-label' )[0].textContent === '/x' );
}

{
	// Without the Navigation module there are no navigations to show
	const w = world();
	w.open( listing( [ '/', '/about' ] ) );
	check( 'the column of the navigations is left out where the Navigation module is off', w.heads().map( el => el.dataset.key ).join() === 'name,paths,templateText,statusText,states' );
	const quick = world();
	quick.open( fullListing() );
	check( '...and is there where it is on', quick.heads().some( el => el.dataset.key === 'navText' ) );
}

{
	// Foreign text is text: in the table, in the head of the record, in the question of a delete and in its details
	const HOSTILE = '<img src=x onerror=alert(1)> "quoted" [[/key]]';
	const w = world();
	const hostile = listing( [ '/x', '/y' ] );
	hostile.pages[0] = page( '/x', { httpUri : HOSTILE, httpUris : { '' : HOSTILE }, template : HOSTILE, body : '[template /templates/'+ HOSTILE+ ']', navs : [ HOSTILE ] } );
	hostile.pages[0].text.de_DE.name = HOSTILE;
	hostile.pages[0].text.de_DE.description = HOSTILE;
	hostile.navs = [ HOSTILE ];
	hostile.navNames = { [HOSTILE] : HOSTILE };
	hostile.templates = [ HOSTILE ];
	w.open( hostile );

	const cells = w.cells('/x');
	check( 'a name, an address, a template and a navigation that hold markup are text in the table - no element is made of them', byClass( cells[0], 'routes-name-label' )[0].textContent === HOSTILE && byClass( cells[1], 'nino-admin-table-clamp' )[0].textContent === HOSTILE
		&& cells[2].textContent === HOSTILE && cells[3].textContent === HOSTILE && findAll( w.mount, el => el.tagName === 'IMG' ).length === 0 );

	fire( w.edit('/x'), 'click', { currentTarget : w.edit('/x') } );
	const d = w.dialog();
	check( 'the head of the record has the name and the Element URI as text', w.titleOf( d ) === HOSTILE && findAll( d, el => el.tagName === 'IMG' ).length === 0 );
	check( '...and the fields hold them as values', w.field( d, 'uri' ).value === '/x' && w.rowControl( d, 'name', 'de_DE' ).value === HOSTILE && w.field( d, 'description' ).value === HOSTILE );

	const questions = [];
	const confirm = w.h.ui.confirm;
	w.h.ui.confirm = function( level, options ) { questions.push( options ); return confirm.call( this, level, options ) };
	fire( w.button( d, '/_admin/routes/label/delete' ), 'click' );
	const asked = questions[0];
	check( 'the question of a delete names the address as text, and its details are lines of text: the address, the texts, the template', asked.message.includes( HOSTILE ) && asked.details[0].lines.join() === HOSTILE && asked.details[1].lines.every( line => typeof line === 'string' ) && asked.details[1].lines[1].includes( HOSTILE ) === true );
	check( '...and what the dialog draws of it is text too', findAll( w.dialog(), el => el.tagName === 'IMG' ).length === 0 );
}

// --- the actions of a row, and the link to the page -----------------------------------------------------------------------

{
	const w = world();

	w.open( listing( [ '/', '/about', '//evil.example', 'relative' ] ) );

	check( 'a row has three buttons in view - edit first, which the keyboard opens the row by, then up and down - each named, and no menu: there is no fourth', Array.from( w.buttons('/about') ).map( el => el.tagName ).join() === 'BUTTON,BUTTON,BUTTON'
		&& w.edit('/about').attributes['aria-label'] === 'Edit route “Über uns”' && text('/_admin/routes/row/open') === 'Edit route “%s”' && w.move( '/about', 'up' ) !== undefined && w.move( '/about', 'down' ) !== undefined
		&& byClass( w.row('/about'), 'nino-admin-menu' ).length === 0 );
	check( '...the first row cannot go up, the last cannot go down', w.move( '/', 'up' ).disabled === true && w.move( 'relative', 'down' ).disabled === true && w.move( '/about', 'up' ).disabled === false && w.move( '/', 'down' ).disabled === false );

	// "Open page" is a link in the address, since the table shows three buttons before it makes a menu of the rest
	const link = tag( w.cells('/about')[1], 'a' )[0];
	check( 'the page opens by a real link behind its address, in a tab of its own, under the project directory, named for a screen reader with the name of the page', link !== undefined && link.href === '/about' && link.target === '_blank' && link.rel === 'noopener'
		&& link.attributes['aria-label'] === 'Open page “Über uns” in a new tab' && link.textContent === '' );
	check( '...but none for a path that would leave the site or is no path', tag( w.cells('//evil.example')[1], 'a' ).length === 0 && tag( w.cells('relative')[1], 'a' ).length === 0 );
	check( '...and a click on the link opens no record: it is the link\'s', ( () => { fire( link, 'click', { target : link } ); return w.dialog() === null } )() );

	// The row opens by a click on it and by its first button
	fire( w.row('/about'), 'click', { target : w.row('/about') } );
	check( 'a click on the row opens its record', w.dialog() !== null && w.titleOf( w.dialog() ) === 'Über uns' );
}

// --- the order -----------------------------------------------------------------------------------------------------------

{
	const w = world();

	w.open( listing( [ '/', '/about', '/contact' ] ) );

	check( 'in the stored order, with no search and no sort, every arrow that has somewhere to go is open, and says what it does', w.move( '/about', 'up' ).disabled === false && w.move( '/about', 'up' ).title === text('/_admin/routes/label/moveup') );

	/*	A move the server accepts: the answer is the new order, and the table is drawn from it rather than from a local guess	*/
	fire( w.move( '/contact', 'up' ), 'click' );
	check( 'the move is sent for the row whose button was pressed, by its Element URI', w.requests.length === 2 && w.requests[1].action === 'routes/move' && JSON.stringify( w.requests[1].payload ) === '{"uri":"/contact","direction":"up"}' );

	w.answer( 200, { pages : listing( [ '/', '/contact', '/about' ] ).pages } );
	check( 'an accepted move draws the rows in the order the server answers with', w.ids().join() === '/,/contact,/about' );
	check( '...marks the row that moved as the one that was changed', has( w.row('/contact'), 'is-fresh' ) && has( w.row('/about'), 'is-fresh' ) === false );
	check( '...keeps the focus on the button that was pressed, which stands in the row the page moved to', w.focused() === w.move( '/contact', 'up' ) );
	check( '...says so as a toast, because the order is saved at once', w.toasts().length === 1 && w.toasts()[0] === text('/_admin/routes/msg/moved') && text('/_admin/routes/msg/moved') !== '' );
	check( '...and tells the other panels that routes were written, of no page in particular', JSON.stringify( w.touches ) === '[["routes",null]]'.replace( 'null', 'undefined' ).replace( 'undefined', 'null' ) || ( w.touches.length === 1 && w.touches[0][0] === 'routes' && w.touches[0][1] === undefined ) );

	// At the edge the button that was pressed is locked: the focus goes to its other half
	fire( w.move( '/contact', 'up' ), 'click' );
	w.answer( 200, { pages : listing( [ '/contact', '/', '/about' ] ).pages } );
	check( 'a move to the edge leaves the focus on the other arrow of the row, the pressed one is locked now', w.move( '/contact', 'up' ).disabled === true && w.focused() === w.move( '/contact', 'down' ) );

	/*	A move the server refuses: the row did not move, and the order on screen is a guess from that moment on - somebody else may have deleted the very route this table
		still draws - so it is read back, and the reason is said	*/
	w.touches.length = 0;
	w.toasts().length = 0;
	fire( w.move( '/about', 'up' ), 'click' );
	w.answer( 409, { error : 'route was removed in the meantime' } );
	check( 'a refused move reads the list back from the server', w.requests.length === 5 && w.requests[4].action === 'routes/list' );
	check( '...and says nothing of routes written, nor as a toast: nothing was', w.touches.length === 0 && w.toasts().length === 0 );
	w.answer( 200, listing( [ '/', '/contact' ] ) );
	check( '...so what is on screen afterwards is what is on disk', w.ids().join() === '/,/contact' );
	const notice = () => byClass( w.mount, 'nino-admin-notice' )[0];
	check( '...and the reason the server gave is said over the table, as an alert', notice() !== undefined && notice().textContent.includes('(409) route was removed in the meantime') );

	// An answer with no reason of its own still says what happened
	fire( w.move( '/contact', 'up' ), 'click' );
	w.answer( 500, null );
	w.answer( 200, listing( [ '/', '/contact' ] ) );
	check( 'an answer with no reason falls back to the shared sentence, and the table is still a table of routes', notice().textContent.includes( '(500) '+ text('/_admin/common/error/save') ) && text('/_admin/common/error/save') !== '' && w.ids().join() === '/,/contact' );

	// A reload that fails too must not leave the panel silent about the move
	fire( w.move( '/contact', 'up' ), 'click' );
	w.answer( 403, { error : 'no permission' } );
	w.answer( 403, null );
	check( 'a move error survives a reload that fails as well', notice().textContent.includes('(403) no permission') && w.ids().join() === '/,/contact' );

	// A move that goes through takes the old reason away
	fire( w.move( '/contact', 'up' ), 'click' );
	w.answer( 200, { pages : listing( [ '/contact', '/' ] ).pages } );
	check( 'the next move that is accepted takes the reason of the one that was refused away', notice() === undefined );
}

{
	// Locked while a search shows not every page
	const w = world();

	w.open( listing( [ '/', '/about', '/contact' ] ) );

	const arrows = () => [ w.move( '/', 'down' ), w.move( '/about', 'up' ), w.move( '/about', 'down' ), w.move( '/contact', 'up' ) ];

	check( 'before anything narrows the table the arrows are open', arrows().every( b => b.disabled === false ) );

	w.type( w.search(), 'tak' );
	check( 'while a search text stands the arrows are locked - the rows shown are not every page - in both directions', w.ids().join() === '/contact' && w.move( '/contact', 'up' ).disabled === true && w.move( '/contact', 'down' ).disabled === true );
	check( '...and say why in their title: the search, in the words of the panel', w.move( '/contact', 'up' ).title === text('/_admin/routes/lock/search') && w.move( '/contact', 'down' ).title === text('/_admin/routes/lock/search') && text('/_admin/routes/lock/search') !== '' );


	w.type( w.search(), '   ' );
	check( 'a search of blanks shows every page and does not lock', w.ids().length === 3 && arrows().every( b => b.disabled === false ) );

	w.type( w.search(), '' );
	check( 'once the search is emptied the arrows are open again', arrows().every( b => b.disabled === false ) && w.move( '/about', 'up' ).title === text('/_admin/routes/label/moveup') );

	// No column sorts: the stored order is the content of the list, so nothing but a search can hide a neighbour
	check( 'no head of the table is a button and none says an order: the heads are words, whatever the width', w.heads().length > 0 && w.heads().every( el => tag( el, 'button' ).length === 0 && el.attributes['aria-sort'] === undefined ) );
	check( '...the table has no select to choose an order by and no button of filters that would hold one - the order is the stored one at every width', tag( w.mount, 'select' ).filter( el => has( el, 'nino-admin-table-sortby' ) ).length === 0
		&& tag( w.mount, 'button' ).filter( b => b.textContent === text('/_admin/common/table/filters') ).length === 0 );
	check( '...and the sort of the table is never set', w.panel._directory.state().sort.key === '' && w.ids().join() === '/,/about,/contact' );
	check( '...there is no word for a lock by a sort', moduleEn['/_admin/routes/lock/sort'] === undefined && moduleDe['/_admin/routes/lock/sort'] === undefined );
}

{
	// A row that is drawn while a search stands is drawn locked: the page the arrows were made for is another
	const w = world();

	w.open( listing( [ '/', '/about', '/contact' ] ) );
	w.type( w.search(), 'about' );
	check( 'a row drawn anew while a search stands has its arrows locked from the first', w.move( '/about', 'up' ).disabled === true );
	fire( w.edit('/about'), 'click', { currentTarget : w.edit('/about') } );
	check( 'a record opened over it steps through the rows the search leaves - there is one - and says the place', w.dialog() !== null );
}

// --- the record of a page -----------------------------------------------------------------------------------------------

{
	const w = world();

	w.open( listing( [ '/', '/about', '/contact' ], { defaultTemplate : 'page-blank' } ) );
	const position = w.h.index();

	const d = w.openRow('/about');
	check( 'the pencil opens the record of the page, in the large dialog, with the name of the page as its title and the Element URI under it', d !== null && has( d, 'nino-admin-record' ) && w.titleOf( d ) === 'Über uns' && w.subtitleOf( d ) === '/about' );
	check( '...at an address of its own that is a step of the history, written through the router: #routes/routes/<Element URI>', w.h.hash() === '#routes/routes/%2Fabout' && w.h.index() === position + 1 && w.h.router.marked() === 'routes' );
	check( '...and says the place among the rows of the table: 2 of 3', byClass( d, 'nino-admin-record-count' )[0].textContent === '2 of 3' && text('/_admin/common/record/position') === '%s of %s' );
	check( '...the focus is in the first field: the Element URI', w.focused() === w.field( d, 'uri' ) );
	check( '...the step buttons are named in the words of the panel', tag( d, 'button' ).some( b => b.attributes['aria-label'] === text('/_admin/routes/record/prev') ) && tag( d, 'button' ).some( b => b.attributes['aria-label'] === text('/_admin/routes/record/next') ) && text('/_admin/routes/record/prev') === 'Previous route' );

	check( 'the form is novalidate, has exactly one main button - Save, at the right - and Delete route at the left, and Save and continue, and no Cancel', w.form( d ).noValidate === true
		&& tag( d, 'button' ).filter( b => has( b, 'nino-admin-btn-primary' ) ).length === 1 && w.button( d, '/_admin/common/label/save' ) !== undefined && has( w.button( d, '/_admin/common/label/save' ), 'nino-admin-btn-primary' )
		&& has( w.button( d, '/_admin/routes/label/delete' ), 'nino-admin-btn-danger' ) && w.button( d, '/_admin/common/label/save-next' ) !== undefined && w.button( d, '/_admin/common/label/cancel' ) === undefined );
	check( 'the Element URI is a required field that holds the page\'s own', w.field( d, 'uri' ).value === '/about' && w.field( d, 'uri' ).attributes['aria-required'] === 'true' );
	check( '...the name and the title of every language are a row of their own', [ 'de_DE', 'en_US' ].every( l => w.rowControl( d, 'name', l ) !== undefined && w.rowControl( d, 'title', l ) !== undefined ) && w.rowControl( d, 'name', 'de_DE' ).value === 'Über uns' && w.rowControl( d, 'name', 'en_US' ).value === 'Über uns EN' );
	check( '...none of the fields has a help sentence in the page that stays open: they are behind a (?) at the name', byClass( d, 'nino-admin-hint' ).length === 0 );
	check( '...two sections stand as groups of a form, a fieldset with its legend: the route and the content', tag( d, 'fieldset' ).filter( f => f.children[0].tagName === 'LEGEND' ).map( f => f.children[0].textContent ).join() === text('/_admin/routes/label/route')+ ','+ text('/_admin/routes/label/content') );
	check( '...the state of the bar says nothing of an input nobody made', w.stateOf( d ).dataset.state !== 'warn' );

	// What the head of the record steps through
	fire( tag( d, 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/routes/record/next') ), 'click' );
	check( 'the next step shows the next page in the rows of the table, and writes its address over the one of the record (no step of the history)', w.titleOf( d ) === 'Kontakt' && w.h.hash() === '#routes/routes/%2Fcontact' && w.h.index() === position + 1 );

	// The cross closes it: the directory is as it was, the focus is on the row
	fire( w.cross( d ), 'click' );
	check( 'the cross closes the record, takes the entry it made off the history and puts the focus on the row', w.dialog() === null && w.h.went.at(-1) === -1 && w.focused() === w.row('/contact') );
}

{
	// A page opened by an address: the reload shows directory and record, and the record is closed by writing the directory's address
	const w = world( '#routes/routes/%2Fabout' );
	w.panel.showCurrent();
	w.answer( 200, listing( [ '/', '/about', '/contact' ] ) );
	check( 'a fresh load on the address of a route opens the directory with its record over it', w.dialog() !== null && w.titleOf( w.dialog() ) === 'Über uns' && w.ids().length === 3 );
	check( '...it is no step of the history: the address stays where it was', w.h.hash() === '#routes/routes/%2Fabout' );
	fire( w.cross( w.dialog() ), 'click' );
	check( '...and closing it writes the address of the directory over it', w.dialog() === null && w.h.hash() === '#routes' );
}

{
	// An address of another panel is not this panel's to answer: it asks for nothing and moves nothing, and the record that stands open is closed by the address that leads away
	const w = world();
	w.open();
	const d = w.openRow('/about');
	const asked = w.requests.length;
	w.h.box.location.hash = '#dashboard';
	w.h.router.arrive();
	w.panel.showCurrent();
	check( 'showing the panel with the address of another panel reads nothing and opens and closes nothing itself', w.requests.length === asked && d.open === true );
	w.h.hashchange();
	check( '...and the record, which stands over no other panel, is closed by that address', d.open === false && w.dialog() === null );
}

{
	// Back closes a record that a person's own move opened
	const w = world();
	w.open();
	w.openRow('/about');
	check( 'the record stands at the address of its page', w.h.hash() === '#routes/routes/%2Fabout' );
	w.back();
	check( 'Back closes the record: the address is the directory\'s and the directory is as it was', w.dialog() === null && w.h.hash() === '#routes' && w.ids().join() === '/,/about,/contact' );
	w.forward();
	check( 'Forward opens it again', w.dialog() !== null && w.titleOf( w.dialog() ) === 'Über uns' );
}

{
	// An address of a page that is not there is said over the directory
	const w = world( '#routes/routes/%2Fnothing-there' );
	w.panel.showCurrent();
	w.answer( 200, listing( [ '/', '/about' ] ) );
	check( 'an address that names no route says so over the directory, in the words of the panel, and the address is the directory\'s', w.dialog() === null && byClass( w.mount, 'nino-admin-notice' ).some( n => n.textContent.includes( text('/_admin/routes/record/missing') ) ) && w.h.hash() === '#routes' );

	const v = world( codec.href( 'routes', [ codec.word('feature'), '/gone' ] ) );
	v.panel.showCurrent();
	v.answer( 200, listing( [ '/', '/about' ], { runtime : FEATURES } ) );
	check( 'a page of a feature that is not there is said the same way, and the address is the directory\'s', v.dialog() === null && byClass( v.mount, 'nino-admin-notice' ).some( n => n.textContent.includes( text('/_admin/routes/record/missing') ) ) && v.h.hash() === '#routes' );
}


// --- Save: what was changed is sent, what was not is not --------------------------------------------------------------------

const SAVE = '/_admin/common/label/save';
const SAVE_NEXT = '/_admin/common/label/save-next';
const CREATE = '/_admin/routes/label/create';
const DELETE = '/_admin/routes/label/delete';

{
	const w = world();

	w.open( listing( [ '/', '/about', '/contact' ], { defaultTemplate : 'page-blank' } ) );

	const d = w.openRow('/about');
	const asked = w.requests.length;

	fire( w.button( d, SAVE ), 'click' );
	check( 'a record that nothing was changed in sends NO request when it is saved, and says it is saved', w.requests.length === asked && w.stateOf( d ).dataset.state === 'ok' && w.toasts().at(-1) === text('/_admin/common/msg/saved') );
	check( '...and tells nobody that a route was written', w.touches.length === 0 );

	w.type( w.rowControl( d, 'name', 'en_US' ), 'About us' );
	check( 'an input makes the record changed, and nothing is sent by typing', w.stateOf( d ).dataset.state === 'warn' && w.h.dirty.isDirty( [ 'routes' ] ) === true && w.requests.length === asked );

	fire( w.button( d, SAVE ), 'click' );
	check( 'a changed name is sent in ONE request, routes/save, with the Element URI the page had and the texts of every language', w.requests.length === asked + 1 && w.requests.at(-1).action === 'routes/save'
		&& w.requests.at(-1).payload.originalUri === '/about' && w.requests.at(-1).payload.uri === '/about' && w.requests.at(-1).payload.text.en_US.name === 'About us' && w.requests.at(-1).payload.text.de_DE.name === 'Über uns' );
	check( '...the shape of the payload is the same as ever: no more keys, the status code as the field holds it', Object.keys( w.requests.at(-1).payload ).sort().join() === 'httpUris,navs,originalUri,statusCode,template,text,uri' && w.requests.at(-1).payload.statusCode === '200'
		&& JSON.stringify( w.requests.at(-1).payload.httpUris ) === '{"":"/about"}' && w.requests.at(-1).payload.template === 'page-about' && Array.isArray( w.requests.at(-1).payload.navs ) );
	check( '...and the line of the bar says it is saving', w.lineOf( d ).dataset.state === 'saving' );

	const saved = listing( [ '/', '/about', '/contact' ] ).pages;
	saved[1].text.en_US = { name : 'About us', title : 'Über uns EN title', description : '' };
	w.answer( 200, { pages : saved } );
	check( 'a save that went through says the page was written - with its address - as the other panels hear it, and says it as a toast and in the badge', w.touches.length === 1 && w.touches[0][0] === 'routes' && w.touches[0][1] === '/about'
		&& w.toasts().at(-1) === text('/_admin/common/msg/saved') && w.stateOf( d ).dataset.state === 'ok' );
	check( '...the record stays where it is, at its address, with a line that says nothing, and the row is the one that is drawn anew', w.dialog() === d && w.h.hash() === '#routes/routes/%2Fabout' && w.lineOf( d ).textContent === '' && has( w.row('/about'), 'is-fresh' ) );
	check( '...and what the record holds is what is saved: the shell would not ask', w.h.dirty.isDirty( [ 'routes' ] ) === false );

	// The next save of the same record, unchanged again, sends nothing either
	const sent = w.requests.length;
	fire( w.button( d, SAVE ), 'click' );
	check( 'saved again with nothing changed since, the record sends nothing', w.requests.length === sent );

	// What counts as a change: every field that goes into the request
	[ [ 'the status code', ( v, e ) => v.type( v.field( e, 'statusCode' ), '404' ) ], [ 'the template', ( v, e ) => { v.field( e, 'template' ).value = 'page-contact'; v.type( v.field( e, 'template' ), 'page-contact' ) } ],
		[ 'the description', ( v, e ) => v.type( v.field( e, 'description' ), 'Neu' ) ], [ 'the title of another language', ( v, e ) => v.type( v.rowControl( e, 'title', 'en_US' ), 'About' ) ],
		[ 'the Element URI', ( v, e ) => v.type( v.field( e, 'uri' ), '/about-us' ) ], [ 'the address', ( v, e ) => v.type( v.field( e, 'httpUri' ), '/about-us' ) ] ].forEach( change => {
		const v = world();
		v.open( listing( [ '/', '/about' ] ) );
		const e = v.openRow('/about');
		const was = v.requests.length;
		change[1]( v, e );
		fire( v.button( e, SAVE ), 'click' );
		check( 'a changed '+ change[0]+ ' is sent', v.requests.length === was + 1 && v.requests.at(-1).action === 'routes/save' );
	} );
}

{
	// Save and continue: the next row in the stored order
	const w = world();
	w.open( listing( [ '/', '/about', '/contact' ] ) );
	const d = w.openRow('/');
	w.type( w.rowControl( d, 'name', 'en_US' ), 'Home' );
	fire( w.button( d, SAVE_NEXT ), 'click' );
	check( '"Save and continue" saves a changed page - one request - and goes on to the next row only once it is saved', w.requests.length === 2 && w.requests[1].action === 'routes/save' && w.titleOf( d ) === 'Start' );
	w.answer( 200, { pages : listing( [ '/', '/about', '/contact' ] ).pages } );
	check( '...the next page in the stored order is the record then, at its address', w.titleOf( d ) === 'Über uns' && w.h.hash() === '#routes/routes/%2Fabout' );
	fire( w.button( d, SAVE_NEXT ), 'click' );
	check( '...on a page nothing was changed in it sends nothing and goes on', w.requests.length === 2 && w.titleOf( d ) === 'Kontakt' );
	check( '...and the last page has no next one to go to', w.requests.length === 2 && ( fire( w.button( d, SAVE_NEXT ), 'click' ), w.titleOf( d ) === 'Kontakt' ) );
}

{
	// Required fields: nothing is sent, the fields say so where they stand, the first takes the focus
	const w = world();
	w.open( listing( [ '/', '/about' ], { defaultTemplate : 'page-blank' } ) );
	fire( w.newButton(), 'click' );
	const d = w.dialog();
	const asked = w.requests.length;
	check( 'the new route opens the form of a route, at #routes/routes/@new, as a step of the history', w.h.hash() === '#routes/routes/@new' && w.titleOf( d ) === text('/_admin/routes/label/new') && w.subtitleOf( d ) === '' );
	check( '...no Element URI and no address filled in, one field for the address, starting on the blank page the project has', w.field( d, 'uri' ).value === '' && w.addressControls( d ).length === 1 && w.addressControls( d )[0].value === '' && w.field( d, 'template' ).value === 'page-blank' );
	check( '...with a name and a title per language and nothing in them, and none of the buttons of a route that exists: no Delete, no Save and continue', [ 'de_DE', 'en_US' ].every( l => w.rowControl( d, 'name', l ).value === '' && w.rowControl( d, 'title', l ).value === '' )
		&& w.button( d, DELETE ).hidden === true && w.button( d, SAVE_NEXT ).hidden === true );
	check( '...the main button is worded as creating, not as saving', has( w.button( d, CREATE ), 'nino-admin-btn-primary' ) && text( CREATE ) !== text( SAVE ) && w.button( d, SAVE ) === undefined );
	check( '...and the state of the bar has nothing to say about an input nobody made', w.stateOf( d ).dataset.state !== 'warn' );

	fire( w.button( d, CREATE ), 'click' );
	check( 'a save with the required fields empty sends no request', w.requests.length === asked );
	check( '...says what is missing at every field: the Element URI, the address, and the name and the title of every language', [ w.field( d, 'uri' ), w.addressControls( d )[0] ].every( el => el.attributes['aria-invalid'] === 'true' )
		&& [ 'de_DE', 'en_US' ].every( l => [ 'name', 'title' ].every( n => w.rowControl( d, n, l ).attributes['aria-invalid'] === 'true' ) ) && w.field( d, 'description' ).attributes['aria-invalid'] === undefined && w.field( d, 'template' ).attributes['aria-invalid'] === undefined );
	check( '...puts the focus on the first of them', w.focused() === w.field( d, 'uri' ) );
	check( '...and a line in the bar, an alert of the status line', w.lineOf( d ).dataset.state === 'error' && w.lineOf( d ).textContent === text('/_admin/routes/error/fields') && text('/_admin/routes/error/fields') !== '' );
	check( 'each missing field points at a sentence of its own that says what is missing', errorsOf( d ).length === 6 && [ w.field( d, 'uri' ), w.addressControls( d )[0], w.rowControl( d, 'name', 'de_DE' ), w.rowControl( d, 'title', 'en_US' ) ].every( el =>
		errorsOf( d ).some( sentence => el.attributes['aria-describedby'].split(' ').indexOf( sentence.id ) !== -1 ) ) && errorsOf( d ).every( sentence => sentence.textContent.length > 0 ) );

	w.type( w.field( d, 'uri' ), '/new' );
	w.type( w.addressControls( d )[0], '/new' );
	w.type( w.rowControl( d, 'name', 'de_DE' ), 'Neu' );
	w.type( w.rowControl( d, 'title', 'de_DE' ), 'Neu' );
	w.type( w.rowControl( d, 'name', 'en_US' ), '   ' );
	w.type( w.rowControl( d, 'title', 'en_US' ), 'New' );
	fire( w.button( d, CREATE ), 'click' );
	check( 'only what is still missing stays marked, a blank value counts as missing, and the focus is on it', w.requests.length === asked && w.field( d, 'uri' ).attributes['aria-invalid'] === undefined && w.rowControl( d, 'name', 'de_DE' ).attributes['aria-invalid'] === undefined
		&& w.rowControl( d, 'name', 'en_US' ).attributes['aria-invalid'] === 'true' && w.rowControl( d, 'title', 'en_US' ).attributes['aria-invalid'] === undefined && w.focused() === w.rowControl( d, 'name', 'en_US' ) && errorsOf( d ).length === 1 );
}

{
	// A template nobody chose is refused as well
	const w = world();
	w.open( listing( [ '/' ] ) );
	fire( w.newButton(), 'click' );
	const d = w.dialog();
	const chooser = w.field( d, 'template' );
	check( 'without a blank page the template select asks for a choice: a disabled, selected placeholder first, and not the first template on offer in disguise', chooser.children[0].value === '' && chooser.children[0].disabled === true && chooser.value === '' && chooser.children.length === 5 );
	w.type( w.field( d, 'uri' ), '/new' );
	w.type( w.addressControls( d )[0], '/new' );
	w.fillTexts( d );
	const asked = w.requests.length;
	fire( w.button( d, CREATE ), 'click' );
	check( 'a template nobody chose is refused', w.requests.length === asked && chooser.attributes['aria-invalid'] === 'true' && w.focused() === chooser );
}

// --- a new route: the same dialog at @new ------------------------------------------------------------------------------------

{
	const w = world();
	w.open( listing( [ '/', '/about', '/contact' ], { defaultTemplate : 'page-blank' } ) );

	fire( w.newButton(), 'click' );
	const d = w.dialog();
	const asked = w.requests.length;
	const position = w.h.index();
	check( 'the form of a new route is one entry of the history, marked as the record\'s own, at the address with the word @new', w.h.hash() === '#routes/routes/@new' && w.h.router.marked() === 'routes' && codec.read( w.h.hash() ).parts.words[0] === true );

	w.type( w.field( d, 'uri' ), '/new' );
	w.type( w.addressControls( d )[0], '/new' );
	w.fillTexts( d );
	fire( w.button( d, CREATE ), 'click' );
	check( 'a complete form is sent, an empty description with it, for a route that did not exist: originalUri empty', w.requests.length === asked + 1 && w.requests.at(-1).action === 'routes/save' && w.requests.at(-1).payload.originalUri === ''
		&& w.requests.at(-1).payload.uri === '/new' && JSON.stringify( w.requests.at(-1).payload.httpUris ) === '{"":"/new"}' && w.requests.at(-1).payload.template === 'page-blank' && w.requests.at(-1).payload.text.en_US.description === ''
		&& Object.keys( w.requests.at(-1).payload ).sort().join() === 'httpUris,navs,originalUri,statusCode,template,text,uri' );
	check( '...the fields cannot be typed into while it is on its way', tag( d, 'input' ).filter( el => el.type !== 'search' && el.type !== 'checkbox' ).every( el => el.disabled === true ) );

	const answered = listing( [ '/', '/about', '/contact', '/new' ] );
	answered.pages[3].text.de_DE.name = 'Neue Seite';
	w.answer( 200, { pages : answered.pages } );
	check( 'a save that went through is the record of that page in the same dialog: its title, its Element URI under it, its address written over the one of the form (no new step of the history)', w.dialog() === d && w.titleOf( d ) === 'Neue Seite' && w.subtitleOf( d ) === '/new'
		&& w.h.hash() === '#routes/routes/%2Fnew' && w.h.index() === position );
	check( '...the page is in the table, at the end, and its row is the one that is drawn anew', w.ids().join() === '/,/about,/contact,/new' && has( w.row('/new'), 'is-fresh' ) );
	check( '...it has the buttons of a route that exists: Delete route, Save, Save and continue - and no Create', w.button( d, DELETE ).hidden !== true && w.button( d, SAVE ) !== undefined && w.button( d, CREATE ) === undefined );
	check( '...the other panels are told, with the address of the page, and a toast says the route was created', w.touches.length === 1 && w.touches[0][1] === '/new' && w.toasts().at(-1) === text('/_admin/routes/msg/created') && text('/_admin/routes/msg/created') !== '' );
	check( '...the shell would not ask: what the record holds is what is saved', w.h.dirty.isDirty( [ 'routes' ] ) === false );
	fire( w.cross( d ), 'click' );
	check( '...and the cross takes the one entry off the history and puts the focus on the new row', w.dialog() === null && w.h.went.at(-1) === -1 && w.focused() === w.row('/new') );
}

{
	// A refusal that names a field is said at that field - and the form stays a form that makes
	const w = world();
	w.open( listing( [ '/', '/about' ], { defaultTemplate : 'page-blank' } ) );
	fire( w.newButton(), 'click' );
	const d = w.dialog();
	w.type( w.field( d, 'uri' ), '/dup' );
	w.type( w.addressControls( d )[0], '/dup' );
	w.fillTexts( d );
	fire( w.button( d, CREATE ), 'click' );
	w.answer( 400, { error : 'invalid uri', code : 'routes_invalid_uri', params : [ '/dup' ], field : 'uri' } );
	check( 'a refusal that names the Element URI marks that field, says it under it and puts the focus there', w.field( d, 'uri' ).attributes['aria-invalid'] === 'true' && w.focused() === w.field( d, 'uri' ) && errorsOf( d ).length === 1
		&& errorsOf( d )[0].textContent === text('/_admin/error/routes_invalid_uri').replace( '%s', '/dup' ) );
	check( '...and in the line of the bar, as an alert, with the same sentence', w.lineOf( d ).dataset.state === 'error' && w.lineOf( d ).textContent === errorsOf( d )[0].textContent );
	check( '...the form stays as it is - the form that makes, with no list changed, no toast and nothing said to the other panels - and its fields can be typed into again', w.dialog() === d && w.ids().join() === '/,/about' && w.toasts().length === 0 && w.touches.length === 0
		&& w.field( d, 'uri' ).disabled === false && w.h.hash() === '#routes/routes/@new' && w.button( d, CREATE ) !== undefined );

	fire( w.button( d, CREATE ), 'click' );
	w.answer( 400, { error : 'missing name', code : 'routes_missing_name', params : [ 'en_US' ], field : 'name' } );
	check( 'a refusal that names a name of a language marks the row of that language, with the language written as the workbench writes it', w.rowControl( d, 'name', 'en_US' ).attributes['aria-invalid'] === 'true' && w.rowControl( d, 'name', 'de_DE' ).attributes['aria-invalid'] === undefined
		&& w.focused() === w.rowControl( d, 'name', 'en_US' ) && errorsOf( d ).length === 1 && errorsOf( d )[0].textContent.includes('English') && errorsOf( d )[0].textContent.includes('en_US') === false );

	fire( w.button( d, CREATE ), 'click' );
	w.answer( 500, null );
	check( 'an answer with no reason falls back to the shared sentence', w.lineOf( d ).textContent === '(500) '+ text('/_admin/common/error/save') );
}

{
	// Escape, with nothing typed, returns to the directory; the address is the directory's and the focus goes to the button
	const w = world();
	w.open();
	const position = w.h.index();
	fire( w.newButton(), 'click', { currentTarget : w.newButton() } );
	const d = w.dialog();
	fire( d, 'keydown', { key : 'Escape', preventDefault() {} } );
	check( 'Escape on the form of a new route with nothing typed closes it: the address is the directory\'s, the entry of the history is taken off, and the focus is on the New route button', w.dialog() === null && w.h.went.at(-1) === -1 && w.focused() === w.newButton() );
	void position;
}

{
	// Reload at the address of the form
	const w = world( codec.href( 'routes', [ codec.word('new') ] ) );
	w.panel.showCurrent();
	w.answer( 200, listing( [ '/', '/about' ], { defaultTemplate : 'page-blank' } ) );
	check( 'a fresh load on #routes/routes/@new opens the form of a new route, over the directory', w.dialog() !== null && w.titleOf( w.dialog() ) === text('/_admin/routes/label/new') && w.field( w.dialog(), 'uri' ).value === '' && w.ids().length === 2 );
	check( '...it is no step of the history of its own: the address stays', w.h.hash() === '#routes/routes/@new' );
}

{
	// A page whose Element URI is /new and one called @new are entries, not the form; a route called "new" keeps an address of its own
	const mk = ( uris, hash ) => { const w = world( hash ); w.panel.showCurrent(); w.answer( 200, listing( [], { pages : uris.map( uri => page( uri, { text : { de_DE : { name : 'P '+ uri, title : 'T', description : '' }, en_US : { name : 'E', title : 'T', description : '' } } } ) ), templates : [ 'page-new', 'page-@new', 'page-home', 'page-/new' ] } ) ); return w };

	const a = mk( [ '/new', '/x' ], codec.href( 'routes', [ '/new' ] ) );
	check( 'a page whose Element URI is /new is an entry: its address opens its record, not the form that makes a route', a.dialog() !== null && a.titleOf( a.dialog() ) === 'P /new' );
	check( '...with its own Element URI in the field', a.field( a.dialog(), 'uri' ).value === '/new' );

	const b = mk( [ '@new', '/x' ], codec.href( 'routes', [ '@new' ] ) );
	check( 'a page whose Element URI is @new has the part %40new in its address and opens as that page, not as the form', b.h.hash() === '#routes/routes/%40new' && b.dialog() !== null && b.field( b.dialog(), 'uri' ).value === '@new' );

	const c = mk( [ 'new', '/x' ], codec.href( 'routes', [ 'new' ] ) );
	check( 'a route called new (written by hand, no slash) has its own address and a fresh load on it opens that route', c.h.hash() === '#routes/routes/new' && c.dialog() !== null && c.field( c.dialog(), 'uri' ).value === 'new' && c.rows().length === 2 );
	check( '...the row of it opens at an address of its own', ( () => { fire( c.cross( c.dialog() ), 'click' ); c.openRow('new'); return c.h.hash() === '#routes/routes/new' } )() );

	// The key @new is no form that makes a route
	const e = world( codec.href( 'routes', [ '@new' ] ) );
	e.panel.showCurrent();
	e.answer( 200, listing( [ '/', '/about' ] ) );
	check( 'the key @new, with no page of that name, is no form that makes one: it is a page that is not there', e.dialog() === null && byClass( e.mount, 'nino-admin-notice' ).length === 1 && e.h.hash() === '#routes' );
}

// --- the Element URI is changed ---------------------------------------------------------------------------------------------

{
	const w = world();
	w.open( listing( [ '/', '/about', '/contact' ] ) );
	const d = w.openRow('/contact');
	const position = w.h.index();
	w.type( w.field( d, 'uri' ), '/kontakt-neu' );
	fire( w.button( d, SAVE ), 'click' );
	check( 'a changed Element URI is sent as the same page: the one it had is the originalUri', w.requests.at(-1).payload.originalUri === '/contact' && w.requests.at(-1).payload.uri === '/kontakt-neu' );

	const renamed = listing( [ '/', '/about', '/kontakt-neu' ] ).pages;
	renamed[2].template = 'page-contact';
	renamed[2].text = page( '/contact' ).text;
	w.answer( 200, { pages : renamed } );
	check( 'the record has the new Element URI as its key: the head says it, the address is written over the old one, with no new step of the history', w.subtitleOf( d ) === '/kontakt-neu' && w.h.hash() === '#routes/routes/%2Fkontakt-neu' && w.h.index() === position);
	check( '...the row of the new key is in the table, the one of the old key is not', w.ids().join() === '/,/about,/kontakt-neu' && has( w.row('/kontakt-neu'), 'is-fresh' ) );
	check( '...the other panels are told the page that was written', w.touches.at(-1)[0] === 'routes' );

	const sent = w.requests.length;
	w.type( w.rowControl( d, 'name', 'en_US' ), 'Contact us' );
	fire( w.button( d, SAVE ), 'click' );
	check( 'the next save names the page by the new Element URI', w.requests.length === sent + 1 && w.requests.at(-1).payload.originalUri === '/kontakt-neu' );
	w.answer( 200, { pages : renamed } );

	fire( w.cross( d ), 'click' );
	check( 'closing the record puts the focus on the row of the new key, and takes the one entry off the history', w.dialog() === null && w.focused() === w.row('/kontakt-neu') && w.h.went.at(-1) === -1 );
}

// --- the addresses: one for every language, or one for each ----------------------------------------------------------------

{
	const addressed = listing( [ '/', '/about', '/contact' ], { defaultTemplate : 'page-blank' } );
	addressed.pages[1].httpUri = '/ueber-uns';
	addressed.pages[1].httpUris = { de_DE : '/ueber-uns', en_US : '/about' };
	addressed.pages[2].httpUri = '/kontakt';
	addressed.pages[2].httpUris = { '' : '/contact', de_DE : '/kontakt' };

	const w = world();
	w.open( addressed );

	const turn = ( d, on ) => { w.oneSwitch( d ).checked = on; fire( w.oneSwitch( d ), 'change', { target : w.oneSwitch( d ) } ) };
	const addressName = ( d, locale ) => w.addressOf( d, locale ).parentNode.parentNode.children[0].textContent;
	const reopen = key => { fire( w.cross( w.dialog() ), 'click' ); if( w.asking() !== null ) w.answerAsk('/_admin/common/label/discard'); return w.openRow( key ) };

	check( 'a page with an address per language is one row, and the row names every address - the one several languages share too', w.cells('/about')[1].children[0].children[0].textContent === '/ueber-uns, /about' && w.cells('/contact')[1].children[0].children[0].textContent === '/contact, /kontakt' );
	check( '...and the link out goes to the address the server gave the page as its own', tag( w.cells('/about')[1], 'a' )[0].href === '/ueber-uns' && tag( w.cells('/contact')[1], 'a' )[0].href === '/kontakt' );

	fire( w.move( '/about', 'down' ), 'click' );
	check( 'a move names the page by its Element URI and not by one of its addresses', JSON.stringify( w.requests.at(-1).payload ) === '{"uri":"/about","direction":"down"}' );
	w.answer( 200, { pages : addressed.pages } );

	let d = w.openRow('/');
	check( 'a page with one address for every language opens with the switch on and the one field, named as the address and not drawn as a group', w.oneSwitch( d ).checked === true && w.addressControls( d ).length === 1 && w.addressControls( d )[0].dataset.address === ''
		&& w.addressControls( d )[0].value === '/' && w.addressControls( d )[0].dataset.field === 'httpUri' && w.fieldById( d, 'routes-form-addresses' ).children[0].tagName === 'LABEL' );

	const copy = findAll( d, el => el.className === 'nino-admin-switch-copy' )[0];
	const sentence = findAll( copy, el => el.className === 'nino-admin-help-text' )[0];
	check( 'the switch says what it is for in the words of the panel, and what it does behind its (?)', findAll( copy, el => el.className === 'nino-admin-field-text' )[0].children[0].textContent === text('/_admin/routes/label/one-address')
		&& sentence !== undefined && sentence.textContent === text('/_admin/routes/hint/one-address') && w.oneSwitch( d ).attributes['aria-describedby'] === sentence.id && text('/_admin/routes/label/one-address') !== '' && text('/_admin/routes/hint/one-address') !== '' );

	turn( d, false );
	check( 'turned off on a page that has one address for every language, every language starts from it, one row each', w.addressControls( d ).map( el => el.dataset.address ).join() === 'de_DE,en_US' && w.addressOf( d, 'de_DE' ).value === '/' && w.addressOf( d, 'en_US' ).value === '/' );

	d = reopen('/about');
	check( 'a page with an address per language opens with the switch off and a row for each language, in the order of the languages', w.oneSwitch( d ).checked === false && w.addressControls( d ).map( el => el.dataset.address ).join() === 'de_DE,en_US' );
	check( '...each named by its language and not by its code, and holding that language\'s own address', addressName( d, 'de_DE' ) === 'Deutsch' && addressName( d, 'en_US' ) === 'English' && w.addressOf( d, 'de_DE' ).value === '/ueber-uns' && w.addressOf( d, 'en_US' ).value === '/about' );
	check( '...each named for the server by the field it marks when it refuses that language\'s address', w.addressControls( d ).every( el => el.dataset.field === 'httpUri-'+ el.dataset.address ) );
	check( '...in a required group that the address names', ( () => { const group = w.addressOf( d, 'de_DE' ).parentNode.parentNode.parentNode; return group.attributes.role === 'group' && group.attributes['aria-required'] === 'true' } )() );

	d = reopen('/contact');
	check( 'a page with an address for every language and one of its own for a language shows that one in the row of the language, and the shared one in the others', w.oneSwitch( d ).checked === false && w.addressOf( d, 'de_DE' ).value === '/kontakt' && w.addressOf( d, 'en_US' ).value === '/contact' );

	d = reopen('/about');
	turn( d, true );
	check( 'turned to one address for every language, the rows of the languages give way to the one field, which starts from the address of the first language that has one', w.addressControls( d ).length === 1 && w.addressControls( d )[0].dataset.address === '' && w.addressControls( d )[0].dataset.field === 'httpUri' && w.fieldById( d, 'routes-form-http-uri' ).value === '/ueber-uns' );
	w.type( w.fieldById( d, 'routes-form-http-uri' ), '/everywhere' );
	turn( d, false );
	check( 'turned off again, the languages hold what they held: nothing typed is lost between two turns', w.addressOf( d, 'de_DE' ).value === '/ueber-uns' && w.addressOf( d, 'en_US' ).value === '/about' );
	w.type( w.addressOf( d, 'en_US' ), '/en-about' );
	turn( d, true );
	check( '...and the one field shows again what was typed into it before', w.fieldById( d, 'routes-form-http-uri' ).value === '/everywhere' );
	turn( d, false );
	check( '...and so does a language what was typed into its row', w.addressOf( d, 'en_US' ).value === '/en-about' && w.addressOf( d, 'de_DE' ).value === '/ueber-uns' );
	fire( w.cross( d ), 'click' );

	// A new page, with an address for each language: the map is what is sent
	const v = world();
	v.open( addressed );
	fire( v.newButton(), 'click' );
	let n = v.dialog();
	const turnN = on => { v.oneSwitch( n ).checked = on; fire( v.oneSwitch( n ), 'change', { target : v.oneSwitch( n ) } ) };
	check( 'a new page starts with the switch on and the one field empty', v.oneSwitch( n ).checked === true && v.addressControls( n ).length === 1 && v.addressControls( n )[0].dataset.address === '' && v.addressControls( n )[0].value === '' );
	v.type( v.field( n, 'uri' ), '/trip' );
	v.type( v.fieldById( n, 'routes-form-http-uri' ), '/trip' );
	v.field( n, 'template' ).value = 'page-blank';
	v.fillTexts( n );
	turnN( false );
	check( 'turned off, every language starts from what the one field held', v.addressOf( n, 'de_DE' ).value === '/trip' && v.addressOf( n, 'en_US' ).value === '/trip' );
	v.type( v.addressOf( n, 'en_US' ), '/reise' );
	const before = v.requests.length;
	fire( v.button( n, CREATE ), 'click' );
	check( 'a page with an address per language is sent as a map of language to address, beside the Element URI and none of the keys of an older shape', v.requests.length === before + 1 && v.requests.at(-1).action === 'routes/save' && JSON.stringify( v.requests.at(-1).payload.httpUris ) === '{"de_DE":"/trip","en_US":"/reise"}'
		&& Object.keys( v.requests.at(-1).payload ).sort().join() === 'httpUris,navs,originalUri,statusCode,template,text,uri' && v.requests.at(-1).payload.originalUri === '' && v.requests.at(-1).payload.uri === '/trip' );

	v.answer( 400, { error : 'missing', code : 'routes_missing_http_uri', params : [ 'en_US' ], field : 'httpUri-en_US' } );
	check( 'a refusal that names the address of a language marks that row and puts the focus on it, and no other', v.addressOf( n, 'en_US' ).attributes['aria-invalid'] === 'true' && v.focused() === v.addressOf( n, 'en_US' ) && v.addressOf( n, 'de_DE' ).attributes['aria-invalid'] === undefined && errorsOf( n ).length === 1 );
	check( '...and the line of the bar tells it in the words of the code, with the language written as the workbench writes it', text('/_admin/error/routes_missing_http_uri') !== '' && v.lineOf( n ).textContent === v.h.ui.format( text('/_admin/error/routes_missing_http_uri'), 'English' ) );

	turnN( true );
	const beforeOne = v.requests.length;
	fire( v.button( n, CREATE ), 'click' );
	check( 'with the switch on the same page is sent as the one address of every language', v.requests.length === beforeOne + 1 && JSON.stringify( v.requests.at(-1).payload.httpUris ) === '{"":"/trip"}' );
	v.answer( 409, { error : 'taken', code : 'routes_http_uri_taken', params : [ '/trip' ], field : 'httpUri' } );
	check( 'a refusal that names the address of every language marks the one field', v.fieldById( n, 'routes-form-http-uri' ).attributes['aria-invalid'] === 'true' && v.focused() === v.fieldById( n, 'routes-form-http-uri' ) );

	/*	A request gives an address once. Fields of languages that hold the same address are the address of those languages together and are sent as that, the
		empty key. The server's refusal of that address is for the first of those fields: no field of the form is called for every language	*/
	turnN( false );
	v.type( v.addressOf( n, 'en_US' ), '/trip' );
	const beforeFold = v.requests.length;
	fire( v.button( n, CREATE ), 'click' );
	check( 'rows per language that hold one address are sent as the address of every language, once', v.requests.length === beforeFold + 1 && JSON.stringify( v.requests.at(-1).payload.httpUris ) === '{"":"/trip"}' );
	v.answer( 400, { error : 'taken', code : 'routes_duplicate_http_uri', params : [ '/trip' ], field : 'httpUri' } );
	check( 'a refusal of that address marks and focuses the first of the rows it was sent for, and no other', v.addressOf( n, 'de_DE' ).attributes['aria-invalid'] === 'true' && v.focused() === v.addressOf( n, 'de_DE' ) && v.addressOf( n, 'en_US' ).attributes['aria-invalid'] === undefined );
}

{
	// The map a save sends, by itself
	const w = world();
	const panel = w.panel;
	const fieldsOf = pairs => pairs.map( pair => ( { locale : pair[0], value : pair[1] } ) );
	const mapOf = pairs => JSON.stringify( panel._addressMap( fieldsOf( pairs ) ) );

	check( 'fields that hold different addresses are sent as they are, and the field of every language as the empty key', mapOf( [ [ 'de_DE', '/a' ], [ 'en_US', '/b' ] ] ) === '{"httpUris":{"de_DE":"/a","en_US":"/b"},"folded":[]}' && mapOf( [ [ '', '/a' ] ] ) === '{"httpUris":{"":"/a"},"folded":[]}' );
	check( '...fields that hold one address are folded into the empty key, in the order they stand in, with the languages that were', mapOf( [ [ 'de_DE', '/a' ], [ 'en_US', '/b' ], [ 'fr_FR', '/b' ] ] ) === '{"httpUris":{"":"/b","de_DE":"/a"},"folded":["en_US","fr_FR"]}' );
	check( '...a blank around an address is none of it', mapOf( [ [ 'de_DE', ' /a' ], [ 'en_US', '/a ' ] ] ) === '{"httpUris":{"":"/a"},"folded":["de_DE","en_US"]}' );
	check( '...nor is the slash the server adds in front or drops behind: a, /a and /a/ are one address, sent as the server reads it', mapOf( [ [ 'de_DE', 'a' ], [ 'en_US', '/a/' ], [ 'fr_FR', '/a' ] ] ) === '{"httpUris":{"":"/a"},"folded":["de_DE","en_US","fr_FR"]}'
		&& mapOf( [ [ 'de_DE', '/' ], [ 'en_US', '/' ] ] ) === '{"httpUris":{"":"/"},"folded":["de_DE","en_US"]}' );
	check( '...fields that are empty are not an address they share', mapOf( [ [ 'de_DE', '' ], [ 'en_US', '' ] ] ) === '{"httpUris":{"de_DE":"","en_US":""},"folded":[]}' );
	check( '...and only one address is folded, the first one that two fields hold: the rest stand as they are for the server to refuse', mapOf( [ [ 'de_DE', '/a' ], [ 'de_AT', '/b' ], [ 'en_US', '/b' ], [ 'en_GB', '/a' ] ] ) === '{"httpUris":{"":"/a","de_AT":"/b","en_US":"/b"},"folded":["de_DE","en_GB"]}' );

	const refused = { error : 'taken', code : 'routes_http_uri_taken', params : [ '/a' ], field : 'httpUri' };
	check( 'a refusal naming the empty key\'s field is made the first of the folded fields', JSON.stringify( panel._refusedField( refused, [ 'en_US', 'fr_FR' ] ) ) === JSON.stringify( Object.assign( {}, refused, { field : 'httpUri-en_US' } ) ) && refused.field === 'httpUri' );
	check( '...and stays as it is where nothing was folded, names another field, or is no answer', panel._refusedField( refused, [] ) === refused && panel._refusedField( null, [ 'en_US' ] ) === null && panel._refusedField( { field : 'httpUri-fr_FR' }, [ 'en_US' ] ).field === 'httpUri-fr_FR' );
}

{
	// A page with an address for one language and one for the others, opened with the switch off, is saved as it is - which is nothing, since nothing was changed
	const three = listing( [], { locales : [ 'de_DE', 'en_US', 'fr_FR' ], localeNames : { de_DE : 'Deutsch', en_US : 'English', fr_FR : 'Français' }, templates : [ 'page-about' ] } );
	const trip = page( '/trip', { httpUri : '/reise', httpUris : { de_DE : '/reise', '' : '/trip' }, template : 'page-about' } );
	trip.text.fr_FR = { name : 'Voyage', title : 'Voyage', description : '' };
	three.pages = [ trip ];

	const w = world();
	w.h.ui.locales.names.fr_FR = 'Français';
	w.open( three );
	const d = w.openRow('/trip');
	check( 'a page that has an address of its own for a language and one for the others shows the one for the others in each of their rows', w.oneSwitch( d ).checked === false && w.addressOf( d, 'de_DE' ).value === '/reise' && w.addressOf( d, 'en_US' ).value === '/trip' && w.addressOf( d, 'fr_FR' ).value === '/trip' );
	fire( w.button( d, SAVE ), 'click' );
	check( '...and saved with nothing changed it sends nothing: the same page', w.requests.length === 1 );
	w.type( w.rowControl( d, 'name', 'fr_FR' ), 'Voyager' );
	fire( w.button( d, SAVE ), 'click' );
	check( '...changed, it sends the shared address as the empty key and the language of its own as itself: the same page', w.requests.length === 2 && JSON.stringify( w.requests[1].payload.httpUris ) === '{"":"/trip","de_DE":"/reise"}' && w.requests[1].payload.originalUri === '/trip' );
}

{
	// Two pairs of languages that each hold an address: one is the address of its languages, the other cannot say so, and the server refuses it for its later field
	const four = listing( [], { locales : [ 'de_DE', 'de_AT', 'en_US', 'en_GB' ], localeNames : { de_DE : 'Deutsch', de_AT : 'Österreich', en_US : 'English', en_GB : 'Britain' }, defaultTemplate : 'page-blank' } );
	const w = world();
	Object.assign( w.h.ui.locales.names, { de_AT : 'Österreich', en_GB : 'Britain' } );
	w.open( four );
	fire( w.newButton(), 'click' );
	const d = w.dialog();
	w.type( w.field( d, 'uri' ), '/pairs' );
	w.type( w.fieldById( d, 'routes-form-http-uri' ), '/kontakt' );
	w.fillTexts( d );
	w.oneSwitch( d ).checked = false;
	fire( w.oneSwitch( d ), 'change', { target : w.oneSwitch( d ) } );
	w.type( w.addressOf( d, 'en_US' ), '/contact' );
	w.type( w.addressOf( d, 'en_GB' ), ' /contact ' );
	fire( w.button( d, CREATE ), 'click' );
	check( 'two pairs of rows that each hold one address: the first is sent as the empty key, the second as it stands', JSON.stringify( w.requests.at(-1).payload.httpUris ) === '{"":"/kontakt","en_US":"/contact","en_GB":" /contact "}' );
	w.answer( 400, { error : 'twice', code : 'routes_duplicate_http_uri', params : [ '/contact' ], field : 'httpUri-en_GB' } );
	check( '...and the refusal, which names the later of the second pair, marks and focuses that row', w.addressOf( d, 'en_GB' ).attributes['aria-invalid'] === 'true' && w.focused() === w.addressOf( d, 'en_GB' ) && [ 'de_DE', 'de_AT', 'en_US' ].every( l => w.addressOf( d, l ).attributes['aria-invalid'] === undefined ) );
}

{
	// Every address row is a required one: marked, told, and the first of them takes the focus once the Element URI is there
	const w = world();
	w.open( listing( [ '/' ], { defaultTemplate : 'page-blank' } ) );
	fire( w.newButton(), 'click' );
	const d = w.dialog();
	w.oneSwitch( d ).checked = false;
	fire( w.oneSwitch( d ), 'change', { target : w.oneSwitch( d ) } );
	fire( w.button( d, CREATE ), 'click' );
	check( 'a row per language that is empty is marked, each with a sentence of its own', w.addressControls( d ).length === 2 && w.addressControls( d ).every( el => {
		const sentence = errorsOf( d ).find( each => el.attributes['aria-describedby'].split(' ').indexOf( each.id ) !== -1 );
		return el.attributes['aria-invalid'] === 'true' && sentence !== undefined && sentence.textContent.length > 0;
	} ) && new Set( w.addressControls( d ).map( el => el.attributes['aria-describedby'] ) ).size === 2 );
	check( '...and the Element URI, which stands first, has the focus', w.focused() === w.field( d, 'uri' ) );

	w.type( w.field( d, 'uri' ), '/x' );
	w.fillTexts( d );
	fire( w.button( d, CREATE ), 'click' );
	check( 'once that is filled the focus goes to the first address that is missing', w.focused() === w.addressOf( d, 'de_DE' ) && w.field( d, 'uri' ).attributes['aria-invalid'] === undefined );
	w.type( w.addressOf( d, 'de_DE' ), '/x' );
	fire( w.button( d, CREATE ), 'click' );
	check( 'only the address that is still missing stays marked, with the focus', w.addressOf( d, 'de_DE' ).attributes['aria-invalid'] === undefined && w.addressOf( d, 'en_US' ).attributes['aria-invalid'] === 'true' && w.focused() === w.addressOf( d, 'en_US' ) );
}

// --- delete ----------------------------------------------------------------------------------------------------------------------

{
	const w = world();
	const shared = listing( [ '/', '/about', '/contact' ] );
	shared.pages[0].template = shared.pages[1].template = shared.pages[2].template = 'page-shared';
	shared.pages[1].uri = '/site-about';
	shared.pages[1].text.en_US.title = 'About';
	w.open( shared );

	const questions = [];
	const confirm = w.h.ui.confirm;
	w.h.ui.confirm = function( level, options ) { questions.push( Object.assign( { level : level }, options ) ); return confirm.call( this, level, options ) };

	const d = w.openRow('/site-about');
	const askedBefore = w.requests.length;
	fire( w.button( d, DELETE ), 'click' );
	const asked = questions.at(-1);
	check( 'the delete question is a question of the destructive level, with a title and the answer worded like it, standing on the record', questions.length === 1 && asked.level === 'destructive' && asked.title === asked.yes && asked.title === text('/_admin/routes/confirm/delete-title') && text('/_admin/routes/confirm/delete-title') !== '' && asked.nested === true );
	check( '...the languages of the texts that stay are named as the workbench names them, never by their code', asked.details[1].lines[0].includes('de_DE') === false && asked.details[1].lines[0].includes('en_US') === false );
	check( '...and has two groups under it: what is deleted - every address - and what stays', Array.isArray( asked.details ) && asked.details.length === 2 && asked.details[0].title === text('/_admin/routes/confirm/deleted') && asked.details[0].lines.join() === '/about' && asked.details[1].title === text('/_admin/routes/confirm/kept') );
	check( '...what stays: the texts with their languages, the path fill, and the template with how many other routes use it', asked.details[1].lines.length === 2 && asked.details[1].lines[0].includes('/_nino/webpage/site-about/name|title (Deutsch, English)') && asked.details[1].lines[0].includes('/_nino/webpage/site-about/uri')
		&& asked.details[1].lines[1].includes('page-shared') && asked.details[1].lines[1].includes('2') );

	// A question that is answered no sends nothing
	const no = w.asking();
	fire( tag( no, 'button' ).find( b => b.textContent === text('/_admin/common/label/cancel') ), 'click' );
	check( 'a refused question sends nothing, and the record stays', w.requests.length === askedBefore && w.dialog() === d );

	// The sentences that say how many other routes use the template
	const sentenceFor = ( mutate, uri ) => {
		const v = world();
		const l = listing( [ '/', '/about', '/contact' ] );
		l.pages[0].template = l.pages[1].template = l.pages[2].template = 'page-shared';
		mutate( l );
		v.open( l );
		const asks = [];
		const c = v.h.ui.confirm;
		v.h.ui.confirm = function( level, options ) { asks.push( options ); return c.call( this, level, options ) };
		const e = v.openRow( uri );
		fire( v.button( e, DELETE ), 'click' );
		return asks.at(-1).details[1].lines[1];
	};
	check( 'one other route and none are said by a sentence of their own each', sentenceFor( l => { l.pages[2].template = 'page-contact' }, '/about' ) === w.h.ui.format( text('/_admin/routes/confirm/stays-template-one'), 'page-shared' )
		&& sentenceFor( l => { l.pages[2].template = 'page-contact'; l.pages[0].template = 'page-home' }, '/about' ) === w.h.ui.format( text('/_admin/routes/confirm/stays-template'), 'page-shared' )
		&& sentenceFor( () => {}, '/about' ) === w.h.ui.format( text('/_admin/routes/confirm/stays-template-shared'), 'page-shared', 2 ) );
	check( 'a route that picks its template at runtime names its body instead', sentenceFor( l => { l.pages[1].template = ''; l.pages[1].body = '[template /templates/page-[[/nino/http/response/locale]]]' }, '/about' ) === w.h.ui.format( text('/_admin/routes/confirm/stays-body'), '[template /templates/page-[[/nino/http/response/locale]]]' ) );

	// Yes: the request names the page by its Element URI; the answer closes the record and the focus goes to the next row
	fire( w.button( d, DELETE ), 'click' );
	fire( tag( w.asking(), 'button' ).find( b => b.textContent === text('/_admin/routes/confirm/delete-title') ), 'click' );
	check( 'a confirmed question deletes the route, by its Element URI', w.requests.at(-1).action === 'routes/delete' && JSON.stringify( w.requests.at(-1).payload ) === '{"uri":"/site-about"}' );
	w.touches.length = 0;
	w.toasts().length = 0;
	w.answer( 200, { pages : listing( [ '/', '/contact' ] ).pages } );
	check( '...a delete that went through tells the other panels that routes were written, with no address: the page is gone', w.touches.length === 1 && w.touches[0][0] === 'routes' && w.touches[0][1] === undefined );
	check( '...says it was deleted as a toast, closes the record by the history, and the table has lost the page', w.toasts().at(-1) === text('/_admin/common/msg/deleted') && w.dialog() === null && w.ids().join() === '/,/contact' && w.h.went.at(-1) === -1 );
	check( '...with the focus on the row after the one that went', w.focused() === w.row('/contact') );

	// A delete the server refuses is said in the line of the record, and the record stays
	const r = world();
	r.open( listing( [ '/', '/contact' ] ) );
	const e = r.openRow('/contact');
	fire( r.button( e, DELETE ), 'click' );
	fire( tag( r.asking(), 'button' ).find( b => b.textContent === text('/_admin/routes/confirm/delete-title') ), 'click' );
	r.answer( 409, { error : 'locked' } );
	check( 'a refused delete is said in the line of the bar, as an alert, and the record stays open with its page', r.lineOf( e ).dataset.state === 'error' && r.lineOf( e ).textContent === '(409) locked' && r.dialog() === e && r.ids().join() === '/,/contact' );
}

// --- the address a window beside the workbench goes to, the language, a body that picks its template -------------------------------

{
	const w = world();
	w.open();
	const panel = w.panel;

	panel._selectedLocale = 'en_US';
	w.h.admin.workLocale.current = null;
	check( 'the address of a page is the one of the language the panel is working in', panel._publicPath( { de_DE : '/ueber-uns', en_US : '/about' } ) === '/about' );
	check( '...else the one of every language', panel._publicPath( { de_DE : '/ueber-uns', '' : '/shared' } ) === '/shared' );
	check( '...else the first there is, an empty one passed over', panel._publicPath( { de_DE : '  ', fr_FR : '/accueil' } ) === '/accueil' );
	check( '...with the slash it lacks, and none for a page that has no address', panel._publicPath( { en_US : ' about ' } ) === '/about' && panel._publicPath( {} ) === '' && panel._publicPath( { en_US : '' } ) === '' );
	check( '...and none for a pattern', panel._publicPath( { '' : '/blog/*' } ) === '' );

	panel._selectedLocale = 'de_DE';
	w.h.admin.workLocale.current = 'en_US';
	check( 'the address and the name on the table are the workbench\'s language where the website has it', panel._language() === 'en_US' && panel._publicPath( { de_DE : '/ueber-uns', en_US : '/about' } ) === '/about' );
	w.h.admin.workLocale.current = 'it_IT';
	check( '...and the one the server named where it has not - a language the website does not serve yet', panel._language() === 'de_DE' && panel._publicPath( { de_DE : '/ueber-uns', en_US : '/about' } ) === '/ueber-uns' );
}

{
	// A route that picks its template at runtime has no template to choose: the disabled select is not a missing field, and the reason it is locked is behind its (?)
	const w = world();
	const runtime = listing( [ '/legal' ], { templates : [ 'page-about' ] } );
	runtime.pages[0].template = '';
	runtime.pages[0].body = '[template /templates/page-legal.[[/nino/http/response/locale]]]';
	w.open( runtime );
	const d = w.openRow('/legal');
	check( 'a route whose body decides its template has the select locked on an option of its own, and the reason behind the (?) at the select', w.field( d, 'template' ).disabled === true && w.field( d, 'template' ).value === ''
		&& findAll( d, el => has( el, 'nino-admin-help-text' ) && el.textContent.includes('page-legal.[[/nino/http/response/locale]]') ).length === 1 );
	w.type( w.rowControl( d, 'name', 'de_DE' ), 'R' );
	fire( w.button( d, SAVE ), 'click' );
	check( 'a route with a runtime body saves without choosing a template', w.requests.length === 2 && w.requests[1].payload.template === '' && w.requests[1].payload.originalUri === '/legal' );
}

// --- a template or a navigation written since the list was read -----------------------------------------------------------------

{
	const w = world();
	w.open( listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-home' ] } ) );
	const idle = w.requests.length;

	w.panel.showCurrent();
	check( 'shown again with no template written since, the panel asks for nothing', w.requests.length === idle );

	w.h.admin.touch('templates');
	const d = w.openRow('/contact');
	w.panel.showCurrent();
	check( 'a template written while a record is open is not read now: the record is not drawn over', w.requests.length === idle && w.dialog() === d );

	w.close( d );
	check( '...closing the record is no showing of the panel, and asks for nothing either', w.requests.length === idle );
	w.panel.showCurrent();
	check( '...the showing that finds no record open reads the list again', w.requests.length === idle + 1 && w.requests[idle].action === 'routes/list' );
	w.panel.showCurrent();
	check( '...and until the answer is there nothing more is asked: a second showing does not start a second read', w.requests.length === idle + 1 );

	w.answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-home', 'page-new' ] } ) );
	check( '...the table is where it was - the search, the rows - and has no loading sentence in the way', byClass( w.mount, 'nino-admin-loading' ).length === 0 && w.ids().join() === '/,/contact' );
	fire( w.newButton(), 'click' );
	check( '...and the template that was written is on offer in the form of a new route', w.field( w.dialog(), 'template' ).children.map( o => o.value ).indexOf('page-new') !== -1 );
	w.close( w.dialog() );
	const settled = w.requests.length;
	w.panel.showCurrent();
	check( '...and with nothing written after that, the next showing asks for nothing', w.requests.length === settled && w.panel._stamp === 1 );

	// A template written while the request is out is not in its answer
	w.h.admin.touch('templates');
	w.panel.showCurrent();
	const out = w.requests.length;
	w.h.admin.touch('templates');
	w.answer( 200, listing( [ '/', '/contact' ] ) );
	check( 'the stamp is the one from before the request went out', w.panel._stamp === 2 );
	w.panel.showCurrent();
	check( '...so a template written while it was out is asked for at the next showing', w.requests.length === out + 1 );
	w.answer( 200, listing( [ '/', '/contact' ] ) );

	// The navigations are read again when the Navigations panel wrote them, as the templates are
	const quiet = w.requests.length;
	w.panel.showCurrent();
	check( 'shown again with no navigation written since, the panel asks for nothing', w.requests.length === quiet );
	w.h.admin.touch('navigations');
	w.panel.showCurrent();
	check( 'a navigation written since the list was read is read again at the next showing, where no record is open', w.requests.length === quiet + 1 && w.requests.at(-1).action === 'routes/list' );
	w.answer( 200, listing( [ '/', '/contact' ], { navs : [ 'recht' ], navNames : { recht : 'Rechtliches' } } ) );
	check( '...the column of the navigations is there now, the record offers them by their new names, and a refused tick is said at the group', w.heads().some( el => el.dataset.key === 'navText' ) && ( () => {
		const e = w.openRow('/contact');
		const ok = w.navTicks( e ).map( el => el.value ).join() === 'recht';
		w.navTicks( e )[0].checked = true;
		w.h.dirty.refresh();
		fire( w.button( e, SAVE ), 'click' );
		w.answer( 400, { error : 'unknown navigation', code : 'routes_unknown_nav', params : [ 'legal' ], field : 'navs' } );
			return ok && findAll( e, el => el.tagName === 'FIELDSET' && has( el, 'nino-admin-checklist-field' ) )[0].attributes['aria-invalid'] === 'true' && errorsOf( e ).length === 1 && text('/_admin/error/routes_unknown_nav') !== '';
	} )() );
}

// --- the navigations a page stands in --------------------------------------------------------------------------------------------

{
	const w = world();
	const withNavs = listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-home' ], navs : [ 'main', 'footer', 'legal' ], navNames : { main : 'Hauptmenü', footer : '' } } );
	withNavs.pages[1].navs = [ 'footer' ];
	w.open( withNavs );
	const d = w.openRow('/contact');
	const group = findAll( d, el => el.tagName === 'FIELDSET' && has( el, 'nino-admin-checklist-field' ) )[0];
	const navRows = findAll( group, el => el.tagName === 'LABEL' );

	check( 'the navigations are the ticks of one group, one for each the project has, the ones the page stands in ticked', w.navTicks( d ).map( el => el.value ).join() === 'main,footer,legal' && w.navTicks( d ).map( el => el.checked ).join() === 'false,true,false' );
	check( '...a navigation with a name is told by the name and its key quietly beside it, one without by its key alone', navRows[0].children[1].textContent === 'Hauptmenü' && navRows[0].children[2]?.textContent === 'main' && navRows[1].children[1].textContent === 'footer' && navRows[1].children.length === 2 );
	check( '...the group has the name and the (?) of a field, and no tick stands by itself in the form', group.children[0].tagName === 'LEGEND' && findAll( group, el => has( el, 'nino-admin-help-text' ) ).length === 1
		&& findAll( d, el => el.type === 'checkbox' && el.dataset.key !== 'navs' && el.dataset.key !== 'one-address' ).length === 0 );
	w.navTicks( d )[0].checked = true;
	w.navTicks( d )[1].checked = false;
	w.h.dirty.refresh();
	fire( w.button( d, SAVE ), 'click' );
	check( 'the keys that are ticked, in the order of the list, are what is sent - a tick is a change', w.requests.at(-1).payload.navs.join() === 'main' );
}

{
	const w = world();
	w.open( listing( [ '/', '/contact' ] ) );
	const d = w.openRow('/contact');
	check( 'without a navigation - the Navigation module is off - the record has no such group, and sends none', w.navTicks( d ).length === 0 );
	w.type( w.field( d, 'description' ), 'x' );
	fire( w.button( d, SAVE ), 'click' );
	check( '...so the request holds an empty list', w.requests.at(-1).payload.navs.length === 0 );
}

// --- the description is the one of the language the workbench edits content in ------------------------------------------------

{
	const w = world();
	const withDescriptions = listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-home' ] } );
	withDescriptions.pages[1].text.de_DE = { name : 'Kontakt', title : 'Kontakt', description : 'Schreib uns.' };
	withDescriptions.pages[1].text.en_US = { name : 'Contact', title : 'Contact', description : 'Write to us.' };
	w.open( withDescriptions );
	w.h.admin.workLocale.current = 'de_DE';
	const d = w.openRow('/contact');
	const badges = () => findAll( d, el => has( el, 'nino-admin-locale-badge' ) && el.tagName === 'SPAN' );

	check( 'the record shows the description of the language of the workbench, and says nothing of the language while it is the native one', w.field( d, 'description' ).value === 'Schreib uns.' && badges().length === 0 );

	w.h.admin.workLocale.current = 'en_US';
	w.h.admin.workLocale._followers.forEach( follower => follower.handler( 'en_US' ) );
	check( 'it follows the language when the workbench moves it: the record shows the description of the new one, with the badge of the language at the head of its body, and the table the names in that language', w.field( d, 'description' ).value === 'Write to us.'
		&& badges().length === 1 && w.titleOf( d ) === 'Contact' && byClass( w.cells('/contact')[0], 'routes-name-label' )[0].textContent === 'Contact' );
	check( '...and what the record holds is what is saved: the shell would not ask', w.h.dirty.isDirty( [ 'routes' ] ) === false );

	w.type( w.field( d, 'description' ), 'Write to us now.' );
	fire( w.button( d, SAVE ), 'click' );
	check( 'a save sends the description of that language as it stands and the other one as it was', w.requests.at(-1).payload.text.en_US.description === 'Write to us now.' && w.requests.at(-1).payload.text.de_DE.description === 'Schreib uns.' );
	const after = listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-home' ] } );
	after.pages[1].text.de_DE = { name : 'Kontakt', title : 'Kontakt', description : 'Schreib uns.' };
	after.pages[1].text.en_US = { name : 'Contact', title : 'Contact', description : 'Write to us now.' };
	w.answer( 200, { pages : after.pages } );

	w.h.admin.workLocale.current = 'de_DE';
	w.h.admin.workLocale._followers.forEach( follower => follower.handler( 'de_DE' ) );
	check( 'what was saved is what the record shows when the language moves on, in both languages', w.field( d, 'description' ).value === 'Schreib uns.' && badges().length === 0 );
	w.h.admin.workLocale.current = 'en_US';
	w.h.admin.workLocale._followers.forEach( follower => follower.handler( 'en_US' ) );
	check( '...and the other language shows what was saved there', w.field( d, 'description' ).value === 'Write to us now.' );

	// The form of a new route follows the language too: no row to be stepped to, what it draws in the language is put right where it stands
	w.close( d );
	fire( w.newButton(), 'click' );
	const n = w.dialog();
	w.h.admin.workLocale.current = 'de_DE';
	w.h.admin.workLocale._followers.forEach( follower => follower.handler( 'de_DE' ) );
	check( 'the form of a new route follows the language: the badge goes, and it is not changed by it', byClass( n, 'routes-worklang' )[0].hidden === true && tag( byClass( n, 'routes-worklang' )[0], 'span' ).length === 0 && w.h.dirty.isDirty( [ 'routes' ] ) === false );
}

{
	// An entry that the search hides has no place among the rows the record steps through: what it draws in the language is put right where it stands
	const w = world();
	const described = listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-home' ] } );
	described.pages[1].text.de_DE.description = 'Schreib uns.';
	described.pages[1].text.en_US.description = 'Write to us.';
	w.open( described );
	w.h.admin.workLocale.current = 'de_DE';
	const d = w.openRow('/contact');
	w.type( w.search(), 'zzz' );
	w.h.admin.workLocale.current = 'en_US';
	w.h.admin.workLocale._followers.forEach( follower => follower.handler( 'en_US' ) );
	check( 'a record whose page the search hides follows the language too: the description is the one of the language, and the record is not changed by it', w.field( d, 'description' ).value === 'Write to us.' && w.h.dirty.isDirty( [ 'routes' ] ) === false
		&& byClass( d, 'routes-worklang' )[0].hidden === false );
}

// --- unsaved input: the shell is asked by the name of the panel -----------------------------------------------------------------

{
	const w = world();
	w.open( listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-home' ] } ) );
	const d = w.openRow('/contact');

	check( 'the record is registered with the shell under the name of the panel, which is what it asks by', w.h.dirty.entry('routes') !== null && w.h.dirty.entry('routes-feature') !== null && w.h.dirty.isDirty( [ 'routes' ] ) === false );
	w.type( w.rowControl( d, 'name', 'de_DE' ), '' );
	check( 'a field that was changed is unsaved input of the panel', w.h.dirty.isDirty( [ 'routes' ] ) === true && w.h.dirty.isDirty( [ 'routes-feature' ] ) === false );

	const outcomes = [];
	const before = w.requests.length;
	w.h.dirty.entry('routes').save( ok => outcomes.push( ok ) );
	check( 'the Save the shell runs of a record that is not complete reports false and sends nothing', outcomes.join() === 'false' && w.requests.length === before );
	w.type( w.rowControl( d, 'name', 'de_DE' ), 'Kontakt!' );
	w.h.dirty.entry('routes').save( ok => outcomes.push( ok ) );
	w.answer( 500, null );
	check( '...one the server refuses reports false', outcomes.join() === 'false,false' && w.lineOf( d ).dataset.state === 'error' );
	w.h.dirty.entry('routes').save( ok => outcomes.push( ok ) );
	w.answer( 200, { pages : listing( [ '/', '/contact' ] ).pages.map( ( p, at ) => at === 1 ? Object.assign( p, { text : Object.assign( {}, p.text, { de_DE : Object.assign( {}, p.text.de_DE, { name : 'Kontakt!' } ) } ) } ) : p ) } );
	check( '...and one that is written reports true', outcomes.join() === 'false,false,true' && w.h.dirty.isDirty( [ 'routes' ] ) === false );

	// Closing a record that holds input asks first
	w.type( w.rowControl( d, 'name', 'de_DE' ), 'Anders' );
	fire( w.cross( d ), 'click' );
	check( 'closing a record that holds input asks Save, Discard or Cancel - and Cancel keeps it, with what was typed', w.asking() !== null && ( fire( tag( w.asking(), 'button' ).find( b => b.textContent === text('/_admin/common/label/cancel') ), 'click' ), w.dialog() === d && w.rowControl( d, 'name', 'de_DE' ).value === 'Anders' ) );
}

// --- the routes of features and modules: a list under the directory, and a record of its own -----------------------------------------

const withFeatures = ( extra ) => listing( [ '/', '/contact' ], Object.assign( { templates : [ 'page-contact', 'page-home' ], runtime : FEATURES.map( entry => JSON.parse( JSON.stringify( entry ) ) ) }, extra || {} ) );
const featureHash = uri => codec.href( 'routes', [ codec.word('feature'), uri ] );
const pageHash = uri => codec.href( 'routes', [ uri ] );
const NEWS = '/newsletter';

{
	const w = world();
	w.open( listing( [ '/', '/contact' ] ) );
	check( 'with no feature route nothing is drawn for them: no heading, no second list', byClass( w.mount, 'nino-admin-section-title' ).length === 0 && w.features().length === 0 );
}

{
	const w = world();
	w.open( withFeatures() );

	const title = byClass( w.mount, 'nino-admin-section-title' );
	check( 'feature routes have a title of their own, with the sentence that says what they are behind a (?)', title.length === 1 && title[0].textContent === text('/_admin/routes/runtime/title') && text('/_admin/routes/runtime/title') !== ''
		&& findAll( title[0], el => has( el, 'nino-admin-help-text' ) && el.textContent === text('/_admin/routes/runtime/help') ).length === 1 && text('/_admin/routes/runtime/help') !== '' && byClass( w.mount, 'nino-admin-hint' ).length === 0 );
	check( 'they stand below the directory and above the bar of the list, a row each, named by what was written and by the path', w.features().length === 2 && byClass( w.features()[0], 'nino-admin-list-copy' )[0].children[1].textContent === '/blog/*' && byClass( w.features()[0], 'nino-admin-list-copy' )[0].children[0].textContent === 'Beitrag'
		&& byClass( w.features()[1], 'nino-admin-list-copy' )[0].children[0].textContent === '/.newsletter' && w.mount.children.map( el => el.id ).slice( 0, 2 ).join() === 'routes-directory,routes-features' && has( w.mount.children[2], 'nino-admin-actionbar' ) );
	check( '...without buttons and without a link out: none of them is the project\'s to move', w.features().every( li => byClass( li, 'nino-admin-row-actions' ).length === 0 ) );
	check( '...a row is a link to its record, #routes/routes/@feature/<Element-URI>', w.featureLink( NEWS ).href === '#routes/routes/@feature/%2Fnewsletter' );

	fire( w.featureLink( NEWS ), 'click', { currentTarget : w.featureLink( NEWS ), preventDefault() {} } );
	const d = w.dialog();
	check( 'a row opens the record of the page, at its address, one step of the history', d !== null && w.h.hash() === '#routes/routes/@feature/%2Fnewsletter' && w.h.router.marked() === 'routes' );
	check( '...its title is the name - the path where nobody wrote one - and its paths stand under it', w.titleOf( d ) === '/.newsletter' && w.subtitleOf( d ) === '/.newsletter' );
	check( '...the paths are a line to read, as text, and no field', byClass( d, 'nino-admin-readline' ).length === 1 && byClass( d, 'nino-admin-readline-value' )[0].textContent === '/.newsletter' && w.field( d, 'uri' ) === undefined && w.field( d, 'template' ) === undefined );
	check( '...a name, a title and a description, the description of the language of the workbench', [ 'de_DE', 'en_US' ].every( l => w.rowControl( d, 'name', l ) !== undefined && w.rowControl( d, 'title', l ) !== undefined ) && w.field( d, 'description' ) !== undefined );
	check( '...no navigations, no status code, no delete and no form that makes: Save and Save and continue at the right', w.navTicks( d ).length === 0 && w.field( d, 'statusCode' ) === undefined && w.button( d, DELETE ) === undefined && w.button( d, CREATE ) === undefined
		&& w.button( d, SAVE ) !== undefined && w.button( d, SAVE_NEXT ) !== undefined );

	const asked = w.requests.length;
	fire( w.button( d, SAVE ), 'click' );
	check( 'a name and a title are required in every language here as well', w.requests.length === asked && w.rowControl( d, 'name', 'de_DE' ).attributes['aria-invalid'] === 'true' && w.rowControl( d, 'title', 'en_US' ).attributes['aria-invalid'] === 'true' && w.lineOf( d ).textContent === text('/_admin/routes/error/fields') );
	w.type( w.rowControl( d, 'name', 'de_DE' ), 'Newsletter' );
	check( 'the record is changed: its input is the input of the panel, and no other record\'s', w.h.dirty.isDirty( [ 'routes' ] ) === true && w.h.dirty.isDirty( [ 'routes-feature' ] ) === true );
	[ 'de_DE', 'en_US' ].forEach( l => { w.type( w.rowControl( d, 'name', l ), 'Newsletter' ); w.type( w.rowControl( d, 'title', l ), 'Abonnieren' ) } );
	w.type( w.field( d, 'description' ), 'Alle drei Monate.' );
	fire( w.button( d, SAVE ), 'click' );
	check( 'a complete record is sent as routes/savetexts for the Element-URI, and only that', w.requests.length === asked + 1 && w.requests.at(-1).action === 'routes/savetexts' && w.requests.at(-1).payload.uri === NEWS && Object.keys( w.requests.at(-1).payload ).sort().join() === 'text,uri'
		&& w.requests.at(-1).payload.text.de_DE.description === 'Alle drei Monate.' && w.requests.at(-1).payload.text.en_US.title === 'Abonnieren' );
	const saved = FEATURES.map( entry => JSON.parse( JSON.stringify( entry ) ) );
	saved[1].text = { de_DE : { name : 'Newsletter', title : 'Abonnieren', description : 'Alle drei Monate.' }, en_US : { name : 'Newsletter', title : 'Abonnieren', description : '' } };
	w.answer( 200, { runtime : saved } );
	check( 'the texts of a feature route are written like a page: the other panels are told, with the address the feature routes it at', w.touches.length === 1 && w.touches[0][0] === 'routes' && w.touches[0][1] === '/.newsletter' );
	check( '...a toast says so, and the person stays in the record, at its address, with a line that says nothing', w.toasts().at(-1) === text('/_admin/common/msg/saved') && w.dialog() === d && w.h.hash() === '#routes/routes/@feature/%2Fnewsletter' && w.lineOf( d ).textContent === '' );
	check( '...the list under the directory names the page by what was written now, and the record, drawn from it, is not changed', byClass( w.features()[1], 'nino-admin-list-copy' )[0].children[0].textContent === 'Newsletter' && w.h.dirty.isDirty( [ 'routes' ] ) === false );

	const sent = w.requests.length;
	fire( w.button( d, SAVE ), 'click' );
	check( 'a record saved again with nothing changed sends nothing', w.requests.length === sent );

	// The step through the rows of the list
	fire( tag( d, 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/routes/record/prev') ), 'click' );
	check( 'the record steps through the feature rows - the previous one - and writes the address over, with its own prefix', w.titleOf( d ) === 'Beitrag' && w.h.hash() === '#routes/routes/@feature/%2Fblog%2Fpost' );
	check( '...saying its place among them', byClass( d, 'nino-admin-record-count' )[0].textContent === '1 of 2' );

	// A page routed by a pattern is no address a window can go to
	[ 'de_DE', 'en_US' ].forEach( l => { w.type( w.rowControl( d, 'name', l ), 'B' ); w.type( w.rowControl( d, 'title', l ), 'T' ) } );
	w.touches.length = 0;
	fire( w.button( d, SAVE ), 'click' );
	w.answer( 200, { runtime : saved } );
	check( 'a feature page routed by a pattern is saved and names no page: /blog/* is no address', w.touches.length === 1 && w.touches[0][0] === 'routes' && !w.touches[0][1] );

	w.close( d );
	check( 'the cross closes it and puts the focus on the row of the page in the list', w.dialog() === null && w.focused() === w.featureLink('/blog/post') );
}

{
	// A page of a module that has a route per language is one row, shown with every path it is reached at
	const w = world();
	w.open( listing( [ '/' ], { runtime : [ { uri : '/legal/imprint', httpUri : '/impressum', httpUris : [ '/impressum', '/imprint' ], body : '[template /templates/page-legal-imprint]',
		text : { de_DE : { name : 'Impressum', title : 'Impressum', description : '' }, en_US : { name : 'Imprint', title : 'Imprint', description : '' } } },
		{ uri : '/.x', httpUri : '/.x', body : '', text : { de_DE : { name : '', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } } ] } ) );
	check( 'a page with a route per language is one row, named by what was written and shown with all its paths', w.features().length === 2 && byClass( w.features()[0], 'nino-admin-list-copy' )[0].children[0].textContent === 'Impressum' && byClass( w.features()[0], 'nino-admin-list-copy' )[0].children[1].textContent === '/impressum, /imprint' );
	fire( w.featureLink('/legal/imprint'), 'click', { currentTarget : w.featureLink('/legal/imprint'), preventDefault() {} } );
	check( '...and its record shows them read only, in one line', byClass( w.dialog(), 'nino-admin-readline-value' )[0].textContent === '/impressum, /imprint' );
	w.close( w.dialog() );
	check( 'a page without a list of paths - an older server\'s answer - shows its one path', byClass( w.features()[1], 'nino-admin-list-copy' )[0].children[1].textContent === '/.x' );
}

{
	// Reload at the address of a page a feature routes
	const w = world( featureHash( NEWS ) );
	w.panel.showCurrent();
	w.answer( 200, withFeatures() );
	check( 'a fresh load on the address of a page a feature routes opens its record over the directory, with no step of the history', w.dialog() !== null && w.titleOf( w.dialog() ) === '/.newsletter' && w.h.hash() === '#routes/routes/@feature/%2Fnewsletter' && w.ids().length === 2 );
	w.close( w.dialog() );
	check( 'closing it writes the address of the directory over it', w.dialog() === null && w.h.hash() === '#routes' );

	const k = world( codec.href( 'routes', [ '@feature', NEWS ] ) );
	k.panel.showCurrent();
	k.answer( 200, withFeatures() );
	check( 'the key @feature in front of a route is no page of a feature: the directory, whatever the key is', ( ( v ) => { v.panel.showCurrent(); v.answer( 200, withFeatures() ); return v.dialog() === null && v.h.hash() === '#routes' } )( world( '#routes/routes/%40feature/%2Fnewsletter' ) ) );

	const only = world( codec.href( 'routes', [ codec.word('feature') ] ) );
	only.panel.showCurrent();
	only.answer( 200, withFeatures() );
	check( 'the word @feature alone names no page: it is the directory, and the address says so', only.dialog() === null && only.h.hash() === '#routes' );
}

// --- two records over one screen: every move from one to the other, and to the list and the form of a new route -------------------------

{
	const setup = () => { const w = world(); w.open( withFeatures( { defaultTemplate : 'page-blank' } ) ); return w };
	const onlyOne = w => w.h.dialogs.filter( d => d.open ).length === 1;

	// page -> feature, by a typed address
	let w = setup();
	let p = w.openRow('/contact');
	let steps = w.h.went.length;
	w.typed( featureHash( NEWS ) );
	check( 'a typed address of a page of a feature closes the record of a page and opens the one of that page: one dialog, its address the one that was typed', p.open === false && onlyOne( w ) && w.titleOf( w.dialog() ) === '/.newsletter' && w.h.hash() === '#routes/routes/@feature/%2Fnewsletter' );
	check( '...the history was not stepped: the entry the page record made stays, the typed one is the new one', w.h.went.length === steps );

	// feature -> page, by a typed address
	w.typed( pageHash('/contact') );
	check( 'a typed address of a page closes the record of a page of a feature and opens the record of that page', onlyOne( w ) && w.titleOf( w.dialog() ) === 'Kontakt' && w.h.hash() === '#routes/routes/%2Fcontact' && w.h.went.length === steps );

	// either -> list
	w.typed( '#routes' );
	check( 'a typed address of the directory closes the record of a page', w.dialog() === null && w.h.hash() === '#routes' );
	w.typed( featureHash( NEWS ) );
	w.typed( '#routes' );
	check( '...and the record of a page of a feature, with the address the directory has - not the word of the feature', w.dialog() === null && w.h.hash() === '#routes' );

	// either -> @new
	w.typed( pageHash('/contact') );
	w.typed( codec.href( 'routes', [ codec.word('new') ] ) );
	check( 'a typed address of the form of a new route turns the record of a page into that form: one dialog, the title of the form', onlyOne( w ) && w.titleOf( w.dialog() ) === text('/_admin/routes/label/new') && w.h.hash() === '#routes/routes/@new' );
	w.typed( featureHash( NEWS ) );
	check( '...and from that form to a page of a feature: the form is closed, the record of the page opens', onlyOne( w ) && w.titleOf( w.dialog() ) === '/.newsletter' && w.h.hash() === '#routes/routes/@feature/%2Fnewsletter' );
	w.typed( codec.href( 'routes', [ codec.word('new') ] ) );
	check( 'a typed address of the form of a new route closes the record of a page of a feature and opens the form', onlyOne( w ) && w.titleOf( w.dialog() ) === text('/_admin/routes/label/new') && w.h.hash() === '#routes/routes/@new' );
	w.typed( '#routes' );
	check( 'and the form is closed by the address of the directory', w.dialog() === null && w.h.hash() === '#routes' );
}

{
	// Back and Forward between the two records: the entry that the browser steps to may be the other record's own, which closing the one that is open would take off
	const w = world();
	w.open( withFeatures() );

	// [ list, page (own) ] then a typed address of a page of a feature: Back must show the page again and Forward the page of the feature
	w.openRow('/contact');
	w.typed( featureHash( NEWS ) );
	const stepsBefore = w.h.went.length;
	w.back();
	check( 'Back from a typed page of a feature to the page that was opened by a person: the record of the feature closes, the record of the page opens - and the history is not stepped any further', w.dialog() !== null && w.titleOf( w.dialog() ) === 'Kontakt' && w.h.hash() === '#routes/routes/%2Fcontact' && w.h.went.length === stepsBefore && w.h.dialogs.filter( d => d.open ).length === 1 );
	w.forward();
	check( 'Forward shows the page of the feature again', w.dialog() !== null && w.titleOf( w.dialog() ) === '/.newsletter' && w.h.hash() === '#routes/routes/@feature/%2Fnewsletter' && w.h.dialogs.filter( d => d.open ).length === 1 );

	// The other way: a page of a feature opened by a person, then a typed page
	const v = world();
	v.open( withFeatures() );
	fire( v.featureLink( NEWS ), 'click', { currentTarget : v.featureLink( NEWS ), preventDefault() {} } );
	v.typed( pageHash('/contact') );
	const before = v.h.went.length;
	v.back();
	check( 'Back from a typed page to the page of a feature a person opened: the record of the page closes, the one of the feature opens, and the history is not stepped further back', v.dialog() !== null && v.titleOf( v.dialog() ) === '/.newsletter' && v.h.hash() === '#routes/routes/@feature/%2Fnewsletter' && v.h.went.length === before && v.h.dialogs.filter( d => d.open ).length === 1 );
	v.back();
	check( '...and Back once more closes that record: the directory', v.dialog() === null && v.h.hash() === '#routes' );
	v.forward();
	v.forward();
	check( '...Forward twice is the page again, and the record opens', v.dialog() !== null && v.titleOf( v.dialog() ) === 'Kontakt' && v.h.dialogs.filter( d => d.open ).length === 1 );
}

{
	// Back from either record to the list closes it; the directory is as it was
	const w = world();
	w.open( withFeatures() );
	w.type( w.search(), 'kont' );
	fire( w.featureLink( NEWS ), 'click', { currentTarget : w.featureLink( NEWS ), preventDefault() {} } );
	w.back();
	check( 'Back from the record of a page of a feature closes it, and the directory is as it was: the search stands', w.dialog() === null && w.h.hash() === '#routes' && w.search().value === 'kont' && w.ids().join() === '/contact' );
	fire( w.edit('/contact'), 'click', { currentTarget : w.edit('/contact') } );
	w.back();
	check( '...and from the record of a page the same', w.dialog() === null && w.h.hash() === '#routes' && w.search().value === 'kont' );
}

{
	// Unsaved input of either record is asked about before the other one takes the screen
	const w = world();
	w.open( withFeatures() );
	let d = w.openRow('/contact');
	w.type( w.rowControl( d, 'name', 'de_DE' ), 'Geändert' );
	w.typed( featureHash( NEWS ) );
	check( 'a typed address of a page of a feature, with input in the record of a page, asks Save, Discard or Cancel - and nothing is closed or opened before the answer', w.asking() !== null && d.open === true && w.h.dialogs.filter( x => x.open ).length === 2 );
	w.cancel();
	check( '...Cancel keeps the record with what was typed, and the address is the one of the record again', w.dialog() === d && w.rowControl( d, 'name', 'de_DE' ).value === 'Geändert' && w.h.hash() === '#routes/routes/%2Fcontact' );

	w.typed( featureHash( NEWS ) );
	fire( tag( w.asking(), 'button' ).find( b => b.textContent === text('/_admin/common/label/discard') ), 'click' );
	check( '...Discard lets the record of the page go and opens the one of the feature, whose input is nothing yet', d.open === false && w.dialog() !== null && w.titleOf( w.dialog() ) === '/.newsletter' && w.h.dirty.isDirty( [ 'routes' ] ) === false && w.h.hash() === '#routes/routes/@feature/%2Fnewsletter' );

	// The other way: input in the record of a page of a feature, a typed address of the list
	d = w.dialog();
	w.type( w.rowControl( d, 'name', 'de_DE' ), 'Neu' );
	w.typed( '#routes' );
	check( 'input in the record of a page of a feature is asked about when the address leads to the directory', w.asking() !== null && d.open === true );
	w.cancel();
	check( '...Cancel keeps it, at its address', w.dialog() === d && w.h.hash() === '#routes/routes/@feature/%2Fnewsletter' && w.rowControl( d, 'name', 'de_DE' ).value === 'Neu' );
	w.typed( pageHash('/contact') );
	fire( tag( w.asking(), 'button' ).find( b => b.textContent === text('/_admin/common/label/discard') ), 'click' );
	check( '...and Discard lets it go to the page that was asked for', d.open === false && w.dialog() !== null && w.titleOf( w.dialog() ) === 'Kontakt' && w.h.hash() === '#routes/routes/%2Fcontact' && w.h.dialogs.filter( x => x.open ).length === 1 );
}

{
	// The shell asks by the one name of the panel: the entry answers for whichever record is open, and saves it - what Ctrl+S runs
	const w = world();
	w.open( withFeatures() );
	fire( w.featureLink( NEWS ), 'click', { currentTarget : w.featureLink( NEWS ), preventDefault() {} } );
	const d = w.dialog();
	[ 'de_DE', 'en_US' ].forEach( l => { w.type( w.rowControl( d, 'name', l ), 'Newsletter' ); w.type( w.rowControl( d, 'title', l ), 'Abonnieren' ) } );
	const outcomes = [];
	w.h.dirty.entry('routes').save( ok => outcomes.push( ok ) );
	check( 'the save of the entry of the panel - what Ctrl+S runs - saves the record of a page of a feature that is open', w.requests.at(-1).action === 'routes/savetexts' && w.requests.at(-1).payload.uri === NEWS );
	w.answer( 200, { runtime : FEATURES.map( entry => Object.assign( {}, entry, { text : entry.uri === NEWS ? { de_DE : { name : 'Newsletter', title : 'Abonnieren', description : '' }, en_US : { name : 'Newsletter', title : 'Abonnieren', description : '' } } : entry.text } ) ) } );
	check( '...and says true once it is written; the panel holds no input after', outcomes.join() === 'true' && w.h.dirty.isDirty( [ 'routes' ] ) === false );

	w.type( w.rowControl( d, 'name', 'de_DE' ), 'Wieder' );
	check( 'unsaved input of the record of a page of a feature is the input of the panel, which the shell asks about when the panel is left', w.h.dirty.isDirty( [ 'routes' ] ) === true );
	let discarded = false;
	w.h.dirty.guard( [ 'routes' ], () => { discarded = true } );
	fire( tag( w.asking(), 'button' ).find( b => b.textContent === text('/_admin/common/label/discard') ), 'click' );
	check( '...and a Discard of that question lets the record go', discarded === true && d.open === false );
}

// --- foreign text in the record of a page of a feature ----------------------------------------------------------------------------------

{
	const HOSTILE = '<img src=x onerror=alert(1)> "quoted" [[/key]]';
	const w = world();
	const feature = withFeatures();
	feature.runtime[0].text.de_DE.name = HOSTILE;
	feature.runtime[0].httpUri = HOSTILE;
	feature.runtime[0].httpUris = [ HOSTILE ];
	w.open( feature );
	check( 'the name and the paths of a page of a feature are text in the list', byClass( w.features()[0], 'nino-admin-list-copy' )[0].children[0].textContent === HOSTILE && findAll( w.mount, el => el.tagName === 'IMG' ).length === 0 );
	fire( w.featureLink('/blog/post'), 'click', { currentTarget : w.featureLink('/blog/post'), preventDefault() {} } );
	check( '...and in the head of its record, its line of paths and its fields', w.titleOf( w.dialog() ) === HOSTILE && w.subtitleOf( w.dialog() ) === HOSTILE && byClass( w.dialog(), 'nino-admin-readline-value' )[0].textContent === HOSTILE && findAll( w.dialog(), el => el.tagName === 'IMG' ).length === 0 );
}

// --- the addresses: every one the panel wrote or drew is read back by the router --------------------------------------------------------

check( 'every address the panel wrote or drew - the directory, a page, a new one, a page a feature routes - is read back by the router as the same screen and the same parts', codec.written.length > 20 && codec.mismatches.length === 0
	&& codec.written.some( entry => typeof entry.parts[0] === 'object' && String( entry.parts[0] ) === '@new' ) && codec.written.some( entry => typeof entry.parts[0] === 'object' && String( entry.parts[0] ) === '@feature' && entry.parts.length === 2 ) && codec.written.some( entry => entry.parts[0] === '/about' ) );

// --- every key the script asks for is a fill in both languages ------------------------------------------------------------------------

const asked = new Set();

for( const found of source('_admin/Nino/Modules/Routes/assets/admin.js').matchAll( /(?:getText|word)\(\s*'(\/_admin\/[a-z0-9\/_-]+)'\s*\)/g ) )
	asked.add( found[1] );

const lacking = Array.from( asked ).filter( key => ( moduleEn[key] ?? workbench[key] ) === undefined || ( moduleDe[key] ?? workbenchDe[key] ) === undefined );
check( 'the script asks for '+ asked.size+ ' keys, and each has words in English and in German'+ ( lacking.length === 0 ? '' : ' - not '+ lacking.join(', ') ), asked.size > 60 && lacking.length === 0 );

// The fills the server's refusals, the dashboard tile, the rail and the manual read are read by those, not by this script
const readElsewhere = key => /^\/_admin\/(error\/|dashboard\/|nav\/|routes\/manual\/)/.test( key );
const unused = Object.keys( moduleEn ).filter( key => readElsewhere( key ) === false && asked.has( key ) === false );
check( 'no fill of the module is left that nothing reads'+ ( unused.length === 0 ? '' : ' - '+ unused.join(', ') ), unused.length === 0 );
check( 'the two languages have the same fills, none of them empty', Object.keys( moduleEn ).sort().join() === Object.keys( moduleDe ).sort().join() && Object.keys( moduleEn ).every( key => moduleEn[key].trim() !== '' && moduleDe[key].trim() !== '' ) );
check( 'the manual says what the panel is now: the lines are the same handles in both languages, and the one that names the arrows says that they lock', moduleEn['/_admin/routes/manual/open'].includes('locked') && moduleDe['/_admin/routes/manual/open'].includes('gesperrt') );

done();

})();
