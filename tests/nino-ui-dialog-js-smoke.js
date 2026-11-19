/**
 *	Nino										A compact filesystembased php framework
 *	nino-ui-dialog-js-smoke.js	DOM-light checks for the dialogs of the workbench's
 *															scripts: Nino.adminUi.confirm() with its title and its
 *															typed word, formDialog(), pickDialog() and
 *															propertiesDialog() - four dialogs on the one
 *															choiceDialog().
 *
 *															Pinned: that a question cannot be asked without a
 *															title, that the typed word locks the way on, the order
 *															and the kinds of the answers and where the focus opens,
 *															that a form does not send what has a field error and does
 *															not close with what was typed in it without a question,
 *															that a list shows a search only from a number of items,
 *															that the properties sheet has one button and hands every
 *															change to its caller at once, that the focus goes back to
 *															what asked, that the sizes are classes made of tokens,
 *															and, in the source of every panel, that each question
 *															that is asked has a title; that a form takes every field
 *															of the family (a tick, a list of ticks, the languages, a
 *															file, code, a moment), that a question lists what it is
 *															about as text, and that a modal dialog keeps the Tab key
 *															and makes the rest of the page inert.
 *
 *	Usage: node tests/nino-ui-dialog-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { check, done, load, documentOf, element, findAll, fire, source } = require( './harness.js' );


// --- the page --------------------------------------------------------------

/**
 *	Nino.admin.js in a page whose dialogs open modally and close the way a browser's do
 *
 *	@param		{Object}		[shell]			What the page has of Nino.admin
 *
 *	@return		{Object}
 */
function world( shell, options ) {

	options = options || {};

	const dialogs = [];
	const page = element('div');
	const Nino = {
		events : { bindCallback : function() {} },
		content : { getText : function( key ) { return key } },
	};

	if( shell !== undefined )
		Nino.admin = shell;

	const doc = documentOf( page );
	doc.body = element('body');
	doc.createElement = function( tag ) {
		const created = element( tag );
		if( tag === 'dialog' ) {
			created.open = false;
			if( options.noDialog !== true ) {
				created.showModal = function() { created.open = true; created.modal = true };
				created.show = function() { created.open = true; created.modal = false };
			}
			created.contains = function( node ) { return node !== null && ( node === created || findAll( created, el => el === node ).length > 0 ) };
			created.close = function() { created.open = false; fire( created, 'close' ) };
			dialogs.push( created );
		}
		return created;
	};

	const keys = [];
	doc.addEventListener = function( name, fn ) { if( name === 'keydown' ) keys.push( fn ) };
	doc.removeEventListener = function( name, fn ) { const at = keys.indexOf( fn ); if( at !== -1 ) keys.splice( at, 1 ) };

	const sandbox = { console : console, document : doc, Nino : Nino };
	sandbox.window = { Nino : Nino, matchMedia : function() { return { matches : options.wide === true } }, confirm : options.confirm ?? function() { throw new Error('a dialog of the design system is not the browser\'s confirm') } };
	load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );

	return { ui : Nino.adminUi, dialogs : dialogs, doc : doc, keys : keys, body : doc.body, sandbox : sandbox };
}

const buttons = dialog => findAll( dialog, el => el.tagName === 'BUTTON' );
const tag = ( dialog, name ) => findAll( dialog, el => el.tagName === name.toUpperCase() );
const last = w => w.dialogs[w.dialogs.length - 1];

/** The Escape key: the keydown a dialog with a guard takes itself and, where nobody prevented it, the cancel event and the close that follows. Answers whether the dialog is still open */
function escape( dialog ) {
	let prevented = false;
	fire( dialog, 'keydown', { key : 'Escape', preventDefault : function() { prevented = true } } );
	if( prevented === false ) {
		fire( dialog, 'cancel', { preventDefault : function() { prevented = true } } );
		if( prevented === false )
			dialog.close();
	}
	return dialog.open;
}

// The name of what a call throws: the script runs in a context of its own, whose TypeError is not this one's
function thrown( fn ) {
	try {
		fn();
	} catch( error ) {
		return error.name;
	}
	return null;
}

const asked = ( overrides ) => Object.assign( { title : 'Delete item', message : 'Delete it?', yes : 'Delete item', no : 'Cancel', onChoose : function() {} }, overrides );

console.log('Nino.adminUi dialogs - confirm, formDialog, pickDialog, propertiesDialog\n');


// --- confirm(): the title ---------------------------------------------------

{
	const w = world();
	const options = asked();
	delete options.title;
	check( 'a question without a title is a TypeError, and nothing was opened', thrown( () => w.ui.confirm( 'destructive', options ) ) === 'TypeError' && w.dialogs.length === 0 );

	const quiet = world( { mode : { policy : function() { return { ask : false, note : '' } } } } );
	check( '...also where the shell would not ask: the mistake is in the call', thrown( () => quiet.ui.confirm( 'big', Object.assign( asked(), { title : '' } ) ) ) === 'TypeError' );

	w.ui.confirm( 'destructive', asked() );
	const heading = tag( w.dialogs[0], 'h2' );
	check( 'the title is the heading that names the dialog', heading.length === 1 && heading[0].textContent === 'Delete item' && w.dialogs[0].attributes['aria-labelledby'] === heading[0].id );
}


