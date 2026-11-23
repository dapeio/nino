/**
 *	Nino									A compact filesystembased php framework
 *	admin-features-js-smoke.js	DOM-light checks for the Features panel's script
 *										(_admin/Nino/Modules/Features/assets/admin.js): that it
 *										attaches under its nav uri, which actions it posts and with
 *										what payload, that every word it renders is a fill both
 *										interface languages define, and what the guide asks of it by
 *										structure - one table for every tab, the count of a tab as a
 *										chip on its button, an address for the view of a feature, the
 *										bar of a form with one main button, a question for every
 *										switch, a toast for what went well and the refusal where the
 *										button was pressed. The real Nino.adminUi renders the shared
 *										blocks, over a dependency-free element stand-in; the question
 *										is recorded, not drawn.
 *
 *	Usage: node tests/admin-features-js-smoke.js
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

/** One row of a table by the key it carries: the <tr> of a feature, an offer, an application or a refused directory */
function row( root, key ) {
	return findAll( root, function( el ) { return el.tagName === 'TR' && el.dataset.row === key } )[0];
}

/** Every row's key, in the order they are drawn */
function rowKeys( root ) {
	return findAll( root, function( el ) { return el.tagName === 'TR' && typeof el.dataset.row === 'string' } ).map( function( el ) { return el.dataset.row } ).join(',');
}

/** The text of a node the script built out of pieces - the stand-in keeps them as children */
function textOf( el ) {
	return el === undefined ? '' : ( el.textContent !== '' ? el.textContent : ( el.children || [] ).map( textOf ).join('') );
}

/** The cells of a row, as text: name, category, version, state */
function cells( el ) {
	return byTag( el, 'td' ).map( textOf );
}

/** The words on the buttons of a row or of a bar, in the order they stand */
function words( el ) {
	return findAll( el, function( node ) { return node.tagName === 'BUTTON' } ).map( function( node ) { return node.textContent } ).join('|');
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
// The panel's second level: the view of one feature
const screen = element('div');
screen.id = 'features-detail';
// The pane the shell renders both into, with the head that names the panel over them (see Panels::panesHtml()):
// where the strip of the tabs and the category go
const pane = element('div');
pane.setAttribute( 'data-panel', 'features' );
const paneHead = element('div');
paneHead.className = 'admin-panel-head';
const paneTitle = element('h2');
paneTitle.className = 'admin-panel-title';
paneTitle.textContent = 'Features';
const paneActions = element('div');
paneActions.className = 'admin-panel-actions';
paneHead.appendChild( paneTitle );
paneHead.appendChild( paneActions );
pane.appendChild( paneHead );
pane.appendChild( mount );
pane.appendChild( screen );

const callbacks = [];
const requests = [];
let reloads = 0;

// The address the shell holds, and what the panel wrote into it: 'go' is the person's own move, 'set' only keeps it true
let hash = { panel : 'features', parts : [] };
const writes = [];
let leaveAnswer = true;
const left = [];

const Nino = {
	dir : '/site',
	admin : {
		router : {
			current : function() { return hash },
			set : function( panel, parts ) { writes.push( 'set '+ [ panel ].concat( parts ).join('/') ) },
			go : function( panel, parts ) { writes.push( 'go '+ [ panel ].concat( parts ).join('/') ); hash = { panel : panel, parts : parts } },
			href : function( panel, parts ) { return '#'+ [ panel ].concat( parts ).join('/') },
			leave : function( names, leaving, proceed, resync ) { left.push( { names : names, leaving : leaving } ); if( leaveAnswer === true ) proceed(); else resync() },
		},
	},
	events : { bindCallback : function( event, callback ) { if( event === 'ready' ) callbacks.push( callback ) } },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};

/** The element that has the focus in the pane, as the page would say */
function focused() {
	return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null;
}

const accountEl = element('span');
accountEl.textContent = 'admin@example.com';

const sandbox = {
	console : console,
	document : {
		get activeElement() { return focused() },
		createElement : element, createElementNS : function( space, tag ) { return element( tag ) },
		createTextNode : textNode,
		getElementById : function( id ) {
			if( id === 'features-list' )
				return mount;
			if( id === 'features-detail' )
				return screen;
			if( id === 'admin-user-email' )
				return accountEl;
			// Anything else is looked up where a browser would look: in the tree the panel just drew
			return findAll( pane, function( node ) { return node.id === id } )[0] ?? null;
		},
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};

// What the panel kept in the session for the page that follows a reload
const stored = {};
const kept = [];
sandbox.window = {
	Nino : Nino,
	sessionStorage : {
		getItem : function( key ) { return stored[key] ?? null },
		removeItem : function( key ) { delete stored[key] },
		setItem : function( key, value ) { stored[key] = value; kept.push( JSON.parse( value ) ) },
	},
	setTimeout : function( run ) { run() },
	location : { hash : '', reload : function() { reloads++ } },
};

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );

// What was said as a toast, and what the workbench was asked: the question is recorded and answered at once
const toasts = [];
Nino.adminUi.say = function( text ) { toasts.push( text ) };
const asked = [];
let answerWith = true;
Nino.adminUi.confirm = function( level, options ) { asked.push( { level : level, options : options } ); options.onChoose( answerWith ); return true };

load( '_admin/Nino/Modules/Features/assets/admin.js', context );

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	const request = requests[requests.length - 1];
	request.callback( { status : status, responseJSON : body } );
	return request;
}

const script = source('_admin/Nino/Modules/Features/assets/admin.js');
const panel 	= Nino.admin.features;

// The installed features: 'old' inactive with problems, 'fresh' inactive and free to switch on, 'plain' active with an update
// waiting and no setting, 'sample' active with a full schema, an install unit and a manual - one category each, 'fresh'
// deliberately without: a project whose features predate the field is the state the panel has to read well too
const FEATURES = [
	{ key : 'old', name : 'Old', description : '', category : 'system', maturity : '', version : '3.0.0', installed : null, active : false, update : false, requires : [ 'nowhere' ],
		components : [], calledIn : { total : 0, templates : [] }, problems : [ 'requires Nino ^0.9, this is 1.0.0', 'requires the php extension "no_such_extension"' ], settings : [], settingsTab : '', unit : false },
	{ key : 'plain', name : 'Plain', description : 'Nothing to set.', category : 'ui', maturity : '', version : '1.0.0', installed : '0.9.0', active : true, update : true, requires : [], components : [],
		calledIn : { total : 3, templates : [ 'page-home', 'page-about' ] }, problems : [], settings : [], settingsTab : '', unit : false },
	{ key : 'sample', name : 'Beispiel-Feature', description : 'Prüft den ganzen Feature-Vertrag.', manual : 'Setze `data-sample="on"` auf den Container.', category : 'content', maturity : 'Beispiel', version : '1.2.0', installed : '1.2.0', active : true, update : false, requires : [], components : [ 'component "x" is refused' ], calledIn : { total : 0, templates : [] }, problems : [], unit : true, settings : [
		{ name : 'enabled', type : 'bool', label : 'Aktiv', hint : '', required : false, min : null, max : null, maxlength : null, unit : '', options : [], value : true },
		{ name : 'limit', type : 'int', label : 'Limit', hint : 'Items per page', required : false, min : 1, max : 50, maxlength : null, unit : 'items', options : [], value : 7 },
		{ name : 'title', type : 'string', label : 'Title', hint : '', required : true, min : null, max : null, maxlength : 40, unit : '', options : [], value : 'Again' },
		{ name : 'notes', type : 'text', label : 'Notes', hint : '', required : false, min : null, max : null, maxlength : 10000, unit : '', options : [], value : 'a\nb' },
		{ name : 'contact', type : 'email', label : 'Contact', hint : '', required : false, min : null, max : null, maxlength : 1000, unit : '', options : [], value : '' },
		{ name : 'mode', type : 'select', label : 'Mode', hint : '', required : false, min : null, max : null, maxlength : null, unit : '', options : [ { value : 'fast', label : 'Fast' }, { value : 'safe', label : 'Sicher' } ], value : 'safe' },
		{ name : 'apiKey', type : 'secret', label : 'API key', hint : '', required : false, min : null, max : null, maxlength : 1000, unit : '', options : [], set : true },
		{ name : 'hosts', type : 'lines', label : 'Hosts', hint : '', required : false, min : null, max : null, maxlength : null, unit : '', options : [], value : [ 'one', 'two' ] },
	], settingsTab : '' },
	{ key : 'fresh', name : 'Fresh', description : 'Not switched on yet.', category : '', maturity : 'Alpha', version : '0.1.0', installed : null, active : false, update : false, requires : [ 'bundled' ], components : [], calledIn : { total : 0, templates : [] }, problems : [], settings : [], settingsTab : '', unit : false },
	{ key : 'bundled', name : 'Bundled', description : '', category : 'system', maturity : '', version : '1.0.0', installed : '1.0.0', active : false, update : false, requires : [], dependents : [ 'Fresh' ], components : [], calledIn : { total : 0, templates : [] }, problems : [], settings : [], settingsTab : '', unit : false },
];

