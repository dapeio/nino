/**
 *	Nino									A compact filesystembased php framework
 *	admin-features-js-smoke.js	DOM-light checks for the Features panel's script
 *										(_admin/Nino/Modules/Features/assets/admin.js): that it
 *										attaches under its nav uri, which actions it posts and
 *										with what payload, that every word it renders is a fill
 *										both interface languages define, that a secret's input
 *										is always empty, and that one Save collects every
 *										setting of its feature by data-key - and the three tabs
 *										(Available, Inactive, Active) with their counts, the
 *										action bar's Refresh catalogue button and status line,
 *										that the Available tab fills from what features/list
 *										already carries (no request of its own), that Refresh
 *										posts features/catalogue, every empty state, and the
 *										switched-off catalogue. The real Nino.adminUi renders
 *										the shared controls, over a dependency-free element
 *										stand-in.
 *
 *	Usage: node tests/admin-features-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

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

// --- an element stand-in, the same shape tests/nino-ui-elementlist-js-smoke.js builds

function classList( el ) {
	const values = new Set();
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
		toggle : function( value, force ) {
			if( force === true ) values.add( value );
			else if( force === false ) values.delete( value );
			else if( values.has( value ) ) values.delete( value );
			else values.add( value );
			return values.has( value );
		},
	};
}

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
		value : '',
		type : '',
		checked : false,
		disabled : false,
		hidden : false,
		id : '',
		href : '',
		style : {},
		dataset : {},
		attributes : {},
		children : [],
		listeners : {},
		appendChild : function( child ) { el.children.push( child ); return child },
		// A data-* attribute is a dataset entry, as in a browser - the shared
		// select writes its key with setAttribute(), the panel reads dataset
		setAttribute : function( name, value ) {
			el.attributes[name] = String( value );
			if( name.indexOf( 'data-' ) === 0 )
				el.dataset[ name.slice( 5 ).replace( /-([a-z])/g, function( m, c ) { return c.toUpperCase() } ) ] = String( value );
		},
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
		// The one query the panel makes: every control carrying a setting name
		querySelectorAll : function( selector ) {
			return selector === '[data-key]' ? findAll( el, function( node ) { return typeof node.dataset.key === 'string' } ) : [];
		},
	};
	el.classList = classList( el );
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	// A control's value is always a string in a browser, whatever was
	// assigned - the shared number field assigns a number, the panel trims
	let value = '';
	Object.defineProperty( el, 'value', {
		get : function() { return value },
		set : function( next ) { value = String( next ) },
	} );
	return el;
}

function textNode( text ) {
	const node = { nodeType : 3, textContent : String( text ), className : '', children : [], dataset : {} };
	node.classList = classList( node );
	return node;
}

function fire( el, name ) {
	( el.listeners[name] || [] ).forEach( function( fn ) { fn( { preventDefault : function() {} } ) } );
}

function byTag( root, tag ) {
	return findAll( root, function( el ) { return el.tagName === tag.toUpperCase() } );
}

function hasClass( el, name ) {
	return String( el.className ).split(' ').indexOf( name ) !== -1 || el.classList.contains( name );
}

function section( root, key ) {
	return byTag( root, 'section' ).filter( function( el ) { return el.dataset.feature === key } )[0];
}

function offer( root, key ) {
	return byTag( root, 'section' ).filter( function( el ) { return el.dataset.offer === key } )[0];
}

// --- the words

const moduleEn 		= fills('_admin/Nino/Modules/Features/text/en_US.php');
const moduleDe 		= fills('_admin/Nino/Modules/Features/text/de_DE.php');
const workbenchEn	= fills('_admin/text/en_US.php');
const workbenchDe	= fills('_admin/text/de_DE.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}

// --- the sandbox: the real Nino.adminUi over the stand-in, the shell's namespace stubbed

const mount = element('div');
mount.id = 'features-list';

const callbacks = [];
const requests = [];
let reloads = 0;

const Nino = {
	admin : {},
	events : { bindCallback : function( event, callback ) { if( event === 'ready' ) callbacks.push( callback ) } },
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
		getElementById : function( id ) { return id === 'features-list' ? mount : null },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, location : { hash : '', reload : function() { reloads++ } } };

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
vm.runInContext( source('_admin/Nino/Modules/Features/assets/admin.js'), context, { filename : 'admin.js' } );

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	const request = requests[requests.length - 1];
	request.callback( { status : status, responseJSON : body } );
	return request;
}

const script = source('_admin/Nino/Modules/Features/assets/admin.js');
const panel 	= Nino.admin.features;

// The installed features fixture: 'old' inactive with problems (no button at
// all), 'fresh' inactive without problems (Activate alone), 'plain' active
// with an update waiting (Update + Deactivate), 'sample' active with a full
// settings schema - so Inactive is { old, fresh } and Active is { plain, sample }
const FEATURES = [
	{ key : 'old', name : 'Old', description : '', version : '3.0.0', installed : null, active : false, update : false, requires : [ 'nowhere' ],
		problems : [ 'requires Nino ^0.9, this is 1.0.0', 'requires the php extension "no_such_extension"' ], settings : [] },
	{ key : 'plain', name : 'Plain', description : 'Nothing to set.', version : '1.0.0', installed : '0.9.0', active : true, update : true, requires : [], problems : [], settings : [] },
	{ key : 'sample', name : 'Beispiel-Feature', description : 'Prüft den ganzen Feature-Vertrag.', version : '1.2.0', installed : '1.2.0', active : true, update : false, requires : [ 'helper' ], problems : [], settings : [
		{ name : 'enabled', type : 'bool', label : 'Aktiv', hint : '', required : false, min : null, max : null, maxlength : null, unit : '', options : [], value : true },
		{ name : 'limit', type : 'int', label : 'Limit', hint : 'Items per page', required : false, min : 1, max : 50, maxlength : null, unit : 'items', options : [], value : 7 },
		{ name : 'title', type : 'string', label : 'Title', hint : '', required : true, min : null, max : null, maxlength : 40, unit : '', options : [], value : 'Again' },
		{ name : 'notes', type : 'text', label : 'Notes', hint : '', required : false, min : null, max : null, maxlength : 10000, unit : '', options : [], value : 'a\nb' },
		{ name : 'contact', type : 'email', label : 'Contact', hint : '', required : false, min : null, max : null, maxlength : 1000, unit : '', options : [], value : '' },
		{ name : 'site', type : 'url', label : 'Site', hint : '', required : false, min : null, max : null, maxlength : 1000, unit : '', options : [], value : 'https://example.com' },
		{ name : 'mode', type : 'select', label : 'Mode', hint : '', required : false, min : null, max : null, maxlength : null, unit : '', options : [ { value : 'fast', label : 'Fast' }, { value : 'safe', label : 'Sicher' } ], value : 'fast' },
		{ name : 'apiKey', type : 'secret', label : 'API key', hint : '', required : false, min : null, max : null, maxlength : 1000, unit : '', options : [], set : true },
		{ name : 'hosts', type : 'lines', label : 'Hosts', hint : '', required : false, min : null, max : null, maxlength : null, unit : '', options : [], value : [ 'one', 'two' ] },
	] },
	{ key : 'fresh', name : 'Fresh', description : 'Not switched on yet.', version : '0.1.0', installed : null, active : false, update : false, requires : [], problems : [], settings : [] },
];

/** A features/list answer: the catalogue url, whether features/ is writable, the cache - null unless given - and the installed features, FEATURES unless a list of its own is given (an install adds one the fixture does not carry) */
function listAnswer( catalogueUrl, writable, cache, features ) {
	return { dir : '/features', catalogueUrl : catalogueUrl, writable : writable, catalogue : cache || null, features : features || FEATURES };
}

