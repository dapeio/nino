/**
 *	Nino										A compact filesystembased php framework
 *	admin-elements-js-smoke.js	DOM-light checks for the Elements panel's script
 *													(_admin/Nino/Modules/Elements/assets/admin.js).
 *
 *													Every view has an address - the types, the elements of
 *													one, one element, a new one at @new - and a save leaves
 *													the person in the form and says so as a toast. The types
 *													are the rows of the block (list()), the elements the
 *													table of the block with its search and heads that are
 *													words; neither the list nor the form has a language
 *													switch of its own: the form shows the language of the
 *													workbench and writes that one and no other, and draws
 *													again when it moves. A field is the control of its type
 *													- a yes or no a switch, an image a file field, a list of
 *													texts rows - with its help behind a (?) and what is
 *													wrong said at the field, the server's refusals too. An
 *													element saved without a change sends back what it
 *													holds (a fixture the PHP suite writes the file from).
 *
 *	Usage: node tests/admin-elements-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const fs 		= require('fs');
const path 	= require('path');
const { check, done, fills, load, element, findAll, fire, shellRouter } = require( './harness.js' );

// What the panel writes into the address goes through the router's own codec: every state written, every link drawn, is read back
const codec = shellRouter();

const fixture = JSON.parse( fs.readFileSync( path.join( __dirname, 'fixtures/admin/elements-roundtrip.json' ), 'utf8' ) );

// Every focus() the panel asks for: the one that has it now is what a page answers to activeElement
let active = null;

function control( tag ) {
	const el = element( tag );
	el.focus = function() { active = el };
	return el;
}


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Elements/text/en_US.php');
const moduleDe 		= fills('_admin/Nino/Modules/Elements/text/de_DE.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const pane = element('div');
pane.dataset.panel = 'elements';
const head = element('div');
head.className = 'admin-panel-head';
const paneTitle = control('h2');
paneTitle.className = 'admin-panel-title';
head.appendChild( paneTitle );
pane.appendChild( head );
const types = element('div');
types.id = 'elements-types';
const list = element('div');
list.id = 'elements-list';
const form = element('div');
form.id = 'elements-form';
[ types, list, form ].forEach( function( level ) { pane.appendChild( level ) } );

// The shell's wrapper, which says what an upload may be
const wrapper = element('div');
wrapper.id = 'admin-page-wrap';
wrapper.dataset.uploadBytes = '1000';
wrapper.dataset.uploadPixels = '1000000';

function everything() {
	return [ types, list, form, wrapper ].concat( findAll( types, function() { return true } ), findAll( list, function() { return true } ), findAll( form, function() { return true } ) );
}

const requests = [];
const toasts = [];
const questions = [];
let questionAnswer = false;
const touched = [];
const written = [];
const moved = [];
const registered = {};
const guarded = [];
const followers = [];
let hash = { panel : 'elements', parts : [] };
let dirtyAnswer = true;
const editors = [];

const workLocale = { current : 'de_DE', native : 'de_DE', follow : function( name, handler ) { followers.push( { name : name, handler : handler } ) } };

const Nino = {
	admin : {
		touch : function( topic ) { touched.push( topic ) },
		stamp : function() { return 0 },
		publicUrl : function( path ) { return '/pub'+ path },
		workLocale : workLocale,
		htmlEditor : { create : function( mount, value, maxlength, inputsize, mode ) {
			const editor = { value : value, mount : mount, maxlength : maxlength, mode : mode, marks : [], destroyed : false,
				getValue : function() { return editor.value }, destroy : function() { editor.destroyed = true }, focus : function() { active = mount }, mark : function( options ) { editor.marks.push( options ) } };
			editors.push( editor );
			return editor;
		} },
		router : {
			current : function() { return hash },
			go : function( panel, parts ) { codec.check( panel, parts ); moved.push( JSON.stringify( [ panel ].concat( parts ) ) ) },
			set : function( panel, parts ) { codec.check( panel, parts ); written.push( JSON.stringify( [ panel ].concat( parts ) ) ) },
			leave : function( names, open, proceed ) { proceed() },
			href : function( name, parts ) { return codec.check( name, parts ) }, word : codec.word, wordOf : codec.wordOf, isWord : codec.isWord,
		},
		dirty : {
			register : function( name, entry ) { registered[name] = entry },
			guard : function( names, proceed ) { guarded.push( names.join() ); if( dirtyAnswer === true ) proceed( 'discard' ) },
			refresh : function() {},
			snapshot : function() {},
			watchForm : function() {},
		},
	},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback, file : data.file } );
		// A page that shuts the control that has the focus loses the focus to the body
		if( data.action === 'elements/save' )
			active = null;
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : control,
		createTextNode : function( content ) { return Object.assign( element('#text'), { textContent : String( content ) } ) },
		getElementById : function( id ) { return everything().find( function( el ) { return el.id === id } ) ?? null },
		createElementNS : function( space, tag ) { return element( tag ) },
		documentElement : null,
		body : null,
		get activeElement() { return active },
	},
	Nino : Nino,
	CSS : { escape : function( value ) { return String( value ) } },
};
sandbox.window = { Nino : Nino, location : { hash : '' }, setTimeout : function() { return 1 }, clearTimeout : function() {}, Event : function( type, options ) { this.type = type; this.bubbles = ( options ?? {} ).bubbles === true } };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );

Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English (US)' } };

// What is said as a toast, and the questions: collected, not drawn
Nino.adminUi.say = function( words ) { toasts.push( words ) };
Nino.adminUi.confirm = function( level, options ) {
	questions.push( Object.assign( { level : level }, options ) );
	options.onChoose( questionAnswer );
	return true;
};
load( '_admin/Nino/Modules/Elements/assets/admin.js', context );

const panel = Nino.admin.elements;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].answered = true;
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

function forget() {
	requests.length = 0;
	toasts.length = 0;
	questions.length = 0;
	moved.length = 0;
	written.length = 0;
	guarded.length = 0;
	touched.length = 0;
}

function theForm() { return findAll( form, function( el ) { return el.tagName === 'FORM' } )[0] }
function bar() { return findAll( form, function( el ) { return el.classList.contains('nino-admin-actionbar') } )[0] }
function barButtons() { return bar().children.filter( function( el ) { return el.tagName === 'BUTTON' } ) }
function danger() { return barButtons().find( function( el ) { return el.classList.contains('nino-admin-btn-danger') } ) }
function primary() { return barButtons().find( function( el ) { return el.classList.contains('nino-admin-btn-primary') } ) }
function sentences() { return findAll( form, function( el ) { return el.classList.contains('nino-admin-field-error') } ) }
function datum( name ) { return findAll( form, function( el ) { return el.dataset.field === name } )[0] }
function backLink() { return findAll( form, function( el ) { return el.classList.contains('nino-admin-back-link') } )[0] }
function save() { fire( theForm(), 'submit' ) }
function flip( el, on ) { el.checked = on; fire( el, 'change' ); fire( theForm(), 'change', { target : el } ) }
function type( el, value ) { el.value = value; fire( el, 'input' ); fire( theForm(), 'input', { target : el } ) }
function lineState() { return findAll( form, function( el ) { return el.id === 'elements-form-msg' } )[0] }
function rows( level ) { return findAll( level, function( el ) { return el.classList.contains('nino-admin-list-row') } ) }
/** The form is drawn once the elements its references point at have been read: answer them */
function settle() {
	while( requests.length > 0 && requests.at(-1).action === 'elements/list' && requests.at(-1).payload.type === 'roundmaker' && requests.at(-1).answered !== true )
		answer( 200, { elements : [ { uri : 'ada', label : 'Ada' }, { uri : 'ben', label : 'Ben' } ] } );
}
/** All the text under an element, in the order it stands */
function words( el ) { return el.textContent+ ( el.children || [] ).map( words ).join('') }
/** What the server answers for the element of the fixture: a copy of its own, which the panel is free to write into */
function stored() { return JSON.parse( JSON.stringify( fixture.stored ) ) }
function inside( root, el ) {
	for( let node = el; node !== null && node !== undefined; node = node.parentNode )
		if( node === root )
			return true;
	return false;
}