// A feature of its own panel: its settings are edited there, and this view says where
const PANELLED = Object.assign( {}, FEATURES[2], { key : 'panelled', name : 'Panelled', settingsTab : 'panelled-settings', manual : '', description : '' } );

/** A features/list answer: the catalogue url, whether features/ is writable, the cache - null unless given - and the installed features */
function listAnswer( catalogueUrl, writable, cache, features, rejected ) {
	return { dir : '/features', catalogueUrl : catalogueUrl, writable : writable, catalogue : cache || null, features : features || FEATURES, rejected : rejected || [] };
}

// The catalogue as features/catalogue - and, once cached, features/list - name its offers: 'ancient' and 'needy' incompatible, 'extra' available,
// 'helper' an upgrade over what is on disk, 'sample' already current - the one state the Available tab excludes
const OFFERS = [
	{ key : 'ancient', name : 'Ancient', description : 'Ein ancient', category : 'content', maturity : '', version : '1.0.0', nino : '^0.9', ext : [], requires : [], archive : 'https://catalogue.test/features/ancient-1.0.0.tar.gz', released : '2026-09-07', state : 'incompatible', local : null },
	{ key : 'extra', name : 'Zusatz', description : 'Ein extra', category : 'marketing', maturity : 'Beta', version : '1.0.0', nino : '^1.0', ext : [], requires : [ 'helper' ], archive : 'https://catalogue.test/features/extra-1.0.0.tar.gz', released : '2026-09-07', state : 'available', local : null },
	{ key : 'helper', name : 'Helper', description : '', category : 'system', maturity : '', version : '1.2.0', nino : '^1.0', ext : [], requires : [], archive : 'https://catalogue.test/features/helper-1.2.0.tar.gz', released : '', state : 'upgrade', local : '1.1.0' },
	{ key : 'needy', name : 'Needy', description : 'Ein needy', category : 'security', maturity : '', version : '1.0.0', nino : '^1.0', ext : [ 'no_such_extension', 'other' ], requires : [], archive : 'https://catalogue.test/features/needy-1.0.0.tar.gz', released : '2026-09-07', state : 'incompatible', local : null },
	{ key : 'sample', name : 'Beispiel-Feature', description : 'Ein sample', category : 'content', maturity : '', version : '1.2.0', nino : '^1.0', ext : [], requires : [ 'helper' ], archive : 'https://catalogue.test/features/sample-1.2.0.tar.gz', released : '2026-09-07', state : 'current', local : '1.2.0' },
];

const APPLICATIONS = [
	{ key : 'shop', name : 'Shop', description : 'A shop.', state : 'partial', start : 'own', next : '/_admin#shop', features : [ { key : 'cart', name : 'Cart', state : 'off', on : false }, { key : 'pay', name : 'Pay', state : 'on', on : true } ] },
	{ key : 'blog', name : 'Blog', description : '', state : 'applied', start : '', next : '/_admin#blog', features : [ { key : 'posts', name : 'Posts', state : 'on', on : true } ] },
];

// The configured catalogue url, as features/list names it under catalogueUrl
const CATALOGUE_URL = 'https://catalogue.getnino.dev/catalogue.json';

const CACHE = { fetched : '2026-09-07 12:00', offers : OFFERS, applications : APPLICATIONS };

console.log('Features panel');

// --- the contract with the class

check( 'the script attaches under its nav uri with init and showCurrent, and binds its ready callback', typeof panel === 'object' && typeof panel.init === 'function' && typeof panel.showCurrent === 'function'
	&& callbacks.length === 1 && callbacks[0] === panel.init );

const admin = source('_admin/Nino/Modules/Features/Admin/Admin.php');
check( 'the panel is a system entry with the nav uri the script speaks and two mount points, and every action method guards itself with the panel\'s permission',
	admin.includes( "return [ 'features', '/_admin/nav/features', 15, 'system' ];" ) && admin.includes( "return [ 'features-list', 'features-detail' ];" )
	&& script.includes( "'features-list'" ) && script.includes( "'features-detail'" )
	&& ( admin.match( /guardPerm\( \$appData, \$request, self::MANAGE_PERM \)/g ) || [] ).length === 16 );
check( 'the script posts the eleven actions of the panel and no other',
	script.includes( "Nino.adminUi.api.call( 'features/'+ endpoint" )
	&& [ 'list', 'settings', 'catalogue', 'install', 'remove', 'application-plan', 'application-apply', 'deactivate-refused', 'reapply' ].every( function( action ) { return script.includes( "_apiCall( '"+ action+ "'" ) } )
	&& script.includes( "? 'deactivate' : 'activate'" ) && ( script.match( /_apiCall\( /g ) || [] ).length === 11 );

// --- one language, one text system

const keys = [];
const keyRe = /getText\(\s*'(\/_admin\/[^']+)'\s*\)/g;
let match;
for( const file of [ 'admin.js', 'settings.js' ] ) {
	const body = source('_admin/Nino/Modules/Features/assets/'+ file);
	while( ( match = keyRe.exec( body ) ) )
		if( keys.indexOf( match[1] ) === -1 )
			keys.push( match[1] );
}
const wordless = keys.filter( function( key ) {
	return ( moduleEn[key] === undefined && workbenchEn[key] === undefined ) || ( moduleDe[key] === undefined && workbenchDe[key] === undefined );
} );
check( 'every word the scripts ask for by a literal is a fill in both interface languages'+ ( wordless.length ? ' - missing: '+ wordless.join(', ') : '' ), keys.length > 80 && wordless.length === 0 );

// The words the class phrases itself and the words passed to the helpers as a key are fills too
const phrased = [];
const phrasedRe = /'(\/_admin\/features\/(?:error|msg|label|confirm|state|expect)\/[^']+)'/g;
for( const body of [ admin, script ] )
	while( ( match = phrasedRe.exec( body ) ) )
		if( phrased.indexOf( match[1] ) === -1 )
			phrased.push( match[1] );
const unphrased = phrased.filter( function( key ) { return moduleEn[key] === undefined || moduleDe[key] === undefined } );
check( 'every message the class phrases itself, and every fill the script hands on by key, is a fill of the module in both languages'+ ( unphrased.length ? ' - missing: '+ unphrased.join(', ') : '' ), phrased.length > 80 && unphrased.length === 0 );
check( 'no value carries a live shortcode, and the nav label and the dashboard tile are among the words', moduleEn['/_admin/nav/features'] !== undefined && moduleEn['/_admin/features/label/active'] !== undefined
	&& Object.keys( moduleEn ).every( function( key ) { return /[[\]]/.test( moduleEn[key] ) === false && /[[\]]/.test( moduleDe[key] ) === false } ) );

// --- the first drawing: one request, the list on its address

panel.init();
panel.init();
check( 'the panel asks for the list once, however often init() is called while the answer is on its way', requests.length === 1 && requests[0].action === 'features/list' );
answer( 200, listAnswer( CATALOGUE_URL, true, null ) );

const strip = function() { return byTag( paneHead.children[1], 'button' ) };
const chips = function() { return strip().map( function( button ) { return textOf( findAll( button, function( el ) { return hasClass( el, 'nino-admin-tab-badge' ) } )[0] ) } ).join('|') };
const tabContent = function() { return mount.children.filter( function( el ) { return el.id === 'features-tabpanel' } )[0] };

check( 'the strip of the four tabs is in the head of the pane, the tabs Active, Inactive, Available and Applications in that order', paneHead.children[1].attributes.role === 'tablist'
	&& strip().map( function( el ) { return el.id } ).join(',') === 'features-tabbutton-active,features-tabbutton-inactive,features-tabbutton-available,features-tabbutton-applications' );
