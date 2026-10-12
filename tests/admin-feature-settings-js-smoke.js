/**
 *	Nino										A compact filesystembased php framework
 *	admin-feature-settings-js-smoke.js	DOM-light checks for the Settings tab a feature's
 *										own panel gets (_admin/Nino/Modules/Features/assets/settings.js):
 *										one namespace per mount, named by the tab the shell
 *										shows, that features/list is asked once and the form is
 *										drawn by the Features panel's own renderers, that Save
 *										posts features/settings with every field collected by
 *										data-key and the secret left empty, that the form is drawn
 *										again from the answer and an error says what failed, and
 *										that every word is a fill both interface languages define.
 *
 *	Usage: node tests/admin-feature-settings-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, source, fills, load, element, findAll, fire } = require( './harness.js' );

function textNode( text ) {
	return Object.assign( element('#text'), { nodeType : 3, textContent : String( text ) } );
}

function byTag( root, tag ) {
	return findAll( root, function( el ) { return el.tagName === tag.toUpperCase() } );
}

function hasClass( el, name ) {
	return String( el.className ).split(' ').indexOf( name ) !== -1 || el.classList.contains( name );
}

// --- the words

const moduleEn 		= fills('_admin/Nino/Modules/Features/text/en_US.php');
const moduleDe 		= fills('_admin/Nino/Modules/Features/text/de_DE.php');
const workbenchEn	= fills('_admin/text/en_US.php');
const workbenchDe	= fills('_admin/text/de_DE.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}

// --- the sandbox: the real Nino.adminUi and the Features panel's own renderers over the stand-in

// Two feature panels, each with its Settings tab as the registry renders it
// (see Panels::panesHtml()): a tab pane named by the tab's uri holding the mount
function tabPane( uri, mountId ) {
	const pane = element('div');
	pane.dataset.tab = uri;
	const mount = element('div');
	mount.id = mountId;
	pane.appendChild( mount );
	return { pane : pane, mount : mount };
}

const sampleTab = tabPane( 'sample-settings', 'feature-settings-sample' );
const otherTab	= tabPane( 'other-settings', 'feature-settings-other' );
const mounts		= [ sampleTab.mount, otherTab.mount ];

const requests = [];

const Nino = {
	dir : '/site',
	admin : { formToolbar : function( backLink ) { return Nino.adminUi.contextBar( backLink ) } },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		createTextNode : textNode,
		getElementById : function( id ) { return mounts.filter( function( m ) { return m.id === id } )[0] ?? null },
		querySelectorAll : function( selector ) { return selector === '[id^="feature-settings-"]' ? mounts : [] },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, location : { hash : '' } };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Features/assets/admin.js', context );

/** Answer the last request the script made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	const request = requests[requests.length - 1];
	request.callback( { status : status, responseJSON : body } );
	return request;
}

// The shell, with the registry of dirty forms: what a watched form is asked
const dirty = { watched : {}, snapshots : [], watchForm : function( name, getter, save ) { dirty.watched[name] = { getter : getter, save : save } }, snapshot : function( name ) { dirty.snapshots.push( name ) } };
Nino.admin.dirty = dirty;

load( '_admin/Nino/Modules/Features/assets/settings.js', context );

const script = source('_admin/Nino/Modules/Features/assets/settings.js');

// The entry features/list answers for the feature, as the panel's tests carry it
const ENTRY = { key : 'sample', name : 'Beispiel-Feature', active : true, settingsTab : 'sample-settings', settings : [
	{ name : 'enabled', type : 'bool', label : 'Aktiv', hint : '', required : false, min : null, max : null, maxlength : null, unit : '', options : [], value : true },
	{ name : 'limit', type : 'int', label : 'Limit', hint : 'Items per page', required : false, min : 1, max : 50, maxlength : null, unit : 'items', options : [], value : 7 },
	{ name : 'title', type : 'string', label : 'Title', hint : '', required : true, min : null, max : null, maxlength : 40, unit : '', options : [], value : 'Again' },
	{ name : 'mode', type : 'select', label : 'Mode', hint : '', required : false, min : null, max : null, maxlength : null, unit : '', options : [ { value : 'fast', label : 'Fast' }, { value : 'safe', label : 'Sicher' } ], value : 'safe' },
	{ name : 'apiKey', type : 'secret', label : 'API key', hint : '', required : false, min : null, max : null, maxlength : 1000, unit : '', options : [], set : true },
	{ name : 'hosts', type : 'lines', label : 'Hosts', hint : '', required : false, min : null, max : null, maxlength : null, unit : '', options : [], value : [ 'one', 'two' ] },
] };
const OTHER = Object.assign( {}, ENTRY, { key : 'other', settingsTab : 'other-settings', settings : [] } );

function listAnswer( features ) {
	return { dir : '/features', catalogueUrl : '', writable : true, catalogue : null, features : features };
}

// --- one namespace per mount, named by the tab the shell shows

check( 'every mount defines the namespace its tab\'s uri names, with the one function the shell calls', typeof Nino.admin['sample-settings'] === 'object' && typeof Nino.admin['sample-settings'].showCurrent === 'function'
	&& typeof Nino.admin['other-settings'] === 'object' && Nino.admin['sample-settings'] !== Nino.admin['other-settings'] );
check( 'nothing is asked for until a tab is shown, and the mounts are empty', requests.length === 0 && sampleTab.mount.children.length === 0 );
check( 'both forms are registered with the shell under their tab\'s uri, and neither is drawn yet', Object.keys( dirty.watched ).sort().join() === 'other-settings,sample-settings'
	&& dirty.watched['sample-settings'].getter() === null );

// --- showing the tab: features/list once, the form from the entry

Nino.admin['sample-settings'].showCurrent();
check( 'showing the tab posts features/list - the action the Features panel already has - through the shell\'s endpoint', requests.length === 1 && requests[0].action === 'features/list' && requests[0].uri === '/site/_admin/' );
answer( 200, listAnswer( [ OTHER, ENTRY ] ) );
check( 'the answer\'s entry for this mount\'s key is drawn, the other feature\'s is not', byTag( sampleTab.mount, 'form' ).length === 1 && byTag( otherTab.mount, 'form' ).length === 0 );

const form = byTag( sampleTab.mount, 'form' )[0];
const controls = form.querySelectorAll('[data-key]');
check( 'the fields are the Features panel\'s own, one control per setting in schema order', controls.map( function( el ) { return el.dataset.key } ).join(',') === 'enabled,limit,title,mode,apiKey,hosts' );
check( 'the group names itself with one legend - a lone tab pane has no strip to say what the form is', byTag( form, 'fieldset' ).length === 1 && byTag( form, 'legend' ).length === 1
	&& byTag( form, 'legend' )[0].textContent === text('/_admin/features/label/settings') );
const save = byTag( form, 'button' ).filter( function( el ) { return el.type === 'submit' } )[0];
const bar = save.parentNode;
const line = byTag( bar, 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0];
check( 'an action bar holds a Save button and a status line that is announced', hasClass( bar, 'nino-admin-actionbar' ) && save.textContent === text('/_admin/common/label/save') && line !== undefined && line.textContent === '' );
check( 'the secret\'s input is empty whatever is stored', controls.filter( function( el ) { return el.dataset.key === 'apiKey' } )[0].value === '' );
check( 'the form is what the shell asks about unsaved input, and was taken as saved when drawn', dirty.watched['sample-settings'].getter() === form && dirty.snapshots.join() === 'sample-settings' );

// Showing it again keeps what was typed
controls.filter( function( el ) { return el.dataset.key === 'title' } )[0].value = 'Typed';
Nino.admin['sample-settings'].showCurrent();
check( 'showing the tab again asks for nothing and keeps the form and what was typed into it', requests.length === 1 && byTag( sampleTab.mount, 'form' )[0] === form
	&& form.querySelectorAll('[data-key]').filter( function( el ) { return el.dataset.key === 'title' } )[0].value === 'Typed' );

// --- Save

fire( form, 'submit' );
const posted = requests[requests.length - 1];
check( 'Save posts features/settings with the key of this mount and every field collected by data-key', posted.action === 'features/settings' && posted.payload.key === 'sample'
	&& Object.keys( posted.payload.fields ).sort().join(',') === 'apiKey,enabled,hosts,limit,mode,title' );
check( 'a switch posts its state, a number its text, a list its raw lines, the secret the empty string that keeps it, a typed value as typed', posted.payload.fields.enabled === true && posted.payload.fields.limit === '7'
	&& posted.payload.fields.hosts === 'one\ntwo' && posted.payload.fields.apiKey === '' && posted.payload.fields.title === 'Typed' && posted.payload.fields.mode === 'safe' );
check( 'while it saves the button is held and the line says so', save.disabled === true && line.textContent === text('/_admin/common/msg/saving') );

answer( 400, { error : 'limit: must be at most 50' } );
check( 'a refusal is told as an error with its status, and the button is free', save.disabled === false && hasClass( line, 'nino-admin-error' ) && line.textContent === '(400) limit: must be at most 50' );
check( '...on the same form, which keeps what was typed', byTag( sampleTab.mount, 'form' )[0] === form );

fire( form, 'submit' );
answer( 500, null );
check( 'a failure without an answer falls back to the fill for saving', hasClass( line, 'nino-admin-error' ) && line.textContent === '(500) '+ text('/_admin/common/error/save') );

fire( form, 'submit' );
const savedEntry = Object.assign( {}, ENTRY, { settings : ENTRY.settings.map( function( f ) { return f.name === 'title' ? Object.assign( {}, f, { value : 'Typed' } ) : f } ) } );
answer( 200, { feature : savedEntry } );
const rebuilt = byTag( sampleTab.mount, 'form' )[0];
check( 'a saved form is drawn again from the entry the answer carries, without asking features/list again', rebuilt !== form && requests[requests.length - 1].action === 'features/settings'
	&& rebuilt.querySelectorAll('[data-key]').filter( function( el ) { return el.dataset.key === 'title' } )[0].value === 'Typed' );
check( '...says it was saved, and takes the new form as saved', byTag( rebuilt, 'p' ).some( function( el ) { return el.textContent === text('/_admin/common/msg/saved') } )
	&& dirty.snapshots.length === 2 && dirty.watched['sample-settings'].getter() === rebuilt );

// The shell's question about unsaved input saves through the same request
let ended = null;
dirty.watched['sample-settings'].save( function( ok ) { ended = ok } );
check( 'a save the shell asks for posts the form and reports how it ended', requests[requests.length - 1].action === 'features/settings' && ended === null );
answer( 200, { feature : savedEntry } );
check( '...true when it was written', ended === true );
dirty.watched['sample-settings'].save( function( ok ) { ended = ok } );
answer( 400, { error : 'no' } );
check( '...and false when it was not', ended === false );

// --- a tab that cannot draw its form

Nino.admin['other-settings'].showCurrent();
answer( 200, listAnswer( [ ENTRY ] ) );
check( 'a feature the list no longer holds leaves an error line instead of a form', byTag( otherTab.mount, 'form' ).length === 0
	&& findAll( otherTab.mount, function( el ) { return hasClass( el, 'nino-admin-error' ) } ).length === 1 );
const before = requests.length;
Nino.admin['other-settings'].showCurrent();
check( 'the tab asks again the next time it is shown', requests.length === before + 1 );
answer( 403, { error : 'not allowed' } );
check( 'a refused list is told with its status', findAll( otherTab.mount, function( el ) { return hasClass( el, 'nino-admin-error' ) } )[0].textContent === '(403) not allowed' );
Nino.admin['other-settings'].showCurrent();
answer( 200, listAnswer( [ ENTRY ] ) );
check( 'a list that answers without the feature is told as a 404, not as a 200', findAll( otherTab.mount, function( el ) { return hasClass( el, 'nino-admin-error' ) } )[0].textContent.indexOf( '(404) ' ) === 0 );
Nino.admin['other-settings'].showCurrent();
answer( 200, listAnswer( [ OTHER ] ) );
check( 'and draws the form once the list has the feature', byTag( otherTab.mount, 'form' ).length === 1 );

// --- the words

const keys = [];
const keyRe = /getText\(\s*'(\/_admin\/[^']+)'\s*\)/g;
const fallbackRe = /'(\/_admin\/common\/error\/[^']+)'/g;
let match;
while( ( match = keyRe.exec( script ) ) )
	if( keys.indexOf( match[1] ) === -1 )
		keys.push( match[1] );
while( ( match = fallbackRe.exec( script ) ) )
	if( keys.indexOf( match[1] ) === -1 )
		keys.push( match[1] );
const missing = keys.filter( function( key ) {
	return ( moduleEn[key] || workbenchEn[key] ) === undefined || ( moduleDe[key] || workbenchDe[key] ) === undefined;
} );
check( 'every fill the script asks for exists in both interface languages'+ ( missing.length ? ' - missing: '+ missing.join(', ') : '' ), keys.length >= 4 && missing.length === 0 );
check( 'the tab\'s own name is a fill of the module in both languages', moduleEn['/_admin/features/tab/settings'] === 'Settings' && moduleDe['/_admin/features/tab/settings'] === 'Einstellungen' );

const hardcoded = [];
script.split('\n').forEach( function( row, index ) {
	if( /^\s*(\/\/|\*|\/\*)/.test( row ) )
		return;
	const re = /\.(?:textContent|placeholder|title)\s*=\s*'([A-Z][^']*\s[^']*)'/g;
	let found;
	while( ( found = re.exec( row ) ) )
		hardcoded.push( ( index + 1 )+ ' '+ JSON.stringify( found[1] ) );
} );
check( 'the script writes no sentence of its own into the dom'+ ( hardcoded.length ? ' - '+ hardcoded.join(', ') : '' ), hardcoded.length === 0 );
check( 'it posts nothing but the two actions the Features panel already has', ( script.match( /call\( '[a-z]+'/g ) || [] ).sort().join() === "call( 'list',call( 'settings'" );

done();
