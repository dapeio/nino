

/**
 *	Nino										A compact filesystembased php framework
 *	admin.js								"Routes" panel: the pages of the site's project - create, edit, order
 *													and delete the page routes without hand-editing /nino/http/routes
 *													as raw json (the Config panel no longer edits that key). The
 *													template select only ever offers a templates/page-*.tpl file that
 *													already exists on disk, and a new route starts with every field
 *													empty - see the panel's own Admin/Admin.php class docblock for the
 *													Element URI/Http URI split every entry carries.
 *
 *													The panel is the standard directory of the workbench
 *													(Nino.adminUi.directory()) of the project's pages in the order
 *													they are stored in, and the record of a page
 *													(Nino.adminUi.record()) over it. The order is what orders every
 *													menu those pages appear in (see \Nino\Modules\Navigation), and it
 *													is saved at once: the arrows of a row move a page, and are locked
 *													while a search shows only some of the rows. No column sorts: the
 *													stored order is the one order of the list.
 *
 *													Every view has an address: #routes is the directory,
 *													#routes/routes/<Element URI> the record of a page,
 *													#routes/routes/@new the form of a new route and
 *													#routes/routes/@feature/<Element URI> the record of a page a feature
 *													routes. Two records stand over one screen, each answering only its
 *													own addresses (see _follow()), and the shell asks about unsaved
 *													input of either by the one name of the panel (see _share()).
 *
 *													A page has one Http URI for every language or one for each: a
 *													switch says which, and the record draws the one field or a row per
 *													language - the name, the title and the addresses are values of
 *													every language one under the other (localeRows()), the description
 *													is the one of the language the workbench edits content in
 *													(Nino.admin.workLocale) and the others keep theirs. Languages whose
 *													rows hold one address share it (see _addressMap()). The
 *													navigations a page stands in are the ticks of one list, each named
 *													by its name and, quietly, its key. A save that changed nothing
 *													sends nothing. The list gives every page the stamp of its state,
 *													a save and a delete send back the stamp of the page the form was
 *													drawn from, and a page that was changed or removed since is the
 *													conflict of the workbench: Reload, Cancel or Overwrite.
 *
 *													The pages a feature or a module routes at runtime - which no
 *													config.php has - are a list under the directory, one row per
 *													Element-URI, without buttons and without delete: their record is
 *													the paths, to read, and the name, title and description, saved as
 *													routes/savetexts.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	const word = function( key ) { return Nino.content.getText( key ) };

	Nino.admin.routes = {

		// What the last list answered: the pages in the order they are stored in, the pages of features, and what a record offers - the templates, the languages, the navigations
		_pages 					: [],
		_runtime 				: [],
		_templates 			: [],
		_defaultTemplate : '',
		_locales 				: [],
		_selectedLocale : '',
		_localeNames 		: {},
		_navs 					: [],
		_navNames 			: {},
		// ...and as rows: what the directory shows of a page, what the record reads
		_rows 					: [],
		_featureRows 		: [],
		_ready 					: false,
		_loading 				: false,
		_directory 			: null,
		// The record of a page of the project, and the one of a page a feature routes
		_record 				: null,
		_featureRecord 	: null,
		// The room the list of the pages of features is drawn in, and the button of the bar of the list
		_features 			: null,
		_add 						: null,
		// The columns the directory was drawn with: with the navigations or without - a navigation that comes or goes is another table
		_shape 					: '',
		// What the person sees of the directory - the search, the page - kept while it is drawn again
		_state 					: {},
		// The up and down button of every row that is drawn, by Element URI: the lock follows what the person asks the table to show
		_buttons 				: {},
		// What the body of each record was given, for as long as it is on screen: the fields are kept in ctx.view
		_ctx 						: null,
		_featureCtx 		: null,
		// The stamp of the templates (see Nino.admin.touch()) the list was asked for: the record offers the templates the answer held, and one written since is
		// not among them
		_stamp 					: 0,
		// ...and the stamp of the navigations (the Navigations panel touches it): the ticks of the record are the ones the list held
		_navStamp 			: 0,
		// ...and the stamp of the texts (the Text panel and its Keys tab touch it): the names, titles and descriptions of the list are the ones it held
		_textStamp 			: 0,

		/**
		 *	Load the persisted page list and draw the directory; an address that names a record opens it once the rows are in
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('routes-list') === null )
				return;

			Nino.admin.routes._load( false );
		},

		/**
		 *	The panel is shown. An address of this panel is followed - the record of a page opens, steps or closes, the form of a new route and the record of a page of
		 *	a feature open and close -, and where none is open the list is read anew if a template, a navigation or a text was written since it was read: the record
		 *	offers what the answer held, and the names and titles are the ones the Text panel left. Not while a record is open
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			const panel = Nino.admin.routes;

			if( panel._ready === false )
				return panel.init();

			if( panel._follow() === true )
				return;

			if( panel._record.isOpen() === true || panel._featureRecord.isOpen() === true )
				return;

			if( Nino.admin.stamp( 'templates' ) !== panel._stamp || Nino.admin.stamp( 'navigations' ) !== panel._navStamp || Nino.admin.stamp( 'texts' ) !== panel._textStamp )
				panel._load( true );
		},

		/**
		 *	Ask for the list and take the answer in: the pages, the pages of features, the templates, the languages and the navigations. Drawn the first time with the
		 *	sentence that it is loading; read again, the directory takes the new rows where it stands - its search and its page are what a person chose
		 *
		 *	@param		{boolean}		quiet				A list that is drawn already is read again
		 *	@param		{Function}	[then]			Called with true once the list is drawn, with false where it could not be read
		 *
		 *	@return		void
		 */
		_load : function( quiet, then ) {

			const panel = Nino.admin.routes;
			const told = function( ok ) { if( typeof then === 'function' ) then( ok ) };

			if( panel._loading === true )
				return told( false );

			panel._loading = true;

			// A template written while the answer is on its way is not in it: the stamp is the one from before the request went out
			const stamp = Nino.admin.stamp( 'templates' );
			const navStamp = Nino.admin.stamp( 'navigations' );
			const textStamp = Nino.admin.stamp( 'texts' );

			if( quiet === false || panel._directory === null ) {
				panel._frame();
				panel._directory.loading( word('/_admin/routes/loading') );
			}

			panel._apiCall( 'list', {}, function( status, response ) {

				panel._loading = false;

				if( status !== 200 || response === null ) {
					panel._directory.fail( status, response, '/_admin/common/error/load' );
					return told( false );
				}

				panel._take( response );
				panel._stamp = stamp;
				panel._navStamp = navStamp;
				panel._textStamp = textStamp;
				panel._ready = true;
				panel._draw();

				// An address that names a page, the new one or a page of a feature is followed once the rows are in
				panel._follow();
				told( true );
			} );
		},

		/**
		 *	Take the answer of the list in
		 *
		 *	@param		{Object}		response
		 *
		 *	@return		void
		 */
		_take : function( response ) {

			const panel = Nino.admin.routes;

			panel._pages 						= response.pages;
			panel._runtime 					= response.runtime || [];
			panel._templates 				= response.templates;
			panel._defaultTemplate 	= response.defaultTemplate || '';
			panel._locales 					= response.locales;
			panel._selectedLocale 	= response.selectedLocale || '';
			panel._localeNames 			= response.localeNames || {};
			panel._navs 						= response.navs;
			panel._navNames 				= response.navNames || {};
			panel._rowsOf();
		},

		/**
		 *	The rows of the pages and of the pages of features, from what the panel holds: a name is the one of the language the workbench is on
		 *
		 *	@return		void
		 */
		_rowsOf : function() {

			const panel = Nino.admin.routes;

			panel._rows = panel._pages.map( panel._row );
			panel._featureRows = panel._runtime.map( panel._featureRow );
		},

		/**
		 *	The room the panel is drawn in: the directory, the list of the pages of features under it and, under both, the bar of the list - "New route" as the main
		 *	button. The records stand over it
		 *
		 *	@return		void
		 */
		_frame : function() {

			const panel = Nino.admin.routes;

			if( panel._directory !== null )
				return;

			const wrap = dc.getElementById('routes-list');

			wrap.innerHTML = '';

			const mount = Nino.adminUi.el( 'div' );

			mount.id = 'routes-directory';
			wrap.appendChild( mount );

			panel._features = Nino.adminUi.el( 'div' );
			panel._features.id = 'routes-features';
			wrap.appendChild( panel._features );

			const add = Nino.adminUi.button( word('/_admin/routes/label/new'), function() { panel._record.create( { opener : add } ) } );

			panel._add = add;
			panel._directory = panel._build( mount, panel._state );
			panel._record = panel._makeRecord();
			panel._featureRecord = panel._makeFeatureRecord();
			panel._share();

			wrap.appendChild( Nino.adminUi.actionBar( { list : true, primary : add } ).el );
		},

		/**
		 *	Draw what the last answer holds: the rows of the directory - drawn anew, with the navigations as a column, where the Navigation module came or went - and the
		 *	list of the pages of features
		 *
		 *	@return		void
		 */
		_draw : function() {

			const panel = Nino.admin.routes;

			if( panel._shape !== ( panel._navs.length > 0 ? 'navs' : '' ) )
				panel._directory = panel._build( dc.getElementById('routes-directory'), panel._directory.state() );

			panel._directory.setRows( panel._rows, { keep : true } );
			panel._drawFeatures();
		},

		/**
		 *	The language a page is named in: the one the workbench edits content in (Nino.admin.workLocale) where the website serves it, else the one the server named
		 *	when the list was read
		 *
		 *	@return		{string}
		 */
		_language : function() {

			const current = Nino.admin.workLocale.current;

			return Nino.admin.routes._locales.indexOf( current ) !== -1 ? current : Nino.admin.routes._selectedLocale;
		},

		/**
		 *	The workbench's content language moved: the names of the directory and of the list are the ones of that language, and a record that is open is drawn again
		 *	with the description of that language - the shell has asked about its unsaved input already, so what it holds now is what is saved
		 *
		 *	@return		void
		 */
		_followLocale : function() {

			const panel = Nino.admin.routes;

			if( panel._ready === false )
				return;

			panel._rowsOf();
			panel._directory.setRows( panel._rows, { keep : true } );
			panel._drawFeatures();

			[ [ panel._record, panel._ctx, 'routes' ], [ panel._featureRecord, panel._featureCtx, 'routes-feature' ] ].forEach( function( open ) {

				if( open[0].isOpen() === false )
					return;

				// An entry that stands among the rows the record steps through is drawn again from its row: its name is in its head, and its description is the one of the
				// language. The form of a new route, and an entry that a search hides, have no place to be stepped to: what they draw in the language - the badge, the
				// description - is put right where it stands, and is what is saved
				const among = open[0].creating !== undefined && open[0].creating() === true ? false : open[0].key() !== null && ( open[0] === panel._featureRecord ? true : panel._directory.rows().some( function( row ) { return row.key === open[0].key() } ) );

				if( among === true )
					return open[1].go( 0 );

				panel._localize( open[1].view );
				Nino.admin.dirty.snapshot( open[2] );
			} );
		},

		/**
		 *	The record's own part that follows the working language, put right in place: the badge that says it and the description
		 *
		 *	@param		{Object}		view					What the body of the record keeps
		 *
		 *	@return		void
		 */
		_localize : function( view ) {

			const panel = Nino.admin.routes;
			const work = panel._language();

			view.work = work;
			view.description.value = view.descriptions[work] ?? '';

			const badge = Nino.adminUi.localeBadge( work );

			view.badge.innerHTML = '';

			if( badge !== null )
				view.badge.appendChild( badge );

			view.badge.hidden = badge === null;
		},

		/**
		 *	Call a routes/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "routes/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'routes/'+ endpoint, payload, callback );
		},

		/**
		 *	Tell the shell that a page was written, after the server has said so: the pages are touched - the window beside the workbench goes to the address - and so are the
		 *	texts, since the name, title and description of a page, its address and its place among the pages are what the Text panel and its Keys tab show of it. What this
		 *	panel wrote is on its own screen: the stamp of the texts it holds moves with the shell's, where it was the shell's before
		 *
		 *	@param		{string}		[path]				The address of the page the write was about, see Nino.admin.touch()
		 *
		 *	@return		void
		 */
		_wrote : function( path ) {

			const panel = Nino.admin.routes;
			const same = Nino.admin.stamp( 'texts' ) === panel._textStamp;

			Nino.admin.touch( 'routes', path );
			Nino.admin.touch( 'texts' );

			if( same === true )
				panel._textStamp = Nino.admin.stamp( 'texts' );
		},

		/**
		 *	The row of a page: what the table shows of it, and the page itself for the record to read
		 *
		 *	@param		{Object}		entry					One entry of _pages
		 *
		 *	@return		{Object}
		 */
		_row : function( entry ) {

			const panel = Nino.admin.routes;
			const code = Number( entry.statusCode ) || 200;

			return {
				key					: entry.uri,
				uri					: entry.uri,
				entry				: entry,
				name				: panel._pageName( entry ),
				paths				: panel._paths( entry ),
				// The template, or the word for a body that picks it when the page is asked for
				templateText : typeof entry.template === 'string' && entry.template !== '' ? entry.template : word('/_admin/routes/label/runtime'),
				navText			: ( entry.navs || [] ).map( function( key ) { return panel._navNames[key] || key } ).join(', '),
				statusText	: code === 200 ? '' : String( code ),
				// The state a person would otherwise see only after opening the page: has it a description in each language
				states			: panel._locales.map( function( locale ) {
					return { locale : locale, label : panel._short( locale ), name : Nino.adminUi.localeName( locale, panel._locales ), ok : String( ( ( entry.text || {} )[locale] || {} ).description || '' ).trim() !== '' };
				} ),
			};
		},

		/**
		 *	The row of a page a feature routes: its name and its paths
		 *
		 *	@param		{Object}		entry					One entry of _runtime
		 *
		 *	@return		{Object}
		 */
		_featureRow : function( entry ) {

			const panel = Nino.admin.routes;

			return { key : entry.uri, uri : entry.uri, entry : entry, name : panel._pageName( entry ), paths : panel._paths( entry ) };
		},

		/**
		 *	The row of a page of the project that the panel holds, or null
		 *
		 *	@param		{string}		key
		 *
		 *	@return		{Object|null}
		 */
		_find : function( key ) {
			return Nino.admin.routes._rows.find( function( row ) { return row.key === key } ) ?? null;
		},

		/**
		 *	The row of a page of a feature that the panel holds, or null
		 *
		 *	@param		{string}		key
		 *
		 *	@return		{Object|null}
		 */
		_findFeature : function( key ) {
			return Nino.admin.routes._featureRows.find( function( row ) { return row.key === key } ) ?? null;
		},

		/**
		 *	The name of a language in a badge: its code's language, "DE" - and where two languages of the site share it, the whole code, "PT-BR"
		 *
		 *	@param		{string}		code
		 *
		 *	@return		{string}
		 */
		_short : function( code ) {

			const language = String( code ).split('_')[0];
			const shared = Nino.admin.routes._locales.filter( function( other ) { return String( other ).split('_')[0] === language } ).length > 1;

			return ( shared === true ? String( code ).replace( '_', '-' ) : language ).toUpperCase();
		},

		/**
		 *	The empty entry of a new route as a row: nothing but the template the project proposes
		 *
		 *	@return		{Object}
		 */
		_blank : function() {

			const panel = Nino.admin.routes;

			return panel._row( { uri : '', httpUri : '', httpUris : {}, template : panel._defaultTemplate, navs : [], statusCode : 200, text : {} } );
		},

		/**
		 *	The directory: the search over the pages and the table, with the up and down arrows of a row for the order the pages are stored in. There is no category, no
		 *	select that narrows and no box to choose rows by: nothing here is done to several pages at once. The rows are always in the order they are stored in: no column sorts
		 *
		 *	@param		{Element}		mount
		 *	@param		{Object}		[state]				What the person saw before
		 *
		 *	@return		{Object}
		 */
		_build : function( mount, state ) {

			const panel = Nino.admin.routes;
			const navs = panel._navs.length > 0;

			panel._shape = navs === true ? 'navs' : '';

			const columns = [
				{ key : 'name', label : word('/_admin/routes/label/name'), type : 'string', primary : true, sortable : false, render : panel._nameCell },
				{ key : 'paths', label : word('/_admin/routes/label/httpuri'), type : 'string', sortable : false, render : panel._pathCell },
				{ key : 'templateText', label : word('/_admin/routes/label/template'), type : 'string', sortable : false, narrow : false },
			];

			// Where the Navigation module is off no page stands in a menu: the column would say nothing
			if( navs === true )
				columns.push( { key : 'navText', label : word('/_admin/routes/col/navs'), type : 'string', sortable : false, narrow : false } );

			columns.push(
				{ key : 'statusText', label : word('/_admin/routes/col/status'), type : 'string', sortable : false, render : panel._statusCell },
				{ key : 'states', label : word('/_admin/routes/col/description'), cell : 'locales', words : { ok : word('/_admin/routes/col/description-ok'), missing : word('/_admin/routes/col/description-missing') } }
			);

			return Nino.adminUi.directory( {
				mount : mount,
				rowKey : 'key',
				state : state,
				onState : function( now ) { panel._state = now; panel._lockAll() },
				columns : columns,
				labels : { search : word('/_admin/routes/label/search'), empty : word('/_admin/routes/empty'), noMatch : word('/_admin/routes/nomatch'), loading : word('/_admin/routes/loading') },
				// What a person looks for, whatever the cells show of it: the name in every language, the Element URI, every address, the template
				searchText : function( row ) {

					const names = panel._locales.map( function( locale ) { return String( ( ( row.entry.text || {} )[locale] || {} ).name || '' ) } );

					return names.concat( [ row.uri, row.paths, row.entry.template || '' ] ).join(' ');
				},
				actions : panel._actions,
				// A row is opened by its first button as well: the row is not a stop of the Tab key where it holds buttons
				onOpen : function( row ) { panel._record.open( row.key, {} ) },
			} );
		},

		/**
		 *	The name of a page in the table: the name strong, its Element URI under it in the face of an identifier, each in one line that an ellipsis ends and whole in its tooltip
		 *
		 *	@param		{string}		value
		 *	@param		{Object}		row
		 *
		 *	@return		{Element}
		 */
		_nameCell : function( value, row ) {

			const cell = Nino.adminUi.el( 'span', 'routes-name' );
			const name = Nino.adminUi.el( 'strong', 'routes-name-label', row.name );
			const key = Nino.adminUi.el( 'span', 'routes-name-key', row.uri );

			name.title = row.name;
			key.title = row.uri;
			cell.appendChild( name );
			cell.appendChild( key );

			return cell;
		},

		/**
		 *	The addresses of a page, in one line that an ellipsis ends, and behind them the link that opens the page itself in a tab of its own - a real link with a name, so
		 *	that it is opened, copied and middle-clicked like one. Only for a path that is a path below this site: a '//host' would be read as another origin
		 *
		 *	@param		{string}		value
		 *	@param		{Object}		row
		 *
		 *	@return		{Element}
		 */
		_pathCell : function( value, row ) {

			const cell = Nino.adminUi.el( 'span', 'routes-paths' );
			const paths = Nino.adminUi.el( 'span', 'nino-admin-table-clamp', row.paths );
			const first = String( row.entry.httpUri || '' );

			paths.title = row.paths;
			// The address is the column that gives way: the name has the room it needs
			paths.style.setProperty( '--nino-admin-clamp', '12rem' );
			cell.appendChild( paths );

			if( first.charAt(0) === '/' && first.charAt(1) !== '/' ) {

				const name = Nino.adminUi.format( word('/_admin/routes/label/open'), row.name );
				const link = Nino.adminUi.el( 'a', 'nino-admin-btn-icon nino-admin-btn-icon--small' );

				link.href = Nino.admin.assetUrl( first );
				link.target = '_blank';
				link.rel = 'noopener';
				link.title = name;
				link.setAttribute( 'aria-label', name );
				link.appendChild( Nino.adminUi.icon( 'external-link' ) );
				cell.appendChild( link );
			}

			return cell;
		},

		/**
		 *	The status of a page: a badge for a code that is not the 200 of a page, and nothing for one that is
		 *
		 *	@param		{string}		value
		 *	@param		{Object}		row
		 *
		 *	@return		{Element|string}
		 */
		_statusCell : function( value, row ) {

			if( row.statusText === '' )
				return '';

			const badge = Nino.adminUi.el( 'span', 'nino-admin-badge', row.statusText );

			badge.dataset.state = 'warn';

			return badge;
		},

		/**
		 *	The buttons at the end of a row, in the order the keyboard and the eye take them: edit - the one that opens the row -, up and down. "Open the page" is a link
		 *	in the address, since a row has room for three buttons and the menu behind "More actions" begins with the third (see table(), actionsInline)
		 *
		 *	@param		{Object}		row
		 *
		 *	@return		{Array}
		 */
		_actions : function( row ) {

			const panel = Nino.admin.routes;

			const edit = Nino.adminUi.iconButton( 'pencil', Nino.adminUi.format( word('/_admin/routes/row/open'), row.name ), function( event ) {
				panel._record.open( row.key, { opener : event.currentTarget } );
			}, { small : true } );
			const up = Nino.adminUi.iconButton( 'arrow-up', word('/_admin/routes/label/moveup'), function() { panel._move( row.uri, 'up' ) }, { small : true } );
			const down = Nino.adminUi.iconButton( 'arrow-down', word('/_admin/routes/label/movedown'), function() { panel._move( row.uri, 'down' ) }, { small : true } );

			panel._buttons[row.uri] = { up : up, down : down };
			panel._lock( row.uri );

			return [ edit, up, down ];
		},

		/**
		 *	Why a page cannot be moved now: the arrows move a page among its neighbours in the order that is stored, and a search shows other rows. No column of the table
		 *	sorts - the stored order is the one order of this list - so a search is the one thing that can hide a neighbour
		 *
		 *	@return		{string}									The reason, '' where every page is shown
		 */
		_locked : function() {

			if( String( Nino.admin.routes._directory.state().query ?? '' ).trim() !== '' )
				return word('/_admin/routes/lock/search');

			return '';
		},

		/**
		 *	Lock or unlock the arrows of a row: locked while a search shows not every page, saying why, and at the edges of the order
		 *
		 *	@param		{string}		uri
		 *
		 *	@return		void
		 */
		_lock : function( uri ) {

			const panel = Nino.admin.routes;
			const pair = panel._buttons[uri];
			const at = panel._rows.findIndex( function( row ) { return row.uri === uri } );
			const reason = panel._locked();

			if( pair === undefined )
				return;

			pair.up.disabled = reason !== '' || at <= 0;
			pair.down.disabled = reason !== '' || at === panel._rows.length - 1;
			pair.up.title = reason === '' ? word('/_admin/routes/label/moveup') : reason;
			pair.down.title = reason === '' ? word('/_admin/routes/label/movedown') : reason;
		},

		/**
		 *	The arrows of every row that is drawn, after the person changed the search
		 *
		 *	@return		void
		 */
		_lockAll : function() {

			const panel = Nino.admin.routes;

			Object.keys( panel._buttons ).forEach( panel._lock );
		},

		/**
		 *	Swap one page with its neighbor - see Admin.php's apiMove(). The order is saved at once, which a toast says; the rows are the ones the server answers with, the
		 *	row that moved is the one drawn anew, and the focus stays on the button that was pressed - on the other one where that is locked now, at the edge of the order.
		 *	A move the server refuses leaves the order on screen a guess - a route somebody else deleted since, a config.php that could not be written -, so the list is read
		 *	again and the reason is said over the table
		 *
		 *	@param		{string}	uri						The Element-URI of the page
		 *	@param		{string}	direction			'up' | 'down'
		 *
		 *	@return		void
		 */
		_move : function( uri, direction ) {

			const panel = Nino.admin.routes;

			panel._apiCall( 'move', { uri : uri, direction : direction }, function( status, response ) {

				if( status !== 200 || response === null ) {

					panel._apiCall( 'list', {}, function( listStatus, listResponse ) {

						if( listStatus === 200 && listResponse !== null ) {
							panel._pages = listResponse.pages;
							panel._rowsOf();
							panel._directory.setRows( panel._rows, { keep : true } );
						}

						// After the rows, which draw the table anew
						panel._directory.notice( Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
					} );

					return;
				}

				panel._wrote();

				panel._pages = response.pages;
				panel._rowsOf();
				panel._directory.notice('');
				panel._directory.update( panel._rows, uri );
				panel._directory.reveal( uri );

				const pair = panel._buttons[uri];

				if( pair !== undefined ) {
					const own = pair[direction];

					( own.disabled === true ? pair[direction === 'up' ? 'down' : 'up'] : own ).focus();
				}

				Nino.adminUi.say( word('/_admin/routes/msg/moved') );
			} );
		},

		/**
		 *	The list of the pages a feature or a module routes at runtime, under the directory: a row is a name over its paths and opens the record of its texts, nothing
		 *	more - no menu order, no delete, because there is no route of the project's to move or remove. What these pages are is behind the (?) at the title of the group.
		 *	Nothing is drawn when no feature routes a page
		 *
		 *	@return		void
		 */
		_drawFeatures : function() {

			const panel = Nino.admin.routes;
			const holder = panel._features;

			holder.innerHTML = '';

			if( panel._featureRows.length === 0 )
				return;

			const group = Nino.adminUi.list( panel._featureRows.map( function( row ) {
				return {
					key			: row.key,
					title		: row.name,
					detail	: row.paths,
					href		: Nino.admin.router.href( 'routes', [ Nino.admin.router.word('feature'), row.uri ] ),
					onOpen	: function( one, event ) { panel._featureRecord.open( row.key, { opener : event !== undefined && event !== null ? event.currentTarget : undefined } ) },
				};
			} ), { title : word('/_admin/routes/runtime/title') } );

			Nino.adminUi.sectionHelp( group.querySelector('h2'), group, word('/_admin/routes/runtime/help') );
			holder.appendChild( group );
		},

		/**
		 *	The directory as the records see it: what is on screen now, whatever drew it last
		 *
		 *	@return		{Object}
		 */
		_pageDirectory : function() {

			const panel = Nino.admin.routes;

			return {
				rows : function() { return panel._directory.rows() },
				reveal : function( key ) { return panel._directory.reveal( key ) },
				focusRow : function( key ) { return panel._directory.focusRow( key ) },
				focusSearch : function() { return panel._directory.focusSearch() },
			};
		},

		/**
		 *	The list of the pages of features as the record of one sees it: the rows in the order they are shown, which are all of them - there is no page, no search
		 *
		 *	@return		{Object}
		 */
		_featureDirectory : function() {

			const panel = Nino.admin.routes;

			const link = function( key ) {

				// The record of a page of a feature has written the address of its directory - the word alone - where it closed, which is the directory of the panel: the
				// focus is given back right after that write
				panel._plain();

				const found = Array.from( panel._features.querySelectorAll('li') ).find( function( li ) { return li.dataset.row === key } );

				return found === undefined ? null : found.querySelector('a');
			};

			return {
				rows : function() { return panel._featureRows },
				reveal : function() { return true },
				focusRow : function( key ) {

					const target = link( key );

					if( target === null )
						return false;

					target.focus();

					return true;
				},
				// The heading of the list, else its first row: where the focus goes when the page is gone
				focusSearch : function() {

					panel._plain();

					const target = panel._features.querySelector('h2') ?? panel._features.querySelector('a');

					if( target === null )
						return false;

					target.setAttribute( 'tabindex', '-1' );
					target.focus();

					return true;
				},
			};
		},

		/**
		 *	The record of a page of the project: a dialog over the directory with the name of the page in its head and its Element URI under it, the fields of the page,
		 *	"Delete route" at the left of the bar and "Save", "Save and continue" at the right. The form of a new route is the same dialog (`create`), and a save that
		 *	changes the Element URI gives the record the new one (`rekey`)
		 *
		 *	@return		{Object}
		 */
		_makeRecord : function() {

			const panel = Nino.admin.routes;

			return Nino.adminUi.record( {
				screen : 'routes',
				prefix : [],
				directory : panel._pageDirectory(),
				keyOf : function( row ) { return row.key },
				find : panel._find,
				title : function( row ) { return row.name },
				subtitle : function( row ) { return row.uri },
				saveNext : true,
				rekey : true,
				create : { blank : panel._blank },
				labels : { prev : word('/_admin/routes/record/prev'), next : word('/_admin/routes/record/next'), createTitle : word('/_admin/routes/label/new'), createSave : word('/_admin/routes/label/create'), created : word('/_admin/routes/msg/created') },
				body : panel._body,
				save : panel._save,
				reload : panel._reload,
				// The page of the address '/' and the page of the status 404 are answered as not deletable: the button is not drawn for them
				danger : { label : word('/_admin/routes/label/delete'), run : panel._remove, hidden : function( row ) { return ( row.entry || {} ).deletable === false } },
				missing : function() { panel._directory.notice( word('/_admin/routes/record/missing') ) },
			} );
		},

		/**
		 *	The record of a page a feature routes: a second record of the same screen, at #routes/routes/@feature/<Element URI>, over the list of those pages. It has
		 *	a name of its own for unsaved input, which the entry of the panel answers for (see _share()), no way to delete and no form of a new page
		 *
		 *	@return		{Object}
		 */
		_makeFeatureRecord : function() {

			const panel = Nino.admin.routes;

			return Nino.adminUi.record( {
				screen : 'routes',
				prefix : [ Nino.admin.router.word('feature') ],
				dirty : 'routes-feature',
				directory : panel._featureDirectory(),
				keyOf : function( row ) { return row.key },
				find : panel._findFeature,
				title : function( row ) { return row.name },
				subtitle : function( row ) { return row.paths },
				saveNext : true,
				labels : { prev : word('/_admin/routes/record/prev'), next : word('/_admin/routes/record/next') },
				body : panel._featureBody,
				save : panel._saveFeature,
				missing : function() { panel._directory.notice( word('/_admin/routes/record/missing') ) },
			} );
		},

		/**
		 *	The two records are two views of one panel, and the shell knows a panel by one name of unsaved input: `routes`, the entry of the record of a page. The record of a
		 *	page of a feature is a watched form of its own - `routes-feature` - and the entry of the panel answers for whichever of the two is open, so that the question
		 *	before a way out of the panel and the save the shell runs reach the one that holds the input
		 *
		 *	@return		void
		 */
		_share : function() {

			const dirty = Nino.admin.dirty;

			if( typeof dirty !== 'object' || typeof dirty.entry !== 'function' )
				return;

			const own = dirty.entry('routes');
			const feature = dirty.entry('routes-feature');

			if( own === null || feature === null )
				return;

			const on = function() { return Nino.admin.routes._featureRecord.isOpen() === true };

			dirty.register( 'routes', {
				isDirty : function() { return on() === true ? feature.isDirty() : own.isDirty() },
				save : function( done ) { return on() === true ? feature.save( done ) : own.save( done ) },
				discard : function() { return on() === true ? feature.discard() : own.discard() },
				bar : function() { return on() === true ? feature.bar() : own.bar() },
			} );
		},

		/**
		 *	What the address names, and which of the two records answers it. A page of the project - one part, the word @new for the form of a new one - is the record of
		 *	a page, a page of a feature - the word @feature and one part - the record of those. The record of a page reads two parts as one too many and writes the
		 *	address of the directory over it, so it never sees an address of the other record; and the one that is open and is not the address's own is closed first,
		 *	asking about its unsaved input: what is on screen is the screen, and where it cannot be taken away the address is written to what the screen shows
		 *
		 *	@return		{boolean}									Whether the address was answered
		 */
		_follow : function() {

			const panel = Nino.admin.routes;
			const router = Nino.admin.router;
			const at = router.current();

			if( at.panel !== 'routes' )
				return false;

			const feature = at.parts.length > 0 && router.isWord( at.parts, 0, 'feature' ) === true;
			const here = feature === true ? panel._featureRecord : panel._record;
			const other = feature === true ? panel._record : panel._featureRecord;

			// The address says what the screen has to be - written again where closing the other one wrote over it
			const parts = at.parts.map( function( part, index ) { return at.parts.words[index] === true ? router.word( part.slice( 1 ) ) : part } );

			const answer = function() {

				const answered = here.follow();

				panel._plain();

				return answered;
			};

			if( other.isOpen() === false )
				return answer();

			router.leave( [ 'routes' ], true, function() {

				if( other.isOpen() === true ) {

					// The entry of the history the browser is on may be the other record's own, which its closing would take off the history by stepping back: it is closed as
					// what it is not, and the address is the one that was asked for
					const mark = router.marked();

					if( mark !== '' )
						router.mark('');

					other.close();

					router.set( 'routes', parts );

					if( mark !== '' )
						router.mark( mark );
				}

				answer();
			}, panel._address );

			return true;
		},

		/**
		 *	The record of a page of a feature writes the address of its own directory, the word @feature alone, where it closes or where the page is not there. The
		 *	directory of the panel is the list, and that is the address
		 *
		 *	@return		void
		 */
		_plain : function() {

			const router = Nino.admin.router;
			const at = router.current();

			if( at.panel === 'routes' && at.parts.length === 1 && router.isWord( at.parts, 0, 'feature' ) === true )
				router.set( 'routes', [] );
		},

		/**
		 *	Write the address of what is on screen: the form of a new route, a page, a page of a feature or the directory
		 *
		 *	@return		void
		 */
		_address : function() {

			const panel = Nino.admin.routes;
			const router = Nino.admin.router;
			let parts = [];

			if( panel._record.isOpen() === true )
				parts = panel._record.creating() === true ? [ router.word('new') ] : [ panel._record.key() ];
			else if( panel._featureRecord.isOpen() === true )
				parts = [ router.word('feature'), panel._featureRecord.key() ];

			router.set( 'routes', parts );
		},

		/**
		 *	The mark after the name of a field that cannot be left empty is the block's star; what a field says is
		 *	its label. The control a block drew inside the label it returned
		 *
		 *	@param		{Element}		label					What textField(), selectField() and the like returned
		 *	@param		{string}		field					The name the server marks the control by where it refuses it
		 *
		 *	@return		{Element}									The control
		 */
		_control : function( label, field ) {

			const control = label.querySelector('input, select, textarea');
			control.dataset.field = field;

			return control;
		},

		/**
		 *	A group of fields of a record: a fieldset with its legend
		 *
		 *	@param		{string}		title
		 *
		 *	@return		{Object}									{ set, legend }
		 */
		_section : function( title ) {

			const set = dc.createElement('fieldset');
			const legend = set.appendChild( Nino.adminUi.el( 'legend', '', title ) );

			return { set : set, legend : legend };
		},

		/**
		 *	What the body of a record keeps of its own, from the entry it draws: the language the description is of, and the description of each language - the one of the
		 *	working language is what its field holds, the others stay as they were
		 *
		 *	@param		{Object}		entry
		 *
		 *	@return		{Object}
		 */
		_view : function( entry ) {

			const panel = Nino.admin.routes;
			// The stamp is the state of the page the form was drawn from, which a save sends back: a page that is another since is a conflict. A save that went
			// through gives the form the stamp of what it wrote
			const view = { entry : entry, work : panel._language(), descriptions : {}, stamp : typeof entry.stamp === 'string' ? entry.stamp : null };

			panel._locales.forEach( function( locale ) {
				view.descriptions[locale] = String( ( ( entry.text || {} )[locale] || {} ).description || '' );
			} );

			// Where the language is not the native one the record says in which it writes: at the head of the body
			view.badge = Nino.adminUi.el( 'p', 'routes-worklang' );

			const badge = Nino.adminUi.localeBadge( view.work );

			if( badge !== null )
				view.badge.appendChild( badge );

			view.badge.hidden = badge === null;

			return view;
		},

		/**
		 *	The section of the content of a page: the name and the title of every language, and the description of the language the workbench edits content in
		 *
		 *	@param		{Object}		view
		 *	@param		{Object}		entry
		 *
		 *	@return		{Element}
		 */
		_content : function( view, entry ) {

			const panel = Nino.admin.routes;
			const section = panel._section( word('/_admin/routes/label/content') );

			Nino.adminUi.sectionHelp( section.legend, section.set, word('/_admin/routes/help/content') );

			view.name = Nino.adminUi.localeRows( { key : 'name', label : word('/_admin/routes/label/name'), required : true, locales : panel._locales,
				values : panel._valuesOf( entry, 'name' ), placeholder : word('/_admin/routes/placeholder/name'), help : word('/_admin/routes/help/name') } );
			view.title = Nino.adminUi.localeRows( { key : 'title', label : word('/_admin/routes/label/title'), required : true, locales : panel._locales,
				values : panel._valuesOf( entry, 'title' ), placeholder : word('/_admin/routes/placeholder/title'), help : word('/_admin/routes/help/title') } );

			[ view.name, view.title ].forEach( function( rows, at ) {
				Object.keys( rows.controls ).forEach( function( locale ) { rows.controls[locale].dataset.field = at === 0 ? 'name' : 'title' } );
				section.set.appendChild( rows.el );
			} );

			const description = Nino.adminUi.textareaField( { key : 'description', label : word('/_admin/routes/label/description'), rows : 3,
				value : view.descriptions[view.work] ?? '', help : word('/_admin/routes/help/description') } );

			view.description = panel._control( description, 'description' );
			section.set.appendChild( description );

			return section.set;
		},

		/**
		 *	The content of the record of a page of the project: the section of the route - the Element URI, the switch for one Http URI for every language and the
		 *	address, or the rows of the languages, the template, the status code and, while the Navigation module is active, the navigations - and the section of the
		 *	content. The record checks its required fields itself (see _save()), so the browser's own bubble - in the browser's language, not the workbench's - never
		 *	gets to speak. What the fields are is kept in ctx.view, which the save finds there
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *
		 *	@return		{Element}
		 */
		_body : function( row, ctx ) {

			const panel = Nino.admin.routes;
			const view = panel._view( row.entry );
			const body = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );

			view.row = row;
			view.creating = ctx.creating === true;
			ctx.view = view;
			panel._ctx = ctx;

			body.appendChild( view.badge );

			const section = panel._section( word('/_admin/routes/label/route') );

			panel._drawRoute( section.set, view, row.entry );
			body.appendChild( section.set );
			body.appendChild( panel._content( view, row.entry ) );

			return body;
		},

		/**
		 *	The content of the record of a page a feature routes: its paths, to read, and the section of the content
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *
		 *	@return		{Element}
		 */
		_featureBody : function( row, ctx ) {

			const panel = Nino.admin.routes;
			const view = panel._view( row.entry );
			const body = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );

			view.row = row;
			ctx.view = view;
			panel._featureCtx = ctx;

			body.appendChild( view.badge );
			body.appendChild( Nino.adminUi.readLine( { label : word('/_admin/routes/label/httpuri'), value : row.paths, mono : true } ) );
			body.appendChild( panel._content( view, row.entry ) );

			return body;
		},

		/**
		 *	One value of every language, for localeRows()
		 *
		 *	@param		{Object}		entry
		 *	@param		{string}		field					'name' or 'title'
		 *
		 *	@return		{Object}									Language => value
		 */
		_valuesOf : function( entry, field ) {

			const values = {};

			Nino.admin.routes._locales.forEach( function( locale ) {
				values[locale] = String( ( ( entry.text || {} )[locale] || {} )[field] || '' );
			} );

			return values;
		},

		/**
		 *	The fields of the section of the route of a page of the project: Element URI, the switch for one Http
		 *	URI for every language and the address, template, status code and the navigations
		 *
		 *	@param		{Element}		fieldset
		 *	@param		{Object}		view
		 *	@param		{Object}		entry
		 *
		 *	@return		void
		 */
		_drawRoute : function( fieldset, view, entry ) {

			const routes = Nino.admin.routes;

			const uri = Nino.adminUi.textField( { label : word('/_admin/routes/label/uri'), id : 'routes-form-uri', value : entry.uri || '', required : true,
				placeholder : word('/_admin/routes/placeholder/uri'), help : word('/_admin/routes/help/uri') } );
			view.uriInput = routes._control( uri, 'uri' );
			fieldset.appendChild( uri );

			view.addresses = routes._addressesOf( entry );

			const oneSwitch = Nino.adminUi.switchField( {
				key 		: 'one-address',
				checked : view.addresses.one,
				label 	: word('/_admin/routes/label/one-address'),
				help 		: word('/_admin/routes/hint/one-address'),
			} );
			oneSwitch.querySelector('input').addEventListener( 'change', function( ev ) { routes._switchAddresses( view, ev.target.checked ) } );
			fieldset.appendChild( oneSwitch );

			const addressWrap = dc.createElement('div');
			addressWrap.id = 'routes-form-addresses';
			addressWrap.className = 'nino-admin-fieldgrid';
			view.addressWrap = addressWrap;
			routes._renderAddresses( view );
			fieldset.appendChild( addressWrap );

			// A route with no template of its own to start on - a new one in a
			// project without a blank page, or one whose file is gone - makes
			// the person choose: the first file in the list is not a proposal,
			// it is the one that happens to sort first. A body that names its
			// template at runtime has its own option, and the select is locked
			const fixedBody = entry.body !== undefined && entry.body !== '' && routes._templateFromBody( entry.body ) === null;
			const options = routes._templates.map( function( name ) { return { value : name, label : name } } );
			const asking = fixedBody === false && routes._templates.indexOf( entry.template ) === -1;

			if( fixedBody === true )
				options.unshift( { value : '', label : word('/_admin/routes/label/runtime') } );
			else if( asking === true )
				options.unshift( { value : '', label : word('/_admin/routes/label/choose') } );

			const template = Nino.adminUi.selectField( { key : 'template', label : word('/_admin/routes/label/template'), id : 'routes-form-template', options : options,
				value : fixedBody === true || asking === true ? '' : entry.template,
				help : fixedBody === true ? Nino.adminUi.format( word('/_admin/routes/hint/runtime'), entry.body ) : word('/_admin/routes/help/template') } );

			// The star is the field's, a placeholder is a choice to make and no choice, and the select that the route's body decides is locked
			view.template = routes._control( template, 'template' );
			view.template.setAttribute( 'aria-required', 'true' );
			// The star stands after the words of the name, where the block puts it, and before the (?)
			const templateName = template.querySelector('span');
			( templateName.querySelector('.nino-admin-field-text') ?? templateName ).appendChild( routes._star() );

			if( asking === true )
				view.template.querySelector('option').disabled = true;

			view.template.disabled = fixedBody === true;
			fieldset.appendChild( template );

			const status = Nino.adminUi.textField( { key : 'status', type : 'number', label : word('/_admin/routes/label/status'), id : 'routes-form-status',
				value : String( entry.statusCode || 200 ), help : word('/_admin/routes/help/status') } );
			view.status = routes._control( status, 'statusCode' );
			view.status.min = '100';
			view.status.max = '599';
			fieldset.appendChild( status );

			// One tick per navigation the project registered (/nino/html/navs), named by its name where it has one and
			// by its key behind it, quietly - an empty list means the Navigation module is inactive and no menu field is
			// offered at all. What a page is a member of lives on its route, which is what actually renders it (see
			// \Nino\Modules\Navigation::routeLines())
			view.navs = null;

			if( routes._navs.length > 0 ) {

				view.navs = Nino.adminUi.checklist( { key : 'navs', label : word('/_admin/routes/label/navs'), help : word('/_admin/routes/help/navs'),
					value : entry.navs || [], options : routes._navs.map( function( key ) {
						const name = routes._navNames[key] ?? '';
						return name === '' ? { value : key, label : key } : { value : key, label : name, state : key };
					} ) } );
				fieldset.appendChild( view.navs.el );
			}
		},

		/**
		 *	The mark after the name of a field that cannot be left empty - drawn
		 *	aria-hidden, the control says it with aria-required. selectField() has none of its own
		 *
		 *	@return		{Element}
		 */
		_star : function() {
			const star = dc.createElement('span');
			star.className = 'nino-admin-required';
			star.setAttribute( 'aria-hidden', 'true' );
			star.textContent = '*';
			return star;
		},

		/**
		 *	The on-disk template a route body names, when it names exactly
		 *	one - mirrors \Nino\Pages::templateOf(), which is what actually
		 *	decides whether a save may rewrite the body
		 *
		 *	@param		{string}	body
		 *
		 *	@return		{string|null}		Null if body isn't a plain template reference
		 */
		_templateFromBody : function( body ) {
			const match = /^\[template \/templates\/([A-Za-z0-9._-]+)\]$/.exec( String( body ).trim() );
			return match !== null ? match[1] : null;
		},

		/**
		 *	What a page is called in the table: its name in the language the
		 *	workbench is on, else in the first language that has one, else its
		 *	path - a page with no name at all is still a row somebody can open
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{string}
		 */
		_pageName : function( entry ) {

			const nameIn = function( locale ) {
				return String( ( ( entry.text || {} )[locale] || {} ).name || '' ).trim();
			};

			const locales = [ Nino.admin.routes._language() ].concat( Nino.admin.routes._locales );

			for( let i = 0; i < locales.length; i++ )
				if( locales[i] !== '' && nameIn( locales[i] ) !== '' )
					return nameIn( locales[i] );

			return entry.httpUri;
		},

		/**
		 *	The paths a page is reached at: every one of a page that has an
		 *	address per language, the one of any other. A page of the project
		 *	says them by language - its 'httpUris' is a map -, a page a feature
		 *	routes has a list of them
		 *
		 *	@param		{Object}	entry					One entry from _pages or _runtime
		 *
		 *	@return		{string}
		 */
		_paths : function( entry ) {

			const uris = entry.httpUris || {};
			const list = Object.keys( uris ).map( function( key ) { return uris[key] } );

			return list.length > 0 ? list.join(', ') : entry.httpUri;
		},

		/**
		 *	The address a page is reached at on the website, for a window that
		 *	follows what is saved (see Nino.admin.touch()): the one of the
		 *	language the panel is editing, else the one of every language, else
		 *	the first there is. A path with its leading slash, or '' where the
		 *	page has none or is routed by a pattern, which no window can go to
		 *
		 *	@param		{Object}	httpUris			Language => address, '' for the address of every language
		 *
		 *	@return		{string}
		 */
		_publicPath : function( httpUris ) {

			const wanted = [ Nino.admin.routes._language(), '' ].concat( Object.keys( httpUris ) );
			const found = wanted.find( function( key ) { return typeof httpUris[key] === 'string' && httpUris[key].trim() !== '' } );

			if( found === undefined )
				return '';

			const address = httpUris[found].trim();

			// A page a feature routes by a pattern - /blog/* - is no address
			if( address.indexOf('*') !== -1 )
				return '';

			return address.charAt(0) === '/' ? address : '/'+ address;
		},

		/**
		 *	What the address fields of a page start from: the one field for
		 *	every language where the page has one address for them all - a new
		 *	page has none yet - and a row per language where it has one for
		 *	each. 'shared' is what the one field starts from, 'bound' the
		 *	address each language has of its own, empty for one that has none
		 *
		 *	@param		{Object}		entry					One entry from _pages, or the empty one of a new page
		 *
		 *	@return		{Object}									{ one, shared, bound } - bound is locale => address
		 */
		_addressesOf : function( entry ) {

			const map = entry.httpUris || {};
			const keys = Object.keys( map );
			const bound = {};

			Nino.admin.routes._locales.forEach( function( locale ) {
				bound[locale] = map[locale] ?? '';
			} );

			return { one : keys.length === 0 || ( keys.length === 1 && keys[0] === '' ), shared : map[''] ?? '', bound : bound };
		},

		/**
		 *	Draw the address fields into their container: the one field, or a
		 *	row per language - one that has no address of its own yet starts
		 *	from the address of every language, which is what it is reached
		 *	at until it is given one
		 *
		 *	@param		{Object}		view					What the body of the record keeps
		 *
		 *	@return		void
		 */
		_renderAddresses : function( view ) {

			const routes = Nino.admin.routes;
			const addresses = view.addresses;
			const wrap = view.addressWrap;
			const label = word('/_admin/routes/label/httpuri');
			const help = word('/_admin/routes/help/httpuri');
			const placeholder = word('/_admin/routes/placeholder/httpuri');

			wrap.innerHTML = '';
			view.address = null;
			view.addressRows = null;

			if( addresses.one === true ) {
				const field = Nino.adminUi.textField( { label : label, id : 'routes-form-http-uri', value : addresses.shared, required : true, placeholder : placeholder, help : help } );
				const input = routes._control( field, 'httpUri' );
				input.dataset.address = '';
				view.address = input;
				wrap.appendChild( field );
				return;
			}

			const values = {};
			routes._locales.forEach( function( locale ) { values[locale] = addresses.bound[locale] || addresses.shared } );

			const rows = Nino.adminUi.localeRows( { key : 'address', label : label, required : true, locales : routes._locales, values : values, placeholder : placeholder, help : help } );

			Object.keys( rows.controls ).forEach( function( locale ) {
				rows.controls[locale].dataset.address = locale;
				rows.controls[locale].dataset.field = 'httpUri-'+ locale;
			} );

			view.addressRows = rows;
			wrap.appendChild( rows.el );
		},

		/**
		 *	The address controls on screen, in the order they stand in: [ { locale, value } ], locale '' for the one field
		 *
		 *	@param		{Object}		view
		 *
		 *	@return		{Array}
		 */
		_addressFields : function( view ) {

			if( view.address !== null )
				return [ { locale : '', value : view.address.value, control : view.address } ];

			return view.addressRows.locales.map( function( locale ) {
				return { locale : locale, value : view.addressRows.value( locale ), control : view.addressRows.controls[locale] };
			} );
		},

		/**
		 *	Turn the switch: what the fields on screen hold is kept, and the
		 *	other kind of field is drawn from it. Turned to one address for
		 *	every language, the field starts from the first address a language
		 *	has where none was given for all of them
		 *
		 *	@param		{Object}		view
		 *	@param		{boolean}		one						Whether the page has one address for every language now
		 *
		 *	@return		void
		 */
		_switchAddresses : function( view, one ) {

			const routes = Nino.admin.routes;
			const addresses = view.addresses;

			routes._addressFields( view ).forEach( function( field ) {
				if( field.locale === '' )
					addresses.shared = field.value;
				else
					addresses.bound[field.locale] = field.value;
			} );

			if( one === true && addresses.shared === '' )
				addresses.shared = routes._locales.map( function( locale ) { return addresses.bound[locale] } ).find( function( value ) { return value !== '' } ) ?? '';

			addresses.one = one;
			routes._renderAddresses( view );
		},

		/**
		 *	The map of language to address that a save sends for the address fields
		 *	on screen. Fields that hold one address are the address of those
		 *	languages together, and a request gives an address once: they are sent
		 *	as the empty key, which is the address of every language that has none
		 *	of its own. A page has one such address - a second pair of fields that
		 *	hold one is sent as it stands, and the server refuses it. Two fields
		 *	hold one address when it reads as one the way the server reads it: a
		 *	slash in front, none behind, no blank around (see \Nino\Pages::normalizeUri())
		 *
		 *	@param		{Array}			fields				[ { locale, value } ] in the order the fields stand in; locale is '' for the field of every language
		 *
		 *	@return		{Object}									{ httpUris, folded } - folded is the languages whose fields were sent as the empty key
		 */
		_addressMap : function( fields ) {

			const read = function( value ) {
				let address = String( value ).trim();
				if( address !== '' && address[0] !== '/' )
					address = '/'+ address;
				return address === '/' ? address : address.replace( /\/+$/, '' );
			};
			const held = fields.filter( function( field ) { return field.locale !== '' } ).map( function( field ) { return read( field.value ) } );
			const twice = held.find( function( value, index ) { return value !== '' && held.indexOf( value ) === index && held.lastIndexOf( value ) !== index } );
			const httpUris = {};
			const folded = [];

			if( twice !== undefined )
				httpUris[''] = twice;

			fields.forEach( function( field ) {
				if( twice !== undefined && field.locale !== '' && read( field.value ) === twice )
					folded.push( field.locale );
				else
					httpUris[field.locale] = field.value;
			} );

			return { httpUris : httpUris, folded : folded };
		},

		/**
		 *	A refusal of a save, with the field it names made one the form draws.
		 *	The empty key is the address of the fields that were sent as one (see
		 *	_addressMap()), and no field of the form is called so while the
		 *	languages have one each: the refusal is for the first of those fields
		 *
		 *	@param		{*}					response			The answer of the server
		 *	@param		{Array}			folded				The languages whose fields were sent as the empty key
		 *
		 *	@return		{*}												The answer, naming the field of the first of them where it named the empty key's
		 */
		_refusedField : function( response, folded ) {

			if( folded.length === 0 || response === null || typeof response !== 'object' || response.field !== 'httpUri' )
				return response;

			return Object.assign( {}, response, { field : 'httpUri-'+ folded[0] } );
		},

		/**
		 *	Say a refusal of the server where it belongs: under the field it names - a language's row for the name, the title and the addresses -, with the focus there.
		 *	A sentence that names a language by its code names it as the workbench does
		 *
		 *	@param		{Object}		ctx
		 *	@param		{number}		status
		 *	@param		{*}					response			The answer of the server
		 *	@param		{Array}			folded				The languages whose address fields were sent as one (see _addressMap())
		 *
		 *	@return		{string}									The sentence, which the line of the bar says as well
		 */
		_refused : function( ctx, status, response, folded ) {

			const routes = Nino.admin.routes;
			const view = ctx.view;
			const named = routes._refusedField( response, folded );

			Nino.adminUi.clearFieldErrors( ctx.form );

			if( named === null || typeof named !== 'object' )
				return Nino.adminUi.api.errorText( status, named, '/_admin/common/error/save' );

			const worded = routes._worded( named );
			const sentence = Nino.adminUi.api.errorText( status, worded, '/_admin/common/error/save' );

			if( typeof named.field !== 'string' )
				return sentence;

			const field = named.field;
			const locale = Array.isArray( named.params ) === true && typeof named.params[0] === 'string' ? named.params[0] : '';
			const own = { uri : view.uriInput, template : view.template, statusCode : view.status, httpUri : view.address };
			let control = null;

			if( field === 'name' || field === 'title' ) {

				const rows = field === 'name' ? view.name : view.title;

				if( Object.hasOwn( rows.controls, locale ) === true ) {
					rows.error( locale, sentence );
					rows.focus( locale );
				}
				return sentence;
			}

			if( field === 'navs' && view.navs !== null && view.navs !== undefined ) {
				view.navs.error( sentence );
				view.navs.focus();
				return sentence;
			}

			if( field.indexOf('httpUri-') === 0 && view.addressRows !== null && view.addressRows !== undefined ) {

				const code = field.slice( 'httpUri-'.length );

				if( Object.hasOwn( view.addressRows.controls, code ) === true ) {
					view.addressRows.error( code, sentence );
					view.addressRows.focus( code );
				}
				return sentence;
			}

			control = Object.hasOwn( own, field ) === true ? own[field] : null;

			if( control === undefined || control === null )
				return sentence;

			Nino.adminUi.fieldError( control.closest('label'), control, 'routes-error-'+ ( control.id || field ), sentence );
			control.focus();

			return sentence;
		},

		/**
		 *	The answer of the server with the language it names written the way the workbench writes a language: a
		 *	refusal that says "de_DE" says "Deutsch"
		 *
		 *	@param		{Object}		response
		 *
		 *	@return		{Object}
		 */
		_worded : function( response ) {

			if( Array.isArray( response.params ) === false || typeof response.params[0] !== 'string' || Nino.admin.routes._locales.indexOf( response.params[0] ) === -1 )
				return response;

			return Object.assign( {}, response, { params : [ Nino.adminUi.localeName( response.params[0], Nino.admin.routes._locales ) ].concat( response.params.slice( 1 ) ) } );
		},

		/**
		 *	The texts of every language as a save sends them: name, title and description - the description of the
		 *	language the workbench edits is what the field holds, the others are what they were
		 *
		 *	@param		{Object}		view
		 *
		 *	@return		{Object}									Language => { name, title, description }
		 */
		_texts : function( view ) {

			const text = {};

			view.descriptions[view.work] = view.description.value;

			Nino.admin.routes._locales.forEach( function( locale ) {
				text[locale] = { name : view.name.value( locale ), title : view.title.value( locale ), description : view.descriptions[locale] ?? '' };
			} );

			return text;
		},

		/**
		 *	Say at a field that it is empty and must not be: the sentence under it, the
		 *	focus on the first one. A language's row says it at the row
		 *
		 *	@param		{Array}			missing			[ { say(), focus } ] in the order the fields stand in
		 *
		 *	@return		void
		 */
		_missing : function( missing ) {

			missing.forEach( function( entry ) { entry.say() } );
			missing[0].focus();
		},

		/**
		 *	The required fields of the form, checked here before a request: the form is not validated by the browser (the record is novalidate), and a name or a title
		 *	nobody wrote would render as the raw [[/_nino/webpage.../name]] on the page and in the menu. Every attempt starts without the marks of the one before; a field that
		 *	fails says why where it stands, the first one takes the focus
		 *
		 *	@param		{Object}		ctx
		 *	@param		{boolean}		page					The record of a page of the project: the Element URI, the addresses and the template are required as well
		 *
		 *	@return		{boolean}									Whether the fields are all there
		 */
		_complete : function( ctx, page ) {

			const routes = Nino.admin.routes;
			const view = ctx.view;
			const said = function( key ) { return Nino.adminUi.format( word('/_admin/common/error/missing'), key ) };

			Nino.adminUi.clearFieldErrors( ctx.form );

			const missing = [];
			const need = function( control, say ) {
				if( String( control.value ).trim() === '' )
					missing.push( { focus : function() { control.focus() }, say : say } );
			};
			const needRow = function( rows, locale, name ) {
				need( rows.controls[locale], function() { rows.error( locale, said( name ) ) } );
			};
			const needField = function( field, name ) {
				need( field, function() { Nino.adminUi.fieldError( field.closest('label'), field, 'routes-error-'+ field.id, said( name ) ) } );
			};

			if( page === true ) {
				needField( view.uriInput, word('/_admin/routes/label/uri') );
				routes._addressFields( view ).forEach( function( field ) {
					if( field.locale === '' )
						needField( field.control, word('/_admin/routes/label/httpuri') );
					else
						needRow( view.addressRows, field.locale, word('/_admin/routes/label/httpuri') );
				} );

				if( view.template.disabled !== true )
					needField( view.template, word('/_admin/routes/label/template') );
			}

			routes._locales.forEach( function( locale ) { needRow( view.name, locale, word('/_admin/routes/label/name') ) } );
			routes._locales.forEach( function( locale ) { needRow( view.title, locale, word('/_admin/routes/label/title') ) } );

			if( missing.length === 0 )
				return true;

			routes._missing( missing );

			return false;
		},

		/**
		 *	Whether the texts of every language are what the entry has: a save of such a record writes nothing
		 *
		 *	@param		{Object}		text					Language => { name, title, description }, as _texts() made it
		 *	@param		{Object}		entry
		 *
		 *	@return		{boolean}
		 */
		_sameTexts : function( text, entry ) {

			return Nino.admin.routes._locales.every( function( locale ) {

				const stored = ( entry.text || {} )[locale] || {};

				return [ 'name', 'title', 'description' ].every( function( field ) { return text[locale][field] === String( stored[field] || '' ) } );
			} );
		},

		/**
		 *	Whether a save would send what the page already is: the Element URI, the addresses, the template, the status code, the navigations and the texts as they were
		 *	loaded. A page that nothing was changed in is not written again
		 *
		 *	@param		{Object}		payload				What a save sends
		 *	@param		{Object}		entry					The page as it is loaded
		 *
		 *	@return		{boolean}
		 */
		_same : function( payload, entry ) {

			const routes = Nino.admin.routes;
			const sorted = function( map ) { return JSON.stringify( Object.keys( map ).sort().map( function( key ) { return [ key, map[key] ] } ) ) };
			const held = ( entry.navs || [] ).slice().sort().join();

			return payload.uri === entry.uri
				&& sorted( payload.httpUris ) === sorted( entry.httpUris || {} )
				&& payload.template === ( entry.template ?? '' )
				&& String( payload.statusCode ).trim() === String( entry.statusCode || 200 )
				&& payload.navs.slice().sort().join() === held
				&& routes._sameTexts( payload.text, entry );
		},

		/**
		 *	Create or save the page the record shows. What is wrong with a field is said at the field before anything is sent, and what the server refuses stands at the field it
		 *	names. A page that nothing was changed in is not sent: it is saved as it is. The rows are the ones the server answers with, and the row of the page - the one the
		 *	answer holds and the list did not, where it is new or its Element URI changed - is the one the record takes
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{Function}	done					done( ok, { row, error } )
		 *
		 *	@return		void
		 */
		_save : function( row, ctx, done ) {

			const routes = Nino.admin.routes;
			const view = ctx.view;
			const current = routes._find( row.key ) ?? row;
			const creating = ctx.creating === true;

			if( routes._complete( ctx, true ) === false )
				return done( false, { error : word('/_admin/routes/error/fields') } );

			const text = routes._texts( view );

			// What is on screen: language => address, or '' for the one address of every language - which is also the address of the languages whose fields hold the
			// same one
			const addressed = routes._addressMap( routes._addressFields( view ) );
			const payload = {
				originalUri 		: creating === true ? '' : current.uri,
				uri 						: view.uriInput.value,
				httpUris 				: addressed.httpUris,
				template 				: view.template.value,
				statusCode 			: view.status.value,
				navs						: view.navs === null ? [] : view.navs.value(),
				text 						: text,
			};

			// Nothing was changed: there is nothing to write, and what is stored stays as it is
			if( creating === false && routes._same( payload, current.entry ) === true )
				return done( true, { row : current } );

			// The page as the form was drawn from it: the server saves only where the page is that. Overwrite is the save that answers a conflict, and it is sent
			// without the stamp - the person has said that what is there is to be written over
			if( creating === false && ctx.overwrite !== true && view.stamp !== null )
				payload.stamp = view.stamp;

			// The server refuses a save that leaves the site without its start page or without its 404 page until the same data comes a second time with confirm :
			// true - asked here, once, and a no sends nothing
			// The button that was pressed - Save, or Save and continue - is the one the focus returns to when the question is answered no
			const held = document.activeElement;
			const pressed = held !== null && held !== undefined && ctx.bar !== undefined && ctx.bar !== null && ctx.bar.el.contains( held ) === true && held.tagName === 'BUTTON' ? held : ( ctx.bar ?? {} ).primary ?? null;

			const send = function( confirmed ) {

				routes._apiCall( 'save', confirmed === true ? Object.assign( {}, payload, { confirm : true } ) : payload, function( status, response ) {

					if( confirmed !== true && routes._leaves( status, response ) === true )
						return routes._ask( ctx, status, response, { name : current.name, address : routes._publicPath( addressed.httpUris ) || String( Object.values( addressed.httpUris )[0] ?? '' ) }, pressed, function( ok ) {

							if( ok === true ) {
								ctx.status.saving();
								return send( true );
							}

							// Nothing was sent and nothing is wrong: the form stays as it is, with the state of unsaved input
							done( false, { error : '' } );
							ctx.status.dirty();
						} );

					// Another tab or another person changed or removed the page since the form was drawn: the question of the guide, asked by the record
					if( routes._conflicted( status, response ) === true )
						return done( false, { conflict : true, error : Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) } );

					if( status !== 200 || response === null )
						return done( false, { error : routes._refused( ctx, status, response, addressed.folded ) } );

					routes._saved( payload, addressed, response, done, view );
				} );
			};

			send( false );
		},

		/**
		 *	Whether the server refused a save because it would leave the site without its start page or without its 404 page - a refusal that is lifted by a confirmation
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response			The answer of the server
		 *
		 *	@return		{boolean}
		 */
		_leaves : function( status, response ) {

			return status === 409 && response !== null && typeof response === 'object' && ( response.code === 'routes_leaves_home' || response.code === 'routes_leaves_not_found' || response.code === 'routes_leaves_both' );
		},

		/**
		 *	Whether the server refused a save or a delete because the page was changed or removed since the form was drawn from it - a refusal the person answers
		 *	with Reload, Cancel or Overwrite
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response			The answer of the server
		 *
		 *	@return		{boolean}
		 */
		_conflicted : function( status, response ) {

			return status === 409 && response !== null && typeof response === 'object' && ( response.code === 'routes_conflict' || response.code === 'routes_conflict_gone' );
		},

		/**
		 *	Read the list anew while a record is open and take it in: the rows of the directory, and what the record offers - the templates, the languages, the
		 *	navigations. The record is not drawn over; its form is what the caller makes of it
		 *
		 *	@param		{Function}	done					Called with true once the list is taken in, with false where it could not be read, which is said over the directory
		 *
		 *	@return		void
		 */
		_refresh : function( done ) {

			const panel = Nino.admin.routes;
			const stamp = Nino.admin.stamp( 'templates' );
			const navStamp = Nino.admin.stamp( 'navigations' );
			const textStamp = Nino.admin.stamp( 'texts' );

			panel._apiCall( 'list', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					panel._directory.notice( Nino.adminUi.api.errorText( status, response, '/_admin/common/error/load' ) );
					return done( false );
				}

				panel._take( response );
				panel._stamp = stamp;
				panel._navStamp = navStamp;
				panel._textStamp = textStamp;
				panel._draw();
				done( true );
			} );
		},

		/**
		 *	The answer Reload of the question of a conflict: the list is read and the record shows the page as it is stored, what was typed in this tab is dropped. A page that is
		 *	gone is no page to show: the record is let go, with what was typed in it - the question has said that Reload drops it -, and the directory says that the page is not there
		 *
		 *	@param		{string}		key
		 *	@param		{Function}	done					done( row ) with the row of the page as it is stored, null where it could not be read or is gone
		 *
		 *	@return		void
		 */
		_reload : function( key, done ) {

			const panel = Nino.admin.routes;

			panel._refresh( function( ok ) {

				if( ok === false )
					return done( null );

				const row = panel._find( key );

				if( row !== null )
					return done( row );

				done( null );

				// What the record holds is dropped: it is not unsaved input any more, and closing it asks nothing
				Nino.admin.dirty.snapshot( 'routes' );
				panel._record.close();
				panel._directory.notice( word('/_admin/routes/record/missing') );
			} );
		},

		/**
		 *	Ask whether a save that leaves the site without its start page or its 404 page is to be made: the question names the page and the address it is saved at, and the sentence of the
		 *	code says what follows. The question stands on the record, and the line of the bar says what is true while it stands: the page is not saved yet
		 *
		 *	@param		{Object}		ctx
		 *	@param		{number}		status
		 *	@param		{Object}		response			The refusal, see _leaves()
		 *	@param		{Object}		page					{ name, address } - the page as the panel shows it and the address it is saved at
		 *	@param		{Element|null}	opener			The button the focus goes back to
		 *	@param		{Function}	answered			answered( true ) for yes, answered( false ) for no
		 *
		 *	@return		void
		 */
		_ask : function( ctx, status, response, page, opener, answered ) {

			// The save is not in flight while the question stands
			ctx.status.dirty();

			Nino.adminUi.confirm( 'destructive', {
				title			: word('/_admin/routes/confirm/leaves-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/leaves'), page.name, page.address )+ ' '+ Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ),
				yes				: word('/_admin/routes/confirm/leaves-title'),
				no				: word('/_admin/common/label/cancel'),
				nested		: true,
				opener		: opener,
				onChoose	: function( ok ) { answered( ok === true ) },
			} );
		},

		/**
		 *	A page was written: the rows are what the server answered, the row of the page is the one the answer holds and the list did not - a new page, or one whose
		 *	Element URI changed -, the directory draws it anew, and the window beside the workbench goes to the page that was saved
		 *
		 *	@param		{Object}		payload				What was sent
		 *	@param		{Object}		addressed			What _addressMap() made of the fields
		 *	@param		{Object}		response			{ pages }
		 *	@param		{Function}	done
		 *	@param		{Object}		[view]				What the body of the record keeps: it has the stamp of the page as it is written, which the next save of the form sends
		 *
		 *	@return		void
		 */
		_saved : function( payload, addressed, response, done, view ) {

			const routes = Nino.admin.routes;
			const before = routes._pages.map( function( page ) { return page.uri } );

			routes._pages = response.pages;
			routes._rowsOf();

			const fresh = response.pages.find( function( page ) { return before.indexOf( page.uri ) === -1 } );
			const uri = fresh === undefined ? payload.originalUri : fresh.uri;
			const made = routes._find( uri );

			// The page that was saved is the one a window beside this one goes to
			routes._wrote( routes._publicPath( addressed.httpUris ) );

			if( made === null ) {
				routes._directory.setRows( routes._rows, { keep : true } );
				return done( false, { error : Nino.adminUi.api.errorText( 200, null, '/_admin/common/error/save' ) } );
			}

			if( view !== undefined )
				view.stamp = typeof made.entry.stamp === 'string' ? made.entry.stamp : null;

			routes._directory.update( routes._rows, uri );
			done( true, { row : made } );
		},

		/**
		 *	Save the texts of the page a feature routes the record shows - routes/savetexts writes the three /_nino/webpage keys per language and nothing else. A record
		 *	nothing was changed in sends nothing
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{Function}	done					done( ok, { row, error } )
		 *
		 *	@return		void
		 */
		_saveFeature : function( row, ctx, done ) {

			const routes = Nino.admin.routes;
			const current = routes._findFeature( row.key ) ?? row;

			if( routes._complete( ctx, false ) === false )
				return done( false, { error : word('/_admin/routes/error/fields') } );

			const text = routes._texts( ctx.view );

			if( routes._sameTexts( text, current.entry ) === true )
				return done( true, { row : current } );

			routes._apiCall( 'savetexts', { uri : current.uri, text : text }, function( status, response ) {

				if( status !== 200 || response === null )
					return done( false, { error : routes._refused( ctx, status, response, [] ) } );

				routes._runtime = response.runtime || routes._runtime;
				routes._rowsOf();

				const made = routes._findFeature( current.key ) ?? current;

				routes._wrote( routes._publicPath( { '' : made.entry.httpUri } ) );
				routes._drawFeatures();
				done( true, { row : made } );
			} );
		},

		/**
		 *	What a delete takes and what it leaves behind, as the two groups of the question: the addresses of the page, and - built from what the list already holds - the page's texts (the keys of the languages that hold
		 *	a value, and the path fill) and the template file, shared with how many other routes, or with none. A
		 *	route that resolves its template at runtime names its body instead, there is no single file to name
		 *
		 *	@param		{Object}		entry
		 *
		 *	@return		{Array}										Groups for confirm()
		 */
		_details : function( entry ) {

			const routes = Nino.admin.routes;
			const fields = [];
			const locales = [];

			routes._locales.forEach( function( locale ) {
				const row = ( entry.text || {} )[locale] || {};
				let held = false;

				[ 'name', 'title', 'description' ].forEach( function( field ) {
					if( String( row[field] || '' ).trim() === '' )
						return;
					held = true;
					if( fields.indexOf( field ) === -1 )
						fields.push( field );
				} );

				if( held === true )
					locales.push( Nino.adminUi.localeName( locale, routes._locales ) );
			} );

			const base = '/_nino/webpage'+ entry.uri;
			const keys = [];
			if( fields.length > 0 )
				keys.push( base+ '/'+ fields.join('|')+ ' ('+ locales.join(', ')+ ')' );
			keys.push( base+ '/uri' );

			const stays = [ Nino.adminUi.format( word('/_admin/routes/confirm/stays-texts'), keys.join(', ') ) ];

			if( entry.template === '' )
				stays.push( Nino.adminUi.format( word('/_admin/routes/confirm/stays-body'), entry.body ) );
			else {
				const others = routes._pages.filter( function( page ) { return page.template === entry.template && page.uri !== entry.uri } ).length;

				if( others === 0 )
					stays.push( Nino.adminUi.format( word('/_admin/routes/confirm/stays-template'), entry.template ) );
				else if( others === 1 )
					stays.push( Nino.adminUi.format( word('/_admin/routes/confirm/stays-template-one'), entry.template ) );
				else
					stays.push( Nino.adminUi.format( word('/_admin/routes/confirm/stays-template-shared'), entry.template, others ) );
			}

			return [
				{ title : word('/_admin/routes/confirm/deleted'), lines : routes._addressLines( entry ) },
				{ title : word('/_admin/routes/confirm/kept'), lines : stays },
			];
		},

		/**
		 *	The addresses a delete takes away, one line each
		 *
		 *	@param		{Object}		entry
		 *
		 *	@return		{Array}
		 */
		_addressLines : function( entry ) {

			const uris = entry.httpUris || {};
			const list = Object.keys( uris ).map( function( key ) { return uris[key] } );

			return list.length > 0 ? list : [ entry.httpUri ];
		},

		/**
		 *	Take the page away, after asking. Its routes are removed - every address it has, which the question names; any /_nino/webpage.../name,title,description,uri text meta
		 *	is left in place, same additive-only rule the rest of Nino follows - and so is its template file, which is why the question says what stays (see _details()).
		 *	The question stands on the record; the record is closed by it and the focus goes to the next row. A cancelled question sends nothing
		 *
		 *	@param		{Object}		row
		 *	@param		{Element}		opener					The button that asked, which has the focus again when the question is closed
		 *	@param		{Function}	done						done( true ) where it was deleted, done( null ) where the answer was no, done( false, { error } )
		 *
		 *	@return		void
		 */
		_remove : function( row, opener, done ) {

			const routes = Nino.admin.routes;
			const current = routes._find( row.key ) ?? row;
			// The page as the question names it: the delete is of that page, and of no other state of it
			const stamp = typeof current.entry.stamp === 'string' ? current.entry.stamp : null;

			Nino.adminUi.confirm( 'destructive', {
				title			: word('/_admin/routes/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/routes/confirm/delete-left'), current.paths ),
				details		: routes._details( current.entry ),
				yes				: word('/_admin/routes/confirm/delete-title'),
				no				: word('/_admin/common/label/cancel'),
				nested		: true,
				opener		: opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return done( null );

					const send = function( stamped ) {

						routes._apiCall( 'delete', stamped === true && stamp !== null ? { uri : current.uri, stamp : stamp } : { uri : current.uri }, function( status, response ) {

							if( routes._conflicted( status, response ) === true )
								return routes._removeConflict( status, response, opener, send, done );

							if( status !== 200 || response === null )
								return done( false, { error : Nino.adminUi.api.errorText( status, response, '/_admin/common/error/delete' ) } );

							routes._wrote();

							routes._pages = response.pages;
							routes._rowsOf();
							routes._directory.setRows( routes._rows, { keep : true } );
							done( true );
						} );
					};

					send( true );
				},
			} );
		},

		/**
		 *	The server refused a delete because the page was changed or removed since the list was read. A page that is gone is what the delete was for: the list is read and the
		 *	row is out of it. A page that was changed is the question of the guide - Delete anyway sends the delete without the state, Reload drops what this tab shows and
		 *	reads the list, Cancel leaves it all as it is
		 *
		 *	@param		{number}		status
		 *	@param		{Object}		response			The refusal, see _conflicted()
		 *	@param		{Element}		opener				The button the focus goes back to
		 *	@param		{Function}	send					send( false ) sends the delete without the state of the page
		 *	@param		{Function}	done					The record's: done( true ), done( null ), done( false, { error } )
		 *
		 *	@return		void
		 */
		_removeConflict : function( status, response, opener, send, done ) {

			const routes = Nino.admin.routes;
			const sentence = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/delete' );

			if( response.code === 'routes_conflict_gone' )
				return routes._refresh( function( ok ) { ok === true ? done( true ) : done( false, { error : sentence } ) } );

			Nino.adminUi.conflict( {
				message : sentence,
				nested : true,
				opener : opener,
				text : { overwrite : word('/_admin/routes/confirm/delete-title') },
				onOverwrite : function() { send( false ) },
				onReload : function() {

					routes._refresh( function( ok ) {

						if( ok === false )
							return done( false, { error : sentence } );

						// What the record holds is dropped, as the question said: the list is the one the server holds
						done( null );
						Nino.admin.dirty.snapshot( 'routes' );
						routes._record.close();
					} );
				},
				onCancel : function() { done( null ) },
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.routes.init );

	// The directory names its pages, and the record describes its page, in the content language, whoever moved it (see Nino.admin.workLocale)
	Nino.admin.workLocale.follow( 'routes', Nino.admin.routes._followLocale );

})(window, document, document.documentElement, document.body);
