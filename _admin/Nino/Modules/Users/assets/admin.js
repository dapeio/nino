

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
 *													when each logged in last. Every view has its address:
 *													#users, #users/users/@new and #users/users/<mail>. What a role grants
 *													is the roles tab beside this one (roles.js), lifting a
 *													lock the login protection tab (lockout.js). An account
 *													that holds a permission the signed-in one does not is
 *													shown without a field or a button: it is not theirs to
 *													change.
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
		// The button that opened the new-account form that is open: Back returns
		// the focus to it (an account's own row is found again, see _rowOf())
		_opener				: null,
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

				// The address of the new-account form works on a fresh load as it does after a click: for an account that may create
				if( hash.panel === 'users' && Nino.admin.router.isWord( hash.parts, 0, 'new' ) === true && Nino.admin.users._canManage === true )
					return Nino.admin.users._renderCreateForm();

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
		 *	list does not hold - and '@new' for one who may not create - is the
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
			const create = Nino.admin.router.isWord( parts, 0, 'new' ) === true && user === undefined && Nino.admin.users._canManage === true;

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
		 *	throughout. The focus stays where it is, unless it stood in the level
		 *	that is hidden now (see Nino.adminUi.showLevel()): the level is shown
		 *	again as it stands, by the address or after a delete. Back goes to the
		 *	row, and what a person opens takes the focus in
		 *
		 *	@param		{Element|null}	[opener]	Back: the control that opened the form that is left; null where there is none to name
		 *
		 *	@return		void
		 */
		_showList : function( opener ) {
			Nino.admin.router.set( 'users', [] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'users-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'users-list', opener );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {
			Nino.admin.router.set( 'users', [ Nino.admin.users._currentUser === null ? Nino.admin.router.word('new') : Nino.admin.users._currentUser.mail ] );
			Nino.adminUi.showLevel( 'users-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	The row of an account as the list is drawn now: the list is drawn
		 *	again behind an open form after a save, and the row that was pressed
		 *	is gone with the old drawing
		 *
		 *	@param		{string}	mail
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( mail ) {

			const row = Array.from( dc.getElementById('users-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === mail } );

			return row === undefined ? null : row.querySelector('a');
		},

		/**
		 *	The control Back returns the focus to from the form that is open: the
		 *	row of the account, or the button that opened the new-account form
		 *
		 *	@return		{Element|null}
		 */
		_backTarget : function() {
			return Nino.admin.users._currentUser === null ? Nino.admin.users._opener : Nino.admin.users._rowOf( Nino.admin.users._currentUser.mail );
		},

		/**
		 *	The one way back of a form: "Back to <the panel>" in the context bar,
		 *	which leaves the form for the list and puts the focus on its row
		 *
		 *	@param		{Element}	wrap				The level the bar stands in
		 *
		 *	@return		{Element}
		 */
		_contextBar : function( wrap ) {
			return Nino.adminUi.contextBar( { back : { to : wrap, onClick : function() {
				Nino.admin.router.go( 'users', [] );
				Nino.admin.users._showList( Nino.admin.users._backTarget() );
			} } } );
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
		 *	The role of an account as a field of a form: a select over every role
		 *	there is that the signed-in account may hand out, plus "no role"
		 *	whenever the current value is not among them (an account without one,
		 *	or a project without any yet) - so what is stored is always an option.
		 *	The select carries data-field, so that a refusal names it
		 *
		 *	@param		{string}	id
		 *	@param		{string}	current				The role id to select
		 *
		 *	@return		{Element}									The label of the field; its select is what the form reads
		 */
		_roleSelect : function( id, current ) {

			// Only a role the signed-in account may hand out (see Roles::notHeld()),
			// and the one the account holds, which has to stay an option
			const offered = Nino.admin.users._roles.filter( function( r ) { return r.canGrant !== false || r.id === current } );
			const options = offered.map( function( role ) { return { value : role.id, label : role.label } } );

			if( offered.some( function( r ) { return r.id === current } ) === false ) {
				options.unshift( { value : '', label : Nino.content.getText('/_admin/users/label/norole') } );
				current = '';
			}

			const field = Nino.adminUi.selectField( {
				key			: 'role',
				id			: id,
				label		: Nino.content.getText('/_admin/users/label/role'),
				help		: Nino.content.getText('/_admin/users/hint/role'),
				options	: options,
				value		: current,
			} );

			field.querySelector('select').dataset.field = 'role';

			return field;
		},

		/**
		 *	A line of text a form shows and does not take: the state of an account, its role where it is not the
		 *	signed-in account's to change. Drawn as text - no input, no stop of the Tab key - in the place of a field,
		 *	with the name at its left
		 *
		 *	@param		{string}	id
		 *	@param		{string}	label
		 *	@param		{string}	value
		 *	@param		{string}	[help]
		 *
		 *	@return		{Element}									The label of the field
		 */
		_readOnly : function( id, label, value, help ) {

			const field = Nino.adminUi.el( 'div', 'nino-admin-field' );
			const name = Nino.adminUi.el( 'span', '', label );
			const text = Nino.adminUi.el( 'p', '', value );

			text.id = id;
			field.appendChild( name );
			field.appendChild( text );

			if( help !== undefined )
				name.appendChild( Nino.adminUi.fieldHelp( help, { field : text, name : label } ) );

			return field;
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
		 *	The name field of the create and the edit form. The input holds at most
		 *	what the server accepts (it refuses a longer one, it does not cut), and
		 *	carries data-field so that a refusal names it
		 *
		 *	@param		{string}	id
		 *	@param		{string}	value					The name stored, '' for none
		 *
		 *	@return		{Element}									The label of the field
		 */
		_nameField : function( id, value ) {

			const field = Nino.adminUi.textField( {
				id					: id,
				label				: Nino.content.getText('/_admin/users/label/name'),
				help				: Nino.content.getText('/_admin/users/hint/name'),
				value				: value,
				maxLength		: 60,
				autocomplete	: 'off',
			} );

			field.querySelector('input').dataset.field = 'name';

			return field;
		},

		/**
		 *	The email field of the create and the edit form
		 *
		 *	@param		{string}	id
		 *	@param		{string}	value
		 *
		 *	@return		{Element}									The label of the field
		 */
		_mailField : function( id, value ) {

			const field = Nino.adminUi.textField( {
				id					: id,
				type				: 'email',
				label				: Nino.content.getText('/_admin/users/label/mail'),
				help				: Nino.content.getText('/_admin/users/hint/mail'),
				value				: value,
				required		: true,
				autocomplete	: 'off',
			} );

			field.querySelector('input').dataset.field = 'mail';

			return field;
		},

		/**
		 *	A password field of a form of this panel. The minimum is the one of the server, which refuses a shorter one
		 *	all the same
		 *
		 *	@param		{Object}	options				{ id, field, label, help, required, minLength, autocomplete }
		 *
		 *	@return		{Element}									The label of the field
		 */
		_passwordField : function( options ) {

			const field = Nino.adminUi.textField( {
				id					: options.id,
				type				: 'password',
				label				: options.label,
				help				: options.help,
				required		: options.required === true,
				minLength		: options.minLength,
				autocomplete	: options.autocomplete,
			} );

			field.querySelector('input').dataset.field = options.field;

			return field;
		},

		/**
		 *	The sentence that is wrong with a field before anything is sent, '' where it is fine: an empty one that is
		 *	required, an address that is none, a password that is too short
		 *
		 *	@param		{Element}	input
		 *	@param		{string}	name					The name of the field
		 *
		 *	@return		{string}
		 */
		_problem : function( input, name ) {

			const secret = input.type === 'password';
			const value = secret === true ? input.value : input.value.trim();

			if( value === '' )
				return input.required === true ? Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), name ) : '';

			if( input.type === 'email' && /^[^\s@]+@[^\s@]+$/.test( value ) === false )
				return Nino.adminUi.format( Nino.content.getText('/_admin/users/error/invalid'), name, Nino.content.getText('/_admin/users/expect/mail') );

			if( secret === true && input.minLength > 0 && value.length < input.minLength )
				return Nino.adminUi.format( Nino.content.getText('/_admin/users/error/invalid'), name, Nino.adminUi.format( Nino.content.getText('/_admin/users/expect/password'), input.minLength ) );

			return '';
		},

		/**
		 *	Check the fields of a form before a request goes out: each wrong one says what is wrong under it, the line of
		 *	the bar says so once, and the first takes the focus. The browser's own checking is off (the forms are
		 *	novalidate), so its bubble in its own language never speaks
		 *
		 *	@param		{Element}		form
		 *	@param		{Array}			fields				[ [ id of the input, fill of the name of the field ], ... ] in the order the fields stand
		 *	@param		{Object}		line					The status line of the form's bar
		 *
		 *	@return		{boolean}									Whether every field is fine
		 */
		_check : function( form, fields, line ) {

			Nino.adminUi.clearFieldErrors( form );

			let first = null;

			fields.forEach( function( field ) {

				const input = dc.getElementById( field[0] );
				const sentence = Nino.admin.users._problem( input, Nino.content.getText( field[1] ) );

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( input.closest('label'), input, 'users-error-'+ input.id, sentence );

				if( first === null )
					first = input;
			} );

			if( first === null )
				return true;

			line.fail( Nino.content.getText('/_admin/users/error/fields') );
			first.focus();

			return false;
		},

		/**
		 *	Say what the server refused: in the line of the bar and, where it named a field of the form, under that field
		 *
		 *	@param		{Element}		form
		 *	@param		{Object}		line					The status line of the form's bar
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *	@param		{string}		fallback			The fill of the sentence for a refusal without a code
		 *
		 *	@return		void
		 */
		_refused : function( form, line, status, response, fallback ) {

			Nino.adminUi.clearFieldErrors( form );
			line.error( status, response, fallback );

			const name = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';
			const input = name === '' || /^[A-Za-z]+$/.test( name ) === false ? null : form.querySelector( '[data-field="'+ name+ '"]' );

			if( input !== null )
				Nino.adminUi.fieldError( input.closest('label'), input, 'users-error-'+ input.id, Nino.adminUi.api.errorText( status, response, fallback ) );
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

			// A row opens an account and writes its address: a link, with that address. What the account is
			// addressed by, its name, leads the second line
			wrap.appendChild( Nino.adminUi.list( users.map( function( user ) {
				return {
					key		: user.mail,
					title	: user.mail + ( user.isSelf === true ? ' ('+ Nino.content.getText('/_admin/users/label/you')+ ')' : '' ),
					detail	: ( typeof user.name === 'string' && user.name !== '' ? [ user.name ] : [] ).concat( [ Nino.admin.users._roleLabel( user ) ], Nino.admin.users._stateParts( user ) ).join(' · '),
					href		: Nino.admin.router.href( 'users', [ user.mail ] ),
					onOpen	: function() { Nino.admin.router.go( 'users', [ user.mail ] ); Nino.admin.users._openUser( user.mail, true ) },
				};
			} ), { label : Nino.content.getText('/_admin/nav/user') } ) );

			if( Nino.admin.users._canManage === true ) {
				const add = Nino.adminUi.button( Nino.content.getText('/_admin/users/label/new'), function() { Nino.admin.router.go( 'users', [ Nino.admin.router.word('new') ] ); Nino.admin.users._renderCreateForm( add ) } );
				wrap.appendChild( Nino.adminUi.actionBar( { list : true, primary : add } ).el );
			}
		},

		/**
		 *	The "new user" form: mail, password and the role the account
		 *	starts with - what a role grants is the roles tab, not this form.
		 *	The focus goes to the first field
		 *
		 *	@param		{Element}		[opener]	The button that was pressed: Back returns the focus to it
		 *
		 *	@return		void
		 */
		_renderCreateForm : function( opener ) {

			const wrap = dc.getElementById('users-form');
			wrap.innerHTML = '';
			Nino.admin.users._currentUser = null;
			Nino.admin.users._opener = opener ?? null;
			wrap.appendChild( Nino.admin.users._contextBar( wrap ) );

			const form = dc.createElement('form');
			form.id = 'users-create-form';
			form.noValidate = true;

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/users/label/new');
			fieldset.appendChild( legend );

			const mailField = Nino.admin.users._mailField( 'users-create-mail', '' );
			const nameField = Nino.admin.users._nameField( 'users-create-name', '' );
			const pwField = Nino.admin.users._passwordField( { id : 'users-create-pw', field : 'pw', label : Nino.content.getText('/_admin/users/label/password'), help : Nino.content.getText('/_admin/users/hint/password'), required : true, minLength : 8, autocomplete : 'new-password' } );
			const firstGrantable = Nino.admin.users._roles.find( function( r ) { return r.canGrant !== false } );
			const roleField = Nino.admin.users._roleSelect( 'users-create-role', firstGrantable ? firstGrantable.id : '' );

			[ mailField, nameField, pwField, roleField ].forEach( function( field ) { fieldset.appendChild( field ) } );
			form.appendChild( fieldset );

			const msg = dc.createElement('p');
			msg.id = 'users-form-msg';
			const bar = Nino.adminUi.actionBar( { status : { line : msg, fresh : true }, primary : Nino.adminUi.button( Nino.content.getText('/_admin/users/label/create'), null, { type : 'submit' } ) } );
			const line = bar.line;
			line.bind( form );
			form.appendChild( bar.el );

			const mailInput = mailField.querySelector('input');
			const nameInput = nameField.querySelector('input');
			const pwInput = pwField.querySelector('input');
			const roleSelect = roleField.querySelector('select');

			// Said once: the form submits it, and the shell's question about
			// unsaved input saves through it (see Nino.admin.dirty.guard()), which
			// wants to hear how it ended
			Nino.admin.users._create = function( done ) {

				const report = function( ok ) {
					if( typeof done === 'function' )
						done( ok );
				};

				if( Nino.admin.users._check( form, [ [ 'users-create-mail', '/_admin/users/label/mail' ], [ 'users-create-pw', '/_admin/users/label/password' ] ], line ) === false ) {
					report( false );
					return;
				}

				line.saving();
				Nino.admin.users._apiCall( 'create', { mail : mailInput.value.trim(), name : nameInput.value.trim(), pw : pwInput.value, role : roleSelect.value }, function( status, response ) {
					if( status !== 200 ) {
						Nino.admin.users._refused( form, line, status, response, '/_admin/users/error/create' );
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
						Nino.admin.users._openUser( response.mail, true );
						Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/saved') );
						report( true );
					} );
				} );
			};

			form.addEventListener( 'submit', function( ev ) {
				ev.preventDefault();
				Nino.admin.users._create();
			} );
			wrap.appendChild( form );
			Nino.admin.router.set( 'users', [ Nino.admin.router.word('new') ] );
			Nino.adminUi.showLevel( 'users-form', { focus : mailInput } );

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'users' );
		},

		/**
		 *	Open one user's edit form
		 *
		 *	@param		{string}		mail
		 *	@param		{boolean}		[opened]	The person opened it - a row, or the create form that was just saved: the focus goes into the form. Otherwise it is shown as it stands, by the address or after a step through the history
		 *
		 *	@return		void
		 */
		_openUser : function( mail, opened ) {

			const user = Nino.admin.users._users.find( function( u ) { return u.mail === mail } );
			if( user === undefined )
				return;

			Nino.admin.users._currentUser = user;
			Nino.admin.users._renderForm();
			Nino.admin.users._showForm( opened === true );
		},

		/**
		 *	Render the edit form: mail, the role (a select for a manager editing
		 *	somebody else, text for everyone else), a new password (optional,
		 *	leave blank to keep it unchanged), and - only when editing yourself -
		 *	your current password to confirm the change. One Save writes all of
		 *	it. The bar carries what else is done to the account: delete at the
		 *	left, and beside Save the buttons that log it out everywhere and
		 *	deactivate or activate it - a manager's, and not on their own account
		 *
		 *	@return		void
		 */
		_renderForm : function() {

			const user = Nino.admin.users._currentUser;

			const wrap = dc.getElementById('users-form');
			wrap.innerHTML = '';
			wrap.appendChild( Nino.admin.users._contextBar( wrap ) );

			const form = dc.createElement('form');
			form.id = 'users-edit-form';
			form.noValidate = true;

			const fieldset = dc.createElement('fieldset');
			fieldset.id = 'users-form-global';
			const legend = dc.createElement('legend');
			legend.textContent = user.mail;
			fieldset.appendChild( legend );
			form.appendChild( fieldset );

			// Whether the account is deactivated or locked, and its last login
			fieldset.appendChild( Nino.admin.users._readOnly( 'users-form-state', Nino.content.getText('/_admin/users/label/state'), Nino.admin.users._stateParts( user ).join(' · ') ) );

			// An account that holds what the signed-in one does not is not theirs
			// to change (see Users\Admin::beyond()): shown, with nothing to press
			if( user.canChange === false )
				return Nino.admin.users._renderBeyond( wrap, form, fieldset, user );

			const mailField = Nino.admin.users._mailField( 'users-form-mail', user.mail );
			const nameField = Nino.admin.users._nameField( 'users-form-name', user.name || '' );
			const pwField = Nino.admin.users._passwordField( { id : 'users-form-pw', field : 'pw', label : Nino.content.getText('/_admin/users/label/newpw'), help : Nino.content.getText('/_admin/users/hint/newpw'), minLength : 8, autocomplete : 'new-password' } );

			fieldset.appendChild( mailField );
			fieldset.appendChild( nameField );
			fieldset.appendChild( Nino.admin.users._roleField() );
			fieldset.appendChild( pwField );

			if( user.isSelf === true )
				fieldset.appendChild( Nino.admin.users._passwordField( { id : 'users-form-currentpw', field : 'currentPassword', label : Nino.content.getText('/_admin/users/label/currentpw'), help : Nino.content.getText('/_admin/users/hint/currentpw'), required : true, autocomplete : 'current-password' } ) );

			// The bar: Delete at the left, what else is done to the account beside Save - never to your own account:
			// log out and let another manager do it
			const manager = Nino.admin.users._canManage === true && user.isSelf !== true;
			const side = [ Nino.adminUi.button( Nino.content.getText('/_admin/users/label/logoutall'), function() { Nino.admin.users._logoutAll() }, { kind : 'secondary' } ) ];

			if( manager === true )
				side.push( Nino.adminUi.button( Nino.content.getText( user.status === 'disabled' ? '/_admin/users/label/activate' : '/_admin/users/label/deactivate' ), function() { Nino.admin.users._toggleStatus() }, { kind : 'secondary', id : 'users-form-status-toggle' } ) );

			const msg = dc.createElement('p');
			msg.id = 'users-form-msg';
			const bar = Nino.adminUi.actionBar( {
				danger		: manager === true ? Nino.adminUi.button( Nino.content.getText('/_admin/users/label/delete'), function() { Nino.admin.users._delete() }, { kind : 'danger' } ) : null,
				status		: { line : msg },
				secondary	: side,
				primary		: Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit' } ),
			} );

			form.appendChild( bar.el );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.users._save() } );

			// Saved or not, and - for a wrong current password - which field
			Nino.admin.users._status = bar.line;
			bar.line.bind( form );

			wrap.appendChild( form );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'users' );
		},

		/**
		 *	The form of an account the signed-in one may not change: what the
		 *	account holds, the reason, and no field to type in and no button.
		 *	Drawn into the form _renderForm() began
		 *
		 *	@param		{Element}	wrap				The level the form goes into
		 *	@param		{Element}	form				The edit form
		 *	@param		{Element}	fieldset		The fieldset of the account, with its state in it
		 *	@param		{Object}		user				The account
		 *
		 *	@return		void
		 */
		_renderBeyond : function( wrap, form, fieldset, user ) {

			if( typeof user.name === 'string' && user.name !== '' )
				fieldset.appendChild( Nino.admin.users._readOnly( 'users-form-name', Nino.content.getText('/_admin/users/label/name'), user.name ) );

			fieldset.appendChild( Nino.admin.users._roleField() );
			fieldset.appendChild( Nino.adminUi.notice( Nino.content.getText('/_admin/users/label/beyond') ) );

			Nino.admin.users._status = null;
			wrap.appendChild( form );

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'users' );
		},

		/**
		 *	The action bar of the form on screen - the edit form's or the one
		 *	that creates an account - which is where the shell puts its
		 *	"unsaved" marker (see Nino.admin.dirty). Not the first bar of
		 *	#users-form: that holds whatever else is drawn there
		 *
		 *	@return		{Element|null}
		 */
		_bar : function() {

			const form = dc.getElementById('users-edit-form') ?? dc.getElementById('users-create-form');

			return form === null || form === undefined ? null : form.querySelector('.nino-admin-actionbar');
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

			const name = dc.getElementById('users-form-name');
			const typed = { mail : mail.value, name : name === null ? null : name.value, pw : pw.value, role : role === null ? null : role.value };

			mail.value = user.mail;
			pw.value = '';
			if( name !== null )
				name.value = user.name || '';
			if( role !== null )
				role.value = role.dataset.saved;

			try {
				Nino.admin.dirty.snapshot( 'users' );
			}
			finally {
				mail.value = typed.mail;
				pw.value = typed.pw;
				if( name !== null )
					name.value = typed.name;
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

			if( dc.getElementById('users-form-mail').value.trim() !== user.mail || dc.getElementById('users-form-pw').value !== '' || Nino.admin.users._roleChanged() === true || Nino.admin.users._nameChanged() === true )
				return Nino.admin.users._save( done );

			// Nothing differed from what is stored (a blank around the mail, a
			// field typed back): the form as it stands is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'users' );

			done( true );
		},

		/**
		 *	Whether the name field holds another name than the one stored
		 *
		 *	@return		{boolean}
		 */
		_nameChanged : function() {

			const name = dc.getElementById('users-form-name');
			const user = Nino.admin.users._currentUser;

			return name !== null && user !== null && name.value.trim() !== ( user.name || '' );
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
		 *	manager - and as a line of text for everyone else. What a role grants is
		 *	the roles tab beside this panel, not this form
		 *
		 *	@return		{Element}
		 */
		_roleField : function() {

			const user = Nino.admin.users._currentUser;

			if( Nino.admin.users._canManage !== true || user.isSelf === true || user.canChange === false )
				return Nino.admin.users._readOnly( 'users-form-role', Nino.content.getText('/_admin/users/label/role'), Nino.admin.users._roleLabel( user ), Nino.admin.users._canManage === true && user.isSelf === true ? Nino.content.getText('/_admin/users/label/role-self') : undefined );

			const field = Nino.admin.users._roleSelect( 'users-form-role-select', user.role );
			const select = field.querySelector('select');

			// The role the account holds, to tell a change from what is stored
			select.dataset.saved = select.value;

			return field;
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

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const user = Nino.admin.users._currentUser;
			const form = dc.getElementById('users-edit-form');
			const line = Nino.admin.users._status;

			const mailInput = dc.getElementById('users-form-mail');
			const pwInput = dc.getElementById('users-form-pw');
			const curInput = dc.getElementById('users-form-currentpw');

			const checked = [ [ 'users-form-mail', '/_admin/users/label/mail' ], [ 'users-form-pw', '/_admin/users/label/newpw' ] ];

			if( curInput !== null )
				checked.push( [ 'users-form-currentpw', '/_admin/users/label/currentpw' ] );

			if( Nino.admin.users._check( form, checked, line ) === false ) {
				report( false );
				return;
			}

			const mail = mailInput.value.trim();
			const pw 	 = pwInput.value;

			const payload = { username : user.mail, mail : mail, pw : pw };

			// Only a change is sent: a role posted unchanged would ask the
			// server for the checks of a role change it is not making
			const role = dc.getElementById('users-form-role-select');
			if( Nino.admin.users._roleChanged() === true )
				payload.role = role.value;

			// ...and so is a name: sent when it changed, '' to take it away
			const nameInput = dc.getElementById('users-form-name');
			if( Nino.admin.users._nameChanged() === true )
				payload.name = nameInput.value.trim();

			if( user.isSelf === true )
				payload.currentPassword = curInput.value;

			line.saving();

			Nino.admin.users._apiCall( 'save', payload, function( status, response ) {

				if( status !== 200 ) {
					Nino.admin.users._refused( form, line, status, response, '/_admin/users/error/save' );
					report( false );
					return;
				}

				user.mail = response.mail;

				if( typeof response.name === 'string' ) {
					user.name = response.name;
					nameInput.value = response.name;
				}

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

				pwInput.value = '';
				if( curInput !== null )
					curInput.value = '';

				line.saved();
				Nino.admin.router.set( 'users', [ user.mail ] );
				Nino.admin.users._snapshot();
				report( true );

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

			const send = function() {

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
					Nino.adminUi.say( Nino.content.getText( active === true ? '/_admin/users/msg/activated' : '/_admin/users/msg/deactivated' ) );
				} );
			};

			if( active === true ) {
				send();
				return;
			}

			Nino.adminUi.confirm( 'big', {
				title			: Nino.content.getText('/_admin/users/confirm/deactivate-title'),
				message		: Nino.content.getText('/_admin/users/confirm/deactivate'),
				visitors	: false,
				yes				: Nino.content.getText('/_admin/users/confirm/deactivate-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {
					if( ok === true )
						send();
				},
			} );
		},

		/**
		 *	Delete the current user, after confirmation, and return to the list
		 *
		 *	@return		void
		 */
		_delete : function() {
			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/users/confirm/delete-title'),
				message		: Nino.content.getText('/_admin/users/confirm/delete'),
				yes				: Nino.content.getText('/_admin/users/confirm/delete-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
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
						Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/deleted') );
					} );
				},
			} );
		},

		/**
		 *	Log the current user out of every session, after confirmation
		 *
		 *	@return		void
		 */
		_logoutAll : function() {

			Nino.adminUi.confirm( 'big', {
				title			: Nino.content.getText('/_admin/users/confirm/logoutall-title'),
				message		: Nino.content.getText('/_admin/users/confirm/logoutall'),
				visitors	: false,
				yes				: Nino.content.getText('/_admin/users/confirm/logoutall-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
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

							Nino.adminUi.say( Nino.content.getText('/_admin/users/msg/loggedout') );
						} );
					};

					if( user.isSelf === true && typeof Nino.admin.dirty === 'object' )
						Nino.admin.dirty.guard( null, request );
					else
						request();
				},
			} );
		},
	};

	// The shell asks before anything throws the open account's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'users', function() { return dc.getElementById('users-form') }, Nino.admin.users._saveOpen, Nino.admin.users._bar );

})(window, document, document.documentElement, document.body);
