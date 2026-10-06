

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
				if( status !== 200 || response === null ) {
					// The error replaces the form: there is nothing left to hold
					Nino.admin.maintenance._ready = false;
					return Nino.admin.maintenance._showError( wrap, status, response );
				}

				// Typed into while the answer was on its way: the form is not
				// rebuilt over it
				if( Nino.admin.maintenance._holdsInput() === true )
					return;

				Nino.admin.maintenance._render( wrap, response );
				Nino.admin.maintenance._ready = true;
			} );
		},

		/*	Read again every time the panel is shown, so the state sentence
			follows a change made elsewhere - but not over a form somebody has
			typed into. Re-rendering it emptied the pane and rebuilt it from the
			server, so a switch turned over or a Retry-After typed was gone on
			any switch of panel, with no word. The Features panel keeps the same
			promise by not reading again at all once its form is up	*/
		showCurrent : function() {

			if( Nino.admin.maintenance._holdsInput() === false )
				Nino.admin.maintenance.init();
		},

		/**
		 *	Whether the form on screen holds input nobody has saved - the shell
		 *	says (see Nino.admin.dirty); a shell without the registry never does
		 *
		 *	@return		{boolean}
		 */
		_holdsInput : function() {
			return Nino.admin.maintenance._ready === true && typeof Nino.admin.dirty === 'object' && Nino.admin.dirty.isDirty( [ 'maintenance' ] ) === true;
		},

		/**
		 *	Call a maintenance/* action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "status", becomes "maintenance/status")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'maintenance/'+ endpoint, payload, callback );
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
			Nino.adminUi.showError( container, status, response, '/_admin/common/error/load' );
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

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'maintenance' );
		},

		/**
		 *	Post both fields in one request
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the state was written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

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
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				Nino.admin.maintenance._pendingMsg = Nino.content.getText('/_admin/common/msg/saved');

				// Re-rendered from the answer rather than left as typed: it
				// carries the state config.php now actually holds, and the
				// state sentence above the form has to follow it
				Nino.admin.maintenance._render( wrap, response );
				report( true );
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.maintenance.init );

	// The shell asks before anything throws the form's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'maintenance', function() { return dc.getElementById('maintenance-form') }, function( done ) { Nino.admin.maintenance._save( done ) } );

})(window, document, document.documentElement, document.body);
