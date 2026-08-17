

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

	wn.Nino.editor = wn.Nino.editor || {};

	Nino.editor.text = {

		_locales				: [],
		_groups					: {},
		_currentGroup		: null,
		_selectedLocale	: null,
		_localeValues		: {},
		_dirtyLocales		: [],
		_htmlEditors		: {},
		_fieldEls				: {},
		_loading				: false,
		_saving					: false,
		_ready					: false,

		/**
		 *	Load every editable key, group them and render the category list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('text-list') === null || Nino.editor.text._loading === true || Nino.editor.text._ready === true )
				return;

			Nino.editor.text._loading = true;

			Nino.editor.text._apiCall( 'keys', {}, function( status, response ) {
				Nino.editor.text._loading = false;
				if( status !== 200 || response === null )
					return Nino.editor.text._showError( dc.getElementById('text-list'), status, response );

				// Capture the hash before any _show*() call below can overwrite it -
				// _showList() would otherwise wipe the deep-link part it's trying to restore
				const hash = Nino.editor.router.current();

				Nino.editor.text._locales = response.locales;
				Nino.editor.sessionLocale.init( response.selectedLocale );
				Nino.editor.text._groups = Nino.editor.text._groupEntries( response.keys );
				Nino.editor.text._renderCategoryList();
				Nino.editor.text._ready 	= true;

				if( hash.panel === 'text' && hash.parts.length > 0 && Nino.editor.text._groups[hash.parts[0]] !== undefined )
					Nino.editor.text._openGroup( hash.parts[0] );
				else
					Nino.editor.text._showList();
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

			if( Nino.editor.text._ready === false ) {
				Nino.editor.text.init();
				return;
			}

			if( dc.getElementById('text-form').classList.contains('editor-hidden') === false )
				return Nino.editor.text._showForm();

			Nino.editor.text._showList();
		},

		/**
		 *	Call a text/* admin action - see elements.js for why /_editor/ (trailing slash)
		 *
		 *	@param		{string}		endpoint			Action name (eg. "savebatch", becomes "text/savebatch")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_editor/', 'POST', function( xhr ) {
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
			p.className = 'editor-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/text/error/load') );
			container.appendChild( p );
		},

		/**
		 *	Drill-down navigation: category list -> category form. The main
		 *	System/Texte/Elemente bar stays visible throughout.
		 *
		 *	@return		void
		 */
		_showList : function() {
			dc.getElementById('text-list').classList.remove('editor-hidden');
			dc.getElementById('text-form').classList.add('editor-hidden');
			Nino.editor.router.set( 'text', [] );
		},

		_showForm : function() {
			dc.getElementById('text-list').classList.add('editor-hidden');
			dc.getElementById('text-form').classList.remove('editor-hidden');
			Nino.editor.router.set( 'text', [ Nino.editor.text._currentGroup ] );
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
			const value = entry.global ? ( entry.values['*'] ?? '' ) : ( entry.values[Nino.editor.text._locales[0]] ?? '' );
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
			const parts 	= entries.map( function( e ) { return Nino.editor.text._preview( e ) } ).filter( function( s ) { return s !== '' } );
			const joined 	= parts.join(', ');
			return '('+ entries.length+ ') '+ ( joined.length > 150 ? joined.slice( 0, 150 )+ ' ..' : joined );
		},

		/**
		 *	Render the category list, styled the same as the Elements type list
		 *
		 *	@return		void
		 */
		_renderCategoryList : function() {

			const wrap = dc.getElementById('text-list');
			wrap.innerHTML = '';
			wrap.classList.add( 'nino-admin-list', 'nino-admin-list-buttons' );

			Object.keys( Nino.editor.text._groups ).sort().forEach( function( group ) {

				const entries = Nino.editor.text._groups[group];

				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'editor-type-btn';
				btn.dataset.group = group;

				const titleWrap = dc.createElement('div');
				titleWrap.textContent = group;

				const descr = dc.createElement('div');
				descr.className = 'editor-type-btn-descr';
				descr.textContent = Nino.editor.text._groupDescr( entries );
				titleWrap.appendChild( descr );

				const chev = dc.createElement('span');
				chev.className = 'editor-view-button-chev';
				chev.setAttribute( 'aria-hidden', 'true' );
				chev.textContent = '›';

				btn.appendChild( titleWrap );
				btn.appendChild( chev );
				btn.addEventListener( 'click', function() { Nino.editor.text._openGroup( group ) } );

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

			Nino.editor.text._destroyHtmlEditors();

			Nino.editor.text._currentGroup 	= group;
			Nino.editor.text._selectedLocale = Nino.editor.sessionLocale.current ?? Nino.editor.text._locales[0] ?? '';
			Nino.editor.text._localeValues 	= {};
			Nino.editor.text._dirtyLocales 	= [];
			Nino.editor.text._fieldEls 			= {};

			Nino.editor.text._renderGroupForm();
			Nino.editor.text._showForm();
		},

		/**
		 *	Render one key as a labeled field, nino-admin-richtext or textarea+counter
		 *	depending on the entry, matching elements.js's field styling
		 *
		 *	@param		{Object}	entry					Key entry
		 *	@param		{*}				value					Current value
		 *
		 *	@return		{Element}								Field wrapper
		 */
		_renderKeyField : function( entry, value ) {

			// contenteditable is an interactive surface of its own. Nesting it in
			// a <label> is invalid interactive markup and Safari may forward the
			// drag back to the label instead of starting a text selection.
			const label = dc.createElement( entry.html === true ? 'div' : 'label' );
			label.className = 'nino-admin-field';

			if( entry.html === true ) {
				label.setAttribute( 'role', 'group' );
				label.setAttribute( 'aria-label', '[['+ entry.key+ ']]' );
				const span = dc.createElement('span');
				span.textContent = '[[' + entry.key + ']]';
				label.appendChild( span );
				const mount = dc.createElement('div');
				label.appendChild( mount );
				Nino.editor.text._htmlEditors[entry.key] = Nino.editor.htmlEditor.create( mount, value ?? '', entry.maxlength );
				return label;
			}

			const textarea = dc.createElement('textarea');
			textarea.maxLength = entry.maxlength;
			textarea.value = value ?? '';
			Nino.editor.text._fieldEls[entry.key] = textarea;

			const header = dc.createElement('div');
			header.className = 'nino-admin-field-header';

			const nameSpan = dc.createElement('span');
			nameSpan.className = 'nino-admin-field-name';
			nameSpan.textContent = '[[' + entry.key + ']]';
			header.appendChild( nameSpan );

			const counter = dc.createElement('span');
			counter.className = 'nino-admin-char-counter';

			function updateCounter() {
				const len = textarea.value.length;
				counter.textContent = len + ' / ' + entry.maxlength;
				counter.classList.toggle( 'is-limit', len >= entry.maxlength );
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
				return ( Nino.editor.text._htmlEditors[entry.key] !== undefined ) ? Nino.editor.text._htmlEditors[entry.key].getValue() : '';

			const el = Nino.editor.text._fieldEls[entry.key];
			return ( el !== undefined ) ? el.value : '';
		},

		/**
		 *	Destroy every currently mounted nino-admin-richtext instance (both global
		 *	and locale-scoped) - call before tearing down the group form
		 *
		 *	@return		void
		 */
		_destroyHtmlEditors : function() {
			Object.keys( Nino.editor.text._htmlEditors ).forEach( function( key ) { Nino.editor.text._htmlEditors[key].destroy() } );
			Nino.editor.text._htmlEditors = {};
		},

		/**
		 *	Snapshot the currently visible locale-scoped fields into
		 *	_localeValues before switching locale (or before saving)
		 *
		 *	@return		void
		 */
		_storeVisibleLocaleFields : function() {

			const group 	= Nino.editor.text._currentGroup;
			const entries = ( Nino.editor.text._groups[group] ?? [] ).filter( function( e ) { return e.global === false } );

			if( entries.length === 0 || Nino.editor.text._selectedLocale === null )
				return;

			const locale = Nino.editor.text._selectedLocale;
			const previous = Nino.editor.text._localeValues[locale] ?? Object.fromEntries( entries.map( function( entry ) {
				return [ entry.key, entry.values[locale] ?? '' ];
			} ) );
			const values = {};
			entries.forEach( function( entry ) { values[entry.key] = Nino.editor.text._readKeyValue( entry ) } );

			const changed = entries.some( function( entry ) {
				return String( previous[entry.key] ?? '' ) !== String( values[entry.key] ?? '' );
			} );

			if( changed === true && Nino.editor.text._dirtyLocales.indexOf( locale ) === -1 )
				Nino.editor.text._dirtyLocales.push( locale );

			Nino.editor.text._localeValues[locale] = values;
		},

		/**
		 *	Locales one Save click must persist, in the order they were edited.
		 *	When only global fields changed, the selected locale is a harmless
		 *	fallback that gives the save loop one request to carry them in.
		 *
		 *	@return		{Array<string>}
		 */
		_saveLocales : function() {
			const locales = Nino.editor.text._dirtyLocales.slice();
			if( locales.length === 0 )
				locales.push( Nino.editor.text._selectedLocale );
			return locales;
		},

		/**
		 *	Keep controls stable while sequential locale requests are running.
		 *	This prevents a mid-save locale switch from changing which values a
		 *	later callback believes it just persisted.
		 *
		 *	@param		{boolean}	pending
		 *
		 *	@return		void
		 */
		_setFormPending : function( pending ) {
			const form = dc.getElementById('text-edit-form');
			if( form === null )
				return;
			form.querySelectorAll('input, textarea, select, button').forEach( function( el ) { el.disabled = pending } );
			form.querySelectorAll('[contenteditable]').forEach( function( el ) {
				el.contentEditable = pending ? 'false' : 'true';
				el.setAttribute( 'aria-disabled', pending ? 'true' : 'false' );
			} );
		},

		/**
		 *	Re-render the locale-scoped fields for the currently selected locale
		 *
		 *	@return		void
		 */
		_renderLocaleFields : function() {

			const group 	= Nino.editor.text._currentGroup;
			const entries = ( Nino.editor.text._groups[group] ?? [] ).filter( function( e ) { return e.global === false } );

			entries.forEach( function( entry ) {
				if( Nino.editor.text._htmlEditors[entry.key] !== undefined ) {
					Nino.editor.text._htmlEditors[entry.key].destroy();
					delete Nino.editor.text._htmlEditors[entry.key];
				}
			} );

			const wrap = dc.getElementById('text-form-locale-fields');
			wrap.innerHTML = '';

			const stored = Nino.editor.text._localeValues[Nino.editor.text._selectedLocale] ?? {};

			entries.forEach( function( entry ) {
				const value = ( stored[entry.key] !== undefined ) ? stored[entry.key] : ( entry.values[Nino.editor.text._selectedLocale] ?? '' );
				wrap.appendChild( Nino.editor.text._renderKeyField( entry, value ) );
			} );
		},

		/**
		 *	Render the category's bulk-edit form: global fields, then a locale
		 *	select + locale-scoped fields, same shape as the Elements form
		 *
		 *	@return		void
		 */
		_renderGroupForm : function() {

			const group 	= Nino.editor.text._currentGroup;
			const entries = Nino.editor.text._groups[group] ?? [];

			const globalEntries = entries.filter( function( e ) { return e.global === true } );
			const localeEntries = entries.filter( function( e ) { return e.global === false } );

			const wrap = dc.getElementById('text-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = Nino.content.getText('/_editor/text/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.editor.text._destroyHtmlEditors(); Nino.editor.text._showList() } );
			const toolbar = Nino.editor.formToolbar( backLink );
			wrap.appendChild( toolbar );

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
				legend.textContent = Nino.content.getText('/_editor/elements/label/global');
				globalWrap.appendChild( legend );

				globalEntries.forEach( function( entry ) {
					globalWrap.appendChild( Nino.editor.text._renderKeyField( entry, entry.values['*'] ?? '' ) );
				} );

				form.appendChild( globalWrap );
			}

			if( localeEntries.length > 0 ) {

				const localeWrap = dc.createElement('fieldset');
				localeWrap.id = 'text-form-locale';
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_editor/elements/label/locale');
				localeWrap.appendChild( legend );

				const select = dc.createElement('select');
				select.id = 'text-form-locale-select';
				select.className = 'nino-admin-locale-select nino-admin-contextbar-select';
				Nino.editor.text._locales.forEach( function( locale ) {
					const option = dc.createElement('option');
					option.value = locale;
					option.textContent = locale;
					option.selected = ( locale === Nino.editor.text._selectedLocale );
					select.appendChild( option );
				} );
				select.addEventListener( 'change', function() {
					Nino.editor.text._storeVisibleLocaleFields();
					Nino.editor.text._selectedLocale = select.value;
					Nino.editor.sessionLocale.set( select.value );
					Nino.editor.text._renderLocaleFields();
				} );
				toolbar.appendChild( select );

				const fieldsWrap = dc.createElement('div');
				fieldsWrap.id = 'text-form-locale-fields';
				fieldsWrap.className = 'nino-admin-fieldgrid';
				localeWrap.appendChild( fieldsWrap );

				form.appendChild( localeWrap );
			}

			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_editor/text/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'text-form-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.editor.text._save() } );

			wrap.appendChild( form );

			if( localeEntries.length > 0 )
				Nino.editor.text._renderLocaleFields();
		},

		/**
		 *	Save every key of the current category (global fields once, plus
		 *	every locale edited before the click) in sequential batched requests.
		 *	Deliberately does not navigate back to the list afterwards - only
		 *	refreshes its preview.
		 *
		 *	@return		void
		 */
		_save : function() {

			if( Nino.editor.text._saving === true )
				return;

			Nino.editor.text._storeVisibleLocaleFields();

			const group 	= Nino.editor.text._currentGroup;
			const entries = Nino.editor.text._groups[group] ?? [];
			const msg 		= dc.getElementById('text-form-msg');

			const globalEntries = entries.filter( function( e ) { return e.global === true } );
			const localeEntries = entries.filter( function( e ) { return e.global === false } );
			const globalItems = globalEntries.map( function( entry ) {
				return { key : entry.key, locale : '*', value : Nino.editor.text._readKeyValue( entry ) };
			} );
			const locales = Nino.editor.text._saveLocales();
			let position = 0;

			Nino.editor.text._saving = true;
			Nino.editor.text._setFormPending( true );
			msg.textContent = Nino.content.getText('/_editor/text/msg/pending');

			function saveNextLocale() {
				const locale = locales[position];
				const localeValues = Nino.editor.text._localeValues[locale] ?? {};
				const localeItems = localeEntries.map( function( entry ) {
					return { key : entry.key, locale : locale, value : localeValues[entry.key] ?? entry.values[locale] ?? '' };
				} );
				const items = ( position === 0 ? globalItems : [] ).concat( localeItems );

				Nino.editor.text._apiCall( 'savebatch', { items : items }, function( status, response ) {

					if( status !== 200 || response === null ) {
						Nino.editor.text._saving = false;
						Nino.editor.text._setFormPending( false );
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/text/error/save') );
						return;
					}

					const results = response.results ?? {};
					const failed 	= [];

					items.forEach( function( item ) {
						const result = results[item.key];
						if( result === undefined || result.ok !== true ) {
							failed.push( item.key );
							return;
						}
						const entry = entries.find( function( e ) { return e.key === item.key } );
						if( entry !== undefined ) {
							entry.values[item.locale] = result.value;
							if( item.locale !== '*' ) {
								Nino.editor.text._localeValues[item.locale] = Nino.editor.text._localeValues[item.locale] ?? {};
								Nino.editor.text._localeValues[item.locale][item.key] = result.value;
							}
						}
					} );

					if( failed.length > 0 ) {
						Nino.editor.text._saving = false;
						Nino.editor.text._setFormPending( false );
						msg.textContent = Nino.content.getText('/_editor/text/error/save')+ ' ('+ failed.join(', ')+ ')';
						return;
					}

					const dirtyAt = Nino.editor.text._dirtyLocales.indexOf( locale );
					if( dirtyAt !== -1 )
						Nino.editor.text._dirtyLocales.splice( dirtyAt, 1 );

					position++;
					if( position < locales.length ) {
						saveNextLocale();
						return;
					}

					Nino.editor.text._saving = false;
					Nino.editor.text._setFormPending( false );
					msg.textContent = Nino.content.getText('/_editor/text/msg/saved');
					Nino.editor.text._renderCategoryList();
				} );
			}

			saveNextLocale();
		},
	};

})(window, document, document.documentElement, document.body);
