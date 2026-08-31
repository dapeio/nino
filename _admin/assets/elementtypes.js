

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Element Types" module: create/edit an element type's
 *													title + model (field definitions) only - never touches a
 *													type's actual content ('*' and locale buckets with real
 *													elements), that part of the file is read back untouched
 *													and saved right along with it server-side. No delete here
 *													on purpose - that would destroy real content; use rm.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.elementTypes = {

		_types 					: [],
		_fieldTypes 		: [],
		_currentUri 		: null,
		_isNew 					: false,
		_fields 				: [],
		_ready 					: false,

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
				Nino.admin.elementTypes._renderList();
				Nino.admin.elementTypes._showList();
				Nino.admin.elementTypes._ready = true;
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
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'devtypes/'+ endpoint, data : JSON.stringify( payload ) } );
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
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			container.appendChild( p );
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

			if( Nino.admin.elementTypes._types.length === 0 ) {
				const empty = dc.createElement('p');
				empty.className = 'nino-admin-hint';
				empty.textContent = 'No element types yet - create one below.';
				wrap.appendChild( empty );
			}

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
				descr.textContent = '/'+ type.uri+ ' · '+ type.fieldCount+ ' '+ ( type.fieldCount === 1 ? 'field' : 'fields' );
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
			addBtn.textContent = 'New type';
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
				Nino.admin.elementTypes._renderForm( '', false, '00001' );
				Nino.admin.elementTypes._showForm();
				return;
			}

			Nino.admin.elementTypes._apiCall( 'get', { uri : uri }, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.elementTypes._showError( dc.getElementById('types-form'), status, response );

				Nino.admin.elementTypes._isNew 			= false;
				Nino.admin.elementTypes._currentUri = response.uri;
				Nino.admin.elementTypes._fields 		= Object.keys( response.model ).map( function( key ) {
					return Object.assign( { key : key }, response.model[key] );
				} );
				Nino.admin.elementTypes._renderForm( response.title, response.autoincrement === true, response.next );
				Nino.admin.elementTypes._showForm();
			} );
		},

		/**
		 *	Render one field's row: key, type, and whichever options apply to
		 *	that type (locale/html/required always; maxlength for string;
		 *	width+height for image; the referenced type for element; suffix for
		 *	everything but boolean/image/element; options for a fixed value list)
		 *
		 *	@param		{Object}	field					{ key, type, locale, html, required, maxlength, width, height, elementType, suffix, options }
		 *	@param		{number}	index					Index into _fields, for the remove button
		 *
		 *	@return		{Element}
		 */
		_renderFieldRow : function( field, index ) {

			const row = dc.createElement('fieldset');
			row.className = 'admin-field-row';

			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.placeholder = 'Field name';
			keyInput.value = field.key ?? '';
			keyInput.className = 'admin-field-key';
			row.appendChild( keyInput );

			const typeSelect = dc.createElement('select');
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
				localeLabel.appendChild( dc.createTextNode(' per translation') );
				optionsWrap.appendChild( localeLabel );

				// Never offered for an image: its file is uploaded separately,
				// after the element already exists (see elements.js's image
				// branch - a new element has no uri to attach an upload to yet),
				// so a required image could never be filled in on the very save
				// that would have to satisfy it. The element would simply be
				// impossible to create. Admin.php's _cleanModel() drops the flag
				// on save too, so a type file that carries one from before loses
				// it the next time it is saved here
				if( type !== 'image' ) {
					const requiredLabel = dc.createElement('label');
					const requiredCheck = dc.createElement('input');
					requiredCheck.type = 'checkbox';
					requiredCheck.className = 'admin-field-required';
					requiredCheck.checked = field.required === true;
					requiredLabel.appendChild( requiredCheck );
					requiredLabel.appendChild( dc.createTextNode(' Required field') );
					optionsWrap.appendChild( requiredLabel );
				}

				// Shown next to the value everywhere except boolean (a "Ja"/"Nein"
				// choice has nothing to append) and image (its own preview/upload
				// area, not an input a suffix could sit next to)
				if( type !== 'boolean' && type !== 'image' ) {
					const suffixInput = dc.createElement('input');
					suffixInput.type = 'text';
					suffixInput.className = 'admin-field-suffix';
					suffixInput.placeholder = 'Unit/suffix (optional, eg. €)';
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
					htmlLabel.appendChild( dc.createTextNode(' Rich text') );
					optionsWrap.appendChild( htmlLabel );

					const maxlengthInput = dc.createElement('input');
					maxlengthInput.type = 'number';
					maxlengthInput.min = '1';
					maxlengthInput.className = 'admin-field-maxlength';
					maxlengthInput.placeholder = 'Max. characters (default 2000)';
					maxlengthInput.value = field.maxlength ?? '';
					optionsWrap.appendChild( maxlengthInput );

					const optionsInput = dc.createElement('input');
					optionsInput.type = 'text';
					optionsInput.className = 'admin-field-select-options';
					optionsInput.placeholder = 'Fixed values, comma-separated (optional)';
					optionsInput.value = ( field.options ?? [] ).join(', ');
					optionsWrap.appendChild( optionsInput );
				}

				// Which type this reference may point at. Part of the field, not
				// of the value: it is what both element forms build their select
				// of elements from, so a reference without one has nothing to
				// offer (Admin.php's _unknownReferencedType() rejects the save).
				// A brand-new type is not in this list yet - it has no file on
				// disk to reference - so a self-reference is added by reopening
				// the type once it exists
				if( type === 'element' ) {
					const refLabel = dc.createElement('label');
					refLabel.className = 'nino-admin-field';
					const refSpan = dc.createElement('span');
					refSpan.textContent = 'References element type';
					refLabel.appendChild( refSpan );

					const refSelect = dc.createElement('select');
					refSelect.className = 'admin-field-element-type';

					const others = Nino.admin.elementTypes._types.filter( function( t ) {
						return t.uri !== Nino.admin.elementTypes._currentUri;
					} );

					if( others.length === 0 ) {
						const empty = dc.createElement('option');
						empty.value = '';
						empty.textContent = 'No other element type exists yet';
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
						dangling.textContent = field.elementType+ ' (missing)';
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
					multiLabel.appendChild( dc.createTextNode(' Several elements') );
					optionsWrap.appendChild( multiLabel );

					const maxInput = dc.createElement('input');
					maxInput.type = 'number';
					maxInput.min = '0';
					maxInput.className = 'admin-field-multiple-max';
					maxInput.placeholder = 'Max. elements (0 = unlimited)';
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
					widthInput.placeholder = 'Width (px)';
					widthInput.value = field.width ?? '';
					optionsWrap.appendChild( widthInput );

					const heightInput = dc.createElement('input');
					heightInput.type = 'number';
					heightInput.min = '1';
					heightInput.className = 'admin-field-height';
					heightInput.placeholder = 'Height (px)';
					heightInput.value = field.height ?? '';
					optionsWrap.appendChild( heightInput );
				}
			}

			typeSelect.addEventListener( 'change', renderTypeOptions );
			renderTypeOptions();

			const actions = dc.createElement('div');
			actions.className = 'admin-field-actions';

			// Same ↑/↓ pair the Pages list uses (see pages.js's _move()) - a
			// field's position in the model is the order both element forms
			// render it in, so this is a real editing control, not just a way
			// to tidy up this list
			const move = dc.createElement('span');
			move.className = 'admin-field-move';

			const up = dc.createElement('button');
			up.type = 'button';
			up.title = 'Move up';
			up.textContent = '↑';
			up.disabled = index === 0;
			up.addEventListener( 'click', function() { Nino.admin.elementTypes._move( index, 'up' ) } );
			move.appendChild( up );

			const down = dc.createElement('button');
			down.type = 'button';
			down.title = 'Move down';
			down.textContent = '↓';
			down.disabled = index === Nino.admin.elementTypes._fields.length - 1;
			down.addEventListener( 'click', function() { Nino.admin.elementTypes._move( index, 'down' ) } );
			move.appendChild( down );

			actions.appendChild( move );

			const removeBtn = dc.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'nino-admin-btn-danger';
			removeBtn.textContent = 'Remove';
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
		 *	order, json_decode and Admin.php's cleanModel() both keep that
		 *	order on the way into the type file, and each element form renders
		 *	its fields in the model's own key order (see elements.js's
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
					type 			: row.querySelector('select').value,
					locale 		: row.querySelector('.admin-field-locale').checked,
					// Absent on an image row, which is never offered the checkbox
					// (see _renderFieldRow()) - false, not "keep whatever was there"
					required 	: ( row.querySelector('.admin-field-required')?.checked ) ?? false,
					html 			: ( row.querySelector('.admin-field-html')?.checked ) ?? false,
					maxlength : row.querySelector('.admin-field-maxlength')?.value,
					width 		: row.querySelector('.admin-field-width')?.value,
					height 		: row.querySelector('.admin-field-height')?.value,
					suffix 		: ( row.querySelector('.admin-field-suffix')?.value ) ?? '',
					// Absent on every row but an element reference
					elementType : ( row.querySelector('.admin-field-element-type')?.value ) ?? '',
					// Whether that reference holds a list, and its ceiling. Sent
					// as the two controls collect them; Admin.php's cleanModel()
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
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.elementTypes._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');

			if( Nino.admin.elementTypes._isNew === true ) {
				const uriLabel = dc.createElement('label');
				uriLabel.className = 'nino-admin-field';
				const uriSpan = dc.createElement('span');
				uriSpan.textContent = 'Uri (elements/<uri>.php) - lowercase letters, digits, - and _ only';
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
			titleSpan.textContent = 'Title';
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
			autoLegend.textContent = 'Element URIs';
			autoWrap.appendChild( autoLegend );

			const autoSwitch = Nino.adminUi.switchField( {
				key 			: 'autoincrement',
				checked 	: autoincrement === true,
				label 		: 'Number the elements of this type',
				hint 			: 'The element form stops asking for a uri and assigns the next number instead - /'+ ( Nino.admin.elementTypes._currentUri ?? '<type>' )+ '/'+ ( next || '00001' )+ '. Numbers are never reused, so deleting an element does not free its uri for the next one.',
			} );
			autoSwitch.id = 'admin-form-autoincrement';
			autoWrap.appendChild( autoSwitch );

			// Only the elements added from here on are numbered. Saying so beats
			// letting someone discover it, and it is the reason turning this on is
			// not a destructive change.
			if( autoincrement !== true && Nino.admin.elementTypes._isNew === false ) {
				const autoNote = dc.createElement('p');
				autoNote.className = 'nino-admin-hint';
				autoNote.textContent = 'Existing elements keep the uris they have. Numbering starts past the highest number this type already uses.';
				autoWrap.appendChild( autoNote );
			}

			form.appendChild( autoWrap );

			const fieldsWrap = dc.createElement('div');
			fieldsWrap.id = 'admin-fields-wrap';
			form.appendChild( fieldsWrap );

			const addFieldBtn = dc.createElement('button');
			addFieldBtn.type = 'button';
			addFieldBtn.textContent = 'Add field';
			addFieldBtn.addEventListener( 'click', function() {
				Nino.admin.elementTypes._storeFields();
				Nino.admin.elementTypes._fields.push( { key : '', type : 'string' } );
				Nino.admin.elementTypes._renderFields();
			} );
			form.appendChild( addFieldBtn );

			// Save + its message in the shared actions row every module's form
			// ends on - Nino.admin.css pins that row to the bottom of the
			// viewport, so a long field list never puts Save out of reach
			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save';
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'admin-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.elementTypes._save() } );

			wrap.appendChild( form );
			Nino.admin.elementTypes._renderFields();
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
				// payload - the server has always accepted both (see Admin.php's
				// cleanModel()), so setting either simply did nothing
				model[field.key] = {
					type 				: field.type,
					locale 			: field.locale,
					required 		: field.required,
					html 				: field.html,
					maxlength 	: field.maxlength,
					width 			: field.width,
					height 			: field.height,
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
		 *	forms from has just changed (see elements.js's invalidate()).
		 *
		 *	That module reads every type's model exactly once per page load,
		 *	so without this a field added, renamed or removed here only
		 *	showed up over there after a full page reload. Called through its
		 *	own public entry point rather than by touching its state, and
		 *	guarded so this module still works on its own if elements.js is
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
		 *	Create or save the type currently open
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg 	= dc.getElementById('admin-form-msg');
			const title = dc.getElementById('admin-form-title').value;
			const model = Nino.admin.elementTypes._buildModel();
			const autoincrement = dc.querySelector('#admin-form-autoincrement input').checked;

			msg.textContent = 'Saving …';

			if( Nino.admin.elementTypes._isNew === true ) {
				const uri = dc.getElementById('admin-form-uri').value;
				Nino.admin.elementTypes._apiCall( 'create', { uri : uri, title : title, model : model, autoincrement : autoincrement }, function( status, response ) {
					if( status !== 200 || response === null ) {
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
						return;
					}
					Nino.admin.elementTypes._isNew 			= false;
					Nino.admin.elementTypes._currentUri = response.uri;
					msg.textContent = 'Saved.';
					Nino.admin.elementTypes.init();
					Nino.admin.elementTypes._invalidateElements();
				} );
				return;
			}

			Nino.admin.elementTypes._apiCall( 'save', { uri : Nino.admin.elementTypes._currentUri, title : title, model : model, autoincrement : autoincrement }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}
				msg.textContent = 'Saved.';
				Nino.admin.elementTypes.init();
				Nino.admin.elementTypes._invalidateElements();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.elementTypes.init );

})(window, document, document.documentElement, document.body);
