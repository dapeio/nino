

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	text.js									Admin "Text" panel: browse text-key categories (grouped by
 *													the key's first path segment), then edit every key of a
 *													category at once - a locale switch at the top plus every
 *													global/locale field below, same shape as the Elements form.
 *													The set of keys is developer-owned (see /text/blacklist.php) -
 *													this only ever edits existing key values, no create/delete.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.text = {

		_locales				: [],
		_groups					: {},
		_currentGroup		: null,
		_selectedLocale	: null,
		_localeValues		: {},
		_htmlEditors		: {},
		_fieldEls				: {},
		_ready					: false,

		/**
		 *	Load every editable key, group them and render the category list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('text-list') === null )
				return;

			Nino.admin.text._apiCall( 'keys', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.text._showError( dc.getElementById('text-list'), status, response );

				// Capture the hash before any _show*() call below can overwrite it -
				// _showList() would otherwise wipe the deep-link part it's trying to restore
				const hash = Nino.admin.router.current();

				Nino.admin.text._locales = response.locales;
				Nino.admin.sessionLocale.init( response.selectedLocale );
				Nino.admin.text._groups = Nino.admin.text._groupEntries( response.keys );
				Nino.admin.text._renderCategoryList();
				Nino.admin.text._ready 	= true;

				if( hash.panel === 'text' && hash.parts.length > 0 && Nino.admin.text._groups[hash.parts[0]] !== undefined )
					Nino.admin.text._openGroup( hash.parts[0] );
				else
					Nino.admin.text._showList();
			} );
		},

		/**
		 *	Re-apply whatever drill-down level this panel is currently on -
		 *	called when the user switches TO this tab, so the hash (only ever
		 *	written by router.set() while this panel is the visible one) gets
		 *	synced to reality instead of staying stale from before the switch
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.text._ready === false )
				return;

			if( dc.getElementById('text-form').classList.contains('admin-hidden') === false )
				return Nino.admin.text._showForm();

			Nino.admin.text._showList();
		},

		/**
		 *	Call a text/* admin action - see elements.js for why /_admin/ (trailing slash)
		 *
		 *	@param		{string}		endpoint			Action name (eg. "savebatch", becomes "text/savebatch")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'text/'+ endpoint, data : JSON.stringify( payload ) } );
		},

		/**
		 *	Show a failed request's status/error in a container
		 *
		 *	@param		{Element}		container			Element to render the error into
		 *	@param		{number}		status				Xhr status code
		 *	@param		{*}					response			Parsed response body, if any
		 *
		 *	@return		void
		 */
		_showError : function( container, status, response ) {
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/text/error/load') );
			container.appendChild( p );
		},

		/**
		 *	Drill-down navigation: category list -> category form. The main
		 *	System/Texte/Elemente bar stays visible throughout.
		 *
		 *	@return		void
		 */
		_showList : function() {
			dc.getElementById('text-list').classList.remove('admin-hidden');
			dc.getElementById('text-form').classList.add('admin-hidden');
			Nino.admin.router.set( 'text', [] );
		},

		_showForm : function() {
			dc.getElementById('text-list').classList.add('admin-hidden');
			dc.getElementById('text-form').classList.remove('admin-hidden');
			Nino.admin.router.set( 'text', [ Nino.admin.text._currentGroup ] );
		},

		/**
		 *	Group key entries by the first path segment (eg. "/home/welcome/h2" -> "home")
		 *
		 *	@param		{Array}		entries				List of key entries (see Text::apiKeys())
		 *
		 *	@return		{Object}									group name -> entries[]
		 */
		_groupEntries : function( entries ) {
			const groups = {};
			entries.forEach( function( entry ) {
				const group = entry.key.split('/').filter( Boolean )[0] || '-';
				groups[group] = groups[group] || [];
				groups[group].push( entry );
			} );
			return groups;
		},

		/**
		 *	A short, tag-stripped preview of a key's current value
		 *
		 *	@param		{Object}	entry					Key entry
		 *
		 *	@return		{string}
		 */
		_preview : function( entry ) {
			const value = entry.global ? ( entry.values['*'] ?? '' ) : ( entry.values[Nino.admin.text._locales[0]] ?? '' );
			return String( value ?? '' ).replace(/<[^>]+>/g, '').trim();
		},

		/**
		 *	Build a group's "(N) preview, preview, .." description, matching
		 *	the Elements type list's description style
		 *
		 *	@param		{Array}		entries
		 *
		 *	@return		{string}
		 */
		_groupDescr : function( entries ) {
			const parts 	= entries.map( function( e ) { return Nino.admin.text._preview( e ) } ).filter( function( s ) { return s !== '' } );
			const joined 	= parts.join(', ');
			return '('+ entries.length+ ') '+ ( joined.length > 150 ? joined.slice( 0, 150 )+ ' ..' : joined );
		},

		/**
		 *	Build the "Übersetzung" toolbar: export a locale's raw file
		 *	content as JSON (round-trip it through an external LLM by hand),
		 *	then paste the translated JSON back in for a target locale. See
		 *	Admin\Text::apiExport()/apiImport() for the actual read/write -
		 *	this only builds the request and downloads/shows the result
		 *
		 *	@return		{Element}
		 */
		_renderTranslateTools : function() {

			const wrap = dc.createElement('div');
			wrap.id = 'text-translate-tools';

			const buildLocaleSelect = function() {
				const select = dc.createElement('select');
				Nino.admin.text._locales.forEach( function( locale ) {
					const opt = dc.createElement('option');
					opt.value = locale;
					opt.textContent = locale;
					select.appendChild( opt );
				} );
				select.value = Nino.admin.sessionLocale.current ?? Nino.admin.text._locales[0] ?? '';
				return select;
			};

			// Export
			const exportRow = dc.createElement('div');
			exportRow.className = 'text-translate-row';

			const exportSelect = buildLocaleSelect();
			exportRow.appendChild( exportSelect );

			const exportBtn = dc.createElement('button');
			exportBtn.type = 'button';
			exportBtn.textContent = 'Als JSON exportieren';
			exportBtn.addEventListener( 'click', function() {
				Nino.admin.text._apiCall( 'export', { locale : exportSelect.value }, function( status, response ) {
					if( status !== 200 || response === null )
						return;
					const json = JSON.stringify( response.content, null, 2 );
					const blob = new Blob( [ json ], { type : 'application/json;charset=utf-8;' } );
					const url = URL.createObjectURL( blob );
					const a = dc.createElement('a');
					a.href = url;
					a.download = 'text-'+ exportSelect.value+ '.json';
					dc.body.appendChild( a );
					a.click();
					a.remove();
					URL.revokeObjectURL( url );
				} );
			} );
			exportRow.appendChild( exportBtn );

			wrap.appendChild( exportRow );

			// Import
			const importRow = dc.createElement('div');
			importRow.className = 'text-translate-row';

			const importSelect = buildLocaleSelect();
			importRow.appendChild( importSelect );

			const importArea = dc.createElement('textarea');
			importArea.id = 'text-import-area';
			importArea.placeholder = 'Übersetztes JSON hier einfügen ...';
			importRow.appendChild( importArea );

			const importResult = dc.createElement('span');
			importResult.id = 'text-import-result';

			const importBtn = dc.createElement('button');
			importBtn.type = 'button';
			importBtn.textContent = 'Importieren';
			importBtn.addEventListener( 'click', function() {

				let content;
				try {
					content = JSON.parse( importArea.value );
				} catch(e) {
					importResult.textContent = 'Ungültiges JSON.';
					importResult.className = 'text-import-error';
					return;
				}

				Nino.admin.text._apiCall( 'import', { locale : importSelect.value, content : content }, function( status, response ) {
					if( status !== 200 || response === null ) {
						importResult.textContent = ( response && response.error ) ? response.error : 'Fehler beim Import.';
						importResult.className = 'text-import-error';
						return;
					}
					importResult.textContent = response.imported+ ' importiert, '+ response.skipped+ ' übersprungen.';
					importResult.className = 'text-import-success';
					importArea.value = '';

					// Re-fetch so the editor (previews, per-key values) reflects what
					// was just imported - delayed so _renderCategoryList()'s rebuild
					// (which replaces this whole toolbar, importResult included)
					// doesn't wipe the success message before it's even been read
					setTimeout( function() {
						Nino.admin.text._apiCall( 'keys', {}, function( status, response ) {
							if( status !== 200 || response === null )
								return;
							Nino.admin.text._locales = response.locales;
							Nino.admin.text._groups 	= Nino.admin.text._groupEntries( response.keys );
							Nino.admin.text._renderCategoryList();
						} );
					}, 2000 );
				} );
			} );
			importRow.appendChild( importBtn );
			importRow.appendChild( importResult );

			wrap.appendChild( importRow );

			return wrap;
		},

		/**
		 *	Render the category list, styled the same as the Elements type list
		 *
		 *	@return		void
		 */
		_renderCategoryList : function() {

			const wrap = dc.getElementById('text-list');
			wrap.innerHTML = '';

			wrap.appendChild( Nino.admin.text._renderTranslateTools() );

			Object.keys( Nino.admin.text._groups ).sort().forEach( function( group ) {

				const entries = Nino.admin.text._groups[group];

				const btn = dc.createElement('div');
				btn.className = 'admin-type-btn';
				btn.dataset.group = group;

				const titleWrap = dc.createElement('div');
				titleWrap.textContent = group;

				const descr = dc.createElement('div');
				descr.className = 'admin-type-btn-descr';
				descr.textContent = Nino.admin.text._groupDescr( entries );
				titleWrap.appendChild( descr );

				const chev = dc.createElement('span');
				chev.className = 'admin-view-button-chev';
				chev.setAttribute( 'aria-hidden', 'true' );
				chev.textContent = '›';

				btn.appendChild( titleWrap );
				btn.appendChild( chev );
				btn.addEventListener( 'click', function() { Nino.admin.text._openGroup( group ) } );

				wrap.appendChild( btn );
			} );
		},

		/**
		 *	Open a category's bulk-edit form
		 *
		 *	@param		{string}	group
		 *
		 *	@return		void
		 */
		_openGroup : function( group ) {

			Nino.admin.text._destroyHtmlEditors();

			Nino.admin.text._currentGroup 	= group;
			Nino.admin.text._selectedLocale = Nino.admin.sessionLocale.current ?? Nino.admin.text._locales[0] ?? '';
			Nino.admin.text._localeValues 	= {};
			Nino.admin.text._fieldEls 			= {};

			Nino.admin.text._renderGroupForm();
			Nino.admin.text._showForm();
		},

		/**
		 *	Render one key as a labeled field, html-editor or textarea+counter
		 *	depending on the entry, matching elements.js's field styling
		 *
		 *	@param		{Object}	entry					Key entry
		 *	@param		{*}				value					Current value
		 *
		 *	@return		{Element}								<label> wrapping the field
		 */
		_renderKeyField : function( entry, value ) {

			const label = dc.createElement('label');
			label.className = 'admin-field';

			if( entry.html === true ) {
				const span = dc.createElement('span');
				span.textContent = '[[' + entry.key + ']]';
				label.appendChild( span );
				const mount = dc.createElement('div');
				label.appendChild( mount );
				Nino.admin.text._htmlEditors[entry.key] = Nino.admin.htmlEditor.create( mount, value ?? '', entry.maxlength );
				return label;
			}

			const textarea = dc.createElement('textarea');
			textarea.maxLength = entry.maxlength;
			textarea.value = value ?? '';
			Nino.admin.text._fieldEls[entry.key] = textarea;

			const header = dc.createElement('div');
			header.className = 'admin-field-header';

			const nameSpan = dc.createElement('span');
			nameSpan.className = 'admin-field-name';
			nameSpan.textContent = '[[' + entry.key + ']]';
			header.appendChild( nameSpan );

			const counter = dc.createElement('span');
			counter.className = 'char-counter';

			function updateCounter() {
				const len = textarea.value.length;
				counter.textContent = len + ' / ' + entry.maxlength;
				counter.classList.toggle( 'char-counter-limit', len >= entry.maxlength );
			}

			textarea.addEventListener( 'input', updateCounter );
			updateCounter();

			header.appendChild( counter );
			label.appendChild( header );
			label.appendChild( textarea );

			return label;
		},

		/**
		 *	Current value of one key's field, wherever it's currently mounted
		 *
		 *	@param		{Object}	entry					Key entry
		 *
		 *	@return		{string}
		 */
		_readKeyValue : function( entry ) {

			if( entry.html === true )
				return ( Nino.admin.text._htmlEditors[entry.key] !== undefined ) ? Nino.admin.text._htmlEditors[entry.key].getValue() : '';

			const el = Nino.admin.text._fieldEls[entry.key];
			return ( el !== undefined ) ? el.value : '';
		},

		/**
		 *	Destroy every currently mounted html-editor instance (both global
		 *	and locale-scoped) - call before tearing down the group form
		 *
		 *	@return		void
		 */
		_destroyHtmlEditors : function() {
			Object.keys( Nino.admin.text._htmlEditors ).forEach( function( key ) { Nino.admin.text._htmlEditors[key].destroy() } );
			Nino.admin.text._htmlEditors = {};
		},

		/**
		 *	Snapshot the currently visible locale-scoped fields into
		 *	_localeValues before switching locale (or before saving)
		 *
		 *	@return		void
		 */
		_storeVisibleLocaleFields : function() {

			const group 	= Nino.admin.text._currentGroup;
			const entries = ( Nino.admin.text._groups[group] ?? [] ).filter( function( e ) { return e.global === false } );

			if( entries.length === 0 || Nino.admin.text._selectedLocale === null )
				return;

			const values = {};
			entries.forEach( function( entry ) { values[entry.key] = Nino.admin.text._readKeyValue( entry ) } );

			Nino.admin.text._localeValues[Nino.admin.text._selectedLocale] = values;
		},

		/**
		 *	Re-render the locale-scoped fields for the currently selected locale
		 *
		 *	@return		void
		 */
		_renderLocaleFields : function() {

			const group 	= Nino.admin.text._currentGroup;
			const entries = ( Nino.admin.text._groups[group] ?? [] ).filter( function( e ) { return e.global === false } );

			entries.forEach( function( entry ) {
				if( Nino.admin.text._htmlEditors[entry.key] !== undefined ) {
					Nino.admin.text._htmlEditors[entry.key].destroy();
					delete Nino.admin.text._htmlEditors[entry.key];
				}
			} );

			const wrap = dc.getElementById('text-form-locale-fields');
			wrap.innerHTML = '';

			const stored = Nino.admin.text._localeValues[Nino.admin.text._selectedLocale] ?? {};

			entries.forEach( function( entry ) {
				const value = ( stored[entry.key] !== undefined ) ? stored[entry.key] : ( entry.values[Nino.admin.text._selectedLocale] ?? '' );
				wrap.appendChild( Nino.admin.text._renderKeyField( entry, value ) );
			} );
		},

		/**
		 *	Render the category's bulk-edit form: global fields, then a locale
		 *	select + locale-scoped fields, same shape as the Elements form
		 *
		 *	@return		void
		 */
		_renderGroupForm : function() {

			const group 	= Nino.admin.text._currentGroup;
			const entries = Nino.admin.text._groups[group] ?? [];

			const globalEntries = entries.filter( function( e ) { return e.global === true } );
			const localeEntries = entries.filter( function( e ) { return e.global === false } );

			const wrap = dc.getElementById('text-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = Nino.content.getText('/_admin/text/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.text._destroyHtmlEditors(); Nino.admin.text._showList() } );
			wrap.appendChild( backLink );

			const form = dc.createElement('form');
			form.id = 'text-edit-form';

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = group;
			wrap.appendChild( title );

			if( globalEntries.length > 0 ) {

				const globalWrap = dc.createElement('fieldset');
				globalWrap.id = 'text-form-global';
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_admin/elements/label/global');
				globalWrap.appendChild( legend );

				globalEntries.forEach( function( entry ) {
					globalWrap.appendChild( Nino.admin.text._renderKeyField( entry, entry.values['*'] ?? '' ) );
				} );

				form.appendChild( globalWrap );
			}

			if( localeEntries.length > 0 ) {

				const localeWrap = dc.createElement('fieldset');
				localeWrap.id = 'text-form-locale';
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_admin/elements/label/locale');
				localeWrap.appendChild( legend );

				const select = dc.createElement('select');
				select.id = 'text-form-locale-select';
				Nino.admin.text._locales.forEach( function( locale ) {
					const option = dc.createElement('option');
					option.value = locale;
					option.textContent = locale;
					option.selected = ( locale === Nino.admin.text._selectedLocale );
					select.appendChild( option );
				} );
				select.addEventListener( 'change', function() {
					Nino.admin.text._storeVisibleLocaleFields();
					Nino.admin.text._selectedLocale = select.value;
					Nino.admin.sessionLocale.set( select.value );
					Nino.admin.text._renderLocaleFields();
				} );
				localeWrap.appendChild( select );

				const fieldsWrap = dc.createElement('div');
				fieldsWrap.id = 'text-form-locale-fields';
				localeWrap.appendChild( fieldsWrap );

				form.appendChild( localeWrap );
			}

			const actions = dc.createElement('div');
			actions.className = 'admin-form-actions';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/text/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'text-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.text._save() } );

			wrap.appendChild( form );

			if( localeEntries.length > 0 )
				Nino.admin.text._renderLocaleFields();
		},

		/**
		 *	Save every key of the current category (global fields always, plus
		 *	the currently selected locale's fields) in one batched request.
		 *	Deliberately does not navigate back to the list afterwards - only
		 *	refreshes its preview.
		 *
		 *	@return		void
		 */
		_save : function() {

			Nino.admin.text._storeVisibleLocaleFields();

			const group 	= Nino.admin.text._currentGroup;
			const entries = Nino.admin.text._groups[group] ?? [];
			const msg 		= dc.getElementById('text-form-msg');

			const items = [];

			entries.filter( function( e ) { return e.global === true } ).forEach( function( entry ) {
				items.push( { key : entry.key, locale : '*', value : Nino.admin.text._readKeyValue( entry ) } );
			} );

			const localeValues = Nino.admin.text._localeValues[Nino.admin.text._selectedLocale] ?? {};
			entries.filter( function( e ) { return e.global === false } ).forEach( function( entry ) {
				items.push( { key : entry.key, locale : Nino.admin.text._selectedLocale, value : localeValues[entry.key] ?? '' } );
			} );

			msg.textContent = Nino.content.getText('/_admin/text/msg/pending');

			Nino.admin.text._apiCall( 'savebatch', { items : items }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/text/error/save') );
					return;
				}

				const results = response.results ?? {};
				const failed 	= [];

				items.forEach( function( item ) {
					const result = results[item.key];
					if( result === undefined )
						return;
					if( result.ok === true ) {
						const entry = entries.find( function( e ) { return e.key === item.key } );
						if( entry !== undefined )
							entry.values[item.locale] = result.value;
					} else {
						failed.push( item.key );
					}
				} );

				msg.textContent = ( failed.length === 0 )
					? Nino.content.getText('/_admin/text/msg/saved')
					: Nino.content.getText('/_admin/text/error/save')+ ' ('+ failed.join(', ')+ ')';

				Nino.admin.text._renderCategoryList();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.text.init );

})(window, document, document.documentElement, document.body);
