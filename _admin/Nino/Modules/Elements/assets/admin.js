

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js   							Admin "Elements" panel: browse types, list/create/edit/delete
 *													the elements within a type. An element *type* itself - its
 *													title and its model - is the Types tab of this same pane
 *													(see assets/types.js), behind a permission of its own.
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
		// The types as the picker last drew them, [ { type, title, model, ... } ]
		// - what a hash that names a type is opened from (see _follow())
		_types					: [],
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
		// What was typed into a translated list or object field that is not
		// valid JSON, '<locale>|<key>' -> the text. The stored value stays what
		// it was, so the translation is not lost when the person switches away
		// from it; the text is what the field shows when it comes back and what
		// the save is held back for (see _storeVisibleLocaleFields())
		_invalidArrays	: {},
		// A save was refused: from now on the marks follow what is typed and
		// every freshly drawn translation carries them (see _showProblems())
		_validated			: false,
		// What the form's controls held when they were drawn or last saved, as
		// compared strings: the uri, the global fields, the translation on
		// screen. isDirty() asks whether they still do
		_baseline				: { uri : null, global : {}, locale : {} },
		// Whether the translation on screen already counted as edited when it
		// was drawn. If not, it is no more than a translation a refused save
		// stored, and typing back what it was drawn with leaves nothing
		// unsaved (see isDirty())
		_localeDirtyAtDraw	: false,
		// The values as the server last sent or accepted them, { global,
		// locales } - what discard() goes back to, so a copy made after it
		// is a copy of the saved element and not of the edits that were thrown away
		_pristine				: { global : {}, locales : {} },
		// The form is a copy nobody has saved (see _duplicate())
		_copy						: false,
		// The wrapper of every field on screen, by key - where its mark, its
		// error and its focus go
		_fieldNodes			: {},
		// The form's status line - saving, saved at, unsaved, why it failed (see
		// Nino.adminUi.status()). Drawn again with the form, so always read from
		// here rather than kept in a local
		_status					: null,
		// The open type's elements as the list last showed them, [ { uri,
		// label } ] in the list's order - what the form's previous/next step
		// through. Refilled with every list the server sends (see
		// _renderList()), dropped with the type
		_elements				: [],
		// The translation the list's cells show - the workbench's content
		// locale at the time the list was read, so a form that switched it
		// can tell the list needs reading again (see _renderForm()'s back link)
		_listLocale			: '',
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
				if( status !== 200 || response === null ) {
					Nino.admin.elements._loading = false;
					return Nino.admin.elements._showError( dc.getElementById('elements-types'), status, response );
				}
				Nino.admin.elements._locales = response.locales;
				Nino.admin.sessionLocale.init( response.selectedLocale );
				Nino.admin.elements._renderTypes( response.types );
				Nino.admin.elements._ready = true;

				// _restoreFromHash() itself drills all the way down to whatever the hash
				// wants (and sets the hash correctly once it gets there) - only fall back
				// to the plain type list if there was nothing to restore
				if( Nino.admin.elements._restoreFromHash( response.types ) === false )
					Nino.admin.elements._showTypes();

				// Cleared last, after the show above and not before it.
				// _refreshTypes() reads this flag as "a types request is already
				// in flight" and stands down; cleared first, both halves of that
				// guard were false by the time _showTypes() reached it, and the
				// list this callback had just rendered was fetched a second time
				Nino.admin.elements._loading = false;
			} );
		},

		/**
		 *	Re-apply whatever drill-down level this panel is currently on -
		 *	called when the user switches TO this tab, so the hash (only ever
		 *	written by router.set() while this panel is the visible one) gets
		 *	synced to reality instead of staying stale from before the switch.
		 *
		 *	Where the hash names this panel, the hash wins: a step through the
		 *	browser's history changes the address and nothing else, so the level
		 *	it names is shown - and leaving a form that holds unsaved input asks
		 *	first, as its back link does. A hash that names another panel (a
		 *	click on the rail, a Dashboard tile aside) leaves the level in memory
		 *	as it is
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.elements._ready === false ) {
				Nino.admin.elements.init();
				return;
			}

			const hash = Nino.admin.router.current();
			if( hash.panel === 'elements' && Nino.admin.elements._follow( hash.parts ) === true )
				return;

			Nino.admin.elements._showLevel();
		},

		/**
		 *	Show the level this panel is on, and write it into the address
		 *
		 *	@return		void
		 */
		_showLevel : function() {

			if( Nino.admin.elements._currentType === null )
				return Nino.admin.elements._showTypes();

			if( dc.getElementById('elements-form').classList.contains('admin-hidden') === false )
				return Nino.admin.elements._showFormView();

			if( dc.getElementById('elements-list').classList.contains('admin-hidden') === false )
				return Nino.admin.elements._showList();

			Nino.admin.elements._showTypes();
		},

		/**
		 *	Move to the level the hash names, if it is not the one on screen: the
		 *	type picker, a type's list or one element's form ('new' for a blank
		 *	one). A type the picker does not know is the picker. A level of
		 *	another type, or one that needs the list first, is loaded the way a
		 *	reload restores it (_selectType() with the element waiting in
		 *	_pendingUri). Leaving a form with unsaved input asks first (see
		 *	Nino.admin.router.leave()); while a save runs the screen stays as it is
		 *
		 *	@param		{Array}		parts					The hash behind the panel's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			if( Nino.admin.elements._saving === true )
				return false;

			const current = Nino.admin.elements._currentType;
			const form = current !== null && dc.getElementById('elements-form').classList.contains('admin-hidden') === false;
			const list = current !== null && dc.getElementById('elements-list').classList.contains('admin-hidden') === false;

			const type = parts.length === 0 ? undefined : Nino.admin.elements._types.find( function( t ) { return t.type === parts[0] } );
			const uri = type === undefined || parts.length < 2 ? undefined : parts[1];

			if( type === undefined ? ( form === false && list === false ) : ( uri === undefined
				? ( list === true && current === type.type )
				: ( form === true && current === type.type && ( uri === 'new' ? Nino.admin.elements._isNew === true : ( Nino.admin.elements._isNew === false && Nino.admin.elements._currentUri === uri ) ) ) ) )
				return false;

			Nino.admin.router.leave( [ 'elements' ], form, function() {

				if( type === undefined ) {
					Nino.admin.elements._destroyHtmlEditors();
					Nino.admin.elements._showTypes();
					return;
				}

				if( current === type.type && uri !== undefined ) {
					Nino.admin.elements._openForm( uri === 'new' ? null : uri );
					return;
				}

				if( current === type.type && form === true ) {
					Nino.admin.elements._destroyHtmlEditors();
					Nino.admin.elements._showList();
					// The form's locale switch moves the workbench's content locale
					if( Nino.admin.elements._listLocale !== ( Nino.admin.sessionLocale.current ?? '' ) )
						Nino.admin.elements._refreshList();
					return;
				}

				Nino.admin.elements._pendingUri = uri;
				Nino.admin.elements._selectType( type.type, type.model, type.title );
			}, Nino.admin.elements._showLevel );

			return true;
		},

		/**
		 *	Open an element - or a blank one - the way a person does, from the
		 *	list or the form's previous and next: the step is one Back returns to
		 *
		 *	@param		{string|null}	uri			Element uri, or null for a new one
		 *
		 *	@return		void
		 */
		_visit : function( uri ) {

			if( Nino.admin.elements._saving === true )
				return;

			Nino.admin.router.go( 'elements', [ Nino.admin.elements._currentType, uri === null ? 'new' : uri ] );
			Nino.admin.elements._openForm( uri );
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
			Nino.admin.elements._elements					= [];
			Nino.admin.elements._referenceOptions	= {};
			Nino.admin.elements._pendingUri				= undefined;
			Nino.admin.elements._types						= [];
			Nino.admin.elements._resetEdits();
			Nino.admin.elements._remember();

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
		 *	Call an elements/* admin action - this panel's name for
		 *	Nino.adminUi.api.call(), which owns where the request goes (always
		 *	/_admin/, with its trailing slash, below the project's directory,
		 *	dispatched server-side via $_POST['action']), why extra multipart
		 *	fields (eg. a File) just work, and what happens when the session
		 *	is gone
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "elements/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *	@param		{Object}		[extra]				Extra multipart fields (eg. { file : File })
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback, extra ) {
			Nino.adminUi.api.call( 'elements/'+ endpoint, payload, callback, extra );
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
			Nino.adminUi.showError( container, status, response, '/_admin/elements/error/load' );
		},

		/**
		 *	Drill-down navigation: types -> list -> form, each level hiding its
		 *	parent (the main System/Text/Elements bar stays visible throughout,
		 *	so only the local "‹ Back" links in the list/form need to move
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
		 *	(see Admin.php's typeDescr()) - and the list is rendered once, by
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
			Nino.admin.elements._types		= types;
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
				btn.addEventListener( 'click', function() { Nino.admin.router.go( 'elements', [ entry.type ] ); Nino.admin.elements._selectType( entry.type, entry.model, entry.title ) } );

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

		/**
		 *	The elements before and after the open one, in the list's order:
		 *	null at either end, both null for a new element or for one the
		 *	list does not hold (yet)
		 *
		 *	@return		{Object}								{ prev, next } - uris or null
		 */
		_neighbours : function() {
			const none = { prev : null, next : null };
			if( Nino.admin.elements._isNew === true || Nino.admin.elements._currentUri === null )
				return none;
			const uris = Nino.admin.elements._elements.map( function( element ) { return element.uri } );
			const at = uris.indexOf( Nino.admin.elements._currentUri );
			if( at === -1 )
				return none;
			return { prev : uris[at - 1] ?? null, next : uris[at + 1] ?? null };
		},

		/**
		 *	Previous/next for the form's context bar: one button each, stepping
		 *	through the type's elements as the list orders them without going
		 *	back to it, disabled at either end. Like the back link, stepping
		 *	away asks first when the form holds input nobody has saved
		 *
		 *	@return		{Element}
		 */
		_renderNav : function() {

			const neighbours = Nino.admin.elements._neighbours();
			const nav = dc.createElement('div');
			nav.className = 'admin-element-nav';

			[ [ 'prev', neighbours.prev ], [ 'next', neighbours.next ] ].forEach( function( pair ) {
				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'nino-admin-btn-secondary';
				btn.dataset.nav = pair[0];
				// Empty when there is nothing to step to - what _setFormPending()
				// reads to keep the button disabled once a save is over
				btn.dataset.uri = pair[1] ?? '';
				btn.disabled = ( pair[1] === null );
				btn.textContent = Nino.content.getText( '/_admin/elements/label/'+ pair[0] );
				btn.addEventListener( 'click', function() {
					if( btn.dataset.uri === '' )
						return;
					Nino.admin.elements._guard( function() { Nino.admin.elements._visit( btn.dataset.uri ) } );
				} );
				nav.appendChild( btn );
			} );

			return nav;
		},

		_selectType : function( type, model, title ) {

			if( Nino.admin.elements._saving === true )
				return;

			const requestId = ++Nino.admin.elements._listRequest;
			++Nino.admin.elements._formRequest; // invalidate an element still loading
			Nino.admin.elements._currentType 			= type;
			Nino.admin.elements._currentTypeTitle	= title;
			Nino.admin.elements._currentModel			= model;
			Nino.admin.elements._elements					= [];
			Nino.admin.elements._globalKeys		= Object.keys( model ).filter( function( key ) { return ( model[key].locale ?? false ) !== true } );
			Nino.admin.elements._localeKeys		= Object.keys( model ).filter( function( key ) { return ( model[key].locale ?? false ) === true } );

			Nino.admin.elements._destroyHtmlEditors();
			dc.getElementById('elements-form').innerHTML = '';

			dc.querySelectorAll('.admin-type-btn').forEach( function( btn ) { btn.classList.toggle( 'active', btn.dataset.type === type ) } );

			Nino.admin.elements._apiCall( 'list', { type : type, locale : Nino.admin.sessionLocale.current ?? '' }, function( status, response ) {
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
				Nino.admin.elements._renderList( response.elements, response.columns ?? [] );
				Nino.admin.elements._showList();

				const pendingUri = Nino.admin.elements._pendingUri;
				Nino.admin.elements._pendingUri = undefined;
				if( pendingUri !== undefined )
					Nino.admin.elements._openForm( pendingUri === 'new' ? null : pendingUri );
			} );
		},

		/**
		 *	Read the current type's list again - after a save, or when the
		 *	workbench's content locale moved on since the list was read - and
		 *	draw it, leaving whatever is on screen alone if the answer is late
		 *
		 *	@return		void
		 */
		_refreshList : function() {
			const type = Nino.admin.elements._currentType;
			Nino.admin.elements._apiCall( 'list', { type : type, locale : Nino.admin.sessionLocale.current ?? '' }, function( status, response ) {
				if( status === 200 && response !== null && type === Nino.admin.elements._currentType )
					Nino.admin.elements._renderList( response.elements, response.columns ?? [] );
			} );
		},

		/**
		 *	Render the element list for the current type, plus an "add new"
		 *	button: a table of the fields a cell can show - one row per element,
		 *	its uri first, the cells the translation the workbench is set to -
		 *	or, for a type none of whose fields fits a cell, the plain list of
		 *	labels
		 *
		 *	@param		{Array}		elements			[ { uri, label, values }, ... ]
		 *	@param		{Array}		columns				Field keys a cell can show, in model order (the server's displayableColumns())
		 *
		 *	@return		void
		 */
		_renderList : function( elements, columns ) {

			Nino.admin.elements._elements = elements;
			Nino.admin.elements._listLocale = Nino.admin.sessionLocale.current ?? '';
			columns = Array.isArray( columns ) ? columns : [];

			// The list is re-read after every save, so a form open on one of
			// these follows the fresh order - a just created element included
			const form = dc.getElementById('elements-form');
			const nav = form === null ? null : form.querySelector('.admin-element-nav');
			if( nav !== null && nav !== undefined )
				nav.replaceWith( Nino.admin.elements._renderNav() );

			const wrap = dc.getElementById('elements-list');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/elements/label/backtypes');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.router.go( 'elements', [] ); Nino.admin.elements._showTypes() } );
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
			} else if( columns.length > 0 ) {
				// The shared data table: search, type-aware sort and pages over
				// the whole set (see Nino.adminUi.table()). The uri is the row's
				// identity and its first column, under a key no model field can
				// carry - '.uri' is what the kernel itself calls it - so a field
				// named "uri" stays a field. A row is a link into the form, as
				// the plain list's entries are
				const mount = dc.createElement('div');
				mount.id = 'elements-table';
				wrap.appendChild( mount );
				Nino.adminUi.table( {
					mount 		: mount,
					rowKey 		: '.uri',
					columns 	: [ { key : '.uri', label : Nino.content.getText('/_admin/elements/label/uri'), type : 'string' } ].concat( columns.map( function( key ) {
						return { key : key, label : Nino.admin.elements._fieldLabel( key ), type : ( ( Nino.admin.elements._currentModel || {} )[key] || {} ).type || 'string' };
					} ) ),
					rows 			: elements.map( function( element ) { return Object.assign( { '.uri' : element.uri }, element.values || {} ) } ),
					labels 		: {
						search 	: Nino.content.getText('/_admin/elements/label/reference-search'),
						empty 	: Nino.content.getText('/_admin/elements/label/reference-empty'),
						noMatch : Nino.content.getText('/_admin/elements/label/reference-no-matches'),
					},
					onRowClick : function( row ) { Nino.admin.elements._visit( row['.uri'] ) },
				} );
			} else {
				const ul = dc.createElement('ul');
				ul.className = 'nino-admin-list';
				elements.forEach( function( element ) {
					const li 		= dc.createElement('li');
					const link	= dc.createElement('a');
					link.href = '#';
					link.textContent = element.label;
					link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.elements._visit( element.uri ) } );
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
			addBtn.addEventListener( 'click', function() { Nino.admin.elements._visit( null ) } );
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
				Nino.admin.elements._resetEdits();
				Nino.admin.elements._remember();
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
				Nino.admin.elements._resetEdits();
				Nino.admin.elements._remember();
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
		 *	@param		{string}	[rawText]			What a list or object field was last typed as, when that was not valid JSON
		 *
		 *	@return		{Element}								Field wrapper
		 */
		_renderField : function( key, field, value, rawText ) {

			const label = Nino.admin.elements._renderFieldControl( key, field, value, rawText );
			Nino.admin.elements._fieldNodes[key] = label;

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
		 *	@param		{string}	[rawText]			What a list or object field was last typed as, when that was not valid JSON
		 *
		 *	@return		{Element}
		 */
		_renderFieldControl : function( key, field, value, rawText ) {

			const displayName = Nino.admin.elements._fieldLabel( key );
			const isHtml = field.type === 'string' && field.html === true;
			const marked = Nino.admin.elements._isMarkedRequired( key, field );
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
				if( marked === true )
					Nino.admin.elements._star( span );
				label.appendChild( span );
				const mount = dc.createElement('div');
				label.appendChild( mount );
				Nino.admin.elements._htmlEditors[key] = Nino.admin.htmlEditor.create( mount, value ?? '', field.maxlength ?? Nino.admin.elements.DEFAULT_MAXLENGTH, field.inputsize ?? 0, field.blocks === true ? 'blocks' : 'inline' );
				// On the text box itself, which is what a screen reader lands on
				if( marked === true && typeof Nino.admin.elements._htmlEditors[key].mark === 'function' )
					Nino.admin.elements._htmlEditors[key].mark( { required : true } );
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
				Nino.admin.elements._markRequired( marked, span, select );

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

					// Only the asterisk: the control is a list and a search box, with
					// no element a screen reader could be told is required. Its first
					// child is the name
					if( marked === true )
						Nino.admin.elements._star( list.firstChild );

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
				Nino.admin.elements._markRequired( marked, span, select );

				const options = referenceOptions;
				const current = ( value === null || value === undefined ) ? '' : String( value );

				// Always offered, even on a required field: the browser's own
				// "select an option" is not what holds the save back - the same
				// _validate() check every other type goes through is, and it
				// needs an empty state to be able to catch
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

				// Where the alt text of this picture is written: a field of its
				// own, per language, which the model names. Said here and on
				// that field, so neither is found by accident
				if( field.alt ) {
					const altHint = dc.createElement('p');
					altHint.className = 'nino-admin-field-hint';
					altHint.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/elements/hint/image-alt'), Nino.admin.elements._fieldLabel( field.alt ) );
					label.appendChild( altHint );
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

					// The field is written empty and its file deleted, right
					// away like an upload: only there is an image to take away
					const removeBtn = dc.createElement('button');
					removeBtn.type = 'button';
					removeBtn.className = 'nino-admin-btn-danger';
					removeBtn.textContent = Nino.content.getText('/_admin/elements/label/image-remove');
					removeBtn.hidden = ! value;
					removeBtn.addEventListener( 'click', function() {
						Nino.admin.elements._removeImage( key, displayName, hiddenInput, preview, msg, removeBtn );
					} );

					fileInput.addEventListener( 'change', function() {
						if( fileInput.files.length === 0 )
							return;
						Nino.admin.elements._uploadImage( key, fileInput.files[0], hiddenInput, preview, msg, fileInput, removeBtn );
					} );
					wrap.appendChild( fileInput );
					// What the server will take, before the file is chosen
					const hint = Nino.adminUi.uploadHint();
					if( hint !== null )
						wrap.appendChild( hint );
					wrap.appendChild( removeBtn );
					wrap.appendChild( msg );
				}

				label.appendChild( wrap );
				return label;
			}

			// A list of texts is edited as a list of rows. Anything else a list can
			// hold - objects, numbers, a list inside a list - and a text that was
			// typed and is not JSON stay in the JSON field below, which can say it
			if( field.type === 'array' && typeof rawText !== 'string' && Nino.admin.elements._isStringList( value ) === true ) {

				const list = Nino.adminUi.stringList( {
					key 		: key,
					label 	: displayName,
					value 	: Array.isArray( value ) ? value : [],
					text 		: {
						add 		: Nino.content.getText('/_admin/elements/label/list-add'),
						remove 	: Nino.content.getText('/_admin/elements/label/reference-remove'),
						up 			: Nino.content.getText('/_admin/elements/label/reference-up'),
						down 		: Nino.content.getText('/_admin/elements/label/reference-down'),
						empty 	: Nino.content.getText('/_admin/elements/label/list-empty'),
						item 		: Nino.adminUi.format( Nino.content.getText('/_admin/elements/label/list-item'), displayName, '%d' ),
					},
				} );

				// Only the asterisk, as on the list of references: the name is the
				// control's first child, and a screen reader is told which rows are
				// the field's by the row names
				if( marked === true )
					Nino.admin.elements._star( list.firstChild );

				if( field.suffix ) {
					const suffix = dc.createElement('span');
					suffix.className = 'nino-admin-field-suffix';
					suffix.textContent = field.suffix;
					list.appendChild( suffix );
				}

				return list;
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
				// What was typed, if it was not JSON: the value it could not become
				// is not what the person should find when they come back to it
				input.value = typeof rawText === 'string' ? rawText : JSON.stringify( value ?? [] );
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
				Nino.admin.elements._markRequired( marked, span, input );
				label.appendChild( Nino.admin.elements._wrapWithSuffix( input, field ) );
				return label;
			}

			// Every plain string field: label name + right-aligned live character
			// count in the same row, plus the matching maxlength
			const maxlength = field.maxlength ?? Nino.admin.elements.DEFAULT_MAXLENGTH;
			input.maxLength = maxlength;

			// The model may say how many rows the field opens with; the author
			// can still drag it taller (the stylesheet leaves resize on)
			if( ( field.inputsize ?? 0 ) > 0 )
				input.rows = field.inputsize;

			const header = dc.createElement('div');
			header.className = 'nino-admin-field-header';

			const nameSpan = dc.createElement('span');
			nameSpan.className = 'nino-admin-field-name';
			nameSpan.textContent = displayName;
			Nino.admin.elements._markRequired( marked, nameSpan, input );
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

			// The alt text of an image field, said where it is written
			const model = Nino.admin.elements._currentModel || {};
			Object.keys( model ).filter( function( imageKey ) {
				return ( model[imageKey] || {} ).type === 'image' && model[imageKey].alt === key;
			} ).forEach( function( imageKey ) {
				const altHint = dc.createElement('p');
				altHint.className = 'nino-admin-field-hint';
				altHint.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/elements/hint/alt'), Nino.admin.elements._fieldLabel( imageKey ) );
				label.appendChild( altHint );
			} );

			return label;
		},

		/**
		 *	Whether a field is drawn as required: its model says so and a save
		 *	holds the form back for it. An image is uploaded on its own once the
		 *	element exists, a yes/no choice is never empty, and a field this
		 *	account may not write can be neither filled nor focused - none of
		 *	them is something to ask for (see _validate())
		 *
		 *	@param		{string}	key
		 *	@param		{Object}	field					Model field definition
		 *
		 *	@return		{boolean}
		 */
		_isMarkedRequired : function( key, field ) {
			return field.required === true && field.type !== 'image' && field.type !== 'boolean' && Nino.admin.elements._mayUpdate( key ) !== false;
		},

		/**
		 *	The asterisk after a field's name. Drawn for the eye and hidden
		 *	from a screen reader, which is told through aria-required on the
		 *	control instead
		 *
		 *	@param		{Element}	name					The field's name element
		 *
		 *	@return		void
		 */
		_star : function( name ) {
			const star = dc.createElement('span');
			star.className = 'nino-admin-required';
			star.setAttribute( 'aria-hidden', 'true' );
			star.textContent = '*';
			name.appendChild( star );
		},

		/**
		 *	Mark a field required: the asterisk on its name, aria-required on
		 *	its control. Nothing for a field that is not drawn as required
		 *
		 *	@param		{boolean}	marked
		 *	@param		{Element}	name
		 *	@param		{Element}	control
		 *
		 *	@return		void
		 */
		_markRequired : function( marked, name, control ) {

			if( marked !== true )
				return;

			Nino.admin.elements._star( name );
			control.setAttribute( 'aria-required', 'true' );
		},

		/**
		 *	Whether a value of an array field is a plain list of texts - or
		 *	nothing yet, which is an empty one - and so can be edited as rows
		 *
		 *	@param		{*}				value
		 *
		 *	@return		{boolean}
		 */
		_isStringList : function( value ) {
			return value === undefined || value === null || ( Array.isArray( value ) === true && value.every( function( item ) { return typeof item === 'string' } ) === true );
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
		 *	Resolve a model field's admin-facing label, in this order: a fill of
		 *	the type's own for the field (/_admin/elements/field/<type>/<key>,
		 *	which a feature or a project may bring), else the word of the
		 *	vocabulary the key is (/_admin/common/word/<key>: "title" is "Titel"),
		 *	else the key itself with a capital first letter - "price_default"
		 *	is "Price_default"
		 *
		 *	@param		{string}	key						Field key (eg. "title")
		 *
		 *	@return		{string}
		 */
		_fieldLabel : function( key ) {

			const translated = Nino.content.getText('/_admin/elements/field/'+ Nino.admin.elements._currentType+ '/'+ key);

			if( translated !== '' )
				return translated;

			const word = Nino.content.getText('/_admin/common/word/'+ key);

			return word !== '' ? word : key.charAt(0).toUpperCase()+ key.slice(1);
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
		 *	Read a list or object field's text: blank is an empty list, a
		 *	JSON array or object is the value, and anything else - a scalar,
		 *	null, a syntax error - is no value at all. The server answers 400
		 *	for the last of those on its own (see \Nino\Elements::valueError());
		 *	this is what lets the form say so before anything is sent, at the
		 *	field, and keep what was typed
		 *
		 *	@param		{string}	text
		 *
		 *	@return		{Object}									{ ok, value } - value is [] where ok is false, which is what a read has always fallen back to
		 */
		_parseArray : function( text ) {

			const raw = String( text ?? '' ).trim();
			if( raw === '' )
				return { ok : true, value : [] };

			let value;
			try {
				value = JSON.parse( raw );
			} catch( e ) {
				return { ok : false, value : [] };
			}

			return value !== null && typeof value === 'object' ? { ok : true, value : value } : { ok : false, value : [] };
		},

		/**
		 *	Whether a list or object has nothing in it - the way the kernel
		 *	counts (see \Nino\Elements: count() === 0), so an object with
		 *	entries satisfies a required field the same as a list does
		 *
		 *	@param		{*}				value
		 *
		 *	@return		{boolean}
		 */
		_isEmptyCollection : function( value ) {
			return value === null || typeof value !== 'object' || Object.keys( value ).length === 0;
		},

		/**
		 *	The text in a field's control, as typed - null where there is none
		 *	on screen (another translation, a field this form does not draw)
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{string|null}
		 */
		_rawText : function( key ) {
			const form = dc.getElementById('elements-form');
			const input = form?.querySelector( '[data-field="'+ CSS.escape( key )+ '"]' );
			return ( input === null || input === undefined ) ? null : input.value;
		},

		/**
		 *	Whether a required field is currently empty - reads the raw dom
		 *	value rather than _readFieldByKey()'s parsed one: integer/double
		 *	coerce an empty input to 0 there, which would make "required"
		 *	unable to ever catch a blank number/date field. Boolean is never
		 *	considered empty (a radio choice always has a value), and a list or
		 *	object text that is not valid JSON is not empty either: it is
		 *	reported for what it is (see _validate()).
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
				const parsed = Nino.admin.elements._parseArray( Nino.admin.elements._rawText( key ) );
				return parsed.ok === true && Nino.admin.elements._isEmptyCollection( parsed.value );
			}

			if( Nino.adminUi.isMultiElement( field ) === true ) {
				const value = Nino.admin.elements._readFieldByKey( key, field );
				return Array.isArray( value ) === false || value.length === 0;
			}

			const form = dc.getElementById('elements-form');
			const input = form?.querySelector( '[data-field="'+ CSS.escape( key )+ '"]' );
			return ( input === null || input === undefined ) || input.value.trim() === '';
		},

		/**
		 *	Whether a required field is empty in a translation that is not on
		 *	screen - the same question _isFieldEmpty() answers, asked of the
		 *	stored value instead of the dom, because the only locale with
		 *	controls to read is the visible one.
		 *
		 *	The one case it cannot see is a blank number field: _readField()
		 *	turned that into 0 when the locale was stored, and 0 is a number.
		 *	For the visible locale _isFieldEmpty() still reads the raw input,
		 *	and for a locale that came from the server the value is whatever
		 *	was actually written.
		 *
		 *	@param		{Object}	field					Model field definition ({ type, html, required, ... })
		 *	@param		{*}				value					The stored value for one locale
		 *
		 *	@return		{boolean}
		 */
		_isStoredValueEmpty : function( field, value ) {

			if( field.type === 'boolean' )
				return false;

			if( field.type === 'array' )
				return Nino.admin.elements._isEmptyCollection( value );

			if( Nino.adminUi.isMultiElement( field ) === true )
				return Array.isArray( value ) === false || value.length === 0;

			if( field.type === 'string' && field.html === true )
				return String( value ?? '' ).replace(/<[^>]+>/g, '').trim() === '';

			return value === null || value === undefined || String( value ).trim() === '';
		},

		/**
		 *	Everything that holds this save back, in the order the form shows
		 *	it: the uri of a new element, the global fields in model order, the
		 *	translation on screen, then the other translations this save is
		 *	about to write, in the order they were edited. Each problem is
		 *	{ key, locale, kind, label } - locale is null for the uri and the
		 *	global fields, kind is 'required' for an empty required field and
		 *	'json' for a list or object text that does not parse.
		 *
		 *	The translations that are checked are the ones _saveLocales()
		 *	queues, and the visible one only if it is among them: a translation
		 *	nobody edited is not written, so an empty required field in it is
		 *	not this save's business (an element may perfectly well have a
		 *	language it has no text for yet). One that is named is the one the
		 *	person could do something about, and its language is in the label of
		 *	the language switch (see _markLocales()).
		 *
		 *	An image field is never among them, even if its model says
		 *	required: its file is uploaded separately, only once the element
		 *	exists and has a uri to attach the upload to (see _renderField()'s
		 *	image branch), so on a new element it is empty by construction -
		 *	holding the save back for it would make the element impossible to
		 *	create. Same rule /_admin's own copy of this module applies, and
		 *	the one its Element Types editor now enforces when writing a model.
		 *	Nor is a field this account may not write: the save leaves it out,
		 *	and it can be neither filled in nor focused
		 *
		 *	@return		{Array<Object>}
		 */
		_validate : function() {

			const model 	 = Nino.admin.elements._currentModel;
			const problems = [];

			const field = function( key, locale, visible, stored ) {

				const definition = model[key];
				if( definition.type === 'image' || definition.type === 'boolean' || Nino.admin.elements._mayUpdate( key ) === false )
					return;

				let kind = null;

				if( definition.type === 'array' ) {
					const typed = visible === true ? Nino.admin.elements._rawText( key ) : Nino.admin.elements._invalidArrays[locale+ '|'+ key];
					if( typeof typed === 'string' && Nino.admin.elements._parseArray( typed ).ok === false )
						kind = 'json';
				}

				if( kind === null && definition.required === true ) {
					const empty = visible === true ? Nino.admin.elements._isFieldEmpty( key, definition ) : Nino.admin.elements._isStoredValueEmpty( definition, stored[key] );
					if( empty === true )
						kind = 'required';
				}

				if( kind !== null )
					problems.push( { key : key, locale : locale, kind : kind, label : Nino.admin.elements._fieldLabel( key ) } );
			};

			const uriInput = dc.getElementById('elements-form-uri');
			if( Nino.admin.elements._isNew === true && Nino.admin.elements._isNumbered() === false && uriInput !== null && uriInput.value.trim() === '' )
				problems.push( { key : '.uri', locale : null, kind : 'required', label : Nino.content.getText('/_admin/elements/label/uri') } );

			Nino.admin.elements._globalKeys.forEach( function( key ) { field( key, null, true, {} ) } );

			// The translation on screen first, the rest in edit order
			const locales = Nino.admin.elements._saveLocales();
			locales.sort( function( a, b ) { return ( b === Nino.admin.elements._selectedLocale ) - ( a === Nino.admin.elements._selectedLocale ) } );

			locales.forEach( function( locale ) {
				const stored = Nino.admin.elements._localeValues[locale] ?? {};
				Nino.admin.elements._localeKeys.forEach( function( key ) { field( key, locale, locale === Nino.admin.elements._selectedLocale, stored ) } );
			} );

			return problems;
		},

		/**
		 *	Say where a save was refused: every problem on screen gets
		 *	aria-invalid, a sentence under it that the control points at with
		 *	aria-describedby, and the form's status line gets the one summary.
		 *	The sentences are not alerts - a form with six empty fields would
		 *	announce six at once - the summary is announced, and focus plus
		 *	aria-describedby tell the rest where the person is standing.
		 *
		 *	A translation that is not on screen has its problems counted in the
		 *	language switch instead (see _markLocales()); if the first problem
		 *	of all is in one, the form switches to it, so there is something
		 *	to focus.
		 *
		 *	@param		{Array<Object>}	problems			What _validate() found
		 *	@param		{boolean}				[announce]		Say it in the status line and move the focus - a save was just refused
		 *
		 *	@return		void
		 */
		_showProblems : function( problems, announce ) {

			const form = dc.getElementById('elements-form');

			if( announce === true && problems.length > 0 && problems[0].locale !== null && problems[0].locale !== Nino.admin.elements._selectedLocale && Nino.admin.elements._localeKeys.length > 0 ) {
				Nino.admin.elements._switchLocale( problems[0].locale );
			}

			form.querySelectorAll('.nino-admin-field-error').forEach( function( el ) { el.remove() } );
			form.querySelectorAll('[aria-invalid]').forEach( function( el ) {
				el.removeAttribute('aria-invalid');
				el.removeAttribute('aria-describedby');
			} );
			Object.keys( Nino.admin.elements._htmlEditors ).forEach( function( key ) {
				if( typeof Nino.admin.elements._htmlEditors[key].mark === 'function' )
					Nino.admin.elements._htmlEditors[key].mark( { invalid : false, describedBy : '' } );
			} );

			let first = null;

			problems.forEach( function( problem ) {

				if( problem.locale !== null && problem.locale !== Nino.admin.elements._selectedLocale )
					return;

				const target = Nino.admin.elements._problemTarget( problem );
				if( target === null )
					return;

				const id = ( problem.key === '.uri' ? 'elements-error-uri' : 'elements-error-field-'+ problem.key );
				const error = dc.createElement('p');
				error.id = id;
				error.className = 'nino-admin-field-error';
				error.textContent = Nino.content.getText( problem.key === '.uri' ? '/_admin/elements/error/uri' : ( problem.kind === 'json' ? '/_admin/elements/error/json' : '/_admin/elements/error/field-required' ) );
				// After a label, not in it: the sentence is the control's description,
				// and inside the label it would be part of its name as well
				if( target.wrapper.tagName.toLowerCase() === 'label' )
					target.wrapper.after( error );
				else
					target.wrapper.appendChild( error );

				if( target.handle !== null )
					target.handle.mark( { invalid : true, describedBy : id } );
				else {
					target.control.setAttribute( 'aria-invalid', 'true' );
					target.control.setAttribute( 'aria-describedby', id );
				}

				if( first === null )
					first = target;
			} );

			Nino.admin.elements._markLocales( problems );

			if( announce !== true || problems.length === 0 )
				return;

			// One sentence, in the status line, which announces it once. A list that does not parse
			// is not an empty required field, so its own words stand for it
			Nino.admin.elements._status.fail( Nino.content.getText( problems.some( function( problem ) { return problem.kind === 'required' } ) ? '/_admin/elements/error/required' : '/_admin/elements/error/json' ) );

			if( first === null )
				return;

			if( first.handle !== null )
				first.handle.focus();
			else if( typeof first.control.focus === 'function' )
				first.control.focus();
		},

		/**
		 *	The parts of a problem's field on screen: the element the sentence
		 *	goes into, the control that is invalid and takes the focus, and the
		 *	rich-text handle where the control is an editor
		 *
		 *	@param		{Object}	problem				{ key, kind, ... } from _validate()
		 *
		 *	@return		{Object|null}						{ wrapper, control, handle }
		 */
		_problemTarget : function( problem ) {

			if( problem.key === '.uri' ) {
				const input = dc.getElementById('elements-form-uri');
				return input === null ? null : { wrapper : input.parentNode, control : input, handle : null };
			}

			const wrapper = Nino.admin.elements._fieldNodes[problem.key];
			if( wrapper === undefined || wrapper === null )
				return null;

			if( Nino.admin.elements._htmlEditors[problem.key] !== undefined && typeof Nino.admin.elements._htmlEditors[problem.key].mark === 'function' )
				return { wrapper : wrapper, control : wrapper, handle : Nino.admin.elements._htmlEditors[problem.key] };

			// A list of references has no control that is the field: its search box
			// is where a person starts, and where the focus goes - and so does the
			// add button of a list of texts that has no row yet
			const control = wrapper.querySelector('.nino-admin-elementlist-search') ?? wrapper.querySelector('input:not([type="hidden"]), textarea, select') ?? wrapper.querySelector('.nino-admin-stringlist-add');
			return control === null ? null : { wrapper : wrapper, control : control, handle : null };
		},

		/**
		 *	Tell the language switch which translations still have problems:
		 *	their option reads "de_DE – 2 open", every other one its plain code
		 *	again. Only the translations a save would write are ever named
		 *	(see _validate())
		 *
		 *	@param		{Array<Object>}	problems
		 *
		 *	@return		void
		 */
		_markLocales : function( problems ) {

			const select = dc.getElementById('elements-form-locale-select');
			if( select === null )
				return;

			select.querySelectorAll('option').forEach( function( option ) {

				const open = problems.filter( function( problem ) { return problem.locale === option.value } ).length;

				option.textContent = open === 0
					? option.value
					: Nino.adminUi.format( Nino.content.getText('/_admin/elements/label/locale-open'), option.value, open );
			} );
		},

		/**
		 *	After a refused save the marks follow what is typed: the problems
		 *	that were fixed lose theirs. Nothing is announced again - the status
		 *	line keeps the refusal until the next save, or until nothing is marked
		 *	and nothing is unsaved
		 *
		 *	@return		void
		 */
		_revalidate : function() {

			if( Nino.admin.elements._validated === false || Nino.admin.elements._currentModel === null )
				return;

			Nino.admin.elements._storeVisibleLocaleFields();

			const problems = Nino.admin.elements._validate();
			Nino.admin.elements._showProblems( problems, false );

			// Nothing marked and nothing left to save: the refusal no longer
			// names anything, so the line goes back to idle
			if( problems.length === 0 && Nino.admin.elements._status !== null && Nino.admin.elements._status.state === 'error' && Nino.admin.elements.isDirty() === false )
				Nino.admin.elements._status.idle();
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
				return Nino.admin.elements._parseArray( input.value ).value;

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
		 *	A list or object text that is not valid JSON cannot be stored as a
		 *	value. The previous value stays where it was, the text goes to
		 *	_invalidArrays (what the field shows again, and what the save is held
		 *	back for), and the translation counts as edited either way - or
		 *	nothing would write it, ask about it or warn that it is about to be lost.
		 *
		 *	A translation counts as edited when a control differs from what it held
		 *	when it was drawn, not from the stored value: a rich-text field
		 *	reads back as the markup it built, which is not always the string the
		 *	server holds, and merely visiting a translation must not mark it
		 *
		 *	@return		void
		 */
		_storeVisibleLocaleFields : function() {

			const wrap = dc.getElementById('elements-form-locale-fields');
			if( wrap === null || Nino.admin.elements._selectedLocale === null )
				return;

			const locale = Nino.admin.elements._selectedLocale;
			const previous = Nino.admin.elements._localeValues[locale] ?? {};
			const values = {};
			let changed = false;

			Nino.admin.elements._localeKeys.forEach( function( key ) {

				const field = Nino.admin.elements._currentModel[key];
				const slot = locale+ '|'+ key;

				if( field.type === 'array' ) {
					const typed = Nino.admin.elements._rawText( key );
					if( typed !== null && Nino.admin.elements._parseArray( typed ).ok === false ) {
						Nino.admin.elements._invalidArrays[slot] = typed;
						values[key] = previous[key];
						changed = true;
						return;
					}
					delete Nino.admin.elements._invalidArrays[slot];
				}

				values[key] = Nino.admin.elements._readFieldByKey( key, field );

				// What this account may not write is never sent, and a rich text
				// reads back as the editor's markup rather than the stored string:
				// it would count as edited just by visiting the language
				if( field.type === 'image' || Nino.admin.elements._mayUpdate( key ) === false )
					return;

				const baseline = Nino.admin.elements._baseline.locale[key];
				if( baseline !== undefined ? Nino.admin.elements._comparable( key, field ) !== baseline : Nino.admin.elements._fieldValuesEqual( field, previous[key], values[key] ) === false )
					changed = true;
			} );

			const dirtyAt = Nino.admin.elements._dirtyLocales.indexOf( locale );
			if( changed === true && dirtyAt === -1 )
				Nino.admin.elements._dirtyLocales.push( locale );

			Nino.admin.elements._localeValues[locale] = values;
		},

		/**
		 *	What a field's control holds, as a string to compare: the value it
		 *	reads back as, or - for a list or object text that does not parse -
		 *	the text itself, so typing into a field that is already wrong counts
		 *
		 *	@param		{string}	key
		 *	@param		{Object}	field
		 *
		 *	@return		{string}
		 */
		_comparable : function( key, field ) {

			if( field.type === 'array' ) {
				const typed = Nino.admin.elements._rawText( key ) ?? '';
				const parsed = Nino.admin.elements._parseArray( typed );
				return JSON.stringify( parsed.ok === true ? parsed.value : { invalid : typed } );
			}

			return JSON.stringify( Nino.admin.elements._readFieldByKey( key, field ) ?? null );
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
		 *	Let go of everything a form on screen carries besides its values:
		 *	the texts that were not JSON, the marks of a refused save, the
		 *	baselines its controls are compared with, the copy that nobody has
		 *	saved. Called wherever a form is opened, dropped or discarded
		 *
		 *	@return		void
		 */
		_resetEdits : function() {
			Nino.admin.elements._invalidArrays	= {};
			Nino.admin.elements._validated			= false;
			Nino.admin.elements._copy						= false;
			Nino.admin.elements._baseline				= { uri : null, global : {}, locale : {} };
		},

		/**
		 *	Take the values as they are now for the ones the server holds -
		 *	when an element was read, and for each translation as it is saved
		 *
		 *	@return		void
		 */
		_remember : function() {
			Nino.admin.elements._pristine = {
				global	: Nino.admin.elements._clone( Nino.admin.elements._globalValues ),
				locales	: Nino.admin.elements._clone( Nino.admin.elements._localeValues ),
			};
		},

		/**
		 *	@param		{*}				value					Plain data: what the server sent
		 *
		 *	@return		{*}											A copy nothing else holds
		 */
		_clone : function( value ) {
			return JSON.parse( JSON.stringify( value ) );
		},

		/**
		 *	What the uri and the global fields hold now, as the baseline the
		 *	form is compared with. Not an image (an upload commits it by
		 *	itself) and not a field this account may not write
		 *
		 *	@return		void
		 */
		_captureGlobalBaseline : function() {

			const uri = dc.getElementById('elements-form-uri');

			Nino.admin.elements._baseline.uri = ( uri === null || uri.type === 'hidden' ) ? null : uri.value.trim();
			Nino.admin.elements._baseline.global = {};
			Nino.admin.elements._globalKeys.forEach( function( key ) {
				if( Nino.admin.elements._currentModel[key].type !== 'image' && Nino.admin.elements._mayUpdate( key ) === true )
					Nino.admin.elements._baseline.global[key] = Nino.admin.elements._comparable( key, Nino.admin.elements._currentModel[key] );
			} );
		},

		/**
		 *	The same for the translation on screen - after it was drawn
		 *
		 *	@return		void
		 */
		_captureLocaleBaseline : function() {

			Nino.admin.elements._baseline.locale = {};
			Nino.admin.elements._localeDirtyAtDraw = Nino.admin.elements._dirtyLocales.indexOf( Nino.admin.elements._selectedLocale ) !== -1;
			if( dc.getElementById('elements-form-locale-fields') === null )
				return;

			Nino.admin.elements._localeKeys.forEach( function( key ) {
				if( Nino.admin.elements._currentModel[key].type !== 'image' && Nino.admin.elements._mayUpdate( key ) === true )
					Nino.admin.elements._baseline.locale[key] = Nino.admin.elements._comparable( key, Nino.admin.elements._currentModel[key] );
			} );
		},

		/**
		 *	Whether the form on screen holds input nobody has saved - what the
		 *	shell asks before it lets anything throw that away (see
		 *	Nino.admin.dirty). Only an open form can: a list or the type picker
		 *	has nothing typed into it. It is when a translation was edited and
		 *	left, a list or object text is not JSON, the form is a copy, or a
		 *	control differs from what it held when it was drawn or last saved
		 *
		 *	@return		{boolean}
		 */
		isDirty : function() {

			const form = dc.getElementById('elements-form');

			if( form === null || Nino.admin.elements._currentType === null || form.classList.contains('admin-hidden') === true || dc.getElementById('elements-edit-form') === null )
				return false;

			if( Nino.admin.elements._copy === true || Object.keys( Nino.admin.elements._invalidArrays ).length > 0 )
				return true;

			const uri = dc.getElementById('elements-form-uri');
			if( uri !== null && uri.type !== 'hidden' && Nino.admin.elements._baseline.uri !== null && uri.value.trim() !== Nino.admin.elements._baseline.uri )
				return true;

			const changed = function( keys, baseline ) {
				return keys.some( function( key ) {
					return baseline[key] !== undefined && Nino.admin.elements._comparable( key, Nino.admin.elements._currentModel[key] ) !== baseline[key];
				} );
			};

			const onScreen = dc.getElementById('elements-form-locale-fields') !== null;
			const localeChanged = onScreen === true && changed( Nino.admin.elements._localeKeys, Nino.admin.elements._baseline.locale );

			// A translation a refused save stored is not edited once the fields
			// hold what they were drawn with again
			const edited = Nino.admin.elements._dirtyLocales.some( function( locale ) {
				return locale !== Nino.admin.elements._selectedLocale || onScreen === false || Nino.admin.elements._localeDirtyAtDraw === true || localeChanged === true;
			} );

			return edited === true || localeChanged === true || changed( Nino.admin.elements._globalKeys, Nino.admin.elements._baseline.global );
		},

		/**
		 *	Throw the input away: the values go back to what the server holds,
		 *	and what the controls show now counts as saved. The form is about to
		 *	be left or drawn again, which is what makes that honest. If the exit
		 *	then fails without drawing the form (a refused request), the
		 *	controls still show the discarded text and the next Save writes it.
		 *	While a save runs nothing is thrown away: it finishes with what it was given
		 *
		 *	@return		void
		 */
		discard : function() {

			// A running save sends the values it was given: going back to the
			// stored ones now would change what the languages not yet sent carry
			if( Nino.admin.elements._saving === true )
				return;

			Nino.admin.elements._globalValues	= Nino.admin.elements._clone( Nino.admin.elements._pristine.global );
			Nino.admin.elements._localeValues	= Nino.admin.elements._clone( Nino.admin.elements._pristine.locales );
			Nino.admin.elements._dirtyLocales	= [];
			Nino.admin.elements._resetEdits();
			Nino.admin.elements._captureGlobalBaseline();
			Nino.admin.elements._captureLocaleBaseline();
			Nino.admin.elements._refreshDirty();
		},

		/**
		 *	Run proceed() - after the shell has asked about unsaved input in
		 *	this form, where the shell has the registry. A shell without it
		 *	(an older one, a test) goes straight on
		 *
		 *	@param		{Function}	proceed
		 *
		 *	@return		void
		 */
		_guard : function( proceed ) {

			if( typeof Nino.admin.dirty !== 'object' ) {
				proceed();
				return;
			}

			Nino.admin.dirty.guard( [ 'elements' ], proceed );
		},

		/**
		 *	Have the shell look at the markers and the browser's question again
		 *
		 *	@return		void
		 */
		_refreshDirty : function() {
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
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
			// The previous/next buttons sit in the same pane: the one at an end
			// stays disabled after the save, like the read-only fields do
			wrap.querySelectorAll('.admin-element-nav button').forEach( function( btn ) {
				btn.disabled = pending || btn.dataset.uri === '';
			} );
			// The back link is one of them: a click on it would ask about input
			// the running save is already sending
			wrap.querySelectorAll('a').forEach( function( el ) {
				// Links inside a rich-text editor are its content: getValue() returns them, so
				// writing attributes onto them would turn the field's value into a change
				if( el.closest('[contenteditable]') !== null )
					return;
				el.setAttribute( 'aria-disabled', pending ? 'true' : 'false' );
				el.style.pointerEvents = pending ? 'none' : '';
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
				wrap.appendChild( Nino.admin.elements._renderField( key, Nino.admin.elements._currentModel[key], values[key] ?? null, Nino.admin.elements._invalidArrays[Nino.admin.elements._selectedLocale+ '|'+ key] ) );
			} );

			Nino.admin.elements._captureLocaleBaseline();

			// A fresh translation carries the marks of the refused save too
			if( Nino.admin.elements._validated === true )
				Nino.admin.elements._showProblems( Nino.admin.elements._validate(), false );
		},

		/**
		 *	Show another translation: what is on screen is stored first, the
		 *	switch itself follows (the select's own change handler reads the
		 *	value from the select, so a switch made from code sets it too), and
		 *	the workbench's content locale moves with it
		 *
		 *	@param		{string}	locale
		 *
		 *	@return		void
		 */
		_switchLocale : function( locale ) {

			Nino.admin.elements._storeVisibleLocaleFields();
			Nino.admin.elements._selectedLocale = locale;

			const select = dc.getElementById('elements-form-locale-select');
			if( select !== null )
				select.value = locale;

			Nino.admin.sessionLocale.set( locale );
			Nino.admin.elements._renderLocaleFields();
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
			Nino.admin.elements._fieldNodes = {};

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
				Nino.admin.router.go( 'elements', [ Nino.admin.elements._currentType ] );
				Nino.admin.elements._showList();
				// The form's locale switch moves the workbench's content locale;
				// the list's cells show one translation, so they follow
				if( Nino.admin.elements._listLocale !== ( Nino.admin.sessionLocale.current ?? '' ) )
					Nino.admin.elements._refreshList();
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
				// Not the native required: its bubble comes in the browser's language
				// and a save from the unsaved-changes question never meets it. The
				// uri is the first thing _validate() asks for
				Nino.admin.elements._markRequired( true, uriSpan, uriInput );
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

				// The id is what _renderLocaleFields() looks the heading up by.
				// Without it that lookup answered null and skipped the update
				// in silence, so switching the locale select left the English
				// title standing over the German fields - the one thing the
				// comment above promises does not happen
				const title = dc.createElement('div');
				title.id = 'elements-form-heading';
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
				select.addEventListener( 'change', function() { Nino.admin.elements._switchLocale( select.value ) } );
				toolbar.appendChild( select );

				const fieldsWrap = dc.createElement('div');
				fieldsWrap.id = 'elements-form-locale-fields';
				fieldsWrap.className = 'nino-admin-fieldgrid';
				localeWrap.appendChild( fieldsWrap );

				form.appendChild( localeWrap );
			}

			// Stepping through the type's elements from the form itself, at the
			// bar's right end after the locale switch. A new element is not in
			// the list yet, so there is nothing to step from
			if( Nino.admin.elements._isNew === false )
				toolbar.appendChild( Nino.admin.elements._renderNav() );

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
			actions.appendChild( msg );

			form.appendChild( actions );

			// Whether what is on screen is saved: any edit turns "saved at" into
			// "unsaved changes", and a save says which of the two it ended in
			Nino.admin.elements._status = Nino.adminUi.status( msg );
			Nino.admin.elements._status.bind( form, Nino.admin.elements.isDirty );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.elements._save() } );

			// After a refused save the marks follow what is typed
			form.addEventListener( 'input', Nino.admin.elements._revalidate );
			form.addEventListener( 'change', Nino.admin.elements._revalidate );

			wrap.appendChild( form );

			Nino.admin.elements._captureGlobalBaseline();

			if( Nino.admin.elements._localeKeys.length > 0 )
				Nino.admin.elements._renderLocaleFields();

			Nino.admin.elements._refreshDirty();
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
		 *	Every way this ends reports to done( ok ), if there is one: the
		 *	shell's question about unsaved input saves through it and goes on
		 *	only when it hears true (see Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when everything was written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const report = function( ok ) {
				Nino.admin.elements._refreshDirty();
				if( typeof done === 'function' )
					done( ok );
			};

			if( Nino.admin.elements._saving === true ) {
				report( false );
				return;
			}

			Nino.admin.elements._storeVisibleLocaleFields();

			// Nothing is sent while something holds the save back: the problems are
			// marked at their fields, with the focus on the first (see _validate())
			const problems = Nino.admin.elements._validate();
			if( problems.length > 0 ) {
				Nino.admin.elements._validated = true;
				Nino.admin.elements._showProblems( problems, true );
				report( false );
				return;
			}

			// A numbered type is saved with no uri on purpose - that is what asks
			// the backend for the next number. Reassigned below, once the insert
			// says which one it got, so the remaining locales of this same save
			// address the element that now exists.
			let uri = dc.getElementById('elements-form-uri').value.trim();

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
			Nino.admin.elements._status.saving();

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
						}

						Nino.admin.elements._saving = false;
						Nino.admin.elements._setFormPending( false );
						Nino.admin.elements._status.error( status, response, '/_admin/elements/error/save' );
						report( false );
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

					// What the server holds now: what discard() goes back to
					Nino.admin.elements._pristine.global = Nino.admin.elements._clone( Nino.admin.elements._globalValues );
					Nino.admin.elements._pristine.locales[locale] = Nino.admin.elements._clone( Nino.admin.elements._localeValues[locale] );

					const dirtyAt = Nino.admin.elements._dirtyLocales.indexOf( locale );
					if( dirtyAt !== -1 )
						Nino.admin.elements._dirtyLocales.splice( dirtyAt, 1 );

					position++;
					if( position < locales.length ) {
						saveNextLocale();
						return;
					}

					// Saved, so nothing is refused or typed-but-wrong any more
					if( Nino.admin.elements._validated === true )
						Nino.admin.elements._showProblems( [], false );
					Nino.admin.elements._resetEdits();

					// Stay on the form after saving. A newly-created element is rendered
					// once more to lock its uri and expose the delete action; existing
					// elements keep focus and selection exactly where they were - what
					// their controls hold is what is saved now. The baseline is taken
					// once the form is enabled again, like Text and Keys do.
					if( wasNew === true ) {
						Nino.admin.elements._renderForm();
						Nino.admin.router.set( 'elements', [ Nino.admin.elements._currentType, Nino.admin.elements._currentUri ] );
					}

					Nino.admin.elements._saving = false;
					Nino.admin.elements._setFormPending( false );

					if( wasNew !== true ) {
						Nino.admin.elements._captureGlobalBaseline();
						Nino.admin.elements._captureLocaleBaseline();
					}

					Nino.admin.elements._status.saved();

					Nino.admin.elements._refreshList();
					report( true );
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
		 *	@param		{Element}	removeBtn				The Remove button, shown once the field holds an image
		 *
		 *	@return		void
		 */
		_uploadImage : function( key, file, hiddenInput, preview, msg, fileInput, removeBtn ) {

			fileInput.disabled = true;
			msg.className = 'nino-admin-field-image-msg';
			msg.textContent = Nino.content.getText('/_admin/elements/msg/pending');

			// The record the file belongs to is taken now: the check below waits
			// for the picture to decode, and the person may step to another
			// element or language meanwhile
			const target = {
				type 		: Nino.admin.elements._currentType,
				uri 		: Nino.admin.elements._currentUri,
				locale 	: Nino.admin.elements._selectedLocale,
				key 		: key,
			};

			// A file the server cannot take is refused here, with the reason,
			// instead of after the upload
			Nino.adminUi.checkImage( file, function( rejection ) {

				if( rejection !== null ) {
					fileInput.disabled = false;
					fileInput.value = '';
					msg.className = 'nino-admin-field-image-msg is-error';
					msg.textContent = Nino.adminUi.api.errorText( 400, rejection );
					return;
				}

				Nino.admin.elements._apiCall( 'uploadimage', target, function( status, response ) {

					fileInput.disabled = false;
					fileInput.value = '';

					if( status !== 200 || response === null ) {
						msg.className = 'nino-admin-field-image-msg is-error';
						msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/elements/error/save' );
						return;
					}

					hiddenInput.value = response.filename;
					preview.src = response.url;
					preview.hidden = false;
					removeBtn.hidden = false;

					// Saved either way - but a picture below the target size was
					// scaled up, which is said in words as well as in colour
					if( response.belowTarget === true && response.source ) {
						msg.className = 'nino-admin-field-image-msg is-warning';
						msg.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/elements/msg/image-below-target'), response.source.width+ ' × '+ response.source.height+ ' px' );
						return;
					}

					msg.textContent = Nino.content.getText('/_admin/elements/msg/saved');
				}, { file : file } );
			} );
		},

		/**
		 *	Take the image out of one field of the saved element, after asking
		 *	- the question names the field and what follows. The server writes
		 *	the field empty and deletes the file, immediately like an upload; a
		 *	cancelled question sends nothing
		 *
		 *	@param		{string}		key						The image field
		 *	@param		{string}		name					Its label, for the question
		 *	@param		{Element}		hiddenInput		The field's value, emptied
		 *	@param		{Element}		preview				<img> preview element
		 *	@param		{Element}		msg						Status message element
		 *	@param		{Element}		removeBtn			The Remove button itself, disabled while pending
		 *
		 *	@return		void
		 */
		_removeImage : function( key, name, hiddenInput, preview, msg, removeBtn ) {

			if( wn.confirm( Nino.adminUi.format( Nino.content.getText('/_admin/elements/confirm/image-remove'), name ) ) === false )
				return;

			removeBtn.disabled = true;
			msg.className = 'nino-admin-field-image-msg';
			msg.textContent = Nino.content.getText('/_admin/elements/msg/pending');

			// The record is taken now, as for an upload: the person may step to
			// another element or language while the request is on its way
			Nino.admin.elements._apiCall( 'removeimage', {
				type 		: Nino.admin.elements._currentType,
				uri 		: Nino.admin.elements._currentUri,
				locale 	: Nino.admin.elements._selectedLocale,
				key 		: key,
			}, function( status, response ) {

				removeBtn.disabled = false;

				if( status !== 200 || response === null ) {
					msg.className = 'nino-admin-field-image-msg is-error';
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/elements/error/image-remove' );
					return;
				}

				hiddenInput.value = '';
				preview.hidden = true;
				removeBtn.hidden = true;
				msg.textContent = Nino.content.getText('/_admin/elements/msg/image-removed');
			} );
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
		 *	The values of the form on screen are what is copied, so input nobody
		 *	has saved is asked about first: saved, the copy carries it; thrown
		 *	away, the copy is of the element as it is stored.
		 *
		 *	@return		void
		 */
		_duplicate : function() {

			if( Nino.admin.elements._saving === true || Nino.admin.elements._isNew === true )
				return;

			Nino.admin.elements._guard( Nino.admin.elements._makeCopy );
		},

		/**
		 *	The copy itself (see _duplicate())
		 *
		 *	@return		void
		 */
		_makeCopy : function() {

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
			Nino.admin.elements._resetEdits();
			Nino.admin.elements._copy 					= true;
			Nino.admin.elements._pristine 			= { global : {}, locales : {} };

			Nino.admin.elements._renderForm();
			Nino.admin.router.go( 'elements', [ Nino.admin.elements._currentType, 'new' ] );

			// A copy nobody has saved yet is the one state "unsaved" is for
			Nino.admin.elements._status.dirty( Nino.content.getText('/_admin/elements/msg/duplicated') );

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
					Nino.admin.elements._status.error( status, response, '/_admin/elements/error/save' );
					return;
				}
				Nino.admin.elements._saving = false;
				Nino.admin.elements._selectType( type, model, title );
			} );
		},
	};

	// The shell asks before anything throws this form's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'elements', {
			isDirty : Nino.admin.elements.isDirty,
			save		: function( done ) { Nino.admin.elements._save( done ) },
			discard : Nino.admin.elements.discard,
		} );

})(window, document, document.documentElement, document.body);
