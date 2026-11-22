/**
 *	Nino										A compact filesystembased php framework
 *	admin-language-js-smoke.js	DOM-light checks for the Language panel's script
 *													(_admin/Nino/Modules/Language/assets/admin.js).
 *
 *													The languages are named as the whole workbench names them
 *													and ticked in a list of the design system; a language is
 *													added in a dialog that asks for its code and nothing else,
 *													where what is wrong with the code is said at its field; a
 *													language that is on and has no file of its own is a notice
 *													above the form; the form ends in the one bar of the guide,
 *													Save its one main button, and what went well is a toast.
 *
 *	Usage: node tests/admin-language-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, element, findAll, fire } = require( './harness.js' );

// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Language/text/en_US.php');
const moduleDe 		= fills('_admin/Nino/Modules/Language/text/de_DE.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

/** The panel in its own context, with the form's mount in a page that has one focus and dialogs that open and close */
function build() {

	const mount = element('div');
	mount.id = 'language-form';

	const requests = [];
	const dialogs = [];
	const body = element('body');

	const Nino = {
		admin : {},
		events : { bindCallback : function() {} },
		content : { getText : text },
	};
	const sandbox = {
		console : console,
		document : {
			createElement : function( tag ) {
				const created = element( tag );
				if( tag === 'dialog' ) {
					created.open = false;
					created.showModal = function() { created.open = true };
					created.contains = function( node ) { return node !== null && ( node === created || findAll( created, function( el ) { return el === node } ).length > 0 ) };
					created.close = function() { created.open = false; fire( created, 'close' ) };
					dialogs.push( created );
				}
				return created;
			},
			createElementNS : function( space, tag ) { return element( tag ) },
			createTextNode : function( value ) { return Object.assign( element('#text'), { textContent : String( value ) } ) },
			getElementById : function( id ) { return id === 'language-form' ? mount : ( findAll( mount, function( el ) { return el.id === id } )[0] ?? null ) },
			addEventListener : function() {},
			removeEventListener : function() {},
			documentElement : null,
			body : body,
			get activeElement() { return findAll( mount, function( el ) { return el.focused === true } )[0] ?? null },
		},
		Nino : Nino,
	};
	sandbox.window = { Nino : Nino, setTimeout : function() { return 1 }, clearTimeout : function() {}, matchMedia : function() { return { matches : false } } };

	const context = vm.createContext( sandbox );
	load( '_admin/assets/Nino.admin.js', context );

	// What the shell hands over: the native language and the name of each one as the site's own picker says it
	Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English (US)', fr_FR : 'Français' } };

	load( '_admin/Nino/Modules/Language/assets/admin.js', context );

	const panel = Nino.admin.language;
	panel._apiCall = function( endpoint, payload, callback ) { requests.push( { endpoint : endpoint, payload : payload, callback : callback } ) };

	return {
		panel : panel,
		mount : mount,
		requests : requests,
		dialogs : dialogs,
		/** Answer the last request the panel made */
		answer : function( status, body ) { requests[requests.length - 1].callback( status, body ) },
		/** A sentence of the panel as it is said, its placeholders filled the way the panel fills them */
		said : function( key, ...params ) { return Nino.adminUi.format( text( key ), ...params ) },
		field : function( id ) { return findAll( mount, function( el ) { return el.id === id } )[0] },
		/** The toasts on the page: what they say */
		toasts : function() { return findAll( body, function( el ) { return el.classList.contains('nino-admin-toast-text') } ).map( function( el ) { return el.textContent } ) },
		/** The tick of a language */
		tick : function( code ) { return findAll( mount, function( el ) { return el.type === 'checkbox' && el.value === code } )[0] },
		/** The row of a language, the list's own */
		row : function( code ) { return findAll( mount, function( el ) { return el.type === 'checkbox' && el.value === code } )[0].parentNode },
		localeName : Nino.adminUi.localeName,
	};
}

const LOCALES = [
	{ code : 'de_DE', name : 'German (Germany)', hasText : true, keys : 120, active : true },
	{ code : 'en_US', name : 'English (United States)', hasText : true, keys : 120, active : true },
	{ code : 'fr_FR', name : 'French (France)', hasText : false, keys : 0, active : false },
];

