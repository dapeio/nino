

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Navigations" module: which menus exist, and which routes
 *													stand in each of them, in which order - the view the
 *													Routes module's per-page checkboxes can't give, since
 *													there a page only ever sees its own membership.
 *
 *													List + drill-down shape follows pages.js closely; the
 *													detail level's ↑/↓ buttons reorder one menu, and every
 *													action re-renders from the response rather than patching
 *													a local copy (see _admin/Admin.php's Navigations class,
 *													whose every action answers with the same payload).
 *
 *													No locale switch anywhere in here: a menu has nothing
 *													per-locale about it, and the wording it renders is each
 *													page's own /webpage<uri>/name key, edited under Routes.
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
		_active 		: true,
		_currentKey : null,
		_isNew 			: false,
		_ready 			: false,

		/**
		 *	Load the menus and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('navs-list') === null )
				return;

			Nino.admin.navs._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.navs._showError( dc.getElementById('navs-list'), status, response );

				Nino.admin.navs._apply( response );
				Nino.admin.navs._showList();
				Nino.admin.navs._ready = true;
			} );
		},

		/**
		 *	Re-show whichever level (list or one menu) is currently on
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.navs._ready === false )
				return Nino.admin.navs.init();

			if( dc.getElementById('navs-form').classList.contains('admin-hidden') === false )
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
			Nino.admin.navs._active 	= response.active;

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
		 *	Call a navs/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "navs/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'navs/'+ endpoint, data : JSON.stringify( payload ) } );
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
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			container.appendChild( p );
		},

		_showList : function() {
			Nino.admin.navs._currentKey = null;
			dc.getElementById('navs-list').classList.remove('admin-hidden');
			dc.getElementById('navs-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('navs-list').classList.add('admin-hidden');
			dc.getElementById('navs-form').classList.remove('admin-hidden');
		},

		/**
		 *	Render the menu list, plus a "New navigation" action below it -
		 *	one row per registered menu, reporting how many entries it holds
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('navs-list');
			wrap.innerHTML = '';

			// The registry is editable either way, but nothing renders from it
			// while the module that reads it is off - say so instead of leaving
			// the dialog looking broken
			if( Nino.admin.navs._active === false ) {
				const off = dc.createElement('p');
				off.className = 'nino-admin-hint';
				off.textContent = 'The Navigation module is not active - these menus are stored, but nothing renders them. Activate \\Nino\\Modules\\Navigation under Config.';
				wrap.appendChild( off );
			}

			if( Nino.admin.navs._navs.length === 0 ) {
				const p = dc.createElement('p');
				p.className = 'nino-admin-hint';
				p.textContent = 'No navigations yet - add one below.';
				wrap.appendChild( p );
			}

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			Nino.admin.navs._navs.forEach( function( nav ) {

				const li = dc.createElement('li');

				const link = dc.createElement('a');
				link.href = '#';
				link.textContent = nav.key+ ' ('+ nav.entries.length+ ')';
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.navs._openForm( nav ) } );
				li.appendChild( link );

				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'nino-admin-btn-primary';
			addBtn.textContent = 'New navigation';
			addBtn.addEventListener( 'click', function() { Nino.admin.navs._openForm( null ) } );
			wrap.appendChild( Nino.adminUi.listActions( [ addBtn ] ) );
		},

		/**
		 *	Open one menu, or a blank one to create
		 *
		 *	@param		{Object|null}	nav		One entry from _navs, or null to create new
		 *
		 *	@return		void
		 */
		_openForm : function( nav ) {

			Nino.admin.navs._isNew 			= nav === null;
			Nino.admin.navs._currentKey = nav ? nav.key : null;

			Nino.admin.navs._renderForm( nav || { key : Nino.admin.navs._freeKey(), entries : [] } );
			Nino.admin.navs._showForm();
		},

		/**
		 *	A menu key not yet taken - "nav", then "nav-2", "nav-3", …
		 *
		 *	@return		{string}
		 */
		_freeKey : function() {

			const taken = Nino.admin.navs._navs.map( function( nav ) { return nav.key } );

			if( taken.indexOf('nav') === -1 )
				return 'nav';

			let n = 2;
			while( taken.indexOf('nav-'+ n) !== -1 )
				n++;

			return 'nav-'+ n;
		},

		/**
		 *	Render one menu: back-link, its id, its running order, an add
		 *	picker, and save/delete
		 *
		 *	@param		{Object}	nav
		 *
		 *	@return		void
		 */
		_renderForm : function( nav ) {

			const wrap = dc.getElementById('navs-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.navs._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');

			const navFieldset = dc.createElement('fieldset');
			const navLegend = dc.createElement('legend');
			navLegend.textContent = 'Navigation';
			navFieldset.appendChild( navLegend );

			const keyLabel = dc.createElement('label');
			keyLabel.className = 'nino-admin-field';
			const keySpan = dc.createElement('span');
			keySpan.textContent = 'Navigation id - the name a template asks for, eg. [navigation nav="main"]';
			keyLabel.appendChild( keySpan );
			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.id = 'navs-form-key';
			keyInput.placeholder = 'Navigation id e.g. "footer"';
			keyInput.value = nav.key || '';
			keyInput.required = true;
			keyLabel.appendChild( keyInput );
			navFieldset.appendChild( keyLabel );

			// Renaming leaves templates alone on purpose - see Admin.php's
			// Navigations::apiSave() for why - so say where the other half of
			// a rename has to happen by hand
			if( Nino.admin.navs._isNew === false ) {
				const renameHint = dc.createElement('p');
				renameHint.className = 'nino-admin-hint';
				renameHint.textContent = 'Renaming updates the routes in this navigation, not the templates rendering it - update their [navigation nav="…"] argument yourself.';
				navFieldset.appendChild( renameHint );
			}

			form.appendChild( navFieldset );

			// The running order only exists once the menu does: a new one has
			// no key on the server to hang entries off yet
			if( Nino.admin.navs._isNew === false )
				form.appendChild( Nino.admin.navs._entriesFieldset( nav ) );

			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save';
			actions.appendChild( saveBtn );

			if( Nino.admin.navs._isNew === false ) {
				const deleteBtn = dc.createElement('button');
				deleteBtn.type = 'button';
				deleteBtn.className = 'nino-admin-btn-danger';
				deleteBtn.textContent = 'Delete navigation';
				deleteBtn.addEventListener( 'click', function() { Nino.admin.navs._delete() } );
				actions.appendChild( deleteBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'navs-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.navs._save() } );

			wrap.appendChild( form );
		},

		/**
		 *	One menu's entries in their running order, each with ↑/↓ and a
		 *	remove button, plus the picker that adds another one at the end
		 *
		 *	@param		{Object}	nav
		 *
		 *	@return		{Element}
		 */
		_entriesFieldset : function( nav ) {

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = 'Entries';
			fieldset.appendChild( legend );

			if( nav.entries.length === 0 ) {
				const p = dc.createElement('p');
				p.className = 'nino-admin-hint';
				p.textContent = 'No entries yet - add a route below.';
				fieldset.appendChild( p );
			}

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			nav.entries.forEach( function( entry, index ) {

				const li = dc.createElement('li');
				li.className = 'admin-page-row';

				const label = dc.createElement('span');
				label.className = 'admin-page-label';
				label.textContent = ( index + 1 )+ '. '+ entry.label+ '  ('+ entry.httpUri+ ')'+
					( entry.named === false ? '  - not named yet, so it stays out of the menu' : '' );
				li.appendChild( label );

				const moveWrap = dc.createElement('span');
				moveWrap.className = 'admin-page-move';

				const upBtn = dc.createElement('button');
				upBtn.type = 'button';
				upBtn.textContent = '↑';
				upBtn.title = 'Move up in this navigation';
				upBtn.disabled = index === 0;
				upBtn.addEventListener( 'click', function() { Nino.admin.navs._move( entry.httpUri, 'up' ) } );
				moveWrap.appendChild( upBtn );

				const downBtn = dc.createElement('button');
				downBtn.type = 'button';
				downBtn.textContent = '↓';
				downBtn.title = 'Move down in this navigation';
				downBtn.disabled = index === nav.entries.length - 1;
				downBtn.addEventListener( 'click', function() { Nino.admin.navs._move( entry.httpUri, 'down' ) } );
				moveWrap.appendChild( downBtn );

				const removeBtn = dc.createElement('button');
				removeBtn.type = 'button';
				removeBtn.textContent = '×';
				removeBtn.title = 'Remove from this navigation - the route itself stays';
				removeBtn.addEventListener( 'click', function() { Nino.admin.navs._unassign( entry.httpUri ) } );
				moveWrap.appendChild( removeBtn );

				li.appendChild( moveWrap );
				ul.appendChild( li );
			} );
			fieldset.appendChild( ul );

			// Every GET route can join a menu, not just the pages the Routes
			// module manages - one already in this menu is simply not offered
			// again
			const taken = nav.entries.map( function( entry ) { return entry.httpUri } );
			const free 	= Nino.admin.navs._routes.filter( function( route ) { return taken.indexOf( route.httpUri ) === -1 } );

			const addLabel = dc.createElement('label');
			addLabel.className = 'nino-admin-field';
			const addSpan = dc.createElement('span');
			addSpan.textContent = 'Add a route - it joins at the end';
			addLabel.appendChild( addSpan );

			const addSelect = dc.createElement('select');
			addSelect.id = 'navs-form-add';
			addSelect.disabled = free.length === 0;
			free.forEach( function( route ) {
				const option = dc.createElement('option');
				option.value = route.httpUri;
				option.textContent = route.label+ ' ('+ route.httpUri+ ')'+ ( route.named === false ? ' - not named yet' : '' );
				addSelect.appendChild( option );
			} );
			addLabel.appendChild( addSelect );
			fieldset.appendChild( addLabel );

			// A plain button, not an .nino-admin-btn-primary: that class is the
			// full-width primary a *list* level carries (see _renderList()),
			// and this one sits inside a form under a Save button it must not
			// compete with - same shape elementtypes.js's "Add field" has
			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.textContent = 'Add to navigation';
			addBtn.disabled = free.length === 0;
			addBtn.addEventListener( 'click', function() { Nino.admin.navs._assign( addSelect.value ) } );
			fieldset.appendChild( addBtn );

			return fieldset;
		},

		/**
		 *	Run one entry-level action against the menu currently open
		 *
		 *	@param		{string}	endpoint			'assign' | 'unassign' | 'move'
		 *	@param		{Object}	payload				Merged onto { key }
		 *
		 *	@return		void
		 */
		_entryAction : function( endpoint, payload ) {

			const msg = dc.getElementById('navs-form-msg');
			msg.textContent = 'Saving …';

			payload.key = Nino.admin.navs._currentKey;

			Nino.admin.navs._apiCall( endpoint, payload, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}
				Nino.admin.navs._apply( response );
			} );
		},

		/**
		 *	@param		{string}	httpUri
		 *	@param		{string}	direction			'up' | 'down'
		 *
		 *	@return		void
		 */
		_move : function( httpUri, direction ) {
			Nino.admin.navs._entryAction( 'move', { httpUri : httpUri, direction : direction } );
		},

		/**
		 *	@param		{string}	httpUri
		 *
		 *	@return		void
		 */
		_assign : function( httpUri ) {
			Nino.admin.navs._entryAction( 'assign', { httpUri : httpUri } );
		},

		/**
		 *	@param		{string}	httpUri
		 *
		 *	@return		void
		 */
		_unassign : function( httpUri ) {
			Nino.admin.navs._entryAction( 'unassign', { httpUri : httpUri } );
		},

		/**
		 *	Create the menu currently open, or rename it
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg = dc.getElementById('navs-form-msg');
			msg.textContent = 'Saving …';

			const key = dc.getElementById('navs-form-key').value;

			Nino.admin.navs._apiCall( 'save', {
				originalKey : Nino.admin.navs._isNew ? '' : Nino.admin.navs._currentKey,
				key 				: key,
			}, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}

				// Stay on the menu that was just created/renamed, under
				// whichever key it now has
				Nino.admin.navs._isNew 			= false;
				Nino.admin.navs._currentKey = key;
				Nino.admin.navs._apply( response );

				const saved = dc.getElementById('navs-form-msg');
				if( saved !== null )
					saved.textContent = 'Saved.';
			} );
		},

		/**
		 *	Confirm, then delete the menu currently open. It goes out of the
		 *	registry and off every route in it; the routes themselves stay
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( wn.confirm( 'Really delete the navigation "'+ Nino.admin.navs._currentKey+ '"? Its routes stay, only their membership goes.' ) === false )
				return;

			const msg = dc.getElementById('navs-form-msg');
			msg.textContent = 'Deleting …';

			Nino.admin.navs._apiCall( 'delete', { key : Nino.admin.navs._currentKey }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to delete.' );
					return;
				}
				Nino.admin.navs._showList();
				Nino.admin.navs._apply( response );
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.navs.init );

})(window, document, document.documentElement, document.body);
