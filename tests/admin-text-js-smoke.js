/**
 *	Nino									A compact filesystembased php framework
 *	admin-text-js-smoke.js	DOM-free checks for the Text editor's
 *										multi-locale save planning, the shared key model
 *										(rows, sections, search, page details), the
 *										editor's list and form on a fake DOM, and the Text
 *										Keys tab's scan form - which answers three
 *										questions per key and must send all three, or a
 *										row the operator meant to retire quietly comes
 *										back - and its segment form.
 *
 *	Usage: node tests/admin-text-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
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

const sandbox = {
	console : console,
	document : { documentElement : null, body : null },
};
sandbox.window = sandbox;
sandbox.Nino = {
	editor : {},
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/assets/admin.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'text.js' }
);

const text = sandbox.Nino.admin.text;

text._dirtyLocales = [ 'de_DE', 'en_US' ];
text._selectedLocale = 'en_US';
check( 'Save includes every locale edited before the visible one', JSON.stringify( text._saveLocales() ) === JSON.stringify( [ 'de_DE', 'en_US' ] ) );

text._dirtyLocales = [];
check( 'the visible locale carries a global-only save', JSON.stringify( text._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );

text._dirtyLocales = [ 'en_US' ];
check( 'the visible locale is not queued twice', JSON.stringify( text._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );


// --- the scan form's three answers ----------------------------------------
//
// A row is created (it has a value), left for later (it is empty) or retired
// (it is ticked). Which one is the server's call, so the request has to carry
// every row - the version before this filtered the ignored ones out client-
// side, which is exactly why an ignored key came back on the next scan.

const keysSandbox = {
	console : console,
	document : { documentElement : null, body : null, getElementById : function() { return { textContent : '' } } },
};
keysSandbox.window = keysSandbox;
keysSandbox.Nino = {
	admin : {},
	content : { getText : function( key ) { return key } },
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/assets/keys.js' ), 'utf8' ),
	vm.createContext( keysSandbox ),
	{ filename : 'keys.js' }
);

const keys = keysSandbox.Nino.admin.keys;

let sent = null;
keys._apiCall = function( endpoint, payload ) { sent = { endpoint : endpoint, payload : payload } };

keys._saveScanResults( [
	{ key : '/a/one', 	valueInput : { value : 'Value' }, ignoreCheck : { checked : false } },
	{ key : '/a/two', 	valueInput : { value : '' }, 			ignoreCheck : { checked : false } },
	{ key : '/a/three', valueInput : { value : '' }, 			ignoreCheck : { checked : true 	} },
] );

check( 'the whole form goes to the server in one request', sent !== null && sent.endpoint === 'scanapply' && sent.payload.rows.length === 3 );
check( '...including the rows left empty, which the server passes over', sent.payload.rows[1].key === '/a/two' && sent.payload.rows[1].value === '' && sent.payload.rows[1].ignore === false );
check( '...and the ignored ones, which is how they get retired at all', sent.payload.rows[2].key === '/a/three' && sent.payload.rows[2].ignore === true );
check( 'a filled row carries its value', sent.payload.rows[0].value === 'Value' );

// A row the scan lists without an input - a key off the grammar, which is
// renamed in the template and can only be ignored - sends no value, and
// still carries its ignore. A key of the system has no row to send at all
keys._saveScanResults( [
	{ key : '/foo/bar', valueInput : null, ignoreCheck : { checked : true } },
	{ key : '/foo/baz', valueInput : null, ignoreCheck : { checked : false } },
] );
check( 'a row without an input sends an empty value and its ignore', sent.payload.rows.length === 2 && sent.payload.rows[0].value === '' && sent.payload.rows[0].ignore === true && sent.payload.rows[1].ignore === false );
keys._view = 'scan';
keys._scanRows = [ { key : '/foo/bar', valueInput : null, ignoreCheck : { checked : false } } ];
keysSandbox.document.getElementById = function() { return { classList : { contains : function() { return false } } } };
check( 'a form whose rows have no input is not dirty until one is ticked', keys.isDirty() === false );
keys._scanRows[0].ignoreCheck.checked = true;
check( '...and is then', keys.isDirty() === true );
keys.discard();
check( '...and discarding unticks it without an input to empty', keys._scanRows[0].ignoreCheck.checked === false );
keys._view = 'group';
keysSandbox.document.getElementById = function() { return { textContent : '' } };

// What the scan says about a key it cannot create, or about where one comes from: a fill, in the words of the workbench
keysSandbox.Nino.adminUi = { format : function( text, a ) { return text.replace( '%s', a ) } };
check( 'a key that is simply to be created has no note', keys._scanNote( { kind : 'create', hint : null } ) === '' );
check( 'a key off the grammar says so', keys._scanNote( { kind : 'grammar' } ) === '/_admin/keys/scan/grammar' );
check( 'a key of the system says who writes it - the Routes panel, the Language panel - or that nobody does', keys._scanNote( { kind : 'system', writer : 'routes' } ) === '/_admin/keys/scan/system-routes'
	&& keys._scanNote( { kind : 'system', writer : 'language' } ) === '/_admin/keys/scan/system-language' && keys._scanNote( { kind : 'system', writer : null } ) === '/_admin/keys/scan/system-none' );
check( 'a /feature or /module key says whose it normally is', keys._scanNote( { kind : 'create', hint : 'feature', owner : 'consent' } ) === '/_admin/keys/scan/hint-feature'
	&& keys._scanNote( { kind : 'create', hint : 'module', owner : 'form' } ) === '/_admin/keys/scan/hint-module' );
// The fills it asks for are put together at runtime ('system-<writer>', 'hint-<kind>'), which the static check of
// tests/admin-system-smoke.php cannot see: both languages carry every one
[ 'en_US', 'de_DE' ].forEach( function( locale ) {
	const words = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/text/'+ locale+ '.php' ), 'utf8' );
	const has = key => words.includes( "[[/_admin/keys/scan/"+ key+ "]]'" );
	check( locale+ ': every note of the scan, and the list of keys other templates read as well, has its words',
		[ 'grammar', 'system-routes', 'system-language', 'system-none', 'hint-feature', 'hint-module', 'also-title', 'also-hint', 'also-line' ].every( has ) && words.includes( "[[/_admin/error/keys_system]]'" ) );
} );

const keysSource = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/assets/keys.js' ), 'utf8' );
// Three outcomes are not obvious from three controls, so the form says them
check( 'the form explains what an empty row and an ignored row each mean',
	keysSource.includes( "Nino.content.getText('/_admin/keys/scan/hint')" ) );
check( '...and the checkbox says the ignore is permanent',
	keysSource.includes( "Nino.content.getText('/_admin/keys/label/scan-ignore')" ) );
// The old flow ended in an alert() built from a hardcoded English sentence -
// the one string in this module that no locale file could reach
check( 'the outcome is reported as a fill rather than an untranslatable alert',
	keysSource.includes( "Nino.content.getText('/_admin/keys/scan/result')" ) && keysSource.includes( "' created. Failed: '" ) === false );


// --- a key this account may not write -------------------------------------
//
// apiKeys() flags each key; the panel shows what it may not change as
// read-only. The flag is absent for anyone unrestricted, so "not false" is
// the test - "=== true" would lock every key of every install that has no
// scoped permissions at all.

check( 'a key with no flag is writable', text._writable( { key : '/a/one' } ) === true );
check( 'a key flagged writable is writable', text._writable( { key : '/a/one', writable : true } ) === true );
check( 'a key flagged unwritable is not', text._writable( { key : '/a/one', writable : false } ) === false );

const textSource = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/assets/admin.js' ), 'utf8' );
check( 'a read-only key is shown rather than hidden',
	textSource.includes( "view.className = 'admin-text-readonly'" ) );
// Snapshotting a field that isn't there reads '' back and would overwrite the
// real value on the next locale switch
check( 'read-only keys are left out of the locale snapshot and the save',
	textSource.split( '&& Nino.admin.text._writable( e ) === true' ).length === 4 );
// ...and nowhere else: filtering them out of the render too would hide the
// text a writable field is being edited next to, which is the point of
// editing a whole row at once
const renderForm = textSource.slice( textSource.indexOf( '_renderGroupForm : function()' ), textSource.indexOf( '_save : function' ) );
check( '...but still rendered, so the row reads as one list',
	renderForm.includes( 'section.fields.forEach( function( field ) {' ) && renderForm.includes( 'Nino.admin.text._renderKeyField( entry, value, field.label )' )
	&& renderForm.includes( '_writable( e ) === true } );' ) === true && renderForm.split( '_writable(' ).length === 2 );
check( 'a group with nothing writable in it is not offered a Save button',
	textSource.includes( 'if( writable === true ) {' ) );


// --- unsaved input: what the shell asks about -------------------------------
//
// Both forms hold input in controls the stored values do not mirror: the
// translation on screen, the other translations edited and left, the global
// fields. A control is compared with what it held when it was drawn - a
// rich-text field reads back as markup that is not always the stored string,
// so comparing with the stored value would make a mere visit look like an edit.

const shown = { hidden : false, forms : true };
const formNode = { classList : { contains : function() { return shown.hidden } } };
sandbox.document.getElementById = function( id ) { return ( id === 'text-form' ) ? formNode : ( id === 'text-edit-form' && shown.forms === true ? {} : null ) };
sandbox.Nino.admin.sessionLocale = { current : null };

text._groups = { g : [
	{ key : '/g/title', global : true, writable : true },
	{ key : '/g/body', global : false, writable : true },
	{ key : '/g/fixed', global : false, writable : false },
] };
text._currentGroup = 'g';
text._fieldEls = { '/g/title' : { value : 'Title' }, '/g/body' : { value : 'Body' }, '/g/fixed' : { value : 'Fixed' } };
text._htmlEditors = {};
text._dirtyLocales = [];
text._captureBaseline( true );

check( 'a group nobody touched holds nothing unsaved', text.isDirty() === false );
text._fieldEls['/g/title'].value = 'Title!';
check( 'an edited global field is unsaved input', text.isDirty() === true );
text._fieldEls['/g/title'].value = 'Title';
check( '...and typing it back takes that away', text.isDirty() === false );
text._fieldEls['/g/body'].value = 'Body!';
check( 'so is an edited field of the translation on screen', text.isDirty() === true );
text._fieldEls['/g/body'].value = 'Body';
text._fieldEls['/g/fixed'].value = 'Changed';
check( 'a key this account may not write is nothing it could save', text.isDirty() === false );
text._dirtyLocales = [ 'de_DE' ];
check( 'a translation that was edited and left is unsaved input', text.isDirty() === true );
shown.hidden = true;
check( 'a form that is not on screen holds nothing the person could lose track of', text.isDirty() === false );
shown.hidden = false;
shown.forms = false;
check( 'a list has nothing typed into it', text.isDirty() === false );
shown.forms = true;
text._fieldEls['/g/title'].value = 'Title!';
text.discard();
check( 'discarding forgets the edited translations and takes the form as it stands for the saved one', text._dirtyLocales.length === 0 && text.isDirty() === false );

// ...but not while a save runs: it finishes with what it was given
text._dirtyLocales = [ 'de_DE' ];
text._localeValues = { de_DE : { '/g/body' : 'Typed' } };
text._saving = true;
text.discard();
check( 'discarding while a save runs leaves the edited translations and their values alone', JSON.stringify( text._dirtyLocales ) === '["de_DE"]' && text._localeValues.de_DE['/g/body'] === 'Typed' );
text._saving = false;
text.discard();
check( '...and once it has ended discards again', text._dirtyLocales.length === 0 && JSON.stringify( text._localeValues ) === '{}' );

text._selectedLocale = 'en_US';
text._localeValues = { en_US : { '/g/body' : 'stored' } };
text._baseline.locale = { '/g/body' : 'Body' };
text._storeVisibleLocaleFields();
check( 'merely visiting a translation does not mark it, even where the stored string differs from what the control reads back', text._dirtyLocales.length === 0 );
text._fieldEls['/g/body'].value = 'Edited';
text._storeVisibleLocaleFields();
check( '...an edit does', JSON.stringify( text._dirtyLocales ) === '["en_US"]' );

check( 'the Text panel registers with the shell behind a check that the shell has the registry',
	/if\( typeof Nino\.admin\.dirty === 'object' \)\s*Nino\.admin\.dirty\.register\( 'text', \{\s*isDirty : Nino\.admin\.text\.isDirty,\s*save\s*: function\( done \) \{ Nino\.admin\.text\._save\( done \) \},\s*discard : Nino\.admin\.text\.discard,/.test( textSource ) === true );

// --- the Keys tab: a save writes every language edited --------------------
//
// One batch per translation, one after the other, the global keys with the
// first only; a failure stops the loop and leaves the rest edited; the
// confirmation comes once, after the last.

const msg = { textContent : '' };
const pendingControls = [ { disabled : false }, { disabled : false } ];
const pendingLinks = [
	{ attrs : {}, style : {}, closest : function() { return null }, setAttribute : function( n, v ) { this.attrs[n] = v } },
	// a link inside an html editor: the editor's content, which its value reads back
	{ attrs : {}, style : {}, closest : function() { return {} }, setAttribute : function( n, v ) { this.attrs[n] = v } },
];
const pendingEditable = [ { contentEditable : 'true', attrs : {}, setAttribute : function( n, v ) { this.attrs[n] = v } } ];
const keysForm = {
	classList : { contains : function() { return false } },
	querySelectorAll : function( selector ) {
		return selector === 'input, textarea, select, button' ? pendingControls : ( selector === 'a' ? pendingLinks : ( selector === '[contenteditable]' ? pendingEditable : [] ) );
	},
};
const keysView = { forms : true };
keysSandbox.document.getElementById = function( id ) {
	if( id === 'keys-form-msg' ) return msg;
	if( id === 'keys-form' ) return keysForm;
	if( id === 'keys-edit-form' ) return keysView.forms === true ? {} : null;
	return { textContent : '' };
};
keysSandbox.confirm = function() { return true };
keysSandbox.Nino.content.getText = function( key ) { return key === '/_admin/keys/error/save-partial' ? 'partial: %s' : key };
keysSandbox.Nino.adminUi = {
	api : { errorText : function( status ) { return 'error '+ status } },
	format : function( text, ...params ) { let at = 0; return String( text ).replace( /%[sdn]/g, function( token ) { return at < params.length ? String( params[at++] ) : token } ) },
};
keys._renderCategoryList = function() { keysSandbox.listDrawn = ( keysSandbox.listDrawn ?? 0 ) + 1 };

keys._groups = { g : [
	{ key : '/g/title', global : true, values : { '*' : 'Title' } },
	{ key : '/g/body', global : false, values : { de_DE : 'Text', en_US : 'Body' } },
] };
keys._currentGroup = 'g';
keys._locales = [ 'de_DE', 'en_US' ];
keys._selectedLocale = 'de_DE';
keys._localeValues = {};
keys._dirtyLocales = [];
keys._fieldEls = { '/g/title' : { value : 'Title' }, '/g/body' : { value : 'Text' } };
keys._htmlEditors = {};
keys._view = 'group';
keys._captureBaseline( true );

check( 'a category nobody touched holds nothing unsaved', keys.isDirty() === false );
keys._storeVisibleLocaleFields();
check( '...and looking at a translation does not edit it', keys._dirtyLocales.length === 0 );
keys._fieldEls['/g/body'].value = 'Text!';
keys._storeVisibleLocaleFields();
check( 'an edited translation is tracked', JSON.stringify( keys._dirtyLocales ) === '["de_DE"]' && keys.isDirty() === true );
keys._selectedLocale = 'en_US';
keys._baseline.locale = { '/g/body' : 'Body' };
keys._fieldEls['/g/body'].value = 'Body!';
keys._storeVisibleLocaleFields();
check( '...in the order they were edited', JSON.stringify( keys._dirtyLocales ) === '["de_DE","en_US"]' && JSON.stringify( keys._saveLocales() ) === '["de_DE","en_US"]' );
check( 'one edited translation is written once, not twice', ( function() { keys._storeVisibleLocaleFields(); return keys._dirtyLocales.length === 2 } )() );

const requests = [];
keys._apiCall = function( endpoint, payload, callback ) { requests.push( { endpoint : endpoint, payload : payload, callback : callback } ) };
const answers = function( request, failedKey ) {
	const results = {};
	request.payload.items.forEach( function( item ) { results[item.key] = item.key === failedKey ? { ok : false } : { ok : true, value : item.value+ '.' } } );
	request.callback( 200, { results : results } );
};

let ended = [];
keys._save( function( ok ) { ended.push( ok ) } );
check( 'a save sends the first edited translation with the global keys', requests.length === 1 && requests[0].endpoint === 'savebatch'
	&& requests[0].payload.items.map( function( i ) { return i.key+ '@'+ i.locale+ '='+ i.value } ).join() === '/g/title@*=Title,/g/body@de_DE=Text!' );
check( '...the form is held while it runs', keys._saving === true && pendingControls.every( function( c ) { return c.disabled === true } ) && pendingLinks[0].attrs['aria-disabled'] === 'true' && pendingEditable[0].contentEditable === 'false' );
check( '...but the links of a rich text are left alone, so what the editor returns is what it held', Object.keys( pendingLinks[1].attrs ).length === 0 && Object.keys( pendingLinks[1].style ).length === 0 );
let blocked = null;
keys._save( function( ok ) { blocked = ok } );
check( '...and a second submit while it runs sends nothing and says no', requests.length === 1 && blocked === false );
keys.discard();
check( '...and discarding while it runs leaves the translations it still has to send, and their values, alone', JSON.stringify( keys._dirtyLocales ) === '["de_DE","en_US"]' && Object.keys( keys._localeValues ).length > 0 );

answers( requests[0] );
check( 'the next translation follows once the first is written - without the global keys again, and with nothing said yet',
	requests.length === 2 && requests[1].payload.items.map( function( i ) { return i.key+ '@'+ i.locale+ '='+ i.value } ).join() === '/g/body@en_US=Body!'
	&& msg.textContent === '/_admin/common/msg/saving' && ended.length === 0 && JSON.stringify( keys._dirtyLocales ) === '["en_US"]' );
answers( requests[1] );
check( 'the confirmation comes once, after the last translation, with the form given back and the list redrawn',
	msg.textContent === '/_admin/common/msg/saved' && ended.join() === 'true' && keys._saving === false && keys._dirtyLocales.length === 0
	&& pendingControls.every( function( c ) { return c.disabled === false } ) && keysSandbox.listDrawn === 1 );
check( '...what the server answered is what the model holds now', keys._groups.g[1].values.de_DE === 'Text!.' && keys._groups.g[1].values.en_US === 'Body!.' && keys._localeValues.de_DE['/g/body'] === 'Text!.' );
check( '...and the controls count as saved', keys.isDirty() === false );

// a request that fails
requests.length = 0;
keys._dirtyLocales = [ 'de_DE', 'en_US' ];
keys._localeValues = { de_DE : { '/g/body' : 'A' }, en_US : { '/g/body' : 'B' } };
ended = [];
keys._save( function( ok ) { ended.push( ok ) } );
requests[0].callback( 500, null );
check( 'a failed request stops the loop: nothing further is sent, every translation stays edited, the message names the language',
	requests.length === 1 && ended.join() === 'false' && JSON.stringify( keys._dirtyLocales ) === '["de_DE","en_US"]' && keys._saving === false
	&& msg.textContent.indexOf('(de_DE)') !== -1 && pendingControls.every( function( c ) { return c.disabled === false } ) );

// a key the server did not accept
requests.length = 0;
ended = [];
keys._save( function( ok ) { ended.push( ok ) } );
answers( requests[0] );
answers( requests[1], '/g/body' );
check( 'a key the server refused stops the loop after the translations that went through; the rest stays edited and the message says key (language)',
	ended.join() === 'false' && JSON.stringify( keys._dirtyLocales ) === '["en_US"]' && msg.textContent.indexOf('/g/body (en_US)') !== -1 );
check( '...and the global keys went out once', requests[0].payload.items.some( function( i ) { return i.locale === '*' } ) && requests[1].payload.items.every( function( i ) { return i.locale !== '*' } ) );

// a result the answer does not contain counts as refused as well
requests.length = 0;
keys._dirtyLocales = [ 'de_DE' ];
ended = [];
keys._save( function( ok ) { ended.push( ok ) } );
requests[0].callback( 200, { results : {} } );
check( 'a key the answer says nothing about is not taken for written', ended.join() === 'false' && JSON.stringify( keys._dirtyLocales ) === '["de_DE"]' );

// only globals changed: the visible translation carries them
requests.length = 0;
keys._dirtyLocales = [];
keys._selectedLocale = 'de_DE';
keys._save();
check( 'with nothing but a global key changed, the visible translation is the one request that carries it', requests.length === 1 && requests[0].payload.items.some( function( i ) { return i.locale === '*' } ) );
requests[0].callback( 500, null );

// the other forms of the tab: the one that creates a key or renames one asks for its four segments
keys._view = 'new';
keys._formInitial = JSON.stringify( { namespace : 'project', category : '', part : '', name : '' } );
const segmentForm = ( segments, extra ) => function( id ) {
	const fields = Object.assign( { 'keys-form': keysForm, 'keys-form-namespace' : { value : segments.namespace }, 'keys-form-category' : { value : segments.category }, 'keys-form-part' : { value : segments.part }, 'keys-form-name' : { value : segments.name },
		'keys-form-new-value' : { value : '' }, 'keys-form-new-global' : { checked : false } }, extra );
	return fields[id] ?? { textContent : '' };
};
keysSandbox.document.getElementById = segmentForm( { namespace : 'project', category : '', part : '', name : '' } );
check( 'a form of /project and nothing else holds nothing', keys.isDirty() === false );
keysSandbox.document.getElementById = segmentForm( { namespace : 'project', category : 'company', part : '', name : '' } );
check( '...a segment typed into it does', keys.isDirty() === true );
keysSandbox.document.getElementById = segmentForm( { namespace : 'template', category : 'common', part : '', name : '' } );
check( '...and so does another namespace', keys.isDirty() === true );
keys._formInitial = JSON.stringify( { namespace : 'project', category : 'company', part : 'general', name : 'name' } );
keysSandbox.document.getElementById = segmentForm( { namespace : 'project', category : 'company', part : 'general', name : 'name' } );
check( 'a form that renames holds nothing while it shows the key\'s own segments', keys.isDirty() === false );
keysSandbox.document.getElementById = segmentForm( { namespace : 'project', category : 'company', part : 'general', name : 'title' } );
check( '...and something once one is changed', keys.isDirty() === true );
let redrawn = 0;
keys._renderNewKeyForm = function() { redrawn++ };
keys.discard();
check( 'discarding draws the form again as it was opened', redrawn === 1 );
keys._view = 'scan';
keys._scanRows = [ { valueInput : { value : '' }, ignoreCheck : { checked : false } }, { valueInput : { value : '' }, ignoreCheck : { checked : false } } ];
check( 'a scan form with every row as it was drawn holds nothing', keys.isDirty() === false );
keys._scanRows[1].valueInput.value = 'x';
check( '...a value typed into a row does', keys.isDirty() === true );
keys._scanRows[1].valueInput.value = '';
keys._scanRows[0].ignoreCheck.checked = true;
check( '...so does a row ticked as ignored', keys.isDirty() === true );
keys.discard();
check( 'discarding empties the scan form', keys.isDirty() === false );
keys._view = 'group';

// the exits that reload the tab ask first - and so does the one that leaves the row for the form that renames
const asked = [];
keysSandbox.Nino.admin.dirty = { guard : function( names, proceed, onCancel ) { asked.push( { names : names, proceed : proceed, onCancel : onCancel } ) }, refresh : function() {}, register : function() {} };
requests.length = 0;
let undone = 0;
let opened = 0;
keys._showForm = function() { opened++ };
keys._saveSchema( '/g/title', true, false, function() { undone++ } );
keys._openNewKeyForm( '/g/title' );
keys._deleteKey( '/g/title' );
check( 'a schema change, the form that renames and a delete ask about the open row before anything happens', asked.length === 3 && requests.length === 0 && opened === 0 && asked.every( function( a ) { return JSON.stringify( a.names ) === '["keys"]' } ) );
asked[0].onCancel();
check( '...a Cancel on a schema checkbox puts the box back', undone === 1 && requests.length === 0 );
asked[0].proceed();
asked[1].proceed();
asked[2].proceed();
check( '...an answer lets each request go, and opens the form with the key to rename', requests.map( function( r ) { return r.endpoint } ).join() === 'save,delete' && opened === 1 && keys._renameFrom === '/g/title' && keys._view === 'new' );
delete keysSandbox.Nino.admin.dirty;
opened = 0;
keys._openNewKeyForm( '/g/title' );
check( 'a shell without the registry asks nothing', opened === 1 && keys._renameFrom === '/g/title' );
keys._openNewKeyForm();
check( 'the form that creates a key leaves the list, which has nothing to lose, without asking', opened === 2 && keys._renameFrom === null && keys._isNew === true );
keys._view = 'group';

// --- a key's format and limit ---------------------------------------------
//
// Applied together with the two checkboxes through keys/save, which leaves a
// setting out as "unchanged": the checkboxes alone must keep posting without
// them. A format that holds less than the one before converts every stored
// text, so it is asked about first - and the question names the conversion.

check( 'the Keys tab knows the four formats, narrowest first, in the order the server states them', JSON.stringify( keys.FORMATS ) === '["plain","inline","lines","blocks"]' );

const confirms = [];
keysSandbox.window.confirm = function( question ) { confirms.push( question ); return true };
keysSandbox.window.alert = function() {};
const plainWords = keysSandbox.Nino.content.getText;
keysSandbox.Nino.content.getText = function( key ) { return key === '/_admin/keys/confirm/format' ? 'Change %s from %s to %s? %s' : plainWords( key ) };
keys._currentGroup = 'g';
requests.length = 0;

keys._saveSettings( { key : '/g/body', format : 'lines' }, false, false, 'blocks', '400' );
check( 'applying posts the key, both checkboxes, the format and the limit as one keys/save', requests.length === 1 && requests[0].endpoint === 'save'
	&& JSON.stringify( requests[0].payload ) === JSON.stringify( { key : '/g/body', global : false, blacklisted : false, format : 'blocks', maxlength : 400 } ) );
check( '...widening asks nothing', confirms.length === 0 );

keys._saveSettings( { key : '/g/body', format : 'lines' }, true, true, 'auto', '' );
check( 'a limit left empty is posted as null - automatic - and "auto" as itself', JSON.stringify( requests[1].payload ) === JSON.stringify( { key : '/g/body', global : true, blacklisted : true, format : 'auto', maxlength : null } ) && confirms.length === 0 );

keys._saveSettings( { key : '/g/body', format : 'blocks' }, false, false, 'plain', '' );
check( 'narrowing asks first, naming the key, the two formats and what becomes of the text', confirms.length === 1 && confirms[0].includes( '/g/body' ) && confirms[0].includes( '/_admin/keys/format/blocks' ) && confirms[0].includes( '/_admin/keys/format/plain' ) && confirms[0].includes( '/_admin/keys/convert/plain' ) === true );
keysSandbox.window.confirm = function( question ) { confirms.push( question ); return false };
keys._saveSettings( { key : '/g/body', format : 'blocks' }, false, false, 'inline', '' );
check( '...and a No sends nothing', requests.length === 3 );
keysSandbox.window.confirm = function( question ) { confirms.push( question ); return true };
keys._saveSettings( { key : '/g/body', format : 'lines' }, false, false, 'lines', '' );
check( 'the format a key already has is no change to ask about', confirms.length === 2 && requests.length === 4 );

requests[0].callback( 200, { ok : true } );
check( 'a success comes back to the category it was made in', keys._reopen === 'g' );
keys._reopen = null;
requests[1].callback( 400, { error : 'x', code : 'keys_limit' } );
check( 'a refusal says so and reloads nothing', keys._reopen === null );

keysSandbox.Nino.content.getText = plainWords;
check( 'the controls are drawn from the entry: a format select with "auto" first, a limit input, an Apply button',
	keysSource.includes( "[ 'auto' ].concat( Nino.admin.keys.FORMATS )" ) && keysSource.includes( "className = 'admin-text-limit-input'" ) && keysSource.includes( "getText('/_admin/keys/label/apply')" ) );
check( 'the format and the limit show what is set: the stored format or "auto", the limit or empty', keysSource.includes( "entry.formatSet === true ? entry.format : 'auto'" ) && keysSource.includes( "entry.maxlengthSet === true ? String( entry.maxlength ) : ''" ) );
check( 'both editors are handed the key\'s format - the Text panel and this tab', /htmlEditor\.create\( mount, value \?\? '', entry\.maxlength, 0, entry\.format \)/.test( keysSource )
	&& /htmlEditor\.create\( mount, value \?\? '', entry\.maxlength, 0, entry\.format \)/.test( fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/assets/admin.js' ), 'utf8' ) ) );
check( 'the group is opened again once the list has loaded', /const reopen = Nino\.admin\.keys\._reopen;[\s\S]{0,300}Nino\.admin\.keys\._openGroup\( reopen \)/.test( keysSource ) );

// keys.js builds '/_admin/keys/format/<format>' and '/_admin/keys/convert/<format>' at runtime, which
// the static fill check of tests/admin-system-smoke.php cannot see: both languages carry every one.
// A conversion is asked about only when it narrows, so 'blocks' has no convert text
[ 'en_US', 'de_DE' ].forEach( function( locale ) {
	const words = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Text/text/'+ locale+ '.php' ), 'utf8' );
	const has = key => words.includes( "[[/_admin/keys/"+ key+ "]]'" );
	check( locale+ ': every format the select offers has a name, and every format a conversion can narrow to says what happens to the text',
		[ 'auto' ].concat( keys.FORMATS ).every( format => has( 'format/'+ format ) ) && keys.FORMATS.filter( format => format !== 'blocks' ).every( format => has( 'convert/'+ format ) ) );
} );

const keysRegistration = keysSource.indexOf( "Nino.admin.dirty.register( 'keys'" );
check( 'the Keys tab registers with the shell behind a check that the shell has the registry', keysRegistration !== -1 && keysSource.slice( 0, keysRegistration ).trimEnd().endsWith( "if( typeof Nino.admin.dirty === 'object' )" ) );

// ============================================================================
// Finding and reading texts: the names of a key, the rows they fall into, the
// search, and the two panels that draw them
// ============================================================================

// The words of the workbench in one language, as the fills the panels ask for -
// read from the files themselves, so what is checked is what ships
function workbenchWords( locale ) {
	const out = {};
	for( const file of [ '_admin/text/', '_admin/Nino/Modules/Text/text/' ] ) {
		const source = fs.readFileSync( path.join( __dirname, '..', file+ locale+ '.php' ), 'utf8' );
		for( const m of source.matchAll( /'\[\[(\/_admin\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g ) )
			out[m[1]] = m[2].replace( /\\'/g, "'" );
	}
	return out;
}

// A dom of plain objects, enough to draw the panels into and read them back
function fakeDom() {

	const matchOne = ( node, selector ) => {
		const parts = selector.trim().match( /(#[\w-]+|\.[\w-]+|\[[\w-]+(?:="[^"]*")?\]|^[a-z0-9]+)/g ) ?? [];
		return parts.length > 0 && parts.every( part => {
			if( part[0] === '#' ) return node.id === part.slice( 1 );
			if( part[0] === '.' ) return node.classList.contains( part.slice( 1 ) );
			if( part[0] === '[' ) {
				const m = /\[([\w-]+)(?:="([^"]*)")?\]/.exec( part );
				const value = m[1].startsWith('data-') ? node.dataset[m[1].slice( 5 )] : ( node.attrs[m[1]] ?? ( node[m[1]] === '' ? undefined : node[m[1]] ) );
				return m[2] === undefined ? value !== null && value !== undefined : String( value ) === m[2];
			}
			return node.tagName === part;
		} );
	};
	// A selector may be a path - "legend code": every part matches an ancestor, in order
	const matchPath = ( node, selector ) => {
		const parts = selector.trim().split( /\s+/ );
		if( matchOne( node, parts.pop() ) === false )
			return false;
		let up = node.parentNode;
		while( parts.length > 0 ) {
			const want = parts[parts.length - 1];
			while( up && matchOne( up, want ) === false ) up = up.parentNode;
			if( ! up ) return false;
			parts.pop();
			up = up.parentNode;
		}
		return true;
	};
	const matches = ( node, selector ) => selector.split(',').some( one => matchPath( node, one ) );
	const walk = ( node, out = [] ) => { node.children.forEach( child => { out.push( child ); walk( child, out ) } ); return out };

	const make = tag => {
		let classes = new Set();
		const node = {
			tagName : tag, children : [], parentNode : null, attrs : {}, dataset : {}, listeners : {}, style : {},
			id : '', value : '', checked : false, disabled : false, hidden : false, type : '', href : '', readOnly : false, required : false, selected : false, focused : false, _text : '',
			classList : {
				add : ( ...k ) => k.forEach( c => classes.add( c ) ), remove : ( ...k ) => k.forEach( c => classes.delete( c ) ),
				contains : k => classes.has( k ), toggle : ( k, on ) => { if( on === undefined ? ! classes.has( k ) : on ) classes.add( k ); else classes.delete( k ); return classes.has( k ) },
			},
			get className() { return [ ...classes ].join(' ') }, set className( v ) { classes = new Set( String( v ).split( /\s+/ ).filter( Boolean ) ) },
			get textContent() { return this._text + this.children.map( c => c.textContent ).join('') }, set textContent( v ) { this.children = []; this._text = String( v ) },
			get innerHTML() { return '' }, set innerHTML( v ) { this.children.forEach( c => c.parentNode = null ); this.children = []; this._text = '' },
			setAttribute( k, v ) { this.attrs[k] = String( v ) }, getAttribute( k ) { return this.attrs[k] ?? null }, removeAttribute( k ) { delete this.attrs[k] },
			appendChild( c ) { if( c.parentNode ) c.remove(); c.parentNode = this; this.children.push( c ); return c },
			removeChild( c ) { c.remove(); return c },
			remove() { if( this.parentNode ) this.parentNode.children.splice( this.parentNode.children.indexOf( this ), 1 ); this.parentNode = null },
			replaceChild( fresh, old ) { const at = this.children.indexOf( old ); fresh.parentNode = this; this.children.splice( at, 1, fresh ); old.parentNode = null; return old },
			addEventListener( type, fn ) { ( this.listeners[type] = this.listeners[type] ?? [] ).push( fn ) },
			fire( type, ev = {} ) { ( this.listeners[type] ?? [] ).forEach( fn => fn( Object.assign( { target : this, preventDefault(){} }, ev ) ) ) },
			click() { this.fire('click') },
			focus() { this.focused = true },
			scrollIntoView() { this.scrolled = true },
			querySelectorAll( selector ) { return walk( this ).filter( n => matches( n, selector ) ) },
			querySelector( selector ) { return this.querySelectorAll( selector )[0] ?? null },
			closest( selector ) { for( let n = this; n; n = n.parentNode ) if( matches( n, selector ) ) return n; return null },
		};
		// A select answers with the option that was chosen, as the real one does
		if( tag === 'select' )
			Object.defineProperty( node, 'value', {
				get() { if( this._value !== undefined ) return this._value; const chosen = this.children.find( o => o.selected === true ) ?? this.children[0]; return chosen ? chosen.value : '' },
				set( v ) { this._value = String( v ) },
			} );
		return node;
	};

	const root = make('body');
	const doc = {
		documentElement : make('html'), body : root, createElement : make,
		createTextNode : text => Object.assign( make('#text'), { _text : String( text ) } ),
		getElementById : id => walk( root ).find( n => n.id === id ) ?? null,
		querySelector : selector => root.querySelector( selector ), querySelectorAll : selector => root.querySelectorAll( selector ),
		addEventListener(){}, removeEventListener(){},
	};

	return { make, doc, root, walk };
}

// One page of the workbench with the scripts of the Text module loaded, in one
// interface language, its server stubbed: what the panels do is read from the dom
function world( locale, hash, session ) {

	const dom = fakeDom();
	const words = workbenchWords( locale );
	const box = { console : console, document : dom.doc };
	box.window = box;
	box.Event = function( type ) { this.type = type };
	const calls = [];
	box.Nino = {
		editor : {}, events : { bindCallback(){} },
		content : { getText : key => words[key] ?? '' },
		admin : {
			router : { set : ( p, parts ) => calls.push( 'set '+ [ p ].concat( parts ).join('/') ), go : ( p, parts ) => calls.push( 'go '+ [ p ].concat( parts ).join('/') ), current : () => hash ?? { panel : '', parts : [] }, leave : ( n, l, proceed ) => proceed() },
			sessionLocale : { current : session === undefined ? locale : session, init : code => { if( box.Nino.admin.sessionLocale.current === null ) box.Nino.admin.sessionLocale.current = code }, set : code => { calls.push( 'locale '+ code ); box.Nino.admin.sessionLocale.current = code } },
			formToolbar : back => { const bar = dom.make('div'); bar.className = 'nino-admin-contextbar'; bar.appendChild( back ); return bar },
			htmlEditor : { create : ( mount, value ) => { mount.setAttribute( 'data-editor', 'yes' ); let held = String( value ?? '' ); return { getValue : () => held, setValue : v => { held = v }, destroy(){} } } },
		},
	};

	const context = vm.createContext( box );
	[ '_admin/assets/Nino.admin.js', '_admin/Nino/Modules/Text/assets/textkeys.js', '_admin/Nino/Modules/Text/assets/admin.js', '_admin/Nino/Modules/Text/assets/keys.js' ]
		.forEach( file => vm.runInContext( fs.readFileSync( path.join( __dirname, '..', file ), 'utf8' ), context, { filename : file } ) );

	const mount = ( ...ids ) => ids.map( id => { const el = dom.make('div'); el.id = id; dom.root.appendChild( el ); return el } );

	return { dom, box, calls, words, Nino : box.Nino, T : box.Nino.admin.textKeys, U : box.Nino.adminUi, mount };
}

// --- the names of a key ---------------------------------------------------------

{
	const de = world( 'de_DE' );
	const en = world( 'en_US' );

	check( 'describeKey: the four segments of a key of the grammar',
		JSON.stringify( de.U.describeKey( '/template/page-home/welcome/title' ) ) === '{"kind":"grammar","namespace":"template","category":"page-home","part":"welcome","name":"title"}' );
	check( '...and the list and the entry of a part with a hyphen', JSON.stringify( de.U.describeKey( '/feature/consent/category-necessary/name' ) ) === '{"kind":"grammar","namespace":"feature","category":"consent","part":"category-necessary","name":"name","list":"category","id":"necessary"}'
		&& de.U.describeKey( '/result-unsubscribe-requested' ).kind === 'free' && de.U.describeKey( '/feature/newsletter/result-unsubscribe-requested/title' ).id === 'unsubscribe-requested' );
	check( 'the details of a page, the uri read from the right: slashes and dots belong to it',
		JSON.stringify( de.U.describeKey( '/_nino/webpage/about/team/title' ) ) === '{"kind":"webpage","uri":"/about/team","field":"title"}'
		&& de.U.describeKey( '/_nino/webpage/.demo-catalogue/name' ).uri === '/.demo-catalogue' && de.U.describeKey( '/_nino/webpage/blog/uri' ).field === 'uri' );
	check( 'the name of a language: its code, unchanged', JSON.stringify( de.U.describeKey( '/_nino/locale/de_DE/name' ) ) === '{"kind":"locale","code":"de_DE"}' );
	check( 'anything else is free - a key somebody made up, one that is too short or too long, an old form, the legal link, a word of the workbench',
		[ '/website/contact/uri', '/home/plain', '/company/name', '/template/page-home/welcome', '/template/page-home/a/b/c', '/Template/page-home/a/b', '/template/page_home/a/b', '/_admin/common/word/title', '/nino/dir', 'x' ].every( key => de.U.describeKey( key ).kind === 'free' ) );

	// slugLabel: the vocabulary, a number, a compound, a humanized slug - in the language of the interface
	const label = ( w, slug ) => w.U.slugLabel( slug );
	check( 'slugLabel: a word of the vocabulary', label( de, 'intro' ) === 'Einleitung' && label( en, 'intro' ) === 'Introduction' && label( de, 'accept-all' ) === 'Alle akzeptieren' && label( de, 'line-height' ) === 'Zeilenhöhe' );
	check( '...<word>-<number>', label( de, 'item-3' ) === 'Eintrag 3' && label( en, 'item-3' ) === 'Item 3' );
	check( '...the entry of a list: the list and the entry, each a word', label( de, 'category-necessary' ) === 'Kategorie · Notwendig' && label( de, 'unit-day' ) === 'Einheit · Tag' && label( de, 'result-unsubscribe-requested' ) === 'Ergebnis · Abmeldung angefordert' );
	check( '...what the builder makes of an id and a property: the left side looked up if it is a word, humanized if not', label( de, 'cta-label' ) === 'Cta · Beschriftung' && label( de, 'hero-image-alt' ) === 'Hero image · Alternativtext' && label( de, 'button-label' ) === 'Schaltfläche · Beschriftung' );
	check( '...the category of a page nothing routes: "Seite" and what follows', label( de, 'page-posts' ) === 'Seite · Beiträge' && label( de, 'page-protected' ) === 'Seite · Geschützter Bereich' && label( de, 'page-newsletter-unsubscribe' ) === 'Seite · Newsletter-Abmeldung' );
	check( '...a word that is not in the vocabulary, humanized', label( de, 'tagline' ) === 'Tagline' && label( de, 'get-in-touch' ) === 'Get in touch' && label( en, 'tagline' ) === 'Tagline' );
	check( 'a group has a word of its own', label( de, 'frame-header' ) === 'Kopfrahmen' && label( de, 'mail-user' ) === 'Mail an den Besucher' && label( de, 'common' ) === 'Allgemeine Wörter' && label( de, 'company' ) === 'Unternehmen' && label( de, 'mail' ) === 'E-Mail-Versand' );
	check( 'humanize: capital first letter, hyphens and underscores as spaces', de.U.humanize( 'price_default' ) === 'Price default' && de.U.humanize( 'get-in-touch' ) === 'Get in touch' );
}

// --- the rows, the sections and their order ------------------------------------------

const textEntry = ( key, values, extra ) => Object.assign( { key : key, global : '*' in values, values : values, html : false, format : 'plain', maxlength : 150, blacklisted : false, writable : true }, extra ?? {} );
const homeKeys = [
	textEntry( '/template/page-services/item-1/title', { de_DE : 'Beratung', en_US : 'Consulting' } ),
	textEntry( '/template/page-services/item-1/text', { de_DE : '<p>Wir beraten Sie gern</p>', en_US : 'We are happy to advise you' }, { html : true, format : 'blocks' } ),
	textEntry( '/template/page-services/item-2/title', { de_DE : 'Planung', en_US : 'Planning' } ),
	textEntry( '/template/page-services/intro/title', { de_DE : 'Leistungen', en_US : 'Services' } ),
	textEntry( '/template/page-services/intro/text', { de_DE : 'Das bieten wir an', en_US : '' } ),
	textEntry( '/template/page-services/legal/note', { de_DE : 'Alle Angaben ohne Gewähr', en_US : 'No guarantee' } ),
	textEntry( '/_nino/webpage/services/name', { de_DE : 'Leistungen', en_US : 'Services' } ),
	textEntry( '/_nino/webpage/services/title', { de_DE : 'Unsere Leistungen', en_US : 'Our services' } ),
	textEntry( '/_nino/webpage/services/description', { de_DE : 'Was wir können', en_US : 'What we can do' } ),
	textEntry( '/_nino/webpage/services/uri', { '*' : '/services' }, { blacklisted : true } ),
	textEntry( '/_nino/webpage/legal/name', { de_DE : 'Rechtliches', en_US : 'Legal' } ),
	textEntry( '/_nino/webpage/blog/name', { de_DE : 'Blog', en_US : 'Blog' } ),
	textEntry( '/_nino/webpage/blog/title', { de_DE : 'Unser Blog', en_US : 'Our blog' } ),
	textEntry( '/_nino/locale/de_DE/name', { '*' : 'Deutsch' } ),
	textEntry( '/_nino/locale/fr_FR/name', { '*' : 'fr_FR' } ),
	textEntry( '/project/company/general/name', { '*' : 'Acme' } ),
	textEntry( '/project/company/general/description', { de_DE : 'Wir bauen Dinge', en_US : 'We build things' } ),
	textEntry( '/project/company/contact/email', { '*' : 'mail@acme.test' } ),
	textEntry( '/project/company/contact/country', { de_DE : 'Deutschland', en_US : 'Germany' } ),
	textEntry( '/project/mail/color/backdrop', { '*' : '#fff' }, { blacklisted : true } ),
	textEntry( '/template/common/form/submit', { de_DE : 'Absenden', en_US : 'Submit' } ),
	textEntry( '/template/common/label/phone', { de_DE : 'Telefon', en_US : 'Phone' } ),
	textEntry( '/template/frame-header/navigation/label', { de_DE : 'Hauptnavigation', en_US : 'Main navigation' } ),
	textEntry( '/template/mail-user/intro/title', { de_DE : 'Danke für Deine Nachricht', en_US : 'Thanks for your message' } ),
	textEntry( '/module/form/info/success', { de_DE : 'Gesendet', en_US : 'Sent' } ),
	textEntry( '/feature/consent/category-necessary/name', { de_DE : 'Notwendig', en_US : 'Necessary' } ),
	textEntry( '/feature/consent/banner/title', { de_DE : 'Cookies', en_US : 'Cookies' } ),
	textEntry( '/website/contact/uri', { '*' : '/contact' } ),
	textEntry( '/_admin/elements/field/social/title', { '*' : 'Netzwerk' }, { blacklisted : true } ),
];
const homePages = [
	{ uri : '/services', httpUri : '/services', template : 'page-services', category : 'page-services', templateName : null },
	{ uri : '/legal', httpUri : '/legal', template : '', category : null, templateName : null },
];
const homeOrder = { '/template/page-services/intro/title' : 100, '/template/page-services/intro/text' : 140, '/template/page-services/item-1/title' : 200, '/template/page-services/item-1/text' : 240, '/template/page-services/item-2/title' : 300,
	'/feature/consent/banner/title' : 10, '/feature/consent/category-necessary/name' : 20 };
const homeData = ( extra ) => Object.assign( { entries : homeKeys.filter( e => e.blacklisted === false ), pages : homePages,
	templates : { 'page-services' : { file : 'page-services.tpl', name : null }, 'page-hello' : { file : 'page-hello.tpl', name : 'Hello World' } },
	features : { consent : 'Cookie-Einwilligung' }, order : homeOrder, locale : 'de_DE' }, extra ?? {} );

{
	const w = world( 'de_DE' );
	const model = w.T.build( homeData() );
	const labels = ids => ids.map( id => w.T.rowLabel( model, model.rows[id] ) );

	check( 'two blocks: the pages, then everything else', model.blocks.map( b => b.id ).join() === 'pages,general' );
	check( 'a page is a row named after its route in the language, the legal page - a template without a category - a row of its own, and neither is a row of the site\'s other texts',
		JSON.stringify( model.blocks[0].rows ) === '["template/page-services","_pages/legal"]' && labels( model.blocks[0].rows ).join() === 'Leistungen,Rechtliches' );
	check( 'the others are in groups, in this order: the project, the common words, the building blocks, modules, features, the system, the rest',
		model.blocks[1].groups.map( g => g.id ).join() === 'project,common,blocks,modules,features,system,other' );
	check( '...the project in the order of the company, the website, the mail; the blocks and modules by name',
		JSON.stringify( model.blocks[1].groups[0].rows ) === '["project/company"]' && labels( model.blocks[1].groups[2].rows ).join() === 'Kopfrahmen,Mail an den Besucher' && labels( model.blocks[1].groups[3].rows ).join() === 'Formular' );
	check( '...a feature named after its manifest, the system as the words of the panel', labels( model.blocks[1].groups[4].rows ).join() === 'Cookie-Einwilligung' && labels( model.blocks[1].groups[5].rows ).join() === 'Sprachnamen,Seitenangaben ohne Seite' );
	check( 'a key somebody made up is a row by its first segment, in "Weitere" - the address of the contact page among them', JSON.stringify( model.blocks[1].groups[6].rows ) === '["website"]' && model.rowOf['/website/contact/uri'] === 'website' );
	check( 'the details of a page without a stored route - a feature\'s - are the system\'s row, a section for each', model.rowOf['/_nino/webpage/blog/name'] === '_nino/webpage'
		&& w.T.sections( model, model.rows['_nino/webpage'] ).map( s => s.label+ ':'+ s.fields.map( f => f.label ).join('+') ).join() === '/blog:Name im Menü+Seitentitel' );
	check( 'the names of the languages are the system\'s row too, a section for each language', w.T.sections( model, model.rows['_nino/locale'] ).map( s => s.label+ ':'+ s.fields.map( f => f.label ).join() ).join() === 'de_DE:Name,fr_FR:Name' );
	check( 'the words of the workbench are no row of the site\'s texts - nor does the Keys tab lose them, which asks for them', Object.keys( w.T.build( homeData( { entries : homeKeys } ) ).rowOf ).every( key => key.indexOf( '/_admin/' ) !== 0 )
		&& w.T.build( homeData( { entries : homeKeys, admin : true } ) ).rowOf['/_admin/elements/field/social/title'] === '_admin' );

	// A page: its details first, then its template in the order the template reads it
	const page = w.T.sections( model, model.rows['template/page-services'] );
	check( 'a page begins with the details of its route - name, title, description, in that order, the path small - and "uri" is not among them', page[0].label === 'Seitenangaben' && page[0].route === '/services' );
	check( '...the three fields of the route are Name im Menü, Seitentitel, Beschreibung für Suchmaschinen',
		page[0].fields.map( f => f.label ).join() === 'Name im Menü,Seitentitel,Beschreibung für Suchmaschinen' );
	check( 'then the sections in the order a template reads them - Einleitung (Titel, Text), Eintrag 1 (Titel, Text), Eintrag 2 - and what no template reads, in the alphabet',
		page.slice( 1 ).map( s => s.label+ ':'+ s.fields.map( f => f.label ).join('+') ).join() === 'Einleitung:Titel+Text,Eintrag 1:Titel+Text,Eintrag 2:Titel,Legal:Anmerkung' );

	// The project: general first, the fields that are the same in every language first in a section
	const company = w.T.sections( model, model.rows['project/company'] );
	check( 'in the project, "general" is the first section; in a section the fields that are the same in every language come first',
		company.map( s => s.id ).join() === 'general,contact' && company[0].fields.map( f => f.label+ ( f.entry.global ? '*' : '' ) ).join() === 'Name*,Beschreibung' && company[1].fields.map( f => f.label+ ( f.entry.global ? '*' : '' ) ).join() === 'E-Mail*,Land' );
	check( 'a feature follows the order its templates read: Banner before Kategorie · Notwendig', w.T.sections( model, model.rows['feature/consent'] ).map( s => s.label ).join() === 'Banner,Kategorie · Notwendig' );

	// Without the Routes module there are no pages: the templates are blocks, the details have no page
	const bare = w.T.build( homeData( { pages : null } ) );
	check( 'without pages there is no block of pages: the templates are building blocks and the details of a page belong to no page',
		bare.blocks.map( b => b.id ).join() === 'general' && bare.rows['template/page-services'].kind === 'block' && bare.rowOf['/_nino/webpage/services/name'] === '_nino/webpage' );
	// A project with the module and no stored page: its page templates are still pages
	const noRoutes = w.T.build( homeData( { pages : [] } ) );
	check( 'with the Routes module but no stored page route, a page-*.tpl is still a row of the pages and the details of a page are the system\'s',
		noRoutes.blocks.map( b => b.id ).join() === 'pages,general' && noRoutes.rows['template/page-services'].kind === 'page' && noRoutes.rowOf['/_nino/webpage/services/name'] === '_nino/webpage' );
	// ...and a template that no route includes but whose name is a page's is a page, with its own row
	const loose = w.T.build( homeData( { pages : [ homePages[1] ] } ) );
	check( 'a page-*.tpl with keys of its own and no route is a row of the pages, named by its category, in the pages after the ones with a route', loose.rows['template/page-services'].kind === 'page'
		&& w.T.rowLabel( loose, loose.rows['template/page-services'] ) === 'Page services' && JSON.stringify( loose.blocks[0].rows ) === '["_pages/legal","template/page-services"]' );

	// The name of a row, by its routes, its own name, its category
	const two = w.T.build( homeData( { pages : [ homePages[0], { uri : '/dienste', httpUri : '/dienste', template : 'page-services', category : 'page-services', templateName : null } ],
		entries : homeKeys.concat( [ textEntry( '/_nino/webpage/dienste/name', { de_DE : 'Dienste', en_US : 'Services' } ) ] ).filter( e => e.blacklisted === false ) } ) );
	check( 'two routes on one template are one row, named by both and with the details of each', w.T.rowLabel( two, two.rows['template/page-services'] ) === 'Leistungen · Dienste'
		&& w.T.sections( two, two.rows['template/page-services'] ).filter( s => s.label === 'Seitenangaben' ).map( s => s.route ).join() === '/dienste,/services' );
	check( 'a row is named in the language it is asked for', w.T.rowLabel( two, two.rows['template/page-services'], 'en_US' ) === 'Services · Services' );
	const hello = w.T.build( { entries : [ textEntry( '/template/page-hello/hero/title', { de_DE : 'Hallo' } ), textEntry( '/template/page-posts/list/title', { de_DE : 'Beiträge' } ), textEntry( '/template/page-2026-home/hero/title', { de_DE : 'Neu' } ) ],
		pages : [ homePages[0] ], templates : { 'page-hello' : { file : 'page-hello.tpl', name : 'Hello World' } }, locale : 'de_DE' } );
	check( 'a page without a route is named by what its template calls itself, else by its category: "Seite · Beiträge"',
		w.T.rowLabel( hello, hello.rows['template/page-hello'] ) === 'Hello World' && w.T.rowLabel( hello, hello.rows['template/page-posts'] ) === 'Seite · Beiträge' && w.T.rowLabel( hello, hello.rows['template/page-2026-home'] ) === 'Page 2026 home' );
	check( 'a row is summed up in the language: how many keys, the beginning of their texts', w.T.rowSummary( model.rows['project/company'], 'de_DE' ) === '(4) Acme, Wir bauen Dinge, mail@acme.test, Deutschland' );
	check( 'a legend shows the beginning of the first text of its section, what is typed counting', w.T.sectionPreview( page[1], 'de_DE' ) === 'Leistungen' && w.T.sectionPreview( page[1], 'de_DE', { '/template/page-services/intro/title' : 'Ein sehr langer Titel, der weit über die vierzig Zeichen hinausgeht' } ) === 'Ein sehr langer Titel, der weit über die ..' );
}

// The demo catalogue is a route to a template that is no page-* and has no category: a page of the site, with a row of its own
{
	const w = world( 'de_DE' );
	const catalogue = { uri : '/.demo-catalogue', httpUri : '/.demo-catalogue', template : '.demo-catalogue', category : null, templateName : null };
	const model = w.T.build( homeData( { pages : homePages.concat( [ catalogue ] ),
		entries : homeKeys.filter( e => e.blacklisted === false ).concat( [ textEntry( '/_nino/webpage/.demo-catalogue/name', { de_DE : 'Katalog', en_US : 'Catalogue' } ), textEntry( '/_nino/webpage/.demo-catalogue/title', { de_DE : 'Katalog: Presets', en_US : 'Catalogue: presets' } ) ] ) } ) );
	check( 'the details of the demo catalogue are the row of a page - not the system\'s "page details without a page" - named by its route',
		model.rowOf['/_nino/webpage/.demo-catalogue/name'] === '_pages/.demo-catalogue' && model.rows['_pages/.demo-catalogue'].kind === 'page' && model.blocks[0].rows.includes('_pages/.demo-catalogue')
		&& w.T.rowLabel( model, model.rows['_pages/.demo-catalogue'] ) === 'Katalog' && model.rows['_nino/webpage'].entries.every( e => e.key.indexOf( '/.demo-catalogue/' ) === -1 ) );
	check( '...with the details of its route as the only section, the path not shown',
		w.T.sections( model, model.rows['_pages/.demo-catalogue'] ).map( s => s.label+ ':'+ s.fields.map( f => f.label ).join('+') ).join() === 'Seitenangaben:Name im Menü+Seitentitel' );
	const orphan = w.T.build( homeData( { pages : homePages } ) );
	check( 'without the route in the list - an older server, or the page deleted - its details are the system\'s, as before', orphan.rowOf['/_nino/webpage/blog/name'] === '_nino/webpage' );
}

// A part, a category or a first segment that is a member of Object.prototype is a name like any other
{
	const w = world( 'de_DE' );
	const entries = [ textEntry( '/project/shop/constructor/name', { de_DE : 'Konstrukteur' } ), textEntry( '/project/shop/constructor/title', { de_DE : 'Titel' } ), textEntry( '/constructor/x', { de_DE : 'Frei' } ),
		textEntry( '/toString/a/b', { de_DE : 'Zeichen' } ), textEntry( '/template/constructor/hero/title', { de_DE : 'Held' } ), textEntry( '/feature/constructor/hero/title', { de_DE : 'Feature' } ),
		textEntry( '/module/constructor/hero/title', { de_DE : 'Modul' } ), textEntry( '/_nino/webpage/constructor/name', { de_DE : 'Seite' } ) ];
	const model = w.T.build( { entries : entries, pages : [], templates : {}, features : {}, locale : 'de_DE' } );
	const everySection = Object.keys( model.rows ).map( id => w.T.sections( model, model.rows[id] ) );
	check( 'a row, a part or a category named constructor or toString is a row, a section and a field - the sections of every row are built',
		model.rowOf['/project/shop/constructor/name'] === 'project/shop' && model.rowOf['/constructor/x'] === 'constructor' && model.rowOf['/toString/a/b'] === 'toString' && everySection.length === Object.keys( model.rows ).length
		&& w.T.sections( model, model.rows['project/shop'] ).map( s => s.id ).join() === 'constructor' && w.T.sections( model, model.rows['project/shop'] )[0].fields.length === 2 );
	check( '...none of them is named by something Object.prototype has: the name of a category that is no template or feature is its word', w.T.rowLabel( model, model.rows['template/constructor'] ) === 'Constructor'
		&& w.T.rowLabel( model, model.rows['feature/constructor'] ) === 'Constructor' && w.T.rowLabel( model, model.rows['module/constructor'] ) === 'Constructor' );
	check( '...and the search finds them, for any query', [ '', 'a', 'konstrukteur', 'constructor', '/toString' ].every( q => Array.isArray( w.T.search( model, { query : q, locale : 'de_DE' } ) ) ) && w.T.search( model, { query : 'konstrukteur', locale : 'de_DE' } ).length === 1
		&& w.T.search( model, { query : '', locale : 'de_DE' } ).length === entries.length );
	check( '...and an address that names them resolves', w.T.resolve( model, [ 'project', 'shop', 'constructor', 'name' ] ).row === 'project/shop' && w.T.resolve( model, [ 'constructor' ] ).row === 'constructor' && w.T.resolve( model, [ 'toString' ] ).row === 'toString' );
}

// --- the search ------------------------------------------------------------------------

{
	const w = world( 'de_DE' );
	const model = w.T.build( homeData() );
	const search = ( query, extra ) => w.T.search( model, Object.assign( { query : query, locale : 'de_DE' }, extra ?? {} ) );
	const keysOf = hits => hits.map( h => h.item.field.entry.key );

	check( 'a word is found in the text of the language on screen, marked in the piece shown', ( () => { const hits = search( 'beraten' ); return hits.length === 1 && keysOf( hits )[0] === '/template/page-services/item-1/text'
		&& hits[0].locale === 'de_DE' && hits[0].snippet.filter( p => p.mark ).map( p => p.text ).join() === 'beraten'; } )() );
	check( '...in the text of another language, which the hit names', ( () => { const hits = search( 'consulting' ); return hits.length === 1 && hits[0].locale === 'en_US' && keysOf( hits )[0] === '/template/page-services/item-1/title'; } )() );
	check( '...in the key, in the name of the row, the section and the field', keysOf( search( 'banner' ) ).join() === '/feature/consent/banner/title' && keysOf( search( 'eintrag 2' ) ).join() === '/template/page-services/item-2/title'
		&& keysOf( search( 'kopfrahmen' ) ).join() === '/template/frame-header/navigation/label' && search( 'welcome' ).length === 0 );
	check( 'every word has to occur, in any part of what is searched, in any order', keysOf( search( 'titel eintrag 1' ) ).join() === '/template/page-services/item-1/title' && search( 'beraten consulting' ).length === 0 );
	check( 'accents and capitals do not matter - on either side', keysOf( search( 'GEWAHR' ) ).join() === '/template/page-services/legal/note' && keysOf( search( 'gewähr' ) ).join() === '/template/page-services/legal/note' && keysOf( search( 'DANKE fur' ) ).join() === '/template/mail-user/intro/title' );
	check( 'a word that begins with a slash is searched in the key alone', search( '/template/page-services' ).length === 6 && keysOf( search( '/common/' ) ).join() === '/template/common/form/submit,/template/common/label/phone'
		&& search( '/leistungen' ).length === 0 && keysOf( search( '/submit' ) ).join() === '/template/common/form/submit' );
	check( '...and may be combined with words that are searched everywhere - and the hits come in the order the rows show them', keysOf( search( '/template/page-services titel' ) ).join() === '/template/page-services/intro/title,/template/page-services/item-1/title,/template/page-services/item-2/title' );
	check( 'the tags of a text are not searched, nor are the entities', search( 'strong' ).length === 0 && search( '<p>' ).length === 0 );
	check( 'a field this account may not write is found as well, and the words of the workbench are not', ( () => { const own = w.T.build( homeData( { entries : homeKeys.map( e => e.key === '/project/company/general/name' ? Object.assign( {}, e, { writable : false } ) : e ) } ) );
		return w.T.search( own, { query : 'acme', locale : 'de_DE' } ).length === 2 && w.T.search( own, { query : '/general/name', locale : 'de_DE' } ).length === 1 && w.T.search( own, { query : 'netzwerk', locale : 'de_DE' } ).length === 0 } )() );
	check( 'a search that finds nothing finds nothing, and no words at all finds every field', search( 'xyzzy' ).length === 0 && search( '' ).length === homeData().entries.length );
	check( 'the filter keeps the keys with no text in the language - the ones that are one per language, empty or not there', keysOf( search( '', { emptyIn : 'en_US' } ) ).join() === '/template/page-services/intro/text'
		&& keysOf( search( '', { emptyIn : 'de_DE' } ) ).length === 0 );
	check( '...and combines with a search', keysOf( search( 'leistungen', { emptyIn : 'en_US' } ) ).join() === '/template/page-services/intro/text' && search( 'planung', { emptyIn : 'en_US' } ).length === 0 );
	check( 'only the hidden keys, for the Keys tab, which has them in its rows', ( () => { const all = w.T.build( homeData( { entries : homeKeys, admin : true } ) );
		return w.T.search( all, { query : '', locale : 'de_DE', onlyHidden : true } ).map( h => h.item.field.entry.key ).sort().join() === '/_admin/elements/field/social/title,/_nino/webpage/services/uri,/project/mail/color/backdrop'; } )() );

	// A key that holds more than a page of hits: the list says so, 50 at a time, in order
	const many = w.T.build( { entries : Array.from( { length : 120 }, ( v, i ) => textEntry( '/project/many/item/n'+ String( i ).padStart( 3, '0' ), { de_DE : 'Treffer '+ i } ) ), locale : 'de_DE' } );
	const manyHits = w.T.search( many, { query : 'treffer', locale : 'de_DE' } );
	check( 'every hit is returned - the panel draws the first 50 and offers more', manyHits.length === 120 && manyHits[0].item.field.entry.key.endsWith( 'n000' ) && manyHits[119].item.field.entry.key.endsWith( 'n119' ) );

	// The piece of text: from the original, accents and capitals as written
	const snip = w.T.snippet( 'Über uns: Wir sind äußerst nett und bieten die Beratung an. '.repeat( 3 ), [ 'uber' ], 60 );
	check( 'a piece of a text is cut from the original, the word marked as it is written there, cut off with a dot of ellipsis',
		snip[0].mark === true && snip[0].text === 'Über' && snip.map( p => p.text ).join('').length <= 66 && snip[snip.length - 1].text.endsWith(' …') );
	check( '...and a hit in the middle of a long text begins with one', ( () => { const parts = w.T.snippet( 'a '.repeat( 100 )+ 'Zielwort '+ 'b '.repeat( 100 ), [ 'zielwort' ], 40 ); return parts[0].text.startsWith('… ') && parts.some( p => p.mark && p.text === 'Zielwort' ) } )() );
	check( 'plain: tags and entities gone, the end of a paragraph a space', w.T.plain( '<p>Eins</p><p>Zwei &amp; <strong>drei</strong>&nbsp;vier</p>' ) === 'Eins Zwei & drei vier' );

	// The hash
	check( 'resolve: a key, a row, the start of a key', JSON.stringify( w.T.resolve( model, [ 'template', 'page-services', 'item-1', 'title' ] ) ) === '{"row":"template/page-services","key":"/template/page-services/item-1/title"}'
		&& JSON.stringify( w.T.resolve( model, [ 'template', 'page-services' ] ) ) === '{"row":"template/page-services","key":null}'
		&& w.T.resolve( model, [ 'project', 'company', 'general' ] ).row === 'project/company' && w.T.resolve( model, [ 'nothing' ] ) === null && w.T.resolve( model, [] ) === null );
	check( '...the deep links of the concept: a text of the home page, the details of a page', w.T.resolve( model, [ 'template', 'page-services', 'intro', 'title' ] ).row === 'template/page-services'
		&& JSON.stringify( w.T.resolve( model, [ '_nino', 'webpage', 'services', 'title' ] ) ) === '{"row":"template/page-services","key":"/_nino/webpage/services/title"}'
		&& w.T.resolve( model, [ '_nino', 'webpage', 'blog', 'name' ] ).row === '_nino/webpage' );
	check( 'hashParts: a row as its path, a key without its leading slash', JSON.stringify( w.T.hashParts( 'template/page-services' ) ) === '["template","page-services"]' && JSON.stringify( w.T.hashParts( 'x', '/a/b/c' ) ) === '["a","b","c"]' );
}

// --- the Text panel: the list, the search, the form ------------------------------------

const textResponse = ( extra ) => Object.assign( { keys : homeKeys.filter( e => e.blacklisted === false ), locales : [ 'de_DE', 'en_US' ], selectedLocale : 'de_DE', order : homeOrder, pages : homePages,
	templates : homeData().templates, features : homeData().features }, extra ?? {} );

function openedText( response, hash, locale ) {
	const w = world( locale ?? 'de_DE', hash );
	const [ list, form ] = w.mount( 'text-list', 'text-form' );
	form.classList.add('admin-hidden');
	const sent = [];
	const text = w.Nino.admin.text;
	text._apiCall = ( endpoint, payload, callback ) => {
		sent.push( { endpoint : endpoint, payload : payload } );
		if( endpoint === 'keys' )
			return callback( 200, response );
		const results = {};
		payload.items.forEach( item => { results[item.key] = { ok : true, value : item.value } } );
		callback( 200, { results : results } );
	};
	text.init();
	return Object.assign( w, { list, form, sent, text } );
}

{
	const t = openedText( textResponse() );
	const rows = t.list.querySelectorAll('.admin-type-btn');

	check( 'the list is two blocks: the pages, and the rest in groups - each with its heading', t.list.querySelectorAll('h2').map( h => h.textContent ).join() === 'Seiten,Allgemein'
		&& t.list.querySelectorAll('h3').map( h => h.textContent ).join() === 'Projekt,Bausteine,Module,Features,System,Weitere' );
	check( '...a row for each page, named after the route, and one for each group of the others', rows.length === Object.keys( t.text._model.rows ).length && rows[0].dataset.group === 'template/page-services'
		&& rows[0].children[0].children[0] === undefined === false && rows.map( r => r.children[0]._text ).slice( 0, 2 ).join() === 'Leistungen,Rechtliches' );
	check( '...with what it holds as its line: how many keys and the beginning of their texts', rows[0].querySelector('.admin-type-btn-descr').textContent.startsWith( '(9) ' ) );
	check( 'the search, the language and the filter are above the rows, each named', t.list.querySelector('#text-search').getAttribute('aria-label') === 'Texte durchsuchen' && t.list.querySelector('#text-list-locale').getAttribute('aria-label') === 'Sprache'
		&& t.list.querySelector('#text-list-empty').textContent === 'Leer in de_DE' && t.list.querySelector('#text-list-empty').getAttribute('aria-pressed') === 'false' );
	check( 'the words of the workbench are not among the rows', t.list.querySelectorAll('.admin-type-btn').every( r => r.dataset.group !== '_admin' ) );

	// the search
	const box = t.list.querySelector('#text-search');
	box.value = 'beraten';
	box.fire('input');
	check( 'a search replaces the rows by hits: the path, the piece of text with the word marked, the key', t.list.querySelectorAll('.admin-type-btn').length === 0 && t.list.querySelectorAll('.admin-text-hit').length === 1
		&& t.list.querySelector('.admin-text-hit strong').textContent === 'Leistungen › Eintrag 1 › Text' && t.list.querySelector('mark').textContent === 'beraten' && t.list.querySelector('.admin-text-hit-key').textContent === '/template/page-services/item-1/text' );
	check( '...and says how many there are, where a screen reader hears it', t.list.querySelector('#text-list-status').textContent === '1 Treffer' && t.list.querySelector('#text-list-status').getAttribute('aria-live') === 'polite' );
	check( 'a hit in the language on screen has no code of a language', t.list.querySelectorAll('.admin-text-hit-lang').length === 0 );
	box.value = 'xyzzy';
	box.fire('input');
	check( 'nothing found says so, with the empty state of the workbench', t.list.querySelectorAll('.admin-text-hit').length === 0 && t.list.querySelector('.nino-admin-empty').textContent === 'Nichts gefunden.' && t.list.querySelector('#text-list-status').textContent === '0 Treffer' );
	box.value = '';
	box.fire('input');
	check( 'an empty box is the rows again', t.list.querySelectorAll('.admin-type-btn').length === rows.length && t.list.querySelector('#text-list-status').textContent === '' );

	// a hit in another language: its code switches the language and nothing else
	box.value = 'consulting';
	box.fire('input');
	const lang = t.list.querySelector('.admin-text-hit-lang');
	check( 'a hit in another language shows that language\'s code, as a button named for what it does', lang.textContent === 'en_US' && lang.getAttribute('aria-label') === 'Die Texte in en_US zeigen' );
	lang.click();
	check( '...which switches the language of the session and the panel, and opens nothing', t.calls.includes('locale en_US') && t.text._currentGroup === null && t.list.querySelector('#text-list-empty').textContent === 'Leer in en_US'
		&& t.list.querySelectorAll('.admin-text-hit-lang').length === 0 && t.list.querySelectorAll('.admin-text-hit').length === 1 );
	box.value = '';
	box.fire('input');
	check( 'in the other language the pages are named by what their routes are called there', t.list.querySelectorAll('.admin-type-btn').slice( 0, 2 ).map( r => r.children[0]._text ).join() === 'Services,Legal' );
	t.list.querySelector('#text-list-empty').click();
	check( 'the filter for the keys with no text in the language shows them as hits, and says it is on', t.list.querySelector('#text-list-empty').getAttribute('aria-pressed') === 'true'
		&& t.list.querySelectorAll('.admin-text-hit').map( h => h.querySelector('.admin-text-hit-key').textContent ).join() === '/template/page-services/intro/text' );
	t.list.querySelector('#text-list-locale').value = 'de_DE';
	t.list.querySelector('#text-list-locale').fire('change');
	check( '...and follows the language chosen in the list', t.calls.includes('locale de_DE') && t.list.querySelector('#text-list-empty').textContent === 'Leer in de_DE' && t.list.querySelectorAll('.admin-text-hit').length === 0 );
	t.list.querySelector('#text-list-empty').click();

	// a hit opens its row, at the field
	box.value = 'beraten';
	box.fire('input');
	t.calls.length = 0;
	t.list.querySelector('.admin-text-hit-open').click();
	const field = t.form.querySelector('[data-key="/template/page-services/item-1/text"]');
	check( 'a hit opens its row at the field - the address names the key, the field is marked, scrolled to and has the cursor', t.text._currentGroup === 'template/page-services' && t.form.classList.contains('admin-hidden') === false && t.list.classList.contains('admin-hidden') === true
		&& t.calls.includes( 'go text/template/page-services/item-1/text' ) && t.calls.includes( 'set text/template/page-services/item-1/text' ) && field.classList.contains('is-found') && field.scrolled === true );

	// the form
	const sections = t.form.querySelectorAll('fieldset.admin-text-section');
	check( 'the form is a section for each part, the details of the page first, then in the order of the template', sections.map( f => f.dataset.section ).join() === 'webpage:/services,intro,item-1,item-2,legal'
		&& t.form.querySelector('.main-title').textContent === 'Leistungen' );
	check( '...a legend with the name of the section, the route of a page small, and the beginning of the first text',
		sections[0].querySelector('legend .admin-text-section-name').textContent === 'Seitenangaben' && sections[0].querySelector('legend code').textContent === '/services' && sections[0].querySelector('legend .admin-text-section-preview').textContent === 'Leistungen'
		&& sections[1].querySelector('legend .admin-text-section-name').textContent === 'Einleitung' && sections[1].querySelector('legend .admin-text-section-preview').textContent === 'Leistungen' && sections[2].querySelector('legend .admin-text-section-name').textContent === 'Eintrag 1' );
	check( 'the fields are named in German - the key is not the name', sections[0].querySelectorAll('.nino-admin-field-name').map( n => n.textContent ).join() === 'Name im Menü,Seitentitel,Beschreibung für Suchmaschinen'
		&& sections[2].querySelectorAll('.nino-admin-field-name').map( n => n.textContent ).join() === 'Titel,Text' && t.form.querySelectorAll('.nino-admin-field-name').every( n => n.textContent.indexOf('[[') === -1 ) );
	const title = t.form.querySelector('[data-key="/template/page-services/intro/title"]');
	const control = title.querySelector('textarea');
	check( 'a field is named by its name and described by its key and counter - they are not the name', control.getAttribute('aria-labelledby') === title.querySelector('.nino-admin-field-name').id && title.querySelector('.nino-admin-field-name').textContent === 'Titel'
		&& control.getAttribute('aria-describedby') === title.querySelector('.admin-text-meta').id && title.querySelector('.admin-text-meta code').textContent === '/template/page-services/intro/title'
		&& title.querySelector('.admin-text-meta .nino-admin-char-counter').textContent === '10 / 150' && title.querySelector('label') === null );
	check( 'a rich-text field is a group named the same way', ( () => { const rich = t.form.querySelector('[data-key="/template/page-services/item-1/text"]'); return rich.getAttribute('role') === 'group' && rich.getAttribute('aria-labelledby') === rich.querySelector('.nino-admin-field-name').id && rich.getAttribute('aria-describedby') === rich.querySelector('.admin-text-meta').id } )() );
	check( 'a field one per language carries no badge', t.form.querySelectorAll('.admin-text-badge').length === 0 );
	check( 'a form has the locale switch in its toolbar, in the language of the session', t.form.querySelector('#text-form-locale-select').value === 'de_DE' && t.form.querySelector('#text-form-locale-select').getAttribute('aria-label') === 'Sprache' );

	// the language changes the fields one per language, in place, and nothing else
	const keysBefore = Object.keys( t.text._fieldEls ).sort().join();
	control.value = 'Leistungen neu';
	const select = t.form.querySelector('#text-form-locale-select');
	select.value = 'en_US';
	select.fire('change');
	check( 'switching the language draws the texts of that language - each in its section, none lost from the form\'s fields',
		t.text._fieldEls['/template/page-services/intro/title'].value === 'Services' && Object.keys( t.text._fieldEls ).sort().join() === keysBefore
		&& t.form.querySelectorAll('fieldset.admin-text-section').map( f => f.dataset.section ).join() === sections.map( f => f.dataset.section ).join() && t.calls.includes('locale en_US')
		&& t.form.querySelector('[data-key="/template/page-services/intro/title"]').parentNode === sections[1].querySelector('.nino-admin-fieldgrid') );
	check( '...the legends follow the language', t.form.querySelectorAll('fieldset.admin-text-section')[1].querySelector('.admin-text-section-preview').textContent === 'Services' );
	check( '...and the model names the rows in it from then on', t.text._model.locale === 'en_US' );
	select.value = 'de_DE';
	select.fire('change');
	check( '...and what was typed in the language left is there when it is back', t.text._fieldEls['/template/page-services/intro/title'].value === 'Leistungen neu' && t.text._dirtyLocales.join() === 'de_DE' );

	// a save of a page carries the keys of both namespaces in one request
	t.sent.length = 0;
	t.text._fieldEls['/_nino/webpage/services/title'].value = 'Neuer Seitentitel';
	t.form.querySelector('form').fire('submit');
	const batch = t.sent.find( c => c.endpoint === 'savebatch' );
	check( 'a save of a page sends the keys of its template and the details of its route in one request', batch !== undefined && t.sent.filter( c => c.endpoint === 'savebatch' ).length === 1
		&& batch.payload.items.some( i => i.key === '/template/page-services/intro/title' && i.value === 'Leistungen neu' && i.locale === 'de_DE' )
		&& batch.payload.items.some( i => i.key === '/_nino/webpage/services/title' && i.value === 'Neuer Seitentitel' && i.locale === 'de_DE' ) );
	check( '...and says it is saved', t.form.querySelector('#text-form-msg').textContent === 'Gespeichert.' && t.text._dirtyLocales.length === 0 );
}

{
	// a row and a part named like a member of Object.prototype open as any other
	const free = textResponse( { keys : homeKeys.filter( e => e.blacklisted === false ).concat( [ textEntry( '/project/shop/constructor/name', { de_DE : 'Konstrukteur', en_US : 'Constructor' } ), textEntry( '/constructor/x', { de_DE : 'Frei' } ) ] ) } );
	const t = openedText( free );
	const box = t.list.querySelector('#text-search');
	box.value = 'konstrukteur';
	box.fire('input');
	check( 'the search over keys with a part named constructor answers, in the Text panel', t.list.querySelectorAll('.admin-text-hit').length === 1 );
	box.value = '';
	box.fire('input');
	t.list.querySelectorAll('.admin-type-btn').find( r => r.dataset.group === 'project/shop' ).click();
	check( '...and the form of its row opens, with the section named by the part', t.form.querySelectorAll('fieldset.admin-text-section').map( f => f.dataset.section ).join() === 'constructor' && t.text._fieldEls['/project/shop/constructor/name'].value === 'Konstrukteur' );
}

{
	// the project: fields that are the same in every language carry the mark, and come first in their section
	const t = openedText( textResponse() );
	t.list.querySelectorAll('.admin-type-btn').find( r => r.dataset.group === 'project/company' ).click();
	const general = t.form.querySelector('fieldset[data-section="general"]') ?? t.form.querySelectorAll('fieldset.admin-text-section').find( f => f.dataset.section === 'general' );
	check( 'a row of the project has "general" first, its fields that are the same in every language first - marked "Alle Sprachen"',
		t.form.querySelectorAll('fieldset.admin-text-section').map( f => f.dataset.section ).join() === 'general,contact'
		&& general.querySelectorAll('.nino-admin-field-name').map( n => n.textContent ).join() === 'Name,Beschreibung' && general.querySelectorAll('.admin-text-badge').map( b => b.parentNode.querySelector('.nino-admin-field-name').textContent ).join() === 'Name'
		&& general.querySelector('.admin-text-badge').textContent === 'Alle Sprachen' && t.form.querySelectorAll('.admin-text-badge').length === 2 );
	check( 'the badge is in the description of the field, so a screen reader hears that it is the same in every language', ( () => {
		const field = general.querySelector('[data-key="/project/company/general/name"]'); const badge = field.querySelector('.admin-text-badge');
		return badge.id !== '' && field.querySelector('textarea').getAttribute('aria-describedby') === badge.id+ ' '+ field.querySelector('.admin-text-meta').id } )() );
	check( 'the row is called by its name in the vocabulary', t.form.querySelector('.main-title').textContent === 'Unternehmen' );
	// the language switch does not touch a field that is the same in every language
	const mail = t.text._fieldEls['/project/company/contact/email'];
	mail.value = 'neu@acme.test';
	const select = t.form.querySelector('#text-form-locale-select');
	select.value = 'en_US';
	select.fire('change');
	check( 'a field that is the same in every language keeps what is typed when the language changes', t.text._fieldEls['/project/company/contact/email'] === mail && mail.value === 'neu@acme.test' && t.text._fieldEls['/project/company/contact/country'].value === 'Germany' );
}

{
	// keys this account may not write are read, in place
	const response = textResponse( { keys : homeKeys.filter( e => e.blacklisted === false ).map( e => e.key.indexOf( '/project/company/' ) === 0 ? Object.assign( {}, e, { writable : e.key.endsWith( 'email' ) } ) : e ) } );
	const t = openedText( response );
	t.list.querySelectorAll('.admin-type-btn').find( r => r.dataset.group === 'project/company' ).click();
	const view = t.form.querySelector('[data-key="/project/company/general/name"] .admin-text-readonly');
	check( 'a key the account may not write is shown as text - named and described like the others - and has no field', view !== null && view.textContent === 'Acme' && t.text._fieldEls['/project/company/general/name'] === undefined
		&& view.getAttribute('aria-labelledby') === view.parentNode.querySelector('.nino-admin-field-name').id && view.parentNode.querySelector('.admin-text-meta code').textContent === '/project/company/general/name' );
	check( '...the keys it may write are fields', t.text._fieldEls['/project/company/contact/email'] !== undefined && t.form.querySelector('button[type="submit"]') !== null );
}

{
	// deep links: a key, the details of a page
	const t = openedText( textResponse(), { panel : 'text', parts : [ 'template', 'page-services', 'item-2', 'title' ] } );
	check( 'a hash that names a key opens the row that holds it, at that key, when the panel loads', t.text._currentGroup === 'template/page-services' && t.text._focusKey === '/template/page-services/item-2/title'
		&& t.form.querySelector('[data-key="/template/page-services/item-2/title"]').classList.contains('is-found') && t.list.classList.contains('admin-hidden') );
	const details = openedText( textResponse(), { panel : 'text', parts : [ '_nino', 'webpage', 'services', 'title' ] } );
	check( '...also the details of a page, which are in the form of the page', details.text._currentGroup === 'template/page-services' && details.text._focusKey === '/_nino/webpage/services/title' );
	// the hash moves to another key of the row that is open: that field is shown, and the address keeps it
	const moving = { panel : 'text', parts : [ 'template', 'page-services', 'item-2', 'title' ] };
	const open = openedText( textResponse(), moving );
	moving.parts = [ 'template', 'page-services', 'intro', 'text' ];
	open.calls.length = 0;
	open.text.showCurrent();
	check( 'a hash that names another key of the row that is open focuses that key and the address keeps it', open.text._focusKey === '/template/page-services/intro/text'
		&& open.form.querySelector('[data-key="/template/page-services/intro/text"]').classList.contains('is-found') && open.calls.at(-1) === 'set text/template/page-services/intro/text' );
	const other = openedText( textResponse(), { panel : 'images', parts : [ 'x' ] } );
	check( 'the hash of another panel leaves the list', other.text._currentGroup === null && other.list.classList.contains('admin-hidden') === false );
	const unknown = openedText( textResponse(), { panel : 'text', parts : [ 'template', 'page-nope' ] } );
	check( 'a row there is not is the list', unknown.text._currentGroup === null && unknown.list.classList.contains('admin-hidden') === false );
}

{
	// more hits than a page: fifty, then "Weitere anzeigen", fifty at a time
	const many = Array.from( { length : 120 }, ( v, i ) => textEntry( '/project/many/item/n'+ String( i ).padStart( 3, '0' ), { de_DE : 'Treffer '+ i, en_US : '' } ) );
	const t = openedText( textResponse( { keys : many, pages : [], order : {} } ) );
	const box = t.list.querySelector('#text-search');
	box.value = 'treffer';
	box.fire('input');
	const more = () => t.list.querySelectorAll('button').find( b => b.textContent === 'Weitere anzeigen' );
	check( 'a search with many hits draws fifty and offers more', t.list.querySelectorAll('.admin-text-hit').length === 50 && more() !== undefined && t.list.querySelector('#text-list-status').textContent === '120 Treffer' );
	more().click();
	check( '...fifty more', t.list.querySelectorAll('.admin-text-hit').length === 100 && more() !== undefined );
	more().click();
	check( '...until there are no more, and no button', t.list.querySelectorAll('.admin-text-hit').length === 120 && more() === undefined );
	box.value = 'treffer 119';
	box.fire('input');
	check( 'typing again begins at the first fifty', t.list.querySelectorAll('.admin-text-hit').length === 1 && t.list.querySelector('#text-list-status').textContent === '1 Treffer' );
	t.list.querySelector('#text-list-locale').value = 'en_US';
	t.list.querySelector('#text-list-locale').fire('change');
	t.list.querySelector('#text-list-empty').click();
	box.value = '';
	box.fire('input');
	check( 'the filter alone is a list of hits as well: 50 of the 120 keys that are empty in English', t.list.querySelectorAll('.admin-text-hit').length === 50 && t.list.querySelector('#text-list-status').textContent === '120 Treffer' );
}

{
	// without the Routes module there are no pages, and the list has no block of them
	const t = openedText( textResponse( { pages : null } ) );
	check( 'without pages the list has the one block, and the details of a page are among the system\'s rows', t.list.querySelectorAll('h2').map( h => h.textContent ).join() === 'Allgemein'
		&& t.list.querySelectorAll('.admin-type-btn').some( r => r.dataset.group === '_nino/webpage' ) );
}

// --- the Keys tab: the list, the search, the form that creates and renames --------------

const keysResponse = ( extra ) => Object.assign( { keys : homeKeys.map( e => Object.assign( {}, e ) ), locales : [ 'de_DE', 'en_US' ], selectedLocale : 'en_US',
	categories : { template : [ 'common', 'frame-header', 'page-services' ], feature : [ 'consent' ], module : [ 'form', 'localepicker' ], project : [ 'company', 'website', 'mail', 'catalog' ] } }, extra ?? {} );

// What the tab asked of the server last, the reload that follows every write aside
const lastWrite = k => k.sent.filter( c => c.endpoint !== 'list' ).at(-1);

function openedKeys( response, hash ) {
	const w = world( 'de_DE', hash, null );
	const [ list, form ] = w.mount( 'keys-list', 'keys-form' );
	form.classList.add('admin-hidden');
	const sent = [];
	const keys = w.Nino.admin.keys;
	keys._apiCall = ( endpoint, payload, callback ) => {
		sent.push( { endpoint : endpoint, payload : payload } );
		callback( 200, endpoint === 'list' ? response : { ok : true } );
	};
	keys.init();
	return Object.assign( w, { list, form, sent, keys } );
}

{
	const k = openedKeys( keysResponse() );

	check( 'the Keys tab has the rows of the Text panel - with the hidden keys and the words of the workbench, which it exists to show - as a list of links', k.list.querySelectorAll('ul.nino-admin-list').length > 0
		&& k.list.querySelectorAll('ul.nino-admin-list a').some( a => a.dataset.group === '_admin' ) && k.list.querySelectorAll('ul.nino-admin-list a').some( a => a.dataset.group === 'project/mail' )
		&& k.list.querySelectorAll('a').every( a => a.href === '#' ) && k.list.querySelector('#keys-list-body').tagName === 'section' );
	check( '...the page rows are blocks, as there is no route list here, and have their names from the vocabulary', k.list.querySelectorAll('h2').map( h => h.textContent ).join() === 'Allgemein'
		&& k.list.querySelectorAll('a').find( a => a.dataset.group === 'template/frame-header' ).querySelector('strong').textContent === 'Kopfrahmen' );
	check( 'the session language is the one the tab opens in - the previews are English where the session is', k.keys._locale() === 'en_US' && k.list.querySelectorAll('a').find( a => a.dataset.group === 'project/company' ).querySelector('small').textContent.includes( 'We build things' ) );
	check( 'the scan and the new key sit in the action bar, below the list', k.list.querySelector('.nino-admin-list-actions') !== null || k.list.children[k.list.children.length - 1].querySelectorAll('button').length === 2 );

	// the search: with the hidden keys, and a filter for them alone
	const box = k.list.querySelector('#keys-search');
	check( 'the search is the Text panel\'s - the same box, named the same - and a chip for the hidden keys', box.getAttribute('aria-label') === 'Texte durchsuchen' && k.list.querySelector('#keys-list-hidden').textContent === 'Nur ausgeblendete'
		&& k.list.querySelector('#keys-list-hidden').getAttribute('aria-pressed') === 'false' );
	box.value = 'netzwerk';
	box.fire('input');
	check( 'it finds a hidden key as well', k.list.querySelectorAll('.admin-text-hit').length === 1 && k.list.querySelector('.admin-text-hit-key').textContent === '/_admin/elements/field/social/title' );
	box.value = '';
	box.fire('input');
	k.list.querySelector('#keys-list-hidden').click();
	check( 'only the hidden ones: the chip is on, and the hits are the keys hidden from the Text panel', k.list.querySelector('#keys-list-hidden').getAttribute('aria-pressed') === 'true'
		&& k.list.querySelectorAll('.admin-text-hit-key').map( c => c.textContent ).sort().join() === '/_admin/elements/field/social/title,/_nino/webpage/services/uri,/project/mail/color/backdrop' );
	k.list.querySelector('#keys-list-hidden').click();

	// a key in another language: the code switches the session language
	box.value = 'consulting';
	box.fire('input');
	k.calls.length = 0;
	check( 'a hit in the language the tab is not in shows its code; there is none in the language it is in', k.list.querySelectorAll('.admin-text-hit-lang').length === 0 );
	box.value = 'beratung';
	box.fire('input');
	const lang = k.list.querySelector('.admin-text-hit-lang');
	check( '...and the code switches the language', lang.textContent === 'de_DE' && ( lang.click(), k.calls.includes('locale de_DE') && k.keys._locale() === 'de_DE' ) );

	// a hit opens its row at its field
	box.value = 'beratung';
	box.fire('input');
	k.calls.length = 0;
	k.list.querySelector('.admin-text-hit-open').click();
	check( 'a hit opens the row at the key: the address names it, the field is marked', k.keys._currentGroup === 'template/page-services' && k.calls.includes('go keys/template/page-services/item-1/title') && k.calls.includes('set keys/template/page-services/item-1/title')
		&& k.form.querySelector('[data-key="/template/page-services/item-1/title"]').classList.contains('is-found') && k.keys._view === 'group' );

	// the form of a row: the key is read, named, and renamed in a form of its own
	const key = k.form.querySelector('[data-key="/template/page-services/item-1/title"]');
	check( 'a key shows its key in a field that is read and has a name - and the name it is read by under it',
		key.querySelector('.admin-text-key-input').readOnly === true && key.querySelector('.admin-text-key-input').value === '/template/page-services/item-1/title' && key.querySelector('.admin-text-key-input').getAttribute('aria-label') === 'Schlüssel'
		&& key.querySelector('.admin-text-subtitle').textContent === 'Eintrag 1 › Titel' && key.querySelector('.admin-text-key-input').getAttribute('aria-describedby') === key.querySelector('.admin-text-subtitle').id );
	check( '...its text has the same name, not the key', key.querySelector('textarea').getAttribute('aria-label') === 'Eintrag 1 › Titel' );
	check( 'the keys of a row come in the order of their sections - the Keys tab knows no template, so a section and its fields are in the alphabet',
		k.form.querySelectorAll('.admin-text-subtitle').map( n => n.textContent ).join() === 'Einleitung › Text,Einleitung › Titel,Eintrag 1 › Text,Eintrag 1 › Titel,Eintrag 2 › Titel,Legal › Anmerkung' );
	const buttonsOf = el => el.querySelectorAll('button').map( b => b.textContent );
	check( 'a key of the grammar can be renamed and deleted', buttonsOf( key ).includes( 'Umbenennen' ) && buttonsOf( key ).includes( 'Löschen' ) );
	k.keys._openGroup( '_nino/webpage' );
	check( 'a key of the system - a page\'s details, a language\'s name - has no button to rename it: only its value is edited, and it can be deleted',
		k.form.querySelectorAll('.admin-text-field').length > 0 && k.form.querySelectorAll('.admin-text-field').every( f => buttonsOf( f ).includes( 'Umbenennen' ) === false && buttonsOf( f ).includes( 'Löschen' ) ) );
	k.keys._openGroup( '_admin' );
	check( '...nor has a word of the workbench', k.form.querySelectorAll('.admin-text-field').every( f => buttonsOf( f ).includes( 'Umbenennen' ) === false ) );
	k.keys._openGroup( 'website' );
	check( '...but a key somebody made up can be renamed - into the grammar', buttonsOf( k.form.querySelector('[data-key="/website/contact/uri"]') ).includes( 'Umbenennen' ) );
}

{
	// the form that renames: the four segments of the key, the namespace already unlocked where the key is not the project's
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'template/page-services' );
	k.form.querySelector('[data-key="/template/page-services/item-1/title"]').querySelectorAll('button').find( b => b.textContent === 'Umbenennen' ).click();

	const control = id => k.form.querySelector( '#'+ id );
	const options = id => control( id ).children.filter( c => c.tagName === 'option' ).map( o => o.value );
	const submit = () => k.form.querySelector('button[type="submit"]');
	check( 'renaming opens the form with the key\'s segments, one in each field', k.keys._view === 'new' && k.keys._renameFrom === '/template/page-services/item-1/title' && control('keys-form-namespace').value === 'template' && control('keys-form-category').value === 'page-services'
		&& control('keys-form-part').value === 'item-1' && control('keys-form-name').value === 'title' && k.form.querySelector('.main-title').textContent === 'Textschlüssel umbenennen' );
	check( '...a namespace other than /project already unlocked, so the choice has them all - and never /_nino or /_admin', options('keys-form-namespace').join() === 'project,template,feature,module' && k.form.querySelectorAll('button').every( b => b.hidden === true || b.textContent !== 'Entsperren' ) );
	check( '...the category a choice: the templates there are, with "common"', control('keys-form-category').tagName === 'select' && options('keys-form-category').join() === 'common,frame-header,page-services' );
	check( '...the key they make and the name it is read by below them, and the button off while the key is what it was', control('keys-form-preview').textContent === '/template/page-services/item-1/title'
		&& k.form.querySelector('#keys-form-shown').textContent === 'Angezeigt als Eintrag 1 › Titel' && submit().disabled === true && submit().textContent === 'Umbenennen' );
	check( '...and no starting value, which a key that exists has', control('keys-form-new-value') === null && control('keys-form-new-global') === null );
	const name = control('keys-form-name');
	name.value = 'Titel';
	name.fire('input');
	check( 'a segment with a capital is marked as the user types, says what is wrong, and the button stays off', name.getAttribute('aria-invalid') === 'true' && control('keys-form-name-error').hidden === false
		&& name.getAttribute('aria-describedby') === 'keys-form-name-error' && submit().disabled === true && control('keys-form-name-error').textContent.includes( 'Bindestriche' ) );
	name.value = 'a.b';
	name.fire('input');
	check( '...so is one with a dot', name.getAttribute('aria-invalid') === 'true' && submit().disabled === true );
	name.value = 'heading-2';
	name.fire('input');
	check( 'a word of a key is not marked, and the button is on once the key is another', name.getAttribute('aria-invalid') === 'false' && control('keys-form-name-error').hidden === true && name.getAttribute('aria-describedby') === null && submit().disabled === false
		&& control('keys-form-preview').textContent === '/template/page-services/item-1/heading-2' );
	const part = control('keys-form-part');
	part.value = '';
	part.fire('input');
	check( 'a segment left empty keeps the button off without calling it wrong', submit().disabled === true && part.getAttribute('aria-invalid') === 'false' );
	part.value = 'item-1';
	part.fire('input');
	check( 'dirty once a segment is changed', k.keys.isDirty() === true );
	k.keys._saveNewKey();
	check( 'submitting renames the key to the one the segments make', lastWrite( k ).endpoint === 'rename' && JSON.stringify( lastWrite( k ).payload ) === '{"key":"/template/page-services/item-1/title","newKey":"/template/page-services/item-1/heading-2"}' );
}

{
	// the form that creates: /project to begin with, the rest after an explicit unlock
	const k = openedKeys( keysResponse() );
	k.keys._openNewKeyForm();
	const control = id => k.form.querySelector( '#'+ id );
	const options = id => control( id ).children.filter( c => c.tagName === 'option' ).map( o => o.value );
	const unlock = () => k.form.querySelectorAll('button').find( b => b.textContent === 'Entsperren' );
	const submit = () => k.form.querySelector('button[type="submit"]');

	check( 'a new key begins with /project alone to choose from, and a button that unlocks the others, with the reason for it beside them', k.keys._view === 'new' && k.keys._renameFrom === null && options('keys-form-namespace').join() === 'project' && unlock().hidden === false
		&& k.form.querySelectorAll('.nino-admin-hint').some( h => h.textContent.includes( 'Install-Einheit' ) && h.hidden === false ) );
	check( '...the category a field with the categories there are as suggestions - the ones the project is shipped with and the ones keys already use', control('keys-form-category').tagName === 'input' && control('keys-form-category').getAttribute('list') === 'keys-form-categories'
		&& control('keys-form-categories').children.map( o => o.value ).join() === 'company,website,mail,catalog' && control('keys-form-category').value === '' );
	check( '...a part, a name, whether it is the same in every language, and a starting value', control('keys-form-part').value === '' && control('keys-form-name').value === '' && control('keys-form-new-global') !== null && control('keys-form-new-value') !== null
		&& submit().disabled === true && submit().textContent === 'Anlegen' && k.form.querySelector('.main-title').textContent === 'Neuer Textschlüssel' );
	check( 'a form nobody touched holds nothing', k.keys.isDirty() === false );
	unlock().click();
	check( 'unlocking offers /template, /feature and /module - and never /_nino or /_admin, which the workbench does not create', options('keys-form-namespace').join() === 'project,template,feature,module' && unlock().hidden === true
		&& options('keys-form-namespace').every( o => o.indexOf('_') === -1 ) && k.form.querySelectorAll('.nino-admin-hint').some( h => h.textContent.includes( 'Install-Einheit' ) && h.hidden === false ) );
	control('keys-form-namespace').value = 'template';
	control('keys-form-namespace').fire('change');
	check( 'the namespace /template offers the templates there are and "common" as the category', control('keys-form-category').tagName === 'select' && options('keys-form-category').join() === 'common,frame-header,page-services' && control('keys-form-categories') === null );
	control('keys-form-namespace').value = 'feature';
	control('keys-form-namespace').fire('change');
	check( '/feature offers the features that are installed', options('keys-form-category').join() === 'consent' );
	control('keys-form-namespace').value = 'module';
	control('keys-form-namespace').fire('change');
	check( '/module the modules of the kernel', options('keys-form-category').join() === 'form,localepicker' );
	control('keys-form-namespace').value = 'project';
	control('keys-form-namespace').fire('change');
	control('keys-form-category').value = 'catalog';
	control('keys-form-category').fire('input');
	control('keys-form-part').value = 'list';
	control('keys-form-part').fire('input');
	control('keys-form-name').value = 'title';
	control('keys-form-name').fire('input');
	check( 'back at /project the category is a field again, and a key typed into the form is a key that can be made', control('keys-form-category').tagName === 'input' && control('keys-form-preview').textContent === '/project/catalog/list/title' && submit().disabled === false
		&& k.form.querySelector('#keys-form-shown').textContent === 'Angezeigt als Liste › Titel' && k.keys.isDirty() === true );
	control('keys-form-category').value = 'Catalog';
	control('keys-form-category').fire('input');
	check( 'a category of the project is checked like the other segments', control('keys-form-category').getAttribute('aria-invalid') === 'true' && submit().disabled === true && control('keys-form-category-error').hidden === false );
	control('keys-form-category').value = 'catalog';
	control('keys-form-category').fire('input');
	control('keys-form-new-value').value = 'Katalog';
	control('keys-form-new-global').checked = true;
	k.keys._saveNewKey();
	check( 'submitting creates the key the segments make, with its value and whether it is global', lastWrite( k ).endpoint === 'create' && JSON.stringify( lastWrite( k ).payload ) === '{"key":"/project/catalog/list/title","global":true,"value":"Katalog"}' );
}

{
	// a key somebody made up starts the form at /project with nothing in it; renaming it moves the key into the grammar
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'website' );
	k.form.querySelector('[data-key="/website/contact/uri"]').querySelectorAll('button').find( b => b.textContent === 'Umbenennen' ).click();
	const control = id => k.form.querySelector( '#'+ id );
	check( 'a key that follows no grammar starts at /project with the rest empty - the namespaces to the others locked', control('keys-form-namespace').value === 'project' && control('keys-form-namespace').children.length === 1
		&& control('keys-form-category').value === '' && control('keys-form-part').value === '' && control('keys-form-name').value === '' && k.keys._renameFrom === '/website/contact/uri' && k.keys.isDirty() === false );
	// ...and the form is drawn again as it was opened when its input is thrown away
	control('keys-form-part').value = 'x';
	k.keys.discard();
	check( 'discarding what was typed draws the form as it was opened', control('keys-form-part') !== null && k.form.querySelector('#keys-form-part').value === '' && k.keys.isDirty() === false );
}

{
	// a deep link into the tab, and only on its first load
	const k = openedKeys( keysResponse(), { panel : 'keys', parts : [ 'project', 'company', 'general', 'name' ] } );
	check( 'a hash that names a key opens its row at that key when the tab loads', k.keys._currentGroup === 'project/company' && k.keys._focusKey === '/project/company/general/name' && k.form.querySelector('[data-key="/project/company/general/name"]').classList.contains('is-found') );
	k.keys._showList();
	k.keys.init();
	check( '...and a reload after a change - which the hash still names - goes back to the list, as it always did', k.list.classList.contains('admin-hidden') === false );
}

{
	// a row named like a member of Object.prototype opens in the Keys tab as well
	const k = openedKeys( keysResponse( { keys : homeKeys.concat( [ textEntry( '/constructor/x', { de_DE : 'Frei' } ), textEntry( '/project/shop/constructor/name', { de_DE : 'Konstrukteur' } ) ] ) } ) );
	k.keys._openGroup( 'constructor' );
	check( 'a row named constructor opens in the Keys tab, and a search over keys with such a part answers', k.keys._currentGroup === 'constructor' && k.form.querySelector('[data-key="/constructor/x"]') !== null );
	k.keys._showList();
	const box = k.list.querySelector('#keys-search');
	box.value = 'konstrukteur';
	box.fire('input');
	check( '...the hit is the key', k.list.querySelectorAll('.admin-text-hit').length === 1 );
}

{
	// the tab has no list of pages, so it cannot say of a page's details that they have no page
	const k = openedKeys( keysResponse() );
	check( 'the row of the details of pages is called by the neutral "Seitenangaben" in the Keys tab', k.list.querySelectorAll('a').find( a => a.dataset.group === '_nino/webpage' ).querySelector('strong').textContent === 'Seitenangaben' );
}

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
