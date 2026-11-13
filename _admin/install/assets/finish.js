

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 6, the last one: set the recovery password. Success
 *													here is what locks the wizard back out for good - see
 *													_admin/install/Install.php's Finish class. A one-account
 *													setup goes straight on - into the entry point of the one
 *													application that has one, else into the workbench - signed
 *													in as that account; any other, and one with several entry
 *													points, shows where to continue.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.finish = {

		/**
		 *	The button that ends the wizard is named for what it leads to: with exactly one
		 *	application that went through and has an entry point it is "Set up <name>", the
		 *	name as the applications step shows it, and it leads there; otherwise it ends the
		 *	wizard and says so
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			const button = dc.getElementById('finish-submit');
			if( button === null )
				return;

			const entries = Nino.install.applications !== undefined ? Nino.install.applications.appliedEntries() : [];

			if( entries.length === 1 ) {
				button.textContent = 'Set up '+ entries[0].name;
				button.dataset.application = entries[0].key;
			}
			else {
				button.textContent = 'Finish installation';
				delete button.dataset.application;
			}
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
		 *	One more next step per application that has an entry point, beside
		 *	the two the page always offers
		 *
		 *	@param		{Array}		targets			[ { key, name, next } ]
		 *
		 *	@return		void
		 */
		_targets : function( targets ) {

			const wrap = dc.getElementById('finish-next-steps');

			targets.forEach( function( target ) {

				const link = dc.createElement('a');
				link.className = 'install-next-step install-next-step--application';
				link.href = Nino.dir+ target.next;
				link.target = '_blank';

				const copy = dc.createElement('span');
				copy.className = 'install-next-step-copy';

				const name = dc.createElement('strong');
				name.textContent = 'Set up '+ target.name;
				copy.appendChild( name );
				link.dataset.application = target.key;

				link.appendChild( copy );
				wrap.appendChild( link );
			} );
		},

		_submit : function( ev ) {

			ev.preventDefault();

			const pw 	= dc.getElementById('finish-pw');
			const pw2 = dc.getElementById('finish-pw2');
			const msg = dc.getElementById('finish-msg');

			if( pw.value !== pw2.value ) {
				msg.textContent = 'Passwords do not match.';
				return;
			}

			msg.textContent = 'Saving …';

			// The applications that went through the last step: the server believes
			// them only as far as their features are on
			const applications = Nino.install.applications !== undefined ? Nino.install.applications.applied() : [];

			Nino.install.apiCall( 'finish/complete', { password : pw.value, applications : applications }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to finish.' );
					return;
				}

				const targets = Array.isArray( response.targets ) ? response.targets : [];

				// The server kept this session signed in - the one account the
				// wizard created - so the next page is not a choice: the entry point
				// of the one application that has one, else the workbench. With
				// several accounts it ended the session, and with several entry
				// points there is a choice; the panel below offers them, and the
				// login, among the next steps
				if( response.login === true && targets.length <= 1 ) {
					msg.textContent = targets.length === 1 ? '' : 'Opening the workbench …';
					Nino.install.finish._go( Nino.dir+ ( targets.length === 1 ? targets[0].next : '/_admin/' ) );
					return;
				}

				Nino.install.finish._targets( targets );

				// Cleared, not left on "Saving …" - the done panel below replaces
				// the form but not this line, so the finished wizard used to show
				// "Installation complete" and "Saving …" at the same time
				msg.textContent = '';

				dc.getElementById('finish-form').classList.add('install-hidden');
				dc.getElementById('finish-done').classList.remove('install-hidden');
				dc.getElementById('install-page-wrap').classList.add('is-complete');
			} );
		},
	};

	Nino.events.bindCallback( 'ready', function() {
		const form = dc.getElementById('finish-form');
		if( form !== null )
			form.addEventListener( 'submit', Nino.install.finish._submit );
	} );

})(window, document, document.documentElement, document.body);
