/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 2, "Languages": pick the available locales and a
 *													native locale among them, and assemble the project from
 *													them (routes, templates, text). The step key stays
 *													`setup` - so do Setup::units(), the `setup/apply`
 *													action and Install.php's Setup class; only the label
 *													the rail shows is the narrower name.
 *
 *													It also carries the module picker, hidden until a
 *													project module with an install unit is found (see
 *													#setup-modules-card) - nothing in a fresh checkout,
 *													which is why the step is named for the half that is
 *													always there. Pages are no step of the wizard; this
 *													one only ever touches base + the picked module units. Driven by the shared Back/Next bar
 *													(script.js) rather than its own save button - apply()
 *													is exposed for Next to call, not wired to a button.
 *
 *													The fields are the blocks of the workbench: a list of ticks for the
 *													languages and for the modules, a select for the native language. A
 *													language is named as the workbench names one ("Deutsch", "English (US)").
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.setup = {

		_ready 		: false,
		_library 	: null,

		// What is ticked and chosen, kept between two drawings: { locales, native, modules }, null until the person or the library says
		_state		: null,

		// The parts that are on the page: { locales, native, modules }
		_ui				: null,

		/**
		 *	Load the library's available locales/modules - each flagged
		 *	with whether it's currently active - and render one pre-checked
		 *	list of ticks per group
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('setup-content');
			if( wrap === null )
				return;

			Nino.install.apiCall( 'setup/library', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );

				const lib = response;

				Nino.install.setup._library = lib;
				Nino.install.setup._state = {
					locales	: lib.locales.filter( function( code ) { return lib.activeLocales.indexOf( code ) !== -1 } ),
					native	: lib.nativeLocale,
					modules	: Object.keys( lib.modules ).filter( function( key ) { return lib.modules[key].active === true } ),
				};
				Nino.install.setup._render();
				Nino.install.setup._ready = true;
			} );
		},

		showCurrent : function() {
			if( Nino.install.setup._ready === false )
				Nino.install.setup.init();
		},

		retext : function() {
			if( Nino.install.setup._library !== null )
				Nino.pageText.keep( dc.getElementById('setup-content'), Nino.install.setup._render );
		},

		/**
		 *	What is ticked and chosen now, read from the page into the state: before a change of the language
		 *	draws the fields again, and before the choice is sent
		 *
		 *	@return		void
		 */
		_collect : function() {

			const ui = Nino.install.setup._ui;

			if( ui === null )
				return;

			Nino.install.setup._state = {
				locales	: ui.locales.value(),
				native	: ui.native.querySelector('select').value,
				modules	: ui.modules === null ? Nino.install.setup._state.modules : ui.modules.value(),
			};
		},

		_render : function() {

			Nino.install.setup._collect();

			const t = Nino.install.t;
			const lib = Nino.install.setup._library;
			const state = Nino.install.setup._state;
			const wrap = dc.getElementById('setup-content');
			const ui = { modules : null };

			wrap.innerHTML = '';

			// Languages: the ticks, and the native one among the ticked
			const card = Nino.adminUi.el( 'fieldset', '' );
			const legend = Nino.adminUi.el( 'legend', '', t('/_admin/install/setup/title-languages') );
			const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );

			card.id = 'setup-languages';
			card.appendChild( legend );
			Nino.adminUi.sectionHelp( legend, card, t('/_admin/install/setup/help-languages') );

			ui.locales = Nino.adminUi.checklist( {
				key			: 'locale',
				label		: t('/_admin/install/setup/locales'),
				help		: t('/_admin/install/setup/locales-help'),
				required: true,
				options	: lib.locales.map( function( code ) { return { value : code, label : Nino.install.localeName( code, lib.locales ) } } ),
				value		: state.locales,
				onChange: function() { Nino.install.setup._refreshNative() },
			} );

			ui.native = Nino.install.setup._nativeField( state.locales, state.native );

			grid.appendChild( ui.locales.el );
			grid.appendChild( ui.native );
			card.appendChild( grid );
			wrap.appendChild( card );

			// Modules: nothing in a checkout - the card is there only where the library or the project brings one
			const moduleKeys = Object.keys( lib.modules );

			if( moduleKeys.length > 0 ) {

				const modules = Nino.adminUi.el( 'fieldset', '' );
				const modulesLegend = Nino.adminUi.el( 'legend', '', t('/_admin/install/setup/title-modules') );
				const modulesGrid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );

				modules.id = 'setup-modules-card';
				modules.appendChild( modulesLegend );
				Nino.adminUi.sectionHelp( modulesLegend, modules, t('/_admin/install/setup/help-modules') );

				ui.modules = Nino.adminUi.checklist( {
					key			: 'module',
					label		: t('/_admin/install/setup/modules'),
					options	: moduleKeys.map( function( key ) {
						const needs = ( lib.modules[key].requiresModules || [] ).join( ', ' );
						return { value : key, label : lib.modules[key].label || key, state : needs === '' ? undefined : t( '/_admin/install/setup/requires', needs ) };
					} ),
					value		: state.modules,
				} );

				modulesGrid.appendChild( ui.modules.el );
				modules.appendChild( modulesGrid );
				wrap.appendChild( modules );
			}

			Nino.install.setup._ui = ui;
		},

		/**
		 *	The select of the native language: only offers whichever locales are ticked
		 *
		 *	@param		{Array<string>}	picked				The ticked locales
		 *	@param		{string|null}		current				Selected if it is still among them
		 *
		 *	@return		{Element}
		 */
		_nativeField : function( picked, current ) {

			return Nino.adminUi.selectField( {
				key			: 'native',
				id			: 'setup-native-locale-select',
				label		: Nino.install.t('/_admin/install/setup/native'),
				help		: Nino.install.t('/_admin/install/setup/native-help'),
				options	: picked.map( function( code ) { return { value : code, label : Nino.install.localeName( code, picked ) } } ),
				value		: picked.indexOf( current ) !== -1 ? current : ( picked[0] ?? '' ),
			} );
		},

		/**
		 *	Rebuild the select of the native language from the locales ticked now: the one chosen stays if it is
		 *	still among them - called every time a tick of a language changes
		 *
		 *	@return		void
		 */
		_refreshNative : function() {

			const ui = Nino.install.setup._ui;
			const fresh = Nino.install.setup._nativeField( ui.locales.value(), ui.native.querySelector('select').value );

			ui.native.replaceWith( fresh );
			ui.native = fresh;
		},

		/**
		 *	Send the current (complete, replacing) selection to setup/apply -
		 *	called by the shared Next button, not by a button of its own
		 *
		 *	@param		{Function}	callback			Called with ( success )
		 *
		 *	@return		void
		 */
		apply : function( callback ) {

			const t = Nino.install.t;

			if( Nino.install.setup._ready !== true ) {
				Nino.install.say( t('/_admin/install/setup/loading'), true );
				callback( false );
				return;
			}

			Nino.install.setup._collect();
			Nino.adminUi.clearFieldErrors( dc.getElementById('setup-content') );

			const ui = Nino.install.setup._ui;
			const state = Nino.install.setup._state;

			// One language at least, said at the list of them: a project without one has nothing to say its text in
			if( state.locales.length === 0 ) {
				ui.locales.error( Nino.adminUi.format( t('/_admin/common/error/missing'), t('/_admin/install/setup/locales') ) );
				Nino.install.say( t('/_admin/install/error/fields'), true );
				ui.locales.focus();
				callback( false );
				return;
			}

			Nino.install.say( t('/_admin/install/setup/applying') );

			Nino.install.apiCall( 'setup/apply', {
				locales : state.locales,
				native 	: state.native,
				modules : state.modules,
			}, function( status, response ) {

				if( status !== 200 || response === null ) {

					// The refusal about the languages is about the list of them: said there, and in the line
					if( response !== null && typeof response === 'object' && response.code === 'install_locales' ) {
						ui.locales.error( Nino.install.failure( status, response ) );
						ui.locales.focus();
						Nino.install.say( t('/_admin/install/error/fields'), true );
					}
					else
						Nino.install.say( Nino.install.failure( status, response ), true );

					callback( false );
					return;
				}

				// This step's own active state, PersonalInfos' key list and the
				// applications' names (in the native language) are all
				// potentially stale now - reload on next visit
				Nino.install.setup._ready = false;
				if( Nino.install.personalinfos !== undefined )
					Nino.install.personalinfos._ready = false;
				if( Nino.install.applications !== undefined )
					Nino.install.applications._ready = false;

				callback( true );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
