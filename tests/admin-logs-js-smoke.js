/**
 *	Nino										A compact filesystembased php framework
 *	admin-logs-js-smoke.js		DOM-light checks for the Log panel's script
 *													(_admin/Nino/Modules/Logs/assets/admin.js) over the real
 *													shell (adminPage() of the harness): the panel is the standard
 *													directory of the lines of the activity log with the record of
 *													one line over it, which is only read.
 *
 *													Pinned: the rows in the order of the answer, the three cells
 *													and what a line that does not parse shows, the search over the
 *													sentence, the account and the time as it is shown, the sort by
 *													time, that there is no box to choose rows by and no bar, the
 *													names a row has as a card; the record (address
 *													#logs/logs/<key> with the key ONE part, only read, the steps,
 *													a reload that opens it, Back and the focus, a key that is not
 *													there - also one with a slash, a percent sign or an at sign);
 *													the list read again when the panel is shown - quietly, with
 *													what was chosen kept, and never while a record is open; a failed
 *													load, and an answer without its keys as one; that everything the
 *													log holds is shown as text; and that every key the script asks
 *													for is a fill in both languages.
 *
 *	Usage: node tests/admin-logs-js-smoke.js
 */

'use strict';

const { check, done, source, fills, load, adminPage, element, findAll, fire, markupWrites } = require( './harness.js' );

const hasClass = ( el, name ) => String( el.className ).split(/\s+/).indexOf( name ) !== -1;
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );
const byClass = ( root, name ) => findAll( root, el => hasClass( el, name ) );
// What an element says: its own text and that of everything in it
const flat = el => el.children.length === 0 ? String( el.textContent ) : el.children.map( flat ).join('');


// --- the words -------------------------------------------------------------

const moduleEn	= fills('_admin/Nino/Modules/Logs/text/en_US.php');
const moduleDe	= fills('_admin/Nino/Modules/Logs/text/de_DE.php');
const workbenchEn	= fills('_admin/text/en_US.php');
const workbenchDe	= fills('_admin/text/de_DE.php');
const text = key => moduleEn[key] ?? workbenchEn[key] ?? '';


// --- what the server answers ----------------------------------------------------

const HOSTILE = '<img src=x onerror=alert(1)> "quoted" [[/key]]';
const HOSTILE_ACCOUNT = '<img src=x onerror=alert(2)>"@example.com';
const LONG = 'Save Text /template/page-home/main-hero/title - '+ 'a sentence that runs on and on, far longer than a cell of a table is wide, '.repeat( 5 )+ 'and ends here';

// The lines as logs/list answers them: most recent first, each with the key beside it - the day and the place in that day's file
const PAIRS = [
	[ '2026-11-24.5', '2026-11-24 11:40  '+ HOSTILE_ACCOUNT+ '  '+ HOSTILE ],
	[ '2026-11-24.4', '2026-11-24 10:15  admin@example.com  Upload Image /logo' ],
	[ '2026-11-24.3', '2026-11-24 09:00  editor@example.com  '+ LONG ],
	[ '2026-11-24.2', 'a line that is no line of the log' ],
	[ '2026-11-24.1', '2026-11-24 08:30  admin@example.com  Login' ],
	[ '2026-11-23.2', '2026-11-23 17:45  admin@example.com  Activate feature "themes"' ],
	[ '2026-11-23.1', '2026-11-23 17:00  admin@example.com  Login' ],
];

const answerOf = pairs => ( { lines : pairs.map( pair => pair[1] ), keys : pairs.map( pair => pair[0] ) } );

// More than a page: sixty lines of an earlier day below the others
const FILLER = Array.from( { length : 60 }, ( unused, at ) => [ '2026-11-01.'+ ( 60 - at ), '2026-11-01 08:'+ String( 59 - at ).padStart( 2, '0' )+ '  filler@example.com  Filler line '+ ( 60 - at ) ] );


// --- the page ----------------------------------------------------------------

