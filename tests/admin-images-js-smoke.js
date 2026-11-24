/**
 *	Nino										A compact filesystembased php framework
 *	admin-images-js-smoke.js	DOM-light checks for the Images panel's script
 *													(_admin/Nino/Modules/Images/assets/admin.js) over the real
 *													shell (adminPage() of the harness) and the Image Slots tab
 *													(slots.js).
 *
 *													The Images panel is the standard directory of the slots with
 *													the record of one slot over it, and everything in the record
 *													is saved with Save: a file that is chosen is a preview in the
 *													browser and goes to the server with the save - the picture
 *													first, then the alt texts that were changed, one request each
 *													- a record with no change sends nothing, closing or discarding
 *													leaves nothing on the server, and Remove image is an action of
 *													its own that is asked about and acts at once. Pinned: the
 *													columns, the search over the name, the key, the group and every
 *													page, the group and the choice of what to show, the sort by the
 *													numbers of a size, the address of a slot as ONE part, the
 *													errors at the field they belong to, that a name of the browser
 *													is in no field of a request, that the preview is a data address
 *													and that everything a person or the server wrote is shown as
 *													text. The Image Slots tab is a list of rows with an address each
 *													and a form with its bar. Nothing here compares a sentence: the
 *													checks read the build - which block drew a part, where it
 *													stands, what was sent - and the keys the scripts ask for are
 *													real ones in both languages.
 *
 *	Usage: node tests/admin-images-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, source, fills, load, element, findAll, fire, documentOf, shellRouter, adminPage, markupWrites } = require( './harness.js' );

// What the Image Slots tab writes into the address goes through the router's own codec: every state written is read back
const codec = shellRouter();

const moduleEn	= fills('_admin/Nino/Modules/Images/text/en_US.php');
const moduleDe	= fills('_admin/Nino/Modules/Images/text/de_DE.php');
const workbench	= fills('_admin/text/en_US.php');

const text = function( key ) { return moduleEn[key] ?? workbench[key] ?? '' };

/**
 *	One page of the workbench: the panes a panel script finds by id, the shell's router and registry of
 *	unsaved input as far as the scripts use them, a dialog that opens and closes as the browser's does,
 *	and the requests the script sends, kept. The scripts named are loaded into it
 *
 *	@param		{Array}		levels			The ids of the levels of the pane
 *	@param		{string}	panel				The pane's name
 *	@param		{Array}		scripts			The scripts of the module, from the checkout root
 *
 *	@return		{Object}
 */
function page( levels, panel, scripts ) {

	const pane = element('div');
	pane.dataset.panel = panel;
	const head = element('div');
	head.className = 'admin-panel-head';
	const title = element('h2');
	title.className = 'admin-panel-title';
	title.textContent = 'Images';
	head.appendChild( title );
	pane.appendChild( head );

	const out = { pane : pane, title : title, requests : [], dialogs : [], routed : [], touched : [], saved : [], refreshed : 0, asked : [], hash : { panel : panel, parts : [] }, stamps : { templates : 0 }, levels : {} };

	levels.forEach( function( id ) {
		const level = element('div');
		level.id = id;
		pane.appendChild( level );
		out.levels[id] = level;
	} );

	// The shell's registry of unsaved input: the entries a script registered, and the question before an exit where one is dirty
	const registry = {
		_entries : {},
		register : function( name, entry ) { registry._entries[name] = entry },
		watchForm : function( name, getter, save ) { registry._entries[name] = { form : getter, save : save, isDirty : function() { return false } } },
		snapshot : function( name ) { out.saved.push( name ) },
		refresh : function() { out.refreshed++ },
		guard : function( names, proceed, onCancel ) {
			if( names.some( function( name ) { return registry._entries[name] !== undefined && registry._entries[name].isDirty() === true } ) === false )
				return proceed();
			out.asked.push( { names : names, proceed : proceed, onCancel : onCancel } );
		},
	};

	const router = {
		set : function( name, parts ) { codec.check( name, parts ); out.routed.push( 'set '+ [ name ].concat( parts ).join('/') ) },
		go : function( name, parts ) { codec.check( name, parts ); out.routed.push( 'go '+ [ name ].concat( parts ).join('/') ) },
		href : function( name, parts ) { return codec.check( name, parts ) }, word : codec.word, wordOf : codec.wordOf, isWord : codec.isWord,
		current : function() { return out.hash },
		leave : function( names, leaving, proceed, resync ) { if( leaving === true ) return registry.guard( names, proceed, resync ); proceed() },
	};

	const Nino = {
		admin : { dirty : registry, router : router, touch : function( topic ) { out.touched.push( topic ) }, stamp : function( topic ) { return out.stamps[topic] ?? 0 } },
		events : { bindCallback : function() {} },
		http : { sendRequest : function( uri, method, callback, data ) {
			out.requests.push( { action : data.action, payload : JSON.parse( data.data ), file : data.file, callback : callback } );
		} },
		content : { getText : text },
	};

	const document = documentOf( pane );
	const wrap = element('div');
	wrap.dataset.uploadBytes = '2097152';
	wrap.dataset.uploadPixels = '20000000';
	const byId = document.getElementById;
	document.getElementById = function( id ) { return id === 'admin-page-wrap' ? wrap : byId( id ) };
	document.body = element('body');
	document.documentElement = { lang : 'en' };
	Object.defineProperty( document, 'activeElement', { get : function() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null } } );

	const created = document.createElement;
	document.createElement = function( tag ) {
		const el = created( tag );
		if( tag === 'dialog' ) {
			el.open = false;
			el.showModal = function() { el.open = true };
			el.close = function() { el.open = false; fire( el, 'close' ) };
			out.dialogs.push( el );
		}
		return el;
	};

	out.bitmap = null;
	const window = {
		Nino : Nino,
		location : { hash : '' },
		setTimeout : function() { return 1 },
		clearTimeout : function() {},
		confirm : function() { throw new Error('a question is asked in a dialog of the design system, not with the browser\'s confirm') },
		alert : function() { throw new Error('a sentence is said at the field, not with the browser\'s alert') },
		createImageBitmap : function() { return out.bitmap === null ? Promise.reject( new Error('no') ) : Promise.resolve( out.bitmap ) },
	};

	const context = vm.createContext( { console : console, document : document, Nino : Nino, window : window } );
	load( '_admin/assets/Nino.admin.js', context );
	scripts.forEach( function( script ) { load( script, context ) } );
	Nino.adminUi.locales.names = { de_DE : 'Deutsch', en_US : 'English (US)' };

	out.Nino = Nino;
	out.window = window;
	out.registry = registry;
	out.answer = function( status, body ) { out.requests[out.requests.length - 1].callback( { status : status, responseJSON : body } ) };
	out.toasts = function() { return findAll( document.body, function( el ) { return el.classList.contains('nino-admin-toast-text') } ).map( function( el ) { return el.textContent } ) };
	out.focused = function() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null };
	out.all = function( root, predicate ) { return findAll( root, predicate ) };
	out.ask = function( yes ) {
		const dialog = out.dialogs.pop();
		const buttons = findAll( dialog, function( el ) { return el.tagName === 'BUTTON' } );
		fire( buttons[yes === true ? 1 : 0], 'click' );
		return dialog;
	};

	return out;
}

const has = function( el, name ) { return el.classList.contains( name ) };
const byClass = function( root, name ) { return findAll( root, function( el ) { return has( el, name ) } ) };
const buttonsOf = function( root ) { return findAll( root, function( el ) { return el.tagName === 'BUTTON' } ) };
const errorsOf = function( root ) { return byClass( root, 'nino-admin-field-error' ) };

