/**
 *	Nino										A compact filesystembased php framework
 *	admin-images-js-smoke.js	DOM-light checks for the Images panel's script
 *													(_admin/Nino/Modules/Images/assets/admin.js) and the
 *													Image Slots tab's row text (slots.js).
 *
 *													One slot, drawn as the panel draws it: where it is used,
 *													the Remove button that only an image earns, the warning
 *													for a picture below the target size, and one alt text
 *													input per language. The words are the module's real
 *													English ones, so a key the script asks for and no file
 *													defines shows up as an empty sentence here.
 *
 *	Usage: node tests/admin-images-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, source, fills, load, element, findAll, fire } = require( './harness.js' );

const byClass = function( root, name ) {
	return findAll( root, function( el ) { return el.className.split(' ').indexOf( name ) !== -1 } );
};


// --- the sandbox -----------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Images/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] ?? workbenchEn[key] ?? '';
}

const requests = [];
const asked = [];
let answerConfirm = true;

// The shell's counters (see Nino.admin.touch()): what the panels say they wrote
const touched = [];

const Nino = {
	admin : { touch : function( topic ) { touched.push( topic ) }, stamp : function() { return 0 } },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		getElementById : function() { return null },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = {
	Nino : Nino,
	location : { hash : '' },
	confirm : function( question ) { asked.push( question ); return answerConfirm },
};

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Images/assets/admin.js', context );
load( '_admin/Nino/Modules/Images/assets/slots.js', context );

const panel = Nino.admin.images;
panel._locales = [ 'de_DE', 'en_US' ];

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

function slot( overrides ) {
	return Object.assign( {
		uri : '/page-home/hero', label : 'Home hero', width : 1920, height : 1080, url : '/public/images/hero.jpg',
		alt : { de_DE : 'Ein Haus' }, usage : { templates : [ 'page-home' ], pages : [ { httpUri : '/', name : 'Startseite' } ] },
	}, overrides || {} );
}

const removeButton = function( fieldset ) { return byClass( fieldset, 'nino-admin-btn-danger' )[0] };
const previewOf = function( fieldset ) { return byClass( fieldset, 'nino-admin-field-image-preview' )[0] };
const messageOf = function( fieldset ) { return byClass( fieldset, 'nino-admin-field-image-msg' )[0] };

console.log('Admin Images');


// --- Remove ----------------------------------------------------------------

const withImage = panel._renderSlotField( slot() );
const withoutImage = panel._renderSlotField( slot( { url : null } ) );

check( 'a slot with an image offers Remove, in the danger style, with the module\'s own words', removeButton( withImage ) !== undefined && removeButton( withImage ).hidden === false
	&& removeButton( withImage ).textContent === 'Remove image' && removeButton( withImage ).type === 'button' );
check( '...and a slot without one has the button but does not show it', removeButton( withoutImage ).hidden === true );

answerConfirm = false;
fire( removeButton( withImage ), 'click' );
check( 'a cancelled question sends no request and leaves the picture', requests.length === 0 && previewOf( withImage ).hidden === false && removeButton( withImage ).hidden === false );
check( '...the question names the slot and what follows from it', asked.length === 1 && asked[0].indexOf( '“Home hero”' ) !== -1 && asked[0].indexOf( 'deleted' ) !== -1 );

answerConfirm = true;
fire( removeButton( withImage ), 'click' );
check( 'a confirmed one sends the removal for the slot', requests.length === 1 && requests[0].action === 'images/remove' && requests[0].payload.uri === '/page-home/hero' );
answer( 500, { error : 'could not save the slot' } );
check( 'a failure is an error beside the picture, which stays', messageOf( withImage ).className === 'nino-admin-field-image-msg is-error' && previewOf( withImage ).hidden === false && removeButton( withImage ).hidden === false );
check( '...and nothing was written, so nothing is said of it to the other panels', touched.length === 0 );

fire( removeButton( withImage ), 'click' );
answer( 200, { filename : null } );
check( 'a successful removal hides the preview and the button and says so', previewOf( withImage ).hidden === true && removeButton( withImage ).hidden === true && messageOf( withImage ).textContent === 'Image removed.' );
check( '...and tells the other panels that slots were written', touched.join() === 'slots' );


// --- the warning for a picture below the target size -----------------------

requests.length = 0;
const upload = panel._renderSlotField( slot( { url : null } ) );
const fileInput = findAll( upload, function( el ) { return el.type === 'file' } )[0];
fileInput.files = [ { size : 1000 } ];
fire( fileInput, 'change' );
check( 'a chosen file goes to the server as an upload for the slot', requests.length === 1 && requests[0].action === 'images/upload' && requests[0].payload.uri === '/page-home/hero' );
answer( 200, { filename : 'a.jpg', url : '/public/images/a.jpg', belowTarget : true, source : { width : 300, height : 150 } } );
check( 'a picture below the target is saved, and said to be in the warning style with its own size', messageOf( upload ).className === 'nino-admin-field-image-msg is-warning'
	&& messageOf( upload ).textContent.indexOf( '300 × 150 px' ) !== -1 && messageOf( upload ).textContent.indexOf( 'smaller than the target size' ) !== -1 );
check( '...and Remove is there from then on', removeButton( upload ).hidden === false && previewOf( upload ).hidden === false );
check( 'an upload that went through tells the other panels that slots were written', touched.join() === 'slots,slots' );

fire( fileInput, 'change' );
answer( 200, { filename : 'b.jpg', url : '/public/images/b.jpg', belowTarget : false, source : { width : 3000, height : 2000 } } );
check( 'a picture that is large enough is just saved', messageOf( upload ).className === 'nino-admin-field-image-msg' && messageOf( upload ).textContent === 'Saved.' );


// --- where a slot is used --------------------------------------------------

const usedLine = byClass( withImage, 'nino-admin-field-hint' )[0];
check( 'a slot that pages show says where: the page by name and http uri', usedLine.textContent === 'Used on: Startseite (/)' && usedLine.className === 'nino-admin-field-hint' );

const twoPages = byClass( panel._renderSlotField( slot( { usage : { templates : [], pages : [ { httpUri : '/a', name : '/a' }, { httpUri : '/b', name : 'B' } ] } } ) ), 'nino-admin-field-hint' )[0];
check( '...every page of them, and a page that has no name of its own by its http uri alone', twoPages.textContent === 'Used on: /a, B (/b)' );

const unusedLine = byClass( panel._renderSlotField( slot( { usage : { templates : [ 'page-x' ], pages : [] } } ) ), 'nino-admin-field-hint' )[0];
check( 'a slot no page shows warns in words, with the warning modifier', unusedLine.className === 'nino-admin-field-hint is-warning' && unusedLine.textContent.indexOf( 'Not included anywhere' ) === 0 );


// --- alt texts per language --------------------------------------------------

requests.length = 0;
const alt = panel._renderSlotField( slot() );
const altInputs = findAll( alt, function( el ) { return el.type === 'text' } );
check( 'there is one text input per language, each labelled with its language and holding what is stored', altInputs.length === 2 && altInputs[0].value === 'Ein Haus' && altInputs[1].value === ''
	&& altInputs.every( function( input ) { return input.maxLength === 250 } ) );
const altLabels = byClass( alt, 'nino-admin-field' ).map( function( label ) { return label.children[0].textContent } );
check( '...labelled "Alt text (de_DE)" and "Alt text (en_US)"', altLabels.join() === 'Alt text (de_DE),Alt text (en_US)' );

const altHint = byClass( alt, 'nino-admin-hint' ).filter( function( el ) { return el.id.indexOf( 'images-alt-hint-' ) === 0 } )[0];
check( 'one hint says that empty is decorative, and every input is wired to it with aria-describedby', altHint !== undefined && altHint.textContent.indexOf( 'Empty = decorative' ) !== -1
	&& altInputs.every( function( input ) { return input.getAttribute('aria-describedby') === altHint.id } ) );

const altSave = findAll( alt, function( el ) { return el.textContent === 'Save alt texts' } )[0];
altInputs[1].value = 'A house';
fire( altSave, 'click' );
check( 'saving posts the slot and every language\'s text, so an emptied input removes its entry', requests.length === 1 && requests[0].action === 'images/alt'
	&& JSON.stringify( requests[0].payload ) === JSON.stringify( { uri : '/page-home/hero', alt : { de_DE : 'Ein Haus', en_US : 'A house' } } ) );
touched.length = 0;
answer( 200, { alt : { de_DE : 'Ein Haus', en_US : 'A house' } } );
// The second such line of the slot: the first is the picture's own
const altStatus = byClass( alt, 'nino-admin-field-image-msg' )[1];
check( 'a saved alt text says so in a polite live region', altStatus.textContent === 'Alt texts saved.' && altStatus.getAttribute('aria-live') === 'polite' );
check( '...and tells the other panels that slots were written', touched.join() === 'slots' );
fire( altSave, 'click' );
answer( 400, { error : 'an alt text is at most 250 characters' } );
check( 'a refused one is an error', altStatus.className === 'nino-admin-field-image-msg is-error' );
check( '...and writes nothing to tell', touched.join() === 'slots' );

// The picture itself is named: by the first alt text the slot has, or by nothing - the name of the slot stands over it
check( 'the preview of a slot carries the alt text the slot has in the first language that has one', previewOf( alt ).alt === 'Ein Haus' && previewOf( panel._renderSlotField( slot( { alt : { en_US : 'A house' } } ) ) ).alt === 'A house' );
check( '...and an empty one where it has none, so that it is not read as a file name', previewOf( panel._renderSlotField( slot( { alt : {} } ) ) ).alt === '' && previewOf( panel._renderSlotField( slot( { alt : undefined } ) ) ).alt === '' );

requests.length = 0;
const fresh = panel._renderSlotField( slot( { alt : {} } ) );
findAll( fresh, function( el ) { return el.type === 'text' } )[0].value = 'Das Haus';
fire( findAll( byClass( fresh, 'images-slot-alt' )[0], function( el ) { return el.tagName === 'BUTTON' } )[0], 'click' );
answer( 200, { alt : { de_DE : 'Das Haus' } } );
check( '...and follows what was saved', previewOf( fresh ).alt === 'Das Haus' );

// --- alt texts typed and not saved -------------------------------------------

// A shell that has the registry: what Nino.admin.dirty does that the panel relies on - the
// entries it registered, a question before an exit where one of them is dirty, and nothing
// else (the real one is the shell script's and has its own checks)
const asks = [];
const shellRequests = [];
const registry = {
	_entries : {},
	register : function( name, entry ) { registry._entries[name] = entry },
	refresh : function() {},
	dirtyNames : function( names ) { return names.filter( function( name ) { return registry._entries[name] !== undefined && registry._entries[name].isDirty() === true } ) },
	guard : function( names, proceed, onCancel ) {
		if( registry.dirtyNames( names ).length === 0 )
			return proceed();
		asks.push( { names : names, proceed : proceed, onCancel : onCancel } );
	},
};
// The pane the shell draws: the name of the panel in its head, and the two levels beside it
const shellPane = element('div');
shellPane.dataset.panel = 'images';
const shellHead = element('div');
shellHead.className = 'admin-panel-head';
const shellTitle = element('h2');
shellTitle.className = 'admin-panel-title';
shellHead.appendChild( shellTitle );
shellPane.appendChild( shellHead );
const shellList = element('div');
shellList.id = 'images-list';
shellPane.appendChild( shellList );
const shellForm = element('div');
shellForm.id = 'images-form';
shellPane.appendChild( shellForm );
// The shell's router as far as the panel uses it: what it was asked to write, the hash a step
// through the history left, and leave() as the shell does it (ask first where a form is left)
const routed = [];
let routedHash = { panel : 'images', parts : [] };
const routedParts = [];
const routerStub = {
	set : function( panel, parts ) { routed.push( 'set '+ [ panel ].concat( parts ).join('/') ); routedParts.push( JSON.stringify( parts ) ) },
	go : function( panel, parts ) { routed.push( 'go '+ [ panel ].concat( parts ).join('/') ); routedParts.push( JSON.stringify( parts ) ) },
	current : function() { return routedHash },
	leave : function( names, leaving, proceed, resync ) { if( leaving === true ) return registry.guard( names, proceed, resync ); proceed() },
};
const shellTouched = [];
const shellStamps = { templates : 0 };
const shellNino = {
	admin : { dirty : registry, formToolbar : function( back ) { const bar = element('div'); bar.appendChild( back ); return bar }, router : routerStub,
		touch : function( topic ) { shellTouched.push( topic ) }, stamp : function( topic ) { return shellStamps[topic] ?? 0 } },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) { shellRequests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } ) } },
	content : { getText : text },
};
const shellSandbox = {
	console : console,
	document : { createElement : element, getElementById : function( id ) { return { 'images-form' : shellForm, 'images-list' : shellList }[id] ?? null }, documentElement : null, body : null,
		get activeElement() { return findAll( shellPane, function( el ) { return el.focused === true } )[0] ?? null } },
	Nino : shellNino,
};
shellSandbox.window = { Nino : shellNino, location : { hash : '' }, confirm : function() { return true } };
const shellContext = vm.createContext( shellSandbox );
load( '_admin/assets/Nino.admin.js', shellContext );
const imagesSource = source('_admin/Nino/Modules/Images/assets/admin.js');
vm.runInContext( imagesSource, shellContext, { filename : 'admin.js' } );

const shellPanel = shellNino.admin.images;
shellPanel._locales = [ 'de_DE', 'en_US' ];
const grouped = shellPanel._groupSlots( [ '/template/page-home/hero/image', '/template/page-home/about/image', '/template/frame-header/logo/image', '/logo', '/home/hero', '/constructor/x' ].map( uri => ( { uri : uri } ) ) );
check( 'slots are grouped as the Text panel groups keys: /template/<category> for a key of the grammar, the first segment for any other',
	Object.keys( grouped ).join() === 'template/page-home,template/frame-header,logo,home,constructor' && grouped['template/page-home'].length === 2 && grouped['template/frame-header'].length === 1 );
const logoGroups = shellPanel._groupSlots( [ '/logo', '/logo/dark' ].map( uri => ( { uri : uri } ) ) );
check( 'the two logo slots, the picture for a light ground and the one for a dark ground, are one group in the list: the first segment of /logo/dark is logo',
	Object.keys( logoGroups ).join() === 'logo' && logoGroups.logo.map( entry => entry.uri ).join() === '/logo,/logo/dark' );
shellPanel._groups = { 'page-home' : [ slot() ] };
const shellAnswer = function( status, body ) { shellRequests[shellRequests.length - 1].callback( { status : status, responseJSON : body } ) };
const registered = registry._entries.images;

check( 'the panel registers with the shell as "images", with all three of isDirty, save and discard', registered !== undefined
	&& typeof registered.isDirty === 'function' && typeof registered.save === 'function' && typeof registered.discard === 'function' );
const registration = imagesSource.indexOf( "Nino.admin.dirty.register( 'images'" );
check( '...behind a check that the shell has the registry', registration !== -1 && imagesSource.slice( 0, registration ).trimEnd().endsWith( "if( typeof Nino.admin.dirty === 'object' )" ) );

shellPanel._currentGroup = 'page-home';
shellPanel._renderGroupForm();
shellForm.classList.remove('admin-hidden');
const shellInputs = findAll( shellForm, function( el ) { return el.type === 'text' } );
check( 'a category as drawn holds nothing unsaved', registered.isDirty() === false && shellInputs.length === 2 );

shellInputs[1].value = 'A house';
check( 'an alt text typed into it is unsaved input', registered.isDirty() === true );
shellInputs[1].value = '';
check( '...typed away again it is not', registered.isDirty() === false );

shellForm.classList.add('admin-hidden');
shellInputs[1].value = 'A house';
check( '...and a form that is not on screen is not asked about', registered.isDirty() === false );
shellForm.classList.remove('admin-hidden');

shellPanel._openGroup( 'page-home' );
check( 'opening a category while alt texts are unsaved asks first and draws nothing', asks.length === 1 && JSON.stringify( asks[0].names ) === '["images"]' && shellInputs[1].value === 'A house' && shellRequests.length === 0 );

asks[0].proceed();
const redrawn = findAll( shellForm, function( el ) { return el.type === 'text' } );
check( '...an answer that lets it go draws the category again, with what is stored', redrawn.length === 2 && redrawn[1].value === '' && registered.isDirty() === false );

shellInputs.length = 0;
redrawn[1].value = 'A house';
registered.discard();
check( 'discarding puts every input back to what is stored', redrawn[0].value === 'Ein Haus' && redrawn[1].value === '' && registered.isDirty() === false );

redrawn[0].value = 'Ein Haus am See ';
redrawn[1].value = 'A house';
let ended = [];
registered.save( function( ok ) { ended.push( ok ) } );
check( 'the shell\'s Save posts the slot with every language\'s text and waits for the answer', shellRequests.length === 1 && shellRequests[0].action === 'images/alt' && ended.length === 0
	&& JSON.stringify( shellRequests[0].payload ) === JSON.stringify( { uri : '/page-home/hero', alt : { de_DE : 'Ein Haus am See ', en_US : 'A house' } } ) );
shellAnswer( 200, { alt : { de_DE : 'Ein Haus am See', en_US : 'A house' } } );
check( '...reports true, and what was stored (cleaned) is what the inputs hold, so it counts as saved', ended.join() === 'true' && redrawn[0].value === 'Ein Haus am See' && registered.isDirty() === false );

redrawn[1].value = 'The house';
registered.save( function( ok ) { ended.push( ok ) } );
shellAnswer( 400, { error : 'an alt text is at most 250 characters' } );
check( 'a refused Save reports false, says why and leaves the text where it is, unsaved', ended.join() === 'true,false' && registered.isDirty() === true && redrawn[1].value === 'The house' );

registered.save( function( ok ) { ended.push( ok ) } );
const second = shellRequests.length;
registered.save( function( ok ) { ended.push( ok ) } );
check( 'a second Save while one runs reports false and sends nothing of its own', ended.join() === 'true,false,false' && shellRequests.length === second );
shellAnswer( 200, { alt : { de_DE : 'Ein Haus am See', en_US : 'The house' } } );
check( '...the first one still ends', ended.join() === 'true,false,false,true' && registered.isDirty() === false );

registered.save( function( ok ) { ended.push( ok ) } );
check( 'a Save with nothing unsaved reports true without a request', ended[ended.length - 1] === true && shellRequests.length === second );


// --- the browser's Back and Forward --------------------------------------------------
// A step through the history changes the address and nothing else: the panel shows the level
// the hash names, asking first where that leaves a form with unsaved alt texts

shellPanel._ready = true;
routed.length = 0;
shellPanel._openGroup( 'page-home' );
check( 'opening a category is a step Back returns to: the address is pushed before the form writes it', routed.join() === 'go images/page-home,set images/page-home' );
shellForm.classList.remove('admin-hidden');
shellList.classList.add('admin-hidden');

routed.length = 0;
asks.length = 0;
routedHash = { panel : 'images', parts : [] };
shellPanel.showCurrent();
check( 'a hash that names the list shows it, leaving the form with nothing unsaved without a question', asks.length === 0 && shellForm.classList.contains('admin-hidden') === true && shellList.classList.contains('admin-hidden') === false && routed.join() === 'set images' );

routedHash = { panel : 'images', parts : [ 'page-home' ] };
shellPanel.showCurrent();
check( '...and one that names a category draws it', shellForm.classList.contains('admin-hidden') === false && shellList.classList.contains('admin-hidden') === true && routed[routed.length - 1] === 'set images/page-home' );

findAll( shellForm, function( el ) { return el.type === 'text' } )[1].value = 'A house';
routed.length = 0;
routedHash = { panel : 'images', parts : [] };
shellPanel.showCurrent();
check( 'Back out of a form with unsaved alt texts asks first and shows nothing yet', asks.length === 1 && JSON.stringify( asks[0].names ) === '["images"]' && shellForm.classList.contains('admin-hidden') === false && routed.length === 0 );
asks[0].onCancel();
check( '...a Cancel keeps the form and writes its level back into the address', shellForm.classList.contains('admin-hidden') === false && routed.join() === 'set images/page-home' );
shellPanel.showCurrent();
asks[1].proceed();
check( '...an answer that lets it go shows the list', shellForm.classList.contains('admin-hidden') === true && routed[routed.length - 1] === 'set images' );

routed.length = 0;
routedHash = { panel : 'images', parts : [ 'nope' ] };
shellPanel.showCurrent();
check( 'a hash that names a category there is not falls back to the list and writes it', shellForm.classList.contains('admin-hidden') === true && routed.join() === 'set images' );

routed.length = 0;
shellPanel._currentGroup = 'page-home';
shellForm.classList.remove('admin-hidden');
shellList.classList.add('admin-hidden');
routedHash = { panel : 'dashboard', parts : [] };
shellPanel.showCurrent();
check( 'a hash that names another panel (a click on the rail) keeps the level in memory and writes it', shellForm.classList.contains('admin-hidden') === false && routed.join() === 'set images/page-home' );

const backLinks = findAll( shellForm, function( el ) { return el.className === 'nino-admin-back-link' } );
routed.length = 0;
fire( backLinks[0], 'click' );
check( 'the back link is a step Back returns to as well', routed.join() === 'go images,set images' && shellForm.classList.contains('admin-hidden') === true );

// --- the focus -------------------------------------------------------------------

/*	A category is opened by its button, which is hidden with the list it stands in:
	the focus goes into the form, Back gives it back to the button - found again where
	the list was drawn anew - and a form that the address closes leaves it the name of the panel	*/
