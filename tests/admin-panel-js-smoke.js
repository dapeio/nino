/**
 *	Nino										A compact filesystembased php framework
 *	admin-panel-js-smoke.js	Nino.adminUi.panel() and Nino.adminUi.levels(), the
 *													lifecycle every panel script wrote for itself: when the
 *													panel asks for its screen, what it does with the
 *													answer and with a failure, what a second showing
 *													does, and the level it shows - and showLevel() and
 *													backTo(), which move the focus into a level and back
 *													to the control that opened it, and tabBadge(), the
 *													word on the button of a tab. Nino.admin.js is loaded
 *													the way the panel suites load it, with the shell's
 *													dirty registry and router as stand-ins - and in a
 *													page that has no Nino.admin at all, which is what the
 *													setup wizard is.
 *
 *	Usage: node tests/admin-panel-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, documentOf, element, findAll } = require( './harness.js' );


// --- the page --------------------------------------------------------------

/**
 *	Nino.admin.js in a page of its own: the requests it sends are kept for the
 *	test to answer, the ready callbacks that were bound to run by hand
 *
 *	@param		{Object}		[shell]			What the page has of Nino.admin: { dirty, router }; none at all when it is left out
 *
 *	@return		{Object}
 */
function world( shell ) {

	const page = element('div');
	const mount = element('div');
	mount.id = 'catalog-list';
	page.appendChild( mount );

	const requests = [];
	const ready = [];

	const Nino = {
		events : { bindCallback : function( name, callback ) { ready.push( { name : name, callback : callback } ) } },
		http : { sendRequest : function( uri, method, callback, data ) {
			requests.push( { uri : uri, action : data.action, payload : JSON.parse( data.data ), callback : callback } );
		} },
		content : { getText : function( key ) { return { '/_admin/catalog/error/load' : 'The list could not be loaded.', '/_admin/catalog/error/other' : 'Something else failed.', '/_admin/common/label/close' : 'Close' }[key] || '' } },
	};
	if( shell !== undefined )
		Nino.admin = shell;

	// The element that has the focus: documentOf() has none, a page does
	const doc = documentOf( page );
	Object.defineProperty( doc, 'activeElement', { get : function() { return findAll( page, function( el ) { return el.focused === true } )[0] ?? null } } );

	const sandbox = { console : console, document : doc, Nino : Nino };
	sandbox.window = { Nino : Nino };
	load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );

	return {
		Nino : Nino,
		page : page,
		mount : mount,
		requests : requests,
		ready : ready,
		doc : doc,
		window : sandbox.window,
	};
}

/**
 *	Answer the last request that is still out, the way Nino.http.sendRequest()
 *	calls back
 *
 *	@param		{Object}		w					A world()
 *	@param		{number}		status
 *	@param		{*}				body
 *
 *	@return		{boolean}					Whether there was one
 */
function reply( w, status, body ) {
	const open = w.requests.filter( function( request ) { return request.answered !== true } );
	if( open.length === 0 )
		return false;
	open[open.length - 1].answered = true;
	open[open.length - 1].callback( { status : status, responseJSON : body } );
	return true;
}

/** A panel over the page, whose render keeps what it was handed */
function catalog( w, extra ) {
	const drawn = [];
	const panel = w.Nino.adminUi.panel( Object.assign( {
		uri : 'catalog',
		mount : 'catalog-list',
		render : function( data, own ) { drawn.push( { data : data, panel : own } ) },
	}, extra || {} ) );
	return { panel : panel, drawn : drawn };
}


// --- the lifecycle ---------------------------------------------------------

console.log( 'Nino.adminUi.panel - when a panel asks, and what it does with the answer\n' );

{
	const w = world( {} );
	const c = catalog( w );

	check( 'a panel started lazily asks for nothing until it is shown', w.requests.length === 0 && c.panel.ready === false && c.panel.loading === false && c.panel.data === null && w.ready.length === 0 );
	check( '...it answers its uri, and the element it draws into', c.panel.uri === 'catalog' && c.panel.mount() === w.mount );

	c.panel.showCurrent();
	check( 'showing it asks for the list of its uri with an empty payload', w.requests.length === 1 && w.requests[0].action === 'catalog/list' && JSON.stringify( w.requests[0].payload ) === '{}' && c.panel.loading === true );

	c.panel.showCurrent();
	c.panel.init();
	check( '...and a request that is out is asked for once: showing it again, or init(), sends nothing', w.requests.length === 1 );

	const body = { items : [ 'a', 'b' ] };
	w.requests[0].callback( { status : 200, responseJSON : body } );
	check( 'an answer of 200 is the panel\'s data, and the panel is ready', c.panel.data === body && c.panel.ready === true && c.panel.loading === false );
	check( '...and is drawn from its body, the panel handed along', c.drawn.length === 1 && c.drawn[0].data === body && c.drawn[0].panel === c.panel );
}

{
	const w = world( {} );
	let thenCalls = 0;
	const calls = [];
	const c = catalog( w, { render : function() { calls.push( 'render' ) } } );

	c.panel.init( function() { calls.push( 'then' ); thenCalls++ } );
	reply( w, 200, { ok : true } );
	check( 'what init() is given to do afterwards runs after the screen is drawn, once', calls.join() === 'render,then' && thenCalls === 1 );
}

