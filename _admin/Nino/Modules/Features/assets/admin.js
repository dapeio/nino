/**
 *	Nino										A compact filesystembased php framework
 *	admin.js								"Features" module: every feature installed under
 *													features/ and the catalogue's offers, in four tabs -
 *													Active, Inactive, Available (what the catalogue offers
 *													that is not already current, so an install or an update)
 *													and Applications - every one of them the same table: a
 *													search over it, the columns name, category, version and
 *													state, and the small buttons of a row at its end. The
 *													count of a tab is the chip on its button. A feature
 *													has an address of its own, #features/<key>, behind its
 *													row: a view with the context bar and its way back, what
 *													it is and - for a feature without a panel of its own -
 *													its settings, and the bar of every form: Uninstall at the
 *													left for a feature that is off, the side actions, and the
 *													one main button at the right. Where the feature has a panel of
 *													its own, the settings are edited in a tab of that panel
 *													(settings.js, drawn with the renderers of this file) and
 *													this view links there.
 *
 *													A switch is asked about as the workbench's modes ask
 *													(the confirm block, level 'big'; removing a
 *													directory is 'destructive'), and ends in a reload where
 *													the rail has to change: activating, deactivating and
 *													installing - the panel a feature brings, or takes away,
 *													is only there once the page is built again. What it
 *													says is a toast ("Social media links is active."), and
 *													what is left over - the places that still hold the
 *													shortcodes of a feature that is off, the sections of the
 *													privacy policy that still describe it - is a notice at
 *													the top of the list; both are kept in the session for the
 *													page that follows the reload (see _reload()). What is
 *													going on - activating, installing, setting up - stands
 *													on the row, which holds its buttons meanwhile, and a
 *													refusal stands there too.
 *
 *													features/list answers the catalogue exactly as
 *													\Nino\Catalogue::cached() last left it on disk, so the
 *													Available tab fills the moment the panel opens without
 *													a request of its own - the workbench still never
 *													contacts the catalogue on its own. Only Refresh
 *													catalogue posts features/catalogue, which re-fetches
 *													and answers the same shape.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	// The words that stand after #features in the address and are tabs: a feature of one of these keys is reached by its row
	// The forms whose settings are on their way to the server
	const SAVING = new WeakSet();

	const TABS = [ 'active', 'inactive', 'available', 'applications' ];

	Nino.admin.features = {

		_ready 			: false,

		// A list request is in flight - see init()
		_loading		: false,
		_dir 				: '',
		_features 	: [],
		// The directories whose manifest the kernel refused, { dir, reason } - shown with the
		// inactive features, which they are not: they are listed so that none vanishes unexplained
		_rejected 	: [],
		// The catalogue url as features/list names it - '' when it is switched off,
		// which is when the panel offers no Refresh button at all
		_catalogueUrl : '',
		// Whether the features directory can be written - features/list and
		// features/catalogue both answer it fresh, live, every time
		_writable 	: true,
		// { fetched, offers } from features/list's own 'catalogue', or
		// from a features/catalogue answer once Refresh was pressed; null
		// before either ever ran
		_cache 			: null,
		// What the line of the list bar says while a refresh runs, or why the last one failed
		_catalogueMsg : { text : '', error : false, busy : false },
		// Which tab is on screen - kept across a re-render so an action does
		// not jump the panel back to the first one. Active is where a panel
		// opens: what this installation is running is the answer to the
		// question that brought someone here
		_tab 				: 'active',
		// What the search over the rows of a tab holds - one value for the panel, kept across a tab and a
		// drawing and, through _reload(), across the reload an action ends in
		_query 			: '',
		// Which category the list is narrowed to, '' for all of them. Kept
		// across a re-render, so that an action does not widen it, and dropped by _renderHead()
		// when the category it names is no longer among the ones on offer
		_category 	: '',
		// application key => { text, error }: what the last set-up of each said, kept
		// across the re-render a failed one ends in (the row is built fresh each time)
		_applicationMsg : {},
		// Whether this page switched a feature on: the workbench it holds was built
		// before that, so a link into a panel that feature brings needs it built again
		_switchedOn : false,
		// The key of the feature whose own view is open, '' while the list is - as state, so the
		// reload a save ends in comes back to the same view rather than dropping to the list
		_openFeature : '',
		// The key of the row the focus goes to once the page that was built again is drawn - see _restored()
		_focusAfter : '',
		// What the page that followed a reload has to say once it is drawn: the toast - see _restore()
		_toastAfter : '',
		// Which of the two tabs of a feature's own view is on. Kept across the re-render a save ends
		// in, and reset to the first one when a different feature is opened. 'about' is where a view
		// opens: somebody who already knows the feature is one click from its settings, somebody who
		// does not needs the sentence first
		_detailTab : 'about',
		// The line and the buttons of a row, by the row's key: the table draws its rows again with
		// every sort, page and search, and a line that says what is going on must be the same element after it
		_rowParts : new Map(),
		// key => the sentence of a refusal that came with a state that was out of date, kept for the row drawn after the list was read again
		_refusals : {},
		// The open view's feature, form, bar and tabs - what the shell's question about unsaved input saves through
		_detailParts : null,
		// What stays at the top of the list until the next tab or the next action: the places that
		// still hold the shortcodes of a feature that was switched off, the privacy policy's sections
		_notices : [],
		// Where what a reload has to say is kept while the workbench is built again
		_afterKey : 'nino-admin-features-after',

		/**
		 *	Load every feature with its state, settings and the cached
		 *	catalogue, and render the panel from what came back
		 *
		 *	@param		{Function}	[then]			Run once the list is back
		 *
		 *	@return		void
		 */
		init : function( then ) {

			const wrap = dc.getElementById('features-list');
			if( wrap === null )
				return;

			// One list request per page load. The shell's onReady is bound
			// before this file's own (script.js loads first), so both fired
			// init() on a page opened with #features in the hash - two
			// requests, and the second one rebuilding the panel the first had
			// just built
			if( Nino.admin.features._loading === true )
				return;
			Nino.admin.features._loading = true;

			Nino.admin.features._apiCall( 'list', {}, function( status, response ) {

				Nino.admin.features._loading = false;
				// Back to the list first: an error written into a view the list is covering would not be read
				if( status !== 200 || response === null ) {
					Nino.adminUi.showLevel( wrap, { focus : false } );
					return Nino.admin.features._showError( wrap, status, response );
				}

				Nino.admin.features._take( response );
				const first = Nino.admin.features._ready === false;

				if( first === true ) {
					Nino.admin.features._fromAddress( Nino.admin.router.current() );
					Nino.admin.features._restore();
				}

				Nino.admin.features._renderPanel();
				Nino.admin.features._ready = true;

				if( first === true )
					Nino.admin.features._restored();

				if( typeof then === 'function' )
					then();
			} );
		},

		/**
		 *	What features/list answered, as the panel's state
		 *
		 *	@param		{Object}		response
		 *
		 *	@return		void
		 */
		_take : function( response ) {

			Nino.admin.features._dir 					= response.dir || '';
			Nino.admin.features._catalogueUrl	= response.catalogueUrl || '';
			Nino.admin.features._writable			= response.writable === true;
			Nino.admin.features._cache 				= response.catalogue || null;
			Nino.admin.features._features			= response.features || [];
			Nino.admin.features._rejected			= response.rejected || [];
		},

		/*	The shell calls this every time its panel is shown again, and the
			contract it documents is that switching panels never resets
			anything: "jumping back and forth is always exactly where you left
			it". Where the address names another level than the one on screen -
			a step through the browser's history changed it and nothing else -
			that level is shown, and leaving a form that holds unsaved input
			asks first (see Nino.admin.router.leave())	*/
		showCurrent : function() {

			if( Nino.admin.features._ready === false ) {
				Nino.admin.features.init();
				return;
			}

			const hash = Nino.admin.router.current();

			if( hash.panel === 'features' && Nino.admin.features._follow( hash.parts ) === true )
				return;

			Nino.admin.features._address();
		},

		/**
		 *	Move to the level the address names, if it is not the one on screen: a feature's view, or a tab of the list
		 *
		 *	@param		{Array}		parts				The address behind #features
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const was = { tab : Nino.admin.features._tab, key : Nino.admin.features._openFeature };
			const now = Nino.admin.features._named( parts );

			if( now.key === was.key && now.tab === was.tab )
				return false;

			Nino.admin.router.leave( [ 'features' ], was.key !== '', function() {
				Nino.admin.features._tab = now.tab;
				Nino.admin.features._openFeature = now.key;
				Nino.admin.features._detailTab = 'about';
				Nino.admin.features._notices = [];
				Nino.admin.features._renderPanel();
			}, Nino.admin.features._address );

			return true;
		},

		/**
		 *	The tab and the feature an address names: one of the four tabs, or a feature's key
		 *
		 *	@param		{Array}		parts				The address behind #features
		 *
		 *	@return		{Object}									{ tab, key } - the tab the list is on, the feature that is open or ''
		 */
		_named : function( parts ) {

			const word = typeof parts[0] === 'string' ? parts[0] : '';

			// A tab is written with an @, which no key begins with: a feature may be called `active`
			if( word.charAt( 0 ) === '@' && TABS.indexOf( word.slice( 1 ) ) !== -1 )
				return { tab : word.slice( 1 ), key : '' };

			// A view keeps the tab it was opened from; one that is opened by its address has none, and is on its feature's own
			if( word !== '' ) {
				const feature = Nino.admin.features._features.filter( function( f ) { return f.key === word } )[0];
				return { tab : feature !== undefined && feature.active === false ? 'inactive' : 'active', key : word };
			}

			return { tab : 'active', key : '' };
		},

		/**
		 *	The tab and the open view the address names, before the first drawing
		 *
		 *	@param		{Object}		hash				Nino.admin.router.current()
		 *
		 *	@return		void
		 */
		_fromAddress : function( hash ) {

			if( hash.panel !== 'features' )
				return;

			const named = Nino.admin.features._named( hash.parts );

			Nino.admin.features._tab = named.tab;
			Nino.admin.features._openFeature = named.key;
		},

		/**
		 *	Call a features/* action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "features/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'features/'+ endpoint, payload, callback );
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
		 *	The whole panel, from state: the head that holds the tab strip and
		 *	the category, what stays at the top of the list, the current tab's
		 *	content and the bar of the list (only while the catalogue is
		 *	switched on), and the open feature's view unless it is left as it is
		 *
		 *	@param		{boolean}		[keepDetail]	True to leave the open feature's view alone
		 *
		 *	@return		void
		 */
		_renderPanel : function( keepDetail ) {

			const wrap = dc.getElementById('features-list');

			Nino.admin.features._rowParts = new Map();

			Array.prototype.slice.call( wrap.children ).forEach( function( child ) { wrap.removeChild( child ) } );

			const head = Nino.admin.features._renderHead();

			if( head !== null )
				wrap.appendChild( head );

			const notices = Nino.admin.features._renderNotices();

			if( notices !== null )
				wrap.appendChild( notices );

			Nino.admin.features._renderTabContent( wrap );

			if( Nino.admin.features._catalogueUrl !== '' )
				wrap.appendChild( Nino.admin.features._renderActionBar() );

			if( keepDetail !== true )
				Nino.admin.features._renderDetail();
		},

		/**
		 *	What stays at the top of the list - something that needs attention until the next tab or the next
		 *	action replaces it: one notice for each line, which keeps the breaks it was written with
		 *
		 *	@return		{?Element}						<div id="features-notices">, null where there is nothing
		 */
		_renderNotices : function() {

			if( Nino.admin.features._notices.length === 0 )
				return null;

			const box = Nino.adminUi.el( 'div', '' );
			box.id = 'features-notices';

			Nino.admin.features._notices.forEach( function( text ) { box.appendChild( Nino.adminUi.notice( text ) ) } );

			return box;
		},

		/**
		 *	Whether the open feature's view holds input nobody has saved -
		 *	the shell says (see Nino.admin.dirty); a shell without the registry
		 *	never does. The same question the Maintenance panel asks before it
		 *	draws its form again
		 *
		 *	@return		{boolean}
		 */
		_holdsInput : function() {
			return Nino.admin.features._openFeature !== '' && typeof Nino.admin.dirty === 'object' && typeof Nino.admin.dirty.isDirty === 'function' && Nino.admin.dirty.isDirty( [ 'features' ] ) === true;
		},

		/**
		 *	How many features/offers each tab holds, for its chip - of what
		 *	the category lets through, so that a category says which tab its
		 *	features are on rather than how much is being hidden
		 *
		 *	@return		{Object}							{ active, inactive, available, applications }
		 */
		_counts : function() {

			const f = Nino.admin.features;
			const hit = function( row ) { return f._hit( row ) };

			return {
				active		: f._features.filter( function( x ) { return x.active === true && f._matches( x ) } ).map( f._featureRow ).filter( hit ).length
					+ f._rejectedShown( true ).map( f._rejectedRow ).filter( hit ).length,
				inactive	: f._features.filter( function( x ) { return x.active === false && f._matches( x ) } ).map( f._featureRow ).filter( hit ).length
					+ f._rejectedShown( false ).map( f._rejectedRow ).filter( hit ).length,
				available	: f._availableOffers().filter( f._matches ).map( f._offerRow ).filter( hit ).length,
				applications	: f._applications().map( f._applicationRow ).filter( hit ).length,
			};
		},

		/**
		 *	Whether a row is what the search is looking for: the text of its columns and its key and
		 *	description, case ignored - the same text the table's own search reads
		 *
		 *	@param		{Object}	row					A row of _featureRow(), _rejectedRow(), _offerRow() or _applicationRow()
		 *
		 *	@return		{boolean}
		 */
		_hit : function( row ) {

			const query = Nino.admin.features._query.trim().toLowerCase();

			return query === '' || [ row.name, row.category, row.version, row.state, Nino.admin.features._searchText( row ) ].join(' ').toLowerCase().indexOf( query ) !== -1;
		},

		/**
		 *	What the search reads of a row besides its columns
		 *
		 *	@param		{Object}	row
		 *
		 *	@return		{string}
		 */
		_searchText : function( row ) {
			return row.key+ ' '+ ( row.description || '' );
		},

		/**
		 *	Say the counts of the tabs again, in place: a search narrows them while the panel stays
		 *	as it is drawn, so that the table that holds the focus is not built anew
		 *
		 *	@return		void
		 */
		_refreshCounts : function() {

			const counts = Nino.admin.features._counts();

			TABS.forEach( function( key ) {
				const tab = dc.getElementById( 'features-tabbutton-'+ key );
				const chip = tab === null ? null : tab.querySelector('.nino-admin-tab-badge');

				if( chip !== null )
					chip.textContent = String( counts[key] );
			} );
		},

		/**
		 *	The refused manifests of one tab; the category select, which a refused manifest has none of, hides them.
		 *	Those whose class is listed in the modules all the same belong to Active, the others to Inactive
		 *
		 *	@param		{boolean}	active			Of the Active tab or of the Inactive one
		 *
		 *	@return		{Array}								Entries { dir, reason, active }
		 */
		_rejectedShown : function( active ) {

			// A refused manifest has no category: the select, set to one, hides them
			if( Nino.admin.features._category !== '' )
				return [];

			return Nino.admin.features._rejected.filter( function( entry ) { return ( entry.active === true ) === active } );
		},

		/**
		 *	Whether one feature or offer is in the category the select narrows to; with
		 *	none chosen, all of them are. The search over what the tab shows is the
		 *	table's own
		 *
		 *	@param		{Object}	entry				A feature of features/list or a cached offer
		 *
		 *	@return		{boolean}
		 */
		_matches : function( entry ) {

			const category = Nino.admin.features._category;

			return category === '' || ( entry.category || '' ) === category;
		},

		/**
		 *	What a category slug is called here. The workbench's own fills name
		 *	the ones the kernel publishes (\Nino\Features::CATEGORIES); a slug
		 *	without a fill - a feature filed under a category newer than this
		 *	workbench - is its own label, which reads as a slug but groups and
		 *	filters like any other
		 *
		 *	@param		{string}	slug				'' for a feature that names none
		 *
		 *	@return		{string}
		 */
		_categoryLabel : function( slug ) {

			const key = slug ? String( slug ) : 'none';

			return Nino.content.getText('/_admin/features/category/'+ key ) || key;
		},

		/**
		 *	The categories to offer in the select: the ones the features and
		 *	the offers on screen actually carry, each once, sorted by the name
		 *	they are shown under.
		 *
		 *	Built from the list rather than from a fixed vocabulary, so the
		 *	select never offers a heading nothing is filed under - and picks up
		 *	a category this workbench has no name for the moment a catalogue
		 *	publishes one
		 *
		 *	@return		{Array}								[ { value, label }, ... ]
		 */
		_categories : function() {

			const seen = {};

			Nino.admin.features._features
				.concat( Nino.admin.features._availableOffers() )
				.forEach( function( entry ) { seen[ entry.category || '' ] = true } );

			return Object.keys( seen ).map( function( slug ) {
				return { value : slug, label : Nino.admin.features._categoryLabel( slug ) };
			} ).sort( function( a, b ) {
				// A feature without a category last, whatever it is called:
				// "no category" is not a category, it is the rest
				if( a.value === '' ) return 1;
				if( b.value === '' ) return -1;
				return a.label.localeCompare( b.label );
			} );
		},

		/**
		 *	The cached offers that belong on the Available tab: whatever is
		 *	not already current - not on disk at all, or on disk in an older
		 *	version, or one no version of which fits this kernel (shown
		 *	greyed, with what it asks for)
		 *
		 *	@return		{Array}
		 */
		_availableOffers : function() {
			const cache = Nino.admin.features._cache;
			return cache === null ? [] : cache.offers.filter( function( o ) { return o.state !== 'current' } );
		},

		/**
		 *	The applications the cached catalogue offers this kernel, as the panel
		 *	phrased them
		 *
		 *	@return		{Array}
		 */
		_applications : function() {
			const cache = Nino.admin.features._cache;
			return cache === null || Array.isArray( cache.applications ) === false ? [] : cache.applications;
		},

		/**
		 *	What follows #features in the address of the level that is on: the open feature, else the tab
		 *	(Active is the bare #features)
		 *
		 *	@return		{Array}
		 */
		_tabParts : function() {

			if( Nino.admin.features._openFeature !== '' )
				return [ Nino.admin.features._openFeature ];

			return Nino.admin.features._tabAddress( Nino.admin.features._tab );
		},

		/**
		 *	What follows #features in the address of a tab: nothing for Active, else the name with its @
		 *
		 *	@param		{string}	tab
		 *
		 *	@return		{Array}
		 */
		_tabAddress : function( tab ) {
			return tab === 'active' ? [] : [ '@'+ tab ];
		},

		/**
		 *	Write the level that is on into the address - #features/inactive for the Inactive tab, #features for
		 *	Active, #features/<key> for a feature's view - so a reload comes back to it. The address only keeps
		 *	true to the screen here; a move the person made is written by Nino.admin.router.go() first
		 *
		 *	@return		void
		 */
		_address : function() {
			Nino.admin.router.set( 'features', Nino.admin.features._tabParts() );
		},

		/**
		 *	Build the workbench again and say something there. Every switch ends this way: the rail is
		 *	rendered by the server, so the panel a feature brings, or takes away, is only there once the
		 *	page is built again. What it says - the toast and the notices - and where the person was - the
		 *	search, the category, and the row the focus goes to now that the one it was on has left the
		 *	tab - are kept in the session for the page that is coming, which shows them where a dialog of
		 *	the browser would hold the reload
		 *
		 *	@param		{string}	text				What to say, '' for nothing
		 *	@param		{Object}	[options]		{ focus, notices, parts } - the key of the row the focus goes to, the lines that stay at the top of the list, and the address to come back to where it is not the one that is on
		 *
		 *	@return		void
		 */
		_reload : function( text, options ) {

			options = options || {};

			try {
				wn.sessionStorage.setItem( Nino.admin.features._afterKey, JSON.stringify( {
					text			: text,
					notices		: options.notices || [],
					filter		: Nino.admin.features._query,
					category	: Nino.admin.features._category,
					focus			: options.focus || '',
					// Whose it is and when: a session that outlives a sign-out holds it for the next account
					user			: Nino.admin.features._account(),
					at				: Date.now(),
				} ) );
			}
			catch( e ) {
				// A browser that keeps nothing for the page reloads all the same
			}

			wn.location.hash = '#'+ [ 'features' ].concat( options.parts || Nino.admin.features._tabParts() ).join('/');
			wn.location.reload();
		},

		/**
		 *	The account this page is of, as the rail says it - '' where the page has no rail
		 *
		 *	@return		{string}
		 */
		_account : function() {

			const el = dc.getElementById('admin-user-email');

			return el === null ? '' : String( el.textContent );
		},

		/**
		 *	What _reload() kept, taken out of the session: the notices and the category are set before the
		 *	first drawing, the toast and the focus are asked for after it - see _restored()
		 *
		 *	@return		void
		 */
		_restore : function() {

			let kept = null;

			try {
				const raw = wn.sessionStorage.getItem( Nino.admin.features._afterKey );
				wn.sessionStorage.removeItem( Nino.admin.features._afterKey );
				kept = JSON.parse( raw );
			}
			catch( e ) {
				kept = null;
			}

			if( typeof kept !== 'object' || kept === null )
				return;

			// Kept for the page that followed the reload, which is made within moments by the account that pressed the button
			if( kept.user !== Nino.admin.features._account() || typeof kept.at !== 'number' || Date.now() - kept.at > 120000 || Date.now() - kept.at < -5000 )
				return;

			Nino.admin.features._toastAfter	= typeof kept.text === 'string' ? kept.text : '';
			Nino.admin.features._notices		= Array.isArray( kept.notices ) === true ? kept.notices.filter( function( line ) { return typeof line === 'string' && line !== '' } ) : [];
			Nino.admin.features._query			= typeof kept.filter === 'string' ? kept.filter : '';
			Nino.admin.features._category		= typeof kept.category === 'string' ? kept.category : '';
			Nino.admin.features._focusAfter	= typeof kept.focus === 'string' ? kept.focus : '';
		},

		/**
		 *	What the page that was built again has to do once it is drawn: say what the switch did, and put the
		 *	focus onto the button of the row that stands where the one just switched on stood, else onto the tab
		 *	that is on - the element that had it is gone with the old page
		 *
		 *	@return		void
		 */
		_restored : function() {

			const key = Nino.admin.features._focusAfter || '';
			const text = Nino.admin.features._toastAfter || '';

			Nino.admin.features._focusAfter = '';
			Nino.admin.features._toastAfter = '';

			if( text !== '' )
				Nino.adminUi.say( text );

			if( text === '' && key === '' )
				return;

			// An open view takes the focus itself
			if( Nino.admin.features._openFeature !== '' )
				return;

			const target = key === '' ? null : Nino.admin.features._firstControl( Nino.admin.features._rowOf( key ), true );
			const tab = dc.getElementById( 'features-tabbutton-'+ Nino.admin.features._tab );

			( target !== null ? target : tab )?.focus();
		},

		/**
		 *	The head of the list: the tab strip and the category over everything the tabs hold. Both stand in
		 *	the pane's head the shell renders over every panel (Nino.adminUi.panelHead()) - the strip beside
		 *	the panel's name, the category in the slot for actions at its end - so the list takes no row of its
		 *	own for them. Only where there is none - the script rendered somewhere other than its pane - are
		 *	they drawn in a row of the list instead. The select is not inside the tablist: a tablist holds
		 *	tabs, and a control in it would be read out as one. The search over a tab's rows is the table's own
		 *
		 *	@return		{?Element}						<div class="admin-features-head"> where there is no head of the pane, else null
		 */
		_renderHead : function() {

			// A category that was picked and is now gone - the last feature
			// carrying it was removed, or a catalogue refresh dropped it - would
			// otherwise hide every row with no visible reason
			const categories = Nino.admin.features._categories();

			if( categories.some( function( c ) { return c.value === Nino.admin.features._category } ) === false )
				Nino.admin.features._category = '';

			const strip			= Nino.admin.features._renderTabs();
			const paneHead	= Nino.adminUi.panelHead( dc.getElementById('features-list') );
			const head			= paneHead === null || paneHead.actions === null ? dc.createElement('div') : null;

			// Nothing to narrow when everything on screen shares one category -
			// a select with a single option is a control that cannot be used
			const select = categories.length > 1 && Nino.admin.features._tab !== 'applications' ? Nino.admin.features._renderCategoryFilter( categories ) : null;

			if( paneHead === null )
				head.appendChild( strip );
			else
				paneHead.tabs( strip );

			if( paneHead !== null && paneHead.actions !== null ) {
				Array.prototype.slice.call( paneHead.actions.children ).forEach( function( child ) { paneHead.actions.removeChild( child ) } );

				if( select !== null )
					paneHead.actions.appendChild( select );
			}
			else if( select !== null ) {
				head.className = 'admin-features-head';
				head.appendChild( select );
			}

			return head;
		},

		/**
		 *	The category select: every category on screen, plus the entry that
		 *	turns the narrowing off. A plain select rather than a row of
		 *	chips - the head is pinned to the top of the pane, and a second
		 *	row of controls costs a long list that much of its height on every
		 *	screen it is read on
		 *
		 *	@param		{Array}		categories	What _categories() found
		 *
		 *	@return		{Element}							<select>
		 */
		_renderCategoryFilter : function( categories ) {

			// Same as the search box: the whole panel is drawn again so the tab counts follow,
			// and the focus goes back to the control that had it
			return Nino.adminUi.selectField( {
				key				: 'category',
				id				: 'features-category',
				className	: 'admin-features-category',
				label			: Nino.content.getText('/_admin/features/label/category'),
				options		: [ { value : '', label : Nino.content.getText('/_admin/features/label/category-all') } ].concat( categories ),
				value			: Nino.admin.features._category,
				onChange	: function( value ) {
					Nino.admin.features._category = value;
					Nino.admin.features._renderPanel();
					const next = dc.getElementById('features-category');
					if( next !== null )
						next.focus();
				},
			} );
		},

		/**
		 *	The tab strip: four tabs, each with its count as a chip - the one the workbench puts on a tab
		 *	(see Nino.adminUi.tabBadge(), which finds only the buttons of the shell's own strip) -
		 *	wired through the shared button row so the active one is underlined and a click switches the
		 *	panel below without reloading anything. Active first: what this installation runs is what a
		 *	panel opens on
		 *
		 *	@return		{Element}							<div role="tablist">
		 */
		_renderTabs : function() {

			const counts	= Nino.admin.features._counts();

			// Literal lookups rather than one built from a key: a fill
			// that only a concatenated argument ever names is invisible to
			// the static check every panel script is held to (see
			// tests/admin-shell-smoke.php and tests/admin-features-js-smoke.js)
			const labels = {
				available	: Nino.content.getText('/_admin/features/tab/available'),
				inactive	: Nino.content.getText('/_admin/features/tab/inactive'),
				active		: Nino.content.getText('/_admin/features/tab/active'),
				applications	: Nino.content.getText('/_admin/features/tab/applications'),
			};

			const strip = Nino.adminUi.tabs( TABS.map( function( key ) { return { key : key, label : labels[key], badge : counts[key] } } ), { id : 'features-tabbutton', active : Nino.admin.features._tab, onSelect : function( key ) {

				// The strip stands in the pane's head, above both levels: a tab picked
				// while a feature's view is open leaves that view, else the list
				// the tab shows is drawn under it and the view stays on top. Where the
				// view holds input nobody saved, the shell asks first, and a Cancel
				// puts the strip back on the tab that was on. The strip is drawn again
				// either way, so the focus goes to the tab that is on afterwards - the
				// next arrow key starts from there
				const focusTab = function() {
					const tab = dc.getElementById( 'features-tabbutton-'+ Nino.admin.features._tab );
					if( tab !== null )
						tab.focus();
				};

				const show = function() {
					Nino.admin.features._openFeature = '';
					Nino.admin.features._tab = key;
					Nino.admin.features._notices = [];
					Nino.admin.router.go( 'features', Nino.admin.features._tabParts() );
					Nino.admin.features._address();
					Nino.admin.features._renderPanel();
					focusTab();
				};

				if( Nino.admin.features._holdsInput() === true )
					Nino.admin.dirty.guard( [ 'features' ], show, function() {
						Nino.admin.features._renderPanel( true );
						focusTab();
					} );
				else
					show();
			} } );

			// The one panel below the strip, which the four tabs share, is what each of them controls
			TABS.forEach( function( key ) {
				strip.tabs[key].setAttribute( 'aria-controls', 'features-tabpanel' );
			} );

			strip.el.classList.add('nino-admin-tabs--panel');

			return strip.el;
		},

		/**
		 *	The bar of the list while the catalogue is on: Refresh catalogue as its one button, and a
		 *	line that says when the cache is from, that it is not loaded yet, what the last refresh
		 *	answered or that it is under way
		 *
		 *	@return		{Element}							nino-admin-actionbar nino-admin-list-actions
		 */
		_renderActionBar : function() {

			const bar = Nino.adminUi.actionBar( { list : true, status : { line : Nino.adminUi.el('p'), fresh : true }, primary : Nino.admin.features._refreshButton() } );
			const msg = Nino.admin.features._catalogueMsg;

			if( msg.error === true )
				bar.line.fail( msg.text );
			else
				bar.line.idle( Nino.admin.features._statusText() );

			return bar.el;
		},

		/**
		 *	The button that loads the catalogue, for the bar and for the empty state that has nothing to show before it
		 *
		 *	@param		{string}		[kind]			The kind of the button: none for the one the bar makes its main button
		 *
		 *	@return		{Element}
		 */
		_refreshButton : function( kind ) {

			return Nino.adminUi.button( Nino.content.getText('/_admin/features/label/catalogue-refresh'), function() { Nino.admin.features._refreshCatalogue() }, { kind : kind, disabled : Nino.admin.features._catalogueMsg.busy === true } );
		},

		/**
		 *	What the line of the list bar reads: a message from the last
		 *	refresh while there is one, else when the cache is from, else that
		 *	there is none yet
		 *
		 *	@return		{string}
		 */
		_statusText : function() {

			if( Nino.admin.features._catalogueMsg.text !== '' )
				return Nino.admin.features._catalogueMsg.text;

			const cache = Nino.admin.features._cache;

			return cache !== null
				? Nino.adminUi.format( Nino.content.getText('/_admin/features/label/catalogue-status'), cache.fetched )
				: Nino.content.getText('/_admin/features/label/catalogue-unloaded');
		},

		/**
		 *	Refresh the catalogue: the one request the workbench ever makes to
		 *	it, and only from here. What came back becomes the cache every tab
		 *	reads, so a fresh Available count and offers follow without
		 *	reading the list again
		 *
		 *	@return		void
		 */
		_refreshCatalogue : function() {

			// The open feature's view is no part of what a refresh changes
			// - the list and the bar are - and it is not drawn again
			// over settings somebody has typed into, whether that is the
			// click or the answer, which can come after he opened a feature
			if( Nino.admin.features._catalogueMsg.busy === true )
				return;

			Nino.admin.features._catalogueMsg = { text : Nino.content.getText('/_admin/features/msg/catalogue-loading'), error : false, busy : true };
			Nino.admin.features._renderPanel( true );

			Nino.admin.features._apiCall( 'catalogue', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.admin.features._catalogueMsg = { text : Nino.adminUi.api.errorText( status, response, '/_admin/features/error/catalogue' ), error : true, busy : false };
					Nino.admin.features._renderPanel( true );
					return;
				}

				Nino.admin.features._cache 				= { fetched : response.fetched, offers : response.offers, applications : response.applications || [] };
				Nino.admin.features._writable			= response.writable === true;
				Nino.admin.features._catalogueMsg	= { text : '', error : false, busy : false };
				Nino.admin.features._renderPanel( true );
			} );
		},

		/**
		 *	The current tab's content, into its own tabpanel: the table of its rows, or the empty state that
		 *	says why there is none
		 *
		 *	@param		{Element}	wrap
		 *
		 *	@return		void
		 */
		_renderTabContent : function( wrap ) {

			const panel = dc.createElement('div');
			panel.className = 'nino-admin-tabpanel';
			panel.id = 'features-tabpanel';
			panel.setAttribute( 'role', 'tabpanel' );
			// The four tabs draw into this one panel rather than into four,
			// so the panel names whichever of them is open - and each of them
			// names the panel (see _renderTabs())
			panel.setAttribute( 'aria-labelledby', 'features-tabbutton-'+ Nino.admin.features._tab );
			wrap.appendChild( panel );

			if( Nino.admin.features._tab === 'inactive' )
				return Nino.admin.features._fillInstalled( panel, false );
			if( Nino.admin.features._tab === 'active' )
				return Nino.admin.features._fillInstalled( panel, true );
			if( Nino.admin.features._tab === 'applications' )
				return Nino.admin.features._fillApplications( panel );
			return Nino.admin.features._fillAvailable( panel );
		},

		/**
		 *	The Inactive or Active tab: one row for each feature, which opens its view, and for each
		 *	directory whose manifest the kernel refused
		 *
		 *	@param		{Element}	panel
		 *	@param		{boolean}	active
		 *
		 *	@return		void
		 */
		_fillInstalled : function( panel, active ) {

			const all	= Nino.admin.features._features.filter( function( f ) { return f.active === active } );
			const list	= all.filter( Nino.admin.features._matches );
			const refused	= Nino.admin.features._rejectedShown( active );

			// The tab is empty, or the category emptied it - two different things to
			// be told, and the second one is not a reason to explain what a
			// feature directory is
			if( all.length === 0 && Nino.admin.features._rejected.filter( function( entry ) { return ( entry.active === true ) === active } ).length === 0 ) {
				// Only a directory with no feature in it at all is told what a feature directory is; a tab that is empty while features are installed says which way it is
				const key = active === true ? '/_admin/features/hint/active-empty' : ( Nino.admin.features._features.length > 0 ? '/_admin/features/hint/inactive-empty' : '/_admin/features/hint/empty' );
				panel.appendChild( Nino.adminUi.emptyState( Nino.adminUi.format( Nino.content.getText( key ), Nino.admin.features._dir ) ) );
				return;
			}

			if( list.length === 0 && refused.length === 0 ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/nomatch') ) );
				return;
			}

			panel.appendChild( Nino.admin.features._renderTable( list.map( Nino.admin.features._featureRow ).concat( refused.map( Nino.admin.features._rejectedRow ) ), true ) );
		},

		/**
		 *	The rows of a tab as the table takes them: the plain values it sorts and
		 *	searches by - name, category, version and state - and what the cells and
		 *	the buttons are made from
		 *
		 *	@param		{Object}	feature			An entry of features/list
		 *
		 *	@return		{Object}
		 */
		_featureRow : function( feature ) {

			const notes = [];

			if( feature.requires.length > 0 )
				notes.push( { text : Nino.adminUi.format( Nino.content.getText('/_admin/features/label/requires'), feature.requires.join( ', ' ) ), bad : false } );

			if( feature.circle === true )
				notes.push( { text : Nino.content.getText('/_admin/features/label/circle'), bad : false } );

			// What stands in the way of switching it on, one line each - the
			// kernel's own words, which is where the check lives
			feature.problems.forEach( function( problem ) { notes.push( { text : problem, bad : true } ) } );

			let state = 'inactive';

			if( feature.active === true )
				state = feature.update === true ? 'update' : 'active';
			else if( feature.problems.length > 0 )
				state = 'blocked';

			return {
				key				: feature.key,
				name			: feature.name,
				maturity	: typeof feature.maturity === 'string' ? feature.maturity : '',
				category	: feature.category ? Nino.admin.features._categoryLabel( feature.category ) : '',
				version		: Nino.admin.features._versionText( feature.version, feature.installed, '' ),
				state			: Nino.admin.features._stateWord( state ),
				stateKind	: state,
				description	: feature.description,
				notes			: notes,
				feature		: feature,
			};
		},

		/**
		 *	One directory whose manifest the kernel refused as a row: its name, the line that says it is
		 *	not loaded - or, for a feature that is switched on all the same, that it runs
		 *	unfinished - and the reason as the kernel worded it - text, never markup
		 *
		 *	@param		{Object}	entry				An entry of 'rejected' in features/list; one that is 'missing' has no directory at all and says to install the feature
		 *
		 *	@return		{Object}
		 */
		_rejectedRow : function( entry ) {

			return {
				key				: entry.dir,
				name			: entry.dir,
				maturity	: '',
				category	: '',
				version		: '',
				state			: Nino.admin.features._stateWord( entry.missing === true ? 'offer' : 'refused' ),
				stateKind	: 'refused',
				description	: '',
				notes			: entry.missing === true
					? [ { text : Nino.adminUi.format( Nino.content.getText('/_admin/features/label/missing'), entry.dir ), bad : true } ]
					: [ { text : Nino.content.getText( entry.active === true ? '/_admin/features/label/rejected-active' : '/_admin/features/label/rejected' ), bad : true }, { text : entry.reason, bad : true } ],
				entry			: entry,
			};
		},

		/**
		 *	One offer of the catalogue as a row of the Available tab
		 *
		 *	@param		{Object}	offer				A cached offer
		 *
		 *	@return		{Object}
		 */
		_offerRow : function( offer ) {

			const notes = [];

			if( offer.requires.length > 0 )
				notes.push( { text : Nino.adminUi.format( Nino.content.getText('/_admin/features/label/requires'), offer.requires.join( ', ' ) ), bad : false } );

			// What it asks of this kernel, for one no version of which fits -
			// the only thing that makes such a row worth showing at all
			if( offer.state === 'incompatible' ) {
				notes.push( { text : Nino.adminUi.format( Nino.content.getText('/_admin/features/label/nino'), offer.nino ), bad : false } );

				if( offer.ext.length > 0 )
					notes.push( { text : Nino.adminUi.format( Nino.content.getText('/_admin/features/label/extensions'), offer.ext.join( ', ' ) ), bad : false } );
			}

			const state = offer.state === 'upgrade' ? 'offer-update' : ( offer.state === 'incompatible' ? 'incompatible' : 'offer' );

			return {
				key				: offer.key,
				name			: offer.name,
				maturity	: typeof offer.maturity === 'string' ? offer.maturity : '',
				category	: offer.category ? Nino.admin.features._categoryLabel( offer.category ) : '',
				version		: Nino.admin.features._versionText( offer.version, offer.local, offer.released ),
				state			: Nino.admin.features._stateWord( state ),
				stateKind	: state,
				description	: offer.description,
				notes			: notes,
				disabled	: offer.state === 'incompatible',
				offer			: offer,
			};
		},

		/**
		 *	One application of the catalogue as a row of the Applications tab: its name, what it is for,
		 *	the features it switches on and which of them are still off, what it leaves alone - and how
		 *	far it is set up, a word and never a colour alone
		 *
		 *	@param		{Object}	application	An entry of the cached catalogue's applications
		 *
		 *	@return		{Object}
		 */
		_applicationRow : function( application ) {

			const names = function( state ) {
				return application.features.filter( function( feature ) { return feature.state === state } ).map( function( feature ) { return feature.name } ).join( ', ' );
			};
			const notes = [];

			notes.push( { text : Nino.adminUi.format( Nino.content.getText('/_admin/features/label/application-features'), application.features.map( function( feature ) { return feature.name } ).join( ', ' ) ), bad : false } );

			// Which are off, by name: what Set up is about to load and switch on
			// ...and which have an update waiting, which setting up applies: another thing than being off
			if( application.state === 'partial' && names( 'off' ) !== '' )
				notes.push( { text : Nino.adminUi.format( Nino.content.getText('/_admin/features/label/application-off'), names( 'off' ) ), bad : false } );
			if( names( 'update' ) !== '' )
				notes.push( { text : Nino.adminUi.format( Nino.content.getText('/_admin/features/label/application-update'), names( 'update' ) ), bad : false } );

			// Who provides the start page: this never changes it, and for an
			// application that brings one of its own the feature offers it itself
			if( application.start === 'own' )
				notes.push( { text : Nino.content.getText('/_admin/features/hint/application-start-own'), bad : false } );
			else if( application.start === 'starter' )
				notes.push( { text : Nino.content.getText('/_admin/features/hint/application-start-starter'), bad : false } );

			const labels = {
				applied	: Nino.content.getText('/_admin/features/state/applied'),
				partial	: Nino.content.getText('/_admin/features/state/partial'),
				none		: Nino.content.getText('/_admin/features/state/none'),
			};

			return {
				key				: application.key,
				name			: application.name,
				maturity	: '',
				category	: '',
				version		: '',
				state			: labels[ application.state ] || '',
				stateKind	: 'application-'+ application.state,
				description	: application.description,
				notes			: notes,
				application	: application,
			};
		},

		/**
		 *	The word a state is said in. A literal lookup for each, as the tabs have: a fill that only a
		 *	built key names is invisible to the static check every panel script is held to
		 *
		 *	@param		{string}	state
		 *
		 *	@return		{string}
		 */
		_stateWord : function( state ) {

			const words = {
				active				: Nino.content.getText('/_admin/features/state/active'),
				update				: Nino.content.getText('/_admin/features/state/update'),
				inactive			: Nino.content.getText('/_admin/features/state/inactive'),
				blocked				: Nino.content.getText('/_admin/features/state/blocked'),
				refused				: Nino.content.getText('/_admin/features/state/refused'),
				offer					: Nino.content.getText('/_admin/features/state/offer'),
				'offer-update'	: Nino.content.getText('/_admin/features/state/offer-update'),
				incompatible	: Nino.content.getText('/_admin/features/state/incompatible'),
			};

			return words[ state ] || '';
		},

		/**
		 *	The version of a row: the one it is, the one on disk where that differs - which is
		 *	what an update is - and the day it was released, where the catalogue named one
		 *
		 *	@param		{string}			version
		 *	@param		{string|null}	installed		The version on disk, null when there is none
		 *	@param		{string}			released		'' when nothing was released
		 *
		 *	@return		{string}
		 */
		_versionText : function( version, installed, released ) {

			return version
				+ ( installed !== null && installed !== undefined && installed !== version
					? ' – '+ Nino.adminUi.format( Nino.content.getText('/_admin/features/label/installed'), installed )
					: '' )
				+ ( released !== undefined && released !== '' ? ' – '+ Nino.adminUi.format( Nino.content.getText('/_admin/features/label/released'), released ) : '' );
		},

		/**
		 *	The state of a row as the badge of the workbench: the word, in the colour of what it means -
		 *	a feature that runs, one that waits for an update or cannot start, one that is refused
		 *
		 *	@param		{Object}	row
		 *
		 *	@return		{Element}
		 */
		_stateBadge : function( row ) {

			const kinds = { active : 'ok', 'application-applied' : 'ok', update : 'warn', blocked : 'warn', 'offer-update' : 'warn', 'application-partial' : 'warn', refused : 'error', incompatible : 'error', inactive : 'muted', offer : 'muted', 'application-none' : 'muted' };
			const badge = Nino.adminUi.el( 'span', 'nino-admin-badge', row.state );

			if( kinds[ row.stateKind ] !== undefined )
				badge.setAttribute( 'data-state', kinds[ row.stateKind ] );

			return badge;
		},

		/**
		 *	The rows of a tab as a table of the workbench: a search over its full width, the
		 *	columns name, category, version and state, each sorted by its head, and the small
		 *	buttons of a row at its end. A row of a feature opens its view, on a click and on
		 *	the link of its name
		 *
		 *	@param		{Array}				rows
		 *	@param		{boolean}			opens		Whether a row of a feature opens its view
		 *
		 *	@return		{Element}								<div>
		 */
		_renderTable : function( rows, opens ) {

			const mount = dc.createElement('div');
			const parts = Nino.admin.features._rowParts;

			rows.forEach( function( row ) {

				if( parts.has( row.key ) === true )
					return;

				const ctx = Nino.admin.features._context( row.key );

				ctx.buttons = Nino.admin.features._rowButtons( row, ctx );

				if( typeof Nino.admin.features._refusals[ row.key ] === 'string' ) {
					ctx.line.fail( Nino.admin.features._refusals[ row.key ] );
					delete Nino.admin.features._refusals[ row.key ];
				}
				parts.set( row.key, ctx );
			} );

			const acts = rows.some( function( row ) { return parts.get( row.key ).buttons.length > 0 } );
			const tab = Nino.admin.features._tab;

			Nino.adminUi.table( {
				mount		: mount,
				rows		: rows,
				rowKey	: 'key',
				search	: true,
				query		: Nino.admin.features._query,
				onQuery	: function( query ) {
					Nino.admin.features._query = query;
					Nino.admin.features._refreshCounts();
				},
				searchText : Nino.admin.features._searchText,
				columns	: [
					{ key : 'name', label : Nino.content.getText('/_admin/features/col/name'), render : function( value, row ) { return Nino.admin.features._nameCell( row, opens ) } },
					{ key : 'category', label : Nino.content.getText('/_admin/features/label/category') },
					{ key : 'version', label : Nino.content.getText('/_admin/features/col/version') },
					{ key : 'state', label : Nino.content.getText('/_admin/features/col/state'), wrap : true, render : function( value, row ) { return Nino.admin.features._stateBadge( row ) } },
				].filter( function( column ) { return tab !== 'applications' || [ 'name', 'state' ].indexOf( column.key ) !== -1 } ),
				labels	: {
					search	: Nino.content.getText('/_admin/features/label/search'),
					empty		: Nino.content.getText('/_admin/features/hint/nomatch'),
					noMatch	: Nino.content.getText('/_admin/features/hint/nomatch'),
				},
				actions	: acts === true ? function( row ) { return parts.get( row.key ).buttons } : undefined,
				onRowClick : opens === true ? function( row ) { if( row.feature !== undefined ) Nino.admin.features._open( row.feature.key ) } : undefined,
			} );

			return mount;
		},

		/**
		 *	The name of a row: the name - the link to the view of a feature, which is how the keyboard opens
		 *	a row - with its maturity as a badge, over the line that says what the feature is for, the
		 *	notes that have to be read whole - a requirement, a reason an activation is refused, what an
		 *	offer asks of this kernel - and the line its buttons report into
		 *
		 *	@param		{Object}	row					A row of _featureRow(), _rejectedRow(), _offerRow() or _applicationRow()
		 *	@param		{boolean}	opens				Whether a row of a feature opens its view
		 *
		 *	@return		{Element}
		 */
		_nameCell : function( row, opens ) {

			const copy = dc.createElement('div');
			copy.className = 'nino-admin-list-copy';

			if( row.disabled === true )
				copy.setAttribute( 'aria-disabled', 'true' );

			const title = dc.createElement('strong');

			if( opens === true && row.feature !== undefined ) {
				const link = Nino.adminUi.el( 'a', '', row.name );
				link.href = Nino.admin.router.href( 'features', [ row.key ] );
				link.addEventListener( 'click', function( ev ) {
					ev.preventDefault();
					Nino.admin.features._open( row.key );
				} );
				title.appendChild( link );
			}
			else
				title.textContent = row.name;

			if( row.maturity !== '' ) {
				title.appendChild( dc.createTextNode( ' ' ) );
				title.appendChild( Nino.adminUi.el( 'span', 'nino-admin-badge', row.maturity ) );
			}

			copy.appendChild( title );

			if( row.description )
				copy.appendChild( Nino.adminUi.el( 'small', '', row.description ) );

			row.notes.forEach( function( note ) { copy.appendChild( Nino.admin.features._note( note.text, note.bad ) ) } );

			const ctx = Nino.admin.features._rowParts.get( row.key );

			if( ctx !== undefined && ctx.buttons.length > 0 )
				copy.appendChild( ctx.line.element );

			return copy;
		},

		/**
		 *	A line under the name that has to be read whole - a requirement, a reason an activation is
		 *	refused, what an offer asks of this kernel. Wraps, unlike the line of what the feature is for
		 *
		 *	@param		{string}	text
		 *	@param		{boolean}	bad					Whether it is a refusal rather than a fact
		 *
		 *	@return		{Element}							<small>
		 */
		_note : function( text, bad ) {

			const note = dc.createElement('small');
			note.className = bad === true ? 'admin-features-note nino-admin-error' : 'admin-features-note';
			note.textContent = text;

			return note;
		},

		/**
		 *	What an action of a row or of a view works on: the line it reports into - what is going on and
		 *	what was refused, which stand where the person pressed - and the buttons it holds while it is
		 *	under way, so that a second click, or another button of the same row, starts nothing
		 *
		 *	@return		{Object}							{ line, buttons, busy }
		 */
		_context : function( key ) {
			return { key : key, line : Nino.adminUi.status( dc.createElement('p') ), buttons : [], busy : false };
		},

		/**
		 *	Start an action: say what is going on and hold every button of the row. Refused while another one
		 *	of the same row is under way
		 *
		 *	@param		{Object}	ctx
		 *	@param		{string}	text				What is going on
		 *
		 *	@return		{boolean}							Whether the action may go on
		 */
		_hold : function( ctx, text ) {

			if( ctx.busy === true )
				return false;

			ctx.busy = true;
			ctx.buttons.forEach( function( button ) { button.disabled = true } );
			ctx.line.idle( text );

			return true;
		},

		/**
		 *	An action that did not go through: its buttons are free again, and the refusal stands at the row,
		 *	in the kernel's words, with the focus back on the button that was pressed
		 *
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button			The button that was pressed
		 *	@param		{number}	status
		 *	@param		{*}				response
		 *	@param		{string}	fallbackKey	The sentence for an answer that says nothing
		 *
		 *	@return		void
		 */
		_refuse : function( ctx, button, status, response, fallbackKey ) {

			Nino.admin.features._free( ctx );
			ctx.line.error( status, response, fallbackKey );

			if( button !== null && button !== undefined )
				button.focus();

			// A refusal that says the state on screen is out of date - the feature is on, or is gone - is answered with the
			// list read again; the sentence is kept for the row that is drawn then, which would otherwise say what it said before
			const code = response !== null && typeof response === 'object' && typeof response.code === 'string' ? response.code : '';

			if( ctx.key === undefined || ( status !== 404 && [ 'features_remove_active', 'features_unknown' ].indexOf( code ) === -1 ) )
				return;

			const key = ctx.key;
			const sentence = Nino.adminUi.api.errorText( status, response, fallbackKey );

			Nino.admin.features._refusals[ key ] = sentence;
			Nino.admin.features.init( function() {
				if( Nino.admin.features._detailParts !== null && Nino.admin.features._openFeature === key )
					Nino.admin.features._detailParts.bar.line.fail( sentence );
			} );
		},

		/**
		 *	Let the buttons of a row go again and take the line off - the question was answered with No
		 *
		 *	@param		{Object}	ctx
		 *
		 *	@return		void
		 */
		_free : function( ctx ) {

			ctx.busy = false;
			ctx.buttons.forEach( function( button ) { button.disabled = false } );
		},

		/**
		 *	The first control of a row, where the focus goes when the row stood where another one was
		 *
		 *	@param		{Element|null}	row
		 *	@param		{boolean}				[button]	Only a button: the action of a row that was done, which the next row offers again, not the link that opens it
		 *
		 *	@return		{Element|null}
		 */
		_firstControl : function( row, button ) {
			return row === null || row === undefined ? null : ( Array.from( row.querySelectorAll( button === true ? 'button' : 'a, button' ) ).find( function( control ) { return control.disabled !== true } ) ?? null );
		},

		/**
		 *	What a row offers, at its end and small: Activate and Uninstall on an inactive feature -
		 *	Activate while nothing stands in the way, the dangerous one last -, Update and Deactivate on an active one, Install or Update on an
		 *	offer, the archive to unpack by hand where nothing can be written, Deactivate on a refused
		 *	manifest that runs, and Set up on an application that is not set up, the way on to what it
		 *	brought once it is
		 *
		 *	@param		{Object}	row
		 *	@param		{Object}	ctx					The row's context, whose line the buttons report into
		 *
		 *	@return		{Array}
		 */
		_rowButtons : function( row, ctx ) {

			const buttons = [];
			const make = function( text, onClick, kind ) {
				const button = Nino.adminUi.button( text, function() { onClick( button ) }, { kind : kind || 'secondary', small : true } );
				buttons.push( button );
			};

			if( row.feature !== undefined && row.feature.active === false ) {

				const feature = row.feature;

				if( feature.problems.length === 0 )
					make( Nino.content.getText('/_admin/features/label/activate'), function( button ) { Nino.admin.features._switch( feature, 'activate', ctx, button ) } );

				// The one destructive control in the list, and it is only ever on an
				// inactive row: an active feature's class is listed in
				// /nino/modules, so its directory is not something to delete from
				// under the autoloader. Switching it off first is one click and
				// says what it is doing
				make( Nino.content.getText('/_admin/features/label/remove'), function( button ) { Nino.admin.features._remove( feature, ctx, button, false ) }, 'danger' );
			}

			if( row.feature !== undefined && row.feature.active === true ) {

				const feature = row.feature;

				if( feature.update === true )
					make( Nino.adminUi.format( Nino.content.getText('/_admin/features/label/update'), feature.version ), function( button ) { Nino.admin.features._switch( feature, 'update', ctx, button ) } );

				make( Nino.content.getText('/_admin/features/label/deactivate'), function( button ) { Nino.admin.features._switch( feature, 'deactivate', ctx, button ) } );
			}

			// Deactivating is not taking something away: its button is the quiet one, the removal's the red
			if( row.entry !== undefined && row.entry.active === true ) {
				const entry = row.entry;
				make( Nino.content.getText('/_admin/features/label/deactivate'), function( button ) { Nino.admin.features._deactivateRefused( entry, ctx, button ) } );
			}

			if( row.offer !== undefined ) {

				const offer = row.offer;
				const wanted = offer.state === 'available' || offer.state === 'upgrade';

				if( wanted === true && Nino.admin.features._writable === true )
					make( offer.state === 'upgrade' ? Nino.adminUi.format( Nino.content.getText('/_admin/features/label/update'), offer.version ) : Nino.content.getText('/_admin/features/label/install'),
						function( button ) { Nino.admin.features._install( offer, ctx, button ) } );
				else if( wanted === true ) {
					const archive = Nino.adminUi.el( 'a', 'nino-admin-btn-secondary nino-admin-btn--small', Nino.content.getText('/_admin/features/label/archive') );
					archive.href = offer.archive;
					buttons.push( archive );
				}
			}

			if( row.application !== undefined ) {

				const application = row.application;
				const kept = Nino.admin.features._applicationMsg[ application.key ];

				if( kept !== undefined && kept.error === true )
					ctx.line.fail( kept.text );
				else if( kept !== undefined )
					ctx.line.idle( kept.text );

				if( application.state !== 'applied' && Nino.admin.features._writable === true )
					make( kept !== undefined && kept.error === true ? Nino.content.getText('/_admin/features/label/application-retry') : Nino.content.getText('/_admin/features/label/application-setup'),
						function( button ) { Nino.admin.features._setUp( application, ctx, button ) } );

				// The server answers a path of the site and nothing else; a script that links to a
				// string does not take that on trust: one slash first, never a second or a backslash
				if( application.state === 'applied' && typeof application.next === 'string' && /^\/(?![\/\\])/.test( application.next ) === true ) {

					const link = Nino.adminUi.el( 'a', 'nino-admin-btn-secondary nino-admin-btn--small', Nino.adminUi.format( Nino.content.getText('/_admin/features/label/application-next'), application.name ) );
					link.href = Nino.dir+ application.next;
					link.addEventListener( 'click', function( ev ) { Nino.admin.features._followLink( ev, link ) } );
					buttons.push( link );
				}
			}

			return buttons;
		},

		/**
		 *	Switch off a feature whose manifest is refused and which runs all the same.
		 *	Asked first, and the question says what does not happen: nothing
		 *	that a feature's switch-off reads from its manifest can be done for
		 *	one nobody can read - who requires it, where its shortcodes stand,
		 *	which sections of the privacy policy describe it. Ends in a reload
		 *	like every switch: the panel it brought leaves the rail
		 *
		 *	@param		{Object}	entry				An entry of 'rejected' in features/list
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button
		 *
		 *	@return		void
		 */
		_deactivateRefused : function( entry, ctx, button ) {

			Nino.adminUi.confirm( 'big', {
				title			: Nino.content.getText('/_admin/features/confirm/deactivate-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/features/confirm/deactivate-refused'), entry.dir ),
				yes				: Nino.content.getText('/_admin/features/confirm/deactivate-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: button,
				onChoose	: function( ok ) {

					if( ok !== true )
						return;

					Nino.admin.features._guard( function() {

						if( Nino.admin.features._hold( ctx, Nino.content.getText('/_admin/features/msg/deactivating') ) === false )
							return;

						Nino.admin.features._apiCall( 'deactivate-refused', { dir : entry.dir }, function( status, response ) {

							if( status !== 200 || response === null )
								return Nino.admin.features._refuse( ctx, button, status, response, '/_admin/features/error/deactivate' );

							ctx.line.idle( Nino.content.getText('/_admin/features/msg/reload') );
							Nino.admin.features._reload( Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/inactive'), entry.dir ) );
						} );
					} );
				},
			} );
		},

		/**
		 *	The Available tab: why there is nothing to show, in order - the
		 *	catalogue is off, it was never loaded, it lists nothing at all,
		 *	everything it lists is already current, or the category matched none
		 *	of it - else the readonly notice where it applies, then one row per
		 *	offer
		 *
		 *	@param		{Element}	panel
		 *
		 *	@return		void
		 */
		_fillAvailable : function( panel ) {

			if( Nino.admin.features._catalogueUrl === '' ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.adminUi.format( Nino.content.getText('/_admin/features/hint/catalogue-off'), Nino.admin.features._dir ) ) );
				return;
			}

			const cache = Nino.admin.features._cache;

			if( cache === null ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/available-unloaded'), Nino.admin.features._refreshButton('secondary') ) );
				return;
			}

			if( cache.offers.length === 0 ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/catalogue-empty'), Nino.admin.features._refreshButton('secondary') ) );
				return;
			}

			const wanted = Nino.admin.features._availableOffers();

			if( wanted.length === 0 ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/available-empty') ) );
				return;
			}

			const shown = wanted.filter( Nino.admin.features._matches );

			if( shown.length === 0 ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/nomatch') ) );
				return;
			}

			if( Nino.admin.features._writable === false )
				panel.appendChild( Nino.adminUi.notice( Nino.adminUi.format( Nino.content.getText('/_admin/features/hint/catalogue-readonly'), Nino.admin.features._dir ) ) );

			panel.appendChild( Nino.admin.features._renderTable( shown.map( Nino.admin.features._offerRow ), false ) );
		},

		/**
		 *	The Applications tab: why there is nothing to show, in order - the
		 *	catalogue is off, it was never loaded, it offers no application
		 *	for this kernel - else the readonly notice where it applies, then one row per
		 *	application
		 *
		 *	@param		{Element}	panel
		 *
		 *	@return		void
		 */
		_fillApplications : function( panel ) {

			if( Nino.admin.features._catalogueUrl === '' ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.adminUi.format( Nino.content.getText('/_admin/features/hint/catalogue-off'), Nino.admin.features._dir ) ) );
				return;
			}

			if( Nino.admin.features._cache === null ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/applications-unloaded'), Nino.admin.features._refreshButton('secondary') ) );
				return;
			}

			const all = Nino.admin.features._applications();

			if( all.length === 0 ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/applications-empty'), Nino.admin.features._refreshButton('secondary') ) );
				return;
			}

			if( Nino.admin.features._writable === false )
				panel.appendChild( Nino.adminUi.notice( Nino.adminUi.format( Nino.content.getText('/_admin/features/hint/catalogue-readonly'), Nino.admin.features._dir ) ) );

			panel.appendChild( Nino.admin.features._renderTable( all.map( Nino.admin.features._applicationRow ), false ) );
		},

		/**
		 *	Follow the link to an application's place. A place in the workbench
		 *	differs from this page in the fragment alone, which is no navigation:
		 *	where this page switched features on, the workbench it holds was built
		 *	before and has no panel for them, so the page is loaded anew
		 *
		 *	@param		{Event}		ev
		 *	@param		{Element}	link
		 *
		 *	@return		void
		 */
		_followLink : function( ev, link ) {

			if( Nino.admin.features._switchedOn !== true )
				return;

			const target = new URL( link.href, wn.location.href );

			if( target.pathname+ target.search !== wn.location.pathname+ wn.location.search || target.hash === '' )
				return;

			ev.preventDefault();
			wn.location.href = target.href;
			wn.location.reload();
		},

		/**
		 *	Set an application up. Asks first what the unsaved input of the page
		 *	is worth (the reload this ends in builds every form again), then the
		 *	server what it comes to - what loading needs, the catalogue believed,
		 *	which features are off - and then the person, in the words of the
		 *	workbench's modes; only then does the first feature load
		 *
		 *	@param		{Object}	application
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button
		 *	@param		{boolean}	[guarded]		The unsaved input of the page was asked about already
		 *
		 *	@return		void
		 */
		_setUp : function( application, ctx, button, guarded ) {

			if( guarded !== true ) {
				Nino.admin.features._guard( function() { Nino.admin.features._setUp( application, ctx, button, true ) } );
				return;
			}

			if( Nino.admin.features._hold( ctx, Nino.content.getText('/_admin/features/msg/application-checking') ) === false )
				return;

			delete Nino.admin.features._applicationMsg[ application.key ];

			Nino.admin.features._apiCall( 'application-plan', { application : application.key }, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.admin.features._refuse( ctx, button, status, response, '/_admin/features/error/application-plan' );

				const todo = response.todo || [];
				const named = function( state ) {
					return response.application.features.filter( function( feature ) { return todo.indexOf( feature.key ) !== -1 && feature.state === state } ).map( function( feature ) { return feature.name } ).join( ', ' );
				};
				const loads = named( 'off' );
				const updates = named( 'update' );
				// What it comes to, in a sentence for each kind: loaded and switched on, and updated
				const comes = [
					loads !== '' ? Nino.adminUi.format( Nino.content.getText('/_admin/features/confirm/application-loads'), loads ) : '',
					updates !== '' ? Nino.adminUi.format( Nino.content.getText('/_admin/features/confirm/application-updates'), updates ) : '',
				].filter( Boolean ).join( ' ' );

				// Nothing left to load: the list was a step behind the project
				if( todo.length === 0 ) {
					Nino.admin.features._free( ctx );
					ctx.line.idle( Nino.content.getText('/_admin/features/msg/application-nothing') );
					Nino.admin.features.init();
					return;
				}

				Nino.adminUi.confirm( 'big', {
					title			: Nino.content.getText('/_admin/features/confirm/application-title'),
					message		: Nino.adminUi.format( Nino.content.getText('/_admin/features/confirm/application'), application.name, comes ),
					yes				: Nino.content.getText('/_admin/features/confirm/application-title'),
					no				: Nino.content.getText('/_admin/common/label/cancel'),
					opener		: button,
					onChoose	: function( ok ) {

						// The button was held while the plan was read, and a button that is
						// held cannot take the focus back from the question that closes
						if( ok === false ) {
							Nino.admin.features._free( ctx );
							ctx.line.idle( '' );
							button.focus();
							return;
						}

						Nino.admin.features._runApplication( response.application, todo, 0, ctx, button );
					},
				} );
			} );
		},

		/**
		 *	Load and switch on the features still off, one request each, and say
		 *	which is under way. The first that fails stops the run: it is named
		 *	with what is on already, and the button goes on from there. When the
		 *	last is on, the toast says it and the workbench is built
		 *	again, as every switch does, on the tab the set-up was made on
		 *
		 *	@param		{Object}	application	As the plan answered it
		 *	@param		{Array}		todo				Feature keys, in the order they are applied
		 *	@param		{number}	index				Which of them is next
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button
		 *
		 *	@return		void
		 */
		_runApplication : function( application, todo, index, ctx, button ) {

			const nameOf = function( key ) {
				const feature = application.features.filter( function( f ) { return f.key === key } )[0];
				return feature === undefined ? key : feature.name;
			};

			if( index >= todo.length ) {

				ctx.line.idle( Nino.content.getText('/_admin/features/msg/reload') );
				Nino.admin.features._reload( Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/application-done'), application.name, todo.map( nameOf ).join( ', ' ) ) );
				return;
			}

			ctx.line.idle( Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/application-step'), application.name, nameOf( todo[ index ] ), index + 1, todo.length ) );

			Nino.admin.features._apiCall( 'application-apply', { application : application.key, feature : todo[ index ] }, function( status, response ) {

				if( status !== 200 || response === null ) {

					const reason = Nino.adminUi.api.errorText( status, response, '/_admin/features/error/application-apply' );

					// What is on now, from the project and not from the count: a
					// feature that was placed and not switched on is not among them
					Nino.admin.features._applicationStopped( application, nameOf( todo[ index ] ), reason, ctx );
					return;
				}

				Nino.admin.features._switchedOn = true;
				Nino.admin.features._runApplication( application, todo, index + 1, ctx, button );
			} );
		},

		/**
		 *	A set-up that stopped: read the list again, so the rows and the
		 *	state of the features are the project's, and write at the row of
		 *	the application which feature failed, why, and which of its features
		 *	are on
		 *
		 *	@param		{Object}	application
		 *	@param		{string}	failed			The name of the feature that stopped it
		 *	@param		{string}	reason			The sentence of the failure
		 *	@param		{Object}	ctx
		 *
		 *	@return		void
		 */
		_applicationStopped : function( application, failed, reason, ctx ) {

			Nino.admin.features._apiCall( 'list', {}, function( status, response ) {

				let on = [];

				if( status === 200 && response !== null ) {

					Nino.admin.features._take( response );

					const now = Nino.admin.features._applications().filter( function( a ) { return a.key === application.key } )[0];

					if( now !== undefined )
						on = now.features.filter( function( feature ) { return feature.on === true } ).map( function( feature ) { return feature.name } );
				}

				Nino.admin.features._applicationMsg[ application.key ] = {
					text	: Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/application-stopped'), application.name, failed, reason, on.length === 0 ? Nino.content.getText('/_admin/features/label/none') : on.join( ', ' ) ),
					error	: true,
				};

				Nino.admin.features._free( ctx );
				Nino.admin.features._renderPanel( Nino.admin.features._holdsInput() );

				// The row was drawn again, and the focus went with the button that
				// held it: it goes to the one that goes on from here
				Nino.admin.features._firstControl( Nino.admin.features._rowOf( application.key ), true )?.focus();
			} );
		},

		/**
		 *	The row of a feature, an offer or an application as the list is drawn now - the list is drawn
		 *	again with every change of the category and the tab, and the row that was pressed is gone
		 *	with the old drawing
		 *
		 *	@param		{string}		key
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( key ) {
			return Array.from( dc.getElementById('features-list').querySelectorAll('tr') ).find( function( tr ) { return tr.dataset.row === key } ) ?? null;
		},

		/**
		 *	Uninstall a feature that is off: its directory is deleted, after asking. What the feature
		 *	kept stays - its settings, its files, the templates its unit copied - which the question says
		 *	under "Kept", because it is the half of the answer nobody expects
		 *
		 *	@param		{Object}	feature
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button
		 *	@param		{boolean}	inView			Whether it is asked from the feature's own view, which the answer leaves for the list
		 *
		 *	@return		void
		 */
		_remove : function( feature, ctx, button, inView ) {

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/features/confirm/remove-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/features/confirm/remove'), feature.name ),
				details		: [
					{ title : Nino.content.getText('/_admin/features/confirm/removed'), lines : [ Nino.content.getText('/_admin/features/confirm/removed-directory') ] },
					// Only what there is: files under data/ where the manifest names some, the copies of an install unit where it brings one
					{ title : Nino.content.getText('/_admin/features/confirm/kept'), lines : [
						Nino.content.getText('/_admin/features/confirm/kept-settings'),
						Array.isArray( feature.data ) === true && feature.data.length > 0 ? Nino.content.getText('/_admin/features/confirm/kept-data') : '',
						feature.unit === true ? Nino.content.getText('/_admin/features/confirm/kept-unit') : '',
					].filter( function( line ) { return line !== '' } ) },
					// The features that require it stay and cannot be switched on until it is back
					...( Array.isArray( feature.dependents ) === true && feature.dependents.length > 0 ? [ { title : Nino.content.getText('/_admin/features/confirm/dependents'), lines : feature.dependents } ] : [] ),
				],
				yes				: Nino.content.getText('/_admin/features/confirm/remove-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: button,
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					if( Nino.admin.features._hold( ctx, Nino.content.getText('/_admin/features/msg/removing') ) === false )
						return;

					// The rows around it, to say where the focus goes when its row is gone
					const rows = Array.from( dc.getElementById('features-list').querySelectorAll('tr') ).map( function( tr ) { return tr.dataset.row } );
					const at = rows.indexOf( feature.key );

					Nino.admin.features._apiCall( 'remove', { key : feature.key }, function( status, response ) {

						if( status !== 200 || response === null )
							return Nino.admin.features._refuse( ctx, button, status, response, '/_admin/features/error/remove' );

						// What the privacy policy still says about it, at the top of the list, which the list drawn
						// again keeps: removing the feature deleted none of it
						Nino.admin.features._notices = Nino.admin.features._privacyLines( response.privacy );
						Nino.adminUi.say( Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/removed'), feature.name ) );

						if( inView === true ) {
							Nino.admin.features._openFeature = '';
							Nino.admin.router.go( 'features', Nino.admin.features._tabParts() );
						}

						// The whole list again rather than the row taken off screen:
						// a removed feature may have been what another one required,
						// and that row now says something different
						Nino.admin.features.init( function() {

							// The row that took its place, else the one before it, else the empty state
							const after = rows.slice( at + 1 ).concat( rows.slice( 0, Math.max( at, 0 ) ).reverse() ).filter( function( key ) { return key !== undefined && key !== feature.key } );
							const target = after.map( function( key ) { return Nino.admin.features._firstControl( Nino.admin.features._rowOf( key ), true ) } ).find( function( el ) { return el !== null } );
							const empty = dc.getElementById('features-list').querySelector('.nino-admin-empty');

							if( target !== undefined )
								target.focus();
							else if( empty !== null ) {
								empty.setAttribute( 'tabindex', '-1' );
								empty.focus();
							}
						} );
					} );
				},
			} );
		},

		/**
		 *	Run proceed() - after the shell has asked about unsaved input anywhere
		 *	in the workbench, where the shell has the registry. A shell without it
		 *	(an older one, a test) goes straight on
		 *
		 *	@param		{Function}	proceed
		 *
		 *	@return		void
		 */
		_guard : function( proceed ) {

			if( typeof Nino.admin.dirty !== 'object' ) {
				proceed();
				return;
			}

			Nino.admin.dirty.guard( null, proceed );
		},

		/**
		 *	"Social media links is active." - what an activation says: the feature asked for, and the
		 *	requirements that were switched on with it
		 *
		 *	@param		{string}	name				The feature that was switched on
		 *	@param		{Array}		switchedOn	[ { key, name } ] as the panel answered, the feature itself among them
		 *	@param		{string}	key
		 *
		 *	@return		{string}
		 */
		_activatedText : function( name, switchedOn, key ) {

			const others = switchedOn.filter( function( entry ) { return entry.key !== key } ).map( function( entry ) { return entry.name } );

			return others.length === 0
				? Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/active'), name )
				: Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/active-with'), name, others.join( ', ' ) );
		},

		/**
		 *	What a deactivation leaves behind: one line a shortcode, the places
		 *	that still contain it - a template, a text, an element. The
		 *	brackets are written here, because a fill may not carry one
		 *
		 *	@param		{Array}		found				[ { shortcode, total, places: [ { kind, where } ] } ]
		 *
		 *	@return		{string}							'' where nothing stands
		 */
		_foundText : function( found ) {

			if( found.length === 0 )
				return '';

			const lines = found.map( function( entry ) {

				const places = entry.places.map( function( place ) {

					let text;

					if( place.kind === 'template' )
						text = Nino.content.getText('/_admin/features/label/found-template');
					else if( place.kind === 'text' )
						text = Nino.content.getText('/_admin/features/label/found-text');
					else
						text = Nino.content.getText('/_admin/features/label/found-element');

					return Nino.adminUi.format( text, place.where );
				} );

				if( entry.total > entry.places.length )
					places.push( Nino.adminUi.format( Nino.content.getText('/_admin/features/label/found-more'), entry.total - entry.places.length ) );

				return '['+ entry.shortcode+ '] '+ places.join( ', ' );
			} );

			return Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/deactivated-found'), '\n'+ lines.join( '\n' ) );
		},

		/**
		 *	The templates that call the components of a feature, for the question before it is switched
		 *	off: a page of such a template draws nothing there while the feature is off
		 *
		 *	@param		{Object}	feature			An entry of the list
		 *
		 *	@return		{Array}								The lines of the question's details, [] where no template calls one
		 */
		_calledLines : function( feature ) {

			if( feature.calledIn.total === 0 )
				return [];

			const names = feature.calledIn.templates.slice();

			if( feature.calledIn.total > names.length )
				names.push( Nino.adminUi.format( Nino.content.getText('/_admin/features/label/found-more'), feature.calledIn.total - names.length ) );

			return [ { title : Nino.content.getText('/_admin/features/confirm/deactivate-templates'), lines : names } ];
		},

		/**
		 *	What the privacy policy still says about a feature that is off or
		 *	gone: the titles of its sections, which no switch deletes
		 *
		 *	@param		{Array}		titles			The titles of the sections still to be seen
		 *
		 *	@return		{Array}								The line of the notice, [] where there is none
		 */
		_privacyLines : function( titles ) {

			if( Array.isArray( titles ) === false || titles.length === 0 )
				return [];

			return [ Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/deactivated-privacy'), titles.map( String ).join( ', ' ) ) ];
		},

		/**
		 *	The inactive feature the focus goes to once this one has left the
		 *	Inactive tab: the next row under it that offers Activate, else the
		 *	nearest one above it. The rows are the ones the tab shows now, the
		 *	category included. Nothing from another tab
		 *
		 *	@param		{string}	key					The feature that is being switched on
		 *
		 *	@return		{string}							Its key, '' where there is none
		 */
		_nextToActivate : function( key ) {

			if( Nino.admin.features._tab !== 'inactive' || Nino.admin.features._openFeature !== '' )
				return '';

			const rows = Nino.admin.features._features.filter( function( f ) { return f.active === false && Nino.admin.features._matches( f ) } );
			const at = rows.map( function( f ) { return f.key } ).indexOf( key );
			const can = function( f ) { return f.key !== key && f.problems.length === 0 };
			const after = rows.slice( at + 1 ).filter( can );
			const before = rows.slice( 0, Math.max( at, 0 ) ).filter( can );

			if( after.length > 0 )
				return after[0].key;

			return before.length > 0 ? before[ before.length - 1 ].key : '';
		},

		/**
		 *	Switch a feature on or off, or apply its update - the kernel's
		 *	one step for an update is activating again, so the two post the
		 *	same action. Asked first, as a switch is: the question of the
		 *	workbench's modes (level 'big'), and then the one about unsaved
		 *	input, since the reload this ends in builds every form again. A
		 *	refusal does nothing but say so at the place it was pressed
		 *
		 *	@param		{Object}	feature
		 *	@param		{string}	what				'activate', 'update' or 'deactivate'
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button
		 *
		 *	@return		void
		 */
		_switch : function( feature, what, ctx, button ) {

			const required = feature.requires.map( function( key ) { return Nino.admin.features._features.filter( function( f ) { return f.key === key && f.active === false } )[0] } )
				.filter( function( f ) { return f !== undefined } ).map( function( f ) { return f.name } );

			const proceed = function() {
				Nino.admin.features._guard( function() { Nino.admin.features._run( feature, what, ctx, button ) } );
			};

			if( what === 'deactivate' )
				// Switching off is asked about, as removing is: it leaves the shortcodes
				// on the pages, the sections of the privacy policy and what the feature
				// installed, which the notice afterwards names, too late to decide against it
				Nino.adminUi.confirm( 'big', {
					title			: Nino.content.getText('/_admin/features/confirm/deactivate-title'),
					message		: Nino.adminUi.format( Nino.content.getText('/_admin/features/confirm/deactivate'), feature.name ),
					details		: Nino.admin.features._calledLines( feature ),
					yes				: Nino.content.getText('/_admin/features/confirm/deactivate-title'),
					no				: Nino.content.getText('/_admin/common/label/cancel'),
					opener		: button,
					onChoose	: function( ok ) {
						if( ok === true )
							proceed();
					},
				} );
			else if( what === 'update' )
				Nino.adminUi.confirm( 'big', {
					title			: Nino.content.getText('/_admin/features/confirm/update-title'),
					message		: Nino.adminUi.format( Nino.content.getText('/_admin/features/confirm/update'), feature.name, feature.version ),
					yes				: Nino.content.getText('/_admin/features/confirm/update-title'),
					no				: Nino.content.getText('/_admin/common/label/cancel'),
					opener		: button,
					onChoose	: function( ok ) {
						if( ok === true )
							proceed();
					},
				} );
			else
				Nino.adminUi.confirm( 'big', {
					title			: Nino.content.getText('/_admin/features/confirm/activate-title'),
					message		: Nino.adminUi.format( Nino.content.getText('/_admin/features/confirm/activate'), feature.name ),
					details		: required.length === 0 ? [] : [ { title : Nino.content.getText('/_admin/features/confirm/activate-requires'), lines : required } ],
					yes				: Nino.content.getText('/_admin/features/confirm/activate-title'),
					no				: Nino.content.getText('/_admin/common/label/cancel'),
					opener		: button,
					onChoose	: function( ok ) {
						if( ok === true )
							proceed();
					},
				} );
		},

		/**
		 *	The request of a switch, after the questions were answered. Ends in a reload: the rail is
		 *	rendered server-side
		 *
		 *	@param		{Object}	feature			{ key, name }
		 *	@param		{string}	what				'activate', 'update' or 'deactivate'
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button
		 *
		 *	@return		void
		 */
		_run : function( feature, what, ctx, button ) {

			let busy, failKey;

			if( what === 'deactivate' ) {
				busy 		 = Nino.content.getText('/_admin/features/msg/deactivating');
				failKey	 = '/_admin/features/error/deactivate';
			}
			else if( what === 'update' ) {
				busy 		 = Nino.content.getText('/_admin/features/msg/updating');
				failKey	 = '/_admin/features/error/update';
			}
			else {
				busy 		 = Nino.content.getText('/_admin/features/msg/activating');
				failKey	 = '/_admin/features/error/activate';
			}

			if( Nino.admin.features._hold( ctx, busy ) === false )
				return;

			Nino.admin.features._apiCall( what === 'deactivate' ? 'deactivate' : 'activate', { key : feature.key }, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.admin.features._refuse( ctx, button, status, response, failKey );

				ctx.line.idle( Nino.content.getText('/_admin/features/msg/reload') );

				// Kept for the page that is built next: what it says is a toast and the places that still
				// hold the feature's shortcodes a notice, since the page that could show them is the one
				// being reloaded - see _reload()
				const inView = Nino.admin.features._openFeature === feature.key;

				if( what === 'deactivate' ) {
					Nino.admin.features._reload( Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/inactive'), feature.name ), {
						notices : [ Nino.admin.features._foundText( response.found || [] ) ].filter( function( line ) { return line !== '' } ).concat( Nino.admin.features._privacyLines( response.privacy ) ),
						parts		: inView === true ? [ feature.key ] : undefined,
					} );
					return;
				}

				// The panel a feature brings only appears - or goes - once the
				// shell is built again, on the level it was pressed on
				Nino.admin.features._reload(
					what === 'update' ? Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/updated'), feature.name ) : Nino.admin.features._activatedText( feature.name, response.switchedOn || [], feature.key ),
					{ focus : what === 'activate' ? Nino.admin.features._nextToActivate( feature.key ) : '', parts : inView === true ? [ feature.key ] : undefined }
				);
			} );
		},

		/**
		 *	Install or update one offer, say what it did, and then either read
		 *	the list again or build the whole workbench again - the offers are
		 *	recomputed against what is on disk now, so the catalogue itself is
		 *	not fetched a second time either way
		 *
		 *	@param		{Object}	offer
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button
		 *
		 *	@return		void
		 */
		_install : function( offer, ctx, button ) {

			Nino.adminUi.confirm( 'big', {
				title			: offer.state === 'upgrade' ? Nino.content.getText('/_admin/features/confirm/update-title') : Nino.content.getText('/_admin/features/confirm/install-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/features/confirm/install'), offer.name, offer.version ),
				details		: offer.requires.length === 0 ? [] : [ { title : Nino.content.getText('/_admin/features/confirm/activate-requires'), lines : offer.requires } ],
				yes				: offer.state === 'upgrade' ? Nino.content.getText('/_admin/features/confirm/update-title') : Nino.content.getText('/_admin/features/confirm/install-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: button,
				onChoose	: function( ok ) {

					if( ok !== true )
						return;

					// The list read or the reload that follows builds the open settings
					// view and every other form of the workbench again
					Nino.admin.features._guard( function() { Nino.admin.features._installRun( offer, ctx, button ) } );
				},
			} );
		},

		/**
		 *	The request of an install, after the questions were answered
		 *
		 *	@param		{Object}	offer
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button
		 *
		 *	@return		void
		 */
		_installRun : function( offer, ctx, button ) {

			if( Nino.admin.features._hold( ctx, Nino.content.getText('/_admin/features/msg/installing') ) === false )
				return;

			Nino.admin.features._apiCall( 'install', { key : offer.key, version : offer.version }, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.admin.features._refuse( ctx, button, status, response, '/_admin/features/error/install' );

				/*	A running feature: the files are in place, and the update is
					applied by activating again - in a request of its own, because
					this one booted with the previous version's class, and the hook
					the new version brought cannot run in it (see apiInstall()). That
					is the call Update in the Active tab makes, reload and all: the
					page holds the version before the update	*/
				if( response.pending === true ) {
					ctx.busy = false;
					Nino.admin.features._run( { key : offer.key, name : offer.name }, 'update', ctx, button );
					return;
				}

				/*	Installing a feature that was not in the directory switches it on -
					see apiInstall() for the one case it does not - and the word has to
					say so, or the next thing somebody does is look for the Activate
					that is no longer there. A feature that pulled its requirements in
					with it names them too: pressing Install on one thing and getting
					three is not something to find out afterwards	*/
				const word 		= response.activated === true ? 'installed-active' : 'installed';

				// What was placed where nothing was switched on; what was switched
				// on - by name, the requirement that was in the directory already
				// but off among them - where it was
				const required = response.activated === true
					? ( response.switchedOn || [] ).filter( function( entry ) { return entry.key !== offer.key } ).map( function( entry ) { return entry.name } )
					: ( response.required || [] );

				const said = required.length === 0
					? Nino.content.getText('/_admin/features/msg/'+ word )
					: Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/'+ word+ '-with'), required.join( ', ' ) );

				/*	Said as a toast rather than written onto the row it was pressed
					on, because that row is gone by the time anybody could read it: an
					offer that is installed is no longer an offer, and the list that
					follows leaves it out (see _availableOffers())	*/

				/*	Switched on by this install, and the page this was pressed in
					was built before that: the feature's panel, its assets and its
					words are only there once the shell is built again, on the tab
					this was pressed on, exactly as _run() does when the same
					thing happens from the Inactive tab	*/
				if( response.activated === true ) {
					ctx.line.idle( Nino.content.getText('/_admin/features/msg/reload') );
					Nino.admin.features._reload( said );
					return;
				}

				Nino.adminUi.say( said );
				Nino.admin.features.init();
			} );
		},

		/**
		 *	One line of facts under the name of a feature: how far along it is, the category, the version
		 *	and the one on disk where that differs, and what it requires
		 *
		 *	@param		{Object}	feature
		 *
		 *	@return		{string}
		 */
		_facts : function( feature ) {

			return [
				feature.maturity || '',
				feature.category ? Nino.admin.features._categoryLabel( feature.category ) : '',
				Nino.adminUi.format( Nino.content.getText('/_admin/features/label/version'), Nino.admin.features._versionText( feature.version, feature.installed, '' ) ),
				feature.requires.length > 0 ? Nino.adminUi.format( Nino.content.getText('/_admin/features/label/requires'), feature.requires.join( ', ' ) ) : '',
			].filter( Boolean ).join( ' · ' );
		},

		/**
		 *	The description tab of a feature's view: the sentence its manifest
		 *	describes itself with, and under it the manual - every kind of thing
		 *	a feature can add, answered in the same order by every feature.
		 *
		 *	null where the manifest carries neither, which is the one case a
		 *	view has no description pane and therefore no tab strip either
		 *
		 *	@param		{object}	feature			As the panel loaded it
		 *
		 *	@return		{?Element}						<div class="features-about">
		 */
		_renderAbout : function( feature ) {

			const about = dc.createElement('div');
			about.className = 'features-about';

			const description = typeof feature.description === 'string' ? feature.description.trim() : '';

			if( description !== '' ) {
				const lead = dc.createElement('p');
				lead.className = 'features-about-lead';
				lead.textContent = description;
				about.appendChild( lead );
			}

			const manual = Nino.admin.features._renderManual( feature );

			if( manual !== null )
				about.appendChild( manual );

			return about.children.length === 0 ? null : about;
		},

		/**
		 *	The manual: what a feature's manifest says about using it - either
		 *	as the sections every manifest answers, or, for a catalogue written
		 *	before those existed, as paragraphs with `backticked` spans as code.
		 *	null where the feature carries none. Drawn by the design system
		 *	(Nino.adminUi.manual()), which is also what the "?" of every panel
		 *	draws a manual with
		 *
		 *	@param		{object}	feature			As the panel loaded it
		 *
		 *	@return		{?Element}						<div class="nino-admin-manual">
		 */
		_renderManual : function( feature ) {

			const manual = feature.manual;

			if( manual === null || typeof manual !== 'object' )
				return Nino.adminUi.manual( typeof manual === 'string' ? manual : '' );

			/*	One shape for every feature, in one order, so the thing a
				developer does with this - look something up - is a glance rather
				than a read. Every section is drawn, the empty ones included: "no
				callbacks" is an answer, and a reader who does not find the
				question has to go and read the source to learn that the answer
				was nothing.

				What is *not* in here: the settings. They are the other tab, with
				their own labels and their own help, and the same words twice is
				the second place they drift from. The headings are the sixth
				level: the view's own order of them, which is not the dialog's	*/
			return Nino.adminUi.manual( ( feature.manualSections || [] ).map( function( name ) {
				return { heading : Nino.content.getText('/_admin/features/manual/'+ name), entries : manual[name] || [] };
			} ), { none : Nino.content.getText('/_admin/features/manual/none'), level : 6 } );
		},

		/**
		 *	Step into a feature's own view: the row, or its link, was pressed
		 *
		 *	@param		{string}	key
		 *
		 *	@return		void
		 */
		_open : function( key ) {

			Nino.admin.router.go( 'features', [ key ] );
			Nino.admin.features._showDetail( key );
		},

		/**
		 *	Show a feature's view and write it into the address
		 *
		 *	@param		{string}	key
		 *
		 *	@return		void
		 */
		_showDetail : function( key ) {

			Nino.admin.features._openFeature = key;
			// A different feature is a different view: the tab the last one was
			// left on says nothing about this one, so it opens on what it is
			Nino.admin.features._detailTab = 'about';
			Nino.admin.features._renderDetail( true );
		},

		/**
		 *	The second level, and which of the two is on screen: everything one feature has - what it is,
		 *	its settings where it declares any and has no panel of its own, an update waiting for it,
		 *	switching it on or off, its directory - while the address names a feature that is installed.
		 *	A key the project does not have, or that is off and cannot be seen, is an empty state with
		 *	the way back, where the person is not left on a list they did not ask for
		 *
		 *	@param		{boolean}		[opened]		The person opened the view: the focus goes into it (see Nino.adminUi.showLevel()). Else the level is only shown, and the focus stays where it is
		 *
		 *	@return		void
		 */
		_renderDetail : function( opened ) {

			const list	= dc.getElementById('features-list');
			const wrap	= dc.getElementById('features-detail');
			const key		= Nino.admin.features._openFeature;
			const feature	= Nino.admin.features._features.filter( function( f ) { return f.key === key } )[0];

			wrap.innerHTML = '';
			Nino.admin.features._detailParts = null;

			// The category narrows the list: it has nothing to say while a feature's own view is on screen
			const category = dc.getElementById('features-category');

			if( category !== null )
				category.closest('label').hidden = key !== '';

			if( key === '' ) {
				Nino.adminUi.showLevel( list, { focus : false } );
				return;
			}

			Nino.admin.features._address();

			const back = function() {
				Nino.admin.router.go( 'features', Nino.admin.features._tabAddress( Nino.admin.features._tab ) );
				Nino.admin.features._showList( true );
			};

			// No such feature: the sentence, and the way back in the context bar, which is the one way back there is
			if( feature === undefined ) {
				wrap.appendChild( Nino.adminUi.contextBar( { back : { to : list, onClick : back } } ) );
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/unknown') ) );
				Nino.adminUi.showLevel( wrap, opened === true ? {} : { focus : false } );
				return;
			}

			wrap.appendChild( Nino.adminUi.contextBar( { back : { to : list, onClick : back }, title : feature.name, code : feature.key } ) );

			// One form, whether or not it has anything to fill in: the bar at the
			// bottom belongs to it, and that bar is where Save, Update and
			// Deactivate all are
			const form = dc.createElement('form');
			form.noValidate = true;
			form.dataset.feature = feature.key;

			form.appendChild( Nino.adminUi.hint( Nino.admin.features._facts( feature ) ) );

			// What keeps it from being switched on, and what it runs without - a component of its folders the kernel did not
			// register, and why - in the kernel's words: nothing that keeps it from running, so no problem of the ones that keep it
			// from being switched on
			feature.problems.forEach( function( problem ) { form.appendChild( Nino.adminUi.notice( problem ) ) } );
			feature.components.forEach( function( reason ) { form.appendChild( Nino.adminUi.notice( Nino.adminUi.format( Nino.content.getText('/_admin/features/label/component-refused'), reason ) ) ) } );

			/*	Where the feature has a panel of its own the settings are edited
				there, in a tab of that panel (see settings.js): this view then
				draws neither the form nor a Save for it - one form of a feature's
				settings on screen at a time - and says where they went	*/
			if( feature.active === true && feature.settings.length > 0 && feature.settingsTab ) {
				const where = Nino.adminUi.hint( '' );
				const link = dc.createElement('a');
				link.href = '#'+ feature.settingsTab;
				link.textContent = Nino.content.getText('/_admin/features/hint/settings-tab');
				where.appendChild( link );
				form.appendChild( where );
			}

			const about		= Nino.admin.features._renderAbout( feature );
			const settings	= feature.active === true && feature.settings.length > 0 && !feature.settingsTab ? Nino.admin.features._renderSettings( feature, about === null ) : null;
			const items		= [];

			/*	Both panes built and one of them hidden, rather than one pane built
				per tab: a setting typed into, and then left to go and read what
				it does, would come back empty. Hidden is also still inside the
				form, so the one Save below collects the whole schema whichever
				tab is on - see _collect()	*/
			if( about !== null )
				items.push( { key : 'about', label : Nino.content.getText('/_admin/features/tab/about'), panel : about } );

			if( settings !== null )
				items.push( { key : 'settings', label : Nino.content.getText('/_admin/features/tab/settings'), panel : settings } );

			// A view with one pane is chrome around nothing: a feature with no settings, or none that
			// describes itself, gets the one thing it has and no strip over it
			let tabs = null;

			if( items.length > 1 ) {
				tabs = Nino.adminUi.tabs( items, { active : Nino.admin.features._detailTab, onSelect : function( tab ) { Nino.admin.features._detailTab = tab } } );
				form.appendChild( tabs.el );
			}

			items.forEach( function( item ) {
				item.panel.classList.add('features-detail-pane');
				form.appendChild( item.panel );
			} );

			const ctx = Nino.admin.features._context( feature.key );
			const bar = Nino.admin.features._detailBar( feature, ctx, settings !== null );

			ctx.line = bar.line;
			form.appendChild( bar.el );
			bar.line.bind( form );

			if( settings !== null )
				form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.features._saveDetail() } );

			wrap.appendChild( form );

			Nino.adminUi.showLevel( wrap, opened === true ? {} : { focus : false } );

			// What the settings hold now is what is saved
			Nino.admin.features._detailParts = { feature : feature, form : form, bar : bar, tabs : tabs, ctx : ctx };

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'features' );
		},

		/**
		 *	The bar of a feature's view, the one of every form: Uninstall at the left where the feature is off
		 *	and has a directory of the project's, the state and its line, the side actions - Apply content
		 *	again, Update, Deactivate - and ONE main button at the right edge: Save where there are settings
		 *	to save, else the one thing the view is for - the update that waits, switching it on or off
		 *
		 *	@param		{Object}	feature
		 *	@param		{Object}	ctx
		 *	@param		{boolean}	hasSettings	Whether the view has a form of settings, which Save belongs to
		 *
		 *	@return		{Object}								actionBar()'s answer
		 */
		_detailBar : function( feature, ctx, hasSettings ) {

			// A button the action holds while it is under way; the main one has no kind, the bar gives it its own
			const make = function( text, onClick, kind ) {
				const button = Nino.adminUi.button( text, function() { onClick( button ) }, { kind : kind } );
				ctx.buttons.push( button );
				return button;
			};
			const secondary = [];
			let danger = null;
			let primary;

			if( feature.active === false ) {

				danger = make( Nino.content.getText('/_admin/features/label/remove'), function( button ) { Nino.admin.features._remove( feature, ctx, button, true ) }, 'danger' );

				// The one thing the view is for. Where something stands in the way - the notices above say what - it is held back
				// for good, and so no action of the view lets it go
				if( feature.problems.length > 0 ) {
					primary = Nino.adminUi.button( Nino.content.getText('/_admin/features/label/activate'), null, { disabled : true } );
					primary.title = feature.problems.join( ' ' );
				}
				else
					primary = make( Nino.content.getText('/_admin/features/label/activate'), function( button ) { Nino.admin.features._switch( feature, 'activate', ctx, button ) } );
			}
			else {

				const waiting = feature.update === true;
				// What the view is for: Save where there are settings to save, else the update that waits, else switching it off
				const main = hasSettings === true ? 'save' : ( waiting === true ? 'update' : 'off' );

				if( Nino.admin.features._canReapply( feature ) === true )
					secondary.push( make( Nino.content.getText('/_admin/features/label/reapply'), function( button ) { Nino.admin.features._reapply( feature, ctx, button ) }, 'secondary' ) );

				const off = make( Nino.content.getText('/_admin/features/label/deactivate'), function( button ) { Nino.admin.features._switch( feature, 'deactivate', ctx, button ) }, main === 'off' ? undefined : 'secondary' );
				const update = waiting === false ? null : make( Nino.adminUi.format( Nino.content.getText('/_admin/features/label/update'), feature.version ), function( button ) { Nino.admin.features._switch( feature, 'update', ctx, button ) }, main === 'update' ? undefined : 'secondary' );

				if( main !== 'off' )
					secondary.push( off );

				if( update !== null && main !== 'update' )
					secondary.push( update );

				primary = main === 'save' ? Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit' } ) : ( main === 'update' ? update : off );
			}

			return Nino.adminUi.actionBar( { danger : danger, status : { line : Nino.adminUi.el('p'), fresh : hasSettings === false }, secondary : secondary, primary : primary } );
		},

		/**
		 *	Whether the view of a feature offers to apply its unit: it brings
		 *	one, no update waits for it, nothing keeps it from running, and what
		 *	it requires is on, up to date and able to run - the cases the kernel
		 *	refuses (see Admin::apiReapply()), which the button would only
		 *	answer with a refusal. An activation would switch a requirement on
		 *	and apply a waiting update, which is not what this is for
		 *
		 *	@param		{Object}	feature			An entry of features/list
		 *
		 *	@return		{boolean}
		 */
		_canReapply : function( feature ) {

			if( feature.unit !== true || feature.update === true || feature.problems.length > 0 )
				return false;

			return feature.requires.every( function( key ) {
				const required = Nino.admin.features._features.filter( function( f ) { return f.key === key } )[0];
				return required !== undefined && required.active === true && required.update === false && required.problems.length === 0;
			} );
		},

		/**
		 *	Apply the install unit of an active feature again and say what that
		 *	did: for each kind of thing what was placed and what the project had
		 *	already. Nothing is replaced, and nothing is reloaded - a unit adds
		 *	no panel to the rail
		 *
		 *	@param		{Object}	feature
		 *	@param		{Object}	ctx
		 *	@param		{Element}	button
		 *
		 *	@return		void
		 */
		_reapply : function( feature, ctx, button ) {

			Nino.adminUi.confirm( 'big', {
				title			: Nino.content.getText('/_admin/features/label/reapply'),
				message		: Nino.content.getText('/_admin/features/confirm/reapply'),
				yes				: Nino.content.getText('/_admin/features/label/reapply'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: button,
				onChoose	: function( ok ) {

					if( ok !== true || Nino.admin.features._hold( ctx, Nino.content.getText('/_admin/features/msg/reapplying') ) === false )
						return;

					Nino.admin.features._apiCall( 'reapply', { key : feature.key }, function( status, response ) {

						if( status !== 200 || response === null )
							return Nino.admin.features._refuse( ctx, button, status, response, '/_admin/features/error/reapply' );

						Nino.admin.features._free( ctx );
						ctx.line.idle( '' );
						Nino.adminUi.say( Nino.admin.features._appliedText( response.applied || {} ) );
						button.focus();
					} );
				},
			} );
		},

		/**
		 *	"... Placed: templates: 1, texts: 4. Already there: routes: 2." - what an
		 *	application of the unit did, by the kinds that have anything to
		 *	say on each side, in the words of the interface language
		 *
		 *	@param		{Object}	applied			kind => { added, present }, as the panel answered it
		 *
		 *	@return		{string}
		 */
		_appliedText : function( applied ) {

			// Literal lookups, as the tabs have: a fill that only a built key
			// names is invisible to the static check every panel script is held to
			const kinds = {
				routes		: Nino.content.getText('/_admin/features/label/unit-routes'),
				files			: Nino.content.getText('/_admin/features/label/unit-files'),
				templates	: Nino.content.getText('/_admin/features/label/unit-templates'),
				types			: Nino.content.getText('/_admin/features/label/unit-types'),
				slots			: Nino.content.getText('/_admin/features/label/unit-slots'),
				elements	: Nino.content.getText('/_admin/features/label/unit-elements'),
				texts			: Nino.content.getText('/_admin/features/label/unit-texts'),
			};

			const side = function( which ) {

				const parts = Object.keys( kinds ).filter( function( kind ) {
					return typeof applied[kind] === 'object' && applied[kind] !== null && applied[kind][which] > 0;
				} ).map( function( kind ) { return kinds[kind]+ ': '+ applied[kind][which] } );

				return parts.length === 0 ? Nino.content.getText('/_admin/features/label/unit-nothing') : parts.join( ', ' );
			};

			return Nino.adminUi.format( Nino.content.getText('/_admin/features/msg/reapplied'), side( 'added' ), side( 'present' ) );
		},

		/**
		 *	Leave a feature's view for the list it was opened from - the tab
		 *	it stands on is the one it was left on
		 *
		 *	@param		{boolean}		[back]			The person went Back: the focus goes to the row of the feature that was open. Else the list is only shown
		 *
		 *	@return		void
		 */
		_showList : function( back ) {

			const key = Nino.admin.features._openFeature;

			Nino.admin.features._openFeature = '';
			Nino.admin.features._address();
			Nino.admin.features._renderDetail();

			if( back === true )
				Nino.adminUi.backTo( 'features-list', Nino.admin.features._firstControl( Nino.admin.features._rowOf( key ) ) );
		},

		/**
		 *	The settings of one feature, every declared setting by its type -
		 *	the settings tab of the view's one form, whose Save sits in the
		 *	bar the workbench pins to the bottom
		 *
		 *	@param		{Object}	feature
		 *	@param		{boolean}	named				Whether the group names itself. It does
		 *															not under a tab - the tab is the name, and
		 *															the pane says so with aria-labelledby. It
		 *															does on a view with no tabs, which is a
		 *															feature that describes itself nowhere
		 *
		 *	@return		{Element}							<fieldset>
		 */
		_renderSettings : function( feature, named ) {

			const fieldset = dc.createElement('fieldset');

			if( named === true ) {
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_admin/features/label/settings');
				fieldset.appendChild( legend );
			}

			feature.settings.forEach( function( field ) {
				fieldset.appendChild( Nino.admin.features._renderField( field ) );
			} );

			return fieldset;
		},

		/**
		 *	One setting, by the type its schema declares, drawn by the block of that kind - the control
		 *	carries the setting's name as data-key, which is how _collect() finds it again, and as
		 *	data-field, which is how a refusal of the server finds the field
		 *
		 *	@param		{Object}	field				{ name, type, label, hint, required, min, max, maxlength, unit, options, value | set }
		 *
		 *	@return		{Element}
		 */
		_renderField : function( field ) {

			let el;

			if( field.type === 'bool' )
				el = Nino.adminUi.switchField( {
					key 		: field.name,
					checked : field.value === true,
					label 	: Nino.adminUi.text( field.label ),
					help 		: Nino.adminUi.text( field.hint ),
				} );
			else if( field.type === 'int' )
				el = Nino.admin.features._renderNumber( field );
			else if( field.type === 'select' )
				el = Nino.admin.features._renderSelect( field );
			else if( field.type === 'text' || field.type === 'lines' )
				el = Nino.admin.features._renderTextarea( field );
			else if( field.type === 'secret' )
				el = Nino.admin.features._renderSecret( field );
			else
				el = Nino.admin.features._renderInput( field );

			const control = el.querySelector('[data-key]');

			if( control !== null )
				control.dataset.field = field.name;

			return el;
		},

		/**
		 *	An int as the design system's bounded number input. Its unit is
		 *	the manifest's own word: the component only knows the units the
		 *	workbench has a fill for, so any other is written into the label
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderNumber : function( field ) {

			const unit 	= field.unit || '';
			const known = unit !== '' && Nino.content.getText( '/_admin/common/unit/'+ unit ) !== '';

			return Nino.adminUi.numberField( {
				key 	: field.name,
				value : field.value,
				min 	: field.min === null ? undefined : field.min,
				max 	: field.max === null ? undefined : field.max,
				unit 	: known === true ? unit : undefined,
				label : known === true || unit === '' ? field.label : field.label+ ' ('+ unit+ ')',
				help 	: field.hint,
			} );
		},

		/**
		 *	A select with the manifest's options. A setting that holds
		 *	nothing yet - no default, or a stored value the schema no longer
		 *	accepts - gets an empty choice first, so the control shows the
		 *	state it is in rather than silently the first option
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderSelect : function( field ) {

			const options = field.options.map( function( option ) {
				return { value : option.value, label : Nino.adminUi.text( option.label ) };
			} );

			if( field.value === '' || field.value === null )
				options.unshift( { value : '', label : Nino.content.getText('/_admin/features/label/none') } );

			return Nino.adminUi.selectField( {
				key 		: field.name,
				label 	: Nino.adminUi.text( field.label ),
				help 		: Nino.adminUi.text( field.hint ),
				options : options,
				value 	: field.value === null ? '' : field.value,
			} );
		},

		/**
		 *	A string, an email or a url as an input of that type
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderInput : function( field ) {

			return Nino.adminUi.textField( {
				key				: field.name,
				type			: field.type === 'string' ? 'text' : field.type,
				value			: field.value === null || field.value === undefined ? '' : String( field.value ),
				label			: Nino.adminUi.text( field.label ),
				help			: Nino.adminUi.text( field.hint ),
				required	: field.required === true,
				maxLength	: field.maxlength || undefined,
				autocomplete : 'off',
			} );
		},

		/**
		 *	A secret as a password input that is always empty: the value
		 *	never reaches the browser, and posting the field empty keeps
		 *	what is stored (see Features::validateSettings()). The note says
		 *	whether there is one
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderSecret : function( field ) {

			return Nino.adminUi.textField( {
				key				: field.name,
				type			: 'password',
				value			: '',
				label			: Nino.adminUi.text( field.label ),
				help			: Nino.adminUi.text( field.hint ),
				note			: Nino.content.getText( field.set === true ? '/_admin/features/hint/secret-set' : '/_admin/features/hint/secret-unset' ),
				maxLength	: field.maxlength || undefined,
				autocomplete : 'new-password',
			} );
		},

		/**
		 *	A text as a textarea; a list as one too, one entry per line -
		 *	the backend splits and trims (see Features::validateSettings()),
		 *	so what is posted is simply the raw text
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderTextarea : function( field ) {

			const lines = field.type === 'lines';

			return Nino.adminUi.textareaField( {
				key				: field.name,
				rows			: 4,
				spellcheck : field.type === 'text',
				label			: Nino.adminUi.text( field.label ),
				help			: [ Nino.adminUi.text( field.hint ), lines === true ? Nino.content.getText('/_admin/features/hint/lines') : '' ].filter( function( text ) { return text !== '' } ).join(' '),
				placeholder : lines === true ? Nino.content.getText('/_admin/features/example/lines') : undefined,
				required	: field.required === true,
				maxLength	: field.maxlength || undefined,
				value			: lines === true
					? ( field.value || [] ).join( '\n' )
					: ( field.value === null || field.value === undefined ? '' : String( field.value ) ),
			} );
		},

		/**
		 *	Every control of one form by the setting name it carries, so
		 *	collecting them needs no second list to keep in step with the
		 *	schema
		 *
		 *	@param		{Element}	form
		 *
		 *	@return		{Object}							name => value as the control holds it
		 */
		_collect : function( form ) {

			const fields = {};

			Array.prototype.slice.call( form.querySelectorAll('[data-key]') ).forEach( function( el ) {

				if( el.type === 'checkbox' )
					fields[el.dataset.key] = el.checked;
				else if( el.type === 'number' )
					fields[el.dataset.key] = el.value.trim();
				else
					fields[el.dataset.key] = el.value;
			} );

			return fields;
		},

		/**
		 *	Check the settings before they are sent: what is missing, and a number that is no whole number
		 *	or outside the bounds of its setting, is said under the field and once in the line of the bar,
		 *	and the first takes the focus. The form is novalidate, so the browser's own bubble never speaks.
		 *	What only the server can judge - an address that is no address - comes back as its refusal
		 *
		 *	@param		{Element}	form
		 *	@param		{Object}	feature			The entry the form was drawn from
		 *	@param		{Object}	line				The status line of the form's bar
		 *
		 *	@return		{?Element}						The first control that is wrong, null where every setting is fine
		 */
		_check : function( form, feature, line ) {

			Nino.adminUi.clearFieldErrors( form );

			let first = null;

			feature.settings.forEach( function( field ) {

				const input = Array.prototype.filter.call( form.querySelectorAll('[data-key]'), function( el ) { return el.dataset.key === field.name } )[0];

				if( input === undefined || field.type === 'bool' || field.type === 'secret' || field.type === 'select' )
					return;

				const name = Nino.adminUi.text( field.label );
				const value = String( input.value ).trim();
				let sentence = '';

				if( value === '' && field.required === true )
					sentence = Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), name );
				else if( field.type === 'int' && value !== '' ) {

					const number = Number( value );
					const low = field.min !== null && field.min !== undefined;
					const high = field.max !== null && field.max !== undefined;

					if( /^-?[0-9]+$/.test( value ) === false || ( low === true && number < field.min ) || ( high === true && number > field.max ) ) {
						const expected = low === true && high === true
							? Nino.adminUi.format( Nino.content.getText('/_admin/features/expect/range'), field.min, field.max )
							: ( low === true ? Nino.adminUi.format( Nino.content.getText('/_admin/features/expect/min'), field.min ) : ( high === true ? Nino.adminUi.format( Nino.content.getText('/_admin/features/expect/max'), field.max ) : Nino.content.getText('/_admin/features/expect/whole') ) );
						sentence = Nino.adminUi.format( Nino.content.getText('/_admin/features/error/invalid'), name, expected );
					}
				}

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( input.closest('label') ?? input.parentNode, input, 'features-error-'+ field.name.replace( /[^a-z0-9]+/gi, '-' ), sentence );

				if( first === null )
					first = input;
			} );

			if( first !== null )
				line.fail( Nino.content.getText('/_admin/features/error/fields') );

			return first;
		},

		/**
		 *	Save one feature's settings in one request: checked first, and the answer - the entry as it
		 *	is stored now - handed to saved()
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Object}		feature
		 *	@param		{Element}		form
		 *	@param		{Object}		line				The status line of the form's bar
		 *	@param		{Function}	saved				Called with the answer of the server once it was written; false when the answer is not what was asked for
		 *	@param		{Function}	[done]			Called once with true when the settings were written, false otherwise
		 *	@param		{Function}	[wrong]			Called with the first control that is wrong, to bring it into view
		 *
		 *	@return		void
		 */
		_save : function( feature, form, line, saved, done, wrong ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			// A second press - a double click, Enter twice - while the request is on its way sends nothing
			if( SAVING.has( form ) === true ) {
				report( false );
				return;
			}

			const first = Nino.admin.features._check( form, feature, line );

			if( first !== null ) {

				if( typeof wrong === 'function' )
					wrong( first );

				first.focus();
				report( false );
				return;
			}

			const main = form.querySelector('.nino-admin-btn-primary');
			const sent = Nino.admin.features._collect( form );

			SAVING.add( form );

			if( main !== null )
				main.disabled = true;

			line.saving();

			Nino.admin.features._apiCall( 'settings', { key : feature.key, fields : sent }, function( status, response ) {

				SAVING.delete( form );

				if( main !== null )
					main.disabled = false;

				if( status !== 200 || response === null ) {
					line.error( status, response, '/_admin/common/error/save' );
					Nino.admin.features._fieldSentence( form, feature, response );
					report( false );
					return;
				}

				// What was typed while the request was on its way is not in what was sent: it stays in its field, and the form is not saved
				const typed = JSON.stringify( Nino.admin.features._collect( form ) ) !== JSON.stringify( sent );

				report( saved( response, typed ) !== false );
			} );
		},

		/**
		 *	What the server found wrong with the settings, said under each field in the interface language and as the guide words it:
		 *	"<Name> is missing." where nothing was given, "<Name> is not valid: <what is expected>." otherwise. The server says it as a code
		 *	and the limits that belong in the sentence for every setting that was refused (problems)
		 *
		 *	@param		{Element}		form
		 *	@param		{Object}		feature
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_fieldSentence : function( form, feature, response ) {

			if( response === null || typeof response !== 'object' || response.problems === null || typeof response.problems !== 'object' )
				return;

			Object.keys( response.problems ).forEach( function( name ) {

				const problem = response.problems[name];
				const field = feature.settings.filter( function( f ) { return f.name === name } )[0];
				const input = Array.prototype.filter.call( form.querySelectorAll('[data-key]'), function( el ) { return el.dataset.key === name } )[0];

				if( field === undefined || input === undefined || problem === null || typeof problem !== 'object' || typeof problem.code !== 'string' )
					return;

				const label = Nino.adminUi.text( field.label );
				const words = Nino.content.getText('/_admin/features/expect/'+ problem.code );
				const sentence = problem.code === 'required'
					? Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), label )
					: Nino.adminUi.format( Nino.content.getText('/_admin/features/error/invalid'), label, Nino.adminUi.format( words, ...( Array.isArray( problem.params ) === true ? problem.params : [] ) ) );

				Nino.adminUi.fieldError( input.closest('label') ?? input.parentNode, input, 'features-error-'+ name.replace( /[^a-z0-9]+/gi, '-' ), sentence );
			} );
		},

		/**
		 *	Put the focus back where it was in a form that was drawn again: on the control of that setting, else on its main button
		 *
		 *	@param		{Element}		form
		 *	@param		{string}		key					The data-key of the control that had the focus, '' for none
		 *
		 *	@return		void
		 */
		_refocus : function( form, key ) {

			const control = key === '' ? undefined : Array.prototype.filter.call( form.querySelectorAll('[data-key]'), function( el ) { return el.dataset.key === key } )[0];
			const target = control ?? form.querySelector('.nino-admin-btn-primary');

			if( target !== null && target !== undefined )
				target.focus();
		},

		/**
		 *	The data-key of the control that has the focus now, '' where it is none of a form's settings
		 *
		 *	@return		{string}
		 */
		_focusKey : function() {

			const el = dc.activeElement;

			return el !== null && el !== undefined && el.dataset !== undefined && typeof el.dataset.key === 'string' ? el.dataset.key : '';
		},

		/**
		 *	Save the settings of the open view, and read the list again so the values come back as
		 *	config.php now holds them and the secret's note says it is set; the new bar says so
		 *
		 *	@param		{Function}	[done]
		 *
		 *	@return		void
		 */
		_saveDetail : function( done ) {

			const parts = Nino.admin.features._detailParts;
			const key = Nino.admin.features._focusKey();

			Nino.admin.features._save( parts.feature, parts.form, parts.bar.line, function( response, typed ) {

				// The entry carries the values as config.php holds them now, for the next drawing
				if( typeof response.feature === 'object' && response.feature !== null )
					Nino.admin.features._features = Nino.admin.features._features.map( function( f ) { return f.key === response.feature.key ? response.feature : f } );

				// Something was typed meanwhile: the form stays as it is, with what was typed, and says it has something to save
				if( typed === true ) {
					parts.bar.line.dirty();
					return true;
				}

				// Drawn from what config.php holds, so the note of a secret says it is set; the focus goes where it was
				Nino.admin.features.init( function() {
					const now = Nino.admin.features._detailParts;

					if( now === null )
						return;

					now.bar.line.saved();
					Nino.admin.features._refocus( now.form, key );
				} );
			}, done, function( first ) {

				// A control the other tab is covering cannot take the focus: its tab comes forward first
				if( parts.tabs !== null && first.closest('fieldset') !== null && first.closest('fieldset').hidden === true )
					parts.tabs.select('settings');
			} );
		},
	};

	// The shell asks before anything throws the open feature's settings away
	// (see Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'features', function() { return dc.getElementById('features-detail') }, function( done ) {
			Nino.admin.features._saveDetail( done );
		} );

	Nino.events.bindCallback( 'ready', Nino.admin.features.init );

})(window, document, document.documentElement, document.body);