// --- confirm(): the answers -------------------------------------------------

{
	const w = world();
	w.ui.confirm( 'destructive', asked() );
	const row = buttons( w.dialogs[0] );
	check( 'the answers stand in one row, Cancel at the left and the way on at the far right, which is the one dangerous answer',
		row.map( b => b.className ).join() === 'nino-admin-btn-secondary,nino-admin-btn-danger' && findAll( w.dialogs[0], el => el.className === 'nino-admin-dialog-actions' )[0].children.length === 2 );
	check( '...and the way on is named as the title is, the answer that does nothing opens with the focus', row[1].textContent === 'Delete item' && row[0].focused === true );
	w.dialogs[0].close();

	w.ui.confirm( 'big', asked() );
	const calm = buttons( w.dialogs[1] );
	check( 'a question that is not about something that goes has a primary way on, and it has the focus', calm.map( b => b.className ).join() === 'nino-admin-btn-secondary,nino-admin-btn-primary' && calm[1].focused === true );
	w.dialogs[1].close();
}


// --- confirm(): typed -------------------------------------------------------

{
	const w = world( { mode : { policy : function() { return { ask : false, note : '' } } } } );
	const answers = [];
	const returned = w.ui.confirm( 'destructive', asked( { typed : 'legal', onChoose : ok => answers.push( ok ) } ) );
	check( 'a question with a word to type is asked where the shell would not ask it', returned === true && w.dialogs.length === 1 && answers.length === 0 );

	const dialog = w.dialogs[0];
	const field = tag( dialog, 'input' )[0];
	const row = buttons( dialog );
	check( 'the way on is locked and the field has the focus', row[1].disabled === true && field.focused === true );

	field.value = 'lega';
	fire( field, 'input' );
	check( '...it stays locked for a word that is not the one', row[1].disabled === true );
	field.dispatchEvent( { type : 'keydown', key : 'Enter', bubbles : true, preventDefault : function() {} } );
	check( '...and Enter in the field does not go on either', dialog.open === true && answers.length === 0 );

	field.value = ' legal ';
	fire( field, 'input' );
	check( 'the word, with the blanks around it dropped, opens it', row[1].disabled === false );
	fire( row[1], 'click' );
	check( '...and the way on answers yes', answers.join() === 'true' && dialog.open === false );

	const plain = world();
	plain.ui.confirm( 'destructive', asked() );
	check( 'a question with no word has no field', tag( plain.dialogs[0], 'input' ).length === 0 );
}


// --- formDialog(): what is wrong stays at the field -------------------------

{
	const w = world();
	const sent = [];
	const opener = element('button');
	opener.focus();
	w.doc.activeElement = opener;

	const fields = [
		{ key : 'name', label : 'Name', required : true },
		{ key : 'kind', label : 'Kind', type : 'select', options : [ { value : 'a', label : 'A' }, { value : 'b', label : 'B' } ], value : 'a' },
	];
	w.ui.formDialog( { title : 'Duplicate theme', fields : fields, submit : 'Duplicate theme', cancel : 'Cancel', onSubmit : ( values, ctl ) => sent.push( { values : values, ctl : ctl } ) } );

	const dialog = w.dialogs[0];
	const name = tag( dialog, 'input' )[0];
	const row = buttons( dialog );
	check( 'the dialog is a form of the middle size, its first field has the focus, and Cancel stands at the left of the primary way on',
		/nino-admin-dialog--form/.test( dialog.className ) && name.focused === true && row.map( b => b.className ).join() === 'nino-admin-btn-secondary,nino-admin-btn-primary' );

	tag( dialog, 'select' )[0].focus();
	fire( row[1], 'click' );
	check( 'a send with a required field empty sends nothing: the dialog stays, the field is marked and says why at the field, and has the focus',
		sent.length === 0 && dialog.open === true && name.attributes['aria-invalid'] === 'true' && tag( dialog, 'p' ).filter( p => p.className === 'nino-admin-field-error' ).length === 1
		&& name.attributes['aria-describedby'] === tag( dialog, 'p' ).filter( p => p.className === 'nino-admin-field-error' )[0].id && name.focused === true );

	name.value = 'Copy';
	fire( name, 'input' );
	name.dispatchEvent( { type : 'keydown', key : 'Enter', bubbles : true, preventDefault : function() {} } );
	check( 'Enter in a field sends what is typed, once, and the sentences of the refusal before are gone',
		sent.length === 1 && sent[0].values.name === 'Copy' && sent[0].values.kind === 'a' && tag( dialog, 'p' ).filter( p => p.className === 'nino-admin-field-error' ).length === 0 );
	check( '...the dialog stays open until the caller says so, and cannot be sent again meanwhile', dialog.open === true && row[1].disabled === true );

	fire( row[1], 'click' );
	check( '...a second press is not a second send', sent.length === 1 );

	sent[0].ctl.fail( 'name', 'Name is taken.' );
	check( 'what the server refuses is said at the field, the dialog is open and can be sent again', dialog.open === true && name.attributes['aria-invalid'] === 'true' && row[1].disabled === false && name.focused === true );

	sent[0].ctl.close();
	check( 'close() from the caller closes it, and the focus goes back to what asked', dialog.open === false && opener.focused === true );
}


