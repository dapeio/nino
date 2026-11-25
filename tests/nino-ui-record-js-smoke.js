/**
 *	Nino										A compact filesystembased php framework
 *	nino-ui-record-js-smoke.js	DOM-light checks for the dialog of a record:
 *															Nino.adminUi.record() over the standard directory, the
 *															real router of the shell and the real registry of
 *															unsaved input (adminPage() of the harness) - and the
 *															dialog it stands on: size, subtitle, cross, bar.
 *
 *															Pinned: the address #panel/tab/<entry> (one entry of the
 *															history for a record that a person opened, however far
 *															they step; none for one an address opened; Back closes
 *															it, the cross takes the entry off the history again),
 *															an entry the directory hides or that is on another page,
 *															an entry that is not there; the order of the stepping
 *															across the pages and by the sort and the selects; the bar
 *															(the destructive action at the left, one main button at
 *															the right, no Cancel); the questions - closing, stepping,
 *															Back, a typed address - that are asked of unsaved input
 *															and that Cancel and Discard answer; save, save and
 *															continue, a refused save, a conflict; the deletion and the
 *															focus after it; the focus on opening and on closing; that
 *															what an entry holds is never set as markup; that the buttons follow the entry after a
 *															save, that an action gets its button as
 *															opener, and that a form opens over a record and one after
 *															the other.
 *
 *	Usage: node tests/nino-ui-record-js-smoke.js
 */

'use strict';

const { check, done, adminPage, element, findAll, fire, markupWrites } = require( './harness.js' );

const hasClass = ( el, name ) => String( el.className ).split(/\s+/).indexOf( name ) !== -1;
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );
const byClass = ( root, name ) => findAll( root, el => hasClass( el, name ) );

const ROWS = [];
for( let i = 1; i <= 60; i++ )
	ROWS.push( { key : 'r'+ String( i ).padStart( 2, '0' ), name : 'Item '+ i, group : i % 3 === 0 ? 'b' : 'a', thumb : '/images/'+ i+ '.jpg' } );

const COLUMNS = [ { key : 'thumb', label : 'Preview', cell : 'thumb' }, { key : 'name', label : 'Name', type : 'string' } ];

/**
 *	A page with a directory of the rows and a record over it. `edit` makes the record a form with a field, a save and "save and continue"
 */
function setup( edit, extra ) {

	const h = adminPage();
	const calls = { save : [], remove : [], missing : [], reload : [] };
	const mount = element('div');
	let rows = ROWS.slice();

	h.page.appendChild( mount );
	h.show('submissions');
	h.router.start();
	// The directory has its address, as the shell wrote it when the panel was shown
	h.router.set( 'submissions', [] );

	const directory = h.ui.directory( {
		mount : mount, columns : COLUMNS, rowKey : 'key', labels : { search : 'Search', empty : 'none', noMatch : 'no match' },
		category : { id : 'cat', label : 'Group', value : '', options : [ { value : '', label : 'All' }, { value : 'a', label : 'A' }, { value : 'b', label : 'B' } ], match : ( row, value ) => value === '' || row.group === value },
		onOpen : row => record.open( row.key, {} ),
	} );

	directory.setRows( rows );

	const options = Object.assign( {
		screen : 'submissions',
		readOnly : edit !== true,
		directory : directory,
		keyOf : row => row.key,
		find : key => rows.find( row => row.key === key ) ?? null,
		title : row => row.name,
		subtitle : row => '/things/'+ row.key,
		labels : { position : '%s of %s', close : 'Close', prev : 'Previous', next : 'Next', save : 'Save', saveNext : 'Save and continue', closeButton : 'Close', deleted : 'Deleted.', saved : 'Saved.' },
		body : function( row ) {
			const wrap = element('div');
			wrap.appendChild( edit === true ? h.ui.textField( { label : 'Name', value : row.name, key : 'name' } ) : h.ui.readLine( { label : 'Name', value : row.name } ) );
			return wrap;
		},
		missing : key => calls.missing.push( key ),
		reload : ( key, finished ) => { calls.reload.push( key ); finished( rows.find( row => row.key === key ) ?? null ) },
		danger : { label : 'Delete', run : ( row, opener, finished ) => { calls.remove.push( row.key ); if( calls.removeAnswer === undefined ) { rows = rows.filter( r => r !== row ); directory.setRows( rows, { keep : true } ) } finished( calls.removeAnswer === undefined ? true : calls.removeAnswer, { error : 'It failed.' } ) } },
	}, extra ?? {} );

	if( edit === true )
		Object.assign( options, {
			saveNext : true,
			save : function( row, ctx, finished ) {
				calls.save.push( { key : row.key, value : tag( ctx.form, 'input' )[0].value, overwrite : ctx.overwrite } );
				calls.answer( finished, row, ctx );
			},
		} );

	calls.answer = function( finished, row, ctx ) {
		const renamed = Object.assign( {}, row, { name : tag( ctx.form, 'input' )[0].value } );
		rows = rows.map( r => r.key === row.key ? renamed : r );
		directory.update( rows, row.key );
		finished( true, { row : renamed } );
	};

	const record = h.ui.record( options );

	const dialog = () => h.dialogs.filter( d => d.open === true ).pop() ?? null;
	const first = () => h.dialogs.filter( d => d.open === true )[0] ?? null;

	return { h, record, directory, calls, mount, dialog, first, rows : () => rows };
}

/** The Escape key as a dialog with a guard has it: the keydown it takes itself and, where nobody prevented it, the cancel event and the close */
function escape( dialog ) {
	let prevented = false;
	fire( dialog, 'keydown', { key : 'Escape', preventDefault : function() { prevented = true } } );
	if( prevented === false ) {
		fire( dialog, 'cancel', { preventDefault : function() { prevented = true } } );
		if( prevented === false )
			dialog.close();
	}
}

const buttonOf = ( dialog, text ) => tag( dialog, 'button' ).find( b => b.textContent === text || b.attributes['aria-label'] === text );
const titleOf = dialog => byClass( dialog, 'nino-admin-dialog-title' )[0].textContent;
const countOf = dialog => byClass( dialog, 'nino-admin-record-count' )[0].textContent;
const rowEl = ( s, key ) => findAll( s.mount, el => el.tagName === 'TR' && el.dataset.row === key )[0];
const typeInto = ( dialog, value ) => { const input = tag( dialog, 'input' )[0]; input.value = value; fire( input, 'input' ); fire( input, 'change' ) };

console.log('Nino.adminUi.record - the dialog of one entry of a directory\n');


// --- the dialog ------------------------------------------------------------------

{
	const s = setup( true );
	check( 'a record is opened by the call of the panel, and no dialog is open before', s.dialog() === null && s.record.isOpen() === false );
	s.record.open( 'r03', {} );
	const d = s.dialog();

	check( 'it is the large dialog of the workbench, and a dialog of its own', d !== null && hasClass( d, 'nino-admin-dialog--large' ) && hasClass( d, 'nino-admin-record' ) && d.modal === true );
	check( 'its title is the name of the entry and its subtitle the identifier, both as the caller said them', titleOf( d ) === 'Item 3' && byClass( d, 'nino-admin-dialog-subtitle' )[0].textContent === '/things/r03' );
	check( 'its head has the step to the one before and the one after, with the place in the rows, and the cross that closes it - each a button with a name',
		countOf( d ) === '3 of 60' && buttonOf( d, 'Previous' ) !== undefined && buttonOf( d, 'Next' ) !== undefined && hasClass( buttonOf( d, 'Close' ), 'nino-admin-dialog-close' ) );
	check( 'the steps say the keys they answer to', buttonOf( d, 'Previous' ).attributes['aria-keyshortcuts'] === 'Alt+Shift+ArrowLeft' && buttonOf( d, 'Next' ).attributes['aria-keyshortcuts'] === 'Alt+Shift+ArrowRight' );

	const bar = byClass( d, 'nino-admin-actionbar' )[0];
	const kinds = bar.children.filter( el => el.tagName === 'BUTTON' ).map( el => el.textContent );
	check( 'the bar is the bar of the workbench: the destructive action first, the state, then the buttons, and the one main button last',
		bar.children[0].textContent === 'Delete' && hasClass( bar.children[0], 'nino-admin-btn-danger' ) && kinds.join() === 'Delete,Save and continue,Save' && hasClass( bar.children[bar.children.length - 1], 'nino-admin-btn-primary' )
		&& bar.children.filter( el => hasClass( el, 'nino-admin-btn-primary' ) ).length === 1 );
	check( 'there is no Cancel in the bar - the cross and Escape close the record', buttonOf( bar, 'Cancel' ) === undefined && byClass( bar, 'nino-admin-actionbar-state' ).length === 1 );
	check( 'the dialog has no row of answers of its own: its bar is the one at the foot', byClass( d, 'nino-admin-dialog-actions' ).length === 0 );
	check( 'the focus is in the first field of a form', tag( d, 'input' )[0].focused === true );

	const ro = setup( false );
	ro.record.open( 'r03', {} );
	const rd = ro.dialog();
	const rbar = byClass( rd, 'nino-admin-actionbar' )[0];
	check( 'a record that is only read has no state in its bar, the destructive action at the left and no Close, no Cancel and no main button: the cross and Escape close it',
		byClass( rbar, 'nino-admin-actionbar-state' ).length === 0 && rbar.children.map( el => el.textContent ).join() === 'Delete' && byClass( rbar, 'nino-admin-btn-primary' ).length === 0 && buttonOf( rbar, 'Close' ) === undefined );
	check( '...and the focus is on its head', byClass( rd, 'nino-admin-dialog-title' )[0].focused === true );
	fire( buttonOf( rd, 'Close' ), 'click' );
	check( '...the cross closes it, and the focus goes back to the row it was opened from', ro.dialog() === null && rowEl( ro, 'r03' ).focused === true );
}


// --- the address -------------------------------------------------------------------

{
	const s = setup( true );
	const before = s.h.entries.length;

	s.record.open( 'r03', {} );
	check( 'a person opens an entry: the address is #panel/tab/<entry> and it is one entry more in the history', s.h.hash() === '#submissions/submissions/r03' && s.h.entries.length === before + 1 );

	fire( buttonOf( s.dialog(), 'Next' ), 'click' );
	fire( buttonOf( s.dialog(), 'Next' ), 'click' );
	check( 'stepping from entry to entry writes over that entry: the history does not grow with each step', s.h.hash() === '#submissions/submissions/r05' && s.h.entries.length === before + 1 && titleOf( s.dialog() ) === 'Item 5' );
	fire( buttonOf( s.dialog(), 'Previous' ), 'click' );
	check( '...in both directions', s.h.hash() === '#submissions/submissions/r04' && titleOf( s.dialog() ) === 'Item 4' && countOf( s.dialog() ) === '4 of 60' );

	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( 'the cross closes it, and takes the one entry it made off the history again - by the history itself, so that it is one move with Back', s.dialog() === null && s.h.went.join() === '-1' );

	// What the browser's Back did: the address is the directory's and the panel follows it
	s.h.back();
	s.h.router.arrive();
	check( 'the step the cross asked of the history is answered once and reads nothing - the record is closed - and the next address of the panel is read as any other', s.h.hash() === '#submissions' && s.record.follow() === true && s.record.follow() === false && s.dialog() === null );
}

{
	const s = setup( true );
	s.record.open( 'r03', {} );
	s.h.back();
	s.h.router.arrive();
	const entries = s.h.entries.length;
	const went = s.h.went.length;

	check( 'Back closes the record that is open - the panel follows the address', s.record.follow() === true && s.dialog() === null && s.record.isOpen() === false );
	check( '...and writes nothing: no entry, no step through the history, the address stays the directory\'s', s.h.entries.length === entries && s.h.went.length === went && s.h.hash() === '#submissions' );
	check( '...and the focus goes to the row', rowEl( s, 'r03' ).focused === true );

	s.h.forward();
	s.h.router.arrive();
	check( 'Forward opens it again, as the address says', s.record.follow() === true && s.dialog() !== null && titleOf( s.dialog() ) === 'Item 3' );
}

{
	// An address typed or reloaded: the record has no entry of its own and is closed by writing the directory's address over its own
	const s = setup( true );
	s.h.box.location.hash = '#submissions/submissions/r07';
	const entries = s.h.entries.length;

	check( 'an address that names an entry opens it - on a reload, from a link', s.record.follow() === true && s.dialog() !== null && titleOf( s.dialog() ) === 'Item 7' );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( 'closed, the address is written over by the directory\'s and no step is taken through the history', s.h.hash() === '#submissions' && s.h.went.length === 0 && s.h.entries.length === entries );
}