function world( hash ) {

	const h = adminPage();
	const mount = element('div');
	const requests = [];

	mount.id = 'logs-list';
	h.page.appendChild( mount );
	h.show('logs');
	h.router.start();
	h.router.set( 'logs', [] );
	h.Nino.content.getText = text;
	h.Nino.http = { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} };

	if( hash !== undefined )
		h.box.location.hash = hash;

	load( '_admin/Nino/Modules/Logs/assets/admin.js', h.context );

	const panel = h.admin.logs;
	const answer = ( status, body ) => { requests[requests.length - 1].callback( { status : status, responseJSON : body } ) };
	const rows = () => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined );
	const dialog = () => h.dialogs.filter( d => d.open ).pop() ?? null;

	return { h, panel, mount, requests, answer, rows, dialog,
		open : pairs => { panel.showCurrent(); answer( 200, answerOf( pairs ?? PAIRS ) ) },
		ids : () => rows().map( el => el.dataset.row ),
		cells : key => rows().find( el => el.dataset.row === key ).children.filter( el => el.tagName === 'TD' ),
		search : () => tag( mount, 'input' ).find( el => el.type === 'search' ),
		heads : () => findAll( mount, el => el.tagName === 'TH' && el.dataset.key !== undefined ),
		titleOf : d => byClass( d, 'nino-admin-dialog-title' )[0]?.textContent ?? null,
		subtitleOf : d => byClass( d, 'nino-admin-dialog-subtitle' )[0]?.textContent ?? '',
		labelsOf : d => byClass( d, 'nino-admin-readline' ).map( el => byClass( el, 'nino-admin-readline-label' )[0].textContent ),
		button : ( d, key ) => tag( d, 'button' ).find( b => b.attributes['aria-label'] === text( key ) || b.textContent === text( key ) ),
		cross : d => tag( d, 'button' ).find( b => hasClass( b, 'nino-admin-dialog-close' ) ),
		next : () => tag( mount, 'button' ).filter( b => hasClass( b, 'nino-admin-table-step' ) )[1],
		step : address => { h.box.location.hash = address; h.router.arrive(); panel.showCurrent() },
		back : () => { h.back(); h.router.arrive(); panel.showCurrent() },
	};
}

// The sentence of an entry cell: the text of its clamp, without the sign that says that the row opens
const entryOf = ( w, key ) => byClass( w.cells( key )[2], 'nino-admin-table-clamp' )[0]?.textContent ?? null;

const when = value => world().h.ui.dateText( value );

console.log('Log panel');

