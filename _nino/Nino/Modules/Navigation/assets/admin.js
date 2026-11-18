/**
 *	Nino										A compact filesystembased php framework
 *	Modules\Navigation		The module's /_admin panel, "Navigations": which menus exist,
 *													and which routes stand in each of them, in which order -
 *													ships with the module (see Modules\Navigation\Admin beside
 *													this file) and is loaded exactly while it is active. The view the
 *													Routes module's per-page ticks can't give, since
 *													there a page only ever sees its own membership.
 *
 *													List + drill-down shape follows the Routes panel's admin.js closely,
 *													and so do the addresses: #navs is the list, #navs/new a new menu and
 *													#navs/<key> one menu. A menu has a name, which is optional, and its
 *													key, which a template asks for; the list shows the name first and the key
 *													quietly beside it, a menu without a name shows its key. One menu is a
 *													working copy. The detail level's rows (up, down, remove) and Add
 *													change that copy in the browser and write nothing; Save posts
 *													the complete running order in one request (see
 *													Modules\Navigation\Admin::apiSave()), whose answer is the
 *													complete state, so nothing is patched locally afterwards and
 *													no reload can disagree with the server. A save leaves the person
 *													in the menu and says so as a toast.
 *
 *													No locale switch anywhere in here: a menu has nothing
 *													per-locale about it, and the wording it renders is each
 *													page's own /_nino/webpage<uri>/name key, edited under Routes.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.navs = {

		_navs 			: [],
		_routes 		: [],
		_currentKey : null,
		_isNew 			: false,
		_ready 			: false,
		_loading 		: false,

		// The open menu's working copy: the entries as they stand on screen,
		// a clone of what the server holds until somebody changes it. _dirty
		// is whether they differ; _savedKey and _savedName are what the form
		// was drawn with, to tell a typed one from it
		_entries 		: [],
		_dirty 			: false,
		_savedKey 	: '',
		_savedName 	: '',
		_entriesHost : null,

		// The main button of the bar of the open menu, which the focus goes to after a menu was created
		_primary 		: null,

		// The status line of the bar of the open menu (see Nino.adminUi.status())
		_line 			: null,

		// The row or the button that opened the menu that is open: Back returns
		// the focus to it (see Nino.adminUi.backTo())
		_opener 		: null,

		/**
		 *	Load the menus and draw the level the address names
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('navs-list') === null || Nino.admin.navs._loading === true )
				return;

			Nino.admin.navs._loading = true;

			Nino.admin.navs._apiCall( 'list', {}, function( status, response ) {

				Nino.admin.navs._loading = false;

				if( status !== 200 || response === null )
					return Nino.admin.navs._showError( dc.getElementById('navs-list'), status, response );

				// Taken before any level below writes its own address
				const hash = Nino.admin.router.current();

				Nino.admin.navs._navs 		= response.navs;
				Nino.admin.navs._routes 	= response.routes;
				Nino.admin.navs._renderList();
				Nino.admin.navs._ready = true;

				const target = hash.panel === 'navs' ? Nino.admin.navs._target( hash.parts ) : null;

				if( target === null )
					return Nino.admin.navs._showList();

				Nino.admin.navs._open( target, false );
			} );
		},

		/**
		 *	Re-show whichever level (list or one menu) is currently on. The panel
		 *	is shown again every time somebody comes back to it, and routes, names
		 *	and menus may have changed in Routes or Text in the meantime - so the
		 *	lists are read again, and the menu that is open is drawn again from
		 *	them, unless it holds changes that were not saved: that working copy
		 *	is never replaced (only the routes it can still pick from are). Where the
		 *	address names another level - a step through the history - that one is shown
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.navs._ready === false )
				return Nino.admin.navs.init();

			const hash = Nino.admin.router.current();

			if( hash.panel !== 'navs' || Nino.admin.navs._follow( hash.parts ) === false ) {

				if( Nino.admin.navs._formOpen() === true )
					Nino.admin.navs._showForm();
				else
					Nino.admin.navs._showList();
			}

			Nino.admin.navs._apiCall( 'list', {}, function( status, response ) {

				// A read that fails leaves what is on screen as it is: the panel
				// is not the place to lose a working copy over a flaky request
				if( status !== 200 || response === null )
					return;

				if( Nino.admin.navs._isDirty() === true ) {
					Nino.admin.navs._navs 		= response.navs;
					Nino.admin.navs._routes 	= response.routes;
					Nino.admin.navs._renderList();
					Nino.admin.navs._renderEntries();
					return;
				}

				Nino.admin.navs._apply( response );
			} );
		},

		/**
		 *	Whether a menu - or the form of a new one - is on screen
		 *
		 *	@return		{boolean}
		 */
		_formOpen : function() {

			const form = dc.getElementById('navs-form');

			return form !== null && form.classList.contains('admin-hidden') === false;
		},

		/**
		 *	What the parts of an address name: '@new' - an at sign no key has, so that a menu called new keeps an address of its own -, a menu, or the list. A menu the list does not hold is the list
		 *
		 *	@param		{Array}		parts					The address behind the panel's name
		 *
		 *	@return		{Object|null}							{ nav }, nav null for a new menu; null for the list
		 */
		_target : function( parts ) {

			if( parts.length === 1 && parts[0] === '@new' )
				return { nav : null };

			const nav = parts.length === 1 ? Nino.admin.navs._find( parts[0] ) : null;

			return nav === null ? null : { nav : nav };
		},

		/**
		 *	Move to the level the address names, if it is not the one on screen. Leaving a menu that holds unsaved
		 *	input asks first (see Nino.admin.router.leave())
		 *
		 *	@param		{Array}		parts					The address behind the panel's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const target = Nino.admin.navs._target( parts );
			const open = Nino.admin.navs._formOpen();
			const same = target === null ? open === false : ( open === true && ( target.nav === null ? Nino.admin.navs._isNew === true : Nino.admin.navs._isNew === false && Nino.admin.navs._currentKey === target.nav.key ) );

			if( same === true )
				return false;

			Nino.admin.router.leave( [ 'navs' ], open, function() {
				if( target === null )
					Nino.admin.navs._showList();
				else
					Nino.admin.navs._open( target, false );
			}, Nino.admin.navs._showLevel );

			return true;
		},

		/**
		 *	Show the level that is on, and write it into the address
		 *
		 *	@return		void
		 */
		_showLevel : function() {

			if( Nino.admin.navs._formOpen() === true )
				return Nino.admin.navs._showForm();

			Nino.admin.navs._showList();
		},

		/**
		 *	Take a response in and redraw whichever level is open - every
		 *	action here answers with the complete state, so nothing has to be
		 *	patched locally and no reload can disagree with the server
		 *
		 *	@param		{Object}	response
		 *
		 *	@return		void
		 */
		_apply : function( response ) {

			Nino.admin.navs._navs 		= response.navs;
			Nino.admin.navs._routes 	= response.routes;

			Nino.admin.navs._renderList();

			// The menu that was open may have just been renamed (the response
			// is the only place its new key exists) or deleted by this very
			// action - fall back to the list rather than redraw a stale one
			if( Nino.admin.navs._currentKey !== null ) {

				const current = Nino.admin.navs._find( Nino.admin.navs._currentKey );

				if( current === null )
					return Nino.admin.navs._showList();

				Nino.admin.navs._renderForm( current );
			}
		},

		/**
		 *	@param		{string}	key
		 *
		 *	@return		{Object|null}
		 */
		_find : function( key ) {
			return Nino.admin.navs._navs.find( function( nav ) { return nav.key === key } ) || null;
		},

		/**
		 *	Call a navs/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "navs/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'navs/'+ endpoint, payload, callback );
		},

		/**
		 *	Show a failed request's status/error in a container
		 *
		 *	@param		{Element}		container
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( container, status, response ) {
			Nino.adminUi.showError( container, status, response, '/_admin/common/error/load' );
		},

		/**
		 *	Show the list again as it stands: the focus stays where it is, unless it
		 *	stood in the menu that is hidden now (see Nino.adminUi.showLevel()). What a
		 *	person opens goes through _open(), and Back through the back link
		 *
		 *	@param		{Element|null}	[opener]	Back: the control that opened the menu that is left
		 *
		 *	@return		void
		 */
		_showList : function( opener ) {

			Nino.admin.navs._leave();
			Nino.admin.router.set( 'navs', [] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'navs-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'navs-list', opener );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the menu
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {

			Nino.admin.router.set( 'navs', Nino.admin.navs._isNew === true ? [ '@new' ] : [ Nino.admin.navs._currentKey ] );
			Nino.adminUi.showLevel( 'navs-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	Let go of the menu that was open: its key, its working copy and the
		 *	changes in it
		 *
		 *	@return		void
		 */
		_leave : function() {
			Nino.admin.navs._currentKey = null;
			Nino.admin.navs._isNew 			= false;
			Nino.admin.navs._dirty 			= false;
			Nino.admin.navs._entries 		= [];
		},

		/**
		 *	The link of a row of the list as the list is drawn now: the list is drawn again behind an open menu
		 *	after a save, and the row that was pressed is gone with the old drawing
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( key ) {

			const row = Array.from( dc.getElementById('navs-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === key } );

			return row === undefined ? null : row.querySelector('a');
		},

		/**
		 *	What a menu is called in the workbench: its name, and its key where it has none
		 *
		 *	@param		{Object}	nav
		 *
		 *	@return		{string}
		 */
		_title : function( nav ) {
			return typeof nav.name === 'string' && nav.name !== '' ? nav.name : nav.key;
		},

		/**
		 *	How many entries a menu holds, in words
		 *
		 *	@param		{number}	count
		 *
		 *	@return		{string}
		 */
		_count : function( count ) {

			if( count === 0 )
				return Nino.content.getText('/_admin/navs/list/entries-none');

			return count === 1 ? Nino.content.getText('/_admin/navs/list/entries-one') : Nino.adminUi.format( Nino.content.getText('/_admin/navs/list/entries'), count );
		},

		/**
		 *	Render the menu list, plus a "New navigation" button in the bar below it -
		 *	one row per registered menu, named by its name with its key and the number of entries quietly under it
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('navs-list');
			wrap.innerHTML = '';

			if( Nino.admin.navs._navs.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/navs/empty') ) );
			else
				wrap.appendChild( Nino.adminUi.list( Nino.admin.navs._navs.map( function( nav ) {
					return {
						key			: nav.key,
						title		: Nino.admin.navs._title( nav ),
						detail	: ( nav.name !== undefined && nav.name !== '' ? nav.key+ ' · ' : '' )+ Nino.admin.navs._count( nav.entries.length ),
						href		: Nino.admin.router.href( 'navs', [ nav.key ] ),
						onOpen	: function() {
							Nino.admin.router.go( 'navs', [ nav.key ] );
							Nino.admin.navs._open( { nav : nav }, true );
						},
					};
				} ), { label : Nino.content.getText('/_admin/nav/navs') } ) );

			const addBtn = Nino.adminUi.button( Nino.content.getText('/_admin/navs/label/new'), function() {
				Nino.admin.router.go( 'navs', [ '@new' ] );
				Nino.admin.navs._open( { nav : null }, true, addBtn );
			} );
			wrap.appendChild( Nino.adminUi.actionBar( { list : true, primary : addBtn } ).el );
		},

		/**
		 *	Open one menu, or a blank one to create
		 *
		 *	@param		{Object}				target		{ nav } - nav one entry from _navs, or null to create new
		 *	@param		{boolean}				opened		The person opened it: the focus goes into the menu
		 *	@param		{Element}				[opener]	The button that was pressed, where it is no row
		 *
		 *	@return		void
		 */
		_open : function( target, opened, opener ) {

			Nino.admin.navs._isNew 			= target.nav === null;
			Nino.admin.navs._currentKey = target.nav === null ? null : target.nav.key;
			Nino.admin.navs._opener 		= opener ?? null;

			Nino.admin.navs._renderForm( target.nav || { key : '', name : '', entries : [] } );
			Nino.admin.navs._showForm( opened );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	The control Back returns the focus to: the row of the menu, or the button that opened the form of a new one
		 *
		 *	@return		{Element|null}
		 */
		_backTarget : function() {
			return Nino.admin.navs._isNew === true || Nino.admin.navs._currentKey === null ? Nino.admin.navs._opener : Nino.admin.navs._rowOf( Nino.admin.navs._currentKey );
		},

		/**
		 *	Render one menu: back-link, its name and key, its running order, an add
		 *	picker, and save/delete. The working copy starts as a clone of the
		 *	menu's entries
		 *
		 *	@param		{Object}	nav
		 *
		 *	@return		void
		 */
		_renderForm : function( nav ) {

			Nino.admin.navs._entries 		= nav.entries.map( function( entry ) { return Object.assign( {}, entry ) } );
			Nino.admin.navs._dirty 			= false;
			Nino.admin.navs._savedKey 	= nav.key || '';
			Nino.admin.navs._savedName 	= nav.name || '';

			const wrap = dc.getElementById('navs-form');
			wrap.innerHTML = '';

			wrap.appendChild( Nino.adminUi.contextBar( { back : { to : wrap, onClick : function() { Nino.admin.navs._back() } } } ) );

			const form = dc.createElement('form');

			// The browser's own bubble is not asked to say what is wrong: _save()
			// does, at the field and in the form's own words
			form.noValidate = true;

			const navFieldset = dc.createElement('fieldset');
			navFieldset.appendChild( Nino.adminUi.el( 'legend', '', Nino.content.getText('/_admin/navs/label/navigation') ) );

			navFieldset.appendChild( Nino.adminUi.textField( { key : 'name', label : Nino.content.getText('/_admin/navs/label/name'), id : 'navs-form-name', value : nav.name || '',
				maxLength : 80 /* \Nino\Modules\Navigation\Admin::NAME_MAX */, placeholder : Nino.content.getText('/_admin/navs/placeholder/name'), help : Nino.content.getText('/_admin/navs/help/name') } ) );

			// Renaming the key leaves templates alone on purpose - see Admin.php's
			// apiSave() for why - so the help says where the other half of
			// a rename has to happen by hand
			navFieldset.appendChild( Nino.adminUi.textField( { key : 'key', label : Nino.content.getText('/_admin/navs/label/key'), id : 'navs-form-key', value : nav.key || '', required : true,
				placeholder : Nino.content.getText('/_admin/navs/placeholder/key'), help : Nino.content.getText( Nino.admin.navs._isNew === true ? '/_admin/navs/help/key' : '/_admin/navs/help/key-rename' ) } ) );

			form.appendChild( navFieldset );

			// The running order only exists once the menu does: a new one has
			// no key on the server to hang entries off yet. Its host stays
			// where it is while the entries inside it are drawn again, so a key
			// typed above is not lost to a click on a row below
			Nino.admin.navs._entriesHost = null;
			if( Nino.admin.navs._isNew === false ) {
				Nino.admin.navs._entriesHost = dc.createElement('div');
				form.appendChild( Nino.admin.navs._entriesHost );
				Nino.admin.navs._renderEntries();
			}

			// The bar is the one of every form (actionBar()): Delete at the left where there is a menu to delete, Save at the right edge
			const deleteBtn = Nino.admin.navs._isNew === true ? null : Nino.adminUi.button( Nino.content.getText('/_admin/navs/label/delete'), function() { Nino.admin.navs._delete() }, { kind : 'danger' } );
			const msg = dc.createElement('p');
			msg.id = 'navs-form-msg';

			const bar = Nino.adminUi.actionBar( { danger : deleteBtn, status : { line : msg, fresh : Nino.admin.navs._isNew === true }, primary : Nino.adminUi.button( Nino.content.getText( Nino.admin.navs._isNew === true ? '/_admin/navs/label/create' : '/_admin/common/label/save' ), null, { type : 'submit' } ) } );
			Nino.admin.navs._line = bar.line;
			Nino.admin.navs._primary = bar.primary;

			form.appendChild( bar.el );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.navs._save() } );

			wrap.appendChild( form );
		},

		/**
		 *	One menu's entries in their running order, each with up, down and remove, plus the picker that adds
		 *	another one at the end - drawn from the working copy, into the host that was made for it. The focus is
		 *	put back where it was: on the button that was pressed, which stands in the row the entry moved to
		 *
		 *	@param		{Object}	[focus]				{ index, button } - the row and the button ('up', 'down' or 'remove') that take the focus, or 'add' for the picker
		 *
		 *	@return		void
		 */
		_renderEntries : function( focus ) {

			const host = Nino.admin.navs._entriesHost;

			if( host === null )
				return;

			host.innerHTML = '';

			const entries = Nino.admin.navs._entries;
			const buttons = [];

			const fieldset = dc.createElement('fieldset');
			const legend = fieldset.appendChild( Nino.adminUi.el( 'legend', '', Nino.content.getText('/_admin/navs/label/entries') ) );
			Nino.adminUi.sectionHelp( legend, fieldset, Nino.content.getText('/_admin/navs/help/entries') );

			if( entries.length === 0 )
				fieldset.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/navs/empty-entries') ) );
			else
				fieldset.appendChild( Nino.adminUi.list( entries.map( function( entry, index ) {

					const up = Nino.adminUi.iconButton( 'arrow-up', Nino.content.getText('/_admin/navs/label/moveup'), function() { Nino.admin.navs._move( index, -1 ) }, { small : true, disabled : index === 0 } );
					const down = Nino.adminUi.iconButton( 'arrow-down', Nino.content.getText('/_admin/navs/label/movedown'), function() { Nino.admin.navs._move( index, 1 ) }, { small : true, disabled : index === entries.length - 1 } );
					const remove = Nino.adminUi.iconButton( 'x', Nino.content.getText('/_admin/navs/label/remove'), function() { Nino.admin.navs._remove( index ) }, { small : true, danger : true } );

					buttons.push( { up : up, down : down, remove : remove } );

					return {
						key			: entry.httpUri,
						title		: entry.label,
						detail	: Nino.admin.navs._paths( entry ),
						badge		: entry.named === false ? { text : Nino.content.getText('/_admin/navs/label/unnamed'), state : 'warn' } : undefined,
						actions : [ up, down, remove ],
					};
				} ), { label : Nino.content.getText('/_admin/navs/label/entries') } ) );

			// Every GET route can join a menu, not just the pages the Routes
			// module manages - one already in this menu is simply not offered
			// again. Nothing is preselected: the first route in the list is not
			// a decision, and Add says so when nothing was chosen
			const taken = entries.map( function( entry ) { return entry.httpUri } );
			const free 	= Nino.admin.navs._routes.filter( function( route ) { return taken.indexOf( route.httpUri ) === -1 } );

			const options = [ { value : '', label : Nino.content.getText('/_admin/navs/label/choose') } ].concat( free.map( function( route ) {
				return { value : route.httpUri, label : route.label+ ' ('+ Nino.admin.navs._paths( route )+ ')'+ ( route.named === false ? ' '+ Nino.content.getText('/_admin/navs/label/unnamed-short') : '' ) };
			} ) );

			const add = Nino.adminUi.selectField( { key : 'add', label : Nino.content.getText('/_admin/navs/label/add'), id : 'navs-form-add', options : options, value : '', help : Nino.content.getText('/_admin/navs/help/add') } );
			const select = add.querySelector('select');
			select.disabled = free.length === 0;
			fieldset.appendChild( add );

			// A secondary button, not the main one: the main button of the view is Save, in its bar. It is not locked while
			// nothing is chosen - it says what is missing, at the field
			fieldset.appendChild( Nino.adminUi.button( Nino.content.getText('/_admin/navs/label/addbtn'), function() { Nino.admin.navs._add( select, add ) }, { kind : 'secondary', id : 'navs-form-addbtn' } ) );

			host.appendChild( fieldset );

			if( focus === undefined || focus === null )
				return;

			if( focus === 'add' ) {
				select.focus();
				return;
			}

			const at = buttons[focus.index];
			const target = at === undefined ? undefined : ( at[focus.button].disabled === true ? at[focus.button === 'up' ? 'down' : 'up'] : at[focus.button] );

			if( target !== undefined && target.disabled !== true )
				target.focus();
		},

		/**
		 *	A change to the working copy: remember that it differs from what is
		 *	saved, say so in the status line, and draw the entries again - only
		 *	them, so what was typed into the key field above survives. Under the
		 *	shell the action bar says "unsaved" itself, so the panel's own line
		 *	is for the shell-less case only
		 *
		 *	@param		{Object}	[focus]				Where the focus goes after the entries are drawn, see _renderEntries()
		 *
		 *	@return		void
		 */
		_changed : function( focus ) {

			Nino.admin.navs._dirty = true;
			Nino.admin.navs._renderEntries( focus );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
			else if( Nino.admin.navs._line !== null )
				Nino.admin.navs._line.dirty();
		},

		/**
		 *	Swap one entry of the working copy with its neighbor
		 *
		 *	@param		{number}	index
		 *	@param		{number}	step					-1 up, 1 down
		 *
		 *	@return		void
		 */
		_move : function( index, step ) {

			const entries = Nino.admin.navs._entries;
			const other 	= index + step;

			if( other < 0 || other >= entries.length )
				return;

			const held = entries[index];
			entries[index] = entries[other];
			entries[other] = held;

			Nino.admin.navs._changed( { index : other, button : step < 0 ? 'up' : 'down' } );
		},

		/**
		 *	The paths an entry is reached at, for the list: all of them for a
		 *	page with a route per language - the panel sends the first one to
		 *	name it - and the one path of any other
		 *
		 *	@param		{Object}	entry					{ httpUri, paths? }
		 *
		 *	@return		{string}
		 */
		_paths : function( entry ) {
			return Array.isArray( entry.paths ) === true && entry.paths.length > 0 ? entry.paths.join(', ') : entry.httpUri;
		},

		/**
		 *	Take one entry out of the working copy - the route itself stays. The focus goes to the entry that now
		 *	stands in its place, else the one before it, else to the picker
		 *
		 *	@param		{number}	index
		 *
		 *	@return		void
		 */
		_remove : function( index ) {

			Nino.admin.navs._entries.splice( index, 1 );

			const left = Nino.admin.navs._entries.length;

			Nino.admin.navs._changed( left === 0 ? 'add' : { index : Math.min( index, left - 1 ), button : 'remove' } );
		},

		/**
		 *	Put the route the picker holds at the end of the working copy
		 *
		 *	@param		{Element}	select				The picker
		 *	@param		{Element}	field					The label the picker stands in, which a missing choice is said under
		 *
		 *	@return		void
		 */
		_add : function( select, field ) {

			Nino.adminUi.clearFieldErrors( field.parentNode );

			const httpUri = select.value;
			const route = Nino.admin.navs._routes.find( function( candidate ) { return candidate.httpUri === httpUri } );

			if( route === undefined ) {
				Nino.adminUi.fieldError( field, select, 'navs-error-add', Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), Nino.content.getText('/_admin/navs/label/add') ) );
				select.focus();
				return;
			}

			if( Nino.admin.navs._entries.some( function( entry ) { return entry.httpUri === httpUri } ) === true )
				return;

			Nino.admin.navs._entries.push( Object.assign( {}, route ) );
			Nino.admin.navs._changed( 'add' );
		},

		/**
		 *	Whether the open menu holds anything that is not saved: a changed
		 *	working copy, or a name or a key that was typed - on screen only, a menu
		 *	that is drawn but not shown holds no input anybody is looking at
		 *
		 *	@return		{boolean}
		 */
		_isDirty : function() {

			if( Nino.admin.navs._currentKey === null && Nino.admin.navs._isNew === false )
				return false;

			if( Nino.admin.navs._formOpen() === false )
				return false;

			const key = dc.getElementById('navs-form-key');
			const name = dc.getElementById('navs-form-name');

			return Nino.admin.navs._dirty === true || ( key !== null && key.value !== Nino.admin.navs._savedKey ) || ( name !== null && name.value !== Nino.admin.navs._savedName );
		},

		/**
		 *	The shell's Discard: the menu is about to be left or drawn again, and
		 *	whatever it held is let go
		 *
		 *	@return		void
		 */
		_discard : function() {
			Nino.admin.navs._dirty 			= false;
			Nino.admin.navs._savedKey 	= ( dc.getElementById('navs-form-key') || { value : '' } ).value;
			Nino.admin.navs._savedName 	= ( dc.getElementById('navs-form-name') || { value : '' } ).value;
		},

		/**
		 *	The back link. A working copy is not saved anywhere and there is no
		 *	undo, so the shell asks before it is left (Save, Discard or Cancel, see
		 *	Nino.admin.dirty) and this runs after it has
		 *
		 *	@return		void
		 */
		_back : function() {

			const opener = Nino.admin.navs._backTarget();

			Nino.admin.router.go( 'navs', [] );
			Nino.admin.navs._showList( opener );
		},

		/**
		 *	Create the menu currently open, rename it, give it a name and/or save its running
		 *	order - one request, the whole order. The answer is the complete state
		 *	and the menu is drawn from it
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the menu was written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = Nino.admin.navs._line;
			const form = dc.getElementById('navs-form');
			const keyInput = dc.getElementById('navs-form-key');
			const key = keyInput.value.trim();
			const name = dc.getElementById('navs-form-name').value.trim();

			Nino.adminUi.clearFieldErrors( form );

			// The key is what a template asks for, so a menu cannot be made without
			// one - and nothing is sent that the server would refuse: the same
			// pattern as Admin::isValidKey(). The field says what is wrong with it
			// where it stands, and takes the focus
			if( key === '' || /^[a-z][a-z0-9_-]*$/.test( key ) === false ) {
				Nino.admin.navs._mark( 'key', key === '' ? Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), Nino.content.getText('/_admin/navs/label/key') ) : Nino.adminUi.format( Nino.content.getText('/_admin/error/navs_invalid_id'), key ) );
				line.fail( Nino.content.getText('/_admin/navs/error/fields') );
				report( false );
				return;
			}

			line.saving();

			const payload = {
				originalKey : Nino.admin.navs._isNew ? '' : Nino.admin.navs._currentKey,
				key 				: key,
				name 				: name,
			};

			// A new menu has no running order yet to post, and a menu whose entries were not touched sends none: saving its name or its
			// key leaves the memberships of the routes as they are
			if( Nino.admin.navs._isNew === false && Nino.admin.navs._dirty === true )
				payload.entries = Nino.admin.navs._entries.map( function( entry ) { return entry.httpUri } );

			Nino.admin.navs._apiCall( 'save', payload, function( status, response ) {

				if( status !== 200 || response === null ) {
					line.error( status, response, '/_admin/common/error/save' );

					// A refusal that names the key or the name stands at the field too
					if( response !== null && typeof response === 'object' && ( response.field === 'key' || response.field === 'name' ) )
						Nino.admin.navs._mark( response.field, line.element.textContent );

					report( false );
					return;
				}

				Nino.admin.touch( 'navigations' );
				Nino.admin.navs._saved( response, key, report );
			} );
		},

		/**
		 *	A menu was written: the lists are what the server answered and the person stays in the menu, under the
		 *	key it has now. A menu that was created is drawn anew - it has its running order and its Delete button
		 *	now - and an existing one only has its entries and its working copy taken from the answer, so that the
		 *	focus stays on the button that was pressed
		 *
		 *	@param		{Object}		response			{ navs, routes }
		 *	@param		{string}		key						The key the menu was saved under
		 *	@param		{Function}	report				Called once with true
		 *
		 *	@return		void
		 */
		_saved : function( response, key, report ) {

			const navs = Nino.admin.navs;
			const created = navs._isNew === true;

			navs._isNew 			= false;
			navs._currentKey 	= key;
			navs._navs 				= response.navs;
			navs._routes 			= response.routes;
			navs._renderList();

			const nav = navs._find( key );

			if( created === true ) {
				navs._renderForm( nav );
				navs._showForm();
				navs._line.saved();
				navs._primary.focus();
			} else {

				// What the fields hold is what the server kept: the key and the name as it trimmed them
				dc.getElementById('navs-form-key').value = key;
				dc.getElementById('navs-form-name').value = nav.name || '';

				navs._savedKey 	= key;
				navs._savedName = nav.name || '';
				navs._dirty 		= false;
				navs._entries 	= nav.entries.map( function( entry ) { return Object.assign( {}, entry ) } );
				navs._renderEntries();
				navs._showForm();
				navs._line.saved();
			}

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();

			report( true );
		},

		/**
		 *	Say at a field of the form what is wrong with it - a sentence under it that
		 *	the field points at - and put the focus there
		 *
		 *	@param		{string}	field					'key' or 'name'
		 *	@param		{string}	text
		 *
		 *	@return		void
		 */
		_mark : function( field, text ) {

			const input = dc.getElementById( field === 'key' ? 'navs-form-key' : 'navs-form-name' );

			Nino.adminUi.clearFieldErrors( input.closest('label').parentNode );
			Nino.adminUi.fieldError( input.closest('label'), input, 'navs-error-'+ field, text );
			input.focus();
		},

		/**
		 *	What a delete takes and what it leaves, as the two groups of the question
		 *
		 *	@param		{Object|null}		nav
		 *
		 *	@return		{Array}
		 */
		_details : function( nav ) {

			const taken = [ Nino.adminUi.format( Nino.content.getText('/_admin/navs/confirm/gone-key'), Nino.admin.navs._currentKey ) ];

			if( nav !== null && nav.entries.length > 0 )
				taken.push( Nino.adminUi.format( Nino.content.getText( nav.entries.length === 1 ? '/_admin/navs/confirm/gone-place-one' : '/_admin/navs/confirm/gone-place' ), nav.entries.length ) );

			return [
				{ title : Nino.content.getText('/_admin/navs/confirm/deleted'), lines : taken },
				{ title : Nino.content.getText('/_admin/navs/confirm/kept'), lines : [ Nino.content.getText('/_admin/navs/confirm/stays-routes'), Nino.content.getText('/_admin/navs/confirm/stays-templates') ] },
			];
		},

		/**
		 *	Confirm, then delete the menu currently open. It goes out of the
		 *	registry and off every route in it; the routes themselves stay. The
		 *	person goes back to the list
		 *
		 *	@return		void
		 */
		_delete : function() {

			const navs = Nino.admin.navs;
			const key = navs._currentKey;
			const nav = navs._find( key );

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/navs/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/navs/confirm/delete'), nav === null ? key : navs._title( nav ) ),
				details		: navs._details( nav ),
				yes				: Nino.content.getText('/_admin/navs/confirm/delete-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					const line = navs._line;
					line.idle( Nino.content.getText('/_admin/common/msg/deleting') );

					// The focus goes to the row after the one that goes, else the one before it
					const at = navs._navs.findIndex( function( each ) { return each.key === key } );
					const next = navs._navs[at + 1] ?? navs._navs[at - 1];

					navs._apiCall( 'delete', { key : key }, function( status, response ) {
						if( status !== 200 || response === null ) {
							line.error( status, response, '/_admin/common/error/delete' );
							return;
						}
						Nino.admin.touch( 'navigations' );
						navs._leave();
						navs._navs 		= response.navs;
						navs._routes 	= response.routes;
						navs._renderList();
						Nino.admin.router.go( 'navs', [] );
						navs._showList( next === undefined ? null : navs._rowOf( next.key ) );
						Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/deleted') );

						if( typeof Nino.admin.dirty === 'object' )
							Nino.admin.dirty.refresh();
					} );
				},
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.navs.init );

	// The shell asks before anything throws the open menu's input away (see
	// Nino.admin.dirty): the working copy of its entries and a typed name or key are not
	// plain fields it could compare, so the panel answers for them itself. A
	// shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'navs', {
			isDirty : function() { return Nino.admin.navs._isDirty() },
			save 		: function( done ) { Nino.admin.navs._save( done ) },
			discard : function() { Nino.admin.navs._discard() },
			bar 		: function() { const wrap = dc.getElementById('navs-form'); return wrap === null ? null : wrap.querySelector('.nino-admin-actionbar') },
		} );

})(window, document, document.documentElement, document.body);