{
	// An entry on another page, one the select hides, and one that is not there
	const s = setup( true );

	check( 'the first page holds 25 entries, the pictures being what a row shows', s.directory.rows().length === 60 && s.mount.querySelectorAll('tr').filter( el => el.dataset.row !== undefined ).length === 25 );
	s.h.box.location.hash = '#submissions/submissions/r58';
	s.record.follow();
	check( 'an entry that stands on the third page opens, and the directory shows that page behind it', s.dialog() !== null && s.directory.state().page === 3 && rowEl( s, 'r58' ) !== undefined );
	s.record.close();

	const select = tag( s.mount, 'select' )[0];
	select.value = 'b';
	fire( select, 'change' );
	s.h.box.location.hash = '#submissions/submissions/r01';
	s.record.follow();
	check( 'an entry that the select hides opens all the same', s.dialog() !== null && titleOf( s.dialog() ) === 'Item 1' );
	check( '...it has no place among the rows shown, so it says none and offers no step', countOf( s.dialog() ) === '' && buttonOf( s.dialog(), 'Next' ).hidden === true && buttonOf( s.dialog(), 'Previous' ).hidden === true );
	s.record.close();

	s.h.box.location.hash = '#submissions/submissions/nothing';
	const answered = s.record.follow();
	check( 'an entry that is not there leaves the directory, tells the panel which, and writes the directory\'s address', answered === true && s.dialog() === null && s.calls.missing.join() === 'nothing' && s.h.hash() === '#submissions' );
	check( 'open() of such an entry says no', s.record.open( 'nothing', {} ) === false && s.calls.missing.join() === 'nothing,nothing' );
}

{
	// A key is one part of the address, whatever it holds
	const s = setup( true );
	const odd = 'a/b c%d<>@new';

	s.rows().push( { key : odd, name : '<img src=x onerror=alert(1)>', group : 'a', thumb : '' } );
	s.directory.setRows( s.rows() );
	const markup = markupWrites.length;

	s.record.open( odd, {} );
	const hash = s.h.hash();
	check( 'a key that holds a slash, a space, a percent sign, markup and an at sign is one segment of the address', hash.split('/').length === 3 && s.h.router.current().parts.length === 1 && s.h.router.current().parts[0] === odd && s.h.router.current().parts.words[0] === false );
	check( 'a name that spells markup is shown as the letters it is - in the title and in the field', titleOf( s.dialog() ) === '<img src=x onerror=alert(1)>' && tag( s.dialog(), 'img' ).length === 0 && markupWrites.length === markup );
}


// --- the order of the stepping ----------------------------------------------------------

{
	const s = setup( true );

	s.record.open( 'r25', {} );
	fire( buttonOf( s.dialog(), 'Next' ), 'click' );
	check( 'a step over the end of a page goes on to the next page, and the directory behind shows it', titleOf( s.dialog() ) === 'Item 26' && s.directory.state().page === 2 && rowEl( s, 'r26' ) !== undefined );
	fire( buttonOf( s.dialog(), 'Previous' ), 'click' );
	check( '...and back', titleOf( s.dialog() ) === 'Item 25' && s.directory.state().page === 1 );
	s.record.close();

	// By the select
	const select = tag( s.mount, 'select' )[0];
	select.value = 'b';
	fire( select, 'change' );
	s.record.open( 'r03', {} );
	check( 'the place is in the rows the directory shows now - the select leaves 20 of the 60', countOf( s.dialog() ) === '1 of 20' );
	fire( buttonOf( s.dialog(), 'Next' ), 'click' );
	check( '...and a step is to the next of those', titleOf( s.dialog() ) === 'Item 6' );
	check( 'at the first the step back is not offered, as at the last the step on is not', buttonOf( s.dialog(), 'Previous' ).disabled === false );
	s.record.close();

	// By the sort
	select.value = '';
	fire( select, 'change' );
	const head = findAll( s.mount, el => el.tagName === 'TH' && el.dataset.key === 'name' )[0];
	fire( tag( head, 'button' )[0], 'click' );
	fire( tag( head, 'button' )[0], 'click' );
	s.record.open( s.directory.rows()[0].key, {} );
	check( 'the order is the order of the sort: a table sorted downwards is walked downwards', countOf( s.dialog() ) === '1 of 60' && titleOf( s.dialog() ) === s.directory.rows()[0].name );
	check( '...and a step back from the first is not there', buttonOf( s.dialog(), 'Previous' ).disabled === true );
	s.record.close();

	s.record.open( s.directory.rows()[59].key, {} );
	check( '...nor a step on from the last', buttonOf( s.dialog(), 'Next' ).disabled === true && countOf( s.dialog() ) === '60 of 60' );

	// By the key
	fire( s.dialog(), 'keydown', { key : 'ArrowLeft', altKey : true, shiftKey : true } );
	check( 'Alt+Shift+Left is the step back, Alt+Shift+Right the step on', countOf( s.dialog() ) === '59 of 60' );
	fire( s.dialog(), 'keydown', { key : 'ArrowRight', altKey : true, shiftKey : true } );
	check( '...', countOf( s.dialog() ) === '60 of 60' );
	fire( s.dialog(), 'keydown', { key : 'ArrowRight', shiftKey : true } );
	check( 'the key alone does nothing - Alt is needed too', countOf( s.dialog() ) === '60 of 60' );
	fire( s.dialog(), 'keydown', { key : 'ArrowLeft', altKey : true } );
	check( '...and Alt alone is the browser\'s own Back, which a record leaves to it', countOf( s.dialog() ) === '60 of 60' );
}


// --- Escape ---------------------------------------------------------------------------

{
	const s = setup( true );

	s.record.open( 'r03', {} );
	escape( s.dialog() );
	check( 'Escape closes the record, the way the cross does - the history takes the entry off', s.dialog() === null && s.h.went.join() === '-1' && rowEl( s, 'r03' ).focused === true );
}


// --- unsaved input ---------------------------------------------------------------------

{
	const s = setup( true );

	s.record.open( 'r03', {} );
	check( 'a record that is as it was saved is no unsaved input', s.h.dirty.isDirty( [ 'submissions' ] ) === false );
	typeInto( s.dialog(), 'Changed' );
	check( 'a field that was typed in is: the registry of the shell knows it, and the bar says so', s.h.dirty.isDirty( [ 'submissions' ] ) === true );
	s.h.dirty.refresh();
	check( '...as "changed"', byClass( s.dialog(), 'nino-admin-actionbar-state' )[0].dataset.state === 'warn' );

	// The cross
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	const question = s.dialog();
	check( 'the cross asks first, on top of the record: Discard, Cancel and Save, in that order', question !== s.first() && s.h.dialogs.filter( d => d.open ).length === 2
		&& tag( question, 'button' ).map( b => b.textContent ).join() === '/_admin/common/label/discard,/_admin/common/label/cancel,/_admin/common/label/save' );
	fire( tag( question, 'button' )[1], 'click' );
	check( 'Cancel leaves the record open, with what was typed and the address it had', s.dialog() === s.first() && tag( s.first(), 'input' )[0].value === 'Changed' && s.h.hash() === '#submissions/submissions/r03' && s.h.went.length === 0 );

	// Stepping
	fire( buttonOf( s.dialog(), 'Next' ), 'click' );
	check( 'a step asks as well', s.h.dialogs.filter( d => d.open ).length === 2 );
	fire( tag( s.dialog(), 'button' )[1], 'click' );
	check( '...and on Cancel stays where it is', titleOf( s.first() ) === 'Item 3' && tag( s.first(), 'input' )[0].value === 'Changed' && s.h.hash() === '#submissions/submissions/r03' );
	fire( buttonOf( s.dialog(), 'Next' ), 'click' );
	fire( tag( s.dialog(), 'button' )[0], 'click' );
	check( 'Discard goes on to the step, with what was typed given up', titleOf( s.first() ) === 'Item 4' && tag( s.first(), 'input' )[0].value === 'Item 4' && s.h.hash() === '#submissions/submissions/r04' && s.h.dirty.isDirty( [ 'submissions' ] ) === false );

	// Closing with Discard
	typeInto( s.dialog(), 'Again' );
	escape( s.dialog() );
	check( 'Escape asks as well', s.h.dialogs.filter( d => d.open ).length === 2 );
	fire( tag( s.dialog(), 'button' )[0], 'click' );
	check( 'Discard closes the record, and the address is the directory\'s', s.dialog() === null && s.calls.save.length === 0 && s.h.went.join() === '-1' );

	// Save, as the answer
	const t = setup( true );
	t.record.open( 'r03', {} );
	typeInto( t.dialog(), 'Saved name' );
	fire( buttonOf( t.dialog(), 'Close' ), 'click' );
	fire( tag( t.dialog(), 'button' )[2], 'click' );
	check( 'Save saves, and then the record closes', t.calls.save.length === 1 && t.calls.save[0].value === 'Saved name' && t.dialog() === null );
	check( '...the row in the directory is the one that was changed, drawn anew and fresh', hasClass( rowEl( t, 'r03' ), 'is-fresh' ) && findAll( rowEl( t, 'r03' ), el => el.textContent === 'Saved name' ).length === 1 );
	check( '...and the focus is on it', rowEl( t, 'r03' ).focused === true );

	// A save that is refused ends the question and the move
	const u = setup( true );
	u.calls.answer = ( finished ) => finished( false, { error : 'The name is taken.' } );
	u.record.open( 'r03', {} );
	typeInto( u.dialog(), 'x' );
	fire( buttonOf( u.dialog(), 'Close' ), 'click' );
	fire( tag( u.dialog(), 'button' )[2], 'click' );
	check( 'a Save that is refused leaves the record open, as it was, and says why in the line of the bar', u.first() !== null && u.first().open === true && u.record.isOpen() === true && byClass( u.first(), 'nino-admin-status' )[0].textContent === 'The name is taken.' && u.h.dirty.isDirty( [ 'submissions' ] ) === true );
}

{
	// Back and a typed address ask too, and Cancel puts the address back
	const s = setup( true );
	s.record.open( 'r03', {} );
	typeInto( s.dialog(), 'Changed' );
	s.h.back();
	s.h.router.arrive();
	const answered = s.record.follow();
	check( 'Back asks before it closes a record that holds input', answered === true && s.first().open === true && s.h.dialogs.filter( d => d.open ).length === 2 );
	fire( tag( s.dialog(), 'button' )[1], 'click' );
	check( '...Cancel keeps it, and takes the step through the history back', s.first().open === true && s.h.went.join() === '1' && tag( s.first(), 'input' )[0].value === 'Changed' );

	const t = setup( true );
	t.record.open( 'r03', {} );
	typeInto( t.dialog(), 'Changed' );
	t.h.box.location.hash = '#submissions/submissions/r09';
	t.h.router.arrive();
	t.record.follow();
	check( 'an address typed that names another entry asks as well', t.h.dialogs.filter( d => d.open ).length === 2 );
	fire( tag( t.dialog(), 'button' )[0], 'click' );
	check( '...and Discard shows it, the dialog staying one', titleOf( t.first() ) === 'Item 9' && t.h.dialogs.filter( d => d.open ).length === 1 );

	// The shell's own Discard - the panel is left by an address - takes the record away
	const u = setup( true );
	u.record.open( 'r03', {} );
	typeInto( u.dialog(), 'Changed' );
	u.h.dirty.guard( [ 'submissions' ], function() {}, function() {} );
	fire( tag( u.dialog(), 'button' )[0], 'click' );
	check( 'where the shell asks and the answer is Discard - another panel is on its way - the record is gone with what it held', u.record.isOpen() === false && u.first() === null );
}


// --- saving ----------------------------------------------------------------------------