( async function() {

// =================================================================================================================
// The Images panel: the standard directory of the slots and the record of one slot, over the real shell
// =================================================================================================================

const hasClass = ( el, name ) => String( el.className ).split(/\s+/).indexOf( name ) !== -1;
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );
const labelOf = el => el.textContent;

// What the server answers for the slots: 40 of them in several groups, some without a picture, some without a page, some with alt texts
const MARKUP = '<img src=x onerror=alert(1)>';
function slotList() {

	const slots = [];
	const add = ( uri, label, extra ) => slots.push( Object.assign( {
		uri : uri, label : label, width : 640, height : 420, url : '/public/images/'+ uri.slice( 1 )+ '.640x420.webp', file : uri.slice( 1 )+ '.640x420.webp', bytes : 20480, shown : { width : 640, height : 420 },
		alt : { de_DE : label+ ' de', en_US : label+ ' en' }, usage : { templates : [ 'page-home' ], pages : [ { httpUri : '/', name : 'Startseite' } ] },
	}, extra ?? {} ) );

	add( '/logo', 'Logo', { width : 500, height : 100, shown : { width : 500, height : 100 }, usage : { templates : [ 'frame-header' ], pages : [ { httpUri : '/', name : 'Startseite' }, { httpUri : '/en', name : 'Startseite' }, { httpUri : '/contact', name : 'Kontakt' }, { httpUri : '/about', name : '/about' } ] } } );
	add( '/logo/dark', 'Logo, dunkel', { width : 500, height : 100, url : null, file : null, bytes : 0, shown : null, alt : {}, usage : { templates : [], pages : [] } } );
	add( '/template/page-home/hero/background', 'Startseite - Titelbild', { width : 1920, height : 1080, shown : { width : 1920, height : 1080 }, alt : { de_DE : 'Ein Haus' } } );
	add( '/template/page-home/service/heating', 'Leistung Heizung', { alt : {} } );
	add( '/template/page-about/team/photo', 'Teamfoto', { usage : { templates : [], pages : [] } } );

	// A key that holds a slash, a percent sign, markup and an at sign: ONE part of the address
	add( '/odd/a%b@c', MARKUP+ ' Name', { alt : { de_DE : MARKUP }, usage : { templates : [], pages : [ { httpUri : '/x', name : MARKUP } ] } } );

	add( '/template/page-lose/hero/image', 'Lose Hero', { alt : {} } );

	for( let i = 1; i <= 33; i++ )
		add( '/template/page-gallery/item-'+ i+ '/image', 'Galerie '+ i, { url : i % 6 === 0 ? null : undefined, file : i % 6 === 0 ? null : undefined, bytes : i % 6 === 0 ? 0 : 20480, alt : i % 4 === 0 ? {} : { de_DE : 'Bild '+ i, en_US : 'Image '+ i } } );

	// What the server says of the group: the page a template belongs to, the feature a key belongs to, the place with no name of its own - each with its key
	slots.forEach( slot => {
		const home = { key : 'page:page-home', type : 'page', name : 'Startseite', address : '/' };
		slot.group = slot.uri.indexOf('/template/page-home/') === 0 ? home : ( slot.uri.indexOf('/template/page-about/') === 0 ? { key : 'page:page-about', type : 'page', name : 'Über uns', address : '/about' }
			: ( slot.uri.indexOf('/template/page-gallery/') === 0 ? { key : 'feature:gallery', type : 'feature', name : 'Galerie', address : '' } : ( slot.uri.indexOf('/template/page-lose/') === 0 ? { key : 'other:page-lose', type : 'other', name : 'Lose', address : '' } : { key : 'general', type : 'general', name : '', address : '' } ) ) );
		slot.everywhere = slot.uri === '/logo';
	} );

	slots.forEach( slot => { if( slot.url === undefined ) { slot.url = '/public/images/'+ slot.uri.slice( 1 )+ '.640x420.webp'; slot.file = slot.uri.slice( 1 )+ '.640x420.webp' } } );

	return slots;
}

function world( hash ) {

	const h = adminPage();
	const mount = element('div');
	const requests = [];
	const readers = [];
	const probes = [];

	mount.id = 'images-list';
	h.page.appendChild( mount );
	h.show('images');
	h.router.start();
	h.router.set( 'images', [] );
	h.Nino.content.getText = text;
	h.Nino.http = { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( data.data ), file : data.file, csrf : data._csrf, callback : callback } );
	} };
	h.ui.locales.names = { de_DE : 'Deutsch', en_US : 'English (US)' };
	h.ui.locales.native = 'de_DE';

	const wrap = h.doc.getElementById('admin-page-wrap');
	wrap.dataset.uploadBytes = '2097152';
	wrap.dataset.uploadPixels = '20000000';

	// The browser's reading of a file and decoding of a picture, as stand-ins that the suite finishes itself
	h.box.FileReader = function() { readers.push( this ); this.readAsDataURL = file => { this.file = file }; };
	h.box.createImageBitmap = () => Promise.resolve( { width : 100, height : 100, close() {} } );
	const create = h.doc.createElement;
	h.doc.createElement = function( name ) { const el = create( name ); if( name === 'img' ) probes.push( el ); return el };

	if( hash !== undefined )
		h.box.location.hash = hash;

	load( '_admin/Nino/Modules/Images/assets/admin.js', h.context );

	const panel = h.admin.images;
	const answer = ( status, body ) => { const request = requests[requests.length - 1]; request.callback( { status : status, responseJSON : body } ); return request };
	// A request that never got an answer: the browser's own failure, which has no headers
	const offline = () => { const request = requests[requests.length - 1]; request.callback( { status : 500, responseJSON : null, getAllResponseHeaders : () => '' } ); return request };
	const open = ( slots, locales ) => { panel.showCurrent(); answer( 200, { slots : slots ?? slotList(), locales : locales ?? [ 'de_DE', 'en_US' ] } ) };

	return { h, panel, mount, requests, readers, probes, answer, offline, open,
		rows : () => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined ),
		ids : () => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined ).map( el => el.dataset.row ),
		search : () => tag( mount, 'input' ).find( el => el.type === 'search' ),
		selects : () => tag( mount, 'select' ).filter( el => el.id === 'images-group' || el.id === 'images-show' ),
		select : id => tag( mount, 'select' ).find( el => el.id === id ),
		dialog : () => h.dialogs.filter( d => d.open ).pop() ?? null,
		first : () => h.dialogs.filter( d => d.open )[0] ?? null,
		button : ( d, key ) => tag( d, 'button' ).find( b => b.textContent === text( key ) || b.attributes['aria-label'] === text( key ) ),
		alts : d => tag( d, 'input' ).filter( el => el.type === 'text' ),
		fileInput : d => tag( d, 'input' ).find( el => el.type === 'file' ),
		tree : () => JSON.stringify( requests.map( r => r.action ) ),
		stateOf : d => byClass( d, 'nino-admin-actionbar-state' )[0],
	};
}

const SAVE = '/_admin/common/label/save';
const KEY = '/template/page-home/hero/background';
const REMOVE = '/_admin/images/label/remove';
const shotFile = ( name, size, type ) => ( { name : name, size : size ?? 3000, type : type ?? 'image/png' } );

// A file is chosen: the field takes it, the check of the browser passes (a bitmap that is small), the reading of the preview is finished and the picture measured
async function choose( w, d, file, size ) {

	const input = w.fileInput( d );

	input.files = [ file ];
	fire( input, 'change' );
	await new Promise( resolve => setTimeout( resolve, 0 ) );

	const reader = w.readers[w.readers.length - 1];

	if( reader !== undefined ) {
		reader.result = 'data:image/png;base64,iVBORw0KGgo=';
		reader.onload();
		const probe = w.probes.filter( el => typeof el.onload === 'function' ).pop();

		if( probe !== undefined ) {
			probe.naturalWidth = size?.[0] ?? 1920;
			probe.naturalHeight = size?.[1] ?? 1080;
			probe.onload();
		}
	}

	return reader;
}

console.log('Admin Images');

{
	// --- the directory ----------------------------------------------------------------------------------

	const w = world();

	check( 'the script attaches under its nav uri with init and showCurrent', typeof w.panel === 'object' && typeof w.panel.init === 'function' && typeof w.panel.showCurrent === 'function' );

	w.panel.showCurrent();
	check( 'showing it asks the backend for the list in one request, and for nothing else', w.requests.length === 1 && w.requests[0].action === 'images/list' && JSON.stringify( w.requests[0].payload ) === '{}' );
	check( 'while it loads the sentence stands in place of the table, and says it is busy', byClass( w.mount, 'nino-admin-loading' ).length === 1 && byClass( w.mount, 'nino-admin-directory-body' )[0].attributes['aria-busy'] === 'true' );

	w.answer( 200, { slots : slotList(), locales : [ 'de_DE', 'en_US' ] } );

	check( 'the table pages by 25, the pictures being what a row shows, and says the range of all 40', w.ids().length === 25 && byClass( w.mount, 'nino-admin-table-range' )[0].textContent === w.h.ui.format( w.h.ui.text('/_admin/common/table/range'), 1, 25, 40 ) );

	const heads = findAll( w.mount, el => el.tagName === 'TH' && el.dataset.key !== undefined ).map( el => el.dataset.key );
	check( 'the columns are the picture, the name, the group, the target size, where it is used and the alt text of each language', heads.join() === 'url,label,groupName,size,usedText,states' );
	check( 'the heads of the picture and of the states of the languages are words, not buttons: they cannot be sorted', findAll( w.mount, el => el.tagName === 'TH' && el.dataset.key === 'url' )[0].children.every( c => c.tagName !== 'BUTTON' )
		&& findAll( w.mount, el => el.tagName === 'TH' && el.dataset.key === 'states' )[0].children.every( c => c.tagName !== 'BUTTON' ) );
	check( 'there is no box to choose rows by: nothing is done to several slots at once', tag( w.mount, 'input' ).filter( el => el.type === 'checkbox' ).length === 0 );

	const first = w.rows()[0];
	const cells = first.children.filter( el => el.tagName === 'TD' );
	check( 'a row is the picture of the slot, its name strong with its key under it, the group by name, the target size, where it is used and a badge for the alt text of each language',
		byClass( cells[0], 'nino-admin-thumb' ).length === 1 && byClass( cells[1], 'images-name-label' )[0].textContent === 'Logo' && byClass( cells[1], 'images-name-key' )[0].textContent === '/logo'
		&& cells[2].textContent === text('/_admin/images/group/general') && cells[3].textContent === '500 × 100 px' && byClass( cells[4], 'images-used' )[0].textContent === text('/_admin/images/label/everywhere')
		&& byClass( cells[5], 'nino-admin-badge' ).map( labelOf ).join() === 'DE ✓,EN ✓' );
	check( '...a slot that every page shows is said so, and the cell keeps the pages in its tooltip by their names - a page of two languages once, the address where a page has no name', byClass( cells[4], 'images-used' )[0].title === 'Startseite, Kontakt, /about' );
	const two = w.rows().find( el => el.dataset.row === KEY ).children.filter( el => el.tagName === 'TD' );
	check( 'a slot that some pages show names them: the page by its name', byClass( two[4], 'images-used' )[0].textContent === 'Startseite' && two[2].textContent === 'Startseite' );

	const dark = w.rows()[1];
	check( 'a slot that no page uses carries the badge "nowhere included" in the cell of the pages, in the state of a warning - and a slot with no picture has the sign of "none" for it',
		byClass( dark, 'nino-admin-badge' ).filter( b => b.dataset.state === 'warn' && b.textContent === text('/_admin/images/label/unused') ).length === 1 && byClass( dark, 'nino-admin-thumb' )[0].classList.contains('is-none') === true );

	check( 'the group is the select left of the search, the choice of what to show is the one after it: the group, the search, the choice', w.selects().length === 2 && w.selects()[0].id === 'images-group' && w.selects()[1].id === 'images-show'
		&& w.select('images-group').parentNode.children[0] === w.select('images-group') && w.select('images-group').parentNode.children[1] === w.search() );
	check( '...the groups are the names of the categories - one entry for each - after the entry that turns the select off', w.select('images-group').children.map( o => o.value ).join('|') === '|general|feature:gallery|other:page-lose|page:page-home|page:page-about' && w.select('images-group').children.map( o => o.textContent ).join('|') === text('/_admin/images/label/group-all')+ '|'+ text('/_admin/images/group/general')+ ' (3)|Galerie (33)|Lose (1)|Startseite (2)|Über uns (1)' );
	check( '...and the choice of what to show says: all, without a picture, alt text missing, not included anywhere', w.select('images-show').children.map( o => o.value ).join() === ',none,alt,unused' );

	// the search reads the name, the key, the group and every page - by its name and by its address
	w.search().value = 'kontakt';
	fire( w.search(), 'input' );
	check( 'the search finds a slot by a page that uses it, though the cell shows only two of its pages', w.ids().join() === '/logo' );
	w.search().value = '/contact';
	fire( w.search(), 'input' );
	check( '...by the address of that page as well', w.ids().join() === '/logo' );
	w.search().value = 'item-7';
	fire( w.search(), 'input' );
	check( '...by the key', w.ids().join() === '/template/page-gallery/item-7/image' );
	w.search().value = 'Galerie 7';
	fire( w.search(), 'input' );
	check( '...by the name', w.ids().join() === '/template/page-gallery/item-7/image' );
	w.search().value = 'startseite';
	fire( w.search(), 'input' );
	check( '...by the group, as it is called: the name of the page', w.ids().length >= 2 && w.ids().indexOf( KEY ) !== -1 );
	w.search().value = '';
	fire( w.search(), 'input' );

	// what to show
	const show = value => { w.select('images-show').value = value; fire( w.select('images-show'), 'change' ) };
	const shown = () => w.panel._directory.rows();

	show( 'none' );
	check( 'without a picture shows the slots that have none', shown().every( row => row.hasImage === false ) && shown().length === 6 );
	show( 'unused' );
	check( 'not included anywhere shows the slots that no page uses', shown().every( row => row.unused === true ) && shown().length === 2 );
	show( 'alt' );
	check( 'alt text missing shows the slots that have a picture and lack the text in a language - a slot with no picture is not asked for one',
		shown().length === 9 + 1 && shown().every( row => row.hasImage === true && row.states.some( state => state.ok === false ) ) );
	show( '' );

	w.select('images-group').value = 'general';
	fire( w.select('images-group'), 'change' );
	check( 'a group narrows the rows to the slots of that group - the slots with no place of their own stand under "General"', w.ids().sort().join() === '/logo,/logo/dark,/odd/a%b@c' );
	w.select('images-group').value = '';
	fire( w.select('images-group'), 'change' );

	// sorting: the size by the numbers in it, not by the letters
	const sizeHead = findAll( w.mount, el => el.tagName === 'TH' && el.dataset.key === 'size' )[0];
	fire( tag( sizeHead, 'button' )[0], 'click' );
	check( 'the target size sorts by its numbers: 500 before 640 before 1920', w.panel._directory.rows()[0].size === '500 × 100 px' && w.panel._directory.rows().pop().size === '1920 × 1080 px' );

	// the markup of the names is text
	const odd = w.panel._find( '/odd/a%b@c' );
	check( 'the name, the alt text and a page that spell markup are kept as they are, and drawn as text - no element comes of them', odd.label === MARKUP+ ' Name' && tag( w.mount, 'img' ).filter( el => el.src.indexOf('x') === 0 ).length === 0 && markupWrites.length === 0 );
}


