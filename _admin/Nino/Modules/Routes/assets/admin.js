

/**
 *	Nino										A compact filesystembased php framework
 *	admin.js								"Routes" panel: create/edit/delete the site's actual page
 *													routes without hand-editing /nino/http/routes as raw json
 *													(the Config panel no longer edits that key). The
 *													template select only ever offers a templates/page-*.tpl
 *													file that already exists on disk, and a new route starts
 *													with every field empty - see the panel's own
 *													Admin/Admin.php class docblock for the Element URI/Http
 *													URI split every entry carries. List + drill-down-form
 *													shape follows slots.js closely; the list's own ↑/↓ buttons
 *													reorder the routes with it, which is what orders every
 *													menu those pages appear in (see \Nino\Modules\Navigation).
 *													A page has one Http URI for every language or one for each: a
 *													switch says which, and the form draws the one field or a field
 *													per language, each named by its language - and languages
 *													whose fields hold one address share it (see _addressMap()).
 *													The pages a feature or a module routes at runtime - which no
 *													config.php has - are listed below them, one row per
 *													Element-URI, without ↑/↓ and without delete: their form
 *													is the paths, read-only, and the name, title and
 *													description per language, saved as routes/savetexts.
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
		_currentUri 		: null,
		_currentRuntime : null,
		_isNew 					: false,
		_ready 					: false,
		// The row or the button that opened the form that is open: Back returns
		// the focus to it (see Nino.adminUi.backTo())
		_opener 				: null,
		// The status line of the form that is open (see Nino.adminUi.status())
		_line 					: null,
		// What the address fields of the form that is open start from, and what
		// they are given back from when the switch is turned: { one, shared,
		// bound } (see _addressesOf()). Between two turns the fields on screen
		// are the truth
		_addresses 			: null,
		// The stamp of the templates (see Nino.admin.touch()) the list was asked
		// for: the form offers the templates the answer held, and one written
		// since is not among them
		_stamp 					: 0,

		/**
		 *	Load the persisted page list and render it
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('routes-list') === null )
				return;

			// A template written while the answer is on its way is not in it: the
			// stamp is the one from before the request went out
			const stamp = Nino.admin.stamp( 'templates' );

			Nino.admin.routes._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.routes._showError( dc.getElementById('routes-list'), status, response );

				Nino.admin.routes._pages 						= response.pages;
				Nino.admin.routes._runtime 					= response.runtime || [];
				Nino.admin.routes._templates 				= response.templates;
				Nino.admin.routes._defaultTemplate 	= response.defaultTemplate || '';
				Nino.admin.routes._locales 					= response.locales;
				Nino.admin.routes._selectedLocale 	= response.selectedLocale || '';
				Nino.admin.routes._localeNames 			= response.localeNames || {};
				Nino.admin.routes._navs 						= response.navs;
				Nino.admin.routes._renderList();
				Nino.admin.routes._showList();
				Nino.admin.routes._ready = true;
				Nino.admin.routes._stamp = stamp;
			} );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on - and read
		 *	the list again first where a page template was written since it was
		 *	read (the Builder creates them), so that the form's select offers it.
		 *	Not while a form is open: the list would be drawn over it, and the
		 *	showing that finds none open reads it
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.routes._ready === false )
				return Nino.admin.routes.init();

			if( dc.getElementById('routes-form').classList.contains('admin-hidden') === false )
				return Nino.admin.routes._showForm();

			if( Nino.admin.stamp( 'templates' ) !== Nino.admin.routes._stamp )
				return Nino.admin.routes.init();

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
		 *	Show the list or the form again as they stand: the focus stays where
		 *	it is, unless it stood in the level that is hidden now (see
		 *	Nino.adminUi.showLevel()). What a person opens goes through
		 *	_openForm(), and Back through the back link
		 *
		 *	@return		void
		 */
		_showList : function() {
			Nino.adminUi.showLevel( 'routes-list', { focus : false } );
		},

		_showForm : function() {
			Nino.adminUi.showLevel( 'routes-form', { focus : false } );
		},

		/**
		 *	Render the page list, plus a "New page" action below it. A row is
		 *	the page's name over its addresses, a button that opens its form and
		 *	a link that opens the page itself; its ↑/↓ buttons reorder the
		 *	persisted list - a page goes with all of its routes - and equal
		 *	menu priorities follow route order, so this is what orders the
		 *	menus (see Admin.php's apiMove())
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('routes-list');
			wrap.innerHTML = '';

			if( Nino.admin.routes._pages.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/routes/empty') ) );

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			Nino.admin.routes._pages.forEach( function( entry, index ) {

				const li = dc.createElement('li');
				li.className = 'admin-page-row';

				// Names are editor content: textContent, never markup. The template
				// and the Element URI are in the form, not on the row. It opens a
				// form and no address is written: a button, not a link to nowhere
				const edit = dc.createElement('button');
				edit.type = 'button';
				edit.className = 'nino-admin-list-link';
				const copy = dc.createElement('span');
				copy.className = 'nino-admin-list-copy';
				const name = dc.createElement('strong');
				name.textContent = Nino.admin.routes._pageName( entry );
				copy.appendChild( name );
				const path = dc.createElement('small');
				path.textContent = Nino.admin.routes._paths( entry );
				copy.appendChild( path );
				edit.appendChild( copy );
				edit.addEventListener( 'click', function() { Nino.admin.routes._openForm( entry, edit ) } );
				li.appendChild( edit );

				// The page itself, in a tab of its own. Only for a path that is a
				// path below this site: a '//host' would be read as another origin
				if( entry.httpUri.charAt(0) === '/' && entry.httpUri.charAt(1) !== '/' ) {
					const open = dc.createElement('a');
					open.className = 'admin-page-open';
					open.href = Nino.admin.assetUrl( entry.httpUri );
					open.target = '_blank';
					open.rel = 'noopener';
					open.textContent = '↗';
					open.title = Nino.content.getText('/_admin/routes/label/open');
					open.setAttribute( 'aria-label', Nino.content.getText('/_admin/routes/label/open') );
					li.appendChild( open );
				}

				const moveWrap = dc.createElement('span');
				moveWrap.className = 'nino-admin-move';

				const upBtn = dc.createElement('button');
				upBtn.type = 'button';
				upBtn.textContent = '↑';
				upBtn.title = Nino.content.getText('/_admin/routes/label/moveup');
				// title is a hover hint, not a name - see Nino.adminUi.elementList()
				upBtn.setAttribute( 'aria-label', Nino.content.getText('/_admin/routes/label/moveup') );
				upBtn.disabled = index === 0;
				upBtn.addEventListener( 'click', function() { Nino.admin.routes._move( entry.uri, 'up' ) } );
				moveWrap.appendChild( upBtn );

				const downBtn = dc.createElement('button');
				downBtn.type = 'button';
				downBtn.textContent = '↓';
				downBtn.title = Nino.content.getText('/_admin/routes/label/movedown');
				downBtn.setAttribute( 'aria-label', Nino.content.getText('/_admin/routes/label/movedown') );
				downBtn.disabled = index === Nino.admin.routes._pages.length - 1;
				downBtn.addEventListener( 'click', function() { Nino.admin.routes._move( entry.uri, 'down' ) } );
				moveWrap.appendChild( downBtn );

				li.appendChild( moveWrap );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			Nino.admin.routes._renderRuntimeList( wrap );

			// The line a refused move reports into. Beside the list rather than
			// in place of it: the rows are still every route there is, and they
			// carry the arrows the next attempt is made with
			const msg = dc.createElement('p');
			msg.id = 'routes-list-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( msg );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'nino-admin-btn-primary';
			addBtn.textContent = Nino.content.getText('/_admin/routes/label/new');
			addBtn.addEventListener( 'click', function() { Nino.admin.routes._openForm( null, addBtn ) } );
			wrap.appendChild( Nino.adminUi.listActions( [ addBtn ] ) );
		},

		/**
		 *	The pages features route at runtime, under the persisted ones: a
		 *	row is the page's name over its path and opens the form of its
		 *	texts, nothing more - no menu order, no delete, because there is no
		 *	route of the project's to move or remove. Nothing is drawn when no
		 *	feature routes a page
		 *
		 *	@param		{Element}		wrap					The list's container
		 *
		 *	@return		void
		 */
		_renderRuntimeList : function( wrap ) {

			if( Nino.admin.routes._runtime.length === 0 )
				return;

			const heading = dc.createElement('h3');
			heading.className = 'admin-routes-runtime-title';
			heading.textContent = Nino.content.getText('/_admin/routes/runtime/title');
			wrap.appendChild( heading );

			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.content.getText('/_admin/routes/runtime/hint');
			wrap.appendChild( hint );

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			Nino.admin.routes._runtime.forEach( function( entry ) {

				const li = dc.createElement('li');
				li.className = 'admin-page-row';

				const edit = dc.createElement('button');
				edit.type = 'button';
				edit.className = 'nino-admin-list-link';
				const copy = dc.createElement('span');
				copy.className = 'nino-admin-list-copy';
				const name = dc.createElement('strong');
				name.textContent = Nino.admin.routes._pageName( entry );
				copy.appendChild( name );
				const path = dc.createElement('small');
				path.textContent = Nino.admin.routes._paths( entry );
				copy.appendChild( path );
				edit.appendChild( copy );
				edit.addEventListener( 'click', function() { Nino.admin.routes._openRuntimeForm( entry, edit ) } );
				li.appendChild( edit );

				ul.appendChild( li );
			} );
			wrap.appendChild( ul );
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

			const wanted = [ Nino.admin.routes._selectedLocale, '' ].concat( Object.keys( httpUris ) );
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

			const locales = [ Nino.admin.routes._selectedLocale ].concat( Nino.admin.routes._locales );

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
		 *	apiMove()
		 *
		 *	@param		{string}	uri						The Element-URI of the page
		 *	@param		{string}	direction			'up' | 'down'
		 *
		 *	@return		void
		 */
		_move : function( uri, direction ) {
			Nino.admin.routes._apiCall( 'move', { uri : uri, direction : direction }, function( status, response ) {

				/*	A move the server refused used to end right here, without a
					word: the row stayed where it was, nothing said why, and the
					arrow read as a button that does nothing. Whatever the reason -
					a route somebody else has deleted since this list was drawn, a
					config.php that could not be written - the order on screen is a
					guess from that moment on, so it is read back from the server
					and the reason is put under the list.	*/
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
				Nino.admin.routes._renderList();
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

			const msg = dc.getElementById('routes-list-msg');

			// No list rendered means no line under it, and then the error has to
			// go somewhere rather than nowhere
			if( msg === null )
				return Nino.admin.routes._showError( dc.getElementById('routes-list'), status, response );

			msg.className = 'nino-admin-error';
			msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
		},

		/**
		 *	Open the editor for an existing page, or an empty one for a new
		 *	page - nothing is suggested but the template, and that only when
		 *	the project has a blank page (see Admin.php's apiList())
		 *
		 *	@param		{Object|null}	entry			One entry from _pages, or null to create new
		 *	@param		{Element}			[opener]	The row or the button that was pressed: Back returns the focus to it
		 *
		 *	@return		void
		 */
		_openForm : function( entry, opener ) {

			Nino.admin.routes._isNew 					= entry === null;
			Nino.admin.routes._currentUri 		= entry ? entry.uri : null;
			Nino.admin.routes._currentRuntime = null;
			Nino.admin.routes._opener 				= opener ?? null;

			Nino.admin.routes._renderForm( entry || { uri : '', httpUris : {}, template : Nino.admin.routes._defaultTemplate } );
			Nino.adminUi.showLevel( 'routes-form' );
		},

		/**
		 *	Open the form of a page a feature routes: its path, which the
		 *	feature decides and this panel only shows, and its name, title and
		 *	description in every language
		 *
		 *	@param		{Object}		entry			One entry from _runtime
		 *	@param		{Element}		[opener]	The row that was pressed: Back returns the focus to it
		 *
		 *	@return		void
		 */
		_openRuntimeForm : function( entry, opener ) {

			Nino.admin.routes._isNew 					= false;
			Nino.admin.routes._currentUri 		= null;
			Nino.admin.routes._currentRuntime = entry;
			Nino.admin.routes._opener 				= opener ?? null;

			Nino.admin.routes._renderRuntimeForm( entry );
			Nino.adminUi.showLevel( 'routes-form' );
		},

		/**
		 *	Render the form of a feature's page: back-link, the path read-only,
		 *	one name/title/description row per active locale, save. No
		 *	delete, no menu fields, no template - none of them is the
		 *	project's to decide for a page it did not route
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		void
		 */
		_renderRuntimeForm : function( entry ) {

			const wrap = dc.getElementById('routes-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.adminUi.backTo( 'routes-list', Nino.admin.routes._opener ) } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');
			form.noValidate = true;

			const pageFieldset = dc.createElement('fieldset');
			const pageLegend = dc.createElement('legend');
			pageLegend.textContent = Nino.content.getText('/_admin/routes/label/route');
			pageFieldset.appendChild( pageLegend );

			const pathHint = dc.createElement('p');
			pathHint.className = 'nino-admin-hint';
			pathHint.textContent = Nino.content.getText('/_admin/routes/runtime/path');
			pageFieldset.appendChild( pathHint );

			const pathLabel = dc.createElement('label');
			pathLabel.className = 'nino-admin-field';
			const pathSpan = dc.createElement('span');
			pathSpan.textContent = Nino.content.getText('/_admin/routes/label/httpuri');
			pathLabel.appendChild( pathSpan );
			const pathInput = dc.createElement('input');
			pathInput.type = 'text';
			pathInput.id = 'routes-form-runtime-path';
			pathInput.readOnly = true;
			pathInput.value = Nino.admin.routes._paths( entry );
			pathLabel.appendChild( pathInput );
			pageFieldset.appendChild( pathLabel );

			form.appendChild( pageFieldset );

			const contentFieldset = dc.createElement('fieldset');
			const contentLegend = dc.createElement('legend');
			contentLegend.textContent = Nino.content.getText('/_admin/routes/label/content');
			contentFieldset.appendChild( contentLegend );

			const contentHint = dc.createElement('p');
			contentHint.className = 'nino-admin-hint';
			contentHint.textContent = Nino.content.getText('/_admin/routes/hint/required');
			contentFieldset.appendChild( contentHint );

			const localesWrap = dc.createElement('div');
			localesWrap.id = 'routes-form-locales';
			Nino.admin.routes._locales.forEach( function( locale ) {
				localesWrap.appendChild( Nino.admin.routes._localeRow( locale, ( entry.text && entry.text[locale] ) || {} ) );
			} );
			contentFieldset.appendChild( localesWrap );

			form.appendChild( contentFieldset );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'routes-form-msg';
			actions.appendChild( msg );
			Nino.admin.routes._line = Nino.adminUi.status( msg );
			// A bar with a status line shows no other mark of unsaved input: the line follows the form
			Nino.admin.routes._line.bind( form );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.routes._save() } );

			wrap.appendChild( form );

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'routes' );
		},

		/**
		 *	Render the page editor: back-link, Element URI, the switch for one
		 *	Http URI for every language and the field - or the field per
		 *	language - it draws, template, status code, nav (only while the
		 *	Navigation module is active), one name/title/description row per
		 *	active locale, save/delete.
		 *	The form checks its required fields itself (see _save()), so the
		 *	browser's own bubble - in the browser's language, not the
		 *	workbench's - never gets to speak
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		void
		 */
		_renderForm : function( entry ) {

			const wrap = dc.getElementById('routes-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.adminUi.backTo( 'routes-list', Nino.admin.routes._opener ) } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');
			form.noValidate = true;

			// Both fieldsets get their card/white-background look for free
			// from the design system's generic `fieldset { ... }`
			// rule - a bare .nino-admin-field label sitting directly in <form>
			// (no fieldset) doesn't, which is why every field used to sit
			// straight on the page's own background
			const pageFieldset = dc.createElement('fieldset');
			const pageLegend = dc.createElement('legend');
			pageLegend.textContent = Nino.content.getText('/_admin/routes/label/route');
			pageFieldset.appendChild( pageLegend );

			const uriLabel = dc.createElement('label');
			uriLabel.className = 'nino-admin-field';
			const uriSpan = dc.createElement('span');
			uriSpan.textContent = Nino.content.getText('/_admin/routes/label/uri');
			uriSpan.appendChild( Nino.admin.routes._star() );
			uriLabel.appendChild( uriSpan );
			const uriInput = dc.createElement('input');
			uriInput.type = 'text';
			uriInput.id = 'routes-form-uri';
			uriInput.placeholder = Nino.content.getText('/_admin/routes/placeholder/uri');
			uriInput.value = entry.uri || '';
			uriInput.required = true;
			uriInput.setAttribute( 'aria-required', 'true' );
			uriLabel.appendChild( uriInput );
			pageFieldset.appendChild( uriLabel );

			Nino.admin.routes._addresses = Nino.admin.routes._addressesOf( entry );

			const oneSwitch = Nino.adminUi.switchField( {
				key 		: 'one-address',
				checked : Nino.admin.routes._addresses.one,
				label 	: Nino.content.getText('/_admin/routes/label/one-address'),
				hint 		: Nino.content.getText('/_admin/routes/hint/one-address'),
			} );
			oneSwitch.querySelector('input').addEventListener( 'change', function( ev ) { Nino.admin.routes._switchAddresses( ev.target.checked ) } );
			pageFieldset.appendChild( oneSwitch );

			const addressWrap = dc.createElement('div');
			addressWrap.id = 'routes-form-addresses';
			addressWrap.className = 'nino-admin-fieldgrid';
			Nino.admin.routes._renderAddresses( addressWrap );
			pageFieldset.appendChild( addressWrap );

			const templateLabel = dc.createElement('label');
			templateLabel.className = 'nino-admin-field';
			const templateSpan = dc.createElement('span');
			templateSpan.textContent = Nino.content.getText('/_admin/routes/label/template');
			templateSpan.appendChild( Nino.admin.routes._star() );
			templateLabel.appendChild( templateSpan );
			const templateSelect = dc.createElement('select');
			templateSelect.id = 'routes-form-template';
			templateSelect.required = true;

			// A route with no template of its own to start on - a new one in a
			// project without a blank page, or one whose file is gone - makes
			// the person choose: the first file in the list is not a proposal,
			// it is the one that happens to sort first. A body that names its
			// template at runtime has its own option below
			const fixedBody = entry.body !== undefined && entry.body !== '' && Nino.admin.routes._templateFromBody( entry.body ) === null;

			if( fixedBody === false && Nino.admin.routes._templates.indexOf( entry.template ) === -1 ) {
				const choose = dc.createElement('option');
				choose.value = '';
				choose.textContent = Nino.content.getText('/_admin/routes/label/choose');
				choose.disabled = true;
				choose.selected = true;
				templateSelect.appendChild( choose );
			}
			Nino.admin.routes._templates.forEach( function( name ) {
				const option = dc.createElement('option');
				option.value = name;
				option.textContent = name;
				option.selected = name === entry.template;
				templateSelect.appendChild( option );
			} );
			templateLabel.appendChild( templateSelect );
			pageFieldset.appendChild( templateLabel );

			// A route body that isn't a plain template reference can't be
			// spelled by this select - a hand-written one may resolve its
			// file per locale via [[/nino/http/response/locale]]. Saving
			// keeps that body either way (see Admin.php's apiSave()),
			// so the select is disabled rather than left looking as if it
			// still decided anything
			if( fixedBody === true ) {

				// Its own, selected option - without one the browser falls back
				// to the first real template in the list, leaving the (disabled)
				// select naming a file this page never uses
				const runtimeOption = dc.createElement('option');
				runtimeOption.value = '';
				runtimeOption.textContent = Nino.content.getText('/_admin/routes/label/runtime');
				runtimeOption.selected = true;
				templateSelect.insertBefore( runtimeOption, templateSelect.firstChild );

				templateSelect.disabled = true;

				const fixedHint = dc.createElement('p');
				fixedHint.className = 'nino-admin-hint';
				fixedHint.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/routes/hint/runtime'), entry.body );
				pageFieldset.appendChild( fixedHint );
			}

			const statusLabel = dc.createElement('label');
			statusLabel.className = 'nino-admin-field';
			const statusSpan = dc.createElement('span');
			statusSpan.textContent = Nino.content.getText('/_admin/routes/label/status');
			statusLabel.appendChild( statusSpan );
			const statusInput = dc.createElement('input');
			statusInput.type = 'number';
			statusInput.id = 'routes-form-status';
			statusInput.min = '100';
			statusInput.max = '599';
			statusInput.value = entry.statusCode || 200;
			statusLabel.appendChild( statusInput );
			pageFieldset.appendChild( statusLabel );

			// One checkbox per navigation the project registered
			// (/nino/html/navs) - an empty list means the Navigation module is
			// inactive and no menu field is offered at all. What a page is a
			// member of lives on its route, which is what actually renders it
			// (see \Nino\Modules\Navigation::routeLines())
			Nino.admin.routes._navs.forEach( function( navKey ) {
				const navLabel = dc.createElement('label');
				navLabel.className = 'nino-admin-checkbox-field';
				const navInput = dc.createElement('input');
				navInput.type = 'checkbox';
				navInput.dataset.nav = navKey;
				navInput.checked = ( entry.navs || [] ).indexOf( navKey ) !== -1;
				navLabel.appendChild( navInput );
				navLabel.appendChild( dc.createTextNode( ' '+ Nino.adminUi.format( Nino.content.getText('/_admin/routes/label/nav'), navKey ) ) );
				pageFieldset.appendChild( navLabel );
			} );

			form.appendChild( pageFieldset );

			const contentFieldset = dc.createElement('fieldset');
			const contentLegend = dc.createElement('legend');
			contentLegend.textContent = Nino.content.getText('/_admin/routes/label/content');
			contentFieldset.appendChild( contentLegend );

			const contentHint = dc.createElement('p');
			contentHint.className = 'nino-admin-hint';
			contentHint.textContent = Nino.content.getText('/_admin/routes/hint/required');
			contentFieldset.appendChild( contentHint );

			const localesWrap = dc.createElement('div');
			localesWrap.id = 'routes-form-locales';
			Nino.admin.routes._locales.forEach( function( locale ) {
				localesWrap.appendChild( Nino.admin.routes._localeRow( locale, ( entry.text && entry.text[locale] ) || {} ) );
			} );
			contentFieldset.appendChild( localesWrap );

			form.appendChild( contentFieldset );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			// In the actions row, not loose below the form: that row is pinned
			// to the bottom of the viewport now (see assets/style.css), and a
			// button left outside it would be the one control that scrolls away
			if( Nino.admin.routes._isNew === false ) {
				const deleteBtn = dc.createElement('button');
				deleteBtn.type = 'button';
				deleteBtn.className = 'nino-admin-btn-danger';
				deleteBtn.textContent = Nino.content.getText('/_admin/routes/label/delete');
				deleteBtn.addEventListener( 'click', function() { Nino.admin.routes._delete() } );
				actions.appendChild( deleteBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'routes-form-msg';
			actions.appendChild( msg );
			Nino.admin.routes._line = Nino.adminUi.status( msg );
			// A bar with a status line shows no other mark of unsaved input: the line follows the form
			Nino.admin.routes._line.bind( form );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.routes._save() } );

			wrap.appendChild( form );

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'routes' );
		},

		/**
		 *	What the address fields of a page start from: the one field for
		 *	every language where the page has one address for them all - a new
		 *	page has none yet - and a field per language where it has one for
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
		 *	field per language - one that has no address of its own yet starts
		 *	from the address of every language, which is what it is reached
		 *	at until it is given one
		 *
		 *	@param		{Element}		wrap					The container of the fields
		 *
		 *	@return		void
		 */
		_renderAddresses : function( wrap ) {

			const addresses = Nino.admin.routes._addresses;

			wrap.innerHTML = '';

			if( addresses.one === true ) {
				wrap.removeAttribute('role');
				wrap.removeAttribute('aria-label');
				wrap.appendChild( Nino.admin.routes._addressField( '', Nino.content.getText('/_admin/routes/label/httpuri'), addresses.shared ) );
				return;
			}

			// The fields are named by their language: what they are the address of is said by the group
			wrap.setAttribute( 'role', 'group' );
			wrap.setAttribute( 'aria-label', Nino.content.getText('/_admin/routes/label/httpuri') );

			Nino.admin.routes._locales.forEach( function( locale ) {
				wrap.appendChild( Nino.admin.routes._addressField( locale, Nino.admin.routes._localeNames[locale] || locale, addresses.bound[locale] || addresses.shared ) );
			} );
		},

		/**
		 *	One address field. data-address says whose it is - a language, or ''
		 *	for the one that serves every language - and data-field is the name
		 *	the server marks it by where it refuses it (see Admin.php's
		 *	_postedAddresses())
		 *
		 *	@param		{string}		locale				The language the address is for, '' for every language
		 *	@param		{string}		name					What the field is called
		 *	@param		{string}		value
		 *
		 *	@return		{Element}									The label
		 */
		_addressField : function( locale, name, value ) {

			const label = dc.createElement('label');
			label.className = 'nino-admin-field';
			const span = dc.createElement('span');
			span.textContent = name;
			span.appendChild( Nino.admin.routes._star() );
			label.appendChild( span );
			const input = dc.createElement('input');
			input.type = 'text';
			input.id = locale === '' ? 'routes-form-http-uri' : 'routes-form-http-uri-'+ locale;
			input.dataset.address = locale;
			input.dataset.field = locale === '' ? 'httpUri' : 'httpUri-'+ locale;
			input.placeholder = Nino.content.getText('/_admin/routes/placeholder/httpuri');
			input.value = value;
			input.required = true;
			input.setAttribute( 'aria-required', 'true' );
			label.appendChild( input );

			return label;
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

			const addresses = Nino.admin.routes._addresses;

			dc.querySelectorAll('#routes-form-addresses [data-address]').forEach( function( input ) {
				if( input.dataset.address === '' )
					addresses.shared = input.value;
				else
					addresses.bound[input.dataset.address] = input.value;
			} );

			if( one === true && addresses.shared === '' )
				addresses.shared = Nino.admin.routes._locales.map( function( locale ) { return addresses.bound[locale] } ).find( function( value ) { return value !== '' } ) ?? '';

			addresses.one = one;
			Nino.admin.routes._renderAddresses( dc.getElementById('routes-form-addresses') );
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
		 *	The mark after the name of a field that cannot be left empty - drawn
		 *	aria-hidden, the control says it with aria-required
		 *
		 *	@return		{Element}
		 */
		_star : function() {
			const star = dc.createElement('span');
			star.className = 'nino-admin-required';
			star.setAttribute( 'aria-hidden', 'true' );
			star.textContent = ' *';
			return star;
		},

		/**
		 *	@param		{string}	locale
		 *	@param		{Object}	values		{ name, title, description }
		 *
		 *	@return		{Element}
		 */
		_localeRow : function( locale, values ) {

			const row = dc.createElement('div');
			row.className = 'admin-page-locale-row';
			row.dataset.locale = locale;

			const label = dc.createElement('span');
			label.className = 'admin-page-locale-label';
			label.textContent = locale;
			row.appendChild( label );

			const name = dc.createElement('input');
			name.type = 'text';
			name.placeholder = Nino.content.getText('/_admin/routes/placeholder/name');
			name.setAttribute( 'aria-label', Nino.adminUi.format( Nino.content.getText('/_admin/routes/aria/name'), locale ) );
			name.setAttribute( 'aria-required', 'true' );
			name.dataset.field = 'name';
			name.value = values.name || '';
			row.appendChild( name );

			const title = dc.createElement('input');
			title.type = 'text';
			title.placeholder = Nino.content.getText('/_admin/routes/placeholder/title');
			title.setAttribute( 'aria-label', Nino.adminUi.format( Nino.content.getText('/_admin/routes/aria/title'), locale ) );
			title.setAttribute( 'aria-required', 'true' );
			title.dataset.field = 'title';
			title.value = values.title || '';
			row.appendChild( title );

			const description = dc.createElement('textarea');
			description.rows = 2;
			description.placeholder = Nino.content.getText('/_admin/routes/placeholder/description');
			description.setAttribute( 'aria-label', Nino.adminUi.format( Nino.content.getText('/_admin/routes/aria/description'), locale ) );
			description.dataset.field = 'description';
			description.value = values.description || '';
			row.appendChild( description );

			return row;
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

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = Nino.admin.routes._line;

			const text = {};
			dc.querySelectorAll('#routes-form-locales [data-locale]').forEach( function( row ) {
				text[row.dataset.locale] = {
					name 				: row.querySelector('[data-field="name"]').value,
					title 			: row.querySelector('[data-field="title"]').value,
					description : row.querySelector('[data-field="description"]').value,
				};
			} );

			/*	The required fields are checked here, before a request: the
				form is not validated by the browser (see _renderForm()), and
				a name or a title nobody wrote would render as the raw
				[[/_nino/webpage.../name]] on the page and in the menu. Every
				attempt starts without the marks of the one before; a field
				that fails is marked and says why where it stands, the first
				one takes the focus, and the one summary is the status line's,
				in the workbench's own words	*/
			Nino.adminUi.clearFieldErrors( dc.getElementById('routes-form') );

			const missing = [];
			const need = function( field ) {
				if( field !== null && field.disabled !== true && String( field.value ).trim() === '' )
					missing.push( field );
			};

			// A page a feature routes has its texts and nothing else to fill in
			if( Nino.admin.routes._currentRuntime === null ) {
				need( dc.getElementById('routes-form-uri') );
				dc.querySelectorAll('#routes-form-addresses [data-address]').forEach( need );
				need( dc.getElementById('routes-form-template') );
			}
			dc.querySelectorAll('#routes-form-locales [data-locale]').forEach( function( row ) {
				need( row.querySelector('[data-field="name"]') );
				need( row.querySelector('[data-field="title"]') );
			} );

			if( missing.length > 0 ) {
				missing.forEach( Nino.admin.routes._markMissing );
				missing[0].focus();
				line.fail( Nino.content.getText('/_admin/routes/error/required') );
				report( false );
				return;
			}

			line.saving();

			if( Nino.admin.routes._currentRuntime !== null )
				return Nino.admin.routes._saveRuntime( text, report );

			const navs = [];
			dc.querySelectorAll('#routes-form [data-nav]').forEach( function( input ) {
				if( input.checked === true )
					navs.push( input.dataset.nav );
			} );

			// What is on screen: language => address, or '' for the one address of
			// every language - which is also the address of the languages whose
			// fields hold the same one
			const addressed = Nino.admin.routes._addressMap( Array.from( dc.querySelectorAll('#routes-form-addresses [data-address]') ).map( function( input ) {
				return { locale : input.dataset.address, value : input.value };
			} ) );

			Nino.admin.routes._apiCall( 'save', {
				originalUri 		: Nino.admin.routes._isNew ? '' : Nino.admin.routes._currentUri,
				uri 						: dc.getElementById('routes-form-uri').value,
				httpUris 				: addressed.httpUris,
				template 				: dc.getElementById('routes-form-template').value,
				statusCode 			: dc.getElementById('routes-form-status').value,
				navs						: navs,
				text 						: text,
			}, function( status, response ) {
				if( status !== 200 || response === null ) {
					line.error( status, Nino.admin.routes._refusedField( response, addressed.folded ), '/_admin/common/error/save' );
					report( false );
					return;
				}
				// The page that was saved is the one a window beside this one goes to
				Nino.admin.touch( 'routes', Nino.admin.routes._publicPath( addressed.httpUris ) );
				line.saved();
				if( typeof Nino.admin.dirty === 'object' )
					Nino.admin.dirty.snapshot( 'routes' );
				Nino.admin.routes.init();
				report( true );
			} );
		},

		/**
		 *	Say at a field that it is empty and must not be: aria-invalid, and a
		 *	sentence under it that the field points at. The name and the title
		 *	of a language stand side by side in one row and are told with one
		 *	sentence, the row's, which both point at; the other fields have
		 *	their label to follow
		 *
		 *	@param		{Element}		field					The input or the select that is missing
		 *
		 *	@return		void
		 */
		_markMissing : function( field ) {

			const row = field.closest('.admin-page-locale-row');
			const id = 'routes-error-'+ ( row === null ? field.id.replace( /^routes-form-/, '' ) : row.dataset.locale );
			const sentence = dc.getElementById( id );

			if( sentence === null )
				Nino.adminUi.fieldError( row ?? field.closest('label'), field, id, Nino.content.getText('/_admin/common/error/required') );
			else {
				field.setAttribute( 'aria-invalid', 'true' );
				field.setAttribute( 'aria-describedby', id );
			}
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

			const line = Nino.admin.routes._line;
			const page = Nino.admin.routes._currentRuntime;

			Nino.admin.routes._apiCall( 'savetexts', { uri : page.uri, text : text }, function( status, response ) {
				if( status !== 200 || response === null ) {
					line.error( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}
				Nino.admin.touch( 'routes', Nino.admin.routes._publicPath( { '' : page.httpUri } ) );
				line.saved();
				if( typeof Nino.admin.dirty === 'object' )
					Nino.admin.dirty.snapshot( 'routes' );
				Nino.admin.routes.init();
				report( true );
			} );
		},

		/**
		 *	The entry of the page that is open, from the list the panel holds
		 *
		 *	@return		{Object|undefined}
		 */
		_currentEntry : function() {
			return Nino.admin.routes._pages.find( function( page ) { return page.uri === Nino.admin.routes._currentUri } );
		},

		/**
		 *	What a delete leaves behind, in words, built from what the list
		 *	already holds: the page's texts (the keys of the languages that hold
		 *	a value, and the path fill) and the template file - shared with how
		 *	many other routes, or with none. A route that resolves its template
		 *	at runtime names its body instead, there is no single file to name
		 *
		 *	@return		{string}
		 */
		_staysLine : function() {

			const entry = Nino.admin.routes._currentEntry();

			if( entry === undefined )
				return '';

			const fields = [];
			const locales = [];

			Nino.admin.routes._locales.forEach( function( locale ) {
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
					locales.push( locale );
			} );

			const base = '/_nino/webpage'+ entry.uri;
			const keys = [];
			if( fields.length > 0 )
				keys.push( base+ '/'+ fields.join('|')+ ' ('+ locales.join(', ')+ ')' );
			keys.push( base+ '/uri' );

			const stays = [ Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-texts'), keys.join(', ') ) ];

			if( entry.template === '' ) {
				stays.push( Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-body'), entry.body ) );
				return stays.join('; ');
			}

			const others = Nino.admin.routes._pages.filter( function( page ) { return page.template === entry.template && page.uri !== entry.uri } ).length;

			if( others === 0 )
				stays.push( Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-template'), entry.template ) );
			else if( others === 1 )
				stays.push( Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-template-one'), entry.template ) );
			else
				stays.push( Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/stays-template-shared'), entry.template, others ) );

			return stays.join('; ');
		},

		/**
		 *	Confirm, then delete the page currently open. Its routes are
		 *	removed - every address it has, which the question names; any
		 *	/_nino/webpage.../name,title,description,uri text meta is left in
		 *	place, same additive-only rule the rest of Nino follows - and so
		 *	is its template file, which is why the question says what stays
		 *	(see _staysLine())
		 *
		 *	@return		void
		 */
		_delete : function() {

			const entry = Nino.admin.routes._currentEntry();

			Nino.adminUi.confirm( 'destructive', {
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/delete-left'), entry === undefined ? Nino.admin.routes._currentUri : Nino.admin.routes._paths( entry ), Nino.admin.routes._staysLine() ),
				yes				: Nino.content.getText('/_admin/common/label/delete'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					const line = Nino.admin.routes._line;
					line.idle( Nino.content.getText('/_admin/common/msg/deleting') );

					Nino.admin.routes._apiCall( 'delete', { uri : Nino.admin.routes._currentUri }, function( status, response ) {
						if( status !== 200 || response === null ) {
							line.error( status, response, '/_admin/common/error/delete' );
							return;
						}
						Nino.admin.touch( 'routes' );
						Nino.admin.routes.init();
					} );
				},
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.routes.init );

	// The shell asks before anything throws the open page's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'routes', function() { return dc.getElementById('routes-form') }, function( done ) { Nino.admin.routes._save( done ) } );

})(window, document, document.documentElement, document.body);
