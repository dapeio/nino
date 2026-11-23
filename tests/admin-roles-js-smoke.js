/**
 *	Nino										A compact filesystembased php framework
 *	admin-roles-js-smoke.js	DOM-light checks for the Roles tab's script
 *													(_admin/Nino/Modules/Users/assets/roles.js).
 *
 *													The finer permissions of a role are picked, never
 *													typed: area, action and field out of the tree the
 *													panels offer (scopes()). What is checked here is the
 *													part that must agree with the server - which string
 *													covers which, and which panel a permission puts into
 *													detail mode, the way \Nino\Auth::checkPermission()
 *													and \Nino\Admin\Admin::isScoped() read them - the
 *													warning that comes with that transition - in and
 *													out again -, the summary
 *													of what a role may do, and that the Add button can
 *													only ever add a string out of the tree. The
 *													permissions are lists of ticks, a group each, and a
 *													tick taken away is a permission taken away. The form
 *													says what is missing under the field.
 *
 *	Usage: node tests/admin-roles-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, element, findAll, fire, shellRouter } = require( './harness.js' );

// --- the words -------------------------------------------------------------

const words = Object.assign( {},
	fills('_admin/text/en_US.php'),
	fills('_admin/Nino/Modules/Elements/text/en_US.php'),
	fills('_admin/Nino/Modules/Text/text/en_US.php'),
	fills('_admin/Nino/Modules/Users/text/en_US.php') );

function text( key ) {
	return words[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const confirms = [];
let confirmAnswer = true;

const Nino = {
	admin : {},
	events : { bindCallback : function() {} },
	http : { sendRequest : function() {} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		createElementNS : function( space, tag ) { return element( tag ) },
		createTextNode : function( value ) { return { tagName : '#TEXT', textContent : value, attributes : {}, dataset : {}, children : [] } },
		getElementById : function() { return null },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, confirm : function( message ) { confirms.push( message ); return confirmAnswer } };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Users/assets/roles.js', context );

const roles = Nino.admin.roles;

const SCOPES = [
	{ scope : '/_admin/elements/', door : '/_admin/elements/manage', label : '/_admin/nav/elements', areas : [
		{ id : 'services', label : 'Services', perm : '/_admin/elements/services/*', actions : [
			{ id : 'insert', label : '/_admin/elements/scope/insert', perm : '/_admin/elements/services/insert' },
			{ id : 'update', label : '/_admin/elements/scope/update', perm : '/_admin/elements/services/update/*', fields : [
				{ id : 'title', label : 'title', perm : '/_admin/elements/services/update/title' },
				{ id : 'body', label : 'body', perm : '/_admin/elements/services/update/body' },
			] },
			{ id : 'delete', label : '/_admin/elements/scope/delete', perm : '/_admin/elements/services/delete' },
		] },
	] },
	{ scope : '/_admin/text/', door : '/_admin/text/manage', label : '/_admin/nav/text', areas : [
		{ id : 'page-home', label : '/page-home', actions : [
			{ id : 'update', label : '/_admin/text/scope/update', perm : '/_admin/text/update/page-home/*', fields : [
				{ id : '/page-home/hero/title', label : '/page-home/hero/title', perm : '/_admin/text/update/page-home/hero/title' },
			] },
		] },
	] },
];

const OPTIONS = [
	{ perm : '/_admin/elements/manage', label : '/_admin/nav/elements', group : 'content', offered : true },
	{ perm : '/_admin/text/manage', label : '/_admin/nav/text', group : 'content', offered : true },
	{ perm : '/_admin/old/thing', label : '/_admin/old/thing', group : 'other', offered : false },
	{ perm : '/_admin/other/thing', label : '/_admin/other/thing', group : 'other', offered : false },
	{ perm : '/_admin/text/update/page-home/hero/title', label : '/_admin/text/update/page-home/hero/title', group : 'other', offered : false },
];

// The Roles tab only reads these three when it draws
roles._scopes = SCOPES;
roles._permOptions = OPTIONS;
roles._groups = [ 'content', 'structure', 'features', 'system', 'other' ];

const ELEMENTS = text('/_admin/nav/elements');
const TEXT = text('/_admin/nav/text');


console.log('Admin Roles - which string covers which');

check( 'the words the checks below rely on exist', ELEMENTS !== '' && TEXT !== '' && text('/_admin/roles/summary/title') !== '' );

check( 'a blanket over a panel covers its door', roles.covers( [ '/_admin/elements/*' ], '/_admin/elements/manage' ) === true );
check( '/_admin/* covers the door of every panel', roles.covers( [ '/_admin/*' ], '/_admin/elements/manage' ) === true );
check( 'the string itself covers itself', roles.covers( [ '/_admin/elements/manage' ], '/_admin/elements/manage' ) === true );
check( 'full access covers anything', roles.covers( [ '/*' ], '/_admin/text/update/page-home/hero/title' ) === true );
check( 'a blanket over one type covers its actions and fields, not another type', roles.covers( [ '/_admin/elements/services/*' ], '/_admin/elements/services/update/title' ) === true
	&& roles.covers( [ '/_admin/elements/services/*' ], '/_admin/elements/posts/insert' ) === false );
check( 'a door does not cover what is inside it', roles.covers( [ '/_admin/elements/manage' ], '/_admin/elements/services/insert' ) === false );
check( 'a sibling does not cover, and neither does nothing', roles.covers( [ '/_admin/text/*' ], '/_admin/elements/manage' ) === false && roles.covers( [], '/_admin/x' ) === false );
check( 'a string with no slash has no parent to walk up to', roles.covers( [ '/*' ], 'plain' ) === false );

console.log('\nAdmin Roles - detail mode');

function state( perms ) {
	return roles.scopeState( perms, SCOPES );
}

check( 'the door puts nothing into detail mode', state( [ '/_admin/elements/manage' ] )['/_admin/elements/'] === false );
check( '...nor does the blanket over the panel', state( [ '/_admin/elements/*' ] )['/_admin/elements/'] === false );
check( 'one action does', state( [ '/_admin/elements/services/insert' ] )['/_admin/elements/'] === true );
check( 'a whole type does too - a role limited to one type is still described', state( [ '/_admin/elements/services/*' ] )['/_admin/elements/'] === true );
check( 'a group of text keys does', state( [ '/_admin/text/update/page-home/*' ] )['/_admin/text/'] === true );
check( 'the panels are told apart', state( [ '/_admin/text/update/page-home/*' ] )['/_admin/elements/'] === false );

console.log('\nAdmin Roles - the three lists only ever add what the tree holds');

const labels = roles._treeLabels( SCOPES );
const entries = roles._areaList( SCOPES );
const reachable = [];
entries.forEach( function( entry, areaAt ) {
	roles._actionList( entry ).forEach( function( item, actionAt ) {
		const fields = roles._fieldList( item );
		if( fields.length === 0 )
			reachable.push( roles._permFor( entries, { area : areaAt, action : actionAt, field : 0 } ) );
		fields.forEach( function( field, fieldAt ) { reachable.push( roles._permFor( entries, { area : areaAt, action : actionAt, field : fieldAt } ) ) } );
	} );
} );

check( 'every combination of the three lists stands on a permission of the tree', reachable.length > 0 && reachable.every( function( perm ) { return typeof labels[perm] === 'string' } ) );
check( '...and every permission of the tree can be reached', Object.keys( labels ).every( function( perm ) { return reachable.indexOf( perm ) !== -1 } ) );
check( 'a combination outside the lists stands on nothing', roles._permFor( entries, { area : 9, action : 0, field : 0 } ) === '' && roles._permFor( entries, { area : 0, action : 9, field : 0 } ) === '' );
check( 'the second list starts with "everything in this area" where the area has a permission of its own', roles._actionList( entries[0] )[0].action === null
	&& roles._actionList( entries[1] )[0].action !== null );
check( 'the third list exists only for an action with fields, and starts with all of them', roles._fieldList( roles._actionList( entries[0] )[2] ).length === 3
	&& roles._fieldList( roles._actionList( entries[0] )[1] ).length === 0 && roles._fieldList( roles._actionList( entries[0] )[0] ).length === 0 );
check( 'a held permission is named by its place in the tree', labels['/_admin/elements/services/update/title'] === ELEMENTS+ ' · Services · '+ text('/_admin/elements/scope/update')+ ' · title' );

console.log('\nAdmin Roles - the lists of ticks and the warning');

/*	The permissions drawn: the fieldsets under one holder, so that a question asks the whole of them	*/
function draw( perms ) {
	const parts = roles._renderPermissions( perms );
	parts.holder = element('div');
	parts.fieldsets.forEach( function( set ) { parts.holder.appendChild( set ) } );
	return parts;
}

