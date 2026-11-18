/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	recoverypw.js						"Recovery password" tab of the Users panel: the secret
 *													_admin/recovery.php asks for, changed with the old one.
 *													A form on the tab, novalidate: what is missing, too
 *													short or not repeated is said under the field. See
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
		 *	Whether the form holds input nobody has saved - the shell says (see
		 *	Nino.admin.dirty), and so the line says "unsaved" while it does and
		 *	not after a Discard has put the fields back. A shell without the
		 *	registry cannot tell, and what was typed is then taken as unsaved
		 *
		 *	@return		{boolean}
		 */
		_holdsInput : function() {
			return typeof Nino.admin.dirty !== 'object' || Nino.admin.dirty.isDirty( [ 'recoverypw' ] ) === true;
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
		 *	One fieldset with the three password fields, what it is for behind the (?) at its title, and Save in the
		 *	pinned action bar
		 *
		 *	@param		{Element}		wrap
		 *
		 *	@return		void
		 */
		_render : function( wrap ) {

			wrap.innerHTML = '';

			const form = dc.createElement('form');
			form.noValidate = true;

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/nav/recoverypw');
			fieldset.appendChild( legend );

			// What the tab is about stands behind the (?) at its title
			Nino.adminUi.sectionHelp( legend, fieldset, Nino.content.getText('/_admin/recoverypw/intro') );

			// No data-key: the three are read by id, by name, never as a set. The
			// data-field is the name the server gives a refusal (see apiSave()), so
			// the input it is about is marked
			[
				{ id : 'current', label : '/_admin/recoverypw/label/current', field : 'current', autocomplete : 'current-password' },
				{ id : 'new', label : '/_admin/recoverypw/label/new', help : '/_admin/recoverypw/hint/new', field : 'pw', autocomplete : 'new-password', minLength : 8 },
				{ id : 'repeat', label : '/_admin/recoverypw/label/repeat', field : '', autocomplete : 'new-password', minLength : 8 },
			].forEach( function( row ) {

				const label = Nino.adminUi.textField( {
					id					: 'recoverypw-'+ row.id,
					type				: 'password',
					label				: Nino.content.getText( row.label ),
					help				: row.help === undefined ? undefined : Nino.content.getText( row.help ),
					required		: true,
					minLength		: row.minLength,
					autocomplete	: row.autocomplete,
				} );

				if( row.field !== '' )
					label.querySelector('input').dataset.field = row.field;

				fieldset.appendChild( label );
			} );

			form.appendChild( fieldset );

			const msg = dc.createElement('p');
			msg.id = 'recoverypw-msg';
			const bar = Nino.adminUi.actionBar( { status : { line : msg }, primary : Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit', id : 'recoverypw-save' } ) } );

			// Saved or not, and - for a refused field - which one
			Nino.admin.recoverypw._status = bar.line;
			bar.line.bind( form, Nino.admin.recoverypw._holdsInput );

			form.appendChild( bar.el );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.recoverypw._save() } );

			wrap.appendChild( form );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot('recoverypw');
		},

		/**
		 *	Say under a field what is wrong with it and put the focus there
		 *
		 *	@param		{Element}		input
		 *	@param		{string}		sentence
		 *
		 *	@return		void
		 */
		_wrong : function( input, sentence ) {
			Nino.adminUi.fieldError( input.closest('label'), input, 'recoverypw-error-'+ input.id, sentence );
		},

		/**
		 *	Check the three fields before anything is sent: a missing one, a new password that is too short and a repeat
		 *	that differs say what is wrong under the field, the line of the bar says so once, and the first takes the
		 *	focus. The form is novalidate, so the browser's own bubble never speaks
		 *
		 *	@param		{Element}		form
		 *
		 *	@return		{boolean}									Whether every field is fine
		 */
		_check : function( form ) {

			Nino.adminUi.clearFieldErrors( form );

			const missing = function( key ) { return Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), Nino.content.getText( key ) ) };
			const current = dc.getElementById('recoverypw-current');
			const fresh = dc.getElementById('recoverypw-new');
			const repeat = dc.getElementById('recoverypw-repeat');
			const wrong = [];

			if( current.value === '' )
				wrong.push( [ current, missing('/_admin/recoverypw/label/current') ] );

			if( fresh.value === '' )
				wrong.push( [ fresh, missing('/_admin/recoverypw/label/new') ] );
			else if( fresh.value.length < fresh.minLength )
				wrong.push( [ fresh, Nino.adminUi.format( Nino.content.getText('/_admin/users/error/invalid'), Nino.content.getText('/_admin/recoverypw/label/new'), Nino.adminUi.format( Nino.content.getText('/_admin/users/expect/password'), fresh.minLength ) ) ] );

			if( repeat.value === '' )
				wrong.push( [ repeat, Nino.content.getText('/_admin/recoverypw/error/repeat-missing') ] );
			else if( fresh.value !== repeat.value )
				wrong.push( [ repeat, Nino.content.getText('/_admin/recoverypw/error/mismatch') ] );

			wrong.forEach( function( found ) { Nino.admin.recoverypw._wrong( found[0], found[1] ) } );

			if( wrong.length === 0 )
				return true;

			Nino.admin.recoverypw._status.fail( Nino.content.getText('/_admin/users/error/fields') );
			wrong[0][0].focus();

			return false;
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

			if( Nino.admin.recoverypw._check( current.closest('form') ) === false ) {
				report( false );
				return;
			}

			line.saving();
			button.disabled = true;

			Nino.admin.recoverypw._apiCall( 'save', { current : current.value, pw : fresh.value }, function( status, response ) {

				button.disabled = false;

				if( status !== 200 || response === null ) {

					Nino.adminUi.clearFieldErrors( current.closest('form') );
					line.error( status, response, '/_admin/common/error/save' );

					// The refusal that names a field says it under that field too
					const named = response !== null && typeof response === 'object' ? [ current, fresh ].find( function( input ) { return input.dataset.field === response.field } ) : undefined;

					if( named !== undefined )
						Nino.admin.recoverypw._wrong( named, Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );

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
