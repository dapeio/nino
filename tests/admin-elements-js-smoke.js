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
check( 'no image url is built under a /uploads directory', /assetUrl\(\s*'\/uploads\//.test( source ) === false );
check( 'the image preview is built under /images', /assetUrl\(\s*'\/images\/'\+ value\s*\)/.test( source ) === true );

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

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
