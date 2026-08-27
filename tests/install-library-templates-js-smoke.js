/**
 * Nino                        A compact filesystembased php framework
 * install-library-templates  Static contracts for the finished public
 *                             templates shipped by /_install.
 *
 * Usage: node tests/install-library-templates-js-smoke.js
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

const ROOT = path.join( __dirname, '..' );
const LIBRARY = path.join( ROOT, '_install/library' );

function filesBelow( directory ) {
	const files = [];
	fs.readdirSync( directory, { withFileTypes : true } ).forEach( function( entry ) {
		const target = path.join( directory, entry.name );
		if( entry.isDirectory() === true )
			files.push.apply( files, filesBelow( target ) );
		else
			files.push( target );
	} );
	return files;
}

function relative( file ) {
	return path.relative( ROOT, file ).replaceAll( path.sep, '/' );
}

/* Mail has its own inline design and the three text/XML templates are not
 * HTML. The dot-prefixed catalogue is an intentionally generated specimen,
 * not one of the finished units offered as a starter page. */
const publicTemplates = filesBelow( LIBRARY ).filter( function( file ) {
	const name = path.basename( file );
	return name.endsWith('.tpl')
		&& relative( file ).includes('/.demo-') === false
		&& name.startsWith('mail-') === false
		&& [ 'llms-txt.tpl', 'robots.tpl', 'sitemap-xml.tpl' ].includes( name ) === false;
} ).sort();

check( 'the audit reaches every finished public template kind',
	publicTemplates.some( function( file ) { return relative( file ).includes('/header/'); } )
	&& publicTemplates.some( function( file ) { return relative( file ).includes('/footer/'); } )
	&& publicTemplates.some( function( file ) { return relative( file ).includes('/pages/'); } )
	&& publicTemplates.some( function( file ) { return relative( file ).includes('/modules/'); } )
	&& publicTemplates.some( function( file ) { return relative( file ).includes('/base/templates/'); } )
);

const VOID_TAGS = new Set( [
	'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link',
	'meta', 'param', 'source', 'track', 'wbr',
] );
const WIDTH_CLASS = /^nino-grid-(?:(?:s|m|l|xl)-)?(?:25|33|50|66|75|100)$/;
const CLASS_NAME = /^nino-[a-z0-9]+(?:-[a-z0-9]+)*(?:--[a-z0-9]+(?:-[a-z0-9]+)*)?$/;