{
	const w = world();

	check( 'the script attaches under its nav uri with init and showCurrent', typeof w.panel === 'object' && typeof w.panel.init === 'function' && typeof w.panel.showCurrent === 'function' );

	w.panel.showCurrent();
	check( 'showing it asks the server for the lines in one request, and for nothing else', w.requests.length === 1 && w.requests[0].action === 'logs/list' && JSON.stringify( w.requests[0].payload ) === '{}' );
	check( 'while it loads the sentence stands in place of the table, and says it is busy', byClass( w.mount, 'nino-admin-loading' )[0].textContent === text('/_admin/logs/loading') && byClass( w.mount, 'nino-admin-directory-body' )[0].attributes['aria-busy'] === 'true' );

	w.answer( 200, answerOf( PAIRS ) );

	// --- the directory ------------------------------------------------------------------------------

	check( 'the lines are the rows of the standard directory, in the order of the answer, each named by its key', byClass( w.mount, 'nino-admin-directory' ).length === 1 && w.ids().join() === PAIRS.map( pair => pair[0] ).join() );
	check( 'the columns are the time, the account and the entry - nothing else', w.heads().map( el => el.dataset.key ).join() === 'when,actor,text' );
	check( '...there is no box to choose rows by, no bar under the list and no select that narrows: nothing is done to a line', tag( w.mount, 'input' ).filter( el => el.type === 'checkbox' ).length === 0
		&& byClass( w.mount, 'nino-admin-actionbar' ).length === 0 && tag( w.mount, 'select' ).filter( el => hasClass( el, 'nino-admin-table-sortby' ) === false && hasClass( el, 'nino-admin-table-size' ) === false ).length === 0 );
	check( '...and the search is named for what it searches', w.search().placeholder === text('/_admin/logs/label/search') && text('/_admin/logs/label/search') !== '' );

	const cells = w.cells('2026-11-24.4');
	check( 'a row is the time in the one form of the workbench - the day, the minute, no seconds -, the account and the sentence', flat( cells[0] ) === when('2026-11-24 10:15') && flat( cells[0] ) === '2026-11-24, 10:15'
		&& flat( cells[1] ) === 'admin@example.com' && entryOf( w, '2026-11-24.4' ) === 'Upload Image /logo' );
	const clamp = byClass( w.cells('2026-11-24.3')[2], 'nino-admin-table-clamp' )[0];
	check( 'a sentence that is long is one line cut by an ellipsis, and whole in its tooltip', clamp !== undefined && clamp.title === LONG && clamp.textContent === LONG );
	check( 'a line that does not have that shape is the sentence alone: no time, no account', [ flat( w.cells('2026-11-24.2')[0] ), flat( w.cells('2026-11-24.2')[1] ), entryOf( w, '2026-11-24.2' ) ].join('|') === '||a line that is no line of the log' );
	check( 'the heads of the columns are the words of the module', w.heads().map( flat ).join() === [ 'when', 'actor', 'text' ].map( key => text('/_admin/logs/label/'+ key) ).join() );

	// Each row has ONE button, which opens it
	const buttons = tag( w.rows()[1], 'button' );
	const named = key => tag( w.rows().find( row => row.dataset.row === key ), 'button' ).map( button => button.attributes['aria-label'] ).join('|');
	check( 'a row has one small icon button and no other, named by the time of its line - no two buttons of a page are called the same but those of one minute', buttons.length === 1
		&& named('2026-11-24.4') === text('/_admin/logs/row/open').replace( '%s', when('2026-11-24 10:15') ) && text('/_admin/logs/row/open').indexOf('%s') !== -1 );
	check( '...and a line that has no time by the plain words', named('2026-11-24.2') === text('/_admin/logs/row/open-plain') && text('/_admin/logs/row/open-plain') !== '' );

	// The card: structure only - the entry is its name, the time the second line, the account a labelled line
	check( 'under 40 rem a row is a card: the time is the second line, the account a line with its label, the entry the name', w.rows()[1].children.filter( el => el.dataset.card !== undefined ).map( el => el.dataset.card ).join() === 'second,line,primary'
		&& w.cells('2026-11-24.4')[1].dataset.label === text('/_admin/logs/label/actor') );

	// --- the search ---------------------------------------------------------------------------------

	w.search().value = 'themes';
	fire( w.search(), 'input' );
	check( 'the search narrows the rows in the browser, with no request, and reads the sentence', w.requests.length === 1 && w.ids().join() === '2026-11-23.2' );
	w.search().value = 'ends here';
	fire( w.search(), 'input' );
	check( '...all of it, also the end of a sentence that the cell cuts short', w.ids().join() === '2026-11-24.3' );
	w.search().value = 'editor@';
	fire( w.search(), 'input' );
	check( '...and the account', w.ids().join() === '2026-11-24.3' );
	w.search().value = when('2026-11-23 17:00');
	fire( w.search(), 'input' );
	check( '...and the time as it is shown, not as it is stored', w.ids().join() === '2026-11-23.1' );
	w.search().value = '2026-11-23 17:00';
	fire( w.search(), 'input' );
	check( '...and says so when it finds nothing, in the words of the module', w.ids().length === 0 && byClass( w.mount, 'nino-admin-empty' ).some( el => el.textContent === text('/_admin/logs/nomatch') ) );
	w.search().value = '';
	fire( w.search(), 'input' );
	check( 'cleared, every row is back in the order of the answer', w.ids().join() === PAIRS.map( pair => pair[0] ).join() );

	// --- the sort ---------------------------------------------------------------------------------

	const head = key => w.heads().find( el => el.dataset.key === key );
	fire( tag( head('when'), 'button' )[0], 'click' );
	const ascending = w.ids();
	check( 'a click on the time sorts by it, the oldest first, by the stored value: a line with no time stands after them', ascending.join() === [ '2026-11-23.1', '2026-11-23.2', '2026-11-24.1', '2026-11-24.3', '2026-11-24.4', '2026-11-24.5', '2026-11-24.2' ].join() );
	fire( tag( head('when'), 'button' )[0], 'click' );
	check( '...and a second click reverses it - the line with no time stays after the others, as an empty value does', w.ids().join() === [ '2026-11-24.5', '2026-11-24.4', '2026-11-24.3', '2026-11-24.1', '2026-11-23.2', '2026-11-23.1', '2026-11-24.2' ].join() );
	fire( tag( head('actor'), 'button' )[0], 'click' );
	check( 'the account sorts as well: the sort is the one of its column, and a line with no account stands last', w.ids()[0] === '2026-11-24.5' && w.ids()[5] === '2026-11-24.3' && w.ids()[6] === '2026-11-24.2' );
}