const MODEL = fixture.model;
const TYPES = [
	{ type : 'roundtrip', title : 'Round trip', count : 1, model : MODEL, rights : { insert : true, delete : true, update : {} }, fills : { summary : 'textfill', body : 'textfill' }, autoincrement : false, nextUri : '' },
	{ type : 'plain', title : 'Plain', count : 0, model : { name : { type : 'string' } }, rights : { insert : false, delete : false, update : {} }, fills : {}, autoincrement : true, nextUri : '00007' },
];

console.log('Admin Elements');

// --- the registry and the language --------------------------------------------

check( 'the panel follows the language of the workbench under the name of its entry of unsaved input, with the handler that draws it', followers.length === 1 && followers[0].name === 'elements' && followers[0].handler === panel._followLocale );
check( '...and registers that entry with the shell: it can be asked, saved and discarded, and says when it is saving', typeof registered.elements === 'object'
	&& [ 'isDirty', 'save', 'discard', 'saving' ].every( function( name ) { return typeof registered.elements[name] === 'function' } ) );

// --- the types -----------------------------------------------------------------

panel.init();
check( 'the panel asks for the types', requests.length === 1 && requests[0].action === 'elements/types' );
answer( 200, { types : TYPES, locales : [ 'de_DE', 'en_US' ], selectedLocale : 'de_DE' } );

check( 'the types are the rows of the block, a link each that writes the address of its list', rows( types ).length === 2
	&& findAll( rows( types )[0], function( el ) { return el.classList.contains('nino-admin-row-open') } )[0].href === '#elements/elements/roundtrip' && findAll( rows( types )[0], function( el ) { return el.tagName === 'STRONG' } )[0].textContent === 'Round trip' );
check( '...whose second line says how many elements the type holds and its identifier, quietly', findAll( rows( types )[0], function( el ) { return el.tagName === 'SMALL' } )[0].textContent === text('/_admin/elements/label/element-one')+ ' · /roundtrip'
	&& findAll( rows( types )[1], function( el ) { return el.tagName === 'SMALL' } )[0].textContent === '0 elements · /plain' );
check( '...and none of them is a button of a class of its own: the block draws them', findAll( types, function( el ) { return el.classList.contains('admin-type-btn') } ).length === 0 );
check( 'a type of the list is not asked about in a search while the list is short: the search is for twelve and more', findAll( types, function( el ) { return el.classList.contains('nino-admin-filterbar') } ).length === 0 );

// --- the elements of a type ----------------------------------------------------

forget();
fire( findAll( rows( types )[0], function( el ) { return el.classList.contains('nino-admin-row-open') } )[0], 'click' );
check( 'a type opens its list through the router and asks for it in the language of the workbench', moved.at(-1) === '["elements","roundtrip"]' && requests.at(-1).action === 'elements/list' && requests.at(-1).payload.type === 'roundtrip' && requests.at(-1).payload.locale === 'de_DE' );

const COLUMNS = [ 'title', 'summary', 'price', 'stock', 'active', 'category', 'launch', 'starts', 'maker', 'related' ];
answer( 200, { columns : COLUMNS, elements : [ { uri : 'hammer', label : 'Hammer', values : { title : 'Hammer', summary : 'Ein guter Hammer.', price : 9, stock : 4, active : true, category : 'toys', launch : '2026-03-01', starts : '', maker : '', related : [] }, from : { summary : 'en_US' } } ] } );

const table = findAll( list, function( el ) { return el.tagName === 'TABLE' } )[0];
const heads = findAll( table, function( el ) { return el.tagName === 'TH' } ).map( function( th ) { return findAll( th, function( el ) { return el.tagName === 'BUTTON' } ).map( function( button ) { return button.textContent } ).join('') } ).filter( function( word ) { return word !== '' } );
check( 'the elements are the table of the block, with the search over its full width above it', table !== undefined && findAll( list, function( el ) { return el.classList.contains('nino-admin-table-search') } ).length === 1
	&& findAll( list, function( el ) { return el.classList.contains('nino-admin-table-search') } )[0].attributes['aria-label'] === text('/_admin/elements/label/search')
	&& findAll( list, function( el ) { return el.classList.contains('nino-admin-table-toolbar--wide') } ).length === 1 );
check( '...its heads are words - a word of the vocabulary, a label, the key read as words - and never the key of a field', heads.join() === [ text('/_admin/elements/label/uri') ].concat( [ 'Title', 'Summary', 'Price', 'Stock', 'Active', 'Category', 'Launch', 'Starts', 'Maker', 'Related' ] ).join()
	&& heads.every( function( word ) { return word.charAt(0) === word.charAt(0).toUpperCase() } ) );
check( '...a text another language holds says whose it is beside it', findAll( table, function( el ) { return el.classList.contains('nino-admin-badge') && el.textContent === 'English (US)' } ).length === 1 );

const listBar = findAll( list, function( el ) { return el.classList.contains('nino-admin-actionbar') } )[0];
check( 'the bar of the list has New element as the one main button, for an account that may add one', listBar.classList.contains('nino-admin-list-actions') && listBar.children.length === 1 && listBar.children[0].textContent === text('/_admin/elements/label/add') && listBar.children[0].classList.contains('nino-admin-btn-primary') );
check( '...and the way back, the name of the type and its identifier stand in the context bar', findAll( list, function( el ) { return el.classList.contains('nino-admin-back-link') } ).length === 1
	&& findAll( list, function( el ) { return el.classList.contains('nino-admin-contextbar-title') } )[0].textContent === 'Round trip' && findAll( list, function( el ) { return el.classList.contains('nino-admin-contextbar-code') } )[0].textContent === '/roundtrip' );
check( 'neither the list nor the form has a language switch of its own: the language is the workbench\'s', findAll( list, function( el ) { return el.tagName === 'SELECT' && String( el.className ).indexOf('locale') !== -1 } ).length === 0 );

// A type nobody may add to has no New element, and a list of none says so in a box
panel._selectType( 'plain', TYPES[1].model, 'Plain' );
answer( 200, { columns : [ 'name' ], elements : [] } );
check( 'an account that may not add elements has no New element button, and an empty type says so in a box with one sentence', findAll( list, function( el ) { return el.classList.contains('nino-admin-actionbar') } ).length === 0
	&& findAll( list, function( el ) { return el.classList.contains('nino-admin-empty') } )[0].textContent === text('/_admin/elements/none') );
panel._selectType( 'roundtrip', MODEL, 'Round trip' );
answer( 200, { columns : COLUMNS, elements : [ { uri : 'hammer', label : 'Hammer', values : {}, from : {} } ] } );

