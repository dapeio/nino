/**
 *	Nino									A compact filesystembased php framework
 *	admin-elementtypes-js-smoke.js	DOM-free checks for the _admin Element
 *											Types editor's field-order handling. A field's
 *											position in the model is not cosmetic: it is the
 *											order every element form renders that type's
 *											fields in (see _admin/Nino/Modules/Elements/assets/admin.js's
 *											_globalKeys/_localeKeys, built from the model's
 *											own key order), and it survives all the way into
 *											the type file - tests/admin-smoke.php pins the
 *											server half of that down.
 *
 *											The rows themselves are re-rendered from _fields
 *											on every reorder, so reading the dom back first is
 *											what keeps in-progress edits alive - the bug this
 *											guards against is a moved row quietly reverting
 *											everything typed since the last render.
 *
 *	Usage: node tests/admin-elementtypes-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
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

const sandbox = {
	console : console,
	document : { documentElement : null, body : null },
};
sandbox.window = sandbox;
sandbox.Nino = {
	admin : {},
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/types.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'elementtypes.js' }
);

const elementTypes = sandbox.Nino.admin.types;

// The shell finds a panel's script by its nav uri alone (Nino.admin[uri]);
// a second name is one more spelling to keep in step
check( 'the Types tab\'s script is Nino.admin.types and has no second name',
	typeof elementTypes === 'object' && typeof elementTypes.init === 'function' && sandbox.Nino.admin.elementTypes === undefined );

// One rendered row, as _storeFields() reads it: every control it looks for,
// returning null for the ones a row of that type never renders (an image row
// has no "Required field" checkbox, a non-string row no maxlength, ...)
function fakeRow( field ) {
	const controls = {
		'.admin-field-key' 						: { value : field.key },
		'.admin-field-type' 					: { value : field.type },
		'.admin-field-locale' 				: { checked : field.locale === true },
		'.admin-field-required' 			: field.type === 'image' ? null : { checked : field.required === true },
		'.admin-field-html' 					: field.type === 'string' ? { checked : field.html === true } : null,
		'.admin-field-blocks' 				: field.type === 'string' ? { checked : field.blocks === true } : null,
		'.admin-field-breaks' 				: field.type === 'string' ? { checked : field.breaks === true } : null,
		'.admin-field-maxlength' 			: field.type === 'string' ? { value : field.maxlength ?? '' } : null,
		'.admin-field-inputsize' 			: field.type === 'string' ? { value : field.inputsize ?? '' } : null,
		'.admin-field-select-options' : field.type === 'string' ? { value : ( field.options ?? [] ).join(', ') } : null,
		'.admin-field-width' 					: field.type === 'image' ? { value : field.width ?? '' } : null,
		'.admin-field-height' 				: field.type === 'image' ? { value : field.height ?? '' } : null,
		'.admin-field-alt' 						: field.type === 'image' ? { value : field.alt ?? '' } : null,
		'.admin-field-suffix' 				: [ 'boolean', 'image', 'element' ].indexOf( field.type ) === -1 ? { value : field.suffix ?? '' } : null,
		'.admin-field-element-type' 	: field.type === 'element' ? { value : field.elementType ?? '' } : null,
		'.admin-field-multiple' 			: field.type === 'element' ? { checked : field.multiple === true } : null,
		'.admin-field-multiple-max' 	: field.type === 'element' ? { value : field.multipleMax ?? '' } : null,
	};
	// A row that was loaded from the type carries the name it was saved under
	// (see _renderFieldRow()); a field added in the form has none
	return { dataset : field.originalKey === undefined ? {} : { originalKey : field.originalKey }, querySelector : function( selector ) { return controls[selector] ?? null } };
}

// _move() re-renders through the real dom, which this sandbox has none of -
// the reordering itself is what matters here
elementTypes._renderFields = function() {};

function mountRows( fields ) {
	const rows = fields.map( fakeRow );
	sandbox.document.querySelectorAll = function() { return rows };
}

const model = [
	{ key : 'title', 	type : 'string', locale : true, required : true, maxlength : '80' },
	{ key : 'photo', 	type : 'image', width : '800', height : '600' },
	{ key : 'sort', 	type : 'integer' },
];

elementTypes._fields = model.map( function( f ) { return Object.assign( {}, f ) } );
mountRows( elementTypes._fields );

elementTypes._move( 2, 'up' );
check( 'moving a field up swaps it with the one above', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'title,sort,photo' );

mountRows( elementTypes._fields );
elementTypes._move( 0, 'down' );
check( 'moving a field down swaps it with the one below', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'sort,title,photo' );

// Out of range in either direction is a no-op, not a hole in the list - the
// buttons are disabled at the ends, but the handler must not rely on that
mountRows( elementTypes._fields );
elementTypes._move( 0, 'up' );
check( 'moving the first field up changes nothing', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'sort,title,photo' );

mountRows( elementTypes._fields );
elementTypes._move( 2, 'down' );
check( 'moving the last field down changes nothing', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'sort,title,photo' );
check( '...and never drops a field on the way', elementTypes._fields.length === 3 );

// The point of reading the rows back before swapping: an edit typed after the
// last render lives only in the dom until _storeFields() picks it up
elementTypes._fields = model.map( function( f ) { return Object.assign( {}, f ) } );
const edited = model.map( function( f ) { return Object.assign( {}, f ) } );
edited[0].key = 'headline';
mountRows( edited );

elementTypes._move( 1, 'up' );
check( 'a reorder keeps an edit that was only in the dom yet', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'photo,headline,sort' );
check( '...including the per-type options of the moved row', ( elementTypes._fields[0].width === '800' && elementTypes._fields[0].height === '600' ) === true );
check( 'an image row, which renders no "required" checkbox, reads back as not required', elementTypes._fields[0].required === false );


// --- _buildModel(): every key a row reads back has to reach the payload ----
//
// _storeFields() collects one object per row, _buildModel() copies it into
// the json that actually gets posted - by naming each key. A key present in
// the first and missing from the second is invisible: the field editor offers
// the control, the user fills it in, the server would accept it, and it is
// dropped in between. maxlength and suffix were exactly that.

elementTypes._fields = [
	{ key : 'title', 	type : 'string', 	locale : true, required : true, html : true, maxlength : '80', inputsize : '8', suffix : '', elementType : '', options : [ 'a', 'b' ] },
	{ key : 'photo', 	type : 'image', 	width : '800', height : '600', alt : 'title' },
	{ key : 'price', 	type : 'double', 	suffix : '\u20ac' },
	{ key : 'author', type : 'element', 	elementType : 'people' },
	{ key : '', 			type : 'string' },
];
mountRows( elementTypes._fields );

const built = elementTypes._buildModel();

check( 'a field without a key never reaches the payload', Object.keys( built ).join() === 'title,photo,price,author' );
check( '_buildModel carries a string field\'s maxlength', built.title.maxlength === '80' );
check( '_buildModel carries a string field\'s inputsize', built.title.inputsize === '8' );
check( '_buildModel carries a suffix', built.price.suffix === '\u20ac' );
check( '_buildModel carries an element field\'s referenced type', built.author.elementType === 'people' );
check( '_buildModel carries the image dimensions', built.photo.width === '800' && built.photo.height === '600' );
check( '_buildModel carries the field an image\'s alt text is written in, and nothing for a row that has none', built.photo.alt === 'title' && ( built.price.alt ?? '' ) === '' );
check( '_buildModel carries locale/required/html and the options list', built.title.locale === true && built.title.required === true && built.title.html === true && built.title.options.join() === 'a,b' );

// The actual guard: whatever _storeFields() knows how to read, _buildModel()
// has to forward. Comparing the two key sets catches a control added to the
// editor and forgotten here, which is how maxlength/suffix went missing
// ('originalKey' is what a rename is made from, not a field of the model)
const readBack = Object.keys( elementTypes._fields[0] ).filter( function( k ) { return k !== 'key' && k !== 'originalKey' } );
const forwarded = Object.keys( built.title );
check( 'every key _storeFields() reads is forwarded by _buildModel()', readBack.every( function( k ) { return forwarded.indexOf( k ) !== -1 } ) );


// --- paragraphs and line breaks --------------------------------------------
//
// A rich text field may allow paragraphs and lists, a plain one may keep its
// line breaks; both are read back from their checkboxes and reach the payload.

elementTypes._fields = [
	{ key : 'body', type : 'string', html : true, blocks : true },
	{ key : 'note', type : 'string', breaks : true },
	{ key : 'plain', type : 'string' },
	{ key : 'count', type : 'integer' },
];
mountRows( elementTypes._fields );
const formatBuilt = elementTypes._buildModel();
check( 'a rich field\'s blocks and a plain field\'s breaks are read back and sent', formatBuilt.body.blocks === true && formatBuilt.body.breaks === false && formatBuilt.note.breaks === true && formatBuilt.note.blocks === false );
check( 'a row without either control reads as neither', formatBuilt.count.blocks === false && formatBuilt.count.breaks === false && formatBuilt.plain.blocks === false );


// --- renaming a field --------------------------------------------------------
//
// A row loaded from the type remembers the name it was saved under, wherever it
// is moved to, and a save carries { old: new } for every one whose key changed.
// Types::apiSave() moves the stored values: here it is only said which.

elementTypes._fields = [
	{ key : 'title', originalKey : 'title', type : 'string' },
	{ key : 'price', originalKey : 'cost', type : 'double' },
	{ key : 'photo', originalKey : 'picture', type : 'image' },
	{ key : ' padded ', originalKey : 'padded', type : 'string' },
	{ key : 'brandnew', type : 'string' },
	{ key : '', originalKey : 'emptied', type : 'string' },
	{ key : 'taken out', originalKey : 'taken out', type : 'string' },
];
mountRows( elementTypes._fields );
check( 'only a saved field whose key changed is a rename - not an unchanged one, a new one or one whose key was emptied', JSON.stringify( elementTypes._renames() ) === '{"cost":"price","picture":"photo"}' );
check( '...and the key is read trimmed, like the server trims it', elementTypes._renames().padded === undefined );
check( 'the name a row was saved under survives a move, which re-renders from the stored fields', ( function() {
	mountRows( elementTypes._fields );
	elementTypes._move( 1, 'up' );
	return elementTypes._fields[0].originalKey === 'cost' && elementTypes._fields[0].key === 'price' && elementTypes._fields[1].originalKey === 'title';
} )() );
check( '...and a field added in the form has none', elementTypes._fields.find( function( f ) { return f.key === 'brandnew' } ).originalKey === undefined );

// The alt link of an image names its field the way it was saved, so a rename of that
// field in the form does not unlink it (Types::apiSave() reads it through the renames)
{
	const altSource = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/types.js' ), 'utf8' );
	check( 'the alt choice is kept by the name a field was saved under, not by the key it has been given since',
		altSource.includes( 'const name = other.originalKey ?? other.key;' ) && altSource.includes( 'opt.selected = ( name === current );' ) );
}

// types.js builds '/_admin/types/reference/<kind>' at runtime - the three kinds Types::_renameReferences()
// reports - which the static fill check of tests/admin-system-smoke.php cannot see
[ 'en_US', 'de_DE' ].forEach( function( locale ) {
	const words = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/text/'+ locale+ '.php' ), 'utf8' );
	check( locale+ ': every kind of reference a rename leaves behind has words', [ 'template', 'role', 'label' ].every( kind => words.includes( "[[/_admin/types/reference/"+ kind+ "]]'" ) ) );
} );

// The words, the questions and the payload of a save - against the stubs this
// sandbox has, which the sections below replace with their own
{
	const nodes = { 'admin-form-msg' : { textContent : '' }, 'admin-form-title' : { value : 'Things' }, 'admin-form-uri' : { value : 'things' } };
	sandbox.document.getElementById = id => nodes[id] ?? null;
	sandbox.document.querySelector = () => ( { checked : false } );
	sandbox.Nino.content = { getText : key => key === '/_admin/types/confirm/rename' ? 'Rename %s?' : ( key === '/_admin/types/notice/references' ? 'Still using the old name: %s' : ( key === '/_admin/types/reference/template' ? 'template %s (%s)' : key ) ) };
	sandbox.Nino.adminUi = { api : { errorText : status => 'error '+ status }, format : ( text, ...params ) => { let at = 0; return String( text ).replace( /%[sdn]/g, token => at < params.length ? String( params[at++] ) : token ) } };
	const questions = [];
	let answer = true;
	sandbox.confirm = question => { questions.push( question ); return answer };
	const sent = [];
	elementTypes._apiCall = ( endpoint, payload, callback ) => sent.push( { endpoint : endpoint, payload : payload, callback : callback } );
	elementTypes._isNew = false;
	elementTypes._currentUri = 'things';
	elementTypes._fields = [ { key : 'headline', originalKey : 'title', type : 'string' }, { key : 'sort', originalKey : 'sort', type : 'integer' } ];
	mountRows( elementTypes._fields );

	let outcome = null;
	answer = false;
	elementTypes._save( ok => { outcome = ok } );
	check( 'a save with a rename asks first, naming it, and a No sends nothing and says so', questions.length === 1 && questions[0] === 'Rename title \u2192 headline?' && sent.length === 0 && outcome === false );
	answer = true;
	questions.length = 0;
	elementTypes._save();
	check( '...a Yes sends it, with the renames beside the model', sent.length === 1 && sent[0].endpoint === 'save' && JSON.stringify( sent[0].payload.renames ) === '{"title":"headline"}' );

	elementTypes._init = elementTypes.init;
	elementTypes.init = () => {};
	elementTypes._invalidateElements = () => {};
	sent[0].callback( 200, { uri : 'things', references : [ { kind : 'template', name : 'page-things.tpl', field : 'title' } ] } );
	check( 'what the rename could not move is said once, above the list the save goes back to', elementTypes._notice === 'Still using the old name: template page-things.tpl (title)' );
	elementTypes._notice = '';

	elementTypes._fields = [ { key : 'headline', originalKey : 'headline', type : 'string' } ];
	mountRows( elementTypes._fields );
	sent.length = 0;
	questions.length = 0;
	elementTypes._save();
	check( 'a save without a rename asks nothing, and carries an empty set', questions.length === 0 && sent.length === 1 && JSON.stringify( sent[0].payload.renames ) === '{}' );
	sent[0].callback( 200, { uri : 'things', references : [] } );
	check( '...and leaves nothing to say after it', elementTypes._notice === '' );

	elementTypes._isNew = true;
	elementTypes._fields = [ { key : 'headline', type : 'string' } ];
	mountRows( elementTypes._fields );
	sent.length = 0;
	elementTypes._save();
	check( 'creating a type posts no renames', sent.length === 1 && sent[0].endpoint === 'create' && 'renames' in sent[0].payload === false );
	elementTypes._isNew = false;
	elementTypes.init = elementTypes._init;
	delete sandbox.confirm;
}


// --- duplicating a type ------------------------------------------------------
//
// The copy is made here, from the form as it stands, and created like any new
// type - what the server keeps of it is what it keeps of any model, so the
// defaults and callbacks of the original stay with the original. The form asks
// for the new uri and a title, and opens the copy once it exists.

{
	const duplicateSource = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/types.js' ), 'utf8' );
	const nodes = { 'admin-form-duplicate-uri' : { value : ' things-copy ' }, 'admin-form-duplicate-title' : { value : ' Things, copied ' }, 'admin-form-duplicate-msg' : { textContent : '' }, 'admin-form-title' : { value : ' Things ' } };
	sandbox.document.getElementById = id => nodes[id] ?? null;
	let numbered = true;
	sandbox.document.querySelector = () => ( { checked : numbered } );
	const sent = [];
	elementTypes._apiCall = ( endpoint, payload, callback ) => sent.push( { endpoint : endpoint, payload : payload, callback : callback } );
	const calls = [];
	const init = elementTypes.init;
	elementTypes.init = () => calls.push( 'init' );
	elementTypes._invalidateElements = () => calls.push( 'invalidate' );
	elementTypes._currentUri = 'things';
	elementTypes._fields = [
		{ key : 'headline', originalKey : 'title', type : 'string', locale : true },
		{ key : 'photo', originalKey : 'photo', type : 'image', width : '10', height : '10', alt : 'title' },
		{ key : 'sort', type : 'integer' },
	];
	mountRows( elementTypes._fields );

	elementTypes._duplicate();
	check( 'the copy is created under the new uri, as any new type is', sent.length === 1 && sent[0].endpoint === 'create' && sent[0].payload.uri === 'things-copy' && sent[0].payload.title === 'Things, copied' && sent[0].payload.autoincrement === true );
	check( '...from the form as it stands: every field, a rename already made, and an alt link made to the name its field has now', Object.keys( sent[0].payload.model ).join() === 'headline,photo,sort' && sent[0].payload.model.headline.locale === true && sent[0].payload.model.photo.alt === 'headline' );
	check( '...and carries nothing the form does not hold: no defaults, no callbacks, no renames', 'renames' in sent[0].payload === false && JSON.stringify( Object.keys( sent[0].payload ).sort() ) === '["autoincrement","model","title","uri"]' );
	sent[0].callback( 409, { error : 'x', code : 'types_exists' } );
	check( 'a refusal is said at the form, and the form stays', nodes['admin-form-duplicate-msg'].textContent === 'error 409' && calls.length === 0 );
	sent[0].callback( 200, { uri : 'things-copy' } );
	check( 'a success reloads the list, drops the Elements form and opens the copy once the list is there', calls.join() === 'init,invalidate' && elementTypes._openUri === 'things-copy' );
	elementTypes._openUri = null;

	const asked = [];
	sandbox.Nino.admin.dirty = { guard : ( names, proceed ) => asked.push( { names : names, proceed : proceed } ) };
	sent.length = 0;
	elementTypes._currentUri = 'things';
	elementTypes._duplicate();
	check( 'unsaved input - this form\'s and the open element\'s - is asked about before the copy opens', asked.length === 1 && JSON.stringify( asked[0].names ) === '["types","elements"]' && sent.length === 0 );
	asked[0].proceed();
	check( '...and the answer lets it go', sent.length === 1 );
	delete sandbox.Nino.admin.dirty;

	nodes['admin-form-duplicate-title'].value = '';
	numbered = false;
	sent.length = 0;
	elementTypes._duplicate();
	check( 'without a title the copy takes the one in the form, and a type that does not number is not numbered', sent.length === 1 && sent[0].payload.title === 'Things' && sent[0].payload.autoincrement === false );

	elementTypes._currentUri = null;
	sent.length = 0;
	elementTypes._duplicate();
	check( 'a form with no saved type open duplicates nothing', sent.length === 0 );
	elementTypes.init = init;

	check( 'the form offers it for a saved type only, above the danger zone', /_isNew === false \) \{\s*form\.appendChild\( Nino\.admin\.types\._renderDuplicate\(\) \);\s*form\.appendChild\( Nino\.admin\.types\._renderDangerZone\(\) \);/.test( duplicateSource ) );
	check( 'what is typed into it is no edit of the type', duplicateSource.includes( "uriInput.dataset.dirty = 'ignore'" ) && duplicateSource.includes( "titleInput.dataset.dirty = 'ignore'" ) );
	const open = duplicateSource.slice( duplicateSource.indexOf( 'const open = Nino.admin.types._openUri' ) );
	check( 'the copy is opened by the list that loads after it', open.startsWith( 'const open = Nino.admin.types._openUri;' ) && /_openForm\( open \)/.test( open.slice( 0, 300 ) ) );
}

delete sandbox.Nino.content;
delete sandbox.Nino.adminUi;


// --- an element reference that holds a list -------------------------------
//
// The model asks two things - whether the reference is a list at all, and
// where it stops - so the editor offers two controls and Admin.php's
// cleanModel() folds them into the single int the model carries.

elementTypes._fields = [
	{ key : 'tags', 	type : 'element', elementType : 'tag', multiple : true, multipleMax : '3' },
	{ key : 'author', type : 'element', elementType : 'people' },
];
mountRows( elementTypes._fields );

const multiBuilt = elementTypes._buildModel();
check( 'both halves of the list setting reach the payload', multiBuilt.tags.multiple === true && multiBuilt.tags.multipleMax === '3' );
// Without this the server cannot tell "not a list" from "a list nobody capped"
check( 'a reference left single says so rather than saying nothing', multiBuilt.author.multiple === false );


// --- the numbering option -------------------------------------------------
//
// Whether a type names its elements or numbers them is a property of the type,
// so it is set here rather than per element. Source-level: _renderForm() is a
// dom branch this sandbox cannot reach.
const typesSource = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/types.js' ), 'utf8' );

check( 'the type editor offers the numbering option through the shared switch',
	typesSource.includes('Nino.adminUi.switchField(') && typesSource.includes("key \t\t\t: 'autoincrement'") );
// The element branch of _renderFieldRow() is a dom branch this sandbox cannot
// reach, so its two controls are pinned at source level the same way
// The image row offers the string fields that can hold an alt text - per language, plain - and is a dom branch like the one above
check( 'the image row offers a select of the per-language plain string fields as its alt text, refreshed when it takes the focus',
	typesSource.includes("className = 'admin-field-alt'") && typesSource.includes("addEventListener( 'focus'") && /other\.locale !== true \|\| other\.html === true/.test( typesSource ) );
check( 'the element branch offers a "several elements" checkbox and a cap',
	typesSource.includes("className = 'admin-field-multiple'") && typesSource.includes("className = 'admin-field-multiple-max'") );
// The words are a fill, and a panel's fills travel with its module (see
// text() in the panel contract), so the wording is checked where it lives -
// _admin/Nino/Modules/Elements/text/ - and the script is checked for asking
// for it
const enFills = require('fs').readFileSync( require('path').join( __dirname, '../_admin/Nino/Modules/Elements/text/en_US.php' ), 'utf8' );
check( '...whose placeholder says what 0 means instead of leaving it to be guessed',
	typesSource.includes( "maxInput.placeholder = Nino.content.getText('/_admin/types/placeholder/max')" )
	&& /placeholder\/max\]\]'\s*=> '[^']*0 = unlimited/.test( enFills ) );
// A cap of 0 is falsy, so a truthiness test would render the box unchecked on
// exactly the unlimited lists it is meant to show as on
check( 'the checkbox reads the stored int rather than its truthiness',
	typesSource.includes("typeof field.multiple === 'number'") );

check( 'every way a type is written sends it - create, save and the copy a duplication creates - so it is not silently dropped',
	typesSource.split('autoincrement : autoincrement').length === 4 );
check( 'the form is told the current setting rather than defaulting to off',
	/_renderForm\( response\.title, response\.autoincrement === true/.test( typesSource ) === true );
check( 'and says that existing elements keep their uris',
	typesSource.includes( "Nino.content.getText('/_admin/types/hint/numbering-existing')" )
	&& enFills.includes( 'Existing elements keep the uris they have' ) );


// --- deleting a type ------------------------------------------------------
//
// The one control in this module that destroys content: the type file, every
// element in it and the images those elements hold. It is offered because
// doing it by hand is the same removal with none of the checks - but it is
// only ever reached by typing the type's own uri, and _delete() re-checks
// that before it posts, so a caller that bypasses the disabled button still
// gets nowhere.

sandbox.Nino.content = { getText : function( key ) { return key } };

const deleteControls = {};
sandbox.document.getElementById = function( id ) { return deleteControls[id] ?? null };

let posted = null;
elementTypes._apiCall = function( endpoint, payload ) { posted = { endpoint : endpoint, payload : payload } };

elementTypes._currentUri = 'services';
deleteControls['admin-form-delete-confirm'] = { value : 'servces' };
deleteControls['admin-form-delete-msg'] 		= { textContent : '' };

elementTypes._delete();
check( 'a mistyped confirmation posts nothing at all', posted === null );

deleteControls['admin-form-delete-confirm'].value = ' services ';
elementTypes._delete();
check( 'the typed uri is what unlocks the request', posted !== null && posted.endpoint === 'delete' );
check( '...and travels with it, so the server can re-check it', posted !== null && posted.payload.uri === 'services' && posted.payload.confirm === 'services' );

// Nothing to post against once the uri is gone - the form is showing a type
// that no longer exists, and the list is where it goes back to
posted = null;
elementTypes._currentUri = null;
elementTypes._delete();
check( 'a form with no type open cannot delete anything', posted === null );

check( 'the delete button starts disabled and is unlocked by the input',
	typesSource.includes( 'delBtn.disabled = true' )
	&& /delBtn\.disabled = \( confirmInput\.value\.trim\(\) !== uri \)/.test( typesSource ) );
// A type another type's element field points at is not offered the control at
// all: the server refuses it (see Types::apiDelete()), so offering a button
// that can only fail would be a lie about what is possible
check( 'a referenced type is told why instead of being offered the button',
	typesSource.includes( "Nino.content.getText('/_admin/types/hint/delete-referenced')" )
	&& /_referencedBy\.length > 0 \)\s*\{[\s\S]*?return wrap;/.test( typesSource ) );
check( 'the cost is named before the click, not after',
	typesSource.includes( "Nino.content.getText('/_admin/types/hint/delete')" )
	&& /hint\/delete\]\]'\s*=> '[^']*no undo/.test( enFills ) );
// Only on a saved type: a form that has never been saved has no file to remove
check( 'the danger zone is not rendered while creating a new type',
	/_isNew === false \) \{[\s\S]*?form\.appendChild\( Nino\.admin\.types\._renderDangerZone\(\) \);\s*\}/.test( typesSource ) );


// --- the unit input is offered for the types the server names --------------
//
// Which field types take a unit is the server's rule (Types.php's
// SUFFIX_TYPES): it keeps a suffix for those alone and hands the list to the
// editor beside the field types. The editor used to state the rule itself,
// one type short, and offered a unit on an element reference that the save
// then dropped in silence. So the input follows the list, and nothing else.
// A recording dom of plain objects, since the sandbox above has none

{
	const make = tag => ( { tagName : tag, children : [], className : '', dataset : {}, classList : { add(){}, remove(){}, toggle(){} },
		appendChild( c ) { this.children.push( c ); return c }, addEventListener( type, fn ) { ( this.listeners[type] = this.listeners[type] ?? [] ).push( fn ) }, setAttribute(){},
		listeners : {}, fire( type ) { ( this.listeners[type] ?? [] ).forEach( fn => fn( { type : type } ) ) }, dispatchEvent( ev ) { this.fire( ev.type ) }, focus() { this.focused = true },
		// a select answers with its selected option, as the real one does
		get value() { if( tag !== 'select' ) return this._value; const chosen = this.children.find( o => o.selected === true ) || this.children[0]; return chosen ? chosen.value : '' },
		set value( v ) { this._value = v },
		get innerHTML() { return '' }, set innerHTML( v ) { this.children = [] } } );
	const find = ( node, test, out = [] ) => { ( node.children || [] ).forEach( c => { if( test( c ) ) out.push( c ); find( c, test, out ) } ); return out };
	sandbox.document.createElement = make;
	sandbox.document.createTextNode = text => ( { nodeType : 3, textContent : text, children : [] } );
	sandbox.Nino.content = { getText : key => key };
	elementTypes._fields = [ { key : 'a', type : 'double' } ];
	elementTypes._types = [];
	elementTypes._currentUri = null;
	elementTypes._fieldTypes = [ 'string', 'integer', 'double', 'boolean', 'array', 'date', 'datetime', 'image', 'element' ];
	elementTypes._suffixTypes = [ 'string', 'integer', 'double', 'array', 'date', 'datetime' ];
	const unitInputs = field => find( elementTypes._renderFieldRow( field, 0 ), n => n.className === 'admin-field-suffix' ).length;
	check( 'a row of a type the server names offers the unit input', unitInputs( { key : 'price', type : 'double' } ) === 1 && unitInputs( { key : 'title', type : 'string' } ) === 1 );
	check( '...and a row of a type it does not name - an element reference, an image, a boolean - does not', unitInputs( { key : 'ref', type : 'element', elementType : 'people' } ) === 0 && unitInputs( { key : 'photo', type : 'image' } ) === 0 && unitInputs( { key : 'live', type : 'boolean' } ) === 0 );
	elementTypes._suffixTypes = [];
	check( 'before the server has answered, no row offers one', unitInputs( { key : 'price', type : 'double' } ) === 0 );

	// The key of a field: a choice among the words fields most often are - the
	// vocabulary's, in the language of the interface, the slug as the value - and
	// an own key, which shows the text field it always was. The text field stays
	// what is read back and saved
	const WORDS = [ 'title', 'subtitle', 'text', 'description', 'image', 'alt', 'caption', 'link', 'label', 'name', 'email', 'phone', 'address', 'date', 'author', 'price', 'icon' ];
	const choice = field => find( elementTypes._renderFieldRow( field, 0 ), n => n.className === 'admin-field-key-select' )[0];
	const keyField = field => find( elementTypes._renderFieldRow( field, 0 ), n => n.className === 'admin-field-key' )[0];
	check( 'the choice offers the 17 words of the vocabulary that name a field, and "own key"', JSON.stringify( elementTypes.KEY_WORDS ) === JSON.stringify( WORDS )
		&& choice( { key : 'a', type : 'string' } ).children.map( o => o.value ).join() === WORDS.concat( [ '' ] ).join() );
	check( '...each named by its word', choice( { key : 'a', type : 'string' } ).children.slice( 0, 17 ).every( o => o.textContent === '/_admin/common/word/'+ o.value ) );
	check( '...and a key that is one of them is chosen, its text field hidden', choice( { key : 'title', type : 'string' } ).value === 'title' && keyField( { key : 'title', type : 'string' } ).hidden === true );
	check( 'any other key shows "own key" with its text field filled', choice( { key : 'price_default', type : 'double' } ).value === '' && keyField( { key : 'price_default', type : 'double' } ).hidden === false && keyField( { key : 'price_default', type : 'double' } ).value === 'price_default' );
	check( 'a field that is new starts as an own key with an empty text field', choice( { type : 'string' } ).value === '' && keyField( { type : 'string' } ).hidden === false && keyField( { type : 'string' } ).value === '' );
	// the choice at work: a word fills the text field and hides it, "own key" shows it again
	sandbox.Event = function( type ) { this.type = type };
	const row = elementTypes._renderFieldRow( { key : 'price_default', type : 'double' }, 0 );
	const select = find( row, n => n.className === 'admin-field-key-select' )[0];
	const input = find( row, n => n.className === 'admin-field-key' )[0];
	select.children.forEach( o => { o.selected = ( o.value === 'author' ) } );
	select.fire('change');
	check( 'picking a word puts it into the text field, which is what is saved, and hides the field', input.value === 'author' && input.hidden === true );
	select.children.forEach( o => { o.selected = ( o.value === '' ) } );
	select.fire('change');
	check( '...and "own key" shows the field again, with the word still in it, and puts the cursor there', input.hidden === false && input.value === 'author' && input.focused === true );
	check( '_storeFields() reads the type from its own select, not from the first select of the row', typesSource.includes( "row.querySelector('.admin-field-type').value" ) && typesSource.includes( "row.querySelector('select').value" ) === false );
}


// --- unsaved input: the element form next door --------------------------------
//
// Every write of a type ends in _invalidateElements(), which drops the Elements
// form and what is typed into it. So a create, a save and a delete ask the shell
// about that form first - and, saved from the shell's own question, ask nothing
// a second time.
{
	const msg = { textContent : '' };
	const confirm = { value : 'people' };
	const nodes = { 'admin-form-msg' : msg, 'admin-form-delete-msg' : msg, 'admin-form-title' : { value : 'People' }, 'admin-form-uri' : { value : 'people' }, 'admin-form-delete-confirm' : confirm };
	sandbox.document.getElementById = id => nodes[id] ?? null;
	sandbox.document.querySelector = () => ( { checked : false } );
	sandbox.Nino.adminUi = { api : { errorText : status => 'error '+ status } };
	const calls = [];
	elementTypes._buildModel = () => ( {} );
	elementTypes._apiCall = ( endpoint, payload, callback ) => calls.push( { endpoint : endpoint, callback : callback } );
	elementTypes.init = () => calls.push( { endpoint : 'init' } );
	elementTypes._invalidateElements = () => calls.push( { endpoint : 'invalidate' } );
	elementTypes._showList = () => {};
	elementTypes._currentUri = 'people';
	elementTypes._isNew = false;

	// no registry: nothing is asked
	elementTypes._save();
	check( 'a shell without the registry asks nothing', calls.length === 1 && calls[0].endpoint === 'save' );

	const asked = [];
	sandbox.Nino.admin.dirty = { guard : ( names, proceed, onCancel ) => asked.push( { names : names, proceed : proceed, onCancel : onCancel } ), snapshot(){}, refresh(){} };
	calls.length = 0;
	let outcome = null;
	elementTypes._save( ok => { outcome = ok } );
	check( 'saving a type asks about the open element form first, and sends nothing before the answer', asked.length === 1 && JSON.stringify( asked[0].names ) === '["elements"]' && calls.length === 0 );
	asked[0].onCancel();
	check( '...a Cancel reports that the save did not happen', outcome === false && calls.length === 0 );
	asked[0].proceed();
	check( '...an answer sends it', calls.length === 1 && calls[0].endpoint === 'save' );
	calls[0].callback( 200, { uri : 'people' } );
	check( '...and the element form is dropped only after the type was written, which reports true', calls.map( c => c.endpoint ).join() === 'save,init,invalidate' && outcome === true );

	asked.length = 0;
	calls.length = 0;
	elementTypes._isNew = true;
	elementTypes._save();
	asked[0].proceed();
	check( 'creating a type asks the same', calls.length === 1 && calls[0].endpoint === 'create' );
	elementTypes._isNew = false;

	asked.length = 0;
	calls.length = 0;
	elementTypes._save( () => {}, true );
	check( 'a save the shell already asked about does not ask again', asked.length === 0 && calls.length === 1 );

	asked.length = 0;
	calls.length = 0;
	elementTypes._delete();
	check( 'deleting a type asks first, too', asked.length === 1 && calls.length === 0 );
	asked[0].proceed();
	check( '...and sends the delete once answered', calls.length === 1 && calls[0].endpoint === 'delete' );

	confirm.value = 'wrong';
	asked.length = 0;
	elementTypes._delete();
	check( 'a delete whose confirmation does not match the uri asks nothing and does nothing', asked.length === 0 );
	confirm.value = 'people';

	const typesSource2 = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/types.js' ), 'utf8' );
	check( 'the type form is watched by the shell under its name, behind a check that the shell has the registry',
		/if\( typeof Nino\.admin\.dirty === 'object' \)\s*Nino\.admin\.dirty\.watchForm\( 'types', function\(\) \{ return dc\.getElementById\('types-form'\) \}/.test( typesSource2 ) === true );
	check( 'the uri typed to confirm a deletion is no edit of the type', typesSource2.includes( "confirmInput.dataset.dirty = 'ignore'" ) );
	delete sandbox.Nino.admin.dirty;
}

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
