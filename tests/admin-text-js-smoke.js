/**
 *	Nino									A compact filesystembased php framework
 *	admin-text-js-smoke.js	DOM-free checks for the Text editor's
 *										multi-locale save planning, plus the Text Keys
 *										tab's scan form - which answers three questions
 *										per key and must send all three, or a row the
 *										operator meant to retire quietly comes back.
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
// editing a whole category at once
check( '...but still rendered, so the group reads as one list',
	textSource.includes( 'const globalEntries = entries.filter( function( e ) { return e.global === true } );' )
	&& textSource.includes( 'const localeEntries = entries.filter( function( e ) { return e.global === false } );' ) );
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

// the other forms of the tab
keys._view = 'new';
keysSandbox.document.getElementById = function( id ) {
	const fields = { 'keys-form': keysForm, 'keys-form-key' : { value : '' }, 'keys-form-new-value' : { value : '' }, 'keys-form-new-global' : { checked : false } };
	return fields[id] ?? { textContent : '' };
};
check( 'a new-key form nobody typed into holds nothing', keys.isDirty() === false );
keysSandbox.document.getElementById = function( id ) {
	const fields = { 'keys-form': keysForm, 'keys-form-key' : { value : '/a/b' }, 'keys-form-new-value' : { value : '' }, 'keys-form-new-global' : { checked : false } };
	return fields[id] ?? { textContent : '' };
};
check( '...a key typed into it does', keys.isDirty() === true );
keys.discard();
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

// the exits that reload the tab ask first
const asked = [];
keysSandbox.Nino.admin.dirty = { guard : function( names, proceed, onCancel ) { asked.push( { names : names, proceed : proceed, onCancel : onCancel } ) }, refresh : function() {}, register : function() {} };
requests.length = 0;
let undone = 0;
keys._saveSchema( '/g/title', true, false, function() { undone++ } );
keys._renameKey( '/g/title', '/g/headline' );
keys._deleteKey( '/g/title' );
check( 'a schema change, a rename and a delete ask about the open category before the request goes out', asked.length === 3 && requests.length === 0 && asked.every( function( a ) { return JSON.stringify( a.names ) === '["keys"]' } ) );
asked[0].onCancel();
check( '...a Cancel on a schema checkbox puts the box back', undone === 1 && requests.length === 0 );
asked[0].proceed();
asked[1].proceed();
asked[2].proceed();
check( '...an answer lets each request go', requests.map( function( r ) { return r.endpoint } ).join() === 'save,rename,delete' );
delete keysSandbox.Nino.admin.dirty;
requests.length = 0;
keys._renameKey( '/g/title', '/g/headline' );
check( 'a shell without the registry asks nothing', requests.length === 1 );

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

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
