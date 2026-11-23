/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 4: create the first _admin account(s). See
 *													_admin/install/Install.php's Accounts class. "Create admin" stays
 *													its own repeatable action (unlike every step before it, more than
 *													one submit here is the normal case) - only the
 *													precondition for the shared Next button (at least one
 *													account exists) is exposed, as checkCanAdvance(). What is typed
 *													into the form when Next is pressed is the account the person means
 *													to create: it is created first, as Enter in a field does.
 *													The form is made of the blocks of the workbench, and what is wrong
 *													is said at the field it is about.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.accounts = {

		// The accounts the server named last, which a change of the language draws again
		_users : null,

		// The parts of the form that are on the page: { form, legend, button, mail, pw, pw2, current } - the labels of the fields
		_ui : null,

		showCurrent : function() {

			if( Nino.install.accounts._ui === null )
				Nino.install.accounts._draw();

			Nino.install.accounts._refreshList();
		},

		retext : function() {
			if( Nino.install.accounts._ui !== null )
				Nino.pageText.keep( dc.getElementById('accounts-content'), Nino.install.accounts._draw );
		},

		/**
		 *	Draw the form of the step, and the list of the accounts again where it is known
		 *
		 *	@return		void
		 */
		_draw : function() {

			const t = Nino.install.t;
			const self = Nino.install.accounts;
			const wrap = dc.getElementById('accounts-content');

			wrap.innerHTML = '';

			const list = Nino.adminUi.el( 'div', '' );
			list.id = 'accounts-list';

			const form = Nino.adminUi.el( 'form', '' );
			form.id = 'accounts-add-form';
			form.noValidate = true;

			const card = Nino.adminUi.el( 'fieldset', '' );
			const legend = Nino.adminUi.el( 'legend', '', t('/_admin/install/accounts/title') );
			card.appendChild( legend );
			Nino.adminUi.sectionHelp( legend, card, t('/_admin/install/accounts/help') );

			const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );
			const mail = Nino.adminUi.textField( { key : 'accounts-mail', id : 'accounts-add-mail', type : 'email', autocomplete : 'off', required : true, label : t('/_admin/install/accounts/mail') } );
			const pw = Nino.adminUi.textField( { key : 'accounts-pw', id : 'accounts-add-pw', type : 'password', autocomplete : 'new-password', required : true, label : t('/_admin/install/accounts/pw'), help : t( '/_admin/install/accounts/pw-help', Nino.install.minPassword() ) } );
			const pw2 = Nino.adminUi.textField( { key : 'accounts-pw2', id : 'accounts-add-pw2', type : 'password', autocomplete : 'new-password', required : true, label : t('/_admin/install/accounts/pw2') } );

			// Asked only where an account exists, and then of the account of the session: it is what changing one's own account asks for
			const current = Nino.adminUi.textField( { key : 'accounts-current', id : 'accounts-add-current', type : 'password', autocomplete : 'current-password', required : true, label : t('/_admin/install/accounts/current') } );
			Nino.install.accounts._show( current, false );

			grid.appendChild( mail );
			grid.appendChild( current );
			grid.appendChild( pw );
			grid.appendChild( pw2 );
			card.appendChild( grid );

			// The step's own action, repeatable: the account is made and the form is ready for the next. Enter in a field does the same
			const actions = Nino.adminUi.el( 'div', 'nino-admin-actions nino-admin-actions--end' );
			const button = Nino.adminUi.button( t('/_admin/install/accounts/create'), function() { self._create( function() {} ) }, { kind : 'secondary', id : 'accounts-add-submit' } );
			actions.appendChild( button );
			card.appendChild( actions );

			form.appendChild( card );
			form.addEventListener( 'keydown', function( ev ) {
				if( ev.key === 'Enter' && ev.target !== null && ev.target.tagName === 'INPUT' ) {
					ev.preventDefault();
					self._create( function() {} );
				}
			} );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault() } );

			wrap.appendChild( list );
			wrap.appendChild( form );

			self._ui = { form : form, legend : legend, button : button, mail : mail, pw : pw, pw2 : pw2, current : current };

			if( self._users !== null )
				self._render( self._users );
		},

		/**
		 *	The shared Next button's precondition for this step - refreshes
		 *	the account list first, so it's accurate even if this is the
		 *	very first time the step has been shown. An account that is typed and not yet made is made first
		 *
		 *	@param		{Function}	callback			Called with ( canAdvance, message )
		 *
		 *	@return		void
		 */
		checkCanAdvance : function( callback ) {

			const self = Nino.install.accounts;

			if( self._typed() === true ) {
				self._create( callback );
				return;
			}

			Nino.install.apiCall( 'accounts/list', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					callback( false, Nino.install.failure( status, response ) );
					return;
				}

				self._render( response.users );

				callback( response.users.length > 0, Nino.install.t('/_admin/install/accounts/none-yet') );
			} );
		},

		/**
		 *	Whether anything is typed into the form
		 *
		 *	@return		{boolean}
		 */
		_typed : function() {

			const ui = Nino.install.accounts._ui;

			return ui !== null && [ ui.mail, ui.pw, ui.pw2, ui.current ].some( function( field ) { return field.querySelector('input').value !== '' } );
		},

		/**
		 *	Show a field of the form or take it away: the attribute and the display both, since the block of a field sets its own display and a hidden attribute alone does not hide it
		 *
		 *	@param		{Element}		field
		 *	@param		{boolean}		shown
		 *
		 *	@return		void
		 */
		_show : function( field, shown ) {
			field.hidden = shown === false;
			field.style.display = shown === true ? '' : 'none';
		},

		_refreshList : function() {

			const wrap = dc.getElementById('accounts-list');
			if( wrap === null )
				return;

			Nino.install.apiCall( 'accounts/list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );
				Nino.install.accounts._render( response.users );
			} );
		},

		/**
		 *	@param		{Array}		users		Mail addresses
		 *
		 *	@return		void
		 */
		_render : function( users ) {

			const t = Nino.install.t;
			const wrap = dc.getElementById('accounts-list');

			Nino.install.accounts._users = users;
			wrap.innerHTML = '';

			// From the first account on the form changes the account of the session (the wizard makes no other): it is said, and the current password is asked
			const ui = Nino.install.accounts._ui;
			if( ui !== null ) {
				ui.legend.firstChild.textContent = t( users.length > 0 ? '/_admin/install/accounts/title-own' : '/_admin/install/accounts/title' );
				ui.button.textContent = t( users.length > 0 ? '/_admin/install/accounts/change' : '/_admin/install/accounts/create' );
				Nino.install.accounts._show( ui.current, users.length > 0 );
			}

			if( users.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( t('/_admin/install/accounts/none') ) );
				return;
			}

			// Rows that are read rather than opened: the list of the workbench
			wrap.appendChild( Nino.adminUi.list( users.map( function( mail ) { return { key : mail, title : mail } } ), { title : t('/_admin/install/accounts/created') } ) );
		},

		/**
		 *	Make the account that is typed: checked at the fields first - what is wrong is said where it is, and the focus
		 *	goes to the first - then sent
		 *
		 *	@param		{Function}	done					Called with ( ok, sentence ) once it is made or refused: the sentence is what the bar says of a refusal, the one of the server's code among them
		 *
		 *	@return		void
		 */
		_create : function( done ) {

			const t = Nino.install.t;
			const ui = Nino.install.accounts._ui;
			const mail = ui.mail.querySelector('input');
			const pw = ui.pw.querySelector('input');
			const pw2 = ui.pw2.querySelector('input');
			const current = ui.current.querySelector('input');
			const missing = function( key ) { return Nino.adminUi.format( t('/_admin/common/error/missing'), t( key ) ) };

			Nino.adminUi.clearFieldErrors( ui.form );
			Nino.install.say('');

			const min = Nino.install.minPassword();
			const problems = [
				[ mail.value.trim() === '' ? missing('/_admin/install/accounts/mail') : '', ui.mail, mail ],
				[ pw.value === '' ? missing('/_admin/install/accounts/pw') : ( pw.value.length < min ? t( '/_admin/install/error/password-short', min ) : '' ), ui.pw, pw ],
				[ pw2.value !== pw.value ? t('/_admin/install/error/mismatch') : ( pw2.value === '' ? missing('/_admin/install/accounts/pw2') : '' ), ui.pw2, pw2 ],
				[ ui.current.hidden === false && current.value === '' ? missing('/_admin/install/accounts/current') : '', ui.current, current ],
			].filter( function( problem ) { return problem[0] !== '' } );

			if( problems.length > 0 ) {
				problems.forEach( function( problem, at ) { Nino.adminUi.fieldError( problem[1], problem[2], 'accounts-error-'+ at, problem[0] ) } );
				Nino.install.say( t('/_admin/install/error/fields'), true );
				problems[0][2].focus();
				done( false, t('/_admin/install/error/fields') );
				return;
			}

			Nino.install.say( t('/_admin/install/accounts/creating') );

			Nino.install.apiCall( 'accounts/create', { mail : mail.value, pw : pw.value, currentPassword : current.value }, function( status, response ) {
				if( status !== 200 || response === null ) {

					const code = response !== null && typeof response === 'object' && typeof response.code === 'string' ? response.code : '';
					const field = code === 'install_mail' || code === 'install_mail_in_use' ? [ ui.mail, mail ] : ( code === 'install_password_short' ? [ ui.pw, pw ] : ( code === 'install_wrong_password' ? [ ui.current, current ] : null ) );

					// A refusal about a value is said at the field of it, and the line says that something is to be fixed; any other is said in the line, as the server's code is worded
					const sentence = field !== null ? t('/_admin/install/error/fields') : Nino.install.failure( status, response );

					if( field !== null ) {
						Nino.adminUi.fieldError( field[0], field[1], 'accounts-error-0', Nino.install.failure( status, response ) );
						field[1].focus();
					}

					Nino.install.say( sentence, true );
					done( false, sentence );
					return;
				}

				// The server signed the account in and rotated the csrf token
				// with it; the [csrf] field is what Nino.http sends from now
				// on, so it takes the new one
				const csrf = dc.querySelector( 'input[name="_csrf"]' );
				if( csrf !== null && typeof response.csrf === 'string' )
					csrf.value = response.csrf;

				Nino.install.say('');
				Nino.adminUi.say( t('/_admin/install/accounts/created-msg') );
				if( Nino.install.session !== undefined && typeof response.user === 'string' )
					Nino.install.session.remember( response.user );

				mail.value = '';
				pw.value = '';
				pw2.value = '';
				current.value = '';
				mail.focus();
				Nino.install.accounts._render( response.users );
				done( true );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
