/**
 *	Nino									A compact filesystembased php framework
 *	admin-config-js-smoke.js	DOM-light checks for the Config panel's script
 *											(_admin/Nino/Modules/Config/assets/admin.js): the
 *											mode drawn as a select from what the server sends, the
 *											value posted back as the string it is, and the shell
 *											told which mode the server now holds - the chip in the
 *											rail and the questions the workbench asks follow it.
 *
 *	Usage: node tests/admin-config-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, fills, load, element, findAll } = require( './harness.js' );


// --- the words -------------------------------------------------------------

const words = Object.assign( {}, fills('_admin/text/en_US.php'), fills('_admin/Nino/Modules/Config/text/en_US.php') );


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'config-form';
const requests = [];

const Nino = {
	admin : {},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : function( key ) { return words[key] || '' } },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		getElementById : function( id ) { return mount.id === id ? mount : ( findAll( mount, function( el ) { return el.id === id } )[0] ?? null ) },
		// The panel asks for every field of its form by the key it carries
		querySelectorAll : function( selector ) { return selector === '#config-form [data-key]' ? findAll( mount, function( el ) { return el.attributes['data-key'] !== undefined } ) : [] },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );

// The switches and numbers are the design system's business, and have their own
// tests: here they are there, and carry their key and their value
Nino.adminUi.switchField = function( options ) {
	const input = element('input');
	input.type = 'checkbox';
	input.setAttribute( 'data-key', options.key );
	input.checked = options.checked;
	return input;
};
Nino.adminUi.numberField = function( options ) {
	const input = element('input');
	input.type = 'number';
	input.setAttribute( 'data-key', options.key );
	input.value = String( options.value );
	return input;
};

load( '_admin/Nino/Modules/Config/assets/admin.js', context );

const panel = Nino.admin.config;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

const schema = {
	groups : {
		mode : [ '/_admin/config/group/mode', '/_admin/config/intro/mode' ],
		cache : [ '/_admin/config/group/cache', '/_admin/config/intro/cache' ],
	},
	fields : [
		{ key : '/nino/mode', type : 'select', group : 'mode', label : '/_admin/config/label/mode', hint : '/_admin/config/hint/mode', value : 'production',
			options : [ { value : 'production', label : '/_admin/config/option/production' }, { value : 'development', label : '/_admin/config/option/development' } ] },
		{ key : '/nino/cache/status', type : 'bool', group : 'cache', label : '/_admin/config/label/cachestatus', hint : '/_admin/config/hint/cachestatus', value : true },
	],
};

const selectOf = () => findAll( mount, function( el ) { return el.tagName === 'SELECT' } )[0] ?? null;

console.log('Admin Config');

panel.init();
check( 'the panel asks for the schema and the values', requests.length === 1 && requests[0].action === 'config/list' );
answer( 200, schema );

const select = selectOf();
check( 'a field of the type select is drawn as a select that carries its key, and holds the value the server sent', select !== null && select.attributes['data-key'] === '/nino/mode' && select.value === 'production' );
check( '...with the options in the order the server sent them, each worded in the interface language from its fill key', select !== null
	&& select.children.map( function( option ) { return option.value+ '='+ option.textContent } ).join() === 'production='+ words['/_admin/config/option/production']+ ',development='+ words['/_admin/config/option/development']
	&& words['/_admin/config/option/production'] !== undefined && words['/_admin/config/option/production'] !== words['/_admin/config/option/development'] );
check( '...and the other fields are drawn beside it as they were', findAll( mount, function( el ) { return el.attributes['data-key'] === '/nino/cache/status' } ).length === 1 );

// --- the save posts the string, and the shell is told what the server holds ---

const told = [];
Nino.admin.mode = { set : function( value ) { told.push( value ) } };
select.value = 'development';
let outcome = null;
requests.length = 0;
panel._save( function( ok ) { outcome = ok } );
check( 'the save posts the mode as the string that was picked, with the other fields', requests.length === 1 && requests[0].action === 'config/save'
	&& requests[0].payload.fields['/nino/mode'] === 'development' && requests[0].payload.fields['/nino/cache/status'] === true );
answer( 200, { saved : [ '/nino/mode', '/nino/cache/status' ], mode : 'development' } );
check( 'a saved form tells the shell the mode the server answers with - not the one that was posted - once', told.join() === 'development' && outcome === true );
check( '...and draws the form again from the server: the values the switch wrote are in it', requests.length === 2 && requests[1].action === 'config/list' );

// What the server says wins over what was picked
requests.length = 0;
told.length = 0;
panel._save();
answer( 200, { saved : [], mode : 'production' } );
check( 'the mode the server answers is the one the shell is told, whatever was picked', told.join() === 'production' );

// A refusal tells nobody anything
requests.length = 0;
told.length = 0;
outcome = null;
panel._save( function( ok ) { outcome = ok } );
answer( 400, { error : 'x', code : 'invalid_value', field : '/nino/mode' } );
check( 'a refused save does not tell the shell, and ends with done( false )', told.length === 0 && outcome === false );

// A shell that is older than the mode
delete Nino.admin.mode;
requests.length = 0;
outcome = null;
panel._save( function( ok ) { outcome = ok } );
answer( 200, { saved : [], mode : 'development' } );
check( 'a shell that has no mode is told nothing and the save ends all the same', outcome === true );

// --- the words ---------------------------------------------------------------

const de = Object.assign( {}, fills('_admin/Nino/Modules/Config/text/de_DE.php') );
const keys = [ '/_admin/config/group/mode', '/_admin/config/intro/mode', '/_admin/config/label/mode', '/_admin/config/hint/mode', '/_admin/config/option/production', '/_admin/config/option/development', '/_admin/config/manual/mode' ];
check( 'both interface languages have the words of the mode, none empty', keys.every( function( key ) { return ( words[key] ?? '' ) !== '' && ( de[key] ?? '' ) !== '' } ) );

done();
