/**
 *	Nino										A compact filesystembased php framework
 *	recovery.js							The break-glass page (_admin/recovery.php): one secret, then
 *													a list of backups to restore and a form to give an account
 *													a password. Plain tags and literal text on purpose - this
 *													has to work when the project's own text and bundles do not.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */
( function(wn,dc,dE,bd) {
	wn.Nino.recovery = {
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
			Nino.http.sendRequest( '/_admin/recovery.php', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'recovery/'+ endpoint, data : JSON.stringify( payload ) } );
		},
		_error : function( status, response, fallback ) {
			return '('+ status+ ') '+ ( ( response && response.error ) ? response.error : fallback );
		},
		/**
		 *	The secret form - a reload after success shows the tools, since
		 *	the [csrf] field has been rotated on the server
		 *
		 *	@return		void
		 */
		onReadyLogin : function() {
			const form = dc.getElementById('recovery-login');
			if( form === null )
				return;
			const msg = dc.getElementById('recovery-login-msg');
			const input = dc.getElementById('recovery-input-pw');
			form.addEventListener( 'submit', function( ev ) {
				ev.preventDefault();
				msg.textContent = 'Checking …';
				Nino.recovery._apiCall( 'login', { password : input.value }, function( status, response ) {
					if( status !== 200 ) {
						msg.textContent = status === 429 ? 'Too many attempts - try again later.' : 'Wrong password.';
						input.value = '';
						input.focus();
						return;
					}
					wn.location.reload();
				} );
			} );
			input.focus();
		},
		/**
		 *	The tools: load the backup dates and known accounts, wire the
		 *	restore buttons and the reset form. Shown by the server only once
		 *	the secret was verified (see page-recovery.tpl's [[...]] gate)
		 *
		 *	@return		void
		 */
		onReadyTools : function() {
			const tools = dc.getElementById('recovery-tools');
			if( tools === null || tools.dataset.open !== '1' )
				return;
			dc.getElementById('recovery-login').classList.add('admin-hidden');
			tools.classList.remove('admin-hidden');
			const list = dc.getElementById('recovery-dates');
			const restoreMsg = dc.getElementById('recovery-restore-msg');
			Nino.recovery._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null ) {
					restoreMsg.textContent = Nino.recovery._error( status, response, 'Could not load the backups.' );
					return;
				}
				list.innerHTML = '';
				if( response.dates.length === 0 ) {
					const li = dc.createElement('li');
					li.textContent = 'No backups on file.';
					list.appendChild( li );
				}
				response.dates.forEach( function( date ) {
					const li = dc.createElement('li');
					const span = dc.createElement('span');
					span.textContent = date;
					li.appendChild( span );
					const btn = dc.createElement('button');
					btn.type = 'button';
					btn.className = 'nino-admin-btn-danger';
					btn.textContent = 'Restore';
					btn.addEventListener( 'click', function() {
						if( wn.confirm( 'Restore the backup of '+ date+ '? The current state is snapshotted first.' ) === false )
							return;
						restoreMsg.textContent = 'Restoring …';
						Nino.recovery._apiCall( 'restore', { date : date }, function( status, response ) {
							restoreMsg.textContent = status === 200 ? 'Restored '+ response.restoredDate+ '.' : Nino.recovery._error( status, response, 'Restore failed.' );
						} );
					} );
					li.appendChild( btn );
					list.appendChild( li );
				} );
				const users = dc.getElementById('recovery-users');
				users.innerHTML = '';
				( response.users || [] ).forEach( function( mail ) {
					const option = dc.createElement('option');
					option.value = mail;
					users.appendChild( option );
				} );
			} );
			const reset = dc.getElementById('recovery-reset');
			const resetMsg = dc.getElementById('recovery-reset-msg');
			reset.addEventListener( 'submit', function( ev ) {
				ev.preventDefault();
				resetMsg.textContent = 'Saving …';
				Nino.recovery._apiCall( 'reset', { mail : dc.getElementById('recovery-reset-mail').value.trim(), pw : dc.getElementById('recovery-reset-pw').value }, function( status, response ) {
					if( status !== 200 ) {
						resetMsg.textContent = Nino.recovery._error( status, response, 'Could not set the password.' );
						return;
					}
					resetMsg.textContent = response.created === true ? 'Account created with full access.' : 'Password set, every session of this account logged out.';
					dc.getElementById('recovery-reset-pw').value = '';
				} );
			} );
			dc.getElementById('recovery-logout').addEventListener( 'click', function( ev ) {
				ev.preventDefault();
				Nino.recovery._apiCall( 'logout', {}, function() { wn.location.reload() } );
			} );
		},
	};
	Nino.events.bindCallback( 'ready', function() {
		Nino.recovery.onReadyTools();
		Nino.recovery.onReadyLogin();
	} );
})(window, document, document.documentElement, document.body);