check( 'the count of a tab is the chip of the workbench on its button, not a part of the word - and it counts what the tab holds', strip().every( function( button ) { return button.children.filter( function( el ) { return hasClass( el, 'nino-admin-badge' ) && hasClass( el, 'nino-admin-tab-badge' ) } ).length === 1 } )
	&& chips() === '2|3|0|0' && strip().every( function( button ) { return /\(\d+\)/.test( button.textContent ) === false } ) );

// --- one form of rows for every tab

check( 'Active is a table of the workbench with the search over its full width, one row to a feature, and the four columns of the guide with the actions last',
	rowKeys( mount ) === 'plain,sample' && findAll( tabContent(), function( el ) { return hasClass( el, 'nino-admin-table-toolbar--wide' ) } ).length === 1
	&& byTag( tabContent(), 'th' ).map( textOf ).join('|') === [ text('/_admin/features/col/name'), text('/_admin/features/label/category'), text('/_admin/features/col/version'), text('/_admin/features/col/state'), '' ].join('|') );
check( 'the state is a badge with a word in the colour of what it means, and a feature that waits for an update says so, not only "active"', cells( row( mount, 'plain' ) )[3] === text('/_admin/features/state/update')
	&& cells( row( mount, 'sample' ) )[3] === text('/_admin/features/state/active')
	&& findAll( row( mount, 'plain' ), function( el ) { return hasClass( el, 'nino-admin-badge' ) } )[0].dataset.state === 'warn'
	&& findAll( row( mount, 'sample' ), function( el ) { return hasClass( el, 'nino-admin-badge' ) && el.textContent === text('/_admin/features/state/active') } )[0].dataset.state === 'ok'
	&& findAll( row( mount, 'sample' ), function( el ) { return hasClass( el, 'nino-admin-badge' ) && el.textContent === 'Beispiel' } ).length === 1 );
check( 'the actions of an active row are small, at its end, and the one that does not take anything away is not red: Update, then Deactivate', words( row( mount, 'plain' ) ) === text('/_admin/features/label/update').replace( '%s', '1.0.0' )+ '|'+ text('/_admin/features/label/deactivate')
	&& byTag( row( mount, 'plain' ), 'button' ).every( function( el ) { return hasClass( el, 'nino-admin-btn--small' ) && hasClass( el, 'nino-admin-btn-danger' ) === false } ) );

const toInactive = function() { fire( strip()[1], 'click' ) };
writes.length = 0;
toInactive();
check( 'a tab picked is the person\'s own move written into the address, and the list follows', writes[0] === 'go features/@inactive' );
check( 'Inactive is the same table of the same four columns', rowKeys( mount ) === 'old,fresh,bundled' && byTag( tabContent(), 'th' ).length === 5 && tabContent().attributes['aria-labelledby'] === 'features-tabbutton-inactive' );
check( 'an inactive row offers Activate, and Uninstall last and in red, and no Activate where something stands in the way',
	words( row( mount, 'fresh' ) ) === text('/_admin/features/label/activate')+ '|'+ text('/_admin/features/label/remove')
	&& hasClass( byTag( row( mount, 'fresh' ), 'button' )[1], 'nino-admin-btn-danger' ) && hasClass( byTag( row( mount, 'fresh' ), 'button' )[0], 'nino-admin-btn-danger' ) === false
	&& words( row( mount, 'old' ) ) === text('/_admin/features/label/remove') && words( row( mount, 'bundled' ) ) === text('/_admin/features/label/activate')+ '|'+ text('/_admin/features/label/remove') );
check( 'the reasons an activation is refused, and what a feature requires, are read whole under its name', findAll( row( mount, 'old' ), function( el ) { return hasClass( el, 'admin-features-note' ) } ).map( textOf ).join('|')
	=== text('/_admin/features/label/requires').replace( '%s', 'nowhere' )+ '|'+ FEATURES[0].problems.join('|') );

// A search over the rows narrows the chips too, without drawing the table anew
const tableNow = byTag( tabContent(), 'table' )[0];
const search = function() { return findAll( tabContent(), function( el ) { return el.tagName === 'INPUT' } )[0] };
search().value = 'fre';
fire( search(), 'input' );
check( 'a search narrows the rows and the chips with them, in the same table, and it is kept over a tab and a drawing', rowKeys( mount ) === 'fresh' && chips() === '0|1|0|0' && byTag( tabContent(), 'table' )[0] === tableNow
	&& ( fire( strip()[0], 'click' ), search().value === 'fre' ) );
search().value = '';
fire( search(), 'input' );
fire( strip()[0], 'click' );

// The category stands in the head of the pane, where the shell keeps a slot for actions, and narrows every tab
const category = function() { return byTag( paneActions.children[0], 'select' )[0] };
check( 'the category is a labelled select in the slot for actions of the pane head - the list takes no row of its own for it - offering what is on screen, the features without one last',
	category().tagName === 'SELECT' && paneActions.children[0].tagName === 'LABEL' && mount.children[0] === tabContent() && byTag( category(), 'option' ).map( function( o ) { return o.value } ).join('|') === '|content|ui|system|' );
category().value = 'content';
fire( category(), 'change' );
check( 'picking one narrows the rows and the count of every tab, and the pick survives the drawing it triggers', rowKeys( mount ) === 'sample' && chips() === '1|0|0|0' && category().value === 'content' );
category().value = '';
fire( category(), 'change' );

// --- the address of a feature's view

const sampleRow = row( mount, 'sample' );
const link = byTag( sampleRow, 'a' )[0];
check( 'the name of a row that opens is a link to the address of the feature, which is how the keyboard opens a row', link !== undefined && link.href === '#features/sample' && link.textContent === 'Beispiel-Feature' );
writes.length = 0;
fire( link, 'click' );
check( 'opening it is the person\'s own move written into the address, then the view is on: the list hidden, the view shown, and the category with the list', writes[0] === 'go features/sample'
	&& mount.classList.contains('admin-hidden') === true && screen.classList.contains('admin-hidden') === false && panel._openFeature === 'sample' && paneActions.children[0].hidden === true );
check( 'the view opens with the context bar, whose one way back says where it leads, and the name of the feature in it', hasClass( screen.children[0], 'nino-admin-contextbar' )
	&& byTag( screen.children[0], 'a' ).length === 1 && byTag( screen.children[0], 'a' )[0].textContent === text('/_admin/common/label/back-to').replace( '%s', 'Features' )
	&& byTag( screen.children[0], 'h3' )[0].textContent === 'Beispiel-Feature' && byTag( screen, 'h2' ).length === 0 );

// The view holds one form, novalidate, whose bar is the bar of every form
const form = byTag( screen, 'form' )[0];
const bar = findAll( form, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } );
const barButtons = function() { return byTag( bar[0], 'button' ) };
check( 'one form, novalidate, with one bar - the state badge and its line, the side actions, ONE main button at the right edge, which is Save for a feature with settings',
	form.noValidate === true && bar.length === 1 && hasClass( barButtons()[barButtons().length - 1], 'nino-admin-btn-primary' ) && barButtons()[barButtons().length - 1].type === 'submit'
	&& barButtons().filter( function( el ) { return hasClass( el, 'nino-admin-btn-primary' ) } ).length === 1
	&& words( bar[0] ) === text('/_admin/features/label/reapply')+ '|'+ text('/_admin/features/label/deactivate')+ '|'+ text('/_admin/common/label/save') );
check( 'Deactivate is not dangerous and does not stand alone at the left: it is a side action before the main button, and nothing in the bar is red for a feature that is on',
	barButtons().filter( function( el ) { return el.textContent === text('/_admin/features/label/deactivate') } ).every( function( el ) { return hasClass( el, 'nino-admin-btn-danger' ) === false && hasClass( el, 'nino-admin-btn-secondary' ) } )
	&& bar[0].children.indexOf( barButtons()[1] ) > 0 && barButtons().every( function( el ) { return hasClass( el, 'nino-admin-btn-danger' ) === false } ) );