shellRequests.length = 0;
shellPanel._ready = true;
shellPanel._groups = { 'page-home' : [ slot() ] };
shellPanel._renderCategoryList();
shellPanel._showList();

const groupButton = function() { return findAll( shellList, function( el ) { return el.tagName === 'BUTTON' } ).find( function( b ) { return b.dataset.group === 'page-home' } ) };
const shellFocused = function() { return findAll( shellPane, function( el ) { return el.focused === true } )[0] ?? null };
const shellBack = function() { return findAll( shellForm, function( el ) { return el.className === 'nino-admin-back-link' } )[0] };

fire( groupButton(), 'click' );
check( 'opening a category shows its form and moves the focus into it', shellForm.classList.contains('admin-hidden') === false && shellList.classList.contains('admin-hidden') === true && shellFocused() === shellBack() && shellBack() !== undefined );

shellPanel._renderCategoryList();
fire( shellBack(), 'click' );
check( 'Back shows the list and puts the focus on the button of the category - the one drawn now', shellList.classList.contains('admin-hidden') === false && shellForm.classList.contains('admin-hidden') === true && shellFocused() === groupButton() );

fire( groupButton(), 'click' );
routedHash = { panel : 'images', parts : [] };
shellPanel.showCurrent();
check( 'a form the address closes - a step back through the history - leaves the focus the name of the panel', shellList.classList.contains('admin-hidden') === false && shellFocused() === shellTitle );
routed.length = 0;

