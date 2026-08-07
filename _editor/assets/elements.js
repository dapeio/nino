

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	elements.js							Admin "Elements" panel: browse types, list/create/edit/delete
 *													the elements within a type. Element *types* themselves are
 *													developer-only (\Nino\Elements::insertElementType), not exposed here.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.editor = wn.Nino.editor || {};

	Nino.editor.elements = {

		_locales				: [],
		_currentType		: null,
		_currentTypeTitle	: null,
		_currentModel		: null,
		_globalKeys			: [],
		_localeKeys			: [],
		_currentUri			: null,
		_isNew					: false,
		_globalValues		: {},
		_localeValues		: {},
		_selectedLocale	: null,
		_dirtyLocales		: [],
		_htmlEditors		: {},
		_pendingUri			: undefined,
		_loading				: false,
		_saving					: false,
		_listRequest		: 0,
		_formRequest		: 0,
		_ready					: false,
		DEFAULT_MAXLENGTH : 2000,

		/**
		 *	Load the available element types and render the type picker
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('elements-types') === null || Nino.editor.elements._loading === true || Nino.editor.elements._ready === true )
				return;

			Nino.editor.elements._loading = true;

			Nino.editor.elements._apiCall( 'types', {}, function( status, response ) {
				Nino.editor.elements._loading = false;
				if( status !== 200 || response === null )
					return Nino.editor.elements._showError( dc.getElementById('elements-types'), status, response );
				Nino.editor.elements._locales = response.locales;
				Nino.editor.sessionLocale.init( response.selectedLocale );
				Nino.editor.elements._renderTypes( response.types );
				Nino.editor.elements._ready = true;

				// _restoreFromHash() itself drills all the way down to whatever the hash
				// wants (and sets the hash correctly once it gets there) - only fall back
				// to the plain type list if there was nothing to restore
				if( Nino.editor.elements._restoreFromHash( response.types ) === false )
					Nino.editor.elements._showTypes();
			} );
		},

		/**
		 *	Re-apply whatever drill-down level this panel is currently on -
		 *	called when the user switches TO this tab, so the hash (only ever
		 *	written by router.set() while this panel is the visible one) gets
		 *	synced to reality instead of staying stale from before the switch
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.editor.elements._ready === false ) {
				Nino.editor.elements.init();
				return;
			}

			if( Nino.editor.elements._currentType === null )
				return Nino.editor.elements._showTypes();

			if( dc.getElementById('elements-form').classList.contains('editor-hidden') === false )
				return Nino.editor.elements._showFormView();

			if( dc.getElementById('elements-list').classList.contains('editor-hidden') === false )
				return Nino.editor.elements._showList();

			Nino.editor.elements._showTypes();
		},

		/**
		 *	If the url hash points at a specific type/element within this panel
		 *	(eg. after a refresh), jump straight there instead of leaving the
		 *	type list as the default view
		 *
		 *	@param		{Array}	types
		 *
		 *	@return		{boolean}	Whether a restore was actually initiated
		 */
		_restoreFromHash : function( types ) {

			const hash = Nino.editor.router.current();
			if( hash.panel !== 'elements' || hash.parts.length === 0 )
				return false;

			const type = types.find( function( t ) { return t.type === hash.parts[0] } );
			if( type === undefined )
				return false;

			Nino.editor.elements._pendingUri = hash.parts.length > 1 ? hash.parts[1] : undefined;
			Nino.editor.elements._selectType( type.type, type.model, type.title );
			return true;
		},

		/**
		 *	Call an elements/* admin action. Always posts to /_editor/ (dispatched
		 *	server-side via $_POST['action']), so this never depends on the
		 *	webserver routing anything beyond the already-required /_editor uri.
		 *	The trailing slash matters here: /_editor is a real directory on disk,
		 *	so some webservers (eg. nginx) auto-redirect the slash-less uri - and
		 *	that redirect can end up on the wrong scheme behind a reverse proxy,
		 *	which browsers block as mixed content for XHR. Nino's own routing
		 *	treats both forms identically (Http::cleanUri() strips trailing
		 *	slashes before matching), so posting with the slash is free.
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "elements/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *	@param		{Object}		[extra]				Extra multipart fields (eg. { file : File }) -
		 *																		sendRequest() already posts everything as
		 *																		multipart/form-data, so a raw File value
		 *																		just works alongside the usual "data" json
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback, extra ) {
			Nino.http.sendRequest( '/_editor/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, Object.assign( { action : 'elements/'+ endpoint, data : JSON.stringify( payload ) }, extra || {} ) );
		},

		/**
		 *	Show a failed request's status/error in a container, instead of
		 *	silently leaving it empty
		 *
		 *	@param		{Element}		container			Element to render the error into
		 *	@param		{number}		status				Xhr status code
		 *	@param		{*}					response			Parsed response body, if any
		 *
		 *	@return		void
		 */
		_showError : function( container, status, response ) {
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'editor-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/elements/error/load') );
			container.appendChild( p );
		},

		/**
		 *	Drill-down navigation: types -> list -> form, each level hiding its
		 *	parent (the main System/Texte/Elemente bar stays visible throughout,
		 *	so only the local "‹ Zurück" links in the list/form need to move
		 *	back up one level, not the whole page)
		 *
		 *	@return		void
		 */
		_showTypes : function() {
			dc.getElementById('elements-types').classList.remove('editor-hidden');
			dc.getElementById('elements-list').classList.add('editor-hidden');
			dc.getElementById('elements-form').classList.add('editor-hidden');
			Nino.editor.router.set( 'elements', [] );
		},

		_showList : function() {
			dc.getElementById('elements-types').classList.add('editor-hidden');
			dc.getElementById('elements-list').classList.remove('editor-hidden');
			dc.getElementById('elements-form').classList.add('editor-hidden');
			Nino.editor.router.set( 'elements', [ Nino.editor.elements._currentType ] );
		},

		_showFormView : function() {
			dc.getElementById('elements-types').classList.add('editor-hidden');
			dc.getElementById('elements-list').classList.add('editor-hidden');
			dc.getElementById('elements-form').classList.remove('editor-hidden');
			Nino.editor.router.set( 'elements', [ Nino.editor.elements._currentType, Nino.editor.elements._isNew ? 'new' : Nino.editor.elements._currentUri ] );
		},

		/**
		 *	Render the list of element types as buttons
		 *
		 *	@param		{Array}		types					[ { type, title, model }, ... ]
		 *
		 *	@return		void
		 */
		_renderTypes : function( types ) {

			const wrap = dc.getElementById('elements-types');
			wrap.innerHTML = '';

			types.forEach( function( entry ) {

				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'editor-type-btn';
				btn.dataset.type = entry.type;
				btn.classList.toggle( 'active', entry.type === Nino.editor.elements._currentType );

				const titleWrap = dc.createElement('div');
				titleWrap.textContent = entry.title;

				const descr = dc.createElement('div');
				descr.className = 'editor-type-btn-descr';
				descr.textContent = entry.descr;
				titleWrap.appendChild( descr );

				const chev = dc.createElement('span');
				chev.className = 'editor-view-button-chev';
				chev.setAttribute( 'aria-hidden', 'true' );
				chev.textContent = '›';

				btn.appendChild( titleWrap );
				btn.appendChild( chev );
				btn.addEventListener( 'click', function() { Nino.editor.elements._selectType( entry.type, entry.model, entry.title ) } );

				wrap.appendChild( btn );
			} );
		},

		/**
		 *	Select a type and load its element list
		 *
		 *	@param		{string}	type					Type name
		 *	@param		{Object}	model					Type model
		 *	@param		{string}	title					Type display title
		 *
		 *	@return		void
		 */
		_selectType : function( type, model, title ) {

			if( Nino.editor.elements._saving === true )
				return;

			const requestId = ++Nino.editor.elements._listRequest;
			++Nino.editor.elements._formRequest; // invalidate an element still loading
			Nino.editor.elements._currentType 			= type;
			Nino.editor.elements._currentTypeTitle	= title;
			Nino.editor.elements._currentModel			= model;
			Nino.editor.elements._globalKeys		= Object.keys( model ).filter( function( key ) { return ( model[key].locale ?? false ) !== true } );
			Nino.editor.elements._localeKeys		= Object.keys( model ).filter( function( key ) { return ( model[key].locale ?? false ) === true } );

			Nino.editor.elements._destroyHtmlEditors();
			dc.getElementById('elements-form').innerHTML = '';

			dc.querySelectorAll('.editor-type-btn').forEach( function( btn ) { btn.classList.toggle( 'active', btn.dataset.type === type ) } );

			Nino.editor.elements._apiCall( 'list', { type : type }, function( status, response ) {
				if( requestId !== Nino.editor.elements._listRequest || type !== Nino.editor.elements._currentType )
					return;
				if( status !== 200 || response === null )
					return Nino.editor.elements._showError( dc.getElementById('elements-list'), status, response );
				Nino.editor.elements._renderList( response.elements );
				Nino.editor.elements._showList();

				const pendingUri = Nino.editor.elements._pendingUri;
				Nino.editor.elements._pendingUri = undefined;
				if( pendingUri !== undefined )
					Nino.editor.elements._openForm( pendingUri === 'new' ? null : pendingUri );
			} );
		},

		/**
		 *	Render the element list for the current type, plus an "add new" button
		 *
		 *	@param		{Array}		elements			[ { uri, label }, ... ]
		 *
		 *	@return		void
		 */
		_renderList : function( elements ) {

			const wrap = dc.getElementById('elements-list');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = Nino.content.getText('/_editor/elements/label/backtypes');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.editor.elements._showTypes() } );
			wrap.appendChild( backLink );

			const title = dc.createElement('div');
			title.className = 'main-title--withuri';
			title.textContent = Nino.editor.elements._currentTypeTitle;
			wrap.appendChild( title );

			const uri = dc.createElement('div');
			uri.className = 'main-uri';
			uri.textContent = '/'+ Nino.editor.elements._currentType;
			wrap.appendChild( uri );

			const ul = dc.createElement('ul');
			elements.forEach( function( element ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';
				link.textContent = element.label;
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.editor.elements._openForm( element.uri ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			// "New element" as an action below the list, not above it
			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'editor-list-action';
			addBtn.textContent = Nino.content.getText('/_editor/elements/label/add');
			addBtn.addEventListener( 'click', function() { Nino.editor.elements._openForm( null ) } );
			wrap.appendChild( addBtn );
		},

		/**
		 *	Open the edit form for an existing element, or a blank one for a new element
		 *
		 *	@param		{string|null}	uri			Element uri, or null to create a new one
		 *
		 *	@return		void
		 */
		_openForm : function( uri ) {

			if( Nino.editor.elements._saving === true )
				return;

			const requestId = ++Nino.editor.elements._formRequest;
			const type = Nino.editor.elements._currentType;

			if( uri === null ) {
				Nino.editor.elements._isNew 				= true;
				Nino.editor.elements._currentUri 	= null;
				Nino.editor.elements._globalValues	= {};
				Nino.editor.elements._localeValues	= {};
				Nino.editor.elements._dirtyLocales	= [];
				Nino.editor.elements._selectedLocale = Nino.editor.sessionLocale.current ?? Nino.editor.elements._locales[0] ?? '';
				Nino.editor.elements._renderForm();
				Nino.editor.elements._showFormView();
				return;
			}

			Nino.editor.elements._apiCall( 'get', { type : type, uri : uri }, function( status, response ) {

				if( requestId !== Nino.editor.elements._formRequest || type !== Nino.editor.elements._currentType )
					return;

				if( status !== 200 || response === null ) {
					Nino.editor.elements._showError( dc.getElementById('elements-form'), status, response );
					Nino.editor.elements._showFormView();
					return;
				}

				// Prefer the remembered session locale, if this element actually has
				// data for it - otherwise fall back to whichever comes first
				const preferred = Nino.editor.sessionLocale.current;

				Nino.editor.elements._isNew 				= false;
				Nino.editor.elements._currentUri 	= uri;
				Nino.editor.elements._globalValues	= response.global;
				Nino.editor.elements._localeValues	= response.locales;
				Nino.editor.elements._dirtyLocales	= [];
				Nino.editor.elements._selectedLocale = ( preferred !== null && response.locales[preferred] !== undefined ) ? preferred : ( Object.keys( response.locales )[0] ?? Nino.editor.elements._locales[0] ?? '' );
				Nino.editor.elements._renderForm();
				Nino.editor.elements._showFormView();
			} );
		},

		/**
		 *	Render one model field as a labeled input, type-aware
		 *
		 *	@param		{string}	key						Field key
		 *	@param		{Object}	field					Model field definition ({ type, ... })
		 *	@param		{*}				value					Current value
		 *
		 *	@return		{Element}								<label> wrapping the input
		 */
		_renderField : function( key, field, value ) {

			const label = dc.createElement('label');
			label.className = 'editor-field';

			const displayName = Nino.editor.elements._fieldLabel( key );

			// A string field with html: true gets the same minimal rich-text editor as
			// Text's html-flagged keys, sanitized the same way server-side on save
			if( field.type === 'string' && field.html === true ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );
				const mount = dc.createElement('div');
				label.appendChild( mount );
				Nino.editor.elements._htmlEditors[key] = Nino.editor.htmlEditor.create( mount, value ?? '', field.maxlength ?? Nino.editor.elements.DEFAULT_MAXLENGTH );
				return label;
			}

			// A boolean field reads clearer as an explicit "Ja"/"Nein" choice than
			// a bare checkbox, especially for admins who don't think in booleans.
			// Both radios share data-field (see _readFieldByKey()'s :checked lookup),
			// so a plain querySelector('[data-field=...]') would always find the
			// first ("Ja") one regardless of which is actually selected
			if( field.type === 'boolean' ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );

				const group = dc.createElement('div');
				group.className = 'editor-field-radios';

				[ true, false ].forEach( function( boolValue ) {
					const optLabel = dc.createElement('label');
					optLabel.className = 'editor-field-radio';
					const radio = dc.createElement('input');
					radio.type = 'radio';
					radio.name = 'elements-radio-'+ key;
					radio.value = boolValue ? 'true' : 'false';
					radio.checked = ( value === true ) === boolValue;
					radio.dataset.field = key;
					radio.dataset.type = field.type;
					optLabel.appendChild( radio );
					optLabel.appendChild( dc.createTextNode( boolValue ? Nino.content.getText('/_editor/elements/label/yes') : Nino.content.getText('/_editor/elements/label/no') ) );
					group.appendChild( optLabel );
				} );

				label.appendChild( group );
				return label;
			}

			// A field with a fixed set of allowed values (model "options": [...])
			// renders as a <select> instead of its type's normal input, whatever
			// that type is - the value is still read/cast per field.type as usual
			if( Array.isArray( field.options ) === true && field.options.length > 0 ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );

				const select = dc.createElement('select');
				select.dataset.field = key;
				select.dataset.type = field.type;

				field.options.forEach( function( opt ) {
					const option = dc.createElement('option');
					option.value = opt;
					option.textContent = opt;
					option.selected = ( value !== null && String( value ) === String( opt ) );
					select.appendChild( option );
				} );

				label.appendChild( Nino.editor.elements._wrapWithSuffix( select, field ) );
				return label;
			}

			// An image field uploads immediately on file selection (not tied to the
			// form's "Speichern" button) - a replaced/discarded upload can then never
			// leave an orphaned file, since the server only deletes the previous one
			// once the new one is safely committed. Needs an already-saved element
			// (a uri to attach the upload to), so it's unavailable on a new one.
			if( field.type === 'image' ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );

				if( field.width && field.height ) {
					const dimensions = dc.createElement('p');
					dimensions.className = 'editor-field-image-dimensions';
					dimensions.textContent = Nino.content.getText('/_editor/elements/label/image-target')+ ' '+ field.width+ ' × '+ field.height+ ' px';
					label.appendChild( dimensions );
				}

				const wrap = dc.createElement('div');
				wrap.className = 'editor-field-image';

				const preview = dc.createElement('img');
				preview.className = 'editor-field-image-preview';
				preview.hidden = ! value;
				if( value )
					preview.src = Nino.editor.assetUrl( '/uploads/'+ value );
				wrap.appendChild( preview );

				const hiddenInput = dc.createElement('input');
				hiddenInput.type = 'hidden';
				hiddenInput.dataset.field = key;
				hiddenInput.dataset.type = field.type;
				hiddenInput.value = value ?? '';
				wrap.appendChild( hiddenInput );

				const msg = dc.createElement('p');
				msg.className = 'editor-field-image-msg';
				msg.setAttribute( 'aria-live', 'polite' );

				if( Nino.editor.elements._isNew === true ) {
					msg.textContent = Nino.content.getText('/_editor/elements/label/image-savefirst');
					wrap.appendChild( msg );
				} else {
					const fileInput = dc.createElement('input');
					fileInput.type = 'file';
					fileInput.accept = 'image/*';
					fileInput.addEventListener( 'change', function() {
						if( fileInput.files.length === 0 )
							return;
						Nino.editor.elements._uploadImage( key, fileInput.files[0], hiddenInput, preview, msg, fileInput );
					} );
					wrap.appendChild( fileInput );
					wrap.appendChild( msg );
				}

				label.appendChild( wrap );
				return label;
			}

			let input;

			if( field.type === 'integer' || field.type === 'double' ) {
				input = dc.createElement('input');
				input.type = 'number';
				input.step = ( field.type === 'double' ) ? 'any' : '1';
				input.value = ( value ?? '' );
			} else if( field.type === 'date' ) {
				input = dc.createElement('input');
				input.type = 'date';
				input.value = ( value ?? '' );
			} else if( field.type === 'datetime' ) {
				input = dc.createElement('input');
				input.type = 'datetime-local';
				input.value = ( value ?? '' );
			} else if( field.type === 'array' ) {
				input = dc.createElement('textarea');
				input.value = JSON.stringify( value ?? [] );
			} else {
				input = dc.createElement('textarea');
				input.value = ( value ?? '' );
			}

			input.dataset.field = key;
			input.dataset.type = field.type;

			// Non-string fields (boolean/integer/double/date/array) keep a plain label -
			// a character counter doesn't mean much for those
			if( field.type !== 'string' ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );
				label.appendChild( Nino.editor.elements._wrapWithSuffix( input, field ) );
				return label;
			}

			// Every plain string field: label name + right-aligned live character
			// count in the same row, plus the matching maxlength
			const maxlength = field.maxlength ?? Nino.editor.elements.DEFAULT_MAXLENGTH;
			input.maxLength = maxlength;

			const header = dc.createElement('div');
			header.className = 'editor-field-header';

			const nameSpan = dc.createElement('span');
			nameSpan.className = 'editor-field-name';
			nameSpan.textContent = displayName;
			header.appendChild( nameSpan );

			const counter = dc.createElement('span');
			counter.className = 'char-counter';

			function updateCounter() {
				const len = input.value.length;
				counter.textContent = len + ' / ' + maxlength;
				counter.classList.toggle( 'char-counter-limit', len >= maxlength );
			}

			input.addEventListener( 'input', updateCounter );
			updateCounter();

			header.appendChild( counter );
			label.appendChild( header );
			label.appendChild( Nino.editor.elements._wrapWithSuffix( input, field ) );

			return label;
		},

		/**
		 *	Wrap an input with a fixed unit/suffix shown to its right (eg. a
		 *	"price" field's model setting suffix: "€") - returns the input
		 *	itself, unwrapped, if the field has no suffix
		 *
		 *	@param		{Element}	input
		 *	@param		{Object}	field					Model field definition ({ type, suffix, ... })
		 *
		 *	@return		{Element}
		 */
		_wrapWithSuffix : function( input, field ) {

			if( !field.suffix )
				return input;

			const row = dc.createElement('div');
			row.className = 'editor-field-suffixed';
			row.appendChild( input );

			const suffix = dc.createElement('span');
			suffix.className = 'editor-field-suffix';
			suffix.textContent = field.suffix;
			row.appendChild( suffix );

			return row;
		},

		/**
		 *	Resolve a model field's admin-facing label: an optional per-type/per-
		 *	field translation from the admin's own text system (same locale the
		 *	admin dashboard itself is currently displayed in), falling back to
		 *	the raw model key if no translation exists for it
		 *
		 *	@param		{string}	key						Field key (eg. "title")
		 *
		 *	@return		{string}
		 */
		_fieldLabel : function( key ) {
			const translated = Nino.content.getText('/_editor/elements/field/'+ Nino.editor.elements._currentType+ '/'+ key);
			return translated !== '' ? translated : key;
		},

		/**
		 *	Read the current value of a field by key - html fields live in
		 *	_htmlEditors (there's no plain input to query), everything else is a
		 *	normal [data-field] input/textarea/select, or - for a boolean's two
		 *	radios sharing one data-field - whichever of them is :checked
		 *
		 *	@param		{string}	key
		 *	@param		{Object}	field					Model field definition ({ type, html, ... })
		 *
		 *	@return		{*}											Parsed value
		 */
		_readFieldByKey : function( key, field ) {

			if( field.type === 'string' && field.html === true )
				return ( Nino.editor.elements._htmlEditors[key] !== undefined ) ? Nino.editor.elements._htmlEditors[key].getValue() : '';

			const form = dc.getElementById('elements-form');
			const selector = '[data-field="'+ CSS.escape( key )+ '"]';
			const input = form?.querySelector( selector+ ':checked' ) ?? form?.querySelector( selector );
			return ( input === null || input === undefined ) ? '' : Nino.editor.elements._readField( input );
		},

		/**
		 *	Whether a required field is currently empty - reads the raw dom
		 *	value rather than _readFieldByKey()'s parsed one: integer/double
		 *	coerce an empty input to 0 there, which would make "required"
		 *	unable to ever catch a blank number/date field. Boolean is never
		 *	considered empty (a radio choice always has a value).
		 *
		 *	@param		{string}	key
		 *	@param		{Object}	field					Model field definition ({ type, html, required, ... })
		 *
		 *	@return		{boolean}
		 */
		_isFieldEmpty : function( key, field ) {

			if( field.type === 'boolean' )
				return false;

			if( field.type === 'string' && field.html === true ) {
				const editor = Nino.editor.elements._htmlEditors[key];
				return editor === undefined || editor.getValue().replace(/<[^>]+>/g, '').trim() === '';
			}

			if( field.type === 'array' ) {
				const value = Nino.editor.elements._readFieldByKey( key, field );
				return Array.isArray( value ) === false || value.length === 0;
			}

			const form = dc.getElementById('elements-form');
			const input = form?.querySelector( '[data-field="'+ CSS.escape( key )+ '"]' );
			return ( input === null || input === undefined ) || input.value.trim() === '';
		},

		/**
		 *	Labels of every currently-visible required field that's empty -
		 *	global fields always, locale fields only for the selected locale
		 *	(the only one actually being submitted)
		 *
		 *	@return		{Array<string>}
		 */
		_missingRequiredFields : function() {

			const keys = Nino.editor.elements._globalKeys.concat( Nino.editor.elements._localeKeys );

			return keys
				.filter( function( key ) { return ( Nino.editor.elements._currentModel[key].required ?? false ) === true } )
				.filter( function( key ) { return Nino.editor.elements._isFieldEmpty( key, Nino.editor.elements._currentModel[key] ) } )
				.map( function( key ) { return Nino.editor.elements._fieldLabel( key ) } );
		},

		/**
		 *	Destroy any currently mounted html-editor instances (removes their
		 *	document-level selectionchange listener) - call before clearing/
		 *	replacing the DOM that contains them
		 *
		 *	@return		void
		 */
		_destroyHtmlEditors : function() {
			Object.keys( Nino.editor.elements._htmlEditors ).forEach( function( key ) { Nino.editor.elements._htmlEditors[key].destroy() } );
			Nino.editor.elements._htmlEditors = {};
		},

		/**
		 *	Read the current value of a rendered field, type-aware
		 *
		 *	@param		{Element}	input					Input/textarea rendered by _renderField()
		 *
		 *	@return		{*}											Parsed value
		 */
		_readField : function( input ) {

			// _readFieldByKey() already resolved this to the :checked radio (or
			// the single checkbox, for callers still passing one directly) - either
			// way its own .checked is true by definition, so read .value instead
			if( input.dataset.type === 'boolean' )
				return input.type === 'radio' ? input.value === 'true' : input.checked;
			if( input.dataset.type === 'integer' )
				return parseInt( input.value, 10 ) || 0;
			if( input.dataset.type === 'double' )
				return parseFloat( input.value ) || 0;
			if( input.dataset.type === 'array' )
				try { return JSON.parse( input.value ); } catch( e ) { return []; }

			return input.value;
		},

		/**
		 *	Store the currently visible locale fields into _localeValues before switching locale
		 *
		 *	@return		void
		 */
		_storeVisibleLocaleFields : function() {

			const wrap = dc.getElementById('elements-form-locale-fields');
			if( wrap === null || Nino.editor.elements._selectedLocale === null )
				return;

			const previous = Nino.editor.elements._localeValues[Nino.editor.elements._selectedLocale] ?? {};
			const values = {};
			Nino.editor.elements._localeKeys.forEach( function( key ) { values[key] = Nino.editor.elements._readFieldByKey( key, Nino.editor.elements._currentModel[key] ) } );

			const changed = Nino.editor.elements._localeKeys.some( function( key ) {
				return Nino.editor.elements._fieldValuesEqual( Nino.editor.elements._currentModel[key], previous[key], values[key] ) === false;
			} );

			if( changed === true && Nino.editor.elements._dirtyLocales.indexOf( Nino.editor.elements._selectedLocale ) === -1 )
				Nino.editor.elements._dirtyLocales.push( Nino.editor.elements._selectedLocale );

			Nino.editor.elements._localeValues[Nino.editor.elements._selectedLocale] = values;
		},

		/**
		 *	Compare a stored field with the value its control currently returns.
		 *	An absent value and that control type's empty value are equivalent -
		 *	merely visiting a translation must not mark it as edited.
		 *
		 *	@param		{Object}	field
		 *	@param		{*}			before
		 *	@param		{*}			after
		 *
		 *	@return		{boolean}
		 */
		_fieldValuesEqual : function( field, before, after ) {

			function normalized( value ) {
				if( value !== null && value !== undefined )
					return value;
				if( field.type === 'array' )
					return [];
				if( field.type === 'boolean' )
					return false;
				if( field.type === 'integer' || field.type === 'double' )
					return 0;
				return '';
			}

			return JSON.stringify( normalized( before ) ) === JSON.stringify( normalized( after ) );
		},

		/**
		 *	Translations one click on Save must persist. Previously the browser
		 *	remembered edits made before a locale switch, then submitted only the
		 *	last visible locale. Keep edit order (important when the first request
		 *	creates a new element), and fall back to the visible locale when no
		 *	translation changed (eg. a save that only changes global fields).
		 *
		 *	@return		{Array<string>}
		 */
		_saveLocales : function() {

			if( Nino.editor.elements._localeKeys.length === 0 )
				return [ Nino.editor.elements._selectedLocale ];

			const locales = Nino.editor.elements._dirtyLocales.slice();
			if( locales.length === 0 )
				locales.push( Nino.editor.elements._selectedLocale );

			return locales;
		},

		/**
		 *	Disable the form while its locale sequence is in flight. Besides
		 *	preventing a duplicate submit, this freezes the locale selector so a
		 *	callback can never be applied to a different visible translation.
		 *
		 *	@param		{boolean}	pending
		 *
		 *	@return		void
		 */
		_setFormPending : function( pending ) {
			const wrap = dc.getElementById('elements-form');
			if( wrap === null )
				return;
			wrap.classList.toggle( 'editor-pending', pending );
			wrap.querySelectorAll('input, textarea, select, button').forEach( function( el ) { el.disabled = pending } );
			wrap.querySelectorAll('[contenteditable]').forEach( function( el ) {
				el.contentEditable = pending ? 'false' : 'true';
				el.setAttribute( 'aria-disabled', pending ? 'true' : 'false' );
			} );
		},

		/**
		 *	Re-render the locale-fields wrap for the currently selected locale
		 *
		 *	@return		void
		 */
		_renderLocaleFields : function() {

			// Only the locale-scoped html editors need destroying/rebuilding on switch -
			// global ones stay mounted for the whole form's lifetime
			Nino.editor.elements._localeKeys.forEach( function( key ) {
				if( Nino.editor.elements._htmlEditors[key] !== undefined ) {
					Nino.editor.elements._htmlEditors[key].destroy();
					delete Nino.editor.elements._htmlEditors[key];
				}
			} );

			const wrap = dc.getElementById('elements-form-locale-fields');
			wrap.innerHTML = '';

			const values = Nino.editor.elements._localeValues[Nino.editor.elements._selectedLocale] ?? {};

			const heading = dc.getElementById('elements-form-heading');
			if( heading !== null )
				heading.textContent = Nino.editor.elements._headingText( values );

			Nino.editor.elements._localeKeys.forEach( function( key ) {
				wrap.appendChild( Nino.editor.elements._renderField( key, Nino.editor.elements._currentModel[key], values[key] ?? null ) );
			} );
		},

		/**
		 *	Build the edit form's heading: the title in whichever locale is
		 *	currently selected, with the uri (the element's actual, immutable
		 *	identifier) alongside in brackets - or just the uri if this type
		 *	has no title field, or it's empty for that locale yet
		 *
		 *	@param		{Object}	[localeValues]	This locale's field values ({ title, ... })
		 *
		 *	@return		{string}
		 */
		_headingText : function( localeValues ) {
			const title = ( localeValues ?? {} ).title;
			return ( title !== undefined && title !== null && title !== '' ) ? title+ ' ('+ Nino.editor.elements._currentUri+ ')' : Nino.editor.elements._currentUri;
		},

		/**
		 *	Render the full edit/create form for the current element
		 *
		 *	@return		void
		 */
		_renderForm : function() {

			Nino.editor.elements._destroyHtmlEditors();

			const wrap = dc.getElementById('elements-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = Nino.content.getText('/_editor/elements/label/back')+ ' '+ Nino.editor.elements._currentTypeTitle;
			backLink.addEventListener( 'click', function( ev ) {
				ev.preventDefault();
				if( Nino.editor.elements._saving === true )
					return;
				Nino.editor.elements._destroyHtmlEditors();
				Nino.editor.elements._showList();
			} );
			wrap.appendChild( backLink );

			const form = dc.createElement('form');
			form.id = 'elements-edit-form';

			// Uri as the form's title - editable (it's how a new element gets its
			// identifier) when new, otherwise a plain heading, just like Text's
			// category name; consistent with Text, it can't be changed afterwards
			if( Nino.editor.elements._isNew === true ) {

				const uriLabel = dc.createElement('label');
				uriLabel.className = 'editor-field';
				const uriSpan = dc.createElement('span');
				uriSpan.textContent = Nino.content.getText('/_editor/elements/label/uri');
				uriLabel.appendChild( uriSpan );
				const uriInput = dc.createElement('input');
				uriInput.type = 'text';
				uriInput.id = 'elements-form-uri';
				uriInput.required = true;
				uriInput.value = '';
				uriLabel.appendChild( uriInput );
				form.appendChild( uriLabel );

				// Only shown here - once an element exists its uri is fixed
				// (shown as a plain heading below), so the hint no longer applies
				const uriHint = dc.createElement('p');
				uriHint.className = 'editor-field-hint';
				uriHint.textContent = Nino.content.getText('/_editor/elements/label/uri-hint');
				form.appendChild( uriHint );

			} else {

				// Title (in the currently selected locale) reads better than the raw
				// uri, which stays alongside in brackets since it's still the element's
				// actual identifier (eg. used in links) - kept in sync with the locale
				// select by _renderLocaleFields()

				const title = dc.createElement('div');
				title.className = 'main-title--withuri';
				title.textContent = Nino.editor.elements._headingText( Nino.editor.elements._localeValues[Nino.editor.elements._selectedLocale] );
				wrap.appendChild( title );

				const uri = dc.createElement('div');
				uri.className = 'main-uri';
				uri.textContent = '/'+ Nino.editor.elements._currentType + '/' + Nino.editor.elements._currentUri;
				wrap.appendChild( uri );

				const uriInput = dc.createElement('input');
				uriInput.type = 'hidden';
				uriInput.id = 'elements-form-uri';
				uriInput.value = Nino.editor.elements._currentUri;
				form.appendChild( uriInput );
			}

			// Global fields - only rendered when this type actually has any
			// (a type with only locale fields, eg. services, has none)
			if( Nino.editor.elements._globalKeys.length > 0 ) {

				const globalWrap = dc.createElement('fieldset');
				globalWrap.id = 'elements-form-global';
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_editor/elements/label/global');
				globalWrap.appendChild( legend );

				Nino.editor.elements._globalKeys.forEach( function( key ) {
					globalWrap.appendChild( Nino.editor.elements._renderField( key, Nino.editor.elements._currentModel[key], Nino.editor.elements._globalValues[key] ?? null ) );
				} );

				form.appendChild( globalWrap );
			}

			// Locale fields
			if( Nino.editor.elements._localeKeys.length > 0 ) {

				const localeWrap = dc.createElement('fieldset');
				localeWrap.id = 'elements-form-locale';
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_editor/elements/label/locale');
				localeWrap.appendChild( legend );

				const select = dc.createElement('select');
				select.id = 'elements-form-locale-select';
				Nino.editor.elements._locales.forEach( function( locale ) {
					const option = dc.createElement('option');
					option.value = locale;
					option.textContent = locale;
					option.selected = ( locale === Nino.editor.elements._selectedLocale );
					select.appendChild( option );
				} );
				select.addEventListener( 'change', function() {
					Nino.editor.elements._storeVisibleLocaleFields();
					Nino.editor.elements._selectedLocale = select.value;
					Nino.editor.sessionLocale.set( select.value );
					Nino.editor.elements._renderLocaleFields();
				} );
				localeWrap.appendChild( select );

				const fieldsWrap = dc.createElement('div');
				fieldsWrap.id = 'elements-form-locale-fields';
				localeWrap.appendChild( fieldsWrap );

				form.appendChild( localeWrap );
			}

			// Actions
			const actions = dc.createElement('div');
			actions.id = 'elements-form-actions';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_editor/elements/label/save');
			actions.appendChild( saveBtn );

			if( Nino.editor.elements._isNew === false ) {
				const delBtn = dc.createElement('button');
				delBtn.type = 'button';
				delBtn.classList.add('editor-danger');
				delBtn.textContent = Nino.content.getText('/_editor/elements/label/delete');
				delBtn.addEventListener( 'click', function() { Nino.editor.elements._delete() } );
				actions.appendChild( delBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'elements-form-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.editor.elements._save() } );

			wrap.appendChild( form );

			if( Nino.editor.elements._localeKeys.length > 0 )
				Nino.editor.elements._renderLocaleFields();
		},

		/**
		 *	Collect the form's current values and save (insert or update) the element
		 *
		 *	@return		void
		 */
		_save : function() {

			if( Nino.editor.elements._saving === true )
				return;

			Nino.editor.elements._storeVisibleLocaleFields();

			const uri = dc.getElementById('elements-form-uri').value.trim();
			let msg = dc.getElementById('elements-form-msg');

			if( uri === '' ) {
				msg.textContent = Nino.content.getText('/_editor/elements/error/uri');
				return;
			}

			const missing = Nino.editor.elements._missingRequiredFields();
			if( missing.length > 0 ) {
				msg.textContent = Nino.content.getText('/_editor/elements/error/required')+ ' '+ missing.join(', ');
				return;
			}

			const globalFields = {};
			Nino.editor.elements._globalKeys.forEach( function( key ) { globalFields[key] = Nino.editor.elements._readFieldByKey( key, Nino.editor.elements._currentModel[key] ) } );

			const locales = Nino.editor.elements._saveLocales();
			const wasNew = Nino.editor.elements._isNew;
			let position = 0;
			let created = false;

			Nino.editor.elements._saving = true;
			Nino.editor.elements._setFormPending( true );
			msg.textContent = Nino.content.getText('/_editor/elements/msg/pending');

			function saveNextLocale() {

				const locale = locales[position];
				// Global fields only need one write. Re-sending them for every
				// translation repeated field callbacks and made a later locale request
				// capable of reverting a transformation performed by the first.
				const fields = Object.assign( {}, position === 0 ? globalFields : {}, Nino.editor.elements._localeValues[locale] ?? {} );

				Nino.editor.elements._apiCall( 'save', {
					type 		: Nino.editor.elements._currentType,
					uri 		: uri,
					locale 	: locale,
					isNew 	: wasNew === true && position === 0,
					fields 	: fields,
				}, function( status, response ) {

					if( status !== 200 || response === null ) {

						// The first locale may already have created the element before a
						// later locale failed. Reflect that durable state immediately so a
						// retry updates the element rather than attempting a second insert.
						if( wasNew === true && created === true ) {
							Nino.editor.elements._renderForm();
							Nino.editor.router.set( 'elements', [ Nino.editor.elements._currentType, Nino.editor.elements._currentUri ] );
							msg = dc.getElementById('elements-form-msg');
						}

						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/elements/error/save') );
						Nino.editor.elements._saving = false;
						Nino.editor.elements._setFormPending( false );
						return;
					}

					if( wasNew === true && position === 0 ) {
						Nino.editor.elements._isNew = false;
						Nino.editor.elements._currentUri = uri;
						created = true;
					}

					Nino.editor.elements._globalKeys.forEach( function( key ) { Nino.editor.elements._globalValues[key] = response.element[key] ?? null } );
					Nino.editor.elements._localeValues[locale] = Nino.editor.elements._localeValues[locale] ?? {};
					Nino.editor.elements._localeKeys.forEach( function( key ) { Nino.editor.elements._localeValues[locale][key] = response.element[key] ?? null } );

					const dirtyAt = Nino.editor.elements._dirtyLocales.indexOf( locale );
					if( dirtyAt !== -1 )
						Nino.editor.elements._dirtyLocales.splice( dirtyAt, 1 );

					position++;
					if( position < locales.length ) {
						saveNextLocale();
						return;
					}

					// Stay on the form after saving. A newly-created element is rendered
					// once more to lock its uri and expose the delete action; existing
					// elements keep focus and selection exactly where they were.
					if( wasNew === true ) {
						Nino.editor.elements._renderForm();
						Nino.editor.router.set( 'elements', [ Nino.editor.elements._currentType, Nino.editor.elements._currentUri ] );
					}

					msg = dc.getElementById('elements-form-msg');
					msg.textContent = Nino.content.getText('/_editor/elements/msg/saved');
					Nino.editor.elements._saving = false;
					Nino.editor.elements._setFormPending( false );

					const savedType = Nino.editor.elements._currentType;
					Nino.editor.elements._apiCall( 'list', { type : savedType }, function( listStatus, listResponse ) {
						if( listStatus === 200 && listResponse !== null && savedType === Nino.editor.elements._currentType )
							Nino.editor.elements._renderList( listResponse.elements );
					} );
				} );
			}

			saveNextLocale();
		},

		/**
		 *	Upload a new image for one "image" field, immediately - the server
		 *	commits it straight to the element record and deletes the previous
		 *	file, so there's nothing left to do here beyond reflecting the result
		 *
		 *	@param		{string}	key							Field key
		 *	@param		{File}		file						Chosen file
		 *	@param		{Element}	hiddenInput			The field's data-field-carrying hidden input
		 *	@param		{Element}	preview					<img> preview element
		 *	@param		{Element}	msg							Status message element
		 *	@param		{Element}	fileInput				The <input type=file> itself, disabled while pending
		 *
		 *	@return		void
		 */
		_uploadImage : function( key, file, hiddenInput, preview, msg, fileInput ) {

			fileInput.disabled = true;
			msg.className = 'editor-field-image-msg';
			msg.textContent = Nino.content.getText('/_editor/elements/msg/pending');

			Nino.editor.elements._apiCall( 'uploadimage', {
				type 		: Nino.editor.elements._currentType,
				uri 		: Nino.editor.elements._currentUri,
				locale 	: Nino.editor.elements._selectedLocale,
				key 		: key,
			}, function( status, response ) {

				fileInput.disabled = false;
				fileInput.value = '';

				if( status !== 200 || response === null ) {
					msg.className = 'editor-field-image-msg error';
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/elements/error/save') );
					return;
				}

				hiddenInput.value = response.filename;
				preview.src = response.url;
				preview.hidden = false;
				msg.textContent = Nino.content.getText('/_editor/elements/msg/saved');
			}, { file : file } );
		},

		/**
		 *	Delete the currently open element, after confirmation
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( Nino.editor.elements._saving === true )
				return;

			if( wn.confirm( Nino.content.getText('/_editor/elements/confirm/delete') ) === false )
				return;

			const type = Nino.editor.elements._currentType;
			const model = Nino.editor.elements._currentModel;
			const title = Nino.editor.elements._currentTypeTitle;
			Nino.editor.elements._saving = true;
			Nino.editor.elements._setFormPending( true );

			Nino.editor.elements._apiCall( 'delete', { type : type, uri : Nino.editor.elements._currentUri }, function( status, response ) {
				if( status !== 200 ) {
					Nino.editor.elements._saving = false;
					Nino.editor.elements._setFormPending( false );
					const msg = dc.getElementById('elements-form-msg');
					if( msg !== null )
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/elements/error/save') );
					return;
				}
				Nino.editor.elements._saving = false;
				Nino.editor.elements._selectType( type, model, title );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
