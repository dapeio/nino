

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js									Admin "Log" panel: read-only view of the activity log
 *													the panel class beside it writes (logins, element/text/
 *													user/image changes) - see Admin/Admin.php's record() and
 *													its class docblock. Nothing here writes anything, it
 *													only lists.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.logs = {

		/**
		 *	Load the recorded activity lines and render them. Unlike the
		 *	other panels (Elements/Text/Images/Users), there's no list/form
		 *	drill-down state to preserve here - it's always just the flat
		 *	list - so this always re-fetches rather than only loading once,
		 *	otherwise switching tabs away and back would keep showing
		 *	whatever was current at page load, missing every change made
		 *	since
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('logs-list') === null )
				return;

			Nino.admin.logs._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.logs._showError( status, response );

				Nino.admin.logs._renderList( response.lines );
			} );
		},

		/**
		 *	Re-fetch and re-show the list when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.admin.logs.init();
		},

		/**
		 *	Call a logs/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "logs/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'logs/'+ endpoint, payload, callback );
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
			Nino.adminUi.showError( dc.getElementById('logs-list'), status, response, '/_admin/logs/error/load' );
		},

		/**
		 *	Render the activity lines, most recent first (already sorted
		 *	that way by the server)
		 *
		 *	@param		{string[]}	lines
		 *
		 *	@return		void
		 */
		_renderList : function( lines ) {

			const wrap = dc.getElementById('logs-list');
			wrap.innerHTML = '';

			if( lines.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/logs/empty') ) );
				return;
			}

			const ul = dc.createElement('ul');
			ul.id = 'logs-entries';
			ul.className = 'nino-admin-list nino-admin-list-dense';

			lines.forEach( function( line ) {
				const li = dc.createElement('li');
				li.textContent = line;
				ul.appendChild( li );
			} );

			wrap.appendChild( ul );
		},
	};

})(window, document, document.documentElement, document.body);