// --- a category of the grammar: its name has a slash, the address has its segments -------

shellPanel._groups = { 'page-home' : [ slot() ], 'template/page-home' : [ slot( { uri : '/template/page-home/hero/image' } ) ], logo : [ slot( { uri : '/logo' } ) ] };
shellPanel._renderCategoryList();
const listLabels = {};
findAll( shellList, function( el ) { return el.className === 'admin-type-btn' } ).forEach( function( btn ) { listLabels[btn.dataset.group] = btn.children[0].textContent } );
check( 'the list names a category by the vocabulary - the category of a grammar group, the word for any other', listLabels['template/page-home'] === shellNino.adminUi.slugLabel('page-home') && listLabels['template/page-home'] !== 'template/page-home'
	&& listLabels.logo === shellNino.adminUi.slugLabel('logo') && listLabels.logo !== 'logo' && listLabels['template/page-home'] !== undefined );
check( '...and that is Nino.adminUi.slugLabel() of the last segment, nothing else', Object.keys( listLabels ).every( function( group ) { return listLabels[group] === shellNino.adminUi.slugLabel( group.split('/').pop() ) } ) );

routed.length = 0;
routedParts.length = 0;
shellPanel._openGroup( 'template/page-home' );
check( 'a category with a slash in its name is written as its segments, as the Text panel writes a row', routedParts.join() === '["template","page-home"],["template","page-home"]' && routed.join() === 'go images/template/page-home,set images/template/page-home' );
check( '...and the form is headed by the category\'s name', findAll( shellForm, function( el ) { return el.className === 'main-title' } )[0].textContent === shellNino.adminUi.slugLabel('page-home') );