{
	// --- text from the server or a person is text ---------------------------------------------------

	const w = world();
	w.open();
	const hostile = w.cells('2026-11-24.5');

	check( 'a sentence and an account that are markup, quotes and a fill are the letters they are: no element comes of them, in the table', flat( hostile[1] ) === HOSTILE_ACCOUNT && entryOf( w, '2026-11-24.5' ) === HOSTILE
		&& tag( w.mount, 'img' ).length === 0 && markupWrites.length === 0 );
	check( '...and the search finds them as typed', ( () => { w.search().value = '[[/key]]'; fire( w.search(), 'input' ); const found = w.ids().join(); w.search().value = ''; fire( w.search(), 'input' ); return found === '2026-11-24.5' } )() );

	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const d = w.dialog();
	const body = byClass( d, 'nino-admin-messagebox' )[0] ?? { textContent : null };
	check( '...in the title and the subtitle of the record', w.titleOf( d ) === when('2026-11-24 11:40') && w.subtitleOf( d ) === HOSTILE_ACCOUNT && tag( d, 'img' ).length === 0 );
	check( '...and in the body: the whole sentence is text', body.textContent === HOSTILE && markupWrites.length === 0 );
	check( 'the script writes nothing as markup: innerHTML is only ever emptied', ( source('_admin/Nino/Modules/Logs/assets/admin.js').match( /innerHTML[^\n]*/g ) || [] ).every( use => /^innerHTML = '';$/.test( use ) ) );
}


// --- the record: one line, only read -------------------------------------------------

{
	const w = world();
	w.open();
	const before = w.h.entries.length;
	const row = w.rows().find( el => el.dataset.row === '2026-11-24.3' );
	w.panel.init();
	check( 'init of a panel that has its list reads nothing: it would put the sentence of loading in place of the table', w.requests.length === 1 && byClass( w.mount, 'nino-admin-loading' ).length === 0 );

	fire( row, 'click', { target : row } );
	const d = w.dialog();

	check( 'a click on a row opens its record, the large dialog, at #logs/logs/<key> - the key ONE part -, one entry more in the history', d !== null && hasClass( d, 'nino-admin-dialog--large' ) && w.h.hash() === '#logs/logs/2026-11-24.3' && w.h.entries.length === before + 1 );
	check( 'it is named by the time as the workbench writes it, and says the account under it', w.titleOf( d ) === when('2026-11-24 09:00') && w.subtitleOf( d ) === 'editor@example.com' );
	check( 'its content is the whole sentence, unshortened, in a box of its own - the time and the account are said once, in the head', w.labelsOf( d ).join() === text('/_admin/logs/label/text')
		&& ( byClass( d, 'nino-admin-messagebox' )[0]?.textContent ?? null ) === LONG && findAll( d, el => el.children.length === 0 && ( el.textContent === when('2026-11-24 09:00') || el.textContent === 'editor@example.com' ) ).length === 2 );
	check( 'it is only read: no field, no Save, no Cancel, no state, no button that takes away - the cross and the steps are all it has', tag( d, 'input' ).length === 0 && tag( d, 'textarea' ).length === 0 && tag( d, 'select' ).length === 0
		&& tag( d, 'form' ).length === 0 && byClass( d, 'nino-admin-actionbar-state' ).length === 0 && tag( d, 'button' ).filter( b => hasClass( b, 'nino-admin-btn-primary' ) || hasClass( b, 'nino-admin-btn-danger' ) || hasClass( b, 'nino-admin-btn-secondary' ) ).length === 0
		&& tag( d, 'button' ).every( b => b === w.cross( d ) || b.attributes['aria-label'] === text('/_admin/common/record/prev') || b.attributes['aria-label'] === text('/_admin/common/record/next') ) );
	check( '...and says its place in the rows the directory shows', ( byClass( d, 'nino-admin-record-count' )[0]?.textContent ?? null ) === '3 of 7' );

	// The other way in: the button of a row
	fire( w.cross( d ), 'click' );
	const button = tag( w.rows().find( el => el.dataset.row === '2026-11-24.4' ), 'button' )[0];
	fire( button, 'click', { currentTarget : button } );
	check( 'the button of a row opens the same record', w.dialog() !== null && w.h.hash() === '#logs/logs/2026-11-24.4' && w.titleOf( w.dialog() ) === when('2026-11-24 10:15') );
}

