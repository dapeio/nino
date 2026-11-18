
/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	favicon.js							"Favicon" tab of the Images panel: the icon set of the
 *													site, made from one raster picture (and, if there is
 *													one, a vector icon). It shows the set as it stands, with
 *													the date of its files, and sends the picture with the
 *													two colours to favicon/generate. The view is the guide's
 *													first model: Generate is its one main button, Undo and
 *													Reset to the base set stand at its left in the bar, and
 *													what is wrong is said at the field of the picture, the
 *													vector icon or the colour it is about. The old set is
 *													kept once, so Undo brings it back - after a question, since
 *													that set is used up by it; Reset puts the base unit's set
 *													back after a question. A picture below the least size is
 *													refused here, with the reason, before it is sent; the
 *													server checks it all again. See Favicon/Favicon.php.
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
		// The status line of the form's bar - see Nino.adminUi.status()
		_status		: null,
		// What the form is made of, as _render() made it: the two file fields, the two colour fields and the buttons
		_parts		: null,
		// The colours the set was made with, as typed: what the form holds beyond them, and a picture that is chosen, is unsaved input
		_base			: { background : '', theme : '' },

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
		 *	A time of the server as the workbench writes a date and a time: the one way, in the interface language
		 *
		 *	@param		{number}		seconds					Seconds since 1970
		 *
		 *	@return		{string}
		 */
		_time : function( seconds ) {
			return Nino.adminUi.dateText( new Date( seconds * 1000 ) );
		},

		/**
		 *	A colour field - the colour block's - with its help behind the (?) at its name and what an empty field
		 *	leaves written in it as the example: the colour the manifest holds
		 *
		 *	@param		{string}		id
		 *	@param		{string}		label
		 *	@param		{string}		help
		 *
		 *	@return		{Object}										What color() answers
		 */
		_colour : function( id, label, help ) {

			const field = Nino.adminUi.color( { id : id, label : label } );

			field.input.maxLength = 7;
			field.input.placeholder = '#ffffff';

			// The block's own label is the first thing in it, and its name the first thing in the label
			const sentence = Nino.adminUi.fieldHelp( help, { field : field.input, name : label } );
			field.el.children[0].children[0].appendChild( sentence );

			// The block writes what the field is described by - its error and its contrast - at every change, and leaves the sentence
			// of the help out of it: the sentence is put back behind them, so that it is read at the field whatever was typed
			const keep = function() {

				const tokens = String( field.input.getAttribute('aria-describedby') ?? '' ).split(/\s+/).filter( function( token ) { return token !== '' } );

				if( tokens.indexOf( sentence.sentenceId ) === -1 )
					field.input.setAttribute( 'aria-describedby', tokens.concat( [ sentence.sentenceId ] ).join(' ') );
			};

			field.input.addEventListener( 'input', keep );
			field.input.addEventListener( 'change', keep );
			Array.from( field.el.children ).filter( function( part ) { return part.type === 'color' } ).forEach( function( picker ) { picker.addEventListener( 'input', keep ) } );

			return field;
		},

		/**
		 *	A line of text the form shows and does not take - the date of the set - with its name at the left
		 *
		 *	@param		{string}		label
		 *
		 *	@return		{Object}										{ el, value } - value is the paragraph that holds the text
		 */
		_readOnly : function( label ) {

			const field = Nino.adminUi.el( 'div', 'nino-admin-field' );
			const value = Nino.adminUi.el( 'p', '' );

			field.appendChild( Nino.adminUi.el( 'span', '', label ) );
			field.appendChild( value );

			return { el : field, value : value };
		},

		/**
		 *	The form, once: the set as it stands, the two pictures, the two colours and the bar
		 *
		 *	@param		{Element}		wrap
		 *
		 *	@return		void
		 */
		_render : function( wrap ) {

			const favicon = Nino.admin.favicon;
			const text = function( key ) { return Nino.content.getText( key ) };

			wrap.innerHTML = '';

			const form = dc.createElement('form');
			form.id = 'favicon-set';
			form.noValidate = true;

			const preview = dc.createElement('fieldset');
			const previewLegend = dc.createElement('legend');
			previewLegend.textContent = text('/_admin/favicon/label/set');
			preview.appendChild( previewLegend );
			Nino.adminUi.sectionHelp( previewLegend, preview, text('/_admin/favicon/hint/set') );

			const date = favicon._readOnly( text('/_admin/favicon/label/date') );
			date.value.id = 'favicon-date';
			preview.appendChild( date.el );

			const list = dc.createElement('ul');
			list.id = 'favicon-preview';
			list.className = 'admin-favicon-preview';
			preview.appendChild( list );

			form.appendChild( preview );

			const make = dc.createElement('fieldset');
			const makeLegend = dc.createElement('legend');
			makeLegend.textContent = text('/_admin/favicon/label/make');
			make.appendChild( makeLegend );
			Nino.adminUi.sectionHelp( makeLegend, make, Nino.adminUi.format( text('/_admin/favicon/hint/intro'), favicon._data.min ) );

			// What the server will take stays in view beside the picture: the least size, and the limits of an upload
			const limits = Nino.adminUi.uploadHint();
			const note = Nino.adminUi.format( text('/_admin/favicon/note/min'), favicon._data.min )+ ( limits === null ? '' : ' '+ limits.textContent );

			const file = Nino.adminUi.fileField( { label : text('/_admin/favicon/label/file'), help : text('/_admin/favicon/hint/file'), note : note, accept : '.png,.jpg,.jpeg,.webp', maxBytes : Nino.adminUi.limits().bytes, required : true } );
			const svg = Nino.adminUi.fileField( { label : text('/_admin/favicon/label/svg'), help : text('/_admin/favicon/hint/svg'), accept : '.svg' } );
			const background = favicon._colour( 'favicon-background', text('/_admin/favicon/label/background'), text('/_admin/favicon/hint/background') );
			const theme = favicon._colour( 'favicon-theme', text('/_admin/favicon/label/theme'), text('/_admin/favicon/hint/theme') );

			make.appendChild( file.el );
			make.appendChild( svg.el );
			make.appendChild( background.el );
			make.appendChild( theme.el );
			form.appendChild( make );

			// Undo and Reset act on the set that stands and stand beside Generate, which makes a new one: the form's one bar
			const undo = Nino.adminUi.button( text('/_admin/favicon/label/undo'), favicon._undo, { kind : 'secondary', id : 'favicon-undo' } );
			const reset = Nino.adminUi.button( text('/_admin/favicon/label/reset'), favicon._reset, { kind : 'secondary', id : 'favicon-reset' } );
			const generate = Nino.adminUi.button( text('/_admin/favicon/label/generate'), null, { type : 'submit', id : 'favicon-generate' } );

			const msg = dc.createElement('p');
			msg.id = 'favicon-msg';

			const bar = Nino.adminUi.actionBar( {
				status		: { line : msg, fresh : true },
				secondary	: [ reset, undo ],
				primary		: generate,
			} );

			bar.line.bind( form );
			favicon._status = bar.line;
			form.appendChild( bar.el );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); favicon._generate() } );
			wrap.appendChild( form );

			favicon._parts = { form : form, file : file, svg : svg, background : background, theme : theme, undo : undo, reset : reset, generate : generate, date : date.value, list : list };
			favicon._base = { background : '', theme : '' };
		},

		/**
		 *	Whether the form holds input nobody has made a set of: a picture or a vector icon that is chosen, or a colour that is
		 *	not the one the last set was made with (the guide: a file the form keeps is unsaved input)
		 *
		 *	@return		{boolean}
		 */
		isDirty : function() {

			const favicon = Nino.admin.favicon;
			const parts = favicon._parts;

			return parts !== null && ( parts.file.value() !== null || parts.svg.value() !== null
				|| parts.background.input.value !== favicon._base.background || parts.theme.input.value !== favicon._base.theme );
		},

		/**
		 *	Throw the input away: the pictures are taken out and the colours go back to the ones the last set was made with
		 *
		 *	@return		void
		 */
		discard : function() {

			const favicon = Nino.admin.favicon;
			const parts = favicon._parts;

			if( parts === null )
				return;

			parts.file.clear();
			parts.svg.clear();
			parts.background.set( favicon._base.background );
			parts.theme.set( favicon._base.theme );
			Nino.adminUi.clearFieldErrors( parts.form );
		},

		/**
		 *	Draw what the answer says: the set as it stands, the date, and which of Undo and Reset make sense
		 *
		 *	@return		void
		 */
		_paint : function() {

			const data = Nino.admin.favicon._data;
			const parts = Nino.admin.favicon._parts;
			const list = parts.list;
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
			parts.date.textContent = newest === 0 ? Nino.content.getText('/_admin/favicon/msg/none') : Nino.admin.favicon._time( newest );

			parts.undo.hidden = data.undo === null;
			parts.undo.title = data.undo === null ? '' : Nino.adminUi.format( Nino.content.getText('/_admin/favicon/msg/undo-from'), Nino.admin.favicon._time( data.undo ) );

			parts.reset.hidden = data.base !== true;

			// What an empty field leaves: the colour the manifest holds, said as the example in the field
			parts.background.input.placeholder = data.backgroundColor || '#ffffff';
			parts.theme.input.placeholder = data.themeColor || '#ffffff';
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
		 *	Where a sentence about one of the form's fields is said: the field's own error, and the focus to it
		 *
		 *	@param		{string}		name						file, svg, background or theme
		 *
		 *	@return		{Object}										{ say( text ), focus() }
		 */
		_target : function( name ) {

			const parts = Nino.admin.favicon._parts;

			if( name === 'file' || name === 'svg' )
				return { say : parts[name].error, focus : function() { parts[name].button.focus() } };

			const field = parts[name];

			return {
				say		: function( words ) { Nino.adminUi.fieldError( field.input.closest('label'), field.input, 'favicon-error-'+ field.input.id, words ) },
				focus	: function() { field.input.focus() },
			};
		},

		/**
		 *	The field a refusal of the server is about: the picture, the vector icon, or the colour it names
		 *
		 *	@param		{*}					response
		 *
		 *	@return		{Object|null}								{ say( text ), focus() } - null where no field is meant
		 */
		_fieldOf : function( response ) {

			const code = response !== null && typeof response === 'object' && typeof response.code === 'string' ? response.code : '';
			const named = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';

			if( code === 'favicon_colour' && named === 'background' )
				return Nino.admin.favicon._target('background');

			if( code === 'favicon_colour' && named === 'themeColor' )
				return Nino.admin.favicon._target('theme');

			if( code === 'favicon_svg' )
				return Nino.admin.favicon._target('svg');

			if( /^(favicon_type|favicon_too_small|image_|upload_)/.test( code ) === true )
				return Nino.admin.favicon._target('file');

			return null;
		},

		/**
		 *	Check the picture, then send it with the colours. A picture below the least size, or one the
		 *	server will not take, is said at its field before it is sent; the colours are checked by the
		 *	server, and by the colour field while they are typed
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the set was made, false otherwise
		 *
		 *	@return		void
		 */
		_generate : function( done ) {

			const favicon = Nino.admin.favicon;
			const parts = favicon._parts;
			const line = favicon._status;
			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			// A second order while one runs - a double click, Enter twice - is no order: it would keep the set the first made as the one Undo brings back
			if( parts.generate.disabled === true ) {
				report( false );
				return;
			}

			parts.generate.disabled = true;
			parts.form.setAttribute( 'aria-busy', 'true' );

			const release = function() {
				parts.generate.disabled = false;
				parts.form.removeAttribute('aria-busy');
			};

			// Said at the field, and in the line for all of them; the focus goes to it
			const refuse = function( words, field ) {
				release();
				line.fail( Nino.content.getText('/_admin/favicon/error/fields') );
				field.say( words );
				field.focus();
				report( false );
			};

			parts.file.clearError();
			parts.svg.clearError();

			// A colour that is typed and is none is said by the colour block already, and its sentence stays: the set is not made with it left out
			const wrong = [ parts.background, parts.theme ].find( function( field ) { return field.input.value.trim() !== '' && field.get() === null } );

			if( wrong !== undefined ) {
				release();
				line.fail( Nino.content.getText('/_admin/favicon/error/fields') );
				wrong.input.focus();
				report( false );
				return;
			}

			Nino.adminUi.clearFieldErrors( parts.form );

			const file = parts.file.value();

			if( file === null ) {
				refuse( Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), Nino.content.getText('/_admin/favicon/label/file') ), favicon._target('file') );
				return;
			}

			const send = function() {

				const extra = { file : file };
				const vector = parts.svg.value();

				if( vector !== null )
					extra.svg = vector;

				line.saving();

				favicon._apiCall( 'generate', {
					background	: parts.background.input.value.trim(),
					themeColor	: parts.theme.input.value.trim(),
				}, function( status, response ) {

					release();

					if( status !== 200 || response === null ) {

						const field = favicon._fieldOf( response );

						line.error( status, response, '/_admin/favicon/error/generate' );

						if( field !== null ) {
							field.say( Nino.adminUi.api.errorText( status, response, '/_admin/favicon/error/generate' ) );
							field.focus();
						}

						report( false );
						return;
					}

					parts.file.clear();
					parts.svg.clear();
					favicon._take( response );

					// The colours typed stay in their fields, and are what the set was made with
					favicon._base = { background : parts.background.input.value.trim(), theme : parts.theme.input.value.trim() };

					line.saved();
					parts.generate.focus();
					report( true );
				}, extra );
			};

			Nino.adminUi.checkImage( file, function( rejection ) {

				if( rejection !== null ) {
					refuse( Nino.adminUi.api.errorText( 400, rejection ), favicon._target('file') );
					return;
				}

				favicon._tooSmall( file, function( size ) {

					if( size !== null ) {
						refuse( Nino.adminUi.api.errorText( 400, { code : 'favicon_too_small', params : [ favicon._data.min, size.width, size.height ] } ), favicon._target('file') );
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
		 *	Put the previous set back, after asking: the set that stands is used up by it, and is not kept
		 *
		 *	@return		void
		 */
		_undo : function() {

			const favicon = Nino.admin.favicon;

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/favicon/confirm/undo-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/favicon/confirm/undo'), favicon._time( favicon._data.undo ?? 0 ) ),
				yes				: Nino.content.getText('/_admin/favicon/confirm/undo-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: favicon._parts.undo,
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					const line = favicon._status;
					line.saving();

					favicon._apiCall( 'undo', {}, function( status, response ) {

						if( status !== 200 || response === null ) {
							line.error( status, response, '/_admin/favicon/error/undo' );
							return;
						}

						favicon._take( response );
						line.idle( '' );
						Nino.adminUi.say( Nino.content.getText('/_admin/favicon/msg/undone') );
						favicon._parts.generate.focus();
					} );
				},
			} );
		},

		/**
		 *	Put the base unit's set back, after asking
		 *
		 *	@return		void
		 */
		_reset : function() {

			const favicon = Nino.admin.favicon;

			// The set that stands is replaced, and the one that was kept for Undo goes with it: a question about what is overwritten
			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/favicon/confirm/reset-title'),
				message		: Nino.content.getText('/_admin/favicon/confirm/reset'),
				kind			: 'danger',
				yes				: Nino.content.getText('/_admin/favicon/confirm/reset-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: favicon._parts.reset,
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					const line = favicon._status;
					line.saving();

					favicon._apiCall( 'reset', {}, function( status, response ) {

						if( status !== 200 || response === null ) {
							line.error( status, response, '/_admin/favicon/error/reset' );
							return;
						}

						favicon._take( response );
						line.idle( '' );
						Nino.adminUi.say( Nino.content.getText('/_admin/favicon/msg/reset-done') );
						favicon._parts.generate.focus();
					} );
				},
			} );
		},
	};

	// The shell asks before a picture that is chosen, or a colour typed and not made, is thrown away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'favicon', {
			isDirty : Nino.admin.favicon.isDirty,
			save		: function( done ) { Nino.admin.favicon._generate( done ) },
			discard : Nino.admin.favicon.discard,
		} );

})(window, document, document.documentElement, document.body);