{
	const w = world( {} );
	const asked = [];
	const c = catalog( w, { action : 'summary', payload : function() { asked.push( 'payload' ); return { month : '2024-05' } } } );
	c.panel.init();
	check( 'the first endpoint and its payload are the spec\'s, the payload read when the request goes out', w.requests[0].action === 'catalog/summary' && w.requests[0].payload.month === '2024-05' && asked.length === 1 );

	const plain = world( {} );
	catalog( plain, { payload : { kind : 'books' } } ).panel.init();
	check( '...a payload that is an object is sent as it is', plain.requests[0].payload.kind === 'books' );
}

{
	const w = world( {} );
	const c = catalog( w );
	c.panel.call( 'save', { id : 'a1' }, function() {} );
	check( 'call() posts an action of the panel: its uri in front, the payload along', w.requests.length === 1 && w.requests[0].action === 'catalog/save' && w.requests[0].payload.id === 'a1' );

	const missing = world( {} );
	missing.page.removeChild( missing.mount );
	const orphan = catalog( missing );
	orphan.panel.init();
	orphan.panel.showCurrent();
	check( 'a page without the panel\'s mount is never asked for anything', missing.requests.length === 0 && orphan.panel.mount() === null && orphan.panel.loading === false );
}


console.log( '\nNino.adminUi.panel - what a second showing does\n' );

{
	const w = world( {} );
	const c = catalog( w );
	for( let i = 0; i < 3; i++ ) {
		c.panel.showCurrent();
		reply( w, 200, { round : i } );
	}
	check( 'once, the default: three showings ask once and draw once', w.requests.length === 1 && c.drawn.length === 1 && c.panel.data.round === 0 );
}

{
	const w = world( {} );
	const c = catalog( w, { show : 'always' } );
	for( let i = 0; i < 3; i++ ) {
		c.panel.showCurrent();
		reply( w, 200, { round : i } );
	}
	check( 'always: every showing asks again, and draws what it is told', w.requests.length === 3 && c.drawn.length === 3 && c.panel.data.round === 2 );
}

{
	let unsaved = false;
	const w = world( { dirty : { register : function() {}, isDirty : function( names ) { return names.join() === 'catalog' && unsaved } } } );
	const c = catalog( w, { show : 'keep' } );
	c.panel.showCurrent();
	reply( w, 200, { round : 1 } );
	check( 'keep: the first showing asks like any other', w.requests.length === 1 && c.drawn.length === 1 );

	c.panel.showCurrent();
	check( '...the next one draws the last answer again without asking, where the registry says the panel holds nothing unsaved', w.requests.length === 1 && c.drawn.length === 2 && c.drawn[1].data === c.drawn[0].data );

	unsaved = true;
	c.panel.showCurrent();
	check( '...and does nothing where it does: the screen is the person\'s, not the answer\'s', w.requests.length === 1 && c.drawn.length === 2 );
}

{
	const w = world( {} );
	const c = catalog( w, { show : 'keep' } );
	c.panel.showCurrent();
	reply( w, 200, { round : 1 } );
	c.panel.showCurrent();
	check( 'keep on a page that has no shell: a panel nothing can tell is dirty is clean', w.requests.length === 1 && c.drawn.length === 2 );
}

{
	const w = world( {} );
	const c = catalog( w );
	c.panel.showCurrent();
	reply( w, 200, { round : 1 } );
	c.panel.reload();
	check( 'reload() asks again, whatever the policy', w.requests.length === 2 && w.requests[1].action === 'catalog/list' );
	reply( w, 200, { round : 2 } );
	check( '...and draws the new answer', c.drawn.length === 2 && c.panel.data.round === 2 );
}

{
	const w = world( {} );
	let again = true;
	const draws = [];
	const c = catalog( w, { render : function( data, own ) { draws.push( data.round ); if( again === true ) { again = false; own.reload() } } } );
	c.panel.init();
	reply( w, 200, { round : 1 } );
	check( 'the request is over before the screen is drawn, so that drawing it may ask again', w.requests.length === 2 && c.panel.loading === true );
	reply( w, 200, { round : 2 } );
	check( '...and the second answer is drawn too', draws.join() === '1,2' && c.panel.loading === false );
}


console.log( '\nNino.adminUi.panel - starting at the ready event\n' );

{
	const w = world( {} );
	const c = catalog( w, { boot : 'ready' } );

	check( 'a panel started at the ready event binds to it and asks for nothing before', w.ready.length === 1 && w.ready[0].name === 'ready' && w.requests.length === 0 );

	c.panel.showCurrent();
	w.ready[0].callback( {} );
	check( 'the shell showing it first and the ready event after ask once', w.requests.length === 1 );

	reply( w, 200, { round : 1 } );
	c.panel.showCurrent();
	check( '...and the answer is drawn once, with the panel shown again doing nothing', c.drawn.length === 1 && w.requests.length === 1 );

	const alone = world( {} );
	catalog( alone, { boot : 'ready' } );
	alone.ready[0].callback( {} );
	check( 'the ready event alone starts it too', alone.requests.length === 1 );
}


console.log( '\nNino.adminUi.panel - what another panel wrote since the screen was asked for\n' );

