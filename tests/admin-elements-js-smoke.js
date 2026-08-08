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

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/assets/elements.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'elements.js' }
);

const elements = sandbox.Nino.admin.elements;

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

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
