/**
 *	Nino									A compact filesystembased php framework
 *	admin-script-js-smoke.js	DOM-free checks for shared editor routing
 *										and CSV safety helpers.
 *
 *	Usage: node tests/admin-script-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
const vm 		= require('vm');

let checks = 0;
let failures = 0;

function check( label, condition ) {
	checks++;
	if( condition === true ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

const sandbox = {
	console : console,
	location : { hash : '' },
	history : { replaceState : function() {} },
	// The shell rendered one pane (users) holding one tab pane (roles) - the
	// DOM is the router's list of panels, see router.exists()
	document : { documentElement : null, body : null, getElementById : function( id ) { return [ 'admin-content-users', 'admin-tab-roles' ].indexOf( id ) !== -1 ? {} : null } },
};
sandbox.window = sandbox;
sandbox.Nino = {
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/assets/script.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'script.js' }
);

const editor = sandbox.Nino.admin;

sandbox.location.hash = '#elements/demo%20type/item';
let route = editor.router.current();
check( 'valid hash components are decoded', route.panel === 'elements' && route.parts[0] === 'demo type' && route.parts[1] === 'item' );

sandbox.location.hash = '#elements/bad%hash';
route = editor.router.current();
check( 'a malformed percent escape falls back safely', route.panel === '' && route.parts.length === 0 );

check( 'a pane the shell rendered is a panel the router knows', editor.router.exists('users') === true );
check( 'a tab of a pane is a panel of its own to the router', editor.router.exists('roles') === true );
check( 'a name without a pane, or one that is not a slug, is no panel', editor.router.exists('nope') === false && editor.router.exists('Roles') === false && editor.router.exists( 42 ) === false );

editor.decodeEntities = function( value ) { return value };
check( 'a leading equals sign is exported as explicit text', editor.csvCell('=2+2') === "'=2+2" );
check( 'a formula marker hidden behind whitespace is neutralized too', editor.csvCell(' \t+SUM(A1:A2)') === "' \t+SUM(A1:A2)" );
check( 'a regular value is unchanged', editor.csvCell('hello') === 'hello' );
check( 'CSV quoting still applies after neutralization', editor.csvCell('=1,2') === '"\'=1,2"' );

/*	The export's columns are the union of every row's keys, in the order
	they first appear - not the first row's alone. The Submissions panel
	deliberately lists several forms in one view ("All forms"), so the first
	entry's fields are not the file's columns: every field the other forms
	carry and it does not was dropped from the file without a word, and one
	entry recorded before ids existed took 'id' and 'form' down with it for
	every row below	*/
let exported = '';
sandbox.Blob = function( parts ) { exported = parts.join('') };
sandbox.URL = { createObjectURL : function() { return 'blob:x' }, revokeObjectURL : function() {} };
sandbox.document.createElement = function() { return { click : function() {}, remove : function() {} } };
sandbox.document.body = { appendChild : function() {} };

editor.exportCsv( 'submissions.csv', [
	{ id : '1', form : 'contact', name : 'Ada' },
	{ id : '2', form : 'quote', company : 'Acme' },
	{ note : 'written before ids existed' },
] );

check( 'a csv export carries every row\'s columns, not the first row\'s',
	exported === '\uFEFFid,form,name,company,note\r\n1,contact,Ada,,\r\n2,quote,,Acme,\r\n,,,,written before ids existed' );

/*	The rail and its panes, driven for real. Which panel is open was a class
	the stylesheet paints and nothing else, so the navigation read as a run of
	links with nothing to tell them apart; and every pane strip but the one
	the shell happened to open reported a tablist with no selected tab and one
	tab stop per tab.

	A second context rather than more of the one above: onReady() wants a dom,
	and the router checks up there want one that answers nothing	*/