const tabs = findAll( screen, function( el ) { return el.attributes.role === 'tablist' } )[0];
const tabButtons = tabs === undefined ? [] : byTag( tabs, 'button' );
const aboutPane = findAll( screen, function( el ) { return hasClass( el, 'features-about' ) } )[0];
const settingsPane = byTag( form, 'fieldset' )[0];
check( 'what it is and what can be set on it are the two tabs of the block, over both panes, which are built and one of them hidden - so what is typed survives a look at the description',
	tabButtons.map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/tab/about')+ '|'+ text('/_admin/features/tab/settings') && aboutPane.hidden === false && settingsPane.hidden === true
	&& tabButtons[0].attributes['aria-controls'] === aboutPane.id && form.children.indexOf( tabs ) < form.children.indexOf( aboutPane ) );
check( 'what has to be seen is not hidden in the view: the line of facts, and what the kernel did not register as notices at the top', byTag( form, 'p' ).some( function( el ) { return hasClass( el, 'nino-admin-hint' ) && el.textContent.indexOf( 'Beispiel' ) === 0 && el.textContent.indexOf( '1.2.0' ) !== -1 } )
	&& findAll( form, function( el ) { return hasClass( el, 'nino-admin-notice' ) } ).map( textOf ).join('|') === text('/_admin/features/label/component-refused').replace( '%s', 'component "x" is refused' ) );

// The controls are the blocks, each carrying its name, and the secret never carries a value
const controls = form.querySelectorAll('[data-key]');
const byKey = {};
controls.forEach( function( el ) { byKey[el.dataset.key] = el } );
check( 'every setting is the block of its type, in the order of the schema, with the data-field the answer of the server finds it by', controls.map( function( el ) { return el.dataset.key } ).join(',') === 'enabled,limit,title,notes,contact,mode,apiKey,hosts'
	&& controls.every( function( el ) { return el.dataset.field === el.dataset.key } )
	&& byKey.enabled.type === 'checkbox' && byKey.limit.type === 'number' && byKey.title.required === true && byKey.contact.type === 'email' && byKey.mode.tagName === 'SELECT'
	&& byKey.hosts.value === 'one\ntwo' && byKey.apiKey.type === 'password' && byKey.apiKey.value === '' );
check( 'no label, input or textarea of the panel\'s own is built in the script: the fields are the blocks - and the buttons are made by button(), the strip of the tabs apart', /createElement\(\s*'(label|input|textarea)'\s*\)/.test( script.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /^\s*\/\/.*$/gm, '' ) ) === false );

// Checked when saving: what is wrong is said under the field and once in the line, and nothing is sent
const before = requests.length;
byKey.title.value = '   ';
byKey.limit.value = '99';
fire( form, 'submit' );
const line = findAll( bar[0], function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0];
check( 'a setting that is missing or outside its bounds is said under its field, once in the line, the first takes the focus, the tab with it comes forward and nothing is posted',
	requests.length === before && form.querySelectorAll('[aria-invalid]').map( function( el ) { return el.dataset.key } ).sort().join() === 'limit,title' && line.dataset.state === 'error'
	&& settingsPane.hidden === false && focused() === byKey.limit );
byKey.title.value = 'Again';
byKey.limit.value = '7';
fire( form, 'submit' );
const posted = requests[requests.length - 1];
check( 'saving posts features/settings with the key and every control by its data-key, a number as its text, a list as its raw lines and the secret as nothing, which keeps it',
	posted.action === 'features/settings' && posted.payload.key === 'sample' && Object.keys( posted.payload.fields ).sort().join(',') === 'apiKey,contact,enabled,hosts,limit,mode,notes,title'
	&& posted.payload.fields.limit === '7' && posted.payload.fields.hosts === 'one\ntwo' && posted.payload.fields.apiKey === '' && posted.payload.fields.enabled === true );
check( 'the line says it is saving, and the checking of the earlier try is gone', line.dataset.state === 'saving' && form.querySelectorAll('[aria-invalid]').length === 0 );

answer( 400, { error : 'limit: must be at most 50', code : 'features_settings', field : 'limit', problems : { limit : { code : 'max', params : [ 50 ] } } } );
check( 'a refusal of the server names its field: that one is marked and has the focus, the sentence stands in the line, and the form is as it was', line.dataset.state === 'error' && byKey.limit.attributes['aria-invalid'] === 'true' && focused() === byKey.limit
	&& line.textContent === text('/_admin/error/features_settings') && screen.children.length > 0 );

check( 'what the server found wrong stands under the field in the interface language, as the guide words it - the name of the setting and what is expected - and not as the English of the kernel', findAll( form, function( el ) { return hasClass( el, 'nino-admin-field-error' ) } ).map( textOf ).join() === Nino.adminUi.format( text('/_admin/features/error/invalid'), 'Limit', Nino.adminUi.format( text('/_admin/features/expect/max'), 50 ) ) );
byKey.title.focus();
fire( form, 'submit' );
const onTheWay = requests.length;
fire( form, 'submit' );
check( 'while the settings are on their way the main button is held and a second submit sends nothing', requests.length === onTheWay && barButtons()[barButtons().length - 1].disabled === true );
byKey.notes.value = 'typed meanwhile';
answer( 200, { feature : FEATURES[2] } );
check( 'what is typed while it saves stays in its field: the form is not drawn again, it says it holds something to save and not that it is saved, and the button is free',
	byTag( screen, 'form' )[0] === form && byKey.notes.value === 'typed meanwhile' && line.dataset.state === 'dirty' && toasts.length === 0 && barButtons()[barButtons().length - 1].disabled === false );
byKey.notes.value = 'a\nb';
toasts.length = 0;
byKey.title.focus();
fire( form, 'submit' );
answer( 200, { feature : FEATURES[2] } );
check( 'a saved form reads the list again, and the new bar says it is saved - the badge, and the toast; the line stays empty', requests[requests.length - 1].action === 'features/list' );
answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
const rebuilt = byTag( screen, 'form' )[0];
const rebuiltLine = findAll( rebuilt, function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0];
check( 'after the save the focus is on the control it was on, in the form that was drawn, and not on the page', focused() !== null && focused().dataset.key === 'title' && rebuilt.contains( focused() ) );
check( 'the view stays where it is - on the address of the feature - and on the tab it was saved from, with the toast and an empty line', rebuilt !== form && screen.classList.contains('admin-hidden') === false && panel._openFeature === 'sample'
	&& toasts.join() === text('/_admin/common/msg/saved') && rebuiltLine.textContent === '' && rebuiltLine.dataset.state === 'saved' );

// --- the way back, the address, the unknown key

writes.length = 0;
fire( byTag( screen.children[0], 'a' )[0], 'click' );
check( 'the way back is the person\'s move into the address of the tab, and shows the list with the focus on the row that was opened', writes[0] === 'go features'
	&& mount.classList.contains('admin-hidden') === false && screen.classList.contains('admin-hidden') === true && panel._openFeature === '' && paneActions.children[0].hidden === false
	&& focused() === byTag( row( mount, 'sample' ), 'a' )[0] );

// The address names a feature: a reload comes back to it
panel._ready = false;
panel._openFeature = '';
panel._tab = 'active';
hash = { panel : 'features', parts : [ 'sample' ] };
panel.init();
answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
check( 'a page opened on #features/<key> shows that feature\'s view, over the list', panel._openFeature === 'sample' && screen.classList.contains('admin-hidden') === false && byTag( screen.children[0], 'h3' )[0].textContent === 'Beispiel-Feature' );

hash = { panel : 'features', parts : [ 'nothing-of-the-kind' ] };
panel._ready = false;
panel.init();
answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
check( 'a key the project does not have is an empty state, with the way back in the context bar - and no form, no bar', screen.classList.contains('admin-hidden') === false && byTag( screen, 'form' ).length === 0
	&& findAll( screen, function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/unknown') && byTag( screen.children[0], 'a' ).length === 1 );
fire( byTag( screen.children[0], 'a' )[0], 'click' );

hash = { panel : 'features', parts : [ '@inactive' ] };
panel._ready = false;
panel.init();
answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
check( 'a tab of the list is its own address word, and the list opens on it', panel._tab === 'inactive' && panel._openFeature === '' && rowKeys( mount ) === 'old,fresh,bundled' );

hash = { panel : 'features', parts : [ 'fresh' ] };
panel._ready = false;
panel.init();
answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
const freshBar = findAll( screen, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } )[0];
check( 'a feature that is off has its view too: Uninstall at the left in red, the state, and Activate as the one main button - nothing to save, so no form of settings',
	panel._openFeature === 'fresh' && freshBar.children[0].textContent === text('/_admin/features/label/remove') && hasClass( freshBar.children[0], 'nino-admin-btn-danger' )
	&& hasClass( byTag( freshBar, 'button' )[byTag( freshBar, 'button' ).length - 1], 'nino-admin-btn-primary' ) && byTag( freshBar, 'button' ).length === 2 && byTag( screen, 'fieldset' ).length === 0 );
fire( byTag( screen.children[0], 'a' )[0], 'click' );

hash = { panel : 'features', parts : [ 'old' ] };
panel._ready = false;
panel.init();
answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
const oldMain = byTag( findAll( screen, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } )[0], 'button' ).filter( function( el ) { return hasClass( el, 'nino-admin-btn-primary' ) } )[0];
check( 'where something stands in the way, the main button is held back for good and what stands in the way is a notice above - the actions of the view cannot let it go',
	oldMain.disabled === true && findAll( screen, function( el ) { return hasClass( el, 'nino-admin-notice' ) } ).length === FEATURES[0].problems.length );