{
	const s = setup( true );

	s.record.open( 'r03', {} );
	typeInto( s.dialog(), 'Renamed' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );

	check( 'Save sends the row and its form to the caller', s.calls.save.length === 1 && s.calls.save[0].key === 'r03' && s.calls.save[0].value === 'Renamed' && s.calls.save[0].overwrite === false );
	check( '...and the view stays as it is: open, at its address, with the new name, saved', s.dialog() !== null && titleOf( s.dialog() ) === 'Renamed' && s.h.hash() === '#submissions/submissions/r03' && byClass( s.dialog(), 'nino-admin-actionbar-state' )[0].dataset.state === 'ok' && s.h.dirty.isDirty( [ 'submissions' ] ) === false );
	check( '...and the line of the bar is empty: a success is said by the badge and the toast', byClass( s.dialog(), 'nino-admin-status' )[0].textContent === '' );

	typeInto( s.dialog(), 'Next name' );
	fire( buttonOf( s.dialog(), 'Save and continue' ), 'click' );
	check( 'Save and continue saves and shows the next entry of the rows', s.calls.save.length === 2 && titleOf( s.dialog() ) === 'Item 4' && s.h.hash() === '#submissions/submissions/r04' );

	{
		// A save that takes the entry out of the rows the directory shows - a select narrows them, and the saved entry no longer fits it - still goes on to the entry that followed
		const gone = setup( true );
		const narrow = tag( gone.mount, 'select' ).find( select => select.id === 'cat' );
		narrow.value = 'a';
		fire( narrow, 'change' );
		gone.record.open( 'r02', {} );
		gone.calls.answer = function( finished, row ) {
			const moved = Object.assign( {}, row, { group : 'b' } );
			gone.rows().splice( gone.rows().indexOf( row ), 1, moved );
			gone.directory.update( gone.rows(), row.key );
			finished( true, { row : moved } );
		};
		typeInto( gone.dialog(), 'Leaves the rows' );
		fire( buttonOf( gone.dialog(), 'Save and continue' ), 'click' );
		check( 'Save and continue goes on to the entry that followed where the save took the saved entry out of the rows that are shown', gone.calls.save.length === 1 && gone.dialog() !== null && titleOf( gone.dialog() ) === 'Item 4' && gone.h.hash() === '#submissions/submissions/r04' );

		const lone = setup( true );
		const narrower = tag( lone.mount, 'select' ).find( select => select.id === 'cat' );
		narrower.value = 'a';
		fire( narrower, 'change' );
		const last = lone.rows().filter( row => row.group === 'a' ).pop();
		lone.record.open( last.key, {} );
		lone.calls.answer = function( finished, row ) {
			const moved = Object.assign( {}, row, { group : 'b' } );
			lone.rows().splice( lone.rows().indexOf( row ), 1, moved );
			lone.directory.update( lone.rows(), row.key );
			finished( true, { row : moved } );
		};
		typeInto( lone.dialog(), 'The last one leaves' );
		fire( buttonOf( lone.dialog(), 'Save and continue' ), 'click' );
		check( '...and stays on the saved entry where none followed it', lone.calls.save.length === 1 && lone.dialog() !== null && titleOf( lone.dialog() ) === last.name && lone.h.hash() === '#submissions/submissions/'+ last.key );
	}

	const t = setup( true );
	t.calls.answer = ( finished ) => finished( false, { error : 'Not saved.' } );
	t.record.open( 'r03', {} );
	typeInto( t.dialog(), 'Nope' );
	fire( buttonOf( t.dialog(), 'Save and continue' ), 'click' );
	check( 'a refused Save and continue stays on the entry, with what was typed, and says why', titleOf( t.dialog() ) === 'Item 3' && tag( t.dialog(), 'input' )[0].value === 'Nope' && byClass( t.dialog(), 'nino-admin-status' )[0].dataset.state === 'error' && byClass( t.dialog(), 'nino-admin-status' )[0].textContent === 'Not saved.' );

	// Enter in a field is the main button
	const u = setup( true );
	u.record.open( 'r03', {} );
	typeInto( u.dialog(), 'By enter' );
	fire( byClass( u.dialog(), 'nino-admin-record-body' )[0], 'submit' );
	check( 'the form of a record sends with Enter: the same save as the main button', u.calls.save.length === 1 && u.calls.save[0].value === 'By enter' );
}

{
	// The entry was written elsewhere first
	const s = setup( true );
	s.calls.answer = ( finished, row, ctx ) => ctx.overwrite === true ? finished( true, { row : Object.assign( {}, row, { name : 'Mine' } ) } ) : finished( false, { conflict : true, error : 'It was changed by someone else.' } );
	s.record.open( 'r03', {} );
	typeInto( s.dialog(), 'Mine' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );

	const question = s.dialog();
	check( 'a save that was refused because another wrote first asks the question of the guide, on the record, with the sentence of the server', question !== s.first() && tag( question, 'button' ).map( b => b.textContent ).join() === '/_admin/common/conflict/reload,/_admin/common/label/cancel,/_admin/common/conflict/overwrite' );
	fire( tag( question, 'button' )[1], 'click' );
	check( 'Cancel leaves it all as it is', s.calls.save.length === 1 && s.first().open === true && tag( s.first(), 'input' )[0].value === 'Mine' );

	fire( buttonOf( s.dialog(), 'Save' ), 'click' );
	fire( tag( s.dialog(), 'button' )[2], 'click' );
	check( 'Overwrite saves again, and says so to the caller', s.calls.save.length === 3 && s.calls.save[2].overwrite === true && titleOf( s.first() ) === 'Mine' );

	s.calls.answer = ( finished, row ) => finished( false, { conflict : true, error : 'Again.' } );
	typeInto( s.first(), 'Third' );
	fire( buttonOf( s.first(), 'Save' ), 'click' );
	fire( tag( s.dialog(), 'button' )[0], 'click' );
	check( 'Reload asks the caller for the entry as it is now and shows it', s.calls.reload.join() === 'r03' && tag( s.first(), 'input' )[0].value === 'Item 3' && s.h.dirty.isDirty( [ 'submissions' ] ) === false );
}


// --- deleting ---------------------------------------------------------------------------

{
	const s = setup( false );

	s.record.open( 'r05', {} );
	fire( buttonOf( s.dialog(), 'Delete' ), 'click' );
	check( 'the destructive action is the caller\'s: it is asked with the row and the button that asked', s.calls.remove.join() === 'r05' );
	check( 'once it went, the record is gone and so are the entry and the row; the focus is on the row that took its place', s.dialog() === null && s.rows().length === 59 && rowEl( s, 'r05' ) === undefined && rowEl( s, 'r06' ).focused === true && s.h.went.join() === '-1' );

	const t = setup( false );
	t.calls.removeAnswer = null;
	t.record.open( 'r05', {} );
	fire( buttonOf( t.dialog(), 'Delete' ), 'click' );
	check( 'an answer of no - the question was declined - leaves it open and as it is', t.record.isOpen() === true && titleOf( t.dialog() ) === 'Item 5' );

	t.calls.removeAnswer = false;
	fire( buttonOf( t.dialog(), 'Delete' ), 'click' );
	check( 'a deletion that failed says why where the person looks: a record that is read has no line, and says it over what it shows',
		t.record.isOpen() === true && byClass( t.dialog(), 'nino-admin-notice' ).length === 1 && byClass( t.dialog(), 'nino-admin-notice' )[0].textContent === 'It failed.' && byClass( t.dialog(), 'nino-admin-notice' )[0].attributes.role === 'alert' );

	const u = setup( true );
	u.calls.removeAnswer = false;
	u.record.open( 'r05', {} );
	fire( buttonOf( u.dialog(), 'Delete' ), 'click' );
	check( '...a record that is edited says it in the line of its bar', byClass( u.dialog(), 'nino-admin-status' )[0].textContent === 'It failed.' );

	// The last one of the rows: nothing to go to, the focus goes to the search
	const v = setup( false );
	v.rows().splice( 1 );
	v.directory.setRows( v.rows() );
	v.record.open( 'r01', {} );
	fire( buttonOf( v.dialog(), 'Delete' ), 'click' );
	check( 'a deletion of the last row leaves the focus on the search of the directory', v.rows().length === 0 && tag( v.mount, 'input' ).find( el => el.type === 'search' ).focused === true );

	// An entry that cannot be taken away
	const w = setup( false, { danger : { label : 'Delete', hidden : row => row.key === 'r02', run : () => {} } } );
	w.record.open( 'r01', {} );
	check( 'a button for what cannot be taken away is not shown, and is shown for what can', buttonOf( w.dialog(), 'Delete' ).hidden === false );
	fire( buttonOf( w.dialog(), 'Next' ), 'click' );
	check( '...as the entries come and go', buttonOf( w.dialog(), 'Delete' ).hidden === true );
}


// --- what a record never does --------------------------------------------------------------

{
	const s = setup( false, { actions : [ { label : 'Reply', run : () => {} } ] } );
	s.record.open( 'r01', {} );
	const labelsOfBar = byClass( s.dialog(), 'nino-admin-actionbar' )[0].children.map( el => el.textContent );
	check( 'a further button stands at the right of the destructive action, and there is no Close', labelsOfBar.join() === 'Delete,Reply' );
	check( 'a record is never closed by a press beside it - the surface behind is no way out', s.dialog().listeners.pointerup === undefined );
}

// --- Round 2: what the review found --------------------------------------------------------

{
	// A record that is only read and shows no button has no bar: an empty strip at its foot says nothing
	const none = setup( false, { danger : undefined } );

	none.record.open( 'r01', {} );
	check( 'a record that is only read and has nothing to press shows no bar', byClass( none.dialog(), 'nino-admin-actionbar' )[0].hidden === true );

	const some = setup( false, { danger : { label : 'Delete', hidden : row => row.key === 'r02', run : () => {} }, actions : [ { label : 'Reply', hidden : row => row.key !== 'r03', run : () => {} } ] } );

	some.record.open( 'r01', {} );
	const strip = byClass( some.dialog(), 'nino-admin-actionbar' )[0];
	check( '...one with a button that is shown has its bar', strip.hidden === false && buttonOf( strip, 'Delete' ).hidden === false );
	fire( buttonOf( some.dialog(), 'Next' ), 'click' );
	check( '...the bar goes where the entry that is stepped to has no use for any of the buttons', strip.hidden === true );
	fire( buttonOf( some.dialog(), 'Next' ), 'click' );
	check( '...and is back with the entry that has: the bar follows the entry that is shown', strip.hidden === false && buttonOf( strip, 'Reply' ).hidden === false );

	const edited = setup( true, { danger : undefined } );

	edited.record.open( 'r01', {} );
	check( 'a record that is edited always has its bar: Save stands in it', byClass( edited.dialog(), 'nino-admin-actionbar' )[0].hidden !== true );
}

{
	// A record that is only read: what can be done with it at the right, nothing else; an action that does nothing for an entry has no button
	const s = setup( false, { actions : [ { label : 'Reply', kind : 'primary', hidden : row => row.key === 'r02', run : () => {} } ] } );

	s.record.open( 'r01', {} );
	const bar = byClass( s.dialog(), 'nino-admin-actionbar' )[0];
	check( 'the main button of a record that is read is what can be done with it: the destructive action first, that button last, and no state, no Close',
		bar.children.map( el => el.textContent ).join() === 'Delete,Reply' && hasClass( bar.children[1], 'nino-admin-btn-primary' ) && byClass( bar, 'nino-admin-actionbar-state' ).length === 0 );
	check( '...the destructive action is the first of the bar, and is shown', bar.children[0] === buttonOf( bar, 'Delete' ) && bar.children[1].hidden === false );
	fire( buttonOf( s.dialog(), 'Next' ), 'click' );
	check( '...and an entry that the action does nothing for has no button for it', buttonOf( bar, 'Reply' ).hidden === true );

	const t = setup( false, { actions : [] } );
	t.record.open( 'r01', {} );
	check( 'a record that is read and has nothing to be done with it has the destructive action in its bar and no main button', byClass( byClass( t.dialog(), 'nino-admin-actionbar' )[0], 'nino-admin-btn-primary' ).length === 0 );
}

{
	// The history: no entry left over after Back, Forward and the cross
	const s = setup( true );
	const entries = s.h.entries.length;

	s.record.open( 'r03', {} );
	s.h.back();
	s.h.router.arrive();
	s.record.follow();
	s.h.forward();
	s.h.router.arrive();
	check( 'Forward opens the record again, as the address says', s.record.follow() === true && s.dialog() !== null );
	check( '...and the entry is still the record\'s own, as it was before: marked, whatever way it came back', s.h.router.marked() === 'submissions' );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( 'the cross then takes the entry off the history, as the first time, and writes nothing over it: no two entries of the directory in a row', s.h.went.join() === '-1' && s.h.entries.length === entries + 1 && s.h.entries[s.h.entries.length - 1] !== '#submissions' );
	s.h.back();
	s.h.router.arrive();
	check( '...Back after that is a step to a different address, and the index of the router is the entry the browser is on', s.record.follow() === true && s.h.router.position() === s.h.index() && s.h.hash() === '#submissions' );

	// An entry that stepping went through is the same entry
	const t = setup( true );
	t.record.open( 'r03', {} );
	fire( buttonOf( t.dialog(), 'Next' ), 'click' );
	check( 'the mark stays on the entry when the address is written over by a step', t.h.router.marked() === 'submissions' && t.h.hash() === '#submissions/submissions/r04' );
	const mine = setup( true );
	mine.h.box.location.hash = '#submissions/submissions/r07';
	mine.record.follow();
	check( 'an entry that a record did not make - an address typed - has no mark, and the cross writes over it', mine.h.router.marked() === '' && ( fire( buttonOf( mine.dialog(), 'Close' ), 'click' ), mine.h.went.length === 0 && mine.h.hash() === '#submissions' ) );
}