shellForm.classList.add('admin-hidden');
shellList.classList.remove('admin-hidden');
shellPanel._currentGroup = null;
routed.length = 0;
routedHash = { panel : 'images', parts : [ 'template', 'page-home' ] };
shellPanel.showCurrent();
check( 'a hash of segments opens that category', shellForm.classList.contains('admin-hidden') === false && shellPanel._currentGroup === 'template/page-home' && routed[routed.length - 1] === 'set images/template/page-home' );

shellForm.classList.add('admin-hidden');
shellList.classList.remove('admin-hidden');
shellPanel._currentGroup = null;
routedHash = { panel : 'images', parts : [ 'template/page-home' ] };
shellPanel.showCurrent();
check( '...and so does an address written with the group as one part, as it was before', shellForm.classList.contains('admin-hidden') === false && shellPanel._currentGroup === 'template/page-home' );

routedHash = { panel : 'images', parts : [ 'template' ] };
shellForm.classList.add('admin-hidden');
shellList.classList.remove('admin-hidden');
routed.length = 0;
shellPanel.showCurrent();
check( 'half a category is none: the list', shellForm.classList.contains('admin-hidden') === true && routed.join() === 'set images' );

// The page load restores the same hash: the router parses the parts back to the category
shellPanel._ready = false;
shellPanel._groupSlots = function() { return { 'template/page-home' : [ slot( { uri : '/template/page-home/hero/image' } ) ] } };
shellForm.classList.add('admin-hidden');
shellList.classList.remove('admin-hidden');
routedHash = { panel : 'images', parts : [ 'template', 'page-home' ] };
shellPanel._apiCall = function( endpoint, payload, callback ) { callback( 200, { slots : [], locales : [ 'de_DE', 'en_US' ] } ) };
shellPanel.init();
check( 'a reload on #images/template/page-home opens the category', shellPanel._currentGroup === 'template/page-home' && shellForm.classList.contains('admin-hidden') === false );

