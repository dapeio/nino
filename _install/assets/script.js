

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Setup wizard frontend: a strictly linear Back/Next flow
 *													through the registered steps (the top nav is a progress
 *													display only, not a jump-menu - see page-wizard.tpl), plus
 *													the shared api-call/error helpers every step's own
 *													assets/<step>.js builds on (see checks.js for the shape a
 *													step's own file follows). A step only exists here as an
 *													<span id="install-nav-<key>"> + <div id="install-content-<key>">
 *													pair wired up in STEPS below - adding one is: one new pair in
 *													the page-wizard.tpl template, one entry in STEPS, and the
 *													step's own assets/<key>.js.
 *
 *													"Next" both commits the current step's data (if it has any -
 *													Setup applies its picker, Themes applies the picked theme,
 *													Webpages applies its page list,
 *													PersonalInfos saves its fields, Admin just checks an
 *													account exists) and advances, replacing what used to be
 *													each step's own save button. A step with nothing to commit
 *													(Checks) just advances.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = {

		// Linear order - index also drives Back/Next's hidden state (first/last step)
		STEPS : [
			{ key : 'checks', 				paneClass : 'show-checks' 				},
			{ key : 'setup', 					paneClass : 'show-setup' 				},
			{ key : 'themes', 				paneClass : 'show-themes' 				},
			{ key : 'webpages', 			paneClass : 'show-webpages' 			},
			{ key : 'personalinfos', paneClass : 'show-personalinfos' },
			{ key : 'admin', 					paneClass : 'show-admin' 				},
			{ key : 'finish', 				paneClass : 'show-finish' 				},
		],

		_index : 0,
		_busy 	: false,

		/**
		 *	Lock both shared navigation controls around one asynchronous commit.
		 *	Keeping Back active while Next was pending let the mutable _index move
		 *	under the response callback, so a successful save advanced from the
		 *	wrong step.
		 *
		 *	@param		{boolean}	busy
		 *
		 *	@return		void
		 */
		_setBusy : function( busy ) {
			Nino.install._busy = busy;
			const backBtn = dc.getElementById('install-back');
			const nextBtn = dc.getElementById('install-next');
			if( backBtn !== null ) backBtn.disabled = busy;
			if( nextBtn !== null ) nextBtn.disabled = busy;
		},

		/**
		 *	Show one step by index: swap the visible pane, update the nav's
		 *	progress highlight and the Back/Next buttons' visibility, and
		 *	let the step's own module load/refresh itself
		 *
		 *	@param		{number}	index
		 *
		 *	@return		void
		 */
		showStep : function( index ) {

			const step = Nino.install.STEPS[index];
			if( step === undefined )
				return;

			Nino.install._index = index;
			dc.getElementById('install-actions-msg').textContent = '';

			// Swap the state class without touching the rest: the shell also
			// carries its design-system classes (see _nino/Nino.admin.css), and
			// assigning className outright dropped them - taking the whole
			// shared layout with them the first time a tab was clicked
			Nino.adminUi.setStateClass( dc.getElementById('install-page-wrap'), step.paneClass );

			Nino.install.STEPS.forEach( function( s, i ) {
				const nav = dc.getElementById('install-nav-'+ s.key);
				if( nav !== null )
					nav.classList.toggle( 'active', i === index );
			} );

			dc.getElementById('install-back').classList.toggle( 'admin-hidden', index === 0 );
			// Finish keeps its own form/button in place of "Next" - there is
			// nothing left to advance to
			dc.getElementById('install-next').classList.toggle( 'admin-hidden', index === Nino.install.STEPS.length - 1 );

			const mod = Nino.install[step.key];
			if( mod !== undefined && typeof mod.showCurrent === 'function' )
				mod.showCurrent();
		},

		back : function() {

			if( Nino.install._busy === true )
				return;

			const step = Nino.install.STEPS[Nino.install._index];
			const mod 	= step !== undefined ? Nino.install[step.key] : undefined;

			// A step may have an open, purely client-side editor whose values
			// must be folded into its in-memory model before leaving it.
			if( mod !== undefined && typeof mod.beforeLeave === 'function' && mod.beforeLeave() === false )
				return;

			if( Nino.install._index > 0 )
				Nino.install.showStep( Nino.install._index - 1 );
		},

		next : function() {

			if( Nino.install._busy === true )
				return;

			const currentIndex = Nino.install._index;
			const step 		= Nino.install.STEPS[currentIndex];

			Nino.install._setBusy( true );

			Nino.install._commitStep( step.key, function( ok, message ) {

				Nino.install._setBusy( false );

				if( ok === false ) {
					dc.getElementById('install-actions-msg').textContent = message || 'Please fix the error above before continuing.';
					return;
				}

				Nino.install.showStep( currentIndex + 1 );
			} );
		},

		/**
		 *	Dispatch to whichever step-specific action "Next" has to run
		 *	before it may advance - a step with nothing to commit (Checks)
		 *	just calls back true right away
		 *
		 *	@param		{string}		key
		 *	@param		{Function}	callback			Called with ( ok, message )
		 *
		 *	@return		void
		 */
		_commitStep : function( key, callback ) {

			if( key === 'checks' && Nino.install.checks !== undefined && Nino.install.checks._ready !== true )
				return callback( false, 'Environment checks are still running.' );

			if( key === 'setup' && Nino.install.setup !== undefined )
				return Nino.install.setup.apply( function( ok ) { callback( ok ) } );

			if( key === 'themes' && Nino.install.themes !== undefined )
				return Nino.install.themes.apply( function( ok ) { callback( ok ) } );

			if( key === 'webpages' && Nino.install.webpages !== undefined )
				return Nino.install.webpages.apply( function( ok ) { callback( ok ) } );

			if( key === 'personalinfos' && Nino.install.personalinfos !== undefined )
				return Nino.install.personalinfos.save( function( ok ) { callback( ok ) } );

			if( key === 'admin' && Nino.install.admin !== undefined )
				return Nino.install.admin.checkCanAdvance( callback );

			callback( true );
		},

		/**
		 *	Call an install/* wizard action
		 *
		 *	@param		{string}		action				Action name (eg. "checks/run")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		apiCall : function( action, payload, callback ) {
			Nino.http.sendRequest( '/_install/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : action, data : JSON.stringify( payload ) } );
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
		showError : function( container, status, response ) {
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Request failed.' );
			container.appendChild( p );
		},

		/**
		 *	Wire up the wizard shell: Back/Next clicks + the first step's load
		 *
		 *	@return		void
		 */
		onReady : function() {

			const wrap = dc.getElementById('install-page-wrap');
			if( wrap === null )
				return;

			dc.getElementById('install-back').addEventListener( 'click', Nino.install.back );
			dc.getElementById('install-next').addEventListener( 'click', Nino.install.next );

			Nino.install.showStep( 0 );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.install.onReady );

})(window, document, document.documentElement, document.body);
