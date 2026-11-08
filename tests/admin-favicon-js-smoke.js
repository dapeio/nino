/**
 *	Nino										A compact filesystembased php framework
 *	admin-favicon-js-smoke.js	DOM-light checks for the Favicon tab's script
 *													(_admin/Nino/Modules/Images/assets/favicon.js): the set drawn
 *													as the server answers it, Undo and Reset only where they make
 *													sense, the files and colours that go to favicon/generate, a
 *													picture below the least size kept back with the reason, a
 *													refused colour marked at its field, and Reset asked about.
 *													The words are the module's real English ones.
 *
 *	Usage: node tests/admin-favicon-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, element, findAll, fire, documentOf } = require( './harness.js' );

const moduleEn 		= fills('_admin/Nino/Modules/Images/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

const text = function( key ) { return moduleEn[key] ?? workbenchEn[key] ?? '' };

const requests = [];
const asked = [];
let answerConfirm = true;
let bitmap = null;

// The shell's registry of unsaved input (see Nino.admin.dirty), as far as the tab uses it
const snapshots = [];
let watched = null;

const mount = element('div');
mount.id = 'favicon-form';

const Nino = {
	admin : { touch : function() {}, stamp : function() { return 0 }, dirty : {
		snapshot : function( name ) { snapshots.push( name ) },
		watchForm : function( name, getter, save ) { watched = { name : name, getter : getter, save : save } },
	} },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), file : data.file, svg : data.svg, callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = { console : console, document : documentOf( mount ), Nino : Nino };
sandbox.window = {
	Nino : Nino,
	location : { hash : '' },
	confirm : function( question ) { asked.push( question ); return answerConfirm },
	createImageBitmap : function() { return bitmap === null ? Promise.reject( new Error('no') ) : Promise.resolve( bitmap ) },
};

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Images/assets/favicon.js', context );

const panel = Nino.admin.favicon;
check( 'the form is registered with the shell as one that holds unsaved input, saved by Generate', watched !== null && watched.name === 'favicon' && typeof watched.save === 'function' );
const byId = function( id ) { return findAll( mount, function( el ) { return el.id === id } )[0] };

const answer = function( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
};

const present = function( name, width, extra ) {
	return Object.assign( { name : name, exists : true, url : '/public/favicon/'+ name+ '?v=5', bytes : 100, time : 1700000000, width : width, height : width }, extra || {} );
};

const setOf = function( overrides ) {
	return Object.assign( {
		files : [ present( 'favicon-16x16.png', 16 ), present( 'favicon-32x32.png', 32 ), present( 'apple-touch-icon.png', 180 ), present( 'favicon-192x192.png', 192 ),
			present( 'favicon-512x512.png', 512 ), present( 'favicon.ico', 32 ), { name : 'favicon.svg', exists : false } ],
		stamp : 1700000000, min : 512, undo : null, base : true, themeColor : '#336699', backgroundColor : '',
	}, overrides || {} );
};

const flush = function() { return new Promise( function( resolve ) { setTimeout( resolve, 0 ) } ) };

( async function() {

console.log('Admin Favicon');

// --- the set -----------------------------------------------------------------

panel.init();
check( 'the tab asks the server for the set when it is first shown', requests.length === 1 && requests[0].action === 'favicon/status' );
answer( 200, setOf() );

const items = findAll( mount, function( el ) { return el.className.split(' ').indexOf('admin-favicon-item') !== -1 } );
check( 'every file of the answer is a row of the preview, with the size it is shown as - and .ico and .svg for the two containers', items.length === 7
	&& findAll( items[0], function( el ) { return el.className === 'admin-favicon-caption' } )[0].textContent === '16 × 16'
	&& findAll( items[5], function( el ) { return el.className === 'admin-favicon-caption' } )[0].textContent === '.ico'
	&& findAll( items[6], function( el ) { return el.className === 'admin-favicon-caption' } )[0].textContent === '.svg' );
check( '...a file that is not there is said to be missing, not drawn', findAll( items[6], function( el ) { return el.tagName === 'IMG' || el.tagName === 'img' } ).length === 0
	&& findAll( items[6], function( el ) { return el.className === 'admin-favicon-missing' } )[0].textContent === 'missing' );
check( '...and a file that is there is a picture at the address the server gave, without a text of its own', findAll( items[0], function( el ) { return el.src === '/public/favicon/favicon-16x16.png?v=5' && el.alt === '' } ).length === 1 );
check( 'Undo is not offered while no previous set is kept, Reset is while the base unit has one', byId('favicon-undo').hidden === true && byId('favicon-reset').hidden === false );
check( 'the colours the manifest holds stand in the fields as what an empty field leaves', byId('favicon-theme').placeholder === '#336699' && byId('favicon-background').placeholder === '#ffffff' && byId('favicon-theme').value === '' );

const rows = panel._rows( setOf( { files : [ { name : 'favicon.ico', exists : false } ] } ) );
check( 'the rows are a model of their own: a missing file has no address and no size', rows.length === 1 && rows[0].exists === false && rows[0].url === '' && rows[0].caption === '.ico' );

panel.showCurrent();
check( 'showing the tab again asks for the set again, and does not draw the form again: what was typed stays', requests.length === 2 );
byId('favicon-background').value = '#abcdef';
answer( 200, setOf( { undo : 1700000100 } ) );
check( '...Undo is offered once a copy is kept, and the typed colour is still there', byId('favicon-undo').hidden === false && byId('favicon-background').value === '#abcdef' );
byId('favicon-background').value = '';


// --- generate ------------------------------------------------------------------

// A file input of a page has an empty list of files; the stand-in has none until a test gives it
byId('favicon-file').files = [];
byId('favicon-svg').files = [];
requests.length = 0;
const submit = function() { fire( findAll( mount, function( el ) { return el.id === 'favicon-set' } )[0], 'submit', { preventDefault : function() {} } ) };

submit();
check( 'Generate without a picture sends nothing and says so', requests.length === 0 && byId('favicon-msg').textContent === 'Choose a source picture first.' );

byId('favicon-file').files = [ { size : 1000, name : 'logo.png' } ];
bitmap = { width : 300, height : 900 };
submit();
await flush();
check( 'a picture below the least size is kept back with the size it has, nothing is sent', requests.length === 0 && byId('favicon-msg').textContent.indexOf( '512 pixels' ) !== -1 && byId('favicon-msg').textContent.indexOf( '300 × 900' ) !== -1 );

bitmap = { width : 800, height : 800 };
byId('favicon-background').value = '#1a2b3c';
byId('favicon-theme').value = '';
submit();
await flush();
check( 'a good picture goes to favicon/generate with the two colours as typed, and no svg', requests.length === 1 && requests[0].action === 'favicon/generate' && requests[0].payload.background === '#1a2b3c' && requests[0].payload.themeColor === '' && requests[0].file.name === 'logo.png' && requests[0].svg === undefined );
check( '...while it is sent the button waits and the line says so', byId('favicon-generate').disabled === true && byId('favicon-msg').dataset.state === 'saving' );

answer( 200, setOf( { undo : 1700000200, files : setOf().files.slice( 0, 6 ).concat( [ present( 'favicon.svg', 0, { width : 0, height : 0 } ) ] ) } ) );
check( 'the answer is the set as it stands now: drawn, the picture field emptied, the line done', byId('favicon-generate').disabled === false && byId('favicon-file').value === '' && byId('favicon-msg').dataset.state === 'saved'
	&& findAll( mount, function( el ) { return el.className === 'admin-favicon-missing' } ).length === 0 );

requests.length = 0;
byId('favicon-file').files = [ { size : 1000, name : 'logo.png' } ];
byId('favicon-svg').files = [ { size : 200, name : 'logo.svg' } ];
submit();
await flush();
check( 'a svg chosen goes with the picture', requests.length === 1 && requests[0].svg !== undefined && requests[0].svg.name === 'logo.svg' );

answer( 400, { error : 'a colour is a hex value', code : 'favicon_colour', field : 'background' } );
check( 'a refused colour is said in words and marked at its field, and the button is free again', byId('favicon-msg').dataset.state === 'error' && byId('favicon-msg').textContent === 'A colour is a hex value like #1a2b3c.'
	&& byId('favicon-background').getAttribute('aria-invalid') === 'true' && byId('favicon-generate').disabled === false );

requests.length = 0;
submit();
await flush();
answer( 400, { error : 'x', code : 'favicon_svg' } );
check( 'a svg the server could not keep is said in words', byId('favicon-msg').textContent === 'The SVG file is not a plain vector picture that can be kept.' );


// --- unsaved input ---------------------------------------------------------

snapshots.length = 0;
requests.length = 0;
byId('favicon-file').files = [];
byId('favicon-svg').files = [];
let ended = [];
watched.save( function( ok ) { ended.push( ok ) } );
check( 'the shell\'s Save with no picture chosen ends with false, and says why', ended.join() === 'false' && requests.length === 0 && byId('favicon-msg').textContent === 'Choose a source picture first.' );

byId('favicon-file').files = [ { size : 1000, name : 'logo.png' } ];
bitmap = { width : 300, height : 300 };
watched.save( function( ok ) { ended.push( ok ) } );
await flush();
check( '...with a picture that is too small, false too', ended.join() === 'false,false' && requests.length === 0 );

bitmap = { width : 900, height : 900 };
watched.save( function( ok ) { ended.push( ok ) } );
await flush();
answer( 500, { error : 'could not write the icons' } );
check( '...with a set the server could not write, false', ended.join() === 'false,false,false' && requests.length === 1 );

watched.save( function( ok ) { ended.push( ok ) } );
await flush();
answer( 200, setOf() );
check( '...and true once the set was made, which is also what the registry takes for unsaved input no more', ended.join() === 'false,false,false,true' && snapshots.join() === 'favicon' );
requests.length = 0;


// --- undo and reset ------------------------------------------------------------

requests.length = 0;
fire( byId('favicon-undo'), 'click' );
check( 'Undo asks the server to put the previous set back', requests.length === 1 && requests[0].action === 'favicon/undo' );
answer( 200, setOf() );
check( '...and the set it answers is drawn, Undo gone with the copy', byId('favicon-undo').hidden === true && byId('favicon-msg').dataset.state === 'saved' );

requests.length = 0;
fire( byId('favicon-undo'), 'click' );
answer( 409, { error : 'there is no previous set', code : 'favicon_no_previous' } );
check( 'an Undo with nothing to put back says so', byId('favicon-msg').textContent === 'There is no previous set.' );

requests.length = 0;
answerConfirm = false;
fire( byId('favicon-reset'), 'click' );
check( 'Reset asks first, and a cancelled question sends nothing', asked.length === 1 && requests.length === 0 );

answerConfirm = true;
fire( byId('favicon-reset'), 'click' );
check( 'a confirmed one asks the server for the base set', requests.length === 1 && requests[0].action === 'favicon/reset' );
answer( 200, setOf() );
check( '...and draws what it answers', byId('favicon-msg').dataset.state === 'saved' );

requests.length = 0;
fire( byId('favicon-reset'), 'click' );
answer( 404, { error : 'x', code : 'favicon_no_base' } );
check( 'a base unit without a set is said in words', byId('favicon-msg').textContent === 'The base unit has no set to put back.' );

done();

} )();