/** A panel that has read its list and drawn the form */
function opened() {
	const t = build();
	t.panel.init();
	t.answer( 200, {
		intro : '/_admin/language/intro',
		fields : [
			{ type : 'locales', label : '/_admin/language/label/available', hint : '/_admin/language/hint/available' },
			{ type : 'native', key : '/nino/locales/native', label : '/_admin/language/label/native', hint : '/_admin/language/hint/native', value : 'de_DE' },
		],
		locales : LOCALES.map( function( locale ) { return Object.assign( {}, locale ) } ),
	} );
	return t;
}

/** The dialog that adds a language, opened by the button of the bar */
function adder( t ) {
	fire( t.field('language-add'), 'click' );
	const dialog = t.dialogs[t.dialogs.length - 1];
	return {
		dialog : dialog,
		input : findAll( dialog, function( el ) { return el.tagName === 'INPUT' } )[0],
		send : findAll( dialog, function( el ) { return el.tagName === 'BUTTON' } ).pop(),
		errors : function() { return findAll( dialog, function( el ) { return el.className === 'nino-admin-field-error' } ) },
	};
}

console.log('Admin Language');


// --- the form --------------------------------------------------------------

{
	const t = opened();

	check( 'every language is a row of the list of ticks, named as the whole workbench names it - by the name the site gives it, not by the English name of the server, and not with its code', [ 'de_DE', 'en_US', 'fr_FR' ].every( function( code ) {
		return findAll( t.row( code ), function( el ) { return el.textContent === t.localeName( code ) } ).length === 1;
	} ) && t.localeName('en_US') === 'English (US)' );
	check( '...with what exists for it at the end of the row: how many keys, or that there is no file', findAll( t.row('de_DE'), function( el ) { return el.classList.contains('nino-admin-checklist-state') } )[0].textContent === t.said( '/_admin/language/label/keys', 120 )
		&& findAll( t.row('fr_FR'), function( el ) { return el.classList.contains('nino-admin-checklist-state') } )[0].textContent === t.said( '/_admin/language/label/notext', 'fr_FR' ) );
	check( 'the ticked ones are the active ones, and the native select offers those and no others', t.tick('de_DE').checked === true && t.tick('fr_FR').checked === false
		&& t.field('language-native').children.map( function( option ) { return option.value } ).join() === 'de_DE,en_US' && t.field('language-native').value === 'de_DE' );
	check( '...each in the name of the workbench', t.field('language-native').children.map( function( option ) { return option.textContent } ).join() === 'Deutsch,English (US)' );
	check( 'no paragraph that explains stands under the title of a section or under a field: it is behind a (?)', findAll( t.mount, function( el ) { return el.classList.contains('nino-admin-hint') } ).length === 0 && findAll( t.mount, function( el ) { return String( el.className ).indexOf('nino-admin-help-button') !== -1 } ).length > 0 );

	const bars = findAll( t.mount, function( el ) { return el.classList.contains('nino-admin-actionbar') } );
	const primaries = findAll( t.mount, function( el ) { return el.classList.contains('nino-admin-btn-primary') } );
	check( 'the form ends in the one bar of the guide: the add button at its side, and Save as the one main button at the far right', bars.length === 1 && primaries.length === 1
		&& bars[0].children[bars[0].children.length - 1] === primaries[0] && primaries[0].type === 'submit'
		&& bars[0].children.indexOf( t.field('language-add') ) !== -1 && t.field('language-add').classList.contains('nino-admin-btn-secondary') );
	check( 'there is no field for a new code in the form: the adder is a dialog', findAll( t.mount, function( el ) { return el.tagName === 'INPUT' && el.type === 'text' } ).length === 0 && t.dialogs.length === 0 );
}


// --- the dialog that adds a language ---------------------------------------

