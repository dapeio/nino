

/**
 *	Nino										A compact filesystembased php framework
 *	Maintenance							The Maintenance panel: one pane, one form - the state, the
 *													switch, the Retry-After seconds, and a note about who
 *													still sees the site. See Admin/Admin.php beside it for
 *													the two actions this renders and posts to.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.maintenance = {

		_ready 			: false,
		// Survives the re-render a successful save triggers - see _save()
		_pendingMsg	: '',

		/**
		 *	Load the current state and render the form
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('maintenance-form');
			if( wrap === null )
				return;

			Nino.admin.maintenance._apiCall( 'status', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.maintenance._showError( wrap, status, response );

				Nino.admin.maintenance._render( wrap, response );
				Nino.admin.maintenance._ready = true;
			} );
		},

		showCurrent : function() {
			Nino.admin.maintenance.init();
		},

		/**
		 *	Call a maintenance/* action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "status", becomes "maintenance/status")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'maintenance/'+ endpoint, data : JSON.stringify( payload ) } );
		},

		/**
		 *	Show a failed request's status/error in a container
		 *
		 *	@param		{Element}		container
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( container, status, response ) {
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/load') );
			container.appendChild( p );
		},

		/**
		 *	The whole pane: the current state as a sentence, the form, and
		 *	the shared save row
		 *
		 *	@param		{Element}		wrap
		 *	@param		{Object}		data					{ status, retry }
		 *
		 *	@return		void
		 */
		_render : function( wrap, data ) {

			wrap.innerHTML = '';

			const state = dc.createElement('p');
			state.className = 'nino-admin-hint-lead';
			state.textContent = data.status === true
				? Nino.content.getText('/_admin/maintenance/state/offline')
				: Nino.content.getText('/_admin/maintenance/state/online');
			wrap.appendChild( state );

			const form = dc.createElement('form');
			const fieldset = dc.createElement('fieldset');

			fieldset.appendChild( Nino.adminUi.switchField( {
				key 			: 'status',
				checked 	: data.status === true,
				label 		: Nino.content.getText('/_admin/maintenance/label/status'),
				hint 			: Nino.content.getText('/_admin/maintenance/hint/status'),
			} ) );

			fieldset.appendChild( Nino.adminUi.numberField( {
				key 	: 'retry',
				value	: data.retry,
				min 	: 60,
				max 	: 604800,
				unit 	: 'seconds',
				label	: '/_admin/maintenance/label/retry',
				hint 	: '/_admin/maintenance/hint/retry',
			} ) );

			const note = dc.createElement('p');
			note.className = 'nino-admin-hint';
			note.textContent = Nino.content.getText('/_admin/maintenance/hint/signedin');
			fieldset.appendChild( note );

			form.appendChild( fieldset );

			// Same shared actions row every module's form ends on
			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.className = 'nino-admin-btn-primary';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'maintenance-form-msg';
			msg.className = 'nino-admin-actionbar-status';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.maintenance._save() } );

			wrap.appendChild( form );

			// Re-shown after the reload that follows a save, which builds this
			// element fresh and would otherwise wipe the confirmation the moment
			// it appeared
			if( Nino.admin.maintenance._pendingMsg !== '' ) {
				msg.textContent = Nino.admin.maintenance._pendingMsg;
				Nino.admin.maintenance._pendingMsg = '';
			}
		},

		/**
		 *	Post both fields in one request
		 *
		 *	@return		void
		 */
		_save : function() {

			const wrap = dc.getElementById('maintenance-form');
			const msg = dc.getElementById('maintenance-form-msg');
			const statusInput = wrap.querySelector('[data-key="status"]');
			const retryInput = wrap.querySelector('[data-key="retry"]');

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.maintenance._apiCall( 'set', {
				status	: statusInput.checked,
				retry		: retryInput.value.trim(),
			}, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
					return;
				}

				Nino.admin.maintenance._pendingMsg = Nino.content.getText('/_admin/common/msg/saved');

				// Re-rendered from the answer rather than left as typed: it
				// carries the state config.php now actually holds, and the
				// state sentence above the form has to follow it
				Nino.admin.maintenance._render( wrap, response );
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.maintenance.init );

})(window, document, document.documentElement, document.body);
