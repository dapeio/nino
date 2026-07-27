/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	dashboard.js						Admin "Dashboard" panel - the landing tab: a handful
 *													of read-only numbers already available from the other
 *													panels (see _admin/Admin.php's Dashboard class), pulled
 *													into one overview. Doesn't write anything, and the tiles
 *													are just #hash links into the panel they summarize -
 *													Nino.admin.onReady()'s own hashchange listener does the
 *													actual tab switch, nothing dashboard-specific needed there.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.dashboard = {

		/**
		 *	Load the summary and render it. Same "always re-fetch" shape as
		 *	logs.js/newsletter.js - there's no drill-down state to preserve,
		 *	and re-fetching on every tab switch keeps the numbers current
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('admin-content-dashboard') === null )
				return;

			Nino.admin.dashboard._apiCall( 'summary', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.dashboard._showError( status, response );

				Nino.admin.dashboard._render( response );
			} );
		},

		/**
		 *	Re-fetch and re-show when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.admin.dashboard.init();
		},

		/**
		 *	Call a dashboard/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "summary", becomes "dashboard/summary")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
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
			const wrap = dc.getElementById('admin-content-dashboard');
			wrap.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Fehler beim Laden.' );
			wrap.appendChild( p );
		},

		/**
		 *	Render the whole panel: a row of stat tiles, an element-per-type
		 *	breakdown, then recent activity
		 *
		 *	@param		{Object}	data					{ submissions, newsletter, elements, lastBackup, recentActivity }
		 *
		 *	@return		void
		 */
		_render : function( data ) {

			const wrap = dc.getElementById('admin-content-dashboard');
			wrap.innerHTML = '';

			const tiles = dc.createElement('div');
			tiles.id = 'admin-dashboard-tiles';
			tiles.appendChild( Nino.admin.dashboard._tile( 'submissions', String( data.submissions ), 'Anfragen' ) );
			tiles.appendChild( Nino.admin.dashboard._tile( 'newsletter', String( data.newsletter ), 'Newsletter-Abonnenten' ) );
			tiles.appendChild( Nino.admin.dashboard._tile( 'logs', data.lastBackup || '–', 'Letztes Backup' ) );
			wrap.appendChild( tiles );

			wrap.appendChild( Nino.admin.dashboard._elementsSection( data.elements ) );
			wrap.appendChild( Nino.admin.dashboard._activitySection( data.recentActivity ) );
		},

		/**
		 *	One clickable stat tile - #hash link into the panel it
		 *	summarizes, same tab switch the nav bar itself uses
		 *
		 *	@param		{string}	panel					Target panel name (eg. "submissions")
		 *	@param		{string}	value					Big figure
		 *	@param		{string}	label					Caption below it
		 *
		 *	@return		{Element}
		 */
		_tile : function( panel, value, label ) {

			const a = dc.createElement('a');
			a.href = '#'+ panel;
			a.className = 'admin-dashboard-tile admin-dashboard-tile--'+ panel;

			const val = dc.createElement('span');
			val.className = 'admin-dashboard-tile-value';
			val.textContent = value;
			a.appendChild( val );

			const lbl = dc.createElement('span');
			lbl.className = 'admin-dashboard-tile-label';
			lbl.textContent = label;
			a.appendChild( lbl );

			return a;
		},

		/**
		 *	"Elemente nach Typ": one meter bar per type, width relative to
		 *	whichever type has the most entries, clicking a row jumps to
		 *	that type in the Elements panel
		 *
		 *	@param		{Array}		elements			[ { type, title, count }, ... ]
		 *
		 *	@return		{Element}
		 */
		_elementsSection : function( elements ) {

			const section = dc.createElement('div');
			section.className = 'admin-dashboard-section';

			const title = dc.createElement('h3');
			title.textContent = 'Elemente nach Typ';
			section.appendChild( title );

			if( elements.length === 0 ) {
				const p = dc.createElement('p');
				p.textContent = 'Noch keine Element-Typen angelegt.';
				section.appendChild( p );
				return section;
			}

			const max = Math.max.apply( null, elements.map( function( e ) { return e.count } ).concat( [1] ) );

			const ul = dc.createElement('ul');
			ul.id = 'admin-dashboard-elements';

			elements.forEach( function( e ) {

				const li = dc.createElement('li');

				const a = dc.createElement('a');
				a.href = '#elements/'+ e.type;

				const row = dc.createElement('span');
				row.className = 'admin-dashboard-meter-row';

				const label = dc.createElement('span');
				label.className = 'admin-dashboard-meter-label';
				label.textContent = e.title;
				row.appendChild( label );

				const count = dc.createElement('span');
				count.className = 'admin-dashboard-meter-count';
				count.textContent = e.count;
				row.appendChild( count );

				a.appendChild( row );

				const track = dc.createElement('span');
				track.className = 'admin-dashboard-meter-track';
				const fill = dc.createElement('span');
				fill.className = 'admin-dashboard-meter-fill';
				fill.style.width = ( e.count / max * 100 )+ '%';
				track.appendChild( fill );
				a.appendChild( track );

				li.appendChild( a );
				ul.appendChild( li );
			} );

			section.appendChild( ul );

			return section;
		},

		/**
		 *	"Letzte Aktivität": the handful of most recent log lines
		 *	(server already limits + orders them), same list markup as
		 *	logs.js's own full list
		 *
		 *	@param		{string[]}	lines
		 *
		 *	@return		{Element}
		 */
		_activitySection : function( lines ) {

			const section = dc.createElement('div');
			section.className = 'admin-dashboard-section';

			const title = dc.createElement('h3');
			title.textContent = 'Letzte Aktivität';
			section.appendChild( title );

			if( lines.length === 0 ) {
				const p = dc.createElement('p');
				p.textContent = 'Noch keine Einträge.';
				section.appendChild( p );
				return section;
			}

			const ul = dc.createElement('ul');
			ul.id = 'admin-dashboard-activity';

			lines.forEach( function( line ) {
				const li = dc.createElement('li');
				li.textContent = line;
				ul.appendChild( li );
			} );

			section.appendChild( ul );

			const more = dc.createElement('a');
			more.href = '#logs';
			more.className = 'admin-dashboard-more';
			more.textContent = 'Alle anzeigen →';
			section.appendChild( more );

			return section;
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.dashboard.init );

})(window, document, document.documentElement, document.body);
