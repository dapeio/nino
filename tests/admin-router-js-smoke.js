/**
 *	Nino									A compact filesystembased php framework
 *	admin-router-js-smoke.js	DOM-free checks for the workbench's history: what
 *										the router writes into it (a person's own move adds
 *										an entry, a state sync replaces one), and how the
 *										drill-down panels - Text, Users, Roles; Images and
 *										Elements have their own tests - follow the hash a
 *										step through it leaves behind.
 *
 *	Usage: node tests/admin-router-js-smoke.js
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

const read = relative => fs.readFileSync( path.join( __dirname, '..', relative ), 'utf8' );

const classes = hidden => {
	const held = { 'admin-hidden' : hidden };
	return { add : k => held[k] = true, remove : k => held[k] = false, contains : k => held[k] === true, toggle(){} };
};


// --- the router ---------------------------------------------------------------------

console.log( 'Router' );
{
	const pageClasses = classes( false );
	const entries = [ '#a' ];
	let at = 0;
	const box = {
		console : console,
		location : { hash : '#a' },
		history : {
			pushState : ( state, title, url ) => { entries.splice( at + 1 ); entries.push( url ); at++; box.location.hash = url },
			replaceState : ( state, title, url ) => { entries[at] = url; box.location.hash = url },
		},
		document : { documentElement : null, body : null, getElementById : id => id === 'admin-page-wrap' ? { classList : pageClasses } : ( [ 'admin-content-images', 'admin-content-users', 'admin-tab-roles' ].indexOf( id ) !== -1 ? {} : null ) },
	};
	box.window = box;
	box.Nino = { events : { bindCallback(){} } };
	vm.runInContext( read('_admin/assets/script.js'), vm.createContext( box ), { filename : 'script.js' } );
	const router = box.Nino.admin.router;
	const screen = name => { pageClasses.contains = k => k === 'show-'+ name };

	screen( 'images' );
	router.set( 'images', [ 'home' ] );
	check( 'set() replaces the entry it is on', entries.join() === '#images/home' && at === 0 );
	router.set( 'images', [ 'home' ] );
	check( '...and writes nothing the address already says', entries.length === 1 );

	router._push = true;
	router.set( 'images', [ 'team' ] );
	check( 'while the shell flags the switch as the person\'s own, the first write adds an entry instead', entries.join() === '#images/home,#images/team' && at === 1 );
	check( '...once: the flag is spent', router._push === false );
	router.set( 'images', [] );
	check( 'the next write replaces again', entries.join() === '#images/home,#images' && at === 1 );

	router.go( 'images', [ 'team' ] );
	check( 'go() adds an entry', entries.join() === '#images/home,#images,#images/team' && at === 2 );
	router.go( 'images', [ 'team' ] );
	check( '...but not for an address the bar already shows: a level followed from the hash adds nothing', entries.length === 3 );
	router.set( 'images', [ 'team' ] );
	check( '...and the write that follows it finds the address true', entries.length === 3 );

	router.go( 'users', [ 'ada@example.com' ] );
	router.set( 'users', [] );
	check( 'a panel that is not on screen writes nothing, by either way', entries.length === 3 && box.location.hash === '#images/team' );

	router.go( 'images', [ 'a b', 'c/d' ] );
	check( 'the parts are encoded', box.location.hash === '#images/a%20b/c%2Fd' );

	// leave(): the question before a form is left
	const guarded = [];
	box.Nino.admin.dirty = { guard : ( names, proceed, cancel ) => guarded.push( { names, proceed, cancel } ) };
	let shown = 0;
	router.leave( [ 'x' ], false, () => shown++, () => {} );
	check( 'leave() goes straight on where no form is left', shown === 1 && guarded.length === 0 );
	const resync = () => {};
	router.leave( [ 'x' ], true, () => shown++, resync );
	check( '...and asks through the shell where one is, a Cancel being the resync', shown === 1 && guarded.length === 1 && guarded[0].cancel === resync && JSON.stringify( guarded[0].names ) === '["x"]' );
	delete box.Nino.admin.dirty;
	router.leave( [ 'x' ], true, () => shown++, resync );
	check( '...and goes on in a shell that has no registry', shown === 2 );

	// A Save that failed brings its form on screen through the shell: that must not be
	// answered by moving on to the level the address names, and so by asking again
	let kept = 0;
	router._keep = true;
	router.leave( [ 'x' ], true, () => shown++, () => kept++ );
	router._keep = false;
	check( 'while the shell brings a panel on screen as it stands, leave() keeps the level in memory and asks nothing', kept === 1 && shown === 2 );
}


// --- the drill-down panels follow the hash ----------------------------------------------

/**
 *	One panel in a context of its own, its levels stubbed: what is checked is
 *	which level it asks for, and whether the shell is asked to put a question
 *	in front of leaving a form
 *
 *	@param		{string}	file				The panel script
 *	@param		{Object}	nodes				id -> { classList }
 *	@param		{string}	name				The panel's name in Nino.admin
 *	@param		{Array}		levels			The functions that show a level
 *
 *	@return		{Object}
 */