{
	// A line that did not parse: the sentence alone, and a name that is not empty
	const w = world();
	w.open();
	fire( w.rows().find( el => el.dataset.row === '2026-11-24.2' ), 'click', { target : w.rows()[3] } );
	const d = w.dialog();

	check( 'a line that did not parse opens with the sentence alone and a title that is not empty - the word "Entry" - and no subtitle', w.labelsOf( d ).join() === text('/_admin/logs/label/text') && ( byClass( d, 'nino-admin-messagebox' )[0]?.textContent ?? null ) === 'a line that is no line of the log'
		&& w.titleOf( d ) === text('/_admin/logs/record/title-plain') && w.titleOf( d ) !== '' && w.subtitleOf( d ) === '' );
}

{
	// Stepping goes along the rows the directory shows
	const w = world();
	w.open();
	w.search().value = 'admin@example.com';
	fire( w.search(), 'input' );
	check( 'the search narrows the rows the record walks', w.ids().join() === '2026-11-24.4,2026-11-24.1,2026-11-23.2,2026-11-23.1' );
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const d = w.dialog();
	const entries = w.h.entries.length;
	const next = tag( d, 'button' ).find( b => b.attributes['aria-label'] === text('/_admin/common/record/next') );

	fire( next, 'click' );
	check( 'the step to the next goes to the next row of those that are shown - not of all lines -, and the address follows without a new entry', w.titleOf( d ) === when('2026-11-24 08:30') && w.h.hash() === '#logs/logs/2026-11-24.1' && w.h.entries.length === entries
		&& ( byClass( d, 'nino-admin-record-count' )[0]?.textContent ?? null ) === '2 of 4' );

	fire( w.cross( d ), 'click' );
	w.h.back();
	w.h.router.arrive();
	check( 'after the record is closed the directory is as it was: the search is still there, and the focus is on the row', w.search().value === 'admin@example.com' && w.ids().length === 4 && w.rows().find( el => el.dataset.row === '2026-11-24.1' ).focused === true );
}

{
	// The address: a reload opens directory and record, Back closes it
	const w = world('#logs/logs/2026-11-24.3');

	w.panel.showCurrent();
	check( 'a reload with an address that names a line draws the directory first and opens the record once the list has come', w.dialog() === null && w.requests.length === 1 );
	w.answer( 200, answerOf( PAIRS ) );
	check( '...the list is there behind it, and the record is the one the address names', w.ids().length === 7 && w.dialog() !== null && w.titleOf( w.dialog() ) === when('2026-11-24 09:00') && w.h.hash() === '#logs/logs/2026-11-24.3' );

	const asked = w.requests.length;
	w.step('#logs/logs');
	check( 'the address of the directory closes the record, and the list is not read again for that', w.dialog() === null && w.requests.length === asked && w.h.hash() === '#logs/logs' );

	// Back from a record that a person opened: closed, the focus on its row
	const v = world();
	v.open();
	fire( v.rows()[1], 'click', { target : v.rows()[1] } );
	const opened = v.requests.length;
	v.back();
	check( 'Back closes the record, reads nothing, and the focus is on the row', v.dialog() === null && v.requests.length === opened && v.h.hash() === '' + '#logs' && v.rows()[1].focused === true );

	fire( v.rows()[1], 'click', { target : v.rows()[1] } );
	fire( v.dialog(), 'keydown', { key : 'Escape' } );
	check( 'Esc closes it as well, and the directory is not drawn again', v.dialog() === null && v.rows()[1].focused === true && v.requests.length === opened );
}