{
	const t = opened();
	const a = adder( t );
	const before = t.requests.length;

	check( 'the button opens a dialog with one field, the code, and what pressing it does stands behind the (?) at the field, not in view under it', t.dialogs.length === 1 && a.dialog.open === true
		&& findAll( a.dialog, function( el ) { return el.tagName === 'INPUT' } ).length === 1
		&& findAll( a.dialog, function( el ) { return el.classList.contains('nino-admin-help-text') && el.textContent.indexOf( text('/_admin/language/hint/add') ) !== -1 } ).length === 1
		&& findAll( a.dialog, function( el ) { return el.classList.contains('nino-admin-field-note') } ).length === 0 );

	a.input.value = 'xx';
	fire( a.send, 'click' );
	check( 'a code that is none is refused at its field before anything is sent, with the sentence of what a code looks like - the dialog stays open', t.requests.length === before && a.dialog.open === true
		&& a.input.attributes['aria-invalid'] === 'true' && a.errors().length === 1 && a.errors()[0].textContent === text('/_admin/language/error/id') );

	a.input.value = 'it_IT';
	fire( a.input, 'input' );
	fire( a.send, 'click' );
	const request = t.requests[t.requests.length - 1];
	check( 'a code is sent as it is typed, and the dialog stays open until the server has said yes', request.endpoint === 'addlocale' && request.payload.locale === 'it_IT' && a.dialog.open === true );

	t.answer( 500, { error : 'refused' } );
	check( 'a request the server refused is said at the field, in the words it gave, and the dialog can be sent again', a.dialog.open === true && a.input.attributes['aria-invalid'] === 'true' && a.errors()[0].textContent.indexOf( 'refused' ) !== -1 && a.send.disabled === false );

	fire( a.send, 'click' );
	t.answer( 200, { created : true, keys : 3, from : 'de_DE', name : 'Italiano' } );
	const added = t.panel._locales.find( function( locale ) { return locale.code === 'it_IT' } );
	check( 'a language that was made is listed, switched off - a skeleton of empty keys is not served - and the dialog is closed', added !== undefined && added.hasText === true && added.active === false && t.tick('it_IT').checked === false && a.dialog.open === false );
	check( '...and the workbench names it from now on, as the server named it: a new language is not the bare code until the page is read again', t.localeName('it_IT') === 'Italiano' && t.panel._locales.some( function( locale ) { return locale.code === 'it_IT' } ) === true );
	check( '...and it is said as a toast, in the words for a language that is left off', t.toasts().length === 1 && t.toasts()[0] === t.said( '/_admin/language/msg/created', t.localeName('it_IT'), 3 ) );
	check( '...and the form is not unsaved by it: a file is made at once, and Save stores the ticks alone', t.panel.isDirty() === false );
}


// --- the file of a language that is on already -----------------------------

{
	const t = opened();

	// What the warning of a language without a file leads to: it is ticked, and its file is made
	t.tick('fr_FR').checked = true;
	fire( t.tick('fr_FR'), 'change' );
	const notices = function() { return findAll( t.field('language-missing'), function( el ) { return el.classList.contains('nino-admin-notice') } ) };
	check( 'a language that is ticked and has no file is a notice above the form, naming it in the words of the workbench', notices().length === 1 && notices()[0].textContent === t.said( '/_admin/language/error/missing', 'Français' ) );
	check( '...and is no line of the bar, whose state it would take from the form', t.field('language-form-msg').textContent === '' );

	t.tick('fr_FR').checked = false;
	fire( t.tick('fr_FR'), 'change' );
	check( '...and switching it off again takes the notice away', notices().length === 0 );

	t.tick('fr_FR').checked = true;
	fire( t.tick('fr_FR'), 'change' );
	const a = adder( t );
	a.input.value = 'fr_FR';
	fire( a.send, 'click' );
	t.answer( 200, { created : true, keys : 3, from : 'de_DE' } );

	const made = t.panel._locales.find( function( locale ) { return locale.code === 'fr_FR' } );
	check( 'its file is made and it stays ticked: the language is on, as it was, and the notice is gone', made.hasText === true && made.active === true && t.tick('fr_FR').checked === true && t.panel._locales.length === LOCALES.length && notices().length === 0 );
	check( '...and the toast does not say it is left off: it says what its pages show', t.toasts()[0] === t.said( '/_admin/language/msg/served', 'Français', 3 ) && t.toasts()[0] !== t.said( '/_admin/language/msg/created', 'Français', 3 ) );
}


