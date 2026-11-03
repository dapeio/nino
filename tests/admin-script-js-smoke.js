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
const { check, done, load, element, findAll, fills } = require( './harness.js' );

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

load( '_admin/assets/script.js', vm.createContext( sandbox ) );

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

/*	What one panel tells another: a counter for each kind of data the panels
	write, raised by whoever wrote it and read by the panels that show it - no
	event, no list of listeners (see Nino.admin.touch())	*/
const kinds = [ 'templates', 'texts', 'elements', 'slots', 'routes', 'navigations' ];
check( 'every kind of data starts with nothing written, in a page that has just been loaded', kinds.every( function( kind ) { return editor.stamp( kind ) === 0 } ) );
editor.touch( 'templates' );
editor.touch( 'templates' );
editor.touch( 'routes' );
check( 'a touch raises the stamp of its kind by one, and of no other', editor.stamp( 'templates' ) === 2 && editor.stamp( 'routes' ) === 1
	&& [ 'texts', 'elements', 'slots', 'navigations' ].every( function( kind ) { return editor.stamp( kind ) === 0 } ) );
editor.touch( 'nope' );
editor.touch( 'toString' );
editor.touch( '__proto__' );
editor.touch( undefined );
check( 'a kind that is none of them is ignored by touch() and reads 0 - whatever its name, an inherited property of an object included',
	editor.stamp( 'nope' ) === 0 && editor.stamp( 'toString' ) === 0 && editor.stamp( '__proto__' ) === 0 && editor.stamp( undefined ) === 0
	&& Object.keys( editor._stamps ).sort().join() === kinds.slice().sort().join() && editor.stamp( 'templates' ) === 2 );
