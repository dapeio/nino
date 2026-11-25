

/**
 *	Nino										A compact filesystembased php framework
 *	admin.js								"Backups" panel: lists the encrypted daily
 *													backups the Backup engine (Backups.php beside it) creates,
 *													writes one more on request, restores one on request,
 *													hands one over as a file and takes one back.
 *													A question in a dialog before the
 *													actual restore call is deliberate - this overwrites the
 *													live config.php/text/elements/images, there's no "are you
 *													sure" step server-side beyond that. Every answer to
 *													something done here is one toast; what is refused
 *													stands in the line above the bar.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.backups = {

		_ready : false,

		// The largest file an upload can carry, in bytes, as the last list said; 0 where php names none
		_limit : 0,

		// Whether the account may download and upload: the last list said so
		_full : false,

		// Whether a download was asked for and the hidden frame that receives it has not loaded: it
		// loads nothing while the browser saves the file, and the refusal where the server refuses
		_downloading : false,

		// What an upload sends besides the file, a little: the fields of the form around it
		_UPLOAD_OVERHEAD : 65536,

		// The line above the bar, which says what a refused restore, upload or download was (see Nino.adminUi.status()); drawn again with the list
		_line : null,

		/**
		 *	Load the available backup dates and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('backups-list') === null )
				return;

			Nino.admin.backups._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.backups._showError( status, response );

				Nino.admin.backups._show( response );
				Nino.admin.backups._ready = true;
			} );
		},

		/**
		 *	Re-show the list when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			if( Nino.admin.backups._ready === false )
				Nino.admin.backups.init();
		},

		/**
		 *	Call a backups/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "backups/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *	@param		{Object}		[extra]				Extra multipart fields (the file of an upload)
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback, extra ) {
			Nino.adminUi.api.call( 'backups/'+ endpoint, payload, callback, extra );
		},

		/**
		 *	Show a failed request's status/error in place of the list - for the
		 *	load that failed, where there is no list to keep in the first place
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( status, response ) {
			Nino.adminUi.showError( dc.getElementById('backups-list'), status, response, '/_admin/common/error/load' );
		},

		/**
		 *	Report a failure that leaves the list standing, in the line above
		 *	the bar.
		 *
		 *	A refused restore is exactly that: nothing was overwritten, and
		 *	every date on the list is still a date worth trying. It used to go
		 *	through _showError() above, which empties the list - so the one
		 *	screen the framework offers for getting a broken site back replaced
		 *	its backups with a sentence, and only a page reload brought them
		 *	back.
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showRestoreError : function( status, response ) {

			// No list rendered means no line either, and then the error has
			// to go somewhere rather than nowhere
			if( Nino.admin.backups._line === null || dc.getElementById('backups-message') === null )
				return Nino.admin.backups._showError( status, response );

			Nino.admin.backups._line.error( status, response, '/_admin/common/error/request' );
		},

		/**
		 *	Draw what the list call answered
		 *
		 *	@param		{Object}		response			{ archives, uploadLimit, enabled, missed, stopped }
		 *
		 *	@return		void
		 */
		_show : function( response ) {

			Nino.admin.backups._full = response.full === true;
			Nino.admin.backups._limit = typeof response.uploadLimit === 'number' && response.uploadLimit > 0 ? response.uploadLimit : 0;

			// A missing 'enabled' is an answer from before there was one
			Nino.admin.backups._renderList( Array.isArray( response.archives ) === true ? response.archives : [], response.enabled !== false, response.missed, response.stopped );
		},

		/**
		 *	Say a run that ended without an end of its own: when it began, what
		 *	ended it where the server said, and how much it had to pack
		 *
		 *	@param		{Object}		stopped				{ started: unix time, files, bytes, cause: 'time'|'memory'|'error'|'disk'|'hook'|'', limit: seconds or bytes, feature: the key of the feature that was asked for its data, '' where none was }
		 *
		 *	@return		{string}
		 */
		_stoppedText : function( stopped ) {

			const when = Nino.adminUi.dateText( new Date( stopped.started * 1000 ) );
			const size = Nino.adminUi.megabytes( stopped.bytes );
			let text;

			if( stopped.cause === 'time' )
				text = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/stopped-time'), when, stopped.limit, size, stopped.files );
			else if( stopped.cause === 'memory' )
				text = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/stopped-memory'), when, Nino.adminUi.megabytes( stopped.limit ), size, stopped.files );
			else if( stopped.cause === 'hook' )
				return Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/stopped-hook'), when, stopped.feature );
			else if( stopped.cause === 'disk' )
				text = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/stopped-disk'), when, size, stopped.files );
			else
				text = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/stopped-open'), when, size, stopped.files );

			// The feature the run was asking for a copy of its data when it ended
			if( typeof stopped.feature === 'string' && stopped.feature !== '' )
				text += ' '+ Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/stopped-feature'), stopped.feature );

			return text;
		},

		/**
		 *	Render the list of available archives, most recent first, each with
		 *	how it came to be, how large it is and its own small buttons - and,
		 *	in the bar below it, "Back up now" as the one main button, which is
		 *	there for an empty list too: it is how the first archive of a
		 *	project that has none yet is made - and the upload of an archive that
		 *	was downloaded beside it. "Back up now" is not offered while backups
		 *	are switched off, which the server would refuse; the upload is then
		 *	the one main button
		 *
		 *	@param		{Object[]}	archives			[ { id, kind: 'daily'|'manual'|'snapshot', size: bytes } ]
		 *	@param		{boolean}		enabled				Whether backups are on
		 *	@param		{Object=}		missed				Set while today's automatic backup did not run: { reason, code }, the reason empty where it ended without one, the code 'write' where a write failed
		 *	@param		{Object=}		stopped				Set while the last run - the daily one or one by hand - ended without an end of its own: { kind, started, files, bytes, cause, limit }, see _stoppedText()
		 *
		 *	@return		void
		 */
		_renderList : function( archives, enabled, missed, stopped ) {

			const panel = Nino.admin.backups;
			const wrap = dc.getElementById('backups-list');
			wrap.innerHTML = '';

			// The one thing to do about it is the button below
			if( enabled !== false ) {

				const isMissed = typeof missed === 'object' && missed !== null;
				const detail = typeof stopped === 'object' && stopped !== null ? panel._stoppedText( stopped ) : '';
				const noReason = isMissed === true && ( typeof missed.reason !== 'string' || missed.reason === '' );

				if( isMissed === true )
					wrap.appendChild( Nino.adminUi.notice( noReason === false
						? Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/missed'), missed.code === 'write' ? Nino.content.getText('/_admin/backups/msg/reason-write') : missed.reason )
						: ( detail !== '' ? detail+ ' '+ Nino.content.getText('/_admin/backups/msg/stopped-retry') : Nino.content.getText('/_admin/backups/msg/interrupted') ) ) );

				// Said once: a missed backup that has no reason of its own is that run
				if( detail !== '' && noReason === false && ( isMissed === true && missed.code === 'write' && stopped.cause === 'disk' ) === false )
					wrap.appendChild( Nino.adminUi.notice( detail ) );
			}

			// The accounts that may download and upload get a title over the archives, and behind its (?) what a
			// downloaded archive needs to be of use - a warning with a consequence that a bar cannot carry: the
			// bubble of a (?) in a fixed bar lies outside the window
			const section = dc.createElement('section');
			wrap.appendChild( section );

			if( panel._full === true ) {
				const title = Nino.adminUi.sectionTitle( Nino.content.getText('/_admin/backups/label/archives') );
				section.appendChild( title );
				Nino.adminUi.sectionHelp( title, section, Nino.content.getText('/_admin/backups/hint/key') );
			}

			if( archives.length === 0 )
				section.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/backups/empty') ) );
			else {

				// What it is: the date and the time, and - in words, never in colour alone - how it
				// came to be and how large it is. The buttons of a row are small and the dangerous one
				// stands last (rowActions())
				const rows = archives.map( function( archive ) {

					const actions = [];

					if( panel._full === true )
						actions.push( Nino.adminUi.button( Nino.content.getText('/_admin/backups/label/download'), function() { panel._download( archive.id ) }, { kind : 'secondary', small : true } ) );

					const restore = Nino.adminUi.button( Nino.content.getText('/_admin/common/label/restore'), function() { panel._confirmRestore( archive.id, restore ) }, { kind : 'secondary', small : true } );
					actions.push( restore );

					return {
						key			: archive.id,
						title		: panel._whenText( archive.id ) || archive.id,
						detail	: [ Nino.content.getText('/_admin/backups/kind/'+ archive.kind), panel._sizeText( archive.size ) ].filter( function( part ) { return part !== '' } ).join(' · '),
						actions	: actions,
					};
				} );

				const list = Nino.adminUi.list( rows, { label : Nino.content.getText('/_admin/nav/backups') } );
				list.querySelector('ul').id = 'backups-dates';
				section.appendChild( list );
			}

			// The line a refused restore or a refused backup reports into, beside
			// the list rather than in place of it
			const message = dc.createElement('p');
			message.id = 'backups-message';
			panel._line = Nino.adminUi.status( message );
			wrap.appendChild( message );

			let now = null;
			let upload = null;

			if( enabled !== false )
				now = Nino.adminUi.button( Nino.content.getText('/_admin/backups/label/now'), function() { panel._backupNow( now ) }, { kind : 'primary', id : 'backups-now' } );

			if( panel._full === true ) {

				// The file is chosen with the control the browser has; the button is
				// the way to it from the keyboard and the same on every screen
				const file = dc.createElement('input');
				file.type = 'file';
				file.id = 'backups-file';
				file.hidden = true;
				file.setAttribute( 'accept', '.php' );
				file.setAttribute( 'tabindex', '-1' );
				file.addEventListener( 'change', function() {

					if( file.files.length === 0 )
						return;

					const chosen = file.files[0];
					file.value = '';
					panel._upload( chosen );
				} );
				wrap.appendChild( file );

				upload = Nino.adminUi.button( Nino.content.getText('/_admin/backups/label/upload'), function() { file.click() }, { kind : now === null ? 'primary' : 'secondary', id : 'backups-upload' } );
			}

			if( now === null && upload === null )
				return;

			// How large an upload may be, and the way round it, stays in view before the file is chosen
			if( upload !== null ) {
				const limit = panel._limit;
				const note = dc.createElement('p');
				note.id = 'backups-limit';
				note.className = 'nino-admin-field-note';
				note.textContent = limit > 0
					? Nino.adminUi.format( Nino.content.getText('/_admin/backups/hint/upload'), Nino.adminUi.megabytes( limit ) )
					: Nino.content.getText('/_admin/backups/hint/upload-sftp');
				wrap.insertBefore( note, message.nextSibling );
			}

			wrap.appendChild( Nino.adminUi.actionBar( { list : true, secondary : now !== null && upload !== null ? [ upload ] : [], primary : now !== null ? now : upload } ).el );
		},

		/**
		 *	The size of a file as a person reads it: kilobytes below a megabyte, megabytes above
		 *
		 *	@param		{number}		bytes
		 *
		 *	@return		{string}
		 */
		_sizeText : function( bytes ) {

			if( typeof bytes !== 'number' || bytes < 0 )
				return '';

			return bytes < 1048576 ? Math.max( 1, Math.round( bytes / 1024 ) )+ ' KB' : Nino.adminUi.megabytes( bytes )+ ' MB';
		},

		/**
		 *	The date - and the time, for an archive that has one - an id names, in
		 *	the form of the interface language (Nino.adminUi.dateText())
		 *
		 *	@param		{string}		id						'Y-m-d', 'Y-m-d-His', 'Y-m-d-His-manual' or 'pre-restore-Y-m-d-His'
		 *
		 *	@return		{string}
		 */
		_whenText : function( id ) {

			const part = /(\d{4})-(\d{2})-(\d{2})(?:-(\d{2})(\d{2})\d{2})?/.exec( id );

			if( part === null )
				return '';

			return Nino.adminUi.dateText( part[1]+ '-'+ part[2]+ '-'+ part[3]+ ( part[4] === undefined ? '' : ' '+ part[4]+ ':'+ part[5] ) );
		},

		/**
		 *	Hand one archive to the browser as a file to save. The workbench posts
		 *	- its session and its token with it - into a frame nobody sees, and the
		 *	answer is the file itself, which the browser saves without the page
		 *	moving: an archive can be larger than a page may hold in memory, so it
		 *	is not read here. Where the server refuses, the frame shows the refusal
		 *	instead, and that is read and said below the list
		 *
		 *	@param		{string}		id
		 *
		 *	@return		void
		 */
		_download : function( id ) {

			if( Nino.admin.backups._line !== null )
				Nino.admin.backups._line.idle();

			let frame = dc.getElementById('backups-download');

			if( frame === null ) {
				frame = dc.createElement('iframe');
				frame.id = 'backups-download';
				frame.name = 'backups-download';
				frame.hidden = true;
				frame.setAttribute( 'title', Nino.content.getText('/_admin/backups/label/download') );
				frame.setAttribute( 'tabindex', '-1' );
				frame.addEventListener( 'load', Nino.admin.backups._downloaded );
				dc.body.appendChild( frame );
			}

			const fields = { action : 'backups/download', data : JSON.stringify( { date : id } ) };
			const csrf = dc.querySelector( 'input[name="_csrf"]' );

			if( csrf !== null )
				fields._csrf = csrf.value;

			const form = dc.createElement('form');
			form.method = 'post';
			// Where every panel posts: the directory of the project and the workbench's own address, with its slash
			form.action = ( typeof Nino.dir === 'string' ? Nino.dir : '' )+ '/_admin/';
			form.target = 'backups-download';
			form.hidden = true;

			Object.keys( fields ).forEach( function( name ) {
				const input = dc.createElement('input');
				input.type = 'hidden';
				input.name = name;
				input.value = fields[name];
				form.appendChild( input );
			} );

			dc.body.appendChild( form );
			Nino.admin.backups._downloading = true;
			form.submit();
			dc.body.removeChild( form );
		},

		/**
		 *	The hidden frame has loaded something: a file that is saved does not
		 *	load, so it is the refusal the server answered with - a json body
		 *	- which is said below the list. Its status is not readable from a
		 *	frame, the sentence of its code is what a person needs
		 *
		 *	@return		void
		 */
		_downloaded : function() {

			if( Nino.admin.backups._downloading === false )
				return;

			const frame = dc.getElementById('backups-download');
			let body = null;

			// The empty page a frame starts with loads too, in some browsers after the post was sent
			try {
				if( frame.contentWindow.location.href === 'about:blank' )
					return;
			} catch( error ) {
				return;
			}

			Nino.admin.backups._downloading = false;

			try {
				body = JSON.parse( frame.contentDocument.body.textContent );
			} catch( error ) {
				return;
			}

			if( body === null || typeof body !== 'object' )
				return;

			const known = typeof body.code === 'string' && /^[a-z][a-z0-9_-]*$/.test( body.code ) === true && Nino.content.getText( '/_admin/error/'+ body.code ) !== '';
			const line = Nino.admin.backups._line;

			if( line === null || dc.getElementById('backups-message') === null )
				return;

			line.fail( known === true ? Nino.adminUi.api.errorText( 0, body ) : Nino.content.getText('/_admin/common/error/request') );
		},

		/**
		 *	Send a chosen file to the server, which checks it and puts it in the
		 *	list. A file larger than the server takes is turned away here, with the
		 *	way round it; the server says the same where it is asked anyway. The
		 *	buttons are off while it runs - checking an archive takes a moment -
		 *	and a refusal keeps the list and says why
		 *
		 *	@param		{File}			file
		 *
		 *	@return		void
		 */
		_upload : function( file ) {

			const panel = Nino.admin.backups;
			const line = panel._line;

			if( line === null || dc.getElementById('backups-message') === null )
				return;

			line.idle();

			if( panel._limit > 0 && file.size > panel._limit - panel._UPLOAD_OVERHEAD ) {
				line.fail( Nino.adminUi.api.errorText( 413, { code : 'backups_too_large', params : [ Nino.adminUi.megabytes( panel._limit ) ] } ) );
				return;
			}

			panel._setBusy( true );
			line.idle( Nino.content.getText('/_admin/backups/msg/uploading') );

			panel._apiCall( 'upload', {}, function( status, response ) {

				panel._setBusy( false );

				if( status !== 200 || response === null || typeof response.id !== 'string' ) {
					line.idle();
					panel._showRestoreError( status, response );
					return panel._focus('backups-upload');
				}

				let said = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/uploaded'), panel._whenText( response.id ) || response.id );

				// An old archive that is put back is the first to go: said at once, where it can
				// still be helped, not after it is gone
				if( response.drops === true )
					said += ' '+ Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/drops'), response.keep );

				panel._apiCall( 'list', {}, function( code, body ) {

					// The archive is in the list all the same: said before the error of the list
					Nino.adminUi.say( said );

					if( code !== 200 || body === null ) {
						panel._showRestoreError( code, body );
						return;
					}

					panel._show( body );
					panel._focus('backups-upload');
				} );
			}, { file : file } );
		},

		/**
		 *	Write one more archive, then show the list again with it in it.
		 *	The button is off while the request runs - an archive takes a
		 *	moment - and a refusal keeps the list and says why
		 *
		 *	@param		{Element}		button
		 *
		 *	@return		void
		 */
		_backupNow : function( button ) {

			const panel = Nino.admin.backups;

			button.disabled = true;

			if( panel._line !== null )
				panel._line.idle();

			panel._apiCall( 'now', {}, function( status, response ) {

				button.disabled = false;

				if( status !== 200 || response === null || typeof response.id !== 'string' ) {
					panel._showRestoreError( status, response );
					return button.focus();
				}

				const created = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/created'), panel._whenText( response.id ) || response.id );

				// The new list replaces the old one; what was made is said once, as the toast
				panel._apiCall( 'list', {}, function( code, body ) {

					// The archive is made all the same: said before the error of the list
					Nino.adminUi.say( created );

					if( code !== 200 || body === null ) {
						panel._showRestoreError( code, body );
						return;
					}

					panel._show( body );
					panel._focus('backups-now');
				} );
			} );
		},

		/**
		 *	Put the focus on a control of the list that was drawn anew - the one that did what was just said, so that the
		 *	focus is not left on the page
		 *
		 *	@param		{string}		id
		 *
		 *	@return		void
		 */
		_focus : function( id ) {

			const control = dc.getElementById( id ) ?? dc.getElementById('backups-now');

			if( control !== null )
				control.focus();
		},

		/**
		 *	Switch the buttons of the list - every restore, download, Back up now
		 *	and the upload - off while a restore or an upload runs, on again where it was refused. After one that
		 *	succeeded they stay off: the page reloads
		 *
		 *	@param		{boolean}		busy
		 *
		 *	@return		void
		 */
		_setBusy : function( busy ) {

			const wrap = dc.getElementById('backups-list');
			if( wrap === null )
				return;

			Array.prototype.forEach.call( wrap.querySelectorAll('button, input'), function( button ) {
				button.disabled = busy;
			} );
		},

		/**
		 *	Confirm, then restore the given backup date. A safety snapshot of
		 *	the current state is taken server-side before anything is
		 *	overwritten (see apiRestore() in Admin/Admin.php beside it)
		 *
		 *	@param		{string}		date
		 *	@param		{Element}		[opener]			The button that asked, which has the focus again when the question is closed
		 *
		 *	@return		void
		 */
		_confirmRestore : function( date, opener ) {

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/backups/confirm/restore-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/backups/confirm/restore'), Nino.admin.backups._whenText( date ) || date ),
				yes				: Nino.content.getText('/_admin/backups/confirm/restore-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					// Whatever a previous attempt left there is about that attempt
					if( Nino.admin.backups._line !== null )
						Nino.admin.backups._line.idle();

					// The restore ends in a reload of the page and every form on it, and
					// has put other files in their place by then: unsaved input is asked
					// about first (see Nino.admin.dirty.guard())
					const restore = function() {

						// One restore at a time: the server refuses a second with a 409, and
						// the list does not offer one while this runs
						Nino.admin.backups._setBusy( true );

						Nino.admin.backups._apiCall( 'restore', { date : date }, function( status, response ) {
							if( status !== 200 || response === null || response.ok !== true ) {
								Nino.admin.backups._setBusy( false );
								Nino.admin.backups._showRestoreError( status, response );

								// The button that asked has the focus again, so that a refusal is read where it was asked for
								if( opener !== undefined && opener.isConnected === true )
									opener.focus();

								return;
							}

							let said = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/restored'), Nino.admin.backups._whenText( date ) || date );

							// A backup of another version of Nino restores all the same, and
							// its notice - a code and its params, worded as a failure's is - is
							// said with the restore in the one message the page shows before it
							// reloads: a line in the panel would be gone with the reload
							if( typeof response.notice === 'object' && response.notice !== null )
								said += ' '+ Nino.adminUi.api.errorText( status, response.notice );

							// The restore ended the session - the sessions are part of what was
							// restored, and the one that ran it is ended either way (see
							// apiRestore()) - so the reload lands on the login page. The message
							// is read in a dialog before that, and the dialog's one choice is the
							// way there - a reload of its own accord would be the page changing
							// under the person's eyes
							if( Nino.adminUi.choiceDialog( {
								title		: Nino.content.getText('/_admin/nav/backups'),
								message	: said,
								choices	: [ { value : 'login', label : Nino.content.getText('/_admin/backups/label/login'), kind : 'primary' } ],
								onChoose : function() { wn.location.reload() },
							} ) === false )
								wn.location.reload();
						} );
					};

					if( typeof Nino.admin.dirty === 'object' )
						Nino.admin.dirty.guard( null, restore );
					else
						restore();
				},
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.backups.init );

})(window, document, document.documentElement, document.body);