// --- groups are led by their key: the same name is not the same group ------------------------------------------------------------------------

{
	const w = world();
	const mk = ( uri, group ) => ( { uri : uri, label : uri, width : 10, height : 10, url : null, file : null, bytes : 0, shown : null, alt : {}, usage : { templates : [], pages : [] }, group : group, everywhere : false } );
	const list = [
		mk( '/logo', { key : 'general', type : 'general', name : '', address : '' } ),
		mk( '/template/page-a/x/image', { key : 'page:page-a', type : 'page', name : text('/_admin/images/group/general'), address : '/allgemein' } ),
		mk( '/template/page-b/x/image', { key : 'page:page-b', type : 'page', name : 'Kontakt', address : '/kontakt' } ),
		mk( '/template/page-c/x/image', { key : 'page:page-c', type : 'page', name : 'Kontakt', address : '/kontakt-2' } ),
		mk( '/template/page-d/x/image', { key : 'page:page-d', type : 'page', name : 'Startseite', address : '/' } ),
	];

	w.open( list );
	const options = w.select('images-group').children;
	check( 'a page that is called like the general group is a group of its own, and says its address beside the name', options.map( o => o.value ).join('|') === '|general|page:page-a|page:page-b|page:page-c|page:page-d'
		&& options.find( o => o.value === 'page:page-a' ).textContent === text('/_admin/images/group/general')+ ' (/allgemein) (1)' && options[1].textContent === text('/_admin/images/group/general')+ ' (1)' );
	check( '...as do two pages of one name: two groups, each with its address - a name that is not shared stands alone', options.find( o => o.value === 'page:page-b' ).textContent === 'Kontakt (/kontakt) (1)' && options.find( o => o.value === 'page:page-c' ).textContent === 'Kontakt (/kontakt-2) (1)'
		&& options.find( o => o.value === 'page:page-d' ).textContent === 'Startseite (1)' );
	w.select('images-group').value = 'page:page-c';
	fire( w.select('images-group'), 'change' );
	check( '...and a group narrows to its own slots: the other page of that name is not in it', w.ids().join() === '/template/page-c/x/image' );
}


// --- the record: one slot --------------------------------------------------------------------------------

