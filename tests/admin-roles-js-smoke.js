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
 *													only ever add a string out of the tree.
 *
 *	Usage: node tests/admin-roles-js-smoke.js
 */

'use strict';

const fs 		= require('fs');
const path	= require('path');
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

function source( relative ) {
	return fs.readFileSync( path.join( __dirname, '..', relative ), 'utf8' );
}

/** The fills of one text file, key => value - enough of the php to read a `'[[/key]]' => 'value',` line */
function fills( relative ) {
	const map = {};
	const re = /'\[\[(\/_admin\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g;
	const text = source( relative );
	let match;
	while( ( match = re.exec( text ) ) )
		map[match[1]] = match[2].replace( /\\'/g, "'" );
	return map;
}


// --- an element stand-in: enough tree to find things in -------------------

function classList( el ) {
	const values = new Set();
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
		toggle : function( value, force ) { ( force === undefined ? values.has( value ) === false : force ) ? values.add( value ) : values.delete( value ) },
	};
}

function matches( el, selector ) {
	return selector.split(',').some( function( part ) {
		part = part.trim();
		const attribute = /^\[([a-z-]+)="([^"]*)"\]$/.exec( part );
		if( attribute !== null )
			return el.attributes[attribute[1]] === attribute[2] || ( attribute[1].indexOf('data-') === 0 && el.dataset[attribute[1].slice(5)] === attribute[2] );
		if( part.charAt(0) === '#' )
			return el.id === part.slice(1);
		return el.tagName === part.toUpperCase();
	} );
}

function findAll( root, predicate ) {
	const found = [];
	( function walk( node ) {
		( node.children || [] ).forEach( function( child ) {
			if( predicate( child ) === true )
				found.push( child );
			walk( child );
		} );
	} )( root );
	return found;
}

function element( tag ) {
	const el = {
		tagName : String( tag ).toUpperCase(),
		className : '',
		textContent : '',
		type : '',
		id : '',
		value : '',
		disabled : false,
		style : {},
		dataset : {},
		attributes : {},
		children : [],
		listeners : {},
		parentNode : null,
		appendChild : function( child ) { child.parentNode = el; el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ); if( name === 'id' ) el.id = String( value ) },
		removeAttribute : function( name ) { delete el.attributes[name] },
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
		querySelector : function( selector ) { return findAll( el, function( node ) { return matches( node, selector ) } )[0] ?? null },
		querySelectorAll : function( selector ) { return findAll( el, function( node ) { return matches( node, selector ) } ) },
		closest : function() { return null },
		focus : function() {},
		replaceWith : function( other ) {
			const at = el.parentNode.children.indexOf( el );
			el.parentNode.children[at] = other;
			other.parentNode = el.parentNode;
		},
	};
	el.classList = classList( el );
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

function fire( el, name ) {
	if( el === null || el === undefined )
		return;
	( el.listeners[name] || [] ).forEach( function( fn ) { fn( { preventDefault : function() {} } ) } );
}


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
		createTextNode : function( value ) { return { tagName : '#TEXT', textContent : value, attributes : {}, dataset : {}, children : [] } },
		getElementById : function() { return null },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, confirm : function( message ) { confirms.push( message ); return confirmAnswer } };

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );

// The multi-reference picker has its own test: here it is a stand-in that
// keeps what it was given
const pickers = [];
Nino.adminUi.elementList = function( options ) {
	const picker = element('div');
	picker.options = options;
	pickers.push( picker );
	return picker;
};

vm.runInContext( source('_admin/Nino/Modules/Users/assets/roles.js'), context, { filename : 'roles.js' } );

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
				{ id : '/page-home/atf/title', label : '/page-home/atf/title', perm : '/_admin/text/update/page-home/atf/title' },
			] },
		] },
	] },
];

const OPTIONS = [
	{ perm : '/_admin/elements/manage', label : '/_admin/nav/elements', group : 'content', offered : true },
	{ perm : '/_admin/text/manage', label : '/_admin/nav/text', group : 'content', offered : true },
	{ perm : '/_admin/old/thing', label : '/_admin/old/thing', group : 'other', offered : false },
];

// The Roles tab only reads these two when it draws
roles._scopes = SCOPES;
roles._permOptions = OPTIONS;

const ELEMENTS = text('/_admin/nav/elements');
const TEXT = text('/_admin/nav/text');


console.log('Admin Roles - which string covers which');

check( 'the words the checks below rely on exist', ELEMENTS !== '' && TEXT !== '' && text('/_admin/roles/summary/title') !== '' );

check( 'a blanket over a panel covers its door', roles.covers( [ '/_admin/elements/*' ], '/_admin/elements/manage' ) === true );
check( '/_admin/* covers the door of every panel', roles.covers( [ '/_admin/*' ], '/_admin/elements/manage' ) === true );
check( 'the string itself covers itself', roles.covers( [ '/_admin/elements/manage' ], '/_admin/elements/manage' ) === true );
check( 'full access covers anything', roles.covers( [ '/*' ], '/_admin/text/update/page-home/atf/title' ) === true );
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

console.log('\nAdmin Roles - the picker and the warning');

function draw( perms ) {
	pickers.length = 0;
	return roles._renderPermissions( perms );
}