function panel( file, nodes, name, levels, before = [] ) {

	const calls = [];
	const asked = [];
	const hash = { panel : '', parts : [] };

	const box = { console : console, document : { getElementById : id => nodes[id] || null, querySelectorAll : () => [], documentElement : {}, body : {} } };
	box.window = box;
	box.Nino = {
		editor : {},
		events : { bindCallback(){} },
		content : { getText : key => key },
		admin : {
			router : {
				set : ( p, parts ) => calls.push( 'set '+ [ p ].concat( parts ).join('/') ),
				go : ( p, parts ) => calls.push( 'go '+ [ p ].concat( parts ).join('/') ),
				current : () => hash,
				leave : ( names, leaving, proceed, resync ) => { asked.push( { names : names, leaving : leaving, resync : resync } ); proceed() },
			},
			sessionLocale : { current : 'de_DE', init(){} },
		},
		adminUi : {},
	};

	const context = vm.createContext( box );
	vm.runInContext( read('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
	before.forEach( extra => vm.runInContext( read( extra ), context, { filename : extra } ) );
	vm.runInContext( read( file ), context, { filename : file } );

	const module = box.Nino.admin[name];
	levels.forEach( level => { module[level] = ( ...args ) => calls.push( level.replace( /^_/, '' )+ ( args.length > 0 ? ' '+ args.map( String ).join(' ') : '' ) ) } );
	module._ready = true;

	const at = ( panelName, ...parts ) => { hash.panel = panelName; hash.parts = parts; calls.length = 0; asked.length = 0; module.showCurrent() };

	return { module, calls, asked, at, box };
}

console.log( 'Text' );
{
	const nodes = { 'text-form' : { classList : classes( true ) }, 'text-list' : { classList : classes( false ) } };
	const { module, calls, asked, at, box } = panel( '_admin/Nino/Modules/Text/assets/admin.js', nodes, 'text', [ '_showList', '_showForm', '_openGroup', '_destroyHtmlEditors', '_focusField' ], [ '_admin/Nino/Modules/Text/assets/textkeys.js' ] );
	// Two rows of the model - a page's template and the company - and the keys in them
	const entry = key => ( { key : key, global : false, values : { de_DE : 'x' } } );
	module._model = box.Nino.admin.textKeys.build( { entries : [ entry('/template/page-home/welcome/title'), entry('/template/page-footer/links/title'), entry('/project/company/general/name'), entry('/website/contact/uri') ], locale : 'de_DE' } );
	const on = ( form, group ) => { nodes['text-form'].classList = classes( form === false ); nodes['text-list'].classList = classes( form === true ); module._currentGroup = group ?? null };

	on( false );
	at( 'text', 'template', 'page-home' );
	check( 'a hash that names a row opens it from the list', calls.join() === 'openGroup template/page-home null' && asked[0].leaving === false );
	at( 'text', 'template', 'page-home', 'welcome', 'title' );
	check( '...a hash that names a key, the row that holds it, at that key', calls.join() === 'openGroup template/page-home /template/page-home/welcome/title' );
	at( 'text', 'website' );
	check( '...a row of keys somebody made up is a row like any other', calls.join() === 'openGroup website null' );
	on( true, 'template/page-home' );
	at( 'text' );
	check( 'the bare panel is the list, the form torn down like its back link does, asked first - a form is left', calls.join() === 'destroyHtmlEditors,showList' && asked[0].leaving === true && JSON.stringify( asked[0].names ) === '["text"]' );
	at( 'text', 'project', 'company' );
	check( 'another row replaces this one', calls.join() === 'openGroup project/company null' && asked[0].leaving === true );
	at( 'text', 'template', 'page-home' );
	check( 'the row on screen is only written back', calls.join() === 'showForm' && asked.length === 0 );
	at( 'text', 'template', 'page-home', 'welcome', 'title' );
	check( '...whichever key of it the address names, which is the one shown and the one the address keeps', calls.join() === 'focusField /template/page-home/welcome/title,showForm' && asked.length === 0 && module._focusKey === '/template/page-home/welcome/title' );
	at( 'text', 'nope' );
	check( 'a row there is not is the list', calls.join() === 'destroyHtmlEditors,showList' );
	on( false );
	at( 'text' );
	check( 'the list on screen is only written back', calls.join() === 'showList' );
	on( true, 'template/page-home' );
	at( 'images' );
	check( 'another panel\'s hash (a click on the rail) keeps the level in memory', calls.join() === 'showForm' && asked.length === 0 );
}

console.log( 'Text Keys' );
{
	const nodes = { 'keys-form' : { classList : classes( true ) }, 'keys-list' : { classList : classes( false ) } };
	const { module, calls, asked, at, box } = panel( '_admin/Nino/Modules/Text/assets/keys.js', nodes, 'keys', [ '_showList', '_showForm', '_openGroup', '_destroyHtmlEditors' ], [ '_admin/Nino/Modules/Text/assets/textkeys.js' ] );
	const entry = key => ( { key : key, global : false, values : { de_DE : 'x' } } );
	module._model = box.Nino.admin.textKeys.build( { entries : [ entry('/template/page-home/welcome/title'), entry('/project/company/general/name') ], locale : 'de_DE', admin : true } );
	const on = ( form, group, view ) => { nodes['keys-form'].classList = classes( form === false ); nodes['keys-list'].classList = classes( form === true ); module._currentGroup = group ?? null; module._view = view ?? 'group' };

	on( false );
	at( 'keys', 'template', 'page-home', 'welcome', 'title' );
	check( 'a hash that names a key opens the row that holds it, at that key', calls.join() === 'openGroup template/page-home /template/page-home/welcome/title' && asked[0].leaving === false );
	on( true, 'template/page-home' );
	at( 'keys' );
	check( 'the bare tab is the list, asked first - a form is left', calls.join() === 'destroyHtmlEditors,showList' && asked[0].leaving === true && JSON.stringify( asked[0].names ) === '["keys"]' );
	at( 'keys', 'project', 'company' );
	check( 'another row replaces this one', calls.join() === 'openGroup project/company null' && asked[0].leaving === true );
	at( 'keys', 'template', 'page-home' );
	check( 'the row on screen is only written back', calls.join() === 'showForm' && asked.length === 0 );
	on( true, null, 'new' );
	at( 'keys' );
	check( 'the form that creates or renames a key is not in the address: the bare tab leaves it where it is', calls.join() === 'showForm' && asked.length === 0 );
	at( 'keys', 'nope' );
	check( '...and so does a row there is not', calls.join() === 'showForm' && asked.length === 0 );
	on( true, 'template/page-home' );
	at( 'text', 'template', 'page-home' );
	check( 'another panel\'s hash keeps the level in memory', calls.join() === 'showForm' && asked.length === 0 );
}

console.log( 'Users' );
{
	const nodes = { 'users-form' : { classList : classes( true ) }, 'users-list' : { classList : classes( false ) } };
	const { module, calls, asked, at } = panel( '_admin/Nino/Modules/Users/assets/admin.js', nodes, 'users', [ '_showList', '_showForm', '_openUser', '_renderCreateForm' ] );
	module._users = [ { mail : 'ada@example.com' }, { mail : 'bob@example.com' } ];
	module._canManage = true;
	const on = ( form, user ) => { nodes['users-form'].classList = classes( form === false ); nodes['users-list'].classList = classes( form === true ); module._currentUser = user === undefined ? null : module._users.find( u => u.mail === user ) };

	on( false );
	at( 'users', 'bob@example.com' );
	check( 'a hash that names an account opens its form', calls.join() === 'openUser bob@example.com' && asked[0].leaving === false );
	at( 'users', 'new' );
	check( '...\'new\' the form for a new one, where the account may create', calls.join() === 'renderCreateForm' );
	module._canManage = false;
	at( 'users', 'new' );
	check( '...and the list where it may not', calls.join() === 'showList' );
	module._canManage = true;

	on( true, 'ada@example.com' );
	at( 'users' );
	check( 'the bare panel is the list, asked first - a form is left', calls.join() === 'showList' && asked[0].leaving === true && JSON.stringify( asked[0].names ) === '["users"]' );
	at( 'users', 'bob@example.com' );
	check( 'another account replaces this one', calls.join() === 'openUser bob@example.com' && asked[0].leaving === true );
	at( 'users', 'new' );
	check( 'the new-account form replaces an account\'s', calls.join() === 'renderCreateForm' );
	at( 'users', 'ada@example.com' );
	check( 'the account on screen is only written back', calls.join() === 'showForm' && asked.length === 0 );
	on( true );
	at( 'users', 'new' );
	check( 'so is the new-account form', calls.join() === 'showForm' );
	at( 'users', 'nobody@example.com' );
	check( 'an account the list does not hold is the list', calls.join() === 'showList' );
	at( 'dashboard' );
	check( 'another panel\'s hash keeps the level in memory', calls.join() === 'showForm' && asked.length === 0 );
}

console.log( 'Roles' );
{
	const nodes = { 'roles-form' : { classList : classes( true ) }, 'roles-list' : { classList : classes( false ) } };
	const { module, calls, asked, at } = panel( '_admin/Nino/Modules/Users/assets/roles.js', nodes, 'roles', [ '_showList', '_showForm', '_openRole' ] );
	module._roles = [ { id : 'editor' }, { id : 'author' } ];
	const on = ( form, role ) => { nodes['roles-form'].classList = classes( form === false ); nodes['roles-list'].classList = classes( form === true ); module._current = role === undefined ? null : module._roles.find( r => r.id === role ) };

	on( false );
	at( 'roles', 'author' );
	check( 'a hash that names a role opens its form', calls.join() === 'openRole author' && asked[0].leaving === false );
	at( 'roles', 'new' );
	check( '...\'new\' the form for a new one', calls.join() === 'openRole null' );

	on( true, 'editor' );
	at( 'roles' );
	check( 'the bare tab is the list, asked first - a form is left', calls.join() === 'showList' && asked[0].leaving === true && JSON.stringify( asked[0].names ) === '["roles"]' );
	at( 'roles', 'author' );
	check( 'another role replaces this one', calls.join() === 'openRole author' );
	at( 'roles', 'editor' );
	check( 'the role on screen is only written back', calls.join() === 'showForm' && asked.length === 0 );
	on( true );
	at( 'roles', 'new' );
	check( 'so is the new-role form', calls.join() === 'showForm' );
	at( 'roles', 'nope' );
	check( 'a role that does not exist is the list', calls.join() === 'showList' );
	at( 'users' );
	check( 'another panel\'s hash keeps the level in memory', calls.join() === 'showForm' && asked.length === 0 );
}


// --- a person's own moves ---------------------------------------------------------------

console.log( 'Back links and rows' );
{
	// Every back link of a drill-down panel is a step Back returns to - the browser's own
	// Back then has something to return to, and the level it leaves is the one the link leaves
	[ '_admin/Nino/Modules/Elements/assets/admin.js', '_admin/Nino/Modules/Images/assets/admin.js', '_admin/Nino/Modules/Text/assets/admin.js', '_admin/Nino/Modules/Users/assets/admin.js', '_admin/Nino/Modules/Users/assets/roles.js' ].forEach( function( file ) {

		const source = read( file );
		const links = source.split( 'backLink.addEventListener(' ).slice( 1 );

		check( file+ ': every back link adds an entry before it shows the level above', links.length > 0 && links.every( function( link ) { return /Nino\.admin\.router\.go\(/.test( link.slice( 0, 400 ) ) } ) );
		check( file+ ': a level opened from a list is pushed, never replaced, and the state syncs are not', source.split( 'router.go(' ).length > 2 );
	} );

	// A state sync - the address made true to what is saved - replaces the entry it is on
	const elements = read('_admin/Nino/Modules/Elements/assets/admin.js');
	check( 'saving a new element writes its uri over the \'new\' entry instead of adding one', ( elements.match( /router\.set\( 'elements', \[ Nino\.admin\.elements\._currentType, Nino\.admin\.elements\._currentUri \] \)/g ) || [] ).length === 2 );
}

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