{
	// An address that leads away
	const s = setup( false );

	s.record.open( 'r03', {} );
	s.h.box.location.hash = '#logs';
	s.h.hashchange();
	check( 'a record that holds nothing unsaved is closed when the address goes to another panel: it stands over no other', s.dialog() === null && s.record.isOpen() === false && s.h.hash() === '#logs' && s.h.went.length === 0 );

	const t = setup( true );
	t.record.open( 'r03', {} );
	typeInto( t.dialog(), 'Changed' );
	t.h.box.location.hash = '#logs';
	t.h.hashchange();
	check( 'one that does is left to the question of the shell: it stays until that is answered', t.dialog() !== null );
	check( '...and the panel, told that the address is not its own, asks nothing a second time', t.record.follow() === false && t.h.dialogs.filter( d => d.open ).length === 1 );

	const u = setup( true );
	u.record.open( 'r03', {} );
	typeInto( u.dialog(), 'Changed' );
	u.h.box.location.hash = '#logs';
	u.h.hashchange();
	u.h.dirty.guard( [ 'submissions' ], function() {}, function() {} );
	fire( tag( u.dialog(), 'button' )[2], 'click' );
	check( 'a Save that the shell asked for because the panel is left takes the record away with it', u.calls.save.length === 1 && u.record.isOpen() === false );
}

{
	// An entry with no address of its own
	const s = setup( true, { addressOf : row => row.key === 'r05' ? null : row.key } );
	const before = s.h.entries.length;

	s.record.open( 'r05', {} );
	check( 'an entry that has no address opens, and the address stays the directory\'s - no entry is added to the history', s.dialog() !== null && titleOf( s.dialog() ) === 'Item 5' && s.h.hash() === '#submissions' && s.h.entries.length === before );
	s.record.close();
	check( '...and closes without a step through the history', s.dialog() === null && s.h.went.length === 0 );

	s.h.box.location.hash = '#submissions/submissions/r05';
	check( 'an address cannot name it: the panel is told that the entry is not there', s.record.follow() === true && s.calls.missing.join() === 'r05' && s.dialog() === null );

	const t = setup( true, { addressOf : row => row.key === 'r05' ? null : row.key } );
	t.record.open( 'r04', {} );
	fire( buttonOf( t.dialog(), 'Next' ), 'click' );
	check( 'stepping to such an entry writes the address of the directory over that of the entry before', titleOf( t.dialog() ) === 'Item 5' && t.h.hash() === '#submissions' );
	fire( buttonOf( t.dialog(), 'Next' ), 'click' );
	check( '...and on to one that has an address writes that', t.h.hash() === '#submissions/submissions/r06' );
}

{
	// Where the focus stands when the next entry is shown
	const s = setup( true );

	s.record.open( 'r03', {} );
	const input = tag( s.dialog(), 'input' )[0];
	input.focus();
	fire( s.dialog(), 'keydown', { key : 'ArrowRight', altKey : true, shiftKey : true } );
	check( 'a step by the keyboard keeps the focus at the same place - the first field - of the next entry', titleOf( s.dialog() ) === 'Item 4' && tag( s.dialog(), 'input' )[0].focused === true );

	const r = setup( true, { body : function( row ) {
		const wrap = element('div');
		if( row.key !== 'r04' )
			wrap.appendChild( s.h.ui.textField( { label : 'Name', value : row.name, key : 'name' } ) );
		return wrap;
	} } );
	r.record.open( 'r03', {} );
	tag( r.dialog(), 'input' )[0].focus();
	fire( r.dialog(), 'keydown', { key : 'ArrowRight', altKey : true, shiftKey : true } );
	check( 'where the next entry has nothing at that place the focus is on the head of the record', titleOf( r.dialog() ) === 'Item 4' && byClass( r.dialog(), 'nino-admin-dialog-title' )[0].focused === true );

	check( 'the steps name their key in the tooltip - in the words of the interface language, from the fills - as well as in aria-keyshortcuts', buttonOf( s.dialog(), 'Next' ).title.indexOf( '/_admin/common/record/next-key' ) !== -1 && buttonOf( s.dialog(), 'Previous' ).title.indexOf( '/_admin/common/record/prev-key' ) !== -1 );
}

{
	// The question about unsaved input stands on a record and on nothing else
	const s = setup( false );
	let cancelled = 0;
	let proceeded = 0;

	s.h.dirty.register( 'other', { isDirty : () => true, save : done => done( true ), discard : () => {} } );
	s.h.ui.confirm( 'destructive', { title : 'Other', message : 'Other?', yes : 'Yes', no : 'No', onChoose : () => {} } );
	s.h.dirty.guard( [ 'other' ], () => { proceeded++ }, () => { cancelled++ } );
	check( 'over a dialog that is no record the question is asked as it was without the record: it is refused, nothing opens on top and the move is not made',
		s.h.dialogs.filter( d => d.open ).length === 1 && cancelled === 1 && proceeded === 0 );
}

// --- Round 3 --------------------------------------------------------------------------------

{
	// Back closes the top layer first: a question that stands on the record goes, the record stays, and the step is taken back
	const h = adminPage();
	const mount = element('div');
	const rows = ROWS.slice();

	h.page.appendChild( mount );
	h.show('submissions');
	h.router.start();
	h.router.set( 'submissions', [] );

	const directory = h.ui.directory( { mount : mount, columns : COLUMNS, rowKey : 'key', labels : { search : 'Search', empty : 'none', noMatch : 'no match' } } );
	directory.setRows( rows );

	const answers = [];
	const record = h.ui.record( {
		screen : 'submissions', readOnly : true, directory : directory, keyOf : row => row.key, find : key => rows.find( row => row.key === key ) ?? null, title : row => row.name, body : row => h.ui.readLine( { label : 'Name', value : row.name } ),
		missing : () => {},
		danger : { label : 'Delete', run : function( row, opener, finished ) {
			h.ui.confirm( 'destructive', { title : 'Delete', message : 'Sure?', yes : 'Delete', no : 'Cancel', nested : true, opener : opener, onChoose : ok => { answers.push( ok ); finished( ok === true ? true : null ) } } );
		} },
	} );

	record.open( 'r03', {} );
	fire( tag( byClass( h.dialogs.filter( d => d.open ).pop(), 'nino-admin-actionbar' )[0], 'button' )[0], 'click' );
	const both = h.dialogs.filter( d => d.open ).length;

	h.back();
	h.router.arrive();
	const answered = record.follow();

	check( 'a question stands on the record: two dialogs are open', both === 2 );
	check( 'Back closes the question first and answers it "no"; the record stays - there is never a question without its record', answered === true && h.dialogs.filter( d => d.open ).length === 1 && record.isOpen() === true && answers.join() === 'false' );
	check( '...and the step through the history is taken back, so that the address is the record\'s again', h.went[h.went.length - 1] === 1 );

	// Where the address leads to another panel with the question open, both go
	fire( tag( byClass( h.dialogs.filter( d => d.open ).pop(), 'nino-admin-actionbar' )[0], 'button' )[0], 'click' );
	h.box.location.hash = '#logs';
	h.hashchange();
	check( 'an address that leads to another panel takes the question and the record with it', h.dialogs.filter( d => d.open ).length === 0 && record.isOpen() === false );
}

{
	// The question about input stands on a record that has nothing else on it, and on nothing else: not on a record with a further dialog over it
	const s = setup( true );
	s.record.open( 'r03', {} );
	typeInto( s.dialog(), 'Changed' );
	s.h.ui.confirm( 'destructive', { title : 'Other', message : 'Other?', yes : 'Yes', no : 'No', nested : true, onChoose : () => {} } );
	let cancelled = 0;
	s.h.dirty.guard( [ 'submissions' ], () => {}, () => { cancelled++ } );
	check( 'with a further dialog over the record the question is refused as it is without the record, and the move is not made', s.h.dialogs.filter( d => d.open ).length === 2 && cancelled === 1 );
}

{
	// An entry that was taken away, and Forward: not found - and no entry of the directory twice, no mark left over
	const s = setup( false );
	const entries = s.h.entries.length;

	s.record.open( 'r03', {} );
	fire( buttonOf( s.dialog(), 'Delete' ), 'click' );
	check( 'the deletion takes the entry of the history off again, as the cross does', s.dialog() === null && s.h.went.join() === '-1' );
	s.h.back();
	s.h.router.arrive();
	s.record.follow();
	s.h.forward();
	s.h.router.arrive();
	const answered = s.record.follow();
	check( 'Forward to the address of the entry that is gone says it is not there', answered === true && s.calls.missing.join() === 'r03' && s.dialog() === null );
	check( '...and takes that entry off the history as well (the step is asked of the history), so that the directory is not in the history twice and Back does something', s.h.went.join() === '-1,-1' && s.h.entries.length === entries + 1 );
	s.h.back();
	s.h.router.arrive();
	check( '...the step that comes of it is the record\'s own, and the address is the directory\'s', s.record.follow() === true && s.h.hash() === '#submissions' && s.h.router.marked() === '' );
}

{
	// The edges of the mark
	// R1: an entry that another panel marked is not a record's of this one
	const s = setup( false );
	s.record.open( 'r03', {} );
	s.h.router.mark( 'other' );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( 'the cross takes off an entry that it made - not one that is marked as another panel\'s: that is written over', s.h.went.length === 0 && s.h.hash() === '#submissions' );

	// R5: a mark only where the record made the entry
	const t = setup( false );
	t.h.box.location.hash = '#submissions/submissions/r03';
	t.record.open( 'r03', {} );
	check( 'where the address was the entry\'s already no entry is made, and none is marked', t.h.router.marked() === '' );
	fire( buttonOf( t.dialog(), 'Close' ), 'click' );
	check( '...so the cross writes the directory\'s address over it', t.h.went.length === 0 && t.h.hash() === '#submissions' );

	// R3: the step that the cross asked for is waited for, but not for ever - the timer ends it, and a record that is opened ends it too
	const u = setup( false );
	u.record.open( 'r03', {} );
	fire( buttonOf( u.dialog(), 'Close' ), 'click' );
	u.h.box.location.hash = '#submissions';
	u.h.runTimers();
	check( 'a step back that never comes does not keep the next address of the panel from being read', u.record.follow() === false );

	const v = setup( false );
	v.record.open( 'r03', {} );
	fire( buttonOf( v.dialog(), 'Close' ), 'click' );
	v.record.open( 'r04', {} );
	v.h.box.location.hash = '#submissions';
	check( 'a record that is opened ends the wait: Back then closes it, and is not taken for the step of the cross', v.record.follow() === true && v.dialog() === null );

	// R11: the wait is for an address that names no entry
	const w = setup( false );
	w.record.open( 'r03', {} );
	fire( buttonOf( w.dialog(), 'Close' ), 'click' );
	w.h.box.location.hash = '#submissions/submissions/r05';
	check( 'an address that names an entry is read as one, also while a step back is awaited', w.record.follow() === true && w.dialog() !== null && titleOf( w.dialog() ) === 'Item 5' );
}

// --- the buttons follow the entry after a save, and an action gives its button back to a dialog it opens ------------------

{
	// "off" in the name takes the actions away: Reply and Delete are for an entry that is on
	const on = row => row.name.indexOf('off') === -1;
	const asked = [];
	const s = setup( true, {
		danger : { label : 'Delete', hidden : row => on( row ) === false, run : () => {} },
		actions : [ { label : 'Reply', hidden : row => on( row ) === false, run : ( row, ctx ) => { asked.push( ctx.opener ); } } ],
	} );

	s.record.open( 'r03', {} );
	const d = s.dialog();
	const shown = () => [ 'Delete', 'Reply' ].filter( text => buttonOf( d, text ).hidden !== true ).join();

	check( 'an entry that is on has the buttons for it', shown() === 'Delete,Reply' );

	// The focus is on a button that the saved entry has no use for
	typeInto( d, 'Item 3 off' );
	buttonOf( d, 'Reply' ).focus();
	fire( buttonOf( d, 'Save' ), 'click' );
	check( 'after a save the buttons are asked of the entry as the save answered it: Delete and Reply are gone', shown() === '' && titleOf( d ) === 'Item 3 off' );
	check( '...and the focus of the button that went goes to the main button of the bar', buttonOf( d, 'Save' ).focused === true );

	typeInto( d, 'Item 3' );
	fire( buttonOf( d, 'Save' ), 'click' );
	check( '...and a save of an entry that is on again shows them', shown() === 'Delete,Reply' && titleOf( d ) === 'Item 3' );

	// The button of an action is its opener
	const reply = buttonOf( d, 'Reply' );
	fire( reply, 'click' );
	check( 'an action gets its own button as ctx.opener', asked.length === 1 && asked[0] === reply );

	s.h.ui.confirm( 'big', { title : 'Reply', message : 'Send?', yes : 'Send', no : 'Cancel', nested : true, opener : asked[0], onChoose : () => {} } );
	escape( s.dialog() );
	check( '...and a question it asks on the record gives the focus back to that button, the record stays open', s.dialog() === d && reply.focused === true );
}

