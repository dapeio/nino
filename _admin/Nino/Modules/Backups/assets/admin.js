

/**
 *	Nino										A compact filesystembased php framework
 *	admin.js								"Backups" panel: lists the encrypted daily
 *													backups the Backup engine (Backups.php beside it) creates,
 *													writes one more on request and restores one on request.
 *													A native confirm() before the
 *													actual restore call is deliberate - this overwrites the
 *													live config.php/text/elements/images, there's no "are you
 *													sure" step server-side beyond that.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.backups = {

		_ready : false,

		/**
		 *	Load the available backup dates and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('backups-list') === null )
				return;

			Nino.admin.backups._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.backups._showError( status, response );

				// A missing 'enabled' is an answer from before there was one
				Nino.admin.backups._renderList( response.dates, response.enabled !== false );
				Nino.admin.backups._ready = true;
			} );
		},

		/**
		 *	Re-show the list when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			if( Nino.admin.backups._ready === false )
				Nino.admin.backups.init();
		},

		/**
		 *	Call a backups/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "backups/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'backups/'+ endpoint, payload, callback );
		},

		/**
		 *	Show a failed request's status/error in place of the list - for the
		 *	load that failed, where there is no list to keep in the first place
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( status, response ) {
			Nino.adminUi.showError( dc.getElementById('backups-list'), status, response, '/_admin/common/error/load' );
		},

		/**
		 *	Report a failure that leaves the list standing, in the line below
		 *	it.
		 *
		 *	A refused restore is exactly that: nothing was overwritten, and
		 *	every date on the list is still a date worth trying. It used to go
		 *	through _showError() above, which empties the list - so the one
		 *	screen the framework offers for getting a broken site back replaced
		 *	its backups with a sentence, and only a page reload brought them
		 *	back.
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showRestoreError : function( status, response ) {

			const message = dc.getElementById('backups-message');

			// No list rendered means no line under it either, and then the
			// error has to go somewhere rather than nowhere
			if( message === null )
				return Nino.admin.backups._showError( status, response );

			message.className = 'nino-admin-error';
			message.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/request' );
		},

		/**
		 *	Render the list of available backup dates, most recent first,
		 *	each with its own restore button - and, below it, "Back up now",
		 *	which is there for an empty list too: it is how the first archive
		 *	of a project that has none yet is made. Not offered while backups
		 *	are switched off, which the server would refuse
		 *
		 *	@param		{string[]}	dates
		 *	@param		{boolean}		enabled				Whether backups are on
		 *
		 *	@return		void
		 */
		_renderList : function( dates, enabled ) {

			const wrap = dc.getElementById('backups-list');
			wrap.innerHTML = '';

			if( dates.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/backups/empty') ) );
			else {

				const ul = dc.createElement('ul');
				ul.id = 'backups-dates';
				ul.className = 'nino-admin-list';

				dates.forEach( function( date ) {

					const li = dc.createElement('li');

					const span = dc.createElement('span');
					span.textContent = date;
					li.appendChild( span );

					const btn = dc.createElement('button');
					btn.type = 'button';
					btn.className = 'nino-admin-btn-danger';
					btn.textContent = Nino.content.getText('/_admin/common/label/restore');
					btn.addEventListener( 'click', function() { Nino.admin.backups._confirmRestore( date ) } );
					li.appendChild( btn );

					ul.appendChild( li );
				} );

				wrap.appendChild( ul );
			}

			// The line a refused restore or a refused backup reports into,
			// beside the list rather than in place of it
			const message = dc.createElement('p');
			message.id = 'backups-message';
			message.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( message );

			if( enabled === false )
				return;

			const now = dc.createElement('button');
			now.type = 'button';
			now.id = 'backups-now';
			now.className = 'nino-admin-btn-primary';
			now.textContent = Nino.content.getText('/_admin/backups/label/now');
			now.addEventListener( 'click', function() { Nino.admin.backups._backupNow( now ) } );
			wrap.appendChild( Nino.adminUi.listActions( [ now ] ) );
		},

		/**
		 *	Write one more archive, then show the list again with it in it.
		 *	The button is off while the request runs - an archive takes a
		 *	moment - and a refusal keeps the list and says why
		 *
		 *	@param		{Element}		button
		 *
		 *	@return		void
		 */
		_backupNow : function( button ) {

			button.disabled = true;

			const message = dc.getElementById('backups-message');
			if( message !== null ) {
				message.className = '';
				message.textContent = '';
			}

			Nino.admin.backups._apiCall( 'now', {}, function( status, response ) {

				button.disabled = false;

				if( status !== 200 || response === null || typeof response.id !== 'string' )
					return Nino.admin.backups._showRestoreError( status, response );

				const created = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/created'), response.id );

				// The new list replaces the old one, the line with it: what
				// was made is said once the list shows it
				Nino.admin.backups._apiCall( 'list', {}, function( code, body ) {

					if( code !== 200 || body === null ) {
						Nino.admin.backups._showRestoreError( code, body );
						// The archive is made all the same: say so before the error
						const line = dc.getElementById('backups-message');
						if( line !== null )
							line.textContent = created+ ' '+ line.textContent;
						return;
					}

					Nino.admin.backups._renderList( body.dates, body.enabled !== false );
					dc.getElementById('backups-message').textContent = created;
				} );
			} );
		},

		/**
		 *	Confirm, then restore the given backup date. A safety snapshot of
		 *	the current state is taken server-side before anything is
		 *	overwritten (see apiRestore() in Admin/Admin.php beside it)
		 *
		 *	@param		{string}		date
		 *
		 *	@return		void
		 */
		_confirmRestore : function( date ) {

			if( wn.confirm( Nino.adminUi.format( Nino.content.getText('/_admin/backups/confirm/restore'), date ) ) === false )
				return;

			// Whatever a previous attempt left there is about that attempt
			const message = dc.getElementById('backups-message');
			if( message !== null )
				message.textContent = '';

			// The restore ends in a reload of the page and every form on it, and
			// has put other files in their place by then: unsaved input is asked
			// about first (see Nino.admin.dirty.guard())
			const restore = function() {

				Nino.admin.backups._apiCall( 'restore', { date : date }, function( status, response ) {
					if( status !== 200 || response === null || response.ok !== true )
						return Nino.admin.backups._showRestoreError( status, response );

					wn.alert( Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/restored'), date ) );
					wn.location.reload();
				} );
			};

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.guard( null, restore );
			else
				restore();
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.backups.init );

})(window, document, document.documentElement, document.body);
