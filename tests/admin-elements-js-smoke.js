/**
 *	Nino									A compact filesystembased php framework
 *	admin-elements-js-smoke.js	DOM-free checks for the _admin Elements
 *											editor's multi-locale save planning - the same
 *											contract tests/editor-elements-js-smoke.js pins
 *											down for _editor's own copy. Both modules carry
 *											this logic independently (see the class docblock
 *											in _admin/assets/elements.js for why), so it needs
 *											guarding on both sides: the bug it prevents -
 *											submitting only the last visible locale and
 *											silently dropping every translation edited before
 *											it - is exactly what was fixed once already.
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
	admin : {},
	events : { bindCallback : function() {} },
};

const source = fs.readFileSync( path.join( __dirname, '../_admin/assets/elements.js' ), 'utf8' );

vm.runInContext(
	source,
	vm.createContext( sandbox ),
	{ filename : 'elements.js' }
);

const elements = sandbox.Nino.admin.elements;

// Regression: an image field's preview used to be built as "/uploads/<stored
// filename>", a directory no deployment has - every upload is stored under
// /images (Nino\Images::UPLOAD_DIR). The preview shown right after an upload
// renders the server's own url and always looked right, so the 404 only
// appeared once the form was re-rendered from the stored value. Checked
// against the source: the url is only ever built inside _renderField()'s dom
// branch, which this dom-free sandbox cannot reach
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
check( 'an absent number equals its untouched zero control', elements._fieldValuesEqual( { type : 'integer' }, null, 0 ) === true );
check( 'actual text edits are detected', elements._fieldValuesEqual( { type : 'string' }, 'Before', 'After' ) === false );

// _admin labels a field by its raw model key on purpose - _editor looks up a
// translated, editor-facing name instead (see the class docblock)
check( 'a field is labeled by its raw model key', elements._fieldLabel( 'someKey' ) === 'someKey' );

// The raw-bucket view is _admin-only, and renders nothing at all rather than
// an empty block when the element has no stored buckets (a brand-new one)
elements._raw = {};
check( 'the raw view renders nothing for an element with no stored buckets', elements._renderRaw() === null );

// invalidate() is this module's contract with _admin's Element Types module
// (see elementtypes.js's _invalidateElements()): the schema every form here
// is built from is read exactly once per page load, so a type saved next
// door has to drop that cache or the change stays invisible until a full
// reload. Only the state half is exercised here - the DOM half returns early
// against this sandbox's stub, which is also what a page without the panel does
sandbox.document.getElementById = function() { return null };

// An image field never blocks a save, even when its model says required: its
// file is uploaded separately, only once the element exists and has a uri to
// attach it to, so on a new element it is empty by construction. Enforcing it
// would make the element impossible to create at all - the Element Types
// editor no longer writes the flag onto an image field (see
// tests/admin-smoke.php), and a model that still carries one is ignored here
elements._globalKeys 		= [ 'photo' ];
elements._localeKeys 		= [ 'title' ];
elements._currentModel	= {
	photo : { type : 'image', required : true },
	title : { type : 'string', required : true },
};

check( 'a required image field is never reported as missing', elements._missingRequiredFields().indexOf( 'photo' ) === -1 );
check( '...while an empty required field of any other type still is', JSON.stringify( elements._missingRequiredFields() ) === JSON.stringify( [ 'title' ] ) );

const beforeTypes = elements._typesRequest;
const beforeList 	= elements._listRequest;
const beforeForm 	= elements._formRequest;

elements._ready 			= true;
elements._currentType = 'service';
elements._currentModel = { title : { locale : true } };
elements._globalKeys 	= [ 'sort' ];
elements._localeKeys 	= [ 'title' ];
elements._currentUri 	= 'consulting';
elements._localeValues = { en_US : { title : 'Consulting' } };
elements._dirtyLocales = [ 'en_US' ];

elements.invalidate();

check( 'invalidate drops the ready flag, so the next showCurrent() fetches again', elements._ready === false );
check( 'invalidate forgets the type it was drilled into', elements._currentType === null && elements._currentUri === null );
check( 'invalidate forgets the model it rendered fields from', elements._currentModel === null && elements._globalKeys.length === 0 && elements._localeKeys.length === 0 );
check( 'invalidate drops edits belonging to the old model rather than carrying them over', JSON.stringify( elements._localeValues ) === '{}' && elements._dirtyLocales.length === 0 );
check( 'invalidate bumps every request counter, so responses in flight are dropped on arrival',
	elements._typesRequest === beforeTypes + 1 && elements._listRequest === beforeList + 1 && elements._formRequest === beforeForm + 1 );

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

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
