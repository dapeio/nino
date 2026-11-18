/**
 *	Nino										A compact filesystembased php framework
 *	admin-images-js-smoke.js	DOM-light checks for the Images panel's script
 *													(_admin/Nino/Modules/Images/assets/admin.js) and the
 *													Image Slots tab (slots.js).
 *
 *													The Images panel is the guide's third model: nothing is
 *													saved with a button. A file that is chosen is stored at once
 *													and a toast says so; an alt text is saved when its field is
 *													left and its value changed, and a toast says so; what the
 *													server refuses stands at the field. The Image Slots tab is a
 *													list of rows with an address each and a form with its bar.
 *													Nothing here compares a sentence: the checks read the build -
 *													which block drew a part, where it stands, what was sent - and
 *													the keys the scripts ask for are real ones in both languages.
 *
 *	Usage: node tests/admin-images-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, source, fills, load, element, findAll, fire, documentOf } = require( './harness.js' );

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
		set : function( name, parts ) { out.routed.push( 'set '+ [ name ].concat( parts ).join('/') ) },
		go : function( name, parts ) { out.routed.push( 'go '+ [ name ].concat( parts ).join('/') ) },
		href : function( name, parts ) { return '#'+ [ name ].concat( parts || [] ).map( encodeURIComponent ).join('/') },
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
const fileInput = function( root ) { return findAll( root, function( el ) { return el.type === 'file' } )[0] };
const errorsOf = function( root ) { return byClass( root, 'nino-admin-field-error' ) };

function slot( overrides ) {
	return Object.assign( {
		uri : '/page-home/hero', label : 'Home hero', width : 1920, height : 1080, url : '/public/images/hero.jpg',
		alt : { de_DE : 'Ein Haus' }, usage : { templates : [ 'page-home' ], pages : [ { httpUri : '/', name : 'Startseite' } ] },
	}, overrides || {} );
}

const flush = function() { return new Promise( function( resolve ) { setTimeout( resolve, 0 ) } ) };

( async function() {

console.log('Admin Images');


// --- the list of categories and the address of each -------------------------------

const grouping = page( [ 'images-list', 'images-form' ], 'images', [ '_admin/Nino/Modules/Images/assets/admin.js' ] );
const images = grouping.Nino.admin.images;
images._locales = [ 'de_DE', 'en_US' ];

const grouped = images._groupSlots( [ '/template/page-home/hero/image', '/template/page-home/about/image', '/template/frame-header/logo/image', '/logo', '/home/hero' ].map( uri => ( { uri : uri } ) ) );
check( 'slots are grouped as the Text panel groups keys: /template/<category> for a key of the grammar, the first segment for any other',
	Object.keys( grouped ).join() === 'template/page-home,template/frame-header,logo,home' && grouped['template/page-home'].length === 2 );
check( 'the two logo slots are one group: the first segment of /logo/dark is logo', Object.keys( images._groupSlots( [ '/logo', '/logo/dark' ].map( uri => ( { uri : uri } ) ) ) ).join() === 'logo' );

images._groups = { 'template/page-home' : [ slot( { uri : '/template/page-home/hero/image' } ) ], logo : [ slot( { uri : '/logo', label : 'Logo' } ), slot( { uri : '/logo/dark', label : 'Logo dark' } ) ] };
images._ready = true;
images._renderCategoryList();

const rows = findAll( grouping.levels['images-list'], function( el ) { return has( el, 'nino-admin-list-row' ) } );
const links = findAll( grouping.levels['images-list'], function( el ) { return el.tagName === 'A' && has( el, 'nino-admin-row-open' ) } );
check( 'every category is a row of the list block, a real link with the address of the category - the segments of its name', rows.length === 2 && links.length === 2
	&& links.map( link => link.href ).join() === '#images/logo,#images/template/page-home' );
check( '...and no row of its own: the old list class and its chevron are not drawn', findAll( grouping.levels['images-list'], function( el ) { return has( el, 'admin-type-btn' ) || has( el, 'admin-view-button-chev' ) } ).length === 0 );
check( '...the label of a row is the vocabulary\'s word for the category, as the Text panel says it', rows.every( row => findAll( row, el => el.tagName === 'STRONG' )[0].textContent === grouping.Nino.adminUi.slugLabel( row.dataset.row.split('/').pop() ) ) );

images._groups = {};
images._renderCategoryList();
const emptyBox = byClass( grouping.levels['images-list'], 'nino-admin-empty' )[0];
check( 'no slots at all is an empty state with the next step as a button, never a bare sentence', emptyBox !== undefined && buttonsOf( emptyBox ).length === 1 );
fire( buttonsOf( emptyBox )[0], 'click' );
check( '...the button goes to the tab that makes slots', grouping.window.location.hash === '#slots' );


// --- a category: the form of its slots --------------------------------------------------

images._groups = { 'page-home' : [ slot() ] };
images._currentGroup = 'page-home';
images._renderGroupForm();
const form = grouping.levels['images-form'];

const bars = byClass( form, 'nino-admin-contextbar' );
check( 'the form has one context bar, with one back link and the name of the category as its heading', bars.length === 1 && byClass( bars[0], 'nino-admin-back-link' ).length === 1
	&& findAll( bars[0], el => el.tagName === 'H3' )[0].textContent === grouping.Nino.adminUi.slugLabel('page-home') );

const card = findAll( form, function( el ) { return el.tagName === 'FIELDSET' } )[0];
check( 'a slot is one card, named by its label', card !== undefined && findAll( card, el => el.tagName === 'LEGEND' )[0].textContent === 'Home hero' );
check( 'its picture is chosen with the file block, which takes the types the server takes - and no bare file input stands anywhere', byClass( card, 'nino-admin-filefield' ).length === 1
	&& fileInput( card ).hidden === true && fileInput( card ).accept === '.jpg,.jpeg,.png,.webp,.gif' );
check( '...the limits of an upload stay in view beside it, as its note', findAll( card, el => has( el, 'nino-admin-field-note' ) ).length === 1 );
check( 'its alt text is the languages block: one row for each language, a short value, at most 250 characters', byClass( card, 'nino-admin-localerows' ).length === 1
	&& byClass( card, 'nino-admin-localerow' ).length === 2 && findAll( card, el => el.type === 'text' ).every( input => input.maxLength === 250 ) );
check( 'what is stored stands in the row of its language', findAll( card, el => el.type === 'text' ).map( input => input.value ).join() === 'Ein Haus,' );
check( 'nothing is saved by a button: no main button, no submit and no form in the card or in the view', findAll( form, el => el.tagName === 'FORM' || el.type === 'submit' || has( el, 'nino-admin-btn-primary' ) ).length === 0 );
check( 'the only buttons of a slot are the file block\'s, the helps and Remove - which is the dangerous one and small', buttonsOf( card ).filter( b => has( b, 'nino-admin-btn-danger' ) ).length === 1
	&& has( buttonsOf( card ).find( b => has( b, 'nino-admin-btn-danger' ) ), 'nino-admin-btn--small' ) );

const unused = images._renderSlotField( slot( { usage : { templates : [ 'page-x' ], pages : [] } } ) );
check( 'a slot no page shows is told as a notice that stays, not as a line of hint', byClass( unused, 'nino-admin-notice' ).filter( n => n.hidden !== true ).length === 1 && byClass( card, 'nino-admin-notice' ).filter( n => n.hidden !== true ).length === 0 );

const noImage = images._renderSlotField( slot( { url : null } ) );
const removeOf = function( root ) { return buttonsOf( root ).find( b => has( b, 'nino-admin-btn-danger' ) ) };
check( 'a slot with an image offers Remove, one without has the button and does not show it', removeOf( card ).hidden === false && removeOf( noImage ).hidden === true );
check( 'the preview names the picture by the first alt text the slot has, and by none where it has none', byClass( card, 'nino-admin-field-image-preview' )[0].alt === 'Ein Haus'
	&& byClass( images._renderSlotField( slot( { alt : {} } ) ), 'nino-admin-field-image-preview' )[0].alt === '' );


// --- an upload ----------------------------------------------------------------------

const shot = function( name, size, type ) { return { name : name, size : size ?? 1000, type : type ?? 'image/png' } };
const choose = function( root, file ) { const input = fileInput( root ); input.files = [ file ]; fire( input, 'change' ) };

grouping.requests.length = 0;
const upload = images._renderSlotField( slot( { url : null } ) );
choose( upload, shot( 'a.webp', 1000, 'image/webp' ) );
await flush();
check( 'a chosen file goes to the server at once, as an upload for the slot, with the file', grouping.requests.length === 1 && grouping.requests[0].action === 'images/upload'
	&& grouping.requests[0].payload.uri === '/page-home/hero' && grouping.requests[0].file.name === 'a.webp' );
check( '...while it is sent the field cannot be pressed again and the card says that it is busy', buttonsOf( upload )[ buttonsOf( upload ).findIndex( b => b.textContent === text('/_admin/common/label/file-choose') ) ].disabled === true && upload.attributes['aria-busy'] === 'true' );

grouping.answer( 200, { filename : 'a.webp', url : '/public/images/a.webp', belowTarget : false, source : { width : 3000, height : 2000 } } );
check( 'a stored picture is said by a toast, and the other panels are told', grouping.toasts().join() === text('/_admin/images/msg/saved') && grouping.touched.join() === 'slots' );
check( '...the preview shows it - the stamp is on the picture only, so a replaced one is not the cached one - and Remove is there from then on', byClass( upload, 'nino-admin-field-image-preview' )[0].hidden === false
	&& /^\/public\/images\/a\.webp\?t=\d+$/.test( byClass( upload, 'nino-admin-field-image-preview' )[0].src ) && removeOf( upload ).hidden === false );
check( '...and the field is empty again and ready, since the picture it holds is the slot\'s', fileInput( upload ).value === '' && upload.attributes['aria-busy'] === undefined && errorsOf( upload ).length === 0
	&& byClass( upload, 'nino-admin-filefield-name' )[0].dataset.state === 'none' );

choose( upload, shot( 'b.png' ) );
await flush();
grouping.answer( 200, { filename : 'b.png', url : '/public/images/b.png', belowTarget : true, source : { width : 300, height : 150 } } );
const warning = byClass( upload, 'nino-admin-notice' ).find( n => n.hidden !== true );
check( 'a picture below the target is saved, and said so in a notice that stays in view with its own size', warning !== undefined && warning.textContent.indexOf( '300 × 150 px' ) !== -1 && grouping.toasts().length === 1 );
choose( upload, shot( 'c.png' ) );
await flush();
grouping.answer( 200, { filename : 'c.png', url : '/public/images/c.png', belowTarget : false, source : { width : 3000, height : 2000 } } );
check( '...and the next picture that is large enough takes the notice away', byClass( upload, 'nino-admin-notice' ).filter( n => n.hidden !== true ).length === 0 );

grouping.touched.length = 0;
choose( upload, shot( 'd.png' ) );
await flush();
grouping.answer( 400, { code : 'image_type', error : 'not an image' } );
check( 'a picture the server refuses is said at the field - one sentence, the field marked invalid - and not as a toast', errorsOf( upload ).length === 1 && grouping.toasts().length === 1
	&& errorsOf( upload )[0].textContent === text('/_admin/error/image_type') );
check( '...it writes nothing there is to tell, and the field is free for another try', grouping.touched.length === 0 && fileInput( upload ).value === '' && buttonsOf( upload ).every( b => b.disabled !== true ) );

grouping.requests.length = 0;
choose( upload, shot( 'e.txt', 1000, 'text/plain' ) );
await flush();
check( 'a type the field does not take is refused at the field and never sent', grouping.requests.length === 0 && errorsOf( upload ).length === 1 );
choose( upload, shot( 'f.png', 3 * 1048576 ) );
await flush();
check( 'a file larger than the limit of the upload is refused at the field and never sent', grouping.requests.length === 0 && errorsOf( upload ).length === 1 );
choose( upload, shot( 'g.png' ) );
await flush();
check( '...and the sentence of an earlier try is gone with the next file chosen', grouping.requests.length === 1 && errorsOf( upload ).length === 0 );


// --- Remove -------------------------------------------------------------------------

grouping.requests.length = 0;
const withImage = images._renderSlotField( slot() );
fire( removeOf( withImage ), 'click' );
check( 'Remove asks first, in a dialog of the design system, with the name of the slot, and Cancel sends nothing', grouping.dialogs.length === 1 && findAll( grouping.dialogs[0], el => el.id === 'nino-admin-choice-text' )[0].textContent.indexOf( '“Home hero”' ) !== -1 );
grouping.ask( false );
check( '...nothing is sent and the picture stays', grouping.requests.length === 0 && removeOf( withImage ).hidden === false );

fire( removeOf( withImage ), 'click' );
grouping.ask( true );
check( 'a confirmed one sends the removal for the slot', grouping.requests.length === 1 && grouping.requests[0].action === 'images/remove' && grouping.requests[0].payload.uri === '/page-home/hero' );
grouping.touched.length = 0;
grouping.answer( 500, { error : 'could not save the slot' } );
check( 'a failure is said at the field, and the picture and the button stay', errorsOf( withImage ).length === 1 && removeOf( withImage ).hidden === false && removeOf( withImage ).disabled === false && grouping.touched.length === 0 );
fire( removeOf( withImage ), 'click' );
grouping.ask( true );
grouping.answer( 200, { filename : null } );
check( 'a successful one hides the picture and the button, tells the other panels and says so by a toast', byClass( withImage, 'nino-admin-field-image-preview' )[0].hidden === true && removeOf( withImage ).hidden === true
	&& grouping.touched.join() === 'slots' && grouping.toasts().join() === text('/_admin/images/msg/removed') );


// --- an alt text ---------------------------------------------------------------------

grouping.requests.length = 0;
const alt = images._renderSlotField( slot() );
const [ deInput, enInput ] = findAll( alt, el => el.type === 'text' );

fire( deInput, 'blur' );
check( 'a field that is left with the value it was stored with sends nothing', grouping.requests.length === 0 );

enInput.value = 'A house';
fire( enInput, 'blur' );
check( 'a field left with a changed value saves that language alone, in one request', grouping.requests.length === 1 && grouping.requests[0].action === 'images/alt'
	&& JSON.stringify( grouping.requests[0].payload ) === JSON.stringify( { uri : '/page-home/hero', alt : { en_US : 'A house' } } ) );
grouping.touched.length = 0;
grouping.answer( 200, { alt : { de_DE : 'Ein Haus', en_US : 'A house' } } );
check( '...a toast says it, naming the language by the one block that names a language, and the other panels are told', grouping.toasts()[0].indexOf( 'English (US)' ) !== -1 && grouping.touched.join() === 'slots' );
check( '...and the preview follows what was stored', byClass( alt, 'nino-admin-field-image-preview' )[0].alt === 'Ein Haus' );

fire( enInput, 'blur' );
check( '...saved once: leaving the field again without a change sends nothing more', grouping.requests.length === 1 );

grouping.requests.length = 0;
enInput.value = 'A house ';
fire( enInput, 'blur' );
grouping.answer( 200, { alt : { de_DE : 'Ein Haus', en_US : 'A house' } } );
check( 'what the server stored (cleaned) is what the field holds after it, so that a trailing space does not stay "unsaved"', enInput.value === 'A house' );

enInput.value = '';
fire( enInput, 'blur' );
check( 'an emptied field is sent as it is: empty means decorative, and removes the entry', JSON.stringify( grouping.requests[grouping.requests.length - 1].payload.alt ) === '{"en_US":""}' );
grouping.answer( 200, { alt : { de_DE : 'Ein Haus' } } );

// The registry's entry, and a form of two slots to tell a fault of one from another
const registered = grouping.registry._entries.images;
const fresh = function( groups ) {
	grouping.requests.length = 0;
	images._groups = groups ?? { 'page-home' : [ slot(), slot( { uri : '/page-home/second', label : 'Second', alt : { en_US : 'Second en' } } ) ] };
	images._currentGroup = 'page-home';
	images._renderGroupForm();
	grouping.levels['images-form'].classList.remove('admin-hidden');
	grouping.levels['images-list'].classList.add('admin-hidden');
	return findAll( grouping.levels['images-form'], el => el.type === 'text' );
};
const formErrors = function() { return errorsOf( grouping.levels['images-form'] ).length };

let alts = fresh();
const [ shownDe, shownEn ] = alts;
check( 'the panel registered an entry for the shell\'s questions with all three of isDirty, save and discard, and a form as drawn holds nothing unsaved', typeof registered.isDirty === 'function' && typeof registered.save === 'function' && typeof registered.discard === 'function' && registered.isDirty() === false );

// A refusal: the value stays, the field says why, nothing takes the focus, nothing is sent by leaving
shownEn.value = 'A house';
fire( shownEn, 'blur' );
check( 'a value whose request is on its way is unsaved input: it differs from what the server confirmed for its field', registered.isDirty() === true );
grouping.answer( 400, { error : 'an alt text is at most 250 characters' } );
check( 'a refused alt text says why at its own language\'s row and leaves the value where it is, and no toast says it was saved', formErrors() === 1 && shownEn.value === 'A house' && shownEn.getAttribute('aria-invalid') === 'true' && shownDe.getAttribute('aria-invalid') === null );
check( '...it is still unsaved input to the shell, and the refusal does not take the focus', registered.isDirty() === true && grouping.focused() === null );

grouping.requests.length = 0;
fire( shownEn, 'blur' );
fire( shownDe, 'blur' );
check( 'leaving the fields after a refusal sends nothing: the value is sent again when it was changed', grouping.requests.length === 0 );

shownDe.value = 'Ein Haus am See';
fire( shownDe, 'blur' );
grouping.answer( 500, { error : 'no' } );
grouping.requests.length = 0;
for( const field of [ shownDe, shownEn, shownDe, shownEn ] ) fire( field, 'blur' );
check( 'two languages that were refused are not sent again by tabbing through them: no request without an input, whatever the focus does', grouping.requests.length === 0 && formErrors() === 2 );

shownEn.value = 'A house by the lake';
fire( shownEn, 'input' );
check( 'typing into one language takes the sentence of that language away and not the other\'s', formErrors() === 1 && shownDe.getAttribute('aria-invalid') === 'true' && shownEn.getAttribute('aria-invalid') === null && grouping.refreshed > 0 );

fire( shownEn, 'blur' );
grouping.answer( 200, { alt : { de_DE : 'Ein Haus' , en_US : 'A house by the lake' } } );
check( 'a later success clears the sentence of its field and sets its baseline - what the answer says of the other language is not taken', formErrors() === 1 && shownDe.value === 'Ein Haus am See' && shownDe.getAttribute('aria-invalid') === 'true' && registered.isDirty() === true && shownEn.value === 'A house by the lake' );
shownEn.value = 'Edit';
fire( shownEn, 'blur' );
grouping.answer( 200, { alt : { en_US : 'Edit' } } );
check( '...a success of the language that was refused clears its own sentence', ( function() { shownDe.value = 'Ein Haus am See 2'; fire( shownDe, 'blur' ); grouping.answer( 200, { alt : { de_DE : 'Ein Haus am See 2' } } ); return formErrors() === 0 && shownDe.getAttribute('aria-invalid') === null && registered.isDirty() === false } )() );

// A second answer that comes late carries an older map: it is no news
alts = fresh();
const [ lateDe, lateEn ] = alts;
lateDe.value = 'de B';
fire( lateDe, 'blur' );
lateEn.value = 'en B';
fire( lateEn, 'blur' );
check( 'two languages are two requests, each for its own language', grouping.requests.length === 2 && grouping.requests[0].payload.alt.de_DE === 'de B' && grouping.requests[1].payload.alt.en_US === 'en B' );
grouping.requests[1].callback( { status : 200, responseJSON : { alt : { de_DE : 'Ein Haus', en_US : 'en B' } } } );
grouping.requests[0].callback( { status : 200, responseJSON : { alt : { de_DE : 'de B', en_US : '' } } } );
check( 'answers that arrive in the reverse order set the baseline of their own field alone: nothing is unsaved afterwards and no field shows the other\'s state', registered.isDirty() === false && lateDe.value === 'de B' && lateEn.value === 'en B' );
registered.discard();
check( '...and Discard puts the fields back to what each one confirmed, not to the older map', lateDe.value === 'de B' && lateEn.value === 'en B' );

// Another window wrote another language: this window did not change it, so it does not send it
alts = fresh();
alts[1].value = 'mine';
fire( alts[1], 'blur' );
grouping.answer( 200, { alt : { de_DE : 'Von Tab 2', en_US : 'mine' } } );
grouping.requests.length = 0;
fire( alts[0], 'blur' );
check( 'an answer that carries news of another language does not make that language\'s field unsaved, and leaving it without typing writes nothing - the other window\'s text is not overwritten', grouping.requests.length === 0 && alts[0].value === 'Ein Haus' && registered.isDirty() === false );

// What is typed while the request is out is sent when it is answered, and counts as unsaved all the while
alts = fresh();
const [ flyDe ] = alts;
flyDe.value = 'first';
fire( flyDe, 'blur' );
flyDe.value = 'second';
fire( flyDe, 'blur' );
check( 'a second save of the same language while one runs sends nothing of its own', grouping.requests.length === 1 );
check( '...and what is typed while the request is out is unsaved input, though its first value is on its way', registered.isDirty() === true );
grouping.answer( 200, { alt : { de_DE : 'first' } } );
check( 'the value typed meanwhile follows when the first is answered, and the field keeps it', grouping.requests.length === 2 && grouping.requests[1].payload.alt.de_DE === 'second' && flyDe.value === 'second' );
grouping.answer( 200, { alt : { de_DE : 'second' } } );
check( '...and nothing is unsaved when that is answered too', registered.isDirty() === false );

// Unsaved input of every card is asked about, not only of the first
alts = fresh();
alts[3].value = 'second card';
check( 'a value in the second card of a category is unsaved input too', registered.isDirty() === true );
registered.discard();
check( '...and Discard puts it back to its baseline', alts[3].value === 'Second en' && registered.isDirty() === false );

// Discard while a request is out: the field goes to the baseline, and to what the server confirms when it answers
alts = fresh();
alts[0].value = 'flying';
fire( alts[0], 'blur' );
registered.discard();
check( 'Discard puts a field whose request is out back to its baseline', alts[0].value === 'Ein Haus' );
grouping.answer( 200, { alt : { de_DE : 'flying' } } );
check( '...and the answer sets the baseline the server confirmed, which the field then shows: no field is left unlike its baseline', alts[0].value === 'flying' && registered.isDirty() === false );

// The shell's Save: it waits for a request that is out, then sends what is left
alts = fresh();
alts[0].value = 'running';
fire( alts[0], 'blur' );
alts[0].value = 'typed after';
alts[1].value = 'other language';
const ended = [];
registered.save( function( ok ) { ended.push( ok ) } );
check( 'the shell\'s Save does not report at once while a request of the language is out, and does not stop the one that is', ended.length === 0 && grouping.requests.length === 1 );
grouping.answer( 200, { alt : { de_DE : 'running' } } );
check( '...it sends what was typed after, then the next language, one at a time', grouping.requests.length === 2 && grouping.requests[1].payload.alt.de_DE === 'typed after' && ended.length === 0 );
grouping.answer( 200, { alt : { de_DE : 'typed after' } } );
check( '...', grouping.requests.length === 3 && grouping.requests[2].payload.alt.en_US === 'other language' && ended.length === 0 );
grouping.answer( 500, { error : 'no' } );
check( '...and stops at the first that fails, with false, leaving the value in its field', ended.join() === 'false' && registered.isDirty() === true && alts[1].value === 'other language' );
grouping.requests.length = 0;
registered.save( function( ok ) { ended.push( ok ) } );
check( 'a Save sends a value that was refused, since the person asked for it', grouping.requests.length === 1 && grouping.requests[0].payload.alt.en_US === 'other language' );
grouping.answer( 200, { alt : { en_US : 'other language' } } );
check( '...and ends with true when everything is stored', ended.join() === 'false,true' && registered.isDirty() === false );

// The category is left while a request is out or after it failed: nothing is lost silently
alts = fresh();
alts[0].value = 'lost?';
fire( alts[0], 'blur' );
const backLink = byClass( grouping.levels['images-form'], 'nino-admin-back-link' )[0];
grouping.asked.length = 0;
fire( backLink, 'click' );
check( 'the back link asks before it leaves a category with a value that is on its way - and shows nothing yet', grouping.asked.length === 1 && grouping.asked[0].names.join() === 'images' && grouping.levels['images-form'].classList.contains('admin-hidden') === false );
grouping.answer( 500, { error : 'no' } );
check( '...a refusal in the meantime leaves the sentence in the field that is still on screen', formErrors() === 1 && alts[0].value === 'lost?' && registered.isDirty() === true );
grouping.routed.length = 0;
grouping.asked[0].proceed();
check( '...and an answer that lets it go shows the list', grouping.levels['images-form'].classList.contains('admin-hidden') === true );
alts = fresh();
alts[0].value = 'x';
grouping.levels['images-form'].classList.add('admin-hidden');
grouping.levels['images-list'].classList.remove('admin-hidden');
check( 'the entry says that a value stands while the list is shown, so that a way out of the panel asks too', registered.isDirty() === true );
grouping.asked.length = 0;
grouping.requests.length = 0;
images._openGroup( 'page-home', true );
check( 'opening a category while that value stands asks first and draws nothing: the field and its value are still the ones of the form that was left', grouping.asked.length === 1 && images._editors[0].rows.controls.de_DE.value === 'x' );
registered.discard();
check( '...and Discard in that question puts the value back to its baseline', registered.isDirty() === false );


// --- the address ----------------------------------------------------------------------------

grouping.levels['images-form'].classList.add('admin-hidden');
grouping.levels['images-list'].classList.remove('admin-hidden');
images._ready = true;
images._groups = { 'page-home' : [ slot() ], 'template/page-home' : [ slot( { uri : '/template/page-home/hero/image' } ) ] };
images._renderCategoryList();

grouping.routed.length = 0;
images._openGroup( 'template/page-home', true );
check( 'opening a category is a step Back returns to: the address is pushed before the form writes it - and a category with a slash in its name is written as its segments', grouping.routed.join() === 'go images/template/page-home,set images/template/page-home' );
check( '...the form is drawn, and the focus goes into it', grouping.levels['images-form'].classList.contains('admin-hidden') === false && grouping.levels['images-list'].classList.contains('admin-hidden') === true && grouping.focused() !== null );

const back = byClass( grouping.levels['images-form'], 'nino-admin-back-link' )[0];
grouping.routed.length = 0;
fire( back, 'click' );
check( 'the back link adds an entry as well, and shows the list with the focus on the row that was opened', grouping.routed.join() === 'go images,set images' && grouping.levels['images-list'].classList.contains('admin-hidden') === false
	&& grouping.focused() === findAll( grouping.levels['images-list'], el => el.tagName === 'A' && el.dataset !== undefined && el.href === '#images/template/page-home' )[0] );

// What an address of a step through the history names
const follow = function( hash ) { grouping.hash = hash; grouping.routed.length = 0; images.showCurrent() };
follow( { panel : 'images', parts : [ 'template', 'page-home' ] } );
check( 'a hash of segments opens that category', images._currentGroup === 'template/page-home' && grouping.levels['images-form'].classList.contains('admin-hidden') === false );
follow( { panel : 'images', parts : [ 'template%2Fpage-home' ].map( decodeURIComponent ) } );
check( '...and so does the group written as one part, as an address was written before', images._currentGroup === 'template/page-home' );
follow( { panel : 'images', parts : [] } );
check( 'a hash that names the list shows it', grouping.levels['images-form'].classList.contains('admin-hidden') === true && grouping.levels['images-list'].classList.contains('admin-hidden') === false && grouping.routed.join() === 'set images' );
follow( { panel : 'images', parts : [ 'nope' ] } );
check( 'a category there is not, and half of one, is the list', grouping.levels['images-form'].classList.contains('admin-hidden') === true );
follow( { panel : 'images', parts : [ 'template' ] } );
check( '...half a category too', grouping.levels['images-form'].classList.contains('admin-hidden') === true && grouping.routed.join() === 'set images' );

// Leaving a form that holds unsaved input asks first, and a Cancel puts the address back
follow( { panel : 'images', parts : [ 'page-home' ] } );
findAll( grouping.levels['images-form'], el => el.type === 'text' )[1].value = 'A house';
grouping.asked.length = 0;
follow( { panel : 'images', parts : [] } );
check( 'a step Back out of a form with unsaved input asks first and shows nothing yet', grouping.asked.length === 1 && grouping.asked[0].names.join() === 'images' && grouping.levels['images-form'].classList.contains('admin-hidden') === false );
grouping.asked[0].onCancel();
check( '...a Cancel keeps the form and writes its level back into the address', grouping.levels['images-form'].classList.contains('admin-hidden') === false && grouping.routed[grouping.routed.length - 1] === 'set images/page-home' );
registered.discard();

follow( { panel : 'dashboard', parts : [] } );
check( 'a hash that names another panel (a click on the rail) keeps the level in memory and writes it', grouping.levels['images-form'].classList.contains('admin-hidden') === false && grouping.routed.join() === 'set images/page-home' );

// A reload on an address
images._ready = false;
grouping.levels['images-form'].classList.add('admin-hidden');
grouping.levels['images-list'].classList.remove('admin-hidden');
grouping.hash = { panel : 'images', parts : [ 'template', 'page-home' ] };
grouping.requests.length = 0;
images.init();
grouping.answer( 200, { slots : [ slot( { uri : '/template/page-home/hero/image' } ) ], locales : [ 'de_DE', 'en_US' ] } );
check( 'a reload on #images/template/page-home opens the category once the slots are in', images._currentGroup === 'template/page-home' && grouping.levels['images-form'].classList.contains('admin-hidden') === false && grouping.requests.length === 1 );

// The templates written since the slots were read
images._ready = true;
images._stamp = 0;
grouping.levels['images-form'].classList.add('admin-hidden');
grouping.levels['images-list'].classList.remove('admin-hidden');
grouping.hash = { panel : 'images', parts : [] };
grouping.requests.length = 0;
images.showCurrent();
check( 'shown again with no template written since, the panel asks for nothing', grouping.requests.length === 0 );
grouping.stamps.templates = 1;
grouping.levels['images-form'].classList.remove('admin-hidden');
images.showCurrent();
check( 'a template written while a category is open is not read now: the form is not drawn over', grouping.requests.length === 0 );
grouping.levels['images-form'].classList.add('admin-hidden');
images.showCurrent();
check( '...the showing that finds none open reads the slots again, once, whatever is written while the request is out', grouping.requests.length === 1 && grouping.requests[0].action === 'images/list' && images._ready === false );
grouping.stamps.templates = 2;
images.showCurrent();
check( '...', grouping.requests.length === 1 );
grouping.answer( 200, { slots : [ slot( { uri : '/template/page-new/hero/image' } ) ], locales : [ 'de_DE' ] } );
check( '...the stamp the answer carries is the one from before the request went out, so what was written meanwhile is read at the next showing', images._ready === true && images._stamp === 1 && Object.keys( images._groups ).join() === 'template/page-new' );

// Whatever the server or an editor wrote reaches the page as text
const imagesSource = source('_admin/Nino/Modules/Images/assets/admin.js');
check( 'nothing the panel is told is written as markup - innerHTML is only ever emptied', ( imagesSource.match( /innerHTML[^\n]*/g ) || [] ).every( function( use ) { return /^innerHTML = '';$/.test( use ) } ) );
check( 'the panel registers its entry behind a check that the shell has the registry', imagesSource.indexOf( "Nino.admin.dirty.register( 'images'" ) !== -1
	&& imagesSource.slice( 0, imagesSource.indexOf( "Nino.admin.dirty.register( 'images'" ) ).trimEnd().endsWith( "if( typeof Nino.admin.dirty === 'object' )" ) );