shellPanel._ready = false;

// --- a template written since the slots were read -----------------------------------------
// Each slot says which pages use it, so a template the Builder creates after the list was read
// is not in it: the panel remembers the stamp of the templates it asked with and reads again
// at the next showing - not while a category is open, and not while the request is out

shellPanel._groupSlots = Nino.admin.images._groupSlots;
shellForm.classList.add('admin-hidden');
shellList.classList.remove('admin-hidden');
routedHash = { panel : 'images', parts : [] };
shellRequests.length = 0;
shellPanel._apiCall = function( endpoint, payload, callback ) { shellRequests.push( { action : endpoint, payload : payload, callback : function( reply ) { callback( reply.status, reply.responseJSON ) } } ) };
shellPanel.init();
shellAnswer( 200, { slots : [ slot( { uri : '/template/page-home/hero/image' } ) ], locales : [ 'de_DE', 'en_US' ] } );
check( 'the panel asked with the stamp the templates had', shellPanel._ready === true && shellPanel._stamp === 0 );

shellPanel.showCurrent();
check( 'shown again with no template written since, it asks for nothing', shellRequests.length === 1 );

shellStamps.templates++;
shellForm.classList.remove('admin-hidden');
shellList.classList.add('admin-hidden');
shellPanel._currentGroup = 'template/page-home';
routedHash = { panel : 'images', parts : [ 'template', 'page-home' ] };
shellPanel.showCurrent();
check( 'a template written while a category is open is not read now: the form is not drawn over', shellRequests.length === 1 && shellForm.classList.contains('admin-hidden') === false );

shellForm.classList.add('admin-hidden');
shellList.classList.remove('admin-hidden');
routedHash = { panel : 'images', parts : [] };
shellPanel.showCurrent();
check( '...the showing that finds none open reads the slots again', shellRequests.length === 2 && shellRequests[1].action === 'list' && shellPanel._ready === false );

shellStamps.templates++;
shellPanel.showCurrent();
check( '...once, whatever is written while the request is out', shellRequests.length === 2 );
shellAnswer( 200, { slots : [ slot( { uri : '/template/page-home/hero/image' } ), slot( { uri : '/template/page-new/hero/image' } ) ], locales : [ 'de_DE', 'en_US' ] } );
check( '...and the stamp is the one from before it went out, so what was written meanwhile is read at the next showing', shellPanel._ready === true && shellPanel._stamp === 1 && Object.keys( shellPanel._groups ).join() === 'template/page-home,template/page-new' );
shellPanel.showCurrent();
check( '...which asks again', shellRequests.length === 3 );
shellAnswer( 200, { slots : [ slot( { uri : '/template/page-home/hero/image' } ) ], locales : [ 'de_DE', 'en_US' ] } );
shellPanel.showCurrent();
check( 'and with nothing written after the last answer, the next showing asks for nothing', shellRequests.length === 3 && shellPanel._stamp === 2 );

// Whatever the server or an editor wrote reaches the page as text: the only innerHTML here empties a container
check( 'nothing the panel is told is written as markup - innerHTML is only ever emptied', ( source('_admin/Nino/Modules/Images/assets/admin.js').match( /innerHTML[^\n]*/g ) || [] ).every( function( use ) { return /^innerHTML = '';$/.test( use ) } ) );


// --- the Slots tab row ---------------------------------------------------------

const slots = Nino.admin.slots;
check( 'a slot some page shows adds nothing to its row', slots._usageLabel( { usage : { templates : [ 'page-home' ], pages : [ { httpUri : '/', name : 'x' } ] } } ) === '' );
check( 'a slot no page shows says it is not included anywhere', slots._usageLabel( { usage : { templates : [], pages : [] } } ) === ', not included anywhere' );
check( '...and names the templates that mention it, if any', slots._usageLabel( { usage : { templates : [ 'page-a', 'page-b' ], pages : [] } } ) === ', not included anywhere, Templates: page-a, page-b' );
check( 'a slot without usage data reads as unused rather than throwing', slots._usageLabel( {} ) === ', not included anywhere' );