function node( id, attributes ) {
	const classes = new Set();
	const el = {
		id : id || '', hidden : false, tabIndex : 0, focused : false,
		dataset : Object.assign( {}, attributes || {} ),
		attributes : {}, listeners : {}, children : [], parent : null,
		classList : {
			add : function( name ) { classes.add( name ) },
			remove : function( name ) { classes.delete( name ) },
			contains : function( name ) { return classes.has( name ) },
			toggle : function( name, force ) {
				if( force === true ) classes.add( name );
				else if( force === false ) classes.delete( name );
				else if( classes.has( name ) ) classes.delete( name );
				else classes.add( name );
				return classes.has( name );
			},
			forEach : function( fn ) { Array.from( classes ).forEach( fn ) },
			[Symbol.iterator] : function() { return classes[Symbol.iterator]() },
		},
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		removeAttribute : function( name ) { delete el.attributes[name] },
		getAttribute : function( name ) { return el.attributes[name] ?? null },
		addEventListener : function( name, fn ) { el.listeners[name] = fn },
		focus : function() { el.focused = true },
		closest : function() { return el.parent === null ? null : ( el.parent.dataset.panel !== undefined ? el.parent : el.parent.closest() ) },
		// A child by one class, the way the head's parts are reached
		querySelector : function( selector ) {
			return el.children.find( function( c ) { return c.classList.contains( selector.replace( ':scope > .', '' ) ) } ) || null;
		},
		querySelectorAll : function( selector ) {
			if( selector === ':scope > div[data-tab]' )
				return el.children.filter( function( c ) { return c.dataset.tab !== undefined && c.isStrip !== true } );
			if( selector === ':scope > button[data-tab]' )
				return el.children.filter( function( c ) { return c.dataset.tab !== undefined } );
			if( selector === ':scope > .admin-panel-head > .admin-panel-tabs > button[data-tab]' )
				return el.children.filter( function( c ) { return c.classList.contains('admin-panel-head') } )
					.flatMap( function( h ) { return h.children.filter( function( c ) { return c.classList.contains('admin-panel-tabs') } ) } )
					.flatMap( function( strip ) { return strip.children } );
			return [];
		},
		// Enough of a live tree for a strip to be put into a head and taken out again
		insertAdjacentElement : function( where, other ) {
			const at = el.parent.children.indexOf( el );
			el.parent.children.splice( where === 'afterend' ? at + 1 : at, 0, other );
			other.parent = el.parent;
			return other;
		},
		insertBefore : function( other, reference ) {
			const at = reference ? el.children.indexOf( reference ) : el.children.length;
			el.children.splice( at, 0, other );
			other.parent = el;
			return other;
		},
		remove : function() {
			if( el.parent === null ) return;
			const at = el.parent.children.indexOf( el );
			if( at !== -1 ) el.parent.children.splice( at, 1 );
			el.parent = null;
		},
	};
	Object.defineProperty( el, 'firstChild', { get : function() { return el.children[0] || null } } );
	Object.defineProperty( el, 'parentNode', { get : function() { return el.parent } } );
	return el;
}

const railLinks = { dashboard : node( 'admin-nav-dashboard', { panel : 'dashboard', layout : 'page' } ),
                    users 		: node( 'admin-nav-users', { panel : 'users', layout : 'page' } ) };

const rolesTab = node( 'admin-tabbutton-roles', { tab : 'roles' } );
const lockoutTab = node( 'admin-tabbutton-lockout', { tab : 'lockout' } );
const strip = node( '', {} );
strip.isStrip = true;
strip.classList.add('admin-panel-tabs');
strip.children = [ rolesTab, lockoutTab ];
rolesTab.parent = strip;
lockoutTab.parent = strip;

// The head the shell renders over the pane (see Panels::panesHtml()): the
// name, the strip beside it, the actions slot
const head = node( '', {} );
head.classList.add('admin-panel-head');
const title = node( '', {} );
title.classList.add('admin-panel-title');
const actions = node( '', {} );
actions.classList.add('admin-panel-actions');
head.children = [ title, strip, actions ];
title.parent = head;
strip.parent = head;
actions.parent = head;

const rolesPane = node( 'admin-tab-roles', { tab : 'roles' } );
const lockoutPane = node( 'admin-tab-lockout', { tab : 'lockout' } );
const usersPane = node( 'admin-content-users', { panel : 'users', layout : 'page' } );
usersPane.children = [ head, rolesPane, lockoutPane ];
head.parent = usersPane;

