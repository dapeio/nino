/**
 *	Nino									A compact filesystembased php framework
 *	admin-style-js-smoke.js	Static checks of the workbench's style sheets: no text
 *										is set smaller than 12px (a size that is below it in the
 *										sheets stands in max( size, 12px ), so that it still
 *										follows the root size above it), and the green of a word
 *										holds 4.5 : 1 on every ground it is put on, in both
 *										schemes.
 *
 *	Usage: node tests/admin-style-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { check, done, load, element, fills, documentOf, source } = require( './harness.js' );

const root = path.resolve( __dirname, '..' );

// The sheets of the workbench: its design system, and the sheet of each panel it ships
function sheets() {

	const found = [ '_admin/assets/style.css' ];

	[ '_admin/Nino/Modules', '_nino/Nino/Modules' ].forEach( function( base ) {
		fs.readdirSync( path.join( root, base ) ).sort().forEach( function( module ) {

			const dir = path.join( base, module, 'assets' );

			if( fs.existsSync( path.join( root, dir ) ) === false )
				return;

			fs.readdirSync( path.join( root, dir ) ).sort().forEach( function( file ) {
				if( /^(admin|favicon)\.css$/.test( file ) === true )
					found.push( path.join( dir, file ) );
			} );
		} );
	} );

	return found;
}

console.log( 'Workbench style sheets\n' );

// --- no text under 12px -----------------------------------------------------------------------------

const ROOT = 15;
const FLOOR = 12;

// A length in px at the root size of the workbench, or null where the value is not a plain length
function px( value ) {

	const match = /^(\d*\.?\d+)(px|rem)$/.exec( value.trim() );

	if( match === null )
		return null;

	return match[2] === 'rem' ? parseFloat( match[1] ) * ROOT : parseFloat( match[1] );
}

// What a declaration of a size comes to: smaller than the floor, or not
function small( value ) {

	const given = value.trim();
	const guard = /^max\(\s*([^,]+?)\s*,\s*12px\s*\)$/.exec( given );

	if( guard !== null )
		return false;

	const first = /^clamp\(\s*([^,]+?)\s*,/.exec( given );

	if( first !== null )
		return small( first[1] );

	if( /^(\d*\.?\d+)em$/.test( given ) === true )
		return parseFloat( given ) < 1;

	const length = px( given );

	return length !== null && length < FLOOR;
}

const found = [];

sheets().forEach( function( file ) {

	const css = fs.readFileSync( path.join( root, file ), 'utf8' ).replace( /\/\*[\s\S]*?\*\//g, '' );

	css.split( '\n' ).forEach( function( line, at ) {

		const size = /(?:^|[\s{;])font-size\s*:\s*([^;}]+)/.exec( line );
		// font: [weight] size/line-height family - the size is what stands before the slash
		const short = /(?:^|[\s{;])font\s*:\s*((?:max\([^)]*\)|[^\s/;}])+)\//.exec( line );

		if( size !== null )
			found.push( { file : file, line : at + 1, value : size[1] } );
		else if( short !== null )
			found.push( { file : file, line : at + 1, value : short[1] } );
	} );
} );

const under = found.filter( function( entry ) { return small( entry.value ) } );

check( 'the sheets of the workbench are found: its design system and the sheets of its panels, with sizes in them', sheets().length > 7 && found.length > 120 );
check( 'no size in them is under 12px at the root size of the workbench - one that is stands in max( size, 12px )',
	under.length === 0 || ( console.log( under.map( function( entry ) { return '    '+ entry.file+ ':'+ entry.line+ '  '+ entry.value } ).join('\n') ) && false ) );
check( 'the sizes under the floor are written max( size, 12px ), so that the root size above them still counts: the smallest step of type, and the sizes of the panel sheets',
	/--nino-admin-text-xs\s*:\s*max\(\s*[^,]+,\s*12px\s*\)/.test( source( '_admin/assets/style.css' ) ) === true
	&& found.filter( function( entry ) { return /^max\(/.test( entry.value.trim() ) } ).length > 0 );

// The reading of a size is itself checked, on the forms the sheets use
check( 'a size is small by its form: rem and px under the floor, an em under 1, the first of a clamp()',
	[ '.72rem', '.78rem', '11px', '.9em', 'clamp(.7rem, 2vw, 1rem)' ].every( small ) === true
	&& [ '.8rem', '.8125rem', '12px', '1em', 'max( .72rem, 12px )', 'clamp(1.15rem, 2vw, 1.4rem)', 'inherit', 'var(--x)', '0' ].some( small ) === false );

// --- the green of a word ---------------------------------------------------------------------------------

const css = source( '_admin/assets/style.css' );

// The custom properties of the block that begins at the selector
function tokens( selector ) {

	const at = css.indexOf( selector );

	if( at === -1 )
		return null;

	const block = css.slice( css.indexOf( '{', at ) + 1, css.indexOf( '}', at ) );
	const map = {};

	block.replace( /(--[a-z0-9-]+)\s*:\s*([^;]+);/g, function( all, name, value ) { map[name] = value.trim(); return all } );

	return map;
}

const sandbox = { console : console, document : documentOf( element('div') ) };
sandbox.window = sandbox;
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const model = sandbox.Nino.adminUi.colorModel;

// A colour of a token as [ r, g, b, a ]
function channels( value ) {

	const rgba = /^rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)$/.exec( value );

	if( rgba !== null )
		return [ +rgba[1], +rgba[2], +rgba[3], +rgba[4] ];

	const hex = model.parse( value );

	return hex === null ? null : [ parseInt( hex.slice( 1, 3 ), 16 ), parseInt( hex.slice( 3, 5 ), 16 ), parseInt( hex.slice( 5, 7 ), 16 ), 1 ];
}

// A tint laid over the ground it is on, as #rrggbb
function over( tint, ground ) {

	const a = channels( tint );
	const b = channels( ground );

	return '#'+ [ 0, 1, 2 ].map( function( i ) { return Math.round( a[i] * a[3] + b[i] * ( 1 - a[3] ) ).toString( 16 ).padStart( 2, '0' ) } ).join('');
}

const schemes = {
	light : tokens( ':root {' ),
	'dark (system)' : tokens( ':root:not([data-theme="light"])' ),
	'dark (chosen)' : tokens( ':root[data-theme="dark"]' ),
};

Object.keys( schemes ).forEach( function( name ) {

	const own = schemes[name];
	const light = name === 'light';
	const grounds = {
		'the page': own['--editor-bg'] ?? schemes.light['--editor-bg'],
		'a card': own['--editor-bg-elevated'] ?? schemes.light['--editor-bg-elevated'],
	};

	grounds['the page under the blue tint'] = over( own['--editor-blue-soft'] ?? schemes.light['--editor-blue-soft'], grounds['the page'] );
	grounds['the page under the green tint'] = over( own['--editor-green-soft'] ?? schemes.light['--editor-green-soft'], grounds['the page'] );

	const green = own['--editor-green'] ?? schemes.light['--editor-green'];

	check( name+ ': the token of the green is a colour', model.parse( green ) !== null && ( light || own['--editor-green'] !== undefined ) );

	Object.keys( grounds ).forEach( function( ground ) {
		const ratio = model.contrast( green, grounds[ground] );
		check( name+ ': the green of a word holds 4.5 : 1 on '+ ground, ratio !== null && ratio >= 4.5 );
	} );
} );

done();