// --- a file that is there already, a language that is listed ---------------

{
	const t = opened();
	const a = adder( t );

	a.input.value = 'fr_FR';
	fire( a.send, 'click' );
	t.answer( 200, { created : false, keys : 3, from : 'de_DE' } );
	check( 'a file the server had already is said to be left as it is, as a toast', t.toasts()[0] === t.said( '/_admin/language/msg/existed', 'fr_FR', 3 ) && a.dialog.open === false );

	const b = adder( t );
	const count = t.requests.length;
	b.input.value = 'en_US';
	t.tick('en_US').checked = false;
	fire( t.tick('en_US'), 'change' );
	fire( b.send, 'click' );
	// A language that is on is told it is there; the one that is off is told where to tick it
	check( '...the sentence for a language that is on does not send anybody to a tick that is set', ( () => { fire( b.send, 'click' ); t.tick('en_US').checked = true; fire( t.tick('en_US'), 'change' ); fire( b.send, 'click' ); return b.errors().length === 1 && b.errors()[0].textContent === text('/_admin/language/error/served') } )() );
	t.tick('en_US').checked = false;
	fire( t.tick('en_US'), 'change' );
	fire( b.send, 'click' );
		check( 'a language that is listed and translated is refused at the field of the dialog, which stays open: it says so, in the words for a listed language, and nothing is asked of the server and nothing is ticked', t.requests.length === count && t.tick('en_US').checked === false && b.dialog.open === true
		&& b.input.attributes['aria-invalid'] === 'true' && b.errors().length === 1 && b.errors()[0].textContent === t.said( '/_admin/language/error/listed', 'English (US)' ) );
}


// --- saving ----------------------------------------------------------------

{
	const t = opened();
	t.tick('en_US').checked = false;
	fire( t.tick('en_US'), 'change' );
	check( 'unticking a language is an unsaved change, and takes it out of the native select', t.panel.isDirty() === true && t.field('language-native').children.map( function( option ) { return option.value } ).join() === 'de_DE' );

	let ended = null;
	t.panel._save( function( ok ) { ended = ok } );
	check( 'a save posts the ticked languages and the native one', t.requests[t.requests.length - 1].endpoint === 'save'
		&& t.requests[t.requests.length - 1].payload.fields['/nino/locales/available'].join() === 'de_DE' && t.requests[t.requests.length - 1].payload.fields['/nino/locales/native'] === 'de_DE' );

	t.answer( 400, { error : 'no', code : 'invalid_value' } );
	check( 'a refused save is said in the line of the bar, as an alert, and ends with done( false )', ended === false && t.field('language-form-msg').dataset.state === 'error' && t.field('language-form-msg').attributes.role === 'alert' && t.toasts().length === 0 );

	t.panel._save( function( ok ) { ended = ok } );
	t.answer( 200, { saved : [] } );
	check( 'a save that went through is a toast, once, and the form is read again from the server', ended === true && t.toasts().length === 1 && t.requests[t.requests.length - 1].endpoint === 'list' && t.panel.isDirty() === false );

	t.tick('fr_FR').checked = true;
	fire( t.tick('fr_FR'), 'change' );
	t.panel.discard();
	check( 'discarding puts the ticks back to what is stored', t.tick('fr_FR').checked === false && t.panel.isDirty() === false );
}


// --- the words -------------------------------------------------------------

{
	const keys = [ '/_admin/language/label/add', '/_admin/language/label/code', '/_admin/language/hint/code', '/_admin/language/msg/created', '/_admin/language/msg/served', '/_admin/language/msg/existed', '/_admin/language/error/id' ];
	check( 'both interface languages have the words of the dialog and of what it says, none empty, and none the same as the other language', keys.every( function( key ) {
		return ( moduleEn[key] ?? '' ) !== '' && ( moduleDe[key] ?? '' ) !== '' && moduleEn[key] !== moduleDe[key];
	} ) );
}

done();