const dashboardPane = node( 'admin-content-dashboard', { panel : 'dashboard', layout : 'page' } );
const pageWrap = node( 'admin-page-wrap', {} );
const shellNodes = {
	'admin-page-wrap' : pageWrap,
	'admin-user-logout' : node( 'admin-user-logout', {} ),
	'admin-content-dashboard' : dashboardPane,
	'admin-content-users' : usersPane,
	'admin-tab-roles' : rolesPane,
	'admin-tab-lockout' : lockoutPane,
};

const shell = {
	console : console,
	location : { hash : '#users' },
	// What the router writes is what the bar shows - the stub keeps the two
	// in step the way a browser does, so the checks below can read it back
	history : { replaceState : function( state, title, url ) { shell.location.hash = url } },
	addEventListener : function() {},
	document : {
		documentElement : null, body : null,
		getElementById : function( id ) { return shellNodes[id] ?? null },
		addEventListener : function() {},
		querySelectorAll : function( selector ) {
			if( selector === '#admin-nav-wrap a[data-panel]' )
				return [ railLinks.dashboard, railLinks.users ];
			if( selector === '#admin-content-wrap > [data-panel]' )
				return [ dashboardPane, usersPane ];
			if( selector === '#admin-content-wrap > [data-panel] > .admin-panel-head > .admin-panel-tabs' )
				return [ strip ];
			return [];
		},
	},
};
shell.window = shell;
shell.Nino = { events : { bindCallback : function() {} } };

const shellContext = vm.createContext( shell );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ), shellContext, { filename : 'Nino.admin.js' } );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/script.js' ), 'utf8' ), shellContext, { filename : 'script.js' } );
shell.Nino.admin.onReady();

check( 'the rail says which panel is open rather than only painting it', railLinks.users.getAttribute('aria-current') === 'page' );
check( '...and no other link claims to be the current page', railLinks.dashboard.getAttribute('aria-current') === null );
railLinks.dashboard.listeners.click( { preventDefault : function() {} } );
check( 'the mark moves with the panel', railLinks.dashboard.getAttribute('aria-current') === 'page' && railLinks.users.getAttribute('aria-current') === null );

/*	And the address moves with it. A panel that keeps drill-down state writes
	the hash itself; the others never wrote it, so the bar kept naming the
	last panel that did - open Images, then Routes, and it still said #images,
	and a reload went back to Images. The shell writes the bare name after
	every switch the panel's own script did not settle	*/
check( 'a panel opened from the rail writes its name into the address', shell.location.hash === '#dashboard' );
railLinks.users.listeners.click( { preventDefault : function() {} } );
check( '...and a pane with tabs writes the tab that is open', shell.location.hash === '#roles' );

check( 'a pane strip starts with exactly one tab selected and one tab stop', rolesTab.getAttribute('aria-selected') === 'true'
	&& lockoutTab.getAttribute('aria-selected') === 'false' && rolesTab.tabIndex === 0 && lockoutTab.tabIndex === -1 );

rolesTab.listeners.keydown( { key : 'ArrowRight', preventDefault : function() {} } );
check( 'an arrow key opens the next tab of a pane strip', lockoutTab.getAttribute('aria-selected') === 'true'
	&& lockoutPane.hidden === false && rolesPane.hidden === true && lockoutTab.focused === true );
check( '...and the address names the tab now open', shell.location.hash === '#lockout' );

/*	The head over every pane but the Dashboard, reached from inside the pane.
	A panel with tabs of its own - Features, a feature's editors - puts its
	strip beside the name through it, so the workbench has one row rather
	than a title and a strip per panel; drawn again, the strip replaces the
	one before it rather than stacking	*/
