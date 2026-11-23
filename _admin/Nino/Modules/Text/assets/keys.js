/**
 *	Nino										A compact filesystembased php framework
 *	keys.js									"Text" module: find a text key - by its key, the names it
 *													is read by or what it says, hidden ones included - or
 *													browse the rows the keys fall into (the same ones as the
 *													Text panel, with the same names, see textkeys.js) and edit
 *													every key of a row: its text in the language the
 *													workbench edits content in (Nino.admin.workLocale) - or
 *													the one text of a global key - and what its shape is,
 *													its format, its limit, whether it is global and whether
 *													it is hidden from the Text panel. All of it is saved
 *													with the one Save of the row. A key is renamed and
 *													deleted from its own row. Every view has its address:
 *													#text/keys, #text/keys/<row>, #text/keys/<row>/<key>,
 *													#text/keys/@new, #text/keys/@rename/<key> and
 *													#text/keys/@scan - the form that
 *													creates or renames a key, which asks for it segment by
 *													segment: /<namespace>/<category>/<part>/<name>.
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

		// The languages that have a text file, the first of which is the language of the tab where the workbench has none
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
		// The text a key was read as, kept for each key a save was turned down for as changed: the keys are read again after every save, and the
		// next save is held to the text this tab was shown, not to the one it was never shown. Given up by Overwrite and by Reload, which
		// are the answers that say what is to be done with the text of the other tab
		_stamps					: {},
		// The language the row on screen was drawn in: what it shows and the only one it writes
		_shownLocale		: null,
		// What the row on screen knows of its fields (see Nino.admin.textKeys.session()), what it holds
		// besides the text - key -> the controls of its shape and what they held when it was drawn - and
		// the line of its bar
		_session				: null,
		_settings				: {},
		_status					: null,
		// Which form the pane shows - 'group', 'new' (which is also the form that
		// renames) or 'scan' - and the rows of the scan form, for isDirty()
		_view						: null,
		_scanRows				: [],
		// The button that opened the form of a key's own or the scan: Back
		// returns the focus to it (a row's own link is found again, see _backTarget())
		_opener					: null,
		// The key the form of 'new' renames, null while it creates one; whether
		// the namespaces that normally belong to a template, a feature or a module
		// are unlocked; what the form held when it was drawn, for isDirty()
		_renameFrom			: null,
		_unlocked				: false,
		_formInitial		: '',
		_fieldSeq				: 0,
		_saving					: false,
		_ready					: false,

		/**
		 *	Load every known text key, sort them into rows and render the list -
		 *	or, on the first load, open the view the hash names
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('keys-list') === null )
				return;

			Nino.admin.keys._load( function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.admin.keys._showError( dc.getElementById('keys-list'), status, response );

				// Before the first _show*() below, which writes the address
				const hash = Nino.admin.keys._ready === false ? Nino.admin.router.current() : { panel : '', parts : [] };

				Nino.admin.keys._renderCategoryList();
				Nino.admin.keys._showList();
				Nino.admin.keys._ready 	= true;

				const target = hash.panel === 'keys' && hash.parts.length > 0 ? Nino.admin.keys._target( hash.parts ) : null;

				if( target !== null )
					Nino.admin.keys._open( target, false );
			} );
		},

		/**
		 *	Ask the server for the keys and what the form of a new key offers, and sort them into rows. Where the
		 *	answer is a failure nothing is changed
		 *
		 *	@param		{Function}	done					Called with ( status, body )
		 *
		 *	@return		void
		 */
		_load : function( done ) {

			Nino.admin.keys._apiCall( 'list', {}, function( status, response ) {

				if( status === 200 && response !== null ) {
					Nino.admin.keys._locales = response.locales.map( function( entry ) { return entry.locale } );
					Nino.admin.keys._categories = response.categories ?? {};
					Nino.admin.keys._data = response;
					Nino.admin.keys._buildModel();
				}

				done( status, response );
			} );
		},

		/**
		 *	The language the tab shows: the one the workbench edits content in
		 *	(Nino.admin.workLocale), the first language of the tab where there is none
		 *
		 *	@return		{string}
		 */
		_locale : function() {
			return Nino.admin.workLocale.current ?? Nino.admin.keys._locales[0] ?? '';
		},

		/**
		 *	Sort the keys the server sent into rows (see textkeys.js), the keys
		 *	of the workbench among them, and name the rows as the Text panel does
		 *
		 *	@return		void
		 */
		_buildModel : function() {

			const data = Nino.admin.keys._data;

			Nino.admin.keys._model = Nino.admin.textKeys.build( {
				entries : data.keys, pages : data.pages, templates : data.templates, features : data.features, order : data.order, admin : true, locale : Nino.admin.keys._locale(),
			} );
			Nino.admin.keys._groups = Object.create( null );

			Object.keys( Nino.admin.keys._model.rows ).forEach( function( id ) {
				Nino.admin.keys._groups[id] = Nino.admin.keys._model.rows[id].entries;
			} );
		},

		/**
		 *	What the parts of an address name: '@new', '@scan' and '@rename' behind a key - an at sign no key has,
		 *	so that a row called new keeps an address of its own -, a row, a key in a row, or the list. What
		 *	is not there is the list
		 *
		 *	@param		{Array}		parts					The address behind the tab's name
		 *
		 *	@return		{Object|null}						{ view : 'new', key } for the form of a key (null for a new one), { view : 'scan' }, { view : 'group', row, key }, null for the list
		 */
		_target : function( parts ) {

			const keys = Nino.admin.keys;

			if( Nino.admin.router.isWord( parts, 0, 'new' ) === true && parts.length === 1 )
				return { view : 'new', key : null };

			if( Nino.admin.router.isWord( parts, 0, 'scan' ) === true && parts.length === 1 )
				return { view : 'scan' };

			if( Nino.admin.router.isWord( parts, 0, 'rename' ) === true )
				return parts.length === 2 && keys._renamable( parts[1] ) === true ? { view : 'new', key : parts[1] } : null;

			const group = keys._model === null ? null : Nino.admin.textKeys.resolve( keys._model, parts );

			return group === null ? null : { view : 'group', row : group.row, key : group.key };
		},

		/**
		 *	Whether a key of the list may be renamed: the names of the system and of the workbench are what the code
		 *	that reads them asks for, and only their value is edited
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{boolean}
		 */
		_renamable : function( key ) {

			const model = Nino.admin.keys._model;

			return model !== null && model.rowOf[key] !== undefined && key.indexOf('/_nino/') !== 0 && key.indexOf('/_admin/') !== 0;
		},

		/**
		 *	Show a view of the tab: the row, the form of a key or the scan
		 *
		 *	@param		{Object}		target				See _target()
		 *	@param		{boolean}		opened				The person opened it: the focus goes into it
		 *	@param		{Element}		[opener]			The control that opened it
		 *
		 *	@return		void
		 */
		_open : function( target, opened, opener ) {

			if( target.view === 'group' )
				Nino.admin.keys._openGroup( target.row, target.key, opened );
			else if( target.view === 'scan' )
				Nino.admin.keys._openScanForm( opener, opened );
			else
				Nino.admin.keys._openNewKeyForm( target.key, opener, opened );
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
		 *	row, or a key - which is in one - opens it, and so does the form that
		 *	creates, renames or scans; a hash that names nothing there is, is the
		 *	list. Leaving a form with unsaved input asks first
		 *
		 *	@param		{Array}		parts					The hash behind the tab's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const keys = Nino.admin.keys;
			const open = dc.getElementById('keys-form').classList.contains('admin-hidden') === false;
			const target = parts.length > 0 ? keys._target( parts ) : null;

			const same = target === null ? open === false
				: ( open === true && keys._view === target.view && ( target.view === 'scan' || ( target.view === 'new' ? keys._renameFrom === target.key : keys._currentGroup === target.row ) ) );

			if( same === true ) {

				// Another key of the row on screen is no move, but the field the
				// address names is the one shown and the one it keeps
				if( target !== null && target.view === 'group' && target.key !== keys._focusKey ) {
					keys._focusKey = target.key;

					if( target.key !== null )
						keys._focusField( target.key );
				}

				return false;
			}

			Nino.admin.router.leave( [ 'keys' ], open, function() {
				if( target === null ) {
					keys._destroyHtmlEditors();
					keys._showList();
					return;
				}
				keys._open( target, false );
			}, keys._showLevel );

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

		/**
		 *	The list again as it stands: the focus stays where it is, unless it
		 *	stood in the level that is hidden now (see Nino.adminUi.showLevel()).
		 *	Back goes to the control that opened the form
		 *
		 *	@param		{Element|null}	[opener]	Back: the control to give the focus to; null where there is none to name
		 *
		 *	@return		void
		 */
		_showList : function( opener ) {

			Nino.admin.keys._view = null;
			Nino.admin.router.set( 'keys', [] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'keys-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'keys-list', opener );
		},

		/**
		 *	The address of the form on screen: a row, or the form that creates, renames or scans
		 *
		 *	@return		{Array}
		 */
		_parts : function() {

			const keys = Nino.admin.keys;

			if( keys._view === 'scan' )
				return [ Nino.admin.router.word('scan') ];

			if( keys._view === 'new' )
				return keys._renameFrom === null ? [ Nino.admin.router.word('new') ] : [ Nino.admin.router.word('rename'), keys._renameFrom ];

			return Nino.admin.textKeys.hashParts( keys._currentGroup, keys._focusKey );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {
			Nino.admin.router.set( 'keys', Nino.admin.keys._parts() );
			Nino.adminUi.showLevel( 'keys-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	The control Back returns the focus to from the form that is open: the
		 *	row of the group as the list is drawn now, or the button that opened a
		 *	form of a key's own or the scan
		 *
		 *	@return		{Element|null}
		 */
		_backTarget : function() {

			if( Nino.admin.keys._view !== 'group' )
				return Nino.admin.keys._opener;

			const row = Array.from( dc.getElementById('keys-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === Nino.admin.keys._currentGroup } );
			return row === undefined ? null : row.querySelector('a');
		},

		/**
		 *	The one way back of a form: "Back to <the panel>" in the context bar, which leaves the form for the list
		 *	and puts the focus on what opened it. A form that holds unsaved input is asked about first by the shell
		 *
		 *	@param		{Element}		wrap					The level the bar stands in
		 *	@param		{string}		title					The name of the view
		 *
		 *	@return		{Element}
		 */
		_contextBar : function( wrap, title ) {

			const bar = Nino.adminUi.contextBar( {
				back : { to : Nino.content.getText('/_admin/text/label/back-name'), href : Nino.admin.router.href( 'keys', [] ), onClick : function() {
					Nino.admin.router.go( 'keys', [] );
					Nino.admin.keys._destroyHtmlEditors();
					Nino.admin.keys._showList( Nino.admin.keys._backTarget() );
				} },
				title : title,
			} );

			// What is shown and written in a language says which, where it is not the native one
			Nino.adminUi.localeBadge( Nino.admin.keys._locale(), bar );

			return bar;
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
		 *	The workbench's content language moved (Nino.admin.workLocale), and the
		 *	shell has asked about the unsaved input of this tab first: the row on
		 *	screen is drawn again in the other language, the list names its rows in it.
		 *	The other forms of the tab (a new key, the scan) have no translation to
		 *	show and read the language when they are drawn
		 *
		 *	@param		{string}	locale
		 *
		 *	@return		void
		 */
		_followLocale : function( locale ) {

			if( Nino.admin.keys._data === null )
				return;

			const form = dc.getElementById('keys-form');

			if( form.classList.contains('admin-hidden') === false && Nino.admin.keys._view === 'group' && dc.getElementById('keys-edit-form') !== null ) {

				if( locale === Nino.admin.keys._shownLocale )
					return;

				Nino.admin.keys._buildModel();
				Nino.admin.keys._renderGroupForm( {}, {} );
				return;
			}

			// The forms that make a key and scan write in the language, and say which
			const bar = form.classList.contains('admin-hidden') === false ? form.querySelector('.nino-admin-contextbar') : null;

			if( bar !== null )
				Nino.adminUi.localeBadge( locale, bar );

			Nino.admin.keys._search.shown = 0;
			Nino.admin.keys._buildModel();
			Nino.admin.keys._renderBody();
		},

		/**
		 *	The hits of the search, 50 at a time: a row for each key (see
		 *	Nino.admin.textKeys.hitList()). A hit opens the row at the key; only
		 *	the code of a language switches the language, asked for as every change of it is
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

			body.appendChild( Nino.admin.textKeys.hitList( hits.slice( 0, search.shown ), locale, {
				href : function( hit ) { return Nino.admin.router.href( 'keys', Nino.admin.textKeys.hashParts( hit.item.row.id, hit.item.field.entry.key ) ) },
				onOpen : function( hit ) {
					Nino.admin.router.go( 'keys', Nino.admin.textKeys.hashParts( hit.item.row.id, hit.item.field.entry.key ) );
					Nino.admin.keys._openGroup( hit.item.row.id, hit.item.field.entry.key, true );
				},
				onLanguage : function( code ) { Nino.admin.workLocale.choose( code ) },
			} ) );

			if( hits.length > search.shown ) {
				body.appendChild( Nino.adminUi.button( Nino.content.getText('/_admin/text/label/more'), function() {
					search.shown += step;
					Nino.admin.keys._renderBody();
				}, { kind : 'secondary' } ) );
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
		 *	Render the search, the rows and the bar of the list: the scan beside the main button that makes a new key
		 *
		 *	@return		void
		 */
		_renderCategoryList : function() {

			const wrap = dc.getElementById('keys-list');
			wrap.innerHTML = '';

			wrap.appendChild( Nino.admin.keys._renderSearchBar() );

			const status = dc.createElement('p');
			status.id = 'keys-list-status';
			status.className = 'admin-text-count';
			status.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( status );

			const body = dc.createElement('section');
			body.id = 'keys-list-body';
			wrap.appendChild( body );

			Nino.admin.keys._renderBody();

			const scanBtn = Nino.adminUi.button( Nino.content.getText('/_admin/keys/label/scan'), function() {
				Nino.admin.router.go( 'keys', [ Nino.admin.router.word('scan') ] );
				Nino.admin.keys._openScanForm( scanBtn, true );
			}, { kind : 'secondary' } );

			const addBtn = Nino.adminUi.button( Nino.content.getText('/_admin/keys/label/new'), function() {
				Nino.admin.router.go( 'keys', [ Nino.admin.router.word('new') ] );
				Nino.admin.keys._openNewKeyForm( null, addBtn, true );
			} );

			wrap.appendChild( Nino.adminUi.actionBar( { list : true, secondary : [ scanBtn ], primary : addBtn } ).el );
		},

		/**
		 *	The rows, in the blocks and groups textkeys.js made of them: each a
		 *	link that opens the row's form, and has its address
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
				part.appendChild( Nino.adminUi.sectionTitle( Nino.content.getText( '/_admin/text/group/'+ block.id ) ) );

				( block.rows !== undefined ? [ { id : block.id, rows : block.rows } ] : block.groups ).forEach( function( group ) {

					// A row opens a form and writes its address: a link, with that address
					part.appendChild( Nino.adminUi.list( group.rows.map( function( id ) {

						const row = model.rows[id];

						return {
							key : id,
							title : Nino.admin.textKeys.rowLabel( model, row, locale ),
							detail : Nino.admin.textKeys.rowSummary( row, locale ),
							sub : Nino.admin.textKeys.rowDepth( row ) > 0,
							href : Nino.admin.router.href( 'keys', Nino.admin.textKeys.hashParts( id ) ),
							onOpen : function() {
								Nino.admin.router.go( 'keys', Nino.admin.textKeys.hashParts( id ) );
								Nino.admin.keys._openGroup( id, null, true );
							},
						};
					} ), { title : block.rows === undefined && group.id !== 'common' ? Nino.content.getText( '/_admin/text/group/'+ group.id ) : '', level : 3 } ) );
				} );

				body.appendChild( part );
			} );
		},

		/**
		 *	Open a row's form, at one of its keys where one was asked for: that
		 *	field is scrolled to and marked
		 *
		 *	@param		{string}		group					The row's id
		 *	@param		{string|null}	[key]
		 *	@param		{boolean}			[opened]			The person opened it - a row, or a hit: the focus goes into the form, to the field of the key where there is one. Otherwise it is shown as it stands, by the address
		 *
		 *	@return		void
		 */
		_openGroup : function( group, key, opened ) {

			Nino.admin.keys._destroyHtmlEditors();

			Nino.admin.keys._currentGroup = group;
			Nino.admin.keys._focusKey 		= key ?? null;
			Nino.admin.keys._view 				= 'group';

			Nino.admin.keys._renderGroupForm( {}, {} );
			Nino.admin.keys._showForm( opened === true );

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

			const control = field.querySelector('textarea, input, [contenteditable="true"]');

			if( control !== null && typeof control.focus === 'function' )
				control.focus( { preventScroll : true } );
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
			Nino.admin.textKeys.fills( function( callback ) { Nino.admin.keys._apiCall( 'fills', { locale : Nino.admin.keys._view === 'group' ? Nino.admin.keys._shownLocale : Nino.admin.keys._locale() }, callback ) }, done );
		},

		/**
		 *	What a key's shape was when the form was drawn, as the controls hold it: the format ('auto' where none
		 *	was chosen), the limit as typed ('' for automatic), whether it is global and whether it is hidden
		 *
		 *	@param		{Object}	entry					Key entry
		 *
		 *	@return		{Object}									{ format, limit, global, hidden }
		 */
		_shapeOf : function( entry ) {
			return { format : entry.formatSet === true ? entry.format : 'auto', limit : entry.maxlengthSet === true ? String( entry.maxlength ) : '', global : entry.global === true, hidden : entry.blacklisted === true };
		},

		/**
		 *	The rows of one key in the form: its text, and under it what its shape is - the format, the limit, whether
		 *	it is global, whether it is hidden from the Text panel - with Rename and Delete at the end of that row. The
		 *	shape is saved with the text, by the one Save of the row
		 *
		 *	@param		{Object}		field					{ entry, label }, see Nino.admin.textKeys.sections()
		 *	@param		{Object}		carried				key -> { value, shape }: what was typed into a key that a save did not take, drawn again as it was typed
		 *
		 *	@return		{Array}										The elements of the grid
		 */
		_keyRows : function( field, carried ) {

			const keys 		= Nino.admin.keys;
			const entry 	= field.entry;
			const seq 		= ++keys._fieldSeq;
			const typed 	= carried[entry.key] ?? {};
			const text 		= function( key ) { return Nino.content.getText( key ) };
			const base 		= keys._shapeOf( entry );
			const shape 	= Object.assign( {}, base, typed.shape ?? {} );

			const value = Nino.admin.textKeys.valueField( {
				entry : entry,
				value : Nino.admin.textKeys.valueOf( entry, keys._shownLocale ),
				label : field.label,
				id : 'keys-field-'+ seq,
				writable : true,
				fills : keys._loadFills,
			} );

			// What was drawn is what is stored; what was typed is a change of it
			keys._session.add( value );

			if( typed.value !== undefined )
				value.write( typed.value );

			const row = Nino.adminUi.el( 'div', 'nino-admin-field admin-key-settings' );
			const name = Nino.adminUi.el( 'span', '', text('/_admin/keys/label/settings') );
			const controls = Nino.adminUi.el( 'div', 'admin-key-controls' );

			name.id = 'keys-settings-'+ seq;
			controls.setAttribute( 'role', 'group' );
			controls.setAttribute( 'aria-labelledby', name.id );
			row.dataset.settings = entry.key;
			name.appendChild( Nino.adminUi.fieldHelp( text('/_admin/keys/help/settings'), { name : text('/_admin/keys/label/settings') } ) );
			row.appendChild( name );

			// The format the value is kept in and the limit its counter counts to. A format that holds less than the
			// one before converts what is stored when it is saved (see _save())
			const formatField = Nino.adminUi.selectField( {
				id : 'keys-format-'+ seq,
				label : text('/_admin/keys/label/format'),
				options : [ 'auto' ].concat( keys.FORMATS ).map( function( format ) { return { value : format, label : text('/_admin/keys/format/'+ format) } } ),
				value : shape.format,
			} );
			const limitField = Nino.adminUi.textField( {
				id : 'keys-limit-'+ seq,
				label : text('/_admin/keys/label/limit'),
				type : 'number',
				value : shape.limit,
				placeholder : text('/_admin/keys/placeholder/limit'),
			} );
			const globalField = Nino.adminUi.switchField( { checked : shape.global, label : text('/_admin/common/label/global'), on : text('/_admin/common/label/on'), off : text('/_admin/common/label/off') } );
			const hiddenField = Nino.adminUi.switchField( { checked : shape.hidden, label : text('/_admin/keys/label/blacklist'), on : text('/_admin/common/label/on'), off : text('/_admin/common/label/off') } );

			limitField.querySelector('input').min = '1';
			limitField.querySelector('input').step = '1';

			// The field counts to the limit that is typed, and takes as much: a text may be made longer in the save that raises it
			limitField.querySelector('input').addEventListener( 'input', function( ev ) {
				const typedLimit = ev.target.value.trim();
				value.setLimit( /^\d+$/.test( typedLimit ) === true && Number( typedLimit ) >= 1 ? Number( typedLimit ) : entry.maxlength );
			} );

			[ formatField, limitField, globalField, hiddenField ].forEach( function( control ) { controls.appendChild( control ) } );

			// Rename and Delete are small buttons at the end of the row, the one that takes something away last
			const actions = [];

			if( keys._renamable( entry.key ) === true )
				actions.push( Nino.adminUi.button( text('/_admin/common/label/rename'), function( ev ) { keys._rename( entry.key, ev.currentTarget ) }, { kind : 'secondary', small : true, name : Nino.adminUi.format( text('/_admin/keys/label/rename-key'), entry.key ) } ) );

			actions.push( Nino.adminUi.button( text('/_admin/common/label/delete'), function() { keys._delete( entry.key ) }, { kind : 'danger', small : true, name : Nino.adminUi.format( text('/_admin/keys/label/delete-key'), entry.key ) } ) );

			controls.appendChild( Nino.adminUi.rowActions( actions ) );
			row.appendChild( controls );

			keys._settings[entry.key] = {
				base : base,
				format : formatField.querySelector('select'), formatField : formatField,
				limit : limitField.querySelector('input'), limitField : limitField,
				global : globalField.querySelector('input'), hidden : hiddenField.querySelector('input'),
				row : row,
			};

			return [ value.el, row ];
		},

		/**
		 *	What the controls of a key's shape hold now
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Object}									{ format, limit, global, hidden }
		 */
		_shapeNow : function( key ) {

			const set = Nino.admin.keys._settings[key];

			return { format : set.format.value, limit : set.limit.value.trim(), global : set.global.checked, hidden : set.hidden.checked };
		},

		/**
		 *	The keys whose shape the controls hold otherwise than it was drawn
		 *
		 *	@return		{Array<string>}
		 */
		_shapeChanged : function() {

			const keys = Nino.admin.keys;

			return Object.keys( keys._settings ).filter( function( key ) {
				const base = keys._settings[key].base;
				const now = keys._shapeNow( key );
				return now.format !== base.format || now.limit !== base.limit || now.global !== base.global || now.hidden !== base.hidden;
			} );
		},

		/**
		 *	Take every rich-text editor of the form on screen down, and the fields with it
		 *
		 *	@return		void
		 */
		_destroyHtmlEditors : function() {

			if( Nino.admin.keys._session !== null )
				Nino.admin.keys._session.destroy();

			Nino.admin.keys._session = null;
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
		 *	Open the form of a key's rename: the same form as the one that creates, filled in with the key. Leaving the
		 *	row that held the key asks about unsaved input first
		 *
		 *	@param		{string}		key
		 *	@param		{Element}		[opener]			The button that was pressed: Back returns the focus to it
		 *
		 *	@return		void
		 */
		_rename : function( key, opener ) {

			Nino.admin.keys._guard( function() {
				Nino.admin.router.go( 'keys', [ Nino.admin.router.word('rename'), key ] );
				Nino.admin.keys._openNewKeyForm( key, opener, true );
			} );
		},

		/**
		 *	Delete a key entirely, after confirmation, and draw the row again without it - or show the list, where it
		 *	was the last. A refusal stands in the line of the bar
		 *
		 *	@param		{string}	key
		 *
		 *	@return		void
		 */
		_delete : function( key ) {

			const keys = Nino.admin.keys;

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/keys/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/keys/confirm/delete'), key ),
				yes				: Nino.content.getText('/_admin/keys/confirm/delete-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					// Drawing the row again drops what is typed into it
					keys._guard( function() {

						const line = keys._status;

						keys._apiCall( 'delete', { key : key }, function( status, response ) {

							if( status !== 200 || response === null ) {
								if( line !== null )
									line.error( status, response, '/_admin/common/error/delete' );
								return;
							}

							Nino.admin.touch( 'texts' );

							// The focus goes to the key that stands where the deleted one did: the next, else the one before
							const order = keys._session === null ? [] : keys._session.keys();
							const at = order.indexOf( key );

							keys._reload( true, 'delete', order[at + 1] ?? order[at - 1] ?? null );
						} );
					} );
				},
			} );
		},

		/**
		 *	Read the keys again after something was written and draw what is on screen from them: the row that is
		 *	open, or the list where the row has no key any more. What is said as a toast is the caller's
		 *
		 *	@param		{boolean}	saved					Whether to say "Saved." or "Deleted." as a toast
		 *	@param		{string}	[what]				'delete' for "Deleted."
		 *	@param		{string|null}	[focusKey]	The key whose field takes the focus
		 *
		 *	@return		void
		 */
		_reload : function( saved, what, focusKey ) {

			const keys = Nino.admin.keys;

			keys._load( function( status, response ) {

				if( status !== 200 || response === null ) {
					keys._stay( status, response, {}, {}, {} );
					return;
				}

				keys._renderCategoryList();

				if( keys._groups[keys._currentGroup] === undefined ) {
					keys._view = null;
					keys._destroyHtmlEditors();
					keys._showList( null );
				}
				else {
					keys._renderGroupForm( {}, {} );
					keys._showForm( false );

					if( focusKey !== undefined && focusKey !== null && keys._session.get( focusKey ) !== undefined )
						keys._session.get( focusKey ).focus();
				}

				if( saved === true )
					Nino.adminUi.say( Nino.content.getText( what === 'delete' ? '/_admin/common/msg/deleted' : '/_admin/common/msg/saved' ) );
			} );
		},

		/**
		 *	Whether the tab holds input nobody has saved - what the shell asks
		 *	before it lets anything throw that away (see Nino.admin.dirty). For
		 *	a row: a text or a shape that differs from what it held when it was
		 *	drawn or last saved, which includes one whose save was refused. For
		 *	the new-key form and the scan form: anything typed or ticked
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

			if( Nino.admin.keys._view !== 'group' || Nino.admin.keys._session === null || dc.getElementById('keys-edit-form') === null )
				return false;

			return Nino.admin.keys._session.isDirty() === true || Nino.admin.keys._shapeChanged().length > 0;
		},

		/**
		 *	Throw the input away. A row goes back to what its fields held when it was drawn; the new-key
		 *	and scan forms are emptied. The form is about to be left or drawn again.
		 *	While a save runs nothing is thrown away: it finishes with what it was given
		 *
		 *	@return		void
		 */
		discard : function() {

			const keys = Nino.admin.keys;

			if( keys._saving === true )
				return;

			if( keys._view === 'new' ) {
				// Drawn again as it was opened
				keys._renderNewKeyForm();
			} else if( keys._view === 'scan' ) {
				keys._scanRows.forEach( function( row ) {
					if( row.valueInput !== null )
						row.valueInput.value = '';
					row.ignoreCheck.checked = false;
				} );
			} else if( keys._session !== null ) {
				keys._session.discard();
				Object.keys( keys._settings ).forEach( function( key ) {
					const set = keys._settings[key];
					set.format.value = set.base.format;
					set.limit.value = set.base.limit;
					set.global.checked = set.base.global;
					set.hidden.checked = set.base.hidden;
				} );
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
		 *	The action bar of the form on screen, which is where the shell puts its mark of unsaved input
		 *
		 *	@return		{Element|null}
		 */
		_bar : function() {

			const form = dc.getElementById('keys-edit-form') ?? dc.getElementById('keys-new-form') ?? dc.getElementById('keys-scan-form');

			return form === null || form === undefined ? null : form.querySelector('.nino-admin-actionbar');
		},

		/**
		 *	Render a row's form in the language the workbench edits content in: the way back and the name of the row
		 *	in the context bar, a section for each part and in it, for every key, its text and its shape
		 *	(see _keyRows()). One bar, one Save
		 *
		 *	@param		{Object}		carried				key -> { value, shape } typed into a key a save did not take, drawn as it was typed
		 *	@param		{Object}		refused				key -> { value, format, limit } the sentence at a field of a key that a save did not take
		 *
		 *	@return		void
		 */
		_renderGroupForm : function( carried, refused ) {

			const keys 		= Nino.admin.keys;
			const group 	= keys._currentGroup;
			const model 	= keys._model;
			const row 		= model.rows[group];
			const locale 	= keys._locale();

			keys._destroyHtmlEditors();
			keys._shownLocale = locale;
			keys._session = Nino.admin.textKeys.session();
			keys._settings = {};

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';

			wrap.appendChild( keys._contextBar( wrap, Nino.admin.textKeys.rowLabel( model, row, locale ) ) );

			const form = dc.createElement('form');
			form.id = 'keys-edit-form';
			form.noValidate = true;

			Nino.admin.textKeys.sectionsView( model, row, function( field ) { return keys._keyRows( field, carried ) } ).forEach( function( fieldset ) { form.appendChild( fieldset ) } );

			const msg = dc.createElement('p');
			msg.id = 'keys-form-msg';

			const bar = Nino.adminUi.actionBar( {
				status : { line : msg },
				primary : Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit' } ),
			} );

			keys._status = bar.line;
			bar.line.bind( form, keys.isDirty );
			form.appendChild( bar.el );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); keys._save() } );

			wrap.appendChild( form );

			// What a save did not take says so at its field
			keys._showRefused( refused );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	Say at its field what a save did not take: the sentence under the text, the format, the limit, or the row
		 *
		 *	@param		{Object}		refused				key -> { value, format, limit, shape } sentences
		 *
		 *	@return		void
		 */
		_showRefused : function( refused ) {

			const keys = Nino.admin.keys;

			Object.keys( refused ).forEach( function( key ) {
				const say = refused[key];
				const set = keys._settings[key];

				if( say.value !== undefined && keys._session.get( key ) !== undefined )
					keys._session.get( key ).fail( say.value );

				if( set === undefined )
					return;

				[ [ 'format', set.formatField, set.format ], [ 'limit', set.limitField, set.limit ] ].forEach( function( part ) {
					if( say[part[0]] !== undefined )
						Nino.adminUi.fieldError( part[1], part[2], 'keys-error-'+ part[2].id, say[part[0]] );
				} );

				if( say.shape !== undefined )
					Nino.adminUi.fieldError( set.row.querySelector('.admin-key-controls'), null, 'keys-error-'+ set.row.dataset.settings, say.shape );
			} );

		},

		/**
		 *	The sentence that is wrong with a limit before anything is sent, '' where it is fine: nothing is automatic,
		 *	anything else is a whole number of at least 1 (the server decides how high, and that it holds the text)
		 *
		 *	@param		{string}	limit					As typed, trimmed
		 *
		 *	@return		{string}
		 */
		_limitProblem : function( limit ) {

			if( limit === '' || ( /^\d+$/.test( limit ) === true && Number( limit ) >= 1 ) )
				return '';

			return Nino.adminUi.format( Nino.content.getText('/_admin/keys/error/invalid'), Nino.content.getText('/_admin/keys/label/limit'), Nino.content.getText('/_admin/keys/expect/limit') );
		},

		/**
		 *	Save everything of the open row that was changed, with the one Save: the shape of every key whose shape
		 *	was changed - one request each - and then the text of every key whose text was changed, in the language the
		 *	row was drawn in, in one request. What a request turns down is said at its field and stays changed; what
		 *	it took is what the row shows afterwards, read from the server, so that nothing counts as saved that is not.
		 *	A format that holds less than the one it replaces converts what is stored, and says which keys in one question first.
		 *	Only what changed is sent, so a row saved without a change leaves its files as they are
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when everything that changed was written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const keys = Nino.admin.keys;

			const report = function( ok ) {
				if( typeof Nino.admin.dirty === 'object' )
					Nino.admin.dirty.refresh();
				if( typeof done === 'function' )
					done( ok );
			};

			if( keys._saving === true || keys._session === null || keys._status === null ) {
				report( false );
				return;
			}

			const session = keys._session;
			const line 		= keys._status;
			const entries = keys._groups[keys._currentGroup] ?? [];
			const form 		= dc.getElementById('keys-edit-form');

			Nino.adminUi.clearFieldErrors( form );

			const shapeKeys = keys._shapeChanged();
			const textKeys 	= session.changed();

			// Nothing differs from what is stored: nothing is written, and what is on screen is what is stored
			if( shapeKeys.length === 0 && textKeys.length === 0 ) {
				line.saved();
				report( true );
				return;
			}

			// What is wrong is said at the field before anything is sent
			let first = null;

			shapeKeys.forEach( function( key ) {

				const set = keys._settings[key];
				const sentence = keys._limitProblem( set.limit.value.trim() );

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( set.limitField, set.limit, 'keys-error-'+ set.limit.id, sentence );
				first = first ?? set.limit;
			} );

			if( first !== null ) {
				line.fail( Nino.content.getText('/_admin/keys/error/fields') );
				first.focus();
				report( false );
				return;
			}

			const entryOf = function( key ) { return entries.find( function( e ) { return e.key === key } ) };
			const formats = keys.FORMATS;

			// What is stored is converted and cannot be converted back where a format holds less than the one it replaces, and
			// where a key becomes global, which keeps the text of the native language and drops the others: said once for
			// all of them, with what happens to each key
			const native = Nino.adminUi.locales.native;
			const converting = shapeKeys.filter( function( key ) {
				const format = keys._shapeNow( key ).format;
				return format !== 'auto' && formats.indexOf( format ) < formats.indexOf( entryOf( key ).format );
			} );
			const losing = shapeKeys.filter( function( key ) {
				const entry = entryOf( key );
				return keys._shapeNow( key ).global === true && entry.global === false && keys._droppedBy( entry ).length > 0;
			} );

			const proceed = function() { keys._send( shapeKeys, textKeys, report ) };

			if( converting.length === 0 && losing.length === 0 ) {
				proceed();
				return;
			}

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/keys/confirm/convert-title'),
				message		: Nino.content.getText('/_admin/keys/confirm/convert'),
				details		: converting.map( function( key ) {
					const format = keys._shapeNow( key ).format;
					return Nino.adminUi.format( Nino.content.getText('/_admin/keys/confirm/format-line'), key, Nino.content.getText('/_admin/keys/format/'+ entryOf( key ).format ), Nino.content.getText('/_admin/keys/format/'+ format ), Nino.content.getText('/_admin/keys/convert/'+ format ) );
				} ).concat( losing.map( function( key ) {
					return Nino.adminUi.format( Nino.content.getText('/_admin/keys/confirm/global-line'), key, keys._droppedBy( entryOf( key ) ).map( function( code ) { return Nino.adminUi.localeName( code ) } ).join(', '), Nino.adminUi.localeName( native ) );
				} ) ),
				yes				: Nino.content.getText('/_admin/keys/confirm/convert-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {
					if( ok === true )
						proceed();
					else
						report( false );
				},
			} );
		},

		/**
		 *	The languages whose text is lost when a key becomes global: the ones other than the native language that
		 *	hold a text other than the native one's - the server keeps the native language's text
		 *
		 *	@param		{Object}	entry					Key entry of a key that is one per language
		 *
		 *	@return		{Array<string>}
		 */
		_droppedBy : function( entry ) {

			const native = String( entry.values[Nino.adminUi.locales.native] ?? '' );

			return Object.keys( entry.values ).filter( function( code ) {
				const text = String( entry.values[code] ?? '' );
				return code !== Nino.adminUi.locales.native && text.trim() !== '' && text !== native;
			} );
		},

		/**
		 *	The requests of a save of a row: the shape of each key whose shape changed, one after the other - a refusal
		 *	does not stop the others -, then the text of every key whose text changed and whose shape was taken, in
		 *	one request. Then the keys are read again and the row is drawn from them, with what a request turned down as
		 *	it was typed and its sentence at the field
		 *
		 *	@param		{Array}			shapeKeys			The keys whose shape changed
		 *	@param		{Array}			textKeys			The keys whose text changed
		 *	@param		{Function}	report				Called once with true when everything was written, false otherwise
		 *
		 *	@return		void
		 */
		_send : function( shapeKeys, textKeys, report ) {

			const keys 		= Nino.admin.keys;
			const session = keys._session;
			const line 		= keys._status;
			const locale 	= keys._shownLocale;
			const entries = keys._groups[keys._currentGroup] ?? [];
			const typed 	= {};

			// What was typed, as the row is drawn again from it where a request turns it down
			session.keys().forEach( function( key ) { typed[key] = { value : session.get( key ).read(), shape : keys._shapeNow( key ) } } );

			const refused = {};
			const refuse = function( key, field, sentence ) {
				refused[key] = refused[key] ?? {};
				refused[key][field] = sentence;
			};
			const written = {};
			const conflicts = [];
			const queue = shapeKeys.slice();

			// A text that is made shorter in the save that lowers the limit is written first, or the server would refuse a
			// limit under the text the key holds; every other text follows the shape, which says where it is written
			const early = textKeys.filter( function( key ) {
				const entry = entries.find( function( e ) { return e.key === key } );
				const now = typed[key].shape;
				return shapeKeys.indexOf( key ) !== -1 && now.limit !== '' && Number( now.limit ) < entry.maxlength && now.global === entry.global;
			} );

			keys._saving = true;
			keys._pending( true );
			line.saving();

			const finish = function() {

				keys._load( function( status, response ) {

					keys._saving = false;

					if( status !== 200 || response === null ) {
						keys._stay( status, response, typed, refused, written );
						report( false );
						return;
					}

					// What a request turned down is drawn as it was typed: the text, and the shape where that was what was refused
					const carried = {};

					Object.keys( refused ).forEach( function( key ) {
						const shape = refused[key].shape !== undefined || refused[key].format !== undefined || refused[key].limit !== undefined;
						carried[key] = shape === true ? { value : typed[key].value, shape : typed[key].shape } : { value : typed[key].value };
					} );

					keys._renderCategoryList();

					const failed = Object.keys( refused ).length;

					if( keys._groups[keys._currentGroup] === undefined ) {
						keys._view = null;
						keys._showList( null );
					}
					else {
						keys._renderGroupForm( carried, refused );
						keys._showForm( false );
						keys._focusAfterSave( failed > 0 ? refused : null );
					}

					if( failed > 0 ) {
						if( keys._status !== null )
							keys._status.fail( Nino.adminUi.format( Nino.content.getText( failed === 1 ? '/_admin/keys/error/one' : '/_admin/keys/error/some' ), failed ) );

						// Another tab or person wrote a key since it was read: the texts stay as they were typed, the question is the kernel's
						if( conflicts.length > 0 )
							keys._askConflict( conflicts );

						report( false );
						return;
					}

					if( keys._status !== null )
						keys._status.saved();

					report( true );
				} );
			};

			const writeTexts = function( wanted, next ) {

				const items = wanted.filter( function( key ) { return refused[key] === undefined } ).map( function( key ) {

					const global = keys._settings[key] === undefined ? false : keys._shapeNow( key ).global;
					const item = { key : key, locale : global === true ? '*' : locale, value : typed[key].value };

					// The text it was read as, so that a text another tab wrote since is not written over unseen - not for a key whose shape
					// is changed in this very save, which moves the text where it was read from
					if( shapeKeys.indexOf( key ) === -1 )
						item.was = keys._stamps[item.locale+ ' '+ key] ?? Nino.admin.textKeys.valueOf( entries.find( function( e ) { return e.key === key } ), locale );

					return item;
				} );

				if( items.length === 0 ) {
					next();
					return;
				}

				keys._apiCall( 'savebatch', { items : items }, function( status, response ) {

					if( status !== 200 || response === null ) {
						items.forEach( function( item ) {
							refuse( item.key, 'value', Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
						} );
						next();
						return;
					}

					Nino.admin.touch( 'texts' );

					const results = response.results ?? {};

					items.forEach( function( item ) {

						if( results[item.key] !== undefined && results[item.key].error === 'changed' ) {
							conflicts.push( item );
							if( item.was !== undefined )
								keys._stamps[item.locale+ ' '+ item.key] = item.was;
							refuse( item.key, 'value', Nino.adminUi.format( Nino.content.getText('/_admin/text/error/changed'), item.key ) );
						}
						else if( results[item.key] === undefined || results[item.key].ok !== true )
							refuse( item.key, 'value', Nino.content.getText('/_admin/text/error/key') );
						else {
							written[item.key] = true;
							delete keys._stamps[item.locale+ ' '+ item.key];
						}
					} );

					next();
				} );
			};

			const writeShape = function() {

				if( queue.length === 0 ) {
					writeTexts( textKeys.filter( function( key ) { return early.indexOf( key ) === -1 } ), finish );
					return;
				}

				const key = queue.shift();
				const now = typed[key].shape;

				keys._apiCall( 'save', { key : key, global : now.global, blacklisted : now.hidden, format : now.format, maxlength : now.limit === '' ? null : Number( now.limit ) }, function( status, response ) {

					if( status !== 200 || response === null ) {

						const sentence = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
						const named = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';

						refuse( key, named === 'format' || named === 'maxlength' ? ( named === 'format' ? 'format' : 'limit' ) : 'shape', sentence );
					}
					else {
						Nino.admin.touch( 'texts' );
						written[key+ '/shape'] = true;
					}

					writeShape();
				} );
			};

			writeTexts( early, writeShape );
		},

		/**
		 *	The question about keys that another tab or person wrote since this row was read (see Nino.admin.textKeys.conflict()):
		 *	Overwrite writes the typed texts over what is stored, Reload shows what is stored, Cancel leaves them as typed
		 *
		 *	@param		{Array}		conflicts				The items the server turned down as changed
		 *
		 *	@return		void
		 */
		_askConflict : function( conflicts ) {

			const keys = Nino.admin.keys;

			Nino.admin.textKeys.conflict( conflicts, {
				model			: keys._model,
				opener		: dc.getElementById('keys-edit-form').querySelector('.nino-admin-btn-primary'),
				onOverwrite : function( forced ) {

					keys._apiCall( 'savebatch', { items : forced }, function( status, response ) {

						const results = status === 200 && response !== null ? ( response.results ?? {} ) : {};

						if( forced.some( function( item ) { return results[item.key] === undefined || results[item.key].ok !== true } ) === true ) {
							keys._status.error( status, response, '/_admin/common/error/save' );
							return;
						}

						Nino.admin.touch( 'texts' );
						keys._stamps = {};
						keys._reload( true, undefined, conflicts[0].key );
					} );
				},
				onReload	: function() {
					keys._stamps = {};
					keys._reload( false, undefined, conflicts[0].key );
				},
				// The text stays as it was typed, at its field, and the next save asks again
				onCancel	: function() { keys._session.get( conflicts[0].key ).focus() },
			} );
		},

		/**
		 *	Where the focus goes when the row was drawn again after a save: to the first field a request turned down, else
		 *	to the main button
		 *
		 *	@param		{Object|null}	refused			See _renderGroupForm(), null where nothing was refused
		 *
		 *	@return		void
		 */
		_focusAfterSave : function( refused ) {

			const keys = Nino.admin.keys;
			const first = refused === null ? undefined : keys._session.keys().find( function( key ) { return refused[key] !== undefined } );

			if( first === undefined ) {
				const main = dc.getElementById('keys-edit-form').querySelector('.nino-admin-btn-primary');
				if( main !== null )
					main.focus();
				return;
			}

			const say = refused[first];

			if( say.value !== undefined )
				keys._session.get( first ).focus();
			else
				( say.limit !== undefined || say.shape !== undefined ? keys._settings[first].limit : keys._settings[first].format ).focus();
		},

		/**
		 *	The save was written and the keys could not be read again: the form stays as it is, the line says that it
		 *	was saved and not read again, what was written counts as saved and what was turned down stays unsaved at its
		 *	field, and a button reads the keys anew
		 *
		 *	@param		{number}		status			What the read answered
		 *	@param		{*}					response
		 *	@param		{Object}		typed				key -> { value, shape } as it was typed
		 *	@param		{Object}		refused			key -> the sentences at its fields
		 *	@param		{Object}		written			key -> true for a text that was written, key/shape for a shape
		 *
		 *	@return		void
		 */
		_stay : function( status, response, typed, refused, written ) {

			const keys = Nino.admin.keys;
			const form = dc.getElementById('keys-edit-form');

			keys._pending( false );

			Object.keys( typed ).forEach( function( key ) {

				if( written[key] === true )
					keys._session.capture( key, typed[key].value );

				if( written[key+ '/shape'] === true )
					keys._settings[key].base = typed[key].shape;
			} );

			keys._showRefused( refused );

			const bar = form.querySelector('.nino-admin-actionbar');
			if( dc.getElementById('keys-reload') === null )
				bar.insertBefore( Nino.adminUi.button( Nino.content.getText('/_admin/keys/label/reload'), function() {
					keys._guard( function() { keys._reload( false ) } );
				}, { kind : 'secondary', id : 'keys-reload' } ), bar.querySelector('.nino-admin-btn-primary') );
			keys._status.fail( Nino.content.getText('/_admin/keys/error/reload') );
			keys._focusAfterSave( Object.keys( refused ).length > 0 ? refused : null );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	Keep controls stable while the requests of a save run: a field changed in the middle of them would be a
		 *	change that nothing says whether it was saved
		 *
		 *	@param		{boolean}	pending
		 *
		 *	@return		void
		 */
		_pending : function( pending ) {

			const form = dc.getElementById('keys-edit-form');

			if( form === null )
				return;

			form.setAttribute( 'aria-busy', pending === true ? 'true' : 'false' );
			form.querySelectorAll('input, textarea, select, button').forEach( function( el ) { el.disabled = pending } );
			form.querySelectorAll('[contenteditable]').forEach( function( el ) { el.contentEditable = pending === true ? 'false' : 'true' } );
		},

		/**
		 *	Open the form that creates a key - or, given a key, renames it. Both
		 *	ask for the four segments of the key, each in a field of its own,
		 *	/<namespace>/<category>/<part>/<name>; a rename is that form filled in
		 *	with the key's segments
		 *
		 *	@param		{string|null}	[fromKey]			The key to rename
		 *	@param		{Element}			[opener]			The button that was pressed: Back returns the focus to it
		 *	@param		{boolean}			[opened]			The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_openNewKeyForm : function( fromKey, opener, opened ) {

			Nino.admin.keys._destroyHtmlEditors();
			Nino.admin.keys._renameFrom = typeof fromKey === 'string' ? fromKey : null;
			Nino.admin.keys._opener = opener ?? null;
			Nino.admin.keys._view = 'new';
			Nino.admin.keys._renderNewKeyForm();
			Nino.admin.keys._showForm( opened === true );
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
		 *	Render the form that creates a key or renames one: a field for each segment, and under them the key
		 *	they make and the name it will be read by. The namespace is a choice that offers /project to begin
		 *	with - the others, which belong to a template, a feature or a module, after "Unlock", or at once for a key
		 *	that is in one; the category is a choice from what there is where it is a template, a feature or a module,
		 *	and a field with suggestions for /project. What is wrong is said at the field when the form is sent,
		 *	and a segment that is no word of the vocabulary is said as it is typed, which it may be all the same
		 *
		 *	@return		void
		 */
		_renderNewKeyForm : function() {

			const keys 		= Nino.admin.keys;
			const from 		= keys._renameFrom;
			const initial = from === null ? { namespace : 'project', category : '', part : '', name : '' } : keys._segmentsOf( from );
			const text 		= function( key ) { return Nino.content.getText( key ) };

			keys._unlocked = initial.namespace !== 'project';
			keys._formInitial = JSON.stringify( initial );

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';
			wrap.appendChild( keys._contextBar( wrap, text( from === null ? '/_admin/keys/label/new' : '/_admin/keys/label/rename-title' ) ) );

			const form = dc.createElement('form');
			form.id = 'keys-new-form';
			form.noValidate = true;

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = text( from === null ? '/_admin/keys/label/new' : '/_admin/keys/label/rename-title' );
			fieldset.appendChild( legend );
			form.appendChild( fieldset );

			// The namespace: one choice at first, all of them after the unlock
			const namespaceField = Nino.adminUi.selectField( {
				id : 'keys-form-namespace',
				label : text('/_admin/keys/label/namespace'),
				options : [ { value : initial.namespace, label : '/'+ initial.namespace } ],
				value : initial.namespace,
			} );
			const namespace = namespaceField.querySelector('select');

			const fillNamespaces = function( chosen ) {
				namespace.innerHTML = '';
				keys.NAMESPACES.filter( function( name ) { return name === 'project' || keys._unlocked === true } ).forEach( function( name ) {
					const option = dc.createElement('option');
					option.value = name;
					option.textContent = '/'+ name;
					option.selected = name === chosen;
					namespace.appendChild( option );
				} );
				namespace.value = chosen;
			};

			fillNamespaces( initial.namespace );
			fieldset.appendChild( namespaceField );

			const unlockField = Nino.adminUi.switchField( {
				checked : keys._unlocked,
				label : text('/_admin/keys/label/unlock'),
				help : text('/_admin/keys/hint/unlock'),
				on : text('/_admin/common/label/on'),
				off : text('/_admin/common/label/off'),
			} );
			const unlock = unlockField.querySelector('input');
			unlock.addEventListener( 'change', function() {
				keys._unlocked = unlock.checked;
				fillNamespaces( keys._unlocked === true ? namespace.value : 'project' );
				fillCategory( '' );
				refresh();
			} );
			fieldset.appendChild( unlockField );

			// The category: a choice where there is a list to choose from, a field
			// with suggestions where the project may name its own
			const segmentHelp = text('/_admin/keys/hint/segment');
			let categoryField = null;
			let category = null;

			const fillCategory = function( chosen ) {

				const offered = ( keys._categories[namespace.value] ?? [] ).slice();
				const old = dc.getElementById('keys-form-categories');

				if( old !== null && old !== undefined )
					old.remove();

				let field;

				if( namespace.value === 'project' ) {
					field = Nino.adminUi.textField( { id : 'keys-form-category', label : text('/_admin/keys/label/category'), help : segmentHelp, value : chosen, required : true, autocomplete : 'off', placeholder : 'company' } );
					const input = field.querySelector('input');
					input.setAttribute( 'list', 'keys-form-categories' );
					input.spellcheck = false;
					const list = dc.createElement('datalist');
					list.id = 'keys-form-categories';
					offered.forEach( function( name ) {
						const option = dc.createElement('option');
						option.value = name;
						list.appendChild( option );
					} );
					form.appendChild( list );
				} else {
					// A category the key has that is not on offer any more (a feature
					// that was taken out) stays choosable, so a rename can keep it
					if( chosen !== '' && offered.indexOf( chosen ) === -1 )
						offered.unshift( chosen );

					field = Nino.adminUi.selectField( {
						id : 'keys-form-category',
						label : text('/_admin/keys/label/category'),
						help : segmentHelp,
						options : offered.map( function( name ) { return { value : name, label : name } } ),
						value : chosen === '' && offered.length > 0 ? offered[0] : chosen,
					} );
					field.querySelector('select').setAttribute( 'aria-required', 'true' );
				}

				category = field.querySelector('input, select');
				category.addEventListener( 'input', refresh );
				category.addEventListener( 'change', refresh );

				if( categoryField === null )
					fieldset.appendChild( field );
				else
					fieldset.replaceChild( field, categoryField );

				categoryField = field;
			};

			fillCategory( initial.category );

			const segment = function( id, labelKey, value, placeholder ) {
				const field = Nino.adminUi.textField( { id : id, label : text( labelKey ), help : segmentHelp, value : value, required : true, autocomplete : 'off', placeholder : placeholder } );
				field.querySelector('input').spellcheck = false;
				field.querySelector('input').addEventListener( 'input', refresh );
				fieldset.appendChild( field );
				return field;
			};

			const partField = segment( 'keys-form-part', '/_admin/keys/label/part', initial.part, 'contact' );
			const nameField = segment( 'keys-form-name', '/_admin/keys/label/name', initial.name, 'phone' );

			// The key the four make and the name it is read by, said as they are typed
			const composedField = Nino.adminUi.el( 'div', 'nino-admin-field' );
			const composedName = Nino.adminUi.el( 'span', '', text('/_admin/keys/label/key') );
			const composedBody = Nino.adminUi.el( 'div', 'admin-key-composed' );
			const composedKey = Nino.adminUi.el( 'code', '' );
			const shown = Nino.adminUi.el( 'small', 'admin-key-note' );

			composedKey.id = 'keys-form-preview';
			shown.id = 'keys-form-shown';
			composedBody.setAttribute( 'aria-live', 'polite' );
			composedBody.appendChild( composedKey );
			composedBody.appendChild( shown );
			composedField.appendChild( composedName );
			composedField.appendChild( composedBody );
			fieldset.appendChild( composedField );

			if( from === null ) {

				const globalField = Nino.adminUi.switchField( {
					label : text('/_admin/common/label/global'),
					help : text('/_admin/keys/help/global'),
					on : text('/_admin/common/label/on'),
					off : text('/_admin/common/label/off'),
				} );
				globalField.querySelector('input').id = 'keys-form-new-global';
				fieldset.appendChild( globalField );

				fieldset.appendChild( Nino.adminUi.textareaField( {
					id : 'keys-form-new-value',
					label : text('/_admin/keys/label/initial'),
					help : text('/_admin/keys/help/initial'),
					rows : 2,
					fills : { editable : true, load : keys._loadFills },
				} ) );
			}

			const msg = dc.createElement('p');
			msg.id = 'keys-form-msg';
			const bar = Nino.adminUi.actionBar( {
				status : { line : msg, fresh : true },
				primary : Nino.adminUi.button( text( from === null ? '/_admin/keys/label/create' : '/_admin/keys/label/rename-title' ), null, { type : 'submit' } ),
			} );

			keys._status = bar.line;
			bar.line.bind( form, keys.isDirty );
			form.appendChild( bar.el );

			// What the fields say now: the key and its name, and which segment is no word of the vocabulary - which
			// it may be all the same
			function refresh() {

				const segments = keys._readSegments();

				composedKey.textContent = keys._composeKey( segments );

				const valid = keys.SEGMENT.test( segments.part ) === true && keys.SEGMENT.test( segments.name ) === true;
				shown.textContent = valid === true ? text('/_admin/keys/label/shown-as')+ ' '+ Nino.adminUi.slugLabel( segments.part )+ ' › '+ Nino.adminUi.slugLabel( segments.name ) : '';

				[ [ categoryField, segments.category ], [ partField, segments.part ], [ nameField, segments.name ] ].forEach( function( pair ) {

					const old = pair[0].querySelector('.admin-key-note');

					if( old !== null )
						old.remove();

					// A slug of a key that is no word still saves: it is said what it will read like
					if( pair[1] !== '' && keys.SEGMENT.test( pair[1] ) === true && Nino.adminUi.isWord( pair[1] ) === false )
						pair[0].appendChild( Nino.adminUi.el( 'small', 'admin-key-note', Nino.adminUi.format( text('/_admin/keys/hint/not-a-word'), pair[1], Nino.adminUi.slugLabel( pair[1] ) ) ) );
				} );
			}

			namespace.addEventListener( 'change', function() { fillCategory( '' ); refresh() } );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); keys._saveNewKey() } );

			wrap.appendChild( form );

			refresh();

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	Say what is wrong with the segments of the form before anything is sent - the sentence under each field,
		 *	the line of the bar for all of them - and take the focus to the first. A key that is not changed by a
		 *	rename is no rename
		 *
		 *	@param		{Object}		line					The status line of the form's bar
		 *
		 *	@return		{boolean}									Whether the form is fine
		 */
		_checkSegments : function( line ) {

			const keys = Nino.admin.keys;
			const segments = keys._readSegments();
			let first = null;

			Nino.adminUi.clearFieldErrors( dc.getElementById('keys-new-form') );

			[ [ 'keys-form-category', segments.category, '/_admin/keys/label/category' ], [ 'keys-form-part', segments.part, '/_admin/keys/label/part' ], [ 'keys-form-name', segments.name, '/_admin/keys/label/name' ] ].forEach( function( rule ) {

				const input = dc.getElementById( rule[0] );
				const name = Nino.content.getText( rule[2] );
				let sentence = '';

				if( rule[1] === '' )
					sentence = Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), name );
				else if( keys.SEGMENT.test( rule[1] ) === false )
					sentence = Nino.adminUi.format( Nino.content.getText('/_admin/keys/error/invalid'), name, Nino.content.getText('/_admin/keys/expect/segment') );

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( input.closest('label'), input, 'keys-error-'+ rule[0], sentence );
				first = first ?? input;
			} );

			if( first !== null ) {
				line.fail( Nino.content.getText('/_admin/keys/error/fields') );
				first.focus();
				return false;
			}

			if( keys._renameFrom !== null && keys._composeKey( segments ) === keys._renameFrom ) {
				line.fail( Nino.content.getText('/_admin/keys/error/unchanged') );
				dc.getElementById('keys-form-name').focus();
				return false;
			}

			return true;
		},

		/**
		 *	Create the key the form holds - or rename the key it was opened for
		 *	to it. The server decides the segments again, and what it refuses stands at the field it names
		 *
		 *	@param		{Function}	[done]				Called once with true when the key was created or renamed, false otherwise
		 *
		 *	@return		void
		 */
		_saveNewKey : function( done ) {

			const keys = Nino.admin.keys;

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = keys._status;

			if( keys._checkSegments( line ) === false ) {
				report( false );
				return;
			}

			const key 		= keys._composeKey( keys._readSegments() );
			const from 		= keys._renameFrom;

			const answered = function( status, response ) {

				if( status !== 200 || response === null ) {

					line.error( status, response, from === null ? '/_admin/common/error/save' : '/_admin/common/error/rename' );

					// A key the server refuses is refused at the last segment, which is where the name of it ends
					const named = response !== null && typeof response === 'object' ? response.field : '';

					if( named === 'key' || named === 'newKey' ) {
						const input = dc.getElementById('keys-form-name');
						Nino.adminUi.fieldError( input.closest('label'), input, 'keys-error-keys-form-name', Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
						input.focus();
					}

					report( false );
					return;
				}

				Nino.admin.touch( 'texts' );
				// A key of a template is one the rows of that template did not have
				if( key.indexOf('/template/') === 0 )
					Nino.admin.touch( 'templates' );

				// The key has its own place now: its row is open at it, at the address it has
				keys._load( function( loadStatus, loadResponse ) {

					if( loadStatus !== 200 || loadResponse === null ) {
						// The key exists: only the list failed, and a Save that is reported as failed would be tried again
						keys._showError( dc.getElementById('keys-form'), loadStatus, loadResponse );
						report( true );
						return;
					}

					keys._renderCategoryList();

					const row = keys._model.rowOf[key];

					if( row === undefined ) {
						keys._showList();
					}
					else {
						Nino.admin.router.go( 'keys', Nino.admin.textKeys.hashParts( row, key ) );
						keys._openGroup( row, key, true );
					}

					Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/saved') );
					report( true );
				} );
			};

			if( from !== null ) {

				// Templates may still call the key by its old name: renaming an identifier is asked about (a key of the system is not renamed here)
				const asked = Nino.adminUi.confirm( 'big', {
					title		: Nino.content.getText('/_admin/keys/confirm/rename-title'),
					message	: Nino.adminUi.format( Nino.content.getText('/_admin/keys/confirm/rename'), from, key ),
					yes			: Nino.content.getText('/_admin/keys/confirm/rename-title'),
					no			: Nino.content.getText('/_admin/common/label/cancel'),
					opener	: dc.getElementById('keys-form-name'),
					onChoose : function( ok ) {
						if( ok !== true ) {
							report( false );
							return;
						}

						line.saving();
						keys._apiCall( 'rename', { key : from, newKey : key }, answered );
					},
				} );

				if( asked === false )
					report( false );

				return;
			}

			// The text is written in the language this tab shows - said in the request, since another window of the same session may have moved the session's own
			const value = dc.getElementById('keys-form-new-value').value;
			line.saving();
			keys._apiCall( 'create', { key : key, global : dc.getElementById('keys-form-new-global').checked, value : value, values : { [ keys._locale() ] : value } }, answered );
		},

		/**
		 *	Open the "missing keys found in templates" scan form - see Keys::apiScan(). The form is the view while the
		 *	scan runs: its sentence says so, and the rows come in its place
		 *
		 *	@param		{Element}		[opener]			The button that was pressed: Back returns the focus to it
		 *	@param		{boolean}		[opened]			The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_openScanForm : function( opener, opened ) {

			const keys = Nino.admin.keys;

			keys._destroyHtmlEditors();
			keys._view = 'scan';
			keys._opener = opener ?? null;
			keys._scanRows = [];

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';
			wrap.appendChild( keys._contextBar( wrap, Nino.content.getText('/_admin/keys/label/scan') ) );

			const loading = Nino.adminUi.el( 'p', 'nino-admin-empty', Nino.content.getText('/_admin/common/msg/scanning') );
			loading.id = 'keys-scan-msg';
			loading.setAttribute( 'aria-busy', 'true' );
			wrap.appendChild( loading );

			keys._showForm( opened === true );

			keys._apiCall( 'scan', {}, function( status, response ) {

				// Left in the meantime: the answer is for a form that is not on screen any more
				if( keys._view !== 'scan' )
					return;

				if( status !== 200 || response === null ) {
					keys._showError( wrap, status, response );
					return;
				}

				keys._renderScanForm( response.missing, response.alsoUsed || [] );
			} );
		},

		/**
		 *	Render the scan results: for every missing key a card with a starting value and an "Ignore permanently"
		 *	tick, and a bar that hands the whole list to keys/scanapply.
		 *
		 *	A row says what can be done with its key (see Keys::_scanRow()): one
		 *	that follows the grammar has the input, and a /feature or /module
		 *	key a note on where it normally comes from; one that does not has no
		 *	input - it is renamed in the template - and can be ignored; a key of
		 *	the system has neither, and names who writes it. Below the rows, the
		 *	keys other templates read as well are listed, as a note and no more.
		 *
		 *	Each row has three possible answers, and doing nothing is one of
		 *	them: a value creates the key (one starting value, written in the language the
		 *	workbench edits content in, to be translated later - see the Translations
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

			const keys = Nino.admin.keys;
			const wrap = dc.getElementById('keys-form');
			const text = function( key ) { return Nino.content.getText( key ) };

			wrap.innerHTML = '';
			wrap.appendChild( keys._contextBar( wrap, text('/_admin/keys/label/scan') ) );

			const form = dc.createElement('form');
			form.id = 'keys-scan-form';
			form.noValidate = true;

			const rows = [];

			// What the scan shows and does not take - where a key is read, what it is - is a line at the height of a name, as at a field
			const shows = function( card, labelKey, words ) {
				const line = Nino.adminUi.el( 'div', 'nino-admin-field' );
				line.appendChild( Nino.adminUi.el( 'span', '', text( labelKey ) ) );
				line.appendChild( Nino.adminUi.el( 'p', 'admin-keys-scan-line', words ) );
				card.appendChild( line );
			};

			if( missing.length === 0 ) {
				form.appendChild( Nino.adminUi.emptyState( text('/_admin/keys/scan/none') ) );
				keys._status = null;
			}

			missing.forEach( function( item ) {

				const kind = item.kind || 'create';
				const card = dc.createElement('fieldset');
				card.className = 'admin-keys-scan-row';
				card.appendChild( Nino.adminUi.el( 'legend', '', item.key ) );

				shows( card, '/_admin/keys/label/scan-files', item.files.join(', ') );

				let valueInput = null;

				if( kind === 'create' ) {
					const field = Nino.adminUi.textField( { id : 'keys-scan-value-'+ rows.length, label : text('/_admin/keys/label/scan-value'), help : text('/_admin/keys/help/scan-value'), autocomplete : 'off' } );
					valueInput = field.querySelector('input');
					card.appendChild( field );
				}

				const note = keys._scanNote( item );

				if( note !== '' )
					shows( card, '/_admin/keys/label/scan-note', note );

				// A key of the system is not the scan's to retire: it is the
				// system's to write, and ignoring it would only hide the gap
				if( kind !== 'system' ) {
					const ignore = Nino.adminUi.checkField( { id : 'keys-scan-ignore-'+ rows.length, label : text('/_admin/keys/label/scan-ignore'), help : text('/_admin/keys/help/scan-ignore') } );
					card.appendChild( ignore );
					rows.push( { key : item.key, valueInput : valueInput, ignoreCheck : ignore.querySelector('input') } );
				}

				form.appendChild( card );
			} );

			// The keys another template reads as well: a note, with no input and nothing counted
			if( alsoUsed.length > 0 ) {

				const also = dc.createElement('fieldset');
				const legend = dc.createElement('legend');
				legend.textContent = text('/_admin/keys/scan/also-title');
				also.appendChild( legend );
				Nino.adminUi.sectionHelp( legend, also, text('/_admin/keys/scan/also-hint') );

				const ul = dc.createElement('ul');
				ul.className = 'nino-admin-list-dense';
				alsoUsed.forEach( function( item ) {
					ul.appendChild( Nino.adminUi.el( 'li', '', Nino.adminUi.format( text('/_admin/keys/scan/also-line'), item.key, item.files.join(', ') ) ) );
				} );
				also.appendChild( ul );

				form.appendChild( also );
			}

			if( missing.length > 0 ) {

				const msg = dc.createElement('p');
				msg.id = 'keys-scan-msg';
				const bar = Nino.adminUi.actionBar( {
					status : { line : msg, fresh : true },
					primary : Nino.adminUi.button( text('/_admin/common/label/save'), null, { type : 'submit' } ),
				} );

				keys._status = bar.line;
				bar.line.bind( form, keys.isDirty );
				form.appendChild( bar.el );
				form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); keys._saveScanResults( rows ) } );
			}

			wrap.appendChild( form );

			keys._scanRows = rows;

			// The form that stood while the scan ran had the focus, on its back link: what replaces it keeps it there
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
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
		 *	Hand the whole scan form to keys/scanapply in one request and say
		 *	what came of it.
		 *
		 *	One call rather than a create per row: the three outcomes are one
		 *	decision per key and the server applies them together, so a row
		 *	that fails cannot leave the list half-answered - and the summary
		 *	is a single sentence as a toast.
		 *
		 *	@param		{Array}		rows					[ { key, valueInput, ignoreCheck }, ... ]
		 *	@param		{Function}	[done]			Called once with true when the rows were applied, false otherwise
		 *
		 *	@return		void
		 */
		_saveScanResults : function( rows, done ) {

			const keys = Nino.admin.keys;

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = keys._status;

			if( line !== null )
				line.saving();

			const payload = rows.map( function( row ) {
				return { key : row.key, value : row.valueInput === null ? '' : row.valueInput.value, ignore : row.ignoreCheck.checked };
			} );

			// The values are written in the language this tab shows, said in the request (see the new key above)
			keys._apiCall( 'scanapply', { rows : payload, locale : keys._locale() }, function( status, response ) {

				if( status !== 200 || response === null ) {
					if( line !== null )
						line.error( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				Nino.admin.touch( 'texts' );
				// What the scan creates are the keys the templates read: a template
				// with one is a row the Text panel did not have
				if( response.created > 0 && payload.some( function( row ) { return row.ignore === false && row.key.indexOf('/template/') === 0 } ) === true )
					Nino.admin.touch( 'templates' );

				// Back to the list, which reloads from the server: the keys
				// just created belong in their categories, and a retired one
				// belongs in the list as a hidden key that can be brought back
				keys._view = null;
				keys._load( function( loadStatus, loadResponse ) {

					if( loadStatus !== 200 || loadResponse === null ) {
						keys._showError( dc.getElementById('keys-form'), loadStatus, loadResponse );
						report( true );
						return;
					}

					keys._renderCategoryList();
					Nino.admin.router.go( 'keys', [] );
					keys._showList();

					Nino.adminUi.say( Nino.content.getText('/_admin/keys/scan/result')
						.replace( '%c', String( response.created ) )
						.replace( '%i', String( response.ignored ) )
						.replace( '%s', String( response.skipped ) ) );

					report( true );
				} );
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.keys.init );

	// The language moves for this tab whoever moved it (see Nino.admin.workLocale)
	Nino.admin.workLocale.follow( 'keys', Nino.admin.keys._followLocale );

	// The shell asks before anything throws the tab's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'keys', {
			isDirty : Nino.admin.keys.isDirty,
			save		: Nino.admin.keys._saveOpen,
			discard : Nino.admin.keys.discard,
			saving	: function() { return Nino.admin.keys._saving === true },
			bar			: Nino.admin.keys._bar,
		} );

})(window, document, document.documentElement, document.body);
