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
const { check, done } = require( './harness.js' );

const ROOT = path.join( __dirname, '..' );
const LIBRARY = path.join( ROOT, '_admin/install/library' );
// The optional kernel modules and the installed features both ship install
// units beside their class
const MODULE_ROOTS = [ path.join( ROOT, '_nino/Nino/Modules' ), path.join( ROOT, 'features' ) ];

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

/* A module's install unit travels with the module - install/ beside its
 * class, below _nino/ or below app/, where Setup::units() finds it - so its
 * templates are audited from there rather than from the library. */
const moduleUnitFiles = MODULE_ROOTS.flatMap( function( MODULES ) {
	return fs.readdirSync( MODULES, { withFileTypes : true } )
		.filter( function( entry ) {
			return entry.isDirectory() === true && fs.existsSync( path.join( MODULES, entry.name, 'install/manifest.php' ) );
		} )
		.flatMap( function( entry ) { return filesBelow( path.join( MODULES, entry.name, 'install' ) ); } );
} );

/* Mail has its own inline design and the three text/XML templates are not
 * HTML. */
const publicTemplates = filesBelow( LIBRARY ).concat( moduleUnitFiles ).filter( function( file ) {
	const name = path.basename( file );
	return name.endsWith('.tpl')
		&& name.startsWith('mail-') === false
		&& [ 'llms-txt.tpl', 'robots.tpl', 'sitemap-xml.tpl' ].includes( name ) === false;
} ).sort();

/* The page frames are the unit of their own that the last step of the wizard writes with the starter pages: frame-header.tpl and frame-footer.tpl,
 * which the stylesheet of the base unit styles. The audit follows them there. */
const FRAMES = [ 'frame/templates/frame-header.tpl', 'frame/templates/frame-footer.tpl' ];

check( 'the audit reaches every finished public template kind',
	FRAMES.every( function( frame ) { return publicTemplates.some( function( file ) { return relative( file ).endsWith( frame ); } ); } )
	&& publicTemplates.some( function( file ) { return relative( file ).includes('/pages/'); } )
	&& publicTemplates.some( function( file ) { return relative( file ).includes('/Modules/') && relative( file ).includes('/install/templates/'); } )
	&& publicTemplates.some( function( file ) { return relative( file ).includes('/base/templates/'); } )
);

const VOID_TAGS = new Set( [
	'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link',
	'meta', 'param', 'source', 'track', 'wbr',
] );
const WIDTH_CLASS = /^nino-grid-(?:(?:s|m|l|xl)-)?(?:25|33|50|66|75|100)$/;
const CLASS_NAME = /^nino-[a-z0-9]+(?:-[a-z0-9]+)*(?:--[a-z0-9]+(?:-[a-z0-9]+)*)?$/;

/* The classes of the site belong to the site: a class of a library template that is no class of the kernel (nino-*) is defined in the stylesheet of the
 * library, the base unit's assets/style.css. */
const STYLESHEET = fs.readFileSync( path.join( LIBRARY, 'base/assets/style.css' ), 'utf8' );
const siteClasses = new Set( Array.from( STYLESHEET.matchAll( /\.([A-Za-z_][\w-]*)/g ), function( match ) { return match[1]; } ) );

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
			if( name.startsWith('nino-') === false ) {
				if( siteClasses.has( name ) === false )
					classProblems.push( 'line '+ token.line+ ': '+ name+ ' is neither a class of the kernel nor defined in the stylesheet of the library' );
			}
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
	'_admin/install/library/base/templates/html-header.tpl',
	'_admin/install/library/base/templates/html-footer.tpl',
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
check( 'every class of a library template is defined in the stylesheet of the library or belongs to the kernel (nino-*), and the classes of the kernel keep its vocabulary'+ ( classProblems.length === 0 ? '' : ' - '+ classProblems.join('; ') ), classProblems.length === 0 );

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

/* The frames are plain html: a header and a footer element of their own, with the calls of the kernel in them and no component. */
const frameMarkup = FRAMES.map( function( frame ) { return fs.readFileSync( path.join( LIBRARY, frame ), 'utf8' ); } ).join('\n');

check( 'the frames are a header element and a footer element', /^<header class="site-header">/.test( fs.readFileSync( path.join( LIBRARY, FRAMES[0] ), 'utf8' ) )
	&& /^<footer class="site-footer">/.test( fs.readFileSync( path.join( LIBRARY, FRAMES[1] ), 'utf8' ) ) && /<script\b/.test( frameMarkup ) === false );

