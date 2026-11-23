/**
 *	Nino										A compact filesystembased php framework
 *	Install									The session of the wizard. Until the first account is made the
 *													wizard is open; from then on every action asks for the session of
 *													an account (see \Nino\Install\Install::authorize()), and this is what
 *													the page does where it has none: a page that outlived its session -
 *													reloaded after the account step, the cookie gone, the sign-in run
 *													out - asks the server whose session this is and what its token is,
 *													and where nobody is signed in it asks for the account's mail and
 *													password in the dialog of the workbench and sends the request that
 *													was refused again. The step that was on goes on where it was.
 *
 *													The server's two ways of saying "no session" are the workbench's
 *													own: 401 with the code 'session', and 403 with the code 'csrf'
 *													for a token that is not the one of this session (see
 *													Nino.adminUi.api). Nino.install.apiCall() hands a request refused
 *													in one of them to resume().
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.session = {

		// The dialog while it is open: { handle, form, user, pw, waiting }, else null
		_shown : null,

		// The account this page is for, once it knows another than the one it was drawn for ('' for none); null until then
		_known : null,

		/**
		 *	The account this page is for: the one of the session it was drawn for (the page carries it), or the one the account step
		 *	made or changed, or the one that signed in since
		 *
		 *	@return		{string}
		 */
		account : function() {

			if( Nino.install.session._known !== null )
				return Nino.install.session._known;

			const wrap = dc.getElementById('install-page-wrap');

			return wrap !== null && typeof wrap.dataset.account === 'string' ? wrap.dataset.account : '';
		},

		/**
		 *	Take an account for the one this page is for
		 *
		 *	@param		{string}		mail
		 *
		 *	@return		void
		 */
		remember : function( mail ) {
			Nino.install.session._known = typeof mail === 'string' ? mail : '';
		},

		/**
		 *	Whether the session is another account's than the one this page is for, which is what the workbench's page calls 'other': a
		 *	request that was refused is not sent as another account - what was typed here was typed for the first one - and the page
		 *	is loaded anew, which draws it for the account that is signed in
		 *
		 *	@param		{string}		user					The account of the session now
		 *
		 *	@return		{boolean}									true where it is another, and the page is being loaded anew
		 */
		changed : function( user ) {

			if( user === '' || user === Nino.install.session.account() )
				return false;

			wn.location.reload();

			return true;
		},

		/**
		 *	Whether an answer is the end of the session rather than a refusal of the action
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		{boolean}
		 */
		lost : function( status, response ) {

			if( response === null || typeof response !== 'object' )
				return false;

			return ( status === 401 && response.code === 'session' ) || ( status === 403 && response.code === 'csrf' );
		},

		/**
		 *	Ask the server whose session this is and take its token into the page's csrf field - the one every
		 *	request of the wizard is sent with
		 *
		 *	@param		{Function}	callback			Called with ( info ): { user, csrf }, null where the server gave none
		 *
		 *	@return		void
		 */
		renew : function( callback ) {
			Nino.adminUi.api.renew( callback );
		},

		/**
		 *	Go on after a refusal for the session: the token is asked for again, and where nobody is signed in - and the
		 *	refusal said so - the dialog asks for the account
		 *
		 *	@param		{boolean}		signIn				Whether a session that is not there is to be asked for: false after a refusal of the token alone, which the new token may mend
		 *	@param		{Function}	callback			Called with ( true ) where the request may be sent again, ( false ) where it may not
		 *
		 *	@return		void
		 */
		resume : function( signIn, callback ) {

			Nino.install.session.renew( function( info ) {

				if( info === null ) {
					callback( false );
					return;
				}

				if( info.user !== '' && Nino.install.session.changed( info.user ) === true ) {
					callback( false );
					return;
				}

				if( info.user !== '' || signIn === false ) {
					callback( true );
					return;
				}

				Nino.install.session.ask( callback );
			} );
		},

		/**
		 *	Ask for the account in the dialog, once however many requests wait for it: each of them is called with whether
		 *	somebody signed in
		 *
		 *	@param		{Function}	callback			Called with ( signedIn )
		 *
		 *	@return		void
		 */
		ask : function( callback ) {

			const self = Nino.install.session;

			if( self._shown !== null ) {
				self._shown.waiting.push( callback );
				return;
			}

			const t = Nino.install.t;
			const form = Nino.adminUi.el( 'form', 'nino-admin-dialog-form' );
			form.noValidate = true;
			form.appendChild( Nino.adminUi.el( 'p', '', t('/_admin/install/session/message') ) );

			const fields = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );
			const user = Nino.adminUi.textField( { key : 'user', id : 'install-session-user', type : 'email', autocomplete : 'username', required : true, label : t('/_admin/install/session/user') } );
			const pw = Nino.adminUi.textField( { key : 'pw', id : 'install-session-pw', type : 'password', autocomplete : 'current-password', required : true, label : t('/_admin/install/session/pw') } );
			fields.appendChild( user );
			fields.appendChild( pw );
			form.appendChild( fields );

			const shown = { handle : null, form : form, user : user, pw : pw, waiting : [ callback ], signedIn : false };

			// Enter in a field is the answer that goes on
			form.addEventListener( 'keydown', function( ev ) {
				if( ev.key === 'Enter' && ev.target !== null && ev.target.tagName === 'INPUT' ) {
					ev.preventDefault();
					shown.handle.close( 'login', true );
				}
			} );

			const opened = Nino.adminUi.choiceDialog( {
				title		: t('/_admin/install/session/title'),
				message	: t('/_admin/install/session/message'),
				content	: form,
				size		: 'form',
				nested	: true,
				refuseWithoutDialog : true,
				focus		: user.querySelector('input'),
				choices	: [
					{ value : 'close', label : t('/_admin/install/session/close'), kind : 'secondary' },
					{ value : 'login', label : t('/_admin/install/session/submit'), kind : 'primary' },
				],
				guard		: function( value, handle ) {

					if( value === 'login' ) {
						self._login( shown );
						return false;
					}

					return true;
				},
				onOpen	: function( handle ) {
					shown.handle = handle;
					self._shown = shown;
				},
				onChoose : function() {

					// However it was closed, the requests that waited are told: signed in, or not
					self._shown = null;
					shown.waiting.forEach( function( waiting ) { waiting( shown.signedIn ) } );
				},
			} );

			// A dialog that could not be opened (another question is open, or the browser has none): nobody can be asked
			if( opened === false && self._shown === null )
				callback( false );
		},

		/**
		 *	Sign in from the dialog. What is wrong is said at the field it is about: a field left empty at its own, the
		 *	refusal of the sign-in and the status of any other answer at the password. Signing in rotates the session and
		 *	its token, so the page asks for the token again before the requests that waited go out
		 *
		 *	@param		{Object}		shown					The open dialog
		 *
		 *	@return		void
		 */
		_login : function( shown ) {

			const t = Nino.install.t;
			const user = shown.user.querySelector('input');
			const pw = shown.pw.querySelector('input');
			const go = shown.handle.buttons.login;

			const refuse = function( field, control, sentence ) {
				Nino.adminUi.fieldError( field, control, 'install-session-error', sentence );
				control.focus();
			};
			const say = function( key, ...params ) { refuse( shown.pw, pw, t( key, ...params ) ) };

			Nino.adminUi.clearFieldErrors( shown.form );

			if( user.value === '' ) {
				refuse( shown.user, user, Nino.adminUi.format( t('/_admin/common/error/missing'), t('/_admin/install/session/user') ) );
				return;
			}

			if( pw.value === '' ) {
				refuse( shown.pw, pw, Nino.adminUi.format( t('/_admin/common/error/missing'), t('/_admin/install/session/pw') ) );
				return;
			}

			go.disabled = true;

			Nino.http.sendRequest( Nino.dir+ '/.nino/auth/login', 'POST', function( xhr ) {

				if( xhr.status !== 200 ) {

					// A 403 is a token that is no longer the session's: the next try is sent with the one the server holds now
					const again = function() {
						go.disabled = false;
						say( xhr.status === 401 ? '/_admin/install/session/wrong' : '/_admin/install/session/error', xhr.status );
					};

					if( xhr.status === 403 )
						Nino.install.session.renew( again );
					else
						again();

					return;
				}

				pw.value = '';

				Nino.install.session.renew( function( info ) {

					go.disabled = false;

					if( info === null || info.user === '' ) {
						say( '/_admin/install/session/error', xhr.status );
						return;
					}

					// Another account than the one this page is for: the page is loaded anew, and the request that waited is not sent as it
					// A page that was drawn for nobody is for whoever signs in
					shown.signedIn = Nino.install.session.account() === '' || Nino.install.session.changed( info.user ) === false;
					Nino.install.session.remember( info.user );
					shown.handle.close( 'signed-in' );
				} );
			}, {}, { user : user.value, pw : pw.value } );
		},
	};

})(window, document, document.documentElement, document.body);
