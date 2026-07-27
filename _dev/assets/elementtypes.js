

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

	wn.Nino.dev = wn.Nino.dev || {};

	Nino.dev.elementTypes = {

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

			Nino.dev.elementTypes._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.dev.elementTypes._showError( dc.getElementById('types-list'), status, response );

				Nino.dev.elementTypes._types 			= response.types;
				Nino.dev.elementTypes._fieldTypes = response.fieldTypes;
				Nino.dev.elementTypes._renderList();
				Nino.dev.elementTypes._showList();
				Nino.dev.elementTypes._ready = true;
			} );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on - called
		 *	when the tab is switched to, once Nino.dev.TABS grows a second entry
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.dev.elementTypes._ready === false )
				return;

			if( dc.getElementById('types-form').classList.contains('dev-hidden') === false )
				return Nino.dev.elementTypes._showForm();

			Nino.dev.elementTypes._showList();
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
			Nino.http.sendRequest( '/_dev/', 'POST', function( xhr ) {
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
			p.className = 'dev-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			container.appendChild( p );
		},

		_showList : function() {
			dc.getElementById('types-list').classList.remove('dev-hidden');
			dc.getElementById('types-form').classList.add('dev-hidden');
		},

		_showForm : function() {
			dc.getElementById('types-list').classList.add('dev-hidden');
			dc.getElementById('types-form').classList.remove('dev-hidden');
		},

		/**
		 *	Render the type list, plus an "add new" action below it
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('types-list');
			wrap.innerHTML = '';

			const ul = dc.createElement('ul');
			Nino.dev.elementTypes._types.forEach( function( type ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';
				link.textContent = type.title+ ' ('+ type.fieldCount+ ' fields)';
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.dev.elementTypes._openForm( type.uri ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'admin-list-action';
			addBtn.textContent = 'New type';
			addBtn.addEventListener( 'click', function() { Nino.dev.elementTypes._openForm( null ) } );
			wrap.appendChild( addBtn );
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
				Nino.dev.elementTypes._isNew 			= true;
				Nino.dev.elementTypes._currentUri = null;
				Nino.dev.elementTypes._fields 		= [];
				Nino.dev.elementTypes._renderForm( '' );
				Nino.dev.elementTypes._showForm();
				return;
			}

			Nino.dev.elementTypes._apiCall( 'get', { uri : uri }, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.dev.elementTypes._showError( dc.getElementById('types-form'), status, response );

				Nino.dev.elementTypes._isNew 			= false;
				Nino.dev.elementTypes._currentUri = response.uri;
				Nino.dev.elementTypes._fields 		= Object.keys( response.model ).map( function( key ) {
					return Object.assign( { key : key }, response.model[key] );
				} );
				Nino.dev.elementTypes._renderForm( response.title );
				Nino.dev.elementTypes._showForm();
			} );
		},

		/**
		 *	Render one field's row: key, type, and whichever options apply to
		 *	that type (locale/html/required always; maxlength for string;
		 *	width+height for image; suffix for everything but boolean/image;
		 *	options for a fixed value list)
		 *
		 *	@param		{Object}	field					{ key, type, locale, html, required, maxlength, width, height, suffix, options }
		 *	@param		{number}	index					Index into _fields, for the remove button
		 *
		 *	@return		{Element}
		 */
		_renderFieldRow : function( field, index ) {

			const row = dc.createElement('fieldset');
			row.className = 'dev-field-row';

			const keyInput = dc.createElement('input');
			keyInput.type = 'text';
			keyInput.placeholder = 'Field name';
			keyInput.value = field.key ?? '';
			keyInput.className = 'dev-field-key';
			row.appendChild( keyInput );

			const typeSelect = dc.createElement('select');
			Nino.dev.elementTypes._fieldTypes.forEach( function( t ) {
				const opt = dc.createElement('option');
				opt.value = t;
				opt.textContent = t;
				opt.selected = ( t === field.type );
				typeSelect.appendChild( opt );
			} );
			row.appendChild( typeSelect );

			const optionsWrap = dc.createElement('div');
			optionsWrap.className = 'dev-field-options';
			row.appendChild( optionsWrap );

			function renderTypeOptions() {

				optionsWrap.innerHTML = '';
				const type = typeSelect.value;

				const localeLabel = dc.createElement('label');
				const localeCheck = dc.createElement('input');
				localeCheck.type = 'checkbox';
				localeCheck.className = 'dev-field-locale';
				localeCheck.checked = field.locale === true;
				localeLabel.appendChild( localeCheck );
				localeLabel.appendChild( dc.createTextNode(' per translation') );
				optionsWrap.appendChild( localeLabel );

				const requiredLabel = dc.createElement('label');
				const requiredCheck = dc.createElement('input');
				requiredCheck.type = 'checkbox';
				requiredCheck.className = 'dev-field-required';
				requiredCheck.checked = field.required === true;
				requiredLabel.appendChild( requiredCheck );
				requiredLabel.appendChild( dc.createTextNode(' Required field') );
				optionsWrap.appendChild( requiredLabel );

				// Shown next to the value everywhere except boolean (a "Ja"/"Nein"
				// choice has nothing to append) and image (its own preview/upload
				// area, not an input a suffix could sit next to)
				if( type !== 'boolean' && type !== 'image' ) {
					const suffixInput = dc.createElement('input');
					suffixInput.type = 'text';
					suffixInput.className = 'dev-field-suffix';
					suffixInput.placeholder = 'Unit/suffix (optional, eg. €)';
					suffixInput.value = field.suffix ?? '';
					optionsWrap.appendChild( suffixInput );
				}

				if( type === 'string' ) {
					const htmlLabel = dc.createElement('label');
					const htmlCheck = dc.createElement('input');
					htmlCheck.type = 'checkbox';
					htmlCheck.className = 'dev-field-html';
					htmlCheck.checked = field.html === true;
					htmlLabel.appendChild( htmlCheck );
					htmlLabel.appendChild( dc.createTextNode(' Rich text') );
					optionsWrap.appendChild( htmlLabel );

					const maxlengthInput = dc.createElement('input');
					maxlengthInput.type = 'number';
					maxlengthInput.min = '1';
					maxlengthInput.className = 'dev-field-maxlength';
					maxlengthInput.placeholder = 'Max. characters (default 2000)';
					maxlengthInput.value = field.maxlength ?? '';
					optionsWrap.appendChild( maxlengthInput );

					const optionsInput = dc.createElement('input');
					optionsInput.type = 'text';
					optionsInput.className = 'dev-field-select-options';
					optionsInput.placeholder = 'Fixed values, comma-separated (optional)';
					optionsInput.value = ( field.options ?? [] ).join(', ');
					optionsWrap.appendChild( optionsInput );
				}

				if( type === 'image' ) {
					const widthInput = dc.createElement('input');
					widthInput.type = 'number';
					widthInput.min = '1';
					widthInput.className = 'dev-field-width';
					widthInput.placeholder = 'Width (px)';
					widthInput.value = field.width ?? '';
					optionsWrap.appendChild( widthInput );

					const heightInput = dc.createElement('input');
					heightInput.type = 'number';
					heightInput.min = '1';
					heightInput.className = 'dev-field-height';
					heightInput.placeholder = 'Height (px)';
					heightInput.value = field.height ?? '';
					optionsWrap.appendChild( heightInput );
				}
			}

			typeSelect.addEventListener( 'change', renderTypeOptions );
			renderTypeOptions();

			const removeBtn = dc.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'red';
			removeBtn.textContent = 'Remove';
			removeBtn.addEventListener( 'click', function() {
				Nino.dev.elementTypes._fields.splice( index, 1 );
				Nino.dev.elementTypes._renderFields();
			} );
			row.appendChild( removeBtn );

			row.dataset.index = index;
			return row;
		},

		/**
		 *	Re-render every field row into #dev-fields-wrap
		 *
		 *	@return		void
		 */
		_renderFields : function() {
			const wrap = dc.getElementById('dev-fields-wrap');
			wrap.innerHTML = '';
			Nino.dev.elementTypes._fields.forEach( function( field, index ) {
				wrap.appendChild( Nino.dev.elementTypes._renderFieldRow( field, index ) );
			} );
		},

		/**
		 *	Read every field row's current dom state back into _fields, so
		 *	adding/removing a row (which re-renders) never loses in-progress edits
		 *
		 *	@return		void
		 */
		_storeFields : function() {
			const rows = dc.querySelectorAll('#dev-fields-wrap .dev-field-row');
			const fields = [];
			rows.forEach( function( row ) {
				const options = row.querySelector('.dev-field-select-options');
				fields.push( {
					key 			: row.querySelector('.dev-field-key').value,
					type 			: row.querySelector('select').value,
					locale 		: row.querySelector('.dev-field-locale').checked,
					required 	: row.querySelector('.dev-field-required').checked,
					html 			: ( row.querySelector('.dev-field-html')?.checked ) ?? false,
					maxlength : row.querySelector('.dev-field-maxlength')?.value,
					width 		: row.querySelector('.dev-field-width')?.value,
					height 		: row.querySelector('.dev-field-height')?.value,
					suffix 		: ( row.querySelector('.dev-field-suffix')?.value ) ?? '',
					options 	: options ? options.value.split(',').map( function(s) { return s.trim() } ).filter( function(s) { return s !== '' } ) : [],
				} );
			} );
			Nino.dev.elementTypes._fields = fields;
		},

		/**
		 *	Render the type editor: back-link, uri (editable only when new),
		 *	title, every field row, "add field", save
		 *
		 *	@param		{string}	title
		 *
		 *	@return		void
		 */
		_renderForm : function( title ) {

			const wrap = dc.getElementById('types-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.dev.elementTypes._showList() } );
			wrap.appendChild( backLink );

			const form = dc.createElement('form');

			if( Nino.dev.elementTypes._isNew === true ) {
				const uriLabel = dc.createElement('label');
				uriLabel.className = 'admin-field';
				const uriSpan = dc.createElement('span');
				uriSpan.textContent = 'Uri (elements/<uri>.php) - lowercase letters, digits, - and _ only';
				uriLabel.appendChild( uriSpan );
				const uriInput = dc.createElement('input');
				uriInput.type = 'text';
				uriInput.id = 'dev-form-uri';
				uriInput.required = true;
				uriLabel.appendChild( uriInput );
				form.appendChild( uriLabel );
			}

			const titleLabel = dc.createElement('label');
			titleLabel.className = 'admin-field';
			const titleSpan = dc.createElement('span');
			titleSpan.textContent = 'Title';
			titleLabel.appendChild( titleSpan );
			const titleInput = dc.createElement('input');
			titleInput.type = 'text';
			titleInput.id = 'dev-form-title';
			titleInput.value = title;
			titleLabel.appendChild( titleInput );
			form.appendChild( titleLabel );

			const fieldsWrap = dc.createElement('div');
			fieldsWrap.id = 'dev-fields-wrap';
			form.appendChild( fieldsWrap );

			const addFieldBtn = dc.createElement('button');
			addFieldBtn.type = 'button';
			addFieldBtn.textContent = 'Add field';
			addFieldBtn.addEventListener( 'click', function() {
				Nino.dev.elementTypes._storeFields();
				Nino.dev.elementTypes._fields.push( { key : '', type : 'string' } );
				Nino.dev.elementTypes._renderFields();
			} );
			form.appendChild( addFieldBtn );

			const msg = dc.createElement('p');
			msg.id = 'dev-form-msg';
			form.appendChild( msg );

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save';
			form.appendChild( saveBtn );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.dev.elementTypes._save() } );

			wrap.appendChild( form );
			Nino.dev.elementTypes._renderFields();
		},

		/**
		 *	Build the model object apiSave()/apiCreate() expect from the
		 *	current field rows, skipping rows with no key
		 *
		 *	@return		{Object}
		 */
		_buildModel : function() {

			Nino.dev.elementTypes._storeFields();

			const model = {};
			Nino.dev.elementTypes._fields.forEach( function( field ) {
				if( field.key === '' )
					return;
				model[field.key] = {
					type 			: field.type,
					locale 		: field.locale,
					required 	: field.required,
					html 			: field.html,
					width 		: field.width,
					height 		: field.height,
					options 	: field.options,
				};
			} );
			return model;
		},

		/**
		 *	Create or save the type currently open
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg 	= dc.getElementById('dev-form-msg');
			const title = dc.getElementById('dev-form-title').value;
			const model = Nino.dev.elementTypes._buildModel();

			msg.textContent = 'Saving …';

			if( Nino.dev.elementTypes._isNew === true ) {
				const uri = dc.getElementById('dev-form-uri').value;
				Nino.dev.elementTypes._apiCall( 'create', { uri : uri, title : title, model : model }, function( status, response ) {
					if( status !== 200 || response === null ) {
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
						return;
					}
					Nino.dev.elementTypes._isNew 			= false;
					Nino.dev.elementTypes._currentUri = response.uri;
					msg.textContent = 'Saved.';
					Nino.dev.elementTypes.init();
				} );
				return;
			}

			Nino.dev.elementTypes._apiCall( 'save', { uri : Nino.dev.elementTypes._currentUri, title : title, model : model }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}
				msg.textContent = 'Saved.';
				Nino.dev.elementTypes.init();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.dev.elementTypes.init );

})(window, document, document.documentElement, document.body);
