/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	login.js								The login screen's shell (templates/page-login.tpl):
 *													draws the two fields with the blocks of the workbench,
 *													checks them, submits through Nino.auth.login and says what
 *													is wrong at the field it is about, and points the locale
 *													picker at its own ?locale=xx targets. Occupies the
 *													Nino.admin slot script.js occupies in the workbench - the
 *													two are never in the same bundle (see Admin::init()).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = {

		/**
		 *	Wire up the admin login form: draw the fields, validate them, submit via
		 *	Nino.auth.login and show the pending state and what went wrong at the field
		 *
		 *	@return		void
		 */
		onReady : function() {

			// The picker's <option> values are already real ?locale=xx
			// targets (see Admin::_localePickerHtml()) - navigating there
			// is all that's needed, Admin::init() reads it back server-side
			const localePicker = dc.getElementById('admin-localepicker');
			if( localePicker !== null )
				localePicker.addEventListener( 'change', function(){ wn.location.href = this.value + wn.location.hash } );

			const form = dc.getElementById('form-login');
			const text = function( key ) { return Nino.content.getText( key ) };

			// The two fields, as every form of the workbench has them: the label at the left of its field from a width
			// that has room for that, a star behind the name of a required one
			const user = Nino.adminUi.textField( { key : 'user', id : 'input-user', type : 'email', autocomplete : 'username', required : true, label : text('/_admin/login/label/user') } );
			const pw = Nino.adminUi.textField( { key : 'pw', id : 'input-pw', type : 'password', autocomplete : 'current-password', required : true, label : text('/_admin/login/label/pw') } );
			const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );

			// The line that says the request is on its way, and the one main button
			const line = Nino.adminUi.status( Nino.adminUi.el('p') );
			line.element.id = 'form-message';
			const submit = Nino.adminUi.button( text('/_admin/login/label/submit'), null, { kind : 'primary', type : 'submit', id : 'submit' } );
			const actions = Nino.adminUi.el( 'div', 'nino-admin-actions nino-admin-actions--between' );

			grid.appendChild( user );
			grid.appendChild( pw );
			actions.appendChild( line.element );
			actions.appendChild( submit );
			dc.getElementById('login-fields').appendChild( grid );
			dc.getElementById('login-fields').appendChild( actions );

			const el = {
				inputUser : user.querySelector('input'),
				inputPw 	: pw.querySelector('input'),
			};

			/**
			 *	Say what is wrong where it is: a sentence under the field, the field marked aria-invalid and
			 *	pointed at the sentence, and the focus on it. The field is what the form says was refused and
			 *	the sentence is why - the mark is no colour alone, which a screen reader is never given and a
			 *	colour-blind operator may not see. One sentence at a time: the next attempt starts from none
			 *
			 *	@param		{Element}		field					The label of the field
			 *	@param		{Element}		control
			 *	@param		{string}		sentence
			 *
			 *	@return		void
			 */
			const refuse = function( field, control, sentence ) {

				Nino.adminUi.fieldError( field, control, 'login-error', sentence );
				control.focus();
			};

			// Catch login
			form.addEventListener( 'submit', function(e){
				e.preventDefault();

				// Every attempt starts from a clean slate: what the last one said stays until now
				Nino.adminUi.clearFieldErrors( form );
				line.idle('');

				if(el.inputUser.value.length === 0) {
					refuse( user, el.inputUser, Nino.adminUi.format( text('/_admin/common/error/missing'), text('/_admin/login/label/user') ) );
					return;
				}
				if(el.inputPw.value.length === 0) {
					refuse( pw, el.inputPw, Nino.adminUi.format( text('/_admin/common/error/missing'), text('/_admin/login/label/pw') ) );
					return;
				}

				// Login
				line.idle( text('/_admin/login/msg/pending') );
				Nino.auth.login( el.inputUser.value, el.inputPw.value, null, function( xhr ){

					// The request is over, so the pending line goes with it
					line.idle('');

					/*	401 is the only answer that means the pair was read and
							refused - that is the one this form may call a wrong
							password. Everything else never got that far: a 403 from
							the csrf guard or from a server that blocks dot-uris, a 404
							where nothing forwards an unmatched address to index.php, a
							500 from php. Telling the person to check their input in
							those cases sends the only one who can fix it looking in the
							wrong place, which is a morning lost to a server setting.

							The two 403s are worth telling apart, because they are not
							the same morning. Every answer the kernel composes carries a
							Content-Security-Policy (it is in Http's default response
							header set), a server's own error page carries none, and a
							same-origin xhr may read that header. With one, the request
							reached php and the csrf guard refused the token - stale
							after the login page sat open, and a reload is the whole
							fix. Without one, the address never reached php, and nothing
							the person types will change that */
					const fromKernel = typeof xhr.getResponseHeader === 'function'
						&& xhr.getResponseHeader('Content-Security-Policy') !== null;

					// The sentence stands at the password field, the one the attempt ends at. The %s is filled through
					// the block's format(), which does it with a function and never a string: String.replace() reads a
					// '$&' in a replacement string
					refuse( pw, el.inputPw, xhr.status === 401
						? text('/_admin/login/error/wrong')
						: ( xhr.status === 403 && fromKernel
							? text('/_admin/login/error/csrf')
							: Nino.adminUi.format( text('/_admin/login/error/endpoint'), String( xhr.status ) ) ) );
				} );
			} );

			el.inputUser.focus();

		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.onReady );

})(window, document, document.documentElement, document.body);
