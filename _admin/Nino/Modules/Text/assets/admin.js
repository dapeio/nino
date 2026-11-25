/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js									Admin "Text" panel: find a text - by what it says, what it
 *													is called or its key - or browse the rows the keys fall
 *													into (pages first, then the project, the common words,
 *													building blocks, modules, features). A row of the list
 *													says how many texts it holds and how many have none in the
 *													working language. A row opens as
 *													the standard directory of its texts
 *													(Nino.adminUi.directory()): the name and the key, the
 *													section, the text of the working language, the characters
 *													against the limit and a badge for each language - in the
 *													order the templates read them, which is the content, so
 *													nothing sorts. ONE text is edited in the record over it
 *													(Nino.adminUi.record(), #text/text/<row>/<key>): the
 *													working language first, the other languages below it, all
 *													of them editable, and a save writes the languages that
 *													were changed, one request each. The language the texts
 *													are shown in is the workbench's own (Nino.admin.workLocale).
 *													How keys become rows, sections and names is textkeys.js.
 *													The set of keys is developer-owned (see
 *													/text/blacklist.php) - this only ever edits existing key
 *													values, no create/delete.
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
		// The ones of them the website does not serve yet
		_inactive				: [],
		// What the server sent (see \Nino\Modules\Text\Admin::apiKeys()) and the
		// rows textkeys.js made of it: _groups is row id -> its keys, _model
		// the rows, blocks and where each key sits
		_data						: null,
		_model					: null,
		_groups					: {},
		// The row whose texts are on screen - its id is the part of a key it stands
		// for, eg. 'template/page-home' - null where the list is
		_group					: null,
		// What the search box holds, whether the list is cut down to the keys
		// with no text in the language, and how many hits are drawn
		_search					: { query : '', emptyOnly : false, shown : 0 },
		// The directory of the texts of the open row, what the person chose in
		// it for each row (kept while the panel lives), and the record over it
		_directory			: null,
		_states					: {},
		_record					: null,
		// What the body of the record on screen was given: the fields of the languages, kept in ctx.view, and the context of the record
		_view						: null,
		_ctx						: null,
		_fieldSeq				: 0,
		// A move of the person's own: the level it leads to takes the focus
		_moved					: false,
		_loading				: false,
		_ready					: false,
		// The stamp of the templates (see Nino.admin.touch()) the keys were asked
		// for: the rows of the templates are made from what the answer held
		_stamp					: 0,

		/**
		 *	Load every editable key, sort them into rows and render the list - or,
		 *	where the address names a row or a text, open it
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

				Nino.admin.text._take( response );
				Nino.admin.text._renderList();
				Nino.admin.text._ready 	= true;
				Nino.admin.text._stamp 	= stamp;

				if( Nino.admin.text._record === null )
					Nino.admin.text._record = Nino.admin.text._makeRecord();

				// Drawn from what this answer holds, whatever stood there before
				Nino.admin.text._group = null;
				Nino.admin.text._directory = null;
				Nino.admin.text.showCurrent();
			} );
		},

		/**
		 *	Take an answer of text/keys in: the languages, the keys and the rows they fall into
		 *
		 *	@param		{Object}	response
		 *
		 *	@return		void
		 */
		_take : function( response ) {

			Nino.admin.text._locales = response.locales.map( function( entry ) { return entry.locale } );
			Nino.admin.text._inactive = response.locales.filter( function( entry ) { return entry.active === false } ).map( function( entry ) { return entry.locale } );
			Nino.admin.text._data = response;
			Nino.admin.text._buildModel();
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
		 *	The row an address names: its first part, where that is a row and no word of the router. Nothing for the list
		 *
		 *	@param		{Object}	at						router.current()
		 *
		 *	@return		{string|null}
		 */
		_addressGroup : function( at ) {

			if( Nino.admin.text._model === null || at.parts.length === 0 || Nino.admin.router.wordOf( at.parts, 0 ) !== '' )
				return null;

			const id = String( at.parts[0] );

			return Nino.admin.text._model.rows[id] === undefined ? null : id;
		},

		/**
		 *	The panel is shown, or the address moved. The record reads the address itself: one that names a text of the open row opens it, one that names the row
		 *	alone or none closes it. The row it names is drawn first, and the list where it names none. Where a template was written since the keys were read
		 *	(the Builder creates them, and so does a key of a template that the Keys tab adds) they are read again, so that the rows have it - not while a record
		 *	is open: the table would be drawn under it. An address of another panel (a click on the rail) leaves the level in memory as it is, and writes its address
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			const text = Nino.admin.text;

			if( text._ready === false ) {
				text.init();
				return;
			}

			const open = text._record !== null && text._record.isOpen() === true;

			if( open === false && Nino.admin.stamp( 'templates' ) !== text._stamp ) {
				text._ready = false;
				text.init();
				return;
			}

			const at = Nino.admin.router.current();

			if( at.panel !== 'text' ) {
				text._address();
				return;
			}

			const want = text._addressGroup( at );

			if( want !== text._group || ( want !== null && text._directory === null ) )
				text._level( want );
			else if( want === null )
				text._address();
			else if( open === false )
				text._release();

			if( want !== null )
				text._record.follow();
		},

		/**
		 *	Write the address of what is on screen and show its level: the list, the texts of a row, or a text of it
		 *
		 *	@return		void
		 */
		_address : function() {

			const text = Nino.admin.text;
			let parts = [];

			if( text._group !== null ) {
				parts = [ text._group ];

				if( text._record !== null && text._record.isOpen() === true && text._record.key() !== null )
					parts.push( text._record.key() );
			}

			Nino.admin.router.set( 'text', parts );
			Nino.adminUi.showLevel( text._group === null ? 'text-list' : 'text-form', { focus : false } );
		},

		/**
		 *	Show the level the address names: the list - which is drawn again, for what was edited since it was shown has changed its counts, and the focus
		 *	goes back to the row that was open where the person came back - or the texts of a row, drawn from the state the person left them in
		 *
		 *	@param		{string|null}	group
		 *
		 *	@return		void
		 */
		_level : function( group ) {

			const text = Nino.admin.text;
			const was = text._group;
			const moved = text._moved;

			text._moved = false;
			text._release();
			text._group = group;

			if( group === null ) {
				Nino.admin.router.set( 'text', [] );

				if( text._model !== null )
					text._renderCategoryList();

				if( moved === true && was !== null )
					Nino.adminUi.backTo( 'text-list', text._rowOf( was ) );
				else
					Nino.adminUi.showLevel( 'text-list', { focus : false } );

				return;
			}

			text._drawGroup( group );
			Nino.adminUi.showLevel( 'text-form', moved === true ? {} : { focus : false } );
		},

		/**
		 *	A move of the person's own inside the panel: the address is a new entry of the history, and the screen follows it
		 *
		 *	@param		{Array}		parts					The address behind the panel's name
		 *
		 *	@return		void
		 */
		_go : function( parts ) {
			Nino.admin.text._moved = true;
			Nino.admin.router.go( 'text', parts );
			Nino.admin.text.showCurrent();
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
		 *	shell has asked about the unsaved input of this panel first: the rows are
		 *	named in the other language, the table of the open row says its texts in it,
		 *	and a record that is open is drawn again with that language first - what it
		 *	held is saved or given up by now
		 *
		 *	@param		{string}	locale
		 *
		 *	@return		void
		 */
		_followLocale : function( locale ) {

			const text = Nino.admin.text;

			if( text._data === null || text._model === null )
				return;

			text._buildModel();
			text._search.shown = 0;

			if( dc.getElementById('text-list-body') !== null )
				text._renderCategoryList();

			if( text._group === null || text._model.rows[text._group] === undefined )
				return;

			text._drawGroup( text._group );

			// A record that was given up with its input is closed, and the address that named its text says the row
			if( text._record === null || text._record.isOpen() === false || text._ctx === null ) {

				if( text._record !== null && text._record.isOpen() === false && Nino.admin.router.current().panel === 'text' )
					text._address();

				return;
			}

			// An entry that stands among the rows the record steps through is drawn again from its row. One that a filter hides has no place to be stepped to: its
			// fields are put in the order of the language where they stand
			if( text._record.key() !== null && text._directory.rows().some( function( row ) { return row.key === text._record.key() && row.writable === true } ) === true )
				return text._ctx.go( 0 );

			if( text._view !== null ) {
				text._view.draw();

				if( typeof Nino.admin.dirty === 'object' )
					Nino.admin.dirty.snapshot( 'text' );
			}
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
		 *	What a row of the list says under its name, in the language on screen: how many texts it holds, and - where there are any - how many of them are empty
		 *	in it
		 *
		 *	@param		{Object}	row
		 *	@param		{string}	locale
		 *
		 *	@return		{string}
		 */
		_counts : function( row, locale ) {

			const counts = Nino.admin.textKeys.rowCounts( row, locale );
			const words = [ Nino.adminUi.format( Nino.content.getText( counts.texts === 1 ? '/_admin/text/count/text' : '/_admin/text/count/texts' ), counts.texts ) ];

			if( counts.empty > 0 )
				words.push( Nino.adminUi.format( Nino.content.getText('/_admin/text/count/empty'), counts.empty, Nino.adminUi.localeName( locale ) ) );

			return words.join(' · ');
		},

		/**
		 *	One group of rows, each a row of the workbench's list that opens the texts of the row, and has its address
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
					detail : Nino.admin.text._counts( row, locale ),
					sub : Nino.admin.textKeys.rowDepth( row ) > 0,
					href : Nino.admin.router.href( 'text', Nino.admin.textKeys.hashParts( id ) ),
					onOpen : function() { Nino.admin.text._go( Nino.admin.textKeys.hashParts( id ) ) },
				};
			} ), { title : title, level : 3 } );
		},

		/**
		 *	The hits of the search: one line for each key - the path a person
		 *	reads it by, the piece of its text with the words marked, its key
		 *	small, and where the words are in another language than the one on
		 *	screen, that language's code - 50 at a time (see
		 *	Nino.admin.textKeys.hitList()). A hit opens the record of its text; only
		 *	the code switches the language. Nothing is edited here: saving and the
		 *	log belong to a record
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
				onOpen : function( hit ) { Nino.admin.text._go( Nino.admin.textKeys.hashParts( hit.item.row.id, hit.item.field.entry.key ) ) },
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
		 *	The short name of a language in the table: its code's language, DE or EN - with the region where two languages of the site share it
		 *
		 *	@param		{string}	code
		 *
		 *	@return		{string}
		 */
		_short : function( code ) {

			const language = String( code ).split('_')[0];
			const shared = Nino.admin.text._locales.filter( function( other ) { return String( other ).split('_')[0] === language } ).length > 1;

			return ( shared === true ? String( code ).replace( '_', '-' ) : language ).toUpperCase();
		},

		/**
		 *	The rows of the directory of a row of the list: a text each, in the order of the sections and of the templates. What the table shows of a text, and the
		 *	text itself for the record to read
		 *
		 *	@param		{string}	group					The row's id
		 *
		 *	@return		{Array}
		 */
		_rows : function( group ) {

			const text 		= Nino.admin.text;
			const T 			= Nino.admin.textKeys;
			const locale 	= text._locale();
			const all 		= Nino.content.getText('/_admin/text/label/all-languages');
			const rows 		= [];

			T.sections( text._model, text._model.rows[group] ).forEach( function( section ) {

				const place = section.route === '' ? section.label : section.label+ ' ('+ section.route+ ')';

				section.fields.forEach( function( field ) {

					const entry = field.entry;
					const shown = T.plain( T.valueOf( entry, locale ) );

					rows.push( {
						key : entry.key,
						entry : entry,
						name : field.label,
						place : place,
						sectionId : section.id,
						title : [ place, field.label ].filter( function( part, at, parts ) { return at === 0 || part !== parts[at - 1] } ).join(' › '),
						text : shown.length > 200 ? shown.slice( 0, 200 )+ '…' : shown,
						chars : T.lengthOf( entry, T.valueOf( entry, locale ) ),
						emptyHere : T.isEmptyIn( entry, locale ),
						writable : text._writable( entry ),
						// One badge for each language of the site; a key that is one text for all of them has the one
						states : entry.global === true ? [ { locale : '*', label : all, name : all, ok : true } ] : text._locales.map( function( code ) {
							return { locale : code, label : text._short( code ), name : Nino.adminUi.localeName( code, text._locales ), ok : T.isEmptyIn( entry, code ) === false };
						} ),
						// What a person looks for: every language's text, whole
						found : Object.keys( entry.values ).map( function( code ) { return T.plain( entry.values[code] ) } ).join(' '),
					} );
				} );
			} );

			return rows;
		},

		/**
		 *	The row of one text, as the directory and the record have it: null for a text that is not in the open row or that this account may not write - which
		 *	opens no record
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Object|null}
		 */
		_find : function( key ) {

			const text = Nino.admin.text;

			if( text._group === null || text._model === null || text._model.rows[text._group] === undefined )
				return null;

			const row = text._rows( text._group ).find( function( one ) { return one.key === key } );

			return row === undefined || row.writable === false ? null : row;
		},

		/**
		 *	The text of the open row that a key names, in the model - whatever this account may do with it
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Object|undefined}
		 */
		_entryOf : function( key ) {

			const text = Nino.admin.text;

			return text._group === null ? undefined : ( text._groups[text._group] ?? [] ).find( function( entry ) { return entry.key === key } );
		},

		/**
		 *	The texts of a row: the way back and the name of the row in the context bar, and under it the directory - the section as its category, the search, the
		 *	select "Show", and the table in the order the templates read the texts. What the person chose in it is kept for the row while the panel lives
		 *
		 *	@param		{string}	group					The row's id
		 *
		 *	@return		void
		 */
		_drawGroup : function( group ) {

			const text 		= Nino.admin.text;
			const T 			= Nino.admin.textKeys;
			const model 	= text._model;
			const row 		= model.rows[group];
			const locale 	= text._locale();
			const word 		= function( key ) { return Nino.content.getText( key ) };

			const wrap = dc.getElementById('text-form');
			wrap.innerHTML = '';

			const back = Nino.adminUi.contextBar( {
				back : { to : word('/_admin/text/label/back-name'), href : Nino.admin.router.href( 'text', [] ), onClick : function() { text._moved = true; Nino.admin.router.go( 'text', [] ); text.showCurrent() } },
				title : T.rowLabel( model, row, locale ),
			} );
			wrap.appendChild( back );
			Nino.adminUi.localeBadge( locale, back );

			const mount = Nino.adminUi.el( 'div' );
			wrap.appendChild( mount );

			const rows = text._rows( group );
			const sections = T.sections( model, row );

			const filters = [ {
				id : 'text-show',
				label : word('/_admin/text/filter/show'),
				value : '',
				options : [
					{ value : '', label : word('/_admin/text/filter/all') },
					{ value : 'empty', label : Nino.adminUi.format( word('/_admin/text/label/empty-in'), Nino.adminUi.localeName( locale ) ) },
					{ value : 'any', label : word('/_admin/text/filter/empty-any') },
				],
				match : function( one, value ) {

					if( value === 'empty' )
						return one.emptyHere === true;

					return value !== 'any' || one.states.some( function( state ) { return state.ok === false } );
				},
			} ];

			text._directory = Nino.adminUi.directory( {
				mount : mount,
				rowKey : 'key',
				state : text._states[group],
				onState : function( now ) { text._states[group] = now },
				columns : [
					{ key : 'name', label : word('/_admin/text/col/name'), type : 'string', primary : true, sortable : false, render : text._nameCell },
					{ key : 'place', label : word('/_admin/text/col/section'), type : 'string', sortable : false, narrow : false },
					{ key : 'text', label : Nino.adminUi.format( word('/_admin/text/col/text'), Nino.adminUi.localeName( locale ) ), type : 'string', cell : 'clamp', fill : true, sortable : false },
					{ key : 'chars', label : word('/_admin/text/col/chars'), type : 'integer', cell : 'limit', sortable : false, limit : function( one ) { return one.entry.maxlength }, over : word('/_admin/text/col/long') },
					{ key : 'states', label : word('/_admin/text/col/languages'), cell : 'locales', sortable : false, words : { ok : word('/_admin/text/col/lang-ok'), missing : word('/_admin/text/col/lang-missing') } },
				],
				labels : { search : word('/_admin/text/label/search'), empty : word('/_admin/text/empty'), noMatch : word('/_admin/text/msg/nothing') },
				// The sections of the row, in the order the templates read them; a row that has one section has nothing to narrow to
				category : sections.length < 2 ? undefined : {
					id : 'text-section',
					label : word('/_admin/text/col/section'),
					value : '',
					options : [ { value : '', label : word('/_admin/text/filter/sections') } ].concat( sections.map( function( section ) {
						return { value : section.id, label : section.route === '' ? section.label : section.label+ ' ('+ section.route+ ')' };
					} ) ),
					match : function( one, value ) { return value === '' || one.sectionId === value },
				},
				filters : filters,
				// What a person looks for: the name, the key, the section and the text in every language
				searchText : function( one ) { return [ one.name, one.key, one.place, one.found ].join(' ') },
				actions : text._actions,
				// A row is opened by its first button as well: the row is not a stop of the Tab key where it holds buttons. A text this account may not change has no
				// button, and its row says why where it is pressed
				onOpen : function( one ) {

					if( one.writable === false )
						return text._directory.notice( word('/_admin/text/error/denied') );

					text._directory.notice('');
					text._record.open( one.key, {} );
				},
				rows : rows,
			} );
		},

		/**
		 *	The text in the table: its name strong, its key under it in the face of an identifier, each in one line that an ellipsis ends and whole in its tooltip
		 *
		 *	@param		{string}	value
		 *	@param		{Object}	row
		 *
		 *	@return		{Element}
		 */
		_nameCell : function( value, row ) {

			const cell = Nino.adminUi.el( 'span', 'admin-text-name' );
			const name = Nino.adminUi.el( 'strong', 'admin-text-name-label', row.name );
			const key = Nino.adminUi.el( 'span', 'admin-text-name-key', row.key );

			name.title = row.name;
			key.title = row.key;
			cell.appendChild( name );
			cell.appendChild( key );

			return cell;
		},

		/**
		 *	The button at the end of a row: edit, which opens the text - for a text that can be changed; there is none for one that cannot
		 *
		 *	@param		{Object}	row
		 *
		 *	@return		{Array}
		 */
		_actions : function( row ) {

			if( row.writable === false )
				return [];

			return [ Nino.adminUi.iconButton( 'pencil', Nino.adminUi.format( Nino.content.getText('/_admin/text/row/open'), row.title ), function( event ) {
				Nino.admin.text._directory.notice('');
				Nino.admin.text._record.open( row.key, { opener : event.currentTarget } );
			}, { small : true } ) ];
		},

		/**
		 *	The directory as the record sees it: what is on screen now, whatever drew it last. The record walks the texts that open - the steps skip the ones
		 *	this account may not change, and the place counts only those
		 *
		 *	@return		{Object}
		 */
		_texts : function() {

			const text = Nino.admin.text;

			return {
				rows : function() { return text._directory === null ? [] : text._directory.rows().filter( function( row ) { return row.writable === true } ) },
				reveal : function( key ) { return text._directory === null ? false : text._directory.reveal( key ) },
				focusRow : function( key ) { return text._directory === null ? false : text._directory.focusRow( key ) },
				focusSearch : function() { return text._directory === null ? false : text._directory.focusSearch() },
			};
		},

		/**
		 *	The record of a text: a dialog over the table with the place of the text in its head and its key under it, a field for each language - the working
		 *	language first - and "Save", "Save and continue". There is nothing to delete or to make here: a key is made, renamed and deleted on the Text Keys tab
		 *
		 *	@return		{Object}
		 */
		_makeRecord : function() {

			const text = Nino.admin.text;

			return Nino.adminUi.record( {
				screen : 'text',
				// The texts are under a part of the screen: the row - #text/text/<row>/<key>
				prefix : function() { return text._group === null ? [] : [ text._group ] },
				directory : text._texts(),
				keyOf : function( row ) { return row.key },
				find : text._find,
				title : function( row ) { return row.title },
				subtitle : function( row ) { return row.key },
				saveNext : true,
				labels : { prev : Nino.content.getText('/_admin/text/record/prev'), next : Nino.content.getText('/_admin/text/record/next') },
				body : text._body,
				save : text._save,
				reload : text._reload,
				// An address that names a text the row does not hold, or one this account may not change: said over the table
				missing : function( key ) {
					text._directory.notice( Nino.content.getText( text._entryOf( key ) === undefined ? '/_admin/text/record/missing' : '/_admin/text/error/denied' ) );
				},
			} );
		},

		/**
		 *	Take the editors of the record that was on screen down, and the fields with them
		 *
		 *	@return		void
		 */
		_release : function() {

			const text = Nino.admin.text;

			if( text._view !== null )
				text._view.fields.forEach( function( field ) { field.handle.destroy() } );

			text._view = null;
			text._ctx = null;
		},

		/**
		 *	The textfills a text may take, for the "[ ]" at the name of a field: asked for when the dialog opens, in the language of THAT field
		 *
		 *	@param		{string}		locale
		 *	@param		{Function}	done					Called with the list the server sent, with null where it could not be had
		 *
		 *	@return		void
		 */
		_loadFills : function( locale, done ) {
			Nino.admin.textKeys.fills( function( callback ) { Nino.admin.text._apiCall( 'fills', { locale : locale }, callback ) }, done );
		},

		/**
		 *	The content of the record: one field for each language of the text - the working language first and marked, then the others in their order, a language
		 *	the website does not serve yet marked as the select of the working language marks it - or the one field of a key that is the same in every language.
		 *	Each is drawn by the rule of the key (a line, several lines, the rich-text editor) with its counter, and what it read back after it was drawn is what a
		 *	save compares it with. What the fields are is kept in ctx.view, which the save finds there
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *
		 *	@return		{Element}
		 */
		_body : function( row, ctx ) {

			const text = Nino.admin.text;

			// The fields of the entry that was on screen go with it
			text._release();

			const wrap = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );

			// The root of what the panel's stylesheet arranges in the record: the dialog stands outside the pane of the panel
			wrap.id = 'text-record';
			const view = { key : row.key, fields : [], wrap : wrap, conflict : null, draw : null };

			// Drawn again where the language moved under an open record that no step can redraw
			view.draw = function() {

				view.fields.forEach( function( field ) { field.handle.destroy() } );
				view.fields = [];
				wrap.innerHTML = '';

				const entry = text._entryOf( view.key ) ?? row.entry;
				const locale = text._locale();
				const writable = text._writable( entry );
				const codes = entry.global === true ? [ '*' ] : [ locale ].concat( text._locales.filter( function( code ) { return code !== locale } ) );

				codes.forEach( function( code ) {

					const marks = [];

					if( entry.global === false && code === locale )
						marks.push( Nino.content.getText('/_admin/text/label/working') );

					if( entry.global === false && text._inactive.indexOf( code ) !== -1 )
						marks.push( Nino.adminUi.text('/_admin/common/word/inactive') );

					const name = entry.global === true ? Nino.content.getText('/_admin/text/label/all-languages') : Nino.adminUi.localeName( code, text._locales );
					const handle = Nino.admin.textKeys.valueField( {
						entry : entry,
						value : Nino.admin.textKeys.valueOf( entry, code ),
						label : marks.length === 0 ? name : name+ ' ('+ marks.join(', ')+ ')',
						id : 'text-field-'+ ( ++text._fieldSeq ),
						writable : writable,
						fills : writable === true ? function( done ) { text._loadFills( code === '*' ? locale : code, done ) } : null,
						keyLine : false,
						badge : false,
						locale : code,
					} );

					// What the field read back after it was drawn: a rich text reads back as the markup the editor built, not always the string that is stored
					view.fields.push( { code : code, handle : handle, base : handle.read() } );
					wrap.appendChild( handle.el );
				} );
			};

			view.draw();
			ctx.view = view;
			text._view = view;
			text._ctx = ctx;

			// The record puts the focus in the first line or box of the form; the editor of a rich text is neither, so it is given the focus once the record is drawn
			if( view.fields.length > 0 && view.fields[0].handle.readonly !== true && /^(INPUT|TEXTAREA)$/.test( view.fields[0].handle.control.tagName ) === false )
				wn.setTimeout( function() {
					if( text._view === view && view.fields.length > 0 && wrap.contains( dc.activeElement ) === false )
						view.fields[0].handle.focus();
				}, 0 );

			return wrap;
		},

		/**
		 *	Take the input from the fields of the record while a save runs, and give it back: a line and several lines are disabled, the editable box of a rich text
		 *	is not editable
		 *
		 *	@param		{Object}		view					What the body of the record keeps
		 *	@param		{boolean}		on
		 *
		 *	@return		void
		 */
		_lock : function( view, on ) {

			view.fields.forEach( function( field ) {

				if( field.handle.readonly === true )
					return;

				const box = field.handle.control.querySelector !== undefined ? field.handle.control.querySelector('[contenteditable]') : null;

				if( box !== null && box !== undefined )
					box.setAttribute( 'contenteditable', on === true ? 'false' : 'true' );
				else
					field.handle.control.disabled = on === true;
			} );
		},

		/**
		 *	The text a conflict is about, by its place, its key and its language, as the question names it
		 *
		 *	@param		{string}	key
		 *	@param		{string}	code
		 *
		 *	@return		{string}
		 */
		_named : function( key, code ) {

			const text = Nino.admin.text;
			const place = Nino.admin.textKeys.nameOf( text._model, key );
			const language = code === '*' ? '' : ', '+ Nino.adminUi.localeName( code, text._locales );

			return ( place === '' ? '' : place+ ' ' )+ '('+ key+ language+ ')';
		},

		/**
		 *	Save the text the record shows: the languages whose field was changed - and nothing else, so that a record saved without a change leaves the files as they
		 *	are -, each in a request of its own with one item, one after the other in the order of the fields: the answer is keyed by the key, so two languages of
		 *	one key in one request would be one answer. What the server kept is what the field shows and what it is marked as saved by. A language the server turns
		 *	down is said at its field and stays changed, and the languages before it stay saved; a language that was written by another since the record was drawn is
		 *	the question of a conflict - Overwrite writes that language again, without the text it was read as. A request that fails ends the save
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{Function}	done					done( ok, { row, error, conflict } )
		 *
		 *	@return		void
		 */
		_save : function( row, ctx, done ) {

			const text = Nino.admin.text;
			const view = ctx.view;
			const key = row.key;
			const group = text._group;

			Nino.adminUi.clearFieldErrors( ctx.form );

			// What is saved, as the table and the record have it now
			const now = function() { return text._find( key ) ?? row };

			// Nothing differs from what is stored: nothing is written, and what is on screen is what is stored
			const changed = view.fields.filter( function( field ) { return field.handle.readonly !== true && field.handle.read() !== field.base } );

			if( changed.length === 0 )
				return done( true, { row : now() } );

			let failed = 0;
			let first = null;

			// The fields take no input while the requests run: the record takes what its form holds when the save ends as saved, and a text typed meanwhile would be
			// taken with it
			const held = dc.activeElement ?? null;

			text._lock( view, true );

			const end = function( ok, result ) {

				text._lock( view, false );

				if( held !== null && held !== undefined && typeof held.focus === 'function' && ctx.form.contains( held ) === true )
					held.focus();

				done( ok, result );
			};

			const finish = function( error ) {

				const made = now();

				text._directory.update( text._rows( group ), key );

				if( error !== undefined )
					return end( false, { error : error, row : made } );

				if( failed > 0 ) {
					end( false, { error : Nino.adminUi.format( Nino.content.getText( failed === 1 ? '/_admin/text/error/one' : '/_admin/text/error/some' ), failed ), row : made } );
					first.handle.focus();
					return;
				}

				end( true, { row : made } );
			};

			const write = function( at ) {

				if( at >= changed.length )
					return finish();

				const field = changed[at];
				const entry = text._entryOf( key );
				const sent = field.handle.read();

				// The language that was turned down as changed is written over what is there: forced, without the text it was read as
				const item = ctx.overwrite === true && view.conflict === field.code
					? { key : key, locale : field.code, value : sent, force : true }
					: Nino.admin.textKeys.saveItem( entry, field.code === '*' ? text._locale() : field.code, sent );

				text._apiCall( 'savebatch', { items : [ item ] }, function( status, response ) {

					if( status !== 200 || response === null )
						return finish( Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );

					const result = ( response.results ?? {} )[key];

					// Another tab or person wrote the text since: the question is the record's, the text stays as it was typed
					if( result !== undefined && result.error === 'changed' ) {
						view.conflict = field.code;
						text._directory.update( text._rows( group ), key );
						return end( false, { conflict : true, error : Nino.adminUi.format( Nino.content.getText('/_admin/text/error/changed'), text._named( key, field.code ) ), row : now() } );
					}

					if( result === undefined || result.ok !== true ) {
						failed++;
						field.handle.fail( Nino.content.getText( result !== undefined && result.error === 'not allowed' ? '/_admin/text/error/denied' : '/_admin/text/error/key' ) );
						first = first ?? field;
						return write( at + 1 );
					}

					// Written
					Nino.admin.touch( 'texts' );
					view.conflict = null;
					entry.values[item.locale] = result.value;

					// What the server kept is what the field shows - unless more was typed while it was on its way, which is not in what was sent and stays unsaved
					if( field.handle.read() === sent ) {
						if( result.value !== sent )
							field.handle.write( result.value );
						field.base = field.handle.read();
					}
					else
						field.base = sent;

					write( at + 1 );
				} );
			};

			write( 0 );
		},

		/**
		 *	Read the keys again for the answer Reload of a conflict: what is stored is what the record and the table show, and what this tab typed is dropped
		 *
		 *	@param		{string}		key
		 *	@param		{Function}	done					done( row ) with the row of the text as it is stored now, null where it could not be read or is gone
		 *
		 *	@return		void
		 */
		_reload : function( key, done ) {

			const text = Nino.admin.text;

			text._apiCall( 'keys', {}, function( status, response ) {

				if( status !== 200 || response === null ) {

					if( text._ctx !== null && text._ctx.status !== null )
						text._ctx.status.fail( Nino.adminUi.api.errorText( status, response, '/_admin/common/error/load' ) );

					return done( null );
				}

				text._take( response );
				text._renderCategoryList();

				if( text._group === null || text._model.rows[text._group] === undefined )
					return done( null );

				text._directory.setRows( text._rows( text._group ), { keep : true } );
				done( text._find( key ) );
			} );
		},
	};

	// The language moves for this panel whoever moved it (see Nino.admin.workLocale)
	Nino.admin.workLocale.follow( 'text', Nino.admin.text._followLocale );

})(window, document, document.documentElement, document.body);
