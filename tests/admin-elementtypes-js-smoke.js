/**
 *	Nino									A compact filesystembased php framework
 *	admin-elementtypes-js-smoke.js	DOM-light checks for the Element Types tab's
 *											script (_admin/Nino/Modules/Elements/assets/types.js).
 *
 *											Every view has an address - the types, one type, a new
 *											one at @new - and a save leaves the person in the form.
 *											The form is built from the blocks of the workbench with
 *											the label at the left of the field: a field of the type
 *											is a card whose options have words for labels, whose yes-
 *											or-no properties are one group of ticks and whose
 *											buttons are icons; the types of a field are words. What
 *											is wrong - here or in the answer of the server - is said
 *											at the field, and the copy of a type is a dialog of the
 *											block. A field's position in the model is not cosmetic:
 *											it is the order every element form draws the type's
 *											fields in, and the cards are drawn again from the model
 *											on every move, so that reading them back first is what
 *											keeps in-progress edits alive. A type read and saved
 *											without a change sends what it holds (a fixture the PHP
 *											suite writes the file from).
 *
 *	Usage: node tests/admin-elementtypes-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const fs 		= require('fs');
const path 	= require('path');
const { check, done, fills, load, element, findAll, fire, shellRouter } = require( './harness.js' );

// What the panel writes into the address goes through the router's own codec: every state written, every link drawn, is read back
const codec = shellRouter();

const fixture = JSON.parse( fs.readFileSync( path.join( __dirname, 'fixtures/admin/elements-roundtrip.json' ), 'utf8' ) );

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
pane.dataset.panel = 'types';
const head = element('div');
head.className = 'admin-panel-head';
const paneTitle = control('h2');
paneTitle.className = 'admin-panel-title';
head.appendChild( paneTitle );
pane.appendChild( head );
const list = element('div');
list.id = 'types-list';
const form = element('div');
form.id = 'types-form';
pane.appendChild( list );
pane.appendChild( form );

function everything() {
	return [ list, form ].concat( findAll( list, function() { return true } ), findAll( form, function() { return true } ) );
}

const requests = [];
const toasts = [];
const questions = [];
const dialogs = [];
let questionAnswer = false;
const written = [];
const moved = [];
const guarded = [];
const touched = [];
const invalidated = [];
let hash = { panel : 'types', parts : [] };
const watched = [];

const Nino = {
	admin : {
		touch : function( topic ) { touched.push( topic ) },
		stamp : function() { return 0 },
		elements : { invalidate : function() { invalidated.push( 'elements' ) } },
		router : {
			current : function() { return hash },
			go : function( panel, parts ) { codec.check( panel, parts ); moved.push( JSON.stringify( [ panel ].concat( parts ) ) ) },
			set : function( panel, parts ) { codec.check( panel, parts ); written.push( JSON.stringify( [ panel ].concat( parts ) ) ) },
			leave : function( names, open, proceed ) { proceed() },
			href : function( name, parts ) { return codec.check( name, parts ) }, word : codec.word, wordOf : codec.wordOf, isWord : codec.isWord,
		},
		dirty : {
			watchForm : function( name, getter, save ) { watched.push( { name : name, getter : getter, save : save } ) },
			guard : function( names, proceed ) { guarded.push( names.join() ); proceed( 'discard' ) },
			refresh : function() {},
			snapshot : function() {},
		},
	},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
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
};
sandbox.window = { Nino : Nino, location : { hash : '' }, setTimeout : function() { return 1 }, clearTimeout : function() {}, Event : function( type, options ) { this.type = type; this.bubbles = ( options ?? {} ).bubbles === true } };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );

Nino.adminUi.say = function( words ) { toasts.push( words ) };
Nino.adminUi.confirm = function( level, options ) {
	questions.push( Object.assign( { level : level }, options ) );
	options.onChoose( questionAnswer, options.typed );
	return true;
};
Nino.adminUi.formDialog = function( options ) { dialogs.push( options ); return true };
load( '_admin/Nino/Modules/Elements/assets/types.js', context, 'elementtypes.js' );

const panel = Nino.admin.types;

/** Answer the last request - or the last one of an action that is not answered yet - the way Nino.http.sendRequest() calls back */
function answer( status, body, action ) {
	const request = action === undefined ? requests[requests.length - 1] : requests.filter( function( r ) { return r.action === action && r.answered !== true } ).pop();
	request.answered = true;
	request.callback( { status : status, responseJSON : body } );
}

function forget() {
	requests.length = 0;
	toasts.length = 0;
	questions.length = 0;
	dialogs.length = 0;
	moved.length = 0;
	written.length = 0;
	guarded.length = 0;
	touched.length = 0;
	invalidated.length = 0;
}