{
	// The shell's counters as stand-ins: stamp() reads what the test has set
	const counters = { templates : 0, texts : 0 };
	let unsaved = false;
	const shell = function() {
		return { stamp : function( topic ) { return counters[topic] ?? 0 }, dirty : { register : function() {}, isDirty : function() { return unsaved } } };
	};

	const w = world( shell() );
	const c = catalog( w, { show : 'keep', stamps : [ 'templates' ] } );

	c.panel.showCurrent();
	reply( w, 200, { round : 1 } );
	c.panel.showCurrent();
	check( 'stamps with keep: a showing with nothing written since draws the last answer again, as keep does', w.requests.length === 1 && c.drawn.length === 2 );

	counters.texts++;
	c.panel.showCurrent();
	check( '...a kind the spec does not name moving changes nothing', w.requests.length === 1 && c.drawn.length === 3 );

	counters.templates++;
	unsaved = true;
	c.panel.showCurrent();
	check( '...a kind it names that moved asks again instead - not while the dirty registry says the panel holds input nobody has saved', w.requests.length === 1 && c.drawn.length === 3 );

	unsaved = false;
	c.panel.showCurrent();
	check( '...which is asked at the next showing that finds none: the list of the panel again, and nothing drawn before the answer', w.requests.length === 2 && w.requests[1].action === 'catalog/list' && c.drawn.length === 3 && c.panel.loading === true );

	reply( w, 200, { round : 2 } );
	check( '...the new answer is the panel\'s data and is drawn', c.panel.data.round === 2 && c.drawn.length === 4 && c.panel.ready === true );

	c.panel.showCurrent();
	check( '...and with nothing written after it, the showing that follows draws it and asks nothing', w.requests.length === 2 && c.drawn.length === 5 );
}

{
	// The stamp is the one from before the request went out: a write that lands while it is out is not in what it answers
	const counters = { templates : 0 };
	const w = world( { stamp : function( topic ) { return counters[topic] ?? 0 } } );
	const c = catalog( w, { show : 'keep', stamps : [ 'templates' ] } );

	c.panel.showCurrent();
	counters.templates++;
	reply( w, 200, { round : 1 } );
	c.panel.showCurrent();
	check( 'a write that landed while the request was out is not in its answer: the next showing asks again', w.requests.length === 2 && c.drawn.length === 1 );

	// A request that is out is asked for once, though the stamp moved
	c.panel.showCurrent();
	check( '...once, however often the panel is shown while that request is out', w.requests.length === 2 );
}

{
	// Any of the kinds it names
	const counters = { templates : 0, texts : 0, slots : 0 };
	const w = world( { stamp : function( topic ) { return counters[topic] ?? 0 } } );
	const c = catalog( w, { show : 'keep', stamps : [ 'templates', 'texts' ] } );

	c.panel.showCurrent();
	reply( w, 200, { round : 1 } );
	counters.slots++;
	c.panel.showCurrent();
	counters.texts++;
	c.panel.showCurrent();
	check( 'a panel that names several kinds asks again when any one of them moved, and for no other', w.requests.length === 2 );
	reply( w, 200, { round : 2 } );
	c.panel.showCurrent();
	check( '...and then asks nothing until one moves again', w.requests.length === 2 );
}

{
	// once and always keep their own rule for a second showing
	const counters = { templates : 0 };
	const shell = { stamp : function( topic ) { return counters[topic] ?? 0 } };

	const once = world( shell );
	const o = catalog( once, { stamps : [ 'templates' ] } );
	o.panel.showCurrent();
	reply( once, 200, { round : 1 } );
	counters.templates++;
	o.panel.showCurrent();
	check( 'once: the stamps change nothing - a second showing does nothing, as it says', once.requests.length === 1 && o.drawn.length === 1 );

	const always = world( shell );
	const a = catalog( always, { show : 'always', stamps : [ 'templates' ] } );
	for( let i = 0; i < 2; i++ ) {
		a.panel.showCurrent();
		reply( always, 200, { round : i } );
	}
	check( 'always: every showing asks, with or without a stamp that moved', always.requests.length === 2 );
}

{
	// A load that failed is not remembered: the panel is not loaded, and a showing asks again whatever the counters say
	const w = world( { stamp : function() { return 0 } } );
	const c = catalog( w, { show : 'keep', stamps : [ 'templates' ] } );
	c.panel.showCurrent();
	reply( w, 500, null );
	c.panel.showCurrent();
	check( 'a load that failed leaves the panel not loaded, and showing it asks again', c.panel.ready === false && w.requests.length === 2 );
}

{
	// A shell without counters, and a page without a shell: nothing ever moves
	const bare = world( {} );
	const b = catalog( bare, { show : 'keep', stamps : [ 'templates' ] } );
	b.panel.showCurrent();
	reply( bare, 200, { round : 1 } );
	b.panel.showCurrent();

	const none = world();
	const n = catalog( none, { show : 'keep', stamps : [ 'templates' ] } );
	let threw = null;
	try {
		n.panel.showCurrent();
		reply( none, 200, { round : 1 } );
		n.panel.showCurrent();
	}
	catch( error ) { threw = error }
	check( 'where the shell has no stamp(), or there is no shell, nothing moves: the last answer is drawn again, asked for once', bare.requests.length === 1 && none.requests.length === 1 && threw === null && b.drawn.length === 2 && n.drawn.length === 2 );
}


console.log( '\nNino.adminUi.panel - a failed load\n' );

{
	const w = world( {} );
	const errors = [];
	const c = catalog( w, { onError : function( status, response, own ) { errors.push( { status : status, response : response, panel : own, held : w.mount.children.length } ) } } );

	c.panel.init();
	reply( w, 500, { error : 'the disk is full' } );
	const line = w.mount.children[0];
	check( 'a failure is said in the mount, in the design system\'s error line', w.mount.children.length === 1 && line.tagName === 'P' && line.className === 'nino-admin-error' );
	check( '...it says what the server said, the number in front of it', line.textContent === '(500) the disk is full' );
	check( '...the panel is not ready, and the failure is handed to its onError after it was shown', c.panel.ready === false && c.panel.loading === false && errors.length === 1 && errors[0].status === 500 && errors[0].response.error === 'the disk is full' && errors[0].panel === c.panel && errors[0].held === 1 );
	check( '...nothing was drawn', c.drawn.length === 0 );

	c.panel.showCurrent();
	reply( w, 200, { round : 1 } );
	check( 'a panel whose load failed is not loaded: showing it asks again, and a good answer draws', w.requests.length === 2 && c.panel.ready === true && c.drawn.length === 1 );

	c.panel.reload();
	reply( w, 500, null );
	c.panel.showCurrent();
	check( '...a load that fails after one that worked makes it not loaded again, so showing it asks once more', c.panel.ready === false && w.requests.length === 4 );
}

