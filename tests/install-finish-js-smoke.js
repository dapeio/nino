/**
 *	Nino									A compact filesystembased php framework
 *	install-finish-js-smoke.js	DOM-light checks for the wizard's last step: what it
 *									tells the server about the applications, and where the
 *									answer leads - straight to the one entry point there is,
 *									else into the workbench, else to a list of links - and
 *									that an entry point that differs from the wizard's own
 *									address in the fragment alone loads the page anew.
 *
 *	Usage: node tests/install-finish-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, documentOf, source } = require( './harness.js' );

const mount = element('div');
const byId = function( id ) { return mount.children.find( function( el ) { return el.id === id } ) };
[ [ 'finish-pw', 'input' ], [ 'finish-pw2', 'input' ], [ 'finish-msg', 'p' ], [ 'finish-form', 'form' ], [ 'finish-done', 'div' ], [ 'install-page-wrap', 'div' ], [ 'finish-next-steps', 'div' ], [ 'finish-submit', 'button' ] ].forEach( function( [ id, tag ] ) {
	const el = element( tag );
	el.id = id;
	mount.appendChild( el );
} );

const location = { href : 'http://example.test/_admin/', pathname : '/_admin/', search : '', reloads : 0, reload : function() { location.reloads++ } };
// The browser's storage, which holds a value or throws where the browser keeps it shut
const stored = {};
let shut = false;
const localStorage = {
	getItem : function( key ) { if( shut === true ) throw new Error('blocked'); return Object.prototype.hasOwnProperty.call( stored, key ) ? stored[key] : null },
	setItem : function( key, value ) { if( shut === true ) throw new Error('blocked'); stored[key] = String( value ) },
};
const sandbox = { console : console, URL : URL, document : documentOf( mount ), location : location, localStorage : localStorage };
sandbox.document.createElement = element;
sandbox.window = sandbox;
sandbox.Nino = { dir : '', install : {}, events : { bindCallback : function( name, fn ) { fn() } } };

let posted = null;
let answer = null;
sandbox.Nino.install.apiCall = function( action, payload, callback ) { posted = { action : action, payload : payload }; callback( 200, answer ) };
let entries = [];
sandbox.Nino.install.applications = { applied : function() { return [ 'website' ] }, appliedEntries : function() { return entries } };

load( '_admin/install/assets/finish.js', vm.createContext( sandbox ) );

const finish = sandbox.Nino.install.finish;
const submit = function( response, dir ) {
	posted = null;
	answer = response;
	location.href = 'http://example.test/_admin/';
	location.reloads = 0;
	sandbox.Nino.dir = dir ?? '';
	byId('finish-pw').value = 'a recovery password';
	byId('finish-pw2').value = 'a recovery password';
	byId('finish-next-steps').children.length = 0;
	byId('finish-done').classList.add( 'install-hidden' );
	finish._submit( { preventDefault : function() {} } );
};

submit( { login : true, targets : [] } );
check( 'the end posts the password and the applications that went through the step', posted.action === 'finish/complete' && posted.payload.password === 'a recovery password' && ( posted.payload.applications || [] ).join() === 'website' );
// The button that ends the wizard is named for what it leads to
const button = byId('finish-submit');
entries = [];
finish.showCurrent();
const plain = button.textContent;
check( 'with no application that has an entry point the button ends the wizard and leads nowhere: no application on it', button.dataset.application === undefined && plain !== '' );
entries = [ { key : 'website', name : 'Seite <b>eins</b>' } ];
finish.showCurrent();
check( 'with exactly one it is named for it: the name it was given is on the button, and the key', button.textContent.includes( 'Seite <b>eins</b>' ) && button.dataset.application === 'website' && button.children.length === 0 && button.textContent !== plain );
entries = [ { key : 'website', name : 'Website' }, { key : 'crm', name : 'CRM' } ];
finish.showCurrent();
check( 'with several it is the button that ends the wizard, as without one', button.dataset.application === undefined && button.textContent === plain );
entries = [];

check( 'one account and no entry point: the workbench, as it always was', location.href === 'http://example.test/_admin/' && location.reloads === 0 && byId('finish-done').classList.contains( 'install-hidden' ) === true );

submit( { login : true, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' } ] } );
check( 'one account and one entry point: straight there', location.href === 'http://example.test/_admin/#builder' );
check( '...and since the address differs from the wizard\'s in the fragment alone, the page is loaded anew - it would stay on screen otherwise', location.reloads === 1 );

submit( { login : true, targets : [ { key : 'card', name : 'Card', next : '/card' } ] } );
check( 'an entry point on another path is a navigation of its own: no reload', location.href === 'http://example.test/card' && location.reloads === 0 );

submit( { login : true, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' } ] }, '/shop' );
check( 'a project in a directory is led there with the directory in front', location.href === 'http://example.test/shop/_admin/#builder' );

submit( { login : true, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' }, { key : 'crm', name : 'CRM', next : '/crm' } ] } );
const links = findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'A' } );
check( 'several entry points are no choice for the page to make: it stays, and lists them among the next steps', location.href === 'http://example.test/_admin/' && location.reloads === 0
	&& byId('finish-done').classList.contains( 'install-hidden' ) === false && links.length === 2 );
check( '...each a link to its path, marked with its application, with the name it was given in its text', links.map( function( a ) { return a.href } ).join() === '/_admin/#builder,/crm'
	&& links.map( function( a ) { return a.dataset.application } ).join() === 'website,crm'
	&& findAll( links[1], function( el ) { return el.tagName === 'STRONG' } )[0].textContent.includes( 'CRM' ) && findAll( links[0], function( el ) { return el.tagName === 'STRONG' } )[0].textContent.includes( 'Website' ) );

submit( { login : false, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' } ] }, '/shop' );
check( 'with several accounts the session is ended and even the one entry point is a link - with the directory in front', location.href === 'http://example.test/_admin/'
	&& byId('finish-done').classList.contains( 'install-hidden' ) === false && findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'A' } )[0].href === '/shop/_admin/#builder' );

submit( { login : false, targets : [] } );
check( '...and with none the page offers what it always offered', byId('finish-done').classList.contains( 'install-hidden' ) === false && findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'A' } ).length === 0 );

// A name the catalogue wrote is text, never markup
submit( { login : false, targets : [ { key : 'x', name : '<img src=x onerror=alert(1)>', next : '/x' } ] } );
const hostile = findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'STRONG' } )[0];
check( 'the name of an application is written as text', hostile.textContent.includes( '<img src=x onerror=alert(1)>' ) && hostile.children.length === 0 );

/*	An application that starts the workbench with its menu folded: the state the
	workbench keeps for it (Nino.admin.rail) is set to folded where nobody has
	chosen yet, once, and only for the application that is opened	*/