fire( byTag( screen.children[0], 'a' )[0], 'click' );

// A step through the history changes the address and nothing else: the panel follows it, and is asked before it leaves an open form
panel._ready = true;
hash = { panel : 'features', parts : [ 'plain' ] };
panel.showCurrent();
check( 'the panel follows an address that changed under it, asking the shell first where a view is open', panel._openFeature === 'plain' && left.length > 0 && left[left.length - 1].leaving === false );
leaveAnswer = false;
const keptOpen = panel._openFeature;
hash = { panel : 'features', parts : [ 'sample' ] };
panel.showCurrent();
check( 'a move the shell refuses leaves the view that is on, and writes its address back', panel._openFeature === keptOpen && left[left.length - 1].leaving === true && writes[writes.length - 1] === 'set features/plain' );
leaveAnswer = true;
hash = { panel : 'features', parts : [] };
panel.showCurrent();
check( 'the bare address is the list on Active', panel._openFeature === '' && panel._tab === 'active' && screen.classList.contains('admin-hidden') === true );

// A view without settings and with an update: the update is its main button
fire( byTag( row( mount, 'plain' ), 'a' )[0], 'click' );
const plainBar = findAll( screen, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } )[0];
check( 'a feature without settings has no fieldset and no Save: the update that waits is its main button, and Deactivate stands before it, not red',
	byTag( screen, 'fieldset' ).length === 0 && words( plainBar ) === text('/_admin/features/label/deactivate')+ '|'+ text('/_admin/features/label/update').replace( '%s', '1.0.0' )
	&& hasClass( byTag( plainBar, 'button' )[1], 'nino-admin-btn-primary' ) && hasClass( byTag( plainBar, 'button' )[0], 'nino-admin-btn-danger' ) === false );
check( 'where a feature has a panel of its own, the settings are edited there: its view has no form of them and links to the tab', ( function() {
	const entry = Object.assign( {}, PANELLED );
	panel._features.push( entry );
	panel._openFeature = 'panelled';
	panel._renderDetail();
	const ok = byTag( screen, 'fieldset' ).length === 0 && byTag( screen, 'a' ).some( function( el ) { return el.href === '#panelled-settings' } )
		&& byTag( findAll( screen, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } )[0], 'button' ).filter( function( el ) { return el.type === 'submit' } ).length === 0;
	panel._features.pop();
	panel._openFeature = 'plain';
	panel._renderDetail();
	return ok;
} )() );
fire( byTag( screen.children[0], 'a' )[0], 'click' );

// --- a switch is asked about, held while it runs, and said as a toast after the reload

const activeList = function() { fire( strip()[0], 'click' ) };
toInactive();
const freshActivate = byTag( row( mount, 'fresh' ), 'button' )[0];
const freshUninstall = byTag( row( mount, 'fresh' ), 'button' )[1];
asked.length = 0;
answerWith = false;
fire( freshActivate, 'click' );
check( 'Activate asks first as a switch of the workbench is asked - level big, titled like the act, the answer worded like it, Cancel the other one - and a No sends nothing',
	asked.length === 1 && asked[0].level === 'big' && asked[0].options.title === text('/_admin/features/confirm/activate-title') && asked[0].options.yes === asked[0].options.title
	&& asked[0].options.no === text('/_admin/common/label/cancel') && requests[requests.length - 1].action === 'features/list' );
check( '...it names what comes on with it - the requirement that is off - as a group under the question, and is not the dangerous answer', asked[0].options.details.length === 1 && asked[0].options.details[0].lines.join() === 'Bundled' && asked[0].options.kind !== 'danger' );
answerWith = true;
const sent = requests.length;
fire( freshActivate, 'click' );
const activating = requests[requests.length - 1];
const freshLine = findAll( row( mount, 'fresh' ), function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0];
check( 'a Yes posts features/activate with the key; while it runs the row says so and holds every one of its buttons - Uninstall too - and a second press starts nothing',
	activating.action === 'features/activate' && activating.payload.key === 'fresh' && requests.length === sent + 1 && freshLine.textContent === text('/_admin/features/msg/activating')
	&& freshActivate.disabled === true && freshUninstall.disabled === true && ( fire( freshActivate, 'click' ), fire( freshUninstall, 'click' ), requests.length === sent + 1 ) );

answer( 400, { error : 'no', code : '' } );
check( 'a refusal stands in the row, where it was pressed - in the kernel\'s words, as an alert - frees the buttons, puts the focus back on the button and reloads nothing',
	freshLine.dataset.state === 'error' && freshLine.attributes.role === 'alert' && freshActivate.disabled === false && freshUninstall.disabled === false && focused() === freshActivate && reloads === 0 && toasts.length === 1 );

const retried = requests.length;
fire( freshActivate, 'click' );
check( 'a row that was refused can be used: the next press starts a NEW request', requests.length === retried + 1 );
answer( 200, { switchedOn : [ { key : 'fresh', name : 'Fresh' }, { key : 'bundled', name : 'Bundled' } ] } );
const afterActivate = kept[kept.length - 1];
check( 'an activation ends in a reload, because the rail is the server\'s, and what it says is kept for the page that follows: the sentence "<name> is active", with what came on with it, and no line of the old kind',
	reloads === 1 && afterActivate.text === text('/_admin/features/msg/active-with').replace( '%s', 'Fresh' ).replace( '%s', 'Bundled' ) && afterActivate.notices.length === 0 && afterActivate.focus === 'bundled' );

// The page that follows says it, as a toast, and brings the notices that stay
{
	toasts.length = 0;
	stored['nino-admin-features-after'] = JSON.stringify( { text : 'Fresh is active.', notices : [ 'A place still holds it.' ], filter : '', category : '', focus : 'bundled', user : 'admin@example.com', at : Date.now() } );
	panel._ready = false;
	panel._notices = [];
	hash = { panel : 'features', parts : [ '@inactive' ] };
	panel.init();
	answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
	check( 'what a reload kept is a toast and a notice at the top of the list, and the focus is on the row that took the place of the one that left',
		toasts.join() === 'Fresh is active.' && findAll( mount, function( el ) { return hasClass( el, 'nino-admin-notice' ) } ).map( textOf ).join() === 'A place still holds it.'
		&& mount.children.indexOf( findAll( mount, function( el ) { return el.id === 'features-notices' } )[0] ) < mount.children.indexOf( tabContent() )
		&& focused() === byTag( row( mount, 'bundled' ), 'button' )[0] );
	stored['nino-admin-features-after'] = JSON.stringify( { text : 'x', notices : [], filter : '', category : '', focus : '', user : 'someone@else', at : Date.now() } );
	toasts.length = 0;
	panel._ready = false;
	panel.init();
	answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
	check( 'what another account kept is not said', toasts.length === 0 );
	fire( strip()[1], 'click' );
	check( 'and a notice stays until the next tab replaces it', findAll( mount, function( el ) { return hasClass( el, 'nino-admin-notice' ) } ).length === 0 );
}