// The catalogue as features/catalogue - and, once cached, features/list - name
// its offers: 'ancient' and 'needy' incompatible (never on disk), 'extra'
// available, 'helper' an upgrade over what is on disk, 'sample' already
// current - the one state the Available tab excludes
const OFFERS = [
	{ key : 'ancient', name : 'Ancient', description : 'Ein ancient', version : '1.0.0', nino : '^0.9', ext : [], requires : [], directory : 'Ancient', archive : 'https://catalogue.test/features/ancient-1.0.0.tar.gz', size : 10, released : '2026-09-07', state : 'incompatible', fits : false, local : null, active : false },
	{ key : 'extra', name : 'Zusatz', description : 'Ein extra', version : '1.0.0', nino : '^1.0', ext : [], requires : [ 'helper' ], directory : 'Extra', archive : 'https://catalogue.test/features/extra-1.0.0.tar.gz', size : 10, released : '2026-09-07', state : 'available', fits : true, local : null, active : false },
	{ key : 'helper', name : 'Helper', description : '', version : '1.2.0', nino : '^1.0', ext : [], requires : [], directory : 'Helper', archive : 'https://catalogue.test/features/helper-1.2.0.tar.gz', size : 10, released : '', state : 'upgrade', fits : true, local : '1.1.0', active : true },
	{ key : 'needy', name : 'Needy', description : 'Ein needy', version : '1.0.0', nino : '^1.0', ext : [ 'no_such_extension', 'other' ], requires : [], directory : 'Needy', archive : 'https://catalogue.test/features/needy-1.0.0.tar.gz', size : 10, released : '2026-09-07', state : 'incompatible', fits : false, local : null, active : false },
	{ key : 'sample', name : 'Beispiel-Feature', description : 'Ein sample', version : '1.2.0', nino : '^1.0', ext : [], requires : [ 'helper' ], directory : 'Sample', archive : 'https://catalogue.test/features/sample-1.2.0.tar.gz', size : 10, released : '2026-09-07', state : 'current', fits : true, local : '1.2.0', active : true },
];

const CACHE = { url : 'https://catalogue.getnino.dev/catalogue.json', fetched : '2026-09-07 12:00', offers : OFFERS };

// features/catalogue's own shape - 'generated' and 'writable' beside it, no 'url' collision
function catalogueAnswer( writable, offers, fetched ) {
	return { url : CACHE.url, generated : '2026-09-07T12:00:00Z', fetched : fetched || CACHE.fetched, writable : writable, offers : offers || OFFERS };
}