// --- every key the three scripts ask for is a fill in both languages ----------------------------

const asked = new Set();
[ 'admin.js', 'slots.js', 'favicon.js' ].forEach( function( name ) {
	for( const found of source('_admin/Nino/Modules/Images/assets/'+ name ).matchAll( /getText\(\s*'(\/_admin\/[a-z0-9\/_-]+)'\s*\)/g ) )
		asked.add( found[1] );
} );
const workbenchDe = fills('_admin/text/de_DE.php');
const missing = Array.from( asked ).filter( function( key ) { return ( moduleEn[key] ?? workbench[key] ) === undefined || ( moduleDe[key] ?? workbenchDe[key] ) === undefined } );
check( 'the scripts ask for '+ asked.size+ ' keys, and each has words in English and in German'+ ( missing.length === 0 ? '' : ' - not '+ missing.join(', ') ), asked.size > 40 && missing.length === 0 );


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
check( 'every slot is a row of the list block - a link with its address, the segments of its uri - and no row is a button to nowhere', slotLinks.map( l => l.href ).join() === '#slots/logo,#slots/logo/dark,#slots/template/page-home/hero/background'
	&& findAll( list, el => has( el, 'nino-admin-list-row' ) ).length === 3 && findAll( list, el => el.tagName === 'BUTTON' && has( el, 'nino-admin-row-open' ) ).length === 0 );
