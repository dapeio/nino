

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js   							Admin "Elements" panel: browse types, list/create/edit/delete
 *													the elements within a type. An element *type* itself - its
 *													title and its model - is the Types tab of this same pane
 *													(see assets/types.js), behind a permission of its own.
 *
 *													Every view has an address that survives a reload: the
 *													types (#elements), the elements of a type
 *													(#elements/<type>), one element (#elements/<type>/<uri>)
 *													and a new one (#elements/<type>/@new - an at sign no
 *													element name has, so that an element called new keeps its
 *													own address). A form shows the language the whole workbench
 *													edits content in (Nino.admin.workLocale) and no other: it
 *													draws again when that moves - the shell has asked about its
 *													unsaved input first - and a save writes the language on
 *													screen, so that no field is ever written into a language it
 *													is not shown in. The one exception is the copy of an element,
 *													which carries every language the original has.
 *
 *													A save leaves the person in the form and says so as a toast;
 *													a new element gets its address when it is created. The form
 *													checks itself (novalidate): what is wrong - here or in the
 *													answer of the server - is said at its field.
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
		// The fields that take textfills, per type, as apiTypes() reported them for this account:
		// { <type> : { <field> : form } } - only fields it may change are in it
		_fills					: {},
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
		// What the server holds of the open element: the global values, and the values of every language it has text in
		_globalValues		: {},
		_localeValues		: {},
		// The language the form on screen shows - and the only one a save writes (see _formLocale())
		_locale					: null,
		// What the form's controls held when they were drawn or last saved, as compared strings: the uri, then every field
		_baseline				: { uri : null, fields : {} },
		// The form is a copy nobody has saved (see _duplicate()): it holds the languages the original has
		_copy						: false,
		// The field of every key on screen, by key: { el, controls, read(), empty(), compare(), error( text ), focus() }
		_fields					: {},
		// The status line of the bar of the form on screen, and the bar itself
		_status					: null,
		_bar						: null,
		// The open type's elements as the list last showed them, [ { uri,
		// label } ] in the list's order - what the form's previous/next step
		// through. Refilled with every list the server sends (see
		// _renderList()), dropped with the type
		_elements				: [],
		// The translation the list's cells show - the workbench's content
		// locale at the time the list was read, so a form that left asks for a new reading
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
		// The control that opened the form that is open: Back returns the focus to it
		_opener					: null,
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
		// How many types a list shows before it has a search of its own (the guide: from 12 entries on)
		TYPES_SEARCH_FROM : 12,

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
		 *	type picker, a type's list or one element's form ('@new' for a blank
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

			// The screen stays as it is, and so does the history: the step that
			// brought the address here is taken back (see Nino.admin.router)
			if( Nino.admin.elements._saving === true ) {
				if( typeof Nino.admin.router.refuse === 'function' )
					Nino.admin.router.refuse();
				return false;
			}

			const current = Nino.admin.elements._currentType;
			const form = current !== null && dc.getElementById('elements-form').classList.contains('admin-hidden') === false;
			const list = current !== null && dc.getElementById('elements-list').classList.contains('admin-hidden') === false;

			const type = parts.length === 0 ? undefined : Nino.admin.elements._types.find( function( t ) { return t.type === parts[0] } );
			const uri = type === undefined || parts.length < 2 ? undefined : parts[1];

			if( type === undefined ? ( form === false && list === false ) : ( uri === undefined
				? ( list === true && current === type.type )
				: ( form === true && current === type.type && ( uri === '@new' ? Nino.admin.elements._isNew === true : ( Nino.admin.elements._isNew === false && Nino.admin.elements._currentUri === uri ) ) ) ) ) {

				// Already on the level the address names - but a request for a
				// deeper one may still be on its way (an element opened from the
				// list, a list loading from the picker, and then Back): its
				// answer must not draw the form or the list over this level
				Nino.admin.elements._dropRequests( uri === undefined, type === undefined );
				return false;
			}

			Nino.admin.router.leave( [ 'elements' ], form, function() {

				// What was asked for before this move is not what the person
				// asked for last. A level that opens something takes its own
				// request id (_openForm(), _selectType()); one that only shows
				// a level retires the ones still in flight
				Nino.admin.elements._dropRequests( uri === undefined, type === undefined );

				if( type === undefined ) {
					Nino.admin.elements._destroyHtmlEditors();
					Nino.admin.elements._showTypes();
					return;
				}

				if( current === type.type && uri !== undefined ) {
					Nino.admin.elements._openForm( uri === '@new' ? null : uri );
					return;
				}

				if( current === type.type && form === true ) {
					Nino.admin.elements._destroyHtmlEditors();
					Nino.admin.elements._showList();
					// The form shows the language of the workbench, which may have moved since the list was read
					if( Nino.admin.elements._listLocale !== Nino.admin.elements._workingLocale( '' ) )
						Nino.admin.elements._refreshList();
					return;
				}

				Nino.admin.elements._pendingUri = uri;
				Nino.admin.elements._selectType( type.type, type.model, type.title );
			}, Nino.admin.elements._showLevel );

			return true;
		},

		/**
		 *	Let the answer of a request that is still on its way fall away: it
		 *	compares its id with the counter and finds it moved. Used when the
		 *	level it would draw is not the one the address names any more
		 *
		 *	@param		{boolean}		form			A form (elements/get) is not wanted any more
		 *	@param		{boolean}		list			A list (elements/list) is not wanted any more either - the picker is the target
		 *
		 *	@return		void
		 */
		_dropRequests : function( form, list ) {

			if( form === true )
				++Nino.admin.elements._formRequest;

			if( list === true )
				++Nino.admin.elements._listRequest;
		},

		/**
		 *	Open an element - or a blank one - the way a person does, from the
		 *	list or the form's previous and next: the step is one Back returns to
		 *
		 *	@param		{string|null}	uri			Element uri, or null for a new one
		 *	@param		{Element}			[opener]	The control that was pressed, which Back gives the focus to
		 *
		 *	@return		void
		 */
		_visit : function( uri, opener ) {

			if( Nino.admin.elements._saving === true )
				return;

			Nino.admin.elements._opener = opener ?? null;
			Nino.admin.router.go( 'elements', [ Nino.admin.elements._currentType, uri === null ? '@new' : uri ] );
			Nino.admin.elements._openForm( uri, undefined, true );
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
			Nino.admin.elements._elements					= [];
			Nino.admin.elements._referenceOptions	= {};
			Nino.admin.elements._pendingUri				= undefined;
			Nino.admin.elements._types						= [];
			Nino.admin.elements._fields						= {};
			Nino.admin.elements._resetEdits();

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
		 *	parent (the shell's rail and the pane's tab strip stay visible
		 *	throughout, so only the local "Back to ..." links in the list/form need
		 *	to move back up one level, not the whole page). The focus stays where
		 *	it is, unless it stood in the level that is hidden now (see
		 *	Nino.adminUi.showLevel()): a level is shown again as it stands by the
		 *	address, or after a request. Back goes to the control that opened the
		 *	level that is left, and what a person opens takes the focus in
		 *
		 *	@param		{Element|null}	[opener]	Back: the control to give the focus to; null where there is none to name
		 *
		 *	@return		void
		 */
		_showTypes : function( opener ) {
			Nino.admin.router.set( 'elements', [] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'elements-types', { focus : false } );
			else
				Nino.adminUi.backTo( 'elements-types', opener );

			Nino.admin.elements._refreshTypes();
		},

		/**
		 *	Re-read the type list behind the overview. What it shows per type is
		 *	content, not schema - the element count (see Admin.php's apiTypes()) -
		 *	and the list is rendered once, by init(). Creating or deleting an
		 *	element and going back to the overview therefore left the count it
		 *	had on page load: "0 elements" next to a type that visibly has an
		 *	element in it.
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

		/**
		 *	@param		{Element|null}	[opener]	A person's move: the control to give the focus to (Back), or null for the level itself (a type that was picked). Left out, the list is only shown
		 *
		 *	@return		void
		 */
		_showList : function( opener ) {
			Nino.admin.router.set( 'elements', [ Nino.admin.elements._currentType ] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'elements-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'elements-list', opener );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showFormView : function( opened ) {
			Nino.admin.router.set( 'elements', [ Nino.admin.elements._currentType, Nino.admin.elements._isNew ? '@new' : Nino.admin.elements._currentUri ] );
			Nino.adminUi.showLevel( 'elements-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	The row of a type as the picker is drawn now - the link that opens it: the
		 *	picker is drawn again whenever it is shown, and the row that was pressed is
		 *	gone with the old drawing
		 *
		 *	@param		{string}	type
		 *
		 *	@return		{Element|null}
		 */
		_typeButton : function( type ) {

			const row = Array.from( dc.getElementById('elements-types').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === type } );

			return row === undefined ? null : row.querySelector('.nino-admin-row-open');
		},

		/**
		 *	The row of an element as the list is drawn now - a row of the table, an
		 *	entry of the plain list: the list is drawn again after a save, and the
		 *	row that was pressed is gone with the old drawing
		 *
		 *	@param		{string|null}	uri
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( uri ) {
			return Array.from( dc.getElementById('elements-list').querySelectorAll('tr, li') ).find( function( row ) { return row.dataset.row === uri } ) ?? null;
		},

		/**
		 *	The control Back returns the focus to from the form that is open: the
		 *	row of its element, and for a blank form the button that adds one
		 *
		 *	@return		{Element|null}
		 */
		_backTarget : function() {

			if( Nino.admin.elements._isNew === false && Nino.admin.elements._currentUri !== null )
				return Nino.admin.elements._rowOf( Nino.admin.elements._currentUri );

			return dc.getElementById('elements-list').querySelector('.nino-admin-btn-primary');
		},

		/**
		 *	How many elements a type holds, in words
		 *
		 *	@param		{number}	count
		 *
		 *	@return		{string}
		 */
		_countText : function( count ) {
			return count === 1 ? Nino.content.getText('/_admin/elements/label/element-one') : Nino.adminUi.format( Nino.content.getText('/_admin/elements/label/elements'), count );
		},

		/**
		 *	Render the list of element types: a row of the block for each, its title
		 *	over the number of its elements and its identifier, quietly. From
		 *	TYPES_SEARCH_FROM types on a search of the browser narrows it
		 *
		 *	@param		{Array}		types					[ { type, title, count, model }, ... ]
		 *
		 *	@return		void
		 */
		_renderTypes : function( types ) {

			// Every path into a form goes through this list, so it is the one
			// place that has to record which types number their own elements
			Nino.admin.elements._numbered = {};
			Nino.admin.elements._rights 	= {};
			Nino.admin.elements._fills 		= {};
			Nino.admin.elements._types		= types;
			types.forEach( function( entry ) {
				if( entry.autoincrement === true )
					Nino.admin.elements._numbered[entry.type] = entry.nextUri || '';
				if( entry.rights !== undefined )
					Nino.admin.elements._rights[entry.type] = entry.rights;
				if( entry.fills !== undefined )
					Nino.admin.elements._fills[entry.type] = entry.fills;
			} );

			const wrap = dc.getElementById('elements-types');

			// The picker is drawn again whenever it is shown, and the row that has
			// the focus is gone with the old drawing: the one for the same type takes it over
			const held = ( dc.activeElement ?? null ) !== null && dc.activeElement.closest('#elements-types') !== null ? dc.activeElement.closest('li') : null;
			const heldType = held === null ? null : ( held.dataset.row ?? null );
			const query = wrap.querySelector('.nino-admin-filterbar-input') !== null ? wrap.querySelector('.nino-admin-filterbar-input').value : '';

			wrap.innerHTML = '';

			if( types.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/elements/empty') ) );
				return;
			}

			const mount = Nino.adminUi.el( 'div' );

			const draw = function( text ) {

				const needle = text.trim().toLowerCase();
				const shown = types.filter( function( entry ) { return needle === '' || ( entry.title+ ' '+ entry.type ).toLowerCase().indexOf( needle ) !== -1 } );

				mount.innerHTML = '';

				if( shown.length === 0 ) {
					mount.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/elements/no-match') ) );
					return;
				}

				mount.appendChild( Nino.adminUi.list( shown.map( function( entry ) {
					return {
						key			: entry.type,
						title		: entry.title,
						detail	: Nino.admin.elements._countText( entry.count ?? 0 )+ ' · /'+ entry.type,
						href		: Nino.admin.router.href( 'elements', [ entry.type ] ),
						onOpen	: function() {
							Nino.admin.router.go( 'elements', [ entry.type ] );
							Nino.admin.elements._selectType( entry.type, entry.model, entry.title, true );
						},
					};
				} ), { label : Nino.content.getText('/_admin/nav/elements') } ) );
			};

			if( types.length >= Nino.admin.elements.TYPES_SEARCH_FROM )
				wrap.appendChild( Nino.adminUi.filterBar( { search : { label : Nino.content.getText('/_admin/elements/label/search-types'), value : query, onInput : draw } } ).el );

			wrap.appendChild( mount );
			draw( query );

			if( heldType !== null && Nino.admin.elements._typeButton( heldType ) !== null )
				Nino.admin.elements._typeButton( heldType ).focus();
		},

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

		/**
		 *	Whether the current type assigns its element uris itself (switched on
		 *	in its form on the Types tab - see \Nino\Elements::AUTOINCREMENT_PAD)
		 *
		 *	@return		{boolean}
		 */
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
		 *	Previous/next for the form's context bar: one icon button each, stepping
		 *	through the type's elements as the list orders them without going
		 *	back to it, disabled at either end. Like the back link, stepping
		 *	away asks first when the form holds input nobody has saved
		 *
		 *	@return		{Array<Element>}
		 */
		_stepButtons : function() {

			return [ [ 'prev', 'chevron-left' ], [ 'next', 'chevron-right' ] ].map( function( pair ) {

				const btn = Nino.adminUi.iconButton( pair[1], Nino.content.getText( '/_admin/elements/label/'+ pair[0] ), function() {
					if( btn.dataset.uri === '' )
						return;
					Nino.admin.elements._guard( function() { Nino.admin.elements._visit( btn.dataset.uri, btn ) } );
				} );

				btn.dataset.nav = pair[0];
				btn.dataset.uri = '';
				btn.disabled = true;

				return btn;
			} );
		},

		/**
		 *	Set the two step buttons of the form on screen to the elements next to the
		 *	open one. The list is re-read after every save, so a form open on one of
		 *	them follows the fresh order - a just created element included
		 *
		 *	@return		void
		 */
		_paintSteps : function() {

			const form = dc.getElementById('elements-form');
			const neighbours = Nino.admin.elements._neighbours();

			if( form === null )
				return;

			form.querySelectorAll('[data-nav]').forEach( function( btn ) {
				const uri = neighbours[btn.dataset.nav] ?? null;
				btn.dataset.uri = uri ?? '';
				btn.disabled = uri === null || Nino.admin.elements._saving === true;
			} );
		},

		/**
		 *	Select a type and load its element list
		 *
		 *	@param		{string}	type					Type name
		 *	@param		{Object}	model					Type model
		 *	@param		{string}	title					Type display title
		 *	@param		{boolean}	[opened]			The person opened it - a type picked, or an element deleted: the focus goes into the list when it arrives. Otherwise it is shown as it stands, by the address
		 *
		 *	@return		void
		 */
		_selectType : function( type, model, title, opened ) {

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

			Nino.admin.elements._apiCall( 'list', { type : type, locale : Nino.admin.elements._workingLocale( '' ) }, function( status, response ) {
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
				Nino.admin.elements._showList( opened === true ? null : undefined );

				const pendingUri = Nino.admin.elements._pendingUri;
				Nino.admin.elements._pendingUri = undefined;
				if( pendingUri !== undefined )
					Nino.admin.elements._openForm( pendingUri === '@new' ? null : pendingUri );
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
			Nino.admin.elements._apiCall( 'list', { type : type, locale : Nino.admin.elements._workingLocale( '' ) }, function( status, response ) {
				if( status === 200 && response !== null && type === Nino.admin.elements._currentType )
					Nino.admin.elements._renderList( response.elements, response.columns ?? [] );
			} );
		},

		/**
		 *	The working language as far as this panel can show it: the workbench's
		 *	(Nino.admin.workLocale) where it is one of the site's languages, else $otherwise. The Text panel
		 *	also names languages the website does not serve yet, and an element has
		 *	no field for those here
		 *
		 *	@param		{string}	otherwise		Answered for a language this panel has none of
		 *
		 *	@return		{string}
		 */
		_workingLocale : function( otherwise ) {

			const current = Nino.admin.workLocale.current;

			return Nino.admin.elements._locales.indexOf( current ) !== -1 ? current : otherwise;
		},

		/**
		 *	The language a form shows and writes: the working language, or the first
		 *	language of the site where the working one is none of them
		 *
		 *	@return		{string}
		 */
		_formLocale : function() {
			return Nino.admin.elements._workingLocale( Nino.admin.elements._locales[0] ?? '' );
		},

		/**
		 *	The words of a model field, as a column head and as the name of a field: a fill of the type's own
		 *	(/_admin/elements/field/<type>/<key>, which a project may bring), else the label the type's model
		 *	gives the field (the server has chosen the language of the workbench), else the word of the
		 *	vocabulary the key is (/_admin/common/word/<key>: "title" is "Titel"), else the key read as words -
		 *	"price_default" is "Price default", "photoAlt" is "Photo alt"
		 *
		 *	@param		{string}	key						Field key (eg. "title")
		 *
		 *	@return		{string}
		 */
		_fieldLabel : function( key ) {

			const translated = Nino.content.getText('/_admin/elements/field/'+ Nino.admin.elements._currentType+ '/'+ key);

			if( translated !== '' )
				return translated;

			const declared = ( ( Nino.admin.elements._currentModel || {} )[key] || {} ).label;

			if( typeof declared === 'string' && declared !== '' )
				return declared;

			const word = Nino.content.getText('/_admin/common/word/'+ key);

			return word !== '' ? word : Nino.adminUi.humanize( key.replace( /([a-z0-9])([A-Z])/g, '$1 $2' ).toLowerCase() );
		},

		/**
		 *	Render the element list for the current type: the way back, the name of the
		 *	type with its identifier, the note of the type if it has one, the table
		 *	of the fields a cell can show - one row per element, its uri first,
		 *	the cells the translation the workbench is set to - or, where there is
		 *	no element, a box that says so, and the bar with "New element" as the one
		 *	main button, for an account that may add one
		 *
		 *	@param		{Array}		elements			[ { uri, label, values }, ... ]
		 *	@param		{Array}		columns				Field keys a cell can show, in model order (the server's displayableColumns())
		 *
		 *	@return		void
		 */
		_renderList : function( elements, columns ) {

			Nino.admin.elements._elements = elements;
			Nino.admin.elements._listLocale = Nino.admin.elements._workingLocale( '' );
			columns = Array.isArray( columns ) ? columns : [];

			// The list is re-read after every save, so a form open on one of
			// these follows the fresh order - a just created element included
			Nino.admin.elements._paintSteps();

			const wrap = dc.getElementById('elements-list');

			// The list is drawn again after a save and when the language moved on, and the
			// row that has the focus is gone with the old drawing: the row of the same
			// element - or the back link - takes it over
			const held = ( dc.activeElement ?? null ) !== null && dc.activeElement.closest('#elements-list') !== null ? dc.activeElement : null;
			wrap.innerHTML = '';

			const bar = Nino.adminUi.contextBar( {
				back	: { to : wrap, onClick : function() {
					Nino.admin.router.go( 'elements', [] );
					Nino.admin.elements._showTypes( Nino.admin.elements._typeButton( Nino.admin.elements._currentType ) );
				} },
				title	: Nino.admin.elements._currentTypeTitle,
				code	: '/'+ Nino.admin.elements._currentType,
			} );
			wrap.appendChild( bar );

			// The language the cells are in: the workbench's, said where it is not the native one. A type with no
			// field per language has nothing to say about it
			if( Nino.admin.elements._localeKeys.length > 0 )
				Nino.admin.elements._paintBadge( bar, Nino.admin.elements._workingLocale( '' ) || Nino.adminUi.locales.native );

			const typeNote = Nino.admin.elements._typeNote();
			if( typeNote !== null )
				wrap.appendChild( typeNote );

			if( elements.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/elements/none') ) );
			else if( columns.length > 0 ) {
				// The shared data table: search, type-aware sort and pages over
				// the whole set (see Nino.adminUi.table()). The uri is the row's
				// identity and its first column, under a key no model field can
				// carry - '.uri' is what the kernel itself calls it - so a field
				// named "uri" stays a field
				const mount = dc.createElement('div');
				mount.id = 'elements-table';
				wrap.appendChild( mount );
				Nino.adminUi.table( {
					mount 		: mount,
					rowKey 		: '.uri',
					search		: true,
					columns 	: [ { key : '.uri', label : Nino.content.getText('/_admin/elements/label/uri'), type : 'string' } ].concat( columns.map( function( key ) {
						const column = { key : key, label : Nino.admin.elements._fieldLabel( key ), type : ( ( Nino.admin.elements._currentModel || {} )[key] || {} ).type || 'string' };
						// A text another language holds says whose it is, beside it, and sorts as
						// no text: what is not translated yet stays with the cells that are empty
						// (searching reads what is shown)
						if( ( ( Nino.admin.elements._currentModel || {} )[key] || {} ).locale === true ) {
							column.render = function( value, row ) {
								const code = row['.from'][key];
								if( code === undefined )
									return undefined;
								const cell = Nino.adminUi.el( 'span', '' );
								const text = Nino.adminUi.el( 'span', '', Nino.adminUi.tableModel.format( value, column.type )+ ' ' );
								text.lang = String( code ).replace( '_', '-' );
								cell.appendChild( text );
								Nino.admin.elements._markFallback( cell, code );
								return cell;
							};
							column.absent = function( row ) { return row['.from'][key] !== undefined };
						}
						return column;
					} ) ),
					rows 			: elements.map( function( element ) { return Object.assign( { '.uri' : element.uri, '.from' : element.from ?? {} }, element.values || {} ) } ),
					labels 		: {
						search 	: Nino.content.getText('/_admin/elements/label/search'),
						empty 	: Nino.content.getText('/_admin/elements/none'),
						noMatch : Nino.content.getText('/_admin/elements/no-match'),
					},
					onRowClick : function( row ) { Nino.admin.elements._visit( row['.uri'] ) },
				} );
			} else {
				wrap.appendChild( Nino.adminUi.list( elements.map( function( element ) {
					return {
						key			: element.uri,
						title		: element.label,
						href		: Nino.admin.router.href( 'elements', [ Nino.admin.elements._currentType, element.uri ] ),
						onOpen	: function() { Nino.admin.elements._visit( element.uri ) },
					};
				} ), { label : Nino.admin.elements._currentTypeTitle } ) );
			}

			// The select is one of the things the list holds that is not a row
			if( held !== null )
				( Nino.admin.elements._rowOf( held.dataset.row ?? ( held.closest('[data-row]') ?? { dataset : {} } ).dataset.row ?? null ) ?? bar.querySelector('.nino-admin-back-link') ).focus();

			// "New element" is the main button of the bar of a list - and only for an account that may actually add one
			if( Nino.admin.elements._mayInsert() === false )
				return;

			const addBtn = Nino.adminUi.button( Nino.content.getText('/_admin/elements/label/add'), function() { Nino.admin.elements._visit( null, addBtn ) } );
			wrap.appendChild( Nino.adminUi.actionBar( { list : true, primary : addBtn } ).el );
		},

		/**
		 *	The note a type has for whoever edits its elements, if it has one:
		 *	the fill /_admin/elements/type/<type>/hint - which a unit may bring,
		 *	as the legal texts do to say they are no legal advice. It stays in
		 *	view as a notice, since it is a warning that holds for every element.
		 *	A type without the fill has none
		 *
		 *	@return		{Element|null}
		 */
		_typeNote : function() {

			const text = Nino.content.getText('/_admin/elements/type/'+ Nino.admin.elements._currentType+ '/hint');

			return text === '' ? null : Nino.adminUi.notice( text );
		},

		/**
		 *	Say beside a text of the list that it is not the element's own in the
		 *	language the list shows but another language's: the language, in the
		 *	spelling of the workbench's pill - the word is the
		 *	signal, the pill only sets it apart - and a sentence a screen reader
		 *	reads in its place, since the pill alone is a code
		 *
		 *	@param		{Element}	host				Where the text stands
		 *	@param		{string}	code				The locale the text is written in, eg. de_DE
		 *
		 *	@return		void
		 */
		_markFallback : function( host, code ) {

			const sentence = Nino.adminUi.format( Nino.content.getText('/_admin/elements/label/fallback'), Nino.adminUi.localeName( code ) );

			const badge = Nino.adminUi.el( 'span', 'nino-admin-badge', Nino.adminUi.localeName( code ) );
			badge.dataset.state = 'muted';
			badge.title = sentence;
			badge.setAttribute( 'aria-hidden', 'true' );
			host.appendChild( badge );

			host.appendChild( Nino.adminUi.el( 'span', 'nino-admin-sr-only', sentence ) );
		},

		/**
		 *	The badge of the language a view shows, in its bar: for a language that
		 *	is not the native one, and for the one it shows when that is not the
		 *	workbench's - a language this panel has no translation of (one the website
		 *	does not serve yet), where the form shows and writes another and says so
		 *
		 *	@param		{Element|null}	bar
		 *	@param		{string}				shown				The language on screen
		 *
		 *	@return		void
		 */
		_paintBadge : function( bar, shown ) {
			Nino.adminUi.localeBadge( shown, bar, shown !== Nino.admin.workLocale.current );
		},

		/**
		 *	Open the edit form for an existing element, or a blank one for a new element
		 *
		 *	@param		{string|null}	uri			Element uri, or null to create a new one
		 *	@param		{Function}		[then]	Called once the form is on screen: the blank one a save asked for says what it saved and has the focus in its first field (see _saveAndAdd())
		 *	@param		{boolean}			[opened]	The person opened it - from the list, the form's steps: the focus goes into the form when it arrives. Otherwise it is shown as it stands, by the address
		 *
		 *	@return		void
		 */
		_openForm : function( uri, then, opened ) {

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
				Nino.admin.elements._resetEdits();
				Nino.admin.elements._loadReferenceOptions( function() {
					if( requestId !== Nino.admin.elements._formRequest || type !== Nino.admin.elements._currentType )
						return;
					Nino.admin.elements._renderForm();
					Nino.admin.elements._showFormView( opened === true );
					if( typeof then === 'function' )
						then();
				} );
				return;
			}

			Nino.admin.elements._apiCall( 'get', { type : type, uri : uri }, function( status, response ) {

				if( requestId !== Nino.admin.elements._formRequest || type !== Nino.admin.elements._currentType )
					return;

				// An element that cannot be read - one the address names and the type has not - says so in place of the form,
				// under the way back to the list, and its address stays the one that was asked for
				if( status !== 200 || response === null ) {
					Nino.admin.elements._isNew = false;
					Nino.admin.elements._currentUri = null;
					Nino.admin.elements._fields = {};
					Nino.admin.elements._status = null;
					const wrap = dc.getElementById('elements-form');
					if( status === 404 ) {
						wrap.innerHTML = '';
						wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/elements/error/unknown') ) );
					}
					else
						Nino.admin.elements._showError( wrap, status, response );
					wrap.insertBefore( Nino.adminUi.contextBar( { back : { to : Nino.admin.elements._currentTypeTitle, onClick : function() {
						Nino.admin.router.go( 'elements', [ type ] );
						Nino.admin.elements._showList( null );
					} } } ), wrap.firstChild );
					Nino.admin.router.set( 'elements', [ type, uri ] );
					Nino.adminUi.showLevel( 'elements-form', opened === true ? {} : { focus : false } );
					return;
				}

				Nino.admin.elements._isNew 				= false;
				Nino.admin.elements._currentUri 	= uri;
				Nino.admin.elements._globalValues	= response.global;
				Nino.admin.elements._localeValues	= response.locales;
				Nino.admin.elements._raw					= response.raw || {};
				Nino.admin.elements._resetEdits();
				Nino.admin.elements._loadReferenceOptions( function() {
					if( requestId !== Nino.admin.elements._formRequest || type !== Nino.admin.elements._currentType )
						return;
					Nino.admin.elements._renderForm();
					Nino.admin.elements._showFormView( opened === true );
					if( typeof then === 'function' )
						then();
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
		 *	paint: a language switch draws the fields again, and options arriving
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
			// to an empty list rather than holding the form back - _makeField()
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
		 *	The asterisk after the name of a field a block did not make it for: in the words of the name, before
		 *	the (?). Drawn for the eye and hidden from a screen reader, which is told through aria-required on
		 *	the control instead
		 *
		 *	@param		{Element}	field					What the block returned
		 *
		 *	@return		void
		 */
		_star : function( field ) {

			const name = field.querySelector('.nino-admin-field-text') ?? field.querySelector('span');
			const star = dc.createElement('span');

			star.className = 'nino-admin-required';
			star.setAttribute( 'aria-hidden', 'true' );
			star.textContent = '*';
			name.appendChild( star );
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
		 *	Put the unit of a field - the 'suffix' of its model - beside its control: the control is taken out of
		 *	its field and set into a row with the unit after it
		 *
		 *	@param		{Element}	field					What a block returned
		 *	@param		{Element}	control				The control in it
		 *	@param		{Object}	model					Model field definition ({ suffix, ... })
		 *
		 *	@return		void
		 */
		_unit : function( field, control, model ) {

			if( !model.suffix )
				return;

			const row = dc.createElement('div');
			row.className = 'nino-admin-field-suffixed';
			control.replaceWith( row );
			row.appendChild( control );

			const suffix = dc.createElement('span');
			suffix.className = 'nino-admin-field-suffix';
			suffix.textContent = model.suffix;
			row.appendChild( suffix );
		},

		/**
		 *	The textfills one field takes, as the rows fillsDialog() shows: the name of each is its key read as words,
		 *	its notation the one the field reads, its value what it says in the language being edited
		 *
		 *	@param		{string}		key						Field key
		 *	@param		{Function}	done					Called once with the rows, or with null where they could not be had
		 *
		 *	@return		void
		 */
		_loadFills : function( key, done ) {

			Nino.admin.elements._apiCall( 'fills', { type : Nino.admin.elements._currentType, field : key }, function( status, response ) {

				if( status !== 200 || response === null || Array.isArray( response.fills ) === false ) {
					done( null );
					return;
				}

				done( response.fills.map( function( fill ) {
					return {
						name 			: fill.key.split('/').slice( 2 ).map( function( part ) { return Nino.adminUi.slugLabel( part ) } ).join(' · '),
						notation 	: fill.notation,
						value 		: fill.value,
					};
				} ) );
			} );
		},

		/**
		 *	The "[ ]" of a field, for the blocks that take it as an option: only where the server named the field as one that
		 *	takes textfills for this account (see Admin.php's fillFields()), and the account may write it
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Object|undefined}				{ editable, load }
		 */
		_fillsOf : function( key ) {

			if( typeof ( Nino.admin.elements._fills[Nino.admin.elements._currentType] ?? {} )[key] !== 'string' || Nino.admin.elements._mayUpdate( key ) !== true )
				return undefined;

			return { editable : true, load : function( done ) { Nino.admin.elements._loadFills( key, done ) } };
		},

		/**
		 *	The sentence behind the (?) of a field that is the alt text of a picture, or that the alt text of a
		 *	picture is written in: said where it is written and where it is used, so neither is found by accident
		 *
		 *	@param		{string}	key
		 *	@param		{Object}	field					Model field definition
		 *
		 *	@return		{string|undefined}
		 */
		_altHelp : function( key, field ) {

			const model = Nino.admin.elements._currentModel || {};

			if( field.type === 'image' && field.alt )
				return Nino.adminUi.format( Nino.content.getText('/_admin/elements/help/image-alt'), Nino.admin.elements._fieldLabel( field.alt ) );

			const image = Object.keys( model ).find( function( imageKey ) { return ( model[imageKey] || {} ).type === 'image' && model[imageKey].alt === key } );

			return image === undefined ? undefined : Nino.adminUi.format( Nino.content.getText('/_admin/elements/help/alt'), Nino.admin.elements._fieldLabel( image ) );
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
		 *	@return		{Object}									{ ok, value } - value is [] where ok is false
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
		 *	What a number control holds as the value it is saved as: a blank one has no value - null, which the server saves
		 *	as a field that was never set - and 0 is only what is typed as 0. Something that is no number is left as the text
		 *	it is, for the server to refuse as the wrong type; _validate() holds the save back for it before
		 *
		 *	@param		{string}	type					'integer' or 'double'
		 *	@param		{string}	text
		 *
		 *	@return		{number|string|null}
		 */
		_number : function( type, text ) {

			const typed = String( text ).trim();

			if( typed === '' )
				return null;

			const number = type === 'integer' ? parseInt( typed, 10 ) : parseFloat( typed );

			return Number.isNaN( number ) ? typed : number;
		},

		/**
		 *	Build one field of the model as the control of its type, with the handle the form reads, checks and marks it
		 *	by: { key, el, controls, read(), empty(), compare(), error( text ), clear(), focus() }
		 *
		 *	  - a rich text is the editor of the workbench, with the "[ ]" where the account may edit it
		 *	  - yes or no is a switch, a field with fixed values a select, a reference a select or the list of its elements
		 *	  - an image is the file field of the guide: it uploads at once, once the element exists, and says so as a toast
		 *	  - a list of texts is rows, any other list - and an object - JSON in the code field
		 *	  - a number, a date and a moment are their own controls, a text several lines of one with its count
		 *
		 *	A field this account may not write is drawn like any other and locked
		 *
		 *	@param		{string}	key						Field key
		 *	@param		{Object}	field					Its model definition
		 *	@param		{*}				value					Its current value
		 *
		 *	@return		{Object}
		 */
		_makeField : function( key, field, value ) {

			const elements = Nino.admin.elements;
			const label = elements._fieldLabel( key );
			const marked = elements._isMarkedRequired( key, field );
			const help = elements._altHelp( key, field );
			const fills = elements._fillsOf( key );
			let handle = null;

			if( field.type === 'string' && field.html === true )
				handle = elements._htmlField( key, field, value, label, marked, fills );
			else if( field.type === 'boolean' ) {

				const row = Nino.adminUi.switchField( { key : key, label : label, checked : value === true, help : help } );
				const control = row.querySelector('input');

				control.dataset.field = key;
				control.dataset.type = field.type;
				handle = { el : row, controls : [ control ], read : function() { return control.checked }, empty : function() { return false } };
			}
			else if( Array.isArray( field.options ) === true && field.options.length > 0 ) {

				// A field with a fixed set of allowed values renders as a select instead of its type's normal
				// input, whatever that type is - the value is still read per field.type as usual. The word of an
				// option is a fill of the type's own (/_admin/elements/option/<type>/<key>/<value>), which a
				// feature or a project may bring; without one it is the value
				// A field nothing was chosen for shows its first choice, as a select does, and a save writes it
				const row = Nino.adminUi.selectField( { key : key, label : label, help : help, value : value === null || value === undefined ? field.options[0] : value, options : field.options.map( function( opt ) {
					return { value : opt, label : Nino.content.getText('/_admin/elements/option/'+ elements._currentType+ '/'+ key+ '/'+ opt) || opt };
				} ) } );
				const control = row.querySelector('select');

				handle = elements._plain( key, field, row, control, marked );
				handle.read = function() { return control.value };
			}
			else if( field.type === 'element' )
				handle = elements._referenceField( key, field, value, label, marked, help );
			else if( field.type === 'image' )
				handle = elements._imageField( key, field, value, label, help );
			else if( field.type === 'array' )
				handle = elements._arrayField( key, field, value, label, marked, help, fills );
			else if( field.type === 'integer' || field.type === 'double' ) {

				const row = Nino.adminUi.textField( { key : key, type : 'number', label : label, help : help, required : marked, value : value ?? '' } );
				const control = row.querySelector('input');

				control.step = field.type === 'double' ? 'any' : '1';
				control.dataset.field = key;
				control.dataset.type = field.type;
				elements._unit( row, control, field );

				handle = elements._plain( key, field, row, control, false );
				handle.read = function() { return elements._number( field.type, control.value ) };
				handle.empty = function() { return control.value.trim() === '' };
				// A number control that holds something that is no number reads as empty
				handle.badInput = function() { return control.validity !== undefined && control.validity.badInput === true };
			}
			else if( field.type === 'date' ) {

				const row = Nino.adminUi.textField( { key : key, type : 'date', label : label, help : help, required : marked, value : value ?? '' } );
				const control = row.querySelector('input');

				control.dataset.field = key;
				control.dataset.type = field.type;
				elements._unit( row, control, field );

				handle = elements._plain( key, field, row, control, false );
			}
			else if( field.type === 'datetime' ) {

				const moment = Nino.adminUi.dateTime( { key : key, label : label, help : help, required : marked, value : value ?? '' } );

				moment.date.dataset.field = key;
				moment.date.dataset.type = field.type;
				handle = {
					el			: moment.el,
					controls	: [ moment.date, moment.time ],
					read		: function() { return moment.value() },
					empty		: function() { return moment.value() === '' },
					error		: function( id, text ) { return moment.error( text ) },
					clear		: function() { moment.clear() },
					focus		: function() { moment.focus() },
				};
			}
			else
				handle = elements._textField( key, field, value, label, marked, help, fills );

			handle.key = key;
			handle.compare = handle.compare ?? function() { return JSON.stringify( handle.read() ?? null ) };
			handle.clear = handle.clear ?? function() { Nino.adminUi.clearFieldErrors( handle.el ) };
			handle.focus = handle.focus ?? function() { if( handle.controls[0] !== undefined && typeof handle.controls[0].focus === 'function' ) handle.controls[0].focus() };
			handle.error = handle.error ?? function( id, text ) { return Nino.adminUi.fieldError( handle.el, handle.controls[0] ?? null, id, text ) };
			handle.empty = handle.empty ?? function() { return false };

			// A field this account may not write (see the panel's mayUpdate()): rendered exactly as it otherwise would
			// be, then locked. Showing the value matters - it is the context the writable fields next to it are edited
			// in - but nothing here may be typed into, and the save leaves it out
			if( elements._mayUpdate( key ) === false ) {
				handle.el.classList.add( 'admin-field-readonly' );
				handle.el.querySelectorAll('input, textarea, select, button').forEach( function( el ) { el.disabled = true } );
				handle.el.querySelectorAll('[contenteditable]').forEach( function( el ) {
					el.contentEditable = 'false';
					el.setAttribute( 'aria-disabled', 'true' );
				} );
			}

			return handle;
		},

		/**
		 *	The handle of a field of one control that a block drew in a label: the control, the star where the block
		 *	has no option for it, the data the form finds the control by
		 *
		 *	@param		{string}		key
		 *	@param		{Object}		field
		 *	@param		{Element}		row						What the block returned
		 *	@param		{Element}		control
		 *	@param		{boolean}		star					Whether the block did not mark it required itself
		 *
		 *	@return		{Object}
		 */
		_plain : function( key, field, row, control, star ) {

			control.dataset.field = key;
			control.dataset.type = field.type;

			if( star === true ) {
				Nino.admin.elements._star( row );
				control.setAttribute( 'aria-required', 'true' );
			}

			return {
				el			: row,
				controls	: [ control ],
				read		: function() { return control.value },
				empty		: function() { return control.value.trim() === '' },
			};
		},

		/**
		 *	A text of several lines, with the count of its characters under it
		 *
		 *	@return		{Object}
		 */
		_textField : function( key, field, value, label, marked, help, fills ) {

			// Every plain string field: the count of its characters beside the matching maxlength
			const maxlength = field.maxlength ?? Nino.admin.elements.DEFAULT_MAXLENGTH;
			const length = function( text ) { return text.length+ ' / '+ maxlength };

			const row = Nino.adminUi.textareaField( {
				key			: key,
				label		: label,
				help		: help,
				fills		: fills,
				required	: marked,
				value		: value ?? '',
				maxLength	: maxlength,
				rows		: ( field.inputsize ?? 0 ) > 0 ? field.inputsize : 2,
				note		: length( String( value ?? '' ) ),
			} );
			const control = row.querySelector('textarea');
			const counter = row.querySelector('.nino-admin-field-note');

			control.dataset.field = key;
			control.dataset.type = field.type;
			counter.classList.add('nino-admin-char-counter');
			control.addEventListener( 'input', function() {
				counter.textContent = length( control.value );
				counter.classList.toggle( 'is-limit', control.value.length >= maxlength );
			} );
			Nino.admin.elements._unit( row, control, field );

			return Nino.admin.elements._plain( key, field, row, control, false );
		},

		/**
		 *	A rich text: the editor of the workbench in a group named by the field, with the "[ ]" at its name where
		 *	the account may edit the field
		 *
		 *	@return		{Object}
		 */
		_htmlField : function( key, field, value, label, marked, fills ) {

			// A contenteditable inside <label> is invalid interactive markup and can make drag-selection fail in
			// Safari: rich text gets a semantic group
			const row = dc.createElement('div');
			row.className = 'nino-admin-field';
			row.setAttribute( 'role', 'group' );
			row.setAttribute( 'aria-label', label );

			const name = dc.createElement('span');
			name.textContent = label;
			row.appendChild( name );

			if( marked === true )
				Nino.admin.elements._star( row );

			// A text whose work textfills decide: the "[ ]" at its name, for an account that may change it
			if( fills !== undefined )
				name.appendChild( Nino.adminUi.fillsButton( { load : fills.load, opener : function() { return Nino.admin.elements._htmlEditors[key] } }, null, label ) );

			const mount = dc.createElement('div');
			row.appendChild( mount );

			const editor = Nino.admin.htmlEditor.create( mount, value ?? '', field.maxlength ?? Nino.admin.elements.DEFAULT_MAXLENGTH, field.inputsize ?? 0, field.blocks === true ? 'blocks' : 'inline' );
			Nino.admin.elements._htmlEditors[key] = editor;

			// On the text box itself, which is what a screen reader lands on
			if( marked === true && typeof editor.mark === 'function' )
				editor.mark( { required : true } );

			return {
				el			: row,
				controls	: [],
				read		: function() { return editor.getValue() },
				empty		: function() { return editor.getValue().replace( /<[^>]+>/g, '' ).trim() === '' },
				focus		: function() { editor.focus() },
				error		: function( id, text ) {
					const sentence = Nino.adminUi.fieldError( row, null, id, text );
					if( typeof editor.mark === 'function' )
						editor.mark( { invalid : true, describedBy : id } );
					return sentence;
				},
				clear		: function() {
					Nino.adminUi.clearFieldErrors( row );
					if( typeof editor.mark === 'function' )
						editor.mark( { invalid : false, describedBy : '' } );
				},
			};
		},

		/**
		 *	A reference to another element: the choices are that type's own elements, loaded before the form rendered
		 *	(see _loadReferenceOptions()), and the stored value is the referenced element's full uri - exactly what
		 *	\Nino\Elements::getElement() takes, so a template never has to re-join it with the model. A model that
		 *	allows several references gets the ordered list control instead of the select
		 *
		 *	@return		{Object}
		 */
		_referenceField : function( key, field, value, label, marked, help ) {

			const options = ( Nino.admin.elements._referenceOptions[field.elementType] ?? [] ).map( function( element ) {
				return { value : '/'+ field.elementType+ '/'+ element.uri, label : element.label };
			} );

			if( Nino.adminUi.isMultiElement( field ) === true ) {

				const list = Nino.adminUi.elementList( {
					key 		: key,
					label 	: label,
					value 	: Array.isArray( value ) ? value : [],
					limit 	: field.multiple,
					options : options,
					text 		: {
						search 		: Nino.content.getText('/_admin/elements/label/reference-search'),
						empty 		: Nino.content.getText( options.length === 0 ? '/_admin/elements/label/reference-empty' : '/_admin/elements/label/reference-list-empty' ),
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
				const store = list.querySelector('input[data-field]');

				// Only the asterisk: the control is a list and a search box, with no element a screen reader could be
				// told is required. Its first child is the name
				if( marked === true )
					Nino.admin.elements._star( list );

				return {
					el			: list,
					controls	: [ store ],
					read		: function() {
						try { return JSON.parse( store.value ) } catch( e ) { return [] }
					},
					empty		: function() { return Array.isArray( this.read() ) === false || this.read().length === 0 },
					focus		: function() { ( list.querySelector('.nino-admin-elementlist-search') ?? list.querySelector('button') ).focus() },
					error		: function( id, text ) { return Nino.adminUi.fieldError( list, null, id, text ) },
				};
			}

			const current = ( value === null || value === undefined ) ? '' : String( value );

			// Always offered, even on a required field: the empty state is what the check of a save needs to catch
			const choices = [ { value : '', label : Nino.content.getText( field.required === true ? '/_admin/elements/label/reference-choose' : '/_admin/elements/label/reference-none' ) } ].concat( options );

			// A reference whose target was deleted since keeps its value instead of silently resetting to none - a
			// save the editor did not intend would otherwise drop the reference for good
			if( current !== '' && options.some( function( e ) { return e.value === current } ) === false )
				choices.push( { value : current, label : current+ ' ('+ Nino.content.getText('/_admin/elements/label/reference-missing')+ ')' } );

			const row = Nino.adminUi.selectField( { key : key, label : label, help : help, value : current, options : choices, note : options.length === 0 ? Nino.content.getText('/_admin/elements/label/reference-empty') : undefined } );
			const control = row.querySelector('select');
			const handle = Nino.admin.elements._plain( key, field, row, control, marked );

			return handle;
		},

		/**
		 *	A list of texts is edited as a list of rows. Anything else a list can hold - objects, numbers, a list inside a
		 *	list - and a text that was typed and is not JSON stay in the JSON field, which can say it
		 *
		 *	@return		{Object}
		 */
		_arrayField : function( key, field, value, label, marked, help, fills ) {

			if( Nino.admin.elements._isStringList( value ) === true ) {

				const list = Nino.adminUi.stringList( {
					key 		: key,
					label 	: label,
					value 	: Array.isArray( value ) ? value : [],
					text 		: {
						add 		: Nino.content.getText('/_admin/elements/label/list-add'),
						remove 	: Nino.content.getText('/_admin/elements/label/reference-remove'),
						up 			: Nino.content.getText('/_admin/elements/label/reference-up'),
						down 		: Nino.content.getText('/_admin/elements/label/reference-down'),
						empty 	: Nino.content.getText('/_admin/elements/label/list-empty'),
						item 		: Nino.adminUi.format( Nino.content.getText('/_admin/elements/label/list-item'), label, '%d' ),
					},
				} );
				const store = list.querySelector('input[data-field]');

				// Only the asterisk, as on the list of references: the name is the control's first child, and a screen
				// reader is told which rows are the field's by the row names
				if( marked === true )
					Nino.admin.elements._star( list );

				if( field.suffix )
					list.appendChild( Nino.adminUi.el( 'span', 'nino-admin-field-suffix', field.suffix ) );

				return {
					el			: list,
					controls	: [ store ],
					read		: function() {
						try { return JSON.parse( store.value ) } catch( e ) { return [] }
					},
					empty		: function() { return this.read().length === 0 },
					focus		: function() { ( list.querySelector('input[type="text"]') ?? list.querySelector('.nino-admin-stringlist-add') ).focus() },
					error		: function( id, text ) { return Nino.adminUi.fieldError( list, null, id, text ) },
				};
			}

			// A list of anything else, and an object: its JSON, kept as it is stored
			const row = Nino.adminUi.codeField( { key : key, label : label, help : help, fills : fills, required : marked, value : JSON.stringify( value ?? [] ) } );
			const control = row.querySelector('textarea');
			const handle = Nino.admin.elements._plain( key, field, row, control, false );

			handle.read = function() { return Nino.admin.elements._parseArray( control.value ).value };
			// What was typed counts, valid or not: typing into a field that is already wrong is an edit
			handle.compare = function() {
				const parsed = Nino.admin.elements._parseArray( control.value );
				return JSON.stringify( parsed.ok === true ? parsed.value : { invalid : control.value } );
			};
			handle.empty = function() {
				const parsed = Nino.admin.elements._parseArray( control.value );
				return parsed.ok === true && Nino.admin.elements._isEmptyCollection( parsed.value );
			};
			handle.invalid = function() { return Nino.admin.elements._parseArray( control.value ).ok === false };

			return handle;
		},

		/**
		 *	An image: the file field of the guide - a button, the name and size of the chosen file, the limits as its note -
		 *	with the picture beside it and "Remove image" where there is one. It uploads at once, as soon as a file is
		 *	chosen, and says so as a toast: the server commits it to the element and deletes the previous file only once
		 *	the new one is safely written, so a replaced or discarded upload can never leave an orphaned file. It needs a
		 *	saved element (a uri to attach the upload to), so it waits for the first save on a new one
		 *
		 *	@return		{Object}
		 */
		_imageField : function( key, field, value, label, help ) {

			const elements = Nino.admin.elements;
			const state = { value : value ?? '' };
			const limits = Nino.adminUi.uploadHint();
			const dimensions = field.width && field.height ? Nino.content.getText( field.fit === true ? '/_admin/elements/label/image-largest' : '/_admin/common/label/image-target' )+ ' '+ field.width+ ' × '+ field.height+ ' px' : '';

			const file = Nino.adminUi.fileField( {
				key			: key,
				label		: label,
				help		: help,
				note		: elements._isNew === true ? Nino.content.getText('/_admin/elements/note/image-savefirst') : [ dimensions, limits === null ? '' : limits.textContent ].filter( function( part ) { return part !== '' } ).join('. '),
				accept	: 'image/*',
				maxBytes : Nino.adminUi.limits().bytes,
				onChange : function( chosen ) {
					if( chosen !== null )
						elements._uploadImage( key, chosen, view );
				},
			} );

			const preview = dc.createElement('img');
			preview.className = 'nino-admin-field-image-preview';
			// Empty: the picture stands beside the name of the field, which names it, and a description would become
			// part of the name of the file button as well
			preview.alt = '';
			preview.hidden = ! state.value;
			// Every uploaded image lives under /images (Nino\Images::UPLOAD_DIR), and the stored value is the filename
			// relative to it - the same url \Nino\Images::getUrl() builds server-side, which _uploadImage() renders
			// straight from the response
			if( state.value )
				preview.src = Nino.admin.publicUrl( '/images/'+ state.value );

			// The field is written empty and its file deleted, right away like an upload: only there is an image to take away
			const removeBtn = Nino.adminUi.button( Nino.content.getText('/_admin/elements/label/image-remove'), function() { elements._removeImage( key, label, view ) }, { kind : 'danger', small : true } );
			removeBtn.hidden = ! state.value;

			const shown = Nino.adminUi.el( 'div', 'nino-admin-field-image' );
			shown.appendChild( preview );
			shown.appendChild( removeBtn );

			// A picture below the target size is saved, and that stays said at the field
			const warning = Nino.adminUi.notice('');
			warning.hidden = true;

			file.el.insertBefore( shown, file.el.children[1] );
			file.el.appendChild( warning );

			// A new element has nothing to attach the file to yet
			if( elements._isNew === true )
				file.button.disabled = true;

			const view = { state : state, file : file, preview : preview, remove : removeBtn, warning : warning };

			return {
				el			: file.el,
				controls	: [ file.button ],
				view		: view,
				read		: function() { return state.value },
				empty		: function() { return state.value === '' },
				compare	: function() { return ''; },
				error		: function( id, text ) { file.error( text ); return null },
				clear		: function() { file.clearError() },
				focus		: function() { file.button.focus() },
			};
		},

		/**
		 *	The error of a field of the form on screen is gone: the sentence, and what marks the control
		 *
		 *	@param		{string}	key						A model key, or '.uri'
		 *
		 *	@return		void
		 */
		_clearError : function( key ) {

			const sentence = dc.getElementById( 'elements-error-'+ key );

			if( sentence !== null )
				sentence.remove();

			if( key === '.uri' ) {
				const input = dc.getElementById('elements-form-uri');
				if( input !== null )
					Nino.adminUi.clearFieldErrors( input.closest('label') ?? input );
				return;
			}

			if( Nino.admin.elements._fields[key] !== undefined )
				Nino.admin.elements._fields[key].clear();
		},

		/**
		 *	The model keys of the form on screen, in the order the fields stand in it
		 *
		 *	@return		{Array<string>}
		 */
		_keys : function() {
			return Nino.admin.elements._globalKeys.concat( Nino.admin.elements._localeKeys );
		},

		/**
		 *	Everything that holds this save back, in the order the form shows it: the uri of a new element, then the
		 *	global fields in model order and the fields of the language on screen. Each problem is { key, kind, label } -
		 *	key is '.uri' for the uri, kind is 'required' for an empty required field, 'json' for a list or object text
		 *	that does not parse, 'number' for a number control that holds no number, 'uri' for an element name that is no
		 *	slug and 'taken' for one the type has already.
		 *
		 *	An image field is never among them, even if its model says required: its file is uploaded separately, only
		 *	once the element exists and has a uri to attach the upload to, so on a new element it is empty by
		 *	construction - holding the save back for it would make the element impossible to create. Same rule
		 *	\Nino\Elements applies on insert, and the one the Element Types tab enforces when writing a model.
		 *	Nor is a field this account may not write: the save leaves it out, and it can be neither filled in nor focused
		 *
		 *	@return		{Array<Object>}
		 */
		_validate : function() {

			const elements = Nino.admin.elements;
			const model = elements._currentModel;
			const problems = [];

			const uriInput = dc.getElementById('elements-form-uri');

			if( elements._isNew === true && elements._isNumbered() === false && uriInput !== null ) {

				const uri = uriInput.value.trim();
				const label = Nino.content.getText('/_admin/elements/label/uri');

				if( uri === '' )
					problems.push( { key : '.uri', kind : 'required', label : label } );
				else if( /^[A-Za-z0-9][A-Za-z0-9_-]*$/.test( uri ) === false )
					problems.push( { key : '.uri', kind : 'uri', label : label } );
				else if( elements._elements.some( function( element ) { return element.uri === uri } ) === true )
					problems.push( { key : '.uri', kind : 'taken', label : label } );
			}

			const untouched = elements._untouchedLocale();

			elements._keys().forEach( function( key ) {

				const definition = model[key];
				const handle = elements._fields[key];

				if( untouched === true && elements._localeKeys.indexOf( key ) !== -1 )
					return;

				if( handle === undefined || definition.type === 'image' || definition.type === 'boolean' || elements._mayUpdate( key ) === false )
					return;

				let kind = null;

				if( typeof handle.badInput === 'function' && handle.badInput() === true )
					kind = 'number';
				else if( typeof handle.invalid === 'function' && handle.invalid() === true )
					kind = 'json';
				else if( definition.required === true && handle.empty() === true )
					kind = 'required';

				if( kind !== null )
					problems.push( { key : key, kind : kind, label : elements._fieldLabel( key ) } );
			} );

			return problems;
		},

		/**
		 *	The sentence that says one problem at its field, in the words of the guide: "<Name> is missing.", "<Name> is
		 *	not valid: <what is expected>.", "<Name> is already taken."
		 *
		 *	@param		{Object}	problem				One of _validate()
		 *
		 *	@return		{string}
		 */
		_sentence : function( problem ) {

			const invalid = function( expected ) {
				return Nino.adminUi.format( Nino.content.getText('/_admin/elements/error/invalid'), problem.label, Nino.content.getText( expected ) );
			};

			switch( problem.kind ) {
				case 'json':
					return invalid('/_admin/elements/expect/json');
				case 'number':
					return invalid('/_admin/elements/expect/number');
				case 'uri':
					return invalid('/_admin/elements/expect/uri');
				case 'taken':
					return Nino.adminUi.format( Nino.content.getText('/_admin/elements/error/taken'), problem.label );
				default:
					return Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), problem.label );
			}
		},

		/**
		 *	Say where a save was refused: every problem gets its sentence at its field, aria-invalid and aria-describedby,
		 *	the line of the bar says once that something is wrong, and the focus goes to the first field. The sentences
		 *	are not alerts - a form with six empty fields would announce six at once - the line is announced, and focus
		 *	plus aria-describedby tell the rest where the person is standing
		 *
		 *	@param		{Array<Object>}	problems			What _validate() found
		 *
		 *	@return		void
		 */
		_showProblems : function( problems ) {

			const elements = Nino.admin.elements;

			elements._clearErrors();

			problems.forEach( function( problem ) {
				elements._say( problem.key, elements._sentence( problem ) );
			} );

			if( problems.length === 0 )
				return;

			elements._status.fail( Nino.content.getText('/_admin/elements/error/fields') );
			elements._focusField( problems[0].key );
		},

		/**
		 *	Put one sentence at the field of a key, or at the uri
		 *
		 *	@param		{string}	key						A model key, or '.uri'
		 *	@param		{string}	text
		 *
		 *	@return		{boolean}									Whether the form has the field
		 */
		_say : function( key, text ) {

			const id = 'elements-error-'+ key;

			if( dc.getElementById( id ) !== null )
				return true;

			if( key === '.uri' ) {

				const input = dc.getElementById('elements-form-uri');

				if( input === null || input.type === 'hidden' )
					return false;

				Nino.adminUi.fieldError( input.closest('label'), input, id, text );
				return true;
			}

			const handle = Nino.admin.elements._fields[key];

			if( handle === undefined )
				return false;

			handle.error( id, text );
			return true;
		},

		/**
		 *	The focus to the field of a key, or to the uri
		 *
		 *	@param		{string}	key
		 *
		 *	@return		void
		 */
		_focusField : function( key ) {

			if( key === '.uri' ) {
				const input = dc.getElementById('elements-form-uri');
				if( input !== null && input.type !== 'hidden' )
					input.focus();
				return;
			}

			if( Nino.admin.elements._fields[key] !== undefined )
				Nino.admin.elements._fields[key].focus();
		},

		/**
		 *	Take every sentence of a refused save off the form on screen
		 *
		 *	@return		void
		 */
		_clearErrors : function() {

			const form = dc.getElementById('elements-edit-form');

			if( form === null )
				return;

			Nino.adminUi.clearFieldErrors( form );
			Object.keys( Nino.admin.elements._fields ).forEach( function( key ) { Nino.admin.elements._fields[key].clear() } );
		},

		/**
		 *	The server refused a save for fields - the answer's `fields`, which a callback of the write names
		 *	(\Nino\Elements::refuse()), or its `field`, which says what one sentence is about: each is said at its field
		 *	like a problem the form found itself - the sentence under the field, aria-invalid, aria-describedby - and the
		 *	focus goes to the first. The line of the bar says the first sentence as it does for every failed request. A
		 *	refusal that names no field of the form stays in the line
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response			Parsed response body of the refused save
		 *
		 *	@return		void
		 */
		_refuse : function( status, response ) {

			const elements = Nino.admin.elements;
			const body = response !== null && typeof response === 'object' ? response : {};
			const named = [];

			if( Array.isArray( body.fields ) === true ) {
				body.fields.forEach( function( item ) {

					if( item === null || typeof item !== 'object' || typeof item.field !== 'string' || typeof item.error !== 'string' || item.error === '' )
						return;

					// The words of the workbench for a code it knows, the callback's own sentence otherwise
					const known = typeof item.code === 'string' && /^[a-z][a-z0-9_-]*$/.test( item.code ) === true ? Nino.content.getText( '/_admin/error/'+ item.code ) : '';

					named.push( { key : item.field, text : known !== '' ? known : item.error } );
				} );
			}
			else if( typeof body.field === 'string' && body.field !== '' )
				named.push( { key : body.field, text : Nino.adminUi.api.errorText( status, response, '/_admin/elements/error/save' ) } );

			const drawn = named.filter( function( item ) { return elements._say( item.key, item.text ) } );

			if( drawn.length > 0 )
				elements._focusField( drawn[0].key );
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
		 *	Let go of everything a form on screen carries besides its values: the
		 *	baselines its controls are compared with, the copy that nobody has
		 *	saved. Called wherever a form is opened or dropped
		 *
		 *	@return		void
		 */
		_resetEdits : function() {
			Nino.admin.elements._copy			= false;
			Nino.admin.elements._baseline	= { uri : null, fields : {} };
		},

		/**
		 *	What the uri and the fields hold now, as the baseline the form is compared with. Not an image (an upload
		 *	commits it by itself) and not a field this account may not write
		 *
		 *	@return		void
		 */
		_captureBaseline : function() {

			const uri = dc.getElementById('elements-form-uri');

			Nino.admin.elements._baseline = { uri : uri === null || uri.type === 'hidden' ? null : uri.value.trim(), fields : {} };

			Nino.admin.elements._keys().forEach( function( key ) {
				if( Nino.admin.elements._counts( key ) === true )
					Nino.admin.elements._baseline.fields[key] = Nino.admin.elements._fields[key].compare();
			} );
		},

		/**
		 *	Whether what is typed into a field is input of the form: not an image, and not a field this account may not write
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{boolean}
		 */
		_counts : function( key ) {
			return Nino.admin.elements._fields[key] !== undefined && Nino.admin.elements._currentModel[key].type !== 'image' && Nino.admin.elements._mayUpdate( key ) === true;
		},

		/**
		 *	Whether the form on screen holds input nobody has saved - what the shell asks before it lets anything throw
		 *	that away (see Nino.admin.dirty). Only an open form can: a list or the type picker has nothing typed into it.
		 *	It is when the form is a copy, or a control differs from what it held when it was drawn or last saved
		 *
		 *	@return		{boolean}
		 */
		isDirty : function() {

			const form = dc.getElementById('elements-form');

			if( form === null || Nino.admin.elements._currentType === null || form.classList.contains('admin-hidden') === true || dc.getElementById('elements-edit-form') === null )
				return false;

			if( Nino.admin.elements._copy === true )
				return true;

			const uri = dc.getElementById('elements-form-uri');
			if( uri !== null && uri.type !== 'hidden' && Nino.admin.elements._baseline.uri !== null && uri.value.trim() !== Nino.admin.elements._baseline.uri )
				return true;

			return Object.keys( Nino.admin.elements._baseline.fields ).some( function( key ) {
				return Nino.admin.elements._fields[key] !== undefined && Nino.admin.elements._fields[key].compare() !== Nino.admin.elements._baseline.fields[key];
			} );
		},

		/**
		 *	Throw the input away: what the controls show now counts as saved. The form is about to be left or drawn
		 *	again, which is what makes that honest. If the exit then fails without drawing the form (a refused
		 *	request), the controls still show the discarded text and the next Save writes it. While a save runs
		 *	nothing is thrown away: it finishes with what it was given
		 *
		 *	@return		void
		 */
		discard : function() {

			if( Nino.admin.elements._saving === true )
				return;

			Nino.admin.elements._copy = false;
			Nino.admin.elements._captureBaseline();
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
		 *	Disable the form while its save is in flight. Besides preventing a duplicate submit, this freezes the
		 *	way out of it, so a callback can never be applied to another element
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
			// A read-only field stays read-only through a save: re-enabling everything afterwards would hand back
			// exactly the fields this account may not write
			wrap.querySelectorAll('input, textarea, select, button').forEach( function( el ) {
				el.disabled = pending || el.closest('.admin-field-readonly') !== null;
			} );
			// The two steps at the end of the list stay disabled after the save, like the read-only fields do
			if( pending === false )
				Nino.admin.elements._paintSteps();
			// The back link is one of them: a click on it would ask about input the running save is already sending
			wrap.querySelectorAll('a').forEach( function( el ) {
				// Links inside a rich-text editor are its content: getValue() returns them, so writing attributes onto
				// them would turn the field's value into a change
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
		 *	The workbench's content language moved (Nino.admin.workLocale): the form on screen draws itself again in the
		 *	other language - what it held was saved or thrown away by the question the shell asked first - and the list
		 *	on screen shows its cells in it. Anything else - the picker of the types, a level that is not drawn - reads
		 *	the language when it is drawn. A form of a type with no field per language, and a language this panel has
		 *	no field for (one the website does not serve yet), stay as they are
		 *
		 *	@param		{string}	locale
		 *
		 *	@return		void
		 */
		_followLocale : function( locale ) {

			const visible = function( id ) {
				const level = dc.getElementById( id );
				return level !== null && level !== undefined && level.classList.contains('admin-hidden') === false;
			};

			if( visible('elements-form') === true && dc.getElementById('elements-edit-form') !== null ) {

				if( Nino.admin.elements._localeKeys.length > 0 && Nino.admin.elements._locales.indexOf( locale ) !== -1 && Nino.admin.elements._formLocale() !== Nino.admin.elements._locale ) {
					Nino.admin.elements._renderForm();
					Nino.adminUi.showLevel( 'elements-form', { focus : false } );
				}
				else
					Nino.admin.elements._paintBadge( dc.getElementById('elements-form').querySelector('.nino-admin-contextbar'), Nino.admin.elements._locale );

				return;
			}

			if( visible('elements-list') === true && Nino.admin.elements._currentType !== null )
				Nino.admin.elements._refreshList();
		},

		/**
		 *	The heading of the form: the title of the element in the language on screen, or its uri where the type
		 *	has no title field or it is empty for that language yet
		 *
		 *	@param		{Object}	[values]			The values of the language on screen
		 *
		 *	@return		{string}
		 */
		_headingText : function( values ) {

			if( Nino.admin.elements._isNew === true )
				return Nino.content.getText('/_admin/elements/label/add');

			const title = ( values ?? {} ).title;

			return ( title !== undefined && title !== null && title !== '' ) ? String( title ) : Nino.admin.elements._currentUri;
		},

		/**
		 *	Render the full edit/create form for the current element: the context bar with the way back, the name and
		 *	identifier of the element, the language badge and the two steps; the fields of every language in one section
		 *	and those of the language on screen in another; the raw storage for whoever manages the type; and the bar -
		 *	"Delete element" at the left, the side actions and ONE main button, "Save" (or "Create element") at the right
		 *
		 *	@return		void
		 */
		_renderForm : function() {

			const elements = Nino.admin.elements;

			elements._destroyHtmlEditors();
			elements._fields = {};

			const wrap = dc.getElementById('elements-form');
			wrap.innerHTML = '';

			const locale = elements._formLocale();
			const values = elements._localeValues[locale] ?? {};
			const withLocale = elements._localeKeys.length > 0;
			elements._locale = locale;

			const bar = Nino.adminUi.contextBar( {
				back	: { to : elements._currentTypeTitle, onClick : function() {
					if( elements._saving === true )
						return;
					elements._destroyHtmlEditors();
					Nino.admin.router.go( 'elements', [ elements._currentType ] );
					elements._showList( elements._backTarget() );
					// The form shows the language of the workbench, which may have moved since the list was read
					if( elements._listLocale !== elements._workingLocale( '' ) )
						elements._refreshList();
				} },
				title	: elements._headingText( values ),
				code	: elements._isNew === true ? '' : '/'+ elements._currentType+ '/'+ elements._currentUri,
				// Stepping through the type's elements from the form itself. A new element is not in the list yet, so
				// there is nothing to step from
				icons	: elements._isNew === true ? [] : elements._stepButtons(),
			} );
			wrap.appendChild( bar );

			if( withLocale === true )
				elements._paintBadge( bar, locale );

			const form = dc.createElement('form');
			form.id = 'elements-edit-form';
			form.noValidate = true;

			// The fields of every language: the uri of a new element, which is how it gets its identifier, and the
			// fields the type does not write per language
			if( elements._globalKeys.length > 0 || elements._isNew === true ) {

				const globalWrap = dc.createElement('fieldset');
				globalWrap.id = 'elements-form-global';
				globalWrap.appendChild( Nino.adminUi.el( 'legend', '', Nino.content.getText( withLocale === true ? '/_admin/elements/label/global' : '/_admin/elements/label/content' ) ) );

				if( elements._isNew === true )
					globalWrap.appendChild( elements._uriField() );
				else
					form.appendChild( elements._hiddenUri() );

				elements._globalKeys.forEach( function( key ) {
					elements._fields[key] = elements._makeField( key, elements._currentModel[key], elements._globalValues[key] ?? null );
					globalWrap.appendChild( elements._fields[key].el );
				} );

				form.appendChild( globalWrap );
			}
			else
				form.appendChild( elements._hiddenUri() );

			// The fields of the language on screen
			if( withLocale === true ) {

				const localeWrap = dc.createElement('fieldset');
				localeWrap.id = 'elements-form-locale';
				localeWrap.appendChild( Nino.adminUi.el( 'legend', '', Nino.adminUi.format( Nino.content.getText('/_admin/elements/label/language'), Nino.adminUi.localeName( locale ) ) ) );

				elements._localeKeys.forEach( function( key ) {
					elements._fields[key] = elements._makeField( key, elements._currentModel[key], values[key] ?? null );
					localeWrap.appendChild( elements._fields[key].el );
				} );

				form.appendChild( localeWrap );
			}

			// What is actually on disk, folded away - a manager's view
			const raw = elements._renderRaw();
			if( raw !== null )
				form.appendChild( raw );

			// The bar of every form (actionBar()): Delete at the left, the state with its line, what else is done beside
			// the one main button at the right edge. The Tab key follows the markup, so the markup follows the eye
			const msg = dc.createElement('p');
			msg.id = 'elements-form-msg';

			const secondary = [];

			// Entering several elements of a type in a row: the same create, and a blank form after it where the list
			// would be. An element that is saved has nothing to add another to
			if( elements._isNew === true ) {
				const againBtn = Nino.adminUi.button( Nino.content.getText('/_admin/elements/label/create-again'), function() { elements._saveAndAdd() }, { kind : 'secondary', id : 'elements-form-save-again' } );
				secondary.push( againBtn );
			}

			// Duplicating ends in a new element, so it is offered exactly where adding one is - and only on a saved
			// element, which is the only thing there is to copy
			if( elements._isNew === false && elements._mayInsert() === true )
				secondary.push( Nino.adminUi.button( Nino.content.getText('/_admin/elements/label/duplicate'), function() { elements._duplicate() }, { kind : 'secondary' } ) );

			const actions = Nino.adminUi.actionBar( {
				danger		: elements._isNew === false && elements._mayDelete() === true ? Nino.adminUi.button( Nino.content.getText('/_admin/elements/label/delete'), function() { elements._delete() }, { kind : 'danger' } ) : null,
				status		: { line : msg, fresh : elements._isNew },
				secondary	: secondary,
				primary		: Nino.adminUi.button( Nino.content.getText( elements._isNew === true ? '/_admin/elements/label/create' : '/_admin/common/label/save' ), null, { type : 'submit' } ),
			} );
			actions.el.id = 'elements-form-actions';

			elements._bar = actions;
			form.appendChild( actions.el );

			// Whether what is on screen is saved: any edit turns the badge and the line into "changed", and a save says
			// which of the two it ended in
			elements._status = actions.line;
			elements._status.bind( form, elements.isDirty );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); elements._save() } );

			// What is typed into a field lets go of what the form said was wrong with it
			const letGo = function( ev ) {

				if( ev === undefined || ev === null || ev.target === null || typeof ev.target !== 'object' )
					return;

				// A file chosen is no text typed: the file field says what is wrong with it in the same event, and that sentence stays
				if( ev.target.type === 'file' )
					return;

				const uri = dc.getElementById('elements-form-uri');

				if( uri !== null && ev.target === uri )
					elements._clearError('.uri');

				Object.keys( elements._fields ).forEach( function( key ) {
					if( elements._within( elements._fields[key].el, ev.target ) === true )
						elements._clearError( key );
				} );
			};

			form.addEventListener( 'input', letGo );
			form.addEventListener( 'change', letGo );

			wrap.appendChild( form );

			elements._paintSteps();
			elements._captureBaseline();
			elements._refreshDirty();
		},

		/**
		 *	The uri of the element that is open, which the form reads back when it saves: nothing a person types into
		 *
		 *	@return		{Element}
		 */
		_hiddenUri : function() {

			const input = Nino.adminUi.el('input');

			input.type = 'hidden';
			input.id = 'elements-form-uri';
			input.value = Nino.admin.elements._currentUri;

			return input;
		},

		/**
		 *	The field of the uri of a new element: asked for where the type does not number its elements, else shown
		 *	as the number the next one gets, locked. The control is the form's own #elements-form-uri either way - an
		 *	empty uri is exactly what tells the backend to allocate the next number
		 *
		 *	@return		{Element}
		 */
		_uriField : function() {

			const elements = Nino.admin.elements;

			if( elements._isNumbered() === true ) {

				const row = Nino.adminUi.textField( { id : 'elements-form-uri', label : Nino.content.getText('/_admin/elements/label/uri'), help : Nino.content.getText('/_admin/elements/help/uri-numbered'), value : '' } );
				const input = row.querySelector('input');

				// The number the element will get is what its field says, and nothing is sent for it
				input.placeholder = '/'+ elements._currentType+ '/'+ elements._nextUri();
				input.disabled = true;
				input.dataset.dirty = 'ignore';

				return row;
			}

			const row = Nino.adminUi.textField( { id : 'elements-form-uri', label : Nino.content.getText('/_admin/elements/label/uri'), help : Nino.content.getText('/_admin/elements/help/uri'), required : true, value : '', autocomplete : 'off' } );

			row.querySelector('input').dataset.field = '.uri';

			return row;
		},

		/**
		 *	Whether a node stands in an element, the element itself included
		 *
		 *	@param		{Object}		ancestor
		 *	@param		{Object}		node
		 *
		 *	@return		{boolean}
		 */
		_within : function( ancestor, node ) {

			for( let at = node; at !== null && at !== undefined; at = at.parentNode )
				if( at === ancestor )
					return true;

			return false;
		},

		/**
		 *	The element's storage buckets as they are on disk, read-only and folded away: '*' holds the global fields
		 *	every locale shares, each locale bucket only that locale's own values, and a locale missing here falls
		 *	back to '*'. Nothing to show for a new element
		 *
		 *	@return		{Element|null}
		 */
		_renderRaw : function() {

			const buckets = Object.keys( Nino.admin.elements._raw || {} );

			if( buckets.length === 0 )
				return null;

			const content = Nino.adminUi.el( 'div', 'elements-raw' );

			buckets.forEach( function( bucket ) {
				// textContent, never innerHTML - stored values are arbitrary content and must never be parsed as markup here
				const field = Nino.adminUi.codeField( { key : 'raw-'+ bucket, label : bucket, rows : 6, value : JSON.stringify( Nino.admin.elements._raw[bucket], null, 2 ) } );
				const area = field.querySelector('textarea');

				area.readOnly = true;
				area.dataset.dirty = 'ignore';
				content.appendChild( field );
			} );

			const drawer = Nino.adminUi.accordion( { id : 'elements-form-raw', collapsible : true, sections : [ { key : 'raw', label : Nino.content.getText('/_admin/elements/label/raw')+ ' ('+ buckets.join(', ')+ ')', content : content } ] } );

			return drawer.el;
		},

		/**
		 *	Whether the open element holds any text in a language: the server answers every language of the site for an
		 *	element that exists, with null for each field it has nothing of
		 *
		 *	@param		{string}	locale
		 *
		 *	@return		{boolean}
		 */
		_hasText : function( locale ) {

			const values = Nino.admin.elements._localeValues[locale];

			return values !== undefined && Nino.admin.elements._localeKeys.some( function( key ) { return values[key] !== null && values[key] !== undefined } );
		},

		/**
		 *	Whether the language on screen of an element that exists is left as it was drawn: the element has no text in it
		 *	and no field of it was typed into. Such a language is neither checked nor written - opening an element in a
		 *	language it does not have to change a field every language shares must not ask for a text there
		 *
		 *	@return		{boolean}
		 */
		_untouchedLocale : function() {

			const elements = Nino.admin.elements;

			return elements._isNew === false && elements._hasText( elements._locale ) === false && elements._localeKeys.every( function( key ) {
				return elements._counts( key ) === false || elements._fields[key].compare() === elements._baseline.fields[key];
			} );
		},

		/**
		 *	The fields of the form on screen that are to be written. A new element is written whole. An element that exists
		 *	is written as far as it was changed: every field is sent only where its control holds something other than
		 *	what it held when it was drawn or last saved - the fields every language shares as well as those of the language on
		 *	screen - so that a save never writes back what somebody else changed meanwhile in a field nobody here touched,
		 *	and a form saved without a change writes nothing. A read-only field is never sent
		 *
		 *	@return		{Object}
		 */
		_collect : function() {

			const elements = Nino.admin.elements;
			const fields = {};

			elements._keys().filter( function( key ) {
				return elements._mayUpdate( key ) === true && ( elements._isNew === true || ( elements._counts( key ) === true && elements._fields[key].compare() !== elements._baseline.fields[key] ) );
			} ).forEach( function( key ) { fields[key] = elements._fields[key].read() } );

			return fields;
		},

		/**
		 *	Collect the form's current values and save (insert or update) the element, in the language on screen
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

			const elements = Nino.admin.elements;

			const report = function( ok ) {
				elements._refreshDirty();
				if( typeof done === 'function' )
					done( ok );
			};

			if( elements._saving === true || elements._status === null ) {
				report( false );
				return;
			}

			// Nothing is sent while something holds the save back: the problems are said at their fields, with the focus
			// on the first (see _validate())
			const problems = elements._validate();
			if( problems.length > 0 ) {
				elements._showProblems( problems );
				report( false );
				return;
			}

			elements._clearErrors();

			// A numbered type is saved with no uri on purpose - that is what asks the backend for the next number.
			// Reassigned below, once the insert says which one it got, so the rest of this same save addresses the
			// element that now exists
			let uri = dc.getElementById('elements-form-uri').value.trim();

			const locale = elements._locale;
			const wasNew = elements._isNew;
			const type = elements._currentType;
			const fields = elements._collect();

			// The copy of an element carries every language the original has: they are written after the one on screen
			const others = elements._copy === true ? Object.keys( elements._localeValues ).filter( function( code ) { return code !== locale && elements._hasText( code ) } ) : [];

			// Nothing was changed in an element that exists: there is nothing to write, and the file stays as it is
			if( wasNew === false && Object.keys( fields ).length === 0 ) {
				elements._status.saved();
				report( true );
				return;
			}

			// A control that is shut while the request runs loses the focus: it is given back when the form is open again
			const held = dc.activeElement ?? null;
			const refocus = function() {
				if( held !== null && held.isConnected !== false && typeof held.focus === 'function' )
					held.focus();
			};

			elements._saving = true;
			elements._setFormPending( true );
			elements._status.saving();

			// What the server holds of the locale of this answer, and of the globals, after it
			const take = function( response, code ) {

				elements._globalKeys.forEach( function( key ) { elements._globalValues[key] = response.element[key] ?? null } );
				elements._localeValues[code] = elements._localeValues[code] ?? {};
				elements._localeKeys.forEach( function( key ) { elements._localeValues[code][key] = response.element[key] ?? null } );
			};

			const failed = function( status, response, created ) {

				// The first request may already have created the element before a later language failed. Reflect that
				// durable state immediately so a retry updates the element rather than attempting a second insert
				if( created === true ) {
					elements._copy = false;
					elements._renderForm();
					Nino.admin.router.set( 'elements', [ type, elements._currentUri ] );
				}

				elements._saving = false;
				elements._setFormPending( false );
				elements._status.error( status, response, '/_admin/elements/error/save' );
				refocus();
				elements._refuse( status, response );
				report( false );
			};

			const finish = function() {

				elements._copy = false;

				// Stay on the form after saving. A newly-created element is drawn once more to lock its uri and expose
				// the delete action, and the focus goes to its main button; an existing one gets the focus back on the
				// control that had it - what its controls hold is what is saved now. The baseline is taken once the form
				// is enabled again, like Text and Keys do
				if( wasNew === true ) {
					elements._renderForm();
					Nino.admin.router.set( 'elements', [ type, elements._currentUri ] );
				}

				elements._saving = false;
				elements._setFormPending( false );

				if( wasNew !== true ) {
					elements._captureBaseline();
					refocus();
				}
				else
					elements._bar.primary.focus();

				elements._status.saved();
				elements._refreshList();
				report( true );
			};

			const saveOthers = function( position ) {

				if( position >= others.length ) {
					finish();
					return;
				}

				const code = others[position];
				const localeFields = {};

				Object.keys( elements._localeValues[code] ?? {} )
					.filter( function( key ) { return elements._mayUpdate( key ) } )
					.forEach( function( key ) { localeFields[key] = elements._localeValues[code][key] } );

				elements._apiCall( 'save', { type : type, uri : uri, locale : code, isNew : false, fields : localeFields }, function( status, response ) {

					if( status !== 200 || response === null ) {
						failed( status, response, wasNew );
						return;
					}

					take( response, code );
					saveOthers( position + 1 );
				} );
			};

			elements._apiCall( 'save', { type : type, uri : uri, locale : locale, isNew : wasNew, fields : fields }, function( status, response ) {

				if( status !== 200 || response === null ) {
					failed( status, response, false );
					return;
				}

				// Written, whatever a later language does: what the panels that show elements hold is no longer what
				// the server has
				Nino.admin.touch( 'elements' );

				if( wasNew === true ) {
					elements._isNew = false;
					// A numbered type only knows its uri now. Every element the kernel returns carries the '.uri' it was
					// actually written under, so take it from there rather than assuming
					const assigned = String( response.element['.uri'] ?? '' ).split('/').pop();
					if( assigned !== '' )
						uri = assigned;
					elements._currentUri = uri;

					// This insert consumed a number, so the next form's promise has to move with it (the backend
					// reports the new one - the padding is the kernel's to decide, not this file's)
					if( response.nextUri )
						elements._numbered[type] = response.nextUri;
				}

				take( response, locale );

				// A number field that was sent empty comes back with the model's default where it has one, so its control
				// shows what the server holds before the baseline is taken
				Object.keys( fields ).forEach( function( key ) {

					const definition = elements._currentModel[key];

					if( fields[key] !== null || definition === undefined || ( definition.type !== 'integer' && definition.type !== 'double' ) || elements._fields[key] === undefined )
						return;

					elements._fields[key].controls[0].value = String( response.element[key] ?? '' );
				} );

				saveOthers( 0 );
			} );
		},

		/**
		 *	Save the form of a new element exactly as Save does and, when everything was written, open a blank form of
		 *	the same type: entering several elements in a row. The blank form says what was saved in its line and has the
		 *	focus in its first field, where the next element is typed. A save that was refused or failed stays where it
		 *	is, with its errors
		 *
		 *	@return		void
		 */
		_saveAndAdd : function() {
			Nino.admin.elements._save( function( ok ) {
				if( ok === true )
					// Not opened for a person to walk into, whose form starts at its way out: the focus makes one move,
					// from where the save left it to the field
					Nino.admin.elements._openForm( null, function() {
						Nino.admin.elements._status.saved();
						Nino.admin.elements._focusFirstField();
					} );
			} );
		},

		/**
		 *	Put the focus on the first field of the form that is open - the uri of a new element that is typed, else the
		 *	first control of its fields that can be typed into - and on its way back where it has none. A locked
		 *	control takes no focus, nor does one in a part that is hidden. The control of a rich text is its text box:
		 *	the input of its editor is the link bar's, hidden until a link is made, and a call on that moves nothing
		 *
		 *	@return		void
		 */
		_focusFirstField : function() {

			const form = dc.getElementById('elements-edit-form');
			const controls = form === null ? [] : Array.from( form.querySelectorAll('input:not([type="hidden"]), textarea, select, [contenteditable="true"]') );
			const target = controls.find( function( control ) { return control.disabled !== true && control.closest('[hidden]') === null } ) ?? dc.getElementById('elements-form').querySelector('.nino-admin-back-link');

			if( target !== null )
				target.focus();
		},

		/**
		 *	Upload a new image for one "image" field, at once - the server commits it straight to the element record and
		 *	deletes the previous file, so there's nothing left to do here beyond reflecting the result. A file the server
		 *	cannot take is refused here, with the reason, at the field; the picture that was there stays
		 *
		 *	@param		{string}	key							Field key
		 *	@param		{File}		file						Chosen file
		 *	@param		{Object}	view						The parts of the field: { state, file, preview, remove, warning }
		 *
		 *	@return		void
		 */
		_uploadImage : function( key, file, view ) {

			view.file.clearError();
			view.warning.hidden = true;
			view.file.button.disabled = true;

			// The record the file belongs to is taken now: the check below waits for the picture to decode, and the
			// person may step to another element or language meanwhile
			const target = {
				type 		: Nino.admin.elements._currentType,
				uri 		: Nino.admin.elements._currentUri,
				locale 	: Nino.admin.elements._locale,
				key 		: key,
			};

			Nino.adminUi.checkImage( file, function( rejection ) {

				if( rejection !== null ) {
					view.file.button.disabled = false;
					view.file.clear();
					view.file.error( Nino.adminUi.api.errorText( 400, rejection ) );
					return;
				}

				Nino.admin.elements._apiCall( 'uploadimage', target, function( status, response ) {

					view.file.button.disabled = false;
					view.file.clear();

					if( status !== 200 || response === null ) {
						view.file.error( Nino.adminUi.api.errorText( status, response, '/_admin/elements/error/save' ) );
						return;
					}

					Nino.admin.touch( 'elements' );

					view.state.value = response.filename;
					view.preview.src = response.url;
					view.preview.hidden = false;
					view.remove.hidden = false;

					// Saved either way, and the toast says it - but a picture below the target size was scaled up, which stays said at
					// the field: its size is all the sentence there names
					if( response.belowTarget === true && response.source ) {
						view.warning.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/elements/msg/image-below-target'), response.source.width+ ' × '+ response.source.height+ ' px' );
						view.warning.hidden = false;
					}

					Nino.adminUi.say( Nino.content.getText('/_admin/elements/msg/image-added') );
				}, { file : file } );
			} );
		},

		/**
		 *	Take the image out of one field of the saved element, after asking - the question names the field and what
		 *	follows. The server writes the field empty and deletes the file, at once like an upload; a cancelled
		 *	question sends nothing
		 *
		 *	@param		{string}		key						The image field
		 *	@param		{string}		name					Its label, for the question
		 *	@param		{Object}		view					The parts of the field: { state, file, preview, remove, warning }
		 *
		 *	@return		void
		 */
		_removeImage : function( key, name, view ) {

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/elements/confirm/image-remove-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/elements/confirm/image-remove'), name ),
				yes				: Nino.content.getText('/_admin/elements/confirm/image-remove-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: view.remove,
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					view.remove.disabled = true;
					view.file.clearError();

					// The record is taken now, as for an upload: the person may step to another element or language
					// while the request is on its way
					Nino.admin.elements._apiCall( 'removeimage', {
						type 		: Nino.admin.elements._currentType,
						uri 		: Nino.admin.elements._currentUri,
						locale 	: Nino.admin.elements._locale,
						key 		: key,
					}, function( status, response ) {

						view.remove.disabled = false;

						if( status !== 200 || response === null ) {
							view.file.error( Nino.adminUi.api.errorText( status, response, '/_admin/elements/error/image-remove' ) );
							return;
						}

						Nino.admin.touch( 'elements' );

						view.state.value = '';
						view.preview.hidden = true;
						view.remove.hidden = true;
						view.warning.hidden = true;
						view.file.button.focus();
						Nino.adminUi.say( Nino.content.getText('/_admin/elements/msg/image-removed') );
					} );
				},
			} );
		},

		/**
		 *	Turn the element on screen into the starting values of a new one:
		 *	every field keeps what it holds, the uri does not.
		 *
		 *	Nothing is written and nothing is copied on the server - this only
		 *	puts the form into its "new element" state with the values already
		 *	in it, so the operator names the copy and creates it like any other
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
		 *	The copy carries every language the original has, which is what a copy of an element is, so the
		 *	creation writes them all (see _save()).
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
			Nino.admin.elements._resetEdits();
			Nino.admin.elements._copy 					= true;

			Nino.admin.elements._renderForm();
			Nino.admin.router.go( 'elements', [ Nino.admin.elements._currentType, '@new' ] );

			// A copy nobody has saved yet is the one state "unsaved" is for
			Nino.admin.elements._status.dirty( Nino.content.getText('/_admin/elements/msg/duplicated') );

			const uriInput = dc.getElementById('elements-form-uri');
			if( uriInput !== null && uriInput.type !== 'hidden' && uriInput.disabled !== true )
				uriInput.focus();
		},

		/**
		 *	Delete the currently open element, after confirmation: the question names the element and what goes with it
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( Nino.admin.elements._saving === true )
				return;

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/elements/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/elements/confirm/delete'), Nino.admin.elements._currentTypeTitle+ ' /'+ Nino.admin.elements._currentUri ),
				yes				: Nino.content.getText('/_admin/elements/confirm/delete-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
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
						Nino.admin.touch( 'elements' );
						Nino.admin.elements._saving = false;
						Nino.admin.elements._copy = false;
						Nino.admin.elements._selectType( type, model, title, true );
						Nino.adminUi.say( Nino.content.getText('/_admin/elements/msg/deleted') );
					} );
				},
			} );
		},
	};

	// The language moves for this panel whoever moved it (see Nino.admin.workLocale)
	Nino.admin.workLocale.follow( 'elements', Nino.admin.elements._followLocale );

	// The shell asks before anything throws this form's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'elements', {
			isDirty : Nino.admin.elements.isDirty,
			save		: function( done ) { Nino.admin.elements._save( done ) },
			discard : Nino.admin.elements.discard,
			saving		: function() { return Nino.admin.elements._saving === true },
		} );

} )(window, document, document.documentElement, document.body);
