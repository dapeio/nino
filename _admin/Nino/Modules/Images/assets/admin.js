

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js 								Admin "Images" panel: developer-fixed image slots
 *													(/nino/html/images in config.php), grouped by the
 *													slot uri's first path segment ("<category>/<identifier>",
 *													same convention as Text's key categories) into a
 *													category list -> all slots of that category shown at
 *													once. Slots can't be added/removed here, only the file
 *													each currently points to changes. Uploading works exactly
 *													like Elements' "image" field (immediate commit, centered
 *													crop/resize, no orphaned files on replace) - each slot's
 *													upload commits on its own the moment a file is chosen,
 *													so unlike Text there is no batched save for the category.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.images = {

		_groups				: {},
		_currentGroup	: null,
		_loading			: false,
		_ready				: false,

		/**
		 *	Load every image slot, group them and render the category list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('images-list') === null || Nino.admin.images._loading === true || Nino.admin.images._ready === true )
				return;

			Nino.admin.images._loading = true;

			Nino.admin.images._apiCall( 'list', {}, function( status, response ) {
				Nino.admin.images._loading = false;
				if( status !== 200 || response === null )
					return Nino.admin.images._showError( dc.getElementById('images-list'), status, response );

				// Capture the hash before any _show*() call below can overwrite it -
				// _showList() would otherwise wipe the deep-link part it's trying to restore
				const hash = Nino.admin.router.current();

				Nino.admin.images._groups = Nino.admin.images._groupSlots( response.slots );
				Nino.admin.images._renderCategoryList();
				Nino.admin.images._ready = true;

				if( hash.panel === 'images' && hash.parts.length > 0 && Nino.admin.images._groups[hash.parts[0]] !== undefined )
					Nino.admin.images._openGroup( hash.parts[0] );
				else
					Nino.admin.images._showList();
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

			if( Nino.admin.images._ready === false ) {
				Nino.admin.images.init();
				return;
			}

			if( dc.getElementById('images-form').classList.contains('admin-hidden') === false )
				return Nino.admin.images._showForm();

			Nino.admin.images._showList();
		},

		/**
		 *	Call an images/* admin action - see elements.js for why /_admin/
		 *	(trailing slash) and why extra multipart fields (eg. a File) just work
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "images/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *	@param		{Object}		[extra]				Extra multipart fields (eg. { file : File })
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback, extra ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, Object.assign( { action : 'images/'+ endpoint, data : JSON.stringify( payload ) }, extra || {} ) );
		},

		/**
		 *	Show a failed request's status/error in a container
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
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/images/error/load') );
			container.appendChild( p );
		},

		/**
		 *	Drill-down navigation: list -> form. The main Elemente/Texte/Bilder/
		 *	Nutzer bar stays visible throughout.
		 *
		 *	@return		void
		 */
		_showList : function() {
			dc.getElementById('images-list').classList.remove('admin-hidden');
			dc.getElementById('images-form').classList.add('admin-hidden');
			Nino.admin.router.set( 'images', [] );
		},

		_showForm : function() {
			dc.getElementById('images-list').classList.add('admin-hidden');
			dc.getElementById('images-form').classList.remove('admin-hidden');
			Nino.admin.router.set( 'images', [ Nino.admin.images._currentGroup ] );
		},

		/**
		 *	Group slots by the uri's first path segment (eg. "home/hero" -> "home"),
		 *	same convention as Text::_groupEntries()
		 *
		 *	@param		{Array}		slots					[ { uri, label, width, height, url }, ... ]
		 *
		 *	@return		{Object}									group name -> slots[]
		 */
		_groupSlots : function( slots ) {
			const groups = {};
			slots.forEach( function( slot ) {
				const group = slot.uri.split('/').filter( Boolean )[0] || '-';
				groups[group] = groups[group] || [];
				groups[group].push( slot );
			} );
			return groups;
		},

		/**
		 *	Render the category list, styled the same as Text's
		 *
		 *	@return		void
		 */
		_renderCategoryList : function() {

			const wrap = dc.getElementById('images-list');
			wrap.innerHTML = '';
			// The wrapper IS the list, so its classes belong to the rows: a
			// re-render that ends up empty has to take them off again, or the
			// notice below is drawn inside a list surface with nothing in it
			wrap.classList.remove( 'nino-admin-list', 'nino-admin-list-buttons' );

			// No slots at all - there is nothing to show a picture for yet, and
			// the tab that creates them is the next step (see the Slots tab)
			if( Object.keys( Nino.admin.images._groups ).length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/slots/empty/images') ) );
				return;
			}

			wrap.classList.add( 'nino-admin-list', 'nino-admin-list-buttons' );

			Object.keys( Nino.admin.images._groups ).sort().forEach( function( group ) {

				const slots = Nino.admin.images._groups[group];

				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'admin-type-btn';
				btn.dataset.group = group;

				const titleWrap = dc.createElement('div');
				titleWrap.textContent = group;

				const descr = dc.createElement('div');
				descr.className = 'admin-type-btn-descr';
				descr.textContent = '(' + slots.length + ') '+ slots.map( function( s ) { return s.label } ).join(', ');
				titleWrap.appendChild( descr );

				const chev = dc.createElement('span');
				chev.className = 'admin-view-button-chev';
				chev.setAttribute( 'aria-hidden', 'true' );
				chev.textContent = '›';

				btn.appendChild( titleWrap );
				btn.appendChild( chev );
				btn.addEventListener( 'click', function() { Nino.admin.images._openGroup( group ) } );

				wrap.appendChild( btn );
			} );
		},

		/**
		 *	Open a category, showing every one of its slots at once
		 *
		 *	@param		{string}	group
		 *
		 *	@return		void
		 */
		_openGroup : function( group ) {

			Nino.admin.images._currentGroup = group;
			Nino.admin.images._renderGroupForm();
			Nino.admin.images._showForm();
		},

		/**
		 *	Render every slot of the current category, stacked - each with its
		 *	own target dimensions, current preview (if any) and a file input
		 *	that uploads immediately, same as an Elements "image" field. Unlike
		 *	Text's category form, there is nothing to batch: a slot always
		 *	exists already (developer-fixed) and each upload commits on its own
		 *
		 *	@return		void
		 */
		_renderGroupForm : function() {

			const group = Nino.admin.images._currentGroup;
			const slots = Nino.admin.images._groups[group] ?? [];

			const wrap = dc.getElementById('images-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/images/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.images._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = group;
			wrap.appendChild( title );

			slots.forEach( function( slot ) {
				wrap.appendChild( Nino.admin.images._renderSlotField( slot ) );
			} );
		},

		/**
		 *	Render one slot as a labeled fieldset: target dimensions, current
		 *	preview (if any) and a file input that uploads immediately
		 *
		 *	@param		{Object}	slot
		 *
		 *	@return		{Element}								<fieldset>
		 */
		_renderSlotField : function( slot ) {

			const fieldset = dc.createElement('fieldset');
			fieldset.className = 'images-slot-field';
			const legend = dc.createElement('legend');
			legend.textContent = slot.label;
			fieldset.appendChild( legend );

			// The actual shortcode usage for this slot, copy/paste-ready for templating.
			// Deliberately built from two string pieces, not one "[image " literal -
			// this whole bundle is itself re-run through Html::renderHtml() when the
			// admin's own assets get cached (see Assets::_createCachefile()), and a
			// literal "[image ...]"-shaped string is indistinguishable there from a
			// real shortcode call, silently resolving to '' since no such slot exists
			const imageUri = dc.createElement('span');
			imageUri.className = 'nino-admin-field-name';
			imageUri.textContent = '[' + 'image ' + slot.uri + ']';
			fieldset.appendChild( imageUri );

			const dimensions = dc.createElement('p');
			dimensions.className = 'nino-admin-field-image-dimensions';
			dimensions.textContent = Nino.content.getText('/_admin/common/label/image-target')+ ' '+ slot.width+ ' × '+ slot.height+ ' px';
			fieldset.appendChild( dimensions );

			const imageWrap = dc.createElement('div');
			imageWrap.className = 'nino-admin-field-image';

			const preview = dc.createElement('img');
			preview.className = 'nino-admin-field-image-preview';
			preview.hidden = ! slot.url;
			if( slot.url )
				preview.src = slot.url;
			imageWrap.appendChild( preview );

			const fileInput = dc.createElement('input');
			fileInput.type = 'file';
			fileInput.accept = 'image/*';

			const msg = dc.createElement('p');
			msg.className = 'nino-admin-field-image-msg';
			msg.setAttribute( 'aria-live', 'polite' );

			fileInput.addEventListener( 'change', function() {
				if( fileInput.files.length === 0 )
					return;
				Nino.admin.images._uploadImage( slot, fileInput.files[0], preview, msg, fileInput );
			} );
			imageWrap.appendChild( fileInput );
			imageWrap.appendChild( msg );

			fieldset.appendChild( imageWrap );
			return fieldset;
		},

		/**
		 *	Upload a new image for one slot, immediately - the server commits
		 *	it straight away and deletes the previous file
		 *
		 *	@param		{Object}	slot
		 *	@param		{File}		file
		 *	@param		{Element}	preview					<img> preview element
		 *	@param		{Element}	msg							Status message element
		 *	@param		{Element}	fileInput				The <input type=file> itself, disabled while pending
		 *
		 *	@return		void
		 */
		_uploadImage : function( slot, file, preview, msg, fileInput ) {

			fileInput.disabled = true;
			msg.className = 'nino-admin-field-image-msg';
			msg.textContent = Nino.content.getText('/_admin/images/msg/pending');

			Nino.admin.images._apiCall( 'upload', { uri : slot.uri }, function( status, response ) {

				fileInput.disabled = false;
				fileInput.value = '';

				if( status !== 200 || response === null ) {
					msg.className = 'nino-admin-field-image-msg error';
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/images/error/save') );
					return;
				}

				slot.url = response.url;
				preview.src = response.url;
				preview.hidden = false;
				msg.textContent = Nino.content.getText('/_admin/images/msg/saved');
			}, { file : file } );
		},
	};

})(window, document, document.documentElement, document.body);