{
	const w = world( {} );
	catalog( w ).panel.init();
	reply( w, 500, null );
	check( 'a failure with no word from the server says the panel\'s own sentence, from the key its uri makes', w.mount.children[0].textContent === '(500) The list could not be loaded.' );

	const own = world( {} );
	catalog( own, { error : '/_admin/catalog/error/other' } ).panel.init();
	reply( own, 404, null );
	check( '...or the key the spec names', own.mount.children[0].textContent === '(404) Something else failed.' );

	const later = world( {} );
	const c = catalog( later );
	const p = c.panel.fail( later.mount, 409, { error : 'taken' }, '/_admin/catalog/error/other' );
	check( 'fail() says a failure in any container and answers the line it wrote', p === later.mount.children[0] && p.textContent === '(409) taken' && later.mount.children.length === 1 );
	check( '...with the panel\'s key unless it is given another one', c.panel.fail( later.mount, 500, null ).textContent === '(500) The list could not be loaded.' && c.panel.fail( later.mount, 500, null, '/_admin/catalog/error/other' ).textContent === '(500) Something else failed.' );
}


console.log( '\nNino.adminUi.panel - the shell\'s dirty registry\n' );

{
	const registered = [];
	const w = world( { dirty : { register : function( name, entry ) { registered.push( { name : name, entry : entry } ) }, isDirty : function() { return false } } } );
	const entry = { isDirty : function() { return false }, save : function( finished ) { finished( true ) }, discard : function() {} };
	const c = catalog( w, { dirty : entry } );

	check( 'a panel registers its input with the shell at the first init(), not before', registered.length === 0 );
	c.panel.init();
	reply( w, 200, {} );
	c.panel.reload();
	reply( w, 500, null );
	c.panel.reload();
	check( '...once, under its uri, however often it asks', registered.length === 1 && registered[0].name === 'catalog' && registered[0].entry === entry );

	const plain = world( { dirty : { register : function() { registered.push( 'plain' ) }, isDirty : function() { return false } } } );
	catalog( plain ).panel.init();
	check( 'a panel that names no input to register registers nothing', registered.length === 1 );
}

{
	const w = world();
	const entry = { isDirty : function() { return true } };
	const c = catalog( w, { dirty : entry, show : 'keep' } );
	let threw = null;
	try {
		c.panel.showCurrent();
		reply( w, 200, { round : 1 } );
		c.panel.showCurrent();
		c.panel.reload();
		reply( w, 200, { round : 2 } );
	}
	catch( error ) { threw = error }
	check( 'in a page that has no Nino.admin at all - the setup wizard - a panel starts, shows and asks without a word', threw === null && c.panel.ready === true && c.drawn.length === 3 );

	const shellless = world( { dirty : null } );
	let nullThrew = null;
	try {
		const n = catalog( shellless, { dirty : entry } );
		n.panel.init();
		reply( shellless, 200, {} );
		n.panel.showCurrent();
	}
	catch( error ) { nullThrew = error }
	check( '...and one whose shell has no registry yet', nullThrew === null );
}


// --- the levels ------------------------------------------------------------

console.log( '\nNino.adminUi.levels - the level that is shown\n' );

function levelsPage( shell ) {
	const w = world( shell );
	const list = element('div'); list.id = 'catalog-list';
	const form = element('div'); form.id = 'catalog-form';
	w.page.removeChild( w.mount );
	w.page.appendChild( list );
	w.page.appendChild( form );
	return { w : w, list : list, form : form };
}

{
	const set = [];
	const p = levelsPage( { router : { set : function( prefix, parts ) { set.push( prefix+ ':'+ parts.join('/') ) } } } );
	const levels = p.w.Nino.adminUi.levels( 'catalog', [ 'list', 'form', 'raw' ] );

	levels.show( 'form' );
	check( 'levels() hides every level but the one shown, with the shell\'s class - one the page does not have is skipped', p.form.classList.contains('admin-hidden') === false && p.list.classList.contains('admin-hidden') === true );
	levels.show( 'list' );
	check( '...and shows the one that was hidden again', p.list.classList.contains('admin-hidden') === false && p.form.classList.contains('admin-hidden') === true );
	check( '...no part of the address is written without the parts', set.length === 0 );

	levels.show( 'form', [ 'a1' ] );
	levels.show( 'list', [] );
	check( 'with the parts, the router writes the level into the address: the prefix, then the parts', set.join() === 'catalog:a1,catalog:' );
}

{
	const p = levelsPage( {} );
	const levels = p.w.Nino.adminUi.levels( 'catalog', [ 'list', 'form' ] );
	let threw = null;
	try { levels.show( 'form', [ 'a1' ] ) } catch( error ) { threw = error }
	check( 'a shell without a router is left out: the level is shown, the address is not touched', threw === null && p.list.classList.contains('admin-hidden') === true );

	const bare = levelsPage();
	const without = bare.w.Nino.adminUi.levels( 'catalog', [ 'list', 'form' ] );
	let bareThrew = null;
	try { without.show( 'list', [] ) } catch( error ) { bareThrew = error }
	check( '...and so is a page that has no shell at all', bareThrew === null && bare.form.classList.contains('admin-hidden') === true );
}