console.log('Features panel');

check( 'the script attaches under its nav uri with init and showCurrent', typeof panel === 'object' && typeof panel.init === 'function' && typeof panel.showCurrent === 'function' );
check( 'and binds its ready callback', callbacks.length === 1 && callbacks[0] === panel.init );

// The backend half of the same contract, read from the class
const admin = source('_admin/Nino/Modules/Features/Admin/Admin.php');
check( 'the panel is a system entry with the nav uri the script speaks, one mount point and six actions',
	admin.includes( "return [ 'features', '/_admin/nav/features', 15, 'system' ];" ) && admin.includes( "return [ 'features-list' ];" ) && script.includes( "'features-list'" )
	&& [ 'features/list', 'features/activate', 'features/deactivate', 'features/settings', 'features/catalogue', 'features/install' ].every( function( action ) { return admin.includes( "'"+ action+ "'" ) } ) );
check( 'every action method guards itself with the panel\'s permission', ( admin.match( /guardPerm\( \$appData, \$request, self::MANAGE_PERM \)/g ) || [] ).length === 6 );
check( 'the script posts those six actions and no other',
	script.includes( "action : 'features/'+ endpoint" ) && script.includes( "_apiCall( 'list'" ) && script.includes( "_apiCall( 'settings'" )
	&& script.includes( "? 'deactivate' : 'activate'" ) && script.includes( "_apiCall( 'catalogue'" ) && script.includes( "_apiCall( 'install'" ) && ( script.match( /_apiCall\( /g ) || [] ).length === 5 );

// --- one language, one text system

const keys = [];
const keyRe = /getText\(\s*'(\/_admin\/[^']+)'\s*\)/g;
let match;
while( ( match = keyRe.exec( script ) ) )
	if( keys.indexOf( match[1] ) === -1 )
		keys.push( match[1] );
const missing = keys.filter( function( key ) {
	return ( moduleEn[key] || workbenchEn[key] ) === undefined || ( moduleDe[key] || workbenchDe[key] ) === undefined;
} );
check( 'every fill the script asks for exists in both interface languages'+ ( missing.length ? ' - missing: '+ missing.join(', ') : '' ), keys.length > 0 && missing.length === 0 );
check( 'the three tab labels and the reload hint are among them', [ 'available', 'inactive', 'active' ].every( function( t ) { return keys.indexOf( '/_admin/features/tab/'+ t ) !== -1 } ) && keys.indexOf( '/_admin/features/msg/reload' ) !== -1 );
check( 'no eyebrow status word is asked for any more - the tab and the version line carry that now', keys.indexOf( '/_admin/features/status/active' ) === -1 && keys.indexOf( '/_admin/features/status/incompatible' ) === -1 );

// The words the class phrases itself - a catalogue refusal, an update that
// did not apply - are fills too, read server-side in the interface language
const phrased = [];
const phrasedRe = /_say\( \$appData, '(\/_admin\/[^']+)'/g;
while( ( match = phrasedRe.exec( admin ) ) )
	if( phrased.indexOf( match[1] ) === -1 )
		phrased.push( match[1] );
const unphrased = phrased.filter( function( key ) { return moduleEn[key] === undefined || moduleDe[key] === undefined; } );
check( 'every message the class phrases itself is a fill of the module in both languages'+ ( unphrased.length ? ' - missing: '+ unphrased.join(', ') : '' ), phrased.length >= 4 && unphrased.length === 0
	&& phrased.indexOf( '/_admin/features/error/catalogue-off' ) !== -1 && phrased.indexOf( '/_admin/features/error/catalogue-key' ) !== -1
	&& moduleEn['/_admin/features/error/catalogue-reason'].includes( '%s' ) && moduleDe['/_admin/features/error/catalogue-reason'].includes( '%s' )
	&& moduleEn['/_admin/features/error/update-after-install'].includes( '%s' ) && moduleDe['/_admin/features/error/update-after-install'].includes( '%s' ) );
check( 'the module\'s two text files declare the same keys', Object.keys( moduleEn ).sort().join(',') === Object.keys( moduleDe ).sort().join(',') && Object.keys( moduleEn ).length > 20 );
check( 'the nav label and the dashboard tile are among them, and no value carries a live shortcode', moduleEn['/_admin/nav/features'] !== undefined && moduleEn['/_admin/features/label/active'] !== undefined
	&& Object.keys( moduleEn ).every( function( key ) { return /[[\]]/.test( moduleEn[key] ) === false && /[[\]]/.test( moduleDe[key] ) === false } ) );
check( 'the intro hint and the eyebrow badge fills are gone', moduleEn['/_admin/features/hint/intro'] === undefined && moduleEn['/_admin/features/label/catalogue'] === undefined
	&& moduleEn['/_admin/features/label/catalogue-load'] === undefined && moduleEn['/_admin/features/label/generated'] === undefined );

