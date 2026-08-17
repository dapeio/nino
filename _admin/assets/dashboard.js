/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Dashboard" module: landing tab with the numbers the other
 *													modules already compute (element types, pages, admin
 *													accounts, last backup, missing text keys/image slots) -
 *													see _admin/Admin.php's Dashboard class. Doesn't write anything,
 *													the tiles/rows just jump to the tab they summarize via
 *													Nino.admin.selectTab().
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.dashboard = {

		/**
		 *	Load the summary and render it. No drill-down state to
		 *	preserve, so this always re-fetches, same shape as the other
		 *	modules' init()/showCurrent()
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
		 *	Call a dashboard/* dev action
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
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			wrap.appendChild( p );
		},

		/**
		 *	Render the whole panel: a row of stat tiles, an element-type
		 *	breakdown (by field count), then the missing-content scans
		 *
		 *	@param		{Object}	data					{ types, pages, users, lastBackup, missingText, missingImages }
		 *
		 *	@return		void
		 */
		_render : function( data ) {

			const wrap = dc.getElementById('admin-content-dashboard');
			wrap.innerHTML = '';

			const tiles = dc.createElement('div');
			tiles.id = 'admin-dashboard-tiles';
			tiles.className = 'nino-admin-tiles';
			tiles.appendChild( Nino.admin.dashboard._tile( 'types', String( data.types.length ), data.types.length === 1 ? 'element type' : 'element types' ) );
			tiles.appendChild( Nino.admin.dashboard._tile( 'pages', String( data.pages ), data.pages === 1 ? 'route' : 'routes' ) );
			tiles.appendChild( Nino.admin.dashboard._tile( 'users', String( data.users ), data.users === 1 ? 'admin account' : 'admin accounts' ) );
			tiles.appendChild( Nino.admin.dashboard._tile( 'restore', data.lastBackup || '–', 'last backup' ) );
			wrap.appendChild( tiles );

			wrap.appendChild( Nino.admin.dashboard._typesSection( data.types ) );

			const ul = dc.createElement('ul');
			ul.id = 'admin-dashboard-summary';
			ul.appendChild( Nino.admin.dashboard._row( 'text', data.missingText, 'missing text key', 'missing text keys', 'No missing text keys.' ) );
			ul.appendChild( Nino.admin.dashboard._row( 'images', data.missingImages, 'missing image slot', 'missing image slots', 'No missing image slots.' ) );
			wrap.appendChild( ul );
		},

		/**
		 *	One clickable stat tile - jumps to the tab it summarizes via
		 *	Nino.admin.selectTab(), using the shared design-system tile
		 *
		 *	@param		{string}	tab						Target tab uri (matches Nino.admin.TABS)
		 *	@param		{string}	value					Big figure
		 *	@param		{string}	label					Caption below it
		 *
		 *	@return		{Element}
		 */
		_tile : function( tab, value, label ) {

			const a = dc.createElement('a');
			a.href = '#';
			a.className = 'nino-admin-tile nino-admin-tile--'+ tab;
			a.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.selectTab( tab ) } );

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
		 *	"Element types": one meter bar per type, width relative to
		 *	whichever type has the most fields, clicking a row jumps to
		 *	the Element Types tab
		 *
		 *	@param		{Array}		types					[ { uri, title, fieldCount }, ... ]
		 *
		 *	@return		{Element}
		 */
		_typesSection : function( types ) {

			const section = dc.createElement('div');
			section.className = 'nino-admin-card';

			const title = dc.createElement('h3');
			title.textContent = 'Element types';
			section.appendChild( title );

			if( types.length === 0 ) {
				const p = dc.createElement('p');
				p.textContent = 'No element types defined yet.';
				section.appendChild( p );
				return section;
			}

			const max = Math.max.apply( null, types.map( function( t ) { return t.fieldCount } ).concat( [1] ) );

			const ul = dc.createElement('ul');
			ul.id = 'admin-dashboard-types';

			types.forEach( function( t ) {

				const li = dc.createElement('li');

				const a = dc.createElement('a');
				a.href = '#';
				a.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.selectTab( 'types' ) } );

				const row = dc.createElement('span');
				row.className = 'admin-dashboard-meter-row';

				const label = dc.createElement('span');
				label.className = 'admin-dashboard-meter-label';
				label.textContent = t.title;
				row.appendChild( label );

				const count = dc.createElement('span');
				count.className = 'admin-dashboard-meter-count';
				count.textContent = t.fieldCount;
				row.appendChild( count );

				a.appendChild( row );

				const track = dc.createElement('span');
				track.className = 'admin-dashboard-meter-track';
				const fill = dc.createElement('span');
				fill.className = 'admin-dashboard-meter-fill';
				fill.style.width = ( t.fieldCount / max * 100 )+ '%';
				track.appendChild( fill );
				a.appendChild( track );

				li.appendChild( a );
				ul.appendChild( li );
			} );

			section.appendChild( ul );

			return section;
		},

		/**
		 *	One summary row - a link when the count is > 0 (jumps to the
		 *	tab via Nino.admin.selectTab), plain text otherwise
		 *
		 *	@param		{string}	tab						Tab uri to jump to (matches Nino.admin.TABS)
		 *	@param		{number}	count
		 *	@param		{string}	singular
		 *	@param		{string}	plural
		 *	@param		{string}	zeroText			Shown instead of a count when it's 0
		 *
		 *	@return		{Element}
		 */
		_row : function( tab, count, singular, plural, zeroText ) {

			const li = dc.createElement('li');
			li.className = 'admin-dashboard-row';

			if( count === 0 ) {
				li.textContent = zeroText;
				return li;
			}

			const a = dc.createElement('a');
			a.href = '#';
			a.textContent = count+ ' '+ ( count === 1 ? singular : plural );
			a.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.selectTab( tab ) } );
			li.appendChild( a );

			return li;
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.dashboard.init );

})(window, document, document.documentElement, document.body);
