/**
 *	Dependency-free tests for Template Builder's pure client-side model helpers.
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let failures = 0;
let checks = 0;

function check( label, condition ) {
	checks++;
	if( condition ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

const callbacks = [];
const documentStub = {
	getElementById : function() { return null },
	querySelectorAll : function() { return [] },
	createElement : function() { return {} },
};
const Nino = {
	events : { bindCallback : function( event, callback ) { if( event === 'ready' ) callbacks.push( callback ) } },
	http : { sendRequest : function() {} },
};
const context = vm.createContext( {
	window : { Nino : Nino, clearTimeout : clearTimeout, setTimeout : setTimeout, confirm : function() { return true } },
	document : documentStub,
	Nino : Nino,
	console : console,
	Promise : Promise,
	Set : Set,
	URLSearchParams : URLSearchParams,
} );

[ 'script.js', 'sections.js', 'composer.js' ].forEach( function( file ) {
	vm.runInContext( fs.readFileSync( path.join( __dirname, '../_templates/assets/', file ), 'utf8' ), context, { filename : file } );
} );

const model = Nino.templates.model;

console.log('Template Builder model');

const raw = { type : 'raw', source : '<!-- locked -->\n' };
const header = { type : 'slot', slot : 'header', path : '/templates/html-header', source : model.slotSource( 'header', '/templates/html-header' ) };
const footer = { type : 'slot', slot : 'footer', path : '/templates/html-footer', source : model.slotSource( 'footer', '/templates/html-footer' ) };
const include = { type : 'template', template : 'section-nav', source : '[template /templates/section-nav]\n', _clientId : 'include' };
const first = { type : 'section', source : '<section id="one"></section>', htmlId : 'one', _clientId : 'one' };
const second = { type : 'section', source : '<section id="two"></section>', htmlId : 'two', _clientId : 'two' };
const segments = [ header, raw, first, second, include, footer ];

check( 'finds HTML and ordinary template sections but excludes page slots', JSON.stringify( model.sectionIndices( segments ) ) === JSON.stringify( [ 2, 3, 4 ] ) );
check( 'moves canvas objects while locked raw and shell slots stay fixed', model.moveSection( segments, 'one', 1 ) && segments[0] === header && segments[1] === raw && segments[2] === second && segments[3] === first && segments[5] === footer );
check( 'moves ordinary template sections through the component model', model.moveSection( segments, 'include', -1 ) && segments[3] === include && segments[4] === first );
check( 'refuses to move beyond the canvas list', model.moveSection( segments, 'second', -1 ) === false );

const inserted = { type : 'section', source : '<section id="three"></section>', htmlId : 'three', _clientId : 'three' };
model.insertSection( segments, inserted, 'two' );
check( 'inserts after the intended section', segments.indexOf( inserted ) === segments.indexOf( second ) + 1 && segments.indexOf( raw ) === 1 );
check( 'removes a section without touching locked raw segments', model.removeSection( segments, 'three' ) && segments.includes( raw ) && segments.includes( footer ) );

const emptyPage = [ Object.assign( {}, header ), Object.assign( {}, footer ) ];
model.insertSection( emptyPage, inserted, null );
check( 'puts a first HTML section between fixed header and footer slots', emptyPage[0].slot === 'header' && emptyPage[1] === inserted && emptyPage[2].slot === 'footer' );
const rawOnly = [ raw ];
model.insertSection( rawOnly, inserted, null );
check( 'puts a first section after a lone locked raw frame', rawOnly[0] === raw && rawOnly[1] === inserted );
check( 'serializes an optional shell template as an ordinary marked shortcode', model.slotSource( 'footer', '' ) === '<!-- nino:template-slot footer -->\n' && model.slotSource( 'header', '/templates/site-header' ).includes( '[template /templates/site-header]' ) );
check( 'creates a stable unique section id', model.nextId( segments, 'Main Hero') === 'main-hero' && model.nextId( segments.concat( [ { type : 'section', htmlId : 'main-hero' } ] ), 'Main Hero') === 'main-hero-2' );
check( 'document search covers display name, filename and page id', model.matchesDocument( { name : 'page-about-us', filename : 'page-about-us.tpl', displayName : 'About the studio', pageId : 'about-us' }, 'studio' ) && model.matchesDocument( { name : 'page-about-us', filename : 'page-about-us.tpl', displayName : 'About the studio', pageId : 'about-us' }, '.tpl' ) && !model.matchesDocument( { name : 'page-home', displayName : 'Homepage', pageId : 'home' }, 'contact' ) );
check( 'validates real page template filenames without hiding prefix or suffix', model.validFilename('page-error-404.tpl') && !model.validFilename('error-404') && !model.validFilename('page-../config.tpl') );
check( 'derives a readable initial name from the filename', model.displayNameFromFilename('page-error-404.tpl') === 'Error 404' );
check( 'rejects names that cannot be safely stored in an HTML comment', model.validDisplayName('Error 404') && !model.validDisplayName('Broken --> comment') );
check( 'HTML+ editing detaches generated composer metadata', Nino.templates.sectionsUI.detachMetadata( '<section>\n\t<!-- nino:section {"preset":"blank"} -->\n\t<p>Kept</p>\n</section>' ) === '<section>\n\t<p>Kept</p>\n</section>' );

console.log('\nSection Library filtering');

const matches = Nino.templates.composer.matchesPreset;
const matchesInclude = Nino.templates.composer.matchesInclude;
const preset = { name : 'FAQ — Accordion', description : 'Questions and answers', category : 'Content', tags : [ 'faq', 'support' ] };
check( 'matches preset names and tags case-insensitively', matches( preset, 'accordion', 'All' ) && matches( preset, 'SUPPORT', 'All' ) );
check( 'applies category and text filters together', matches( preset, 'questions', 'Content' ) && !matches( preset, 'questions', 'Hero' ) );
check( 'empty search keeps the selected category visible', matches( preset, '', 'Content' ) );
check( 'reusable templates participate in the Add Section search and filter', matchesInclude( { name : 'section-navigation', label : 'Section Navigation', kind : 'Section template' }, 'navigation', 'All' ) && matchesInclude( { name : 'section-navigation', label : 'Section Navigation', kind : 'Section template' }, '', 'Templates' ) && !matchesInclude( { name : 'section-navigation', label : 'Section Navigation', kind : 'Section template' }, '', 'Hero' ) );
const previewDocument = Nino.templates.composer.previewDocument( '<section id="sample"></section>' );
check( 'preview documents load the project bundle around generated section HTML', previewDocument.includes( '/.cache/style.css' ) && previewDocument.includes( '<section id="sample"></section>' ) );
check( 'preview documents block scripts, forms and third-party network access', previewDocument.includes( 'Content-Security-Policy' ) && previewDocument.includes( "script-src 'none'" ) && previewDocument.includes( "form-action 'none'" ) );

const templateMarkup = fs.readFileSync( path.join( __dirname, '../_templates/templates/page-index.tpl' ), 'utf8' );
check( 'new-template UI asks for filename, name, shell slots and VPA', [ 'pd-create-filename', 'pd-create-name', 'pd-create-header', 'pd-create-footer', 'pd-create-vpa' ].every( function( id ) { return templateMarkup.includes( 'id="'+ id+ '"' ) } ) );
check( 'the primary toolbar exposes one Add Section entry point', templateMarkup.includes( 'id="pd-add-section"' ) && templateMarkup.includes( 'id="pd-add-template"' ) === false );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures > 0 ? 1 : 0 );