const RAIL = sandbox.Nino.install.finish.RAIL_KEY;
const folded = { key : 'vcard', name : 'vCard', next : '/_admin/#vcard', menu : 'folded' };
const plainTarget = { key : 'website', name : 'Website', next : '/_admin/#dashboard' };

submit( { login : true, targets : [ plainTarget ] } );
check( 'an application that says nothing about the menu leaves the browser\'s state alone', Object.keys( stored ).length === 0 );

submit( { login : true, targets : [ folded ] } );
check( 'one account and an application that folds the menu: the menu is folded before the page leaves, and the page leads to the entry point as for any other', stored[RAIL] === 'folded' && location.href === 'http://example.test/_admin/#vcard' );

stored[RAIL] = 'open';
submit( { login : true, targets : [ folded ] } );
check( '...a state somebody pinned is theirs and is not overwritten', stored[RAIL] === 'open' );
delete stored[RAIL];

submit( { login : false, targets : [ folded, plainTarget ] } );
check( 'where the page lists the entry points, nothing is folded before one of them is opened', Object.keys( stored ).length === 0 );
const listed = findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'A' } );
fire( listed[1], 'click' );
check( '...and opening one that says nothing leaves the state alone', Object.keys( stored ).length === 0 );
fire( listed[0], 'click' );
check( '...opening the one that folds the menu folds it', stored[RAIL] === 'folded' );

delete stored[RAIL];
shut = true;
submit( { login : true, targets : [ folded ] } );
check( 'a browser that keeps its storage shut still leads to the entry point', location.href === 'http://example.test/_admin/#vcard' );
shut = false;

// The key is the one the workbench keeps its menu under
check( 'the wizard names the key the workbench stores the menu state under', source('_admin/assets/script.js').includes( "STORAGE_KEY : '"+ RAIL+ "'" ) );

done();
