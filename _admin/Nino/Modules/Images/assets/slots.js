

/**
 *	Nino										A compact filesystembased php framework
 *	slots.js								"Image Slots" tab of the Images panel: create/edit image
 *													slot definitions (label/width/height) - the "set" half of
 *													what the Images panel edits ("values" half: which file
 *													currently fills a slot). Same split as types.js and the
 *													Elements panel. Never touches a slot's filename. A row
 *													also says when no page shows its slot.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.slots = {

		_slots 		: [],
		_currentUri : null,
		_isNew 		: false,
		// The rows of the scan form while it is the one on screen, else null -
		// what the shell's Save tells the two forms apart by
		_scanRows : null,
		_ready 		: false,
		// The row or the button that opened the form that is open: Back returns
		// the focus to it (see Nino.adminUi.backTo())
		_opener 	: null,

		/**
		 *	Load every image slot and render the list
		 *
		 *	@param		{boolean}	[stay]		The form stays on screen: the list is drawn again under it, for Back to return to, and not shown
		 *
		 *	@return		void
		 */
		init : function( stay ) {

			if( dc.getElementById('slots-list') === null )
				return;

			Nino.admin.slots._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.slots._showError( dc.getElementById('slots-list'), status, response );

				Nino.admin.slots._slots = response.slots;
				Nino.admin.slots._renderList();
				if( stay !== true )
					Nino.admin.slots._showList();
				Nino.admin.slots._ready = true;

				// The Images panel of this same module lists the slots there
				// are pictures for, and loads them once. The slots just
				// changed - every create, save, delete and scan ends up here -
				// so its copy is stale, and it now says "no image slots yet"
				// out loud when it has none: a stale copy would not be an
				// empty screen but a wrong sentence. Marking it unloaded is
				// enough, showCurrent() fetches again when the tab is next on
				if( Nino.admin.images !== undefined )
					Nino.admin.images._ready = false;
			} );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.slots._ready === false )
				return;

			if( dc.getElementById('slots-form').classList.contains('admin-hidden') === false )
				return Nino.admin.slots._showForm();

			Nino.admin.slots._showList();
		},

		/**
		 *	Call a slots/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "slots/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'slots/'+ endpoint, payload, callback );
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
		 *	@return		void
		 */
		_showList : function() {
			Nino.adminUi.showLevel( 'slots-list', { focus : false } );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {
			Nino.adminUi.showLevel( 'slots-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	Render the slot list, plus an "add new" action below it
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('slots-list');
			wrap.innerHTML = '';

			if( Nino.admin.slots._slots.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/slots/empty') ) );

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			Nino.admin.slots._slots.forEach( function( slot ) {
				const li 		= dc.createElement('li');

				// It opens a form and no address is written: a button, not a link to nowhere
				const edit	= dc.createElement('button');
				edit.type = 'button';
				edit.className = 'nino-admin-list-link';
				edit.textContent = slot.label+ ' ('+ slot.uri+ ', '+ slot.width+ '×'+ slot.height+ ( slot.hasImage ? '' : ', '+ Nino.content.getText('/_admin/slots/label/noimage') )+ Nino.admin.slots._usageLabel( slot )+ ')';
				edit.addEventListener( 'click', function() { Nino.admin.slots._openForm( slot, edit ) } );
				li.appendChild( edit );
				ul.appendChild( li );
			} );
			if( Nino.admin.slots._slots.length > 0 )
				wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'nino-admin-btn-primary';
			addBtn.textContent = Nino.content.getText('/_admin/slots/label/new');
			addBtn.addEventListener( 'click', function() { Nino.admin.slots._openForm( null, addBtn ) } );

			const scanBtn = dc.createElement('button');
			scanBtn.type = 'button';
			scanBtn.className = 'nino-admin-btn-secondary';
			scanBtn.textContent = Nino.content.getText('/_admin/slots/label/scan');
			scanBtn.addEventListener( 'click', function() { Nino.admin.slots._openScanForm( scanBtn ) } );
			wrap.appendChild( Nino.adminUi.listActions( [ scanBtn, addBtn ] ) );
		},

		/**
		 *	What the list row says about where a slot is used: nothing when a
		 *	page shows it, and where no page does, that it is included nowhere -
		 *	with the templates that name it, if any, since a slot used in a
		 *	template no route renders is not the same thing as one no template
		 *	mentions
		 *
		 *	@param		{Object}		slot					{ usage : { templates, pages } }
		 *
		 *	@return		{string}									'' or ', not included anywhere[, Templates: a, b]'
		 */
		_usageLabel : function( slot ) {

			const usage = slot.usage || { templates : [], pages : [] };
			if( usage.pages.length > 0 )
				return '';

			let label = ', '+ Nino.content.getText('/_admin/slots/label/unused');
			if( usage.templates.length > 0 )
				label += ', '+ Nino.adminUi.format( Nino.content.getText('/_admin/slots/label/templates'), usage.templates.join(', ') );

			return label;
		},

		/**
		 *	Open the editor for an existing slot, or a blank one for a new slot
		 *
		 *	@param		{Object|null}	slot			{ uri, label, width, height, hasImage }, or null to create new
		 *	@param		{Element}			[opener]	The row or the button that was pressed: Back returns the focus to it
		 *
		 *	@return		void
		 */
		_openForm : function( slot, opener ) {

			Nino.admin.slots._isNew 			= slot === null;
			Nino.admin.slots._currentUri = slot ? slot.uri : null;
			Nino.admin.slots._opener 		= opener ?? null;
			Nino.admin.slots._renderForm( slot ?? {} );
			Nino.admin.slots._showForm( true );
		},

		/**
		 *	Render the slot editor: back-link, uri (editable only when new),
		 *	label, width, height, save
		 *
		 *	@param		{Object}	slot
		 *
		 *	@return		void
		 */
		_renderForm : function( slot ) {

			const wrap = dc.getElementById('slots-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.adminUi.backTo( 'slots-list', Nino.admin.slots._opener ) } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');

			if( Nino.admin.slots._isNew === true ) {
				const uriLabel = dc.createElement('label');
				uriLabel.className = 'nino-admin-field';
				const uriSpan = dc.createElement('span');
				uriSpan.textContent = Nino.content.getText('/_admin/slots/label/uri');
				uriLabel.appendChild( uriSpan );
				const uriInput = dc.createElement('input');
				uriInput.type = 'text';
				uriInput.id = 'slots-form-uri';
				uriInput.required = true;
				uriLabel.appendChild( uriInput );
				form.appendChild( uriLabel );
			}

			const labelLabel = dc.createElement('label');
			labelLabel.className = 'nino-admin-field';
			const labelSpan = dc.createElement('span');
			labelSpan.textContent = Nino.content.getText('/_admin/common/label/label');
			labelLabel.appendChild( labelSpan );
			const labelInput = dc.createElement('input');
			labelInput.type = 'text';
			labelInput.id = 'slots-form-label';
			labelInput.value = slot.label ?? '';
			labelInput.required = true;
			labelLabel.appendChild( labelInput );
			form.appendChild( labelLabel );

			const widthLabel = dc.createElement('label');
			widthLabel.className = 'nino-admin-field';
			const widthSpan = dc.createElement('span');
			widthSpan.textContent = Nino.content.getText('/_admin/slots/label/width');
			widthLabel.appendChild( widthSpan );
			const widthInput = dc.createElement('input');
			widthInput.type = 'number';
			widthInput.min = '1';
			widthInput.id = 'slots-form-width';
			widthInput.value = slot.width ?? '';
			widthInput.required = true;
			widthLabel.appendChild( widthInput );
			form.appendChild( widthLabel );

			const heightLabel = dc.createElement('label');
			heightLabel.className = 'nino-admin-field';
			const heightSpan = dc.createElement('span');
			heightSpan.textContent = Nino.content.getText('/_admin/slots/label/height');
			heightLabel.appendChild( heightSpan );
			const heightInput = dc.createElement('input');
			heightInput.type = 'number';
			heightInput.min = '1';
			heightInput.id = 'slots-form-height';
			heightInput.value = slot.height ?? '';
			heightInput.required = true;
			heightLabel.appendChild( heightInput );
			form.appendChild( heightLabel );

			// Save + its message in the shared actions row every module's form
			// ends on - style.css pins that row to the bottom of the
			// viewport, so a long field list never puts Save out of reach
			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			// In the actions row, not loose below the form: that row is pinned
			// to the bottom of the viewport now (see assets/style.css), and a
			// button left outside it would be the one control that scrolls away
			if( Nino.admin.slots._isNew === false ) {
				const deleteBtn = dc.createElement('button');
				deleteBtn.type = 'button';
				deleteBtn.className = 'nino-admin-btn-danger';
				deleteBtn.textContent = Nino.content.getText('/_admin/slots/label/delete');
				deleteBtn.addEventListener( 'click', function() { Nino.admin.slots._delete() } );
				actions.appendChild( deleteBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'slots-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.slots._save() } );

			wrap.appendChild( form );

			// What the form holds now is what is saved
			Nino.admin.slots._scanRows = null;
			Nino.admin.slots._snapshot();
		},

		/**
		 *	Have the shell take the form on screen - whichever it is - as the one
		 *	that is stored (see Nino.admin.dirty)
		 *
		 *	@return		void
		 */
		_snapshot : function() {
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'slots' );
		},

		/**
		 *	Delete the slot currently open, after confirmation - its
		 *	uploaded file (if any) goes with it
		 *
		 *	@return		void
		 */
		_delete : function() {

			Nino.adminUi.confirm( 'destructive', {
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/slots/confirm/delete'), Nino.admin.slots._currentUri ),
				yes				: Nino.content.getText('/_admin/common/label/delete'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					const msg = dc.getElementById('slots-form-msg');
					msg.textContent = Nino.content.getText('/_admin/common/msg/deleting');

					Nino.admin.slots._apiCall( 'delete', { uri : Nino.admin.slots._currentUri }, function( status, response ) {
						if( status !== 200 || response === null ) {
							msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/delete' );
							return;
						}
						Nino.admin.touch( 'slots' );
						Nino.admin.slots.init();
					} );
				},
			} );
		},

		/**
		 *	Create or save the slot currently open
		 *
		 *	Every way this ends reports to done( ok ), if there is one (see
		 *	Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	[done]				Called once with true when the slot was written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const msg 		= dc.getElementById('slots-form-msg');
			const label 	= dc.getElementById('slots-form-label').value;
			const width 	= dc.getElementById('slots-form-width').value;
			const height 	= dc.getElementById('slots-form-height').value;

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			if( Nino.admin.slots._isNew === true ) {
				const uri = dc.getElementById('slots-form-uri').value;
				Nino.admin.slots._apiCall( 'create', { uri : uri, label : label, width : width, height : height }, function( status, response ) {
					if( status !== 200 || response === null ) {
						msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
						report( false );
						return;
					}
					Nino.admin.touch( 'slots' );
					msg.textContent = Nino.content.getText('/_admin/common/msg/saved');
					Nino.admin.slots._snapshot();
					Nino.admin.slots.init();
					report( true );
				} );
				return;
			}

			Nino.admin.slots._apiCall( 'save', { uri : Nino.admin.slots._currentUri, label : label, width : width, height : height }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );
					report( false );
					return;
				}
				Nino.admin.touch( 'slots' );
				msg.textContent = Nino.content.getText('/_admin/common/msg/saved');
				Nino.admin.slots._snapshot();
				Nino.admin.slots.init();
				report( true );
			} );
		},

		/**
		 *	Open the "hardcoded <img> tags found in templates, not backed by
		 *	any slot - and slots a template names that nobody has made" scan
		 *	results form - see Slots::apiScan()
		 *
		 *	@param		{Element}		[opener]	The button that was pressed: Back returns the focus to it
		 *
		 *	@return		void
		 */
		_openScanForm : function( opener ) {

			Nino.admin.slots._opener = opener ?? null;

			const wrap = dc.getElementById('slots-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.adminUi.backTo( 'slots-list', Nino.admin.slots._opener ) } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = Nino.content.getText('/_admin/slots/label/scan');
			wrap.appendChild( title );

			const msg = dc.createElement('p');
			msg.id = 'slots-scan-msg';
			msg.textContent = Nino.content.getText('/_admin/common/msg/scanning');
			wrap.appendChild( msg );

			Nino.admin.slots._scanRows = null;
			Nino.admin.slots._snapshot();
			Nino.admin.slots._showForm( true );

			Nino.admin.slots._apiCall( 'scan', {}, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/scan' );
					return;
				}
				Nino.admin.slots._renderScanForm( response.missing );
			} );
		},

		/**
		 *	Render the scan results: one row per <img src="/images/..."> not
		 *	backed by a slot, and one per slot a component names that is not
		 *	defined (its uri is the src, and no size is known) - editable
		 *	uri/label/width/height (pre-filled from the tag's own attributes,
		 *	or the actual file's real pixel size if the tag had none) + an
		 *	"Ignore" toggle. Submitting
		 *	creates every non-ignored row's slot via the existing
		 *	slots/create action - filename stays empty, same "this tab only
		 *	ever creates the empty slot, a real upload is the Images panel's job" rule
		 *	the manual "New image slot" form already follows. A slot is made with
		 *	a size, so a row that comes without one marks its width and its height
		 *	as required and is refused at them when it is submitted (see
		 *	_saveScanResults())
		 *
		 *	@param		{Array}		missing				[ { filename, src, suggestedUri, width, height, files[] }, ... ]
		 *
		 *	@return		void
		 */
		_renderScanForm : function( missing ) {

			const wrap = dc.getElementById('slots-form');

			// The form that stood while the scan ran had the focus, on its back link:
			// the one that replaces it takes it over
			const held = ( dc.activeElement ?? null ) !== null && dc.activeElement.closest('#slots-form') !== null;
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/common/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.adminUi.backTo( 'slots-list', Nino.admin.slots._opener ) } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			if( held === true )
				backLink.focus();

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = Nino.content.getText('/_admin/slots/label/scan');
			wrap.appendChild( title );

			if( missing.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/slots/scan/none') ) );
				Nino.admin.slots._snapshot();
				return;
			}

			const form = dc.createElement('form');
			const rows = [];

			// _saveScanResults() says what is missing, at the field, and the shell's Save
			// goes there too: the browser's own bubble would be a second voice
			form.noValidate = true;

			missing.forEach( function( item ) {

				const field = dc.createElement('div');
				field.className = 'nino-admin-field';

				const span = dc.createElement('span');
				span.textContent = item.src+ '  ('+ item.files.join(', ')+ ')';
				field.appendChild( span );

				const uriSpan = dc.createElement('span');
				uriSpan.textContent = Nino.content.getText('/_admin/common/label/uri');
				field.appendChild( uriSpan );
				const uriInput = dc.createElement('input');
				uriInput.type = 'text';
				uriInput.placeholder = Nino.content.getText('/_admin/slots/label/uri');
				uriInput.value = item.suggestedUri;
				field.appendChild( uriInput );

				const labelSpan = dc.createElement('span');
				labelSpan.textContent = Nino.content.getText('/_admin/common/label/label');
				field.appendChild( labelSpan );
				const labelInput = dc.createElement('input');
				labelInput.type = 'text';
				labelInput.placeholder = Nino.content.getText('/_admin/common/label/label');
				labelInput.value = item.suggestedUri.replace(/^\//, '').replace(/-/g, ' ');
				field.appendChild( labelInput );

				const widthSpan = dc.createElement('span');
				widthSpan.textContent = Nino.content.getText('/_admin/slots/label/width');
				field.appendChild( widthSpan );
				const widthInput = dc.createElement('input');
				widthInput.type = 'number';
				widthInput.min = '1';
				widthInput.placeholder = Nino.content.getText('/_admin/common/label/width');
				widthInput.value = item.width || '';
				field.appendChild( widthInput );

				const heightSpan = dc.createElement('span');
				heightSpan.textContent = Nino.content.getText('/_admin/slots/label/height');
				field.appendChild( heightSpan );
				const heightInput = dc.createElement('input');
				heightInput.type = 'number';
				heightInput.min = '1';
				heightInput.placeholder = Nino.content.getText('/_admin/common/label/height');
				heightInput.value = item.height || '';
				field.appendChild( heightInput );

				const ignoreLabel = dc.createElement('label');
				ignoreLabel.className = 'admin-scan-ignore';
				const ignoreCheck = dc.createElement('input');
				ignoreCheck.type = 'checkbox';
				ignoreLabel.appendChild( ignoreCheck );
				ignoreLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/common/label/ignore') ) );
				field.appendChild( ignoreLabel );

				form.appendChild( field );

				const row = { field : field, uriInput : uriInput, labelInput : labelInput, widthInput : widthInput, heightInput : heightInput, ignoreCheck : ignoreCheck };
				Nino.admin.slots._markSize( row, [ widthSpan, heightSpan ], Nino.admin.slots._sized( row ) === false, rows.length );
				rows.push( row );
			} );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/slots/label/scan-create');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'slots-scan-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.slots._saveScanResults( rows ) } );

			wrap.appendChild( form );

			// The rows come pre-filled from the scan: that is the baseline
			Nino.admin.slots._scanRows = rows;
			Nino.admin.slots._snapshot();
		},

		/**
		 *	Whether a field holds a size a slot can be made with: a whole number
		 *	of at least 1
		 *
		 *	@param		{Element}		input
		 *
		 *	@return		{boolean}
		 */
		_hasSize : function( input ) {
			return /^\s*\d+\s*$/.test( input.value ) === true && parseInt( input.value, 10 ) >= 1;
		},

		/**
		 *	Whether the row has a size a slot can be made with: a width and a height
		 *
		 *	@param		{Object}		row				A row of the scan form
		 *
		 *	@return		{boolean}
		 */
		_sized : function( row ) {
			return Nino.admin.slots._hasSize( row.widthInput ) === true && Nino.admin.slots._hasSize( row.heightInput ) === true;
		},

		/**
		 *	Mark the size of a row as required while it needs one: the star after
		 *	the names of the two fields and aria-required on them, both off again
		 *	while the row is ignored (a slot nobody makes needs no size). Then
		 *	give the row what says it at its field: say( text ) puts the sentence
		 *	after the row, marks the fields and links them to it, and it goes with
		 *	the next thing typed into either field that makes the size whole
		 *
		 *	@param		{Object}		row				A row of the scan form, which gets say()
		 *	@param		{Array}			names			The two spans that name the fields, width first
		 *	@param		{boolean}		needed		The row came without a size
		 *	@param		{number}		index			The row's place, which its sentence is named by
		 *
		 *	@return		void
		 */
		_markSize : function( row, names, needed, index ) {

			const fields = [ row.widthInput, row.heightInput ];
			const stars = needed === false ? [] : names.map( function( name ) {
				const star = dc.createElement('span');
				star.className = 'nino-admin-required';
				star.setAttribute( 'aria-hidden', 'true' );
				star.textContent = '*';
				name.appendChild( star );
				return star;
			} );
			let sentence = null;

			const follow = function() {
				const required = needed === true && row.ignoreCheck.checked === false;
				fields.forEach( function( input ) {
					input.required = required;
					if( required === true )
						input.setAttribute( 'aria-required', 'true' );
					else
						input.removeAttribute('aria-required');
				} );
				stars.forEach( function( star ) { star.hidden = required === false } );
			};

			const forget = function() {
				if( sentence === null )
					return;
				sentence.remove();
				sentence = null;
				fields.forEach( function( input ) {
					input.removeAttribute('aria-invalid');
					input.removeAttribute('aria-describedby');
				} );
			};

			row.say = function( text ) {
				forget();
				sentence = Nino.adminUi.fieldError( row.field, row.widthInput, 'slots-scan-size-'+ index, text );
				row.heightInput.setAttribute( 'aria-invalid', 'true' );
				row.heightInput.setAttribute( 'aria-describedby', sentence.id );
			};

			row.ignoreCheck.addEventListener( 'change', follow );
			fields.forEach( function( input ) {
				input.addEventListener( 'input', function() {
					if( Nino.admin.slots._sized( row ) === true )
						forget();
				} );
			} );
			follow();
		},

		/**
		 *	Create every non-ignored row's slot (see _renderScanForm()), one
		 *	slots/create call at a time, so a per-row failure (eg. a
		 *	duplicate uri) is reported without swallowing the rest.
		 *
		 *	A slot is made with a size, so a row that has none is not sent: it is
		 *	refused at its width and its height, where the person can type one,
		 *	and the rows that have a size are made. The form stays for the ones
		 *	that were refused, without the ones that were made
		 *
		 *	@param		{Array}		rows
		 *	@param		{Function}	[done]			Called once with true when every row was created, false otherwise
		 *
		 *	@return		void
		 */
		_saveScanResults : function( rows, done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const msg 		= dc.getElementById('slots-scan-msg');
			const ready 	= rows.filter( function( row ) { return row.ignoreCheck.checked === false } );

			if( ready.length === 0 ) {
				Nino.admin.slots._showList();
				report( true );
				return;
			}

			const pending 	= ready.filter( function( row ) { return Nino.admin.slots._sized( row ) === true } );
			const unsized 	= ready.filter( function( row ) { return pending.indexOf( row ) === -1 } );

			// The line says that something is wrong, the sentences at the fields say what
			const refuse = function() {
				unsized.forEach( function( row ) { row.say( Nino.content.getText('/_admin/slots/error/size') ) } );
				Nino.adminUi.status( msg ).fail( Nino.content.getText('/_admin/error/invalid_value') );
			};

			if( unsized.length > 0 ) {
				refuse();
				// The first field that is refused takes the focus, and its sentence is read there
				const first = Nino.admin.slots._hasSize( unsized[0].widthInput ) === false ? unsized[0].widthInput : unsized[0].heightInput;
				if( typeof first.focus === 'function' )
					first.focus();
			}

			if( pending.length === 0 ) {
				report( false );
				return;
			}

			let created = 0;
			const made = [];
			const failed = [];

			function next( i ) {

				if( i >= pending.length ) {
					if( failed.length > 0 )
						wn.alert( Nino.adminUi.format( Nino.content.getText('/_admin/slots/scan/created-failed'), created, failed.join(', ') ) );

					// The rows that were refused - for a missing size, or by the server - are
					// still on screen, and they are all that is there: the slots that were
					// made are gone from the form, and the list under it is drawn again for Back
					if( unsized.length > 0 ) {
						made.forEach( function( row ) {
							rows.splice( rows.indexOf( row ), 1 );
							row.field.remove();
						} );
						Nino.admin.slots._snapshot();
						Nino.admin.slots.init( true );
						Nino.adminUi.status( msg ).fail( Nino.content.getText('/_admin/error/invalid_value') );
						report( false );
						return;
					}

					Nino.admin.slots._snapshot();
					Nino.admin.slots.init();
					report( failed.length === 0 );
					return;
				}

				const row = pending[i];

				// The line that says the rows without a size were refused stays what it is
				if( unsized.length === 0 )
					msg.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/common/msg/creating'), row.uriInput.value, i + 1, pending.length );

				Nino.admin.slots._apiCall( 'create', {
					uri 		: row.uriInput.value,
					label 	: row.labelInput.value,
					width 	: row.widthInput.value,
					height 	: row.heightInput.value,
				}, function( status ) {
					if( status === 200 ) {
						Nino.admin.touch( 'slots' );
						created++;
						made.push( row );
					}
					else
						failed.push( row.uriInput.value );
					next( i + 1 );
				} );
			}

			next( 0 );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.slots.init );

	// The shell asks before anything throws the open slot's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'slots', function() { return dc.getElementById('slots-form') }, function( done ) {
			if( Nino.admin.slots._scanRows !== null )
				Nino.admin.slots._saveScanResults( Nino.admin.slots._scanRows, done );
			else
				Nino.admin.slots._save( done );
		} );

})(window, document, document.documentElement, document.body);
