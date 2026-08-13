/**
 *	Nino									A compact filesystembased php framework
 *	editor-elements-js-smoke.js	DOM-free checks for the Elements editor's
 *											multi-locale save planning.
 *
 *	Usage: node tests/editor-elements-js-smoke.js
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

const source = fs.readFileSync( path.join( __dirname, '../_editor/assets/elements.js' ), 'utf8' );

vm.runInContext(
	source,
	vm.createContext( sandbox ),
	{ filename : 'elements.js' }
);

const elements = sandbox.Nino.editor.elements;

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


console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
