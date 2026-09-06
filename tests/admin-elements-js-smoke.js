/**
 *	Nino									A compact filesystembased php framework
 *	admin-elements-js-smoke.js	DOM-free checks for the Elements editor's
 *											multi-locale save planning.
 *
 *	Usage: node tests/admin-elements-js-smoke.js
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
	editor : {},
	events : { bindCallback : function() {} },
};

const source = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/admin.js' ), 'utf8' );

const context = vm.createContext( sandbox );

// The shared admin layer, loaded first exactly as the tool's own shell loads
// it: elements.js asks Nino.adminUi whether a model field is a multi element
// reference, so a sandbox without it tests a module the page never runs
vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ),
	context,
	{ filename : 'Nino.admin.js' }
);

vm.runInContext(
	source,
	context,
	{ filename : 'elements.js' }
);

const elements = sandbox.Nino.admin.elements;

// Regression: an image field's preview used to be built as "/uploads/<stored
// filename>", a directory no deployment has - every upload is stored under
// /images (Nino\Images::UPLOAD_DIR). The preview shown right after an upload
// renders the server's own url and always looked right, so the 404 only
// appeared once the form was re-rendered from the stored value. Same bug and
// same guard as _admin's own copy of this module (see
// tests/admin-elements-js-smoke.js)
check( 'no image url is built under a /uploads directory', /(?:asset|public)Url\(\s*'\/uploads\//.test( source ) === false );
check( 'the image preview is built under /images, via the public content prefix', /publicUrl\(\s*'\/images\/'\+ value\s*\)/.test( source ) === true );

elements._localeKeys = [ 'title', 'description' ];
elements._dirtyLocales = [ 'de_DE', 'en_US' ];
elements._selectedLocale = 'en_US';
check( 'Save includes an edited locale visited before the visible one', JSON.stringify( elements._saveLocales() ) === JSON.stringify( [ 'de_DE', 'en_US' ] ) );

elements._dirtyLocales = [ 'de_DE', 'en_US' ];
check( 'the visible locale is not queued twice', JSON.stringify( elements._saveLocales() ) === JSON.stringify( [ 'de_DE', 'en_US' ] ) );

elements._dirtyLocales = [];
check( 'the visible locale is the fallback when only global fields changed', JSON.stringify( elements._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );

elements._localeKeys = [];
check( 'a type without translated fields still needs only one save', JSON.stringify( elements._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );

check( 'an absent string equals its untouched empty control', elements._fieldValuesEqual( { type : 'string' }, null, '' ) === true );
check( 'an absent array equals its untouched empty control', elements._fieldValuesEqual( { type : 'array' }, undefined, [] ) === true );
check( 'an absent boolean equals its untouched false control', elements._fieldValuesEqual( { type : 'boolean' }, null, false ) === true );
check( 'actual text edits are detected', elements._fieldValuesEqual( { type : 'string' }, 'Before', 'After' ) === false );

// An image field never blocks a save, even when its model says required: its
// file is uploaded separately, only once the element exists and has a uri to
// attach it to, so on a new element it is empty by construction. Enforcing it
// would make the element impossible to create at all - the same rule _admin's
// own copy of this module and its Element Types editor apply (see
// tests/admin-elements-js-smoke.js and tests/admin-smoke.php)
sandbox.document.getElementById = function() { return null };
sandbox.Nino.content = { getText : function() { return '' } };

elements._currentType 	= 'service';
elements._globalKeys 		= [ 'photo' ];
elements._localeKeys 		= [ 'title' ];
elements._currentModel	= {
	photo : { type : 'image', required : true },
	title : { type : 'string', required : true },
};

check( 'a required image field is never reported as missing', elements._missingRequiredFields().indexOf( 'photo' ) === -1 );
check( '...while an empty required field of any other type still is', JSON.stringify( elements._missingRequiredFields() ) === JSON.stringify( [ 'title' ] ) );

// --- _loadReferenceOptions(): what an element field's select is built from --
//
// The choices come from the referenced type's own element list, fetched
// before the form renders (a locale switch re-renders the fields, so options
// arriving afterwards would have to be threaded through that too). One
// request per distinct referenced type, never one per field.

const calls = [];
elements._apiCall = function( endpoint, payload, callback ) {
	calls.push( endpoint+ ':'+ JSON.stringify( payload ) );
	callback( 200, { elements : [ { uri : 'ada', label : 'Ada' }, { uri : 'grace', label : 'Grace' } ] } );
};

elements._currentModel = {
	headline 	: { type : 'string' },
	author 		: { type : 'element', elementType : 'people' },
	reviewer 	: { type : 'element', elementType : 'people' },
	source 		: { type : 'element', elementType : 'journals' },
	broken 		: { type : 'element' },
};

let done = false;
elements._loadReferenceOptions( function() { done = true } );

check( 'the continuation runs once every referenced type has answered', done === true );
check( 'one list request per distinct referenced type, not per field', calls.length === 2 );
check( '...and each asks for the type the field declares', calls.join() === 'list:{"type":"people"},list:{"type":"journals"}' );
check( 'a field with no referenced type is skipped rather than requested as ""', calls.some( function( c ) { return c.indexOf( '""' ) !== -1 } ) === false );
check( 'the options land keyed by referenced type', Object.keys( elements._referenceOptions ).sort().join() === 'journals,people' );
check( '...carrying uri and label for the select to render', elements._referenceOptions.people[0].uri === 'ada' && elements._referenceOptions.people[0].label === 'Ada' );

// A model with nothing to resolve must not wait on a request that never fires
calls.length = 0;
elements._currentModel = { headline : { type : 'string' } };
let plainDone = false;
elements._loadReferenceOptions( function() { plainDone = true } );
check( 'a model without references continues immediately, with no request at all', plainDone === true && calls.length === 0 );

// A referenced type deleted since the model was written answers with an error.
// Resolving it to an empty list is what lets _renderField() show the stored
// value as missing - holding the form back would strand the whole element
elements._apiCall = function( endpoint, payload, callback ) { callback( 404, null ) };
elements._currentModel = { author : { type : 'element', elementType : 'gone' } };
let errorDone = false;
elements._loadReferenceOptions( function() { errorDone = true } );
check( 'a referenced type that errors resolves to an empty list instead of hanging', errorDone === true && JSON.stringify( elements._referenceOptions.gone ) === '[]' );

// Stale options are worse than none: an element added to the referenced type
// has to be selectable in the very next form that points at it
elements._apiCall = function( endpoint, payload, callback ) { callback( 200, { elements : [ { uri : 'new', label : 'New' } ] } ) };
elements._loadReferenceOptions( function() {} );
check( 'each form open refills the options rather than reusing the last ones', elements._referenceOptions.gone[0].uri === 'new' );



// --- a type that numbers its own elements ---------------------------------
//
// The element form asks for a uri, unless the type assigns one itself (see
// Elements::AUTOINCREMENT_PAD). Which types those are arrives with the same
// type list every form is built from, and _renderTypes() is the one place that
// records it - every path into a form goes through that list.

elements._numbered = {};
elements._currentType = 'articles';
check( 'a type not in the numbered map asks for a uri', elements._isNumbered() === false );

elements._numbered = { gallery : '00007' };
elements._currentType = 'gallery';
check( 'a numbered type is recognised', elements._isNumbered() === true );
check( 'the next uri is the one the backend reported', elements._nextUri() === '00007' );

// A type whose entry is an empty string is still numbered - the backend simply
// had no number to report. hasOwnProperty, not a truthiness test, is what keeps
// those two apart.
elements._numbered = { gallery : '' };
check( 'a numbered type with no reported number is still numbered', elements._isNumbered() === true );
check( '...and falls back to the first number rather than showing nothing', elements._nextUri() === '00001' );

// Source-level, because both live in _renderForm()/_save()'s dom branches
// which this dom-free sandbox cannot reach
check( 'a numbered insert renders the uri field hidden rather than dropping it',
	/_isNumbered\(\) === true \) \{[\s\S]{0,600}uriInput\.type = 'hidden'/.test( source ) === true );
check( '...and keeps its value empty, which is what asks for a number',
	/_isNumbered\(\) === true \) \{[\s\S]{0,600}uriInput\.value = ''/.test( source ) === true );
check( 'the "a uri is required" guard is skipped for a numbered insert',
	/uri === '' && numberedInsert === false/.test( source ) === true );
check( 'the saved element\'s own .uri is what the form adopts afterwards',
	/response\.element\['\.uri'\]/.test( source ) === true );

// --- an element reference holding a list ----------------------------------
//
// The control stores its ordered list as json in a hidden input, so the form
// reads it back through the same [data-field] path as every other field. Only
// data-multiple separates it from a single reference's select, which carries
// one uri as a plain string.

check( 'a list reference is read back as the array it holds',
	JSON.stringify( elements._readField( { dataset : { type : 'element', multiple : '0' }, value : '["/tag/php","/tag/css"]' } ) ) === '["/tag/php","/tag/css"]' );
check( 'a single reference is still read back as its plain uri',
	elements._readField( { dataset : { type : 'element' }, value : '/tag/php' } ) === '/tag/php' );
// A half-written value must not take the whole save down with it
check( 'unreadable json falls back to an empty list rather than throwing',
	JSON.stringify( elements._readField( { dataset : { type : 'element', multiple : '2' }, value : '[' } ) ) === '[]' );

// Merely visiting a translation must not mark it as edited
check( 'an absent list equals its untouched empty control',
	elements._fieldValuesEqual( { type : 'element', multiple : 0 }, null, [] ) === true );
check( 'a reordered list is an actual edit',
	elements._fieldValuesEqual( { type : 'element', multiple : 0 }, [ '/tag/php', '/tag/css' ], [ '/tag/css', '/tag/php' ] ) === false );
// Without the multiple key this is the old single reference, whose empty
// value is the empty string - normalizing it to [] would call every untouched
// single reference edited
check( 'a single reference still normalizes to an empty string, not a list',
	elements._fieldValuesEqual( { type : 'element' }, null, '' ) === true );

check( 'the form renders the shared control rather than a second copy of it',
	source.includes('Nino.adminUi.elementList(') && source.includes('Nino.adminUi.isMultiElement( field )') );


// --- scoped permissions, as the form reads them ---------------------------
//
// apiTypes() sends what the account may do per type; the form draws itself
// from it so a control the save would refuse is never offered. A type the
// server said nothing about has to stay fully usable - the enforcement is
// server-side, and a missing answer must not lock a working panel down.

elements._currentType = 'services';
elements._rights = {};
check( 'a type with no answer is unrestricted', elements._mayInsert() === true && elements._mayDelete() === true && elements._mayUpdate('title') === true );

elements._rights = { services : { insert : false, delete : false, update : { title : true, price : false } } };
check( 'adding is refused where the server said so', elements._mayInsert() === false );
check( 'deleting is refused where the server said so', elements._mayDelete() === false );
check( 'a field it may write is writable', elements._mayUpdate('title') === true );
check( '...and one it may not is not', elements._mayUpdate('price') === false );
// A field the answer does not mention at all - a model changed since the list
// was loaded - is not a field to lock: the save decides
check( 'a field the answer does not mention stays writable', elements._mayUpdate('subtitle') === true );

elements._currentType = 'other';
check( 'another type is judged by its own answer, not this one', elements._mayInsert() === true );

check( 'the save sends only the fields it may write',
	source.includes( '.filter( function( key ) { return Nino.admin.elements._mayUpdate( key ) } )' ) );
// Locking, not hiding: the value is the context the writable fields around it
// are edited in
check( 'a field it may not write is rendered and then locked',
	source.includes( "label.classList.add( 'admin-field-readonly' )" ) && source.includes( "el.disabled = true" ) );
// _setFormPending( false ) used to re-enable every control in the form, which
// would hand back exactly the fields this account may not write
check( 'a save cycle does not unlock them again',
	source.includes( "el.disabled = pending || el.closest('.admin-field-readonly') !== null" ) );
check( 'Duplicate is offered where adding is, and only there',
	source.includes( "Nino.admin.elements._isNew === false && Nino.admin.elements._mayInsert() === true" ) );


// --- invalidate(): the contract with the Element Types tab ----------------
//
// The schema this module renders every form from belongs to the tab next
// door, and is read once per page load. When a type is saved, created or
// deleted over there, this cache is stale - a deleted type leaves this pane
// on a list of elements that no longer exist. types.js calls
// Nino.admin.elements.invalidate() for exactly that, so the method has to be
// here: the call is silent about a namespace member that does not exist until
// it throws in the browser.
check( 'the module answers the invalidate() its sibling calls',
	typeof elements.invalidate === 'function' );

const typesSource = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/types.js' ), 'utf8' );
check( '...and that sibling is the one calling it',
	typesSource.includes( 'Nino.admin.elements.invalidate()' ) );

sandbox.document.getElementById = function() { return null };

elements._ready 			= true;
elements._loading 		= true;
elements._currentType = 'services';
elements._currentModel = { title : { type : 'string' } };
elements._currentUri 	= 'first';
elements._globalKeys 	= [ 'title' ];
elements._localeKeys 	= [ 'body' ];
elements._globalValues = { title : 'x' };
elements._localeValues = { de_DE : { body : 'y' } };
elements._dirtyLocales = [ 'de_DE' ];
elements._raw 				= { '*' : {} };
elements._referenceOptions = { tag : [] };
elements._pendingUri 	= 'first';
const typesRequestBefore = elements._typesRequest;

elements.invalidate();

// _ready false is what makes the next showCurrent() fetch again rather than
// re-showing a drill-down level built from the deleted type
check( 'invalidate drops the cached schema and the open element', elements._ready === false && elements._currentType === null && elements._currentModel === null && elements._currentUri === null );
check( '...every value the form was holding', JSON.stringify( [ elements._globalKeys, elements._localeKeys, elements._globalValues, elements._localeValues, elements._dirtyLocales, elements._raw ] ) === '[[],[],{},{},[],{}]' );
check( '...and the leftovers that would outlive it', elements._referenceOptions !== undefined && Object.keys( elements._referenceOptions ).length === 0 && elements._pendingUri === undefined );
// A types request in flight would otherwise land after this and mark the
// module ready again, with the list it fetched before the type was deleted
check( 'a request still in flight is invalidated with it', elements._typesRequest === typesRequestBefore + 1 && elements._loading === false );

// Every drill-down level that writes an error into its pane has to show that
// pane too - the list one runs while the type picker is still the visible
// level, so without it a failed elements/list is a click that does nothing
const errorPaths = source.split( '_showError(' ).length - 1;
check( 'every error path shows the pane it writes into', errorPaths === 3
	&& /_showError\( dc\.getElementById\('elements-list'\)[\s\S]{0,120}_showList\(\)/.test( source )
	&& /_showError\( dc\.getElementById\('elements-form'\)[\s\S]{0,120}_showFormView\(\)/.test( source ) );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
