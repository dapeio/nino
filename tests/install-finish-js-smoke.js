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
[ [ 'finish-pw', 'input' ], [ 'finish-pw2', 'input' ], [ 'finish-msg', 'p' ], [ 'finish-form', 'form' ], [ 'finish-done', 'div' ], [ 'install-page-wrap', 'div' ], [ 'finish-next-steps', 'div' ], [ 'finish-submit', 'button' ], [ 'finish-key', 'form' ], [ 'finish-key-value', 'code' ], [ 'finish-key-msg', 'p' ], [ 'finish-key-ack', 'input' ], [ 'finish-key-continue', 'button' ], [ 'finish-key-copy', 'button' ], [ 'finish-key-save', 'button' ] ].forEach( function( [ id, tag ] ) {
	const el = element( tag );
	el.id = id;
	mount.appendChild( el );
} );

const location = { hostname : 'example.test', href : 'http://example.test/_admin/', pathname : '/_admin/', search : '', reloads : 0, reload : function() { location.reloads++ } };
// The browser's storage, which holds a value or throws where the browser keeps it shut
const stored = {};
let shut = false;
const localStorage = {
	getItem : function( key ) { if( shut === true ) throw new Error('blocked'); return Object.prototype.hasOwnProperty.call( stored, key ) ? stored[key] : null },
	setItem : function( key, value ) { if( shut === true ) throw new Error('blocked'); stored[key] = String( value ) },
};
// What the key screen reaches for: the clipboard, and a text file made of a blob
const copied = [];
let clipboardWorks = true;
const navigator = { clipboard : { writeText : function( text ) { copied.push( text ); return { then : function( ok, fail ) { return clipboardWorks === true ? ok() : fail( new Error('blocked') ) } } } } };
const made = [];
const blobs = [];
const RealURL = URL;
const URLs = Object.assign( function( path, base ) { return new RealURL( path, base ) }, { createObjectURL : function( blob ) { made.push( blob ); return 'blob:made' }, revokeObjectURL : function() { made.push( 'revoked' ) } } );
const sandbox = { console : console, URL : URLs, Blob : function( parts, options ) { blobs.push( { parts : parts, options : options } ); this.parts = parts }, navigator : navigator, document : documentOf( mount ), location : location, localStorage : localStorage };
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

/*	The recovery key: shown once, before anything else of the end, and gone when it is confirmed
	saved. The workbench or the entry point is walked into only after that	*/
const KEY = 'q1w2e3r4t5y6u7i8o9p0a1s2d3f4g5h6j7k8l9z0x1c=';
const clickChild = function( id ) { fire( byId( id ), 'click' ) };
let remembered = Object.keys( stored ).length;

// The screen is a fresh one for every run: the listeners of the one before stay on its buttons, so they are renewed
const freshKeyScreen = function() {
	[ 'finish-key', 'finish-key-ack', 'finish-key-continue', 'finish-key-copy', 'finish-key-save' ].forEach( function( id ) {
		const fresh = element( byId( id ).tagName.toLowerCase() );
		fresh.id = id;
		mount.children.splice( mount.children.indexOf( byId( id ) ), 1, fresh );
	} );
};
freshKeyScreen();
const ack = function() { return byId('finish-key-ack') }, go = function() { return byId('finish-key-continue') };

submit( { login : true, targets : [], recoveryKey : KEY } );
check( 'where the answer holds the recovery key the key is on screen, and nothing else of the end: no way into the workbench, no done panel', byId('finish-key-value').textContent === KEY
	&& byId('finish-key').classList.contains( 'install-hidden' ) === false && location.href === 'http://example.test/_admin/' && byId('finish-done').classList.contains( 'install-hidden' ) === true
	&& byId('finish-form').classList.contains( 'install-hidden' ) === true );
check( '...the way on is off until it is confirmed, and a page that is closed meanwhile asks first', go().disabled === true && typeof sandbox.onbeforeunload === 'function' );
check( '...and the form of the wizard is gone with the status line cleared of "Saving"', byId('finish-msg').textContent === '' );
fire( byId('finish-key'), 'submit' );
check( 'the form does nothing while the box is not ticked, even when it is pressed', location.href === 'http://example.test/_admin/' && byId('finish-key-value').textContent === KEY );
ack().checked = true;
fire( ack(), 'change' );
check( 'ticking the box lets it go on', go().disabled === false );
ack().checked = false;
fire( ack(), 'change' );
check( '...and clearing it stops it again', go().disabled === true );

// Copy and the text file
clickChild('finish-key-copy');
check( 'Copy puts the key on the clipboard, and says so', copied.length === 1 && copied[0] === KEY && byId('finish-key-msg').textContent !== '' );
copied.length = 0;
clipboardWorks = false;
const saidCopied = byId('finish-key-msg').textContent;
clickChild('finish-key-copy');
check( '...and where the browser refuses it says to copy it by hand instead', byId('finish-key-msg').textContent !== '' && byId('finish-key-msg').textContent !== saidCopied );
clipboardWorks = true;
const savedBefore = made.length;
clickChild('finish-key-save');
check( 'Save as text file makes the file in the browser, of the key and a line break and nothing else', blobs.length === 1 && blobs[0].parts.join('') === KEY+ '\n' && blobs[0].options.type === 'text/plain' && made.length === savedBefore + 2 && made[savedBefore] !== 'revoked' && made[savedBefore + 1] === 'revoked' );
check( '...and nothing of the key is anywhere in the browser besides the page: no storage, no address', Object.keys( stored ).length === remembered && location.href.indexOf( KEY ) === -1 );

ack().checked = true;
fire( ack(), 'change' );
fire( byId('finish-key'), 'submit' );
check( 'confirmed, the key is gone from the page and its box, the way on is walked as it always was: one account, no entry point - the workbench', byId('finish-key-value').textContent === '' && byId('finish-key').classList.contains( 'install-hidden' ) === true
	&& sandbox.onbeforeunload === null && location.href === 'http://example.test/_admin/' );

// With several accounts: after the key, the done panel
freshKeyScreen();
submit( { login : false, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' } ], recoveryKey : KEY } );
check( 'with several accounts the done panel waits for the key as well', byId('finish-done').classList.contains( 'install-hidden' ) === true && byId('finish-key-value').textContent === KEY );
byId('finish-key-ack').checked = true;
fire( byId('finish-key-ack'), 'change' );
fire( byId('finish-key'), 'submit' );
check( '...and shows the next steps once it is confirmed, the key gone', byId('finish-done').classList.contains( 'install-hidden' ) === false && byId('finish-key-value').textContent === ''
	&& findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'A' } ).length === 1 );

// A key is text, never markup
freshKeyScreen();
submit( { login : false, targets : [], recoveryKey : '<img src=x onerror=alert(1)>' } );
check( 'the key is written as text', byId('finish-key-value').textContent === '<img src=x onerror=alert(1)>' && byId('finish-key-value').children.length === 0 );

// No key in the answer: the end is what it was
freshKeyScreen();
byId('finish-key').classList.add( 'install-hidden' );
submit( { login : false, targets : [] } );
check( 'an answer without a key shows no key screen and ends as it did', byId('finish-key').classList.contains( 'install-hidden' ) === true && byId('finish-done').classList.contains( 'install-hidden' ) === false );

// The key is the one the workbench keeps its menu under
check( 'the wizard names the key the workbench stores the menu state under', source('_admin/assets/script.js').includes( "STORAGE_KEY : '"+ RAIL+ "'" ) );

done();
