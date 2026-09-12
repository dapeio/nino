/**
 *	Nino									A compact filesystembased php framework
 *	admin-features-js-smoke.js	DOM-light checks for the Features panel's script
 *										(_admin/Nino/Modules/Features/assets/admin.js): that it
 *										attaches under its nav uri, which actions it posts and
 *										with what payload, that every word it renders is a fill
 *										both interface languages define, that a secret's input
 *										is always empty, and that one Save collects every
 *										setting of its feature by data-key - on the screen of
 *										its own the Settings button steps into and the back
 *										link leaves, over the two tabs that screen is split
 *										into (Description, Settings) - and the three tabs
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

/** One row by the key it carries - a <li> on Inactive and Available, the drill-down <button> on Active */
function row( root, key ) {
	return findAll( root, function( el ) { return el.dataset.feature === key } )[0];
}

function offer( root, key ) {
	return findAll( root, function( el ) { return el.dataset.offer === key } )[0];
}

/** The line an offer reports into, and the button it carries */
function offerMessage( root, key ) {
	return ( byTag( offer( root, key ), 'p' )[0] || { textContent : '' } ).textContent;
}

function offerButton( root, key ) {
	return byTag( offer( root, key ), 'button' )[0];
}

/** Every row's key, in the order they are drawn */
function rowKeys( root, attr ) {
	return findAll( root, function( el ) { return typeof el.dataset[attr] === 'string' } ).map( function( el ) { return el.dataset[attr] } ).join(',');
}

/** The one line under a row's name: joined by the script, so these are read for what they contain */
function meta( el ) {
	return el === undefined ? '' : ( byTag( el, 'small' )[0] || byTag( el, 'div' ).filter( function( n ) { return hasClass( n, 'admin-type-btn-descr' ) } )[0] || { textContent : '' } ).textContent;
}

/** The text of a node the script built out of pieces - the stand-in keeps them as children */
function textOf( el ) {
	return el === undefined ? '' : ( el.textContent !== '' ? el.textContent : ( el.children || [] ).map( textOf ).join('') );
}

