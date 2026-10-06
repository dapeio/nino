/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Settings" tab of a feature's own panel: the form
 *													the feature's manifest declares, drawn beside the
 *													screens it belongs to instead of behind the Features
 *													panel. One script for every such tab - the registry
 *													builds one per feature with settings and a panel
 *													(see Settings/Settings.php), each with a mount named
 *													feature-settings-<key>, and each mount gets a
 *													namespace of its own below Nino.admin, named by its
 *													tab's uri, which is what the shell asks to show it.
 *
 *													Nothing of the form is written here: the fields come
 *													from features/list, are drawn by the Features
 *													panel's own renderers (admin.js, loaded before this
 *													file) and saved through features/settings, the same
 *													two actions and the same permission as the screen
 *													this tab replaces.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	const PREFIX = 'feature-settings-';

	// The bundle is loaded after the panes are rendered, so every mount is there
	Array.prototype.slice.call( dc.querySelectorAll('[id^="'+ PREFIX+ '"]') ).forEach( function( mount ) {

		const tab = mount.closest('[data-tab]');

		if( tab === null )
			return;

		const key	= mount.id.slice( PREFIX.length );
		const name	= tab.dataset.tab;

		// Whether the form was fetched - a tab shown again keeps what was typed
		let started = false;
		let current = null;

		const call = function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'features/'+ endpoint, payload, callback );
		};

		/**
		 *	Draw the form of one feature entry: its fields, and the bar with
		 *	Save and the line that says how it went
		 *
		 *	@param		{Object}	feature				An entry of features/list
		 *	@param		{string}	[message]			What the line says at first
		 *
		 *	@return		void
		 */
		const render = function( feature, message ) {

			mount.innerHTML = '';

			const form = dc.createElement('form');
			form.dataset.feature = feature.key;
			form.appendChild( Nino.admin.features._renderSettings( feature, true ) );

			const bar = Nino.adminUi.actionBar( dc.createElement('div') );

			const save = dc.createElement('button');
			save.type = 'submit';
			save.className = 'nino-admin-btn-primary';
			save.textContent = Nino.content.getText('/_admin/common/label/save');
			bar.appendChild( save );

			const msg = dc.createElement('p');
			msg.setAttribute( 'aria-live', 'polite' );
			msg.textContent = message || '';
			bar.appendChild( msg );

			form.appendChild( bar );
			mount.appendChild( form );

			current = { feature : feature, form : form, save : save, msg : msg };

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); submit() } );

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( name );
		};

		/**
		 *	Save the form in one request and draw it again from the entry the
		 *	answer carries - the values as config.php holds them now, and the
		 *	secret's hint saying it is set
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]
		 *
		 *	@return		void
		 */
		const submit = function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const parts = current;

			parts.save.disabled = true;
			parts.msg.classList.remove('nino-admin-error');
			parts.msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			call( 'settings', { key : key, fields : Nino.admin.features._collect( parts.form ) }, function( status, response ) {

				parts.save.disabled = false;

				if( status !== 200 || response === null || typeof response.feature !== 'object' ) {
					parts.msg.classList.add('nino-admin-error');
					parts.msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				render( response.feature, Nino.content.getText('/_admin/common/msg/saved') );
				report( true );
			} );
		};

		/**
		 *	Fetch the feature's entry and draw the form
		 *
		 *	@return		void
		 */
		const start = function() {

			started = true;

			call( 'list', {}, function( status, response ) {

				const feature = status === 200 && response !== null && Array.isArray( response.features ) === true
					? response.features.filter( function( f ) { return f.key === key } )[0]
					: undefined;

				if( feature === undefined ) {
					// Asked for again the next time the tab is shown. A list that
					// answers without the key is a feature that is gone: 404, not 200
					started = false;
					Nino.adminUi.showError( mount, status === 200 ? 404 : status, response, '/_admin/common/error/load' );
					return;
				}

				render( feature, '' );
			} );
		};

		Nino.admin[name] = {

			/*	The shell calls this every time the tab is shown. The form is
				fetched once; showing the tab again must not draw it from the
				server's values over what was typed	*/
			showCurrent : function() {
				if( started === false )
					start();
			},
		};

		if( typeof Nino.admin.dirty === 'object' )
			Nino.admin.dirty.watchForm( name, function() { return current === null ? null : current.form }, function( done ) { submit( done ) } );
	} );

})(window, document, document.documentElement, document.body);
