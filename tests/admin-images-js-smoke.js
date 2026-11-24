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
 *													text.
 *
 *													The Image Slots tab is the standard directory of the slots
 *													with the record of one slot over it (address
 *													#images/slots/<the uri as ONE part>), a dialog of its own for
 *													a new slot (#images/slots/@new) and the scan of the templates
 *													as a view of its own (#images/slots/@scan). Pinned: the
 *													columns, the state of a slot as the table says it, the choice
 *													of what to show and the search over the label, the uri and
 *													the templates, the record (a record that was not changed
 *													sends no request, what is wrong is said at the field, what
 *													the server refuses stands at the field it names, a deletion
 *													asks first), the dialog of a new slot, the words @new and
 *													@scan told from a key, the requests, the addresses and that
 *													everything a person or the server wrote is shown as text.
 *													Nothing here compares a sentence: the checks read the build -
 *													which block drew a part, where it stands, what was sent - and
 *													the keys the scripts ask for are real ones in both languages.
 *
 *	Usage: node tests/admin-images-js-smoke.js
 */

'use strict';

const { check, done, source, fills, load, element, findAll, fire, shellRouter, adminPage, markupWrites } = require( './harness.js' );

// What the Image Slots tab writes into the address goes through the router's own codec as well: every state written is read back
const codec = shellRouter();

const moduleEn	= fills('_admin/Nino/Modules/Images/text/en_US.php');
const moduleDe	= fills('_admin/Nino/Modules/Images/text/de_DE.php');
const workbench	= fills('_admin/text/en_US.php');

const text = function( key ) { return moduleEn[key] ?? workbench[key] ?? '' };

const has = function( el, name ) { return el.classList.contains( name ) };
const byClass = function( root, name ) { return findAll( root, function( el ) { return has( el, name ) } ) };
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

// What the server answers for the slots: some with a picture, some without, some that no page includes - with and without the templates that name them -, and one whose
// name, key and template are markup and quotes
const HOSTILE = '<img src=x onerror=alert(1)> "quoted"';
const ODD = '/odd/a%b@c';
const LOGO = '/logo';
const slotCatalogue = () => [
	{ uri : LOGO, label : 'Logo', width : 500, height : 100, hasImage : true, usage : { templates : [], pages : [ { httpUri : '/', name : 'Home' } ] } },
	{ uri : '/logo/dark', label : 'Logo dark', width : 500, height : 100, hasImage : false, usage : { templates : [ 'page-x', 'frame-header' ], pages : [] } },
	{ uri : '/template/page-home/hero/background', label : 'Hero', width : 1920, height : 1080, hasImage : true, usage : { templates : [], pages : [ { httpUri : '/', name : 'Home' } ] } },
	{ uri : '/template/page-about/team/photo', label : 'Team photo', width : 640, height : 480, hasImage : true, usage : { templates : [], pages : [] } },
	{ uri : '/template/page-lose/hero/image', label : 'Lose', width : 640, height : 360, hasImage : false, usage : { templates : [], pages : [ { httpUri : '/lose', name : 'Lose' } ] } },
	{ uri : ODD, label : HOSTILE, width : 10, height : 20, hasImage : false, usage : { templates : [ HOSTILE ], pages : [] } },
];

// The Image Slots tab over the real shell, as the Images panel is above: the router and the registry of unsaved input of the page, the levels of the pane, the
// requests the tab sends, kept
function slotsWorld( hash ) {

	const h = adminPage();
	const list = element('div');
	const form = element('div');
	const requests = [];

	list.id = 'slots-list';
	form.id = 'slots-form';
	form.classList.add('admin-hidden');
	h.page.appendChild( list );
	h.page.appendChild( form );
	h.show('slots');
	h.router.start();
	h.router.set( 'slots', [] );
	h.Nino.content.getText = text;
	h.Nino.http = { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( data.data ), csrf : data._csrf, callback : callback } );
	} };
	h.box.KeyboardEvent = function( type, init ) { Object.assign( this, { type : type }, init ) };

	// Every address the tab writes or links to is read back by the router as the same screen and the same parts
	[ 'set', 'go', 'href' ].forEach( name => {
		const real = h.router[name];
		h.router[name] = function( screen, parts ) { codec.check( screen, parts ); return real.call( this, screen, parts ) };
	} );

	if( hash !== undefined )
		h.box.location.hash = hash;

	load( '_admin/Nino/Modules/Images/assets/slots.js', h.context );

	const panel = h.admin.slots;
	const answer = ( status, body ) => { const request = requests[requests.length - 1]; request.callback( { status : status, responseJSON : body } ); return request };
	const open = slots => { panel.showCurrent(); answer( 200, { slots : slots ?? slotCatalogue() } ) };
	const rows = () => findAll( list, el => el.tagName === 'TR' && el.dataset.row !== undefined );
	const dialog = () => h.dialogs.filter( d => d.open ).pop() ?? null;

	return { h, panel, list, form, requests, answer, open, rows, dialog,
		ids : () => rows().map( el => el.dataset.row ),
		search : () => tag( list, 'input' ).find( el => el.type === 'search' ),
		show : () => tag( list, 'select' ).find( el => el.id === 'slots-show' ),
		cells : id => rows().find( el => el.dataset.row === id ).children.filter( el => el.tagName === 'TD' ),
		bar : () => byClass( list, 'nino-admin-actionbar' ).filter( el => hasClass( el, 'nino-admin-list-actions' ) )[0],
		button : ( d, key ) => tag( d, 'button' ).find( b => b.textContent === text( key ) || b.attributes['aria-label'] === text( key ) ),
		// The fields of the record: the label, the width and the height; of the dialog of a new slot, the uri as well
		field : ( d, key ) => tag( d, 'input' ).find( el => el.dataset.key === key || ( key === 'label' && el.id === 'slots-form-label' ) ),
		stateOf : d => byClass( d, 'nino-admin-actionbar-state' )[0],
		ask : yes => { const d = dialog(); fire( tag( d, 'button' )[yes === true ? 1 : 0], 'click' ); return d },
		tree : () => requests.map( r => r.action ).join(),
		toasts : () => byClass( h.body, 'nino-admin-toast-text' ).map( el => el.textContent ),
		focused : () => findAll( h.page, el => el.focused === true )[0] ?? null,
		step : address => { h.box.location.hash = address; h.router.arrive(); panel.showCurrent() },
	};
}

const SAVE_SLOT = '/_admin/common/label/save';
const CREATE_SLOT = '/_admin/slots/label/create';
const DELETE_SLOT = '/_admin/slots/label/delete';
const NEW_SLOT = '/_admin/slots/label/new';
const SCAN_SLOT = '/_admin/slots/label/scan';

