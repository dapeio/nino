/**
 *	Nino											A compact filesystembased php framework
 *	templates-js-smoke.js			Dependency-free smoke test for the template builder's
 *														two dom-free frontend modules: the block layer
 *														(_templates/assets/blocks.js - which library block a
 *														node is, and how a block's settings map onto its
 *														css classes) and the tree layer
 *														(_templates/assets/tree.js - insert, remove, move,
 *														duplicate).
 *
 *														Those two hold the parts of the builder that can
 *														silently corrupt a template: a setting written back
 *														differently than it was read reshuffles class
 *														attributes, and an insert that mishandles whitespace
 *														leaves the file a little more crooked on every edit.
 *														Neither touches the dom, so both run here in plain
 *														node with a two-line window stub - no test framework,
 *														no browser, same "dependency-free smoke test" shape
 *														as the php suites.
 *
 *														What it cannot cover is the rendering in canvas.js
 *														and inspector.js, which is dom-bound.
 *
 *	Usage: node tests/templates-js-smoke.js
 */

'use strict';

const fs 		= require('fs');
const path 	= require('path');
const vm 		= require('vm');

let failures 	= 0;
let checks 		= 0;

/**
 *	Assert a condition and print the result
 *
 *	@param		{string}		label				Description of the check
 *	@param		{boolean}		condition		Result to assert
 *
 *	@return		void
 */
