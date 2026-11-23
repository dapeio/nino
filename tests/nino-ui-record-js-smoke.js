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
 *															what an entry holds is never set as markup.
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

done();
