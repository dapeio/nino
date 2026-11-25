/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 6, the last one: set the recovery password. Success
 *													here is what locks the wizard back out for good - see
 *													_admin/install/Install.php's Finish class. A one-account
 *													setup goes straight on - into the entry point of the one
 *													application that has one, else into the workbench - signed
 *													in as that account; any other, and one with several entry
 *													points, shows where to continue. An application
 *													that starts the workbench with its menu folded
 *													(menu: folded) leaves the menu folded where the
 *													browser has no choice of its own yet. Where the server
 *													answers with the recovery key - the key the project's
 *													backups are encrypted with - the page shows it first,
 *													once, and goes on only after it was confirmed saved.
 *
 *													The one main button is the bar's, named for what it leads to:
 *													the step has no button of its own besides the key's "Copy" and
 *													"Save as text file". The forms are the blocks of the workbench.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.finish = {

		// The key the workbench keeps the state of its menu under (Nino.admin.rail.STORAGE_KEY)
		RAIL_KEY : 'nino-admin-rail',

		// The parts of the form that are on the page: { form, pw, pw2 }
		_ui : null,

		// The key while it is shown, and what the end of the wizard does once it was saved: { key, proceed } - null otherwise,
		// so that nothing of the key is kept anywhere once the person went on
		_shown : null,

		// The next steps the page offers beyond the two it always has, kept so that a change of the language words them again
		_targetList : [],

		showCurrent : function() {

			if( Nino.install.finish._ui === null )
				Nino.install.finish._draw();
		},

		retext : function() {

			const self = Nino.install.finish;

			if( self._ui !== null )
				Nino.pageText.keep( dc.getElementById('finish-content'), self._draw );

			// The card of the key stands beside the form of the password, not in it: its tick and its sentence are kept the same way
			if( self._shown !== null )
				Nino.pageText.keep( dc.getElementById('finish-key-wrap'), self._drawKey );

			self._drawTargets();
		},

		/**
		 *	The application the button that ends the wizard is named for: with exactly one application that went through and has an
		 *	entry point it is that one and the button leads there; otherwise the wizard ends and says so
		 *
		 *	@return		{Object|null}									{ key, name } or null
		 */
		entry : function() {

			const entries = Nino.install.applications !== undefined ? Nino.install.applications.appliedEntries() : [];

			return entries.length === 1 ? entries[0] : null;
		},

		/**
		 *	The form of the step: the recovery password, twice
		 *
		 *	@return		void
		 */
		_draw : function() {

			const t = Nino.install.t;
			const self = Nino.install.finish;
			const wrap = dc.getElementById('finish-content');

			wrap.innerHTML = '';

			const form = Nino.adminUi.el( 'form', '' );
			form.id = 'finish-form';
			form.noValidate = true;

			const card = Nino.adminUi.el( 'fieldset', '' );
			const legend = Nino.adminUi.el( 'legend', '', t('/_admin/install/finish/title') );
			card.appendChild( legend );
			Nino.adminUi.sectionHelp( legend, card, t('/_admin/install/finish/help') );

			const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );
			const pw = Nino.adminUi.textField( { key : 'finish-pw', id : 'finish-pw', type : 'password', autocomplete : 'new-password', required : true, label : t('/_admin/install/finish/pw'), help : t( '/_admin/install/finish/pw-help', Nino.install.minPassword(), Nino.install.maxPassword() ) } );
			const pw2 = Nino.adminUi.textField( { key : 'finish-pw2', id : 'finish-pw2', type : 'password', autocomplete : 'new-password', required : true, label : t('/_admin/install/finish/pw2') } );

			grid.appendChild( pw );
			grid.appendChild( pw2 );
			card.appendChild( grid );
			form.appendChild( card );

			// Enter in a field is the main button of the step: the one of the bar
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.install.next() } );
			form.addEventListener( 'keydown', function( ev ) {
				if( ev.key === 'Enter' && ev.target !== null && ev.target.tagName === 'INPUT' ) {
					ev.preventDefault();
					Nino.install.next();
				}
			} );

			wrap.appendChild( form );
			self._ui = { form : form, pw : pw, pw2 : pw2 };
		},

		/**
		 *	Go to a path of the project. The wizard is at /_admin/, and an entry
		 *	point that differs from it in the fragment alone (/_admin/#builder) is
		 *	no navigation - the page would stay where it is - so it is loaded anew
		 *
		 *	@param		{string}	path
		 *
		 *	@return		void
		 */
		_go : function( path ) {

			const target = new URL( path, wn.location.href );
			const sameDocument = target.pathname+ target.search === wn.location.pathname+ wn.location.search;

			wn.location.href = target.href;

			if( sameDocument === true && target.hash !== '' )
				wn.location.reload();
		},

		/**
		 *	Fold the workbench's menu for the application that is about to be
		 *	opened, where it asks for that - once, as the state the workbench
		 *	starts in. The wizard and the workbench are one origin, and the
		 *	menu's state is the one value the workbench keeps in the browser
		 *	(Nino.admin.rail, _admin/assets/script.js): a person's own choice,
		 *	which is there already, stays
		 *
		 *	@param		{Object}	target			{ key, name, next [, menu] }
		 *
		 *	@return		void
		 */
		_menu : function( target ) {

			if( target.menu !== 'folded' )
				return;

			try {
				if( !wn.localStorage.getItem( Nino.install.finish.RAIL_KEY ) )
					wn.localStorage.setItem( Nino.install.finish.RAIL_KEY, 'folded' );
			} catch(e) {}
		},

		/**
		 *	One more next step per application that has an entry point, beside
		 *	the two the page always offers
		 *
		 *	@param		{Array}		targets			[ { key, name, next [, menu] } ]
		 *
		 *	@return		void
		 */
		_targets : function( targets ) {

			Nino.install.finish._targetList = targets;
			Nino.install.finish._drawTargets();
		},

		_drawTargets : function() {

			const wrap = dc.getElementById('finish-next-steps');

			Array.prototype.slice.call( wrap.querySelectorAll('[data-application]') ).forEach( function( el ) { el.remove() } );

			Nino.install.finish._targetList.forEach( function( target ) {

				const link = dc.createElement('a');
				link.className = 'install-next-step install-next-step--application';
				link.href = Nino.dir+ target.next;
				link.target = '_blank';
				link.addEventListener( 'click', function() { Nino.install.finish._menu( target ) } );

				const copy = dc.createElement('span');
				copy.className = 'install-next-step-copy';

				const name = dc.createElement('strong');
				name.textContent = Nino.install.t( '/_admin/install/finish/set-up', target.name );
				copy.appendChild( name );
				link.dataset.application = target.key;

				link.appendChild( copy );
				wrap.appendChild( link );
			} );
		},

		/**
		 *	The recovery key, once: the text itself, the two ways to keep it and the tick that says it was kept. Drawn
		 *	from what is shown, so that a change of the language words it again without the key going anywhere
		 *
		 *	@return		void
		 */
		_drawKey : function() {

			const t = Nino.install.t;
			const self = Nino.install.finish;
			const shown = self._shown;
			const wrap = dc.getElementById('finish-key-wrap');

			wrap.innerHTML = '';

			const form = Nino.adminUi.el( 'form', '' );
			form.id = 'finish-key';
			form.noValidate = true;

			const card = Nino.adminUi.el( 'fieldset', '' );
			const legend = Nino.adminUi.el( 'legend', '', t('/_admin/install/finish/key-title') );
			card.appendChild( legend );
			Nino.adminUi.sectionHelp( legend, card, t('/_admin/install/finish/key-help') );

			// What has to stay in view: it is shown only now
			card.appendChild( Nino.adminUi.notice( t('/_admin/install/finish/key-once') ) );

			const label = Nino.adminUi.el( 'p', 'install-key-label', t('/_admin/install/finish/key-label') );
			label.id = 'finish-key-label';
			const value = Nino.adminUi.el( 'code', 'install-key-value' );
			value.id = 'finish-key-value';
			value.tabIndex = 0;
			value.setAttribute( 'aria-labelledby', 'finish-key-label' );
			value.textContent = shown.key;

			card.appendChild( label );
			card.appendChild( value );

			const line = Nino.adminUi.status( Nino.adminUi.el('p') );
			line.element.id = 'finish-key-msg';

			const copy = Nino.adminUi.button( t('/_admin/install/finish/key-copy'), function() {

				const failed = t('/_admin/install/finish/key-copy-failed');

				if( wn.navigator && wn.navigator.clipboard && typeof wn.navigator.clipboard.writeText === 'function' )
					wn.navigator.clipboard.writeText( shown.key ).then( function() { Nino.adminUi.say( t('/_admin/install/finish/key-copied') ) }, function() { line.fail( failed ) } );
				else
					line.fail( failed );
			}, { kind : 'secondary', id : 'finish-key-copy' } );

			const save = Nino.adminUi.button( t('/_admin/install/finish/key-save'), function() {

				// Made in the browser from what is on the screen; the link is gone again at once
				const url = wn.URL.createObjectURL( new wn.Blob( [ shown.key+ '\n' ], { type : 'text/plain' } ) );
				const link = dc.createElement('a');
				link.href = url;
				link.download = 'nino-recovery-key-'+ ( wn.location.hostname || 'project' ).replace( /[^a-z0-9.-]/gi, '' )+ '.txt';
				link.click();
				wn.URL.revokeObjectURL( url );
				line.idle( t('/_admin/install/finish/key-saved') );
			}, { kind : 'secondary', id : 'finish-key-save' } );

			const actions = Nino.adminUi.el( 'div', 'nino-admin-actions' );
			actions.appendChild( copy );
			actions.appendChild( save );
			card.appendChild( actions );
			card.appendChild( line.element );

			const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );
			const ack = Nino.adminUi.checkField( { key : 'finish-key-ack', id : 'finish-key-ack', required : true, label : t('/_admin/install/finish/key-ack') } );
			grid.appendChild( ack );
			card.appendChild( grid );

			const go = Nino.adminUi.button( t('/_admin/install/finish/key-continue'), null, { kind : 'primary', type : 'submit', id : 'finish-key-continue' } );
			const foot = Nino.adminUi.el( 'div', 'nino-admin-actions nino-admin-actions--end' );
			foot.appendChild( go );
			card.appendChild( foot );

			form.appendChild( card );

			form.addEventListener( 'submit', function( ev ) {

				ev.preventDefault();
				Nino.adminUi.clearFieldErrors( form );

				// The one thing left to say: it is asked for at the tick, and the focus goes there
				if( ack.fieldControl.checked !== true ) {
					Nino.adminUi.fieldError( ack, ack.fieldControl, 'finish-key-error', t('/_admin/install/finish/key-ack-error') );
					ack.fieldControl.focus();
					return;
				}

				// Gone from the page, and from what the clipboard and the file buttons would give
				wn.onbeforeunload = null;
				value.textContent = '';
				wrap.innerHTML = '';
				self._shown = null;
				shown.proceed();
			} );

			wrap.appendChild( form );
		},

		/**
		 *	Show the recovery key, once, and hand over to the end of the wizard only after the person
		 *	said it is saved. Nothing of it is kept anywhere but on this screen: not in the storage
		 *	of the browser, not in an address, not in a field that is sent. The text file is made
		 *	here, in the browser, from the text on the screen. When the person goes on the key leaves
		 *	the page, and until then a page that is closed or left asks first
		 *
		 *	@param		{string}		key							The key as the server answered it
		 *	@param		{Function}	proceed					What the end of the wizard does once the key is saved
		 *
		 *	@return		void
		 */
		_showKey : function( key, proceed ) {

			const self = Nino.install.finish;

			self._shown = { key : key, proceed : proceed };
			dc.getElementById('install-page-wrap').classList.add('is-complete');
			self._drawKey();

			wn.onbeforeunload = function( ev ) {
				ev.preventDefault();
				ev.returnValue = '';
				return '';
			};

			dc.getElementById('finish-key-ack').focus();
		},

		/**
		 *	The step's one main button, the bar's: check the two passwords at their fields, then end the wizard
		 *
		 *	@param		{Function}	callback			Called with ( ok, message ) once the server answered
		 *
		 *	@return		void
		 */
		submit : function( callback ) {

			const t = Nino.install.t;
			const self = Nino.install.finish;
			const ui = self._ui;
			const pw = ui.pw.querySelector('input');
			const pw2 = ui.pw2.querySelector('input');
			const missing = function( key ) { return Nino.adminUi.format( t('/_admin/common/error/missing'), t( key ) ) };
			const min = Nino.install.minPassword();

			Nino.adminUi.clearFieldErrors( ui.form );

			const problems = [
				[ pw.value === '' ? missing('/_admin/install/finish/pw') : ( pw.value.length < min ? t( '/_admin/install/error/password-short', min ) : '' ), ui.pw, pw ],
				[ pw2.value !== pw.value ? t('/_admin/install/error/mismatch') : ( pw2.value === '' ? missing('/_admin/install/finish/pw2') : '' ), ui.pw2, pw2 ],
			].filter( function( problem ) { return problem[0] !== '' } );

			if( problems.length > 0 ) {
				problems.forEach( function( problem, at ) { Nino.adminUi.fieldError( problem[1], problem[2], 'finish-error-'+ at, problem[0] ) } );
				problems[0][2].focus();
				callback( false, t('/_admin/install/error/fields') );
				return;
			}

			Nino.install.say( t('/_admin/install/step/saving') );

			// The applications that went through the last step: the server believes
			// them only as far as their features are on
			const applications = Nino.install.applications !== undefined ? Nino.install.applications.applied() : [];

			Nino.install.apiCall( 'finish/complete', { password : pw.value, applications : applications, lang : Nino.pageText.language() }, function( status, response ) {
				if( status !== 200 || response === null ) {

					const code = response !== null && typeof response === 'object' && typeof response.code === 'string' ? response.code : '';

					if( [ 'install_password_short', 'install_password_long', 'install_password_invalid' ].indexOf( code ) !== -1 ) {
						Nino.adminUi.fieldError( ui.pw, pw, 'finish-error-0', Nino.install.failure( status, response ) );
						pw.focus();
						callback( false, t('/_admin/install/error/fields') );
						return;
					}

					callback( false, Nino.install.failure( status, response ) );
					return;
				}

				const targets = Array.isArray( response.targets ) ? response.targets : [];

				const proceed = function() {
					// The server kept this session signed in - the one account the
					// wizard created - so the next page is not a choice: the entry point
					// of the one application that has one, else the workbench. With
					// several accounts it ended the session, and with several entry
					// points there is a choice; the panel below offers them, and the
					// login, among the next steps
					if( response.login === true && targets.length <= 1 ) {
						Nino.install.say( targets.length === 1 ? '' : t('/_admin/install/finish/opening') );
						if( targets.length === 1 )
							self._menu( targets[0] );
						self._go( Nino.dir+ ( targets.length === 1 ? targets[0].next : '/_admin/' ) );
						return;
					}

					self._targets( targets );

					// Cleared, not left on "Saving …" - the done panel below replaces
					// the form but not the line
					Nino.install.say('');

					dc.getElementById('finish-content').classList.add('install-hidden');
					dc.getElementById('finish-done').classList.remove('install-hidden');
					dc.getElementById('install-page-wrap').classList.add('is-complete');

					const heading = dc.getElementById('finish-done').querySelector('h2');
					if( heading !== null ) {
						heading.setAttribute( 'tabindex', '-1' );
						heading.focus();
					}
				};

				// The recovery key comes first: the end of the wizard, whichever it is, waits for it
				if( typeof response.recoveryKey === 'string' && response.recoveryKey !== '' ) {
					Nino.install.say('');
					dc.getElementById('finish-content').classList.add('install-hidden');
					self._showKey( response.recoveryKey, proceed );
					callback( true );
					return;
				}

				proceed();
				callback( true );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
