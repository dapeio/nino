/**
 *	Nino										A compact filesystembased php framework
 *	admin-dashboard-js-smoke.js	DOM-light checks for the Dashboard panel's script
 *													(_admin/Nino/Modules/Dashboard/assets/admin.js) and the
 *													notice component it draws with (Nino.adminUi.notice()).
 *
 *													A notice is what the workbench says needs attention -
 *													mail that fails, texts without a translation - so what
 *													matters here is that it is said at all, above the
 *													numbers, in the interface language, with its blanks
 *													filled in order, and that nothing the server sent can
 *													become markup.
 *
 *	Usage: node tests/admin-dashboard-js-smoke.js
 */

'use strict';

const fs 		= require('fs');
const path	= require('path');
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

function source( relative ) {
	return fs.readFileSync( path.join( __dirname, '..', relative ), 'utf8' );
}

/**
 *	The fills of one text file, key => value - enough of the php to read
 *	a `'[[/key]]' => 'value',` line
 */
function fills( relative ) {
	const map = {};
	const re = /'\[\[(\/_admin\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g;
	const text = source( relative );
	let match;
	while( ( match = re.exec( text ) ) )
		map[match[1]] = match[2].replace( /\\'/g, "'" );
	return map;
}


// --- an element stand-in, the same shape the other panel tests build -------

function findAll( root, predicate ) {
	const found = [];
	( function walk( node ) {
		( node.children || [] ).forEach( function( child ) {
			if( predicate( child ) === true )
				found.push( child );
			walk( child );
		} );
	} )( root );
	return found;
}

function element( tag ) {
	const el = {
		tagName : String( tag ).toUpperCase(),
		className : '',
		textContent : '',
		id : '',
		href : '',
		style : {},
		attributes : {},
		children : [],
		appendChild : function( child ) { el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		addEventListener : function() {},
	};
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

/** The text a notice shows: its own, and the label of its link */
function notices() {
	return findAll( mount, function( el ) { return el.className === 'nino-admin-notice' } );
}

function links( notice ) {
	return findAll( notice, function( el ) { return el.tagName === 'A' } );
}

function ownText( notice ) {
	return notice.textContent;
}


// --- the words -------------------------------------------------------------

const moduleEn = fills('_admin/Nino/Modules/Dashboard/text/en_US.php');

function text( key ) {
	return moduleEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'admin-dashboard-content';
const wrap = element('div');
wrap.id = 'admin-content-dashboard';
mount.appendChild( wrap );

const requests = [];

const Nino = {
	admin : { router : { set : function() {} } },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		createTextNode : function( value ) { return { nodeType : 3, textContent : String( value ), children : [] } },
		getElementById : function( id ) { return id === 'admin-content-dashboard' ? wrap : null },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino };

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
vm.runInContext( source('_admin/Nino/Modules/Dashboard/assets/admin.js'), context, { filename : 'admin.js' } );

const panel = Nino.admin.dashboard;

function show( body ) {
	panel.init();
	requests[requests.length - 1].callback( { status : 200, responseJSON : body } );
}

console.log('Admin Dashboard');

const mailText = 'Mail delivery has been failing since %s (%s failed attempts).';
moduleEn['/_admin/dashboard/notice/mail'] = mailText;

// --- the component ---------------------------------------------------------

const plain = Nino.adminUi.notice( 'Something needs you' );
check( 'a notice is a status paragraph of the design system, its text set as text', plain.tagName === 'P' && plain.className === 'nino-admin-notice'
	&& plain.attributes.role === 'status' && plain.textContent === 'Something needs you' );
check( '...with no link where none was given', plain.children.length === 0 );

const markup = Nino.adminUi.notice( '<img src=x onerror=alert(1)>' );
check( '...and markup in it stays text', markup.textContent === '<img src=x onerror=alert(1)>' && markup.children.length === 0 );

const linked = Nino.adminUi.notice( 'Open the panel', { href : '#text', label : 'Open' } );
check( 'a link to a panel of the workbench is an anchor with its label', links( linked ).length === 1 && links( linked )[0].href === '#text' && links( linked )[0].textContent === 'Open' );
check( '...a hash with a path in it too', links( Nino.adminUi.notice( 'x', { href : '#elements/news', label : 'Open' } ) ).length === 1 );

[ 'javascript:alert(1)', 'https://example.org/', '//example.org/', '#', '#Text', '#a b', '', '#"onclick="x' ].forEach( function( href ) {
	check( 'no link for the target '+ JSON.stringify( href ), links( Nino.adminUi.notice( 'x', { href : href, label : 'Open' } ) ).length === 0 );
} );
check( '...nor for a link that is no object', links( Nino.adminUi.notice( 'x', null ) ).length === 0 && links( Nino.adminUi.notice( 'x', '#text' ) ).length === 0 );

// --- the panel -------------------------------------------------------------

show( { tiles : [ { panel : 'users', value : '3', label : 'Users' } ], lastBackup : '2024-05-03',
	notices : [
		{ text : '/_admin/dashboard/notice/mail', values : [ '2024-05-03 10:15', '3' ], link : '' },
		{ text : 'A text the server wrote itself, 100%', values : [], link : '#text' },
	] } );

const drawn = notices();
check( 'every notice of the answer is drawn', drawn.length === 2 );
check( 'the fill key is resolved and the blanks are filled in order', ownText( drawn[0] ) === 'Mail delivery has been failing since 2024-05-03 10:15 (3 failed attempts).' );
check( '...a notice with no link has none', links( drawn[0] ).length === 0 );
check( '...a text that is no key is shown as it is', ownText( drawn[1] ).startsWith( 'A text the server wrote itself, 100%' ) );
check( '...and a link is drawn for a #panel, labelled with the dashboard\'s own word', links( drawn[1] ).length === 1 && links( drawn[1] )[0].href === '#text'
	&& links( drawn[1] )[0].textContent === text('/_admin/dashboard/notice/open') && text('/_admin/dashboard/notice/open') === 'Open' );
check( 'the notices stand above the tiles', wrap.children[0].className === 'nino-admin-notice' && wrap.children[1].className === 'nino-admin-notice'
	&& wrap.children[2].id === 'admin-dashboard-tiles' );

// The values are what a person or a file produced
show( { tiles : [], notices : [ { text : '/_admin/dashboard/notice/mail', values : [ '<b>x</b>', '$&' ], link : '' } ] } );
check( 'a value containing markup or a replacement pattern stays text, as it stands', ownText( notices()[0] ) === 'Mail delivery has been failing since <b>x</b> ($& failed attempts).' );

show( { tiles : [], notices : [ { text : '/_admin/dashboard/notice/mail', values : [ 7 ], link : 3 } ] } );
check( 'a number is filled in as its text, a missing value leaves the blank, and a link that is no string is none', ownText( notices()[0] ) === 'Mail delivery has been failing since 7 (%s failed attempts).'
	&& links( notices()[0] ).length === 0 );

// What the Legal module tells: the placeholder, the title of a section a person wrote, the language
const unknownWords = text('/_admin/dashboard/notice/legal-unknown');
show( { tiles : [], notices : [
	{ text : '/_admin/dashboard/notice/legal-unknown', values : [ '#/project/company/contact/email#', 'Hosting <i>$&</i>', 'de_DE' ], link : '#elements/privacy/hosting' },
	{ text : '/_admin/dashboard/notice/legal-more', values : [ '3' ], link : '' },
] } );
check( 'a notice of the legal texts fills its three values in as text, whatever a section is called', unknownWords.split('%s').length === 4 && notices().length === 2
	&& ownText( notices()[0] ).indexOf( unknownWords.split('%s')[0]+ '#/project/company/contact/email#' ) === 0 && ownText( notices()[0] ).indexOf( 'Hosting <i>$&</i>' ) !== -1 && ownText( notices()[0] ).indexOf( '(de_DE)' ) !== -1 );
check( '...with a link into the section it is about', links( notices()[0] ).length === 1 && links( notices()[0] )[0].href === '#elements/privacy/hosting' );
check( '...and the line that counts the rest has no link and its number in it', links( notices()[1] ).length === 0 && ownText( notices()[1] ) === text('/_admin/dashboard/notice/legal-more').replace( '%s', '3' ) && text('/_admin/dashboard/notice/legal-more').indexOf( '%s' ) !== -1 );

show( { tiles : [ { panel : 'users', value : '3', label : 'Users' } ] } );
check( 'an answer without notices draws none, and the tiles as before', notices().length === 0 && wrap.children[0].id === 'admin-dashboard-tiles' );

show( { tiles : [], notices : [] } );
check( '...an empty list the same', notices().length === 0 );

show( { tiles : [], notices : 'broken' } );
check( '...and a notices that is no list is ignored rather than thrown at', notices().length === 0 );

console.log( '\n'+ checks +' checks, '+ failures +' failed' );
process.exit( failures > 0 ? 1 : 0 );
