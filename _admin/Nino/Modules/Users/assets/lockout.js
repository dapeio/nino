

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	lockout.js							"Login protection" tab of the Users panel: the two
 *													numbers of the throttle in front of the login, as one
 *													form, and below them the accounts locked right now,
 *													each with a button that lifts its lock. See
 *													Lockout/Lockout.php beside it for the schema this
 *													renders and validates against.
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

		// Whether the form has been built once - see showCurrent()
		_ready : false,

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

				Nino.admin.lockout._render( response.fields, response.locked || [] );
				Nino.admin.lockout._ready = true;
			} );
		},

		/*	The shell calls this every time its panel is shown again, and the
			contract it documents is that switching panels never resets
			anything: "jumping back and forth is always exactly where you left
			it". Re-running init() broke that promise on the one screen where
			it costs the most - a form. A ticked switch, a typed number, a
			locale code half entered: the answer came back, the wrap was
			emptied and rebuilt from the server's values, and the edit was gone
			without a word. Nothing to do once the form is up: the shell
			un-hides the pane, the pane is where it was left. The actions that
			change state (save above all) re-fetch on their own. The one
			thing that is not the form's - who is locked, which changes by
			itself as cooldowns run out and others fail - is fetched again,
			and replaces only its own fieldset: the numbers are never rebuilt	*/
		showCurrent : function() {

			if( Nino.admin.lockout._ready === false ) {
				Nino.admin.lockout.init();
				return;
			}

			Nino.admin.lockout._apiCall( 'list', {}, function( status, response ) {
				if( status === 200 && response !== null && Array.isArray( response.locked ) === true )
					Nino.admin.lockout._renderLocked( response.locked, '' );
			} );
		},

		/**
		 *	Call a lockout/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "lockout/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'lockout/'+ endpoint, payload, callback );
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
		 *	One fieldset with both numbers, the one with the locked accounts
		 *	below it, and Save in the pinned action bar
		 *
		 *	@param		{Array}		fields			[ { key, min, max, unit, label, hint, value }, ... ]
		 *	@param		{Array}		locked			[ { mail, until }, ... ]
		 *
		 *	@return		void
		 */
		_render : function( fields, locked ) {

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

			// No data-key anywhere in it: the save posts every [data-key] of this form
			const lockedSet = dc.createElement('fieldset');
			lockedSet.id = 'lockout-locked';
			form.appendChild( lockedSet );

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

			Nino.admin.lockout._renderLocked( locked, '' );

			if( Nino.admin.lockout._pendingMsg !== '' ) {
				msg.textContent = Nino.admin.lockout._pendingMsg;
				Nino.admin.lockout._pendingMsg = '';
			}

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'lockout' );
		},

		/**
		 *	The locked accounts: who, until when, and a button that lifts the
		 *	lock. Fills the fieldset _render() made and nothing else - the
		 *	number form above it keeps what was typed into it
		 *
		 *	@param		{Array}		locked			[ { mail, until }, ... ]
		 *	@param		{string}	message			What to say under the list, '' for nothing
		 *
		 *	@return		void
		 */
		_renderLocked : function( locked, message ) {

			const fieldset = dc.getElementById('lockout-locked');
			if( fieldset === null )
				return;

			fieldset.innerHTML = '';

			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/lockout/locked/title');
			fieldset.appendChild( legend );

			const line = dc.createElement('p');
			line.className = 'nino-admin-hint';
			const status = Nino.adminUi.status( line );

			if( locked.length === 0 )
				fieldset.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/lockout/locked/empty') ) );
			else {

				const ul = dc.createElement('ul');
				ul.className = 'nino-admin-list nino-admin-list-dense';

				locked.forEach( function( account ) {

					const li = dc.createElement('li');

					const copy = dc.createElement('span');
					copy.className = 'nino-admin-list-copy';
					const mail = dc.createElement('strong');
					mail.textContent = account.mail;
					const until = dc.createElement('small');
					until.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/lockout/locked/until'), account.until );
					copy.appendChild( mail );
					copy.appendChild( until );
					li.appendChild( copy );

					const button = dc.createElement('button');
					button.type = 'button';
					button.className = 'nino-admin-btn-secondary';
					button.textContent = Nino.content.getText('/_admin/lockout/label/unlock');
					button.addEventListener( 'click', function() { Nino.admin.lockout._unlock( account.mail, button, status ) } );
					li.appendChild( button );

					ul.appendChild( li );
				} );

				fieldset.appendChild( ul );
			}

			fieldset.appendChild( line );

			if( message !== '' )
				status.idle( message );
		},

		/**
		 *	Lift one account's lock, then show what the server says is still
		 *	locked. A refusal keeps the row and says why
		 *
		 *	@param		{string}		mail
		 *	@param		{Element}		button				The row's button, off while the request is on its way
		 *	@param		{Object}		status				The line under the list (see Nino.adminUi.status())
		 *
		 *	@return		void
		 */
		_unlock : function( mail, button, status ) {

			button.disabled = true;

			Nino.admin.lockout._apiCall( 'unlock', { username : mail }, function( code, response ) {

				if( code !== 200 || response === null ) {
					button.disabled = false;
					status.error( code, response, '/_admin/lockout/error/unlock' );
					return;
				}

				Nino.admin.lockout._renderLocked( response.locked || [], Nino.adminUi.format( Nino.content.getText('/_admin/lockout/msg/unlocked'), mail ) );
			} );
		},

		/**
		 *	Save both numbers in one request, then reload so the form shows
		 *	the ints config.php now holds
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the numbers were written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const msg = dc.getElementById('lockout-form-msg');
			const fields = {};

			Array.prototype.slice.call( dc.querySelectorAll('#lockout-form [data-key]') ).forEach( function( el ) {
				fields[el.dataset.key] = el.value.trim();
			} );

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.lockout._apiCall( 'save', { fields : fields }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				Nino.admin.lockout._pendingMsg = Nino.content.getText('/_admin/common/msg/saved');
				if( typeof Nino.admin.dirty === 'object' )
					Nino.admin.dirty.snapshot( 'lockout' );
				Nino.admin.lockout.init();
				report( true );
			} );
		},
	};

	// The shell asks before anything throws the form's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'lockout', function() { return dc.getElementById('lockout-form') }, function( done ) { Nino.admin.lockout._save( done ) } );

})(window, document, document.documentElement, document.body);
