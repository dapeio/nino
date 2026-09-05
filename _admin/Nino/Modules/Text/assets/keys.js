

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Text" module: browse text-key categories (grouped by the
 *													key's first path segment, same as the Text panel) and
 *													bulk-edit every key of a category's value(s) - global fields
 *													always, per-locale fields behind a locale switcher. Unlike
 *													the Text panel, also shows blacklisted keys and lets each key's
 *													key/global/per-locale shape/blacklist status be changed or
 *													the key deleted entirely, all inline (the "set" half - see
 *													Dev\Text's class docblock) - and a "New text key"
 *													action to create one. Full CRUD, deliberately not just a
 *													copy of the Text panel's values-only editor - during active
 *													development that's one less reason to switch tabs.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.keys = {

		_locales				: [],
		_groups					: {},
		_currentGroup		: null,
		_selectedLocale	: null,
		_localeValues		: {},
		_htmlEditors		: {},
		_fieldEls				: {},
		_isNew					: false,
		_ready					: false,
		// What the last scan pass did, shown once above the category list -
		// the form it happened in is gone by then (see _saveScanResults())
		_scanSummary		: '',

		/**
		 *	Load every known text key, group them and render the category list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('keys-list') === null )
				return;

			Nino.admin.keys._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.keys._showError( dc.getElementById('keys-list'), status, response );

				Nino.admin.keys._locales = response.locales;
				Nino.admin.keys._groups 	= Nino.admin.keys._groupEntries( response.keys );
				Nino.admin.keys._renderCategoryList();
				Nino.admin.keys._showList();
				Nino.admin.keys._ready 	= true;
			} );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.keys._ready === false )
				return;

			if( dc.getElementById('keys-form').classList.contains('admin-hidden') === false )
				return Nino.admin.keys._showForm();

			Nino.admin.keys._showList();
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
			}, { action : 'keys/'+ endpoint, data : JSON.stringify( payload ) } );
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
			dc.getElementById('keys-list').classList.remove('admin-hidden');
			dc.getElementById('keys-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('keys-list').classList.add('admin-hidden');
			dc.getElementById('keys-form').classList.remove('admin-hidden');
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
			const value = entry.global ? ( entry.values['*'] ?? '' ) : ( entry.values[Nino.admin.keys._locales[0]] ?? '' );
			return String( value ?? '' ).replace(/<[^>]+>/g, '').trim();
		},

		/**
		 *	Build a group's "(N) preview, preview, .." description, matching
		 *	the Text panel's and the Element Types list style
		 *
		 *	@param		{Array}		entries
		 *
		 *	@return		{string}
		 */
		_groupDescr : function( entries ) {
			const parts 	= entries.map( function( e ) { return Nino.admin.keys._preview( e ) } ).filter( function( s ) { return s !== '' } );
			const joined 	= parts.join(', ');
			return '('+ entries.length+ ') '+ ( joined.length > 150 ? joined.slice( 0, 150 )+ ' ..' : joined );
		},

		/**
		 *	Render the scan action, category list and "add new key" action
		 *
		 *	@return		void
		 */
		_renderCategoryList : function() {

			const wrap = dc.getElementById('keys-list');
			wrap.innerHTML = '';

			if( Nino.admin.keys._scanSummary !== '' ) {
				const summary = dc.createElement('p');
				summary.className = 'nino-admin-hint';
				summary.setAttribute( 'aria-live', 'polite' );
				summary.textContent = Nino.admin.keys._scanSummary;
				wrap.appendChild( summary );
				Nino.admin.keys._scanSummary = '';
			}

			const groups = Object.keys( Nino.admin.keys._groups ).sort();
			if( groups.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/keys/empty') ) );

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';

			groups.forEach( function( group ) {

				const entries = Nino.admin.keys._groups[group];

				const li = dc.createElement('li');
				const link = dc.createElement('a');
				link.href = '#';
				link.dataset.group = group;

				const copy = dc.createElement('span');
				copy.className = 'nino-admin-list-copy';
				const title = dc.createElement('strong');
				title.textContent = group;

				const descr = dc.createElement('small');
				descr.textContent = Nino.admin.keys._groupDescr( entries );
				copy.appendChild( title );
				copy.appendChild( descr );
				link.appendChild( copy );

				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.keys._openGroup( group ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			if( groups.length > 0 )
				wrap.appendChild( ul );

			const scanBtn = dc.createElement('button');
			scanBtn.type = 'button';
			scanBtn.className = 'nino-admin-btn-secondary';
			scanBtn.textContent = Nino.content.getText('/_admin/keys/label/scan');
			scanBtn.addEventListener( 'click', function() { Nino.admin.keys._openScanForm() } );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'nino-admin-btn-primary';
			addBtn.textContent = Nino.content.getText('/_admin/keys/label/new');
			addBtn.addEventListener( 'click', function() { Nino.admin.keys._openNewKeyForm() } );
			wrap.appendChild( Nino.adminUi.listActions( [ scanBtn, addBtn ] ) );
		},

		/**
		 *	Open a category's bulk-edit form
		 *
		 *	@param		{string}	group
		 *
		 *	@return		void
		 */
		_openGroup : function( group ) {

			Nino.admin.keys._destroyHtmlEditors();

			Nino.admin.keys._isNew 					= false;
			Nino.admin.keys._currentGroup 	= group;
			Nino.admin.keys._selectedLocale = Nino.admin.keys._locales[0] ?? '';
			Nino.admin.keys._localeValues 	= {};
			Nino.admin.keys._fieldEls 			= {};

			Nino.admin.keys._renderGroupForm();
			Nino.admin.keys._showForm();
		},

		/**
		 *	Render one key as a labeled value field (nino-admin-richtext or
		 *	textarea+counter), plus its global/ausgeblendet schema toggles
		 *
		 *	@param		{Object}	entry					Key entry
		 *	@param		{*}				value					Current value
		 *
		 *	@return		{Element}								<div> wrapping the field + its schema toggles
		 */
		_renderKeyField : function( entry, value ) {

			const wrap = dc.createElement('div');
			// .nino-admin-field-wide marks the three-part (header / value / schema)
			// shape assets/style.css folds into two rows from 768px up - the
			// plain .nino-admin-field label/input pairs elsewhere in this module
			// must not be caught by that grid
			wrap.className = 'nino-admin-field nino-admin-field-wide';

			const header = dc.createElement('div');
			header.className = 'nino-admin-field-header';

			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.className = 'admin-text-key-input';
			keyInput.value = entry.key;
			header.appendChild( keyInput );

			const renameBtn = dc.createElement('button');
			renameBtn.type = 'button';
			renameBtn.className = 'admin-text-key-btn';
			renameBtn.textContent = Nino.content.getText('/_admin/common/label/rename');
			renameBtn.addEventListener( 'click', function() { Nino.admin.keys._renameKey( entry.key, keyInput.value ) } );
			header.appendChild( renameBtn );

			const deleteBtn = dc.createElement('button');
			deleteBtn.type = 'button';
			deleteBtn.className = 'admin-text-key-btn nino-admin-btn-danger';
			deleteBtn.textContent = Nino.content.getText('/_admin/common/label/delete');
			deleteBtn.addEventListener( 'click', function() { Nino.admin.keys._deleteKey( entry.key ) } );
			header.appendChild( deleteBtn );

			wrap.appendChild( header );

			if( entry.html === true ) {
				const mount = dc.createElement('div');
				wrap.appendChild( mount );
				Nino.admin.keys._htmlEditors[entry.key] = Nino.admin.htmlEditor.create( mount, value ?? '', entry.maxlength );
			} else {
				const textarea = dc.createElement('textarea');
				textarea.maxLength = entry.maxlength;
				textarea.value = value ?? '';
				Nino.admin.keys._fieldEls[entry.key] = textarea;

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
				wrap.appendChild( textarea );
			}

			const schemaWrap = dc.createElement('div');
			schemaWrap.className = 'admin-text-schema';

			const globalLabel = dc.createElement('label');
			const globalCheck = dc.createElement('input');
			globalCheck.type = 'checkbox';
			globalCheck.checked = entry.global;
			globalCheck.addEventListener( 'change', function() {
				Nino.admin.keys._saveSchema( entry.key, globalCheck.checked, blacklistCheck.checked );
			} );
			globalLabel.appendChild( globalCheck );
			globalLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/common/label/global') ) );
			schemaWrap.appendChild( globalLabel );

			const blacklistLabel = dc.createElement('label');
			const blacklistCheck = dc.createElement('input');
			blacklistCheck.type = 'checkbox';
			blacklistCheck.checked = entry.blacklisted;
			blacklistCheck.addEventListener( 'change', function() {
				Nino.admin.keys._saveSchema( entry.key, globalCheck.checked, blacklistCheck.checked );
			} );
			blacklistLabel.appendChild( blacklistCheck );
			blacklistLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/keys/label/blacklist') ) );
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
			Nino.admin.keys._apiCall( 'save', { key : key, global : isGlobal, blacklisted : blacklisted }, function( status, response ) {
				if( status !== 200 || response === null ) {
					wn.alert( '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') ) );
					return;
				}
				Nino.admin.keys.init();
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

			Nino.admin.keys._apiCall( 'rename', { key : key, newKey : newKey }, function( status, response ) {
				if( status !== 200 || response === null ) {
					wn.alert( '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/rename') ) );
					return;
				}
				Nino.admin.keys.init();
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

			if( wn.confirm( Nino.content.getText('/_admin/keys/confirm/delete').replace( '%s', key ) ) === false )
				return;

			Nino.admin.keys._apiCall( 'delete', { key : key }, function( status, response ) {
				if( status !== 200 || response === null ) {
					wn.alert( '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/delete') ) );
					return;
				}
				Nino.admin.keys.init();
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
				return ( Nino.admin.keys._htmlEditors[entry.key] !== undefined ) ? Nino.admin.keys._htmlEditors[entry.key].getValue() : '';

			const el = Nino.admin.keys._fieldEls[entry.key];
			return ( el !== undefined ) ? el.value : '';
		},

		/**
		 *	Destroy every currently mounted nino-admin-richtext instance - call
		 *	before tearing down the group form
		 *
		 *	@return		void
		 */
		_destroyHtmlEditors : function() {
			Object.keys( Nino.admin.keys._htmlEditors ).forEach( function( key ) { Nino.admin.keys._htmlEditors[key].destroy() } );
			Nino.admin.keys._htmlEditors = {};
		},

		/**
		 *	Snapshot the currently visible locale-scoped fields into
		 *	_localeValues before switching locale (or before saving)
		 *
		 *	@return		void
		 */
		_storeVisibleLocaleFields : function() {

			const group 	= Nino.admin.keys._currentGroup;
			const entries = ( Nino.admin.keys._groups[group] ?? [] ).filter( function( e ) { return e.global === false } );

			if( entries.length === 0 || Nino.admin.keys._selectedLocale === null )
				return;

			const values = {};
			entries.forEach( function( entry ) { values[entry.key] = Nino.admin.keys._readKeyValue( entry ) } );

			Nino.admin.keys._localeValues[Nino.admin.keys._selectedLocale] = values;
		},

		/**
		 *	Re-render the locale-scoped fields for the currently selected locale
		 *
		 *	@return		void
		 */
		_renderLocaleFields : function() {

			const group 	= Nino.admin.keys._currentGroup;
			const entries = ( Nino.admin.keys._groups[group] ?? [] ).filter( function( e ) { return e.global === false } );

			entries.forEach( function( entry ) {
				if( Nino.admin.keys._htmlEditors[entry.key] !== undefined ) {
					Nino.admin.keys._htmlEditors[entry.key].destroy();
					delete Nino.admin.keys._htmlEditors[entry.key];
				}
			} );

			const wrap = dc.getElementById('keys-form-locale-fields');
			wrap.innerHTML = '';

			const stored = Nino.admin.keys._localeValues[Nino.admin.keys._selectedLocale] ?? {};

			entries.forEach( function( entry ) {
				const value = ( stored[entry.key] !== undefined ) ? stored[entry.key] : ( entry.values[Nino.admin.keys._selectedLocale] ?? '' );
				wrap.appendChild( Nino.admin.keys._renderKeyField( entry, value ) );
			} );
		},

		/**
		 *	Render the category's bulk-edit form: global fields, then a
		 *	locale select + locale-scoped fields
		 *
		 *	@return		void
		 */
		_renderGroupForm : function() {

			const group 	= Nino.admin.keys._currentGroup;
			const entries = Nino.admin.keys._groups[group] ?? [];

			const globalEntries = entries.filter( function( e ) { return e.global === true } );
			const localeEntries = entries.filter( function( e ) { return e.global === false } );

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.keys._destroyHtmlEditors(); Nino.admin.keys._showList() } );

			// A category can carry hundreds of keys - the back link and the
			// locale switch ride along in one pinned row instead of scrolling
			// out of reach at the top of it (see script.js's formToolbar())
			const toolbar = Nino.admin.formToolbar( backLink );
			wrap.appendChild( toolbar );

			const form = dc.createElement('form');
			form.id = 'keys-edit-form';

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = group;
			wrap.appendChild( title );

			if( globalEntries.length > 0 ) {

				const globalWrap = dc.createElement('fieldset');
				globalWrap.id = 'keys-form-global';
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_admin/common/label/global');
				globalWrap.appendChild( legend );

				globalEntries.forEach( function( entry ) {
					globalWrap.appendChild( Nino.admin.keys._renderKeyField( entry, entry.values['*'] ?? '' ) );
				} );

				form.appendChild( globalWrap );
			}

			if( localeEntries.length > 0 ) {

				const localeWrap = dc.createElement('fieldset');
				localeWrap.id = 'keys-form-locale';
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_admin/keys/label/perlocale');
				localeWrap.appendChild( legend );

				const select = dc.createElement('select');
				select.id = 'keys-form-locale-select';
				select.className = 'nino-admin-locale-select nino-admin-contextbar-select';
				Nino.admin.keys._locales.forEach( function( locale ) {
					const option = dc.createElement('option');
					option.value = locale;
					option.textContent = locale;
					option.selected = ( locale === Nino.admin.keys._selectedLocale );
					select.appendChild( option );
				} );
				select.addEventListener( 'change', function() {
					Nino.admin.keys._storeVisibleLocaleFields();
					Nino.admin.keys._selectedLocale = select.value;
					Nino.admin.keys._renderLocaleFields();
				} );

				// In the pinned toolbar, not in this fieldset's own corner:
				// which translation you are looking at has to stay switchable
				// from anywhere in a long category, not only from its top
				toolbar.appendChild( select );

				const fieldsWrap = dc.createElement('div');
				fieldsWrap.id = 'keys-form-locale-fields';
				fieldsWrap.className = 'nino-admin-fieldgrid';
				localeWrap.appendChild( fieldsWrap );

				form.appendChild( localeWrap );
			}

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'keys-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.keys._save() } );

			wrap.appendChild( form );

			if( localeEntries.length > 0 )
				Nino.admin.keys._renderLocaleFields();
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

			Nino.admin.keys._storeVisibleLocaleFields();

			const group 	= Nino.admin.keys._currentGroup;
			const entries = Nino.admin.keys._groups[group] ?? [];
			const msg 		= dc.getElementById('keys-form-msg');

			const items = [];

			entries.filter( function( e ) { return e.global === true } ).forEach( function( entry ) {
				items.push( { key : entry.key, locale : '*', value : Nino.admin.keys._readKeyValue( entry ) } );
			} );

			const localeValues = Nino.admin.keys._localeValues[Nino.admin.keys._selectedLocale] ?? {};
			entries.filter( function( e ) { return e.global === false } ).forEach( function( entry ) {
				items.push( { key : entry.key, locale : Nino.admin.keys._selectedLocale, value : localeValues[entry.key] ?? '' } );
			} );

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.keys._apiCall( 'savebatch', { items : items }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
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
					? Nino.content.getText('/_admin/common/msg/saved')
					: Nino.content.getText('/_admin/keys/error/save-partial').replace( '%s', failed.join(', ') );

				Nino.admin.keys._renderCategoryList();
			} );
		},

		/**
		 *	Open the "create a new key" form
		 *
		 *	@return		void
		 */
		_openNewKeyForm : function() {
			Nino.admin.keys._isNew = true;
			Nino.admin.keys._renderNewKeyForm();
			Nino.admin.keys._showForm();
		},

		/**
		 *	Render the "create a new key" form: key, global toggle, initial value
		 *
		 *	@return		void
		 */
		_renderNewKeyForm : function() {

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.keys._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');

			const keyLabel = dc.createElement('label');
			keyLabel.className = 'nino-admin-field';
			const keySpan = dc.createElement('span');
			keySpan.textContent = Nino.content.getText('/_admin/keys/label/key');
			keyLabel.appendChild( keySpan );
			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.id = 'keys-form-key';
			keyInput.required = true;
			keyLabel.appendChild( keyInput );
			form.appendChild( keyLabel );

			const globalLabel = dc.createElement('label');
			const globalCheck = dc.createElement('input');
			globalCheck.type = 'checkbox';
			globalCheck.id = 'keys-form-new-global';
			globalLabel.appendChild( globalCheck );
			globalLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/keys/label/global-hint') ) );
			form.appendChild( globalLabel );

			const valueLabel = dc.createElement('label');
			valueLabel.className = 'nino-admin-field';
			const valueSpan = dc.createElement('span');
			valueSpan.textContent = Nino.content.getText('/_admin/keys/label/initial');
			valueLabel.appendChild( valueSpan );
			const valueInput = dc.createElement('input');
			valueInput.type = 'text';
			valueInput.id = 'keys-form-new-value';
			valueLabel.appendChild( valueInput );
			form.appendChild( valueLabel );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/create');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'keys-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.keys._saveNewKey() } );

			wrap.appendChild( form );
		},

		/**
		 *	Create the new key currently in the form
		 *
		 *	@return		void
		 */
		_saveNewKey : function() {

			const msg 		= dc.getElementById('keys-form-msg');
			const key 		= dc.getElementById('keys-form-key').value;
			const isGlobal = dc.getElementById('keys-form-new-global').checked;
			const value 	= dc.getElementById('keys-form-new-value').value;

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.keys._apiCall( 'create', { key : key, global : isGlobal, value : value }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
					return;
				}
				Nino.admin.keys.init();
			} );
		},

		/**
		 *	Open the "missing keys found in templates" scan results form -
		 *	see Dev\Text::apiScan()
		 *
		 *	@return		void
		 */
		_openScanForm : function() {

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.keys._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = Nino.content.getText('/_admin/keys/label/scan');
			wrap.appendChild( title );

			const msg = dc.createElement('p');
			msg.id = 'keys-scan-msg';
			msg.textContent = Nino.content.getText('/_admin/common/msg/scanning');
			wrap.appendChild( msg );

			Nino.admin.keys._showForm();

			Nino.admin.keys._apiCall( 'scan', {}, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/scan') );
					return;
				}
				Nino.admin.keys._renderScanForm( response.missing );
			} );
		},

		/**
		 *	Render the scan results: one row per missing key (starting-value
		 *	input + "Ignore permanently" toggle) and a submit button that
		 *	hands the whole list to keys/scanapply.
		 *
		 *	Each row has three possible answers, and doing nothing is one of
		 *	them: a value creates the key (one starting value, copied into
		 *	every language, to be translated later - see the Translations
		 *	tab's JSON export/import round-trip), an empty field is passed
		 *	over until the next scan, and "ignore" retires the key for good.
		 *	That is what makes a long list workable in several sittings
		 *	instead of one all-or-nothing pass.
		 *
		 *	@param		{Array}		missing				[ { key, files[] }, ... ]
		 *
		 *	@return		void
		 */
		_renderScanForm : function( missing ) {

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.keys._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = Nino.content.getText('/_admin/keys/label/scan');
			wrap.appendChild( title );

			if( missing.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/keys/scan/none') ) );
				return;
			}

			// Said once, at the top: without it the empty rows look like rows
			// that were forgotten rather than rows that were left for later
			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.content.getText('/_admin/keys/scan/hint');
			wrap.appendChild( hint );

			const form = dc.createElement('form');
			const rows = [];

			missing.forEach( function( item ) {

				const field = dc.createElement('div');
				field.className = 'nino-admin-field';

				const span = dc.createElement('span');
				span.textContent = item.key+ '  ('+ item.files.join(', ')+ ')';
				field.appendChild( span );

				const valueInput = dc.createElement('input');
				valueInput.type = 'text';
				valueInput.placeholder = Nino.content.getText('/_admin/keys/label/scan-value');
				field.appendChild( valueInput );

				const ignoreLabel = dc.createElement('label');
				ignoreLabel.className = 'admin-scan-ignore';
				const ignoreCheck = dc.createElement('input');
				ignoreCheck.type = 'checkbox';
				ignoreLabel.appendChild( ignoreCheck );
				ignoreLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/keys/label/scan-ignore') ) );
				field.appendChild( ignoreLabel );

				form.appendChild( field );

				rows.push( { key : item.key, valueInput : valueInput, ignoreCheck : ignoreCheck } );
			} );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/keys/label/scan-create');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'keys-scan-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.keys._saveScanResults( rows ) } );

			wrap.appendChild( form );
		},

		/**
		 *	Hand the whole scan form to keys/scanapply in one request and say
		 *	what came of it.
		 *
		 *	One call rather than a create per row: the three outcomes are one
		 *	decision per key and the server applies them together, so a row
		 *	that fails cannot leave the list half-answered - and the summary
		 *	is a single readable line instead of an alert() naming keys.
		 *
		 *	@param		{Array}		rows					[ { key, valueInput, ignoreCheck }, ... ]
		 *
		 *	@return		void
		 */
		_saveScanResults : function( rows ) {

			const msg = dc.getElementById('keys-scan-msg');
			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			const payload = rows.map( function( row ) {
				return { key : row.key, value : row.valueInput.value, ignore : row.ignoreCheck.checked };
			} );

			Nino.admin.keys._apiCall( 'scanapply', { rows : payload }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
					return;
				}

				Nino.admin.keys._scanSummary = Nino.content.getText('/_admin/keys/scan/result')
					.replace( '%c', String( response.created ) )
					.replace( '%i', String( response.ignored ) )
					.replace( '%s', String( response.skipped ) );

				// Back to the list, which reloads from the server: the keys
				// just created belong in their categories, and a retired one
				// belongs in the list as a hidden key that can be brought back
				Nino.admin.keys._showList();
				Nino.admin.keys.init();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.keys.init );

})(window, document, document.documentElement, document.body);
