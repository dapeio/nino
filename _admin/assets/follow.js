/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	follow.js								The follow window's shell (templates/page-follow.tpl):
 *													the website in a frame, loaded anew after every write
 *													of the workbench. The workbench tells it on a
 *													BroadcastChannel (see Nino.admin.touch() in script.js)
 *													and it listens; it never asks the server anything
 *													itself. Occupies the Nino.admin slot script.js
 *													occupies in the workbench - the two are never in the
 *													same bundle (see Admin::init()).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = {

		// Where the switch keeps its state, in this browser
		STORAGE_KEY : 'nino-admin-follow',

		// How long messages are gathered into one load: a save often writes
		// two kinds of data, and the page is not asked for twice
		DEBOUNCE : 200,

		// What an address must look like to be gone to (the rule of
		// \Nino\Admin\Admin::FOLLOW_PATH): a path below this site that is
		// not the workbench's own, with nothing in it that ends an attribute,
		// starts a tag or is a control character
		PATH : /^\/(?!\/)(?!_admin(?:[/?#]|$))[^\s<>"'\\\x00-\x1f\x7f]*$/i,

		// Where the page last shown is kept, in this browser
		URI_KEY : 'nino-admin-follow-uri',

		// Where the workbench keeps its theme (see Nino.admin.theme in script.js)
		THEME_KEY : 'nino-admin-theme',

		_dir : '',
		_frame : null,
		_pathEl : null,
		_follow : true,
		_timer : null,
		_pending : null,
		_channel : null,

		/**
		 *	Whether an address is a path of this site: what a message names to
		 *	be gone to. Anything else counts as no address
		 *
		 *	@param		{*}				uri
		 *
		 *	@return		{boolean}
		 */
		isPath : function( uri ) {
			return typeof uri === 'string' && Nino.admin.PATH.test( uri );
		},

		/**
		 *	Wire up the window: the bar, the switch, the frame and the channel
		 *
		 *	@return		void
		 */
		onReady : function() {

			// The window is opened by the workbench with rel="opener", so that a
			// second click finds it again by its name - and a page in the frame
			// that leaves the site by target="_top" could set opener.location
			// and take the workbench to another site. The name still finds it
			try {
				wn.opener = null;
			}
			catch( e ) {
				// A browser that keeps it keeps it
			}

			Nino.admin._theme();

			const wrap = dc.getElementById('admin-follow-wrap');
			if( wrap === null )
				return;

			Nino.admin._dir 		= wrap.dataset !== undefined && typeof wrap.dataset.dir === 'string' ? wrap.dataset.dir : '';
			Nino.admin._frame 	= dc.getElementById('admin-follow-frame');
			Nino.admin._pathEl 	= dc.getElementById('admin-follow-path');

			const reload = dc.getElementById('admin-follow-reload');
			const mount = dc.getElementById('admin-follow-switch');

			if( reload !== null )
				reload.addEventListener( 'click', Nino.admin.reload );

			if( mount !== null )
				Nino.admin._drawSwitch( mount );

			if( Nino.admin._frame !== null ) {
				Nino.admin._frame.addEventListener( 'load', Nino.admin._loaded );
				Nino.admin._start();
			}

			// A browser without the channel keeps a frame with a button
			if( typeof wn.BroadcastChannel === 'function' ) {
				Nino.admin._channel = new wn.BroadcastChannel( 'nino-admin:'+ Nino.admin._dir );
				Nino.admin._channel.addEventListener( 'message', function( event ) { Nino.admin.message( event.data ) } );
			}
		},

		/**
		 *	The theme the workbench is in, taken over: light or dark where the
		 *	person chose one there, and the system's own where none was chosen
		 *
		 *	@return		void
		 */
		_theme : function() {

			let theme = '';

			try {
				theme = wn.localStorage.getItem( Nino.admin.THEME_KEY ) || '';
			}
			catch( e ) {
				theme = '';
			}

			if( theme !== 'light' && theme !== 'dark' )
				return;

			dE.setAttribute( 'data-theme', theme );
			bd.className = 'theme-'+ theme;
		},

		/**
		 *	The frame is given its first address - the template names one in
		 *	data-src and leaves src out, so that no request goes out for a page
		 *	this is about to replace. ?uri= is what was asked for and wins; else
		 *	the page the frame showed last, which a second click on the
		 *	workbench's link would lose; else the home page
		 *
		 *	@return		void
		 */
		_start : function() {

			const frame = Nino.admin._frame;
			let address = frame.dataset !== undefined && typeof frame.dataset.src === 'string' ? frame.dataset.src : Nino.admin._dir+ '/';

			if( /(?:^|[?&])uri=/.test( String( wn.location.search ) ) === false ) {
				try {
					const kept = wn.localStorage.getItem( Nino.admin.URI_KEY );
					if( Nino.admin.isPath( kept ) )
						address = Nino.admin._dir+ kept;
				}
				catch( e ) {
					// Nothing kept is the home page
				}
			}

			frame.src = address;
		},

		/**
		 *	The switch "Follow the page": on, a save that names a page takes the
		 *	frame there; off, the frame loads again the page it shows. The state
		 *	is kept in the browser, on where nothing was kept
		 *
		 *	@param		{Element}	mount
		 *
		 *	@return		void
		 */
		_drawSwitch : function( mount ) {

			try {
				Nino.admin._follow = wn.localStorage.getItem( Nino.admin.STORAGE_KEY ) !== '0';
			}
			catch( e ) {
				Nino.admin._follow = true;
			}

			const field = Nino.adminUi.switchField( {
				key 		: 'follow',
				checked : Nino.admin._follow,
				label 	: Nino.content.getText('/_admin/follow/label/switch'),
			} );

			// The sentence is the title, not a line under the label: the bar is one line high
			field.setAttribute( 'title', Nino.content.getText('/_admin/follow/hint/switch') );

			field.querySelector('input').addEventListener( 'change', function() {

				Nino.admin._follow = this.checked === true;

				try {
					wn.localStorage.setItem( Nino.admin.STORAGE_KEY, Nino.admin._follow === true ? '1' : '0' );
				}
				catch( e ) {
					// A browser that keeps nothing forgets the switch at the next load
				}
			} );

			mount.appendChild( field );
		},

		/**
		 *	A message of the workbench: something was written. The load waits
		 *	for the others of the same save; of the addresses they name, the
		 *	last one counts
		 *
		 *	@param		{*}				data				{ kind, uri, at }
		 *
		 *	@return		void
		 */
		message : function( data ) {

			if( data !== null && typeof data === 'object' && Nino.admin.isPath( data.uri ) )
				Nino.admin._pending = data.uri;

			if( Nino.admin._timer !== null )
				return;

			Nino.admin._timer = wn.setTimeout( Nino.admin._flush, Nino.admin.DEBOUNCE );
		},

		/**
		 *	The gathered messages are one load
		 *
		 *	@return		void
		 */
		_flush : function() {

			const uri = Nino.admin._pending;

			Nino.admin._timer 	= null;
			Nino.admin._pending = null;

			if( uri !== null && Nino.admin._follow === true )
				Nino.admin._go( uri );
			else
				Nino.admin.reload();
		},

		/**
		 *	The frame goes to a page of the site
		 *
		 *	@param		{string}	uri
		 *
		 *	@return		void
		 */
		_go : function( uri ) {

			if( Nino.admin._frame !== null )
				Nino.admin._frame.src = Nino.admin._dir+ uri;
		},

		/**
		 *	The frame loads the page it shows once more, now. A page the frame
		 *	cannot be reached into - it has left the site - is gone to again
		 *
		 *	@return		void
		 */
		reload : function() {

			const frame = Nino.admin._frame;
			if( frame === null )
				return;

			try {
				frame.contentWindow.location.reload();
			}
			catch( e ) {
				const address = frame.src;
				frame.src = address;
			}
		},

		/**
		 *	The frame has loaded a page: the bar says which, by its path
		 *	without the directory the installation is in. Text, as it is an
		 *	address the website chose
		 *
		 *	@return		void
		 */
		_loaded : function() {

			if( Nino.admin._pathEl === null )
				return;

			let path;
			try {
				path = String( Nino.admin._frame.contentWindow.location.pathname );
			}
			catch( e ) {
				// A page of another site, or one the browser keeps closed: the bar
				// shows none rather than the one before
				Nino.admin._pathEl.textContent = '';
				return;
			}

			const dir = Nino.admin._dir;
			if( dir !== '' && path.indexOf( dir ) === 0 )
				path = path.slice( dir.length );

			// about:blank has a pathname of 'blank': no page, so no path
			if( path === '' )
				path = '/';

			Nino.admin._pathEl.textContent = path.charAt(0) === '/' ? path : '';

			// Kept for the next time the window is opened without an address
			if( Nino.admin.isPath( path ) === false )
				return;

			try {
				wn.localStorage.setItem( Nino.admin.URI_KEY, path );
			}
			catch( e ) {
				// A browser that keeps nothing opens at the home page
			}
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.onReady );

})(window, document, document.documentElement, document.body);
