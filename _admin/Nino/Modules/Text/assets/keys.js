/**
 *	Nino										A compact filesystembased php framework
 *	keys.js									"Text" module: find a text key - by its key, the names it
 *													is read by or what it says, hidden ones included - or
 *													browse the rows the keys fall into (the same ones as the
 *													Text panel, see textkeys.js) and bulk-edit every key of a
 *													row's value(s) - global fields always, per-locale fields
 *													behind a locale switcher. Unlike the Text panel, also
 *													shows blacklisted keys and lets each key's global/per-locale
 *													shape/blacklist status be changed or the key deleted
 *													entirely, all inline (the "set" half - see the Keys class
 *													docblock) - and a form to create or rename a key, which
 *													asks for it segment by segment: /<namespace>/<category>/
 *													<part>/<name>. Full CRUD, deliberately not just a copy of
 *													the Text panel's values-only editor - during active
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

		// What a segment of a key is made of: lower-case words of letters and
		// digits, joined by hyphens - the server decides it again (see
		// \Nino\Text::isGrammarKey())
		SEGMENT					: /^[a-z0-9]+(?:-[a-z0-9]+)*$/,

		// The namespaces a key can be created in. Only the first is offered at
		// first: the others belong to a template, a feature or a module, and
		// are unlocked on purpose
		NAMESPACES			: [ 'project', 'template', 'feature', 'module' ],

		_locales				: [],
		// What the server sent (see \Nino\Modules\Text\Keys::apiList()) and the
		// rows textkeys.js made of it: _groups is row id -> its keys. _categories
		// is what the key form offers to choose from
		_data						: null,
		_model					: null,
		_categories			: {},
		_groups					: {},
		// The row that is open - its id is the part of a key it stands for,
		// eg. 'template/page-home' - and the key that was asked for, if one was
		_currentGroup		: null,
		_focusKey				: null,
		// What the search box holds, whether it is cut down to the keys that are
		// hidden from the Text panel, and how many hits are drawn
		_search					: { query : '', hiddenOnly : false, shown : 0 },
		_selectedLocale	: null,
		_localeValues		: {},
		// The translations edited since the group was opened, in the order they
		// were edited - what one Save writes (see _saveLocales())
		_dirtyLocales		: [],
		// What the controls of the open group held when they were drawn or last
		// saved, key -> value: the global fields, and the translation on screen
		// (see Nino.admin.text, which keeps the same)
		_baseline				: { global : {}, locale : {} },
		// Which form the pane shows - 'group', 'new' (which is also the form that
		// renames) or 'scan' - and the rows of the scan form, for isDirty()
		_view						: null,
		_scanRows				: [],
		// The key the form of 'new' renames, null while it creates one; whether
		// the namespaces that normally belong to a template, a feature or a module
		// are unlocked; what the form held when it was drawn, for isDirty()
		_renameFrom			: null,
		_unlocked				: false,
		_formInitial		: '',
		_htmlEditors		: {},
		_fieldEls				: {},
		_isNew					: false,
		_fieldSeq				: 0,
		_saving					: false,
		_ready					: false,
		// The category to open again once the list has loaded - what applying a
		// key's format and limit comes back to (see _saveSettings())
		_reopen					: null,
		// What the last scan pass did, shown once above the category list -
		// the form it happened in is gone by then (see _saveScanResults())
		_scanSummary		: '',

		/**
		 *	Load every known text key, sort them into rows and render the list -
		 *	or, on the first load, open the row or the key the hash names
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('keys-list') === null )
				return;

			Nino.admin.keys._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.keys._showError( dc.getElementById('keys-list'), status, response );

				// Before the first _show*() below, which writes the address
				const hash = Nino.admin.keys._ready === false ? Nino.admin.router.current() : { panel : '', parts : [] };

				Nino.admin.keys._locales = response.locales;
				Nino.admin.keys._categories = response.categories ?? {};

				if( typeof Nino.admin.sessionLocale === 'object' )
					Nino.admin.sessionLocale.init( response.selectedLocale );

				Nino.admin.keys._data = response;
				Nino.admin.keys._buildModel();
				Nino.admin.keys._renderCategoryList();
				Nino.admin.keys._showList();
				Nino.admin.keys._ready 	= true;

				const reopen = Nino.admin.keys._reopen;
				Nino.admin.keys._reopen = null;
				const target = hash.panel === 'keys' && hash.parts.length > 0 ? Nino.admin.keys._resolve( hash.parts ) : null;

				if( reopen !== null && Nino.admin.keys._groups[reopen] !== undefined )
					Nino.admin.keys._openGroup( reopen );
				else if( target !== null )
					Nino.admin.keys._openGroup( target.row, target.key );
			} );
		},

		/**
		 *	The language the tab shows: the one of the session, which is the one
		 *	the interface is in too - the first language where the session has
		 *	none yet
		 *
		 *	@return		{string}
		 */
		_locale : function() {
			return ( typeof Nino.admin.sessionLocale === 'object' ? Nino.admin.sessionLocale.current : null ) ?? Nino.admin.keys._locales[0] ?? '';
		},

		/**
		 *	Sort the keys the server sent into rows (see textkeys.js), the keys
		 *	of the workbench among them
		 *
		 *	@return		void
		 */
		_buildModel : function() {

			Nino.admin.keys._model = Nino.admin.textKeys.build( { entries : Nino.admin.keys._data.keys, admin : true, locale : Nino.admin.keys._locale() } );
			Nino.admin.keys._groups = Object.create( null );

			Object.keys( Nino.admin.keys._model.rows ).forEach( function( id ) {
				Nino.admin.keys._groups[id] = Nino.admin.keys._model.rows[id].entries;
			} );
		},

		/**
		 *	What a hash behind #keys names: a row, or a key and its row
		 *
		 *	@param		{Array}		parts					The hash behind the tab's name
		 *
		 *	@return		{Object|null}						{ row, key }, see Nino.admin.textKeys.resolve()
		 */
		_resolve : function( parts ) {
			return Nino.admin.keys._model === null ? null : Nino.admin.textKeys.resolve( Nino.admin.keys._model, parts );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on - or, where the
		 *	hash names this tab, the level it names: a step through the browser's
		 *	history changes the address and nothing else. Leaving a form that holds
		 *	unsaved input asks first, as its back link does
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.keys._ready === false )
				return;

			const hash = Nino.admin.router.current();
			if( hash.panel === 'keys' && Nino.admin.keys._follow( hash.parts ) === true )
				return;

			Nino.admin.keys._showLevel();
		},

		_showLevel : function() {

			if( dc.getElementById('keys-form').classList.contains('admin-hidden') === false )
				return Nino.admin.keys._showForm();

			Nino.admin.keys._showList();
		},

		/**
		 *	Move to the level the hash names, if it is not the one on screen. A
		 *	row, or a key - which is in one - opens it; a hash that names nothing
		 *	there is, is the list. Only a row is followed to and from: the forms
		 *	that create, rename or scan are not in the address
		 *
		 *	@param		{Array}		parts					The hash behind the tab's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const open = dc.getElementById('keys-form').classList.contains('admin-hidden') === false;
			const target = parts.length > 0 ? Nino.admin.keys._resolve( parts ) : null;

			// A form that is not a row's is not in the address: the address says the list
			if( target === null ? ( open === false || Nino.admin.keys._view !== 'group' ) : ( open === true && Nino.admin.keys._view === 'group' && Nino.admin.keys._currentGroup === target.row ) ) {

				// Another key of the row on screen is no move, but the field the
				// address names is the one shown and the one it keeps
				if( target !== null && target.key !== Nino.admin.keys._focusKey ) {
					Nino.admin.keys._focusKey = target.key;

					if( target.key !== null )
						Nino.admin.keys._focusField( target.key );
				}

				return false;
			}

			Nino.admin.router.leave( [ 'keys' ], open, function() {
				if( target === null ) {
					Nino.admin.keys._destroyHtmlEditors();
					Nino.admin.keys._showList();
					return;
				}
				Nino.admin.keys._openGroup( target.row, target.key );
			}, Nino.admin.keys._showLevel );

			return true;
		},

		/**
		 *	Call a keys/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "keys/list")
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
			Nino.admin.router.set( 'keys', [] );
		},

		_showForm : function() {
			dc.getElementById('keys-list').classList.add('admin-hidden');
			dc.getElementById('keys-form').classList.remove('admin-hidden');
			Nino.admin.router.set( 'keys', Nino.admin.keys._view === 'group' ? Nino.admin.textKeys.hashParts( Nino.admin.keys._currentGroup, Nino.admin.keys._focusKey ) : [] );
		},

		/**
		 *	The search of the list - a box and the filter for the keys hidden from
		 *	the Text panel - and the line that says how many hits there are
		 *
		 *	@return		{Element}
		 */
		_renderSearchBar : function() {

			const search = Nino.admin.textKeys.searchBar( {
				query : Nino.admin.keys._search.query,
				onQuery : function( query ) {
					Nino.admin.keys._search.query = query;
					Nino.admin.keys._search.shown = 0;
					Nino.admin.keys._renderBody();
				},
				chips : [ { id : 'hidden', onToggle : function() {
					Nino.admin.keys._search.hiddenOnly = Nino.admin.keys._search.hiddenOnly === false;
					Nino.admin.keys._search.shown = 0;
					search.chips.hidden.setAttribute( 'aria-pressed', Nino.admin.keys._search.hiddenOnly === true ? 'true' : 'false' );
					Nino.admin.keys._renderBody();
				} } ],
			} );

			search.input.id = 'keys-search';
			search.chips.hidden.id = 'keys-list-hidden';
			search.chips.hidden.textContent = Nino.content.getText('/_admin/keys/label/hidden-only');
			search.chips.hidden.setAttribute( 'aria-pressed', Nino.admin.keys._search.hiddenOnly === true ? 'true' : 'false' );

			return search.bar;
		},

		/**
		 *	One hit: it opens the row at the key, and only its language code
		 *	switches the language
		 *
		 *	@param		{Object}	hit						See Nino.admin.textKeys.search()
		 *	@param		{string}	locale				The language on screen
		 *
		 *	@return		{Element}
		 */
		_renderHit : function( hit, locale ) {
			return Nino.admin.textKeys.hitElement( hit, locale, function() {
				Nino.admin.router.go( 'keys', Nino.admin.textKeys.hashParts( hit.item.row.id, hit.item.field.entry.key ) );
				Nino.admin.keys._openGroup( hit.item.row.id, hit.item.field.entry.key );
			}, Nino.admin.keys._setLocale );
		},

		/**
		 *	Change the language the tab shows. It is the session's language, which
		 *	the Text panel and the next form open in too
		 *
		 *	@param		{string}	locale
		 *
		 *	@return		void
		 */
		_setLocale : function( locale ) {

			if( typeof Nino.admin.sessionLocale === 'object' )
				Nino.admin.sessionLocale.set( locale );

			Nino.admin.keys._search.shown = 0;
			Nino.admin.keys._buildModel();
			Nino.admin.keys._renderBody();
		},

		/**
		 *	The hits of the search, 50 at a time: a line for each key (see
		 *	Nino.admin.textKeys.hitElement()). A hit opens the row at the key; only
		 *	the code of a language switches the language
		 *
		 *	@param		{Element}	body
		 *
		 *	@return		void
		 */
		_renderHits : function( body ) {

			const search 	= Nino.admin.keys._search;
			const locale 	= Nino.admin.keys._locale();
			const hits 		= Nino.admin.textKeys.search( Nino.admin.keys._model, { query : search.query, locale : locale, onlyHidden : search.hiddenOnly } );
			const status 	= dc.getElementById('keys-list-status');
			const step 		= Nino.adminUi.ELEMENTLIST_RESULTS;

			if( search.shown === 0 )
				search.shown = step;

			status.textContent = Nino.adminUi.format( Nino.content.getText( hits.length === 1 ? '/_admin/text/msg/result' : '/_admin/text/msg/results' ), hits.length );

			if( hits.length === 0 ) {
				body.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/text/msg/nothing') ) );
				return;
			}

			const list = dc.createElement('div');
			list.className = 'nino-admin-list admin-text-hits';

			hits.slice( 0, search.shown ).forEach( function( hit ) {
				list.appendChild( Nino.admin.keys._renderHit( hit, locale ) );
			} );

			body.appendChild( list );

			if( hits.length > search.shown ) {
				const more = dc.createElement('button');
				more.type = 'button';
				more.className = 'nino-admin-btn-secondary';
				more.textContent = Nino.content.getText('/_admin/text/label/more');
				more.addEventListener( 'click', function() {
					search.shown += step;
					Nino.admin.keys._renderBody();
				} );
				body.appendChild( more );
			}
		},

		/**
		 *	Draw what is below the search: the hits where something is searched
		 *	for, the rows otherwise
		 *
		 *	@return		void
		 */
		_renderBody : function() {

			const body = dc.getElementById('keys-list-body');

			if( body === null )
				return;

			body.innerHTML = '';

			if( Nino.admin.keys._search.query.trim() === '' && Nino.admin.keys._search.hiddenOnly === false ) {
				dc.getElementById('keys-list-status').textContent = '';
				Nino.admin.keys._fillBody( body );
				return;
			}

			Nino.admin.keys._renderHits( body );
		},

		/**
		 *	Render the scan action, the search, the rows and "add new key" action
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

			wrap.appendChild( Nino.admin.keys._renderSearchBar() );

			const status = dc.createElement('p');
			status.id = 'keys-list-status';
			status.className = 'nino-admin-hint';
			status.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( status );

			const body = dc.createElement('section');
			body.id = 'keys-list-body';
			wrap.appendChild( body );

			Nino.admin.keys._renderBody();

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
		 *	The rows, in the blocks and groups textkeys.js made of them: each a
		 *	link that opens the row's form
		 *
		 *	@param		{Element}	body
		 *
		 *	@return		void
		 */
		_fillBody : function( body ) {

			const model 	= Nino.admin.keys._model;
			const locale 	= Nino.admin.keys._locale();

			if( Object.keys( model.rows ).length === 0 ) {
				body.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/keys/empty') ) );
				return;
			}

			model.blocks.forEach( function( block ) {

				const part = dc.createElement('section');
				part.className = 'admin-text-block';

				const heading = dc.createElement('h2');
				heading.textContent = Nino.content.getText( '/_admin/text/group/'+ block.id );
				part.appendChild( heading );

				( block.rows !== undefined ? [ { id : block.id, rows : block.rows } ] : block.groups ).forEach( function( group ) {

					if( block.rows === undefined && group.id !== 'common' ) {
						const title = dc.createElement('h3');
						title.textContent = Nino.content.getText( '/_admin/text/group/'+ group.id );
						part.appendChild( title );
					}

					const ul = dc.createElement('ul');
					ul.className = 'nino-admin-list';

					group.rows.forEach( function( id ) {

						const row = model.rows[id];

						const li = dc.createElement('li');
						const link = dc.createElement('a');
						link.href = '#';
						link.dataset.group = id;

						const copy = dc.createElement('span');
						copy.className = 'nino-admin-list-copy';
						const title = dc.createElement('strong');
						title.textContent = Nino.admin.textKeys.rowLabel( model, row, locale );

						const descr = dc.createElement('small');
						descr.textContent = Nino.admin.textKeys.rowSummary( row, locale );
						copy.appendChild( title );
						copy.appendChild( descr );
						link.appendChild( copy );

						link.addEventListener( 'click', function( ev ) {
							ev.preventDefault();
							Nino.admin.router.go( 'keys', Nino.admin.textKeys.hashParts( id ) );
							Nino.admin.keys._openGroup( id );
						} );
						li.appendChild( link );
						ul.appendChild( li );
					} );

					part.appendChild( ul );
				} );

				body.appendChild( part );
			} );
		},

		/**
		 *	Open a row's bulk-edit form, at one of its keys where one was asked
		 *	for: that field is scrolled to and marked
		 *
		 *	@param		{string}		group					The row's id
		 *	@param		{string|null}	[key]
		 *
		 *	@return		void
		 */
		_openGroup : function( group, key ) {

			Nino.admin.keys._destroyHtmlEditors();

			Nino.admin.keys._isNew 					= false;
			Nino.admin.keys._currentGroup 	= group;
			Nino.admin.keys._focusKey 			= key ?? null;
			Nino.admin.keys._selectedLocale = Nino.admin.keys._locale();
			Nino.admin.keys._localeValues 	= {};
			Nino.admin.keys._dirtyLocales 	= [];
			Nino.admin.keys._baseline 			= { global : {}, locale : {} };
			Nino.admin.keys._view 					= 'group';
			Nino.admin.keys._fieldEls 			= {};

			Nino.admin.keys._renderGroupForm();
			Nino.admin.keys._showForm();

			if( Nino.admin.keys._focusKey !== null )
				Nino.admin.keys._focusField( Nino.admin.keys._focusKey );
		},

		/**
		 *	Scroll to the field of a key, mark it and put the cursor in its text
		 *
		 *	@param		{string}	key
		 *
		 *	@return		void
		 */
		_focusField : function( key ) {

			const field = Array.from( dc.getElementById('keys-form').querySelectorAll('[data-key]') ).find( function( el ) { return el.dataset.key === key } );

			if( field === undefined )
				return;

			field.classList.add('is-found');

			if( typeof field.scrollIntoView === 'function' )
				field.scrollIntoView( { block : 'center' } );

			const control = field.querySelector('textarea, [contenteditable="true"]');

			if( control !== null && typeof control.focus === 'function' )
				control.focus( { preventScroll : true } );
		},

		/**
		 *	Render one key as a labeled value field (nino-admin-richtext or
		 *	textarea+counter), plus its global/ausgeblendet schema toggles.
		 *
		 *	The key is shown in a field that is read, not typed into - renaming is
		 *	the form of a key's own (see _openNewKeyForm()), which asks for the four
		 *	segments - with the name the key is read by under it: "Eintrag 1 › Titel"
		 *
		 *	@param		{Object}	entry					Key entry
		 *	@param		{*}				value					Current value
		 *	@param		{string}	[named]				The name the key is read by, see Nino.admin.textKeys.sections()
		 *
		 *	@return		{Element}								<div> wrapping the field + its schema toggles
		 */
		_renderKeyField : function( entry, value, named ) {

			const wrap = dc.createElement('div');
			// .nino-admin-field-wide marks the three-part (header / value / schema)
			// shape the Text panel's assets/admin.css folds into two rows from 48rem up - the
			// plain .nino-admin-field label/input pairs elsewhere in this module
			// must not be caught by that grid
			wrap.className = 'nino-admin-field nino-admin-field-wide admin-text-field';
			wrap.dataset.key = entry.key;

			const header = dc.createElement('div');
			header.className = 'nino-admin-field-header';

			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.readOnly = true;
			keyInput.className = 'admin-text-key-input';
			keyInput.value = entry.key;
			keyInput.setAttribute( 'aria-label', Nino.content.getText('/_admin/keys/label/key') );
			header.appendChild( keyInput );

			// The names of the system and of the workbench are what the code that
			// reads them asks for: their value is edited, their key is not renamed
			if( entry.key.indexOf('/_nino/') !== 0 && entry.key.indexOf('/_admin/') !== 0 ) {

				const renameBtn = dc.createElement('button');
				renameBtn.type = 'button';
				renameBtn.className = 'admin-text-key-btn';
				renameBtn.textContent = Nino.content.getText('/_admin/common/label/rename');
				renameBtn.addEventListener( 'click', function() { Nino.admin.keys._openNewKeyForm( entry.key ) } );
				header.appendChild( renameBtn );
			}

			const deleteBtn = dc.createElement('button');
			deleteBtn.type = 'button';
			deleteBtn.className = 'admin-text-key-btn nino-admin-btn-danger';
			deleteBtn.textContent = Nino.content.getText('/_admin/common/label/delete');
			deleteBtn.addEventListener( 'click', function() { Nino.admin.keys._deleteKey( entry.key ) } );
			header.appendChild( deleteBtn );

			wrap.appendChild( header );

			// One child for the value area of the grid, the name above the control
			const valueWrap = dc.createElement('div');
			valueWrap.className = 'admin-text-value';

			if( named !== undefined ) {
				const subtitle = dc.createElement('small');
				subtitle.className = 'admin-text-subtitle';
				subtitle.id = 'keys-name-'+ ( ++Nino.admin.keys._fieldSeq );
				subtitle.textContent = named;
				keyInput.setAttribute( 'aria-describedby', subtitle.id );
				valueWrap.appendChild( subtitle );
			}

			wrap.appendChild( valueWrap );

			if( entry.html === true ) {
				const mount = dc.createElement('div');
				valueWrap.appendChild( mount );
				Nino.admin.keys._htmlEditors[entry.key] = Nino.admin.htmlEditor.create( mount, value ?? '', entry.maxlength, 0, entry.format );
			} else {
				const textarea = dc.createElement('textarea');
				textarea.maxLength = entry.maxlength;
				textarea.value = value ?? '';
				textarea.setAttribute( 'aria-label', named ?? entry.key );
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
				valueWrap.appendChild( textarea );
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

			// The form that creates or renames: dirty once a segment is not what it was
			// drawn with - /project and nothing, or the key's own - or anything is typed
			// into the starting value or ticked
			if( Nino.admin.keys._view === 'new' ) {
				const value = function( id ) { const el = dc.getElementById( id ); return el === null ? '' : el.value };
				const isGlobal = dc.getElementById('keys-form-new-global');
				return JSON.stringify( Nino.admin.keys._readSegments() ) !== Nino.admin.keys._formInitial || value('keys-form-new-value') !== '' || ( isGlobal !== null && isGlobal.checked === true );
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
		 *	controls still show the discarded text and the next Save writes it.
		 *	While a save runs nothing is thrown away: it finishes with what it
		 *	was given
		 *
		 *	@return		void
		 */
		discard : function() {

			// The running save sends the translations still edited, from the
			// values kept for them - see the Elements panel's discard()
			if( Nino.admin.keys._saving === true )
				return;

			if( Nino.admin.keys._view === 'new' ) {
				// Drawn again as it was opened
				Nino.admin.keys._renderNewKeyForm();
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
		 *	The keys of a row with the name each is read by, in the order a template
		 *	reads them (see Nino.admin.textKeys.sections()): "Eintrag 1 › Titel"
		 *
		 *	@param		{string}	group					The row's id
		 *
		 *	@return		{Array}										[ { entry, named } ]
		 */
		_orderedFields : function( group ) {

			const model = Nino.admin.keys._model;
			const row = model === null ? undefined : model.rows[group];

			if( row === undefined )
				return ( Nino.admin.keys._groups[group] ?? [] ).map( function( entry ) { return { entry : entry, named : undefined } } );

			return Nino.admin.textKeys.sections( model, row ).flatMap( function( section ) {
				return section.fields.map( function( field ) {
					return { entry : field.entry, named : [ section.label, field.label ].filter( function( part, at, all ) { return at === 0 || part !== all[at - 1] } ).join(' › ') };
				} );
			} );
		},

		/**
		 *	Re-render the locale-scoped fields for the currently selected locale
		 *
		 *	@return		void
		 */
		_renderLocaleFields : function() {

			const group 	= Nino.admin.keys._currentGroup;
			const fields 	= Nino.admin.keys._orderedFields( group ).filter( function( f ) { return f.entry.global === false } );

			fields.forEach( function( field ) {
				if( Nino.admin.keys._htmlEditors[field.entry.key] !== undefined ) {
					Nino.admin.keys._htmlEditors[field.entry.key].destroy();
					delete Nino.admin.keys._htmlEditors[field.entry.key];
				}
			} );

			const wrap = dc.getElementById('keys-form-locale-fields');
			wrap.innerHTML = '';

			const stored = Nino.admin.keys._localeValues[Nino.admin.keys._selectedLocale] ?? {};

			fields.forEach( function( field ) {
				const entry = field.entry;
				const value = ( stored[entry.key] !== undefined ) ? stored[entry.key] : ( entry.values[Nino.admin.keys._selectedLocale] ?? '' );
				wrap.appendChild( Nino.admin.keys._renderKeyField( entry, value, field.named ) );
			} );

			Nino.admin.keys._captureBaseline( false );
		},

		/**
		 *	Render a row's bulk-edit form: global fields, then a
		 *	locale select + locale-scoped fields
		 *
		 *	@return		void
		 */
		_renderGroupForm : function() {

			const group 	= Nino.admin.keys._currentGroup;
			const fields 	= Nino.admin.keys._orderedFields( group );

			const globalFields = fields.filter( function( f ) { return f.entry.global === true } );
			const localeEntries = fields.filter( function( f ) { return f.entry.global === false } );

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.router.go( 'keys', [] ); Nino.admin.keys._destroyHtmlEditors(); Nino.admin.keys._showList() } );

			// A row can carry hundreds of keys - the back link and the
			// locale switch ride along in one pinned row instead of scrolling
			// out of reach at the top of it (see script.js's formToolbar())
			const toolbar = Nino.admin.formToolbar( backLink );
			wrap.appendChild( toolbar );

			const form = dc.createElement('form');
			form.id = 'keys-edit-form';

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = Nino.admin.keys._model !== null && Nino.admin.keys._model.rows[group] !== undefined ? Nino.admin.textKeys.rowLabel( Nino.admin.keys._model, Nino.admin.keys._model.rows[group], Nino.admin.keys._selectedLocale ) : group;
			wrap.appendChild( title );

			if( globalFields.length > 0 ) {

				const globalWrap = dc.createElement('fieldset');
				globalWrap.id = 'keys-form-global';
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_admin/common/label/global');
				globalWrap.appendChild( legend );

				globalFields.forEach( function( field ) {
					globalWrap.appendChild( Nino.admin.keys._renderKeyField( field.entry, field.entry.values['*'] ?? '', field.named ) );
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
		 *	Open the form that creates a key - or, given a key, renames it. Both
		 *	ask for the four segments of the key, each in a field of its own,
		 *	/<namespace>/<category>/<part>/<name>; a rename is that form filled in
		 *	with the key's segments. Leaving the row that held the key asks about
		 *	unsaved input first
		 *
		 *	@param		{string}	[fromKey]			The key to rename
		 *
		 *	@return		void
		 */
		_openNewKeyForm : function( fromKey ) {

			const open = function() {
				Nino.admin.keys._isNew = typeof fromKey !== 'string';
				Nino.admin.keys._renameFrom = typeof fromKey === 'string' ? fromKey : null;
				Nino.admin.keys._view = 'new';
				Nino.admin.keys._renderNewKeyForm();
				Nino.admin.keys._showForm();
			};

			if( typeof fromKey === 'string' )
				Nino.admin.keys._guard( open );
			else
				open();
		},

		/**
		 *	The four segments a key is filled into the form as: its own if it
		 *	follows the grammar, otherwise /project and nothing - a key somebody
		 *	invented has to be named again
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Object}									{ namespace, category, part, name }
		 */
		_segmentsOf : function( key ) {

			const described = Nino.adminUi.describeKey( key );

			return described.kind === 'grammar'
				? { namespace : described.namespace, category : described.category, part : described.part, name : described.name }
				: { namespace : 'project', category : '', part : '', name : '' };
		},

		/**
		 *	What the four fields of the form hold now
		 *
		 *	@return		{Object}									{ namespace, category, part, name }
		 */
		_readSegments : function() {

			const value = function( id ) { const el = dc.getElementById( id ); return el === null ? '' : String( el.value ) };

			return { namespace : value('keys-form-namespace'), category : value('keys-form-category'), part : value('keys-form-part'), name : value('keys-form-name') };
		},

		/**
		 *	The key the segments make
		 *
		 *	@param		{Object}	segments				{ namespace, category, part, name }
		 *
		 *	@return		{string}
		 */
		_composeKey : function( segments ) {
			return '/'+ [ segments.namespace, segments.category, segments.part, segments.name ].join('/');
		},

		/**
		 *	Render the form that creates a key or renames one: a field for each
		 *	segment, checked as it is typed; under them the key they make and the
		 *	name it will be read by. The namespace is a choice that offers /project
		 *	to begin with - the others, which belong to a template, a feature or a
		 *	module, after "Unlock", or at once for a key that is in one; the
		 *	category is a choice from what there is where it is a template, a
		 *	feature or a module, and a field with suggestions for /project. The
		 *	button stays off until all four are words of a key
		 *
		 *	@return		void
		 */
		_renderNewKeyForm : function() {

			const keys 		= Nino.admin.keys;
			const from 		= keys._renameFrom;
			const initial = from === null ? { namespace : 'project', category : '', part : '', name : '' } : keys._segmentsOf( from );

			keys._unlocked = initial.namespace !== 'project';
			keys._formInitial = JSON.stringify( initial );

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); keys._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = Nino.content.getText( from === null ? '/_admin/keys/label/new' : '/_admin/keys/label/rename-title' );
			wrap.appendChild( title );

			const form = dc.createElement('form');

			// A field of the form: a label over a control, and under it what is wrong
			const field = function( labelKey, control ) {
				const label = dc.createElement('label');
				label.className = 'nino-admin-field';
				const span = dc.createElement('span');
				span.textContent = Nino.content.getText( labelKey );
				label.appendChild( span );
				label.appendChild( control );
				return label;
			};

			const namespace = dc.createElement('select');
			namespace.id = 'keys-form-namespace';

			const fillNamespaces = function( chosen ) {
				namespace.innerHTML = '';
				keys.NAMESPACES.filter( function( name ) { return name === 'project' || keys._unlocked === true } ).forEach( function( name ) {
					const option = dc.createElement('option');
					option.value = name;
					option.textContent = '/'+ name;
					option.selected = name === chosen;
					namespace.appendChild( option );
				} );
			};

			fillNamespaces( initial.namespace );
			form.appendChild( field( '/_admin/keys/label/namespace', namespace ) );

			const unlock = dc.createElement('button');
			unlock.type = 'button';
			unlock.className = 'nino-admin-btn-secondary';
			unlock.textContent = Nino.content.getText('/_admin/keys/label/unlock');
			unlock.hidden = keys._unlocked === true;
			unlock.addEventListener( 'click', function() {
				keys._unlocked = true;
				unlock.hidden = true;
				fillNamespaces( namespace.value );
				namespace.focus();
			} );
			form.appendChild( unlock );

			// Said before the unlock as well as after it: it is why there is one
			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.content.getText('/_admin/keys/hint/unlock');
			form.appendChild( hint );

			// The category: a choice where there is a list to choose from, a field
			// with suggestions where the project may name its own
			const categoryField = dc.createElement('label');
			categoryField.className = 'nino-admin-field';
			const categorySpan = dc.createElement('span');
			categorySpan.textContent = Nino.content.getText('/_admin/keys/label/category');
			categoryField.appendChild( categorySpan );
			form.appendChild( categoryField );

			const categoryError = dc.createElement('p');
			categoryError.className = 'nino-admin-field-error';
			categoryError.id = 'keys-form-category-error';
			categoryError.textContent = Nino.content.getText('/_admin/keys/error/segment');
			categoryError.hidden = true;
			form.appendChild( categoryError );

			let category = null;

			const fillCategory = function( chosen ) {

				if( category !== null )
					categoryField.removeChild( category );
				const old = dc.getElementById('keys-form-categories');
				if( old !== null )
					old.remove();

				const offered = ( keys._categories[namespace.value] ?? [] ).slice();

				if( namespace.value === 'project' ) {
					category = dc.createElement('input');
					category.type = 'text';
					category.setAttribute( 'list', 'keys-form-categories' );
					category.autocomplete = 'off';
					category.value = chosen;
					const list = dc.createElement('datalist');
					list.id = 'keys-form-categories';
					offered.forEach( function( name ) {
						const option = dc.createElement('option');
						option.value = name;
						list.appendChild( option );
					} );
					form.appendChild( list );
				} else {
					category = dc.createElement('select');
					// A category the key has that is not on offer any more (a feature
					// that was taken out) stays choosable, so a rename can keep it
					if( chosen !== '' && offered.indexOf( chosen ) === -1 )
						offered.unshift( chosen );
					offered.forEach( function( name ) {
						const option = dc.createElement('option');
						option.value = name;
						option.textContent = name;
						option.selected = name === chosen;
						category.appendChild( option );
					} );
				}

				category.id = 'keys-form-category';
				category.required = true;
				category.addEventListener( 'input', refresh );
				category.addEventListener( 'change', refresh );
				categoryField.appendChild( category );
			};

			const segment = function( id, labelKey, value ) {
				const input = dc.createElement('input');
				input.type = 'text';
				input.id = id;
				input.required = true;
				input.autocomplete = 'off';
				input.value = value;
				input.addEventListener( 'input', refresh );
				form.appendChild( field( labelKey, input ) );
				const error = dc.createElement('p');
				error.className = 'nino-admin-field-error';
				error.id = id+ '-error';
				error.textContent = Nino.content.getText('/_admin/keys/error/segment');
				error.hidden = true;
				form.appendChild( error );
			};

			segment( 'keys-form-part', '/_admin/keys/label/part', initial.part );
			segment( 'keys-form-name', '/_admin/keys/label/name', initial.name );

			const segmentHint = dc.createElement('p');
			segmentHint.className = 'nino-admin-hint';
			segmentHint.textContent = Nino.content.getText('/_admin/keys/hint/segment');
			form.appendChild( segmentHint );

			// The key the four make and the name it is read by, said as they are typed
			const composed = dc.createElement('p');
			const composedLabel = dc.createElement('span');
			composedLabel.textContent = Nino.content.getText('/_admin/keys/label/key')+ ' ';
			const composedKey = dc.createElement('code');
			composedKey.id = 'keys-form-preview';
			composed.setAttribute( 'aria-live', 'polite' );
			composed.appendChild( composedLabel );
			composed.appendChild( composedKey );
			form.appendChild( composed );

			const shown = dc.createElement('p');
			shown.className = 'nino-admin-hint';
			shown.id = 'keys-form-shown';
			form.appendChild( shown );

			if( from === null ) {

				const globalLabel = dc.createElement('label');
				const globalCheck = dc.createElement('input');
				globalCheck.type = 'checkbox';
				globalCheck.id = 'keys-form-new-global';
				globalLabel.appendChild( globalCheck );
				globalLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/keys/label/global-hint') ) );
				form.appendChild( globalLabel );

				const valueInput = dc.createElement('input');
				valueInput.type = 'text';
				valueInput.id = 'keys-form-new-value';
				form.appendChild( field( '/_admin/keys/label/initial', valueInput ) );
			}

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText( from === null ? '/_admin/common/label/create' : '/_admin/common/label/rename' );
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'keys-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			// What the fields say now: which of them is not a word of a key, the key
			// and its name, and whether the button may be pressed
			function refresh() {

				const segments = keys._readSegments();
				let valid = true;

				[ [ 'keys-form-category', segments.category ], [ 'keys-form-part', segments.part ], [ 'keys-form-name', segments.name ] ].forEach( function( pair ) {

					const control = dc.getElementById( pair[0] );
					const error = dc.getElementById( pair[0]+ '-error' );
					const wrong = pair[1] !== '' && keys.SEGMENT.test( pair[1] ) === false;

					valid = valid && pair[1] !== '' && wrong === false;
					control.setAttribute( 'aria-invalid', wrong ? 'true' : 'false' );

					if( wrong )
						control.setAttribute( 'aria-describedby', error.id );
					else
						control.removeAttribute( 'aria-describedby' );

					error.hidden = wrong === false;
				} );

				composedKey.textContent = keys._composeKey( segments );
				shown.textContent = valid ? Nino.content.getText('/_admin/keys/label/shown-as')+ ' '+ Nino.adminUi.slugLabel( segments.part )+ ' › '+ Nino.adminUi.slugLabel( segments.name ) : '';
				saveBtn.disabled = valid === false || ( from !== null && keys._composeKey( segments ) === from );
			}

			namespace.addEventListener( 'change', function() { fillCategory( '' ); refresh() } );
			fillCategory( initial.category );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); keys._saveNewKey() } );

			wrap.appendChild( form );

			refresh();
		},

		/**
		 *	Create the key the form holds - or rename the key it was opened for
		 *	to it. The button is off until the four segments are words of a key;
		 *	the server decides it again
		 *
		 *	@param		{Function}	[done]				Called once with true when the key was created or renamed, false otherwise
		 *
		 *	@return		void
		 */
		_saveNewKey : function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const keys 		= Nino.admin.keys;
			const msg 		= dc.getElementById('keys-form-msg');
			const key 		= keys._composeKey( keys._readSegments() );
			const from 		= keys._renameFrom;

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			const answered = function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, from === null ? '/_admin/common/error/save' : '/_admin/common/error/rename' );
					report( false );
					return;
				}
				keys.init();
				report( true );
			};

			if( from !== null ) {
				keys._apiCall( 'rename', { key : from, newKey : key }, answered );
				return;
			}

			keys._apiCall( 'create', { key : key, global : dc.getElementById('keys-form-new-global').checked, value : dc.getElementById('keys-form-new-value').value }, answered );
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