/** The lines under it that are read whole - a requirement, a refusal, what an offer asks for */
function notes( el ) {
	return byTag( el, 'small' ).filter( function( n ) { return hasClass( n, 'admin-features-note' ) } ).map( function( n ) { return n.textContent } );
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
// The panel's second pane: the screen an active feature's row drills into
const screen = element('div');
screen.id = 'features-detail';

const callbacks = [];
const requests = [];
let reloads = 0;

const Nino = {
	// The shell's own one-line delegation (see _admin/assets/script.js), so
	// the panel reaches the same context bar every drill-down level uses
	admin : { formToolbar : function( backLink ) { return Nino.adminUi.contextBar( backLink ) } },
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
		getElementById : function( id ) { return id === 'features-list' ? mount : ( id === 'features-detail' ? screen : null ) },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
let confirmAnswer = true;
sandbox.window = {
	Nino : Nino,
	confirm : function() { return confirmAnswer },
	location : { hash : '', reload : function() { reloads++ } },
};

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
// - and one category each, 'fresh' deliberately without: a project whose
// features predate the field is the state the panel has to read well too
const FEATURES = [
	{ key : 'old', name : 'Old', description : '', category : 'system', version : '3.0.0', installed : null, active : false, update : false, requires : [ 'nowhere' ],
		problems : [ 'requires Nino ^0.9, this is 1.0.0', 'requires the php extension "no_such_extension"' ], settings : [] },
	{ key : 'plain', name : 'Plain', description : 'Nothing to set.', category : 'ui', version : '1.0.0', installed : '0.9.0', active : true, update : true, requires : [], problems : [], settings : [] },
	{ key : 'sample', name : 'Beispiel-Feature', description : 'Prüft den ganzen Feature-Vertrag.', manual : 'Setze `data-sample="on"` auf den Container.\nJede Zeile wird nacheinander getippt.\n\nMehr braucht es nicht - <b>kein</b> Markup.\n\nEin Backtick ` allein bleibt Text.', category : 'content', version : '1.2.0', installed : '1.2.0', active : true, update : false, requires : [ 'helper' ], problems : [], settings : [
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
	{ key : 'fresh', name : 'Fresh', description : 'Not switched on yet.', category : '', version : '0.1.0', installed : null, active : false, update : false, requires : [], problems : [], settings : [] },
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
	{ key : 'ancient', name : 'Ancient', description : 'Ein ancient', category : 'content', version : '1.0.0', nino : '^0.9', ext : [], requires : [], directory : 'Ancient', archive : 'https://catalogue.test/features/ancient-1.0.0.tar.gz', size : 10, released : '2026-09-07', state : 'incompatible', fits : false, local : null, active : false },
	{ key : 'extra', name : 'Zusatz', description : 'Ein extra', category : 'marketing', version : '1.0.0', nino : '^1.0', ext : [], requires : [ 'helper' ], directory : 'Extra', archive : 'https://catalogue.test/features/extra-1.0.0.tar.gz', size : 10, released : '2026-09-07', state : 'available', fits : true, local : null, active : false },
	{ key : 'helper', name : 'Helper', description : '', category : 'system', version : '1.2.0', nino : '^1.0', ext : [], requires : [], directory : 'Helper', archive : 'https://catalogue.test/features/helper-1.2.0.tar.gz', size : 10, released : '', state : 'upgrade', fits : true, local : '1.1.0', active : true },
	{ key : 'needy', name : 'Needy', description : 'Ein needy', category : 'security', version : '1.0.0', nino : '^1.0', ext : [ 'no_such_extension', 'other' ], requires : [], directory : 'Needy', archive : 'https://catalogue.test/features/needy-1.0.0.tar.gz', size : 10, released : '2026-09-07', state : 'incompatible', fits : false, local : null, active : false },
	{ key : 'sample', name : 'Beispiel-Feature', description : 'Ein sample', category : 'content', version : '1.2.0', nino : '^1.0', ext : [], requires : [ 'helper' ], directory : 'Sample', archive : 'https://catalogue.test/features/sample-1.2.0.tar.gz', size : 10, released : '2026-09-07', state : 'current', fits : true, local : '1.2.0', active : true },
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
check( 'the panel is a system entry with the nav uri the script speaks, two mount points and seven actions',
	admin.includes( "return [ 'features', '/_admin/nav/features', 15, 'system' ];" ) && admin.includes( "return [ 'features-list', 'features-detail' ];" )
	&& script.includes( "'features-list'" ) && script.includes( "'features-detail'" )
	&& [ 'features/list', 'features/activate', 'features/deactivate', 'features/settings', 'features/catalogue', 'features/install', 'features/remove' ].every( function( action ) { return admin.includes( "'"+ action+ "'" ) } ) );
// The one thing the module's own stylesheet is for: the script builds a
// card's buttons as siblings with no whitespace between them, so the row
// carries its gap itself
const moduleCss = source('_admin/Nino/Modules/Features/assets/admin.css');
check( 'the panel bundles a stylesheet of its own, in the workbench\'s layer, spacing the row of buttons a card and an offer carry',
	admin.includes( "'/assets/admin.css'" ) && moduleCss.includes( '@layer nino.tool {' )
	&& /#features-list \.admin-features-actions \{[^}]*display: flex;[^}]*gap:/s.test( moduleCss ) && script.includes( "actions.className = 'admin-features-actions'" ) );
// The shared strip is deliberately not sticky (a panel with tabs drills into
// a form whose context bar is pinned to the same edge). Drilling into a
// feature's settings hides this pane instead, so here it can be - which is
// why the rule is the module's own and scoped to its pane
check( 'the head that carries the tabs and the filter stays at the top of the list pane, scoped to that pane',
	/#features-list \.admin-features-head \{[^}]*position: sticky;/s.test( moduleCss )
	&& /\.admin-panel-tabs \{[^}]*position: sticky;/s.test( source('_admin/assets/style.css') ) === false );
check( 'every action method guards itself with the panel\'s permission', ( admin.match( /guardPerm\( \$appData, \$request, self::MANAGE_PERM \)/g ) || [] ).length === 7 );
check( 'the script posts those seven actions and no other',
	script.includes( "action : 'features/'+ endpoint" ) && script.includes( "_apiCall( 'list'" ) && script.includes( "_apiCall( 'settings'" )
	&& script.includes( "? 'deactivate' : 'activate'" ) && script.includes( "_apiCall( 'catalogue'" ) && script.includes( "_apiCall( 'install'" )
	&& script.includes( "_apiCall( 'remove'" ) && ( script.match( /_apiCall\( /g ) || [] ).length === 6 );

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

// The vocabulary is the kernel's (\Nino\Features::CATEGORIES), the words for
// it are this panel's. A category shipped without a fill would show as its
// own slug - which is the fallback for a feature from a later catalogue, not
// for one this very workbench was released with
const vocabulary = ( source('_nino/Nino/Features/Features.php').match( /public const array CATEGORIES = \[([^\]]*)\]/ ) || [ '', '' ] )[1]
	.split(',').map( function( part ) { return part.trim().replace( /^'|'$/g, '' ) } ).filter( Boolean );
const unnamed = vocabulary.concat( [ 'none' ] ).filter( function( slug ) {
	return moduleEn['/_admin/features/category/'+ slug ] === undefined || moduleDe['/_admin/features/category/'+ slug ] === undefined;
} );
check( 'every category the kernel publishes is named in both interface languages'+ ( unnamed.length ? ' - missing: '+ unnamed.join(', ') : '' ),
	vocabulary.length === 6 && vocabulary.indexOf( 'security' ) !== -1 && unnamed.length === 0 );
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
check( 'there is no intro line and no eyebrow badge - just the head, the action bar and the current tab', mount.children.length === 3
	&& hasClass( mount.children[0], 'admin-features-head' )
	&& findAll( mount, function( el ) { return hasClass( el, 'nino-admin-hint-lead' ) } ).length === 0 && findAll( mount, function( el ) { return hasClass( el, 'nino-admin-eyebrow' ) } ).length === 0 );

// --- the head: the tab strip and the filter beside it

const head 	 = mount.children[0];
const strip	 = head.children[0];
const filter = head.children[1];
check( 'the head holds the tab strip, the filter and the category, in that order, and neither filter is inside the tablist', head.children.length === 3
	&& hasClass( strip, 'nino-admin-tabs' ) && hasClass( strip, 'nino-admin-tabs--bar' ) && hasClass( strip, 'admin-panel-tabs' ) && strip.attributes.role === 'tablist'
	&& filter.tagName === 'INPUT' && filter.type === 'search' && filter.id === 'features-filter' );
check( 'the filter is labelled and placeheld from the text system, and reuses the shared search control', hasClass( filter, 'nino-admin-table-search' )
	&& filter.placeholder === text('/_admin/features/label/filter') && filter.attributes['aria-label'] === text('/_admin/features/label/filter') );

const tabs = byTag( strip, 'button' );
check( 'three tabs, Active first, each labelled with its count - Active 2 (plain, sample), Inactive 2 (old, fresh)', tabs.length === 3
	&& tabs[0].textContent === text('/_admin/features/tab/active')+ ' (2)' && tabs[1].textContent === text('/_admin/features/tab/inactive')+ ' (2)' && tabs[2].textContent === text('/_admin/features/tab/available')+ ' (0)' );
check( 'Active is the tab a panel opens on, role=tab/tablist throughout, aria-selected in step with it', tabs.every( function( t ) { return t.attributes.role === 'tab' } )
	&& hasClass( tabs[0], 'is-active' ) && tabs[0].attributes['aria-selected'] === 'true' && hasClass( tabs[1], 'is-active' ) === false && tabs[1].attributes['aria-selected'] === 'false' );

// --- the action bar

check( 'the action bar carries the shared classes and one button plus a status line', hasClass( mount.children[1], 'nino-admin-actionbar' ) && hasClass( mount.children[1], 'nino-admin-list-actions' )
	&& byTag( mount.children[1], 'button' ).length === 1 && byTag( mount.children[1], 'button' )[0].textContent === text('/_admin/features/label/catalogue-refresh') && hasClass( byTag( mount.children[1], 'button' )[0], 'nino-admin-btn-secondary' ) );
const status = byTag( mount.children[1], 'p' )[0];
check( 'without a cache yet the status says so, not an error', status.textContent === text('/_admin/features/label/catalogue-unloaded') && hasClass( status, 'nino-admin-error' ) === false && status.attributes['aria-live'] === 'polite' );

// --- the Available tab before anything was ever fetched

fire( tabs[2], 'click' );
check( 'the Available tab explains there is nothing cached yet', findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/available-unloaded') );

// --- Inactive and Active

fire( tabs[1], 'click' );
check( 'Inactive is the grouped list, one row per feature that is off, in the backend\'s order', rowKeys( mount, 'feature' ) === 'old,fresh'
	&& hasClass( byTag( mount.children[2], 'ul' )[0], 'nino-admin-list' ) && row( mount, 'old' ).tagName === 'LI' );
check( 'a row is the shared name-over-line copy, and carries no status badge', hasClass( row( mount, 'fresh' ).children[0], 'nino-admin-list-copy' )
	&& row( mount, 'fresh' ).children[0].children[0].tagName === 'STRONG' && row( mount, 'fresh' ).children[0].children[0].textContent === 'Fresh' );
check( 'the line under the name says which version this is and what the feature does', meta( row( mount, 'fresh' ) ) === text('/_admin/features/label/version').replace( '%s', '0.1.0' )+ ' · Not switched on yet.' );
check( 'a feature that names no category writes nothing about one - "uncategorized" on every line of an older project is noise, not information',
	meta( row( mount, 'fresh' ) ).indexOf( text('/_admin/features/category/none') ) === -1
	&& meta( row( mount, 'old' ) ) === text('/_admin/features/category/system')+ ' · '+ text('/_admin/features/label/version').replace( '%s', '3.0.0' ) );
check( 'an inactive feature without problems offers Remove and Activate', byTag( row( mount, 'fresh' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/remove')+ '|'+ text('/_admin/features/label/activate')
	&& hasClass( byTag( row( mount, 'fresh' ), 'button' )[0], 'nino-admin-btn-danger' ) && hasClass( byTag( row( mount, 'fresh' ), 'button' )[1], 'nino-admin-btn-primary' ) );
// A feature that cannot be switched on is exactly the one somebody wants rid
// of, so Remove is there whether or not Activate is
check( 'one with problems offers Remove alone, and its requirements and every refusal are read whole rather than ellipsized', byTag( row( mount, 'old' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/remove')
	&& notes( row( mount, 'old' ) ).join('|') === text('/_admin/features/label/requires').replace( '%s', 'nowhere' )+ '|'+ FEATURES[0].problems.join('|')
	&& byTag( row( mount, 'old' ), 'small' ).filter( function( el ) { return hasClass( el, 'nino-admin-error' ) } ).map( function( el ) { return el.textContent } ).join('|') === FEATURES[0].problems.join('|') );

fire( tabs[0], 'click' );
check( 'Active lists the features that are on', rowKeys( mount, 'feature' ) === 'plain,sample' );
check( 'an active feature is the shared drill-down row - a button with a chevron, and no action of its own in the list', row( mount, 'plain' ).tagName === 'BUTTON'
	&& hasClass( row( mount, 'plain' ), 'admin-type-btn' ) && row( mount, 'plain' ).type === 'button'
	&& byTag( row( mount, 'plain' ), 'span' ).filter( function( el ) { return hasClass( el, 'admin-view-button-chev' ) } ).length === 1
	&& findAll( row( mount, 'plain' ), function( el ) { return el.tagName === 'BUTTON' } ).length === 0 );
check( 'its line leads with the category, then names the version and, where an update waits, the one on disk', meta( row( mount, 'plain' ) ) === text('/_admin/features/category/ui')+ ' · '+ text('/_admin/features/label/version').replace( '%s', '1.0.0' )+ ' – '+ text('/_admin/features/label/installed').replace( '%s', '0.9.0' )+ ' · Nothing to set.' );

// --- the filter over everything the tabs hold

/** The filter as it stands after the last redraw - the panel builds a new one every time */
function filterNow() {
	return mount.children[0].children[1];
}

filterNow().value = 'beispiel';
fire( filterNow(), 'input' );
const narrowed = byTag( mount.children[0], 'button' );
check( 'a filter narrows every tab\'s count to what matches, so the counts say where the match is', narrowed[0].textContent === text('/_admin/features/tab/active')+ ' (1)'
	&& narrowed[1].textContent === text('/_admin/features/tab/inactive')+ ' (0)' && narrowed[2].textContent === text('/_admin/features/tab/available')+ ' (0)' );
check( 'and the tab on screen shows the match alone', rowKeys( mount, 'feature' ) === 'sample' );
check( 'the filter survives the redraw it triggers, with what was typed', filterNow().value === 'beispiel' );

filterNow().value = 'NOTHING TO SET';
fire( filterNow(), 'input' );
check( 'it reads the description too, and ignores case', rowKeys( mount, 'feature' ) === 'plain' );

filterNow().value = 'fre';
fire( filterNow(), 'input' );
fire( byTag( mount.children[0], 'button' )[1], 'click' );
check( 'switching tabs keeps the filter, and it reads the key as well as the name', rowKeys( mount, 'feature' ) === 'fresh'
	&& filterNow().value === 'fre' );

filterNow().value = 'zzz';
fire( filterNow(), 'input' );
check( 'a filter that matches nothing says so - not the tab\'s own empty state, which would explain a features directory nobody asked about',
	findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/nomatch') );

filterNow().value = '';
fire( filterNow(), 'input' );
fire( byTag( mount.children[0], 'button' )[0], 'click' );
check( 'cleared, every card is back', rowKeys( mount, 'feature' ) === 'plain,sample' );

// --- the category beside it

/** The category select as it stands after the last redraw - rebuilt like everything else in the head */
function categoryNow() {
	return mount.children[0].children[2];
}

check( 'the category is a select of its own, labelled from the text system and reusing the shared input control',
	categoryNow().tagName === 'SELECT' && categoryNow().id === 'features-category'
	&& hasClass( categoryNow(), 'nino-admin-input' ) && hasClass( categoryNow(), 'admin-features-category' )
	&& categoryNow().attributes['aria-label'] === text('/_admin/features/label/category') );
check( 'it offers what is on screen and nothing else: the entry that turns it off first, then one per category by the name it is shown under, the features without one last',
	byTag( categoryNow(), 'option' ).map( function( o ) { return o.textContent } ).join('|')
		=== [ text('/_admin/features/label/category-all'), text('/_admin/features/category/content'), text('/_admin/features/category/ui'),
			text('/_admin/features/category/system'), text('/_admin/features/category/none') ].join('|') );
check( 'so a category nothing is filed under is not offered - the catalogue is not loaded yet, and its own are not among them',
	byTag( categoryNow(), 'option' ).map( function( o ) { return o.value } ).join('|') === '|content|ui|system|' );

categoryNow().value = 'content';
fire( categoryNow(), 'change' );
const byCategory = byTag( mount.children[0], 'button' );
check( 'picking one narrows every tab\'s count the way the search box does', byCategory[0].textContent === text('/_admin/features/tab/active')+ ' (1)'
	&& byCategory[1].textContent === text('/_admin/features/tab/inactive')+ ' (0)' && byCategory[2].textContent === text('/_admin/features/tab/available')+ ' (0)' );
check( 'and the tab on screen shows what is filed under it', rowKeys( mount, 'feature' ) === 'sample' );
check( 'the pick survives the redraw it triggers', categoryNow().value === 'content' );

filterNow().value = 'plain';
fire( filterNow(), 'input' );
check( 'the two narrow together rather than one replacing the other - Plain is not filed under Content',
	findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/nomatch') );

filterNow().value = '';
fire( filterNow(), 'input' );
categoryNow().value = '';
fire( categoryNow(), 'change' );
check( 'cleared, every feature is back', rowKeys( mount, 'feature' ) === 'plain,sample' );

filterNow().value = text('/_admin/features/category/ui').toLowerCase();
fire( filterNow(), 'input' );
check( 'typing a category name into the search box finds the same rows, for anyone who never noticed the select', rowKeys( mount, 'feature' ) === 'plain' );
filterNow().value = '';
fire( filterNow(), 'input' );

// A pick that no longer names anything on screen would hide every row with
// nothing on the page saying why - so a reload that drops the last feature
// carrying it drops the pick too
categoryNow().value = 'ui';
fire( categoryNow(), 'change' );
panel.init();
answer( 200, listAnswer( CACHE.url, true, null, FEATURES.filter( function( f ) { return f.category !== 'ui' } ) ) );
check( 'a category gone after a reload stops narrowing instead of emptying the list for good', rowKeys( mount, 'feature' ) === 'sample' && categoryNow().value === '' );

panel.init();
answer( 200, listAnswer( CACHE.url, true, null ) );
check( 'and the whole list is back with the feature', rowKeys( mount, 'feature' ) === 'plain,sample' );

// --- one feature's own screen (from the Active tab)

const sample = row( mount, 'sample' );
check( 'an active feature holds no form and no action in the list - the row itself is what leads to them', byTag( sample, 'form' ).length === 0
	&& findAll( sample, function( el ) { return el.tagName === 'BUTTON' } ).length === 0 );

const asked = requests.length;
fire( sample, 'click' );
check( 'stepping into it asks the backend for nothing - features/list already carried the schema', requests.length === asked );
check( 'the list is stepped out of and the feature\'s pane shown, the way every drill-down level is', mount.classList.contains('admin-hidden') === true && screen.classList.contains('admin-hidden') === false );
check( 'the screen names the feature, and the line under it is identity alone - what it does is a sentence, and a sentence is not appended to a line that gets scanned', byTag( screen, 'h3' )[0].textContent === 'Beispiel-Feature'
	&& byTag( screen, 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/category/content')+ ' · '+ text('/_admin/features/label/version').replace( '%s', '1.2.0' ) } )
	&& byTag( screen, 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/requires').replace( '%s', 'helper' ) } ) );

const backLink = byTag( screen.children[0], 'a' )[0];
check( 'the screen opens with the shared context bar and the workbench\'s own back link', hasClass( screen.children[0], 'nino-admin-contextbar' )
	&& hasClass( backLink, 'nino-admin-back-link' ) && backLink.textContent === text('/_admin/common/label/back') );

const form 	 = byTag( screen, 'form' )[0];
const controls = form ? form.querySelectorAll('[data-key]') : [];
check( 'the form is on that screen and nowhere in the list, every setting a control carrying its name in schema order', form !== undefined && byTag( mount, 'form' ).length === 0
	&& controls.map( function( el ) { return el.dataset.key } ).join(',') === 'enabled,limit,title,notes,contact,site,mode,apiKey,hosts' );
check( 'the settings sit in one fieldset', byTag( form, 'fieldset' ).length === 1 );

/*	Two tabs over one form: what the feature is, and what can be set on it.
	Both panes are built and one of them is hidden - not one pane rebuilt per
	switch - so a setting typed into and then left to go and read what it does
	comes back with what was typed in it	*/
const tabBar 		= findAll( screen, function( el ) { return hasClass( el, 'features-detail-tabs' ) } )[0];
const detailTabs	= tabBar === undefined ? [] : byTag( tabBar, 'button' );
const aboutPane	= findAll( screen, function( el ) { return hasClass( el, 'features-about' ) } )[0];
const settingsPane = byTag( form, 'fieldset' )[0];

check( 'the screen is two tabs, Description first, each a tab of the shared strip', tabBar !== undefined && tabBar.attributes.role === 'tablist'
	&& detailTabs.map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/tab/about')+ '|'+ text('/_admin/features/tab/settings')
	&& detailTabs.every( function( el ) { return el.attributes.role === 'tab' && el.type === 'button' } ) );
check( 'they sit above both panes, in the form, so the one Save below belongs to them', form.children.indexOf( tabBar ) < form.children.indexOf( aboutPane )
	&& form.children.indexOf( aboutPane ) < form.children.indexOf( settingsPane ) );
check( 'a screen opens on what the feature is: the description pane shown, the settings pane hidden rather than unmounted', aboutPane.hidden === false && settingsPane.hidden === true
	&& detailTabs[0].attributes['aria-selected'] === 'true' && detailTabs[1].attributes['aria-selected'] === 'false' );
check( 'each tab points at its pane and each pane back at its tab, so a reader who cannot see the strip is told what names the group', detailTabs[0].attributes['aria-controls'] === aboutPane.id
	&& detailTabs[1].attributes['aria-controls'] === settingsPane.id && aboutPane.attributes['aria-labelledby'] === detailTabs[0].id
	&& settingsPane.attributes['aria-labelledby'] === detailTabs[1].id && aboutPane.id !== '' && settingsPane.id !== '' );
check( '...and the group has no legend of its own: the tab is its name, and the same word twice is one of them drifting', byTag( settingsPane, 'legend' ).length === 0 );
check( 'the description leads the pane it is under, with the manual below it', byTag( aboutPane, 'p' )[0].textContent === 'Prüft den ganzen Feature-Vertrag.'
	&& hasClass( byTag( aboutPane, 'p' )[0], 'features-about-lead' ) );

fire( detailTabs[1], 'click' );
check( 'switching brings the settings forward and takes the description back', aboutPane.hidden === true && settingsPane.hidden === false
	&& detailTabs[1].attributes['aria-selected'] === 'true' && detailTabs[0].attributes['aria-selected'] === 'false' );
check( '...without rebuilding either of them - the controls are the same elements, with what was typed still in them', byTag( screen, 'form' )[0] === form
	&& findAll( screen, function( el ) { return hasClass( el, 'features-about' ) } )[0] === aboutPane );

fire( detailTabs[0], 'click' );
check( 'and back again', aboutPane.hidden === false && settingsPane.hidden === true );

const manualBody = findAll( aboutPane, function( el ) { return hasClass( el, 'features-manual-body' ) } )[0];
const manualP		 = byTag( manualBody, 'p' );

check( 'the manual out of the manifest is under the description, in that pane, in no box of its own', manualBody !== undefined
	&& findAll( screen, function( el ) { return el.tagName === 'DETAILS' } ).length === 0
	&& aboutPane.children.indexOf( manualBody ) === 1 );
check( 'a blank line starts a paragraph, a single one is where the manifest wrapped its own line', manualP.length === 3
	&& textOf( manualP[0] ) === 'Setze data-sample="on" auf den Container. Jede Zeile wird nacheinander getippt.' );
check( 'a backticked span is a code element and the rest is text - a manifest writes prose, never markup', byTag( manualP[0], 'code' ).map( function( el ) { return el.textContent } ).join('|') === 'data-sample="on"'
	&& textOf( manualP[1] ) === 'Mehr braucht es nicht - <b>kein</b> Markup.' && byTag( manualP[1], 'b' ).length === 0 );
check( 'an unclosed backtick is prose, not the rest of the paragraph turned into code', textOf( manualP[2] ) === 'Ein Backtick ` allein bleibt Text.'
	&& byTag( manualP[2], 'code' ).length === 0 );

/*	...and the shape a manual is written in now: a section per kind of thing a
	feature adds, each a handle and one line. Drawn from the object the panel
	is handed, which is the same path the screen takes - what changes is only
	what the server put in 'manual' */
const cardedBody = panel._renderManual( {
	manualSections : [ 'shortcodes', 'markup', 'routes', 'panel', 'callbacks', 'install' ],
	manual : {
		shortcodes	: [ { handle : '[carded]', text : 'Draws the card.' } ],
		markup			: [ { handle : '', text : 'A line that stands on its own.' } ],
		routes			: [],
		panel				: [],
		callbacks		: [],
		install			: [],
	},
} );

const cardedH6	 = byTag( cardedBody, 'h6' );
const cardedDl	 = byTag( cardedBody, 'dl' );
const cardedDt	 = byTag( cardedBody, 'dt' );
const cardedDd	 = byTag( cardedBody, 'dd' );

check( 'a sectioned manual is one heading per section, always the same six, always in the same order',
	cardedH6.map( function( el ) { return el.textContent } ).join('|')
	=== [ 'shortcodes', 'markup', 'routes', 'panel', 'callbacks', 'install' ].map( function( name ) { return text('/_admin/features/manual/'+ name) } ).join('|') );
/*	One list per section, not one per entry: a handle and what it does is a
	description list, and one list is what puts every handle of a section in
	one column. A grid per entry sizes its first column to its own handle -
	which is not a column	*/
check( 'a section with entries is one description list, one term and one line per entry', cardedDl.length === 2
	&& cardedDt.length === 2 && cardedDd.length === 2 && byTag( cardedDl[0], 'dt' ).length === 1 );
check( 'an entry is its handle as code, and what it does beside it', byTag( cardedDt[0], 'code' )[0].textContent === '[carded]'
	&& cardedDd[0].textContent === 'Draws the card.' );
check( 'an entry with no handle keeps the column the other lines are in, its term empty', byTag( cardedDt[1], 'code' ).length === 0
	&& textOf( cardedDt[1] ) === '' && cardedDd[1].textContent === 'A line that stands on its own.' );

/*	Four sections with nothing in them, each saying so. "No callbacks" is an
	answer, and a reader who does not find the question has to go and read the
	source to learn that the answer was nothing	*/
const none = byTag( cardedBody, 'p' ).filter( function( el ) { return hasClass( el, 'features-manual-none' ) } );

check( 'an empty section says there is nothing rather than being left out, with no list at all', none.length === 4
	&& none.every( function( el ) { return el.textContent === text('/_admin/features/manual/none') } ) );

check( 'a feature with neither manual nor sections gets no manual at all, and one that says nothing about itself either gets no pane - which is the one screen with no tab strip over it',
	panel._renderManual( { manual : '' } ) === null && panel._renderAbout( { description : '', manual : '' } ) === null
	&& byTag( panel._renderAbout( { description : 'Just the sentence.', manual : '' } ), 'p' ).length === 1 );

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

const bar = form.children[form.children.length - 1];
const save = byTag( form, 'button' ).filter( function( el ) { return el.type === 'submit' } )[0];
const off = byTag( form, 'button' ).filter( function( el ) { return el.textContent === text('/_admin/features/label/deactivate') } )[0];
check( 'the bar the workbench pins to the bottom holds everything the feature can do: Deactivate and Save, no Update while none waits', hasClass( bar, 'nino-admin-actionbar' )
	&& byTag( bar, 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/deactivate')+ '|'+ text('/_admin/common/label/save')
	&& save.type === 'submit' && hasClass( off, 'nino-admin-btn-danger' ) && off.type === 'button' );

/*	One bar under both tabs, so Save can be pressed from the description. A
	hidden control is not focusable and the browser cannot report a failed
	constraint on one - the form would simply never submit - so the settings
	come forward on the click, before the form is asked to submit at all	*/
fire( save, 'click' );
check( 'Save pressed from the description brings the settings forward first, rather than submitting over a pane nothing can be reported on', settingsPane.hidden === false
	&& aboutPane.hidden === true && detailTabs[1].attributes['aria-selected'] === 'true' );

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
check( 'the reload comes back to the feature\'s screen rather than dropping to the list', screen.classList.contains('admin-hidden') === false && mount.classList.contains('admin-hidden') === true );
const rebuilt = byTag( screen, 'form' )[0];
check( '...on the rebuilt form, where the confirmation survives the re-render', rebuilt !== form && byTag( rebuilt, 'p' ).some( function( el ) { return el.textContent === text('/_admin/common/msg/saved') } ) );
check( '...and on the tab it was saved from: a save is not a reason to be put back at the beginning of a screen', byTag( findAll( screen, function( el ) { return hasClass( el, 'features-detail-tabs' ) } )[0], 'button' )[1].attributes['aria-selected'] === 'true'
	&& findAll( screen, function( el ) { return hasClass( el, 'features-about' ) } )[0].hidden === true );

fire( byTag( screen.children[0], 'a' )[0], 'click' );
check( 'the back link returns to the list on the tab it was left on, and empties the pane behind it', mount.classList.contains('admin-hidden') === false && screen.classList.contains('admin-hidden') === true
	&& screen.children.length === 0 && rowKeys( mount, 'feature' ) === 'plain,sample' );

// A feature switched off somewhere else - another tab, another account -
// must not leave a screen standing for something that is no longer on
fire( row( mount, 'sample' ), 'click' );
check( 'stepping into a feature opens on what it is, whatever tab the screen before it was left on - a save keeps its tab, a fresh drill-in starts at the beginning', findAll( screen, function( el ) { return hasClass( el, 'features-about' ) } )[0].hidden === false );
panel.init();
answer( 200, listAnswer( CACHE.url, true, null, FEATURES.map( function( f ) { return f.key === 'sample' ? Object.assign( {}, f, { active : false, settings : [] } ) : f } ) ) );
check( 'a feature that is no longer switched on drops its screen and comes back to the list', mount.classList.contains('admin-hidden') === false && screen.classList.contains('admin-hidden') === true && screen.children.length === 0 );

// A feature with no settings at all still has a screen - it is where its
// Deactivate is, and where an update waiting for it is applied
fire( row( mount, 'plain' ), 'click' );
const plainBar = byTag( screen, 'form' )[0].children[ byTag( screen, 'form' )[0].children.length - 1 ];
check( 'a feature whose manifest carries no manual gets no manual, only the sentence it describes itself with', findAll( screen, function( el ) { return hasClass( el, 'features-manual-body' ) } ).length === 0
	&& byTag( findAll( screen, function( el ) { return hasClass( el, 'features-about' ) } )[0], 'p' ).map( function( el ) { return el.textContent } ).join('|') === 'Nothing to set.' );
check( '...and with one pane there is no strip over it, and nothing hidden behind a tab that is not there', findAll( screen, function( el ) { return hasClass( el, 'features-detail-tabs' ) } ).length === 0
	&& findAll( screen, function( el ) { return hasClass( el, 'features-detail-pane' ) } ).every( function( el ) { return el.hidden === false } ) );
check( 'an active feature that declares no setting gets the same screen, without a fieldset and without a Save', byTag( screen, 'fieldset' ).length === 0
	&& byTag( plainBar, 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/deactivate')+ '|'+ text('/_admin/features/label/update').replace( '%s', '1.0.0' )
	&& byTag( plainBar, 'button' ).every( function( el ) { return el.type === 'button' } ) );
fire( byTag( screen.children[0], 'a' )[0], 'click' );

panel.init();
answer( 200, listAnswer( CACHE.url, true, null ) );

// --- switching on and off (from the tab the feature is on)

fire( tabs[1], 'click' );
const activate = byTag( row( mount, 'fresh' ), 'button' )[1];
const freshMsg = byTag( row( mount, 'fresh' ), 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0];
fire( activate, 'click' );
check( 'Activate posts features/activate with the key', requests[requests.length - 1].action === 'features/activate' && requests[requests.length - 1].payload.key === 'fresh' && activate.disabled === true && freshMsg.textContent === text('/_admin/features/msg/activating') );
answer( 400, { error : 'feature "fresh" cannot be activated: nope' } );
check( 'a refusal shows the kernel\'s reason and reloads nothing', reloads === 0 && activate.disabled === false && hasClass( freshMsg, 'nino-admin-error' ) && freshMsg.textContent === '(400) feature "fresh" cannot be activated: nope' );
fire( activate, 'click' );
answer( 200, { feature : FEATURES[3] } );
check( 'success says so, keeps the hash on the panel and reloads the workbench', reloads === 1 && sandbox.window.location.hash === '#features' && freshMsg.textContent === text('/_admin/features/msg/activated')+ ' '+ text('/_admin/features/msg/reload') );

// --- removing the directory, which deactivating deliberately does not do

fire( tabs[1], 'click' );
const removeBtn = byTag( row( mount, 'old' ), 'button' )[0];
const oldMsg = byTag( row( mount, 'old' ), 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0];

confirmAnswer = false;
const beforeRemove = requests.length;
fire( removeBtn, 'click' );
check( 'declining the confirmation sends nothing and holds no button', requests.length === beforeRemove && removeBtn.disabled === false );

confirmAnswer = true;
fire( removeBtn, 'click' );
check( 'Remove posts features/remove with the key alone, holds the button and says so', requests[requests.length - 1].action === 'features/remove'
	&& JSON.stringify( requests[requests.length - 1].payload ) === '{"key":"old"}'
	&& removeBtn.disabled === true && oldMsg.textContent === text('/_admin/features/msg/removing') );

answer( 400, { error : 'could not remove /features/Old - the web server may not write there' } );
check( 'a refusal shows the kernel\'s reason and frees the button', removeBtn.disabled === false
	&& oldMsg.textContent === 'could not remove /features/Old - the web server may not write there' );

fire( removeBtn, 'click' );
answer( 200, { removed : 'old' } );
check( 'a removal reads the whole list again - what it took away may be what another row was waiting for', requests[requests.length - 1].action === 'features/list' );
answer( 200, listAnswer( CACHE.url, true, null, FEATURES.filter( function( f ) { return f.key !== 'old' } ) ) );
check( '...and the row is gone with no page reload: no rail entry changes when an inactive feature leaves', rowKeys( mount, 'feature' ) === 'fresh' && reloads === 1 );

panel.init();
answer( 200, listAnswer( CACHE.url, true, null ) );

// Deactivate and Update live on the feature's own screen now, not in the list
fire( tabs[0], 'click' );
fire( row( mount, 'sample' ), 'click' );
const deactivate = byTag( screen, 'button' ).filter( function( el ) { return el.textContent === text('/_admin/features/label/deactivate') } )[0];
fire( deactivate, 'click' );
check( 'Deactivate, from the screen, posts features/deactivate with the key', requests[requests.length - 1].action === 'features/deactivate' && requests[requests.length - 1].payload.key === 'sample' && hasClass( deactivate, 'nino-admin-btn-danger' ) );
answer( 200, { feature : FEATURES[2] } );
check( '...and reloads too', reloads === 2 );

fire( byTag( screen.children[0], 'a' )[0], 'click' );
fire( row( mount, 'plain' ), 'click' );
const update = byTag( screen, 'button' ).filter( function( el ) { return el.textContent === text('/_admin/features/label/update').replace( '%s', '1.0.0' ) } )[0];
fire( update, 'click' );
check( 'Update posts features/activate - the kernel\'s one step for an update is activating again', requests[requests.length - 1].action === 'features/activate' && requests[requests.length - 1].payload.key === 'plain' );
answer( 500, null );
check( 'a failed update falls back to its own error line', byTag( screen, 'p' ).some( function( el ) { return el.textContent === '(500) '+ text('/_admin/features/error/update') } ) );
fire( byTag( screen.children[0], 'a' )[0], 'click' );

// --- opening with a cache already on disk: the Available tab fills with no request

panel.init();
answer( 200, listAnswer( CACHE.url, true, CACHE ) );
check( 'a cached catalogue fills the Available tab straight from features/list - no features/catalogue request at all', requests.filter( function( r ) { return r.action === 'features/catalogue' } ).length === 0 );

// The panel stayed on Active from the previous section (a re-render keeps
// whichever tab is current) - switch back to look at the freshly cached one
fire( tabs[2], 'click' );

const availableTabs = byTag( mount.children[0], 'button' );
check( 'Available\'s count is the offers that are not already current: ancient, extra, helper, needy - not sample', availableTabs[2].textContent === text('/_admin/features/tab/available')+ ' (4)' );
check( 'the status line reads the cache\'s own stamp', byTag( mount.children[1], 'p' )[0].textContent === text('/_admin/features/label/catalogue-status').replace( '%s', CACHE.fetched ) );
check( 'and the category now offers what the catalogue brought as well - it is built from what is on screen, installed or not',
	byTag( mount.children[0].children[2], 'option' ).map( function( o ) { return o.value } ).join('|') === '|content|ui|marketing|security|system|' );

check( 'the Available tab is one card per offer that is not current, ancient/extra/helper/needy but not sample', rowKeys( mount, 'offer' ) === 'ancient,extra,helper,needy' );
check( 'an offer is a row of the same grouped list, its name in the shared copy, no status badge', offer( mount, 'extra' ).tagName === 'LI'
	&& hasClass( offer( mount, 'extra' ).children[0], 'nino-admin-list-copy' ) && offer( mount, 'extra' ).children[0].children[0].textContent === 'Zusatz' );
check( 'an available offer has Install as the primary action, and one line naming its version, its release date and what it is', byTag( offer( mount, 'extra' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/install') && hasClass( byTag( offer( mount, 'extra' ), 'button' )[0], 'nino-admin-btn-primary' )
	&& meta( offer( mount, 'extra' ) ) === text('/_admin/features/category/marketing')+ ' · '+ text('/_admin/features/label/version').replace( '%s', '1.0.0' )+ ' – '+ text('/_admin/features/label/released').replace( '%s', '2026-09-07' )+ ' · Ein extra'
	&& notes( offer( mount, 'extra' ) ).join('|') === text('/_admin/features/label/requires').replace( '%s', 'helper' ) );
check( 'an upgrade offers Update to the new version and names the one on disk', byTag( offer( mount, 'helper' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/update').replace( '%s', '1.2.0' )
	&& meta( offer( mount, 'helper' ) ) === text('/_admin/features/category/system')+ ' · '+ text('/_admin/features/label/version').replace( '%s', '1.2.0' )+ ' – '+ text('/_admin/features/label/installed').replace( '%s', '1.1.0' ) );
check( 'an incompatible one is greyed, offers nothing, and says what it asks for: the Nino constraint, and the extensions where it names some', byTag( offer( mount, 'needy' ), 'button' ).length === 0 && offer( mount, 'needy' ).attributes['aria-disabled'] === 'true'
	&& notes( offer( mount, 'needy' ) ).join('|') === text('/_admin/features/label/nino').replace( '%s', '^1.0' )+ '|'+ text('/_admin/features/label/extensions').replace( '%s', 'no_such_extension, other' )
	&& notes( offer( mount, 'ancient' ) ).join('|') === text('/_admin/features/label/nino').replace( '%s', '^0.9' )
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
// An install that pulled a requirement in with it names it: pressing Install
// on one feature and getting two is not something to work out from the
// Inactive tab afterwards
fire( install, 'click' );
answer( 200, { feature : EXTRA, updated : false, required : [ 'helper' ] } );
answer( 200, listAnswer( CACHE.url, true, CACHE, FEATURES_WITH_EXTRA ) );
fire( byTag( mount.children[0], 'button' )[2], 'click' );
check( 'an install that brought a requirement along says which one', offerMessage( mount, 'extra' ) === text('/_admin/features/msg/installed-with').replace( '%s', 'helper' ) );

const requestsBeforeInstall = requests.length;
fire( offerButton( mount, 'extra' ), 'click' );
answer( 200, { feature : EXTRA, updated : false } );
check( 'success reads the list again, and only the list - the cached catalogue\'s offers are recomputed there, no catalogue request and no page reload', requests.length === requestsBeforeInstall + 2 && requests[requests.length - 1].action === 'features/list' && reloads === 2 );

// The list answers a catalogue whose offers already reflect the install:
// extra now current - features/list itself would only carry this once the
// backend saw it, which is exactly what a fresh cache read gives it - and
// the installed feature itself, which the fixture above did not carry
const installedOffers = JSON.parse( JSON.stringify( OFFERS ) );
installedOffers[1] = Object.assign( {}, installedOffers[1], { state : 'current', local : '1.0.0' } );
answer( 200, listAnswer( CACHE.url, true, Object.assign( {}, CACHE, { offers : installedOffers } ), FEATURES_WITH_EXTRA ) );
check( 'the installed feature now shows on Inactive, off, with Activate - and Available lost it, straight from the cache', byTag( mount.children[0], 'button' )[2].textContent === text('/_admin/features/tab/available')+ ' (3)' );

fire( byTag( mount.children[0], 'button' )[1], 'click' );
check( 'the just-installed feature is on Inactive now, with Remove and Activate', row( mount, 'extra' ) !== undefined && byTag( row( mount, 'extra' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/remove')+ '|'+ text('/_admin/features/label/activate') );

fire( byTag( mount.children[0], 'button' )[2], 'click' );
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
	&& byTag( mount.children[0], 'button' )[2].textContent === text('/_admin/features/tab/available')+ ' (0)' );

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
check( 'the Available tab now shows the refreshed offers, count 4', byTag( mount.children[0], 'button' )[2].textContent === text('/_admin/features/tab/available')+ ' (4)'
	&& rowKeys( mount, 'offer' ) === 'ancient,extra,helper,needy' );
check( 'switching tabs and back leaves the refreshed cache in place - the tab bar rebuild does not lose state', ( fire( byTag( mount.children[0], 'button' )[0], 'click' ), fire( byTag( mount.children[0], 'button' )[2], 'click' ), rowKeys( mount, 'offer' ).split(',').filter( Boolean ).length === 4 ) );

// --- nothing installed at all, and a failed load

panel.showCurrent();
answer( 200, { dir : '/features', catalogueUrl : CACHE.url, writable : true, catalogue : null, features : [] } );
check( 'showCurrent reloads, and Inactive\'s empty state names the directory - Active empty too, both counted 0', requests[requests.length - 1].action === 'features/list'
	&& byTag( mount.children[0], 'button' )[0].textContent === text('/_admin/features/tab/active')+ ' (0)' && byTag( mount.children[0], 'button' )[1].textContent === text('/_admin/features/tab/inactive')+ ' (0)' );
fire( byTag( mount.children[0], 'button' )[1], 'click' );
check( 'Inactive, empty, names the features directory', findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/empty').replace( '%s', '/features' ) );
fire( byTag( mount.children[0], 'button' )[0], 'click' );
check( 'Active, empty, says no feature is on', findAll( mount.children[2], function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/active-empty') );
check( 'and with nothing to narrow the category is not drawn at all - a select with one option is a control that cannot be used',
	mount.children[0].children.length === 2 && byTag( mount.children[0], 'select' ).length === 0 );

panel.init();
answer( 500, null );
check( 'a failed load is reported through the workbench\'s words', mount.children.length === 1 && hasClass( mount.children[0], 'nino-admin-error' ) && mount.children[0].textContent === '(500) '+ text('/_admin/common/error/load') );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
