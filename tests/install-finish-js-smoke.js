/**
 *	Nino									A compact filesystembased php framework
 *	install-finish-js-smoke.js	DOM-light checks for the wizard's last step: what it
 *									tells the server about the applications, and where the
 *									answer leads - straight to the one entry point there is,
 *									else into the workbench, else to a list of links - and
 *									that an entry point that differs from the wizard's own
 *									address in the fragment alone loads the page anew. The
 *									passwords are the fields of the blocks of the workbench,
 *									what is wrong with them is said at them, and the one main
 *									button is the bar's, named for what it leads to.
 *
 *	Usage: node tests/install-finish-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, documentOf, source } = require( './harness.js' );

const root = element('div');
const wrap = element('div');
wrap.id = 'install-page-wrap';
wrap.dataset.minPassword = '8';
wrap.dataset.maxPassword = '72';
root.appendChild( wrap );
[ 'finish-content', 'finish-key-wrap', 'finish-done', 'finish-next-steps' ].forEach( function( id ) {
	const el = element('div');
	el.id = id;
	wrap.appendChild( el );
} );
const heading = element('h2');
byIdOf( 'finish-done' ).appendChild( heading );

function byIdOf( id ) { return findAll( root, el => el.id === id )[0] }
const byId = byIdOf;

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

const doc = Object.assign( documentOf( root ), { documentElement : element('html'), body : element('body'), addEventListener : function() {}, removeEventListener : function() {} } );
doc.createElement = element;
const Nino = { dir : '', events : { bindCallback : function() {} }, content : { text : {}, getText : function( key ) { return Nino.content.text[key] || '' } } };
const sandbox = { console : console, URL : URLs, Blob : function( parts, options ) { blobs.push( { parts : parts, options : options } ); this.parts = parts }, navigator : navigator, document : doc, location : location, localStorage : localStorage, Nino : Nino };
sandbox.window = sandbox;
sandbox.matchMedia = function() { return { matches : false } };
sandbox.setTimeout = function() { return 0 };
sandbox.clearTimeout = function() {};
const context = vm.createContext( sandbox );

[ '_admin/assets/Nino.admin.js', '_admin/assets/pagetext.js', '_admin/install/assets/text.js' ].forEach( file => load( file, context ) );
const text = Nino.pageText;
text.set('en');
const en = text._tables.en;

const said = [];
Nino.install = {
	t : function( key, ...params ) { return text.t( key, ...params ) },
	minPassword : function() { return 8 },
	maxPassword : function() { return 72 },
	failure : function( status, response ) { return text.failure( status, response, 8 ) },
	say : function( words, error ) { said.push( [ words, error === true ] ) },
	next : function() { Nino.install.finish.submit( function( ok, message ) { outcome = [ ok, message ] } ) },
};
let outcome = null;
let posted = null;
let answer = null;
Nino.install.apiCall = function( action, payload, callback ) { posted = { action : action, payload : payload }; callback( answer.status ?? 200, answer.status === undefined ? answer : answer.response ) };
let entries = [];
Nino.install.applications = { applied : function() { return [ 'website' ] }, appliedEntries : function() { return entries } };
// The wizard's shell makes the bar; here its main button is a stand-in that names the application
Nino.install._index = 5;
Nino.install.STEPS = [ 1, 2, 3, 4, 5, 6 ];

load( '_admin/install/assets/finish.js', context );

const finish = Nino.install.finish;
finish.showCurrent();

const field = id => byId( id );
const errors = () => findAll( byId('finish-content'), el => el.className === 'nino-admin-field-error' ).map( el => el.textContent );

const submit = function( response, dir ) {
	posted = null;
	outcome = null;
	answer = response;
	location.href = 'http://example.test/_admin/';
	location.reloads = 0;
	Nino.dir = dir ?? '';
	field('finish-pw').value = 'a recovery password';
	field('finish-pw2').value = 'a recovery password';
	byId('finish-next-steps').children.filter( el => el.dataset.application !== undefined ).forEach( el => el.remove() );
	byId('finish-done').classList.add( 'install-hidden' );
	finish.submit( function( ok, message ) { outcome = [ ok, message ] } );
};

check( 'the step draws a form of the blocks of the workbench: novalidate, the recovery password twice as required fields with a star, the first with its (?), and no button of its own - the one main button is the bar\'s',
	byId('finish-form').noValidate === true && [ 'finish-pw', 'finish-pw2' ].every( id => field(id).type === 'password' && field(id).autocomplete === 'new-password' && field(id).getAttribute('aria-required') === 'true' )
	&& findAll( byId('finish-content'), el => el.tagName === 'BUTTON' && /nino-admin-btn-(primary|secondary|danger)/.test( el.className ) ).length === 0 && findAll( byId('finish-content'), el => el.className === 'nino-admin-help' ).length >= 2 );

submit( { login : true, targets : [] } );
check( 'the end posts the password, the applications that went through the step and the language the page speaks', posted.action === 'finish/complete' && posted.payload.password === 'a recovery password' && ( posted.payload.applications || [] ).join() === 'website' && posted.payload.lang === 'en' );
check( '...and calls back that it went through, which releases the bar', outcome[0] === true );

// The button that ends the wizard is named for what it leads to: the step says which application
entries = [];
check( 'with no application that has an entry point the button ends the wizard and leads nowhere', finish.entry() === null );
entries = [ { key : 'website', name : 'Seite <b>eins</b>' } ];
check( 'with exactly one it is named for it: the key and the name it was given', finish.entry().key === 'website' && finish.entry().name === 'Seite <b>eins</b>' );
entries = [ { key : 'website', name : 'Website' }, { key : 'crm', name : 'CRM' } ];
check( 'with several it is the button that ends the wizard, as without one', finish.entry() === null );
entries = [];

check( 'one account and no entry point: the workbench, as it always was', location.href === 'http://example.test/_admin/' && location.reloads === 0 && byId('finish-done').classList.contains( 'install-hidden' ) === true );
check( '...and the line says that the workbench is opening, in the words of the page', said.some( entry => entry[0] === en['/_admin/install/finish/opening'] ) );

submit( { login : true, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' } ] } );
check( 'one account and one entry point: straight there', location.href === 'http://example.test/_admin/#builder' );
check( '...and since the address differs from the wizard\'s in the fragment alone, the page is loaded anew - it would stay on screen otherwise', location.reloads === 1 );

submit( { login : true, targets : [ { key : 'card', name : 'Card', next : '/card' } ] } );
check( 'an entry point on another path is a navigation of its own: no reload', location.href === 'http://example.test/card' && location.reloads === 0 );

submit( { login : true, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' } ] }, '/shop' );
check( 'a project in a directory is led there with the directory in front', location.href === 'http://example.test/shop/_admin/#builder' );

submit( { login : true, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' }, { key : 'crm', name : 'CRM', next : '/crm' } ] } );
const links = findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'A' && el.dataset.application !== undefined } );
check( 'several entry points are no choice for the page to make: it stays, and lists them among the next steps', location.href === 'http://example.test/_admin/' && location.reloads === 0
	&& byId('finish-done').classList.contains( 'install-hidden' ) === false && links.length === 2 );
check( '...each a link to its path, marked with its application, with the name it was given in its text', links.map( function( a ) { return a.href } ).join() === '/_admin/#builder,/crm'
	&& links.map( function( a ) { return a.dataset.application } ).join() === 'website,crm'
	&& findAll( links[1], function( el ) { return el.tagName === 'STRONG' } )[0].textContent === en['/_admin/install/finish/set-up'].replace( '%s', 'CRM' ) );
check( '...and the focus goes to the heading of the panel', heading.focused === true );

submit( { login : false, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' } ] }, '/shop' );
check( 'with several accounts the session is ended and even the one entry point is a link - with the directory in front', location.href === 'http://example.test/_admin/'
	&& byId('finish-done').classList.contains( 'install-hidden' ) === false && findAll( byId('finish-next-steps'), function( el ) { return el.dataset.application !== undefined } )[0].href === '/shop/_admin/#builder' );

submit( { login : false, targets : [] } );
check( '...and with none the page offers what it always offered', byId('finish-done').classList.contains( 'install-hidden' ) === false && findAll( byId('finish-next-steps'), function( el ) { return el.dataset.application !== undefined } ).length === 0 );

// A name the catalogue wrote is text, never markup
submit( { login : false, targets : [ { key : 'x', name : '<img src=x onerror=alert(1)>', next : '/x' } ] } );
const hostile = findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'STRONG' && el.parentNode.parentNode.dataset.application === 'x' } )[0];
check( 'the name of an application is written as text', hostile.textContent.includes( '<img src=x onerror=alert(1)>' ) && hostile.children.length === 0 );

// The next steps are words of the page too: another language words them again
text.set('de');
finish.retext();
check( 'the next steps the page adds are worded again in the other language', findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'STRONG' && el.parentNode.parentNode.dataset.application === 'x' } )[0].textContent === text._tables.de['/_admin/install/finish/set-up'].replace( '%s', '<img src=x onerror=alert(1)>' ) );
text.set('en');
finish.retext();

/*	An application that starts the workbench with its menu folded: the state the
	workbench keeps for it (Nino.admin.rail) is set to folded where nobody has
	chosen yet, once, and only for the application that is opened	*/
