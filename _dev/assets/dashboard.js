/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Dashboard" module: landing tab with the numbers Text/
 *													Images already compute (missing keys/slots) - see
 *													_dev/Dev.php's Dashboard class. Doesn't write anything,
 *													the two links just jump to the tab that found them.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.dev = wn.Nino.dev || {};

	Nino.dev.dashboard = {

		/**
		 *	Load the summary and render it. No drill-down state to
		 *	preserve, so this always re-fetches, same shape as the other
		 *	modules' init()/showCurrent()
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('dev-content-dashboard') === null )
				return;

			Nino.dev.dashboard._apiCall( 'summary', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.dev.dashboard._showError( status, response );

				Nino.dev.dashboard._render( response );
			} );
		},

		/**
		 *	Re-fetch and re-show when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.dev.dashboard.init();
		},

		/**
		 *	Call a dashboard/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "summary", becomes "dashboard/summary")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_dev/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'dashboard/'+ endpoint, data : JSON.stringify( payload ) } );
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
			const wrap = dc.getElementById('dev-content-dashboard');
			wrap.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'dev-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			wrap.appendChild( p );
		},

		/**
		 *	Render the two scan summaries, each a link that jumps straight
		 *	to the tab that found them
		 *
		 *	@param		{Object}	data					{ missingText, missingImages }
		 *
		 *	@return		void
		 */
		_render : function( data ) {

			const wrap = dc.getElementById('dev-content-dashboard');
			wrap.innerHTML = '';

			const ul = dc.createElement('ul');
			ul.id = 'dev-dashboard-summary';

			ul.appendChild( Nino.dev.dashboard._row( 'text', data.missingText, 'missing text key', 'missing text keys', 'No missing text keys.' ) );
			ul.appendChild( Nino.dev.dashboard._row( 'images', data.missingImages, 'missing image slot', 'missing image slots', 'No missing image slots.' ) );

			wrap.appendChild( ul );
		},

		/**
		 *	One summary row - a link when the count is > 0 (jumps to the
		 *	tab via Nino.dev.selectTab), plain text otherwise
		 *
		 *	@param		{string}	tab						Tab uri to jump to (matches Nino.dev.TABS)
		 *	@param		{number}	count
		 *	@param		{string}	singular
		 *	@param		{string}	plural
		 *	@param		{string}	zeroText			Shown instead of a count when it's 0
		 *
		 *	@return		{Element}
		 */
		_row : function( tab, count, singular, plural, zeroText ) {

			const li = dc.createElement('li');
			li.className = 'dev-dashboard-row';

			if( count === 0 ) {
				li.textContent = zeroText;
				return li;
			}

			const a = dc.createElement('a');
			a.href = '#';
			a.textContent = count+ ' '+ ( count === 1 ? singular : plural );
			a.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.dev.selectTab( tab ) } );
			li.appendChild( a );

			return li;
		},
	};

	Nino.events.bindCallback( 'ready', Nino.dev.dashboard.init );

})(window, document, document.documentElement, document.body);
