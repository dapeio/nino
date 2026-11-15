

/**
 *	Nino										A compact filesystembased php framework
 *	admin.js								"Backups" panel: lists the encrypted daily
 *													backups the Backup engine (Backups.php beside it) creates,
 *													writes one more on request, restores one on request,
 *													hands one over as a file and takes one back.
 *													A native confirm() before the
 *													actual restore call is deliberate - this overwrites the
 *													live config.php/text/elements/images, there's no "are you
 *													sure" step server-side beyond that.
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
		 *	Report a failure that leaves the list standing, in the line below
		 *	it.
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

			const message = dc.getElementById('backups-message');

			// No list rendered means no line under it either, and then the
			// error has to go somewhere rather than nowhere
			if( message === null )
				return Nino.admin.backups._showError( status, response );

			message.className = 'nino-admin-error';
			message.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/common/error/request' );
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
		 *	@param		{Object}		stopped				{ started: unix time, files, bytes, cause: 'time'|'memory'|'error'|'', limit: seconds or bytes }
		 *
		 *	@return		{string}
		 */
		_stoppedText : function( stopped ) {

			const root = dc.documentElement;
			const when = new Date( stopped.started * 1000 ).toLocaleString( root && root.lang ? root.lang : undefined );
			const size = Nino.adminUi.megabytes( stopped.bytes );

			if( stopped.cause === 'time' )
				return Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/stopped-time'), when, stopped.limit, size, stopped.files );

			if( stopped.cause === 'memory' )
				return Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/stopped-memory'), when, Nino.adminUi.megabytes( stopped.limit ), size, stopped.files );

			return Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/stopped-open'), when, size, stopped.files );
		},

		/**
		 *	Render the list of available archives, most recent first, each with
		 *	how it came to be, how large it is and its own buttons - and, below
		 *	it, "Back up now", which is there for an empty list too: it is how the
		 *	first archive of a project that has none yet is made - and the upload
		 *	of an archive that was downloaded. "Back up now" is not offered while
		 *	backups are switched off, which the server would refuse
		 *
		 *	@param		{Object[]}	archives			[ { id, kind: 'daily'|'manual'|'snapshot', size: bytes } ]
		 *	@param		{boolean}		enabled				Whether backups are on
		 *	@param		{Object=}		missed				Set while today's automatic backup did not run: { reason, code }, the reason empty where it ended without one, the code 'write' where a write failed
		 *	@param		{Object=}		stopped				Set while the last run - the daily one or one by hand - ended without an end of its own: { kind, started, files, bytes, cause, limit }, see _stoppedText()
		 *
		 *	@return		void
		 */
		_renderList : function( archives, enabled, missed, stopped ) {

			const wrap = dc.getElementById('backups-list');
			wrap.innerHTML = '';

			// The one thing to do about it is the button below
			if( enabled !== false ) {

				const isMissed = typeof missed === 'object' && missed !== null;
				const detail = typeof stopped === 'object' && stopped !== null ? Nino.admin.backups._stoppedText( stopped ) : '';
				const noReason = isMissed === true && ( typeof missed.reason !== 'string' || missed.reason === '' );

				if( isMissed === true )
					wrap.appendChild( Nino.adminUi.notice( noReason === false
						? Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/missed'), missed.code === 'write' ? Nino.content.getText('/_admin/backups/msg/reason-write') : missed.reason )
						: ( detail !== '' ? detail+ ' '+ Nino.content.getText('/_admin/backups/msg/stopped-retry') : Nino.content.getText('/_admin/backups/msg/interrupted') ) ) );

				// Said once: a missed backup that has no reason of its own is that run
				if( detail !== '' && noReason === false )
					wrap.appendChild( Nino.adminUi.notice( detail ) );
			}

			if( archives.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/backups/empty') ) );
			else {

				const ul = dc.createElement('ul');
				ul.id = 'backups-dates';
				ul.className = 'nino-admin-list';

				archives.forEach( function( archive ) {

					const li = dc.createElement('li');
					li.setAttribute( 'data-kind', archive.kind );

					// What it is: the name, which is the date and the time and, for an archive made by
					// hand, says so - and in words, never in colour alone, how it came to be and how
					// large it is
					const info = dc.createElement('span');
					info.className = 'backups-info';

					const name = dc.createElement('span');
					name.className = 'backups-id';
					name.textContent = archive.id;
					info.appendChild( name );

					const meta = dc.createElement('span');
					meta.className = 'backups-meta';
					meta.textContent = [ Nino.content.getText('/_admin/backups/kind/'+ archive.kind), Nino.admin.backups._whenText( archive.id ), Nino.admin.backups._sizeText( archive.size ) ].join(' · ');
					info.appendChild( meta );

					li.appendChild( info );

					const actions = dc.createElement('span');
					actions.className = 'backups-actions';

					const download = dc.createElement('button');
					download.type = 'button';
					download.className = 'backups-download';
					download.textContent = Nino.content.getText('/_admin/backups/label/download');
					download.addEventListener( 'click', function() { Nino.admin.backups._download( archive.id ) } );
					if( Nino.admin.backups._full === true )
						actions.appendChild( download );

					const btn = dc.createElement('button');
					btn.type = 'button';
					btn.className = 'nino-admin-btn-danger';
					btn.textContent = Nino.content.getText('/_admin/common/label/restore');
					btn.addEventListener( 'click', function() { Nino.admin.backups._confirmRestore( archive.id ) } );
					actions.appendChild( btn );

					li.appendChild( actions );

					ul.appendChild( li );
				} );

				wrap.appendChild( ul );
			}

			// The line a refused restore or a refused backup reports into,
			// beside the list rather than in place of it
			const message = dc.createElement('p');
			message.id = 'backups-message';
			message.setAttribute( 'aria-live', 'polite' );
			wrap.appendChild( message );

			if( Nino.admin.backups._full === true ) {
				// What a downloaded archive needs to be of use, and what goes back: said to the
				// accounts that may download and upload
				const hint = dc.createElement('p');
				hint.className = 'nino-admin-hint';
				hint.textContent = Nino.content.getText('/_admin/backups/hint/key');
				wrap.appendChild( hint );

				const limit = Nino.admin.backups._limit;
				const upHint = dc.createElement('p');
				upHint.className = 'nino-admin-hint';
				upHint.textContent = limit > 0
					? Nino.adminUi.format( Nino.content.getText('/_admin/backups/hint/upload'), Nino.adminUi.megabytes( limit ) )
					: Nino.content.getText('/_admin/backups/hint/upload-sftp');
				wrap.appendChild( upHint );
			}

			const buttons = [];

			if( enabled !== false ) {
				const now = dc.createElement('button');
				now.type = 'button';
				now.id = 'backups-now';
				now.className = 'nino-admin-btn-primary';
				now.textContent = Nino.content.getText('/_admin/backups/label/now');
				now.addEventListener( 'click', function() { Nino.admin.backups._backupNow( now ) } );
				buttons.push( now );
			}

			if( Nino.admin.backups._full === true ) {
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
					Nino.admin.backups._upload( chosen );
				} );
				wrap.appendChild( file );

				const upload = dc.createElement('button');
				upload.type = 'button';
				upload.id = 'backups-upload';
				upload.textContent = Nino.content.getText('/_admin/backups/label/upload');
				upload.addEventListener( 'click', function() { file.click() } );
				buttons.push( upload );
			}

			if( buttons.length > 0 )
				wrap.appendChild( Nino.adminUi.listActions( buttons ) );
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
		 *	the language of the page
		 *
		 *	@param		{string}		id						'Y-m-d', 'Y-m-d-His', 'Y-m-d-His-manual' or 'pre-restore-Y-m-d-His'
		 *
		 *	@return		{string}
		 */
		_whenText : function( id ) {

			const part = /(\d{4})-(\d{2})-(\d{2})(?:-(\d{2})(\d{2})(\d{2}))?/.exec( id );

			if( part === null )
				return '';

			const root = dc.documentElement;
			const lang = root && root.lang ? root.lang : undefined;
			const when = new Date( +part[1], +part[2] - 1, +part[3], +( part[4] || 0 ), +( part[5] || 0 ), +( part[6] || 0 ) );

			return part[4] === undefined ? when.toLocaleDateString( lang ) : when.toLocaleString( lang );
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

			const message = dc.getElementById('backups-message');
			if( message !== null ) {
				message.className = '';
				message.textContent = '';
			}

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

			const known = typeof body.code === 'string' && /^[a-z][a-z0-9_]*$/.test( body.code ) === true && Nino.content.getText( '/_admin/error/'+ body.code ) !== '';
			const line = dc.getElementById('backups-message');

			if( line === null )
				return;

			line.className = 'nino-admin-error';
			line.textContent = known === true ? Nino.adminUi.api.errorText( 0, body ) : Nino.content.getText('/_admin/common/error/request');
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
			const message = dc.getElementById('backups-message');

			if( message === null )
				return;

			message.className = '';
			message.textContent = '';

			if( panel._limit > 0 && file.size > panel._limit - panel._UPLOAD_OVERHEAD ) {
				message.className = 'nino-admin-error';
				message.textContent = Nino.adminUi.api.errorText( 413, { code : 'backups_too_large', params : [ Nino.adminUi.megabytes( panel._limit ) ] } );
				return;
			}

			panel._setBusy( true );
			message.textContent = Nino.content.getText('/_admin/backups/msg/uploading');

			panel._apiCall( 'upload', {}, function( status, response ) {

				panel._setBusy( false );

				if( status !== 200 || response === null || typeof response.id !== 'string' ) {
					message.textContent = '';
					return panel._showRestoreError( status, response );
				}

				let said = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/uploaded'), response.id );

				// An old archive that is put back is the first to go: said at once, where it can
				// still be helped, not after it is gone
				if( response.drops === true )
					said += ' '+ Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/drops'), response.keep );

				panel._apiCall( 'list', {}, function( code, body ) {

					if( code !== 200 || body === null ) {
						panel._showRestoreError( code, body );
						const line = dc.getElementById('backups-message');
						if( line !== null )
							line.textContent = said+ ' '+ line.textContent;
						return;
					}

					panel._show( body );
					dc.getElementById('backups-message').textContent = said;
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

			button.disabled = true;

			const message = dc.getElementById('backups-message');
			if( message !== null ) {
				message.className = '';
				message.textContent = '';
			}

			Nino.admin.backups._apiCall( 'now', {}, function( status, response ) {

				button.disabled = false;

				if( status !== 200 || response === null || typeof response.id !== 'string' )
					return Nino.admin.backups._showRestoreError( status, response );

				const created = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/created'), response.id );

				// The new list replaces the old one, the line with it: what
				// was made is said once the list shows it
				Nino.admin.backups._apiCall( 'list', {}, function( code, body ) {

					if( code !== 200 || body === null ) {
						Nino.admin.backups._showRestoreError( code, body );
						// The archive is made all the same: say so before the error
						const line = dc.getElementById('backups-message');
						if( line !== null )
							line.textContent = created+ ' '+ line.textContent;
						return;
					}

					Nino.admin.backups._show( body );
					dc.getElementById('backups-message').textContent = created;
				} );
			} );
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
		 *
		 *	@return		void
		 */
		_confirmRestore : function( date ) {

			Nino.adminUi.confirm( 'destructive', {
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/backups/confirm/restore'), date ),
				yes				: Nino.content.getText('/_admin/common/label/restore'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					// Whatever a previous attempt left there is about that attempt
					const message = dc.getElementById('backups-message');
					if( message !== null )
						message.textContent = '';

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
								return Nino.admin.backups._showRestoreError( status, response );
							}

							let said = Nino.adminUi.format( Nino.content.getText('/_admin/backups/msg/restored'), date );

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
