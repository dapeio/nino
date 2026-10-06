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

check( 'the editor has a load path of its own', /function load\(\s*into,\s*value,\s*profile\s*\)/.test( source ) === true );
check( '...which parses the value without running it', source.includes( "new DOMParser().parseFromString" ) === true );
check( '...and empties the field with textContent rather than markup', source.includes( "into.textContent = ''" ) === true );

// The two places a stored value used to be assigned straight to innerHTML
check( 'the editor is filled through it when it is built', source.includes( 'load( content, value, profile )' ) === true );
check( '...and when a value is handed to it later', source.includes( 'load( content, html, profile )' ) === true );

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


// --- The formats -----------------------------------------------------------

console.log( '\nhtml-editor.js - what each format lets the editor hold\n' );

// The table the editor reads a format from, lifted out as it stands: the same
// three \Nino\Html::sanitizeHtml() knows, and the one an unknown name falls back to
const profilesSource = source.slice( source.indexOf( 'const PROFILES = ' ) + 'const PROFILES = '.length );
const PROFILES = new Function( 'return '+ profilesSource.slice( 0, profilesSource.indexOf( '\n\t};' ) + 3 ) )();

check( 'the editor knows inline, lines and blocks, and nothing else', Object.keys( PROFILES ).join() === 'inline,lines,blocks' );
check( 'inline holds no break, lines holds breaks, blocks holds breaks and blocks', PROFILES.inline.breaks === false && PROFILES.inline.blocks === false
	&& PROFILES.lines.breaks === true && PROFILES.lines.blocks === false
	&& PROFILES.blocks.breaks === true && PROFILES.blocks.blocks === true );
check( 'a format that is no profile - plain among them, a plain field is a textarea - is read as inline', /PROFILES\[format\]\s*\?\?\s*PROFILES\.inline/.test( source ) === true );
check( 'create() takes the format as its fifth argument', /create\s*:\s*function\(\s*container,\s*value,\s*maxlength,\s*rows,\s*format\s*\)/.test( source ) === true );
check( 'aria-multiline says so wherever the format has more than a line', source.includes( "'aria-multiline', profile.breaks === true ? 'true' : 'false'" ) === true );
check( 'the list buttons are only built where the format has blocks', /if\( profile\.blocks === true \)\s*\[ 'ul', 'ol' \]\.forEach/.test( source ) === true );

// A value is read into blocks the way \Nino\Html::_sanitizeBlocks() reads it (tests/kernel-smoke.php
// holds the vectors): an item outside a list is a paragraph, and loose content is one walk, so what a
// block in it unwrapped keeps its neighbours apart
check( 'an item outside a list is a paragraph, as on the server', source.includes( "PARAGRAPHS.indexOf( tag ) !== -1 || tag === 'li'" ) === true );
check( 'loose nodes share one boundary state, text among them', source.includes( 'walk( [ node ], loose, profile, state, false )' ) === true && /if\( state\.ended === true \)\s*separate\( loose, part, profile \);/.test( source ) === true );

// The labels of every toolbar button, the two lists included, are fills in both languages
const labelTags = [ 'strong', 'em', 'span', 'code', 'a', 'ul', 'ol', 'linkplaceholder', 'linkok', 'linkcancel', 'formatting', 'content' ];
[ 'en_US', 'de_DE' ].forEach( function( locale ) {
	const words = fs.readFileSync( path.join( __dirname, '../_admin/text/'+ locale+ '.php' ), 'utf8' );
	check( locale+ ': every label the toolbar builds has words, '+ labelTags.join( ', ' ), labelTags.every( function( tag ) { return words.includes( "[[/_admin/htmleditor/label/"+ tag+ "]]'" ) } ) );
} );
check( 'the toolbar builds a label from the tag it is for, which the static fill check cannot see - so it is checked above', source.includes( "getText('/_admin/htmleditor/label/'+ kind)" ) === true && source.includes( "getText('/_admin/htmleditor/label/'+ tag)" ) === true );


// --- Keyboard shortcuts ----------------------------------------------------

console.log( '\nhtml-editor.js - Ctrl/Cmd+B and +I are the two tags, U is not a tag at all\n' );

const shortcutSource = source.slice( source.indexOf( 'function shortcutTag' ) );
const shortcutTag = new Function( 'return '+ shortcutSource.slice( 0, shortcutSource.indexOf( '\n\t}' ) + 3 ) )();
const press = function( key, modifiers ) { return shortcutTag( Object.assign( { key : key, ctrlKey : false, metaKey : false, altKey : false, shiftKey : false }, modifiers ) ) };