// --- formDialog(): what was typed is not thrown away silently ---------------

{
	const w = world();
	const cancelled = [];
	w.ui.formDialog( { title : 'Save as', fields : [ { key : 'name', label : 'Name', value : 'Base' } ], submit : 'Save', cancel : 'Cancel', onSubmit : function() {}, onCancel : () => cancelled.push( 'cancel' ) } );
	const dialog = w.dialogs[0];

	check( 'a form that was not changed closes on Escape without a question', escape( dialog ) === false && dialog.open === false && w.dialogs.length === 1 && cancelled.length === 1 );

	w.ui.formDialog( { title : 'Save as', fields : [ { key : 'name', label : 'Name', value : 'Base' } ], submit : 'Save', cancel : 'Cancel', onSubmit : function() {}, onCancel : () => cancelled.push( 'cancel' ) } );
	const second = last( w );
	const field = tag( second, 'input' )[0];
	field.value = 'Other';
	fire( field, 'input' );

	check( 'a form with something typed in it is kept open by Escape, and a question stands on it', escape( second ) === true && second.open === true && w.dialogs.length === 3 && w.dialogs[2].open === true );

	const question = w.dialogs[2];
	check( '...the question has a title, the answer that discards is the dangerous one at the far right and the focus is on the other', tag( question, 'h2' ).length === 1
		&& buttons( question ).map( b => b.className ).join() === 'nino-admin-btn-secondary,nino-admin-btn-danger' && buttons( question )[0].focused === true );
	question.close();
	check( '...Cancel keeps the form as it was, with what was typed', second.open === true && field.value === 'Other' && cancelled.length === 1 && w.ui._choiceOpen === true );

	// The question goes again from the button Cancel of the form
	fire( buttons( second )[0], 'click' );
	fire( buttons( last( w ) )[1], 'click' );
	check( '...and discarding closes the form, and says it was cancelled', second.open === false && cancelled.length === 2 && w.ui._choiceOpen === false );

	// A field that was typed into and typed back is not a change
	w.ui.formDialog( { title : 'Save as', fields : [ { key : 'name', label : 'Name', value : 'Base' } ], submit : 'Save', cancel : 'Cancel', onSubmit : function() {} } );
	const third = last( w );
	const again = tag( third, 'input' )[0];
	again.value = 'Basement';
	fire( again, 'input' );
	again.value = 'Base';
	fire( again, 'input' );
	check( '...a field typed in and put back is no change', escape( third ) === false && third.open === false );

}


// --- pickDialog() ------------------------------------------------------------

{
	const w = world();
	const items = n => Array.from( { length : n }, ( _, at ) => ( { value : 'v'+ at, label : 'Item '+ at, description : 'Line '+ at } ) );
	const picked = [];

	w.ui.pickDialog( { title : 'Insert block', items : items( w.ui.PICK_SEARCH_FROM - 1 ), search : 'Search blocks', cancel : 'Cancel', onChoose : v => picked.push( v ) } );
	check( 'a short list has no search, and the focus opens on its first item', tag( w.dialogs[0], 'input' ).length === 0 && buttons( w.dialogs[0] )[0].focused === true );
	w.dialogs[0].close();

	w.ui.pickDialog( { title : 'Insert block', items : items( w.ui.PICK_SEARCH_FROM ), search : 'Search blocks', cancel : 'Cancel', onChoose : v => picked.push( v ) } );
	const dialog = w.dialogs[1];
	const search = tag( dialog, 'input' )[0];
	check( 'from the number of items on there is a search, named for a screen reader, and it has the focus', search !== undefined && search.focused === true && search.attributes['aria-label'] === 'Search blocks' );
	check( '...the dialog is a wide one and has Cancel alone to answer with', /nino-admin-dialog--wide/.test( dialog.className ) && buttons( dialog ).filter( b => b.className === 'nino-admin-btn-secondary' ).length === 1 );

	search.value = 'item 3';
	fire( search, 'input' );
	const shown = buttons( dialog ).filter( b => b.className === 'nino-admin-pick-item' && b.hidden === false );
	check( 'the search keeps the items that have the words', shown.length === 1 && shown[0].children[0].textContent === 'Item 3' );

	search.dispatchEvent( { type : 'keydown', key : 'Enter', bubbles : true, preventDefault : function() {} } );
	check( 'Enter in the search picks the first that is left, the dialog closes, and the caller gets its value once', picked.join() === 'v3' && dialog.open === false );

	w.ui.pickDialog( { title : 'Insert block', items : items( 2 ), cancel : 'Cancel', onChoose : v => picked.push( v ), onCancel : () => picked.push( 'none' ) } );
	escape( last( w ) );
	check( 'Escape picks nothing and says so', picked.join() === 'v3,none' );
}


// --- propertiesDialog() -----------------------------------------------------

