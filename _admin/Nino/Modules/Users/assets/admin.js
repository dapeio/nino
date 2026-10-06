

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js								"Users" panel: change your own mail/password (with
 *													current-password confirmation), or - given the manage
 *													permission - anyone's, create accounts with a role,
 *													change which role an account holds (never your own,
 *													in the same save as the address and the password),
 *													deactivate and activate accounts and delete them. The
 *													list says which accounts are deactivated or locked and
 *													when each logged in last. What a role grants is the
 *													roles tab beside this one (roles.js), lifting a lock
 *													the login protection tab (lockout.js).
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
		// The edit form's status line - see Nino.adminUi.status(). Drawn again
		// with its form, so always read from here rather than kept in a local
		_status				: null,
		// The open new-account form's save, set while that form is drawn: called
		// with a function that is told how it ended
		_create			: null,
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
		 *	synced to reality instead of staying stale from before the switch.
		 *
		 *	Where the hash names this panel, the hash wins: a step through the
		 *	browser's history changes the address and nothing else, so the level
		 *	it names is shown - and leaving a form that holds unsaved input asks
		 *	first, as its back link does. A hash that names another panel (a
		 *	click on the rail) leaves the level in memory as it is
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.users._ready === false ) {
				Nino.admin.users.init();
				return;
			}

			const hash = Nino.admin.router.current();
			if( hash.panel === 'users' && Nino.admin.users._follow( hash.parts ) === true )
				return;

			Nino.admin.users._showLevel();
		},

		/**
		 *	Show the level this panel is on, and write it into the address
		 *
		 *	@return		void
		 */
		_showLevel : function() {

			if( dc.getElementById('users-form').classList.contains('admin-hidden') === false )
				return Nino.admin.users._showForm();

			Nino.admin.users._showList();
		},

		/**
		 *	Move to the level the hash names, if it is not the one on screen:
		 *	the new-account form, an account's form or the list. An account the
		 *	list does not hold - and 'new' for one who may not create - is the
		 *	list. Leaving a form with unsaved input asks first (see
		 *	Nino.admin.router.leave())
		 *
		 *	@param		{Array}		parts					The hash behind the panel's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const open = dc.getElementById('users-form').classList.contains('admin-hidden') === false;
			const current = Nino.admin.users._currentUser;
			const user = parts.length === 0 ? undefined : Nino.admin.users._users.find( function( u ) { return u.mail === parts[0] } );
			const create = parts[0] === 'new' && user === undefined && Nino.admin.users._canManage === true;

			if( create === true ? ( open === true && current === null ) : ( user === undefined ? open === false : ( open === true && current !== null && current.mail === user.mail ) ) )
				return false;

			Nino.admin.router.leave( [ 'users' ], open, function() {
				if( create === true )
					Nino.admin.users._renderCreateForm();
				else if( user === undefined )
					Nino.admin.users._showList();
				else
					Nino.admin.users._openUser( user.mail );
			}, Nino.admin.users._showLevel );

			return true;
		},

		/**
		 *	Call a users/* admin action - this panel's name for Nino.adminUi.api.call(),
		 *	which owns where the request goes
		 *
		 *	@param		{string}		endpoint			Action name (eg. "save", becomes "users/save")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'users/'+ endpoint, payload, callback );
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
			Nino.adminUi.showError( container, status, response, '/_admin/users/error/load' );
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
			select.name = 'role';

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
		 *	What the list and the form say of an account beside its role, as
		 *	words and never as colour alone: that it is deactivated, until when
		 *	it is locked, and when it logged in last - or that it never did
		 *
		 *	@param		{Object}	user					{ status, locked, lastLogin, ... }
		 *
		 *	@return		{Array}									Texts, in reading order
		 */
		_stateParts : function( user ) {

			const parts = [];

			if( user.status === 'disabled' )
				parts.push( Nino.content.getText('/_admin/users/label/status-disabled') );

			if( typeof user.locked === 'string' && user.locked !== '' )
				parts.push( Nino.adminUi.format( Nino.content.getText('/_admin/users/label/locked-until'), user.locked ) );

			parts.push( typeof user.lastLogin === 'string' && user.lastLogin !== ''
				? Nino.adminUi.format( Nino.content.getText('/_admin/users/label/lastlogin'), user.lastLogin )
				: Nino.content.getText('/_admin/users/label/never') );

			return parts;
		},

		/**
		 *	Render the user list: every account with the role it holds, whether
		 *	it is deactivated or locked, and its last login
		 *
		 *	@param		{Array}		users					[ { mail, isSelf, role, perms, status, locked, lastLogin }, ... ]
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
				role.textContent = [ Nino.admin.users._roleLabel( user ) ].concat( Nino.admin.users._stateParts( user ) ).join(' · ');
				copy.appendChild( mail );
				copy.appendChild( role );
				link.appendChild( copy );

				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.router.go( 'users', [ user.mail ] ); Nino.admin.users._openUser( user.mail ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );
			if( Nino.admin.users._canManage === true ) {
				const add = dc.createElement('button');
				add.type = 'button';
				add.className = 'nino-admin-btn-primary';
				add.textContent = Nino.content.getText('/_admin/users/label/new');
				add.addEventListener( 'click', function() { Nino.admin.router.go( 'users', [ 'new' ] ); Nino.admin.users._renderCreateForm() } );
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
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.router.go( 'users', [] ); Nino.admin.users._showList() } );
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
			mailInput.dataset.field = 'mail';
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
			pwInput.dataset.field = 'pw';
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
			actions.appendChild( msg );
			form.appendChild( actions );
			const line = Nino.adminUi.status( msg );
			line.bind( form );

			// Said once: the form submits it, and the shell's question about
			// unsaved input saves through it (see Nino.admin.dirty.guard()), which
			// wants to hear how it ended
			Nino.admin.users._create = function( done ) {

				const report = function( ok ) {
					if( typeof done === 'function' )
						done( ok );
				};

				line.saving();
				Nino.admin.users._apiCall( 'create', { mail : mailInput.value.trim(), pw : pwInput.value, role : roleSelect.value }, function( status, response ) {
					if( status !== 200 ) {
						line.error( status, response, '/_admin/users/error/create' );
						report( false );
						return;
					}
					// Reload the list and open the account just created
					Nino.admin.users._apiCall( 'list', {}, function( listStatus, listResponse ) {
						if( listStatus !== 200 || listResponse === null ) {
							// The account exists: only the list failed, and a Save that is
							// reported as failed would be tried again
							Nino.admin.users._showError( dc.getElementById('users-list'), listStatus, listResponse );
							report( true );
							return;
						}
						Nino.admin.users._users = listResponse.users;
						Nino.admin.users._renderList( listResponse.users );
						Nino.admin.users._openUser( response.mail );
						report( true );
					} );
				} );
			};

			form.addEventListener( 'submit', function( ev ) {
				ev.preventDefault();
				Nino.admin.users._create();
			} );
			wrap.appendChild( form );
			dc.getElementById('users-list').classList.add('admin-hidden');
			wrap.classList.remove('admin-hidden');
			Nino.admin.router.set( 'users', [ 'new' ] );
			mailInput.focus();

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'users' );
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
		 *	Render the edit form: mail, the role (a select for a manager editing
		 *	somebody else, text for everyone else), a new password (optional,
		 *	leave blank to keep it unchanged), and - only when editing yourself -
		 *	your current password to confirm the change. One Save writes all of
		 *	it. Beside it, for a manager and not on their own account, the button
		 *	that deactivates or activates the account
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
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.router.go( 'users', [] ); Nino.admin.users._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');
			form.id = 'users-edit-form';


			const usersWrap = dc.createElement('fieldset');
			usersWrap.id = 'users-form-global';
			const legend = dc.createElement('legend');
			legend.textContent = user.mail;
			usersWrap.appendChild( legend );
			wrap.appendChild(usersWrap)

			// Whether the account is deactivated or locked, and its last login
			const state = dc.createElement('p');
			state.id = 'users-form-state';
			state.className = 'nino-admin-hint';
			state.textContent = Nino.admin.users._stateParts( user ).join(' · ');
			form.appendChild( state );

			const mailLabel = dc.createElement('label');
			mailLabel.className = 'nino-admin-field';
			const mailSpan = dc.createElement('span');
			mailSpan.textContent = Nino.content.getText('/_admin/users/label/mail');
			mailLabel.appendChild( mailSpan );
			const mailInput = dc.createElement('input');
			mailInput.type = 'email';
			mailInput.id = 'users-form-mail';
			mailInput.dataset.field = 'mail';
			mailInput.required = true;
			mailInput.value = user.mail;
			mailLabel.appendChild( mailInput );
			form.appendChild( mailLabel );

			form.appendChild( Nino.admin.users._roleField() );

			const pwLabel = dc.createElement('label');
			pwLabel.className = 'nino-admin-field';
			const pwSpan = dc.createElement('span');
			pwSpan.textContent = Nino.content.getText('/_admin/users/label/newpw');
			pwLabel.appendChild( pwSpan );
			const pwInput = dc.createElement('input');
			pwInput.type = 'password';
			pwInput.id = 'users-form-pw';
			pwInput.dataset.field = 'pw';
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
				curInput.dataset.field = 'currentPassword';
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

				const statusBtn = dc.createElement('button');
				statusBtn.type = 'button';
				statusBtn.id = 'users-form-status-toggle';
				statusBtn.textContent = Nino.content.getText( user.status === 'disabled' ? '/_admin/users/label/activate' : '/_admin/users/label/deactivate' );
				statusBtn.addEventListener( 'click', function() { Nino.admin.users._toggleStatus() } );
				actions.appendChild( statusBtn );

				const delBtn = dc.createElement('button');
				delBtn.type = 'button';
				delBtn.className = 'nino-admin-btn-danger';
				delBtn.textContent = Nino.content.getText('/_admin/users/label/delete');
				delBtn.addEventListener( 'click', function() { Nino.admin.users._delete() } );
				actions.appendChild( delBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'users-form-msg';
			msg.className = 'nino-admin-actionbar-status';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.users._save() } );

			// Saved or not, and - for a wrong current password - which field
			Nino.admin.users._status = Nino.adminUi.status( msg );
			Nino.admin.users._status.bind( form );

			usersWrap.appendChild( form );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'users' );
		},

		/**
		 *	Take the account as it is stored for what is saved: the baseline is
		 *	the form with its fields at their stored values, and what was typed
		 *	while the save was on its way is put back afterwards - it is not in
		 *	what was sent, so the form stays unsaved for it
		 *
		 *	@return		void
		 */
		_snapshot : function() {

			if( typeof Nino.admin.dirty !== 'object' )
				return;

			const user = Nino.admin.users._currentUser;
			const mail = dc.getElementById('users-form-mail');
			const pw = dc.getElementById('users-form-pw');
			const role = dc.getElementById('users-form-role-select');

			if( user === null || mail === null || pw === null ) {
				Nino.admin.dirty.snapshot( 'users' );
				return;
			}

			const typed = { mail : mail.value, pw : pw.value, role : role === null ? null : role.value };

			mail.value = user.mail;
			pw.value = '';
			if( role !== null )
				role.value = role.dataset.saved;

			try {
				Nino.admin.dirty.snapshot( 'users' );
			}
			finally {
				mail.value = typed.mail;
				pw.value = typed.pw;
				if( role !== null )
					role.value = typed.role;
			}

			Nino.admin.dirty.refresh();
		},

		/**
		 *	Save what the open form holds, whichever it is: a new account, or
		 *	an existing one - address, password and role in one request, if any
		 *	of them differs from what is stored. What the shell's question about
		 *	unsaved input saves through (see Nino.admin.dirty.guard())
		 *
		 *	@param		{Function}	done				Called once with true when everything that changed was written, false otherwise
		 *
		 *	@return		void
		 */
		_saveOpen : function( done ) {

			if( dc.getElementById('users-create-form') !== null )
				return Nino.admin.users._create( done );

			const user = Nino.admin.users._currentUser;

			if( dc.getElementById('users-form-mail').value.trim() !== user.mail || dc.getElementById('users-form-pw').value !== '' || Nino.admin.users._roleChanged() === true )
				return Nino.admin.users._save( done );

			// Nothing differed from what is stored (a blank around the mail, a
			// field typed back): the form as it stands is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'users' );

			done( true );
		},

		/**
		 *	Whether the role select holds another role than the one stored.
		 *	False where there is no select: for your own account, and for an
		 *	account that is not a manager's to change
		 *
		 *	@return		{boolean}
		 */
		_roleChanged : function() {

			const role = dc.getElementById('users-form-role-select');

			return role !== null && role.value !== role.dataset.saved;
		},

		/**
		 *	The role of the open account, as a field of the edit form: a select
		 *	a manager may change - never for their own account, same as the
		 *	backend refuses (see Users\Admin::apiSave()): log out and ask another
		 *	manager - and as plain text for everyone else. What a role grants is
		 *	the roles tab beside this panel, not this form
		 *
		 *	@return		{Element}
		 */
		_roleField : function() {

			const user = Nino.admin.users._currentUser;

			if( Nino.admin.users._canManage !== true || user.isSelf === true ) {
				const current = dc.createElement('p');
				current.className = 'nino-admin-hint';
				current.textContent = Nino.content.getText('/_admin/users/label/role')+ ': '+ Nino.admin.users._roleLabel( user )+ ( Nino.admin.users._canManage === true ? ' – '+ Nino.content.getText('/_admin/users/label/role-self') : '' );
				return current;
			}

			const roleLabel = dc.createElement('label');
			roleLabel.className = 'nino-admin-field';
			const roleSpan = dc.createElement('span');
			roleSpan.textContent = Nino.content.getText('/_admin/users/label/role');
			roleLabel.appendChild( roleSpan );
			const select = Nino.admin.users._roleSelect( 'users-form-role-select', user.role );
			// The role the account holds, to tell a change from what is stored
			select.dataset.saved = select.value;
			roleLabel.appendChild( select );

			return roleLabel;
		},

		/**
		 *	Save the current user's mail, password and - where it was changed -
		 *	role, in one request
		 *
		 *	@param		{Function}	[done]				Called once with true when the account was written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( done ) {

			const user = Nino.admin.users._currentUser;
			const mail = dc.getElementById('users-form-mail').value.trim();
			const pw 	 = dc.getElementById('users-form-pw').value;
			const line = Nino.admin.users._status;

			const payload = { username : user.mail, mail : mail, pw : pw };

			// Only a change is sent: a role posted unchanged would ask the
			// server for the checks of a role change, which a manager editing
			// just the address of a wider account must not meet
			const role = dc.getElementById('users-form-role-select');
			if( Nino.admin.users._roleChanged() === true )
				payload.role = role.value;

			if( user.isSelf === true )
				payload.currentPassword = dc.getElementById('users-form-currentpw').value;

			line.saving();

			Nino.admin.users._apiCall( 'save', payload, function( status, response ) {

				if( status !== 200 ) {
					line.error( status, response, '/_admin/users/error/save' );
					if( typeof done === 'function' )
						done( false );
					return;
				}

				user.mail = response.mail;

				if( typeof response.role === 'string' ) {
					user.role = response.role;
					// What the select shows is what counts as stored: a role id
					// that is no longer in the config is not an option, and it
					// would otherwise read as a change on the next save
					if( role !== null ) {
						role.value = response.role;
						role.dataset.saved = role.value;
					}
				}

				// The rail names the account the page is for - the api compares a
				// session it finds later against it
				const rail = user.isSelf === true ? dc.getElementById('admin-user-email') : null;
				if( rail !== null )
					rail.textContent = response.mail;

				dc.getElementById('users-form-pw').value = '';
				const curInput = dc.getElementById('users-form-currentpw');
				if( curInput !== null )
					curInput.value = '';

				line.saved();
				Nino.admin.router.set( 'users', [ user.mail ] );
				Nino.admin.users._snapshot();
				if( typeof done === 'function' )
					done( true );

				// Refresh the list in the background so a renamed mail is reflected there too
				Nino.admin.users._apiCall( 'list', {}, function( listStatus, listResponse ) {
					if( listStatus === 200 && listResponse !== null ) {
						Nino.admin.users._users = listResponse.users;
						// The open account is the list's own entry again, so a change
						// made to one (see _toggleStatus()) is the other's too
						Nino.admin.users._currentUser = listResponse.users.find( function( u ) { return u.mail === user.mail } ) || user;
						Nino.admin.users._renderList( listResponse.users );
					}
				} );
			} );
		},

		/**
		 *	Deactivate or activate the current user - a deactivation after
		 *	confirmation, since it ends every session the account holds. The
		 *	form keeps what was typed into it: only the button and the line
		 *	about the account's state are drawn again
		 *
		 *	@return		void
		 */
		_toggleStatus : function() {

			const user = Nino.admin.users._currentUser;
			const active = user.status === 'disabled';

			if( active === false && wn.confirm( Nino.content.getText('/_admin/users/confirm/deactivate') ) === false )
				return;

			const button = dc.getElementById('users-form-status-toggle');
			button.disabled = true;

			Nino.admin.users._apiCall( 'status', { username : user.mail, active : active }, function( status, response ) {

				button.disabled = false;

				if( status !== 200 || response === null ) {
					Nino.admin.users._status.error( status, response, '/_admin/users/error/save' );
					return;
				}

				user.status = response.status;

				button.textContent = Nino.content.getText( active === true ? '/_admin/users/label/deactivate' : '/_admin/users/label/activate' );
				dc.getElementById('users-form-state').textContent = Nino.admin.users._stateParts( user ).join(' · ');
				Nino.admin.users._renderList( Nino.admin.users._users );
				Nino.admin.users._status.idle( Nino.content.getText( active === true ? '/_admin/users/msg/activated' : '/_admin/users/msg/deactivated' ) );
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
			Nino.admin.users._apiCall( 'delete', { username : user.mail }, function( status, response ) {
				if( status !== 200 ) {
					Nino.admin.users._status.error( status, response, '/_admin/users/error/save' );
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

			// Ending your own sessions ends this page's, and with it every form
			// on it: the unsaved input of all of them is asked about first
			const request = function() {

				Nino.admin.users._apiCall( 'logoutall', { username : user.mail }, function( status, response ) {

					if( status !== 200 ) {
						Nino.admin.users._status.error( status, response, '/_admin/users/error/save' );
						return;
					}

					// Logging out yourself invalidates the current session - reload straight to the login form
					if( response.loggedOutSelf === true ) {
						wn.location.replace( '[[/nino/dir]]/_admin' );
						return;
					}

					Nino.admin.users._status.idle( Nino.content.getText('/_admin/users/msg/loggedout') );
				} );
			};

			if( user.isSelf === true && typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.guard( null, request );
			else
				request();
		},
	};

	// The shell asks before anything throws the open account's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'users', function() { return dc.getElementById('users-form') }, Nino.admin.users._saveOpen );

})(window, document, document.documentElement, document.body);