check( 'the stamps are numbers a panel compares by being unequal: a second touch is a different stamp from the first', ( function() { const before = editor.stamp( 'slots' ); editor.touch( 'slots' ); return typeof before === 'number' && editor.stamp( 'slots' ) !== before } )() );

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
// The element that has the focus: a page has one
let focusedNode = null;

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
		focus : function() { if( focusedNode !== null ) focusedNode.focused = false; focusedNode = el; el.focused = true },
		contains : function( other ) { for( let at = other; at !== null && at !== undefined; at = at.parent ) if( at === el ) return true; return false },
		closest : function() { return el.parent === null ? null : ( el.parent.dataset.panel !== undefined ? el.parent : el.parent.closest() ) },
		// A child by one class, the way the head's parts are reached
		querySelector : function( selector ) {
			// The registry's strip: one in the head whose buttons carry data-tab
			if( selector === ':scope > .admin-panel-tabs > button[data-tab]' )
				return el.children.filter( function( c ) { return c.classList.contains('admin-panel-tabs') } )
					.flatMap( function( c ) { return c.children } ).find( function( b ) { return b.dataset.tab !== undefined } ) || null;
			// The name of a panel: the title in the head of the pane
			if( selector === ':scope > .admin-panel-head > .admin-panel-title' )
				return el.children.filter( function( c ) { return c.classList.contains('admin-panel-head') } )
					.flatMap( function( h ) { return h.children } ).find( function( c ) { return c.classList.contains('admin-panel-title') } ) || null;
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
		// A child of another stand-in - a chip on the button of a tab - comes and goes
		appendChild : function( other ) {
			el.children.push( other );
			other.parent = el;
			other.parentNode = el;
			return other;
		},
		removeChild : function( other ) {
			const at = el.children.indexOf( other );
			if( at !== -1 ) el.children.splice( at, 1 );
			other.parent = null;
			other.parentNode = null;
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

// A rail link: the icon, and the label the phone's select takes its text from
function navLink( panel, label ) {
	const link = node( 'admin-nav-'+ panel, { panel : panel, layout : 'page' } );
	const text = node( '', {} );
	text.textContent = label;
	link.querySelector = function( selector ) { return selector === '.nino-admin-nav-label' ? text : null };
	return link;
}

// A group heading: a button, as Panels::$html renders it
function navHeading( group, label ) {
	const heading = node( '', { group : group } );
	heading.classList.add('nino-admin-nav-group');
	heading.setAttribute( 'aria-expanded', 'true' );
	heading.textContent = label;
	return heading;
}

const railLinks = { dashboard : navLink( 'dashboard', 'Dashboard' ),
                    users 		: navLink( 'users', 'Users' ) };
const railHeadings = { content : navHeading( 'content', 'Content' ), system : navHeading( 'system', 'System' ) };
const railNav = node( 'admin-nav-wrap', {} );
railNav.children = [ railHeadings.content, railLinks.dashboard, railHeadings.system, railLinks.users ];
railNav.setAttribute( 'aria-label', 'Navigation' );

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
// The first stop of the page, and the pane it jumps to
const skipLink = node( '', {} );
const contentWrap = node( 'admin-content-wrap', {} );
// The settings menu: the button that opens it, and what it holds
const settingsWrap = node( 'admin-nav-ui', {} );
const settingsToggle = node( 'admin-nav-ui-toggle', {} );
const settingsMenu = node( 'admin-nav-ui-menu', {} );
const themeToggle = node( 'admin-theme-toggle', {} );
settingsMenu.children = [ themeToggle ];
themeToggle.parent = settingsMenu;
settingsWrap.children = [ settingsToggle, settingsMenu ];
settingsToggle.parent = settingsWrap;
settingsMenu.parent = settingsWrap;
const shellNodes = {
	'admin-page-wrap' : pageWrap,
	'admin-content-wrap' : contentWrap,
	'admin-nav-ui' : settingsWrap,
	'admin-nav-ui-toggle' : settingsToggle,
	'admin-nav-ui-menu' : settingsMenu,
	'admin-nav-wrap' : railNav,
	'admin-user-logout' : node( 'admin-user-logout', {} ),
	'admin-content-dashboard' : dashboardPane,
	'admin-content-users' : usersPane,
	'admin-tab-roles' : rolesPane,
	'admin-tab-lockout' : lockoutPane,
	'admin-tabbutton-roles' : rolesTab,
	'admin-tabbutton-lockout' : lockoutTab,
};

// The interface-language picker: a select whose current option is the one the page was drawn with
const pickerOptions = [ { value : '/_admin?locale=de_DE', defaultSelected : true, selected : true }, { value : '/_admin?locale=en_US', defaultSelected : false, selected : false } ];
const picker = node( 'admin-localepicker', {} );
picker.options = pickerOptions;
picker.value = '/_admin?locale=en_US';
shellNodes['admin-localepicker'] = picker;

// The browser's history: what the router writes is what the bar shows, and
// pushState adds an entry where replaceState overwrites the one it is on - the
// stub keeps both in step the way a browser does, so the checks below can read
// the entries and the position back
const browser = { entries : [ '#users' ], states : [ null ], at : 0, traversals : [], timers : [] };
const storage = {};
const windowListeners = {};
const shell = {
	console : console,
	location : { hash : '#users', href : '' },
	history : {
		// Without an address replaceState keeps the one it is on, as in a browser
		replaceState : function( state, title, url ) { if( url !== undefined ) { browser.entries[browser.at] = url; shell.location.hash = url } browser.states[browser.at] = state },
		pushState : function( state, title, url ) { browser.entries.splice( browser.at + 1 ); browser.states.splice( browser.at + 1 ); browser.entries.push( url ); browser.states.push( state ); browser.at++; shell.location.hash = url },
		get state() { return browser.states[browser.at] },
		// A traversal is asynchronous: it is queued, and browser.arrive() lets it happen
		go : function( delta ) { browser.traversals.push( delta ) },
	},
	localStorage : {
		getItem : function( key ) { return storage[key] ?? null },
		setItem : function( key, value ) { storage[key] = String( value ) },
		removeItem : function( key ) { delete storage[key] },
	},
	addEventListener : function( type, fn ) { windowListeners[type] = fn },
	removeEventListener : function( type, fn ) { if( windowListeners[type] === fn ) delete windowListeners[type] },
	setTimeout : function( fn, ms ) { browser.timers.push( { fn : fn, ms : ms } ); return browser.timers.length },
	clearTimeout : function() {},
	document : {
		documentElement : null, body : null,
		get activeElement() { return focusedNode },
		getElementById : function( id ) { return shellNodes[id] ?? null },
		createElement : function( tag ) { return fakeElement( tag ) },
		addEventListener : function() {},
		querySelectorAll : function( selector ) {
			if( selector === '.nino-admin-skip' )
				return [ skipLink ];
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

/**
 *	The browser moves `delta` entries - the Back or Forward button, or a go() it was asked for -
 *	and tells the page the way it does: popstate always, hashchange when the address differs
 *
 *	@param		{number}		delta
 *
 *	@return		void
 */
browser.step = function( delta ) {
	const before = browser.entries[browser.at];
	browser.at += delta;
	shell.location.hash = browser.entries[browser.at];
	windowListeners.popstate?.();
	if( browser.entries[browser.at] !== before )
		windowListeners.hashchange();
};
// The traversals the page asked for arrive
browser.arrive = function() {
	while( browser.traversals.length > 0 )
		browser.step( browser.traversals.shift() );
};

const shellContext = vm.createContext( shell );
load( '_admin/assets/Nino.admin.js', shellContext );
load( '_admin/assets/script.js', shellContext );
shell.Nino.admin.onReady();

check( 'the page load selects the panel the hash names without adding an entry - the address is only made true to the screen', browser.entries.join() === '#roles' && browser.at === 0 );
check( 'the rail says which panel is open rather than only painting it', railLinks.users.getAttribute('aria-current') === 'page' );
check( '...and no other link claims to be the current page', railLinks.dashboard.getAttribute('aria-current') === null );
railLinks.dashboard.listeners.click( { preventDefault : function() {} } );
check( 'the mark moves with the panel', railLinks.dashboard.getAttribute('aria-current') === 'page' && railLinks.users.getAttribute('aria-current') === null );
check( 'a panel without a head to name it leaves the focus where the click put it', title.focused === false );

/*	And the address moves with it. A panel that keeps drill-down state writes
	the hash itself; the others never wrote it, so the bar kept naming the
	last panel that did - open Images, then Routes, and it still said #images,
	and a reload went back to Images. The shell writes the bare name after
	every switch the panel's own script did not settle	*/
check( 'a panel opened from the rail writes its name into the address', shell.location.hash === '#dashboard' );
railLinks.users.listeners.click( { preventDefault : function() {} } );
check( '...and a pane with tabs writes the tab that is open', shell.location.hash === '#roles' );
check( 'a panel opened from the rail starts at its name: the title has the focus, and is no stop of the Tab key', title.focused === true && title.getAttribute('tabindex') === '-1' );

/*	The skip link jumps to the pane and writes no address - an anchor jump
	would be answered by the router as a panel of that name	*/
const hashBefore = shell.location.hash;
let skipped = 0;
check( 'the pane is no target of the focus until the link is followed', contentWrap.getAttribute('tabindex') === null );
skipLink.listeners.click( { preventDefault : function() { skipped++ } } );
check( 'the skip link puts the focus on the pane and leaves the address alone', skipped === 1 && contentWrap.focused === true && shell.location.hash === hashBefore );
check( '...which can take it for the jump: the pane is made a target of the focus, and not a stop of the Tab key', contentWrap.getAttribute('tabindex') === '-1' );
contentWrap.listeners.blur();
check( '...and is none again as the focus leaves it, so that a click on a text in the pane starts the Tab key after the text', contentWrap.getAttribute('tabindex') === null );

/*	The settings menu is a disclosure. Closing it with the Escape key takes the
	focus out of the page when it was in the menu: a hidden element keeps no
	focus. It goes back to the button that opened it. A click on another control
	closes the menu too, and the focus is on that control by then	*/
settingsToggle.setAttribute( 'aria-expanded', 'true' );
themeToggle.focus();
shell.Nino.admin.navUi.close();
check( 'the menu closed with the focus in it gives the focus to the button that opened it', settingsMenu.classList.contains('admin-hidden') === true && settingsToggle.getAttribute('aria-expanded') === 'false' && settingsToggle.focused === true );
settingsMenu.classList.remove('admin-hidden');
railLinks.users.focus();
shell.Nino.admin.navUi.close();
check( '...and not when it was elsewhere: it stays on the control the person went to', settingsMenu.classList.contains('admin-hidden') === true && railLinks.users.focused === true && settingsToggle.focused === false );
title.focus();

check( 'a pane strip starts with exactly one tab selected and one tab stop', rolesTab.getAttribute('aria-selected') === 'true'
	&& lockoutTab.getAttribute('aria-selected') === 'false' && rolesTab.tabIndex === 0 && lockoutTab.tabIndex === -1 );

rolesTab.listeners.keydown( { key : 'ArrowRight', preventDefault : function() {} } );
check( 'an arrow key opens the next tab of a pane strip', lockoutTab.getAttribute('aria-selected') === 'true'
	&& lockoutPane.hidden === false && rolesPane.hidden === true && lockoutTab.focused === true );
check( '...and the address names the tab now open', shell.location.hash === '#lockout' );

/*	What Back and Forward walk is the workbench. A move the person makes -
	the rail, a tab - adds an entry; whatever only keeps the address true to
	the screen (the page load above, the arrow keys, a panel showing its level
	again) replaces the one it is on, or the history would fill with steps
	nobody took	*/
check( 'each click on the rail added one entry; the arrow keys replaced the one they were on', browser.entries.join() === '#roles,#dashboard,#lockout' && browser.at === 2 );

rolesTab.listeners.click();
check( 'a click on a tab adds an entry', browser.entries.join() === '#roles,#dashboard,#lockout,#roles' && browser.at === 3 );

// A panel that writes its level while it is shown (the Dashboard, the drill-down panels) is
// settled before the shell looks: the switch is still one entry, and the one before it is kept
shell.Nino.admin.dashboard = { showCurrent : function() { shell.Nino.admin.router.set( 'dashboard', [] ) } };
railLinks.dashboard.listeners.click( { preventDefault : function() {} } );
check( 'a panel that writes the address itself while it is shown still yields exactly one entry, and the previous one survives', browser.entries.join() === '#roles,#dashboard,#lockout,#roles,#dashboard' && browser.at === 4 );

let defaultsPrevented = 0;
const before = browser.entries.join();
[ { ctrlKey : true }, { metaKey : true }, { shiftKey : true }, { altKey : true }, { button : 1 }, { defaultPrevented : true } ].forEach( function( flags ) {
	railLinks.users.listeners.click( Object.assign( { preventDefault : function() { defaultsPrevented++ } }, flags ) );
} );
check( 'a click with a modifier key, with another button or already handled is left to the browser: a new tab opens the link\'s own address', defaultsPrevented === 0 && browser.entries.join() === before && railLinks.dashboard.getAttribute('aria-current') === 'page' );

// Back: the browser moves to the previous entry, which changes the hash and nothing else
browser.step( -1 );
check( 'Back selects the panel the earlier entry names - a tab of a pane through its owner', railLinks.users.getAttribute('aria-current') === 'page' && rolesPane.hidden === false && lockoutPane.hidden === true );
check( '...and adds nothing to the history', browser.entries.length === 5 && browser.at === 3 );
browser.step( 1 );
check( 'Forward selects the one after it, again without an entry', railLinks.dashboard.getAttribute('aria-current') === 'page' && browser.entries.length === 5 && browser.at === 4 );

/*	The rail's group headings and the phone's select. The headings are buttons
	that fold their links; the fold is the browser's own preference, and the
	panel on screen always has its group open	*/
const navSelect = railNav.children[0];
check( 'the nav gets a select built from the rail, and says so, so the stylesheet hides the strip only where the select exists', navSelect.className === 'nino-admin-nav-select' && railNav.classList.contains('nino-admin-nav--select') === true );
check( '...one optgroup per heading, labelled by it, holding that group\'s panels by name', navSelect.children.length === 2
	&& navSelect.children[0].label === 'Content' && navSelect.children[0].children.map( o => o.value+ ':'+ o.textContent ).join() === 'dashboard:Dashboard'
	&& navSelect.children[1].label === 'System' && navSelect.children[1].children.map( o => o.value+ ':'+ o.textContent ).join() === 'users:Users' );
check( '...named like the nav itself', navSelect.attributes['aria-label'] === 'Navigation' );
check( '...and showing the panel on screen', navSelect.value === 'dashboard' );

const GROUPS_KEY = 'nino-admin-nav-groups';
railHeadings.content.listeners.click();
check( 'a heading folds its group: its links and no others, aria-expanded says so, the state is kept', railLinks.dashboard.classList.contains('nino-admin-nav-collapsed') === true
	&& railLinks.users.classList.contains('nino-admin-nav-collapsed') === false
	&& railHeadings.content.getAttribute('aria-expanded') === 'false' && railHeadings.system.getAttribute('aria-expanded') === 'true'
	&& storage[GROUPS_KEY] === '["content"]' );
railHeadings.content.listeners.click();
check( '...and opens it again', railLinks.dashboard.classList.contains('nino-admin-nav-collapsed') === false && railHeadings.content.getAttribute('aria-expanded') === 'true' && storage[GROUPS_KEY] === undefined );

storage[GROUPS_KEY] = '["content","system"]';
shell.Nino.admin.navGroups.apply();
check( 'what the browser kept is what is folded', railLinks.dashboard.classList.contains('nino-admin-nav-collapsed') === true && railLinks.users.classList.contains('nino-admin-nav-collapsed') === true );
railLinks.dashboard.listeners.click( { preventDefault : function() {} } );
check( 'selecting a panel opens its group - and only that one', railLinks.dashboard.classList.contains('nino-admin-nav-collapsed') === false
	&& railHeadings.content.getAttribute('aria-expanded') === 'true' && railLinks.users.classList.contains('nino-admin-nav-collapsed') === true && storage[GROUPS_KEY] === '["system"]' );

navSelect.value = 'users';
navSelect.listeners.change();
check( 'a change of the select opens that panel as a person\'s own move: one entry, its group open', railLinks.users.getAttribute('aria-current') === 'page'
	&& browser.entries[browser.entries.length - 1] === '#roles' && browser.entries.length === 6 && railLinks.users.classList.contains('nino-admin-nav-collapsed') === false && storage[GROUPS_KEY] === undefined );

/*	The address of a panel is its own screen. #<panel> - typed, linked, or written by the back link of a panel - opens the
	first tab of the pane, whichever was open last; #<tab> opens that tab. The rail is the way back to where one was: its
	links, and the select that stands for it on a phone, open the tab last open and write that tab's address	*/
{
	// An address typed: the browser makes the entry and tells the page, with no step of ours behind it
	const typed = function( hash ) {
		browser.entries.splice( browser.at + 1 );
		browser.states.splice( browser.at + 1 );
		browser.entries.push( hash );
		browser.states.push( null );
		browser.at++;
		shell.location.hash = hash;
		windowListeners.hashchange();
	};
	const openTab = function() { return rolesPane.hidden === false && lockoutPane.hidden === true ? 'roles' : ( lockoutPane.hidden === false && rolesPane.hidden === true ? 'lockout' : '' ) };
	const clickRail = function( link ) { link.listeners.click( { preventDefault : function() {} } ) };

	lockoutTab.listeners.click();
	check( 'the second tab of the pane is open and its address is written', openTab() === 'lockout' && shell.location.hash === '#lockout' );

	clickRail( railLinks.dashboard );
	typed( '#users' );
	check( 'the address of a panel with tabs opens its first tab, not the one open last: the panel\'s own screen', openTab() === 'roles' && railLinks.users.getAttribute('aria-current') === 'page' );
	check( '...the strip says so, and the address becomes the first tab\'s', rolesTab.getAttribute('aria-selected') === 'true' && lockoutTab.getAttribute('aria-selected') === 'false'
		&& rolesTab.tabIndex === 0 && lockoutTab.tabIndex === -1 && shell.location.hash === '#roles' );

	lockoutTab.listeners.click();
	typed( '#users' );
	check( '...also with the panel on screen, on its second tab: a link to the panel or its back link says the panel', openTab() === 'roles' && shell.location.hash === '#roles' );

	clickRail( railLinks.dashboard );
	typed( '#lockout' );
	check( 'the address of a tab opens that tab', openTab() === 'lockout' && railLinks.users.getAttribute('aria-current') === 'page' && shell.location.hash === '#lockout' );

	clickRail( railLinks.dashboard );
	clickRail( railLinks.users );
	check( 'the rail opens the tab last open, and writes its address', openTab() === 'lockout' && shell.location.hash === '#lockout' );

	navSelect.value = 'dashboard';
	navSelect.listeners.change();
	navSelect.value = 'users';
	navSelect.listeners.change();
	check( '...so does the select that stands for the rail on a phone', openTab() === 'lockout' && shell.location.hash === '#lockout' && railLinks.users.getAttribute('aria-current') === 'page' );

	// What the checks below find, as before: the pane on its first tab
	rolesTab.listeners.click();
	check( 'a click on the first tab is the way back to where the checks below start', openTab() === 'roles' && shell.location.hash === '#roles' );
}

/*	A word on the button of a tab (Nino.adminUi.tabBadge()) stays through every move of the shell: the strip is rendered by the
	server and wired once, and a move changes the classes and attributes of its buttons, never the buttons	*/
{
	const realCreate = shell.document.createElement;
	shell.document.createElement = element;

	const chipsOn = function( button ) { return button.children.filter( function( child ) { return child.classList.contains('nino-admin-tab-badge') } ) };
	const clickRail = function( link ) { link.listeners.click( { preventDefault : function() {} } ) };

	shell.Nino.adminUi.tabBadge( 'lockout', '2', 'warn' );
	const chip = chipsOn( lockoutTab )[0];
	check( 'a word is drawn on the button the shell rendered for the tab, and on no other', chipsOn( lockoutTab ).length === 1 && chip.textContent === '2' && chip.dataset.state === 'warn' && chipsOn( rolesTab ).length === 0 );

	rolesTab.listeners.click();
	lockoutTab.listeners.click();
	clickRail( railLinks.dashboard );
	clickRail( railLinks.users );
	navSelect.value = 'dashboard';
	navSelect.listeners.change();
	navSelect.value = 'users';
	navSelect.listeners.change();
	rolesTab.listeners.keydown( { key : 'ArrowRight', preventDefault : function() {} } );
	browser.step( -1 );
	browser.step( 1 );
	check( 'it survives the moves of the shell - a tab, the rail, the select, an arrow key, the history - as the one chip that was drawn, and the record is the same', chipsOn( lockoutTab ).length === 1 && chipsOn( lockoutTab )[0] === chip
		&& chip.textContent === '2' && shell.Nino.adminUi.tabBadge('lockout') === '2' );

	shell.Nino.adminUi.tabBadge( 'lockout', '' );
	check( 'and goes when it is asked to', chipsOn( lockoutTab ).length === 0 && shell.Nino.adminUi.tabBadge('lockout') === '' );

	shell.document.createElement = realCreate;
	// Where the checks below start: the pane on its first tab
	rolesTab.listeners.click();
}

/*	A step through the history that is refused. Back from a form that holds input
	nobody saved is a hashchange; the panel asks, and on Cancel the screen stays
	where it is. What the old shell did then was write the form's address over the
	entry the browser had just stepped to - the history named the form twice and
	lost the list. Every entry the shell writes now carries its number, so the
	direction of a step is known, and a refused one is taken back with history.go()	*/
{
	const router = shell.Nino.admin.router;
	const savedDashboard = shell.Nino.admin.dashboard;
	const realGuard = shell.Nino.admin.dirty.guard;
	const questions = [];
	let level = '';
	let saving = false;
	// A drill-down panel as the real ones are: it follows the address, asks before it leaves a form
	shell.Nino.admin.dashboard = { showCurrent : function() {
		const hash = router.current();
		const wanted = hash.panel === 'dashboard' ? ( hash.parts[0] ?? '' ) : level;
		const own = function() { router.set( 'dashboard', level === '' ? [] : [ level ] ) };
		if( wanted === level ) {
			own();
			return;
		}
		if( saving === true ) {
			router.refuse();
			own();
			return;
		}
		router.leave( [ 'dashboard' ], level !== '', function() { level = wanted; own() }, own );
	} };

	// The move to it is the rail's, and nothing is unsaved: it goes at once. Every question from here on is recorded and answered by hand
	railLinks.dashboard.listeners.click( { preventDefault : function() {} } );
	shell.Nino.admin.dirty.guard = function( names, proceed, cancel ) { questions.push( { names : names, proceed : proceed, cancel : cancel } ) };
	level = 'form';
	router.go( 'dashboard', [ 'form' ] );
	router.set( 'dashboard', [ 'form' ] );
	const walked = browser.entries.slice();
	const formEntry = browser.at;
	check( 'every entry the shell wrote carries its number, the one the page was loaded on included', browser.states.every( function( state ) { return state !== null && Number.isInteger( state.nino ) } )
		&& browser.states.map( function( state ) { return state.nino } ).join() === browser.states.map( function( state, at ) { return at } ).join() );

	browser.step( -1 );
	check( 'Back from a form asks, and has written nothing yet', questions.length === 1 && browser.entries.join() === walked.join() && browser.traversals.length === 0 && shell.location.hash === '#dashboard' );
	questions[0].cancel();
	check( 'Cancel takes the step back with history.go() - one entry forward - and does not write the form\'s address over the entry the browser is on', browser.traversals.join() === '1' && browser.entries.join() === walked.join() && shell.location.hash === '#dashboard' );
	check( '...the panel showing its level again writes nothing while the traversal is on its way', router._undoing !== null );
	browser.arrive();
	check( 'the traversal arrives on the form\'s entry, the list entry before it is as it was, and the address is the form\'s', browser.at === formEntry && browser.entries.join() === walked.join() && shell.location.hash === '#dashboard/form' );
	check( '...and the hashchange it caused is not followed: no second question, nothing asked of the panel', questions.length === 1 && router._undoing === null && router._index === formEntry && router._settled === formEntry );

	browser.step( -1 );
	questions[1].proceed();
	check( 'Back answered with Discard or Save goes on: no traversal, no entry written, the panel is on the earlier level', questions.length === 2 && browser.traversals.length === 0 && level === '' && browser.entries.join() === walked.join() && browser.at === formEntry - 1 );

	// Several entries at once - the browser's history menu
	router.go( 'dashboard', [ 'second' ] );
	level = 'second';
	router.set( 'dashboard', [ 'second' ] );
	router.go( 'dashboard', [ 'third' ] );
	level = 'third';
	router.set( 'dashboard', [ 'third' ] );
	const manyEntries = browser.entries.slice();
	const third = browser.at;
	questions.length = 0;
	browser.step( -2 );
	questions[0].cancel();
	check( 'a step of two entries is taken back by two', questions.length === 1 && browser.traversals.join() === '2' );
	browser.arrive();
	check( '...and lands where the screen is', browser.at === third && browser.entries.join() === manyEntries.join() && shell.location.hash === '#dashboard/third' && questions.length === 1 );

	// A save is running: the panel stays where it is and says so
	saving = true;
	questions.length = 0;
	browser.step( -1 );
	check( 'a panel that will not follow because a save runs refuses the step through router.refuse(): taken back, no question', questions.length === 0 && browser.traversals.join() === '1' && shell.location.hash === '#dashboard/second' && browser.entries.join() === manyEntries.join() );
	browser.arrive();
	check( '...and the entry is the one the screen shows', browser.at === third && shell.location.hash === '#dashboard/third' && browser.entries.join() === manyEntries.join() );
	saving = false;

	// A traversal that never arrives does not keep the address shut for good
	browser.step( -1 );
	questions[0].cancel();
	const timer = browser.timers[browser.timers.length - 1];
	check( 'a traversal that is asked for has a limit', timer !== undefined && timer.ms === 1500 && router._undoing !== null );
	browser.traversals.length = 0;
	timer.fn();
	check( '...after which the address is written again', router._undoing === null );
	browser.step( 1 );
	router._settled = router._index = third;

	// An entry the browser made itself - an address typed - is numbered when it is met. The one before the stamping cannot be measured: that refusal keeps the old way
	browser.entries.splice( browser.at + 1 );
	browser.states.splice( browser.at + 1 );
	browser.entries.push( '#dashboard/typed' );
	browser.states.push( null );
	browser.at++;
	shell.location.hash = '#dashboard/typed';
	questions.length = 0;
	windowListeners.hashchange();
	check( 'an address typed by hand becomes an entry that is numbered, and is followed like any other', browser.states[browser.at] !== null && browser.states[browser.at].nino === third + 1 && questions.length === 1 );
	questions[0].cancel();
	check( 'refusing it is a step that is not measured - it came as a new entry, not as a move along the old ones - so the old way stands: the form\'s address over it', browser.traversals.length === 0 && browser.entries[browser.at] === '#dashboard/third' );

	// A shell without the numbers (an entry written by an older workbench) is followed without them
	const stamped = browser.states[browser.at];
	browser.states[browser.at] = null;
	router._settled = null;
	questions.length = 0;
	level = 'third';
	windowListeners.hashchange();
	check( 'where the step cannot be measured nothing is taken back', browser.traversals.length === 0 );
	browser.states[browser.at] = stamped;

	shell.Nino.admin.dirty.guard = realGuard;
	shell.Nino.admin.dashboard = savedDashboard;
}

/*	The rail asks as the back link does. A move to another panel leaves the one on screen, and what
	that holds unsaved - in the panel or in a tab of it - is asked about first, in the shell's own
	question: Save, Discard or Cancel. The registry is the real one, the dialog is recorded and
	answered by hand	*/
{
	const registry = shell.Nino.admin.dirty;
	const realChoice = shell.Nino.adminUi.choiceDialog;
	const realContent = shell.Nino.content;
	const asked = [];
	const happened = [];
	const unsaved = { users : false, roles : false };
	let saves = true;
	shell.Nino.content = { getText : function( key ) { return key } };
	shell.Nino.adminUi.choiceDialog = function( options ) { asked.push( options ); return true };
	[ 'users', 'roles' ].forEach( function( name ) {
		registry.register( name, { isDirty : function() { return unsaved[name] }, save : function( done ) { happened.push( 'save '+ name ); unsaved[name] = saves === false; done( saves ) }, discard : function() { happened.push( 'discard '+ name ); unsaved[name] = false } } );
	} );

	const click = function( link ) { link.listeners.click( { preventDefault : function() {} } ) };
	const current = function() { return railLinks.dashboard.getAttribute('aria-current') === 'page' ? 'dashboard' : ( railLinks.users.getAttribute('aria-current') === 'page' ? 'users' : '' ) };

	click( railLinks.users );
	click( railLinks.dashboard );
	check( 'a move through the rail with nothing unsaved goes at once, and asks nothing', current() === 'dashboard' && asked.length === 0 );
	click( railLinks.users );

	unsaved.users = true;
	click( railLinks.dashboard );
	check( 'a move through the rail with unsaved input asks - the question the back link asks, with the same three answers, about the panel on screen and the tabs of it', asked.length === 1
		&& asked[0].title === '/_admin/common/msg/dirty' && asked[0].message === '/_admin/common/confirm/unsaved' && asked[0].choices.map( function( choice ) { return choice.value } ).join() === 'save,discard,cancel' );
	check( '...and it has not moved: the panel stays, the link stays the current one, the form has its input', current() === 'users' && unsaved.users === true && happened.length === 0 );
	asked[0].onChoose('cancel');
	check( 'Cancel keeps the panel and the rail as it was', current() === 'users' && navSelect.value === 'users' && unsaved.users === true && happened.length === 0 && shell.location.hash === '#roles' );

	click( railLinks.dashboard );
	asked[1].onChoose('discard');
	check( 'Discard lets the entries forget what was typed and goes on to the panel asked for', happened.join() === 'discard users' && unsaved.users === false && current() === 'dashboard' );

	click( railLinks.users );
	unsaved.users = true;
	happened.length = 0;
	click( railLinks.dashboard );
	asked[2].onChoose('save');
	check( 'Save saves, then goes on', happened.join() === 'save users' && unsaved.users === false && current() === 'dashboard' );

	click( railLinks.users );
	unsaved.users = true;
	saves = false;
	happened.length = 0;
	click( railLinks.dashboard );
	asked[3].onChoose('save');
	check( 'a Save that failed does not go on: the panel is where the failure is, with its input', happened.join() === 'save users' && current() === 'users' && unsaved.users === true );
	saves = true;
	unsaved.users = false;

	// A tab of the pane counts as the pane
	unsaved.roles = true;
	click( railLinks.dashboard );
	check( 'input in a tab of the panel on screen is asked about too', asked.length === 5 && current() === 'users' );
	asked[4].onChoose('discard');
	check( '...and discarded with it', happened.join() === 'save users,discard roles' && current() === 'dashboard' && unsaved.roles === false );
	click( railLinks.users );

	// The phone's select is the rail as a control, which has to be put back
	unsaved.users = true;
	navSelect.value = 'dashboard';
	navSelect.listeners.change();
	check( 'the select of the phone asks the same question', asked.length === 6 && current() === 'users' );
	asked[5].onChoose('cancel');
	check( '...and on Cancel the select shows the panel on screen again, not the one that was asked for', navSelect.value === 'users' && current() === 'users' );
	navSelect.value = 'dashboard';
	navSelect.listeners.change();
	asked[6].onChoose('discard');
	check( '...and on an answer it goes on', current() === 'dashboard' && navSelect.value === 'dashboard' );
	click( railLinks.users );

	// A move inside the pane leaves nothing: from one tab to another
	unsaved.users = true;
	const before = asked.length;
	lockoutTab.listeners.click();
	rolesTab.listeners.click();
	check( 'a tab of the same pane is no exit: nothing is asked, nothing is lost', asked.length === before && current() === 'users' && unsaved.users === true );

	// A step through the browser's history to another panel is an exit as well; Cancel takes it back
	unsaved.users = false;
	click( railLinks.dashboard );
	click( railLinks.users );
	unsaved.users = true;
	const entriesBefore = browser.entries.slice();
	const hashBefore = shell.location.hash;
	browser.step( -1 );
	check( 'Back to the panel before asks too', asked.length === before + 1 && current() === 'users' );
	asked[before].onChoose('cancel');
	check( 'Cancel takes the step back with the history, and the address is the screen\'s again', browser.traversals.join() === '1' );
	browser.arrive();
	check( '...the entries are as they were', shell.location.hash === hashBefore && browser.entries.join() === entriesBefore.join() && current() === 'users' );

	// An address that was typed is a hashchange with no step of ours behind it, and there is nothing to take back with the history.
	// The pane on screen writes no address of its own (it has no script here): what the bar shows after Cancel is written by the shell,
	// or a reload and Back would act on the panel that was refused
	shell.location.hash = '#dashboard';
	windowListeners.hashchange();
	check( 'an address typed for another panel asks too, and has not moved the panel', asked.length === before + 2 && current() === 'users' && browser.traversals.length === 0 );
	asked[before + 1].onChoose('cancel');
	check( 'Cancel on it writes the panel on screen back into the address, where the panel writes none of its own', shell.location.hash === '#roles' && browser.entries[browser.at] === '#roles' );
	check( '...with the rail and the select on it, and the history left alone', current() === 'users' && navSelect.value === 'users' && browser.traversals.length === 0 && unsaved.users === true );

	unsaved.users = false;
	registry._entries.users.isDirty = function() { return false };
	registry._entries.roles.isDirty = function() { return false };
	shell.Nino.adminUi.choiceDialog = realChoice;
	shell.Nino.content = realContent;
}

/*	Discard on a move through the rail leaves the form. The rail leaves a pane standing in memory, and the form would come back with the
	input in it, counted as saved. A watched form has its fields put back, and a form that has a back link is left through it, the way
	the back link of the person leaves it - once the panel is left, and not on Cancel or Save. Real entries over stand-in forms; the
	dialog is recorded and answered by hand	*/
{
	const registry = shell.Nino.admin.dirty;
	const realChoice = shell.Nino.adminUi.choiceDialog;
	const realContent = shell.Nino.content;
	const realQuery = usersPane.querySelectorAll;
	const realRolesQuery = rolesPane.querySelectorAll;
	const asked = [];
	const presses = [];
	const saved = [];
	shell.Nino.content = { getText : function( key ) { return key } };
	shell.Nino.adminUi.choiceDialog = function( options ) { asked.push( options ); return true };
	shell.Event = function( type, init ) { this.type = type; this.bubbles = init !== undefined && init.bubbles === true };

	const click = function( link ) { link.listeners.click( { preventDefault : function() {} } ) };
	const current = function() { return railLinks.dashboard.getAttribute('aria-current') === 'page' ? 'dashboard' : ( railLinks.users.getAttribute('aria-current') === 'page' ? 'users' : '' ) };
	const backLink = function( parent ) {
		const link = element('a');
		link.className = 'nino-admin-back-link';
		link.parentNode = parent;
		link.click = function() { presses.push( ( parent === usersPane ? 'users' : ( parent === rolesPane ? 'roles' : 'hidden' ) )+ ' at '+ current()+ ( registry._bypass === link ? ' past the question' : '' ) ) };
		return link;
	};

	// The pane of the users: a form of plain fields with its back link, and the tab of it with a form of its own
	const input = element('input');
	input.type = 'text';
	input.value = 'stored';
	const box = element('input');
	box.type = 'checkbox';
	const form = element('div');
	form.appendChild( input );
	form.appendChild( box );
	const tabInput = element('input');
	tabInput.type = 'text';
	tabInput.value = 'role';
	const tabForm = element('div');
	tabForm.appendChild( tabInput );

	registry.watchForm( 'users', function() { return form }, function( done ) { saved.push( input.value ); registry.snapshot('users'); done( true ) } );
	registry.watchForm( 'roles', function() { return tabForm }, function( done ) { saved.push( tabInput.value ); registry.snapshot('roles'); done( true ) } );
	registry.snapshot('users');
	registry.snapshot('roles');

	const own = backLink( usersPane );
	const hidden = node( '', {} );
	hidden.classList.add('admin-hidden');
	hidden.parent = usersPane;
	const inHidden = backLink( hidden );
	const inTab = backLink( rolesPane );
	let links = [ inHidden, inTab, own ];
	usersPane.querySelectorAll = function( selector ) { return selector === 'a.nino-admin-back-link' ? links : realQuery.call( usersPane, selector ) };
	rolesPane.querySelectorAll = function( selector ) { return selector === 'a.nino-admin-back-link' ? [ inTab ] : [] };

	click( railLinks.users );
	input.value = 'typed';
	box.checked = true;
	click( railLinks.dashboard );
	check( 'a move through the rail with a watched form holding input asks, and has put nothing back and pressed nothing', asked.length === 1 && input.value === 'typed' && presses.length === 0 && current() === 'users' );
	asked[0].onChoose('discard');
	check( 'Discard puts the fields of the form back to what it held when it was drawn or saved, and it is clean', input.value === 'stored' && box.checked === false && registry.isDirty( [ 'users' ] ) === false );
	check( '...and presses the back link of the level that is on screen in the pane, once, after the panel was left and past the question the shell asks of a back link: not the link of a hidden level, not the link of a tab', current() === 'dashboard' && presses.join() === 'users at dashboard past the question' && registry._bypass === null );
	registry._entries.users.save( function() {} );
	check( '...and a Save from there sends what is stored, not what was discarded', saved.join() === 'stored' );
	saved.length = 0;

	presses.length = 0;
	click( railLinks.users );
	input.value = 'typed';
	click( railLinks.dashboard );
	asked[1].onChoose('cancel');
	check( 'Cancel presses nothing and puts nothing back', presses.length === 0 && input.value === 'typed' && current() === 'users' && registry.isDirty( [ 'users' ] ) === true );
	click( railLinks.dashboard );
	asked[2].onChoose('save');
	check( 'Save goes on with the form as it was saved: no link is pressed, nothing is put back', saved.join() === 'typed' && presses.length === 0 && input.value === 'typed' && current() === 'dashboard' );

	// The form that is the whole screen of its panel has no back link: the fields are put back, and that is all there is
	links = [ inHidden, inTab ];
	click( railLinks.users );
	input.value = 'typed again';
	click( railLinks.dashboard );
	asked[3].onChoose('discard');
	check( 'a pane with no back link on screen is left with its fields put back, to what the last save stored', presses.length === 0 && input.value === 'typed' && registry.isDirty( [ 'users' ] ) === false && current() === 'dashboard' );

	// Input in a tab of the pane is the tab's: its own link is pressed
	links = [ own ];
	click( railLinks.users );
	tabInput.value = 'role typed';
	click( railLinks.dashboard );
	check( 'input in a tab of the pane is asked about', asked.length === 5 && current() === 'users' );
	asked[4].onChoose('discard');
	check( '...and discarded in the tab: the back link of the tab is pressed, the one of the pane is not', tabInput.value === 'role' && presses.join() === 'roles at dashboard past the question' && current() === 'dashboard' );

	delete registry._entries.users;
	delete registry._forms.users;
	delete registry._baseline.users;
	delete registry._entries.roles;
	delete registry._forms.roles;
	delete registry._baseline.roles;
	registry.refresh();
	usersPane.querySelectorAll = realQuery;
	rolesPane.querySelectorAll = realRolesQuery;
	delete shell.Event;
	shell.Nino.adminUi.choiceDialog = realChoice;
	shell.Nino.content = realContent;
}

/*	A pane with a screen of its own and tabs besides - Elements, Text, Users, Language - draws that screen as the first of its tabs, named
	like the panel (see Panels::panesHtml()), so the name of the panel is also the name of a tab of its pane. The rail asks about the panel
	and its tabs, and the panel is one of them once: its entry is saved once - a second call, with nothing left to save, would report
	failure as the Design panel's does, and the move would be refused - and its back link is pressed once. A shell of its own with such a
	pane, the real registry, the dialog recorded and answered by hand	*/
{
	const homeLink = navLink( 'dashboard', 'Dashboard' );
	const sitesLink = navLink( 'sites', 'Sites' );
	const wrap = node( 'admin-nav-wrap', {} );
	wrap.children = [ homeLink, sitesLink ];
	wrap.setAttribute( 'aria-label', 'Navigation' );

	// The pane as Panels::panesHtml() draws it: the head with the strip, then the screen of the panel, then its tab
	const screenButton = node( 'admin-tabbutton-sites', { tab : 'sites' } );
	const archiveButton = node( 'admin-tabbutton-archive', { tab : 'archive' } );
	const tabStrip = node( '', {} );
	tabStrip.isStrip = true;
	tabStrip.classList.add('admin-panel-tabs');
	tabStrip.children = [ screenButton, archiveButton ];
	screenButton.parent = tabStrip;
	archiveButton.parent = tabStrip;
	const paneHead = node( '', {} );
	paneHead.classList.add('admin-panel-head');
	paneHead.children = [ tabStrip ];
	tabStrip.parent = paneHead;
	const screenPane = node( 'admin-tab-sites', { tab : 'sites' } );
	const archivePane = node( 'admin-tab-archive', { tab : 'archive' } );
	const sitesPane = node( 'admin-content-sites', { panel : 'sites', layout : 'page' } );
	sitesPane.children = [ paneHead, screenPane, archivePane ];
	[ paneHead, screenPane, archivePane ].forEach( function( child ) { child.parent = sitesPane } );
	const homePane = node( 'admin-content-dashboard', { panel : 'dashboard', layout : 'page' } );
	const nodes = { 'admin-page-wrap' : node( 'admin-page-wrap', {} ), 'admin-nav-wrap' : wrap, 'admin-user-logout' : node( 'admin-user-logout', {} ), 'admin-content-dashboard' : homePane, 'admin-content-sites' : sitesPane, 'admin-tab-sites' : screenPane, 'admin-tab-archive' : archivePane };

	const own = {
		console : console,
		location : { hash : '#sites', href : '' },
		history : { replaceState : function() {}, pushState : function() {}, state : null, go : function() {} },
		localStorage : { getItem : function() { return null }, setItem : function() {}, removeItem : function() {} },
		addEventListener : function() {},
		setTimeout : function() {},
		clearTimeout : function() {},
		document : {
			documentElement : null, body : null,
			getElementById : function( id ) { return nodes[id] ?? null },
			createElement : function( tag ) { return fakeElement( tag ) },
			addEventListener : function() {},
			querySelectorAll : function( selector ) {
				if( selector === '#admin-nav-wrap a[data-panel]' )
					return [ homeLink, sitesLink ];
				if( selector === '#admin-content-wrap > [data-panel]' )
					return [ homePane, sitesPane ];
				if( selector === '#admin-content-wrap > [data-panel] > .admin-panel-head > .admin-panel-tabs' )
					return [ tabStrip ];
				return [];
			},
		},
	};
	own.window = own;
	own.Nino = { events : { bindCallback : function() {} } };
	const ownContext = vm.createContext( own );
	load( '_admin/assets/Nino.admin.js', ownContext );
	load( '_admin/assets/script.js', ownContext );
	own.Nino.admin.onReady();

	const registry = own.Nino.admin.dirty;
	const asked = [];
	const happened = [];
	const presses = [];
	const unsaved = { sites : false, archive : false };
	let sitesSaves = 0;
	own.Nino.content = { getText : function( key ) { return key } };
	own.Nino.adminUi.choiceDialog = function( options ) { asked.push( options ); return true };
	registry.register( 'sites', { isDirty : function() { return unsaved.sites }, save : function( done ) { sitesSaves++; happened.push( 'save sites' ); unsaved.sites = false; done( sitesSaves === 1 ) }, discard : function() { happened.push( 'discard sites' ); unsaved.sites = false } } );
	registry.register( 'archive', { isDirty : function() { return unsaved.archive }, save : function( done ) { happened.push( 'save archive' ); unsaved.archive = false; done( true ) }, discard : function() { happened.push( 'discard archive' ); unsaved.archive = false } } );

	const click = function( link ) { link.listeners.click( { preventDefault : function() {} } ) };
	const current = function() { return homeLink.getAttribute('aria-current') === 'page' ? 'dashboard' : ( sitesLink.getAttribute('aria-current') === 'page' ? 'sites' : '' ) };
	const backLink = function( parent, name ) {
		const link = element('a');
		link.className = 'nino-admin-back-link';
		link.parentNode = parent;
		link.click = function() { presses.push( name+ ' at '+ current() ) };
		return link;
	};
	const sitesBack = backLink( screenPane, 'sites' );
	const archiveBack = backLink( archivePane, 'archive' );
	screenPane.querySelectorAll = function( selector ) { return selector === 'a.nino-admin-back-link' ? [ sitesBack ] : [] };
	archivePane.querySelectorAll = function( selector ) { return selector === 'a.nino-admin-back-link' ? [ archiveBack ] : [] };
	const leave = function() {
		happened.length = 0;
		presses.length = 0;
		click( sitesLink );
	};

	check( 'the pane is on screen, and the panel is the one the rail marks', current() === 'sites' );
	unsaved.sites = true;
	click( homeLink );
	check( 'a move through the rail from a pane whose first tab is named like the panel asks, and has not moved', asked.length === 1 && current() === 'sites' && happened.length === 0 );
	asked[0].onChoose('save');
	check( 'Save calls the entry of the panel once, and goes on', happened.join() === 'save sites' && sitesSaves === 1 && current() === 'dashboard' );

	leave();
	unsaved.sites = true;
	click( homeLink );
	asked[1].onChoose('discard');
	check( 'Discard lets the entry forget once and presses its back link once, after the panel was left', happened.join() === 'discard sites' && presses.join() === 'sites at dashboard' && current() === 'dashboard' );

	// The panel and a tab of it are two entries, each asked about and left once
	leave();
	unsaved.sites = true;
	unsaved.archive = true;
	click( homeLink );
	asked[2].onChoose('discard');
	check( 'the panel and a tab of it, both holding input, are discarded once each and left through a link each', happened.join() === 'discard sites,discard archive' && presses.join() === 'sites at dashboard,archive at dashboard' && current() === 'dashboard' );

	leave();
	sitesSaves = 0;
	unsaved.sites = true;
	unsaved.archive = true;
	click( homeLink );
	asked[3].onChoose('save');
	check( '...and saved once each, in the order of the pane, before the move', happened.join() === 'save sites,save archive' && sitesSaves === 1 && presses.length === 0 && current() === 'dashboard' );
}

// A folded rail: the headings are dividers, nothing is hidden, nobody tabs to them
railHeadings.system.listeners.click();
check( 'the heading folds the group again', railLinks.users.classList.contains('nino-admin-nav-collapsed') === true );
storage['nino-admin-rail'] = 'folded';
shell.Nino.admin.rail._apply();
check( 'a folded rail shows every link and takes its headings out of the tab order', railLinks.users.classList.contains('nino-admin-nav-collapsed') === false
	&& railHeadings.system.tabIndex === -1 && railHeadings.system.getAttribute('aria-expanded') === 'true' );
railHeadings.content.listeners.click();
check( '...and a click on one does nothing there', storage[GROUPS_KEY] === '["system"]' && railLinks.dashboard.classList.contains('nino-admin-nav-collapsed') === false );
delete storage['nino-admin-rail'];
shell.Nino.admin.rail._apply();
check( 'unfolded again, the folded group is folded again and the headings are reachable', railLinks.users.classList.contains('nino-admin-nav-collapsed') === true && railHeadings.system.tabIndex === 0 );
railHeadings.system.listeners.click();

// An account that sees one group has no headings: a plain select, nothing to fold
const lone = { children : [ navLink( 'dashboard', 'Dashboard' ), navLink( 'logs', 'Logs' ) ], classList : { add : function() {} }, getAttribute : function() { return 'Nav' }, insertBefore : function( el, ref ) { this.children.unshift( el ) }, firstChild : null };
const loneCtx = { console : console, localStorage : { getItem : function() { return null }, setItem : function() {}, removeItem : function() {} }, document : { documentElement : null, body : null, getElementById : function( id ) { return id === 'admin-nav-wrap' ? lone : null }, createElement : function( tag ) { return fakeElement( tag ) } } };
loneCtx.window = loneCtx;
loneCtx.Nino = { events : { bindCallback : function() {} } };
load( '_admin/assets/script.js', vm.createContext( loneCtx ) );
loneCtx.Nino.admin.navGroups.init( function() {} );
check( 'a rail without headings gets a select of plain options and nothing to fold', lone.children[0].children.length === 2 && lone.children[0].children.every( o => o.tagName === 'option' ) && loneCtx.Nino.admin.navGroups._groups.length === 0 );

/*	The head over every pane but the Dashboard, reached from inside the pane.
	A panel with tabs of its own - Features, a feature's editors - puts its
	strip beside the name through it, so the workbench has one row rather
	than a title and a strip per panel; drawn again, the strip replaces the
	one before it rather than stacking	*/
const ui = shell.Nino.adminUi;
check( 'there is no head to reach from outside a pane', ui.panelHead( node( 'loose', {} ) ) === null && ui.panelHead( null ) === null );
const reached = ui.panelHead( rolesTab );
check( 'from inside a pane the head is the row over it, with the name and the actions slot', reached !== null && reached.element === head && reached.title === title && reached.actions === actions );
{
	// The Dashboard keeps its "?" in a row of its own: that is no head, and a script asking for the head of its pane is told there is none
	const row = node( '', {} );
	row.classList.add('admin-panel-manualrow');
	const screenMount = node( 'dashboard-list', {} );
	dashboardPane.children = [ row, screenMount ];
	row.parent = dashboardPane;
	screenMount.parent = dashboardPane;
	check( 'the row a pane keeps its "?" in when it has no head is no head: panelHead() answers null for it, as it did before the "?" was there', ui.panelHead( screenMount ) === null );
	dashboardPane.children = [];
}

/*	A pane whose head holds the registry's strip - the Settings tab a feature's
	panel gets is a button of it - and a screen inside one of its tabs that
	draws a strip of its own (Redirects does): the registry's strip must stay,
	and the screen's goes to the top of its tab's pane, replaced on a redraw	*/
const mountInTab = node( 'roles-mount', {} );
rolesPane.children = [ mountInTab ];
mountInTab.parent = rolesPane;
rolesPane.parent = usersPane;
const tabStrip = node( 'tab-strip', {} );
const tabStripAgain = node( 'tab-strip-again', {} );
const inTab = ui.panelHead( mountInTab );
inTab.tabs( tabStrip );
check( 'a screen inside a tab of a pane with the registry strip gets its own strip at the top of that tab, and the registry strip is left alone', rolesPane.children[0] === tabStrip && tabStrip.classList.contains('admin-panel-tabs')
	&& rolesPane.children[1] === mountInTab && head.children.indexOf( strip ) === 1 && head.children.length === 3 && strip.children.length === 2 );
inTab.tabs( tabStripAgain );
check( '...and drawn again it replaces the one before it in the tab rather than stacking, the head still untouched', rolesPane.children.indexOf( tabStrip ) === -1 && rolesPane.children[0] === tabStripAgain && rolesPane.children.length === 2
	&& head.children.indexOf( strip ) === 1 && head.children.length === 3 );
tabStripAgain.remove();

/*	The same screen in a tab pane that stands alone - an account holding only
	the Settings tab's permission gets no registry strip: the screen's strip
	keeps its old place in the head, after the name	*/
head.children.splice( head.children.indexOf( strip ), 1 );
strip.parent = null;
const loneStrip = node( 'lone-strip', {} );
ui.panelHead( mountInTab ).tabs( loneStrip );
check( 'a screen inside a lone tab pane, with no registry strip in the head, keeps the old placement: its strip after the name, the pane untouched', head.children[1] === loneStrip
	&& loneStrip.classList.contains('admin-panel-tabs') && rolesPane.children.length === 1 && rolesPane.children[0] === mountInTab );
loneStrip.remove();
head.children.splice( 1, 0, strip );
strip.parent = head;

mountInTab.remove();
rolesPane.parent = null;

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
load( '_admin/assets/Nino.admin.js', dialogContext );
load( '_admin/assets/script.js', dialogContext );
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

/*	Unsaved input. A panel that keeps a form registers with the shell and says
	when it holds something nobody saved; the shell asks Save / Discard /
	Cancel before it throws that away. Driven through a context of its own: a
	registry needs listeners that record what is added to them, and the node
	above keeps one listener per event	*/
const dirtyLog = [];
const dirtyAsked = [];
const dirtyShown = [];
const windowEvents = [];
const wrapListeners = [];
const frames = [];
const timers = [];
const dirtyState = { keys : false, text : false, ok : true };

function append( el ) {
	el.appendChild = function( child ) { el.children.push( child ); child.parent = el; return child };
	return el;
}

const barHolder = { bar : append( node( '', {} ) ) };
barHolder.bar.classList.add('nino-admin-actionbar');
const keysPane = node( 'admin-tab-keys', { tab : 'keys' } );
const textPane = node( 'admin-content-text', { panel : 'text' } );
keysPane.parent = textPane;
keysPane.querySelector = function( selector ) { return selector === '.nino-admin-actionbar:not(.nino-admin-list-actions)' ? barHolder.bar : null };
textPane.querySelector = function() { return null };
const imagesPane = node( 'admin-content-images', { panel : 'images' } );
const wrapNode = node( 'admin-content-wrap', {} );
wrapNode.addEventListener = function( type, fn, capture ) { wrapListeners.push( { type : type, fn : fn, capture : capture === true } ) };

const dirtyNodes = { 'admin-content-wrap' : wrapNode, 'admin-tab-keys' : keysPane, 'admin-content-text' : textPane, 'admin-content-images' : imagesPane };
const dirtyCtx = {
	console : console,
	location : { hash : '', href : '' },
	history : { replaceState : function() {} },
	addEventListener : function( type, fn ) { windowEvents.push( [ 'add', type, fn ] ) },
	removeEventListener : function( type, fn ) { windowEvents.push( [ 'remove', type, fn ] ) },
	requestAnimationFrame : function( fn ) { frames.push( fn ) },
	setTimeout : function( fn, ms ) { timers.push( { fn : fn, ms : ms } ); return timers.length },
	clearTimeout : function( id ) { if( timers[id - 1] !== undefined ) timers[id - 1].cleared = true },
	document : {
		documentElement : null, body : null,
		getElementById : function( id ) { return dirtyNodes[id] ?? null },
		createElement : function( tag ) { const created = append( node( '', {} ) ); created.tagName = tag; return created },
		addEventListener : function() {},
	},
};
dirtyCtx.window = dirtyCtx;
dirtyCtx.Nino = { events : { bindCallback : function() {} }, content : { getText : function( key ) { return key } } };
const dirtyContext = vm.createContext( dirtyCtx );
load( '_admin/assets/Nino.admin.js', dirtyContext );
load( '_admin/assets/script.js', dirtyContext );

// The question itself is checked below; here it records what was asked and is answered by hand
dirtyCtx.Nino.adminUi.choiceDialog = function( options ) { dirtyAsked.push( options ); return true };
const dirty = dirtyCtx.Nino.admin.dirty;
const answer = function( value ) { dirtyAsked[dirtyAsked.length - 1].onChoose( value ) };

check( 'the shell registers no listener of its own at load - a script loaded without a dom (the first context above) did load', windowEvents.length === 0 && typeof sandbox.Nino.admin.dirty.register === 'function' );
dirty.init( function( name ) { dirtyShown.push( name ) } );
check( '...init() asks to be told when the page is shown again', windowEvents.length === 1 && windowEvents[0][1] === 'pageshow' && windowEvents[0][2] === dirty._stay );

const entryFor = function( name ) {
	return {
		isDirty : function() { return dirtyState[name] },
		save		: function( done ) { dirtyLog.push( 'save '+ name ); if( dirtyState.ok === true ) dirtyState[name] = false; done( dirtyState.ok ) },
		discard : function() { dirtyLog.push( 'discard '+ name ); dirtyState[name] = false },
	};
};
dirty.register( 'text', entryFor('text') );
dirty.register( 'keys', entryFor('keys') );

check( 'nothing is dirty until a panel says so', dirty.isDirty() === false && dirty.dirtyNames().length === 0 );
let proceeded = 0;
dirty.guard( null, function() { proceeded++ } );
check( 'a guard with nothing unsaved goes straight on and asks nothing', proceeded === 1 && dirtyAsked.length === 0 );

dirtyState.keys = true;
check( 'an entry that says so is dirty, by name or for all', JSON.stringify( dirty.dirtyNames() ) === '["keys"]' && dirty.isDirty() === true && dirty.isDirty( [ 'keys' ] ) === true );
check( '...and an entry outside the scope of a guard does not count', dirty.isDirty( [ 'text' ] ) === false && dirty.dirtyNames( [ 'text', 'nobody' ] ).length === 0 );

let cancelled = 0;
proceeded = 0;
dirty.guard( [ 'keys' ], function() { proceeded++ }, function() { cancelled++ } );
check( 'unsaved input is asked about: Save, Discard, Cancel, in the interface language',
	dirtyAsked.length === 1 && dirtyAsked[0].message === '/_admin/common/confirm/unsaved' && dirtyAsked[0].title === '/_admin/common/msg/dirty'
	&& dirtyAsked[0].choices.map( function( c ) { return c.value+ ':'+ c.kind+ ':'+ c.label } ).join() === 'save:primary:/_admin/common/label/save,discard:danger:/_admin/common/label/discard,cancel:secondary:/_admin/common/label/cancel' );
dirty.guard( [ 'keys' ], function() { proceeded += 10 } );
check( 'while a question stands a second call is ignored - it is the same click arriving twice', dirtyAsked.length === 1 && proceeded === 0 );
answer('cancel');
check( 'Cancel does nothing but tell the exit it was cancelled', proceeded === 0 && cancelled === 1 && dirty._asking === false );

dirty.guard( [ 'keys' ], function() { proceeded++ }, function() { cancelled++ } );
answer('save');
check( 'Save runs the entry and goes on once it reports ok', JSON.stringify( dirtyLog ) === '["save keys"]' && proceeded === 1 && cancelled === 1 );

dirtyState.keys = true;
dirtyState.ok = false;
dirtyLog.length = 0;
proceeded = 0;
dirty.guard( [ 'keys' ], function() { proceeded++ }, function() { cancelled++ } );
answer('save');
check( 'a Save that fails stops: the form that failed is brought on screen with its errors, and the exit is cancelled',
	proceeded === 0 && cancelled === 2 && dirtyShown.join() === 'keys' && dirty._asking === false );

dirtyState.ok = true;
dirtyState.text = true;
dirtyState.keys = true;
dirtyLog.length = 0;
dirty.guard( null, function() { proceeded++ } );
answer('save');
check( 'Save runs every dirty entry in order', JSON.stringify( dirtyLog ) === '["save text","save keys"]' && proceeded === 1 );

// a Save that has a question of its own: Element Types drops the element form next door
let nestedSaved = 0;
dirty.register( 'nester', {
	isDirty : function() { return true },
	save		: function( done ) { dirty.guard( [ 'text' ], function() { nestedSaved++; done( true ) }, function() { done( false ) } ) },
	discard : function() {},
} );
dirtyState.text = true;
dirtyState.keys = false;
dirtyAsked.length = 0;
proceeded = 0;
dirty.guard( [ 'nester' ], function() { proceeded++ } );
answer('save');
check( 'a Save may ask a question of its own about something else that is unsaved', dirtyAsked.length === 2 && dirty._saving === 1 && proceeded === 0 );
answer('discard');
check( '...and the exit goes on when both are answered, with nothing left asking', nestedSaved === 1 && proceeded === 1 && dirty._asking === false && dirty._saving === 0 );
dirtyState.text = true;
dirtyAsked.length = 0;
dirty.guard( [ 'nester' ], function() { proceeded++ } );
answer('save');
answer('cancel');
check( 'a Cancel of the question inside a Save fails that Save, and the exit with it', proceeded === 1 && dirty._asking === false && dirty._saving === 0 );
delete dirty._entries.nester;
dirtyState.text = false;
proceeded = 1;

// two Saves that fail, one inside the other: only the innermost form is brought on screen
dirty.register( 'inner', entryFor('inner') );
dirty.register( 'outer', {
	isDirty : function() { return true },
	save		: function( done ) { dirty.guard( [ 'inner' ], function() { done( true ) }, function() { done( false ) } ) },
	discard : function() {},
} );
dirtyState.inner = true;
dirtyState.ok = false;
dirtyShown.length = 0;
dirtyAsked.length = 0;
cancelled = 0;
dirty.guard( [ 'outer' ], function() { proceeded += 10 }, function() { cancelled++ } );
answer('save');
answer('save');
check( 'of two failed Saves, one inside the other, only the innermost is brought on screen - the refusal and its marks are in that form',
	dirtyShown.join() === 'inner' && cancelled === 1 && proceeded === 1 && dirty._saving === 0 && dirty._asking === false && dirty._failShown === false );
dirtyState.ok = true;
dirtyState.inner = false;
delete dirty._entries.inner;
delete dirty._entries.outer;

// while a Save started from the question runs, a guard that is not the save asking is a stray click
let slowDone = null;
dirty.register( 'slow', { isDirty : function() { return true }, save : function( done ) { slowDone = done }, discard : function() {} } );
dirtyState.text = true;
dirtyAsked.length = 0;
dirty.guard( [ 'slow' ], function() { proceeded += 10 } );
answer('save');
let strayed = 0;
dirty.guard( [ 'text' ], function() { strayed++ }, function() { strayed += 10 } );
check( '...a back link, the logout or the language picker meanwhile asks nothing - and tells the select to go back', dirtyAsked.length === 1 && strayed === 10 && dirty._saving === 1 );
slowDone( true );
check( '...and the Save then ends as it would have', proceeded === 11 && dirty._saving === 0 && dirty._asking === false );
dirtyState.text = false;
delete dirty._entries.slow;
proceeded = 1;

dirtyState.text = true;
dirtyState.keys = true;
dirtyLog.length = 0;
dirty.guard( null, function() { proceeded++ }, undefined, true );
answer('discard');
check( 'Discard lets every entry forget and goes on', JSON.stringify( dirtyLog ) === '["discard text","discard keys"]' && proceeded === 2 );
check( '...an exit that leaves the page says so, so the browser does not ask again', dirty._leaving === true );
check( '...but only for a while: if the page is still here then, the browser\'s question is back',
	timers.length === 1 && timers[0].ms === 10000 && ( timers[0].fn(), dirty._leaving === false ) );
dirty._leaving = true;
dirty._stay();
check( '...and when the page is shown again', dirty._leaving === false );
dirty._leaving = false;

// the browser's own question
dirtyState.keys = true;
const unload = function() { return { prevented : 0, returnValue : undefined, preventDefault : function() { this.prevented++ } } };
let ev = unload();
dirty._beforeUnload( ev );
check( 'closing or reloading the page with unsaved input is asked about by the browser', ev.prevented === 1 && ev.returnValue === '' );
dirty._leaving = true;
ev = unload();
dirty._beforeUnload( ev );
check( '...but not once the page has decided to leave', ev.prevented === 0 );
dirty._leaving = false;
dirtyState.keys = false;
ev = unload();
dirty._beforeUnload( ev );
check( '...nor when nothing is unsaved', ev.prevented === 0 );

windowEvents.length = 0;
dirtyState.keys = true;
dirty.refresh();
check( 'the browser\'s question is installed only while something is unsaved, so a clean page keeps the browser\'s fast back and forward', windowEvents.length === 1 && windowEvents[0][0] === 'add' && windowEvents[0][1] === 'beforeunload' );
dirty.refresh();
check( '...and not twice', windowEvents.length === 1 );
dirtyState.keys = false;
dirty.refresh();
check( '...and taken off again when the page is clean', windowEvents.length === 2 && windowEvents[1][0] === 'remove' && windowEvents[1][1] === 'beforeunload' );

// the marker
const markerOf = function( bar ) { return bar.children.find( function( c ) { return c.className === 'nino-admin-actionbar-dirty' } ) };
check( 'a clean form shows no marker', markerOf( barHolder.bar ) === undefined || markerOf( barHolder.bar ).hidden === true );
dirtyState.keys = true;
dirty.refresh();
const mark = markerOf( barHolder.bar );
check( 'an unsaved form carries the marker in its action bar: a span, not a p and not a status role - the phone rule that hides the status line leaves it',
	mark !== undefined && mark.tagName === 'span' && mark.textContent === '/_admin/common/msg/dirty' && mark.hidden === false && mark.getAttribute('role') === null );
dirtyState.keys = false;
dirty.refresh();
check( '...hidden again once the form is saved', mark.hidden === true && barHolder.bar.children.length === 1 );
const oldBar = barHolder.bar;
barHolder.bar = append( node( '', {} ) );
barHolder.bar.classList.add('nino-admin-actionbar');
dirtyState.keys = true;
dirty.refresh();
const again = markerOf( barHolder.bar );
check( 'a bar drawn again gets a new marker - the old one went with the old bar', again !== undefined && again !== mark && markerOf( oldBar ) === mark );

const hasStatus = append( node( '', {} ) );
hasStatus.classList.add('nino-admin-actionbar');
const statusLine = node( '', {} );
statusLine.classList.add('nino-admin-status');
hasStatus.querySelector = function( selector ) { return selector === '.nino-admin-status' ? statusLine : null };
hasStatus.children = [ statusLine ];
statusLine.parent = hasStatus;
barHolder.bar = hasStatus;
dirty.refresh();
check( 'a bar with a status line of its own gets the marker as well - the style sheet hides it there, and shows it where the phone rule hides the line', markerOf( hasStatus ) !== undefined && markerOf( hasStatus ).hidden === false );
barHolder.bar = oldBar;
dirtyState.keys = false;
dirty.refresh();

// input, change and click all re-read the state - once per frame
const listenerFor = function( type, capture ) { return wrapListeners.find( function( l ) { return l.type === type && l.capture === capture } ) };
check( 'the wrap listens for input, change and click, and for the back link in the capture phase',
	listenerFor( 'input', false ) !== undefined && listenerFor( 'change', false ) !== undefined && listenerFor( 'click', false ) !== undefined && listenerFor( 'click', true ) !== undefined );
dirtyState.keys = true;
frames.length = 0;
[ 'input', 'change', 'click' ].forEach( function( type ) { listenerFor( type, false ).fn() } );
check( 'three events in one frame schedule one refresh', frames.length === 1 && markerOf( barHolder.bar ).hidden === true );
frames[0]();
check( '...which shows the marker', barHolder.bar.children.some( function( c ) { return c.className === 'nino-admin-actionbar-dirty' && c.hidden === false } ) );

// the back link
let clicks = 0;
const backLink = node( '', {} );
backLink.parent = keysPane;
backLink.closest = function( selector ) { return selector === 'a.nino-admin-back-link' ? backLink : null };
backLink.click = function() { clicks++; listenerFor( 'click', true ).fn( { target : backLink } ) };
const clickEvent = function( target ) { return { target : target, prevented : 0, stopped : 0, preventDefault : function() { this.prevented++ }, stopImmediatePropagation : function() { this.stopped++ } } };
dirtyAsked.length = 0;
ev = clickEvent( backLink );
listenerFor( 'click', true ).fn( ev );
check( 'a back link out of a form holding unsaved input is held back and asked about', ev.prevented === 1 && ev.stopped === 1 && dirtyAsked.length === 1 );
answer('discard');
check( '...and clicked again, past the question, once the person has answered', clicks === 1 && dirty._bypass === null );

const unregistered = node( '', {} );
unregistered.parent = imagesPane;
unregistered.closest = function() { return unregistered };
ev = clickEvent( unregistered );
listenerFor( 'click', true ).fn( ev );
check( 'a back link of a panel that is not registered is left alone', ev.prevented === 0 && ev.stopped === 0 );

dirtyState.keys = false;
dirtyState.text = true;
ev = clickEvent( backLink );
listenerFor( 'click', true ).fn( ev );
check( 'the owner of a link is the nearest tab, then the panel: the Keys tab is clean even while its panel is registered dirty', ev.prevented === 0 );
dirtyState.text = false;
ev = clickEvent( { closest : function() { return null } } );
listenerFor( 'click', true ).fn( ev );
check( 'a click that is not on a back link is none of its business', ev.prevented === 0 );

// a form that is only plain fields. A field keeps the events it was told of, the way a listener on it would hear them
dirtyCtx.Event = function( type, init ) { this.type = type; this.bubbles = init !== undefined && init.bubbles === true };
const field = function( type, value, extra ) {
	return Object.assign( { type : type, value : String( value ), checked : false, autocomplete : '', dataset : {}, events : [], dispatchEvent : function( event ) { this.events.push( event.type+ ( event.bubbles === true ? '+bubbles' : '' ) ) } }, extra || {} );
};
const plain = {
	text		: field( 'text', 'a' ),
	check		: field( 'checkbox', 'on' ),
	file		: field( 'file', '' ),
	search	: field( 'search', '' ),
	secret	: field( 'password', '', { autocomplete : 'current-password' } ),
	typed		: field( 'text', 'x', { dataset : { dirty : 'ignore' } } ),
};
const editable = { innerHTML : '<b>x</b>' };
const form = node( 'routes-form', {} );
form.querySelectorAll = function( selector ) { return selector === 'input, textarea, select' ? Object.values( plain ) : ( selector === '[contenteditable]' ? [ editable ] : [] ) };
form.querySelector = function( selector ) { return selector === 'input, textarea, select, [contenteditable]' ? Object.values( plain )[0] : null };
let formSaved = 0;
dirty.watchForm( 'routes', function() { return form }, function( done ) { formSaved++; done( true ) } );
let savedWith = null;
dirty._entries.routes.save( function( ok ) { savedWith = ok } );
check( 'the shell\'s Save of a watched form is the panel\'s own save, and hears how it ended', formSaved === 1 && savedWith === true );
check( 'a watched form is clean before it was ever drawn', dirty.isDirty( [ 'routes' ] ) === false );
dirty.snapshot('routes');
check( '...and right after it was', dirty.isDirty( [ 'routes' ] ) === false );
plain.text.value = 'b';
check( 'a changed field makes it dirty', dirty.isDirty( [ 'routes' ] ) === true );
plain.text.value = 'a';
check( '...and changing it back takes that away - it is compared, not counted', dirty.isDirty( [ 'routes' ] ) === false );
plain.check.checked = true;
check( 'a checkbox counts by its state', dirty.isDirty( [ 'routes' ] ) === true );
plain.check.checked = false;
editable.innerHTML = '<b>y</b>';
check( 'so does the content of a rich-text field', dirty.isDirty( [ 'routes' ] ) === true );
editable.innerHTML = '<b>x</b>';
plain.file.value = 'C:\\fakepath\\a.jpg';
plain.search.value = 'filter';
plain.secret.value = 'filled in by a password manager';
plain.typed.value = 'DELETE';
check( 'a file input, a search box, a password the browser may fill in and a field that says it is no edit are not input', dirty.isDirty( [ 'routes' ] ) === false );
plain.text.value = 'c';
form.classList.add('admin-hidden');
check( 'a form that is not on screen holds nothing the person could lose track of', dirty.isDirty( [ 'routes' ] ) === false );
form.classList.remove('admin-hidden');
plain.check.checked = true;
editable.innerHTML = '<b>y</b>';
Object.values( plain ).forEach( function( el ) { el.events.length = 0 } );
dirty._entries.routes.discard();
check( 'discarding puts the fields back to what the form held when it was drawn - a text, a checkbox, the content of a rich-text field', plain.text.value === 'a' && plain.check.checked === false && editable.innerHTML === '<b>x</b>' );
check( '...and the form is clean', dirty.isDirty( [ 'routes' ] ) === false );
check( '...a field that was put back tells what hangs on it - a reading aid, a status line - the way typing it back would: an input and a change that bubble', plain.text.events.join() === 'input+bubbles,change+bubbles' && plain.check.events.join() === 'input+bubbles,change+bubbles' );
check( '...and one that was not touched is not told of anything', plain.file.events.length === 0 && plain.search.events.length === 0 && plain.secret.events.length === 0 );
check( '...a field that is no input stays as it is: a file, a search box, the password a browser filled in, the confirmation that says it is no edit', plain.file.value === 'C:\\fakepath\\a.jpg' && plain.search.value === 'filter'
	&& plain.secret.value === 'filled in by a password manager' && plain.typed.value === 'DELETE' );
dirty._entries.routes.discard();
check( '...and discarding a form that holds nothing new changes and tells nothing', plain.text.events.length === 2 && plain.text.value === 'a' && dirty.isDirty( [ 'routes' ] ) === false );

// A form that has other fields than it was drawn with - a row added since - cannot be put back field by field: it is taken for saved as it stands, the way a discard always did, and its exit draws it again
{
	plain.added = field( 'text', 'a new row' );
	plain.text.value = 'z';
	plain.added.value = 'typed into the row';
	check( 'a row added to a form is input', dirty.isDirty( [ 'routes' ] ) === true );
	dirty._entries.routes.discard();
	check( 'a form that has other fields than it was drawn with is not put back: its fields are as they were typed, and it counts as saved', plain.text.value === 'z' && plain.added.value === 'typed into the row' && dirty.isDirty( [ 'routes' ] ) === false );
	delete plain.added;
	plain.text.value = 'a';
	dirty.snapshot('routes');
}

// a form whose own action bar is not the first one in it: the watcher is told which carries the marker
{
	const stray = append( node( '', {} ) );
	stray.classList.add('nino-admin-actionbar');
	const own = append( node( '', {} ) );
	own.classList.add('nino-admin-actionbar');
	const inputs = { text : field( 'text', 'a' ) };
	const twoBars = node( 'two-bars', {} );
	twoBars.querySelectorAll = function( selector ) { return selector === 'input, textarea, select' ? Object.values( inputs ) : [] };
	twoBars.querySelector = function( selector ) { return selector === 'input, textarea, select, [contenteditable]' ? inputs.text : ( selector === '.nino-admin-actionbar' ? stray : null ) };
	dirty.watchForm( 'twobars', function() { return twoBars }, function( done ) { done( true ) }, function() { return own } );
	dirty.snapshot('twobars');
	inputs.text.value = 'typed';
	dirty.refresh();
	check( 'the marker of a watched form goes to the bar its panel names, not to the first bar in the form', markerOf( own ) !== undefined && markerOf( own ).hidden === false && markerOf( stray ) === undefined );
	inputs.text.value = 'a';
	dirty.refresh();
	check( '...and goes away from it when the form is clean again', markerOf( own ).hidden === true );

	// Without a bar of its own a watched form keeps the first bar in it
	delete dirty._entries.twobars.bar;
	inputs.text.value = 'typed again';
	dirty.refresh();
	check( 'a watched form that names no bar gets the first one in it, as before', markerOf( stray ) !== undefined && markerOf( stray ).hidden === false );
	delete dirty._entries.twobars;
	delete dirty._forms.twobars;
	delete dirty._baseline.twobars;
	dirty.refresh();
}

// a panel that failed to load writes its error into the watched container: no fields, nothing typed
plain.text.value = 'd';
check( 'a form with something typed into it is dirty again', dirty.isDirty( [ 'routes' ] ) === true );
const formQuery = form.querySelector;
form.querySelector = function() { return null };
form.querySelectorAll = function() { return [] };
check( 'an error message in the watched container holds no input - it is not unsaved', dirty.isDirty( [ 'routes' ] ) === false && dirty.dirtyNames().indexOf('routes') === -1 );
form.querySelector = formQuery;

// a save() that throws must not leave the exits shut
dirty.register( 'thrower', { isDirty : function() { return true }, save : function() { throw new Error('boom') }, discard : function() {} } );
dirtyAsked.length = 0;
let thrownCancelled = 0;
let thrown = null;
dirty.guard( [ 'thrower' ], function() { proceeded += 100 }, function() { thrownCancelled++ } );
try {
	answer('save');
}
catch( error ) {
	thrown = error;
}
check( 'a save() that throws is not swallowed...', thrown !== null && thrown.message === 'boom' && proceeded < 100 );
check( '...but the question is over (not asking, nothing saving, no Save in flight) and the exit was cancelled', dirty._asking === false && dirty._saving === 0 && dirty._inSave === false && thrownCancelled === 1 );
delete dirty._entries.thrower;
dirtyAsked.length = 0;
dirtyState.text = true;
dirty.guard( [ 'text' ], function() { proceeded += 1000 } );
check( '...and the next exit asks as before', dirtyAsked.length === 1 );
answer('cancel');
dirtyState.text = false;
dirtyAsked.length = 0;

// a refused Save asks for the focus on a pane that is still hidden: once shown, the first invalid field takes it
let focusedAt = [];
const realTextPane = dirtyNodes['admin-content-text'];
dirtyNodes['admin-content-text'] = { querySelector : function( selector ) { return selector === '[aria-invalid="true"]' ? { focus : function() { focusedAt.push( dirtyShown.join() ) } } : null } };
dirtyState.text = true;
dirtyState.ok = false;
dirtyShown.length = 0;
dirty.guard( [ 'text' ], function() {} );
answer('save');
check( 'a Save that failed puts the focus on the first invalid field only after the pane is on screen', dirtyShown.join() === 'text' && focusedAt.join() === 'text' );
dirtyNodes['admin-content-text'] = realTextPane;
dirtyState.text = false;
dirtyState.ok = true;

// ---- the shell's own exits. The logout button belongs to the last shell that wired it
// (the one with the session dialog above), the language picker to this one
const logoutAsked = [];
withDialog.Nino.auth = { logout : function( to ) { dirtyLog.push( 'logout '+ to ) } };
withDialog.Nino.adminUi.choiceDialog = function( options ) { logoutAsked.push( options ); return true };
const logoutDirty = withDialog.Nino.admin.dirty;
logoutDirty.register( 'probe', { isDirty : function() { return true }, save : function( done ) { done( true ) }, discard : function() {} } );

dirtyLog.length = 0;
shellNodes['admin-user-logout'].listeners.click();
check( 'logout asks first: its request goes out before the page leaves, so the browser would ask after the session is gone', logoutAsked.length === 1 && dirtyLog.length === 0 );
logoutAsked[0].onChoose('cancel');
check( '...a Cancel leaves the session alone', dirtyLog.length === 0 );
shellNodes['admin-user-logout'].listeners.click();
logoutAsked[1].onChoose('discard');
check( '...an answer lets it go', dirtyLog.join() === 'logout [[/nino/dir]]/_admin' && logoutDirty._leaving === true );
logoutDirty._entries.probe.isDirty = function() { return false };

shell.Nino.content = { getText : function( key ) { return key } };
const shellAsked = [];
shell.Nino.adminUi.choiceDialog = function( options ) { shellAsked.push( options ); return true };
const shellDirty = shell.Nino.admin.dirty;
shellDirty.register( 'probe', { isDirty : function() { return true }, save : function( done ) { done( true ) }, discard : function() {} } );

shell.location.href = '';
picker.options.forEach( function( option, i ) { option.selected = i === 1 } );
picker.listeners.change.call( picker );
check( 'the interface language asks first, before the page is loaded again', shellAsked.length === 1 && shell.location.href === '' );
shellAsked[0].onChoose('cancel');
check( '...and a Cancel puts the picker back on the language the page is in', pickerOptions[0].selected === true && pickerOptions[1].selected === false && shell.location.href === '' );
shell.location.hash = '#elements/team';
picker.listeners.change.call( picker );
shellAsked[1].onChoose('discard');
check( '...an answer loads the page in the other language, and the hash goes along - the panel and the level the page was on', shell.location.href === '/_admin?locale=en_US#elements/team' );
shellDirty._leaving = false;

shellDirty._entries.probe.isDirty = function() { return false };
shell.location.hash = '#x';
shellDirty._show('roles');
check( 'a panel or tab that failed to save is brought on screen by its name - a tab of a pane first, since \'roles\' is a tab of the Users pane', shell.location.hash === '#roles' );
shellDirty._show('dashboard');
check( '...a panel by its own', shell.location.hash === '#dashboard' );
let keptDuring = null;
shell.Nino.admin.roles = { showCurrent : function() { keptDuring = shell.Nino.admin.router._keep } };
shellDirty._show('roles');
check( 'it is brought on screen as it stands: while it is shown, a panel that follows the hash is told to keep its level, so a failed Save is not answered with the question again', keptDuring === true && shell.Nino.admin.router._keep === false );
delete shell.Nino.admin.roles;

// ---- the question itself: a native dialog
function fakeElement( tag, noModal ) {
	const el = { tagName : tag, children : [], attributes : {}, listeners : {}, className : '', textContent : '', id : '', parent : null, removed : false, open : false, type : '' };
	el.setAttribute = function( name, value ) { el.attributes[name] = String( value ) };
	el.appendChild = function( child ) { el.children.push( child ); child.parent = el; return child };
	el.addEventListener = function( type, fn ) { el.listeners[type] = fn };
	el.remove = function() { el.removed = true };
	el.focus = function() { el.focused = true };
	if( tag === 'dialog' && noModal !== true ) {
		el.showModal = function() { el.open = true };
		el.close = function() { el.open = false; el.listeners.close() };
	}
	return el;
}

function choiceWorld( noModal ) {
	const world = { opener : fakeElement('button'), root : fakeElement('div'), confirms : [], dialogs : [] };
	world.ctx = {
		console : console,
		confirm : function( message ) { world.confirms.push( message ); return world.confirmAnswer },
		document : {
			documentElement : null, body : fakeElement('body'), activeElement : world.opener,
			createElement : function( tag ) { const created = fakeElement( tag, noModal ); if( tag === 'dialog' ) world.dialogs.push( created ); return created },
			getElementById : function( id ) { return id === 'admin-page-wrap' ? world.root : null },
			addEventListener : function() {},
		},
	};
	world.ctx.window = world.ctx;
	world.ctx.Nino = { events : { bindCallback : function() {} } };
	load( '_admin/assets/Nino.admin.js', vm.createContext( world.ctx ) );
	return world;
}

const choices = [
	{ value : 'save', label : 'Save', kind : 'primary' },
	{ value : 'discard', label : 'Discard', kind : 'danger' },
	{ value : 'cancel', label : 'Cancel', kind : 'secondary' },
];

const world = choiceWorld( false );
const chosen = [];
const opened = world.ctx.Nino.adminUi.choiceDialog( { title : 'Title', message : '<b>Question</b>', choices : choices, onChoose : function( value ) { chosen.push( value ) } } );
const box = world.dialogs[0];
const buttons = box.children[0].children[box.children[0].children.length - 1].children;
check( 'the question is a native dialog in the workbench root, opened modally - not in the body, where the design system\'s rules do not reach', opened === true && box.open === true && world.root.children[0] === box && box.className === 'nino-admin-dialog' );
check( '...its words are text, never markup', box.children[0].children.some( function( c ) { return c.textContent === '<b>Question</b>' } ) && box.children[0].children[0].textContent === 'Title' );
check( '...it has one button per choice, in the three button kinds', buttons.map( function( b ) { return b.className+ ':'+ b.textContent } ).join() === 'nino-admin-btn-primary:Save,nino-admin-btn-danger:Discard,nino-admin-btn-secondary:Cancel' && buttons.every( function( b ) { return b.type === 'button' } ) );
check( 'a second question while one stands is refused', world.ctx.Nino.adminUi.choiceDialog( { message : 'again', choices : choices, onChoose : function() { chosen.push('second') } } ) === false && world.dialogs.length === 1 );
buttons[1].listeners.click();
check( 'a click answers once with its value, removes the dialog and gives the focus back', chosen.join() === 'discard' && box.removed === true && world.opener.focused === true );
world.ctx.Nino.adminUi.choiceDialog( { message : 'next', choices : choices, onChoose : function( value ) { chosen.push( value ) } } );
world.dialogs[1].close();
check( 'Escape - the dialog closing with no button clicked - is the secondary choice, the one that does nothing', chosen.join() === 'discard,cancel' );

const fallback = choiceWorld( true );
fallback.confirmAnswer = true;
const fallbackChosen = [];
fallback.ctx.Nino.adminUi.choiceDialog( { message : 'Sure?', choices : choices, onChoose : function( value ) { fallbackChosen.push( value ) } } );
fallback.confirmAnswer = false;
fallback.ctx.Nino.adminUi.choiceDialog( { message : 'Sure?', choices : choices, onChoose : function( value ) { fallbackChosen.push( value ) } } );
check( 'a browser without showModal asks with confirm(): OK is the primary choice (never the one that throws input away), Cancel the secondary one', fallback.confirms.join() === 'Sure?,Sure?' && fallbackChosen.join() === 'save,cancel' );

/*	The "?" of a pane: one listener on the content wrapper answers every one of them, asks the server for the manual of
	the pane the button names, and hands the answer to the design system - the shell draws nothing of its own. A shell of
	its own, since the one above has one click listener on its wrapper to give to the registry of unsaved input, and a
	page has as many as it likes	*/
function manualWorld() {
	const wrap = node( 'admin-content-wrap', {} );
	const texts = {
		'/_admin/nav/elements' : 'Elements', '/_admin/common/label/manual' : 'Manual', '/_admin/common/notice/manual' : 'This panel has no manual.',
		'/_admin/common/error/manual' : 'The manual could not be loaded.', '/_admin/common/error/offline' : 'No connection.', '/_admin/error/no_manual' : 'This panel has no manual (code).',
		'/_admin/common/word/page' : 'Page', '/_admin/common/word/intro' : 'Introduction',
	};
	const win = {
		console : console,
		location : { hash : '' },
		history : { replaceState : function() {} },
		document : {
			documentElement : null, body : null,
			getElementById : function( id ) { return id === 'admin-content-wrap' ? wrap : null },
			createElement : element,
			createTextNode : function( text ) { return Object.assign( element('#text'), { textContent : String( text ) } ) },
			addEventListener : function() {},
			querySelectorAll : function() { return [] },
		},
	};
	win.window = win;
	win.Nino = { events : { bindCallback : function() {} }, content : { getText : function( key ) { return texts[key] ?? '' } } };
	const ctx = vm.createContext( win );
	load( '_admin/assets/Nino.admin.js', ctx );
	load( '_admin/assets/script.js', ctx );

	const asked = [];
	const shown = [];
	win.Nino.adminUi.api.call = function( action, payload, callback ) { asked.push( { action : action, payload : payload, callback : callback } ) };
	win.Nino.adminUi.manualDialog = function( title, content, opener ) { shown.push( { title : title, content : content, opener : opener } ); return true };

	return { Nino : win.Nino, wrap : wrap, asked : asked, shown : shown };
}

{
	const m = manualWorld();
	m.Nino.admin.manual.init();
	const questionMark = node( '', { manual : 'elements' } );
	const press = function( target ) { m.wrap.listeners.click( { target : { closest : function( selector ) { return selector === '[data-manual]' ? target : null } } } ) };
	const tags = function( root, tag ) { return findAll( root, function( el ) { return el.tagName === tag.toUpperCase() } ) };

	check( 'the shell listens once, on the content wrapper, for every "?" of every pane', typeof m.wrap.listeners.click === 'function' );
	press( null );
	check( 'a click on anything else is nothing', m.asked.length === 0 && m.shown.length === 0 );

	press( questionMark );
	check( 'a "?" asks for the manual of the pane its button names, and opens nothing before the answer is here', m.asked.length === 1 && m.asked[0].action === 'admin/manual' && m.asked[0].payload.panel === 'elements' && m.shown.length === 0 );
	press( questionMark );
	check( 'pressed again while the answer is out, it is the same question', m.asked.length === 1 );

	m.asked[0].callback( 200, { title : '/_admin/nav/elements', sections : [ { heading : '', entries : [ { handle : '', text : 'What it is for.' }, { handle : 'page', text : 'A page.' } ] } ], text : '', none : '', words : true } );
	check( 'the answer is shown in the dialog under the name of the panel in the interface language, with the button that asked to get the focus back', m.shown.length === 1 && m.shown[0].title === 'Elements' && m.shown[0].opener === questionMark );
	check( '...drawn by the design system: a panel\'s entries are named by the words of the vocabulary and are no code, the line about the whole panel has no name', m.shown[0].content.className === 'nino-admin-manual'
		&& tags( m.shown[0].content, 'code' ).length === 0 && tags( m.shown[0].content, 'dt' ).map( el => el.textContent ).join('|') === '|Page' && tags( m.shown[0].content, 'dd' ).map( el => el.textContent ).join('|') === 'What it is for.|A page.' );

	press( questionMark );
	check( 'once it is answered the "?" asks again - the pane may have changed under the person', m.asked.length === 2 );
	m.asked[1].callback( 200, { title : 'Sample', sections : [ { heading : 'Shortcodes', entries : [ { handle : '[card]', text : 'Draws the card.' } ] }, { heading : 'Routes', entries : [] } ], text : '', none : 'Nothing.', words : false } );
	check( 'a feature\'s manual is the same call: its sections under their headings, the handles as code, an empty section saying so, a title that is no key shown as it is', m.shown[1].title === 'Sample'
		&& tags( m.shown[1].content, 'h3' ).map( el => el.textContent ).join('|') === 'Shortcodes|Routes' && tags( tags( m.shown[1].content, 'dt' )[0], 'code' )[0].textContent === '[card]'
		&& tags( m.shown[1].content, 'p' ).map( el => el.textContent ).join('|') === 'Nothing.' );

	press( questionMark );
	m.asked[2].callback( 200, { title : 'Sample', sections : [], text : 'Put `[card]` where it goes.', none : '', words : false } );
	check( 'a manual written as running text is drawn as that', tags( m.shown[2].content, 'p' ).length === 1 && tags( m.shown[2].content, 'code' )[0].textContent === '[card]' );

	press( questionMark );
	m.asked[3].callback( 200, { title : 'Sample', sections : [], text : '', none : '', words : false } );
	check( 'an answer with nothing in it says so in the dialog instead of opening an empty one', m.shown[3].title === 'Sample' && m.shown[3].content.textContent === 'This panel has no manual.' && m.shown[3].content.className === 'nino-admin-error' );

	press( questionMark );
	m.asked[4].callback( 404, { error : 'no manual' } );
	check( 'a request that failed is told in the same dialog, under the name of the manual, in the words the failure has', m.shown[4].title === 'Manual' && m.shown[4].opener === questionMark
		&& m.shown[4].content.textContent === '(404) no manual' && m.shown[4].content.className === 'nino-admin-error' );
	press( questionMark );
	m.asked[5].callback( 500, { code : 'offline' } );
	check( '...and one that never got an answer as that', m.shown[5].content.textContent === 'No connection.' );
	press( questionMark );
	m.asked[6].callback( 500, null );
	check( '...and one with no body at all is a failure too, with the sentence the shell has for it', m.shown[6].content.textContent === '(500) The manual could not be loaded.' );
	press( questionMark );
	m.asked[7].callback( 404, { error : 'no manual', code : 'no_manual' } );
	check( 'a pane that lost its manual since the page was drawn is refused with a code, and the dialog tells it in the words of the code - without the status number or the English of the log', m.shown[7].title === 'Manual' && m.shown[7].content.textContent === 'This panel has no manual (code).' && m.shown[7].content.className === 'nino-admin-error' );
	press( questionMark );
	check( 'after every one of them the "?" can ask again', m.asked.length === 9 );
}

/*	The mode the project is in, and what the shell makes of it: the chip, the
	weight a question has to have to be asked (Nino.admin.mode.policy()), and
	Ctrl+S, which saves what is on screen through the registry. A context of its
	own: a page with the shell's root, its chip and a document that keeps the
	listeners it is given	*/
{
	const words = fills('_admin/text/en_US.php');
	const modeNodes = {};
	const modeDialog = { open : null };
	const modeListeners = {};
	const modeNode = function( id, dataset ) {
		const created = node( id, dataset );
		created.textContent = '';
		created.title = '';
		created.setAttribute = function( name, value ) { created.attributes[name] = String( value ); if( name === 'title' ) created.title = String( value ) };
		created.removeAttribute = function( name ) { delete created.attributes[name] };
		modeNodes[id] = created;
		return created;
	};
	const modeWrap = modeNode( 'admin-page-wrap', { mode : 'production', maintenance : '0' } );
	const chip = modeNode( 'admin-mode-chip', {} );
	chip.setAttribute( 'href', '#config' );
	const modeContent = node( 'admin-content-wrap', {} );
	modeContent.addEventListener = function() {};
	modeNodes['admin-content-wrap'] = modeContent;
	const modeHash = { hash : '#users' };
	const modeCtx = {
		console : console,
		location : modeHash,
		history : { replaceState : function() {} },
		addEventListener : function() {},
		removeEventListener : function() {},
		document : {
			documentElement : null, body : null,
			getElementById : function( id ) { return modeNodes[id] ?? null },
			// The dialog of a question, where one is open
			querySelector : function( selector ) { return selector === 'dialog[open]' ? modeDialog.open : null },
			createElement : function() { return node( '', {} ) },
			addEventListener : function( type, fn ) { ( modeListeners[type] = modeListeners[type] ?? [] ).push( fn ) },
		},
	};
	modeCtx.window = modeCtx;
	modeCtx.Nino = { events : { bindCallback : function() {} }, content : { getText : function( key ) { return words[key] ?? key } } };
	const modeContext = vm.createContext( modeCtx );
	load( '_admin/assets/Nino.admin.js', modeContext );
	load( '_admin/assets/script.js', modeContext );
	const mode = modeCtx.Nino.admin.mode;

	// A page without the shell's root answers production and writes nothing anywhere
	const rootless = modeCtx.document.getElementById;
	modeCtx.document.getElementById = function() { return null };
	mode.set( 'development' );
	check( 'a page without the shell\'s root is a production page, and set() has nothing to write to', mode.current() === 'production' );
	modeCtx.document.getElementById = rootless;

	modeNodes['admin-content-config'] = node( 'admin-content-config', {} );
	mode.init();
	check( 'init() draws the chip: the word of the mode and the sentence of the mode as its title, and no class of a mode that is not on', chip.textContent === words['/_admin/mode/word/production'] && chip.title === words['/_admin/mode/hint/production']
		&& chip.classList.contains('nino-admin-mode-chip--development') === false && chip.dataset.initial === chip.textContent.charAt(0) );
	check( '...and keeps the link to the Config panel where the account has the panel', chip.getAttribute('href') === '#config' );

	delete modeNodes['admin-content-config'];
	mode.init();
	check( 'an account without the Config panel gets the chip without the link', chip.getAttribute('href') === null );
	chip.setAttribute( 'href', '#config' );

	mode.set( 'development' );
	check( 'set() writes the mode on the shell and draws the chip again: its word, its sentence, its colour class', modeWrap.dataset.mode === 'development' && mode.current() === 'development'
		&& chip.textContent === words['/_admin/mode/word/development'] && chip.title === words['/_admin/mode/hint/development'] && chip.classList.contains('nino-admin-mode-chip--development') === true
		&& chip.dataset.initial === chip.textContent.charAt(0) && chip.textContent !== words['/_admin/mode/word/production'] );
	mode.set( 'staging' );
	mode.set( undefined );
	mode.set( '' );
	mode.set( 'Production' );
	check( 'a value that is no mode is ignored by set(): the mode, the chip and the class stay', modeWrap.dataset.mode === 'development' && mode.current() === 'development' && chip.textContent === words['/_admin/mode/word/development'] && chip.classList.contains('nino-admin-mode-chip--development') === true );
	mode.set( 'production' );
	check( 'set() takes the class off with the mode', mode.current() === 'production' && chip.classList.contains('nino-admin-mode-chip--development') === false && chip.textContent === words['/_admin/mode/word/production'] );
	modeWrap.dataset.mode = 'staging';
	check( 'a shell that says anything but development is production', mode.current() === 'production' );
	modeWrap.dataset.mode = 'production';

	// The matrix: the three weights, a weight nobody named, the two modes, the maintenance page up and down
	const live = words['/_admin/mode/confirm/live'];
	const expected = {
		production : { routine : [ true, '' ], big : [ true, live ], destructive : [ true, '' ], nonsense : [ true, '' ] },
		development : { routine : [ false, '' ], big : [ false, '' ], destructive : [ true, '' ], nonsense : [ true, '' ] },
	};
	const wrong = [];
	[ 'production', 'development' ].forEach( function( kind ) {
		[ false, true ].forEach( function( maintenance ) {
			mode.set( kind );
			mode.maintenance( maintenance );
			Object.keys( expected[kind] ).forEach( function( level ) {
				const policy = mode.policy( level );
				const note = kind === 'production' && level === 'big' && maintenance === true ? '' : expected[kind][level][1];
				if( policy.ask !== expected[kind][level][0] || policy.note !== note )
					wrong.push( kind+ '/'+ ( maintenance ? 'maintenance' : 'live' )+ '/'+ level+ ' '+ JSON.stringify( policy ) );
			} );
		} );
	} );
	check( 'policy() answers the matrix of weight, mode and maintenance page: a site being built asks only before what cannot be undone, a live one asks about everything and warns before a big change unless the maintenance page is up'+ ( wrong.length === 0 ? '' : ' - '+ wrong.join( '; ' ) ), wrong.length === 0 );
	check( '...and the warning is a sentence the shell has', live !== undefined && live !== '' );
	mode.set( 'production' );
	modeWrap.dataset.maintenance = '1';
	mode.init();
	check( 'init() takes the maintenance state the page was drawn with: no warning on a big question while the maintenance page is up', mode.policy( 'big' ).note === '' );
	mode.maintenance( false );
	check( 'maintenance( false ) brings the warning back, and only true is a maintenance page', mode.policy( 'big' ).note === live && ( mode.maintenance( 'yes' ), mode.policy( 'big' ).note === live ) );
	modeWrap.dataset.maintenance = '0';
	mode.init();

	// --- Ctrl+S --------------------------------------------------------------
	const dirty = modeCtx.Nino.admin.dirty;
	dirty.init( function() {} );
	check( 'the registry listens for the key on the document, from its init()', ( modeListeners.keydown ?? [] ).length === 1 && modeListeners.keydown[0] === dirty.shortcut );

	const key = function( init ) {
		let prevented = 0;
		const ev = Object.assign( { key : 's', ctrlKey : true, metaKey : false, altKey : false, shiftKey : false, preventDefault : function() { prevented++ } }, init );
		modeListeners.keydown.forEach( function( fn ) { fn( ev ) } );
		return prevented;
	};
	const log = [];
	const state = { users : true };
	let finish = null;
	let refreshed = 0;
	const realRefresh = dirty.refresh;
	dirty.refresh = function() { refreshed++; return realRefresh() };
	dirty._entries = {};
	dirty.register( 'users', {
		isDirty : function() { return state.users },
		save : function( done ) { log.push( 'save' ); state.users = false; done( true ) },
		discard : function() {},
	} );
	modeHash.hash = '#users';
	mode.set( 'production' );

	let prevented = key( {} );
	check( 'Ctrl+S saves the panel that is on screen when it holds unsaved input', JSON.stringify( log ) === '["save"]' && prevented === 1 );
	check( '...and the registry is free again, and the markers follow', dirty._saving === 0 && dirty._inSave === false && dirty._asking === false && refreshed >= 1 );
	prevented = key( {} );
	check( 'a panel that holds nothing unsaved has nothing saved - and the browser still does not get the key', JSON.stringify( log ) === '["save"]' && prevented === 1 );

	state.users = true;
	prevented = key( { ctrlKey : false, metaKey : true } );
	check( 'Cmd+S does what Ctrl+S does', JSON.stringify( log ) === '["save","save"]' && prevented === 1 );
	state.users = true;
	key( { key : 'S' } );
	check( '...and so does the capital letter, where caps lock is on', log.length === 3 );

	state.users = true;
	const before = log.length;
	const refused = [ key( { shiftKey : true } ), key( { altKey : true } ), key( { ctrlKey : false } ), key( { key : 'a' } ), key( { key : 'Enter' } ) ];
	check( 'Ctrl+Shift+S, Alt+S, a bare S and another key do nothing, and leave the browser its answer to them', log.length === before && refused.every( function( n ) { return n === 0 } ) );
	check( '...Ctrl+Alt+S does nothing either', ( key( { altKey : true, metaKey : true } ), log.length === before ) );
	state.users = false;

	// no entry for what is on screen
	modeHash.hash = '#nobody';
	prevented = key( {} );
	check( 'a panel that registered nothing has nothing saved, and the key is still taken from the browser', log.length === before && prevented === 1 );
	modeHash.hash = '';
	check( '...and so is an address that names no panel', key( {} ) === 1 && log.length === before );
	modeHash.hash = '#users';

	// a Save that is still running
	let saves = 0;
	dirty._entries.users.save = function( done ) { saves++; finish = done };
	state.users = true;
	key( {} );
	check( 'while the save runs the registry is busy', saves === 1 && dirty._saving === 1 && dirty._asking === true );
	prevented = key( {} );
	check( '...a second press is the same press arriving twice: nothing is saved again, and the browser still does not save the page', saves === 1 && prevented === 1 );
	let stayed = 0;
	dirty.guard( [ 'users' ], function() { stayed += 10 }, function() { stayed++ } );
	check( '...and an exit that comes meanwhile - a link, the log out - asks nothing and is told to stay', stayed === 1 && dirty._saving === 1 );
	finish( true );
	check( 'done( true ) hands the registry back', dirty._saving === 0 && dirty._asking === false && dirty._inSave === false );
	finish( true );
	check( '...once, however often it is called', dirty._saving === 0 );
	key( {} );
	check( '...and the next press saves again', saves === 2 );
	finish( false );
	check( 'a save that fails hands it back too', dirty._saving === 0 && dirty._asking === false );

	// a question that is open, a Save the shell answered to one that runs, a save being called
	state.users = true;
	dirty._asking = true;
	key( {} );
	check( 'nothing is saved while a question is open', saves === 2 );
	dirty._asking = false;
	dirty._saving = 1;
	key( {} );
	check( '...nor while a Save answered to a question runs', saves === 2 );
	dirty._saving = 0;
	dirty._inSave = true;
	key( {} );
	check( '...nor from inside a save that is being called', saves === 2 );
	dirty._inSave = false;

	// a dialog stands over the page: a question of the shell or a panel's own
	modeDialog.open = {};
	state.users = true;
	prevented = key( {} );
	check( 'nothing is saved while a dialog is open, whoever asked it, and the browser still does not get the key', saves === 2 && prevented === 1 );
	modeDialog.open = null;

	// a failure of a nested save leaves its mark until the run that saw it ends
	dirty._entries.users.save = function( done ) { saves++; finish = done };
	dirty._failShown = true;
	key( {} );
	finish( false );
	check( 'a run ends as a Save answered to a question does: the mark of a nested failure does not outlive it, so that a later failed save brings its form on screen', dirty._failShown === false && dirty._saving === 0 );

	// a function that throws
	dirty._entries.users.save = function() { throw new Error( 'broken' ) };
	let thrown = '';
	try { key( {} ) } catch( error ) { thrown = error.message }
	check( 'a save that throws is not swallowed, and does not leave the registry shut', thrown === 'broken' && dirty._saving === 0 && dirty._asking === false && dirty._inSave === false );

	// the entry's own function for the mode
	const ran = [];
	state.users = false;
	dirty.register( 'users', {
		isDirty : function() { return state.users },
		save : function( done ) { ran.push( 'save' ); done( true ) },
		discard : function() {},
		shortcut : {
			development : function( done ) { ran.push( 'development' ); done( true ) },
			production : function( done ) { ran.push( 'production' ); finish = done },
		},
	} );
	mode.set( 'production' );
	key( {} );
	check( 'an entry\'s function for the mode the project is in is what Ctrl+S calls - also when nothing is unsaved - and its save is not', JSON.stringify( ran ) === '["production"]' && dirty._saving === 1 );
	key( {} );
	check( '...and a run of it is a run: a second press meanwhile does nothing', ran.length === 1 );
	finish( true );
	check( '...which ends with done( ok )', dirty._saving === 0 && dirty._asking === false );
	mode.set( 'development' );
	key( {} );
	check( 'the other mode calls the other function', JSON.stringify( ran ) === '["production","development"]' && dirty._saving === 0 );
	state.users = true;
	key( {} );
	check( '...whether the entry holds unsaved input or not', JSON.stringify( ran ) === '["production","development","development"]' );

	dirty.register( 'users', {
		isDirty : function() { return state.users },
		save : function( done ) { ran.push( 'save' ); state.users = false; done( true ) },
		discard : function() {},
		shortcut : { production : function( done ) { ran.push( 'production' ); done( true ) } },
	} );
	state.users = false;
	key( {} );
	check( 'an entry with no function for this mode saves, as an entry with none does: nothing while it is clean', JSON.stringify( ran ) === '["production","development","development"]' );
	state.users = true;
	key( {} );
	check( '...and its save while it holds unsaved input', ran[ran.length - 1] === 'save' && dirty._saving === 0 );
	dirty.refresh = realRefresh;

	// watchForm() hands the function on
	const form = node( 'a-form', {} );
	form.querySelector = function() { return null };
	form.querySelectorAll = function() { return [] };
	const given = { production : function() {} };
	dirty.watchForm( 'with', function() { return form }, function() {}, undefined, given );
	dirty.watchForm( 'without', function() { return form }, function() {} );
	check( 'watchForm() registers the functions it is given for Ctrl+S with the entry, and none where it is given none', dirty._entries.with.shortcut === given && dirty._entries.without.shortcut === undefined );
}

// the wiring in the shell's source
const shellSource = fs.readFileSync( path.join( __dirname, '../_admin/assets/script.js' ), 'utf8' );
check( 'the shell wires the registry up from onReady()', /Nino\.admin\.dirty\.init\(\s*function\( name \)/.test( shellSource ) === true );
check( 'the shell draws the chip of the mode from onReady() too', /onReady : function\(\) \{[\s\S]*?Nino\.admin\.mode\.init\(\);/.test( shellSource ) === true );
check( 'the shell wires the "?" of the panes up from onReady() too', /Nino\.admin\.onReady = |onReady : function\(\) \{[\s\S]*?Nino\.admin\.manual\.init\(\);/.test( shellSource ) === true );

// the stylesheet's two rules that depend on what the script puts into the nav and the bar
const shellCss = fs.readFileSync( path.join( __dirname, '../_admin/assets/style.css' ), 'utf8' );
check( 'the first heading of the rail loses its top margin also where the phone\'s select was inserted in front of it',
	/\.nino-admin \.nino-admin-nav > \.nino-admin-nav-group:first-child,\s*\.nino-admin \.nino-admin-nav > \.nino-admin-nav-select \+ \.nino-admin-nav-group \{\s*margin-top: 0;/.test( shellCss ) === true );
check( 'on a phone the unsaved marker is a row of its own, so that it never slides under the Save button of a bar of three',
	/@media \(max-width: 38rem\) \{[^@]*\.nino-admin-actionbar:has\(> \.nino-admin-actionbar-dirty:not\(\[hidden\]\)\) \{\s*flex-wrap: wrap;\s*\}\s*\.nino-admin \.nino-admin-actionbar > \.nino-admin-actionbar-dirty \{\s*flex: 1 0 100%;/.test( shellCss ) === true );

// The rail is a column (.nino-admin-rail, the shell's #admin-bar-wrap):
// letting it wrap made the page wider than a 320-358px phone screen
check( 'nothing lets the rail wrap, so it is never wider than a phone screen',
	/(?:#admin-bar-wrap|\.nino-admin-rail)\s*\{[^}]*flex-wrap/.test( shellCss ) === false );

done();
