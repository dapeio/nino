/**
 *	Nino										A compact filesystembased php framework
 *	keys.js									"Text" module: find a text key - by its key, the names it
 *													is read by or what it says, hidden ones included - or
 *													browse the rows the keys fall into (the same ones as the
 *													Text panel, with the same names, see textkeys.js). A row
 *													of the list says how many keys it holds and how many are
 *													hidden; it opens as the standard directory of its keys
 *													(Nino.adminUi.directory()): the name and the key, the
 *													section, the text of the working language, the format, the
 *													limit and the state - in the order the templates read
 *													them, so nothing sorts. ONE key is edited in the record
 *													over it (Nino.adminUi.record(), #text/keys/<row>/<key>):
 *													its text in every language and its settings - format,
 *													limit, one text for all languages, hidden from the Text
 *													panel. A save writes what was changed and nothing else:
 *													the shape of the key by keys/save, each language that was
 *													changed by keys/savebatch. The record renames the key in a
 *													small dialog over it (#text/keys/@rename/<key>), deletes
 *													it, and makes a new one (#text/keys/@new) from the four
 *													parts /<namespace>/<category>/<part>/<name>. The scan of
 *													the templates for missing keys is a view of its own
 *													(#text/keys/@scan). The language the texts are shown in is
 *													the workbench's own (Nino.admin.workLocale).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	const word = function( key ) { return Nino.content.getText( key ) };

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

		// The languages that have a text file, the ones of them the website does not serve yet
		_locales				: [],
		_inactive				: [],
		// What the server sent (see \Nino\Modules\Text\Keys::apiList()) and the
		// rows textkeys.js made of it: _groups is row id -> its keys. _categories
		// is what the form of a key offers to choose from
		_data						: null,
		_model					: null,
		_categories			: {},
		_groups					: {},
		// The row whose keys are on screen - its id is the part of a key it stands
		// for, eg. 'template/page-home' - null where the list is; which level is
		// on screen ('list', 'group') and the one of the scan
		_group					: null,
		_shown					: null,
		// What the search box holds, whether it is cut down to the keys that are
		// hidden from the Text panel, and how many hits are drawn
		_search					: { query : '', hiddenOnly : false, shown : 0 },
		// The directory of the keys of the open row, what the person chose in it
		// for each row (kept while the tab lives), and the record over it
		_directory			: null,
		_states					: {},
		_record					: null,
		// What the body of the record on screen was given - the fields of the languages, the controls of the shape - and the context of the record
		_view						: null,
		_ctx						: null,
		_fieldSeq				: 0,
		// The text a key was read as, kept for each language a save was turned down for as changed: the keys are read again after every save, and the next
		// save is held to the text this tab was shown, not to the one it was never shown. Given up by Overwrite and by Reload
		_stamps					: {},
		// A save wrote and the keys could not be read again: the record says so and offers Reload
		_stale					: false,
		// The key whose rename dialog is open, the dialog, whether it was opened as an entry of the history of its own, and what is to be done once the history is
		// back at the record of the key (the rename is done and the record follows the new key)
		_renaming				: null,
		_dialog					: null,
		_renamePushed		: false,
		_renameLeaving	: false,
		_afterRename		: null,
		// The address is read for the form of a new key (its parts do not stand under a row), or for a record the address says is not the form of a new key
		_wantNew				: false,
		_forced					: null,
		// The row and the namespace the form of a new key starts from, where the button that opened it stood in a row
		_seed						: null,
		// The scan is the view on screen, with its rows or still waiting for them
		_scanning				: false,
		_scanRows				: null,
		_line						: null,
		// The scan button of the bar of the list, and the button that opened the scan - one of the two bars - which the way back of the scan gives the focus to
		_buttons				: { scan : null, add : null },
		_opener					: null,
		// A move of the person's own: the level it leads to takes the focus
		_moved					: false,
		_loading				: false,
		_ready					: false,
		// What the templates and the texts were when the keys were read (see Nino.admin.touch())
		_stamp					: '',

		/**
		 *	Load every known text key, sort them into rows and render the list - or,
		 *	where the address names a row, a key or a view, open it
		 *
		 *	@return		void
		 */
		init : function() {

			const keys = Nino.admin.keys;

			if( dc.getElementById('keys-list') === null || keys._loading === true || keys._ready === true )
				return;

			keys._loading = true;

			// A text or a template written while the answer is on its way is not in it: the stamp is the one from before the request went out
			const stamp = keys._stampNow();

			keys._load( function( status, response ) {

				keys._loading = false;

				if( status !== 200 || response === null )
					return keys._showError( dc.getElementById('keys-list'), status, response );

				keys._stamp = stamp;
				keys._ready = true;
				keys._renderList();

				if( keys._record === null ) {
					keys._record = keys._makeRecord();
					keys._share();
				}

				// Drawn from what this answer holds, whatever stood there before
				keys._group = null;
				keys._directory = null;
				keys._shown = null;
				keys.showCurrent();
			} );
		},

		/**
		 *	What the templates and the texts are: two counters of the shell, as one value
		 *
		 *	@return		{string}
		 */
		_stampNow : function() {
			return Nino.admin.stamp( 'templates' )+ ':'+ Nino.admin.stamp( 'texts' );
		},

		/**
		 *	Tell the shell that something was written, and take it as read by this tab: what the tab wrote itself is on screen
		 *
		 *	@param		{Array<string>}		topics		'texts' and, for a key a template reads, 'templates'
		 *
		 *	@return		void
		 */
		_wrote : function( topics ) {

			const keys = Nino.admin.keys;
			const same = keys._stampNow() === keys._stamp;

			topics.forEach( function( topic ) { Nino.admin.touch( topic ) } );

			if( same === true )
				keys._stamp = keys._stampNow();
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
					Nino.admin.keys._inactive = response.locales.filter( function( entry ) { return entry.active === false } ).map( function( entry ) { return entry.locale } );
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

			const keys = Nino.admin.keys;
			const data = keys._data;

			keys._model = Nino.admin.textKeys.build( {
				entries : data.keys, pages : data.pages, templates : data.templates, features : data.features, order : data.order, admin : true, locale : keys._locale(),
			} );
			keys._groups = Object.create( null );

			Object.keys( keys._model.rows ).forEach( function( id ) {
				keys._groups[id] = keys._model.rows[id].entries;
			} );
		},

		/**
		 *	Call a keys/* admin action - this tab's name for Nino.adminUi.api.call(),
		 *	which owns where the request goes
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
		 *	The row an address names: its first part, where that is a row and no word of the router. Nothing for the list
		 *
		 *	@param		{Object}	at						router.current()
		 *
		 *	@return		{string|null}
		 */
		_addressGroup : function( at ) {

			const keys = Nino.admin.keys;

			if( keys._model === null || at.parts.length === 0 || Nino.admin.router.wordOf( at.parts, 0 ) !== '' )
				return null;

			const id = String( at.parts[0] );

			return keys._model.rows[id] === undefined ? null : id;
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
		 *	The tab is shown, or the address moved. The record reads the address itself; the words of the tab - @new, @scan, @rename and a key - are answered here
		 *	first (see _follow()). Where a text or a template was written since the keys were read they are read again, not while something stands over the table
		 *	that would be drawn under it. An address of another panel (a click on the rail) leaves the level in memory as it is, and writes its address
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			const keys = Nino.admin.keys;

			if( keys._ready === false ) {
				keys.init();
				return;
			}

			const standing = keys._record.isOpen() === true || keys._scanning === true || keys._dialog !== null;

			if( standing === false && keys._stampNow() !== keys._stamp ) {
				keys._ready = false;
				keys.init();
				return;
			}

			const at = Nino.admin.router.current();

			if( at.panel !== 'keys' ) {
				keys._address();
				return;
			}

			keys._follow( at );
		},

		/**
		 *	What the address names, and who answers it. The words of the tab are the tab's own and are never read as a row or as the key of a record: @scan is the view
		 *	of the scan, @rename and a key the rename dialog over the record of that key, @new the form of a new key in the record. Everything else is a row, a key in
		 *	it - the record's - or the list. What is on screen is the screen: a step through the history that leaves a view asks first where there is input
		 *
		 *	@param		{Object}	at						router.current()
		 *
		 *	@return		void
		 */
		_follow : function( at ) {

			const keys = Nino.admin.keys;
			const router = Nino.admin.router;
			const parts = at.parts;
			const single = parts.length === 1;
			const isNew = single === true && router.isWord( parts, 0, 'new' ) === true;
			const isScan = single === true && router.isWord( parts, 0, 'scan' ) === true;
			const isRename = parts.length === 2 && router.isWord( parts, 0, 'rename' ) === true && keys._renamable( parts[1] ) === true;

			// The history is back at the record of the key that was renamed: the record follows the new key
			if( keys._afterRename !== null ) {
				keys._finishRename();
				return;
			}

			if( keys._dialog !== null ) {

				if( isRename === true && parts[1] === keys._renaming )
					return;

				keys._leaveRename();
				return;
			}

			if( keys._scanning === true ) {

				if( isScan === true )
					return;

				router.leave( [ 'keys' ], true, function() {
					keys._leaveScan( null );
					keys._follow( router.current() );
				}, keys._address );

				return;
			}

			if( isScan === true ) {

				router.leave( [ 'keys' ], keys._record.isOpen(), function() {

					// A record that holds input was asked about; one that does not is closed here
					if( keys._record.isOpen() === true )
						keys._record.close();

					if( keys._record.isOpen() === true )
						return keys._address();

					keys._openScan( false, false );
				}, keys._address );

				return;
			}

			if( isRename === true ) {
				keys._openRenameByAddress( parts[1] );
				return;
			}

			if( isNew === true ) {

				if( keys._shown === null )
					keys._level( null );

				if( keys._record.isOpen() === false )
					keys._seed = null;

				keys._wantNew = true;

				try {
					keys._record.follow();
				}
				finally {
					keys._wantNew = false;
				}

				return;
			}

			const want = keys._addressGroup( at );

			// A form of a new key that stands open reads the address as the key of a record: what the address says is the row, not that
			if( keys._record.creating() === true )
				keys._forced = { row : want };

			if( want !== keys._group || ( want !== null && keys._directory === null ) || keys._shown === null )
				keys._level( want );
			else if( want === null )
				Nino.adminUi.showLevel( 'keys-list', { focus : false } );

			try {
				keys._record.follow();
			}
			finally {
				keys._forced = null;
			}
		},

		/**
		 *	Write the address of what is on screen and show its level: the scan, the form of a new key, a key of a row, the row, or the list
		 *
		 *	@return		void
		 */
		_address : function() {

			const keys = Nino.admin.keys;
			const router = Nino.admin.router;
			let parts = [];

			if( keys._scanning === true )
				parts = [ router.word('scan') ];
			else if( keys._dialog !== null && keys._renaming !== null )
				parts = [ router.word('rename'), keys._renaming ];
			else if( keys._record !== null && keys._record.creating() === true )
				parts = [ router.word('new') ];
			else if( keys._group !== null ) {
				parts = [ keys._group ];

				if( keys._record !== null && keys._record.isOpen() === true && keys._record.key() !== null )
					parts.push( keys._record.key() );
			}

			router.set( 'keys', parts );
			Nino.adminUi.showLevel( keys._scanning === true || keys._group !== null ? 'keys-form' : 'keys-list', { focus : false } );
		},

		/**
		 *	Show the level the address names: the list - which is drawn again, for what was edited since it was shown has changed its counts, and the focus goes
		 *	back to the row that was open where the person came back - or the keys of a row, drawn from the state the person left them in
		 *
		 *	@param		{string|null}	group
		 *
		 *	@return		void
		 */
		_level : function( group ) {

			const keys = Nino.admin.keys;
			const was = keys._group;
			const moved = keys._moved;

			keys._moved = false;

			// A record that stands open keeps its fields until it is closed
			if( keys._record.isOpen() === false )
				keys._release();

			keys._group = group;

			if( group === null ) {

				keys._directory = null;
				keys._shown = 'list';
				keys._renderBody();

				if( moved === true && was !== null )
					Nino.adminUi.backTo( 'keys-list', keys._rowOf( was ) );
				else
					Nino.adminUi.showLevel( 'keys-list', { focus : false } );

				return;
			}

			keys._drawGroup( group );
			keys._shown = 'group';
			Nino.adminUi.showLevel( 'keys-form', moved === true ? {} : { focus : false } );
		},

		/**
		 *	A move of the person's own inside the tab: the address is a new entry of the history, and the screen follows it
		 *
		 *	@param		{Array}		parts					The address behind the tab's name
		 *
		 *	@return		void
		 */
		_go : function( parts ) {
			Nino.admin.keys._moved = true;
			Nino.admin.router.go( 'keys', parts );
			Nino.admin.keys.showCurrent();
		},

		/**
		 *	The link of a row as the list is drawn now: the list is drawn again whenever it is shown, and the link that was pressed is gone with the old drawing
		 *
		 *	@param		{string}	group			The row's id
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( group ) {

			const row = Array.from( dc.getElementById('keys-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === group } );

			return row === undefined ? null : row.querySelector('a');
		},

		// ------------------------------------------------------------------ The first level

		/**
		 *	Draw the controls of the list - the search and the chip for the keys hidden from the Text panel - and the line that says how many hits there are, the place
		 *	the rows or the hits go, and the bar of the list: the scan beside the main button that makes a new key. Then the rows
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const keys = Nino.admin.keys;
			const wrap = dc.getElementById('keys-list');

			wrap.innerHTML = '';

			const search = Nino.admin.textKeys.searchBar( {
				query : keys._search.query,
				onQuery : function( query ) {
					keys._search.query = query;
					keys._search.shown = 0;
					keys._renderBody();
				},
				chips : [ { id : 'hidden', onToggle : function() {
					keys._search.hiddenOnly = keys._search.hiddenOnly === false;
					keys._search.shown = 0;
					keys._renderBody();
				} } ],
			} );

			search.input.id = 'keys-search';
			search.chips.hidden.id = 'keys-list-hidden';
			search.chips.hidden.textContent = word('/_admin/keys/label/hidden-only');
			wrap.appendChild( search.bar );

			const status = dc.createElement('p');
			status.id = 'keys-list-status';
			status.className = 'admin-text-count';
			status.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( status );

			const body = dc.createElement('div');
			body.id = 'keys-list-body';
			wrap.appendChild( body );

			const bar = keys._listBar( function() { return null } );

			keys._buttons = { scan : bar.scan, add : bar.add };
			wrap.appendChild( bar.el );

			keys._renderBody();
		},

		/**
		 *	The bar of the list - and of the table of a row, under it: the scan beside the main button that makes a new key
		 *
		 *	@param		{Function}	seed		seed() answers what the form of a new key starts from - { namespace, category } - or null
		 *
		 *	@return		{Object}									{ el, scan, add }
		 */
		_listBar : function( seed ) {

			const keys = Nino.admin.keys;

			const scan = Nino.adminUi.button( word('/_admin/keys/label/scan'), function() {
				keys._opener = scan;
				keys._openScan( true, true );
			}, { kind : 'secondary' } );

			const add = Nino.adminUi.button( word('/_admin/keys/label/new'), function() {
				keys._newKey( add, seed() );
			} );

			return { el : Nino.adminUi.actionBar( { list : true, secondary : [ scan ], primary : add } ).el, scan : scan, add : add };
		},

		/**
		 *	Draw what is below the search: the hits where something is searched for, the rows otherwise
		 *
		 *	@return		void
		 */
		_renderBody : function() {

			const keys = Nino.admin.keys;
			const body = dc.getElementById('keys-list-body');

			if( body === null || keys._model === null )
				return;

			const chip = dc.getElementById('keys-list-hidden');

			chip.setAttribute( 'aria-pressed', keys._search.hiddenOnly === true ? 'true' : 'false' );
			body.innerHTML = '';

			if( keys._search.query.trim() === '' && keys._search.hiddenOnly === false ) {
				dc.getElementById('keys-list-status').textContent = '';
				keys._fillBody( body );
				return;
			}

			keys._renderHits( body );
		},

		/**
		 *	What a row of the list says under its name: how many keys it holds and - where there are any - how many of them are hidden from the Text panel
		 *
		 *	@param		{Object}	row
		 *
		 *	@return		{string}
		 */
		_counts : function( row ) {

			const total = row.entries.length;
			const hidden = row.entries.filter( function( entry ) { return entry.blacklisted === true } ).length;
			const words = [ Nino.adminUi.format( word( total === 1 ? '/_admin/keys/count/key' : '/_admin/keys/count/keys' ), total ) ];

			if( hidden > 0 )
				words.push( Nino.adminUi.format( word('/_admin/keys/count/hidden'), hidden ) );

			return words.join(' · ');
		},

		/**
		 *	The rows, in the blocks and groups textkeys.js made of them: each a link that opens the keys of the row, and has its address
		 *
		 *	@param		{Element}	body
		 *
		 *	@return		void
		 */
		_fillBody : function( body ) {

			const keys = Nino.admin.keys;
			const model = keys._model;
			const locale = keys._locale();

			if( Object.keys( model.rows ).length === 0 ) {
				body.appendChild( Nino.adminUi.emptyState( word('/_admin/keys/empty') ) );
				return;
			}

			model.blocks.forEach( function( block ) {

				const part = dc.createElement('section');
				part.appendChild( Nino.adminUi.sectionTitle( word( '/_admin/text/group/'+ block.id ) ) );

				( block.rows !== undefined ? [ { id : block.id, rows : block.rows } ] : block.groups ).forEach( function( group ) {

					part.appendChild( Nino.adminUi.list( group.rows.map( function( id ) {

						const row = model.rows[id];

						return {
							key : id,
							title : Nino.admin.textKeys.rowLabel( model, row, locale ),
							detail : keys._counts( row ),
							sub : Nino.admin.textKeys.rowDepth( row ) > 0,
							href : Nino.admin.router.href( 'keys', Nino.admin.textKeys.hashParts( id ) ),
							onOpen : function() { keys._go( Nino.admin.textKeys.hashParts( id ) ) },
						};
					} ), { title : block.rows === undefined && group.id !== 'common' ? word( '/_admin/text/group/'+ group.id ) : '', level : 3 } ) );
				} );

				body.appendChild( part );
			} );
		},

		/**
		 *	The hits of the search, 50 at a time: a row for each key (see Nino.admin.textKeys.hitList()). A hit opens the record of its key; only the code of
		 *	a language switches the language, asked for as every change of it is
		 *
		 *	@param		{Element}	body
		 *
		 *	@return		void
		 */
		_renderHits : function( body ) {

			const keys = Nino.admin.keys;
			const search = keys._search;
			const locale = keys._locale();
			const hits = Nino.admin.textKeys.search( keys._model, { query : search.query, locale : locale, onlyHidden : search.hiddenOnly } );
			const status = dc.getElementById('keys-list-status');
			const step = Nino.adminUi.ELEMENTLIST_RESULTS;

			if( search.shown === 0 )
				search.shown = step;

			status.textContent = Nino.adminUi.format( word( hits.length === 1 ? '/_admin/text/msg/result' : '/_admin/text/msg/results' ), hits.length );

			if( hits.length === 0 ) {
				body.appendChild( Nino.adminUi.emptyState( word('/_admin/text/msg/nothing') ) );
				return;
			}

			body.appendChild( Nino.admin.textKeys.hitList( hits.slice( 0, search.shown ), locale, {
				href : function( hit ) { return Nino.admin.router.href( 'keys', Nino.admin.textKeys.hashParts( hit.item.row.id, hit.item.field.entry.key ) ) },
				onOpen : function( hit ) { keys._go( Nino.admin.textKeys.hashParts( hit.item.row.id, hit.item.field.entry.key ) ) },
				onLanguage : function( code ) { Nino.admin.workLocale.choose( code ) },
			} ) );

			if( hits.length > search.shown ) {
				body.appendChild( Nino.adminUi.button( word('/_admin/text/label/more'), function() {
					search.shown += step;
					keys._renderBody();
				}, { kind : 'secondary' } ) );
			}
		},

		/**
		 *	The workbench's content language moved (Nino.admin.workLocale), and the shell has asked about the unsaved input of this tab first: the rows are named in
		 *	the other language, the table of the open row says its texts in it, and a record that is open is drawn again with that language first - what it held
		 *	is saved or given up by now. The form of a new key says in which language its start value is written; the scan says it in its bar
		 *
		 *	@param		{string}	locale
		 *
		 *	@return		void
		 */
		_followLocale : function( locale ) {

			const keys = Nino.admin.keys;

			if( keys._data === null || keys._model === null )
				return;

			keys._buildModel();
			keys._search.shown = 0;

			if( dc.getElementById('keys-list-body') !== null )
				keys._renderBody();

			if( keys._scanning === true ) {

				const bar = dc.getElementById('keys-form').querySelector('.nino-admin-contextbar');

				if( bar !== null )
					Nino.adminUi.localeBadge( locale, bar );

				return;
			}

			if( keys._group !== null && keys._model.rows[keys._group] !== undefined )
				keys._drawGroup( keys._group );

			if( keys._record === null || keys._record.isOpen() === false || keys._view === null )
				return;

			if( keys._view.creating === true ) {
				keys._view.mark();
				return;
			}

			keys._redraw();
		},

		// ------------------------------------------------------------------ The keys of a row: the table

		/**
		 *	The rows of the directory of a row of the list: a key each, in the order of the sections and of the templates. What the table shows of a key, and the key
		 *	itself for the record to read
		 *
		 *	@param		{string}	group					The row's id
		 *
		 *	@return		{Array}
		 */
		_rows : function( group ) {

			const keys = Nino.admin.keys;
			const T = Nino.admin.textKeys;
			const locale = keys._locale();
			const all = word('/_admin/text/label/all-languages');
			const hidden = word('/_admin/keys/label/blacklist');
			const rows = [];

			T.sections( keys._model, keys._model.rows[group] ).forEach( function( section ) {

				const place = section.route === '' ? section.label : section.label+ ' ('+ section.route+ ')';

				section.fields.forEach( function( field ) {

					const entry = field.entry;
					const shown = T.plain( T.valueOf( entry, locale ) );
					const formatWord = keys.FORMATS.indexOf( entry.format ) === -1 ? String( entry.format ) : word( '/_admin/keys/format/'+ entry.format );
					const states = [];

					if( entry.global === true )
						states.push( { text : all } );

					if( entry.blacklisted === true )
						states.push( { text : hidden, state : 'warn' } );

					rows.push( {
						key : entry.key,
						entry : entry,
						name : field.label,
						place : place,
						sectionId : section.id,
						title : [ place, field.label ].filter( function( part, at, parts ) { return at === 0 || part !== parts[at - 1] } ).join(' › '),
						text : shown.length > 200 ? shown.slice( 0, 200 )+ '…' : shown,
						format : entry.formatSet === true ? formatWord : Nino.adminUi.format( word('/_admin/keys/col/auto'), formatWord ),
						limit : entry.maxlengthSet === true ? String( entry.maxlength ) : Nino.adminUi.format( word('/_admin/keys/col/auto'), entry.maxlength ),
						hidden : entry.blacklisted === true,
						states : states,
						// What a person looks for: every language's text, whole
						found : Object.keys( entry.values ).map( function( code ) { return T.plain( entry.values[code] ) } ).join(' '),
					} );
				} );
			} );

			return rows;
		},

		/**
		 *	The row of one key, as the directory and the record have it: null for a key that is not in the open row
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Object|null}
		 */
		_find : function( key ) {

			const keys = Nino.admin.keys;

			if( keys._group === null || keys._model === null || keys._model.rows[keys._group] === undefined )
				return null;

			const row = keys._rows( keys._group ).find( function( one ) { return one.key === key } );

			return row === undefined ? null : row;
		},

		/**
		 *	The key of the model, whatever row the table shows
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Object|undefined}
		 */
		_entryOf : function( key ) {

			const keys = Nino.admin.keys;

			if( keys._model === null || keys._model.rowOf[key] === undefined )
				return undefined;

			return ( keys._groups[keys._model.rowOf[key]] ?? [] ).find( function( entry ) { return entry.key === key } );
		},

		/**
		 *	The keys of a row: the way back and the name of the row in the context bar, and under it the directory - the section as its category, the search, the
		 *	select "Show", and the table in the order the templates read the keys - with the bar of the list under it. What the person chose in it is kept for the
		 *	row while the tab lives
		 *
		 *	@param		{string}	group					The row's id
		 *
		 *	@return		void
		 */
		_drawGroup : function( group ) {

			const keys = Nino.admin.keys;
			const T = Nino.admin.textKeys;
			const model = keys._model;
			const row = model.rows[group];
			const locale = keys._locale();

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';

			const back = Nino.adminUi.contextBar( {
				back : { to : word('/_admin/text/label/back-name'), href : Nino.admin.router.href( 'keys', [] ), onClick : function() { keys._moved = true; Nino.admin.router.go( 'keys', [] ); keys.showCurrent() } },
				title : T.rowLabel( model, row, locale ),
			} );
			wrap.appendChild( back );
			Nino.adminUi.localeBadge( locale, back );

			const mount = Nino.adminUi.el( 'div' );
			wrap.appendChild( mount );

			const sections = T.sections( model, row );

			keys._directory = Nino.adminUi.directory( {
				mount : mount,
				rowKey : 'key',
				state : keys._states[group],
				onState : function( now ) { keys._states[group] = now },
				columns : [
					{ key : 'name', label : word('/_admin/text/col/name'), type : 'string', primary : true, sortable : false, render : keys._nameCell },
					{ key : 'place', label : word('/_admin/text/col/section'), type : 'string', sortable : false, narrow : false },
					{ key : 'text', label : Nino.adminUi.format( word('/_admin/text/col/text'), Nino.adminUi.localeName( locale ) ), type : 'string', cell : 'clamp', fill : true, sortable : false },
					{ key : 'format', label : word('/_admin/keys/label/format'), type : 'string', sortable : false, narrow : false },
					{ key : 'limit', label : word('/_admin/keys/label/limit'), type : 'string', sortable : false, narrow : false },
					{ key : 'states', label : word('/_admin/keys/col/state'), sortable : false, render : keys._stateCell },
				],
				labels : { search : word('/_admin/text/label/search'), empty : word('/_admin/keys/empty'), noMatch : word('/_admin/text/msg/nothing') },
				// The sections of the row, in the order the templates read them; a row that has one section has nothing to narrow to
				category : sections.length < 2 ? undefined : {
					id : 'keys-section',
					label : word('/_admin/text/col/section'),
					value : '',
					options : [ { value : '', label : word('/_admin/text/filter/sections') } ].concat( sections.map( function( section ) {
						return { value : section.id, label : section.route === '' ? section.label : section.label+ ' ('+ section.route+ ')' };
					} ) ),
					match : function( one, value ) { return value === '' || one.sectionId === value },
				},
				filters : [ {
					id : 'keys-show',
					label : word('/_admin/text/filter/show'),
					value : '',
					options : [
						{ value : '', label : word('/_admin/keys/filter/all') },
						{ value : 'hidden', label : word('/_admin/keys/filter/hidden') },
						{ value : 'global', label : word('/_admin/keys/filter/global') },
					],
					match : function( one, value ) {

						if( value === 'hidden' )
							return one.hidden === true;

						return value !== 'global' || one.entry.global === true;
					},
				} ],
				// What a person looks for: the name, the key, the section and the text in every language
				searchText : function( one ) { return [ one.name, one.key, one.place, one.found ].join(' ') },
				actions : keys._actions,
				onOpen : function( one ) {
					keys._directory.notice('');
					keys._record.open( one.key, {} );
				},
				rows : keys._rows( group ),
			} );

			// The form of a new key starts in the namespace and the category of the row
			wrap.appendChild( keys._listBar( function() { return keys._seedOf( group ) } ).el );
		},

		/**
		 *	What the form of a new key starts from where its button stands in a row: the namespace and the category the row stands for, for a row that stands
		 *	for one. Nothing for the others (the rows of the system, the pages without a template)
		 *
		 *	@param		{string}	group					The row's id
		 *
		 *	@return		{Object|null}
		 */
		_seedOf : function( group ) {

			const keys = Nino.admin.keys;
			const parts = String( group ).split('/');

			if( parts.length !== 2 || keys.NAMESPACES.indexOf( parts[0] ) === -1 || keys.SEGMENT.test( parts[1] ) === false )
				return null;

			return { namespace : parts[0], category : parts[1] };
		},

		/**
		 *	The name of a key in the table: its name strong, its key under it in the face of an identifier, each in one line that an ellipsis ends and whole in its tooltip
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
		 *	What a key is besides its text, as badges: a word each, and the colour is not the only thing that says it - one text for all languages, hidden from the Text panel
		 *
		 *	@param		{Array}		value
		 *	@param		{Object}	row
		 *
		 *	@return		{Element|string}
		 */
		_stateCell : function( value, row ) {

			if( row.states.length === 0 )
				return '';

			const cell = Nino.adminUi.el( 'span', 'admin-key-states' );

			row.states.forEach( function( state ) {

				const badge = Nino.adminUi.el( 'span', 'nino-admin-badge', state.text );

				if( state.state !== undefined )
					badge.dataset.state = state.state;

				cell.appendChild( badge );
			} );

			return cell;
		},

		/**
		 *	The button at the end of a row: edit, which opens the record of the key
		 *
		 *	@param		{Object}	row
		 *
		 *	@return		{Array}
		 */
		_actions : function( row ) {

			return [ Nino.adminUi.iconButton( 'pencil', Nino.adminUi.format( word('/_admin/keys/row/open'), row.title ), function( event ) {
				Nino.admin.keys._directory.notice('');
				Nino.admin.keys._record.open( row.key, { opener : event.currentTarget } );
			}, { small : true } ) ];
		},

		/**
		 *	The directory as the record sees it: what is on screen now, whatever drew it last. Before a table is drawn - the form of a new key over the list - it
		 *	holds no row
		 *
		 *	@return		{Object}
		 */
		_texts : function() {

			const keys = Nino.admin.keys;

			return {
				rows : function() { return keys._directory === null ? [] : keys._directory.rows() },
				reveal : function( key ) { return keys._directory === null ? false : keys._directory.reveal( key ) },
				focusRow : function( key ) { return keys._directory === null ? false : keys._directory.focusRow( key ) },
				focusSearch : function() { return keys._directory === null ? false : keys._directory.focusSearch() },
			};
		},

		// ------------------------------------------------------------------ The record of a key

		/**
		 *	The parts of the address that stand before the entry of the record: the row - #text/keys/<row>/<key> -, nothing for the form of a new key (#text/keys/@new),
		 *	and the word and the key of the rename dialog that stands over the record (#text/keys/@rename/<key>) - the address that is on screen is the one the
		 *	record does not read as leading away
		 *
		 *	@return		{Array}
		 */
		_prefix : function() {

			const keys = Nino.admin.keys;

			if( keys._renaming !== null )
				return [ Nino.admin.router.word('rename'), keys._renaming ];

			if( keys._forced !== null )
				return keys._forced.row === null ? [] : [ keys._forced.row ];

			if( keys._wantNew === true || ( keys._record !== null && keys._record.creating() === true ) )
				return [];

			return keys._group === null ? [] : [ keys._group ];
		},

		/**
		 *	The row of the form of a new key: nothing yet, and what the form starts from
		 *
		 *	@return		{Object}
		 */
		_blank : function() {
			return { key : '', blank : true, entry : null, seed : Nino.admin.keys._seed ?? {} };
		},

		/**
		 *	The record of a key: a dialog over the table with the place of the key in its head and its key under it, its text in every language and its settings,
		 *	"Delete text key" at the left of the bar and "Rename", "Save and continue" and "Save" at the right. The form of a new key is the same dialog (`create`)
		 *
		 *	@return		{Object}
		 */
		_makeRecord : function() {

			const keys = Nino.admin.keys;

			return Nino.adminUi.record( {
				screen : 'keys',
				prefix : keys._prefix,
				directory : keys._texts(),
				keyOf : function( row ) { return row.key },
				find : keys._find,
				title : function( row ) { return row.title },
				subtitle : function( row ) { return row.key },
				saveNext : true,
				create : { blank : keys._blank },
				labels : {
					prev : word('/_admin/keys/record/prev'), next : word('/_admin/keys/record/next'),
					createTitle : word('/_admin/keys/label/new'), createSave : word('/_admin/keys/label/create'), created : word('/_admin/keys/msg/created'),
				},
				body : keys._body,
				save : keys._save,
				reload : keys._reload,
				danger : { label : word('/_admin/keys/confirm/delete-title'), run : keys._remove },
				actions : [
					// A key of the system is what the code that reads it asks for: its value is edited, its name is not
					{ label : word('/_admin/common/label/rename'), kind : 'secondary', hidden : function( row ) { return keys._renamable( row.key ) === false }, run : function( row, ctx ) { keys._rename( row.key, ctx.opener ) } },
					// Where the keys could not be read again after a save, the record says so and offers to read them
					{ label : word('/_admin/keys/label/reload'), kind : 'secondary', hidden : function() { return keys._stale === false }, run : function( row, ctx ) { keys._refetch( row.key, ctx.opener ) } },
				],
				// An address that names a key the row does not hold: said over the table
				missing : function() {
					if( keys._directory !== null )
						keys._directory.notice( word('/_admin/keys/record/missing') );
				},
			} );
		},

		/**
		 *	The record, the form of a new key and the scan are views of one tab, and the shell knows a tab by one name of unsaved input: the entry of the record, `keys`.
		 *	The scan is a watched form of its own - `keys-scan` - and the entry of the tab answers for whichever of the two is on screen, so that the question before a way
		 *	out of the tab, the back link of the scan and Ctrl+S reach the one that holds the input
		 *
		 *	@return		void
		 */
		_share : function() {

			const dirty = Nino.admin.dirty;

			if( typeof dirty !== 'object' || typeof dirty.entry !== 'function' )
				return;

			const own = dirty.entry('keys');
			const scan = dirty.entry('keys-scan');

			if( own === null || scan === null )
				return;

			const on = function() { return Nino.admin.keys._scanning === true };

			dirty.register( 'keys', {
				isDirty : function() { return on() === true ? scan.isDirty() : own.isDirty() },
				save : function( done ) { return on() === true ? scan.save( done ) : own.save( done ) },
				discard : function() { return on() === true ? scan.discard() : own.discard() },
				bar : function() { return on() === true ? scan.bar() : own.bar() },
			} );
		},

		/**
		 *	Take the editors of the record that was on screen down, and the fields with them
		 *
		 *	@return		void
		 */
		_release : function() {

			const keys = Nino.admin.keys;

			if( keys._view !== null )
				keys._view.fields.forEach( function( field ) { field.handle.destroy() } );

			keys._view = null;
			keys._ctx = null;
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
			Nino.admin.textKeys.fills( function( callback ) { Nino.admin.keys._apiCall( 'fills', { locale : locale }, callback ) }, done );
		},

		/**
		 *	What a key's shape was when it was drawn, as the controls hold it: the format ('auto' where none was chosen), the limit as typed ('' for automatic),
		 *	whether it is global and whether it is hidden
		 *
		 *	@param		{Object}	entry					Key entry
		 *
		 *	@return		{Object}									{ format, limit, global, hidden }
		 */
		_shapeOf : function( entry ) {
			return { format : entry.formatSet === true ? entry.format : 'auto', limit : entry.maxlengthSet === true ? String( entry.maxlength ) : '', global : entry.global === true, hidden : entry.blacklisted === true };
		},

		/**
		 *	What the controls of the shape hold now
		 *
		 *	@param		{Object}	view
		 *
		 *	@return		{Object}									{ format, limit, global, hidden }
		 */
		_shapeNow : function( view ) {

			const set = view.set;

			return { format : set.format.value, limit : set.limit.value.trim(), global : set.global.checked, hidden : set.hidden.checked };
		},

		/**
		 *	Whether the controls of the shape hold otherwise than they were drawn
		 *
		 *	@param		{Object}	view
		 *
		 *	@return		{boolean}
		 */
		_shapeChanged : function( view ) {

			const base = view.set.base;
			const now = Nino.admin.keys._shapeNow( view );

			return now.format !== base.format || now.limit !== base.limit || now.global !== base.global || now.hidden !== base.hidden;
		},

		/**
		 *	A group of fields of the record: a fieldset with its legend
		 *
		 *	@param		{string}		title
		 *
		 *	@return		{Element}
		 */
		_section : function( title ) {

			const set = dc.createElement('fieldset');

			set.appendChild( Nino.adminUi.el( 'legend', '', title ) );

			return set;
		},

		/**
		 *	The content of the record. A key that exists: the text in every language - the working language first and marked, then the others in their order, a language
		 *	the website does not serve yet marked as the select of the working language marks it - or the one field of a key that is the same in every language,
		 *	each drawn by the rule of the key (a line, several lines, the rich-text editor) with its counter, and under it the settings: the format, the limit, whether it
		 *	is one text for all languages and whether it is hidden from the Text panel. What the fields and the controls are is kept in ctx.view, which the save finds
		 *	there. The form of a new key: see _newBody()
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *
		 *	@return		{Element}
		 */
		_body : function( row, ctx ) {

			const keys = Nino.admin.keys;

			// The fields of the entry that was on screen go with it
			keys._release();

			if( ctx.creating === true )
				return keys._newBody( row, ctx );

			const wrap = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );

			// The root of what the tab's stylesheet arranges in the record: the dialog stands outside the pane of the tab
			wrap.id = 'keys-record';

			const view = { key : row.key, creating : false, fields : [], set : null, wrap : wrap, conflict : [], locale : keys._locale(), draw : null, lock : null };

			/**
			 *	Draw the fields and the controls from what the keys hold now. What a save did not take is drawn as it was typed - carry.texts the text of a language,
			 *	carry.shape the controls of the shape - and says why at its field - carry.say
			 */
			view.draw = function( carry ) {

				carry = carry ?? {};

				view.fields.forEach( function( field ) { field.handle.destroy() } );
				view.fields = [];
				wrap.innerHTML = '';

				const entry = keys._entryOf( view.key ) ?? row.entry;
				const locale = keys._locale();
				const codes = entry.global === true ? [ '*' ] : [ locale ].concat( keys._locales.filter( function( code ) { return code !== locale } ) );

				view.locale = locale;

				const texts = keys._section( word('/_admin/keys/label/text') );

				codes.forEach( function( code ) {

					const marks = [];

					if( entry.global === false && code === locale )
						marks.push( word('/_admin/text/label/working') );

					if( entry.global === false && keys._inactive.indexOf( code ) !== -1 )
						marks.push( word('/_admin/common/word/inactive') );

					const name = entry.global === true ? word('/_admin/text/label/all-languages') : Nino.adminUi.localeName( code, keys._locales );
					const handle = Nino.admin.textKeys.valueField( {
						entry : entry,
						value : Nino.admin.textKeys.valueOf( entry, code === '*' ? locale : code ),
						label : marks.length === 0 ? name : name+ ' ('+ marks.join(', ')+ ')',
						id : 'keys-field-'+ ( ++keys._fieldSeq ),
						writable : true,
						fills : function( done ) { keys._loadFills( code === '*' ? locale : code, done ) },
						keyLine : false,
						badge : false,
						locale : code,
					} );

					// What the field read back after it was drawn: a rich text reads back as the markup the editor built, not always the string that is stored
					const kept = { code : code, handle : handle, base : handle.read() };

					view.fields.push( kept );

					if( carry.texts !== undefined && carry.texts[code] !== undefined )
						handle.write( carry.texts[code] );

					texts.appendChild( handle.el );
				} );

				wrap.appendChild( texts );

				const settings = keys._settings( view, entry, carry );

				wrap.appendChild( settings );

				if( carry.say !== undefined )
					keys._say( view, carry.say );
			};

			view.lock = function( on ) {

				view.fields.forEach( function( field ) {

					if( field.handle.readonly === true )
						return;

					const box = field.handle.control.querySelector !== undefined ? field.handle.control.querySelector('[contenteditable]') : null;

					if( box !== null && box !== undefined )
						box.setAttribute( 'contenteditable', on === true ? 'false' : 'true' );
					else
						field.handle.control.disabled = on === true;
				} );

				[ view.set.format, view.set.limit, view.set.global, view.set.hidden ].forEach( function( control ) { control.disabled = on === true } );
			};

			view.draw();
			ctx.view = view;
			keys._view = view;
			keys._ctx = ctx;

			// The record puts the focus in the first line or box of the form; the editor of a rich text is neither, so it is given the focus once the record is drawn
			if( view.fields.length > 0 && view.fields[0].handle.readonly !== true && /^(INPUT|TEXTAREA)$/.test( view.fields[0].handle.control.tagName ) === false )
				wn.setTimeout( function() {
					if( keys._view === view && view.fields.length > 0 && wrap.contains( dc.activeElement ) === false )
						view.fields[0].handle.focus();
				}, 0 );

			return wrap;
		},

		/**
		 *	The settings of a key: the format the text is kept in, the limit its counter counts to, one text for all languages and whether the key is hidden from the
		 *	Text panel - fields of the workbench, each with its own help. A format that holds less than the one before converts what is stored when it is saved
		 *	(see _save()); the controls and what they held when they were drawn are kept in view.set
		 *
		 *	@param		{Object}	view
		 *	@param		{Object}	entry
		 *	@param		{Object}	carry					What was typed into controls that a save did not take
		 *
		 *	@return		{Element}									The fieldset
		 */
		_settings : function( view, entry, carry ) {

			const keys = Nino.admin.keys;
			const seq = ++keys._fieldSeq;
			const base = keys._shapeOf( entry );
			const shape = Object.assign( {}, base, carry.shape ?? {} );
			const section = keys._section( word('/_admin/keys/label/settings') );

			// The format the value is kept in, and the limit its counter counts to
			const formatField = Nino.adminUi.selectField( {
				id : 'keys-format-'+ seq,
				label : word('/_admin/keys/label/format'),
				help : word('/_admin/keys/help/format'),
				options : [ 'auto' ].concat( keys.FORMATS ).map( function( format ) { return { value : format, label : word( '/_admin/keys/format/'+ format ) } } ),
				value : shape.format,
			} );
			const limitField = Nino.adminUi.textField( {
				id : 'keys-limit-'+ seq,
				label : word('/_admin/keys/label/limit')+ ' ('+ word('/_admin/common/unit/characters')+ ')',
				help : word('/_admin/keys/help/limit'),
				type : 'number',
				value : shape.limit,
				placeholder : word('/_admin/keys/placeholder/limit'),
			} );
			const globalField = Nino.adminUi.switchField( { checked : shape.global, label : word('/_admin/keys/label/global'), help : word('/_admin/keys/help/global'), on : word('/_admin/common/label/on'), off : word('/_admin/common/label/off') } );
			const hiddenField = Nino.adminUi.switchField( { checked : shape.hidden, label : word('/_admin/keys/label/hide'), help : word('/_admin/keys/help/hidden'), on : word('/_admin/common/label/on'), off : word('/_admin/common/label/off') } );

			limitField.querySelector('input').min = '1';
			limitField.querySelector('input').step = '1';

			// The field counts to the limit that is typed, and takes as much: a text may be made longer in the save that raises it
			limitField.querySelector('input').addEventListener( 'input', function( ev ) {

				const typed = ev.target.value.trim();
				const limit = /^\d+$/.test( typed ) === true && Number( typed ) >= 1 ? Number( typed ) : entry.maxlength;

				view.fields.forEach( function( field ) { field.handle.setLimit( limit ) } );
			} );

			[ formatField, limitField, globalField, hiddenField ].forEach( function( control ) { section.appendChild( control ) } );

			view.set = {
				base : base,
				format : formatField.querySelector('select'), formatField : formatField,
				limit : limitField.querySelector('input'), limitField : limitField,
				global : globalField.querySelector('input'), globalField : globalField,
				hidden : hiddenField.querySelector('input'), hiddenField : hiddenField,
			};

			// The limit of a key that was typed and not saved is the limit its fields count to
			if( shape.limit !== base.limit && /^\d+$/.test( shape.limit ) === true && Number( shape.limit ) >= 1 )
				view.fields.forEach( function( field ) { field.handle.setLimit( Number( shape.limit ) ) } );

			return section;
		},

		/**
		 *	Say at its field what a save did not take: the sentence under the text of a language, the format, the limit, or the controls of the shape
		 *
		 *	@param		{Object}	view
		 *	@param		{Object}	say						{ texts : { code : sentence }, format, limit, shape }
		 *
		 *	@return		void
		 */
		_say : function( view, say ) {

			const set = view.set;

			Object.keys( say.texts ?? {} ).forEach( function( code ) {

				const field = view.fields.find( function( one ) { return one.code === code } );

				if( field !== undefined )
					field.handle.fail( say.texts[code] );
			} );

			[ [ 'format', set.formatField, set.format ], [ 'limit', set.limitField, set.limit ] ].forEach( function( part ) {
				if( say[part[0]] !== undefined )
					Nino.adminUi.fieldError( part[1], part[2], 'keys-error-'+ part[2].id, say[part[0]] );
			} );

			if( say.shape !== undefined )
				Nino.adminUi.fieldError( set.hiddenField, set.hidden, 'keys-error-'+ set.hidden.id, say.shape );
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

			return Nino.adminUi.format( word('/_admin/keys/error/invalid'), word('/_admin/keys/label/limit'), word('/_admin/keys/expect/limit') );
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
		 *	The text a conflict is about, by its place, its key and its language, as the question names it
		 *
		 *	@param		{string}	key
		 *	@param		{string}	code
		 *
		 *	@return		{string}
		 */
		_named : function( key, code ) {

			const keys = Nino.admin.keys;
			const place = Nino.admin.textKeys.nameOf( keys._model, key );
			const language = code === '*' ? '' : ', '+ Nino.adminUi.localeName( code, keys._locales );

			return ( place === '' ? '' : place+ ' ' )+ '('+ key+ language+ ')';
		},

		/**
		 *	Save what the record holds: the shape of the key - by keys/save, with all five fields - where it was changed, and each language whose text was changed, by
		 *	keys/savebatch, one request for each with one item; and nothing else, so that a record saved without a change leaves the files as they are. A text
		 *	made shorter in the save that lowers the limit is written BEFORE the shape, or the server would refuse a limit under the text the key holds; every other
		 *	text follows it. A text of a key whose shape changes goes without the text it was read as - the shape says where the text is written - and the text of a
		 *	key whose shape the server turned down is not sent. A format that holds less than the one it replaces and a key that becomes global are asked about once,
		 *	with the heaviest weight, for what they drop. A limit that is no whole number is said at its field before anything is sent. Then the keys are read again
		 *	and the record shows what the server holds: what a request turned down is drawn as it was typed, with the sentence at its field. A language that was
		 *	written by another since the record was drawn is the question of a conflict - Overwrite writes that language again, without the text it was read as
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{Function}	done					done( ok, { row, error, conflict } )
		 *
		 *	@return		void
		 */
		_save : function( row, ctx, done ) {

			const keys = Nino.admin.keys;

			if( ctx.creating === true )
				return keys._saveNew( row, ctx, done );

			const view = ctx.view;
			const key = row.key;
			const entry = keys._entryOf( key ) ?? row.entry;
			const now = function() { return keys._find( key ) ?? row };

			Nino.adminUi.clearFieldErrors( ctx.form );

			const shape = keys._shapeNow( view );
			const reshaped = keys._shapeChanged( view );
			const changed = view.fields.filter( function( field ) { return field.handle.read() !== field.base } );

			// Nothing differs from what is stored: nothing is written, and what is on screen is what is stored
			if( reshaped === false && changed.length === 0 )
				return done( true, { row : now() } );

			// What is wrong is said at the field before anything is sent
			if( reshaped === true ) {

				const sentence = keys._limitProblem( shape.limit );

				if( sentence !== '' ) {
					Nino.adminUi.fieldError( view.set.limitField, view.set.limit, 'keys-error-'+ view.set.limit.id, sentence );
					view.set.limit.focus();
					return done( false, { error : word('/_admin/keys/error/fields'), row : now() } );
				}
			}

			// What is stored is converted and cannot be converted back where a format holds less than the one it replaces, and where a key becomes global, which keeps
			// the text of the native language and drops the others: said once, with what happens to the key
			const formats = keys.FORMATS;
			const native = Nino.adminUi.locales.native;
			const converting = reshaped === true && shape.format !== 'auto' && formats.indexOf( shape.format ) < formats.indexOf( entry.format );
			const losing = reshaped === true && shape.global === true && entry.global === false && keys._droppedBy( entry ).length > 0;

			const proceed = function() { keys._send( row, ctx, entry, shape, reshaped, changed, done ) };

			if( converting === false && losing === false )
				return proceed();

			const details = [];

			if( converting === true )
				details.push( Nino.adminUi.format( word('/_admin/keys/confirm/format-line'), key, word( '/_admin/keys/format/'+ entry.format ), word( '/_admin/keys/format/'+ shape.format ), word( '/_admin/keys/convert/'+ shape.format ) ) );

			if( losing === true )
				details.push( Nino.adminUi.format( word('/_admin/keys/confirm/global-line'), key, keys._droppedBy( entry ).map( function( code ) { return Nino.adminUi.localeName( code ) } ).join(', '), Nino.adminUi.localeName( native ) ) );

			Nino.adminUi.confirm( 'destructive', {
				title			: word('/_admin/keys/confirm/convert-title'),
				message		: Nino.content.getText('/_admin/keys/confirm/convert'),
				details		: details,
				yes				: word('/_admin/keys/confirm/convert-title'),
				no				: word('/_admin/common/label/cancel'),
				nested		: true,
				opener		: ctx.bar.primary,
				onChoose	: function( ok ) {

					if( ok === true )
						return proceed();

					// Nothing was sent: the record is as it was typed
					done( false, { error : '', row : now() } );
					ctx.status.idle();
				},
			} );
		},

		/**
		 *	The requests of a save, one after the other, and then the keys read again and the record drawn from them
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{Object}		entry					The key as the model holds it
		 *	@param		{Object}		shape					What the controls of the shape hold
		 *	@param		{boolean}		reshaped			Whether the shape is changed
		 *	@param		{Array}			changed				The fields whose text is changed
		 *	@param		{Function}	done
		 *
		 *	@return		void
		 */
		_send : function( row, ctx, entry, shape, reshaped, changed, done ) {

			const keys = Nino.admin.keys;
			const T = Nino.admin.textKeys;
			const view = ctx.view;
			const key = row.key;
			const locale = view.locale;
			const native = Nino.adminUi.locales.native;
			const typed = { texts : {}, shape : shape };
			const refused = { texts : {}, shape : false };
			const say = { texts : {} };
			const written = {};
			const conflicts = [];

			view.fields.forEach( function( field ) { typed.texts[field.code] = field.handle.read() } );

			// A key that becomes global keeps the text of the native language and drops the others (which the question named): only that text is written
			const wanted = changed.filter( function( field ) { return reshaped === false || shape.global === false || entry.global === true || field.code === native } );

			// The language a text is written in follows the shape the key has after the save
			const items = wanted.map( function( field ) {

				const target = shape.global === true ? '*' : ( field.code === '*' ? locale : field.code );
				const item = { key : key, locale : target, value : typed.texts[field.code] };

				// The text it was read as, so that a text another tab wrote since is not written over unseen - not for a key whose shape is changed in this very save,
				// which moves the text where it was read from
				if( reshaped === false )
					item.was = keys._stamps[target+ ' '+ key] ?? T.valueOf( entry, field.code === '*' ? locale : field.code );

				// The language that was turned down as changed is written over what is there: forced, without the text it was read as
				if( ctx.overwrite === true && view.conflict.indexOf( field.code ) !== -1 ) {
					delete item.was;
					item.force = true;
				}

				return { code : field.code, item : item };
			} );

			// A text that is made shorter in the save that lowers the limit is written first, or the server would refuse a limit under the text the key holds
			const early = reshaped === true && shape.limit !== '' && Number( shape.limit ) < entry.maxlength && shape.global === entry.global;

			view.lock( true );

			const finish = function() {

				keys._load( function( status, response ) {

					view.lock( false );

					const failed = Object.keys( refused.texts ).length + ( refused.shape === true ? 1 : 0 );

					// What was typed and not written is drawn as it was typed - the text of a language, the shape where that was what was refused
					const carry = { texts : {}, say : { texts : refused.texts } };

					items.forEach( function( one ) {
						if( written[one.code] !== true )
							carry.texts[one.code] = typed.texts[one.code];
					} );

					[ 'format', 'limit', 'shape' ].forEach( function( part ) {
						if( say[part] !== undefined ) {
							carry.say[part] = say[part];
							carry.shape = typed.shape;
						}
					} );

					if( status !== 200 || response === null ) {
						keys._stay( ctx, view, typed, written, say, refused, done, now );
						return;
					}

					keys._after( ctx, view, key, carry, function( made ) {

						if( conflicts.length > 0 ) {

							view.conflict = conflicts.map( function( one ) { return one.code } );

							return done( false, { conflict : true, error : Nino.adminUi.format( word('/_admin/text/error/changed'), conflicts.map( function( one ) { return keys._named( key, one.code ) } ).join(', ') ), row : made } );
						}

						view.conflict = [];

						if( failed > 0 )
							return done( false, { error : word('/_admin/keys/error/one'), row : made } );

						done( true, { row : made } );
					} );
				} );
			};

			const now = function() { return keys._find( key ) ?? row };

			const writeText = function( list, next ) {

				if( list.length === 0 )
					return next();

				const one = list[0];

				keys._apiCall( 'savebatch', { items : [ one.item ] }, function( status, response ) {

					if( status !== 200 || response === null ) {
						refused.texts[one.code] = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
						return writeText( list.slice( 1 ), next );
					}

					const result = ( response.results ?? {} )[key];

					if( result !== undefined && result.error === 'changed' ) {

						conflicts.push( one );

						if( one.item.was !== undefined )
							keys._stamps[one.item.locale+ ' '+ key] = one.item.was;
					}
					else if( result === undefined || result.ok !== true )
						refused.texts[one.code] = word('/_admin/text/error/key');
					else {
						written[one.code] = true;
						delete keys._stamps[one.item.locale+ ' '+ key];
						keys._wrote( [ 'texts' ] );
					}

					writeText( list.slice( 1 ), next );
				} );
			};

			const writeShape = function( next ) {

				if( reshaped === false )
					return next();

				keys._apiCall( 'save', { key : key, global : shape.global, blacklisted : shape.hidden, format : shape.format, maxlength : shape.limit === '' ? null : Number( shape.limit ) }, function( status, response ) {

					if( status !== 200 || response === null ) {

						const sentence = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
						const named = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';

						refused.shape = true;
						say[named === 'format' ? 'format' : ( named === 'maxlength' ? 'limit' : 'shape' )] = sentence;
					}
					else {
						written.shape = true;
						keys._wrote( [ 'texts' ] );
					}

					next();
				} );
			};

			if( early === true )
				writeText( items, function() { writeShape( finish ) } );
			else
				writeShape( function() {
					// The text of a key whose shape was turned down is not sent
					writeText( refused.shape === true ? [] : items, finish );
				} );
		},

		/**
		 *	The keys were read again after a save: the table is given the row as it is stored and the record is drawn from it, with what a request turned down as it
		 *	was typed. The focus that stood in a field that was drawn anew goes to the main button
		 *
		 *	@param		{Object}		ctx
		 *	@param		{Object}		view
		 *	@param		{string}		key
		 *	@param		{Object}		carry					See view.draw()
		 *	@param		{Function}	then					then( row ) - the row as it is stored now
		 *
		 *	@return		void
		 */
		_after : function( ctx, view, key, carry, then ) {

			const keys = Nino.admin.keys;
			const held = dc.activeElement ?? null;

			keys._stale = false;

			if( keys._directory !== null && keys._group !== null && keys._model.rows[keys._group] !== undefined )
				keys._directory.update( keys._rows( keys._group ), key );

			view.draw( carry );

			if( held !== null && held !== undefined && view.wrap.contains( held ) === false && held.isConnected === false && ctx.bar !== null && ctx.bar.primary !== undefined )
				ctx.bar.primary.focus();

			then( keys._find( key ) );
		},

		/**
		 *	The save was written and the keys could not be read again: the record stays as it is, says that it was saved and not read again, what was written counts
		 *	as saved and what was turned down stays unsaved at its field, and a button reads the keys anew
		 *
		 *	@param		{Object}		ctx
		 *	@param		{Object}		view
		 *	@param		{Object}		typed					{ texts, shape } as it was typed
		 *	@param		{Object}		written				code => true for a text that was written, shape for the shape
		 *	@param		{Object}		say						The sentences at the shape
		 *	@param		{Object}		refused				The sentences at the texts
		 *	@param		{Function}	done
		 *	@param		{Function}	now						The row as the table has it
		 *
		 *	@return		void
		 */
		_stay : function( ctx, view, typed, written, say, refused, done, now ) {

			const keys = Nino.admin.keys;

			Object.keys( typed.texts ).forEach( function( code ) {

				const field = view.fields.find( function( one ) { return one.code === code } );

				if( written[code] === true && field !== undefined )
					field.base = typed.texts[code];
			} );

			if( written.shape === true )
				view.set.base = typed.shape;

			keys._say( view, { texts : refused.texts, format : say.format, limit : say.limit, shape : say.shape } );
			keys._stale = true;

			done( false, { error : word('/_admin/keys/error/reload'), row : now() } );
		},

		/**
		 *	The button Reload of a record whose keys could not be read again: ask about the input that would be lost, read the keys and draw the record from them
		 *
		 *	@param		{string}		key
		 *	@param		{Element}		opener
		 *
		 *	@return		void
		 */
		_refetch : function( key, opener ) {

			const keys = Nino.admin.keys;

			keys._guarded( key, opener, function() {

				keys._load( function( status, response ) {

					if( status !== 200 || response === null ) {

						if( keys._ctx !== null && keys._ctx.status !== null )
							keys._ctx.status.error( status, response, '/_admin/common/error/load' );

						return;
					}

					keys._stale = false;
					keys._stamps = {};

					if( keys._directory !== null && keys._model.rows[keys._group] !== undefined )
						keys._directory.setRows( keys._rows( keys._group ), { keep : true } );

					if( keys._ctx !== null && keys._ctx.status !== null )
						keys._ctx.status.idle();

					keys._redraw();
				} );
			} );
		},

		/**
		 *	Draw the open record again from what the keys hold, as the next entry of the rows is drawn - or, for a key the rows the directory shows do not hold, in
		 *	place - and take it for saved
		 *
		 *	@return		void
		 */
		_redraw : function() {

			const keys = Nino.admin.keys;
			const view = keys._view;

			if( view === null || keys._ctx === null )
				return;

			if( keys._record.key() !== null && keys._directory !== null && keys._directory.rows().some( function( row ) { return row.key === keys._record.key() } ) === true ) {
				keys._ctx.go( 0 );
				return;
			}

			view.draw();

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'keys' );
		},

		/**
		 *	Ask the shell about the input the record holds before something that would lose it - the rename, a reload -, and run proceed() where the answer lets it go
		 *	on. A Discard of the shell's question takes the record away; the record of the key is opened again where it did, at the address it has
		 *
		 *	@param		{string}		key
		 *	@param		{Element}		opener
		 *	@param		{Function}	proceed
		 *
		 *	@return		void
		 */
		_guarded : function( key, opener, proceed ) {

			const keys = Nino.admin.keys;

			if( typeof Nino.admin.dirty !== 'object' )
				return proceed();

			Nino.admin.dirty.guard( [ 'keys' ], function() {

				if( keys._record.isOpen() === false )
					keys._record.open( key, { opener : opener } );

				proceed();
			} );
		},

		/**
		 *	Read the keys again for the answer Reload of a conflict: what is stored is what the record and the table show, and what this tab typed is dropped
		 *
		 *	@param		{string}		key
		 *	@param		{Function}	done					done( row ) with the row of the key as it is stored now, null where it could not be read or is gone
		 *
		 *	@return		void
		 */
		_reload : function( key, done ) {

			const keys = Nino.admin.keys;

			keys._load( function( status, response ) {

				if( status !== 200 || response === null ) {

					if( keys._ctx !== null && keys._ctx.status !== null )
						keys._ctx.status.error( status, response, '/_admin/common/error/load' );

					return done( null );
				}

				keys._stamps = {};

				if( keys._view !== null )
					keys._view.conflict = [];


				if( keys._group === null || keys._model.rows[keys._group] === undefined )
					return done( null );

				if( keys._directory !== null )
					keys._directory.setRows( keys._rows( keys._group ), { keep : true } );

				done( keys._find( key ) );
			} );
		},

		/**
		 *	Delete a key entirely, after the question of its weight, and draw the table without it - or the list, where it was the last of the row. A refusal stands in
		 *	the line of the record's bar
		 *
		 *	@param		{Object}		row
		 *	@param		{Element}		opener
		 *	@param		{Function}	done					done( true ) where it went, done( false, { error } ) where it failed, done( null ) where the answer was no
		 *
		 *	@return		void
		 */
		_remove : function( row, opener, done ) {

			const keys = Nino.admin.keys;
			const key = row.key;

			Nino.adminUi.confirm( 'destructive', {
				title			: word('/_admin/keys/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/keys/confirm/delete'), key ),
				yes				: word('/_admin/keys/confirm/delete-title'),
				no				: word('/_admin/common/label/cancel'),
				nested		: true,
				opener		: opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return done( null );

					keys._apiCall( 'delete', { key : key }, function( status, response ) {

						if( status !== 200 || response === null )
							return done( false, { error : Nino.adminUi.api.errorText( status, response, '/_admin/common/error/delete' ) } );

						keys._wrote( [ 'texts' ] );

						keys._load( function( loadStatus, loadResponse ) {

							// The key is gone: where the keys cannot be read again the model is made without it
							if( loadStatus !== 200 || loadResponse === null ) {
								keys._data.keys = keys._data.keys.filter( function( entry ) { return entry.key !== key } );
								keys._buildModel();
							}


							if( keys._group !== null && keys._model.rows[keys._group] === undefined ) {

								// The last key of the row: the list is what is behind the record
								keys._level( null );
								return done( true );
							}

							if( keys._directory !== null )
								keys._directory.setRows( keys._rows( keys._group ), { keep : true } );

							done( true );
						} );
					} );
				},
			} );
		},

		// ------------------------------------------------------------------ A key that is made

		/**
		 *	Open the form of a new key in the record, at #text/keys/@new
		 *
		 *	@param		{Element}		opener				The button that was pressed: the focus goes back to it
		 *	@param		{Object|null}	seed				{ namespace, category } the form starts from
		 *
		 *	@return		void
		 */
		_newKey : function( opener, seed ) {

			const keys = Nino.admin.keys;

			keys._seed = seed;
			keys._wantNew = true;

			try {
				keys._record.create( { opener : opener } );
			}
			finally {
				keys._wantNew = false;
			}
		},

		/**
		 *	The four parts a key is filled into the form as: its own if it follows the grammar, otherwise /project and nothing - a key somebody
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
		 *	The key the four parts make
		 *
		 *	@param		{Object}	segments				{ namespace, category, part, name }
		 *
		 *	@return		{string}
		 */
		_composeKey : function( segments ) {
			return '/'+ [ segments.namespace, segments.category, segments.part, segments.name ].join('/');
		},

		/**
		 *	Draw the fields of the four parts of a key into a container, for the form of a new key and for the rename: a field for each part, and under them the key
		 *	they make and the name it will be read by. The namespace is a choice that offers /project to begin with - the others, which belong to a template, a feature
		 *	or a module, after "More namespaces", or at once for a key that is in one; the category is a choice from what there is where it is a template, a feature or
		 *	a module, and a field with suggestions for /project. What is wrong is said at the field when the form is sent (check()), and a part that is no word of the
		 *	vocabulary is said as it is typed, which it may be all the same
		 *
		 *	@param		{Element}		host					Where the fields are put
		 *	@param		{Object}		initial				{ namespace, category, part, name }
		 *
		 *	@return		{Object}									{ read(), key(), check(), fields : { category, part, name }, controls : { namespace, category, part, name }, focus() }
		 */
		_segmentForm : function( host, initial ) {

			const keys = Nino.admin.keys;
			let unlocked = initial.namespace !== 'project';

			const namespaceField = Nino.adminUi.selectField( {
				id : 'keys-form-namespace',
				label : word('/_admin/keys/label/namespace'),
				options : [ { value : initial.namespace, label : '/'+ initial.namespace } ],
				value : initial.namespace,
			} );
			const namespace = namespaceField.querySelector('select');

			// The namespace: one choice at first, all of them after the unlock
			const fillNamespaces = function( chosen ) {

				namespace.innerHTML = '';

				keys.NAMESPACES.filter( function( name ) { return name === 'project' || unlocked === true } ).forEach( function( name ) {
					const option = dc.createElement('option');
					option.value = name;
					option.textContent = '/'+ name;
					option.selected = name === chosen;
					namespace.appendChild( option );
				} );

				namespace.value = chosen;
			};

			fillNamespaces( initial.namespace );
			host.appendChild( namespaceField );

			const unlockField = Nino.adminUi.switchField( {
				checked : unlocked,
				label : word('/_admin/keys/label/unlock'),
				help : word('/_admin/keys/hint/unlock'),
				on : word('/_admin/common/label/on'),
				off : word('/_admin/common/label/off'),
			} );
			const unlock = unlockField.querySelector('input');

			unlock.addEventListener( 'change', function() {
				unlocked = unlock.checked;
				fillNamespaces( unlocked === true ? namespace.value : 'project' );
				fillCategory( '' );
				refresh();
			} );
			host.appendChild( unlockField );

			// The category: a choice where there is a list to choose from, a field with suggestions where the project may name its own
			const segmentHelp = word('/_admin/keys/hint/segment');
			let categoryField = null;
			let category = null;

			const fillCategory = function( chosen ) {

				const offered = ( keys._categories[namespace.value] ?? [] ).slice();
				const old = dc.getElementById('keys-form-categories');

				if( old !== null && old !== undefined )
					old.remove();

				let field;

				if( namespace.value === 'project' ) {
					field = Nino.adminUi.textField( { id : 'keys-form-category', label : word('/_admin/keys/label/category'), help : segmentHelp, value : chosen, required : true, autocomplete : 'off', placeholder : 'company' } );
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
					host.appendChild( list );
				} else {
					// A category the key has that is not on offer any more (a feature that was taken out) stays choosable, so a rename can keep it
					if( chosen !== '' && offered.indexOf( chosen ) === -1 )
						offered.unshift( chosen );

					field = Nino.adminUi.selectField( {
						id : 'keys-form-category',
						label : word('/_admin/keys/label/category'),
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
					host.appendChild( field );
				else
					categoryField.replaceWith( field );

				categoryField = field;
			};

			fillCategory( initial.category );

			const segment = function( id, labelKey, value, placeholder ) {
				const field = Nino.adminUi.textField( { id : id, label : word( labelKey ), help : segmentHelp, value : value, required : true, autocomplete : 'off', placeholder : placeholder } );
				field.querySelector('input').spellcheck = false;
				field.querySelector('input').addEventListener( 'input', refresh );
				host.appendChild( field );
				return field;
			};

			const partField = segment( 'keys-form-part', '/_admin/keys/label/part', initial.part, 'contact' );
			const nameField = segment( 'keys-form-name', '/_admin/keys/label/name', initial.name, 'phone' );

			// The key the four make and the name it is read by, said as they are typed
			const composedField = Nino.adminUi.el( 'div', 'nino-admin-field' );
			const composedBody = Nino.adminUi.el( 'div', 'admin-key-composed' );
			const composedKey = Nino.adminUi.el( 'code', '' );
			const shown = Nino.adminUi.el( 'small', 'admin-key-note' );

			composedKey.id = 'keys-form-preview';
			shown.id = 'keys-form-shown';
			composedBody.setAttribute( 'aria-live', 'polite' );
			composedBody.appendChild( composedKey );
			composedBody.appendChild( shown );
			composedField.appendChild( Nino.adminUi.el( 'span', '', word('/_admin/keys/label/key') ) );
			composedField.appendChild( composedBody );
			host.appendChild( composedField );

			const input = function( field ) { return field.querySelector('input') };

			const read = function() {
				return { namespace : String( namespace.value ), category : String( category.value ), part : String( input( partField ).value ), name : String( input( nameField ).value ) };
			};

			// What the fields say now: the key and its name, and which segment is no word of the vocabulary - which it may be all the same
			function refresh() {

				const segments = read();

				composedKey.textContent = keys._composeKey( segments );

				const valid = keys.SEGMENT.test( segments.part ) === true && keys.SEGMENT.test( segments.name ) === true;
				shown.textContent = valid === true ? word('/_admin/keys/label/shown-as')+ ' '+ Nino.adminUi.slugLabel( segments.part )+ ' › '+ Nino.adminUi.slugLabel( segments.name ) : '';

				[ [ categoryField, segments.category ], [ partField, segments.part ], [ nameField, segments.name ] ].forEach( function( pair ) {

					const old = pair[0].querySelector('.admin-key-note');

					if( old !== null )
						old.remove();

					// A slug of a key that is no word still saves: it is said what it will read like
					if( pair[1] !== '' && keys.SEGMENT.test( pair[1] ) === true && Nino.adminUi.isWord( pair[1] ) === false )
						pair[0].appendChild( Nino.adminUi.el( 'small', 'admin-key-note', Nino.adminUi.format( word('/_admin/keys/hint/not-a-word'), pair[1], Nino.adminUi.slugLabel( pair[1] ) ) ) );
				} );
			}

			namespace.addEventListener( 'change', function() { fillCategory( '' ); refresh() } );

			refresh();

			return {
				read : read,
				key : function() { return keys._composeKey( read() ) },
				controls : { namespace : namespace, category : function() { return category }, part : input( partField ), name : input( nameField ) },
				fields : { category : function() { return categoryField }, part : partField, name : nameField },
				/**
				 *	Say what is wrong with the parts before anything is sent - the sentence under each field - and answer the first control that is wrong, null where they are fine
				 */
				check : function() {

					const segments = read();
					let first = null;

					Nino.adminUi.clearFieldErrors( host );

					[ [ 'keys-form-category', segments.category, '/_admin/keys/label/category' ], [ 'keys-form-part', segments.part, '/_admin/keys/label/part' ], [ 'keys-form-name', segments.name, '/_admin/keys/label/name' ] ].forEach( function( rule ) {

						const control = dc.getElementById( rule[0] );
						const name = word( rule[2] );
						let sentence = '';

						if( rule[1] === '' )
							sentence = Nino.adminUi.format( word('/_admin/common/error/missing'), name );
						else if( keys.SEGMENT.test( rule[1] ) === false )
							sentence = Nino.adminUi.format( word('/_admin/keys/error/invalid'), name, word('/_admin/keys/expect/segment') );

						if( sentence === '' )
							return;

						Nino.adminUi.fieldError( control.closest('label'), control, 'keys-error-'+ rule[0], sentence );
						first = first ?? control;
					} );

					return first;
				},
			};
		},

		/**
		 *	The content of the form of a new key: the four parts of the key, one text for all languages, and the start value, written in the language the workbench
		 *	edits content in - which the form says where it is not the native one. The record is opened at the namespace and the category of the row the button stood in
		 *
		 *	@param		{Object}		row						See _blank()
		 *	@param		{Object}		ctx
		 *
		 *	@return		{Element}
		 */
		_newBody : function( row, ctx ) {

			const keys = Nino.admin.keys;
			const wrap = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );

			wrap.id = 'keys-record';

			const view = { key : null, creating : true, fields : [], wrap : wrap, form : null, global : null, value : null, badge : Nino.adminUi.el( 'p', 'admin-key-worklang' ), mark : null };

			view.mark = function() {

				view.badge.innerHTML = '';

				const badge = Nino.adminUi.localeBadge( keys._locale() );

				if( badge !== null )
					view.badge.appendChild( badge );

				view.badge.hidden = badge === null;
			};

			view.mark();
			wrap.appendChild( view.badge );

			const section = keys._section( word('/_admin/keys/label/new') );
			const initial = Object.assign( { namespace : 'project', category : '', part : '', name : '' }, row.seed ?? {} );

			view.form = keys._segmentForm( section, initial );

			const globalField = Nino.adminUi.switchField( {
				label : word('/_admin/keys/label/global'),
				help : word('/_admin/keys/help/global'),
				on : word('/_admin/common/label/on'),
				off : word('/_admin/common/label/off'),
			} );

			view.global = globalField.querySelector('input');
			view.global.id = 'keys-form-new-global';
			section.appendChild( globalField );

			const value = Nino.adminUi.textareaField( {
				id : 'keys-form-new-value',
				label : word('/_admin/keys/label/initial'),
				help : word('/_admin/keys/help/initial'),
				rows : 2,
				fills : { editable : true, load : function( done ) { keys._loadFills( keys._locale(), done ) } },
			} );

			view.value = value.querySelector('textarea');
			section.appendChild( value );
			wrap.appendChild( section );

			ctx.view = view;
			keys._view = view;
			keys._ctx = ctx;

			return wrap;
		},

		/**
		 *	Create the key the form holds. The parts are checked first and what is wrong is said at its field; the server decides them again, and what it refuses
		 *	stands at the field it names. The key has its own place once it is made: the keys are read again, the table of its row is drawn behind the record, and the record
		 *	is the record of the new key, at its address
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{Function}	done
		 *
		 *	@return		void
		 */
		_saveNew : function( row, ctx, done ) {

			const keys = Nino.admin.keys;
			const view = ctx.view;
			const first = view.form.check();

			if( first !== null ) {
				first.focus();
				return done( false, { error : word('/_admin/keys/error/fields') } );
			}

			const key = view.form.key();
			const value = view.value.value;

			keys._apiCall( 'create', { key : key, global : view.global.checked, value : value, values : { [ keys._locale() ] : value } }, function( status, response ) {

				if( status !== 200 || response === null ) {

					// A key the server refuses is refused at the last part, which is where the name of it ends
					const named = response !== null && typeof response === 'object' ? response.field : '';

					if( named === 'key' || named === 'newKey' ) {
						const input = view.form.controls.name;
						Nino.adminUi.fieldError( input.closest('label'), input, 'keys-error-keys-form-name', Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
						input.focus();
					}

					return done( false, { error : Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) } );
				}

				// A key of a template is one the rows of that template did not have
				keys._wrote( key.indexOf('/template/') === 0 ? [ 'texts', 'templates' ] : [ 'texts' ] );

				keys._load( function( loadStatus, loadResponse ) {

					if( loadStatus !== 200 || loadResponse === null )
						return done( false, { error : word('/_admin/keys/error/reload') } );

					const group = keys._model.rowOf[key];


					if( group === undefined )
						return done( false, { error : word('/_admin/keys/error/reload') } );

					// The table of the row that holds the key is behind the record, and the address is the key's
					keys._release();
					keys._group = group;
					keys._drawGroup( group );
					keys._shown = 'group';
					Nino.adminUi.showLevel( 'keys-form', { focus : false } );

					done( true, { row : keys._find( key ) } );
				} );
			} );
		},

		// ------------------------------------------------------------------ A key that is renamed

		/**
		 *	The Rename of a record: the dialog over it with the four parts of the key. Input in the record is asked about first; the dialog is an entry of the history
		 *	of its own, #text/keys/@rename/<key>, which Back closes
		 *
		 *	@param		{string}		key
		 *	@param		{Element}		opener
		 *
		 *	@return		void
		 */
		_rename : function( key, opener ) {

			const keys = Nino.admin.keys;

			keys._guarded( key, opener, function() {
				keys._openRename( key, opener, true );
			} );
		},

		/**
		 *	The address names the rename of a key: the table of its row, the record of the key and the dialog over it, as one entry of the history - the one the
		 *	address is. The record of a key that is not there is the notice over the table
		 *
		 *	@param		{string}		key
		 *
		 *	@return		void
		 */
		_openRenameByAddress : function( key ) {

			const keys = Nino.admin.keys;
			const router = Nino.admin.router;
			const group = keys._model.rowOf[key];

			if( keys._group !== group || keys._directory === null )
				keys._level( group );

			// The record of the key is what the address says first - also where the form of a new key is open, which reads the parts of the address as a key
			router.set( 'keys', [ group, key ] );

			if( keys._record.creating() === true )
				keys._forced = { row : group };

			try {
				keys._record.follow();
			}
			finally {
				keys._forced = null;
			}

			if( keys._record.isOpen() === false || keys._record.key() !== key )
				return;

			keys._openRename( key, null, false );
		},

		/**
		 *	Open the dialog that renames a key: the four parts of its name, the key they make and, once the parts are fine, the question before the rename
		 *
		 *	@param		{string}			key
		 *	@param		{Element|null}	opener
		 *	@param		{boolean}			push			The address is a new entry of the history - a person's own move - and not written over the one that named the rename
		 *
		 *	@return		void
		 */
		_openRename : function( key, opener, push ) {

			const keys = Nino.admin.keys;
			const router = Nino.admin.router;
			const host = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );

			host.id = 'keys-rename-fields';

			const form = keys._segmentForm( host, keys._segmentsOf( key ) );
			const name = form.controls.name;

			keys._renaming = key;
			keys._renamePushed = false;
			keys._renameLeaving = false;

			const before = router.position();

			if( push === true ) {
				router.go( 'keys', [ router.word('rename'), key ] );

				if( router.position() !== before ) {
					router.mark('keys-rename');
					keys._renamePushed = true;
				}
			}
			else
				router.set( 'keys', [ router.word('rename'), key ] );

			const closed = function( value ) {

				const back = keys._renameLeaving === false && keys._afterRename === null;
				const pushed = keys._renamePushed;
				const row = keys._group;

				keys._dialog = null;
				keys._renaming = null;
				keys._renamePushed = false;
				keys._renameLeaving = false;

				// A dialog that was closed by its own cancel gives the address back to the record: the entry it made is taken off the history, the address the reload
				// named is written over
				if( value === 'cancel' && back === true ) {

					if( pushed === true && router.marked() === 'keys-rename' && typeof wn.history === 'object' && typeof wn.history.go === 'function' )
						wn.history.go( -1 );
					else
						router.set( 'keys', row === null ? [] : [ row, key ] );
				}
			};

			const handle = Nino.adminUi.formDialog( {
				title : word('/_admin/keys/confirm/rename-title'),
				body : host,
				submit : word('/_admin/keys/confirm/rename-title'),
				cancel : word('/_admin/common/label/cancel'),
				nested : true,
				opener : opener ?? undefined,
				onClose : closed,
				onSubmit : function( values, ctl ) {

					const first = form.check();

					if( first !== null ) {
						first.focus();
						return ctl.idle();
					}

					const target = form.key();

					// A key that is not changed is no rename
					if( target === key ) {
						Nino.adminUi.fieldError( name.closest('label'), name, 'keys-error-keys-form-name', word('/_admin/keys/error/unchanged') );
						name.focus();
						return ctl.idle();
					}

					// Templates may still call the key by its old name: renaming an identifier is asked about
					const asked = Nino.adminUi.confirm( 'big', {
						title		: word('/_admin/keys/confirm/rename-title'),
						message	: Nino.adminUi.format( Nino.content.getText('/_admin/keys/confirm/rename'), key, target ),
						yes			: word('/_admin/keys/confirm/rename-title'),
						no			: word('/_admin/common/label/cancel'),
						nested	: true,
						opener	: name,
						onChoose : function( ok ) {

							if( ok !== true )
								return ctl.idle();

							keys._apiCall( 'rename', { key : key, newKey : target }, function( status, response ) {

								if( status !== 200 || response === null ) {

									// A key the server refuses is refused at the last part, which is where the name of it ends
									const named = response !== null && typeof response === 'object' ? response.field : '';
									const sentence = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/rename' );

									if( named === 'key' || named === 'newKey' ) {
										ctl.idle();
										Nino.adminUi.fieldError( name.closest('label'), name, 'keys-error-keys-form-name', sentence );
										name.focus();
										return;
									}

									return ctl.fail( null, sentence );
								}

								keys._renamed( key, target, ctl );
							} );
						},
					} );

					if( asked === false )
						ctl.idle();
				},
			} );

			if( handle === false ) {
				keys._renaming = null;
				return;
			}

			keys._dialog = handle;
		},

		/**
		 *	The server renamed the key: the keys are read again, the dialog is closed and the record follows the new key. The history is back at the record of the old key
		 *	first where the dialog made an entry of its own, so that there is one entry for the record and the cross takes it off the history as before
		 *
		 *	@param		{string}		key
		 *	@param		{string}		target
		 *	@param		{Object}		ctl						The control of the dialog
		 *
		 *	@return		void
		 */
		_renamed : function( key, target, ctl ) {

			const keys = Nino.admin.keys;

			keys._wrote( target.indexOf('/template/') === 0 || key.indexOf('/template/') === 0 ? [ 'texts', 'templates' ] : [ 'texts' ] );

			keys._load( function( status, response ) {

				// The key is renamed: where the keys cannot be read again the model is made with the new name
				if( status !== 200 || response === null ) {
					const entry = keys._data.keys.find( function( one ) { return one.key === key } );

					if( entry !== undefined )
						entry.key = target;

					keys._buildModel();
				}


				const group = keys._model.rowOf[target];

				keys._afterRename = { key : target, group : group === undefined ? keys._group : group, pushed : keys._renamePushed };
				ctl.close();

				const router = Nino.admin.router;

				// Back at the record of the old key by the history: the hashchange that comes finishes the rename. Where the dialog made no entry - a reload at its
				// address - it is finished at once
				if( keys._afterRename !== null && keys._afterRename.pushed === true && router.marked() === 'keys-rename' && typeof wn.history === 'object' && typeof wn.history.go === 'function' ) {
					wn.history.go( -1 );
					wn.setTimeout( function() { if( keys._afterRename !== null ) keys._finishRename() }, 1500 );
					return;
				}

				keys._finishRename();
			} );
		},

		/**
		 *	The rename is done and the address is the record's again: the table is drawn for the row of the new key, which may be another, and the record follows the key -
		 *	one address, written over the one the record had
		 *
		 *	@return		void
		 */
		_finishRename : function() {

			const keys = Nino.admin.keys;
			const after = keys._afterRename;

			keys._afterRename = null;

			if( after === null )
				return;

			keys._release();
			keys._group = after.group;
			keys._drawGroup( after.group );
			keys._shown = 'group';
			Nino.adminUi.showLevel( 'keys-form', { focus : false } );

			// The record shows the new key where it stands: no new entry of the history
			keys._record.open( after.key, {} );
			keys._address();
		},

		/**
		 *	An address that leads away while the rename dialog is open - Back, a typed address: the dialog is asked to close as Escape asks it, one with nothing typed
		 *	closes, one with input asks what to do with it. Where it stays the address is written to what the screen shows
		 *
		 *	@return		void
		 */
		_leaveRename : function() {

			const keys = Nino.admin.keys;
			const router = Nino.admin.router;
			const key = keys._renaming;

			keys._renameLeaving = true;

			if( keys._dialog !== null && typeof wn.KeyboardEvent === 'function' )
				keys._dialog.el.dispatchEvent( new wn.KeyboardEvent( 'keydown', { key : 'Escape', bubbles : true, cancelable : true } ) );

			if( keys._dialog === null ) {
				// The address is the record's own: the follow goes on with it
				keys._follow( router.current() );
				return;
			}

			keys._renameLeaving = false;
			router.set( 'keys', [ router.word('rename'), key ] );
		},

		// ------------------------------------------------------------------ The scan

		/**
		 *	The one way back of the scan: "Back to <the tab>", which leaves the scan for the list and puts the focus on what opened it. A scan that holds unsaved input is
		 *	asked about first by the shell
		 *
		 *	@param		{string}		title					The name of the view
		 *
		 *	@return		{Element}
		 */
		_contextBar : function( title ) {

			const keys = Nino.admin.keys;

			const bar = Nino.adminUi.contextBar( {
				back : { to : word('/_admin/text/label/back-name'), href : Nino.admin.router.href( 'keys', [] ), onClick : function() {
					Nino.admin.router.go( 'keys', [] );
					keys._leaveScan( true );
				} },
				title : title,
			} );

			// What is shown and written in a language says which, where it is not the native one
			Nino.adminUi.localeBadge( keys._locale(), bar );

			return bar;
		},

		/**
		 *	Leave the scan for the list, as it stands: the focus goes back to the button that opened the scan where the person came back by the link, else it stays
		 *
		 *	@param		{boolean|null}	back				The person used the way back
		 *
		 *	@return		void
		 */
		_leaveScan : function( back ) {

			const keys = Nino.admin.keys;

			keys._scanning = false;
			keys._scanRows = null;
			keys._line = null;
			keys._group = null;
			keys._directory = null;
			keys._shown = 'list';
			keys._renderBody();

			if( back === true )
				Nino.adminUi.backTo( 'keys-list', keys._opener !== null && keys._opener.isConnected === true ? keys._opener : keys._buttons.scan );
			else
				Nino.adminUi.showLevel( 'keys-list', { focus : false } );
		},

		/**
		 *	Have the shell take the scan form on screen as the one that is stored (see Nino.admin.dirty)
		 *
		 *	@return		void
		 */
		_snapshot : function() {

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'keys-scan' );
		},

		/**
		 *	Open the "missing keys found in templates" scan - see Keys::apiScan(). The scan is a view of its own in the level beside the list: its way back, its form and
		 *	its bar. It says that it is scanning while the scan runs: the rows come in its place
		 *
		 *	@param		{boolean}		[opened]			The person pressed the button: the focus goes into the view
		 *	@param		{boolean}		[person]			...and the address is a step of the history; otherwise it is written over the one that named the scan
		 *
		 *	@return		void
		 */
		_openScan : function( opened, person ) {

			const keys = Nino.admin.keys;

			if( person === true )
				Nino.admin.router.go( 'keys', [ Nino.admin.router.word('scan') ] );

			keys._scanRows = null;
			keys._scanning = true;
			keys._group = null;
			keys._directory = null;
			keys._release();

			const wrap = dc.getElementById('keys-form');
			wrap.innerHTML = '';
			wrap.appendChild( keys._contextBar( word('/_admin/keys/label/scan') ) );

			const loading = Nino.adminUi.el( 'p', 'nino-admin-empty', word('/_admin/common/msg/scanning') );
			loading.id = 'keys-scan-msg';
			loading.setAttribute( 'aria-busy', 'true' );
			wrap.appendChild( loading );

			keys._snapshot();
			Nino.admin.router.set( 'keys', [ Nino.admin.router.word('scan') ] );
			keys._shown = 'group';
			Nino.adminUi.showLevel( 'keys-form', opened === true ? {} : { focus : false } );

			keys._apiCall( 'scan', {}, function( status, response ) {

				// Left in the meantime: the answer is for a view that is not on screen any more
				if( keys._scanning !== true || loading.isConnected === false )
					return;

				if( status !== 200 || response === null ) {
					keys._showError( wrap, status, response );
					return;
				}

				keys._renderScan( response.missing, response.alsoUsed || [] );
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
		_renderScan : function( missing, alsoUsed ) {

			const keys = Nino.admin.keys;
			const wrap = dc.getElementById('keys-form');

			// The view that stood while the scan ran may hold the focus, on its back link: the one that replaces it takes it over
			const held = ( dc.activeElement ?? null ) !== null && dc.activeElement.closest('#keys-form') !== null;

			wrap.innerHTML = '';

			const back = keys._contextBar( word('/_admin/keys/label/scan') );
			wrap.appendChild( back );

			if( held === true )
				back.querySelector('a').focus();

			const form = dc.createElement('form');
			form.id = 'keys-scan-form';
			form.noValidate = true;

			const rows = [];

			// What the scan shows and does not take - where a key is read, what it is - is a line at the height of a name, as at a field
			const shows = function( card, labelKey, words ) {
				const line = Nino.adminUi.el( 'div', 'nino-admin-field' );
				line.appendChild( Nino.adminUi.el( 'span', '', word( labelKey ) ) );
				line.appendChild( Nino.adminUi.el( 'p', 'admin-keys-scan-line', words ) );
				card.appendChild( line );
			};

			if( missing.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( word('/_admin/keys/scan/none') ) );
				keys._line = null;
			}

			missing.forEach( function( item ) {

				const kind = item.kind || 'create';
				const card = dc.createElement('fieldset');
				card.className = 'admin-keys-scan-row';
				card.appendChild( Nino.adminUi.el( 'legend', '', item.key ) );

				shows( card, '/_admin/keys/label/scan-files', item.files.join(', ') );

				let valueInput = null;

				if( kind === 'create' ) {
					const field = Nino.adminUi.textField( { id : 'keys-scan-value-'+ rows.length, label : word('/_admin/keys/label/scan-value'), help : word('/_admin/keys/help/scan-value'), autocomplete : 'off' } );
					valueInput = field.querySelector('input');
					card.appendChild( field );
				}

				const note = keys._scanNote( item );

				if( note !== '' )
					shows( card, '/_admin/keys/label/scan-note', note );

				// A key of the system is not the scan's to retire: it is the
				// system's to write, and ignoring it would only hide the gap
				if( kind !== 'system' ) {
					const ignore = Nino.adminUi.checkField( { id : 'keys-scan-ignore-'+ rows.length, label : word('/_admin/keys/label/scan-ignore'), help : word('/_admin/keys/help/scan-ignore') } );
					card.appendChild( ignore );
					rows.push( { key : item.key, valueInput : valueInput, ignoreCheck : ignore.querySelector('input') } );
				}

				form.appendChild( card );
			} );

			// The keys another template reads as well: a note, with no input and nothing counted
			if( alsoUsed.length > 0 ) {

				const also = dc.createElement('fieldset');
				const legend = dc.createElement('legend');
				legend.textContent = word('/_admin/keys/scan/also-title');
				also.appendChild( legend );
				Nino.adminUi.sectionHelp( legend, also, word('/_admin/keys/scan/also-hint') );

				const ul = dc.createElement('ul');
				ul.className = 'nino-admin-list-dense';
				alsoUsed.forEach( function( item ) {
					ul.appendChild( Nino.adminUi.el( 'li', '', Nino.adminUi.format( word('/_admin/keys/scan/also-line'), item.key, item.files.join(', ') ) ) );
				} );
				also.appendChild( ul );

				form.appendChild( also );
			}

			if( missing.length > 0 ) {

				const msg = dc.createElement('p');
				msg.id = 'keys-scan-msg';
				const bar = Nino.adminUi.actionBar( {
					status : { line : msg, fresh : true },
					primary : Nino.adminUi.button( word('/_admin/common/label/save'), null, { type : 'submit' } ),
				} );

				keys._line = bar.line;
				bar.line.bind( form );
				form.appendChild( bar.el );
				form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); keys._saveScanResults( rows ) } );
			}

			wrap.appendChild( form );

			keys._scanRows = rows;

			// What the scan came with is the baseline of its input
			keys._snapshot();
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
				return word('/_admin/keys/scan/grammar');

			if( item.kind === 'system' )
				return word('/_admin/keys/scan/system-'+ ( item.writer || 'none' ) );

			if( item.hint === 'feature' || item.hint === 'module' )
				return Nino.adminUi.format( word('/_admin/keys/scan/hint-'+ item.hint ), item.owner );

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

			const line = keys._line;

			if( line !== null )
				line.saving();

			const payload = rows.map( function( row ) {
				return { key : row.key, value : row.valueInput === null ? '' : row.valueInput.value, ignore : row.ignoreCheck.checked };
			} );

			// The values are written in the language this tab shows, said in the request: another window of the same session may have moved the session's own
			keys._apiCall( 'scanapply', { rows : payload, locale : keys._locale() }, function( status, response ) {

				if( status !== 200 || response === null ) {
					if( line !== null )
						line.error( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				// What the scan creates are the keys the templates read: a template with one is a row the Text panel did not have
				keys._wrote( response.created > 0 && payload.some( function( row ) { return row.ignore === false && row.key.indexOf('/template/') === 0 } ) === true ? [ 'texts', 'templates' ] : [ 'texts' ] );

				// Back to the list, which reads again: the keys just created belong in their rows, and a retired one belongs in the list as a hidden key that can be brought back
				keys._load( function( loadStatus, loadResponse ) {

					if( loadStatus !== 200 || loadResponse === null ) {
						keys._showError( dc.getElementById('keys-form'), loadStatus, loadResponse );
						report( true );
						return;
					}

					Nino.admin.router.go( 'keys', [] );
					keys._leaveScan( null );

					Nino.adminUi.say( word('/_admin/keys/scan/result')
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

	// The shell asks before anything throws the input of the scan away (see Nino.admin.dirty). The record has its own entry, `keys`, which answers for the scan as
	// well while the scan is on screen (see _share()). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'keys-scan', function() { return Nino.admin.keys._scanning === true ? dc.getElementById('keys-form') : null }, function( done ) {
			if( Nino.admin.keys._scanRows !== null )
				Nino.admin.keys._saveScanResults( Nino.admin.keys._scanRows, done );
			else
				done( true );
		}, function() {
			const form = dc.getElementById('keys-form');
			return form === null || Nino.admin.keys._scanning !== true ? null : form.querySelector('.nino-admin-actionbar');
		} );

})(window, document, document.documentElement, document.body);