const RAIL = finish.RAIL_KEY;
const folded = { key : 'vcard', name : 'vCard', next : '/_admin/#vcard', menu : 'folded' };
const plainTarget = { key : 'website', name : 'Website', next : '/_admin/#dashboard' };

submit( { login : true, targets : [ plainTarget ] } );
check( 'an application that says nothing about the menu leaves the browser\'s state alone', Object.keys( stored ).filter( key => key !== text.STORAGE_KEY ).length === 0 );

submit( { login : true, targets : [ folded ] } );
check( 'one account and an application that folds the menu: the menu is folded before the page leaves, and the page leads to the entry point as for any other', stored[RAIL] === 'folded' && location.href === 'http://example.test/_admin/#vcard' );

stored[RAIL] = 'open';
submit( { login : true, targets : [ folded ] } );
check( '...a state somebody pinned is theirs and is not overwritten', stored[RAIL] === 'open' );
delete stored[RAIL];

submit( { login : false, targets : [ folded, plainTarget ] } );
check( 'where the page lists the entry points, nothing is folded before one of them is opened', stored[RAIL] === undefined );
const listed = findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'A' && el.dataset.application !== undefined } );
fire( listed[1], 'click' );
check( '...and opening one that says nothing leaves the state alone', stored[RAIL] === undefined );
fire( listed[0], 'click' );
check( '...opening the one that folds the menu folds it', stored[RAIL] === 'folded' );

