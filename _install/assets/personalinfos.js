

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 4: bulk-fill the /company/* and /website/* text keys
 *													every project has (global.php + per-locale
 *													text/<locale>.php) in one form instead of clicking through
 *													_editor's Text panel one key at a time - each with a
 *													friendly label instead of its raw key, see
 *													_install/Install.php's PersonalInfos class. Same shape as
 *													_editor/assets/text.js's category form: every global key on
 *													top in one fieldset, every locale-scoped key below behind
 *													a locale <select>, in-memory _localeValues preserving
 *													unsaved edits across a locale switch. Driven by the shared
 *													Back/Next bar (script.js) rather than its own save button -
 *													save() is exposed for Next to call, not wired to a button
 *													here.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.personalinfos = {

		_ready 					: false,
		_entries 				: [],
		_locales 				: [],
		_selectedLocale : null,
		_localeValues 	: {},
		_fieldEls 			: {},

		// Fixed display order for the handful of keys base/text/*.php
		// ships, per the wizard's own field ordering - a key not listed
		// here (a fork's own /company/* or /website/* addition) still
		// renders, just appended after these in whatever order apiList()
		// returned (alphabetical, see \Nino\Text::entries())
		ORDER : [
			'/company/name', '/company/email', '/company/phone', '/company/adress',
			'/website/author', '/website/host',
			'/company/country', '/company/description',
		],

		// Every other key renders as a single-line <input> - only these two
		// are naturally multi-line content and stay a <textarea>
		TEXTAREA_KEYS : [ '/company/adress', '/company/description' ],

		/**
		 *	Load every key and render the form
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('personalinfos-list');
			if( wrap === null )
				return;

			Nino.install.apiCall( 'personalinfos/list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );

				Nino.install.personalinfos._entries 				= response.entries;
				Nino.install.personalinfos._locales 				= response.locales;
				Nino.install.personalinfos._selectedLocale = response.locales[0] ?? null;
				Nino.install.personalinfos._localeValues 	= {};
				Nino.install.personalinfos._render();
				Nino.install.personalinfos._ready = true;
			} );
		},

		showCurrent : function() {
			if( Nino.install.personalinfos._ready === false )
				Nino.install.personalinfos.init();
		},

		/**
		 *	@param		{Function}	filterFn			Entry -> bool
		 *
		 *	@return		{Array}										Matching entries, ORDER first, then whatever's
		 *																	left over in apiList()'s own (alphabetical) order
		 */
		_sortedEntries : function( filterFn ) {

			const matching = Nino.install.personalinfos._entries.filter( filterFn );

			return matching.slice().sort( function( a, b ) {
				let ia = Nino.install.personalinfos.ORDER.indexOf( a.key );
				let ib = Nino.install.personalinfos.ORDER.indexOf( b.key );
				if( ia === -1 ) ia = Nino.install.personalinfos.ORDER.length;
				if( ib === -1 ) ib = Nino.install.personalinfos.ORDER.length;
				return ia - ib;
			} );
		},

		/**
		 *	Render one key as a labeled field - <textarea> only for
		 *	TEXTAREA_KEYS, a single-line <input> otherwise
		 *
		 *	@param		{Object}	entry					Key entry
		 *	@param		{string}	value					Current value
		 *
		 *	@return		{Element}								<label> wrapping the field
		 */
		_renderKeyField : function( entry, value ) {

			const label = dc.createElement('label');
			label.className = 'editor-field';

			const span = dc.createElement('span');
			span.textContent = entry.label;
			label.appendChild( span );

			const multiline = Nino.install.personalinfos.TEXTAREA_KEYS.indexOf( entry.key ) !== -1;
			const field = dc.createElement( multiline ? 'textarea' : 'input' );
			if( multiline === true )
				field.rows = 2;
			else
				field.type = 'text';
			field.dataset.key = entry.key;
			field.value = value ?? '';

			Nino.install.personalinfos._fieldEls[entry.key] = field;
			label.appendChild( field );

			return label;
		},

		/**
		 *	Snapshot the currently visible locale-scoped fields into
		 *	_localeValues before switching locale (or before saving)
		 *
		 *	@return		void
		 */
		_storeVisibleLocaleFields : function() {

			const entries = Nino.install.personalinfos._sortedEntries( function( e ) { return e.global === false } );
			if( entries.length === 0 || Nino.install.personalinfos._selectedLocale === null )
				return;

			const values = {};
			entries.forEach( function( entry ) {
				const el = Nino.install.personalinfos._fieldEls[entry.key];
				values[entry.key] = ( el !== undefined ) ? el.value : '';
			} );

			Nino.install.personalinfos._localeValues[Nino.install.personalinfos._selectedLocale] = values;
		},

		/**
		 *	Re-render the locale-scoped fields for the currently selected locale
		 *
		 *	@return		void
		 */
		_renderLocaleFields : function() {

			const entries = Nino.install.personalinfos._sortedEntries( function( e ) { return e.global === false } );
			const wrap 		= dc.getElementById('personalinfos-form-locale-fields');
			wrap.innerHTML = '';

			const stored = Nino.install.personalinfos._localeValues[Nino.install.personalinfos._selectedLocale] ?? {};

			entries.forEach( function( entry ) {
				const value = ( stored[entry.key] !== undefined ) ? stored[entry.key] : ( entry.values[Nino.install.personalinfos._selectedLocale] ?? '' );
				wrap.appendChild( Nino.install.personalinfos._renderKeyField( entry, value ) );
			} );
		},

		/**
		 *	Render the whole form: every global key in one fieldset, every
		 *	locale-scoped key below behind a locale <select> - same shape as
		 *	_editor/assets/text.js's _renderGroupForm()
		 *
		 *	@return		void
		 */
		_render : function() {

			Nino.install.personalinfos._fieldEls = {};

			const wrap = dc.getElementById('personalinfos-list');
			wrap.innerHTML = '';

			const globalEntries = Nino.install.personalinfos._sortedEntries( function( e ) { return e.global === true } );
			const localeEntries = Nino.install.personalinfos._sortedEntries( function( e ) { return e.global === false } );

			if( globalEntries.length > 0 ) {

				const globalWrap = dc.createElement('fieldset');
				globalWrap.id = 'personalinfos-form-global';

				const legend = dc.createElement('legend');
				legend.textContent = 'Global';
				globalWrap.appendChild( legend );

				globalEntries.forEach( function( entry ) {
					globalWrap.appendChild( Nino.install.personalinfos._renderKeyField( entry, entry.values['*'] ?? '' ) );
				} );

				wrap.appendChild( globalWrap );
			}

			if( localeEntries.length > 0 ) {

				const localeWrap = dc.createElement('fieldset');
				localeWrap.id = 'personalinfos-form-locale';

				const legend = dc.createElement('legend');
				legend.textContent = 'Per Language';
				localeWrap.appendChild( legend );

				const select = dc.createElement('select');
				select.id = 'personalinfos-locale-select';
				Nino.install.personalinfos._locales.forEach( function( locale ) {
					const option = dc.createElement('option');
					option.value = locale;
					option.textContent = locale;
					option.selected = ( locale === Nino.install.personalinfos._selectedLocale );
					select.appendChild( option );
				} );
				select.addEventListener( 'change', function() {
					Nino.install.personalinfos._storeVisibleLocaleFields();
					Nino.install.personalinfos._selectedLocale = select.value;
					Nino.install.personalinfos._renderLocaleFields();
				} );
				localeWrap.appendChild( select );

				const fieldsWrap = dc.createElement('div');
				fieldsWrap.id = 'personalinfos-form-locale-fields';
				localeWrap.appendChild( fieldsWrap );

				wrap.appendChild( localeWrap );

				Nino.install.personalinfos._renderLocaleFields();
			}
		},

		/**
		 *	Build the complete batch payload: global fields once, and every
		 *	locale-scoped field once per available locale. A locale already
		 *	visited in the selector comes from _localeValues; an untouched one
		 *	keeps the value apiList() originally returned.
		 *
		 *	@return		{Array}									Items for personalinfos/savebatch
		 */
		_saveItems : function() {

			const items = [];

			Nino.install.personalinfos._sortedEntries( function( e ) { return e.global === true } ).forEach( function( entry ) {
				const el = Nino.install.personalinfos._fieldEls[entry.key];
				items.push( { key : entry.key, locale : '*', value : ( el !== undefined ) ? el.value : ( entry.values['*'] ?? '' ) } );
			} );

			const localeEntries = Nino.install.personalinfos._sortedEntries( function( e ) { return e.global === false } );
			Nino.install.personalinfos._locales.forEach( function( locale ) {
				const stored = Nino.install.personalinfos._localeValues[locale] ?? {};
				localeEntries.forEach( function( entry ) {
					const value = ( stored[entry.key] !== undefined ) ? stored[entry.key] : ( entry.values[locale] ?? '' );
					items.push( { key : entry.key, locale : locale, value : value } );
				} );
			} );

			return items;
		},

		/**
		 *	Save every field on the page in one batch request (global fields
		 *	plus all available locales, including languages edited before the
		 *	currently visible one) - called by
		 *	the shared Next button, not by a button of its own
		 *
		 *	@param		{Function}	callback			Called with ( success )
		 *
		 *	@return		void
		 */
		save : function( callback ) {

			const msg = dc.getElementById('personalinfos-msg');

			if( Nino.install.personalinfos._ready !== true ) {
				msg.textContent = 'Personal Infos are still loading.';
				callback( false );
				return;
			}

			Nino.install.personalinfos._storeVisibleLocaleFields();

			msg.textContent = 'Saving …';

			const items = Nino.install.personalinfos._saveItems();

			Nino.install.apiCall( 'personalinfos/savebatch', { items : items }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					callback( false );
					return;
				}

				const failed = Object.keys( response.results ).filter( function( key ) { return response.results[key].ok === false } );

				if( failed.length > 0 ) {
					msg.textContent = failed.length+ ' key(s) failed to save.';
					callback( false );
					return;
				}

				msg.textContent = 'Saved.';
				callback( true );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
