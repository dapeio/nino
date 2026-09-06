

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js   							Admin "Elements" panel: browse types, list/create/edit/delete
 *													the elements within a type. Element *types* themselves are
 *													developer-only (\Nino\Elements::insertElementType), not exposed here.
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
		// What this account may do per type, as apiTypes() reported it:
		// { <type> : { insert, delete, update : { <field> : bool } } }. Absent
		// means unrestricted - the server is what enforces any of this, this
		// only decides which controls are worth drawing
		_rights					: {},
		// Type name -> the uri its next element would get, for the types that
		// number their own elements (see Elements::AUTOINCREMENT_PAD). Filled
		// from the same type list every form is built from, so the element form
		// knows whether to ask for a uri at all.
		_numbered				: {},
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
		// Referenced type uri -> [ { uri, label } ], the choices an element
		// field's select offers. Refilled on every form open rather than
		// cached across them: adding an element to the referenced type has to
		// show up in the very next form that points at it
		_referenceOptions	: {},
		_pendingUri			: undefined,
		// The buckets the open element is stored in ('*' and one per locale),
		// as apiGet() sent them - read-only, for the raw drawer at the foot of
		// the form
		_raw						: {},
		_loading				: false,
		_saving					: false,
		// One sequence counter per level of the drill-down. A response that
		// arrives after its level was left - or after invalidate() dropped the
		// cache - renders a type that is no longer the open one, so every
		// callback checks the counter it started with
		_typesRequest		: 0,
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

			if( dc.getElementById('elements-types') === null || Nino.admin.elements._loading === true || Nino.admin.elements._ready === true )
				return;

			Nino.admin.elements._loading = true;
			const requestId = ++Nino.admin.elements._typesRequest;

			Nino.admin.elements._apiCall( 'types', {}, function( status, response ) {
				// invalidate() ran while this was in flight: the types and models
				// about to be cached here are the ones it just declared stale
				if( requestId !== Nino.admin.elements._typesRequest )
					return;
				Nino.admin.elements._loading = false;
				if( status !== 200 || response === null )
					return Nino.admin.elements._showError( dc.getElementById('elements-types'), status, response );
				Nino.admin.elements._locales = response.locales;
				Nino.admin.sessionLocale.init( response.selectedLocale );
				Nino.admin.elements._renderTypes( response.types );
				Nino.admin.elements._ready = true;

				// _restoreFromHash() itself drills all the way down to whatever the hash
				// wants (and sets the hash correctly once it gets there) - only fall back
				// to the plain type list if there was nothing to restore
				if( Nino.admin.elements._restoreFromHash( response.types ) === false )
					Nino.admin.elements._showTypes();
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
		 *	Drop everything this module cached and fall back to its type
		 *	picker, so the next showCurrent() fetches again.
		 *
		 *	The schema this module renders every form from - the type list,
		 *	each type's model, its global/locale key split, and now what this
		 *	account may do with each type - belongs to the Element Types tab
		 *	next door (see types.js) and is read exactly once per page load:
		 *	init() returns early forever after _ready, and showCurrent() only
		 *	re-shows a drill-down level from what is already in memory. Without
		 *	this, a field added, renamed or removed over there never appeared
		 *	here until the whole page was reloaded - and a type deleted over
		 *	there left this pane sitting on a list of elements that no longer
		 *	exist, with a form still editing them.
		 *
		 *	Public on purpose: it is this module's own contract with its
		 *	sibling ("your save invalidates my cache"), not a private flag for
		 *	the other side to reach in and flip.
		 *
		 *	@return		void
		 */
		invalidate : function() {

			// Any types/list/get response still in flight belongs to the state
			// being dropped here. The guards those callbacks check are reset
			// below, but bump the counters too, exactly as _selectType() does,
			// in case the same type is picked again before they arrive
			++Nino.admin.elements._typesRequest;
			++Nino.admin.elements._listRequest;
			++Nino.admin.elements._formRequest;

			Nino.admin.elements._destroyHtmlEditors();

			Nino.admin.elements._loading					= false;
			Nino.admin.elements._ready						= false;
			Nino.admin.elements._saving					= false;
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
			Nino.admin.elements._referenceOptions	= {};
			Nino.admin.elements._pendingUri				= undefined;

			if( dc.getElementById('elements-types') === null )
				return;

			// The rendered list and form belong to the model that just changed -
			// left standing, showCurrent() would flash them before init()'s
			// response replaces the picker behind them. The picker itself stays:
			// init() re-renders it from the answer, and clearing it here would
			// only show an empty pane in the meantime
			dc.getElementById('elements-list').innerHTML = '';
			dc.getElementById('elements-form').innerHTML = '';
			Nino.admin.elements._showTypes();
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

			const hash = Nino.admin.router.current();
			if( hash.panel !== 'elements' || hash.parts.length === 0 )
				return false;

			const type = types.find( function( t ) { return t.type === hash.parts[0] } );
			if( type === undefined )
				return false;

			Nino.admin.elements._pendingUri = hash.parts.length > 1 ? hash.parts[1] : undefined;
			Nino.admin.elements._selectType( type.type, type.model, type.title );
			return true;
		},

		/**
		 *	Call an elements/* admin action. Always posts to /_admin/ (dispatched
		 *	server-side via $_POST['action']), so this never depends on the
		 *	webserver routing anything beyond the already-required /_admin uri.
		 *	The trailing slash matters here: /_admin is a real directory on disk,
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
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
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
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/elements/error/load') );
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
			dc.getElementById('elements-types').classList.remove('admin-hidden');
			dc.getElementById('elements-list').classList.add('admin-hidden');
			dc.getElementById('elements-form').classList.add('admin-hidden');
			Nino.admin.router.set( 'elements', [] );
			Nino.admin.elements._refreshTypes();
		},

		/**
		 *	Re-read the type list behind the overview. What it shows per type is
		 *	content, not schema - the element count and the uris underneath it
		 *	(see Editor.php's typeDescr()) - and the list is rendered once, by
		 *	init(). Creating or deleting an element and going back to the
		 *	overview therefore left the count it had on page load: "(0)" next to
		 *	a type that visibly has an element in it.
		 *
		 *	Not a failure path: the list on screen stays whatever it was if this
		 *	does not come back.
		 *
		 *	@return		void
		 */
		_refreshTypes : function() {

			// init() renders the list itself and calls _showTypes() on the way
			// through - refetching there would just repeat the request it is
			// already inside of
			if( Nino.admin.elements._ready === false || Nino.admin.elements._loading === true )
				return;

			Nino.admin.elements._apiCall( 'types', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return;
				Nino.admin.elements._renderTypes( response.types );
			} );
		},

		_showList : function() {
			dc.getElementById('elements-types').classList.add('admin-hidden');
			dc.getElementById('elements-list').classList.remove('admin-hidden');
			dc.getElementById('elements-form').classList.add('admin-hidden');
			Nino.admin.router.set( 'elements', [ Nino.admin.elements._currentType ] );
		},

		_showFormView : function() {
			dc.getElementById('elements-types').classList.add('admin-hidden');
			dc.getElementById('elements-list').classList.add('admin-hidden');
			dc.getElementById('elements-form').classList.remove('admin-hidden');
			Nino.admin.router.set( 'elements', [ Nino.admin.elements._currentType, Nino.admin.elements._isNew ? 'new' : Nino.admin.elements._currentUri ] );
		},

		/**
		 *	Render the list of element types as buttons
		 *
		 *	@param		{Array}		types					[ { type, title, model }, ... ]
		 *
		 *	@return		void
		 */
		_renderTypes : function( types ) {

			// Every path into a form goes through this list, so it is the one
			// place that has to record which types number their own elements
			Nino.admin.elements._numbered = {};
			Nino.admin.elements._rights 	= {};
			types.forEach( function( entry ) {
				if( entry.autoincrement === true )
					Nino.admin.elements._numbered[entry.type] = entry.nextUri || '';
				if( entry.rights !== undefined )
					Nino.admin.elements._rights[entry.type] = entry.rights;
			} );


			const wrap = dc.getElementById('elements-types');
			wrap.innerHTML = '';

			if( types.length === 0 ) {
				const hint = dc.createElement('p');
				hint.className = 'nino-admin-empty';
				hint.textContent = Nino.content.getText('/_admin/elements/empty');
				wrap.appendChild( hint );
				return;
			}

			wrap.classList.add( 'nino-admin-list', 'nino-admin-list-buttons' );

			types.forEach( function( entry ) {

				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'admin-type-btn';
				btn.dataset.type = entry.type;
				btn.classList.toggle( 'active', entry.type === Nino.admin.elements._currentType );

				const titleWrap = dc.createElement('div');
				titleWrap.textContent = entry.title;

				const descr = dc.createElement('div');
				descr.className = 'admin-type-btn-descr';
				descr.textContent = entry.descr;
				titleWrap.appendChild( descr );

				const chev = dc.createElement('span');
				chev.className = 'admin-view-button-chev';
				chev.setAttribute( 'aria-hidden', 'true' );
				chev.textContent = '›';

				btn.appendChild( titleWrap );
				btn.appendChild( chev );
				btn.addEventListener( 'click', function() { Nino.admin.elements._selectType( entry.type, entry.model, entry.title ) } );

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
		/**
		 *	Whether the current type assigns its element uris itself (set up in
		 *	/_admin's Element Types - see Elements::AUTOINCREMENT_PAD)
		 *
		 *	@return		{boolean}
		 */
		/**
		 *	Whether this account may add, delete, or change one field of the
		 *	open type - see \Nino\Modules\Elements\Admin::rights(). A type
		 *	the server said nothing about is unrestricted: the checks that
		 *	matter run there, and a missing answer must not lock a working
		 *	panel down
		 *
		 *	@return		{boolean}
		 */
		_mayInsert : function() {
			return ( Nino.admin.elements._rights[Nino.admin.elements._currentType] ?? {} ).insert !== false;
		},

		_mayDelete : function() {
			return ( Nino.admin.elements._rights[Nino.admin.elements._currentType] ?? {} ).delete !== false;
		},

		/**
		 *	@param		{string}	key						Field key
		 *
		 *	@return		{boolean}
		 */
		_mayUpdate : function( key ) {
			return ( ( Nino.admin.elements._rights[Nino.admin.elements._currentType] ?? {} ).update ?? {} )[key] !== false;
		},

		_isNumbered : function() {
			return Object.prototype.hasOwnProperty.call( Nino.admin.elements._numbered, Nino.admin.elements._currentType );
		},

		/**
		 *	The uri the next element of the current type would get, as the backend
		 *	last reported it. The allocation itself happens on save, under the
		 *	type file's lock.
		 *
		 *	@return		{string}
		 */
		_nextUri : function() {
			return Nino.admin.elements._numbered[Nino.admin.elements._currentType] || '00001';
		},

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

			dc.querySelectorAll('.admin-type-btn').forEach( function( btn ) { btn.classList.toggle( 'active', btn.dataset.type === type ) } );

			Nino.admin.elements._apiCall( 'list', { type : type }, function( status, response ) {
				if( requestId !== Nino.admin.elements._listRequest || type !== Nino.admin.elements._currentType )
					return;
				// Shown as well as written: this runs while the type picker is the
				// visible level, so the pane the message lands in is still hidden
				// - exactly as _openForm()'s own error path handles it below
				if( status !== 200 || response === null ) {
					Nino.admin.elements._showError( dc.getElementById('elements-list'), status, response );
					Nino.admin.elements._showList();
					return;
				}
				Nino.admin.elements._renderList( response.elements );
				Nino.admin.elements._showList();

				const pendingUri = Nino.admin.elements._pendingUri;
				Nino.admin.elements._pendingUri = undefined;
				if( pendingUri !== undefined )
					Nino.admin.elements._openForm( pendingUri === 'new' ? null : pendingUri );
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
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/elements/label/backtypes');
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


			if( elements.length === 0 ) {
				const hint = dc.createElement('p');
				hint.className = 'nino-admin-empty';
				hint.textContent = Nino.content.getText('/_admin/elements/label/reference-empty');
				wrap.appendChild( hint );
			} else {
				const ul = dc.createElement('ul');
				ul.className = 'nino-admin-list';
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
			}

			// "New element" as an action below the list, not above it - and
			// only for an account that may actually add one
			if( Nino.admin.elements._mayInsert() === false )
				return;

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'nino-admin-btn-primary';
			addBtn.textContent = Nino.content.getText('/_admin/elements/label/add');
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
				Nino.admin.elements._raw					= {};
				Nino.admin.elements._dirtyLocales	= [];
				Nino.admin.elements._selectedLocale = Nino.admin.sessionLocale.current ?? Nino.admin.elements._locales[0] ?? '';
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

				// Prefer the remembered session locale, if this element actually has
				// data for it - otherwise fall back to whichever comes first
				const preferred = Nino.admin.sessionLocale.current;

				Nino.admin.elements._isNew 				= false;
				Nino.admin.elements._currentUri 	= uri;
				Nino.admin.elements._globalValues	= response.global;
				Nino.admin.elements._localeValues	= response.locales;
				Nino.admin.elements._raw					= response.raw || {};
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
		 *	would answer the same question a second way. Element management is
		 *	one permission, so a type reachable as a reference is one this
		 *	account may list anyway.
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

			const label = Nino.admin.elements._renderFieldControl( key, field, value );

			// A field this account may not write (see the panel's mayUpdate()):
			// rendered exactly as it otherwise would be, then locked. Showing
			// the value matters - it is the context the writable fields next to
			// it are edited in - but nothing here may be typed into, and the
			// save leaves it out
			if( Nino.admin.elements._mayUpdate( key ) === false ) {
				label.classList.add( 'admin-field-readonly' );
				label.querySelectorAll('input, textarea, select, button').forEach( function( el ) { el.disabled = true } );
				label.querySelectorAll('[contenteditable]').forEach( function( el ) {
					el.contentEditable = 'false';
					el.setAttribute( 'aria-disabled', 'true' );
				} );
			}

			return label;
		},

		/**
		 *	Build one field's control - the whole of what used to be
		 *	_renderField(), which now wraps this to lock a field the account
		 *	may not write
		 *
		 *	@param		{string}	key						Field key
		 *	@param		{Object}	field					Its model definition
		 *	@param		{*}				value					Its current value
		 *
		 *	@return		{Element}
		 */
		_renderFieldControl : function( key, field, value ) {

			const displayName = Nino.admin.elements._fieldLabel( key );
			const isHtml = field.type === 'string' && field.html === true;
			// A contenteditable inside <label> is invalid interactive markup and
			// can make drag-selection fail in Safari. Rich text gets a semantic
			// group; ordinary controls retain their native label wrapper.
			const label = dc.createElement( isHtml ? 'div' : 'label' );
			label.className = 'nino-admin-field';
			if( isHtml ) {
				label.setAttribute( 'role', 'group' );
				label.setAttribute( 'aria-label', displayName );
			}

			// A string field with html: true gets the same minimal rich-text editor as
			// Text's html-flagged keys, sanitized the same way server-side on save
			if( isHtml ) {
				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );
				const mount = dc.createElement('div');
				label.appendChild( mount );
				Nino.admin.elements._htmlEditors[key] = Nino.admin.htmlEditor.create( mount, value ?? '', field.maxlength ?? Nino.admin.elements.DEFAULT_MAXLENGTH );
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
				group.className = 'nino-admin-field-radios';

				[ true, false ].forEach( function( boolValue ) {
					const optLabel = dc.createElement('label');
					optLabel.className = 'nino-admin-field-radio';
					const radio = dc.createElement('input');
					radio.type = 'radio';
					radio.name = 'elements-radio-'+ key;
					radio.value = boolValue ? 'true' : 'false';
					radio.checked = ( value === true ) === boolValue;
					radio.dataset.field = key;
					radio.dataset.type = field.type;
					optLabel.appendChild( radio );
					optLabel.appendChild( dc.createTextNode( boolValue ? Nino.content.getText('/_admin/elements/label/yes') : Nino.content.getText('/_admin/elements/label/no') ) );
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

				const referenceOptions = ( Nino.admin.elements._referenceOptions[field.elementType] ?? [] ).map( function( element ) {
					return { value : '/'+ field.elementType+ '/'+ element.uri, label : element.label };
				} );

				// A model that allows several references gets the ordered list
				// control instead of the select. It builds its own field wrapper
				// (and must: the up/down/remove buttons and the search box are
				// several controls, which a <label> may not wrap), so the label
				// created above is not used on this path
				if( Nino.adminUi.isMultiElement( field ) === true ) {

					const list = Nino.adminUi.elementList( {
						key 		: key,
						label 	: displayName,
						value 	: Array.isArray( value ) ? value : [],
						limit 	: field.multiple,
						options : referenceOptions,
						text 		: {
							search 		: Nino.content.getText('/_admin/elements/label/reference-search'),
							empty 		: Nino.content.getText('/_admin/elements/label/reference-list-empty'),
							noMatches : Nino.content.getText('/_admin/elements/label/reference-no-matches'),
							more 			: Nino.content.getText('/_admin/elements/label/reference-more'),
							missing 	: Nino.content.getText('/_admin/elements/label/reference-missing'),
							up 				: Nino.content.getText('/_admin/elements/label/reference-up'),
							down 			: Nino.content.getText('/_admin/elements/label/reference-down'),
							remove 		: Nino.content.getText('/_admin/elements/label/reference-remove'),
							add 			: Nino.content.getText('/_admin/elements/label/reference-add'),
							full 			: Nino.content.getText('/_admin/elements/label/reference-full'),
						},
					} );

					if( referenceOptions.length === 0 ) {
						const hint = dc.createElement('p');
						hint.className = 'nino-admin-field-hint';
						hint.textContent = Nino.content.getText('/_admin/elements/label/reference-empty');
						list.appendChild( hint );
					}

					return list;
				}

				const span = dc.createElement('span');
				span.textContent = displayName;
				label.appendChild( span );

				const select = dc.createElement('select');
				select.dataset.field = key;
				select.dataset.type = field.type;

				const options = referenceOptions;
				const current = ( value === null || value === undefined ) ? '' : String( value );

				// Always offered, even on a required field: the browser's own
				// "select an option" is not what holds the save back - the same
				// _missingRequiredFields() check every other type goes through
				// is, and it needs an empty state to be able to catch
				const empty = dc.createElement('option');
				empty.value = '';
				empty.textContent = ( field.required === true )
					? Nino.content.getText('/_admin/elements/label/reference-choose')
					: Nino.content.getText('/_admin/elements/label/reference-none');
				empty.selected = ( current === '' );
				select.appendChild( empty );

				options.forEach( function( element ) {
					const option = dc.createElement('option');
					option.value = element.value;
					option.textContent = element.label;
					option.selected = ( option.value === current );
					select.appendChild( option );
				} );

				// A reference whose target was deleted since keeps its value
				// instead of silently resetting to none - a save the editor did
				// not intend would otherwise drop the reference for good
				if( current !== '' && options.some( function( e ) { return e.value === current } ) === false ) {
					const dangling = dc.createElement('option');
					dangling.value = current;
					dangling.textContent = current+ ' ('+ Nino.content.getText('/_admin/elements/label/reference-missing')+ ')';
					dangling.selected = true;
					select.appendChild( dangling );
				}

				label.appendChild( select );

				if( options.length === 0 ) {
					const hint = dc.createElement('p');
					hint.className = 'nino-admin-field-hint';
					hint.textContent = Nino.content.getText('/_admin/elements/label/reference-empty');
					label.appendChild( hint );
				}

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
					dimensions.className = 'nino-admin-field-image-dimensions';
					dimensions.textContent = Nino.content.getText('/_admin/common/label/image-target')+ ' '+ field.width+ ' × '+ field.height+ ' px';
					label.appendChild( dimensions );
				}

				const wrap = dc.createElement('div');
				wrap.className = 'nino-admin-field-image';

				const preview = dc.createElement('img');
				preview.className = 'nino-admin-field-image-preview';
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
				msg.className = 'nino-admin-field-image-msg';
				msg.setAttribute( 'aria-live', 'polite' );

				if( Nino.admin.elements._isNew === true ) {
					msg.textContent = Nino.content.getText('/_admin/elements/label/image-savefirst');
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

			// Non-string fields (boolean/integer/double/date/array) keep a plain label -
			// a character counter doesn't mean much for those
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
			header.className = 'nino-admin-field-header';

			const nameSpan = dc.createElement('span');
			nameSpan.className = 'nino-admin-field-name';
			nameSpan.textContent = displayName;
			header.appendChild( nameSpan );

			const counter = dc.createElement('span');
			counter.className = 'nino-admin-char-counter';

			function updateCounter() {
				const len = input.value.length;
				counter.textContent = len + ' / ' + maxlength;
				counter.classList.toggle( 'is-limit', len >= maxlength );
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
			row.className = 'nino-admin-field-suffixed';
			row.appendChild( input );

			const suffix = dc.createElement('span');
			suffix.className = 'nino-admin-field-suffix';
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
			const translated = Nino.content.getText('/_admin/elements/field/'+ Nino.admin.elements._currentType+ '/'+ key);
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
				return ( Nino.admin.elements._htmlEditors[key] !== undefined ) ? Nino.admin.elements._htmlEditors[key].getValue() : '';

			const form = dc.getElementById('elements-form');
			const selector = '[data-field="'+ CSS.escape( key )+ '"]';
			const input = form?.querySelector( selector+ ':checked' ) ?? form?.querySelector( selector );
			return ( input === null || input === undefined ) ? '' : Nino.admin.elements._readField( input );
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
				const editor = Nino.admin.elements._htmlEditors[key];
				return editor === undefined || editor.getValue().replace(/<[^>]+>/g, '').trim() === '';
			}

			if( field.type === 'array' || Nino.adminUi.isMultiElement( field ) === true ) {
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
		 *	create. Same rule /_admin's own copy of this module applies, and
		 *	the one its Element Types editor now enforces when writing a model
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
		 *	Destroy any currently mounted nino-admin-richtext instances (removes their
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

			// The multi-reference control stores its ordered list as json in a
			// hidden input, and marks it with data-multiple - a single reference
			// is a plain select carrying one uri, so the type alone cannot tell
			// the two apart
			if( input.dataset.type === 'element' && input.dataset.multiple !== undefined )
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
				if( field.type === 'array' || Nino.adminUi.isMultiElement( field ) === true )
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
			wrap.classList.toggle( 'admin-pending', pending );
			// A read-only field stays read-only through a save: re-enabling
			// everything afterwards would hand back exactly the fields this
			// account may not write
			wrap.querySelectorAll('input, textarea, select, button').forEach( function( el ) {
				el.disabled = pending || el.closest('.admin-field-readonly') !== null;
			} );
			wrap.querySelectorAll('[contenteditable]').forEach( function( el ) {
				const locked = pending || el.closest('.admin-field-readonly') !== null;
				el.contentEditable = locked ? 'false' : 'true';
				el.setAttribute( 'aria-disabled', locked ? 'true' : 'false' );
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
		 *	identifier) alongside in brackets - or just the uri if this type
		 *	has no title field, or it's empty for that locale yet
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
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/elements/label/back')+ ' '+ Nino.admin.elements._currentTypeTitle;
			backLink.addEventListener( 'click', function( ev ) {
				ev.preventDefault();
				if( Nino.admin.elements._saving === true )
					return;
				Nino.admin.elements._destroyHtmlEditors();
				Nino.admin.elements._showList();
			} );
			const toolbar = Nino.admin.formToolbar( backLink );
			wrap.appendChild( toolbar );

			const form = dc.createElement('form');
			form.id = 'elements-edit-form';

			// Uri as the form's title - editable (it's how a new element gets its
			// identifier) when new, otherwise a plain heading, just like Text's
			// category name; consistent with Text, it can't be changed afterwards
			if( Nino.admin.elements._isNew === true && Nino.admin.elements._isNumbered() === true ) {

				// A numbered type has nothing to ask for. The input stays in the
				// markup, empty and hidden, because an empty uri is exactly what
				// tells the backend to allocate the next number - and _save()
				// keeps reading the field either way.
				const uriInput = dc.createElement('input');
				uriInput.type = 'hidden';
				uriInput.id = 'elements-form-uri';
				uriInput.value = '';
				form.appendChild( uriInput );

				// Shown rather than described: the number is what the element will
				// be reachable at, and it is assigned on save
				// .nino-admin-hint, not -field-hint: this explains the screen, it
				// does not belong to a field above it (there is none, and that
				// class carries a negative top margin to sit under one)
				const numbered = dc.createElement('p');
				numbered.className = 'nino-admin-hint';
				numbered.textContent = Nino.content.getText('/_admin/elements/label/uri-numbered')+ ' /'+ Nino.admin.elements._currentType+ '/'+ Nino.admin.elements._nextUri()+ '.';
				form.appendChild( numbered );

			} else if( Nino.admin.elements._isNew === true ) {

				const uriLabel = dc.createElement('label');
				uriLabel.className = 'nino-admin-field';
				const uriSpan = dc.createElement('span');
				uriSpan.textContent = Nino.content.getText('/_admin/elements/label/uri');
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
				uriHint.className = 'nino-admin-field-hint';
				uriHint.textContent = Nino.content.getText('/_admin/elements/label/uri-hint');
				form.appendChild( uriHint );

			} else {

				// Title (in the currently selected locale) reads better than the raw
				// uri, which stays alongside in brackets since it's still the element's
				// actual identifier (eg. used in links) - kept in sync with the locale
				// select by _renderLocaleFields()

				const title = dc.createElement('div');
				title.className = 'main-title--withuri';
				title.textContent = Nino.admin.elements._headingText( Nino.admin.elements._localeValues[Nino.admin.elements._selectedLocale] );
				wrap.appendChild( title );

				const uri = dc.createElement('div');
				uri.className = 'main-uri';
				uri.textContent = '/'+ Nino.admin.elements._currentType + '/' + Nino.admin.elements._currentUri;
				wrap.appendChild( uri );

				const uriInput = dc.createElement('input');
				uriInput.type = 'hidden';
				uriInput.id = 'elements-form-uri';
				uriInput.value = Nino.admin.elements._currentUri;
				form.appendChild( uriInput );
			}

			// Global fields - only rendered when this type actually has any
			// (a type with only locale fields, eg. services, has none)
			if( Nino.admin.elements._globalKeys.length > 0 ) {

				const globalWrap = dc.createElement('fieldset');
				globalWrap.id = 'elements-form-global';
				const legend = dc.createElement('legend');
				legend.textContent = Nino.content.getText('/_admin/common/label/global');
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
				legend.textContent = Nino.content.getText('/_admin/common/label/locale');
				localeWrap.appendChild( legend );

				const select = dc.createElement('select');
				select.id = 'elements-form-locale-select';
				select.className = 'nino-admin-locale-select nino-admin-contextbar-select';
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
					Nino.admin.sessionLocale.set( select.value );
					Nino.admin.elements._renderLocaleFields();
				} );
				toolbar.appendChild( select );

				const fieldsWrap = dc.createElement('div');
				fieldsWrap.id = 'elements-form-locale-fields';
				fieldsWrap.className = 'nino-admin-fieldgrid';
				localeWrap.appendChild( fieldsWrap );

				form.appendChild( localeWrap );
			}

			// What is actually on disk, folded away - a manager's view
			const raw = Nino.admin.elements._renderRaw();
			if( raw !== null )
				form.appendChild( raw );

			// Actions
			const actions = dc.createElement('div');
			actions.id = 'elements-form-actions';
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/elements/label/save');
			actions.appendChild( saveBtn );

			// Duplicating ends in a new element, so it is offered exactly where
			// adding one is - and only on a saved element, which is the only
			// thing there is to copy
			if( Nino.admin.elements._isNew === false && Nino.admin.elements._mayInsert() === true ) {
				const dupBtn = dc.createElement('button');
				dupBtn.type = 'button';
				dupBtn.className = 'nino-admin-btn-secondary';
				dupBtn.textContent = Nino.content.getText('/_admin/elements/label/duplicate');
				dupBtn.addEventListener( 'click', function() { Nino.admin.elements._duplicate() } );
				actions.appendChild( dupBtn );
			}

			if( Nino.admin.elements._isNew === false && Nino.admin.elements._mayDelete() === true ) {
				const delBtn = dc.createElement('button');
				delBtn.type = 'button';
				delBtn.classList.add('nino-admin-btn-danger');
				delBtn.textContent = Nino.content.getText('/_admin/elements/label/delete');
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
		},

		/**
		 *	The element's storage buckets as they are on disk, read-only and
		 *	folded away: '*' holds the global fields every locale shares,
		 *	each locale bucket only that locale's own values, and a locale
		 *	missing here falls back to '*'. Nothing to show for a new element
		 *
		 *	@return		{Element|null}
		 */
		_renderRaw : function() {
			const buckets = Object.keys( Nino.admin.elements._raw || {} );
			if( buckets.length === 0 )
				return null;
			const details = dc.createElement('details');
			details.id = 'elements-form-raw';
			const summary = dc.createElement('summary');
			summary.textContent = Nino.content.getText('/_admin/elements/label/raw')+ ' ('+ buckets.join(', ')+ ')';
			details.appendChild( summary );
			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.content.getText('/_admin/elements/hint/raw');
			details.appendChild( hint );
			buckets.forEach( function( bucket ) {
				const heading = dc.createElement('div');
				heading.className = 'elements-raw-bucket';
				heading.textContent = bucket;
				details.appendChild( heading );
				const pre = dc.createElement('pre');
				pre.className = 'elements-raw-json';
				// textContent, never innerHTML - stored values are arbitrary
				// content and must never be parsed as markup here
				pre.textContent = JSON.stringify( Nino.admin.elements._raw[bucket], null, 2 );
				details.appendChild( pre );
			} );
			return details;
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

			// A numbered type is saved with no uri on purpose - that is what asks
			// the backend for the next number. Reassigned below, once the insert
			// says which one it got, so the remaining locales of this same save
			// address the element that now exists.
			let uri = dc.getElementById('elements-form-uri').value.trim();
			const numberedInsert = Nino.admin.elements._isNew === true && Nino.admin.elements._isNumbered() === true;
			let msg = dc.getElementById('elements-form-msg');

			if( uri === '' && numberedInsert === false ) {
				msg.textContent = Nino.content.getText('/_admin/elements/error/uri');
				return;
			}

			const missing = Nino.admin.elements._missingRequiredFields();
			if( missing.length > 0 ) {
				msg.textContent = Nino.content.getText('/_admin/elements/error/required')+ ' '+ missing.join(', ');
				return;
			}

			// Only what this account may write: a read-only field has no edit to
			// carry, and sending it back unchanged would be refused by the same
			// permission that made it read-only in the first place
			const globalFields = {};
			Nino.admin.elements._globalKeys
				.filter( function( key ) { return Nino.admin.elements._mayUpdate( key ) } )
				.forEach( function( key ) { globalFields[key] = Nino.admin.elements._readFieldByKey( key, Nino.admin.elements._currentModel[key] ) } );

			const locales = Nino.admin.elements._saveLocales();
			const wasNew = Nino.admin.elements._isNew;
			let position = 0;
			let created = false;

			Nino.admin.elements._saving = true;
			Nino.admin.elements._setFormPending( true );
			msg.textContent = Nino.content.getText('/_admin/elements/msg/pending');

			function saveNextLocale() {

				const locale = locales[position];
				// Global fields only need one write. Re-sending them for every
				// translation repeated field callbacks and made a later locale request
				// capable of reverting a transformation performed by the first.
				const localeFields = {};
				Object.keys( Nino.admin.elements._localeValues[locale] ?? {} )
					.filter( function( key ) { return Nino.admin.elements._mayUpdate( key ) } )
					.forEach( function( key ) { localeFields[key] = Nino.admin.elements._localeValues[locale][key] } );

				const fields = Object.assign( {}, position === 0 ? globalFields : {}, localeFields );

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
							Nino.admin.router.set( 'elements', [ Nino.admin.elements._currentType, Nino.admin.elements._currentUri ] );
							msg = dc.getElementById('elements-form-msg');
						}

						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/elements/error/save') );
						Nino.admin.elements._saving = false;
						Nino.admin.elements._setFormPending( false );
						return;
					}

					if( wasNew === true && position === 0 ) {
						Nino.admin.elements._isNew = false;
						// A numbered type only knows its uri now. Every element the
						// kernel returns carries the '.uri' it was actually written
						// under, so take it from there rather than assuming.
						const assigned = String( response.element['.uri'] ?? '' ).split('/').pop();
						if( assigned !== '' )
							uri = assigned;
						Nino.admin.elements._currentUri = uri;
						created = true;

						// This insert consumed a number, so the next form's promise
						// has to move with it (the backend reports the new one -
						// the padding is the kernel's to decide, not this file's)
						if( response.nextUri )
							Nino.admin.elements._numbered[Nino.admin.elements._currentType] = response.nextUri;
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

					// Stay on the form after saving. A newly-created element is rendered
					// once more to lock its uri and expose the delete action; existing
					// elements keep focus and selection exactly where they were.
					if( wasNew === true ) {
						Nino.admin.elements._renderForm();
						Nino.admin.router.set( 'elements', [ Nino.admin.elements._currentType, Nino.admin.elements._currentUri ] );
					}

					msg = dc.getElementById('elements-form-msg');
					msg.textContent = Nino.content.getText('/_admin/elements/msg/saved');
					Nino.admin.elements._saving = false;
					Nino.admin.elements._setFormPending( false );

					const savedType = Nino.admin.elements._currentType;
					Nino.admin.elements._apiCall( 'list', { type : savedType }, function( listStatus, listResponse ) {
						if( listStatus === 200 && listResponse !== null && savedType === Nino.admin.elements._currentType )
							Nino.admin.elements._renderList( listResponse.elements );
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
			msg.className = 'nino-admin-field-image-msg';
			msg.textContent = Nino.content.getText('/_admin/elements/msg/pending');

			Nino.admin.elements._apiCall( 'uploadimage', {
				type 		: Nino.admin.elements._currentType,
				uri 		: Nino.admin.elements._currentUri,
				locale 	: Nino.admin.elements._selectedLocale,
				key 		: key,
			}, function( status, response ) {

				fileInput.disabled = false;
				fileInput.value = '';

				if( status !== 200 || response === null ) {
					msg.className = 'nino-admin-field-image-msg is-error';
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/elements/error/save') );
					return;
				}

				hiddenInput.value = response.filename;
				preview.src = response.url;
				preview.hidden = false;
				msg.textContent = Nino.content.getText('/_admin/elements/msg/saved');
			}, { file : file } );
		},

		/**
		 *	Turn the element on screen into the starting values of a new one:
		 *	every field keeps what it holds, the uri does not.
		 *
		 *	Nothing is written and nothing is copied on the server - this only
		 *	puts the form into its "new element" state with the values already
		 *	in it, so the operator names the copy and saves it like any other
		 *	insert. Cancelling is leaving the form.
		 *
		 *	Two things deliberately do not come along:
		 *
		 *	Image fields. An image value is a filename, and deleting an element
		 *	deletes the files its image fields name (see the panel's
		 *	imageFilenames()). A copy carrying the filename would share
		 *	one file with its original, and deleting either would take the
		 *	picture off the other. A new element cannot upload one yet anyway -
		 *	the field says so and waits for the first save.
		 *
		 *	The raw drawer. What is in there sits in buckets this type's model
		 *	does not describe; it is shown, never written, and inventing a
		 *	second element that claims it would be a guess.
		 *
		 *	Every translation the original has is marked dirty, so the insert
		 *	writes them all rather than only the one on screen (see
		 *	_saveLocales()).
		 *
		 *	@return		void
		 */
		_duplicate : function() {

			if( Nino.admin.elements._saving === true || Nino.admin.elements._isNew === true )
				return;

			const model = Nino.admin.elements._currentModel || {};
			const imageKeys = Object.keys( model ).filter( function( key ) { return ( model[key] || {} ).type === 'image' } );

			const strip = function( values ) {
				imageKeys.forEach( function( key ) { if( values[key] !== undefined ) values[key] = '' } );
			};

			strip( Nino.admin.elements._globalValues );
			Object.keys( Nino.admin.elements._localeValues ).forEach( function( locale ) {
				strip( Nino.admin.elements._localeValues[locale] );
			} );

			Nino.admin.elements._isNew 					= true;
			Nino.admin.elements._currentUri 		= null;
			Nino.admin.elements._raw 						= {};
			Nino.admin.elements._dirtyLocales 	= Object.keys( Nino.admin.elements._localeValues );

			Nino.admin.elements._renderForm();
			Nino.admin.router.set( 'elements', [ Nino.admin.elements._currentType, 'new' ] );

			const msg = dc.getElementById('elements-form-msg');
			if( msg !== null )
				msg.textContent = Nino.content.getText('/_admin/elements/msg/duplicated');

			const uriInput = dc.getElementById('elements-form-uri');
			if( uriInput !== null && uriInput.type !== 'hidden' )
				uriInput.focus();
		},

		/**
		 *	Delete the currently open element, after confirmation
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( Nino.admin.elements._saving === true )
				return;

			if( wn.confirm( Nino.content.getText('/_admin/elements/confirm/delete') ) === false )
				return;

			const type = Nino.admin.elements._currentType;
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
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/elements/error/save') );
					return;
				}
				Nino.admin.elements._saving = false;
				Nino.admin.elements._selectType( type, model, title );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
