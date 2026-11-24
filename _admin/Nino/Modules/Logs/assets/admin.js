/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js									Admin "Log" panel: read-only view of the activity log
 *													the panel class beside it writes (logins, element/text/
 *													user/image changes) - see Admin/Admin.php's record() and
 *													its class docblock. Nothing here writes anything, it
 *													only reads.
 *
 *													The panel is the standard directory of the workbench
 *													(Nino.adminUi.directory(): the search, the table of the
 *													lines - time, account and entry - and its paging) and
 *													each line opens in the dialog of a record that is only
 *													read (Nino.adminUi.record( { readOnly } ), address
 *													#logs/logs/<key>). The key of a line is the one the list
 *													answers beside it, "<day>.<position in that day's file>",
 *													one part of the address that stays the line's for as long
 *													as the line is kept. The list is read whole - fourteen
 *													days of lines - so the table searches, sorts and pages in
 *													the browser; showing the panel again reads it again, quietly,
 *													and keeps what the person chose. Everything the log holds is
 *													shown as text.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	const word = function( key ) { return Nino.content.getText( key ) };

	Nino.admin.logs = {

		// The rows of the last list: one for each line - what the table shows of it and what the record reads
		_rows					: [],
		_directory		: null,
		_record				: null,
		// What the person sees of the directory - the search, the sort, the page - kept while the panel lives
		_state				: {},
		_ready				: false,
		_loading			: false,

		/**
		 *	Load the recorded activity lines and draw them
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('logs-list') === null || Nino.admin.logs._ready === true )
				return;

			Nino.admin.logs._load( false );
		},

		/**
		 *	The panel is shown. An address that names a line opens the record, one that names none closes it; the record reads the address itself and answers only one that
		 *	is this panel's. Where it had nothing to say - the panel is entered from another one - and no record is open, the lines are read again, quietly: new
		 *	lines appear, and the search, the sort and the page stay. A record that is open is never read over
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			const panel = Nino.admin.logs;

			if( panel._ready === false )
				return panel.init();

			if( panel._record.follow() === true || panel._record.isOpen() === true )
				return;

			panel._load( true );
		},

		/**
		 *	Call a logs/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "logs/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'logs/'+ endpoint, payload, callback );
		},

		/**
		 *	Read the list. Drawn the first time with the sentence that it is loading; read again without it, the directory takes the new rows where it stands. An
		 *	answer that does not carry a key for every line is a failed load: a line without its key cannot be opened, and a key is not guessed
		 *
		 *	@param		{boolean}		quiet				A list that is drawn already is read again
		 *
		 *	@return		void
		 */
		_load : function( quiet ) {

			const panel = Nino.admin.logs;

			if( panel._loading === true )
				return;

			panel._loading = true;

			if( quiet === false || panel._directory === null ) {
				panel._frame();
				panel._directory.loading( word('/_admin/logs/loading') );
			}

			panel._apiCall( 'list', {}, function( status, response ) {

				panel._loading = false;

				const rows = status === 200 ? panel._rowsOf( response ) : null;

				if( rows === null ) {
					panel._ready = false;
					return panel._directory.fail( status, response, '/_admin/logs/error/load' );
				}

				panel._rows = rows;
				panel._directory.setRows( rows, { keep : true } );
				panel._ready = true;

				// An address that names a line is followed once the rows are in
				panel._record.follow();
			} );
		},

		/**
		 *	The rows of an answer of logs/list: the lines, most recent first, each with its key
		 *
		 *	@param		{*}					response			{ lines, keys }
		 *
		 *	@return		{Object[]|null}						null where the answer is not that - no lines, no keys, a key that is no text or is there twice
		 */
		_rowsOf : function( response ) {

			if( response === null || typeof response !== 'object' || Array.isArray( response.lines ) === false || Array.isArray( response.keys ) === false || response.keys.length !== response.lines.length )
				return null;

			const seen = new Set();

			for( const key of response.keys ) {
				if( typeof key !== 'string' || key === '' || seen.has( key ) === true )
					return null;

				seen.add( key );
			}

			return response.lines.map( function( line, at ) { return Nino.admin.logs._parse( line, response.keys[at] ) } );
		},

		/**
		 *	The room the list is drawn in: the directory, and the record over it
		 *
		 *	@return		void
		 */
		_frame : function() {

			const panel = Nino.admin.logs;

			if( panel._directory !== null )
				return;

			const wrap = dc.getElementById('logs-list');

			wrap.innerHTML = '';

			panel._directory = panel._build( wrap, panel._state );
			panel._record = panel._makeRecord();
		},

		/**
		 *	The line the log stores is the minute, the account and the sentence, two spaces between them
		 *	(see \Nino\Modules\Logs\Admin::record()); a line that is none of that is shown as the sentence alone
		 *
		 *	@param		{string}		line
		 *	@param		{string}		key						What the list answered for it: "<day>.<position>"
		 *
		 *	@return		{Object}									{ key, when, actor, text, shown }, shown the time as the workbench writes it
		 */
		_parse : function( line, key ) {

			const part = /^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}) {2}(.*?) {2}(.*)$/s.exec( String( line ) );
			const row = part === null
				? { key : key, when : '', actor : '', text : String( line ) }
				: { key : key, when : part[1], actor : part[2], text : part[3] };

			row.shown = Nino.adminUi.dateText( row.when ) || row.when;

			return row;
		},

		/**
		 *	The directory: the search and the table of the lines with its paging. There is no box to choose rows by, no select and no bar - nothing is done to a line.
		 *	On a phone a row is a card: the entry is its name, the time stands under it and the account is a line of its own
		 *
		 *	@param		{Element}		mount
		 *	@param		{Object}		[state]				What the person saw before
		 *
		 *	@return		{Object}
		 */
		_build : function( mount, state ) {

			const panel = Nino.admin.logs;

			return Nino.adminUi.directory( {
				mount : mount,
				rowKey : 'key',
				state : state,
				onState : function( now ) { panel._state = now },
				columns : [
					{ key : 'when', label : word('/_admin/logs/label/when'), type : 'date', cell : 'date', narrow : 'second' },
					{ key : 'actor', label : word('/_admin/logs/label/actor'), type : 'string' },
					{ key : 'text', label : word('/_admin/logs/label/text'), type : 'string', cell : 'clamp', fill : true, primary : true },
				],
				labels : { search : word('/_admin/logs/label/search'), empty : word('/_admin/logs/empty'), noMatch : word('/_admin/logs/nomatch'), loading : word('/_admin/logs/loading') },
				actions : function( row ) {
					return [ Nino.adminUi.iconButton( 'eye', row.shown !== '' ? Nino.adminUi.format( word('/_admin/logs/row/open'), row.shown ) : word('/_admin/logs/row/open-plain'), function( event ) {
						panel._record.open( row.key, { opener : event.currentTarget } );
					}, { small : true } ) ];
				},
				// A row is opened by its first button as well: the row is not a stop of the Tab key where it holds buttons
				onOpen : function( row ) { panel._record.open( row.key, {} ) },
			} );
		},

		/**
		 *	The record: one line read in a dialog of its own, with the step to the one before and the one after it in the rows the directory shows. The time as the
		 *	workbench writes it is its name and the account stands under it; a line that did not parse has no time to name it, and is called "Entry"
		 *
		 *	@return		{Object}
		 */
		_makeRecord : function() {

			const panel = Nino.admin.logs;

			return Nino.adminUi.record( {
				screen : 'logs',
				prefix : [],
				readOnly : true,
				directory : panel._directory,
				keyOf : function( row ) { return row.key },
				find : function( key ) { return panel._rows.find( function( row ) { return row.key === key } ) ?? null },
				title : function( row ) { return row.shown !== '' ? row.shown : word('/_admin/logs/record/title-plain') },
				subtitle : function( row ) { return row.actor },
				body : function( row ) {

					panel._directory.notice('');

					const lines = Nino.adminUi.el( 'div', 'nino-admin-record-lines' );

					lines.appendChild( Nino.adminUi.readLine( { label : word('/_admin/logs/label/text'), node : Nino.adminUi.messageBox( row.text, { label : word('/_admin/logs/label/text') } ) } ) );

					return lines;
				},
				missing : function() { panel._directory.notice( word('/_admin/logs/record/missing') ) },
			} );
		},
	};

})(window, document, document.documentElement, document.body);