function lists( parts ) {
	const select = function( key ) { return parts.holder.querySelector('[data-key="'+ key+ '"]') };
	return { area : select('scope-area'), action : select('scope-action'), field : select('scope-field') };
}

function choose( select, index ) {
	select.value = String( index );
	fire( select, 'change' );
}

function addButton( parts ) {
	return findAll( parts.holder, function( el ) { return el.tagName === 'BUTTON' && el.textContent === text('/_admin/roles/scope/add') } )[0];
}

function summary( parts ) {
	return findAll( parts.holder, function( el ) { return el.tagName === 'LI' } ).map( function( li ) { return li.textContent } );
}

/** The tick of a permission, in whichever list it stands */
function tickOf( parts, perm ) {
	return findAll( parts.holder, function( el ) { return el.tagName === 'INPUT' && el.type === 'checkbox' && el.value === perm } )[0] ?? null;
}

function tick( parts, perm, on ) {
	const box = tickOf( parts, perm );
	box.checked = on;
	fire( box, 'change' );
}

/** The words a tick is named by */
function nameOfTick( parts, perm ) {
	return findAll( tickOf( parts, perm ).parentNode, function( el ) { return el.tagName === 'SPAN' } )[0].textContent;
}

let parts = draw( [ '/_admin/elements/manage' ] );
let found = lists( parts );

