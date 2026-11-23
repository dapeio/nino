/**
 *	Nino										A compact filesystembased php framework
 *	nino-ui-fields-js-smoke.js	DOM-light checks for the fields of the second part of
 *															the workbench's form blocks: Nino.adminUi.checkField(),
 *															checklist(), localeRows(), fileField(), codeField(),
 *															dateTime(), selectField() and numberField().
 *
 *															Pinned: that each is the element of the guide - a label
 *															at the field, a group that is named, a button of the
 *															second kind - and gives its value the way it is meant (a
 *															tick true or false, a list of the ticked in the order of
 *															the options, a text for each language, a File or null,
 *															an ISO moment of the site's clock), that what is wrong
 *															is said at the field or the row it is wrong in, that a
 *															file that is too large or of another type is not taken,
 *															that a field of code leaves the Tab key to the browser,
 *															and that a language that is the working one is the first
 *															row; that selectField() and numberField() mark a
 *															required field as the others do, that numberField()
 *															takes a step, limits and a unit, and what numberOf()
 *															makes of a value with a dot or a comma.
 *
 *	Usage: node tests/nino-ui-fields-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, documentOf, element, findAll, fire, fills } = require( './harness.js' );

const page = element('div');
const doc = documentOf( page );
doc.body = element('body');
doc.createElement = element;

const text = fills( '_admin/text/en_US.php' );
const sandbox = { console : console, document : doc };
sandbox.window = sandbox;
sandbox.Nino = {
	events : { bindCallback : function() {} },
	content : { getText : function( key ) { return text[key] ?? '' } },
	admin : { workLocale : { current : 'de_DE' } },
};
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );

const ui = sandbox.Nino.adminUi;
ui.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English (US)', fr_FR : 'Français' } };

const classes = el => String( el.className ).split(/\s+/);
const errorsIn = el => findAll( el, node => classes( node ).includes('nino-admin-field-error') );
const named = ( el, tag ) => findAll( el, node => node.tagName === tag );

console.log( 'Nino.adminUi.checkField, checklist, localeRows, fileField, codeField, dateTime - the fields of a form\n' );

// --- checkField ------------------------------------------------------------------

{
	const told = [];
	const field = ui.checkField( { key : 'agree', label : 'I agree', checked : false, required : true, help : 'Why it is asked.', note : 'Kept in view.', onChange : value => told.push( value ) } );
	const box = field.fieldControl;

	check( 'a tick is a label with its name first and the box in it, as every field: the label stands at the field', field.tagName === 'LABEL' && classes( field ).includes('nino-admin-field') && box.type === 'checkbox' && field.children.includes( box ) && field.children[0].tagName === 'SPAN' );
	check( '...a required one has its star and aria-required, and its help is behind a (?) that is a button', box.getAttribute('aria-required') === 'true' && named( field, 'SPAN' ).some( el => classes( el ).includes('nino-admin-required') ) && findAll( field, el => classes( el ).includes('nino-admin-help-button') ).length === 1 );
	check( '...the note stays in view as a line under it', findAll( field, el => classes( el ).includes('nino-admin-field-note') ).length === 1 );
	box.checked = true;
	fire( box, 'change' );
	check( '...and says true when it is ticked', told.join() === 'true' );
}

// --- checklist -------------------------------------------------------------------

{
	const told = [];
	const list = ui.checklist( { key : 'fields', label : 'Fields', help : 'Which of them.', required : true, options : [ { value : 'b', label : 'Bee' }, { value : 'a', label : 'Ay', state : '12 keys' }, { value : 'c', label : 'See', disabled : true } ], value : [ 'a' ], onChange : value => told.push( value ) } );

	check( 'a list of ticks is a fieldset with a legend, named by its words, with a (?) behind them that is described by the sentence', list.el.tagName === 'FIELDSET' && named( list.el, 'LEGEND' ).length === 1 && findAll( list.el, el => classes( el ).includes('nino-admin-help-button') ).length === 1 && list.el.getAttribute('aria-labelledby') !== null );
	check( '...one row for each option with a box in it, a row\'s state at its end, a disabled option disabled', list.controls.length === 3 && findAll( list.el, el => classes( el ).includes('nino-admin-checklist-state') ).length === 1 && list.controls[2].disabled === true );
	check( '...its value is the list of the ticked ones, and what was given is ticked', list.value().join() === 'a' && list.controls[1].checked === true );
	list.controls[0].checked = true;
	fire( list.controls[0], 'change' );
	check( '...in the order of the options, not of the ticking, which the change says', list.value().join() === 'b,a' && told[0].join() === 'b,a' );
	list.set( [ 'c', 'zz' ] );
	check( '...set() ticks what it is given and a value that is no option is let go', list.value().join() === 'c' );
	list.error( 'Pick one.' );
	check( 'what is wrong is said in the group, which is marked invalid, and said once', errorsIn( list.el ).length === 1 && list.el.getAttribute('aria-invalid') === 'true' );
	list.error( 'Pick one.' );
	list.clear();
	check( '...and clear() takes it off again', errorsIn( list.el ).length === 0 && list.el.getAttribute('aria-invalid') === null );
}

// --- localeRows ------------------------------------------------------------------

{
	const told = [];
	const rows = ui.localeRows( { key : 'title', label : 'Title', required : true, locales : [ 'en_US', 'de_DE', 'fr_FR' ], values : { de_DE : 'Titel', en_US : 'Title' }, onChange : ( code, value ) => told.push( code+ '='+ value ) } );
	const order = findAll( rows.el, el => classes( el ).includes('nino-admin-localerow') );

	check( 'one row for each language, the working one first and marked, the others in the order of the site', order.map( row => row.dataset.locale ).join() === 'de_DE,en_US,fr_FR' && classes( order[0] ).includes('is-work') && order.slice( 1 ).every( row => classes( row ).includes('is-work') === false ) );
	check( '...the badge of a language is its name, at the left of its field, and a label of that field', order.every( row => row.children[0].tagName === 'LABEL' && classes( row.children[0] ).includes('nino-admin-locale-badge') && row.children[0].getAttribute('for') === rows.controls[row.dataset.locale].id ) && order[0].children[0].textContent === 'Deutsch' );
	check( '...the group is named by its words and each field by those and by its badge', rows.el.children[1].getAttribute('role') === 'group' && Object.values( rows.controls ).every( control => String( control.getAttribute('aria-labelledby') ).split(' ').length === 2 ) );
	check( '...it takes the values it is given, as one map or one text', rows.value('de_DE') === 'Titel' && rows.value().en_US === 'Title' && rows.value().fr_FR === '' );
	rows.controls.fr_FR.value = 'Titre';
	fire( rows.controls.fr_FR, 'input' );
	check( '...and says which language changed to what', told.join() === 'fr_FR=Titre' );
	rows.set( { de_DE : 'Neu' } );
	rows.set( 'en_US', 'New' );
	check( 'set() takes a map or a language and a text, and a language it has not is let go', rows.value().de_DE === 'Neu' && rows.value().en_US === 'New' && ( rows.set( { xx : 'y' } ), Object.keys( rows.value() ).join() === 'de_DE,en_US,fr_FR' ) );
	rows.error( 'en_US', 'Title is missing.' );
	const english = order[1];
	check( 'what is wrong is said in the row it is wrong in, and in no other', errorsIn( english ).length === 1 && errorsIn( order[0] ).length === 0 && rows.controls.en_US.getAttribute('aria-invalid') === 'true' && rows.controls.de_DE.getAttribute('aria-invalid') === null );
	rows.clear();
	check( '...clear() takes every sentence away', errorsIn( rows.el ).length === 0 );
	check( 'one line is an input, several lines a textarea', rows.controls.de_DE.tagName === 'INPUT' && ui.localeRows( { label : 'Text', locales : [ 'de_DE' ], multiline : true } ).controls.de_DE.tagName === 'TEXTAREA' );
	check( 'a working language the site does not have leaves the order of the site', ui.localeRows( { label : 'x', locales : [ 'en_US', 'fr_FR' ] } ).locales.join() === 'en_US,fr_FR' );
}

// --- fileField -------------------------------------------------------------------

{
	const told = [];
	const file = { name : 'cv.pdf', size : 2048, type : 'application/pdf' };
	const big = { name : 'big.pdf', size : 3 * 1048576, type : 'application/pdf' };
	const wrong = { name : 'cv.exe', size : 10, type : 'application/x-msdownload' };
	const field = ui.fileField( { key : 'cv', label : 'CV', accept : '.pdf,image/*', maxBytes : 2 * 1048576, note : 'PDF, up to 2 MB.', onChange : value => told.push( value ) } );
	const choose = ( picked ) => { field.input.files = [ picked ]; fire( field.input, 'change' ) };

	check( 'a file is chosen with a button of the second kind, the input itself is out of the way, and the limits stay in view', field.button.className === 'nino-admin-btn-secondary' && field.input.hidden === true && field.input.accept === '.pdf,image/*' && findAll( field.el, el => classes( el ).includes('nino-admin-field-note') ).length === 1 );
	check( '...the button is named by the name of the field and says what is chosen, "No file chosen" until a file is', field.el.children[1].getAttribute('role') === 'group' && field.value() === null && findAll( field.el, el => classes( el ).includes('nino-admin-filefield-name') )[0].dataset.state === 'none' );
	fire( field.button, 'click' );
	choose( file );
	const name = () => findAll( field.el, el => classes( el ).includes('nino-admin-filefield-name') )[0];
	const remove = () => named( field.el, 'BUTTON' ).find( el => el !== field.button );
	check( 'a file that was chosen is the value, its name and its size are shown, and Remove is there', field.value() === file && name().textContent === 'cv.pdf' && name().dataset.state === 'chosen' && remove().hidden === false && told.length === 1 && told[0] === file );
	choose( big );
	check( 'a file that is too large is not taken and is said at the field: what was chosen stays', field.value() === file && errorsIn( field.el ).length === 1 && told.length === 1 );
	choose( wrong );
	check( '...so is one of a type the field does not take, and the sentence is said once', field.value() === file && errorsIn( field.el ).length === 1 && told.length === 1 );
	check( '...in the words of a person: the types it takes by their names - the extension and the kind of file - and not as the notation the picker reads', errorsIn( field.el )[0].textContent === ui.format( text['/_admin/common/error/file-type'], 'PDF '+ text['/_admin/common/filetype/or']+ ' '+ text['/_admin/common/filetype/image'] ) && errorsIn( field.el )[0].textContent.indexOf('image/*') === -1 );
	check( '...a list is joined by commas and the word for "or" before the last, a subtype by its name', ui._acceptNames('.png,.webp,image/jpeg') === 'PNG, WEBP '+ text['/_admin/common/filetype/or']+ ' JPEG' && ui._acceptNames('image/svg+xml') === 'SVG' && ui._acceptNames('.pdf, .PDF') === 'PDF' );
	choose( { name : 'photo.JPG', size : 10, type : 'image/jpeg' } );
	check( '...and a file of the type with another spelling or by a type of its kind is taken, which takes the sentence away', field.value().name === 'photo.JPG' && errorsIn( field.el ).length === 0 && told.length === 2 );
	fire( remove(), 'click' );
	check( 'Remove takes the file away, says so and hides itself', field.value() === null && told[2] === null && name().dataset.state === 'none' && remove().hidden === true );
	// Enter on the button asks for the picker when the key is let go, as Space does: a picker asked for while the key goes down is refused in a dialog
	{
		const picker = ui.fileField( { key : 'cv', label : 'CV' } );
		let asked = 0;
		picker.input.click = function() { asked++ };
		const press = ( type, key, repeat ) => { const event = { prevented : false }; fire( picker.button, type, { key : key, repeat : repeat === true, preventDefault : function() { event.prevented = true } } ); return event };

		check( 'Enter on the file button goes down without a click of its own and asks for the picker on its release, once', ( press( 'keydown', 'Enter' ).prevented === true && asked === 0 && ( press( 'keyup', 'Enter' ), asked === 1 ) && ( press( 'keyup', 'Enter' ), asked === 1 ) ) );
		check( '...a release that no press on this button went before - the Enter that opened the dialog - asks for nothing; a key that repeats does not arm it; leaving the button disarms it',
			( press( 'keyup', 'Enter' ), asked === 1 ) && ( press( 'keydown', 'Enter', true ), press( 'keyup', 'Enter' ), asked === 1 ) && ( press( 'keydown', 'Enter' ), fire( picker.button, 'blur' ), press( 'keyup', 'Enter' ), asked === 1 ) );
		check( '...another key is left to the button, and the click of a pointer and of Space still asks at once', press( 'keydown', 'Tab' ).prevented === false && ( fire( picker.button, 'click' ), asked === 2 ) );
	}
	check( 'it uploads nothing: there is no request in the block, the caller has the File', ui.fileField.toString().indexOf('sendRequest') === -1 && ui.fileField.toString().indexOf('FormData') === -1 );
	check( 'what a file is held to is read the way the picker reads accept', ui._accepts( { name : 'A.PDF', type : '' }, '.pdf' ) === true && ui._accepts( { name : 'a.pdf', type : '' }, 'image/*' ) === false && ui._accepts( { name : 'a', type : 'image/png' }, 'image/*' ) === true && ui._accepts( { name : '.pdf', type : '' }, '.pdf' ) === false );
	check( 'a size is written as a person reads it', ui._bytes( 12 ) === '12 B' && ui._bytes( 1536 ) === '1.5 KB' && ui._bytes( 5 * 1048576 ) === '5 MB' );
}

// --- codeField -------------------------------------------------------------------

{
	const field = ui.codeField( { key : 'css', label : 'CSS', value : 'a {}', fills : { editable : true, items : [] } } );
	const area = field.fieldControl;

	check( 'a field of code is a textarea in the face of code that checks no spelling, completes nothing and does not wrap', area.tagName === 'TEXTAREA' && classes( area ).includes('nino-admin-code') && area.spellcheck === false && area.getAttribute('wrap') === 'off' && area.getAttribute('autocapitalize') === 'off' && area.getAttribute('autocomplete') === 'off' );
	check( '...the Tab key is the browser\'s: the field listens to no key and takes none', Object.keys( area.listeners ).every( name => name !== 'keydown' && name !== 'keypress' && name !== 'keyup' ) );
	check( '...it has the "[ ]" of the textfills where the account may edit them, the value it was given, and the lines of a text field', findAll( field, el => classes( el ).includes('nino-admin-fills-button') ).length === 1 && area.value === 'a {}' && area.rows === 8 && ui.codeField( { label : 'x', rows : 3 } ).control.rows === 3 );
}

// --- dateTime --------------------------------------------------------------------

{
	const told = [];
	const field = ui.dateTime( { key : 'start', label : 'Start', value : '2026-11-17T06:02:30', min : '2026-11-01', max : '2026-11-30T18:00', zone : 'Europe/Berlin', onChange : value => told.push( value ) } );

	check( 'a moment is two native fields, a date and a time, in one group that is named, each by the name and its own word', field.date.type === 'date' && field.time.type === 'time' && field.el.children[1].getAttribute('role') === 'group' && [ field.date, field.time ].every( control => String( control.getAttribute('aria-labelledby') ).split(' ').length === 2 ) );
	check( '...its value is one ISO string of the clock of the site, without seconds and without a zone, and what was given is in the two fields', field.value() === '2026-11-17T06:02' && field.date.value === '2026-11-17' && field.time.value === '06:02' );
	check( '...the zone of the site is said under it, and the earliest and the latest day are the limits of the date', findAll( field.el, el => classes( el ).includes('nino-admin-field-note') ).length === 1 && field.date.min === '2026-11-01' && field.date.max === '2026-11-30' );
	field.date.value = '2026-11-30';
	field.time.value = '18:30';
	check( '...valid() says what was broken: a moment after the latest', field.valid() === 'max' && ( field.time.value = '17:59', field.valid() === '' ) );
	field.set( '2026-10-31T23:59' );
	check( '...and one before the earliest', field.valid() === 'min' );
	field.date.value = '';
	field.time.value = '09:00';
	check( 'a time with no date is no value and is said to be incomplete', field.value() === '' && field.valid() === 'incomplete' );
	field.time.value = '';
	check( '...nothing at all is a value that is allowed: empty', field.value() === '' && field.valid() === '' );
	field.date.value = '2026-11-17';
	check( '...a date alone is midnight', field.value() === '2026-11-17T00:00' );
	fire( field.date, 'input' );
	check( 'a change says the value', told[told.length - 1] === '2026-11-17T00:00' );
	field.set( '2026-02-30' );
	check( 'a day the calendar does not have is no value, nor is a time of day it does not have', field.value() === '' && ui._moment( '2026-02-28T24:00' ) === null && ui._moment( '2026-02-28 07:05' ).time === '07:05' && ui._moment( 'tomorrow' ) === null );
	field.error( 'Start is not valid.' );
	check( 'what is wrong is said at the field', errorsIn( field.el ).length === 1 && field.date.getAttribute('aria-invalid') === 'true' );
}

// --- selectField: required ---------------------------------------------------------

{
	const deep = el => el.textContent+ el.children.map( deep ).join('');
	const options = [ { value : '', label : '' }, { value : 'a', label : 'A' } ];
	const plain = ui.selectField( { key : 'kind', label : 'Kind', options : options, value : '' } );
	const field = ui.selectField( { key : 'kind', label : 'Kind', options : options, value : '', required : true, help : 'Which.' } );
	const select = field.querySelector('select');

	check( 'a required select has the star behind its name, which a screen reader does not hear, and aria-required on the control', named( field, 'SPAN' ).filter( el => classes( el ).includes('nino-admin-required') && el.getAttribute('aria-hidden') === 'true' && el.textContent === '*' ).length === 1 && select.getAttribute('aria-required') === 'true' && select.required === true );
	check( '...its help stands behind the (?) as before, and the star is in the name that the (?) is named by', findAll( field, el => classes( el ).includes('nino-admin-help-button') ).length === 1 && deep( field.children[0] ).indexOf('Kind*') === 0 );
	check( 'a select that is not required has neither', named( plain, 'SPAN' ).filter( el => classes( el ).includes('nino-admin-required') ).length === 0 && plain.querySelector('select').getAttribute('aria-required') === null && !plain.querySelector('select').required );
}

// --- numberField: step, limits, unit, required -----------------------------------------

{
	const deep = el => el.textContent+ el.children.map( deep ).join('');
	const input = field => field.querySelector('input');
	const plain = ui.numberField( { key : 'n', label : 'Count', value : 3, min : 1, max : 9 } );

	check( 'a number field steps by 1 unless it is told another step, and takes the limits it is given', input( plain ).step === '1' && input( plain ).min === 1 && input( plain ).max === 9 );
	check( '...a step of a tenth or a half, or any, is written on the input as it is given', [ [ 0.1, '0.1' ], [ 0.5, '0.5' ], [ '0.25', '0.25' ], [ 'any', 'any' ] ].every( ( [ given, written ] ) => input( ui.numberField( { key : 'n', label : 'x', value : 1, step : given } ) ).step === written ) );
	check( '...without required it has no star and no aria-required', named( plain, 'SPAN' ).filter( el => classes( el ).includes('nino-admin-required') ).length === 0 && input( plain ).getAttribute('aria-required') === null );

	const depth = ui.numberField( { key : 'depth', label : 'Depth', unit : 'mm', value : 2.5, step : 0.5, required : true, help : 'How deep.' } );
	check( 'a unit is a word of the workbench, written in brackets behind the label: mm among them, in both languages', deep( depth.children[0] ).indexOf( 'Depth ('+ text['/_admin/common/unit/mm']+ ')' ) === 0 && text['/_admin/common/unit/mm'] === 'mm' && fills( '_admin/text/de_DE.php' )['/_admin/common/unit/mm'] === 'mm' );
	check( '...a required number has the star behind the name and aria-required on the input, and the name of its help is the words alone', named( depth, 'SPAN' ).filter( el => classes( el ).includes('nino-admin-required') ).length === 1 && input( depth ).getAttribute('aria-required') === 'true' && input( depth ).required === true && findAll( depth, el => classes( el ).includes('nino-admin-help-button') ).length === 1 );
}

// --- numberOf: what a number input says, as the number ------------------------------------

{
	// What a browser in German gives for "12,5" is "12.5" in the value of its input (measured in the browser run of the patch); a text typed with the comma is read the same
	check( 'a value with a dot, or with a comma, is the number', ui.numberOf('12.5') === 12.5 && ui.numberOf('12,5') === 12.5 && ui.numberOf('-0,5') === -0.5 && ui.numberOf(3) === 3 && ui.numberOf('7') === 7 );
	check( '...an empty field and anything that is no number is null, which is for the caller to say is nothing typed or wrong', [ '', '  ', null, undefined, 'abc', '1,2,3', '1.234,5', '12,5 mm', '1e3' ].every( value => ui.numberOf( value ) === null ) );
}

// --- the note of a field is read with it -----------------------------------------------------

{
	const base = { key : 'k', label : 'L', note : 'Stays in view.' };
	const made = {
		text : () => { const el = ui.textField( base ); return [ el, el.querySelector('input') ] },
		textarea : () => { const el = ui.textareaField( base ); return [ el, el.querySelector('textarea') ] },
		select : () => { const el = ui.selectField( Object.assign( { options : [], value : '' }, base ) ); return [ el, el.querySelector('select') ] },
		number : () => { const el = ui.numberField( Object.assign( { value : 1 }, base ) ); return [ el, el.querySelector('input') ] },
		tick : () => { const el = ui.checkField( base ); return [ el, el.fieldControl ] },
		switcher : () => { const el = ui.switchField( Object.assign( { checked : false }, base ) ); return [ el, el.querySelector('input') ] },
		code : () => { const el = ui.codeField( base ); return [ el, el.fieldControl ] },
		list : () => { const h = ui.checklist( Object.assign( { options : [ { value : 'a', label : 'A' } ], value : [] }, base ) ); return [ h.el, h.el ] },
		file : () => { const h = ui.fileField( base ); return [ h.el, findAll( h.el, el => el.getAttribute('role') === 'group' )[0] ] },
		moment : () => { const h = ui.dateTime( base ); return [ h.el, findAll( h.el, el => el.getAttribute('role') === 'group' )[0] ] },
		languages : () => { const h = ui.localeRows( Object.assign( { locales : [ 'de_DE' ] }, base ) ); return [ h.el, findAll( h.el, el => el.getAttribute('role') === 'group' )[0] ] },
	};
	const described = ( root, control ) => String( control.getAttribute('aria-describedby') ?? '' ).split(/\s+/).map( id => findAll( root, el => el.id === id )[0] ).filter( Boolean );

	Object.keys( made ).forEach( function( name ) {
		const [ root, control ] = made[name]();
		const note = findAll( root, el => classes( el ).includes('nino-admin-field-note') || ( el.tagName === 'SMALL' && el.textContent === 'Stays in view.' ) )[0];
		check( 'the note of a '+ name+ ' is what its control - or its group - is described by', note !== undefined && described( root, control ).includes( note ) );
	} );

	// Together with the help, and an error does not take it away
	const field = ui.checkField( { key : 'k', label : 'L', note : 'Stays in view.', help : 'Why.' } );
	element('div').appendChild( field );
	ui.fieldError( field, field.fieldControl, 'e1', 'Wrong.' );
	const tokens = String( field.fieldControl.getAttribute('aria-describedby') ).split(/\s+/);
	check( '...after the sentence of the help, both still there while an error is said, with the error first', tokens.length === 3 && tokens[0] === 'e1' && tokens.slice( 1 ).every( id => findAll( field, el => el.id === id ).length === 1 ) );
	check( 'a field without a note is described by nothing it has not', ui.textField( { key : 'k', label : 'L' } ).querySelector('input').getAttribute('aria-describedby') === null );
}

done();
