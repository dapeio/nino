/**
 *	Nino										A compact filesystembased php framework
 *	admin-field-help-js-smoke.js	The help behind the (?) at the name of a field and the
 *													textfill icon "[ ]" beside it: fieldHelp(), the options
 *													help, hint, note and fills of the field builders, and
 *													fillsDialog(). Read from the elements a script builds, the
 *													way a person and a screen reader meet them - no word is
 *													compared but the ones the test hands in.
 *
 *													Pinned: the (?) is a button with a name and aria-expanded
 *													whose sentence stands in the document and describes the
 *													field; Escape and a press beside it close it, and at most
 *													one is open; hint and help draw the same; a note stays
 *													where it is; the textfill icon is there only where the
 *													caller says the account may edit the field; a press on a
 *													row of the dialog copies the notation and, where the page
 *													has no clipboard, marks it for copying by hand.
 *
 *	Usage: node tests/admin-field-help-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, fills, load, documentOf, element, findAll, fire } = require( './harness.js' );

const words = fills('_admin/text/en_US.php');


// --- the page --------------------------------------------------------------

/**
 *	Nino.admin.js in a page whose document takes listeners and whose dialogs open
 *
 *	@param		{Function}	[clipboard]		What Nino.clipboard() does: a function that answers a promise of true or false
 *	@param		{boolean}		[api]					Whether the page has navigator.clipboard to write to, true by default
 *
 *	@return		{Object}
 */
function world( clipboard, api ) {

	const page = element('div');
	const dialogs = [];
	const listeners = {};
	const copied = [];
	const selections = [];
	const doc = documentOf( page );

	doc.body = element('body');
	doc.createElement = function( tag ) {
		const created = element( tag );
		if( tag === 'dialog' ) {
			created.open = false;
			created.showModal = function() { created.open = true };
			created.close = function() { created.open = false; fire( created, 'close' ) };
			created.contains = function( node ) { return node !== null && ( node === created || findAll( created, el => el === node ).length > 0 ) };
			dialogs.push( created );
		}
		return created;
	};
	doc.addEventListener = function( name, fn ) { ( listeners[name] = listeners[name] || [] ).push( fn ) };
	doc.removeEventListener = function() {};
	doc.createRange = function() { return { selectNodeContents : function( node ) { this.node = node } } };

	const Nino = {
		events : { bindCallback : function() {} },
		content : { getText : function( key ) { return words[key] ?? '' } },
		clipboard : function( text ) { copied.push( text ); return clipboard === undefined ? Promise.resolve( true ) : clipboard( text ) },
	};
	const sandbox = { console : console, document : doc, Nino : Nino, Promise : Promise };
	sandbox.window = { Nino : Nino, innerWidth : 1000, innerHeight : 900, isSecureContext : api !== false, navigator : api === false ? {} : { clipboard : { writeText : function() { return Promise.resolve() } } }, setTimeout : function() { return 0 }, clearTimeout : function() {}, matchMedia : function() { return { matches : false } }, getSelection : function() {
		return { removeAllRanges : function() {}, addRange : function( range ) { selections.push( range.node ) } };
	} };
	load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );

	return {
		ui : Nino.adminUi, page : page, dialogs : dialogs, copied : copied, selections : selections, body : doc.body,
		// A key pressed or a pointer pressed on the document, the way a page's own listeners hear it
		press : function( name, event ) {
			const stopped = { value : false };
			( listeners[name] || [] ).slice().forEach( fn => fn( Object.assign( { type : name, target : element('div'), preventDefault : function() {}, stopPropagation : function() { stopped.value = true } }, event ) ) );
			return stopped.value;
		},
	};
}