// --- showLevel and backTo --------------------------------------------------

console.log( '\nNino.adminUi.showLevel - a level is shown and the focus goes into it\n' );

/**
 *	A pane of two levels as the shell draws one: the head with the name of the panel, the list with a row to open,
 *	the form with a context bar, a back link and a heading of its own
 *
 *	@param		{boolean}		[titled]		Whether the form has a heading (default true)
 *
 *	@return		{Object}
 */
function drill( titled ) {
	const w = world( {} );
	w.page.removeChild( w.mount );

	const pane = element('div');
	pane.dataset.panel = 'catalog';
	const head = element('div'); head.className = 'admin-panel-head';
	const name = element('h2'); name.className = 'admin-panel-title';
	head.appendChild( name );
	pane.appendChild( head );

	const list = element('div'); list.id = 'catalog-list';
	const row = element('button'); row.type = 'button';
	list.appendChild( row );
	const form = element('div'); form.id = 'catalog-form'; form.className = 'admin-hidden';
	const back = element('a'); back.className = 'nino-admin-back-link';
	form.appendChild( back );
	const title = element('h2');
	if( titled !== false )
		form.appendChild( title );
	const field = element('input');
	form.appendChild( field );

	pane.appendChild( list );
	pane.appendChild( form );
	w.page.appendChild( pane );

	return Object.assign( w, { pane : pane, name : name, list : list, row : row, form : form, back : back, title : title, field : field } );
}

{
	const d = drill();
	d.row.focus();
	const reached = d.Nino.adminUi.showLevel( 'catalog-form' );
	check( 'a level is shown by its id, and the others of its pane are hidden', d.form.classList.contains('admin-hidden') === false && d.list.classList.contains('admin-hidden') === true );
	check( '...the focus goes onto its first heading, which can take it without being a stop of the Tab key', reached === d.title && d.title.focused === true && d.title.attributes.tabindex === '-1' );
	check( '...and not onto the head of the pane or the next control', d.name.focused !== true && d.field.focused !== true && d.page.querySelector('[data-panel]') === d.pane );
	check( 'the head of the pane is not a level and stays', d.pane.children[0].classList.contains('admin-hidden') === false );

	d.Nino.adminUi.showLevel( d.list );
	check( 'a level is shown by the element as well', d.list.classList.contains('admin-hidden') === false && d.form.classList.contains('admin-hidden') === true );
	check( '...a list with no heading of its own takes the focus to the name of the panel', d.name.focused === true && d.name.attributes.tabindex === '-1' );

	const elsewhere = d.Nino.adminUi.showLevel( 'catalog-form', { focus : d.field } );
	check( 'an element named in the options takes the focus instead of the heading', elsewhere === d.field && d.field.focused === true && d.title.focused !== true );
	check( '...a control is not given a tabindex', d.field.attributes.tabindex === undefined );

	check( 'an id the page does not have is no level: nothing is shown, nothing throws', d.Nino.adminUi.showLevel( 'catalog-nope' ) === null && d.form.classList.contains('admin-hidden') === false );
}

{
	const d = drill( false );
	const reached = d.Nino.adminUi.showLevel( 'catalog-form' );
	check( 'a level with no heading takes the focus onto its back link', reached === d.back && d.back.focused === true );
	check( '...which is a link and so gets no tabindex', d.back.attributes.tabindex === undefined );
}

{
	const d = drill();
	d.name.focus();
	const stayed = d.Nino.adminUi.showLevel( 'catalog-form', { focus : false } );
	check( 'false keeps the focus where it is - a level shown again, not one the person opened', stayed === null && d.name.focused === true && d.title.focused !== true );
	check( '...and the level is shown all the same', d.form.classList.contains('admin-hidden') === false && d.list.classList.contains('admin-hidden') === true );

	d.Nino.adminUi.showLevel( 'catalog-list', { focus : false } );
	d.row.focus();
	d.Nino.adminUi.showLevel( 'catalog-form', { focus : false } );
	check( '...unless it stood in a level that is hidden now: nothing keeps it there, and it goes to the name of the panel', d.name.focused === true && d.row.focused !== true && d.title.focused !== true );
}

console.log( '\nNino.adminUi.backTo - back to the control that opened the level\n' );

{
	const d = drill();
	d.row.focus();
	d.Nino.adminUi.showLevel( 'catalog-form' );
	const back = d.Nino.adminUi.backTo( 'catalog-list', d.row );
	check( 'back shows the level it was asked for and hides the other', d.list.classList.contains('admin-hidden') === false && d.form.classList.contains('admin-hidden') === true );
	check( '...and the focus goes to the control that opened the level that is left', back === d.row && d.row.focused === true );

	d.Nino.adminUi.showLevel( 'catalog-form' );
	d.list.removeChild( d.row );
	d.list.appendChild( element('button') );
	const fallback = d.Nino.adminUi.backTo( 'catalog-list', d.row );
	check( 'an opener the page no longer has is not focused: the focus goes where showLevel() sends it', fallback === d.name && d.name.focused === true && d.row.focused !== true );

	const second = element('button');
	d.list.appendChild( second );
	d.Nino.adminUi.showLevel( 'catalog-form' );
	d.list.innerHTML = '';
	d.list.appendChild( element('button') );
	check( '...whether it was removed or its list was emptied and drawn again', d.Nino.adminUi.backTo( 'catalog-list', second ) === d.name && second.focused !== true );

	d.Nino.adminUi.showLevel( 'catalog-form' );
	check( 'back without an opener is the level\'s own heading as well', d.Nino.adminUi.backTo( 'catalog-list' ) === d.name && d.Nino.adminUi.backTo( 'catalog-list', null ) === d.name );
}