delete stored[RAIL];
shut = true;
submit( { login : true, targets : [ folded ] } );
check( 'a browser that keeps its storage shut still leads to the entry point', location.href === 'http://example.test/_admin/#vcard' );
shut = false;

// What is wrong with the two passwords is said at them, before anything is sent
posted = null;
field('finish-pw').value = '';
field('finish-pw2').value = '';
finish.submit( function( ok, message ) { outcome = [ ok, message ] } );
check( 'two empty passwords are said at their fields, in the wording of the workbench, with the focus on the first, nothing sent and a sentence for the bar', posted === null && errors().length === 2 && field('finish-pw').focused === true && errors()[0] === en['/_admin/common/error/missing'].replace( '%s', en['/_admin/install/finish/pw'] ) && outcome[0] === false && outcome[1] === en['/_admin/install/error/fields'] );
field('finish-pw').value = 'a recovery password';
field('finish-pw2').value = 'a recovery passwore';
finish.submit( function() {} );
check( 'a repeat that differs posts nothing and is said at the repeat', posted === null && errors().join() === en['/_admin/install/error/mismatch'] && field('finish-pw2').getAttribute('aria-invalid') === 'true' && field('finish-pw2').focused === true );
field('finish-pw').value = 'short';
field('finish-pw2').value = 'short';
finish.submit( function() {} );
check( 'a password that is shorter than the length the page states is said at its field, with the length', posted === null && errors().join() === en['/_admin/install/error/password-short'].replace( '%s', '8' ) && field('finish-pw').focused === true );
field('finish-pw').value = 'a recovery password';
field('finish-pw2').value = 'a recovery password';

