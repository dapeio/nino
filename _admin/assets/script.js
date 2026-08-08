

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											Dev-only tooling frontend: login, logout, tab switching
 *													between registered modules. Modules only exist here as
 *													<a id="admin-nav-<uri>"> + <div id="dev-content-<uri>"> pairs
 *													wired up in TABS below - adding one is: one new pair in
 *													the page-index.tpl template, one entry in TABS, and the
 *													module's own assets/<uri>.js (see elementtypes.js for the
 *													shape a module's own file follows).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = {

		// uri (matches "admin-nav-<uri>"/"dev-content-<uri>" ids) -> [ page-wrap class, "showCurrent" fn ]
		// Each module registers its own entry here once it exists
		TABS : {
			dashboard : [ 'show-dashboard', function() { Nino.admin.dashboard.showCurrent() } ],
			types : [ 'show-types', function() { Nino.admin.elementTypes.showCurrent() } ],
			elements : [ 'show-elements', function() { Nino.admin.elements.showCurrent() } ],
			text : [ 'show-text', function() { Nino.admin.text.showCurrent() } ],
			pages : [ 'show-pages', function() { Nino.admin.pages.showCurrent() } ],
			images : [ 'show-images', function() { Nino.admin.images.showCurrent() } ],
			users : [ 'show-users', function() { Nino.admin.users.showCurrent() } ],
			restore : [ 'show-restore', function() { Nino.admin.restore.showCurrent() } ],
			config : [ 'show-config', function() { Nino.admin.config.showCurrent() } ],
		},

		/**
		 *	Switch the visible tab/content pane
		 *
		 *	@param		{string}	uri
		 *
		 *	@return		void
		 */
		selectTab : function( uri ) {

			const target = Nino.admin.TABS[uri];
			if( target === undefined )
				return;

			dc.getElementById('admin-page-wrap').className = target[0];
			dc.querySelectorAll('#admin-nav-wrap a').forEach( function( a ) { a.classList.toggle( 'active', a.id === 'admin-nav-'+ uri ) } );
			target[1]();
		},

		/**
		 *	Labels for the shared html editor (_editor/assets/html-editor.js),
		 *	which resolves every one of its own strings through
		 *	Nino.content.getText(). That store is populated by the Jstext module,
		 *	which only the public site and /_editor load - over here it stays
		 *	empty, so getText()'s "|| ''" fallback rendered the whole toolbar
		 *	as four blank buttons, the link popover's Apply/Cancel included.
		 *	Seeded rather than translated: _admin is English-only by design
		 *	(see any module's own hardcoded strings).
		 *
		 *	Existing entries win, so a project that does load a real text store
		 *	here keeps its own values.
		 *
		 *	@return		void
		 */
		seedHtmlEditorText : function() {

			if( wn.Nino.content === undefined )
				return;

			Nino.content.text = Nino.content.text || {};

			const labels = {
				'/_editor/htmleditor/label/strong'					: 'Bold',
				'/_editor/htmleditor/label/em'							: 'Italic',
				'/_editor/htmleditor/label/span'						: 'Highlight',
				'/_editor/htmleditor/label/a'								: 'Link',
				'/_editor/htmleditor/label/linkplaceholder'	: 'https://…',
				'/_editor/htmleditor/label/linkok'					: 'Apply',
				'/_editor/htmleditor/label/linkcancel'			: 'Cancel',
				'/_editor/htmleditor/label/formatting'			: 'Text formatting',
				'/_editor/htmleditor/label/content'					: 'Formatted text',
			};

			Object.keys( labels ).forEach( function( key ) {
				if( !Nino.content.text[key] )
					Nino.content.text[key] = labels[key];
			} );
		},

		/**
		 *	Prefix a project-root-relative path with the deploy directory - the
		 *	project may live in a subdirectory rather than at the site root, and
		 *	the frontend has no other way to know that. Same helper _editor's own
		 *	script.js carries, reading the same [[/nino/dir]] fill (see
		 *	page-index.tpl's data-dir)
		 *
		 *	@param		{string}	path					Eg. "/uploads/elements/services/item1.webp"
		 *
		 *	@return		{string}
		 */
		assetUrl : function( path ) {
			const wrap = dc.getElementById('admin-page-wrap');
			return ( ( wrap === null ? '' : wrap.dataset.dir ) ?? '' )+ path;
		},

		/**
		 *	Wire up the login form (page-login.tpl)
		 *
		 *	@return		void
		 */
		onReadyLogin : function() {

			const form = dc.getElementById('admin-login-form');
			if( form === null )
				return;

			const msg = dc.getElementById('admin-login-msg');
			const input = dc.getElementById('admin-input-pw');

			form.addEventListener( 'submit', function( ev ) {
				ev.preventDefault();

				msg.textContent = 'Checking …';
				Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
					if( xhr.status !== 200 ) {
						msg.textContent = 'Wrong password.';
						input.value = '';
						input.focus();
						return;
					}
					wn.location.reload();
				}, { action : 'dev/login', data : JSON.stringify( { password : input.value } ) } );
			} );

			input.focus();
		},

		/**
		 *	Wire up the dashboard shell (page-index.tpl): logout + nav tabs
		 *
		 *	@return		void
		 */
		onReadyIndex : function() {

			const wrap = dc.getElementById('admin-page-wrap');
			if( wrap === null )
				return;

			dc.getElementById('admin-logout').addEventListener( 'click', function( ev ) {
				ev.preventDefault();
				Nino.http.sendRequest( '/_admin/', 'POST', function() { wn.location.reload() }, { action : 'dev/logout' } );
			} );

			Object.keys( Nino.admin.TABS ).forEach( function( uri ) {
				const a = dc.getElementById('admin-nav-'+ uri);
				if( a !== null )
					a.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.selectTab( uri ) } );
			} );
		},
	};

	// First: Nino.controller.init() assigns Nino.content.text before any ready
	// callback fires, so seeding earlier would be overwritten - and this file is
	// loaded before every module, so this runs before any of them mounts an
	// html editor
	Nino.events.bindCallback( 'ready', function() {
		Nino.admin.seedHtmlEditorText();
		Nino.admin.onReadyLogin();
		Nino.admin.onReadyIndex();
	} );

})(window, document, document.documentElement, document.body);
