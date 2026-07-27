

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Images" module: create/edit image slot definitions
 *													(label/width/height) - the "set" half of what _admin's
 *													Images panel edits ("values" half: which file currently
 *													fills a slot). Same split as elementtypes.js/_admin's
 *													Elements. Never touches a slot's filename.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.dev = wn.Nino.dev || {};

	Nino.dev.images = {

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

			Nino.dev.images._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.dev.images._showError( dc.getElementById('images-list'), status, response );

				Nino.dev.images._slots = response.slots;
				Nino.dev.images._renderList();
				Nino.dev.images._showList();
				Nino.dev.images._ready = true;
			} );
		},

		/**
		 *	Re-show whichever level (list or form) is currently on
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.dev.images._ready === false )
				return;

			if( dc.getElementById('images-form').classList.contains('dev-hidden') === false )
				return Nino.dev.images._showForm();

			Nino.dev.images._showList();
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
			Nino.http.sendRequest( '/_dev/', 'POST', function( xhr ) {
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
			p.className = 'dev-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			container.appendChild( p );
		},

		_showList : function() {
			dc.getElementById('images-list').classList.remove('dev-hidden');
			dc.getElementById('images-form').classList.add('dev-hidden');
		},

		_showForm : function() {
			dc.getElementById('images-list').classList.add('dev-hidden');
			dc.getElementById('images-form').classList.remove('dev-hidden');
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
			Nino.dev.images._slots.forEach( function( slot ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';
				link.textContent = slot.label+ ' ('+ slot.uri+ ', '+ slot.width+ '×'+ slot.height+ ( slot.hasImage ? '' : ', no image' )+ ')';
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.dev.images._openForm( slot ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'admin-list-action';
			addBtn.textContent = 'New image slot';
			addBtn.addEventListener( 'click', function() { Nino.dev.images._openForm( null ) } );
			wrap.appendChild( addBtn );

			const scanBtn = dc.createElement('button');
			scanBtn.type = 'button';
			scanBtn.className = 'admin-list-action';
			scanBtn.textContent = 'Scan templates for missing image slots';
			scanBtn.addEventListener( 'click', function() { Nino.dev.images._openScanForm() } );
			wrap.appendChild( scanBtn );
		},

		/**
		 *	Open the editor for an existing slot, or a blank one for a new slot
		 *
		 *	@param		{Object|null}	slot		{ uri, label, width, height, hasImage }, or null to create new
		 *
		 *	@return		void
		 */
		_openForm : function( slot ) {

			Nino.dev.images._isNew 			= slot === null;
			Nino.dev.images._currentUri = slot ? slot.uri : null;
			Nino.dev.images._renderForm( slot ?? {} );
			Nino.dev.images._showForm();
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
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.dev.images._showList() } );
			wrap.appendChild( backLink );

			const form = dc.createElement('form');

			if( Nino.dev.images._isNew === true ) {
				const uriLabel = dc.createElement('label');
				uriLabel.className = 'admin-field';
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
			labelLabel.className = 'admin-field';
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
			widthLabel.className = 'admin-field';
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
			heightLabel.className = 'admin-field';
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

			const msg = dc.createElement('p');
			msg.id = 'images-form-msg';
			form.appendChild( msg );

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save';
			form.appendChild( saveBtn );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.dev.images._save() } );

			wrap.appendChild( form );

			if( Nino.dev.images._isNew === false ) {
				const deleteBtn = dc.createElement('button');
				deleteBtn.type = 'button';
				deleteBtn.className = 'dev-danger-btn';
				deleteBtn.textContent = 'Delete slot';
				deleteBtn.addEventListener( 'click', function() { Nino.dev.images._delete() } );
				wrap.appendChild( deleteBtn );
			}
		},

		/**
		 *	Delete the slot currently open, after confirmation - its
		 *	uploaded file (if any) goes with it
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( wn.confirm( 'Really delete image slot "'+ Nino.dev.images._currentUri+ '"? Any uploaded image is lost with it.' ) === false )
				return;

			const msg = dc.getElementById('images-form-msg');
			msg.textContent = 'Deleting …';

			Nino.dev.images._apiCall( 'delete', { uri : Nino.dev.images._currentUri }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to delete.' );
					return;
				}
				Nino.dev.images.init();
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

			if( Nino.dev.images._isNew === true ) {
				const uri = dc.getElementById('images-form-uri').value;
				Nino.dev.images._apiCall( 'create', { uri : uri, label : label, width : width, height : height }, function( status, response ) {
					if( status !== 200 || response === null ) {
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
						return;
					}
					msg.textContent = 'Saved.';
					Nino.dev.images.init();
				} );
				return;
			}

			Nino.dev.images._apiCall( 'save', { uri : Nino.dev.images._currentUri, label : label, width : width, height : height }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}
				msg.textContent = 'Saved.';
				Nino.dev.images.init();
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
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.dev.images._showList() } );
			wrap.appendChild( backLink );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = 'Scan templates for missing image slots';
			wrap.appendChild( title );

			const msg = dc.createElement('p');
			msg.id = 'images-scan-msg';
			msg.textContent = 'Scanning …';
			wrap.appendChild( msg );

			Nino.dev.images._showForm();

			Nino.dev.images._apiCall( 'scan', {}, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Scan failed.' );
					return;
				}
				Nino.dev.images._renderScanForm( response.missing );
			} );
		},

		/**
		 *	Render the scan results: one row per <img src="/images/..."> not
		 *	backed by a slot - editable uri/label/width/height (pre-filled
		 *	from the tag's own attributes, or the actual file's real pixel
		 *	size if the tag had none) + an "Ignore" toggle. Submitting
		 *	creates every non-ignored row's slot via the existing
		 *	devimages/create action - filename stays empty, same "dev only
		 *	ever creates the empty slot, a real upload is _admin's job" rule
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
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.dev.images._showList() } );
			wrap.appendChild( backLink );

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
				field.className = 'admin-field';

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
				ignoreLabel.className = 'dev-scan-ignore';
				const ignoreCheck = dc.createElement('input');
				ignoreCheck.type = 'checkbox';
				ignoreLabel.appendChild( ignoreCheck );
				ignoreLabel.appendChild( dc.createTextNode(' Ignore') );
				field.appendChild( ignoreLabel );

				form.appendChild( field );

				rows.push( { uriInput : uriInput, labelInput : labelInput, widthInput : widthInput, heightInput : heightInput, ignoreCheck : ignoreCheck } );
			} );

			const msg = dc.createElement('p');
			msg.id = 'images-scan-msg';
			form.appendChild( msg );

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Create the non-ignored slots';
			form.appendChild( saveBtn );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.dev.images._saveScanResults( rows ) } );

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
				Nino.dev.images._showList();
				return;
			}

			let created = 0;
			const failed = [];

			function next( i ) {

				if( i >= pending.length ) {
					if( failed.length > 0 )
						wn.alert( created+ ' created. Failed: '+ failed.join(', ') );
					Nino.dev.images.init();
					return;
				}

				const row = pending[i];
				msg.textContent = 'Creating '+ row.uriInput.value+ ' ('+ (i + 1)+ ' / '+ pending.length+ ') …';

				Nino.dev.images._apiCall( 'create', {
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

	Nino.events.bindCallback( 'ready', Nino.dev.images.init );

})(window, document, document.documentElement, document.body);