// --- tabBadge ---------------------------------------------------------------

console.log( '\nNino.adminUi.tabBadge - a word on the button of a tab\n' );

{
	const w = world( {} );
	const ui = w.Nino.adminUi;

	// The strip the registry renders: a button a tab, with the id the shell gives it (see Panels::$html)
	const strip = element('div');
	const tabs = {};
	[ 'roles', 'lockout' ].forEach( function( name ) {
		const button = element('button');
		button.id = 'admin-tabbutton-'+ name;
		button.className = 'nino-admin-tab';
		button.textContent = name;
		strip.appendChild( button );
		tabs[name] = button;
	} );
	w.page.appendChild( strip );

	const chips = function( button ) { return button.children.filter( function( child ) { return child.classList.contains('nino-admin-badge') } ) };

	check( 'a tab that was given nothing answers an empty word and has no chip', ui.tabBadge('roles') === '' && chips( tabs.roles ).length === 0 );

	ui.tabBadge( 'roles', '3', 'warn' );
	check( 'a word is one chip on the button of the tab: the workbench\'s pill, the word its text, the state its data-state', chips( tabs.roles ).length === 1
		&& chips( tabs.roles )[0].tagName === 'SPAN' && chips( tabs.roles )[0].textContent === '3' && chips( tabs.roles )[0].dataset.state === 'warn' && chips( tabs.lockout ).length === 0 );
	check( '...and the tab alone answers the word', ui.tabBadge('roles') === '3' );

	ui.tabBadge( 'roles', '12', 'ok' );
	check( 'a second word replaces the first: still one chip, with the new word and the new state', chips( tabs.roles ).length === 1 && chips( tabs.roles )[0].textContent === '12'
		&& chips( tabs.roles )[0].dataset.state === 'ok' && ui.tabBadge('roles') === '12' );

	ui.tabBadge( 'roles', '5', 'purple' );
	check( 'a state that is none of the four draws the chip without one, in the colour of the label', chips( tabs.roles ).length === 1 && chips( tabs.roles )[0].dataset.state === undefined );
	ui.tabBadge( 'roles', '6' );
	check( '...as does no state at all', chips( tabs.roles ).length === 1 && chips( tabs.roles )[0].textContent === '6' && chips( tabs.roles )[0].dataset.state === undefined );

	ui.tabBadge( 'roles', '' );
	check( 'an empty word takes the chip away, and the record with it', chips( tabs.roles ).length === 0 && ui.tabBadge('roles') === '' );
	ui.tabBadge( 'roles', '2', 'muted' );
	ui.tabBadge( 'roles', null );
	check( 'so does null', chips( tabs.roles ).length === 0 && ui.tabBadge('roles') === '' );
	ui.tabBadge( 'roles', '  ' );
	check( '...and a word of nothing but spaces, which would draw an empty pill', chips( tabs.roles ).length === 0 && ui.tabBadge('roles') === '' );

	ui.tabBadge( 'roles', 7 );
	check( 'a number is its digits', chips( tabs.roles )[0].textContent === '7' && ui.tabBadge('roles') === '7' );
	ui.tabBadge( 'roles', '<b>1</b>' );
	check( 'the word is text and never markup', chips( tabs.roles )[0].textContent === '<b>1</b>' && chips( tabs.roles )[0].children.length === 0 );
	ui.tabBadge( 'roles', '' );

	// What another script put on the same button is its own
	const own = element('span');
	own.className = 'nino-admin-badge design-tab-changed';
	tabs.lockout.appendChild( own );
	ui.tabBadge( 'lockout', '1' );
	check( 'a chip another script drew on the same button stays beside the one drawn here', chips( tabs.lockout ).length === 2 && chips( tabs.lockout )[0] === own );
	ui.tabBadge( 'lockout', '' );
	check( '...and is not taken away with it', chips( tabs.lockout ).length === 1 && chips( tabs.lockout )[0] === own );

	// A tab the page has no button for is recorded, and nothing is drawn
	ui.tabBadge( 'nope', '4' );
	check( 'a tab with no button in the page - the account lacks its permission - is recorded and draws nothing', ui.tabBadge('nope') === '4' && findAll( w.page, function( el ) { return el.classList.contains('nino-admin-tab-badge') } ).length === 0 );
	ui.tabBadge( 'No Slug', 'x' );
	check( 'a name that is no slug is no tab: nothing is recorded', ui.tabBadge('No Slug') === '' && ui.tabBadge( 42 ) === '' );
}

// --- manual and manualDialog -----------------------------------------------

console.log( '\nNino.adminUi.manual - sections of a handle and what it does, or running text\n' );

/** What a person reads of an element: the text of it and of everything in it, as a browser's textContent has it */
function textOf( el ) {
	return el.children.length === 0 ? el.textContent : el.children.map( textOf ).join('');
}

