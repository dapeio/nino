

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 2: pick available locales/modules - every module
 *													unit the installer finds, see Setup::units() - and a
 *													native locale among the ones picked, and assemble them
 *													into the real project (routes, templates, text). See
 *													_admin/install/Install.php's Setup class. Themes (see
 *													themes.js) and pages (see webpages.js) have their own
 *													steps - this one only ever touches base + the picked
 *													module units. Driven by the shared Back/Next bar
 *													(script.js) rather than its own save button - apply() is
 *													exposed for Next to call, not wired to a button here.
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

		/**
		 *	Load the library's available locales/modules - each flagged
		 *	with whether it's currently active - and render one pre-checked
		 *	checkbox list per group
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('setup-locales');
			if( wrap === null )
				return;

			Nino.install.apiCall( 'setup/library', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );

				Nino.install.setup._library = response;
				Nino.install.setup._render();
				Nino.install.setup._ready = true;
			} );
		},

		showCurrent : function() {
			if( Nino.install.setup._ready === false )
				Nino.install.setup.init();
		},

		_render : function() {

			const lib = Nino.install.setup._library;

			Nino.install.setup._renderList( 'setup-locales', lib.locales.map( function( code ) {
				return { key : code, label : code, active : lib.activeLocales.indexOf( code ) !== -1 };
			} ), 'locale' );

			Nino.install.setup._renderList( 'setup-modules', Object.keys( lib.modules ).map( function( key ) {
				return Object.assign( { key : key }, lib.modules[key] );
			} ), 'module' );

			Nino.install.setup._renderNativeLocale( lib.nativeLocale );
		},

		/**
		 *	Build the "Native Locale" <select> - only ever offers whichever
		 *	locales are currently checked above, kept in sync by a change
		 *	listener _renderList() attaches to every locale checkbox (see
		 *	_refreshNativeLocale())
		 *
		 *	@param		{string|null}	nativeLocale	Setup::apiLibrary()'s current '/nino/locales/native'
		 *
		 *	@return		void
		 */
		_renderNativeLocale : function( nativeLocale ) {

			const wrap = dc.getElementById('setup-native-locale');
			if( wrap === null )
				return;

			const select = dc.createElement('select');
			select.id = 'setup-native-locale-select';

			wrap.innerHTML = '';
			wrap.appendChild( select );

			Nino.install.setup._refreshNativeLocale( nativeLocale );
		},

		/**
		 *	Rebuild the Native Locale <select>'s options from whichever
		 *	locale checkboxes are currently checked - called on init (with
		 *	the persisted native locale as $preferred) and again every time
		 *	a locale checkbox is toggled (with no $preferred, so the
		 *	currently-selected option carries over if it's still available)
		 *
		 *	@param		{string}	[preferred]		Selected if still among the checked locales
		 *
		 *	@return		void
		 */
		_refreshNativeLocale : function( preferred ) {

			const select = dc.getElementById('setup-native-locale-select');
			if( select === null )
				return;

			const current = ( preferred !== undefined && preferred !== null ) ? preferred : select.value;
			const picked 	= Nino.install.setup._picked( 'locale' );

			select.innerHTML = '';
			picked.forEach( function( code ) {
				const option = dc.createElement('option');
				option.value = code;
				option.textContent = code;
				option.selected = code === current;
				select.appendChild( option );
			} );

			// Nothing checked (yet) leaves the select empty until at least
			// one locale is - "Next" rejects an empty locale selection
			// either way, see Setup::apiApply()'s docblock
			if( picked.indexOf( current ) === -1 && picked.length > 0 )
				select.value = picked[0];
		},

		/**
		 *	@param		{string}	containerId
		 *	@param		{Array}		items				[ { key, label, active, requiresModules? }, ... ]
		 *	@param		{string}	kind				'locale' | 'module' - tags each checkbox for apply()
		 *
		 *	@return		void
		 */
		_renderList : function( containerId, items, kind ) {

			const wrap = dc.getElementById( containerId );
			wrap.innerHTML = '';

			items.forEach( function( item ) {

				// One .nino-admin-checklist row: the label is the row, and the
				// module requirement is its trailing state - the wrapper this
				// used to carry stacked a second row height on top of the
				// shared one and made a short list read as a sparse page
				const label = dc.createElement('label');

				const input = dc.createElement('input');
				input.type = 'checkbox';
				input.value = item.key;
				input.checked = item.active === true;
				input.dataset.kind = kind;
				if( kind === 'locale' )
					input.addEventListener( 'change', function() { Nino.install.setup._refreshNativeLocale() } );
				label.appendChild( input );

				const span = dc.createElement('span');
				span.textContent = item.label || item.key;
				label.appendChild( span );

				if( ( item.requiresModules || [] ).length > 0 ) {
					const hint = dc.createElement('span');
					hint.className = 'nino-admin-checklist-state';
					hint.textContent = 'requires modules: '+ item.requiresModules.join( ', ' );
					label.appendChild( hint );
				}

				wrap.appendChild( label );
			} );
		},

		/**
		 *	Collect every checked box of one kind
		 *
		 *	@param		{string}	kind				'locale' | 'module'
		 *
		 *	@return		{Array}
		 */
		_picked : function( kind ) {
			return Array.prototype.slice.call( dc.querySelectorAll( 'input[data-kind="'+ kind+ '"]:checked' ) ).map( function( el ) { return el.value } );
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

			const msg = dc.getElementById('setup-msg');

			if( Nino.install.setup._ready !== true ) {
				msg.textContent = 'Setup options are still loading.';
				callback( false );
				return;
			}

			msg.textContent = 'Applying …';

			const nativeSelect = dc.getElementById('setup-native-locale-select');

			Nino.install.apiCall( 'setup/apply', {
				locales : Nino.install.setup._picked( 'locale' ),
				native 	: ( nativeSelect !== null ) ? nativeSelect.value : '',
				modules : Nino.install.setup._picked( 'module' ),
			}, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to apply.' );
					callback( false );
					return;
				}

				msg.textContent = 'Applied - modules: '+ ( response.modules.join( ', ' ) || 'none' )+ '.';

				// This step's own active state, Webpages' available
				// templates/module hints and PersonalInfos' key list are
				// all potentially stale now - reload on next visit
				Nino.install.setup._ready = false;
				if( Nino.install.webpages !== undefined )
					Nino.install.webpages._ready = false;
				if( Nino.install.personalinfos !== undefined )
					Nino.install.personalinfos._ready = false;

				callback( true );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
