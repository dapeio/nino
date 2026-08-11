

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	Nino.editor.js					Admin area
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.editor = {

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

			PANEL_CLASS : { dashboard : 'show-dashboard', elements : 'show-elements', text : 'show-text', images : 'show-images', users : 'show-user', submissions : 'show-submissions', newsletter : 'show-newsletter', logs : 'show-logs' },

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
				const cls = Nino.editor.router.PANEL_CLASS[panel];
				return cls !== undefined && dc.getElementById('editor-page-wrap').classList.contains( cls );
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

				if( Nino.editor.router.isActive( panel ) === false )
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
				if( Nino.editor.sessionLocale.current === null )
					Nino.editor.sessionLocale.current = locale;
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
				Nino.editor.sessionLocale.current = locale;
				Nino.http.sendRequest( '/_editor/', 'POST', function() {}, { action : 'admin/locale', data : JSON.stringify( { locale : locale } ) } );
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

				const btn = dc.getElementById('editor-theme-toggle');
				if( btn === null )
					return;

				Nino.editor.theme._apply( Nino.editor.theme._stored() );
				btn.addEventListener( 'click', Nino.editor.theme._toggle );
			},

			/**
			 *	Read the stored override, if any
			 *
			 *	@return		{string}	'light' | 'dark' | '' (follow OS setting)
			 */
			_stored : function() {
				try {
					return wn.localStorage.getItem( Nino.editor.theme.STORAGE_KEY ) || '';
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

				if( value === '' )
					dE.removeAttribute('data-theme');
				else
					dE.setAttribute( 'data-theme', value );

				const btn = dc.getElementById('editor-theme-toggle');
				if( btn !== null )
					btn.textContent = { '' : '☀️🌙' , 'dark':'🌙', 'light' : '☀️'  }[value];
			},

			/**
			 *	Cycle: follow OS -> light -> dark -> follow OS
			 *
			 *	@return		void
			 */
			_toggle : function() {

				const current = Nino.editor.theme._stored();
				const next = ( current === '' ) ? 'light' : ( current === 'light' ? 'dark' : '' );

				try {
					if( next === '' )
						wn.localStorage.removeItem( Nino.editor.theme.STORAGE_KEY );
					else
						wn.localStorage.setItem( Nino.editor.theme.STORAGE_KEY, next );
				} catch(e) {}

				Nino.editor.theme._apply( next );
			},
		},

		/**
		 *	The bar's ⚙️ settings menu (theme toggle + locale picker) -
		 *	click/tap-toggled, not :hover: touch devices have no real hover
		 *	state (only inconsistent tap-simulated ones, with no equivalent
		 *	"un-hover" gesture to close it again), and a :hover-only menu
		 *	build from a plain <div> is also unreachable by keyboard on any
		 *	device. Open/closed state is the .editor-hidden utility class
		 *	every other panel's drill-down levels already use.
		 */
		navUi : {

			/**
			 *	Wire up the toggle button, click-outside, and Escape
			 *
			 *	@return		void
			 */
			init : function() {

				const wrap 		= dc.getElementById('editor-nav-ui');
				const toggle	= dc.getElementById('editor-nav-ui-toggle');
				const menu 		= dc.getElementById('editor-nav-ui-menu');

				if( wrap === null || toggle === null || menu === null )
					return;

				toggle.addEventListener( 'click', function( ev ) {
					ev.stopPropagation();
					const isOpen = menu.classList.toggle('editor-hidden') === false;
					toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
				} );

				dc.addEventListener( 'click', function( ev ) {
					if( wrap.contains( ev.target ) === false )
						Nino.editor.navUi.close();
				} );

				dc.addEventListener( 'keydown', function( ev ) {
					if( ev.key === 'Escape' )
						Nino.editor.navUi.close();
				} );
			},

			/**
			 *	Close the menu (safe to call whether it's open or not)
			 *
			 *	@return		void
			 */
			close : function() {
				const toggle	= dc.getElementById('editor-nav-ui-toggle');
				const menu 		= dc.getElementById('editor-nav-ui-menu');
				if( menu === null || toggle === null )
					return;
				menu.classList.add('editor-hidden');
				toggle.setAttribute( 'aria-expanded', 'false' );
			},
		},

		/**
		 *	Build a url to a file the framework itself serves (eg. an uploaded
		 *	image) - reads the site's deploy-path prefix from #editor-page-wrap's
		 *	data-dir (filled server-side from /nino/dir), since
		 *	that's not otherwise known to js and can be a subdirectory rather
		 *	than site root
		 *
		 *	@param		{string}	path					Path starting with "/" (eg. "/images/x.jpg")
		 *
		 *	@return		{string}
		 */
		assetUrl : function( path ) {
			return ( dc.getElementById('editor-page-wrap').dataset.dir ?? '' )+ path;
		},

		/**
		 *	Decode html entities back to plain text (eg. "&amp;" -> "&") -
		 *	Submissions/Newsletter fields are stored htmlspecialchars()-encoded
		 *	(see Shortcodes\Form/Newsletter::callbackResponse()), so anything
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
		 *	objects - shared by Submissions/Newsletter (see submissions.js/
		 *	newsletter.js), no server endpoint needed since both panels
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
			const lines = [ headers.map( Nino.editor.csvCell ).join(',') ].concat(
				rows.map( function( row ) { return headers.map( function( key ) { return Nino.editor.csvCell( row[key] ) } ).join(',') } )
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
			let str = Nino.editor.decodeEntities( String( value ?? '' ) );
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

			Nino.editor.theme.init();
			Nino.editor.navUi.init();

			// The picker's <option> values are already real ?locale=xx
			// targets (see Admin::_localePickerHtml()) - navigating there
			// is all that's needed, Admin::init() reads it back server-side
			const localePicker = dc.getElementById('editor-localepicker');
			if( localePicker !== null )
				localePicker.addEventListener( 'change', function(){ wn.location.href = this.value } );

			const el = {
				'pageWrap'		: dc.getElementById('editor-page-wrap'),
				'userLogout'	: dc.getElementById('editor-user-logout'),
				'navDashboard' : dc.getElementById('editor-nav-dashboard'),
				'navElements'	: dc.getElementById('editor-nav-elements'),
				'navText'			: dc.getElementById('editor-nav-text'),
				'navImages'		: dc.getElementById('editor-nav-images'),
				'navUser'			: dc.getElementById('editor-nav-user'),
				'navSubmissions' : dc.getElementById('editor-nav-submissions'),
				'navNewsletter' : dc.getElementById('editor-nav-newsletter'),
				'navLogs'			: dc.getElementById('editor-nav-logs'),
			};

			// panel name (matches the hash + router.PANEL_CLASS) -> [ page-wrap class, tab link, panel's own "re-show whatever it's currently on" fn ]
			const panels = {
				dashboard : [ 'show-dashboard', 	el.navDashboard, function() { Nino.editor.dashboard.showCurrent() } ],
				elements 	: [ 'show-elements', 	el.navElements, function() { Nino.editor.elements.showCurrent() } ],
				text 			: [ 'show-text', 			el.navText, 		function() { Nino.editor.text.showCurrent() } ],
				images 		: [ 'show-images', 	el.navImages, 	function() { Nino.editor.images.showCurrent() } ],
				users 		: [ 'show-user', 			el.navUser, 		function() { Nino.editor.users.showCurrent() } ],
				submissions : [ 'show-submissions', el.navSubmissions, function() { Nino.editor.submissions.showCurrent() } ],
				newsletter : [ 'show-newsletter', 	el.navNewsletter, function() { Nino.editor.newsletter.showCurrent() } ],
				logs 			: [ 'show-logs', 			el.navLogs, 		function() { Nino.editor.logs.showCurrent() } ],
			};

			const allowed = new Set( ( el.pageWrap.dataset.panels || 'dashboard users' ).split(/\s+/).filter( Boolean ) );
			const tabs = Object.keys( panels ).map( function( panel ) { return panels[panel][1] } );

			Object.keys( panels ).forEach( function( panel ) {
				if( allowed.has( panel ) === false )
					panels[panel][1].hidden = true;
			} );

			/**
			 *	Switch panel and mark the clicked tab active - each panel keeps its
			 *	own drill-down state (see elements.js/text.js/users.js), switching
			 *	tabs never resets it, so jumping back and forth is always exactly
			 *	where you left it. Re-syncs the hash to that panel's current state,
			 *	since router.set() ignores writes from a panel that isn't on screen
			 *	(see the router docblock) - which, until this tab becomes active, was
			 *	every write this panel made while loading in the background.
			 *
			 *	@param		{string}	panel					Panel name (users|text|elements)
			 *
			 *	@return		void
			 */
			function selectTab( panel ) {
				const target = panels[panel];
				if( target === undefined || allowed.has( panel ) === false )
					return;
				el.pageWrap.className = target[0];
				tabs.forEach( function( t ) { t.classList.toggle( 'active', t === target[1] ) } );
				target[2]();
			}

			/**
			 *	Select whichever tab the current #hash points at, defaulting to
			 *	Dashboard when there's none (or it doesn't name a real panel) -
			 *	the panel's own init() separately drills into the exact
			 *	sub-state once its data has loaded
			 *
			 *	@return		void
			 */
			function selectTabFromHash() {
				const panel = Nino.editor.router.current().panel;
				selectTab( panels[panel] !== undefined && allowed.has( panel ) === true ? panel : 'dashboard' );
			}

			// Bind events - the User/Texte/Elemente bar itself always stays visible
			// (see style.css), local "‹ Zurück" links inside each panel handle drilling
			// back up a level, so there's no separate "back to main menu" step anymore
			el.userLogout.addEventListener( 'click', function(ev){ ev.preventDefault(); Nino.auth.logout( '/_editor' ) } );
			el.navDashboard.addEventListener( 'click', function(ev){ ev.preventDefault(); selectTab( 'dashboard' ) } );
			el.navUser.addEventListener( 'click', function(ev){ ev.preventDefault(); selectTab( 'users' ) } );
			el.navText.addEventListener( 'click', function(ev){ ev.preventDefault(); selectTab( 'text' ) } );
			el.navImages.addEventListener( 'click', function(ev){ ev.preventDefault(); selectTab( 'images' ) } );
			el.navElements.addEventListener( 'click', function(ev){ ev.preventDefault(); selectTab( 'elements' ) } );
			el.navSubmissions.addEventListener( 'click', function(ev){ ev.preventDefault(); selectTab( 'submissions' ) } );
			el.navNewsletter.addEventListener( 'click', function(ev){ ev.preventDefault(); selectTab( 'newsletter' ) } );
			el.navLogs.addEventListener( 'click', function(ev){ ev.preventDefault(); selectTab( 'logs' ) } );

			// Restore the tab from a refresh/deep link, and react to manual hash edits
			// or the browser back/forward buttons (our own updates use replaceState,
			// which never fires hashchange, so this only ever reacts to real user navigation)
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

	Nino.events.bindCallback( 'ready', Nino.editor.onReady );
	Nino.events.bindCallback( 'scroll', Nino.editor.onScroll );
	Nino.events.bindCallback( 'resize', Nino.editor.onResize );

})(window, document, document.documentElement, document.body);
