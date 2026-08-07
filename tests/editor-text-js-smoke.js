/**
 *	Nino									A compact filesystembased php framework
 *	editor-text-js-smoke.js	DOM-free checks for the Text editor's
 *										multi-locale save planning.
 *
 *	Usage: node tests/editor-text-js-smoke.js
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

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_editor/assets/text.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'text.js' }
);

const text = sandbox.Nino.editor.text;

text._dirtyLocales = [ 'de_DE', 'en_US' ];
text._selectedLocale = 'en_US';
check( 'Save includes every locale edited before the visible one', JSON.stringify( text._saveLocales() ) === JSON.stringify( [ 'de_DE', 'en_US' ] ) );

text._dirtyLocales = [];
check( 'the visible locale carries a global-only save', JSON.stringify( text._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );

text._dirtyLocales = [ 'en_US' ];
check( 'the visible locale is not queued twice', JSON.stringify( text._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
