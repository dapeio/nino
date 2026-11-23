/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js								The Form module's /_admin panel, "Submissions": every
 *													submission \Nino\Form records (in addition to the mail
 *													itself) - see Modules\Form\Admin beside this file.
 *													Ships with the module: it is in the workbench bundle
 *													exactly while the module is active.
 *
 *													The panel knows no field names of its own. A project
 *													may define any number of forms with fields of their
 *													own (\Nino\Form::FORMS), so what a row shows and what
 *													a submission opens as is whatever the entry carries,
 *													labelled from the form it belongs to.
 *
 *													It is the standard directory of the workbench
 *													(Nino.adminUi.directory(): the form to narrow to, the
 *													search over every value, the table, its box to choose
 *													rows by and the paging) and each submission opens in
 *													the dialog of a record (Nino.adminUi.record(), address
 *													#submissions/submissions/<id>). The list is read whole,
 *													as it always was: a month holds at most
 *													\Nino\Form::MONTH_CAP_DEFAULT submissions and the
 *													retention is three months by default, so the table
 *													filters and sorts in the browser.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.submissions = {

		// What the last list answered: the entries, most recent first, and
		// key => { name, fields } for the forms they belong to
		_entries : [],
		_forms 	 : {},
		// Which cap of \Nino\Form refuses submissions right now: 'month',
		// 'hour' or '' - the list then says so above the table
		_capped  : '',
		// The rows of the table, one for each entry, and what draws them
		_rows 	 : [],
		_directory : null,
		_record  : null,
		// The forms the directory was drawn for, as one string: a form that comes or goes is another select
		_shape   : '',
		// Whether a list has been read, and whether one is being read now
		_ready 	 : false,
		_loading : false,
		// What the person sees of the directory - the form, the search, the sort, the page - kept while the directory is drawn again
		_state 	 : {},

		/**
		 *	Load the recorded submissions and draw them
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('submissions-list') === null )
				return;

			Nino.admin.submissions._load( false );
		},

		/**
		 *	The panel is shown: the address names a submission - the record opens, or follows - or none - the record is closed - and the list
		 *	that was drawn stays as it is. A panel that is entered again reads what came in meanwhile, without losing what was chosen
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			const panel = Nino.admin.submissions;

			if( panel._ready === false )
				return panel.init();

			// The record reads the address itself, and answers only one that is this panel's: it closes by itself where the address leads away
			if( panel._record.follow() === true || panel._record.isOpen() === true )
				return;

			panel._load( true );
		},

		/**
		 *	Call a submissions/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "submissions/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'submissions/'+ endpoint, payload, callback );
		},

		/**
		 *	Read the list. Drawn the first time with the sentence that it is loading; read again without it, the directory takes the new rows
		 *	where it stands - its search, its form, its sort and its page are what a person chose
		 *
		 *	@param		{boolean}		quiet				A list that is drawn already is read again
		 *
		 *	@return		void
		 */
		_load : function( quiet ) {

			const panel = Nino.admin.submissions;

			if( panel._loading === true )
				return;

			panel._loading = true;

			if( quiet === false || panel._directory === null ) {
				panel._frame();
				panel._directory.loading( Nino.content.getText('/_admin/submissions/loading') );
			}

			panel._apiCall( 'list', {}, function( status, response ) {

				panel._loading = false;

				if( status !== 200 || response === null )
					return panel._directory.fail( status, response, '/_admin/submissions/error/load' );

				panel._entries = response.entries || [];
				panel._forms 	 = response.forms || {};
				panel._capped	 = response.capped || '';
				panel._draw();
			} );
		},

		/**
		 *	The room the list is drawn in: the notice of a cap over the directory, and the directory
		 *
		 *	@return		void
		 */
		_frame : function() {

			const panel = Nino.admin.submissions;

			if( panel._directory !== null )
				return;

			const wrap = dc.getElementById('submissions-list');

			wrap.innerHTML = '';

			const cap = dc.createElement('div');
			cap.id = 'submissions-cap';
			wrap.appendChild( cap );

			const mount = dc.createElement('div');
			mount.id = 'submissions-directory';
			wrap.appendChild( mount );

			panel._shape = '';
			panel._directory = panel._build( mount );
			panel._record = panel._makeRecord();
		},

		/**
		 *	Draw what the last list answered
		 *
		 *	@return		void
		 */
		_draw : function() {

			const panel = Nino.admin.submissions;
			const cap = dc.getElementById('submissions-cap');

			// A cap that refuses submissions, said before anything else: a full month's list is as long as it gets, and what is refused is not on it
			cap.innerHTML = '';

			if( panel._capped === 'month' || panel._capped === 'hour' ) {
				const capped = Nino.adminUi.notice( Nino.content.getText( '/_admin/submissions/capped/'+ panel._capped ) );
				capped.id = 'submissions-capped';
				cap.appendChild( capped );
			}

			// A form that comes or goes is another select: the directory is drawn again, as it stood
			const shape = Object.keys( panel._forms ).join('|');

			if( panel._shape !== shape ) {

				panel._state = panel._directory.state();

				// A form that is gone - renamed, or deleted in the builder - would otherwise hide every row with nothing on screen saying why.
				// Its submissions are still there and still shown
				if( panel._forms[ panel._state.category ] === undefined )
					panel._state = Object.assign( {}, panel._state, { category : '' } );

				panel._directory = panel._build( dc.getElementById('submissions-directory'), panel._state );
				panel._shape = shape;
			}

			panel._rows = panel._entries.map( panel._row );
			panel._directory.setRows( panel._rows, { keep : true } );
			panel._bar();
			panel._ready = true;

			panel._record.follow();
		},

		/**
		 *	The bar of the list: its one main button is the export of what is on screen
		 *
		 *	@return		void
		 */
		_bar : function() {

			const panel = Nino.admin.submissions;
			const wrap = dc.getElementById('submissions-list');
			const old = dc.getElementById('submissions-actions');

			if( old !== null )
				old.remove();

			if( panel._rows.length === 0 )
				return;

			// What is on screen, not what is on disk: an export taken while a form is selected is the export of that form. Read at the moment of the
			// click, since the directory moves without the list being drawn again
			const exportBtn = Nino.adminUi.button( Nino.content.getText('/_admin/submissions/label/export'), function() {
				Nino.admin.exportCsv( Nino.content.getText('/_admin/submissions/label/filename'), panel._directory.rows().map( function( row ) { return row.entry } ) );
			}, { kind : 'primary', id : 'submissions-export' } );

			wrap.appendChild( Nino.adminUi.actionBar( { list : true, id : 'submissions-actions', primary : exportBtn } ).el );
		},

		/**
		 *	The directory: the form to narrow to - where there are several - then the search, the table of the submissions and its paging
		 *
		 *	@param		{Element}		mount
		 *	@param		{Object}		[state]				What the person saw before
		 *
		 *	@return		{Object}
		 */
		_build : function( mount, state ) {

			const panel = Nino.admin.submissions;
			const keys = Object.keys( panel._forms );
			const word = function( key ) { return Nino.content.getText( key ) };
			const columns = [
				{ key : 'date', label : word('/_admin/submissions/col/date'), type : 'date', cell : 'date' },
			];

			// With one form the column would say the same word in every row
			if( keys.length > 1 )
				columns.push( { key : 'form', label : word('/_admin/submissions/label/form'), type : 'string' } );

			columns.push(
				// On a phone a row is a card: the sender is its name, the address is in the record
				{ key : 'sender', label : word('/_admin/submissions/col/sender'), type : 'string', cell : 'clamp', max : '12rem', primary : true },
				{ key : 'mail', label : word('/_admin/submissions/col/mail'), type : 'string', cell : 'clamp', max : '13rem', narrow : false },
				{ key : 'message', label : word('/_admin/submissions/col/message'), type : 'string', cell : 'clamp', fill : true }
			);

			return Nino.adminUi.directory( {
				mount : mount,
				columns : columns,
				rowKey : 'key',
				state : state,
				onState : function( now ) { panel._state = now },
				labels : { search : word('/_admin/submissions/label/search'), empty : word('/_admin/submissions/empty'), noMatch : word('/_admin/submissions/nomatch') },
				// The select is drawn only where there is more than one form to choose between - on the single-form project this framework ships as, it
				// would be a control with one option
				category : keys.length > 1 ? {
					id : 'submissions-form',
					label : word('/_admin/submissions/label/form'),
					value : '',
					options : [ { value : '', label : word('/_admin/submissions/label/form-all') } ].concat( keys.map( function( key ) {
						return { value : key, label : panel._forms[key].name || key };
					} ) ),
					match : function( row, value ) { return value === '' || row.formKey === value },
				} : undefined,
				// A search reads every value and the label it was collected under, and the moment as it is shown
				searchText : function( row ) {
					return row.fields.map( function( field ) { return field.label+ ' '+ field.value } ).concat( [ row.date ] ).join(' ');
				},
				actions : function( row ) {

					const open = Nino.adminUi.iconButton( 'eye', Nino.adminUi.format( word('/_admin/submissions/row/open'), row.when ), function( event ) {
						panel._record.open( row.key, { opener : event.currentTarget } );
					}, { small : true } );

					if( row.entry.id === '' )
						return [ open ];

					return [ open, Nino.adminUi.iconButton( 'trash-2', Nino.adminUi.format( word('/_admin/submissions/row/delete'), row.when ), function( event ) {
						panel._remove( row, event.currentTarget, function( went ) {
							if( went === true )
								Nino.adminUi.say( word('/_admin/common/msg/deleted') );
						} );
					}, { small : true, danger : true } ) ];
				},
				// A row is opened by its first button as well: the row is not a stop of the Tab key where it holds buttons
				onOpen : function( row ) { panel._record.open( row.key, {} ) },
				select : {
					label : word('/_admin/submissions/select/page'),
					rowLabel : function( row ) { return Nino.adminUi.format( word('/_admin/submissions/select/row'), row.when ) },
					actions : [
						{ key : 'export', label : word('/_admin/submissions/bulk/export'), run : function( keys ) {
							Nino.admin.exportCsv( word('/_admin/submissions/label/filename'), panel._rows.filter( function( row ) { return keys.indexOf( row.key ) !== -1 } ).map( function( row ) { return row.entry } ) );
						} },
						{ key : 'delete', label : word('/_admin/submissions/bulk/delete'), danger : true, run : function( keys ) { panel._removeMany( keys ) } },
					],
				},
			} );
		},

		/**
		 *	The row of an entry: what the table shows of it and what the record reads
		 *
		 *	@param		{Object}		entry
		 *	@param		{number}		index
		 *
		 *	@return		{Object}
		 */
		_row : function( entry, index ) {

			const panel = Nino.admin.submissions;
			const fields = panel._fields( entry );
			const mail = fields.filter( function( field ) { return field.type === 'email' && field.value !== '' } )[0];
			const long = fields.filter( function( field ) { return field.type === 'textarea' && field.value !== '' } )[0];
			const first = fields.filter( function( field ) { return field !== mail && field !== long && field.type !== 'textarea' && field.value !== '' } )[0];

			return {
				// An entry recorded before submissions carried an id has the place it stands at for a key, in a form no id has (the ids the server makes are hex): it names a row of the
				// table, and is no address - such an entry has none (see addressOf)
				key : ( entry.id || '' ) !== '' ? String( entry.id ) : 'no-id-'+ index,
				entry : entry,
				fields : fields,
				date : String( entry.date ?? '' ),
				when : panel._when( entry ),
				form : ( panel._forms[ entry.form ] || {} ).name || entry.form || '',
				formKey : entry.form || '',
				sender : first === undefined ? '' : first.value,
				mail : mail === undefined ? '' : mail.value,
				message : long === undefined ? '' : long.value,
			};
		},

		/**
		 *	One entry's values as the record shows them: everything it carries that is not bookkeeping, in the order the form declares its
		 *	fields, each with the label it was collected under.
		 *
		 *	A value whose field the form no longer declares still shows, at
		 *	the end and under its own name - a form that lost a field must
		 *	not take the answers with it. And the decoding is
		 *	decodeEntities() + textContent throughout: \Nino\Form::record()
		 *	stored every value htmlspecialchars()'d, and this undoes that for
		 *	display without ever parsing the result as markup
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{Array}								[ { name, label, type, value }, ... ]
		 */
		_fields : function( entry ) {

			const form 	= Nino.admin.submissions._forms[ entry.form ] || { fields : [] };
			const skip 	= [ 'id', 'date', 'ip', 'form' ];
			const seen 	= [];
			const out 	= [];

			form.fields.forEach( function( field ) {
				if( entry[ field.name ] === undefined )
					return;
				seen.push( field.name );
				out.push( { name : field.name, label : field.label || field.name, type : field.type, value : Nino.admin.decodeEntities( String( entry[ field.name ] ) ) } );
			} );

			Object.keys( entry ).forEach( function( name ) {
				if( skip.indexOf( name ) !== -1 || seen.indexOf( name ) !== -1 )
					return;
				out.push( { name : name, label : name, type : 'text', value : Nino.admin.decodeEntities( String( entry[ name ] ) ) } );
			} );

			return out;
		},

		/**
		 *	The day and the minute an entry came in, as the workbench writes a moment
		 *	(Nino.adminUi.dateText()) - what the row shows, and so what the search finds
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{string}									The text the stored date gives, the stored date itself where it is none
		 */
		_when : function( entry ) {
			return Nino.adminUi.dateText( String( entry.date ?? '' ) ) || String( entry.date ?? '' );
		},

		/**
		 *	The address a submission can be answered at: only a strictly plain one - ASCII letters, digits and . _ + - in the name, one @, a domain of
		 *	labels of letters, digits and hyphens - is made a link or answered by mail. A value a visitor typed that carries anything else (a % that a mail
		 *	program decodes to a comma or a line break, a query, a second address, a character that reverses the text) is shown as the text it is and
		 *	answered by hand
		 *
		 *	@param		{string}		value
		 *
		 *	@return		{string}									'' where it is none
		 */
		_address : function( value ) {
			return /^[A-Za-z0-9][A-Za-z0-9._+-]{0,63}@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$/.test( value ) === true && value.length <= 254 ? value : '';
		},

		/**
		 *	The record: a submission read in a dialog of its own, with the step to the one before and the one after it in the rows the directory
		 *	shows, the answer by mail, and the deletion at the left of its bar
		 *
		 *	@return		{Object}
		 */
		_makeRecord : function() {

			const panel = Nino.admin.submissions;
			const word = function( key ) { return Nino.content.getText( key ) };
			const rowOf = function( key ) { return panel._rows.find( function( row ) { return row.key === key } ) ?? null };

			return Nino.adminUi.record( {
				screen : 'submissions',
				readOnly : true,
				// The directory it opened from, whatever it is drawn as at the moment
				directory : {
					rows : function() { return panel._directory.rows() },
					reveal : function( key ) { return panel._directory.reveal( key ) },
					focusRow : function( key ) { return panel._directory.focusRow( key ) },
					focusSearch : function() { return panel._directory.focusSearch() },
				},
				keyOf : function( row ) { return row.key },
				// An entry recorded before submissions carried an id has no address: it is read all the same, and the address stays the list's
				addressOf : function( row ) { return row.entry.id === '' ? null : row.key },
				find : rowOf,
				title : function( row ) { return Nino.adminUi.format( word('/_admin/submissions/record/title'), row.sender !== '' ? row.sender : ( row.mail !== '' ? row.mail : row.form ) ) },
				subtitle : function( row ) { return row.form+ ' · '+ row.when },
				body : function( row ) {

					panel._directory.notice('');

					const lines = Nino.adminUi.el( 'div', 'nino-admin-record-lines' );
					const long = row.fields.filter( function( field ) { return field.type === 'textarea' && field.value !== '' } );

					row.fields.forEach( function( field ) {

						if( field.value === '' || long.indexOf( field ) !== -1 )
							return;

						lines.appendChild( Nino.adminUi.readLine( { label : field.label, value : field.value, href : field.type === 'email' && panel._address( field.value ) !== '' ? 'mailto:'+ field.value : undefined } ) );
					} );

					lines.appendChild( Nino.adminUi.readLine( { label : word('/_admin/submissions/col/date'), value : row.when } ) );

					if( Object.keys( panel._forms ).length > 1 )
						lines.appendChild( Nino.adminUi.readLine( { label : word('/_admin/submissions/label/form'), value : row.form } ) );

					long.forEach( function( field ) {
						lines.appendChild( Nino.adminUi.readLine( { label : field.label, node : Nino.adminUi.messageBox( field.value, { label : field.label } ) } ) );
					} );

					return lines;
				},
				// What can be done with a submission is to answer it - there is no button where there is no plain address to answer at
				actions : [ { label : word('/_admin/submissions/record/reply'), kind : 'primary', hidden : function( row ) { return panel._address( row.mail ) === '' }, run : function( row ) {

					const to = panel._address( row.mail );

					if( to !== '' )
						wn.location.href = 'mailto:'+ to;
				} } ],
				danger : {
					label : word('/_admin/submissions/confirm/delete-title'),
					// An entry too old to have an id carries none - there is nothing to address it by that survives a deletion
					hidden : function( row ) { return row.entry.id === '' },
					run : function( row, opener, done ) { panel._remove( row, opener, done, true ) },
				},
				missing : function() { panel._directory.notice( word('/_admin/submissions/record/missing') ) },
			} );
		},

		/**
		 *	Delete one submission, after asking. Once the server has said it is gone the row is taken out of the table where it stands, on the
		 *	page it is on
		 *
		 *	@param		{Object}		row
		 *	@param		{Element}		opener					The button that asked, which has the focus again when the question is closed
		 *	@param		{Function}	done						done( true ) where it was deleted, done( null ) where the answer was no, done( false, { error } )
		 *	@param		{boolean}		[nested]				The question stands on the record that is open
		 *
		 *	@return		void
		 */
		_remove : function( row, opener, done, nested ) {

			const panel = Nino.admin.submissions;
			const entry = row.entry;

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/submissions/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/submissions/confirm/delete'), row.when ),
				yes				: Nino.content.getText('/_admin/submissions/confirm/delete-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				nested		: nested === true,
				opener		: opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return done( null );

					panel._apiCall( 'delete', { id : entry.id }, function( status, response ) {

						if( status !== 200 || response === null )
							return done( false, { error : Nino.adminUi.api.errorText( status, response, '/_admin/submissions/error/delete' ) } );

						const around = panel._directory.rows();
						const at = around.indexOf( row );
						const next = around[at + 1] ?? around[at - 1];

						panel._entries = panel._entries.filter( function( one ) { return one !== entry } );
						panel._rows = panel._entries.map( panel._row );
						panel._directory.setRows( panel._rows, { keep : true } );
						panel._bar();

						// A row deleted from the table has the focus go to the one that took its place
						if( nested !== true && next !== undefined )
							panel._directory.focusRow( next.key );

						done( true );
					} );
				},
			} );
		},

		/**
		 *	Delete the submissions that are chosen in the table, after one question that says how many. Each is one delete of the one action
		 *	there is, with its own check of the right and the token; the first that fails ends the run, and the list is read again to show
		 *	what is left
		 *
		 *	@param		{Array}			keys					The keys of the chosen rows
		 *
		 *	@return		void
		 */
		_removeMany : function( keys ) {

			const panel = Nino.admin.submissions;
			const rows = panel._rows.filter( function( row ) { return keys.indexOf( row.key ) !== -1 && row.entry.id !== '' } );

			if( rows.length === 0 )
				return;

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/submissions/confirm/delete-many-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/submissions/confirm/delete-many'), rows.length ),
				yes				: Nino.content.getText('/_admin/submissions/confirm/delete-many-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					let gone = 0;

					const next = function() {

						if( gone === rows.length ) {
							panel._directory.clearSelection();
							Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/deleted') );
							panel._load( true );
							return;
						}

						panel._apiCall( 'delete', { id : rows[gone].entry.id }, function( status, response ) {

							if( status !== 200 || response === null ) {
								panel._directory.notice( Nino.adminUi.format( Nino.content.getText('/_admin/submissions/bulk/partial'), gone, rows.length, Nino.adminUi.api.errorText( status, response, '/_admin/submissions/error/delete' ) ) );
								panel._load( true );
								return;
							}

							gone++;
							next();
						} );
					};

					next();
				},
			} );
		},
	};

})(window, document, document.documentElement, document.body);