// --- the form of an element ------------------------------------------------------

forget();
panel._visit( 'hammer' );
check( 'an element opens through the router at its address and is asked for', moved.at(-1) === '["elements","roundtrip","hammer"]' && requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === 'hammer' );
answer( 200, { global : stored().global, locales : stored().locales, raw : { '*' : { price : 9 }, de_DE : { title : 'Hammer' } } } );
// The references the form offers are the elements of the type they point at: they are read before the form is drawn
check( 'the form asks for the elements its references point at before it is drawn', requests.at(-1).action === 'elements/list' && requests.at(-1).payload.type === 'roundmaker' && theForm() === undefined );
settle();

check( 'the form is novalidate and built from the blocks of the workbench: it has the way back, the bar, and a state badge', theForm().noValidate === true && backLink() !== undefined && bar() !== undefined
	&& findAll( bar(), function( el ) { return el.classList.contains('nino-admin-actionbar-state') } ).length === 1 );
check( 'the bar has Delete element at its left, in the danger kind, one main button at its right, Save, and the copy beside it', barButtons()[0] === danger() && barButtons().at(-1) === primary() && primary().textContent === text('/_admin/common/label/save')
	&& barButtons().filter( function( el ) { return el.classList.contains('nino-admin-btn-primary') } ).length === 1 && danger().textContent === text('/_admin/elements/label/delete') && barButtons()[1].textContent === text('/_admin/elements/label/duplicate') );