{
	// --- the directory ----------------------------------------------------------------------------------

	const w = slotsWorld();

	check( 'the tab attaches under its nav uri with init and showCurrent', typeof w.panel === 'object' && typeof w.panel.init === 'function' && typeof w.panel.showCurrent === 'function' );

	w.panel.showCurrent();
	check( 'showing it asks the server for the slots in one request, and for nothing else', w.requests.length === 1 && w.requests[0].action === 'slots/list' && JSON.stringify( w.requests[0].payload ) === '{}' );
	check( 'while it loads the sentence stands in place of the table, and says it is busy', byClass( w.list, 'nino-admin-loading' )[0].textContent === text('/_admin/slots/loading') && byClass( w.list, 'nino-admin-directory-body' )[0].attributes['aria-busy'] === 'true' );

	w.answer( 200, { slots : slotCatalogue() } );

	check( 'every slot is a row of the standard directory, in the order the server gave', w.ids().join() === slotCatalogue().map( slot => slot.uri ).join() && byClass( w.list, 'nino-admin-directory' ).length === 1 );
	check( 'the list is the one on show, the level of the scan is not', w.list.classList.contains('admin-hidden') === false && w.form.classList.contains('admin-hidden') === true );

	const heads = findAll( w.list, el => el.tagName === 'TH' && el.dataset.key !== undefined ).map( el => el.dataset.key );
	check( 'the columns are the name, the target size and the state - no picture, nothing else', heads.join() === 'label,size,stateText' );
	check( 'there is no box to choose rows by: nothing is done to several slots at once', tag( w.list, 'input' ).filter( el => el.type === 'checkbox' ).length === 0 );
	check( 'there is no category: the one select is the choice of what to show', tag( w.list, 'select' ).filter( el => hasClass( el, 'nino-admin-filterbar-select' ) && hasClass( el, 'nino-admin-table-sortby' ) === false ).length === 1 && w.show().id === 'slots-show' );

	// The name: the label strong, its uri under it
	const cells = w.cells( LOGO );
	check( 'a row says the label of the slot strong, with its uri under it, as text', byClass( cells[0], 'images-name-label' )[0].tagName === 'STRONG' && byClass( cells[0], 'images-name-label' )[0].textContent === 'Logo' && byClass( cells[0], 'images-name-key' )[0].textContent === LOGO );
	check( '...and the target size as width × height px', cells[1].textContent === '500 × 100 px' );

	// The state is what one would otherwise see only after opening the slot
	const dark = w.cells('/logo/dark')[2];
	check( 'a slot that no page includes says so in a badge that warns, with the templates that name it under it', byClass( dark, 'nino-admin-badge' ).length === 1 && byClass( dark, 'nino-admin-badge' )[0].dataset.state === 'warn' && byClass( dark, 'nino-admin-badge' )[0].textContent === text('/_admin/slots/label/unused')
		&& byClass( dark, 'slots-state-templates' )[0].textContent === text('/_admin/slots/label/templates').replace( '%s', 'page-x, frame-header' ) );
	const team = w.cells('/template/page-about/team/photo')[2];
	check( '...a slot that no page includes and no template names has the badge and no line of templates', byClass( team, 'nino-admin-badge' )[0].dataset.state === 'warn' && byClass( team, 'slots-state-templates' ).length === 0 );
	const lose = w.cells('/template/page-lose/hero/image')[2];
	check( 'a slot that a page includes and that has no image says so quietly, in a badge of the state muted', byClass( lose, 'nino-admin-badge' ).length === 1 && byClass( lose, 'nino-admin-badge' )[0].dataset.state === 'muted' && byClass( lose, 'nino-admin-badge' )[0].textContent === text('/_admin/slots/label/noimage') );
	check( 'a slot that is included and has its image says nothing in the state', byClass( w.cells( LOGO )[2], 'nino-admin-badge' ).length === 0 && w.cells( LOGO )[2].textContent === '' );

	// The choice of what to show: all, without an image, not included anywhere
	check( 'the choice has the name "Show" and its first option is "all"', w.show().children.map( o => o.value ).join('|') === '|none|unused' && w.show().children[0].textContent === text('/_admin/slots/label/show-all') && w.show().children.map( o => o.textContent ).join('|') === [ 'show-all', 'show-none', 'show-unused' ].map( word => text('/_admin/slots/label/'+ word) ).join('|') );
	const show = value => { w.show().value = value; fire( w.show(), 'change' ) };
	show( 'none' );
	check( 'without image shows the slots that have none', w.ids().join() === [ '/logo/dark', '/template/page-lose/hero/image', ODD ].join() );
	show( 'unused' );
	check( 'not included anywhere shows the slots that no page includes, whatever else they have', w.ids().join() === [ '/logo/dark', '/template/page-about/team/photo', ODD ].join() );
	show( '' );
	check( 'all shows every slot again', w.ids().length === 6 );

	// The search reads the label, the uri and the templates
	w.search().value = 'page-about';
	fire( w.search(), 'input' );
	check( 'the search finds a slot by its uri', w.ids().join() === '/template/page-about/team/photo' );
	w.search().value = 'frame-header';
	fire( w.search(), 'input' );
	check( '...and by a template that names it, which no cell shows beyond the badge of the slot', w.ids().join() === '/logo/dark' );
	w.search().value = 'team photo';
	fire( w.search(), 'input' );
	check( '...and by its label', w.ids().join() === '/template/page-about/team/photo' );
	w.search().value = 'no such slot';
	fire( w.search(), 'input' );
	check( 'a search that finds nothing says so with its own sentence', w.ids().length === 0 && byClass( w.list, 'nino-admin-table-empty' ).concat( byClass( w.list, 'nino-admin-empty' ) ).some( el => el.textContent === text('/_admin/slots/nomatch') ) );
	check( '...and the search is named for what it searches', w.search().attributes['aria-label'] === text('/_admin/slots/label/search') || w.search().placeholder === text('/_admin/slots/label/search') );
	w.search().value = '';
	fire( w.search(), 'input' );

	// The size sorts as a string whose numbers are numbers: 10 before 500 before 640 before 1920
	const sizeHead = findAll( w.list, el => el.tagName === 'TH' && el.dataset.key === 'size' )[0];
	fire( tag( sizeHead, 'button' )[0], 'click' );
	check( 'the target size sorts by its numbers: 10 before 500 before 640 before 1920', w.panel._directory.rows().map( row => row.width ).join() === '10,500,500,640,640,1920' );

	// The list bar: the scan beside "New image slot", which is the one main button at the far right
	const buttons = tag( w.bar(), 'button' );
	check( 'the list has its bar: the scan beside "New image slot", which is the one main button at the far right', buttons.length === 2 && buttons[1].textContent === text( NEW_SLOT ) && hasClass( buttons[1], 'nino-admin-btn-primary' )
		&& buttons[0].textContent === text( SCAN_SLOT ) && hasClass( buttons[0], 'nino-admin-btn-secondary' ) );

	// Each row has ONE button: the pencil, named for the slot
	const edit = tag( w.rows().find( el => el.dataset.row === LOGO ), 'button' );
	check( 'a row has one small icon button, "Edit image slot <label>", and no other', edit.length === 1 && edit[0].attributes['aria-label'] === text('/_admin/slots/row/open').replace( '%s', 'Logo' ) );
	check( 'a list that is empty has no table but the sentence of the empty state, and the bar is still there', ( () => {
		const e = slotsWorld();
		e.open( [] );
		return byClass( e.list, 'nino-admin-table-empty' ).concat( byClass( e.list, 'nino-admin-empty' ) ).some( el => el.textContent === text('/_admin/slots/empty') ) && tag( e.bar(), 'button' ).length === 2;
	} )() );

	check( 'a list that cannot be read says so in place of the table', ( () => {
		const e = slotsWorld();
		e.panel.showCurrent();
		e.answer( 500, { error : 'down' } );
		return byClass( e.list, 'nino-admin-error' ).concat( byClass( e.list, 'nino-admin-notice' ) ).length > 0 && e.panel._ready === false;
	} )() );
}

