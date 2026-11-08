
/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	favicon.js							"Favicon" tab of the Images panel: the icon set of the
 *													site, made from one raster picture (and, if there is
 *													one, a vector icon). It shows the set as it stands, with
 *													the date of its files, and sends the picture with the
 *													two colours to favicon/generate. The old set is kept
 *													once, so Undo brings it back; Reset puts the base unit's
 *													set back after a question. A picture below the least
 *													size is refused here, with the reason, before it is sent;
 *													the server checks it all again. See Favicon/Favicon.php.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.favicon = {

		_ready		: false,
		_loading	: false,
		_data			: null,
		_status		: null,

		/**
		 *	Ask for the set and draw the tab
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('favicon-form');
			if( wrap === null || Nino.admin.favicon._loading === true )
				return;

			Nino.admin.favicon._loading = true;

			Nino.admin.favicon._apiCall( 'status', {}, function( status, response ) {

				Nino.admin.favicon._loading = false;

				if( status !== 200 || response === null ) {
					Nino.adminUi.showError( wrap, status, response, '/_admin/images/error/load' );
					Nino.admin.favicon._ready = false;
					return;
				}

				Nino.admin.favicon._data = response;

				if( Nino.admin.favicon._ready === false )
					Nino.admin.favicon._render( wrap );

				Nino.admin.favicon._paint();
				Nino.admin.favicon._ready = true;
			} );
		},

		/*	The form is drawn once and never again: what was typed stays where
			it was left. Only the set it shows is asked for again, since
			another tab or another person may have written it	*/
		showCurrent : function() {
			Nino.admin.favicon.init();
		},

		/**
		 *	Call a favicon/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "status", becomes "favicon/status")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *	@param		{Object}		[extra]				Extra multipart fields (the files)
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback, extra ) {
			Nino.adminUi.api.call( 'favicon/'+ endpoint, payload, callback, extra );
		},

		/**
		 *	The rows of the preview: for each file of the answer its caption (the size it is shown as, or
		 *	the name for the two that have none), whether it is there and its address
		 *
		 *	@param		{Object}		data						The answer of favicon/status or of a write
		 *
		 *	@return		{Array}										[ { name, caption, exists, url, width, time } ]
		 */
		_rows : function( data ) {

			return ( data.files || [] ).map( function( file ) {

				// The two containers have no size of their own to say
				let caption = file.name === 'favicon.ico' ? '.ico' : ( file.name === 'favicon.svg' ? '.svg' : file.name );
				if( file.exists === true && file.width > 0 && file.height > 0 && caption === file.name )
					caption = file.width+ ' × '+ file.height;

				return { name : file.name, caption : caption, exists : file.exists === true, url : file.url || '', width : file.width || 0, time : file.time || 0 };
			} );
		},

		/**
		 *	A time of the server as the person reads dates: in the language of the page
		 *
		 *	@param		{number}		seconds					Seconds since 1970
		 *
		 *	@return		{string}
		 */
		_time : function( seconds ) {
			return new Date( seconds * 1000 ).toLocaleString( dE !== null && dE.lang ? dE.lang : undefined );
		},

		/**
		 *	The form, once: the preview, the two pictures, the two colours and the buttons
		 *
		 *	@param		{Element}		wrap
		 *
		 *	@return		void
		 */
		_render : function( wrap ) {

			wrap.innerHTML = '';

			const form = dc.createElement('form');
			form.id = 'favicon-set';

			const preview = dc.createElement('fieldset');
			const previewLegend = dc.createElement('legend');
			previewLegend.textContent = Nino.content.getText('/_admin/favicon/label/set');
			preview.appendChild( previewLegend );

			const date = dc.createElement('p');
			date.id = 'favicon-date';
			date.className = 'nino-admin-hint';
			preview.appendChild( date );

			const list = dc.createElement('ul');
			list.id = 'favicon-preview';
			list.className = 'admin-favicon-preview';
			preview.appendChild( list );
			// What acts on the set that stands sits with it; Generate is the form's and stays in the action bar
			const row = dc.createElement('div');
			row.className = 'admin-favicon-actions';
			const undo = dc.createElement('button');
			undo.type = 'button';
			undo.id = 'favicon-undo';
			undo.className = 'nino-admin-btn-secondary';
			undo.textContent = Nino.content.getText('/_admin/favicon/label/undo');
			undo.addEventListener( 'click', Nino.admin.favicon._undo );
			row.appendChild( undo );

			const reset = dc.createElement('button');
			reset.type = 'button';
			reset.id = 'favicon-reset';
			reset.className = 'nino-admin-btn-secondary';
			reset.textContent = Nino.content.getText('/_admin/favicon/label/reset');
			reset.addEventListener( 'click', Nino.admin.favicon._reset );
			row.appendChild( reset );

			preview.appendChild( row );

			form.appendChild( preview );

			const make = dc.createElement('fieldset');
			const makeLegend = dc.createElement('legend');
			makeLegend.textContent = Nino.content.getText('/_admin/favicon/label/make');
			make.appendChild( makeLegend );

			const intro = dc.createElement('p');
			intro.className = 'nino-admin-hint';
			intro.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/favicon/hint/intro'), Nino.admin.favicon._data.min );
			make.appendChild( intro );

			make.appendChild( Nino.admin.favicon._field( 'file', 'file', '/_admin/favicon/label/file', '/_admin/favicon/hint/file', 'image/png,image/jpeg,image/webp' ) );
			const hint = Nino.adminUi.uploadHint();
			if( hint !== null )
				make.appendChild( hint );
			make.appendChild( Nino.admin.favicon._field( 'svg', 'file', '/_admin/favicon/label/svg', '/_admin/favicon/hint/svg', 'image/svg+xml,.svg' ) );
			make.appendChild( Nino.admin.favicon._field( 'background', 'text', '/_admin/favicon/label/background', '/_admin/favicon/hint/background', '' ) );
			make.appendChild( Nino.admin.favicon._field( 'theme', 'text', '/_admin/favicon/label/theme', '/_admin/favicon/hint/theme', '' ) );
			form.appendChild( make );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const generate = dc.createElement('button');
			generate.type = 'submit';
			generate.id = 'favicon-generate';
			generate.className = 'nino-admin-btn-primary';
			generate.textContent = Nino.content.getText('/_admin/favicon/label/generate');
			actions.appendChild( generate );

			const msg = dc.createElement('p');
			msg.id = 'favicon-msg';
			msg.className = 'nino-admin-actionbar-status';
			actions.appendChild( msg );
			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.favicon._generate() } );

			Nino.admin.favicon._status = Nino.adminUi.status( msg, { saved : Nino.content.getText('/_admin/favicon/msg/done') } );

			wrap.appendChild( form );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot('favicon');
		},

		/**
		 *	One labelled field with its hint
		 *
		 *	@param		{string}		id
		 *	@param		{string}		type
		 *	@param		{string}		label					Fill key
		 *	@param		{string}		hint					Fill key
		 *	@param		{string}		accept				For a file field
		 *
		 *	@return		{Element}
		 */
		_field : function( id, type, label, hint, accept ) {

			const wrap = dc.createElement('label');
			wrap.className = 'nino-admin-field';

			const caption = dc.createElement('span');
			caption.textContent = Nino.content.getText( label );
			wrap.appendChild( caption );

			const input = dc.createElement('input');
			input.type = type;
			input.id = 'favicon-'+ id;
			input.dataset.field = id;
			if( type === 'file' )
				input.accept = accept;
			else {
				input.maxLength = 7;
				input.placeholder = '#ffffff';
				input.setAttribute( 'autocomplete', 'off' );
				input.setAttribute( 'spellcheck', 'false' );
			}
			wrap.appendChild( input );

			const text = dc.createElement('small');
			text.className = 'nino-admin-hint';
			text.textContent = Nino.content.getText( hint );
			wrap.appendChild( text );

			return wrap;
		},

		/**
		 *	Draw what the answer says: the set as it stands, the date, and which of Undo and Reset make sense
		 *
		 *	@return		void
		 */
		_paint : function() {

			const data = Nino.admin.favicon._data;
			const list = dc.getElementById('favicon-preview');
			list.innerHTML = '';

			Nino.admin.favicon._rows( data ).forEach( function( row ) {

				const item = dc.createElement('li');
				item.className = 'admin-favicon-item';

				if( row.exists === true ) {
					const img = dc.createElement('img');
					img.src = row.url;
					img.alt = '';
					img.className = 'admin-favicon-image';
					if( row.width > 0 )
						img.width = Math.min( row.width, 96 );
					item.appendChild( img );
				} else {
					const none = dc.createElement('span');
					none.className = 'admin-favicon-missing';
					none.textContent = Nino.content.getText('/_admin/favicon/label/missing');
					item.appendChild( none );
				}

				const caption = dc.createElement('span');
				caption.className = 'admin-favicon-caption';
				caption.textContent = row.caption;
				item.appendChild( caption );
				list.appendChild( item );
			} );

			const newest = Math.max.apply( null, [ 0 ].concat( data.files.map( function( file ) { return file.time || 0 } ) ) );
			dc.getElementById('favicon-date').textContent = newest === 0
				? Nino.content.getText('/_admin/favicon/msg/none')
				: Nino.adminUi.format( Nino.content.getText('/_admin/favicon/msg/date'), Nino.admin.favicon._time( newest ) );

			const undo = dc.getElementById('favicon-undo');
			undo.hidden = data.undo === null;
			undo.title = data.undo === null ? '' : Nino.adminUi.format( Nino.content.getText('/_admin/favicon/msg/undo-from'), Nino.admin.favicon._time( data.undo ) );

			dc.getElementById('favicon-reset').hidden = data.base !== true;

			dc.getElementById('favicon-background').placeholder = data.backgroundColor || '#ffffff';
			dc.getElementById('favicon-theme').placeholder = data.themeColor || '#ffffff';
		},

		/**
		 *	Take the answer of a write as the set and draw it
		 *
		 *	@param		{Object}		response
		 *
		 *	@return		void
		 */
		_take : function( response ) {
			Nino.admin.favicon._data = response;
			Nino.admin.favicon._paint();
			// The shell's other panels show the stamp of the set only through the page itself
		},

		/**
		 *	Check the picture, then send it with the colours. A picture below the least size, or one the
		 *	server will not take, is said here; the colours are checked by the server
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the set was made, false otherwise
		 *
		 *	@return		void
		 */
		_generate : function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = Nino.admin.favicon._status;
			const file = dc.getElementById('favicon-file');
			const svg = dc.getElementById('favicon-svg');

			if( file.files.length === 0 ) {
				line.fail( Nino.content.getText('/_admin/favicon/error/nofile') );
				report( false );
				return;
			}

			const send = function() {

				const extra = { file : file.files[0] };
				if( svg.files.length > 0 )
					extra.svg = svg.files[0];

				const button = dc.getElementById('favicon-generate');
				button.disabled = true;
				line.saving();

				Nino.admin.favicon._apiCall( 'generate', {
					background	: dc.getElementById('favicon-background').value,
					themeColor	: dc.getElementById('favicon-theme').value,
				}, function( status, response ) {

					button.disabled = false;

					if( status !== 200 || response === null ) {
						line.error( status, response, '/_admin/favicon/error/generate' );
						report( false );
						return;
					}

					file.value = '';
					svg.value = '';
					Nino.admin.favicon._take( response );
					line.saved();

					// The colours typed stay in their fields, and are what was sent
					if( typeof Nino.admin.dirty === 'object' )
						Nino.admin.dirty.snapshot('favicon');
					report( true );
				}, extra );
			};

			Nino.adminUi.checkImage( file.files[0], function( rejection ) {

				if( rejection !== null ) {
					line.fail( Nino.adminUi.api.errorText( 400, rejection ) );
					report( false );
					return;
				}

				Nino.admin.favicon._tooSmall( file.files[0], function( size ) {

					if( size !== null ) {
						line.fail( Nino.adminUi.api.errorText( 400, { code : 'favicon_too_small', params : [ Nino.admin.favicon._data.min, size.width, size.height ] } ) );
						report( false );
						return;
					}

					send();
				} );
			} );
		},

		/**
		 *	Whether the picture is below the least size, as far as the browser can tell
		 *
		 *	@param		{File}			file
		 *	@param		{Function}	done						Called once with { width, height } of a picture that is too small, else null
		 *
		 *	@return		void
		 */
		_tooSmall : function( file, done ) {

			if( typeof wn.createImageBitmap !== 'function' ) {
				done( null );
				return;
			}

			const min = Nino.admin.favicon._data.min;

			wn.createImageBitmap( file ).then( function( bitmap ) {
				const small = bitmap.width < min || bitmap.height < min;
				const size = { width : bitmap.width, height : bitmap.height };
				if( typeof bitmap.close === 'function' )
					bitmap.close();
				done( small === true ? size : null );
			}, function() {
				done( null );
			} );
		},

		/**
		 *	Put the previous set back
		 *
		 *	@return		void
		 */
		_undo : function() {

			const line = Nino.admin.favicon._status;
			line.saving();

			Nino.admin.favicon._apiCall( 'undo', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					line.error( status, response, '/_admin/favicon/error/undo' );
					return;
				}

				Nino.admin.favicon._take( response );
				line.saved();
			} );
		},

		/**
		 *	Put the base unit's set back, after asking
		 *
		 *	@return		void
		 */
		_reset : function() {

			const opener = dc.getElementById('favicon-reset');

			Nino.adminUi.confirm( 'big', {
				message		: Nino.content.getText('/_admin/favicon/confirm/reset'),
				yes				: Nino.content.getText('/_admin/favicon/label/reset'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					const line = Nino.admin.favicon._status;
					line.saving();

					Nino.admin.favicon._apiCall( 'reset', {}, function( status, response ) {

						if( status !== 200 || response === null ) {
							line.error( status, response, '/_admin/favicon/error/reset' );
							return;
						}

						Nino.admin.favicon._take( response );
						line.saved();
					} );
				},
			} );
		},
	};

	// The shell asks before a colour typed and not made is thrown away (see Nino.admin.dirty). A file
	// field does not count as unsaved input, only the typed colours. A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'favicon', function() { return dc.getElementById('favicon-set') }, function( done ) { Nino.admin.favicon._generate( done ) } );

})(window, document, document.documentElement, document.body);