// What the server refuses about the password is said at the password, with the focus there, in the words of the table and with the number the server names
[ [ 'install_password_short', [], en['/_admin/page/error/install-password-short'].replace( '%s', '8' ) ], [ 'install_password_long', [ 72 ], en['/_admin/page/error/install-password-long'].replace( '%s', '72' ) ],
	[ 'install_password_invalid', [], en['/_admin/page/error/install-password-invalid'] ] ].forEach( function( [ code, params, sentence ] ) {
	answer = { status : 400, response : { error : 'English of the server', code : code, params : params } };
	field('finish-pw2').focused = true;
	field('finish-pw').focused = false;
	outcome = null;
	finish.submit( function( ok, message ) { outcome = [ ok, message ] } );
	check( 'a refusal "'+ code+ '" is said at the password field, with the focus there, and the bar is told to look at the fields', errors().join() === sentence && field('finish-pw').getAttribute('aria-invalid') === 'true' && field('finish-pw').focused === true && outcome[0] === false && outcome[1] === en['/_admin/install/error/fields'] );
} );
answer = { status : 500, response : { error : 'English of the server', code : 'install_recovery_write' } };
finish.submit( function( ok, message ) { outcome = [ ok, message ] } );
check( 'a refusal of the server is said in the line of the bar in the words of the table for its code - never the English of the server - and keeps what was typed', outcome[0] === false && outcome[1] === en['/_admin/page/error/install-recovery-write'] && field('finish-pw').value === 'a recovery password' );

/*	The recovery key: shown once, before anything else of the end, and gone when it is confirmed
	saved. The workbench or the entry point is walked into only after that	*/
const KEY = 'q1w2e3r4t5y6u7i8o9p0a1s2d3f4g5h6j7k8l9z0x1c=';
const clickChild = function( id ) { fire( byId( id ), 'click' ) };
let remembered = Object.keys( stored ).length;
const ack = function() { return byId('finish-key-ack') }, go = function() { return byId('finish-key-continue') };

submit( { login : true, targets : [], recoveryKey : KEY } );
check( 'where the answer holds the recovery key the key is on screen, and nothing else of the end: no way into the workbench, no done panel, no form', byId('finish-key-value').textContent === KEY
	&& byId('finish-key') !== undefined && location.href === 'http://example.test/_admin/' && byId('finish-done').classList.contains( 'install-hidden' ) === true
	&& byId('finish-content').classList.contains( 'install-hidden' ) === true );
check( '...the way on is a main button that is not off, and a page that is closed meanwhile asks first', go().disabled === false && go().className.includes('nino-admin-btn-primary') && typeof sandbox.onbeforeunload === 'function' );
check( '...and the line of the bar is cleared of "Saving"', said[said.length - 1][0] === '' );
check( '...what has to stay in view of it is said as a notice - it is shown only now - and the rest behind the (?) of the card', findAll( byId('finish-key'), el => el.className === 'nino-admin-notice' ).length === 1 && findAll( byId('finish-key'), el => el.className === 'nino-admin-help' ).length === 1 );
check( '...the tick that says it was saved is a field of the blocks, with a star, and has the focus', ack().type === 'checkbox' && ack().getAttribute('aria-required') === 'true' && ack().focused === true );
fire( byId('finish-key'), 'submit' );
check( 'the form does not go on while the box is not ticked: it says so at the box, which keeps the focus, and the key stays', location.href === 'http://example.test/_admin/' && byId('finish-key-value').textContent === KEY
	&& findAll( byId('finish-key'), el => el.className === 'nino-admin-field-error' ).map( el => el.textContent ).join() === en['/_admin/install/finish/key-ack-error'] && ack().getAttribute('aria-invalid') === 'true' && ack().focused === true );

