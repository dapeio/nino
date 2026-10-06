

/**
 *	Nino										A compact filesystembased php framework
 *	Modules\Navigation		The module's /_admin panel, "Navigations": which menus exist,
 *													and which routes stand in each of them, in which order -
 *													ships with the module (see Modules\Navigation\Admin beside
 *													this file) and is loaded exactly while it is active. The view the
 *													Routes module's per-page checkboxes can't give, since
 *													there a page only ever sees its own membership.
 *
 *													List + drill-down shape follows the Routes panel's admin.js closely;
 *													one menu is a working copy. The detail level's ↑/↓/× and Add
 *													change that copy in the browser and write nothing; Save posts
 *													the complete running order in one request (see
 *													Modules\Navigation\Admin::apiSave()), whose answer is the
 *													complete state, so nothing is patched locally afterwards and
 *													no reload can disagree with the server.
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

		// The open menu's working copy: the entries as they stand on screen,
		// a clone of what the server holds until somebody changes it. _dirty
		// is whether they differ; _savedKey is the id the form was drawn with,
		// to tell a typed one from it
		_entries 		: [],
		_dirty 			: false,
		_savedKey 	: '',
		_entriesHost : null,

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
		 *	Re-show whichever level (list or one menu) is currently on. The panel
		 *	is shown again every time somebody comes back to it, and routes, names
		 *	and menus may have changed in Routes or Text in the meantime - so the
		 *	lists are read again, and the menu that is open is drawn again from
		 *	them, unless it holds changes that were not saved: that working copy
		 *	is never replaced (only the routes it can still pick from are)
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.navs._ready === false )
				return Nino.admin.navs.init();

			if( dc.getElementById('navs-form').classList.contains('admin-hidden') === false )
				Nino.admin.navs._showForm();
			else
				Nino.admin.navs._showList();

			Nino.admin.navs._apiCall( 'list', {}, function( status, response ) {

				// A read that fails leaves what is on screen as it is: the panel
				// is not the place to lose a working copy over a flaky request
				if( status !== 200 || response === null )
					return;

				if( Nino.admin.navs._isDirty() === true ) {
					Nino.admin.navs._navs 		= response.navs;
					Nino.admin.navs._routes 	= response.routes;
					Nino.admin.navs._active 	= response.active;
					Nino.admin.navs._renderList();
					Nino.admin.navs._renderEntries();
					return;
				}

				Nino.admin.navs._apply( response );
			} );
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

		_showList : function() {
			Nino.admin.navs._currentKey = null;
			Nino.admin.navs._dirty 			= false;
			Nino.admin.navs._entries 		= [];
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
				off.textContent = Nino.content.getText('/_admin/navs/inactive');
				wrap.appendChild( off );
			}

			if( Nino.admin.navs._navs.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/navs/empty') ) );

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
			addBtn.textContent = Nino.content.getText('/_admin/navs/label/new');
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
		 *	picker, and save/delete. The working copy starts as a clone of the
		 *	menu's entries
		 *
		 *	@param		{Object}	nav
		 *
		 *	@return		void
		 */
		_renderForm : function( nav ) {

			Nino.admin.navs._entries 	= nav.entries.map( function( entry ) { return Object.assign( {}, entry ) } );
			Nino.admin.navs._dirty 		= false;
			Nino.admin.navs._savedKey = nav.key || '';

			const wrap = dc.getElementById('navs-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.navs._back() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');

			const navFieldset = dc.createElement('fieldset');
			const navLegend = dc.createElement('legend');
			navLegend.textContent = Nino.content.getText('/_admin/navs/label/navigation');
			navFieldset.appendChild( navLegend );

			const keyLabel = dc.createElement('label');
			keyLabel.className = 'nino-admin-field';
			const keySpan = dc.createElement('span');
			keySpan.textContent = Nino.content.getText('/_admin/navs/label/key');
			keyLabel.appendChild( keySpan );
			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.id = 'navs-form-key';
			keyInput.placeholder = Nino.content.getText('/_admin/navs/placeholder/key');
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
				renameHint.textContent = Nino.content.getText('/_admin/navs/hint/rename');
				navFieldset.appendChild( renameHint );
			}

			form.appendChild( navFieldset );

			// The running order only exists once the menu does: a new one has
			// no key on the server to hang entries off yet. Its host stays
			// where it is while the entries inside it are drawn again, so a key
			// typed above is not lost to a click on an arrow below
			Nino.admin.navs._entriesHost = null;
			if( Nino.admin.navs._isNew === false ) {
				Nino.admin.navs._entriesHost = dc.createElement('div');
				form.appendChild( Nino.admin.navs._entriesHost );
				Nino.admin.navs._renderEntries();
			}

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			if( Nino.admin.navs._isNew === false ) {
				const deleteBtn = dc.createElement('button');
				deleteBtn.type = 'button';
				deleteBtn.className = 'nino-admin-btn-danger';
				deleteBtn.textContent = Nino.content.getText('/_admin/navs/label/delete');
				deleteBtn.addEventListener( 'click', function() { Nino.admin.navs._delete() } );
				actions.appendChild( deleteBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'navs-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.navs._save() } );

			wrap.appendChild( form );

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	One menu's entries in their running order, each with ↑/↓ and a
		 *	remove button, plus the picker that adds another one at the end -
		 *	drawn from the working copy, into the host that was made for it
		 *
		 *	@return		void
		 */
		_renderEntries : function() {

			const host = Nino.admin.navs._entriesHost;

			if( host === null )
				return;

			host.innerHTML = '';

			const entries = Nino.admin.navs._entries;

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/navs/label/entries');
			fieldset.appendChild( legend );

			if( entries.length === 0 )
				fieldset.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/navs/empty-entries') ) );

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			entries.forEach( function( entry, index ) {

				const li = dc.createElement('li');
				li.className = 'admin-page-row';

				const label = dc.createElement('span');
				label.className = 'admin-page-label';
				label.textContent = ( index + 1 )+ '. '+ entry.label+ '  ('+ entry.httpUri+ ')'+
					( entry.named === false ? '  '+ Nino.content.getText('/_admin/navs/label/unnamed') : '' );
				li.appendChild( label );

				const moveWrap = dc.createElement('span');
				moveWrap.className = 'admin-page-move';

				const upBtn = dc.createElement('button');
				upBtn.type = 'button';
				upBtn.textContent = '↑';
				upBtn.title = Nino.content.getText('/_admin/navs/label/moveup');
				// title is a hover hint, not a name - see Nino.adminUi.elementList()
				upBtn.setAttribute( 'aria-label', Nino.content.getText('/_admin/navs/label/moveup') );
				upBtn.disabled = index === 0;
				upBtn.addEventListener( 'click', function() { Nino.admin.navs._move( index, -1 ) } );
				moveWrap.appendChild( upBtn );

				const downBtn = dc.createElement('button');
				downBtn.type = 'button';
				downBtn.textContent = '↓';
				downBtn.title = Nino.content.getText('/_admin/navs/label/movedown');
				downBtn.setAttribute( 'aria-label', Nino.content.getText('/_admin/navs/label/movedown') );
				downBtn.disabled = index === entries.length - 1;
				downBtn.addEventListener( 'click', function() { Nino.admin.navs._move( index, 1 ) } );
				moveWrap.appendChild( downBtn );

				const removeBtn = dc.createElement('button');
				removeBtn.type = 'button';
				removeBtn.textContent = '×';
				removeBtn.title = Nino.content.getText('/_admin/navs/label/remove');
				removeBtn.setAttribute( 'aria-label', Nino.content.getText('/_admin/navs/label/remove') );
				removeBtn.addEventListener( 'click', function() { Nino.admin.navs._remove( index ) } );
				moveWrap.appendChild( removeBtn );

				li.appendChild( moveWrap );
				ul.appendChild( li );
			} );
			fieldset.appendChild( ul );

			// Every GET route can join a menu, not just the pages the Routes
			// module manages - one already in this menu is simply not offered
			// again. Nothing is preselected: the first route in the list is not
			// a decision, and Add waits for one
			const taken = entries.map( function( entry ) { return entry.httpUri } );
			const free 	= Nino.admin.navs._routes.filter( function( route ) { return taken.indexOf( route.httpUri ) === -1 } );

			const addLabel = dc.createElement('label');
			addLabel.className = 'nino-admin-field';
			const addSpan = dc.createElement('span');
			addSpan.textContent = Nino.content.getText('/_admin/navs/label/add');
			addLabel.appendChild( addSpan );

			// A plain button, not an .nino-admin-btn-primary: that class is the
			// full-width primary a *list* level carries (see _renderList()),
			// and this one sits inside a form under a Save button it must not
			// compete with - same shape the Elements panel's types.js "Add field" has
			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.textContent = Nino.content.getText('/_admin/navs/label/addbtn');
			addBtn.disabled = true;

			const addSelect = dc.createElement('select');
			addSelect.id = 'navs-form-add';
			addSelect.disabled = free.length === 0;

			const choose = dc.createElement('option');
			choose.value = '';
			choose.textContent = Nino.content.getText('/_admin/navs/label/choose');
			choose.selected = true;
			addSelect.appendChild( choose );

			free.forEach( function( route ) {
				const option = dc.createElement('option');
				option.value = route.httpUri;
				option.textContent = route.label+ ' ('+ route.httpUri+ ')'+ ( route.named === false ? ' '+ Nino.content.getText('/_admin/navs/label/unnamed-short') : '' );
				addSelect.appendChild( option );
			} );
			addSelect.addEventListener( 'change', function() { addBtn.disabled = addSelect.value === '' } );
			addLabel.appendChild( addSelect );
			fieldset.appendChild( addLabel );

			addBtn.addEventListener( 'click', function() { Nino.admin.navs._add( addSelect.value ) } );
			fieldset.appendChild( addBtn );

			host.appendChild( fieldset );
		},

		/**
		 *	A change to the working copy: remember that it differs from what is
		 *	saved, say so in the status line, and draw the entries again - only
		 *	them, so what was typed into the id field above survives. Under the
		 *	shell the action bar says "unsaved" itself, so the panel's own line
		 *	is for the shell-less case only
		 *
		 *	@return		void
		 */
		_changed : function() {

			Nino.admin.navs._dirty = true;
			Nino.admin.navs._renderEntries();

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
			else {

				const msg = dc.getElementById('navs-form-msg');
				if( msg !== null )
					msg.textContent = Nino.content.getText('/_admin/navs/msg/unsaved');
			}
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

			Nino.admin.navs._changed();
		},

		/**
		 *	Take one entry out of the working copy - the route itself stays
		 *
		 *	@param		{number}	index
		 *
		 *	@return		void
		 */
		_remove : function( index ) {
			Nino.admin.navs._entries.splice( index, 1 );
			Nino.admin.navs._changed();
		},

		/**
		 *	Put a route at the end of the working copy
		 *
		 *	@param		{string}	httpUri				One of the routes the picker offered
		 *
		 *	@return		void
		 */
		_add : function( httpUri ) {

			const route = Nino.admin.navs._routes.find( function( candidate ) { return candidate.httpUri === httpUri } );

			if( route === undefined || Nino.admin.navs._entries.some( function( entry ) { return entry.httpUri === httpUri } ) === true )
				return;

			Nino.admin.navs._entries.push( Object.assign( {}, route ) );
			Nino.admin.navs._changed();
		},

		/**
		 *	Whether the open menu holds anything that is not saved: a changed
		 *	working copy, or an id that was typed - on screen only, a menu that
		 *	is drawn but not shown holds no input anybody is looking at
		 *
		 *	@return		{boolean}
		 */
		_isDirty : function() {

			if( Nino.admin.navs._currentKey === null && Nino.admin.navs._isNew === false )
				return false;

			const form = dc.getElementById('navs-form');
			if( form === null || form.classList.contains('admin-hidden') === true )
				return false;

			const key = dc.getElementById('navs-form-key');

			return Nino.admin.navs._dirty === true || ( key !== null && key.value !== Nino.admin.navs._savedKey );
		},

		/**
		 *	The shell's Discard: the menu is about to be left or drawn again, and
		 *	whatever it held is let go
		 *
		 *	@return		void
		 */
		_discard : function() {
			Nino.admin.navs._dirty 		= false;
			Nino.admin.navs._savedKey = ( dc.getElementById('navs-form-key') || { value : '' } ).value;
		},

		/**
		 *	The back link. A working copy is not saved anywhere and there is no
		 *	undo, so leaving it asks first. A shell that has the registry has
		 *	asked already, in its own dialog (Save, Discard or Cancel, see
		 *	Nino.admin.dirty), before this runs; one that has not is asked here
		 *
		 *	@return		void
		 */
		_back : function() {

			if( typeof Nino.admin.dirty !== 'object' && Nino.admin.navs._isDirty() === true && wn.confirm( Nino.content.getText('/_admin/navs/confirm/discard') ) === false )
				return;

			Nino.admin.navs._showList();
		},

		/**
		 *	Create the menu currently open, rename it, and/or save its running
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

			const msg = dc.getElementById('navs-form-msg');
			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			const key = dc.getElementById('navs-form-key').value;

			const payload = {
				originalKey : Nino.admin.navs._isNew ? '' : Nino.admin.navs._currentKey,
				key 				: key,
			};

			// A new menu has no running order yet to post
			if( Nino.admin.navs._isNew === false )
				payload.entries = Nino.admin.navs._entries.map( function( entry ) { return entry.httpUri } );

			Nino.admin.navs._apiCall( 'save', payload, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				// Stay on the menu that was just created/renamed, under
				// whichever key it now has
				Nino.admin.navs._isNew 			= false;
				Nino.admin.navs._currentKey = key;
				Nino.admin.navs._apply( response );

				const saved = dc.getElementById('navs-form-msg');
				if( saved !== null )
					saved.textContent = Nino.content.getText('/_admin/common/msg/saved');

				report( true );
			} );
		},

		/**
		 *	Confirm, then delete the menu currently open. It goes out of the
		 *	registry and off every route in it; the routes themselves stay
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( wn.confirm( Nino.content.getText('/_admin/navs/confirm/delete').replace( '%s', Nino.admin.navs._currentKey ) ) === false )
				return;

			const msg = dc.getElementById('navs-form-msg');
			msg.textContent = Nino.content.getText('/_admin/common/msg/deleting');

			Nino.admin.navs._apiCall( 'delete', { key : Nino.admin.navs._currentKey }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/delete' );
					return;
				}
				Nino.admin.navs._showList();
				Nino.admin.navs._apply( response );
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.navs.init );

	// The shell asks before anything throws the open menu's input away (see
	// Nino.admin.dirty): the working copy of its entries and a typed id are not
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
