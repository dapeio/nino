

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Images" module: create/edit image slot definitions
 *													(label/width/height) - the "set" half of what _editor's
 *													Images panel edits ("values" half: which file currently
 *													fills a slot). Same split as elementtypes.js/_editor's
 *													Elements. Never touches a slot's filename.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.images = {

		_slots 		: [],
		_currentUri : null,
		_isNew 		: false,
		_ready 		: false,

		/**
		 *	Load every image slot and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('images-list') === null )
				return;

			Nino.admin.images._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.images._showError( dc.getElementById('images-list'), status, response );

				Nino.admin.images._slots = response.slots;
				Nino.admin.images._renderList();
				Nino.admin.images._showList();
				Nino.admin.images._ready = true;
			} );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.images._ready === false )
				return;

			if( dc.getElementById('images-form').classList.contains('admin-hidden') === false )
				return Nino.admin.images._showForm();

			Nino.admin.images._showList();
		},

		/**
		 *	Call a devimages/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "devimages/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'devimages/'+ endpoint, data : JSON.stringify( payload ) } );
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
			dc.getElementById('images-list').classList.remove('admin-hidden');
			dc.getElementById('images-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('images-list').classList.add('admin-hidden');
			dc.getElementById('images-form').classList.remove('admin-hidden');
		},

		/**
		 *	Render the slot list, plus an "add new" action below it
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('images-list');
			wrap.innerHTML = '';

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			Nino.admin.images._slots.forEach( function( slot ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';
				link.textContent = slot.label+ ' ('+ slot.uri+ ', '+ slot.width+ '×'+ slot.height+ ( slot.hasImage ? '' : ', no image' )+ ')';
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.images._openForm( slot ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'nino-admin-btn-primary';
			addBtn.textContent = 'New image slot';
			addBtn.addEventListener( 'click', function() { Nino.admin.images._openForm( null ) } );

			const scanBtn = dc.createElement('button');
			scanBtn.type = 'button';
			scanBtn.className = 'nino-admin-btn-primary';
			scanBtn.textContent = 'Scan templates for missing image slots';
			scanBtn.addEventListener( 'click', function() { Nino.admin.images._openScanForm() } );
			wrap.appendChild( Nino.adminUi.listActions( [ addBtn, scanBtn ] ) );
		},

		/**
		 *	Open the editor for an existing slot, or a blank one for a new slot
		 *
		 *	@param		{Object|null}	slot		{ uri, label, width, height, hasImage }, or null to create new
		 *
		 *	@return		void
		 */
		_openForm : function( slot ) {

			Nino.admin.images._isNew 			= slot === null;
			Nino.admin.images._currentUri = slot ? slot.uri : null;
			Nino.admin.images._renderForm( slot ?? {} );
			Nino.admin.images._showForm();
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

			const wrap = dc.getElementById('images-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.images._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');

			if( Nino.admin.images._isNew === true ) {
				const uriLabel = dc.createElement('label');
				uriLabel.className = 'nino-admin-field';
				const uriSpan = dc.createElement('span');
				uriSpan.textContent = 'Uri (eg. /home/hero)';
				uriLabel.appendChild( uriSpan );
				const uriInput = dc.createElement('input');
				uriInput.type = 'text';
				uriInput.id = 'images-form-uri';
				uriInput.required = true;
				uriLabel.appendChild( uriInput );
				form.appendChild( uriLabel );
			}

			const labelLabel = dc.createElement('label');
			labelLabel.className = 'nino-admin-field';
			const labelSpan = dc.createElement('span');
			labelSpan.textContent = 'Label';
			labelLabel.appendChild( labelSpan );
			const labelInput = dc.createElement('input');
			labelInput.type = 'text';
			labelInput.id = 'images-form-label';
			labelInput.value = slot.label ?? '';
			labelInput.required = true;
			labelLabel.appendChild( labelInput );
			form.appendChild( labelLabel );

			const widthLabel = dc.createElement('label');
			widthLabel.className = 'nino-admin-field';
			const widthSpan = dc.createElement('span');
			widthSpan.textContent = 'Width (px)';
			widthLabel.appendChild( widthSpan );
			const widthInput = dc.createElement('input');
			widthInput.type = 'number';
			widthInput.min = '1';
			widthInput.id = 'images-form-width';
			widthInput.value = slot.width ?? '';
			widthInput.required = true;
			widthLabel.appendChild( widthInput );
			form.appendChild( widthLabel );

			const heightLabel = dc.createElement('label');
			heightLabel.className = 'nino-admin-field';
			const heightSpan = dc.createElement('span');
			heightSpan.textContent = 'Height (px)';
			heightLabel.appendChild( heightSpan );
			const heightInput = dc.createElement('input');
			heightInput.type = 'number';
			heightInput.min = '1';
			heightInput.id = 'images-form-height';
			heightInput.value = slot.height ?? '';
			heightInput.required = true;
			heightLabel.appendChild( heightInput );
			form.appendChild( heightLabel );

			// Save + its message in the shared actions row every module's form
			// ends on - assets/style.css pins that row to the bottom of the
			// viewport, so a long field list never puts Save out of reach
			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save';
			actions.appendChild( saveBtn );

			// In the actions row, not loose below the form: that row is pinned
			// to the bottom of the viewport now (see assets/style.css), and a
			// button left outside it would be the one control that scrolls away
			if( Nino.admin.images._isNew === false ) {
				const deleteBtn = dc.createElement('button');
				deleteBtn.type = 'button';
				deleteBtn.className = 'nino-admin-btn-danger';
				deleteBtn.textContent = 'Delete slot';
				deleteBtn.addEventListener( 'click', function() { Nino.admin.images._delete() } );
				actions.appendChild( deleteBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'images-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.images._save() } );

			wrap.appendChild( form );
		},

		/**
		 *	Delete the slot currently open, after confirmation - its
		 *	uploaded file (if any) goes with it
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( wn.confirm( 'Really delete image slot "'+ Nino.admin.images._currentUri+ '"? Any uploaded image is lost with it.' ) === false )
				return;

			const msg = dc.getElementById('images-form-msg');
			msg.textContent = 'Deleting …';

			Nino.admin.images._apiCall( 'delete', { uri : Nino.admin.images._currentUri }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to delete.' );
					return;
				}
				Nino.admin.images.init();
			} );
		},

		/**
		 *	Create or save the slot currently open
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg 		= dc.getElementById('images-form-msg');
			const label 	= dc.getElementById('images-form-label').value;
			const width 	= dc.getElementById('images-form-width').value;
			const height 	= dc.getElementById('images-form-height').value;

			msg.textContent = 'Saving …';

			if( Nino.admin.images._isNew === true ) {
				const uri = dc.getElementById('images-form-uri').value;
				Nino.admin.images._apiCall( 'create', { uri : uri, label : label, width : width, height : height }, function( status, response ) {
					if( status !== 200 || response === null ) {
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
						return;
					}
					msg.textContent = 'Saved.';
					Nino.admin.images.init();
				} );
				return;
			}

			Nino.admin.images._apiCall( 'save', { uri : Nino.admin.images._currentUri, label : label, width : width, height : height }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}
				msg.textContent = 'Saved.';
				Nino.admin.images.init();
			} );
		},

		/**
		 *	Open the "hardcoded <img> tags found in templates, not backed by
		 *	any slot" scan results form - see Dev\Images::apiScan()
		 *
		 *	@return		void
		 */
		_openScanForm : function() {

			const wrap = dc.getElementById('images-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.images._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = 'Scan templates for missing image slots';
			wrap.appendChild( title );

			const msg = dc.createElement('p');
			msg.id = 'images-scan-msg';
			msg.textContent = 'Scanning …';
			wrap.appendChild( msg );

			Nino.admin.images._showForm();

			Nino.admin.images._apiCall( 'scan', {}, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Scan failed.' );
					return;
				}
				Nino.admin.images._renderScanForm( response.missing );
			} );
		},

		/**
		 *	Render the scan results: one row per <img src="/images/..."> not
		 *	backed by a slot - editable uri/label/width/height (pre-filled
		 *	from the tag's own attributes, or the actual file's real pixel
		 *	size if the tag had none) + an "Ignore" toggle. Submitting
		 *	creates every non-ignored row's slot via the existing
		 *	devimages/create action - filename stays empty, same "dev only
		 *	ever creates the empty slot, a real upload is _editor's job" rule
		 *	the manual "New image slot" form already follows
		 *
		 *	@param		{Array}		missing				[ { filename, src, suggestedUri, width, height, files[] }, ... ]
		 *
		 *	@return		void
		 */
		_renderScanForm : function( missing ) {

			const wrap = dc.getElementById('images-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.images._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = 'Scan templates for missing image slots';
			wrap.appendChild( title );

			if( missing.length === 0 ) {
				const p = dc.createElement('p');
				p.textContent = 'No missing slots found - every local <img src="/images/..."> is already backed by a slot.';
				wrap.appendChild( p );
				return;
			}

			const form = dc.createElement('form');
			const rows = [];

			missing.forEach( function( item ) {

				const field = dc.createElement('div');
				field.className = 'nino-admin-field';

				const span = dc.createElement('span');
				span.textContent = item.src+ '  ('+ item.files.join(', ')+ ')';
				field.appendChild( span );

				const uriSpan = dc.createElement('span');
				uriSpan.textContent = 'Uri';
				field.appendChild( uriSpan );
				const uriInput = dc.createElement('input');
				uriInput.type = 'text';
				uriInput.placeholder = 'Uri (eg. /home/hero)';
				uriInput.value = item.suggestedUri;
				field.appendChild( uriInput );

				const labelSpan = dc.createElement('span');
				labelSpan.textContent = 'Label';
				field.appendChild( labelSpan );
				const labelInput = dc.createElement('input');
				labelInput.type = 'text';
				labelInput.placeholder = 'Label';
				labelInput.value = item.suggestedUri.replace(/^\//, '').replace(/-/g, ' ');
				field.appendChild( labelInput );

				const widthSpan = dc.createElement('span');
				widthSpan.textContent = 'Width (px)';
				field.appendChild( widthSpan );
				const widthInput = dc.createElement('input');
				widthInput.type = 'number';
				widthInput.min = '1';
				widthInput.placeholder = 'Width (px)';
				widthInput.value = item.width || '';
				field.appendChild( widthInput );

				const heightSpan = dc.createElement('span');
				heightSpan.textContent = 'Height (px)';
				field.appendChild( heightSpan );
				const heightInput = dc.createElement('input');
				heightInput.type = 'number';
				heightInput.min = '1';
				heightInput.placeholder = 'Height (px)';
				heightInput.value = item.height || '';
				field.appendChild( heightInput );

				const ignoreLabel = dc.createElement('label');
				ignoreLabel.className = 'admin-scan-ignore';
				const ignoreCheck = dc.createElement('input');
				ignoreCheck.type = 'checkbox';
				ignoreLabel.appendChild( ignoreCheck );
				ignoreLabel.appendChild( dc.createTextNode(' Ignore') );
				field.appendChild( ignoreLabel );

				form.appendChild( field );

				rows.push( { uriInput : uriInput, labelInput : labelInput, widthInput : widthInput, heightInput : heightInput, ignoreCheck : ignoreCheck } );
			} );

			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Create the non-ignored slots';
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'images-scan-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.images._saveScanResults( rows ) } );

			wrap.appendChild( form );
		},

		/**
		 *	Create every non-ignored row's slot (see _renderScanForm()), one
		 *	devimages/create call at a time, so a per-row failure (eg. a
		 *	duplicate uri) is reported without swallowing the rest
		 *
		 *	@param		{Array}		rows
		 *
		 *	@return		void
		 */
		_saveScanResults : function( rows ) {

			const msg 		= dc.getElementById('images-scan-msg');
			const pending = rows.filter( function( row ) { return row.ignoreCheck.checked === false } );

			if( pending.length === 0 ) {
				Nino.admin.images._showList();
				return;
			}

			let created = 0;
			const failed = [];

			function next( i ) {

				if( i >= pending.length ) {
					if( failed.length > 0 )
						wn.alert( created+ ' created. Failed: '+ failed.join(', ') );
					Nino.admin.images.init();
					return;
				}

				const row = pending[i];
				msg.textContent = 'Creating '+ row.uriInput.value+ ' ('+ (i + 1)+ ' / '+ pending.length+ ') …';

				Nino.admin.images._apiCall( 'create', {
					uri 		: row.uriInput.value,
					label 	: row.labelInput.value,
					width 	: row.widthInput.value,
					height 	: row.heightInput.value,
				}, function( status ) {
					if( status === 200 )
						created++;
					else
						failed.push( row.uriInput.value );
					next( i + 1 );
				} );
			}

			next( 0 );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.images.init );

})(window, document, document.documentElement, document.body);
