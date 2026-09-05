

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js   							"Language" panel: which languages the site serves and
 *													which one a visitor gets before choosing, as one form.
 *													See Admin/Admin.php beside it for the schema this
 *													renders and validates against.
 *
 *													One form, one Save: the two locale keys constrain each
 *													other (a native language has to be one of the
 *													available ones), so they cannot be saved separately
 *													without a moment where config.php contradicts itself.
 *													Translating a language is the Text panel's job, or the
 *													Translations tab beside this one.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.language = {

		// Survives the re-render a successful save triggers - see _save()
		_pendingMsg : '',
		_intro 		: '',
		_fields 	: [],
		// The locale inventory as apiList() reported it, plus whatever has been
		// added in this session. Each entry: { code, name, hasText, keys, active }
		_locales 	: [],
		// The stored native language, held between _renderNative() building the
		// select and _render() filling it once it is in the document
		_nativeValue : '',

		/**
		 *	Load the schema, the inventory and the native language, and
		 *	render the form
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('language-form');
			if( wrap === null )
				return;

			Nino.admin.language._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.language._showError( wrap, status, response );

				Nino.admin.language._intro 		= response.intro || '';
				Nino.admin.language._fields 	= response.fields;
				Nino.admin.language._locales	= response.locales;
				Nino.admin.language._render();
			} );
		},

		showCurrent : function() {
			Nino.admin.language.init();
		},

		/**
		 *	Call a language/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "language/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'language/'+ endpoint, data : JSON.stringify( payload ) } );
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
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/load') );
			container.appendChild( p );
		},

		/**
		 *	One fieldset with the language list and the native select, and
		 *	Save in the pinned action bar
		 *
		 *	@return		void
		 */
		_render : function() {

			const wrap = dc.getElementById('language-form');
			wrap.innerHTML = '';

			const form = dc.createElement('form');

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/nav/languages');
			fieldset.appendChild( legend );

			if( Nino.admin.language._intro !== '' ) {
				const intro = dc.createElement('p');
				intro.className = 'nino-admin-hint';
				intro.textContent = Nino.adminUi.text( Nino.admin.language._intro );
				fieldset.appendChild( intro );
			}

			Nino.admin.language._fields.forEach( function( field ) {
				if( field.type === 'locales' )
					fieldset.appendChild( Nino.admin.language._renderLocales( field ) );
				else if( field.type === 'native' )
					fieldset.appendChild( Nino.admin.language._renderNative( field ) );
			} );

			form.appendChild( fieldset );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.className = 'nino-admin-btn-primary';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'language-form-msg';
			msg.className = 'nino-admin-actionbar-status';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.language._save() } );

			wrap.appendChild( form );

			// Only now, with the form actually in the document. Both of these
			// reach their targets through getElementById - the adder and _save()
			// need those ids anyway - and an element that is still a detached
			// subtree is not findable that way
			Nino.admin.language._renderLocaleRows();
			Nino.admin.language._refreshNative( Nino.admin.language._nativeValue );

			// Re-shown after the reload that follows a save, which builds this
			// element fresh and would otherwise wipe the confirmation the moment
			// it appeared
			if( Nino.admin.language._pendingMsg !== '' ) {
				msg.textContent = Nino.admin.language._pendingMsg;
				Nino.admin.language._pendingMsg = '';
			}
		},

		/**
		 *	The language list: one row per locale the project knows about, which
		 *	is the union of what config.php lists and what actually has a
		 *	text/<locale>.php on disk (see Language::_localeInventory()).
		 *
		 *	Showing both sides is the point. A translated language that is
		 *	simply switched off should be one checkbox away from being back on,
		 *	and a language switched on without a text file of its own renders
		 *	every per-locale fill as a raw [[key]] - so that row says so, before
		 *	the language is ever saved rather than after the frontend breaks.
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderLocales : function( field ) {

			const wrap = dc.createElement('div');
			wrap.className = 'nino-admin-field nino-admin-field-wide';

			const span = dc.createElement('span');
			span.className = 'nino-admin-field-name';
			span.textContent = Nino.adminUi.text( field.label );
			wrap.appendChild( span );

			if( field.hint ) {
				const hint = dc.createElement('p');
				hint.className = 'nino-admin-hint';
				hint.textContent = Nino.adminUi.text( field.hint );
				wrap.appendChild( hint );
			}

			const list = dc.createElement('div');
			list.id = 'language-locales';
			list.className = 'nino-admin-checklist';
			wrap.appendChild( list );

			// Adding one is a plain text field rather than a picker: the set of
			// locales a project may use is not something this panel gets to
			// decide, and a text/<locale>.php is all a new one needs
			const adder = dc.createElement('div');
			adder.className = 'nino-admin-field';

			const adderLabel = dc.createElement('span');
			adderLabel.textContent = Nino.content.getText('/_admin/language/label/add');
			adder.appendChild( adderLabel );

			// Said before the button is pressed, because pressing it writes a
			// file - an action that leaves something on disk should not be a
			// surprise
			const adderHint = dc.createElement('small');
			adderHint.className = 'nino-admin-hint';
			adderHint.textContent = Nino.content.getText('/_admin/language/hint/add');
			adder.appendChild( adderHint );

			const adderRow = dc.createElement('div');
			adderRow.className = 'admin-language-adder';

			const adderInput = dc.createElement('input');
			adderInput.type = 'text';
			adderInput.id = 'language-locale-new';
			adderInput.placeholder = 'fr_FR';
			adderInput.autocomplete = 'off';
			adderInput.spellcheck = false;
			adderRow.appendChild( adderInput );

			const adderBtn = dc.createElement('button');
			adderBtn.type = 'button';
			adderBtn.textContent = Nino.content.getText('/_admin/common/label/add');
			adderBtn.addEventListener( 'click', function() { Nino.admin.language._addLocale() } );
			adderRow.appendChild( adderBtn );

			// Enter inside the field adds the language instead of submitting
			// the whole form, which is what a lone text input in a form does
			adderInput.addEventListener( 'keydown', function( ev ) {
				if( ev.key !== 'Enter' )
					return;
				ev.preventDefault();
				Nino.admin.language._addLocale();
			} );

			adder.appendChild( adderRow );

			const adderMsg = dc.createElement('p');
			adderMsg.id = 'language-locale-msg';
			adderMsg.className = 'nino-admin-hint';
			adder.appendChild( adderMsg );

			wrap.appendChild( adder );

			// Rows are filled by _render() once this is in the document
			return wrap;
		},

		/**
		 *	(Re)build the locale rows from _locales
		 *
		 *	@return		void
		 */
		_renderLocaleRows : function() {

			const list = dc.getElementById('language-locales');
			if( list === null )
				return;

			list.innerHTML = '';

			Nino.admin.language._locales.forEach( function( locale ) {

				// No .nino-admin-checkbox-field here: .nino-admin-checklist already
				// styles its own rows, and carrying both stacked the two components'
				// spacing on top of each other
				const label = dc.createElement('label');

				const input = dc.createElement('input');
				input.type = 'checkbox';
				input.checked = locale.active === true;
				input.dataset.locale = locale.code;
				input.addEventListener( 'change', function() {
					locale.active = input.checked;
					Nino.admin.language._refreshNative();
					Nino.admin.language._refreshLocaleWarning();
				} );
				label.appendChild( input );

				const name = dc.createElement('span');
				name.textContent = locale.name+ ' · '+ locale.code;
				label.appendChild( name );

				// What exists on disk for it, which is what decides whether
				// switching it on produces a working language or a page full of
				// unresolved fills
				const state = dc.createElement('small');
				state.className = 'nino-admin-checklist-state';
				state.textContent = locale.hasText === true
					? Nino.content.getText('/_admin/language/label/keys').replace( '%d', String( locale.keys ) )
					: Nino.content.getText('/_admin/language/label/notext').replace( '%s', locale.code );
				label.appendChild( state );

				list.appendChild( label );
			} );

			Nino.admin.language._refreshLocaleWarning();
		},

		/**
		 *	Warn about any language that is switched on without a text file of
		 *	its own. Rendered into the adder's own message line, so it sits
		 *	directly under the list it is about
		 *
		 *	@return		void
		 */
		_refreshLocaleWarning : function() {

			const msg = dc.getElementById('language-locale-msg');
			if( msg === null )
				return;

			const missing = Nino.admin.language._locales
				.filter( function( locale ) { return locale.active === true && locale.hasText !== true } )
				.map( function( locale ) { return locale.code } );

			if( missing.length === 0 ) {
				msg.className = 'nino-admin-hint';
				msg.textContent = '';
				return;
			}

			msg.className = 'nino-admin-error';
			msg.textContent = Nino.content.getText('/_admin/language/error/missing').replace( '%s', missing.join( ', ' ) );
		},

		/**
		 *	Add a locale typed into the adder field
		 *
		 *	@return		void
		 */
		_addLocale : function() {

			const input = dc.getElementById('language-locale-new');
			const msg 	= dc.getElementById('language-locale-msg');
			const code 	= input.value.trim();

			if( /^[a-z]{2}_[A-Z]{2}$/.test( code ) === false ) {
				msg.className = 'nino-admin-error';
				msg.textContent = Nino.content.getText('/_admin/language/error/id');
				return;
			}

			const existing = Nino.admin.language._locales.find( function( locale ) { return locale.code === code } );

			// Already listed and already translated - switch it back on rather
			// than adding a duplicate, and leave its file alone
			if( existing !== undefined && existing.hasText === true ) {
				existing.active = true;
				input.value = '';
				Nino.admin.language._renderLocaleRows();
				Nino.admin.language._refreshNative();
				return;
			}

			msg.className = 'nino-admin-hint';
			msg.textContent = Nino.content.getText('/_admin/language/msg/creating').replace( '%s', code );

			// The file comes first, then the row: a language whose text file
			// exists is a language that renders, and the row can then report
			// how many keys are waiting instead of warning that none are
			Nino.admin.language._apiCall( 'addlocale', { locale : code }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.className = 'nino-admin-error';
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/language/error/create') );
					return;
				}

				const entry = existing !== undefined
					? existing
					: { code : code, name : code, hasText : false, keys : 0, active : false };

				entry.hasText = true;
				entry.keys 		= response.keys;

				// Deliberately left unticked. The file that was just written is a
				// skeleton of empty keys, so ticking it here would put a blank
				// language one Save away from being served - which is the exact
				// failure this whole panel exists to warn about. Translate it
				// first, then tick it. An already-translated language that was
				// merely switched off takes the branch above and is ticked there.
				if( existing === undefined ) {
					Nino.admin.language._locales.push( entry );
					Nino.admin.language._locales.sort( function( a, b ) { return a.code < b.code ? -1 : 1 } );
				}

				input.value = '';
				Nino.admin.language._renderLocaleRows();
				Nino.admin.language._refreshNative();

				// _renderLocaleRows() has just cleared this line via
				// _refreshLocaleWarning(), so the outcome goes in afterwards
				const after = dc.getElementById('language-locale-msg');
				after.className = 'nino-admin-hint';
				after.textContent = ( response.created === true
					? Nino.content.getText('/_admin/language/msg/created').replace( '%n', String( response.from ) )
					: Nino.content.getText('/_admin/language/msg/existed') )
					.replaceAll( '%s', code ).replace( '%d', String( response.keys ) );
			} );
		},

		/**
		 *	The native language, as a select over whichever languages are
		 *	currently ticked above - not over what config.php happens to hold,
		 *	so the two can never be saved contradicting each other
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderNative : function( field ) {

			const label = dc.createElement('label');
			label.className = 'nino-admin-field';

			const span = dc.createElement('span');
			span.textContent = Nino.adminUi.text( field.label );
			label.appendChild( span );

			const select = dc.createElement('select');
			select.id = 'language-native';
			select.dataset.key = field.key;
			label.appendChild( select );

			if( field.hint ) {
				const hint = dc.createElement('small');
				hint.className = 'nino-admin-hint';
				hint.textContent = Nino.adminUi.text( field.hint );
				label.appendChild( hint );
			}

			// Kept for _render() to apply once this select is in the document
			Nino.admin.language._nativeValue = field.value;

			return label;
		},

		/**
		 *	Rebuild the native select's options from the currently ticked
		 *	languages, keeping the current selection if it is still among them
		 *
		 *	@param		{string}	[preferred]		Selected if still available
		 *
		 *	@return		void
		 */
		_refreshNative : function( preferred ) {

			const select = dc.getElementById('language-native');
			if( select === null )
				return;

			const current = ( preferred !== undefined ) ? preferred : select.value;
			const active 	= Nino.admin.language._locales.filter( function( locale ) { return locale.active === true } );

			select.innerHTML = '';
			active.forEach( function( locale ) {
				const option = dc.createElement('option');
				option.value = locale.code;
				option.textContent = locale.name+ ' · '+ locale.code;
				option.selected = locale.code === current;
				select.appendChild( option );
			} );

			// The previously native language was just switched off. Falling back
			// to the first available one keeps the form in a saveable state -
			// the backend rejects a native language that is not in the list
			if( active.some( function( locale ) { return locale.code === current } ) === false && active.length > 0 )
				select.value = active[0].code;
		},

		/**
		 *	Save the ticked languages and the native one in one request
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg = dc.getElementById('language-form-msg');
			const fields = {};

			const active = Nino.admin.language._locales
				.filter( function( locale ) { return locale.active === true } )
				.map( function( locale ) { return locale.code } );

			fields['/nino/locales/available'] = active;

			const native = dc.getElementById('language-native');
			if( native !== null && native.value !== '' )
				fields['/nino/locales/native'] = native.value;

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.language._apiCall( 'save', { fields : fields }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
					return;
				}

				// Held rather than written straight to this element: the reload
				// below replaces it, so assigning here shows the confirmation for
				// exactly as long as the request that follows takes
				Nino.admin.language._pendingMsg = Nino.content.getText('/_admin/common/msg/saved');

				// Reloaded rather than left as typed: a saved language list
				// changes what the inventory reports (a new locale is now
				// configured, its text file may or may not exist)
				Nino.admin.language.init();
			} );
		},
	};

})(window, document, document.documentElement, document.body);