{
	// A key that is not there: said over the directory, and the address is the directory's
	const w = world('#logs/logs/2026-11-24.99');
	w.panel.showCurrent();
	w.answer( 200, answerOf( PAIRS ) );
	check( 'a key that is not there leaves the directory, says so over it in the words of the module, and the address is the directory\'s', w.dialog() === null && w.ids().length === 7
		&& byClass( w.mount, 'nino-admin-notice' ).length === 1 && byClass( w.mount, 'nino-admin-notice' )[0].textContent === text('/_admin/logs/record/missing') && text('/_admin/logs/record/missing') !== '' && w.h.hash() === '#logs' );
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	check( '...and the sentence is gone when a line is opened', byClass( w.mount, 'nino-admin-notice' ).length === 0 );

	// A key that holds a slash, a percent sign and an at sign is read as ONE key, and is simply not found
	const keys = [ '#logs/logs/a%2Fb%25c%40d', '#logs/logs/%40new', '#logs/logs/2026-11-24.3%2F1' ];

	check( 'a key with a slash, a percent sign or an at sign is read as a key and not found - a notice, no error', keys.every( address => {
		const v = world( address );
		v.panel.showCurrent();
		v.answer( 200, answerOf( PAIRS ) );
		return v.dialog() === null && v.ids().length === 7 && byClass( v.mount, 'nino-admin-notice' ).length === 1 && byClass( v.mount, 'nino-admin-error' ).length === 0 && v.h.hash() === '#logs';
	} ) );

	// An address that is no address of this screen: the directory, without a notice
	const t = world('#logs/logs/ab%zz');
	t.panel.showCurrent();
	t.answer( 200, answerOf( PAIRS ) );
	check( 'a part that cannot be decoded is no key: the directory shows, and the address is the directory\'s', t.dialog() === null && t.h.hash() === '#logs' && byClass( t.mount, 'nino-admin-notice' ).length === 0 );
	const x = world('#logs/logs/2026-11-24.3/extra');
	x.panel.showCurrent();
	x.answer( 200, answerOf( PAIRS ) );
	check( 'an address with a part too many is the directory\'s', x.dialog() === null && x.h.hash() === '#logs' );
}


// --- the list read again ---------------------------------------------------------------

{
	const w = world();
	w.open( PAIRS.concat( FILLER ) );

	check( 'a page holds 50 rows', w.ids().length === 50 );
	w.search().value = 'line';
	fire( w.search(), 'input' );
	fire( w.next(), 'click' );
	const second = w.ids();

	check( 'a search and the second page are chosen', w.search().value === 'line' && second.length === 11 && second[0] === '2026-11-01.11' );

	const asked = w.requests.length;
	w.panel.showCurrent();
	check( 'showing the panel again reads the list again, quietly: the table is still on screen, without the sentence that it is loading', w.requests.length === asked + 1 && w.requests[asked].action === 'logs/list' && byClass( w.mount, 'nino-admin-loading' ).length === 0 && w.ids().join() === second.join() );

	const fresh = [ [ '2026-11-24.6', '2026-11-24 12:00  admin@example.com  A new line of the filler kind' ] ];
	w.answer( 200, answerOf( fresh.concat( PAIRS, FILLER ) ) );
	check( '...what came in meanwhile is among the rows, and the search and the page are what the person chose', w.search().value === 'line' && w.ids().length === 12 && w.ids()[0] === '2026-11-01.12' );

	// The sort stays as well
	fire( tag( w.heads().find( el => el.dataset.key === 'when' ), 'button' )[0], 'click' );
	const order = w.ids().join();
	w.panel.showCurrent();
	w.answer( 200, answerOf( fresh.concat( PAIRS, FILLER ) ) );
	check( '...and so does the sort', w.ids().join() === order && w.heads().find( el => el.dataset.key === 'when' ).attributes['aria-sort'] === 'ascending' );
}

{
	// While the record is open, nothing is read
	const w = world();
	w.open();
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	const asked = w.requests.length;
	w.panel.showCurrent();
	check( 'the panel shown while a record is open reads nothing and leaves the record as it is', w.requests.length === asked && w.dialog() !== null && w.h.hash() === '#logs/logs/2026-11-24.5' );

	// A step of the history that is this panel's own address is followed, not read over
	w.step('#logs/logs/2026-11-24.4');
	check( 'an address of another line of this panel is followed - the record shows that line - and the list is not read for it', w.requests.length === asked && w.titleOf( w.dialog() ) === when('2026-11-24 10:15') );

	// The directory's address closes it; the panel entered from another one then reads again
	w.step('#logs/logs');
	check( 'the address of the directory closes the record without a read', w.dialog() === null && w.requests.length === asked );
	w.h.box.location.hash = '#users';
	w.h.router.arrive();
	w.panel.showCurrent();
	check( 'an address of another panel, with no record open, reads the list again', w.requests.length === asked + 1 );
	w.answer( 200, answerOf( PAIRS ) );
	check( '...and the record that an address names keeps its line when the list is read again: the key still names it', ( () => {
		w.step('#logs/logs/2026-11-24.4');
		return w.dialog() !== null && w.titleOf( w.dialog() ) === when('2026-11-24 10:15');
	} )() );
}

