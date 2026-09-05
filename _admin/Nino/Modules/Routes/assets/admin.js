

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Pages" module: create/edit/delete the site's actual page
 *													routes without hand-editing /nino/http/routes as raw json
 *													(Config still covers everything this doesn't). The
 *													template select only ever offers a templates/page-*.tpl
 *													file that already exists on disk - see _admin/Admin.php's
 *													Routes class docblock for the Element URI/Http URI
 *													split every entry carries. List + drill-down-form shape
 *													follows images.js closely; the list's own ↑/↓ buttons
 *													reorder the routes with it, which is what orders every
 *													menu those pages appear in (see \Nino\Modules\Navigation).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.routes = {

		_pages 					: [],
		_templates 			: [],
		_locales 				: [],
		_navs 					: [],
		_currentHttpUri : null,
		_isNew 					: false,
		_ready 					: false,

		/**
		 *	Load the persisted page list and render it
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('routes-list') === null )
				return;

			Nino.admin.routes._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.routes._showError( dc.getElementById('routes-list'), status, response );

				Nino.admin.routes._pages 			= response.pages;
				Nino.admin.routes._templates 	= response.templates;
				Nino.admin.routes._locales 		= response.locales;
				Nino.admin.routes._navs 			= response.navs;
				Nino.admin.routes._renderList();
				Nino.admin.routes._showList();
				Nino.admin.routes._ready = true;
			} );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.routes._ready === false )
				return Nino.admin.routes.init();

			if( dc.getElementById('routes-form').classList.contains('admin-hidden') === false )
				return Nino.admin.routes._showForm();

			Nino.admin.routes._showList();
		},

		/**
		 *	Call a pages/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "pages/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'routes/'+ endpoint, data : JSON.stringify( payload ) } );
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
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/load') );
			container.appendChild( p );
		},

		_showList : function() {
			dc.getElementById('routes-list').classList.remove('admin-hidden');
			dc.getElementById('routes-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('routes-list').classList.add('admin-hidden');
			dc.getElementById('routes-form').classList.remove('admin-hidden');
		},

		/**
		 *	Render the page list, plus a "New page" action below it. Each
		 *	row's ↑/↓ buttons reorder the persisted list and the routes with
		 *	it - equal menu priorities follow route order, so this is what
		 *	orders the menus (see Routes.php's Routes::apiMove())
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

				// An entry whose body resolves its template at runtime has no
				// single template name to show (see _renderForm()) - report
				// the body it does have rather than an empty arrow
				const target = entry.template || entry.body || '?';

				const link = dc.createElement('a');
				link.href = '#';
				const label = dc.createElement('span');
				label.className = 'admin-page-label';
				label.textContent = entry.httpUri+ ' → '+ target+
					( entry.uri !== entry.httpUri ? '  ('+ entry.uri+ ')' : '' );
				link.appendChild( label );
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.routes._openForm( entry ) } );
				li.appendChild( link );

				const moveWrap = dc.createElement('span');
				moveWrap.className = 'admin-page-move';

				const upBtn = dc.createElement('button');
				upBtn.type = 'button';
				upBtn.textContent = '↑';
				upBtn.title = Nino.content.getText('/_admin/routes/label/moveup');
				upBtn.disabled = index === 0;
				upBtn.addEventListener( 'click', function() { Nino.admin.routes._move( entry.httpUri, 'up' ) } );
				moveWrap.appendChild( upBtn );

				const downBtn = dc.createElement('button');
				downBtn.type = 'button';
				downBtn.textContent = '↓';
				downBtn.title = Nino.content.getText('/_admin/routes/label/movedown');
				downBtn.disabled = index === Nino.admin.routes._pages.length - 1;
				downBtn.addEventListener( 'click', function() { Nino.admin.routes._move( entry.httpUri, 'down' ) } );
				moveWrap.appendChild( downBtn );

				li.appendChild( moveWrap );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'nino-admin-btn-primary';
			addBtn.textContent = Nino.content.getText('/_admin/routes/label/new');
			addBtn.addEventListener( 'click', function() { Nino.admin.routes._openForm( null ) } );
			wrap.appendChild( Nino.adminUi.listActions( [ addBtn ] ) );
		},

		/**
		 *	The on-disk template a route body names, when it names exactly
		 *	one - mirrors Routes.php's Routes::_templateFromBody(), which is
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
		 *	Routes::apiMove()
		 *
		 *	@param		{string}	httpUri
		 *	@param		{string}	direction			'up' | 'down'
		 *
		 *	@return		void
		 */
		_move : function( httpUri, direction ) {
			Nino.admin.routes._apiCall( 'move', { httpUri : httpUri, direction : direction }, function( status, response ) {
				if( status !== 200 || response === null )
					return;
				Nino.admin.routes._pages = response.pages;
				Nino.admin.routes._renderList();
			} );
		},

		/**
		 *	Open the editor for an existing page, or a blank (uri-suggested)
		 *	one for a new page
		 *
		 *	@param		{Object|null}	entry		One entry from _pages, or null to create new
		 *
		 *	@return		void
		 */
		_openForm : function( entry ) {

			Nino.admin.routes._isNew 					= entry === null;
			Nino.admin.routes._currentHttpUri = entry ? entry.httpUri : null;

			let initial = entry;
			if( entry === null ) {
				const freeUri = Nino.admin.routes._freeUri( function( e ) { return e.uri } );
				initial = {
					uri 			: freeUri,
					httpUri 	: Nino.admin.routes._freeUri( function( e ) { return e.httpUri }, freeUri ),
					template 	: Nino.admin.routes._templates[0] || '',
				};
			}

			Nino.admin.routes._renderForm( initial );
			Nino.admin.routes._showForm();
		},

		/**
		 *	@param		{Function}	pick				Reads the field to check for a collision off one entry
		 *	@param		{string}		[preferred]	Tried first, as-is, before falling back to '/new-page[-N]'
		 *
		 *	@return		{string}		$preferred (if free), else '/new-page', '/new-page-2', ... -
		 *												whichever isn't taken yet
		 */
		_freeUri : function( pick, preferred ) {

			const taken = Nino.admin.routes._pages.map( pick );

			if( preferred !== undefined && taken.indexOf( preferred ) === -1 )
				return preferred;

			let uri = '/new-page';
			let n 	= 2;

			while( taken.indexOf( uri ) !== -1 ) {
				uri = '/new-page-'+ n;
				n++;
			}

			return uri;
		},

		/**
		 *	Render the page editor: back-link, Element/Http URI, template,
		 *	status code, nav (only while the Navigation module is active),
		 *	one name/title/description row per active locale, save/delete
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
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.routes._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');

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
			uriLabel.appendChild( uriSpan );
			const uriInput = dc.createElement('input');
			uriInput.type = 'text';
			uriInput.id = 'routes-form-uri';
			uriInput.placeholder = Nino.content.getText('/_admin/routes/placeholder/uri');
			uriInput.value = entry.uri || '';
			uriInput.required = true;
			uriLabel.appendChild( uriInput );
			pageFieldset.appendChild( uriLabel );

			const httpUriLabel = dc.createElement('label');
			httpUriLabel.className = 'nino-admin-field';
			const httpUriSpan = dc.createElement('span');
			httpUriSpan.textContent = Nino.content.getText('/_admin/routes/label/httpuri');
			httpUriLabel.appendChild( httpUriSpan );
			const httpUriInput = dc.createElement('input');
			httpUriInput.type = 'text';
			httpUriInput.id = 'routes-form-http-uri';
			httpUriInput.placeholder = Nino.content.getText('/_admin/routes/placeholder/httpuri');
			httpUriInput.value = entry.httpUri || '';
			httpUriInput.required = true;
			httpUriLabel.appendChild( httpUriInput );
			pageFieldset.appendChild( httpUriLabel );

			const templateLabel = dc.createElement('label');
			templateLabel.className = 'nino-admin-field';
			const templateSpan = dc.createElement('span');
			templateSpan.textContent = Nino.content.getText('/_admin/routes/label/template');
			templateLabel.appendChild( templateSpan );
			const templateSelect = dc.createElement('select');
			templateSelect.id = 'routes-form-template';
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
			// spelled by this select - the setup wizard's "legal" unit resolves its
			// file per locale via [[/nino/http/response/locale]]. Saving
			// keeps that body either way (see Routes.php's Routes::apiSave),
			// so the select is disabled rather than left looking as if it
			// still decided anything
			if( entry.body !== undefined && entry.body !== '' && Nino.admin.routes._templateFromBody( entry.body ) === null ) {

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
				fixedHint.textContent = Nino.content.getText('/_admin/routes/hint/runtime').replace( '%s', entry.body );
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
				navLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/routes/label/nav').replace( '%s', navKey ) ) );
				pageFieldset.appendChild( navLabel );
			} );

			form.appendChild( pageFieldset );

			const contentFieldset = dc.createElement('fieldset');
			const contentLegend = dc.createElement('legend');
			contentLegend.textContent = Nino.content.getText('/_admin/routes/label/content');
			contentFieldset.appendChild( contentLegend );

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

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.routes._save() } );

			wrap.appendChild( form );
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
			name.dataset.field = 'name';
			name.value = values.name || '';
			row.appendChild( name );

			const title = dc.createElement('input');
			title.type = 'text';
			title.placeholder = Nino.content.getText('/_admin/routes/placeholder/title');
			title.dataset.field = 'title';
			title.value = values.title || '';
			row.appendChild( title );

			const description = dc.createElement('textarea');
			description.rows = 2;
			description.placeholder = Nino.content.getText('/_admin/routes/placeholder/description');
			description.dataset.field = 'description';
			description.value = values.description || '';
			row.appendChild( description );

			return row;
		},

		/**
		 *	Create or save the page currently open
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg = dc.getElementById('routes-form-msg');
			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			const text = {};
			dc.querySelectorAll('#routes-form-locales [data-locale]').forEach( function( row ) {
				text[row.dataset.locale] = {
					name 				: row.querySelector('[data-field="name"]').value,
					title 			: row.querySelector('[data-field="title"]').value,
					description : row.querySelector('[data-field="description"]').value,
				};
			} );

			const navs = [];
			dc.querySelectorAll('#routes-form [data-nav]').forEach( function( input ) {
				if( input.checked === true )
					navs.push( input.dataset.nav );
			} );

			Nino.admin.routes._apiCall( 'save', {
				originalHttpUri : Nino.admin.routes._isNew ? '' : Nino.admin.routes._currentHttpUri,
				uri 						: dc.getElementById('routes-form-uri').value,
				httpUri 				: dc.getElementById('routes-form-http-uri').value,
				template 				: dc.getElementById('routes-form-template').value,
				statusCode 			: dc.getElementById('routes-form-status').value,
				navs						: navs,
				text 						: text,
			}, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
					return;
				}
				msg.textContent = Nino.content.getText('/_admin/common/msg/saved');
				Nino.admin.routes.init();
			} );
		},

		/**
		 *	Confirm, then delete the page currently open. Its route is
		 *	removed; any /webpage.../name,title,description text meta is
		 *	left in place, same additive-only rule the rest of Nino follows
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( wn.confirm( Nino.content.getText('/_admin/routes/confirm/delete').replace( '%s', Nino.admin.routes._currentHttpUri ) ) === false )
				return;

			const msg = dc.getElementById('routes-form-msg');
			msg.textContent = Nino.content.getText('/_admin/common/msg/deleting');

			Nino.admin.routes._apiCall( 'delete', { httpUri : Nino.admin.routes._currentHttpUri }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/delete') );
					return;
				}
				Nino.admin.routes.init();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.routes.init );

})(window, document, document.documentElement, document.body);
