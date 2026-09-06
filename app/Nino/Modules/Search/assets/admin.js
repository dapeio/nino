/**
 *	Nino										A compact filesystembased php framework
 *	Modules\Search					The module's /_admin panel, "Search": one button that
 *													recreates every Elements search index configured in
 *													config.php (see Modules\Search\Admin beside this file and
 *													docs/development.md, "Elements Search Index"). Ships with
 *													the module and is loaded exactly while it is active.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.search = {

		/**
		 *	Render the panel - nothing to load, the index is only ever
		 *	rebuilt on request
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('search-form');
			if( wrap === null )
				return;

			wrap.innerHTML = '';

			const heading = dc.createElement('h2');
			heading.textContent = Nino.content.getText('/_admin/search/label/title');
			wrap.appendChild( heading );

			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.content.getText('/_admin/search/hint/intro');
			wrap.appendChild( hint );

			const msg = dc.createElement('p');
			msg.id = 'search-form-msg';
			msg.setAttribute( 'aria-live', 'polite' );

			const btn = dc.createElement('button');
			btn.type = 'button';
			btn.id = 'search-createindex';
			btn.className = 'nino-admin-btn-primary';
			btn.textContent = Nino.content.getText('/_admin/search/label/create');
			btn.addEventListener( 'click', function() { Nino.admin.search._createIndex( btn, msg ) } );

			const actions = dc.createElement('div');
			actions.appendChild( btn );
			wrap.appendChild( Nino.adminUi.actionBar( actions ) );
			wrap.appendChild( msg );
		},

		/**
		 *	Nothing to restore when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
		},

		/**
		 *	Recreate all configured Elements search indexes
		 *
		 *	@param		{Element}	btn
		 *	@param		{Element}	msg
		 *
		 *	@return		void
		 */
		_createIndex : function( btn, msg ) {

			btn.disabled = true;
			msg.textContent = Nino.content.getText('/_admin/search/msg/creating');

			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {

				btn.disabled = false;
				const status 	= xhr.status;
				const response	= xhr.responseJSON;

				if( status !== 200 || response === null || typeof response !== 'object' ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/search/error/create') );
					return;
				}

				msg.textContent = response.created === 0
					? Nino.content.getText('/_admin/search/msg/none')
					: Nino.content.getText( response.created === 1 ? '/_admin/search/msg/created' : '/_admin/search/msg/created-plural' )
						.replace( '%d', String( response.created ) ).replace( '%n', String( response.elements ) );
			}, { action : 'search/createindex', data : JSON.stringify( {} ) } );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.search.init );

})(window, document, document.documentElement, document.body);
