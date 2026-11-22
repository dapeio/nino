

/**
 *	Nino										A compact filesystembased php framework
 *	types.js								"Element Types" module: create/edit an element type's
 *													title + model (field definitions) only - never touches a
 *													type's actual content ('*' and locale buckets with real
 *													elements), that part of the file is read back untouched
 *													and saved right along with it server-side. Deleting a type
 *													does destroy that content, and is offered anyway - behind
 *													a typed confirmation, see _delete(). A field can
 *													be renamed - its values move with it, see _renames() -
 *													and a type duplicated, see _duplicate().
 *
 *													Every view has an address that survives a reload: the types
 *													(#types), one type (#types/<uri>) and a new one
 *													(#types/@new). A form is built from the blocks of the
 *													workbench with the label at the left of the field, checks
 *													itself (novalidate) and says what is wrong at the field - the
 *													answer of the server too, where it names one. A field of the
 *													type is a card with the words of its options as labels, its
 *													yes-or-no properties as one group of ticks and the buttons that
 *													move and remove it as icons. A save leaves the person in the
 *													form and says so as a toast.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	// Under its nav uri: the shell finds a panel's script as
	// Nino.admin[uri] (see _admin/assets/script.js)
	Nino.admin.types = {

		// The keys a field is offered as, before an own one: the words of the
		// vocabulary fields most often are (/_admin/common/word/<key>, the ones
		// the Elements form names by it too)
		KEY_WORDS 			: [ 'title', 'subtitle', 'text', 'description', 'image', 'alt', 'caption', 'link', 'label', 'name', 'email', 'phone', 'address', 'date', 'author', 'price', 'icon' ],

		// The type a field of such a word most often has - a picture for the image
		// and the icon, a date for the date, a number with decimals for the price,
		// text for the rest: choosing the word puts its type on the select of the
		// field, which can be changed after. Each is one of the field types the
		// server lists
		KEY_TYPES 			: { title : 'string', subtitle : 'string', text : 'string', description : 'string', image : 'image', alt : 'string', caption : 'string', link : 'string', label : 'string', name : 'string', email : 'string', phone : 'string', address : 'string', date : 'date', author : 'string', price : 'double', icon : 'image' },

		// The properties of a field that are a yes or no, in the order they are listed: the key of the model, and the
		// types of field they mean something for (null: every type)
		FLAGS 					: [
			{ key : 'locale', label : 'perlocale', types : null },
			{ key : 'required', label : 'required', types : [ 'string', 'integer', 'double', 'boolean', 'array', 'date', 'datetime', 'element' ] },
			{ key : 'html', label : 'richtext', types : [ 'string' ] },
			{ key : 'blocks', label : 'blocks', types : [ 'string' ] },
			{ key : 'breaks', label : 'breaks', types : [ 'string' ] },
			{ key : 'fit', label : 'fit', types : [ 'image' ] },
			{ key : 'multiple', label : 'multiple', types : [ 'element' ] },
		],

		_types 					: [],
		_fieldTypes 		: [],
		// The field types a unit/suffix applies to, as the server states them
		// (Types.php's SUFFIX_TYPES): the rule lives there, the editor only
		// asks. Empty until the list has answered
		_suffixTypes 		: [],
		_currentUri 		: null,
		_isNew 					: false,
		_fields 				: [],
		// The cards of the fields on screen, in their order: { el, read(), ... } - what _storeFields() reads back
		_cards 					: [],
		_ready 					: false,
		// What deleting the open type would cost, as apiGet() reported it:
		// how many elements are in the file, and which other types' element
		// fields point at it (any at all means the server refuses)
		_elementCount 	: 0,
		_referencedBy 	: [],
		// The type to open once the list has loaded - the copy a duplication
		// just made (see _duplicate())
		_openUri 				: null,
		// The row or the button that opened the form that is open: Back returns
		// the focus to it (see Nino.adminUi.backTo())
		_opener 				: null,
		// What the last save of a rename left that it cannot move, shown at the head of the form it was made in
		_notice 				: '',
		// The status line and the bar of the form on screen, and the identifier and title fields
		_status 				: null,
		_bar 						: null,
		_uriField 			: null,
		_titleField 		: null,
		_numberField 		: null,
		_errors 				: 0,
		_saving 				: false,
		// What the form held when it was drawn, as a string (see _formState()): a type saved without a change writes nothing
		_baseline 			: '',

		/**
		 *	Load every element type and render the list - or, on the first load, open the type the hash names
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('types-list') === null )
				return;

			// Before the first _show*() below, which writes the address
			const hash = Nino.admin.types._ready === false ? Nino.admin.router.current() : { panel : '', parts : [] };

			Nino.admin.types._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.types._showError( dc.getElementById('types-list'), status, response );

				Nino.admin.types._take( response );
				Nino.admin.types._renderList();
				Nino.admin.types._showList();
				Nino.admin.types._ready = true;

				const open = Nino.admin.types._openUri;
				Nino.admin.types._openUri = null;

				if( open !== null )
					Nino.admin.types._openForm( open, undefined, true );
				else if( hash.panel === 'types' && hash.parts.length > 0 )
					Nino.admin.types._restore( hash.parts );
			} );
		},

		/**
		 *	What the list answered, kept
		 *
		 *	@param		{Object}	response
		 *
		 *	@return		void
		 */
		_take : function( response ) {
			Nino.admin.types._types 			= response.types;
			Nino.admin.types._fieldTypes 	= response.fieldTypes;
			Nino.admin.types._suffixTypes = response.suffixTypes ?? [];
		},

		/**
		 *	Read the list again and draw it where it stands - after a save, a copy or a deletion: the level on screen
		 *	stays as it is
		 *
		 *	@param		{Function}	[then]				Called once it is drawn
		 *
		 *	@return		void
		 */
		_reloadList : function( then ) {

			Nino.admin.types._apiCall( 'list', {}, function( status, response ) {

				if( status === 200 && response !== null ) {
					Nino.admin.types._take( response );
					Nino.admin.types._renderList();
				}

				if( typeof then === 'function' )
					then();
			} );
		},

		/**
		 *	Open the level an address names on the first load: a type, or a new one
		 *
		 *	@param		{Array}		parts					The hash behind the tab's name
		 *
		 *	@return		void
		 */
		_restore : function( parts ) {

			if( parts[0] === '@new' && parts.length === 1 ) {
				Nino.admin.types._openForm( null );
				return;
			}

			if( parts.length === 1 && Nino.admin.types._types.some( function( type ) { return type.uri === parts[0] } ) === true )
				Nino.admin.types._openForm( parts[0] );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on - or, where the hash names this tab, the level it
		 *	names: a step through the browser's history changes the address and nothing else. Leaving a form that
		 *	holds unsaved input asks first, as its back link does
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.types._ready === false )
				return;

			const hash = Nino.admin.router.current();
			if( hash.panel === 'types' && Nino.admin.types._follow( hash.parts ) === true )
				return;

			Nino.admin.types._showLevel();
		},

		_showLevel : function() {

			if( dc.getElementById('types-form').classList.contains('admin-hidden') === false )
				return Nino.admin.types._showForm();

			Nino.admin.types._showList();
		},

		/**
		 *	Move to the level the hash names, if it is not the one on screen: the list, a type of it, or a new one.
		 *	A type the list does not hold is the list. Leaving a form that holds unsaved input asks first (see
		 *	Nino.admin.router.leave())
		 *
		 *	@param		{Array}		parts					The hash behind the tab's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			if( Nino.admin.types._saving === true ) {
				if( typeof Nino.admin.router.refuse === 'function' )
					Nino.admin.router.refuse();
				return false;
			}

			const open = dc.getElementById('types-form').classList.contains('admin-hidden') === false;
			const isNew = parts.length === 1 && parts[0] === '@new';
			const known = parts.length === 1 && isNew === false && Nino.admin.types._types.some( function( type ) { return type.uri === parts[0] } ) === true;

			// Already on the level the address names
			if( isNew === false && known === false ? open === false : ( open === true && ( isNew === true ? Nino.admin.types._isNew === true : ( Nino.admin.types._isNew === false && Nino.admin.types._currentUri === parts[0] ) ) ) )
				return false;

			Nino.admin.router.leave( [ 'types' ], open, function() {

				if( isNew === true )
					Nino.admin.types._openForm( null );
				else if( known === true )
					Nino.admin.types._openForm( parts[0] );
				else
					Nino.admin.types._showList();
			}, Nino.admin.types._showLevel );

			return true;
		},

		/**
		 *	Call a types/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "types/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'types/'+ endpoint, payload, callback );
		},

		/**
		 *	Show a failed request's status/error in a container
		 *
		 *	@param		{Element}		container
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( container, status, response ) {
			Nino.adminUi.showError( container, status, response, '/_admin/common/error/load' );
		},

		/**
		 *	Show the list or the form again as they stand: the focus stays where
		 *	it is, unless it stood in the level that is hidden now (see
		 *	Nino.adminUi.showLevel()). Back goes through the back link, and what
		 *	a person opens takes the focus in
		 *
		 *	@param		{Element|null}	[opener]	Back: the control to give the focus to; null where there is none to name
		 *
		 *	@return		void
		 */
		_showList : function( opener ) {
			Nino.admin.router.set( 'types', [] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'types-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'types-list', opener );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {
			Nino.admin.router.set( 'types', [ Nino.admin.types._isNew === true ? '@new' : Nino.admin.types._currentUri ] );
			Nino.adminUi.showLevel( 'types-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	The row of a type as the list is drawn now - the list is drawn again after a save, and the row that was pressed
		 *	is gone with the old drawing
		 *
		 *	@param		{string|null}	uri
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( uri ) {

			const row = Array.from( dc.getElementById('types-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === uri } );

			return row === undefined ? null : row.querySelector('.nino-admin-row-open');
		},

		/**
		 *	The words a count of fields is written in
		 *
		 *	@param		{number}	count
		 *
		 *	@return		{string}
		 */
		_fieldCount : function( count ) {
			return Nino.adminUi.format( Nino.content.getText( count === 1 ? '/_admin/types/label/field' : '/_admin/types/label/fields' ), count );
		},

		/**
		 *	Render the type list, plus "New element type" in the bar below it: a row of the block for each type, its
		 *	title over the number of its fields and its identifier, quietly
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('types-list');
			wrap.innerHTML = '';

			if( Nino.admin.types._types.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/types/empty') ) );
			else
				wrap.appendChild( Nino.adminUi.list( Nino.admin.types._types.map( function( type ) {
					return {
						key			: type.uri,
						title		: type.title,
						detail	: Nino.admin.types._fieldCount( type.fieldCount )+ ' · /'+ type.uri,
						href		: Nino.admin.router.href( 'types', [ type.uri ] ),
						onOpen	: function() {
							Nino.admin.router.go( 'types', [ type.uri ] );
							Nino.admin.types._openForm( type.uri, Nino.admin.types._rowOf( type.uri ), true );
						},
					};
				} ), { label : Nino.content.getText('/_admin/nav/types') } ) );

			const addBtn = Nino.adminUi.button( Nino.content.getText('/_admin/types/label/new'), function() {
				Nino.admin.router.go( 'types', [ '@new' ] );
				Nino.admin.types._openForm( null, addBtn, true );
			} );
			wrap.appendChild( Nino.adminUi.actionBar( { list : true, primary : addBtn } ).el );
		},

		/**
		 *	Open the editor for an existing type, or a blank one for a new type
		 *
		 *	@param		{string|null}		uri				Type uri, or null to create a new one
		 *	@param		{Element|null}	[opener]	The row or the button that was pressed - Back returns the focus there; null for the copy a duplication just made, which has none
		 *	@param		{boolean}				[opened]	The person opened it: the focus goes into the form. Otherwise it is shown as it stands, by the address
		 *	@param		{Function}			[then]		Called once the form of a type that exists is on screen with true - with false where the type could not be read
		 *
		 *	@return		void
		 */
		_openForm : function( uri, opener, opened, then ) {

			Nino.admin.types._opener = opener ?? null;

			if( uri === null ) {
				Nino.admin.types._isNew 			= true;
				Nino.admin.types._currentUri = null;
				Nino.admin.types._fields 		= [];
				Nino.admin.types._elementCount = 0;
				Nino.admin.types._referencedBy = [];
				Nino.admin.types._renderForm( '', false, '00001' );
				Nino.admin.types._showForm( opened === true );
				return;
			}

			Nino.admin.types._apiCall( 'get', { uri : uri }, function( status, response ) {

				// Shown as well as written - the pane this error goes into is the one the list is covering, so without
				// the _showForm() the list stayed on screen and clicking a type that no longer parses (or one an account
				// just lost the permission for) did nothing visible at all. Same rule as the Elements panel next door,
				// which its own suite enforces
				if( status !== 200 || response === null ) {
					Nino.admin.types._showError( dc.getElementById('types-form'), status, response );
					Nino.admin.types._showForm( opened === true );

					if( typeof then === 'function' )
						then( false );
					return;
				}

				Nino.admin.types._isNew 			= false;
				Nino.admin.types._currentUri = response.uri;
				Nino.admin.types._fields 		= Object.keys( response.model ).map( function( key ) {
					// The name it is saved under: a field renamed here is moved from this
					// one to the new (see _renames())
					return Object.assign( { key : key, originalKey : key }, response.model[key] );
				} );
				Nino.admin.types._elementCount = response.elements ?? 0;
				Nino.admin.types._referencedBy = response.referencedBy ?? [];
				Nino.admin.types._renderForm( response.title, response.autoincrement === true, response.next );
				Nino.admin.types._showForm( opened === true );

				if( typeof then === 'function' )
					then( true );
			} );
		},

		/**
		 *	The name a field card goes by in its legend: the word of its key where it is one of the words fields most
		 *	often are, the key itself otherwise, and "New field" for one that has none yet
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{string}
		 */
		_fieldTitle : function( key ) {

			key = String( key ?? '' ).trim();

			if( key === '' )
				return Nino.content.getText('/_admin/types/label/field-new');

			return Nino.admin.types.KEY_WORDS.indexOf( key ) !== -1 ? ( Nino.content.getText('/_admin/common/word/'+ key) || key ) : key;
		},

		/**
		 *	The ticks a field of a type offers: the yes-or-no properties that mean something for it
		 *
		 *	@param		{string}	type
		 *
		 *	@return		{Array<Object>}
		 */
		_flagsOf : function( type ) {
			return Nino.admin.types.FLAGS.filter( function( flag ) { return flag.types === null || flag.types.indexOf( type ) !== -1 } );
		},

		/**
		 *	A whole number in the form's own words: the number field of the blocks, drawn empty where the model has none
		 *
		 *	@param		{string}				name			The key of the field in the model: maxlength, width, ...
		 *	@param		{*}							value
		 *	@param		{number}				min
		 *	@param		{string}				label			The fill of the label
		 *	@param		{string}				help			The fill of the sentence behind its (?)
		 *
		 *	@return		{Element}									The label of the field
		 */
		_numberOf : function( name, value, min, label, help ) {

			return Nino.adminUi.numberField( {
				key		: name,
				label	: Nino.content.getText( label ),
				help	: Nino.content.getText( help ),
				value	: value === undefined || value === null ? '' : value,
				min		: min,
			} );
		},

		/**
		 *	One field's card: the name, the type, the properties that are a yes or no as one group of ticks, whatever
		 *	options that type has (the length and the rows of a text, the unit, the fixed values, the size and the alt text
		 *	of an image, the type a reference points at and how many it may hold), and the icon buttons that move
		 *	and remove it. The labels are words, the explanation of each stands behind its (?). Choosing a word for the
		 *	name puts its usual type on the select of the type; changing the type draws the options of the new one,
		 *	keeping what was typed
		 *
		 *	@param		{Object}	field					{ key, originalKey, type, locale, html, blocks, breaks, required, maxlength, inputsize, width, height, fit, alt, elementType, suffix, options, multiple }
		 *	@param		{number}	index					Index into _fields, for the buttons
		 *
		 *	@return		{Element}									The card
		 */
		_renderFieldRow : function( field, index ) {

			const types = Nino.admin.types;
			const text = function( key ) { return Nino.content.getText( key ) };
			const card = dc.createElement('fieldset');
			card.className = 'types-field';

			const legend = Nino.adminUi.el( 'legend', '', types._fieldTitle( field.key ) );
			card.appendChild( legend );

			const head = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );
			card.appendChild( head );

			// The key of a field is one of the words fields most often are - "Titel", "Bild", "Preis" - or one of the
			// type's own. The choice puts the word into the text field, which is what is read back and saved and is
			// shown only for an own key, with the checks it always had
			const known = types.KEY_WORDS.indexOf( field.key ?? '' ) !== -1;
			const nameField = Nino.adminUi.selectField( {
				key		: 'name',
				label	: text('/_admin/types/label/fieldname'),
				help	: text('/_admin/types/help/fieldname'),
				value	: known === true ? field.key : '',
				note	: field.originalKey === undefined ? undefined : text('/_admin/types/hint/renamed'),
				options	: types.KEY_WORDS.map( function( word ) { return { value : word, label : text('/_admin/common/word/'+ word) || word } } ).concat( [ { value : '', label : text('/_admin/types/label/ownkey') } ] ),
			} );
			const nameSelect = nameField.querySelector('select');
			head.appendChild( nameField );

			const ownField = Nino.adminUi.textField( { key : 'own', label : text('/_admin/types/label/ownkey'), required : true, value : field.key ?? '', autocomplete : 'off' } );
			const keyInput = ownField.querySelector('input');
			ownField.hidden = known;
			head.appendChild( ownField );

			// A field that is saved keeps the name it was saved under on the card, so a rename can be told from a new
			// field however the cards are moved or drawn again, and says so while it differs
			if( field.originalKey !== undefined ) {

				card.dataset.originalKey = field.originalKey;

				const renamed = nameField.querySelector('.nino-admin-field-note');
				const showRenamed = function() {
					const key = keyInput.value.trim();
					renamed.hidden = ( key === '' || key === field.originalKey );
				};

				keyInput.addEventListener( 'input', showRenamed );
				showRenamed();
				card.showRenamed = showRenamed;
			}

			const typeField = Nino.adminUi.selectField( {
				key		: 'type',
				label	: text('/_admin/types/label/type'),
				help	: text('/_admin/types/help/type'),
				value	: field.type,
				options	: types._fieldTypes.map( function( t ) { return { value : t, label : text('/_admin/types/option/type/'+ t) || t } } ),
			} );
			const typeSelect = typeField.querySelector('select');
			head.appendChild( typeField );

			const options = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid types-field-options' );
			card.appendChild( options );

			// What the controls of the options hold, by name; refilled whenever they are drawn
			let parts = {};
			let state = Object.assign( {}, field );

			// What the options hold now, as far as they are on screen: kept where the type changes under them
			const take = function() {

				if( parts.flags !== undefined ) {
					const ticked = parts.flags.value();
					types._flagsOf( state.type ).forEach( function( flag ) { state[flag.key] = ticked.indexOf( flag.key ) !== -1 } );
				}

				[ 'maxlength', 'inputsize', 'width', 'height', 'suffix' ].forEach( function( name ) {
					if( parts[name] !== undefined )
						state[name] = parts[name].value;
				} );

				if( parts.options !== undefined )
					state.options = parts.options.value.split(',').map( function( s ) { return s.trim() } ).filter( function( s ) { return s !== '' } );

				if( parts.alt !== undefined )
					state.alt = parts.alt.value;

				if( parts.elementType !== undefined )
					state.elementType = parts.elementType.value;

				if( parts.multipleMax !== undefined )
					state.multipleMax = parts.multipleMax.value;
			};

			const draw = function() {

				options.innerHTML = '';
				parts = {};

				const type = typeSelect.value;
				const flags = types._flagsOf( type );
				const asNumber = function( name, value, min, label, help ) {
					const row = types._numberOf( name, value, min, label, help );
					parts[name] = row.querySelector('input');
					parts[name].dataset.field = name;
					options.appendChild( row );
					return row;
				};

				// The properties that are a yes or no, one group: the explanation of each is a sentence of the (?) at the
				// group, since a tick has no help of its own
				if( flags.length > 0 ) {

					parts.flags = Nino.adminUi.checklist( {
						key			: 'flags',
						label		: text('/_admin/types/label/flags'),
						help		: flags.map( function( flag ) { return text('/_admin/types/label/'+ flag.label)+ ': '+ text('/_admin/types/help/'+ flag.label) } ).join(' '),
						options	: flags.map( function( flag ) { return { value : flag.key, label : text('/_admin/types/label/'+ flag.label) } } ),
						value		: flags.filter( function( flag ) { return state[flag.key] === true || ( flag.key === 'multiple' && typeof state.multiple === 'number' ) } ).map( function( flag ) { return flag.key } ),
					} );

					options.appendChild( parts.flags.el );

					// What goes with another: paragraphs and lists with rich text, the line breaks of plain text only without it
					const sync = function() {

						const ticked = parts.flags.value();
						const control = function( key ) { return parts.flags.controls[ flags.findIndex( function( flag ) { return flag.key === key } ) ] };

						if( control('blocks') !== undefined )
							control('blocks').disabled = ticked.indexOf('html') === -1;

						if( control('breaks') !== undefined )
							control('breaks').disabled = ticked.indexOf('html') !== -1;

						if( parts.multipleMax !== undefined )
							parts.multipleMax.disabled = ticked.indexOf('multiple') === -1;
					};

					parts.flags.el.addEventListener( 'change', sync );
					parts.sync = sync;
				}

				// Offered for the types the server keeps a suffix for - the ones that render an input a unit can sit next
				// to, which is neither a boolean's choice, nor an image's upload area, nor an element reference's select
				const unit = function() {
					if( types._suffixTypes.indexOf( type ) === -1 )
						return;
					const row = Nino.adminUi.textField( { key : 'suffix', label : text('/_admin/types/label/suffix'), help : text('/_admin/types/help/suffix'), value : state.suffix ?? '', autocomplete : 'off' } );
					parts.suffix = row.querySelector('input');
					options.appendChild( row );
				};

				if( type === 'string' ) {

					asNumber( 'maxlength', state.maxlength, 1, '/_admin/types/label/maxlength', '/_admin/types/help/maxlength' );
					// Rows the field's input opens with - a hint for the form, not a limit on the value (see Types.php's cleanModel())
					asNumber( 'inputsize', state.inputsize, 1, '/_admin/types/label/inputsize', '/_admin/types/help/inputsize' );

					const values = Nino.adminUi.textField( { key : 'options', label : text('/_admin/types/label/options'), help : text('/_admin/types/help/options'), placeholder : text('/_admin/types/placeholder/options'), value : ( state.options ?? [] ).join(', '), autocomplete : 'off' } );
					parts.options = values.querySelector('input');
					options.appendChild( values );
				}

				unit();

				// Which type this reference may point at. Part of the field, not of the value: it is what the element form
				// builds its select of elements from, so a reference without one has nothing to offer (Types.php's
				// _unknownReferencedType() rejects the save). A brand-new type is not in this list yet - it has no file on
				// disk to reference - so a self-reference is added by reopening the type once it exists
				if( type === 'element' ) {

					const others = types._types.filter( function( t ) { return t.uri !== types._currentUri } );
					const choices = others.map( function( t ) { return { value : t.uri, label : t.title+ ' (/'+ t.uri+ ')' } } );

					if( others.length === 0 )
						choices.push( { value : '', label : text('/_admin/types/label/references-empty') } );

					// A reference whose target was deleted since keeps showing what it points at, rather than silently
					// re-pointing at whichever type happens to sort first
					if( state.elementType && others.some( function( t ) { return t.uri === state.elementType } ) === false )
						choices.push( { value : state.elementType, label : state.elementType+ ' ('+ text('/_admin/elements/label/reference-missing')+ ')' } );

					const refs = Nino.adminUi.selectField( { key : 'elementType', label : text('/_admin/types/label/references'), help : text('/_admin/types/help/references'), value : state.elementType || ( choices[0] ?? { value : '' } ).value, options : choices } );
					parts.elementType = refs.querySelector('select');
					parts.elementType.disabled = others.length === 0 && ! state.elementType;
					options.appendChild( refs );

					// How many elements the field may hold: the tick says whether this is a list at all (an absent key is the
					// single reference every existing type still means), and this where it stops. 0 is the honest way to say
					// "no ceiling" - the alternative, an empty box meaning unlimited, is indistinguishable from one nobody
					// filled in
					asNumber( 'multipleMax', typeof state.multiple === 'number' ? state.multiple : ( state.multipleMax ?? '' ), 0, '/_admin/types/label/max', '/_admin/types/help/max' );
				}

				if( type === 'image' ) {

					asNumber( 'width', state.width, 1, '/_admin/common/label/width', '/_admin/types/help/width' );
					asNumber( 'height', state.height, 1, '/_admin/common/label/height', '/_admin/types/help/height' );

					// Which string field holds this picture's alt text. Only a field that can: written per language,
					// plain text, and not the image itself (Types.php's cleanModel() decides the same on save). Offered from
					// the cards as they stand now, so a field added a moment ago is there - the list is made again when the
					// control takes the focus, keeping what is chosen. A field is named by the name it was saved under (and by
					// its key while it is new), shown by the key it has now: renaming it does not unlink it, and apiSave()
					// reads the link through the renames
					const alt = Nino.adminUi.selectField( { key : 'alt', label : text('/_admin/types/label/alt'), help : text('/_admin/types/help/alt'), value : state.alt ?? '', options : [ { value : '', label : text('/_admin/types/option/alt-none') } ] } );
					const altSelect = alt.querySelector('select');

					const fill = function( current ) {

						altSelect.innerHTML = '';
						altSelect.appendChild( Nino.adminUi.el( 'option', '', text('/_admin/types/option/alt-none') ) ).value = '';

						// The fields as the cards hold them now - or, while the cards are being drawn, as the model holds them
						types._storeFields().forEach( function( other ) {

							if( other.key === '' || other.key === keyInput.value || other.type !== 'string' || other.locale !== true || other.html === true )
								return;

							const name = other.originalKey ?? other.key;
							const opt = Nino.adminUi.el( 'option', '', other.key );

							opt.value = name;
							opt.selected = ( name === current );
							altSelect.appendChild( opt );
						} );
					};

					fill( state.alt ?? '' );
					altSelect.addEventListener( 'focus', function() { fill( altSelect.value ) } );
					parts.alt = altSelect;
					options.appendChild( alt );
				}

				if( parts.sync !== undefined )
					parts.sync();
			};

			// The select of the name: the word is the key and gives the field its usual type
			nameSelect.addEventListener( 'change', function() {

				const word = nameSelect.value;
				ownField.hidden = ( word !== '' );

				if( word !== '' ) {

					keyInput.value = word;

					// The word says what the field is: its type follows, and the options of that type with it - the same
					// change a pick on the type select makes. An own key leaves the type as it is
					const type = types.KEY_TYPES[word];

					if( types._fieldTypes.indexOf( type ) !== -1 && typeSelect.value !== type ) {
						typeSelect.value = type;
						typeSelect.dispatchEvent( new wn.Event( 'change', { bubbles : true } ) );
					}
				}
				else
					keyInput.focus();

				// What the field is called changed, whichever way: the legend and the sentence that says it is renamed follow it
				keyInput.dispatchEvent( new wn.Event( 'input', { bubbles : true } ) );
			} );

			keyInput.addEventListener( 'input', function() {
				legend.textContent = types._fieldTitle( keyInput.value );
				if( card.showRenamed !== undefined )
					card.showRenamed();
			} );

			typeSelect.addEventListener( 'change', function() {
				take();
				state.type = typeSelect.value;
				draw();
			} );

			draw();

			// The buttons that move and remove it, as the icons of every ordered row of a draft: the field's position in
			// the model is the order the element form draws its fields in, so this is a real editing control, not just a
			// way to tidy up this list
			const move = Nino.adminUi.el( 'div', 'nino-admin-move' );

			move.appendChild( Nino.adminUi.iconButton( 'arrow-up', text('/_admin/common/label/moveup'), function() { types._move( index, 'up' ) }, { disabled : index === 0 } ) );
			move.appendChild( Nino.adminUi.iconButton( 'arrow-down', text('/_admin/common/label/movedown'), function() { types._move( index, 'down' ) }, { disabled : index === types._fields.length - 1 } ) );
			move.appendChild( Nino.adminUi.iconButton( 'trash-2', text('/_admin/types/label/removefield'), function() {
				// Read the cards back first, same as _move() and "Add field" do - every card is drawn again from _fields
				// below, so without this dropping one would silently revert every edit typed into the others since the last drawing
				types._storeFields();
				types._fields.splice( index, 1 );
				types._renderFields();
				types._refreshDirty();
				types._focusAfterRemoval( index );
			}, { danger : true } ) );
			card.appendChild( move );

			card.dataset.index = index;

			card.read = function() {

				take();

				const key = keyInput.value;

				return {
					key 			: key,
					// Undefined on a field that is not saved yet - see _renames()
					originalKey : card.dataset.originalKey,
					type 			: typeSelect.value,
					locale 		: state.locale === true,
					required 	: state.type === 'image' ? false : state.required === true,
					html 			: state.type === 'string' && state.html === true,
					blocks 		: state.type === 'string' && state.blocks === true,
					breaks 		: state.type === 'string' && state.breaks === true,
					maxlength : state.type === 'string' ? ( state.maxlength ?? '' ) : '',
					inputsize : state.type === 'string' ? ( state.inputsize ?? '' ) : '',
					width 		: state.type === 'image' ? ( state.width ?? '' ) : '',
					height 		: state.type === 'image' ? ( state.height ?? '' ) : '',
					fit 			: state.type === 'image' && state.fit === true,
					alt 			: state.type === 'image' ? ( state.alt ?? '' ) : '',
					suffix 		: types._suffixTypes.indexOf( state.type ) !== -1 ? ( state.suffix ?? '' ) : '',
					elementType : state.type === 'element' ? ( state.elementType ?? '' ) : '',
					multiple 		: state.type === 'element' && state.multiple === true,
					multipleMax : state.type === 'element' ? ( state.multipleMax ?? '' ) : '',
					options 	: state.type === 'string' ? ( state.options ?? [] ) : [],
					// What the type's file says the form calls the field: the form has no control for it, so it only travels
					// along to a copy (see _copyModel()) and is never sent by a save
					label 		: field.label,
				};
			};

			// What the validation reaches: the fields a sentence is said at
			card.fields = {
				name : nameField,
				own : ownField,
				key : keyInput,
				nameSelect : nameSelect,
				parts : function() { return parts },
			};

			return card;
		},

		/**
		 *	Swap a field card with its neighbour and draw the cards again.
		 *
		 *	Order matters beyond this list: _buildModel() walks _fields in
		 *	order, json_decode and Types.php's cleanModel() both keep that
		 *	order on the way into the type file, and each element form renders
		 *	its fields in the model's own key order (see admin.js's
		 *	_globalKeys/_localeKeys) - so this is how the editing form for
		 *	every element of this type gets arranged
		 *
		 *	@param		{number}	index
		 *	@param		{string}	direction		'up' | 'down'
		 *
		 *	@return		void
		 */
		_move : function( index, direction ) {

			// Cards are drawn again from _fields, so whatever is currently typed
			// into them has to be read back first - otherwise moving a card
			// would revert every edit made since the last drawing
			Nino.admin.types._storeFields();

			const fields 		= Nino.admin.types._fields;
			const swapWith 	= direction === 'up' ? index - 1 : index + 1;

			if( swapWith < 0 || swapWith >= fields.length )
				return;

			[ fields[index], fields[swapWith] ] = [ fields[swapWith], fields[index] ];

			Nino.admin.types._renderFields();
			Nino.admin.types._refreshDirty();

			// The button that was pressed stands in the card the field moved to: the focus stays on its kind, so the card can be moved again
			const moved = dc.getElementById('types-form').querySelectorAll('.types-field')[swapWith];
			const buttons = moved === undefined ? [] : moved.querySelector('.nino-admin-move').querySelectorAll('button');
			const target = buttons[ direction === 'up' ? 0 : 1 ];

			if( target !== undefined )
				( target.disabled === true ? buttons[ direction === 'up' ? 1 : 0 ] : target ).focus();
		},

		/**
		 *	The focus after a card was taken away: on the remove button of the one that took its place, else of the one
		 *	before it, else on "Add field"
		 *
		 *	@param		{number}	index					Where the card stood
		 *
		 *	@return		void
		 */
		_focusAfterRemoval : function( index ) {

			const cards = dc.getElementById('types-fields').querySelectorAll('.types-field');
			const next = cards[index] ?? cards[index - 1];

			if( next === undefined ) {
				dc.getElementById('types-form-addfield').focus();
				return;
			}

			next.querySelector('.nino-admin-btn-danger').focus();
		},

		/**
		 *	Draw every field card into #types-fields
		 *
		 *	@return		void
		 */
		_renderFields : function() {

			const wrap = dc.getElementById('types-fields');
			wrap.innerHTML = '';
			Nino.admin.types._cards = [];

			Nino.admin.types._fields.forEach( function( field, index ) {
				const card = Nino.admin.types._renderFieldRow( field, index );
				Nino.admin.types._cards.push( card );
				wrap.appendChild( card );
			} );
		},

		/**
		 *	Read every field card's current state back into _fields, so
		 *	adding/removing a card (which draws them again) never loses in-progress edits
		 *
		 *	@return		{Array}										The fields
		 */
		_storeFields : function() {

			// Only when every field has its card: while they are drawn, the fields are what the model holds
			if( Nino.admin.types._cards.length > 0 && Nino.admin.types._cards.length === Nino.admin.types._fields.length )
				Nino.admin.types._fields = Nino.admin.types._cards.map( function( card ) { return card.read() } );

			return Nino.admin.types._fields;
		},

		/**
		 *	Have the shell look at the markers again, where it has the registry: a field added, moved or taken away is
		 *	input the form holds
		 *
		 *	@return		void
		 */
		_refreshDirty : function() {

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
		},

		/**
		 *	The control Back returns the focus to from the form that is open: the row of its type as the list is drawn now,
		 *	and for a blank form the button that adds one
		 *
		 *	@return		void
		 */
		_backTarget : function() {
			return Nino.admin.types._currentUri === null ? dc.getElementById('types-list').querySelector('.nino-admin-btn-primary') : Nino.admin.types._rowOf( Nino.admin.types._currentUri );
		},

		/**
		 *	Render the type editor: the way back with the name of the type, the section of the type - the identifier
		 *	(only when new), the title and whether it numbers its elements - the cards of the fields and "Add field",
		 *	and the bar: "Delete element type" at the left, "Duplicate element type" beside the one main button, Save
		 *	(or "Create element type") at the right
		 *
		 *	@param		{string}	title
		 *	@param		{boolean}	autoincrement	Whether this type numbers its own elements
		 *	@param		{string}	next					The uri the next element would get, for the help
		 *
		 *	@return		void
		 */
		_renderForm : function( title, autoincrement, next ) {

			const types = Nino.admin.types;
			const text = function( key ) { return Nino.content.getText( key ) };
			const wrap = dc.getElementById('types-form');
			wrap.innerHTML = '';

			wrap.appendChild( Nino.adminUi.contextBar( {
				back	: { to : text('/_admin/nav/types'), onClick : function() {
					Nino.admin.router.go( 'types', [] );
					types._showList( types._backTarget() );
				} },
				title	: types._isNew === true ? text('/_admin/types/label/new') : title,
				code	: types._isNew === true ? '' : '/'+ types._currentUri,
			} ) );

			if( types._notice !== '' ) {
				wrap.appendChild( Nino.adminUi.notice( types._notice ) );
				types._notice = '';
			}

			const form = dc.createElement('form');
			form.id = 'types-edit-form';
			form.noValidate = true;

			const section = dc.createElement('fieldset');
			section.appendChild( Nino.adminUi.el( 'legend', '', text('/_admin/types/label/type-section') ) );

			if( types._isNew === true ) {
				types._uriField = Nino.adminUi.textField( { id : 'types-form-uri', key : 'uri', label : text('/_admin/types/label/uri'), help : text('/_admin/types/help/uri'), required : true, value : '', autocomplete : 'off' } );
				types._uriField.querySelector('input').dataset.field = 'uri';
				section.appendChild( types._uriField );
			}
			else
				types._uriField = null;

			types._titleField = Nino.adminUi.textField( { id : 'types-form-title', key : 'title', label : text('/_admin/types/label/title'), help : text('/_admin/types/help/title'), value : title, autocomplete : 'off' } );
			types._titleField.querySelector('input').dataset.field = 'title';
			section.appendChild( types._titleField );

			// How an element of this type gets its uri. A type whose entries have a name worth putting in a url
			// ("/team/ada") is asked for one; a type whose entries have no natural name - a gallery image, a price row -
			// is better off numbered than made to invent one per entry, which is how it ends up with "bild-2",
			// "bild-2-neu", "bild-2-final". Only the elements added from here on are numbered, which the help says
			types._numberField = Nino.adminUi.switchField( {
				key 		: 'autoincrement',
				checked : autoincrement === true,
				label 	: text('/_admin/types/label/numbering'),
				help 		: Nino.adminUi.format( text('/_admin/types/help/numbering'), '/'+ ( types._currentUri ?? '<type>' )+ '/'+ ( next || '00001' ) ),
			} );
			types._numberField.id = 'types-form-autoincrement';
			section.appendChild( types._numberField );

			form.appendChild( section );

			form.appendChild( Nino.adminUi.sectionTitle( text('/_admin/types/label/fields-section') ) );

			const fieldsWrap = dc.createElement('div');
			fieldsWrap.id = 'types-fields';
			form.appendChild( fieldsWrap );

			const addFieldBtn = Nino.adminUi.button( text('/_admin/types/label/addfield'), function() {
				types._storeFields();
				types._fields.push( { key : '', type : 'string' } );
				types._renderFields();
				types._refreshDirty();

				// The new card is the one at the end, and its name is what is typed first
				const cards = dc.getElementById('types-fields').querySelectorAll('.types-field');
				const last = cards[cards.length - 1];

				if( last !== undefined )
					( last.querySelector('select') ).focus();
			}, { kind : 'secondary', icon : 'plus', id : 'types-form-addfield' } );
			form.appendChild( addFieldBtn );

			// The bar of every form: "Delete element type" at the left - and where other types point at this one, shut,
			// with the reason behind its (?) - the state with its line, the copy beside the one main button
			const msg = dc.createElement('p');
			msg.id = 'types-form-msg';

			const secondary = [];

			if( types._isNew === false ) {
				const duplicateBtn = Nino.adminUi.button( text('/_admin/types/label/duplicate'), function() { types._duplicateAsk( duplicateBtn ) }, { kind : 'secondary', id : 'types-form-duplicate' } );
				secondary.push( duplicateBtn );
			}

			let deleteBtn = null;

			if( types._isNew === false ) {
				deleteBtn = Nino.adminUi.button( text('/_admin/types/label/delete'), function() { types._deleteAsk( deleteBtn ) }, { kind : 'danger', id : 'types-form-delete' } );
				deleteBtn.disabled = types._referencedBy.length > 0;
			}

			const bar = Nino.adminUi.actionBar( {
				danger		: deleteBtn,
				status		: { line : msg, fresh : types._isNew },
				secondary	: secondary,
				primary		: Nino.adminUi.button( text( types._isNew === true ? '/_admin/types/label/create' : '/_admin/common/label/save' ), null, { type : 'submit' } ),
			} );

			// The reason the button is shut stands behind a (?) beside it, and in its title
			if( deleteBtn !== null && deleteBtn.disabled === true ) {
				const reason = Nino.adminUi.format( text('/_admin/types/help/delete-referenced'), types._referencedBy.join(', ') );
				deleteBtn.title = reason;
				deleteBtn.after( Nino.adminUi.fieldHelp( reason, { field : deleteBtn, name : text('/_admin/types/label/delete') } ) );
			}

			types._bar = bar;
			types._status = bar.line;
			form.appendChild( bar.el );
			bar.line.bind( form );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); types._save() } );

			// What is typed into a field lets go of what the form said was wrong with it
			const letGo = function( ev ) {

				const row = ev !== undefined && ev !== null && ev.target !== null && typeof ev.target === 'object' && typeof ev.target.closest === 'function' ? ev.target.closest('.nino-admin-field, .nino-admin-checklist-field') : null;

				if( row === null || row === undefined )
					return;

				// The sentence of a field is what its control is described by (see fieldError())
				String( ev.target.getAttribute('aria-describedby') ?? '' ).split(/\s+/).forEach( function( id ) {

					const sentence = id.indexOf('types-error-') === 0 ? dc.getElementById( id ) : null;

					if( sentence !== null )
						sentence.remove();
				} );

				Nino.adminUi.clearFieldErrors( row );
			};

			form.addEventListener( 'input', letGo );
			form.addEventListener( 'change', letGo );

			wrap.appendChild( form );
			types._renderFields();

			// What the form holds now is what is saved
			types._baseline = types._formState();
			types._snapshot();
		},

		/**
		 *	What the form holds, as one string to compare: the title, whether it numbers its elements and the model its
		 *	cards make, in their order
		 *
		 *	@return		{string}
		 */
		_formState : function() {

			const types = Nino.admin.types;

			return JSON.stringify( [ types._titleField.querySelector('input').value, types._numberField.querySelector('input').checked, types._buildModel() ] );
		},

		/**
		 *	The form on screen is what is stored now
		 *
		 *	@return		void
		 */
		_snapshot : function() {
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'types' );
		},

		/**
		 *	The model a copy of the open type is made from: its fields as the form holds them now, with an alt link
		 *	moved to the name its field has now - the copy has no saved names
		 *
		 *	@return		{Object}
		 */
		_copyModel : function() {

			const model = Nino.admin.types._buildModel();
			const renames = Nino.admin.types._renames();

			Object.keys( model ).forEach( function( key ) {
				if( Object.prototype.hasOwnProperty.call( renames, model[key].alt ) === true )
					model[key].alt = renames[model[key].alt];
			} );

			// The words a field is called in the element form are part of the field, and so of its copy
			Nino.admin.types._fields.forEach( function( field ) {
				if( field.key !== '' && field.label !== undefined && field.label !== null && model[field.key] !== undefined )
					model[field.key].label = field.label;
			} );

			return model;
		},

		/**
		 *	"Duplicate element type": the question is asked first about the unsaved input this form and the element form
		 *	next door hold, since the copy is opened in place of this form, and then the dialog asks for the identifier of
		 *	the copy and, where it is to differ, its title
		 *
		 *	@param		{Element}		opener			The button that was pressed
		 *
		 *	@return		void
		 */
		_duplicateAsk : function( opener ) {

			const types = Nino.admin.types;

			if( types._currentUri === null || types._saving === true )
				return;

			const open = function() { types._duplicate( opener ) };

			if( typeof Nino.admin.dirty !== 'object' ) {
				open();
				return;
			}

			Nino.admin.dirty.guard( [ 'types', 'elements' ], open );
		},

		/**
		 *	The dialog that makes a copy of the open type under a new identifier. The copy is made here, from the form as it
		 *	stands - its title, its fields and whether it numbers its elements - and created like any new type, so what
		 *	the server keeps of it is what it keeps of any model: the defaults a new element starts with and a
		 *	hand-written whitelist or callback stay with the original. A refusal of the server stands at its field and
		 *	the dialog stays open; the copy is opened when it is made
		 *
		 *	@param		{Element}		opener			The button that was pressed
		 *
		 *	@return		void
		 */
		_duplicate : function( opener ) {

			const types = Nino.admin.types;
			const text = function( key ) { return Nino.content.getText( key ) };
			const own = types._currentUri;

			Nino.adminUi.formDialog( {
				title		: text('/_admin/types/label/duplicate'),
				fields	: [
					{ key : 'uri', label : text('/_admin/types/label/uri'), help : text('/_admin/types/help/duplicate-uri'), required : true, autocomplete : 'off',
						validate : function( value ) {
							if( /^[a-z][a-z0-9_-]*$/.test( value.trim() ) === false )
								return Nino.adminUi.format( text('/_admin/types/error/invalid'), text('/_admin/types/label/uri'), text('/_admin/types/expect/uri') );
							if( value.trim() === own || types._types.some( function( type ) { return type.uri === value.trim() } ) === true )
								return Nino.adminUi.format( text('/_admin/elements/error/taken'), text('/_admin/types/label/uri') );
							return '';
						} },
					{ key : 'title', label : text('/_admin/types/label/title'), help : text('/_admin/types/help/duplicate-title'), autocomplete : 'off' },
				],
				submit	: text('/_admin/types/label/duplicate'),
				cancel	: text('/_admin/common/label/cancel'),
				opener	: opener,
				onSubmit : function( values, ctl ) {

					const title = values.title.trim() === '' ? types._titleField.querySelector('input').value.trim() : values.title.trim();

					types._apiCall( 'create', { uri : values.uri.trim(), title : title, model : types._copyModel(), autoincrement : types._numberField.querySelector('input').checked }, function( status, response ) {

						if( status !== 200 || response === null ) {
							ctl.fail( response !== null && typeof response === 'object' && response.field === 'uri' ? 'uri' : null, Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
							return;
						}

						ctl.close();
						Nino.admin.touch( 'elements' );
						types._invalidateElements();
						types._reloadList( function() {
							Nino.admin.router.go( 'types', [ response.uri ] );
							types._openForm( response.uri, undefined, true, function() { Nino.adminUi.say( text('/_admin/types/msg/duplicated') ) } );
						} );
					} );
				},
			} );
		},

		/**
		 *	"Delete element type": the one control in this module that destroys content - the open type, its elements and
		 *	the images those elements own.
		 *
		 *	Doing it by hand means deleting the same file from a shell, which
		 *	is not safer - it is the same removal with no reference check, no
		 *	image cleanup and no log line. So the risk is not avoided by
		 *	leaving it out, only moved somewhere with fewer guards. Here it
		 *	gets three:
		 *
		 *	  - it says what goes with the type before anything is clicked:
		 *	    the element count came with the type itself (see apiGet())
		 *	  - a type another type's element field points at is not offered
		 *	    at all: the button is shut and says which field holds the reference
		 *	  - the question names the type and what goes with it, and the answer
		 *	    stays locked until the type's own uri is typed (confirm() with
		 *	    `typed`), so no single click reaches it
		 *
		 *	Every one of these is re-checked server-side in
		 *	\Nino\Modules\Elements\Types::apiDelete() - this is the readable
		 *	half, not the enforcing one.
		 *
		 *	@param		{Element}		opener			The button that was pressed
		 *
		 *	@return		void
		 */
		_deleteAsk : function( opener ) {

			const types = Nino.admin.types;
			const text = function( key ) { return Nino.content.getText( key ) };
			const uri = types._currentUri;

			if( uri === null || types._saving === true || types._referencedBy.length > 0 )
				return;

			const lines = [ text('/_admin/types/confirm/delete-type') ];

			if( types._elementCount > 0 ) {
				lines.push( Nino.adminUi.format( text('/_admin/types/confirm/delete-elements'), types._elementCount ) );
				lines.push( text('/_admin/types/confirm/delete-images') );
			}

			Nino.adminUi.confirm( 'destructive', {
				title			: text('/_admin/types/label/delete'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/types/confirm/delete'), '/'+ uri ),
				details		: [ { title : text('/_admin/types/confirm/deleted'), lines : lines } ],
				yes				: text('/_admin/types/label/delete'),
				no				: text('/_admin/common/label/cancel'),
				typed			: uri,
				opener		: opener,
				onChoose	: function( ok, word ) {
					if( ok === true )
						types._delete( word );
				},
			} );
		},

		/**
		 *	Send the deletion the question was answered with yes for, then go back to a freshly loaded list - the type the
		 *	form was showing is gone, so there is nothing to stay on. The unsaved input of an open element form is asked
		 *	about first: deleting a type drops that form
		 *
		 *	@param		{string}	word					What was typed to confirm: the type's own uri, which the server checks again; anything else sends nothing
		 *	@param		{boolean}	[guarded]			The unsaved input of an open element form was asked about already
		 *
		 *	@return		void
		 */
		_delete : function( word, guarded ) {

			const types = Nino.admin.types;
			const uri = types._currentUri;

			if( uri === null || word !== uri )
				return;

			if( guarded !== true ) {
				types._guardElements( function() { types._delete( word, true ) } );
				return;
			}

			types._saving = true;
			types._status.saving();

			types._apiCall( 'delete', { uri : uri, confirm : word }, function( status, response ) {

				types._saving = false;

				if( status !== 200 || response === null ) {
					types._status.error( status, response, '/_admin/common/error/delete' );
					return;
				}

				types._isNew 			= false;
				types._currentUri = null;
				types._fields 		= [];
				types._cards 			= [];
				dc.getElementById('types-form').innerHTML = '';
				types._snapshot();
				types._reloadList( function() {
					Nino.admin.router.go( 'types', [] );
					types._showList( null );
					Nino.adminUi.say( Nino.content.getText('/_admin/types/msg/deleted') );
				} );
				types._invalidateElements();
			} );
		},

		/**
		 *	The renames this save carries: { name it was saved under: name it has
		 *	now } for every field that was loaded from the type and has a different,
		 *	non-empty key. A field added here has no original key and is never a
		 *	rename, and a key that was emptied is a field taken out of the model,
		 *	not one renamed to nothing. Types::apiSave() moves the stored values
		 *	from the one to the other
		 *
		 *	@return		{Object}
		 */
		_renames : function() {

			Nino.admin.types._storeFields();

			const renames = {};

			Nino.admin.types._fields.forEach( function( field ) {

				const key = field.key.trim();

				if( field.originalKey !== undefined && key !== '' && key !== field.originalKey )
					renames[field.originalKey] = key;
			} );

			return renames;
		},

		/**
		 *	What a save of renames left that it cannot move, as one line: the
		 *	templates, the permissions and the label texts that still use the old
		 *	name, as Types::apiSave() reports them
		 *
		 *	@param		{Array}		references		[ { kind, name, field } ]
		 *
		 *	@return		{string}									'' when nothing is left
		 */
		_referenceNotice : function( references ) {

			if( Array.isArray( references ) === false || references.length === 0 )
				return '';

			const items = references.map( function( reference ) {
				return Nino.adminUi.format( Nino.content.getText('/_admin/types/reference/'+ reference.kind ), reference.name, reference.field );
			} );

			return Nino.adminUi.format( Nino.content.getText('/_admin/types/notice/references'), items.join(', ') );
		},

		/**
		 *	Build the model object apiSave()/apiCreate() expect from the
		 *	current field cards, skipping cards with no key
		 *
		 *	@return		{Object}
		 */
		_buildModel : function() {

			Nino.admin.types._storeFields();

			const model = {};
			Nino.admin.types._fields.forEach( function( field ) {
				if( field.key === '' )
					return;
				// Every key a card reads back belongs here
				model[field.key] = {
					type 				: field.type,
					locale 			: field.locale,
					required 		: field.required,
					html 				: field.html,
					blocks 			: field.blocks,
					breaks 			: field.breaks,
					maxlength 	: field.maxlength,
					inputsize 	: field.inputsize,
					width 			: field.width,
					height 			: field.height,
					fit 				: field.fit,
					alt 				: field.alt,
					suffix 			: field.suffix,
					elementType : field.elementType,
					multiple 		: field.multiple,
					multipleMax : field.multipleMax,
					options 		: field.options,
				};
			} );
			return model;
		},

		/**
		 *	Tell the Elements module next door that the schema it renders its
		 *	forms from has just changed (see admin.js's invalidate()).
		 *
		 *	That module reads every type's model exactly once per page load,
		 *	so without this a field added, renamed or removed here only
		 *	showed up over there after a full page reload. Called through its
		 *	own public entry point rather than by touching its state, and
		 *	guarded so this module still works on its own if admin.js is
		 *	not deployed alongside it. Every panel that shows elements is told
		 *	as well, by the stamp of the elements (see Nino.admin.touch()):
		 *	a type written is the elements of it written
		 *
		 *	@return		void
		 */
		_invalidateElements : function() {

			Nino.admin.touch( 'elements' );

			if( wn.Nino.admin.elements === undefined )
				return;

			Nino.admin.elements.invalidate();
		},

		/**
		 *	Run proceed() once the Elements form next door has been asked about
		 *	its unsaved input: every write of a type ends in _invalidateElements(),
		 *	which drops that form and what is typed into it
		 *
		 *	@param		{Function}	proceed
		 *	@param		{Function}	[onCancel]
		 *
		 *	@return		void
		 */
		_guardElements : function( proceed, onCancel ) {

			if( typeof Nino.admin.dirty !== 'object' ) {
				proceed();
				return;
			}

			Nino.admin.dirty.guard( [ 'elements' ], proceed, onCancel );
		},

		/**
		 *	Put one sentence at a field of the form: under the label that holds the control
		 *
		 *	@param		{Element}		label					The field the block returned
		 *	@param		{string}		text
		 *
		 *	@return		{Element}									The control that is wrong
		 */
		_say : function( label, text ) {

			const control = label.querySelector('input, select, textarea');

			Nino.adminUi.fieldError( label, control, 'types-error-'+ ( ++Nino.admin.types._errors ), text );

			return control;
		},

		/**
		 *	Everything that holds this save back, as { field, text } in the order the form shows it, where field is the
		 *	label the sentence stands under: the identifier of a new type, then for each card its name (missing, or taken
		 *	by another card), what a reference points at, and the whole numbers its options hold. The words are those of the
		 *	guide: "<Name> is missing.", "<Name> is not valid: <what is expected>.", "<Name> is already taken."
		 *
		 *	@return		{Array<Object>}
		 */
		_problems : function() {

			const types = Nino.admin.types;
			const text = function( key ) { return Nino.content.getText( key ) };
			const missing = function( name ) { return Nino.adminUi.format( text('/_admin/common/error/missing'), name ) };
			const invalid = function( name, expected ) { return Nino.adminUi.format( text('/_admin/types/error/invalid'), name, text( expected ) ) };
			const problems = [];

			if( types._uriField !== null ) {

				const uri = types._uriField.querySelector('input').value.trim();
				const name = text('/_admin/types/label/uri');

				if( uri === '' )
					problems.push( { field : types._uriField, text : missing( name ) } );
				else if( /^[a-z][a-z0-9_-]*$/.test( uri ) === false )
					problems.push( { field : types._uriField, text : invalid( name, '/_admin/types/expect/uri' ) } );
				else if( types._types.some( function( type ) { return type.uri === uri } ) === true )
					problems.push( { field : types._uriField, text : Nino.adminUi.format( text('/_admin/elements/error/taken'), name ) } );
			}

			const seen = {};

			types._cards.forEach( function( card ) {

				const field = card.read();
				const key = field.key.trim();
				const place = card.fields.own.hidden === true ? card.fields.name : card.fields.own;
				const parts = card.fields.parts();

				if( key === '' )
					problems.push( { field : place, text : missing( text('/_admin/types/label/fieldname') ) } );
				else if( ( field.originalKey === undefined || key !== field.originalKey ) && /^[A-Za-z0-9_-][A-Za-z0-9_.-]{0,63}$/.test( key ) === false ) // the form of \Nino\Modules\Elements\Types::FIELD_KEY
					problems.push( { field : place, text : invalid( text('/_admin/types/label/fieldname'), '/_admin/types/expect/key' ) } );
				else if( Object.prototype.hasOwnProperty.call( seen, key ) === true )
					problems.push( { field : place, text : Nino.adminUi.format( text('/_admin/types/error/field-twice'), key ) } );

				seen[key] = true;

				if( field.type === 'element' && parts.elementType !== undefined && ( field.elementType === '' || parts.elementType.disabled === true ) )
					problems.push( { field : parts.elementType.closest('label'), text : missing( text('/_admin/types/label/references') ) } );

				[ [ 'maxlength', 1, 'number', '/_admin/types/label/maxlength' ], [ 'inputsize', 1, 'number', '/_admin/types/label/inputsize' ], [ 'width', 1, 'number', '/_admin/common/label/width' ], [ 'height', 1, 'number', '/_admin/common/label/height' ], [ 'multipleMax', 0, 'max', '/_admin/types/label/max' ] ].forEach( function( check ) {

					const control = parts[check[0]];

					if( control === undefined || control.closest('label') === null || control.disabled === true )
						return;

					const value = control.value.trim();
					const name = text( check[3] );

					// A width and a height are what a picture is stored at: without them it has no size
					if( value === '' ) {
						if( field.type === 'image' && ( check[0] === 'width' || check[0] === 'height' ) )
							problems.push( { field : control.closest('label'), text : missing( name ) } );
						return;
					}

					if( /^\d+$/.test( value ) === false || parseInt( value, 10 ) < check[1] )
						problems.push( { field : control.closest('label'), text : invalid( name, '/_admin/types/expect/'+ check[2] ) } );
				} );
			} );

			return problems;
		},

		/**
		 *	Say where a save was refused: the sentences stand at their fields, the line of the bar says once that something
		 *	is wrong, and the focus goes to the first field
		 *
		 *	@param		{Array<Object>}	problems			What _problems() found
		 *
		 *	@return		void
		 */
		_show : function( problems ) {

			const controls = problems.map( function( problem ) { return Nino.admin.types._say( problem.field, problem.text ) } );

			Nino.admin.types._status.fail( Nino.content.getText('/_admin/types/error/fields') );

			if( controls[0] !== undefined && controls[0] !== null )
				controls[0].focus();
		},

		/**
		 *	Say what the server refused: in the line of the bar and, where it named a field of the form - the identifier
		 *	of the type - under that field
		 *
		 *	@param		{number}	status
		 *	@param		{*}				response
		 *
		 *	@return		void
		 */
		_refused : function( status, response ) {

			const types = Nino.admin.types;

			types._status.error( status, response, '/_admin/common/error/save' );

			const name = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';

			if( name === 'uri' && types._uriField !== null )
				types._say( types._uriField, Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) ).focus();
		},

		/**
		 *	Create or save the type currently open
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the type was written, false otherwise
		 *	@param		{boolean}		[guarded]			The unsaved input of an open element form was asked about already
		 *	@param		{boolean}		[confirmed]		The renames were asked about already
		 *
		 *	@return		void
		 */
		_save : function( done, guarded, confirmed ) {

			const types = Nino.admin.types;

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			if( types._saving === true || types._status === null ) {
				report( false );
				return;
			}

			if( guarded !== true ) {

				// What is wrong is said before the other form is asked about: nothing is sent, so nothing of it is lost
				Nino.adminUi.clearFieldErrors( dc.getElementById('types-edit-form') );
				types._storeFields();

				const problems = types._problems();

				if( problems.length > 0 ) {
					types._show( problems );
					report( false );
					return;
				}

				types._guardElements( function() { types._save( done, true ) }, function() { report( false ) } );
				return;
			}

			// A type that was not changed is not written: its file stays as it is, key for key
			if( types._isNew === false && types._formState() === types._baseline ) {
				types._status.saved();
				report( true );
				return;
			}

			const title = types._titleField.querySelector('input').value;
			const model = types._buildModel();
			const autoincrement = types._numberField.querySelector('input').checked;
			const renames = types._isNew === true ? {} : types._renames();

			// A rename moves stored values and leaves the rest where it is: said before it is made, with what it does
			// not reach
			if( Object.keys( renames ).length > 0 && confirmed !== true ) {

				const pairs = Object.keys( renames ).map( function( old ) { return old+ ' → '+ renames[old] } ).join(', ');

				// Asked again from the top once answered: a question that was not asked (another is open) ends the Save
				// like one that was refused
				if( Nino.adminUi.confirm( 'big', {
					title			: Nino.content.getText('/_admin/types/confirm/rename-title'),
					message		: Nino.adminUi.format( Nino.content.getText('/_admin/types/confirm/rename'), pairs ),
					yes				: Nino.content.getText('/_admin/types/confirm/rename-title'),
					no				: Nino.content.getText('/_admin/common/label/cancel'),
					onChoose	: function( ok ) {
						if( ok === true )
							types._save( done, true, true );
						else
							report( false );
					},
				} ) === false )
					report( false );

				return;
			}

			types._saving = true;
			types._status.saving();

			// The form is drawn again from what the server holds once it is written: the names the fields are saved under
			// are the ones they have now, and what the server made of a model - a flag that means nothing for a type is
			// dropped - is what the form shows
			const written = function( uri, notice ) {

				types._saving = false;
				types._notice = notice;
				types._isNew = false;
				types._currentUri = uri;
				Nino.admin.router.set( 'types', [ uri ] );
				types._invalidateElements();

				types._openForm( uri, types._opener, false, function( ok ) {

					// The type is written either way: only the form that shows it may be missing
					if( ok === true ) {
						types._status.saved();
						types._snapshot();
						types._bar.primary.focus();
					}

					report( true );
				} );

				types._reloadList();
			};

			if( types._isNew === true ) {

				types._apiCall( 'create', { uri : types._uriField.querySelector('input').value.trim(), title : title, model : model, autoincrement : autoincrement }, function( status, response ) {
					if( status !== 200 || response === null ) {
						types._saving = false;
						types._refused( status, response );
						report( false );
						return;
					}
					written( response.uri, '' );
				} );
				return;
			}

			types._apiCall( 'save', { uri : types._currentUri, title : title, model : model, autoincrement : autoincrement, renames : renames }, function( status, response ) {
				if( status !== 200 || response === null ) {
					types._saving = false;
					types._refused( status, response );
					report( false );
					return;
				}
				written( types._currentUri, types._referenceNotice( response.references ) );
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.types.init );

	// The shell asks before anything throws the open type's input away (see
	// Nino.admin.dirty)
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'types', function() { return dc.getElementById('types-form') }, function( done ) { Nino.admin.types._save( done ) } );

})(window, document, document.documentElement, document.body);
