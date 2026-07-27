

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

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.users = {

		_users				: [],
		_currentUser	: null,
		_canManage		: false,
		_permOptions	: [],
		_ready				: false,

		/**
		 *	Load every user the current user may see and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('users-list') === null )
				return;

			Nino.admin.users._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.users._showError( dc.getElementById('users-list'), status, response );

				// Capture the hash before any _show*() call below can overwrite it -
				// _showList() would otherwise wipe the deep-link part it's trying to restore
				const hash = Nino.admin.router.current();

				Nino.admin.users._users = response.users;
				Nino.admin.users._canManage = response.canManage;
				Nino.admin.users._permOptions = response.permOptions;
				Nino.admin.users._renderList( response.users );
				Nino.admin.users._ready = true;

				const target = hash.panel === 'users' && hash.parts.length > 0
					? Nino.admin.users._users.find( function( u ) { return u.mail === hash.parts[0] } )
					: undefined;

				if( target !== undefined )
					Nino.admin.users._openUser( target.mail );
				else
					Nino.admin.users._showList();
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

			if( Nino.admin.users._ready === false )
				return;

			if( dc.getElementById('users-form').classList.contains('admin-hidden') === false )
				return Nino.admin.users._showForm();

			Nino.admin.users._showList();
		},

		/**
		 *	Call a users/* admin action - see elements.js for why /_admin/ (trailing slash)
		 *
		 *	@param		{string}		endpoint			Action name (eg. "save", becomes "users/save")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
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
			p.className = 'admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/users/error/load') );
			container.appendChild( p );
		},

		/**
		 *	Drill-down navigation: list -> form. The main System/Texte/Elemente
		 *	bar stays visible throughout.
		 *
		 *	@return		void
		 */
		_showList : function() {
			dc.getElementById('users-list').classList.remove('admin-hidden');
			dc.getElementById('users-form').classList.add('admin-hidden');
			Nino.admin.router.set( 'users', [] );
		},

		_showForm : function() {
			dc.getElementById('users-list').classList.add('admin-hidden');
			dc.getElementById('users-form').classList.remove('admin-hidden');
			Nino.admin.router.set( 'users', [ Nino.admin.users._currentUser.mail ] );
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
				link.textContent = user.mail + ( user.isSelf === true ? ' ('+ Nino.content.getText('/_admin/users/label/you')+ ')' : '' );
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.users._openUser( user.mail ) } );
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

			const user = Nino.admin.users._users.find( function( u ) { return u.mail === mail } );
			if( user === undefined )
				return;

			Nino.admin.users._currentUser = user;
			Nino.admin.users._renderForm();
			Nino.admin.users._showForm();
		},

		/**
		 *	Render the edit form: mail, a new password (optional, leave blank
		 *	to keep it unchanged), and - only when editing yourself - your
		 *	current password to confirm the change
		 *
		 *	@return		void
		 */
		_renderForm : function() {

			const user = Nino.admin.users._currentUser;

			const wrap = dc.getElementById('users-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = Nino.content.getText('/_admin/users/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.users._showList() } );
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
			mailLabel.className = 'admin-field';
			const mailSpan = dc.createElement('span');
			mailSpan.textContent = Nino.content.getText('/_admin/users/label/mail');
			mailLabel.appendChild( mailSpan );
			const mailInput = dc.createElement('input');
			mailInput.type = 'email';
			mailInput.id = 'users-form-mail';
			mailInput.required = true;
			mailInput.value = user.mail;
			mailLabel.appendChild( mailInput );
			form.appendChild( mailLabel );

			const pwLabel = dc.createElement('label');
			pwLabel.className = 'admin-field';
			const pwSpan = dc.createElement('span');
			pwSpan.textContent = Nino.content.getText('/_admin/users/label/newpw');
			pwLabel.appendChild( pwSpan );
			const pwInput = dc.createElement('input');
			pwInput.type = 'password';
			pwInput.id = 'users-form-pw';
			pwInput.autocomplete = 'new-password';
			pwLabel.appendChild( pwInput );
			form.appendChild( pwLabel );

			if( user.isSelf === true ) {
				const curLabel = dc.createElement('label');
				curLabel.className = 'admin-field';
				const curSpan = dc.createElement('span');
				curSpan.textContent = Nino.content.getText('/_admin/users/label/currentpw');
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
			actions.className = 'admin-form-actions';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/users/label/save');
			actions.appendChild( saveBtn );

			const logoutAllBtn = dc.createElement('button');
			logoutAllBtn.type = 'button';
			logoutAllBtn.textContent = Nino.content.getText('/_admin/users/label/logoutall');
			logoutAllBtn.addEventListener( 'click', function() { Nino.admin.users._logoutAll() } );
			actions.appendChild( logoutAllBtn );

			const msg = dc.createElement('p');
			msg.id = 'users-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.users._save() } );

			usersWrap.appendChild( form );

			if( Nino.admin.users._canManage === true )
				wrap.appendChild( Nino.admin.users._renderPermissions() );
		},

		/**
		 *	Render the permissions fieldset: one checkbox per KNOWN_PERMS
		 *	entry (see _admin/Admin.php's Users class), plus a "full access"
		 *	checkbox that stands in for all of them at once ('/*'). Manager-
		 *	only (canManage), same gate the backend enforces independently -
		 *	this is just UI, apiSetPermissions() never trusts it
		 *
		 *	@return		{Element}
		 */
		_renderPermissions : function() {

			const user = Nino.admin.users._currentUser;
			const hasFullAccess = user.perms.indexOf('/*') !== -1;

			const wrap = dc.createElement('fieldset');
			wrap.id = 'users-form-permissions';
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/users/label/permissions');
			wrap.appendChild( legend );

			const form = dc.createElement('form');
			form.id = 'users-permissions-form';

			const fullLabel = dc.createElement('label');
			fullLabel.className = 'admin-checkbox-field';
			const fullCheck = dc.createElement('input');
			fullCheck.type = 'checkbox';
			fullCheck.id = 'users-permissions-full';
			fullCheck.checked = hasFullAccess;
			fullLabel.appendChild( fullCheck );
			fullLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/users/label/permissions-full') ) );
			form.appendChild( fullLabel );

			const checkboxes = [];
			Nino.admin.users._permOptions.forEach( function( option ) {

				const label = dc.createElement('label');
				label.className = 'admin-checkbox-field';
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
			actions.className = 'admin-form-actions';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/users/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'users-permissions-msg';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.users._savePermissions( fullCheck, checkboxes ) } );

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

			const user = Nino.admin.users._currentUser;
			const msg 	= dc.getElementById('users-permissions-msg');

			const perms = fullCheck.checked
				? [ '/*' ]
				: checkboxes.filter( function( c ) { return c.checked } ).map( function( c ) { return c.value } );

			msg.textContent = Nino.content.getText('/_admin/users/msg/pending');

			Nino.admin.users._apiCall( 'permissions', { username : user.mail, perms : perms }, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/users/error/save') );
					return;
				}

				user.perms = response.perms;
				msg.textContent = Nino.content.getText('/_admin/users/msg/saved');
			} );
		},

		/**
		 *	Save the current user's mail/password
		 *
		 *	@return		void
		 */
		_save : function() {

			const user = Nino.admin.users._currentUser;
			const mail = dc.getElementById('users-form-mail').value.trim();
			const pw 	 = dc.getElementById('users-form-pw').value;
			const msg 	= dc.getElementById('users-form-msg');

			const payload = { username : user.mail, mail : mail, pw : pw };

			if( user.isSelf === true )
				payload.currentPassword = dc.getElementById('users-form-currentpw').value;

			msg.textContent = Nino.content.getText('/_admin/users/msg/pending');

			Nino.admin.users._apiCall( 'save', payload, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/users/error/save') );
					return;
				}

				user.mail = response.mail;
				dc.getElementById('users-form-pw').value = '';
				const curInput = dc.getElementById('users-form-currentpw');
				if( curInput !== null )
					curInput.value = '';

				msg.textContent = Nino.content.getText('/_admin/users/msg/saved');
				Nino.admin.router.set( 'users', [ user.mail ] );

				// Refresh the list in the background so a renamed mail is reflected there too
				Nino.admin.users._apiCall( 'list', {}, function( listStatus, listResponse ) {
					if( listStatus === 200 && listResponse !== null ) {
						Nino.admin.users._users = listResponse.users;
						Nino.admin.users._renderList( listResponse.users );
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

			if( wn.confirm( Nino.content.getText('/_admin/users/confirm/logoutall') ) === false )
				return;

			const user = Nino.admin.users._currentUser;
			const msg 	= dc.getElementById('users-form-msg');

			Nino.admin.users._apiCall( 'logoutall', { username : user.mail }, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/users/error/save') );
					return;
				}

				// Logging out yourself invalidates the current session - reload straight to the login form
				if( response.loggedOutSelf === true ) {
					wn.location.replace( '/_admin' );
					return;
				}

				msg.textContent = Nino.content.getText('/_admin/users/msg/loggedout');
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.users.init );

})(window, document, document.documentElement, document.body);