{
	// A form over the record, and one after the other: the focus goes back to the place it came from
	const s = setup( true );
	s.record.open( 'r03', {} );
	const d = s.dialog();
	const field = tag( d, 'input' )[0];
	const save = buttonOf( d, 'Save' );
	const form = ( opener, extra ) => s.h.ui.formDialog( Object.assign( { title : 'Note', fields : [ { key : 'n', label : 'Note', value : 'x' } ], submit : 'Add', cancel : 'Cancel', nested : true, opener : opener, onSubmit : ( values, ctl ) => ctl.close() }, extra ) );
	const sendIt = dialog => fire( tag( dialog, 'button' ).filter( b => b.textContent === 'Add' )[0], 'click' );

	field.focus();
	check( 'a form opens over a record', form( field ) !== false && s.h.dialogs.filter( x => x.open === true ).length === 2 );
	sendIt( s.dialog() );
	check( '...and when it is sent the record is still open, and the focus is back in its field', s.dialog() === d && field.focused === true );

	save.focus();
	const log = [];
	form( save, { onClose : v => { log.push( v ); form( field, { onClose : w => log.push( 'second:'+ w ) } ) } } );
	sendIt( s.dialog() );
	check( 'a form asked for in the onClose of another is opened over the record', log.join() === 'submit' && s.dialog() !== d && s.h.dialogs.filter( x => x.open === true ).length === 2 );
	escape( s.dialog() );
	check( '...and when that one is closed the focus is back in the field it was asked for with', s.dialog() === d && field.focused === true && log.join() === 'submit,second:cancel' );
}


// --- the state badge of the bar: nothing until something is typed, "Saved" only after a save ------------------------------

{
	const s = setup( true );
	s.record.open( 'r03', {} );
	const d = s.dialog();
	const badge = () => byClass( d, 'nino-admin-actionbar-state' )[0];

	check( 'an entry that was just opened says nothing of its state: no "Saved" for what was not saved', badge().hidden === true && badge().textContent === '' );

	typeInto( d, 'Item 3 edited' );
	s.h.dirty.refresh();
	check( '...the first input says it is changed', badge().hidden === false && badge().dataset.state === 'warn' );

	fire( buttonOf( d, 'Save' ), 'click' );
	check( '...and only a save that went well says it is saved', badge().hidden === false && badge().dataset.state === 'ok' );

	fire( buttonOf( d, 'Next' ), 'click' );
	check( '...a step to the next entry is neutral again', badge().hidden === true && badge().textContent === '' );

}


// --- a directory that lies under a part of its screen: the parts before the entry --------------------------------------

{
	const folder = { key : 'folder/one' };
	const s = setup( true, { prefix : () => [ folder.key ] } );

	s.h.router.set( 'submissions', [ folder.key ] );
	s.record.open( 'r03', {} );
	check( 'a record under a folder writes the folder as ONE part - whatever it holds - and the entry behind it', s.h.hash() === '#submissions/submissions/folder%2Fone/r03' );

	fire( buttonOf( s.dialog(), 'Next' ), 'click' );
	check( '...stepping writes over the entry and keeps the folder', s.h.hash() === '#submissions/submissions/folder%2Fone/r04' && s.h.router.current().parts.join('|') === 'folder/one|r04' );

	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( '...closed, the address is the folder\'s and no more', s.dialog() === null && s.h.went.join() === '-1' );

	const t = setup( true, { prefix : () => [ folder.key ] } );
	t.h.box.location.hash = '#submissions/submissions/folder%2Fone/r05';
	check( 'an address that names the entry behind the folder opens it', t.record.follow() === true && t.dialog() !== null && titleOf( t.dialog() ) === 'Item 5' );

	t.h.box.location.hash = '#submissions/submissions/folder%2Fone';
	check( '...one that names the folder alone is the directory: the record closes', t.record.follow() === true && t.dialog() === null );

	t.h.box.location.hash = '#submissions/submissions/folder%2Fone/r06';
	t.record.follow();
	t.h.box.location.hash = '#submissions/submissions/other%20folder/r06';
	t.h.hashchange();
	check( '...and an address of another folder is not its own: the record closes, and the panel draws that folder', t.dialog() === null && t.record.follow() === false );

	// A panel that leads views of its own in the tab - a folder view, `@folder/<id>` - and gives no prefix: its address is not the record's to write over
	const u = setup( true );
	u.h.box.location.hash = '#submissions/submissions/@folder/room1';
	check( 'a record without a prefix reads the address from the first part: two parts name no entry, and the address the panel leads is left as it is', u.record.follow() === false && u.dialog() === null && u.h.hash() === '#submissions/submissions/@folder/room1' );
	u.h.box.location.hash = '#submissions/submissions/r03/extra';
	check( '...a part too many as well', u.record.follow() === false && u.dialog() === null && u.h.hash() === '#submissions/submissions/r03/extra' );
	u.h.box.location.hash = '#submissions/submissions/r99';
	u.record.follow();
	check( '...and an entry that is not there is told to the panel; the address is the directory\'s', u.calls.missing.join() === 'r99' && u.h.hash() === '#submissions' );
}


// --- the prefix: a word and a key are not the same part, and a record opened by a typed address closes to the folder ------------------------

{
	const folder = 'folder/one';
	const s = setup( true, { prefix : [ folder ] } );

	s.h.box.location.hash = '#submissions/submissions/folder%2Fone/r04';
	s.record.follow();
	check( 'a record that an address opened under a folder has no entry of its own, and is closed by writing the address of that folder - not the address of the screen', s.dialog() !== null && s.h.router.marked() === '' );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( '...the address stays the folder\'s: the prefix is not lost by closing', s.h.hash() === '#submissions/submissions/folder%2Fone' && s.h.went.length === 0 );

	// A prefix that is a key: written as the word it looks like is another address
	const k = setup( true, { prefix : [ '@new' ] } );
	k.h.box.location.hash = '#submissions/submissions/@new/r03';
	check( 'a part of the prefix that is a key does not match the word that is written the same - the record is not opened by it', k.record.follow() === false && k.dialog() === null );
	k.h.box.location.hash = '#submissions/submissions/%40new/r03';
	check( '...and matches the key', k.record.follow() === true && k.dialog() !== null );

	// A prefix that is a word does not match the key that is written the same
	const m = setup( true, { prefix : [ { word : '@new' } ] } );
	m.h.box.location.hash = '#submissions/submissions/%40new/r03';
	check( 'a part of the prefix that is a word does not match the key that is written the same', m.record.follow() === false && m.dialog() === null );
	m.h.box.location.hash = '#submissions/submissions/@new/r03';
	check( '...and matches the word', m.record.follow() === true && m.dialog() !== null );
}


// --- an action that takes something of the entry away and not the entry: the record stays ----------------------------------------------

{
	let gone = false;
	const s = setup( true, { danger : { label : 'Remove picture', stay : true, hidden : row => row.thumb === '', run : ( row, opener, finished ) => {
		gone = true;
		s.rows()[s.rows().indexOf( row )] = Object.assign( {}, row, { thumb : '', name : 'Item without picture' } );
		s.directory.update( s.rows(), row.key );
		finished( true );
	} } } );

	s.record.open( 'r03', {} );
	const d = s.dialog();
	fire( buttonOf( d, 'Remove picture' ), 'click' );
	check( 'an action that says it stays leaves the record open after it went, drawn as the entry is now: its title is the new one, and the button of an entry that has nothing to take away is not shown',
		gone === true && s.dialog() === d && titleOf( d ) === 'Item without picture' && buttonOf( d, 'Remove picture' ).hidden === true && s.record.isOpen() === true );
	check( '...the address is the entry\'s still, the directory has the row, fresh, and nothing is asked about unsaved input', s.h.hash() === '#submissions/submissions/r03' && rowEl( s, 'r03' ).classList.contains('is-fresh') === true && s.h.dirty.isDirty( [ 'submissions' ] ) === false );
}


// --- a save that wrote part of the entry says the entry as it is --------------------------------------------------------------------------

{
	const s = setup( true, { danger : { label : 'Delete', hidden : row => row.name.indexOf('half') === -1, run : () => {} } } );

	s.calls.answer = ( finished, row ) => {
		const half = Object.assign( {}, row, { name : 'half written' } );
		s.rows()[s.rows().indexOf( row )] = half;
		finished( false, { error : 'The rest failed.', row : half } );
	};
	s.record.open( 'r03', {} );
	check( 'before: the entry has no button to delete', buttonOf( s.dialog(), 'Delete' ).hidden === true );
	typeInto( s.dialog(), 'typed' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );
	check( 'a save that failed after it wrote part of the entry says it: the line of the bar says why, and the record takes the row the panel gave - its title, and the buttons that fit it',
		byClass( s.dialog(), 'nino-admin-status' )[0].textContent === 'The rest failed.' && titleOf( s.dialog() ) === 'half written' && buttonOf( s.dialog(), 'Delete' ).hidden === false );
	check( '...and what was typed stays, as unsaved input', tag( s.dialog(), 'input' )[0].value === 'typed' && s.h.dirty.isDirty( [ 'submissions' ] ) === true );
}


// --- an address that was typed with a part that is none is written over by the true one ---------------------------------

{
	const s = setup( true, { prefix : [] } );

	s.h.box.location.hash = '#submissions/submissions/ab%zz';
	check( 'a part that cannot be decoded ends the parts: the address is the directory\'s, and the bar says so instead of what was typed', s.record.follow() === false && s.h.hash() === '#submissions' );

	s.h.box.location.hash = '#submissions/submissions/r03/extra';
	check( '...as is one part too many', s.record.follow() === false && s.h.hash() === '#submissions' );

	s.h.box.location.hash = '#submissions/submissions/r03/%zz';
	check( '...and the entry that stood before the part that is none is opened, under its own address', s.record.follow() === true && s.dialog() !== null && titleOf( s.dialog() ) === 'Item 3' && s.h.hash() === '#submissions/submissions/r03' );

	const entries = s.h.entries.length;
	s.record.follow();
	check( 'an address that is true is written over by nothing', s.h.hash() === '#submissions/submissions/r03' && s.h.entries.length === entries );
}


// --- what takes away has one place --------------------------------------------------------------------------------------

{
	let refused = null;

	try {
		setup( true, { actions : [ { label : 'Archive', kind : 'danger', run : () => {} } ] } ).record.open( 'r01', {} );
	}
	catch( error ) {
		refused = error;
	}

	check( 'a second action in the colour of danger is refused: what takes an entry away is `danger`, at the left', refused !== null && refused.name === 'TypeError' );

	const ok = setup( true, { actions : [ { label : 'Archive', kind : 'secondary', run : () => {} } ] } );
	ok.record.open( 'r01', {} );
	check( '...and a normal action stands beside them', buttonOf( ok.dialog(), 'Archive' ) !== undefined && hasClass( buttonOf( ok.dialog(), 'Archive' ), 'nino-admin-btn-secondary' ) );
}


// --- a choice that is taken back is no unsaved input --------------------------------------------------------------------

{
	let ctx = null;
	const s = setup( true, { body : function( row, c ) { ctx = c; return element('div') } } );

	s.record.open( 'r03', {} );
	ctx.touch();
	check( 'something chosen that no field holds - a file - is unsaved input', s.h.dirty.isDirty( [ 'submissions' ] ) === true );
	ctx.touch( false );
	check( '...and is none again when it is taken back', s.h.dirty.isDirty( [ 'submissions' ] ) === false );
}


// --- an entry that is being made: `create` -------------------------------------------------------------------------------------------

const MAKER_LABELS = { position : '%s of %s', close : 'Close', prev : 'Previous', next : 'Next', save : 'Save', saveNext : 'Save and continue', closeButton : 'Close', deleted : 'Deleted.', saved : 'Saved.', createTitle : 'New thing', createSave : 'Create thing', created : 'Created.' };

