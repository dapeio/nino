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

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
