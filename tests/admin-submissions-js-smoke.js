/**
 *	Nino											A compact filesystembased php framework
 *	admin-submissions-js-smoke.js	DOM-light checks for the Submissions panel's script
 *																(_nino/Nino/Modules/Form/assets/admin.js): the standard
 *																directory over the real shell (adminPage() of the harness)
 *																and the record that a row opens.
 *
 *																The panel knows no field names of its own: a project may
 *																define any number of forms with fields of their own
 *																(\Nino\Form::FORMS), so what a row and a record show is
 *																whatever the entry carries, labelled from the form it
 *																belongs to. Pinned here: that a form Nino never heard of
 *																draws, that a value whose field was removed still shows,
 *																that the form and the search narrow together and the
 *																export takes what they left, that an entry too old to
 *																have an id offers no delete rather than one that could
 *																remove the wrong row, that the deletions - of a row, of a
 *																record, of the chosen rows - are the one action the
 *																server has and nothing else, that every value is shown
 *																as text and an address is a link only where it is one,
 *																that the address of a record is the entry, and that what
 *																a person chose in the directory stays while a record is
 *																open and while the panel is left and entered again.
 *
 *	Usage: node tests/admin-submissions-js-smoke.js
 */

'use strict';

const { check, done, source, fills, load, adminPage, element, findAll, fire, markupWrites } = require( './harness.js' );

const hasClass = ( el, name ) => String( el.className ).split(/\s+/).indexOf( name ) !== -1;
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );
const byClass = ( root, name ) => findAll( root, el => hasClass( el, name ) );


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_nino/Nino/Modules/Form/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');
const text = key => moduleEn[key] || workbenchEn[key] || '';


// --- the page ----------------------------------------------------------------

// Two forms Nino never heard of, one of which lost a field since the submission that used it was recorded
const FORMS = {
	contact : { name : 'Contact', fields : [
		{ name : 'name', 		label : 'Name', 		type : 'text' },
		{ name : 'email', 	label : 'E-Mail', 	type : 'email' },
		{ name : 'message', label : 'Message', 	type : 'textarea' },
	] },
	quote : { name : 'Quote', fields : [
		{ name : 'company', label : 'Company', 	type : 'text' },
		{ name : 'mail', 		label : 'Address', 	type : 'email' },
		{ name : 'budget', 	label : 'Budget', 	type : 'number' },
	] },
};

const ENTRIES = [
	{ id : 'aaaaaaaaaaaaaaaa', date : '2026-09-08 10:00:00', form : 'quote', company : 'Acme &amp; Sons', mail : 'buyer@example.com', budget : '5000', legacy : 'kept', ip : '127.0.0.1' },
	{ id : 'bbbbbbbbbbbbbbbb', date : '2026-09-07 09:00:00', form : 'contact', name : 'Jo', email : 'jo@example.com', message : 'Hello there\nsecond line', ip : '127.0.0.1' },
	// Written before submissions carried an id, and read as the first form's
	{ id : '', date : '2020-01-01 00:00:00', form : 'contact', name : 'Old', email : 'old@example.com', message : 'Ancient', ip : '127.0.0.1' },
];

function world() {

	const h = adminPage();
	const mount = element('div');
	const requests = [];
	const exported = [];

	mount.id = 'submissions-list';
	h.page.appendChild( mount );
	h.show('submissions');
	h.router.start();
	h.router.set( 'submissions', [] );
	h.Nino.content.getText = text;
	h.Nino.http = { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} };
	// The two helpers the panel takes from the shell's own script, which is not loaded whole here: the escaping a stored value is read back through, and the export
	h.admin.decodeEntities = value => String( value ).replace( /&amp;/g, '&' ).replace( /&quot;/g, '"' ).replace( /&#039;/g, "'" ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' );
	h.admin.exportCsv = ( filename, rows ) => exported.push( { filename : filename, rows : rows } );

	load( '_nino/Nino/Modules/Form/assets/admin.js', h.context );

	const panel = h.admin.submissions;

	const answer = ( status, body ) => {
		const request = requests[requests.length - 1];
		request.callback( { status : status, responseJSON : body } );
		return request;
	};
	const load_ = ( entries, forms, capped ) => {
		panel.showCurrent();
		answer( 200, { entries : entries ?? ENTRIES, forms : forms ?? FORMS, capped : capped ?? '' } );
	};

	return { h, panel, mount, requests, exported, answer, load : load_,
		rows : () => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined ),
		ids : () => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined ).map( el => el.dataset.row ).join(','),
		search : () => tag( mount, 'input' ).find( el => el.type === 'search' ),
		category : () => findAll( mount, el => el.tagName === 'SELECT' && el.id === 'submissions-form' )[0],
		dialog : () => h.dialogs.filter( d => d.open ).pop() ?? null,
	};
}

