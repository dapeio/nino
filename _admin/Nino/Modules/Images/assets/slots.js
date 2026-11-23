

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	slots.js								"Image Slots" tab of the Images panel: create/edit image
 *													slot definitions (label/width/height) - the "set" half of
 *													what the Images panel edits ("values" half: which file
 *													currently fills a slot). Same split as types.js and the
 *													Elements panel. Never touches a slot's filename. A row
 *													also says when no page shows its slot. Every view has its
 *													address: #images/slots, #images/slots/@new,
 *													#images/slots/@scan and #images/slots/<the slot's uri,
 *													one part>. The form is the
 *													guide's first model - a bar with Delete at its left, the state
 *													and one main button - and stays after a save; what is wrong is
 *													said at the field, and what went well is a toast.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.slots = {

		_slots 		: [],
		// The slot on the form, null while a new one is being made
		_current	: null,
		_isNew 		: false,
		// The rows of the scan form while it is the one on screen, else null -
		// what the shell's Save tells the two forms apart by
		_scanRows : null,
		// The scan is the view on screen, with its rows or still waiting for them
		_scanning : false,
		// The controls the form's save reads, as _renderForm() made them
		_parts		: null,
		// The status line of the form that is open - see Nino.adminUi.status()
		_line			: null,
		_loading	: false,
		_ready 		: false,
		// The row or the button that opened the form that is open: Back returns
		// the focus to it (see Nino.adminUi.backTo())
		_opener 	: null,

		/**
		 *	Load every image slot, render the list and open whatever the hash points at
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('slots-list') === null || Nino.admin.slots._loading === true || Nino.admin.slots._ready === true )
				return;

			Nino.admin.slots._loading = true;

			Nino.admin.slots._reload( function( ok ) {

				Nino.admin.slots._loading = false;

				if( ok === false )
					return;

				Nino.admin.slots._ready = true;

				// Captured only now: the hash is the one the page was loaded with
				const hash = Nino.admin.router.current();

				if( hash.panel === 'slots' && hash.parts.length > 0 ) {

					if( Nino.admin.router.isWord( hash.parts, 0, 'new' ) === true && hash.parts.length === 1 )
						return Nino.admin.slots._openSlot( null );

					if( Nino.admin.router.isWord( hash.parts, 0, 'scan' ) === true && hash.parts.length === 1 )
						return Nino.admin.slots._openScan();

					const slot = Nino.admin.slots._slotAt( hash.parts );

					if( slot !== null )
						return Nino.admin.slots._openSlot( slot.uri );
				}

				Nino.admin.slots._showList();
			} );
		},

		/**
		 *	Ask for the slots and draw the list - behind the form that is open, which stays: the list under it is the one Back
		 *	returns to. The Images panel of this same module lists the slots there are pictures for, and loads them once. The
		 *	slots just changed - every create, save, delete and scan ends up here - so its copy is stale, and it says "no
		 *	image slots yet" out loud when it has none: a stale copy would not be an empty screen but a wrong sentence. Marking
		 *	it unloaded is enough, showCurrent() fetches again when the tab is next on
		 *
		 *	@param		{Function}	[then]		Called once with true when the list is drawn, false when it could not be had
		 *
		 *	@return		void
		 */
		_reload : function( then ) {

			Nino.admin.slots._apiCall( 'list', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.admin.slots._showError( dc.getElementById('slots-list'), status, response );
					if( typeof then === 'function' )
						then( false );
					return;
				}

				Nino.admin.slots._slots = response.slots;
				Nino.admin.slots._renderList();

				if( Nino.admin.images !== undefined )
					Nino.admin.images._ready = false;

				if( typeof then === 'function' )
					then( true );
			} );
		},

		/**
		 *	Re-apply whatever drill-down level this tab is currently on -
		 *	called when it is selected, so the hash gets synced to reality.
		 *
		 *	Where the hash names this tab, the hash wins: a step through the
		 *	browser's history changes the address and nothing else, so the level
		 *	it names is shown - and leaving a form that holds unsaved input asks
		 *	first, as its back link does. A hash that names another panel (a
		 *	click on the rail) leaves the level in memory as it is
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.slots._ready === false )
				return;

			const hash = Nino.admin.router.current();
			if( hash.panel === 'slots' && Nino.admin.slots._follow( hash.parts ) === true )
				return;

			Nino.admin.slots._showLevel();
		},

		/**
		 *	Show the level this tab is on, and write it into the address
		 *
		 *	@return		void
		 */
		_showLevel : function() {

			if( dc.getElementById('slots-form').classList.contains('admin-hidden') === false )
				return Nino.admin.slots._showForm();

			Nino.admin.slots._showList();
		},

		/**
		 *	Move to the level the hash names, if it is not the one on screen:
		 *	the new-slot form, the scan, a slot's form or the list. A slot that does
		 *	not exist is the list. Leaving a form with unsaved input asks first (see
		 *	Nino.admin.router.leave())
		 *
		 *	@param		{Array}		parts					The hash behind the tab's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const slots = Nino.admin.slots;
			const open = dc.getElementById('slots-form').classList.contains('admin-hidden') === false;
			const scanning = slots._scanRows !== null || slots._scanning === true;
			const create = parts.length === 1 && Nino.admin.router.isWord( parts, 0, 'new' ) === true;
			const scan = parts.length === 1 && Nino.admin.router.isWord( parts, 0, 'scan' ) === true;
			const slot = create === true || scan === true || parts.length === 0 ? null : slots._slotAt( parts );

			let there;

			if( create === true )
				there = open === true && scanning === false && slots._isNew === true;
			else if( scan === true )
				there = open === true && scanning === true;
			else if( slot === null )
				there = open === false;
			else
				there = open === true && scanning === false && slots._isNew === false && slots._current !== null && slots._current.uri === slot.uri;

			if( there === true )
				return false;

			Nino.admin.router.leave( [ 'slots' ], open, function() {
				if( create === true )
					slots._openSlot( null );
				else if( scan === true )
					slots._openScan();
				else if( slot === null )
					slots._showList();
				else
					slots._openSlot( slot.uri );
			}, slots._showLevel );

			return true;
		},

		/**
		 *	The slot an address names, or null: its uri is the one part, whatever it holds
		 *
		 *	@param		{Array}		parts					The hash behind the tab's name
		 *
		 *	@return		{Object|null}
		 */
		_slotAt : function( parts ) {
			return parts.length !== 1 ? null : Nino.admin.slots._slots.find( function( slot ) { return slot.uri === parts[0] } ) ?? null;
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
		 *	Show the list again as it stands: the focus stays where it is, unless it
		 *	stood in the level that is hidden now (see Nino.adminUi.showLevel()). Back
		 *	goes to the row that opened the form, and what a person opens takes the
		 *	focus in
		 *
		 *	@param		{Element|null}	[opener]	Back: the control that opened the form that is left
		 *
		 *	@return		void
		 */
		_showList : function( opener ) {
			Nino.admin.router.set( 'slots', [] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'slots-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'slots-list', opener );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {

			const slots = Nino.admin.slots;
			let parts = [ Nino.admin.router.word('new') ];

			if( slots._scanRows !== null || slots._scanning === true )
				parts = [ Nino.admin.router.word('scan') ];
			else if( slots._isNew === false && slots._current !== null )
				parts = [ slots._current.uri ];

			Nino.admin.router.set( 'slots', parts );

			// A new slot is named first: the field is where the person starts, not the heading over the form
			Nino.adminUi.showLevel( 'slots-form', opened !== true ? { focus : false } : ( slots._isNew === true && slots._parts !== null && slots._scanning === false ? { focus : slots._parts.uriInput } : {} ) );
		},

		/**
		 *	The row of a slot as the list is drawn now: the list is drawn again behind
		 *	an open form after a save, and the row that was pressed is gone with the
		 *	old drawing
		 *
		 *	@param		{string}	uri
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( uri ) {

			const row = Array.from( dc.getElementById('slots-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === uri } );

			return row === undefined ? null : row.querySelector('a');
		},

		/**
		 *	The control Back returns the focus to from the form that is open: the row
		 *	of the slot, or the button that opened the form
		 *
		 *	@return		{Element|null}
		 */
		_backTarget : function() {

			const slots = Nino.admin.slots;

			return slots._isNew === false && slots._current !== null && slots._scanRows === null && slots._scanning !== true ? slots._rowOf( slots._current.uri ) : slots._opener;
		},

		/**
		 *	Draw the way back of a form: one link, "Back to <the tab>", which leaves the form for the list
		 *
		 *	@param		{string}		title			The name of the view
		 *
		 *	@return		{Element}									The bar
		 */
		_contextBar : function( title ) {
			return Nino.adminUi.contextBar( {
				back	: { to : Nino.content.getText('/_admin/nav/slots'), href : Nino.admin.router.href( 'slots', [] ), onClick : function() {
					Nino.admin.router.go( 'slots', [] );
					Nino.admin.slots._showList( Nino.admin.slots._backTarget() );
				} },
				title	: title,
			} );
		},

		/**
		 *	Render the slot list: a row for each slot - its label over its uri and size - and the list's own bar,
		 *	"New image slot" as the main button and the scan beside it
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('slots-list');
			wrap.innerHTML = '';

			if( Nino.admin.slots._slots.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/slots/empty') ) );
			else
				// A row opens a slot and writes its address: a link, with that address
				wrap.appendChild( Nino.adminUi.list( Nino.admin.slots._slots.map( function( slot ) {

					const unused = ( slot.usage || { pages : [] } ).pages.length === 0;

					return {
						key			: slot.uri,
						title		: slot.label,
						detail	: slot.uri+ ' · '+ slot.width+ ' × '+ slot.height+ ' px'+ ( unused === true ? Nino.admin.slots._templatesLabel( slot ) : '' ),
						badge		: unused === true ? { text : Nino.content.getText('/_admin/slots/label/unused'), state : 'warn' } : ( slot.hasImage ? undefined : { text : Nino.content.getText('/_admin/slots/label/noimage'), state : 'muted' } ),
						href		: Nino.admin.router.href( 'slots', [ slot.uri ] ),
						onOpen	: function() { Nino.admin.router.go( 'slots', [ slot.uri ] ); Nino.admin.slots._openSlot( slot.uri, true ) },
					};
				} ), { label : Nino.content.getText('/_admin/nav/slots') } ) );

			const add = Nino.adminUi.button( Nino.content.getText('/_admin/slots/label/new'), function() {
				Nino.admin.router.go( 'slots', [ Nino.admin.router.word('new') ] );
				Nino.admin.slots._opener = add;
				Nino.admin.slots._openSlot( null, true );
			} );

			const scan = Nino.adminUi.button( Nino.content.getText('/_admin/slots/label/scan'), function() {
				Nino.admin.router.go( 'slots', [ Nino.admin.router.word('scan') ] );
				Nino.admin.slots._opener = scan;
				Nino.admin.slots._openScan( true );
			}, { kind : 'secondary' } );

			wrap.appendChild( Nino.adminUi.actionBar( { list : true, secondary : [ scan ], primary : add } ).el );
		},

		/**
		 *	What the list row says about the pages that do not show a slot: the templates that name it, if any, since a slot
		 *	used in a template no route renders is not the same thing as one no template mentions
		 *
		 *	@param		{Object}		slot					{ usage : { templates, pages } }
		 *
		 *	@return		{string}									'' or ' · Templates: a, b'
		 */
		_templatesLabel : function( slot ) {

			const templates = ( slot.usage || { templates : [] } ).templates;

			return templates.length === 0 ? '' : ' · '+ Nino.adminUi.format( Nino.content.getText('/_admin/slots/label/templates'), templates.join(', ') );
		},

		/**
		 *	Open the form of one slot, or an empty one
		 *
		 *	@param		{string|null}	uri
		 *	@param		{boolean}			[opened]	The person opened it - a row, a button, or the slot that was just made: the focus goes into the form. Otherwise it is shown as it stands, by the address or after a step through the history
		 *
		 *	@return		void
		 */
		_openSlot : function( uri, opened ) {

			const slots = Nino.admin.slots;

			slots._isNew = uri === null;
			slots._current = uri === null ? null : ( slots._slots.find( function( slot ) { return slot.uri === uri } ) ?? null );
			slots._scanning = false;
			slots._renderForm();
			slots._showForm( opened === true );
		},

		/**
		 *	Render the form: the uri (a new slot's own; fixed once the slot exists - it is what the templates write), the
		 *	label, the width and the height, and the bar
		 *
		 *	@return		void
		 */
		_renderForm : function() {

			const slots = Nino.admin.slots;
			const slot = slots._current;
			const text = function( key ) { return Nino.content.getText( key ) };

			const wrap = dc.getElementById('slots-form');
			wrap.innerHTML = '';
			wrap.appendChild( slots._contextBar( slot === null ? text('/_admin/slots/label/new') : slot.label ) );

			const form = dc.createElement('form');
			form.id = 'slots-edit-form';
			form.noValidate = true;

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = slot === null ? text('/_admin/slots/label/new') : slot.label;
			fieldset.appendChild( legend );

			const uriField = Nino.adminUi.textField( {
				id					: 'slots-form-uri',
				label				: text('/_admin/slots/label/uri'),
				help				: text('/_admin/slots/hint/uri'),
				placeholder	: '/template/page-home/hero/image',
				value				: slot === null ? '' : slot.uri,
				required		: true,
				autocomplete: 'off',
			} );
			const uriInput = uriField.querySelector('input');
			uriInput.spellcheck = false;
			uriInput.disabled = slot !== null;
			fieldset.appendChild( uriField );

			const labelField = Nino.adminUi.textField( {
				id					: 'slots-form-label',
				label				: text('/_admin/common/label/label'),
				help				: text('/_admin/slots/hint/label'),
				value				: slot === null ? '' : slot.label,
				required		: true,
			} );
			const labelInput = labelField.querySelector('input');
			fieldset.appendChild( labelField );

			const size = function( id, label, value ) {
				const field = Nino.adminUi.textField( { id : id, label : label, help : text('/_admin/slots/hint/size'), value : value === undefined ? '' : String( value ), required : true, type : 'number' } );
				const input = field.querySelector('input');
				input.min = '1';
				input.step = '1';
				return field;
			};

			const widthField = size( 'slots-form-width', text('/_admin/slots/label/width'), slot === null ? undefined : slot.width );
			const heightField = size( 'slots-form-height', text('/_admin/slots/label/height'), slot === null ? undefined : slot.height );
			fieldset.appendChild( widthField );
			fieldset.appendChild( heightField );

			form.appendChild( fieldset );

			const deleteBtn = slot === null ? null : Nino.adminUi.button( text('/_admin/slots/label/delete'), function() { slots._delete() }, { kind : 'danger' } );

			const msg = dc.createElement('p');
			msg.id = 'slots-form-msg';
			const bar = Nino.adminUi.actionBar( {
				danger	: deleteBtn,
				status	: { line : msg, fresh : slot === null },
				primary	: Nino.adminUi.button( text( slot === null ? '/_admin/slots/label/create' : '/_admin/common/label/save' ), null, { type : 'submit' } ),
			} );

			slots._line = bar.line;
			bar.line.bind( form );
			form.appendChild( bar.el );

			slots._parts = {
				uriInput : uriInput, labelInput : labelInput,
				widthInput : widthField.querySelector('input'), heightInput : heightField.querySelector('input'),
			};
			slots._scanRows = null;

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); slots._save() } );
			wrap.appendChild( form );

			if( slot === null )
				uriInput.focus();

			// What the form holds now is what is saved
			slots._snapshot();
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
		 *	What is wrong with a field, as the sentence that says it - or '' where nothing is. A name is missing when it is
		 *	empty, and not valid when it is not what the field takes
		 *
		 *	@param		{string}		name					What the field is called
		 *	@param		{string}		value					What it holds, as typed
		 *	@param		{string}		[expect]			The fill key of what the field takes, where there is a rule
		 *	@param		{Function}	[valid]				Whether the value is what the field takes
		 *
		 *	@return		{string}
		 */
		_wrong : function( name, value, expect, valid ) {

			if( value.trim() === '' )
				return Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), name );

			if( typeof valid === 'function' && valid( value.trim() ) === false )
				return Nino.adminUi.format( Nino.content.getText('/_admin/slots/error/invalid'), name, Nino.content.getText( expect ) );

			return '';
		},

		/**
		 *	Whether a text is a slot's uri: what the server takes - words of lower case letters, digits, "-" and "_",
		 *	each behind a slash
		 *
		 *	@param		{string}		uri
		 *
		 *	@return		{boolean}
		 */
		_isUri : function( uri ) {
			return /^\/[a-z][a-z0-9_-]*(\/[a-z][a-z0-9_-]*)*$/.test( uri );
		},

		/**
		 *	Whether a text is a size a slot can be made with: a whole number of at least 1
		 *
		 *	@param		{string}		size
		 *
		 *	@return		{boolean}
		 */
		_isSize : function( size ) {
			return /^\d+$/.test( size ) === true && parseInt( size, 10 ) >= 1;
		},

		/**
		 *	Say what is wrong at the fields of the form that is open - the sentence under each, the line of the bar for
		 *	all of them - and take the focus to the first. A new slot's uri is also wrong where a slot holds it already
		 *
		 *	@param		{Object}		line					The status line of the form's bar
		 *
		 *	@return		{boolean}									Whether every field is fine
		 */
		_check : function( line ) {

			const slots = Nino.admin.slots;
			const parts = slots._parts;
			const text = function( key ) { return Nino.content.getText( key ) };

			Nino.adminUi.clearFieldErrors( dc.getElementById('slots-edit-form') );

			let first = null;

			[
				[ parts.uriInput, '/_admin/slots/label/uri', '/_admin/slots/expect/uri', slots._isUri ],
				[ parts.labelInput, '/_admin/common/label/label', '', undefined ],
				[ parts.widthInput, '/_admin/slots/label/width', '/_admin/slots/expect/size', slots._isSize ],
				[ parts.heightInput, '/_admin/slots/label/height', '/_admin/slots/expect/size', slots._isSize ],
			].forEach( function( rule ) {

				const input = rule[0];

				// The uri of a slot that exists is no field to fill
				if( input.disabled === true )
					return;

				const name = text( rule[1] );
				let sentence = slots._wrong( name, input.value, rule[2], rule[3] );

				if( sentence === '' && input === parts.uriInput && slots._slots.some( function( slot ) { return slot.uri === input.value.trim() } ) === true )
					sentence = Nino.adminUi.format( text('/_admin/slots/error/taken'), name );

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( input.closest('label'), input, 'slots-error-'+ input.id, sentence );

				if( first === null )
					first = input;
			} );

			if( first === null )
				return true;

			line.fail( text('/_admin/slots/error/fields') );
			first.focus();

			return false;
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

			const slots = Nino.admin.slots;
			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			if( slots._check( slots._line ) === false ) {
				report( false );
				return;
			}

			const parts = slots._parts;
			const line = slots._line;
			const creating = slots._isNew === true;
			const uri = creating === true ? parts.uriInput.value.trim() : slots._current.uri;

			line.saving();

			slots._apiCall( creating === true ? 'create' : 'save', { uri : uri, label : parts.labelInput.value.trim(), width : parts.widthInput.value.trim(), height : parts.heightInput.value.trim() }, function( status, response ) {

				if( status !== 200 || response === null ) {
					slots._refused( line, status, response );
					report( false );
					return;
				}

				Nino.admin.touch( 'slots' );

				// The list under the form is drawn again; the form stays, and a slot that was just made is the one on it, with its address
				slots._reload( function( ok ) {

					// The slot is written: only the list failed, and a Save that is reported as failed would be tried again
					if( ok === false ) {
						slots._showList();
						report( true );
						return;
					}

					if( creating === true ) {
						slots._openSlot( uri, true );
						slots._line.saved();
					}
					else {
						slots._current = slots._slots.find( function( slot ) { return slot.uri === uri } ) ?? slots._current;
						slots._snapshot();
						line.saved();
					}

					report( true );
				} );
			} );
		},

		/**
		 *	Say what the server refused: in the line of the bar and, for the refusals that are about a field, under that
		 *	field - the size it names, the uri that is taken
		 *
		 *	@param		{Object}		line					The status line of the form's bar
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_refused : function( line, status, response ) {

			const slots = Nino.admin.slots;
			const parts = slots._parts;
			const field = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';
			let input = null;

			if( field === 'width' )
				input = parts.widthInput;
			else if( field === 'height' )
				input = parts.heightInput;
			else if( status === 409 )
				input = parts.uriInput;

			Nino.adminUi.clearFieldErrors( dc.getElementById('slots-edit-form') );
			line.error( status, response, '/_admin/common/error/save' );

			if( input === null )
				return;

			Nino.adminUi.fieldError( input.closest('label'), input, 'slots-error-'+ input.id, status === 409 ? Nino.adminUi.format( Nino.content.getText('/_admin/slots/error/taken'), Nino.content.getText('/_admin/slots/label/uri') ) : Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
			input.focus();
		},

		/**
		 *	Delete the slot currently open, after confirmation - its
		 *	uploaded file (if any) goes with it - and return to the list
		 *
		 *	@return		void
		 */
		_delete : function() {

			const slots = Nino.admin.slots;
			const slot = slots._current;

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/slots/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/slots/confirm/delete'), slot.label ),
				yes				: Nino.content.getText('/_admin/slots/confirm/delete-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					slots._apiCall( 'delete', { uri : slot.uri }, function( status, response ) {

						if( status !== 200 || response === null ) {
							slots._line.error( status, response, '/_admin/common/error/delete' );
							return;
						}

						Nino.admin.touch( 'slots' );

						slots._reload( function() {
							slots._current = null;
							slots._showList();
							Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/deleted') );
						} );
					} );
				},
			} );
		},

		/**
		 *	Open the "hardcoded <img> tags found in templates, not backed by
		 *	any slot - and slots a template names that nobody has made" scan
		 *	results form - see Slots::apiScan()
		 *
		 *	@param		{boolean}		[opened]	The person pressed the button: the focus goes into the view
		 *
		 *	@return		void
		 */
		_openScan : function( opened ) {

			const slots = Nino.admin.slots;

			slots._isNew = false;
			slots._current = null;
			slots._scanRows = null;
			slots._scanning = true;

			const wrap = dc.getElementById('slots-form');
			wrap.innerHTML = '';
			wrap.appendChild( slots._contextBar( Nino.content.getText('/_admin/slots/label/scan') ) );

			const waiting = Nino.adminUi.el( 'p', '', Nino.content.getText('/_admin/common/msg/scanning') );
			waiting.setAttribute( 'aria-busy', 'true' );
			wrap.appendChild( waiting );

			slots._snapshot();
			slots._showForm( opened === true );

			slots._apiCall( 'scan', {}, function( status, response ) {

				// The view was left while the scan ran: its answer has nowhere to go
				if( waiting.isConnected === false )
					return;

				if( status !== 200 || response === null ) {
					waiting.removeAttribute('aria-busy');
					waiting.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/scan' );
					return;
				}

				slots._renderScanForm( response.missing );
			} );
		},

		/**
		 *	Render the scan results: one card per <img src="/images/..."> not
		 *	backed by a slot, and one per slot a component names that is not
		 *	defined (its uri is the src, and no size is known) - editable
		 *	uri/label/width/height (pre-filled from the tag's own attributes,
		 *	or the actual file's real pixel size if the tag had none) + an
		 *	"Ignore" tick. Submitting
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

			const slots = Nino.admin.slots;
			const wrap = dc.getElementById('slots-form');
			const text = function( key ) { return Nino.content.getText( key ) };

			// The view that stood while the scan ran may hold the focus, on its back link: the one that replaces it takes it over
			const held = ( dc.activeElement ?? null ) !== null && dc.activeElement.closest('#slots-form') !== null;
			wrap.innerHTML = '';
			slots._scanning = true;

			const back = slots._contextBar( text('/_admin/slots/label/scan') );
			wrap.appendChild( back );

			if( held === true )
				back.querySelector('a').focus();

			if( missing.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( text('/_admin/slots/scan/none') ) );
				slots._scanRows = null;
				slots._snapshot();
				return;
			}

			const form = dc.createElement('form');
			form.id = 'slots-scan-form';
			const rows = [];

			// _saveScanResults() says what is missing, at the field, and the shell's Save
			// goes there too: the browser's own bubble would be a second voice
			form.noValidate = true;

			missing.forEach( function( item, index ) {

				const fieldset = dc.createElement('fieldset');
				fieldset.className = 'slots-scan-row';
				const legend = dc.createElement('legend');
				legend.textContent = item.src;
				fieldset.appendChild( legend );

				const where = Nino.adminUi.el( 'div', 'nino-admin-field' );
				fieldset.appendChild( where );
				where.appendChild( Nino.adminUi.el( 'span', '', text('/_admin/slots/label/files') ) );
				where.appendChild( Nino.adminUi.el( 'p', '', item.files.join(', ') ) );

				const field = function( id, label, value, type, required ) {
					const made = Nino.adminUi.textField( { id : id, label : label, value : value, type : type, required : required } );
					fieldset.appendChild( made );
					return made;
				};

				const uriField = field( 'slots-scan-uri-'+ index, text('/_admin/slots/label/uri'), item.suggestedUri, 'text', true );
				const labelField = field( 'slots-scan-label-'+ index, text('/_admin/common/label/label'), item.suggestedUri.replace(/^\//, '').replace(/-/g, ' '), 'text', true );
				const widthField = field( 'slots-scan-width-'+ index, text('/_admin/slots/label/width'), item.width ? String( item.width ) : '', 'number', true );
				const heightField = field( 'slots-scan-height-'+ index, text('/_admin/slots/label/height'), item.height ? String( item.height ) : '', 'number', true );

				const ignore = Nino.adminUi.checkField( { id : 'slots-scan-ignore-'+ index, label : text('/_admin/common/label/ignore') } );
				fieldset.appendChild( ignore );

				form.appendChild( fieldset );

				const row = {
					field : fieldset, uriField : uriField, labelField : labelField, widthField : widthField, heightField : heightField,
					uriInput : uriField.querySelector('input'), labelInput : labelField.querySelector('input'),
					widthInput : widthField.querySelector('input'), heightInput : heightField.querySelector('input'),
					ignoreCheck : ignore.fieldControl, index : index,
				};

				[ row.widthInput, row.heightInput ].forEach( function( input ) { input.min = '1'; input.step = '1' } );
				slots._markSize( row, slots._sized( row ) === false );
				rows.push( row );
			} );

			const msg = dc.createElement('p');
			msg.id = 'slots-scan-msg';
			const bar = Nino.adminUi.actionBar( {
				status	: { line : msg, fresh : true },
				primary	: Nino.adminUi.button( text('/_admin/slots/label/scan-create'), null, { type : 'submit' } ),
			} );

			slots._line = bar.line;
			bar.line.bind( form );
			form.appendChild( bar.el );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); slots._saveScanResults( rows ) } );
			wrap.appendChild( form );

			// The rows come pre-filled from the scan: that is the baseline
			slots._scanRows = rows;
			slots._snapshot();
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
		 *	while the row is ignored (a slot nobody makes needs no size). What was
		 *	said at a field goes with the next thing typed into it
		 *
		 *	@param		{Object}		row				A row of the scan form
		 *	@param		{boolean}		needed		The row came without a size
		 *
		 *	@return		void
		 */
		_markSize : function( row, needed ) {

			const fields = [ [ row.widthField, row.widthInput ], [ row.heightField, row.heightInput ] ];

			const follow = function() {

				const required = needed === true && row.ignoreCheck.checked === false;

				fields.forEach( function( pair ) {

					pair[1].required = required;

					if( required === true )
						pair[1].setAttribute( 'aria-required', 'true' );
					else
						pair[1].removeAttribute('aria-required');

					const star = pair[0].querySelector('.nino-admin-required');

					if( star !== null )
						star.hidden = required === false;
				} );
			};

			// What the block drew as a star where the row has a size already is no mark of this row
			fields.forEach( function( pair ) {

				const star = pair[0].querySelector('.nino-admin-required');

				if( needed === false && star !== null )
					star.remove();

			} );

			row.ignoreCheck.addEventListener( 'change', follow );
			follow();

			// What was said at a row is stale with the next thing typed into it
			[ row.uriInput, row.labelInput, row.widthInput, row.heightInput ].forEach( function( input ) {
				input.addEventListener( 'input', function() { Nino.adminUi.clearFieldErrors( row.field ) } );
			} );
		},

		/**
		 *	Say what is wrong with a row of the scan, at its fields: its uri that is no uri or is taken, its label, its size
		 *
		 *	@param		{Object}		row				A row of the scan form
		 *	@param		{Array}			taken			The uris that are made already
		 *
		 *	@return		{Element|null}					The first field that is wrong
		 */
		_checkRow : function( row, taken ) {

			const slots = Nino.admin.slots;
			const text = function( key ) { return Nino.content.getText( key ) };

			Nino.adminUi.clearFieldErrors( row.field );

			let first = null;

			[
				[ row.uriInput, row.uriField, '/_admin/slots/label/uri', '/_admin/slots/expect/uri', slots._isUri ],
				[ row.labelInput, row.labelField, '/_admin/common/label/label', '', undefined ],
				[ row.widthInput, row.widthField, '/_admin/slots/label/width', '/_admin/slots/expect/size', slots._isSize ],
				[ row.heightInput, row.heightField, '/_admin/slots/label/height', '/_admin/slots/expect/size', slots._isSize ],
			].forEach( function( rule ) {

				const name = text( rule[2] );
				let sentence = slots._wrong( name, rule[0].value, rule[3], rule[4] );

				if( sentence === '' && rule[0] === row.uriInput && taken.indexOf( rule[0].value.trim() ) !== -1 )
					sentence = Nino.adminUi.format( text('/_admin/slots/error/taken'), name );

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( rule[1], rule[0], 'slots-scan-error-'+ row.index+ '-'+ rule[0].id, sentence );

				if( first === null )
					first = rule[0];
			} );

			return first;
		},

		/**
		 *	Create every non-ignored row's slot (see _renderScanForm()), one
		 *	slots/create call at a time, so a per-row failure (eg. a
		 *	duplicate uri) is reported without swallowing the rest.
		 *
		 *	A row that is not right - no size, a uri that is no uri - is not sent: it is
		 *	refused at its fields, where the person can fix it, and the rows that are
		 *	right are made. The form stays for the ones that were refused, without the
		 *	ones that were made; a row the server refused stays with its sentence
		 *
		 *	@param		{Array}		rows
		 *	@param		{Function}	[done]			Called once with true when every row was created, false otherwise
		 *
		 *	@return		void
		 */
		_saveScanResults : function( rows, done ) {

			const slots = Nino.admin.slots;
			const text = function( key ) { return Nino.content.getText( key ) };
			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = slots._line;
			const ready = rows.filter( function( row ) { return row.ignoreCheck.checked === false } );

			if( ready.length === 0 ) {
				slots._showList();
				report( true );
				return;
			}

			const taken = slots._slots.map( function( slot ) { return slot.uri } );
			const pending = [];
			const refused = [];
			let first = null;

			ready.forEach( function( row ) {

				const wrong = slots._checkRow( row, taken );

				if( wrong === null )
					pending.push( row );
				else {
					refused.push( row );
					first = first ?? wrong;
				}
			} );

			if( refused.length > 0 ) {
				line.fail( text('/_admin/slots/error/fields') );
				first.focus();
			}

			if( pending.length === 0 ) {
				report( false );
				return;
			}

			let created = 0;
			const made = [];
			const failed = [];

			const next = function( i ) {

				if( i >= pending.length ) {

					// The slots that were made are gone from the form, and the list under it is drawn again for Back
					made.forEach( function( row ) {
						rows.splice( rows.indexOf( row ), 1 );
						row.field.remove();
					} );

					const left = refused.length + failed.length;

					slots._reload( function() {

						slots._snapshot();

						if( left === 0 ) {
							slots._showList();
							Nino.adminUi.say( Nino.adminUi.format( text('/_admin/slots/scan/created'), created ) );
						}
						else {
							line.fail( text('/_admin/slots/error/fields') );
							if( created > 0 )
								Nino.adminUi.say( Nino.adminUi.format( text('/_admin/slots/scan/created'), created ) );
						}

						report( left === 0 );
					} );

					return;
				}

				const row = pending[i];

				if( refused.length === 0 )
					line.idle( Nino.adminUi.format( text('/_admin/common/msg/creating'), row.uriInput.value, i + 1, pending.length ) );

				slots._apiCall( 'create', {
					uri 		: row.uriInput.value.trim(),
					label 	: row.labelInput.value.trim(),
					width 	: row.widthInput.value.trim(),
					height 	: row.heightInput.value.trim(),
				}, function( status, response ) {

					if( status === 200 ) {
						Nino.admin.touch( 'slots' );
						created++;
						made.push( row );
					}
					else {
						failed.push( row );
						Nino.adminUi.fieldError( row.uriField, row.uriInput, 'slots-scan-error-'+ row.index+ '-'+ row.uriInput.id, status === 409
							? Nino.adminUi.format( text('/_admin/slots/error/taken'), text('/_admin/slots/label/uri') )
							: Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
					}

					next( i + 1 );
				} );
			};

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
