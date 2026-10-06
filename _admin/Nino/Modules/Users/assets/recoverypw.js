/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	recoverypw.js						"Recovery password" tab of the Users panel: the secret
 *													_admin/recovery.php asks for, changed with the old one.
 *													A form on the tab rather than a dialog - the workbench
 *													has no modal component. See
 *													RecoveryPassword/RecoveryPassword.php beside it for what
 *													the server checks.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.recoverypw = {

		// Whether the form has been built once - see showCurrent()
		_ready : false,

		// The form's status line - see Nino.adminUi.status(). Drawn again with the form
		_status : null,

		/**
		 *	Render the form
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('recoverypw-form');
			if( wrap === null )
				return;

			Nino.admin.recoverypw._render( wrap );
			Nino.admin.recoverypw._ready = true;
		},

		/*	The shell calls this every time the tab is shown again, and a form
			is never rebuilt then: what was typed stays where it was left (see
			Nino.admin.lockout.showCurrent()). There is nothing on the server
			this form shows, so there is nothing to fetch either	*/
		showCurrent : function() {

			if( Nino.admin.recoverypw._ready === false )
				Nino.admin.recoverypw.init();
		},

		/**
		 *	Call a recoverypw/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "save", becomes "recoverypw/save")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'recoverypw/'+ endpoint, payload, callback );
		},

		/**
		 *	One fieldset with the hint and the three password fields, and
		 *	Save in the pinned action bar
		 *
		 *	@param		{Element}		wrap
		 *
		 *	@return		void
		 */
		_render : function( wrap ) {

			wrap.innerHTML = '';

			const form = dc.createElement('form');

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/nav/recoverypw');
			fieldset.appendChild( legend );

			const intro = dc.createElement('p');
			intro.className = 'nino-admin-hint';
			intro.textContent = Nino.content.getText('/_admin/recoverypw/intro');
			fieldset.appendChild( intro );

			// No data-key: the three are read by id, by name, never as a set. The
			// data-field is the name the server gives a refusal (see apiSave()), so
			// the input it is about is marked
			[
				[ 'current', 'current-password', '/_admin/recoverypw/label/current', 'current' ],
				[ 'new', 'new-password', '/_admin/recoverypw/label/new', 'pw' ],
				[ 'repeat', 'new-password', '/_admin/recoverypw/label/repeat', '' ],
			].forEach( function( row ) {

				const label = dc.createElement('label');
				label.className = 'nino-admin-field';

				const caption = dc.createElement('span');
				caption.textContent = Nino.content.getText( row[2] );
				label.appendChild( caption );

				const input = dc.createElement('input');
				input.type = 'password';
				input.id = 'recoverypw-'+ row[0];
				if( row[3] !== '' )
					input.dataset.field = row[3];
				input.setAttribute( 'autocomplete', row[1] );
				if( row[0] !== 'current' )
					input.minLength = 8;
				input.required = true;
				label.appendChild( input );

				fieldset.appendChild( label );
			} );

			form.appendChild( fieldset );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.id = 'recoverypw-save';
			saveBtn.className = 'nino-admin-btn-primary';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'recoverypw-msg';
			msg.className = 'nino-admin-actionbar-status';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.recoverypw._save() } );

			// Saved or not, and - for a refused field - which one
			Nino.admin.recoverypw._status = Nino.adminUi.status( msg, { saved : Nino.content.getText('/_admin/recoverypw/msg/saved') } );
			Nino.admin.recoverypw._status.bind( form );

			wrap.appendChild( form );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot('recoverypw');
		},

		/**
		 *	Send the old and the new password. A repeat that differs sends
		 *	nothing. On success the three fields are emptied - a password
		 *	does not stay in a form - and on a failure they are kept, so a
		 *	mistyped old one is typed once, not three times
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the password was changed, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = Nino.admin.recoverypw._status;
			const button = dc.getElementById('recoverypw-save');
			const current = dc.getElementById('recoverypw-current');
			const fresh = dc.getElementById('recoverypw-new');
			const repeat = dc.getElementById('recoverypw-repeat');

			if( fresh.value !== repeat.value ) {
				line.fail( Nino.content.getText('/_admin/recoverypw/error/mismatch') );
				report( false );
				return;
			}

			line.saving();
			button.disabled = true;

			Nino.admin.recoverypw._apiCall( 'save', { current : current.value, pw : fresh.value }, function( status, response ) {

				button.disabled = false;

				if( status !== 200 || response === null ) {
					line.error( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				current.value = '';
				fresh.value = '';
				repeat.value = '';
				line.saved();
				if( typeof Nino.admin.dirty === 'object' )
					Nino.admin.dirty.snapshot('recoverypw');
				report( true );
			} );
		},
	};

	// The shell asks before anything throws a typed password away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'recoverypw', function() { return dc.getElementById('recoverypw-form') }, function( done ) { Nino.admin.recoverypw._save( done ) } );

})(window, document, document.documentElement, document.body);
