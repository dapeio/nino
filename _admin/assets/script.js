

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	script.js								The workbench shell (/_admin): the url-hash router, the
 *													theme and locale chrome, the panel rail and the csv
 *													export - and the Nino.admin namespace itself, which
 *													every panel's own assets/admin.js attaches to (see
 *													\Nino\Admin\Panels). Loads after Nino.admin.js, whose
 *													primitives it builds on, and before any panel file.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = {

		/**
		 *	Minimal url-hash "router": lets a panel (Elements/Text/Users) persist
		 *	which drill-down level it's on into the hash, so a refresh restores
		 *	the exact view instead of resetting to the panel's top level. Uses
		 *	history.replaceState (not location.hash=) so it never scroll-jumps
		 *	and never fires its own hashchange event.
		 *
		 *	All three panels' init() run unconditionally on page load and each
		 *	ends by calling its own "show my current state" function - which is
		 *	also what set() is called from. Without a guard, whichever panel's
		 *	background load finishes last would stomp the hash with its own
		 *	default state, regardless of which panel the hash actually pointed
		 *	at. set() only writes when `panel` is the one currently on screen;
		 *	onReady() below resolves that race by selecting the hash's target
		 *	tab synchronously, before any panel's async data can arrive.
		 */
		router : {

			/**
			 *	Whether `panel` names a pane the shell rendered - the panes come
			 *	from the server's panel registry (see Admin::panels()), so the
			 *	DOM is the list, and a name that has no pane is no panel. A tab
			 *	of a pane (see Panels::panesHtml()) is a panel of its own
			 *
			 *	@param		{string}	panel
			 *
			 *	@return		{boolean}
			 */
			exists : function( panel ) {
				return typeof panel === 'string' && /^[a-z][a-z0-9-]*$/.test( panel )
					&& ( dc.getElementById( 'admin-content-'+ panel ) !== null || dc.getElementById( 'admin-tab-'+ panel ) !== null );
			},

			/**
			 *	Read the current #hash into { panel, parts[] }
			 *
			 *	@return		{Object}
			 */
			current : function() {
				const raw = wn.location.hash.replace(/^#/, '');
				try {
					const parts = raw === '' ? [] : raw.split('/').map( function(p) { return decodeURIComponent(p) } );
					return { panel : parts[0] ?? '', parts : parts.slice(1) };
				} catch(e) {
					// A hand-edited hash with a stray '%' must not abort all editor
					// initialization with decodeURIComponent()'s URIError.
					return { panel : '', parts : [] };
				}
			},

			/**
			 *	Whether `panel` is the tab currently on screen
			 *
			 *	@param		{string}	panel
			 *
			 *	@return		{boolean}
			 */
			isActive : function( panel ) {
				return Nino.admin.router.exists( panel ) && dc.getElementById('admin-page-wrap').classList.contains( 'show-'+ panel );
			},

			/**
			 *	Replace the hash with #panel/part/part/... - a no-op while
			 *	`panel` isn't the currently visible tab (see class docblock)
			 *
			 *	@param		{string}	panel
			 *	@param		{Array}		[parts]
			 *
			 *	@return		void
			 */
			set : function( panel, parts ) {

				if( Nino.admin.router.isActive( panel ) === false )
					return;

				const hash = '#'+ [ panel ].concat( parts || [] ).map( encodeURIComponent ).join('/');
				if( wn.location.hash !== hash )
					wn.history.replaceState( null, '', hash );
			},
		},

		/**
		 *	Shared "which locale am I currently working in" cache, so switching
		 *	it in Elements also applies the next time Text opens an item (and
		 *	vice versa) - each panel keeping its own copy would go stale the
		 *	moment the OTHER one changed it, since both only fetch their
		 *	initial value once, from their own init(). Persisted server-side
		 *	(POST admin/locale) so it also survives a reload.
		 */
		sessionLocale : {

			current : null,

			/**
			 *	Called by each panel's init() with its own initial locale, once
			 *	it's known - first one in wins, so a later-resolving panel's
			 *	initial value never overwrites one the user already changed
			 *
			 *	@param		{string}	locale
			 *
			 *	@return		void
			 */
			init : function( locale ) {
				if( Nino.admin.sessionLocale.current === null )
					Nino.admin.sessionLocale.current = locale;
			},

			/**
			 *	Called when the user changes a locale switch in any panel -
			 *	fire and forget, nothing in the UI depends on the request's result
			 *
			 *	@param		{string}	locale
			 *
			 *	@return		void
			 */
			set : function( locale ) {
				Nino.admin.sessionLocale.current = locale;
				Nino.http.sendRequest( '/_admin/', 'POST', function() {}, { action : 'admin/locale', data : JSON.stringify( { locale : locale } ) } );
			},
		},

		/**
		 *	Manual light/dark override for the admin dashboard, independent
		 *	of the device's OS-level dark mode setting - some admins prefer
		 *	dark everywhere except here. Purely a local browser preference
		 *	(localStorage), not site data, so it never touches the server.
		 *	Defaults to following the OS setting (no override stored).
		 */
		theme : {

			STORAGE_KEY : 'nino-admin-theme',

			/**
			 *	Apply whatever's stored (if anything) and wire up the toggle
			 *	button's click handler
			 *
			 *	@return		void
			 */
			init : function() {

				const btn = dc.getElementById('admin-theme-toggle');
				if( btn === null )
					return;

				Nino.admin.theme._apply( Nino.admin.theme._stored() );
				btn.addEventListener( 'click', Nino.admin.theme._toggle );
			},

			/**
			 *	Read the stored override, if any
			 *
			 *	@return		{string}	'light' | 'dark' | '' (follow OS setting)
			 */
			_stored : function() {
				try {
					return wn.localStorage.getItem( Nino.admin.theme.STORAGE_KEY ) || '';
				} catch(e) {
					return '';
				}
			},

			/**
			 *	Set (or clear) the override and reflect it on <html> and the
			 *	toggle button's label
			 *
			 *	@param		{string}	value		'light' | 'dark' | ''
			 *
			 *	@return		void
			 */
			_apply : function( value ) {

				if( value === '' ) {
					dE.removeAttribute('data-theme');
					bd.className = '';
					return;
				}

				dE.setAttribute( 'data-theme', value );
				bd.className = 'theme-'+ value;
			},

			/**
			 *	Cycle: follow OS -> light -> dark -> follow OS
			 *
			 *	@return		void
			 */
			_toggle : function() {

				const current = Nino.admin.theme._stored();
				const next = ( current === '' ) ? 'light' : ( current === 'light' ? 'dark' : '' );

				try {
					if( next === '' )
						wn.localStorage.removeItem( Nino.admin.theme.STORAGE_KEY );
					else
						wn.localStorage.setItem( Nino.admin.theme.STORAGE_KEY, next );
				} catch(e) {}

				Nino.admin.theme._apply( next );
			},
		},

		/**
		 *	The bar's theme settings menu (theme toggle + locale picker) -
		 *	click/tap-toggled, not :hover: touch devices have no real hover
		 *	state (only inconsistent tap-simulated ones, with no equivalent
		 *	"un-hover" gesture to close it again), and a :hover-only menu
		 *	build from a plain <div> is also unreachable by keyboard on any
		 *	device. Open/closed state is the .admin-hidden utility class
		 *	every other panel's drill-down levels already use.
		 */
		navUi : {

			/**
			 *	Wire up the toggle button, click-outside, and Escape
			 *
			 *	@return		void
			 */
			init : function() {

				const wrap 		= dc.getElementById('admin-nav-ui');
				const toggle	= dc.getElementById('admin-nav-ui-toggle');
				const menu 		= dc.getElementById('admin-nav-ui-menu');

				if( wrap === null || toggle === null || menu === null )
					return;

				toggle.addEventListener( 'click', function( ev ) {
					ev.stopPropagation();
					const isOpen = menu.classList.toggle('admin-hidden') === false;
					toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
				} );

				dc.addEventListener( 'click', function( ev ) {
					if( wrap.contains( ev.target ) === false )
						Nino.admin.navUi.close();
				} );

				dc.addEventListener( 'keydown', function( ev ) {
					if( ev.key === 'Escape' )
						Nino.admin.navUi.close();
				} );
			},

			/**
			 *	Close the menu (safe to call whether it's open or not)
			 *
			 *	@return		void
			 */
			close : function() {
				const toggle	= dc.getElementById('admin-nav-ui-toggle');
				const menu 		= dc.getElementById('admin-nav-ui-menu');
				if( menu === null || toggle === null )
					return;
				menu.classList.add('admin-hidden');
				toggle.setAttribute( 'aria-expanded', 'false' );
			},
		},

		/**
		 *	The rail's fold - desktop only, below the sidebar breakpoint the
		 *	rail is a top bar and the classes set here are inert (see
		 *	style.css). Two states an account can pin, open or folded,
		 *	kept in localStorage exactly like the theme override, and one it
		 *	gets without asking: open on a page panel, folded on a workspace
		 *	panel (see layout() in the panel contract), so the Template
		 *	Builder opens wide without a click and the dashboard opens with
		 *	its labels. selectTab() reports which kind is on screen.
		 */
		rail : {

			STORAGE_KEY : 'nino-admin-rail',
			_workspace : false,

			/**
			 *	Wire up the fold button and apply the stored state
			 *
			 *	@return		void
			 */
			init : function() {

				const btn = dc.getElementById('admin-rail-toggle');
				if( btn === null )
					return;

				btn.addEventListener( 'click', Nino.admin.rail.toggle );
				Nino.admin.rail._apply();
			},

			/**
			 *	Read the pinned state, if any
			 *
			 *	@return		{string}	'open' | 'folded' | '' (whatever the panel implies)
			 */
			_stored : function() {
				try {
					return wn.localStorage.getItem( Nino.admin.rail.STORAGE_KEY ) || '';
				} catch(e) {
					return '';
				}
			},

			/**
			 *	Told by selectTab() which kind of panel is on screen
			 *
			 *	@param		{boolean}	workspace		true for a layout() === 'workspace' panel
			 *
			 *	@return		void
			 */
			layout : function( workspace ) {
				Nino.admin.rail._workspace = workspace === true;
				Nino.admin.rail._apply();
			},

			/**
			 *	Whether the rail is folded right now: the pinned state when
			 *	there is one, else what the panel on screen implies
			 *
			 *	@return		{boolean}
			 */
			folded : function() {
				const stored = Nino.admin.rail._stored();
				return stored === 'folded' || ( stored === '' && Nino.admin.rail._workspace === true );
			},

			/**
			 *	Reflect layout and fold on the shell and the button
			 *
			 *	@return		void
			 */
			_apply : function() {

				const wrap = dc.getElementById('admin-page-wrap');
				if( wrap === null )
					return;

				const folded = Nino.admin.rail.folded();
				wrap.classList.toggle( 'nino-admin-shell--workspace', Nino.admin.rail._workspace );
				wrap.classList.toggle( 'nino-admin-shell--folded', folded );

				const btn = dc.getElementById('admin-rail-toggle');
				if( btn !== null )
					btn.setAttribute( 'aria-pressed', folded ? 'true' : 'false' );
			},

			/**
			 *	Pin the opposite of what is on screen. Pinning what the panel
			 *	would have implied anyway unpins instead, so a rail folded by
			 *	hand on the dashboard and opened again by hand is back to
			 *	following the panels
			 *
			 *	@return		void
			 */
			toggle : function() {

				const next 		= Nino.admin.rail.folded() ? 'open' : 'folded';
				const implied	= Nino.admin.rail._workspace === true ? 'folded' : 'open';

				try {
					if( next === implied )
						wn.localStorage.removeItem( Nino.admin.rail.STORAGE_KEY );
					else
						wn.localStorage.setItem( Nino.admin.rail.STORAGE_KEY, next );
				} catch(e) {}

				Nino.admin.rail._apply();
			},
		},

		/**
		 *	Build a url to a file the framework itself serves (eg. an uploaded
		 *	image) - reads the site's deploy-path prefix from #admin-page-wrap's
		 *	data-dir (filled server-side from /nino/dir), since
		 *	that's not otherwise known to js and can be a subdirectory rather
		 *	than site root
		 *
		 *	@param		{string}	path					Path starting with "/" (eg. "/images/x.jpg")
		 *
		 *	@return		{string}
		 */
		assetUrl : function( path ) {
			return ( dc.getElementById('admin-page-wrap').dataset.dir ?? '' )+ path;
		},

		/**
		 *	The url of something a browser loads directly - an uploaded image,
		 *	a bundled stylesheet. Those live under the public content
		 *	directory, one level below the project root (see
		 *	\Nino\Filesystem::getPublicDir()), unlike a link into /_admin
		 *
		 *	@param		{string}	path			Eg. '/images/hero.jpg'
		 *
		 *	@return		{string}
		 */
		publicUrl : function( path ) {
			return ( dc.getElementById('admin-page-wrap').dataset.public ?? '' )+ path;
		},

		/**
		 *	The common pinned context row used by every drill-down level.
		 *	Locale selects can be appended after creation by the calling module.
		 *
		 *	@param		{Element}	backLink
		 *
		 *	@return		{Element}
		 */
		formToolbar : function( backLink ) {
			return Nino.adminUi.contextBar( backLink );
		},

		/**
		 *	Decode html entities back to plain text (eg. "&amp;" -> "&") -
		 *	Submissions/Newsletter fields are stored htmlspecialchars()-encoded
		 *	(see Modules\Form/Newsletter::callbackResponse()), so anything
		 *	rendering their raw value for display or export needs this first.
		 *	Safe regardless of what the string contains: assigning to a
		 *	detached <textarea>'s innerHTML never executes markup, it's
		 *	always treated as literal text content - unlike assigning to a
		 *	real element's innerHTML, this never depends on the string
		 *	actually being fully escaped to stay safe
		 *
		 *	@param		{string}	str
		 *
		 *	@return		{string}
		 */
		decodeEntities : function( str ) {
			const el = dc.createElement('textarea');
			el.innerHTML = str;
			return el.value;
		},

		/**
		 *	Trigger a client-side CSV download from an array of plain, flat
		 *	objects - shared by the Form and Newsletter modules' panels (see
		 *	their assets/editor.js), no server endpoint needed since both panels
		 *	already have the full entries array loaded for their list view.
		 *	Column order follows the first row's own key order
		 *
		 *	@param		{string}	filename			Download filename (eg. "newsletter.csv")
		 *	@param		{Array}		rows					Array of plain objects, same shape for every row
		 *
		 *	@return		void
		 */
		exportCsv : function( filename, rows ) {

			if( rows.length === 0 )
				return;

			const headers = Object.keys( rows[0] );
			const lines = [ headers.map( Nino.admin.csvCell ).join(',') ].concat(
				rows.map( function( row ) { return headers.map( function( key ) { return Nino.admin.csvCell( row[key] ) } ).join(',') } )
			);

			// Leading BOM so Excel (still the most common CSV consumer) detects
			// UTF-8 instead of guessing a local codepage and mangling umlauts
			const blob = new Blob( [ '\uFEFF'+ lines.join('\r\n') ], { type : 'text/csv;charset=utf-8;' } );
			const url = URL.createObjectURL( blob );
			const a = dc.createElement('a');
			a.href = url;
			a.download = filename;
			dc.body.appendChild( a );
			a.click();
			a.remove();
			URL.revokeObjectURL( url );
		},

		/**
		 *	Escape one CSV cell and neutralize spreadsheet formulas. Prefixing an
		 *	apostrophe is the convention spreadsheet apps use for explicit text;
		 *	it also covers formula markers hidden behind whitespace.
		 *
		 *	@param		{*}	value
		 *
		 *	@return		{string}
		 */
		csvCell : function( value ) {
			let str = Nino.admin.decodeEntities( String( value ?? '' ) );
			if( /^[\t\r\n ]*[=+\-@]/.test( str ) === true )
				str = "'"+ str;
			return /["\r\n,]/.test( str ) ? '"'+ str.replace( /"/g, '""' )+ '"' : str;
		},

		/**
		 *	Wire up the admin dashboard: logout button and the nav switches
		 *	between the user/text/elements panels
		 *
		 *	@return		void
		 */
		onReady : function() {

			Nino.admin.theme.init();
			Nino.admin.navUi.init();
			Nino.admin.rail.init();

			// The picker's <option> values are already real ?locale=xx
			// targets (see Admin::_localePickerHtml()) - navigating there
			// is all that's needed, Admin::init() reads it back server-side
			const localePicker = dc.getElementById('admin-localepicker');
			if( localePicker !== null )
				localePicker.addEventListener( 'change', function(){ wn.location.href = this.value } );

			const el = {
				'pageWrap'		: dc.getElementById('admin-page-wrap'),
				'userLogout'	: dc.getElementById('admin-user-logout'),
			};

			// The panels are whatever the server rendered into the rail (see
			// Admin::panels() - core and module panels alike): one nav link
			// per panel, carrying its name in data-panel, and one pane with
			// the same name. A panel's script attaches to Nino.admin.<name>
			// and answers showCurrent() when its tab is selected - a panel
			// without a script (or without that function) simply shows its
			// pane. A pane may hold tabs (see Panels::panesHtml()): a strip
			// of buttons and one tab pane per tab, each tab a panel of its
			// own with its own script and hash prefix. Nothing in here names
			// a panel, and nothing in here decides who sees one: the server
			// rendered only the panels this account may use (see
			// Admin::visiblePanels()).
			//
			// panel name (matches the hash) -> [ nav link, pane ]
			const panels = {};
			dc.querySelectorAll('#admin-nav-wrap a[data-panel]').forEach( function( link ) {
				panels[link.dataset.panel] = [ link, dc.getElementById( 'admin-content-'+ link.dataset.panel ) ];
			} );

			// tab name -> the panel whose pane holds it, and per panel the tab
			// last open, so coming back to a panel lands where you left it.
			// Only a pane's direct children count: a panel's own markup (the
			// Template Builder's, say) may use data-tab for its own purposes
			const tabOwner = {};
			const lastTab  = {};
			Object.keys( panels ).forEach( function( panel ) {
				if( panels[panel][1] !== null )
					panels[panel][1].querySelectorAll(':scope > div[data-tab]').forEach( function( pane ) { tabOwner[pane.dataset.tab] = panel } );
			} );

			const links = Object.keys( panels ).map( function( panel ) { return panels[panel][0] } );

			/**
			 *	Hand a panel, or one of its tabs, the screen: its state class
			 *	first (router.set() ignores writes from a panel that isn't on
			 *	screen), then its own "re-show whatever it's currently on",
			 *	which is also what re-syncs the hash
			 *
			 *	@param		{string}	name					A panel or tab uri
			 *
			 *	@return		void
			 */
			function show( name ) {
				// Swap the state class without touching the rest: the shell also
				// carries its design-system classes (see style.css), and
				// assigning className outright dropped them
				Nino.adminUi.setStateClass( el.pageWrap, 'show-'+ name );
				const module = Nino.admin[name];
				if( module && typeof module.showCurrent === 'function' )
					module.showCurrent();
			}

			/**
			 *	Switch panel and mark the clicked link active - each panel keeps
			 *	its own drill-down state, switching never resets it, so jumping
			 *	back and forth is always exactly where you left it. A pane with
			 *	tabs opens on the requested one, else the one last open, else
			 *	its first
			 *
			 *	@param		{string}	panel					Panel name
			 *	@param		{string}	[tab]					One of its tabs
			 *
			 *	@return		void
			 */
			function selectTab( panel, tab ) {
				const target = panels[panel];
				if( target === undefined )
					return;
				links.forEach( function( t ) { t.classList.toggle( 'active', t === target[0] ) } );
				// The panes start hidden (see Panels::panesHtml()); showing one
				// is a plain attribute flip, so no stylesheet has to know the
				// panel names either
				dc.querySelectorAll('#admin-content-wrap > [data-panel]').forEach( function( pane ) { pane.hidden = pane.dataset.panel !== panel } );
				// A workspace panel (layout() in the panel contract, carried
				// on the link as data-layout) gets the whole width and, unless
				// the account pinned the rail, a folded one - see rail
				Nino.admin.rail.layout( target[0].dataset.layout === 'workspace' );

				const pane 			= target[1];
				const tabPanes	= pane === null ? [] : Array.from( pane.querySelectorAll(':scope > div[data-tab]') );

				if( tabPanes.length === 0 ) {
					show( panel );
					return;
				}

				const names 	= tabPanes.map( function( p ) { return p.dataset.tab } );
				const current = names.indexOf( tab ) !== -1 ? tab : ( names.indexOf( lastTab[panel] ) !== -1 ? lastTab[panel] : names[0] );
				lastTab[panel] = current;

				tabPanes.forEach( function( p ) { p.hidden = p.dataset.tab !== current } );
				pane.querySelectorAll(':scope > .admin-panel-tabs > button[data-tab]').forEach( function( btn ) {
					const active = btn.dataset.tab === current;
					btn.classList.toggle( 'is-active', active );
					btn.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );

				show( current );
			}

			/**
			 *	Select whichever panel or tab the current #hash points at,
			 *	defaulting to the first panel in the rail when there's none (or
			 *	it doesn't name a real one) - the panel's own init() separately
			 *	drills into the exact sub-state once its data has loaded.
			 *
			 *	The first panel rather than a named one: every screen is a
			 *	module (see Admin::modules()), Dashboard included, and a
			 *	workbench delivered without it - or an account that may not
			 *	see it - still has to land somewhere. With no panel at all
			 *	there is nothing to select and nothing to do
			 *
			 *	@return		void
			 */
			function selectTabFromHash() {
				const name = Nino.admin.router.current().panel;
				if( panels[name] !== undefined )
					return selectTab( name );
				if( tabOwner[name] !== undefined )
					return selectTab( tabOwner[name], name );
				const first = Object.keys( panels )[0];
				if( first !== undefined )
					selectTab( first );
			}

			// Bind events - the rail itself always stays visible, local
			// "‹ Back" links inside each panel handle drilling back up a level
			el.userLogout.addEventListener( 'click', function(ev){ ev.preventDefault(); Nino.auth.logout( '/_admin' ) } );
			Object.keys( panels ).forEach( function( panel ) {
				panels[panel][0].addEventListener( 'click', function(ev){ ev.preventDefault(); selectTab( panel ) } );
			} );
			dc.querySelectorAll('#admin-content-wrap > [data-panel] > .admin-panel-tabs > button[data-tab]').forEach( function( btn ) {
				btn.addEventListener( 'click', function() { selectTab( btn.closest('[data-panel]').dataset.panel, btn.dataset.tab ) } );
			} );

			// Restore the panel from a refresh/deep link, and react to manual hash
			// edits or the browser back/forward buttons (our own updates use
			// replaceState, which never fires hashchange, so this only ever reacts
			// to real user navigation)
			selectTabFromHash();
			wn.addEventListener( 'hashchange', selectTabFromHash );

		},


		/**
		 *	Resize hook (currently unused)
		 *
		 *	@return		void
		 */
		onResize : function() {

		},

		/**
		 *	Scroll hook (currently unused)
		 *
		 *	@return		void
		 */
		onScroll : function() {

		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.onReady );
	Nino.events.bindCallback( 'scroll', Nino.admin.onScroll );
	Nino.events.bindCallback( 'resize', Nino.admin.onResize );

})(window, document, document.documentElement, document.body);
