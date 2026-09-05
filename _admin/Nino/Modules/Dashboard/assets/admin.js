/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js    						Admin "Dashboard" panel - the landing tab: a handful
 *													of read-only numbers already available from the other
 *													panels (see _admin/Editor.php's Dashboard class), pulled
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
		 *	logs.js - there's no drill-down state to preserve,
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
			Nino.admin.router.set( 'dashboard', [] );
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
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/dashboard/error/load') );
			wrap.appendChild( p );
		},

		/**
		 *	Render the whole panel: a row of stat tiles, an element-per-type
		 *	breakdown, then recent activity
		 *
		 *	@param		{Object}	data					{ elements, lastBackup, tiles, recentActivity? }
		 *
		 *	@return		void
		 */
		_render : function( data ) {

			const wrap = dc.getElementById('admin-content-dashboard');
			wrap.innerHTML = '';

			const tiles = dc.createElement('div');
			tiles.id = 'admin-dashboard-tiles';
			tiles.className = 'nino-admin-tiles';
			// One tile per panel that reports a number (see the panel
			// contract's summary() and Dashboard::apiSummary()) - only in the
			// response at all if this admin holds that panel's permission,
			// omitted rather than empty, so nothing here has to know which
			// panels exist. recentActivity is gated the same way.
			( Array.isArray( data.tiles ) ? data.tiles : [] ).forEach( function( tile ) {
				tiles.appendChild( Nino.admin.dashboard._tile( String( tile.panel ), String( tile.value ), Nino.adminUi.text( tile.label ) ) );
			} );
			// Both of these come from another module (see the panel's
			// apiSummary()), so both are drawn only when the answer carried
			// them: a workbench without Backups has no last backup to show,
			// one without Elements has no types to chart
			// Named after the panel whose number it is, like every other tile:
			// that is what gives it the Backups link, the Backups icon and the
			// .nino-admin-tile--backups accent the stylesheet has always had
			// for it. An account that cannot open that panel still sees the
			// date - basic operational info, deliberately ungated (see the
			// panel's apiSummary()) - and its link lands on the first panel it
			// does have, the same as any hash that names no visible pane
			if( data.lastBackup !== undefined )
				tiles.appendChild( Nino.admin.dashboard._tile( 'backups', data.lastBackup || '–', Nino.content.getText('/_admin/dashboard/label/lastbackup') ) );
			wrap.appendChild( tiles );

			if( Array.isArray( data.elements ) )
				wrap.appendChild( Nino.admin.dashboard._elementsSection( data.elements ) );
			if( data.recentActivity !== undefined )
				wrap.appendChild( Nino.admin.dashboard._activitySection( data.recentActivity ) );
		},

		/**
		 *	One clickable stat tile - #hash link into the panel it
		 *	summarizes, same tab switch the nav bar itself uses
		 *
		 *	@param		{string}	panel					Target panel name (eg. "elements")
		 *	@param		{string}	value					Big figure
		 *	@param		{string}	label					Caption below it
		 *
		 *	@return		{Element}
		 */
		_tile : function( panel, value, label ) {

			const a = dc.createElement('a');
			a.href = '#'+ panel;
			a.className = 'nino-admin-tile nino-admin-tile--'+ panel;

			const val = dc.createElement('span');
			val.className = 'nino-admin-tile-value';
			val.textContent = value;
			a.appendChild( val );

			const lbl = dc.createElement('span');
			lbl.className = 'nino-admin-tile-label';
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
			section.className = 'nino-admin-card';

			const title = dc.createElement('h3');
			title.textContent = Nino.content.getText('/_admin/dashboard/label/elements');
			section.appendChild( title );

			if( elements.length === 0 ) {
				section.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/dashboard/empty/elements') ) );
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
				row.className = 'nino-admin-meter-row';

				const label = dc.createElement('span');
				label.className = 'nino-admin-meter-label';
				label.textContent = e.title;
				row.appendChild( label );

				const count = dc.createElement('span');
				count.className = 'nino-admin-meter-count';
				count.textContent = e.count;
				row.appendChild( count );

				a.appendChild( row );

				const track = dc.createElement('span');
				track.className = 'nino-admin-meter-track';
				const fill = dc.createElement('span');
				fill.className = 'nino-admin-meter-fill';
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
			section.className = 'nino-admin-card';

			const title = dc.createElement('h3');
			title.textContent = Nino.content.getText('/_admin/dashboard/label/activity');
			section.appendChild( title );

			if( lines.length === 0 ) {
				section.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/dashboard/empty/activity') ) );
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
			more.textContent = Nino.content.getText('/_admin/dashboard/label/all')+ ' →';
			section.appendChild( more );

			return section;
		},
	};

})(window, document, document.documentElement, document.body);