{
	const w = world();
	const opener = element('button');
	w.doc.activeElement = opener;
	const changes = [];
	const closed = [];

	w.ui.propertiesDialog( {
		title : 'Button Contact',
		close : 'Close',
		onChange : ( key, value ) => changes.push( key+ '='+ value ),
		onClose : () => closed.push( 'closed' ),
		sections : [
			{ key : 'content', label : 'Content', fields : [ { key : 'text', label : 'Text', value : 'Hello' } ] },
			{ key : 'layout', label : 'Layout', content : () => element('div') },
		],
	} );

	const dialog = w.dialogs[0];
	const close = buttons( dialog ).filter( b => b.className.indexOf( 'nino-admin-btn-' ) === 0 );
	check( 'the sheet has exactly one button of its own, Close, and no primary or dangerous one', close.length === 1 && close[0].className === 'nino-admin-btn-secondary' && close[0].textContent === 'Close' );
	check( '...it stands at the right over the whole height, as its own size', /nino-admin-dialog--sheet/.test( dialog.className ) && dialog.className.indexOf('--form') === -1 );

	const text = tag( dialog, 'input' )[0];
	check( 'the focus opens on the first field of the section that is open', text.focused === true );

	text.value = 'Hi';
	fire( text, 'input' );
	check( 'a change is handed to the caller at once, with nothing to press', changes.join() === 'text=Hi' && dialog.open === true );

	escape( dialog );
	check( 'Escape closes it without a question - the values are the caller\'s already - and the focus goes back to what asked', dialog.open === false && closed.length === 1 && opener.focused === true );
}


// --- typed: what is typed goes to the caller -----------------------------------

{
	const w = world();
	const got = [];
	w.ui.confirm( 'destructive', asked( { typed : 'legal', onChoose : ( ok, word ) => got.push( [ ok, word ] ) } ) );
	const dialog = w.dialogs[0];
	const field = tag( dialog, 'input' )[0];
	field.value = '  legal ';
	fire( field, 'input' );
	fire( buttons( dialog )[1], 'click' );
	check( 'the word that was typed, as typed and without the blanks around it, is the second argument of onChoose', got.length === 1 && got[0][0] === true && got[0][1] === 'legal' );

	const plain = world();
	const answers = [];
	plain.ui.confirm( 'destructive', asked( { onChoose : ( ok, word ) => answers.push( [ ok, word ] ) } ) );
	fire( buttons( plain.dialogs[0] )[1], 'click' );
	check( '...and a question with no word has none to hand over', answers.length === 1 && answers[0][1] === undefined );

	const text = findAll( dialog, el => el.tagName === 'P' && el.id === 'nino-admin-choice-text' )[0];
	check( 'the dialog with a word to type is described by its message, which is read out with it', text !== undefined && dialog.attributes['aria-describedby'].split(' ').indexOf( text.id ) !== -1 );

	const bare = world( undefined, { noDialog : true } );
	const refused = [];
	bare.ui.confirm( 'destructive', asked( { typed : 'legal', onChoose : ( ok ) => refused.push( ok ) } ) );
	check( 'a browser with no <dialog> answers No to a question with a word to type: OK is no word', refused.join() === 'false' );
}


// --- the one that is open is not stuck ----------------------------------------

{
	const w = world();
	const mother = [];
	w.ui.formDialog( { title : 'Save as', fields : [ { key : 'name', label : 'Name', value : 'Base' } ], submit : 'Save', cancel : 'Cancel', onSubmit : ( values, ctl ) => mother.push( ctl ), onCancel : function() {} } );
	const form = w.dialogs[0];
	const field = tag( form, 'input' )[0];
	field.value = 'Other';
	fire( field, 'input' );
	escape( form );
	const question = w.dialogs[1];

	check( 'a question on a form has ids of its own: it is named by its own title, and the form by its own', question.attributes['aria-labelledby'] !== form.attributes['aria-labelledby'] && tag( question, 'h2' )[0].id === question.attributes['aria-labelledby'] && tag( form, 'h2' )[0].id === form.attributes['aria-labelledby'] );

	// The form closes by the caller's word while the question stands, and the question is answered afterwards
	fire( buttons( form )[1], 'click' );
	mother[0].close();
	check( 'the form is closed by its caller while the question about discarding it stands', form.open === false && w.ui._choiceOpen === true );
	question.close();
	check( '...and once that is answered too, nothing stands: every dialog can be asked for again', w.ui._choiceOpen === false && w.ui.confirm( 'big', asked() ) === true );
}


// --- the error is said ---------------------------------------------------------

{
	const w = world();
	w.ui.formDialog( { title : 'Save as', fields : [ { key : 'name', label : 'Name', required : true } ], submit : 'Save', cancel : 'Cancel', onSubmit : function() {} } );
	const dialog = w.dialogs[0];
	const live = findAll( dialog, el => el.attributes.role === 'alert' )[0];
	check( 'the form has a place that announces, empty until something is wrong', live !== undefined && live.textContent === '' );
	fire( buttons( dialog )[1], 'click' );
	check( '...and what is wrong with the first field is said there, once, where the focus does not move', live.textContent !== '' && live.textContent === findAll( dialog, el => el.className === 'nino-admin-field-error' )[0].textContent );
}