function theForm() { return findAll( form, function( el ) { return el.tagName === 'FORM' } )[0] }
function bar() { return findAll( form, function( el ) { return el.classList.contains('nino-admin-actionbar') } )[0] }
function barButtons() { return bar().children.filter( function( el ) { return el.tagName === 'BUTTON' } ) }
function danger() { return barButtons().find( function( el ) { return el.classList.contains('nino-admin-btn-danger') } ) }
function primary() { return barButtons().find( function( el ) { return el.classList.contains('nino-admin-btn-primary') } ) }
function cards() { return findAll( form, function( el ) { return el.classList.contains('types-field') } ) }
function sentences() { return findAll( form, function( el ) { return el.classList.contains('nino-admin-field-error') } ) }
function lineState() { return findAll( form, function( el ) { return el.id === 'types-form-msg' } )[0] }
function save() { fire( theForm(), 'submit' ) }
function typeInto( el, value ) { el.value = value; fire( el, 'input' ); fire( theForm(), 'input', { target : el } ) }
function pick( select, value ) { select.value = value; fire( select, 'change' ); fire( theForm(), 'change', { target : select } ) }
function tick( card, flag, on ) {
	const box = findAll( card, function( el ) { return el.type === 'checkbox' && el.value === flag } )[0];
	box.checked = on;
	box.dispatchEvent( { type : 'change', bubbles : true } );
}
function ofCard( card, key ) { return findAll( card, function( el ) { return el.dataset.key === key || el.dataset.field === key } )[0] }
function rows() { return findAll( list, function( el ) { return el.classList.contains('nino-admin-list-row') } ) }
function buttonsOf( card ) { return findAll( findAll( card, function( el ) { return el.classList.contains('nino-admin-move') } )[0], function( el ) { return el.tagName === 'BUTTON' } ) }

const MODEL = fixture.model;
const TYPELIST = {
	types : [ { uri : 'roundtrip', title : 'Round trip', fieldCount : Object.keys( MODEL ).length }, { uri : 'roundmaker', title : 'Makers', fieldCount : 1 } ],
	fieldTypes : [ 'string', 'integer', 'double', 'boolean', 'array', 'date', 'datetime', 'image', 'element' ],
	suffixTypes : [ 'string', 'integer', 'double', 'array', 'date', 'datetime' ],
};
function got( extra ) {
	return Object.assign( { uri : 'roundtrip', title : 'Round trip', model : MODEL, autoincrement : false, next : '00001', elements : 3, referencedBy : [] }, extra || {} );
}

console.log('Admin Element Types');

// The shell finds a panel's script by its nav uri alone (Nino.admin[uri]); a second name is one more spelling to keep in step
check( 'the Types tab\'s script is Nino.admin.types and has no second name, and it watches its form for the shell', typeof panel === 'object' && typeof panel.init === 'function' && Nino.admin.elementTypes === undefined
	&& watched.length === 1 && watched[0].name === 'types' );

// --- the list ----------------------------------------------------------------------------

panel.init();
check( 'the panel asks for the types', requests.length === 1 && requests[0].action === 'types/list' );
answer( 200, TYPELIST );
check( 'the types are the rows of the block, a link each that writes its address, the title over the number of its fields and the identifier, quietly', rows().length === 2
	&& findAll( rows()[0], function( el ) { return el.classList.contains('nino-admin-row-open') } )[0].href === '#elements/types/roundtrip' && findAll( rows()[0], function( el ) { return el.tagName === 'STRONG' } )[0].textContent === 'Round trip'
	&& findAll( rows()[0], function( el ) { return el.tagName === 'SMALL' } )[0].textContent === Object.keys( MODEL ).length+ ' fields · /roundtrip' && findAll( rows()[1], function( el ) { return el.tagName === 'SMALL' } )[0].textContent === '1 field · /roundmaker' );
const listBar = findAll( list, function( el ) { return el.classList.contains('nino-admin-actionbar') } )[0];
check( 'New element type is the one main button of the bar of the list, and no unsaved note stands over the list', listBar.children.length === 1 && listBar.children[0].textContent === text('/_admin/types/label/new') && listBar.children[0].classList.contains('nino-admin-btn-primary')
	&& findAll( list, function( el ) { return el.classList.contains('nino-admin-hint') } ).length === 0 );

// --- a type opens at its address ----------------------------------------------------------------