check( '...two lines each: the label over the uri and the size', findAll( list, el => el.tagName === 'SMALL' ).map( s => s.textContent.indexOf( ' × ' ) !== -1 ).every( Boolean ) && findAll( list, el => el.tagName === 'SMALL' ).length === 3 );
const pills = findAll( list, el => has( el, 'nino-admin-badge' ) );
check( 'a slot that no page shows has a badge that warns, in words; one without a picture says it quietly', pills.length === 1 && pills[0].dataset.state === 'warn' );
check( '...the templates that name it are on its second line', findAll( list, el => el.tagName === 'SMALL' )[1].textContent.indexOf('page-x') !== -1 );
check( 'the list has its bar: the scan beside "New image slot", which is the one main button at the far right', byClass( list, 'nino-admin-actionbar' ).length === 1
	&& buttonsOf( byClass( list, 'nino-admin-actionbar' )[0] ).length === 2 && has( buttonsOf( byClass( list, 'nino-admin-actionbar' )[0] )[1], 'nino-admin-btn-primary' ) && has( buttonsOf( byClass( list, 'nino-admin-actionbar' )[0] )[0], 'nino-admin-btn-secondary' ) );

tab.routed.length = 0;
fire( slotLinks[0], 'click' );
check( 'opening a slot is a step Back returns to: it writes its address, shows the form and moves the focus into it', tab.routed.join() === 'go slots/logo,set slots/logo' && form2.classList.contains('admin-hidden') === false
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
check( 'New image slot opens an empty form at the address #slots/new', tab.routed.join() === 'go slots/new,set slots/new' && inputs().uri.disabled === false && inputs().uri.value === '' && tab.focused() === inputs().uri );
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
	&& tab.routed.indexOf( 'set slots/new-slot' ) !== -1 && has( byClass( form2, 'nino-admin-actionbar' )[0].children[0], 'nino-admin-btn-danger' ) && slots._isNew === false );

