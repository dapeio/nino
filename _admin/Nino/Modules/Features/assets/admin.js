/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Features" module: every feature installed under
 *													features/, sorted into three tabs - Available (what
 *													the catalogue offers that is not already current, so
 *													an install or an update), Inactive and Active - a
 *													shared action bar above them holding the one Refresh
 *													catalogue button. An active feature's settings are a
 *													screen of their own, behind its Settings button and
 *													left again through the back link. See Admin/Admin.php
 *													beside it: the entries arrive with their words already
 *													in the interface language and the settings schema
 *													normalized by \Nino\Features, so this file only knows
 *													how to sort a feature into its tab, draw each setting
 *													type and collect it back.
 *
 *													Activating and deactivating end in a reload: the rail
 *													is rendered server-side, so the panel a feature brings
 *													- or takes away - is only there once the page is built
 *													again. The hash stays on this panel, so the workbench
 *													comes back where it was.
 *
 *													features/list answers the catalogue exactly as
 *													\Nino\Catalogue::cached() last left it on disk, so the
 *													Available tab fills the moment the panel opens without
 *													a request of its own - the workbench still never
 *													contacts the catalogue on its own. Only Refresh
 *													catalogue posts features/catalogue, which re-fetches
 *													and answers the same shape; installing reads the list
 *													again rather than the catalogue, since the offers are
 *													always recomputed against the features on disk now.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.features = {

		_ready 			: false,
		// key => message: survives the re-render a successful save triggers - see _save()
		_pendingMsg : {},
		_dir 				: '',
		_features 	: [],
		// The catalogue url as features/list names it - '' when it is switched off,
		// which is when the Available tab offers no Refresh button at all
		_catalogueUrl : '',
		// Whether the features directory can be written - features/list and
		// features/catalogue both answer it fresh, live, every time
		_writable 	: true,
		// { url, fetched, offers } from features/list's own 'catalogue', or
		// from a features/catalogue answer once Refresh was pressed; null
		// before either ever ran
		_cache 			: null,
		// What the action bar's status line says while a refresh runs, or why
		// the last one failed - as state, since the bar is built fresh each time
		_catalogueMsg : { text : '', error : false, busy : false },
		// key => message: what an install answered, shown on its offer through
		// the two renders that follow it - see _install()
		_offerMsg 		: {},
		// Which tab is on screen - kept across a re-render so an action does
		// not jump the panel back to the first one. Active is where a panel
		// opens: what this installation is running is the answer to the
		// question that brought someone here
		_tab 				: 'active',
		// What the filter above the tabs holds - kept across a re-render too,
		// so switching tabs while searching keeps searching
		_filter 		: '',
		// The key of the feature whose own screen is open, '' while the list
		// is - as state, so the reload a save ends in comes back to the same
		// screen rather than dropping to the list
		_openFeature : '',

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

			Nino.admin.features._apiCall( 'list', {}, function( status, response ) {
				// Back to the list first: an error written into a pane a settings
				// screen is covering would not be read
				if( status !== 200 || response === null ) {
					Nino.admin.features._showList();
					return Nino.admin.features._showError( wrap, status, response );
				}

				Nino.admin.features._dir 					= response.dir || '';
				Nino.admin.features._catalogueUrl	= response.catalogueUrl || '';
				Nino.admin.features._writable			= response.writable === true;
				Nino.admin.features._cache 				= response.catalogue || null;
				Nino.admin.features._features			= response.features || [];
				Nino.admin.features._renderPanel();
				Nino.admin.features._ready = true;

				if( typeof then === 'function' )
					then();
			} );
		},

		showCurrent : function() {
			Nino.admin.features.init();
		},

		/**
		 *	Call a features/* action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "features/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'features/'+ endpoint, data : JSON.stringify( payload ) } );
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

		/**
		 *	The whole panel, from state: the head that holds the tab strip and
		 *	the filter, the action bar (only while the catalogue is switched
		 *	on) and the current tab's content
		 *
		 *	@return		void
		 */
		_renderPanel : function() {

			const wrap = dc.getElementById('features-list');
			wrap.innerHTML = '';

			wrap.appendChild( Nino.admin.features._renderHead() );

			if( Nino.admin.features._catalogueUrl !== '' )
				wrap.appendChild( Nino.admin.features._renderActionBar() );

			Nino.admin.features._renderTabContent( wrap );
			Nino.admin.features._renderDetail();
		},

		/**
		 *	How many features/offers each tab holds, for its label - of what
		 *	the filter lets through, so a search says which tab the match is
		 *	on rather than how much is being hidden
		 *
		 *	@return		{Object}							{ active, inactive, available }
		 */
		_counts : function() {
			const matches = Nino.admin.features._matches;
			return {
				active		: Nino.admin.features._features.filter( function( f ) { return f.active === true && matches( f ) } ).length,
				inactive	: Nino.admin.features._features.filter( function( f ) { return f.active === false && matches( f ) } ).length,
				available	: Nino.admin.features._availableOffers().filter( matches ).length,
			};
		},

		/**
		 *	Whether one feature or offer is what the filter is looking for -
		 *	its name, its key or its description, case ignored. An empty
		 *	filter matches everything, which is the state the panel opens in
		 *
		 *	@param		{Object}	entry				A feature of features/list or a cached offer
		 *
		 *	@return		{boolean}
		 */
		_matches : function( entry ) {

			const query = Nino.admin.features._filter.trim().toLowerCase();

			return query === ''
				|| [ entry.name, entry.key, entry.description ].join( ' ' ).toLowerCase().indexOf( query ) !== -1;
		},

		/**
		 *	The cached offers that belong on the Available tab: whatever is
		 *	not already current - not on disk at all, or on disk in an older
		 *	version, or one no version of which fits this kernel (shown
		 *	greyed, with what it asks for, same as before)
		 *
		 *	@return		{Array}
		 */
		_availableOffers : function() {
			const cache = Nino.admin.features._cache;
			return cache === null ? [] : cache.offers.filter( function( o ) { return o.state !== 'current' } );
		},

		/**
		 *	The head of the pane: the tab strip and, beside it, the filter
		 *	over everything the tabs hold. One block, because the two belong
		 *	together and because it is what stays at the top of a long list -
		 *	see assets/admin.css. The filter is outside the tablist: a
		 *	tablist holds tabs, and a search input in it would be read out as
		 *	one
		 *
		 *	@return		{Element}							<div class="admin-features-head">
		 */
		_renderHead : function() {

			const head = dc.createElement('div');
			head.className = 'admin-features-head';

			head.appendChild( Nino.admin.features._renderTabs() );

			const filter = dc.createElement('input');
			filter.type = 'search';
			filter.className = 'nino-admin-table-search';
			filter.value = Nino.admin.features._filter;
			filter.placeholder = Nino.content.getText('/_admin/features/label/filter');
			filter.setAttribute( 'aria-label', Nino.content.getText('/_admin/features/label/filter') );

			// The whole panel is drawn again per keystroke, so the counts on the
			// tabs follow along - and the focus put back where it was, since the
			// element that had it is gone by then
			filter.addEventListener( 'input', function() {
				Nino.admin.features._filter = filter.value;
				Nino.admin.features._renderPanel();
				const next = dc.getElementById('features-filter');
				if( next !== null ) {
					next.focus();
					if( typeof next.setSelectionRange === 'function' )
						next.setSelectionRange( next.value.length, next.value.length );
				}
			} );

			filter.id = 'features-filter';
			head.appendChild( filter );

			return head;
		},

		/**
		 *	The tab strip: three tabs, each labelled with its count, wired
		 *	through the shared button row so the active one is underlined and
		 *	a click switches the panel below without reloading anything.
		 *	Active first: what this installation runs is what a panel opens on
		 *
		 *	@return		{Element}							<div role="tablist">
		 */
		_renderTabs : function() {

			const bar = dc.createElement('div');
			bar.className = 'nino-admin-tabs nino-admin-tabs--bar admin-panel-tabs';
			bar.setAttribute( 'role', 'tablist' );

			const counts	= Nino.admin.features._counts();

			// Three literal lookups rather than one built from a key: a fill
			// that only a concatenated argument ever names is invisible to
			// the static check every panel script is held to (see the
			// module's Dev docblock and tests/admin-features-js-smoke.js)
			const labels = {
				available	: Nino.content.getText('/_admin/features/tab/available'),
				inactive	: Nino.content.getText('/_admin/features/tab/inactive'),
				active		: Nino.content.getText('/_admin/features/tab/active'),
			};
			const buttons	= {};

			[ 'active', 'inactive', 'available' ].forEach( function( key ) {
				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.setAttribute( 'role', 'tab' );
				btn.className = 'nino-admin-tab';
				btn.textContent = labels[key]+ ' ('+ counts[key]+ ')';
				bar.appendChild( btn );
				buttons[key] = btn;
			} );

			Nino.adminUi.buttonRow( buttons, Nino.admin.features._tab, function( key ) {
				Nino.admin.features._tab = key;
				Nino.admin.features._renderPanel();
			}, 'aria-selected' );

			return bar;
		},

		/**
		 *	The action bar above the tabs: Refresh catalogue - absent while
		 *	the catalogue is switched off, the caller already checked that -
		 *	and a status line saying when the cache is from, or that it is
		 *	not loaded yet, or what the last refresh answered
		 *
		 *	@return		{Element}							nino-admin-actionbar nino-admin-list-actions
		 */
		_renderActionBar : function() {

			const refresh = dc.createElement('button');
			refresh.type = 'button';
			refresh.className = 'nino-admin-btn-secondary';
			refresh.disabled = Nino.admin.features._catalogueMsg.busy === true;
			refresh.textContent = Nino.content.getText('/_admin/features/label/catalogue-refresh');
			refresh.addEventListener( 'click', function() { Nino.admin.features._refreshCatalogue() } );

			const status = dc.createElement('p');
			status.className = 'nino-admin-actionbar-status';
			status.setAttribute( 'aria-live', 'polite' );
			status.textContent = Nino.admin.features._statusText();
			if( Nino.admin.features._catalogueMsg.error === true )
				status.classList.add('nino-admin-error');

			return Nino.adminUi.listActions( [ refresh, status ] );
		},

		/**
		 *	What the action bar's status line reads: a message from the last
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
				? Nino.content.getText('/_admin/features/label/catalogue-status').replace( '%s', cache.fetched )
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

			Nino.admin.features._catalogueMsg = { text : Nino.content.getText('/_admin/features/msg/catalogue-loading'), error : false, busy : true };
			Nino.admin.features._renderPanel();

			Nino.admin.features._apiCall( 'catalogue', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.admin.features._catalogueMsg = { text : '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/features/error/catalogue') ), error : true, busy : false };
					Nino.admin.features._renderPanel();
					return;
				}

				Nino.admin.features._cache 				= { url : response.url, fetched : response.fetched, offers : response.offers };
				Nino.admin.features._writable			= response.writable === true;
				Nino.admin.features._catalogueMsg	= { text : '', error : false, busy : false };
				Nino.admin.features._renderPanel();
			} );
		},

		/**
		 *	The current tab's content, into its own tabpanel: one card per
		 *	feature or offer, or the empty state that says why there is none
		 *
		 *	@param		{Element}	wrap
		 *
		 *	@return		void
		 */
		_renderTabContent : function( wrap ) {

			const panel = dc.createElement('div');
			panel.className = 'nino-admin-tabpanel';
			panel.setAttribute( 'role', 'tabpanel' );
			wrap.appendChild( panel );

			if( Nino.admin.features._tab === 'inactive' )
				return Nino.admin.features._fillInstalled( panel, false );
			if( Nino.admin.features._tab === 'active' )
				return Nino.admin.features._fillInstalled( panel, true );
			return Nino.admin.features._fillAvailable( panel );
		},

		/**
		 *	The Inactive or Active tab. An active feature is a row you step
		 *	into: everything there is to do with it - its settings, an update
		 *	waiting for it, switching it off - lives on its own screen, so the
		 *	list stays one line per feature however many there are. An inactive
		 *	one is not: it has exactly one thing to offer, and Activate is
		 *	better one click away than two
		 *
		 *	@param		{Element}	panel
		 *	@param		{boolean}	active
		 *
		 *	@return		void
		 */
		_fillInstalled : function( panel, active ) {

			const all	= Nino.admin.features._features.filter( function( f ) { return f.active === active } );
			const list	= all.filter( Nino.admin.features._matches );

			// The tab is empty, or the filter emptied it - two different things to
			// be told, and the second one is not a reason to explain what a
			// feature directory is
			if( all.length === 0 ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText( active === true ? '/_admin/features/hint/active-empty' : '/_admin/features/hint/empty' ).replace( '%s', Nino.admin.features._dir ) ) );
				return;
			}

			if( list.length === 0 ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/nomatch') ) );
				return;
			}

			if( active === true ) {
				const rows = dc.createElement('div');
				rows.className = 'nino-admin-list nino-admin-list-buttons';
				list.forEach( function( feature ) { rows.appendChild( Nino.admin.features._renderActiveRow( feature ) ) } );
				panel.appendChild( rows );
				return;
			}

			const rows = dc.createElement('ul');
			rows.className = 'nino-admin-list';
			list.forEach( function( feature ) { rows.appendChild( Nino.admin.features._renderFeature( feature ) ) } );
			panel.appendChild( rows );
		},

		/**
		 *	The Available tab: why there is nothing to show, in order - the
		 *	catalogue is off, it was never loaded, it lists nothing at all,
		 *	everything it lists is already current, or the filter matched none
		 *	of it - else the readonly notice where it applies, then one row per
		 *	offer
		 *
		 *	@param		{Element}	panel
		 *
		 *	@return		void
		 */
		_fillAvailable : function( panel ) {

			if( Nino.admin.features._catalogueUrl === '' ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/catalogue-off').replace( '%s', Nino.admin.features._dir ) ) );
				return;
			}

			const cache = Nino.admin.features._cache;

			if( cache === null ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/available-unloaded') ) );
				return;
			}

			if( cache.offers.length === 0 ) {
				panel.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/catalogue-empty') ) );
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

			if( Nino.admin.features._writable === false ) {
				const readonly = dc.createElement('p');
				readonly.className = 'nino-admin-error';
				readonly.textContent = Nino.content.getText('/_admin/features/hint/catalogue-readonly').replace( '%s', Nino.admin.features._dir );
				panel.appendChild( readonly );
			}

			const rows = dc.createElement('ul');
			rows.className = 'nino-admin-list';
			shown.forEach( function( offer ) { rows.appendChild( Nino.admin.features._renderOffer( offer, Nino.admin.features._writable === true ) ) } );
			panel.appendChild( rows );
		},

		/**
		 *	One active feature as a row that is stepped into - the shared
		 *	drill-down row the Elements, Images and Text panels use: the name,
		 *	the line under it, and the chevron that says there is more behind
		 *	it. It carries no buttons of its own, which is the point: Update,
		 *	Deactivate and the settings are all on the screen it opens
		 *
		 *	@param		{Object}	feature			An entry of features/list
		 *
		 *	@return		{Element}							<button>
		 */
		_renderActiveRow : function( feature ) {

			const row = dc.createElement('button');
			row.type = 'button';
			row.className = 'admin-type-btn';
			row.dataset.feature = feature.key;

			const copy = dc.createElement('div');
			copy.textContent = feature.name;

			const meta = dc.createElement('div');
			meta.className = 'admin-type-btn-descr';
			meta.textContent = Nino.admin.features._meta( feature.version, feature.installed, '', feature.description );
			copy.appendChild( meta );

			const chev = dc.createElement('span');
			chev.className = 'admin-view-button-chev';
			chev.setAttribute( 'aria-hidden', 'true' );
			chev.textContent = '\u203a';

			row.appendChild( copy );
			row.appendChild( chev );
			row.addEventListener( 'click', function() { Nino.admin.features._showDetail( feature ) } );

			return row;
		},

		/**
		 *	One inactive feature as a row of the grouped list: its name, the
		 *	one line that says which version this is and what it does, whatever
		 *	stands in the way of switching it on, and Activate where nothing
		 *	does. No status badge: which tab it is in already says that
		 *
		 *	@param		{Object}	feature			An entry of features/list
		 *
		 *	@return		{Element}							<li>
		 */
		_renderFeature : function( feature ) {

			const row = dc.createElement('li');
			row.dataset.feature = feature.key;

			const copy = Nino.admin.features._copy( feature.name, Nino.admin.features._meta( feature.version, feature.installed, '', feature.description ) );

			if( feature.requires.length > 0 )
				copy.appendChild( Nino.admin.features._note( Nino.content.getText('/_admin/features/label/requires').replace( '%s', feature.requires.join( ', ' ) ), false ) );

			// What stands in the way of switching it on, one line each - the
			// kernel's own words, which is where the check lives. These wrap
			// rather than ellipsize: a reason cut off in the middle is no reason
			feature.problems.forEach( function( problem ) {
				copy.appendChild( Nino.admin.features._note( problem, true ) );
			} );

			row.appendChild( copy );
			row.appendChild( Nino.admin.features._renderActions( feature ) );

			return row;
		},

		/**
		 *	The one line under a name: which version this is, the one on disk
		 *	where that differs - which is what an update is - when the
		 *	catalogue named a release date, and what the thing is for. Joined
		 *	rather than stacked: a row is scanned, and the filter above is what
		 *	finds a description nobody can read to the end of at this width
		 *
		 *	@param		{string}	version
		 *	@param		{string|null}	installed	The version on disk, null when there is none
		 *	@param		{string}	released		'' when nothing was released
		 *	@param		{string}	description
		 *
		 *	@return		{string}
		 */
		_meta : function( version, installed, released, description ) {

			return [
				Nino.content.getText('/_admin/features/label/version').replace( '%s', version )
					+ ( installed !== null && installed !== undefined && installed !== version
						? ' \u2013 '+ Nino.content.getText('/_admin/features/label/installed').replace( '%s', installed )
						: '' )
					+ ( released !== '' ? ' \u2013 '+ Nino.content.getText('/_admin/features/label/released').replace( '%s', released ) : '' ),
				description,
			].filter( Boolean ).join( ' \u00b7 ' );
		},

		/**
		 *	The left half of a row: the name, and under it the one line the
		 *	shared list component ellipsizes when it does not fit
		 *
		 *	@param		{string}	name
		 *	@param		{string}	meta
		 *
		 *	@return		{Element}							<div class="nino-admin-list-copy">
		 */
		_copy : function( name, meta ) {

			const copy = dc.createElement('div');
			copy.className = 'nino-admin-list-copy';

			const title = dc.createElement('strong');
			title.textContent = name;
			copy.appendChild( title );

			const line = dc.createElement('small');
			line.textContent = meta;
			copy.appendChild( line );

			return copy;
		},

		/**
		 *	A line under the meta line that has to be read whole - a
		 *	requirement, a reason an activation is refused, what an offer asks
		 *	of this kernel. Wraps, unlike the meta line
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
		 *	What an inactive feature offers: Activate, while nothing stands in
		 *	the way - and the line it reports into. An update is not offered
		 *	here: activating applies whatever version is on disk anyway, so a
		 *	second button for it would be the same button twice
		 *
		 *	@param		{Object}	feature
		 *
		 *	@return		{Element}							<div>
		 */
		_renderActions : function( feature ) {

			const actions = dc.createElement('div');
			actions.className = 'admin-features-actions';

			const msg = dc.createElement('p');
			msg.className = 'nino-admin-hint';
			msg.setAttribute( 'aria-live', 'polite' );

			if( feature.problems.length === 0 ) {
				const activate = dc.createElement('button');
				activate.type = 'button';
				activate.className = 'nino-admin-btn-primary';
				activate.textContent = Nino.content.getText('/_admin/features/label/activate');
				activate.addEventListener( 'click', function() { Nino.admin.features._switch( feature, 'activate', activate, msg ) } );
				actions.appendChild( activate );
			}

			actions.appendChild( msg );

			return actions;
		},

		/**
		 *	Switch a feature on or off, or apply its update - the kernel's
		 *	one step for an update is activating again, so the two post the
		 *	same action. Ends in a reload: the rail is rendered server-side
		 *
		 *	@param		{Object}	feature
		 *	@param		{string}	what				'activate', 'update' or 'deactivate'
		 *	@param		{Element}	btn
		 *	@param		{Element}	msg
		 *
		 *	@return		void
		 */
		_switch : function( feature, what, btn, msg ) {

			let busy, done, fallback;

			if( what === 'deactivate' ) {
				busy 		 = Nino.content.getText('/_admin/features/msg/deactivating');
				done 		 = Nino.content.getText('/_admin/features/msg/deactivated');
				fallback = Nino.content.getText('/_admin/features/error/deactivate');
			}
			else if( what === 'update' ) {
				busy 		 = Nino.content.getText('/_admin/features/msg/updating');
				done 		 = Nino.content.getText('/_admin/features/msg/updated');
				fallback = Nino.content.getText('/_admin/features/error/update');
			}
			else {
				busy 		 = Nino.content.getText('/_admin/features/msg/activating');
				done 		 = Nino.content.getText('/_admin/features/msg/activated');
				fallback = Nino.content.getText('/_admin/features/error/activate');
			}

			btn.disabled = true;
			msg.classList.remove('nino-admin-error');
			msg.textContent = busy;

			Nino.admin.features._apiCall( what === 'deactivate' ? 'deactivate' : 'activate', { key : feature.key }, function( status, response ) {

				if( status !== 200 || response === null ) {
					btn.disabled = false;
					msg.classList.add('nino-admin-error');
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : fallback );
					return;
				}

				msg.textContent = done+ ' '+ Nino.content.getText('/_admin/features/msg/reload');

				// The panel a feature brings only appears - or goes - once the
				// shell is built again; the hash keeps the workbench on this panel
				wn.location.hash = '#features';
				wn.location.reload();
			} );
		},

		/**
		 *	One offer as a row: name, the version the catalogue offers beside
		 *	the one on disk - which is what an update is - the release date it
		 *	names, and the button its state allows, or the archive link where
		 *	nothing can be placed. Greyed rather than left out where it is
		 *	incompatible: what it asks for is the useful part
		 *
		 *	@param		{Object}	offer				An entry of the cached catalogue
		 *	@param		{boolean}	writable		Whether the features directory can be written
		 *
		 *	@return		{Element}							<li>
		 */
		_renderOffer : function( offer, writable ) {

			const row = dc.createElement('li');
			row.dataset.offer = offer.key;

			if( offer.state === 'incompatible' )
				row.setAttribute( 'aria-disabled', 'true' );

			const copy = Nino.admin.features._copy( offer.name, Nino.admin.features._meta( offer.version, offer.local, offer.released, offer.description ) );

			if( offer.requires.length > 0 )
				copy.appendChild( Nino.admin.features._note( Nino.content.getText('/_admin/features/label/requires').replace( '%s', offer.requires.join( ', ' ) ), false ) );

			// What it asks of this kernel, for one no version of which fits -
			// the only thing that makes such a row worth showing at all
			if( offer.state === 'incompatible' ) {
				copy.appendChild( Nino.admin.features._note( Nino.content.getText('/_admin/features/label/nino').replace( '%s', offer.nino ), false ) );

				if( offer.ext.length > 0 )
					copy.appendChild( Nino.admin.features._note( Nino.content.getText('/_admin/features/label/extensions').replace( '%s', offer.ext.join( ', ' ) ), false ) );
			}

			row.appendChild( copy );
			row.appendChild( Nino.admin.features._renderOfferActions( offer, writable ) );

			return row;
		},

		/**
		 *	The button an offer's state allows: Install for one that is not on
		 *	disk, Update for one there in an older version, nothing for one
		 *	already current or one that does not fit - and, where nothing can
		 *	be written, the archive to unpack by hand instead
		 *
		 *	@param		{Object}	offer
		 *	@param		{boolean}	writable
		 *
		 *	@return		{Element}							<div>
		 */
		_renderOfferActions : function( offer, writable ) {

			const actions = dc.createElement('div');
			actions.className = 'admin-features-actions';

			const msg = dc.createElement('p');
			msg.className = 'nino-admin-hint';
			msg.setAttribute( 'aria-live', 'polite' );

			const wanted = offer.state === 'available' || offer.state === 'upgrade';

			if( wanted === true && writable === true ) {
				const install = dc.createElement('button');
				install.type = 'button';
				install.className = 'nino-admin-btn-primary';
				install.textContent = offer.state === 'upgrade'
					? Nino.content.getText('/_admin/features/label/update').replace( '%s', offer.version )
					: Nino.content.getText('/_admin/features/label/install');
				install.addEventListener( 'click', function() { Nino.admin.features._install( offer, install, msg ) } );
				actions.appendChild( install );
			}
			else if( wanted === true ) {
				const archive = dc.createElement('a');
				archive.href = offer.archive;
				archive.textContent = Nino.content.getText('/_admin/features/label/archive');
				actions.appendChild( archive );
			}

			if( Nino.admin.features._offerMsg[offer.key] )
				msg.textContent = Nino.admin.features._offerMsg[offer.key];

			actions.appendChild( msg );

			return actions;
		},

		/**
		 *	Install or update one offer, then read the list again - the offers
		 *	are recomputed against what is on disk now, so the catalogue itself
		 *	is not fetched a second time
		 *
		 *	@param		{Object}	offer
				*	@param		{Element}	btn
		 *	@param		{Element}	msg
		 *
		 *	@return		void
		 */
		_install : function( offer, btn, msg ) {

			btn.disabled = true;
			msg.classList.remove('nino-admin-error');
			msg.textContent = Nino.content.getText('/_admin/features/msg/installing');

			Nino.admin.features._apiCall( 'install', { key : offer.key, version : offer.version }, function( status, response ) {

				if( status !== 200 || response === null ) {
					btn.disabled = false;
					msg.classList.add('nino-admin-error');
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/features/error/install') );
					return;
				}

				// Kept as state rather than written here: init() rebuilds this
				// row from scratch, and the word has to be there once it does
				Nino.admin.features._offerMsg[offer.key] = Nino.content.getText('/_admin/features/msg/installed');
				Nino.admin.features.init();
			} );
		},

		/**
		 *	The second level, and which of the two is on screen: everything
		 *	one active feature has - what it is, its settings where it declares
		 *	any, the update waiting for it where one is, and switching it off -
		 *	while the feature _openFeature names is still installed and still
		 *	on. Else the list, which is where a feature that was switched off
		 *	somewhere else ends up rather than on a screen about nothing
		 *
		 *	@return		void
		 */
		_renderDetail : function() {

			const list	= dc.getElementById('features-list');
			const wrap	= dc.getElementById('features-detail');
			const feature	= Nino.admin.features._features.filter( function( f ) {
				return f.key === Nino.admin.features._openFeature && f.active === true;
			} )[0];

			wrap.innerHTML = '';

			if( feature === undefined ) {
				Nino.admin.features._openFeature = '';
				list.classList.remove('admin-hidden');
				wrap.classList.add('admin-hidden');
				return;
			}

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.features._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			// One form, whether or not it has anything to fill in: the bar at the
			// bottom belongs to it, and that bar is where Save, Update and
			// Deactivate all are
			const form = dc.createElement('form');
			form.dataset.feature = feature.key;

			const title = dc.createElement('h3');
			title.textContent = feature.name;
			form.appendChild( title );

			const meta = dc.createElement('p');
			meta.className = 'nino-admin-hint';
			meta.textContent = Nino.admin.features._meta( feature.version, feature.installed, '', feature.description );
			form.appendChild( meta );

			if( feature.requires.length > 0 ) {
				const requires = dc.createElement('p');
				requires.className = 'nino-admin-hint';
				requires.textContent = Nino.content.getText('/_admin/features/label/requires').replace( '%s', feature.requires.join( ', ' ) );
				form.appendChild( requires );
			}

			const settings = feature.settings.length > 0;

			if( settings === true )
				form.appendChild( Nino.admin.features._renderSettings( feature ) );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			// The destructive one first in the markup; the shared bar orders it to
			// the far side, away from the confirming ones
			const off = dc.createElement('button');
			off.type = 'button';
			off.className = 'nino-admin-btn-danger';
			off.textContent = Nino.content.getText('/_admin/features/label/deactivate');
			actions.appendChild( off );

			if( feature.update === true ) {
				const update = dc.createElement('button');
				update.type = 'button';
				update.className = 'nino-admin-btn-primary';
				update.textContent = Nino.content.getText('/_admin/features/label/update').replace( '%s', feature.version );
				update.addEventListener( 'click', function() { Nino.admin.features._switch( feature, 'update', update, msg ) } );
				actions.appendChild( update );
			}

			let save = null;

			if( settings === true ) {
				save = dc.createElement('button');
				save.type = 'submit';
				save.textContent = Nino.content.getText('/_admin/common/label/save');
				actions.appendChild( save );
			}

			const msg = dc.createElement('p');
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			off.addEventListener( 'click', function() { Nino.admin.features._switch( feature, 'deactivate', off, msg ) } );

			form.appendChild( actions );

			if( settings === true )
				form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.features._save( feature, form, save, msg ) } );

			// Re-shown after the reload that follows a save, which builds this
			// element fresh and would otherwise wipe the confirmation the moment
			// it appeared
			if( Nino.admin.features._pendingMsg[feature.key] ) {
				msg.textContent = Nino.admin.features._pendingMsg[feature.key];
				delete Nino.admin.features._pendingMsg[feature.key];
			}

			wrap.appendChild( form );

			list.classList.add('admin-hidden');
			wrap.classList.remove('admin-hidden');
		},

		/**
		 *	Step into one active feature's own screen
		 *
		 *	@param		{Object}	feature
		 *
		 *	@return		void
		 */
		_showDetail : function( feature ) {
			Nino.admin.features._openFeature = feature.key;
			Nino.admin.features._renderDetail();
		},

		/**
		 *	Leave a feature's screen for the list it was opened from - the tab
		 *	it stands on is the one it was left on
		 *
		 *	@return		void
		 */
		_showList : function() {
			Nino.admin.features._openFeature = '';
			Nino.admin.features._renderDetail();
		},

		/**
		 *	The settings of one feature, every declared setting by its type -
		 *	the fieldset of the screen's one form, whose Save sits in the bar
		 *	the workbench pins to the bottom
		 *
		 *	@param		{Object}	feature
		 *
		 *	@return		{Element}							<fieldset>
		 */
		_renderSettings : function( feature ) {

			const fieldset = dc.createElement('fieldset');

			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/features/label/settings');
			fieldset.appendChild( legend );

			feature.settings.forEach( function( field ) {
				fieldset.appendChild( Nino.admin.features._renderField( field ) );
			} );

			return fieldset;
		},

		/**
		 *	One setting, by the type its schema declares - the control
		 *	carries the setting's name as data-key, which is how _collect()
		 *	finds it again
		 *
		 *	@param		{Object}	field				{ name, type, label, hint, required, min, max, maxlength, unit, options, value | set }
		 *
		 *	@return		{Element}
		 */
		_renderField : function( field ) {

			if( field.type === 'bool' )
				return Nino.adminUi.switchField( {
					key 		: field.name,
					checked : field.value === true,
					label 	: Nino.adminUi.text( field.label ),
					hint 		: Nino.adminUi.text( field.hint ),
				} );

			if( field.type === 'int' )
				return Nino.admin.features._renderNumber( field );

			if( field.type === 'select' )
				return Nino.admin.features._renderSelect( field );

			if( field.type === 'text' || field.type === 'lines' )
				return Nino.admin.features._renderTextarea( field );

			if( field.type === 'secret' )
				return Nino.admin.features._renderSecret( field );

			return Nino.admin.features._renderInput( field );
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
				hint 	: field.hint,
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
				hint 		: Nino.adminUi.text( field.hint ),
				options : options,
				value 	: field.value === null ? '' : field.value,
			} );
		},

		/**
		 *	A labelled field: the name, the control, the hint under it
		 *
		 *	@param		{Object}	field
		 *	@param		{Element}	control			The input, textarea or select
		 *	@param		{string}	extra				A second hint the type itself adds, '' for none
		 *	@param		{boolean}	wide				Whether it opts out of the two-column desktop grid
		 *
		 *	@return		{Element}							<label>
		 */
		_wrap : function( field, control, extra, wide ) {

			const label = dc.createElement('label');
			label.className = wide === true ? 'nino-admin-field nino-admin-field-wide' : 'nino-admin-field';

			const span = dc.createElement('span');
			span.textContent = Nino.adminUi.text( field.label );
			label.appendChild( span );

			control.dataset.key = field.name;
			label.appendChild( control );

			[ Nino.adminUi.text( field.hint ), extra ].forEach( function( text ) {
				if( text === '' )
					return;
				const hint = dc.createElement('small');
				hint.className = 'nino-admin-hint';
				hint.textContent = text;
				label.appendChild( hint );
			} );

			return label;
		},

		/**
		 *	A string, an email or a url as an input of that type - the
		 *	browser then checks the shape the backend checks too
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderInput : function( field ) {

			const input = dc.createElement('input');
			input.type = field.type === 'string' ? 'text' : field.type;
			input.value = field.value === null || field.value === undefined ? '' : String( field.value );
			input.required = field.required === true;
			input.autocomplete = 'off';
			if( field.maxlength )
				input.maxLength = field.maxlength;

			return Nino.admin.features._wrap( field, input, '', false );
		},

		/**
		 *	A secret as a password input that is always empty: the value
		 *	never reaches the browser, and posting the field empty keeps
		 *	what is stored (see Features::validateSettings()). The hint says
		 *	whether there is one
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderSecret : function( field ) {

			const input = dc.createElement('input');
			input.type = 'password';
			input.value = '';
			input.autocomplete = 'new-password';
			if( field.maxlength )
				input.maxLength = field.maxlength;

			return Nino.admin.features._wrap( field, input,
				Nino.content.getText( field.set === true ? '/_admin/features/hint/secret-set' : '/_admin/features/hint/secret-unset' ), false );
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

			const area = dc.createElement('textarea');
			area.rows = 4;
			area.spellcheck = field.type === 'text';
			area.value = field.type === 'lines'
				? ( field.value || [] ).join( '\n' )
				: ( field.value === null || field.value === undefined ? '' : String( field.value ) );

			return Nino.admin.features._wrap( field, area, field.type === 'lines' ? Nino.content.getText('/_admin/features/hint/lines') : '', true );
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
		 *	Save one feature's settings in one request
		 *
		 *	@param		{Object}	feature
		 *	@param		{Element}	form
		 *	@param		{Element}	save
		 *	@param		{Element}	msg
		 *
		 *	@return		void
		 */
		_save : function( feature, form, save, msg ) {

			save.disabled = true;
			msg.classList.remove('nino-admin-error');
			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.features._apiCall( 'settings', { key : feature.key, fields : Nino.admin.features._collect( form ) }, function( status, response ) {

				save.disabled = false;

				if( status !== 200 || response === null ) {
					msg.classList.add('nino-admin-error');
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
					return;
				}

				// Held rather than written straight to this element: the reload
				// below replaces it, so assigning here shows the confirmation for
				// exactly as long as the request that follows takes
				Nino.admin.features._pendingMsg[feature.key] = Nino.content.getText('/_admin/common/msg/saved');

				// Reloaded rather than left as typed: the values come back as
				// config.php now holds them, and the secret's hint says it is set
				Nino.admin.features.init();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.features.init );

})(window, document, document.documentElement, document.body);