forget();
fire( findAll( rows()[0], function( el ) { return el.classList.contains('nino-admin-row-open') } )[0], 'click' );
check( 'a row opens the form of its type through the router and asks for it', moved.at(-1) === '["types","roundtrip"]' && requests.at(-1).action === 'types/get' && requests.at(-1).payload.uri === 'roundtrip' );
answer( 200, got() );
check( 'the form is novalidate, shows the type\'s name and identifier in the bar of context under one way back that leads to the types, and writes its address', theForm().noValidate === true && written.at(-1) === '["types","roundtrip"]'
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-back-link') } ).length === 1
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-contextbar-title') } )[0].textContent === 'Round trip' && findAll( form, function( el ) { return el.classList.contains('nino-admin-contextbar-code') } )[0].textContent === '/roundtrip' );
check( 'every field of the model is a card: a fieldset with its legend, in the order of the model', cards().length === Object.keys( MODEL ).length && cards().every( function( card ) { return card.tagName === 'FIELDSET' } )
	&& cards().map( function( card ) { return findAll( card, function( el ) { return el.tagName === 'LEGEND' } )[0].textContent } ).slice( 0, 3 ).join() === 'Title,summary,body' );

// the label at the left: every control has a name
const card0 = cards()[0];
const controls0 = findAll( card0, function( el ) { return el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA' } );
check( 'every control of a card is in a label or in a group with a legend that names it: a placeholder is never the name of a field', controls0.length > 3 && controls0.every( function( el ) {
	for( let node = el; node !== null; node = node.parentNode ) {
		if( node.tagName === 'LABEL' && findAll( node, function( child ) { return child.tagName === 'SPAN' && child.textContent !== '' } ).length > 0 )
			return true;
		if( node.tagName === 'FIELDSET' && node.classList.contains('nino-admin-checklist-field') )
			return true;
	}
	return false;
} ) && controls0.filter( function( el ) { return el.placeholder !== '' } ).map( function( el ) { return el.placeholder } ).join() === text('/_admin/types/placeholder/options') );
check( 'the options of a card stand in the grid of the fields, where the label is at the left from the width the stylesheet says', findAll( card0, function( el ) { return el.classList.contains('nino-admin-fieldgrid') } ).length === 2 );
check( 'what a field does is behind a (?): the card has no paragraph of help that stands open', findAll( card0, function( el ) { return el.tagName === 'P' && ( el.classList.contains('nino-admin-hint') || el.classList.contains('nino-admin-field-hint') ) } ).length === 0
	&& findAll( card0, function( el ) { return el.classList.contains('nino-admin-help-button') } ).length >= 4 );

// types are words
const typeSelect = ofCard( card0, 'type' );
check( 'the types of a field are words of the workbench, in the order the server lists them, never the raw name of the type', typeSelect.children.map( function( option ) { return option.textContent } ).join() === 'Text,Whole number,Decimal number,Yes or no,List,Date,Date and time,Image,Reference to an element'
	&& typeSelect.value === 'string' );
check( '...and so are the words of the names a field is offered: the key of a field that is one of the usual ones is a word, an own key is chosen with the word Own name', ofCard( card0, 'name' ).children.slice( -1 )[0].textContent === text('/_admin/types/label/ownkey') && ofCard( card0, 'name' ).value === 'title' );

// the properties are one group of ticks
const flagGroup = findAll( card0, function( el ) { return el.classList.contains('nino-admin-checklist-field') } )[0];
const flagBoxes = findAll( flagGroup, function( el ) { return el.type === 'checkbox' } );
check( 'the yes-or-no properties of a text are one group of ticks with one (?) for the group - per language, required, rich text, paragraphs and lists, line breaks - and the ones the field has are ticked', flagBoxes.map( function( el ) { return el.value+ ':'+ el.checked } ).join() === 'locale:true,required:true,html:false,blocks:false,breaks:false'
	&& findAll( flagGroup, function( el ) { return el.classList.contains('nino-admin-help-button') } ).length === 1 );
check( '...each tick is named by its word', findAll( flagGroup, function( el ) { return el.tagName === 'LABEL' } ).map( function( el ) { return findAll( el, function( child ) { return child.tagName === 'SPAN' } )[0].textContent } ).join() === [ 'perlocale', 'required', 'richtext', 'blocks', 'breaks' ].map( function( key ) { return text('/_admin/types/label/'+ key) } ).join() );
check( '...paragraphs and lists are for rich text only, and line breaks for plain text only', flagBoxes[3].disabled === true && flagBoxes[4].disabled === false );
tick( card0, 'html', true );
check( '...ticking rich text frees paragraphs and lists and shuts the line breaks', flagBoxes[3].disabled === false && flagBoxes[4].disabled === true );
tick( card0, 'html', false );
const imageCard = cards().find( function( card ) { return ofCard( card, 'type' ).value === 'image' } );
check( 'an image has no tick for required: its file is uploaded once the element exists, so a required one could not be satisfied by the save that creates it', imageCard === undefined );
pick( ofCard( cards()[1], 'type' ), 'image' );
check( '...a text that becomes one loses it, and the ticks that are left are the ones that mean something for a picture', findAll( cards()[1], function( el ) { return el.type === 'checkbox' } ).map( function( el ) { return el.value } ).join() === 'locale,fit' );
pick( ofCard( cards()[1], 'type' ), 'string' );

// --- the buttons of a card are icons -------------------------------------------------------------------

check( 'a card has three icon buttons, named: up, down and remove - the first card cannot go up, the last cannot go down - and none of them is a character', buttonsOf( card0 ).length === 3
	&& buttonsOf( card0 ).map( function( el ) { return el.attributes['aria-label'] } ).join() === [ text('/_admin/common/label/moveup'), text('/_admin/common/label/movedown'), text('/_admin/types/label/removefield') ].join()
	&& buttonsOf( card0 )[0].disabled === true && buttonsOf( cards().at(-1) )[1].disabled === true && buttonsOf( card0 )[2].classList.contains('nino-admin-btn-danger') && buttonsOf( card0 ).every( function( el ) { return el.textContent === '' } ) );

// --- moving, adding and removing keep what was typed ---------------------------------------------------------

typeInto( ofCard( cards()[1], 'maxlength' ), '999' );
const firstKey = function() { return cards().map( function( card ) { return ofCard( card, 'own' ).value } ) };
const order = firstKey().join();
fire( buttonsOf( cards()[1] )[1], 'click' );
check( 'a card moved down takes the place of the next one; the order of the cards is the order of the model', firstKey().slice( 1, 3 ).join() === order.split(',').slice( 1, 3 ).reverse().join() );
check( '...what was typed into a card before the move is still in it', ofCard( cards()[2], 'maxlength' ).value === '999' );
check( '...and the focus stays on the button that was pressed, which stands in the card the field moved to, so it can be moved again', active === buttonsOf( cards()[2] )[1] );
fire( buttonsOf( cards()[2] )[0], 'click' );
check( 'moving it up again restores the order', firstKey().join() === order );

fire( findAll( form, function( el ) { return el.id === 'types-form-addfield' } )[0], 'click' );
check( 'Add field draws one more card at the end, and the focus goes to its name', cards().length === Object.keys( MODEL ).length + 1 && active === ofCard( cards().at(-1), 'name' ) && ofCard( cards()[1], 'maxlength' ).value === '999' );
check( '...a field that has no name yet is called New field in its legend', findAll( cards().at(-1), function( el ) { return el.tagName === 'LEGEND' } )[0].textContent === text('/_admin/types/label/field-new') );
const newCard = cards().at(-1);
pick( ofCard( newCard, 'name' ), 'price' );
check( 'choosing a word for the name puts its usual type on the select of the type and its words into the legend, and shuts the own name', ofCard( newCard, 'type' ).value === 'double' && findAll( newCard, function( el ) { return el.tagName === 'LEGEND' } )[0].textContent === workbenchEn['/_admin/common/word/price'] && ofCard( newCard, 'own' ).value === 'price' && ofCard( newCard, 'own' ).closest('label').hidden === true );
const countBefore = cards().length;
typeInto( ofCard( cards()[1], 'maxlength' ), '777' );
fire( buttonsOf( newCard )[2], 'click' );
check( 'Remove field takes the card away - without a question, it is a draft - and the focus goes to the card that took its place, else to the card before it', cards().length === countBefore - 1 && active === buttonsOf( cards().at(-1) )[2] && ofCard( cards()[1], 'maxlength' ).value === '777' );
typeInto( ofCard( cards()[1], 'maxlength' ), String( MODEL.summary.maxlength ) );

// changing the type keeps what the field has and draws the options of the new type
const summary = cards()[1];
tick( summary, 'required', true );
pick( ofCard( summary, 'type' ), 'image' );
check( 'a field changed to another type draws that type\'s options, with the ticks it had where they mean the same', ofCard( summary, 'width' ) !== undefined && ofCard( summary, 'maxlength' ) === undefined
	&& findAll( summary, function( el ) { return el.type === 'checkbox' } ).map( function( el ) { return el.value } ).join() === 'locale,fit' );
pick( ofCard( summary, 'type' ), 'string' );
check( '...and back again: the text it was keeps its length and its ticks', ofCard( summary, 'maxlength' ).value === '400' && findAll( summary, function( el ) { return el.type === 'checkbox' && el.value === 'locale' } )[0].checked === true
	&& findAll( summary, function( el ) { return el.type === 'checkbox' && el.value === 'required' } )[0].checked === true );
tick( summary, 'required', false );

// --- the round trip ----------------------------------------------------------------------------------------------------

check( 'a type read and saved without a change builds the model it was read from: the payload of the fixture the PHP suite writes the file from', JSON.stringify( panel._buildModel() ) === JSON.stringify( fixture.typePayload.model ) );

forget();
save();
check( 'a type saved without a change writes nothing, so its file stays as it is - the order of its keys included - and the toast says it', requests.length === 0 && toasts.length === 1 );
typeInto( findAll( form, function( el ) { return el.id === 'types-form-title' } )[0], 'Round trip 2' );
forget();
save();
check( 'a save of a type that exists asks about the unsaved input of the elements next door first, and sends its identifier, title, numbering and model, with no rename', guarded.join() === 'elements' && requests.length === 1 && requests[0].action === 'types/save' && requests[0].payload.uri === 'roundtrip' && requests[0].payload.title === 'Round trip 2'
	&& requests[0].payload.autoincrement === false && JSON.stringify( requests[0].payload.renames ) === '{}' && JSON.stringify( requests[0].payload.model ) === JSON.stringify( fixture.typePayload.model ) );
check( '...the controls and the bar are shut while it is on its way: the state of the line says saving', lineState().dataset.state === 'saving' && panel._saving === true );
answer( 200, { uri : 'roundtrip', references : [] } );
check( 'the answer makes the form draw again from what the server holds: the type is read again, and so is the list behind it', requests.some( function( r ) { return r.action === 'types/get' && r.answered !== true } ) && requests.some( function( r ) { return r.action === 'types/list' && r.answered !== true } ) );
answer( 200, got(), 'types/get' );
answer( 200, TYPELIST, 'types/list' );
check( '...the person stays in the form of the type: no jump to the list, the line is empty, the toast says it once', toasts.join() === text('/_admin/common/msg/saved') && form.classList.contains('admin-hidden') === false && lineState().textContent === '' && invalidated.length === 1 && touched.includes('elements') );

// --- what is wrong -------------------------------------------------------------------------------------------------------

forget();
typeInto( ofCard( cards()[0], 'own' ), '' );
pick( ofCard( cards()[0], 'name' ), '' );
save();
check( 'a field with no name holds the save back: nothing is sent, the sentence stands at the name and says it is missing, the line says so once, the focus is on it', requests.length === 0 && sentences().length === 1
	&& sentences()[0].textContent === Nino.adminUi.format( text('/_admin/common/error/missing'), text('/_admin/types/label/fieldname') ) && lineState().textContent === text('/_admin/types/error/fields') && lineState().dataset.state === 'error' && active === ofCard( cards()[0], 'own' ) );
typeInto( ofCard( cards()[0], 'own' ), 'summary' );
check( 'what is typed into the field lets go of its sentence', sentences().length === 0 );
save();
check( 'two fields with one name are refused at the later one', requests.length === 0 && sentences().length === 1 && sentences()[0].textContent === Nino.adminUi.format( text('/_admin/types/error/field-twice'), 'summary' ) );
typeInto( ofCard( cards()[0], 'own' ), 'Bad Key!' );
save();
check( 'a name that is no key is not valid, in the words of the guide, at its field', requests.length === 0 && sentences()[0].textContent === Nino.adminUi.format( text('/_admin/types/error/invalid'), text('/_admin/types/label/fieldname'), text('/_admin/types/expect/key') ) );
typeInto( ofCard( cards()[0], 'own' ), '.uri' );
save();
check( 'a name with a dot in front is refused at its field, in the same words: the keys with a dot belong to the kernel', requests.length === 0 && sentences().length === 1 && sentences()[0].textContent === Nino.adminUi.format( text('/_admin/types/error/invalid'), text('/_admin/types/label/fieldname'), text('/_admin/types/expect/key') ) );
typeInto( ofCard( cards()[0], 'own' ), 'title' );

// the numbers and the reference
pick( ofCard( cards()[1], 'type' ), 'image' );
typeInto( ofCard( cards()[1], 'width' ), '' );
typeInto( ofCard( cards()[1], 'height' ), '0' );
save();
check( 'an image needs its size: a size that is missing, or not a whole number of 1 or more, is said at its field in the words of the guide', requests.length === 0 && sentences().map( function( el ) { return el.textContent } ).join('|') === Nino.adminUi.format( text('/_admin/common/error/missing'), text('/_admin/common/label/width') )+ '|'+ Nino.adminUi.format( text('/_admin/types/error/invalid'), text('/_admin/common/label/height'), text('/_admin/types/expect/number') ) );
pick( ofCard( cards()[1], 'type' ), 'string' );
// put the cards back to the model before the next check
panel._fields = Object.keys( MODEL ).map( function( key ) { return Object.assign( { key : key, originalKey : key }, MODEL[key] ) } );
panel._renderFields();
forget();
const reference = cards().find( function( card ) { return ofCard( card, 'type' ).value === 'element' } );
check( 'a reference offers the other types by title, with their identifier behind it, and not the type it is drawn in', ofCard( reference, 'elementType' ).children.map( function( o ) { return o.value } ).join() === 'roundmaker' && ofCard( reference, 'elementType' ).children[0].textContent === 'Makers (/roundmaker)' );
check( 'how many a list of references may hold is shut until it is a list, and 0 is no limit', ofCard( cards().at(-1), 'multipleMax' ).value === '3' );

// --- a new type ------------------------------------------------------------------------------------------------------------

forget();
panel._openForm( null );
check( 'a new type has the address #elements/types/@new, an identifier as its first field, required, and the main button creates it; there is no Delete and no copy of what does not exist', written.at(-1) === '["types",{"word":"@new"}]'
	&& findAll( form, function( el ) { return el.id === 'types-form-uri' } )[0].attributes['aria-required'] === 'true' && primary().textContent === text('/_admin/types/label/create') && danger() === undefined && barButtons().length === 1 && cards().length === 0 );
save();
check( 'the identifier is the first thing a save asks for', requests.length === 0 && sentences()[0].textContent === Nino.adminUi.format( text('/_admin/common/error/missing'), text('/_admin/types/label/uri') ) && active === findAll( form, function( el ) { return el.id === 'types-form-uri' } )[0] );
typeInto( findAll( form, function( el ) { return el.id === 'types-form-uri' } )[0], 'Bad Name' );
save();
check( 'an identifier that is no slug is not valid, and one a type has is taken - at the field, in the guide\'s words', sentences()[0].textContent === Nino.adminUi.format( text('/_admin/types/error/invalid'), text('/_admin/types/label/uri'), text('/_admin/types/expect/uri') ) );
typeInto( findAll( form, function( el ) { return el.id === 'types-form-uri' } )[0], 'roundmaker' );
save();
check( '...taken', sentences()[0].textContent === Nino.adminUi.format( text('/_admin/elements/error/taken'), text('/_admin/types/label/uri') ) );
typeInto( findAll( form, function( el ) { return el.id === 'types-form-uri' } )[0], 'fresh' );
typeInto( findAll( form, function( el ) { return el.id === 'types-form-title' } )[0], 'Fresh' );
fire( findAll( form, function( el ) { return el.id === 'types-form-addfield' } )[0], 'click' );
pick( ofCard( cards()[0], 'name' ), 'title' );
forget();
save();
check( 'a new type is created with its identifier, its title and the model of its cards', requests.length === 1 && requests[0].action === 'types/create' && requests[0].payload.uri === 'fresh' && requests[0].payload.title === 'Fresh' && Object.keys( requests[0].payload.model ).join() === 'title' );
answer( 409, { error : 'type already exists', code : 'types_exists', field : 'uri' } );
check( 'a refusal that names the identifier is said at it, with the focus on it, and in the line; nothing says that it was saved', sentences().length === 1 && sentences()[0].parentNode !== null && toasts.length === 0 && lineState().dataset.state === 'error' && active === findAll( form, function( el ) { return el.id === 'types-form-uri' } )[0] );
forget();
save();
answer( 400, { error : 'field "x" references the unknown element type "y"' } );
check( 'a refusal that names no field is said in the line alone', sentences().length === 0 && lineState().dataset.state === 'error' );
forget();
save();
answer( 200, { uri : 'fresh', title : 'Fresh' } );
check( 'a type that was created gets its address and is drawn again as the form of a type that exists, with Delete and the copy', written.at(-1) === '["types","fresh"]' && requests.some( function( r ) { return r.action === 'types/get' } ) );
answer( 200, got( { uri : 'fresh', title : 'Fresh', model : { title : { type : 'string' } }, elements : 0 } ), 'types/get' );
answer( 200, TYPELIST, 'types/list' );
check( '...and nothing jumped to the list: the main button is Save, the toast says it, and the elements are told', danger() !== undefined && primary().textContent === text('/_admin/common/label/save') && toasts.length === 1 && invalidated.length === 1 );

// --- a rename -------------------------------------------------------------------------------------------------------------------------

forget();
panel._openForm( 'roundtrip', null, true );
answer( 200, got() );
typeInto( ofCard( cards()[0], 'own' ), 'heading' );
pick( ofCard( cards()[0], 'name' ), '' );
typeInto( ofCard( cards()[0], 'own' ), 'heading' );
check( 'a field renamed says so beside its name: the stored values move when it is saved', findAll( cards()[0], function( el ) { return el.classList.contains('nino-admin-field-note') && el.hidden === false } ).length === 1 );
forget();
questionAnswer = false;
save();
check( 'a rename asks first - in the weight of a big change, naming each pair - and a No sends nothing and ends the save', questions.length === 1 && questions[0].level === 'big' && questions[0].message.indexOf('title → heading') !== -1 && requests.length === 0 );
questionAnswer = true;
save();
check( 'a yes sends the rename with the model', requests.length === 1 && requests[0].payload.renames.title === 'heading' && Object.keys( requests[0].payload.model )[0] === 'heading' );
questionAnswer = false;
answer( 200, { uri : 'roundtrip', references : [ { kind : 'template', name : 'page-x', field : 'title' } ] } );
answer( 200, got( { model : Object.assign( { heading : MODEL.title }, MODEL ) } ), 'types/get' );
answer( 200, TYPELIST, 'types/list' );
check( 'what a rename cannot move is a notice at the head of the form it was made in, until the form is drawn again', findAll( form, function( el ) { return el.classList.contains('nino-admin-notice') } ).length === 1 );
forget();
save();
check( 'a second save sends no rename - nothing at all: the form was drawn again from what the server holds, so the name it was saved under is the one it has now', requests.length === 0 );

// --- the copy ------------------------------------------------------------------------------------------------------------------------------

forget();
panel._openForm( 'roundtrip', null, true );
answer( 200, got( { model : Object.assign( {}, MODEL, { title : Object.assign( { label : { de_DE : 'Titel' } }, MODEL.title ) } ) } ) );
forget();
fire( findAll( form, function( el ) { return el.id === 'types-form-duplicate' } )[0], 'click' );
check( 'Duplicate element type asks about the unsaved input of this form and of the elements next door, and then opens a dialog of the block with the identifier and the title', guarded.join() === 'types,elements' && dialogs.length === 1
	&& dialogs[0].fields.map( function( field ) { return field.key } ).join() === 'uri,title' && dialogs[0].fields[0].required === true && dialogs[0].fields[0].help !== '' && dialogs[0].title === text('/_admin/types/label/duplicate') && dialogs[0].submit === dialogs[0].title );
check( '...the identifier of the copy is checked before anything is sent: one of the types, and a slug', dialogs[0].fields[0].validate('roundtrip') !== '' && dialogs[0].fields[0].validate('Bad Name') !== '' && dialogs[0].fields[0].validate('copy') === '' );
check( '...the words a field is called in the element form travel along to the copy, though the form has no control for them and never sends them in a save', JSON.stringify( panel._copyModel().title.label ) === JSON.stringify( { de_DE : 'Titel' } ) && panel._buildModel().title.label === undefined );
const ctl = { closed : false, failed : null, close : function() { this.closed = true }, fail : function( key, words ) { this.failed = [ key, words ] } };
dialogs[0].onSubmit( { uri : 'copy', title : '' }, ctl );
check( 'the copy is created from the form as it stands, with the title of this type where none is given, and its numbering', requests.length === 1 && requests[0].action === 'types/create' && requests[0].payload.uri === 'copy' && requests[0].payload.title === 'Round trip'
	&& Object.keys( requests[0].payload.model ).join() === Object.keys( fixture.typePayload.model ).join() && requests[0].payload.autoincrement === false );
answer( 409, { error : 'type already exists', code : 'types_exists', field : 'uri' } );
check( 'a refusal of the server stands at the identifier in the dialog, which stays open', ctl.closed === false && ctl.failed !== null && ctl.failed[0] === 'uri' && ctl.failed[1] !== '' );
dialogs[0].onSubmit( { uri : 'copy', title : 'Copy' }, ctl );
answer( 200, { uri : 'copy', title : 'Copy' } );
check( 'a copy that was made closes the dialog, tells the elements, reads the types and opens the copy', ctl.closed === true && invalidated.length === 1 && requests.at(-1).action === 'types/list' );
answer( 200, TYPELIST );
check( '...which is opened through the router, at its own address', moved.at(-1) === '["types","copy"]' && requests.at(-1).action === 'types/get' && requests.at(-1).payload.uri === 'copy' );
answer( 200, got( { uri : 'copy', title : 'Copy' } ) );
check( '...and a toast says that the type was duplicated', toasts.at(-1) === text('/_admin/types/msg/duplicated') );

// --- delete -----------------------------------------------------------------------------------------------------------------------------------------

forget();
panel._openForm( 'roundtrip', null, true );
answer( 200, got( { referencedBy : [ 'roundmaker.owner' ] } ) );
check( 'Delete element type is at the left of the bar in the danger kind; where other types point at this one it is shut, and the reason stands behind a (?) beside it', barButtons()[0] === danger() && danger().disabled === true
	&& bar().children.filter( function( el ) { return el.classList.contains('nino-admin-help-button') || findAll( el, function( child ) { return child.classList.contains('nino-admin-help-button') } ).length > 0 } ).length === 1 );
forget();
panel._openForm( 'roundtrip', null, true );
answer( 200, got( { elements : 3 } ) );
forget();
fire( danger(), 'click' );
check( 'the question is asked in the destructive weight with the identifier typed to confirm, names what goes with the type - the number of its elements - as a list under the message, and a No sends nothing', questions.length === 1 && questions[0].level === 'destructive' && questions[0].typed === 'roundtrip'
	&& questions[0].title === text('/_admin/types/label/delete') && questions[0].yes === questions[0].title && questions[0].details[0].lines.some( function( line ) { return line.indexOf('3') !== -1 } ) && requests.length === 0 );
questionAnswer = true;
forget();
fire( danger(), 'click' );
check( 'a yes asks the elements next door about their unsaved input and then sends the identifier typed', guarded.join() === 'elements' && requests.length === 1 && requests[0].action === 'types/delete' && requests[0].payload.uri === 'roundtrip' && requests[0].payload.confirm === 'roundtrip' );
answer( 200, {} );
check( '...and the list is read again and shown in place of the form, a toast says it, and the elements are told', requests.at(-1).action === 'types/list' && invalidated.length === 1 && touched.includes('elements') );
answer( 200, { types : [ TYPELIST.types[1] ], fieldTypes : TYPELIST.fieldTypes, suffixTypes : TYPELIST.suffixTypes } );
check( '...the form of the type is gone', toasts.join() === text('/_admin/types/msg/deleted') && rows().length === 1 && findAll( form, function() { return true } ).length === 0 );
questionAnswer = false;

// --- the address names a level ----------------------------------------------------------------------------------------------------------------

forget();
forget();
panel._restore( [ 'new' ] );
check( 'a type called new is no new type: the address names a type the list does not have, so nothing opens', panel._isNew === false && requests.length === 0 );
panel._restore( codec.parts( { key : '@new' } ) );
panel._follow( codec.parts( { key : '@new' } ) );
check( 'the key @new is no new type - it is read as a key, the address names a type the list does not have - and no blank form is opened for it, on a load and on a step through the history', panel._isNew === false && requests.length === 0 );
panel._restore( codec.parts( '@new' ) );
check( 'the first load opens the blank form for @new', panel._isNew === true );
panel._follow( codec.parts( '@new' ) );
check( 'an address that names a new type opens a blank form, and a type of that name - there may be one called new - is asked for like any other', requests.length === 0 && panel._isNew === true );
panel._follow( [ 'roundmaker' ] );
check( 'an address that names a type opens it', requests.at(-1).action === 'types/get' && requests.at(-1).payload.uri === 'roundmaker' );
answer( 200, got( { uri : 'roundmaker', title : 'Makers', model : { name : { type : 'string' } } } ) );
panel._follow( [ 'nope' ] );
check( 'an address that names nothing is the list', form.classList.contains('admin-hidden') === true );

// --- text is text, and the words are in both languages -------------------------------------------------------------------------------------------

const script = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/types.js' ), 'utf8' );
check( 'the script assigns no markup to anything but to empty it', ( script.match( /\.innerHTML\s*=\s*(?!'')\S/g ) || [] ).length === 0 );
const used = new Set( ( script.match( /'\/_admin\/types\/[a-z0-9\/-]+'/g ) || [] ).map( function( key ) { return key.slice( 1, -1 ) } ).filter( function( key ) { return key.slice( -1 ) !== '/' } ) );
[ 'perlocale', 'required', 'richtext', 'blocks', 'breaks', 'fit', 'multiple' ].forEach( function( flag ) { used.add( '/_admin/types/label/'+ flag ); used.add( '/_admin/types/help/'+ flag ) } );
[ 'string', 'integer', 'double', 'boolean', 'array', 'date', 'datetime', 'image', 'element' ].forEach( function( kind ) { used.add( '/_admin/types/option/type/'+ kind ) } );
const missing = [ ...used ].filter( function( key ) { return ( key in moduleEn ) === false || ( key in moduleDe ) === false } );
check( 'every word of the tab is a fill in English and in German'+ ( missing.length === 0 ? '' : ' - not '+ missing.join(', ') ), used.size > 40 && missing.length === 0 );

check( 'every address the tab wrote or drew - the types, a type, a new one - is read back by the router as the same screen and the same parts', codec.written.length > 4 && codec.mismatches.length === 0
	&& codec.written.some( written => ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@new' ) ) && codec.written.some( written => written.parts.length === 1 && written.parts[0] !== '@new' ) );

done();
