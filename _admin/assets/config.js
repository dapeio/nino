

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Config" module: the project's settings as one form -
 *													error handling, login throttling, the _editor features
 *													that can be switched off, and the site's languages.
 *													See _admin/Admin.php's Config class for the field
 *													schema this renders and validates against; the shape
 *													of every field (type, bounds, label, hint) comes from
 *													there rather than being duplicated here, so adding a
 *													setting is one FIELDS entry and nothing in this file.
 *
 *													One form, one Save: the two locale keys constrain each
 *													other (a native language has to be one of the
 *													available ones), so they cannot be saved separately
 *													without a moment where config.php contradicts itself.
 *
 *													Routes, navigations and asset bundles used to be
 *													editable here as raw json and no longer are - the
 *													first two have real editors of their own (pages.js,
 *													navs.js) and a second, unvalidated way to write the
 *													same data is a way to corrupt it.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.config = {

		_ready 		: false,
		// Survives the re-render a successful save triggers - see _save()
		_pendingMsg : '',
		_groups 	: {},
		_fields 	: [],
		// The locale inventory as apiList() reported it, plus whatever has been
		// added in this session. Each entry: { code, name, hasText, keys, active }
		_locales 	: [],
		// The stored native language, held between _renderNative() building the
		// select and _render() filling it once it is in the document
		_nativeValue : '',

		/**
		 *	Load the schema plus current values and render the form
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('config-form');
			if( wrap === null )
				return;

			Nino.admin.config._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.config._showError( wrap, status, response );

				Nino.admin.config._groups 	= response.groups;
				Nino.admin.config._fields 	= response.fields;
				Nino.admin.config._locales	= response.locales;
				Nino.admin.config._render();
				Nino.admin.config._ready = true;
			} );
		},

		showCurrent : function() {
			Nino.admin.config.init();
		},

		/**
		 *	Call a config/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "config/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'config/'+ endpoint, data : JSON.stringify( payload ) } );
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
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			container.appendChild( p );
		},

		/**
		 *	Render every group in the order Admin.php declared it, each with the
		 *	fields that belong to it
		 *
		 *	@return		void
		 */
		_render : function() {

			const wrap = dc.getElementById('config-form');
			wrap.innerHTML = '';

			const form = dc.createElement('form');

			Object.keys( Nino.admin.config._groups ).forEach( function( groupKey ) {

				const fields = Nino.admin.config._fields.filter( function( field ) { return field.group === groupKey } );
				if( fields.length === 0 )
					return;

				form.appendChild( Nino.admin.config._renderGroup( groupKey, fields ) );
			} );

			// Save + its message in the shared actions row every module's form
			// ends on - Nino.admin.css pins that row to the bottom of the
			// viewport, so a long form never puts Save out of reach
			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.className = 'nino-admin-btn-primary';
			saveBtn.textContent = 'Save';
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'config-form-msg';
			msg.className = 'nino-admin-actionbar-status';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.config._save() } );

			wrap.appendChild( form );

			// Only now, with the form actually in the document. Both of these
			// reach their targets through getElementById - the adder and _save()
			// need those ids anyway - and an element that is still a detached
			// subtree is not findable that way, so populating them while building
			// the fieldsets above left the language list and the native select
			// silently empty
			Nino.admin.config._renderLocaleRows();
			Nino.admin.config._refreshNative( Nino.admin.config._nativeValue );

			// Re-shown after the reload that follows a save, which builds this
			// element fresh and would otherwise wipe the confirmation the moment
			// it appeared
			if( Nino.admin.config._pendingMsg !== '' ) {
				msg.textContent = Nino.admin.config._pendingMsg;
				Nino.admin.config._pendingMsg = '';
			}
		},

		/**
		 *	@param		{string}	groupKey
		 *	@param		{Array}		fields			This group's fields, in schema order
		 *
		 *	@return		{Element}							<fieldset>
		 */
		_renderGroup : function( groupKey, fields ) {

			const group = Nino.admin.config._groups[groupKey];

			const fieldset = dc.createElement('fieldset');

			const legend = dc.createElement('legend');
			legend.textContent = group[0];
			fieldset.appendChild( legend );

			if( group[1] ) {
				const intro = dc.createElement('p');
				intro.className = 'nino-admin-hint';
				intro.textContent = group[1];
				fieldset.appendChild( intro );
			}

			fields.forEach( function( field ) {
				const el = Nino.admin.config._renderField( field );
				if( el !== null )
					fieldset.appendChild( el );
			} );

			return fieldset;
		},

		/**
		 *	One field, by the type its schema entry declares
		 *
		 *	@param		{Object}	field				{ key, type, label, hint, ... }
		 *
		 *	@return		{Element|null}
		 */
		_renderField : function( field ) {

			if( field.type === 'bool' )
				return Nino.admin.config._renderSwitch( field );

			if( field.type === 'int' )
				return Nino.admin.config._renderNumber( field );

			if( field.type === 'locales' )
				return Nino.admin.config._renderLocales( field );

			if( field.type === 'native' )
				return Nino.admin.config._renderNative( field );

			if( field.type === 'lines' )
				return Nino.admin.config._renderLines( field );

			return null;
		},

		/**
		 *	A list as a textarea, one entry per line. A repeater with an Add
		 *	button would be more machinery for less: these are short uri
		 *	patterns, and typing four of them is a paste, not four clicks.
		 *	The backend splits and trims (see Config::_cleanLines()), so what
		 *	is posted is simply the raw text.
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderLines : function( field ) {

			const label = dc.createElement('label');
			label.className = 'nino-admin-field nino-admin-field-wide';

			const span = dc.createElement('span');
			span.textContent = field.label;
			label.appendChild( span );

			const area = dc.createElement('textarea');
			area.rows = 4;
			area.spellcheck = false;
			area.dataset.key = field.key;
			area.value = ( field.value || [] ).join( '\n' );
			label.appendChild( area );

			if( field.hint ) {
				const hint = dc.createElement('small');
				hint.className = 'nino-admin-hint';
				hint.textContent = field.hint;
				label.appendChild( hint );
			}

			return label;
		},

		/**
		 *	A boolean as the design system's switch. The Element Types editor
		 *	needs the same control for its numbering option, so the component
		 *	itself lives in Nino.adminUi - this only names the setting.
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderSwitch : function( field ) {
			return Nino.adminUi.switchField( {
				key 			: field.key,
				checked 	: field.value === true,
				label 		: field.label,
				hint 			: field.hint,
			} );
		},

		/**
		 *	An int as a number input, bounded by the same min/max the backend
		 *	rejects against - so the browser catches the out-of-range value
		 *	before the request, and the backend still catches it if it doesn't
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderNumber : function( field ) {

			const label = dc.createElement('label');
			label.className = 'nino-admin-field';

			const span = dc.createElement('span');
			span.textContent = field.unit ? field.label+ ' ('+ field.unit+ ')' : field.label;
			label.appendChild( span );

			const input = dc.createElement('input');
			input.type = 'number';
			input.step = '1';
			input.value = field.value;
			input.dataset.key = field.key;
			if( field.min !== undefined ) input.min = field.min;
			if( field.max !== undefined ) input.max = field.max;
			label.appendChild( input );

			if( field.hint ) {
				const hint = dc.createElement('small');
				hint.className = 'nino-admin-hint';
				hint.textContent = field.hint;
				label.appendChild( hint );
			}

			// A duration in seconds is unreadable as a number - 3600 says
			// nothing, "1 h" does. Recomputed on input so the two never disagree
			if( field.unit === 'seconds' ) {
				const human = dc.createElement('small');
				human.className = 'nino-admin-hint';
				const setHuman = function() { human.textContent = Nino.admin.config._humanDuration( input.value ) };
				input.addEventListener( 'input', setHuman );
				setHuman();
				label.appendChild( human );
			}

			return label;
		},

		/**
		 *	"3600" -> "= 1 h". Rounded, not exact: this is a reading aid next to
		 *	the real value, not a second source of truth
		 *
		 *	@param		{string|number}	seconds
		 *
		 *	@return		{string}
		 */
		_humanDuration : function( seconds ) {

			const total = parseInt( seconds, 10 );
			if( isNaN( total ) === true || total <= 0 )
				return '';

			if( total < 60 )
				return '= '+ total+ ' s';

			if( total < 3600 )
				return '= '+ Math.round( total / 60 )+ ' min';

			if( total < 86400 )
				return '= '+ ( Math.round( total / 360 ) / 10 )+ ' h';

			return '= '+ ( Math.round( total / 8640 ) / 10 )+ ' d';
		},

		/**
		 *	The language list: one row per locale the project knows about, which
		 *	is the union of what config.php lists and what actually has a
		 *	text/<locale>.php on disk (see Config::_localeInventory()).
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
			span.textContent = field.label;
			wrap.appendChild( span );

			if( field.hint ) {
				const hint = dc.createElement('p');
				hint.className = 'nino-admin-hint';
				hint.textContent = field.hint;
				wrap.appendChild( hint );
			}

			const list = dc.createElement('div');
			list.id = 'config-locales';
			list.className = 'nino-admin-checklist';
			wrap.appendChild( list );

			// Adding one is a plain text field rather than a picker: the set of
			// locales a project may use is not something this panel gets to
			// decide, and a text/<locale>.php is all a new one needs
			const adder = dc.createElement('div');
			adder.className = 'nino-admin-field';

			const adderLabel = dc.createElement('span');
			adderLabel.textContent = 'Add a language';
			adder.appendChild( adderLabel );

			// Said before the button is pressed, because pressing it writes a
			// file - an action that leaves something on disk should not be a
			// surprise
			const adderHint = dc.createElement('small');
			adderHint.className = 'nino-admin-hint';
			adderHint.textContent = 'Creates text/<locale>.php with every key of the native language, all empty, ready to translate under Text. An existing file is never overwritten.';
			adder.appendChild( adderHint );

			const adderRow = dc.createElement('div');
			adderRow.className = 'admin-config-locale-adder';

			const adderInput = dc.createElement('input');
			adderInput.type = 'text';
			adderInput.id = 'config-locale-new';
			adderInput.placeholder = 'fr_FR';
			adderInput.autocomplete = 'off';
			adderInput.spellcheck = false;
			adderRow.appendChild( adderInput );

			const adderBtn = dc.createElement('button');
			adderBtn.type = 'button';
			adderBtn.textContent = 'Add';
			adderBtn.addEventListener( 'click', function() { Nino.admin.config._addLocale() } );
			adderRow.appendChild( adderBtn );

			// Enter inside the field adds the language instead of submitting
			// the whole form, which is what a lone text input in a form does
			adderInput.addEventListener( 'keydown', function( ev ) {
				if( ev.key !== 'Enter' )
					return;
				ev.preventDefault();
				Nino.admin.config._addLocale();
			} );

			adder.appendChild( adderRow );

			const adderMsg = dc.createElement('p');
			adderMsg.id = 'config-locale-msg';
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

			const list = dc.getElementById('config-locales');
			if( list === null )
				return;

			list.innerHTML = '';

			Nino.admin.config._locales.forEach( function( locale ) {

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
					Nino.admin.config._refreshNative();
					Nino.admin.config._refreshLocaleWarning();
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
					? locale.keys+ ' text keys'
					: 'no text/'+ locale.code+ '.php yet';
				label.appendChild( state );

				list.appendChild( label );
			} );

			Nino.admin.config._refreshLocaleWarning();
		},

		/**
		 *	Warn about any language that is switched on without a text file of
		 *	its own. Rendered into the adder's own message line, so it sits
		 *	directly under the list it is about
		 *
		 *	@return		void
		 */
		_refreshLocaleWarning : function() {

			const msg = dc.getElementById('config-locale-msg');
			if( msg === null )
				return;

			const missing = Nino.admin.config._locales
				.filter( function( locale ) { return locale.active === true && locale.hasText !== true } )
				.map( function( locale ) { return locale.code } );

			if( missing.length === 0 ) {
				msg.className = 'nino-admin-hint';
				msg.textContent = '';
				return;
			}

			msg.className = 'nino-admin-error';
			msg.textContent = 'No text file yet for '+ missing.join( ', ' )
				+ ' - every per-locale fill renders unresolved until one exists. Import it under Translations, or copy an existing text/<locale>.php.';
		},

		/**
		 *	Add a locale typed into the adder field
		 *
		 *	@return		void
		 */
		_addLocale : function() {

			const input = dc.getElementById('config-locale-new');
			const msg 	= dc.getElementById('config-locale-msg');
			const code 	= input.value.trim();

			if( /^[a-z]{2}_[A-Z]{2}$/.test( code ) === false ) {
				msg.className = 'nino-admin-error';
				msg.textContent = 'A language id looks like de_DE: two lowercase letters, an underscore, two uppercase ones.';
				return;
			}

			const existing = Nino.admin.config._locales.find( function( locale ) { return locale.code === code } );

			// Already listed and already translated - switch it back on rather
			// than adding a duplicate, and leave its file alone
			if( existing !== undefined && existing.hasText === true ) {
				existing.active = true;
				input.value = '';
				Nino.admin.config._renderLocaleRows();
				Nino.admin.config._refreshNative();
				return;
			}

			msg.className = 'nino-admin-hint';
			msg.textContent = 'Creating text/'+ code+ '.php …';

			// The file comes first, then the row: a language whose text file
			// exists is a language that renders, and the row can then report
			// how many keys are waiting instead of warning that none are
			Nino.admin.config._apiCall( 'addlocale', { locale : code }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.className = 'nino-admin-error';
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Could not create the language file.' );
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
				// failure this whole group exists to warn about. Translate it
				// first, then tick it. An already-translated language that was
				// merely switched off takes the branch above and is ticked there.
				if( existing === undefined ) {
					Nino.admin.config._locales.push( entry );
					Nino.admin.config._locales.sort( function( a, b ) { return a.code < b.code ? -1 : 1 } );
				}

				input.value = '';
				Nino.admin.config._renderLocaleRows();
				Nino.admin.config._refreshNative();

				// _renderLocaleRows() has just cleared this line via
				// _refreshLocaleWarning(), so the outcome goes in afterwards
				const after = dc.getElementById('config-locale-msg');
				after.className = 'nino-admin-hint';
				after.textContent = response.created === true
					? 'Created text/'+ code+ '.php with '+ response.keys+ ' empty keys copied from '+ response.from+ '. Translate them under Text, then tick '+ code+ ' here and Save - it is left off for now, because a language of empty keys would serve blank pages.'
					: 'text/'+ code+ '.php already existed with '+ response.keys+ ' keys - left untouched.';
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
			span.textContent = field.label;
			label.appendChild( span );

			const select = dc.createElement('select');
			select.id = 'config-native';
			select.dataset.key = field.key;
			label.appendChild( select );

			if( field.hint ) {
				const hint = dc.createElement('small');
				hint.className = 'nino-admin-hint';
				hint.textContent = field.hint;
				label.appendChild( hint );
			}

			// Kept for _render() to apply once this select is in the document
			Nino.admin.config._nativeValue = field.value;

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

			const select = dc.getElementById('config-native');
			if( select === null )
				return;

			const current = ( preferred !== undefined ) ? preferred : select.value;
			const active 	= Nino.admin.config._locales.filter( function( locale ) { return locale.active === true } );

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
		 *	Collect every field and save the form in one request
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg = dc.getElementById('config-form-msg');
			const fields = {};

			// Every switch and number input carries its own key, so collecting
			// them needs no second list to keep in step with the schema
			Array.prototype.slice.call( dc.querySelectorAll('#config-form [data-key]') ).forEach( function( el ) {

				if( el.type === 'checkbox' )
					fields[el.dataset.key] = el.checked;
				else if( el.type === 'number' )
					fields[el.dataset.key] = el.value.trim();
				else
					// A textarea goes as its raw text - Config::_cleanLines()
					// splits and trims it, so the two never disagree about what
					// an empty line means
					fields[el.dataset.key] = el.value;
			} );

			const active = Nino.admin.config._locales
				.filter( function( locale ) { return locale.active === true } )
				.map( function( locale ) { return locale.code } );

			if( active.length > 0 )
				fields['/nino/locales/available'] = active;

			msg.textContent = 'Saving …';

			Nino.admin.config._apiCall( 'save', { fields : fields }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}

				// Held rather than written straight to this element: the reload
				// below replaces it, so assigning here shows the confirmation for
				// exactly as long as the request that follows takes
				Nino.admin.config._pendingMsg = 'Saved.';

				// Reloaded rather than left as typed: a saved language list
				// changes what the inventory reports (a new locale is now
				// configured, its text file may or may not exist), and the
				// number fields come back as the ints config.php now holds
				Nino.admin.config.init();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.config.init );

})(window, document, document.documentElement, document.body);
