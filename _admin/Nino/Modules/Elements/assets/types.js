

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Element Types" module: create/edit an element type's
 *													title + model (field definitions) only - never touches a
 *													type's actual content ('*' and locale buckets with real
 *													elements), that part of the file is read back untouched
 *													and saved right along with it server-side. Deleting a type
 *													does destroy that content, and is offered anyway - behind
 *													a typed confirmation, see _renderDangerZone(). A field can
 *													be renamed - its values move with it, see _renames() -
 *													and a type duplicated, see _renderDuplicate().
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.elementTypes = {

		// The keys a field is offered as, before an own one: the words of the
		// vocabulary fields most often are (/_admin/common/word/<key>, the ones
		// the Elements form names by it too)
		KEY_WORDS 			: [ 'title', 'subtitle', 'text', 'description', 'image', 'alt', 'caption', 'link', 'label', 'name', 'email', 'phone', 'address', 'date', 'author', 'price', 'icon' ],

		_types 					: [],
		_fieldTypes 		: [],
		// The field types a unit/suffix applies to, as the server states them
		// (Types.php's SUFFIX_TYPES): the rule lives there, the editor only
		// asks. Empty until the list has answered
		_suffixTypes 		: [],
		_currentUri 		: null,
		_isNew 					: false,
		_fields 				: [],
		_ready 					: false,
		// What deleting the open type would cost, as apiGet() reported it:
		// how many elements are in the file, and which other types' element
		// fields point at it (any at all means the server refuses)
		_elementCount 	: 0,
		_referencedBy 	: [],
		// The type to open once the list has loaded - the copy a duplication
		// just made (see _duplicate())
		_openUri 				: null,
		// What the last save of a rename left that it cannot move, shown once
		// above the type list - the form it happened in is gone by then
		_notice 				: '',

		/**
		 *	Load every element type and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('types-list') === null )
				return;

			Nino.admin.elementTypes._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.elementTypes._showError( dc.getElementById('types-list'), status, response );

				Nino.admin.elementTypes._types 			= response.types;
				Nino.admin.elementTypes._fieldTypes = response.fieldTypes;
				Nino.admin.elementTypes._suffixTypes = response.suffixTypes ?? [];
				Nino.admin.elementTypes._renderList();
				Nino.admin.elementTypes._showList();
				Nino.admin.elementTypes._ready = true;

				const open = Nino.admin.elementTypes._openUri;
				Nino.admin.elementTypes._openUri = null;

				if( open !== null )
					Nino.admin.elementTypes._openForm( open );
			} );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on - called
		 *	when the tab is switched to, once Nino.admin.TABS grows a second entry
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.elementTypes._ready === false )
				return;

			if( dc.getElementById('types-form').classList.contains('admin-hidden') === false )
				return Nino.admin.elementTypes._showForm();

			Nino.admin.elementTypes._showList();
		},

		/**
		 *	Call a devtypes/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "devtypes/list")
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

		_showList : function() {
			dc.getElementById('types-list').classList.remove('admin-hidden');
			dc.getElementById('types-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('types-list').classList.add('admin-hidden');
			dc.getElementById('types-form').classList.remove('admin-hidden');
		},

		/**
		 *	Render the type list, plus an "add new" action below it
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('types-list');
			wrap.innerHTML = '';

			if( Nino.admin.elementTypes._notice !== '' ) {
				const notice = dc.createElement('p');
				notice.className = 'nino-admin-hint';
				notice.setAttribute( 'aria-live', 'polite' );
				notice.textContent = Nino.admin.elementTypes._notice;
				wrap.appendChild( notice );
				Nino.admin.elementTypes._notice = '';
			}

			if( Nino.admin.elementTypes._types.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/types/empty') ) );

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			Nino.admin.elementTypes._types.forEach( function( type ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';

				const copy = dc.createElement('span');
				copy.className = 'nino-admin-list-copy';
				const title = dc.createElement('strong');
				title.textContent = type.title;
				const descr = dc.createElement('small');
				descr.textContent = '/'+ type.uri+ ' · '+ Nino.content.getText( type.fieldCount === 1 ? '/_admin/types/label/field' : '/_admin/types/label/fields' ).replace( '%d', String( type.fieldCount ) );
				copy.appendChild( title );
				copy.appendChild( descr );
				link.appendChild( copy );

				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.elementTypes._openForm( type.uri ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			if( Nino.admin.elementTypes._types.length > 0 )
				wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'nino-admin-btn-primary';
			addBtn.textContent = Nino.content.getText('/_admin/types/label/new');
			addBtn.addEventListener( 'click', function() { Nino.admin.elementTypes._openForm( null ) } );
			wrap.appendChild( Nino.adminUi.listActions( [ addBtn ] ) );
		},

		/**
		 *	Open the editor for an existing type, or a blank one for a new type
		 *
		 *	@param		{string|null}	uri			Type uri, or null to create a new one
		 *
		 *	@return		void
		 */
		_openForm : function( uri ) {

			if( uri === null ) {
				Nino.admin.elementTypes._isNew 			= true;
				Nino.admin.elementTypes._currentUri = null;
				Nino.admin.elementTypes._fields 		= [];
				Nino.admin.elementTypes._elementCount = 0;
				Nino.admin.elementTypes._referencedBy = [];
				Nino.admin.elementTypes._renderForm( '', false, '00001' );
				Nino.admin.elementTypes._showForm();
				return;
			}

			Nino.admin.elementTypes._apiCall( 'get', { uri : uri }, function( status, response ) {

				// Shown as well as written - the pane this error goes into is
				// the one the list is covering, so without the _showForm() the
				// list stayed on screen and clicking a type that no longer
				// parses (or one an account just lost the permission for) did
				// nothing visible at all. Same rule as the Elements panel next
				// door, which its own suite enforces
				if( status !== 200 || response === null ) {
					Nino.admin.elementTypes._showError( dc.getElementById('types-form'), status, response );
					Nino.admin.elementTypes._showForm();
					return;
				}

				Nino.admin.elementTypes._isNew 			= false;
				Nino.admin.elementTypes._currentUri = response.uri;
				Nino.admin.elementTypes._fields 		= Object.keys( response.model ).map( function( key ) {
					// The name it is saved under: a field renamed here is moved from this
					// one to the new (see _renames())
					return Object.assign( { key : key, originalKey : key }, response.model[key] );
				} );
				Nino.admin.elementTypes._elementCount = response.elements ?? 0;
				Nino.admin.elementTypes._referencedBy = response.referencedBy ?? [];
				Nino.admin.elementTypes._renderForm( response.title, response.autoincrement === true, response.next );
				Nino.admin.elementTypes._showForm();
			} );
		},

		/**
		 *	Render one field's row: key, type, and whichever options apply to
		 *	that type (locale/html/required always; maxlength and inputsize for string;
		 *	width+height and the field holding the alt text for image; the referenced type for element; suffix for
		 *	the types the server names, see _suffixTypes; options for a fixed value list)
		 *
		 *	@param		{Object}	field					{ key, originalKey, type, locale, html, blocks, breaks, required, maxlength, inputsize, width, height, alt, elementType, suffix, options }
		 *	@param		{number}	index					Index into _fields, for the remove button
		 *
		 *	@return		{Element}
		 */
		_renderFieldRow : function( field, index ) {

			const row = dc.createElement('fieldset');
			row.className = 'admin-field-row';

			// The key of a field is one of the words fields most often are -
			// "Titel", "Bild", "Preis" - or one of the type's own. The choice puts
			// the word into the text field, which is what is read back and saved
			// and is shown only for an own key, with the checks it always had
			const keySelect = dc.createElement('select');
			keySelect.className = 'admin-field-key-select';
			keySelect.setAttribute( 'aria-label', Nino.content.getText('/_admin/types/label/fieldname') );
			Nino.admin.elementTypes.KEY_WORDS.forEach( function( word ) {
				const opt = dc.createElement('option');
				opt.value = word;
				opt.textContent = Nino.content.getText('/_admin/common/word/'+ word) || word;
				opt.selected = ( word === field.key );
				keySelect.appendChild( opt );
			} );
			const ownOpt = dc.createElement('option');
			ownOpt.value = '';
			ownOpt.textContent = Nino.content.getText('/_admin/types/label/ownkey');
			ownOpt.selected = ( Nino.admin.elementTypes.KEY_WORDS.indexOf( field.key ?? '' ) === -1 );
			keySelect.appendChild( ownOpt );
			row.appendChild( keySelect );

			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.placeholder = Nino.content.getText('/_admin/types/label/fieldname');
			keyInput.setAttribute( 'aria-label', Nino.content.getText('/_admin/types/label/ownkey') );
			keyInput.value = field.key ?? '';
			keyInput.className = 'admin-field-key';
			keyInput.hidden = ( Nino.admin.elementTypes.KEY_WORDS.indexOf( field.key ?? '' ) !== -1 );
			row.appendChild( keyInput );

			keySelect.addEventListener( 'change', function() {

				const word = keySelect.value;
				keyInput.hidden = ( word !== '' );

				if( word !== '' )
					keyInput.value = word;
				else
					keyInput.focus();

				// What the field is called changed, whichever way: the hint that says
				// it is renamed follows it (see below)
				keyInput.dispatchEvent( new wn.Event( 'input' ) );
			} );

			// A field that is saved keeps the name it was saved under on the row, so
			// a rename can be told from a new field however the rows are moved or
			// redrawn, and says so while it differs
			if( field.originalKey !== undefined ) {

				row.dataset.originalKey = field.originalKey;

				const renamed = dc.createElement('p');
				renamed.className = 'nino-admin-hint admin-field-renamed';
				renamed.textContent = Nino.content.getText('/_admin/types/hint/renamed');

				const showRenamed = function() {
					const key = keyInput.value.trim();
					renamed.hidden = ( key === '' || key === field.originalKey );
				};

				keyInput.addEventListener( 'input', showRenamed );
				showRenamed();
				row.appendChild( renamed );
			}

			const typeSelect = dc.createElement('select');
			typeSelect.className = 'admin-field-type';
			Nino.admin.elementTypes._fieldTypes.forEach( function( t ) {
				const opt = dc.createElement('option');
				opt.value = t;
				opt.textContent = t;
				opt.selected = ( t === field.type );
				typeSelect.appendChild( opt );
			} );
			row.appendChild( typeSelect );

			const optionsWrap = dc.createElement('div');
			optionsWrap.className = 'admin-field-options';
			row.appendChild( optionsWrap );

			function renderTypeOptions() {

				optionsWrap.innerHTML = '';
				const type = typeSelect.value;

				const localeLabel = dc.createElement('label');
				const localeCheck = dc.createElement('input');
				localeCheck.type = 'checkbox';
				localeCheck.className = 'admin-field-locale';
				localeCheck.checked = field.locale === true;
				localeLabel.appendChild( localeCheck );
				localeLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/types/label/perlocale') ) );
				optionsWrap.appendChild( localeLabel );

				// Never offered for an image: its file is uploaded separately,
				// after the element already exists (see admin.js's image
				// branch - a new element has no uri to attach an upload to yet),
				// so a required image could never be filled in on the very save
				// that would have to satisfy it. The element would simply be
				// impossible to create. Types.php's cleanModel() drops the flag
				// on save too, so a type file that carries one from before loses
				// it the next time it is saved here
				if( type !== 'image' ) {
					const requiredLabel = dc.createElement('label');
					const requiredCheck = dc.createElement('input');
					requiredCheck.type = 'checkbox';
					requiredCheck.className = 'admin-field-required';
					requiredCheck.checked = field.required === true;
					requiredLabel.appendChild( requiredCheck );
					requiredLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/types/label/required') ) );
					optionsWrap.appendChild( requiredLabel );
				}

				// Offered for the types the server keeps a suffix for - the ones
				// that render an input a unit can sit next to, which is neither
				// a boolean's "Ja"/"Nein" choice, nor an image's upload area, nor
				// an element reference's select. The editor used to state that
				// rule itself, one type short, and offered a unit on a reference
				// that the save then dropped in silence
				if( Nino.admin.elementTypes._suffixTypes.indexOf( type ) !== -1 ) {
					const suffixInput = dc.createElement('input');
					suffixInput.type = 'text';
					suffixInput.className = 'admin-field-suffix';
					suffixInput.placeholder = Nino.content.getText('/_admin/types/placeholder/suffix');
					suffixInput.value = field.suffix ?? '';
					optionsWrap.appendChild( suffixInput );
				}

				if( type === 'string' ) {
					const htmlLabel = dc.createElement('label');
					const htmlCheck = dc.createElement('input');
					htmlCheck.type = 'checkbox';
					htmlCheck.className = 'admin-field-html';
					htmlCheck.checked = field.html === true;
					htmlLabel.appendChild( htmlCheck );
					htmlLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/types/label/richtext') ) );
					optionsWrap.appendChild( htmlLabel );

					// What goes with the one above: paragraphs and lists for rich
					// text, line breaks for plain text - each offered only while it
					// can apply, and dropped by Types.php's cleanModel() where it
					// cannot
					const blocksLabel = dc.createElement('label');
					const blocksCheck = dc.createElement('input');
					blocksCheck.type = 'checkbox';
					blocksCheck.className = 'admin-field-blocks';
					blocksCheck.checked = field.blocks === true;
					blocksLabel.appendChild( blocksCheck );
					blocksLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/types/label/blocks') ) );
					optionsWrap.appendChild( blocksLabel );

					const breaksLabel = dc.createElement('label');
					const breaksCheck = dc.createElement('input');
					breaksCheck.type = 'checkbox';
					breaksCheck.className = 'admin-field-breaks';
					breaksCheck.checked = field.breaks === true;
					breaksLabel.appendChild( breaksCheck );
					breaksLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/types/label/breaks') ) );
					optionsWrap.appendChild( breaksLabel );

					const syncFormat = function() {
						blocksCheck.disabled = htmlCheck.checked === false;
						breaksCheck.disabled = htmlCheck.checked === true;
					};

					htmlCheck.addEventListener( 'change', syncFormat );
					syncFormat();

					const maxlengthInput = dc.createElement('input');
					maxlengthInput.type = 'number';
					maxlengthInput.min = '1';
					maxlengthInput.className = 'admin-field-maxlength';
					maxlengthInput.placeholder = Nino.content.getText('/_admin/types/placeholder/maxlength');
					maxlengthInput.value = field.maxlength ?? '';
					optionsWrap.appendChild( maxlengthInput );

					// Rows the field's input opens with - a hint for the form, not
					// a limit on the value (see Types.php's cleanModel())
					const inputsizeInput = dc.createElement('input');
					inputsizeInput.type = 'number';
					inputsizeInput.min = '1';
					inputsizeInput.className = 'admin-field-inputsize';
					inputsizeInput.placeholder = Nino.content.getText('/_admin/types/placeholder/inputsize');
					inputsizeInput.value = field.inputsize ?? '';
					optionsWrap.appendChild( inputsizeInput );

					const optionsInput = dc.createElement('input');
					optionsInput.type = 'text';
					optionsInput.className = 'admin-field-select-options';
					optionsInput.placeholder = Nino.content.getText('/_admin/types/label/options');
					optionsInput.value = ( field.options ?? [] ).join(', ');
					optionsWrap.appendChild( optionsInput );
				}

				// Which type this reference may point at. Part of the field, not
				// of the value: it is what the element form builds its select
				// of elements from, so a reference without one has nothing to
				// offer (Types.php's _unknownReferencedType() rejects the save).
				// A brand-new type is not in this list yet - it has no file on
				// disk to reference - so a self-reference is added by reopening
				// the type once it exists
				if( type === 'element' ) {
					const refLabel = dc.createElement('label');
					refLabel.className = 'nino-admin-field';
					const refSpan = dc.createElement('span');
					refSpan.textContent = Nino.content.getText('/_admin/types/label/references');
					refLabel.appendChild( refSpan );

					const refSelect = dc.createElement('select');
					refSelect.className = 'admin-field-element-type';

					const others = Nino.admin.elementTypes._types.filter( function( t ) {
						return t.uri !== Nino.admin.elementTypes._currentUri;
					} );

					if( others.length === 0 ) {
						const empty = dc.createElement('option');
						empty.value = '';
						empty.textContent = Nino.content.getText('/_admin/types/label/references-empty');
						refSelect.appendChild( empty );
						refSelect.disabled = true;
					}

					others.forEach( function( t ) {
						const opt = dc.createElement('option');
						opt.value = t.uri;
						opt.textContent = t.title+ ' ('+ t.uri+ ')';
						opt.selected = ( t.uri === field.elementType );
						refSelect.appendChild( opt );
					} );

					// A reference whose target was deleted since keeps showing what
					// it points at, rather than silently re-pointing at whichever
					// type happens to sort first
					if( field.elementType && others.some( function( t ) { return t.uri === field.elementType } ) === false ) {
						const dangling = dc.createElement('option');
						dangling.value = field.elementType;
						dangling.textContent = field.elementType+ ' ('+ Nino.content.getText('/_admin/elements/label/reference-missing')+ ')';
						dangling.selected = true;
						refSelect.appendChild( dangling );
						refSelect.disabled = false;
					}

					refLabel.appendChild( refSelect );
					optionsWrap.appendChild( refLabel );

					// How many elements the field may hold. Two controls because
					// the model asks two things: whether this is a list at all
					// (an absent key is the single reference every existing type
					// still means), and where it stops. 0 is the honest way to
					// say "no ceiling" - the alternative, an empty box meaning
					// unlimited, is indistinguishable from one nobody filled in
					const multiLabel = dc.createElement('label');
					const multiCheck = dc.createElement('input');
					multiCheck.type = 'checkbox';
					multiCheck.className = 'admin-field-multiple';
					multiCheck.checked = typeof field.multiple === 'number';
					multiLabel.appendChild( multiCheck );
					multiLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/types/label/multiple') ) );
					optionsWrap.appendChild( multiLabel );

					const maxInput = dc.createElement('input');
					maxInput.type = 'number';
					maxInput.min = '0';
					maxInput.className = 'admin-field-multiple-max';
					maxInput.placeholder = Nino.content.getText('/_admin/types/placeholder/max');
					maxInput.value = ( typeof field.multiple === 'number' ) ? String( field.multiple ) : '';
					maxInput.disabled = ( multiCheck.checked === false );
					optionsWrap.appendChild( maxInput );

					multiCheck.addEventListener( 'change', function() {
						maxInput.disabled = ( multiCheck.checked === false );
						if( multiCheck.checked === true && maxInput.value === '' )
							maxInput.value = '0';
					} );
				}

				if( type === 'image' ) {
					const widthInput = dc.createElement('input');
					widthInput.type = 'number';
					widthInput.min = '1';
					widthInput.className = 'admin-field-width';
					widthInput.placeholder = Nino.content.getText('/_admin/common/label/width');
					widthInput.value = field.width ?? '';
					optionsWrap.appendChild( widthInput );

					const heightInput = dc.createElement('input');
					heightInput.type = 'number';
					heightInput.min = '1';
					heightInput.className = 'admin-field-height';
					heightInput.placeholder = Nino.content.getText('/_admin/common/label/height');
					heightInput.value = field.height ?? '';
					optionsWrap.appendChild( heightInput );

					// Which string field holds this picture's alt text. Only a field
					// that can: written per language, plain text, and not the image
					// itself (Types.php's cleanModel() decides the same on save).
					// Offered from the rows as they stand now, so a field added a
					// moment ago is there - the list is rebuilt when the control
					// takes the focus, keeping what is chosen. A field is named by
					// the name it was saved under (and by its key while it is new),
					// shown by the key it has now: renaming it does not unlink it,
					// and apiSave() reads the link through the renames
					const altLabel = dc.createElement('label');
					altLabel.className = 'nino-admin-field';
					const altSpan = dc.createElement('span');
					altSpan.textContent = Nino.content.getText('/_admin/types/label/alt');
					altLabel.appendChild( altSpan );

					const altSelect = dc.createElement('select');
					altSelect.className = 'admin-field-alt';

					const fillAlt = function( current ) {
						altSelect.innerHTML = '';

						const none = dc.createElement('option');
						none.value = '';
						none.textContent = Nino.content.getText('/_admin/types/option/alt-none');
						altSelect.appendChild( none );

						Nino.admin.elementTypes._fields.forEach( function( other ) {
							if( other.key === '' || other.key === keyInput.value || other.type !== 'string' || other.locale !== true || other.html === true )
								return;
							const name = other.originalKey ?? other.key;
							const opt = dc.createElement('option');
							opt.value = name;
							opt.textContent = other.key;
							opt.selected = ( name === current );
							altSelect.appendChild( opt );
						} );
					};

					fillAlt( field.alt ?? '' );
					altSelect.addEventListener( 'focus', function() {
						const current = altSelect.value;
						Nino.admin.elementTypes._storeFields();
						fillAlt( current );
					} );

					altLabel.appendChild( altSelect );
					optionsWrap.appendChild( altLabel );
				}
			}

			typeSelect.addEventListener( 'change', renderTypeOptions );
			renderTypeOptions();

			const actions = dc.createElement('div');
			actions.className = 'admin-field-actions';

			// Same ↑/↓ pair the Routes list uses (see its admin.js's _move()) - a
			// field's position in the model is the order the element form
			// renders it in, so this is a real editing control, not just a way
			// to tidy up this list
			const move = dc.createElement('span');
			move.className = 'admin-field-move';

			const up = dc.createElement('button');
			up.type = 'button';
			up.title = Nino.content.getText('/_admin/common/label/moveup');
			// title is a hover hint, not a name - see the same pair in the Routes
			// and Navigations lists, and Nino.adminUi.elementList()'s button()
			up.setAttribute( 'aria-label', Nino.content.getText('/_admin/common/label/moveup') );
			up.textContent = '↑';
			up.disabled = index === 0;
			up.addEventListener( 'click', function() { Nino.admin.elementTypes._move( index, 'up' ) } );
			move.appendChild( up );

			const down = dc.createElement('button');
			down.type = 'button';
			down.title = Nino.content.getText('/_admin/common/label/movedown');
			down.setAttribute( 'aria-label', Nino.content.getText('/_admin/common/label/movedown') );
			down.textContent = '↓';
			down.disabled = index === Nino.admin.elementTypes._fields.length - 1;
			down.addEventListener( 'click', function() { Nino.admin.elementTypes._move( index, 'down' ) } );
			move.appendChild( down );

			actions.appendChild( move );

			const removeBtn = dc.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'nino-admin-btn-danger';
			removeBtn.textContent = Nino.content.getText('/_admin/common/label/remove');
			removeBtn.addEventListener( 'click', function() {
				// Read the rows back first, same as _move()/"Add field" do -
				// every row is re-rendered from _fields below, so without this
				// dropping one row would silently revert every edit typed into
				// the others since the last render
				Nino.admin.elementTypes._storeFields();
				Nino.admin.elementTypes._fields.splice( index, 1 );
				Nino.admin.elementTypes._renderFields();
			} );
			actions.appendChild( removeBtn );

			row.appendChild( actions );

			row.dataset.index = index;
			return row;
		},

		/**
		 *	Swap a field row with its neighbour and re-render.
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

			// Rows are re-rendered from _fields, so whatever is currently typed
			// into them has to be read back first - otherwise moving a row
			// would revert every edit made since the last render
			Nino.admin.elementTypes._storeFields();

			const fields 		= Nino.admin.elementTypes._fields;
			const swapWith 	= direction === 'up' ? index - 1 : index + 1;

			if( swapWith < 0 || swapWith >= fields.length )
				return;

			[ fields[index], fields[swapWith] ] = [ fields[swapWith], fields[index] ];

			Nino.admin.elementTypes._renderFields();
		},

		/**
		 *	Re-render every field row into #admin-fields-wrap
		 *
		 *	@return		void
		 */
		_renderFields : function() {
			const wrap = dc.getElementById('admin-fields-wrap');
			wrap.innerHTML = '';
			Nino.admin.elementTypes._fields.forEach( function( field, index ) {
				wrap.appendChild( Nino.admin.elementTypes._renderFieldRow( field, index ) );
			} );
		},

		/**
		 *	Read every field row's current dom state back into _fields, so
		 *	adding/removing a row (which re-renders) never loses in-progress edits
		 *
		 *	@return		void
		 */
		_storeFields : function() {
			const rows = dc.querySelectorAll('#admin-fields-wrap .admin-field-row');
			const fields = [];
			rows.forEach( function( row ) {
				const options = row.querySelector('.admin-field-select-options');
				fields.push( {
					key 			: row.querySelector('.admin-field-key').value,
					// Undefined on a field that is not saved yet - see _renames()
					originalKey : row.dataset.originalKey,
					type 			: row.querySelector('.admin-field-type').value,
					locale 		: row.querySelector('.admin-field-locale').checked,
					// Absent on an image row, which is never offered the checkbox
					// (see _renderFieldRow()) - false, not "keep whatever was there"
					required 	: ( row.querySelector('.admin-field-required')?.checked ) ?? false,
					html 			: ( row.querySelector('.admin-field-html')?.checked ) ?? false,
					blocks 		: ( row.querySelector('.admin-field-blocks')?.checked ) ?? false,
					breaks 		: ( row.querySelector('.admin-field-breaks')?.checked ) ?? false,
					maxlength : row.querySelector('.admin-field-maxlength')?.value,
					inputsize : row.querySelector('.admin-field-inputsize')?.value,
					width 		: row.querySelector('.admin-field-width')?.value,
					height 		: row.querySelector('.admin-field-height')?.value,
					// The string field that holds an image's alt text - absent on every row but an image
					alt 			: ( row.querySelector('.admin-field-alt')?.value ) ?? '',
					suffix 		: ( row.querySelector('.admin-field-suffix')?.value ) ?? '',
					// Absent on every row but an element reference
					elementType : ( row.querySelector('.admin-field-element-type')?.value ) ?? '',
					// Whether that reference holds a list, and its ceiling. Sent
					// as the two controls collect them; Types.php's cleanModel()
					// is what folds them into the model's single 'multiple' int
					multiple 		: ( row.querySelector('.admin-field-multiple')?.checked ) ?? false,
					multipleMax : row.querySelector('.admin-field-multiple-max')?.value,
					options 	: options ? options.value.split(',').map( function(s) { return s.trim() } ).filter( function(s) { return s !== '' } ) : [],
				} );
			} );
			Nino.admin.elementTypes._fields = fields;
		},

		/**
		 *	Render the type editor: back-link, uri (editable only when new),
		 *	title, the uri form, every field row, "add field", save
		 *
		 *	@param		{string}	title
		 *	@param		{boolean}	autoincrement	Whether this type numbers its own elements
		 *	@param		{string}	next					The uri the next element would get, for the hint
		 *
		 *	@return		void
		 */
		_renderForm : function( title, autoincrement, next ) {

			const wrap = dc.getElementById('types-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.elementTypes._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');

			if( Nino.admin.elementTypes._isNew === true ) {
				const uriLabel = dc.createElement('label');
				uriLabel.className = 'nino-admin-field';
				const uriSpan = dc.createElement('span');
				uriSpan.textContent = Nino.content.getText('/_admin/types/label/uri');
				uriLabel.appendChild( uriSpan );
				const uriInput = dc.createElement('input');
				uriInput.type = 'text';
				uriInput.id = 'admin-form-uri';
				uriInput.required = true;
				uriLabel.appendChild( uriInput );
				form.appendChild( uriLabel );
			}

			const titleLabel = dc.createElement('label');
			titleLabel.className = 'nino-admin-field';
			const titleSpan = dc.createElement('span');
			titleSpan.textContent = Nino.content.getText('/_admin/types/label/title');
			titleLabel.appendChild( titleSpan );
			const titleInput = dc.createElement('input');
			titleInput.type = 'text';
			titleInput.id = 'admin-form-title';
			titleInput.value = title;
			titleLabel.appendChild( titleInput );
			form.appendChild( titleLabel );

			// How an element of this type gets its uri. A type whose entries have
			// a name worth putting in a url ("/team/ada") is asked for one; a type
			// whose entries have no natural name - a gallery image, a price row -
			// is better off numbered than made to invent one per entry, which is
			// how it ends up with "bild-2", "bild-2-neu", "bild-2-final".
			const autoWrap = dc.createElement('fieldset');
			autoWrap.className = 'nino-admin-card';
			const autoLegend = dc.createElement('legend');
			autoLegend.textContent = Nino.content.getText('/_admin/types/label/uris');
			autoWrap.appendChild( autoLegend );

			const autoSwitch = Nino.adminUi.switchField( {
				key 			: 'autoincrement',
				checked 	: autoincrement === true,
				label 		: Nino.content.getText('/_admin/types/label/numbering'),
				hint 			: Nino.content.getText('/_admin/types/hint/numbering').replace( '%s', '/'+ ( Nino.admin.elementTypes._currentUri ?? '<type>' )+ '/'+ ( next || '00001' ) ),
			} );
			autoSwitch.id = 'admin-form-autoincrement';
			autoWrap.appendChild( autoSwitch );

			// Only the elements added from here on are numbered. Saying so beats
			// letting someone discover it, and it is the reason turning this on is
			// not a destructive change.
			if( autoincrement !== true && Nino.admin.elementTypes._isNew === false ) {
				const autoNote = dc.createElement('p');
				autoNote.className = 'nino-admin-hint';
				autoNote.textContent = Nino.content.getText('/_admin/types/hint/numbering-existing');
				autoWrap.appendChild( autoNote );
			}

			form.appendChild( autoWrap );

			const fieldsWrap = dc.createElement('div');
			fieldsWrap.id = 'admin-fields-wrap';
			form.appendChild( fieldsWrap );

			const addFieldBtn = dc.createElement('button');
			addFieldBtn.type = 'button';
			addFieldBtn.textContent = Nino.content.getText('/_admin/types/label/addfield');
			addFieldBtn.addEventListener( 'click', function() {
				Nino.admin.elementTypes._storeFields();
				Nino.admin.elementTypes._fields.push( { key : '', type : 'string' } );
				Nino.admin.elementTypes._renderFields();
			} );
			form.appendChild( addFieldBtn );

			// Last things in the form body, below the fields and above the
			// pinned actions row: nothing here is reached on the way to Save
			if( Nino.admin.elementTypes._isNew === false ) {
				form.appendChild( Nino.admin.elementTypes._renderDuplicate() );
				form.appendChild( Nino.admin.elementTypes._renderDangerZone() );
			}

			// Save + its message in the shared actions row every module's form
			// ends on - style.css pins that row to the bottom of the
			// viewport, so a long field list never puts Save out of reach
			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'admin-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.elementTypes._save() } );

			wrap.appendChild( form );
			Nino.admin.elementTypes._renderFields();

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'types' );
		},

		/**
		 *	Copy the open type under a new uri. The copy is made here, from the form
		 *	as it stands - its title, its fields and whether it numbers its
		 *	elements - and created like any new type, so what the server keeps of
		 *	it is what it keeps of any model: the defaults a new element starts with
		 *	and a hand-written whitelist or callback stay with the original
		 *
		 *	@return		{Element}
		 */
		_renderDuplicate : function() {

			const wrap = dc.createElement('fieldset');
			wrap.className = 'nino-admin-card admin-type-duplicate';

			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/types/label/duplicate');
			wrap.appendChild( legend );

			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.content.getText('/_admin/types/hint/duplicate');
			wrap.appendChild( hint );

			const row = dc.createElement('div');
			row.className = 'admin-type-duplicate-row';

			// What is typed here is not an edit of the type
			const uriInput = dc.createElement('input');
			uriInput.type = 'text';
			uriInput.id = 'admin-form-duplicate-uri';
			uriInput.autocomplete = 'off';
			uriInput.dataset.dirty = 'ignore';
			uriInput.placeholder = Nino.content.getText('/_admin/types/placeholder/duplicate-uri');
			uriInput.setAttribute( 'aria-label', Nino.content.getText('/_admin/types/placeholder/duplicate-uri') );
			row.appendChild( uriInput );

			const titleInput = dc.createElement('input');
			titleInput.type = 'text';
			titleInput.id = 'admin-form-duplicate-title';
			titleInput.autocomplete = 'off';
			titleInput.dataset.dirty = 'ignore';
			titleInput.placeholder = Nino.content.getText('/_admin/types/placeholder/duplicate-title');
			titleInput.setAttribute( 'aria-label', Nino.content.getText('/_admin/types/placeholder/duplicate-title') );
			row.appendChild( titleInput );

			const button = dc.createElement('button');
			button.type = 'button';
			button.className = 'nino-admin-btn-secondary';
			button.textContent = Nino.content.getText('/_admin/types/label/duplicate');
			button.addEventListener( 'click', function() { Nino.admin.elementTypes._duplicate() } );
			row.appendChild( button );

			wrap.appendChild( row );

			const msg = dc.createElement('p');
			msg.id = 'admin-form-duplicate-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( msg );

			return wrap;
		},

		/**
		 *	Send the duplication the form above asks for, then open the copy.
		 *	Opening it drops this form, and the Elements form next door is
		 *	dropped by what a new type is, so unsaved input is asked about first
		 *
		 *	@param		{boolean}	[guarded]			The unsaved input was asked about already
		 *
		 *	@return		void
		 */
		_duplicate : function( guarded ) {

			const uri 		= Nino.admin.elementTypes._currentUri;
			const newUri 	= dc.getElementById('admin-form-duplicate-uri').value.trim();
			let title 			= dc.getElementById('admin-form-duplicate-title').value.trim();
			const msg 		= dc.getElementById('admin-form-duplicate-msg');

			if( uri === null )
				return;

			if( guarded !== true ) {

				if( typeof Nino.admin.dirty !== 'object' ) {
					Nino.admin.elementTypes._duplicate( true );
					return;
				}

				Nino.admin.dirty.guard( [ 'types', 'elements' ], function() { Nino.admin.elementTypes._duplicate( true ) } );
				return;
			}

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			const model 					= Nino.admin.elementTypes._buildModel();
			const renames 				= Nino.admin.elementTypes._renames();
			const autoincrement 	= dc.querySelector('#admin-form-autoincrement input').checked;

			// The copy has no saved names: an alt link is made to the name its
			// field has now
			Object.keys( model ).forEach( function( key ) {
				if( Object.prototype.hasOwnProperty.call( renames, model[key].alt ) === true )
					model[key].alt = renames[model[key].alt];
			} );

			if( title === '' )
				title = dc.getElementById('admin-form-title').value.trim();

			Nino.admin.elementTypes._apiCall( 'create', { uri : newUri, title : title, model : model, autoincrement : autoincrement }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					return;
				}

				Nino.admin.elementTypes._openUri 		= response.uri;
				Nino.admin.elementTypes._currentUri = null;
				Nino.admin.elementTypes._fields 		= [];
				Nino.admin.elementTypes.init();
				Nino.admin.elementTypes._invalidateElements();
			} );
		},

		/**
		 *	The one control in this module that destroys content: delete the
		 *	open type, its elements and the images those elements own.
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
		 *	    at all, and says which field holds the reference
		 *	  - the button stays disabled until the type's own uri is typed
		 *	    into the input next to it, so no single click reaches it
		 *
		 *	Every one of these is re-checked server-side in
		 *	\Nino\Modules\Elements\Types::apiDelete() - this is the readable
		 *	half, not the enforcing one.
		 *
		 *	@return		{Element}
		 */
		_renderDangerZone : function() {

			const uri 	= Nino.admin.elementTypes._currentUri;
			const wrap 	= dc.createElement('fieldset');
			wrap.className = 'nino-admin-card admin-type-danger';

			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/types/label/delete');
			wrap.appendChild( legend );

			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.content.getText('/_admin/types/hint/delete')
				.replace( '%s', '/'+ uri )
				.replace( '%d', String( Nino.admin.elementTypes._elementCount ) );
			wrap.appendChild( hint );

			// Refused rather than warned about: deleting it would leave those
			// fields pointing at a type that no longer exists, and no later
			// save of theirs could tell the difference
			if( Nino.admin.elementTypes._referencedBy.length > 0 ) {
				const blocked = dc.createElement('p');
				blocked.className = 'nino-admin-error';
				blocked.textContent = Nino.content.getText('/_admin/types/hint/delete-referenced')
					.replace( '%s', Nino.admin.elementTypes._referencedBy.join(', ') );
				wrap.appendChild( blocked );
				return wrap;
			}

			const row = dc.createElement('div');
			row.className = 'admin-type-danger-row';

			const confirmInput = dc.createElement('input');
			confirmInput.type = 'text';
			confirmInput.id = 'admin-form-delete-confirm';
			confirmInput.autocomplete = 'off';
			// The uri typed to confirm a deletion is not an edit of the type
			confirmInput.dataset.dirty = 'ignore';
			confirmInput.placeholder = Nino.content.getText('/_admin/types/placeholder/delete').replace( '%s', uri );
			row.appendChild( confirmInput );

			const delBtn = dc.createElement('button');
			delBtn.type = 'button';
			delBtn.className = 'nino-admin-btn-danger';
			delBtn.textContent = Nino.content.getText('/_admin/types/label/delete');
			delBtn.disabled = true;
			row.appendChild( delBtn );

			confirmInput.addEventListener( 'input', function() {
				delBtn.disabled = ( confirmInput.value.trim() !== uri );
			} );

			delBtn.addEventListener( 'click', function() { Nino.admin.elementTypes._delete() } );

			wrap.appendChild( row );

			const msg = dc.createElement('p');
			msg.id = 'admin-form-delete-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( msg );

			return wrap;
		},

		/**
		 *	Send the deletion the danger zone just unlocked, then go back to a
		 *	freshly loaded list - the type the form was showing is gone, so
		 *	there is nothing to stay on
		 *
		 *	@param		{boolean}	[guarded]			The unsaved input of an open element form was asked about already
		 *
		 *	@return		void
		 */
		_delete : function( guarded ) {

			const uri 	= Nino.admin.elementTypes._currentUri;
			const input = dc.getElementById('admin-form-delete-confirm');
			const msg 	= dc.getElementById('admin-form-delete-msg');

			if( uri === null || input === null || input.value.trim() !== uri )
				return;

			if( guarded !== true ) {
				Nino.admin.elementTypes._guardElements( function() { Nino.admin.elementTypes._delete( true ) } );
				return;
			}

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.elementTypes._apiCall( 'delete', { uri : uri, confirm : input.value.trim() }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					return;
				}

				Nino.admin.elementTypes._isNew 			= false;
				Nino.admin.elementTypes._currentUri = null;
				Nino.admin.elementTypes._fields 		= [];
				Nino.admin.elementTypes._showList();
				Nino.admin.elementTypes.init();
				Nino.admin.elementTypes._invalidateElements();
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

			Nino.admin.elementTypes._storeFields();

			const renames = {};

			Nino.admin.elementTypes._fields.forEach( function( field ) {

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
		 *	current field rows, skipping rows with no key
		 *
		 *	@return		{Object}
		 */
		_buildModel : function() {

			Nino.admin.elementTypes._storeFields();

			const model = {};
			Nino.admin.elementTypes._fields.forEach( function( field ) {
				if( field.key === '' )
					return;
				// Every key _storeFields() reads back off a row belongs here.
				// maxlength and suffix were offered by the field editor and
				// collected by _storeFields(), but never made it into the
				// payload - the server has always accepted both (see Types.php's
				// cleanModel()), so setting either simply did nothing
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
		 *	not deployed alongside it
		 *
		 *	@return		void
		 */
		_invalidateElements : function() {

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
		 *	Create or save the type currently open
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the type was written, false otherwise
		 *	@param		{boolean}		[guarded]			The unsaved input of an open element form was asked about already
		 *
		 *	@return		void
		 */
		_save : function( done, guarded ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			if( guarded !== true ) {
				Nino.admin.elementTypes._guardElements( function() { Nino.admin.elementTypes._save( done, true ) }, function() { report( false ) } );
				return;
			}

			const msg 	= dc.getElementById('admin-form-msg');
			const title = dc.getElementById('admin-form-title').value;
			const model = Nino.admin.elementTypes._buildModel();
			const autoincrement = dc.querySelector('#admin-form-autoincrement input').checked;
			const renames = Nino.admin.elementTypes._isNew === true ? {} : Nino.admin.elementTypes._renames();

			// A rename moves stored values and leaves the rest where it is: said
			// before it is made, with what it does not reach
			if( Object.keys( renames ).length > 0 ) {

				const pairs = Object.keys( renames ).map( function( old ) { return old+ ' \u2192 '+ renames[old] } ).join(', ');

				if( wn.confirm( Nino.adminUi.format( Nino.content.getText('/_admin/types/confirm/rename'), pairs ) ) === false ) {
					report( false );
					return;
				}
			}

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			if( Nino.admin.elementTypes._isNew === true ) {
				const uri = dc.getElementById('admin-form-uri').value;
				Nino.admin.elementTypes._apiCall( 'create', { uri : uri, title : title, model : model, autoincrement : autoincrement }, function( status, response ) {
					if( status !== 200 || response === null ) {
						msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
						report( false );
						return;
					}
					Nino.admin.elementTypes._isNew 			= false;
					Nino.admin.elementTypes._currentUri = response.uri;
					msg.textContent = Nino.content.getText('/_admin/common/msg/saved');
					Nino.admin.elementTypes._saved();
					Nino.admin.elementTypes.init();
					Nino.admin.elementTypes._invalidateElements();
					report( true );
				} );
				return;
			}

			Nino.admin.elementTypes._apiCall( 'save', { uri : Nino.admin.elementTypes._currentUri, title : title, model : model, autoincrement : autoincrement, renames : renames }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}
				msg.textContent = Nino.content.getText('/_admin/common/msg/saved');
				Nino.admin.elementTypes._notice = Nino.admin.elementTypes._referenceNotice( response.references );
				Nino.admin.elementTypes._saved();
				Nino.admin.elementTypes.init();
				Nino.admin.elementTypes._invalidateElements();
				report( true );
			} );
		},

		/**
		 *	The form on screen is what is stored now
		 *
		 *	@return		void
		 */
		_saved : function() {
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'types' );
		},
	};

	// The shell looks a panel's script up by its nav uri (see Admin::panels()),
	// and this panel's uri is 'types' - one name, two spellings
	Nino.admin.types = Nino.admin.elementTypes;

	Nino.events.bindCallback( 'ready', Nino.admin.elementTypes.init );

	// The shell asks before anything throws the open type's input away (see
	// Nino.admin.dirty)
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'types', function() { return dc.getElementById('types-form') }, function( done ) { Nino.admin.elementTypes._save( done ) } );

})(window, document, document.documentElement, document.body);
