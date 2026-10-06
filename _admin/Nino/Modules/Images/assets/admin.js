

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
 *													each currently points to changes - or goes away: Remove
 *													takes the image out of a slot after a question and leaves
 *													the slot. Uploading works exactly
 *													like Elements' "image" field (immediate commit, centered
 *													crop/resize, no orphaned files on replace) - each slot's
 *													upload commits on its own the moment a file is chosen,
 *													so unlike Text there is no batched save for the category.
 *													A picture smaller than the slot's target size is saved
 *													and said to be: crop mode scales it up. Each slot also
 *													says which pages use it - or that none does - and keeps
 *													an alt text per language, saved on its own button; empty
 *													means decorative. An alt text typed and not saved is
 *													reported to Nino.admin.dirty, which asks before the form
 *													is left.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.images = {

		_groups				: {},
		_locales			: [],
		_altCount			: 0,
		_altEditors		: [],
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

				Nino.admin.images._locales = response.locales || [];
				Nino.admin.images._groups = Nino.admin.images._groupSlots( response.slots );
				Nino.admin.images._renderCategoryList();
				Nino.admin.images._ready = true;

				const named = Nino.admin.images._groupOf( hash.parts );

				if( hash.panel === 'images' && named !== null )
					Nino.admin.images._openGroup( named );
				else
					Nino.admin.images._showList();
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
		 *	click on the rail) leaves the level in memory as it is
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.images._ready === false ) {
				Nino.admin.images.init();
				return;
			}

			const hash = Nino.admin.router.current();
			if( hash.panel === 'images' && Nino.admin.images._follow( hash.parts ) === true )
				return;

			Nino.admin.images._showLevel();
		},

		/**
		 *	Show the level this panel is on, and write it into the address
		 *
		 *	@return		void
		 */
		_showLevel : function() {

			if( dc.getElementById('images-form').classList.contains('admin-hidden') === false )
				return Nino.admin.images._showForm();

			Nino.admin.images._showList();
		},

		/**
		 *	Move to the level the hash names, if it is not the one on screen.
		 *	An unknown group is the list. Leaving a form with unsaved alt texts
		 *	asks first (see Nino.admin.router.leave())
		 *
		 *	@param		{Array}		parts					The hash behind the panel's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const open = dc.getElementById('images-form').classList.contains('admin-hidden') === false;
			const group = Nino.admin.images._groupOf( parts );

			if( group === null ? open === false : ( open === true && Nino.admin.images._currentGroup === group ) )
				return false;

			Nino.admin.router.leave( [ 'images' ], open, function() {
				if( group === null ) {
					Nino.admin.images._showList();
					return;
				}
				Nino.admin.images._currentGroup = group;
				Nino.admin.images._renderGroupForm();
				Nino.admin.images._showForm();
			}, Nino.admin.images._showLevel );

			return true;
		},

		/**
		 *	The group a hash names, or null: its parts are the group's segments,
		 *	the way the Text panel writes a row (#images/template/page-home), and
		 *	the group as one part (#images/template%2Fpage-home, an address
		 *	written before) reads the same
		 *
		 *	@param		{Array}		parts					The hash behind the panel's name
		 *
		 *	@return		{string|null}
		 */
		_groupOf : function( parts ) {

			const group = parts.join('/');

			return parts.length > 0 && Nino.admin.images._groups[group] !== undefined ? group : null;
		},

		/**
		 *	The parts of the address that name a group: its segments
		 *
		 *	@param		{string}	group
		 *
		 *	@return		{Array<string>}
		 */
		_groupParts : function( group ) {
			return String( group ).split('/');
		},

		/**
		 *	What a group is called in the list and over its form: its category,
		 *	named by the vocabulary - "Page home" for template/page-home, "Logo"
		 *	for logo - as the Text panel names the same category
		 *
		 *	@param		{string}	group
		 *
		 *	@return		{string}
		 */
		_groupLabel : function( group ) {
			return Nino.adminUi.slugLabel( String( group ).split('/').pop() );
		},

		/**
		 *	Call an images/* admin action - this panel's name for
		 *	Nino.adminUi.api.call(), which owns where the request goes and why
		 *	extra multipart fields (eg. a File) just work
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "images/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *	@param		{Object}		[extra]				Extra multipart fields (eg. { file : File })
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback, extra ) {
			Nino.adminUi.api.call( 'images/'+ endpoint, payload, callback, extra );
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
			Nino.adminUi.showError( container, status, response, '/_admin/images/error/load' );
		},

		/**
		 *	Drill-down navigation: list -> form. The main Elements/Text/Images/
		 *	Users bar stays visible throughout.
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
			Nino.admin.router.set( 'images', Nino.admin.images._groupParts( Nino.admin.images._currentGroup ) );
		},

		/**
		 *	Group slots as the Text panel groups keys - by what Nino.adminUi.describeKey()
		 *	makes of the uri: /<namespace>/<category> for a slot that follows the
		 *	grammar of a text key (a template's, "template/page-home"), the first
		 *	path segment for any other ("logo")
		 *
		 *	@param		{Array}		slots					[ { uri, label, width, height, url, alt, usage }, ... ]
		 *
		 *	@return		{Object}									group name -> slots[]
		 */
		_groupSlots : function( slots ) {
			const groups = Object.create( null );
			slots.forEach( function( slot ) {
				const described = Nino.adminUi.describeKey( slot.uri );
				const group = described.kind === 'grammar' ? described.namespace+ '/'+ described.category : ( slot.uri.split('/').filter( Boolean )[0] || '-' );
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
				titleWrap.textContent = Nino.admin.images._groupLabel( group );

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
		 *	Open a category, showing every one of its slots at once - after
		 *	the shell has asked about alt texts typed into the form on screen,
		 *	where it has the registry (see Nino.admin.dirty). A shell without
		 *	it (an older one, a test) goes straight on
		 *
		 *	@param		{string}	group
		 *
		 *	@return		void
		 */
		_openGroup : function( group ) {

			const open = function() {
				Nino.admin.images._currentGroup = group;
				Nino.admin.images._renderGroupForm();
				Nino.admin.router.go( 'images', Nino.admin.images._groupParts( group ) );
				Nino.admin.images._showForm();
			};

			if( typeof Nino.admin.dirty !== 'object' ) {
				open();
				return;
			}

			Nino.admin.dirty.guard( [ 'images' ], open );
		},

		/**
		 *	Render every slot of the current category, stacked - each with its
		 *	own target dimensions, current preview (if any) and a file input
		 *	that uploads immediately, same as an Elements "image" field. Unlike
		 *	Text's category form, there is nothing to batch: a slot always
		 *	exists already (developer-fixed) and each upload commits on its own.
		 *	The alt texts are the one thing typed into it that is not committed
		 *	at once: each slot saves its own on its button, and the shell saves
		 *	the ones still open when it asks about unsaved input
		 *
		 *	@return		void
		 */
		_renderGroupForm : function() {

			const group = Nino.admin.images._currentGroup;
			const slots = Nino.admin.images._groups[group] ?? [];

			const wrap = dc.getElementById('images-form');
			wrap.innerHTML = '';
			Nino.admin.images._altEditors = [];

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/images/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.router.go( 'images', [] ); Nino.admin.images._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const title = dc.createElement('div');
			title.className = 'main-title';
			title.textContent = Nino.admin.images._groupLabel( group );
			wrap.appendChild( title );

			slots.forEach( function( slot ) {
				wrap.appendChild( Nino.admin.images._renderSlotField( slot ) );
			} );

			Nino.admin.images._refreshDirty();
		},

		/**
		 *	Render one slot as a labeled fieldset: target dimensions, where
		 *	it is used, current preview (if any), a file input that uploads
		 *	immediately, a Remove button and the alt texts
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
			fieldset.appendChild( Nino.admin.images._renderUsage( slot ) );

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

			// The slot stays and its image goes: only there is one to take away
			const removeBtn = dc.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'nino-admin-btn-danger';
			removeBtn.textContent = Nino.content.getText('/_admin/images/label/remove');
			removeBtn.hidden = ! slot.url;
			removeBtn.addEventListener( 'click', function() {
				Nino.admin.images._removeImage( slot, preview, msg, removeBtn );
			} );

			fileInput.addEventListener( 'change', function() {
				if( fileInput.files.length === 0 )
					return;
				Nino.admin.images._uploadImage( slot, fileInput.files[0], preview, msg, fileInput, removeBtn );
			} );
			imageWrap.appendChild( fileInput );
			// What the server will take, before the file is chosen
			const hint = Nino.adminUi.uploadHint();
			if( hint !== null )
				imageWrap.appendChild( hint );
			imageWrap.appendChild( removeBtn );
			imageWrap.appendChild( msg );

			fieldset.appendChild( imageWrap );
			fieldset.appendChild( Nino.admin.images._renderAlt( slot ) );
			return fieldset;
		},

		/**
		 *	The line that says where a slot is used - the pages whose
		 *	templates show it - or, with the warning's modifier, that none
		 *	does: an image uploaded to such a slot never appears on the
		 *	website. The words carry the warning, not only the colour
		 *
		 *	@param		{Object}	slot
		 *
		 *	@return		{Element}								<p>
		 */
		_renderUsage : function( slot ) {

			const pages = ( slot.usage && slot.usage.pages ) || [];

			const line = dc.createElement('p');
			line.className = 'nino-admin-field-hint';

			if( pages.length === 0 ) {
				line.className = 'nino-admin-field-hint is-warning';
				line.textContent = Nino.content.getText('/_admin/images/msg/unused');
				return line;
			}

			line.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/images/label/usedon'), pages.map( function( page ) {
				return page.name === page.httpUri ? page.httpUri : page.name+ ' ('+ page.httpUri+ ')';
			} ).join(', ') );

			return line;
		},

		/**
		 *	One text input per available site language for the
		 *	slot's alt text, one hint that every input points to, and a button
		 *	that saves them all - an alt text is not part of an upload and has
		 *	its own request. Empty means decorative, which the hint says
		 *
		 *	@param		{Object}	slot
		 *
		 *	@return		{Element}								<div>
		 */
		_renderAlt : function( slot ) {

			const wrap = dc.createElement('div');
			wrap.className = 'images-slot-alt';

			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.id = 'images-alt-hint-'+ ( ++Nino.admin.images._altCount );
			hint.textContent = Nino.content.getText('/_admin/images/hint/alt');
			wrap.appendChild( hint );

			const inputs = {};
			Nino.admin.images._locales.forEach( function( locale ) {

				const label = dc.createElement('label');
				label.className = 'nino-admin-field';
				const name = dc.createElement('span');
				name.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/images/label/alt'), locale );
				label.appendChild( name );

				const input = dc.createElement('input');
				input.type = 'text';
				input.maxLength = 250;
				input.value = ( slot.alt && slot.alt[locale] ) || '';
				input.setAttribute( 'aria-describedby', hint.id );
				label.appendChild( input );

				inputs[locale] = input;
				wrap.appendChild( label );
			} );

			const msg = dc.createElement('p');
			msg.className = 'nino-admin-field-image-msg';
			msg.setAttribute( 'aria-live', 'polite' );

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'button';
			saveBtn.className = 'nino-admin-btn-secondary';
			saveBtn.textContent = Nino.content.getText('/_admin/images/label/alt-save');

			const editor = { slot : slot, inputs : inputs, msg : msg, saveBtn : saveBtn, saving : false };
			Nino.admin.images._altEditors.push( editor );

			saveBtn.addEventListener( 'click', function() { Nino.admin.images._saveAlt( editor ) } );

			wrap.appendChild( saveBtn );
			wrap.appendChild( msg );

			return wrap;
		},

		/**
		 *	Save the alt texts of one slot: every input it shows, empty ones
		 *	included, in one request. What the server stored (cleaned) is put
		 *	back into the inputs that still hold what was sent, so that a text
		 *	saved with a trailing space does not stay "unsaved"
		 *
		 *	Every way this ends reports to done( ok ), if there is one: the
		 *	shell's question about unsaved input saves through it and goes on
		 *	only when it hears true (see Nino.admin.dirty.guard())
		 *
		 *	@param		{Object}		editor				One entry of _altEditors
		 *	@param		{Function}	[done]				Called once with true when the texts were written, false otherwise
		 *
		 *	@return		void
		 */
		_saveAlt : function( editor, done ) {

			const report = function( ok ) {
				Nino.admin.images._refreshDirty();
				if( typeof done === 'function' )
					done( ok );
			};

			// A second submit while one runs: the one running decides
			if( editor.saving === true ) {
				report( false );
				return;
			}

			const sent = {};
			Object.keys( editor.inputs ).forEach( function( locale ) { sent[locale] = editor.inputs[locale].value } );

			editor.saving = true;
			editor.saveBtn.disabled = true;
			editor.msg.className = 'nino-admin-field-image-msg';
			editor.msg.textContent = Nino.content.getText('/_admin/images/msg/pending');

			Nino.admin.images._apiCall( 'alt', { uri : editor.slot.uri, alt : sent }, function( status, response ) {

				editor.saving = false;
				editor.saveBtn.disabled = false;

				if( status !== 200 || response === null ) {
					editor.msg.className = 'nino-admin-field-image-msg is-error';
					editor.msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/images/error/alt' );
					report( false );
					return;
				}

				editor.slot.alt = response.alt || {};
				Object.keys( editor.inputs ).forEach( function( locale ) {
					if( editor.inputs[locale].value === sent[locale] )
						editor.inputs[locale].value = editor.slot.alt[locale] || '';
				} );
				editor.msg.textContent = Nino.content.getText('/_admin/images/msg/alt-saved');
				report( true );
			} );
		},

		/**
		 *	Whether the inputs of one slot's alt texts differ from what is stored
		 *
		 *	@param		{Object}		editor				One entry of _altEditors
		 *
		 *	@return		{boolean}
		 */
		_altChanged : function( editor ) {
			return Object.keys( editor.inputs ).some( function( locale ) {
				return editor.inputs[locale].value !== ( ( editor.slot.alt && editor.slot.alt[locale] ) || '' );
			} );
		},

		/**
		 *	Whether the category on screen holds alt texts nobody has saved -
		 *	what the shell asks before it lets anything throw that away (see
		 *	Nino.admin.dirty). A list has nothing typed into it, and an upload
		 *	or a removal commits on its own
		 *
		 *	@return		{boolean}
		 */
		isDirty : function() {

			const form = dc.getElementById('images-form');

			if( form === null || form.classList.contains('admin-hidden') === true )
				return false;

			return Nino.admin.images._altEditors.some( Nino.admin.images._altChanged );
		},

		/**
		 *	Save every slot's alt texts that differ from what is stored, one
		 *	request after the other - the shell's Save in its question about
		 *	unsaved input. Stops at the first that fails
		 *
		 *	@param		{Function}	done					Called once with true when everything was written, false otherwise
		 *
		 *	@return		void
		 */
		save : function( done ) {

			const open = Nino.admin.images._altEditors.filter( Nino.admin.images._altChanged );
			let at = 0;

			const next = function() {

				if( at >= open.length ) {
					done( true );
					return;
				}

				Nino.admin.images._saveAlt( open[at++], function( ok ) {
					if( ok === true )
						next();
					else
						done( false );
				} );
			};

			next();
		},

		/**
		 *	Throw the input away: the inputs go back to what is stored. The
		 *	form is about to be left or drawn again. A slot whose save is
		 *	running is left alone - it finishes with what it was given
		 *
		 *	@return		void
		 */
		discard : function() {

			Nino.admin.images._altEditors.forEach( function( editor ) {

				if( editor.saving === true )
					return;

				Object.keys( editor.inputs ).forEach( function( locale ) {
					editor.inputs[locale].value = ( editor.slot.alt && editor.slot.alt[locale] ) || '';
				} );
			} );

			Nino.admin.images._refreshDirty();
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
		 *	Take the image out of a slot, after asking - the question names the
		 *	slot and what follows: the file is deleted and the website shows
		 *	nothing there. A cancelled question sends nothing
		 *
		 *	@param		{Object}	slot
		 *	@param		{Element}	preview					<img> preview element
		 *	@param		{Element}	msg							Status message element
		 *	@param		{Element}	removeBtn				The Remove button itself, disabled while pending
		 *
		 *	@return		void
		 */
		_removeImage : function( slot, preview, msg, removeBtn ) {

			if( wn.confirm( Nino.adminUi.format( Nino.content.getText('/_admin/images/confirm/remove'), slot.label ) ) === false )
				return;

			removeBtn.disabled = true;
			msg.className = 'nino-admin-field-image-msg';
			msg.textContent = Nino.content.getText('/_admin/images/msg/pending');

			Nino.admin.images._apiCall( 'remove', { uri : slot.uri }, function( status, response ) {

				removeBtn.disabled = false;

				if( status !== 200 || response === null ) {
					msg.className = 'nino-admin-field-image-msg is-error';
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/images/error/remove' );
					return;
				}

				slot.url = null;
				preview.hidden = true;
				removeBtn.hidden = true;
				msg.textContent = Nino.content.getText('/_admin/images/msg/removed');
			} );
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
		 *	@param		{Element}	removeBtn				The Remove button, shown once there is an image
		 *
		 *	@return		void
		 */
		_uploadImage : function( slot, file, preview, msg, fileInput, removeBtn ) {

			fileInput.disabled = true;
			msg.className = 'nino-admin-field-image-msg';
			msg.textContent = Nino.content.getText('/_admin/images/msg/pending');

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

				Nino.admin.images._apiCall( 'upload', { uri : slot.uri }, function( status, response ) {

					fileInput.disabled = false;
					fileInput.value = '';

					if( status !== 200 || response === null ) {
						msg.className = 'nino-admin-field-image-msg is-error';
						msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/images/error/save' );
						return;
					}

					slot.url = response.url;

					// The stored name is deterministic per slot, so replacing a
					// picture answers the url the browser already has in its cache
					// - and public/images/ is served statically, with no
					// Cache-Control of its own. The panel said "saved" while the
					// preview still showed the old picture until a hard reload.
					// The stamp is on the <img> only; the url the panel keeps and
					// the page later renders stays the clean one
					preview.src = response.url + ( response.url.indexOf('?') === -1 ? '?' : '&' ) + 't=' + Date.now();
					preview.hidden = false;
					removeBtn.hidden = false;

					// Saved either way - but a picture below the target size was
					// scaled up, and that is said where it can be seen: in words
					// as well as in colour
					if( response.belowTarget === true && response.source ) {
						msg.className = 'nino-admin-field-image-msg is-warning';
						msg.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/images/msg/below-target'), response.source.width+ ' × '+ response.source.height+ ' px' );
						return;
					}

					msg.textContent = Nino.content.getText('/_admin/images/msg/saved');
				}, { file : file } );
			} );
		},
	};

	// The shell asks before anything throws alt texts typed and not saved away
	// (see Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.register( 'images', {
			isDirty : Nino.admin.images.isDirty,
			save		: Nino.admin.images.save,
			discard : Nino.admin.images.discard,
		} );

})(window, document, document.documentElement, document.body);
