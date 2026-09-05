

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	lockout.js							"Login protection" tab of the Users panel: the two
 *													numbers of the throttle in front of the login, as one
 *													form. See Lockout/Lockout.php beside it for the schema
 *													this renders and validates against.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.lockout = {

		// Survives the re-render a successful save triggers - see _save()
		_pendingMsg : '',

		/**
		 *	Load the schema plus current values and render the form
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('lockout-form');
			if( wrap === null )
				return;

			Nino.admin.lockout._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.lockout._showError( wrap, status, response );

				Nino.admin.lockout._render( response.fields );
			} );
		},

		showCurrent : function() {
			Nino.admin.lockout.init();
		},

		/**
		 *	Call a lockout/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "lockout/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'lockout/'+ endpoint, data : JSON.stringify( payload ) } );
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
		 *	One fieldset with both numbers, and Save in the pinned action bar
		 *
		 *	@param		{Array}		fields			[ { key, min, max, unit, label, hint, value }, ... ]
		 *
		 *	@return		void
		 */
		_render : function( fields ) {

			const wrap = dc.getElementById('lockout-form');
			wrap.innerHTML = '';

			const form = dc.createElement('form');

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/nav/lockout');
			fieldset.appendChild( legend );

			const intro = dc.createElement('p');
			intro.className = 'nino-admin-hint';
			intro.textContent = Nino.content.getText('/_admin/lockout/intro');
			fieldset.appendChild( intro );

			fields.forEach( function( field ) {
				fieldset.appendChild( Nino.adminUi.numberField( field ) );
			} );

			form.appendChild( fieldset );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.className = 'nino-admin-btn-primary';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'lockout-form-msg';
			msg.className = 'nino-admin-actionbar-status';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.lockout._save() } );

			wrap.appendChild( form );

			if( Nino.admin.lockout._pendingMsg !== '' ) {
				msg.textContent = Nino.admin.lockout._pendingMsg;
				Nino.admin.lockout._pendingMsg = '';
			}
		},

		/**
		 *	Save both numbers in one request, then reload so the form shows
		 *	the ints config.php now holds
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg = dc.getElementById('lockout-form-msg');
			const fields = {};

			Array.prototype.slice.call( dc.querySelectorAll('#lockout-form [data-key]') ).forEach( function( el ) {
				fields[el.dataset.key] = el.value.trim();
			} );

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.lockout._apiCall( 'save', { fields : fields }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
					return;
				}

				Nino.admin.lockout._pendingMsg = Nino.content.getText('/_admin/common/msg/saved');
				Nino.admin.lockout.init();
			} );
		},
	};

})(window, document, document.documentElement, document.body);