check( 'the form has no language select and no open help sentence: what a field says is behind its (?), and a fill of an alt text too', findAll( form, function( el ) { return el.tagName === 'SELECT' && String( el.className ).indexOf('locale') !== -1 } ).length === 0
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-field-hint') } ).length === 0 );
check( 'the section of the language says which language it is and the badge of the bar says it where it is not the native one: not for German', findAll( form, function( el ) { return el.tagName === 'LEGEND' } ).map( function( el ) { return el.textContent } ).join() === text('/_admin/elements/label/global')+ ','+ 'Language: Deutsch'
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-locale-badge') } ).length === 0 );

// the controls of the types of field
check( 'a yes or no is a switch of the block, never a pair of radios', findAll( form, function( el ) { return el.classList.contains('nino-admin-switch') } ).length === 2 && findAll( form, function( el ) { return el.type === 'radio' } ).length === 0 && datum('active').checked === false && datum('featured').checked === true );
check( 'a field with fixed values is a select, a reference a select with a first empty choice, a list of references the list of the block', datum('category').tagName === 'SELECT' && datum('category').value === 'toys'
	&& datum('maker').tagName === 'SELECT' && datum('maker').children[0].value === '' && datum('maker').value === '/roundmaker/ada'
	&& JSON.parse( datum('related').value ).join() === '/roundmaker/ada,/roundmaker/ben' );
check( 'a list of texts is rows of the block, another list - an object - is JSON in the code field', findAll( form, function( el ) { return el.classList.contains('nino-admin-stringlist') } ).length === 1 && datum('tags').type === 'hidden'
	&& datum('meta').tagName === 'TEXTAREA' && datum('meta').classList.contains('nino-admin-code') && JSON.parse( datum('meta').value ).weight === 1.2 );
check( 'a rich text is the editor of the workbench, created with the field\'s own size and format', editors.length === 1 && editors[0].mode === 'blocks' && editors[0].maxlength === 8000 && editors[0].value === fixture.stored.locales.de_DE.body );
check( 'a text of several lines is a textarea of its rows, with the count of its characters under it, which follows what is typed', datum('summary').rows === 4 && datum('summary').maxLength === 400
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-char-counter') } ).map( function( el ) { return el.textContent } ).join() === '6 / 150,25 / 400' );
type( datum('summary'), 'Kurz' );
check( '...the count follows what is typed', findAll( form, function( el ) { return el.classList.contains('nino-admin-char-counter') } )[1].textContent === '4 / 400' );
type( datum('summary'), fixture.stored.locales.de_DE.summary );
check( 'a number, a date and a moment have controls of their own: the unit stands beside the number', datum('price').type === 'number' && datum('price').step === 'any' && datum('stock').step === '1' && datum('launch').type === 'date'
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-field-suffix') } )[0].textContent === 'EUR' && datum('starts').type === 'date' );
check( 'a required field has its star and aria-required, which the model says and the block draws', datum('title').attributes['aria-required'] === 'true' && datum('price').attributes['aria-required'] === 'true' && datum('stock').attributes['aria-required'] === undefined );

check( 'a field is named by a word in its form: the label of the price says Price, the key is no name', words( datum('price').closest('label').children[0] ).indexOf('Price') === 0 );

// the fills
const fillsButtons = findAll( form, function( el ) { return el.classList.contains('nino-admin-fills-button') } );
check( 'the "[ ]" of a field whose textfills decide stands at the fields the server names - the several lines and the rich text - and at no other', fillsButtons.length === 2
	&& fillsButtons.every( function( el ) { return el.attributes['aria-label'] !== undefined } ) );

// no raw file input outside the block, the image is the file field of the guide
check( 'the form has no image field: this model has none, and none is a bare file input', findAll( form, function( el ) { return el.type === 'file' && el.parentNode !== null && el.parentNode.classList.contains('nino-admin-filefield-body') === false } ).length === 0 );

// the raw storage is an accordion
fire( findAll( form, function( el ) { return el.classList.contains('nino-admin-accordion-button') } )[0], 'click' );
check( 'the raw storage is folded in an accordion of the block, one box of code for each bucket, which nobody can type into', findAll( form, function( el ) { return el.classList.contains('nino-admin-accordion') } ).length === 1
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-code') && el.readOnly === true } ).length === 2 );

// the steps
const steps = findAll( form, function( el ) { return el.dataset.nav !== undefined } );
check( 'the two steps are icon buttons with a name: the first element has no way back, and the list says what lies next', steps.length === 2 && steps.every( function( el ) { return el.classList.contains('nino-admin-btn-icon') && el.attributes['aria-label'] !== undefined } ) && steps[0].disabled === true );

// --- unsaved input ---------------------------------------------------------------

check( 'an element nobody has changed holds no unsaved input', panel.isDirty() === false );
type( datum('title'), 'Hammer 2' );
check( 'a changed field is unsaved input', panel.isDirty() === true );
type( datum('title'), 'Hammer' );
check( '...and typed back to what it was is none', panel.isDirty() === false );
type( datum('meta'), '{ not json' );
check( 'a text of a JSON field that is no JSON counts as input too - what is typed is what is unsaved', panel.isDirty() === true );
type( datum('meta'), JSON.stringify( fixture.stored.global.meta ) );
check( '...until it is what it was', panel.isDirty() === false );

// --- saving: only what was changed ------------------------------------------------------

forget();
save();
check( 'an element saved without a change writes nothing: no request, so its file stays as it is - the keys, the order and the odd values of a file written by hand with it - and the toast says it', requests.length === 0 && toasts.length === 1 && toasts[0] === text('/_admin/common/msg/saved') );

flip( datum('active'), true );
flip( datum('featured'), false );
type( datum('stock'), '5' );
type( datum('title'), 'Hammer 2' );
forget();
datum('title').focus();
save();
check( 'a changed element sends the fields that were changed - the ones every language shares and the one of the language on screen - and no other: the fixture the PHP suite writes the same values from', requests.length === 1 && requests[0].action === 'elements/save'
	&& requests[0].payload.locale === 'de_DE' && requests[0].payload.isNew === false && JSON.stringify( requests[0].payload.fields ) === JSON.stringify( fixture.edit.de_DE )
	&& Object.keys( fixture.same.de_DE ).join() === Object.keys( fixture.edit.de_DE ).join() );
check( '...a switch that was turned off is sent as false, never as nothing', requests[0].payload.fields.featured === false );
check( '...the controls are shut while it is on its way', findAll( form, function( el ) { return el.tagName === 'INPUT' && el.type !== 'hidden' } ).every( function( el ) { return el.disabled === true } ) );

answer( 200, { element : Object.assign( { '.uri' : '/roundtrip/hammer' }, fixture.stored.global, fixture.stored.locales.de_DE, fixture.edit.de_DE ), nextUri : '' } );
check( 'the answer is said once, as a toast and by the badge of the bar, and the person stays in the form at the address it was opened at', toasts.length === 1 && toasts[0] === text('/_admin/common/msg/saved')
	&& form.classList.contains('admin-hidden') === false && moved.length === 0 && lineState().textContent === '' );
check( '...the elements are said to have been written, the list is read again, and the form holds nothing unsaved', touched.join() === 'elements' && requests.at(-1).action === 'elements/list' && panel.isDirty() === false );
check( '...and the focus is on the control that had it, which the running save shut', active === datum('title') );
forget();
save();
check( 'what was saved is the new state of the form: a second save writes nothing', requests.length === 0 );

// --- the language of the workbench ---------------------------------------------------

forget();
workLocale.current = 'en_US';
panel._followLocale( 'en_US' );
check( 'a move of the language draws the form again in the other language, its legend and the badge saying which', findAll( form, function( el ) { return el.tagName === 'LEGEND' } )[1].textContent === 'Language: English (US)'
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-locale-badge') } ).length === 1 && datum('title').value === '' );
check( '...the fields of the language are the ones of that language, not the ones that were on screen', datum('summary').value === '' && datum('price').value === '9' && panel.isDirty() === false );
type( datum('stock'), '6' );
save();
check( 'in a language the element has no text in a field every language shares can be saved: the title that is required there is not asked for, nothing is sent of the language, and the language stays without text', requests.length === 1 && requests[0].payload.locale === 'en_US'
	&& JSON.stringify( requests[0].payload.fields ) === JSON.stringify( fixture.edit.en_US ) && sentences().length === 0 && Object.keys( fixture.same.en_US ).join() === Object.keys( fixture.edit.en_US ).join() );
answer( 200, { element : Object.assign( { '.uri' : '/roundtrip/hammer' }, fixture.stored.global, { stock : 6 } ), nextUri : '' } );
forget();
save();
check( '...and there is nothing to ask for in a language nobody typed into, even when nothing else was changed', requests.length === 0 && sentences().length === 0 );
type( datum('summary'), 'A good hammer.' );
save();
check( 'a text typed in that language makes it one that is written - and then its required fields are asked for', requests.length === 0 && sentences().length === 1 && sentences()[0].textContent === Nino.adminUi.format( text('/_admin/common/error/missing'), 'Title' ) );
type( datum('title'), 'Hammer' );
save();
check( '...and only that language is written: its fields that were changed, none of those every language shares', requests.length === 1 && requests[0].payload.locale === 'en_US' && JSON.stringify( requests[0].payload.fields ) === JSON.stringify( { title : 'Hammer', summary : 'A good hammer.' } ) );
answer( 200, { element : Object.assign( { '.uri' : '/roundtrip/hammer' }, fixture.stored.global, { stock : 6, title : 'Hammer', summary : 'A good hammer.', body : '' } ), nextUri : '' } );
workLocale.current = 'de_DE';
panel._followLocale( 'de_DE' );
check( 'moving back shows what the server holds of the first language, not what was typed in the second', datum('title').value === 'Hammer 2' && datum('summary').value === fixture.stored.locales.de_DE.summary );
// A language the website does not serve is not one the form can show: it shows the first, and says which
workLocale.current = 'fr_FR';
panel._followLocale( 'fr_FR' );
check( 'a language the site does not serve is not drawn: the form keeps the one it has, with its badge, and says that it is not the workbench\'s', findAll( form, function( el ) { return el.tagName === 'LEGEND' } )[1].textContent === 'Language: Deutsch' );
type( datum('summary'), 'Anders.' );
forget();
save();
check( 'what is saved then is the language the form shows - never the one the workbench is on, which the form cannot show', requests.length === 1 && requests[0].payload.locale === 'de_DE' && requests[0].payload.fields.summary === 'Anders.' );
answer( 200, { element : Object.assign( { '.uri' : '/roundtrip/hammer' }, fixture.stored.global, fixture.stored.locales.de_DE, { title : 'Hammer 2', summary : 'Anders.' } ), nextUri : '' } );
workLocale.current = 'de_DE';
panel._followLocale( 'de_DE' );
type( datum('summary'), fixture.stored.locales.de_DE.summary );
type( datum('title'), 'Hammer 2' );

// --- what is wrong --------------------------------------------------------------------

forget();
type( datum('title'), '' );
save();
check( 'a required field that is empty holds the save back: nothing is sent, the sentence stands at the field and says its name, the line says that something is wrong, the focus goes to the field', requests.length === 0
	&& sentences().length === 1 && sentences()[0].textContent === Nino.adminUi.format( text('/_admin/common/error/missing'), 'Title' ) && datum('title').attributes['aria-invalid'] === 'true'
	&& lineState().textContent === text('/_admin/elements/error/fields') && lineState().dataset.state === 'error' && active === datum('title') );
type( datum('title'), 'Hammer' );
check( 'what is typed into the field lets go of its sentence, the others stay', sentences().length === 0 && datum('title').attributes['aria-invalid'] === undefined );
type( datum('meta'), '{ not json' );
type( datum('price'), '' );
save();
check( 'a JSON text that is none and a number that is empty each say so at their own field, in the words of the guide: missing, or not valid with what is expected', requests.length === 0 && sentences().length === 2
	&& sentences().map( function( el ) { return el.textContent } ).join('|') === Nino.adminUi.format( text('/_admin/common/error/missing'), 'Price' )+ '|'+ Nino.adminUi.format( text('/_admin/elements/error/invalid'), 'Meta', text('/_admin/elements/expect/json') ) && active === datum('price') );
type( datum('meta'), JSON.stringify( fixture.stored.global.meta ) );
type( datum('price'), '9' );

// The server refuses for a field: its sentence stands at that field, and the refusals of a callback too
forget();
save();
answer( 400, { error : 'refused', fields : [ { field : 'stock', error : 'Stock is below the minimum.', code : '' }, { field : 'nonexistent', error : 'x' } ] } );
check( 'a refusal of the server that names a field of the form is said at that field with the focus on it, and the line says it too', sentences().length === 1 && sentences()[0].textContent === 'Stock is below the minimum.' && datum('stock').attributes['aria-invalid'] === 'true' && active === datum('stock')
	&& lineState().dataset.state === 'error' );
check( '...the controls are open again, and nothing was said to be saved', toasts.length === 0 && datum('title').disabled === false );
forget();
save();
answer( 403, { error : 'not allowed', field : 'price', code : 'elements_fields_not_allowed', params : [ 'price' ] } );
check( 'a refusal that names a field by its field is said at it as well', sentences().length === 1 && datum('price').attributes['aria-invalid'] === 'true' );
forget();
save();
answer( 500, null );
check( 'a failure that names no field is said in the line of the bar alone', sentences().length === 0 && lineState().dataset.state === 'error' && lineState().textContent.indexOf('500') !== -1 );

// --- a new element -------------------------------------------------------------------

forget();
panel._visit( null );
check( 'a new element has the address #elements/elements/<type>/@new, never one that an element called new could have', moved.at(-1) === '["elements","roundtrip",{"word":"@new"}]' );
settle();
check( '...the Element URI is the first field, required, with its help behind a (?), and the main button creates the element', datum('.uri') !== undefined && datum('.uri').attributes['aria-required'] === 'true' && primary().textContent === text('/_admin/elements/label/create')
	&& danger() === undefined && barButtons().some( function( el ) { return el.textContent === text('/_admin/elements/label/create-again') } ) );
check( '...and the badge of its bar has nothing to say of an input nobody made', panel.isDirty() === false );
forget();
save();
check( 'the uri is the first thing a save asks for, and what it says is the guide\'s sentence', requests.length === 0 && sentences()[0].textContent === Nino.adminUi.format( text('/_admin/common/error/missing'), text('/_admin/elements/label/uri') ) && active === datum('.uri') );
type( datum('.uri'), 'Not A Slug' );
save();
check( 'a uri that is no slug is not valid, a uri the type already has is taken', sentences()[0].textContent === Nino.adminUi.format( text('/_admin/elements/error/invalid'), text('/_admin/elements/label/uri'), text('/_admin/elements/expect/uri') ) );
type( datum('.uri'), 'hammer' );
save();
check( '...taken is a sentence of its own', sentences()[0].textContent === Nino.adminUi.format( text('/_admin/elements/error/taken'), text('/_admin/elements/label/uri') ) );
type( datum('.uri'), 'saw' );
type( datum('title'), 'Säge' );
type( datum('price'), '3' );
save();
check( 'a new element is inserted, with its uri, in the language on screen', requests.length === 1 && requests[0].payload.isNew === true && requests[0].payload.uri === 'saw' && requests[0].payload.locale === 'de_DE' && requests[0].payload.fields.title === 'Säge' );
answer( 200, { element : Object.assign( { '.uri' : '/roundtrip/saw' }, { price : 3 }, { title : 'Säge' } ), nextUri : '' } );
check( '...and gets its address: the form is drawn again as the form of an element that exists, with Delete, at the address of the new element', written.at(-1) === '["elements","roundtrip","saw"]' && danger() !== undefined && datum('.uri') === undefined
	&& toasts.length === 1 && primary().textContent === text('/_admin/common/label/save') && active === primary() );

// A numbered type shows the number it will give, and asks for nothing
panel._selectType( 'plain', TYPES[1].model, 'Plain' );
answer( 200, { columns : [ 'name' ], elements : [] } );
forget();
panel._visit( null );
check( 'a type that numbers its elements shows the number the next one gets, shut, and sends no uri', datum('.uri') === undefined && findAll( form, function( el ) { return el.id === 'elements-form-uri' } )[0].placeholder === '/plain/00007' && findAll( form, function( el ) { return el.id === 'elements-form-uri' } )[0].disabled === true );

// --- the copy -------------------------------------------------------------------------

panel._selectType( 'roundtrip', MODEL, 'Round trip' );
answer( 200, { columns : COLUMNS, elements : [ { uri : 'hammer', label : 'Hammer', values : {}, from : {} } ] } );
forget();
panel._visit( 'hammer' );
answer( 200, { global : stored().global, locales : { de_DE : stored().locales.de_DE, en_US : { title : 'Hammer', summary : 'A good hammer.', body : '' } }, raw : {} } );
settle();
forget();
fire( barButtons().find( function( el ) { return el.textContent === text('/_admin/elements/label/duplicate') } ), 'click' );
check( 'the copy is asked about unsaved input first and then is a new form that carries the values, with no uri of its own', guarded.join() === 'elements' && datum('.uri') !== undefined && datum('.uri').value === '' && datum('title').value === 'Hammer' && moved.at(-1) === '["elements","roundtrip",{"word":"@new"}]' );
check( '...it counts as unsaved input, and the line says that it is a copy', panel.isDirty() === true && lineState().textContent === text('/_admin/elements/msg/duplicated') );
type( datum('.uri'), 'hammer-copy' );
forget();
save();
check( 'the copy is the one thing that writes more than the language on screen: every language the original has, the first in an insert and the others after it', requests.length === 1 && requests[0].payload.isNew === true && requests[0].payload.locale === 'de_DE' );
answer( 200, { element : Object.assign( { '.uri' : '/roundtrip/hammer-copy' }, fixture.stored.global, fixture.stored.locales.de_DE ), nextUri : '' } );
check( '...the second language is an update of the element that exists now, with the fields of that language only', requests.length === 2 && requests[1].payload.isNew === false && requests[1].payload.locale === 'en_US' && requests[1].payload.uri === 'hammer-copy'
	&& JSON.stringify( Object.keys( requests[1].payload.fields ).sort() ) === JSON.stringify( [ 'body', 'summary', 'title' ] ) );
answer( 200, { element : Object.assign( { '.uri' : '/roundtrip/hammer-copy' }, fixture.stored.global, { title : 'Hammer' } ), nextUri : '' } );
check( '...and then the person stays in the form, which is no copy any more', toasts.length === 1 && panel._copy === false && danger() !== undefined );

// --- delete ---------------------------------------------------------------------------

forget();
questionAnswer = false;
fire( danger(), 'click' );
check( 'Delete asks first, in the destructive weight, and names the element in its question and the words of the action in the title and the answer', questions.length === 1 && questions[0].level === 'destructive' && questions[0].title === text('/_admin/elements/confirm/delete-title')
	&& questions[0].yes === questions[0].title && questions[0].message.indexOf('/hammer-copy') !== -1 && requests.length === 0 );
questionAnswer = true;
fire( danger(), 'click' );
check( 'a yes sends the deletion of the element that is open', requests.length === 1 && requests[0].action === 'elements/delete' && requests[0].payload.uri === 'hammer-copy' && requests[0].payload.type === 'roundtrip' );
answer( 200, {} );
check( '...and a toast says that it is gone, the list is read again and shown in place of the form', toasts.join() === text('/_admin/elements/msg/deleted') && requests.at(-1).action === 'elements/list' );
questionAnswer = false;

// --- the steps from one element to the next ---------------------------------------------

panel._selectType( 'roundtrip', MODEL, 'Round trip' );
answer( 200, { columns : COLUMNS, elements : [ { uri : 'a', label : 'A', values : {}, from : {} }, { uri : 'b', label : 'B', values : {}, from : {} }, { uri : 'c', label : 'C', values : {}, from : {} } ] } );
forget();
panel._visit( 'b' );
answer( 200, { global : stored().global, locales : stored().locales, raw : {} } );
settle();
const stepButtons = function() { return findAll( form, function( el ) { return el.dataset.nav !== undefined } ) };
check( 'the steps are set to the elements next to the open one in the order of the list, and are shut at an end', stepButtons().map( function( el ) { return el.disabled+ ':'+ el.dataset.uri } ).join() === 'false:a,false:c' );
forget();
fire( stepButtons()[1], 'click' );
check( 'a step asks about unsaved input first and then opens the next element through the router', guarded.join() === 'elements' && moved.at(-1) === '["elements","roundtrip","c"]' && requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === 'c' );
answer( 200, { global : stored().global, locales : stored().locales, raw : {} } );
settle();

// --- the address names a level -------------------------------------------------------------

forget();
panel._follow( [ 'roundtrip', '@new' ] );
settle();
check( 'an address that names a new element opens a blank form, and one that names an element opens it', panel._isNew === true && findAll( form, function( el ) { return el.dataset.field === '.uri' } ).length === 1 );
panel._elements = [ { uri : 'new', label : 'An element called new' } ];
panel._follow( [ 'roundtrip', 'new' ] );
check( 'new is a name like any other: an element of that name is asked for, never a blank form', requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === 'new' );
answer( 404, { error : 'element not found' } );

// --- an image -------------------------------------------------------------------------------

const GALLERY = { title : { type : 'string', locale : true }, caption : { type : 'string', locale : true }, photo : { type : 'image', width : 400, height : 300, alt : 'caption' } };
panel._types = [ { type : 'gallery', title : 'Gallery', model : GALLERY } ];
panel._rights = {};
panel._fills = {};
panel._selectType( 'gallery', GALLERY, 'Gallery' );
answer( 200, { columns : [ 'title' ], elements : [ { uri : 'pic', label : 'Pic', values : {}, from : {} } ] } );
forget();
panel._visit( 'pic' );
answer( 200, { global : { photo : 'elements/gallery/pic.400x300.jpg' }, locales : { de_DE : { title : 'Bild', caption : 'Ein Bild' } }, raw : {} } );
const fileField = findAll( form, function( el ) { return el.classList.contains('nino-admin-filefield') } )[0];
const fileInput = findAll( fileField, function( el ) { return el.type === 'file' } )[0];
const previews = function() { return findAll( fileField, function( el ) { return el.tagName === 'IMG' } ) };
check( 'an image is the file field of the guide - a button, the name of the file, the limits as its note - with the picture beside it and the stored one from the images folder', fileField !== undefined && fileInput.accept === 'image/*'
	&& previews()[0].src === '/pub/images/elements/gallery/pic.400x300.jpg' && previews()[0].hidden === false && previews()[0].alt === ''
	&& findAll( fileField, function( el ) { return el.classList.contains('nino-admin-field-note') } )[0].textContent.indexOf('400 × 300 px. ') !== -1 );
check( '...the sentence that says where its alt text is written stands behind its (?), and the field of the alt text says whose it is', findAll( fileField, function( el ) { return el.classList.contains('nino-admin-help-button') } ).length === 1
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-help-button') } ).length === 2 );
check( 'the picture is no input of the form: a form with nothing changed in it holds nothing unsaved', panel.isDirty() === false );

forget();
fileInput.files = [ { name : 'a.jpg', size : 1000, type : 'image/jpeg' } ];
fire( fileInput, 'change' );
check( 'a chosen file is uploaded at once, for the language on screen, and the button is shut meanwhile', requests.length === 1 && requests[0].action === 'elements/uploadimage' && requests[0].payload.key === 'photo' && requests[0].payload.uri === 'pic' && requests[0].payload.locale === 'de_DE' && requests[0].file.name === 'a.jpg'
	&& findAll( fileField, function( el ) { return el.classList.contains('nino-admin-btn-secondary') } )[0].disabled === true );
answer( 200, { filename : 'elements/gallery/pic.400x300.png', url : '/images/elements/gallery/pic.400x300.png' } );
check( 'the upload is said as a toast, the picture is the new one, the elements are said to have been written, and the form is what it was', toasts.length === 1 && previews()[0].src === '/images/elements/gallery/pic.400x300.png' && touched.join() === 'elements' && panel.isDirty() === false );
forget();
fileInput.files = [ { name : 'b.jpg', size : 1000, type : 'image/jpeg' } ];
fire( fileInput, 'change' );
answer( 400, { error : 'the image was refused', code : 'image_type' } );
check( 'a file the server refuses is said at the file field, the picture that was there stays and no toast says anything', sentences().length === 1 && inside( fileField, sentences()[0] ) && previews()[0].src === '/images/elements/gallery/pic.400x300.png' && toasts.length === 0 );
// The sentence of a file the field refuses is set in the event that chose it, which goes on up to the form: it stays
forget();
fileInput.files = [ { name : 'huge.png', size : 5000, type : 'image/png' } ];
fileInput.dispatchEvent( { type : 'change', bubbles : true } );
check( 'a file that is too large is refused at the file field and the sentence stays there: nothing is sent', requests.length === 0 && sentences().length === 1 && inside( fileField, sentences()[0] ) && sentences()[0].textContent.indexOf('MB') !== -1 );
fileInput.files = [ { name : 'doc.txt', size : 10, type : 'text/plain' } ];
fileInput.dispatchEvent( { type : 'change', bubbles : true } );
check( 'so does a file of a type the field does not take', requests.length === 0 && sentences().length === 1 && inside( fileField, sentences()[0] ) );
type( datum('title'), 'Bild' );
check( 'what is typed into another field lets go of its own sentences only, not of the file field\'s', sentences().length === 1 );
type( datum('title'), 'Bild 1' );
type( datum('title'), 'Ein Bild' );
forget();
fileInput.files = [ { name : 'c.jpg', size : 1000, type : 'image/jpeg' } ];
fire( fileInput, 'change' );
answer( 200, { filename : 'elements/gallery/pic.400x300.png', url : '/images/x.png', belowTarget : true, source : { width : 100, height : 80 } } );
check( 'a picture below the target size is saved, the toast says so and the warning that names its size stays at the field', toasts.length === 1 && findAll( fileField, function( el ) { return el.classList.contains('nino-admin-notice') && el.hidden === false } ).length === 1 );

forget();
questionAnswer = false;
const removeBtn = findAll( fileField, function( el ) { return el.classList.contains('nino-admin-btn-danger') } )[0];
fire( removeBtn, 'click' );
check( 'Remove image asks first, in the destructive weight, and sends nothing for a No', questions.length === 1 && questions[0].level === 'destructive' && requests.length === 0 );
questionAnswer = true;
fire( removeBtn, 'click' );
check( 'a yes takes the image out of the element at once, for the language on screen, and says so as a toast', requests.length === 1 && requests[0].action === 'elements/removeimage' && requests[0].payload.locale === 'de_DE' );
answer( 200, { filename : null } );
check( '...the picture is gone from the field, and so is the button, and the focus is on the choice of a file, which stays', previews()[0].hidden === true && removeBtn.hidden === true && toasts.length === 1 && active === findAll( fileField, function( el ) { return el.classList.contains('nino-admin-btn-secondary') } )[0] );
questionAnswer = false;
type( datum('title'), 'Bild 2' );
forget();
save();
check( 'a save never sends the image, which an upload commits by itself: a field nobody changed is not in it', requests.length === 1 && 'photo' in requests[0].payload.fields === false && requests[0].payload.fields.title === 'Bild 2' );
answer( 200, { element : { '.uri' : '/gallery/pic', photo : '', title : 'Bild 2', caption : 'Ein Bild' }, nextUri : '' } );
forget();
panel._visit( null );
const newFile = findAll( form, function( el ) { return el.classList.contains('nino-admin-filefield') } )[0];
check( 'a new element cannot take an image before it exists: the button is shut and the note says why', findAll( newFile, function( el ) { return el.classList.contains('nino-admin-btn-secondary') } )[0].disabled === true
	&& findAll( newFile, function( el ) { return el.classList.contains('nino-admin-field-note') } )[0].textContent === text('/_admin/elements/note/image-savefirst') );

// --- what an account may not write -------------------------------------------------------------

const LOCKED = { title : { type : 'string', locale : true }, price : { type : 'double' } };
panel._types = [ { type : 'locked', title : 'Locked', model : LOCKED } ];
panel._rights = { locked : { insert : true, delete : false, update : { title : true, price : false } } };
panel._selectType( 'locked', LOCKED, 'Locked' );
answer( 200, { columns : [ 'title' ], elements : [ { uri : 'x', label : 'X', values : {}, from : {} } ] } );
forget();
panel._visit( 'x' );
answer( 200, { global : { price : 4 }, locales : { de_DE : { title : 'X' } }, raw : {} } );
type( datum('title'), 'Y' );
const lockedBefore = datum('price').disabled;
forget();
save();
check( 'a field the account may not write is drawn and locked, and not sent; an account that may not delete has no Delete', lockedBefore === true && 'price' in requests[0].payload.fields === false && requests[0].payload.fields.title === 'Y' && danger() === undefined );
answer( 200, { element : { '.uri' : '/locked/x', price : 4, title : 'Y' }, nextUri : '' } );

// --- the note of a type ----------------------------------------------------------------------------

moduleEn['/_admin/elements/type/locked/hint'] = 'Not legal advice.';
panel._selectType( 'locked', LOCKED, 'Locked' );
answer( 200, { columns : [ 'title' ], elements : [] } );
check( 'the note a type brings is a notice at the head of its list, never a paragraph that stands in the page', findAll( list, function( el ) { return el.classList.contains('nino-admin-notice') } )[0].textContent === 'Not legal advice.' );
delete moduleEn['/_admin/elements/type/locked/hint'];

// --- the words of a type are text, whatever they hold ---------------------------------------------------

const MARKUP = '<img src=x onerror=alert(1)>';
moduleEn['/_admin/elements/type/locked/hint'] = MARKUP;
moduleEn['/_admin/elements/field/locked/price'] = MARKUP;
panel._types = [ { type : 'locked', title : MARKUP, count : 0, model : LOCKED } ];
panel._renderTypes( panel._types );
check( 'a title of a type is the text of its row, whatever it holds', findAll( types, function( el ) { return el.tagName === 'IMG' } ).length === 0 && findAll( types, function( el ) { return el.tagName === 'STRONG' } )[0].textContent === MARKUP );
panel._selectType( 'locked', LOCKED, MARKUP );
answer( 200, { columns : [ 'title' ], elements : [ { uri : 'x', label : MARKUP, values : { title : MARKUP }, from : { title : 'en_US' } } ] } );
check( 'the note of a type, the name of the type and the cell of a table are text on the list', findAll( list, function( el ) { return el.tagName === 'IMG' } ).length === 0 && findAll( list, function( el ) { return el.classList.contains('nino-admin-notice') } )[0].textContent === MARKUP
	&& findAll( list, function( el ) { return el.classList.contains('nino-admin-contextbar-title') } )[0].textContent === MARKUP );
panel._visit( 'x' );
answer( 200, { global : { price : 4 }, locales : { de_DE : { title : MARKUP } }, raw : {} } );
check( 'the form has no note of the type - it stands over the list - and the name of a field, the title of an element and its legend are text', findAll( form, function( el ) { return el.tagName === 'IMG' } ).length === 0
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-notice') } ).length === 0 && words( datum('price').closest('label').children[0] ).indexOf( MARKUP ) === 0 && datum('title').value === MARKUP );
delete moduleEn['/_admin/elements/type/locked/hint'];
delete moduleEn['/_admin/elements/field/locked/price'];

// --- an element the address names and the type has not ----------------------------------------------------------

forget();
panel._openForm( 'nope' );
answer( 404, { error : 'element not found' } );
check( 'an element that does not exist is a box that says so under the way back to the list, and its address stays the one that was asked for - never null', findAll( form, function( el ) { return el.classList.contains('nino-admin-empty') } ).length === 1
	&& backLink() !== undefined && written.at(-1) === '["elements","locked","nope"]' && form.classList.contains('admin-hidden') === false );

// An address that names an element the list of the type does not hold: the list is read once more, since the element may have been made since
// (by another window, by a colleague) - and only the list that was read just now says it is not there. The element is never asked for
const emptyBox = () => findAll( form, function( el ) { return el.classList.contains('nino-admin-empty') } ).length === 1;
forget();
panel._follow( [ 'locked', 'nobody' ] );
check( 'an element the list does not hold, named by the address, is looked for in the list once more: one request, the list, and the element is not asked for', requests.length === 1 && requests[0].action === 'elements/list' && panel._pendingUri === 'nobody' );
answer( 200, { elements : [ { uri : 'other', label : 'Other' } ], columns : [] } );
check( '...and the list that was read says it is not there: the box, no request for the element, the address kept as it was typed', requests.every( request => request.action !== 'elements/get' ) && panel._pendingUri === undefined && emptyBox() === true
	&& backLink() !== undefined && written.at(-1) === '["elements","locked","nobody"]' && form.classList.contains('admin-hidden') === false );
forget();
panel._follow( [ 'locked', 'fresh' ] );
answer( 200, { elements : [ { uri : 'other', label : 'Other' }, { uri : 'fresh', label : 'Made a minute ago' } ], columns : [] } );
check( 'an element made since the list was read opens, as one the list has: the list is read again and then the element asked for - no box, no 404', requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === 'fresh' && panel._pendingUri === undefined );
answer( 404, { error : 'not found' } );

// A list that is still on its way is waited for: the address is looked for in the list that comes, and no second list is asked for
forget();
panel._currentType = null;
panel._follow( [ 'locked', 'other' ] );
check( '...where the type is read first (a reload), the list of it is the only request, and the element is neither asked for nor said to be missing before it is here', requests.length === 1 && requests[0].action === 'elements/list' && panel._pendingUri === 'other' && emptyBox() === false );
panel._follow( [ 'locked', 'later' ] );
check( '...an address that changes while the list is on its way is looked for in that list: no further request', requests.length === 1 && panel._pendingUri === 'later' );
answer( 200, { elements : [ { uri : 'other', label : 'Other' }, { uri : 'later', label : 'Later' } ], columns : [] } );
check( '...and the last one named is opened from it', requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === 'later' );
answer( 404, { error : 'not found' } );

// A list that was on its way when the address went back to the types is not waited for: nothing is left loading
forget();
panel._currentType = 'locked';
panel._selectType( 'locked', LOCKED, 'Locked' );
check( 'a list asked for is on its way', panel._listLoading === true );
panel._follow( [] );
check( '...and the address that goes back to the types drops it: the list is no longer waited for, and the type picked next is not left waiting for an answer that falls away', panel._listLoading === false );

// The list that is read again for an address the list does not hold is not drawn over a level the person has gone back to: nothing of it is
// opened, no box is drawn, and no address is written over the entry the person returned to
const showList = function() { panel._isNew = false; panel._currentUri = null; form.classList.add('admin-hidden'); types.classList.add('admin-hidden'); list.classList.remove('admin-hidden'); forget() };
const backTo = function( parts, level ) {
	forget();
	panel._currentType = 'locked';
	panel._elements = [ { uri : 'other', label : 'Other' } ];
	panel._listLoading = false;
	showList();
	panel._follow( [ 'locked', 'nosuch' ] );
	const refresh = requests.at(-1);
	const asked = requests.length;
	written.length = 0;
	panel._follow( parts );
	refresh.callback( { status : 200, responseJSON : { elements : [ { uri : 'other', label : 'Other' } ], columns : [] } } );
	return { asked : asked, level : level() };
};
const onList = () => list.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true;
const gone = backTo( [ 'locked' ], onList );
check( 'back to the list while the list is read again for an element that is not there: the list stays, no box is drawn over it, and nothing writes the address of that element over the entry the person returned to',
	gone.asked === 1 && gone.level === true && emptyBox() === false && written.every( entry => entry.indexOf( 'nosuch' ) === -1 ) && panel._pendingUri === undefined && panel._listLoading === false );
const away = backTo( [], () => types.classList.contains('admin-hidden') === false );
check( '...and back to the types the same: the types stay, nothing of the list that was dropped is shown, and the next type picked waits for nothing', away.level === true && emptyBox() === false && written.every( entry => entry.indexOf( 'nosuch' ) === -1 ) && panel._pendingUri === undefined && panel._listLoading === false );

// A list that cannot be read says nothing of the element: the server is asked for it, and what it answers is the answer; no list is left loading
forget();
panel._currentType = 'locked';
panel._elements = [ { uri : 'other', label : 'Other' } ];
panel._listLoading = false;
showList();
panel._follow( [ 'locked', 'asked' ] );
answer( 500, { error : 'broken' } );
check( 'the list read again for an address fails (500): it is not taken for an answer - the element is asked for, and nothing is left loading', requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === 'asked' && panel._listLoading === false && panel._pendingUri === undefined );
answer( 404, { error : 'not found' } );
check( '...and the server\'s 404 is the box that says it is not there', emptyBox() === true );
showList();
panel._follow( [ 'locked', 'asked' ] );
answer( 0, null );
check( '...so is a request that never got an answer (no status): the element is asked for', requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === 'asked' && panel._listLoading === false );
answer( 200, { global : {}, locales : { de_DE : { title : 'Asked' } }, raw : {} } );

// What was on its way to being opened is not what the address names any more: a late answer draws nothing over the box
forget();
panel._openForm( 'slow' );
const slow = requests.at(-1);
panel._follow( [ 'locked', 'nobody' ] );
answer( 200, { elements : [ { uri : 'other', label : 'Other' } ], columns : [] } );
slow.callback( { status : 200, responseJSON : { global : {}, locales : { de_DE : { title : 'Slow' } }, raw : {} } } );
check( 'an element still being read when the address names one that is not there does not draw its form over the box', emptyBox() === true && panel._currentUri === null );

// An element whose uri reads @new, and one called new: the word @new is the form that makes an element, %40new the key of the element that is
// called that, and new its own key. What the panel writes is read back by the router, and the panel opens what the router read
panel._types = [ { type : 'roundtrip', title : 'Round trip', model : MODEL } ];
panel._currentType = 'roundtrip';
panel._elements = [ { uri : '@new', label : 'At new' }, { uri : 'new', label : 'New' }, { uri : 'hammer', label : 'Hammer' } ];
const reread = function( hash ) {
	const read = codec.read( hash );
	panel._follow( read.parts );
	return read;
};
const toList = function() { panel._isNew = false; panel._currentUri = null; form.classList.add('admin-hidden'); list.classList.remove('admin-hidden'); types.classList.add('admin-hidden'); forget() };

toList();
panel._visit( '@new' );
const keyHash = codec.href( 'elements', [ 'roundtrip', '@new' ] );
check( 'the element whose uri reads @new is written as its key, %40new, and the form that makes one as the word', keyHash === '#elements/elements/roundtrip/%40new' && codec.href( 'elements', [ 'roundtrip', codec.word('new') ] ) === '#elements/elements/roundtrip/@new'
	&& requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === '@new' );
toList();
reread( keyHash );
check( '...read back by the router and followed by the panel, %40new asks for the element @new and opens no blank form', requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === '@new' && panel._isNew === false );
toList();
reread( '#elements/elements/roundtrip/@new' );
check( '...@new opens the blank form of a new element, no element is asked for', requests.every( request => request.action !== 'elements/get' ) && panel._isNew === true );
toList();
reread( '#elements/elements/roundtrip/new' );
check( '...and an element called new is an element: asked for as that, never a blank form', requests.at(-1).action === 'elements/get' && requests.at(-1).payload.uri === 'new' && panel._isNew === false );
toList();
reread( '#elements/elements/roundtrip/%40new' );
answer( 200, { global : stored().global, locales : stored().locales, raw : {} } );
settle();
check( '...once it is open the element @new is the element on the form, with the address it was opened at: a save writes THAT element and makes no second one', panel._currentUri === '@new' && panel._isNew === false
	&& written.at(-1) === JSON.stringify( [ 'elements', 'roundtrip', '@new' ] ) );

// --- text is text ---------------------------------------------------------------------------------------

const script = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/admin.js' ), 'utf8' );
check( 'what an element holds is put on screen as text or as the value of a control: the script assigns no markup to anything but to empty it', ( script.match( /\.innerHTML\s*=\s*(?!'')\S/g ) || [] ).length === 0 );
check( '...and no image url is built under a /uploads directory: a stored image lives under /images', /(?:asset|public)Url\(\s*'\/uploads\//.test( script ) === false && /publicUrl\(\s*'\/images\/'\+ state\.value\s*\)/.test( script ) === true );

// --- the words are in both languages ---------------------------------------------------------------------

const used = new Set( ( script.match( /'\/_admin\/elements\/[a-z0-9\/-]+'/g ) || [] ).map( function( key ) { return key.slice( 1, -1 ) } ).filter( function( key ) { return key.slice( -1 ) !== '/' && /^\/_admin\/elements\/(label|help|note|msg|error|expect|confirm|none|no-match|empty)(\/|$)/.test( key ) } ) );
// The two steps are named by the word that is put together from their kind
[ '/_admin/elements/label/prev', '/_admin/elements/label/next' ].forEach( function( key ) { used.add( key ) } );
const missing = [ ...used ].filter( function( key ) { return ( key in moduleEn ) === false || ( key in moduleDe ) === false } );
check( 'every word of the panel is a fill in English and in German'+ ( missing.length === 0 ? '' : ' - not '+ missing.join(', ') ), used.size > 40 && missing.length === 0 );

check( 'every address the panel wrote or drew - the types, a type, an element, a new one - is read back by the router as the same screen and the same parts', codec.written.length > 4 && codec.mismatches.length === 0
	&& codec.written.some( written => ( typeof written.parts[1] === 'object' && String( written.parts[1] ) === '@new' ) ) && codec.written.some( written => written.parts.length === 2 && written.parts[1] !== '@new' ) );

done();
