

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	logs.js									Admin "Log" panel: read-only view of the activity log
 *													Admin.php's Logs class writes to (logins, element/text/
 *													user/image changes) - see _editor/Editor.php's Logs class
 *													docblock. Nothing here writes anything, it only lists.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.editor = wn.Nino.editor || {};

	Nino.editor.logs = {

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

			Nino.editor.logs._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.editor.logs._showError( status, response );

				Nino.editor.logs._renderList( response.lines );
			} );
		},

		/**
		 *	Re-fetch and re-show the list when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.editor.logs.init();
		},

		/**
		 *	Call a logs/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "logs/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_editor/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'logs/'+ endpoint, data : JSON.stringify( payload ) } );
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
			const wrap = dc.getElementById('logs-list');
			wrap.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/logs/error/load') );
			wrap.appendChild( p );
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
				const p = dc.createElement('p');
				p.textContent = Nino.content.getText('/_editor/logs/empty');
				wrap.appendChild( p );
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
