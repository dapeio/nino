

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Text" module: browse text-key categories (grouped by the
 *													key's first path segment, same as _editor's Text panel) and
 *													bulk-edit every key of a category's value(s) - global fields
 *													always, per-locale fields behind a locale switcher. Unlike
 *													_editor, also shows blacklisted keys and lets each key's
 *													key/global/per-locale shape/blacklist status be changed or
 *													the key deleted entirely, all inline (the "set" half - see
 *													Dev\Text's class docblock) - and a "New text key"
 *													action to create one. Full CRUD, deliberately not just a
 *													copy of _editor's values-only editor - during active
 *													development that's one less reason to switch tabs.
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
		_isNew					: false,
		_ready					: false,

		/**
		 *	Load every known text key, group them and render the category list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('text-list') === null )
				return;

			Nino.admin.text._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.text._showError( dc.getElementById('text-list'), status, response );

				Nino.admin.text._locales = response.locales;
				Nino.admin.text._groups 	= Nino.admin.text._groupEntries( response.keys );
				Nino.admin.text._renderCategoryList();
				Nino.admin.text._showList();
				Nino.admin.text._ready 	= true;
			} );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on
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
		 *	Call a devtext/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "devtext/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'devtext/'+ endpoint, data : JSON.stringify( payload ) } );
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
			p.className = 'admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			container.appendChild( p );
		},

		_showList : function() {
			dc.getElementById('text-list').classList.remove('admin-hidden');
			dc.getElementById('text-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('text-list').classList.add('admin-hidden');
			dc.getElementById('text-form').classList.remove('admin-hidden');
		},

		/**
		 *	Group key entries by the first path segment (eg. "/home/welcome/h2" -> "home")
		 *
		 *	@param		{Array}		entries				List of key entries (see Text::_entries())
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
		 *	_editor's Text panel and _admin's own Element-type list style
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
		 *	Render the scan action, category list and "add new key" action
		 *
		 *	@return		void
		 */
		_renderCategoryList : function() {

			const wrap = dc.getElementById('text-list');
			wrap.innerHTML = '';

			const scanBtn = dc.createElement('button');
			scanBtn.type = 'button';
			scanBtn.className = 'editor-list-action admin-list-action--before';
			scanBtn.textContent = 'Scan templates for missing keys';
			scanBtn.addEventListener( 'click', function() { Nino.admin.text._openScanForm() } );
			wrap.appendChild( scanBtn );

			const ul = dc.createElement('ul');
			ul.className = 'admin-drill-list';

			Object.keys( Nino.admin.text._groups ).sort().forEach( function( group ) {

				const entries = Nino.admin.text._groups[group];

				const li = dc.createElement('li');
				const link = dc.createElement('a');
				link.href = '#';
				link.dataset.group = group;

				const copy = dc.createElement('span');
				copy.className = 'admin-list-copy';
				const title = dc.createElement('strong');
				title.textContent = group;

				const descr = dc.createElement('small');
				descr.textContent = Nino.admin.text._groupDescr( entries );
				copy.appendChild( title );
				copy.appendChild( descr );
				link.appendChild( copy );

				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.text._openGroup( group ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'editor-list-action';
			addBtn.textContent = 'New text key';
			addBtn.addEventListener( 'click', function() { Nino.admin.text._openNewKeyForm() } );
			wrap.appendChild( Nino.adminUi.listActions( [ addBtn ] ) );
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

			Nino.admin.text._isNew 					= false;
			Nino.admin.text._currentGroup 	= group;
			Nino.admin.text._selectedLocale = Nino.admin.text._locales[0] ?? '';
			Nino.admin.text._localeValues 	= {};
			Nino.admin.text._fieldEls 			= {};

			Nino.admin.text._renderGroupForm();
			Nino.admin.text._showForm();
		},

		/**
		 *	Render one key as a labeled value field (html-editor or
		 *	textarea+counter), plus its global/ausgeblendet schema toggles
		 *
		 *	@param		{Object}	entry					Key entry
		 *	@param		{*}				value					Current value
		 *
		 *	@return		{Element}								<div> wrapping the field + its schema toggles
		 */
		_renderKeyField : function( entry, value ) {

			const wrap = dc.createElement('div');
			// .admin-text-field marks the three-part (header / value / schema)
			// shape assets/style.css folds into two rows from 768px up - the
			// plain .editor-field label/input pairs elsewhere in this module
			// must not be caught by that grid
			wrap.className = 'editor-field admin-text-field';

			const header = dc.createElement('div');
			header.className = 'editor-field-header';

			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.className = 'admin-text-key-input';
			keyInput.value = entry.key;
			header.appendChild( keyInput );

			const renameBtn = dc.createElement('button');
			renameBtn.type = 'button';
			renameBtn.className = 'admin-text-key-btn';
			renameBtn.textContent = 'Rename';
			renameBtn.addEventListener( 'click', function() { Nino.admin.text._renameKey( entry.key, keyInput.value ) } );
			header.appendChild( renameBtn );

			const deleteBtn = dc.createElement('button');
			deleteBtn.type = 'button';
			deleteBtn.className = 'admin-text-key-btn admin-danger-btn';
			deleteBtn.textContent = 'Delete';
			deleteBtn.addEventListener( 'click', function() { Nino.admin.text._deleteKey( entry.key ) } );
			header.appendChild( deleteBtn );

			wrap.appendChild( header );

			if( entry.html === true ) {
				const mount = dc.createElement('div');
				wrap.appendChild( mount );
				Nino.admin.text._htmlEditors[entry.key] = Nino.editor.htmlEditor.create( mount, value ?? '', entry.maxlength );
			} else {
				const textarea = dc.createElement('textarea');
				textarea.maxLength = entry.maxlength;
				textarea.value = value ?? '';
				Nino.admin.text._fieldEls[entry.key] = textarea;

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
				wrap.appendChild( textarea );
			}

			const schemaWrap = dc.createElement('div');
			schemaWrap.className = 'admin-text-schema';

			const globalLabel = dc.createElement('label');
			const globalCheck = dc.createElement('input');
			globalCheck.type = 'checkbox';
			globalCheck.checked = entry.global;
			globalCheck.addEventListener( 'change', function() {
				Nino.admin.text._saveSchema( entry.key, globalCheck.checked, blacklistCheck.checked );
			} );
			globalLabel.appendChild( globalCheck );
			globalLabel.appendChild( dc.createTextNode(' Global') );
			schemaWrap.appendChild( globalLabel );

			const blacklistLabel = dc.createElement('label');
			const blacklistCheck = dc.createElement('input');
			blacklistCheck.type = 'checkbox';
			blacklistCheck.checked = entry.blacklisted;
			blacklistCheck.addEventListener( 'change', function() {
				Nino.admin.text._saveSchema( entry.key, globalCheck.checked, blacklistCheck.checked );
			} );
			blacklistLabel.appendChild( blacklistCheck );
			blacklistLabel.appendChild( dc.createTextNode(' Hidden (in _editor)') );
			schemaWrap.appendChild( blacklistLabel );

			wrap.appendChild( schemaWrap );

			return wrap;
		},

		/**
		 *	Persist a key's global/blacklisted shape immediately (fires on
		 *	toggling either checkbox) - reloads the whole module afterwards
		 *	since a shape change moves the key between groups' locale/global
		 *	fieldsets, which the currently-open form can no longer represent
		 *
		 *	@param		{string}	key
		 *	@param		{boolean}	isGlobal
		 *	@param		{boolean}	blacklisted
		 *
		 *	@return		void
		 */
		_saveSchema : function( key, isGlobal, blacklisted ) {
			Nino.admin.text._apiCall( 'save', { key : key, global : isGlobal, blacklisted : blacklisted }, function( status, response ) {
				if( status !== 200 || response === null ) {
					wn.alert( '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' ) );
					return;
				}
				Nino.admin.text.init();
			} );
		},

		/**
		 *	Rename a key - reloads the whole module afterwards since a
		 *	rename can move the key into a different category (its first
		 *	path segment may have changed)
		 *
		 *	@param		{string}	key
		 *	@param		{string}	newKey
		 *
		 *	@return		void
		 */
		_renameKey : function( key, newKey ) {

			if( newKey === key )
				return;

			Nino.admin.text._apiCall( 'rename', { key : key, newKey : newKey }, function( status, response ) {
				if( status !== 200 || response === null ) {
					wn.alert( '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to rename.' ) );
					return;
				}
				Nino.admin.text.init();
			} );
		},

		/**
		 *	Delete a key entirely, after confirmation
		 *
		 *	@param		{string}	key
		 *
		 *	@return		void
		 */
		_deleteKey : function( key ) {

			if( wn.confirm( 'Really delete text key "'+ key+ '"? The value is lost in every language.' ) === false )
				return;

			Nino.admin.text._apiCall( 'delete', { key : key }, function( status, response ) {
				if( status !== 200 || response === null ) {
					wn.alert( '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to delete.' ) );
					return;
				}
				Nino.admin.text.init();
			} );
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
		 *	Destroy every currently mounted html-editor instance - call
		 *	before tearing down the group form
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
		 *	Render the category's bulk-edit form: global fields, then a
		 *	locale select + locale-scoped fields
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
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.text._destroyHtmlEditors(); Nino.admin.text._showList() } );

			// A category can carry hundreds of keys - the back link and the
			// locale switch ride along in one pinned row instead of scrolling
			// out of reach at the top of it (see script.js's formToolbar())
			const toolbar = Nino.admin.formToolbar( backLink );
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
				legend.textContent = 'Global';
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
				legend.textContent = 'Per language';
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
					Nino.admin.text._renderLocaleFields();
				} );

				// In the pinned toolbar, not in this fieldset's own corner:
				// which translation you are looking at has to stay switchable
				// from anywhere in a long category, not only from its top
				toolbar.appendChild( select );

				const fieldsWrap = dc.createElement('div');
				fieldsWrap.id = 'text-form-locale-fields';
				localeWrap.appendChild( fieldsWrap );

				form.appendChild( localeWrap );
			}

			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save';
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
		 *	Save every key's value of the current category (global fields
		 *	always, plus the currently selected locale's fields) in one
		 *	batched request. Deliberately does not navigate back to the
		 *	list afterwards - only refreshes its preview.
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

			msg.textContent = 'Saving …';

			Nino.admin.text._apiCall( 'savebatch', { items : items }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
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
					? 'Gespeichert.'
					: 'Fehler beim Speichern ('+ failed.join(', ')+ ')';

				Nino.admin.text._renderCategoryList();
			} );
		},

		/**
		 *	Open the "create a new key" form
		 *
		 *	@return		void
		 */
		_openNewKeyForm : function() {
			Nino.admin.text._isNew = true;
			Nino.admin.text._renderNewKeyForm();
			Nino.admin.text._showForm();
		},

		/**
		 *	Render the "create a new key" form: key, global toggle, initial value
		 *
		 *	@return		void
		 */
		_renderNewKeyForm : function() {

			const wrap = dc.getElementById('text-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.text._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');

			const keyLabel = dc.createElement('label');
			keyLabel.className = 'editor-field';
			const keySpan = dc.createElement('span');
			keySpan.textContent = 'Key (eg. /home/welcome/subtitle)';
			keyLabel.appendChild( keySpan );
			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.id = 'text-form-key';
			keyInput.required = true;
			keyLabel.appendChild( keyInput );
			form.appendChild( keyLabel );

			const globalLabel = dc.createElement('label');
			const globalCheck = dc.createElement('input');
			globalCheck.type = 'checkbox';
			globalCheck.id = 'text-form-new-global';
			globalLabel.appendChild( globalCheck );
			globalLabel.appendChild( dc.createTextNode(' Global (one translation for all languages, instead of one per language)') );
			form.appendChild( globalLabel );

			const valueLabel = dc.createElement('label');
			valueLabel.className = 'editor-field';
			const valueSpan = dc.createElement('span');
			valueSpan.textContent = 'Initial value (for global, or as a starting point for every language)';
			valueLabel.appendChild( valueSpan );
			const valueInput = dc.createElement('input');
			valueInput.type = 'text';
			valueInput.id = 'text-form-new-value';
			valueLabel.appendChild( valueInput );
			form.appendChild( valueLabel );

			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Create';
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'text-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.text._saveNewKey() } );

			wrap.appendChild( form );
		},

		/**
		 *	Create the new key currently in the form
		 *
		 *	@return		void
		 */
		_saveNewKey : function() {

			const msg 		= dc.getElementById('text-form-msg');
			const key 		= dc.getElementById('text-form-key').value;
			const isGlobal = dc.getElementById('text-form-new-global').checked;
			const value 	= dc.getElementById('text-form-new-value').value;

			msg.textContent = 'Saving …';

			Nino.admin.text._apiCall( 'create', { key : key, global : isGlobal, value : value }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}
				Nino.admin.text.init();
			} );
		},

		/**
		 *	Open the "missing keys found in templates" scan results form -
		 *	see Dev\Text::apiScan()
		 *
		 *	@return		void
		 */
		_openScanForm : function() {

			const wrap = dc.getElementById('text-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.text._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = 'Scan templates for missing keys';
			wrap.appendChild( title );

			const msg = dc.createElement('p');
			msg.id = 'text-scan-msg';
			msg.textContent = 'Scanning …';
			wrap.appendChild( msg );

			Nino.admin.text._showForm();

			Nino.admin.text._apiCall( 'scan', {}, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Scan failed.' );
					return;
				}
				Nino.admin.text._renderScanForm( response.missing );
			} );
		},

		/**
		 *	Render the scan results: one row per missing key (starting-value
		 *	input + "Ignore" toggle), a submit button that creates every
		 *	non-ignored one via the existing devtext/create action - the
		 *	same "one starting value, copied into every language" semantics
		 *	_openNewKeyForm() already uses, since the value is just a
		 *	starting point to be translated later (see _editor's JSON export/
		 *	import round-trip)
		 *
		 *	@param		{Array}		missing				[ { key, files[] }, ... ]
		 *
		 *	@return		void
		 */
		_renderScanForm : function( missing ) {

			const wrap = dc.getElementById('text-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.text._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = 'Scan templates for missing keys';
			wrap.appendChild( title );

			if( missing.length === 0 ) {
				const p = dc.createElement('p');
				p.textContent = 'No missing keys found - every [[/key]] referenced in templates/*.tpl is already defined.';
				wrap.appendChild( p );
				return;
			}

			const form = dc.createElement('form');
			const rows = [];

			missing.forEach( function( item ) {

				const field = dc.createElement('div');
				field.className = 'editor-field';

				const span = dc.createElement('span');
				span.textContent = item.key+ '  ('+ item.files.join(', ')+ ')';
				field.appendChild( span );

				const valueInput = dc.createElement('input');
				valueInput.type = 'text';
				valueInput.placeholder = 'Starting value for every language';
				field.appendChild( valueInput );

				const ignoreLabel = dc.createElement('label');
				ignoreLabel.className = 'admin-scan-ignore';
				const ignoreCheck = dc.createElement('input');
				ignoreCheck.type = 'checkbox';
				ignoreLabel.appendChild( ignoreCheck );
				ignoreLabel.appendChild( dc.createTextNode(' Ignore') );
				field.appendChild( ignoreLabel );

				form.appendChild( field );

				rows.push( { key : item.key, valueInput : valueInput, ignoreCheck : ignoreCheck } );
			} );

			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Create the non-ignored keys';
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'text-scan-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.text._saveScanResults( rows ) } );

			wrap.appendChild( form );
		},

		/**
		 *	Create every non-ignored row's key (see _renderScanForm()), one
		 *	devtext/create call at a time - simplest way to surface a
		 *	per-key failure (eg. a duplicate created elsewhere in the
		 *	meantime) without one bad key silently swallowing the rest
		 *
		 *	@param		{Array}		rows					[ { key, valueInput, ignoreCheck }, ... ]
		 *
		 *	@return		void
		 */
		_saveScanResults : function( rows ) {

			const msg 		= dc.getElementById('text-scan-msg');
			const pending = rows.filter( function( row ) { return row.ignoreCheck.checked === false } );

			if( pending.length === 0 ) {
				Nino.admin.text._showList();
				return;
			}

			let created = 0;
			const failed = [];

			function next( i ) {

				if( i >= pending.length ) {
					if( failed.length > 0 )
						wn.alert( created+ ' created. Failed: '+ failed.join(', ') );
					Nino.admin.text.init();
					return;
				}

				const row = pending[i];
				msg.textContent = 'Creating '+ row.key+ ' ('+ (i + 1)+ ' / '+ pending.length+ ') …';

				Nino.admin.text._apiCall( 'create', { key : row.key, global : false, value : row.valueInput.value }, function( status ) {
					if( status === 200 )
						created++;
					else
						failed.push( row.key );
					next( i + 1 );
				} );
			}

			next( 0 );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.text.init );

})(window, document, document.documentElement, document.body);