{
	// Only one read at a time
	const w = world();
	w.panel.showCurrent();
	w.panel.showCurrent();
	w.panel.init();
	check( 'a list that is being read is not asked for again', w.requests.length === 1 );
}


// --- a list that is empty, one that fails ------------------------------------------------

{
	const w = world();
	w.open( [] );
	check( 'a log without a line is the box of an empty state in the directory, in words of the module', w.ids().length === 0 && byClass( w.mount, 'nino-admin-empty' ).some( el => el.textContent === text('/_admin/logs/empty') ) );
}

{
	const w = world();
	w.panel.showCurrent();
	w.answer( 403, { error : 'no' } );
	check( 'a list that could not be loaded is the error in place of the list', byClass( w.mount, 'nino-admin-error' ).length === 1 && w.ids().length === 0 && byClass( w.mount, 'nino-admin-loading' ).length === 0 );
	w.panel.showCurrent();
	check( '...and the panel shown again asks again, and shows the list once it is there', w.requests.length === 2 && ( w.answer( 200, answerOf( PAIRS ) ), w.ids().length === 7 && byClass( w.mount, 'nino-admin-error' ).length === 0 ) );

	// A failure while the list is on screen stands in its place as well
	w.panel.showCurrent();
	w.answer( 500, { error : 'down' } );
	check( 'a list that cannot be read again stands as the error in place of the table', byClass( w.mount, 'nino-admin-error' ).length === 1 && w.ids().length === 0 );
}

{
	// An answer that does not say what the key of every line is, is a failed load - a key is not guessed
	const good = answerOf( PAIRS );
	const answers = {
		'without keys' : { lines : good.lines },
		'keys that are no list' : { lines : good.lines, keys : 'a' },
		'fewer keys than lines' : { lines : good.lines, keys : good.keys.slice( 1 ) },
		'a key that is no text' : { lines : good.lines, keys : good.keys.map( ( key, at ) => at === 2 ? 3 : key ) },
		'an empty key' : { lines : good.lines, keys : good.keys.map( ( key, at ) => at === 2 ? '' : key ) },
		'a key twice' : { lines : good.lines, keys : good.keys.map( ( key, at ) => at === 2 ? good.keys[1] : key ) },
		'no lines' : { keys : good.keys },
		'nothing' : null,
	};

	Object.keys( answers ).forEach( name => {
		const w = world();
		w.panel.showCurrent();
		w.answer( 200, answers[name] );
		check( 'an answer with '+ name+ ' is a failed load: the error stands in place of the list, no row is drawn', byClass( w.mount, 'nino-admin-error' ).length === 1 && w.ids().length === 0 );
	} );
}


// --- the requests -------------------------------------------------------------------------

{
	const w = world();
	w.open();
	fire( w.rows()[0], 'click', { target : w.rows()[0] } );
	w.step('#logs/logs/2026-11-24.4');
	w.step('#logs/logs');
	check( 'the panel calls logs/list and nothing else - it writes nothing - and every call goes to the one door of the workbench, as a POST', w.requests.length === 1 && w.requests.every( r => r.action === 'logs/list' && r.method === 'POST' && r.uri.endsWith('/_admin/') ) );
}


// --- every key the script asks for is a fill in both languages ------------------------------

const asked = new Set();

for( const found of source('_admin/Nino/Modules/Logs/assets/admin.js').matchAll( /'(\/_admin\/logs\/[a-z0-9\/_-]+)'/g ) )
	asked.add( found[1] );

const lacking = Array.from( asked ).filter( key => ( moduleEn[key] ?? workbenchEn[key] ) === undefined || ( moduleDe[key] ?? workbenchDe[key] ) === undefined );
check( 'the script asks for '+ asked.size+ ' keys, and each has words in English and in German'+ ( lacking.length === 0 ? '' : ' - not '+ lacking.join(', ') ), asked.size >= 10 && lacking.length === 0 );

const unused = Object.keys( moduleEn ).filter( key => key.indexOf('/_admin/logs/manual/') !== 0 && key !== '/_admin/nav/logs' && asked.has( key ) === false );
check( 'no fill of the module is left that nothing reads'+ ( unused.length === 0 ? '' : ' - '+ unused.join(', ') ), unused.length === 0 );
check( 'the two languages have the same fills', Object.keys( moduleEn ).sort().join() === Object.keys( moduleDe ).sort().join() );

done();
