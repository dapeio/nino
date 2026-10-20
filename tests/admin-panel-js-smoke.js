/**
 *	Nino										A compact filesystembased php framework
 *	admin-panel-js-smoke.js	Nino.adminUi.panel() and Nino.adminUi.levels(), the
 *													lifecycle every panel script wrote for itself: when the
 *													panel asks for its screen, what it does with the
 *													answer and with a failure, what a second showing
 *													does, and the level it shows - and showLevel() and
 *													backTo(), which move the focus into a level and back
 *													to the control that opened it. Nino.admin.js is loaded
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
		content : { getText : function( key ) { return { '/_admin/catalog/error/load' : 'The list could not be loaded.', '/_admin/catalog/error/other' : 'Something else failed.' }[key] || '' } },
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

done();
