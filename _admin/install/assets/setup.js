/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 2, "Language": pick the language of the website - one - and
 *													assemble the project from it (routes, templates, text). The
 *													step key stays `setup` - so do Setup::units(), the `setup/apply`
 *													action and Install.php's Setup class; the API still takes a list
 *													of languages and the native one, and the step sends a list of one:
 *													further languages are added in the workbench's Languages panel.
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
 *													The fields are the blocks of the workbench: a select for the
 *													language and a list of ticks for the modules. A language is named
 *													as the workbench names one ("Deutsch", "English (US)").
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

		// What is ticked and chosen, kept between two drawings: { locale, modules }, null until the person or the library says
		_state		: null,

		// The parts that are on the page: { locale, modules }
		_ui				: null,

		/**
		 *	Load the library's locales and modules - the modules each flagged
		 *	with whether it's currently active - and render the select of the
		 *	language and the pre-checked list of ticks of the modules. A project
		 *	that has not answered this step yet starts on the language of the
		 *	page, where the library has texts in it; one that has keeps its own
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
					locale	: Nino.install.setup._startLocale( lib ),
					modules	: Object.keys( lib.modules ).filter( function( key ) { return lib.modules[key].active === true } ),
				};
				Nino.install.setup._render();
				Nino.install.setup._ready = true;
			} );
		},

		/**
		 *	The language the select starts on: the one the project has, once it has answered this step; before that the one of the page
		 *	- the library's locale that begins with its language code - and else the one the project has by default
		 *
		 *	@param		{Object}		lib						The answer of setup/library
		 *
		 *	@return		{string}
		 */
		_startLocale : function( lib ) {

			const language = Nino.pageText.language();
			const ofPage = lib.locales.filter( function( code ) { return code.split('_')[0] === language } )[0];

			if( lib.decided === false && ofPage !== undefined )
				return ofPage;

			return lib.locales.indexOf( lib.nativeLocale ) !== -1 ? lib.nativeLocale : ( lib.locales[0] ?? '' );
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
		 *	What is chosen and ticked now, read from the page into the state: before a change of the language
		 *	of the page draws the fields again, and before the choice is sent
		 *
		 *	@return		void
		 */
		_collect : function() {

			const ui = Nino.install.setup._ui;

			if( ui === null )
				return;

			Nino.install.setup._state = {
				locale	: ui.locale.querySelector('select').value,
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

			// The language of the website: one of the library's
			const card = Nino.adminUi.el( 'fieldset', '' );
			const legend = Nino.adminUi.el( 'legend', '', t('/_admin/install/setup/title-languages') );
			const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );

			card.id = 'setup-languages';
			card.appendChild( legend );
			Nino.adminUi.sectionHelp( legend, card, t('/_admin/install/setup/help-languages') );

			ui.locale = Nino.adminUi.selectField( {
				key			: 'locale',
				id			: 'setup-locale-select',
				label		: t('/_admin/install/setup/language'),
				help		: t('/_admin/install/setup/language-help'),
				options	: lib.locales.map( function( code ) { return { value : code, label : Nino.install.localeName( code, lib.locales ) } } ),
				value		: lib.locales.indexOf( state.locale ) !== -1 ? state.locale : ( lib.locales[0] ?? '' ),
			} );

			grid.appendChild( ui.locale );
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

			const state = Nino.install.setup._state;

			// One language: the select always has one, unless the library has none to offer
			if( state.locale === '' ) {
				Nino.install.say( t('/_admin/install/error/fields'), true );
				callback( false );
				return;
			}

			Nino.install.say( t('/_admin/install/setup/applying') );

			Nino.install.apiCall( 'setup/apply', {
				locales : [ state.locale ],
				native 	: state.locale,
				modules : state.modules,
			}, function( status, response ) {

				if( status !== 200 || response === null ) {

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