// The same rule tests/admin-lists-js-smoke.js applies to every panel script:
// no sentence assigned straight to what the user reads
const hardcoded = [];
script.split('\n').forEach( function( line, index ) {
	if( /^\s*(\/\/|\*|\/\*)/.test( line ) )
		return;
	const re = /\.(?:textContent|placeholder|title)\s*=\s*'([A-Z][^']*\s[^']*)'/g;
	let found;
	while( ( found = re.exec( line ) ) )
		hardcoded.push( ( index + 1 )+ ' '+ JSON.stringify( found[1] ) );
} );
check( 'the script writes no sentence of its own into the dom'+ ( hardcoded.length ? ' - '+ hardcoded.join(', ') : '' ), hardcoded.length === 0 );

// --- opening the panel: no cache yet

panel.init();
check( 'init loads features/list through the shell\'s one endpoint', requests.length === 1 && requests[0].action === 'features/list' && requests[0].uri === '/_admin/' && requests[0].method === 'POST' );

answer( 200, listAnswer( CACHE.url, true, null ) );
check( 'opening the panel fetches nothing but the list - no catalogue request follows on its own', requests.length === 1 );
check( 'there is no intro line and no eyebrow badge - just the tab strip, the action bar and the current tab', mount.children.length === 3
	&& hasClass( mount.children[0], 'nino-admin-tabs' ) && hasClass( mount.children[0], 'nino-admin-tabs--bar' ) && hasClass( mount.children[0], 'admin-panel-tabs' )
	&& findAll( mount, function( el ) { return hasClass( el, 'nino-admin-hint-lead' ) } ).length === 0 && findAll( mount, function( el ) { return hasClass( el, 'nino-admin-eyebrow' ) } ).length === 0 );

// --- the tab strip

const tabs = byTag( mount.children[0], 'button' );
check( 'three tabs, in order, each labelled with its count - Inactive 2 (old, fresh), Active 2 (plain, sample)', tabs.length === 3
	&& tabs[0].textContent === text('/_admin/features/tab/available')+ ' (0)' && tabs[1].textContent === text('/_admin/features/tab/inactive')+ ' (2)' && tabs[2].textContent === text('/_admin/features/tab/active')+ ' (2)' );
check( 'Available is the tab on screen, role=tab/tablist throughout, aria-selected in step with the active one', mount.children[0].attributes.role === 'tablist' && tabs.every( function( t ) { return t.attributes.role === 'tab' } )
	&& hasClass( tabs[0], 'is-active' ) && tabs[0].attributes['aria-selected'] === 'true' && hasClass( tabs[1], 'is-active' ) === false && tabs[1].attributes['aria-selected'] === 'false' );

// --- the action bar

check( 'the action bar carries the shared classes and one button plus a status line', hasClass( mount.children[1], 'nino-admin-actionbar' ) && hasClass( mount.children[1], 'nino-admin-list-actions' )
	&& byTag( mount.children[1], 'button' ).length === 1 && byTag( mount.children[1], 'button' )[0].textContent === text('/_admin/features/label/catalogue-refresh') && hasClass( byTag( mount.children[1], 'button' )[0], 'nino-admin-btn-secondary' ) );
const status = byTag( mount.children[1], 'p' )[0];
check( 'without a cache yet the status says so, not an error', status.textContent === text('/_admin/features/label/catalogue-unloaded') && hasClass( status, 'nino-admin-error' ) === false && status.attributes['aria-live'] === 'polite' );

// --- the Available tab before anything was ever fetched

