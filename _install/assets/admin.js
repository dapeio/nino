

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 4: create the first _editor account(s). See
 *													_install/Install.php's Admin class. "Create admin" stays
 *													its own repeatable action (unlike Setup/Content, more than
 *													one submit here is the normal case) - only the
 *													precondition for the shared Next button (at least one
 *													account exists) is exposed, as checkCanAdvance()
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.admin = {

		showCurrent : function() {
			Nino.install.admin._refreshList();
		},

		/**
		 *	The shared Next button's precondition for this step - refreshes
		 *	the account list first, so it's accurate even if this is the
		 *	very first time the step has been shown
		 *
		 *	@param		{Function}	callback			Called with ( canAdvance, message )
		 *
		 *	@return		void
		 */
		checkCanAdvance : function( callback ) {

			Nino.install.apiCall( 'admin/list', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					callback( false, 'Could not check for an admin account.' );
					return;
				}

				Nino.install.admin._render( response.users );

				callback( response.users.length > 0, 'Create at least one admin account first.' );
			} );
		},

		_refreshList : function() {

			const wrap = dc.getElementById('editor-list');
			if( wrap === null )
				return;

			Nino.install.apiCall( 'admin/list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );
				Nino.install.admin._render( response.users );
			} );
		},

		/**
		 *	@param		{Array}		users		Mail addresses
		 *
		 *	@return		void
		 */
		_render : function( users ) {

			const wrap = dc.getElementById('editor-list');
			wrap.innerHTML = '';

			if( users.length === 0 ) {
				const p = dc.createElement('p');
				p.className = 'nino-admin-hint';
				p.textContent = 'No admin account yet.';
				wrap.appendChild( p );
				return;
			}

			const ul = dc.createElement('ul');
			users.forEach( function( mail ) {
				const li = dc.createElement('li');
				li.textContent = mail;
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );
		},

		_create : function( ev ) {

			ev.preventDefault();

			const mail = dc.getElementById('editor-add-mail');
			const pw 		= dc.getElementById('editor-add-pw');
			const msg 	= dc.getElementById('editor-add-msg');

			msg.textContent = 'Creating …';

			Nino.install.apiCall( 'admin/create', { mail : mail.value, pw : pw.value }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to create.' );
					return;
				}

				msg.textContent = 'Created.';
				mail.value = '';
				pw.value = '';
				Nino.install.admin._render( response.users );
			} );
		},
	};

	Nino.events.bindCallback( 'ready', function() {
		const form = dc.getElementById('editor-add-form');
		if( form !== null )
			form.addEventListener( 'submit', Nino.install.admin._create );
	} );

})(window, document, document.documentElement, document.body);