function tokenize( source ) {
	const tokens = [];
	const expression = /<!--[\s\S]*?-->|<![^>]*>|<\/?[A-Za-z][^>]*>/g;
	let match;
	while( ( match = expression.exec( source ) ) !== null ) {
		if( match[0].startsWith('<!--') || match[0].startsWith('<!') )
			continue;
		const tag = match[0].match( /^<\/?\s*([A-Za-z][\w:-]*)/ );
		if( tag === null )
			continue;
		const classAttribute = match[0].match( /\bclass\s*=\s*(["'])(.*?)\1/s );
		tokens.push( {
			tag : tag[1].toLowerCase(),
			closing : /^<\//.test( match[0] ),
			selfClosing : /\/\s*>$/.test( match[0] ),
			classes : classAttribute === null ? [] : classAttribute[2].trim().split( /\s+/ ).filter(Boolean),
			markup : match[0],
			line : source.slice( 0, match.index ).split('\n').length,
		} );
	}
	return tokens;
}

function inspect( file, source ) {
	const stack = [];
	const mismatches = [];
	const gridProblems = [];
	const classProblems = [];

	tokenize( source ).forEach( function( token ) {
		if( token.closing === true ) {
			const parent = stack[stack.length - 1];
			if( parent === undefined || parent.tag !== token.tag )
				mismatches.push( 'line '+ token.line+ ': '+ token.markup );
			const index = stack.map( function( open ) { return open.tag; } ).lastIndexOf( token.tag );
			if( index !== -1 )
				stack.length = index;
			return;
		}

		const parent = stack[stack.length - 1];
		const hasWidth = token.classes.some( function( name ) { return WIDTH_CLASS.test( name ); } );
		const parentIsRow = parent !== undefined && parent.classes.includes('nino-grid-row');

		if( token.classes.includes('nino-grid-row') && token.tag !== 'div' )
			gridProblems.push( 'line '+ token.line+ ': grid row is <'+ token.tag+ '>' );
		if( hasWidth && parentIsRow === false )
			gridProblems.push( 'line '+ token.line+ ': grid width without direct row parent' );
		if( parentIsRow && hasWidth === false )
			gridProblems.push( 'line '+ token.line+ ': direct row child without grid width' );

		token.classes.forEach( function( name ) {
			if( name.startsWith('nino-') === false )
				classProblems.push( 'line '+ token.line+ ': '+ name+ ' is outside the public namespace' );
			else if( CLASS_NAME.test( name ) === false )
				classProblems.push( 'line '+ token.line+ ': '+ name );
			if( name.startsWith('nino-section--') && token.classes.includes('nino-section') === false )
				classProblems.push( 'line '+ token.line+ ': '+ name+ ' without nino-section' );
		} );

		if( VOID_TAGS.has( token.tag ) === false && token.selfClosing === false )
			stack.push( token );
	} );

	return {
		mismatches : mismatches.concat( stack.map( function( token ) {
			return 'line '+ token.line+ ': unclosed <'+ token.tag+ '>';
		} ) ),
		gridProblems : gridProblems,
		classProblems : classProblems,
	};
}

/* html-header/html-footer deliberately split one document. Test that pair as
 * a pair and keep the individual fragment check for complete units only. */
const splitDocument = new Set( [
	'_install/library/base/templates/html-header.tpl',
	'_install/library/base/templates/html-footer.tpl',
] );
const structureProblems = [];
const gridProblems = [];
const classProblems = [];

publicTemplates.forEach( function( file ) {
	const source = fs.readFileSync( file, 'utf8' );
	const result = inspect( file, source );
	if( splitDocument.has( relative( file ) ) === false )
		result.mismatches.forEach( function( issue ) { structureProblems.push( relative( file )+ ': '+ issue ); } );
	result.gridProblems.forEach( function( issue ) { gridProblems.push( relative( file )+ ': '+ issue ); } );
	result.classProblems.forEach( function( issue ) { classProblems.push( relative( file )+ ': '+ issue ); } );
} );

const documentPair = fs.readFileSync( path.join( LIBRARY, 'base/templates/html-header.tpl' ), 'utf8' )
	+ '\n'
	+ fs.readFileSync( path.join( LIBRARY, 'base/templates/html-footer.tpl' ), 'utf8' );
inspect( 'base document', documentPair ).mismatches.forEach( function( issue ) {
	structureProblems.push( 'base header/footer: '+ issue );
} );

check( 'finished HTML fragments are balanced'+ ( structureProblems.length === 0 ? '' : ' - '+ structureProblems.join('; ') ), structureProblems.length === 0 );
check( 'grid rows contain direct grid columns only'+ ( gridProblems.length === 0 ? '' : ' - '+ gridProblems.join('; ') ), gridProblems.length === 0 );
check( 'public class names use the Nino vocabulary'+ ( classProblems.length === 0 ? '' : ' - '+ classProblems.join('; ') ), classProblems.length === 0 );

const definitionFiles = filesBelow( path.join( ROOT, '_nino' ) )
	.concat( filesBelow( LIBRARY ) )
	.filter( function( file ) { return /\.(?:css|js)$/.test( file ); } );
const definitions = new Set();
definitionFiles.forEach( function( file ) {
	const source = fs.readFileSync( file, 'utf8' );
	const expression = file.endsWith('.css')
		? /\.((?:nino-)[a-z0-9-]+)/g
		: /\b(nino-[a-z0-9-]+)\b/g;
	for( const match of source.matchAll( expression ) )
		definitions.add( match[1] );
} );
const undefinedClasses = [];

publicTemplates.forEach( function( file ) {
	const source = fs.readFileSync( file, 'utf8' );
	for( const match of source.matchAll( /\bclass\s*=\s*(["'])(.*?)\1/gs ) )
		match[2].trim().split( /\s+/ ).filter(Boolean).forEach( function( name ) {
			if( name.startsWith('nino-') && definitions.has( name ) === false )
				undefinedClasses.push( relative( file )+ ': '+ name );
		} );
} );

check( 'every public nino-* class has an owner'+ ( undefinedClasses.length === 0 ? '' : ' - '+ undefinedClasses.join(', ') ), undefinedClasses.length === 0 );

const orphanedFrameClasses = [];
[ 'header', 'footer' ].forEach( function( kind ) {
	fs.readdirSync( path.join( LIBRARY, kind ) ).sort().forEach( function( unit ) {
		const unitDirectory = path.join( LIBRARY, kind, unit );
		const stylesheet = path.join( unitDirectory, 'style.css' );
		const template = path.join( unitDirectory, 'template.tpl' );
		if( fs.existsSync( stylesheet ) === false || fs.existsSync( template ) === false )
			return;
		const css = fs.readFileSync( stylesheet, 'utf8' ).replace( /\/\*[\s\S]*?\*\//g, '' );
		const markup = fs.readFileSync( template, 'utf8' );
		const classes = Array.from( css.matchAll( /\.(nino-frame-[a-z0-9-]+)/g ), function( match ) { return match[1]; } );
		Array.from( new Set( classes ) ).forEach( function( name ) {
			if( markup.includes( name ) === false )
				orphanedFrameClasses.push( kind+ '/'+ unit+ ': '+ name );
		} );
	} );
} );

check( 'frame styles name markup their own unit renders'+ ( orphanedFrameClasses.length === 0 ? '' : ' - '+ orphanedFrameClasses.join(', ') ), orphanedFrameClasses.length === 0 );

const semanticProblems = [];
publicTemplates.forEach( function( file ) {
	const source = fs.readFileSync( file, 'utf8' );
	const name = relative( file );

	for( const match of source.matchAll( /<nav\b[^>]*>/g ) )
		if( /\baria-label(?:ledby)?=/.test( match[0] ) === false )
			semanticProblems.push( name+ ': unnamed navigation' );

	for( const match of source.matchAll( /<a\b[^>]*\btarget=["']_blank["'][^>]*>/g ) ) {
		if( /\brel=["'][^"']*\bnoopener\b/.test( match[0] ) === false )
			semanticProblems.push( name+ ': target=_blank without noopener' );
		if( /\baria-label=/.test( match[0] ) === false )
			semanticProblems.push( name+ ': icon link without accessible name' );
	}

	for( const match of source.matchAll( /<form\b[^>]*>/g ) )
		if( /\bmethod=["']post["']/i.test( match[0] ) === false || /\baction=/.test( match[0] ) === false )
			semanticProblems.push( name+ ': form without POST action' );

	for( const match of source.matchAll( /<[^>]*\bclass=["'][^"']*\bnino-form-message\b[^"']*["'][^>]*>/g ) )
		if( /\baria-live=["']polite["']/.test( match[0] ) === false )
			semanticProblems.push( name+ ': form message without polite live region' );

	if( /<h[3-6]\b[^>]*\bnino-footer-title\b/.test( source ) )
		semanticProblems.push( name+ ': footer title skips to a lower heading level' );

	const ids = Array.from( source.matchAll( /\bid=["']([^"']+)["']/g ), function( match ) { return match[1]; } );
	const duplicates = ids.filter( function( id, index ) { return ids.indexOf( id ) !== index; } );
	if( duplicates.length > 0 )
		semanticProblems.push( name+ ': duplicate id '+ Array.from( new Set( duplicates ) ).join(', ') );
} );

check( 'finished templates keep non-visual HTML semantics'+ ( semanticProblems.length === 0 ? '' : ' - '+ semanticProblems.join('; ') ), semanticProblems.length === 0 );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failures' );
if( failures > 0 )
	process.exit(1);
