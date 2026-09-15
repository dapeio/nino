/**
 *	Nino											A compact filesystembased php framework
 *	admin-html-editor-js-smoke.js	What the workbench's rich-text editor does with a
 *																stored value. The value comes from an element or a
 *																text file, which AGENTS.md lists as untrusted: the
 *																save path sanitises, a record written by hand, by an
 *																import or by a module that writes elements without
 *																the panel does not go through it.
 *
 *																Node has no DOM, so what runs here is the editor's
 *																own href rule, lifted out of the file, and the
 *																shape of the load path - that it rebuilds the value
 *																instead of assigning it. The browser half is in
 *																the patch that brought this file: measured in
 *																Chromium, an onerror handler in a stored value ran
 *																with the editor's session before, and runs no
 *																longer.
 *
 *	Usage: node tests/admin-html-editor-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

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

const source = fs.readFileSync( path.join( __dirname, '../_admin/assets/html-editor.js' ), 'utf8' );


// --- The load path ---------------------------------------------------------

console.log( 'html-editor.js - a stored value is rebuilt, not assigned\n' );

check( 'the editor has a load path of its own', /function load\(\s*into,\s*value\s*\)/.test( source ) === true );
check( '...which parses the value without running it', source.includes( "new DOMParser().parseFromString" ) === true );
check( '...and empties the field with textContent rather than markup', source.includes( "into.textContent = ''" ) === true );

// The two places a stored value used to be assigned straight to innerHTML
check( 'the editor is filled through it when it is built', source.includes( 'load( content, value )' ) === true );
check( '...and when a value is handed to it later', source.includes( 'load( content, html )' ) === true );

// Emptying a container with innerHTML = '' is not assigning a value to it
const assignments = ( source.match( /innerHTML\s*=[^\n]*/g ) || [] ).filter( function( line ) { return /innerHTML\s*=\s*''\s*;/.test( line ) === false } );
check( 'nothing assigns a value to innerHTML any more'+ ( assignments.length === 0 ? '' : ' - found: '+ assignments.join( ' | ' ) ), assignments.length === 0 );


// --- The href rule ---------------------------------------------------------

console.log( '\nhtml-editor.js - which hrefs a rebuilt link keeps\n' );

// Lifted out of the file and run as it stands there: the rule has to be the
// same one \Nino\Html::_safeHref() applies on the way in, or the editor and
// the server disagree about what a link is
const safeHrefSource = source.slice( source.indexOf( 'function safeHref' ) );
const safeHref = new Function( 'return '+ safeHrefSource.slice( 0, safeHrefSource.indexOf( '\n\t}' ) + 3 ) )();

const kept = [ '#anchor', '/contact', '/de/kontakt', 'https://example.com/x', 'http://example.com', 'mailto:post@example.com', 'tel:+49123' ];
const dropped = [ 'javascript:alert(1)', 'JavaScript:alert(1)', 'data:text/html,<script>x</script>', '//evil.example/phish', '/\\evil.example', 'vbscript:x', '', '   ' ];

check( 'every href a link may keep is kept', kept.every( function( href ) { return safeHref( href ) === href } ) );
check( 'every href a link may not keep is dropped', dropped.every( function( href ) { return safeHref( href ) === null } ) );
check( 'an href is trimmed before it is judged', safeHref( '  /contact  ' ) === '/contact' );
check( 'a scheme hiding behind whitespace is still judged', safeHref( '  javascript:alert(1)' ) === null );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
