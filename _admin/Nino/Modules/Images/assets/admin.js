

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js 								Admin "Images" panel: developer-fixed image slots
 *													(/nino/html/images in config.php), grouped by the
 *													slot uri's first path segment ("<category>/<identifier>",
 *													same convention as Text's key categories) into a list of
 *													categories -> all slots of that category shown at once,
 *													each category with an address of its own
 *													(#images/images/<category>). Slots can't be added/removed here,
 *													only the file each currently points to changes - or goes
 *													away: Remove takes the image out of a slot after a
 *													question and leaves the slot.
 *
 *													Everything here is immediate - the guide's third model - so
 *													there is no Save and no bar. A file that is chosen is stored
 *													at once, cut to the slot's size as an Elements "image" field
 *													is, and a toast says so; an alt text, one short value for each
 *													language, is saved when its field is left and its value has
 *													changed, and a toast says so. What the server refuses is said
 *													at the field, and the value stays in it: Nino.admin.dirty
 *													knows of it, so that no change of view loses it silently. A
 *													picture smaller than the slot's target size is saved and said
 *													to be, where it stays in view: crop mode scales it up. Each
 *													slot also says which pages use it - or that none does.
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
		_editors			: [],
		_currentGroup	: null,
		_loading			: false,
		_ready				: false,
		// The stamp of the templates (see Nino.admin.touch()) the slots were asked
		// for: each slot says which pages use it, and that is what a template
		// written since is not in
		_stamp				: 0,

		/**
		 *	Load every image slot, group them and render the category list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('images-list') === null || Nino.admin.images._loading === true || Nino.admin.images._ready === true )
				return;

			Nino.admin.images._loading = true;

			// A template written while the answer is on its way is not in it: the
			// stamp is the one from before the request went out
			const stamp = Nino.admin.stamp( 'templates' );

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
				Nino.admin.images._stamp = stamp;

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
		 *	click on the rail) leaves the level in memory as it is.
		 *
		 *	The slots are read again where a template was written since they were
		 *	read (the Builder creates them, with the image slots they use), so
		 *	that the list has them. Not while a category is open: the list would
		 *	be drawn over it, and the showing that finds none open reads them
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.images._ready === false ) {
				Nino.admin.images.init();
				return;
			}

			if( dc.getElementById('images-form').classList.contains('admin-hidden') === true && Nino.admin.stamp( 'templates' ) !== Nino.admin.images._stamp ) {
				Nino.admin.images._ready = false;
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
		 *	The group a hash names, or null: the group is the one part,
		 *	whatever it holds ('template/page-home')
		 *
		 *	@param		{Array}		parts					The hash behind the panel's name
		 *
		 *	@return		{string|null}
		 */
		_groupOf : function( parts ) {
			return parts.length === 1 && Nino.admin.images._groups[parts[0]] !== undefined ? parts[0] : null;
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
		 *	Users bar stays visible throughout. The focus stays where it is,
		 *	unless it stood in the level that is hidden now (see
		 *	Nino.adminUi.showLevel()): the level is shown again as it stands, by
		 *	the address. Back goes to the category, and what a person opens takes
		 *	the focus in
		 *
		 *	@param		{Element|null}	[opener]	Back: the control that opened the form that is left; null where there is none to name
		 *
		 *	@return		void
		 */
		_showList : function( opener ) {
			Nino.admin.router.set( 'images', [] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'images-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'images-list', opener );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {
			Nino.admin.router.set( 'images', [ Nino.admin.images._currentGroup ] );
			Nino.adminUi.showLevel( 'images-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	The row of a category as the list is drawn now: the list is drawn
		 *	again where the slots are read anew, and the link that was pressed is
		 *	gone with the old drawing
		 *
		 *	@param		{string}	group
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( group ) {

			const row = Array.from( dc.getElementById('images-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === group } );

			return row === undefined ? null : row.querySelector('a');
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
		 *	Render the category list: a row for each category, a link with the
		 *	address of the category, its name over the number of its slots and
		 *	their names. No slots at all: the tab that creates them is the next step
		 *
		 *	@return		void
		 */
		_renderCategoryList : function() {

			const wrap = dc.getElementById('images-list');
			wrap.innerHTML = '';

			if( Object.keys( Nino.admin.images._groups ).length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/slots/empty/images'), Nino.adminUi.button( Nino.content.getText('/_admin/nav/slots'), function() { wn.location.hash = Nino.admin.router.href( 'slots', [] ) } ) ) );
				return;
			}

			wrap.appendChild( Nino.adminUi.list( Object.keys( Nino.admin.images._groups ).sort().map( function( group ) {

				const slots = Nino.admin.images._groups[group];

				return {
					key			: group,
					title		: Nino.admin.images._groupLabel( group ),
					detail	: Nino.adminUi.format( Nino.content.getText('/_admin/images/label/count'), slots.length )+ ' · '+ slots.map( function( slot ) { return slot.label } ).join(', '),
					href		: Nino.admin.router.href( 'images', [ group ] ),
					onOpen	: function() { Nino.admin.images._openGroup( group, true ) },
				};
			} ), { label : Nino.content.getText('/_admin/nav/images') } ) );
		},

		/**
		 *	Open a category, showing every one of its slots at once - after
		 *	the shell has asked about alt texts typed into the form on screen,
		 *	where it has the registry (see Nino.admin.dirty). A shell without
		 *	it (an older one, a test) goes straight on
		 *
		 *	@param		{string}		group
		 *	@param		{boolean}		[opened]	The person pressed its row: the focus goes into the form. Otherwise it is shown as it stands, by the address
		 *
		 *	@return		void
		 */
		_openGroup : function( group, opened ) {

			const open = function() {
				Nino.admin.images._currentGroup = group;
				Nino.admin.images._renderGroupForm();
				Nino.admin.router.go( 'images', [ group ] );
				Nino.admin.images._showForm( opened === true );
			};

			if( typeof Nino.admin.dirty !== 'object' )
				open();
			else
				Nino.admin.dirty.guard( [ 'images' ], open );
		},

		/**
		 *	Render every slot of the current category, stacked - each with its
		 *	own target dimensions, current preview (if any) and a file field
		 *	that uploads immediately, same as an Elements "image" field. Unlike
		 *	Text's category form, there is nothing to batch: a slot always
		 *	exists already (developer-fixed), each upload commits on its own and
		 *	so does each alt text, when its field is left
		 *
		 *	@return		void
		 */
		_renderGroupForm : function() {

			const group = Nino.admin.images._currentGroup;
			const slots = Nino.admin.images._groups[group] ?? [];

			const wrap = dc.getElementById('images-form');
			wrap.innerHTML = '';
			Nino.admin.images._editors = [];

			wrap.appendChild( Nino.adminUi.contextBar( {
				back	: { to : wrap, href : Nino.admin.router.href( 'images', [] ), onClick : function() { Nino.admin.images._guarded( function() {
					Nino.admin.router.go( 'images', [] );
					Nino.admin.images._showList( Nino.admin.images._rowOf( Nino.admin.images._currentGroup ) );
				} ) } },
				title	: Nino.admin.images._groupLabel( group ),
			} ) );

			slots.forEach( function( slot ) {
				wrap.appendChild( Nino.admin.images._renderSlotField( slot ) );
			} );

			Nino.admin.images._refreshDirty();
		},

		/**
		 *	A line of text a slot shows and does not take - its target size, the pages that use it, the call a
		 *	template writes for it: no input, no stop of the Tab key, the name at its left
		 *
		 *	@param		{string}				label
		 *	@param		{Element|string}	value
		 *
		 *	@return		{Element}										The field
		 */
		_readOnly : function( label, value ) {

			const field = Nino.adminUi.el( 'div', 'nino-admin-field' );

			field.appendChild( Nino.adminUi.el( 'span', '', label ) );
			field.appendChild( typeof value === 'string' ? Nino.adminUi.el( 'p', '', value ) : value );

			return field;
		},

		/**
		 *	Render one slot as a card: target dimensions, where it is used, the
		 *	call a template writes, the picture with Remove and the file field
		 *	that uploads at once, and the alt texts
		 *
		 *	@param		{Object}	slot
		 *
		 *	@return		{Element}								<fieldset>
		 */
		_renderSlotField : function( slot ) {

			const fieldset = dc.createElement('fieldset');
			fieldset.className = 'images-slot';
			const legend = dc.createElement('legend');
			legend.textContent = slot.label;
			fieldset.appendChild( legend );

			fieldset.appendChild( Nino.admin.images._readOnly( Nino.content.getText('/_admin/images/label/target'), slot.width+ ' × '+ slot.height+ ' px' ) );
			fieldset.appendChild( Nino.admin.images._renderUsage( slot ) );

			// The actual shortcode usage for this slot, copy/paste-ready for templating.
			// Deliberately built from two string pieces, not one "[image " literal -
			// this whole bundle is itself re-run through Html::renderHtml() when the
			// admin's own assets get cached (see Assets::_createCachefile()), and a
			// literal "[image ...]"-shaped string is indistinguishable there from a
			// real shortcode call, silently resolving to '' since no such slot exists
			fieldset.appendChild( Nino.admin.images._readOnly( Nino.content.getText('/_admin/images/label/call'), Nino.adminUi.el( 'code', '', '[' + 'image ' + slot.uri + ']' ) ) );

			const preview = dc.createElement('img');
			preview.className = 'nino-admin-field-image-preview';
			preview.alt = Nino.admin.images._previewAlt( slot );
			preview.hidden = ! slot.url;
			if( slot.url )
				preview.src = slot.url;

			// What the server will take stays in view, since nobody should have to fail an upload to learn the limit
			const limits = Nino.adminUi.uploadHint();

			const file = Nino.adminUi.fileField( {
				label		: Nino.content.getText('/_admin/images/label/image'),
				help		: Nino.content.getText('/_admin/images/hint/image'),
				note		: limits === null ? undefined : limits.textContent,
				accept	: '.jpg,.jpeg,.png,.webp,.gif',
				maxBytes : Nino.adminUi.limits().bytes,
				onChange : function( chosen ) {
					if( chosen !== null )
						Nino.admin.images._uploadImage( slot, chosen, parts );
				},
			} );

			// The slot stays and its image goes: only there is one to take away
			const removeBtn = Nino.adminUi.button( Nino.content.getText('/_admin/images/label/remove'), function() {
				Nino.admin.images._removeImage( slot, parts );
			}, { kind : 'danger', small : true } );
			removeBtn.hidden = ! slot.url;

			const shown = Nino.adminUi.el( 'div', 'images-slot-image' );
			shown.appendChild( preview );
			shown.appendChild( removeBtn );

			// A picture below the target size is saved, and that stays said at the field: the sentence and its place are the upload's
			const warning = Nino.adminUi.notice('');
			warning.hidden = true;

			file.el.insertBefore( shown, file.el.children[1] );
			file.el.appendChild( warning );

			const parts = { file : file, preview : preview, remove : removeBtn, warning : warning, fieldset : fieldset };

			fieldset.appendChild( file.el );
			fieldset.appendChild( Nino.admin.images._renderAlt( slot, preview ) );

			return fieldset;
		},

		/**
		 *	What the preview of a slot says to a screen reader: the alt text the
		 *	slot has in the first language that has one - the words its page
		 *	carries - or nothing at all, the name of the slot stands over it and a
		 *	picture that repeated that would be read twice
		 *
		 *	@param		{Object}	slot
		 *
		 *	@return		{string}
		 */
		_previewAlt : function( slot ) {

			const locale = Nino.admin.images._locales.find( function( code ) { return ( slot.alt && slot.alt[code] ) ? true : false } );

			return locale === undefined ? '' : slot.alt[locale];
		},

		/**
		 *	The field that says where a slot is used - the pages whose
		 *	templates show it - or, as a notice, that none does: an image
		 *	uploaded to such a slot never appears on the website. The words
		 *	carry the warning, not only the colour
		 *
		 *	@param		{Object}	slot
		 *
		 *	@return		{Element}								The field
		 */
		_renderUsage : function( slot ) {

			const pages = ( slot.usage && slot.usage.pages ) || [];

			if( pages.length === 0 )
				return Nino.admin.images._readOnly( Nino.content.getText('/_admin/images/label/usedon'), Nino.adminUi.notice( Nino.content.getText('/_admin/images/msg/unused') ) );

			return Nino.admin.images._readOnly( Nino.content.getText('/_admin/images/label/usedon'), pages.map( function( page ) {
				return page.name === page.httpUri ? page.httpUri : page.name+ ' ('+ page.httpUri+ ')';
			} ).join(', ') );
		},

		/**
		 *	The alt text of a slot: one short value for each language of the site,
		 *	one row under the other, the working language first. The words of the
		 *	field say that empty means decorative. Each value is saved by itself
		 *	when its field is left and it differs from what the server confirmed
		 *	for that field (see _saveAlt())
		 *
		 *	Every field has one baseline: the value the server last confirmed for it,
		 *	at the load or in the answer to its own save - never the answer to another
		 *	field's save, which carries a map that may be older or newer. A field is
		 *	unsaved input as long as its value differs from it, while a request is on
		 *	its way and after one failed
		 *
		 *	@param		{Object}	slot
		 *	@param		{Element}	preview					The slot's <img>, whose alt follows what is saved here
		 *
		 *	@return		{Element}								The field
		 */
		_renderAlt : function( slot, preview ) {

			const rows = Nino.adminUi.localeRows( {
				key				: 'alt',
				label			: Nino.content.getText('/_admin/images/label/alt'),
				help			: Nino.content.getText('/_admin/images/hint/alt'),
				locales		: Nino.admin.images._locales,
				values		: slot.alt || {},
				maxLength	: 250,
			} );

			const base = {};
			Object.keys( rows.controls ).forEach( function( code ) { base[code] = ( slot.alt && slot.alt[code] ) || '' } );

			// base: the confirmed value; sent: the value of the last request, failed or not; seq: the number of the last request; saving, queued, waiting, discarded: the request that is out
			const editor = { slot : slot, preview : preview, rows : rows, base : base, sent : {}, seq : {}, saving : {}, queued : {}, waiting : {}, discarded : {} };
			Nino.admin.images._editors.push( editor );

			Object.keys( rows.controls ).forEach( function( code ) {

				rows.controls[code].addEventListener( 'blur', function() { Nino.admin.images._saveAlt( editor, code ) } );

				// What is typed makes the sentence of that language stale, and is input the shell may have to ask about
				rows.controls[code].addEventListener( 'input', function() {
					Nino.adminUi.clearFieldErrors( rows.controls[code].parentNode );
					Nino.admin.images._refreshDirty();
				} );
			} );

			return rows.el;
		},

		/**
		 *	The languages of one slot whose field holds a value other than the baseline
		 *
		 *	@param		{Object}		editor				One entry of _editors
		 *
		 *	@return		{Array<string>}
		 */
		_changedAlt : function( editor ) {
			return Object.keys( editor.rows.controls ).filter( function( code ) { return editor.rows.controls[code].value !== editor.base[code] } );
		},

		/**
		 *	Save the alt text of one language of one slot, if it differs from the baseline: one request for that
		 *	language alone (an empty text removes its entry, see \Nino\Images::setSlotAlt()), a toast when it is
		 *	stored, and when it is not the sentence at the field with the value left in it - no focus is taken,
		 *	and leaving the field sends nothing more: the value is sent again when it was changed, or by the Save
		 *	of the shell (force). The answer sets the baseline of this language and nothing else: what it says of
		 *	the others is not what their fields showed. A text changed while the request is out is sent when it is
		 *	answered, and so is a Save that asked meanwhile
		 *
		 *	Every way this ends reports to done( ok ), if there is one: the shell's question about unsaved input
		 *	saves through it and goes on only when it hears true (see Nino.admin.dirty.guard())
		 *
		 *	@param		{Object}		editor				One entry of _editors
		 *	@param		{string}		code					The language
		 *	@param		{Function}	[done]				Called once with true when the text is stored (or was), false otherwise
		 *	@param		{boolean}		[force]				Send a value that was sent and refused, too
		 *
		 *	@return		void
		 */
		_saveAlt : function( editor, code, done, force ) {

			const report = function( ok ) {
				Nino.admin.images._refreshDirty();
				if( typeof done === 'function' )
					done( ok );
			};

			const control = editor.rows.controls[code];

			// A save while one runs: it is sent when that one is answered, and the caller hears of that
			if( editor.saving[code] === true ) {
				if( control.value !== editor.sent[code] || force === true )
					editor.queued[code] = true;
				if( typeof done === 'function' )
					( editor.waiting[code] = editor.waiting[code] ?? [] ).push( done );
				return;
			}

			if( control.value === editor.base[code] ) {
				report( true );
				return;
			}

			// Leaving a field whose value was sent and refused is no reason to send it again
			if( force !== true && control.value === editor.sent[code] ) {
				report( false );
				return;
			}

			const sent = control.value;
			const mine = ( editor.seq[code] ?? 0 ) + 1;

			editor.saving[code] = true;
			editor.sent[code] = sent;
			editor.seq[code] = mine;
			editor.queued[code] = false;

			Nino.admin.images._apiCall( 'alt', { uri : editor.slot.uri, alt : { [ code ] : sent } }, function( status, response ) {

				// An answer that is not to the last request of this field is no news
				if( mine !== editor.seq[code] )
					return;

				editor.saving[code] = false;

				// Whoever asked for a save while this one was out hears how it ended
				const waiting = editor.waiting[code] ?? [];
				const finish = function( ok ) {
					report( ok );
					waiting.forEach( function( wait ) { wait( ok ) } );
				};

				editor.waiting[code] = [];

				if( status !== 200 || response === null ) {
					editor.queued[code] = false;
					editor.discarded[code] = false;
					editor.rows.error( code, Nino.adminUi.api.errorText( status, response, '/_admin/images/error/alt' ) );
					finish( false );
					return;
				}

				Nino.admin.touch( 'slots' );

				const stored = ( response.alt && response.alt[code] ) || '';

				editor.base[code] = stored;
				editor.slot.alt = Object.assign( {}, editor.slot.alt );

				if( stored === '' )
					delete editor.slot.alt[code];
				else
					editor.slot.alt[code] = stored;

				editor.preview.alt = Nino.admin.images._previewAlt( editor.slot );

				if( control.value === sent || editor.discarded[code] === true )
					control.value = stored;

				editor.discarded[code] = false;
				Nino.adminUi.clearFieldErrors( control.parentNode );
				Nino.adminUi.say( Nino.adminUi.format( Nino.content.getText('/_admin/images/msg/alt-saved'), Nino.adminUi.localeName( code, editor.rows.locales ) ) );

				if( editor.queued[code] === true && control.value !== editor.base[code] ) {
					editor.waiting[code] = waiting;
					Nino.admin.images._saveAlt( editor, code, done, true );
					return;
				}

				finish( true );
			} );
		},

		/**
		 *	Whether the category holds alt texts nobody has stored - a value that differs from what the server
		 *	confirmed for its field, also while its request is on its way and after it failed, and also while
		 *	the list is shown: what the shell asks before it lets anything throw that away (see Nino.admin.dirty).
		 *	An upload or a removal commits on its own
		 *
		 *	@return		{boolean}
		 */
		isDirty : function() {
			return Nino.admin.images._editors.some( function( editor ) { return Nino.admin.images._changedAlt( editor ).length > 0 } );
		},

		/**
		 *	Save every alt text that differs from the baseline, one request after the other - the shell's Save in
		 *	its question about unsaved input. A request that is out is waited for, and what was typed since is
		 *	sent after it. Stops at the first that fails
		 *
		 *	@param		{Function}	done					Called once with true when everything was written, false otherwise
		 *
		 *	@return		void
		 */
		save : function( done ) {

			const open = [];

			Nino.admin.images._editors.forEach( function( editor ) {
				Nino.admin.images._changedAlt( editor ).forEach( function( code ) { open.push( [ editor, code ] ) } );
			} );

			let at = 0;

			const next = function() {

				if( at >= open.length ) {
					done( true );
					return;
				}

				const entry = open[at++];

				Nino.admin.images._saveAlt( entry[0], entry[1], function( ok ) {
					if( ok === true )
						next();
					else
						done( false );
				}, true );
			};

			next();
		},

		/**
		 *	Throw the input away: every field goes back to its baseline and loses its sentence. A language whose
		 *	request is out is put back as well, and again by its answer, which sets the baseline it confirms
		 *
		 *	@return		void
		 */
		discard : function() {

			Nino.admin.images._editors.forEach( function( editor ) {

				Object.keys( editor.rows.controls ).forEach( function( code ) {
					editor.rows.controls[code].value = editor.base[code];

					if( editor.saving[code] === true )
						editor.discarded[code] = true;
				} );

				editor.rows.clear();
			} );

			Nino.admin.images._refreshDirty();
		},

		/**
		 *	Ask the shell about unsaved alt texts before a way out that stays in this panel - the back link - and
		 *	go on when it says so
		 *
		 *	@param		{Function}	proceed
		 *
		 *	@return		void
		 */
		_guarded : function( proceed ) {
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.guard( [ 'images' ], proceed );
			else
				proceed();
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
		 *	@param		{Object}	parts						The slot's parts: { file, preview, remove, warning }
		 *
		 *	@return		void
		 */
		_removeImage : function( slot, parts ) {

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/images/confirm/remove-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/images/confirm/remove'), slot.label ),
				yes				: Nino.content.getText('/_admin/images/confirm/remove-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: parts.remove,
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					parts.remove.disabled = true;
					parts.file.clearError();

					Nino.admin.images._apiCall( 'remove', { uri : slot.uri }, function( status, response ) {

						parts.remove.disabled = false;

						if( status !== 200 || response === null ) {
							parts.file.error( Nino.adminUi.api.errorText( status, response, '/_admin/images/error/remove' ) );
							return;
						}

						Nino.admin.touch( 'slots' );

						slot.url = null;
						parts.preview.hidden = true;
						parts.remove.hidden = true;
						parts.warning.hidden = true;
						Nino.adminUi.say( Nino.content.getText('/_admin/images/msg/removed') );
					} );
				},
			} );
		},

		/**
		 *	Upload a new image for one slot, immediately - the server commits
		 *	it straight away and deletes the previous file. A file the server
		 *	cannot take is refused here, with the reason, before it is sent;
		 *	what the server refuses is said at the field. The field is emptied
		 *	again once the answer is in: what it holds is the slot's image, which
		 *	the preview shows
		 *
		 *	@param		{Object}	slot
		 *	@param		{File}		file
		 *	@param		{Object}	parts						The slot's parts: { file, preview, remove, warning, fieldset }
		 *
		 *	@return		void
		 */
		_uploadImage : function( slot, file, parts ) {

			parts.file.button.disabled = true;
			parts.fieldset.setAttribute( 'aria-busy', 'true' );

			const finish = function() {
				parts.file.button.disabled = false;
				parts.fieldset.removeAttribute('aria-busy');
				parts.file.clear();
			};

			Nino.adminUi.checkImage( file, function( rejection ) {

				if( rejection !== null ) {
					finish();
					parts.file.error( Nino.adminUi.api.errorText( 400, rejection ) );
					return;
				}

				Nino.admin.images._apiCall( 'upload', { uri : slot.uri }, function( status, response ) {

					finish();

					if( status !== 200 || response === null ) {
						parts.file.error( Nino.adminUi.api.errorText( status, response, '/_admin/images/error/save' ) );
						return;
					}

					Nino.admin.touch( 'slots' );

					slot.url = response.url;

					// The stored name is deterministic per slot, so replacing a
					// picture answers the url the browser already has in its cache
					// - and public/images/ is served statically, with no
					// Cache-Control of its own. The panel said "saved" while the
					// preview still showed the old picture until a hard reload.
					// The stamp is on the <img> only; the url the panel keeps and
					// the page later renders stays the clean one
					parts.preview.src = response.url + ( response.url.indexOf('?') === -1 ? '?' : '&' ) + 't=' + Date.now();
					parts.preview.hidden = false;
					parts.remove.hidden = false;

					// Saved either way - but a picture below the target size was
					// scaled up, and that stays said where it can be seen: in words
					// as well as in colour
					parts.warning.hidden = ! ( response.belowTarget === true && response.source );

					if( parts.warning.hidden === false )
						parts.warning.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/images/msg/below-target'), response.source.width+ ' × '+ response.source.height+ ' px' );

					Nino.adminUi.say( Nino.content.getText('/_admin/images/msg/saved') );
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
