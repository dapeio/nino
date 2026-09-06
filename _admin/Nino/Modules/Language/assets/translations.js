/**
 *	Nino								A compact filesystembased php framework
 *	Admin							Native Text + Elements translation hand-off. Exports
 *										the versioned JSON document, accepts it back from a
 *										file or paste, and merges it into one chosen locale.
 *										A tab of the Language panel (see Language::tabs()).
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
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'translations/'+ endpoint, data : JSON.stringify( payload ) } );
		},

		_showError : function( status, response ) {
			const wrap = dc.getElementById('translations-content');
			wrap.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/load') );
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
				throw new TypeError( Nino.content.getText('/_admin/translations/error/object') );
			return parsed;
		},

		_render : function() {

			const info = Nino.admin.translations._info;
			const wrap = dc.getElementById('translations-content');
			wrap.innerHTML = '';

			const intro = dc.createElement('header');
			intro.className = 'admin-translations-intro';
			const kicker = dc.createElement('span');
			kicker.textContent = Nino.content.getText('/_admin/translations/label/workflow');
			intro.appendChild( kicker );
			const title = dc.createElement('h1');
			title.textContent = Nino.content.getText('/_admin/translations/label/title');
			intro.appendChild( title );
			// The native locale sits emphasised inside the sentence, wherever
			// the language puts it
			const lead = dc.createElement('p');
			const leadParts = Nino.content.getText('/_admin/translations/hint/intro').split( '%s' );
			lead.appendChild( dc.createTextNode( leadParts[0] ) );
			const native = dc.createElement('strong');
			native.textContent = info.nativeLocale;
			lead.appendChild( native );
			lead.appendChild( dc.createTextNode( leadParts[1] ?? '' ) );
			intro.appendChild( lead );
			wrap.appendChild( intro );

			const flow = dc.createElement('div');
			flow.className = 'admin-translations-flow';

			const exportCard = dc.createElement('section');
			exportCard.className = 'nino-admin-card admin-translation-card';
			exportCard.innerHTML = '<span class="admin-step-number">1</span><div class="admin-translation-card-copy"><h2></h2><p></p></div>';
			exportCard.querySelector('h2').textContent = Nino.content.getText('/_admin/translations/label/export');
			exportCard.querySelector('p').textContent = Nino.content.getText('/_admin/translations/hint/export')
				.replace( '%d', String( info.textCount ) ).replace( '%n', String( info.elementCount ) );

			const exportBtn = dc.createElement('button');
			exportBtn.type = 'button';
			exportBtn.className = 'nino-admin-btn-primary';
			exportBtn.textContent = Nino.content.getText('/_admin/translations/label/download').replace( '%s', info.nativeLocale );
			exportBtn.addEventListener( 'click', function() {
				exportBtn.disabled = true;
				exportBtn.textContent = Nino.content.getText('/_admin/translations/msg/preparing');
				Nino.admin.translations._apiCall( 'export', {}, function( status, response ) {
					exportBtn.disabled = false;
					exportBtn.textContent = Nino.content.getText('/_admin/translations/label/download').replace( '%s', info.nativeLocale );
					if( status !== 200 || response === null )
						return Nino.admin.translations._setMessage( Nino.content.getText('/_admin/translations/error/export'), true );

					const blob = new Blob( [ JSON.stringify( response, null, 2 )+ '\n' ], { type : 'application/json' } );
					const url = URL.createObjectURL( blob );
					const a = dc.createElement('a');
					a.href = url;
					a.download = 'nino-translation-'+ info.nativeLocale+ '.json';
					dc.body.appendChild( a );
					a.click();
					a.remove();
					URL.revokeObjectURL( url );
					Nino.admin.translations._setMessage( Nino.content.getText('/_admin/translations/msg/downloaded'), false );
				} );
			} );
			exportCard.appendChild( exportBtn );
			flow.appendChild( exportCard );

			const importCard = dc.createElement('section');
			importCard.className = 'nino-admin-card admin-translation-card admin-translation-card-import';
			importCard.innerHTML = '<span class="admin-step-number">2</span><div class="admin-translation-card-copy"><h2></h2><p></p></div>';
			importCard.querySelector('h2').textContent = Nino.content.getText('/_admin/translations/label/import');
			importCard.querySelector('p').textContent = Nino.content.getText('/_admin/translations/hint/import');

			const fields = dc.createElement('div');
			fields.className = 'admin-translation-fields';

			const localeLabel = dc.createElement('label');
			localeLabel.className = 'nino-admin-field';
			localeLabel.appendChild( dc.createElement('span') ).textContent = Nino.content.getText('/_admin/translations/label/target');
			const localeSelect = dc.createElement('select');
			localeSelect.id = 'translations-target-locale';
			info.locales.forEach( function( locale ) {
				const option = dc.createElement('option');
				option.value = locale;
				option.textContent = locale+ ( locale === info.nativeLocale ? ' '+ Nino.content.getText('/_admin/translations/label/native') : '' );
				localeSelect.appendChild( option );
			} );
			localeSelect.value = info.locales.find( function( locale ) { return locale !== info.nativeLocale } ) ?? info.nativeLocale;
			localeLabel.appendChild( localeSelect );
			fields.appendChild( localeLabel );

			const fileLabel = dc.createElement('label');
			fileLabel.className = 'nino-admin-field';
			fileLabel.appendChild( dc.createElement('span') ).textContent = Nino.content.getText('/_admin/translations/label/file');
			const fileInput = dc.createElement('input');
			fileInput.type = 'file';
			fileInput.accept = 'application/json,.json';
			fileLabel.appendChild( fileInput );
			fields.appendChild( fileLabel );

			const jsonLabel = dc.createElement('label');
			jsonLabel.className = 'nino-admin-field admin-translation-json';
			jsonLabel.appendChild( dc.createElement('span') ).textContent = Nino.content.getText('/_admin/translations/label/paste');
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
				file.text().then( function( value ) { textarea.value = value; Nino.admin.translations._setMessage( Nino.content.getText('/_admin/translations/msg/loaded').replace( '%s', file.name ), false ) } )
					.catch( function() { Nino.admin.translations._setMessage( Nino.content.getText('/_admin/translations/error/read'), true ) } );
			} );

			const importBtn = dc.createElement('button');
			importBtn.type = 'button';
			importBtn.className = 'nino-admin-btn-primary';
			importBtn.textContent = Nino.content.getText('/_admin/translations/label/importbtn');
			importBtn.addEventListener( 'click', function() {
				let translation;
				try {
					translation = Nino.admin.translations._parseJson( textarea.value );
				} catch( error ) {
					return Nino.admin.translations._setMessage( Nino.content.getText('/_admin/translations/error/json').replace( '%s', error.message ), true );
				}

				if( localeSelect.value === info.nativeLocale && wn.confirm( Nino.content.getText('/_admin/translations/confirm/native') ) === false )
					return;

				importBtn.disabled = true;
				importBtn.textContent = Nino.content.getText('/_admin/translations/msg/importing');
				Nino.admin.translations._apiCall( 'import', { targetLocale : localeSelect.value, translation : translation }, function( status, response ) {
					importBtn.disabled = false;
					importBtn.textContent = Nino.content.getText('/_admin/translations/label/importbtn');
					if( status !== 200 || response === null )
						return Nino.admin.translations._setMessage( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/translations/error/import'), true );

					const imported = response.text.imported+ response.elements.imported;
					const skipped = response.text.skipped+ response.elements.skipped;
					let message = Nino.content.getText('/_admin/translations/msg/imported').replace( '%d', String( imported ) ).replace( '%s', response.targetLocale ).replace( '%n', String( skipped ) );
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
			message.className = error === true ? 'nino-admin-error' : 'admin-success';
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.translations.init );

})(window, document, document.documentElement, document.body);
