/**
 *	Nino									A compact filesystembased php framework
 *	admin-features-js-smoke.js	DOM-light checks for the Features panel's script
 *										(_admin/Nino/Modules/Features/assets/admin.js): that it
 *										attaches under its nav uri, which actions it posts and
 *										with what payload, that every word it renders is a fill
 *										both interface languages define, that a secret's input
 *										is always empty, and that one Save collects every
 *										setting of its feature by data-key. The real
 *										Nino.adminUi renders the shared controls, over a
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

const LIST = {
	dir : '/features',
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
check( 'the panel is a system entry with the nav uri the script speaks, one mount point and four actions',
	admin.includes( "return [ 'features', '/_admin/nav/features', 15, 'system' ];" ) && admin.includes( "return [ 'features-list' ];" ) && script.includes( "'features-list'" )
	&& [ 'features/list', 'features/activate', 'features/deactivate', 'features/settings' ].every( function( action ) { return admin.includes( "'"+ action+ "'" ) } ) );
check( 'every action method guards itself with the panel\'s permission', ( admin.match( /guardPerm\( \$appData, \$request, self::MANAGE_PERM \)/g ) || [] ).length === 4 );
check( 'the script posts those four actions and no other',
	script.includes( "action : 'features/'+ endpoint" ) && script.includes( "_apiCall( 'list'" ) && script.includes( "_apiCall( 'settings'" )
	&& script.includes( "? 'deactivate' : 'activate'" ) && ( script.match( /_apiCall\( /g ) || [] ).length === 3 );

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
check( 'the four status words and the reload hint are among them', [ 'active', 'inactive', 'update', 'incompatible' ].every( function( s ) { return keys.indexOf( '/_admin/features/status/'+ s ) !== -1 } ) && keys.indexOf( '/_admin/features/msg/reload' ) !== -1 );
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

// --- nothing installed, and a failed load

panel.showCurrent();
answer( 200, { dir : '/features', features : [] } );
check( 'showCurrent reloads, and an empty directory is the shared empty state naming it', requests[requests.length - 1].action === 'features/list'
	&& findAll( mount, function( el ) { return hasClass( el, 'nino-admin-empty' ) } ).length === 1 && findAll( mount, function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/features/hint/empty').replace( '%s', '/features' ) && byTag( mount, 'section' ).length === 0 );

panel.init();
answer( 500, null );
check( 'a failed load is reported through the workbench\'s words', mount.children.length === 1 && hasClass( mount.children[0], 'nino-admin-error' ) && mount.children[0].textContent === '(500) '+ text('/_admin/common/error/load') );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