/**
 *	A record that can make an entry: its form has the key as a field of its own while it is made (the first), the blank row is asked each time, and a save that makes
 *	puts the row in the directory before it says so. `made` logs what body() and save() were given
 */
function maker( extra, create ) {

	const made = { blanks : 0, bodies : [], saves : [] };
	const s = setup( true, Object.assign( {
		create : Object.assign( { blank : () => { made.blanks++; return { key : '', name : '' } } }, create ?? {} ),
		labels : MAKER_LABELS,
		body : function( row, ctx ) {
			made.bodies.push( { row : row, creating : ctx.creating, key : ctx.key } );
			const wrap = element('div');
			if( ctx.creating === true )
				wrap.appendChild( s.h.ui.textField( { label : 'Key', value : row.key, key : 'key' } ) );
			wrap.appendChild( s.h.ui.textField( { label : 'Name', value : row.name, key : 'name' } ) );
			return wrap;
		},
	}, extra ?? {} ) );

	s.made = made;
	s.opener = element('button');
	s.h.page.appendChild( s.opener );

	// What a save of the form does: the panel puts the new row in its directory and says it
	s.calls.answer = function( finished, row, ctx ) {

		const inputs = tag( ctx.form, 'input' );

		made.saves.push( { creating : ctx.creating, row : row, key : ctx.key, values : inputs.map( el => el.value ) } );

		if( ctx.creating !== true ) {
			const renamed = Object.assign( {}, row, { name : inputs[0].value } );
			s.rows().splice( s.rows().indexOf( row ), 1, renamed );
			s.directory.update( s.rows(), row.key );
			finished( true, { row : renamed } );
			return;
		}

		const row2 = { key : inputs[0].value, name : inputs[1].value, group : 'a', thumb : '/images/new.jpg' };

		s.rows().push( row2 );
		s.directory.setRows( s.rows() );
		finished( true, { row : row2 } );
	};

	return s;
}

/** Type into the n-th field of the record that is open */
function typeAt( dialog, at, value ) { const input = tag( dialog, 'input' )[at]; input.value = value; fire( input, 'input' ); fire( input, 'change' ) }

const toastsOf = h => byClass( h.body, 'nino-admin-toast-text' ).map( el => el.textContent );

{
	// Without `create` nothing is different: the handle has no call to make an entry, and the address of the word is no entry
	const s = setup( true );

	check( 'a record that was not given create has no create() and no creating() - a call is a TypeError of the language', s.record.create === undefined && s.record.creating === undefined );
	s.h.box.location.hash = '#submissions/submissions/@new';
	check( '...the address of the word is read as it always was: an entry that is not there, told to the panel, the address the directory\'s', s.record.follow() === true && s.dialog() === null && s.calls.missing.join() === '@new' && s.h.hash() === '#submissions' );

	// create without what a form needs is a mistake of the caller
	const refused = [ { create : { blank : () => ( {} ) }, labels : { createSave : 'x' } }, { create : { blank : () => ( {} ) }, labels : { createTitle : 'x' } }, { create : {}, labels : MAKER_LABELS }, { create : { blank : () => ( {} ) }, labels : MAKER_LABELS, readOnly : true } ].map( extra => {
		try { setup( true, extra ); return null } catch( error ) { return error }
	} );
	check( 'a record with create and no createTitle, no createSave, no blank() or no way to save is a mistake of the caller: a TypeError when it is made', refused.every( error => error !== null && error.name === 'TypeError' ) );
}

{
	const entryBadge = ( () => { const e = maker( { prefix : [] } ); e.record.open( 'r03', {} ); return byClass( e.dialog(), 'nino-admin-actionbar-state' )[0] } )();
	const s = maker( { prefix : [] } );
	const before = s.h.entries.length;

	check( 'a record with create has create() and creating(), and no form is open before', typeof s.record.create === 'function' && s.record.creating() === false && s.dialog() === null );
	s.record.create( { opener : s.opener } );
	const d = s.dialog();

	check( 'create() opens one dialog, the large dialog of a record', d !== null && s.h.dialogs.filter( x => x.open ).length === 1 && hasClass( d, 'nino-admin-dialog--large' ) && hasClass( d, 'nino-admin-record' ) );
	check( '...its title is the one of the caller and it has no identifier under it', titleOf( d ) === 'New thing' && byClass( d, 'nino-admin-dialog-subtitle' ).every( el => el.hidden === true && el.textContent === '' ) );
	check( '...no steps and no place: the steps are not shown and the count says nothing', buttonOf( d, 'Previous' ).hidden === true && buttonOf( d, 'Next' ).hidden === true && countOf( d ) === '' );
	const bar = byClass( d, 'nino-admin-actionbar' )[0];
	const shown = bar.children.filter( el => el.tagName === 'BUTTON' && el.hidden !== true ).map( el => el.textContent );
	check( '...no destructive action and no "Save and continue" in its bar: the one main button, in the words of the caller', shown.join() === 'Create thing' && hasClass( bar.children[bar.children.length - 1], 'nino-admin-btn-primary' ) );
	check( '...the badge says what an entry that was just opened says', byClass( d, 'nino-admin-actionbar-state' )[0].dataset.state === entryBadge.dataset.state && byClass( d, 'nino-admin-actionbar-state' )[0].dataset.fresh === entryBadge.dataset.fresh && entryBadge.dataset.state !== undefined );
	check( 'the blank row is asked for when the form opens, and body() is given it and says that an entry is being made', s.made.blanks === 1 && s.made.bodies.length === 1 && s.made.bodies[0].creating === true && s.made.bodies[0].key === null && s.made.bodies[0].row.name === '' );
	check( '...the focus is in the first field', tag( d, 'input' )[0].focused === true && tag( d, 'input' ).length === 2 );
	check( 'the record says it: creating() is true, isOpen() is true and there is no key', s.record.creating() === true && s.record.isOpen() === true && s.record.key() === null );
	check( 'the address is the word, written by the router, and it is one entry more of the history, marked as the record\'s own', s.h.hash() === '#submissions/submissions/@new' && s.h.entries.length === before + 1 && s.h.router.marked() === 'submissions' && s.h.router.current().parts.words[0] === true );
}

{
	const s = maker( { prefix : [ 'folder' ] } );

	s.h.router.set( 'submissions', [ 'folder' ] );
	s.record.create( { opener : s.opener } );
	check( 'under a prefix the address is the prefix and then the word', s.h.hash() === '#submissions/submissions/folder/@new' );

	const f = maker( { prefix : [ 'folder' ] } );
	f.h.box.location.hash = '#submissions/submissions/folder/@new';
	check( 'an address that names the word behind the prefix opens the form, and closing writes the prefix', f.record.follow() === true && f.record.creating() === true && ( fire( buttonOf( f.dialog(), 'Close' ), 'click' ), f.h.hash() === '#submissions/submissions/folder' ) );
	f.h.box.location.hash = '#submissions/submissions/@new';
	check( '...and one that does not begin with the prefix is not the form', f.record.follow() === false && f.dialog() === null );

	const t = maker( { prefix : [] }, { word : 'draft' } );
	t.record.create( { opener : t.opener } );
	check( 'the word of the address is the caller\'s where it says one', t.h.hash() === '#submissions/submissions/@draft' );
}

{
	// Closing with nothing typed
	const s = maker( { prefix : [] } );

	s.record.create( { opener : s.opener } );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( 'the cross with nothing typed closes the form and takes the one entry of the history it made back - by the history itself', s.dialog() === null && s.record.isOpen() === false && s.record.creating() === false && s.h.went.join() === '-1' );
	check( '...the focus goes to the button that opened it', s.opener.focused === true );

	s.h.back();
	s.h.router.arrive();
	check( '...and the address is the directory\'s', s.h.hash() === '#submissions' && s.record.follow() === true && s.dialog() === null );

	const t = maker( { prefix : [] } );
	t.record.create( { opener : t.opener } );
	escape( t.dialog() );
	check( 'Escape does the same', t.dialog() === null && t.h.went.join() === '-1' && t.opener.focused === true );

	// Back
	const u = maker( { prefix : [] } );
	u.record.create( { opener : u.opener } );
	u.h.back();
	u.h.router.arrive();
	const entries = u.h.entries.length;
	check( 'Back closes the form that holds no input, and writes nothing', u.record.follow() === true && u.dialog() === null && u.h.went.length === 0 && u.h.entries.length === entries && u.h.hash() === '#submissions' );
	u.h.forward();
	u.h.router.arrive();
	check( '...and Forward opens it again, as the address says', u.record.follow() === true && u.dialog() !== null && titleOf( u.dialog() ) === 'New thing' && u.record.creating() === true );
}

{
	// An address opens the form: a reload, a typed address, a link
	const s = maker( { prefix : [] } );

	s.h.box.location.hash = '#submissions/submissions/@new';
	const entries = s.h.entries.length;

	check( 'an address that names the word opens the form, and adds no entry of the history', s.record.follow() === true && s.dialog() !== null && titleOf( s.dialog() ) === 'New thing' && s.record.creating() === true && s.h.entries.length === entries && s.h.router.marked() === '' );
	check( '...it is open once: the address that still names it does nothing', ( s.record.follow(), s.h.dialogs.filter( x => x.open ).length === 1 ) && s.made.blanks === 1 );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( '...closed, it writes the directory\'s address over its own and takes no step through the history', s.dialog() === null && s.h.hash() === '#submissions' && s.h.went.length === 0 && s.h.entries.length === entries );
	check( '...and the focus goes to the search, there being no button and no row', tag( s.mount, 'input' ).find( el => el.type === 'search' ).focused === true );

	// A key that is written the same is an entry
	const t = maker( { prefix : [] } );
	t.rows().push( { key : '@new', name : 'At new', group : 'a', thumb : '' } );
	t.directory.setRows( t.rows() );
	t.h.box.location.hash = '#submissions/submissions/%40new';
	check( 'an entry whose key is @new is the part %40new: it opens the entry, not the form', t.record.follow() === true && titleOf( t.dialog() ) === 'At new' && t.record.creating() === false && t.record.key() === '@new' );
	t.record.close();
	t.record.open( '@new', {} );
	check( '...and is written as %40new', t.h.hash() === '#submissions/submissions/%40new' );
}

