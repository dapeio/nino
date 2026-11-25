/**
 *	Nino										A compact filesystembased php framework
 *	admin-users-js-smoke.js	DOM-light checks for the Users panel's script
 *													(_admin/Nino/Modules/Users/assets/admin.js) over the real
 *													shell (adminPage() of the harness): the panel is the standard
 *													directory of the accounts - the role is its category, "Show"
 *													narrows to the active, the deactivated or the locked ones - and
 *													an account is edited in the record over it, a new one is made in
 *													the same dialog.
 *
 *													One Save writes address, name, password and - for a manager
 *													editing somebody else - the role in one users/save; what was not
 *													changed is not sent, and a role that was not changed is not sent
 *													(a manager who only renames an account wider than their own role
 *													must not meet the checks of a role change). A password is sent
 *													once and kept nowhere. Log out everywhere, deactivate and activate
 *													act at once and keep what was typed, delete asks. An account that
 *													holds more than the signed-in one stands in the table with a badge
 *													and opens no record: its row says why, and nothing is asked of the
 *													server for it.
 *
 *													Every view has its address - the directory, an account, a new one
 *													- which survives a reload; every refusal of the server that names
 *													a field stands at that field; everything a person or the server
 *													wrote is only ever text. The words the four scripts of the panel
 *													name (this one, roles, login protection, recovery password) are
 *													held here.
 *
 *	Usage: node tests/admin-users-js-smoke.js
 */

'use strict';

const { check, done, source, fills, load, element, findAll, fire, shellRouter, adminPage, markupWrites } = require( './harness.js' );

// What the panel writes into the address goes through the router's own codec: every state written, every link drawn, is read back
const codec = shellRouter();

const moduleEn	= fills('_admin/Nino/Modules/Users/text/en_US.php');
const moduleDe	= fills('_admin/Nino/Modules/Users/text/de_DE.php');
const workbench	= fills('_admin/text/en_US.php');
const workbenchDe = fills('_admin/text/de_DE.php');

const text = key => moduleEn[key] ?? workbench[key] ?? '';

const has = ( el, name ) => el.classList.contains( name );
const byClass = ( root, name ) => findAll( root, el => has( el, name ) );
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );
const errorsOf = root => byClass( root, 'nino-admin-field-error' );
// What an element says, in reading order: the stand-in's textContent is the element's own text, a cell made of elements is the text of them
const flat = el => ( el.children ?? [] ).length > 0 ? ( el.textContent ?? '' )+ el.children.map( flat ).join('') : ( el.textContent ?? '' );

const SAVE = '/_admin/common/label/save';
const CREATE = '/_admin/users/label/create';
const DELETE = '/_admin/users/label/delete';
const LOGOUT = '/_admin/users/label/logoutall';
const DEACTIVATE = '/_admin/users/label/deactivate';
const ACTIVATE = '/_admin/users/label/activate';
const CANCEL = '/_admin/common/label/cancel';
const PASSWORD = 'a-new-password';

// --- what the server answers --------------------------------------------------------------------------------------------

const ROLES = [ { id : 'dev', label : 'Developer', canGrant : true }, { id : 'editor', label : 'Editor', canGrant : true } ];

function account( mail, extra ) {
	return Object.assign( { mail : mail, isSelf : false, role : 'editor', name : '', perms : [], status : 'active', locked : '', lastLogin : '', canChange : true }, extra || {} );
}

// The stored order is not the alphabetical one: the table starts in it
function accounts() {
	return [
		account( 'me@example.com', { isSelf : true, role : 'dev', name : 'Mia', lastLogin : '2026-10-05 10:00' } ),
		account( 'b@example.com', { name : 'Bo', lastLogin : '2026-10-04 08:00' } ),
		account( 'a@example.com', { status : 'disabled', locked : '2026-10-05 15:00' } ),
	];
}

function listing( users, extra ) {
	return Object.assign( { users : users ?? accounts(), canManage : true, roles : ROLES.map( role => Object.assign( {}, role ) ) }, extra || {} );
}

// --- the page ------------------------------------------------------------------------------------------------------------

function world( hash ) {

	const h = adminPage();
	const mount = element('div');
	const requests = [];
	const toasts = [];
	const replaced = [];

	mount.id = 'users-list';
	h.page.appendChild( mount );
	h.show('users');
	h.router.start();
	h.router.set( 'users', [] );
	h.Nino.content.getText = text;
	h.Nino.http = { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} };
	h.box.location.replace = function( to ) { replaced.push( to ) };

	// The rail names the account the page is for
	const rail = element('span');
	rail.id = 'admin-user-email';
	rail.textContent = 'me@example.com';
	h.page.appendChild( rail );

	const say = h.ui.say;
	h.ui.say = function( words, options ) { toasts.push( words ); return say.call( this, words, options ) };

	// Every address the panel writes or links to is read back by the router as the same screen and the same parts
	[ 'set', 'go', 'href' ].forEach( name => {
		const real = h.router[name];
		h.router[name] = function( screen, parts ) { codec.check( screen, parts ); return real.call( this, screen, parts ) };
	} );

	if( hash !== undefined )
		h.box.location.hash = hash;

	// The question that stands on the record, with what it was given
	const questions = [];
	const confirm = h.ui.confirm;
	h.ui.confirm = function( level, options ) { questions.push( Object.assign( { level : level }, options ) ); return confirm.call( this, level, options ) };

	load( '_admin/Nino/Modules/Users/assets/admin.js', h.context );

	const panel = h.admin.users;
	const answer = ( status, body ) => { const request = requests[requests.length - 1]; request.callback( { status : status, responseJSON : body } ); return request };
	const open = answered => { panel.showCurrent(); answer( 200, answered ?? listing() ) };
	const rows = () => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined );
	const dialog = () => h.dialogs.filter( d => d.open ).pop() ?? null;

	const w = { h, panel, mount, requests, toasts, questions, replaced, rail, answer, open, rows, dialog,
		ids : () => rows().map( el => el.dataset.row ),
		row : key => rows().find( el => el.dataset.row === key ),
		cells : key => w.row( key ).children.filter( el => el.tagName === 'TD' ),
		search : () => tag( mount, 'input' ).find( el => el.type === 'search' ),
		heads : () => findAll( mount, el => el.tagName === 'TH' && el.dataset.key !== undefined ),
		head : key => w.heads().find( el => el.dataset.key === key ),
		selects : () => tag( mount, 'select' ),
		select : label => w.selects().find( el => el.attributes['aria-label'] === label || ( el.closest !== undefined && findAll( el.parentNode ?? mount, c => c.textContent === label ).length > 0 ) ),
		// The buttons at the end of a row, in the order they stand in
		buttons : key => byClass( w.row( key ), 'nino-admin-row-actions' ).flatMap( el => el.children ),
		edit : key => w.buttons( key )[0],
		bar : () => byClass( mount, 'nino-admin-actionbar' ).filter( el => has( el, 'nino-admin-list-actions' ) )[0],
		newButton : () => w.bar() === undefined ? undefined : tag( w.bar(), 'button' )[0],
		notice : () => byClass( mount, 'nino-admin-notice' ).map( el => el.textContent ),
		// The record is the dialog of the class record, the questions on it are the others
		record : () => h.dialogs.filter( d => d.open && has( d, 'nino-admin-record' ) ).pop() ?? null,
		asking : () => { const top = dialog(); return top !== null && has( top, 'nino-admin-record' ) === false ? top : null },
		answerAsk : key => { const top = w.asking(); if( top !== null ) fire( tag( top, 'button' ).find( b => b.textContent === text( key ) ), 'click' ); return top },
		button : ( d, key ) => tag( d, 'button' ).find( b => b.textContent === text( key ) || b.attributes['aria-label'] === text( key ) ),
		shown : ( d, key ) => { const b = w.button( d, key ); return b !== undefined && b.hidden !== true },
		bartext : d => tag( byClass( d, 'nino-admin-record-bar' )[0], 'button' ).filter( b => b.hidden !== true ).map( b => b.textContent ),
		kinds : d => tag( byClass( d, 'nino-admin-record-bar' )[0], 'button' ).filter( b => b.hidden !== true ).map( b => [ 'danger', 'primary', 'secondary' ].find( kind => has( b, 'nino-admin-btn-'+ kind ) ) ?? 'plain' ).join(),
		stateOf : d => byClass( d, 'nino-admin-actionbar-state' )[0],
		lineOf : d => byClass( d, 'nino-admin-status' )[0],
		titleOf : d => byClass( d, 'nino-admin-dialog-title' )[0]?.textContent ?? null,
		subtitleOf : d => byClass( d, 'nino-admin-dialog-subtitle' )[0]?.textContent ?? '',
		field : ( d, name ) => findAll( d, el => el.dataset.field === name )[0],
		fieldById : ( d, id ) => findAll( d, el => el.id === id )[0],
		// The line of the record that is read and not typed in: its name and its value
		readLine : ( d, label ) => byClass( d, 'nino-admin-readline' ).find( el => flat( byClass( el, 'nino-admin-readline-label' )[0] ).startsWith( label ) ),
		readValue : ( d, label ) => flat( byClass( w.readLine( d, label ), 'nino-admin-readline-value' )[0] ),
		form : d => tag( d, 'form' )[0],
		openRow : key => { fire( w.edit( key ), 'click', { currentTarget : w.edit( key ) } ); return w.record() },
		cross : d => tag( d, 'button' ).find( b => has( b, 'nino-admin-dialog-close' ) ),
		// The cross, and the step back through the history that it asks the browser for (the stand-in only notes it): the shell arrives and the panel is shown
		close : d => { const asked = h.went.length; fire( w.cross( d ), 'click' ); if( h.went.length > asked && w.record() === null ) w.back() },
		submit : d => fire( tag( d, 'form' )[0], 'submit' ),
		save : d => fire( w.button( d, SAVE ), 'click' ),
		type : ( el, value ) => {
			el.value = value;
			// A field that stands in a form tells the form: what listens for the state of the bar and the shell's registry does
			let up = el;
			while( up !== null && up !== undefined && up.tagName !== 'FORM' )
				up = up.parentNode;
			fire( up ?? el, 'input', { target : el } );
			h.dirty.refresh();
		},
		focused : () => findAll( h.page, el => el.focused === true )[0] ?? null,
		tree : () => requests.map( r => r.action ).join(),
		last : () => requests[requests.length - 1],
		// An address that is typed or followed: a new entry of the history, which the shell arrives at and the panel is shown
		typed : address => { h.box.history.pushState( { nino : h.index() + 1 }, '', address ); h.router.arrive(); panel.showCurrent(); h.hashchange() },
		step : address => { h.box.location.hash = address; h.router.arrive(); panel.showCurrent(); h.hashchange() },
		back : () => { h.back(); h.router.arrive(); panel.showCurrent(); h.hashchange() },
		forward : () => { h.forward(); h.router.arrive(); panel.showCurrent(); h.hashchange() },
		// The panel is left for another one and shown again
		leaveAndReturn : () => { h.router.set( 'users', [] ); panel.showCurrent() },
	};

	return w;
}

