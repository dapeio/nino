

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	users.js								Admin "User" panel: change your own mail/password (with
 *													current-password confirmation), or - given the manage
 *													permission - anyone's, plus (manage-only, no self-service)
 *													per-module permissions. New users can't be created or
 *													deleted here - sessions/tries/status stay a developer-only,
 *													direct-json task.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.editor = wn.Nino.editor || {};

	Nino.editor.users = {

		_users				: [],
		_currentUser	: null,
		_canManage		: false,
		_permOptions	: [],
		_loading			: false,
		_ready				: false,

		/**
		 *	Load every user the current user may see and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('users-list') === null || Nino.editor.users._loading === true || Nino.editor.users._ready === true )
				return;

			Nino.editor.users._loading = true;

			Nino.editor.users._apiCall( 'list', {}, function( status, response ) {
				Nino.editor.users._loading = false;
				if( status !== 200 || response === null )
					return Nino.editor.users._showError( dc.getElementById('users-list'), status, response );

				// Capture the hash before any _show*() call below can overwrite it -
				// _showList() would otherwise wipe the deep-link part it's trying to restore
				const hash = Nino.editor.router.current();

				Nino.editor.users._users = response.users;
				Nino.editor.users._canManage = response.canManage;
				Nino.editor.users._permOptions = response.permOptions;
				Nino.editor.users._renderList( response.users );
				Nino.editor.users._ready = true;

				const target = hash.panel === 'users' && hash.parts.length > 0
					? Nino.editor.users._users.find( function( u ) { return u.mail === hash.parts[0] } )
					: undefined;

				if( target !== undefined )
					Nino.editor.users._openUser( target.mail );
				else
					Nino.editor.users._showList();
			} );
		},

		/**
		 *	Re-apply whatever drill-down level this panel is currently on -
		 *	called when the user switches TO this tab, so the hash (only ever
		 *	written by router.set() while this panel is the visible one) gets
		 *	synced to reality instead of staying stale from before the switch
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.editor.users._ready === false ) {
				Nino.editor.users.init();
				return;
			}

			if( dc.getElementById('users-form').classList.contains('editor-hidden') === false )
				return Nino.editor.users._showForm();

			Nino.editor.users._showList();
		},

		/**
		 *	Call a users/* admin action - see elements.js for why /_editor/ (trailing slash)
		 *
		 *	@param		{string}		endpoint			Action name (eg. "save", becomes "users/save")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_editor/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'users/'+ endpoint, data : JSON.stringify( payload ) } );
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
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'editor-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/users/error/load') );
			container.appendChild( p );
		},

		/**
		 *	Drill-down navigation: list -> form. The main System/Texte/Elemente
		 *	bar stays visible throughout.
		 *
		 *	@return		void
		 */
		_showList : function() {
			dc.getElementById('users-list').classList.remove('editor-hidden');
			dc.getElementById('users-form').classList.add('editor-hidden');
			Nino.editor.router.set( 'users', [] );
		},

		_showForm : function() {
			dc.getElementById('users-list').classList.add('editor-hidden');
			dc.getElementById('users-form').classList.remove('editor-hidden');
			Nino.editor.router.set( 'users', [ Nino.editor.users._currentUser.mail ] );
		},

		/**
		 *	Render the user list
		 *
		 *	@param		{Array}		users					[ { mail, isSelf }, ... ]
		 *
		 *	@return		void
		 */
		_renderList : function( users ) {

			const wrap = dc.getElementById('users-list');
			wrap.innerHTML = '';

			const ul = dc.createElement('ul');
			users.forEach( function( user ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';
				link.textContent = user.mail + ( user.isSelf === true ? ' ('+ Nino.content.getText('/_editor/users/label/you')+ ')' : '' );
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.editor.users._openUser( user.mail ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );
		},

		/**
		 *	Open one user's edit form
		 *
		 *	@param		{string}	mail
		 *
		 *	@return		void
		 */
		_openUser : function( mail ) {

			const user = Nino.editor.users._users.find( function( u ) { return u.mail === mail } );
			if( user === undefined )
				return;

			Nino.editor.users._currentUser = user;
			Nino.editor.users._renderForm();
			Nino.editor.users._showForm();
		},

		/**
		 *	Render the edit form: mail, a new password (optional, leave blank
		 *	to keep it unchanged), and - only when editing yourself - your
		 *	current password to confirm the change
		 *
		 *	@return		void
		 */
		_renderForm : function() {

			const user = Nino.editor.users._currentUser;

			const wrap = dc.getElementById('users-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = Nino.content.getText('/_editor/users/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.editor.users._showList() } );
			wrap.appendChild( backLink );

			const form = dc.createElement('form');
			form.id = 'users-edit-form';


			const usersWrap = dc.createElement('fieldset');
			usersWrap.id = 'users-form-global';
			const legend = dc.createElement('legend');
			legend.textContent = user.mail;
			usersWrap.appendChild( legend );
			wrap.appendChild(usersWrap)

			const mailLabel = dc.createElement('label');
			mailLabel.className = 'editor-field';
			const mailSpan = dc.createElement('span');
			mailSpan.textContent = Nino.content.getText('/_editor/users/label/mail');
			mailLabel.appendChild( mailSpan );
			const mailInput = dc.createElement('input');
			mailInput.type = 'email';
			mailInput.id = 'users-form-mail';
			mailInput.required = true;
			mailInput.value = user.mail;
			mailLabel.appendChild( mailInput );
			form.appendChild( mailLabel );

			const pwLabel = dc.createElement('label');
			pwLabel.className = 'editor-field';
			const pwSpan = dc.createElement('span');
			pwSpan.textContent = Nino.content.getText('/_editor/users/label/newpw');
			pwLabel.appendChild( pwSpan );
			const pwInput = dc.createElement('input');
			pwInput.type = 'password';
			pwInput.id = 'users-form-pw';
			pwInput.minLength = 8;
			pwInput.autocomplete = 'new-password';
			pwLabel.appendChild( pwInput );
			form.appendChild( pwLabel );

			if( user.isSelf === true ) {
				const curLabel = dc.createElement('label');
				curLabel.className = 'editor-field';
				const curSpan = dc.createElement('span');
				curSpan.textContent = Nino.content.getText('/_editor/users/label/currentpw');
				curLabel.appendChild( curSpan );
				const curInput = dc.createElement('input');
				curInput.type = 'password';
				curInput.id = 'users-form-currentpw';
				curInput.required = true;
				curInput.autocomplete = 'current-password';
				curLabel.appendChild( curInput );
				form.appendChild( curLabel );
			}

			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_editor/users/label/save');
			actions.appendChild( saveBtn );

			const logoutAllBtn = dc.createElement('button');
			logoutAllBtn.type = 'button';
			logoutAllBtn.textContent = Nino.content.getText('/_editor/users/label/logoutall');
			logoutAllBtn.addEventListener( 'click', function() { Nino.editor.users._logoutAll() } );
			actions.appendChild( logoutAllBtn );

			const msg = dc.createElement('p');
			msg.id = 'users-form-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.editor.users._save() } );

			usersWrap.appendChild( form );

			if( Nino.editor.users._canManage === true )
				wrap.appendChild( Nino.editor.users._renderPermissions() );
		},

		/**
		 *	Render the permissions fieldset: one checkbox per KNOWN_PERMS
		 *	entry (see _editor/Editor.php's Users class), plus a "full access"
		 *	checkbox that stands in for all of them at once ('/*'). Manager-
		 *	only (canManage), same gate the backend enforces independently -
		 *	this is just UI, apiSetPermissions() never trusts it
		 *
		 *	@return		{Element}
		 */
		_renderPermissions : function() {

			const user = Nino.editor.users._currentUser;
			const hasFullAccess = user.perms.indexOf('/*') !== -1;

			const wrap = dc.createElement('fieldset');
			wrap.id = 'users-form-permissions';
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_editor/users/label/permissions');
			wrap.appendChild( legend );

			const form = dc.createElement('form');
			form.id = 'users-permissions-form';

			const fullLabel = dc.createElement('label');
			fullLabel.className = 'editor-checkbox-field';
			const fullCheck = dc.createElement('input');
			fullCheck.type = 'checkbox';
			fullCheck.id = 'users-permissions-full';
			fullCheck.checked = hasFullAccess;
			fullLabel.appendChild( fullCheck );
			fullLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_editor/users/label/permissions-full') ) );
			form.appendChild( fullLabel );

			const checkboxes = [];
			Nino.editor.users._permOptions.forEach( function( option ) {

				const label = dc.createElement('label');
				label.className = 'editor-checkbox-field';
				const check = dc.createElement('input');
				check.type = 'checkbox';
				check.value = option.perm;
				check.checked = hasFullAccess || user.perms.indexOf( option.perm ) !== -1;
				check.disabled = hasFullAccess;
				checkboxes.push( check );
				label.appendChild( check );
				label.appendChild( dc.createTextNode( ' '+ Nino.content.getText( option.label ) ) );
				form.appendChild( label );
			} );

			fullCheck.addEventListener( 'change', function() {
				checkboxes.forEach( function( check ) { check.disabled = fullCheck.checked } );
			} );

			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_editor/users/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'users-permissions-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.editor.users._savePermissions( fullCheck, checkboxes ) } );

			wrap.appendChild( form );

			return wrap;
		},

		/**
		 *	Save the current user's permissions
		 *
		 *	@param		{Element}		fullCheck			The "full access" checkbox
		 *	@param		{Element[]}	checkboxes		The individual permission checkboxes
		 *
		 *	@return		void
		 */
		_savePermissions : function( fullCheck, checkboxes ) {

			const user = Nino.editor.users._currentUser;
			const msg 	= dc.getElementById('users-permissions-msg');

			const perms = fullCheck.checked
				? [ '/*' ]
				: checkboxes.filter( function( c ) { return c.checked } ).map( function( c ) { return c.value } );

			msg.textContent = Nino.content.getText('/_editor/users/msg/pending');

			Nino.editor.users._apiCall( 'permissions', { username : user.mail, perms : perms }, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/users/error/save') );
					return;
				}

				user.perms = response.perms;
				msg.textContent = Nino.content.getText('/_editor/users/msg/saved');
			} );
		},

		/**
		 *	Save the current user's mail/password
		 *
		 *	@return		void
		 */
		_save : function() {

			const user = Nino.editor.users._currentUser;
			const mail = dc.getElementById('users-form-mail').value.trim();
			const pw 	 = dc.getElementById('users-form-pw').value;
			const msg 	= dc.getElementById('users-form-msg');

			const payload = { username : user.mail, mail : mail, pw : pw };

			if( user.isSelf === true )
				payload.currentPassword = dc.getElementById('users-form-currentpw').value;

			msg.textContent = Nino.content.getText('/_editor/users/msg/pending');

			Nino.editor.users._apiCall( 'save', payload, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/users/error/save') );
					return;
				}

				user.mail = response.mail;
				dc.getElementById('users-form-pw').value = '';
				const curInput = dc.getElementById('users-form-currentpw');
				if( curInput !== null )
					curInput.value = '';

				msg.textContent = Nino.content.getText('/_editor/users/msg/saved');
				Nino.editor.router.set( 'users', [ user.mail ] );

				// Refresh the list in the background so a renamed mail is reflected there too
				Nino.editor.users._apiCall( 'list', {}, function( listStatus, listResponse ) {
					if( listStatus === 200 && listResponse !== null ) {
						Nino.editor.users._users = listResponse.users;
						Nino.editor.users._renderList( listResponse.users );
					}
				} );
			} );
		},

		/**
		 *	Log the current user out of every session, after confirmation
		 *
		 *	@return		void
		 */
		_logoutAll : function() {

			if( wn.confirm( Nino.content.getText('/_editor/users/confirm/logoutall') ) === false )
				return;

			const user = Nino.editor.users._currentUser;
			const msg 	= dc.getElementById('users-form-msg');

			Nino.editor.users._apiCall( 'logoutall', { username : user.mail }, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/users/error/save') );
					return;
				}

				// Logging out yourself invalidates the current session - reload straight to the login form
				if( response.loggedOutSelf === true ) {
					wn.location.replace( '/_editor' );
					return;
				}

				msg.textContent = Nino.content.getText('/_editor/users/msg/loggedout');
			} );
		},
	};

})(window, document, document.documentElement, document.body);
