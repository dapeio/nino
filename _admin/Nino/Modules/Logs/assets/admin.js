

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

		// What the search holds, kept while the list is read again as the tab is shown again
		_query : '',

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
		 *	The line the log stores is the minute, the account and the sentence, two spaces between them
		 *	(see \Nino\Modules\Logs\Admin::record()); a line that is none of that is shown as the sentence alone
		 *
		 *	@param		{string}		line
		 *	@param		{number}		index					Where it stands in the answer: the identity of its row
		 *
		 *	@return		{Object}									{ key, when, actor, text }
		 */
		_parse : function( line, index ) {

			const part = /^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}) {2}(.*?) {2}(.*)$/s.exec( String( line ) );

			return part === null
				? { key : String( index ), when : '', actor : '', text : String( line ) }
				: { key : String( index ), when : part[1], actor : part[2], text : part[3] };
		},

		/**
		 *	Render the activity lines, most recent first (already sorted
		 *	that way by the server), as a table of the design system: the time
		 *	in the form of the workbench, the account and the sentence, with a
		 *	search over the full width above it, sortable columns and paging below.
		 *	The sentences are shown as they are stored
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

			Nino.adminUi.table( {
				mount		: wrap,
				rows		: lines.map( Nino.admin.logs._parse ),
				rowKey	: 'key',
				search	: true,
				query		: Nino.admin.logs._query,
				onQuery	: function( query ) { Nino.admin.logs._query = query },
				// The sentence is drawn with the time and the account under it: the search reads the sentence itself
				searchText : function( row ) { return row.text },
				columns	: [
					{ key : 'when', label : Nino.content.getText('/_admin/logs/label/when'), render : function( value ) { return Nino.adminUi.dateText( value ) || value } },
					{ key : 'actor', label : Nino.content.getText('/_admin/logs/label/actor') },
					// The sentence, and under it - on a narrow screen, where the two columns before it are hidden - the time and the account
					{ key : 'text', label : Nino.content.getText('/_admin/logs/label/text'), wrap : true, render : function( value, row ) {
						const cell = Nino.adminUi.el( 'span', 'logs-entry' );
						cell.appendChild( Nino.adminUi.el( 'span', 'logs-sentence', value ) );
						cell.appendChild( Nino.adminUi.el( 'small', 'logs-meta', [ Nino.adminUi.dateText( row.when ) || row.when, row.actor ].filter( function( part ) { return part !== '' } ).join(' · ') ) );
						return cell;
					} },
				],
				labels	: {
					search	: Nino.content.getText('/_admin/logs/label/search'),
					empty		: Nino.content.getText('/_admin/logs/empty'),
					noMatch	: Nino.content.getText('/_admin/logs/nomatch'),
				},
			} );
		},
	};

})(window, document, document.documentElement, document.body);