check( 'the permissions are one list of ticks for each group of the rail, in the order roles/list names them, and the group is the name of its list', findAll( parts.holder, function( el ) { return el.dataset.group !== undefined } ).map( function( el ) { return el.dataset.group } ).join() === 'content'
	&& findAll( parts.holder, function( el ) { return el.dataset.group !== undefined } ).every( function( set ) { return set.tagName === 'FIELDSET' && set.children[0].tagName === 'LEGEND' } ) );
check( '...every permission offered has its tick, the ones the role holds are ticked, and the list of the permissions nobody offers is not shown as long as the role holds none: what other roles hold is not in it', tickOf( parts, '/_admin/elements/manage' ).checked === true && tickOf( parts, '/_admin/text/manage' ).checked === false
	&& tickOf( parts, '/_admin/old/thing' ) === null && tickOf( parts, '/_admin/other/thing' ) === null && parts.perms().join() === '/_admin/elements/manage' );
check( 'full access is a switch, which is no tick of any list', tickOf( parts, '/*' ) === null && parts.fullCheck.type === 'checkbox' && parts.fullCheck.parentNode.className === 'nino-admin-switch' );

tick( parts, '/_admin/text/manage', true );
check( 'a tick adds the permission, and a tick taken away takes it', parts.perms().join() === '/_admin/elements/manage,/_admin/text/manage' );
tick( parts, '/_admin/elements/manage', false );
check( '...and nobody is asked about a door', parts.perms().join() === '/_admin/text/manage' && confirms.length === 0 );
parts = draw( [ '/_admin/elements/manage', '/_admin/old/thing', '/_admin/elements/services/insert' ] );
tick( parts, '/_admin/text/manage', true );
check( 'a tick in one group leaves the permissions of every other group, the ones nobody offers and the added ones as they are', parts.perms().sort().join() === [ '/_admin/elements/manage', '/_admin/old/thing', '/_admin/elements/services/insert', '/_admin/text/manage' ].sort().join() );
tick( parts, '/_admin/elements/manage', false );
check( '...and a tick taken away takes that one alone', parts.perms().sort().join() === [ '/_admin/old/thing', '/_admin/elements/services/insert', '/_admin/text/manage' ].sort().join() );
parts = draw( [ '/_admin/elements/manage' ] );
found = lists( parts );

check( 'the first list offers every area, under the name of its panel', found.area !== null
	&& found.area.children.map( function( o ) { return o.textContent } ).join('|') === ELEMENTS+ ' · Services|'+ TEXT+ ' · /page-home' );
check( 'choosing it draws the list of actions after it', found.action !== null && found.action.children.length === 4 );
check( '...and the list of fields only for an action that has them', found.field === null );

choose( found.action, 2 );
found = lists( parts );
check( 'an action with fields brings the third list, with "all fields" first', found.field !== null
	&& found.field.children.map( function( o ) { return o.textContent } ).join('|') === text('/_admin/roles/scope/all-fields')+ '|title|body' );
check( 'these lists are not an edit of the role', found.area.dataset.dirty === 'ignore' && found.action.dataset.dirty === 'ignore' && found.field.dataset.dirty === 'ignore' );

// Adding the first single permission of a panel is the transition
parts = draw( [ '/_admin/elements/manage' ] );
found = lists( parts );
choose( found.action, 2 );
found = lists( parts );
choose( found.field, 1 );
confirmAnswer = false;
fire( addButton( parts ), 'click' );
check( 'the first single permission of a panel asks first, naming the panel', confirms.length === 1 && confirms[0].indexOf( ELEMENTS ) !== -1 && confirms[0] === Nino.adminUi.format( text('/_admin/roles/scope/warning'), ELEMENTS ) );
check( '...and adds nothing when the answer is no', parts.perms().join() === '/_admin/elements/manage' && tickOf( parts, '/_admin/elements/services/update/title' ) === null );

confirmAnswer = true;
fire( addButton( parts ), 'click' );
check( 'with a yes it is added', parts.perms().indexOf( '/_admin/elements/services/update/title' ) !== -1 && confirms.length === 2 );
check( '...and sits ticked in the list of its own, under its place in the tree', tickOf( parts, '/_admin/elements/services/update/title' ).checked === true
	&& nameOfTick( parts, '/_admin/elements/services/update/title' ) === labels['/_admin/elements/services/update/title'] );