// Copy and the text file
clickChild('finish-key-copy');
check( 'Copy puts the key on the clipboard, and says so by the toast', copied.length === 1 && copied[0] === KEY && findAll( doc.body, el => el.textContent === en['/_admin/install/finish/key-copied'] ).length >= 1 );
copied.length = 0;
clipboardWorks = false;
clickChild('finish-key-copy');
check( '...and where the browser refuses it says to copy it by hand instead, in the line of the card', byId('finish-key-msg').textContent === en['/_admin/install/finish/key-copy-failed'] && byId('finish-key-msg').getAttribute('role') === 'alert' );
clipboardWorks = true;
const savedBefore = made.length;
clickChild('finish-key-save');
check( 'Save as text file makes the file in the browser, of the key and a line break and nothing else', blobs.length === 1 && blobs[0].parts.join('') === KEY+ '\n' && blobs[0].options.type === 'text/plain' && made.length === savedBefore + 2 && made[savedBefore] !== 'revoked' && made[savedBefore + 1] === 'revoked' );
check( '...and nothing of the key is anywhere in the browser besides the page: no storage, no address', Object.keys( stored ).length === remembered && location.href.indexOf( KEY ) === -1 && Object.values( stored ).every( value => value.indexOf( KEY ) === -1 ) );

// The language is changed while the key is shown: the page is worded again and the key is where it was
text.set('de');
finish.retext();
check( 'a change of the language while the key is shown words the screen again in that language, and the key stays', byId('finish-key-value').textContent === KEY && byId('finish-key-copy').textContent === text._tables.de['/_admin/install/finish/key-copy'] );
check( '...and the sentence at the tick, said in that language, and its mark, stay', findAll( byId('finish-key'), el => el.className === 'nino-admin-field-error' ).map( el => el.textContent ).join() === text._tables.de['/_admin/install/finish/key-ack-error'] && ack().getAttribute('aria-invalid') === 'true' );
ack().checked = true;
text.set('en');
finish.retext();
check( '...and so does the tick that was set', ack().checked === true && byId('finish-key-value').textContent === KEY );

ack().checked = true;
fire( byId('finish-key'), 'submit' );
check( 'confirmed, the key is gone from the page and its box, the way on is walked as it always was: one account, no entry point - the workbench', byId('finish-key') === undefined && findAll( root, el => el.textContent === KEY ).length === 0
	&& sandbox.onbeforeunload === null && location.href === 'http://example.test/_admin/' );

// With several accounts: after the key, the done panel
submit( { login : false, targets : [ { key : 'website', name : 'Website', next : '/_admin/#builder' } ], recoveryKey : KEY } );
check( 'with several accounts the done panel waits for the key as well', byId('finish-done').classList.contains( 'install-hidden' ) === true && byId('finish-key-value').textContent === KEY );
ack().checked = true;
fire( byId('finish-key'), 'submit' );
check( '...and shows the next steps once it is confirmed, the key gone', byId('finish-done').classList.contains( 'install-hidden' ) === false && findAll( root, el => el.textContent === KEY ).length === 0
	&& findAll( byId('finish-next-steps'), function( el ) { return el.tagName === 'A' && el.dataset.application !== undefined } ).length === 1 );

// A key is text, never markup
submit( { login : false, targets : [], recoveryKey : '<img src=x onerror=alert(1)>' } );
check( 'the key is written as text', byId('finish-key-value').textContent === '<img src=x onerror=alert(1)>' && byId('finish-key-value').children.length === 0 );
ack().checked = true;
fire( byId('finish-key'), 'submit' );
check( 'once the key is not needed any more it is nowhere: not in the memory of the step, not on the page', finish._shown === null && byId('finish-key') === undefined && findAll( root, el => String( el.textContent ).includes( '<img src=x onerror=alert(1)>' ) ).length === 0 );

// No key in the answer: the end is what it was
submit( { login : false, targets : [] } );
check( 'an answer without a key shows no key screen and ends as it did', byId('finish-key') === undefined && byId('finish-done').classList.contains( 'install-hidden' ) === false );

// The key is the one the workbench keeps its menu under
check( 'the wizard names the key the workbench stores the menu state under', source('_admin/assets/script.js').includes( "STORAGE_KEY : '"+ RAIL+ "'" ) );

done();
