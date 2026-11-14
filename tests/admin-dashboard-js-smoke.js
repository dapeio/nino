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
 *													The areas a feature registers stand above the notices
 *													(Nino.admin.dashboard.head): the pins at the end of the
 *													file hold what draws them, in which order, and what
 *													leaves them out.
 *
 *	Usage: node tests/admin-dashboard-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, documentOf, findAll, element } = require( './harness.js' );

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

const askedKeys = [];

function text( key ) {
	return moduleEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'admin-dashboard-content';
const wrap = element('div');
wrap.id = 'dashboard-list';
mount.appendChild( wrap );

const requests = [];

const Nino = {
	admin : { router : { set : function() {} } },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, callback : callback } );
	} },
	content : { getText : function( key ) { askedKeys.push( key ); return text( key ) } },
};
// What the script says to the console: the areas it leaves out are named there
const warned = [];
const sandbox = {
	console : { warn : function( text ) { warned.push( String( text ) ) }, log : console.log, error : console.error },
	document : Object.assign( documentOf( wrap ), {
		createTextNode : function( value ) { return { nodeType : 3, textContent : String( value ), children : [] } },
	} ),
	Nino : Nino,
};
sandbox.window = { Nino : Nino };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Dashboard/assets/admin.js', context );

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

pinHeadAreas();

done();


// --- the head --------------------------------------------------------------

/** The element an area draws: a card with its data as text */
function area( name ) {
	return function( data ) {
		const card = element('div');
		card.id = 'area-'+ name;
		card.textContent = String( ( data || {} ).text );
		return card;
	};
}

/** An area is registered by name and drawn from the answer's head, above the notices; the kernel draws none of its own */
function pinHeadAreas() {

	show( { tiles : [], notices : [ { text : '/_admin/dashboard/notice/mail', values : [ 'x', '1' ], link : '' } ] } );
	check( 'with no area registered the dashboard opens on its notices', wrap.children[0].className === 'nino-admin-notice' && wrap.children.length === 2 );

	show( { tiles : [], notices : [], head : { first : { text : 'no one asked' } } } );
	check( '...and a head in the answer that no script registered is not drawn', wrap.children.length === 1 && wrap.children[0].id === 'admin-dashboard-tiles' );

	panel.head.add( 'first', area( 'first' ) );
	panel.head.add( 'second', area( 'second' ) );
	const answer = { tiles : [], notices : [ { text : '/_admin/dashboard/notice/mail', values : [ 'x', '1' ], link : '' } ], head : { second : { text : 'Two <b>x</b>' }, first : { text : 'One' } } };
	show( answer );
	check( 'the areas stand first, before the notices and the tiles, in the order they were registered whatever the answer\'s order', wrap.children[0].id === 'area-first' && wrap.children[1].id === 'area-second'
		&& wrap.children[2].className === 'nino-admin-notice' && wrap.children[3].id === 'admin-dashboard-tiles' );
	check( '...what the server sent is text', wrap.children[1].textContent === 'Two <b>x</b>' && wrap.children[1].children.length === 0 );

	show( Object.assign( {}, answer, { head : { second : { text : 'Only' } } } ) );
	check( 'an area the answer does not carry is not drawn', wrap.children[0].id === 'area-second' && findAll( wrap, el => el.id === 'area-first' ).length === 0 );

	show( Object.assign( {}, answer, { head : 'broken' } ) );
	check( '...and a head that is no object draws none', findAll( wrap, el => /^area-/.test( el.id ) ).length === 0 && wrap.children[0].className === 'nino-admin-notice' );

	panel.head.add( 'null', function() { return null } );
	show( Object.assign( {}, answer, { head : { 'null' : { text : 'x' }, first : { text : 'One' } } } ) );
	check( 'an area that draws nothing leaves no gap', wrap.children[0].id === 'area-first' && wrap.children[1].className === 'nino-admin-notice' );

	panel.head.add( 'first', area( 'again' ) );
	show( Object.assign( {}, answer, { head : { second : { text : 'Two' }, first : { text : 'One' } } } ) );
	check( 'registering a name again replaces its area, and the area goes last', findAll( wrap, el => el.id === 'area-first' ).length === 0 && wrap.children[0].id === 'area-second' && wrap.children[1].id === 'area-again' );

	pinHeadAreaThatFails();

	const before = panel.head._areas.length;
	panel.head.add( '', area( 'x' ) );
	panel.head.add( 'broken', 'not a function' );
	check( 'a name that is empty and a renderer that is no function register nothing', panel.head._areas.length === before );
}

/** An area that throws or draws what is no element is named in the console and left out; the other areas and the rest of the Dashboard are drawn */
function pinHeadAreaThatFails() {

	const answer = { tiles : [ { panel : 'users', value : '3', label : 'Users' } ], notices : [], head : { fine : { text : 'Fine' }, bad : { text : 'x' }, 'also-fine' : { text : 'Also' } } };
	const failing = {
		'throws' : function() { throw new Error('boom') },
		'a string' : function() { return '<b>text</b>' },
		'a number' : function() { return 7 },
		'an object' : function() { return { tagName : 'DIV' } },
		'null' : function() { return null },
	};

	panel.head._areas = [];
	panel.head.add( 'fine', area( 'fine' ) );
	panel.head.add( 'bad', failing['null'] );
	panel.head.add( 'also-fine', area( 'also-fine' ) );

	Object.keys( failing ).forEach( function( kind ) {
		panel.head._areas[1].render = failing[kind];
		warned.length = 0;
		show( answer );
		const ids = Array.from( wrap.children ).map( c => c.id );
		check( 'an area that '+ kind+ ' leaves the others and the tiles drawn', ids.join() === 'area-fine,area-also-fine,admin-dashboard-tiles' );
		check( '...'+ ( kind === 'null' ? 'and says nothing, an area may draw none' : 'and is named in the console, by its name' ), kind === 'null' ? warned.length === 0 : warned.length === 1 && warned[0].indexOf('"bad"') !== -1 );
	} );
}