// Deactivate: asked, not dangerous, names the templates; the places that still hold its shortcodes are a notice, the sentence a toast
activeList();
asked.length = 0;
const plainOff = byTag( row( mount, 'plain' ), 'button' )[1];
fire( plainOff, 'click' );
check( 'Deactivate asks at level big and is not the dangerous answer; it names the templates that call its components as a group of the question, with what is not shown cut short by a line of its own',
	asked.length === 1 && asked[0].level === 'big' && asked[0].options.kind !== 'danger' && asked[0].options.title === text('/_admin/features/confirm/deactivate-title')
	&& asked[0].options.details[0].lines.join('|') === 'page-home|page-about|'+ text('/_admin/features/label/found-more').replace( '%d', '1' ) );
answer( 200, { found : [ { shortcode : 'plain', total : 1, places : [ { kind : 'template', where : 'page-home' } ] } ], privacy : [ 'Cookies' ] } );
const afterOff = kept[kept.length - 1];
check( 'it ends in a reload that keeps the sentence for a toast and the places for notices, one line each - the shortcodes that still stand, and the sections of the policy',
	requests[requests.length - 1].action === 'features/deactivate' && afterOff.text === text('/_admin/features/msg/inactive').replace( '%s', 'Plain' ) && afterOff.notices.length === 2
	&& afterOff.notices[0].indexOf( '[plain] ' ) !== -1 && afterOff.notices[1].indexOf( 'Cookies' ) !== -1 );

// Update: asked, posts activate, says it is updated. The page is built again after a reload, and so are the rows
panel._renderPanel();
asked.length = 0;
fire( byTag( row( mount, 'plain' ), 'button' )[0], 'click' );
answer( 200, { switchedOn : [] } );
check( 'an update asks at level big and posts the one action an update is, saying "<name> is updated"', asked[0].level === 'big' && asked[0].options.title === text('/_admin/features/confirm/update-title')
	&& requests[requests.length - 1].action === 'features/activate' && kept[kept.length - 1].text === text('/_admin/features/msg/updated').replace( '%s', 'Plain' ) );

// ...and where the unit of the feature was left out because the project has pages, the sentence for it follows the one of the update, with the name of the feature
panel._renderPanel();
fire( byTag( row( mount, 'plain' ), 'button' )[0], 'click' );
answer( 200, { switchedOn : [], skipped : [ { key : 'plain', name : 'Plain' } ] } );
check( 'an activation or an update that left the unit out says so after its own sentence: the project has pages already and the feature placed nothing',
	kept[kept.length - 1].text === text('/_admin/features/msg/updated').replace( '%s', 'Plain' ) + ' ' + text('/_admin/features/msg/unit-skipped').replace( '%s', 'Plain' )
	&& panel._skippedText( [] ) === '' && panel._skippedText( undefined ) === '' );

// Uninstall: destructive, says what goes and what stays, then the list is read again and the row after it takes the focus
toInactive();
asked.length = 0;
{
	// A feature that others require: the question names them, as the ones that cannot be switched on afterwards
	const keepAnswer = answerWith;
	answerWith = false;
	fire( byTag( row( mount, 'bundled' ), 'button' )[1], 'click' );
	check( 'Uninstall of a feature that other features require names them as a third group of the question, the ones that cannot be switched on afterwards - and one that nobody requires has two',
		asked[0].options.details.length === 3 && asked[0].options.details[2].title === text('/_admin/features/confirm/dependents') && asked[0].options.details[2].lines.join('|') === 'Fresh' );
	answerWith = keepAnswer;
}
asked.length = 0;
const uninstall = byTag( row( mount, 'fresh' ), 'button' )[1];
fire( uninstall, 'click' );
check( 'Uninstall is the destructive question, titled like the act, with what is deleted and what is kept as the two groups of its details - the directory goes, the settings, the files and the copies of its unit stay',
	asked[0].level === 'destructive' && asked[0].options.title === text('/_admin/features/confirm/remove-title') && asked[0].options.details.length === 2
	&& asked[0].options.details[0].title === text('/_admin/features/confirm/removed') && asked[0].options.details[1].lines.length === 1 );
{
	// What stays is named only where there is something: files under data/ and the copies of an install unit
	const keep = answerWith;
	answerWith = false;
	asked.length = 0;
	panel._remove( Object.assign( {}, FEATURES[3], { unit : true, data : [ 'x.php' ] } ), panel._context( 'k' ), null, false );
	check( 'a feature that owns files under data/ and brings an install unit says that those stay too', asked[0].options.details[1].lines.length === 3 );
	answerWith = keep;
}
check( 'asked, it posts features/remove and holds the row meanwhile', requests[requests.length - 1].action === 'features/remove' && requests[requests.length - 1].payload.key === 'fresh' && uninstall.disabled === true );
answer( 400, { error : 'no' } );
check( 'a refusal of the server is said at the row, and the row stays', uninstall.disabled === false && findAll( row( mount, 'fresh' ), function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0].dataset.state === 'error' );
fire( uninstall, 'click' );
answer( 400, { error : 'on', code : 'features_remove_active' } );
check( 'a refusal that says the state on screen is out of date reads the list again', requests[requests.length - 1].action === 'features/list' );
answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
check( '...and the sentence stands at the row that is drawn then', findAll( row( mount, 'fresh' ), function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0].dataset.state === 'error' && findAll( row( mount, 'fresh' ), function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0].textContent !== ''
	&& panel._rowParts.get( 'fresh' ).busy === false );
toasts.length = 0;
fire( uninstall, 'click' );
answer( 200, { privacy : [ 'Cookies' ] } );
check( 'removed, it says so as a toast, keeps the sections of the policy that still describe it as a notice, and reads the list again', toasts.join() === text('/_admin/features/msg/removed').replace( '%s', 'Fresh' )
	&& panel._notices.join() === text('/_admin/features/msg/deactivated-privacy').replace( '%s', 'Cookies' ) && requests[requests.length - 1].action === 'features/list' );
answer( 200, listAnswer( CATALOGUE_URL, true, null, FEATURES.filter( function( f ) { return f.key !== 'fresh' } ) ) );
check( '...and the focus goes to the row that took its place', rowKeys( mount ) === 'old,bundled' && focused() === byTag( row( mount, 'bundled' ), 'button' )[0] );

// Deactivate of a refused manifest that runs
{
	panel._rejected = [ { dir : 'broken', reason : 'no key', active : true } ];
	panel._renderPanel();
	fire( strip()[0], 'click' );
	check( 'a refused manifest is a row with its reason, in the state "refused", and Deactivate as its one action', row( mount, 'broken' ) !== undefined && cells( row( mount, 'broken' ) )[3] === text('/_admin/features/state/refused')
		&& words( row( mount, 'broken' ) ) === text('/_admin/features/label/deactivate') );
	asked.length = 0;
	fire( byTag( row( mount, 'broken' ), 'button' )[0], 'click' );
	answer( 200, {} );
	check( 'it asks, posts the directory alone and says the directory is inactive after the reload', asked[0].level === 'big' && requests[requests.length - 1].action === 'features/deactivate-refused' && requests[requests.length - 1].payload.dir === 'broken'
		&& kept[kept.length - 1].text === text('/_admin/features/msg/inactive').replace( '%s', 'broken' ) );
	panel._rejected = [ { dir : 'Gone', reason : 'there is no directory features/Gone', active : true, missing : true } ];
	panel._renderPanel();
	check( 'a feature that is switched on and not installed is a row of its own kind: the state "not installed", the sentence that it is in the catalogue with its name in it - and not the reason of the kernel - and Deactivate as its one action',
		cells( row( mount, 'Gone' ) )[3] === text('/_admin/features/state/offer') && findAll( row( mount, 'Gone' ), function( el ) { return hasClass( el, 'admin-features-note' ) } ).map( textOf ).join('|') === text('/_admin/features/label/missing').replace( '%s', 'Gone' )
		&& words( row( mount, 'Gone' ) ) === text('/_admin/features/label/deactivate') );
	panel._rejected = [];
	panel._renderPanel();
}