function lists( parts ) {
	const select = function( key ) { return parts.fieldset.querySelector('[data-key="'+ key+ '"]') };
	return { area : select('scope-area'), action : select('scope-action'), field : select('scope-field') };
}

function choose( select, index ) {
	select.value = String( index );
	fire( select, 'change' );
}

function addButton( parts ) {
	return findAll( parts.fieldset, function( el ) { return el.tagName === 'BUTTON' && el.textContent === text('/_admin/roles/scope/add') } )[0];
}

function summary( parts ) {
	return findAll( parts.fieldset, function( el ) { return el.tagName === 'LI' } ).map( function( li ) { return li.textContent } );
}

let parts = draw( [ '/_admin/elements/manage' ] );
let found = lists( parts );

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
choose( found.field, 1 );
confirmAnswer = false;
fire( addButton( parts ), 'click' );
check( 'the first single permission of a panel asks first, naming the panel', confirms.length === 1 && confirms[0].indexOf( ELEMENTS ) !== -1 && confirms[0] === Nino.adminUi.format( text('/_admin/roles/scope/warning'), ELEMENTS ) );
check( '...and adds nothing when the answer is no', parts.perms().join() === '/_admin/elements/manage' );

confirmAnswer = true;
fire( addButton( parts ), 'click' );
check( 'with a yes it is added', parts.perms().indexOf( '/_admin/elements/services/update/title' ) !== -1 && confirms.length === 2 );
check( '...and sits in the picker under its place in the tree', pickers[pickers.length - 1].options.options.some( function( o ) {
	return o.value === '/_admin/elements/services/update/title' && o.label === labels['/_admin/elements/services/update/title'];
} ) );

found = lists( parts );
choose( found.field, 2 );
fire( addButton( parts ), 'click' );
check( 'the second single permission of the same panel does not ask again', confirms.length === 2 && parts.perms().indexOf( '/_admin/elements/services/update/body' ) !== -1 );

fire( addButton( parts ), 'click' );
check( 'a permission the role already holds is not added twice', parts.perms().filter( function( p ) { return p === '/_admin/elements/services/update/body' } ).length === 1 && confirms.length === 2 );

// Another panel is another transition
choose( lists( parts ).area, 1 );
choose( lists( parts ).action, 0 );
fire( addButton( parts ), 'click' );
check( 'a single permission of another panel asks again, naming that one', confirms.length === 3 && confirms[2] === Nino.adminUi.format( text('/_admin/roles/scope/warning'), TEXT ) );

// Taking the last single permission of a panel away is the transition back
// to everything. The picker's ✕ reports the list it has left
const DOOR = '/_admin/elements/manage';
const INSERT = '/_admin/elements/services/insert';
const TITLE = '/_admin/elements/services/update/title';
const lastPicker = function() { return pickers[pickers.length - 1] };

parts = draw( [ DOOR, INSERT, TITLE ] );
confirms.length = 0;
confirmAnswer = false;
lastPicker().options.onChange( [ DOOR, INSERT ] );
check( 'removing one of two single permissions of a panel does not ask', confirms.length === 0 && parts.perms().join() === [ DOOR, INSERT ].join() );

const drawn = pickers.length;
lastPicker().options.onChange( [ DOOR ] );
check( 'removing the last one asks, naming the panel', confirms.length === 1 && confirms[0] === Nino.adminUi.format( text('/_admin/roles/scope/warning-leave'), ELEMENTS ) );
check( '...and No keeps it', parts.perms().join() === [ DOOR, INSERT ].join() );
check( '...with the picker drawn again from what the role holds', pickers.length === drawn + 1 && lastPicker().options.value.join() === [ DOOR, INSERT ].join() );

confirmAnswer = true;
lastPicker().options.onChange( [ DOOR ] );
check( 'Yes removes it', confirms.length === 2 && parts.perms().join() === DOOR );

lastPicker().options.onChange( [] );
check( 'taking away a permission that is not a single one of a panel does not ask', confirms.length === 2 && parts.perms().length === 0 );

// A permission that is held but not offered any more, and is in the tree
parts = draw( [ '/_admin/text/update/page-home/atf/title', '/_admin/old/thing' ] );
const options = pickers[pickers.length - 1].options.options;
check( 'a held scoped permission is named by the tree, one outside it by its string', options.some( function( o ) { return o.value === '/_admin/text/update/page-home/atf/title' && o.label === labels['/_admin/text/update/page-home/atf/title'] } )
	&& options.some( function( o ) { return o.value === '/_admin/old/thing' && o.label === text('/_admin/roles/group/other')+ ' · /_admin/old/thing' } ) );
check( 'a role that holds a single permission names its panel under the picker', findAll( parts.fieldset, function( el ) { return el.textContent === Nino.adminUi.format( text('/_admin/roles/scope/detail-hint'), TEXT ) } ).length === 1 );

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
check( 'the form carries the summary under its title and refreshes it on every change', summary( parts ).join('|') === Nino.adminUi.format( text('/_admin/roles/summary/doors'), ELEMENTS ) );
parts.fullCheck.checked = true;
fire( parts.fullCheck, 'change' );
check( '...and with full access it says so', summary( parts ).join('|') === text('/_admin/roles/summary/full') );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