const ui = shell.Nino.adminUi;
check( 'there is no head to reach from outside a pane', ui.panelHead( node( 'loose', {} ) ) === null && ui.panelHead( null ) === null );
const reached = ui.panelHead( rolesTab );
check( 'from inside a pane the head is the row over it, with the name and the actions slot', reached !== null && reached.element === head && reached.title === title && reached.actions === actions );
const own = node( 'own-strip', {} );
const ownAgain = node( 'own-strip-again', {} );
reached.tabs( own );
check( 'a strip of the panel\'s own goes in after the name and takes the class the head lays a strip out by', head.children[1] === own && own.classList.contains('admin-panel-tabs') && head.children.indexOf( strip ) === -1 && head.children.length === 3 );
reached.tabs( ownAgain );
check( '...and a strip drawn again replaces the one before it rather than stacking', head.children.indexOf( own ) === -1 && head.children[1] === ownAgain && head.children.length === 3 );

check( 'a shell whose page carries no dialog registers no session handler - a session failure is then handed back raw', shell.Nino.adminUi.api._handler === null );

/*	A session that ended under an open form: the dialog the shell puts over
	the page, the login it posts without leaving it, and what it does with
	the answer. The api decides whether the waiting requests are sent again
	(see tests/admin-api-js-smoke.js); this is the dialog and the login that
	stand between	*/
const requested = [];
const dialogNodes = {};
[ 'dialog', 'form', 'title', 'text', 'user', 'pw', 'msg', 'submit', 'reload', 'close' ].forEach( name => { dialogNodes[name] = node( 'admin-session-'+ name, {} ) } );
const accountNode = node( 'admin-user-email', {} );
accountNode.textContent = ' me@example.com ';
const userField = node( '', {} );
const pwField = node( '', {} );
userField.children = [ dialogNodes.user ]; dialogNodes.user.parent = userField;
pwField.children = [ dialogNodes.pw ]; dialogNodes.pw.parent = pwField;
dialogNodes.dialog.open = false;
dialogNodes.dialog.showModal = function() { dialogNodes.dialog.open = true };
// A browser fires 'close' after every way of closing a dialog
dialogNodes.dialog.close = function() {
	dialogNodes.dialog.open = false;
	dialogNodes.dialog.listeners.close();
};
dialogNodes.user.value = '';
dialogNodes.pw.value = '';

const texts = {
	'/_admin/common/session/title' : 'title text', '/_admin/common/session/other_title' : 'other title', '/_admin/common/session/expired' : 'expired text', '/_admin/common/session/other' : 'other text',
	'/_admin/common/session/wrong' : 'wrong text',
	'/_admin/common/session/error' : 'error %s text',
};
let reloaded = 0;
const withDialog = {
	console : console,
	location : { hash : '#users', reload : function() { reloaded++ } },
	history : { replaceState : function() {} },
	addEventListener : function() {},
	document : {
		documentElement : null, body : null,
		getElementById : function( id ) { return id === 'admin-page-wrap' ? pageWrap : ( id === 'admin-user-logout' ? shellNodes[id] : ( id === 'admin-user-email' ? accountNode : ( id.indexOf( 'admin-session-' ) === 0 ? dialogNodes[ id.slice( 14 ) ] : null ) ) ) },
		addEventListener : function() {},
		querySelectorAll : function() { return [] },
	},
};
withDialog.window = withDialog;
withDialog.Nino = {
	dir : '/sub',
	events : { bindCallback : function() {} },
	content : { getText : function( key ) { return texts[key] || '' } },
	http : { sendRequest : function( uri, method, callback, data, auth ) { requested.push( { uri : uri, method : method, callback : callback, data : data, auth : auth } ) } },
};
const dialogContext = vm.createContext( withDialog );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ), dialogContext, { filename : 'Nino.admin.js' } );
vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/script.js' ), 'utf8' ), dialogContext, { filename : 'script.js' } );
withDialog.Nino.admin.onReady();

const api = withDialog.Nino.adminUi.api;
check( 'a shell with the dialog registers it with the api', typeof api._handler === 'function' );

/** A request that finds the session gone, then the check's answer: the api's own path into the dialog */
function loseSession( user ) {
	const answers = [];
	const first = requested.length;
	api.call( 'x/save', { a : 1 }, function( status, body ) { answers.push( [ status, body ] ) } );
	requested[first].callback( { status : 401, responseJSON : { error : 'not logged in', code : 'session' }, getAllResponseHeaders : function() { return 'x: y' } } );
	requested[first + 1].callback( { status : 200, responseJSON : { user : user, csrf : 'token' } } );
	return answers;
}

