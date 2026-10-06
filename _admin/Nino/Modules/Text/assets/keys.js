

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Text" module: browse text-key categories (grouped by the
 *													key's first path segment, same as the Text panel) and
 *													bulk-edit every key of a category's value(s) - global fields
 *													always, per-locale fields behind a locale switcher. Unlike
 *													the Text panel, also shows blacklisted keys and lets each key's
 *													key/global/per-locale shape/blacklist status be changed or
 *													the key deleted entirely, all inline (the "set" half - see
 *													the Keys class docblock) - and a "New text key"
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

		// The formats a key can be kept in, from the narrowest to the widest -
		// \Nino\Html::FORMATS, in the same order, which is what says whether
		// a change loses anything
		FORMATS					: [ 'plain', 'inline', 'lines', 'blocks' ],

		_locales				: [],
		_groups					: {},
		_currentGroup		: null,
		_selectedLocale	: null,
		_localeValues		: {},
		// The translations edited since the group was opened, in the order they
		// were edited - what one Save writes (see _saveLocales())
		_dirtyLocales		: [],
		// What the controls of the open group held when they were drawn or last
		// saved, key -> value: the global fields, and the translation on screen
		// (see Nino.admin.text, which keeps the same)
		_baseline				: { global : {}, locale : {} },
		// Which form the pane shows - 'group', 'new' or 'scan' - and the rows
		// of the scan form, for isDirty()
		_view						: null,
		_scanRows				: [],
		_htmlEditors		: {},
		_fieldEls				: {},
		_isNew					: false,
		_saving					: false,
		_ready					: false,
		// The category to open again once the list has loaded - what applying a
		// key's format and limit comes back to (see _saveSettings())
		_reopen					: null,
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

				const reopen = Nino.admin.keys._reopen;
				Nino.admin.keys._reopen = null;

				if( reopen !== null && Nino.admin.keys._groups[reopen] !== undefined )
					Nino.admin.keys._openGroup( reopen );
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
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'keys/'+ endpoint, payload, callback );
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
			dc.getElementById('keys-list').classList.remove('admin-hidden');
			dc.getElementById('keys-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('keys-list').classList.add('admin-hidden');
			dc.getElementById('keys-form').classList.remove('admin-hidden');
		},

		/**
		 *	Group key entries by the first path segment (eg. "/template/page-home/welcome/title" -> "template")
		 *
		 *	@param		{Array}		entries				List of key entries (see \Nino\Text::entries())
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
			Nino.admin.keys._dirtyLocales 	= [];
			Nino.admin.keys._baseline 			= { global : {}, locale : {} };
			Nino.admin.keys._view 					= 'group';
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
				Nino.admin.keys._htmlEditors[entry.key] = Nino.admin.htmlEditor.create( mount, value ?? '', entry.maxlength, 0, entry.format );
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
				Nino.admin.keys._saveSchema( entry.key, globalCheck.checked, blacklistCheck.checked, function() { globalCheck.checked = ! globalCheck.checked } );
			} );
			globalLabel.appendChild( globalCheck );
			globalLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/common/label/global') ) );
			schemaWrap.appendChild( globalLabel );

			const blacklistLabel = dc.createElement('label');
			const blacklistCheck = dc.createElement('input');
			blacklistCheck.type = 'checkbox';
			blacklistCheck.checked = entry.blacklisted;
			blacklistCheck.addEventListener( 'change', function() {
				Nino.admin.keys._saveSchema( entry.key, globalCheck.checked, blacklistCheck.checked, function() { blacklistCheck.checked = ! blacklistCheck.checked } );
			} );
			blacklistLabel.appendChild( blacklistCheck );
			blacklistLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/keys/label/blacklist') ) );
			schemaWrap.appendChild( blacklistLabel );

			// The format the value is kept in and the limit its counter counts
			// to. Neither is saved with the values: applying them converts what is
			// stored, which is a decision of its own (see _saveSettings())
			const formatLabel = dc.createElement('label');
			formatLabel.appendChild( dc.createTextNode( Nino.content.getText('/_admin/keys/label/format')+ ' ' ) );
			const formatSelect = dc.createElement('select');
			formatSelect.className = 'admin-text-format-select';
			[ 'auto' ].concat( Nino.admin.keys.FORMATS ).forEach( function( format ) {
				const option = dc.createElement('option');
				option.value = format;
				option.textContent = Nino.content.getText('/_admin/keys/format/'+ format);
				option.selected = ( format === ( entry.formatSet === true ? entry.format : 'auto' ) );
				formatSelect.appendChild( option );
			} );
			formatLabel.appendChild( formatSelect );
			schemaWrap.appendChild( formatLabel );

			const limitLabel = dc.createElement('label');
			limitLabel.appendChild( dc.createTextNode( Nino.content.getText('/_admin/keys/label/limit')+ ' ' ) );
			const limitInput = dc.createElement('input');
			limitInput.type = 'number';
			limitInput.min = '1';
			limitInput.className = 'admin-text-limit-input';
			limitInput.placeholder = Nino.content.getText('/_admin/keys/placeholder/limit');
			limitInput.value = entry.maxlengthSet === true ? String( entry.maxlength ) : '';
			limitLabel.appendChild( limitInput );
			schemaWrap.appendChild( limitLabel );

			const applyBtn = dc.createElement('button');
			applyBtn.type = 'button';
			applyBtn.className = 'admin-text-key-btn';
			applyBtn.textContent = Nino.content.getText('/_admin/keys/label/apply');
			applyBtn.addEventListener( 'click', function() {
				Nino.admin.keys._saveSettings( entry, globalCheck.checked, blacklistCheck.checked, formatSelect.value, limitInput.value );
			} );
			schemaWrap.appendChild( applyBtn );

			wrap.appendChild( schemaWrap );

			return wrap;
		},

		/**
		 *	Apply a key's format and limit - together with the two checkboxes it
		 *	is posted with, which keys/save takes as one request. A format that
		 *	holds less than the one before it converts every stored text of the
		 *	key, in every language, so it is asked about first and the question
		 *	says what happens to the text. The reload that follows draws the
		 *	category list, which drops what is typed into the open group: the
		 *	shell asks about unsaved input first (see _guard()), and the group
		 *	is opened again afterwards
		 *
		 *	@param		{Object}	entry					Key entry
		 *	@param		{boolean}	isGlobal
		 *	@param		{boolean}	blacklisted
		 *	@param		{string}	format				'auto' or one of FORMATS
		 *	@param		{string}	limit					The limit as typed, '' for automatic
		 *
		 *	@return		void
		 */
		_saveSettings : function( entry, isGlobal, blacklisted, format, limit ) {

			const formats = Nino.admin.keys.FORMATS;

			if( format !== 'auto' && format !== entry.format && formats.indexOf( format ) < formats.indexOf( entry.format ) ) {

				const question = Nino.adminUi.format( Nino.content.getText('/_admin/keys/confirm/format'),
					entry.key,
					Nino.content.getText('/_admin/keys/format/'+ entry.format ),
					Nino.content.getText('/_admin/keys/format/'+ format ),
					Nino.content.getText('/_admin/keys/convert/'+ format )
				);

				if( wn.confirm( question ) === false )
					return;
			}

			const group = Nino.admin.keys._currentGroup;
			const posted = { key : entry.key, global : isGlobal, blacklisted : blacklisted, format : format, maxlength : limit === '' ? null : Number( limit ) };

			Nino.admin.keys._guard( function() {
				Nino.admin.keys._apiCall( 'save', posted, function( status, response ) {
					if( status !== 200 || response === null ) {
						wn.alert( Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
						return;
					}
					Nino.admin.keys._reopen = group;
					Nino.admin.keys.init();
				} );
			} );
		},

		/**
		 *	Persist a key's global/blacklisted shape immediately (fires on
		 *	toggling either checkbox) - reloads the whole module afterwards
		 *	since a shape change moves the key between groups' locale/global
		 *	fieldsets, which the currently-open form can no longer represent
		 *
		 *	The reload drops what is typed into the open group, so unsaved input
		 *	is asked about first (see Nino.admin.dirty.guard()); a Cancel puts the
		 *	checkbox that was just clicked back
		 *
		 *	@param		{string}	key
		 *	@param		{boolean}	isGlobal
		 *	@param		{boolean}	blacklisted
		 *	@param		{Function}	[undo]				Puts the clicked checkbox back
		 *
		 *	@return		void
		 */
		_saveSchema : function( key, isGlobal, blacklisted, undo ) {
			Nino.admin.keys._guard( function() {
				Nino.admin.keys._apiCall( 'save', { key : key, global : isGlobal, blacklisted : blacklisted }, function( status, response ) {
					if( status !== 200 || response === null ) {
						wn.alert( Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
						return;
					}
					Nino.admin.keys.init();
				} );
			}, undo );
		},

		/**
		 *	Run proceed() - after the shell has asked about unsaved input in this
		 *	tab, where the shell has the registry. A shell without it (an older
		 *	one, a test) goes straight on
		 *
		 *	@param		{Function}	proceed
		 *	@param		{Function}	[onCancel]
		 *
		 *	@return		void
		 */
		_guard : function( proceed, onCancel ) {

			if( typeof Nino.admin.dirty !== 'object' ) {
				proceed();
				return;
			}

			Nino.admin.dirty.guard( [ 'keys' ], proceed, onCancel );
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

			Nino.admin.keys._guard( function() {
				Nino.admin.keys._apiCall( 'rename', { key : key, newKey : newKey }, function( status, response ) {
					if( status !== 200 || response === null ) {
						wn.alert( Nino.adminUi.api.errorText( status, response, '/_admin/common/error/rename' ) );
						return;
					}
					Nino.admin.keys.init();
				} );
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

			Nino.admin.keys._guard( function() {
				Nino.admin.keys._apiCall( 'delete', { key : key }, function( status, response ) {
					if( status !== 200 || response === null ) {
						wn.alert( Nino.adminUi.api.errorText( status, response, '/_admin/common/error/delete' ) );
						return;
					}
					Nino.admin.keys.init();
				} );
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
		 *	_localeValues before switching locale (or before saving), and note
		 *	the translation as edited when a control differs from what it held
		 *	when it was drawn - merely visiting one must not mark it, and a
		 *	rich-text field reads back as the markup it built, which is not
		 *	always the string the server holds
		 *
		 *	@return		void
		 */
		_storeVisibleLocaleFields : function() {

			const group 	= Nino.admin.keys._currentGroup;
			const entries = ( Nino.admin.keys._groups[group] ?? [] ).filter( function( e ) { return e.global === false } );

			if( entries.length === 0 || Nino.admin.keys._selectedLocale === null )
				return;

			const locale = Nino.admin.keys._selectedLocale;
			const previous = Nino.admin.keys._localeValues[locale] ?? {};
			const baseline = Nino.admin.keys._baseline.locale;
			const values = {};
			entries.forEach( function( entry ) { values[entry.key] = Nino.admin.keys._readKeyValue( entry ) } );

			const changed = entries.some( function( entry ) {
				return String( baseline[entry.key] ?? previous[entry.key] ?? entry.values[locale] ?? '' ) !== String( values[entry.key] ?? '' );
			} );

			if( changed === true && Nino.admin.keys._dirtyLocales.indexOf( locale ) === -1 )
				Nino.admin.keys._dirtyLocales.push( locale );

			Nino.admin.keys._localeValues[locale] = values;
		},

		/**
		 *	Locales one Save click must persist, in the order they were edited.
		 *	When only global fields changed, the selected locale is a harmless
		 *	fallback that gives the save loop one request to carry them in.
		 *
		 *	@return		{Array<string>}
		 */
		_saveLocales : function() {
			const locales = Nino.admin.keys._dirtyLocales.slice();
			if( locales.length === 0 )
				locales.push( Nino.admin.keys._selectedLocale );
			return locales;
		},

		/**
		 *	Keep controls stable while sequential locale requests are running:
		 *	the fields, the locale switch, the buttons of every key (rename,
		 *	delete, the schema checkboxes - each of them reloads the module), the
		 *	back link. A locale switch in the middle would change which values a
		 *	later callback believes it just persisted
		 *
		 *	@param		{boolean}	pending
		 *
		 *	@return		void
		 */
		_setFormPending : function( pending ) {

			// '#keys-form', not '#keys-edit-form': the locale select and the back
			// link are appended to the toolbar beside the form
			const form = dc.getElementById('keys-form');
			if( form === null )
				return;

			form.querySelectorAll('input, textarea, select, button').forEach( function( el ) { el.disabled = pending } );
			form.querySelectorAll('a').forEach( function( el ) {
				// Links inside a rich-text editor are its content: getValue() returns them, so
				// writing attributes onto them would turn the field's value into a change
				if( el.closest('[contenteditable]') !== null )
					return;
				el.setAttribute( 'aria-disabled', pending ? 'true' : 'false' );
				el.style.pointerEvents = pending ? 'none' : '';
			} );
			form.querySelectorAll('[contenteditable]').forEach( function( el ) {
				el.contentEditable = pending ? 'false' : 'true';
				el.setAttribute( 'aria-disabled', pending ? 'true' : 'false' );
			} );
		},

		/**
		 *	Take what the controls on screen hold now as what is saved - the
		 *	translation after it was drawn, the global fields too when the whole
		 *	form was drawn or saved
		 *
		 *	@param		{boolean}	withGlobal
		 *
		 *	@return		void
		 */
		_captureBaseline : function( withGlobal ) {

			const entries = Nino.admin.keys._groups[Nino.admin.keys._currentGroup] ?? [];
			const read = function( global ) {
				const held = {};
				entries.filter( function( entry ) { return entry.global === global } ).forEach( function( entry ) {
					held[entry.key] = Nino.admin.keys._readKeyValue( entry );
				} );
				return held;
			};

			Nino.admin.keys._baseline.locale = read( false );

			if( withGlobal === true )
				Nino.admin.keys._baseline.global = read( true );
		},

		/**
		 *	Whether the tab holds input nobody has saved - what the shell asks
		 *	before it lets anything throw that away (see Nino.admin.dirty). For
		 *	a category: a translation that was edited and left, or a field that
		 *	differs from what it held when it was drawn or last saved. For the
		 *	new-key form and the scan form: anything typed or ticked
		 *
		 *	@return		{boolean}
		 */
		isDirty : function() {

			const form = dc.getElementById('keys-form');

			if( form === null || form.classList.contains('admin-hidden') === true )
				return false;

			if( Nino.admin.keys._view === 'new' ) {
				const value = function( id ) { const el = dc.getElementById( id ); return el === null ? '' : el.value };
				const isGlobal = dc.getElementById('keys-form-new-global');
				return value('keys-form-key') !== '' || value('keys-form-new-value') !== '' || ( isGlobal !== null && isGlobal.checked === true );
			}

			if( Nino.admin.keys._view === 'scan' )
				return Nino.admin.keys._scanRows.some( function( row ) { return ( row.valueInput !== null && row.valueInput.value !== '' ) || row.ignoreCheck.checked === true } );

			if( Nino.admin.keys._view !== 'group' || dc.getElementById('keys-edit-form') === null )
				return false;

			if( Nino.admin.keys._dirtyLocales.length > 0 )
				return true;

			return ( Nino.admin.keys._groups[Nino.admin.keys._currentGroup] ?? [] ).some( function( entry ) {
				const held = entry.global === true ? Nino.admin.keys._baseline.global : Nino.admin.keys._baseline.locale;
				return held[entry.key] !== undefined && Nino.admin.keys._readKeyValue( entry ) !== held[entry.key];
			} );
		},

		/**
		 *	Throw the input away. A category goes back to the stored values the
		 *	server sent and counts what its controls show as saved; the new-key
		 *	and scan forms are emptied. The form is about to be left or drawn again;
		 *	if the exit then fails without drawing it (a refused request), the
		 *	controls still show the discarded text and the next Save writes it
		 *
		 *	@return		void
		 */
		discard : function() {

			if( Nino.admin.keys._view === 'new' ) {
				[ 'keys-form-key', 'keys-form-new-value' ].forEach( function( id ) {
					const el = dc.getElementById( id );
					if( el !== null )
						el.value = '';
				} );
				const isGlobal = dc.getElementById('keys-form-new-global');
				if( isGlobal !== null )
					isGlobal.checked = false;
			} else if( Nino.admin.keys._view === 'scan' ) {
				Nino.admin.keys._scanRows.forEach( function( row ) {
					if( row.valueInput !== null )
						row.valueInput.value = '';
					row.ignoreCheck.checked = false;
				} );
			} else {
				Nino.admin.keys._dirtyLocales = [];
				Nino.admin.keys._localeValues = {};
				Nino.admin.keys._captureBaseline( true );
			}

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	Save what the open form holds, whichever form it is (see isDirty())
		 *
		 *	@param		{Function}	done				Called once with true or false
		 *
		 *	@return		void
		 */
		_saveOpen : function( done ) {

			if( Nino.admin.keys._view === 'new' )
				return Nino.admin.keys._saveNewKey( done );

			if( Nino.admin.keys._view === 'scan' )
				return Nino.admin.keys._saveScanResults( Nino.admin.keys._scanRows, done );

			Nino.admin.keys._save( done );
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

			Nino.admin.keys._captureBaseline( false );
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

			Nino.admin.keys._captureBaseline( true );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	Save every key of the current category: the global fields once, plus
		 *	every translation edited since the group was opened, one batched
		 *	request per translation, one after the other. Deliberately does not
		 *	navigate back to the list afterwards - only refreshes its preview.
		 *
		 *	A request that fails, or a key the server did not accept, stops the
		 *	loop: the translations not written yet stay edited, and the message
		 *	names what failed as key (locale). "Saved." is said once, after the
		 *	last translation. Every way this ends reports to done( ok ), if there
		 *	is one (see Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when everything was written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const report = function( ok ) {
				if( typeof Nino.admin.dirty === 'object' )
					Nino.admin.dirty.refresh();
				if( typeof done === 'function' )
					done( ok );
			};

			if( Nino.admin.keys._saving === true ) {
				report( false );
				return;
			}

			Nino.admin.keys._storeVisibleLocaleFields();

			const group 	= Nino.admin.keys._currentGroup;
			const entries = Nino.admin.keys._groups[group] ?? [];
			const msg 		= dc.getElementById('keys-form-msg');

			const globalItems = entries.filter( function( e ) { return e.global === true } ).map( function( entry ) {
				return { key : entry.key, locale : '*', value : Nino.admin.keys._readKeyValue( entry ) };
			} );
			const localeEntries = entries.filter( function( e ) { return e.global === false } );
			const locales = Nino.admin.keys._saveLocales();
			let position = 0;

			Nino.admin.keys._saving = true;
			Nino.admin.keys._setFormPending( true );
			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			// Ends the loop on a failure: the form is given back, the translations
			// not written yet are still edited, and the message says what failed
			function stop( text ) {
				Nino.admin.keys._saving = false;
				Nino.admin.keys._setFormPending( false );
				msg.textContent = text;
				report( false );
			}

			function saveNextLocale() {

				const locale = locales[position];
				const localeValues = Nino.admin.keys._localeValues[locale] ?? {};
				const localeItems = localeEntries.map( function( entry ) {
					return { key : entry.key, locale : locale, value : localeValues[entry.key] ?? entry.values[locale] ?? '' };
				} );

				// The global fields need one write: sent again for every translation
				// they would only repeat what the first request did
				const items = ( position === 0 ? globalItems : [] ).concat( localeItems );

				Nino.admin.keys._apiCall( 'savebatch', { items : items }, function( status, response ) {

					if( status !== 200 || response === null )
						return stop( Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' )+ ' ('+ locale+ ')' );

					const results = response.results ?? {};
					const failed 	= [];

					items.forEach( function( item ) {
						const result = results[item.key];
						if( result === undefined || result.ok !== true ) {
							failed.push( item.key+ ' ('+ item.locale+ ')' );
							return;
						}
						const entry = entries.find( function( e ) { return e.key === item.key } );
						if( entry === undefined )
							return;
						entry.values[item.locale] = result.value;
						if( item.locale !== '*' ) {
							Nino.admin.keys._localeValues[item.locale] = Nino.admin.keys._localeValues[item.locale] ?? {};
							Nino.admin.keys._localeValues[item.locale][item.key] = result.value;
						}
					} );

					if( failed.length > 0 )
						return stop( Nino.adminUi.format( Nino.content.getText('/_admin/keys/error/save-partial'), failed.join(', ') ) );

					const dirtyAt = Nino.admin.keys._dirtyLocales.indexOf( locale );
					if( dirtyAt !== -1 )
						Nino.admin.keys._dirtyLocales.splice( dirtyAt, 1 );

					position++;
					if( position < locales.length ) {
						saveNextLocale();
						return;
					}

					Nino.admin.keys._saving = false;
					Nino.admin.keys._setFormPending( false );
					msg.textContent = Nino.content.getText('/_admin/common/msg/saved');
					Nino.admin.keys._renderCategoryList();

					// What the controls hold is what is stored now
					Nino.admin.keys._captureBaseline( true );
					report( true );
				} );
			}

			saveNextLocale();
		},

		/**
		 *	Open the "create a new key" form
		 *
		 *	@return		void
		 */
		_openNewKeyForm : function() {
			Nino.admin.keys._isNew = true;
			Nino.admin.keys._view = 'new';
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
		 *	@param		{Function}	[done]				Called once with true when the key was created, false otherwise
		 *
		 *	@return		void
		 */
		_saveNewKey : function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const msg 		= dc.getElementById('keys-form-msg');
			const key 		= dc.getElementById('keys-form-key').value;
			const isGlobal = dc.getElementById('keys-form-new-global').checked;
			const value 	= dc.getElementById('keys-form-new-value').value;

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.keys._apiCall( 'create', { key : key, global : isGlobal, value : value }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}
				Nino.admin.keys.init();
				report( true );
			} );
		},

		/**
		 *	Open the "missing keys found in templates" scan results form -
		 *	see Keys::apiScan()
		 *
		 *	@return		void
		 */
		_openScanForm : function() {

			Nino.admin.keys._view = 'scan';
			Nino.admin.keys._scanRows = [];

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
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/scan' );
					return;
				}
				Nino.admin.keys._renderScanForm( response.missing, response.alsoUsed || [] );
			} );
		},

		/**
		 *	Render the scan results: one row per missing key (starting-value
		 *	input + "Ignore permanently" toggle) and a submit button that
		 *	hands the whole list to keys/scanapply.
		 *
		 *	A row says what can be done with its key (see Keys::_scanRow()): one
		 *	that follows the grammar has the input, and a /feature or /module
		 *	key a note on where it normally comes from; one that does not has no
		 *	input - it is renamed in the template - and can be ignored; a key of
		 *	the system has neither, and names who writes it. Below the rows, the
		 *	keys other templates read as well are listed, as a note and no more.
		 *
		 *	Each row has three possible answers, and doing nothing is one of
		 *	them: a value creates the key (one starting value, copied into
		 *	every language, to be translated later - see the Translations
		 *	tab's JSON export/import round-trip), an empty field is passed
		 *	over until the next scan, and "ignore" retires the key for good.
		 *	That is what makes a long list workable in several sittings
		 *	instead of one all-or-nothing pass.
		 *
		 *	@param		{Array}		missing				[ { key, files[], kind, writer, hint, owner }, ... ]
		 *	@param		{Array}		alsoUsed			[ { key, files[] }, ... ]
		 *
		 *	@return		void
		 */
		_renderScanForm : function( missing, alsoUsed ) {

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
				Nino.admin.keys._appendAlsoUsed( wrap, alsoUsed );
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

				const kind = item.kind || 'create';

				let valueInput = null;
				if( kind === 'create' ) {
					valueInput = dc.createElement('input');
					valueInput.type = 'text';
					valueInput.placeholder = Nino.content.getText('/_admin/keys/label/scan-value');
					field.appendChild( valueInput );
				}

				const note = Nino.admin.keys._scanNote( item );
				if( note !== '' ) {
					const noteEl = dc.createElement('p');
					noteEl.className = 'nino-admin-hint';
					noteEl.textContent = note;
					field.appendChild( noteEl );
				}

				// A key of the system is not the scan's to retire: it is the
				// system's to write, and ignoring it would only hide the gap
				if( kind === 'system' ) {
					form.appendChild( field );
					return;
				}

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

			Nino.admin.keys._appendAlsoUsed( wrap, alsoUsed );

			Nino.admin.keys._scanRows = rows;
		},

		/**
		 *	What the scan says about one missing key besides its name: why it
		 *	cannot be created here (the grammar, or the system writing it, and
		 *	who), or where a /feature or /module key normally comes from. Empty
		 *	for a key that is simply to be created
		 *
		 *	@param		{Object}		item					One row of the scan
		 *
		 *	@return		{string}
		 */
		_scanNote : function( item ) {

			if( item.kind === 'grammar' )
				return Nino.content.getText('/_admin/keys/scan/grammar');

			if( item.kind === 'system' )
				return Nino.content.getText('/_admin/keys/scan/system-'+ ( item.writer || 'none' ) );

			if( item.hint === 'feature' || item.hint === 'module' )
				return Nino.adminUi.format( Nino.content.getText('/_admin/keys/scan/hint-'+ item.hint ), item.owner );

			return '';
		},

		/**
		 *	The keys of one template that another reads as well, under the
		 *	scan: a note, with no input and nothing counted. Nothing is drawn
		 *	when there are none
		 *
		 *	@param		{Element}		wrap					The form's container
		 *	@param		{Array}			alsoUsed			[ { key, files[] }, ... ]
		 *
		 *	@return		void
		 */
		_appendAlsoUsed : function( wrap, alsoUsed ) {

			if( alsoUsed.length === 0 )
				return;

			const heading = dc.createElement('h3');
			heading.textContent = Nino.content.getText('/_admin/keys/scan/also-title');
			wrap.appendChild( heading );

			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.content.getText('/_admin/keys/scan/also-hint');
			wrap.appendChild( hint );

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list-dense';
			alsoUsed.forEach( function( item ) {
				const li = dc.createElement('li');
				li.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/keys/scan/also-line'), item.key, item.files.join(', ') );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );
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
		 *	@param		{Function}	[done]			Called once with true when the rows were applied, false otherwise
		 *
		 *	@return		void
		 */
		_saveScanResults : function( rows, done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const msg = dc.getElementById('keys-scan-msg');
			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			const payload = rows.map( function( row ) {
				return { key : row.key, value : row.valueInput === null ? '' : row.valueInput.value, ignore : row.ignoreCheck.checked };
			} );

			Nino.admin.keys._apiCall( 'scanapply', { rows : payload }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					report( false );
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
				report( true );
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.keys.init );

	// The shell asks before anything throws the tab's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'keys', {
			isDirty : Nino.admin.keys.isDirty,
			save		: Nino.admin.keys._saveOpen,
			discard : Nino.admin.keys.discard,
		} );

})(window, document, document.documentElement, document.body);