found = lists( parts );
choose( found.field, 2 );
fire( addButton( parts ), 'click' );
check( 'the second single permission of the same panel does not ask again', confirms.length === 2 && parts.perms().indexOf( '/_admin/elements/services/update/body' ) !== -1 );

fire( addButton( parts ), 'click' );
check( 'a permission the role already holds is not added twice', parts.perms().filter( function( p ) { return p === '/_admin/elements/services/update/body' } ).length === 1 && confirms.length === 2
	&& findAll( parts.holder, function( el ) { return el.tagName === 'INPUT' && el.value === '/_admin/elements/services/update/body' } ).length === 1 );

// Another panel is another transition
choose( lists( parts ).area, 1 );
choose( lists( parts ).action, 0 );
fire( addButton( parts ), 'click' );
check( 'a single permission of another panel asks again, naming that one', confirms.length === 3 && confirms[2] === Nino.adminUi.format( text('/_admin/roles/scope/warning'), TEXT ) );

// Taking the last single permission of a panel away is the transition back
// to everything: the tick of the list of its own
const DOOR = '/_admin/elements/manage';
const INSERT = '/_admin/elements/services/insert';
const TITLE = '/_admin/elements/services/update/title';

parts = draw( [ DOOR, INSERT, TITLE ] );
confirms.length = 0;
confirmAnswer = false;
tick( parts, INSERT, false );
check( 'taking one of two single permissions of a panel away does not ask', confirms.length === 0 && parts.perms().join() === [ DOOR, TITLE ].join() );

tick( parts, TITLE, false );
check( 'taking the last one away asks, naming the panel', confirms.length === 1 && confirms[0] === Nino.adminUi.format( text('/_admin/roles/scope/warning-leave'), ELEMENTS ) );
check( '...and No keeps it, with its tick back where it was', parts.perms().join() === [ DOOR, TITLE ].join() && tickOf( parts, TITLE ).checked === true );

confirmAnswer = true;
tick( parts, TITLE, false );
check( 'Yes takes it away', confirms.length === 2 && parts.perms().join() === DOOR && tickOf( parts, TITLE ).checked === false );
tick( parts, TITLE, true );
check( '...and the tick is still there to take it back, which asks the other way', confirms.length === 3 && parts.perms().join() === [ DOOR, TITLE ].join() );

// A permission that is held but not offered any more, and is in the tree
parts = draw( [ '/_admin/text/update/page-home/hero/title', '/_admin/old/thing' ] );
check( 'a held scoped permission is named by the tree, one outside it stands in the list of the permissions nobody offers', nameOfTick( parts, '/_admin/text/update/page-home/hero/title' ) === labels['/_admin/text/update/page-home/hero/title']
	&& tickOf( parts, '/_admin/old/thing' ).checked === true && findAll( parts.holder, function( el ) { return el.dataset.group === 'other' } )[0].children.some( function( child ) { return child.children.length > 0 && findAll( child, function( el ) { return el === tickOf( parts, '/_admin/old/thing' ) } ).length === 1 } ) );
// A role is saved with what it holds and drawn again from what the server then lists: the permissions it carries stay where they stood
const SCOPED = '/_admin/text/update/page-home/hero/title';
parts = draw( [ SCOPED, '/_admin/old/thing' ] );
const placed = function( parts, perm ) {
	const tick = tickOf( parts, perm );
	const set = tick === null ? undefined : findAll( parts.holder, function( el ) { return el.tagName === 'FIELDSET' && ( el.dataset.group !== undefined || el.id === 'roles-permissions-scoped' ) && findAll( el, function( inner ) { return inner === tick } ).length === 1 } )[0];
	return set === undefined ? '' : set.dataset.group ?? set.id;
};
check( 'what the role carries and no panel offers is in the list of those nobody offers, and what it does not carry is not; a scoped one the tree names is in the list of the added ones, as it was added', placed( parts, '/_admin/old/thing' ) === 'other' && tickOf( parts, '/_admin/other/thing' ) === null && placed( parts, SCOPED ) === 'roles-permissions-scoped' );
check( '...saved without a change, the role holds what it held, in the order it held it', parts.perms().join() === [ SCOPED, '/_admin/old/thing' ].join() );
check( 'a role that holds a single permission says what it may do in that panel, in the summary', summary( parts ).some( function( line ) { return line.indexOf( TEXT ) !== -1 } ) );

// The order is the server's, not a copy of it: a group the registry adds
// reaches the form without a change here
roles._groups = [ 'other', 'content' ];
parts = draw( [ '/_admin/old/thing' ] );
check( 'the lists follow the order roles/list names the groups in', findAll( parts.holder, function( el ) { return el.dataset.group !== undefined } ).map( function( el ) { return el.dataset.group } ).join() === 'other,content' );
roles._groups = [ 'content', 'structure', 'features', 'system', 'other' ];

console.log('\nAdmin Roles - what the role may do');

function say( perms ) {
	return roles.summarize( perms, OPTIONS, SCOPES );
}