let prevented = 0;
const escape = function() { prevented = 0; dialogNodes.dialog.listeners.cancel( { preventDefault : function() { prevented++ } } ); return prevented };
check( 'Escape does not close the dialog while no request waits and it is not open for a login', escape() === 0 );
dialogNodes.reload.listeners.click();
check( 'the reload button reloads the page', reloaded === 1 );
check( 'a dialog closed while no request waits stays closed', ( function() { dialogNodes.dialog.listeners.close(); return dialogNodes.dialog.open === false } )() );

// An expired session with a request waiting: only a login can bring it back
let answers = loseSession('');
check( 'an expired session opens the dialog through the api with the waiting request held', dialogNodes.dialog.open === true && api.waiting() === true && answers.length === 0 );
check( 'Escape does not close it while a login can still bring the waiting request back', escape() === 1 );
check( '...there is a Close button for the person who cannot log in', typeof dialogNodes.close.listeners.click === 'function' );

// The login fails (the account is gone, the password was changed): the person gives up
dialogNodes.user.value = 'gone@example.com';
dialogNodes.pw.value = 'old';
dialogNodes.form.listeners.submit( { preventDefault : function() {} } );
requested[requested.length - 1].callback( { status : 401 } );
check( 'a failed login leaves the dialog and the waiting request where they were', dialogNodes.msg.textContent === 'wrong text' && dialogNodes.dialog.open === true && api.waiting() === true && escape() === 1 );
dialogNodes.close.listeners.click();
check( 'Close gives the page back: the dialog is shut and the typed password is gone', dialogNodes.dialog.open === false && dialogNodes.pw.value === '' );
check( '...the waiting request got the answer it had, so the panel shows its error and gives its form back', answers.length === 1 && answers[0][0] === 401 && answers[0][1].code === 'session' && api.waiting() === false );

// Another account took over the browser's session: nothing to log in for
answers = loseSession('someone@else.example');
check( 'another account\'s session opens the dialog with the reload only', dialogNodes.dialog.open === true && dialogNodes.text.textContent === 'other text' && dialogNodes.submit.hidden === true && dialogNodes.close.hidden !== true && answers.length === 0 );
check( 'Escape closes it: there is no login that could bring the request back', escape() === 0 );
dialogNodes.dialog.close();
check( 'closing it releases the request with the answer it had', answers.length === 1 && answers[0][0] === 401 && answers[0][1].code === 'session' && api.waiting() === false && dialogNodes.dialog.open === false );

// Escape closing it in a browser: the same
answers = loseSession('');
dialogNodes.dialog.close();
check( 'a dialog closed by whatever means releases what waited - it is not opened again', answers.length === 1 && api.waiting() === false && dialogNodes.dialog.open === false );

// A check that failed: nothing waits any more, so Escape closes the dialog
answers = loseSession('');
dialogNodes.user.value = 'me@example.com';
dialogNodes.pw.value = 'secret';
dialogNodes.form.listeners.submit( { preventDefault : function() {} } );
requested[requested.length - 1].callback( { status : 200 } );
requested[requested.length - 1].callback( { status : 503, responseJSON : null } );
check( 'a login whose check then fails releases the request and says so in the dialog', dialogNodes.msg.textContent === 'error 503 text' && answers.length === 1 && api.waiting() === false && dialogNodes.dialog.open === true );
check( '...and Escape may close the dialog then', escape() === 0 );
dialogNodes.dialog.close();

let checkedWith = null;
const askAgain = function( done ) { checkedWith = done };
api._handler( askAgain, 'expired' );
check( 'an expired session opens the dialog as a modal with the expired text and the login fields', dialogNodes.dialog.open === true
	&& dialogNodes.title.textContent === 'title text' && dialogNodes.text.textContent === 'expired text' && userField.hidden === false && pwField.hidden === false && dialogNodes.submit.hidden === false && dialogNodes.user.focused === true );

