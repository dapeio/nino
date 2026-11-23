/**
 *	Nino										A compact filesystembased php framework
 *	Install									Setup wizard frontend: a strictly linear Back/Next flow
 *													through the registered steps (the rail is a progress
 *													display only, not a jump-menu - see page-wizard.tpl), plus
 *													the shared api-call/error helpers every step's own
 *													assets/<step>.js builds on (see checks.js for the shape a
 *													step's own file follows). A step only exists here as an
 *													<span id="install-nav-<key>"> + <div id="install-content-<key>">
 *													pair wired up in STEPS below - adding one is: one new pair in
 *													the page-wizard.tpl template, one entry in STEPS, and the
 *													step module in assets/<key>.js.
 *
 *													"Next" both commits the current step's data (if it has any -
 *													Setup applies its picker, PersonalInfos saves its fields,
 *													Accounts just checks an account exists, Applications
 *													places the applications picked) and advances, replacing
 *													what used to be each step's own save button. A step with nothing to commit
 *													(Checks) just advances. On the last step the same button ends the wizard.
 *
 *													The wizard speaks German or English (pagetext.js, text.js): every
 *													sentence is a key of that table, and a step draws what it writes
 *													by hand again, through its retext(), when the language is changed.
 *													The bar at the foot is the block of the workbench (Nino.adminUi.actionBar()):
 *													its line says how the last action went, "Back" is the quiet button
 *													beside the one main button.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = Object.assign( wn.Nino.install || {}, {

		// Linear order - index also drives Back/Next's hidden state (first/last step)
		STEPS : [
			{ key : 'checks', 				paneClass : 'show-checks' 				},
			{ key : 'setup', 					paneClass : 'show-setup' 				},
			{ key : 'personalinfos', paneClass : 'show-personalinfos' },
			{ key : 'accounts', 			paneClass : 'show-accounts' 		},
			{ key : 'applications', 	paneClass : 'show-applications' 	},
			{ key : 'finish', 				paneClass : 'show-finish' 				},
		],

		_index : 0,
		_busy 	: false,

		// The last sentence a step said as an error: Next says it again where the step calls back with none of its own
		_said 	: '',

		// The bar at the foot: { el, line, primary, secondary }, drawn by onReady()
		bar : null,

		/**
		 *	A sentence of the table, in the language of the page
		 *
		 *	@param		{string}		key
		 *	@param		{...*}			params
		 *
		 *	@return		{string}
		 */
		t : function( key, ...params ) {
			return Nino.pageText.t( key, ...params );
		},

		/**
		 *	A language as the workbench names it, which here is the word the table has for its code
		 *	("Deutsch", "English (US)"): the one block that writes a name, Nino.adminUi.localeName(), is told it
		 *
		 *	@param		{string}				code
		 *	@param		{Array<string>}	[among]				The codes that stand together
		 *
		 *	@return		{string}
		 */
		localeName : function( code, among ) {

			const name = Nino.install.t( '/_admin/install/locale/'+ code.toLowerCase().replace( '_', '-' ) );

			if( name !== '' )
				Nino.adminUi.locales.names[code] = name;

			return Nino.adminUi.localeName( code, among );
		},

		/**
		 *	The length a password must have, which the server states in the page and enforces itself
		 *
		 *	@return		{number}
		 */
		minPassword : function() {

			const wrap = dc.getElementById('install-page-wrap');
			const min = wrap === null ? 0 : parseInt( wrap.dataset.minPassword, 10 );

			return Number.isInteger( min ) === true && min > 0 ? min : 0;
		},

		/**
		 *	What a refusal says, in the language of the page: the sentence of its code, or the general one. A
		 *	refusal about the length of a password has the length to say
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		{string}
		 */
		failure : function( status, response ) {
			return Nino.pageText.failure( status, response, Nino.install.minPassword() );
		},

		/**
		 *	Say what the last action did in the line of the bar: what is under way, or - with `error` - why it failed. An
		 *	error stays until the next thing is said
		 *
		 *	@param		{string}		text					'' empties the line
		 *	@param		{boolean}		[error]
		 *
		 *	@return		void
		 */
		say : function( text, error ) {

			const bar = Nino.install.bar;

			if( bar === null )
				return;

			// What a step said as an error is what Next says when the step then calls back without a sentence of its own
			if( error === true )
				Nino.install._said = text;

			if( error === true )
				bar.line.fail( text );
			else
				bar.line.idle( text );
		},

		/**
		 *	Lock both shared navigation controls around one asynchronous commit.
		 *	Keeping Back active while Next was pending let the mutable _index move
		 *	under the response callback, so a successful save advanced from the
		 *	wrong step. The picker of the language waits as well: what a step writes
		 *	by hand is drawn again for the language, and not in the middle of a request.
		 *
		 *	@param		{boolean}	busy
		 *
		 *	@return		void
		 */
		_setBusy : function( busy ) {
			Nino.install._busy = busy;
			const backBtn = dc.getElementById('install-back');
			const nextBtn = dc.getElementById('install-next');
			const picker = dc.getElementById('page-language');
			if( backBtn !== null ) backBtn.disabled = busy;
			if( nextBtn !== null ) nextBtn.disabled = busy;
			if( picker !== null ) picker.disabled = busy;
		},

		/**
		 *	Show one step by index: swap the visible pane, update the nav's
		 *	progress highlight and the Back/Next buttons' visibility, and
		 *	let the step's own module load/refresh itself. The focus goes to the
		 *	heading of the step, as it does after any change of view
		 *
		 *	@param		{number}	index
		 *	@param		{boolean}	[focus]				Whether the focus goes to the heading of the step: not for the first, which is the page itself
		 *
		 *	@return		void
		 */
		showStep : function( index, focus ) {

			const step = Nino.install.STEPS[index];
			if( step === undefined )
				return;

			Nino.install._index = index;
			Nino.install.say('');

			// Swap the state class without touching the rest: the shell also
			// carries its design-system classes (see _admin/assets/style.css), and
			// assigning className outright dropped them - taking the whole
			// shared layout with them the first time a tab was clicked
			Nino.adminUi.setStateClass( dc.getElementById('install-page-wrap'), step.paneClass );

			/*	Which step the wizard is on, said as well as drawn. The rail is a
				progress display (see page-wizard.tpl), and progress shown only by
				the colour of one of six words is progress an operator who cannot
				see it has no way of reading. aria-current="step" is the attribute
				for exactly this, and it is removed from the other five rather
				than set to "false" - five steps saying "not this one" is five
				things to listen past	*/
			Nino.install.STEPS.forEach( function( s, i ) {

				const nav = dc.getElementById('install-nav-'+ s.key);

				if( nav === null )
					return;

				nav.classList.toggle( 'active', i === index );

				if( i === index )
					nav.setAttribute( 'aria-current', 'step' );
				else
					nav.removeAttribute('aria-current');
			} );

			Nino.install._paintBar();

			const mod = Nino.install[step.key];
			if( mod !== undefined && typeof mod.showCurrent === 'function' )
				mod.showCurrent();

			if( focus === true ) {
				const heading = dc.getElementById('install-content-'+ step.key).querySelector('h2');
				if( heading !== null ) {
					heading.setAttribute( 'tabindex', '-1' );
					heading.focus();
				}
			}
		},

		/**
		 *	What the bar says for the step that is shown: Back is there from the second step on, and the main button
		 *	is "Next" - on the last step it ends the wizard
		 *
		 *	@return		void
		 */
		_paintBar : function() {

			const bar = Nino.install.bar;

			if( bar === null )
				return;

			const last = Nino.install._index === Nino.install.STEPS.length - 1;
			const entry = last === true && Nino.install.finish !== undefined ? Nino.install.finish.entry() : null;

			bar.secondary.classList.toggle( 'install-hidden', Nino.install._index === 0 );
			bar.secondary.textContent = Nino.install.t('/_admin/install/label/back');

			// The last step's button ends the wizard, and is named for the application it leads to where there is exactly one
			bar.primary.textContent = entry !== null ? Nino.install.t( '/_admin/install/finish/set-up', entry.name ) : Nino.install.t( last === true ? '/_admin/install/finish/submit' : '/_admin/install/label/next' );

			if( entry !== null )
				bar.primary.dataset.application = entry.key;
			else
				delete bar.primary.dataset.application;
		},

		back : function() {

			if( Nino.install._busy === true )
				return;

			if( Nino.install._index > 0 )
				Nino.install.showStep( Nino.install._index - 1, true );
		},

		next : function() {

			if( Nino.install._busy === true )
				return;

			const currentIndex = Nino.install._index;
			const step 		= Nino.install.STEPS[currentIndex];

			Nino.install._setBusy( true );
			Nino.install._said = '';

			Nino.install._commitStep( step.key, function( ok, message ) {

				Nino.install._setBusy( false );

				if( ok === false ) {
					Nino.install.say( message || Nino.install._said || Nino.install.t('/_admin/install/error/step'), true );
					return;
				}

				// The last step ends the wizard itself: there is no step after it to show
				if( currentIndex + 1 < Nino.install.STEPS.length )
					Nino.install.showStep( currentIndex + 1, true );
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
				return callback( false, Nino.install.t('/_admin/install/checks/running') );

			if( key === 'setup' && Nino.install.setup !== undefined )
				return Nino.install.setup.apply( function( ok ) { callback( ok ) } );

			if( key === 'personalinfos' && Nino.install.personalinfos !== undefined )
				return Nino.install.personalinfos.save( function( ok ) { callback( ok ) } );

			if( key === 'accounts' && Nino.install.accounts !== undefined )
				return Nino.install.accounts.checkCanAdvance( callback );

			if( key === 'applications' && Nino.install.applications !== undefined )
				return Nino.install.applications.apply( callback );

			if( key === 'finish' && Nino.install.finish !== undefined )
				return Nino.install.finish.submit( callback );

			callback( true );
		},

		/**
		 *	Call an install/* wizard action. An answer that says the session is gone - from the first account on every action
		 *	is the session's (see session.js) - is not handed to the caller: the page asks for the token and, where nobody is
		 *	signed in, for the account, and sends the request again - three times at most for one request: a session that is
		 *	still gone after that is the answer
		 *
		 *	@param		{string}		action				Action name (eg. "checks/run")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *	@param		{number}		[round]				How often the request was sent before: 0 for the first
		 *
		 *	@return		void
		 */
		apiCall : function( action, payload, callback, round ) {

			const sent = typeof round === 'number' ? round : 0;
			const session = Nino.install.session;

			Nino.http.sendRequest( Nino.dir+ '/_admin/', 'POST', function( xhr ) {

				if( session === undefined || sent >= 3 || session.lost( xhr.status, xhr.responseJSON ) === false ) {
					callback( xhr.status, xhr.responseJSON );
					return;
				}

				// A token that is not the session's may be mended by the one the server holds; a session that is not there wants the account
				session.resume( xhr.status === 401, function( resumed ) {

					if( resumed === false ) {
						callback( xhr.status, xhr.responseJSON );
						return;
					}

					Nino.install.apiCall( action, payload, callback, sent + 1 );
				} );
			}, { action : action, data : JSON.stringify( payload ) } );
		},

		/**
		 *	Show a failed request in a container, in place of what was to be shown there: the sentence of the
		 *	refusal in the language of the page
		 *
		 *	@param		{Element}		container
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		showError : function( container, status, response ) {
			container.innerHTML = '';
			container.appendChild( Nino.adminUi.el( 'p', 'nino-admin-error', Nino.install.failure( status, response ) ) );
		},

		/**
		 *	The language was changed: the words the template carries are written by pagetext.js, and each step
		 *	that drew words by hand draws them again, with what was typed kept
		 *
		 *	@return		void
		 */
		retext : function() {

			Nino.install._paintBar();

			Nino.install.STEPS.forEach( function( step ) {

				const mod = Nino.install[step.key];

				if( mod !== undefined && typeof mod.retext === 'function' )
					mod.retext();
			} );
		},

		/**
		 *	Wire up the wizard shell: the bar at the foot, the language and the first step's load
		 *
		 *	@return		void
		 */
		onReady : function() {

			const wrap = dc.getElementById('install-page-wrap');
			if( wrap === null )
				return;

			const back = Nino.adminUi.button( '', Nino.install.back, { kind : 'secondary', id : 'install-back' } );
			const next = Nino.adminUi.button( '', Nino.install.next, { kind : 'primary', id : 'install-next' } );
			const bar = Nino.adminUi.actionBar( { id : 'install-actions-wrap', status : { fresh : true }, secondary : [ back ], primary : next } );

			wrap.appendChild( bar.el );
			Nino.install.bar = { el : bar.el, line : bar.line, secondary : back, primary : next };
			Nino.pageText.onChange( Nino.install.retext );

			Nino.install.showStep( 0, false );
		},
	} );

	Nino.events.bindCallback( 'ready', Nino.install.onReady );

})(window, document, document.documentElement, document.body);
