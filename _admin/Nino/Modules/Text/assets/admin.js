/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js									Admin "Text" panel: find a text - by what it says, what it
 *													is called or its key - or browse the rows the keys fall
 *													into (pages first, then the project, the common words,
 *													building blocks, modules, features), then edit every key
 *													of a row at once: a section for each part, a locale
 *													switch at the top, the fields that are the same in every
 *													language marked. How keys become rows, sections and
 *													names is textkeys.js. The set of keys is developer-owned
 *													(see /text/blacklist.php) - this only ever edits existing
 *													key values, no create/delete.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.text = {

		_locales				: [],
		// What the server sent (see \Nino\Modules\Text\Admin::apiKeys()) and the
		// rows textkeys.js made of it: _groups is row id -> its keys, _model
		// the rows, blocks and where each key sits
		_data						: null,
		_model					: null,
		_groups					: {},
		// The row that is open - its id is the part of a key it stands for,
		// eg. 'template/page-home' - and the key that was asked for, if one
		// was: a hit of the search, a link
		_currentGroup		: null,
		_focusKey				: null,
		// What the search box holds, whether the list is cut down to the keys
		// with no text in the language, and how many hits are drawn
		_search					: { query : '', emptyOnly : false, shown : 0 },
		// The wrapper of every field of the open row that is one per language,
		// to draw it again in place when the language changes
		_fieldWraps			: {},
		_sectionEls			: {},
		_fieldSeq				: 0,
		_selectedLocale	: null,
		_localeValues		: {},
		_dirtyLocales		: [],
		// What the controls of the open group held when they were drawn or last
		// saved, key -> value: the global fields, and the translation on screen.
		// A rich-text field reads back as the markup it built, which is not
		// always the string the server holds, so a translation is edited when a
		// control differs from this - not from the stored value
		_baseline				: { global : {}, locale : {} },
		_htmlEditors		: {},
		_fieldEls				: {},
		_loading				: false,
		_saving					: false,
		_ready					: false,

		/**
		 *	Load every editable key, sort them into rows and render the list - or,
		 *	where the hash names a row or a key, open it
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('text-list') === null || Nino.admin.text._loading === true || Nino.admin.text._ready === true )
				return;

			Nino.admin.text._loading = true;

			Nino.admin.text._apiCall( 'keys', {}, function( status, response ) {
				Nino.admin.text._loading = false;
				if( status !== 200 || response === null )
					return Nino.admin.text._showError( dc.getElementById('text-list'), status, response );

				// Capture the hash before any _show*() call below can overwrite it -
				// _showList() would otherwise wipe the deep-link part it's trying to restore
				const hash = Nino.admin.router.current();

				Nino.admin.text._locales = response.locales;
				Nino.admin.sessionLocale.init( response.selectedLocale );
				Nino.admin.text._data = response;
				Nino.admin.text._buildModel();
				Nino.admin.text._renderList();
				Nino.admin.text._ready 	= true;

				const target = hash.panel === 'text' && hash.parts.length > 0 ? Nino.admin.text._resolve( hash.parts ) : null;

				if( target !== null )
					Nino.admin.text._openGroup( target.row, target.key );
				else
					Nino.admin.text._showList();
			} );
		},

		/**
		 *	The language the panel shows: the one of the session, which is the
		 *	one the interface is in too
		 *
		 *	@return		{string}
		 */
		_locale : function() {
			return Nino.admin.sessionLocale.current ?? Nino.admin.text._locales[0] ?? '';
		},

		/**
		 *	Sort the keys the server sent into rows (see textkeys.js)
		 *
		 *	@return		void
		 */
		_buildModel : function() {

			const data = Nino.admin.text._data;

			Nino.admin.text._model = Nino.admin.textKeys.build( {
				entries : data.keys, pages : data.pages, templates : data.templates, features : data.features, order : data.order, locale : Nino.admin.text._locale(),
			} );
			Nino.admin.text._groups = Object.create( null );

			Object.keys( Nino.admin.text._model.rows ).forEach( function( id ) {
				Nino.admin.text._groups[id] = Nino.admin.text._model.rows[id].entries;
			} );
		},

		/**
		 *	What a hash behind #text names: a row, or a key and its row
		 *
		 *	@param		{Array}		parts					The hash behind the panel's name
		 *
		 *	@return		{Object|null}						{ row, key }, see Nino.admin.textKeys.resolve()
		 */
		_resolve : function( parts ) {
			return Nino.admin.text._model === null ? null : Nino.admin.textKeys.resolve( Nino.admin.text._model, parts );
		},

		/**
		 *	Re-apply whatever drill-down level this panel is currently on -
		 *	called when the user switches TO this tab, so the hash (only ever
		 *	written by router.set() while this panel is the visible one) gets
		 *	synced to reality instead of staying stale from before the switch.
		 *
		 *	Where the hash names this panel, the hash wins: a step through the
		 *	browser's history changes the address and nothing else, so the level
		 *	it names is shown - and leaving a form that holds unsaved input asks
		 *	first, as its back link does. A hash that names another panel (a
		 *	click on the rail) leaves the level in memory as it is
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.text._ready === false ) {
				Nino.admin.text.init();
				return;
			}

			const hash = Nino.admin.router.current();
			if( hash.panel === 'text' && Nino.admin.text._follow( hash.parts ) === true )
				return;

			Nino.admin.text._showLevel();
		},

		/**
		 *	Show the level this panel is on, and write it into the address
		 *
		 *	@return		void
		 */
		_showLevel : function() {

			if( dc.getElementById('text-form').classList.contains('admin-hidden') === false )
				return Nino.admin.text._showForm();

			Nino.admin.text._showList();
		},

		/**
		 *	Move to the level the hash names, if it is not the one on screen.
		 *	A hash that names a row - or a key, which is in one - opens it; one
		 *	that names nothing there is is the list. Leaving a form with unsaved
		 *	input asks first (see Nino.admin.router.leave()). A key of the row
		 *	that is open is shown in place
		 *
		 *	@param		{Array}		parts					The hash behind the panel's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const open = dc.getElementById('text-form').classList.contains('admin-hidden') === false;
			const target = parts.length > 0 ? Nino.admin.text._resolve( parts ) : null;

			if( target === null ? open === false : ( open === true && Nino.admin.text._currentGroup === target.row ) ) {

				// Another key of the row on screen is no move, but the field the
				// address names is the one shown and the one it keeps
				if( target !== null && target.key !== Nino.admin.text._focusKey ) {
					Nino.admin.text._focusKey = target.key;

					if( target.key !== null )
						Nino.admin.text._focusField( target.key );
				}

				return false;
			}

			Nino.admin.router.leave( [ 'text' ], open, function() {
				if( target === null ) {
					Nino.admin.text._destroyHtmlEditors();
					Nino.admin.text._showList();
					return;
				}
				Nino.admin.text._openGroup( target.row, target.key );
			}, Nino.admin.text._showLevel );

			return true;
		},

		/**
		 *	Call a text/* admin action - this panel's name for Nino.adminUi.api.call(),
		 *	which owns where the request goes
		 *
		 *	@param		{string}		endpoint			Action name (eg. "savebatch", becomes "text/savebatch")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'text/'+ endpoint, payload, callback );
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
			Nino.adminUi.showError( container, status, response, '/_admin/text/error/load' );
		},

		/**
		 *	Drill-down navigation: list -> row form. The main
		 *	System/Text/Elements bar stays visible throughout.
		 *
		 *	@return		void
		 */
		_showList : function() {
			dc.getElementById('text-list').classList.remove('admin-hidden');
			dc.getElementById('text-form').classList.add('admin-hidden');
			Nino.admin.router.set( 'text', [] );

			// What was edited in the form, or the language it was in, is not
			// what the list showed when it was last drawn
			if( Nino.admin.text._model !== null )
				Nino.admin.text._renderCategoryList();
		},

		_showForm : function() {
			dc.getElementById('text-list').classList.add('admin-hidden');
			dc.getElementById('text-form').classList.remove('admin-hidden');
			Nino.admin.router.set( 'text', Nino.admin.textKeys.hashParts( Nino.admin.text._currentGroup, Nino.admin.text._focusKey ) );
		},

		/**
		 *	Draw the list's own controls - the search, the language, the filter
		 *	for the keys with no text in it - and the place the rows or the hits
		 *	go, then the rows
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('text-list');
			wrap.innerHTML = '';

			const search = Nino.admin.textKeys.searchBar( {
				query : Nino.admin.text._search.query,
				onQuery : function( query ) {
					Nino.admin.text._search.query = query;
					Nino.admin.text._search.shown = 0;
					Nino.admin.text._renderCategoryList();
				},
				select : { locales : Nino.admin.text._locales, value : Nino.admin.text._locale(), onChange : function( locale ) { Nino.admin.text._setLocale( locale ) } },
				chips : [ { id : 'empty', onToggle : function() {
					Nino.admin.text._search.emptyOnly = Nino.admin.text._search.emptyOnly === false;
					Nino.admin.text._search.shown = 0;
					Nino.admin.text._renderCategoryList();
				} } ],
			} );

			search.input.id = 'text-search';
			search.select.id = 'text-list-locale';
			search.chips.empty.id = 'text-list-empty';
			wrap.appendChild( search.bar );

			const status = dc.createElement('p');
			status.id = 'text-list-status';
			status.className = 'nino-admin-hint';
			status.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( status );

			const body = dc.createElement('div');
			body.id = 'text-list-body';
			wrap.appendChild( body );

			Nino.admin.text._renderCategoryList();
		},

		/**
		 *	Change the language the panel shows. Nothing is edited by it: it is
		 *	the session's language, which the next form opens in too
		 *
		 *	@param		{string}	locale
		 *
		 *	@return		void
		 */
		_setLocale : function( locale ) {
			Nino.admin.sessionLocale.set( locale );
			Nino.admin.text._search.shown = 0;
			Nino.admin.text._buildModel();
			Nino.admin.text._renderCategoryList();
		},

		/**
		 *	Draw what is below the controls: the hits where something is
		 *	searched for, the rows otherwise
		 *
		 *	@return		void
		 */
		_renderCategoryList : function() {

			const body = dc.getElementById('text-list-body');

			if( body === null )
				return;

			const locale = Nino.admin.text._locale();
			const search = Nino.admin.text._search;

			const select = dc.getElementById('text-list-locale');
			if( select !== null )
				select.value = locale;

			const chip = dc.getElementById('text-list-empty');
			chip.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/text/label/empty-in'), locale );
			chip.setAttribute( 'aria-pressed', search.emptyOnly === true ? 'true' : 'false' );

			body.innerHTML = '';

			if( search.query.trim() === '' && search.emptyOnly === false ) {
				dc.getElementById('text-list-status').textContent = '';
				Nino.admin.text._renderRows( body );
				return;
			}

			Nino.admin.text._renderHits( body );
		},

		/**
		 *	The rows, in the blocks and groups textkeys.js made of them
		 *
		 *	@param		{Element}	body
		 *
		 *	@return		void
		 */
		_renderRows : function( body ) {

			const model = Nino.admin.text._model;

			if( Object.keys( model.rows ).length === 0 ) {
				body.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/text/empty') ) );
				return;
			}

			model.blocks.forEach( function( block ) {

				const section = dc.createElement('section');
				section.className = 'admin-text-block';

				const heading = dc.createElement('h2');
				heading.textContent = Nino.content.getText( '/_admin/text/group/'+ block.id );
				section.appendChild( heading );

				( block.rows !== undefined ? [ { id : block.id, rows : block.rows } ] : block.groups ).forEach( function( group ) {

					if( block.rows === undefined && group.id !== 'common' ) {
						const title = dc.createElement('h3');
						title.textContent = Nino.content.getText( '/_admin/text/group/'+ group.id );
						section.appendChild( title );
					}

					section.appendChild( Nino.admin.text._renderRowList( group.rows ) );
				} );

				body.appendChild( section );
			} );
		},

		/**
		 *	One list of rows, each a button that opens the row's form - styled the
		 *	same as the Elements type list
		 *
		 *	@param		{Array}		ids						Row ids
		 *
		 *	@return		{Element}
		 */
		_renderRowList : function( ids ) {

			const model 	= Nino.admin.text._model;
			const locale 	= Nino.admin.text._locale();
			const list 		= dc.createElement('div');
			list.className = 'nino-admin-list nino-admin-list-buttons';

			ids.forEach( function( id ) {

				const row = model.rows[id];

				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'admin-type-btn';
				btn.dataset.group = id;

				if( Nino.admin.textKeys.rowDepth( row ) > 0 )
					btn.classList.add('is-sub');

				const titleWrap = dc.createElement('div');
				titleWrap.textContent = Nino.admin.textKeys.rowLabel( model, row, locale );

				const descr = dc.createElement('div');
				descr.className = 'admin-type-btn-descr';
				descr.textContent = Nino.admin.textKeys.rowSummary( row, locale );
				titleWrap.appendChild( descr );

				const chev = dc.createElement('span');
				chev.className = 'admin-view-button-chev';
				chev.setAttribute( 'aria-hidden', 'true' );
				chev.textContent = '›';

				btn.appendChild( titleWrap );
				btn.appendChild( chev );
				btn.addEventListener( 'click', function() {
					Nino.admin.router.go( 'text', Nino.admin.textKeys.hashParts( id ) );
					Nino.admin.text._openGroup( id );
				} );

				list.appendChild( btn );
			} );

			return list;
		},

		/**
		 *	The hits of the search: one line for each key - the path a person
		 *	reads it by, the piece of its text with the words marked, its key
		 *	small, and where the words are in another language than the one on
		 *	screen, that language's code - 50 at a time. A hit opens the row at its
		 *	field; only the code switches the language. Nothing is edited here:
		 *	saving and the log belong to a row
		 *
		 *	@param		{Element}	body
		 *
		 *	@return		void
		 */
		_renderHits : function( body ) {

			const search 	= Nino.admin.text._search;
			const locale 	= Nino.admin.text._locale();
			const hits 		= Nino.admin.textKeys.search( Nino.admin.text._model, { query : search.query, locale : locale, emptyIn : search.emptyOnly === true ? locale : '' } );
			const status 	= dc.getElementById('text-list-status');
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
				list.appendChild( Nino.admin.text._renderHit( hit, locale ) );
			} );

			body.appendChild( list );

			if( hits.length > search.shown ) {
				const more = dc.createElement('button');
				more.type = 'button';
				more.className = 'nino-admin-btn-secondary';
				more.textContent = Nino.content.getText('/_admin/text/label/more');
				more.addEventListener( 'click', function() {
					search.shown += step;
					Nino.admin.text._renderCategoryList();
				} );
				body.appendChild( more );
			}
		},

		/**
		 *	One hit: it opens the row at its field, and only its language code
		 *	switches the language
		 *
		 *	@param		{Object}	hit						See Nino.admin.textKeys.search()
		 *	@param		{string}	locale				The language on screen
		 *
		 *	@return		{Element}
		 */
		_renderHit : function( hit, locale ) {
			return Nino.admin.textKeys.hitElement( hit, locale, function() {
				Nino.admin.router.go( 'text', Nino.admin.textKeys.hashParts( hit.item.row.id, hit.item.field.entry.key ) );
				Nino.admin.text._openGroup( hit.item.row.id, hit.item.field.entry.key );
			}, Nino.admin.text._setLocale );
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

			Nino.admin.text._destroyHtmlEditors();

			Nino.admin.text._currentGroup 	= group;
			Nino.admin.text._focusKey 			= key ?? null;
			Nino.admin.text._selectedLocale = Nino.admin.sessionLocale.current ?? Nino.admin.text._locales[0] ?? '';
			Nino.admin.text._localeValues 	= {};
			Nino.admin.text._dirtyLocales 	= [];
			Nino.admin.text._baseline 			= { global : {}, locale : {} };
			Nino.admin.text._fieldEls 			= {};

			Nino.admin.text._renderGroupForm();
			Nino.admin.text._showForm();

			if( Nino.admin.text._focusKey !== null )
				Nino.admin.text._focusField( Nino.admin.text._focusKey );
		},

		/**
		 *	Scroll to the field of a key, mark it and put the cursor in it
		 *
		 *	@param		{string}	key
		 *
		 *	@return		void
		 */
		_focusField : function( key ) {

			const field = Array.from( dc.getElementById('text-form').querySelectorAll('[data-key]') ).find( function( el ) { return el.dataset.key === key } );

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
		 *	Render one key as a labeled field, nino-admin-richtext or textarea+counter
		 *	depending on the entry, matching Elements' admin.js's field styling.
		 *
		 *	The field is named by its label - "Titel" - and says in its description
		 *	what else a person may want to know: the key it is, small, and the
		 *	counter of what has been typed. Neither is part of the name a screen
		 *	reader announces, which a counter inside a <label> used to be. A field
		 *	that is the same in every language says so
		 *
		 *	@param		{Object}	entry					Key entry
		 *	@param		{*}				value					Current value
		 *	@param		{string}	[label]				The field's name, see Nino.admin.textKeys.sections()
		 *
		 *	@return		{Element}								Field wrapper
		 */
		_renderKeyField : function( entry, value, label ) {

			const id = 'text-field-'+ ( ++Nino.admin.text._fieldSeq );

			// A <div>, for every field: contenteditable is an interactive surface
			// of its own, and nesting it in a <label> is invalid interactive
			// markup - Safari may forward the drag back to the label instead of
			// starting a text selection. The name is pointed at with aria-labelledby
			const wrap = dc.createElement('div');
			wrap.className = 'nino-admin-field admin-text-field';
			wrap.dataset.key = entry.key;

			const header = dc.createElement('div');
			header.className = 'nino-admin-field-header';

			const nameSpan = dc.createElement('span');
			nameSpan.id = id+ '-name';
			nameSpan.className = 'nino-admin-field-name';
			nameSpan.textContent = label ?? entry.key;
			header.appendChild( nameSpan );

			// The badge is part of the field's description, so a screen reader
			// learns that the field is the same in every language
			let described = id+ '-meta';

			if( entry.global === true ) {
				const badge = dc.createElement('span');
				badge.id = id+ '-badge';
				described = badge.id+ ' '+ described;
				badge.className = 'admin-text-badge';
				badge.textContent = Nino.content.getText('/_admin/text/label/all-languages');
				header.appendChild( badge );
			}

			wrap.appendChild( header );

			const meta = dc.createElement('div');
			meta.id = id+ '-meta';
			meta.className = 'admin-text-meta';

			const keyCode = dc.createElement('code');
			keyCode.textContent = entry.key;
			meta.appendChild( keyCode );

			// A key this account may not write (see the panel's mayUpdate()):
			// shown, because seeing the site's text is part of working on it,
			// but not offered as something to type into. The save leaves it
			// out and the server would refuse it either way
			if( Nino.admin.text._writable( entry ) === false ) {

				const view = dc.createElement('div');
				view.className = 'admin-text-readonly';
				view.setAttribute( 'role', 'group' );
				view.setAttribute( 'aria-labelledby', nameSpan.id );
				view.setAttribute( 'aria-describedby', described );
				// Already through \Nino\Text::sanitizeValue() on its way in, and
				// the rich-text editor renders the same string as markup - a
				// read-only view that escaped it would show a key differently
				// from the one next to it that happens to be writable
				if( entry.html === true )
					view.innerHTML = value ?? '';
				else
					view.textContent = value ?? '';
				wrap.appendChild( view );
				wrap.appendChild( meta );

				return wrap;
			}

			if( entry.html === true ) {
				wrap.setAttribute( 'role', 'group' );
				wrap.setAttribute( 'aria-labelledby', nameSpan.id );
				wrap.setAttribute( 'aria-describedby', described );
				const mount = dc.createElement('div');
				wrap.appendChild( mount );
				Nino.admin.text._htmlEditors[entry.key] = Nino.admin.htmlEditor.create( mount, value ?? '', entry.maxlength, 0, entry.format );
				wrap.appendChild( meta );
				return wrap;
			}

			const textarea = dc.createElement('textarea');
			textarea.id = id;
			textarea.maxLength = entry.maxlength;
			textarea.value = value ?? '';
			textarea.setAttribute( 'aria-labelledby', nameSpan.id );
			textarea.setAttribute( 'aria-describedby', described );
			Nino.admin.text._fieldEls[entry.key] = textarea;

			const counter = dc.createElement('span');
			counter.className = 'nino-admin-char-counter';

			function updateCounter() {
				const len = textarea.value.length;
				counter.textContent = len + ' / ' + entry.maxlength;
				counter.classList.toggle( 'is-limit', len >= entry.maxlength );
			}

			textarea.addEventListener( 'input', updateCounter );
			updateCounter();

			meta.appendChild( counter );
			wrap.appendChild( textarea );
			wrap.appendChild( meta );

			return wrap;
		},

		/**
		 *	Current value of one key's field, wherever it's currently mounted
		 *
		 *	@param		{Object}	entry					Key entry
		 *
		 *	@return		{string}
		 */
		_writable : function( entry ) {
			return entry.writable !== false;
		},

		_readKeyValue : function( entry ) {

			if( entry.html === true )
				return ( Nino.admin.text._htmlEditors[entry.key] !== undefined ) ? Nino.admin.text._htmlEditors[entry.key].getValue() : '';

			const el = Nino.admin.text._fieldEls[entry.key];
			return ( el !== undefined ) ? el.value : '';
		},

		/**
		 *	Destroy every currently mounted nino-admin-richtext instance (both global
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
			// Read-only keys have no field to read back - snapshotting them
			// would overwrite their real values with the empty string
			const entries = ( Nino.admin.text._groups[group] ?? [] ).filter( function( e ) { return e.global === false && Nino.admin.text._writable( e ) === true } );

			if( entries.length === 0 || Nino.admin.text._selectedLocale === null )
				return;

			const locale = Nino.admin.text._selectedLocale;
			const previous = Nino.admin.text._localeValues[locale] ?? Object.fromEntries( entries.map( function( entry ) {
				return [ entry.key, entry.values[locale] ?? '' ];
			} ) );
			const values = {};
			entries.forEach( function( entry ) { values[entry.key] = Nino.admin.text._readKeyValue( entry ) } );

			// Against what the controls held when they were drawn, where that is
			// known (see _baseline); merely visiting a translation must not mark it
			const baseline = Nino.admin.text._baseline.locale;
			const changed = entries.some( function( entry ) {
				return String( baseline[entry.key] ?? previous[entry.key] ?? '' ) !== String( values[entry.key] ?? '' );
			} );

			if( changed === true && Nino.admin.text._dirtyLocales.indexOf( locale ) === -1 )
				Nino.admin.text._dirtyLocales.push( locale );

			Nino.admin.text._localeValues[locale] = values;
		},

		/**
		 *	Locales one Save click must persist, in the order they were edited.
		 *	When only global fields changed, the selected locale is a harmless
		 *	fallback that gives the save loop one request to carry them in.
		 *
		 *	@return		{Array<string>}
		 */
		_saveLocales : function() {
			const locales = Nino.admin.text._dirtyLocales.slice();
			if( locales.length === 0 )
				locales.push( Nino.admin.text._selectedLocale );
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
			// '#text-form', not '#text-edit-form': the locale select and the
			// back link are appended to the toolbar beside the form, so the
			// narrower query never reached them. Changing the locale while a
			// save was in flight built fresh, enabled fields for the other
			// locale and sent the queued request from the older snapshot - the
			// screen said "saved" over a field whose text was not persisted,
			// and the back link destroyed the editors mid-save
			const form = dc.getElementById('text-form');
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
		 *	Re-render the locale-scoped fields for the currently selected locale,
		 *	each where it is - in its section - and nothing else: the fields
		 *	that are the same in every language stay as they are, with whatever
		 *	is typed into them, and none of them leaves _fieldEls
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

			const stored = Nino.admin.text._localeValues[Nino.admin.text._selectedLocale] ?? {};
			const labels = Nino.admin.text._fieldLabels();

			entries.forEach( function( entry ) {
				const value = ( stored[entry.key] !== undefined ) ? stored[entry.key] : ( entry.values[Nino.admin.text._selectedLocale] ?? '' );
				const fresh = Nino.admin.text._renderKeyField( entry, value, labels[entry.key] );
				const old = Nino.admin.text._fieldWraps[entry.key];

				if( old !== undefined && old.parentNode !== null )
					old.parentNode.replaceChild( fresh, old );

				Nino.admin.text._fieldWraps[entry.key] = fresh;
			} );

			Nino.admin.text._renderPreviews();
			Nino.admin.text._captureBaseline( false );
		},

		/**
		 *	The name of every field of the open row, key -> label
		 *
		 *	@return		{Object}
		 */
		_fieldLabels : function() {

			const model = Nino.admin.text._model;
			const labels = {};

			Nino.admin.textKeys.sections( model, model.rows[Nino.admin.text._currentGroup] ).forEach( function( section ) {
				section.fields.forEach( function( field ) { labels[field.entry.key] = field.label } );
			} );

			return labels;
		},

		/**
		 *	The beginning of the first text of each section, in the legend, in the
		 *	language that is on screen
		 *
		 *	@return		void
		 */
		_renderPreviews : function() {

			const model = Nino.admin.text._model;
			const locale = Nino.admin.text._selectedLocale;

			Nino.admin.textKeys.sections( model, model.rows[Nino.admin.text._currentGroup] ).forEach( function( section ) {
				const preview = Nino.admin.text._sectionEls[section.id];
				if( preview !== undefined )
					preview.textContent = Nino.admin.textKeys.sectionPreview( section, locale, Nino.admin.text._localeValues[locale] );
			} );
		},

		/**
		 *	Take what the controls on screen hold now as what is saved - the
		 *	translation after it was drawn, the global fields too when the whole
		 *	form was drawn or saved. A key this account may not write has no
		 *	field to read
		 *
		 *	@param		{boolean}	withGlobal
		 *
		 *	@return		void
		 */
		_captureBaseline : function( withGlobal ) {

			const entries = Nino.admin.text._groups[Nino.admin.text._currentGroup] ?? [];
			const read = function( global ) {
				const held = {};
				entries.filter( function( entry ) { return entry.global === global && Nino.admin.text._writable( entry ) === true } ).forEach( function( entry ) {
					held[entry.key] = Nino.admin.text._readKeyValue( entry );
				} );
				return held;
			};

			Nino.admin.text._baseline.locale = read( false );

			if( withGlobal === true )
				Nino.admin.text._baseline.global = read( true );
		},

		/**
		 *	Whether the open group holds input nobody has saved - what the shell
		 *	asks before it lets anything throw that away (see Nino.admin.dirty):
		 *	a translation that was edited and left, or a writable field that
		 *	differs from what it held when it was drawn or last saved
		 *
		 *	@return		{boolean}
		 */
		isDirty : function() {

			const form = dc.getElementById('text-form');

			if( form === null || Nino.admin.text._currentGroup === null || form.classList.contains('admin-hidden') === true || dc.getElementById('text-edit-form') === null )
				return false;

			if( Nino.admin.text._dirtyLocales.length > 0 )
				return true;

			const entries = Nino.admin.text._groups[Nino.admin.text._currentGroup] ?? [];

			return entries.some( function( entry ) {
				const held = entry.global === true ? Nino.admin.text._baseline.global : Nino.admin.text._baseline.locale;
				return Nino.admin.text._writable( entry ) === true && held[entry.key] !== undefined && Nino.admin.text._readKeyValue( entry ) !== held[entry.key];
			} );
		},

		/**
		 *	Throw the input away: what is stored locally goes, the stored
		 *	values the server sent stay, and what the controls show counts as
		 *	saved. The form is about to be left or drawn again; if the exit then
		 *	fails without drawing it (a refused request), the controls still show
		 *	the discarded text and the next Save writes it
		 *
		 *	@return		void
		 */
		discard : function() {
			Nino.admin.text._dirtyLocales = [];
			Nino.admin.text._localeValues = {};
			Nino.admin.text._captureBaseline( true );
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	Render a row's bulk-edit form: a section for each part - a page begins
		 *	with the details of its routes - and in it every key, the ones that
		 *	are the same in every language first. A locale select in the pinned
		 *	toolbar says which language the others are shown in
		 *
		 *	@return		void
		 */
		_renderGroupForm : function() {

			const group 	= Nino.admin.text._currentGroup;
			const entries = Nino.admin.text._groups[group] ?? [];
			const model 	= Nino.admin.text._model;
			const row 		= model.rows[group];

			// Every key of the row, writable or not - a read-only one is
			// rendered as a read-only field (see _renderKeyField()), because
			// seeing the text around the one being edited is the point of
			// editing a whole row at once
			const localeEntries = entries.filter( function( e ) { return e.global === false } );

			const wrap = dc.getElementById('text-form');
			wrap.innerHTML = '';

			Nino.admin.text._fieldWraps = {};
			Nino.admin.text._sectionEls = Object.create( null );

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/text/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.router.go( 'text', [] ); Nino.admin.text._destroyHtmlEditors(); Nino.admin.text._showList() } );
			const toolbar = Nino.admin.formToolbar( backLink );
			wrap.appendChild( toolbar );

			const form = dc.createElement('form');
			form.id = 'text-edit-form';

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = Nino.admin.textKeys.rowLabel( model, row, Nino.admin.text._selectedLocale );
			wrap.appendChild( title );

			if( localeEntries.length > 0 ) {

				const select = dc.createElement('select');
				select.id = 'text-form-locale-select';
				select.className = 'nino-admin-locale-select nino-admin-contextbar-select';
				select.setAttribute( 'aria-label', Nino.content.getText('/_admin/text/label/language') );
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
					Nino.admin.text._model.locale = select.value;
					Nino.admin.sessionLocale.set( select.value );
					Nino.admin.text._renderLocaleFields();
				} );
				toolbar.appendChild( select );
			}

			const stored = Nino.admin.text._localeValues[Nino.admin.text._selectedLocale] ?? {};

			Nino.admin.textKeys.sections( model, row ).forEach( function( section ) {

				const fieldset = dc.createElement('fieldset');
				fieldset.className = 'admin-text-section';
				fieldset.dataset.section = section.id;

				const legend = dc.createElement('legend');

				const name = dc.createElement('span');
				name.className = 'admin-text-section-name';
				name.textContent = section.label;
				legend.appendChild( name );

				if( section.route !== '' ) {
					const route = dc.createElement('code');
					route.className = 'admin-text-section-route';
					route.textContent = section.route;
					legend.appendChild( route );
				}

				const preview = dc.createElement('span');
				preview.className = 'admin-text-section-preview';
				legend.appendChild( preview );
				Nino.admin.text._sectionEls[section.id] = preview;

				fieldset.appendChild( legend );

				const grid = dc.createElement('div');
				grid.className = 'nino-admin-fieldgrid';

				section.fields.forEach( function( field ) {
					const entry = field.entry;
					const value = entry.global === true ? ( entry.values['*'] ?? '' )
						: ( stored[entry.key] !== undefined ? stored[entry.key] : ( entry.values[Nino.admin.text._selectedLocale] ?? '' ) );
					const el = Nino.admin.text._renderKeyField( entry, value, field.label );

					if( entry.global === false )
						Nino.admin.text._fieldWraps[entry.key] = el;

					grid.appendChild( el );
				} );

				fieldset.appendChild( grid );
				form.appendChild( fieldset );
			} );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			// A row this account may not write anywhere is a row to read.
			// Offering Save on it would post an empty batch and report success
			// for a screen where nothing could have changed
			const writable = entries.some( function( e ) { return Nino.admin.text._writable( e ) === true } );

			if( writable === true ) {
				const saveBtn = dc.createElement('button');
				saveBtn.type = 'submit';
				saveBtn.textContent = Nino.content.getText('/_admin/text/label/save');
				actions.appendChild( saveBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'text-form-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.text._save() } );

			wrap.appendChild( form );

			Nino.admin.text._renderPreviews();
			Nino.admin.text._captureBaseline( true );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	Save every key of the current row (global fields once, plus
		 *	every locale edited before the click) in sequential batched requests.
		 *	Deliberately does not navigate back to the list afterwards - only
		 *	refreshes its preview.
		 *
		 *	Every way this ends reports to done( ok ), if there is one: the
		 *	shell's question about unsaved input saves through it and goes on
		 *	only when it hears true (see Nino.admin.dirty.guard())
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

			if( Nino.admin.text._saving === true ) {
				report( false );
				return;
			}

			Nino.admin.text._storeVisibleLocaleFields();

			const group 	= Nino.admin.text._currentGroup;
			const entries = Nino.admin.text._groups[group] ?? [];
			const msg 		= dc.getElementById('text-form-msg');

			const globalEntries = entries.filter( function( e ) { return e.global === true && Nino.admin.text._writable( e ) === true } );
			const localeEntries = entries.filter( function( e ) { return e.global === false && Nino.admin.text._writable( e ) === true } );
			const globalItems = globalEntries.map( function( entry ) {
				return { key : entry.key, locale : '*', value : Nino.admin.text._readKeyValue( entry ) };
			} );
			const locales = Nino.admin.text._saveLocales();
			let position = 0;

			Nino.admin.text._saving = true;
			Nino.admin.text._setFormPending( true );
			msg.textContent = Nino.content.getText('/_admin/text/msg/pending');

			function saveNextLocale() {
				const locale = locales[position];
				const localeValues = Nino.admin.text._localeValues[locale] ?? {};
				const localeItems = localeEntries.map( function( entry ) {
					return { key : entry.key, locale : locale, value : localeValues[entry.key] ?? entry.values[locale] ?? '' };
				} );
				const items = ( position === 0 ? globalItems : [] ).concat( localeItems );

				Nino.admin.text._apiCall( 'savebatch', { items : items }, function( status, response ) {

					if( status !== 200 || response === null ) {
						Nino.admin.text._saving = false;
						Nino.admin.text._setFormPending( false );
						msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/text/error/save' );
						report( false );
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
								Nino.admin.text._localeValues[item.locale] = Nino.admin.text._localeValues[item.locale] ?? {};
								Nino.admin.text._localeValues[item.locale][item.key] = result.value;
							}
						}
					} );

					if( failed.length > 0 ) {
						Nino.admin.text._saving = false;
						Nino.admin.text._setFormPending( false );
						msg.textContent = Nino.content.getText('/_admin/text/error/save')+ ' ('+ failed.join(', ')+ ')';
						report( false );
						return;
					}

					const dirtyAt = Nino.admin.text._dirtyLocales.indexOf( locale );
					if( dirtyAt !== -1 )
						Nino.admin.text._dirtyLocales.splice( dirtyAt, 1 );

					position++;
					if( position < locales.length ) {
						saveNextLocale();
						return;
					}

					Nino.admin.text._saving = false;
					Nino.admin.text._setFormPending( false );
					msg.textContent = Nino.content.getText('/_admin/text/msg/saved');
					Nino.admin.text._renderCategoryList();

					// What the controls hold is what is stored now
					Nino.admin.text._captureBaseline( true );
					report( true );
				} );
			}

			saveNextLocale();
		},
	};

	// The shell asks before anything throws the open group's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'text', {
			isDirty : Nino.admin.text.isDirty,
			save		: function( done ) { Nino.admin.text._save( done ) },
			discard : Nino.admin.text.discard,
		} );

})(window, document, document.documentElement, document.body);