{
	const w = world();
	w.open();
	const before = w.h.entries.length;
	const row = w.rows().find( el => el.dataset.row === KEY );

	fire( row, 'click', { target : row } );
	const d = w.dialog();

	check( 'a click on a row opens its record, the large dialog, at the address #images/images/<the uri as ONE part>, one entry more in the history',
		d !== null && hasClass( d, 'nino-admin-dialog--large' ) && w.h.hash() === '#images/images/%2Ftemplate%2Fpage-home%2Fhero%2Fbackground' && w.h.entries.length === before + 1 );
	check( 'it is named by the slot and says its key under it, and has the place in the rows', byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Startseite - Titelbild' && byClass( d, 'nino-admin-dialog-subtitle' )[0].textContent === KEY
		&& byClass( d, 'nino-admin-record-count' )[0].textContent.indexOf(' of 40') !== -1 );

	const frame = byClass( d, 'nino-admin-imageframe' )[0];
	check( 'the picture stands large in the frame of the workbench, with the file, its size and its weight under it', tag( frame, 'img' ).length === 1 && tag( frame, 'img' )[0].src === '/public/images/template/page-home/hero/background.640x420.webp'
		&& byClass( frame, 'nino-admin-imageframe-caption' )[0].textContent === text('/_admin/images/msg/current').replace( '%s', 'template/page-home/hero/background.640x420.webp · 1920 × 1080 px · 20 KB' ) );

	const lines = byClass( d, 'nino-admin-readline' ).map( el => byClass( el, 'nino-admin-readline-label' )[0].textContent );
	check( 'it reads the target size and where the slot is used', lines.join() === text('/_admin/images/label/target')+ ','+ text('/_admin/images/label/usedon') );
	check( 'the file is chosen with the block of the workbench: it takes the types the server takes, and the limits of the upload stay in view as its note',
		byClass( d, 'nino-admin-filefield' ).length === 1 && w.fileInput( d ).accept === '.jpg,.jpeg,.png,.webp,.gif' && byClass( d, 'nino-admin-field-note' ).length === 1 );
	check( 'the alt text is the languages block: a row for EVERY language of the site, the working language first, a short value, at most 250 characters',
		byClass( d, 'nino-admin-localerow' ).length === 2 && w.alts( d ).every( input => input.maxLength === 250 ) && w.alts( d ).map( input => input.dataset.locale ).join() === 'de_DE,en_US' );
	check( '...and what is stored stands in the row of its language', w.alts( d ).map( input => input.value ).join() === 'Ein Haus,' );

	const bar = byClass( d, 'nino-admin-actionbar' )[0];
	check( 'the bar has the dangerous button first, "Remove image", then the state, "Save and continue", and the one main button "Save" last - no Cancel, no Close',
		bar.children[0].textContent === text( REMOVE ) && hasClass( bar.children[0], 'nino-admin-btn-danger' ) && bar.children.filter( el => el.tagName === 'BUTTON' ).map( labelOf ).join() === text( REMOVE )+ ','+ text('/_admin/common/label/save-next')+ ','+ text( SAVE )
		&& hasClass( bar.children[bar.children.length - 1], 'nino-admin-btn-primary' ) );
	check( 'a record that was just opened says nothing of its state', w.stateOf( d ).hidden === true );

	// Stepping goes along the rows the directory shows
	fire( w.button( d, '/_admin/images/record/next' ), 'click' );
	check( 'stepping goes to the slot after in the rows the directory shows, and the address follows without a new entry', byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Leistung Heizung' && w.h.hash() === '#images/images/%2Ftemplate%2Fpage-home%2Fservice%2Fheating' && w.h.entries.length === before + 1 );
	fire( w.button( d, '/_admin/images/record/prev' ), 'click' );
	fire( w.button( d, '/_admin/images/record/prev' ), 'click' );

	// A slot with no picture: the sign of none, the notice that nobody uses it, and no button to remove what is not there
	check( 'a slot with no picture shows the sign of "none" in the frame, and has no button to take a picture away', w.h.hash() === '#images/images/%2Flogo%2Fdark' && byClass( d, 'nino-admin-imageframe-frame' )[0].classList.contains('is-none') === true && w.button( d, REMOVE ).hidden === true );
	check( 'a slot that no page uses says so in a notice of its own in the line of "used on"', byClass( d, 'nino-admin-notice' ).filter( n => n.textContent === text('/_admin/images/msg/unused') ).length === 1 );
}


// --- Save: what was changed is sent, what was not is not --------------------------------------------------

{
	const w = world();
	w.open();
	const row = w.rows().find( el => el.dataset.row === KEY );

	fire( row, 'click', { target : row } );
	const d = w.dialog();
	const asked = w.requests.length;

	fire( w.button( d, SAVE ), 'click' );
	check( 'a save with no change sends nothing - there is no request, so no write: what is stored stays byte for byte as it was', w.requests.length === asked && w.stateOf( d ).dataset.state === 'ok' );

	// Only the language that was changed is sent
	w.alts( d )[1].value = 'A house';
	fire( w.alts( d )[1], 'input' );
	w.h.dirty.refresh();
	check( 'a typed alt text makes the record changed, and nothing is sent by typing - nor by leaving the field', w.stateOf( d ).dataset.state === 'warn' && ( fire( w.alts( d )[1], 'blur' ), w.requests.length === asked ) );
	check( '...and the shell knows of the input', w.h.dirty.isDirty( [ 'images' ] ) === true );

	fire( w.button( d, SAVE ), 'click' );
	check( 'Save sends the alt texts of the languages that were changed in ONE request, for the slot: the other language is not in it',
		w.requests.length === asked + 1 && w.requests[asked].action === 'images/alt' && JSON.stringify( w.requests[asked].payload ) === JSON.stringify( { uri : KEY, alt : { en_US : 'A house' } } ) );

	w.answer( 200, { alt : { de_DE : 'Ein Haus', en_US : 'A house' } } );
	const saved = w.panel._find( KEY );
	check( 'once the server says so the row has the new text - the badge of the language turned to "present" - and the record says saved', saved.alt.en_US === 'A house' && saved.states.map( s => s.ok ).join() === 'true,true' && w.stateOf( d ).dataset.state === 'ok' && w.h.dirty.isDirty( [ 'images' ] ) === false );
	check( '...the row of the directory is drawn anew and the other panels are told that a slot was written', w.rows().find( el => el.dataset.row === KEY ).classList.contains('is-fresh') === true && w.h.admin.stamp('slots') === 1 );

	// What the server stored - cleaned - is what the field holds
	w.alts( d )[0].value = '  Haus mit Dach ';
	fire( w.alts( d )[0], 'input' );
	fire( w.button( d, SAVE ), 'click' );
	w.answer( 200, { alt : { de_DE : 'Haus mit Dach', en_US : 'A house' } } );
	check( 'what the server stored is what the field holds after the save: the spaces are gone', w.alts( d )[0].value === 'Haus mit Dach' && w.h.dirty.isDirty( [ 'images' ] ) === false );

	// 251 characters: said at the language before anything is sent
	const sent = w.requests.length;
	w.alts( d )[0].value = 'x'.repeat( 251 );
	fire( w.alts( d )[0], 'input' );
	fire( w.button( d, SAVE ), 'click' );
	check( 'an alt text of more than 250 characters is said at its language - and nothing is sent', w.requests.length === sent && byClass( d, 'nino-admin-field-error' ).length === 1 && w.stateOf( d ).dataset.state === 'warn' );

	// A refused alt text: at the language, the line says it failed, what was typed stays
	w.alts( d )[0].value = 'Neu';
	fire( w.alts( d )[0], 'input' );
	fire( w.button( d, SAVE ), 'click' );
	w.answer( 400, { error : 'refused', code : 'images_alt_plain', field : 'alt' } );
	check( 'an alt text the server refuses is said at the language it was typed in, with what was typed still in the field, and the record is still changed',
		byClass( d, 'nino-admin-field-error' ).length === 1 && w.alts( d )[0].value === 'Neu' && w.h.dirty.isDirty( [ 'images' ] ) === true );
}


// --- Choose a file: a preview in the browser, nothing sent until Save ---------------------------------------------

{
	const w = world();
	w.open();
	const row = w.rows().find( el => el.dataset.row === KEY );

	fire( row, 'click', { target : row } );
	const d = w.dialog();
	const asked = w.requests.length;
	const hostile = shotFile( MARKUP+ '.png', 5000 );

	await choose( w, d, hostile, [ 3000, 2000 ] );
	check( 'a chosen file is shown as a preview in the frame - and nothing is sent', w.requests.length === asked && tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' )[0].src.indexOf('data:image/png;base64,') === 0 );
	check( '...the preview is a data address: a blob address is not one the policy of the workbench lets a picture load, and the policy stays as it is', w.readers.length === 1 && tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' ).every( img => img.src.indexOf('blob:') === -1 ) );
	check( '...it says under the frame what it is - the file as text and its size in pixels - and that it is taken over with Save', byClass( d, 'nino-admin-imageframe-caption' )[0].textContent === text('/_admin/images/msg/chosen').replace( '%s', MARKUP+ '.png · 3000 × 2000 px' )
		&& tag( d, 'img' ).filter( el => el.src.indexOf('x') === 0 ).length === 0 );
	check( '...and the record is changed: the shell would ask before it were closed', w.h.dirty.isDirty( [ 'images' ] ) === true && w.stateOf( d ).dataset.state === 'warn' );
	check( 'a picture that is large enough says nothing of being scaled up', byClass( d, 'nino-admin-notice' ).filter( n => n.hidden !== true ).length === 0 );

	// Closing leaves nothing: the question, Discard, and the server has heard nothing
	fire( tag( d, 'button' ).find( b => hasClass( b, 'nino-admin-dialog-close' ) ), 'click' );
	check( 'closing a record with a file that was chosen asks first, as it does for any input', w.h.dialogs.filter( x => x.open ).length === 2 );
	const question = w.dialog();
	fire( tag( question, 'button' ).find( b => b.textContent === text('/_admin/common/label/discard') ), 'click' );
	check( 'Discard closes the record - and nothing was sent: no upload, no write, nothing left on the server', w.dialog() === null && w.requests.length === asked && w.tree() === '["images/list"]' );
	check( '...and the row is as it was', w.panel._find( KEY ).file === 'template/page-home/hero/background.640x420.webp' );
}

{
	// A picture smaller than the slot is said before it is sent
	const w = world();
	w.open();
	fire( w.rows()[3], 'click', { target : w.rows()[3] } );
	const d = w.dialog();

	await choose( w, d, shotFile( 'klein.png' ), [ 100, 50 ] );
	const warning = byClass( d, 'nino-admin-notice' ).filter( n => n.hidden !== true );
	check( 'a picture smaller than the target size says before it is sent that it is scaled up, with its own size', warning.length === 1 && warning[0].textContent.indexOf('100 × 50 px') !== -1 );

	// A file the browser can already tell is too large or no picture is not taken
	w.fileInput( d ).files = [ shotFile( 'gross.png', 3 * 1048576 ) ];
	fire( w.fileInput( d ), 'change' );
	check( 'a file over the limit of the upload is refused at once, said at the field, and the field holds none', byClass( d, 'nino-admin-field-error' ).length === 1 && w.fileInput( d ).value === '' );
}

{
	// Taking the choice back
	const w = world();
	w.open();
	fire( w.rows()[3], 'click', { target : w.rows()[3] } );
	const d = w.dialog();

	await choose( w, d, shotFile( 'a.png' ) );
	const remove = tag( d, 'button' ).find( b => b.textContent === text('/_admin/common/label/remove') && b.hidden !== true );
	fire( remove, 'click' );
	check( 'a choice that is taken back shows the picture the slot has again, and the record is not changed any more', tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' )[0].src.indexOf('data:') === -1 && w.h.dirty.isDirty( [ 'images' ] ) === false );
}


// --- Save with a file: the picture first, then the alt texts ------------------------------------------------------------

{
	const w = world();
	w.open();
	const row = w.rows().find( el => el.dataset.row === KEY );

	fire( row, 'click', { target : row } );
	const d = w.dialog();
	const asked = w.requests.length;
	const file = shotFile( '../../etc/passwd<b>.png', 4000 );

	await choose( w, d, file, [ 3000, 2000 ] );
	w.alts( d )[1].value = 'New en';
	fire( w.alts( d )[1], 'input' );
	fire( w.button( d, SAVE ), 'click' );

	check( 'Save sends the picture first: an upload for the slot, with the file in its own field - the name of the file is in no field of the request', w.requests.length === asked + 1 && w.requests[asked].action === 'images/upload'
		&& JSON.stringify( w.requests[asked].payload ) === JSON.stringify( { uri : KEY } ) && w.requests[asked].file === file && JSON.stringify( w.requests[asked].payload ).indexOf('passwd') === -1 );
	check( '...and the record is busy while it is out: a second press sends nothing', ( fire( w.button( d, SAVE ), 'click' ), w.requests.length === asked + 1 ) );

	w.answer( 200, { filename : 'template/page-home/hero/background.1920x1080.webp', url : '/public/images/template/page-home/hero/background.1920x1080.webp', bytes : 51200, belowTarget : false, source : { width : 3000, height : 2000 } } );
	check( 'once the picture is stored the alt texts that were changed go in a request of their own', w.requests.length === asked + 2 && w.requests[asked + 1].action === 'images/alt' && JSON.stringify( w.requests[asked + 1].payload.alt ) === '{"en_US":"New en"}' );
	check( '...the frame shows the stored picture - its address carries the moment, so that the replaced one is not the cached one - and the field is empty', /^\/public\/images\/template\/page-home\/hero\/background\.1920x1080\.webp\?t=\d+$/.test( tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' )[0].src ) && byClass( d, 'nino-admin-filefield-name' )[0].dataset.state === 'none' );

	w.answer( 200, { alt : { de_DE : 'Ein Haus', en_US : 'New en' } } );
	const saved = w.panel._find( KEY );
	check( 'both are in the row: the file, the weight, the size - and the stamp on the address of the picture, which a list read again keeps', saved.file === 'template/page-home/hero/background.1920x1080.webp' && saved.bytes === 51200 && /\?t=\d+$/.test( saved.url ) && saved.alt.en_US === 'New en'
		&& w.stateOf( d ).dataset.state === 'ok' && w.h.dirty.isDirty( [ 'images' ] ) === false );
	check( '...the thumbnail of the row is the new picture', tag( w.rows().find( el => el.dataset.row === KEY ), 'img' )[0].src === saved.url );
}

{
	// The server refuses the file: at the field - nothing was written, what was typed stays, the old picture stays
	const w = world();
	w.open();
	const row = w.rows().find( el => el.dataset.row === KEY );

	fire( row, 'click', { target : row } );
	const d = w.dialog();
	const before = w.panel._find( KEY );

	await choose( w, d, shotFile( 'fake.png' ) );
	w.alts( d )[1].value = 'typed';
	fire( w.alts( d )[1], 'input' );
	fire( w.button( d, SAVE ), 'click' );
	w.answer( 400, { error : 'the image was refused: image_type', code : 'image_type' } );

	check( 'a file the server refuses is said at the file field, in the words of the reason - and no alt text is sent after it', byClass( d, 'nino-admin-field-error' ).length === 1 && byClass( d, 'nino-admin-field-error' )[0].textContent === text('/_admin/error/image_type') && w.tree() === '["images/list","images/upload"]' );
	check( '...the record is changed still: the file is chosen and what was typed stays; the row and the picture the slot has are as they were', w.alts( d )[1].value === 'typed' && w.h.dirty.isDirty( [ 'images' ] ) === true
		&& w.panel._find( KEY ) === before && byClass( d, 'nino-admin-status' )[0].dataset.state === 'error' );

	// 413: the size the server takes
	fire( w.button( d, SAVE ), 'click' );
	w.answer( 413, { error : 'too large', code : 'post_too_large', params : [ 8 ] } );
	check( 'a request that is larger than the server accepts says so at the field, with the limit', byClass( d, 'nino-admin-field-error' )[0].textContent.indexOf('8') !== -1 );
}

{
	// The file is written and the alt texts are not: said at the alt text, the row shows the picture that is there
	const w = world();
	w.open();
	fire( w.rows()[3], 'click', { target : w.rows()[3] } );
	const d = w.dialog();
	const uri = w.panel._find( '/template/page-home/service/heating' ).uri;

	await choose( w, d, shotFile( 'a.png' ) );
	w.alts( d )[0].value = 'neu';
	fire( w.alts( d )[0], 'input' );
	fire( w.button( d, SAVE ), 'click' );
	w.answer( 200, { filename : 'x.640x420.webp', url : '/public/images/x.640x420.webp', bytes : 1000, belowTarget : false, source : { width : 640, height : 420 } } );
	w.answer( 500, { error : 'could not save the alt texts' } );

	check( 'a picture that was written and alt texts that were not: the error stands at the alt text, and the row shows the picture that is stored now', byClass( d, 'nino-admin-field-error' ).length === 1 && w.panel._find( uri ).file === 'x.640x420.webp' );
	check( '...what was chosen is the slot\'s picture now: the field is empty, and what was typed stays and is still unsaved', w.fileInput( d ).value === '' && w.alts( d )[0].value === 'neu' && w.h.dirty.isDirty( [ 'images' ] ) === true );
}


// --- Remove image: its own action, asked about, at once ---------------------------------------------------------------

{
	const w = world();
	w.open();
	const row = w.rows().find( el => el.dataset.row === KEY );

	fire( row, 'click', { target : row } );
	const d = w.dialog();
	const asked = w.requests.length;

	fire( w.button( d, REMOVE ), 'click' );
	const question = w.dialog();
	check( 'it asks first, on the record, with the question of the level destructive that names the slot - and sends nothing', question !== d && byClass( question, 'nino-admin-dialog-title' )[0].textContent === text('/_admin/images/confirm/remove-title')
		&& byClass( question, 'nino-admin-dialog-message' ).concat( tag( question, 'p' ) ).some( el => el.textContent.indexOf('Startseite - Titelbild') !== -1 ) && w.requests.length === asked );
	fire( tag( question, 'button' )[0], 'click' );
	check( 'declining sends nothing and leaves the record as it is', w.requests.length === asked && w.dialog() === d && w.panel._find( KEY ).hasImage === true );

	fire( w.button( d, REMOVE ), 'click' );
	fire( tag( w.dialog(), 'button' )[1], 'click' );
	check( 'confirming posts images/remove for the slot, and for nothing else', w.requests.length === asked + 1 && w.requests[asked].action === 'images/remove' && JSON.stringify( w.requests[asked].payload ) === JSON.stringify( { uri : KEY } ) );
	w.answer( 200, { filename : null } );
	check( 'once the server says so the row has no picture - the sign of none in its cell - and the other panels are told', w.panel._find( KEY ).hasImage === false
		&& byClass( w.rows().find( el => el.dataset.row === KEY ), 'nino-admin-thumb' )[0].classList.contains('is-none') === true && w.h.admin.stamp('slots') === 1 );
	check( 'the record stays open, as the entry is now: the empty place in its frame, no button to remove what is not there, the row of the directory fresh', w.dialog() === d && byClass( d, 'nino-admin-imageframe-frame' )[0].classList.contains('is-none') === true
		&& w.button( d, REMOVE ).hidden === true && w.rows().find( el => el.dataset.row === KEY ).classList.contains('is-fresh') === true && w.h.hash() === '#images/images/%2Ftemplate%2Fpage-home%2Fhero%2Fbackground' );
	check( '...and a file can be chosen for it at once', w.fileInput( d ) !== undefined && w.h.dirty.isDirty( [ 'images' ] ) === false );

	// With input that is typed and not saved: the question says it is lost
	const v = world();
	v.open();
	const rv = v.rows().find( el => el.dataset.row === KEY );
	fire( rv, 'click', { target : rv } );
	const e = v.dialog();
	v.alts( e )[1].value = 'typed';
	fire( v.alts( e )[1], 'input' );
	fire( v.button( e, REMOVE ), 'click' );
	check( 'where input is typed and not saved the question says that it is lost with the picture it was typed for - nothing is lost without being said', tag( v.dialog(), 'p' ).concat( byClass( v.dialog(), 'nino-admin-dialog-message' ) ).some( el => el.textContent.indexOf( text('/_admin/images/confirm/remove-unsaved') ) !== -1 ) );

	// A removal that failed says so where the person looks
	const f = world();
	f.open();
	const rf = f.rows().find( el => el.dataset.row === KEY );
	fire( rf, 'click', { target : rf } );
	const g = f.dialog();
	fire( f.button( g, REMOVE ), 'click' );
	fire( tag( f.dialog(), 'button' )[1], 'click' );
	f.answer( 500, { error : 'could not save the slot' } );
	check( 'a removal that failed leaves the record open and says it in the line of the bar - the slot keeps its picture', f.dialog() === g && byClass( g, 'nino-admin-status' )[0].textContent !== '' && f.panel._find( KEY ).hasImage === true );
}


// --- Save and continue ------------------------------------------------------------------------------------------------------

{
	const w = world();
	w.open();
	fire( w.rows()[2], 'click', { target : w.rows()[2] } );
	const d = w.dialog();

	w.alts( d )[1].value = 'weiter';
	fire( w.alts( d )[1], 'input' );
	fire( w.button( d, '/_admin/common/label/save-next' ), 'click' );
	w.answer( 200, { alt : { de_DE : 'Ein Haus', en_US : 'weiter' } } );
	check( 'Save and continue saves and goes on to the next slot of the rows: the title and the address are the next one\'s', byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Leistung Heizung' && w.h.hash() === '#images/images/%2Ftemplate%2Fpage-home%2Fservice%2Fheating' );
}


// --- the address: a round trip over the real router ----------------------------------------------------------------------

{
	const w = world();
	const keys = slotList().map( slot => slot.uri );

	check( 'every key of the slots - with a slash, a percent sign, an at sign - is ONE part of the address and is read back as the same key', keys.every( key => {
		const read = w.h.router.current.call( { _x : 0 } ) && ( w.h.box.location.hash = w.h.router.href( 'images', [ key ] ), w.h.router.current() );
		return read.panel === 'images' && read.parts.length === 1 && read.parts[0] === key && read.parts.words[0] === false && w.h.router.href( 'images', [ key ] ).split('/').length === 3;
	} ) );
	w.h.box.location.hash = '';
}

{
	// A reload with an address that names a slot
	const w = world( '#images/images/%2Fodd%2Fa%25b%40c' );

	w.panel.showCurrent();
	check( 'a reload with an address draws the directory first and opens the record the address names once the list has come', w.dialog() === null );
	w.answer( 200, { slots : slotList(), locales : [ 'de_DE', 'en_US' ] } );
	const d = w.dialog();
	check( '...the record is the one the address names, with the markup of its name as the letters it is, and it has no entry of its own in the history', d !== null && byClass( d, 'nino-admin-dialog-title' )[0].textContent === MARKUP+ ' Name' && tag( d, 'img' ).filter( el => el.src.indexOf('x') === 0 ).length === 0 && markupWrites.length === 0 );
	check( '...and the alt text of the slot is in its field as text', w.alts( d )[0].value === MARKUP );

	// Back closes it and does not read the list again
	const asked = w.requests.length;
	w.h.box.location.hash = '#images';
	w.h.router.arrive();
	w.panel.showCurrent();
	check( 'the address of the directory closes the record, and the list is not read again for that', w.dialog() === null && w.requests.length === asked );

	// An address that names no slot
	const m = world( '#images/images/%2Fnothing' );
	m.panel.showCurrent();
	m.answer( 200, { slots : slotList(), locales : [ 'de_DE', 'en_US' ] } );
	check( 'a slot that is not there is said over the directory and the address is the directory\'s', m.dialog() === null && byClass( m.mount, 'nino-admin-notice' ).length === 1 && m.h.hash() === '#images' );

	// An address with a part that cannot be decoded is written over by the true one
	const t = world( '#images/images/ab%zz' );
	t.panel.showCurrent();
	t.answer( 200, { slots : slotList(), locales : [ 'de_DE', 'en_US' ] } );
	check( 'a part that cannot be decoded is no slot: the directory shows, and the bar says the address of the directory', t.dialog() === null && t.h.hash() === '#images' );
}

{
	// What the person chose in the directory stays while a record is open and after it is closed
	const w = world();
	w.open();
	w.select('images-group').value = 'feature:gallery';
	fire( w.select('images-group'), 'change' );
	w.search().value = 'galerie 1';
	fire( w.search(), 'input' );
	const ids = w.ids().join();
	const row = w.rows()[0];

	fire( row, 'click', { target : row } );
	fire( tag( w.dialog(), 'button' ).find( b => hasClass( b, 'nino-admin-dialog-close' ) ), 'click' );
	w.h.back();
	w.h.router.arrive();
	check( 'after the record is closed the directory is as it was: the group, the search, the rows - and the focus is on the row', w.select('images-group').value === 'feature:gallery' && w.search().value === 'galerie 1' && w.ids().join() === ids && w.rows()[0].focused === true );

	// A template or a slot written since: the panel entered again reads the list quietly
	const asked = w.requests.length;
	w.panel.showCurrent();
	check( 'shown again with nothing written since, it reads nothing', w.requests.length === asked );
	w.h.admin.touch( 'templates' );
	w.panel.showCurrent();
	check( 'shown again after a template was written, it reads the list quietly - what was chosen is still chosen', w.requests.length === asked + 1 && w.select('images-group').value === 'feature:gallery' && w.search().value === 'galerie 1' );
	w.answer( 200, { slots : slotList(), locales : [ 'de_DE', 'en_US' ] } );
	check( '...and it keeps the place it was at', w.ids().join() === ids );
}


// --- a save that wrote the picture and not the alt texts says so, and the entry is the one the server holds --------------------------------

{
	const w = world();
	w.open();
	const row = w.rows().find( el => el.dataset.row === '/logo/dark' );

	fire( row, 'click', { target : row } );
	const d = w.dialog();
	const dark = w.panel._find( '/logo/dark' );

	check( 'a slot with no picture has no button to remove one', w.button( d, REMOVE ).hidden === true && dark.hasImage === false );

	await choose( w, d, shotFile( 'a.png' ), [ 3000, 2000 ] );
	w.alts( d )[0].value = 'neu';
	fire( w.alts( d )[0], 'input' );
	fire( w.button( d, SAVE ), 'click' );
	w.answer( 200, { filename : 'logo/dark.500x100.webp', url : '/public/images/logo/dark.500x100.webp', bytes : 4000, belowTarget : false, source : { width : 3000, height : 2000 } } );
	w.answer( 403, { error : 'not allowed' } );

	const sentence = text('/_admin/images/error/partial').replace( '%s', '(403) not allowed' );
	check( 'the picture is written and the alt text is refused: the sentence says the truth - the image is saved, the alt text is not - at the alt text and in the bar, not "saving failed"',
		byClass( d, 'nino-admin-field-error' ).length === 1 && byClass( d, 'nino-admin-field-error' )[0].textContent.indexOf( sentence.slice( 0, 20 ) ) === 0 && byClass( d, 'nino-admin-status' )[0].textContent === sentence
		&& byClass( d, 'nino-admin-status' )[0].dataset.state === 'error' );
	check( '...the entry shows what the server holds: the new picture in the frame, and the button to remove it - the row had none when the record was opened', w.panel._find( '/logo/dark' ).hasImage === true && w.button( d, REMOVE ).hidden === false
		&& tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' ).length === 1 && tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' )[0].src.indexOf('data:') === -1 );
	check( '...the row of the directory is drawn anew with the picture, and what was typed stays as input that is not saved', w.rows().find( el => el.dataset.row === '/logo/dark' ).classList.contains('is-fresh') === true
		&& byClass( w.rows().find( el => el.dataset.row === '/logo/dark' ), 'nino-admin-thumb' )[0].classList.contains('is-none') === false && w.alts( d )[0].value === 'neu' && w.h.dirty.isDirty( [ 'images' ] ) === true );
}


// --- an answer that never came --------------------------------------------------------------------------------------------------------------

{
	const w = world();
	w.open();
	const row = w.rows().find( el => el.dataset.row === KEY );

	fire( row, 'click', { target : row } );
	const d = w.dialog();

	await choose( w, d, shotFile( 'a.png' ), [ 3000, 2000 ] );
	fire( w.button( d, SAVE ), 'click' );
	const asked = w.requests.length;
	w.offline();
	check( 'an answer of a save that is lost says that it is not known what was stored - and the list is read again to find out', w.requests.length === asked + 1 && w.requests[asked].action === 'images/list' );

	const stored = slotList();
	stored.find( slot => slot.uri === KEY ).file = 'template/page-home/hero/background.1920x1080.webp';
	w.answer( 200, { slots : stored, locales : [ 'de_DE', 'en_US' ] } );
	check( 'the sentence says that it was not known - not "nothing happened" - at the field and in the bar', byClass( d, 'nino-admin-field-error' )[0].textContent === text('/_admin/images/error/unknown') && byClass( d, 'nino-admin-status' )[0].textContent === text('/_admin/images/error/unknown') );
	check( 'the server did write: the entry shows the new picture of the list - not the preview of the browser - and offers to remove it; the sentence says it was not known', tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' ).length === 1 && tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' )[0].src.indexOf('data:') === -1 && w.button( d, REMOVE ).hidden === false );
	check( '...and the entry shows what the server holds - the file of the list - with no file left in the field, and nothing counted as changed', byClass( d, 'nino-admin-imageframe-caption' )[0].textContent.indexOf('background.1920x1080.webp') !== -1 && w.h.dirty.isDirty( [ 'images' ] ) === false );

	// The list cannot be read either
	const v = world();
	v.open();
	const rv = v.rows().find( el => el.dataset.row === KEY );
	fire( rv, 'click', { target : rv } );
	const e = v.dialog();
	await choose( v, e, shotFile( 'a.png' ), [ 3000, 2000 ] );
	fire( v.button( e, SAVE ), 'click' );
	v.offline();
	v.offline();
	check( 'where the list cannot be read either it says that, and to reload the page', byClass( e, 'nino-admin-status' )[0].textContent === text('/_admin/images/error/unknown-load') );
}


// --- a file that was chosen and a second one that is refused: the choice is taken back ---------------------------------------------------------

{
	const w = world();
	w.open();
	fire( w.rows()[3], 'click', { target : w.rows()[3] } );
	const d = w.dialog();

	await choose( w, d, shotFile( 'good.png' ), [ 3000, 2000 ] );
	check( 'a file that is chosen is unsaved input', w.h.dirty.isDirty( [ 'images' ] ) === true );

	w.h.box.createImageBitmap = () => Promise.resolve( { width : 6000, height : 5000, close() {} } );
	w.fileInput( d ).files = [ shotFile( 'huge.png' ) ];
	fire( w.fileInput( d ), 'change' );
	await new Promise( resolve => setTimeout( resolve, 0 ) );
	check( 'a second file that the browser refuses (too many pixels) takes the first one back with it: the field is empty, the frame shows the stored picture and nothing is counted as changed',
		byClass( d, 'nino-admin-field-error' ).length === 1 && byClass( d, 'nino-admin-filefield-name' )[0].dataset.state === 'none' && w.h.dirty.isDirty( [ 'images' ] ) === false && tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' )[0].src.indexOf('data:') === -1 );
}


// --- a file the browser cannot decode, and a file that is taken back ---------------------------------------------------------------------------

{
	const w = world();
	w.open();
	fire( w.rows()[3], 'click', { target : w.rows()[3] } );
	const d = w.dialog();
	const input = w.fileInput( d );

	input.files = [ shotFile( 'fake.jpg', 46, 'image/jpeg' ) ];
	fire( input, 'change' );
	await new Promise( resolve => setTimeout( resolve, 0 ) );
	w.readers[w.readers.length - 1].result = 'data:image/jpeg;base64,dGhpcyBpcyBubyBpbWFnZQ==';
	w.readers[w.readers.length - 1].onload();
	const probe = w.probes.filter( el => typeof el.onload === 'function' ).pop();

	probe.onerror();
	check( 'a file the browser cannot read as a picture - a text with the ending .jpg - is refused at once, at the file field, with the words of the refusal of the server, and nothing is taken',
		byClass( d, 'nino-admin-field-error' ).length === 1 && byClass( d, 'nino-admin-field-error' )[0].textContent === text('/_admin/error/image_type') && byClass( d, 'nino-admin-filefield-name' )[0].dataset.state === 'none' );
	check( '...and nothing is changed: the shell would not ask before the record were closed', w.h.dirty.isDirty( [ 'images' ] ) === false && byClass( d, 'nino-admin-imageframe-caption' ).every( c => c.textContent.indexOf('fake.jpg') === -1 ) );

	// a file the browser cannot check for its pixels: refused as it is by the limits it knows
	w.h.box.createImageBitmap = () => Promise.resolve( { width : 6000, height : 5000, close() {} } );
	input.files = [ shotFile( 'huge.png' ) ];
	fire( input, 'change' );
	await new Promise( resolve => setTimeout( resolve, 0 ) );
	check( 'a file with too many pixels is refused at the field and taken back: nothing is changed', byClass( d, 'nino-admin-field-error' ).length === 1 && w.h.dirty.isDirty( [ 'images' ] ) === false && w.readers.length === 1 );
}

{
	// Two files chosen one after the other: the preview of the one that was read last is the one that stays
	const w = world();
	w.open();
	fire( w.rows()[3], 'click', { target : w.rows()[3] } );
	const d = w.dialog();
	const input = w.fileInput( d );

	input.files = [ shotFile( 'first.png' ) ];
	fire( input, 'change' );
	await new Promise( resolve => setTimeout( resolve, 0 ) );
	input.files = [ shotFile( 'second.png' ) ];
	fire( input, 'change' );
	await new Promise( resolve => setTimeout( resolve, 0 ) );

	const [ one, two ] = w.readers;
	two.result = 'data:image/png;base64,SECOND';
	two.onload();
	w.probes.filter( el => typeof el.onload === 'function' ).pop().onload();
	one.result = 'data:image/png;base64,FIRST';
	one.onload();
	const late = w.probes.filter( el => typeof el.onload === 'function' ).pop();
	late.naturalWidth = 10;
	late.naturalHeight = 10;
	late.onload();
	check( 'a preview that was read late, for a file that is no longer the choice, does not draw over the choice: the frame shows the second file', byClass( d, 'nino-admin-imageframe-caption' )[0].textContent.indexOf('second.png') !== -1
		&& tag( byClass( d, 'nino-admin-imageframe' )[0], 'img' )[0].src.indexOf('SECOND') !== -1 );
}

{
	// An alt text of exactly 250 characters is sent; one of 251 is not
	const w = world();
	w.open();
	fire( w.rows()[3], 'click', { target : w.rows()[3] } );
	const d = w.dialog();
	const asked = w.requests.length;

	w.alts( d )[0].value = 'x'.repeat( 250 );
	fire( w.alts( d )[0], 'input' );
	fire( w.button( d, SAVE ), 'click' );
	check( 'an alt text of exactly 250 characters is the longest there is and goes to the server', w.requests.length === asked + 1 && w.requests[asked].action === 'images/alt' && w.requests[asked].payload.alt.de_DE.length === 250 );
}


// --- the rights and the token: the panel calls the four actions the server has, and no other ---------------------------------

{
	const w = world();
	w.open();
	fire( w.rows()[2], 'click', { target : w.rows()[2] } );
	const d = w.dialog();

	await choose( w, d, shotFile( 'a.png' ) );
	w.alts( d )[0].value = 'neu';
	fire( w.alts( d )[0], 'input' );
	fire( w.button( d, SAVE ), 'click' );
	w.answer( 200, { filename : 'a.webp', url : '/a.webp', bytes : 1, belowTarget : false, source : { width : 1920, height : 1080 } } );
	w.answer( 200, { alt : { de_DE : 'neu' } } );
	fire( w.button( d, REMOVE ), 'click' );
	fire( tag( w.dialog(), 'button' )[1], 'click' );
	w.answer( 200, { filename : null } );

	check( 'the panel calls images/list, images/upload, images/alt and images/remove - the four actions the server has - and nothing else', Array.from( new Set( w.requests.map( r => r.action ) ) ).sort().join() === 'images/alt,images/list,images/remove,images/upload' );
	check( 'every call goes to the one door of the workbench, as a POST', w.requests.every( r => r.method === 'POST' && r.uri.endsWith('/_admin/') ) );
}

{
	// Whatever the server or an editor wrote reaches the page as text
	const imagesSource = source('_admin/Nino/Modules/Images/assets/admin.js');
	check( 'nothing the panel is told is written as markup - innerHTML is only ever emptied', ( imagesSource.match( /innerHTML[^\n]*/g ) || [] ).every( use => /^innerHTML = '';$/.test( use ) ) );
	check( 'the panel calls no confirm or alert of the browser, and sends no request by hand', /(^|[^.\w])(confirm|alert)\(/.test( imagesSource ) === false && imagesSource.indexOf('XMLHttpRequest') === -1 && imagesSource.indexOf('fetch(') === -1 );
}


// --- every key the three scripts ask for is a fill in both languages ----------------------------

const asked = new Set();
[ 'admin.js', 'slots.js', 'favicon.js' ].forEach( function( name ) {
	for( const found of source('_admin/Nino/Modules/Images/assets/'+ name ).matchAll( /(?:getText|word)\(\s*'(\/_admin\/[a-z0-9\/_-]+)'\s*\)/g ) )
		asked.add( found[1] );
} );
const workbenchDe = fills('_admin/text/de_DE.php');
const missing = Array.from( asked ).filter( function( key ) { return ( moduleEn[key] ?? workbench[key] ) === undefined || ( moduleDe[key] ?? workbenchDe[key] ) === undefined } );
check( 'the scripts ask for '+ asked.size+ ' keys, and each has words in English and in German'+ ( missing.length === 0 ? '' : ' - not '+ missing.join(', ') ), asked.size > 60 && missing.length === 0 );



// --- the Image Slots tab ------------------------------------------------------------------------

console.log('\nAdmin Image Slots');

const tab = page( [ 'slots-list', 'slots-form' ], 'slots', [ '_admin/Nino/Modules/Images/assets/slots.js' ] );
const slots = tab.Nino.admin.slots;
const catalogue = [
	{ uri : '/logo', label : 'Logo', width : 500, height : 100, hasImage : true, usage : { templates : [], pages : [ { httpUri : '/', name : 'Home' } ] } },
	{ uri : '/logo/dark', label : 'Logo dark', width : 500, height : 100, hasImage : false, usage : { templates : [ 'page-x' ], pages : [] } },
	{ uri : '/template/page-home/hero/background', label : 'Hero', width : 1920, height : 1080, hasImage : true, usage : { templates : [], pages : [ { httpUri : '/', name : 'Home' } ] } },
];
const list = tab.levels['slots-list'];
const form2 = tab.levels['slots-form'];

slots.init();
check( 'the tab asks the server for the slots when it is first shown', tab.requests.length === 1 && tab.requests[0].action === 'slots/list' );
tab.answer( 200, { slots : catalogue } );
check( '...and shows the list when no address names another view', slots._ready === true && list.classList.contains('admin-hidden') === false && tab.routed[tab.routed.length - 1] === 'set slots' );

const slotLinks = findAll( list, el => el.tagName === 'A' && has( el, 'nino-admin-row-open' ) );
check( 'every slot is a row of the list block - a link with its address, its uri as one part - and no row is a button to nowhere', slotLinks.map( l => l.href ).join() === '#images/slots/%2Flogo,#images/slots/%2Flogo%2Fdark,#images/slots/%2Ftemplate%2Fpage-home%2Fhero%2Fbackground'
	&& findAll( list, el => has( el, 'nino-admin-list-row' ) ).length === 3 && findAll( list, el => el.tagName === 'BUTTON' && has( el, 'nino-admin-row-open' ) ).length === 0 );
check( '...two lines each: the label over the uri and the size', findAll( list, el => el.tagName === 'SMALL' ).map( s => s.textContent.indexOf( ' × ' ) !== -1 ).every( Boolean ) && findAll( list, el => el.tagName === 'SMALL' ).length === 3 );
const pills = findAll( list, el => has( el, 'nino-admin-badge' ) );
check( 'a slot that no page shows has a badge that warns, in words; one without a picture says it quietly', pills.length === 1 && pills[0].dataset.state === 'warn' );
check( '...the templates that name it are on its second line', findAll( list, el => el.tagName === 'SMALL' )[1].textContent.indexOf('page-x') !== -1 );
check( 'the list has its bar: the scan beside "New image slot", which is the one main button at the far right', byClass( list, 'nino-admin-actionbar' ).length === 1
	&& buttonsOf( byClass( list, 'nino-admin-actionbar' )[0] ).length === 2 && has( buttonsOf( byClass( list, 'nino-admin-actionbar' )[0] )[1], 'nino-admin-btn-primary' ) && has( buttonsOf( byClass( list, 'nino-admin-actionbar' )[0] )[0], 'nino-admin-btn-secondary' ) );

tab.routed.length = 0;
fire( slotLinks[0], 'click' );
check( 'opening a slot is a step Back returns to: it writes its address, shows the form and moves the focus into it', tab.routed.join() === 'go slots//logo,set slots//logo' && form2.classList.contains('admin-hidden') === false
	&& list.classList.contains('admin-hidden') === true && tab.focused() !== null );

const edit = findAll( form2, el => el.tagName === 'FORM' )[0];
const inputs = function() { return Object.fromEntries( [ 'uri', 'label', 'width', 'height' ].map( name => [ name, findAll( form2, el => el.id === 'slots-form-'+ name )[0] ] ) ) };
check( 'the form is novalidate: what is wrong is said by the panel, at the field', edit.noValidate === true );
check( 'the fields are blocks - a label with a star and aria-required each, the help behind a (?) and no line of hint under them', findAll( edit, el => has( el, 'nino-admin-help-button' ) ).length === 4
	&& findAll( edit, el => has( el, 'nino-admin-required' ) ).length === 4 && Object.values( inputs() ).every( input => input.getAttribute('aria-required') === 'true' ) && findAll( edit, el => el.tagName === 'SMALL' ).length === 0 );
check( 'the uri of a slot that exists is fixed: it is what the templates write', inputs().uri.disabled === true && inputs().uri.value === '/logo' && inputs().width.value === '500' );
const bar = byClass( form2, 'nino-admin-actionbar' )[0];
check( 'one bar: "Delete" at its left, the state, and one main button - the submit - at the far right', bar.children[0].tagName === 'BUTTON' && has( bar.children[0], 'nino-admin-btn-danger' )
	&& buttonsOf( bar ).filter( b => has( b, 'nino-admin-btn-primary' ) ).length === 1 && bar.children[bar.children.length - 1].type === 'submit' && byClass( bar, 'nino-admin-actionbar-state' ).length === 1 );
check( 'one back link, in the context bar, with the name of the slot as its heading', byClass( form2, 'nino-admin-back-link' ).length === 1 && findAll( form2, el => el.tagName === 'H3' )[0].textContent === 'Logo' );

// Saving: refused at the fields first, nothing sent
const submit = function() { fire( edit, 'submit' ) };
const line = function() { return findAll( form2, el => el.id === 'slots-form-msg' )[0] };
tab.requests.length = 0;
inputs().label.value = '  ';
inputs().width.value = '0';
inputs().height.value = 'abc';
submit();
check( 'a form that is not right is refused at its fields: one sentence under each, the field invalid, nothing sent', tab.requests.length === 0 && errorsOf( form2 ).length === 3
	&& [ 'label', 'width', 'height' ].every( name => inputs()[name].getAttribute('aria-invalid') === 'true' ) && inputs().uri.getAttribute('aria-invalid') === null );
check( '...the line of the bar says it as an alert, and the focus is on the first field that is wrong', line().dataset.state === 'error' && line().attributes.role === 'alert' && tab.focused() === inputs().label );

inputs().label.value = 'Logo';
inputs().width.value = '640';
inputs().height.value = '120';
submit();
check( 'a form that is right is sent for the slot - the values trimmed - and the sentences are gone', tab.requests.length === 1 && tab.requests[0].action === 'slots/save'
	&& JSON.stringify( tab.requests[0].payload ) === JSON.stringify( { uri : '/logo', label : 'Logo', width : '640', height : '120' } ) && errorsOf( form2 ).length === 0 );
tab.answer( 400, { error : 'a slot needs a width and a height', code : 'invalid_value', field : 'width' } );
check( 'a refusal that names a field is said at that field, and in the line', errorsOf( form2 ).length === 1 && inputs().width.getAttribute('aria-invalid') === 'true' && line().dataset.state === 'error' );

submit();
tab.answer( 200, {} );
check( 'a save that went through reads the list again and tells the other panels', tab.requests[tab.requests.length - 1].action === 'slots/list' && tab.touched.join() === 'slots' );
tab.answer( 200, { slots : catalogue.map( s => s.uri === '/logo' ? Object.assign( {}, s, { width : 640, height : 120 } ) : s ) } );
check( '...and the form stays: no jump to the list, one toast that says it and an empty line', form2.classList.contains('admin-hidden') === false && tab.toasts().join() === text('/_admin/common/msg/saved') && line().textContent === '' );
check( '...the list under it is drawn again, for Back', findAll( list, el => el.tagName === 'SMALL' )[0].textContent.indexOf('640 × 120') !== -1 );

// Delete
tab.requests.length = 0;
fire( bar.children[0], 'click' );
check( 'Delete asks first - the dialog is the design system\'s, it opens on Cancel and names the slot - and a Cancel sends nothing', tab.dialogs.length === 1 && findAll( tab.dialogs[0], el => el.id === 'nino-admin-choice-text' )[0].textContent.indexOf('“Logo”') !== -1 );
tab.ask( false );
check( '...', tab.requests.length === 0 );
fire( bar.children[0], 'click' );
tab.ask( true );
check( 'a confirmed one sends the delete for the slot', tab.requests.length === 1 && tab.requests[0].action === 'slots/delete' && tab.requests[0].payload.uri === '/logo' );
tab.answer( 403, null );
check( 'a refusal is said in the line of the bar and the form stays', line().dataset.state === 'error' && form2.classList.contains('admin-hidden') === false );
fire( bar.children[0], 'click' );
tab.ask( true );
tab.touched.length = 0;
tab.answer( 200, {} );
tab.answer( 200, { slots : catalogue.slice( 1 ) } );
check( 'a delete that went through shows the list again, tells the other panels and says it by a toast', list.classList.contains('admin-hidden') === false && form2.classList.contains('admin-hidden') === true && tab.touched.join() === 'slots'
	&& tab.toasts().join() === text('/_admin/common/msg/deleted') && findAll( list, el => el.tagName === 'STRONG' ).length === 2 );

// A new slot
slots._slots = catalogue;
tab.routed.length = 0;
const add = buttonsOf( byClass( list, 'nino-admin-actionbar' )[0] ).find( b => has( b, 'nino-admin-btn-primary' ) );
fire( add, 'click' );
check( 'New image slot opens an empty form at the address #images/slots/@new', tab.routed.join() === 'go slots/@new,set slots/@new' && inputs().uri.disabled === false && inputs().uri.value === '' && tab.focused() === inputs().uri );
const bar2 = byClass( form2, 'nino-admin-actionbar' )[0];
check( '...with no Delete - the bar holds the state and the one main button, worded as a view that creates' , has( bar2.children[0], 'nino-admin-badge' ) === true && bar2.children[bar2.children.length - 1].textContent === text('/_admin/slots/label/create') );

tab.requests.length = 0;
const edit2 = findAll( form2, el => el.tagName === 'FORM' )[0];
inputs().uri.value = 'logo';
inputs().label.value = 'X';
inputs().width.value = '10';
inputs().height.value = '10';
fire( edit2, 'submit' );
check( 'a uri that is no uri is refused at the field before anything is sent', tab.requests.length === 0 && errorsOf( form2 ).length === 1 && tab.focused() === inputs().uri );
inputs().uri.value = '/logo/dark';
fire( edit2, 'submit' );
check( 'a uri a slot holds already is refused at the field as taken', tab.requests.length === 0 && errorsOf( form2 ).length === 1 );
inputs().uri.value = '/new-slot';
fire( edit2, 'submit' );
check( 'a good one goes to slots/create', tab.requests.length === 1 && tab.requests[0].action === 'slots/create' && tab.requests[0].payload.uri === '/new-slot' );
tab.answer( 409, { error : 'slot already exists' } );
check( 'a 409 is said at the uri', errorsOf( form2 ).length === 1 && inputs().uri.getAttribute('aria-invalid') === 'true' );
fire( edit2, 'submit' );
tab.answer( 200, {} );
tab.routed.length = 0;
tab.answer( 200, { slots : catalogue.concat( [ { uri : '/new-slot', label : 'X', width : 10, height : 10, hasImage : false, usage : { templates : [], pages : [] } } ] ) } );
check( 'a slot that was made is the one on the form from then on: its uri fixed, Delete in the bar, its address written over the one of the new form', inputs().uri.disabled === true && inputs().uri.value === '/new-slot'
	&& tab.routed.indexOf( 'set slots//new-slot' ) !== -1 && has( byClass( form2, 'nino-admin-actionbar' )[0].children[0], 'nino-admin-btn-danger' ) && slots._isNew === false );

// The address
tab.hash = { panel : 'slots', parts : [] };
slots.showCurrent();
check( 'an address that names the list shows it', list.classList.contains('admin-hidden') === false && form2.classList.contains('admin-hidden') === true );
tab.hash = { panel : 'slots', parts : [ '/logo/dark' ] };
slots.showCurrent();
check( 'an address of a uri as one part opens that slot', form2.classList.contains('admin-hidden') === false && slots._current.uri === '/logo/dark' );
tab.hash = { panel : 'slots', parts : codec.parts( { key : '@new' } ) };
slots.showCurrent();
check( '...%40new is the key @new, which is no slot and no form that makes one: the list', slots._isNew === false && form2.classList.contains('admin-hidden') === true );
tab.hash = { panel : 'slots', parts : codec.parts( { key : '@scan' } ) };
slots.showCurrent();
check( '...and %40scan is no scan', form2.classList.contains('admin-hidden') === true && slots._scanning !== true );
tab.hash = { panel : 'slots', parts : codec.parts( '@new' ) };
slots.showCurrent();
check( '...@new the form of a new one', slots._isNew === true && form2.classList.contains('admin-hidden') === false );
tab.hash = { panel : 'slots', parts : [ 'nope' ] };
slots.showCurrent();
check( '...and a slot there is none of is the list', form2.classList.contains('admin-hidden') === true );
check( 'the slot a person types into a new form and leaves asks first, as every form does', ( function() {
	tab.hash = { panel : 'slots', parts : codec.parts( '@new' ) };
	slots.showCurrent();
	tab.registry._entries.slots.isDirty = function() { return true };
	tab.asked.length = 0;
	tab.hash = { panel : 'slots', parts : [] };
	slots.showCurrent();
	const asked = tab.asked.length === 1 && form2.classList.contains('admin-hidden') === false;
	tab.registry._entries.slots.isDirty = function() { return false };
	return asked;
} )() );
check( 'a slot is named by its uri as one part: the segments of it name none, and a word with an at sign names no slot', slots._slotAt( [ '/logo/dark' ] ).uri === '/logo/dark'
	&& slots._slotAt( [ 'logo', 'dark' ] ) === null && slots._slotAt( [ '@scan' ] ) === null && slots._slotAt( [ '@new' ] ) === null );
tab.registry._entries.slots.isDirty = function() { return false };


// --- the scan -------------------------------------------------------------------------------------

slots._slots = catalogue;
tab.requests.length = 0;
tab.hash = { panel : 'slots', parts : [] };
slots.showCurrent();
const scanButton = buttonsOf( byClass( list, 'nino-admin-actionbar' )[0] ).find( b => has( b, 'nino-admin-btn-secondary' ) );
tab.routed.length = 0;
fire( scanButton, 'click' );
check( 'the scan has an address of its own and opens in the form level, waiting for the server with the focus in the view', tab.routed.join() === 'go slots/@scan,set slots/@scan' && tab.requests[0].action === 'slots/scan' && form2.classList.contains('admin-hidden') === false
	&& findAll( form2, el => el.attributes['aria-busy'] === 'true' ).length === 1 );

const found = [
	{ filename : 'a.png', src : '/images/a.png', suggestedUri : '/a', width : 100, height : 50, files : [ 'page-a.tpl' ] },
	{ filename : '', src : '/template/page-x/cards/one-background', suggestedUri : '/template/page-x/cards/one-background', width : 0, height : 0, files : [ 'page-x.tpl' ] },
	{ filename : 'c.png', src : '/images/c.png', suggestedUri : '/c', width : 80, height : 0, files : [ 'page-c.tpl' ] },
];
tab.answer( 200, { missing : found } );
const cards = findAll( form2, el => el.tagName === 'FIELDSET' );
const sizeOf = function( card ) { return findAll( card, el => el.type === 'number' ) };
check( 'every finding is a card with the fields of a slot, as blocks, in a form that is novalidate and has the one bar', cards.length === 3 && findAll( form2, el => el.tagName === 'FORM' )[0].noValidate === true
	&& byClass( form2, 'nino-admin-actionbar' ).length === 1 && buttonsOf( byClass( form2, 'nino-admin-actionbar' )[0] ).filter( b => has( b, 'nino-admin-btn-primary' ) ).length === 1 );
check( 'a finding that comes with a size asks for none; one without marks its width and its height as required', sizeOf( cards[0] ).every( i => i.required !== true ) && sizeOf( cards[1] ).length === 2 && sizeOf( cards[1] ).every( i => i.required === true && i.getAttribute('aria-required') === 'true' )
	&& sizeOf( cards[2] ).every( i => i.required === true ) );
const starsShown = function( card ) { return findAll( card, el => has( el, 'nino-admin-required' ) && el.hidden !== true ).length };
check( 'a finding without a size has the star after the names of its width and its height, as it has after its uri and label; one with a size has two', starsShown( cards[1] ) === 4 && starsShown( cards[2] ) === 4 && starsShown( cards[0] ) === 2 );
check( 'the scan opens as a view of nothing yet saved: its state badge is the one of a fresh thing', byClass( form2, 'nino-admin-actionbar-state' )[0].dataset.fresh === 'true' );
const tick = function( card ) { return findAll( card, el => el.type === 'checkbox' )[0] };
tick( cards[1] ).checked = true;
fire( tick( cards[1] ), 'change' );
check( 'a finding that is ignored asks for nothing: a slot nobody makes needs no size', sizeOf( cards[1] ).every( i => i.required !== true && i.getAttribute('aria-required') === null ) );
tick( cards[1] ).checked = false;
fire( tick( cards[1] ), 'change' );
check( '...and the stars of its size come back with the need', starsShown( cards[1] ) === 4 );
check( '...and asks again when it is not', sizeOf( cards[1] ).every( i => i.required === true ) );

tab.requests.length = 0;
let outcome = null;
slots._saveScanResults( slots._scanRows, function( ok ) { outcome = ok } );
check( 'a submit makes the finding that is right and sends none of those that are not', tab.requests.length === 1 && tab.requests[0].action === 'slots/create' && tab.requests[0].payload.uri === '/a' );
check( 'each finding that is not right is refused at its fields: a sentence under each field, the field invalid, the focus on the first', errorsOf( cards[1] ).length === 2 && errorsOf( cards[2] ).length === 1 && errorsOf( cards[0] ).length === 0
	&& tab.focused() === sizeOf( cards[1] )[0] );
tab.answer( 200, {} );
check( 'the slot made is told to the other panels and the list is read again', tab.touched.slice( -1 )[0] === 'slots' && tab.requests[tab.requests.length - 1].action === 'slots/list' );
tab.answer( 200, { slots : catalogue } );
check( '...the card of the slot that was made is gone, the others stay with their sentences, and the save reports false', outcome === false && findAll( form2, el => el.tagName === 'FIELDSET' ).length === 2 && slots._scanRows.length === 2 );

const left = slots._scanRows.slice();
sizeOf( left[0].field )[0].value = '10';
fire( sizeOf( left[0].field )[0], 'input' );
check( 'what is typed into a finding takes its sentences away', errorsOf( left[0].field ).length === 0 && errorsOf( left[1].field ).length === 1 );
sizeOf( left[0].field )[1].value = '10';
sizeOf( left[1].field )[0].value = '20';
sizeOf( left[1].field )[1].value = '20';
tab.requests.length = 0;
outcome = null;
slots._saveScanResults( slots._scanRows, function( ok ) { outcome = ok } );
check( 'with every finding right they are made one request at a time, the next after the answer to the one before', tab.requests.length === 1 );
tab.answer( 200, {} );
check( '...', tab.requests.length === 2 && outcome === null );
tab.answer( 409, { error : 'exists' } );
check( 'a finding the server refuses stays in the form with its sentence at the uri; the one made leaves it', slots._scanRows.length === 1 && slots._scanRows[0] === left[1] && errorsOf( left[1].field ).length === 1 );
tab.answer( 200, { slots : catalogue } );
check( '...and the save reports false', outcome === false );

// What a template or a file is called is text: a name that is markup draws no element
slots._renderScanForm( [ { filename : '', src : '<img src=x onerror=1>', suggestedUri : '/x', width : 1, height : 1, files : [ '<b>part</b>.tpl' ] } ] );
check( 'the path of a finding and the files it was found in are drawn as text: a name that is markup makes no element', findAll( form2, el => el.tagName === 'IMG' || el.tagName === 'B' ).length === 0
	&& findAll( form2, el => el.tagName === 'LEGEND' )[0].textContent === '<img src=x onerror=1>' && findAll( form2, el => el.tagName === 'P' ).some( p => p.textContent === '<b>part</b>.tpl' ) );
slots._renderScanForm( [ { filename : 'd.png', src : '/images/d.png', suggestedUri : '/d', width : 1, height : 1, files : [ 'page-d.tpl', 'page-e.tpl' ] } ] );
check( 'the files a finding was found in are said, each by its name, in the order the server named them, and nothing else stands in that place', findAll( form2, el => el.tagName === 'P' && el.textContent.indexOf( '.tpl' ) !== -1 ).map( p => p.textContent ).join('|') === 'page-d.tpl, page-e.tpl' );

// All ignored, and nothing found
slots._renderScanForm( found.slice( 0, 1 ) );
tick( findAll( form2, el => el.tagName === 'FIELDSET' )[0] ).checked = true;
outcome = null;
slots._saveScanResults( slots._scanRows, function( ok ) { outcome = ok } );
check( 'findings that are all ignored make nothing: the list is shown and the save is done', outcome === true && list.classList.contains('admin-hidden') === false );
slots._renderScanForm( [] );
check( 'a scan that found nothing is an empty state, and has no form', byClass( form2, 'nino-admin-empty' ).length === 1 && findAll( form2, el => el.tagName === 'FORM' ).length === 0 && slots._scanRows === null );

// The scan view left while it ran
const wait = page( [ 'slots-list', 'slots-form' ], 'slots', [ '_admin/Nino/Modules/Images/assets/slots.js' ] );
wait.Nino.admin.slots._slots = catalogue;
wait.Nino.admin.slots._openScan();
wait.levels['slots-form'].children.length = 0;
wait.levels['slots-form'].appendChild( element('p') );
wait.answer( 200, { missing : found } );
check( 'an answer to a scan whose view was left has nowhere to go: nothing is drawn over what is on screen', findAll( wait.levels['slots-form'], el => el.tagName === 'FORM' ).length === 0 );

// Nothing the tab shows is a sentence of the browser's: its script says no alert and no confirm
const slotsSource = source('_admin/Nino/Modules/Images/assets/slots.js');
check( 'the tab asks nothing with the browser\'s own dialogs', /\b(alert|confirm|prompt)\s*\(/.test( slotsSource.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /Nino\.adminUi\.confirm\(/g, '' ) ) === false );
check( 'nothing the tab is told is written as markup - innerHTML is only ever emptied', ( slotsSource.match( /innerHTML[^\n]*/g ) || [] ).every( function( use ) { return /^innerHTML = '';$/.test( use ) } ) );

check( 'every address the Image Slots tab wrote or drew - the slots, the new slot, the scan - is read back by the router as the same screen and the same parts', codec.written.length > 6 && codec.mismatches.length === 0
	&& codec.written.some( written => written.screen === 'slots' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@new' ) ) && codec.written.some( written => written.screen === 'slots' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@scan' ) )
	&& codec.written.every( written => written.screen === 'slots' && written.parts.length <= 1 ) );


done();

} )();
