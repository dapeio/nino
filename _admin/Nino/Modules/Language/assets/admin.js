

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
		// Set once the first answer has rendered the form; showCurrent() builds
		// nothing while it is set - see there
		_ready 		: false,
		_fields 	: [],
		// The locale inventory as apiList() reported it, plus whatever has been
		// added in this session. Each entry: { code, name, hasText, keys, active }
		_locales 	: [],
		// The stored native language, held between _renderNative() building the
		// select and _render() filling it once it is in the document
		_nativeValue : '',
		// The ticked languages and the native one as they are stored, for
		// isDirty(): adding a language writes its file at once and adds a row
		// to the list, which is not an edit of what Save stores
		_saved 		: { active : [], native : '' },
		// The name of the list of languages and the sentence behind its (?), as the schema said them
		_words 		: { label : '', help : undefined },
		// The line of the bar: what is saving, what is unsaved and why a save was
		// refused (see Nino.adminUi.status()). Drawn again with the form
		_line 		: null,

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

				Nino.admin.language._fields 	= response.fields;
				Nino.admin.language._locales	= response.locales;
				Nino.admin.language._render();
				Nino.admin.language._ready = true;
			} );
		},

		/*	The shell calls this every time its panel is shown again, and the
			contract it documents is that switching panels never resets
			anything: "jumping back and forth is always exactly where you left
			it". Re-running init() broke that promise on the one screen where
			it costs the most - a form. A ticked switch, a typed number, a
			locale code half entered: the answer came back, the wrap was
			emptied and rebuilt from the server's values, and the edit was gone
			without a word. Nothing to do once the form is up: the shell
			un-hides the pane, the pane is where it was left. The actions that
			change state (save above all) re-fetch on their own	*/
		showCurrent : function() {

			if( Nino.admin.language._ready === false )
				Nino.admin.language.init();
		},

		/**
		 *	Call a language/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "language/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'language/'+ endpoint, payload, callback );
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
		 *	One fieldset with the list of languages and the native select, a
		 *	notice above it while a language that is on has no file, and the one
		 *	bar below: the badge and the line, "Add language" at the side and Save
		 *	as the one main button
		 *
		 *	@return		void
		 */
		_render : function() {

			const panel = Nino.admin.language;
			const wrap = dc.getElementById('language-form');
			wrap.innerHTML = '';

			const form = dc.createElement('form');
			form.noValidate = true;

			// Where the warning about a language that is on and has no file of its own is drawn (see _refreshLocaleWarning())
			const missing = dc.createElement('div');
			missing.id = 'language-missing';
			form.appendChild( missing );

			// One section, which the head of the tab names: what each field does stands behind the (?) at its name
			const fieldset = dc.createElement('fieldset');

			panel._fields.forEach( function( field ) {
				if( field.type === 'locales' )
					fieldset.appendChild( panel._renderLocales( field ) );
				else if( field.type === 'native' )
					fieldset.appendChild( panel._renderNative( field ) );
			} );

			form.appendChild( fieldset );

			const msg = dc.createElement('p');
			msg.id = 'language-form-msg';

			// A language is added in a dialog, from the bar: what it makes is a file, which is no part of what Save stores
			const add = Nino.adminUi.button( Nino.content.getText('/_admin/language/label/add'), function() { panel._openAdder( add ) }, { kind : 'secondary', id : 'language-add' } );
			const bar = Nino.adminUi.actionBar( {
				status		: { line : msg },
				secondary	: [ add ],
				primary		: Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit' } ),
			} );
			panel._line = bar.line;
			bar.line.bind( form, panel.isDirty );

			form.appendChild( bar.el );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); panel._save() } );

			wrap.appendChild( form );

			// Only now, with the form actually in the document. Both of these
			// reach their targets through getElementById, and an element that is
			// still a detached subtree is not findable that way
			panel._renderLocaleRows();
			panel._refreshNative( panel._nativeValue );

			panel._capture();
		},

		/**
		 *	Take the ticked languages and the native one as they are now for
		 *	the ones that are stored - after the form was drawn, and after a
		 *	save went through
		 *
		 *	@return		void
		 */
		_capture : function() {

			const native = dc.getElementById('language-native');

			Nino.admin.language._saved = {
				active	: Nino.admin.language._locales.filter( function( locale ) { return locale.active === true } ).map( function( locale ) { return locale.code } ),
				native	: native === null ? '' : native.value,
			};

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	Whether the form holds a change nobody has saved: another set of
		 *	ticked languages, or another native one. What the shell asks before
		 *	it lets anything throw that away (see Nino.admin.dirty)
		 *
		 *	@return		{boolean}
		 */
		isDirty : function() {

			if( dc.getElementById('language-form') === null )
				return false;

			const active = Nino.admin.language._locales.filter( function( locale ) { return locale.active === true } ).map( function( locale ) { return locale.code } );
			const native = dc.getElementById('language-native');

			return active.join() !== Nino.admin.language._saved.active.join() || ( native !== null && native.value !== Nino.admin.language._saved.native );
		},

		/**
		 *	Throw the change away: the languages go back to the ticks that are
		 *	stored. The form is about to be left or drawn again
		 *
		 *	@return		void
		 */
		discard : function() {

			Nino.admin.language._locales.forEach( function( locale ) {
				locale.active = Nino.admin.language._saved.active.indexOf( locale.code ) !== -1;
			} );

			Nino.admin.language._renderLocaleRows();
			Nino.admin.language._refreshNative( Nino.admin.language._saved.native );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	The mount of the list of languages: one row per locale the project
		 *	knows about, which is the union of what config.php lists and what
		 *	actually has a text/<locale>.php on disk (see Language::_localeInventory()).
		 *
		 *	Showing both sides is the point. A translated language that is
		 *	simply switched off should be one tick away from being back on,
		 *	and a language switched on without a text file of its own shows the
		 *	texts of the native language and is offered for translation neither
		 *	under Text nor under Translations - so that row says so, before the
		 *	language is ever saved. The rows are drawn by _renderLocaleRows()
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderLocales : function( field ) {

			const mount = dc.createElement('div');
			mount.id = 'language-locales';

			// The words of the checklist are the field's: the name and the help that stands behind the (?) at it
			Nino.admin.language._words = { label : Nino.adminUi.text( field.label ), help : field.hint ? Nino.adminUi.text( field.hint ) : undefined };

			return mount;
		},

		/**
		 *	(Re)build the locale rows from _locales: a list of ticks, each language
		 *	named as the whole workbench names it, with what exists on disk for it at
		 *	the end of its row - which is what decides whether switching it on
		 *	produces a language with texts of its own or one that shows the native
		 *	language's until it has a file
		 *
		 *	@return		void
		 */
		_renderLocaleRows : function() {

			const panel = Nino.admin.language;
			const mount = dc.getElementById('language-locales');
			if( mount === null )
				return;

			const codes = panel._locales.map( function( locale ) { return locale.code } );

			const list = Nino.adminUi.checklist( {
				key			: 'locales',
				label		: panel._words.label,
				help		: panel._words.help,
				options	: panel._locales.map( function( locale ) {
					return {
						value : locale.code,
						label : Nino.adminUi.localeName( locale.code, codes ),
						state : locale.hasText === true
							? Nino.adminUi.format( Nino.content.getText('/_admin/language/label/keys'), locale.keys )
							: Nino.adminUi.format( Nino.content.getText('/_admin/language/label/notext'), locale.code ),
					};
				} ),
				value		: panel._locales.filter( function( locale ) { return locale.active === true } ).map( function( locale ) { return locale.code } ),
				onChange : function( ticked ) {
					panel._locales.forEach( function( locale ) { locale.active = ticked.indexOf( locale.code ) !== -1 } );
					panel._refreshNative();
					panel._refreshLocaleWarning();
				},
			} );

			mount.innerHTML = '';
			mount.appendChild( list.el );

			panel._refreshLocaleWarning();
		},

		/**
		 *	Warn about any language that is switched on without a text file of
		 *	its own: a notice at the top of the form, which is there while one is
		 *	and goes when it is switched off or its file is made
		 *
		 *	@return		void
		 */
		_refreshLocaleWarning : function() {

			const mount = dc.getElementById('language-missing');
			if( mount === null )
				return;

			const codes = Nino.admin.language._locales.map( function( locale ) { return locale.code } );
			const missing = Nino.admin.language._locales
				.filter( function( locale ) { return locale.active === true && locale.hasText !== true } )
				.map( function( locale ) { return Nino.adminUi.localeName( locale.code, codes ) } );

			mount.innerHTML = '';

			if( missing.length > 0 )
				mount.appendChild( Nino.adminUi.notice( Nino.adminUi.format( Nino.content.getText('/_admin/language/error/missing'), missing.join( ', ' ) ) ) );
		},

		/**
		 *	The dialog that adds a language: a code and nothing else, and what
		 *	pressing the button does said before it is pressed - it writes a file
		 *
		 *	@param		{Element}	opener					The button that opened it, which has the focus again when it closes
		 *
		 *	@return		void
		 */
		_openAdder : function( opener ) {

			Nino.adminUi.formDialog( {
				title		: Nino.content.getText('/_admin/language/label/add'),
				fields	: [ {
					key				: 'code',
					label			: Nino.content.getText('/_admin/language/label/code'),
					// What the code looks like and what pressing the button does stand behind the (?): nothing is overwritten, so no warning stays in view
					help			: Nino.content.getText('/_admin/language/hint/code')+ ' '+ Nino.content.getText('/_admin/language/hint/add'),
					required	: true,
					validate	: function( value ) { return /^[a-z]{2}_[A-Z]{2}$/.test( value.trim() ) === true ? '' : Nino.content.getText('/_admin/language/error/id') },
				} ],
				submit	: Nino.content.getText('/_admin/language/label/add'),
				cancel	: Nino.content.getText('/_admin/common/label/cancel'),
				opener	: opener,
				onSubmit : function( values, ctl ) { Nino.admin.language._addLocale( values.code.trim(), ctl ) },
			} );
		},

		/**
		 *	Add a locale named in the dialog. A code that is no code was refused
		 *	by the dialog before it got here, at the field, and a language that is
		 *	listed and translated is refused there as well; any other has its file
		 *	made first, then its row
		 *
		 *	@param		{string}		code
		 *	@param		{Object}		ctl						The dialog's { close(), fail( key, text ) }
		 *
		 *	@return		void
		 */
		_addLocale : function( code, ctl ) {

			const panel = Nino.admin.language;
			const existing = panel._locales.find( function( locale ) { return locale.code === code } );

			// Already listed and already translated: it is in the list, ticked or not, and there is nothing to make - said at the field,
			// with the dialog left open, and its file is left alone
			if( existing !== undefined && existing.hasText === true ) {
				// A language that is switched off is one tick away; one that is on needs no more than being told it is there
				ctl.fail( 'code', existing.active === true ? Nino.content.getText('/_admin/language/error/served') : Nino.adminUi.format( Nino.content.getText('/_admin/language/error/listed'), Nino.adminUi.localeName( code ) ) );
				return;
			}

			// The file comes first, then the row: a language whose text file
			// exists is a language that renders, and the row can then report
			// how many keys are waiting instead of warning that none are
			panel._apiCall( 'addlocale', { locale : code }, function( status, response ) {

				if( status !== 200 || response === null ) {
					ctl.fail( 'code', Nino.adminUi.api.errorText( status, response, '/_admin/language/error/create' ) );
					return;
				}

				const entry = existing !== undefined
					? existing
					: { code : code, name : code, hasText : false, keys : 0, active : false };

				entry.hasText = true;
				entry.keys 		= response.keys;

				// The workbench keeps the names of its languages in one place: the name this one was given stands there, so that every
				// block that names a language says it as the server does, before the page is read again
				if( typeof response.name === 'string' && response.name !== '' )
					Nino.adminUi.locales.names[code] = response.name;

				// Deliberately left unticked. The file that was just written is a
				// skeleton of empty keys, so ticking it here would put a language
				// with nothing of its own one Save away from being served - its
				// pages would show the native language's texts, all of them.
				// Translate it first, then tick it. An already-translated language
				// that was merely switched off took the branch above and is
				// ticked there.
				if( existing === undefined ) {
					panel._locales.push( entry );
					panel._locales.sort( function( a, b ) { return a.code < b.code ? -1 : 1 } );
				}

				panel._renderLocaleRows();
				panel._refreshNative();
				ctl.close();

				// A language that is ticked already - the warning above led here -
				// is not left off: its pages show the native language's texts
				const name = Nino.adminUi.localeName( code );

				if( response.created !== true )
					Nino.adminUi.say( Nino.adminUi.format( Nino.content.getText('/_admin/language/msg/existed'), code, response.keys ) );
				else if( entry.active === true )
					Nino.adminUi.say( Nino.adminUi.format( Nino.content.getText('/_admin/language/msg/served'), name, response.keys ) );
				else
					Nino.adminUi.say( Nino.adminUi.format( Nino.content.getText('/_admin/language/msg/created'), name, response.keys ) );
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

			const label = Nino.adminUi.selectField( {
				key 		: field.key,
				label 	: Nino.adminUi.text( field.label ),
				help 		: field.hint ? Nino.adminUi.text( field.hint ) : undefined,
				id 			: 'language-native',
				options : [],
				value 	: '',
			} );

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
			const codes 	= active.map( function( locale ) { return locale.code } );

			select.innerHTML = '';
			active.forEach( function( locale ) {
				select.appendChild( Nino.adminUi.localeOption( locale.code, codes, [], current ) );
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
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the languages were written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = Nino.admin.language._line;
			const fields = {};

			const active = Nino.admin.language._locales
				.filter( function( locale ) { return locale.active === true } )
				.map( function( locale ) { return locale.code } );

			fields['/nino/locales/available'] = active;

			const native = dc.getElementById('language-native');
			if( native !== null && native.value !== '' )
				fields['/nino/locales/native'] = native.value;

			line.saving();

			Nino.admin.language._apiCall( 'save', { fields : fields }, function( status, response ) {

				if( status !== 200 || response === null ) {
					line.error( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}

				line.saved();
				Nino.admin.language._capture();

				// Reloaded rather than left as typed: a saved language list
				// changes what the inventory reports (a new locale is now
				// configured, its text file may or may not exist)
				Nino.admin.language.init();
				report( true );
			} );
		},
	};

	// The shell asks before anything throws the form's changes away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'language', {
			isDirty : Nino.admin.language.isDirty,
			save		: function( done ) { Nino.admin.language._save( done ) },
			discard : Nino.admin.language.discard,
		} );

})(window, document, document.documentElement, document.body);