check( 'full access', say( [ '/*' ] ).join('|') === text('/_admin/roles/summary/full') );
check( 'nothing at all', say( [] ).join('|') === text('/_admin/roles/summary/none') );
check( 'a door opens its area', say( [ '/_admin/elements/manage' ] ).join('|') === Nino.adminUi.format( text('/_admin/roles/summary/doors'), ELEMENTS ) );
check( 'a blanket opens the doors it covers', say( [ '/_admin/*' ] ).join('|') === Nino.adminUi.format( text('/_admin/roles/summary/doors'), ELEMENTS+ ', '+ TEXT ) );

const detail = say( [ '/_admin/elements/manage', '/_admin/elements/services/insert', '/_admin/elements/services/update/title' ] );
check( 'a panel in detail mode says what is allowed there, per action and field', detail.length === 2
	&& detail[1] === Nino.adminUi.format( text('/_admin/roles/summary/detail'), ELEMENTS,
		Nino.adminUi.format( text('/_admin/roles/summary/action'), 'Services', text('/_admin/elements/scope/insert') )+ '; '
		+ Nino.adminUi.format( text('/_admin/roles/summary/fields'), 'Services', text('/_admin/elements/scope/update'), 'title' ) ) );

const wide = say( [ '/_admin/elements/manage', '/_admin/elements/services/*' ] );
check( 'a whole type is "everything" of it', wide[1] === Nino.adminUi.format( text('/_admin/roles/summary/detail'), ELEMENTS, Nino.adminUi.format( text('/_admin/roles/summary/area'), 'Services' ) ) );

const noDoor = say( [ '/_admin/text/update/page-home/*' ] );
check( 'single permissions without the door say that the area stays shut', noDoor.length === 2
	&& noDoor[1] === Nino.adminUi.format( text('/_admin/roles/summary/nodoor'), TEXT, '/_admin/text/manage' ) );

const unknown = say( [ '/_admin/elements/manage', '/_admin/old/thing', '/_admin/elements/services/update/surprise' ] );
check( 'what nothing explains is named by its string', unknown.indexOf( Nino.adminUi.format( text('/_admin/roles/summary/unknown'), '/_admin/old/thing' ) ) !== -1
	&& unknown.indexOf( Nino.adminUi.format( text('/_admin/roles/summary/unknown'), '/_admin/elements/services/update/surprise' ) ) !== -1 );

parts = draw( [ '/_admin/elements/manage' ] );
check( 'the form carries the summary and refreshes it on every change', summary( parts ).join('|') === Nino.adminUi.format( text('/_admin/roles/summary/doors'), ELEMENTS ) );
tick( parts, '/_admin/text/manage', true );
check( '...a tick is a change of it', summary( parts ).join('|') === Nino.adminUi.format( text('/_admin/roles/summary/doors'), ELEMENTS+ ', '+ TEXT ) );
parts.fullCheck.checked = true;
fire( parts.fullCheck, 'change' );
check( '...and with full access it says so, and the lists are not shown', summary( parts ).join('|') === text('/_admin/roles/summary/full')
	&& findAll( parts.holder, function( el ) { return el.dataset.group !== undefined } ).every( function( set ) { return set.classList.contains('admin-hidden') } ) );


console.log('\nAdmin Roles - the drill-down');

