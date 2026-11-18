/**
 *	Nino								A compact filesystembased php framework
 *	Admin							Native Text + Elements translation hand-off. Exports
 *										the versioned JSON document, accepts it back from a
 *										file or paste, and merges it into one chosen locale.
 *										A tab of the Language panel (see Language::tabs()): two
 *										sections and one bar, the import its main button.
 *
 *	@package					Dape/Nino
 *	@author					David Perchermeier <mail@dape.io>
 *	@link						https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.translations = {

		_info 	: null,
		_ready 	: false,
		// The controls of the form on screen: { form, target, json, file, button, label }, null while there is none
		_fields : null,
		// The line of the bar (see Nino.adminUi.status()), and whether an import is on its way
		_line 	: null,
		_busy 	: false,

		init : function() {

			const wrap = dc.getElementById('translations-content');
			if( wrap === null )
				return;

			Nino.admin.translations._apiCall( 'info', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.translations._showError( status, response );

				Nino.admin.translations._info = response;
				Nino.admin.translations._render();
				Nino.admin.translations._ready = true;
			} );
		},

		showCurrent : function() {
			if( Nino.admin.translations._ready === false )
				return Nino.admin.translations.init();
		},

		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'translations/'+ endpoint, payload, callback );
		},

		_showError : function( status, response ) {
			Nino.adminUi.showError( dc.getElementById('translations-content'), status, response, '/_admin/common/error/load' );
		},

		/**
		 *	Accept ordinary JSON as well as the fenced block an AI assistant
		 *	commonly returns when the document is pasted straight from chat
		 *
		 *	@param		{string}	value
		 *	@return		{Object}
		 */
		_parseJson : function( value ) {
			let source = String( value ?? '' ).trim();
			const fenced = /^```(?:json)?\s*([\s\S]*?)\s*```$/i.exec( source );
			if( fenced !== null )
				source = fenced[1];

			const parsed = JSON.parse( source );
			if( parsed === null || Array.isArray( parsed ) === true || typeof parsed !== 'object' )
				throw new TypeError( Nino.content.getText('/_admin/translations/error/object') );
			return parsed;
		},

		/**
		 *	The tab as the other panels' forms are built: two sections - the export, with its one small
		 *	button, and the import, with the language it goes into, the file or the pasted document - and
		 *	the one bar below, whose main button is the import. What explains a section stands behind the
		 *	(?) at its title
		 *
		 *	@return		void
		 */
		_render : function() {

			const panel = Nino.admin.translations;
			const info = panel._info;
			const wrap = dc.getElementById('translations-content');
			wrap.innerHTML = '';

			const codes = info.locales.map( function( entry ) { return entry.locale } );
			const native = Nino.adminUi.localeName( info.nativeLocale, codes );
			const form = dc.createElement('form');
			form.noValidate = true;

			// --- the export: one small button, and what the package holds behind the (?)

			const exportSet = dc.createElement('fieldset');
			const exportLegend = dc.createElement('legend');
			exportLegend.textContent = Nino.content.getText('/_admin/translations/label/export');
			exportSet.appendChild( exportLegend );
			Nino.adminUi.sectionHelp( exportLegend, exportSet, Nino.adminUi.format( Nino.content.getText('/_admin/translations/hint/intro'), native )+ ' '+ Nino.adminUi.format( Nino.content.getText('/_admin/translations/hint/export'), info.textCount, info.elementCount ) );

			const exportLabel = Nino.adminUi.format( Nino.content.getText('/_admin/translations/label/download'), native );
			const exportBtn = Nino.adminUi.button( exportLabel, function() {
				exportBtn.disabled = true;
				exportBtn.textContent = Nino.content.getText('/_admin/translations/msg/preparing');
				panel._apiCall( 'export', {}, function( status, response ) {
					exportBtn.disabled = false;
					exportBtn.textContent = exportLabel;
					if( status !== 200 || response === null )
						return panel._line.fail( Nino.content.getText('/_admin/translations/error/export') );

					const blob = new Blob( [ JSON.stringify( response, null, 2 )+ '\n' ], { type : 'application/json' } );
					const url = URL.createObjectURL( blob );
					const a = dc.createElement('a');
					a.href = url;
					a.download = 'nino-translation-'+ info.nativeLocale+ '.json';
					dc.body.appendChild( a );
					a.click();
					a.remove();
					URL.revokeObjectURL( url );
					panel._line.idle();
					Nino.adminUi.say( Nino.content.getText('/_admin/translations/msg/downloaded') );
				} );
			}, { kind : 'secondary', small : true, id : 'translations-export' } );

			const exportActions = Nino.adminUi.el( 'div', 'nino-admin-actions' );
			exportActions.appendChild( exportBtn );
			exportSet.appendChild( exportActions );
			form.appendChild( exportSet );

			// --- the import: the language it goes into, the file or the document pasted

			const importSet = dc.createElement('fieldset');
			const importLegend = dc.createElement('legend');
			importLegend.textContent = Nino.content.getText('/_admin/translations/label/import');
			importSet.appendChild( importLegend );
			Nino.adminUi.sectionHelp( importLegend, importSet, Nino.content.getText('/_admin/translations/hint/import') );

			const target = Nino.adminUi.selectField( {
				key			: 'target',
				id			: 'translations-target-locale',
				label		: Nino.content.getText('/_admin/translations/label/target'),
				options	: info.locales.map( function( entry ) {
					return {
						value : entry.locale,
						label : Nino.adminUi.localeName( entry.locale, codes )
							+ ( entry.locale === info.nativeLocale ? ' '+ Nino.content.getText('/_admin/translations/label/native') : '' )
							+ ( entry.active === false ? ' ('+ Nino.content.getText('/_admin/common/word/inactive')+ ')' : '' ),
					};
				} ),
				// The first language that is not the native one: what is translated is the others
				value		: ( info.locales.find( function( entry ) { return entry.locale !== info.nativeLocale } ) ?? { locale : info.nativeLocale } ).locale,
			} );
			importSet.appendChild( target );

			const json = Nino.adminUi.codeField( {
				key					: 'json',
				id					: 'translations-json',
				label				: Nino.content.getText('/_admin/translations/label/paste'),
				rows				: 14,
				placeholder	: '{\n  "format": "nino.translation",\n  …\n}',
			} );

			const file = Nino.adminUi.fileField( {
				key			: 'file',
				label		: Nino.content.getText('/_admin/translations/label/file'),
				accept	: 'application/json,.json',
				onChange : function( chosen ) {
					if( chosen === null )
						return;
					chosen.text().then( function( value ) {
						json.control.value = value;
						Nino.adminUi.clearFieldErrors( importSet );
						if( typeof Nino.admin.dirty === 'object' )
							Nino.admin.dirty.refresh();
					} ).catch( function() { file.error( Nino.content.getText('/_admin/translations/error/read') ) } );
				},
			} );

			importSet.appendChild( file.el );
			importSet.appendChild( json );
			form.appendChild( importSet );

			// --- the one bar: the import is the one main button of the view

			const message = dc.createElement('p');
			message.id = 'translations-message';

			const importLabel = Nino.content.getText('/_admin/translations/label/importbtn');
			const importBtn = Nino.adminUi.button( importLabel, null, { type : 'submit', kind : 'primary' } );
			const bar = Nino.adminUi.actionBar( { status : { line : message, fresh : true }, primary : importBtn } );
			panel._line = bar.line;
			bar.line.bind( form, function() { return panel._holds() } );
			form.appendChild( bar.el );

			panel._fields = { form : form, target : target.querySelector('select'), json : json, file : file, button : importBtn, label : importLabel };

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); panel._import() } );

			wrap.appendChild( form );
		},

		/**
		 *	Whether the form holds input nobody has sent: a document pasted or loaded from a file
		 *
		 *	@return		{boolean}
		 */
		_holds : function() {

			const fields = Nino.admin.translations._fields;

			return fields !== null && fields.json.control.value.trim() !== '';
		},

		/**
		 *	Put the document into the language that is chosen. A document that is no JSON, or none at all, is said at
		 *	its field; one into the native language - the source the others are translated from - is asked about first.
		 *	Every way this ends reports to done( ok ), if there is one (see Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the document was imported, false otherwise
		 *
		 *	@return		void
		 */
		_import : function( done ) {

			const panel = Nino.admin.translations;
			const info = panel._info;
			const fields = panel._fields;

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			if( fields === null || panel._busy === true )
				return report( false );

			Nino.adminUi.clearFieldErrors( fields.form );
			panel._line.idle();

			let translation;

			if( fields.json.control.value.trim() === '' ) {
				Nino.adminUi.fieldError( fields.json, fields.json.control, 'translations-json-error', Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), Nino.content.getText('/_admin/translations/label/paste') ) );
				panel._line.fail( Nino.content.getText('/_admin/translations/error/fields') );
				fields.json.control.focus();
				return report( false );
			}

			try {
				translation = panel._parseJson( fields.json.control.value );
			} catch( error ) {
				Nino.adminUi.fieldError( fields.json, fields.json.control, 'translations-json-error', Nino.adminUi.format( Nino.content.getText('/_admin/translations/error/json'), error.message ) );
				panel._line.fail( Nino.content.getText('/_admin/translations/error/fields') );
				fields.json.control.focus();
				return report( false );
			}

			const send = function() {

				panel._busy = true;
				fields.button.disabled = true;
				fields.button.textContent = Nino.content.getText('/_admin/translations/msg/importing');
				panel._line.saving();

				panel._apiCall( 'import', { targetLocale : fields.target.value, translation : translation }, function( status, response ) {

					panel._busy = false;
					fields.button.disabled = false;
					fields.button.textContent = fields.label;

					if( status !== 200 || response === null ) {
						panel._line.error( status, response, '/_admin/translations/error/import' );
						return report( false );
					}

					const imported = response.text.imported+ response.elements.imported;
					const skipped = response.text.skipped+ response.elements.skipped;

					Nino.adminUi.say( Nino.adminUi.format( Nino.content.getText('/_admin/translations/msg/imported'), imported, Nino.adminUi.localeName( response.targetLocale ), skipped ) );

					// What the import turned away stays in the line, where it can be read and acted on
					if( response.errors.length > 0 ) {
						panel._line.fail( response.errors.join(' ') );
						return report( true );
					}

					// The document is in: the field holds nothing that is not sent
					panel._line.idle();
					fields.json.control.value = '';
					fields.file.clear( false );

					if( typeof Nino.admin.dirty === 'object' )
						Nino.admin.dirty.refresh();

					report( true );
				} );
			};

			// An import overwrites the values that match, in any language, and the native one is the source the
			// others are translated from: it is asked first whatever the mode, with how many values it may touch
			const target = fields.target.value;

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/translations/confirm/overwrite-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/translations/confirm/overwrite'), panel._countValues( translation ), Nino.adminUi.localeName( target ) )
					+ ( target === info.nativeLocale ? ' '+ Nino.content.getText('/_admin/translations/confirm/native') : '' ),
				yes				: Nino.content.getText('/_admin/translations/confirm/overwrite-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: fields.button,
				onChoose	: function( ok ) {
					if( ok === true )
						send();
					else
						report( false );
				},
			} );
		},

		/**
		 *	How many values a document holds that an import may write: its texts and every text of its elements
		 *
		 *	@param		{Object}		translation
		 *
		 *	@return		{number}
		 */
		_countValues : function( translation ) {

			const count = function( node ) {
				if( typeof node === 'string' )
					return 1;
				if( node === null || typeof node !== 'object' )
					return 0;
				return Object.keys( node ).reduce( function( sum, key ) { return sum + count( node[key] ) }, 0 );
			};

			return count( translation.text ) + count( translation.elements );
		},

		/**
		 *	Forget what was pasted or chosen: the form is about to be left or drawn again
		 *
		 *	@return		void
		 */
		_discard : function() {

			const fields = Nino.admin.translations._fields;

			if( fields === null )
				return;

			fields.json.control.value = '';
			fields.file.clear( false );
			Nino.adminUi.clearFieldErrors( fields.form );
			Nino.admin.translations._line.idle();
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.translations.init );

	// The shell asks before anything throws a pasted document away (see Nino.admin.dirty): the question's
	// Save is the import. A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'translations', {
			isDirty : function() { return Nino.admin.translations._holds() },
			save		: function( done ) { Nino.admin.translations._import( done ) },
			discard : function() { Nino.admin.translations._discard() },
		} );

})(window, document, document.documentElement, document.body);