{
	// --- text from the server or a person is text -------------------------------------------------------------------

	const w = slotsWorld();
	w.open();
	const hostile = w.cells( ODD );

	check( 'a label, a uri and a template that are markup and quotes are drawn as the letters they are: no element comes of them, in the table', byClass( hostile[0], 'images-name-label' )[0].textContent === HOSTILE && byClass( hostile[0], 'images-name-key' )[0].textContent === ODD
		&& byClass( hostile[2], 'slots-state-templates' )[0].textContent.indexOf( HOSTILE ) !== -1 && tag( w.list, 'img' ).length === 0 && markupWrites.length === 0 );

	fire( w.rows().find( el => el.dataset.row === ODD ), 'click', { target : w.rows().find( el => el.dataset.row === ODD ) } );
	const d = w.dialog();
	check( '...in the title and the subtitle of the record', byClass( d, 'nino-admin-dialog-title' )[0].textContent === HOSTILE && byClass( d, 'nino-admin-dialog-subtitle' )[0].textContent === ODD && tag( d, 'img' ).length === 0 );
	check( '...and in the field of the label, as its value', w.field( d, 'label' ).value === HOSTILE );

	fire( w.button( d, DELETE_SLOT ), 'click' );
	const question = w.dialog();
	check( '...and in the question that asks before the deletion, which names the slot', question !== d && tag( question, 'img' ).length === 0 && tag( question, 'p' ).some( p => p.textContent.indexOf( HOSTILE ) !== -1 ) && markupWrites.length === 0 );
}