{
	// Unsaved input
	const s = maker( { prefix : [] } );

	s.record.create( { opener : s.opener } );
	check( 'a form that holds nothing typed is no unsaved input', s.h.dirty.isDirty( [ 'submissions' ] ) === false );
	typeAt( s.dialog(), 0, 'k1' );
	check( '...and one that was typed in is: the entry of the registry serves the form', s.h.dirty.isDirty( [ 'submissions' ] ) === true );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( 'the cross asks first: Discard, Cancel and Save', s.h.dialogs.filter( d => d.open ).length === 2 && tag( s.dialog(), 'button' ).map( b => b.textContent ).join() === '/_admin/common/label/discard,/_admin/common/label/cancel,/_admin/common/label/save' );
	fire( tag( s.dialog(), 'button' )[1], 'click' );
	check( 'Cancel keeps the form, what was typed and the address', s.first().open === true && tag( s.first(), 'input' )[0].value === 'k1' && s.h.hash() === '#submissions/submissions/@new' && s.h.went.length === 0 && s.record.creating() === true );

	fire( buttonOf( s.first(), 'Close' ), 'click' );
	fire( tag( s.dialog(), 'button' )[0], 'click' );
	check( 'Discard closes the form, with the history entry taken back and the focus on the opener', s.dialog() === null && s.made.saves.length === 0 && s.h.went.join() === '-1' && s.opener.focused === true && s.rows().length === 60 );

	// Save, as the answer
	const t = maker( { prefix : [] } );
	t.record.create( { opener : t.opener } );
	typeAt( t.dialog(), 0, 'k2' );
	typeAt( t.dialog(), 1, 'Made' );
	fire( buttonOf( t.dialog(), 'Close' ), 'click' );
	fire( tag( t.dialog(), 'button' )[2], 'click' );
	check( 'Save of the question runs the save that makes, and then leaves as for an entry: the form is closed', t.made.saves.length === 1 && t.made.saves[0].creating === true && t.dialog() === null && t.rows().length === 61 );
	check( '...and the focus is on the new row', rowEl( t, 'k2' ) !== undefined && rowEl( t, 'k2' ).focused === true && t.h.went.join() === '-1' );

	// Back with input
	const u = maker( { prefix : [] } );
	u.record.create( { opener : u.opener } );
	typeAt( u.dialog(), 0, 'x' );
	u.h.back();
	u.h.router.arrive();
	check( 'Back asks when the form holds input', u.record.follow() === true && u.first().open === true && u.h.dialogs.filter( d => d.open ).length === 2 );
	fire( tag( u.dialog(), 'button' )[1], 'click' );
	check( '...Cancel keeps the form and takes the step through the history back', u.first().open === true && u.h.went.join() === '1' && tag( u.first(), 'input' )[0].value === 'x' );

	// open() while it holds input
	const v = maker( { prefix : [] } );
	v.record.create( { opener : v.opener } );
	typeAt( v.dialog(), 0, 'y' );
	v.record.open( 'r03', {} );
	check( 'open() of an entry while the form holds input asks first, and shows nothing yet', v.h.dialogs.filter( d => d.open ).length === 2 && v.record.creating() === true );
	fire( tag( v.dialog(), 'button' )[1], 'click' );
	check( '...Cancel leaves the form as it is', v.record.creating() === true && tag( v.first(), 'input' )[0].value === 'y' && v.h.hash() === '#submissions/submissions/@new' );
	v.record.open( 'r03', {} );
	fire( tag( v.dialog(), 'button' )[0], 'click' );
	check( '...Discard shows the entry, with the title, the steps and the address of an entry, in the dialog that was open', titleOf( v.first() ) === 'Item 3' && v.record.creating() === false && v.record.key() === 'r03' && v.h.hash() === '#submissions/submissions/r03' && v.h.dialogs.filter( d => d.open ).length === 1 && countOf( v.first() ) === '3 of 60' );
	check( '...with the buttons of an entry', buttonOf( v.first(), 'Delete' ).hidden !== true && buttonOf( v.first(), 'Save and continue' ).hidden !== true && buttonOf( v.first(), 'Save' ) !== undefined && buttonOf( v.first(), 'Create thing' ) === undefined );

	// create() while an entry holds input
	const w = maker( { prefix : [] } );
	w.record.open( 'r03', {} );
	typeAt( w.dialog(), 0, 'z' );
	w.record.create( { opener : w.opener } );
	check( 'create() while an entry holds input asks first', w.h.dialogs.filter( d => d.open ).length === 2 && w.record.key() === 'r03' && w.record.creating() === false );
	fire( tag( w.dialog(), 'button' )[0], 'click' );
	check( '...Discard shows the form: its title, its bar, the word', titleOf( w.first() ) === 'New thing' && w.record.creating() === true && w.h.hash() === '#submissions/submissions/@new' && buttonOf( w.first(), 'Create thing' ) !== undefined && buttonOf( w.first(), 'Delete' ).hidden === true && buttonOf( w.first(), 'Save and continue' ).hidden === true );
	check( '...with no entry of the history added: the one of the entry stays', w.h.entries.length === 2 && countOf( w.first() ) === '' );

	// An entry with nothing typed goes to the form without a question
	const x = maker( { prefix : [] } );
	x.record.open( 'r03', {} );
	x.record.create( { opener : x.opener } );
	check( 'create() while an entry holds nothing is no question', x.h.dialogs.filter( d => d.open ).length === 1 && x.record.creating() === true );
}

{
	// A save that is refused
	const s = maker( { prefix : [] } );

	s.calls.answer = ( finished ) => finished( false, { error : 'The key is taken.' } );
	s.record.create( { opener : s.opener } );
	typeAt( s.dialog(), 0, 'dup' );
	fire( buttonOf( s.dialog(), 'Create thing' ), 'click' );
	check( 'a save that makes and is refused stays: the line says why, the form is still the form of an entry that is being made', byClass( s.dialog(), 'nino-admin-status' )[0].textContent === 'The key is taken.' && s.record.creating() === true && titleOf( s.dialog() ) === 'New thing' && s.h.hash() === '#submissions/submissions/@new' && s.h.dirty.isDirty( [ 'submissions' ] ) === true );
	check( '...and a row that the failed save said it wrote is no entry for the form', ( s.calls.answer = ( finished ) => finished( false, { error : 'Half.', row : { key : 'half', name : 'Half' } } ), fire( buttonOf( s.dialog(), 'Create thing' ), 'click' ), titleOf( s.dialog() ) === 'New thing' && s.record.creating() === true ) );
}

{
	// A save that makes
	const s = maker( { prefix : [], saveNext : true, danger : { label : 'Delete', hidden : row => row.key === 'hide', run : () => {} }, actions : [ { label : 'Reply', run : () => {}, hidden : row => row.key === 'hide' } ] } );

	s.record.create( { opener : s.opener } );
	const d = s.dialog();
	const entries = s.h.entries.length;
	const bodies = s.made.bodies.length;

	typeAt( d, 0, 'k9' );
	typeAt( d, 1, 'Nine' );
	fire( buttonOf( d, 'Create thing' ), 'click' );

	check( 'the save is given the form and says that an entry is being made', s.made.saves.length === 1 && s.made.saves[0].creating === true && s.made.saves[0].key === null && s.made.saves[0].row.name === '' && s.made.saves[0].values.join() === 'k9,Nine' );
	check( 'the record is the record of the entry: its key, creating() false, the same dialog', s.record.key() === 'k9' && s.record.creating() === false && s.dialog() === d && s.record.isOpen() === true );
	check( '...the address is the entry\'s, written over: no entry of the history is added', s.h.hash() === '#submissions/submissions/k9' && s.h.entries.length === entries && s.h.router.marked() === 'submissions' );
	check( '...the title and the identifier are the entry\'s', titleOf( d ) === 'Nine' && byClass( d, 'nino-admin-dialog-subtitle' )[0].textContent === '/things/k9' && byClass( d, 'nino-admin-dialog-subtitle' )[0].hidden !== true );
	check( '...the steps and the place are there', countOf( d ) === '61 of 61' && buttonOf( d, 'Previous' ).hidden === false && buttonOf( d, 'Previous' ).disabled === false );
	check( '...the destructive action, the further action and "Save and continue" are there, and the main button is Save', buttonOf( d, 'Delete' ).hidden === false && buttonOf( d, 'Reply' ).hidden === false && buttonOf( d, 'Save and continue' ).hidden === false && buttonOf( d, 'Save' ) !== undefined && buttonOf( d, 'Create thing' ) === undefined );
	check( '...the body is drawn anew for the entry: a form with the name only, creating false, and the key the entry\'s', s.made.bodies.length === bodies + 1 && s.made.bodies[bodies].creating === false && s.made.bodies[bodies].key === 'k9' && tag( d, 'input' ).length === 1 && tag( d, 'input' )[0].value === 'Nine' );
	check( '...the badge says saved, the toast says that it was made, and nothing is unsaved', byClass( d, 'nino-admin-actionbar-state' )[0].dataset.state === 'ok' && toastsOf( s.h ).join() === 'Created.' && s.h.dirty.isDirty( [ 'submissions' ] ) === false );

	fire( buttonOf( d, 'Close' ), 'click' );
	check( 'the cross takes the one entry of the history back, and the focus is on the new row', s.dialog() === null && s.h.went.join() === '-1' && rowEl( s, 'k9' ).focused === true );

	// An entry that is hidden by hidden( row ), and no created word
	const t = maker( { prefix : [], labels : Object.assign( {}, MAKER_LABELS, { created : undefined } ), danger : { label : 'Delete', hidden : row => row.key === 'hide', run : () => {} }, actions : [ { label : 'Reply', run : () => {}, hidden : row => row.key === 'hide' } ] } );
	t.record.create( { opener : t.opener } );
	typeAt( t.dialog(), 0, 'hide' );
	fire( buttonOf( t.dialog(), 'Create thing' ), 'click' );
	check( 'the buttons are those that hidden( row ) says for the entry that was made', buttonOf( t.dialog(), 'Delete' ).hidden === true && buttonOf( t.dialog(), 'Reply' ).hidden === true );
	check( '...and the toast is the saved word where created is left out', toastsOf( t.h ).join() === 'Saved.' );

	// Stepping from the new entry, and back to a form
	const u = maker( { prefix : [], saveNext : true } );
	u.record.create( { opener : u.opener } );
	typeAt( u.dialog(), 0, 'k8' );
	fire( buttonOf( u.dialog(), 'Create thing' ), 'click' );
	fire( buttonOf( u.dialog(), 'Previous' ), 'click' );
	check( 'the entry that was made has its place: the step goes to the one before', titleOf( u.dialog() ) === 'Item 60' && u.h.hash() === '#submissions/submissions/r60' );
	u.record.create( { opener : u.opener } );
	check( '...and another form can be opened over it, with the blank row asked again', u.record.creating() === true && u.made.blanks === 2 && u.dialog() !== null );
}

{
	// What a save of an entry that exists says is the saved word, also in a record that can make entries; the page of the directory is not asked for an entry that is not there
	const s = maker( { prefix : [] } );
	const revealed = [];
	const reveal = s.directory.reveal;

	s.directory.reveal = function( key ) { revealed.push( key ); return reveal( key ) };
	s.record.create( { opener : s.opener } );
	check( 'the page that holds the entry is asked for an entry only: a form that makes one is on none', revealed.length === 0 );
	typeAt( s.dialog(), 0, 'k7' );
	fire( buttonOf( s.dialog(), 'Create thing' ), 'click' );
	typeAt( s.dialog(), 0, 'Renamed' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );
	check( 'a save of the entry that was made is a save of an entry: the toast is the saved word, and the entry is the one that was made', toastsOf( s.h ).join() === 'Saved.' && s.made.saves[1].creating === false && s.made.saves[1].key === 'k7' && titleOf( s.dialog() ) === 'Renamed' && revealed.every( key => key === 'k7' ) );

	// The step of the history that the cross asked for is on its way, and the address is the form's
	const t = maker( { prefix : [] } );
	t.record.create( { opener : t.opener } );
	fire( buttonOf( t.dialog(), 'Close' ), 'click' );
	t.h.box.location.hash = '#submissions/submissions/@new';
	check( 'the word is read as the word while the step of the cross is still on its way: it opens the form', t.record.follow() === true && t.record.creating() === true );
}

{
	// An entry without an address of its own stays at the directory's address
	const s = maker( { prefix : [], addressOf : row => row.key === 'noaddr' ? null : row.key } );

	s.record.create( { opener : s.opener } );
	typeAt( s.dialog(), 0, 'noaddr' );
	fire( buttonOf( s.dialog(), 'Create thing' ), 'click' );
	s.h.back();
	s.h.router.arrive();
	check( 'an entry that has no address of its own, once made, has the address of the directory', s.record.key() === 'noaddr' && s.h.hash() === '#submissions' && s.record.creating() === false );
}

{
	// A save that makes and does not say what it made is a mistake of the caller: said, and the form is not left in half a state
	[ [ 'no row', ( finished ) => finished( true ) ], [ 'a row that find() does not know', ( finished ) => finished( true, { row : { key : 'ghost', name : 'Ghost' } } ) ] ].forEach( ( [ what, answer ] ) => {

		const s = maker( { prefix : [] } );
		let thrown = null;

		s.calls.answer = answer;
		s.record.create( { opener : s.opener } );
		typeAt( s.dialog(), 0, 'q' );

		try { fire( buttonOf( s.dialog(), 'Create thing' ), 'click' ) } catch( error ) { thrown = error }

		check( 'done( true ) with '+ what+ ' is a TypeError of the caller, and the form stays as it was: creating, its address, what was typed, still unsaved, no ghost entry',
			thrown !== null && thrown.name === 'TypeError' && s.record.creating() === true && s.record.key() === null && s.h.hash() === '#submissions/submissions/@new' && tag( s.dialog(), 'input' )[0].value === 'q' && s.h.dirty.isDirty( [ 'submissions' ] ) === true && titleOf( s.dialog() ) === 'New thing' );

		// ...and the form can be saved again when the panel is right
		s.calls.answer = ( finished, row, ctx ) => { const made = { key : 'q', name : '', group : 'a', thumb : '' }; s.rows().push( made ); s.directory.setRows( s.rows() ); finished( true, { row : made } ) };
		fire( buttonOf( s.dialog(), 'Create thing' ), 'click' );
		check( '...and a save that is right after it makes the entry', s.record.key() === 'q' && s.record.creating() === false );
	} );
}

{
	// Ctrl+S, the registry's save
	const s = maker( { prefix : [] } );

	s.record.create( { opener : s.opener } );
	typeAt( s.dialog(), 0, 'kc' );
	s.h.dirty.shortcut( { ctrlKey : true, key : 's', preventDefault : () => {} } );
	check( 'Ctrl+S - the key the shell listens for - reaches the save that makes', s.made.saves.length === 1 && s.made.saves[0].creating === true && s.record.key() === 'kc' );
}

