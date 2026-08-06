

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 3: the project's actual pages - a free-form, ordered
 *													list of { uri, httpUri, template, nav, text } entries
 *													built here, rather than a fixed checkbox per
 *													_install/library/pages/&lt;key&gt; unit (that picker is
 *													Setup's - see setup.js). "uri" (Element-URI) is a stable
 *													identifier, "httpUri" is the real browser path - see
 *													Install.php's Webpages::_routeKeys() docblock for why
 *													both exist. List + drill-down-form shape, same as _admin's
 *													Pages module (see pages.js) - the two are meant to feel
 *													like the same tool, since they do the same job. "New
 *													Webpage" opens a blank-looking entry (its name/title/
 *													description fields carry no value, just a generic
 *													placeholder - what actually lands on "Next" if left
 *													untouched, see Install.php's Webpages class); the list's
 *													own ↑/↓ buttons reorder in place. Everything here is
 *													purely client-side - unlike _admin's Pages module, nothing
 *													persists until "Next" posts the whole list at once (see
 *													apply()), so Save/Delete only ever edit _entries in
 *													memory. Driven by the shared Back/Next bar (script.js)
 *													rather than its own save button - apply() is exposed for
 *													Next to call, not wired to a button here.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.webpages = {

		_ready 					: false,
		_templates 			: {},
		_locales 				: [],
		_navModule 			: false,
		_entries 				: [],
		_currentIndex 	: null,
		_current 			: null,
		_isNew 					: false,

		/**
		 *	Load the available templates and the current, persisted list
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('webpages-list');
			if( wrap === null )
				return;

			Nino.install.apiCall( 'webpages/list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );

				Nino.install.webpages._templates 	= response.templates;
				Nino.install.webpages._locales 		= response.locales;
				Nino.install.webpages._navModule 	= response.navModule;
				Nino.install.webpages._entries 		= response.webpages;
				Nino.install.webpages._renderList();
				Nino.install.webpages._showList();
				Nino.install.webpages._ready = true;
			} );
		},

		showCurrent : function() {

			if( Nino.install.webpages._ready === false )
				return Nino.install.webpages.init();

			// Always resume on the list - nothing needs re-fetching (this
			// step never leaves the browser until apply()), and re-showing
			// a stale open form after Back/Next would risk editing an
			// entry that isn't at _currentIndex anymore
			Nino.install.webpages._showList();
		},

		_showList : function() {
			dc.getElementById('webpages-list').classList.remove('admin-hidden');
			dc.getElementById('webpages-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('webpages-list').classList.add('admin-hidden');
			dc.getElementById('webpages-form').classList.remove('admin-hidden');
		},

		/**
		 *	Render the page list, plus a "New Webpage" action below it. Each
		 *	row's ↑/↓ buttons reorder _entries in place
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('webpages-list');
			wrap.innerHTML = '';

			if( Nino.install.webpages._entries.length === 0 ) {
				const p = dc.createElement('p');
				p.className = 'admin-hint';
				p.textContent = 'No pages yet - add one below.';
				wrap.appendChild( p );
			}

			const ul = dc.createElement('ul');
			Nino.install.webpages._entries.forEach( function( entry, index ) {

				const li = dc.createElement('li');
				li.className = 'install-webpage-row';

				const label = Nino.install.webpages._label( entry );

				const link = dc.createElement('a');
				link.href = '#';
				link.textContent = entry.httpUri+ ' → '+ label+
					( entry.uri !== entry.httpUri ? '  ('+ entry.uri+ ')' : '' );
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.install.webpages._openForm( index ) } );
				li.appendChild( link );

				const move = dc.createElement('span');
				move.className = 'install-webpage-move';

				const up = dc.createElement('button');
				up.type = 'button';
				up.title = 'Move up';
				up.textContent = '↑';
				up.disabled = index === 0;
				up.addEventListener( 'click', function() { Nino.install.webpages._move( index, 'up' ) } );
				move.appendChild( up );

				const down = dc.createElement('button');
				down.type = 'button';
				down.title = 'Move down';
				down.textContent = '↓';
				down.disabled = index === Nino.install.webpages._entries.length - 1;
				down.addEventListener( 'click', function() { Nino.install.webpages._move( index, 'down' ) } );
				move.appendChild( down );

				li.appendChild( move );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'editor-list-action';
			addBtn.textContent = 'New Webpage';
			addBtn.addEventListener( 'click', function() { Nino.install.webpages._openForm( null ) } );
			wrap.appendChild( addBtn );
		},

		/**
		 *	How one entry is named in the list and the template select. An
		 *	entry /_admin's Pages module created carries no library unit at
		 *	all, just the template it already sits on - reported as-is
		 *	rather than blank
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{string}
		 */
		_label : function( entry ) {

			if( entry.libraryKey && Nino.install.webpages._templates[entry.libraryKey] )
				return Nino.install.webpages._templates[entry.libraryKey].label || entry.libraryKey;

			return entry.template || entry.libraryKey || '—';
		},

		/**
		 *	Swap an entry with its neighbor and re-render - purely
		 *	client-side, same as every other edit here, since the whole
		 *	list only actually persists on apply()
		 *
		 *	@param		{number}	index
		 *	@param		{string}	direction		'up' | 'down'
		 *
		 *	@return		void
		 */
		_move : function( index, direction ) {

			const entries 	= Nino.install.webpages._entries;
			const swapWith = direction === 'up' ? index - 1 : index + 1;

			if( swapWith < 0 || swapWith >= entries.length )
				return;

			[ entries[index], entries[swapWith] ] = [ entries[swapWith], entries[index] ];

			Nino.install.webpages._renderList();
		},

		/**
		 *	Open the editor for an existing entry, or a blank (uri-suggested)
		 *	one for a new page - mirrors _admin's Pages module (see pages.js's
		 *	_openForm()), except nothing here is pushed into _entries until
		 *	_save() actually runs, so "Back to list" on an unsaved new entry
		 *	simply discards it
		 *
		 *	@param		{number|null}	index		Index into _entries, or null to create new
		 *
		 *	@return		void
		 */
		_openForm : function( index ) {

			Nino.install.webpages._isNew 			= index === null;
			Nino.install.webpages._currentIndex = index;

			let entry = index !== null ? Nino.install.webpages._entries[index] : null;
			if( entry === null ) {

				const text = {};
				Nino.install.webpages._locales.forEach( function( locale ) { text[locale] = {} } );

				const freeUri = Nino.install.webpages._freeUri( function( e ) { return e.uri } );

				entry = {
					uri 				: freeUri,
					httpUri 		: Nino.install.webpages._freeUri( function( e ) { return e.httpUri }, freeUri ),
					libraryKey 	: Object.keys( Nino.install.webpages._templates )[0] || '',
					nav 				: false,
					text 				: text,
				};
			}

			Nino.install.webpages._current = entry;
			Nino.install.webpages._renderForm( entry );
			Nino.install.webpages._showForm();
		},

		/**
		 *	@param		{Function}	pick				Reads the field to check for a collision off one entry
		 *	@param		{string}		[preferred]	Tried first, as-is, before falling back to '/new-page[-N]'
		 *
		 *	@return		{string}		$preferred (if free), else '/new-page', '/new-page-2', ... -
		 *												whichever isn't taken yet
		 */
		_freeUri : function( pick, preferred ) {

			const taken = Nino.install.webpages._entries.map( pick );

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
		 *	Render the page editor: back-link, Element/Http URI, template
		 *	(plus a live "requires modules" hint), nav (only while the
		 *	Navigation module is active), one name/title/description row per
		 *	active locale, save/delete - same fieldset shape as _admin's Pages
		 *	module (see pages.js's _renderForm())
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		void
		 */
		_renderForm : function( entry ) {

			const wrap = dc.getElementById('webpages-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.install.webpages._showList() } );
			wrap.appendChild( backLink );

			const form = dc.createElement('form');

			// Both fieldsets get their card/white-background look for free
			// from _editor/assets/style.css's generic `fieldset { ... }` rule
			const pageFieldset = dc.createElement('fieldset');
			const pageLegend = dc.createElement('legend');
			pageLegend.textContent = 'Page';
			pageFieldset.appendChild( pageLegend );

			const uriLabel = dc.createElement('label');
			uriLabel.className = 'editor-field';
			const uriSpan = dc.createElement('span');
			uriSpan.textContent = 'Element URI - a stable identifier, not necessarily the real path (see Http URI)';
			uriLabel.appendChild( uriSpan );
			const uriInput = dc.createElement('input');
			uriInput.type = 'text';
			uriInput.id = 'webpages-form-uri';
			uriInput.placeholder = 'Element URI e.g. "/home"';
			uriInput.value = entry.uri || '';
			uriInput.required = true;
			uriLabel.appendChild( uriInput );
			pageFieldset.appendChild( uriLabel );

			const httpUriLabel = dc.createElement('label');
			httpUriLabel.className = 'editor-field';
			const httpUriSpan = dc.createElement('span');
			httpUriSpan.textContent = 'Http URI - the real, reachable browser path';
			httpUriLabel.appendChild( httpUriSpan );
			const httpUriInput = dc.createElement('input');
			httpUriInput.type = 'text';
			httpUriInput.id = 'webpages-form-http-uri';
			httpUriInput.placeholder = 'Http URI e.g. "/"';
			httpUriInput.value = entry.httpUri || '';
			httpUriInput.required = true;
			httpUriLabel.appendChild( httpUriInput );
			pageFieldset.appendChild( httpUriLabel );

			const templateLabel = dc.createElement('label');
			templateLabel.className = 'editor-field';
			const templateSpan = dc.createElement('span');
			templateSpan.textContent = 'Template';
			templateLabel.appendChild( templateSpan );
			const templateSelect = dc.createElement('select');
			templateSelect.id = 'webpages-form-template';

			// An entry /_admin's Pages module created has no library unit to
			// select - it gets its own, non-library option so the select can
			// still represent it, and re-applying leaves it on whatever
			// template it already sits on
			if( !entry.libraryKey ) {
				const own = dc.createElement('option');
				own.value = '';
				own.textContent = Nino.install.webpages._label( entry )+ ' (already set up)';
				own.selected = true;
				templateSelect.appendChild( own );
			}

			Object.keys( Nino.install.webpages._templates ).forEach( function( key ) {
				const option = dc.createElement('option');
				option.value = key;
				option.textContent = Nino.install.webpages._templates[key].label || key;
				option.selected = key === entry.libraryKey;
				templateSelect.appendChild( option );
			} );
			templateLabel.appendChild( templateSelect );
			pageFieldset.appendChild( templateLabel );

			const requiresHint = dc.createElement('p');
			requiresHint.className = 'admin-hint install-webpage-requires';
			const updateRequiresHint = function() {
				const requires = Nino.install.webpages._templates[templateSelect.value]?.requiresModules || [];
				requiresHint.textContent = requires.length > 0
					? 'requires modules: '+ requires.join( ', ' )+ ' - picked automatically on "Next" if not already active'
					: '';
				requiresHint.classList.toggle( 'admin-hidden', requires.length === 0 );
			};
			templateSelect.addEventListener( 'change', updateRequiresHint );
			updateRequiresHint();
			pageFieldset.appendChild( requiresHint );

			if( Nino.install.webpages._navModule === true ) {
				const navLabel = dc.createElement('label');
				navLabel.className = 'editor-field';
				const navInput = dc.createElement('input');
				navInput.type = 'checkbox';
				navInput.id = 'webpages-form-nav';
				navInput.checked = entry.nav === true;
				navLabel.appendChild( navInput );
				navLabel.appendChild( dc.createTextNode(' Show in main navigation') );
				pageFieldset.appendChild( navLabel );
			}

			form.appendChild( pageFieldset );

			const contentFieldset = dc.createElement('fieldset');
			const contentLegend = dc.createElement('legend');
			contentLegend.textContent = 'Content';
			contentFieldset.appendChild( contentLegend );

			const localesWrap = dc.createElement('div');
			localesWrap.id = 'webpages-form-locales';
			Nino.install.webpages._locales.forEach( function( locale ) {
				localesWrap.appendChild( Nino.install.webpages._localeRow( locale, entry.text?.[locale] || {} ) );
			} );
			contentFieldset.appendChild( localesWrap );

			form.appendChild( contentFieldset );

			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save';
			actions.appendChild( saveBtn );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.install.webpages._save() } );

			wrap.appendChild( form );

			if( Nino.install.webpages._isNew === false ) {
				const deleteBtn = dc.createElement('button');
				deleteBtn.type = 'button';
				deleteBtn.className = 'admin-danger-btn';
				deleteBtn.textContent = 'Delete page';
				deleteBtn.addEventListener( 'click', function() { Nino.install.webpages._delete() } );
				wrap.appendChild( deleteBtn );
			}
		},

		/**
		 *	@param		{string}	locale
		 *	@param		{Object}	values		{ name, title, description }
		 *
		 *	@return		{Element}
		 */
		_localeRow : function( locale, values ) {

			const row = dc.createElement('div');
			row.className = 'install-webpage-locale-row';
			row.dataset.locale = locale;

			const label = dc.createElement('span');
			label.className = 'install-webpage-locale-label';
			label.textContent = locale;
			row.appendChild( label );

			const name = dc.createElement('input');
			name.type = 'text';
			name.placeholder = 'Name e.g. "Home"';
			name.dataset.field = 'name';
			name.value = values.name || '';
			row.appendChild( name );

			const title = dc.createElement('input');
			title.type = 'text';
			title.placeholder = 'HTML Title e.g. "Welcome"';
			title.dataset.field = 'title';
			title.value = values.title || '';
			row.appendChild( title );

			const description = dc.createElement('textarea');
			description.rows = 2;
			description.placeholder = 'HTML Description, etc.';
			description.dataset.field = 'description';
			description.value = values.description || '';
			row.appendChild( description );

			return row;
		},

		/**
		 *	Create or update the entry currently open, purely in _entries -
		 *	nothing reaches the server until apply()
		 *
		 *	@return		void
		 */
		_save : function() {

			const text = {};
			dc.querySelectorAll('#webpages-form-locales [data-locale]').forEach( function( row ) {
				text[row.dataset.locale] = {
					name 				: row.querySelector('[data-field="name"]').value,
					title 			: row.querySelector('[data-field="title"]').value,
					description : row.querySelector('[data-field="description"]').value,
				};
			} );

			const navInput = dc.getElementById('webpages-form-nav');

			// Built on top of the entry as it stands rather than from the
			// form's fields alone: 'body'/'statusCode'/'template' are
			// resolved server-side (see Install.php's Webpages::apiApply())
			// and have no field here, so rebuilding from scratch would post
			// them away - which is exactly how an entry /_admin created, or a
			// 404 page's status code, would get lost
			const updated = Object.assign( {}, Nino.install.webpages._current, {
				uri 				: dc.getElementById('webpages-form-uri').value,
				httpUri 		: dc.getElementById('webpages-form-http-uri').value,
				libraryKey 	: dc.getElementById('webpages-form-template').value,
				nav 				: navInput !== null && navInput.checked,
				text 				: text,
			} );

			if( Nino.install.webpages._isNew === true )
				Nino.install.webpages._entries.push( updated );
			else
				Nino.install.webpages._entries[Nino.install.webpages._currentIndex] = updated;

			Nino.install.webpages._renderList();
			Nino.install.webpages._showList();
		},

		/**
		 *	Confirm, then remove the entry currently open from _entries
		 *
		 *	@return		void
		 */
		_delete : function() {

			const entry = Nino.install.webpages._entries[Nino.install.webpages._currentIndex];
			if( wn.confirm( 'Really delete the page at "'+ entry.httpUri+ '"?' ) === false )
				return;

			Nino.install.webpages._entries.splice( Nino.install.webpages._currentIndex, 1 );
			Nino.install.webpages._renderList();
			Nino.install.webpages._showList();
		},

		/**
		 *	Send the current (complete, replacing) list to webpages/apply -
		 *	called by the shared Next button, not by a button of its own
		 *
		 *	@param		{Function}	callback			Called with ( success )
		 *
		 *	@return		void
		 */
		apply : function( callback ) {

			const msg = dc.getElementById('webpages-msg');
			msg.textContent = 'Applying …';

			Nino.install.apiCall( 'webpages/apply', { webpages : Nino.install.webpages._entries }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to apply.' );
					callback( false );
					return;
				}

				msg.textContent = 'Applied '+ response.webpages.length+ ' page(s).';

				// Setup may since have added/removed a module the
				// requiresModules pull-in also touches - reload on next visit
				Nino.install.webpages._ready = false;

				callback( true );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