{
	// --- the record: one slot -----------------------------------------------------------------------------------------

	const w = slotsWorld();
	w.open();
	const before = w.h.entries.length;
	const row = w.rows().find( el => el.dataset.row === LOGO );

	fire( row, 'click', { target : row } );
	const d = w.dialog();

	check( 'a click on a row opens its record, the large dialog, at the address #images/slots/<the uri as ONE part>, one entry more in the history', d !== null && hasClass( d, 'nino-admin-dialog--large' ) && w.h.hash() === '#images/slots/%2Flogo' && w.h.entries.length === before + 1 );
	check( 'it is named by the label of the slot and says the uri under it, and has its place in the rows', byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Logo' && byClass( d, 'nino-admin-dialog-subtitle' )[0].textContent === LOGO && byClass( d, 'nino-admin-record-count' )[0].textContent.indexOf(' of 6') !== -1 );
	check( 'the uri is a line to read - it is what the templates write - and no field', tag( d, 'input' ).every( input => input.value !== LOGO ) && byClass( d, 'nino-admin-readline' ).length === 1 && byClass( d, 'nino-admin-readline-value' )[0].textContent === LOGO );
	check( 'the label, the width and the height are the fields of the record, with the values of the slot, each required with a star', tag( d, 'input' ).length === 3 && w.field( d, 'label' ).value === 'Logo' && w.field( d, 'width' ).value === '500' && w.field( d, 'height' ).value === '100'
		&& byClass( d, 'nino-admin-required' ).length === 3 && tag( d, 'input' ).every( input => input.attributes['aria-required'] === 'true' ) );
	check( '...the sizes are numbers of at least 1, in steps of 1, and each field has its help behind a (?) and no line under it', w.field( d, 'width' ).type === 'number' && w.field( d, 'width' ).min === '1' && w.field( d, 'width' ).step === '1' && w.field( d, 'height' ).type === 'number' && byClass( d, 'nino-admin-help-button' ).length === 3 && tag( d, 'small' ).length === 0 );
	check( '...the form of the record is novalidate: what is wrong is said by the tab, at the field', findAll( d, el => el.tagName === 'FORM' )[0].noValidate === true );

	const bar = byClass( d, 'nino-admin-actionbar' )[0];
	check( 'the bar has "Delete image slot" first, then the state, "Save and continue", and the one main button "Save" last - no Cancel, no Close', bar.children[0].textContent === text( DELETE_SLOT ) && hasClass( bar.children[0], 'nino-admin-btn-danger' )
		&& bar.children.filter( el => el.tagName === 'BUTTON' ).map( el => el.textContent ).join() === [ text( DELETE_SLOT ), text('/_admin/common/label/save-next'), text( SAVE_SLOT ) ].join() && hasClass( bar.children[bar.children.length - 1], 'nino-admin-btn-primary' ) );
	check( 'a record that was just opened says nothing of its state, and the step to the next slot is named for the slot', w.stateOf( d ).hidden === true && w.button( d, '/_admin/slots/record/next' ) !== undefined && w.button( d, '/_admin/slots/record/prev' ) !== undefined );

	// The row by its button, too
	fire( tag( d, 'button' ).find( b => hasClass( b, 'nino-admin-dialog-close' ) ), 'click' );
	check( 'the cross closes the record, and the focus is on the row', w.dialog() === null && w.rows().find( el => el.dataset.row === LOGO ).focused === true );
}

{
	const w = slotsWorld();
	w.open();
	const pencil = tag( w.rows().find( el => el.dataset.row === '/logo/dark' ), 'button' )[0];

	fire( pencil, 'click', { currentTarget : pencil } );
	const d = w.dialog();
	check( 'the pencil of a row opens the same record: its address is the uri as one part, with the slash written %2F', d !== null && w.h.hash() === '#images/slots/%2Flogo%2Fdark' && byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Logo dark' );

	// Stepping goes along the rows the directory shows
	fire( w.button( d, '/_admin/slots/record/next' ), 'click' );
	check( 'stepping goes to the slot after in the rows the directory shows, and the address follows without a new entry', byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Hero' && w.h.hash() === '#images/slots/%2Ftemplate%2Fpage-home%2Fhero%2Fbackground' );
}

{
	// A reload with an address that names a slot, and an address of a slot that is not there
	const w = slotsWorld( '#images/slots/%2Fodd%2Fa%25b%40c' );

	w.panel.showCurrent();
	check( 'a reload with an address draws the directory first and opens the record the address names once the list has come', w.dialog() === null && w.requests.length === 1 );
	w.answer( 200, { slots : slotCatalogue() } );
	check( '...the record is the one the address names - a uri with a slash, a percent sign and an at sign is ONE part -, with no entry of its own in the history', w.dialog() !== null && byClass( w.dialog(), 'nino-admin-dialog-title' )[0].textContent === HOSTILE && w.ids().length === 6 );

	// Back closes it, and the list is not read again for that
	const asked = w.requests.length;
	w.step('#images/slots');
	check( 'the address of the directory closes the record, and the list is not read again for that', w.dialog() === null && w.requests.length === asked && w.h.hash() === '#images/slots' );

	const m = slotsWorld( '#images/slots/%2Fnothing' );
	m.panel.showCurrent();
	m.answer( 200, { slots : slotCatalogue() } );
	check( 'a slot that is not there is said over the directory, and the address is the directory\'s', m.dialog() === null && byClass( m.list, 'nino-admin-notice' ).length === 1 && byClass( m.list, 'nino-admin-notice' )[0].textContent === text('/_admin/slots/record/missing') && m.h.hash() === '#images/slots' );

	const t = slotsWorld( '#images/slots/ab%zz' );
	t.panel.showCurrent();
	t.answer( 200, { slots : slotCatalogue() } );
	check( 'a part that cannot be decoded is no slot: the directory shows, and the address is the directory\'s', t.dialog() === null && t.h.hash() === '#images/slots' && byClass( t.list, 'nino-admin-notice' ).length === 0 );

	// %40new is the key @new - which no slot can be called - and not the word
	const k = slotsWorld( '#images/slots/%40new' );
	k.panel.showCurrent();
	k.answer( 200, { slots : slotCatalogue() } );
	check( 'the key @new (%40new) is no slot and no dialog of a new one: the word is told from the key by the router, and the directory says that there is no such slot', k.dialog() === null && k.panel._adding === false && byClass( k.list, 'nino-admin-notice' ).length === 1 );
	const s = slotsWorld( '#images/slots/%40scan' );
	s.panel.showCurrent();
	s.answer( 200, { slots : slotCatalogue() } );
	check( '...and %40scan is no scan', s.panel._scanning === false && s.form.classList.contains('admin-hidden') === true );
}

{
	// --- Save: what was changed is sent, what was not is not -----------------------------------------------------------

	const w = slotsWorld();
	w.open();
	const row = w.rows().find( el => el.dataset.row === LOGO );

	fire( row, 'click', { target : row } );
	const d = w.dialog();
	const asked = w.requests.length;

	fire( w.button( d, SAVE_SLOT ), 'click' );
	check( 'a save with no change sends no request - what is stored stays as it is - and says that it is saved', w.requests.length === asked && w.stateOf( d ).dataset.state === 'ok' && w.toasts().join() === text('/_admin/common/msg/saved') );
	check( '...and the other panels are not told of a write there was none of', w.h.admin.stamp('slots') === 0 );

	// A value that says the same - the size with leading zeros, the label with spaces around it - is no change either
	w.field( d, 'label' ).value = '  Logo ';
	w.field( d, 'width' ).value = '0500';
	fire( w.field( d, 'width' ), 'input' );
	fire( w.button( d, SAVE_SLOT ), 'click' );
	check( '...nor is one that says the same thing in other letters: spaces around the label, a size with zeros in front', w.requests.length === asked );

	// Wrong values: refused at the fields, nothing sent
	w.field( d, 'label' ).value = '  ';
	w.field( d, 'width' ).value = '0';
	w.field( d, 'height' ).value = 'abc';
	fire( w.field( d, 'height' ), 'input' );
	fire( w.button( d, SAVE_SLOT ), 'click' );
	check( 'a record that is not right is refused at its fields: one sentence under each, the field invalid, nothing sent', w.requests.length === asked && errorsOf( d ).length === 3 && [ 'label', 'width', 'height' ].every( key => w.field( d, key ).attributes['aria-invalid'] === 'true' ) );
	check( '...the sentences say what is wrong with the field, the line of the bar says that there are fields to check, and the focus is on the first that is wrong', errorsOf( d )[0].textContent === text('/_admin/common/error/missing').replace( '%s', text('/_admin/common/label/label') )
		&& errorsOf( d )[1].textContent === text('/_admin/slots/error/invalid').replace( '%s', text('/_admin/slots/label/width') ).replace( '%s', text('/_admin/slots/expect/size') )
		&& byClass( d, 'nino-admin-status' )[0].textContent === text('/_admin/slots/error/fields') && byClass( d, 'nino-admin-status' )[0].dataset.state === 'error' && w.focused() === w.field( d, 'label' ) );

	// A change: ONE request with the trimmed values
	w.field( d, 'label' ).value = '  Wordmark ';
	w.field( d, 'width' ).value = '640';
	w.field( d, 'height' ).value = '120';
	fire( w.field( d, 'height' ), 'input' );
	fire( w.button( d, SAVE_SLOT ), 'click' );
	check( 'a record that is changed sends ONE slots/save for the slot with the values trimmed, and the sentences are gone', w.requests.length === asked + 1 && w.requests[asked].action === 'slots/save'
		&& JSON.stringify( w.requests[asked].payload ) === JSON.stringify( { uri : LOGO, label : 'Wordmark', width : '640', height : '120' } ) && errorsOf( d ).length === 0 );

	w.answer( 400, { error : 'a slot needs a width and a height of at least 1 pixel', code : 'invalid_value', field : 'width' } );
	check( 'a refusal that names a field is said at that field - and in the bar - with the focus on it, and the record is still changed', errorsOf( d ).length === 1 && w.field( d, 'width' ).attributes['aria-invalid'] === 'true' && w.focused() === w.field( d, 'width' )
		&& byClass( d, 'nino-admin-status' )[0].dataset.state === 'error' && w.h.dirty.isDirty( [ 'slots' ] ) === true && w.panel._find( LOGO ).label === 'Logo' );

	w.answer( 400, { error : 'x', code : 'slots_label', field : 'label' } ) ;
}

{
	const w = slotsWorld();
	w.open();
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const d = w.dialog();
	const asked = w.requests.length;

	w.field( d, 'label' ).value = 'Wordmark';
	w.field( d, 'width' ).value = '640';
	w.field( d, 'height' ).value = '120';
	fire( w.field( d, 'height' ), 'input' );
	w.h.dirty.refresh();
	check( 'a typed value makes the record changed, and nothing is sent by typing', w.stateOf( d ).dataset.state === 'warn' && w.h.dirty.isDirty( [ 'slots' ] ) === true && w.requests.length === asked );

	fire( w.button( d, SAVE_SLOT ), 'click' );
	check( 'the save is one request, and the one that is sent is for the slot with every value of the record: the label and both sizes', w.requests.length === asked + 1 && JSON.stringify( w.requests[asked].payload ) === JSON.stringify( { uri : LOGO, label : 'Wordmark', width : '640', height : '120' } ) );
	check( '...and a second press while it is out sends nothing', ( fire( w.button( d, SAVE_SLOT ), 'click' ), w.requests.length === asked + 1 ) );

	w.answer( 200, {} );
	const saved = w.panel._find( LOGO );
	check( 'once the server says so the row is what was sent - the label, the sizes as numbers - and the list is NOT read again', saved.label === 'Wordmark' && saved.width === 640 && saved.height === 120 && saved.size === '640 × 120 px' && w.tree() === 'slots/list,slots/save' );
	check( '...the row of the directory is drawn anew and fresh, the title of the record is the new label, the badge says saved, and the other panels are told', w.rows()[0].classList.contains('is-fresh') === true && byClass( w.cells( LOGO )[0], 'images-name-label' )[0].textContent === 'Wordmark' && w.cells( LOGO )[1].textContent === '640 × 120 px'
		&& byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Wordmark' && w.stateOf( d ).dataset.state === 'ok' && w.h.dirty.isDirty( [ 'slots' ] ) === false && w.h.admin.stamp('slots') === 1 );
	check( '...a slot that was written is told to the Images panel as well: its copy is stale', ( () => {
		const v = slotsWorld();
		v.h.admin.images = { _ready : true };
		v.open();
		fire( v.rows()[0], 'click', { target : v.rows()[0] } );
		v.field( v.dialog(), 'label' ).value = 'Other';
		fire( v.field( v.dialog(), 'label' ), 'input' );
		fire( v.button( v.dialog(), SAVE_SLOT ), 'click' );
		v.answer( 200, {} );
		return v.h.admin.images._ready === false && v.h.admin.stamp('slots') === 1;
	} )() );

	// A refusal of another kind is said in the bar alone
	const v = slotsWorld();
	v.open();
	fire( v.rows()[0], 'click', { target : v.rows()[0] } );
	v.field( v.dialog(), 'label' ).value = 'Other';
	fire( v.field( v.dialog(), 'label' ), 'input' );
	fire( v.button( v.dialog(), SAVE_SLOT ), 'click' );
	v.answer( 403, { error : 'not allowed' } );
	check( 'a refusal that names no field is said in the line of the bar alone, and the row stays as it was', errorsOf( v.dialog() ).length === 0 && byClass( v.dialog(), 'nino-admin-status' )[0].dataset.state === 'error' && v.panel._find( LOGO ).label === 'Logo' );

	// A refusal that names the label stands at the label
	const u = slotsWorld();
	u.open();
	fire( u.rows()[0], 'click', { target : u.rows()[0] } );
	u.field( u.dialog(), 'label' ).value = 'Other';
	fire( u.field( u.dialog(), 'label' ), 'input' );
	fire( u.button( u.dialog(), SAVE_SLOT ), 'click' );
	u.answer( 400, { error : 'label is required', code : 'slots_label', field : 'label' } );
	check( 'a refusal that names the label is said at the label', errorsOf( u.dialog() ).length === 1 && u.field( u.dialog(), 'label' ).attributes['aria-invalid'] === 'true' );
}

{
	// Save and continue goes on to the next slot of the rows the directory shows
	const w = slotsWorld();
	w.open();
	w.show().value = 'unused';
	fire( w.show(), 'change' );
	check( 'the choice of what to show narrows the rows the record walks', w.ids().join() === [ '/logo/dark', '/template/page-about/team/photo', ODD ].join() );
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const d = w.dialog();

	w.field( d, 'label' ).value = 'Dark logo';
	fire( w.field( d, 'label' ), 'input' );
	fire( w.button( d, '/_admin/common/label/save-next' ), 'click' );
	w.answer( 200, {} );
	check( 'Save and continue saves and goes on to the next slot of the rows that are shown - not of all slots -: the title and the address are the next one\'s', byClass( d, 'nino-admin-dialog-title' )[0].textContent === 'Team photo' && w.h.hash() === '#images/slots/%2Ftemplate%2Fpage-about%2Fteam%2Fphoto' );
	check( '...and what the person chose in the directory is as it was when the record is closed', ( fire( tag( d, 'button' ).find( b => hasClass( b, 'nino-admin-dialog-close' ) ), 'click' ), w.show().value === 'unused' && w.ids().length === 3 ) );
}

{
	// --- Delete: asked first, the record is closed, the focus goes to the next row ------------------------------------

	const w = slotsWorld();
	w.open();
	fire( w.rows()[1], 'click', { target : w.rows()[1] } );
	const d = w.dialog();
	const asked = w.requests.length;

	fire( w.button( d, DELETE_SLOT ), 'click' );
	const question = w.dialog();
	check( 'it asks first, on the record, with the question of the level destructive that names the slot - and sends nothing', question !== d && byClass( question, 'nino-admin-dialog-title' )[0].textContent === text('/_admin/slots/confirm/delete-title')
		&& tag( question, 'p' ).some( p => p.textContent === text('/_admin/slots/confirm/delete').replace( '%s', 'Logo dark' ) ) && w.requests.length === asked );
	w.ask( false );
	check( 'declining sends nothing and leaves the record as it is', w.requests.length === asked && w.dialog() === d && w.panel._find('/logo/dark') !== null );

	fire( w.button( d, DELETE_SLOT ), 'click' );
	w.ask( true );
	check( 'confirming sends slots/delete for the slot, and for nothing else', w.requests.length === asked + 1 && w.requests[asked].action === 'slots/delete' && JSON.stringify( w.requests[asked].payload ) === JSON.stringify( { uri : '/logo/dark' } ) );
	w.answer( 403, { error : 'not allowed' } );
	check( 'a refusal is said in the bar and the record stays, the row with it', w.dialog() === d && byClass( d, 'nino-admin-status' )[0].dataset.state === 'error' && w.panel._find('/logo/dark') !== null );

	fire( w.button( d, DELETE_SLOT ), 'click' );
	w.ask( true );
	w.answer( 200, {} );
	check( 'once the server says so the row is gone from the directory and the list is not read again for that', w.ids().indexOf('/logo/dark') === -1 && w.ids().length === 5 && w.tree() === 'slots/list,slots/delete,slots/delete' );
	check( '...the record is closed, the other panels are told, and the focus is on the row that took its place', w.dialog() === null && w.h.admin.stamp('slots') >= 1 && w.rows()[1].dataset.row === '/template/page-home/hero/background' && w.rows()[1].focused === true );
	check( '...the entry of the history the record made is taken off by the history, and the address is the directory\'s', w.h.went.slice( -1 )[0] === -1 && ( w.h.back(), w.h.router.arrive(), w.panel.showCurrent(), w.h.hash() === '#images/slots' && w.dialog() === null && w.ids().length === 5 ) );
}

{
	// --- A new slot: no row to open, a dialog of its own ----------------------------------------------------------------

	const w = slotsWorld();
	w.open();
	const add = tag( w.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-primary' ) );
	const before = w.h.entries.length;

	fire( add, 'click' );
	const d = w.dialog();
	check( 'New image slot opens the dialog of a new slot at the address #images/slots/@new, one entry more in the history - the dialog of a form, no record', d !== null && hasClass( d, 'nino-admin-dialog--form' ) && hasClass( d, 'nino-admin-record' ) === false
		&& w.h.hash() === '#images/slots/@new' && w.h.entries.length === before + 1 );
	check( 'it is titled and answered "Create image slot", with Cancel beside it', byClass( d, 'nino-admin-dialog-title' )[0].textContent === text( CREATE_SLOT ) && w.button( d, CREATE_SLOT ) !== undefined && w.button( d, '/_admin/common/label/cancel' ) !== undefined );
	check( 'it has the four fields - the uri, the label, the width and the height - each required, each with its help, the uri with the example of what is typed', [ 'uri', 'label', 'width', 'height' ].every( key => w.field( d, key ) !== undefined && w.field( d, key ).attributes['aria-required'] === 'true' )
		&& w.field( d, 'uri' ).placeholder === '/template/page-home/hero/image' && byClass( d, 'nino-admin-help-button' ).length === 4 && w.field( d, 'width' ).type === 'number' );
	check( '...and the focus is on the uri', w.field( d, 'uri' ).focused === true );

	const asked = w.requests.length;
	const type = ( key, value ) => { w.field( d, key ).value = value; fire( w.field( d, key ), 'input' ) };

	fire( w.button( d, CREATE_SLOT ), 'click' );
	check( 'nothing typed is refused at every field - four sentences "is missing" - and nothing is sent', w.requests.length === asked && errorsOf( d ).length === 4 );

	type( 'uri', 'logo' ); type( 'label', 'X' ); type( 'width', '10' ); type( 'height', '10' );
	fire( w.button( d, CREATE_SLOT ), 'click' );
	check( 'a uri that is no uri is refused at the field before anything is sent, with what the field takes', w.requests.length === asked && errorsOf( d ).length === 1 && errorsOf( d )[0].textContent === text('/_admin/slots/error/invalid').replace( '%s', text('/_admin/slots/label/uri') ).replace( '%s', text('/_admin/slots/expect/uri') ) && w.focused() === w.field( d, 'uri' ) );
	type( 'uri', '/logo/dark' );
	fire( w.button( d, CREATE_SLOT ), 'click' );
	check( 'a uri a slot holds already is refused at the field as taken - nothing is sent', w.requests.length === asked && errorsOf( d ).length === 1 && errorsOf( d )[0].textContent === text('/_admin/slots/error/taken').replace( '%s', text('/_admin/slots/label/uri') ) );
	type( 'width', '0' );
	fire( w.button( d, CREATE_SLOT ), 'click' );
	check( 'a size that is no whole number of at least 1 is refused at its field', w.requests.length === asked && errorsOf( d ).some( el => el.textContent.indexOf( text('/_admin/slots/expect/size') ) !== -1 ) );

	type( 'uri', ' /new-slot ' ); type( 'label', ' Fresh ' ); type( 'width', '300' ); type( 'height', '200' );
	fire( w.button( d, CREATE_SLOT ), 'click' );
	check( 'a good one sends slots/create with the values trimmed, and the dialog does not close before the server has said yes', w.requests.length === asked + 1 && w.requests[asked].action === 'slots/create' && JSON.stringify( w.requests[asked].payload ) === JSON.stringify( { uri : '/new-slot', label : 'Fresh', width : '300', height : '200' } ) && w.dialog() === d );

	w.answer( 409, { error : 'slot already exists', code : 'slots_exists', field : 'uri' } );
	check( 'a 409 is said at the uri, the dialog stays open with what was typed, and the form can be sent again', w.dialog() === d && errorsOf( d ).length === 1 && w.field( d, 'uri' ).attributes['aria-invalid'] === 'true' && w.field( d, 'label' ).value === ' Fresh ' && w.panel._find('/new-slot') === null );
	fire( w.button( d, CREATE_SLOT ), 'click' );
	w.answer( 400, { error : 'a slot needs a width and a height of at least 1 pixel', code : 'invalid_value', field : 'height' } );
	check( 'a refusal that names the height is said at the height', w.dialog() === d && errorsOf( d ).length === 1 && w.field( d, 'height' ).attributes['aria-invalid'] === 'true' );
	fire( w.button( d, CREATE_SLOT ), 'click' );
	w.answer( 500, { error : 'could not save the slot' } );
	check( 'a refusal that names no field is said at the foot of the form', w.dialog() === d && errorsOf( d ).length === 1 && errorsOf( d )[0].textContent.indexOf('could not save the slot') !== -1 );

	fire( w.button( d, CREATE_SLOT ), 'click' );
	w.answer( 200, {} );
	check( 'once the server says so the dialog closes, the address is the directory\'s, the other panels are told, and the list is read again - the new row needs its usage', w.dialog() === null && w.h.hash() === '#images/slots' && w.h.admin.stamp('slots') === 1 && w.requests[w.requests.length - 1].action === 'slots/list' );

	const fresh = slotCatalogue().concat( [ { uri : '/new-slot', label : 'Fresh', width : 300, height : 200, hasImage : false, usage : { templates : [], pages : [] } } ] );
	w.answer( 200, { slots : fresh } );
	check( '...the row of the new slot is in the directory, fresh, has the focus, and a toast says that the slot was made', w.ids().indexOf('/new-slot') !== -1 && w.rows().find( el => el.dataset.row === '/new-slot' ).classList.contains('is-fresh') === true && w.rows().find( el => el.dataset.row === '/new-slot' ).focused === true
		&& w.toasts().join() === text('/_admin/slots/msg/created') );
}

{
	// The address opens the dialog, Esc and Cancel close it, Back too
	const w = slotsWorld( '#images/slots/@new' );
	w.panel.showCurrent();
	w.answer( 200, { slots : slotCatalogue() } );
	const d = w.dialog();
	check( 'a reload at #images/slots/@new draws the directory and opens the dialog of a new slot over it', d !== null && hasClass( d, 'nino-admin-dialog--form' ) && w.ids().length === 6 && w.panel._adding === true && w.h.hash() === '#images/slots/@new' );

	fire( d, 'keydown', { key : 'Escape' } );
	check( 'Esc closes it - nothing was typed, so nothing is asked - and the address is the directory\'s', w.dialog() === null && w.panel._adding === false && w.h.hash() === '#images/slots' );

	const v = slotsWorld();
	v.open();
	fire( tag( v.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-primary' ) ), 'click' );
	fire( v.button( v.dialog(), '/_admin/common/label/cancel' ), 'click' );
	check( 'Cancel closes it too, and the focus is on the button that opened it', v.dialog() === null && v.h.hash() === '#images/slots' && tag( v.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-primary' ) ).focused === true );

	// Input that was typed is asked about
	const u = slotsWorld();
	u.open();
	fire( tag( u.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-primary' ) ), 'click' );
	u.field( u.dialog(), 'label' ).value = 'typed';
	fire( u.field( u.dialog(), 'label' ), 'input' );
	fire( u.dialog(), 'keydown', { key : 'Escape' } );
	check( 'Esc with something typed asks first - the dialog stays, with the question on it - and the address stays', u.dialogs !== null && u.h.dialogs.filter( x => x.open ).length === 2 && u.panel._adding === true && u.h.hash() === '#images/slots/@new' );
	u.ask( true );
	check( '...Discard closes it, and the address is the directory\'s', u.dialog() === null && u.panel._adding === false && u.h.hash() === '#images/slots' );

	// Back
	const t = slotsWorld();
	t.open();
	fire( tag( t.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-primary' ) ), 'click' );
	t.h.back();
	t.h.router.arrive();
	t.panel.showCurrent();
	check( 'Back from the dialog closes it, as Esc does', t.dialog() === null && t.panel._adding === false && t.h.hash() === '#images/slots' );
}

{
	// The address of a new slot, typed while a record is open: the record is closed, the dialog opens
	const w = slotsWorld();
	w.open();
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	check( 'a record is open', w.dialog() !== null && w.panel._record.isOpen() === true );
	w.step('#images/slots/@new');
	check( 'the address of a new slot, typed while a record is open, closes the record and opens the dialog of a new slot', w.panel._record.isOpen() === false && w.panel._adding === true && w.dialog() !== null && hasClass( w.dialog(), 'nino-admin-dialog--form' ) && w.h.hash() === '#images/slots/@new' );
}

{
	// The word is told from a key by the router
	const w = slotsWorld();
	w.open();
	check( 'a slot is named by its uri as one part: the segments of it name none, a word names no slot, and the key @new is no word', w.panel._find('/logo/dark').uri === '/logo/dark' && w.panel._find('logo') === null && w.panel._find('@scan') === null && w.panel._find('@new') === null );
}

// --- the scan -------------------------------------------------------------------------------------

{
	const w = slotsWorld();
	w.open();
	const scanButton = tag( w.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-secondary' ) );
	const before = w.h.entries.length;

	fire( scanButton, 'click' );
	check( 'the scan has an address of its own and opens in the level beside the directory, waiting for the server', w.h.hash() === '#images/slots/@scan' && w.h.entries.length === before + 1 && w.requests[w.requests.length - 1].action === 'slots/scan'
		&& w.form.classList.contains('admin-hidden') === false && w.list.classList.contains('admin-hidden') === true && findAll( w.form, el => el.attributes['aria-busy'] === 'true' ).length === 1 );
	check( '...with one back link, "Back to Image Slots", in its context bar', byClass( w.form, 'nino-admin-back-link' ).length === 1 );

	const found = [
		{ filename : 'a.png', src : '/images/a.png', suggestedUri : '/a', width : 100, height : 50, files : [ 'page-a.tpl' ] },
		{ filename : '', src : '/template/page-x/cards/one-background', suggestedUri : '/template/page-x/cards/one-background', width : 0, height : 0, files : [ 'page-x.tpl' ] },
		{ filename : 'c.png', src : '/images/c.png', suggestedUri : '/c', width : 80, height : 0, files : [ 'page-c.tpl' ] },
	];
	w.answer( 200, { missing : found } );
	const cards = findAll( w.form, el => el.tagName === 'FIELDSET' );
	const sizeOf = card => findAll( card, el => el.type === 'number' );
	check( 'every finding is a card with the fields of a slot, as blocks, in a form that is novalidate and has the one bar', cards.length === 3 && findAll( w.form, el => el.tagName === 'FORM' )[0].noValidate === true
		&& byClass( w.form, 'nino-admin-actionbar' ).length === 1 && tag( byClass( w.form, 'nino-admin-actionbar' )[0], 'button' ).filter( b => hasClass( b, 'nino-admin-btn-primary' ) ).length === 1 );
	check( 'a finding that comes with a size asks for none; one without marks its width and its height as required', sizeOf( cards[0] ).every( i => i.required !== true ) && sizeOf( cards[1] ).length === 2 && sizeOf( cards[1] ).every( i => i.required === true && i.getAttribute('aria-required') === 'true' ) && sizeOf( cards[2] ).every( i => i.required === true ) );
	const starsShown = card => findAll( card, el => hasClass( el, 'nino-admin-required' ) && el.hidden !== true ).length;
	check( 'a finding without a size has the star after the names of its width and its height, as it has after its uri and label; one with a size has two', starsShown( cards[1] ) === 4 && starsShown( cards[2] ) === 4 && starsShown( cards[0] ) === 2 );
	check( 'the scan opens as a view of nothing yet saved: its state badge is the one of a fresh thing, and the shell has no input of it', byClass( w.form, 'nino-admin-actionbar-state' )[0].dataset.fresh === 'true' && w.h.dirty.isDirty( [ 'slots' ] ) === false );
	const tick = card => findAll( card, el => el.type === 'checkbox' )[0];
	tick( cards[1] ).checked = true;
	fire( tick( cards[1] ), 'change' );
	check( 'a finding that is ignored asks for nothing: a slot nobody makes needs no size', sizeOf( cards[1] ).every( i => i.required !== true && i.getAttribute('aria-required') === null ) );
	tick( cards[1] ).checked = false;
	fire( tick( cards[1] ), 'change' );
	check( '...and the stars of its size come back with the need', starsShown( cards[1] ) === 4 && sizeOf( cards[1] ).every( i => i.required === true ) );

	// Input in the scan is unsaved input of the tab: the shell asks before it is lost
	const label = findAll( cards[0], el => el.type === 'text' )[1];
	label.value = 'Changed in the scan';
	fire( label, 'input' );
	check( 'what is typed into a finding is unsaved input of the tab - the entry of the tab says so while the scan is on screen - and is no input of the record', w.h.dirty.isDirty( [ 'slots' ] ) === true && w.h.dirty.isDirty( [ 'slots-scan' ] ) === true );
	label.value = 'a';
	fire( label, 'input' );
	check( '...and the entry of the tab is clean again once the input is the one that was drawn', w.h.dirty.isDirty( [ 'slots' ] ) === false );

	w.requests.length = 0;
	let outcome = null;
	w.panel._saveScanResults( w.panel._scanRows, ok => { outcome = ok } );
	check( 'a submit makes the finding that is right and sends none of those that are not', w.requests.length === 1 && w.requests[0].action === 'slots/create' && w.requests[0].payload.uri === '/a' );
	check( 'each finding that is not right is refused at its fields: a sentence under each field, the field invalid, the focus on the first', errorsOf( cards[1] ).length === 2 && errorsOf( cards[2] ).length === 1 && errorsOf( cards[0] ).length === 0 && w.focused() === sizeOf( cards[1] )[0] );
	w.answer( 200, {} );
	check( 'the slot made is told to the other panels and the list is read again', w.h.admin.stamp('slots') === 1 && w.requests[w.requests.length - 1].action === 'slots/list' );
	const withA = slotCatalogue().concat( [ { uri : '/a', label : 'a', width : 100, height : 50, hasImage : false, usage : { templates : [], pages : [] } } ] );
	w.answer( 200, { slots : withA } );
	check( '...the card of the slot that was made is gone, the others stay with their sentences, the save reports false - and the directory under the scan has the new slot', outcome === false && findAll( w.form, el => el.tagName === 'FIELDSET' ).length === 2 && w.panel._scanRows.length === 2 && w.panel._find('/a') !== null && w.rows().some( el => el.dataset.row === '/a' ) === true );

	const left = w.panel._scanRows.slice();
	sizeOf( left[0].field )[0].value = '10';
	fire( sizeOf( left[0].field )[0], 'input' );
	check( 'what is typed into a finding takes its sentences away', errorsOf( left[0].field ).length === 0 && errorsOf( left[1].field ).length === 1 );
	sizeOf( left[0].field )[1].value = '10';
	sizeOf( left[1].field )[0].value = '20';
	sizeOf( left[1].field )[1].value = '20';
	w.requests.length = 0;
	outcome = null;
	w.panel._saveScanResults( w.panel._scanRows, ok => { outcome = ok } );
	check( 'with every finding right they are made one request at a time, the next after the answer to the one before', w.requests.length === 1 );
	w.answer( 200, {} );
	check( '...', w.requests.length === 2 && outcome === null );
	w.answer( 409, { error : 'exists' } );
	check( 'a finding the server refuses stays in the form with its sentence at the uri; the one made leaves it', w.panel._scanRows.length === 1 && w.panel._scanRows[0] === left[1] && errorsOf( left[1].field ).length === 1 );
	w.answer( 200, { slots : withA } );
	check( '...and the save reports false', outcome === false );

	// Back returns to the directory with the focus on the button that opened the scan
	const back = byClass( w.form, 'nino-admin-back-link' )[0];
	fire( back, 'click' );
	check( 'the way back leaves the scan for the directory, writes its address and gives the focus to the button that opened the scan', w.list.classList.contains('admin-hidden') === false && w.form.classList.contains('admin-hidden') === true && w.h.hash() === '#images/slots' && scanButton.focused === true && w.panel._scanning === false );
}

{
	// After the scan made slots the directory shows them
	const w = slotsWorld();
	w.open();
	fire( tag( w.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-secondary' ) ), 'click' );
	w.answer( 200, { missing : [ { filename : 'a.png', src : '/images/a.png', suggestedUri : '/scanned', width : 100, height : 50, files : [ 'page-a.tpl' ] } ] } );
	fire( findAll( w.form, el => el.tagName === 'FORM' )[0], 'submit' );
	check( 'the scan creates the finding that is right for its uri, label and size', w.requests[w.requests.length - 1].action === 'slots/create' && JSON.stringify( w.requests[w.requests.length - 1].payload ) === JSON.stringify( { uri : '/scanned', label : 'scanned', width : '100', height : '50' } ) );
	w.answer( 200, {} );
	w.answer( 200, { slots : slotCatalogue().concat( [ { uri : '/scanned', label : 'scanned', width : 100, height : 50, hasImage : false, usage : { templates : [], pages : [] } } ] ) } );
	check( 'the directory shows the slots the scan made, the directory is the level on screen again, its address is written, and the number of slots is told in a toast', w.ids().indexOf('/scanned') !== -1 && w.list.classList.contains('admin-hidden') === false && w.form.classList.contains('admin-hidden') === true && w.h.hash() === '#images/slots'
		&& w.toasts().join() === text('/_admin/slots/scan/created').replace( '%s', '1' ) );
}

{
	// A reload at the scan, Back, and a record while the scan has input
	const w = slotsWorld( '#images/slots/@scan' );
	w.panel.showCurrent();
	w.answer( 200, { slots : slotCatalogue() } );
	check( 'a reload at #images/slots/@scan opens the scan once the list is in, and asks the server for it', w.panel._scanning === true && w.form.classList.contains('admin-hidden') === false && w.requests[w.requests.length - 1].action === 'slots/scan' );
	w.answer( 200, { missing : [ { filename : 'a.png', src : '/images/a.png', suggestedUri : '/a', width : 100, height : 50, files : [ 'page-a.tpl' ] } ] } );

	const input = findAll( w.form, el => el.type === 'text' )[0];
	input.value = '/typed';
	fire( input, 'input' );
	w.h.box.location.hash = '#images/slots';
	w.h.router.arrive();
	w.panel.showCurrent();
	check( 'a step through the history that leaves the scan with input in it asks first - Save, Discard, Cancel - and the scan stays until it is answered', w.h.dialogs.filter( d => d.open ).length === 1 && w.panel._scanning === true );
	const question = w.dialog();
	fire( tag( question, 'button' ).find( b => b.textContent === text('/_admin/common/label/discard') ), 'click' );
	check( '...Discard shows the directory', w.panel._scanning === false && w.list.classList.contains('admin-hidden') === false && w.form.classList.contains('admin-hidden') === true );
}

{
	// The scan view left while it ran
	const w = slotsWorld();
	w.open();
	fire( tag( w.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-secondary' ) ), 'click' );
	w.form.children.length = 0;
	w.form.appendChild( element('p') );
	w.answer( 200, { missing : [ { filename : 'a.png', src : '/images/a.png', suggestedUri : '/a', width : 100, height : 50, files : [ 'page-a.tpl' ] } ] } );
	check( 'an answer to a scan whose view was left has nowhere to go: nothing is drawn over what is on screen', findAll( w.form, el => el.tagName === 'FORM' ).length === 0 );

	// What a template or a file is called is text: a name that is markup draws no element
	w.panel._renderScanForm( [ { filename : '', src : '<img src=x onerror=1>', suggestedUri : '/x', width : 1, height : 1, files : [ '<b>part</b>.tpl' ] } ] );
	check( 'the path of a finding and the files it was found in are drawn as text: a name that is markup makes no element', findAll( w.form, el => el.tagName === 'IMG' || el.tagName === 'B' ).length === 0
		&& findAll( w.form, el => el.tagName === 'LEGEND' )[0].textContent === '<img src=x onerror=1>' && findAll( w.form, el => el.tagName === 'P' ).some( p => p.textContent === '<b>part</b>.tpl' ) );
	w.panel._renderScanForm( [ { filename : 'd.png', src : '/images/d.png', suggestedUri : '/d', width : 1, height : 1, files : [ 'page-d.tpl', 'page-e.tpl' ] } ] );
	check( 'the files a finding was found in are said, each by its name, in the order the server named them, and nothing else stands in that place', findAll( w.form, el => el.tagName === 'P' && el.textContent.indexOf( '.tpl' ) !== -1 ).map( p => p.textContent ).join('|') === 'page-d.tpl, page-e.tpl' );

	// All ignored, and nothing found
	w.panel._renderScanForm( [ { filename : 'a.png', src : '/images/a.png', suggestedUri : '/a', width : 100, height : 50, files : [ 'page-a.tpl' ] } ] );
	findAll( w.form, el => el.type === 'checkbox' )[0].checked = true;
	let outcome = null;
	w.panel._saveScanResults( w.panel._scanRows, ok => { outcome = ok } );
	check( 'findings that are all ignored make nothing: the list is shown and the save is done', outcome === true && w.list.classList.contains('admin-hidden') === false );
	w.panel._renderScanForm( [] );
	check( 'a scan that found nothing is an empty state, and has no form', byClass( w.form, 'nino-admin-empty' ).length === 1 && findAll( w.form, el => el.tagName === 'FORM' ).length === 0 && w.panel._scanRows === null );
}

{
	// --- the tab shown again: what was written elsewhere --------------------------------------------------------------

	const w = slotsWorld();
	w.open();
	w.show().value = 'none';
	fire( w.show(), 'change' );
	const asked = w.requests.length;

	w.panel.showCurrent();
	check( 'shown again with nothing written since, it reads nothing', w.requests.length === asked );
	w.h.admin.touch( 'slots' );
	w.panel.showCurrent();
	check( 'shown again after a slot was written elsewhere - the Images panel puts a picture in one - it reads the list quietly, and what was chosen is still chosen', w.requests.length === asked + 1 && w.requests[asked].action === 'slots/list' && w.show().value === 'none' );
	w.answer( 200, { slots : slotCatalogue().map( slot => Object.assign( slot, { hasImage : true } ) ) } );
	check( '...and the rows are what the server holds now: with an image, no slot is shown for "without image"', w.ids().length === 0 );
}

{
	// --- the rights and the token: the tab calls the five actions the server has, and no other -------------------------

	const w = slotsWorld();
	w.open();
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	w.field( w.dialog(), 'label' ).value = 'Other';
	fire( w.field( w.dialog(), 'label' ), 'input' );
	fire( w.button( w.dialog(), SAVE_SLOT ), 'click' );
	w.answer( 200, {} );
	fire( w.button( w.dialog(), DELETE_SLOT ), 'click' );
	w.ask( true );
	w.answer( 200, {} );
	fire( tag( w.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-primary' ) ), 'click' );
	[ [ 'uri', '/made' ], [ 'label', 'Made' ], [ 'width', '5' ], [ 'height', '5' ] ].forEach( ( [ key, value ] ) => { w.field( w.dialog(), key ).value = value } );
	fire( w.button( w.dialog(), CREATE_SLOT ), 'click' );
	w.answer( 200, {} );
	w.answer( 200, { slots : slotCatalogue() } );
	fire( tag( w.bar(), 'button' ).find( b => hasClass( b, 'nino-admin-btn-secondary' ) ), 'click' );

	check( 'the tab calls slots/list, slots/save, slots/delete, slots/create and slots/scan - the five actions the server has - and nothing else', Array.from( new Set( w.requests.map( r => r.action ) ) ).sort().join() === 'slots/create,slots/delete,slots/list,slots/save,slots/scan' );
	check( 'every call goes to the one door of the workbench, as a POST', w.requests.every( r => r.method === 'POST' && r.uri.endsWith('/_admin/') ) );
}

// Nothing the tab shows is a sentence of the browser's, and nothing it is told is markup
const slotsSource = source('_admin/Nino/Modules/Images/assets/slots.js');
check( 'the tab asks nothing with the browser\'s own dialogs', /\b(alert|confirm|prompt)\s*\(/.test( slotsSource.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /Nino\.adminUi\.confirm\(/g, '' ) ) === false );
check( 'nothing the tab is told is written as markup - innerHTML is only ever emptied', ( slotsSource.match( /innerHTML[^\n]*/g ) || [] ).every( use => /^innerHTML = '';$/.test( use ) ) );

check( 'every address the Image Slots tab wrote or drew - the slots, the new slot, the scan - is read back by the router as the same screen and the same parts', codec.written.length > 20 && codec.mismatches.length === 0
	&& codec.written.some( written => written.screen === 'slots' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@new' ) ) && codec.written.some( written => written.screen === 'slots' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@scan' ) )
	&& codec.written.some( written => written.screen === 'slots' && written.parts[0] === LOGO ) && codec.written.every( written => written.screen === 'slots' && written.parts.length <= 1 ) );


done();

} )();