/*	A row opens a role and writes its address, so it is a link with that address.
	What is opened takes the focus into the form, Back gives it to the row - found
	again where the list was drawn anew behind the form - and a delete leaves it
	nowhere to stand	*/
{
	// A text node has no class list
	const hasClass = function( el, name ) { return typeof el.className === 'string' && el.className.split(/\s+/).indexOf( name ) !== -1 };

	// The pane the shell draws: the name of the panel in its head, and the two levels beside it
	const pane = element('div');
	pane.dataset.panel = 'roles';
	const head = element('div');
	head.className = 'admin-panel-head';
	const paneTitle = element('h2');
	paneTitle.className = 'admin-panel-title';
	head.appendChild( paneTitle );
	pane.appendChild( head );
	const list = element('div');
	list.id = 'roles-list';
	pane.appendChild( list );
	const form = element('div');
	form.id = 'roles-form';
	form.classList.add('admin-hidden');
	pane.appendChild( form );

	// The panel writes through the router's own codec: every state it writes is read back
	const codec = shellRouter();
	const sent = [];
	const shell = {
		router : codec.stub( { current : function() { return { panel : 'roles', parts : [] } } } ),
	};
	const page = { admin : shell, events : { bindCallback : function() {} }, http : { sendRequest : function( uri, method, callback, data ) { sent.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } ) } }, content : { getText : text } };
	const box = {
		console : console,
		document : {
			createElement : element,
			createElementNS : function( space, tag ) { return element( tag ) },
			createTextNode : function( value ) { return { tagName : '#TEXT', textContent : value, attributes : {}, dataset : {}, children : [] } },
			getElementById : function( id ) { return findAll( pane, function( el ) { return el.id === id } )[0] ?? null },
			documentElement : null,
			body : null,
			get activeElement() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null },
		},
		Nino : page,
	};
	let deleteAnswer = true;
	box.window = { Nino : page, confirm : function() { return deleteAnswer } };
	const drillContext = vm.createContext( box );
	load( '_admin/assets/Nino.admin.js', drillContext );

	const toasts = [];
	page.adminUi.say = function( words ) { toasts.push( words ) };
	load( '_admin/Nino/Modules/Users/assets/roles.js', drillContext );

	const drilled = page.admin.roles;
	drilled._roles = [ { id : 'editor', label : 'Editor', users : 1, perms : [ '/_admin/elements/manage' ] }, { id : 'author', label : 'Author', users : 0, perms : [] } ];
	drilled._scopes = SCOPES;
	drilled._permOptions = OPTIONS;
	drilled._groups = [ 'content', 'other' ];
	drilled._renderList();
	drilled._showList();

	const linkOf = function( id ) {
		const row = findAll( list, function( el ) { return el.tagName === 'LI' } ).find( function( li ) { return li.dataset.row === id } );
		return row === undefined ? undefined : findAll( row, function( el ) { return el.tagName === 'A' } )[0];
	};
	const focusedNow = function() { return findAll( pane, function( el ) { return el.focused === true } )[0] ?? null };
	const backLinkOf = function() { return findAll( form, function( el ) { return el.className === 'nino-admin-back-link' } )[0] };
	const byId = function( id ) { return findAll( pane, function( el ) { return el.id === id } )[0] ?? null };
	const errorsOf = function() { return findAll( form, function( el ) { return hasClass( el, 'nino-admin-field-error' ) } ) };

	check( 'a row is a link with the address of the role it opens', linkOf('editor').href === '#users/roles/editor' && linkOf('author').href === '#users/roles/author' );

	fire( linkOf('editor'), 'click' );
	check( 'opening a role shows its form and moves the focus into it', form.classList.contains('admin-hidden') === false && list.classList.contains('admin-hidden') === true && backLinkOf() !== undefined && focusedNow() === backLinkOf() );

	drilled._renderList();
	fire( backLinkOf(), 'click' );
	check( 'Back shows the list and puts the focus on the row of the role - the one drawn now, not the one that was pressed', list.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true && focusedNow() === linkOf('editor') );

	const addButton = findAll( list, function( el ) { return el.className === 'nino-admin-btn-primary' } )[0];
	fire( addButton, 'click' );
	check( 'a new role opens the same way', form.classList.contains('admin-hidden') === false && focusedNow() === backLinkOf() );

	// The form checks nothing by itself: what is missing and what is no id is said under the field
	const edit = byId('roles-edit-form');
	check( 'the form is novalidate, and its two fields are required with their star', edit.noValidate === true && byId('roles-form-id').required === true && byId('roles-form-name').required === true );
	fire( edit, 'submit' );
	check( 'an empty form sends nothing, says under the id and the name that they are missing, and the focus goes to the first', sent.length === 0 && errorsOf().length === 2 && byId('roles-form-id').attributes['aria-invalid'] === 'true' && byId('roles-form-id').focused === true );
	byId('roles-form-id').value = 'Not An Id';
	byId('roles-form-name').value = 'Newcomer';
	fire( edit, 'submit' );
	check( 'an id that is no id sends nothing and says what is expected, the sentence of the name is gone', sent.length === 0 && errorsOf().length === 1 );

	// Save: what goes out is the id, the name and the ticked permissions; the answer opens the role and says it
	// A new role takes no id a role holds: it would be written over
	byId('roles-form-id').value = 'editor';
	fire( edit, 'submit' );
	check( 'an id that a role holds sends nothing and is said to be taken under the field', sent.length === 0 && errorsOf().length === 1 && byId('roles-form-id').attributes['aria-invalid'] === 'true' );

	// Full access is sent as the one permission it is, whatever the lists hold
	byId('roles-form-id').value = 'newcomer';
	tick( { holder : form }, '/_admin/text/manage', true );
	drilled._parts.permissions.fullCheck.checked = true;
	fire( drilled._parts.permissions.fullCheck, 'change' );
	fire( edit, 'submit' );
	check( 'with full access on the form sends the one permission \'/*\' and nothing of the ticks', sent.length === 1 && sent[0].payload.perms.join() === '/*' );
	sent.length = 0;
	drilled._parts.permissions.fullCheck.checked = false;
	fire( drilled._parts.permissions.fullCheck, 'change' );

	fire( edit, 'submit' );
	check( 'a good form sends roles/save with the id, the name and what is ticked', sent.length === 1 && sent[0].action === 'roles/save' && sent[0].payload.create === true && sent[0].payload.id === 'newcomer' && sent[0].payload.label === 'Newcomer' && sent[0].payload.perms.join() === '/_admin/text/manage' && errorsOf().length === 0 );
	sent[0].callback( { status : 200, responseJSON : { id : 'newcomer' } } );
	drilled._roles.push( { id : 'newcomer', label : 'Newcomer', users : 0, perms : [ '/_admin/text/manage' ] } );
	sent[1].callback( { status : 200, responseJSON : { roles : drilled._roles, permOptions : OPTIONS, groups : [ 'content', 'other' ], scopes : SCOPES } } );
	check( 'the saved role is the one on the form, drawn from what the server holds, and saving it is said as a toast and by the badge, in no line', byId('roles-form-id').disabled === true && byId('roles-form-id').value === 'newcomer'
		&& toasts.length === 1 && toasts[0] === text('/_admin/common/msg/saved') && byId('roles-form-msg').textContent === '' );

	// The server's refusal of a taken id is said under the id as well
	fire( byId('roles-edit-form'), 'submit' );
	sent[sent.length - 1].callback( { status : 409, responseJSON : { error : 'taken', code : 'roles_id_taken', field : 'id' } } );
	check( 'a refusal for an id that is taken is said under the id', errorsOf().length === 1 && byId('roles-form-id').attributes['aria-invalid'] === 'true' );
	sent[sent.length - 1].callback = function() {};

	// A refusal that names the id is said under it
	fire( byId('roles-edit-form'), 'submit' );
	sent[sent.length - 1].callback( { status : 400, responseJSON : { error : 'invalid id', code : 'roles_invalid_id' } } );
	check( 'a refusal of the server is said in the line of the bar, and - for the id - under the field', byId('roles-form-msg').dataset.state === 'error' && errorsOf().length === 1 );

	fire( backLinkOf(), 'click' );
	check( '...and Back returns to the row of the role', focusedNow() === linkOf('newcomer') && list.classList.contains('admin-hidden') === false );
	const addAgain = findAll( list, function( el ) { return el.className === 'nino-admin-btn-primary' } )[0];
	fire( addAgain, 'click' );
	fire( backLinkOf(), 'click' );
	check( '...and, from a new one, to the New role button as the list is drawn now', focusedNow() === addAgain && list.classList.contains('admin-hidden') === false );

	// The bar: delete at the left, ONE main button at the right, and a role accounts hold cannot go
	fire( linkOf('editor'), 'click' );
	const bar = findAll( byId('roles-edit-form'), function( el ) { return hasClass( el, 'nino-admin-actionbar' ) } )[0];
	const barButtons = findAll( bar, function( el ) { return el.tagName === 'BUTTON' } );
	check( 'the bar of a form has Delete first, as the one dangerous button, and one main button, the submit, last', barButtons.length === 2 && barButtons[0].classList.contains('nino-admin-btn-danger') && barButtons[1].classList.contains('nino-admin-btn-primary') && barButtons[1].type === 'submit' );
	check( '...and the Delete of a role that accounts hold is off, with its reason as its title', barButtons[0].disabled === true && barButtons[0].title === text('/_admin/roles/label/delete-hint') );

	fire( linkOf('author'), 'click' );
	byId('roles-form-name').focus();
	deleteAnswer = false;
	const sentBefore = sent.length;
	drilled._delete();
	check( 'a role delete that is answered No sends nothing and leaves the form', sent.length === sentBefore && list.classList.contains('admin-hidden') === true );
	deleteAnswer = true;
	drilled._delete();
	check( '...and a Yes sends it', sent.length === sentBefore + 1 && sent[sent.length - 1].action === 'roles/delete' );
	toasts.length = 0;
	sent[sent.length - 1].callback( { status : 200, responseJSON : {} } );
	check( 'a deleted role leaves the focus nothing to stand on in the form: it goes to the name of the panel, and the deletion is a toast', list.classList.contains('admin-hidden') === false && focusedNow() === paneTitle
		&& toasts.length === 1 && toasts[0] === text('/_admin/common/msg/deleted') );

	// What a role is called is only ever text
	drilled._roles.push( { id : 'markup', label : '<img src=x onerror=1>', users : 0, perms : [] } );
	drilled._renderList();
	drilled._showList();
	fire( linkOf('markup'), 'click' );
	const roleTitle = findAll( form, function( el ) { return el.tagName === 'LEGEND' } )[0];
	check( 'the title of a role\'s form and its row are its name as text, whatever it holds', roleTitle.textContent === '<img src=x onerror=1>' && roleTitle.children.length === 0
		&& findAll( list, function( el ) { return el.tagName === 'STRONG' && el.textContent === '<img src=x onerror=1>' && el.children.length === 0 } ).length === 1 );
	drilled._showList();

	// A role that holds what the signed-in account does not is shown, not
	// edited: no field to type in, no button, what it may do in words, and why
	drilled._roles.push( { id : 'wide', label : 'Wide', users : 2, perms : [ '/*' ], canChange : false } );
	drilled._renderList();
	drilled._showList();
	fire( linkOf('wide'), 'click' );
	check( 'a role the account may not change has nothing to type in and no button in its form', findAll( form, function( el ) { return el.tagName === 'INPUT' && el.readOnly !== true || el.tagName === 'BUTTON' || el.tagName === 'SELECT' } ).length === 0
		&& findAll( form, function( el ) { return el.id === 'roles-form-name' } ).length === 0 );
	check( '...it says what the role may do and why it is not editable', findAll( form, function( el ) { return el.tagName === 'LI' } ).map( function( el ) { return el.textContent } ).join('|') === text('/_admin/roles/summary/full')
		&& findAll( form, function( el ) { return hasClass( el, 'nino-admin-notice' ) && el.textContent === text('/_admin/roles/label/beyond') } ).length === 1 );
	drilled._showList();
	fire( linkOf('editor'), 'click' );
	drilled._roles.push( { id : 'carried', label : 'Carried', users : 1, perms : [ '/_admin/text/manage' ], canChange : false, heldBeyond : true } );
	drilled._renderList();
	drilled._showList();
	fire( linkOf('carried'), 'click' );
	check( 'a role that is beyond only through an account that holds it says so, and not that the role holds the permission', findAll( form, function( el ) { return el.textContent === text('/_admin/roles/label/held-beyond') } ).length === 1
		&& findAll( form, function( el ) { return el.textContent === text('/_admin/roles/label/beyond') } ).length === 0 );
	drilled._showList();
	fire( linkOf('wide'), 'click' );
	check( '...and the one that holds it itself says that', findAll( form, function( el ) { return el.textContent === text('/_admin/roles/label/beyond') } ).length === 1 );
	drilled._showList();
	fire( linkOf('editor'), 'click' );
	check( '...and a role that may be changed keeps its form', byId('roles-form-name') !== null && byId('roles-form-name').readOnly !== true && findAll( form, function( el ) { return el.tagName === 'BUTTON' } ).length > 0 );
	// A role whose id reads @new and one called new: the word @new is the form that makes a role, %40new the key of the one that is called that.
	// What the panel writes is read back by the router, and the panel opens what the router read
	drilled._roles.push( { id : '@new', label : 'At new', users : 0, perms : [], canChange : true }, { id : 'new', label : 'New', users : 0, perms : [], canChange : true } );
	drilled._renderList();
	drilled._showList();
	const hashOf = id => linkOf( id ).href;
	check( 'the row of the role @new is a link to %40new and the row of the role new a link to new: two roles, two addresses, and the form that makes one is a third', hashOf('@new') === '#users/roles/%40new' && hashOf('new') === '#users/roles/new' && codec.href( 'roles', [ codec.word('new') ] ) === '#users/roles/@new' );
	const follow = hash => { drilled._showList(); const read = codec.read( hash ); drilled._follow( read.parts ); return read };
	follow( hashOf('@new') );
	check( '...%40new, read by the router, opens the role @new and no form that makes one', drilled._current !== null && drilled._current.id === '@new' );
	follow( hashOf('new') );
	check( '...new opens the role called new', drilled._current !== null && drilled._current.id === 'new' );
	follow( '#users/roles/@new' );
	check( '...@new, the word, opens the form that makes a role, and no role', drilled._current === null && form.classList.contains('admin-hidden') === false );
	drilled._showList();
	fire( linkOf('@new'), 'click' );
	check( '...and a click on the row of the role @new writes the address that opens that role again: the panel is on the role, and what it wrote is the key', drilled._current !== null && drilled._current.id === '@new' && codec.written.at(-1).parts.length === 1 && typeof codec.written.at(-1).parts[0] === 'string' );
	drilled._showList();
	drilled._roles.splice( -2 );
	drilled._renderList();
	drilled._showList();

	check( 'every address the panel wrote or drew - the rows, a new role, a role - is read back by the router as the same screen and the same parts', codec.written.length > 3 && codec.mismatches.length === 0
		&& codec.written.some( written => written.screen === 'roles' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@new' ) ) && codec.written.some( written => written.screen === 'roles' && written.parts[0] === 'editor' ) );
}

check( 'the words of the refusals the server names by code are worded in English and in German', [ 'en_US', 'de_DE' ].every( function( locale ) {
	const words = fills('_admin/Nino/Modules/Users/text/'+ locale+ '.php');
	return [ '/_admin/error/roles_role_beyond', '/_admin/error/roles_role_beyond_scoped', '/_admin/error/roles_role_held_beyond', '/_admin/error/roles_role_held_beyond_scoped' ].every( function( key ) { return typeof words[key] === 'string' && words[key] !== '' } );
} ) );

done();
