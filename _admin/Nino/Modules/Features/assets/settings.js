/**
 *	Nino										A compact filesystembased php framework
 *	settings.js							"Settings" tab of a feature's own panel: the form
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
		 *	Draw the form of one feature entry: its fields, and the bar of every form - the state, the line
		 *	that says how it went and Save as its one main button
		 *
		 *	@param		{Object}	feature				An entry of features/list
		 *
		 *	@return		void
		 */
		const render = function( feature ) {

			mount.innerHTML = '';

			const form = dc.createElement('form');
			form.noValidate = true;
			form.dataset.feature = feature.key;
			form.appendChild( Nino.admin.features._renderSettings( feature, true ) );

			const bar = Nino.adminUi.actionBar( { status : { line : dc.createElement('p') }, primary : Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit' } ) } );

			bar.line.bind( form );
			form.appendChild( bar.el );
			mount.appendChild( form );

			current = { feature : feature, form : form, line : bar.line };

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); submit() } );

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( name );
		};

		/**
		 *	Save the form in one request and draw it again from the entry the
		 *	answer carries - the values as config.php holds them now, and the
		 *	secret's note saying it is set; the new bar says it was saved
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]
		 *
		 *	@return		void
		 */
		const submit = function( done ) {

			const parts = current;
			const key = Nino.admin.features._focusKey();

			Nino.admin.features._save( parts.feature, parts.form, parts.line, function( response, typed ) {

				if( typeof response.feature !== 'object' || response.feature === null ) {
					parts.line.fail( Nino.content.getText('/_admin/common/error/save') );
					return false;
				}

				// Something was typed meanwhile: the form stays as it is, with what was typed, and says it has something to save
				if( typed === true ) {
					parts.feature = response.feature;
					parts.line.dirty();
					return true;
				}

				render( response.feature );
				current.line.saved();
				Nino.admin.features._refocus( current.form, key );
				return true;
			}, done );
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

				render( feature );
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