// The address
tab.hash = { panel : 'slots', parts : [] };
slots.showCurrent();
check( 'an address that names the list shows it', list.classList.contains('admin-hidden') === false && form2.classList.contains('admin-hidden') === true );
tab.hash = { panel : 'slots', parts : [ 'logo', 'dark' ] };
slots.showCurrent();
check( 'an address of the segments of a uri opens that slot', form2.classList.contains('admin-hidden') === false && slots._current.uri === '/logo/dark' );
tab.hash = { panel : 'slots', parts : [ 'new' ] };
slots.showCurrent();
check( '...#slots/new the form of a new one', slots._isNew === true && form2.classList.contains('admin-hidden') === false );
tab.hash = { panel : 'slots', parts : [ 'nope' ] };
slots.showCurrent();
check( '...and a slot there is none of is the list', form2.classList.contains('admin-hidden') === true );
check( 'the slot a person types into a new form and leaves asks first, as every form does', ( function() {
	tab.hash = { panel : 'slots', parts : [ 'new' ] };
	slots.showCurrent();
	tab.registry._entries.slots.isDirty = function() { return true };
	tab.asked.length = 0;
	tab.hash = { panel : 'slots', parts : [] };
	slots.showCurrent();
	const asked = tab.asked.length === 1 && form2.classList.contains('admin-hidden') === false;
	tab.registry._entries.slots.isDirty = function() { return false };
	return asked;
} )() );
check( 'the one uri that would read as a view of the tab is written as one part, and is read back', slots._slotParts('/scan').join() === '/scan' && slots._slotParts('/logo/dark').join() === 'logo,dark'
	&& slots._slotAt( [ '/scan' ] ) === null && slots._slotAt( [ 'logo', 'dark' ] ).uri === '/logo/dark' );
tab.registry._entries.slots.isDirty = function() { return false };


// --- the scan -------------------------------------------------------------------------------------

slots._slots = catalogue;
tab.requests.length = 0;
tab.hash = { panel : 'slots', parts : [] };
slots.showCurrent();
const scanButton = buttonsOf( byClass( list, 'nino-admin-actionbar' )[0] ).find( b => has( b, 'nino-admin-btn-secondary' ) );
tab.routed.length = 0;
fire( scanButton, 'click' );
check( 'the scan has an address of its own and opens in the form level, waiting for the server with the focus in the view', tab.routed.join() === 'go slots/scan,set slots/scan' && tab.requests[0].action === 'slots/scan' && form2.classList.contains('admin-hidden') === false
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
	&& findAll( form2, el => el.tagName === 'LEGEND' )[0].textContent === '<img src=x onerror=1>' );

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

done();

} )();
