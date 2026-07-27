

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	Nino.admin.js					Admin area
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = {

		/**
		 *	Wire up the admin login form: validate inputs, submit via
		 *	Nino.auth.login and show pending/error messages
		 *
		 *	@return		void
		 */
		onReady : function() {

			// The picker's <option> values are already real ?locale=xx
			// targets (see Admin::_localePickerHtml()) - navigating there
			// is all that's needed, Admin::init() reads it back server-side
			const localePicker = dc.getElementById('admin-localepicker');
			if( localePicker !== null )
				localePicker.addEventListener( 'change', function(){ wn.location.href = this.value } );

			const el = {
				formMsg 	: dc.getElementById('form-message'),
				inputUser : dc.getElementById('input-user'),
				inputPw 	: dc.getElementById('input-pw'),
				submit		: dc.getElementById('submit'),
			};

			// Catch login
			dc.getElementById('form-login').addEventListener( 'submit', function(e){
				e.preventDefault();
				if(el.inputUser.value.length==0) {
					el.inputUser.classList.add('error')
					el.formMsg.innerHTML = Nino.content.getText('/_admin/login/error/user');
					el.inputUser.focus();
					return;
				}
				if(el.inputPw.value.length==0) {
					el.inputPw.classList.add('error')
					el.formMsg.innerHTML = Nino.content.getText('/_admin/login/error/pw');
					el.inputPw.focus();
					return;
				}

				// Login
				el.formMsg.className = 'pending';
				el.formMsg.innerHTML = Nino.content.getText('/_admin/login/msg/pending');
				Nino.auth.login( el.inputUser.value, el.inputPw.value, '/_admin', function(){
					el.formMsg.classList.add('error');
					el.formMsg.innerHTML = Nino.content.getText('/_admin/login/error/wrong');
				} );
			} );

			el.inputUser.focus();

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