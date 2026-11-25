/**
 *	Nino										A compact filesystembased php framework
 *	recovery.js							The break-glass page (_admin/recovery.php): one secret, then
 *													a list of backups to restore - and an archive of another
 *													project from a file, with the key that archive was made
 *													with - a form to give an account a password and one to
 *													create an account with full access.
 *													It speaks German or English (pagetext.js) and draws its
 *													forms with the blocks of the workbench (Nino.admin.js), which
 *													are static files of the kernel: nothing here reads the project's
 *													text or bundles, which may be what is broken. Every sentence is
 *													a key of the table in recovery-text.js, and what the server
 *													refuses comes back as a code that table has a sentence for.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */
( function(wn,dc,dE,bd) {

	wn.Nino.recovery = wn.Nino.recovery || {};

	// A sentence of the table, in the language of the page
	const t = function( key, ...params ) { return Nino.pageText.t( key, ...params ) };

	// The codes of a refusal that belong to a field: the key of an archive, the file of one
	const KEY_CODES = [ 'backups_key', 'backups_key_wrong', 'backups_key_missing', 'backups_key_format', 'backups_key_tries', 'backups_upload_key', 'backups_upload_nokey' ];
	const FILE_CODES = [ 'backups_too_large', 'backups_upload_name', 'backups_upload_exists', 'backups_upload_format', 'backups_upload_memory', 'backups_disk', 'upload_too_large', 'upload_partial', 'upload_missing', 'upload_server' ];

	Object.assign( wn.Nino.recovery, {

		// What the last list said: the dates of the backups, the accounts, the largest upload the server takes (0 where php names none)
		_data : { dates : [], users : [], limit : 0, failed : '' },

		// The parts of the forms that are on the page, for the handlers that reach them
		_ui : null,

		// What the request carries besides the file: the other fields of the form, a little
		_OVERHEAD : 16384,

		/**
		 *	Call a recovery/* action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "recovery/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( Nino.dir+ '/_admin/recovery.php', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'recovery/'+ endpoint, data : JSON.stringify( payload ) } );
		},

		/**
		 *	The least length of a password, which the server states in the page and enforces itself
		 *
		 *	@return		{number}
		 */
		_min : function() {

			const wrap = dc.getElementById('recovery-wrap');
			const min = wrap === null ? 0 : parseInt( wrap.dataset.minPassword, 10 );

			return Number.isInteger( min ) === true && min > 0 ? min : 0;
		},

		/**
		 *	The most bytes a password may have, which the server states in the page and enforces itself
		 *
		 *	@return		{number}
		 */
		_max : function() {

			const wrap = dc.getElementById('recovery-wrap');
			const max = wrap === null ? 0 : parseInt( wrap.dataset.maxPassword, 10 );

			return Number.isInteger( max ) === true && max > 0 ? max : 0;
		},

		/**
		 *	Whether an answer refuses the password that was sent: its code is one of the three the server has for it
		 *
		 *	@param		{*}					response
		 *
		 *	@return		{boolean}
		 */
		_isPasswordRefusal : function( response ) {

			const code = response !== null && typeof response === 'object' && typeof response.code === 'string' ? response.code : '';

			return [ 'recovery_password_short', 'recovery_password_long', 'recovery_password_invalid' ].indexOf( code ) !== -1;
		},

		/**
		 *	What a refusal says, in the language of the page: the sentence of its code, or the general one. A refusal
		 *	that is about the length of a password has the length to say
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		{string}
		 */
		_failure : function( status, response ) {
			return Nino.pageText.failure( status, response, Nino.recovery._min() );
		},

		/**
		 *	A fieldset with its title and the (?) of the sentence that explains it: the card of one tool
		 *
		 *	@param		{string}		id
		 *	@param		{string}		area					The part of the table: 'restore', 'reset', 'create'
		 *
		 *	@return		{Element}
		 */
		_card : function( id, area ) {

			const card = Nino.adminUi.el( 'fieldset', '' );
			card.id = id;

			const legend = Nino.adminUi.el( 'legend', '', t( '/_admin/recovery/'+ area+ '/title' ) );
			card.appendChild( legend );
			Nino.adminUi.sectionHelp( legend, card, t( '/_admin/recovery/'+ area+ '/help' ) );

			return card;
		},

		/**
		 *	The foot of a card: the line that says how the last action went, at the left, and the button of the card at the right
		 *
		 *	@param		{Element}		button
		 *
		 *	@return		{Object}									{ el, line } - line is the status of the workbench
		 */
		_foot : function( button ) {

			const line = Nino.adminUi.status( Nino.adminUi.el('p') );
			const el = Nino.adminUi.el( 'div', 'nino-admin-actions nino-admin-actions--between' );

			el.appendChild( line.element );
			el.appendChild( button );

			return { el : el, line : line };
		},

		/**
		 *	The secret form - a reload after success shows the tools, since
		 *	the [csrf] field has been rotated on the server
		 *
		 *	@return		void
		 */
		_drawLogin : function() {

			const holder = dc.getElementById('recovery-login-fields');
			const form = dc.getElementById('recovery-login');

			holder.innerHTML = '';

			const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );
			const pw = Nino.adminUi.textField( { key : 'login-pw', id : 'recovery-input-pw', type : 'password', autocomplete : 'current-password', required : true, label : t('/_admin/recovery/login/pw'), help : t('/_admin/recovery/login/pw-help') } );
			const input = pw.querySelector('input');
			const submit = Nino.adminUi.button( t('/_admin/recovery/login/submit'), null, { kind : 'primary', type : 'submit', id : 'recovery-login-submit' } );
			const foot = Nino.recovery._foot( submit );

			grid.appendChild( pw );
			holder.appendChild( grid );
			holder.appendChild( foot.el );
			Nino.recovery._login = { form : form, pw : pw, input : input, line : foot.line };

			input.focus();
		},

		_login : null,

		_onLogin : function( ev ) {

			ev.preventDefault();

			const ui = Nino.recovery._login;

			Nino.adminUi.clearFieldErrors( ui.form );

			if( ui.input.value === '' ) {
				ui.line.idle('');
				Nino.adminUi.fieldError( ui.pw, ui.input, 'recovery-login-error', Nino.adminUi.format( t('/_admin/common/error/missing'), t('/_admin/recovery/login/pw') ) );
				ui.input.focus();
				return;
			}

			ui.line.idle( t('/_admin/recovery/login/checking') );

			Nino.recovery._apiCall( 'login', { password : ui.input.value }, function( status, response ) {

				if( status !== 200 ) {
					ui.line.idle('');
					Nino.adminUi.fieldError( ui.pw, ui.input, 'recovery-login-error', Nino.recovery._failure( status, response ) );
					ui.input.value = '';
					ui.input.focus();
					return;
				}

				wn.location.reload();
			} );
		},

		/**
		 *	What a restore says besides that it happened, for the backup of
		 *	another version of Nino: where it comes from and what runs here
		 *
		 *	@param		{*}					response			The answer of recovery/restore
		 *
		 *	@return		{string}									'' or ' The backup comes from Nino 1.8.0; this is 1.9.0.'
		 */
		_notice : function( response ) {

			const params = response && response.notice && Array.isArray( response.notice.params ) ? response.notice.params : [];
			const code = response && response.notice && typeof response.notice.code === 'string' ? response.notice.code : '';

			// The notices of a snapshot of the state as it was and of files that stand beside a database: the table of the page has their sentences
			if( code === 'backups_snapshot_raw' || code === 'backups_companions_left' )
				return ' '+ t( '/_admin/page/error/'+ code.replace( /_/g, '-' ), ...params );

			return params.length === 2 ? ' '+ t( '/_admin/recovery/restore/notice', params[0], params[1] ) : '';
		},

		/**
		 *	Say a refusal where it belongs: at the field it is about - the key of an archive, the file - else in the line of the card.
		 *	The focus goes to the field
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *	@param		{Object}		line					The status of the card
		 *	@param		{boolean}		[withFile]		Whether the file of the card is the field of a refusal about a file
		 *
		 *	@return		void
		 */
		_refused : function( status, response, line, withFile ) {

			const ui = Nino.recovery._ui;
			const code = response !== null && typeof response === 'object' && typeof response.code === 'string' ? response.code : '';
			const text = Nino.recovery._failure( status, response );

			line.idle('');

			if( KEY_CODES.indexOf( code ) !== -1 ) {
				Nino.adminUi.fieldError( ui.key, ui.key.querySelector('input'), 'recovery-key-error', text );
				ui.key.querySelector('input').focus();
				return;
			}

			if( withFile === true && FILE_CODES.indexOf( code ) !== -1 ) {
				ui.file.error( text );
				ui.file.button.focus();
				return;
			}

			line.fail( text );
		},

		/**
		 *	The key that was typed is used up by the one request it was typed for: the field is emptied
		 *	where the restore went through, so that nothing of it stays on a page that is left open
		 *
		 *	@param		{number}		status				What the restore answered
		 *
		 *	@return		void
		 */
		_keyDone : function( status ) {
			if( status === 200 )
				Nino.recovery._ui.key.querySelector('input').value = '';
		},

		/**
		 *	Restore one backup of the list, after the question
		 *
		 *	@param		{string}		date
		 *	@param		{Element}		opener
		 *
		 *	@return		void
		 */
		_restore : function( date, opener ) {

			const ui = Nino.recovery._ui;

			Nino.adminUi.clearFieldErrors( ui.restore );
			ui.restoreLine.idle('');

			Nino.adminUi.confirm( 'destructive', {
				title		: t('/_admin/recovery/restore/confirm-title'),
				message	: t( '/_admin/recovery/restore/confirm', date ),
				yes			: t('/_admin/recovery/restore/confirm-title'),
				no			: t('/_admin/common/label/cancel'),
				kind		: 'danger',
				opener	: opener,
				onChoose : function( ok ) {

					if( ok !== true )
						return;

					ui.restoreLine.idle( t('/_admin/recovery/restore/busy') );

					Nino.recovery._apiCall( 'restore', { date : date, key : ui.key.querySelector('input').value }, function( status, response ) {

						if( status !== 200 ) {
							Nino.recovery._refused( status, response, ui.restoreLine, false );
							return;
						}

						ui.restoreLine.idle('');
						Nino.adminUi.say( t( '/_admin/recovery/restore/done', response.restoredDate )+ Nino.recovery._notice( response ) );
						Nino.recovery._keyDone( status );
					} );
				},
			} );
		},

		/**
		 *	Restore the archive of a file with the key above: the file and the key go in one request, and
		 *	nothing is kept of either on the server afterwards
		 *
		 *	@param		{Event}		ev
		 *
		 *	@return		void
		 */
		_restoreFile : function( ev ) {

			ev.preventDefault();

			const ui = Nino.recovery._ui;
			const input = ui.key.querySelector('input');
			const file = ui.file.value();

			Nino.adminUi.clearFieldErrors( ui.restore );
			ui.fileLine.idle('');

			if( file === null ) {
				ui.file.error( Nino.adminUi.format( t('/_admin/common/error/missing'), t('/_admin/recovery/file/label') ) );
				ui.file.button.focus();
				return;
			}

			if( input.value.trim() === '' ) {
				Nino.adminUi.fieldError( ui.key, input, 'recovery-key-error', t('/_admin/page/error/backups-key-missing') );
				input.focus();
				return;
			}

			Nino.adminUi.confirm( 'destructive', {
				title		: t('/_admin/recovery/file/confirm-title'),
				message	: t('/_admin/recovery/file/confirm'),
				yes			: t('/_admin/recovery/file/confirm-title'),
				no			: t('/_admin/common/label/cancel'),
				kind		: 'danger',
				opener	: ui.fileSubmit,
				onChoose : function( ok ) {

					if( ok !== true )
						return;

					ui.fileLine.idle( t('/_admin/recovery/file/busy') );

					Nino.http.sendRequest( Nino.dir+ '/_admin/recovery.php', 'POST', function( xhr ) {

						const response = xhr.responseJSON;

						if( xhr.status !== 200 ) {
							Nino.recovery._refused( xhr.status, response, ui.fileLine, true );
							return;
						}

						ui.fileLine.idle('');
						Nino.adminUi.say( t('/_admin/recovery/file/done')+ Nino.recovery._notice( response ) );
						Nino.recovery._keyDone( xhr.status );
						ui.file.clear( false );
					}, { action : 'recovery/restore-file', data : JSON.stringify( { key : input.value } ), file : file } );
				},
			} );
		},

		/**
		 *	The card that restores: the key of an archive of another project, the archives on the server and the form
		 *	of an archive from a file
		 *
		 *	@return		{Element}
		 */
		_drawRestore : function() {

			const ui = Nino.recovery._ui;
			const data = Nino.recovery._data;
			const card = Nino.recovery._card( 'recovery-restore', 'restore' );
			const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );

			ui.restore = card;

			ui.key = Nino.adminUi.textField( { key : 'key', id : 'recovery-key', type : 'password', autocomplete : 'off', maxLength : 100, label : t('/_admin/recovery/restore/key'), help : t('/_admin/recovery/restore/key-help') } );
			ui.key.querySelector('input').setAttribute( 'autocapitalize', 'off' );
			ui.key.querySelector('input').setAttribute( 'spellcheck', 'false' );

			const show = Nino.adminUi.checkField( { key : 'key-show', id : 'recovery-key-show', label : t('/_admin/recovery/restore/key-show'), onChange : function( checked ) {
				ui.key.querySelector('input').type = checked === true ? 'text' : 'password';
			} } );

			grid.appendChild( ui.key );
			grid.appendChild( show );
			card.appendChild( grid );

			// The archives that are on this server, one row each with the restore of it
			if( data.dates.length === 0 )
				card.appendChild( Nino.adminUi.emptyState( t('/_admin/recovery/restore/none') ) );
			else
				card.appendChild( Nino.adminUi.list( data.dates.map( function( date ) {
					return { key : date, title : date, actions : [ Nino.adminUi.button( t('/_admin/recovery/restore/action'), function( ev ) { Nino.recovery._restore( date, ev.currentTarget ) }, { kind : 'danger', small : true } ) ] };
				} ), { title : t('/_admin/recovery/restore/archives'), level : 3 } ) );

			ui.restoreLine = Nino.adminUi.status( Nino.adminUi.el('p') );

			card.appendChild( ui.restoreLine.element );
			if( data.failed !== '' )
				ui.restoreLine.fail( data.failed );

			// An archive from a file: a form of its own, so that its button asks for it alone
			card.appendChild( Nino.adminUi.sectionTitle( t('/_admin/recovery/file/title'), 3 ) );

			const form = Nino.adminUi.el( 'form', 'nino-admin-fieldgrid' );
			form.id = 'recovery-file';
			form.noValidate = true;

			// A file the server would turn away for its size is turned away at the field, with the way round it in the note
			ui.file = Nino.adminUi.fileField( { key : 'file', id : 'recovery-file-choose', label : t('/_admin/recovery/file/label'), help : t('/_admin/recovery/file/help'), note : t('/_admin/recovery/file/note'),
				accept : '.php,application/octet-stream', maxBytes : data.limit > 0 ? data.limit - Nino.recovery._OVERHEAD : 0 } );
			// Not a submit button: the stylesheet paints every submit button as the main one, and this one takes something away
			ui.fileSubmit = Nino.adminUi.button( t('/_admin/recovery/file/action'), Nino.recovery._restoreFile, { kind : 'danger', id : 'recovery-file-submit' } );

			const foot = Nino.recovery._foot( ui.fileSubmit );
			ui.fileLine = foot.line;

			form.appendChild( ui.file.el );
			form.appendChild( foot.el );
			form.addEventListener( 'submit', Nino.recovery._restoreFile );
			card.appendChild( form );

			return card;
		},

		/**
		 *	The field of the account a password is set for: a select, so that a typo cannot name an account that is not there
		 *
		 *	@return		{Element}
		 */
		_accountField : function() {

			const users = Nino.recovery._data.users;

			return Nino.adminUi.selectField( { key : 'reset-mail', id : 'recovery-reset-mail', label : t('/_admin/recovery/reset/account'),
				options : users.length === 0 ? [ { value : '', label : t('/_admin/recovery/reset/accounts-none') } ] : users.map( function( mail ) { return { value : mail, label : mail } } ), value : users.length === 0 ? '' : users[0] } );
		},

		_onReset : function( ev ) {

			ev.preventDefault();

			const ui = Nino.recovery._ui;
			const mail = ui.reset.querySelector('#recovery-reset-mail');
			const pw = ui.resetPw.querySelector('input');

			Nino.adminUi.clearFieldErrors( ui.reset );
			ui.resetLine.idle('');

			if( mail.value === '' )
				return;

			const problem = Nino.recovery._passwordProblem( pw.value, t('/_admin/recovery/reset/pw') );

			if( problem !== '' ) {
				Nino.adminUi.fieldError( ui.resetPw, pw, 'recovery-reset-error', problem );
				pw.focus();
				return;
			}

			ui.resetLine.idle( t('/_admin/recovery/reset/busy') );

			Nino.recovery._apiCall( 'reset', { mail : mail.value, pw : pw.value }, function( status, response ) {

				if( status !== 200 ) {

					// A refusal about the password is the field's, and the line says nothing more
					if( Nino.recovery._isPasswordRefusal( response ) === true ) {
						ui.resetLine.idle('');
						Nino.adminUi.fieldError( ui.resetPw, pw, 'recovery-reset-error', Nino.recovery._failure( status, response ) );
						pw.focus();
						return;
					}

					ui.resetLine.fail( Nino.recovery._failure( status, response ) );
					return;
				}

				ui.resetLine.idle('');
				Nino.adminUi.say( t('/_admin/recovery/reset/done') );
				pw.value = '';
			} );
		},

		/**
		 *	What is wrong with a password, as a sentence - empty, or shorter than the server takes - or ''
		 *
		 *	@param		{string}		value
		 *	@param		{string}		name				The name of the field, for the sentence of an empty one
		 *
		 *	@return		{string}
		 */
		_passwordProblem : function( value, name ) {

			if( value === '' )
				return Nino.adminUi.format( t('/_admin/common/error/missing'), name );

			return value.length < Nino.recovery._min() ? t( '/_admin/recovery/error/short', Nino.recovery._min() ) : '';
		},

		_drawReset : function() {

			const ui = Nino.recovery._ui;
			const card = Nino.recovery._card( 'recovery-reset-card', 'reset' );
			const form = Nino.adminUi.el( 'form', 'nino-admin-fieldgrid' );

			form.id = 'recovery-reset';
			form.noValidate = true;

			ui.resetAccount = Nino.recovery._accountField();
			ui.resetPw = Nino.adminUi.textField( { key : 'reset-pw', id : 'recovery-reset-pw', type : 'password', autocomplete : 'new-password', required : true, label : t('/_admin/recovery/reset/pw'), help : t( '/_admin/recovery/reset/pw-help', Nino.recovery._min(), Nino.recovery._max() ) } );
			ui.resetSubmit = Nino.adminUi.button( t('/_admin/recovery/reset/action'), null, { kind : 'primary', type : 'submit', id : 'recovery-reset-submit', disabled : Nino.recovery._data.users.length === 0 } );

			const foot = Nino.recovery._foot( ui.resetSubmit );
			ui.resetLine = foot.line;

			form.appendChild( ui.resetAccount );
			form.appendChild( ui.resetPw );
			form.appendChild( foot.el );
			form.addEventListener( 'submit', Nino.recovery._onReset );
			card.appendChild( form );
			ui.reset = form;

			return card;
		},

		_onCreate : function( ev ) {

			ev.preventDefault();

			const ui = Nino.recovery._ui;
			const mail = ui.createMail.querySelector('input');
			const pw = ui.createPw.querySelector('input');
			const repeat = ui.createRepeat.querySelector('input');

			Nino.adminUi.clearFieldErrors( ui.create );
			ui.createLine.idle('');

			const address = mail.value.trim();
			const problems = [
				[ address === '' ? Nino.adminUi.format( t('/_admin/common/error/missing'), t('/_admin/recovery/create/mail') ) : '', ui.createMail, mail ],
				[ Nino.recovery._passwordProblem( pw.value, t('/_admin/recovery/create/pw') ), ui.createPw, pw ],
				[ repeat.value === '' ? Nino.adminUi.format( t('/_admin/common/error/missing'), t('/_admin/recovery/create/repeat') ) : ( pw.value !== repeat.value ? t('/_admin/recovery/error/mismatch') : '' ), ui.createRepeat, repeat ],
			].filter( function( problem ) { return problem[0] !== '' } );

			if( problems.length > 0 ) {
				problems.forEach( function( problem, at ) { Nino.adminUi.fieldError( problem[1], problem[2], 'recovery-create-error-'+ at, problem[0] ) } );
				problems[0][2].focus();
				return;
			}

			// The one action here that cannot be taken back from this page, so it is asked about - and the server wants the answer as well
			Nino.adminUi.confirm( 'destructive', {
				title		: t('/_admin/recovery/create/confirm-title'),
				message	: t( '/_admin/recovery/create/confirm', address ),
				yes			: t('/_admin/recovery/create/confirm-title'),
				no			: t('/_admin/common/label/cancel'),
				kind		: 'danger',
				opener	: ui.createSubmit,
				onChoose : function( ok ) {

					if( ok !== true )
						return;

					ui.createLine.idle( t('/_admin/recovery/create/busy') );

					Nino.recovery._apiCall( 'create', { mail : address, pw : pw.value, confirm : true }, function( status, response ) {

						if( status !== 200 ) {

							const code = response !== null && typeof response === 'object' && typeof response.code === 'string' ? response.code : '';

							ui.createLine.idle('');

							// The address is the field a refusal about it is about
							if( code === 'recovery_mail' || code === 'recovery_exists' ) {
								Nino.adminUi.fieldError( ui.createMail, mail, 'recovery-create-error-mail', Nino.recovery._failure( status, response ) );
								mail.focus();
								return;
							}

							// ...and so is one about the password
							if( Nino.recovery._isPasswordRefusal( response ) === true ) {
								Nino.adminUi.fieldError( ui.createPw, pw, 'recovery-create-error-pw', Nino.recovery._failure( status, response ) );
								pw.focus();
								return;
							}

							ui.createLine.fail( Nino.recovery._failure( status, response ) );
							return;
						}

						ui.createLine.idle('');
						Nino.adminUi.say( t('/_admin/recovery/create/done') );
						pw.value = '';
						repeat.value = '';

						// So the new account is there to pick under "Set a password"
						Nino.recovery._load( false );
					} );
				},
			} );
		},

		_drawCreate : function() {

			const ui = Nino.recovery._ui;
			const card = Nino.recovery._card( 'recovery-create-card', 'create' );
			const form = Nino.adminUi.el( 'form', 'nino-admin-fieldgrid' );

			form.id = 'recovery-create';
			form.noValidate = true;

			ui.createMail = Nino.adminUi.textField( { key : 'create-mail', id : 'recovery-create-mail', type : 'email', autocomplete : 'off', required : true, label : t('/_admin/recovery/create/mail') } );
			ui.createPw = Nino.adminUi.textField( { key : 'create-pw', id : 'recovery-create-pw', type : 'password', autocomplete : 'new-password', required : true, label : t('/_admin/recovery/create/pw'), help : t( '/_admin/recovery/create/pw-help', Nino.recovery._min(), Nino.recovery._max() ) } );
			ui.createRepeat = Nino.adminUi.textField( { key : 'create-repeat', id : 'recovery-create-repeat', type : 'password', autocomplete : 'new-password', required : true, label : t('/_admin/recovery/create/repeat') } );
			ui.createSubmit = Nino.adminUi.button( t('/_admin/recovery/create/action'), null, { kind : 'primary', type : 'submit', id : 'recovery-create-submit' } );

			const foot = Nino.recovery._foot( ui.createSubmit );
			ui.createLine = foot.line;

			form.appendChild( ui.createMail );
			form.appendChild( ui.createPw );
			form.appendChild( ui.createRepeat );
			form.appendChild( foot.el );
			form.addEventListener( 'submit', Nino.recovery._onCreate );
			card.appendChild( form );
			ui.create = form;

			return card;
		},

		/**
		 *	The foot of the page: the way back to the workbench and the end of the recovery session
		 *
		 *	@return		void
		 */
		_drawFoot : function() {

			const foot = dc.getElementById('recovery-foot');

			foot.innerHTML = '';

			const back = Nino.adminUi.el( 'a', '', t('/_admin/recovery/label/back') );
			back.id = 'recovery-back';
			back.href = Nino.dir+ '/_admin/';

			foot.appendChild( back );
			foot.appendChild( Nino.adminUi.button( t('/_admin/recovery/label/close'), function() {
				Nino.recovery._apiCall( 'logout', {}, function() { wn.location.reload() } );
			}, { kind : 'link', id : 'recovery-logout' } ) );
		},

		/**
		 *	Draw the tools - the three cards and the foot - from what the last list said, in the language of the page.
		 *	What was typed stays where the language is changed
		 *
		 *	@return		void
		 */
		_drawTools : function() {

			const sections = dc.getElementById('recovery-sections');

			Nino.pageText.keep( sections, function() {

				Nino.recovery._ui = {};
				sections.innerHTML = '';
				sections.appendChild( Nino.recovery._drawRestore() );
				sections.appendChild( Nino.recovery._drawReset() );
				sections.appendChild( Nino.recovery._drawCreate() );
			} );

			Nino.recovery._drawFoot();
		},

		/**
		 *	The tools: load the backup dates and known accounts, draw the cards. Shown by the server
		 *	only once the secret was verified (see page-recovery.tpl's [[...]] gate)
		 *
		 *	@return		void
		 */
		onReadyTools : function() {

			const tools = dc.getElementById('recovery-tools');
			if( tools === null || tools.dataset.open !== '1' )
				return;

			dc.getElementById('recovery-login').classList.add('admin-hidden');
			dc.getElementById('recovery-wrap').classList.remove('nino-admin-auth--form');
			dc.getElementById('recovery-wrap').classList.add('nino-admin-auth--tools');
			tools.classList.remove('admin-hidden');
			Nino.recovery._load( true );
		},

		/**
		 *	Ask for the backup dates and the accounts, and draw both: the restore buttons and the accounts a
		 *	password can be set for
		 *
		 *	@param		{boolean}		first					Whether this is the first load: the cards are drawn, else only the accounts are refreshed
		 *
		 *	@return		void
		 */
		_load : function( first ) {

			Nino.recovery._apiCall( 'list', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.recovery._data = { dates : [], users : [], limit : 0, failed : Nino.recovery._failure( status, response ) };

					if( first === true ) {
						Nino.recovery._drawTools();
						Nino.recovery._arrive();
					}
					return;
				}

				Nino.recovery._data = {
					dates		: Array.isArray( response.dates ) === true ? response.dates : [],
					users		: Array.isArray( response.users ) === true ? response.users : [],
					limit		: typeof response.uploadLimit === 'number' && response.uploadLimit > 0 ? response.uploadLimit : 0,
					failed	: '',
				};

				if( first === true ) {
					Nino.recovery._drawTools();
					Nino.recovery._arrive();
					return;
				}

				// The accounts only: what was typed in the cards stays, and the new account is there to pick
				const ui = Nino.recovery._ui;
				const fresh = Nino.recovery._accountField();

				ui.resetAccount.replaceWith( fresh );
				ui.resetAccount = fresh;
				ui.resetSubmit.disabled = Nino.recovery._data.users.length === 0;
			} );
		},

		/**
		 *	The view that was opened has the focus: its heading, which takes it without being a stop of the keyboard. Else the page
		 *	that loaded again after the right secret would leave it on the body
		 *
		 *	@return		void
		 */
		_arrive : function() {

			const heading = dc.getElementById('recovery-tools').querySelector('h1');

			if( heading !== null )
				heading.focus();
		},

		/**
		 *	Draw what the page writes by hand again, in the language that was chosen
		 *
		 *	@return		void
		 */
		_retext : function() {

			const tools = dc.getElementById('recovery-tools');

			if( dc.getElementById('recovery-login').classList.contains('admin-hidden') === false ) {
				Nino.pageText.keep( dc.getElementById('recovery-login-fields'), Nino.recovery._drawLogin );
				return;
			}

			if( tools !== null && Nino.recovery._ui !== null )
				Nino.recovery._drawTools();
		},

		onReady : function() {

			const form = dc.getElementById('recovery-login');

			if( form === null )
				return;

			Nino.recovery._drawLogin();
			form.addEventListener( 'submit', Nino.recovery._onLogin );
			Nino.pageText.onChange( Nino.recovery._retext );
			Nino.recovery.onReadyTools();
		},
	} );

	Nino.events.bindCallback( 'ready', Nino.recovery.onReady );

})(window, document, document.documentElement, document.body);
