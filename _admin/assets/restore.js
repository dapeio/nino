

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Restore" module: lists the encrypted daily
 *													backups _editor/Editor.php's Backup class creates, and
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

	Nino.admin.restore = {

		_ready : false,

		/**
		 *	Load the available backup dates and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('restore-list') === null )
				return;

			Nino.admin.restore._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.restore._showError( status, response );

				Nino.admin.restore._renderList( response.dates );
				Nino.admin.restore._ready = true;
			} );
		},

		/**
		 *	Re-show the list when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			if( Nino.admin.restore._ready === false )
				Nino.admin.restore.init();
		},

		/**
		 *	Call a restore/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "restore/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'restore/'+ endpoint, data : JSON.stringify( payload ) } );
		},

		/**
		 *	Show a failed request's status/error
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( status, response ) {
			const wrap = dc.getElementById('restore-list');
			wrap.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			wrap.appendChild( p );
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

			const wrap = dc.getElementById('restore-list');
			wrap.innerHTML = '';

			if( dates.length === 0 ) {
				const p = dc.createElement('p');
				p.textContent = 'No backup available yet.';
				wrap.appendChild( p );
				return;
			}

			const ul = dc.createElement('ul');
			ul.id = 'restore-dates';
			ul.className = 'nino-admin-list';

			dates.forEach( function( date ) {

				const li = dc.createElement('li');

				const span = dc.createElement('span');
				span.textContent = date;
				li.appendChild( span );

				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'nino-admin-btn-danger';
				btn.textContent = 'Restore';
				btn.addEventListener( 'click', function() { Nino.admin.restore._confirmRestore( date ) } );
				li.appendChild( btn );

				ul.appendChild( li );
			} );

			wrap.appendChild( ul );
		},

		/**
		 *	Confirm, then restore the given backup date. A safety snapshot of
		 *	the current state is taken server-side before anything is
		 *	overwritten (see Restore::apiRestore() in _admin/Admin.php)
		 *
		 *	@param		{string}		date
		 *
		 *	@return		void
		 */
		_confirmRestore : function( date ) {

			if( wn.confirm( 'Restore the backup from '+ date+ '? The current state is backed up automatically first.' ) === false )
				return;

			Nino.admin.restore._apiCall( 'restore', { date : date }, function( status, response ) {
				if( status !== 200 || response === null || response.ok !== true )
					return Nino.admin.restore._showError( status, response );

				wn.alert( 'The backup from '+ date+ ' has been restored.' );
				wn.location.reload();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.restore.init );

})(window, document, document.documentElement, document.body);
