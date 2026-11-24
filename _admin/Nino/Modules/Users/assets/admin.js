

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js								"Users" panel: change your own mail/password (with
 *													current-password confirmation), or - given the manage
 *													permission - anyone's, create accounts with a role,
 *													change which role an account holds (never your own,
 *													in the same save as the address and the password),
 *													deactivate and activate accounts and delete them.
 *
 *													The panel is the standard directory of the workbench
 *													(Nino.adminUi.directory()) of the accounts - the account,
 *													its name, its role, its state (deactivated, locked) and
 *													its last login -, in the order the server stores them in
 *													until a person sorts. The role is its category, "Show"
 *													narrows to the active, the deactivated or the locked
 *													accounts. An account is edited in the record over the
 *													table (Nino.adminUi.record()): the fields of the account,
 *													Delete user at the left of its bar, Log out everywhere
 *													and Deactivate or Activate beside Save, which writes
 *													address, name, password and - for a manager editing
 *													somebody else - the role in one users/save. A save that
 *													changed nothing sends nothing, and a changed address is
 *													the account's new key. A new account is made in the same
 *													dialog.
 *
 *													Every view has its address: #users is the directory,
 *													#users/users/<mail> the record of an account and
 *													#users/users/@new the form of a new one. What a role grants
 *													is the roles tab beside this one (roles.js), lifting a
 *													lock the login protection tab (lockout.js). An account
 *													that holds a permission the signed-in one does not is
 *													shown in the table with a badge and opens no record: it is
 *													not theirs to change, a click on it says so over the table.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	const word = function( key ) { return Nino.content.getText( key ) };

	Nino.admin.users = {

		// What the last list answered: the accounts as the server sent them, the roles and whether the signed-in account may manage users
		_users				: [],
		_roles				: [],
		_canManage		: false,
		// ...and as rows: what the directory shows of an account, what the record reads
		_rows					: [],
		_ready				: false,
		_loading			: false,
		_directory		: null,
		// The record of an account, and whether it was made with the form of a new one: only an account that may manage users has it
		_record				: null,
		_maker				: false,
		// The button of the bar of the list, only while the account may manage users
		_bar					: null,
		// The groups of the category the directory was drawn with: a role that an account holds for the first time, or none holds any more, is another table
		_shape				: null,
		// What the person sees of the directory - the search, the sort, the page, the selects - kept while it is drawn again
		_state				: {},
		// The line of the open record that says the state of the account, which an action that acts at once draws again
		_stateLine		: null,

		/**
		 *	Load the accounts and draw the directory; an address that names a record opens it once the rows are in
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('users-list') === null || Nino.admin.users._ready === true )
				return;

			Nino.admin.users._load( false );
		},

		/**
		 *	The panel is shown. An address that names an account opens its record, one that names none closes it; the record reads the address itself and answers
		 *	only one that is this panel's. Where it had nothing to say - the panel is entered from another one - and no record is open, the accounts are read again,
		 *	quietly: a role made on the roles tab is in the select of the next record, and the search, the sort and the page stay. A record that is open is never read over
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			const panel = Nino.admin.users;

			if( panel._ready === false )
				return panel.init();

			if( panel._follow() === true || panel._record.isOpen() === true )
				return;

			panel._load( true );
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
		 *	Ask for the list and take the answer in. Drawn the first time with the sentence that it is loading; read again, the directory takes the new rows where it
		 *	stands - its search, its sort and its page are what a person chose
		 *
		 *	@param		{boolean}		quiet				A list that is drawn already is read again
		 *
		 *	@return		void
		 */
		_load : function( quiet ) {

			const panel = Nino.admin.users;

			if( panel._loading === true )
				return;

			panel._loading = true;

			if( quiet === false || panel._directory === null ) {
				panel._frame();
				panel._directory.loading( word('/_admin/users/loading') );
			}

			panel._apiCall( 'list', {}, function( status, response ) {

				panel._loading = false;

				if( status !== 200 || response === null || typeof response !== 'object' || Array.isArray( response.users ) === false ) {
					panel._ready = false;
					return panel._directory.fail( status, response, '/_admin/users/error/load' );
				}

				panel._take( response );
				panel._ready = true;
				panel._directory.notice('');
				panel._draw();

				// An address that names an account, or the form of a new one, is followed once the rows are in
				panel._follow();
			} );
		},

		/**
		 *	Take the answer of the list in: the accounts, the roles, whether the signed-in account may manage users - which is whether there is a form that makes one
		 *
		 *	@param		{Object}		response			{ users, roles, canManage }
		 *
		 *	@return		void
		 */
		_take : function( response ) {

			const panel = Nino.admin.users;

			panel._users = response.users;
			panel._roles = Array.isArray( response.roles ) === true ? response.roles : [];
			panel._canManage = response.canManage === true;
			panel._rowsOf();

			// The record is made once the account is known to may manage users or not; one that is open is not made again under the person's hands
			if( panel._record === null || ( panel._maker !== panel._canManage && panel._record.isOpen() === false ) )
				panel._record = panel._makeRecord();
		},

		/**
		 *	The rows of the accounts, from what the panel holds
		 *
		 *	@return		void
		 */
		_rowsOf : function() {
			Nino.admin.users._rows = Nino.admin.users._users.map( Nino.admin.users._row );
		},

		/**
		 *	The row of an account: what the table shows of it, and the account itself for the record to read
		 *
		 *	@param		{Object}		user					One entry of the answer
		 *
		 *	@return		{Object}
		 */
		_row : function( user ) {

			const panel = Nino.admin.users;
			const held = panel._roles.find( function( role ) { return role.id === user.role } );

			const row = {
				key					: user.mail,
				mail				: user.mail,
				entry				: user,
				name				: typeof user.name === 'string' ? user.name : '',
				role				: typeof user.role === 'string' ? user.role : '',
				roleLabel		: panel._roleLabel( user ),
				// The category the account stands under: its role where the role is one there is, else full access or none
				group				: held !== undefined ? held.id : ( ( user.perms || [] ).indexOf('/*') !== -1 ? '@full' : '@none' ),
				status			: user.status === 'disabled' ? 'disabled' : 'active',
				locked			: typeof user.locked === 'string' ? user.locked : '',
				lastLogin		: typeof user.lastLogin === 'string' ? user.lastLogin : '',
				isSelf			: user.isSelf === true,
				canChange		: user.canChange !== false,
			};

			// What the badges of the state say, as one text: the cell is empty where none applies
			row.stateText = panel._badges( row ).map( function( badge ) { return badge[0] } ).join(' · ');

			return row;
		},

		/**
		 *	What the table says of the state of an account, as words that a badge draws: that it is deactivated, until when it is locked, and that it is beyond the
		 *	signed-in account's reach
		 *
		 *	@param		{Object}		row
		 *
		 *	@return		{Array}										[ [ words, state of the badge, tooltip where the words are short for it ] ], none where none applies
		 */
		_badges : function( row ) {

			const badges = [];

			if( row.status === 'disabled' )
				badges.push( [ word('/_admin/users/label/status-disabled'), 'muted' ] );

			if( row.locked !== '' )
				badges.push( [ word('/_admin/users/badge/lockedshort'), 'warn', Nino.adminUi.format( word('/_admin/users/badge/locked'), Nino.adminUi.dateText( row.locked ) || row.locked ) ] );

			if( row.canChange === false )
				badges.push( [ word('/_admin/users/badge/beyond'), 'muted' ] );

			return badges;
		},

		/**
		 *	The row of an account that the panel holds and the record may open: one that is the signed-in account's to change
		 *
		 *	@param		{string}		key
		 *
		 *	@return		{Object|null}
		 */
		_find : function( key ) {

			const row = Nino.admin.users._lookup( key );

			return row !== null && row.canChange === true ? row : null;
		},

		/**
		 *	The row of an account that the panel holds, one beyond the reach of the signed-in account too
		 *
		 *	@param		{string}		key
		 *
		 *	@return		{Object|null}
		 */
		_lookup : function( key ) {
			return Nino.admin.users._rows.find( function( row ) { return row.key === key } ) ?? null;
		},

		/**
		 *	The row of an account that is made - the form of a new one: nothing but the role the account starts with, the first one that may be handed out
		 *
		 *	@return		{Object}
		 */
		_blank : function() {

			const first = Nino.admin.users._roles.find( function( role ) { return role.canGrant !== false } );

			return Nino.admin.users._row( { mail : '', name : '', role : first ? first.id : '', perms : [], status : 'active', locked : '', lastLogin : '', isSelf : false, canChange : true } );
		},

		/**
		 *	What the list and the record say about an account's role: the role's
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
		 *	What the table and the record say of an account beside its role, as
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
		 *	The name field of the form of a new account and of an account. The input holds at most
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
		 *	The email field of the form of a new account and of an account
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
		 *	A line of text a record shows and does not take: the role of an account that is not the signed-in account's to give, the role of one's own account. Where it has
		 *	a sentence to say - the own role of a manager is another manager's to change - the sentence stands behind the (?) at its name, as that of a field
		 *
		 *	@param		{string}	label
		 *	@param		{string}	value
		 *	@param		{string}	[help]
		 *
		 *	@return		{Element}
		 */
		_readLine : function( label, value, help ) {

			const line = Nino.adminUi.readLine( { label : label, value : value } );

			if( help !== undefined )
				line.children[0].appendChild( Nino.adminUi.fieldHelp( help, { field : line, name : label } ) );

			return line;
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
		 *	Check the fields of the record before a request goes out: each wrong one says what is wrong under it. The browser's own checking is off (the record is
		 *	novalidate), so its bubble in its own language never speaks. The record puts the focus on the first field that is marked
		 *
		 *	@param		{Element}		form
		 *	@param		{Array}			fields				[ [ the input, fill of the name of the field ], ... ] in the order the fields stand
		 *
		 *	@return		{boolean}									Whether every field is fine
		 */
		_check : function( form, fields ) {

			Nino.adminUi.clearFieldErrors( form );

			let wrong = false;

			fields.forEach( function( field ) {

				const sentence = Nino.admin.users._problem( field[0], Nino.content.getText( field[1] ) );

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( field[0].closest('label'), field[0], 'users-error-'+ field[0].id, sentence );
				wrong = true;
			} );

			return wrong === false;
		},

		/**
		 *	Say what the server refused: under the field it named, where the record has one, and as the sentence the line of the bar says
		 *
		 *	@param		{Element}		form
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *	@param		{string}		fallback			The fill of the sentence for a refusal without a code
		 *
		 *	@return		{string}									The sentence
		 */
		_refused : function( form, status, response, fallback ) {

			Nino.adminUi.clearFieldErrors( form );

			const sentence = Nino.adminUi.api.errorText( status, response, fallback );
			const name = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';
			const input = name === '' || /^[A-Za-z]+$/.test( name ) === false ? null : form.querySelector( '[data-field="'+ name+ '"]' );

			if( input !== null )
				Nino.adminUi.fieldError( input.closest('label'), input, 'users-error-'+ input.id, sentence );

			return sentence;
		},

		/**
		 *	The room the panel is drawn in: the directory, and under it the bar of the list. The record stands over it
		 *
		 *	@return		void
		 */
		_frame : function() {

			const panel = Nino.admin.users;

			if( panel._directory !== null )
				return;

			const wrap = dc.getElementById('users-list');

			wrap.innerHTML = '';

			const mount = Nino.adminUi.el( 'div' );

			mount.id = 'users-directory';
			wrap.appendChild( mount );
			panel._directory = panel._build( mount, panel._state );
		},

		/**
		 *	Draw what the last answer holds: the rows of the directory - drawn anew where the roles the accounts hold are other than they were, since the category is
		 *	one choice of them - and the bar of the list, which only an account that may manage users has
		 *
		 *	@return		void
		 */
		_draw : function() {

			const panel = Nino.admin.users;
			const shape = JSON.stringify( panel._groups().map( function( group ) { return group.value } ) );

			if( panel._shape !== shape ) {

				const state = panel._directory.state();

				// A category the accounts no longer fall under is none that a person could choose again: the table shows every account
				if( panel._groups().some( function( group ) { return group.value === state.category } ) === false )
					state.category = '';

				panel._directory = panel._build( dc.getElementById('users-directory'), state );
				panel._shape = shape;
			}

			panel._directory.setRows( panel._rows, { keep : true } );
			panel._barDraw();
		},

		/**
		 *	The bar of the list: "New user" as its main button, for an account that may manage users - for any other there is no form that makes one, and no bar
		 *
		 *	@return		void
		 */
		_barDraw : function() {

			const panel = Nino.admin.users;

			if( panel._canManage === false ) {

				if( panel._bar !== null ) {
					panel._bar.remove();
					panel._bar = null;
				}

				return;
			}

			if( panel._bar !== null )
				return;

			const add = Nino.adminUi.button( word('/_admin/users/label/new'), function() { panel._record.create( { opener : add } ) } );

			panel._bar = Nino.adminUi.actionBar( { list : true, primary : add } ).el;
			dc.getElementById('users-list').appendChild( panel._bar );
		},

		/**
		 *	What the accounts stand under in the category of the directory: every role an account holds, in the order the roles are listed in, then full access and no
		 *	role where accounts fall under them. Nothing - no category - where they are all under one: a select with one choice narrows nothing
		 *
		 *	@return		{Array}										[ { value, label } ]
		 */
		_groups : function() {

			const panel = Nino.admin.users;
			const held = {};

			panel._rows.forEach( function( row ) { held[row.group] = true } );

			const groups = panel._roles.filter( function( role ) { return held[role.id] === true } ).map( function( role ) { return { value : role.id, label : role.label } } );

			if( held['@full'] === true )
				groups.push( { value : '@full', label : word('/_admin/users/label/fullaccess') } );

			if( held['@none'] === true )
				groups.push( { value : '@none', label : word('/_admin/users/label/norole') } );

			return groups.length > 1 ? groups : [];
		},

		/**
		 *	The directory: the category - the role -, the search, the select "Show", and the table of the accounts in the order the server stores them in, with the
		 *	pencil of an account that can be changed. There is no box to choose rows by: nothing here is done to several accounts at once
		 *
		 *	@param		{Element}		mount
		 *	@param		{Object}		[state]				What the person saw before
		 *
		 *	@return		{Object}
		 */
		_build : function( mount, state ) {

			const panel = Nino.admin.users;
			const groups = panel._groups();

			return Nino.adminUi.directory( {
				mount : mount,
				rowKey : 'key',
				state : state,
				onState : function( now ) { panel._state = now },
				columns : [
					{ key : 'mail', label : word('/_admin/users/col/account'), type : 'string', primary : true, render : panel._accountCell },
					{ key : 'name', label : word('/_admin/users/label/name'), type : 'string', cell : 'clamp', max : '14rem', narrow : 'second' },
					{ key : 'roleLabel', label : word('/_admin/users/label/role'), type : 'string', cell : 'clamp', max : '10rem' },
					{ key : 'stateText', label : word('/_admin/users/label/state'), type : 'string', sortable : false, render : panel._stateCell },
					{ key : 'lastLogin', label : word('/_admin/users/col/lastlogin'), type : 'date', cell : 'date' },
				],
				labels : { search : word('/_admin/users/label/search'), empty : word('/_admin/users/empty'), noMatch : word('/_admin/users/nomatch'), loading : word('/_admin/users/loading') },
				category : groups.length === 0 ? undefined : {
					id : 'users-role',
					label : word('/_admin/users/label/role'),
					value : '',
					options : [ { value : '', label : word('/_admin/users/filter/allroles') } ].concat( groups ),
					match : function( row, value ) { return value === '' || row.group === value },
				},
				filters : [ {
					id : 'users-show',
					label : word('/_admin/users/filter/show'),
					value : '',
					options : [
						{ value : '', label : word('/_admin/users/filter/all') },
						{ value : 'active', label : word('/_admin/users/filter/active') },
						{ value : 'disabled', label : word('/_admin/users/label/status-disabled') },
						{ value : 'locked', label : word('/_admin/users/filter/locked') },
					],
					match : function( row, value ) {

						if( value === 'active' )
							return row.status === 'active';

						if( value === 'disabled' )
							return row.status === 'disabled';

						return value !== 'locked' || row.locked !== '';
					},
				} ],
				// What a person looks for: the address, the name, the name of the role - whatever the cells draw of them
				searchText : function( row ) { return [ row.mail, row.name, row.roleLabel ].join(' ') },
				actions : panel._actions,
				// A row is opened by its first button as well: the row is not a stop of the Tab key where it holds buttons. An account that is not the signed-in one's to change
				// has no button, and its row says why where it is pressed
				onOpen : function( row ) {

					if( row.canChange === false )
						return panel._directory.notice( word('/_admin/users/label/beyond') );

					panel._record.open( row.key, {} );
				},
			} );
		},

		/**
		 *	The account in the table: its address strong, and for the signed-in account "(you)" behind it, quietly - both as text
		 *
		 *	@param		{string}		value
		 *	@param		{Object}		row
		 *
		 *	@return		{Element}
		 */
		_accountCell : function( value, row ) {

			const cell = Nino.adminUi.el( 'span', 'users-account' );

			// A long address ends in an ellipsis, whole in its tooltip
			cell.title = row.mail;

			cell.appendChild( Nino.adminUi.el( 'strong', '', row.mail ) );

			if( row.isSelf === true )
				cell.appendChild( Nino.adminUi.el( 'span', 'users-you', ' ('+ word('/_admin/users/label/you')+ ')' ) );

			return cell;
		},

		/**
		 *	What the table says of the state of an account, as words in badges: that it is deactivated, until when it is locked, and that it is beyond the signed-in
		 *	account's reach. Nothing for an account none of it applies to
		 *
		 *	@param		{string}		value
		 *	@param		{Object}		row
		 *
		 *	@return		{Element|string}
		 */
		_stateCell : function( value, row ) {

			const badges = Nino.admin.users._badges( row );

			if( badges.length === 0 )
				return '';

			const cell = Nino.adminUi.el( 'span', 'users-states' );

			badges.forEach( function( badge ) {

				const el = Nino.adminUi.el( 'span', 'nino-admin-badge', badge[0] );

				el.dataset.state = badge[1];

				if( typeof badge[2] === 'string' )
					el.title = badge[2];

				cell.appendChild( el );
			} );

			return cell;
		},

		/**
		 *	The button at the end of a row: edit, which opens the row - for an account that can be changed; there is none for one that cannot
		 *
		 *	@param		{Object}		row
		 *
		 *	@return		{Array}
		 */
		_actions : function( row ) {

			const panel = Nino.admin.users;

			if( row.canChange === false )
				return [];

			return [ Nino.adminUi.iconButton( 'pencil', Nino.adminUi.format( word('/_admin/users/row/open'), row.mail ), function( event ) {
				panel._record.open( row.key, { opener : event.currentTarget } );
			}, { small : true } ) ];
		},

		/**
		 *	The directory as the record sees it: what is on screen now, whatever drew it last
		 *
		 *	@return		{Object}
		 */
		_accountDirectory : function() {

			const panel = Nino.admin.users;

			return {
				// The record walks the accounts that open: the steps skip the ones beyond reach, and the place counts only those
				rows : function() { return panel._directory.rows().filter( function( row ) { return row.canChange === true } ) },
				reveal : function( key ) { return panel._directory.reveal( key ) },
				focusRow : function( key ) { return panel._directory.focusRow( key ) },
				focusSearch : function() { return panel._directory.focusSearch() },
			};
		},

		/**
		 *	The record of an account: a dialog over the directory with the address of the account in its head and its name under it, the fields of the account, "Delete
		 *	user" at the left of the bar, "Log out everywhere" and "Deactivate account" or "Activate account" beside "Save". The form of a new account is the same dialog
		 *	(`create`, for an account that may manage users), and a save that changes the address gives the record the new one (`rekey`). There is no "Save and continue":
		 *	an account is saved for itself
		 *
		 *	@return		{Object}
		 */
		_makeRecord : function() {

			const panel = Nino.admin.users;

			panel._maker = panel._canManage;

			const options = {
				screen : 'users',
				prefix : [],
				directory : panel._accountDirectory(),
				keyOf : function( row ) { return row.key },
				find : panel._find,
				title : function( row ) { return row.mail+ ( row.isSelf === true ? ' ('+ word('/_admin/users/label/you')+ ')' : '' ) },
				subtitle : function( row ) { return row.name },
				rekey : true,
				labels : { prev : word('/_admin/users/record/prev'), next : word('/_admin/users/record/next'), createTitle : word('/_admin/users/label/new'), createSave : word('/_admin/users/label/create'), created : word('/_admin/users/msg/created') },
				body : panel._body,
				save : panel._save,
				danger : { label : word('/_admin/users/label/delete'), hidden : panel._noDelete, run : panel._remove },
				actions : [
					{ label : word('/_admin/users/label/logoutall'), run : panel._logoutAll },
					{ label : word('/_admin/users/label/deactivate'), hidden : function( row ) { return panel._noToggle( row ) === true || row.status === 'disabled' }, run : function( row, ctx ) { panel._toggle( row, ctx, false ) } },
					{ label : word('/_admin/users/label/activate'), hidden : function( row ) { return panel._noToggle( row ) === true || row.status !== 'disabled' }, run : function( row, ctx ) { panel._toggle( row, ctx, true ) } },
				],
				// An address that names an account the signed-in one may not change, or none at all: said over the directory
				missing : function( key ) {

					const known = panel._lookup( key );

					panel._directory.notice( known !== null && known.canChange === false ? word('/_admin/users/label/beyond') : word('/_admin/users/record/missing') );
				},
			};

			if( panel._canManage === true )
				options.create = { blank : panel._blank };

			return Nino.adminUi.record( options );
		},

		/**
		 *	The address names what the panel answers: the form of a new account is for an account that may manage users - for any other the address is the directory's, with
		 *	nothing opened and nothing asked of the server - and every other address is the record's
		 *
		 *	@return		{boolean}									Whether the address was answered
		 */
		_follow : function() {

			const panel = Nino.admin.users;
			const router = Nino.admin.router;
			const at = router.current();

			if( at.panel === 'users' && panel._canManage === false && panel._record.isOpen() === false && at.parts.length === 1 && router.isWord( at.parts, 0, 'new' ) === true ) {
				panel._directory.notice('');
				router.set( 'users', [] );

				return true;
			}

			return panel._record.follow();
		},

		/**
		 *	Whether the account may not be deleted from its record: a delete is for an account that may manage users, and never for one's own
		 *
		 *	@param		{Object}		row
		 *
		 *	@return		{boolean}
		 */
		_noDelete : function( row ) {
			return Nino.admin.users._canManage !== true || row.isSelf === true;
		},

		/**
		 *	Whether the account has no button that deactivates or activates it: that is for an account that may manage users, and never for one's own
		 *
		 *	@param		{Object}		row
		 *
		 *	@return		{boolean}
		 */
		_noToggle : function( row ) {
			return Nino.admin.users._canManage !== true || row.isSelf === true;
		},

		/**
		 *	The content of the record: the state of the account to read, and the fields - the address, the name, the role, the new password and, for the signed-in account,
		 *	the current password -, or those of a new account. What is typed stays in the fields: the record finds them by the name the server marks them by
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *
		 *	@return		{Element}
		 */
		_body : function( row, ctx ) {

			const panel = Nino.admin.users;
			const body = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );

			panel._directory.notice('');

			if( ctx.creating === true ) {

				panel._stateLine = null;
				body.appendChild( panel._mailField( 'users-create-mail', '' ) );
				body.appendChild( panel._nameField( 'users-create-name', '' ) );
				body.appendChild( panel._passwordField( { id : 'users-create-pw', field : 'pw', label : word('/_admin/users/label/password'), help : word('/_admin/users/hint/password'), required : true, minLength : 8, autocomplete : 'new-password' } ) );
				body.appendChild( panel._roleSelect( 'users-create-role', row.role ) );

				return body;
			}

			// Whether the account is deactivated or locked, and its last login
			panel._stateLine = Nino.adminUi.el( 'span', '', panel._stateParts( row.entry ).join(' · ') );
			body.appendChild( Nino.adminUi.readLine( { label : word('/_admin/users/label/state'), node : panel._stateLine } ) );

			body.appendChild( panel._mailField( 'users-form-mail', row.mail ) );
			body.appendChild( panel._nameField( 'users-form-name', row.name ) );
			body.appendChild( panel._roleField( row ) );
			body.appendChild( panel._passwordField( { id : 'users-form-pw', field : 'pw', label : word('/_admin/users/label/newpw'), help : word('/_admin/users/hint/newpw'), minLength : 8, autocomplete : 'new-password' } ) );

			if( row.isSelf === true )
				body.appendChild( panel._passwordField( { id : 'users-form-currentpw', field : 'currentPassword', label : word('/_admin/users/label/currentpw'), help : word('/_admin/users/hint/currentpw'), required : true, autocomplete : 'current-password' } ) );

			return body;
		},

		/**
		 *	The role of the account in the record: a select a manager may change - never for their own account, same as the backend refuses (see Users\Admin::apiSave()):
		 *	log out and ask another manager - and a line of text for everyone else. What a role grants is the roles tab beside this panel, not this record
		 *
		 *	@param		{Object}		row
		 *
		 *	@return		{Element}
		 */
		_roleField : function( row ) {

			const panel = Nino.admin.users;

			if( panel._canManage !== true || row.isSelf === true )
				return panel._readLine( word('/_admin/users/label/role'), row.roleLabel, panel._canManage === true && row.isSelf === true ? word('/_admin/users/label/role-self') : undefined );

			const field = panel._roleSelect( 'users-form-role-select', row.role );
			const select = field.querySelector('select');

			// The role the account holds, to tell a change from what is stored
			select.dataset.saved = select.value;

			return field;
		},

		/**
		 *	Save what the record holds: a new account is created, an account is written - address, name, password and the role, where it changed, in one request. What
		 *	was not changed sends nothing. What is wrong with a field is said at the field before anything is sent, and what the server refuses stands at the field it names
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{Function}	done					done( ok, { row, error } )
		 *
		 *	@return		void
		 */
		_save : function( row, ctx, done ) {

			const panel = Nino.admin.users;

			if( ctx.creating === true )
				return panel._create( ctx, done );

			const current = panel._find( row.key ) ?? row;
			const form = ctx.form;
			const field = function( name ) { return form.querySelector( '[data-field="'+ name+ '"]' ) };
			const mailInput = field('mail');
			const nameInput = field('name');
			const pwInput = field('pw');
			const curInput = field('currentPassword');
			const role = field('role');

			const mail = mailInput.value.trim();
			const nameChanged = nameInput.value.trim() !== current.name;
			// Only a change is sent: a role posted unchanged would ask the server for the checks of a role change it is not making
			const roleChanged = role !== null && role.value !== role.dataset.saved;

			// Nothing was changed: there is nothing to write, and nothing to confirm with the current password
			// ...unless the own account typed its current password: that is asked for a save with a confirmation, and the server answers it
			if( mail === current.mail && pwInput.value === '' && nameChanged === false && roleChanged === false && ( curInput === null || curInput.value === '' ) )
				return done( true, { row : current } );

			const checked = [ [ mailInput, '/_admin/users/label/mail' ], [ pwInput, '/_admin/users/label/newpw' ] ];

			if( curInput !== null )
				checked.push( [ curInput, '/_admin/users/label/currentpw' ] );

			if( panel._check( form, checked ) === false )
				return done( false, { error : word('/_admin/users/error/fields') } );

			const payload = { username : current.mail, mail : mail, pw : pwInput.value };

			if( roleChanged === true )
				payload.role = role.value;

			// ...and so is a name: sent when it changed, '' to take it away
			if( nameChanged === true )
				payload.name = nameInput.value.trim();

			if( current.isSelf === true )
				payload.currentPassword = curInput.value;

			panel._apiCall( 'save', payload, function( status, response ) {

				if( status !== 200 || response === null || typeof response !== 'object' || typeof response.mail !== 'string' )
					return done( false, { error : panel._refused( form, status, response, '/_admin/users/error/save' ) } );

				Nino.adminUi.clearFieldErrors( form );

				const user = Object.assign( {}, current.entry, { mail : response.mail } );

				if( typeof response.name === 'string' ) {
					user.name = response.name;
					nameInput.value = response.name;
				}

				if( typeof response.role === 'string' ) {
					user.role = response.role;
					// What the select shows is what counts as stored: a role id that is no longer in the config is not an option, and it would otherwise read as a change on
					// the next save
					if( role !== null ) {
						role.value = response.role;
						role.dataset.saved = role.value;
					}
				}

				// The rail names the account the page is for - the api compares a session it finds later against it
				const rail = current.isSelf === true ? dc.getElementById('admin-user-email') : null;

				if( rail !== null )
					rail.textContent = response.mail;

				// A password is not kept: it was sent, and no field of the record holds it any more
				pwInput.value = '';

				if( curInput !== null )
					curInput.value = '';

				mailInput.value = response.mail;

				panel._users = panel._users.map( function( one ) { return one === current.entry ? user : one } );
				panel._rowsOf();
				panel._draw();
				panel._directory.update( panel._rows, response.mail );
				done( true, { row : panel._find( response.mail ) } );
			} );
		},

		/**
		 *	Create the account the form holds. The list is read again once it is made, so that the flags of the new account - which the server decides - are the ones on
		 *	screen, and the record is the record of that account
		 *
		 *	@param		{Object}		ctx
		 *	@param		{Function}	done					done( ok, { row, error } )
		 *
		 *	@return		void
		 */
		_create : function( ctx, done ) {

			const panel = Nino.admin.users;
			const form = ctx.form;
			const field = function( name ) { return form.querySelector( '[data-field="'+ name+ '"]' ) };
			const mailInput = field('mail');
			const nameInput = field('name');
			const pwInput = field('pw');
			const role = field('role');

			if( panel._check( form, [ [ mailInput, '/_admin/users/label/mail' ], [ pwInput, '/_admin/users/label/password' ] ] ) === false )
				return done( false, { error : word('/_admin/users/error/fields') } );

			panel._apiCall( 'create', { mail : mailInput.value.trim(), name : nameInput.value.trim(), pw : pwInput.value, role : role.value }, function( status, response ) {

				if( status !== 200 || response === null || typeof response !== 'object' || typeof response.mail !== 'string' )
					return done( false, { error : panel._refused( form, status, response, '/_admin/users/error/create' ) } );

				// A password is not kept: it was sent, and the form that held it is drawn anew for the account
				pwInput.value = '';
				Nino.adminUi.clearFieldErrors( form );

				panel._apiCall( 'list', {}, function( listStatus, listResponse ) {

					if( listStatus === 200 && listResponse !== null && typeof listResponse === 'object' && Array.isArray( listResponse.users ) === true )
						panel._take( listResponse );

					let made = panel._lookup( response.mail );

					// The account exists: only the list could not be read again, and a save that is reported as failed would be tried again. It is a row as the server makes
					// it - active, never logged in - and the table says that the list is not what the server holds
					if( made === null ) {
						panel._users = panel._users.concat( [ { mail : response.mail, name : typeof response.name === 'string' ? response.name : '', role : typeof response.role === 'string' ? response.role : '', perms : [], status : 'active', locked : '', lastLogin : '', isSelf : false, canChange : true } ] );
						panel._rowsOf();
						made = panel._lookup( response.mail );
					}

					panel._draw();
					panel._directory.update( panel._rows, response.mail );
					done( true, { row : panel._find( response.mail ) ?? made } );

					// After the record was drawn for the account, which clears what stood over the table
					if( listStatus !== 200 )
						panel._directory.notice( Nino.adminUi.api.errorText( listStatus, listResponse, '/_admin/users/error/load' ) );
				} );
			} );
		},

		/**
		 *	Say a refusal of an action that acts at once, where the person looks: in the line of the record while the record still shows that account, else over the table
		 *	with the account named - the record has gone on to another one by then, and an error is never a toast
		 *
		 *	@param		{Object}		ctx
		 *	@param		{string}		mail					The account the action was for
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_refusal : function( ctx, mail, status, response ) {

			const panel = Nino.admin.users;
			const sentence = Nino.adminUi.api.errorText( status, response, '/_admin/users/error/save' );

			if( panel._record.key() === mail )
				return ctx.status.fail( sentence );

			panel._directory.notice( Nino.adminUi.format( word('/_admin/users/error/late'), mail, sentence ) );
		},

		/**
		 *	Deactivate or activate the account the record shows - a deactivation after confirmation, since it ends every session the account holds. It acts at once, and
		 *	the record stays as it is with what was typed in it: only the state line, the two buttons and the row of the table are drawn again
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{boolean}		active				Whether the account is to be activated
		 *
		 *	@return		void
		 */
		_toggle : function( row, ctx, active ) {

			const panel = Nino.admin.users;
			const current = panel._find( row.key ) ?? row;
			const button = ctx.opener;

			const send = function() {

				button.disabled = true;

				panel._apiCall( 'status', { username : current.mail, active : active }, function( status, response ) {

					button.disabled = false;

					if( status !== 200 || response === null || typeof response !== 'object' )
						return panel._refusal( ctx, current.mail, status, response );

					const live = panel._find( current.mail );

					if( live !== null ) {

						const user = Object.assign( {}, live.entry, { status : response.status } );

						panel._users = panel._users.map( function( one ) { return one === live.entry ? user : one } );
						// The row keeps its identity: the record holds it, and asks it whether a button fits the account
						Object.assign( live, panel._row( user ) );
						panel._draw();
						panel._directory.update( panel._rows, live.key );
					}

					// The record that is still the account's: its line and its buttons say the new state, and the focus stays on the pair
					if( live !== null && panel._record.key() === live.key ) {

						if( panel._stateLine !== null )
							panel._stateLine.textContent = panel._stateParts( live.entry ).join(' · ');

						const other = Array.from( ctx.bar.el.querySelectorAll('button') ).find( function( one ) {
							return one.textContent === word( active === true ? '/_admin/users/label/deactivate' : '/_admin/users/label/activate' );
						} );

						if( other !== undefined ) {
							button.hidden = true;
							other.hidden = false;
							other.focus();
						}

						if( ctx.status.state === 'error' )
							ctx.status.idle();
					}

					Nino.adminUi.say( word( active === true ? '/_admin/users/msg/activated' : '/_admin/users/msg/deactivated' ) );
				} );
			};

			if( active === true )
				return send();

			Nino.adminUi.confirm( 'big', {
				title			: word('/_admin/users/confirm/deactivate-title'),
				message		: Nino.content.getText('/_admin/users/confirm/deactivate'),
				visitors	: false,
				yes				: word('/_admin/users/confirm/deactivate-title'),
				no				: word('/_admin/common/label/cancel'),
				nested		: true,
				opener		: button,
				onChoose	: function( ok ) {
					if( ok === true )
						send();
				},
			} );
		},

		/**
		 *	Delete the account the record shows, after confirmation. The question stands on the record; the record is closed by it and the focus goes to the next row. A
		 *	question that is answered No sends nothing
		 *
		 *	@param		{Object}		row
		 *	@param		{Element}		opener					The button that asked, which has the focus again when the question is closed
		 *	@param		{Function}	done						done( true ) where it was deleted, done( null ) where the answer was no, done( false, { error } )
		 *
		 *	@return		void
		 */
		_remove : function( row, opener, done ) {

			const panel = Nino.admin.users;
			const current = panel._find( row.key ) ?? row;

			Nino.adminUi.confirm( 'destructive', {
				title			: word('/_admin/users/confirm/delete-title'),
				message		: Nino.content.getText('/_admin/users/confirm/delete'),
				yes				: word('/_admin/users/confirm/delete-title'),
				no				: word('/_admin/common/label/cancel'),
				nested		: true,
				opener		: opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return done( null );

					panel._apiCall( 'delete', { username : current.mail }, function( status, response ) {

						if( status !== 200 )
							return done( false, { error : Nino.adminUi.api.errorText( status, response, '/_admin/users/error/save' ) } );

						panel._users = panel._users.filter( function( one ) { return one !== current.entry } );
						panel._rowsOf();
						panel._draw();
						done( true );
					} );
				},
			} );
		},

		/**
		 *	Log the account the record shows out of every session, after confirmation. For the signed-in account that ends this page's session as well, and with it every
		 *	form on it: the unsaved input of all of them is asked about first, and the page is loaded again to the login form once it is done
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *
		 *	@return		void
		 */
		_logoutAll : function( row, ctx ) {

			const panel = Nino.admin.users;
			const current = panel._find( row.key ) ?? row;

			Nino.adminUi.confirm( 'big', {
				title			: word('/_admin/users/confirm/logoutall-title'),
				message		: Nino.content.getText('/_admin/users/confirm/logoutall'),
				visitors	: false,
				yes				: word('/_admin/users/confirm/logoutall-title'),
				no				: word('/_admin/common/label/cancel'),
				nested		: true,
				opener		: ctx.opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					const request = function() {

						panel._apiCall( 'logoutall', { username : current.mail }, function( status, response ) {

							if( status !== 200 || response === null || typeof response !== 'object' )
								return panel._refusal( ctx, current.mail, status, response );

							// Logging out yourself invalidates the current session - reload straight to the login form
							if( response.loggedOutSelf === true ) {
								wn.location.replace( '[[/nino/dir]]/_admin' );
								return;
							}

							Nino.adminUi.say( word('/_admin/users/msg/loggedout') );
						} );
					};

					// Ending your own sessions ends this page's, and with it every form on it: the unsaved input of all of them is asked about first
					if( current.isSelf === true && typeof Nino.admin.dirty === 'object' )
						Nino.admin.dirty.guard( null, request );
					else
						request();
				},
			} );
		},
	};

})(window, document, document.documentElement, document.body);
