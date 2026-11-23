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

const vm 		= require('vm');
const { check, done, source, load, classList, shellRouter } = require( './harness.js' );

// What an address says reaches the panel through the router that reads it: a part that is an at sign and a word is the word, { key } the key that reads like it
const typed = shellRouter();
const classes = hidden => classList( null, hidden ? [ 'admin-hidden' ] : [] );


// --- the router ---------------------------------------------------------------------

console.log( 'Router' );
{
	const shell = shellRouter();
	const { router, box, entries } = shell;
	const at = () => shell.index();
	const screen = name => shell.show( name );

	screen( 'images' );
	router.set( 'images', [ 'home' ] );
	check( 'set() replaces the entry it is on', entries.at(-1) === '#images/images/home' && entries.length === 1 && at() === 0 );
	router.set( 'images', [ 'home' ] );
	check( '...and writes nothing the address already says', entries.length === 1 );

	router._push = true;
	router.set( 'images', [ 'team' ] );
	check( 'while the shell flags the switch as the person\'s own, the first write adds an entry instead', entries.join() === '#images/images/home,#images/images/team' && at() === 1 );
	check( '...once: the flag is spent', router._push === false );
	router.set( 'images', [] );
	check( 'the next write replaces again', entries.join() === '#images/images/home,#images' && at() === 1 );

	router.go( 'images', [ 'team' ] );
	check( 'go() adds an entry', entries.join() === '#images/images/home,#images,#images/images/team' && at() === 2 );
	router.go( 'images', [ 'team' ] );
	check( '...but not for an address the bar already shows: a level followed from the hash adds nothing', entries.length === 3 );
	router.set( 'images', [ 'team' ] );
	check( '...and the write that follows it finds the address true', entries.length === 3 );

	router.go( 'users', [ 'ada@example.com' ] );
	router.set( 'users', [] );
	check( 'a panel that is not on screen writes nothing, by either way', entries.length === 3 && box.location.hash === '#images/images/team' );

	router.go( 'images', [ 'a b', 'c/d' ] );
	check( 'the parts are encoded, a key as one segment whatever it holds', box.location.hash === '#images/images/a%20b/c%2Fd' );
	check( 'href() is that address, for a link that opens the level: what go() writes', router.href( 'images', [ 'a b', 'c/d' ] ) === box.location.hash && router.href( 'users', [ 'ada@example.com' ] ) === '#users/users/ada%40example.com' && router.href( 'users' ) === '#users' );
	check( 'a tab is a screen of its panel: the address names the panel first, and the tab by its name', router.href( 'roles', [ router.word('new') ] ) === '#users/roles/@new' && router.href( 'roles' ) === '#users/roles' );

	// Every entry the router writes is numbered, in the order of the history
	const numbers = () => box.history.states.map( state => state === null ? null : state.nino ).join();
	check( 'an entry the router pushed carries its position, and one it replaced keeps its own', numbers() === '0,1,2,3' );
	router.set( 'images', [ 'team', 'again' ] );
	check( '...replacing writes the number of the entry it is on again', box.history.states[at()].nino === at() );

	// leave(): the question before a form is left
	const guarded = [];
	box.Nino.admin.dirty = { guard : ( names, proceed, cancel ) => guarded.push( { names, proceed, cancel } ) };
	let shown = 0;
	router.leave( [ 'x' ], false, () => shown++, () => {} );
	check( 'leave() goes straight on where no form is left', shown === 1 && guarded.length === 0 );
	let resynced = 0;
	const resync = () => resynced++;
	router.leave( [ 'x' ], true, () => shown++, resync );
	check( '...and asks through the shell where one is, a Cancel showing the level in memory again', shown === 1 && guarded.length === 1 && JSON.stringify( guarded[0].names ) === '["x"]' && resynced === 0 );
	guarded[0].cancel();
	check( '...and with no step through the history to take back, nothing else happens', resynced === 1 && box.history.went.length === 0 );
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

	// restore(): the address put back where it stood, after a move that was cancelled
	const entriesBefore = entries.length;
	box.history.replaceState( { nino : at() }, '', '#dashboard' );
	router.restore( '#images/images/a%20b/team', 'images' );
	check( 'restore() writes the address that was replaced over the entry the browser is on, exactly - with the levels', box.location.hash === '#images/images/a%20b/team' && entries[at()] === '#images/images/a%20b/team' && entries.length === entriesBefore );
	check( '...numbered as the entry it replaced', box.history.states[at()].nino === at() && router._index === at() );
	router.restore( '#images/images/a%20b/team', 'images' );
	check( '...and writes nothing where the address already says it', entries.length === entriesBefore && box.history.states[at()].nino === at() );

	box.history.replaceState( { nino : at() }, '', '#dashboard' );
	router.restore( '', 'images' );
	check( 'with no address that was replaced it is the shell\'s own: the name of the panel on screen', box.location.hash === '#images' );
	box.history.replaceState( { nino : at() }, '', '#dashboard' );
	router.restore( undefined, 'images' );
	check( '...as it is where none is known', box.location.hash === '#images' );

	box.history.replaceState( { nino : at() }, '', '#dashboard' );
	router._undoing = { index : at() - 1 };
	router.restore( '#images/images/team', 'images' );
	check( 'while a traversal the shell asked for is on its way, the entry the browser is leaving is not written over', box.location.hash === '#dashboard' );
	router._undoing = null;
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
				word : typed.word, wordOf : typed.wordOf, isWord : typed.isWord,
				set : ( p, parts ) => calls.push( 'set '+ [ p ].concat( parts ).join('/') ),
				go : ( p, parts ) => calls.push( 'go '+ [ p ].concat( parts ).join('/') ),
				current : () => hash,
				leave : ( names, leaving, proceed, resync ) => { asked.push( { names : names, leaving : leaving, resync : resync } ); proceed() },
			},
			workLocale : { current : 'de_DE', follow(){} },
			// The counters of the shell: nothing is written in these checks, so nothing moves
			touch(){}, stamp : () => 0,
		},
		adminUi : {},
	};

	const context = vm.createContext( box );
	load( '_admin/assets/Nino.admin.js', context );
	before.forEach( extra => load( extra, context, extra ) );
	load( file, context, file );

	const module = box.Nino.admin[name];
	levels.forEach( level => { module[level] = ( ...args ) => calls.push( level.replace( /^_/, '' )+ ( args.length > 0 ? ' '+ args.map( String ).join(' ') : '' ) ) } );
	module._ready = true;

	const at = ( panelName, ...parts ) => { hash.panel = panelName; hash.parts = typed.parts( ...parts ); calls.length = 0; asked.length = 0; module.showCurrent() };

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
	at( 'text', 'template/page-home' );
	check( 'a hash that names a row opens it from the list', calls.join() === 'openGroup template/page-home null' && asked[0].leaving === false );
	at( 'text', 'template/page-home', '/template/page-home/welcome/title' );
	check( '...a hash that names a key of it, the row at that key', calls.join() === 'openGroup template/page-home /template/page-home/welcome/title' );
	at( 'text', 'website' );
	check( '...a row of keys somebody made up is a row like any other', calls.join() === 'openGroup website null' );
	on( true, 'template/page-home' );
	at( 'text' );
	check( 'the bare panel is the list, the form torn down like its back link does, asked first - a form is left', calls.join() === 'destroyHtmlEditors,showList' && asked[0].leaving === true && JSON.stringify( asked[0].names ) === '["text"]' );
	at( 'text', 'project/company' );
	check( 'another row replaces this one', calls.join() === 'openGroup project/company null' && asked[0].leaving === true );
	at( 'text', 'template/page-home' );
	check( 'the row on screen is only written back', calls.join() === 'showForm' && asked.length === 0 );
	at( 'text', 'template/page-home', '/template/page-home/welcome/title' );
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
	const { module, calls, asked, at, box } = panel( '_admin/Nino/Modules/Text/assets/keys.js', nodes, 'keys', [ '_showList', '_showForm', '_openGroup', '_openNewKeyForm', '_openScanForm', '_destroyHtmlEditors' ], [ '_admin/Nino/Modules/Text/assets/textkeys.js' ] );
	const entry = key => ( { key : key, global : false, values : { de_DE : 'x' } } );
	module._model = box.Nino.admin.textKeys.build( { entries : [ entry('/template/page-home/welcome/title'), entry('/project/company/general/name') ], locale : 'de_DE', admin : true } );
	const on = ( form, group, view, renaming ) => { nodes['keys-form'].classList = classes( form === false ); nodes['keys-list'].classList = classes( form === true ); module._currentGroup = group ?? null; module._view = view ?? 'group'; module._renameFrom = renaming ?? null };

	on( false );
	at( 'keys', 'template/page-home', '/template/page-home/welcome/title' );
	check( 'a hash that names a key opens the row that holds it, at that key', calls.join() === 'openGroup template/page-home /template/page-home/welcome/title false' && asked[0].leaving === false );
	on( true, 'template/page-home' );
	at( 'keys' );
	check( 'the bare tab is the list, asked first - a form is left', calls.join() === 'destroyHtmlEditors,showList' && asked[0].leaving === true && JSON.stringify( asked[0].names ) === '["keys"]' );
	at( 'keys', 'project/company' );
	check( 'another row replaces this one', calls.join() === 'openGroup project/company null false' && asked[0].leaving === true );
	at( 'keys', 'template/page-home' );
	check( 'the row on screen is only written back', calls.join() === 'showForm' && asked.length === 0 );

	// the forms that make, rename and scan have an address, which an at sign keeps from a row of the same name
	on( false );
	at( 'keys', { key : '@new' } );
	check( 'the key @new is a row nobody has: it is no form that makes a key', calls.join() !== 'openNewKeyForm null undefined false' );
	at( 'keys', '@new' );
	check( 'the address of a new key opens the form that makes one', calls.join() === 'openNewKeyForm null undefined false' && asked[0].leaving === false );
	at( 'keys', '@scan' );
	check( '...the address of the scan the scan', calls.join() === 'openScanForm undefined false' );
	at( 'keys', '@rename', '/template/page-home/welcome/title' );
	check( '...and the address of a rename the form that renames that key', calls.join() === 'openNewKeyForm /template/page-home/welcome/title undefined false' );
	at( 'keys', '@rename', '/template/page-home/nope/title' );
	check( 'a rename of a key there is not is the list', calls.join() === 'showList' );
	on( true, null, 'new' );
	at( 'keys', '@new' );
	check( 'the form that makes a key is only written back where it is on screen', calls.join() === 'showForm' && asked.length === 0 );
	at( 'keys' );
	check( 'the bare tab leaves it for the list, asked first as every form is', calls.join() === 'destroyHtmlEditors,showList' && asked[0].leaving === true );
	at( 'keys', '@scan' );
	check( 'the scan replaces it', calls.join() === 'openScanForm undefined false' && asked[0].leaving === true );
	on( true, null, 'new', '/template/page-home/welcome/title' );
	at( 'keys', '@rename', '/template/page-home/welcome/title' );
	check( 'the rename of the key on screen is only written back', calls.join() === 'showForm' && asked.length === 0 );
	at( 'keys', '@new' );
	check( '...and the form of a new key replaces it', calls.join() === 'openNewKeyForm null undefined false' && asked[0].leaving === true );
	on( true, 'template/page-home' );
	at( 'keys', 'nope' );
	check( 'a row there is not is the list', calls.join() === 'destroyHtmlEditors,showList' );
	at( 'text', 'template/page-home' );
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
	at( 'users', { key : '@new' } );
	check( '...the key @new, which no account has, is no form for a new one', calls.join() !== 'renderCreateForm' && calls.join() !== 'openUser @new' );
	at( 'users', '@new' );
	check( '...\'@new\' the form for a new one, where the account may create', calls.join() === 'renderCreateForm' );
	module._canManage = false;
	at( 'users', '@new' );
	check( '...and the list where it may not', calls.join() === 'showList' );
	module._canManage = true;

	on( true, 'ada@example.com' );
	at( 'users' );
	check( 'the bare panel is the list, asked first - a form is left', calls.join() === 'showList' && asked[0].leaving === true && JSON.stringify( asked[0].names ) === '["users"]' );
	at( 'users', 'bob@example.com' );
	check( 'another account replaces this one', calls.join() === 'openUser bob@example.com' && asked[0].leaving === true );
	at( 'users', '@new' );
	check( 'the new-account form replaces an account\'s', calls.join() === 'renderCreateForm' );
	at( 'users', 'ada@example.com' );
	check( 'the account on screen is only written back', calls.join() === 'showForm' && asked.length === 0 );
	on( true );
	at( 'users', '@new' );
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
	module._roles = [ { id : 'editor' }, { id : 'author' }, { id : 'new' } ];
	const on = ( form, role ) => { nodes['roles-form'].classList = classes( form === false ); nodes['roles-list'].classList = classes( form === true ); module._current = role === undefined ? null : module._roles.find( r => r.id === role ) };

	on( false );
	at( 'roles', 'author' );
	check( 'a hash that names a role opens its form', calls.join() === 'openRole author' && asked[0].leaving === false );
	at( 'roles', '@new' );
	check( '...\'@new\' the form for a new one', calls.join() === 'openRole null' );
	at( 'roles', 'new' );
	check( '...and a role that is called new is a role like any other: the word has an at sign, the key has none', calls.join() === 'openRole new' );

	on( true, 'editor' );
	at( 'roles' );
	check( 'the bare tab is the list, asked first - a form is left', calls.join() === 'showList' && asked[0].leaving === true && JSON.stringify( asked[0].names ) === '["roles"]' );
	at( 'roles', 'author' );
	check( 'another role replaces this one', calls.join() === 'openRole author' );
	at( 'roles', 'editor' );
	check( 'the role on screen is only written back', calls.join() === 'showForm' && asked.length === 0 );
	on( true );
	at( 'roles', '@new' );
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
	[].forEach( function( file ) {

		const script = source( file );
		const links = script.split( 'backLink.addEventListener(' ).slice( 1 );

		check( file+ ': every back link adds an entry before it shows the level above', links.length > 0 && links.every( function( link ) { return /Nino\.admin\.router\.go\(/.test( link.slice( 0, 400 ) ) } ) );
		check( file+ ': a level opened from a list is pushed, never replaced, and the state syncs are not', script.split( 'router.go(' ).length > 2 );
	} );

	// The panels whose way back is the context bar's: the click that leaves a form goes through the router as well
	[ '_admin/Nino/Modules/Elements/assets/admin.js', '_admin/Nino/Modules/Elements/assets/types.js', '_admin/Nino/Modules/Users/assets/admin.js', '_admin/Nino/Modules/Users/assets/roles.js', '_admin/Nino/Modules/Images/assets/admin.js', '_admin/Nino/Modules/Images/assets/slots.js', '_admin/Nino/Modules/Text/assets/admin.js', '_admin/Nino/Modules/Text/assets/keys.js' ].forEach( function( file ) {

		const script = source( file );
		const links = script.split( /back\s*:\s*\{ to : [^\n]*onClick : function\(\) \{/ ).slice( 1 );

		check( file+ ': every back link adds an entry before it shows the level above', links.length > 0 && links.every( function( link ) { return /Nino\.admin\.router\.go\(/.test( link.slice( 0, 200 ) ) } ) );
		check( file+ ': a level opened from a list is pushed, never replaced, and the state syncs are not', script.split( 'router.go(' ).length > 2 );
	} );

	// A state sync - the address made true to what is saved - replaces the entry it is on
	const elements = source('_admin/Nino/Modules/Elements/assets/admin.js');
	check( 'saving a new element writes its uri over the \'new\' entry instead of adding one', ( elements.match( /router\.set\( 'elements', \[ type, elements\._currentUri \] \)/g ) || [] ).length === 2 );
}

done();
