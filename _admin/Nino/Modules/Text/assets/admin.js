/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js									Admin "Text" panel: find a text - by what it says, what it
 *													is called or its key - or browse the rows the keys fall
 *													into (pages first, then the project, the common words,
 *													building blocks, modules, features), then edit every key
 *													of a row at once: a section for each part, the fields that
 *													are the same in every language marked. The language the
 *													texts are shown and written in is the workbench's own
 *													(Nino.admin.workLocale): a row on screen shows it, writes
 *													it and nothing else. How keys become rows, sections and
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

		// The languages that have a text file, the first of which is the language of the panel where the workbench has none
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
		// The language the form on screen was drawn in: what it shows and the only one it writes
		_shownLocale		: null,
		// What the form on screen knows of its fields (see Nino.admin.textKeys.session()), and the line of its bar
		_session				: null,
		_status					: null,
		// The button that opened the form: Back returns the focus to it
		_opener					: null,
		_fieldSeq				: 0,
		_loading				: false,
		_saving					: false,
		_ready					: false,
		// The stamp of the templates (see Nino.admin.touch()) the keys were asked
		// for: the rows of the templates are made from what the answer held
		_stamp					: 0,

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

			// A template written while the answer is on its way is not in it: the
			// stamp is the one from before the request went out
			const stamp = Nino.admin.stamp( 'templates' );

			Nino.admin.text._apiCall( 'keys', {}, function( status, response ) {
				Nino.admin.text._loading = false;
				if( status !== 200 || response === null )
					return Nino.admin.text._showError( dc.getElementById('text-list'), status, response );

				// Capture the hash before any _show*() call below can overwrite it -
				// _showList() would otherwise wipe the deep-link part it's trying to restore
				const hash = Nino.admin.router.current();

				Nino.admin.text._locales = response.locales.map( function( entry ) { return entry.locale } );
				Nino.admin.text._data = response;
				Nino.admin.text._buildModel();
				Nino.admin.text._renderList();
				Nino.admin.text._ready 	= true;
				Nino.admin.text._stamp 	= stamp;

				const target = hash.panel === 'text' && hash.parts.length > 0 ? Nino.admin.text._resolve( hash.parts ) : null;

				if( target !== null )
					Nino.admin.text._openGroup( target.row, target.key );
				else
					Nino.admin.text._showList();
			} );
		},

		/**
		 *	The language the panel shows: the one the workbench edits content in
		 *	(Nino.admin.workLocale), the first language of the panel where there is none
		 *
		 *	@return		{string}
		 */
		_locale : function() {
			return Nino.admin.workLocale.current ?? Nino.admin.text._locales[0] ?? '';
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
		 *	click on the rail) leaves the level in memory as it is.
		 *
		 *	The keys are read again where a template was written since they were
		 *	read (the Builder creates them, and so does a key of a template that
		 *	the Keys tab adds), so that the rows have it. Not while a row is
		 *	open: the list would be drawn over it, and the showing that finds
		 *	none open reads them
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.text._ready === false ) {
				Nino.admin.text.init();
				return;
			}

			if( dc.getElementById('text-form').classList.contains('admin-hidden') === true && Nino.admin.stamp( 'templates' ) !== Nino.admin.text._stamp ) {
				Nino.admin.text._ready = false;
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
			Nino.adminUi.showError( container, status, response, '/_admin/common/error/load' );
		},

		/**
		 *	Drill-down navigation: list -> row form. The main
		 *	System/Text/Elements bar stays visible throughout. The focus stays
		 *	where it is, unless it stood in the level that is hidden now (see
		 *	Nino.adminUi.showLevel()): the level is shown again as it stands, by the
		 *	address. Back goes to the row, and what a person opens takes the
		 *	focus in
		 *
		 *	@param		{string|null}	[group]		Back: the row that was open, to give the focus to; null where there is none to name
		 *
		 *	@return		void
		 */
		_showList : function( group ) {
			Nino.admin.router.set( 'text', [] );

			// What was edited in the form, or the language it was in, is not
			// what the list showed when it was last drawn - and drawn before the
			// focus goes back, so that the row it goes to is there
			if( Nino.admin.text._model !== null )
				Nino.admin.text._renderCategoryList();

			if( group === undefined )
				Nino.adminUi.showLevel( 'text-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'text-list', group === null ? null : Nino.admin.text._rowOf( group ) );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {
			Nino.admin.router.set( 'text', Nino.admin.textKeys.hashParts( Nino.admin.text._currentGroup, Nino.admin.text._focusKey ) );
			Nino.adminUi.showLevel( 'text-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	The link of a row as the list is drawn now: the list is drawn again
		 *	whenever it is shown, and the link that was pressed is gone with the
		 *	old drawing
		 *
		 *	@param		{string}	group			The row's id
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( group ) {

			const row = Array.from( dc.getElementById('text-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === group } );
			return row === undefined ? null : row.querySelector('a');
		},

		/**
		 *	Draw the list's own controls - the search and the filter for the keys
		 *	with no text in the language - and the place the rows or the hits
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
				chips : [ { id : 'empty', onToggle : function() {
					Nino.admin.text._search.emptyOnly = Nino.admin.text._search.emptyOnly === false;
					Nino.admin.text._search.shown = 0;
					Nino.admin.text._renderCategoryList();
				} } ],
			} );

			search.input.id = 'text-search';
			search.chips.empty.id = 'text-list-empty';
			wrap.appendChild( search.bar );

			const status = dc.createElement('p');
			status.id = 'text-list-status';
			status.className = 'admin-text-count';
			status.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( status );

			const body = dc.createElement('div');
			body.id = 'text-list-body';
			wrap.appendChild( body );

			Nino.admin.text._renderCategoryList();
		},

		/**
		 *	The workbench's content language moved (Nino.admin.workLocale), and the
		 *	shell has asked about the unsaved input of this panel first: the form on
		 *	screen is drawn again in the other language - what it held is saved or
		 *	given up by now - and the list names its rows in it
		 *
		 *	@param		{string}	locale
		 *
		 *	@return		void
		 */
		_followLocale : function( locale ) {

			if( Nino.admin.text._data === null || Nino.admin.text._model === null )
				return;

			const form = dc.getElementById('text-form');

			if( form.classList.contains('admin-hidden') === false && dc.getElementById('text-edit-form') !== null ) {

				if( locale === Nino.admin.text._shownLocale )
					return;

				Nino.admin.text._buildModel();
				Nino.admin.text._renderGroupForm();
				return;
			}

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

			const chip = dc.getElementById('text-list-empty');
			chip.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/text/label/empty-in'), Nino.adminUi.localeName( locale ) );
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
				section.appendChild( Nino.adminUi.sectionTitle( Nino.content.getText( '/_admin/text/group/'+ block.id ) ) );

				( block.rows !== undefined ? [ { id : block.id, rows : block.rows } ] : block.groups ).forEach( function( group ) {
					section.appendChild( Nino.admin.text._renderRowList( group.rows, block.rows === undefined && group.id !== 'common' ? Nino.content.getText( '/_admin/text/group/'+ group.id ) : '' ) );
				} );

				body.appendChild( section );
			} );
		},

		/**
		 *	One group of rows, each a row of the workbench's list that opens the row's
		 *	form, and has its address
		 *
		 *	@param		{Array}		ids						Row ids
		 *	@param		{string}	[title]				The name of the group, where it has one
		 *
		 *	@return		{Element}
		 */
		_renderRowList : function( ids, title ) {

			const model 	= Nino.admin.text._model;
			const locale 	= Nino.admin.text._locale();

			return Nino.adminUi.list( ids.map( function( id ) {

				const row = model.rows[id];

				return {
					key : id,
					title : Nino.admin.textKeys.rowLabel( model, row, locale ),
					detail : Nino.admin.textKeys.rowSummary( row, locale ),
					sub : Nino.admin.textKeys.rowDepth( row ) > 0,
					href : Nino.admin.router.href( 'text', Nino.admin.textKeys.hashParts( id ) ),
					onOpen : function() {
						Nino.admin.router.go( 'text', Nino.admin.textKeys.hashParts( id ) );
						Nino.admin.text._openGroup( id, null, true );
					},
				};
			} ), { title : title, level : 3 } );
		},

		/**
		 *	The hits of the search: one line for each key - the path a person
		 *	reads it by, the piece of its text with the words marked, its key
		 *	small, and where the words are in another language than the one on
		 *	screen, that language's code - 50 at a time (see
		 *	Nino.admin.textKeys.hitList()). A hit opens the row at its field; only
		 *	the code switches the language. Nothing is edited here: saving and the
		 *	log belong to a row
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

			body.appendChild( Nino.admin.textKeys.hitList( hits.slice( 0, search.shown ), locale, {
				href : function( hit ) { return Nino.admin.router.href( 'text', Nino.admin.textKeys.hashParts( hit.item.row.id, hit.item.field.entry.key ) ) },
				onOpen : function( hit ) {
					Nino.admin.router.go( 'text', Nino.admin.textKeys.hashParts( hit.item.row.id, hit.item.field.entry.key ) );
					Nino.admin.text._openGroup( hit.item.row.id, hit.item.field.entry.key, true );
				},
				// Asked for as every change of the language is, so that a view with unsaved input is asked about first
				onLanguage : function( code ) { Nino.admin.workLocale.choose( code ) },
			} ) );

			if( hits.length > search.shown ) {
				const more = Nino.adminUi.button( Nino.content.getText('/_admin/text/label/more'), function() {
					search.shown += step;
					Nino.admin.text._renderCategoryList();
				}, { kind : 'secondary' } );
				body.appendChild( more );
			}
		},

		/**
		 *	Open a row's bulk-edit form, at one of its keys where one was asked
		 *	for: that field is scrolled to and marked
		 *
		 *	@param		{string}		group					The row's id
		 *	@param		{string|null}	[key]
		 *	@param		{boolean}			[opened]			The person opened it - a row, or a hit: the focus goes into the form, to the field of the key where there is one. Otherwise it is shown as it stands, by the address
		 *
		 *	@return		void
		 */
		_openGroup : function( group, key, opened ) {

			Nino.admin.text._destroyHtmlEditors();

			Nino.admin.text._currentGroup = group;
			Nino.admin.text._focusKey 		= key ?? null;

			Nino.admin.text._renderGroupForm();
			Nino.admin.text._showForm( opened === true );

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

			const control = field.querySelector('textarea, input, [contenteditable="true"]');

			if( control !== null && typeof control.focus === 'function' )
				control.focus( { preventScroll : true } );
		},

		/**
		 *	Whether this account may write a key - see the panel's mayUpdate(): the flag is absent for anyone
		 *	unrestricted, so a key is writable unless it says it is not
		 *
		 *	@param		{Object}	entry					Key entry
		 *
		 *	@return		{boolean}
		 */
		_writable : function( entry ) {
			return entry.writable !== false;
		},

		/**
		 *	The textfills a text may take, for the "[ ]" at the name of a field: asked for when the dialog opens,
		 *	in the language on screen
		 *
		 *	@param		{Function}	done					Called with the list the server sent, with null where it could not be had
		 *
		 *	@return		void
		 */
		_loadFills : function( done ) {
			Nino.admin.textKeys.fills( function( callback ) { Nino.admin.text._apiCall( 'fills', { locale : Nino.admin.text._shownLocale ?? Nino.admin.text._locale() }, callback ) }, done );
		},

		/**
		 *	Take every rich-text editor of the form on screen down, and the fields with it
		 *
		 *	@return		void
		 */
		_destroyHtmlEditors : function() {

			if( Nino.admin.text._session !== null )
				Nino.admin.text._session.destroy();

			Nino.admin.text._session = null;
		},

		/**
		 *	Whether the open group holds input nobody has saved - what the shell
		 *	asks before it lets anything throw that away (see Nino.admin.dirty):
		 *	a writable field that differs from what it held when it was drawn or
		 *	last saved, which includes one whose save is on its way or was refused
		 *
		 *	@return		{boolean}
		 */
		isDirty : function() {

			const form = dc.getElementById('text-form');

			if( form === null || Nino.admin.text._session === null || form.classList.contains('admin-hidden') === true || dc.getElementById('text-edit-form') === null )
				return false;

			return Nino.admin.text._session.isDirty();
		},

		/**
		 *	Throw the input away: every field is put back to what it held. The
		 *	form is about to be left or drawn again. While a save runs nothing is
		 *	thrown away: it finishes with what it was given
		 *
		 *	@return		void
		 */
		discard : function() {

			if( Nino.admin.text._saving === true || Nino.admin.text._session === null )
				return;

			Nino.admin.text._session.discard();

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	The action bar of the form on screen, which is where the shell puts its mark of unsaved input
		 *
		 *	@return		{Element|null}
		 */
		_bar : function() {

			const form = dc.getElementById('text-edit-form');

			return form === null ? null : form.querySelector('.nino-admin-actionbar');
		},

		/**
		 *	Render a row's bulk-edit form in the language the workbench edits content in: the way back and the name
		 *	of the row in the context bar, a section for each part - a page begins with the details of its routes -
		 *	and in it every key, the ones that are the same in every language first. One bar, one Save
		 *
		 *	@return		void
		 */
		_renderGroupForm : function() {

			const text 		= Nino.admin.text;
			const group 	= text._currentGroup;
			const entries = text._groups[group] ?? [];
			const model 	= text._model;
			const row 		= model.rows[group];
			const locale 	= text._locale();

			text._destroyHtmlEditors();
			text._shownLocale = locale;
			text._session = Nino.admin.textKeys.session();

			const wrap = dc.getElementById('text-form');
			wrap.innerHTML = '';

			const back = Nino.adminUi.contextBar( {
				back : { to : Nino.content.getText('/_admin/text/label/back-name'), href : Nino.admin.router.href( 'text', [] ), onClick : function() {
					Nino.admin.router.go( 'text', [] );
					Nino.admin.text._destroyHtmlEditors();
					Nino.admin.text._showList( Nino.admin.text._currentGroup );
				} },
				title : Nino.admin.textKeys.rowLabel( model, row, locale ),
			} );
			wrap.appendChild( back );
			Nino.adminUi.localeBadge( locale, back );

			const form = dc.createElement('form');
			form.id = 'text-edit-form';
			form.noValidate = true;

			// Every key of the row, writable or not - a read-only one is
			// rendered as a read-only field (see Nino.admin.textKeys.valueField()), because
			// seeing the text around the one being edited is the point of
			// editing a whole row at once
			Nino.admin.textKeys.sectionsView( model, row, function( field ) {

				const entry = field.entry;
				const writable = text._writable( entry );
				const handle = Nino.admin.textKeys.valueField( {
					entry : entry,
					value : Nino.admin.textKeys.valueOf( entry, locale ),
					label : field.label,
					id : 'text-field-'+ ( ++text._fieldSeq ),
					writable : writable,
					fills : writable === true ? text._loadFills : null,
				} );

				text._session.add( handle );

				return handle.el;
			} ).forEach( function( fieldset ) { form.appendChild( fieldset ) } );

			text._status = null;

			// A row this account may not write anywhere is a row to read.
			// Offering Save on it would post an empty batch and report success
			// for a screen where nothing could have changed
			if( entries.some( function( e ) { return text._writable( e ) === true } ) === true ) {

				const msg = dc.createElement('p');
				msg.id = 'text-form-msg';

				const bar = Nino.adminUi.actionBar( {
					status : { line : msg },
					primary : Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit' } ),
				} );

				text._status = bar.line;
				bar.line.bind( form, text.isDirty );
				form.appendChild( bar.el );
			}

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); text._save() } );

			wrap.appendChild( form );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	Save the keys of the open row whose field was changed - and nothing else, so that a row saved without a
		 *	change leaves its files as they are - in the language the form was drawn in, in one request. A key the
		 *	server turns down is said at its field and stays changed; what was written is what the field holds
		 *	and what it is marked as saved by. The view stays as it is.
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

			const text = Nino.admin.text;

			const report = function( ok ) {
				if( typeof Nino.admin.dirty === 'object' )
					Nino.admin.dirty.refresh();
				if( typeof done === 'function' )
					done( ok );
			};

			if( text._saving === true || text._session === null || text._status === null ) {
				report( false );
				return;
			}

			const session = text._session;
			const line 		= text._status;
			const locale 	= text._shownLocale;
			const entries = text._groups[text._currentGroup] ?? [];

			Nino.adminUi.clearFieldErrors( dc.getElementById('text-edit-form') );

			const keys = session.changed();

			// Nothing differs from what is stored: nothing is written, and what is on screen is what is stored
			if( keys.length === 0 ) {
				line.saved();
				report( true );
				return;
			}

			const sent = {};
			const items = keys.map( function( key ) {

				const entry = entries.find( function( e ) { return e.key === key } );
				sent[key] = session.get( key ).read();

				// The language the form shows is the language it writes, whatever the workbench says by now
				return { key : key, locale : entry.global === true ? '*' : locale, value : sent[key] };
			} );

			text._saving = true;
			line.saving();

			text._apiCall( 'savebatch', { items : items }, function( status, response ) {

				text._saving = false;

				if( status !== 200 || response === null ) {
					line.error( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				// Written, whichever of the items a later check turns down
				Nino.admin.touch( 'texts' );

				const results = response.results ?? {};
				let first = null;
				let failed = 0;

				items.forEach( function( item ) {

					const result = results[item.key];
					const field = session.get( item.key );

					if( result === undefined || result.ok !== true ) {
						failed++;
						field.fail( Nino.content.getText( result !== undefined && result.error === 'not allowed' ? '/_admin/text/error/denied' : '/_admin/text/error/key' ) );
						first = first ?? field;
						return;
					}

					const entry = entries.find( function( e ) { return e.key === item.key } );
					entry.values[item.locale] = result.value;

					// What the server kept is what the field shows - unless more was typed while it was on its way, which is not
					// in what was sent and stays unsaved
					if( field.read() === sent[item.key] ) {
						if( result.value !== sent[item.key] )
							field.write( result.value );
						session.capture( item.key );
					}
					else
						session.capture( item.key, sent[item.key] );
				} );

				if( failed > 0 ) {
					line.fail( Nino.adminUi.format( Nino.content.getText( failed === 1 ? '/_admin/text/error/one' : '/_admin/text/error/some' ), failed ) );
					first.focus();
					report( false );
					return;
				}

				line.saved();
				report( true );
			} );
		},
	};

	// The language moves for this panel whoever moved it (see Nino.admin.workLocale)
	Nino.admin.workLocale.follow( 'text', Nino.admin.text._followLocale );

	// The shell asks before anything throws the open group's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'text', {
			isDirty : Nino.admin.text.isDirty,
			save		: function( done ) { Nino.admin.text._save( done ) },
			discard : Nino.admin.text.discard,
			saving	: function() { return Nino.admin.text._saving === true },
			bar			: Nino.admin.text._bar,
		} );

})(window, document, document.documentElement, document.body);