{
	const ui = world().Nino.adminUi;
	const tags = function( root, tag ) { return findAll( root, function( el ) { return el.tagName === tag.toUpperCase() } ) };

	const body = ui.manual( [
		{ heading : 'Shortcodes', entries : [ { handle : '[card]', text : 'Draws the card.' }, { handle : '', text : 'A line about the whole section.' } ] },
		{ heading : 'Routes', entries : [] },
	], { none : 'Nothing here.' } );

	check( 'a manual is one box with the design system\'s class and nothing else about it', body.tagName === 'DIV' && body.className === 'nino-admin-manual' );
	check( 'a section is its heading - the third level, one under a dialog\'s own - and one description list for all of its entries', tags( body, 'h3' ).map( el => el.textContent ).join('|') === 'Shortcodes|Routes'
		&& tags( body, 'h3' ).every( el => el.className === 'nino-admin-manual-heading' ) && tags( body, 'dl' ).length === 1 && tags( body, 'dl' )[0].className === 'nino-admin-manual-section'
		&& tags( body, 'dt' ).length === 2 && tags( body, 'dd' ).length === 2 );
	check( 'a handle is code and what it does stands beside it', tags( tags( body, 'dt' )[0], 'code' )[0].textContent === '[card]' && tags( body, 'dd' )[0].textContent === 'Draws the card.' );
	check( 'a line with no handle keeps its place in the column: an empty term, the line beside it', tags( body, 'dt' )[1].children.length === 0 && tags( body, 'dd' )[1].textContent === 'A line about the whole section.' );
	check( 'a section with no entries says so, quietly, and has no list', tags( body, 'p' ).length === 1 && tags( body, 'p' )[0].className === 'nino-admin-manual-none' && tags( body, 'p' )[0].textContent === 'Nothing here.' );

	const noSentence = ui.manual( [ { heading : 'Routes', entries : [] }, { heading : 'Markup', entries : [ { handle : '', text : 'One.' } ] } ] );
	check( 'where the caller has no sentence for an empty section the section is left out - its heading with it', tags( noSentence, 'h3' ).map( el => el.textContent ).join('|') === 'Markup' && tags( noSentence, 'p' ).length === 0 );
	check( 'the level of the headings is the caller\'s, between the second and the sixth', tags( ui.manual( [ { heading : 'A', entries : [ { handle : 'x', text : 'y' } ] } ], { level : 6 } ), 'h6' ).length === 1
		&& tags( ui.manual( [ { heading : 'A', entries : [ { handle : 'x', text : 'y' } ] } ], { level : 99 } ), 'h6' ).length === 1 && tags( ui.manual( [ { heading : 'A', entries : [ { handle : 'x', text : 'y' } ] } ], { level : 1 } ), 'h2' ).length === 1 );
	check( 'a section with no heading is a list alone', tags( ui.manual( [ { entries : [ { handle : 'x', text : 'y' } ] } ] ), 'h3' ).length === 0 );

	const named = ui.manual( [ { entries : [ { handle : '', text : 'What it is for.' }, { handle : 'page', text : 'What you do with a page.' } ] } ], { named : handle => 'Word of ' + handle } );
	check( 'a manual written for the people who edit names a handle in words instead of as code, and says so in the list\'s class', tags( named, 'code' ).length === 0 && tags( named, 'dt' )[1].textContent === 'Word of page'
		&& tags( named, 'dl' )[0].className === 'nino-admin-manual-section nino-admin-manual-section--terms' && tags( named, 'dt' )[0].textContent === '' );
	check( '...and a handle it names is a plain word: the entry with none is not named at all', tags( named, 'dt' )[0].children.length === 0 );

	const prose = ui.manual( "Put `[told]` where it goes.\nEvery line is typed in turn.\n\nNo more than that - <b>no</b> markup.\n\nA lone ` stays text.\n \n  \n" );
	check( 'running text is a paragraph at a blank line, a single line break being where the text wrapped its own line', tags( prose, 'p' ).length === 3
		&& textOf( tags( prose, 'p' )[0] ) === 'Put [told] where it goes. Every line is typed in turn.' );
	check( '...a `backticked` span is code, anything else is text - markup included', tags( tags( prose, 'p' )[0], 'code' ).map( el => el.textContent ).join('|') === '[told]'
		&& textOf( tags( prose, 'p' )[1] ) === 'No more than that - <b>no</b> markup.' && tags( tags( prose, 'p' )[1], 'b' ).length === 0 );
	check( '...and a backtick that is never closed is a character, not the rest of the paragraph turned into code', textOf( tags( prose, 'p' )[2] ) === 'A lone ` stays text.' && tags( tags( prose, 'p' )[2], 'code' ).length === 0 );

	check( 'nothing to show is null, whatever it is that is nothing', ui.manual( [] ) === null && ui.manual( '' ) === null && ui.manual( ' \n \n ' ) === null && ui.manual( undefined ) === null && ui.manual( null ) === null && ui.manual( 7 ) === null
		&& ui.manual( [ { heading : 'Routes', entries : [] } ] ) === null && ui.manual( [ { heading : 'Routes' } ] ) === null );
}

console.log( '\nNino.adminUi.manualDialog - a manual in the dialog of the workbench\n' );

/**
 *	A world whose page has the workbench's wrapper and whose dialogs do what a browser's do: showModal() opens one and
 *	puts the focus on the first control in it, close() closes it and says so
 *
 *	@param		{boolean}		[modal]			Whether <dialog> has showModal() at all (default true)
 *
 *	@return		{Object}
 */
