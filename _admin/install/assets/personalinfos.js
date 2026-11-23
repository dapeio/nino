/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 3: bulk-fill the /project/company/* and
 *													/project/website/general/* text keys every project
 *													has (global.php + per-locale
 *													text/<locale>.php) in one form instead of clicking through
 *													_admin's Text panel one key at a time - each with a
 *													friendly label instead of its raw key, which the server
 *													gives in both languages of the wizard, see
 *													_admin/install/Install.php's PersonalInfos class. Same shape as
 *													the Text panel's own assets/admin.js and its category
 *													form: every global key on top in one fieldset, every
 *													locale-scoped key below it - in the one language of the
 *													website, the first the project has: there is nothing to
 *													page through. Driven by the shared
 *													Back/Next bar (script.js) rather than its own save button -
 *													save() is exposed for Next to call, not wired to a button
 *													here. The fields are the blocks of the workbench.
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
		_globalValues 	: {},
		_fieldEls 			: {},

		// Fixed display order for the handful of keys base/text/*.php
		// ships, per the wizard's own field ordering - a key not listed
		// here (a fork's own /project/company/* or /project/website/general/*
		// addition) still
		// renders, just appended after these in whatever order apiList()
		// returned (alphabetical, see \Nino\Text::entries())
		ORDER : [
			'/project/website/general/url', '/project/company/general/name',
			'/project/company/contact/email', '/project/company/contact/phone', '/project/company/contact/address',
			'/project/website/general/author', '/project/website/general/host',
			'/project/company/contact/country', '/project/company/general/description',
		],

		// Every other key renders as a single-line <input> - only these two
		// are naturally multi-line content and stay a <textarea>
		TEXTAREA_KEYS : [ '/project/company/contact/address', '/project/company/general/description' ],

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
				Nino.install.personalinfos._globalValues 	= {};
				Nino.install.personalinfos._render();
				Nino.install.personalinfos._ready = true;
			} );
		},

		showCurrent : function() {
			if( Nino.install.personalinfos._ready === false )
				Nino.install.personalinfos.init();
		},

		retext : function() {
			if( Nino.install.personalinfos._ready === true )
				Nino.install.personalinfos._render();
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
		 *	TEXTAREA_KEYS, a single-line <input> otherwise. The label is the one the server
		 *	gives in the language of the page
		 *
		 *	@param		{Object}	entry					Key entry
		 *	@param		{string}	value					Current value
		 *
		 *	@return		{Element}								<label> wrapping the field
		 */
		_renderKeyField : function( entry, value ) {

			const multiline = Nino.install.personalinfos.TEXTAREA_KEYS.indexOf( entry.key ) !== -1;
			const options = { key : entry.key, label : entry.labels[ Nino.pageText.language() ] ?? '', value : value ?? '' };
			const label = multiline === true ? Nino.adminUi.textareaField( Object.assign( options, { rows : 2 } ) ) : Nino.adminUi.textField( options );

			Nino.install.personalinfos._fieldEls[entry.key] = label.querySelector( multiline === true ? 'textarea' : 'input' );

			return label;
		},

		/**
		 *	Snapshot the currently visible fields into _globalValues and
		 *	_localeValues before saving or before they are drawn again in another
		 *	language of the page
		 *
		 *	@return		void
		 */
		_storeVisibleLocaleFields : function() {

			const self = Nino.install.personalinfos;

			self._sortedEntries( function( e ) { return e.global === true } ).forEach( function( entry ) {
				const el = self._fieldEls[entry.key];
				if( el !== undefined )
					self._globalValues[entry.key] = el.value;
			} );

			const entries = self._sortedEntries( function( e ) { return e.global === false } );
			if( entries.length === 0 || self._selectedLocale === null )
				return;

			// Only what is drawn is taken: the first drawing has no field yet, and an empty value taken for one would go before the one the server gave
			const values = Object.assign( {}, self._localeValues[self._selectedLocale] ?? {} );
			let drawn = false;
			entries.forEach( function( entry ) {
				const el = self._fieldEls[entry.key];
				if( el === undefined )
					return;
				values[entry.key] = el.value;
				drawn = true;
			} );

			if( drawn === true )
				self._localeValues[self._selectedLocale] = values;
		},

		/**
		 *	Re-render the locale-scoped fields for the language of the website
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
		 *	locale-scoped key below it - same shape as the Text panel's own
		 *	_renderGroupForm() (its assets/admin.js)
		 *
		 *	@return		void
		 */
		_render : function() {

			const self = Nino.install.personalinfos;
			const t = Nino.install.t;

			self._storeVisibleLocaleFields();
			self._fieldEls = {};

			const wrap = dc.getElementById('personalinfos-list');
			wrap.innerHTML = '';

			const globalEntries = self._sortedEntries( function( e ) { return e.global === true } );
			const localeEntries = self._sortedEntries( function( e ) { return e.global === false } );

			if( globalEntries.length > 0 ) {

				const globalWrap = Nino.adminUi.el( 'fieldset', '' );
				globalWrap.id = 'personalinfos-form-global';

				const legend = Nino.adminUi.el( 'legend', '', t('/_admin/install/personalinfos/title-global') );
				globalWrap.appendChild( legend );
				Nino.adminUi.sectionHelp( legend, globalWrap, t('/_admin/install/personalinfos/help-global') );

				const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );
				globalEntries.forEach( function( entry ) {
					grid.appendChild( self._renderKeyField( entry, self._globalValues[entry.key] ?? entry.values['*'] ?? '' ) );
				} );
				globalWrap.appendChild( grid );

				wrap.appendChild( globalWrap );
			}

			if( localeEntries.length > 0 ) {

				const localeWrap = Nino.adminUi.el( 'fieldset', '' );
				localeWrap.id = 'personalinfos-form-locale';

				const legend = Nino.adminUi.el( 'legend', '', t('/_admin/install/personalinfos/title-locale') );
				localeWrap.appendChild( legend );
				Nino.adminUi.sectionHelp( legend, localeWrap, t('/_admin/install/personalinfos/help-locale') );

				const fieldsWrap = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );
				fieldsWrap.id = 'personalinfos-form-locale-fields';
				localeWrap.appendChild( fieldsWrap );

				wrap.appendChild( localeWrap );

				self._renderLocaleFields();
			}
		},

		/**
		 *	Build the complete batch payload: global fields once, and every
		 *	locale-scoped field once per available locale - the one the fields
		 *	show from what stands in them, any other as apiList() gave it.
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
		 *	plus all available locales) - called by
		 *	the shared Next button, not by a button of its own
		 *
		 *	@param		{Function}	callback			Called with ( success )
		 *
		 *	@return		void
		 */
		save : function( callback ) {

			const t = Nino.install.t;

			if( Nino.install.personalinfos._ready !== true ) {
				Nino.install.say( t('/_admin/install/personalinfos/loading'), true );
				callback( false );
				return;
			}

			Nino.install.personalinfos._storeVisibleLocaleFields();

			Nino.install.say( t('/_admin/install/step/saving') );

			const items = Nino.install.personalinfos._saveItems();

			Nino.install.apiCall( 'personalinfos/savebatch', { items : items }, function( status, response ) {
				if( status !== 200 || response === null ) {
					Nino.install.say( Nino.install.failure( status, response ), true );
					callback( false );
					return;
				}

				const failed = Object.keys( response.results ).filter( function( key ) { return response.results[key].ok === false } );

				if( failed.length > 0 ) {
					Nino.install.say( t( '/_admin/install/personalinfos/failed', failed.length ), true );
					callback( false );
					return;
				}

				callback( true );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