function check( label, condition ) {
	checks++;
	if( condition === true ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

// The modules are plain browser scripts, not modules - they are evaluated
// against a stub context. 'window' has to *be* the global object here, the
// way it is in a browser: both files hang their namespace off the window
// they are handed and then reach it again as a bare global, which only
// works while the two are the same object.
//
// 'document' is a trap rather than a stub. Both files end in the usual
// '})(window, document, document.documentElement, document.body)', so the
// name has to resolve - but any *use* of it beyond that throws. Staying
// dom-free is what makes these two testable outside a browser at all, so it
// is asserted here rather than left as a comment somebody eventually breaks
const domTrap = new Proxy( {}, {
	get : function( target, property ) {
		if( property === 'documentElement' || property === 'body' || typeof property === 'symbol' )
			return null;
		throw new Error( 'dom access in a module that must stay dom-free: document.'+ String( property ) );
	}
} );

const sandbox = { document : domTrap, console : console };
sandbox.window 			= sandbox;
sandbox.globalThis 	= sandbox;

const context = vm.createContext( sandbox );

for( const file of [ 'tree.js', 'blocks.js' ] )
	vm.runInContext( fs.readFileSync( path.join( __dirname, '../_templates/assets/', file ), 'utf8' ), context, { filename : file } );

const Nino = sandbox.Nino;
const tree = Nino.templates.tree;
const blocks = Nino.templates.blocks;

/**
 *	The block definitions the builder would fetch from library/blocks, cut
 *	down to what these tests need. Deliberately hand-written rather than
 *	read out of the php manifests: this asserts the *mapping*, and reading
 *	the real declarations would make the test pass or fail for reasons that
 *	belong to templates-smoke.php instead
 */
blocks.setAll( {
	'grid-col' : {
		key : 'grid-col', category : 'Grid', tag : 'wrap', name : 'Grid Column',
		match : { tags : [ 'div' ], classes : [], classesAny : [ 'ui-grid-50', 'ui-grid-100' ], attrs : {}, not : [] },
		settings : {
			width : { type : 'classenum', pattern : 'ui-grid-%s', bpPattern : 'ui-grid-%b-%s', breakpoints : [ 'm', 'l' ], values : [ '50', '100' ] },
			mb : { type : 'classenum', pattern : 'ui-mb-%s', values : [ '0', '1', '2', '3' ] },
		},
	},
	'section' : {
		key : 'section', category : 'Sections', tag : 'wrap', name : 'Section',
		match : { tags : [ 'section' ], classes : [ 'ui-section' ], classesAny : [], attrs : {}, not : [] },
		settings : {
			variant : { type : 'classgroup', options : { '' : 'Default', 'ui-section--dark' : 'Dark', 'ui-section--alt' : 'Alt' } },
			fullwidth : { type : 'classtoggle', class : 'ui-section--fullwidth' },
		},
	},
	'section-title' : {
		key : 'section-title', category : 'Content', tag : 'title', name : 'Section Title',
		match : { tags : [ 'h2', 'h3' ], classes : [ 'ui-section-title' ], classesAny : [], attrs : {}, not : [] },
		settings : {
			text : { type : 'text' },
			level : { type : 'tag', values : [ 'h2', 'h3' ] },
		},
	},
	'heading' : {
		key : 'heading', category : 'Content', tag : 'title', name : 'Heading',
		match : { tags : [ 'h2', 'h3' ], classes : [], classesAny : [], attrs : {}, not : [ 'ui-section-title' ] },
		settings : { text : { type : 'text' } },
	},
	'button' : {
		key : 'button', category : 'Content', tag : 'link', name : 'Button',
		match : { tags : [ 'a' ], classes : [ 'ui-btn' ], classesAny : [], attrs : {}, not : [] },
		settings : { href : { type : 'attr', attr : 'href' } },
	},
	'sc-elements' : {
		key : 'sc-elements', category : 'Shortcodes', tag : 'loop', name : 'Elements Loop',
		match : { tags : [ 'nino-sc' ], classes : [], classesAny : [], attrs : { name : 'elements' }, not : [] },
		settings : { args : { type : 'attr', attr : 'args' } },
	},
} );

/**
 *	@param		{string}	tag
 *	@param		{Array}		classes
 *	@param		{Object}	attrs
 *
 *	@return		{Object}
 */
function el( tag, classes, attrs ) {
	return { type : 'element', id : tag+ '-'+ ( classes || [] ).join('.'), tag : tag, attrs : attrs || {}, classes : classes || [], children : [] };
}

function text( value ) {
	return { type : 'text', value : value };
}


// --- blocks.match ----------------------------------------------------------

console.log( '\nblocks.match\n' );

check( 'matches a block on its required class', blocks.match( el( 'section', [ 'ui-section' ] ) ) === 'section' );
check( 'matches a grid column on any of its width classes', blocks.match( el( 'div', [ 'ui-grid-50' ] ) ) === 'grid-col' );
check( 'matches a shortcode placeholder on its name attribute', blocks.match( el( 'nino-sc', [], { name : 'elements' } ) ) === 'sc-elements' );
check( 'returns null for markup the library does not describe', blocks.match( el( 'aside', [ 'my-thing' ] ) ) === null );
check( 'returns null for a text node', blocks.match( text( 'x' ) ) === null );

// The tie-break that lets a generic and a specific block coexist
check( 'the specific block wins over the generic one', blocks.match( el( 'h3', [ 'ui-section-title' ] ) ) === 'section-title' );
check( 'the generic block still catches a plain heading', blocks.match( el( 'h3', [] ) ) === 'heading' );
check( 'a "not" class rules a block out', blocks.match( el( 'h2', [ 'ui-section-title' ] ) ) !== 'heading' );
check( 'a wrong tag rules a block out even with the right class', blocks.match( el( 'div', [ 'ui-section' ] ) ) === null );


// --- blocks.read -----------------------------------------------------------

console.log( '\nblocks.read\n' );

const column = el( 'div', [ 'ui-grid-100', 'ui-grid-m-50', 'ui-mb-3' ] );
const read = blocks.read( column, 'grid-col' );

check( 'reads a classenum off the class list', read.width === '100' );
check( 'reads its breakpoint variants separately', read['width@m'] === '50' );
check( 'reports an absent breakpoint as empty', read['width@l'] === '' );
check( 'reads a second, unrelated classenum', read.mb === '3' );

const dark = blocks.read( el( 'section', [ 'ui-section', 'ui-section--dark' ] ), 'section' );
check( 'reads a classgroup as the active class', dark.variant === 'ui-section--dark' );
check( 'reads an inactive classtoggle as false', dark.fullwidth === false );
check( 'reads an active classtoggle as true', blocks.read( el( 'section', [ 'ui-section', 'ui-section--fullwidth' ] ), 'section' ).fullwidth === true );
check( 'reads an attr setting', blocks.read( el( 'a', [ 'ui-btn' ], { href : '/x' } ), 'button' ).href === '/x' );
check( 'reads a missing attr as empty', blocks.read( el( 'a', [ 'ui-btn' ] ), 'button' ).href === '' );
check( 'reads a tag setting as the element name', blocks.read( el( 'h2', [ 'ui-section-title' ] ), 'section-title' ).level === 'h2' );

const titled = el( 'h3', [ 'ui-section-title' ] );
titled.children = [ text( 'Hello [[/x]]' ) ];
check( 'reads a text setting, text fills included', blocks.read( titled, 'section-title' ).text === 'Hello [[/x]]' );


// --- blocks.write ----------------------------------------------------------

console.log( '\nblocks.write\n' );

// The property the round-trip guarantee depends on through an edit: a class
// changes in place, it does not get removed and re-appended
const ordered = el( 'div', [ 'ui-grid-100', 'ui-grid-m-50', 'ui-mb-3' ] );
blocks.write( ordered, 'grid-col', 'width', '50' );
check( 'replaces a classenum at the index it already sat at', ordered.classes.join(' ') === 'ui-grid-50 ui-grid-m-50 ui-mb-3' );

blocks.write( ordered, 'grid-col', 'width@m', '100' );
check( 'writes a breakpoint variant without touching the base', ordered.classes.join(' ') === 'ui-grid-50 ui-grid-m-100 ui-mb-3' );

blocks.write( ordered, 'grid-col', 'mb', '' );
check( 'an empty value removes the class entirely', ordered.classes.join(' ') === 'ui-grid-50 ui-grid-m-100' );

blocks.write( ordered, 'grid-col', 'mb', '1' );
check( 'setting it again appends it', ordered.classes.join(' ') === 'ui-grid-50 ui-grid-m-100 ui-mb-1' );

const variant = el( 'section', [ 'ui-section', 'ui-section--dark' ] );
blocks.write( variant, 'section', 'variant', 'ui-section--alt' );
check( 'a classgroup swaps its active class', variant.classes.join(' ') === 'ui-section ui-section--alt' );

blocks.write( variant, 'section', 'variant', '' );
check( '...and clears it on the empty option', variant.classes.join(' ') === 'ui-section' );

blocks.write( variant, 'section', 'fullwidth', true );
check( 'a classtoggle adds its class', variant.classes.indexOf( 'ui-section--fullwidth' ) !== -1 );
blocks.write( variant, 'section', 'fullwidth', false );
check( '...and removes it again', variant.classes.indexOf( 'ui-section--fullwidth' ) === -1 );

const link = el( 'a', [ 'ui-btn' ], { href : '/x' } );
blocks.write( link, 'button', 'href', '/y' );
check( 'an attr setting writes the attribute', link.attrs.href === '/y' );
blocks.write( link, 'button', 'href', '' );
check( 'an empty attr value drops the attribute rather than writing an empty one', ( 'href' in link.attrs ) === false );

const level = el( 'h2', [ 'ui-section-title' ] );
blocks.write( level, 'section-title', 'level', 'h3' );
check( 'a tag setting renames the element', level.tag === 'h3' );
blocks.write( level, 'section-title', 'level', 'script' );
check( '...but only to a value the setting declares', level.tag === 'h3' );

const writeText = el( 'h3', [ 'ui-section-title' ] );
writeText.children = [ text( 'old' ) ];
blocks.write( writeText, 'section-title', 'text', 'new' );
check( 'a text setting replaces the node text', blocks.readText( writeText ) === 'new' );

const mixed = el( 'h3', [ 'ui-section-title' ] );
mixed.children = [ text( 'old' ), el( 'span', [] ) ];
blocks.write( mixed, 'section-title', 'text', 'new' );
check( 'writing text leaves element children alone', mixed.children.filter( c => c.type === 'element' ).length === 1 );

// read -> write -> read has to be the identity, or an edit to one setting
// silently changes another
const roundtrip = el( 'div', [ 'ui-grid-100', 'ui-grid-m-50', 'ui-mb-3' ] );
const before = blocks.read( roundtrip, 'grid-col' );
Object.keys( before ).forEach( name => blocks.write( roundtrip, 'grid-col', name, before[name] ) );
check( 'writing back every value read changes nothing', roundtrip.classes.join(' ') === 'ui-grid-100 ui-grid-m-50 ui-mb-3' );


// --- blocks entity handling ------------------------------------------------

console.log( '\nblocks entity handling\n' );

const entity = el( 'p', [] );
entity.children = [ text( 'Sil\uE000shy\uE001be' ) ];
check( 'a stored entity placeholder is shown as the entity', blocks.readText( entity ) === 'Sil&shy;be' );

blocks.writeText( entity, 'A&nbsp;B' );
check( 'an entity typed into a field is stored as a placeholder', entity.children[0].value === 'A\uE000nbsp\uE001B' );
check( '...and reads back as it was typed', blocks.readText( entity ) === 'A&nbsp;B' );


// --- tree.insert -----------------------------------------------------------

console.log( '\ntree.insert\n' );

/**
 *	A two-column row, written the way a real template is: one tab of
 *	indentation per level, newline before every tag
 */
function fixture() {
	const colA = el( 'div', [ 'ui-grid-50' ] );
	const colB = el( 'div', [ 'ui-grid-50' ] );
	colA.id = 'a';
	colB.id = 'b';
	// Both columns are empty: their only child is the whitespace holding
	// their own closing tag, at their own level - not a child's
	colA.children = [ text( '\n\t\t' ) ];
	colB.children = [ text( '\n\t\t' ) ];
	const row = el( 'div', [ 'ui-grid-row' ] );
	row.id = 'row';
	row.children = [ text( '\n\t\t' ), colA, text( '\n\t\t' ), colB, text( '\n\t' ) ];
	return [ text( '\n\t' ), row, text( '\n' ) ];
}

let doc = fixture();
tree.insert( doc, 'a', 'after', [ tree.assignIds( el( 'div', [ 'ui-grid-50' ] ) ) ] );
check( 'inserting after a sibling puts it in the same list', doc[1].children.filter( n => n.type === 'element' ).length === 3 );
check( '...at the right position', doc[1].children.filter( n => n.type === 'element' )[1].id.startsWith('c') === true );
check( '...copying the sibling\'s own indentation', doc[1].children[2].type === 'text' && doc[1].children[2].value === '\n\t\t' );

doc = fixture();
tree.insert( doc, 'row', 'child', [ tree.assignIds( el( 'p', [] ) ) ] );
const rowKids = doc[1].children;
check( 'inserting as a child appends inside the parent', rowKids.filter( n => n.type === 'element' ).length === 3 );
check( '...before the parent\'s closing indentation, not after it', rowKids[rowKids.length - 1].type === 'text' && rowKids[rowKids.length - 1].value === '\n\t' );
check( '...at the depth its siblings already use', rowKids[rowKids.length - 2].tag === 'p' && rowKids[rowKids.length - 3].value === '\n\t\t' );

// The empty-parent case: the only child list entry is the closing tag's
// indentation, so the new child has to go in front of it and a new closing
// indent has to be produced
doc = fixture();
tree.insert( doc, 'a', 'child', [ tree.assignIds( el( 'span', [] ) ) ] );
const colKids = doc[1].children[1].children;
check( 'an empty parent gets its first child indented one level deeper', colKids[0].value === '\n\t\t\t' );
check( '...the child itself', colKids[1].tag === 'span' );
check( '...and its own closing tag back on its own line', colKids[2].value === '\n\t\t' );

doc = fixture();
tree.insert( doc, null, 'after', [ tree.assignIds( el( 'section', [ 'ui-section' ] ) ) ] );
check( 'a null anchor appends at the document root', doc.filter( n => n.type === 'element' ).length === 2 );

check( 'inserting nothing is a no-op', tree.insert( fixture(), 'row', 'child', [] ) === false );
check( 'inserting against an unknown anchor fails cleanly', tree.insert( fixture(), 'nope', 'after', [ el( 'p', [] ) ] ) === false );


// --- tree.remove -----------------------------------------------------------

console.log( '\ntree.remove\n' );

doc = fixture();
tree.remove( doc, 'b' );
check( 'removes the node', tree.find( doc, 'b' ) === null );
check( '...and takes its indentation with it, leaving no blank line', doc[1].children.map( n => n.type ).join(',') === 'text,element,text' );
check( '...leaving the remaining sibling untouched', tree.find( doc, 'a' ) !== null );

// Removing the last element must keep the whitespace that holds the parent's
// closing tag on its own line - otherwise </div> ends up glued to <div>
doc = fixture();
tree.remove( doc, 'a' );
tree.remove( doc, 'b' );
check( 'removing the last child keeps the closing tag\'s own indentation', doc[1].children.length === 1 && doc[1].children[0].type === 'text' );

check( 'removing an unknown id fails cleanly', tree.remove( fixture(), 'nope' ) === false );


// --- tree.move -------------------------------------------------------------

console.log( '\ntree.move\n' );

doc = fixture();
tree.move( doc, 'b', -1 );
check( 'moving up swaps with the previous element sibling', doc[1].children.filter( n => n.type === 'element' ).map( n => n.id ).join(',') === 'b,a' );
check( '...leaving the whitespace where it was', doc[1].children.map( n => n.type ).join(',') === 'text,element,text,element,text' );

doc = fixture();
tree.move( doc, 'a', 1 );
check( 'moving down swaps with the next element sibling', doc[1].children.filter( n => n.type === 'element' ).map( n => n.id ).join(',') === 'b,a' );

check( 'moving the first node up is refused', tree.move( fixture(), 'a', -1 ) === false );
check( 'moving the last node down is refused', tree.move( fixture(), 'b', 1 ) === false );


// --- tree.duplicate --------------------------------------------------------

console.log( '\ntree.duplicate\n' );

doc = fixture();
const nested = tree.find( doc, 'a' );
nested.children = [ text( '\n\t\t\t' ), el( 'p', [] ), text( '\n\t\t' ) ];
const copy = tree.duplicate( doc, 'a' );

check( 'duplicate returns the copy', copy !== null );
check( '...inserted right after the original', doc[1].children.filter( n => n.type === 'element' ).map( n => n.id )[1] === copy.id );
check( '...with a fresh id, not the original\'s', copy.id !== 'a' && tree.find( doc, 'a' ) !== null );
check( '...deep, children included', copy.children.filter( n => n.type === 'element' ).length === 1 );
check( '...whose children got fresh ids too', copy.children.filter( n => n.type === 'element' )[0].id.startsWith('c') === true );

copy.classes.push('touched');
check( 'the copy is independent of the original', tree.find( doc, 'a' ).classes.indexOf('touched') === -1 );


// --- tree.path / find ------------------------------------------------------

console.log( '\ntree.path / tree.find\n' );

doc = fixture();
check( 'finds a nested node', tree.find( doc, 'a' ) !== null );
check( 'returns null for an unknown id', tree.find( doc, 'nope' ) === null );
check( 'the path is root-first and reaches the node', tree.path( doc, 'a' ).length === 2 );
check( 'a root-level node has a single-entry path', tree.path( doc, 'row' ).length === 1 );


console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );

process.exit( failures > 0 ? 1 : 0 );
