

/**
 *	Nino										A compact filesystembased php framework
 *	Maintenance							The Maintenance panel: one pane, one form - the switch and
 *													the Retry-After seconds, with what the switch does and who
 *													still sees the site behind its (?). See Admin/Admin.php
 *													beside it for the two actions this renders and posts to. While the
 *													switch is on, the notice every screen of the
 *													workbench carries is drawn from here too (_banner()).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.maintenance = {

		_ready 			: false,
		// The line of the bar: what is saving and why a save was refused (see Nino.adminUi.status()); drawn again with the form
		_line 			: null,
		// The bounds of the seconds the server answered with, and how many sentences of a wrong field were drawn
		_bounds 		: { min : undefined, max : undefined },
		_errors 		: 0,

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

				// Whatever is on the form, the site is in the state the server
				// says
				Nino.admin.maintenance._banner( response.status === true );

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
		 *	The notice that the site shows the maintenance page, at the top of
		 *	the workbench's content column - above every panel, not in this
		 *	one's pane, since it is for whoever is working anywhere in it.
		 *	One at most, put there or taken away as the state says. Drawn for
		 *	the accounts that have the panel - the status answer is theirs -
		 *	which is the audience of the dashboard tile as well
		 *
		 *	@param		{boolean}		on
		 *
		 *	@return		void
		 */
		_banner : function( on ) {

			// The workbench asks less before a change visitors see while they
			// see the maintenance page instead (see Nino.admin.mode.policy())
			if( typeof Nino.admin.mode === 'object' )
				Nino.admin.mode.maintenance( on === true );

			const before = dc.getElementById('maintenance-banner');

			if( on !== true ) {
				if( before !== null )
					before.remove();
				return;
			}

			const column = dc.getElementById('admin-content-wrap');

			if( before !== null || column === null )
				return;

			const notice = Nino.adminUi.notice( Nino.content.getText('/_admin/maintenance/banner/text'), {
				href	: '#maintenance',
				label	: Nino.content.getText('/_admin/maintenance/banner/link'),
			} );
			notice.id = 'maintenance-banner';

			const heading = column.querySelector(':scope > h1');

			if( heading !== null )
				heading.insertAdjacentElement( 'afterend', notice );
			else
				column.insertBefore( notice, column.firstChild );
		},

		/**
		 *	The whole pane: the form and its bar
		 *
		 *	@param		{Element}		wrap
		 *	@param		{Object}		data					{ status, retry, min, max }
		 *
		 *	@return		void
		 */
		_render : function( wrap, data ) {

			wrap.innerHTML = '';
			Nino.admin.maintenance._bounds = { min : data.min, max : data.max };

			const form = dc.createElement('form');
			form.noValidate = true;
			const fieldset = dc.createElement('fieldset');

			// What the switch does and who still sees the site: one sentence behind the (?) at its name
			fieldset.appendChild( Nino.adminUi.switchField( {
				key 			: 'status',
				checked 	: data.status === true,
				label 		: Nino.content.getText('/_admin/maintenance/label/status'),
				help 			: Nino.content.getText('/_admin/maintenance/hint/status')+ ' '+ Nino.content.getText('/_admin/maintenance/hint/signedin'),
			} ) );

			fieldset.appendChild( Nino.adminUi.numberField( {
				key 	: 'retry',
				value	: data.retry,
				min 	: data.min,
				max 	: data.max,
				unit 	: 'seconds',
				label	: '/_admin/maintenance/label/retry',
				help 	: '/_admin/maintenance/hint/retry',
			} ) );

			form.appendChild( fieldset );

			// The one bar of the view: the badge and the line, and Save as the one main button
			const msg = dc.createElement('p');
			msg.id = 'maintenance-form-msg';

			const bar = Nino.adminUi.actionBar( { status : { line : msg }, primary : Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit' } ) } );
			Nino.admin.maintenance._line = bar.line;
			bar.line.bind( form );

			form.appendChild( bar.el );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.maintenance._save() } );

			wrap.appendChild( form );

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
			const line = Nino.admin.maintenance._line;
			const statusInput = wrap.querySelector('[data-key="status"]');
			const retryInput = wrap.querySelector('[data-key="retry"]');

			// Checked when saving: what is wrong is said at its field and in the line, with the focus on it
			Nino.adminUi.clearFieldErrors( wrap );

			const bounds = Nino.admin.maintenance._bounds;
			const value = retryInput.value.trim();
			const number = Number( value );

			if( /^-?\d+$/.test( value ) === false || ( Number.isFinite( bounds.min ) === true && number < bounds.min ) || ( Number.isFinite( bounds.max ) === true && number > bounds.max ) ) {

				const name = Nino.content.getText('/_admin/maintenance/label/retry');

				Nino.adminUi.fieldError( retryInput.closest('label') ?? retryInput.parentNode, retryInput, 'maintenance-error-'+ ( ++Nino.admin.maintenance._errors ),
					Number.isFinite( bounds.min ) === true && Number.isFinite( bounds.max ) === true
						? Nino.adminUi.format( Nino.content.getText('/_admin/maintenance/error/range'), name, bounds.min, bounds.max )
						: Nino.adminUi.format( Nino.content.getText('/_admin/maintenance/error/int'), name ) );
				line.fail( Nino.content.getText('/_admin/maintenance/error/fields') );
				retryInput.focus();
				return report( false );
			}

			line.saving();

			Nino.admin.maintenance._apiCall( 'set', {
				status	: statusInput.checked,
				retry		: retryInput.value.trim(),
			}, function( status, response ) {

				if( status !== 200 || response === null ) {
					line.error( status, response, '/_admin/common/error/save' );

					// A sentence of the server about the seconds stands at their field too
					if( response !== null && typeof response === 'object' && response.field === 'retry' ) {
						Nino.adminUi.fieldError( retryInput.closest('label') ?? retryInput.parentNode, retryInput, 'maintenance-error-'+ ( ++Nino.admin.maintenance._errors ), Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
						retryInput.focus();
					}

					report( false );
					return;
				}

				line.saved();

				// Re-rendered from the answer rather than left as typed: it
				// carries the state config.php now actually holds
				Nino.admin.maintenance._render( wrap, response );
				Nino.admin.maintenance._banner( response.status === true );
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
