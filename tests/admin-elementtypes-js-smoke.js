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

const elementTypes = sandbox.Nino.admin.elementTypes;

// One rendered row, as _storeFields() reads it: every control it looks for,
// returning null for the ones a row of that type never renders (an image row
// has no "Required field" checkbox, a non-string row no maxlength, ...)
function fakeRow( field ) {
	const controls = {
		'.admin-field-key' 						: { value : field.key },
		'select' 											: { value : field.type },
		'.admin-field-locale' 				: { checked : field.locale === true },
		'.admin-field-required' 			: field.type === 'image' ? null : { checked : field.required === true },
		'.admin-field-html' 					: field.type === 'string' ? { checked : field.html === true } : null,
		'.admin-field-maxlength' 			: field.type === 'string' ? { value : field.maxlength ?? '' } : null,
		'.admin-field-select-options' : field.type === 'string' ? { value : ( field.options ?? [] ).join(', ') } : null,
		'.admin-field-width' 					: field.type === 'image' ? { value : field.width ?? '' } : null,
		'.admin-field-height' 				: field.type === 'image' ? { value : field.height ?? '' } : null,
		'.admin-field-suffix' 				: [ 'boolean', 'image', 'element' ].indexOf( field.type ) === -1 ? { value : field.suffix ?? '' } : null,
		'.admin-field-element-type' 	: field.type === 'element' ? { value : field.elementType ?? '' } : null,
		'.admin-field-multiple' 			: field.type === 'element' ? { checked : field.multiple === true } : null,
		'.admin-field-multiple-max' 	: field.type === 'element' ? { value : field.multipleMax ?? '' } : null,
	};
	return { querySelector : function( selector ) { return controls[selector] ?? null } };
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
	{ key : 'title', 	type : 'string', 	locale : true, required : true, html : true, maxlength : '80', suffix : '', elementType : '', options : [ 'a', 'b' ] },
	{ key : 'photo', 	type : 'image', 	width : '800', height : '600' },
	{ key : 'price', 	type : 'double', 	suffix : '\u20ac' },
	{ key : 'author', type : 'element', 	elementType : 'people' },
	{ key : '', 			type : 'string' },
];
mountRows( elementTypes._fields );

const built = elementTypes._buildModel();

check( 'a field without a key never reaches the payload', Object.keys( built ).join() === 'title,photo,price,author' );
check( '_buildModel carries a string field\'s maxlength', built.title.maxlength === '80' );
check( '_buildModel carries a suffix', built.price.suffix === '\u20ac' );
check( '_buildModel carries an element field\'s referenced type', built.author.elementType === 'people' );
check( '_buildModel carries the image dimensions', built.photo.width === '800' && built.photo.height === '600' );
check( '_buildModel carries locale/required/html and the options list', built.title.locale === true && built.title.required === true && built.title.html === true && built.title.options.join() === 'a,b' );

// The actual guard: whatever _storeFields() knows how to read, _buildModel()
// has to forward. Comparing the two key sets catches a control added to the
// editor and forgotten here, which is how maxlength/suffix went missing
const readBack = Object.keys( elementTypes._fields[0] ).filter( function( k ) { return k !== 'key' } );
const forwarded = Object.keys( built.title );
check( 'every key _storeFields() reads is forwarded by _buildModel()', readBack.every( function( k ) { return forwarded.indexOf( k ) !== -1 } ) );


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

check( 'both save paths send it, so it is not silently dropped on create',
	typesSource.split('autoincrement : autoincrement').length === 3 );
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
	/_isNew === false \)\s*\n\s*form\.appendChild\( Nino\.admin\.elementTypes\._renderDangerZone\(\) \);/.test( typesSource ) );


console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