function dialogs( modal ) {
	const w = world();
	const wrap = element('div');
	wrap.id = 'admin-page-wrap';
	w.page.appendChild( wrap );
	w.asked = [];
	w.window.confirm = function( message ) { w.asked.push( message ); return true };
	w.opened = [];
	w.doc.createElement = function( tag ) {
		const el = element( tag );
		if( tag === 'dialog' && modal !== false ) {
			el.open = false;
			el.showModal = function() { el.open = true; w.opened.push( el ); findAll( el, function( node ) { return node.tagName === 'BUTTON' } )[0].focus() };
			el.close = function() { el.open = false; fireClose( el ) };
		}
		return el;
	};
	return Object.assign( w, { wrap : wrap } );
}

function fireClose( el ) {
	( el.listeners.close || [] ).forEach( fn => fn() );
}

{
	const d = dialogs();
	const ui = d.Nino.adminUi;
	const help = element('button');
	d.page.appendChild( help );
	help.focus();

	const content = ui.manual( [ { entries : [ { handle : 'x', text : 'y' } ] } ] );
	check( 'opening it answers true', ui.manualDialog( 'Elements', content, help ) === true );

	const dialog = d.opened[0];
	const tags = function( root, tag ) { return findAll( root, function( el ) { return el.tagName === tag.toUpperCase() } ) };
	check( 'it is a native dialog in the workbench\'s root, opened as a modal, wider than a question', dialog.parentNode === d.wrap && dialog.open === true && dialog.className === 'nino-admin-dialog nino-admin-dialog--manual' );
	check( 'the panel\'s name is its heading, and names the dialog', tags( dialog, 'h2' )[0].textContent === 'Elements' && tags( dialog, 'h2' )[0].className === 'nino-admin-dialog-title'
		&& dialog.attributes['aria-labelledby'] === tags( dialog, 'h2' )[0].id && tags( dialog, 'h2' )[0].id !== '' );
	check( 'the manual stands between it and the button, in a box of its own that scrolls', findAll( dialog, el => el.className === 'nino-admin-dialog-content' )[0].children[0] === content
		&& findAll( dialog, el => el.className === 'nino-admin-dialog-body' )[0].children.map( el => el.className ).join('|') === 'nino-admin-dialog-title|nino-admin-dialog-content|nino-admin-dialog-actions' );
	check( 'a manual is read when it is looked at: the dialog has no description to be read out with it', dialog.attributes['aria-describedby'] === undefined );
	const close = tags( dialog, 'button' );
	check( 'one button closes it, and it is the first control in the dialog - which is where the focus goes when it opens', close.length === 1 && close[0].textContent === 'Close' && close[0].focused === true );

	check( 'asked while it is open, a second manual is refused', ui.manualDialog( 'Text', content, help ) === false && d.opened.length === 1 );

	help.blur();
	close[0].click();
	check( 'the button closes it and takes it out of the page, and the focus goes back to the "?" that asked', dialog.open === false && dialog.parentNode === null && help.focused === true );
	check( '...and a new one can be opened after it', ui.manualDialog( 'Text', content, help ) === true && d.opened.length === 2 );

	// Escape closes a modal dialog by itself, and says so as every way of closing does
	help.blur();
	d.opened[1].close();
	check( 'Escape - the browser closing it - gives the focus back as well', help.focused === true && d.opened[1].parentNode === null );
}

{
	// The button that was pressed has the focus in Chromium and in Firefox, and does not in Safari: the one that asked is given
	const d = dialogs();
	const ui = d.Nino.adminUi;
	const help = element('button');
	d.page.appendChild( help );
	check( 'nothing has the focus when the dialog opens, as after a click in Safari', d.doc.activeElement === null && ui.manualDialog( 'Elements', ui.manual( 'A line.' ), help ) === true );
	d.opened[0].close();
	check( '...and the "?" that was named gets it back all the same', help.focused === true );

	// Without an opener it is what had the focus when the question was asked
	const field = element('input');
	d.page.appendChild( field );
	field.focus();
	ui.manualDialog( 'Elements', ui.manual( 'A line.' ) );
	d.opened[1].close();
	check( 'a dialog that is not told who asked gives the focus back to what had it', field.focused === true );
}

{
	// A question keeps its message and its description, and the options the manual added are optional
	const d = dialogs();
	const ui = d.Nino.adminUi;
	const answers = [];
	ui.choiceDialog( { title : 'Unsaved', message : 'Save first?', choices : [ { value : 'save', label : 'Save', kind : 'primary' }, { value : 'cancel', label : 'Cancel', kind : 'secondary' } ], onChoose : v => answers.push( v ) } );
	const question = d.opened[0];
	check( 'a question is as it was: its message in a paragraph, described by it, no scrolling box, the class the design system gives it', question.className === 'nino-admin-dialog' && question.attributes['aria-describedby'] === 'nino-admin-choice-text'
		&& findAll( question, el => el.id === 'nino-admin-choice-text' )[0].textContent === 'Save first?' && findAll( question, el => el.className === 'nino-admin-dialog-content' ).length === 0 );
	question.close();
	check( '...and Escape is the answer that does nothing', answers.join() === 'cancel' );
}

{
	// A browser without showModal() asks with confirm(): nothing to show a dialog in, and the manual is its words
	const d = dialogs( false );
	const ui = d.Nino.adminUi;
	// What a browser's textContent makes of the manual: the stand-in does not add its children up
	const words = element('div');
	words.textContent = 'A line of words.';
	check( 'a browser that cannot open a dialog is shown the manual as text in a confirm, and the call still answers true', ui.manualDialog( 'Elements', words ) === true
		&& d.asked.join() === 'A line of words.' && findAll( d.wrap, el => el.tagName === 'DIALOG' ).length === 0 );
	check( '...without leaving the page believing a question is open', ui.manualDialog( 'Elements', words ) === true && d.asked.length === 2 );
}

done();
