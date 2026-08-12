/**
 *	Nino								A compact filesystembased php framework
 *	Admin							Native Text + Elements translation hand-off. Exports
 *										the versioned JSON document, accepts it back from a
 *										file or paste, and merges it into one chosen locale.
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

		init : function() {

			const wrap = dc.getElementById('admin-content-translations');
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
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'translations/'+ endpoint, data : JSON.stringify( payload ) } );
		},

		_showError : function( status, response ) {
			const wrap = dc.getElementById('admin-content-translations');
			wrap.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			wrap.appendChild( p );
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
				throw new TypeError('Translation document must be a JSON object.');
			return parsed;
		},

		_render : function() {

			const info = Nino.admin.translations._info;
			const wrap = dc.getElementById('admin-content-translations');
			wrap.innerHTML = '';

			const intro = dc.createElement('header');
			intro.className = 'admin-translations-intro';
			intro.innerHTML = '<span>Batch workflow</span><h1>Translate native content</h1><p>Export all public text and localized element values from <strong></strong>, translate only the values, then merge the document into a target language.</p>';
			intro.querySelector('strong').textContent = info.nativeLocale;
			wrap.appendChild( intro );

			const flow = dc.createElement('div');
			flow.className = 'admin-translations-flow';

			const exportCard = dc.createElement('section');
			exportCard.className = 'admin-translation-card';
			exportCard.innerHTML = '<span class="admin-step-number">1</span><div class="admin-translation-card-copy"><h2>Export native JSON</h2><p></p></div>';
			exportCard.querySelector('p').textContent = info.textCount+ ' text values and '+ info.elementCount+ ' element fields. Global values, technical text, images, and structure stay outside the package.';

			const exportBtn = dc.createElement('button');
			exportBtn.type = 'button';
			exportBtn.className = 'editor-list-action';
			exportBtn.textContent = 'Download '+ info.nativeLocale+ ' JSON';
			exportBtn.addEventListener( 'click', function() {
				exportBtn.disabled = true;
				exportBtn.textContent = 'Preparing …';
				Nino.admin.translations._apiCall( 'export', {}, function( status, response ) {
					exportBtn.disabled = false;
					exportBtn.textContent = 'Download '+ info.nativeLocale+ ' JSON';
					if( status !== 200 || response === null )
						return Nino.admin.translations._setMessage( 'Export failed.', true );

					const blob = new Blob( [ JSON.stringify( response, null, 2 )+ '\n' ], { type : 'application/json' } );
					const url = URL.createObjectURL( blob );
					const a = dc.createElement('a');
					a.href = url;
					a.download = 'nino-translation-'+ info.nativeLocale+ '.json';
					dc.body.appendChild( a );
					a.click();
					a.remove();
					URL.revokeObjectURL( url );
					Nino.admin.translations._setMessage( 'Native translation package downloaded.', false );
				} );
			} );
			exportCard.appendChild( exportBtn );
			flow.appendChild( exportCard );

			const importCard = dc.createElement('section');
			importCard.className = 'admin-translation-card admin-translation-card-import';
			importCard.innerHTML = '<span class="admin-step-number">2</span><div class="admin-translation-card-copy"><h2>Import translated JSON</h2><p>Choose the destination deliberately. Import overwrites matching values in that language, but never deletes content omitted from the document.</p></div>';

			const fields = dc.createElement('div');
			fields.className = 'admin-translation-fields';

			const localeLabel = dc.createElement('label');
			localeLabel.className = 'editor-field';
			localeLabel.innerHTML = '<span>Target language</span>';
			const localeSelect = dc.createElement('select');
			localeSelect.id = 'translations-target-locale';
			info.locales.forEach( function( locale ) {
				const option = dc.createElement('option');
				option.value = locale;
				option.textContent = locale+ ( locale === info.nativeLocale ? ' (native)' : '' );
				localeSelect.appendChild( option );
			} );
			localeSelect.value = info.locales.find( function( locale ) { return locale !== info.nativeLocale } ) ?? info.nativeLocale;
			localeLabel.appendChild( localeSelect );
			fields.appendChild( localeLabel );

			const fileLabel = dc.createElement('label');
			fileLabel.className = 'editor-field';
			fileLabel.innerHTML = '<span>JSON file</span>';
			const fileInput = dc.createElement('input');
			fileInput.type = 'file';
			fileInput.accept = 'application/json,.json';
			fileLabel.appendChild( fileInput );
			fields.appendChild( fileLabel );

			const jsonLabel = dc.createElement('label');
			jsonLabel.className = 'editor-field admin-translation-json';
			jsonLabel.innerHTML = '<span>Or paste JSON</span>';
			const textarea = dc.createElement('textarea');
			textarea.id = 'translations-json';
			textarea.rows = 14;
			textarea.spellcheck = false;
			textarea.placeholder = '{\n  "format": "nino.translation",\n  …\n}';
			jsonLabel.appendChild( textarea );
			fields.appendChild( jsonLabel );

			fileInput.addEventListener( 'change', function() {
				const file = fileInput.files[0];
				if( file === undefined )
					return;
				file.text().then( function( value ) { textarea.value = value; Nino.admin.translations._setMessage( file.name+ ' loaded.', false ) } )
					.catch( function() { Nino.admin.translations._setMessage( 'Could not read that file.', true ) } );
			} );

			const importBtn = dc.createElement('button');
			importBtn.type = 'button';
			importBtn.className = 'editor-list-action';
			importBtn.textContent = 'Import into selected language';
			importBtn.addEventListener( 'click', function() {
				let translation;
				try {
					translation = Nino.admin.translations._parseJson( textarea.value );
				} catch( error ) {
					return Nino.admin.translations._setMessage( 'Invalid JSON: '+ error.message, true );
				}

				if( localeSelect.value === info.nativeLocale && wn.confirm( 'Importing here overwrites native source content. Continue?' ) === false )
					return;

				importBtn.disabled = true;
				importBtn.textContent = 'Importing …';
				Nino.admin.translations._apiCall( 'import', { targetLocale : localeSelect.value, translation : translation }, function( status, response ) {
					importBtn.disabled = false;
					importBtn.textContent = 'Import into selected language';
					if( status !== 200 || response === null )
						return Nino.admin.translations._setMessage( ( response && response.error ) ? response.error : 'Import failed.', true );

					const imported = response.text.imported+ response.elements.imported;
					const skipped = response.text.skipped+ response.elements.skipped;
					let message = imported+ ' values imported into '+ response.targetLocale+ ', '+ skipped+ ' skipped.';
					if( response.errors.length > 0 )
						message += ' '+ response.errors.join(' ');
					Nino.admin.translations._setMessage( message, response.errors.length > 0 );
				} );
			} );

			importCard.appendChild( fields );
			importCard.appendChild( importBtn );
			flow.appendChild( importCard );
			wrap.appendChild( flow );

			const message = dc.createElement('p');
			message.id = 'translations-message';
			message.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( message );
		},

		_setMessage : function( value, error ) {
			const message = dc.getElementById('translations-message');
			if( message === null )
				return;
			message.textContent = value;
			message.className = error === true ? 'admin-error' : 'admin-success';
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.translations.init );

})(window, document, document.documentElement, document.body);
