/**
 *	Nino									A compact filesystembased php framework
 *	admin-features-js-smoke.js	DOM-light checks for the Features panel's script
 *										(_admin/Nino/Modules/Features/assets/admin.js): that it
 *										attaches under its nav uri, which actions it posts and
 *										with what payload, that every word it renders is a fill
 *										both interface languages define, that a secret's input
 *										is always empty, and that one Save collects every
 *										setting of its feature by data-key - and, below the
 *										list, the catalogue block: nothing fetched until its
 *										button is pressed, one card per offer with the button
 *										its state allows, and an install that reads the list
 *										and the catalogue again rather than reloading. The
 *										real Nino.adminUi renders the shared controls, over a
 *										dependency-free element stand-in.
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
const catalogueMount = element('div');
catalogueMount.id = 'features-catalogue';

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
		getElementById : function( id ) { return id === 'features-list' ? mount : ( id === 'features-catalogue' ? catalogueMount : null ) },
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

const LIST = {
	dir : '/features',
	catalogue : 'https://catalogue.getnino.dev/catalogue.json',
	features : [
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
	],
};

console.log('Features panel');

check( 'the script attaches under its nav uri with init and showCurrent', typeof panel === 'object' && typeof panel.init === 'function' && typeof panel.showCurrent === 'function' );
check( 'and binds its ready callback', callbacks.length === 1 && callbacks[0] === panel.init );

// The backend half of the same contract, read from the class
const admin = source('_admin/Nino/Modules/Features/Admin/Admin.php');
check( 'the panel is a system entry with the nav uri the script speaks, two mount points and six actions',
	admin.includes( "return [ 'features', '/_admin/nav/features', 15, 'system' ];" ) && admin.includes( "return [ 'features-list', 'features-catalogue' ];" ) && script.includes( "'features-list'" ) && script.includes( "'features-catalogue'" )
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
check( 'the six status words and the reload hint are among them', [ 'active', 'inactive', 'update', 'incompatible', 'available', 'installed' ].every( function( s ) { return keys.indexOf( '/_admin/features/status/'+ s ) !== -1 } ) && keys.indexOf( '/_admin/features/msg/reload' ) !== -1 );

// The words the class phrases itself - a catalogue refusal, an update that
// did not apply - are fills too, read server-side in the interface language
const phrased = [];
const phrasedRe = /_say\( \$appData, '(\/_admin\/[^']+)'/g;
while( ( match = phrasedRe.exec( admin ) ) )
	if( phrased.indexOf( match[1] ) === -1 )
		phrased.push( match[1] );
const unphrased = phrased.filter( function( key ) { return moduleEn[key] === undefined || moduleDe[key] === undefined } );
check( 'every message the class phrases itself is a fill of the module in both languages'+ ( unphrased.length ? ' - missing: '+ unphrased.join(', ') : '' ), phrased.length >= 4 && unphrased.length === 0
	&& phrased.indexOf( '/_admin/features/error/catalogue-off' ) !== -1 && phrased.indexOf( '/_admin/features/error/catalogue-key' ) !== -1
	&& moduleEn['/_admin/features/error/catalogue-reason'].includes( '%s' ) && moduleDe['/_admin/features/error/catalogue-reason'].includes( '%s' )
	&& moduleEn['/_admin/features/error/update-after-install'].includes( '%s' ) && moduleDe['/_admin/features/error/update-after-install'].includes( '%s' ) );
check( 'the module\'s two text files declare the same keys', Object.keys( moduleEn ).sort().join(',') === Object.keys( moduleDe ).sort().join(',') && Object.keys( moduleEn ).length > 20 );
check( 'the nav label and the dashboard tile are among them, and no value carries a live shortcode', moduleEn['/_admin/nav/features'] !== undefined && moduleEn['/_admin/features/label/active'] !== undefined
	&& Object.keys( moduleEn ).every( function( key ) { return /[[\]]/.test( moduleEn[key] ) === false && /[[\]]/.test( moduleDe[key] ) === false } ) );

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

// --- the list

panel.init();
check( 'init loads features/list through the shell\'s one endpoint', requests.length === 1 && requests[0].action === 'features/list' && requests[0].uri === '/_admin/' && requests[0].method === 'POST' );

answer( 200, LIST );
check( 'opening the panel fetches nothing but the list - the catalogue block is drawn from the url alone', requests.length === 1 && catalogueMount.children.length === 1 && catalogueMount.children[0].dataset.catalogue === 'on'
	&& byTag( catalogueMount, 'h3' )[0].textContent === text('/_admin/features/label/catalogue') && byTag( catalogueMount, 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/hint/catalogue').replace( '%s', LIST.catalogue ) && el.textContent.includes( LIST.catalogue ) } )
	&& byTag( catalogueMount, 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/catalogue-load') && hasClass( byTag( catalogueMount, 'button' )[0], 'nino-admin-btn-secondary' ) && byTag( catalogueMount, 'section' ).length === 1 );
check( 'the intro names the directory, as the tinted lead hint', mount.children.length > 0 && hasClass( mount.children[0], 'nino-admin-hint-lead' ) && mount.children[0].textContent.includes( '/features' ) && mount.children[0].textContent === text('/_admin/features/hint/intro').replace( '%s', '/features' ) );
check( 'one card per feature, in the order the backend sorted them', byTag( mount, 'section' ).map( function( el ) { return el.dataset.feature } ).join(',') === 'old,plain,sample,fresh' && byTag( mount, 'section' ).every( function( el ) { return hasClass( el, 'nino-admin-card' ) } ) );

const statuses = {};
findAll( mount, function( el ) { return typeof el.dataset.status === 'string' } ).forEach( function( el ) { statuses[el.dataset.status] = el } );
check( 'the status is a word from the text system: incompatible, update, active, inactive', Object.keys( statuses ).sort().join(',') === 'active,inactive,incompatible,update'
	&& statuses.active.textContent === text('/_admin/features/status/active') && statuses.inactive.textContent === text('/_admin/features/status/inactive')
	&& statuses.update.textContent === text('/_admin/features/status/update') && statuses.incompatible.textContent === text('/_admin/features/status/incompatible') );
check( 'an incompatible one is underlined as an error, but by the word first', hasClass( statuses.incompatible, 'nino-admin-error' ) && hasClass( statuses.incompatible, 'nino-admin-eyebrow' ) && hasClass( statuses.active, 'nino-admin-error' ) === false );

const old = section( mount, 'old' );
check( 'every problem is a line of its own, in the kernel\'s words', byTag( old, 'p' ).filter( function( el ) { return hasClass( el, 'nino-admin-error' ) } ).map( function( el ) { return el.textContent } ).join('|') === LIST.features[0].problems.join('|') );
check( 'and a feature with problems offers no button at all', byTag( old, 'button' ).length === 0 && byTag( old, 'form' ).length === 0 );
check( 'the requirements are named', byTag( old, 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/requires').replace( '%s', 'nowhere' ) } ) );

const plain = section( mount, 'plain' );
check( 'an installed version behind the manifest is shown beside it', byTag( plain, 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/version').replace( '%s', '1.0.0' )+ ' – '+ text('/_admin/features/label/installed').replace( '%s', '0.9.0' ) } )
	&& byTag( section( mount, 'sample' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/version').replace( '%s', '1.2.0' ) } ) );
check( 'an update offers Update and Deactivate, no settings form without settings', byTag( plain, 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/update').replace( '%s', '1.0.0' )+ '|'+ text('/_admin/features/label/deactivate') && byTag( plain, 'form' ).length === 0 );

const fresh = section( mount, 'fresh' );
check( 'an inactive feature without problems offers Activate alone', byTag( fresh, 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/activate') && hasClass( byTag( fresh, 'button' )[0], 'nino-admin-btn-primary' ) );
check( 'the description is shown as it arrived', byTag( fresh, 'p' ).some( function( el ) { return el.textContent === 'Not switched on yet.' } ) );

// --- the settings form

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
answer( 200, { feature : LIST.features[2] } );
check( 'a saved form reloads the list rather than trusting what was typed', requests[requests.length - 1].action === 'features/list' );
answer( 200, LIST );
const rebuilt = byTag( section( mount, 'sample' ), 'form' )[0];
check( 'and the confirmation survives the re-render', rebuilt !== form && byTag( rebuilt, 'p' ).some( function( el ) { return el.textContent === text('/_admin/common/msg/saved') } ) );

// --- switching on and off

const activate = byTag( section( mount, 'fresh' ), 'button' )[0];
const freshMsg = byTag( section( mount, 'fresh' ), 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0];
fire( activate, 'click' );
check( 'Activate posts features/activate with the key', requests[requests.length - 1].action === 'features/activate' && requests[requests.length - 1].payload.key === 'fresh' && activate.disabled === true && freshMsg.textContent === text('/_admin/features/msg/activating') );
answer( 400, { error : 'feature "fresh" cannot be activated: nope' } );
check( 'a refusal shows the kernel\'s reason and reloads nothing', reloads === 0 && activate.disabled === false && hasClass( freshMsg, 'nino-admin-error' ) && freshMsg.textContent === '(400) feature "fresh" cannot be activated: nope' );
fire( activate, 'click' );
answer( 200, { feature : LIST.features[3] } );
check( 'success says so, keeps the hash on the panel and reloads the workbench', reloads === 1 && sandbox.window.location.hash === '#features' && freshMsg.textContent === text('/_admin/features/msg/activated')+ ' '+ text('/_admin/features/msg/reload') );

const deactivate = byTag( section( mount, 'sample' ), 'button' ).filter( function( el ) { return el.textContent === text('/_admin/features/label/deactivate') } )[0];
fire( deactivate, 'click' );
check( 'Deactivate posts features/deactivate with the key', requests[requests.length - 1].action === 'features/deactivate' && requests[requests.length - 1].payload.key === 'sample' && hasClass( deactivate, 'nino-admin-btn-secondary' ) );
answer( 200, { feature : LIST.features[2] } );
check( '...and reloads too', reloads === 2 );

const update = byTag( section( mount, 'plain' ), 'button' )[0];
fire( update, 'click' );
check( 'Update posts features/activate - the kernel\'s one step for an update is activating again', requests[requests.length - 1].action === 'features/activate' && requests[requests.length - 1].payload.key === 'plain' );
answer( 500, null );
check( 'a failed update falls back to its own error line', byTag( section( mount, 'plain' ), 'p' ).some( function( el ) { return el.textContent === '(500) '+ text('/_admin/features/error/update') } ) );

// --- the catalogue

const CATALOGUE = {
	url : LIST.catalogue,
	generated : '2026-09-07T12:00:00Z',
	writable : true,
	offers : [
		{ key : 'ancient', name : 'Ancient', description : 'Ein ancient', version : '1.0.0', nino : '^0.9', ext : [], requires : [], directory : 'Ancient', archive : 'https://catalogue.test/features/ancient-1.0.0.tar.gz', size : 10, released : '2026-09-07', state : 'incompatible', fits : false, local : null, active : false },
		{ key : 'extra', name : 'Zusatz', description : 'Ein extra', version : '1.0.0', nino : '^1.0', ext : [], requires : [ 'helper' ], directory : 'Extra', archive : 'https://catalogue.test/features/extra-1.0.0.tar.gz', size : 10, released : '2026-09-07', state : 'available', fits : true, local : null, active : false },
		{ key : 'helper', name : 'Helper', description : '', version : '1.2.0', nino : '^1.0', ext : [], requires : [], directory : 'Helper', archive : 'https://catalogue.test/features/helper-1.2.0.tar.gz', size : 10, released : '', state : 'upgrade', fits : true, local : '1.1.0', active : true },
		{ key : 'needy', name : 'Needy', description : 'Ein needy', version : '1.0.0', nino : '^1.0', ext : [ 'no_such_extension', 'other' ], requires : [], directory : 'Needy', archive : 'https://catalogue.test/features/needy-1.0.0.tar.gz', size : 10, released : '2026-09-07', state : 'incompatible', fits : false, local : null, active : false },
		{ key : 'sample', name : 'Beispiel-Feature', description : 'Ein sample', version : '1.2.0', nino : '^1.0', ext : [], requires : [ 'helper' ], directory : 'Sample', archive : 'https://catalogue.test/features/sample-1.2.0.tar.gz', size : 10, released : '2026-09-07', state : 'current', fits : true, local : '1.2.0', active : true },
	],
};

function offer( key ) {
	return byTag( catalogueMount, 'section' ).filter( function( el ) { return el.dataset.offer === key } )[0];
}

function offerMsg( key ) {
	return byTag( offer( key ), 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0];
}

function loadButton() {
	return byTag( catalogueMount, 'button' ).filter( function( el ) { return el.textContent === text('/_admin/features/label/catalogue-load') } )[0];
}

function loadMsg() {
	return byTag( catalogueMount.children[0], 'p' ).filter( function( el ) { return el.attributes['aria-live'] === 'polite' } )[0];
}

panel.init();
answer( 200, LIST );
const reloadsBefore = reloads;
const requestsBefore = requests.length;

fire( loadButton(), 'click' );
check( 'Load catalogue posts features/catalogue with nothing, holds the button and says it is loading', requests.length === requestsBefore + 1 && requests[requests.length - 1].action === 'features/catalogue' && Object.keys( requests[requests.length - 1].payload ).length === 0
	&& loadButton().disabled === true && loadMsg().textContent === text('/_admin/features/msg/catalogue-loading') );
answer( 400, { error : 'Der Katalog konnte nicht geladen werden: the catalogue signature does not verify' } );
check( 'a refusal shows the backend\'s message as an error, frees the button and offers nothing', loadButton().disabled === false && hasClass( loadMsg(), 'nino-admin-error' ) && loadMsg().textContent === '(400) Der Katalog konnte nicht geladen werden: the catalogue signature does not verify' && byTag( catalogueMount, 'section' ).length === 1 );
fire( loadButton(), 'click' );
answer( 500, null );
check( 'a failed load falls back to its own error line', loadMsg().textContent === '(500) '+ text('/_admin/features/error/catalogue') );

fire( loadButton(), 'click' );
answer( 200, CATALOGUE );
check( 'a loaded catalogue is one card per offer, in the backend\'s order, below the block that names its stamp', byTag( catalogueMount, 'section' ).map( function( el ) { return el.dataset.offer } ).filter( Boolean ).join(',') === 'ancient,extra,helper,needy,sample'
	&& loadMsg().textContent === '' && hasClass( loadMsg(), 'nino-admin-error' ) === false && loadButton().disabled === false
	&& byTag( catalogueMount.children[0], 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/generated').replace( '%s', CATALOGUE.generated ) } ) );

const states = {};
findAll( catalogueMount, function( el ) { return typeof el.dataset.state === 'string' } ).forEach( function( el ) { states[el.dataset.state] = el } );
check( 'the state is a word from the text system: available, update, installed, incompatible', Object.keys( states ).sort().join(',') === 'available,current,incompatible,upgrade'
	&& states.available.textContent === text('/_admin/features/status/available') && states.upgrade.textContent === text('/_admin/features/status/update')
	&& states.current.textContent === text('/_admin/features/status/installed') && states.incompatible.textContent === text('/_admin/features/status/incompatible')
	&& hasClass( states.incompatible, 'nino-admin-error' ) && hasClass( states.upgrade, 'nino-admin-changed' ) && hasClass( states.available, 'nino-admin-eyebrow' ) );

check( 'an available offer has Install as the primary action, its version, description and requirements', byTag( offer( 'extra' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/install') && hasClass( byTag( offer( 'extra' ), 'button' )[0], 'nino-admin-btn-primary' )
	&& byTag( offer( 'extra' ), 'h3' )[0].textContent === 'Zusatz '
	&& byTag( offer( 'extra' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/version').replace( '%s', '1.0.0' )+ ' – '+ text('/_admin/features/label/released').replace( '%s', '2026-09-07' ) } )
	&& byTag( offer( 'extra' ), 'p' ).some( function( el ) { return el.textContent === 'Ein extra' } ) && byTag( offer( 'extra' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/requires').replace( '%s', 'helper' ) } ) );
check( 'an upgrade offers Update to the new version and names the one on disk', byTag( offer( 'helper' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/update').replace( '%s', '1.2.0' )
	&& byTag( offer( 'helper' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/version').replace( '%s', '1.2.0' )+ ' – '+ text('/_admin/features/label/installed').replace( '%s', '1.1.0' ) } ) );
check( 'a current one says installed and offers no button', byTag( offer( 'sample' ), 'button' ).length === 0 && byTag( offer( 'sample' ), 'a' ).length === 0 && byTag( offer( 'sample' ), 'span' )[0].dataset.state === 'current' );
check( 'an incompatible one is greyed, offers nothing, and says what it asks for: the Nino constraint, and the extensions where it names some', byTag( offer( 'needy' ), 'button' ).length === 0 && offer( 'needy' ).attributes['aria-disabled'] === 'true'
	&& byTag( offer( 'needy' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/nino').replace( '%s', '^1.0' ) } ) && byTag( offer( 'needy' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/extensions').replace( '%s', 'no_such_extension, other' ) } )
	&& byTag( offer( 'ancient' ), 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/label/nino').replace( '%s', '^0.9' ) } ) && byTag( offer( 'ancient' ), 'p' ).every( function( el ) { return el.textContent.indexOf( text('/_admin/features/label/extensions').split('%s')[0] ) !== 0 } )
	&& offer( 'extra' ).attributes['aria-disabled'] === undefined );
check( 'the installed list is untouched by a catalogue load', byTag( mount, 'section' ).map( function( el ) { return el.dataset.feature } ).join(',') === 'old,plain,sample,fresh' );

// --- installing from it

const install = byTag( offer( 'extra' ), 'button' )[0];
fire( install, 'click' );
check( 'Install posts features/install with key and version, holds the button and says so', requests[requests.length - 1].action === 'features/install' && requests[requests.length - 1].payload.key === 'extra' && requests[requests.length - 1].payload.version === '1.0.0'
	&& install.disabled === true && offerMsg( 'extra' ).textContent === text('/_admin/features/msg/installing') );
answer( 400, { error : 'the archive does not match what the catalogue promised' } );
check( 'a refusal shows the kernel\'s reason, frees the button and reads nothing again', install.disabled === false && hasClass( offerMsg( 'extra' ), 'nino-admin-error' ) && offerMsg( 'extra' ).textContent === '(400) the archive does not match what the catalogue promised' && requests[requests.length - 1].action === 'features/install' );
fire( install, 'click' );
answer( 500, null );
check( 'a failed install falls back to its own error line', offerMsg( 'extra' ).textContent === '(500) '+ text('/_admin/features/error/install') );

const EXTRA = { key : 'extra', name : 'Extra', description : 'Ein extra', version : '1.0.0', installed : null, active : false, update : false, requires : [ 'helper' ], problems : [], settings : [] };
fire( install, 'click' );
answer( 200, { feature : EXTRA, updated : false } );
check( 'success reads the list again first, and reloads no page', requests[requests.length - 1].action === 'features/list' && reloads === reloadsBefore );
answer( 200, { dir : LIST.dir, catalogue : LIST.catalogue, features : LIST.features.concat( [ EXTRA ] ) } );
check( 'then the catalogue - the list already shows the new feature, off, with Activate', requests[requests.length - 1].action === 'features/catalogue' && section( mount, 'extra' ) !== undefined
	&& byTag( section( mount, 'extra' ), 'button' ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/features/label/activate') );
const installed = JSON.parse( JSON.stringify( CATALOGUE ) );
installed.offers[1].state = 'current'; installed.offers[1].local = '1.0.0';
answer( 200, installed );
check( 'the refreshed offer says installed, keeps the install\'s answer on its line and offers no button', offer( 'extra' ).children.length > 0 && byTag( offer( 'extra' ), 'span' )[0].dataset.state === 'current' && byTag( offer( 'extra' ), 'button' ).length === 0
	&& offerMsg( 'extra' ).textContent === text('/_admin/features/msg/installed') && loadMsg().textContent === '' );

const upgrade = byTag( offer( 'helper' ), 'button' )[0];
fire( upgrade, 'click' );
check( 'Update posts features/install with the offered version and says it is updating', requests[requests.length - 1].action === 'features/install' && requests[requests.length - 1].payload.key === 'helper' && requests[requests.length - 1].payload.version === '1.2.0'
	&& offerMsg( 'helper' ).textContent === text('/_admin/features/msg/updating') );
answer( 500, null );
check( 'a failed update falls back to the update error line', offerMsg( 'helper' ).textContent === '(500) '+ text('/_admin/features/error/update') );
fire( upgrade, 'click' );
answer( 200, { feature : LIST.features[1], updated : true } );
answer( 200, LIST );
installed.offers[2].state = 'current'; installed.offers[2].local = '1.2.0';
answer( 200, installed );
check( 'an applied update reads both again and says updated', requests.slice( -2 ).map( function( r ) { return r.action } ).join(',') === 'features/list,features/catalogue'
	&& offerMsg( 'helper' ).textContent === text('/_admin/features/msg/updated') && byTag( offer( 'helper' ), 'button' ).length === 0 && reloads === reloadsBefore );

// --- a directory the web server cannot write, an empty catalogue, and the catalogue switched off

fire( loadButton(), 'click' );
const readonly = JSON.parse( JSON.stringify( CATALOGUE ) );
readonly.writable = false;
answer( 200, readonly );
check( 'without a writable directory the block says so, naming it', byTag( catalogueMount.children[0], 'p' ).some( function( el ) { return hasClass( el, 'nino-admin-error' ) && el.textContent === text('/_admin/features/hint/catalogue-readonly').replace( '%s', '/features' ) } ) );
check( 'and every offer that could be installed links its archive instead of a button', byTag( catalogueMount, 'button' ).length === 1
	&& byTag( offer( 'extra' ), 'a' ).length === 1 && byTag( offer( 'extra' ), 'a' )[0].href === CATALOGUE.offers[1].archive && byTag( offer( 'extra' ), 'a' )[0].textContent === text('/_admin/features/label/archive')
	&& byTag( offer( 'helper' ), 'a' )[0].href === CATALOGUE.offers[2].archive && byTag( offer( 'sample' ), 'a' ).length === 0 && byTag( offer( 'needy' ), 'a' ).length === 0 );

fire( loadButton(), 'click' );
answer( 200, { url : LIST.catalogue, generated : '', writable : true, offers : [] } );
check( 'a catalogue listing nothing is the shared empty state', findAll( catalogueMount, function( el ) { return hasClass( el, 'nino-admin-empty' ) } ).length === 1 && findAll( catalogueMount, function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/catalogue-empty')
	&& byTag( catalogueMount, 'section' ).length === 1 && byTag( catalogueMount.children[0], 'p' ).every( function( el ) { return el.textContent.indexOf( text('/_admin/features/label/generated').split('%s')[0] ) !== 0 } ) );

panel.showCurrent();
answer( 200, { dir : '/features', catalogue : '', features : LIST.features } );
check( 'switched off, the block says so and offers no button - and a fetch was never made on its own', catalogueMount.children[0].dataset.catalogue === 'off' && byTag( catalogueMount, 'button' ).length === 0 && byTag( catalogueMount, 'section' ).length === 1
	&& byTag( catalogueMount, 'p' ).some( function( el ) { return el.textContent === text('/_admin/features/hint/catalogue-off').replace( '%s', '/features' ) } )
	&& requests.filter( function( r ) { return r.action === 'features/catalogue' } ).length === 7 );

// --- nothing installed, and a failed load

panel.showCurrent();
answer( 200, { dir : '/features', catalogue : LIST.catalogue, features : [] } );
check( 'showCurrent reloads, and an empty directory is the shared empty state naming it - the catalogue block still there below', requests[requests.length - 1].action === 'features/list'
	&& findAll( mount, function( el ) { return hasClass( el, 'nino-admin-empty' ) } ).length === 1 && findAll( mount, function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/empty').replace( '%s', '/features' ) && byTag( mount, 'section' ).length === 0
	&& catalogueMount.children[0].dataset.catalogue === 'on' && byTag( catalogueMount, 'button' ).length === 1 );

panel.init();
answer( 500, null );
check( 'a failed load is reported through the workbench\'s words', mount.children.length === 1 && hasClass( mount.children[0], 'nino-admin-error' ) && mount.children[0].textContent === '(500) '+ text('/_admin/common/error/load') );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