// Apply the content again: from the bar of the view, asked, the answer a toast, a refusal in the line
fire( byTag( row( mount, 'sample' ), 'a' )[0], 'click' );
asked.length = 0;
const reapply = byTag( findAll( screen, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } )[0], 'button' )[0];
toasts.length = 0;
fire( reapply, 'click' );
check( 'applying the content again is asked and posts features/reapply', asked.length === 1 && asked[0].level === 'big' && requests[requests.length - 1].action === 'features/reapply' );
answer( 400, { error : 'no' } );
check( 'a refusal stands in the line of the bar, not in a toast', toasts.length === 0 && findAll( screen, function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0].dataset.state === 'error' );
fire( reapply, 'click' );
answer( 200, { applied : { templates : { added : 1, present : 0 } } } );
check( 'what it did is a toast', toasts.join() === text('/_admin/features/msg/reapplied').replace( '%s', text('/_admin/features/label/unit-templates')+ ': 1' ).replace( '%s', text('/_admin/features/label/unit-nothing') ) );
toasts.length = 0;
fire( reapply, 'click' );
answer( 200, { applied : { skipped : 'pages', templates : { added : 0, present : 0 } } } );
check( 'a unit that was left out because the project has pages says so, with the name of the feature, and not that it placed nothing', toasts.length === 1 && toasts[0] === text('/_admin/features/msg/unit-skipped').replace( '%s', 'Beispiel-Feature' ) );
fire( byTag( screen.children[0], 'a' )[0], 'click' );

// --- the catalogue

// Without a catalogue: the sentence in words, nothing to refresh
panel.init();
answer( 200, listAnswer( '', true, null ) );
fire( strip()[2], 'click' );
check( 'with the catalogue switched off the Available tab says so in words and the list has no bar', findAll( tabContent(), function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/catalogue-off').replace( '%s', '/features' )
	&& findAll( mount, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } ).length === 0 );

// Not loaded yet: the sentence and the one step that helps
panel.init();
answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
fire( strip()[2], 'click' );
const unloaded = findAll( tabContent(), function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0];
check( 'before the catalogue was loaded the tab says so and carries the button that loads it, beside the one of the bar', hasClass( unloaded, 'nino-admin-empty--action' ) && words( unloaded ) === text('/_admin/features/label/catalogue-refresh')
	&& findAll( mount, function( el ) { return hasClass( el, 'nino-admin-list-actions' ) } ).length === 1 );

const barOfList = function() { return findAll( mount, function( el ) { return hasClass( el, 'nino-admin-list-actions' ) } )[0] };
check( 'the bar of the list is the one of the blocks: Refresh catalogue as its one button, and a line that says the catalogue is not loaded - not an error',
	words( barOfList() ) === text('/_admin/features/label/catalogue-refresh') && findAll( barOfList(), function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0].textContent === text('/_admin/features/label/catalogue-unloaded') );
fire( byTag( barOfList(), 'button' )[0], 'click' );
check( 'Refresh posts features/catalogue, and says that it is loading in the line, holding the button', requests[requests.length - 1].action === 'features/catalogue'
	&& findAll( barOfList(), function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0].textContent === text('/_admin/features/msg/catalogue-loading') && byTag( barOfList(), 'button' )[0].disabled === true );
answer( 502, { error : 'unreachable' } );
check( 'a catalogue that cannot be reached is an error in that line', findAll( barOfList(), function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0].dataset.state === 'error' );
fire( byTag( barOfList(), 'button' )[0], 'click' );
answer( 200, { fetched : CACHE.fetched, writable : true, offers : OFFERS, applications : APPLICATIONS } );
check( 'a catalogue that came is the cache of every tab: Available has its offers and the chips follow', chips() === '2|3|4|2' && panel._cache.offers.length === 5 );
fire( strip()[2], 'click' );
check( 'Available is the same table: an offer is a row with its category, its version - what is on disk and the day it was released where they differ - and the state of what it is',
	rowKeys( mount ) === 'ancient,extra,helper,needy' && cells( row( mount, 'helper' ) )[2].indexOf( '1.1.0' ) !== -1 && cells( row( mount, 'extra' ) )[3] === text('/_admin/features/state/offer')
	&& cells( row( mount, 'helper' ) )[3] === text('/_admin/features/state/offer-update') && cells( row( mount, 'needy' ) )[3] === text('/_admin/features/state/incompatible') );
check( 'an offer has Install - Update to <version> for an upgrade - and one no version of which fits this kernel has no button at all and says what it asks for',
	words( row( mount, 'extra' ) ) === text('/_admin/features/label/install') && words( row( mount, 'helper' ) ) === text('/_admin/features/label/update').replace( '%s', '1.2.0' ) && words( row( mount, 'needy' ) ) === ''
	&& findAll( row( mount, 'needy' ), function( el ) { return hasClass( el, 'admin-features-note' ) } ).map( textOf ).join('|').indexOf( 'no_such_extension' ) !== -1 );

asked.length = 0;
fire( byTag( row( mount, 'extra' ), 'button' )[0], 'click' );
check( 'Install asks first at level big, titled like the act, and names what it requires as a group; the request is features/install with the key and the version',
	asked[0].level === 'big' && asked[0].options.title === text('/_admin/features/confirm/install-title') && asked[0].options.details[0].lines.join() === 'helper'
	&& requests[requests.length - 1].action === 'features/install' && requests[requests.length - 1].payload.key === 'extra' && requests[requests.length - 1].payload.version === '1.0.0' );
check( 'an upgrade is asked as an update', ( function() {
	asked.length = 0;
	answer( 400, { error : 'no' } );
	fire( byTag( row( mount, 'helper' ), 'button' )[0], 'click' );
	return asked[0].options.title === text('/_admin/features/confirm/update-title');
} )() );
answer( 400, { error : 'no' } );
toasts.length = 0;
fire( byTag( row( mount, 'extra' ), 'button' )[0], 'click' );
answer( 200, { activated : false, required : [] } );
check( 'an install that switched nothing on says it as a toast and reads the list again, without a reload', toasts.join() === text('/_admin/features/msg/installed') && requests[requests.length - 1].action === 'features/list' );
answer( 200, listAnswer( CATALOGUE_URL, true, CACHE ) );
const reloadsBefore = reloads;
fire( byTag( row( mount, 'extra' ), 'button' )[0], 'click' );
answer( 200, { activated : true, switchedOn : [ { key : 'extra', name : 'Zusatz' }, { key : 'helper', name : 'Helper' } ] } );
check( 'one that switched the feature on builds the workbench again and keeps the sentence for it, naming what came with it', reloads === reloadsBefore + 1 && kept[kept.length - 1].text === text('/_admin/features/msg/installed-active-with').replace( '%s', 'Helper' ) );
fire( byTag( row( mount, 'helper' ), 'button' )[0], 'click' );
const reloadsPending = reloads;
answer( 200, { pending : true } );
check( 'an update of a feature that runs is applied by activating it again, in a request of its own, with a reload', requests[requests.length - 1].action === 'features/activate' && requests[requests.length - 1].payload.key === 'helper' );
answer( 200, { switchedOn : [] } );
check( '...which says "<name> is updated"', reloads === reloadsPending + 1 && kept[kept.length - 1].text === text('/_admin/features/msg/updated').replace( '%s', 'Helper' ) );

// A directory the server cannot write: the archive, and a notice
panel.init();
answer( 200, listAnswer( CATALOGUE_URL, false, CACHE ) );
fire( strip()[2], 'click' );
check( 'where features/ cannot be written an offer links to its archive instead of installing, and a notice at the top says why', byTag( row( mount, 'extra' ), 'a' ).some( function( el ) { return el.href === OFFERS[1].archive } )
	&& words( row( mount, 'extra' ) ) === '' && findAll( tabContent(), function( el ) { return hasClass( el, 'nino-admin-notice' ) } ).length === 1 );

// --- the empty states, in words, with the next step where there is one

panel.init();
answer( 200, listAnswer( CATALOGUE_URL, true, { fetched : 'x', offers : [], applications : [] }, [] ) );
const emptyOf = function() { return findAll( tabContent(), function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0] };
fire( strip()[0], 'click' );
check( 'no features installed is a box with the sentence of what a feature directory is', emptyOf().textContent === text('/_admin/features/hint/active-empty') );
fire( strip()[1], 'click' );
check( 'the Inactive tab says it has nothing in a sentence of its own', hasClass( emptyOf(), 'nino-admin-empty' ) && emptyOf().textContent === text('/_admin/features/hint/empty').replace( '%s', '/features' ) );
fire( strip()[2], 'click' );
check( 'a catalogue that lists nothing says so and has the button that asks it again', textOf( emptyOf() ).indexOf( text('/_admin/features/hint/catalogue-empty') ) === 0 && words( emptyOf() ) === text('/_admin/features/label/catalogue-refresh') );
fire( strip()[3], 'click' );
check( 'the same for the applications', words( emptyOf() ) === text('/_admin/features/label/catalogue-refresh') );