const find = ( root, predicate ) => findAll( root, predicate );
const byClass = ( root, name ) => find( root, el => String( el.className ).split(/\s+/).indexOf( name ) !== -1 );
const byId = ( root, id ) => find( root, el => el.id === id )[0];
const inputOf = field => find( field, el => el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' )[0];

/** The field without what is only a serial number: the tree of tags, classes and words */
const shape = function( el ) {
	return el.tagName+ '.'+ el.className+ ':'+ ( el.children.length === 0 ? el.textContent : el.children.map( shape ).join(',') );
};


// --- the (?) -----------------------------------------------------------------

console.log( 'fieldHelp - the sentence behind the (?)\n' );

{
	const w = world();
	const field = w.ui.textField( { label : 'Name', help : 'What the name does.' } );
	const input = inputOf( field );
	const button = byClass( field, 'nino-admin-help-button' )[0];

	check( 'a field with help has a button at its name, 24 px, named after the field, closed', button !== undefined && button.tagName === 'BUTTON' && button.attributes['aria-label'] === 'Help for Name'
		&& button.className.indexOf('nino-admin-btn-icon--small') !== -1 && button.attributes['aria-expanded'] === 'false' );

	const sentence = byId( field, String( input.attributes['aria-describedby'] ).split(' ')[0] );
	check( '...the sentence stands in the document and the field is described by it', sentence !== undefined && sentence.textContent === 'What the name does.' && sentence.className === 'nino-admin-help-text' );

	const nameWords = byId( field, input.attributes['aria-labelledby'] );
	check( '...and the field is named by its words alone, not by the sentence that stands in the same label', nameWords !== undefined && nameWords.children[0].textContent === 'Name' && find( nameWords, el => el === sentence ).length === 0 );

	fire( button, 'click' );
	const root = button.parentNode;
	check( 'a press opens the sentence: aria-expanded is true and the wrapper says it is open', button.attributes['aria-expanded'] === 'true' && root.dataset.open === 'true' );

	check( 'Escape closes it, stops there, and gives the focus back to the button', w.press( 'keydown', { key : 'Escape' } ) === true && button.attributes['aria-expanded'] === 'false' && root.dataset.open === undefined && button.focused === true );
	check( '...and a key that is not Escape leaves it alone', ( fire( button, 'click' ), w.press( 'keydown', { key : 'a' } ) === false && button.attributes['aria-expanded'] === 'true' ) );

	w.press( 'click', { target : input } );
	check( 'a press beside the bubble closes it', button.attributes['aria-expanded'] === 'false' );

	fire( button, 'click' );
	w.press( 'click', { target : sentence } );
	check( '...a press on the bubble itself does not', button.attributes['aria-expanded'] === 'true' );
	fire( button, 'click' );
	check( '...and a second press on the button closes it', button.attributes['aria-expanded'] === 'false' );
}

{
	const w = world();
	const one = w.ui.textField( { label : 'One', help : 'First.' } );
	const two = w.ui.selectField( { key : 'two', label : 'Two', help : 'Second.', options : [], value : '' } );
	const first = byClass( one, 'nino-admin-help-button' )[0];
	const second = byClass( two, 'nino-admin-help-button' )[0];

	fire( first, 'click' );
	fire( second, 'click' );
	check( 'at most one bubble is open: opening a second closes the first', first.attributes['aria-expanded'] === 'false' && second.attributes['aria-expanded'] === 'true'
		&& byClass( one, 'nino-admin-help' )[0].dataset.open === undefined && byClass( two, 'nino-admin-help' )[0].dataset.open === 'true' );
}

{
	const w = world();
	const holder = element('div');
	const standing = w.ui.textField( { label : 'Name', required : true, help : 'x' } );
	holder.appendChild( standing );

	w.ui.fieldError( standing, inputOf( standing ), 'name-error', 'Name is missing.' );
	const described = String( inputOf( standing ).attributes['aria-describedby'] ).split(' ');
	check( 'an error is said in front of the sentence of the help, which stays', described[0] === 'name-error' && described.length === 2 && byId( standing, described[1] ) !== undefined );

	w.ui.clearFieldErrors( holder );
	check( '...and clearing the errors leaves the field described by the sentence of its help alone', String( inputOf( standing ).attributes['aria-describedby'] ) === described[1] && inputOf( standing ).attributes['aria-invalid'] === undefined );
}


// --- hint, help, note ---------------------------------------------------------

console.log( '\nhint, help and note\n' );

{
	const w = world();
	const builders = {
		text : o => w.ui.textField( o ),
		number : o => w.ui.numberField( Object.assign( { value : 3 }, o ) ),
		select : o => w.ui.selectField( Object.assign( { key : 'k', options : [], value : '' }, o ) ),
		switch : o => w.ui.switchField( Object.assign( { checked : false }, o ) ),
		textarea : o => w.ui.textareaField( o ),
	};

	Object.keys( builders ).forEach( function( kind ) {
		const old = builders[kind]( { label : 'Field', hint : 'Says what it does.' } );
		const fresh = builders[kind]( { label : 'Field', help : 'Says what it does.' } );
		check( kind+ 'Field: hint and help draw the same, a (?) at the name and no line under the field', shape( old ) === shape( fresh ) && byClass( old, 'nino-admin-help-button' ).length === 1
			&& find( old, el => el.tagName === 'SMALL' ).length === 0 );
	} );

	const note = w.ui.numberField( { label : 'Pause', value : 5, unit : 'seconds', help : 'Why.', note : 'Stays in view.' } );
	const lines = find( note, el => el.tagName === 'SMALL' ).map( el => el.textContent );
	const noteLine = find( note, el => el.tagName === 'SMALL' && el.textContent === 'Stays in view.' )[0];
	check( 'a note stays where it is: a line after the control, outside the sentence of the help', noteLine !== undefined && noteLine.className.indexOf('nino-admin-hint') !== -1 && noteLine.hidden !== true && String( noteLine.className ).indexOf('sr-only') === -1
		&& find( byClass( note, 'nino-admin-help' )[0], el => el === noteLine ).length === 0 && note.children.indexOf( noteLine ) > note.children.indexOf( inputOf( note ) ) );
	check( '...as does the conversion of a duration', lines.indexOf('= 5 s') !== -1 );

	const noteAlone = w.ui.textField( { label : 'Slug', note : '12 / 60' } );
	check( 'a note without help draws no (?)', byClass( noteAlone, 'nino-admin-help-button' ).length === 0 && find( noteAlone, el => el.textContent === '12 / 60' ).length === 1 );

	const plain = w.ui.textField( { label : 'Plain' } );
	check( 'a field with neither has its name and its control as it always had', plain.children.length === 2 && inputOf( plain ).attributes['aria-labelledby'] === undefined && inputOf( plain ).attributes['aria-describedby'] === undefined );
}


// --- the label belongs to the field --------------------------------------------

console.log( '\nthe label and the bubble\n' );

{
	const w = world();
	const items = [ { name : 'A', notation : '#/a#', value : '' } ];
	const kinds = {
		text : () => w.ui.textField( { label : 'Name', help : 'x' } ),
		number : () => w.ui.numberField( { label : 'Pause', value : 1, help : 'x' } ),
		select : () => w.ui.selectField( { key : 'k', label : 'Mode', options : [], value : '', help : 'x' } ),
		textarea : () => w.ui.textareaField( { label : 'Text', help : 'x' } ),
		fills : () => w.ui.textareaField( { label : 'Text', fills : { editable : true, items : items } } ),
	};

	Object.keys( kinds ).forEach( function( kind ) {
		const field = kinds[kind]();
		check( kind+ ': the label points at its field, so a press on the name focuses the field and not the (?) that stands first in the label', inputOf( field ).id !== '' && field.attributes['for'] === inputOf( field ).id );
	} );
}

// --- the section ---------------------------------------------------------------

{
	const w = world();
	const set = element('fieldset');
	const legend = element('legend');
	legend.appendChild( Object.assign( element('#text'), { textContent : 'Mode' } ) );
	set.appendChild( legend );
	const help = w.ui.sectionHelp( legend, set, 'What the whole section is for.' );
	const button = byClass( legend, 'nino-admin-help-button' )[0];
	const words = byId( legend, set.attributes['aria-labelledby'] );

	check( 'the explanation of a section is a (?) at its title, named for the section, and the section is named by the words of the title alone', button !== undefined && button.attributes['aria-label'] === 'Help for Mode'
		&& words !== undefined && words.children[0].textContent === 'Mode' && find( words, el => el === help ).length === 0 );
	check( '...the sentence stands in the document and the section is described by it, and a fieldset needs no role of its own', byId( legend, set.attributes['aria-describedby'] ).textContent === 'What the whole section is for.' && set.attributes.role === undefined );

	const card = element('div');
	const heading = element('h2');
	heading.appendChild( Object.assign( element('#text'), { textContent : 'Card' } ) );
	card.appendChild( heading );
	w.ui.sectionHelp( heading, card, 'Sentence.' );
	check( '...any other element becomes a group', card.attributes.role === 'group' && card.attributes['aria-labelledby'] !== undefined );
}

// --- where the bubble opens ------------------------------------------------------

{
	const w = world();
	const rect = ( left, top, width, height ) => ( { left : left, top : top, right : left + width, bottom : top + height, width : width, height : height } );
	const at = field => {
		const button = byClass( field, 'nino-admin-help-button' )[0];
		const sentence = byClass( field, 'nino-admin-help-text' )[0];
		return { button : button, sentence : sentence, input : inputOf( field ) };
	};
	const top = parts => parseInt( parts.sentence.style.top, 10 );
	const overlaps = ( parts, box ) => parseInt( parts.sentence.style.left, 10 ) < box.right && parseInt( parts.sentence.style.left, 10 ) + 200 > box.left && top( parts ) < box.bottom && top( parts ) + 80 > box.top;

	// The name stands over the field: the field is under the button, and the bubble does not lie on it
	const stacked = w.ui.textField( { label : 'Name', help : 'x' } );
	const one = at( stacked );
	one.button.getBoundingClientRect = () => rect( 20, 100, 24, 24 );
	one.sentence.getBoundingClientRect = () => rect( 0, 0, 200, 80 );
	one.input.getBoundingClientRect = () => rect( 20, 128, 280, 38 );
	fire( one.button, 'click' );
	check( 'a field that stands under its name is not covered by the sentence about it', overlaps( one, rect( 20, 128, 280, 38 ) ) === false );

	// The bar at the foot of the page: the bubble stays above it
	fire( one.button, 'click' );
	const bar = element('div');
	bar.className = 'nino-admin-actionbar';
	bar.getBoundingClientRect = () => rect( 0, 150, 1000, 70 );
	// The bar of a pane that is not shown stands first in the page and has no size
	const ghost = element('div');
	ghost.className = 'nino-admin-actionbar';
	ghost.getBoundingClientRect = () => rect( 0, 0, 0, 0 );
	w.page.appendChild( ghost );
	w.page.appendChild( bar );
	fire( one.button, 'click' );
	check( 'the bubble never goes below the top of the bar at the foot of the page', top( one ) + 80 <= 150 );
}

// --- the textfill icon --------------------------------------------------------

console.log( '\nthe icon "[ ]" and the dialog\n' );

{
	const w = world();
	const items = [ { name : 'Company · Email', notation : '#/project/company/contact/email#', value : 'info@example.org' }, { name : 'Company · Name', notation : '#/project/company/general/name#', value : 'Müller' } ];
	const iconsOf = field => byClass( field, 'nino-admin-fills-button' );

	check( 'a multi-line field of an account that may edit it has the icon, named for what it shows', ( () => {
		const field = w.ui.textareaField( { label : 'Text', fills : { editable : true, items : items } } );
		const icon = iconsOf( field )[0];
		return iconsOf( field ).length === 1 && icon.attributes['aria-label'] === 'Show available textfills' && icon.className.indexOf('nino-admin-btn-icon--small') !== -1;
	} )() );

	check( '...a field the account may not edit has none, and neither has one that asks for none', iconsOf( w.ui.textareaField( { label : 'Text', fills : { editable : false, items : items } } ) ).length === 0
		&& iconsOf( w.ui.textareaField( { label : 'Text' } ) ).length === 0 && iconsOf( w.ui.textareaField( { label : 'Text', help : 'x' } ) ).length === 0 );
	check( '...a line of text has it only where its caller sets it', iconsOf( w.ui.textField( { label : 'Line', help : 'x' } ) ).length === 0 && iconsOf( w.ui.textField( { label : 'Line', fills : { editable : true, items : items } } ) ).length === 1 );

	const both = w.ui.textareaField( { label : 'Text', help : 'What it holds.', fills : { editable : true, items : items } } );
	const row = byClass( both, 'nino-admin-field-text' )[0].parentNode;
	check( 'the (?) and the icon stand together at the name, the help first', row.children.length === 3 && byClass( row.children[1], 'nino-admin-help-button' ).length === 1 && row.children[2].className.indexOf('nino-admin-fills-button') !== -1 );

	// The dialog: a row for each, searchable, and a press copies the NOTATION
	fire( iconsOf( both )[0], 'click' );
	const dialog = w.dialogs[w.dialogs.length - 1];
	const rows = byClass( dialog, 'nino-admin-pick-item' );
	check( 'a press on the icon opens the dialog with a row for each fill: its name, its notation and its value, and a search', w.dialogs.length === 1 && dialog.open === true && rows.length === 2
		&& byClass( rows[0], 'nino-admin-pick-name' )[0].textContent === 'Company · Email' && byClass( rows[0], 'nino-admin-pick-code' )[0].textContent === '#/project/company/contact/email#'
		&& rows[0].children[1].children[1].textContent.indexOf('info@example.org') !== -1 && find( dialog, el => el.tagName === 'INPUT' && el.type === 'search' ).length === 1 );

	fire( rows[1], 'click' );
	( async () => {
		await Promise.resolve(); await Promise.resolve(); await Promise.resolve();

		check( 'a press on a row copies its notation - not the value, not the key - says so as a toast and closes the dialog', w.copied.join() === '#/project/company/general/name#' && dialog.open === false
			&& byClass( w.body, 'nino-admin-toast' ).length === 1 );
		check( '...and the focus goes back to the field, where the notation is pasted next', inputOf( both ).focused === true );

		// A page that has no clipboard: the notation is marked where it stands, the dialog stays
		const bare = world( () => Promise.resolve( false ) );
		const field = bare.ui.textareaField( { label : 'Text', fills : { editable : true, items : items } } );
		fire( byClass( field, 'nino-admin-fills-button' )[0], 'click' );
		const open = bare.dialogs[0];
		fire( byClass( open, 'nino-admin-pick-item' )[0], 'click' );
		await Promise.resolve(); await Promise.resolve(); await Promise.resolve();

		check( 'where the clipboard refuses, the notation is marked for copying by hand, the dialog stays, and no toast says it was copied', open.open === true && bare.selections.length === 1
			&& bare.selections[0].textContent === '#/project/company/contact/email#' && byClass( bare.body, 'nino-admin-toast' ).length === 0 );
		check( '...a sentence in the dialog says so, as a status', byClass( open, 'nino-admin-pick-marked' ).length === 1 && byClass( open, 'nino-admin-pick-marked' )[0].attributes.role === 'status' );

		// A page that has no clipboard to write to (http, not localhost): the older way is not trusted - the answer of the lying Nino.clipboard is not asked
		const http = world( () => Promise.resolve( true ), false );
		const plain = http.ui.textareaField( { label : 'Text', fills : { editable : true, items : items } } );
		fire( byClass( plain, 'nino-admin-fills-button' )[0], 'click' );
		const modal = http.dialogs[0];
		fire( byClass( modal, 'nino-admin-pick-item' )[0], 'click' );
		await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
		check( 'without navigator.clipboard nothing is said to be copied: Nino.clipboard is not asked, no toast, the dialog stays, the notation is marked and the sentence stands in the dialog', http.copied.length === 0
			&& byClass( http.body, 'nino-admin-toast' ).length === 0 && modal.open === true && http.selections.length === 1 && http.selections[0].textContent === '#/project/company/contact/email#'
			&& byClass( modal, 'nino-admin-pick-marked' ).length === 1 );

		// The list is asked for when the icon is pressed, and where it cannot be had the field says so
		const lazy = world();
		let asked = 0;
		const loading = lazy.ui.textareaField( { label : 'Text', fills : { editable : true, load : function( answer ) { asked++; answer( null ) } } } );
		const holder = element('div');
		holder.appendChild( loading );
		check( 'a list that is loaded is asked for at the press, not before', asked === 0 && ( fire( byClass( loading, 'nino-admin-fills-button' )[0], 'click' ), asked === 1 ) );
		check( '...and one that could not be had is said at the field, with no dialog and without marking the field as wrong', lazy.dialogs.length === 0 && inputOf( loading ).attributes['aria-invalid'] === undefined && byClass( holder, 'nino-admin-field-error' ).length === 1 );

		done();
	} )();
}
