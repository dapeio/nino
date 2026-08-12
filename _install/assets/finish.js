

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 7, the last one: set the real /_admin password. Success
 *													here is what locks /_install back out for good - see
 *													_install/Install.php's Finish class.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.finish = {

		showCurrent : function() {},

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

			Nino.install.apiCall( 'finish/complete', { password : pw.value }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to finish.' );
					return;
				}

				dc.getElementById('finish-form').classList.add('admin-hidden');
				dc.getElementById('finish-done').classList.remove('admin-hidden');
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