check( 'the Available tab explains there is nothing cached yet', findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/available-unloaded') );

// --- Inactive and Active

fire( tabs[1], 'click' );
check( 'Inactive lists the features that are off, in the backend\'s order, no button at all for one with problems', byTag( mount, 'section' ).map( function( el ) { return el.dataset.feature } ).join(',') === 'old,fresh'
	&& byTag( section( mount, 'old' ), 'button' ).length === 0 && byTag( section( mount, 'old' ), 'form' ).length === 0 );
check( 'a card carries no status badge any more - just its name', section( mount, 'fresh' ).children[0].tagName === 'H3' && section( mount, 'fresh' ).children[0].textContent === 'Fresh' );
check( 'and an inactive feature without problems offers Activate alone', byTag( section( mount, 'fresh' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/activate') && hasClass( byTag( section( mount, 'fresh' ), 'button' )[0], 'nino-admin-btn-primary' ) );
check( 'the requirements of a problem feature are still named', byTag( section( mount, 'old' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/requires').replace( '%s', 'nowhere' ) } )
	&& byTag( section( mount, 'old' ), 'p' ).filter( function( el ) { return hasClass( el, 'nino-admin-error' ) } ).map( function( el ) { return el.textContent } ).join('|') === FEATURES[0].problems.join('|') );

fire( tabs[2], 'click' );
check( 'Active lists the features that are on', byTag( mount, 'section' ).map( function( el ) { return el.dataset.feature } ).join(',') === 'plain,sample' );
check( 'an update offers Update and Deactivate, its installed version named', byTag( section( mount, 'plain' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/version').replace( '%s', '1.0.0' )+ ' – '+ text('/_admin/features/label/installed').replace( '%s', '0.9.0' ) } )
	&& byTag( section( mount, 'plain' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/update').replace( '%s', '1.0.0' )+ '|'+ text('/_admin/features/label/deactivate') );

// --- the settings form (Active tab)

const sample = section( mount, 'sample' );
const form 	 = byTag( sample, 'form' )[0];
const controls = form ? form.querySelectorAll('[data-key]') : [];
check( 'an active feature with settings gets a form, every setting a control carrying its name in schema order', form !== undefined && controls.map( function( el ) { return el.dataset.key } ).join(',') === 'enabled,limit,title,notes,contact,site,mode,apiKey,hosts' );
check( 'the form sits in a fieldset legended from the text system', byTag( form, 'fieldset' ).length === 1 && byTag( form, 'legend' )[0].textContent === text('/_admin/features/label/settings') );

const byKey = {};
controls.forEach( function( el ) { byKey[el.dataset.key] = el } );
check( 'a bool is the shared switch', byKey.enabled.type === 'checkbox' && byKey.enabled.checked === true && findAll( form, function( el ) { return hasClass( el, 'nino-admin-switch-state' ) } ).length === 1 );
const limitLabel = findAll( form, function( el ) { return el.tagName === 'LABEL' && el.children.indexOf( byKey.limit ) !== -1 } )[0];
check( 'an int is the shared bounded number input, its unit written into the label when the workbench has no word for it', byKey.limit.type === 'number' && byKey.limit.value === '7' && byKey.limit.min === 1 && byKey.limit.max === 50 && limitLabel.children[0].textContent === 'Limit (items)' );
check( 'a string, an email and a url are inputs of that type, required and capped as the schema says', byKey.title.type === 'text' && byKey.title.value === 'Again' && byKey.title.required === true && byKey.title.maxLength === 40
	&& byKey.contact.type === 'email' && byKey.contact.value === '' && byKey.site.type === 'url' && byKey.site.value === 'https://example.com' );
check( 'a text and a list are textareas, the list one entry per line', byKey.notes.tagName === 'TEXTAREA' && byKey.notes.value === 'a\nb' && byKey.hosts.tagName === 'TEXTAREA' && byKey.hosts.value === 'one\ntwo' );
check( 'a select carries its options with their localized labels and the current value', byKey.mode.tagName === 'SELECT' && byKey.mode.value === 'fast' && byKey.mode.children.map( function( el ) { return el.value+ '='+ el.textContent } ).join(',') === 'fast=Fast,safe=Sicher' );
check( 'a secret is a password input that renders empty although one is stored', byKey.apiKey.type === 'password' && byKey.apiKey.value === '' && byKey.apiKey.autocomplete === 'new-password' );
const apiKeyLabel = findAll( form, function( el ) { return el.tagName === 'LABEL' && el.children.indexOf( byKey.apiKey ) !== -1 } )[0];
check( 'and its hint says one is stored and how to keep it', byTag( apiKeyLabel, 'small' ).some( function( el ) { return el.textContent === text('/_admin/features/hint/secret-set') } ) );
check( 'no field label is the raw setting name - every one is the schema\'s label', findAll( form, function( el ) { return el.tagName === 'SPAN' && [ 'enabled', 'limit', 'title', 'apiKey', 'hosts' ].indexOf( el.textContent ) !== -1 } ).length === 0 );

const save = byTag( form, 'button' )[0];
check( 'one Save per feature, the primary action, labelled from the workbench\'s words', byTag( form, 'button' ).length === 1 && save.type === 'submit' && save.textContent === text('/_admin/common/label/save') );

fire( form, 'submit' );
const posted = requests[requests.length - 1];
check( 'saving posts features/settings with the feature\'s key and every field collected by data-key', posted.action === 'features/settings' && posted.payload.key === 'sample'
	&& Object.keys( posted.payload.fields ).sort().join(',') === 'apiKey,contact,enabled,hosts,limit,mode,notes,site,title' );
check( 'a switch posts its state, a number its text, a list its raw lines, the secret the empty string that keeps it', posted.payload.fields.enabled === true && posted.payload.fields.limit === '7'
	&& posted.payload.fields.hosts === 'one\ntwo' && posted.payload.fields.apiKey === '' && posted.payload.fields.mode === 'fast' && posted.payload.fields.title === 'Again' );
const saveMsg = byTag( form, 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0];
check( 'while it saves, the button is held and the status says so', save.disabled === true && saveMsg.textContent === text('/_admin/common/msg/saving') );

answer( 400, { error : 'limit: must be at most 50' } );
check( 'a rejected form shows the fields the backend named, as an error, and frees the button', save.disabled === false && hasClass( saveMsg, 'nino-admin-error' ) && saveMsg.textContent === '(400) limit: must be at most 50' );

fire( form, 'submit' );
answer( 200, { feature : FEATURES[2] } );
check( 'a saved form reloads the list rather than trusting what was typed', requests[requests.length - 1].action === 'features/list' );
answer( 200, listAnswer( CACHE.url, true, null ) );
check( 'the panel stayed on Active across the reload, and the confirmation survives the re-render', byTag( mount, 'section' ).map( function( el ) { return el.dataset.feature } ).join(',') === 'plain,sample' );
const rebuilt = byTag( section( mount, 'sample' ), 'form' )[0];
check( '...on the rebuilt form', rebuilt !== form && byTag( rebuilt, 'p' ).some( function( el ) { return el.textContent === text('/_admin/common/msg/saved') } ) );

// --- switching on and off (from the tab the feature is on)

fire( tabs[1], 'click' );
const activate = byTag( section( mount, 'fresh' ), 'button' )[0];
const freshMsg = byTag( section( mount, 'fresh' ), 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0];
fire( activate, 'click' );
check( 'Activate posts features/activate with the key', requests[requests.length - 1].action === 'features/activate' && requests[requests.length - 1].payload.key === 'fresh' && activate.disabled === true && freshMsg.textContent === text('/_admin/features/msg/activating') );
answer( 400, { error : 'feature "fresh" cannot be activated: nope' } );
check( 'a refusal shows the kernel\'s reason and reloads nothing', reloads === 0 && activate.disabled === false && hasClass( freshMsg, 'nino-admin-error' ) && freshMsg.textContent === '(400) feature "fresh" cannot be activated: nope' );
fire( activate, 'click' );
answer( 200, { feature : FEATURES[3] } );
check( 'success says so, keeps the hash on the panel and reloads the workbench', reloads === 1 && sandbox.window.location.hash === '#features' && freshMsg.textContent === text('/_admin/features/msg/activated')+ ' '+ text('/_admin/features/msg/reload') );

fire( tabs[2], 'click' );
const deactivate = byTag( section( mount, 'sample' ), 'button' ).filter( function( el ) { return el.textContent === text('/_admin/features/label/deactivate') } )[0];
fire( deactivate, 'click' );
check( 'Deactivate posts features/deactivate with the key', requests[requests.length - 1].action === 'features/deactivate' && requests[requests.length - 1].payload.key === 'sample' && hasClass( deactivate, 'nino-admin-btn-secondary' ) );
answer( 200, { feature : FEATURES[2] } );
check( '...and reloads too', reloads === 2 );

const update = byTag( section( mount, 'plain' ), 'button' )[0];
fire( update, 'click' );
check( 'Update posts features/activate - the kernel\'s one step for an update is activating again', requests[requests.length - 1].action === 'features/activate' && requests[requests.length - 1].payload.key === 'plain' );
answer( 500, null );
check( 'a failed update falls back to its own error line', byTag( section( mount, 'plain' ), 'p' ).some( function( el ) { return el.textContent === '(500) '+ text('/_admin/features/error/update') } ) );

// --- opening with a cache already on disk: the Available tab fills with no request

panel.init();
answer( 200, listAnswer( CACHE.url, true, CACHE ) );
check( 'a cached catalogue fills the Available tab straight from features/list - no features/catalogue request at all', requests.filter( function( r ) { return r.action === 'features/catalogue' } ).length === 0 );

// The panel stayed on Active from the previous section (a re-render keeps
// whichever tab is current) - switch back to look at the freshly cached one
fire( tabs[0], 'click' );

const availableTabs = byTag( mount.children[0], 'button' );
check( 'Available\'s count is the offers that are not already current: ancient, extra, helper, needy - not sample', availableTabs[0].textContent === text('/_admin/features/tab/available')+ ' (4)' );
check( 'the status line reads the cache\'s own stamp', byTag( mount.children[1], 'p' )[0].textContent === text('/_admin/features/label/catalogue-status').replace( '%s', CACHE.fetched ) );

check( 'the Available tab is one card per offer that is not current, ancient/extra/helper/needy but not sample', byTag( mount, 'section' ).map( function( el ) { return el.dataset.offer } ).filter( Boolean ).join(',') === 'ancient,extra,helper,needy' );
check( 'a card carries no status badge - just its name', offer( mount, 'extra' ).children[0].tagName === 'H3' && offer( mount, 'extra' ).children[0].textContent === 'Zusatz' );
check( 'an available offer has Install as the primary action, its version, description and requirements', byTag( offer( mount, 'extra' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/install') && hasClass( byTag( offer( mount, 'extra' ), 'button' )[0], 'nino-admin-btn-primary' )
	&& byTag( offer( mount, 'extra' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/version').replace( '%s', '1.0.0' )+ ' – '+ text('/_admin/features/label/released').replace( '%s', '2026-09-07' ) } )
	&& byTag( offer( mount, 'extra' ), 'p' ).some( function( el ) { return el.textContent === 'Ein extra' } ) && byTag( offer( mount, 'extra' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/requires').replace( '%s', 'helper' ) } ) );
check( 'an upgrade offers Update to the new version and names the one on disk', byTag( offer( mount, 'helper' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/update').replace( '%s', '1.2.0' )
	&& byTag( offer( mount, 'helper' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/version').replace( '%s', '1.2.0' )+ ' – '+ text('/_admin/features/label/installed').replace( '%s', '1.1.0' ) } ) );
check( 'an incompatible one is greyed, offers nothing, and says what it asks for: the Nino constraint, and the extensions where it names some', byTag( offer( mount, 'needy' ), 'button' ).length === 0 && offer( mount, 'needy' ).attributes['aria-disabled'] === 'true'
	&& byTag( offer( mount, 'needy' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/nino').replace( '%s', '^1.0' ) } ) && byTag( offer( mount, 'needy' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/extensions').replace( '%s', 'no_such_extension, other' ) } )
	&& byTag( offer( mount, 'ancient' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/nino').replace( '%s', '^0.9' ) } ) && byTag( offer( mount, 'ancient' ), 'p' ).every( function( el ) { return el.textContent.indexOf( text('/_admin/features/label/extensions').split('%s')[0] ) !== 0 } )
	&& offer( mount, 'extra' ).attributes['aria-disabled'] === undefined );
check( 'a current offer (sample) is excluded from Available entirely', offer( mount, 'sample' ) === undefined );

// --- installing from it

const install = byTag( offer( mount, 'extra' ), 'button' )[0];
fire( install, 'click' );
check( 'Install posts features/install with key and version, holds the button and says so', requests[requests.length - 1].action === 'features/install' && requests[requests.length - 1].payload.key === 'extra' && requests[requests.length - 1].payload.version === '1.0.0'
	&& install.disabled === true && byTag( offer( mount, 'extra' ), 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0].textContent === text('/_admin/features/msg/installing') );
answer( 400, { error : 'the archive does not match what the catalogue promised' } );
const extraMsg = byTag( offer( mount, 'extra' ), 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0];
check( 'a refusal shows the kernel\'s reason, frees the button and reads nothing again', install.disabled === false && hasClass( extraMsg, 'nino-admin-error' ) && extraMsg.textContent === '(400) the archive does not match what the catalogue promised' && requests[requests.length - 1].action === 'features/install' );
fire( install, 'click' );
answer( 500, null );
check( 'a failed install falls back to its own error line', byTag( offer( mount, 'extra' ), 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0].textContent === '(500) '+ text('/_admin/features/error/install') );

const EXTRA = { key : 'extra', name : 'Extra', description : 'Ein extra', version : '1.0.0', installed : null, active : false, update : false, requires : [ 'helper' ], problems : [], settings : [] };
const FEATURES_WITH_EXTRA = FEATURES.concat( [ EXTRA ] );
const requestsBeforeInstall = requests.length;
fire( install, 'click' );
answer( 200, { feature : EXTRA, updated : false } );
check( 'success reads the list again, and only the list - the cached catalogue\'s offers are recomputed there, no catalogue request and no page reload', requests.length === requestsBeforeInstall + 2 && requests[requests.length - 1].action === 'features/list' && reloads === 2 );

// The list answers a catalogue whose offers already reflect the install:
// extra now current - features/list itself would only carry this once the
// backend saw it, which is exactly what a fresh cache read gives it - and
// the installed feature itself, which the fixture above did not carry
const installedOffers = JSON.parse( JSON.stringify( OFFERS ) );
installedOffers[1] = Object.assign( {}, installedOffers[1], { state : 'current', local : '1.0.0' } );
answer( 200, listAnswer( CACHE.url, true, Object.assign( {}, CACHE, { offers : installedOffers } ), FEATURES_WITH_EXTRA ) );
check( 'the installed feature now shows on Inactive, off, with Activate - and Available lost it, straight from the cache', byTag( mount.children[0], 'button' )[0].textContent === text('/_admin/features/tab/available')+ ' (3)' );

fire( byTag( mount.children[0], 'button' )[1], 'click' );
check( 'the just-installed feature is on Inactive now, with Activate', section( mount, 'extra' ) !== undefined && byTag( section( mount, 'extra' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/activate') );

fire( byTag( mount.children[0], 'button' )[0], 'click' );
check( 'and the offer itself is gone from Available - excluded as current, not shown with a disabled button', offer( mount, 'extra' ) === undefined );

// --- a directory the web server cannot write

const helperBtn = byTag( offer( mount, 'helper' ), 'button' )[0];
fire( helperBtn, 'click' );
check( 'Update posts features/install with the offered version', requests[requests.length - 1].action === 'features/install' && requests[requests.length - 1].payload.key === 'helper' && requests[requests.length - 1].payload.version === '1.2.0' );
answer( 200, { feature : FEATURES[1], updated : true } );
answer( 200, listAnswer( CACHE.url, false, Object.assign( {}, CACHE, { offers : installedOffers } ) ) );
check( 'without a writable directory the tab says so, naming it, and every remaining offer links its archive instead of a button', byTag( mount.children[2], 'p' ).some( function( el ) { return hasClass( el, 'nino-admin-error' ) && el.textContent === text('/_admin/features/hint/catalogue-readonly').replace( '%s', '/features' ) } )
	&& byTag( offer( mount, 'ancient' ), 'button' ).length === 0 && byTag( offer( mount, 'needy' ), 'button' ).length === 0
	&& byTag( offer( mount, 'helper' ), 'a' ).length === 1 && byTag( offer( mount, 'helper' ), 'a' )[0].href === CACHE.offers[2].archive && byTag( offer( mount, 'helper' ), 'a' )[0].textContent === text('/_admin/features/label/archive') );

// --- an empty catalogue, one offering nothing new, and the catalogue switched off

panel.init();
answer( 200, listAnswer( CACHE.url, true, Object.assign( {}, CACHE, { offers : [] } ) ) );
check( 'a cached catalogue listing no features at all is the shared empty state, naming none of the module\'s regular hints', findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/catalogue-empty') );

panel.init();
const allCurrent = OFFERS.map( function( o ) { return Object.assign( {}, o, { state : 'current' } ) } );
answer( 200, listAnswer( CACHE.url, true, Object.assign( {}, CACHE, { offers : allCurrent } ) ) );
check( 'a cache whose offers are all already current says everything is up to date, count 0', findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/available-empty')
	&& byTag( mount.children[0], 'button' )[0].textContent === text('/_admin/features/tab/available')+ ' (0)' );

panel.showCurrent();
answer( 200, listAnswer( '', true, null ) );
check( 'switched off: no action bar at all, and the Available tab says so, naming the directory', mount.children.length === 2 && findAll( mount, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } ).length === 0
	&& byTag( mount.children[1], 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/hint/catalogue-off').replace( '%s', '/features' ) } ) );
check( 'and a fetch was never made on its own throughout all of this', requests.filter( function( r ) { return r.action === 'features/catalogue' } ).length === 0 );

// --- Refresh catalogue

panel.showCurrent();
answer( 200, listAnswer( CACHE.url, true, null ) );
const refreshBtn = byTag( mount.children[1], 'button' )[0];
const requestsBeforeRefresh = requests.length;

fire( refreshBtn, 'click' );
// The click rebuilt the whole action bar - re-query rather than trust the
// button reference from before the click, which the rebuild detached
check( 'Refresh catalogue posts features/catalogue with nothing, holds the button and says it is loading', requests.length === requestsBeforeRefresh + 1 && requests[requests.length - 1].action === 'features/catalogue' && Object.keys( requests[requests.length - 1].payload ).length === 0
	&& byTag( mount.children[1], 'button' )[0].disabled === true && byTag( mount.children[1], 'p' )[0].textContent === text('/_admin/features/msg/catalogue-loading') );
answer( 400, { error : 'Der Katalog konnte nicht geladen werden: the catalogue signature does not verify' } );
check( 'a refusal shows the backend\'s message as an error and frees the button, the Available tab unchanged (still unloaded)', byTag( mount.children[1], 'button' )[0].disabled === false && hasClass( byTag( mount.children[1], 'p' )[0], 'nino-admin-error' )
	&& byTag( mount.children[1], 'p' )[0].textContent === '(400) Der Katalog konnte nicht geladen werden: the catalogue signature does not verify'
	&& findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/available-unloaded') );

fire( byTag( mount.children[1], 'button' )[0], 'click' );
answer( 500, null );
check( 'a failed refresh falls back to its own error line', byTag( mount.children[1], 'p' )[0].textContent === '(500) '+ text('/_admin/features/error/catalogue') );

fire( byTag( mount.children[1], 'button' )[0], 'click' );
answer( 200, catalogueAnswer( true, OFFERS, '2026-09-08 09:00' ) );
check( 'a successful refresh clears the error, frees the button and the status now names when it was fetched', hasClass( byTag( mount.children[1], 'p' )[0], 'nino-admin-error' ) === false && byTag( mount.children[1], 'button' )[0].disabled === false
	&& byTag( mount.children[1], 'p' )[0].textContent === text('/_admin/features/label/catalogue-status').replace( '%s', '2026-09-08 09:00' ) );
check( 'the Available tab now shows the refreshed offers, count 4', byTag( mount.children[0], 'button' )[0].textContent === text('/_admin/features/tab/available')+ ' (4)'
	&& byTag( mount, 'section' ).map( function( el ) { return el.dataset.offer } ).filter( Boolean ).join(',') === 'ancient,extra,helper,needy' );
check( 'switching tabs and back leaves the refreshed cache in place - the tab bar rebuild does not lose state', ( fire( byTag( mount.children[0], 'button' )[2], 'click' ), fire( byTag( mount.children[0], 'button' )[0], 'click' ), byTag( mount, 'section' ).map( function( el ) { return el.dataset.offer } ).filter( Boolean ).length === 4 ) );

// --- nothing installed at all, and a failed load

panel.showCurrent();
answer( 200, { dir : '/features', catalogueUrl : CACHE.url, writable : true, catalogue : null, features : [] } );
check( 'showCurrent reloads, and Inactive\'s empty state names the directory - Active empty too, both counted 0', requests[requests.length - 1].action === 'features/list'
	&& byTag( mount.children[0], 'button' )[1].textContent === text('/_admin/features/tab/inactive')+ ' (0)' && byTag( mount.children[0], 'button' )[2].textContent === text('/_admin/features/tab/active')+ ' (0)' );
fire( byTag( mount.children[0], 'button' )[1], 'click' );
check( 'Inactive, empty, names the features directory', findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/empty').replace( '%s', '/features' ) );
fire( byTag( mount.children[0], 'button' )[2], 'click' );
check( 'Active, empty, says no feature is on', findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/active-empty') );

panel.init();
answer( 500, null );
check( 'a failed load is reported through the workbench\'s words', mount.children.length === 1 && hasClass( mount.children[0], 'nino-admin-error' ) && mount.children[0].textContent === '(500) '+ text('/_admin/common/error/load') );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