// --- the Slots tab says what it wrote ---------------------------------------------------------

const slotsNodes = {};
const slotsNode = id => slotsNodes[id] = slotsNodes[id] ?? { id : id, value : '', textContent : '', classList : { contains : () => false, add : () => {}, remove : () => {} } };
const slotsTouched = [];
const slotsNino = {
	admin : { touch : topic => slotsTouched.push( topic ), stamp : () => 0 },
	events : { bindCallback : function() {} },
	http : { sendRequest : function() {} },
	content : { getText : text },
};
const slotsSandbox = { console : console, document : { createElement : element, getElementById : slotsNode, documentElement : null, body : null }, Nino : slotsNino };
let slotsConfirm = true;
slotsSandbox.window = { Nino : slotsNino, confirm : () => slotsConfirm, alert : () => {} };
const slotsContext = vm.createContext( slotsSandbox );
load( '_admin/assets/Nino.admin.js', slotsContext );
load( '_admin/Nino/Modules/Images/assets/slots.js', slotsContext );
const slotsPanel = slotsNino.admin.slots;
const slotsSent = [];
slotsPanel._apiCall = function( endpoint, payload, callback ) { slotsSent.push( { endpoint : endpoint, callback : callback } ) };
slotsPanel._snapshot = function() {};
slotsPanel.init = function() {};
const slotsAnswer = function( status, body ) { slotsSent[slotsSent.length - 1].callback( status, body ) };

slotsPanel._isNew = true;
slotsNode('slots-form-uri').value = '/page-new/hero';
slotsPanel._save();
slotsAnswer( 500, { error : 'no' } );
check( 'a slot that could not be created tells nobody that slots were written', slotsSent[0].endpoint === 'create' && slotsTouched.length === 0 );
slotsPanel._save();
slotsAnswer( 200, {} );
check( 'a slot that was created does', slotsTouched.join() === 'slots' );

slotsPanel._isNew = false;
slotsPanel._currentUri = '/page-new/hero';
slotsPanel._save();
slotsAnswer( 200, {} );
check( 'a slot that was saved does too', slotsSent[slotsSent.length - 1].endpoint === 'save' && slotsTouched.join() === 'slots,slots' );

slotsConfirm = false;
const slotsBefore = slotsSent.length;
slotsPanel._delete();
check( 'a delete that is answered No sends nothing', slotsSent.length === slotsBefore );
slotsConfirm = true;
slotsPanel._delete();
slotsAnswer( 403, null );
check( 'a delete that was refused does not', slotsSent[slotsSent.length - 1].endpoint === 'delete' && slotsTouched.length === 2 );
slotsPanel._delete();
slotsAnswer( 200, {} );
check( 'one that went through does', slotsTouched.join() === 'slots,slots,slots' );

slotsTouched.length = 0;
slotsPanel._saveScanResults( [
	{ ignoreCheck : { checked : false }, uriInput : { value : '/a' }, labelInput : { value : 'A' }, widthInput : { value : '100' }, heightInput : { value : '100' } },
	{ ignoreCheck : { checked : false }, uriInput : { value : '/b' }, labelInput : { value : 'B' }, widthInput : { value : '100' }, heightInput : { value : '100' } },
] );
slotsAnswer( 200, {} );
slotsAnswer( 409, { error : 'taken' } );
check( 'the slots a scan creates are told one by one, the one that was refused not', slotsTouched.join() === 'slots' && slotsSent[slotsSent.length - 1].endpoint === 'create' );


// --- the Slots tab opens a slot and goes back to it ------------------------------------------

/*	A row opens a form and writes no address, so it is a button and not a link to nowhere.
	What is opened takes the focus into the form, Back gives it to the row or the button
	that opened it, and a form closed by a save leaves it the name of the panel	*/
{
	const pane = element('div');
	pane.dataset.panel = 'slots';
	const head = element('div');
	head.className = 'admin-panel-head';
	const paneTitle = element('h2');
	paneTitle.className = 'admin-panel-title';
	head.appendChild( paneTitle );
	pane.appendChild( head );
	const list = element('div');
	list.id = 'slots-list';
	pane.appendChild( list );
	const form = element('div');
	form.id = 'slots-form';
	form.classList.add('admin-hidden');
	pane.appendChild( form );

	const page = {
		admin : { formToolbar : function( back ) { const bar = element('div'); bar.appendChild( back ); return bar }, touch : function() {}, stamp : function() { return 0 } },
		events : { bindCallback : function() {} },
		http : { sendRequest : function() {} },
		content : { getText : text },
	};
	const box = {
		console : console,
		document : {
			createElement : element,
			createTextNode : function( value ) { return Object.assign( element('#text'), { textContent : String( value ) } ) },
			getElementById : function( id ) { return findAll( pane, function( el ) { return el.id === id } )[0] ?? null },
			documentElement : null,
			body : null,
			get activeElement() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null },
		},
		Nino : page,
	};
	box.window = { Nino : page, confirm : () => true, alert : () => {} };
	const context = vm.createContext( box );
	load( '_admin/assets/Nino.admin.js', context );
	load( '_admin/Nino/Modules/Images/assets/slots.js', context );

	const drawn = page.admin.slots;
	let scanned = null;
	drawn._apiCall = function( endpoint, payload, callback ) { if( endpoint === 'scan' ) scanned = callback };
	drawn._snapshot = function() {};
	drawn._slots = [ slot(), slot( { uri : '/page-home/logo', label : 'Logo', hasImage : false } ) ];
	drawn._renderList();
	drawn._showList();

	const rows = findAll( list, function( el ) { return el.className === 'nino-admin-list-link' } );
	const focusedNow = function() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null };
	const backLinkOf = function() { return findAll( form, function( el ) { return el.className === 'nino-admin-back-link' } )[0] };

	check( 'a slot is a row that opens its form: a button, because no address is written', rows.length === 2 && rows.every( function( row ) { return row.tagName === 'BUTTON' && row.type === 'button' && row.href === '' } ) );

	fire( rows[0], 'click' );
	check( 'opening a slot shows its form and moves the focus into it', form.classList.contains('admin-hidden') === false && list.classList.contains('admin-hidden') === true && backLinkOf() !== undefined && focusedNow() === backLinkOf() );
	fire( backLinkOf(), 'click' );
	check( 'Back shows the list and puts the focus on the row that was opened', list.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true && focusedNow() === rows[0] );

	const buttons = findAll( list, function( el ) { return el.tagName === 'BUTTON' && el.className !== 'nino-admin-list-link' } );
	const scan = buttons.find( function( b ) { return b.className === 'nino-admin-btn-secondary' } );
	const add = buttons.find( function( b ) { return b.className === 'nino-admin-btn-primary' } );
	fire( add, 'click' );
	check( 'a new slot opens the same way', form.classList.contains('admin-hidden') === false && focusedNow() === backLinkOf() );
	fire( backLinkOf(), 'click' );
	check( '...and Back returns to the New slot button', focusedNow() === add );

	fire( scan, 'click' );
	check( 'the scan opens in the form level, with the focus in it', form.classList.contains('admin-hidden') === false && focusedNow() === backLinkOf() );
	const waiting = backLinkOf();
	scanned( 200, { missing : [] } );
	check( '...and the form its answer draws over the one that waited has the focus on its own back link - not the body', backLinkOf() !== waiting && focusedNow() === backLinkOf() );
	fire( backLinkOf(), 'click' );
	check( '...and Back returns to the Scan button', focusedNow() === scan );

	fire( rows[1], 'click' );
	findAll( form, function( el ) { return el.tagName === 'BUTTON' } )[0].focus();
	drawn._showList();
	check( 'a form that is closed without Back leaves the focus nothing to stand on: it goes to the name of the panel', list.classList.contains('admin-hidden') === false && focusedNow() === paneTitle );
}