( async function() {

console.log('Admin Users');

// --- the directory: loading, the rows, the cells ------------------------------------------------------------------------------

{
	const w = world();

	w.panel.showCurrent();
	check( 'showing the panel asks the server for the list, in one request, and for nothing else', w.requests.length === 1 && w.requests[0].action === 'users/list' && JSON.stringify( w.requests[0].payload ) === '{}' );
	check( 'while it loads the sentence stands in place of the table, and says it is busy', byClass( w.mount, 'nino-admin-loading' )[0].textContent === text('/_admin/users/loading') && text('/_admin/users/loading') !== ''
		&& byClass( w.mount, 'nino-admin-directory-body' )[0].attributes['aria-busy'] === 'true' );
	check( '...and there is no button to make an account yet: whether this account may is not known', w.newButton() === undefined );

	w.answer( 200, listing() );
	check( 'every account is a row of the standard directory, in the order the server stored them in, keyed by its address', w.ids().join() === 'me@example.com,b@example.com,a@example.com' && byClass( w.mount, 'nino-admin-directory' ).length === 1 );
}

{
	const w = world();

	w.open();

	check( 'the columns are the account, the name, the role, the state and the last login - and no box to choose rows by', w.heads().map( el => el.dataset.key ).join() === 'mail,name,roleLabel,stateText,lastLogin'
		&& tag( w.mount, 'input' ).filter( el => el.type === 'checkbox' ).length === 0 );
	check( '...each is headed by its word, in the words of the panel', w.heads().map( flat ).join() === [ 'col/account', 'label/name', 'label/role', 'label/state', 'col/lastlogin' ].map( key => text('/_admin/users/'+ key ) ).join() && text('/_admin/users/col/account') === 'Account' );
	check( '...the rows are in the order the server gave and no column is sorted', w.heads().every( el => el.attributes['aria-sort'] === undefined || el.attributes['aria-sort'] === 'none' ) );

	const me = w.cells('me@example.com');
	check( 'the account is its address, strong and as text, with "(you)" behind it for the signed-in account only', byClass( me[0], 'users-account' )[0].children[0].tagName === 'STRONG' && byClass( me[0], 'users-account' )[0].children[0].textContent === 'me@example.com'
		&& flat( byClass( me[0], 'users-account' )[0] ) === 'me@example.com ('+ text('/_admin/users/label/you')+ ')' && flat( byClass( w.cells('b@example.com')[0], 'users-account' )[0] ) === 'b@example.com' );
	check( '...then its name, its role - the name of the role, not its id - and its last login as the workbench writes a moment', flat( me[1] ) === 'Mia' && flat( me[2] ) === 'Developer' && me[4].textContent === '2026-10-05, 10:00'
		&& flat( w.cells('b@example.com')[2] ) === 'Editor' && flat( w.cells('a@example.com')[1] ) === '' );
	check( 'a long address ends in an ellipsis and stands whole in its tooltip, a long role is clamped to one line with its whole name as the tooltip', byClass( me[0], 'users-account' )[0].title === 'me@example.com' && byClass( me[2], 'nino-admin-table-clamp' ).length === 1 && byClass( me[2], 'nino-admin-table-clamp' )[0].title === 'Developer' );
	check( '...an account that never logged in has the quiet dash of the table for its last login, and the record says that it never did', w.cells('a@example.com')[4].classList.contains('is-empty') === true && w.cells('a@example.com')[4].textContent === '' );

	// The state: words in badges, nothing where none applies
	const badgesOf = key => byClass( w.cells( key )[3], 'nino-admin-badge' ).map( b => b.textContent+ ':'+ b.dataset.state );
	check( 'a deactivated and locked account says both as words in badges - Deactivated, Locked until the time as the workbench writes it', badgesOf('a@example.com').join() === 'Deactivated:muted,Locked:warn' && byClass( w.cells('a@example.com')[3], 'nino-admin-badge' )[1].title === 'Locked until 2026-10-05, 15:00' && text('/_admin/users/badge/locked') === 'Locked until %s' );
	check( '...an account that is neither has no badge and nothing in the cell', badgesOf('b@example.com').length === 0 && w.cells('b@example.com')[3].textContent === '' && badgesOf('me@example.com').length === 0 );

	// The buttons of a row: the pencil, named for the account
	check( 'a row has one button, the pencil, named for the account it opens, which the keyboard opens the row by', w.buttons('b@example.com').length === 1 && w.edit('b@example.com').attributes['aria-label'] === 'Edit user “b@example.com”' && text('/_admin/users/row/open') === 'Edit user “%s”' );

	// The bar of the list
	check( 'the bar of the list has one button, the main one: New user, for an account that may manage users', tag( w.bar(), 'button' ).length === 1 && has( w.newButton(), 'nino-admin-btn-primary' ) && w.newButton().textContent === text('/_admin/users/label/new') );
	check( 'the search says what it searches in its own words', w.search().attributes['aria-label'] === text('/_admin/users/label/search') && text('/_admin/users/label/search') === 'Search users' );
}

// --- the category, the select Show, the search, the order ----------------------------------------------------------------------

{
	const w = world();
	const many = accounts().concat( [
		account( 'w@example.com', { role : 'ghost', perms : [ '/*' ], name : 'Wanda', lastLogin : '2026-11-01 09:30' } ),
		account( 'n@example.com', { role : '', perms : [], name : 'Nobody' } ),
		account( 'k@example.com', { role : 'dev', name : 'Kai', lastLogin : '2026-09-30 12:00', locked : '2026-12-24 08:00' } ),
	] );
	w.open( listing( many ) );

	const category = w.selects().find( el => el.id === 'users-role' || findAll( el, o => o.textContent === text('/_admin/users/filter/allroles') ).length > 0 );
	const labelsOf = select => findAll( select, el => el.tagName === 'OPTION' ).map( el => el.textContent );
	const showSelect = w.selects().find( el => findAll( el, o => o.textContent === text('/_admin/users/filter/locked') ).length > 0 );
	const pick = ( select, value ) => { select.value = value; fire( select, 'change', { target : select } ) };

	check( 'left of the search stands the category - the role: "All roles", then every role that an account holds, by its name, then Full access and No role where accounts fall under them', labelsOf( category ).join() === 'All roles,Developer,Editor,Full access,No role' );
	check( '...a role that no account holds is not offered', ( () => { const v = world(); v.open( listing( accounts().map( user => Object.assign( user, { role : 'dev' } ) ).concat( [ account( 'z@example.com', { role : 'dev' } ) ] ) ) ); return v.selects().filter( el => findAll( el, o => o.textContent === 'Editor' ).length > 0 ).length === 0 } )() );
	check( '...and there is no category at all where every account stands under one role: a select with one choice narrows nothing', ( () => { const v = world(); v.open( listing( [ account( 'z@example.com' ), account( 'y@example.com' ) ] ) ); return v.selects().filter( el => findAll( el, o => o.textContent === text('/_admin/users/filter/allroles') ).length > 0 ).length === 0 } )() );
	check( 'the one select after the search is "Show": all, active, deactivated, locked', labelsOf( showSelect ).join() === 'All accounts,Active,Deactivated,Locked' && showSelect.attributes['aria-label'] === 'Show' );

	pick( category, 'dev' );
	check( 'a role narrows the table to the accounts that hold it', w.ids().join() === 'me@example.com,k@example.com' );
	pick( category, '@full' );
	check( '...full access to the account whose role is none there is and whose permissions are everything', w.ids().join() === 'w@example.com' );
	pick( category, '@none' );
	check( '...and no role to the one that holds nothing', w.ids().join() === 'n@example.com' );
	pick( category, '' );
	check( '...and "All roles" is every account again', w.ids().length === 6 );

	pick( showSelect, 'active' );
	check( 'Show: active - the accounts that are not deactivated, a locked one among them', w.ids().join() === 'me@example.com,b@example.com,w@example.com,n@example.com,k@example.com' );
	pick( showSelect, 'disabled' );
	check( '...deactivated', w.ids().join() === 'a@example.com' );
	pick( showSelect, 'locked' );
	check( '...locked - a deactivated account that is locked as well too', w.ids().join() === 'a@example.com,k@example.com' );
	pick( category, 'dev' );
	check( '...and the selects narrow together', w.ids().join() === 'k@example.com' );
	pick( category, '' );
	pick( showSelect, '' );
	check( '...all is every account', w.ids().length === 6 );

	// The search: the address, the name, the role
	const found = query => { w.type( w.search(), query ); return w.ids().join() };
	check( 'the search finds an account by its address', found( 'w@exa' ) === 'w@example.com' );
	check( '...by its name', found( 'wanda' ) === 'w@example.com' && found( 'kai' ) === 'k@example.com' );
	check( '...by the name of its role', found( 'developer' ) === 'me@example.com,k@example.com' && found( 'full access' ) === 'w@example.com' && found( 'no role' ) === 'n@example.com' );
	check( '...and says that it finds nothing in its own words', found( 'zzz' ) === '' && byClass( w.mount, 'nino-admin-empty' ).filter( el => el.textContent === text('/_admin/users/nomatch') ).length === 1 && text('/_admin/users/nomatch') !== '' );
	w.type( w.search(), '' );

	// Sorting: the account, the name, the role and the last login; not the state
	const sortBy = key => { fire( tag( w.head( key ), 'button' )[0], 'click' ); return w.ids().join() };
	check( 'the heads of the account, the name, the role and the last login are buttons that sort, the head of the state is a word', [ 'mail', 'name', 'roleLabel', 'lastLogin' ].every( key => tag( w.head( key ), 'button' ).length === 1 ) && tag( w.head('stateText'), 'button' ).length === 0 );
	check( 'a click on the head of the account sorts by it, ascending - and a second descending', sortBy('mail') === 'a@example.com,b@example.com,k@example.com,me@example.com,n@example.com,w@example.com' && sortBy('mail') === 'w@example.com,n@example.com,me@example.com,k@example.com,b@example.com,a@example.com' );
	check( '...the last login sorts as a moment, the accounts that never logged in last whichever way it runs', sortBy('lastLogin') === 'k@example.com,b@example.com,me@example.com,w@example.com,a@example.com,n@example.com' && sortBy('lastLogin') === 'w@example.com,me@example.com,b@example.com,k@example.com,a@example.com,n@example.com' );
}

{
	// An account that may not manage users is given its own row only: the same directory, with no category and no bar
	const w = world();
	w.open( listing( [ accounts()[0] ], { canManage : false } ) );
	check( 'an account that may not manage users sees one row, its own', w.ids().join() === 'me@example.com' );
	check( '...with no category, no bar and no button to make an account', w.selects().filter( el => findAll( el, o => o.textContent === text('/_admin/users/filter/allroles') ).length > 0 ).length === 0 && w.bar() === undefined && w.newButton() === undefined );
}

// --- foreign text is text -------------------------------------------------------------------------------------------------------

{
	// A mail, a name and the label of a role that hold markup, a quote, a fill token - and a mail with the characters an address may hold
	const HOSTILE = '<img src=x onerror=alert(1)> "q" [[/key]]';
	const WILD = '"odd/local+part%20"@example.com';
	const w = world();
	const hostile = [
		account( 'me@example.com', { isSelf : true, role : 'dev', name : 'Mia' } ),
		account( HOSTILE+ '@example.com', { name : HOSTILE, role : 'weird', locked : HOSTILE, lastLogin : HOSTILE } ),
		account( WILD, { name : 'Wild', role : 'dev' } ),
	];
	w.open( listing( hostile, { roles : [ { id : 'dev', label : 'Developer', canGrant : true }, { id : 'weird', label : HOSTILE, canGrant : true } ] } ) );

	const evil = hostile[1].mail;
	const cells = w.cells( evil );
	check( 'a mail, a name and the label of a role that hold markup are text in the table - no element is made of them', byClass( cells[0], 'users-account' )[0].children[0].textContent === evil && flat( cells[1] ) === HOSTILE && flat( cells[2] ) === HOSTILE
		&& findAll( w.mount, el => el.tagName === 'IMG' ).length === 0 && markupWrites.length === 0 );
	check( '...a moment that is none - the time of a lock, of a login - is the text it is, in its badge and in its cell', byClass( cells[3], 'nino-admin-badge' )[0].title === 'Locked until '+ HOSTILE && cells[4].textContent === HOSTILE );
	check( '...and the pencil is named by the mail as text', w.edit( evil ).attributes['aria-label'] === 'Edit user “'+ evil+ '”' );

	const d = w.openRow( evil );
	check( 'the head of the record has the mail as its title and the name under it, as text', w.titleOf( d ) === evil && w.subtitleOf( d ) === HOSTILE && findAll( d, el => el.tagName === 'IMG' ).length === 0 );
	check( '...and the fields hold them as values, the state line says them as text', w.field( d, 'mail' ).value === evil && w.field( d, 'name' ).value === HOSTILE && w.readValue( d, text('/_admin/users/label/state') ) === 'locked until '+ HOSTILE+ ' · Last login: '+ HOSTILE && markupWrites.length === 0 );
	check( '...the address of the record is ONE part, read back by the router as the same account', codec.read( w.h.hash() ).parts.length === 1 && codec.read( w.h.hash() ).parts[0] === evil && codec.read( w.h.hash() ).known === true );

	fire( w.button( d, DELETE ), 'click' );
	check( 'the question of a delete does not repeat the address as markup: its words are the fills', w.questions.at(-1).message === text('/_admin/users/confirm/delete') && findAll( w.asking(), el => el.tagName === 'IMG' ).length === 0 && markupWrites.length === 0 );
	w.answerAsk( CANCEL );
	w.close( d );

	// A mail with characters of its own: the address is one part, which reads back and opens the account
	const v = world( codec.href( 'users', [ WILD ] ) );
	v.panel.showCurrent();
	v.answer( 200, listing( hostile, { roles : ROLES } ) );
	check( 'a mail with a quoted local part, a slash, a plus and a percent sign has an address that reads back, and a reload on it opens the account', v.record() !== null && v.titleOf( v.record() ) === WILD && v.field( v.record(), 'mail' ).value === WILD );
	const sent = v.requests.length;
	v.type( v.field( v.record(), 'name' ), 'Wilder' );
	v.save( v.record() );
	check( '...and a save names it as the account to write, whole', v.requests.length === sent + 1 && v.last().payload.username === WILD );
}

{
	// Two accounts that differ in the case of a letter are two accounts: the key is the address as it is stored
	const w = world();
	w.open( listing( [ accounts()[0], account( 'Case@Example.com', { name : 'Upper' } ), account( 'case@example.com', { name : 'Lower' } ) ] ) );
	const d = w.openRow('Case@Example.com');
	check( 'an address is a key as it is written: the record of Case@Example.com is that account, and no other', w.titleOf( d ) === 'Case@Example.com' && w.subtitleOf( d ) === 'Upper' && w.h.hash() === '#users/users/Case%40Example.com' );
	w.close( d );
	check( '...and the account that differs in the case is another', w.openRow('case@example.com') !== null && w.subtitleOf( w.record() ) === 'Lower' );
}

{
	// More accounts than a page holds: the page the person is on stays through the quiet reading
	const w = world();
	const crowd = [ accounts()[0] ];
	for( let i = 0; i < 59; i++ )
		crowd.push( account( 'user'+ String( i ).padStart( 2, '0' )+ '@example.com' ) );
	w.open( listing( crowd ) );
	fire( byClass( w.mount, 'nino-admin-table-pager' )[0].querySelectorAll('button').filter( b => b.attributes['aria-label'] === 'Next page' )[0], 'click' );
	check( 'the table pages by fifty, and a person can be on its second page', w.ids().length === 10 && w.panel._directory.state().page === 2 );
	w.leaveAndReturn();
	w.answer( 200, listing( crowd ) );
	check( '...and the quiet reading of the list leaves the person on that page', w.panel._directory.state().page === 2 && w.ids().length === 10 );
}

// --- an account beyond the reach of the signed-in one -----------------------------------------------------------------------

{
	const BEYOND = account( 'wide@example.com', { role : 'dev', name : 'Wide', canChange : false, perms : [ '/*' ] } );
	const w = world();
	w.open( listing( [ accounts()[0], accounts()[1], BEYOND ] ) );
	const asked = w.requests.length;

	check( 'an account the signed-in one may not change stands in the table with a badge that says so, in words', byClass( w.cells('wide@example.com')[3], 'nino-admin-badge' ).map( b => b.textContent ).join() === 'More rights than you' && text('/_admin/users/badge/beyond') === 'More rights than you' );
	check( '...it has no pencil - no button at all - and the account that can be changed has one', w.buttons('wide@example.com').length === 0 && w.buttons('b@example.com').length === 1 );
	check( '...its row is the one stop of the Tab key of its own, and still shows the sign that a row opens', w.row('wide@example.com').tabIndex === 0 && byClass( w.row('wide@example.com'), 'nino-admin-row-chev' ).length === 1 );

	fire( w.row('wide@example.com'), 'click', { target : w.row('wide@example.com') } );
	check( 'a click on it opens no record and says over the table why, in the sentence of the panel', w.record() === null && w.notice().join() === text('/_admin/users/label/beyond') && text('/_admin/users/label/beyond') !== '' );
	check( '...nothing is asked of the server for it, and the address is the directory\'s', w.requests.length === asked && w.h.hash() === '#users' );

	// Its address, typed or followed, says the same
	const v = world( codec.href( 'users', [ 'wide@example.com' ] ) );
	v.panel.showCurrent();
	v.answer( 200, listing( [ accounts()[0], BEYOND ] ) );
	check( 'the address of such an account opens no record either: the notice stands over the table, the address is the directory\'s and no request went out but the list', v.record() === null && v.notice().join() === text('/_admin/users/label/beyond') && v.h.hash() === '#users' && v.tree() === 'users/list' );

	const u = world();
	u.open( listing( [ accounts()[0], BEYOND, accounts()[1] ] ) );
	u.typed( codec.href( 'users', [ 'wide@example.com' ] ) );
	check( '...and so does an address that is typed while the panel is shown', u.record() === null && u.notice().join() === text('/_admin/users/label/beyond') && u.tree() === 'users/list' );
	u.typed( codec.href( 'users', [ 'nobody@example.com' ] ) );
	check( '...where an account that is not there says that it is not there, in its own sentence', u.record() === null && u.notice().join() === text('/_admin/users/record/missing') );

	// The notice goes where the next record opens
	fire( w.row('wide@example.com'), 'click', { target : w.row('wide@example.com') } );
	w.openRow('b@example.com');
	check( 'the next record that opens takes the notice away', w.notice().length === 0 );
	w.close( w.record() );
	fire( w.row('wide@example.com'), 'click', { target : w.row('wide@example.com') } );
	w.leaveAndReturn();
	w.answer( 200, listing( [ accounts()[0], accounts()[1], BEYOND ] ) );
	check( 'a notice over the table is gone when the list is read again', w.notice().length === 0 );
}

// --- the roles the signed-in account may hand out ----------------------------------------------------------------------------------

{
	// A role the signed-in account may not hand out is no option; the role an account holds stays one
	const roles = [ { id : 'dev', label : 'Developer', canGrant : false }, { id : 'editor', label : 'Editor', canGrant : true }, { id : 'other', label : 'Other' } ];
	const optionsOf = select => findAll( select, el => el.tagName === 'OPTION' ).map( el => el.value ).join();
	const w = world();
	w.open( listing( [ accounts()[0], accounts()[1], account( 'h@example.com', { role : 'dev' } ), account( 'x@example.com', { role : 'gone' } ) ], { roles : roles } ) );

	const d = w.openRow('b@example.com');
	check( 'the role select offers the roles that may be handed out and the one held', optionsOf( w.field( d, 'role' ) ) === 'editor,other' && w.field( d, 'role' ).value === 'editor' );
	w.close( d );
	const e = w.openRow('h@example.com');
	check( '...a held role that may not be handed out stays an option', optionsOf( w.field( e, 'role' ) ) === 'dev,editor,other' && w.field( e, 'role' ).value === 'dev' );
	w.close( e );
	const f = w.openRow('x@example.com');
	check( '...an account that holds a role there is none of, or none, has the choice of no role, and it is what it holds', optionsOf( w.field( f, 'role' ) ) === ',editor,other' && w.field( f, 'role' ).value === '' && w.field( f, 'role' ).dataset.saved === ''
		&& findAll( w.field( f, 'role' ), el => el.tagName === 'OPTION' )[0].textContent === 'No role' );
	w.save( f );
	check( '...and saving it as it is sends nothing', w.requests.length === 1 );
	w.close( f );

	fire( w.newButton(), 'click', { currentTarget : w.newButton() } );
	check( 'a new account starts with the first role that may be handed out', optionsOf( w.field( w.record(), 'role' ) ) === 'editor,other' && w.field( w.record(), 'role' ).value === 'editor' );
	w.close( w.record() );

	// Without a role to hand out the new account is offered no role, and says it
	const v = world();
	v.open( listing( accounts(), { roles : [ { id : 'dev', label : 'Developer', canGrant : false } ] } ) );
	fire( v.newButton(), 'click', { currentTarget : v.newButton() } );
	check( 'where no role may be handed out the form of a new account offers no role, and it is the one chosen', optionsOf( v.field( v.record(), 'role' ) ) === '' && v.field( v.record(), 'role' ).value === '' );
}

// --- the record: by the pencil, by the row, by the address, by Back ---------------------------------------------------------

{
	const w = world();
	w.open();
	const position = w.h.index();

	const d = w.openRow('b@example.com');
	check( 'the pencil opens the record of the account, in the large dialog, with the address in its head and the name under it', d !== null && has( d, 'nino-admin-record' ) && w.titleOf( d ) === 'b@example.com' && w.subtitleOf( d ) === 'Bo' );
	check( '...at an address of its own that is a step of the history, written through the router: #users/users/<mail>', w.h.hash() === '#users/users/b%40example.com' && w.h.index() === position + 1 && w.h.router.marked() === 'users' );
	check( '...and says the place among the rows of the table: 2 of 3', byClass( d, 'nino-admin-record-count' )[0].textContent === '2 of 3' );
	check( '...the focus is in the first field: the address', w.focused() === w.field( d, 'mail' ) );
	check( '...the step buttons are named in the words of the panel', tag( d, 'button' ).some( b => b.attributes['aria-label'] === 'Previous user' ) && tag( d, 'button' ).some( b => b.attributes['aria-label'] === 'Next user' ) );
	check( 'the record is a form, novalidate, whose fields stand directly in its fields and have the label at their left, and nothing explains itself in the page but behind a (?)', w.form( d ).noValidate === true && tag( d, 'fieldset' ).length === 0
		&& byClass( d, 'nino-admin-hint' ).length === 0 && byClass( d, 'nino-admin-help-button' ).length >= 4 );

	// What the title says of the own account
	w.close( d );
	const own = w.openRow('me@example.com');
	check( 'the title of the signed-in account says so, as the row does', w.titleOf( own ) === 'me@example.com (you)' && w.subtitleOf( own ) === 'Mia' );
	w.close( own );

	// A click on the row opens it too
	fire( w.row('a@example.com'), 'click', { target : w.row('a@example.com') } );
	check( 'a click on the row opens the record as the pencil does', w.record() !== null && w.titleOf( w.record() ) === 'a@example.com' );
	// The step
	fire( tag( w.record(), 'button' ).find( b => b.attributes['aria-label'] === 'Previous user' ), 'click' );
	check( 'the step shows the account before in the rows of the table, and writes its address over the one of the record - no step of the history', w.titleOf( w.record() ) === 'b@example.com' && w.h.hash() === '#users/users/b%40example.com' );
	w.close( w.record() );
	check( 'the cross closes the record, takes the one entry it made off the history, and puts the focus on the row', w.record() === null && w.h.went.at(-1) === -1 && w.focused() === w.row('b@example.com') );
}

{
	// The record of an account that exists: the state to read, the fields of the form, in their order, with the labels, the helps and the hints for a password manager
	const w = world();
	w.open();
	const d = w.openRow('a@example.com');

	check( 'the state of the account is a line to read, as words: deactivated, locked until, never logged in - no input, no stop of the Tab key', w.readValue( d, text('/_admin/users/label/state') ) === 'Deactivated · locked until 2026-10-05 15:00 · Never logged in' );
	const order = findAll( d, el => el.dataset.field !== undefined ).map( el => el.dataset.field );
	check( 'the fields are the address, the name, the role, the new password - in this order - and no current password: it is not the own account', order.join() === 'mail,name,role,pw' );
	check( '...the address is required and an email, the name optional and of at most sixty characters, the new password optional of at least eight, for a password manager a new one', w.field( d, 'mail' ).required === true && w.field( d, 'mail' ).type === 'email' && w.field( d, 'mail' ).autocomplete === 'off'
		&& w.field( d, 'name' ).required !== true && w.field( d, 'name' ).maxLength === 60 && w.field( d, 'pw' ).required !== true && w.field( d, 'pw' ).minLength === 8 && w.field( d, 'pw' ).type === 'password' && w.field( d, 'pw' ).autocomplete === 'new-password' );
	check( '...the role is a select that holds the role the account has', w.field( d, 'role' ).tagName === 'SELECT' && w.field( d, 'role' ).value === 'editor' && findAll( w.field( d, 'role' ), o => o.tagName === 'OPTION' ).map( o => o.value ).join() === 'dev,editor' );
	check( '...what each field is for stands behind its (?), which describes the field', [ 'mail', 'name', 'role', 'pw' ].every( name => {
		const tokens = String( w.field( d, name ).attributes['aria-describedby'] ?? '' ).split(/\s+/);
		return findAll( d, el => tokens.indexOf( el.id ) !== -1 && has( el, 'nino-admin-help-text' ) ).length === 1;
	} ) && byClass( d, 'nino-admin-help-text' ).map( el => el.textContent ).includes( text('/_admin/users/hint/name') ) && text('/_admin/users/hint/name') !== '' );
	check( 'the bar has Delete user at the left as the one dangerous button, Log out everywhere and Activate account - the one that fits a deactivated account - beside Save, which is the one main button - and no Cancel and no Save and continue', w.kinds( d ) === 'danger,secondary,secondary,primary'
		&& w.bartext( d ).join() === [ DELETE, LOGOUT, ACTIVATE, SAVE ].map( text ).join() && w.button( d, CANCEL ) === undefined && w.button( d, '/_admin/common/label/save-next' ) === undefined );
	check( '...of an active account it is Deactivate account', ( () => { w.close( d ); const e = w.openRow('b@example.com'); return w.bartext( e ).join() === [ DELETE, LOGOUT, DEACTIVATE, SAVE ].map( text ).join() && w.shown( e, ACTIVATE ) === false } )() );
	check( '...the state of the bar says nothing of an input nobody made', w.stateOf( w.record() ).dataset.state !== 'warn' && w.h.dirty.isDirty( [ 'users' ] ) === false );
}

{
	// The own account: no role select, no delete, no deactivate, the current password is asked for
	const w = world();
	w.open();
	const d = w.openRow('me@example.com');

	check( 'on the own account the role is a line to read - no select - with the sentence that another manager changes it behind its (?)', w.field( d, 'role' ) === undefined && w.readValue( d, text('/_admin/users/label/role') ) === 'Developer'
		&& byClass( w.readLine( d, text('/_admin/users/label/role') ), 'nino-admin-help-text' )[0].textContent === text('/_admin/users/label/role-self') );
	check( '...the fields are the address, the name, the new password and the current password, which is required and tells a password manager that it is the current one', findAll( d, el => el.dataset.field !== undefined ).map( el => el.dataset.field ).join() === 'mail,name,pw,currentPassword'
		&& w.field( d, 'currentPassword' ).required === true && w.field( d, 'currentPassword' ).autocomplete === 'current-password' && w.field( d, 'currentPassword' ).type === 'password' );
	check( '...the bar has what only the own account may do - log out everywhere - and Save: no delete, no deactivate, no activate', w.kinds( d ) === 'secondary,primary' && w.bartext( d ).join() === [ LOGOUT, SAVE ].map( text ).join()
		&& w.shown( d, DELETE ) === false && w.shown( d, DEACTIVATE ) === false && w.shown( d, ACTIVATE ) === false );
}

{
	// An account that may manage users but not deactivate... no: one that may not manage - its own record only, no select, no delete
	const w = world();
	w.open( listing( [ accounts()[0] ], { canManage : false } ) );
	const d = w.openRow('me@example.com');
	check( 'an account that may not manage users has its own record with no role select and no button but log out everywhere and Save', w.field( d, 'role' ) === undefined && w.readLine( d, text('/_admin/users/label/role') ) !== undefined && w.bartext( d ).join() === [ LOGOUT, SAVE ].map( text ).join()
		&& byClass( w.readLine( d, text('/_admin/users/label/role') ), 'nino-admin-help-text' ).length === 0 );
}

{
	// Reload at the address of an account: the directory and the record over it, no step of the history of its own
	const w = world( '#users/users/b%40example.com' );
	w.panel.showCurrent();
	w.answer( 200, listing() );
	check( 'a fresh load on the address of an account opens the directory with its record over it', w.record() !== null && w.titleOf( w.record() ) === 'b@example.com' && w.ids().length === 3 );
	check( '...it is no step of the history: the address stays where it was, and closing writes the address of the directory over it', w.h.hash() === '#users/users/b%40example.com' && ( fire( w.cross( w.record() ), 'click' ), w.record() === null && w.h.hash() === '#users' ) );
}

{
	// Back closes a record that a person's own move opened; Forward opens it again
	const w = world();
	w.open();
	w.openRow('b@example.com');
	w.back();
	check( 'Back closes the record: the address is the directory\'s and the directory is as it was', w.record() === null && w.h.hash() === '#users' && w.ids().join() === 'me@example.com,b@example.com,a@example.com' );
	w.forward();
	check( 'Forward opens it again', w.record() !== null && w.titleOf( w.record() ) === 'b@example.com' );
}

{
	// An address of an account that is not there is said over the directory
	const w = world( '#users/users/nobody%40example.com' );
	w.panel.showCurrent();
	w.answer( 200, listing() );
	check( 'an address that names no account says so over the directory, in the words of the panel, and the address is the directory\'s', w.record() === null && w.notice().join() === 'This user does not exist (any more).' && w.h.hash() === '#users' );
}

// --- the address of a new account ----------------------------------------------------------------------------------------------

{
	const w = world( codec.href( 'users', [ codec.word('new') ] ) );
	w.panel.showCurrent();
	w.answer( 200, listing() );
	check( 'a fresh load on #users/users/@new opens the form of a new account over the directory, for an account that may manage users', w.record() !== null && w.titleOf( w.record() ) === text('/_admin/users/label/new') && w.field( w.record(), 'mail' ).value === '' && w.ids().length === 3 );
	check( '...it is no step of the history of its own: the address stays', w.h.hash() === '#users/users/@new' );

	// The key @new is no form that makes an account
	const v = world( codec.href( 'users', [ '@new' ] ) );
	v.panel.showCurrent();
	v.answer( 200, listing() );
	check( 'the key @new, with no account of that name, is no form that makes one: an account that is not there', v.record() === null && v.notice().length === 1 && v.h.hash() === '#users' );

	// An account called @new is an entry
	const u = world( codec.href( 'users', [ '@new' ] ) );
	u.panel.showCurrent();
	u.answer( 200, listing( accounts().concat( [ account( '@new' ) ] ) ) );
	check( 'an account whose address is @new has the part %40new and opens as that account, not as the form', u.h.hash() === '#users/users/%40new' && u.record() !== null && u.field( u.record(), 'mail' ).value === '@new' );

	// A word that is not the form for one that may not make an account
	const x = world( codec.href( 'users', [ codec.word('new') ] ) );
	x.panel.showCurrent();
	x.answer( 200, listing( [ accounts()[0] ], { canManage : false } ) );
	check( 'an account that may not manage users gets the directory at #users/users/@new: no form opens, nothing is sent, the address is the directory\'s', x.record() === null && x.tree() === 'users/list' && x.h.hash() === '#users' && x.ids().join() === 'me@example.com' );
	x.typed( codec.href( 'users', [ codec.word('new') ] ) );
	check( '...also where the address is typed while the panel is shown', x.record() === null && x.tree() === 'users/list' && x.h.hash() === '#users' && x.notice().length === 0 );
	// ... and while the record of the own account is open
	x.openRow('me@example.com');
	x.typed( codec.href( 'users', [ codec.word('new') ] ) );
	check( '...and with a record open: it is closed, no form that makes an account takes its place, nothing is sent', x.record() === null && x.tree() === 'users/list' );
}

// --- Save: what was changed is sent, what was not is not ----------------------------------------------------------------------

{
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	const sent = w.requests.length;

	w.save( d );
	check( 'a Save of an account that nothing was changed in sends nothing - it is saved as it is', w.requests.length === sent && w.toasts.at(-1) === text('/_admin/common/msg/saved') && w.record() === d );
	w.type( w.field( d, 'mail' ), '  b@example.com  ' );
	w.save( d );
	check( '...a blank around the address is no change', w.requests.length === sent );
	w.type( w.field( d, 'name' ), '  Bo  ' );
	w.save( d );
	check( '...nor is a blank around the name', w.requests.length === sent );

	// The name only: no role in the payload
	w.type( w.field( d, 'name' ), '  Bea  ' );
	w.save( d );
	check( 'one save sends the account to write by its stored address, the address and the name - trimmed - and, for a name only, no role: the checks of a role change are not asked for', w.requests.length === sent + 1 && w.last().action === 'users/save'
		&& JSON.stringify( w.last().payload ) === '{"username":"b@example.com","mail":"b@example.com","pw":"","name":"Bea"}' );
	w.answer( 200, { mail : 'b@example.com', role : 'editor', name : 'Bea' } );
	check( '...and the answer is what the account is: the title is the same, the name under it is the new one, the field holds it, the table row too, and the record is saved', w.titleOf( d ) === 'b@example.com' && w.subtitleOf( d ) === 'Bea' && w.field( d, 'name' ).value === 'Bea'
		&& flat( w.cells('b@example.com')[1] ) === 'Bea' && w.toasts.at(-1) === text('/_admin/common/msg/saved') && w.h.dirty.isDirty( [ 'users' ] ) === false && w.lineOf( d ).textContent === '' );
	check( '...and only the one request went: the list is not read again', w.tree() === 'users/list,users/save' );

	// An emptied name is sent as an empty text: that takes it away
	w.type( w.field( d, 'name' ), '' );
	w.save( d );
	check( 'an emptied name is sent as an empty text, which takes it away', w.last().payload.name === '' && 'role' in w.last().payload === false );
	w.answer( 200, { mail : 'b@example.com', role : 'editor', name : '' } );
	check( '...and the account has none then', w.subtitleOf( d ) === '' && flat( w.cells('b@example.com')[1] ) === '' );

	// The role: sent only where it changed
	w.type( w.field( d, 'role' ), 'dev' );
	w.save( d );
	check( 'a role that was changed is sent, with the account to write', w.last().payload.role === 'dev' && w.last().payload.username === 'b@example.com' && 'name' in w.last().payload === false );
	w.answer( 200, { mail : 'b@example.com', role : 'dev' } );
	check( '...the select holds the role as saved: the next save, unchanged, sends nothing - and the table says the role by its name', w.field( d, 'role' ).dataset.saved === 'dev' && flat( w.cells('b@example.com')[2] ) === 'Developer' && ( () => { const n = w.requests.length; w.save( d ); return w.requests.length === n } )() );

	// No request carries the role by another action
	check( 'no request goes to users/role: the role is a field of the one save', w.requests.filter( r => r.action === 'users/role' ).length === 0 );
}

{
	// A role id the config does not have any more is not an option of the select, which then shows none. What the server answers with must not make the next save look like a role change: it would post '' and strip the stored id
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	const select = w.field( d, 'role' );
	let shown = select.value;

	Object.defineProperty( select, 'value', {
		get : function() { return shown },
		set : function( value ) { shown = findAll( select, el => el.tagName === 'OPTION' && el.value === value ).length === 1 ? value : '' },
	} );
	w.type( w.field( d, 'pw' ), PASSWORD );
	w.save( d );
	w.answer( 200, { mail : 'b@example.com', role : 'ghost' } );
	check( 'a stored role the config no longer has is not a change of the role on the next save', select.dataset.saved === select.value );
	w.type( w.field( d, 'name' ), 'Next' );
	w.save( d );
	check( '...the next save does not send the role', 'role' in w.last().payload === false && w.last().payload.name === 'Next' );
}

{
	// A new password: sent once, and nowhere kept
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');

	w.type( w.field( d, 'pw' ), 'short' );
	const sent = w.requests.length;
	w.save( d );
	check( 'a new password under the minimum is said under its field before anything is sent, with the focus there, and in the line of the bar', w.requests.length === sent && errorsOf( d ).length === 1 && w.field( d, 'pw' ).attributes['aria-invalid'] === 'true' && w.focused() === w.field( d, 'pw' )
		&& w.lineOf( d ).dataset.state === 'error' && w.lineOf( d ).textContent === text('/_admin/users/error/fields') );
	w.type( w.field( d, 'pw' ), PASSWORD );
	w.save( d );
	check( 'a good one is sent with the account to write, and the sentences are gone', w.requests.length === sent + 1 && w.last().payload.pw === PASSWORD && w.last().payload.username === 'b@example.com' && errorsOf( d ).length === 0 );
	check( '...the fields cannot be taken for a second Save while it runs: the line says that it is saving', w.lineOf( d ).dataset.state === 'saving' );
	w.answer( 200, { mail : 'b@example.com', role : 'editor' } );
	check( '...and once it was sent the password is in no field - the record, the row and the head do not hold it', w.field( d, 'pw' ).value === '' && JSON.stringify( w.panel._rows.map( row => [ row.key, row.mail, row.name, row.role, row.stateText, row.entry ] ) ).includes( PASSWORD ) === false
		&& w.titleOf( d ).includes( PASSWORD ) === false && w.toasts.join().includes( PASSWORD ) === false && w.h.hash().includes( PASSWORD ) === false && w.lineOf( d ).textContent.includes( PASSWORD ) === false );
	check( '...and nothing the panel keeps holds it: a walk over what it keeps finds no password', ( () => {
		const seen = new Set();
		const walk = value => {
			if( value === null || typeof value !== 'object' || seen.has( value ) || value.nodeType !== undefined || value.tagName !== undefined )
				return false;
			seen.add( value );
			return Object.keys( value ).some( key => ( typeof value[key] === 'string' && value[key].includes( PASSWORD ) ) || walk( value[key] ) );
		};
		return walk( w.panel._users ) === false && walk( w.panel._rows ) === false && walk( w.panel._state ) === false && walk( w.panel._roles ) === false;
	} )() );
	// ... and it is not kept in a field after a refusal that left the fields as they are only for the person to correct them
}

{
	// A password that was typed and refused stays in its field for the person to correct, as every other input
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	w.type( w.field( d, 'pw' ), PASSWORD );
	w.save( d );
	w.answer( 403, { error : 'too wide', code : 'users_account_beyond', params : [ '/*' ], field : 'pw' } );
	check( 'a password the server refused stays in its field and is marked: it is what the person corrects', w.field( d, 'pw' ).value === PASSWORD && w.field( d, 'pw' ).attributes['aria-invalid'] === 'true' );
	w.close( d );
	w.answerAsk( '/_admin/common/label/discard' );
	check( '...a Discard of it takes the record away, and the shell holds nothing unsaved', w.record() === null && w.h.dirty.isDirty( [ 'users' ] ) === false );
}

// --- the own account: the current password -----------------------------------------------------------------------------------------

{
	const w = world();
	w.open();
	const d = w.openRow('me@example.com');
	const sent = w.requests.length;

	w.save( d );
	check( 'a Save of the own account that nothing was changed in sends nothing, and does not ask for the current password', w.requests.length === sent && errorsOf( d ).length === 0 && w.field( d, 'currentPassword' ).attributes['aria-invalid'] === undefined );

	w.type( w.field( d, 'name' ), 'Mia M.' );
	w.save( d );
	check( 'a change of the own account asks for the current password: without it nothing is sent, it is said under the field, and the focus goes there', w.requests.length === sent && errorsOf( d ).length === 1 && w.field( d, 'currentPassword' ).attributes['aria-invalid'] === 'true' && w.focused() === w.field( d, 'currentPassword' ) );
	w.type( w.field( d, 'currentPassword' ), 'the current one' );
	w.type( w.field( d, 'pw' ), 'short' );
	w.save( d );
	check( '...a new password under the minimum is said under its field as well', w.requests.length === sent && errorsOf( d ).length === 1 && w.field( d, 'pw' ).attributes['aria-invalid'] === 'true' );
	w.type( w.field( d, 'pw' ), '' );
	w.save( d );
	check( '...an empty new password is none: the rest is sent, with the current password', w.requests.length === sent + 1 && w.last().payload.currentPassword === 'the current one' && w.last().payload.username === 'me@example.com' && w.last().payload.name === 'Mia M.' && 'role' in w.last().payload === false );
	w.answer( 401, { error : 'wrong current password', code : 'wrong_password', field : 'currentPassword' } );
	check( 'a refusal that names the current password stands at that field and in the line, and is no loss of the session: the record stays', w.field( d, 'currentPassword' ).attributes['aria-invalid'] === 'true' && errorsOf( d ).length === 1 && w.lineOf( d ).dataset.state === 'error' && w.record() === d && w.focused() === w.field( d, 'currentPassword' ) );
	w.type( w.field( d, 'currentPassword' ), 'the right one' );
	w.save( d );
	w.answer( 200, { mail : 'me@example.com', role : 'dev', name : 'Mia M.' } );
	check( 'a save that went through empties the current password too, and the rail still names the account', w.field( d, 'currentPassword' ).value === '' && w.field( d, 'pw' ).value === '' && w.rail.textContent === 'me@example.com' && w.subtitleOf( d ) === 'Mia M.' );
}

// --- the refusals of the server stand at their field ----------------------------------------------------------------------------

{
	const REFUSALS = [
		[ 'users_unknown_role', 400, 'role', [] ], [ 'users_invalid_mail', 400, 'mail', [] ], [ 'users_password_short', 400, 'pw', [ 8 ] ], [ 'users_name_long', 400, 'name', [ 60 ] ], [ 'users_name_invalid', 400, 'name', [] ],
		[ 'wrong_password', 401, 'currentPassword', [] ], [ 'users_account_beyond', 403, 'pw', [ '/*' ] ], [ 'users_account_beyond_scoped', 403, 'pw', [ '/x/' ] ], [ 'users_role_too_wide', 403, 'role', [ '/*' ] ], [ 'users_role_too_wide_scoped', 403, 'role', [ '/x/' ] ],
		[ 'users_own_role', 400, 'role', [] ], [ 'users_mail_in_use', 400, 'mail', [] ],
	];

	for( const [ code, status, field, params ] of REFUSALS ) {
		const own = field === 'currentPassword';
		const w = world();
		w.open();
		const d = w.openRow( own ? 'me@example.com' : 'b@example.com' );
		const control = w.field( d, field );
		w.type( w.field( d, 'name' ), 'Changed' );
		if( own )
			w.type( w.field( d, 'currentPassword' ), 'x' );
		w.save( d );
		const requested = w.requests.length;
		w.answer( status, { error : 'refused', code : code, params : params, field : field } );
		const sentence = errorsOf( d )[0];
		check( code+ ': the refusal stands at the field it names - marked, with the focus and its own sentence - and in the line, and the record stays as it is', control !== undefined && control.attributes['aria-invalid'] === 'true' && w.focused() === control && errorsOf( d ).length === 1
			&& sentence !== undefined && sentence.textContent === w.lineOf( d ).textContent && sentence.textContent !== '' && w.lineOf( d ).dataset.state === 'error' && w.record() === d && w.field( d, 'name' ).value === 'Changed' && w.toasts.length === 0 && requested === w.requests.length );
		check( code+ ': it has its words in both languages', ( moduleEn['/_admin/error/'+ code] ?? workbench['/_admin/error/'+ code] ) !== undefined && ( moduleDe['/_admin/error/'+ code] ?? workbenchDe['/_admin/error/'+ code] ) !== undefined || code === 'wrong_password' );
	}
}

{
	// A refusal without a field stands in the line alone; one that names no field of this record marks none
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	w.type( w.field( d, 'name' ), 'X' );
	w.save( d );
	w.answer( 409, { error : 'the last account with full access cannot lose it', code : 'users_last_admin_role' } );
	check( 'a refusal that names no field stands in the line of the bar alone', errorsOf( d ).length === 0 && w.lineOf( d ).dataset.state === 'error' && w.lineOf( d ).textContent === text('/_admin/error/users_last_admin_role') && findAll( d, el => el.attributes['aria-invalid'] === 'true' ).length === 0 );
	w.save( d );
	w.answer( 403, { error : 'beyond', code : 'users_account_beyond', params : [ '/*' ] } );
	check( '...so does the refusal of an account that is beyond the signed-in one', errorsOf( d ).length === 0 && w.lineOf( d ).textContent === text('/_admin/error/users_account_beyond').replace( '%s', '/*' ) );
	w.save( d );
	w.answer( 400, { error : 'x', code : 'users_invalid_mail', field : 'mail"], [data-field="name' } );
	check( '...and one that names a field which is no name marks no field', errorsOf( d ).length === 0 && w.field( d, 'name' ).attributes['aria-invalid'] === undefined );
	w.save( d );
	w.answer( 500, null );
	check( '...an answer with no reason falls back to the shared sentence of the panel', w.lineOf( d ).textContent === '(500) '+ text('/_admin/users/error/save') );
	check( '...and none of the refusals changed the table: the account is what it was', flat( w.cells('b@example.com')[1] ) === 'Bo' && w.toasts.length === 0 );
}

// --- the address of an account is changed ------------------------------------------------------------------------------------------

{
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	const position = w.h.index();

	w.type( w.field( d, 'mail' ), '  c@example.com ' );
	w.save( d );
	check( 'a changed address is sent as the same account, trimmed: the one it had is the username', w.last().payload.username === 'b@example.com' && w.last().payload.mail === 'c@example.com' && 'role' in w.last().payload === false );
	w.answer( 200, { mail : 'c@example.com', role : 'editor' } );
	check( 'the record has the new address as its key: the head says it, the address is written over the old one, with no new step of the history', w.titleOf( d ) === 'c@example.com' && w.h.hash() === '#users/users/c%40example.com' && w.h.index() === position && w.field( d, 'mail' ).value === 'c@example.com' );
	check( '...the row of the new key is in the table, the one of the old key is not, and the one that was written is the one drawn anew', w.ids().join() === 'me@example.com,c@example.com,a@example.com' && has( w.row('c@example.com'), 'is-fresh' ) );
	check( '...a rename of another account leaves the rail as it is', w.rail.textContent === 'me@example.com' );

	const sent = w.requests.length;
	w.type( w.field( d, 'name' ), 'Cy' );
	w.save( d );
	check( 'the next save names the account by the NEW address', w.requests.length === sent + 1 && w.last().payload.username === 'c@example.com' && w.last().payload.mail === 'c@example.com' );
	w.answer( 200, { mail : 'c@example.com', role : 'editor', name : 'Cy' } );

	fire( w.cross( d ), 'click' );
	check( 'closing the record puts the focus on the row of the new address, and takes the one entry off the history', w.record() === null && w.focused() === w.row('c@example.com') && w.h.went.at(-1) === -1 );
}

{
	// Your own address: the rail follows
	const w = world();
	w.open();
	const d = w.openRow('me@example.com');
	w.type( w.field( d, 'mail' ), 'mia@example.com' );
	w.type( w.field( d, 'currentPassword' ), 'the current one' );
	w.save( d );
	w.answer( 200, { mail : 'mia@example.com', role : 'dev' } );
	check( 'a rename of the own account says the new address in the rail, as the api compares a session it finds later against it', w.rail.textContent === 'mia@example.com' && w.titleOf( d ) === 'mia@example.com (you)' && w.h.hash() === '#users/users/mia%40example.com' );
	check( '...the row is still the signed-in account\'s, and the current password is gone from its field', flat( byClass( w.cells('mia@example.com')[0], 'users-account' )[0] ) === 'mia@example.com (you)' && w.field( d, 'currentPassword' ).value === '' );
}

// --- a new account: the same dialog at @new ------------------------------------------------------------------------------------------

{
	const w = world();
	w.open();
	const asked = w.requests.length;
	const position = w.h.index();

	fire( w.newButton(), 'click', { currentTarget : w.newButton() } );
	const d = w.record();
	check( 'New user opens the form of a new account: one entry of the history, marked as the record\'s own, at the address with the word @new', d !== null && w.h.hash() === '#users/users/@new' && w.h.index() === position + 1 && w.h.router.marked() === 'users' && codec.read( w.h.hash() ).parts.words[0] === true );
	check( '...it has the title of the form, no identifier under it and no place, and no step', w.titleOf( d ) === text('/_admin/users/label/new') && w.subtitleOf( d ) === '' && byClass( d, 'nino-admin-record-count' )[0].textContent === '' );
	check( '...the fields are the address, the name, the password - required, of at least eight - and the role', findAll( d, el => el.dataset.field !== undefined ).map( el => el.dataset.field ).join() === 'mail,name,pw,role' && w.field( d, 'mail' ).required === true && w.field( d, 'pw' ).required === true
		&& w.field( d, 'pw' ).minLength === 8 && w.field( d, 'pw' ).autocomplete === 'new-password' && w.field( d, 'name' ).maxLength === 60 );
	check( '...the role starts with the first one the signed-in account may hand out, and the focus is in the first field', w.field( d, 'role' ).value === 'dev' && w.focused() === w.field( d, 'mail' ) );
	check( '...the bar has one main button, Create user, and none of the buttons of an account that exists', w.bartext( d ).join() === text( CREATE ) && w.kinds( d ) === 'primary' );

	// Empty: nothing is sent, and it is said under the fields
	fire( w.button( d, CREATE ), 'click' );
	check( 'a form with nothing in it sends nothing and says under the address and the password that they are missing, the focus goes to the first', w.requests.length === asked && errorsOf( d ).length === 2 && w.field( d, 'mail' ).attributes['aria-invalid'] === 'true' && w.focused() === w.field( d, 'mail' ) && w.lineOf( d ).dataset.state === 'error' );
	w.type( w.field( d, 'mail' ), 'not an address' );
	w.type( w.field( d, 'pw' ), 'short' );
	fire( w.button( d, CREATE ), 'click' );
	check( '...an address that is none and a password under the minimum are wrong as well, once each', w.requests.length === asked && errorsOf( d ).length === 2 );

	w.type( w.field( d, 'mail' ), ' fresh@example.com ' );
	w.type( w.field( d, 'name' ), '  Fresh  ' );
	w.type( w.field( d, 'pw' ), PASSWORD );
	w.type( w.field( d, 'role' ), 'editor' );
	const typedPassword = w.field( d, 'pw' );
	fire( w.button( d, CREATE ), 'click' );
	check( 'a good form is sent as users/create with the address and the name trimmed, the password and the role - and the sentences are gone', w.requests.length === asked + 1 && w.last().action === 'users/create'
		&& JSON.stringify( w.last().payload ) === '{"mail":"fresh@example.com","name":"Fresh","pw":"'+ PASSWORD+ '","role":"editor"}' && errorsOf( d ).length === 0 );
	check( '...the fields take no input while it is on its way', tag( d, 'input' ).filter( el => el.type !== 'search' ).every( el => el.disabled === true ) );

	w.answer( 200, { mail : 'fresh@example.com', role : 'editor', name : 'Fresh' } );
	check( 'once it is made the list is read again, so that the flags of the new account are the server\'s', w.last().action === 'users/list' && w.requests.length === asked + 2 );
	check( '...and the field that held the password is empty the moment it was sent', typedPassword.value === '' && w.last().action === 'users/list' );
	w.answer( 200, listing( accounts().concat( [ account( 'fresh@example.com', { name : 'Fresh', lastLogin : '2026-11-24 09:00', locked : '2026-11-24 10:00' } ) ] ) ) );
	check( '...the row says what the server says of the new account, not what the browser would guess', w.cells('fresh@example.com')[4].textContent === '2026-11-24, 09:00' && byClass( w.cells('fresh@example.com')[3], 'nino-admin-badge' ).length === 1 );
	check( 'the record is the record of the account in the same dialog: its title, its name under it, its address written over the form\'s with no new step of the history', w.record() === d && w.titleOf( d ) === 'fresh@example.com' && w.subtitleOf( d ) === 'Fresh'
		&& w.h.hash() === '#users/users/fresh%40example.com' && w.h.index() === position + 1 );
	check( '...it has the buttons of an account that exists - Delete user, Log out everywhere, Deactivate account, Save - and no Create', w.bartext( d ).join() === [ DELETE, LOGOUT, DEACTIVATE, SAVE ].map( text ).join() && w.button( d, CREATE ) === undefined );
	check( '...the account is in the table, at the end, and its row is the one drawn anew; a toast says that it was created', w.ids().join() === 'me@example.com,b@example.com,a@example.com,fresh@example.com' && has( w.row('fresh@example.com'), 'is-fresh' ) && w.toasts.at(-1) === 'User created.' );
	check( '...and the password is nowhere: not in the form that was drawn anew, not in the rows', w.field( d, 'pw' ).value === '' && JSON.stringify( w.panel._rows.map( row => row.entry ) ).includes( PASSWORD ) === false && w.titleOf( d ).includes( PASSWORD ) === false && w.toasts.join().includes( PASSWORD ) === false );
	check( '...the shell would not ask: what the record holds is what is saved', w.h.dirty.isDirty( [ 'users' ] ) === false );
	fire( w.cross( d ), 'click' );
	check( '...and the cross takes the one entry off the history and puts the focus on the new row', w.record() === null && w.h.went.at(-1) === -1 && w.focused() === w.row('fresh@example.com') );
}

{
	// What the server refuses of a new account stands at the field it names, and the form stays a form that makes
	const CREATE_REFUSALS = [
		[ 'users_invalid_mail', 400, 'mail' ], [ 'users_password_short', 400, 'pw' ], [ 'users_unknown_role', 400, 'role' ], [ 'users_name_long', 400, 'name' ], [ 'users_name_invalid', 400, 'name' ],
		[ 'users_role_too_wide', 403, 'role' ], [ 'users_mail_in_use', 409, 'mail' ],
	];

	for( const [ code, status, field ] of CREATE_REFUSALS ) {
		const w = world();
		w.open();
		fire( w.newButton(), 'click' );
		const d = w.record();
		w.type( w.field( d, 'mail' ), 'x@example.com' );
		w.type( w.field( d, 'pw' ), PASSWORD );
		fire( w.button( d, CREATE ), 'click' );
		const requested = w.requests.length;
		w.answer( status, { error : 'refused', code : code, params : [ 60 ], field : field } );
		check( code+ ' of a new account stands at the '+ field+ ' field and in the line, the form stays with what was typed, nothing is added to the table', w.field( d, field ).attributes['aria-invalid'] === 'true' && errorsOf( d ).length === 1 && errorsOf( d )[0].textContent === w.lineOf( d ).textContent
			&& w.record() === d && w.titleOf( d ) === text('/_admin/users/label/new') && w.field( d, 'mail' ).value === 'x@example.com' && w.field( d, 'pw' ).value === PASSWORD && w.field( d, 'mail' ).disabled === false
			&& w.ids().length === 3 && w.h.hash() === '#users/users/@new' && w.toasts.length === 0 && requested === w.requests.length );
	}

	const w = world();
	w.open();
	fire( w.newButton(), 'click' );
	const d = w.record();
	w.type( w.field( d, 'mail' ), 'x@example.com' );
	w.type( w.field( d, 'pw' ), PASSWORD );
	fire( w.button( d, CREATE ), 'click' );
	w.answer( 500, null );
	check( 'an answer with no reason falls back to the sentence of the panel', w.lineOf( d ).textContent === '(500) '+ text('/_admin/users/error/create') );
}

{
	// The account exists and only the list could not be read again: the account is in the table as the server makes it, and the table says that it is not what the server holds
	const w = world();
	w.open();
	fire( w.newButton(), 'click' );
	const d = w.record();
	w.type( w.field( d, 'mail' ), 'x@example.com' );
	w.type( w.field( d, 'pw' ), PASSWORD );
	fire( w.button( d, CREATE ), 'click' );
	w.answer( 200, { mail : 'x@example.com', role : 'dev', name : '' } );
	w.answer( 500, null );
	check( 'a list that cannot be read again after the account was made does not make the save a failed one: the record is the account\'s, the row is in the table, and the table says why', w.record() === d && w.titleOf( d ) === 'x@example.com' && w.ids().includes('x@example.com') && w.notice().join() === '(500) '+ text('/_admin/users/error/load') && w.lineOf( d ).dataset.state !== 'error' );
}

{
	// A form of a new account over a record, and the other way round: what is typed is asked about, and the history keeps one entry
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	w.type( w.field( d, 'name' ), 'Typed' );
	check( 'a record that holds input is unsaved input of the panel, under the name of the screen', w.h.dirty.isDirty( [ 'users' ] ) === true && w.h.dirty.entry('users') !== null );
	fire( w.cross( d ), 'click' );
	check( 'closing it asks Save, Discard or Cancel - and Cancel keeps it, with what was typed', w.asking() !== null && ( w.answerAsk( CANCEL ), w.record() === d && w.field( d, 'name' ).value === 'Typed' ) );
	fire( w.cross( d ), 'click' );
	w.answerAsk( '/_admin/common/label/discard' );
	check( 'Discard closes it and the shell holds nothing unsaved', w.record() === null && w.h.dirty.isDirty( [ 'users' ] ) === false );

	// The Save the shell runs
	const v = world();
	v.open();
	const e = v.openRow('b@example.com');
	const outcomes = [];
	v.h.dirty.entry('users').save( ok => outcomes.push( ok ) );
	check( 'the Save the shell runs of a record that nothing was changed in sends nothing and says that it is saved', outcomes.join() === 'true' && v.requests.length === 1 );
	v.type( v.field( e, 'mail' ), '' );
	v.h.dirty.entry('users').save( ok => outcomes.push( ok ) );
	check( '...one that is not complete reports false and sends nothing', outcomes.join() === 'true,false' && v.requests.length === 1 );
	v.type( v.field( e, 'mail' ), 'b@example.com' );
	v.type( v.field( e, 'name' ), 'S' );
	v.h.dirty.entry('users').save( ok => outcomes.push( ok ) );
	v.answer( 500, null );
	check( '...one the server refuses reports false', outcomes.join() === 'true,false,false' && v.lineOf( e ).dataset.state === 'error' );
	v.h.dirty.entry('users').save( ok => outcomes.push( ok ) );
	v.answer( 200, { mail : 'b@example.com', role : 'editor', name : 'S' } );
	check( '...and one that is written reports true, and nothing is unsaved then', outcomes.join() === 'true,false,false,true' && v.h.dirty.isDirty( [ 'users' ] ) === false );
}

// --- Log out everywhere ---------------------------------------------------------------------------------------------------------------

{
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	const sent = w.requests.length;

	fire( w.button( d, LOGOUT ), 'click' );
	const asked = w.questions.at(-1);
	check( 'logging an account out everywhere is a question of the big level, standing on the record, worded like the act, that does not speak of visitors', asked.level === 'big' && asked.nested === true && asked.visitors === false && asked.title === asked.yes && asked.title === text('/_admin/users/confirm/logoutall-title') && asked.message === text('/_admin/users/confirm/logoutall')
		&& asked.opener === w.button( d, LOGOUT ) );
	w.answerAsk( CANCEL );
	check( '...and sends nothing when the answer is no', w.requests.length === sent && w.record() === d );

	fire( w.button( d, LOGOUT ), 'click' );
	w.answerAsk( '/_admin/users/confirm/logoutall-title' );
	check( '...a Yes sends users/logoutall for that account, and nothing else', w.requests.length === sent + 1 && w.last().action === 'users/logoutall' && JSON.stringify( w.last().payload ) === '{"username":"b@example.com"}' );
	w.answer( 200, { ok : true, loggedOutSelf : false } );
	check( '...says it as a toast, the line stays as it was, nothing is drawn again and nothing leaves the page', w.toasts.at(-1) === text('/_admin/users/msg/loggedout') && w.lineOf( d ).dataset.state !== 'error' && w.replaced.length === 0 && w.record() === d );

	// Typed input is kept
	w.type( w.field( d, 'name' ), 'Kept' );
	fire( w.button( d, LOGOUT ), 'click' );
	w.answerAsk( '/_admin/users/confirm/logoutall-title' );
	w.answer( 200, { ok : true, loggedOutSelf : false } );
	check( 'what was typed in the record is kept: the action does not draw the fields again', w.field( d, 'name' ).value === 'Kept' && w.h.dirty.isDirty( [ 'users' ] ) === true );
}

{
	// A refusal is said in the line and changes nothing on screen
	for( const [ status, code, label ] of [ [ 403, 'users_account_beyond', 'beyond' ], [ 400, 'users_invalid_name', 'no name' ] ] ) {
		const w = world();
		w.open();
		const d = w.openRow('b@example.com');
		fire( w.button( d, LOGOUT ), 'click' );
		w.answerAsk( '/_admin/users/confirm/logoutall-title' );
		w.answer( status, { error : 'refused', code : code, params : [ '/*' ], field : 'username' } );
		check( 'a logout that is refused ('+ label+ ') is said in the line of the bar, and nothing on screen changes', w.lineOf( d ).dataset.state === 'error' && w.lineOf( d ).textContent !== '' && w.toasts.length === 0 && w.record() === d && w.replaced.length === 0 && errorsOf( d ).length === 0 );
	}
}

{
	// The own account: the unsaved input of the page is asked about first, and the page goes to the login form once it is done
	const w = world();
	w.open();
	const d = w.openRow('me@example.com');
	w.type( w.field( d, 'name' ), 'Unsaved' );
	const sent = w.requests.length;

	fire( w.button( d, LOGOUT ), 'click' );
	w.answerAsk( '/_admin/users/confirm/logoutall-title' );
	check( 'the own account is asked about unsaved input before the request: nothing is sent while that question stands', w.requests.length === sent && w.asking() !== null && w.questions.at(-1).level === 'big' || w.asking() !== null );
	w.answerAsk( '/_admin/common/label/discard' );
	check( '...and a Discard goes on: users/logoutall for the own account', w.last().action === 'users/logoutall' && w.last().payload.username === 'me@example.com' );
	w.answer( 200, { ok : true, loggedOutSelf : true } );
	check( '...the answer that says the own session ended sends the page to the login form, at once, and says no toast', w.replaced.length === 1 && w.replaced[0].endsWith('/_admin') && w.toasts.length === 0 );
}

{
	// The own account with nothing unsaved: no question about input, the page goes
	const w = world();
	w.open();
	const d = w.openRow('me@example.com');
	fire( w.button( d, LOGOUT ), 'click' );
	w.answerAsk( '/_admin/users/confirm/logoutall-title' );
	check( 'with nothing unsaved the request goes out at once', w.last().action === 'users/logoutall' && w.asking() === null );
	w.answer( 200, { ok : true, loggedOutSelf : true } );
	check( '...and the page is sent to the login form', w.replaced.length === 1 );
	const v = world();
	v.open();
	const e = v.openRow('me@example.com');
	v.type( v.field( e, 'name' ), 'Unsaved' );
	fire( v.button( e, LOGOUT ), 'click' );
	v.answerAsk( '/_admin/users/confirm/logoutall-title' );
	v.answerAsk( CANCEL );
	check( 'Cancel to the question about unsaved input sends nothing and keeps the record with what was typed', v.requests.length === 1 && v.record() === e && v.field( e, 'name' ).value === 'Unsaved' && v.replaced.length === 0 );
}

// --- Deactivate and Activate -----------------------------------------------------------------------------------------------------------

{
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	const sent = w.requests.length;
	const toggle = w.button( d, DEACTIVATE );

	check( 'a manager gets a Deactivate button on somebody else\'s account, which is no dangerous one - and no Activate beside it', toggle !== undefined && w.shown( d, DEACTIVATE ) === true && w.shown( d, ACTIVATE ) === false && has( toggle, 'nino-admin-btn-danger' ) === false );

	// Typed input is not lost
	w.type( w.field( d, 'name' ), 'Typed' );
	fire( toggle, 'click' );
	const asked = w.questions.at(-1);
	check( 'a deactivation is asked about first: a question of the big level on the record, which does not speak of visitors', asked.level === 'big' && asked.nested === true && asked.visitors === false && asked.title === text('/_admin/users/confirm/deactivate-title') && asked.message === text('/_admin/users/confirm/deactivate') && asked.opener === toggle );
	w.answerAsk( CANCEL );
	check( '...and nothing is sent when the answer is no', w.requests.length === sent && w.shown( d, DEACTIVATE ) === true );

	fire( toggle, 'click' );
	w.answerAsk( '/_admin/users/confirm/deactivate-title' );
	check( 'a confirmed deactivation sends users/status for that account, and nothing else', w.requests.length === sent + 1 && w.last().action === 'users/status' && JSON.stringify( w.last().payload ) === '{"username":"b@example.com","active":false}' );
	check( '...the button is off while the request is on its way, so a second click cannot send it twice', toggle.disabled === true );
	w.answer( 200, { status : 'disabled' } );
	check( '...and on again when it is answered', toggle.disabled === false );
	check( '...the button says Activate account now - the other one is shown, this one is not - the state line says deactivated, the row has its badge, and a toast says it', w.shown( d, ACTIVATE ) === true && w.shown( d, DEACTIVATE ) === false
		&& w.readValue( d, text('/_admin/users/label/state') ) === 'Deactivated · Last login: 2026-10-04 08:00' && byClass( w.cells('b@example.com')[3], 'nino-admin-badge' ).map( b => b.textContent ).join() === 'Deactivated' && w.toasts.at(-1) === text('/_admin/users/msg/deactivated') );
	check( '...what was typed is kept, and is still unsaved', w.field( d, 'name' ).value === 'Typed' && w.h.dirty.isDirty( [ 'users' ] ) === true && w.record() === d );
	check( '...the focus stays in the pair of buttons: on the one that is shown now', w.focused() === w.button( d, ACTIVATE ) );

	// Activate: no question
	const questions = w.questions.length;
	fire( w.button( d, ACTIVATE ), 'click' );
	check( 'activating needs no question', w.questions.length === questions && w.last().action === 'users/status' && JSON.stringify( w.last().payload ) === '{"username":"b@example.com","active":true}' );
	w.answer( 200, { status : 'active' } );
	check( '...and takes the marker away again: the buttons, the line and the badge are the active account\'s', w.shown( d, DEACTIVATE ) === true && w.shown( d, ACTIVATE ) === false && w.readValue( d, text('/_admin/users/label/state') ) === 'Last login: 2026-10-04 08:00'
		&& byClass( w.cells('b@example.com')[3], 'nino-admin-badge' ).length === 0 && w.toasts.at(-1) === text('/_admin/users/msg/activated') && w.field( d, 'name' ).value === 'Typed' );

	// A save after it works on the account as it is now
	w.save( d );
	check( 'a Save after it writes the typed input, for the same account', w.last().action === 'users/save' && w.last().payload.name === 'Typed' && w.last().payload.username === 'b@example.com' );
}

{
	// Refusals change nothing on screen
	for( const [ status, code, label ] of [ [ 409, 'users_last_admin_status', 'the last account with full access' ], [ 400, 'users_status_self', 'the own account' ], [ 403, 'users_account_beyond', 'beyond' ], [ 404, 'users_unknown', 'unknown' ] ] ) {
		const w = world();
		w.open();
		const d = w.openRow('b@example.com');
		fire( w.button( d, DEACTIVATE ), 'click' );
		w.answerAsk( '/_admin/users/confirm/deactivate-title' );
		w.answer( status, { error : 'refused', code : code, params : [ '/*' ] } );
		check( 'a deactivation that is refused ('+ label+ ') is said in the line of the bar, and nothing on screen changes: the button, the line, the row', w.lineOf( d ).dataset.state === 'error' && w.lineOf( d ).textContent !== '' && w.shown( d, DEACTIVATE ) === true && w.shown( d, ACTIVATE ) === false
			&& w.button( d, DEACTIVATE ).disabled === false && w.readValue( d, text('/_admin/users/label/state') ) === 'Last login: 2026-10-04 08:00' && byClass( w.cells('b@example.com')[3], 'nino-admin-badge' ).length === 0 && w.toasts.length === 0 );
	}
	const w = world();
	w.open();
	const d = w.openRow('a@example.com');
	fire( w.button( d, ACTIVATE ), 'click' );
	w.answer( 403, { error : 'beyond', code : 'users_account_beyond', params : [ '/*' ] } );
	check( 'an activation that is refused says so in the line and leaves the account deactivated', w.lineOf( d ).dataset.state === 'error' && w.shown( d, ACTIVATE ) === true && w.shown( d, DEACTIVATE ) === false && byClass( w.cells('a@example.com')[3], 'nino-admin-badge' ).length === 2 );
}

{
	// The record that steps on while a status is on its way is not drawn over
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	fire( w.button( d, DEACTIVATE ), 'click' );
	w.answerAsk( '/_admin/users/confirm/deactivate-title' );
	fire( tag( d, 'button' ).find( b => b.attributes['aria-label'] === 'Next user' ), 'click' );
	check( 'a step to the next account while the request runs shows that account as it is', w.titleOf( d ) === 'a@example.com' );
	w.answer( 200, { status : 'disabled' } );
	check( '...the answer changes the account it was for, in the table, and not the one on screen: its buttons and its state line are the ones of that account', w.shown( d, ACTIVATE ) === true && w.shown( d, DEACTIVATE ) === false && w.readValue( d, text('/_admin/users/label/state') ) === 'Deactivated · locked until 2026-10-05 15:00 · Never logged in'
		&& byClass( w.cells('b@example.com')[3], 'nino-admin-badge' ).map( b => b.textContent ).join() === 'Deactivated' );
}

// --- the steps of the record skip what does not open -------------------------------------------------------------------------------

{
	const BEYOND = account( 'wide@example.com', { role : 'dev', canChange : false, perms : [ '/*' ] } );
	const w = world();
	w.open( listing( [ accounts()[0], accounts()[1], BEYOND, accounts()[2] ] ) );
	const next = d => tag( d, 'button' ).find( b => b.attributes['aria-label'] === 'Next user' );
	const previous = d => tag( d, 'button' ).find( b => b.attributes['aria-label'] === 'Previous user' );
	const d = w.openRow('b@example.com');

	check( 'the place counts the accounts that open - three of the four rows - and not the one beyond reach', byClass( d, 'nino-admin-record-count' )[0].textContent === '2 of 3' );
	fire( next( d ), 'click' );
	check( 'the step after b@ is the account behind the one beyond reach: it is skipped, the title and the place say so, and no question was asked', w.titleOf( d ) === 'a@example.com' && byClass( d, 'nino-admin-record-count' )[0].textContent === '3 of 3' && w.asking() === null && w.h.hash() === '#users/users/a%40example.com' );
	check( '...there is no step beyond the last that opens, and back goes to b@, the beyond account not between', next( d ).disabled === true && ( fire( previous( d ), 'click' ), w.titleOf( d ) === 'b@example.com' ) );

	// With typed input the question is asked for a step that goes somewhere, and Discard does go there
	w.type( w.field( d, 'name' ), 'Typed' );
	fire( next( d ), 'click' );
	check( 'with input typed the step asks Save, Discard or Cancel once, since it leads to an account that opens', w.asking() !== null && w.titleOf( d ) === 'b@example.com' );
	w.answerAsk( '/_admin/common/label/discard' );
	check( '...and a Discard shows that account', w.titleOf( d ) === 'a@example.com' && w.field( d, 'name' ).value === '' );
	w.type( w.field( d, 'name' ), 'Typed again' );
	fire( next( d ), 'click' );
	check( '...at the end of the walk a step that leads nowhere asks nothing and keeps the input', w.asking() === null && w.field( d, 'name' ).value === 'Typed again' && w.titleOf( d ) === 'a@example.com' );
}

// --- an answer that comes after the record moved on ------------------------------------------------------------------------------------

{
	for( const [ kind, label, confirmKey ] of [ [ 'status', DEACTIVATE, '/_admin/users/confirm/deactivate-title' ], [ 'logoutall', LOGOUT, '/_admin/users/confirm/logoutall-title' ] ] ) {
		const w = world();
		w.open();
		const d = w.openRow('b@example.com');
		fire( w.button( d, label ), 'click' );
		w.answerAsk( confirmKey );
		fire( tag( d, 'button' ).find( b => b.attributes['aria-label'] === 'Next user' ), 'click' );
		w.toasts.length = 0;
		w.answer( 409, { error : 'refused', code : 'users_last_admin_status' } );
		check( 'a refusal of users/'+ kind+ ' that arrives after the record showed the next account is not said on that account: its line is quiet', w.titleOf( d ) === 'a@example.com' && w.lineOf( d ).dataset.state !== 'error' && w.lineOf( d ).textContent === '' );
		check( '...it is said over the table, naming the account it was for and the reason - and never as a toast', w.notice().join() === w.h.ui.format( text('/_admin/users/error/late'), 'b@example.com', w.h.ui.api.errorText( 409, { error : 'refused', code : 'users_last_admin_status' }, '' ) ) && w.toasts.length === 0 && text('/_admin/users/error/late') !== '' );
		check( '...and the account on screen is as it was', w.shown( d, ACTIVATE ) === true && byClass( w.cells('b@example.com')[3], 'nino-admin-badge' ).length === 0 );
	}
}

// --- Save: the own account asks for a save with a confirmation ---------------------------------------------------------------------------

{
	const w = world();
	w.open();
	const d = w.openRow('me@example.com');
	const sent = w.requests.length;

	w.type( w.field( d, 'currentPassword' ), 'the current one' );
	w.save( d );
	check( 'nothing changed but the current password typed: the save is sent as it always was - the account, the address, no new password and the confirmation, and no role or name', w.requests.length === sent + 1
		&& JSON.stringify( w.last().payload ) === '{"username":"me@example.com","mail":"me@example.com","pw":"","currentPassword":"the current one"}' );
	w.answer( 401, { error : 'wrong current password', code : 'wrong_password', field : 'currentPassword' } );
	check( '...a wrong password stands at its field and in the line, and is never answered with "Saved."', w.field( d, 'currentPassword' ).attributes['aria-invalid'] === 'true' && w.lineOf( d ).dataset.state === 'error' && w.toasts.length === 0 && w.focused() === w.field( d, 'currentPassword' ) );
	w.type( w.field( d, 'currentPassword' ), 'the right one' );
	w.save( d );
	w.answer( 200, { mail : 'me@example.com', role : 'dev' } );
	check( '...a right one is saved, said as "Saved.", and the field is emptied', w.toasts.at(-1) === text('/_admin/common/msg/saved') && w.field( d, 'currentPassword' ).value === '' && errorsOf( d ).length === 0 && w.last().payload.currentPassword === 'the right one' );
}

// --- Delete ------------------------------------------------------------------------------------------------------------------------------

{
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	const sent = w.requests.length;

	fire( w.button( d, DELETE ), 'click' );
	const asked = w.questions.at(-1);
	check( 'the delete question is a question of the destructive level, with a title and the answer worded like it, standing on the record', asked.level === 'destructive' && asked.title === asked.yes && asked.title === text('/_admin/users/confirm/delete-title') && asked.nested === true && asked.message === text('/_admin/users/confirm/delete') && asked.opener === w.button( d, DELETE ) );
	w.answerAsk( CANCEL );
	check( 'a delete that is answered No sends nothing, and the record stays', w.requests.length === sent && w.record() === d && w.ids().length === 3 );

	fire( w.button( d, DELETE ), 'click' );
	w.answerAsk( '/_admin/users/confirm/delete-title' );
	check( '...a Yes sends users/delete for that account', w.requests.length === sent + 1 && w.last().action === 'users/delete' && JSON.stringify( w.last().payload ) === '{"username":"b@example.com"}' );
	w.answer( 200, { ok : true } );
	check( '...the record is closed by it, the account is gone from the table, a toast says it was deleted, and the focus goes to the row after the one that went', w.record() === null && w.ids().join() === 'me@example.com,a@example.com' && w.toasts.at(-1) === text('/_admin/common/msg/deleted') && w.focused() === w.row('a@example.com') );
}

{
	for( const [ status, code, label ] of [ [ 409, 'users_last_admin_delete', 'the last account with full access' ], [ 400, 'users_delete_self', 'the own account' ], [ 403, 'users_account_beyond', 'beyond' ], [ 404, 'unknown', 'unknown' ] ] ) {
		const w = world();
		w.open();
		const d = w.openRow('b@example.com');
		fire( w.button( d, DELETE ), 'click' );
		w.answerAsk( '/_admin/users/confirm/delete-title' );
		w.answer( status, { error : 'refused', code : code === 'unknown' ? undefined : code, params : [ '/*' ] } );
		check( 'a delete that is refused ('+ label+ ') is said in the line of the bar, the record stays open and the table is as it was', w.lineOf( d ).dataset.state === 'error' && w.lineOf( d ).textContent !== '' && w.record() === d && w.ids().join() === 'me@example.com,b@example.com,a@example.com' && w.toasts.length === 0 );
	}
}

// --- the accounts are read again, quietly, when the tab is shown ----------------------------------------------------------------------

{
	const w = world();
	w.open();
	w.type( w.search(), 'a@' );
	fire( tag( w.head('mail'), 'button' )[0], 'click' );
	const sent = w.requests.length;

	w.leaveAndReturn();
	check( 'showing the panel again reads the list again, in one request, with no sentence that it is loading: the table stays as it is', w.requests.length === sent + 1 && w.last().action === 'users/list' && byClass( w.mount, 'nino-admin-loading' ).length === 0 && w.ids().join() === 'a@example.com' );

	// A role made on the Roles tab is in the select of the record that opens next
	const roles = ROLES.concat( [ { id : 'author', label : 'Author', canGrant : true } ] );
	w.answer( 200, listing( accounts().concat( [ account( 'n@example.com', { role : 'author' } ) ] ), { roles : roles } ) );
	check( '...the rows are the new ones, the search and the sort are what the person chose', w.ids().join() === 'a@example.com' && w.panel._directory.state().query === 'a@' && w.panel._directory.state().sort.key === 'mail' );
	w.type( w.search(), '' );
	const d = w.openRow('b@example.com');
	check( '...and the role select of a record that is opened afterwards has the roles of the last answer', findAll( w.field( d, 'role' ), o => o.tagName === 'OPTION' ).map( o => o.value ).join() === 'dev,editor,author' );
	w.close( d );

	// The category follows the roles the accounts hold
	check( 'a role that an account holds for the first time is in the category', findAll( w.selects().find( el => findAll( el, o => o.textContent === text('/_admin/users/filter/allroles') ).length > 0 ), o => o.tagName === 'OPTION' ).map( o => o.textContent ).join() === 'All roles,Developer,Editor,Author' );
}

{
	// Not while the record is open, and not while an address is followed
	const w = world();
	w.open();
	const d = w.openRow('b@example.com');
	const sent = w.requests.length;
	w.panel.showCurrent();
	w.panel.showCurrent();
	check( 'a record that is open is never read over: the panel shown again asks nothing', w.requests.length === sent && w.record() === d );

	const v = world();
	v.open();
	const before = v.requests.length;
	v.typed( codec.href( 'users', [ 'b@example.com' ] ) );
	check( 'an address that names an account is followed by opening it, and no list is read for it', v.record() !== null && v.requests.length === before );
	v.close( v.record() );

	// A list that cannot be read is said where the table is, and read again at the next showing
	const u = world();
	u.open();
	u.leaveAndReturn();
	u.answer( 500, null );
	check( 'a list that cannot be read is said in place of the table, with the sentence of the panel', byClass( u.mount, 'nino-admin-notice' ).length + byClass( u.mount, 'nino-admin-error' ).length + tag( u.mount, 'p' ).filter( el => el.textContent.includes( text('/_admin/users/error/load') ) ).length > 0 );
	u.leaveAndReturn();
	check( '...and the next showing reads it again, with the loading sentence', u.last().action === 'users/list' && u.requests.length === 3 && byClass( u.mount, 'nino-admin-loading' ).length === 1 );
	u.answer( 200, listing() );
	check( '...and the table is there', u.ids().length === 3 );
}

// --- an account that may manage users is a manager from the list's answer ------------------------------------------------------------

{
	// The bar of the list follows the answer
	const w = world();
	w.open( listing( accounts(), { canManage : false } ) );
	check( 'no bar where the list says that the account may not manage users', w.bar() === undefined );
	w.leaveAndReturn();
	w.answer( 200, listing( accounts(), { canManage : true } ) );
	check( '...and a bar once it says that it may', w.bar() !== undefined && w.newButton().textContent === text('/_admin/users/label/new') );
	fire( w.newButton(), 'click', { currentTarget : w.newButton() } );
	check( '...with a form that makes an account behind its button - the record is made for the account that may', w.record() !== null && w.titleOf( w.record() ) === text('/_admin/users/label/new') && w.field( w.record(), 'pw' ) !== undefined );
	w.close( w.record() );
	w.leaveAndReturn();
	w.answer( 200, listing( accounts(), { canManage : false } ) );
	check( '...and none again once it says it may not', w.bar() === undefined );
}

// --- the focus -------------------------------------------------------------------------------------------------------------------------------

{
	const w = world();
	w.open();

	fire( w.newButton(), 'click', { currentTarget : w.newButton() } );
	fire( w.cross( w.record() ), 'click' );
	check( 'the form of a new account closed by its cross gives the focus back to the button New user', w.record() === null && w.focused() === w.newButton() );
	fire( w.newButton(), 'click', { currentTarget : w.newButton() } );
	fire( w.record(), 'keydown', { key : 'Escape', preventDefault() {} } );
	check( 'Escape on a form that nothing was typed in closes it: the address is the directory\'s and the entry of the history is taken off', w.record() === null && w.h.went.at(-1) === -1 );
}

check( 'nothing a person typed or the server said was written as markup in the whole run: no element was given anything but nothing as its innerHTML', markupWrites.length === 0 );

// --- every address the panel wrote or drew is read back ---------------------------------------------------------------------------------

check( 'every address the panel wrote or drew - the directory, an account, a new one - is read back by the router as the same screen and the same parts', codec.written.length > 20 && codec.mismatches.length === 0
	&& codec.written.some( entry => typeof entry.parts[0] === 'object' && String( entry.parts[0] ) === '@new' ) && codec.written.every( entry => entry.parts[0] !== 'new' ) && codec.written.some( entry => entry.parts[0] === 'c@example.com' ) );

// --- the words the four scripts of the panel name ----------------------------------------------------------------------------------------

{
	const scripts = [ 'admin.js', 'roles.js', 'lockout.js', 'recoverypw.js' ];
	const keys = new Set();

	scripts.forEach( script => {
		for( const found of source('_admin/Nino/Modules/Users/assets/'+ script).matchAll( /'(\/_admin\/[a-z0-9\/_-]*[a-z0-9])'/g ) )
			keys.add( found[1] );
	} );

	const lacking = [ ...keys ].filter( key => ( moduleEn[key] ?? workbench[key] ?? '' ) === '' || ( moduleDe[key] ?? workbenchDe[key] ?? '' ) === '' );
	check( 'every word the scripts of the panel name is a fill in English and in German'+ ( lacking.length === 0 ? '' : ' - not '+ lacking.join(', ') ), keys.size > 80 && lacking.length === 0 );

	// What the accounts part asks for in particular: the keys of admin.js alone
	const own = new Set();

	for( const found of source('_admin/Nino/Modules/Users/assets/admin.js').matchAll( /(?:getText|word)\(\s*'(\/_admin\/[a-z0-9\/_-]+)'\s*\)/g ) )
		own.add( found[1] );
	for( const found of source('_admin/Nino/Modules/Users/assets/admin.js').matchAll( /'(\/_admin\/[a-z0-9\/_-]*[a-z0-9])'/g ) )
		own.add( found[1] );

	check( 'the accounts script asks for '+ own.size+ ' keys, and each has words in English and in German', own.size > 50 && [ ...own ].every( key => ( moduleEn[key] ?? workbench[key] ?? '' ) !== '' && ( moduleDe[key] ?? workbenchDe[key] ?? '' ) !== '' ) );

	// No fill of the module is left that nothing reads: the keys the four scripts name, the refusals and the manual and the rail read by others, and what the PHP of the module names
	const php = [ 'Admin/Admin.php', 'Roles/Roles.php', 'Lockout/Lockout.php', 'RecoveryPassword/RecoveryPassword.php' ].map( file => source('_admin/Nino/Modules/Users/'+ file) ).join('\n');
	const readElsewhere = key => /^\/_admin\/(error\/|dashboard\/|nav\/|users\/manual\/)/.test( key ) || php.includes( "'"+ key+ "'" );
	const unused = Object.keys( moduleEn ).filter( key => readElsewhere( key ) === false && keys.has( key ) === false );
	check( 'no fill of the module is left that nothing reads'+ ( unused.length === 0 ? '' : ' - '+ unused.join(', ') ), unused.length === 0 );
	check( 'the two languages have the same fills, none of them empty', Object.keys( moduleEn ).sort().join() === Object.keys( moduleDe ).sort().join() && Object.keys( moduleEn ).every( key => moduleEn[key].trim() !== '' && moduleDe[key].trim() !== '' ) );
	check( 'the German of the panel says "Du" with a capital where it speaks to the person', Object.keys( moduleDe ).filter( key => key.startsWith('/_admin/users/') ).every( key => /\bdu\b|\bdein(e[mnrs]?)?\b|\bdir\b|\bdich\b/.test( moduleDe[key] ) === false ) && moduleDe['/_admin/users/badge/beyond'] === 'Mehr Rechte als Du' && moduleDe['/_admin/users/label/you'] === 'Du' );
	check( 'the manual says what the panel is: the table, the role, Show, and the account that is beyond - in both languages', moduleEn['/_admin/users/manual/account'].includes('table') && moduleEn['/_admin/users/manual/account'].includes('Show') && moduleDe['/_admin/users/manual/account'].includes('Tabelle') && moduleDe['/_admin/users/manual/account'].includes('Anzeigen') );
}

// --- the stylesheet of the accounts: scoped to the panel's own pane, with the tokens of the design system ------------------------------------

{
	const sheet = source('_admin/Nino/Modules/Users/assets/admin.css').replace( /\/\*[\s\S]*?\*\//g, '' );
	const selectors = [ ...sheet.matchAll( /([^{}]+)\{/g ) ].map( found => found[1].trim() ).filter( one => one !== '' && one.startsWith('@') === false ).flatMap( one => one.split(',').map( part => part.trim() ) );
	const colours = sheet.match( /#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/g ) ?? [];

	check( 'every selector of the stylesheet starts at an id of the panel, and none names the pane of the forms that is gone', selectors.length > 3 && selectors.every( one => /^#(users-list|roles-form|roles-list|roles-permissions-scoped|roles-permissions-summary)\b/.test( one ) ) && selectors.some( one => one.startsWith('#users-form') ) === false );
	check( '...and it writes no colour of its own: the tokens of the design system', colours.length === 0 );
	check( 'a badge of the state says its words in one line, and the address of an account is cut by an ellipsis', /\.users-states > \.nino-admin-badge \{[^}]*white-space: nowrap/.test( sheet ) && /\.users-account \{[^}]*text-overflow: ellipsis/.test( sheet ) );
}

done();

})();
