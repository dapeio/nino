

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Elements" module: full CRUD on an element type's actual
 *													*content* - the entries themselves, per type and locale.
 *													Sibling of the "Element Types" module next to it, which
 *													owns the schema (model/field definitions) and never
 *													touches content; this one is the exact inverse.
 *
 *													Deliberately a near-copy of _editor/assets/elements.js
 *													rather than a shared file: each tool owns its own
 *													frontend module (same as images.js/users.js/text.js
 *													already are on both sides), so _admin keeps working
 *													whether or not _editor is deployed. Differences to the
 *													_editor original are only:
 *														- posts to /_admin/ with develements/* actions
 *														- no url-hash router (that's an _editor concept)
 *														- no Nino.content translation layer: _admin is
 *														  developer-facing and English-only, and a field is
 *														  labeled by its raw model key on purpose - the
 *														  schema name is what you're working with here
 *														- a read-only raw-bucket view per element (see
 *														  _renderRaw), matching how this tool's Text module
 *														  also exposes what _editor hides
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.elements = {

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
		_raw						: {},
		_selectedLocale	: null,
		_dirtyLocales		: [],
		_htmlEditors		: {},
		// Referenced type uri -> [ { uri, label } ], the choices an element
		// field's select offers. Refilled on every form open rather than
		// cached across them: adding an element to the referenced type has to
		// show up in the very next form that points at it
		_referenceOptions	: {},
		_loading				: false,
		_saving					: false,
		_typesRequest		: 0,
		_listRequest		: 0,
		_formRequest		: 0,
		_ready					: false,
		_deepLinkHandled	: false,
		DEFAULT_MAXLENGTH : 2000,

		/**
		 *	Load the available element types and render the type picker
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('elements-types') === null || Nino.admin.elements._loading === true || Nino.admin.elements._ready === true )
				return;

			const requestId = ++Nino.admin.elements._typesRequest;
			Nino.admin.elements._loading = true;

			Nino.admin.elements._apiCall( 'types', {}, function( status, response ) {
				Nino.admin.elements._loading = false;
				// invalidate() ran while this was in flight: the types/models
				// about to be cached here are the ones it just declared stale
				if( requestId !== Nino.admin.elements._typesRequest )
					return;
				if( status !== 200 || response === null )
					return Nino.admin.elements._showError( dc.getElementById('elements-types'), status, response );
				Nino.admin.elements._locales = response.locales;
				Nino.admin.elements._selectedLocale = response.selectedLocale ?? ( response.locales[0] ?? '' );
				Nino.admin.elements.DEFAULT_MAXLENGTH = response.maxlength ?? Nino.admin.elements.DEFAULT_MAXLENGTH;
				Nino.admin.elements._renderTypes( response.types );
				Nino.admin.elements._showTypes();
				Nino.admin.elements._ready = true;
				Nino.admin.elements._followDeepLink( response.types );
			} );
		},

		/**
		 *	Open a requested collection after the normal type list has loaded.
		 *	Used by Template Builder resource links; malformed/unknown values simply
		 *	leave the regular type picker visible.
		 *
		 *	@param		{Array}		types
		 *
		 *	@return		void
		 */
		_followDeepLink : function( types ) {

			if( Nino.admin.elements._deepLinkHandled === true )
				return;

			Nino.admin.elements._deepLinkHandled = true;
			const requested = new URLSearchParams( wn.location.search || '' ).get('type');
			const entry = types.find( function( type ) { return type.type === requested } );

			if( entry !== undefined )
				Nino.admin.elements._selectType( entry.type, entry.model, entry.title );
		},

		/**
		 *	Drop everything this module cached and fall back to its type
		 *	picker, so the next showCurrent() fetches again.
		 *
		 *	The schema this module renders every form from - the type list,
		 *	each type's model, its global/locale key split - belongs to the
		 *	Element Types module next door (see elementtypes.js), and is read
		 *	exactly once per page load: init() returns early forever after
		 *	_ready, and showCurrent() only re-shows a drill-down level from
		 *	what is already in memory. Without this, a field added, renamed
		 *	or removed over there simply never appeared here until the whole
		 *	page was reloaded - and a form still open on the old model would
		 *	have gone on editing keys the type no longer has.
		 *
		 *	Public on purpose: it is this module's own contract with its
		 *	sibling ("your save invalidates my cache"), not a private flag
		 *	for the other side to reach in and flip.
		 *
		 *	@return		void
		 */
		invalidate : function() {

			// Any list/get response still in flight targets the type that is
			// being dropped here - both guards those callbacks check are reset
			// below, but bump the sequence counters too, exactly as
			// _selectType() does, in case the same type is picked again before
			// they arrive
			++Nino.admin.elements._typesRequest;
			++Nino.admin.elements._listRequest;
			++Nino.admin.elements._formRequest;

			Nino.admin.elements._destroyHtmlEditors();

			Nino.admin.elements._ready 						= false;
			Nino.admin.elements._currentType 			= null;
			Nino.admin.elements._currentTypeTitle	= null;
			Nino.admin.elements._currentModel			= null;
			Nino.admin.elements._globalKeys				= [];
			Nino.admin.elements._localeKeys				= [];
			Nino.admin.elements._currentUri				= null;
			Nino.admin.elements._isNew						= false;
			Nino.admin.elements._globalValues			= {};
			Nino.admin.elements._localeValues			= {};
			Nino.admin.elements._raw							= {};
			Nino.admin.elements._dirtyLocales			= [];

			if( dc.getElementById('elements-types') === null )
				return;

			// The rendered list/form belong to the model that just changed -
			// left standing, showCurrent() would flash them before init()'s
			// response replaces the picker behind them
			dc.getElementById('elements-list').innerHTML = '';
			dc.getElementById('elements-form').innerHTML = '';
			Nino.admin.elements._showTypes();
		},

		/**
		 *	Re-apply whatever drill-down level this panel is currently on -
		 *	called when the user switches to this tab
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.elements._ready === false ) {
				Nino.admin.elements.init();
				return;
			}

			if( Nino.admin.elements._currentType === null )
				return Nino.admin.elements._showTypes();

			if( dc.getElementById('elements-form').classList.contains('admin-hidden') === false )
				return Nino.admin.elements._showFormView();

			if( dc.getElementById('elements-list').classList.contains('admin-hidden') === false )
				return Nino.admin.elements._showList();

			Nino.admin.elements._showTypes();
		},

		/**
		 *	Call a develements/* dev action. The trailing slash on /_admin/
		 *	matters: /_admin is a real directory on disk, so some webservers
		 *	auto-redirect the slash-less uri, and that redirect can land on the
		 *	wrong scheme behind a reverse proxy - which browsers block as mixed
		 *	content for XHR. Nino's own routing treats both forms identically
		 *	(Http::cleanUri() strips trailing slashes before matching).
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "develements/list")
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
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, Object.assign( { action : 'develements/'+ endpoint, data : JSON.stringify( payload ) }, extra || {} ) );
		},

		/**
		 *	Show a failed request's status/error in a container, instead of
		 *	silently leaving it empty
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
			p.className = 'admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			container.appendChild( p );
		},

		/**
		 *	Drill-down navigation: types -> list -> form, each level hiding its
		 *	parent (the module tab bar itself stays visible throughout, so only
		 *	the local back-links need to move up one level)
		 *
		 *	@return		void
		 */
		_showTypes : function() {
			dc.getElementById('elements-types').classList.remove('admin-hidden');
			dc.getElementById('elements-list').classList.add('admin-hidden');
			dc.getElementById('elements-form').classList.add('admin-hidden');
		},

		_showList : function() {
			dc.getElementById('elements-types').classList.add('admin-hidden');
			dc.getElementById('elements-list').classList.remove('admin-hidden');
			dc.getElementById('elements-form').classList.add('admin-hidden');
		},

		_showFormView : function() {
			dc.getElementById('elements-types').classList.add('admin-hidden');
			dc.getElementById('elements-list').classList.add('admin-hidden');
			dc.getElementById('elements-form').classList.remove('admin-hidden');
		},

		/**
		 *	Render the list of element types as drill-down rows
		 *
		 *	@param		{Array}		types					[ { type, title, descr, model }, ... ]
		 *
		 *	@return		void
		 */
		_renderTypes : function( types ) {

			const wrap = dc.getElementById('elements-types');
			wrap.innerHTML = '';

			if( types.length === 0 ) {
				const p = dc.createElement('p');
				p.className = 'admin-hint';
				p.textContent = 'No element types yet - create one in the "Element Types" tab first.';
				wrap.appendChild( p );
				return;
			}

			const ul = dc.createElement('ul');
			ul.className = 'admin-drill-list';

			types.forEach( function( entry ) {

				const li = dc.createElement('li');
				const link = dc.createElement('a');
				link.href = '#';
				link.dataset.type = entry.type;
				link.classList.toggle( 'active', entry.type === Nino.admin.elements._currentType );

				const copy = dc.createElement('span');
				copy.className = 'admin-list-copy';
				const title = dc.createElement('strong');
				title.textContent = entry.title;

				const descr = dc.createElement('small');
				descr.textContent = entry.descr;
				copy.appendChild( title );
				copy.appendChild( descr );
				link.appendChild( copy );

				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.elements._selectType( entry.type, entry.model, entry.title ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );

			wrap.appendChild( ul );
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

			if( Nino.admin.elements._saving === true )
				return;

			const requestId = ++Nino.admin.elements._listRequest;
			++Nino.admin.elements._formRequest; // invalidate an element still loading
			Nino.admin.elements._currentType 			= type;
			Nino.admin.elements._currentTypeTitle	= title;
			Nino.admin.elements._currentModel			= model;
			Nino.admin.elements._globalKeys		= Object.keys( model ).filter( function( key ) { return ( model[key].locale ?? false ) !== true } );
			Nino.admin.elements._localeKeys		= Object.keys( model ).filter( function( key ) { return ( model[key].locale ?? false ) === true } );

			Nino.admin.elements._destroyHtmlEditors();
			dc.getElementById('elements-form').innerHTML = '';

			dc.querySelectorAll('#elements-types .admin-drill-list a').forEach( function( link ) { link.classList.toggle( 'active', link.dataset.type === type ) } );

			Nino.admin.elements._apiCall( 'list', { type : type }, function( status, response ) {
				if( requestId !== Nino.admin.elements._listRequest || type !== Nino.admin.elements._currentType )
					return;
				if( status !== 200 || response === null )
					return Nino.admin.elements._showError( dc.getElementById('elements-list'), status, response );
				Nino.admin.elements._renderList( response.elements );
				Nino.admin.elements._showList();
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
			// No chevron in the text - .back-link::before already supplies one
			backLink.textContent = 'Back to overview';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.elements._showTypes() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title--withuri';
			title.textContent = Nino.admin.elements._currentTypeTitle;
			wrap.appendChild( title );

			const uri = dc.createElement('div');
			uri.className = 'main-uri';
			uri.textContent = '/'+ Nino.admin.elements._currentType;
			wrap.appendChild( uri );

			const ul = dc.createElement('ul');
			ul.className = 'admin-drill-list';
			elements.forEach( function( element ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';
				link.textContent = element.label;
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.elements._openForm( element.uri ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'editor-list-action';
			addBtn.textContent = 'New element';
			addBtn.addEventListener( 'click', function() { Nino.admin.elements._openForm( null ) } );
			wrap.appendChild( Nino.adminUi.listActions( [ addBtn ] ) );
		},

		/**
		 *	Open the edit form for an existing element, or a blank one for a new element
		 *
		 *	@param		{string|null}	uri			Element uri, or null to create a new one
		 *
		 *	@return		void
		 */
		_openForm : function( uri ) {

			if( Nino.admin.elements._saving === true )
				return;

			const requestId = ++Nino.admin.elements._formRequest;
			const type = Nino.admin.elements._currentType;

			if( uri === null ) {
				Nino.admin.elements._isNew 				= true;
				Nino.admin.elements._currentUri 	= null;
				Nino.admin.elements._globalValues	= {};
				Nino.admin.elements._localeValues	= {};
				Nino.admin.elements._raw 					= {};
				Nino.admin.elements._dirtyLocales	= [];
				Nino.admin.elements._selectedLocale = Nino.admin.elements._selectedLocale ?? Nino.admin.elements._locales[0] ?? '';
				Nino.admin.elements._loadReferenceOptions( function() {
					if( requestId !== Nino.admin.elements._formRequest || type !== Nino.admin.elements._currentType )
						return;
					Nino.admin.elements._renderForm();
					Nino.admin.elements._showFormView();
				} );
				return;
			}

			Nino.admin.elements._apiCall( 'get', { type : type, uri : uri }, function( status, response ) {

				if( requestId !== Nino.admin.elements._formRequest || type !== Nino.admin.elements._currentType )
					return;

				if( status !== 200 || response === null ) {
					Nino.admin.elements._showError( dc.getElementById('elements-form'), status, response );
					Nino.admin.elements._showFormView();
					return;
				}

				// Keep the locale currently being worked in, if this element has
				// data for it - otherwise fall back to whichever comes first
				const preferred = Nino.admin.elements._selectedLocale;

				Nino.admin.elements._isNew 				= false;
				Nino.admin.elements._currentUri 	= uri;
				Nino.admin.elements._globalValues	= response.global;
				Nino.admin.elements._localeValues	= response.locales;
				Nino.admin.elements._raw 					= response.raw ?? {};
				Nino.admin.elements._dirtyLocales	= [];
				Nino.admin.elements._selectedLocale = ( preferred !== null && response.locales[preferred] !== undefined ) ? preferred : ( Object.keys( response.locales )[0] ?? Nino.admin.elements._locales[0] ?? '' );
				Nino.admin.elements._loadReferenceOptions( function() {
					if( requestId !== Nino.admin.elements._formRequest || type !== Nino.admin.elements._currentType )
						return;
					Nino.admin.elements._renderForm();
					Nino.admin.elements._showFormView();
				} );
			} );
		},

		/**
		 *	Fill _referenceOptions with the elements every 'element' field in
		 *	the current model may point at, then continue. Reuses the ordinary
		 *	list action - the referenced type's elements are exactly what it
		 *	returns, labels included - rather than adding an endpoint that
		 *	would answer the same question a second way.
		 *
		 *	Runs before the form renders so every select is complete on first
		 *	paint: a locale switch re-renders the fields, and options arriving
		 *	afterwards would have to be threaded through that too.
		 *
		 *	@param		{Function}	done				Called once every referenced type has answered
		 *
		 *	@return		void
		 */
		_loadReferenceOptions : function( done ) {

			Nino.admin.elements._referenceOptions = {};

			const model = Nino.admin.elements._currentModel ?? {};
			const types = [];

			Object.keys( model ).forEach( function( key ) {
				const referenced = model[key].elementType;
				if( model[key].type === 'element' && referenced && types.indexOf( referenced ) === -1 )
					types.push( referenced );
			} );

			if( types.length === 0 )
				return done();

			// A type that errors (deleted since the model was written) resolves
			// to an empty list rather than holding the form back - _renderField()
			// then shows the dangling value and says so
			let pending = types.length;

			types.forEach( function( referenced ) {
				Nino.admin.elements._apiCall( 'list', { type : referenced }, function( status, response ) {
					Nino.admin.elements._referenceOptions[referenced] = ( status === 200 && response !== null ) ? ( response.elements ?? [] ) : [];
					if( --pending === 0 )
						done();
				} );
			} );
		},

		/**
		 *	Render one model field as a labeled input, type-aware
		 *
		 *	@param		{string}	key						Field key
		 *	@param		{Object}	field					Model field definition ({ type, ... })
		 *	@param		{*}				value					Current value
		 *
		 *	@return		{Element}								Field wrapper
		 */
		_renderField : function( key, field, value ) {

			const displayName = Nino.admin.elements._fieldLabel( key );
			const isHtml = field.type === 'string' && field.html === true;
			// A contenteditable inside <label> is invalid interactive markup and
			// can make drag-selection fail in Safari. Rich text gets a semantic
			// group; ordinary controls retain their native label wrapper.
			const label = dc.createElement( isHtml ? 'div' : 'label' );
			label.className = 'editor-field';
			if( isHtml ) {
				label.setAttribute( 'role', 'group' );
				label.setAttribute( 'aria-label', displayName );
			}

			// A string field with html: true gets the same minimal rich-text editor
			// as Text's html-flagged keys, sanitized the same way server-side on save
			if( isHtml ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );
				const mount = dc.createElement('div');
				label.appendChild( mount );
				Nino.admin.elements._htmlEditors[key] = Nino.editor.htmlEditor.create( mount, value ?? '', field.maxlength ?? Nino.admin.elements.DEFAULT_MAXLENGTH );
				return label;
			}

			// A boolean reads clearer as an explicit yes/no choice than a bare
			// checkbox. Both radios share data-field (see _readFieldByKey()'s
			// :checked lookup), so a plain querySelector('[data-field=...]') would
			// always find the first one regardless of which is actually selected
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
					optLabel.appendChild( dc.createTextNode( boolValue ? 'Yes' : 'No' ) );
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

				label.appendChild( Nino.admin.elements._wrapWithSuffix( select, field ) );
				return label;
			}

			// A reference to another element: the choices are that type's own
			// elements, loaded before the form rendered (see
			// _loadReferenceOptions()), and the stored value is the referenced
			// element's full uri - exactly what \Nino\Elements::getElement()
			// takes, so a template never has to re-join it with the model
			if( field.type === 'element' ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );

				const select = dc.createElement('select');
				select.dataset.field = key;
				select.dataset.type = field.type;

				const options = Nino.admin.elements._referenceOptions[field.elementType] ?? [];
				const current = ( value === null || value === undefined ) ? '' : String( value );

				// Always offered, even on a required field: the browser's own
				// "select an option" is not what holds the save back - the same
				// _missingRequiredFields() check every other type goes through
				// is, and it needs an empty state to be able to catch
				const empty = dc.createElement('option');
				empty.value = '';
				empty.textContent = ( field.required === true ) ? '— please choose —' : '— none —';
				empty.selected = ( current === '' );
				select.appendChild( empty );

				options.forEach( function( element ) {
					const option = dc.createElement('option');
					option.value = '/'+ field.elementType+ '/'+ element.uri;
					option.textContent = element.label;
					option.selected = ( option.value === current );
					select.appendChild( option );
				} );

				// A reference whose target was deleted since keeps its value
				// instead of silently resetting to none - a save the editor did
				// not intend would otherwise drop the reference for good
				if( current !== '' && options.some( function( e ) { return '/'+ field.elementType+ '/'+ e.uri === current } ) === false ) {
					const dangling = dc.createElement('option');
					dangling.value = current;
					dangling.textContent = current+ ' (missing)';
					dangling.selected = true;
					select.appendChild( dangling );
				}

				label.appendChild( select );

				if( options.length === 0 ) {
					const hint = dc.createElement('p');
					hint.className = 'editor-field-hint';
					hint.textContent = field.elementType
						? 'No element of type "'+ field.elementType+ '" exists yet.'
						: 'This field has no element type to reference.';
					label.appendChild( hint );
				}

				return label;
			}

			// An image field uploads immediately on file selection (not tied to the
			// form's save button) - a replaced/discarded upload can then never leave
			// an orphaned file, since the server only deletes the previous one once
			// the new one is safely committed. Needs an already-saved element (a uri
			// to attach the upload to), so it's unavailable on a new one.
			if( field.type === 'image' ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );

				if( field.width && field.height ) {
					const dimensions = dc.createElement('p');
					dimensions.className = 'editor-field-image-dimensions';
					dimensions.textContent = 'Target size '+ field.width+ ' × '+ field.height+ ' px';
					label.appendChild( dimensions );
				}

				const wrap = dc.createElement('div');
				wrap.className = 'editor-field-image';

				const preview = dc.createElement('img');
				preview.className = 'editor-field-image-preview';
				preview.hidden = ! value;
				// Every uploaded image lives under /images (Nino\Images::UPLOAD_DIR),
				// and the stored value is the filename relative to it - the same
				// url \Nino\Images::getUrl() builds server-side, which is what
				// _uploadImage() below renders straight from the response. Only
				// this re-render from a stored value had to build it itself, and
				// pointed at a /uploads directory that does not exist
				if( value )
					preview.src = Nino.admin.publicUrl( '/images/'+ value );
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

				if( Nino.admin.elements._isNew === true ) {
					msg.textContent = 'Save the element once before uploading an image.';
					wrap.appendChild( msg );
				} else {
					const fileInput = dc.createElement('input');
					fileInput.type = 'file';
					fileInput.accept = 'image/*';
					fileInput.addEventListener( 'change', function() {
						if( fileInput.files.length === 0 )
							return;
						Nino.admin.elements._uploadImage( key, fileInput.files[0], hiddenInput, preview, msg, fileInput );
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

			// Non-string fields keep a plain label - a character counter doesn't
			// mean much for those
			if( field.type !== 'string' ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );
				label.appendChild( Nino.admin.elements._wrapWithSuffix( input, field ) );
				return label;
			}

			// Every plain string field: label name + right-aligned live character
			// count in the same row, plus the matching maxlength
			const maxlength = field.maxlength ?? Nino.admin.elements.DEFAULT_MAXLENGTH;
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
			label.appendChild( Nino.admin.elements._wrapWithSuffix( input, field ) );

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
		 *	A model field's label. Unlike _editor - which looks up a translated,
		 *	admin-facing name - this tool deliberately shows the raw model key:
		 *	over here you are working on the schema itself, so the key is the
		 *	useful name, and a translation would only hide which field you are
		 *	actually editing
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{string}
		 */
		_fieldLabel : function( key ) {
			return key;
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
				return ( Nino.admin.elements._htmlEditors[key] !== undefined ) ? Nino.admin.elements._htmlEditors[key].getValue() : '';

			const form = dc.getElementById('elements-form');
			const selector = '[data-field="'+ CSS.escape( key )+ '"]';
			const input = form?.querySelector( selector+ ':checked' ) ?? form?.querySelector( selector );
			return ( input === null || input === undefined ) ? '' : Nino.admin.elements._readField( input );
		},

		/**
		 *	Whether a required field is currently empty - reads the raw dom value
		 *	rather than _readFieldByKey()'s parsed one: integer/double coerce an
		 *	empty input to 0 there, which would make "required" unable to ever
		 *	catch a blank number/date field. Boolean is never considered empty
		 *	(a radio choice always has a value).
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
				const editor = Nino.admin.elements._htmlEditors[key];
				return editor === undefined || editor.getValue().replace(/<[^>]+>/g, '').trim() === '';
			}

			if( field.type === 'array' ) {
				const value = Nino.admin.elements._readFieldByKey( key, field );
				return Array.isArray( value ) === false || value.length === 0;
			}

			const form = dc.getElementById('elements-form');
			const input = form?.querySelector( '[data-field="'+ CSS.escape( key )+ '"]' );
			return ( input === null || input === undefined ) || input.value.trim() === '';
		},

		/**
		 *	Labels of every currently-visible required field that's empty -
		 *	global fields always, locale fields only for the selected locale
		 *	(the only one actually being submitted).
		 *
		 *	An image field is never among them, even if its model says
		 *	required: its file is uploaded separately, only once the element
		 *	exists and has a uri to attach the upload to (see _renderField()'s
		 *	image branch), so on a new element it is empty by construction -
		 *	holding the save back for it would make the element impossible to
		 *	create. Neither tool writes that flag onto an image field anymore
		 *	(see Admin.php's cleanModel()); this keeps a model that still
		 *	carries one from an older version out of that dead end
		 *
		 *	@return		{Array<string>}
		 */
		_missingRequiredFields : function() {

			const keys = Nino.admin.elements._globalKeys.concat( Nino.admin.elements._localeKeys );

			return keys
				.filter( function( key ) { return Nino.admin.elements._currentModel[key].type !== 'image' } )
				.filter( function( key ) { return ( Nino.admin.elements._currentModel[key].required ?? false ) === true } )
				.filter( function( key ) { return Nino.admin.elements._isFieldEmpty( key, Nino.admin.elements._currentModel[key] ) } )
				.map( function( key ) { return Nino.admin.elements._fieldLabel( key ) } );
		},

		/**
		 *	Destroy any currently mounted html-editor instances (removes their
		 *	document-level selectionchange listener) - call before clearing/
		 *	replacing the DOM that contains them
		 *
		 *	@return		void
		 */
		_destroyHtmlEditors : function() {
			Object.keys( Nino.admin.elements._htmlEditors ).forEach( function( key ) { Nino.admin.elements._htmlEditors[key].destroy() } );
			Nino.admin.elements._htmlEditors = {};
		},

		/**
		 *	Read the current value of a rendered field, type-aware
		 *
		 *	@param		{Element}	input					Input/textarea rendered by _renderField()
		 *
		 *	@return		{*}											Parsed value
		 */
		_readField : function( input ) {

			// _readFieldByKey() already resolved this to the :checked radio - either
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
		 *	Store the currently visible locale fields into _localeValues before
		 *	switching locale
		 *
		 *	@return		void
		 */
		_storeVisibleLocaleFields : function() {

			const wrap = dc.getElementById('elements-form-locale-fields');
			if( wrap === null || Nino.admin.elements._selectedLocale === null )
				return;

			const previous = Nino.admin.elements._localeValues[Nino.admin.elements._selectedLocale] ?? {};
			const values = {};
			Nino.admin.elements._localeKeys.forEach( function( key ) { values[key] = Nino.admin.elements._readFieldByKey( key, Nino.admin.elements._currentModel[key] ) } );

			const changed = Nino.admin.elements._localeKeys.some( function( key ) {
				return Nino.admin.elements._fieldValuesEqual( Nino.admin.elements._currentModel[key], previous[key], values[key] ) === false;
			} );

			if( changed === true && Nino.admin.elements._dirtyLocales.indexOf( Nino.admin.elements._selectedLocale ) === -1 )
				Nino.admin.elements._dirtyLocales.push( Nino.admin.elements._selectedLocale );

			Nino.admin.elements._localeValues[Nino.admin.elements._selectedLocale] = values;
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
		 *	Translations one click on Save must persist: every locale edited
		 *	since the form was opened, not just the last visible one. Keeps edit
		 *	order (important when the first request creates a new element), and
		 *	falls back to the visible locale when no translation changed (eg. a
		 *	save that only changes global fields).
		 *
		 *	@return		{Array<string>}
		 */
		_saveLocales : function() {

			if( Nino.admin.elements._localeKeys.length === 0 )
				return [ Nino.admin.elements._selectedLocale ];

			const locales = Nino.admin.elements._dirtyLocales.slice();
			if( locales.length === 0 )
				locales.push( Nino.admin.elements._selectedLocale );

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

			// Only the locale-scoped html editors need destroying/rebuilding on a
			// switch - global ones stay mounted for the whole form's lifetime
			Nino.admin.elements._localeKeys.forEach( function( key ) {
				if( Nino.admin.elements._htmlEditors[key] !== undefined ) {
					Nino.admin.elements._htmlEditors[key].destroy();
					delete Nino.admin.elements._htmlEditors[key];
				}
			} );

			const wrap = dc.getElementById('elements-form-locale-fields');
			wrap.innerHTML = '';

			const values = Nino.admin.elements._localeValues[Nino.admin.elements._selectedLocale] ?? {};

			const heading = dc.getElementById('elements-form-heading');
			if( heading !== null )
				heading.textContent = Nino.admin.elements._headingText( values );

			Nino.admin.elements._localeKeys.forEach( function( key ) {
				wrap.appendChild( Nino.admin.elements._renderField( key, Nino.admin.elements._currentModel[key], values[key] ?? null ) );
			} );
		},

		/**
		 *	Build the edit form's heading: the title in whichever locale is
		 *	currently selected, with the uri (the element's actual, immutable
		 *	identifier) alongside in brackets - or just the uri if this type has
		 *	no title field, or it's empty for that locale yet
		 *
		 *	@param		{Object}	[localeValues]	This locale's field values ({ title, ... })
		 *
		 *	@return		{string}
		 */
		_headingText : function( localeValues ) {
			const title = ( localeValues ?? {} ).title;
			return ( title !== undefined && title !== null && title !== '' ) ? title+ ' ('+ Nino.admin.elements._currentUri+ ')' : Nino.admin.elements._currentUri;
		},

		/**
		 *	The element's storage exactly as it sits in elements/<type>.php, one
		 *	block per bucket - read-only. The resolved form above merges '*' and
		 *	the selected locale together, which is what an editor wants but hides
		 *	what a developer usually needs to know: whether a value is actually
		 *	stored for this locale or is only showing through from '*', and which
		 *	translations exist at all. Same reasoning as this tool's Text module
		 *	showing blacklisted keys that _editor hides.
		 *
		 *	@return		{Element|null}					<details> block, or null when there is nothing to show
		 */
		_renderRaw : function() {

			const buckets = Object.keys( Nino.admin.elements._raw );
			if( buckets.length === 0 )
				return null;

			const details = dc.createElement('details');
			details.id = 'elements-form-raw';

			const summary = dc.createElement('summary');
			summary.textContent = 'Raw storage ('+ buckets.join(', ')+ ')';
			details.appendChild( summary );

			const hint = dc.createElement('p');
			hint.className = 'admin-hint';
			hint.textContent = 'Read-only. "*" holds this element\'s global fields, shared by every locale; each locale bucket holds only that locale\'s own values. A locale missing here falls back to "*".';
			details.appendChild( hint );

			buckets.forEach( function( bucket ) {
				const heading = dc.createElement('div');
				heading.className = 'elements-raw-bucket';
				heading.textContent = bucket;
				details.appendChild( heading );

				const pre = dc.createElement('pre');
				pre.className = 'elements-raw-json';
				// textContent, never innerHTML - stored values are arbitrary
				// developer/editor content and must never be parsed as markup here
				pre.textContent = JSON.stringify( Nino.admin.elements._raw[bucket], null, 2 );
				details.appendChild( pre );
			} );

			return details;
		},

		/**
		 *	Render the full edit/create form for the current element
		 *
		 *	@return		void
		 */
		_renderForm : function() {

			Nino.admin.elements._destroyHtmlEditors();

			const wrap = dc.getElementById('elements-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			// No chevron in the text - .back-link::before already supplies one
			backLink.textContent = 'Back to '+ Nino.admin.elements._currentTypeTitle;
			backLink.addEventListener( 'click', function( ev ) {
				ev.preventDefault();
				if( Nino.admin.elements._saving === true )
					return;
				Nino.admin.elements._destroyHtmlEditors();
				Nino.admin.elements._showList();
			} );

			// An element type can declare a long list of locale keys - the
			// back link and the locale switch ride along in one pinned row
			// instead of scrolling out of reach (see script.js's formToolbar())
			const toolbar = Nino.admin.formToolbar( backLink );
			wrap.appendChild( toolbar );

			const form = dc.createElement('form');
			form.id = 'elements-edit-form';

			// Uri as the form's title - editable (it's how a new element gets its
			// identifier) when new, otherwise a plain heading; consistent with
			// Text, it can't be changed afterwards
			if( Nino.admin.elements._isNew === true ) {

				const uriLabel = dc.createElement('label');
				uriLabel.className = 'editor-field';
				const uriSpan = dc.createElement('span');
				uriSpan.textContent = 'Element URI';
				uriLabel.appendChild( uriSpan );
				const uriInput = dc.createElement('input');
				uriInput.type = 'text';
				uriInput.id = 'elements-form-uri';
				uriInput.required = true;
				uriInput.value = '';
				uriLabel.appendChild( uriInput );
				form.appendChild( uriLabel );

				// Only shown here - once an element exists its uri is fixed
				const uriHint = dc.createElement('p');
				uriHint.className = 'editor-field-hint';
				uriHint.textContent = 'Letters, digits, "-" and "_" only, starting with a letter or digit. Cannot be changed later.';
				form.appendChild( uriHint );

			} else {

				// Carries the id _renderLocaleFields() updates, so the heading
				// follows the locale select instead of going stale on a switch
				const title = dc.createElement('div');
				title.id = 'elements-form-heading';
				title.className = 'main-title--withuri';
				title.textContent = Nino.admin.elements._headingText( Nino.admin.elements._localeValues[Nino.admin.elements._selectedLocale] );
				wrap.appendChild( title );

				const uri = dc.createElement('div');
				uri.className = 'main-uri';
				uri.textContent = '/'+ Nino.admin.elements._currentType+ '/'+ Nino.admin.elements._currentUri;
				wrap.appendChild( uri );

				const uriInput = dc.createElement('input');
				uriInput.type = 'hidden';
				uriInput.id = 'elements-form-uri';
				uriInput.value = Nino.admin.elements._currentUri;
				form.appendChild( uriInput );
			}

			// Global fields - only rendered when this type actually has any
			if( Nino.admin.elements._globalKeys.length > 0 ) {

				const globalWrap = dc.createElement('fieldset');
				globalWrap.id = 'elements-form-global';
				const legend = dc.createElement('legend');
				legend.textContent = 'Global fields';
				globalWrap.appendChild( legend );

				Nino.admin.elements._globalKeys.forEach( function( key ) {
					globalWrap.appendChild( Nino.admin.elements._renderField( key, Nino.admin.elements._currentModel[key], Nino.admin.elements._globalValues[key] ?? null ) );
				} );

				form.appendChild( globalWrap );
			}

			// Locale fields
			if( Nino.admin.elements._localeKeys.length > 0 ) {

				const localeWrap = dc.createElement('fieldset');
				localeWrap.id = 'elements-form-locale';
				const legend = dc.createElement('legend');
				legend.textContent = 'Per-locale fields';
				localeWrap.appendChild( legend );

				const select = dc.createElement('select');
				select.id = 'elements-form-locale-select';
				Nino.admin.elements._locales.forEach( function( locale ) {
					const option = dc.createElement('option');
					option.value = locale;
					option.textContent = locale;
					option.selected = ( locale === Nino.admin.elements._selectedLocale );
					select.appendChild( option );
				} );
				select.addEventListener( 'change', function() {
					Nino.admin.elements._storeVisibleLocaleFields();
					Nino.admin.elements._selectedLocale = select.value;
					Nino.admin.elements._renderLocaleFields();
				} );

				// In the pinned toolbar, not in this fieldset's own corner:
				// which translation you are looking at has to stay switchable
				// from anywhere in a long form, not only from its top
				toolbar.appendChild( select );

				const fieldsWrap = dc.createElement('div');
				fieldsWrap.id = 'elements-form-locale-fields';
				localeWrap.appendChild( fieldsWrap );

				form.appendChild( localeWrap );
			}

			// Actions
			const actions = dc.createElement('div');
			actions.id = 'elements-form-actions';
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save';
			actions.appendChild( saveBtn );

			if( Nino.admin.elements._isNew === false ) {
				const delBtn = dc.createElement('button');
				delBtn.type = 'button';
				delBtn.classList.add('editor-danger');
				delBtn.textContent = 'Delete';
				delBtn.addEventListener( 'click', function() { Nino.admin.elements._delete() } );
				actions.appendChild( delBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'elements-form-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.elements._save() } );

			wrap.appendChild( form );

			if( Nino.admin.elements._localeKeys.length > 0 )
				Nino.admin.elements._renderLocaleFields();

			// Below the form, not inside it - it's a diagnostic view, and nothing
			// in it is submitted
			const raw = Nino.admin.elements._renderRaw();
			if( raw !== null )
				wrap.appendChild( raw );
		},

		/**
		 *	Collect the form's current values and save (insert or update) the element
		 *
		 *	@return		void
		 */
		_save : function() {

			if( Nino.admin.elements._saving === true )
				return;

			Nino.admin.elements._storeVisibleLocaleFields();

			const uri = dc.getElementById('elements-form-uri').value.trim();
			let msg = dc.getElementById('elements-form-msg');

			if( uri === '' ) {
				msg.textContent = 'An element uri is required.';
				return;
			}

			const missing = Nino.admin.elements._missingRequiredFields();
			if( missing.length > 0 ) {
				msg.textContent = 'Required fields are still empty: '+ missing.join(', ');
				return;
			}

			const globalFields = {};
			Nino.admin.elements._globalKeys.forEach( function( key ) { globalFields[key] = Nino.admin.elements._readFieldByKey( key, Nino.admin.elements._currentModel[key] ) } );

			const locales = Nino.admin.elements._saveLocales();
			const wasNew = Nino.admin.elements._isNew;
			let position = 0;
			let created = false;

			Nino.admin.elements._saving = true;
			Nino.admin.elements._setFormPending( true );
			msg.textContent = 'Saving …';

			function saveNextLocale() {

				const locale = locales[position];
				// Global fields only need one write. Re-sending them for every
				// translation repeats field callbacks and lets a later locale request
				// revert a transformation performed by the first.
				const fields = Object.assign( {}, position === 0 ? globalFields : {}, Nino.admin.elements._localeValues[locale] ?? {} );

				Nino.admin.elements._apiCall( 'save', {
					type 		: Nino.admin.elements._currentType,
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
							Nino.admin.elements._renderForm();
							msg = dc.getElementById('elements-form-msg');
						}

						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
						Nino.admin.elements._saving = false;
						Nino.admin.elements._setFormPending( false );
						return;
					}

					if( wasNew === true && position === 0 ) {
						Nino.admin.elements._isNew = false;
						Nino.admin.elements._currentUri = uri;
						created = true;
					}

					Nino.admin.elements._globalKeys.forEach( function( key ) { Nino.admin.elements._globalValues[key] = response.element[key] ?? null } );
					Nino.admin.elements._localeValues[locale] = Nino.admin.elements._localeValues[locale] ?? {};
					Nino.admin.elements._localeKeys.forEach( function( key ) { Nino.admin.elements._localeValues[locale][key] = response.element[key] ?? null } );

					const dirtyAt = Nino.admin.elements._dirtyLocales.indexOf( locale );
					if( dirtyAt !== -1 )
						Nino.admin.elements._dirtyLocales.splice( dirtyAt, 1 );

					position++;
					if( position < locales.length ) {
						saveNextLocale();
						return;
					}

					Nino.admin.elements._saving = false;
					Nino.admin.elements._setFormPending( false );

					// Re-read the element so the raw view reflects what actually
					// landed on disk, rather than the values this form just sent -
					// which is the whole point of showing it. Also re-renders the
					// form, locking a newly-created element's uri and exposing its
					// delete action.
					Nino.admin.elements._refreshAfterSave( uri );
				} );
			}

			saveNextLocale();
		},

		/**
		 *	Re-read the just-saved element (for the raw view) and refresh the
		 *	list behind it. Failure here is not a save failure - the write
		 *	already succeeded - so it only ever downgrades to leaving the form
		 *	as-is with a note, never reports the save itself as failed.
		 *
		 *	@param		{string}	uri						The uri that was just saved
		 *
		 *	@return		void
		 */
		_refreshAfterSave : function( uri ) {

			const type = Nino.admin.elements._currentType;

			Nino.admin.elements._apiCall( 'get', { type : type, uri : uri }, function( status, response ) {

				if( type !== Nino.admin.elements._currentType )
					return;

				if( status === 200 && response !== null ) {
					Nino.admin.elements._globalValues	= response.global;
					Nino.admin.elements._localeValues	= response.locales;
					Nino.admin.elements._raw 					= response.raw ?? {};
					Nino.admin.elements._dirtyLocales	= [];
					Nino.admin.elements._renderForm();
				}

				const msg = dc.getElementById('elements-form-msg');
				if( msg !== null )
					msg.textContent = status === 200 ? 'Saved.' : 'Saved, but reloading the element failed.';

				Nino.admin.elements._apiCall( 'list', { type : type }, function( listStatus, listResponse ) {
					if( listStatus === 200 && listResponse !== null && type === Nino.admin.elements._currentType )
						Nino.admin.elements._renderList( listResponse.elements );
				} );
			} );
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
			msg.textContent = 'Uploading …';

			Nino.admin.elements._apiCall( 'uploadimage', {
				type 		: Nino.admin.elements._currentType,
				uri 		: Nino.admin.elements._currentUri,
				locale 	: Nino.admin.elements._selectedLocale,
				key 		: key,
			}, function( status, response ) {

				fileInput.disabled = false;
				fileInput.value = '';

				if( status !== 200 || response === null ) {
					msg.className = 'editor-field-image-msg error';
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}

				hiddenInput.value = response.filename;
				preview.src = response.url;
				preview.hidden = false;
				msg.textContent = 'Saved.';
			}, { file : file } );
		},

		/**
		 *	Delete the currently open element, after confirmation
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( Nino.admin.elements._saving === true )
				return;

			if( wn.confirm( 'Delete this element in every locale? Its uploaded images are deleted too. This cannot be undone.' ) === false )
				return;

			const type 	= Nino.admin.elements._currentType;
			const model = Nino.admin.elements._currentModel;
			const title = Nino.admin.elements._currentTypeTitle;
			Nino.admin.elements._saving = true;
			Nino.admin.elements._setFormPending( true );

			Nino.admin.elements._apiCall( 'delete', { type : type, uri : Nino.admin.elements._currentUri }, function( status, response ) {
				if( status !== 200 ) {
					Nino.admin.elements._saving = false;
					Nino.admin.elements._setFormPending( false );
					const msg = dc.getElementById('elements-form-msg');
					if( msg !== null )
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to delete.' );
					return;
				}
				Nino.admin.elements._saving = false;
				Nino.admin.elements._selectType( type, model, title );
			} );
		},
	};

	// Same eager bootstrap every other _admin module uses - init() is guarded
	// against running on the login page (no #elements-types there) and against
	// running twice, so showCurrent()'s own lazy init stays a safe fallback
	Nino.events.bindCallback( 'ready', Nino.admin.elements.init );

})(window, document, document.documentElement, document.body);