// --- the order of the answers, and where the focus is ----------------------------

{
	const w = world();
	// The shapes of the shell's own questions: unsaved input with and without a save, a refused save
	const shapes = [
		[ { value : 'discard', label : 'D', kind : 'danger' }, { value : 'cancel', label : 'C', kind : 'secondary' }, { value : 'save', label : 'S', kind : 'primary' } ],
		[ { value : 'discard', label : 'D', kind : 'danger' }, { value : 'cancel', label : 'C', kind : 'secondary' } ],
		[ { value : 'reload', label : 'R', kind : 'secondary' }, { value : 'cancel', label : 'C', kind : 'secondary' }, { value : 'overwrite', label : 'O', kind : 'danger' } ],
	];
	shapes.forEach( ( choices, at ) => {
		w.ui.choiceDialog( { message : 'x', choices : choices, escape : 'cancel', onChoose : function() {} } );
		const row = buttons( last( w ) );
		const kinds = row.map( b => b.className.replace( 'nino-admin-btn-', '' ) );
		const focused = row.filter( b => b.focused === true );
		check( 'shape '+ ( at + 1 )+ ': the focus is on exactly one answer, and not on the one that destroys', focused.length === 1 && focused[0].className !== 'nino-admin-btn-danger' );
		if( at !== 1 )
			check( '...and the way on is at the far right', kinds[kinds.length - 1] !== 'secondary' );
		last( w ).close();
	} );
}


// --- the sheet is not modal where there is room ------------------------------------

{
	const w = world( undefined, { wide : true } );
	const opener = element('button');
	w.doc.activeElement = opener;
	const changes = [];
	const handle = w.ui.propertiesDialog( { title : 'Button', close : 'Close', opener : opener, onChange : ( key, value, section ) => changes.push( section+ ':'+ key+ '='+ value ),
		sections : [ { key : 'content', label : 'Content', fields : [ { key : 'text', label : 'Text', value : 'Hello' } ] }, { key : 'layout', label : 'Layout', fields : [ { key : 'text', label : 'Text', value : 'Two' } ] } ] } );
	const dialog = w.dialogs[0];

	check( 'from 38 rem on the sheet is opened beside the surface and not as a modal dialog, and the workbench root makes room for it', dialog.open === true && dialog.modal === false && w.body.classList.contains('nino-admin-has-sheet') );
	check( '...it is no question that is open: a question of the surface can be asked over it', w.ui._choiceOpen === false && w.ui.confirm( 'destructive', asked() ) === true );
	last( w ).close();

	check( '...the answer is a handle with close() and set(), not a boolean', typeof handle === 'object' && typeof handle.close === 'function' && typeof handle.set === 'function' );

	const text = tag( dialog, 'input' )[0];
	handle.set( 'text', 'Undone' );
	check( 'set() puts a value into the field of the sheet and does not call onChange', text.value === 'Undone' && changes.length === 0 );
	text.value = 'Typed';
	fire( text, 'input' );
	check( '...and onChange names the section a change came from', changes.join() === 'content:text=Typed' );

	w.keys.forEach( fn => fn( { key : 'Escape', preventDefault : function() {} } ) );
	check( 'Escape closes it wherever the focus is, and the focus goes back to what asked, the root has its room back', dialog.open === false && opener.focused === true && w.body.classList.contains('nino-admin-has-sheet') === false && w.keys.length === 0 );
	check( '...and it can be opened again', typeof w.ui.propertiesDialog( { title : 'Button', close : 'Close', sections : [] } ) === 'object' );

	const narrow = world();
	narrow.ui.propertiesDialog( { title : 'Button', close : 'Close', sections : [] } );
	check( 'below that width it is modal and the whole screen', narrow.dialogs[0].modal === true && narrow.ui._choiceOpen === true );
}


// --- the dialog is one ---------------------------------------------------------

{
	const w = world();
	const sizes = [ 'narrow', 'form', 'wide', 'sheet' ];
	sizes.forEach( size => {
		const opened = w.ui.choiceDialog( { message : 'x', size : size, choices : [ { value : 'close', label : 'Close', kind : 'secondary' } ], onChoose : function() {} } );
		check( 'size '+ size+ ' is the class nino-admin-dialog--'+ size, opened === true && last( w ).className.split(' ').indexOf( 'nino-admin-dialog--'+ size ) !== -1 );
		last( w ).close();
	} );

	w.ui.choiceDialog( { message : 'x', className : 'own-size', choices : [ { value : 'close', label : 'Close', kind : 'secondary' } ], onChoose : function() {} } );
	check( 'a dialog that brings a class of its own for its size is not made narrow beside it', last( w ).className === 'nino-admin-dialog own-size' );
	last( w ).close();

	w.ui.confirm( 'big', asked() );
	const refused = [
		w.ui.confirm( 'big', asked() ),
		w.ui.formDialog( { title : 'T', fields : [], submit : 'S', cancel : 'C', onSubmit : function() {} } ),
		w.ui.pickDialog( { title : 'T', items : [], cancel : 'C', onChoose : function() {} } ),
		w.ui.propertiesDialog( { title : 'T', sections : [], close : 'C' } ),
	];
	check( 'a second dialog while one is open is the same click twice and is refused, by each of the four', refused.every( answer => answer === false ) );
	last( w ).close();

	w.ui.formDialog( { title : 'T', fields : [], submit : 'S', cancel : 'C', onSubmit : function() {} } );
	const body = findAll( last( w ), el => el.className === 'nino-admin-dialog-body' )[0];
	check( 'every dialog has its head and its foot of its own and what is between them scrolls: title, content, answers, in this order', body.children.map( el => el.className ).join() === 'nino-admin-dialog-head,nino-admin-dialog-content,nino-admin-dialog-actions' );
}