check( 'Ctrl+B is bold, Ctrl+I italic - in either case', press( 'b', { ctrlKey : true } ) === 'strong' && press( 'I', { ctrlKey : true } ) === 'em' );
check( '...and Cmd on a Mac the same', press( 'b', { metaKey : true } ) === 'strong' && press( 'i', { metaKey : true } ) === 'em' );
check( 'Ctrl+U is swallowed - the answer is "", not "u": nothing is made of it', press( 'u', { ctrlKey : true } ) === '' );
check( 'a key with neither Ctrl nor Cmd is not a shortcut', press( 'b', {} ) === null && press( 'u', {} ) === null );
check( 'Alt or Shift in the combination leaves it to the browser: AltGr reports Ctrl and Alt together', press( 'b', { ctrlKey : true, altKey : true } ) === null && press( 'i', { ctrlKey : true, shiftKey : true } ) === null && press( 'u', { metaKey : true, altKey : true } ) === null );
check( 'every other key with Ctrl is none of ours - Ctrl+Z, Ctrl+A, Ctrl+K stay the browser\'s', [ 'z', 'a', 'k', 'x', 'Enter' ].every( function( key ) { return press( key, { ctrlKey : true } ) === null } ) );
check( 'the shortcuts reach applyFormat() through the keydown handler, and a swallowed key does nothing else', /const shortcut = shortcutTag\( ev \);\s*if\( shortcut !== null \) \{\s*ev\.preventDefault\(\);\s*if\( shortcut !== '' \)\s*applyFormat\( shortcut \);/.test( source ) === true );
check( 'the two buttons announce their shortcuts', source.includes( "'Control+B Meta+B'" ) === true && source.includes( "'Control+I Meta+I'" ) === true );


// --- What the editor leaves to the browser, and what it does not ----------

console.log( '\nhtml-editor.js - Enter, formats and drops are the editor\'s, not the browser\'s\n' );

check( 'a beforeinput handler cancels the browser\'s own formatting', /addEventListener\( 'beforeinput'/.test( source ) === true && source.includes( "/^format/.test( type ) === true" ) === true );
check( '...and the lists, rules and links the browser can insert on its own', [ 'insertOrderedList', 'insertUnorderedList', 'insertHorizontalRule', 'insertLink' ].every( function( type ) { return source.includes( "type === '"+ type+ "'" ) } ) );
check( 'insertParagraph and insertLineBreak are handled there too - a mobile or IME keyboard reports Enter there and not in keydown', source.includes( "type === 'insertParagraph' || type === 'insertLineBreak'" ) === true );
check( 'a drop is taken as plain text, in the profile\'s own way', source.includes( "type === 'insertFromDrop'" ) === true && /insertFromDrop[\s\S]{0,400}getData\('text\/plain'\)[\s\S]{0,400}insertPlainText\( text \)/.test( source ) === true );
check( 'paste goes through the same function', /addEventListener\( 'paste'[\s\S]{0,300}insertPlainText\( \( ev\.clipboardData/.test( source ) === true );
check( 'Enter is the editor\'s in keydown, and an IME composition is left alone', /ev\.isComposing === true \|\| ev\.keyCode === 229[\s\S]{0,500}ev\.key === 'Enter'[\s\S]{0,100}ev\.preventDefault\(\);\s*enter\( ev\.shiftKey === true \)/.test( source ) === true );
check( 'in the inline format Enter makes nothing: enter() returns before anything is built', /function enter\( lineBreak \) \{\s*if\( profile\.breaks === false \)\s*return;/.test( source ) === true );
check( 'in blocks Backspace and Delete are only the editor\'s at the edge of a block', /profile\.blocks === true && \( ev\.key === 'Backspace' \|\| ev\.key === 'Delete' \) && mergeAtBoundary\( ev\.key \) === true/.test( source ) === true );
check( 'the editor makes no <b>, <i> or <u>, and no style', /createElement\(\s*'(b|i|u)'\s*\)/.test( source ) === false && /\.style\.(?!setProperty)/.test( source ) === false );
check( 'what the browser leaves behind is cleaned on every input: a styled span, a div, a bare run of text', source.includes( "'span[style], span[class]'" ) === true && source.includes( "'div, h1, h2, h3, h4, h5, h6, blockquote'" ) === true );
check( 'removeEmptyTags() stays inline-only, so the <p><br></p> a caret stands on is never taken', /querySelectorAll\( TAGS\.join\(','\) \)/.test( source ) === true );
check( 'a selection that crosses a line or a block is wrapped run by run, and never flattened into one tag', source.includes( 'wrapRun(' ) === true && /node\.tagName === 'BR'\s*\)\s*run = null/.test( source ) === true );
check( 'the value the editor hands back leaves out what only holds a caret: a trailing <br>, an empty paragraph or item', /function serialize\(\)[\s\S]*?trimBreaks\( block \)[\s\S]*?block\.remove\(\)/.test( source ) === true );

// --- The handle ------------------------------------------------------------

console.log( '\nhtml-editor.js - what a form can tell the editor about its field\n' );

check( 'the handle offers focus() and mark() beside the three it had', /return \{\s*getValue[\s\S]*?setValue[\s\S]*?focus : function[\s\S]*?mark : function[\s\S]*?destroy/.test( source ) === true );
check( 'the box the handle acts on is the role=textbox element', /const content = dc\.createElement\('div'\);[\s\S]{0,200}content\.setAttribute\( 'role', 'textbox' \)/.test( source ) === true );

// Lifted out of the file and run as it stands there, against a stand-in for the text box
const start = source.indexOf( 'focus : function() {' );
const end = source.indexOf( 'destroy : function() {' );
const attributes = {};
let focused = 0;
const content = {
	focus : function() { focused++ },
	setAttribute : function( name, value ) { attributes[name] = value },
	removeAttribute : function( name ) { delete attributes[name] },
};
const handle = new Function( 'content', 'return { '+ source.slice( start, end ).trim().replace( /,\s*$/, '' ) + ' }' )( content );

handle.focus();
check( 'focus() puts the caret in the text box', focused === 1 );
handle.mark( { required : true } );
check( 'mark() puts aria-required on the text box', attributes['aria-required'] === 'true' && attributes['aria-invalid'] === undefined );
handle.mark( { invalid : true, describedBy : 'err-1' } );
check( '...and aria-invalid and the explaining element beside it, leaving what it was not told about as it was',
	attributes['aria-required'] === 'true' && attributes['aria-invalid'] === 'true' && attributes['aria-describedby'] === 'err-1' );
handle.mark( { invalid : false, describedBy : '' } );
check( '...false and an empty id let go of them again', attributes['aria-invalid'] === undefined && attributes['aria-describedby'] === undefined && attributes['aria-required'] === 'true' );
handle.mark( { required : false } );
check( '...required: false takes aria-required off', Object.keys( attributes ).length === 0 );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
