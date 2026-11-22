/**
 *	Nino										A compact filesystembased php framework
 *	admin-favicon-js-smoke.js	DOM-light checks for the Favicon tab's script
 *													(_admin/Nino/Modules/Images/assets/favicon.js): the set drawn
 *													as the server answers it, the form built of the blocks of the
 *													workbench - the file block, the colour block, one bar with Generate
 *													as its one main button and Undo and Reset beside it - Undo and Reset
 *													asked about and said by a toast, and what is wrong said at the field
 *													of the picture, the vector icon or the colour it is about. Nothing
 *													here compares a sentence: the checks read the build, and that the
 *													keys the script asks for have words in both languages.
 *
 *	Usage: node tests/admin-favicon-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, source, fills, load, element, findAll, fire, documentOf } = require( './harness.js' );

const moduleEn	= fills('_admin/Nino/Modules/Images/text/en_US.php');
const moduleDe	= fills('_admin/Nino/Modules/Images/text/de_DE.php');
const workbench	= fills('_admin/text/en_US.php');
const workbenchDe = fills('_admin/text/de_DE.php');

const text = function( key ) { return moduleEn[key] ?? workbench[key] ?? '' };

const requests = [];
const dialogs = [];
let watched = null;
let bitmap = null;

const mount = element('div');
mount.id = 'favicon-form';
const wrap = element('div');
wrap.dataset.uploadBytes = '2097152';
wrap.dataset.uploadPixels = '20000000';

const Nino = {
	admin : { touch : function() {}, stamp : function() { return 0 }, dirty : {
		register : function( name, entry ) { watched = Object.assign( { name : name }, entry ) },
		refresh : function() {},
	} },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), file : data.file, svg : data.svg, callback : callback } );
	} },
	content : { getText : text },
};

const document = documentOf( mount );
const byId = document.getElementById;
document.getElementById = function( id ) { return id === 'admin-page-wrap' ? wrap : byId( id ) };
document.body = element('body');
document.documentElement = { lang : 'de' };
const created = document.createElement;
document.createElement = function( tag ) {
	const el = created( tag );
	if( tag === 'dialog' ) {
		el.open = false;
		el.showModal = function() { el.open = true };
		el.close = function() { el.open = false; fire( el, 'close' ) };
		dialogs.push( el );
	}
	return el;
};

const window = {
	Nino : Nino,
	location : { hash : '' },
	setTimeout : function() { return 1 },
	clearTimeout : function() {},
	confirm : function() { throw new Error('a question is asked in a dialog of the design system, not with the browser\'s confirm') },
	createImageBitmap : function() { return bitmap === null ? Promise.reject( new Error('no') ) : Promise.resolve( bitmap ) },
};

const context = vm.createContext( { console : console, document : document, Nino : Nino, window : window } );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Images/assets/favicon.js', context );

const panel = Nino.admin.favicon;
const find = function( predicate ) { return findAll( mount, predicate ) };
const byId2 = function( id ) { return find( el => el.id === id )[0] };
const has = function( el, name ) { return el.classList.contains( name ) };
const byClass = function( name ) { return find( el => has( el, name ) ) };
const errors = function() { return byClass('nino-admin-field-error') };
const toasts = function() { return findAll( document.body, el => has( el, 'nino-admin-toast-text' ) ).map( el => el.textContent ) };
const line = function() { return byId2('favicon-msg') };
const answer = function( status, body ) { requests[requests.length - 1].callback( { status : status, responseJSON : body } ) };
const flush = function() { return new Promise( function( resolve ) { setTimeout( resolve, 0 ) } ) };

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

( async function() {

console.log('Admin Favicon');

check( 'the form is registered with the shell as one that holds unsaved input, with all three of isDirty, save and discard - Generate is its Save', watched !== null && watched.name === 'favicon' && typeof watched.save === 'function' && typeof watched.isDirty === 'function' && typeof watched.discard === 'function' );

// --- the set -----------------------------------------------------------------

panel.init();
check( 'the tab asks the server for the set when it is first shown', requests.length === 1 && requests[0].action === 'favicon/status' );
answer( 200, setOf() );

const items = byClass('admin-favicon-item');
const captionOf = function( item ) { return findAll( item, el => has( el, 'admin-favicon-caption' ) )[0].textContent };
check( 'every file of the answer is a row of the preview, with the size it is shown as - and .ico and .svg for the two containers', items.length === 7 && captionOf( items[0] ) === '16 × 16' && captionOf( items[5] ) === '.ico' && captionOf( items[6] ) === '.svg' );
check( '...a file that is not there is said to be missing and not drawn; one that is there is a picture at the address the server gave, with no text of its own', findAll( items[6], el => el.tagName === 'IMG' ).length === 0
	&& findAll( items[6], el => has( el, 'admin-favicon-missing' ) ).length === 1 && findAll( items[0], el => el.src === '/public/favicon/favicon-16x16.png?v=5' && el.alt === '' ).length === 1 );

const rows = panel._rows( setOf( { files : [ { name : 'favicon.ico', exists : false } ] } ) );
check( 'the rows are a model of their own: a missing file has no address and no size', rows.length === 1 && rows[0].exists === false && rows[0].url === '' && rows[0].caption === '.ico' );

const date = byId2('favicon-date');
check( 'the date of the set is written by the one way the workbench writes a date: no seconds, the form of the interface language', date.textContent === Nino.adminUi.dateText( new Date( 1700000000 * 1000 ) ) && date.textContent !== '' && /\d{2}:\d{2}$/.test( date.textContent ) );
check( '...and "there is no set" where no file has a time', ( function() { panel._data = setOf( { files : [ { name : 'favicon.ico', exists : false } ] } ); panel._paint(); const none = date.textContent !== ''; panel._data = setOf(); panel._paint(); return none && date.textContent !== text('/_admin/favicon/msg/none') } )() );


// --- the build ------------------------------------------------------------------

const form = byId2('favicon-set');
const choose = function( which, file ) { const input = find( el => el.type === 'file' )[which]; input.files = file === null ? [] : [ file ]; fire( input, 'change' ) };
const type = function( id, value ) { const input = byId2( id ); input.value = value; fire( input, 'input' ) };
const submit = function() { fire( form, 'submit' ) };
check( 'the form is novalidate: what is wrong is said by the script, at the field, and never by the browser', form.noValidate === true );
check( 'the two pictures are file blocks, with the extensions the server takes - and no input of type file stands anywhere else', byClass('nino-admin-filefield').length === 2 && find( el => el.type === 'file' ).length === 2
	&& find( el => el.type === 'file' ).every( input => input.hidden === true ) && find( el => el.type === 'file' ).map( input => input.accept ).join() === '.png,.jpg,.jpeg,.webp,.svg' );
check( '...the source picture is required, with its star, and its limits - the least size and the upload\'s - stay in view as its note', find( el => has( el, 'nino-admin-required' ) ).length === 1
	&& findAll( byClass('nino-admin-filefield')[0], el => has( el, 'nino-admin-field-note' ) ).length === 1 );
check( 'the two colours are colour blocks - the hex code with the picker beside it - and no hex text field of its own', byClass('nino-admin-color').length === 2 && find( el => el.type === 'color' ).length === 2
	&& find( el => has( el, 'nino-admin-color-hex' ) ).length === 2 && find( el => el.type === 'text' ).length === 2 );
check( '...a colour that is empty leaves what the manifest holds, which is written in the field as its example', byId2('favicon-theme').placeholder === '#336699' && byId2('favicon-background').placeholder === '#ffffff' && byId2('favicon-theme').value === '' );
const described = function( id ) { return String( byId2( id ).getAttribute('aria-describedby') ?? '' ).split(/\s+/) };
const helpOf = function( id ) { return find( el => has( el, 'nino-admin-help-text' ) ).find( el => described( id ).indexOf( el.id ) !== -1 ) };
type( 'favicon-theme', '#12' );
check( 'the help of a colour field stays what the field is described by whatever is typed into it: the block writes its error and its contrast there, and the sentence stays behind them', helpOf('favicon-theme') !== undefined && described('favicon-theme').length > 1 );
type( 'favicon-theme', '#123456' );
fire( find( el => el.type === 'color' )[1], 'input' );
check( '...also when the colour is right, and when the picker is moved', helpOf('favicon-theme') !== undefined );
type( 'favicon-theme', '' );
check( 'every field has its help behind a (?) and no line of hint under it: the sentences of the sections are behind theirs', find( el => has( el, 'nino-admin-help-button' ) ).length === 6 && find( el => el.tagName === 'SMALL' && has( el, 'nino-admin-hint' ) && !has( el, 'nino-admin-field-note' ) ).length === 0 );

const bar = byClass('nino-admin-actionbar')[0];
const barButtons = findAll( bar, el => el.tagName === 'BUTTON' );
check( 'one bar, with one main button - Generate, the submit - at the far right, and Undo and Reset beside it as buttons of the second kind', byClass('nino-admin-actionbar').length === 1
	&& barButtons.filter( b => has( b, 'nino-admin-btn-primary' ) ).length === 1 && barButtons[barButtons.length - 1] === byId2('favicon-generate') && byId2('favicon-generate').type === 'submit'
	&& has( byId2('favicon-undo'), 'nino-admin-btn-secondary' ) && has( byId2('favicon-reset'), 'nino-admin-btn-secondary' ) && barButtons.every( b => !has( b, 'nino-admin-btn-danger' ) ) );
check( 'Undo is not offered while no previous set is kept, Reset is while the base unit has one', byId2('favicon-undo').hidden === true && byId2('favicon-reset').hidden === false );

panel.showCurrent();
check( 'showing the tab again asks for the set again, and does not draw the form again: what was typed stays', requests.length === 2 );
byId2('favicon-background').value = '#abcdef';
answer( 200, setOf( { undo : 1700000100 } ) );
check( '...Undo is offered once a copy is kept, and the typed colour is still there', byId2('favicon-undo').hidden === false && byId2('favicon-background').value === '#abcdef' && find( el => el.tagName === 'FORM' )[0] === form );
byId2('favicon-background').value = '';


// --- Generate -------------------------------------------------------------------

requests.length = 0;

submit();
check( 'Generate without a picture sends nothing and says so at the field of the picture - and in the line of the bar - with the focus on it', requests.length === 0 && errors().length === 1
	&& line().dataset.state === 'error' && line().attributes.role === 'alert' && find( el => el.focused === true )[0] === find( el => has( el, 'nino-admin-filefield-body' ) )[0].children.find( c => c.tagName === 'BUTTON' ) );

choose( 0, { name : 'logo.png', size : 1000, type : 'image/png' } );
check( 'a picture that is chosen takes the sentence away and is not sent until Generate', errors().length === 0 && requests.length === 0 );

bitmap = { width : 300, height : 900 };
submit();
await flush();
check( 'a picture below the least size is kept back, with the size it has, at its field - and nothing is sent', requests.length === 0 && errors().length === 1 && errors()[0].textContent.indexOf( '300 × 900' ) !== -1 );

type( 'favicon-theme', '#12' );
bitmap = { width : 800, height : 800 };
submit();
await flush();
check( 'a colour typed that is no colour is not sent as an empty one: the set is not made, the colour block says it, the focus is on it', requests.length === 0 && byId2('favicon-theme').getAttribute('aria-invalid') === 'true' && find( el => el.focused === true )[0] === byId2('favicon-theme') );
type( 'favicon-theme', '' );

type( 'favicon-background', '#1a2b3c' );
submit();
await flush();
check( 'a good picture goes to favicon/generate with the two colours as typed, and no svg', requests.length === 1 && requests[0].action === 'favicon/generate' && requests[0].payload.background === '#1a2b3c'
	&& requests[0].payload.themeColor === '' && requests[0].file.name === 'logo.png' && requests[0].svg === undefined );
check( '...while it is sent the button waits and the line says so', byId2('favicon-generate').disabled === true && line().dataset.state === 'saving' );

answer( 200, setOf( { undo : 1700000200, files : setOf().files.slice( 0, 6 ).concat( [ present( 'favicon.svg', 0, { width : 0, height : 0 } ) ] ) } ) );
check( 'the answer is the set as it stands now: drawn, the pictures emptied, the line empty, and a toast says it', byId2('favicon-generate').disabled === false && find( el => has( el, 'nino-admin-filefield-name' ) ).every( el => el.dataset.state === 'none' )
	&& line().dataset.state === 'saved' && line().textContent === '' && toasts().length === 1 && byClass('admin-favicon-missing').length === 0 );
check( '...and what the form holds now is what the set was made with: the colours typed stay, and are no unsaved input any more', watched.isDirty() === false && byId2('favicon-background').value === '#1a2b3c' && find( el => el.focused === true )[0] === byId2('favicon-generate') );
type( 'favicon-background', '' );

requests.length = 0;
choose( 0, { name : 'logo.png', size : 1000, type : 'image/png' } );
choose( 1, { name : 'logo.svg', size : 200, type : 'image/svg+xml' } );
submit();
await flush();
check( 'a svg chosen goes with the picture', requests.length === 1 && requests[0].svg !== undefined && requests[0].svg.name === 'logo.svg' );

answer( 400, { error : 'a colour is a hex value', code : 'favicon_colour', field : 'background' } );
check( 'a refused colour is said at the colour field it names - the code marked invalid - and in the line, and the button is free again', errors().length === 1 && byId2('favicon-background').getAttribute('aria-invalid') === 'true'
	&& byId2('favicon-theme').getAttribute('aria-invalid') === null && line().dataset.state === 'error' && byId2('favicon-generate').disabled === false && find( el => el.focused === true )[0] === byId2('favicon-background') );

requests.length = 0;
submit();
await flush();
answer( 400, { error : 'x', code : 'favicon_svg' } );
check( 'a svg the server could not keep is said at the svg field, the one that is about it', errors().length === 1 && findAll( byClass('nino-admin-filefield')[1], el => has( el, 'nino-admin-field-error' ) ).length === 1 );
submit();
await flush();
answer( 400, { error : 'x', code : 'favicon_type' } );
check( 'a picture of a type the server does not take is said at the field of the picture', errors().length === 1 && findAll( byClass('nino-admin-filefield')[0], el => has( el, 'nino-admin-field-error' ) ).length === 1 );
submit();
await flush();
answer( 500, { error : 'could not write the icons' } );
check( 'a failure that is about no field is said in the line alone', errors().length === 0 && line().dataset.state === 'error' );

choose( 0, { name : 'notes.txt', size : 100, type : 'text/plain' } );
const nameOf = function( which ) { return find( el => has( el, 'nino-admin-filefield-name' ) )[which].textContent };
const before = nameOf( 0 );
check( 'a type the field does not take is refused at once, at the field, and the file is not taken: what was chosen before stays', errors().length === 1 && nameOf( 0 ) === before && before === 'logo.png' );
choose( 0, { name : 'huge.png', size : 3 * 1048576, type : 'image/png' } );
check( 'a file larger than the limit of the upload is refused at the field too, and not taken', errors().length === 1 && nameOf( 0 ) === before );
choose( 1, { name : 'logo.png', size : 100, type : 'image/png' } );
check( '...and the vector field takes nothing that is not a svg', findAll( byClass('nino-admin-filefield')[1], el => has( el, 'nino-admin-field-error' ) ).length === 1 && nameOf( 1 ) === 'logo.svg' );


// --- unsaved input -------------------------------------------------------------------

requests.length = 0;
panel._parts.file.clear();
panel._parts.svg.clear();
let ended = [];
watched.save( function( ok ) { ended.push( ok ) } );
check( 'the shell\'s Save with no picture chosen ends with false, and says why', ended.join() === 'false' && requests.length === 0 && errors().length >= 1 );

choose( 0, { name : 'logo.png', size : 1000, type : 'image/png' } );
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
check( '...and true once the set was made, which is also what the registry takes for unsaved input no more', ended.join() === 'false,false,false,true' && watched.isDirty() === false );


// --- what is unsaved, and what is ordered once ----------------------------------------------

check( 'a form as it stands after a set was made holds nothing unsaved', watched.isDirty() === false );
choose( 0, { name : 'logo.png', size : 1000, type : 'image/png' } );
check( 'a picture that is chosen and not made into a set is unsaved input, as the guide says of a file the form keeps', watched.isDirty() === true );
watched.discard();
check( '...Discard takes it out of the field again', watched.isDirty() === false && find( el => has( el, 'nino-admin-filefield-name' ) )[0].dataset.state === 'none' );
type( 'favicon-theme', '#445566' );
check( 'a colour that is typed is unsaved input as well, and Discard puts it back to the colour the set was made with', watched.isDirty() === true && ( watched.discard(), byId2('favicon-theme').value === '' && watched.isDirty() === false ) );

requests.length = 0;
choose( 0, { name : 'logo.png', size : 1000, type : 'image/png' } );
bitmap = { width : 800, height : 800 };
submit();
submit();
await flush();
check( 'a second order while the first is checked or sent is no order: one request, and the button is locked from the first press', requests.length === 1 && byId2('favicon-generate').disabled === true && form.attributes['aria-busy'] === 'true' );
answer( 500, { error : 'no' } );
check( '...it is free again when the first is answered, whatever it said, and the lock is gone', byId2('favicon-generate').disabled === false && form.attributes['aria-busy'] === undefined );
watched.discard();
requests.length = 0;
submit();
check( 'the lock is let go of at every refusal as well: a form that is refused can be sent again', byId2('favicon-generate').disabled === false );

// --- Undo and Reset ----------------------------------------------------------------------

const ask = function( yes ) {
	const dialog = dialogs.pop();
	const buttons = findAll( dialog, el => el.tagName === 'BUTTON' );
	fire( buttons[yes === true ? 1 : 0], 'click' );
	return dialog;
};

requests.length = 0;
panel._data = setOf( { undo : 1700000100 } );
panel._paint();
fire( byId2('favicon-undo'), 'click' );
check( 'Undo asks first - the set that stands is used up by it - in a dialog of the design system, as a question about something that is overwritten', dialogs.length === 1 && requests.length === 0
	&& findAll( dialogs[0], el => has( el, 'nino-admin-btn-danger' ) ).length === 1 );
ask( false );
check( '...a Cancel sends nothing', requests.length === 0 );
fire( byId2('favicon-undo'), 'click' );
ask( true );
check( 'a confirmed Undo asks the server to put the previous set back', requests.length === 1 && requests[0].action === 'favicon/undo' );
answer( 200, setOf() );
check( '...and the set it answers is drawn, Undo gone with the copy, and a toast says it - the line of the bar stays empty', byId2('favicon-undo').hidden === true && toasts().length === 1 && line().textContent === '' );
check( '...and the focus is on Generate, since the button that was pressed is gone', find( el => el.focused === true )[0] === byId2('favicon-generate') );

panel._data = setOf( { undo : 1700000100 } );
panel._paint();
requests.length = 0;
fire( byId2('favicon-undo'), 'click' );
ask( true );
answer( 409, { error : 'there is no previous set', code : 'favicon_no_previous' } );
check( 'an Undo with nothing to put back says so in the line, as an alert', line().dataset.state === 'error' && line().textContent === text('/_admin/error/favicon_no_previous') );

requests.length = 0;
fire( byId2('favicon-reset'), 'click' );
check( 'Reset asks first, as a question about a set that is overwritten', dialogs.length === 1 && requests.length === 0 && findAll( dialogs[0], el => has( el, 'nino-admin-btn-danger' ) ).length === 1 );
ask( false );
check( '...a cancelled question sends nothing', requests.length === 0 );
fire( byId2('favicon-reset'), 'click' );
ask( true );
check( 'a confirmed one asks the server for the base set', requests.length === 1 && requests[0].action === 'favicon/reset' );
answer( 200, setOf() );
check( '...and draws what it answers', toasts().length === 1 && line().textContent === '' );

fire( byId2('favicon-reset'), 'click' );
ask( true );
answer( 404, { error : 'x', code : 'favicon_no_base' } );
check( 'a base unit without a set is said in the line', line().dataset.state === 'error' && line().textContent === text('/_admin/error/favicon_no_base') );


// --- the words ------------------------------------------------------------------------------

const asked = new Set();
for( const found of source('_admin/Nino/Modules/Images/assets/favicon.js').matchAll( /getText\(\s*'(\/_admin\/[a-z0-9\/_-]+)'\s*\)/g ) )
	asked.add( found[1] );
const missing = Array.from( asked ).filter( function( key ) { return ( moduleEn[key] ?? workbench[key] ) === undefined || ( moduleDe[key] ?? workbenchDe[key] ) === undefined } );
check( 'the script asks for '+ asked.size+ ' keys and each has words in English and in German'+ ( missing.length === 0 ? '' : ' - not '+ missing.join(', ') ), asked.size > 10 && missing.length === 0 );

done();

} )();