console.log('Submissions panel');

{
	const w = world();

	check( 'the script attaches under its nav uri with init and showCurrent', typeof w.panel === 'object' && typeof w.panel.init === 'function' && typeof w.panel.showCurrent === 'function' );

	w.panel.showCurrent();
	check( 'showing it asks the backend for the list, and nothing else', w.requests.length === 1 && w.requests[0].action === 'submissions/list' );
	check( 'while it loads the sentence stands in place of the table, and says it is busy', byClass( w.mount, 'nino-admin-loading' ).length === 1 && byClass( w.mount, 'nino-admin-directory-body' )[0].attributes['aria-busy'] === 'true' );

	w.answer( 200, { entries : ENTRIES, forms : FORMS, capped : '' } );

	// --- the table: a row is what the entry carries ---------------------------

	check( 'every entry is a row, in the order the server sent them', w.ids() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,no-id-2' );

	const heads = findAll( w.mount, el => el.tagName === 'TH' && el.dataset.key !== undefined ).map( el => el.dataset.key );
	check( 'with more than one form the columns are the moment, the form, the sender, the address and the message', heads.join() === 'date,form,sender,mail,message' );

	const first = w.rows()[0];
	const cell = n => first.children.filter( el => el.tagName === 'TD' )[n];
	check( 'the moment is written in the one form of the workbench - no seconds', findAll( cell( 1 ), el => true ).length >= 0 && cell( 1 ).textContent === w.h.ui.dateText('2026-09-08 10:00') && cell( 1 ).textContent.indexOf('00:00') === -1 );
	check( 'a row is the form it came from, the sender (the first short answer), the address and the message - as the form declares them, the escaping undone',
		cell( 2 ).textContent === 'Quote' && byClass( cell( 3 ), 'nino-admin-table-clamp' )[0].textContent === 'Acme & Sons' && byClass( cell( 4 ), 'nino-admin-table-clamp' )[0].textContent === 'buyer@example.com' );
	check( 'the message of a row is the long text, one line, cut by an ellipsis, whole in its tooltip',
		byClass( w.rows()[1], 'nino-admin-table-clamp' ).pop().title === 'Hello there\nsecond line' );

	check( 'under 40 rem a row is a card: the sender is its name, the moment, the form and the message stand under it, the address is left to the record',
		first.children.filter( el => el.dataset.card !== undefined ).map( el => el.dataset.card ).join() === 'line,line,primary,omit,line' );

	// --- the form is the category, the search is the rest ------------------------

	check( 'the form is a select left of the search, named for a screen reader, with the entry that turns it off first',
		w.category().attributes['aria-label'] === text('/_admin/submissions/label/form') && w.category().children.map( o => o.value ).join('|') === '|contact|quote' && w.category().children[0].textContent === text('/_admin/submissions/label/form-all')
		&& w.category().parentNode.children[0] === w.category() && w.category().parentNode.children[1] === w.search() );
	check( 'the search is named as the workbench names a search, by the thing', w.search().placeholder === text('/_admin/submissions/label/search') );

	w.search().value = w.h.ui.dateText('2026-09-07 09:00');
	fire( w.search(), 'input' );
	check( 'the search finds a day as the row shows it, not only as it is stored', w.ids() === 'bbbbbbbbbbbbbbbb' );

	w.search().value = '';
	fire( w.search(), 'input' );
	w.category().value = 'contact';
	fire( w.category(), 'change' );
	check( 'picking a form shows its submissions alone', w.ids() === 'bbbbbbbbbbbbbbbb,no-id-2' && w.category().value === 'contact' );

	w.search().value = 'ANCIENT';
	fire( w.search(), 'input' );
	check( 'the search reads the values, ignoring case, and narrows what the form left', w.ids() === 'no-id-2' );

	w.search().value = 'address';
	fire( w.search(), 'input' );
	check( 'the two narrow together - the Quote form has an "Address", the contact form does not', w.ids() === '' && byClass( w.mount, 'nino-admin-empty' )[0].textContent === text('/_admin/submissions/nomatch') );

	w.category().value = '';
	fire( w.category(), 'change' );
	check( 'a label is searchable as well as a value, so "address" finds the form that has one', w.ids() === 'aaaaaaaaaaaaaaaa' );

	w.search().value = '';
	fire( w.search(), 'input' );
	check( 'cleared, every row is back', w.ids() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,no-id-2' );

	// --- the export is what is on screen ---------------------------------------------

	w.category().value = 'contact';
	fire( w.category(), 'change' );
	const bar = w.h.doc.getElementById('submissions-actions');
	check( 'the export is the one main button of the one bar of the list', bar !== null && hasClass( bar, 'nino-admin-list-actions' ) && bar.children.length === 1 && bar.children[0].id === 'submissions-export' && hasClass( bar.children[0], 'nino-admin-btn-primary' ) );
	fire( bar.children[0], 'click' );
	check( 'the export takes what the form and the search left, not what is on disk', w.exported.length === 1 && w.exported[0].filename === text('/_admin/submissions/label/filename') && w.exported[0].rows.map( e => e.id ).join() === 'bbbbbbbbbbbbbbbb,' );
	w.category().value = '';
	fire( w.category(), 'change' );
}


// --- a row opens the record -------------------------------------------------

{
	const w = world();
	w.load();
	const before = w.h.entries.length;

	fire( w.rows()[1], 'click', { target : w.rows()[1] } );
	const d = w.dialog();

	check( 'a click on a row opens its record: the large dialog, at the address #submissions/submissions/<id>, one entry more in the history', d !== null && hasClass( d, 'nino-admin-dialog--large' )
		&& w.h.hash() === '#submissions/submissions/bbbbbbbbbbbbbbbb' && w.h.entries.length === before + 1 );
	check( 'it is named by the sender and says the form and the moment under it', byClass( d, 'nino-admin-dialog-title' )[0].textContent === text('/_admin/submissions/record/title').replace( '%s', 'Jo' )
		&& byClass( d, 'nino-admin-dialog-subtitle' )[0].textContent === 'Contact · '+ w.h.ui.dateText('2026-09-07 09:00') );

	const lines = byClass( d, 'nino-admin-readline' ).map( el => byClass( el, 'nino-admin-readline-label' )[0].textContent );
	check( 'it reads every value under the label it was collected under, then the moment and the form, and the long text last in a box of its own', lines.join() === 'Name,E-Mail,Received,Form,Message' && byClass( d, 'nino-admin-messagebox' )[0].textContent === 'Hello there\nsecond line' );
	check( 'the address to answer at is a link, and the bar has the deletion at the left and the answer by mail at the right - no Close, the cross closes',
		tag( d, 'a' )[0].href === 'mailto:jo@example.com'
		&& byClass( d, 'nino-admin-actionbar' )[0].children.map( el => el.textContent ).join() === text('/_admin/submissions/confirm/delete-title')+ ','+ text('/_admin/submissions/record/reply') );
	check( 'the place in the list is "n of N" and the steps are there', byClass( d, 'nino-admin-record-count' )[0].textContent === '2 of 3' );

	// The panel is entered again with the record open: it is left as it is
	const requests = w.requests.length;
	w.panel.showCurrent();
	check( 'the panel shown again with its record open reads nothing and leaves it', w.requests.length === requests && w.dialog() === d );

	// Closing: Back
	w.h.back();
	w.h.router.arrive();
	w.panel.showCurrent();
	check( 'Back closes it, and the panel does not read the list again for that', w.dialog() === null && w.requests.length === requests );

	// The cross asks the history to step back; the address that comes is the record's own and reads nothing
	w.h.forward();
	w.h.router.arrive();
	w.panel.showCurrent();
	const focusedRow = w.dialog() !== null;
	fire( tag( w.dialog(), 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/common/label/close') ), 'click' );
	w.h.back();
	w.h.router.arrive();
	w.panel.showCurrent();
	check( 'the cross closes the record, and the step back through the history that it asked for does not read the list and does not draw the table again: the row it was opened from has the focus still', focusedRow === true && w.dialog() === null && w.requests.length === requests && w.rows().some( row => row.focused === true ) );
}

{
	// What a person chose stays: the record stands over the directory and the directory is not drawn again
	const w = world();
	w.load();
	w.category().value = 'contact';
	fire( w.category(), 'change' );
	w.search().value = 'jo';
	fire( w.search(), 'input' );
	const box = tag( w.rows()[0], 'input' )[0];
	box.checked = true;
	fire( box, 'click' );

	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	fire( tag( w.dialog(), 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/common/label/close') ), 'click' );
	// The step through the history the cross asked for
	w.h.back();
	w.h.router.arrive();

	check( 'after the record is closed the directory is as it was: the form, the search and the rows chosen', w.category().value === 'contact' && w.search().value === 'jo' && w.ids() === 'bbbbbbbbbbbbbbbb' && w.rows()[0].classList.contains('is-selected') === true );
	check( '...and the focus is on the row', w.rows()[0].focused === true );

	// Left and entered again: what came in meanwhile is read, and the directory takes the new rows where it stands
	const requests = w.requests.length;
	w.panel.showCurrent();
	check( 'the step back of the cross is answered once, and reads nothing', w.requests.length === requests );
	w.panel.showCurrent();
	check( 'the panel entered again reads the list quietly: what was chosen is still chosen', w.requests.length === requests + 1 && w.category().value === 'contact' && w.search().value === 'jo' );
	w.answer( 200, { entries : [ { id : 'cccccccccccccccc', date : '2026-09-09 08:00:00', form : 'contact', name : 'Joan', email : 'joan@example.com', message : 'New' } ].concat( ENTRIES ), forms : FORMS, capped : '' } );
	check( '...and the new entry is among the rows that fit the search', w.ids() === 'cccccccccccccccc,bbbbbbbbbbbbbbbb' );
}


// --- an address names a record ---------------------------------------------------

{
	const w = world();

	w.h.box.location.hash = '#submissions/submissions/aaaaaaaaaaaaaaaa';
	w.panel.showCurrent();
	check( 'a reload with an address that names an entry draws the directory and opens the record once the list has come', w.dialog() === null );
	w.answer( 200, { entries : ENTRIES, forms : FORMS, capped : '' } );
	check( '...the list is there behind it and the record is the one the address names', w.ids() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,no-id-2' && w.dialog() !== null && byClass( w.dialog(), 'nino-admin-dialog-title' )[0].textContent === text('/_admin/submissions/record/title').replace( '%s', 'Acme & Sons' ) );
	check( '...and, being no entry of its own in the history, it is closed by writing the address of the directory over it', ( () => { fire( tag( w.dialog(), 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/common/label/close') ), 'click' ); return w.h.hash() === '#submissions' && w.dialog() === null && w.h.went.length === 0 } )() );

	const m = world();
	m.h.box.location.hash = '#submissions/submissions/ffffffffffffffff';
	m.panel.showCurrent();
	m.answer( 200, { entries : ENTRIES, forms : FORMS, capped : '' } );
	check( 'an entry that is not there leaves the list, says so over it, and writes the address of the list', m.dialog() === null && m.ids() !== '' && byClass( m.mount, 'nino-admin-notice' ).length === 1 && byClass( m.mount, 'nino-admin-notice' )[0].textContent === text('/_admin/submissions/record/missing') && m.h.hash() === '#submissions' );
	fire( m.rows()[0], 'click', { target : m.rows()[0] } );
	check( '...and the sentence is gone when an entry is opened', byClass( m.mount, 'nino-admin-notice' ).length === 0 );
}


// --- an entry too old to have an id -----------------------------------------------

{
	const w = world();
	w.load();
	const old = w.rows()[2];

	check( 'a row of an entry without an id has its open button and no delete button', tag( old, 'button' ).filter( b => hasClass( b, 'nino-admin-btn-danger' ) ).length === 0 && tag( old, 'button' ).some( b => b.attributes['aria-label'].indexOf( w.h.ui.dateText('2020-01-01 00:00') ) !== -1 ) );
	fire( old, 'click', { target : old } );
	const entries = w.h.entries.length;
	check( 'it is read all the same, and it has no address: the address stays the list\'s and no entry is added to the history', w.dialog() !== null && w.h.hash() === '#submissions' && w.h.entries.length === entries );
	check( '...and its record has no button to delete', byClass( w.dialog(), 'nino-admin-actionbar' )[0].children[0].hidden === true );
	fire( tag( w.dialog(), 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/common/label/close') ), 'click' );
	w.h.box.location.hash = '#submissions/submissions/no-id-2';
	check( 'an address cannot name it either', w.panel._record.follow() === true && w.dialog() === null && byClass( w.mount, 'nino-admin-notice' ).length === 1 );
}


// --- the deletions: the one action there is ---------------------------------------------

{
	const w = world();
	w.load();
	const row = w.rows()[0];
	const del = tag( row, 'button' ).find( b => hasClass( b, 'nino-admin-btn-danger' ) );

	check( 'a row carries a small button to delete, of the dangerous kind, named by the day of the entry', del !== undefined && hasClass( del, 'nino-admin-btn-icon--small' ) && del.attributes['aria-label'] === text('/_admin/submissions/row/delete').replace( '%s', w.h.ui.dateText('2026-09-08 10:00') ) );

	const asked = w.requests.length;
	fire( del, 'click' );
	const question = w.dialog();
	check( 'it asks first, with the question of the level destructive and a title', question !== null && byClass( question, 'nino-admin-dialog-title' )[0].textContent === text('/_admin/submissions/confirm/delete-title') && w.requests.length === asked );
	fire( tag( question, 'button' )[0], 'click' );
	check( 'declining sends nothing', w.requests.length === asked && w.ids() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,no-id-2' );

	fire( del, 'click' );
	fire( tag( w.dialog(), 'button' )[1], 'click' );
	check( 'confirming posts submissions/delete with the entry\'s id alone', w.requests.length === asked + 1 && w.requests[w.requests.length - 1].action === 'submissions/delete' && JSON.stringify( w.requests[w.requests.length - 1].payload ) === '{"id":"aaaaaaaaaaaaaaaa"}' );
	w.answer( 200, { deleted : true } );
	check( 'once the server has said so the row is gone from the page it was on and the focus is on the one that took its place', w.ids() === 'bbbbbbbbbbbbbbbb,no-id-1' && w.rows()[0].focused === true );

	// A failure is told
	const t = world();
	t.load();
	const del2 = tag( t.rows()[0], 'button' ).find( b => hasClass( b, 'nino-admin-btn-danger' ) );
	fire( del2, 'click' );
	fire( tag( t.dialog(), 'button' )[1], 'click' );
	t.answer( 403, { error : 'no' } );
	check( 'a refusal of the server leaves the row where it is', t.ids() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,no-id-2' );
}

{
	// From the record
	const w = world();
	w.load();
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const record = w.dialog();
	fire( tag( record, 'button' )[ tag( record, 'button' ).findIndex( b => b.textContent === text('/_admin/submissions/confirm/delete-title') ) ], 'click' );
	const question = w.dialog();

	check( 'the record asks on top of itself', question !== record && w.h.dialogs.filter( d => d.open ).length === 2 );
	fire( tag( question, 'button' )[1], 'click' );
	check( '...the answer yes posts the same one action', w.requests[w.requests.length - 1].action === 'submissions/delete' && w.requests[w.requests.length - 1].payload.id === 'aaaaaaaaaaaaaaaa' );
	w.answer( 200, { deleted : true } );
	check( 'it goes, and the record with it, and the focus is on the next row', w.dialog() === null && w.ids() === 'bbbbbbbbbbbbbbbb,no-id-1' && w.rows()[0].focused === true && w.h.went.join() === '-1' );
}

{
	// The rows that are chosen
	const w = world();
	w.load();
	const check_ = row => { const box = tag( row, 'input' )[0]; box.checked = true; fire( box, 'click' ) };

	check_( w.rows()[0] );
	check_( w.rows()[1] );
	check_( w.rows()[2] );
	const bar = byClass( w.mount, 'nino-admin-table-selection' )[0];
	const actions = tag( bar, 'button' ).map( b => b.textContent );
	check( 'the chosen rows offer the export of the choice and the deletion of it, the one that takes away in red', actions.slice( 0, 2 ).join() === text('/_admin/submissions/bulk/export')+ ','+ text('/_admin/submissions/bulk/delete') && hasClass( tag( bar, 'button' )[1], 'nino-admin-btn-danger' ) );

	fire( tag( bar, 'button' )[0], 'click' );
	check( 'the export of the choice is the entries that are chosen', w.exported.length === 1 && w.exported[0].rows.map( e => e.id ).join() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,' );

	const asked = w.requests.length;
	fire( tag( bar, 'button' )[1], 'click' );
	check( 'the deletion asks once, with the number - the entry with no id is not counted - and sends nothing yet', w.dialog() !== null && w.requests.length === asked && byClass( w.dialog(), 'nino-admin-dialog-title' )[0].textContent === text('/_admin/submissions/confirm/delete-many-title') );
	fire( tag( w.dialog(), 'button' )[1], 'click' );
	check( 'then it is the one delete, once for each entry, one after the other', w.requests.length === asked + 1 && w.requests[w.requests.length - 1].payload.id === 'aaaaaaaaaaaaaaaa' );
	w.answer( 200, { deleted : true } );
	check( '...the next only when the first is answered', w.requests.length === asked + 2 && w.requests[w.requests.length - 1].payload.id === 'bbbbbbbbbbbbbbbb' );
	w.answer( 200, { deleted : true } );
	check( '...and then the list is read again, to show what is left', w.requests[w.requests.length - 1].action === 'submissions/list' );
	w.answer( 200, { entries : [ ENTRIES[2] ], forms : FORMS, capped : '' } );
	check( '...with nothing chosen any more', w.ids() === 'no-id-0' && w.mount.querySelectorAll('input').filter( el => el.checked === true ).length === 0 );

	// One that fails ends the run, and says how far it got
	const t = world();
	t.load();
	check_( t.rows()[0] );
	check_( t.rows()[1] );
	fire( tag( byClass( t.mount, 'nino-admin-table-selection' )[0], 'button' )[1], 'click' );
	fire( tag( t.dialog(), 'button' )[1], 'click' );
	t.answer( 200, { deleted : true } );
	t.answer( 500, null );
	check( 'a deletion of the run that fails ends it - no more are sent - and the list is read to show what is left', t.requests[t.requests.length - 1].action === 'submissions/list' && t.requests.filter( r => r.action === 'submissions/delete' ).length === 2 );
	t.answer( 200, { entries : ENTRIES.slice( 1 ), forms : FORMS, capped : '' } );
	check( '...and says how far it got, over the table', byClass( t.mount, 'nino-admin-notice' ).length === 1 );
}


// --- what is read is text -----------------------------------------------------------------

{
	const hostile = '<img src=x onerror=alert(1)>';
	const entries = [ { id : 'dddddddddddddddd', date : '2026-09-10 08:00:00', form : 'contact', name : hostile, email : 'a@b.example?bcc=evil@example.org', message : '<script>alert(2)</script> &lt;b&gt;bold&lt;/b&gt;', ip : '127.0.0.1' } ];
	const w = world();
	const markup = markupWrites.length;

	w.load( entries, FORMS, '' );
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const d = w.dialog();

	check( 'what a visitor typed in every field is shown as the letters it is - in the row, in the title, in the lines, in the box', tag( w.mount, 'img' ).length === 0 && tag( w.mount, 'script' ).length === 0 && markupWrites.length === markup
		&& byClass( d, 'nino-admin-dialog-title' )[0].textContent.indexOf( hostile ) !== -1 && byClass( d, 'nino-admin-messagebox' )[0].textContent === '<script>alert(2)</script> <b>bold</b>' );
	check( 'an address that carries a query is not a link to write mail with - it is the text it is', tag( d, 'a' ).length === 0 );

	const script = source('_nino/Nino/Modules/Form/assets/admin.js');
	check( 'the script writes no value as markup', /innerHTML\s*=\s*[^']/.test( script.replace( /innerHTML\s*=\s*''/g, '' ) ) === false );
}


// --- the states that are not each other ------------------------------------------------------

{
	const w = world();
	w.load( [], FORMS, 'month' );
	check( 'nothing recorded at all says so, and a cap that refuses is said above it', byClass( w.mount, 'nino-admin-empty' )[0].textContent === text('/_admin/submissions/empty') && w.h.doc.getElementById('submissions-capped') !== null && w.h.doc.getElementById('submissions-capped').textContent === text('/_admin/submissions/capped/month') );
	w.panel.showCurrent();
	w.answer( 200, { entries : ENTRIES, forms : FORMS, capped : 'hour' } );
	check( '...as the cap of the hour, in words of its own', w.h.doc.getElementById('submissions-capped').textContent === text('/_admin/submissions/capped/hour') && text('/_admin/submissions/capped/hour') !== text('/_admin/submissions/capped/month') );
	w.panel.showCurrent();
	w.answer( 200, { entries : ENTRIES, forms : FORMS, capped : '' } );
	check( 'and the notice is gone once the cap is not reached', w.h.doc.getElementById('submissions-capped') === null );

	// A form that was chosen and is gone from the next load
	w.category().value = 'quote';
	fire( w.category(), 'change' );
	check( 'narrowed to Quote, only its submission is on screen', w.ids() === 'aaaaaaaaaaaaaaaa' );
	w.panel.showCurrent();
	w.answer( 200, { entries : ENTRIES, forms : { contact : FORMS.contact }, capped : '' } );
	check( 'a form that is gone since the last load stops narrowing rather than emptying the list for good, and with one form left there is no select and no column for it',
		w.ids() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,no-id-2' && w.category() === undefined && findAll( w.mount, el => el.tagName === 'TH' && el.dataset.key === 'form' ).length === 0 );

	const e = world();
	e.panel.showCurrent();
	e.answer( 500, null );
	check( 'a failed load is reported through the module\'s own words, in place of the table', byClass( e.mount, 'nino-admin-error' ).length === 1 && byClass( e.mount, 'nino-admin-error' )[0].textContent === '(500) '+ text('/_admin/submissions/error/load') && tag( e.mount, 'table' ).length === 0 );
	e.panel.showCurrent();
	check( '...and showing the panel again tries again', e.requests.length === 2 );
}

{
	// A list of the size the cap allows
	const many = [];
	for( let i = 0; i < 120; i++ )
		many.push( { id : String( i ).padStart( 16, '0' ), date : '2026-09-01 10:'+ String( i % 60 ).padStart( 2, '0' )+ ':00', form : 'contact', name : 'N'+ i, email : 'n'+ i+ '@example.com', message : 'text '+ i } );
	const w = world();
	w.load( many, FORMS, '' );
	check( 'a list of 120 is paged by 50 and the range says where it is', w.rows().length === 50 && byClass( w.mount, 'nino-admin-table-range' )[0].textContent === '1–50 of 120' );
	fire( byClass( w.mount, 'nino-admin-table-step' )[1], 'click' );
	check( '...and the page stays while a record is opened and closed', ( () => { fire( w.rows()[0], 'click', { target : w.rows()[0] } ); fire( tag( w.dialog(), 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/common/label/close') ), 'click' ); return w.rows()[0].dataset.row === '0000000000000050' } )() );
	w.rows()[0].focused = false;
	fire( w.rows()[49], 'click', { target : w.rows()[49] } );
	fire( tag( w.dialog(), 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/common/record/next') ), 'click' );
	check( 'a step from the last row of a page goes to the first of the next, and the directory behind follows', byClass( w.dialog(), 'nino-admin-record-count' )[0].textContent === '101 of 120' );
}

{
	// Only the actions the panel always had
	const w = world();
	w.load();
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const actions = new Set( w.requests.map( r => r.action ) );
	check( 'the panel asks for the list and deletes: no other action of the server is called', Array.from( actions ).every( a => a === 'submissions/list' || a === 'submissions/delete' ) );
}

// --- Round 2: what the review found --------------------------------------------------------

{
	// An entry has no key that an address can read: the row of an entry with no id is no address, and an id that looks like a position is its own entry
	const w = world();
	w.load( [ { id : '~0', date : '2026-09-10 08:00:00', form : 'contact', name : 'Tilde', email : 'jo@example.com', message : 'tilde' },
		{ id : '', date : '2020-01-01 00:00:00', form : 'contact', name : 'Old', email : 'old@example.com', message : 'old' } ], FORMS, '' );
	check( 'no key of a row of this panel begins with a tilde, and the entry with no id has a key that no id can be', w.rows().every( row => row.dataset.row.indexOf('~') !== 0 || row.dataset.row === '~0' ) && w.ids() === '~0,no-id-1' );
	w.h.box.location.hash = '#submissions/submissions/~0';
	w.panel.showCurrent();
	check( 'an id that looks like a position opens the entry that has it, not the one that has no id', w.dialog() !== null && byClass( w.dialog(), 'nino-admin-readline-value' ).some( el => el.textContent === 'Tilde' ) && byClass( w.dialog(), 'nino-admin-readline-value' ).every( el => el.textContent !== 'Old' ) );
	w.h.box.location.hash = '#submissions/submissions/no-id-1';
	w.h.router.arrive();
	check( 'the key of the entry with no id is no address: it is not found', w.panel._record.follow() === true && byClass( w.mount, 'nino-admin-notice' ).length === 1 );
}

{
	// The panel shown with an address that is not its own leaves the record to the listener of the address: it asks nothing and closes nothing itself
	const w = world();
	w.load();
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const open = w.dialog();
	const requests = w.requests.length;

	w.h.box.location.hash = '#logs';
	w.panel.showCurrent();
	check( 'showCurrent() with the address of another panel does not hand it to the record and does not read the list while the record is open', w.dialog() === open && w.requests.length === requests );
}

{
	// The address to answer at: strictly plain, as a pattern that lets through and not a list of what is kept out
	const plain = [ 'jo@example.com', 'a.b+c@sub.example.org', 'x_y-z@example.co.uk' ];
	const odd = [ 'x%2Cevil@example.org', 'a%0Abcc%3Aevil%40x.org@example.org', 'ad‮gro.live@nimda.example.org', 'a@b.example?bcc=evil@example.org', 'a@b.org, c@d.org', 'a@b.org;c@d.org',
		'a@b.org\nbcc:x@y.org', '<a@b.org>', 'a&cc=x@b.org', 'a b@c.org', 'ärger@müller.de', 'a@@b.org', '@b.org', 'a@b', 'a@b..org', 'a@-b.org', 'a\u0000@b.org', "a'b@c.org", '' ];
	const w = world();
	const address = w.panel._address;

	const longest = 'a'.repeat( 64 )+ '@'+ ( 'b'.repeat( 61 )+ '.' ).repeat( 4 )+ 'com';
	check( 'an address longer than 254 characters is none, though every part of it is plain', longest.length > 254 && address( longest ) === '' );
	check( 'a strictly plain address - ASCII, one @, a domain with a dot - is an address to answer at', plain.every( one => address( one ) === one ) );
	check( 'nothing else is one: not a percent sign, a comma, a semicolon, a query, a line break, a bracket, a control or a reversing character, not a second address, not a name that is not ASCII', odd.every( one => address( one ) === '' ) );

	const entries = [ { id : 'aaaaaaaaaaaaaaaa', date : '2026-09-10 08:00:00', form : 'contact', name : 'Ok', email : 'jo@example.com', message : 'm' },
		{ id : 'bbbbbbbbbbbbbbbb', date : '2026-09-09 08:00:00', form : 'contact', name : 'Odd', email : 'x%2Cevil@example.org', message : 'm' } ];
	w.load( entries, FORMS, '' );
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const reply = tag( w.dialog(), 'button' ).find( b => b.textContent === text('/_admin/submissions/record/reply') );
	check( 'a plain address has the button "Reply by email" as the main button of the bar', reply !== undefined && reply.hidden === false && hasClass( reply, 'nino-admin-btn-primary' ) );
	fire( tag( w.dialog(), 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/common/record/next') ), 'click' );
	check( 'one that is not plain has no such button - a button with no effect is no button - and the address stands as text, no link', reply.hidden === true && tag( w.dialog(), 'a' ).length === 0
		&& byClass( w.dialog(), 'nino-admin-readline-value' ).some( el => el.textContent === 'x%2Cevil@example.org' ) );
}

{
	// A value that a form no longer declares is shown as the visitor wrote it - its escaping undone, never as markup
	const w = world();
	w.load( [ { id : 'aaaaaaaaaaaaaaaa', date : '2026-09-10 08:00:00', form : 'contact', name : 'Ok', email : 'jo@example.com', message : 'm', legacy : 'a &amp; b &lt;i&gt;', ip : '127.0.0.1' } ], FORMS, '' );
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	check( 'a field that the form does not declare is read with its escaping undone and as text', byClass( w.dialog(), 'nino-admin-readline-value' ).some( el => el.textContent === 'a & b <i>' ) && tag( w.dialog(), 'i' ).length === 0 );
}

{
	// What is chosen is of the rows in view: a choice does not reach what a select hides, and what a deletion says is what it deletes
	const w = world();
	w.load();
	const choose = row => { const box = tag( row, 'input' )[0]; box.checked = true; fire( box, 'click' ) };

	choose( w.rows()[0] );
	choose( w.rows()[1] );
	w.category().value = 'quote';
	fire( w.category(), 'change' );
	check( 'a change of the form empties the choice: the bar of the choice is gone', byClass( w.mount, 'nino-admin-table-selection' )[0].classList.contains('nino-admin-hidden') === true );
	w.category().value = '';
	fire( w.category(), 'change' );
	choose( w.rows()[0] );
	choose( w.rows()[1] );
	choose( w.rows()[2] );
	fire( tag( byClass( w.mount, 'nino-admin-table-selection' )[0], 'button' )[1], 'click' );
	const message = byClass( w.dialog(), 'nino-admin-dialog-content' )[0].children[0].textContent;
	check( 'the question of a deletion says how many it deletes: the rows that have an id among those that are chosen - not the one that has none', message.indexOf('2') !== -1 && message.indexOf('3') === -1 );
}

{
	// A deletion in the list moves the focus to the next row - and a deletion from the record does not, until the record is gone
	const w = world();
	w.load();
	const spy = [];
	const was = w.panel._directory.focusRow;

	w.panel._directory.focusRow = function( key ) { spy.push( w.dialog() !== null ); return was.call( w.panel._directory, key ) };
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const delButton = tag( byClass( w.dialog(), 'nino-admin-actionbar' )[0], 'button' )[0];
	fire( delButton, 'click' );
	fire( tag( w.dialog(), 'button' )[1], 'click' );
	w.answer( 200, { deleted : true } );
	check( 'the focus is not moved while the record is open: it is moved once it is closed', spy.length > 0 && spy.every( open => open === false ) );
}

{
	// A cross that is pressed, a failure of a deletion of several: the number a screen reader is told is the true one
	const w = world();
	w.load();
	const choose = row => { const box = tag( row, 'input' )[0]; box.checked = true; fire( box, 'click' ) };

	choose( w.rows()[0] );
	choose( w.rows()[1] );
	fire( tag( byClass( w.mount, 'nino-admin-table-selection' )[0], 'button' )[1], 'click' );
	fire( tag( w.dialog(), 'button' )[1], 'click' );
	w.answer( 200, { deleted : true } );
	w.answer( 500, null );
	w.answer( 200, { entries : ENTRIES.slice( 1 ), forms : FORMS, capped : '' } );
	const said = findAll( w.mount, el => el.attributes['aria-live'] === 'polite' )[0].textContent;
	check( 'after a deletion of several that failed half way the live region does not still say how many were chosen', said === '' || said.indexOf('2') === -1 );
}

done();