// A switch pressed in the view of a feature leaves the page on that view, whichever way it was switched
{
	panel._features = FEATURES.slice();
	panel._renderPanel();
	panel._showDetail( 'sample' );
	const offButton = byTag( findAll( screen, function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } )[0], 'button' ).filter( function( el ) { return el.textContent === text('/_admin/features/label/deactivate') } )[0];
	fire( offButton, 'click' );
	answer( 200, { found : [], privacy : [] } );
	check( 'Deactivate in the view of a feature builds the page again on the address of that feature, as Activate does - a feature that is off has a view', sandbox.window.location.hash === '#features/sample' );
	panel._openFeature = '';
	panel._renderPanel();
}

// A feature may be called like a tab: the tabs are written with an @, the feature has its own address
{
	const named = Object.assign( {}, FEATURES[1], { key : 'active', name : 'Active feature', update : false } );
	panel._features.push( named );
	check( '#features/active is the view of the feature `active`, #features/@active the tab - and the address of the view is the key', panel._named( [ 'active' ] ).key === 'active' && panel._named( [ '@active' ] ).key === ''
		&& panel._named( [ '@inactive' ] ).tab === 'inactive' && ( panel._openFeature = 'active', panel._tabParts().join() === 'active' ) );
	panel._openFeature = '';
	panel._features.pop();
}

// What a manifest says is the text it is: nothing of it is read as markup
{
	const markup = '<img src=x onerror=1><b>x</b>';
	const hostile = Object.assign( {}, FEATURES[1], { key : 'hostile', name : markup, description : '<i>d</i>'+ markup, requires : [ '<i>r</i>' ], maturity : '<i>m</i>', problems : [ '<i>p</i>' ], active : true, update : false, settings : [], manual : '' } );
	panel._features.push( hostile );
	panel._tab = 'active';
	panel._renderPanel();
	const small = findAll( row( mount, 'hostile' ), function( el ) { return el.tagName === 'SMALL' } ).map( textOf );
	check( 'the name, the description, the notes and the maturity of a manifest stand as text in the table, with no element made of what they hold',
		byTag( row( mount, 'hostile' ), 'img' ).length === 0 && byTag( row( mount, 'hostile' ), 'b' ).length === 0 && textOf( byTag( row( mount, 'hostile' ), 'a' )[0] ) === markup && small.indexOf( '<i>d</i>'+ markup ) !== -1 && small.some( function( t ) { return t.indexOf( '<i>r</i>' ) !== -1 } ) && small.indexOf( '<i>p</i>' ) !== -1 && textOf( byTag( row( mount, 'hostile' ), 'strong' )[0] ).indexOf( '<i>m</i>' ) !== -1 );
	panel._openFeature = 'hostile';
	panel._renderDetail();
	check( '...and in the view of the feature', byTag( screen, 'img' ).length === 0 && byTag( screen, 'b' ).length === 0 && byTag( screen.children[0], 'h3' )[0].textContent === markup
		&& byTag( screen, 'p' ).some( function( el ) { return textOf( el ) === '<i>d</i>'+ markup } ) );
	panel._openFeature = '';
	panel._features.pop();
	panel._renderPanel();
}

// --- the applications are rows of the same table

panel.init();
answer( 200, listAnswer( CATALOGUE_URL, true, CACHE ) );
fire( strip()[3], 'click' );
check( 'Applications is the same table, with the name and the state of each as a word and a badge, how far it is set up, and the features it switches on read whole under the name',
	rowKeys( mount ) === 'shop,blog' && byTag( tabContent(), 'table' ).length === 1 && cells( row( mount, 'shop' ) )[1] === text('/_admin/features/state/partial')
	&& findAll( row( mount, 'shop' ), function( el ) { return hasClass( el, 'admin-features-note' ) } ).map( textOf ).join('|').indexOf( 'Cart' ) !== -1 );
check( 'a set that is not complete has Set up, one that is has the way on to what it brought as a link - no button', words( row( mount, 'shop' ) ) === text('/_admin/features/label/application-setup') && words( row( mount, 'blog' ) ) === ''
	&& byTag( row( mount, 'blog' ), 'a' )[0].href === '/site/_admin#blog' );
fire( byTag( row( mount, 'shop' ), 'button' )[0], 'click' );
check( 'Set up asks the server what it comes to before it asks the person: features/application-plan with the key', requests[requests.length - 1].action === 'features/application-plan' && requests[requests.length - 1].payload.application === 'shop' );
asked.length = 0;
answer( 200, { todo : [ 'cart' ], application : APPLICATIONS[0] } );
check( 'then it asks at level big, and runs the features one request each, in the order of the plan', asked[0].level === 'big' && requests[requests.length - 1].action === 'features/application-apply' && requests[requests.length - 1].payload.feature === 'cart' );
answer( 400, { error : 'no' } );
check( 'a feature that fails stops the run, and the row says which, why, and what is on - and the button that goes on is Try again', requests[requests.length - 1].action === 'features/list' );
answer( 200, listAnswer( CATALOGUE_URL, true, CACHE ) );
check( '...as the text of the row\'s own line, in the state of an error, after the rows were drawn again', findAll( row( mount, 'shop' ), function( el ) { return hasClass( el, 'nino-admin-status' ) } )[0].dataset.state === 'error'
	&& words( row( mount, 'shop' ) ) === text('/_admin/features/label/application-retry') );
fire( byTag( row( mount, 'shop' ), 'button' )[0], 'click' );
answer( 200, { todo : [ 'cart' ], application : APPLICATIONS[0] } );
answer( 200, {} );
check( 'the last feature on builds the workbench again and keeps the sentence for the toast', reloads > reloadsPending && kept[kept.length - 1].text === text('/_admin/features/msg/application-done').replace( '%s', 'Shop' ).replace( '%s', 'Cart' ) );

// --- unsaved input of the open view is asked about by the shell, and every way a save ends is told to it

{
	const watched = [];
	const registry = { isDirty : function() { return true }, guard : function( names, proceed ) { watched.push( names ); proceed() }, snapshot : function() {}, watchForm : function( name, getter, save ) { watched.push( { name : name, getter : getter, save : save } ) } };
	Nino.admin.dirty = registry;
	panel.init();
	answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
	fire( strip()[0], 'click' );
	panel._showDetail( 'sample' );
	const outcomes = [];
	const typed = byTag( screen, 'form' )[0].querySelectorAll('[data-key]').filter( function( el ) { return el.dataset.key === 'title' } )[0];
	typed.value = '';
	panel._saveDetail( function( ok ) { outcomes.push( ok ) } );
	check( 'a save that is stopped by the checking of the form ends in done( false )', outcomes.join() === 'false' && requests[requests.length - 1].action === 'features/list' );
	typed.value = 'ok';
	panel._saveDetail( function( ok ) { outcomes.push( ok ) } );
	answer( 400, { error : 'no' } );
	check( '...so does one the server refuses', outcomes.join() === 'false,false' );
	panel._saveDetail( function( ok ) { outcomes.push( ok ) } );
	answer( 200, { feature : FEATURES[2] } );
	check( '...and one that went through ends in done( true )', outcomes.join() === 'false,false,true' );
	answer( 200, listAnswer( CATALOGUE_URL, true, null ) );
	check( 'a tab picked over a view that holds input asks the shell about the feature panel', ( function() {
		watched.length = 0;
		Nino.admin.dirty.isDirty = function() { return true };
		fire( strip()[1], 'click' );
		return JSON.stringify( watched[0] ) === '["features"]';
	} )() );
	delete Nino.admin.dirty;
}

// --- the stylesheet of the panel names the head and the rows of this panel, and the view of a feature, and nothing of the blocks

const css = source('_admin/Nino/Modules/Features/assets/admin.css');
check( 'the stylesheet is bundled by the panel and writes no size of type, weight or space of its own', admin.includes( "'/assets/admin.css'" ) && css.includes( '@layer nino.tool {' )
	&& /font-size|font-weight/.test( css.replace( /\/\*[\s\S]*?\*\//g, '' ) ) === false && /(margin|padding|gap)[a-z-]*:[^;]*\d+(\.\d+)?(rem|px)/.test( css.replace( /\/\*[\s\S]*?\*\//g, '' ) ) === false );

done();