// --- the scan form asks for a size ----------------------------------------------------------

/*	A slot is made with a size: it is the canvas every upload is cut to. The rows of the scan that
	come without one - a slot a component names and nobody made has none to propose - mark their
	two fields as required and are refused at them when they are submitted, and the rows that have
	a size are made all the same	*/
{
	const pane = element('div');
	pane.dataset.panel = 'slots';
	const list = element('div');
	list.id = 'slots-list';
	pane.appendChild( list );
	const form = element('div');
	form.id = 'slots-form';
	pane.appendChild( form );

	const written = [];
	const alerts = [];
	const page = {
		admin : { formToolbar : function( back ) { const bar = element('div'); bar.appendChild( back ); return bar }, touch : function( topic ) { written.push( topic ) }, stamp : function() { return 0 } },
		events : { bindCallback : function() {} },
		http : { sendRequest : function() {} },
		content : { getText : text },
	};
	const box = {
		console : console,
		document : {
			createElement : element,
			createTextNode : function( value ) { return Object.assign( element('#text'), { textContent : String( value ) } ) },
			getElementById : function( id ) { return findAll( pane, function( el ) { return el.id === id } )[0] ?? null },
			documentElement : null,
			body : null,
			get activeElement() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null },
		},
		Nino : page,
	};
	box.window = { Nino : page, confirm : () => true, alert : function( message ) { alerts.push( message ) } };
	const context = vm.createContext( box );
	load( '_admin/assets/Nino.admin.js', context );
	load( '_admin/Nino/Modules/Images/assets/slots.js', context );

	const drawn = page.admin.slots;
	const sent = [];
	const refreshed = [];
	drawn._apiCall = function( endpoint, payload, callback ) { sent.push( { endpoint : endpoint, payload : payload, callback : callback } ) };
	drawn._snapshot = function() {};
	drawn.init = function( stay ) { refreshed.push( stay === true ) };

	const rowsOnScreen = function() { return findAll( form, function( el ) { return el.className === 'nino-admin-field' } ) };
	const numbers = function( row ) { return findAll( row, function( el ) { return el.type === 'number' } ) };
	const sentencesOf = function( row ) { return findAll( row, function( el ) { return el.className === 'nino-admin-field-error' } ) };
	const starsOf = function( row ) { return findAll( row, function( el ) { return el.className === 'nino-admin-required' } ) };
	const scanMessage = function() { return findAll( form, function( el ) { return el.id === 'slots-scan-msg' } )[0] };
	const focusedNow = function() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null };

	drawn._renderScanForm( [
		{ filename : 'a.png', src : '/images/a.png', suggestedUri : '/a', width : 100, height : 50, files : [ 'page-a.tpl' ] },
		{ filename : '', src : '/template/page-x/cards/one-background', suggestedUri : '/template/page-x/cards/one-background', width : 0, height : 0, files : [ 'page-x.tpl' ] },
		{ filename : 'c.png', src : '/images/c.png', suggestedUri : '/c', width : 80, height : 0, files : [ 'page-c.tpl' ] },
	] );
	let rows = rowsOnScreen();
	check( 'a row that comes with a size asks for none: no star, nothing required', rows.length === 3 && starsOf( rows[0] ).length === 0 && numbers( rows[0] ).every( function( input ) { return input.required !== true && input.getAttribute('aria-required') === null } ) );
	check( 'a row without one marks its width and its height as required - the star after each name, aria-required on each field', starsOf( rows[1] ).length === 2 && starsOf( rows[1] ).every( function( star ) { return star.getAttribute('aria-hidden') === 'true' } )
		&& numbers( rows[1] ).length === 2 && numbers( rows[1] ).every( function( input ) { return input.required === true && input.getAttribute('aria-required') === 'true' } ) );
	check( '...and so does a row that knows its width and not its height: it has no size to make a slot with', starsOf( rows[2] ).length === 2 && numbers( rows[2] ).every( function( input ) { return input.required === true } ) );
	check( 'the form leaves the checking to the script: the browser\'s own bubble would be a second voice, and would stop the submit before the sentence at the field is said', findAll( form, function( el ) { return el.tagName === 'FORM' } )[0].noValidate === true );
	const ignoreOf = function( row ) { return findAll( row, function( el ) { return el.type === 'checkbox' } )[0] };
	ignoreOf( rows[1] ).checked = true;
	fire( ignoreOf( rows[1] ), 'change' );
	check( 'a row that is ignored asks for nothing: a slot nobody makes needs no size', numbers( rows[1] ).every( function( input ) { return input.required !== true && input.getAttribute('aria-required') === null } ) && starsOf( rows[1] ).every( function( star ) { return star.hidden === true } ) );
	ignoreOf( rows[1] ).checked = false;
	fire( ignoreOf( rows[1] ), 'change' );
	check( '...and asks again when it is not', numbers( rows[1] ).every( function( input ) { return input.required === true } ) );

	let outcome = null;
	drawn._saveScanResults( drawn._scanRows, function( ok ) { outcome = ok } );
	check( 'a submit makes the row that has a size and sends none of those without', sent.length === 1 && sent[0].endpoint === 'create' && sent[0].payload.uri === '/a' && sent[0].payload.width === '100' );
	check( 'each row without a size is refused at its fields: one sentence in the row, both fields marked invalid and described by it', [ rows[1], rows[2] ].every( function( row ) {
		const sentence = sentencesOf( row );
		return sentence.length === 1 && sentence[0].textContent === text('/_admin/slots/error/size') && numbers( row ).every( function( input ) { return input.getAttribute('aria-invalid') === 'true' && input.getAttribute('aria-describedby') === sentence[0].id } );
	} ) );
	check( '...the row that has a size is not', sentencesOf( rows[0] ).length === 0 && numbers( rows[0] ).every( function( input ) { return input.getAttribute('aria-invalid') === null } ) );
	check( '...the line says that something is wrong, as an alert, and the focus is on the first field refused', scanMessage().getAttribute('role') === 'alert' && scanMessage().textContent === text('/_admin/error/invalid_value') && focusedNow() === numbers( rows[1] )[0] );
	check( 'the sentence is words and is a key both languages have', text('/_admin/slots/error/size') !== '' && fills('_admin/Nino/Modules/Images/text/de_DE.php')['/_admin/slots/error/size'] !== undefined );

	sent[0].callback( 200, {} );
	rows = rowsOnScreen();
	check( 'the slot that was made is gone from the form, and the ones that were refused stay with their sentences; the list under the form is drawn again and not shown',
		outcome === false && rows.length === 2 && written.join() === 'slots' && refreshed.join() === 'true' && rows.every( function( row ) { return sentencesOf( row ).length === 1 } ) && drawn._scanRows.length === 2 );
	check( '...the form is not the one the shell sees as done: it reported false, and the line is the refusal again and not the progress of the run', scanMessage().getAttribute('role') === 'alert' && scanMessage().textContent === text('/_admin/error/invalid_value') );

	// What is typed into the fields that makes a size whole takes the sentence away
	const [ widthOfFirst, heightOfFirst ] = numbers( rows[0] );
	widthOfFirst.value = '20';
	fire( widthOfFirst, 'input' );
	check( 'a width alone is not a size: the sentence stays', sentencesOf( rows[0] ).length === 1 );
	heightOfFirst.value = '10';
	fire( heightOfFirst, 'input' );
	check( '...the height that makes it whole takes the sentence and the marks away - and only in that row', sentencesOf( rows[0] ).length === 0 && numbers( rows[0] ).every( function( input ) { return input.getAttribute('aria-invalid') === null && input.getAttribute('aria-describedby') === null } ) && sentencesOf( rows[1] ).length === 1 );

	sent.length = 0;
	outcome = null;
	numbers( rows[1] )[1].value = '40';
	fire( numbers( rows[1] )[1], 'input' );
	drawn._saveScanResults( drawn._scanRows, function( ok ) { outcome = ok } );
	check( 'once every row has a size nothing is refused, and the rows are made one request at a time', sentencesOf( rows[0] ).length === 0 && sentencesOf( rows[1] ).length === 0 && sent.length === 1 );
	sent[0].callback( 200, {} );
	check( '...the next after the answer to the one before', sent.length === 2 && outcome === null );
	sent[1].callback( 200, {} );
	check( '...and when none was refused the list is shown, as ever, and the save is done', outcome === true && refreshed.join() === 'true,false' );

	// A row the server refused stays as well: only the slot that was made leaves the form
	drawn._renderScanForm( [
		{ filename : 'd.png', src : '/images/d.png', suggestedUri : '/d', width : 10, height : 10, files : [ 'page-d.tpl' ] },
		{ filename : 'e.png', src : '/images/e.png', suggestedUri : '/e', width : 20, height : 20, files : [ 'page-e.tpl' ] },
		{ filename : '', src : '/template/page-z/a', suggestedUri : '/template/page-z/a', width : 0, height : 0, files : [ 'page-z.tpl' ] },
	] );
	sent.length = 0;
	outcome = null;
	alerts.length = 0;
	drawn._saveScanResults( drawn._scanRows, function( ok ) { outcome = ok } );
	sent[0].callback( 200, {} );
	sent[1].callback( 409, { error : 'exists' } );
	rows = rowsOnScreen();
	check( 'a row the server refused stays in the form with the one that has no size, and the slot that was made is gone from it', outcome === false && rows.length === 2 && drawn._scanRows.length === 2
		&& rows.map( function( row ) { return findAll( row, function( el ) { return el.type === 'text' } )[0].value } ).join() === '/e,/template/page-z/a' && alerts.length === 1 );

	// Nothing to send: every row came without a size
	drawn._renderScanForm( [
		{ filename : '', src : '/template/page-y/a', suggestedUri : '/template/page-y/a', width : 0, height : 0, files : [ 'page-y.tpl' ] },
		{ filename : '', src : '/template/page-y/b', suggestedUri : '/template/page-y/b', width : 0, height : 0, files : [ 'page-y.tpl' ] },
	] );
	sent.length = 0;
	outcome = null;
	drawn._saveScanResults( drawn._scanRows, function( ok ) { outcome = ok } );
	check( 'rows that all lack a size send nothing, are all refused at their fields, and report that the save did not happen', sent.length === 0 && outcome === false && rowsOnScreen().every( function( row ) { return sentencesOf( row ).length === 1 } ) );

	// The server refuses it too, with the code of the field it names
	check( 'the server\'s refusal has the words: the code a width or a height under 1 is refused with is one the workbench says', text('/_admin/error/invalid_value') !== '' );
}

done();
