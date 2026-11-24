

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	slots.js								"Image Slots" tab of the Images panel: create/edit image
 *													slot definitions (label/width/height) - the "set" half of
 *													what the Images panel edits ("values" half: which file
 *													currently fills a slot). Same split as types.js and the
 *													Elements panel. Never touches a slot's filename.
 *
 *													The tab is the standard directory of the workbench
 *													(Nino.adminUi.directory(): the name of a slot with its uri
 *													under it, the target size, and what one would otherwise see
 *													only after opening it - no page includes the slot, with the
 *													templates that name it, or it has no image -, the choice of
 *													what to show and the search) with the record of one slot
 *													over it (Nino.adminUi.record(), address #images/slots/<the
 *													slot's uri, ONE part>: %2Flogo). The record holds the uri to
 *													read, the label, the width and the height, is saved with
 *													Save (a record that was not changed sends nothing) and takes
 *													the slot away with "Delete image slot", after a question.
 *													A new slot is made in a dialog of its own (formDialog(), the
 *													address #images/slots/@new): there is no row to open for it.
 *													The scan of the templates (#images/slots/@scan) stays a view
 *													of its own in the level beside the directory, with its way
 *													back, its form and its bar. The words @new and @scan are
 *													answered here, before an address is handed to the record: a
 *													record reads one part as the key of an entry. Everything the
 *													server or a person wrote is shown as text.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	const word = function( key ) { return Nino.content.getText( key ) };

	Nino.admin.slots = {

		// The rows of the last list: one for each slot - what the table shows of it and what the record reads
		_rows					: [],
		_directory		: null,
		_record				: null,
		// What the person sees of the directory - the search, the choice, the sort, the page - kept while the panel lives
		_state				: {},
		_ready				: false,
		_loading			: false,
		// What the templates and the slots were when the list was read (see Nino.admin.touch()): the usage of a slot and its picture are in the rows, and a
		// template or a slot written since is not
		_stamp				: '',
		// The two buttons of the list's bar, which the views that they open give the focus back to
		_buttons			: { add : null, scan : null },
		// The dialog of a new slot is open - its handle
		_adding				: false,
		_dialog				: null,
		// The rows of the scan form while it is the one on screen, else null - what the shell's Save tells the scan from the record by
		_scanRows			: null,
		// The scan is the view on screen, with its rows or still waiting for them
		_scanning			: false,
		// The status line of the scan form
		_line					: null,

		/**
		 *	Load every image slot, draw the directory and open whatever the address points at
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('slots-list') === null || Nino.admin.slots._loading === true || Nino.admin.slots._ready === true )
				return;

			Nino.admin.slots._load( false );
		},

		/**
		 *	The tab is shown. An address of this tab is followed - the record opens, closes or steps, the dialog of a new slot and the scan open and close -, an address
		 *	of another panel leaves what is on screen as it is and writes its own address. The slots are read again, without losing what was chosen, where a template or a
		 *	slot was written since they were read (the Images panel writes the pictures)
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			const panel = Nino.admin.slots;

			if( panel._ready === false )
				return panel.init();

			const here = Nino.admin.router.current().panel === 'slots';

			// The record reads the address itself, and answers only one that is this tab's
			if( here === true && panel._follow() === true )
				return;

			// An address of another panel leaves the tab as it is, and writes the address of what is on screen
			if( here === false )
				panel._address();

			if( panel._record.isOpen() === true || panel._adding === true || panel._scanning === true )
				return;

			if( panel._stampNow() !== panel._stamp )
				panel._load( true );
		},

		/**
		 *	What the templates and the slots are: the two counters of the shell, as one value
		 *
		 *	@return		{string}
		 */
		_stampNow : function() {
			return Nino.admin.stamp( 'templates' )+ ':'+ Nino.admin.stamp( 'slots' );
		},

		/**
		 *	Tell the shell that a slot was written, and take it as read by this tab: what the tab wrote itself is on screen. The Images panel lists the slots there are
		 *	pictures for and says "no image slots yet" out loud when it has none: its copy is stale after every write here, and marking it unloaded is enough - its
		 *	showCurrent() reads again when it is next on
		 *
		 *	@return		void
		 */
		_wrote : function() {

			const panel = Nino.admin.slots;
			const same = panel._stampNow() === panel._stamp;

			Nino.admin.touch( 'slots' );

			if( same === true )
				panel._stamp = panel._stampNow();

			if( Nino.admin.images !== undefined )
				Nino.admin.images._ready = false;
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
		 *	Read the list. Drawn the first time with the sentence that it is loading; read again without it, the directory takes the new rows where it stands - its
		 *	search, its choice, its sort and its page are what a person chose
		 *
		 *	@param		{boolean}		quiet				A list that is drawn already is read again
		 *	@param		{Function}	[then]			Called with true once the list is drawn, with false where it could not be read
		 *
		 *	@return		void
		 */
		_load : function( quiet, then ) {

			const panel = Nino.admin.slots;
			const told = function( ok ) { if( typeof then === 'function' ) then( ok ) };

			if( panel._loading === true )
				return told( false );

			panel._loading = true;

			// A template written while the answer is on its way is not in it: the stamp is the one from before the request went out
			const stamp = panel._stampNow();

			if( quiet === false || panel._directory === null ) {
				panel._frame();
				panel._directory.loading( word('/_admin/slots/loading') );
			}

			panel._apiCall( 'list', {}, function( status, response ) {

				panel._loading = false;

				if( status !== 200 || response === null || Array.isArray( response.slots ) === false ) {
					panel._directory.fail( status, response, '/_admin/common/error/load' );
					return told( false );
				}

				panel._rows = response.slots.map( panel._row );
				panel._stamp = stamp;
				panel._directory.setRows( panel._rows, { keep : true } );
				panel._ready = true;

				// An address that names a slot, the new one or the scan is followed once the rows are in
				panel._follow();
				told( true );
			} );
		},

		/**
		 *	The room the list is drawn in: the directory, the record over it and, under the directory, the bar of the list - "New image slot" as the main button and the
		 *	scan beside it
		 *
		 *	@return		void
		 */
		_frame : function() {

			const panel = Nino.admin.slots;

			if( panel._directory !== null )
				return;

			const wrap = dc.getElementById('slots-list');

			wrap.innerHTML = '';

			const mount = Nino.adminUi.el( 'div' );

			mount.id = 'slots-directory';
			wrap.appendChild( mount );

			const add = Nino.adminUi.button( word('/_admin/slots/label/new'), function() { panel._openNew( add, true ) } );
			const scan = Nino.adminUi.button( word('/_admin/slots/label/scan'), function() { panel._openScan( true, true ) }, { kind : 'secondary' } );

			panel._buttons = { add : add, scan : scan };
			panel._directory = panel._build( mount, panel._state );
			panel._record = panel._makeRecord();
			panel._share();

			wrap.appendChild( Nino.adminUi.actionBar( { list : true, secondary : [ scan ], primary : add } ).el );
		},

		/**
		 *	The row of a slot as the list answered it: what the table shows of it and what the record reads
		 *
		 *	@param		{Object}		slot					{ uri, label, width, height, hasImage, usage : { templates, pages } }
		 *
		 *	@return		{Object}
		 */
		_row : function( slot ) {

			const usage = slot.usage !== null && typeof slot.usage === 'object' ? slot.usage : {};

			return Nino.admin.slots._derive( {
				key			: String( slot.uri ),
				uri			: String( slot.uri ),
				label		: String( slot.label ?? slot.uri ),
				width		: Number( slot.width ) || 0,
				height	: Number( slot.height ) || 0,
				hasImage	: slot.hasImage === true,
				templates	: ( Array.isArray( usage.templates ) === true ? usage.templates : [] ).map( String ),
				pages		: Array.isArray( usage.pages ) === true ? usage.pages.length : 0,
			} );
		},

		/**
		 *	What a row says that follows from the rest of it: its size, and the state a person would otherwise see only after opening the slot - that no page
		 *	includes it, or that it has no image
		 *
		 *	@param		{Object}		row
		 *
		 *	@return		{Object}									The row, with the derived values set
		 */
		_derive : function( row ) {

			row.size = row.width+ ' × '+ row.height+ ' px';
			row.unused = row.pages === 0;
			row.stateText = row.unused === true ? word('/_admin/slots/label/unused') : ( row.hasImage === true ? '' : word('/_admin/slots/label/noimage') );

			return row;
		},

		/**
		 *	The row of a key, or null
		 *
		 *	@param		{string}		key
		 *
		 *	@return		{Object|null}
		 */
		_find : function( key ) {
			return Nino.admin.slots._rows.find( function( row ) { return row.key === key } ) ?? null;
		},

		/**
		 *	Put a row in the place of the row of the same key, and have the directory draw it anew where it stands
		 *
		 *	@param		{Object}		next
		 *
		 *	@return		void
		 */
		_replace : function( next ) {

			const panel = Nino.admin.slots;

			panel._rows = panel._rows.map( function( row ) { return row.key === next.key ? next : row } );
			panel._directory.update( panel._rows, next.key );
		},

		/**
		 *	The directory: the choice of what to show - the slots without an image, those no page includes -, the search and the table with its paging. There is no
		 *	box to choose rows by: nothing here is done to several slots at once. The size is sorted as a string whose numbers are read as numbers (the width first,
		 *	then the height), as it is in the table of the Images panel
		 *
		 *	@param		{Element}		mount
		 *	@param		{Object}		[state]				What the person saw before
		 *
		 *	@return		{Object}
		 */
		_build : function( mount, state ) {

			const panel = Nino.admin.slots;

			return Nino.adminUi.directory( {
				mount : mount,
				rowKey : 'key',
				state : state,
				onState : function( now ) { panel._state = now },
				columns : [
					{ key : 'label', label : word('/_admin/images/col/name'), type : 'string', primary : true, render : panel._nameCell },
					{ key : 'size', label : word('/_admin/images/label/target'), type : 'string' },
					{ key : 'stateText', label : word('/_admin/slots/col/state'), type : 'string', render : panel._stateCell, absent : function( row ) { return row.stateText === '' } },
				],
				labels : { search : word('/_admin/slots/label/search'), empty : word('/_admin/slots/empty'), noMatch : word('/_admin/slots/nomatch'), loading : word('/_admin/slots/loading') },
				filters : [ {
					id : 'slots-show',
					label : word('/_admin/slots/label/show'),
					value : '',
					options : [
						{ value : '', label : word('/_admin/slots/label/show-all') },
						{ value : 'none', label : word('/_admin/slots/label/show-none') },
						{ value : 'unused', label : word('/_admin/slots/label/show-unused') },
					],
					match : function( row, value ) {
						return value === '' || ( value === 'none' ? row.hasImage === false : row.unused === true );
					},
				} ],
				// What a person looks for, whatever the cells show of it: the name, the uri and the templates that name the slot
				searchText : function( row ) {
					return [ row.label, row.uri ].concat( row.templates ).join(' ');
				},
				actions : function( row ) {
					return [ Nino.adminUi.iconButton( 'pencil', Nino.adminUi.format( word('/_admin/slots/row/open'), row.label ), function( event ) {
						panel._record.open( row.key, { opener : event.currentTarget } );
					}, { small : true } ) ];
				},
				// A row is opened by its first button as well: the row is not a stop of the Tab key where it holds buttons
				onOpen : function( row ) { panel._record.open( row.key, {} ) },
			} );
		},

		/**
		 *	The cell of the name: the label of the slot, strong, and its uri under it
		 *
		 *	@param		{string}		value
		 *	@param		{Object}		row
		 *
		 *	@return		{Element}
		 */
		_nameCell : function( value, row ) {

			const cell = Nino.adminUi.el( 'span', 'images-name' );
			const label = Nino.adminUi.el( 'strong', 'images-name-label', row.label );
			const key = Nino.adminUi.el( 'span', 'images-name-key', row.uri );

			// What an ellipsis cuts is whole in the tooltip
			label.title = row.label;
			key.title = row.uri;
			cell.appendChild( label );
			cell.appendChild( key );

			return cell;
		},

		/**
		 *	The cell of the state: a badge that warns where no page includes the slot, with the templates that name it where there are any - a slot used in a
		 *	template that no route renders is not the same thing as one no template mentions -; else a quiet badge where the slot has no image; else nothing
		 *
		 *	@param		{string}		value
		 *	@param		{Object}		row
		 *
		 *	@return		{Element|string}
		 */
		_stateCell : function( value, row ) {

			if( row.stateText === '' )
				return '';

			const cell = Nino.adminUi.el( 'span', 'slots-state' );
			const badge = Nino.adminUi.el( 'span', 'nino-admin-badge', row.stateText );

			badge.dataset.state = row.unused === true ? 'warn' : 'muted';
			cell.appendChild( badge );

			if( row.unused === true && row.templates.length > 0 ) {
				const names = Nino.adminUi.format( word('/_admin/slots/label/templates'), row.templates.join(', ') );
				const line = Nino.adminUi.el( 'span', 'slots-state-templates', names );

				line.title = names;
				cell.appendChild( line );
			}

			return cell;
		},

		/**
		 *	The record: a slot in a dialog of its own, with the step to the one before and the one after it in the rows the directory shows, "Save", "Save and continue"
		 *	and "Delete image slot" at the left of its bar
		 *
		 *	@return		{Object}
		 */
		_makeRecord : function() {

			const panel = Nino.admin.slots;

			return Nino.adminUi.record( {
				screen : 'slots',
				prefix : [],
				directory : panel._directory,
				keyOf : function( row ) { return row.key },
				find : panel._find,
				title : function( row ) { return row.label },
				subtitle : function( row ) { return row.uri },
				saveNext : true,
				labels : { prev : word('/_admin/slots/record/prev'), next : word('/_admin/slots/record/next') },
				body : panel._body,
				save : panel._save,
				danger : { label : word('/_admin/slots/label/delete'), run : panel._remove },
				missing : function() { panel._directory.notice( word('/_admin/slots/record/missing') ) },
			} );
		},

		/**
		 *	The record and the scan are two views of one tab, and the shell knows a tab by one name of unsaved input: the entry of the record, `slots`. The scan is a
		 *	watched form of its own - `slots-scan` - and the entry of the tab answers for whichever of the two is on screen, so that the question before a way out of
		 *	the panel, the back link of the scan and Ctrl+S reach the one that holds the input
		 *
		 *	@return		void
		 */
		_share : function() {

			const dirty = Nino.admin.dirty;

			if( typeof dirty !== 'object' || typeof dirty.entry !== 'function' )
				return;

			const own = dirty.entry('slots');
			const scan = dirty.entry('slots-scan');

			if( own === null || scan === null )
				return;

			const on = function() { return Nino.admin.slots._scanning === true };

			dirty.register( 'slots', {
				isDirty : function() { return on() === true ? scan.isDirty() : own.isDirty() },
				save : function( done ) { return on() === true ? scan.save( done ) : own.save( done ) },
				discard : function() { return on() === true ? scan.discard() : own.discard() },
				bar : function() { return on() === true ? scan.bar() : own.bar() },
			} );
		},

		/**
		 *	The content of the record: the label, the width and the height. The uri stands under the name in the head of the record - it is what the templates write and cannot be changed, so it is no field. What it keeps in ctx
		 *	- the fields - is what the save finds there
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *
		 *	@return		{Element}
		 */
		_body : function( row, ctx ) {

			const fields = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );

			const label = Nino.adminUi.textField( {
				id : 'slots-form-label',
				label : word('/_admin/common/label/label'),
				help : word('/_admin/slots/hint/label'),
				value : row.label,
				required : true,
			} );

			// The unit stands in the name of the field: a unit of the block would say it twice
			const size = function( key, name, value ) {
				return Nino.adminUi.numberField( { key : key, label : name, help : word('/_admin/slots/hint/size'), value : String( value ), min : '1', step : 1, required : true } );
			};

			const width = size( 'width', word('/_admin/slots/label/width'), row.width );
			const height = size( 'height', word('/_admin/slots/label/height'), row.height );

			[ label, width, height ].forEach( function( field ) { fields.appendChild( field ) } );

			ctx.parts = { label : label, width : width, height : height };

			return fields;
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
				return Nino.adminUi.format( word('/_admin/common/error/missing'), name );

			if( typeof valid === 'function' && valid( value.trim() ) === false )
				return Nino.adminUi.format( word('/_admin/slots/error/invalid'), name, Nino.content.getText( expect ) );

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
		 *	The save of the record: what was changed and nothing else - a record with no change sends nothing and says that it is saved. What is wrong with a field is
		 *	said at the field, before anything is sent, and what the server refuses stands at the field it names. The row is what was sent: the server answers nothing
		 *	but ok, so the list is not read again
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{Function}	done					done( ok, { row, error } )
		 *
		 *	@return		void
		 */
		_save : function( row, ctx, done ) {

			const panel = Nino.admin.slots;
			const current = panel._find( row.key ) ?? row;
			const parts = ctx.parts;
			const input = function( key ) { return parts[key].querySelector('input') };

			Nino.adminUi.clearFieldErrors( ctx.form );

			let first = null;

			[
				[ 'label', '/_admin/common/label/label', '', undefined ],
				[ 'width', '/_admin/slots/label/width', '/_admin/slots/expect/size', panel._isSize ],
				[ 'height', '/_admin/slots/label/height', '/_admin/slots/expect/size', panel._isSize ],
			].forEach( function( rule ) {

				const sentence = panel._wrong( word( rule[1] ), input( rule[0] ).value, rule[2], rule[3] );

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( parts[rule[0]], input( rule[0] ), 'slots-error-'+ rule[0], sentence );
				first = first ?? input( rule[0] );
			} );

			if( first !== null ) {
				first.focus();
				return done( false, { error : word('/_admin/slots/error/fields') } );
			}

			const typed = { label : input('label').value.trim(), width : input('width').value.trim(), height : input('height').value.trim() };

			// Nothing was changed: there is nothing to write, and what is stored stays as it is
			if( typed.label === current.label && parseInt( typed.width, 10 ) === current.width && parseInt( typed.height, 10 ) === current.height )
				return done( true, { row : current } );

			panel._apiCall( 'save', { uri : current.uri, label : typed.label, width : typed.width, height : typed.height }, function( status, response ) {

				if( status !== 200 || response === null ) {

					const field = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';
					const sentence = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' );

					// A refusal that names a field of the record is said there, and the focus goes to it
					if( parts[field] !== undefined ) {
						Nino.adminUi.fieldError( parts[field], input( field ), 'slots-error-'+ field, sentence );
						input( field ).focus();
					}

					return done( false, { error : sentence } );
				}

				panel._wrote();

				const saved = panel._derive( Object.assign( {}, current, { label : typed.label, width : parseInt( typed.width, 10 ), height : parseInt( typed.height, 10 ) } ) );

				panel._replace( saved );
				done( true, { row : saved } );
			} );
		},

		/**
		 *	Take the slot away, after asking - its uploaded file (if any) goes with it. The question stands on the record; the record is closed by it and the focus goes
		 *	to the next row. A cancelled question sends nothing
		 *
		 *	@param		{Object}		row
		 *	@param		{Element}		opener					The button that asked, which has the focus again when the question is closed
		 *	@param		{Function}	done						done( true ) where it was deleted, done( null ) where the answer was no, done( false, { error } )
		 *
		 *	@return		void
		 */
		_remove : function( row, opener, done ) {

			const panel = Nino.admin.slots;
			const current = panel._find( row.key ) ?? row;

			Nino.adminUi.confirm( 'destructive', {
				title			: word('/_admin/slots/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/slots/confirm/delete'), current.label ),
				yes				: word('/_admin/slots/confirm/delete-title'),
				no				: word('/_admin/common/label/cancel'),
				nested		: true,
				opener		: opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return done( null );

					panel._apiCall( 'delete', { uri : current.uri }, function( status, response ) {

						if( status !== 200 || response === null )
							return done( false, { error : Nino.adminUi.api.errorText( status, response, '/_admin/common/error/delete' ) } );

						panel._wrote();
						panel._rows = panel._rows.filter( function( one ) { return one.key !== current.key } );
						panel._directory.setRows( panel._rows, { keep : true } );
						done( true );
					} );
				},
			} );
		},

		/**
		 *	The dialog of a new slot: its uri, its label, its width and its height. It is no entry of the directory - there is no row to open - so it is a dialog of its
		 *	own with the address #images/slots/@new. What is wrong is said at the field before anything is sent; what the server refuses - a uri that is taken, a size -
		 *	is said at its field, and the dialog stays open until the server has said yes. Then the list is read again - the new row needs its usage - and the row is
		 *	shown, fresh and with the focus
		 *
		 *	@param		{Element|null}	opener			The button that opened it
		 *	@param		{boolean}				[person]		The person pressed the button: the address is a step of the history; otherwise it is written over the one that named it
		 *
		 *	@return		void
		 */
		_openNew : function( opener, person ) {

			const panel = Nino.admin.slots;
			const router = Nino.admin.router;

			if( panel._adding === true )
				return;

			const closed = function() {
				panel._adding = false;
				panel._dialog = null;
			};

			const uriName = word('/_admin/slots/label/uri');
			const size = function( key, label ) {
				return { key : key, label : label, help : word('/_admin/slots/hint/size'), type : 'number', min : 1, step : 1, required : true, validate : function( value ) { return panel._wrong( label, value, '/_admin/slots/expect/size', panel._isSize ) } };
			};

			if( person === true )
				router.go( 'slots', [ router.word('new') ] );
			else
				router.set( 'slots', [ router.word('new') ] );

			panel._adding = true;

			const dialog = Nino.adminUi.formDialog( {
				title		: word('/_admin/slots/label/create'),
				fields	: [
					{
						key : 'uri', label : uriName, help : word('/_admin/slots/hint/uri'), placeholder : '/template/page-home/hero/image', required : true,
						validate : function( value ) {

							const sentence = panel._wrong( uriName, value, '/_admin/slots/expect/uri', panel._isUri );

							return sentence === '' && panel._find( value.trim() ) !== null ? Nino.adminUi.format( word('/_admin/slots/error/taken'), uriName ) : sentence;
						},
					},
					{ key : 'label', label : word('/_admin/common/label/label'), help : word('/_admin/slots/hint/label'), required : true },
					size( 'width', word('/_admin/slots/label/width') ),
					size( 'height', word('/_admin/slots/label/height') ),
				],
				submit	: word('/_admin/slots/label/create'),
				cancel	: word('/_admin/common/label/cancel'),
				opener	: opener ?? undefined,
				onCancel : function() {
					closed();
					router.set( 'slots', [] );
				},
				onSubmit : function( values, ctl ) {

					const uri = values.uri.trim();

					panel._apiCall( 'create', { uri : uri, label : values.label.trim(), width : values.width.trim(), height : values.height.trim() }, function( status, response ) {

						if( status !== 200 || response === null ) {

							const field = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';
							const key = [ 'uri', 'label', 'width', 'height' ].indexOf( field ) !== -1 ? field : ( status === 409 ? 'uri' : null );

							return ctl.fail( key, status === 409 ? Nino.adminUi.format( word('/_admin/slots/error/taken'), uriName ) : Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
						}

						panel._wrote();
						ctl.close();
						closed();
						router.set( 'slots', [] );

						panel._load( true, function( read ) {

							if( read === true ) {
								panel._directory.update( panel._rows, uri );
								panel._directory.focusRow( uri );
							}

							Nino.adminUi.say( word('/_admin/slots/msg/created') );
						} );
					} );
				},
			} );

			if( dialog === false ) {
				closed();
				router.set( 'slots', [] );
				return;
			}

			panel._dialog = dialog;
		},

		/**
		 *	A step through the history that leaves the dialog of a new slot: the dialog is asked to close as Escape asks it - one with nothing typed in it closes, one
		 *	with input asks what to do with it, which is the question of the workbench - and where it stays, the address is written to what the screen shows
		 *
		 *	@return		void
		 */
		_leaveAdding : function() {

			const panel = Nino.admin.slots;
			const router = Nino.admin.router;

			if( panel._dialog !== null && typeof wn.KeyboardEvent === 'function' )
				panel._dialog.el.dispatchEvent( new wn.KeyboardEvent( 'keydown', { key : 'Escape', bubbles : true, cancelable : true } ) );

			if( panel._adding === false )
				return;

			if( router.refuse() === false )
				router.set( 'slots', [ router.word('new') ] );
		},

		/**
		 *	What the address names. The dialog of a new slot and the scan are the tab's own - #images/slots/@new, #images/slots/@scan -, the words are told from a slot
		 *	that is called "@new" by the router (isWord()) and never answered by the record, which would read one part as the key of an entry. A slot is the record's: it
		 *	reads the address itself. What is on screen is the screen: a step through the history that leaves it asks first where there is input, and where it
		 *	cannot be taken, the address is written to what the screen shows
		 *
		 *	@return		{boolean}									Whether the address was answered
		 */
		_follow : function() {

			const panel = Nino.admin.slots;
			const router = Nino.admin.router;
			const at = router.current();

			if( at.panel !== 'slots' )
				return false;

			const one = at.parts.length === 1;
			const create = one === true && router.isWord( at.parts, 0, 'new' ) === true;
			const scan = one === true && router.isWord( at.parts, 0, 'scan' ) === true;

			if( panel._adding === true ) {

				if( create === false )
					panel._leaveAdding();

				return true;
			}

			if( panel._scanning === true ) {

				if( scan === true )
					return false;

				router.leave( [ 'slots' ], true, function() {
					panel._leaveScan( null );
					panel._follow();
				}, panel._address );

				return true;
			}

			if( create === true || scan === true ) {

				router.leave( [ 'slots' ], panel._record.isOpen(), function() {

					// A record that holds input was asked about; one that does not is closed here
					if( panel._record.isOpen() === true )
						panel._record.close();

					if( panel._record.isOpen() === true )
						return panel._address();

					if( create === true )
						panel._openNew( null, false );
					else
						panel._openScan( false, false );
				}, panel._address );

				return true;
			}

			return panel._record.follow();
		},

		/**
		 *	Write the address of what is on screen: the scan, the dialog of a new slot, the slot that is open or the directory
		 *
		 *	@return		void
		 */
		_address : function() {

			const panel = Nino.admin.slots;
			const router = Nino.admin.router;
			let parts = [];

			if( panel._scanning === true )
				parts = [ router.word('scan') ];
			else if( panel._adding === true )
				parts = [ router.word('new') ];
			else if( panel._record !== null && panel._record.isOpen() === true )
				parts = [ panel._record.key() ];

			router.set( 'slots', parts );
		},

		/**
		 *	Leave the scan for the directory, as it stands: the focus goes back to the button that opened the scan where the person came back by the link, else it stays
		 *
		 *	@param		{boolean|null}	back				The person used the way back
		 *
		 *	@return		void
		 */
		_leaveScan : function( back ) {

			const panel = Nino.admin.slots;

			panel._scanning = false;
			panel._scanRows = null;

			if( back === true )
				Nino.adminUi.backTo( 'slots-list', panel._buttons.scan );
			else
				Nino.adminUi.showLevel( 'slots-list', { focus : false } );
		},

		/**
		 *	Show the directory and write its address
		 *
		 *	@return		void
		 */
		_showList : function() {
			Nino.admin.slots._leaveScan( null );
			Nino.admin.router.set( 'slots', [] );
		},

		/**
		 *	Draw the way back of the scan: one link, "Back to <the tab>", which leaves the scan for the directory
		 *
		 *	@param		{string}		title			The name of the view
		 *
		 *	@return		{Element}									The bar
		 */
		_contextBar : function( title ) {
			return Nino.adminUi.contextBar( {
				back	: { to : word('/_admin/nav/slots'), href : Nino.admin.router.href( 'slots', [] ), onClick : function() {
					Nino.admin.router.go( 'slots', [] );
					Nino.admin.slots._leaveScan( true );
				} },
				title	: title,
			} );
		},

		/**
		 *	Have the shell take the scan form on screen as the one that is stored (see Nino.admin.dirty)
		 *
		 *	@return		void
		 */
		_snapshot : function() {
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'slots-scan' );
		},

		/**
		 *	Show the scan in the level beside the directory and write its address
		 *
		 *	@param		{boolean}		[opened]	The person opened it: the focus goes into the view. Otherwise it is shown as it stands, by the address or after a step through the history
		 *
		 *	@return		void
		 */
		_showScan : function( opened ) {
			Nino.admin.router.set( 'slots', [ Nino.admin.router.word('scan') ] );
			Nino.adminUi.showLevel( 'slots-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	Open the "hardcoded <img> tags found in templates, not backed by
		 *	any slot - and slots a template names that nobody has made" scan
		 *	results form - see Slots::apiScan(). It is a view of its own, in the
		 *	level beside the directory: its way back, its form and its bar
		 *
		 *	@param		{boolean}		[opened]	The person pressed the button: the focus goes into the view
		 *	@param		{boolean}		[person]	...and the address is a step of the history; otherwise it is written over the one that named the scan
		 *
		 *	@return		void
		 */
		_openScan : function( opened, person ) {

			const slots = Nino.admin.slots;

			if( person === true )
				Nino.admin.router.go( 'slots', [ Nino.admin.router.word('scan') ] );

			slots._scanRows = null;
			slots._scanning = true;

			const wrap = dc.getElementById('slots-form');
			wrap.innerHTML = '';
			wrap.appendChild( slots._contextBar( word('/_admin/slots/label/scan') ) );

			const waiting = Nino.adminUi.el( 'p', '', word('/_admin/common/msg/scanning') );
			waiting.setAttribute( 'aria-busy', 'true' );
			wrap.appendChild( waiting );

			slots._snapshot();
			slots._showScan( opened === true );

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

			// The view that stood while the scan ran may hold the focus, on its back link: the one that replaces it takes it over
			const held = ( dc.activeElement ?? null ) !== null && dc.activeElement.closest('#slots-form') !== null;
			wrap.innerHTML = '';
			slots._scanning = true;

			const back = slots._contextBar( word('/_admin/slots/label/scan') );
			wrap.appendChild( back );

			if( held === true )
				back.querySelector('a').focus();

			if( missing.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( word('/_admin/slots/scan/none') ) );
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
				where.appendChild( Nino.adminUi.el( 'span', '', word('/_admin/slots/label/files') ) );
				where.appendChild( Nino.adminUi.el( 'p', '', item.files.join(', ') ) );

				const field = function( id, label, value, type, required ) {
					const made = Nino.adminUi.textField( { id : id, label : label, value : value, type : type, required : required } );
					fieldset.appendChild( made );
					return made;
				};

				const uriField = field( 'slots-scan-uri-'+ index, word('/_admin/slots/label/uri'), item.suggestedUri, 'text', true );
				const labelField = field( 'slots-scan-label-'+ index, word('/_admin/common/label/label'), item.suggestedUri.replace(/^\//, '').replace(/-/g, ' '), 'text', true );
				const widthField = field( 'slots-scan-width-'+ index, word('/_admin/slots/label/width'), item.width ? String( item.width ) : '', 'number', true );
				const heightField = field( 'slots-scan-height-'+ index, word('/_admin/slots/label/height'), item.height ? String( item.height ) : '', 'number', true );

				const ignore = Nino.adminUi.checkField( { id : 'slots-scan-ignore-'+ index, label : word('/_admin/common/label/ignore') } );
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
				primary	: Nino.adminUi.button( word('/_admin/slots/label/scan-create'), null, { type : 'submit' } ),
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

			Nino.adminUi.clearFieldErrors( row.field );

			let first = null;

			[
				[ row.uriInput, row.uriField, '/_admin/slots/label/uri', '/_admin/slots/expect/uri', slots._isUri ],
				[ row.labelInput, row.labelField, '/_admin/common/label/label', '', undefined ],
				[ row.widthInput, row.widthField, '/_admin/slots/label/width', '/_admin/slots/expect/size', slots._isSize ],
				[ row.heightInput, row.heightField, '/_admin/slots/label/height', '/_admin/slots/expect/size', slots._isSize ],
			].forEach( function( rule ) {

				const name = word( rule[2] );
				let sentence = slots._wrong( name, rule[0].value, rule[3], rule[4] );

				if( sentence === '' && rule[0] === row.uriInput && taken.indexOf( rule[0].value.trim() ) !== -1 )
					sentence = Nino.adminUi.format( word('/_admin/slots/error/taken'), name );

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

			const taken = slots._rows.map( function( row ) { return row.uri } );
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
				line.fail( word('/_admin/slots/error/fields') );
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

					slots._load( true, function() {

						slots._snapshot();

						if( left === 0 ) {
							slots._showList();
							Nino.adminUi.say( Nino.adminUi.format( word('/_admin/slots/scan/created'), created ) );
						}
						else {
							line.fail( word('/_admin/slots/error/fields') );
							if( created > 0 )
								Nino.adminUi.say( Nino.adminUi.format( word('/_admin/slots/scan/created'), created ) );
						}

						report( left === 0 );
					} );

					return;
				}

				const row = pending[i];

				if( refused.length === 0 )
					line.idle( Nino.adminUi.format( word('/_admin/common/msg/creating'), row.uriInput.value, i + 1, pending.length ) );

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
							? Nino.adminUi.format( word('/_admin/slots/error/taken'), word('/_admin/slots/label/uri') )
							: Nino.adminUi.api.errorText( status, response, '/_admin/common/error/save' ) );
					}

					next( i + 1 );
				} );
			};

			next( 0 );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.slots.init );

	// The shell asks before anything throws the input of the scan away (see Nino.admin.dirty). The record has its own entry, `slots`, which answers for the scan as
	// well while the scan is on screen (see _share()). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'slots-scan', function() { return dc.getElementById('slots-form') }, function( done ) {
			if( Nino.admin.slots._scanRows !== null )
				Nino.admin.slots._saveScanResults( Nino.admin.slots._scanRows, done );
			else
				done( true );
		}, function() {
			const form = dc.getElementById('slots-form');
			return form === null ? null : form.querySelector('.nino-admin-actionbar');
		} );

})(window, document, document.documentElement, document.body);
