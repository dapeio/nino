

/**
 *	Nino										A compact filesystembased php framework
 *	admin.js								"Routes" panel: create/edit/delete the site's actual page
 *													routes without hand-editing /nino/http/routes as raw json
 *													(the Config panel no longer edits that key). The
 *													template select only ever offers a templates/page-*.tpl
 *													file that already exists on disk, and a new route starts
 *													with every field empty - see the panel's own
 *													Admin/Admin.php class docblock for the Element URI/Http
 *													URI split every entry carries.
 *
 *													Every view has an address: #routes is the list,
 *													#routes/new the form of a new route, #routes/<Element
 *													URI> a route and #routes/feature/<Element URI> a page a
 *													feature routes. A save leaves the person in the view it
 *													was made in and says so as a toast; a new route gets its
 *													address when it is saved. The list is rows of the block
 *													(list()) whose up and down buttons reorder the persisted routes at once,
 *													which is what orders every menu those pages appear in
 *													(see \Nino\Modules\Navigation), and say so as a toast.
 *
 *													A page has one Http URI for every language or one for each: a
 *													switch says which, and the form draws the one field or a row per
 *													language - the name, the title and the addresses are values
 *													of every language one under the other (localeRows()), the
 *													description is the one of the language the workbench edits
 *													content in (Nino.admin.workLocale) and the others keep theirs.
 *													Languages whose rows hold one address share it (see
 *													_addressMap()). The navigations a page stands in are the ticks
 *													of one list, each named by its name and, quietly, its key.
 *													The pages a feature or a module routes at runtime - which no
 *													config.php has - are listed below them, one row per
 *													Element-URI, without the buttons and without delete: their form
 *													is the paths, read-only, and the name, title and
 *													description, saved as routes/savetexts.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.routes = {

		_pages 					: [],
		_runtime 				: [],
		_templates 			: [],
		_defaultTemplate : '',
		_locales 				: [],
		_selectedLocale : '',
		_localeNames 		: {},
		_navs 					: [],
		_navNames 			: {},
		_ready 					: false,
		_loading 				: false,
		// The form that is open - { kind, uri, entry, work, fields... } - or null while the list is. kind is
		// 'page' (a route of the project), 'new' (none yet) or 'feature' (a page a feature routes)
		_view 					: null,
		// The row or the button that opened the form that is open: Back returns
		// the focus to it (see Nino.adminUi.backTo()) - a row is found again by its key, since the list is drawn again behind the form
		_opener 				: null,
		// The status line of the form that is open (see Nino.adminUi.status())
		_line 					: null,
		// The status line under the list, where a refused move reports
		_listLine 			: null,
		// What the address fields of the form that is open start from, and what
		// they are given back from when the switch is turned: { one, shared,
		// bound } (see _addressesOf()). Between two turns the fields on screen
		// are the truth
		_addresses 			: null,
		// The stamp of the templates (see Nino.admin.touch()) the list was asked
		// for: the form offers the templates the answer held, and one written
		// since is not among them
		_stamp 					: 0,
		// ...and the stamp of the navigations (the Navigations panel touches it): the ticks of the form are the ones the list held
		_navStamp 			: 0,

		/**
		 *	Load the persisted page list and draw the level the address names
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('routes-list') === null || Nino.admin.routes._loading === true )
				return;

			Nino.admin.routes._loading = true;

			// A template written while the answer is on its way is not in it: the
			// stamp is the one from before the request went out
			const stamp = Nino.admin.stamp( 'templates' );
			const navStamp = Nino.admin.stamp( 'navigations' );

			Nino.admin.routes._load( function( status, response ) {

				Nino.admin.routes._loading = false;

				if( status !== 200 || response === null )
					return Nino.admin.routes._showError( dc.getElementById('routes-list'), status, response );

				Nino.admin.routes._ready = true;
				Nino.admin.routes._stamp = stamp;
				Nino.admin.routes._navStamp = navStamp;

				// Taken before any level below writes its own address
				const hash = Nino.admin.router.current();

				Nino.admin.routes._renderList();

				const target = hash.panel === 'routes' ? Nino.admin.routes._target( hash.parts ) : null;

				if( target === null )
					return Nino.admin.routes._showList();

				Nino.admin.routes._open( target, false );
			} );
		},

		/**
		 *	Ask for the list and take the answer in: the pages, the pages of features, the templates, the languages and the
		 *	navigations. The caller draws
		 *
		 *	@param		{Function}	done					Called with ( status, response )
		 *
		 *	@return		void
		 */
		_load : function( done ) {

			Nino.admin.routes._apiCall( 'list', {}, function( status, response ) {

				if( status === 200 && response !== null ) {
					Nino.admin.routes._pages 						= response.pages;
					Nino.admin.routes._runtime 					= response.runtime || [];
					Nino.admin.routes._templates 				= response.templates;
					Nino.admin.routes._defaultTemplate 	= response.defaultTemplate || '';
					Nino.admin.routes._locales 					= response.locales;
					Nino.admin.routes._selectedLocale 	= response.selectedLocale || '';
					Nino.admin.routes._localeNames 			= response.localeNames || {};
					Nino.admin.routes._navs 						= response.navs;
					Nino.admin.routes._navNames 				= response.navNames || {};
				}

				done( status, response );
			} );
		},

		/**
		 *	The language a page is named in on the list: the one the workbench edits
		 *	content in (Nino.admin.workLocale) where the website serves it, else the one
		 *	the server named when the list was read
		 *
		 *	@return		{string}
		 */
		_language : function() {

			const current = Nino.admin.workLocale.current;

			return Nino.admin.routes._locales.indexOf( current ) !== -1 ? current : Nino.admin.routes._selectedLocale;
		},

		/**
		 *	The workbench's content language moved: the list names its pages in it,
		 *	and the form that is open is drawn again with the description of that
		 *	language - the shell has asked about its unsaved input already, so what
		 *	it holds now is what is saved
		 *
		 *	@return		void
		 */
		_followLocale : function() {

			if( Nino.admin.routes._ready === false )
				return;

			Nino.admin.routes._renderList();

			const view = Nino.admin.routes._view;

			if( view === null )
				return;

			Nino.admin.routes._draw( view.kind, view.entry );
			Nino.admin.routes._snapshot();
		},

		/**
		 *	Re-show whichever level (list or form) is on - and read the list again
		 *	first where a page template was written since it was read (the Builder
		 *	creates them), so that the form's select offers it. Not while a form
		 *	is open: the list would be drawn over it. Where the address names
		 *	another level - a step through the history - that one is shown
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.routes._ready === false )
				return Nino.admin.routes.init();

			const hash = Nino.admin.router.current();

			if( hash.panel === 'routes' && Nino.admin.routes._follow( hash.parts ) === true )
				return;

			if( Nino.admin.routes._view !== null )
				return Nino.admin.routes._showForm();

			if( Nino.admin.stamp( 'templates' ) !== Nino.admin.routes._stamp || Nino.admin.stamp( 'navigations' ) !== Nino.admin.routes._navStamp )
				return Nino.admin.routes.init();

			Nino.admin.routes._showList();
		},

		/**
		 *	What the parts of an address name: '@new' - an at sign no Element URI has, so that a route called new keeps an address of its own -, a route of the project, a page a feature routes, or the list. A
		 *	route or a page the list does not hold is the list
		 *
		 *	@param		{Array}		parts					The address behind the panel's name
		 *
		 *	@return		{Object|null}							{ kind, uri, entry }, null for the list
		 */
		_target : function( parts ) {

			if( parts[0] === '@new' && parts.length === 1 )
				return { kind : 'new', uri : '', entry : Nino.admin.routes._blank() };

			if( parts[0] === 'feature' && parts.length === 2 ) {
				const runtime = Nino.admin.routes._runtime.find( function( page ) { return page.uri === parts[1] } );
				return runtime === undefined ? null : { kind : 'feature', uri : runtime.uri, entry : runtime };
			}

			const page = parts.length === 1 ? Nino.admin.routes._pages.find( function( entry ) { return entry.uri === parts[0] } ) : undefined;

			return page === undefined ? null : { kind : 'page', uri : page.uri, entry : page };
		},

		/**
		 *	The empty entry of a new route: nothing but the template the project proposes
		 *
		 *	@return		{Object}
		 */
		_blank : function() {
			return { uri : '', httpUris : {}, template : Nino.admin.routes._defaultTemplate, text : {} };
		},

		/**
		 *	Move to the level the address names, if it is not the one on screen. Leaving a form that holds unsaved
		 *	input asks first (see Nino.admin.router.leave())
		 *
		 *	@param		{Array}		parts					The address behind the panel's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const target = Nino.admin.routes._target( parts );
			const view = Nino.admin.routes._view;

			if( target === null ? view === null : ( view !== null && view.kind === target.kind && view.uri === target.uri ) )
				return false;

			Nino.admin.router.leave( [ 'routes' ], view !== null, function() {
				if( target === null )
					Nino.admin.routes._showList();
				else
					Nino.admin.routes._open( target, false );
			}, Nino.admin.routes._showLevel );

			return true;
		},

		/**
		 *	Show the level that is on, and write it into the address
		 *
		 *	@return		void
		 */
		_showLevel : function() {

			if( Nino.admin.routes._view !== null )
				return Nino.admin.routes._showForm();

			Nino.admin.routes._showList();
		},

		/**
		 *	Call a routes/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "routes/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'routes/'+ endpoint, payload, callback );
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
		 *	stood in the form that is hidden now (see Nino.adminUi.showLevel()). What
		 *	a person opens goes through _open(), and Back through the back link
		 *
		 *	@param		{Element|null}	[opener]	Back: the control that opened the form that is left
		 *
		 *	@return		void
		 */
		_showList : function( opener ) {

			Nino.admin.routes._view = null;
			Nino.admin.router.set( 'routes', [] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'routes-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'routes-list', opener );
		},

		/**
		 *	Show the form again as it stands, at the address it has
		 *
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {

			Nino.admin.router.set( 'routes', Nino.admin.routes._parts() );
			Nino.adminUi.showLevel( 'routes-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	The address of the form that is open, behind the panel's name
		 *
		 *	@return		{Array}
		 */
		_parts : function() {

			const view = Nino.admin.routes._view;

			if( view === null )
				return [];

			return view.kind === 'new' ? [ '@new' ] : ( view.kind === 'feature' ? [ 'feature', view.uri ] : [ view.uri ] );
		},

		/**
		 *	The link of a row of the list, as the list draws it now: the list is drawn
		 *	again behind an open form after a save, and the row that was pressed is
		 *	gone with the old drawing
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( key ) {

			const row = Array.from( dc.getElementById('routes-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === key } );

			return row === undefined ? null : row.querySelector('a');
		},

		/**
		 *	The control Back returns the focus to from the form that is open: the row of
		 *	the route, or the button that opened the form of a new one
		 *
		 *	@return		{Element|null}
		 */
		_backTarget : function() {

			const view = Nino.admin.routes._view;

			if( view === null || view.kind === 'new' )
				return Nino.admin.routes._opener;

			return Nino.admin.routes._rowOf( ( view.kind === 'feature' ? 'feature:' : '' )+ view.uri );
		},

		/**
		 *	The row of a page in the list: its name over its addresses, a link that opens
		 *	the form, and the buttons at the end. A page that is routed at runtime has
		 *	none of the buttons - no menu order to change, nothing to delete
		 *
		 *	@param		{Object}		entry					One entry from _pages or _runtime
		 *	@param		{string}		kind					'page' or 'feature'
		 *	@param		{Array}			[actions]			Small buttons
		 *
		 *	@return		{Object}									A row for list()
		 */
		_row : function( entry, kind, actions ) {

			const parts = kind === 'feature' ? [ 'feature', entry.uri ] : [ entry.uri ];

			return {
				key			: ( kind === 'feature' ? 'feature:' : '' )+ entry.uri,
				title		: Nino.admin.routes._pageName( entry ),
				detail	: Nino.admin.routes._paths( entry ),
				href		: Nino.admin.router.href( 'routes', parts ),
				actions : actions,
				onOpen	: function( row ) {
					Nino.admin.router.go( 'routes', parts );
					Nino.admin.routes._open( { kind : kind, uri : entry.uri, entry : entry }, true );
				},
			};
		},

		/**
		 *	Render the page list, plus the "New route" button in the bar below it. A
		 *	row is the page's name over its addresses, a link that opens its form
		 *	and, at the end, the link to the page itself and the buttons that
		 *	reorder the persisted list - a page goes with all of its routes - which is
		 *	what orders the menus (equal menu priorities follow route order, see
		 *	Admin.php's apiMove())
		 *
		 *	@return		void
		 */
		_renderList : function( focus ) {

			const wrap = dc.getElementById('routes-list');
			const moves = {};
			wrap.innerHTML = '';

			const pages = Nino.admin.routes._pages;

			if( pages.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/routes/empty') ) );
			else
				wrap.appendChild( Nino.adminUi.list( pages.map( function( entry, index ) {

					const actions = [];
					const open = Nino.admin.routes._openLink( entry );

					if( open !== null )
						actions.push( open );

					const up = Nino.adminUi.iconButton( 'arrow-up', Nino.content.getText('/_admin/routes/label/moveup'), function() { Nino.admin.routes._move( entry.uri, 'up' ) }, { small : true, disabled : index === 0 } );
					const down = Nino.adminUi.iconButton( 'arrow-down', Nino.content.getText('/_admin/routes/label/movedown'), function() { Nino.admin.routes._move( entry.uri, 'down' ) }, { small : true, disabled : index === pages.length - 1 } );

					moves[entry.uri] = { up : up, down : down };
					actions.push( up, down );

					return Nino.admin.routes._row( entry, 'page', actions );
				} ), { label : Nino.content.getText('/_admin/nav/routes') } ) );

			Nino.admin.routes._renderRuntimeList( wrap );

			// The line a refused move reports into. Beside the list rather than
			// in place of it: the rows are still every route there is, and they
			// carry the buttons the next attempt is made with
			const msg = dc.createElement('p');
			msg.id = 'routes-list-msg';
			Nino.admin.routes._listLine = Nino.adminUi.status( msg );
			wrap.appendChild( msg );

			const addBtn = Nino.adminUi.button( Nino.content.getText('/_admin/routes/label/new'), function() {
				Nino.admin.router.go( 'routes', [ '@new' ] );
				Nino.admin.routes._open( { kind : 'new', uri : '', entry : Nino.admin.routes._blank() }, true, addBtn );
			} );
			wrap.appendChild( Nino.adminUi.actionBar( { list : true, primary : addBtn } ).el );

			// After a move the focus stays on the button that was pressed, which stands in the row the page moved to; at the edge of the
			// list that button is locked and the other one takes it
			if( focus !== undefined && moves[focus.uri] !== undefined ) {
				const own = moves[focus.uri][focus.direction];
				const other = moves[focus.uri][focus.direction === 'up' ? 'down' : 'up'];

				( own.disabled === true ? other : own ).focus();
			}
		},

		/**
		 *	The link that opens the page itself in a tab of its own, as a small icon: a real link with a name, so
		 *	that it is opened, copied and middle-clicked like one. Only for a path that is a path below this site - a
		 *	'//host' would be read as another origin
		 *
		 *	@param		{Object}		entry					One entry from _pages
		 *
		 *	@return		{Element|null}
		 */
		_openLink : function( entry ) {

			if( entry.httpUri.charAt(0) !== '/' || entry.httpUri.charAt(1) === '/' )
				return null;

			const name = Nino.content.getText('/_admin/routes/label/open');
			const link = Nino.adminUi.el( 'a', 'nino-admin-btn-icon nino-admin-btn-icon--small' );

			link.href = Nino.admin.assetUrl( entry.httpUri );
			link.target = '_blank';
			link.rel = 'noopener';
			link.title = name;
			link.setAttribute( 'aria-label', name );
			link.appendChild( Nino.adminUi.icon( 'external-link' ) );

			return link;
		},

		/**
		 *	The pages features route at runtime, under the persisted ones: a
		 *	row is the page's name over its path and opens the form of its
		 *	texts, nothing more - no menu order, no delete, because there is no
		 *	route of the project's to move or remove. What these pages are is
		 *	behind the (?) at the title of the group. Nothing is drawn when no
		 *	feature routes a page
		 *
		 *	@param		{Element}		wrap					The list's container
		 *
		 *	@return		void
		 */
		_renderRuntimeList : function( wrap ) {

			if( Nino.admin.routes._runtime.length === 0 )
				return;

			const group = Nino.adminUi.list( Nino.admin.routes._runtime.map( function( entry ) {
				return Nino.admin.routes._row( entry, 'feature' );
			} ), { title : Nino.content.getText('/_admin/routes/runtime/title') } );

			Nino.adminUi.sectionHelp( group.querySelector('h2'), group, Nino.content.getText('/_admin/routes/runtime/help') );
			wrap.appendChild( group );
		},

		/**
		 *	The paths a page is reached at: every one of a page that has an
		 *	address per language, the one of any other. A page of the project
		 *	says them by language - its 'httpUris' is a map -, a page a feature
		 *	routes has a list of them
		 *
		 *	@param		{Object}	entry					One entry from _pages or _runtime
		 *
		 *	@return		{string}
		 */
		_paths : function( entry ) {

			const uris = entry.httpUris || {};
			const list = Object.keys( uris ).map( function( key ) { return uris[key] } );

			return list.length > 0 ? list.join(', ') : entry.httpUri;
		},

		/**
		 *	The address a page is reached at on the website, for a window that
		 *	follows what is saved (see Nino.admin.touch()): the one of the
		 *	language the panel is editing, else the one of every language, else
		 *	the first there is. A path with its leading slash, or '' where the
		 *	page has none or is routed by a pattern, which no window can go to
		 *
		 *	@param		{Object}	httpUris			Language => address, '' for the address of every language
		 *
		 *	@return		{string}
		 */
		_publicPath : function( httpUris ) {

			const wanted = [ Nino.admin.routes._language(), '' ].concat( Object.keys( httpUris ) );
			const found = wanted.find( function( key ) { return typeof httpUris[key] === 'string' && httpUris[key].trim() !== '' } );

			if( found === undefined )
				return '';

			const address = httpUris[found].trim();

			// A page a feature routes by a pattern - /blog/* - is no address
			if( address.indexOf('*') !== -1 )
				return '';

			return address.charAt(0) === '/' ? address : '/'+ address;
		},

		/**
		 *	What a page is called in the list: its name in the language the
		 *	workbench is on, else in the first language that has one, else its
		 *	path - a page with no name at all is still a row somebody can open
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{string}
		 */
		_pageName : function( entry ) {

			const nameIn = function( locale ) {
				return String( ( ( entry.text || {} )[locale] || {} ).name || '' ).trim();
			};

			const locales = [ Nino.admin.routes._language() ].concat( Nino.admin.routes._locales );

			for( let i = 0; i < locales.length; i++ )
				if( locales[i] !== '' && nameIn( locales[i] ) !== '' )
					return nameIn( locales[i] );

			return entry.httpUri;
		},

		/**
		 *	The on-disk template a route body names, when it names exactly
		 *	one - mirrors Admin.php's _templateFromBody(), which is
		 *	what actually decides whether a save may rewrite the body
		 *
		 *	@param		{string}	body
		 *
		 *	@return		{string|null}		Null if body isn't a plain template reference
		 */
		_templateFromBody : function( body ) {
			const match = /^\[template \/templates\/([A-Za-z0-9._-]+)\]$/.exec( String( body ).trim() );
			return match !== null ? match[1] : null;
		},

		/**
		 *	Swap one page with its neighbor and re-render - see Admin.php's
		 *	apiMove(). A move that is made says so as a toast: it is saved at once
		 *
		 *	@param		{string}	uri						The Element-URI of the page
		 *	@param		{string}	direction			'up' | 'down'
		 *
		 *	@return		void
		 */
		_move : function( uri, direction ) {
			Nino.admin.routes._apiCall( 'move', { uri : uri, direction : direction }, function( status, response ) {

				/*	A move the server refused leaves the order on screen a guess:
					whatever the reason - a route somebody else has deleted since
					this list was drawn, a config.php that could not be written - it
					is read back from the server and the reason is put under the
					list, where the row's buttons are for the next attempt	*/
				if( status !== 200 || response === null ) {

					Nino.admin.routes._apiCall( 'list', {}, function( listStatus, listResponse ) {

						if( listStatus === 200 && listResponse !== null ) {
							Nino.admin.routes._pages = listResponse.pages;
							Nino.admin.routes._renderList();
						}

						// After the re-render, because that builds the line anew
						Nino.admin.routes._showMoveError( status, response );
					} );
					return;
				}

				Nino.admin.touch( 'routes' );

				Nino.admin.routes._pages = response.pages;
				Nino.admin.routes._renderList( { uri : uri, direction : direction } );
				Nino.adminUi.say( Nino.content.getText('/_admin/routes/msg/moved') );
			} );
		},

		/**
		 *	Report a move the server refused, in the line below the list
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showMoveError : function( status, response ) {

			const line = Nino.admin.routes._listLine;

			// No list rendered means no line under it, and then the error has to
			// go somewhere rather than nowhere
			if( line === null || dc.getElementById('routes-list-msg') === null )
				return Nino.admin.routes._showError( dc.getElementById('routes-list'), status, response );

			line.error( status, response, '/_admin/common/error/save' );
		},

		/**
		 *	Open a form: the route of the project, a page a feature routes or the empty one of a new route
		 *
		 *	@param		{Object}				target		{ kind, uri, entry }
		 *	@param		{boolean}				opened		The person opened it: the focus goes into the form
		 *	@param		{Element}				[opener]	The button that was pressed, where it is no row
		 *
		 *	@return		void
		 */
		_open : function( target, opened, opener ) {

			Nino.admin.routes._opener = opener ?? null;
			Nino.admin.routes._draw( target.kind, target.entry );
			Nino.admin.routes._showForm( opened );
			Nino.admin.routes._snapshot();
		},

		/**
		 *	What the form holds now is what is saved
		 *
		 *	@return		void
		 */
		_snapshot : function() {

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'routes' );
		},

		/**
		 *	The mark after the name of a field that cannot be left empty is the block's star; what a field says is
		 *	its label. The control a block drew inside the label it returned
		 *
		 *	@param		{Element}		label					What textField(), selectField() and the like returned
		 *	@param		{string}		field					The name the server marks the control by where it refuses it
		 *
		 *	@return		{Element}									The control
		 */
		_control : function( label, field ) {

			const control = label.querySelector('input, select, textarea');
			control.dataset.field = field;

			return control;
		},

		/**
		 *	Draw the form of a page: the way back, the section of the route with the Element URI, the switch for one
		 *	Http URI for every language and the address - or the rows of the languages -, the template, the status
		 *	code and, while the Navigation module is active, the navigations; the section of the content with the name
		 *	and title of every language and the description of the language the workbench edits; and the bar with
		 *	Delete at the left (where there is a page to delete) and Save at the right edge. A page a feature routes has
		 *	the path, read-only, and the content. The form checks its required fields itself (see _save()), so the
		 *	browser's own bubble - in the browser's language, not the workbench's - never gets to speak
		 *
		 *	@param		{string}		kind					'page', 'new' or 'feature'
		 *	@param		{Object}		entry					The page the form is for, or the empty one of a new route
		 *
		 *	@return		void
		 */
		_draw : function( kind, entry ) {

			const routes = Nino.admin.routes;
			const wrap = dc.getElementById('routes-form');
			const work = routes._language();
			const view = { kind : kind, uri : kind === 'new' ? '' : entry.uri, entry : entry, work : work, descriptions : {} };

			wrap.innerHTML = '';
			routes._view = view;

			routes._locales.forEach( function( locale ) {
				view.descriptions[locale] = String( ( ( entry.text || {} )[locale] || {} ).description || '' );
			} );

			const back = Nino.adminUi.contextBar( { back : { to : wrap, onClick : function() {
				Nino.admin.router.go( 'routes', [] );
				Nino.admin.routes._showList( Nino.admin.routes._backTarget() );
			} } } );
			wrap.appendChild( back );
			Nino.adminUi.localeBadge( work, back );

			const form = dc.createElement('form');
			form.noValidate = true;

			// Both sections get their card look from the design system's `fieldset` rule
			const pageFieldset = dc.createElement('fieldset');
			pageFieldset.appendChild( Nino.adminUi.el( 'legend', '', Nino.content.getText('/_admin/routes/label/route') ) );

			if( kind === 'feature' )
				routes._drawPath( pageFieldset, entry );
			else
				routes._drawRoute( pageFieldset, view, entry );

			form.appendChild( pageFieldset );

			const contentFieldset = dc.createElement('fieldset');
			const contentLegend = contentFieldset.appendChild( Nino.adminUi.el( 'legend', '', Nino.content.getText('/_admin/routes/label/content') ) );
			Nino.adminUi.sectionHelp( contentLegend, contentFieldset, Nino.content.getText('/_admin/routes/help/content') );

			view.name = Nino.adminUi.localeRows( { key : 'name', label : Nino.content.getText('/_admin/routes/label/name'), required : true, locales : routes._locales,
				values : routes._valuesOf( entry, 'name' ), placeholder : Nino.content.getText('/_admin/routes/placeholder/name'), help : Nino.content.getText('/_admin/routes/help/name') } );
			view.title = Nino.adminUi.localeRows( { key : 'title', label : Nino.content.getText('/_admin/routes/label/title'), required : true, locales : routes._locales,
				values : routes._valuesOf( entry, 'title' ), placeholder : Nino.content.getText('/_admin/routes/placeholder/title'), help : Nino.content.getText('/_admin/routes/help/title') } );

			[ view.name, view.title ].forEach( function( rows, at ) {
				Object.keys( rows.controls ).forEach( function( locale ) { rows.controls[locale].dataset.field = at === 0 ? 'name' : 'title' } );
				contentFieldset.appendChild( rows.el );
			} );

			const description = Nino.adminUi.textareaField( { key : 'description', label : Nino.content.getText('/_admin/routes/label/description'), rows : 3,
				value : view.descriptions[work] ?? '', help : Nino.content.getText('/_admin/routes/help/description') } );
			view.description = routes._control( description, 'description' );
			contentFieldset.appendChild( description );

			form.appendChild( contentFieldset );

			// The bar is the one of every form (actionBar()): Delete at the left where there is a page to delete, Save at the right edge
			const deleteBtn = kind === 'page' ? Nino.adminUi.button( Nino.content.getText('/_admin/routes/label/delete'), function() { Nino.admin.routes._delete() }, { kind : 'danger' } ) : null;
			const msg = dc.createElement('p');
			msg.id = 'routes-form-msg';

			const bar = Nino.adminUi.actionBar( { danger : deleteBtn, status : { line : msg, fresh : kind === 'new' }, primary : Nino.adminUi.button( Nino.content.getText( kind === 'new' ? '/_admin/routes/label/create' : '/_admin/common/label/save' ), null, { type : 'submit' } ) } );
			routes._line = bar.line;
			view.bar = bar;
			// A bar with a status line shows no other mark of unsaved input: the line follows the form
			routes._line.bind( form );

			form.appendChild( bar.el );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.routes._save() } );

			wrap.appendChild( form );
		},

		/**
		 *	One value of every language, for localeRows()
		 *
		 *	@param		{Object}		entry
		 *	@param		{string}		field					'name' or 'title'
		 *
		 *	@return		{Object}									Language => value
		 */
		_valuesOf : function( entry, field ) {

			const values = {};

			Nino.admin.routes._locales.forEach( function( locale ) {
				values[locale] = String( ( ( entry.text || {} )[locale] || {} )[field] || '' );
			} );

			return values;
		},

		/**
		 *	The path of a page a feature routes: shown, not changed - the feature decides it
		 *
		 *	@param		{Element}		fieldset
		 *	@param		{Object}		entry
		 *
		 *	@return		void
		 */
		_drawPath : function( fieldset, entry ) {

			const path = Nino.adminUi.textField( { label : Nino.content.getText('/_admin/routes/label/httpuri'), id : 'routes-form-runtime-path', value : Nino.admin.routes._paths( entry ),
				help : Nino.content.getText('/_admin/routes/runtime/path') } );

			path.querySelector('input').readOnly = true;
			fieldset.appendChild( path );
		},

		/**
		 *	The fields of the section of the route of a page of the project: Element URI, the switch for one Http
		 *	URI for every language and the address, template, status code and the navigations
		 *
		 *	@param		{Element}		fieldset
		 *	@param		{Object}		view
		 *	@param		{Object}		entry
		 *
		 *	@return		void
		 */
		_drawRoute : function( fieldset, view, entry ) {

			const routes = Nino.admin.routes;

			const uri = Nino.adminUi.textField( { label : Nino.content.getText('/_admin/routes/label/uri'), id : 'routes-form-uri', value : entry.uri || '', required : true,
				placeholder : Nino.content.getText('/_admin/routes/placeholder/uri'), help : Nino.content.getText('/_admin/routes/help/uri') } );
			view.uriInput = routes._control( uri, 'uri' );
			fieldset.appendChild( uri );

			routes._addresses = routes._addressesOf( entry );

			const oneSwitch = Nino.adminUi.switchField( {
				key 		: 'one-address',
				checked : routes._addresses.one,
				label 	: Nino.content.getText('/_admin/routes/label/one-address'),
				help 		: Nino.content.getText('/_admin/routes/hint/one-address'),
			} );
			oneSwitch.querySelector('input').addEventListener( 'change', function( ev ) { Nino.admin.routes._switchAddresses( ev.target.checked ) } );
			fieldset.appendChild( oneSwitch );

			const addressWrap = dc.createElement('div');
			addressWrap.id = 'routes-form-addresses';
			addressWrap.className = 'nino-admin-fieldgrid';
			view.addressWrap = addressWrap;
			routes._renderAddresses( addressWrap );
			fieldset.appendChild( addressWrap );

			// A route with no template of its own to start on - a new one in a
			// project without a blank page, or one whose file is gone - makes
			// the person choose: the first file in the list is not a proposal,
			// it is the one that happens to sort first. A body that names its
			// template at runtime has its own option, and the select is locked
			const fixedBody = entry.body !== undefined && entry.body !== '' && routes._templateFromBody( entry.body ) === null;
			const options = routes._templates.map( function( name ) { return { value : name, label : name } } );
			const asking = fixedBody === false && routes._templates.indexOf( entry.template ) === -1;

			if( fixedBody === true )
				options.unshift( { value : '', label : Nino.content.getText('/_admin/routes/label/runtime') } );
			else if( asking === true )
				options.unshift( { value : '', label : Nino.content.getText('/_admin/routes/label/choose') } );

			const template = Nino.adminUi.selectField( { key : 'template', label : Nino.content.getText('/_admin/routes/label/template'), id : 'routes-form-template', options : options,
				value : fixedBody === true || asking === true ? '' : entry.template,
				help : fixedBody === true ? Nino.adminUi.format( Nino.content.getText('/_admin/routes/hint/runtime'), entry.body ) : Nino.content.getText('/_admin/routes/help/template') } );

			// The star is the field's, a placeholder is a choice to make and no choice, and the select that the route's body decides is locked
			view.template = routes._control( template, 'template' );
			view.template.setAttribute( 'aria-required', 'true' );
			// The star stands after the words of the name, where the block puts it, and before the (?)
			const templateName = template.querySelector('span');
			( templateName.querySelector('.nino-admin-field-text') ?? templateName ).appendChild( routes._star() );

			if( asking === true )
				view.template.querySelector('option').disabled = true;

			view.template.disabled = fixedBody === true;
			fieldset.appendChild( template );

			const status = Nino.adminUi.textField( { key : 'status', type : 'number', label : Nino.content.getText('/_admin/routes/label/status'), id : 'routes-form-status',
				value : String( entry.statusCode || 200 ), help : Nino.content.getText('/_admin/routes/help/status') } );
			view.status = routes._control( status, 'statusCode' );
			view.status.min = '100';
			view.status.max = '599';
			fieldset.appendChild( status );

			// One tick per navigation the project registered (/nino/html/navs), named by its name where it has one and
			// by its key behind it, quietly - an empty list means the Navigation module is inactive and no menu field is
			// offered at all. What a page is a member of lives on its route, which is what actually renders it (see
			// \Nino\Modules\Navigation::routeLines())
			view.navs = null;

			if( routes._navs.length > 0 ) {

				view.navs = Nino.adminUi.checklist( { key : 'navs', label : Nino.content.getText('/_admin/routes/label/navs'), help : Nino.content.getText('/_admin/routes/help/navs'),
					value : entry.navs || [], options : routes._navs.map( function( key ) {
						const name = routes._navNames[key] ?? '';
						return name === '' ? { value : key, label : key } : { value : key, label : name, state : key };
					} ) } );
				fieldset.appendChild( view.navs.el );
			}
		},

		/**
		 *	The mark after the name of a field that cannot be left empty - drawn
		 *	aria-hidden, the control says it with aria-required. selectField() has none of its own
		 *
		 *	@return		{Element}
		 */
		_star : function() {
			const star = dc.createElement('span');
			star.className = 'nino-admin-required';
			star.setAttribute( 'aria-hidden', 'true' );
			star.textContent = '*';
			return star;
		},

		/**
		 *	What the address fields of a page start from: the one field for
		 *	every language where the page has one address for them all - a new
		 *	page has none yet - and a row per language where it has one for
		 *	each. 'shared' is what the one field starts from, 'bound' the
		 *	address each language has of its own, empty for one that has none
		 *
		 *	@param		{Object}		entry					One entry from _pages, or the empty one of a new page
		 *
		 *	@return		{Object}									{ one, shared, bound } - bound is locale => address
		 */
		_addressesOf : function( entry ) {

			const map = entry.httpUris || {};
			const keys = Object.keys( map );
			const bound = {};

			Nino.admin.routes._locales.forEach( function( locale ) {
				bound[locale] = map[locale] ?? '';
			} );

			return { one : keys.length === 0 || ( keys.length === 1 && keys[0] === '' ), shared : map[''] ?? '', bound : bound };
		},

		/**
		 *	Draw the address fields into their container: the one field, or a
		 *	row per language - one that has no address of its own yet starts
		 *	from the address of every language, which is what it is reached
		 *	at until it is given one
		 *
		 *	@param		{Element}		wrap					The container of the fields
		 *
		 *	@return		void
		 */
		_renderAddresses : function( wrap ) {

			const routes = Nino.admin.routes;
			const addresses = routes._addresses;
			const label = Nino.content.getText('/_admin/routes/label/httpuri');
			const help = Nino.content.getText('/_admin/routes/help/httpuri');
			const placeholder = Nino.content.getText('/_admin/routes/placeholder/httpuri');

			wrap.innerHTML = '';
			routes._view.address = null;
			routes._view.addressRows = null;

			if( addresses.one === true ) {
				const field = Nino.adminUi.textField( { label : label, id : 'routes-form-http-uri', value : addresses.shared, required : true, placeholder : placeholder, help : help } );
				const input = routes._control( field, 'httpUri' );
				input.dataset.address = '';
				routes._view.address = input;
				wrap.appendChild( field );
				return;
			}

			const values = {};
			routes._locales.forEach( function( locale ) { values[locale] = addresses.bound[locale] || addresses.shared } );

			const rows = Nino.adminUi.localeRows( { key : 'address', label : label, required : true, locales : routes._locales, values : values, placeholder : placeholder, help : help } );

			Object.keys( rows.controls ).forEach( function( locale ) {
				rows.controls[locale].dataset.address = locale;
				rows.controls[locale].dataset.field = 'httpUri-'+ locale;
			} );

			routes._view.addressRows = rows;
			wrap.appendChild( rows.el );
		},

		/**
		 *	The address controls on screen, in the order they stand in: [ { locale, value } ], locale '' for the one field
		 *
		 *	@return		{Array}
		 */
		_addressFields : function() {

			const view = Nino.admin.routes._view;

			if( view.address !== null )
				return [ { locale : '', value : view.address.value, control : view.address } ];

			return view.addressRows.locales.map( function( locale ) {
				return { locale : locale, value : view.addressRows.value( locale ), control : view.addressRows.controls[locale] };
			} );
		},

		/**
		 *	Turn the switch: what the fields on screen hold is kept, and the
		 *	other kind of field is drawn from it. Turned to one address for
		 *	every language, the field starts from the first address a language
		 *	has where none was given for all of them
		 *
		 *	@param		{boolean}		one						Whether the page has one address for every language now
		 *
		 *	@return		void
		 */
		_switchAddresses : function( one ) {

			const routes = Nino.admin.routes;
			const addresses = routes._addresses;

			routes._addressFields().forEach( function( field ) {
				if( field.locale === '' )
					addresses.shared = field.value;
				else
					addresses.bound[field.locale] = field.value;
			} );

			if( one === true && addresses.shared === '' )
				addresses.shared = routes._locales.map( function( locale ) { return addresses.bound[locale] } ).find( function( value ) { return value !== '' } ) ?? '';

			addresses.one = one;
			routes._renderAddresses( routes._view.addressWrap );
		},

		/**
		 *	The map of language to address that a save sends for the address fields
		 *	on screen. Fields that hold one address are the address of those
		 *	languages together, and a request gives an address once: they are sent
		 *	as the empty key, which is the address of every language that has none
		 *	of its own. A page has one such address - a second pair of fields that
		 *	hold one is sent as it stands, and the server refuses it. Two fields
		 *	hold one address when it reads as one the way the server reads it: a
		 *	slash in front, none behind, no blank around (see Admin.php's
		 *	_normalizeUri())
		 *
		 *	@param		{Array}			fields				[ { locale, value } ] in the order the fields stand in; locale is '' for the field of every language
		 *
		 *	@return		{Object}									{ httpUris, folded } - folded is the languages whose fields were sent as the empty key
		 */
		_addressMap : function( fields ) {

			const read = function( value ) {
				let address = String( value ).trim();
				if( address !== '' && address[0] !== '/' )
					address = '/'+ address;
				return address === '/' ? address : address.replace( /\/+$/, '' );
			};
			const held = fields.filter( function( field ) { return field.locale !== '' } ).map( function( field ) { return read( field.value ) } );
			const twice = held.find( function( value, index ) { return value !== '' && held.indexOf( value ) === index && held.lastIndexOf( value ) !== index } );
			const httpUris = {};
			const folded = [];

			if( twice !== undefined )
				httpUris[''] = twice;

			fields.forEach( function( field ) {
				if( twice !== undefined && field.locale !== '' && read( field.value ) === twice )
					folded.push( field.locale );
				else
					httpUris[field.locale] = field.value;
			} );

			return { httpUris : httpUris, folded : folded };
		},

		/**
		 *	A refusal of a save, with the field it names made one the form draws.
		 *	The empty key is the address of the fields that were sent as one (see
		 *	_addressMap()), and no field of the form is called so while the
		 *	languages have one each: the refusal is for the first of those fields
		 *
		 *	@param		{*}					response			The answer of the server
		 *	@param		{Array}			folded				The languages whose fields were sent as the empty key
		 *
		 *	@return		{*}												The answer, naming the field of the first of them where it named the empty key's
		 */
		_refusedField : function( response, folded ) {

			if( folded.length === 0 || response === null || typeof response !== 'object' || response.field !== 'httpUri' )
				return response;

			return Object.assign( {}, response, { field : 'httpUri-'+ folded[0] } );
		},

		/**
		 *	Say a refusal of the server where it belongs: in the line of the bar and, where it names a field of the
		 *	form, under that field - a language's row for the name, the title and the addresses - with the focus
		 *	there. A sentence that names a language by its code names it as the workbench does
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response			The answer of the server
		 *	@param		{Array}			folded				The languages whose address fields were sent as one (see _addressMap())
		 *
		 *	@return		void
		 */
		_refused : function( status, response, folded ) {

			const routes = Nino.admin.routes;
			const view = routes._view;
			const form = dc.getElementById('routes-form');
			const named = routes._refusedField( response, folded );

			Nino.adminUi.clearFieldErrors( form );

			if( named === null || typeof named !== 'object' ) {
				routes._line.error( status, named, '/_admin/common/error/save' );
				return;
			}

			// The field is marked below, where it is: the line only says the sentence
			const worded = routes._worded( named );
			const said = Object.assign( {}, worded );
			delete said.field;
			routes._line.error( status, said, '/_admin/common/error/save' );

			if( view === null || typeof named.field !== 'string' )
				return;

			const sentence = Nino.adminUi.api.errorText( status, worded, '/_admin/common/error/save' );
			const field = named.field;
			const locale = Array.isArray( named.params ) === true && typeof named.params[0] === 'string' ? named.params[0] : '';
			const own = { uri : view.uriInput, template : view.template, statusCode : view.status, httpUri : view.address };
			let control = null;

			if( field === 'name' || field === 'title' ) {

				const rows = field === 'name' ? view.name : view.title;

				if( Object.hasOwn( rows.controls, locale ) === true ) {
					rows.error( locale, sentence );
					rows.focus( locale );
				}
				return;
			}

			if( field === 'navs' && view.navs !== null ) {
				view.navs.error( sentence );
				view.navs.focus();
				return;
			}

			if( field.indexOf('httpUri-') === 0 && view.addressRows !== null ) {

				const code = field.slice( 'httpUri-'.length );

				if( Object.hasOwn( view.addressRows.controls, code ) === true ) {
					view.addressRows.error( code, sentence );
					view.addressRows.focus( code );
				}
				return;
			}

			control = Object.hasOwn( own, field ) === true ? own[field] : null;

			if( control === undefined || control === null )
				return;

			Nino.adminUi.fieldError( control.closest('label'), control, 'routes-error-'+ ( control.id || field ), sentence );
			control.focus();
		},

		/**
		 *	The answer of the server with the language it names written the way the workbench writes a language: a
		 *	refusal that says "de_DE" says "Deutsch"
		 *
		 *	@param		{Object}		response
		 *
		 *	@return		{Object}
		 */
		_worded : function( response ) {

			if( Array.isArray( response.params ) === false || typeof response.params[0] !== 'string' || Nino.admin.routes._locales.indexOf( response.params[0] ) === -1 )
				return response;

			return Object.assign( {}, response, { params : [ Nino.adminUi.localeName( response.params[0], Nino.admin.routes._locales ) ].concat( response.params.slice( 1 ) ) } );
		},

		/**
		 *	The texts of every language as a save sends them: name, title and description - the description of the
		 *	language the workbench edits is what the field holds, the others are what they were
		 *
		 *	@return		{Object}									Language => { name, title, description }
		 */
		_texts : function() {

			const view = Nino.admin.routes._view;
			const text = {};

			view.descriptions[view.work] = view.description.value;

			Nino.admin.routes._locales.forEach( function( locale ) {
				text[locale] = { name : view.name.value( locale ), title : view.title.value( locale ), description : view.descriptions[locale] ?? '' };
			} );

			return text;
		},

		/**
		 *	Say at a field that it is empty and must not be: the sentence under it, the
		 *	focus on the first one. A language's row says it at the row
		 *
		 *	@param		{Array}			missing			[ { say(), focus } ] in the order the fields stand in
		 *
		 *	@return		void
		 */
		_missing : function( missing ) {

			missing.forEach( function( entry ) { entry.say() } );
			missing[0].focus();
		},

		/**
		 *	Create or save the page currently open
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the page was written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const routes = Nino.admin.routes;
			const view = routes._view;

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = routes._line;
			const word = function( key ) { return Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), Nino.content.getText( key ) ) };

			/*	The required fields are checked here, before a request: the
				form is not validated by the browser (see _draw()), and
				a name or a title nobody wrote would render as the raw
				[[/_nino/webpage.../name]] on the page and in the menu. Every
				attempt starts without the marks of the one before; a field
				that fails says why where it stands, the first one takes the
				focus, and the one summary is the status line's, in the
				workbench's own words	*/
			Nino.adminUi.clearFieldErrors( dc.getElementById('routes-form') );

			const missing = [];
			const need = function( control, say ) {
				if( String( control.value ).trim() === '' )
					missing.push( { focus : function() { control.focus() }, say : say } );
			};
			const needRow = function( rows, locale, key ) {
				need( rows.controls[locale], function() { rows.error( locale, word( key ) ) } );
			};
			const needField = function( field, key ) {
				need( field, function() { Nino.adminUi.fieldError( field.closest('label'), field, 'routes-error-'+ field.id, word( key ) ) } );
			};

			// A page a feature routes has its texts and nothing else to fill in
			if( view.kind !== 'feature' ) {
				needField( view.uriInput, '/_admin/routes/label/uri' );
				routes._addressFields().forEach( function( field ) {
					if( field.locale === '' )
						needField( field.control, '/_admin/routes/label/httpuri' );
					else
						needRow( view.addressRows, field.locale, '/_admin/routes/label/httpuri' );
				} );

				if( view.template.disabled !== true )
					needField( view.template, '/_admin/routes/label/template' );
			}

			routes._locales.forEach( function( locale ) { needRow( view.name, locale, '/_admin/routes/label/name' ) } );
			routes._locales.forEach( function( locale ) { needRow( view.title, locale, '/_admin/routes/label/title' ) } );

			if( missing.length > 0 ) {
				routes._missing( missing );
				line.fail( Nino.content.getText('/_admin/routes/error/fields') );
				report( false );
				return;
			}

			line.saving();

			const text = routes._texts();

			if( view.kind === 'feature' )
				return routes._saveRuntime( text, report );

			// What is on screen: language => address, or '' for the one address of
			// every language - which is also the address of the languages whose
			// fields hold the same one
			const addressed = routes._addressMap( routes._addressFields() );
			const original = view.kind === 'new' ? '' : view.uri;

			routes._apiCall( 'save', {
				originalUri 		: original,
				uri 						: view.uriInput.value,
				httpUris 				: addressed.httpUris,
				template 				: view.template.value,
				statusCode 			: view.status.value,
				navs						: view.navs === null ? [] : view.navs.value(),
				text 						: text,
			}, function( status, response ) {

				if( status !== 200 || response === null ) {
					routes._refused( status, response, addressed.folded );
					report( false );
					return;
				}

				routes._saved( response, original, addressed, report );
			} );
		},

		/**
		 *	A page was written: the list is what the server answered, the form stays - a new route gets its address and
		 *	its Delete button, a route whose Element-URI changed the new one - and the window beside the workbench goes to
		 *	the page that was saved. What the form holds now is what is saved
		 *
		 *	@param		{Object}		response			{ pages }
		 *	@param		{string}		original			The Element-URI the page had, '' for a new one
		 *	@param		{Object}		addressed			What _addressMap() made of the fields
		 *	@param		{Function}	report				Called once with true
		 *
		 *	@return		void
		 */
		_saved : function( response, original, addressed, report ) {

			const routes = Nino.admin.routes;
			const view = routes._view;
			const before = routes._pages.map( function( page ) { return page.uri } );
			const kind = view.kind;

			routes._pages = response.pages;

			// A page that was created or whose Element-URI changed is the one the answer holds and the list did not
			const fresh = response.pages.find( function( page ) { return before.indexOf( page.uri ) === -1 } );
			const uri = fresh === undefined ? original : fresh.uri;

			// The page that was saved is the one a window beside this one goes to
			Nino.admin.touch( 'routes', routes._publicPath( addressed.httpUris ) );
			routes._renderList();

			const entry = routes._pages.find( function( page ) { return page.uri === uri } );

			if( kind === 'new' && entry !== undefined ) {

				// The form of the page that now exists: with its address and its Delete button
				routes._draw( 'page', entry );
				routes._showForm();
				routes._line.saved();
				routes._snapshot();
				routes._view.bar.primary.focus();
				report( true );
				return;
			}

			if( entry !== undefined ) {
				view.uri = uri;
				view.entry = entry;
			}

			view.kind = 'page';

			if( entry !== undefined )
				routes._showForm();

			routes._line.saved();
			routes._snapshot();
			report( true );
		},

		/**
		 *	Save the texts of the feature page currently open - routes/savetexts
		 *	writes the three /_nino/webpage keys per language and nothing else
		 *
		 *	@param		{Object}		text					Locale => { name, title, description }
		 *	@param		{Function}	report				Called once with true when the texts were written, false otherwise
		 *
		 *	@return		void
		 */
		_saveRuntime : function( text, report ) {

			const routes = Nino.admin.routes;
			const line = routes._line;
			const view = routes._view;

			routes._apiCall( 'savetexts', { uri : view.uri, text : text }, function( status, response ) {

				if( status !== 200 || response === null ) {
					routes._refused( status, response, [] );
					report( false );
					return;
				}

				routes._runtime = response.runtime || routes._runtime;

				const entry = routes._runtime.find( function( page ) { return page.uri === view.uri } );

				if( entry !== undefined )
					view.entry = entry;

				Nino.admin.touch( 'routes', routes._publicPath( { '' : view.entry.httpUri } ) );
				routes._renderList();
				line.saved();
				routes._snapshot();
				report( true );
			} );
		},

		/**
		 *	The entry of the page that is open, from the list the panel holds
		 *
		 *	@return		{Object|undefined}
		 */
		_currentEntry : function() {

			const view = Nino.admin.routes._view;

			return view === null ? undefined : Nino.admin.routes._pages.find( function( page ) { return page.uri === view.uri } );
		},

		/**
		 *	What a delete takes and what it leaves behind, as the two groups of the question: the addresses of the page,
		 *	and - built from what the list already holds - the page's texts (the keys of the languages that hold
		 *	a value, and the path fill) and the template file, shared with how many other routes, or with none. A
		 *	route that resolves its template at runtime names its body instead, there is no single file to name
		 *
		 *	@param		{Object}		entry
		 *
		 *	@return		{Array}										Groups for confirm()
		 */
		_details : function( entry ) {

			const routes = Nino.admin.routes;
			const fields = [];
			const locales = [];

			routes._locales.forEach( function( locale ) {
				const row = ( entry.text || {} )[locale] || {};
				let held = false;

				[ 'name', 'title', 'description' ].forEach( function( field ) {
					if( String( row[field] || '' ).trim() === '' )
						return;
					held = true;
					if( fields.indexOf( field ) === -1 )
						fields.push( field );
				} );

				if( held === true )
					locales.push( Nino.adminUi.localeName( locale, routes._locales ) );
			} );

			const base = '/_nino/webpage'+ entry.uri;
			const keys = [];
			if( fields.length > 0 )
				keys.push( base+ '/'+ fields.join('|')+ ' ('+ locales.join(', ')+ ')' );
			keys.push( base+ '/uri' );

			const stays = [ Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-texts'), keys.join(', ') ) ];

			if( entry.template === '' )
				stays.push( Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-body'), entry.body ) );
			else {
				const others = routes._pages.filter( function( page ) { return page.template === entry.template && page.uri !== entry.uri } ).length;

				if( others === 0 )
					stays.push( Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-template'), entry.template ) );
				else if( others === 1 )
					stays.push( Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-template-one'), entry.template ) );
				else
					stays.push( Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-template-shared'), entry.template, others ) );
			}

			return [
				{ title : Nino.content.getText('/_admin/routes/confirm/deleted'), lines : routes._addressLines( entry ) },
				{ title : Nino.content.getText('/_admin/routes/confirm/kept'), lines : stays },
			];
		},

		/**
		 *	The addresses a delete takes away, one line each
		 *
		 *	@param		{Object}		entry
		 *
		 *	@return		{Array}
		 */
		_addressLines : function( entry ) {

			const uris = entry.httpUris || {};
			const list = Object.keys( uris ).map( function( key ) { return uris[key] } );

			return list.length > 0 ? list : [ entry.httpUri ];
		},

		/**
		 *	Confirm, then delete the page currently open. Its routes are
		 *	removed - every address it has, which the question names; any
		 *	/_nino/webpage.../name,title,description,uri text meta is left in
		 *	place, same additive-only rule the rest of Nino follows - and so
		 *	is its template file, which is why the question says what stays
		 *	(see _details()). The person goes back to the list, which the page
		 *	has left
		 *
		 *	@return		void
		 */
		_delete : function() {

			const routes = Nino.admin.routes;
			const entry = routes._currentEntry();
			const uri = routes._view.uri;

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/routes/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/delete-left'), entry === undefined ? uri : routes._paths( entry ) ),
				details		: entry === undefined ? undefined : routes._details( entry ),
				yes				: Nino.content.getText('/_admin/routes/confirm/delete-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					const line = routes._line;
					line.idle( Nino.content.getText('/_admin/common/msg/deleting') );

					// The focus goes to the row after the one that goes, else the one before it
					const at = routes._pages.findIndex( function( page ) { return page.uri === uri } );
					const next = routes._pages[at + 1] ?? routes._pages[at - 1];

					routes._apiCall( 'delete', { uri : uri }, function( status, response ) {
						if( status !== 200 || response === null ) {
							line.error( status, response, '/_admin/common/error/delete' );
							return;
						}
						Nino.admin.touch( 'routes' );
						routes._pages = response.pages;
						routes._renderList();
						Nino.admin.router.go( 'routes', [] );
						routes._showList( next === undefined ? null : routes._rowOf( next.uri ) );
						Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/deleted') );
						routes._snapshot();
					} );
				},
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.routes.init );

	// The list names its pages, and the form describes its page, in the content language, whoever moved it (see Nino.admin.workLocale)
	Nino.admin.workLocale.follow( 'routes', Nino.admin.routes._followLocale );

	// The shell asks before anything throws the open page's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'routes', function() { return dc.getElementById('routes-form') }, function( done ) { Nino.admin.routes._save( done ) } );

})(window, document, document.documentElement, document.body);