/* The starter site is drawn by the kernel alone, with no feature on: every call in a template of the library is one the kernel registers - what a project with no feature has as
   shortcodes - or the [else] of [imagevalues], and none is the call of a component, which a feature brings. */
const KERNEL_CALLS = [ 'json', 'imagevalues', 'else', 'link', 'assets', 'element', 'elements', 'elementvalues', 'template', 'frame', 'jstext', 'csrf', 'navigation', 'localepicker', 'legal', 'privacy' ];
const foreignCalls = [];
let calls = 0;

filesBelow( LIBRARY ).filter( function( file ) { return file.endsWith('.tpl'); } ).forEach( function( file ) {
	fs.readFileSync( file, 'utf8' ).replace( /(?<!\[)\[([a-z][a-z0-9-]*)\b/g, function( match, name ) {
		calls++;
		if( KERNEL_CALLS.includes( name ) === false )
			foreignCalls.push( relative( file )+ ': '+ name );
		return match;
	} );
} );

check( 'every call in a template of the library is a call of the kernel, and none the call of a component ('+ calls+ ' calls looked at)'+ ( foreignCalls.length === 0 ? '' : ' - '+ foreignCalls.join(', ') ), foreignCalls.length === 0 && calls > 20 );

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

/* The stylesheet of the site, by rule: a word longer than its box breaks where the box ends in the places a name of the company stands (titles, cards, the foot, the buttons of a page and the menu), so nothing
 * scrolls sideways at 320 px; and the print view hides the menu and the buttons of the header and of the title area and keeps what the page says - the address of the e-mail on the contact page is a button. */
const bare = STYLESHEET.replace( /\/\*[\s\S]*?\*\//g, '' );
const breaking = ( bare.match( /([^{}]+)\{[^{}]*overflow-wrap:\s*anywhere[^{}]*\}/g ) || [] ).join(' ');
const printBlock = ( bare.match( /@media print\s*\{([\s\S]*)\}\s*$/ ) || [ '', '' ] )[1];
const hiddenInPrint = ( printBlock.match( /([^{}]+)\{[^{}]*display:\s*none[^{}]*\}/ ) || [ '', '' ] )[1].split(',').map( selector => selector.trim() );

check( 'the stylesheet breaks a long word in titles, cards, the foot, the buttons and the menu',
	[ 'h1', 'h2', 'h3', '.card', '.site-footer', '.button', '.site-nav a', '.brand' ].every( selector => breaking.split(/[,{}]/).map( part => part.trim() ).includes( selector ) ) );
check( 'the print view hides the menu and the buttons of the header and of the title area, and no bare .button: the e-mail address of the contact page is one and is printed',
	[ '.site-nav', '.site-header .button', '.hero .button' ].every( selector => hiddenInPrint.includes( selector ) ) && hiddenInPrint.includes('.button') === false );

/* A brand with no picture and no name - the company has none yet - draws an empty link: it stays in the line, so that the menu keeps its place, and is taken out of the order of the
 * focus, so that the key never stops on a link nobody sees. The start page is in the menu. */
const emptyBrand = ( bare.match( /\.brand:has\(\s*>\s*span:empty\s*\)\s*\{([^{}]*)\}/ ) || [ '', '' ] )[1];
check( 'a brand that has neither picture nor name is hidden with its place kept - visibility, not display - so that the focus does not stop on an empty link and the menu does not move',
	/visibility:\s*hidden/.test( emptyBrand ) === true && /display:\s*none/.test( emptyBrand ) === false );

/* The frames name their menus by words the frame unit delivers, in both languages: a label that is a fill of a template key reads a key somebody writes. */
const frameWords = [ 'de_DE', 'en_US' ].map( function( locale ) { return fs.readFileSync( path.join( LIBRARY, 'frame/text/'+ locale+ '.php' ), 'utf8' ); } );
const frameLabels = Array.from( frameMarkup.matchAll( /\baria-label="\[\[(\/template\/[^\]]+)\]\]"/g ), function( match ) { return match[1]; } );

check( 'every label of the frames is a word the frame unit delivers in both languages', frameLabels.length === 2
	&& frameLabels.every( function( key ) { return frameWords.every( function( words ) { return words.includes( "'[["+ key+ "]]'" ); } ); } ) );

done();
