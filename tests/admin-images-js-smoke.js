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

/** The fills of one text file, key => value - enough of the php to read a `'[[/key]]' => 'value',` line */
function fills( relative ) {
	const map = {};
	const re = /'\[\[(\/_admin\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g;
	const text = source( relative );
	let match;
	while( ( match = re.exec( text ) ) )
		map[match[1]] = match[2].replace( /\\'/g, "'" );
	return map;
}


// --- an element stand-in ---------------------------------------------------

function classList( el ) {
	return {
		add : function( value ) { if( el.className.split(' ').indexOf( value ) === -1 ) el.className = ( el.className+ ' '+ value ).trim() },
		remove : function( value ) { el.className = el.className.split(' ').filter( function( v ) { return v !== value } ).join(' ') },
		contains : function( value ) { return el.className.split(' ').indexOf( value ) !== -1 },
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
		type : '',
		href : '',
		id : '',
		value : '',
		maxLength : -1,
		disabled : false,
		hidden : false,
		src : '',
		dataset : {},
		attributes : {},
		children : [],
		listeners : {},
		appendChild : function( child ) { el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		getAttribute : function( name ) { return el.attributes[name] ?? null },
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
	};
	el.classList = classList( el );
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

function fire( el, name ) {
	if( el === null || el === undefined )
		return;
	( el.listeners[name] || [] ).forEach( function( fn ) { fn( { preventDefault : function() {} } ) } );
}

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

const Nino = {
	admin : {},
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
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
vm.runInContext( source('_admin/Nino/Modules/Images/assets/admin.js'), context, { filename : 'admin.js' } );
vm.runInContext( source('_admin/Nino/Modules/Images/assets/slots.js'), context, { filename : 'slots.js' } );

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

fire( removeButton( withImage ), 'click' );
answer( 200, { filename : null } );
check( 'a successful removal hides the preview and the button and says so', previewOf( withImage ).hidden === true && removeButton( withImage ).hidden === true && messageOf( withImage ).textContent === 'Image removed.' );


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
answer( 200, { alt : { de_DE : 'Ein Haus', en_US : 'A house' } } );
// The second such line of the slot: the first is the picture's own
const altStatus = byClass( alt, 'nino-admin-field-image-msg' )[1];
check( 'a saved alt text says so in a polite live region', altStatus.textContent === 'Alt texts saved.' && altStatus.getAttribute('aria-live') === 'polite' );
fire( altSave, 'click' );
answer( 400, { error : 'an alt text is at most 250 characters' } );
check( 'a refused one is an error', altStatus.className === 'nino-admin-field-image-msg is-error' );

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
const shellForm = element('div');
const shellList = element('div');
const shellNino = {
	admin : { dirty : registry, formToolbar : function( back ) { const bar = element('div'); bar.appendChild( back ); return bar }, router : { set : function() {}, current : function() { return { panel : 'images', parts : [] } } } },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) { shellRequests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } ) } },
	content : { getText : text },
};
const shellSandbox = {
	console : console,
	document : { createElement : element, getElementById : function( id ) { return { 'images-form' : shellForm, 'images-list' : shellList }[id] ?? null }, documentElement : null, body : null },
	Nino : shellNino,
};
shellSandbox.window = { Nino : shellNino, location : { hash : '' }, confirm : function() { return true } };
const shellContext = vm.createContext( shellSandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), shellContext, { filename : 'Nino.admin.js' } );
const imagesSource = source('_admin/Nino/Modules/Images/assets/admin.js');
vm.runInContext( imagesSource, shellContext, { filename : 'admin.js' } );

const shellPanel = shellNino.admin.images;
shellPanel._locales = [ 'de_DE', 'en_US' ];
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


// Whatever the server or an editor wrote reaches the page as text: the only innerHTML here empties a container
check( 'nothing the panel is told is written as markup - innerHTML is only ever emptied', ( source('_admin/Nino/Modules/Images/assets/admin.js').match( /innerHTML[^\n]*/g ) || [] ).every( function( use ) { return /^innerHTML = '';$/.test( use ) } ) );


// --- the Slots tab row ---------------------------------------------------------

const slots = Nino.admin.slots;
check( 'a slot some page shows adds nothing to its row', slots._usageLabel( { usage : { templates : [ 'page-home' ], pages : [ { httpUri : '/', name : 'x' } ] } } ) === '' );
check( 'a slot no page shows says it is not included anywhere', slots._usageLabel( { usage : { templates : [], pages : [] } } ) === ', not included anywhere' );
check( '...and names the templates that mention it, if any', slots._usageLabel( { usage : { templates : [ 'page-a', 'page-b' ], pages : [] } } ) === ', not included anywhere, Templates: page-a, page-b' );
check( 'a slot without usage data reads as unused rather than throwing', slots._usageLabel( {} ) === ', not included anywhere' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
