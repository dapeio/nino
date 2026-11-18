

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	lockout.js							"Login protection" tab of the Users panel: the two
 *													numbers of the throttle in front of the login, as one
 *													form, and below them the accounts locked right now,
 *													each with a button that lifts its lock. See
 *													Lockout/Lockout.php beside it for the schema this
 *													renders and validates against. A number is checked
 *													before it is sent and said under its field - the
 *													form is novalidate.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.lockout = {

		// Whether the form has been built once - see showCurrent()
		_ready : false,

		// The status line of the form's bar - see Nino.adminUi.status(). Drawn again with the form
		_line : null,

		// The schema the form on screen was drawn from, which its numbers are checked against
		_fields : [],

		/**
		 *	Load the schema plus current values and render the form
		 *
		 *	@param		{Function}	[drawn]				Called once the form is drawn
		 *
		 *	@return		void
		 */
		init : function( drawn ) {

			const wrap = dc.getElementById('lockout-form');
			if( wrap === null )
				return;

			Nino.admin.lockout._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.lockout._showError( wrap, status, response );

				Nino.admin.lockout._render( response.fields, response.locked || [] );
				Nino.admin.lockout._ready = true;

				if( typeof drawn === 'function' )
					drawn();
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
					Nino.admin.lockout._renderLocked( response.locked );
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
			Nino.admin.lockout._fields = fields;

			const form = dc.createElement('form');
			form.noValidate = true;

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/nav/lockout');
			fieldset.appendChild( legend );

			// What the section is about stands behind the (?) at its title
			Nino.adminUi.sectionHelp( legend, fieldset, Nino.content.getText('/_admin/lockout/intro') );

			fields.forEach( function( field ) {
				fieldset.appendChild( Nino.adminUi.numberField( field ) );
			} );

			form.appendChild( fieldset );

			// No data-key anywhere in it: the save posts every [data-key] of this form
			const lockedSet = dc.createElement('fieldset');
			lockedSet.id = 'lockout-locked';
			form.appendChild( lockedSet );

			const msg = dc.createElement('p');
			msg.id = 'lockout-form-msg';
			const bar = Nino.adminUi.actionBar( { status : { line : msg }, primary : Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit' } ) } );

			Nino.admin.lockout._line = bar.line;
			bar.line.bind( form );
			form.appendChild( bar.el );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.lockout._save() } );

			wrap.appendChild( form );

			Nino.admin.lockout._renderLocked( locked );

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
		 *
		 *	@return		void
		 */
		_renderLocked : function( locked ) {

			const fieldset = dc.getElementById('lockout-locked');
			if( fieldset === null )
				return;

			fieldset.innerHTML = '';

			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/lockout/locked/title');
			fieldset.appendChild( legend );

			// What went wrong lifting a lock stands under the list; what went well is a toast
			const line = dc.createElement('p');
			const status = Nino.adminUi.status( line );

			if( locked.length === 0 )
				fieldset.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/lockout/locked/empty') ) );
			else
				fieldset.appendChild( Nino.adminUi.list( locked.map( function( account ) {

					// An account that holds what the signed-in one does not is not theirs
					// to open (see Users\Admin::beyond()): listed, with no button
					const until = Nino.adminUi.format( Nino.content.getText('/_admin/lockout/locked/until'), account.until );
					const unlock = account.canChange === false ? null : Nino.adminUi.button( Nino.content.getText('/_admin/lockout/label/unlock'), function() { Nino.admin.lockout._unlock( account.mail, unlock, status ) }, { kind : 'secondary', small : true } );

					return {
						key			: account.mail,
						title		: account.mail,
						detail	: unlock === null ? until+ ' · '+ Nino.content.getText('/_admin/lockout/label/beyond') : until,
						actions	: unlock === null ? [] : [ unlock ],
					};
				} ), { label : Nino.content.getText('/_admin/lockout/locked/title') } ) );

			fieldset.appendChild( line );
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

				// The focus goes to the button of the row that took the place of the one that left, else the one before it, else the empty state
				const before = Array.from( dc.getElementById('lockout-locked').querySelectorAll('button') );
				const at = before.indexOf( button );

				Nino.admin.lockout._renderLocked( response.locked || [] );

				const after = Array.from( dc.getElementById('lockout-locked').querySelectorAll('button') );
				const next = after.length === 0 ? dc.getElementById('lockout-locked').querySelector('.nino-admin-empty') : after[Math.min( Math.max( at, 0 ), after.length - 1 )];

				if( next !== null && next !== undefined ) {
					if( after.length === 0 )
						next.setAttribute( 'tabindex', '-1' );

					next.focus();
				}

				Nino.adminUi.say( Nino.adminUi.format( Nino.content.getText('/_admin/lockout/msg/unlocked'), mail ) );
			} );
		},

		/**
		 *	Check both numbers before they are sent: a number that is no whole number in the bounds of its field says
		 *	so under the field, the line of the bar says it once, and the first takes the focus. The form is novalidate,
		 *	so the browser's own bubble never speaks
		 *
		 *	@param		{Element}		form
		 *	@param		{Array}			fields				The schema the form was drawn from: [ { key, min, max, label, ... } ]
		 *	@param		{Object}		line					The status line of the form's bar
		 *
		 *	@return		{boolean}									Whether every number is fine
		 */
		_check : function( form, fields, line ) {

			Nino.adminUi.clearFieldErrors( form );

			let first = null;

			fields.forEach( function( field ) {

				const input = form.querySelector( '[data-key="'+ field.key+ '"]' );
				const name = Nino.adminUi.text( field.label );
				const value = input.value.trim();
				const number = Number( value );

				let sentence = '';

				if( value === '' )
					sentence = Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), name );
				else if( /^[0-9]+$/.test( value ) === false || number < field.min || number > field.max )
					sentence = Nino.adminUi.format( Nino.content.getText('/_admin/users/error/invalid'), name, Nino.adminUi.format( Nino.content.getText('/_admin/lockout/expect/range'), field.min, field.max ) );

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( input.closest('label'), input, 'lockout-error-'+ field.key.replace( /[^a-z0-9]+/g, '-' ), sentence );

				if( first === null )
					first = input;
			} );

			if( first === null )
				return true;

			line.fail( Nino.content.getText('/_admin/users/error/fields') );
			first.focus();

			return false;
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

			const line = Nino.admin.lockout._line;

			if( Nino.admin.lockout._check( dc.getElementById('lockout-form').querySelector('form'), Nino.admin.lockout._fields, line ) === false ) {
				report( false );
				return;
			}

			const fields = {};

			Array.prototype.slice.call( dc.querySelectorAll('#lockout-form [data-key]') ).forEach( function( el ) {
				fields[el.dataset.key] = el.value.trim();
			} );

			line.saving();

			Nino.admin.lockout._apiCall( 'save', { fields : fields }, function( status, response ) {

				if( status !== 200 || response === null ) {
					line.error( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				if( typeof Nino.admin.dirty === 'object' )
					Nino.admin.dirty.snapshot( 'lockout' );

				// The form is drawn again from what the server holds, and the new bar says it
				Nino.admin.lockout.init( function() { Nino.admin.lockout._line.saved() } );
				report( true );
			} );
		},
	};

	// The shell asks before anything throws the form's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'lockout', function() { return dc.getElementById('lockout-form') }, function( done ) { Nino.admin.lockout._save( done ) } );

})(window, document, document.documentElement, document.body);