const sentBefore = requested.length;
dialogNodes.user.value = '';
dialogNodes.pw.value = '';
dialogNodes.form.listeners.submit( { preventDefault : function() {} } );
check( 'an empty login is refused at once, in words, and nothing is sent', dialogNodes.msg.textContent === 'wrong text' && requested.length === sentBefore );

const lastRequest = function() { return requested[requested.length - 1] };

dialogNodes.user.value = 'me@example.com';
dialogNodes.pw.value = 'secret';
dialogNodes.form.listeners.submit( { preventDefault : function() {} } );
check( 'the login is posted from the dialog, to the project\'s login endpoint, with basic auth and no redirect', requested.length === sentBefore + 1 && lastRequest().uri === '/sub/.nino/auth/login' && lastRequest().method === 'POST'
	&& lastRequest().auth.user === 'me@example.com' && lastRequest().auth.pw === 'secret' && dialogNodes.submit.disabled === true );
lastRequest().callback( { status : 401 } );
check( 'a 401 says the credentials were wrong and lets the person try again', dialogNodes.msg.textContent === 'wrong text' && dialogNodes.submit.disabled === false && dialogNodes.dialog.open === true );
dialogNodes.form.listeners.submit( { preventDefault : function() {} } );
lastRequest().callback( { status : 503 } );
check( 'any other status says what was answered', dialogNodes.msg.textContent === 'error 503 text' );

dialogNodes.form.listeners.submit( { preventDefault : function() {} } );
lastRequest().callback( { status : 200 } );
check( 'a successful login does not leave the page - it has the api ask whose session this is', checkedWith !== null && dialogNodes.pw.value === '' && dialogNodes.dialog.open === true );
checkedWith( 'resumed', 200 );
check( '...and closes the dialog once the waiting requests were sent again', dialogNodes.dialog.open === false && dialogNodes.submit.disabled === false );

api._handler( askAgain, 'other' );
check( 'another account\'s session offers only the reload (and Close), under a title of its own', dialogNodes.title.textContent === 'other title' && dialogNodes.text.textContent === 'other text' && userField.hidden === true && pwField.hidden === true && dialogNodes.submit.hidden === true && dialogNodes.reload.focused === true );
dialogNodes.dialog.close();

dialogNodes.dialog.open = false;
api._handler( askAgain, 'expired' );
dialogNodes.user.value = 'me@example.com';
dialogNodes.pw.value = 'secret';
dialogNodes.form.listeners.submit( { preventDefault : function() {} } );
lastRequest().callback( { status : 200 } );
checkedWith( 'failed', 502 );
check( 'a check that fails after a login says so', dialogNodes.msg.textContent === 'error 502 text' && dialogNodes.dialog.open === true );

// a 403 from the login: the token was rotated elsewhere - the check fetches the new one
checkedWith = null;
dialogNodes.dialog.open = true;
dialogNodes.pw.value = 'secret';
dialogNodes.form.listeners.submit( { preventDefault : function() {} } );
lastRequest().callback( { status : 403 } );
check( 'a 403 from the login asks whose session this is instead of repeating the status', checkedWith !== null && dialogNodes.msg.textContent === '' && dialogNodes.submit.disabled === true );
checkedWith( 'resumed', 200 );
check( '...and closes the dialog if the same account turns out to be signed in', dialogNodes.dialog.open === false && dialogNodes.submit.disabled === false );

// ...and when it turns out that nobody is: the person is told, not left with a button that did nothing
checkedWith = null;
dialogNodes.dialog.open = true;
dialogNodes.pw.value = 'secret';
dialogNodes.form.listeners.submit( { preventDefault : function() {} } );
lastRequest().callback( { status : 403 } );
dialogNodes.msg.textContent = '';
checkedWith( 'expired', 200 );
check( 'a 403 from the login whose check finds the session still gone says so', dialogNodes.msg.textContent === 'error 403 text' && dialogNodes.submit.disabled === false );

withDialog.Nino.admin.sessionLocale.set( 'de_DE' );
check( 'the locale switch posts through the one request helper', requested[requested.length - 1].uri === '/sub/_admin/' && requested[requested.length - 1].data.action === 'admin/locale' && requested[requested.length - 1].data.data === '{"locale":"de_DE"}' );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