{
	const css = source( '_admin/assets/style.css' );
	const tokens = { narrow : '26rem', form : '40rem', wide : '60rem' };
	check( 'the three widths are tokens, and each size class reads its token and no number',
		Object.keys( tokens ).every( size => css.indexOf( '--nino-admin-dialog-'+ size+ ': '+ tokens[size]+ ';' ) !== -1
			&& new RegExp( 'nino-admin-dialog--'+ size+ '[^{}]*\\{\\s*width: min\\(var\\(--nino-admin-dialog-'+ size+ '\\)' ).test( css ) ) );
	check( 'the answers are never the whole width of the dialog: the row ends at the right and its buttons are as wide as their words',
		/\.nino-admin-dialog-actions \{[^}]*justify-content: flex-end;/.test( css ) && /\.nino-admin-dialog-actions > button \{\s*width: auto;/.test( css ) );
}


// --- every question the panels ask has a title -------------------------------------

{
	const root = path.join( __dirname, '..' );
	const scripts = [];

	( function walk( dir ) {
		fs.readdirSync( dir, { withFileTypes : true } ).forEach( entry => {
			const full = path.join( dir, entry.name );
			if( entry.isDirectory() === true && entry.name !== 'node_modules' )
				walk( full );
			else if( entry.name.endsWith('.js') === true )
				scripts.push( full );
		} );
	} )( path.join( root, '_admin' ) );

	( function walk( dir ) {
		fs.readdirSync( dir, { withFileTypes : true } ).forEach( entry => {
			const full = path.join( dir, entry.name );
			if( entry.isDirectory() === true )
				walk( full );
			else if( entry.name.endsWith('.js') === true && full.indexOf( path.join( '', 'assets' ) ) !== -1 )
				scripts.push( full );
		} );
	} )( path.join( root, '_nino', 'Nino', 'Modules' ) );

	let calls = 0;
	const without = [];

	scripts.forEach( file => {
		const code = fs.readFileSync( file, 'utf8' );
		for( const match of code.matchAll( /Nino\.adminUi\.confirm\(\s*'\w+',\s*\{/g ) ) {
			// The object literal of the call: to the brace that closes the one it opens
			let depth = 1;
			let at = match.index + match[0].length;
			while( depth > 0 && at < code.length ) {
				const c = code[at++];
				depth += c === '{' ? 1 : ( c === '}' ? -1 : 0 );
			}
			const literal = code.slice( match.index + match[0].length, at );
			calls++;
			// A property of this object, not of one inside it
			let own = literal;
			for( let before = ''; before !== own; ) {
				before = own;
				own = own.replace( /\{[^{}]*\}/g, '' );
			}
			if( /^\s*title\s*:/m.test( own ) === false )
				without.push( path.relative( root, file )+ ':'+ code.slice( 0, match.index ).split('\n').length );
		}
	} );

	console.log( '  ('+ calls+ ' questions asked in the sources of the panels)' );
	check( 'every confirm() of the workbench\'s scripts gives a title'+ ( without.length > 0 ? ': '+ without.join(', ') : '' ), calls > 20 && without.length === 0 );
}

// --- formDialog(): every field of the family ----------------------------------------

{
	const w = world();
	const sent = [];
	const file = { name : 'logo.png', size : 40, type : 'image/png', lastModified : 1 };
	w.ui.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English (US)' } };
	w.ui.formDialog( {
		title : 'Add item',
		submit : 'Add item',
		cancel : 'Cancel',
		fields : [
			{ key : 'agree', type : 'check', label : 'Agree', required : true },
			{ key : 'parts', type : 'checklist', label : 'Parts', required : true, options : [ { value : 'a', label : 'A' }, { value : 'b', label : 'B' } ] },
			{ key : 'title', type : 'locales', label : 'Title', required : true, locales : [ 'de_DE', 'en_US' ], work : 'de_DE' },
			{ key : 'logo', type : 'file', label : 'Logo', required : true, accept : 'image/*' },
			{ key : 'css', type : 'code', label : 'CSS', value : 'a {}' },
			{ key : 'when', type : 'datetime', label : 'When', value : '2026-11-17T06:02' },
		],
		onSubmit : ( values, ctl ) => sent.push( { values : values, ctl : ctl } ),
	} );

	const dialog = w.dialogs[0];
	const row = findAll( dialog, el => el.className === 'nino-admin-dialog-actions' )[0].children;
	const boxes = tag( dialog, 'input' ).filter( el => el.type === 'checkbox' );
	const inputs = tag( dialog, 'input' );

	fire( row[1], 'click' );
	const refused = tag( dialog, 'p' ).filter( p => p.className === 'nino-admin-field-error' );
	check( 'a form takes a tick, a list of ticks, the languages, a file, code and a moment: with every required one empty it sends nothing and says it at four fields', sent.length === 0 && refused.length === 4 && dialog.open === true );

	boxes[0].checked = true;
	boxes[1].checked = true;
	boxes[2].checked = false;
	inputs.filter( el => el.dataset.locale === 'de_DE' )[0].value = 'Titel';
	fire( boxes[0], 'change' );
	fire( row[1], 'click' );
	check( '...a language that is left empty keeps a required row of the languages open as well', sent.length === 0 && tag( dialog, 'p' ).filter( p => p.className === 'nino-admin-field-error' ).length === 2 );

	inputs.filter( el => el.dataset.locale === 'en_US' )[0].value = 'Title';
	const picker = inputs.filter( el => el.type === 'file' )[0];
	picker.files = [ file ];
	fire( picker, 'change' );
	fire( row[1], 'click' );
	check( '...and with all of them filled it sends one value for each, of the kind the field gives', sent.length === 1 && sent[0].values.agree === true && sent[0].values.parts.join() === 'a' && sent[0].values.title.de_DE === 'Titel' && sent[0].values.title.en_US === 'Title'
		&& sent[0].values.logo === file && sent[0].values.css === 'a {}' && sent[0].values.when === '2026-11-17T06:02' );
	sent[0].ctl.close();
}

{
	const w = world();
	w.ui.formDialog( { title : 'Pick', submit : 'Pick', cancel : 'Cancel', fields : [ { key : 'parts', type : 'checklist', label : 'Parts', options : [ { value : 'a', label : 'A' } ], value : [] } ], onSubmit : function() {} } );
	const dialog = w.dialogs[0];
	const box = tag( dialog, 'input' ).filter( el => el.type === 'checkbox' )[0];
	box.checked = true;
	fire( box, 'change' );
	check( 'a change in a list of ticks is a change of the form: Escape asks before it throws it away', escape( dialog ) === true && w.dialogs.length === 2 );
	w.dialogs[1].close();
	box.checked = false;
	fire( box, 'change' );
	check( '...and ticked back it is none', escape( dialog ) === false );
}


// --- confirm(): what the question is about ------------------------------------------

{
	const w = world();
	w.ui.confirm( 'destructive', asked( { details : [ 'Page /about', 'Page <b>/team</b>', { title : 'Kept', lines : [ 'Its texts', 'and 3 more' ] }, '', { title : 'Empty', lines : [] } ] } ) );
	const dialog = w.dialogs[0];
	const lists = tag( dialog, 'ul' );
	const items = tag( dialog, 'li' );

	check( 'a question that names what it is about draws it as lists under the message: the lines that stand alone are one list, a group has a title and a list of its own', lists.length === 2 && items.map( li => li.textContent ).join('|') === 'Page /about|Page <b>/team</b>|Its texts|and 3 more'
		&& findAll( dialog, el => el.className === 'nino-admin-dialog-details-title' ).map( el => el.textContent ).join() === 'Kept' );
	check( '...every line is text, a line that spells markup is no element, and an empty line or group is not drawn', items.every( li => li.children.length === 0 ) && tag( dialog, 'b' ).length === 0 );
	check( '...the dialog is described by it as it is by the message', String( dialog.attributes['aria-describedby'] ).indexOf( findAll( dialog, el => el.className === 'nino-admin-dialog-details' )[0].id ) !== -1 );
	dialog.close();

	const plain = world();
	plain.ui.confirm( 'destructive', asked() );
	check( 'a question that names nothing has no list', tag( plain.dialogs[0], 'ul' ).length === 0 );

	const typed = world();
	typed.ui.confirm( 'destructive', asked( { typed : 'legal', details : [ 'Page /about' ] } ) );
	check( 'a question with a word to type has the list too', tag( typed.dialogs[0], 'li' ).length === 1 );

	// A browser without <dialog> asks with its own question: the lines are part of it
	let said = '';
	const old = world( undefined, { noDialog : true, confirm : function( text ) { said = text; return true } } );
	old.ui.confirm( 'destructive', asked( { details : [ 'Page /about' ] } ) );
	check( 'where there is no <dialog> the browser\'s own question carries the lines', said.indexOf('Page /about') !== -1 );
}


// --- choiceDialog(): dismiss - a press on the surface behind a modal dialog ---------

{
	const w = world();
	const answers = [];
	const opener = element('button');
	const open = ( extra ) => {
		w.ui.choiceDialog( Object.assign( { message : 'x', opener : opener, choices : [ { value : 'cancel', label : 'Cancel', kind : 'secondary' }, { value : 'ok', label : 'Ok', kind : 'primary' } ], onChoose : function( value ) { answers.push( value ) } }, extra ) );
		return last( w );
	};
	const surface = ( dialog, down, up ) => { fire( dialog, 'pointerdown', { target : down } ); fire( dialog, 'pointerup', { target : up } ) };

	let dialog = open( {} );
	surface( dialog, dialog, dialog );
	check( 'without dismiss a press on the surface does nothing - a question is closed by an answer', dialog.open === true && answers.length === 0 );
	dialog.close();
	answers.length = 0;

	dialog = open( { dismiss : true } );
	const inside = buttons( dialog )[0];
	surface( dialog, inside, dialog );
	surface( dialog, dialog, inside );
	check( 'with dismiss a press that starts or ends inside the dialog does not close it - a selection dragged out of a field', dialog.open === true && answers.length === 0 );

	surface( dialog, dialog, dialog );
	check( '...one that starts and ends on the surface closes it as its secondary answer, and the focus goes back to what had it', dialog.open === false && answers.join() === 'cancel' && opener.focused === true );

	let kept = 0;
	dialog = open( { dismiss : true, guard : function() { kept++; return false } } );
	surface( dialog, dialog, dialog );
	check( '...the guard is asked, and keeps the dialog open as it keeps it from the Cancel button', kept === 1 && dialog.open === true );
	dialog.close();

	const below = open( { dismiss : true } );
	const above = open( { dismiss : true, nested : true } );
	surface( below, below, below );
	check( '...a dialog that is not the top one is not closed by it', below.open === true && above.open === true );
	surface( above, above, above );
	check( '...the top one is', above.open === false && below.open === true );
	below.close();

	const form = world();
	form.ui.formDialog( { title : 'Make', fields : [ { key : 'name', label : 'Name', type : 'text', value : '' } ], submit : 'Make', cancel : 'Cancel', dismiss : true, onSubmit : function() {} } );
	const made = last( form );
	fire( made, 'pointerdown', { target : made } );
	fire( made, 'pointerup', { target : made } );
	check( 'formDialog() takes no dismiss: a press on the surface never closes a form', made.open === true );
}


// --- confirm(): a question asked of an open dialog --------------------------------

{
	const w = world();
	Object.defineProperty( w.doc, 'activeElement', { get : () => findAll( w.body, el => el.focused === true )[0] ?? null, configurable : true } );

	w.ui.formDialog( { title : 'Make', fields : [ { key : 'name', label : 'Name', type : 'text', value : '' } ], submit : 'Make', cancel : 'Cancel', onSubmit : function() {} } );
	const below = w.dialogs[0];
	const field = findAll( below, el => el.tagName === 'INPUT' )[0];
	field.focus();

	const answers = [];
	const asked = w.ui.confirm( 'destructive', Object.assign( { title : 'Throw away', message : 'Sure?', yes : 'Throw away', no : 'Keep', onChoose : function( ok ) { answers.push( ok ) } } ) );

	check( 'a question without nested is not asked of an open dialog, as before', asked === false && w.dialogs.length === 1 );

	const nested = w.ui.confirm( 'destructive', { title : 'Throw away', message : 'Sure?', yes : 'Throw away', no : 'Keep', nested : true, onChoose : function( ok ) { answers.push( ok ) } } );
	const above = w.dialogs[1];

	check( 'with nested it is asked on the open dialog, which stays open and is not given the focus', nested === true && w.dialogs.length === 2 && above.open === true && below.open === true );

	escape( above );
	check( 'Escape closes the question alone, answers No, and the focus is back in the dialog below, on what had it', above.open === false && below.open === true && answers.join() === 'false' && field.focused === true );
}


// --- choiceDialog(): the Tab key stays in a modal ----------------------------------

{
	const w = world();
	const behind = element('main');
	const toasts = element('div');
	toasts.className = 'nino-admin-toasts';
	w.body.appendChild( behind );
	w.body.appendChild( toasts );

	Object.defineProperty( w.doc, 'activeElement', { get : () => findAll( w.body, el => el.focused === true )[0] ?? null, configurable : true } );

	w.ui.confirm( 'big', asked() );
	const dialog = w.dialogs[0];
	const row = buttons( dialog );
	const tab = ( shift ) => {
		let prevented = false;
		fire( dialog, 'keydown', { key : 'Tab', shiftKey : shift, preventDefault : function() { prevented = true } } );
		return prevented;
	};

	check( 'while a modal dialog is open the rest of the page is inert, apart from the toast, which is heard', behind.inert === true && toasts.inert !== true && dialog.inert !== true );

	row[1].focus();
	check( 'Tab at the last control goes to the first, and the browser is not left to move it out', tab( false ) === true && row[0].focused === true );
	check( '...Shift+Tab at the first goes to the last', tab( true ) === true && row[1].focused === true );
	row[0].focus();
	check( '...Tab in between is the browser\'s, which moves on by itself', tab( false ) === false && tab( true ) === true );

	w.ui.choiceDialog( { title : 'Second', message : 'Sure?', nested : true, choices : [ { value : 'no', label : 'No', kind : 'secondary' } ], onChoose : function() {} } );
	check( 'a dialog on a dialog makes the one below inert, and gives it back when it closes', w.dialogs[0].inert === true && ( w.dialogs[1].close(), w.dialogs[0].inert === false ) && behind.inert === true );

	dialog.close();
	check( 'the page is given back when the dialog closes', behind.inert === false );
}


done();
