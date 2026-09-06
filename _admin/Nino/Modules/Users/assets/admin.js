

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js								"Users" panel: change your own mail/password (with
 *													current-password confirmation), or - given the manage
 *													permission - anyone's, create accounts with a role,
 *													change which role an account holds (never your own)
 *													and delete accounts. What a role grants is the roles
 *													tab beside this one (roles.js); sessions/tries/status
 *													stay a developer-only, direct-json task.
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
		_roles				: [],
		_loading			: false,
		_ready				: false,

		/**
		 *	Load every user the current user may see and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('users-list') === null || Nino.admin.users._loading === true || Nino.admin.users._ready === true )
				return;

			Nino.admin.users._loading = true;

			Nino.admin.users._apiCall( 'list', {}, function( status, response ) {
				Nino.admin.users._loading = false;
				if( status !== 200 || response === null )
					return Nino.admin.users._showError( dc.getElementById('users-list'), status, response );

				// Capture the hash before any _show*() call below can overwrite it -
				// _showList() would otherwise wipe the deep-link part it's trying to restore
				const hash = Nino.admin.router.current();

				Nino.admin.users._users = response.users;
				Nino.admin.users._canManage = response.canManage;
				Nino.admin.users._roles = response.roles || [];
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

			if( Nino.admin.users._ready === false ) {
				Nino.admin.users.init();
				return;
			}

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
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/users/error/load') );
			container.appendChild( p );
		},

		/**
		 *	Drill-down navigation: list -> form. The rail stays visible
		 *	throughout.
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
			Nino.admin.router.set( 'users', [ Nino.admin.users._currentUser === null ? 'new' : Nino.admin.users._currentUser.mail ] );
		},

		/**
		 *	What the list and the form say about an account's role: the role's
		 *	name, or - for an account without one - whether its own permissions
		 *	give it full access (the account a recovery created) or nothing
		 *
		 *	@param		{Object}	user					{ mail, role, perms }
		 *
		 *	@return		{string}
		 */
		_roleLabel : function( user ) {

			const role = Nino.admin.users._roles.find( function( r ) { return r.id === user.role } );
			if( role !== undefined )
				return role.label;

			if( ( user.perms || [] ).indexOf('/*') !== -1 )
				return Nino.content.getText('/_admin/users/label/fullaccess');

			return Nino.content.getText('/_admin/users/label/norole');
		},

		/**
		 *	A select over every role there is, plus "no role" whenever the
		 *	current value is not among them (an account without one, or a
		 *	project without any yet) - so what is stored is always an option
		 *
		 *	@param		{string}	id
		 *	@param		{string}	current				The role id to select
		 *
		 *	@return		{Element}
		 */
		_roleSelect : function( id, current ) {

			const select = dc.createElement('select');
			select.id = id;

			if( Nino.admin.users._roles.some( function( r ) { return r.id === current } ) === false ) {
				const none = dc.createElement('option');
				none.value = '';
				none.textContent = Nino.content.getText('/_admin/users/label/norole');
				select.appendChild( none );
				current = '';
			}

			Nino.admin.users._roles.forEach( function( role ) {
				const option = dc.createElement('option');
				option.value = role.id;
				option.textContent = role.label;
				select.appendChild( option );
			} );

			select.value = current;

			return select;
		},

		/**
		 *	Render the user list: every account with the role it holds
		 *
		 *	@param		{Array}		users					[ { mail, isSelf, role, perms }, ... ]
		 *
		 *	@return		void
		 */
		_renderList : function( users ) {

			const wrap = dc.getElementById('users-list');
			wrap.innerHTML = '';

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			users.forEach( function( user ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';

				const copy = dc.createElement('span');
				copy.className = 'nino-admin-list-copy';
				const mail = dc.createElement('strong');
				mail.textContent = user.mail + ( user.isSelf === true ? ' ('+ Nino.content.getText('/_admin/users/label/you')+ ')' : '' );
				const role = dc.createElement('small');
				role.textContent = Nino.admin.users._roleLabel( user );
				copy.appendChild( mail );
				copy.appendChild( role );
				link.appendChild( copy );

				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.users._openUser( user.mail ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );
			if( Nino.admin.users._canManage === true ) {
				const add = dc.createElement('button');
				add.type = 'button';
				add.className = 'nino-admin-btn-primary';
				add.textContent = Nino.content.getText('/_admin/users/label/new');
				add.addEventListener( 'click', function() { Nino.admin.users._renderCreateForm() } );
				wrap.appendChild( Nino.adminUi.listActions( [ add ] ) );
			}
		},

		/**
		 *	The "new user" form: mail, password and the role the account
		 *	starts with - what a role grants is the roles tab, not this form
		 *
		 *	@return		void
		 */
		_renderCreateForm : function() {
			const wrap = dc.getElementById('users-form');
			wrap.innerHTML = '';
			Nino.admin.users._currentUser = null;
			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/users/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.users._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );
			const form = dc.createElement('form');
			form.id = 'users-create-form';
			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/users/label/new');
			fieldset.appendChild( legend );
			const mailLabel = dc.createElement('label');
			mailLabel.className = 'nino-admin-field';
			const mailSpan = dc.createElement('span');
			mailSpan.textContent = Nino.content.getText('/_admin/users/label/mail');
			mailLabel.appendChild( mailSpan );
			const mailInput = dc.createElement('input');
			mailInput.type = 'email';
			mailInput.id = 'users-create-mail';
			mailInput.required = true;
			mailInput.autocomplete = 'off';
			mailLabel.appendChild( mailInput );
			fieldset.appendChild( mailLabel );
			const pwLabel = dc.createElement('label');
			pwLabel.className = 'nino-admin-field';
			const pwSpan = dc.createElement('span');
			pwSpan.textContent = Nino.content.getText('/_admin/users/label/password');
			pwLabel.appendChild( pwSpan );
			const pwInput = dc.createElement('input');
			pwInput.type = 'password';
			pwInput.id = 'users-create-pw';
			pwInput.required = true;
			pwInput.minLength = 8;
			pwInput.autocomplete = 'new-password';
			pwLabel.appendChild( pwInput );
			fieldset.appendChild( pwLabel );
			const roleLabel = dc.createElement('label');
			roleLabel.className = 'nino-admin-field';
			const roleSpan = dc.createElement('span');
			roleSpan.textContent = Nino.content.getText('/_admin/users/label/role');
			roleLabel.appendChild( roleSpan );
			const roleSelect = Nino.admin.users._roleSelect( 'users-create-role', Nino.admin.users._roles.length > 0 ? Nino.admin.users._roles[0].id : '' );
			roleLabel.appendChild( roleSelect );
			fieldset.appendChild( roleLabel );
			form.appendChild( fieldset );
			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';
			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/users/label/create');
			actions.appendChild( saveBtn );
			const msg = dc.createElement('p');
			msg.id = 'users-form-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );
			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) {
				ev.preventDefault();
				msg.textContent = Nino.content.getText('/_admin/users/msg/pending');
				Nino.admin.users._apiCall( 'create', { mail : mailInput.value.trim(), pw : pwInput.value, role : roleSelect.value }, function( status, response ) {
					if( status !== 200 ) {
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/users/error/create') );
						return;
					}
					// Reload the list and open the account just created
					Nino.admin.users._apiCall( 'list', {}, function( listStatus, listResponse ) {
						if( listStatus !== 200 || listResponse === null )
							return Nino.admin.users._showError( dc.getElementById('users-list'), listStatus, listResponse );
						Nino.admin.users._users = listResponse.users;
						Nino.admin.users._renderList( listResponse.users );
						Nino.admin.users._openUser( response.mail );
					} );
				} );
			} );
			wrap.appendChild( form );
			dc.getElementById('users-list').classList.add('admin-hidden');
			wrap.classList.remove('admin-hidden');
			Nino.admin.router.set( 'users', [ 'new' ] );
			mailInput.focus();
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
		 *	current password to confirm the change. The role sits in its own
		 *	fieldset below (see _renderRole)
		 *
		 *	@return		void
		 */
		_renderForm : function() {

			const user = Nino.admin.users._currentUser;

			const wrap = dc.getElementById('users-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/users/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.users._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');
			form.id = 'users-edit-form';


			const usersWrap = dc.createElement('fieldset');
			usersWrap.id = 'users-form-global';
			const legend = dc.createElement('legend');
			legend.textContent = user.mail;
			usersWrap.appendChild( legend );
			wrap.appendChild(usersWrap)

			const mailLabel = dc.createElement('label');
			mailLabel.className = 'nino-admin-field';
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
			pwLabel.className = 'nino-admin-field';
			const pwSpan = dc.createElement('span');
			pwSpan.textContent = Nino.content.getText('/_admin/users/label/newpw');
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
				curLabel.className = 'nino-admin-field';
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
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/users/label/save');
			actions.appendChild( saveBtn );

			const logoutAllBtn = dc.createElement('button');
			logoutAllBtn.type = 'button';
			logoutAllBtn.textContent = Nino.content.getText('/_admin/users/label/logoutall');
			logoutAllBtn.addEventListener( 'click', function() { Nino.admin.users._logoutAll() } );
			actions.appendChild( logoutAllBtn );
			// Never your own account: log out and let another manager do it
			if( Nino.admin.users._canManage === true && user.isSelf !== true ) {
				const delBtn = dc.createElement('button');
				delBtn.type = 'button';
				delBtn.className = 'nino-admin-btn-danger';
				delBtn.textContent = Nino.content.getText('/_admin/users/label/delete');
				delBtn.addEventListener( 'click', function() { Nino.admin.users._delete() } );
				actions.appendChild( delBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'users-form-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.users._save() } );

			usersWrap.appendChild( form );

			wrap.appendChild( Nino.admin.users._renderRole() );
		},

		/**
		 *	The role fieldset under the profile form: which role the account
		 *	holds, as a select a manager may change - never for their own
		 *	account, same as the backend refuses (apiSetRole()): log out and
		 *	ask another manager - and as plain text for everyone else. What a
		 *	role grants is the roles tab beside this panel, not this form.
		 *	Its own form with an in-flow actions row: the pinned action bar
		 *	is the profile form's
		 *
		 *	@return		{Element}
		 */
		_renderRole : function() {

			const user = Nino.admin.users._currentUser;

			const wrap = dc.createElement('fieldset');
			wrap.id = 'users-form-role';
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/users/label/role');
			wrap.appendChild( legend );

			if( Nino.admin.users._canManage !== true || user.isSelf === true ) {
				const current = dc.createElement('p');
				current.className = 'nino-admin-hint';
				current.textContent = Nino.admin.users._roleLabel( user )+ ( Nino.admin.users._canManage === true ? ' – '+ Nino.content.getText('/_admin/users/label/role-self') : '' );
				wrap.appendChild( current );
				return wrap;
			}

			const form = dc.createElement('form');
			form.id = 'users-role-form';

			const roleLabel = dc.createElement('label');
			roleLabel.className = 'nino-admin-field';
			const roleSpan = dc.createElement('span');
			roleSpan.textContent = Nino.content.getText('/_admin/users/label/role');
			roleLabel.appendChild( roleSpan );
			const select = Nino.admin.users._roleSelect( 'users-form-role-select', user.role );
			roleLabel.appendChild( select );
			form.appendChild( roleLabel );

			const actions = dc.createElement('div');
			actions.className = 'admin-form-actions';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText('/_admin/users/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'users-role-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.users._saveRole( select ) } );

			wrap.appendChild( form );

			return wrap;
		},

		/**
		 *	Save the current user's role
		 *
		 *	@param		{Element}		select				The role select
		 *
		 *	@return		void
		 */
		_saveRole : function( select ) {

			const user = Nino.admin.users._currentUser;
			const msg 	= dc.getElementById('users-role-msg');

			msg.textContent = Nino.content.getText('/_admin/users/msg/pending');

			Nino.admin.users._apiCall( 'role', { username : user.mail, role : select.value }, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/users/error/save') );
					return;
				}

				user.role = response.role;
				msg.textContent = Nino.content.getText('/_admin/users/msg/saved');
				Nino.admin.users._renderList( Nino.admin.users._users );
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
		 *	Delete the current user, after confirmation, and return to the list
		 *
		 *	@return		void
		 */
		_delete : function() {
			if( wn.confirm( Nino.content.getText('/_admin/users/confirm/delete') ) === false )
				return;
			const user = Nino.admin.users._currentUser;
			const msg 	= dc.getElementById('users-form-msg');
			Nino.admin.users._apiCall( 'delete', { username : user.mail }, function( status, response ) {
				if( status !== 200 ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/users/error/save') );
					return;
				}
				Nino.admin.users._users = Nino.admin.users._users.filter( function( u ) { return u.mail !== user.mail } );
				Nino.admin.users._renderList( Nino.admin.users._users );
				Nino.admin.users._showList();
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

})(window, document, document.documentElement, document.body);
