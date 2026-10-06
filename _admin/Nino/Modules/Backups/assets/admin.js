

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Backups" panel: lists the encrypted daily
 *													backups the Backup engine (Backups.php beside it) creates, and
 *													restores one on request. A native confirm() before the
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

				Nino.admin.backups._renderList( response.dates );
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
		 *	Call a restore/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "restore/list")
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
		 *	each with its own restore button
		 *
		 *	@param		{string[]}	dates
		 *
		 *	@return		void
		 */
		_renderList : function( dates ) {

			const wrap = dc.getElementById('backups-list');
			wrap.innerHTML = '';

			if( dates.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/backups/empty') ) );
				return;
			}

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

			// The line a refused restore reports into, beside the list rather
			// than in place of it
			const message = dc.createElement('p');
			message.id = 'backups-message';
			message.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( message );
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