{
	// A form that is open when the panel is left by another address
	const s = maker( { prefix : [] } );

	s.record.create( { opener : s.opener } );
	s.h.box.location.hash = '#images';
	s.h.hashchange();
	check( 'an address of another panel takes the form away, as it takes an entry', s.dialog() === null && s.record.isOpen() === false );

	const t = maker( { prefix : [] } );
	t.record.create( { opener : t.opener } );
	t.h.box.location.hash = '#submissions/submissions/r03';
	t.h.router.arrive();
	check( 'an address that names an entry shows the entry in the form\'s dialog', t.record.follow() === true && t.record.creating() === false && titleOf( t.dialog() ) === 'Item 3' && t.h.dialogs.filter( d => d.open ).length === 1 );
}


// --- an entry whose key changes with a save: `rekey` -----------------------------------------------------------------------------------

function renamer( extra ) {

	const s = setup( true, extra );

	// The panel has replaced the row in its directory - another key - before it says so
	s.calls.answer = function( finished, row, ctx ) {
		const moved = Object.assign( {}, row, { key : 'n'+ row.key.slice( 1 ), name : tag( ctx.form, 'input' )[0].value } );
		s.rows().splice( s.rows().indexOf( row ), 1, moved );
		s.directory.setRows( s.rows() );
		finished( true, { row : moved } );
	};

	return s;
}

{
	const s = renamer( { rekey : true, prefix : [] } );

	s.record.open( 'r03', {} );
	const entries = s.h.entries.length;
	typeInto( s.dialog(), 'Moved' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );

	check( 'with rekey a save that says a row of another key makes the record the record of that key: key() says it', s.record.key() === 'n03' );
	check( '...the address is written over by the new address, with no entry added and the record\'s own entry still marked', s.h.hash() === '#submissions/submissions/n03' && s.h.entries.length === entries && s.h.router.marked() === 'submissions' );
	check( '...the title, the subtitle and the place follow the row', titleOf( s.dialog() ) === 'Moved' && byClass( s.dialog(), 'nino-admin-dialog-subtitle' )[0].textContent === '/things/n03' && countOf( s.dialog() ) === '3 of 60' );
	fire( buttonOf( s.dialog(), 'Next' ), 'click' );
	check( '...and the steps go from the new place', titleOf( s.dialog() ) === 'Item 4' && s.h.hash() === '#submissions/submissions/r04' );
	fire( buttonOf( s.dialog(), 'Previous' ), 'click' );
	check( '...back to the entry of the new key', titleOf( s.dialog() ) === 'Moved' && s.record.key() === 'n03' );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( 'the cross takes the one entry of the history back, and the focus is on the row of the new key', s.dialog() === null && s.h.went.join() === '-1' && rowEl( s, 'n03' ) !== undefined && rowEl( s, 'n03' ).focused === true );
}

{
	// A key that stays the same is no move
	const s = setup( true, { rekey : true, prefix : [] } );

	s.record.open( 'r03', {} );
	typeInto( s.dialog(), 'Same key' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );
	check( 'with rekey a save that keeps the key leaves everything as it is', s.record.key() === 'r03' && s.h.hash() === '#submissions/submissions/r03' && titleOf( s.dialog() ) === 'Same key' );
}

{
	// Without rekey the record keeps the key it has: what the base does is what stays
	const s = renamer( { prefix : [] } );

	s.record.open( 'r03', {} );
	const entries = s.h.entries.length;
	typeInto( s.dialog(), 'Moved' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );
	check( 'without rekey a row of another key leaves key() and the address as they were', s.record.key() === 'r03' && s.h.hash() === '#submissions/submissions/r03' && s.h.entries.length === entries && titleOf( s.dialog() ) === 'Moved' );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( '...and closing gives the focus to the row of the key it held, which is gone: the search has it', s.dialog() === null && tag( s.mount, 'input' ).find( el => el.type === 'search' ).focused === true );
}



// --- fix round: what a save in flight, a key that moves and an entry without an address do -------------------------------------------------

{
	// B1: the ctx the next save is given follows a key that a save changed
	const s = setup( true, { rekey : true, prefix : [] } );
	const seen = [];

	s.calls.answer = function( finished, row, ctx ) {
		seen.push( { ctxKey : ctx.key, ctxRow : ctx.row.key, rowKey : row.key } );
		const moved = Object.assign( {}, row, { key : row.key+ 'x', name : tag( ctx.form, 'input' )[0].value } );
		s.rows().splice( s.rows().indexOf( row ), 1, moved );
		s.directory.setRows( s.rows() );
		finished( true, { row : moved } );
	};
	s.record.open( 'r03', {} );
	typeInto( s.dialog(), 'A' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );
	typeInto( s.dialog(), 'B' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );
	check( 'with rekey the save that follows a change of the key is given the new key and the new row in its ctx', seen.length === 2 && seen[1].ctxKey === 'r03x' && seen[1].ctxRow === 'r03x' && seen[1].rowKey === 'r03x' && s.record.key() === 'r03xx' );
}

{
	// N8d and the page: rekey under a prefix, and the directory is asked for the page of the new key
	const s = renamer( { rekey : true, prefix : [ 'folder' ] } );
	const revealed = [];
	const reveal = s.directory.reveal;

	s.directory.reveal = function( key ) { revealed.push( key ); return reveal( key ) };
	s.h.router.set( 'submissions', [ 'folder' ] );
	s.record.open( 'r03', {} );
	const entries = s.h.entries.length;
	revealed.length = 0;
	typeInto( s.dialog(), 'Moved' );
	fire( buttonOf( s.dialog(), 'Save' ), 'click' );
	check( 'with rekey under a prefix the address is the prefix and the new key, written over', s.h.hash() === '#submissions/submissions/folder/n03' && s.h.entries.length === entries && s.record.key() === 'n03' );
	check( '...and the page of the directory that holds the new key is the one behind the record', revealed.indexOf( 'n03' ) !== -1 );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( '...closed, the address is the folder\'s', s.h.hash() === '#submissions/submissions/folder' || s.h.went.join() === '-1' );
}

{
	// N2: rekey to a key that find() does not know
	const s = setup( true, { rekey : true, prefix : [] } );
	let thrown = null;

	s.calls.answer = ( finished, row ) => finished( true, { row : Object.assign( {}, row, { key : 'ghost' } ) } );
	s.record.open( 'r03', {} );
	typeInto( s.dialog(), 'A' );

	try { fire( buttonOf( s.dialog(), 'Save' ), 'click' ) } catch( error ) { thrown = error }

	check( 'a rekey to a key that find() does not know is a TypeError of the caller: the key, the address, the title and the place stay', thrown !== null && thrown.name === 'TypeError' && s.record.key() === 'r03' && s.h.hash() === '#submissions/submissions/r03' && titleOf( s.dialog() ) === 'Item 3' && countOf( s.dialog() ) === '3 of 60' );
	check( '...what was typed stays as unsaved input, the badge says changed, the line is quiet, and the entry can be saved when the panel is right', tag( s.dialog(), 'input' )[0].value === 'A' && s.h.dirty.isDirty( [ 'submissions' ] ) === true && byClass( s.dialog(), 'nino-admin-actionbar-state' )[0].dataset.state === 'warn' && byClass( s.dialog(), 'nino-admin-status' )[0].dataset.state !== 'saving' );
}

{
	// N1: no input while a save that makes runs
	const s = maker( { prefix : [] } );
	let pending = null;

	s.calls.answer = ( finished, row, ctx ) => { pending = finished };
	s.record.create( { opener : s.opener } );
	typeAt( s.dialog(), 0, 'k5' );
	fire( buttonOf( s.dialog(), 'Create thing' ), 'click' );
	check( 'while the save of a form that makes an entry runs its fields take no input', pending !== null && tag( s.dialog(), 'input' ).every( input => input.disabled === true ) );
	pending( false, { error : 'No.' } );
	check( '...and take it again after a refused save, the focus back where it was', tag( s.dialog(), 'input' ).every( input => input.disabled !== true ) && byClass( s.dialog(), 'nino-admin-status' )[0].textContent === 'No.' );

	// An entry that exists is as it was: its fields stay open while it is saved
	const t = maker( { prefix : [] } );
	let waiting = null;

	t.calls.answer = ( finished ) => { waiting = finished };
	t.record.open( 'r03', {} );
	typeAt( t.dialog(), 0, 'x' );
	fire( buttonOf( t.dialog(), 'Save' ), 'click' );
	check( 'the fields of an entry that exists are not locked by its save', waiting !== null && tag( t.dialog(), 'input' ).every( input => input.disabled !== true ) );
}

{
	// N3: a conflict answered to a save that makes is a refused save
	const s = maker( { prefix : [], reload : ( key, finished ) => { s.reloaded = key; finished( null ) } } );

	s.calls.answer = ( finished ) => finished( false, { conflict : true, error : 'Taken.' } );
	s.record.create( { opener : s.opener } );
	typeAt( s.dialog(), 0, 'c' );
	fire( buttonOf( s.dialog(), 'Create thing' ), 'click' );
	check( 'a conflict answered to the save of a form that makes an entry offers no Reload and no Overwrite: the error is in the line and the form stays', s.h.dialogs.filter( d => d.open ).length === 1 && byClass( s.dialog(), 'nino-admin-status' )[0].textContent === 'Taken.' && s.record.creating() === true && tag( s.dialog(), 'input' )[0].value === 'c' && s.reloaded === undefined );
}

{
	// N4: an entry that was made and has no address of its own
	const s = maker( { prefix : [], addressOf : row => row.key === 'noaddr' ? null : row.key } );
	const before = s.h.entries.length;

	s.record.create( { opener : s.opener } );
	typeAt( s.dialog(), 0, 'noaddr' );
	fire( buttonOf( s.dialog(), 'Create thing' ), 'click' );
	check( 'the entry of the history that the form added is taken off the history, as the cross takes it', s.h.went.join() === '-1' && s.record.key() === 'noaddr' && s.record.isOpen() === true );
	s.h.back();
	s.h.router.arrive();
	check( '...and the record stays open over the directory\'s address, with no entry of its own and no mark, when the panel follows the address', s.record.follow() === true && s.record.isOpen() === true && s.h.hash() === '#submissions' && s.h.entries.length === before + 1 && s.h.router.marked() === '' && s.record.creating() === false );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( '...and closing writes nothing more to the history', s.dialog() === null && s.h.went.join() === '-1' && s.h.hash() === '#submissions' );
}

{
	// N5: the button that asked for the form is the one the focus goes back to
	const s = maker( { prefix : [] } );
	const rowButton = element('button');

	s.h.page.appendChild( rowButton );
	s.record.open( 'r03', { opener : rowButton } );
	s.record.create( { opener : s.opener } );
	fire( buttonOf( s.dialog(), 'Close' ), 'click' );
	check( 'create() over an entry: the cross gives the focus to the button that asked for the form, not to the one that opened the entry', s.opener.focused === true && rowButton.focused !== true );
}

{
	// N6: a word that is none is refused when the record is made
	const refused = [ 'New Thing', 'Neu', '', 'new thing' ].map( word => {
		try { maker( {}, { word : word } ); return null } catch( error ) { return error }
	} );
	check( 'a create.word that is no word of an address is a TypeError of the caller when the record is made', refused.every( error => error !== null && error.name === 'TypeError' ) );
	check( '...a word as the router says one is none', ( () => { try { maker( {}, { word : 'draft' } ); return true } catch( error ) { return false } } )() === true );
}

{
	// N7 and the focus after a save that makes
	const s = maker( { prefix : [] } );

	s.calls.answer = ( finished ) => finished( true );
	s.record.create( { opener : s.opener } );
	typeAt( s.dialog(), 0, 'q' );
	try { fire( buttonOf( s.dialog(), 'Create thing' ), 'click' ) } catch( error ) { /* said by the check below */ }
	check( 'a save that makes and answers no row leaves the bar as a refused save does: the badge says changed', byClass( s.dialog(), 'nino-admin-actionbar-state' )[0].dataset.state === 'warn' );

	const t = maker( { prefix : [] } );

	t.record.create( { opener : t.opener } );
	typeAt( t.dialog(), 0, 'f1' );
	tag( t.dialog(), 'input' )[1].focus();
	fire( buttonOf( t.dialog(), 'Create thing' ), 'click' );
	check( 'the focus that was in the form goes to the first field of the entry that it became', tag( t.dialog(), 'input' )[0].focused === true );
}


done();
