/**
 *	Nino										A compact filesystembased php framework
 *	nino-ui-previewtools-js-smoke.js	DOM-light checks for Nino.adminUi.previewTools(), the tools of a preview - the page it
 *													shows, the width it is laid out at, Reload - and for the page in a frame being marked by
 *													previewFrame() so that the sticky header of Basis does not stay. Held by structure: what
 *													is named, what reports a change made by hand and what does not.
 *
 *	Usage: node tests/nino-ui-previewtools-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, fills, documentOf } = require( './harness.js' );

const sandbox = { console : console, document : documentOf( element('div') ), location : { host : 'localhost', origin : 'http://localhost', href : 'http://localhost/_admin/' }, URL : URL };
sandbox.window = sandbox;
sandbox.clearTimeout = function() {};
sandbox.setTimeout = function() { return 0 };
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;
// The words are the fills of the workbench, whatever they say
const word = ( name ) => fills( '_admin/text/en_US.php' )['/_admin/common/'+ name];

const told = [];
const build = ( extra ) => adminUi.previewTools( Object.assign( {
	pages : [ { value : '', label : 'Automatic' }, { value : '/contact', label : 'Contact' } ], page : '/contact', width : 'tablet',
	onPage : value => told.push( 'page:'+ value ), onWidth : name => told.push( 'width:'+ name ), onReload : () => told.push('reload'),
}, extra || {} ) );
const buttonsOf = ( root ) => findAll( root, el => el.tagName === 'BUTTON' );
// The word of a step is the text of the span it holds
const wordOf = ( button ) => findAll( button, el => el.tagName === 'SPAN' )[0].textContent;

console.log( 'Nino.adminUi.previewTools - the tools of a preview\n' );

{
	const tools = build();
	const steps = buttonsOf( tools.widths.el );

	check( 'the page is chosen in a select that a visible word names, and that stands at the page that is shown',
		tools.select.tagName === 'SELECT' && tools.select.parentNode.tagName === 'LABEL' && tools.select.parentNode.children[0].textContent === word('preview/shows') && tools.select.children.length === 2 && tools.select.value === '/contact' && tools.page() === '/contact' );
	check( 'the width is a group of steps named by a word, one step for each width, the one that was asked for chosen',
		tools.widths.el.getAttribute('role') === 'radiogroup' && steps.map( wordOf ).join() === [ 'mobile', 'tablet', 'desktop' ].map( name => word( 'preview/'+ name ) ).join() && tools.widths.el.getAttribute('aria-labelledby') !== null
		&& steps.map( el => el.getAttribute('aria-checked') ).join() === 'false,true,false' && tools.width() === 'tablet' );
	check( 'without icons the widths are words, as before', steps.every( el => findAll( el, node => node.tagName === 'SVG' ).length === 0 ) );

	check( 'Reload is an icon button with a name', tools.reload.tagName === 'BUTTON' && tools.reload.getAttribute('aria-label') === word('split/reload') && findAll( tools.reload, el => el.tagName === 'SVG' ).length === 1 );

	tools.select.value = '';
	fire( tools.select, 'change' );
	fire( steps[2], 'click' );
	fire( tools.reload, 'click' );
	check( 'a page, a width and Reload chosen by hand are told to the caller, once each', told.join() === 'page:,width:desktop,reload' && tools.width() === 'desktop' && tools.page() === '' );

	told.length = 0;
	tools.setPage('/contact');
	tools.setWidth('mobile');
	check( 'setPage() and setWidth() move the tools from outside and tell nobody', told.length === 0 && tools.page() === '/contact' && tools.width() === 'mobile' && steps[0].getAttribute('aria-checked') === 'true' );
}

{
	const tools = build( { pages : [], widths : [ 'mobile', 'desktop' ], width : 'none', text : { shows : 'Page', width : 'Layout', mobile : 'Phone' } } );

	check( 'with no page there is no choice of one, the widths are the ones that are given, and a width that is none of them is none chosen',
		tools.select === null && buttonsOf( tools.widths.el ).map( wordOf ).join() === 'Phone,Desktop' && tools.width() === null );
	check( 'the words can be said differently', findAll( tools.el, el => el.textContent === 'Layout' ).length === 1 );
}

{
	const quiet = build( { note : 'Shows the start page', reload : false } );
	const noted = findAll( quiet.el, el => String( el.className ).split(' ').includes('nino-admin-previewtools-note') );

	check( 'a quiet line stands beside the choice of the page, and can be said again', noted.length === 1 && quiet.note === noted[0] && quiet.el.children.indexOf( noted[0] ) === 1 && ( quiet.setNote('Other'), noted[0].textContent === 'Other' ) );
	check( 'where the view has a button of its own the button is left out', quiet.reload === null && buttonsOf( quiet.el ).every( el => String( el.className ).includes('nino-admin-previewtools-reload') === false ) );
	check( 'without a line there is none', build().note === null && build().reload !== null );
}

// --- a page marked for the workbench ----------------------------------------------------

console.log( '\nNino.adminUi.previewFrame - the page in the frame is marked\n' );

{
	const split = adminUi.split( { settings : element('div'), preview : { src : '/preview' } } );
	const root = element('html');
	split.frame.contentDocument = { documentElement : root };

	adminUi.previewFrame( { split : split, send : () => {}, address : query => '/p?'+ query, mark : 'loose' } );

	check( 'the page in the frame is marked on its <html> when the option is given: the attribute the stylesheets of a page ask for', root.getAttribute('data-nino-preview') === 'loose' );

	root.removeAttribute('data-nino-preview');
	fire( split.frame, 'load' );
	check( '...and again with every page the frame loads', root.getAttribute('data-nino-preview') === 'loose' );

	const blank = adminUi.split( { settings : element('div'), preview : { src : '/preview' } } );
	adminUi.previewFrame( { split : blank, send : () => {}, address : query => '/p?'+ query } );
	const untouched = element('html');
	blank.frame.contentDocument = { documentElement : untouched };
	fire( blank.frame, 'load' );
	check( 'without the option nothing is written in the page', untouched.hasAttribute('data-nino-preview') === false );

	const sealed = adminUi.split( { settings : element('div'), preview : { src : '/preview' } } );
	Object.defineProperty( sealed.frame, 'contentDocument', { get : function() { throw new Error('another origin') } } );
	check( 'a page that cannot be reached - another origin - is passed over in silence', ( adminUi.previewFrame( { split : sealed, send : () => {}, address : query => query, mark : 'loose' } ), fire( sealed.frame, 'load' ), true ) );

	const asked = [];
	const marking = adminUi.previewFrame( { split : { frame : null, reload : src => asked.push( src ), width : () => null }, send : ( token, done ) => done( 200, { token : 't', query : 'nino-preview=t', ttl : 900 } ), address : query => '/p?'+ query, mark : 'loose' } );
	marking.flush();
	check( 'the address of the page asks for the mark, so that the kernel writes it from the first byte - the script follows after the load', asked.length === 1 && asked[0] === '/p?nino-preview=t&nino-preview-mark=loose' );

	check( 'a word that is none is refused: it is written in a page', [ 'Loose', 'a b', '"x', '1x' ].every( word => { try { adminUi.previewFrame( { split : split, send : () => {}, address : query => query, mark : word } ) } catch( e ) { return e.name === 'TypeError' } return false } ) );
}

{
	const pictured = build( { icons : true, widths : [ 'mobile', 'tablet', 'desktop', 'wide' ] } );
	const steps = buttonsOf( pictured.widths.el );
	const svgs = el => findAll( el, node => node.tagName === 'SVG' );
	const quiet = el => findAll( el, node => String( node.className ).split(' ').includes('nino-admin-sr-only') );

	check( 'with icons the three widths are pictures, each with its word kept as the name for a screen reader and as its tooltip; a width that has no picture keeps its word',
		steps.slice( 0, 3 ).every( ( el, at ) => svgs( el ).length === 1 && quiet( el )[0].textContent === word( 'preview/'+ [ 'mobile', 'tablet', 'desktop' ][at] ) && el.title === quiet( el )[0].textContent )
		&& svgs( steps[3] ).length === 0 && steps[3].title === '' );
	told.length = 0;
	fire( steps[2], 'click' );
	check( '...and they are chosen and told as the words are', told.join() === 'width:desktop' && pictured.width() === 'desktop' );
}

done();
