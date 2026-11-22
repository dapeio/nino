

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	roles.js								"User roles" tab of the Users panel: the named sets of
 *													permissions an account holds one of. A list of roles,
 *													and a form per role with its name, a full-access switch
 *													and the permissions themselves - every one a panel or tab
 *													offers right now, plus every one this installation
 *													holds without a panel behind it - as a list of ticks for
 *													each group of the rail. The finer ones, per action and
 *													field, are added with three dependent lists out of the
 *													tree the panels offer (scopes()) and ticked off again in
 *													a list of their own, and a summary says in plain words
 *													what the role may do. Every view has its address:
 *													#roles, #roles/new and #roles/<id>. Manager-only, same
 *													gate the backend enforces independently (see
 *													Roles/Roles.php beside it). A role that holds a
 *													permission the signed-in account does not is shown in
 *													words, without a field or a button.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.roles = {

		_roles				: [],
		_permOptions	: [],
		// The order the form lists the groups in - see Roles::apiList()
		_groups				: [],
		// The scoped permissions the panels offer - see Users\Admin::scopeOptions()
		_scopes				: [],
		// The role on the form, null while a new one is being made
		_current			: null,
		// The button that opened the new-role form that is open: Back returns the
		// focus to it (a role's own row is found again, see _rowOf())
		_opener				: null,
		// The controls the form's save reads, as _renderForm() made them
		_parts				: null,
		// The status line of the form that is open - see Nino.adminUi.status()
		_line					: null,
		_loading			: false,
		_ready				: false,

		/**
		 *	Load the roles and the assignable permissions, render the list,
		 *	and open whatever the hash points at
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('roles-list') === null || Nino.admin.roles._loading === true || Nino.admin.roles._ready === true )
				return;

			Nino.admin.roles._loading = true;

			Nino.admin.roles._apiCall( 'list', {}, function( status, response ) {
				Nino.admin.roles._loading = false;
				if( status !== 200 || response === null )
					return Nino.admin.roles._showError( dc.getElementById('roles-list'), status, response );

				// Captured before any _show*() call below can overwrite it
				const hash = Nino.admin.router.current();

				Nino.admin.roles._roles = response.roles;
				Nino.admin.roles._permOptions = response.permOptions;
				Nino.admin.roles._groups = response.groups;
				Nino.admin.roles._scopes = response.scopes || [];
				Nino.admin.roles._renderList();
				Nino.admin.roles._ready = true;

				if( hash.panel === 'roles' && hash.parts.length > 0 ) {
					if( hash.parts[0] === 'new' )
						return Nino.admin.roles._openRole( null );
					if( Nino.admin.roles._roles.some( function( r ) { return r.id === hash.parts[0] } ) === true )
						return Nino.admin.roles._openRole( hash.parts[0] );
				}

				Nino.admin.roles._showList();
			} );
		},

		/**
		 *	Re-apply whatever drill-down level this tab is currently on -
		 *	called when it is selected, so the hash gets synced to reality.
		 *
		 *	Where the hash names this tab, the hash wins: a step through the
		 *	browser's history changes the address and nothing else, so the level
		 *	it names is shown - and leaving a form that holds unsaved input asks
		 *	first, as its back link does. A hash that names another panel (a
		 *	click on the rail) leaves the level in memory as it is
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.roles._ready === false ) {
				Nino.admin.roles.init();
				return;
			}

			const hash = Nino.admin.router.current();
			if( hash.panel === 'roles' && Nino.admin.roles._follow( hash.parts ) === true )
				return;

			Nino.admin.roles._showLevel();
		},

		/**
		 *	Show the level this tab is on, and write it into the address
		 *
		 *	@return		void
		 */
		_showLevel : function() {

			if( dc.getElementById('roles-form').classList.contains('admin-hidden') === false )
				return Nino.admin.roles._showForm();

			Nino.admin.roles._showList();
		},

		/**
		 *	Move to the level the hash names, if it is not the one on screen:
		 *	the new-role form, a role's form or the list. A role that does not
		 *	exist is the list. Leaving a form with unsaved input asks first (see
		 *	Nino.admin.router.leave())
		 *
		 *	@param		{Array}		parts					The hash behind the tab's name
		 *
		 *	@return		{boolean}									Whether a move was made or is being asked about
		 */
		_follow : function( parts ) {

			const open = dc.getElementById('roles-form').classList.contains('admin-hidden') === false;
			const current = Nino.admin.roles._current;
			const create = parts[0] === 'new';
			const role = create === true || parts.length === 0 ? undefined : Nino.admin.roles._roles.find( function( r ) { return r.id === parts[0] } );

			if( create === true ? ( open === true && current === null ) : ( role === undefined ? open === false : ( open === true && current !== null && current.id === role.id ) ) )
				return false;

			Nino.admin.router.leave( [ 'roles' ], open, function() {
				if( create === true )
					Nino.admin.roles._openRole( null );
				else if( role === undefined )
					Nino.admin.roles._showList();
				else
					Nino.admin.roles._openRole( role.id );
			}, Nino.admin.roles._showLevel );

			return true;
		},

		/**
		 *	Call a roles/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "save", becomes "roles/save")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'roles/'+ endpoint, payload, callback );
		},

		/**
		 *	Show a failed request's status/error in a container
		 *
		 *	@param		{Element}		container
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( container, status, response ) {
			Nino.adminUi.showError( container, status, response, '/_admin/roles/error/load' );
		},

		/**
		 *	Drill-down navigation: list -> form. The focus stays where it is,
		 *	unless it stood in the level that is hidden now (see
		 *	Nino.adminUi.showLevel()): the level is shown again as it stands, by
		 *	the address or after a delete. Back goes to the row, and what a person
		 *	opens takes the focus in
		 *
		 *	@param		{Element|null}	[opener]	Back: the control that opened the form that is left; null where there is none to name
		 *
		 *	@return		void
		 */
		_showList : function( opener ) {
			Nino.admin.router.set( 'roles', [] );

			if( opener === undefined )
				Nino.adminUi.showLevel( 'roles-list', { focus : false } );
			else
				Nino.adminUi.backTo( 'roles-list', opener );
		},

		/**
		 *	@param		{boolean}	[opened]	The person opened it: the focus goes into the form
		 *
		 *	@return		void
		 */
		_showForm : function( opened ) {
			Nino.admin.router.set( 'roles', [ Nino.admin.roles._current === null ? 'new' : Nino.admin.roles._current.id ] );
			Nino.adminUi.showLevel( 'roles-form', opened === true ? {} : { focus : false } );
		},

		/**
		 *	The row of a role as the list is drawn now: the list is drawn again
		 *	behind an open form after a save, and the row that was pressed is gone
		 *	with the old drawing
		 *
		 *	@param		{string}	id
		 *
		 *	@return		{Element|null}
		 */
		_rowOf : function( id ) {

			const row = Array.from( dc.getElementById('roles-list').querySelectorAll('li') ).find( function( li ) { return li.dataset.row === id } );

			return row === undefined ? null : row.querySelector('a');
		},

		/**
		 *	The control Back returns the focus to from the form that is open: the
		 *	row of the role, or the button that opened the new-role form
		 *
		 *	@return		{Element|null}
		 */
		_backTarget : function() {
			return Nino.admin.roles._current === null ? Nino.admin.roles._opener : Nino.admin.roles._rowOf( Nino.admin.roles._current.id );
		},

		/**
		 *	Render the role list: name, id, how many accounts hold it and
		 *	how much it grants
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('roles-list');
			wrap.innerHTML = '';

			if( Nino.admin.roles._roles.length === 0 )
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/roles/empty') ) );
			else
				// A row opens a role and writes its address: a link, with that address
				wrap.appendChild( Nino.adminUi.list( Nino.admin.roles._roles.map( function( role ) {
					return {
						key		: role.id,
						title	: role.label,
						detail	: role.id+ ' · '+ Nino.content.getText('/_admin/roles/label/users')+ ': '+ role.users+ ' · '
							+ ( role.perms.indexOf('/*') !== -1 ? Nino.content.getText('/_admin/users/label/fullaccess') : role.perms.length+ ' × '+ Nino.content.getText('/_admin/roles/label/permissions') ),
						href		: Nino.admin.router.href( 'roles', [ role.id ] ),
						onOpen	: function() { Nino.admin.router.go( 'roles', [ role.id ] ); Nino.admin.roles._openRole( role.id, true ) },
					};
				} ), { label : Nino.content.getText('/_admin/nav/roles') } ) );

			const add = Nino.adminUi.button( Nino.content.getText('/_admin/roles/label/new'), function() { Nino.admin.router.go( 'roles', [ 'new' ] ); Nino.admin.roles._opener = add; Nino.admin.roles._openRole( null, true ) } );
			wrap.appendChild( Nino.adminUi.actionBar( { list : true, primary : add } ).el );
		},

		/**
		 *	Open one role's form, or an empty one
		 *
		 *	@param		{string|null}	id
		 *	@param		{boolean}			[opened]	The person opened it - a row, the New button, or the role that was just saved: the focus goes into the form. Otherwise it is shown as it stands, by the address or after a step through the history
		 *
		 *	@return		void
		 */
		_openRole : function( id, opened ) {

			Nino.admin.roles._current = id === null ? null : ( Nino.admin.roles._roles.find( function( r ) { return r.id === id } ) ?? null );
			Nino.admin.roles._renderForm();
			Nino.admin.roles._showForm( opened === true );
		},

		/**
		 *	A line of text a form shows and does not take: the id of a role that cannot be changed, the number of accounts
		 *	that hold it. Drawn as text - no input, no stop of the Tab key - in the place of a field, with the name at its left
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
		 *	Render the form: id (fixed once the role exists - it is what the
		 *	accounts refer to), name, and the permissions
		 *
		 *	@return		void
		 */
		_renderForm : function() {

			const role = Nino.admin.roles._current;
			const wrap = dc.getElementById('roles-form');
			wrap.innerHTML = '';

			wrap.appendChild( Nino.adminUi.contextBar( { back : { to : wrap, onClick : function() {
				Nino.admin.router.go( 'roles', [] );
				Nino.admin.roles._showList( Nino.admin.roles._backTarget() );
			} } } ) );

			// A role that holds what the signed-in account does not is not theirs
			// to change (see Roles::beyond()): shown, with nothing to press
			if( role !== null && role.canChange === false )
				return Nino.admin.roles._renderBeyond( wrap, role );

			const form = dc.createElement('form');
			form.id = 'roles-edit-form';
			form.noValidate = true;

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = role === null ? Nino.content.getText('/_admin/roles/label/new') : role.label;
			fieldset.appendChild( legend );

			const idField = Nino.adminUi.textField( {
				id					: 'roles-form-id',
				label				: Nino.content.getText('/_admin/roles/label/id'),
				help				: Nino.content.getText('/_admin/roles/hint/id'),
				value				: role === null ? '' : role.id,
				required		: true,
				maxLength		: 40,
				autocomplete	: 'off',
			} );
			const idInput = idField.querySelector('input');
			idInput.spellcheck = false;
			idInput.disabled = role !== null;
			fieldset.appendChild( idField );

			const nameField = Nino.adminUi.textField( {
				id					: 'roles-form-name',
				label				: Nino.content.getText('/_admin/roles/label/name'),
				value				: role === null ? '' : role.label,
				required		: true,
				maxLength		: 60,
			} );
			const nameInput = nameField.querySelector('input');
			fieldset.appendChild( nameField );

			// A role accounts hold cannot go (the backend refuses too) - the reason stands behind the (?) at their number
			if( role !== null )
				fieldset.appendChild( Nino.admin.roles._readOnly( 'roles-form-users', Nino.content.getText('/_admin/roles/label/users'), String( role.users ), role.users > 0 ? Nino.content.getText('/_admin/roles/label/delete-hint') : undefined ) );

			form.appendChild( fieldset );

			const permissions = Nino.admin.roles._renderPermissions( role === null ? [] : role.perms );
			permissions.fieldsets.forEach( function( set ) { form.appendChild( set ) } );

			// A role accounts hold cannot go (the backend refuses too) - the button says so rather than disappearing
			const deleteBtn = role === null ? null : Nino.adminUi.button( Nino.content.getText('/_admin/roles/label/delete'), function() { Nino.admin.roles._delete() }, { kind : 'danger', disabled : role.users > 0 } );

			if( deleteBtn !== null && role.users > 0 )
				deleteBtn.title = Nino.content.getText('/_admin/roles/label/delete-hint');

			const msg = dc.createElement('p');
			msg.id = 'roles-form-msg';
			const bar = Nino.adminUi.actionBar( {
				danger	: deleteBtn,
				status	: { line : msg, fresh : role === null },
				primary	: Nino.adminUi.button( Nino.content.getText( role === null ? '/_admin/roles/label/create' : '/_admin/common/label/save' ), null, { type : 'submit' } ),
			} );

			Nino.admin.roles._line = bar.line;
			bar.line.bind( form );
			form.appendChild( bar.el );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.roles._save( idInput, nameInput, permissions ) } );

			wrap.appendChild( form );

			Nino.admin.roles._parts = { idInput : idInput, nameInput : nameInput, permissions : permissions };

			if( role === null )
				idInput.focus();

			// What the form holds now is what is saved
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'roles' );
		},

		/**
		 *	The form of a role the signed-in account may not change: its name, the
		 *	accounts that hold it, what it may do in words, and the reason - no
		 *	field to type in, no button. Drawn below the bar _renderForm() began
		 *
		 *	@param		{Element}	wrap				The form's container
		 *	@param		{Object}		role				The role
		 *
		 *	@return		void
		 */
		_renderBeyond : function( wrap, role ) {

			const roles = Nino.admin.roles;

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = role.label;
			fieldset.appendChild( legend );

			fieldset.appendChild( roles._readOnly( 'roles-form-id', Nino.content.getText('/_admin/roles/label/id'), role.id ) );
			fieldset.appendChild( roles._readOnly( 'roles-form-users', Nino.content.getText('/_admin/roles/label/users'), String( role.users ) ) );
			fieldset.appendChild( Nino.adminUi.notice( Nino.content.getText( role.heldBeyond === true ? '/_admin/roles/label/held-beyond' : '/_admin/roles/label/beyond' ) ) );
			wrap.appendChild( fieldset );

			const summary = dc.createElement('fieldset');
			const title = dc.createElement('legend');
			title.textContent = Nino.content.getText('/_admin/roles/summary/title');
			summary.appendChild( title );

			const list = dc.createElement('ul');
			roles.summarize( role.perms, roles._permOptions, roles._scopes ).forEach( function( text ) {
				const item = dc.createElement('li');
				item.textContent = text;
				list.appendChild( item );
			} );
			summary.appendChild( list );
			wrap.appendChild( summary );

			Nino.admin.roles._line = null;

			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.snapshot( 'roles' );
		},

		/**
		 *	The permissions of the form, as the fieldsets that hold them: a "full access" switch that stands in for
		 *	all of them at once ('/*') and, under it, the permissions themselves as a list of ticks for each group
		 *	of the rail. Then the finer permissions - added with three dependent lists, and ticked off again in a
		 *	list of their own - and a summary of what the role may do.
		 *
		 *	Every permission the backend offers is here. Of the ones no panel is
		 *	offering right now (see \Nino\Modules\Users\Admin::permOptions(), the
		 *	"other" group) the role's own are listed under the "other" group name,
		 *	as they are written, so a permission from a switched-off module is
		 *	visible, keepable and removable rather than silently dropped on the
		 *	next save - what other roles hold is not. A scoped permission the
		 *	panels list (see _treeLabels()) is named by its place in the tree and
		 *	stays in the list of the added ones, before and after a save.
		 *
		 *	@param		{Array}		perms					The role's current permissions
		 *
		 *	@return		{Object}								{ fieldsets, fullCheck, perms() }
		 */
		_renderPermissions : function( perms ) {

			const roles = Nino.admin.roles;
			const scopes = roles._scopes;
			const tree = roles._treeLabels( scopes );
			const text = function( key ) { return Nino.content.getText( key ) };

			// The role's own permissions, minus full access - that one is the
			// switch, never a tick in a list
			let chosen = perms.filter( function( perm ) { return perm !== '/*' } );

			const fieldset = dc.createElement('fieldset');
			fieldset.id = 'roles-form-permissions';
			const legend = dc.createElement('legend');
			legend.textContent = text('/_admin/roles/label/permissions');
			fieldset.appendChild( legend );
			Nino.adminUi.sectionHelp( legend, fieldset, text('/_admin/roles/hint/permissions') );

			const fullField = Nino.adminUi.switchField( { key : 'full', checked : perms.indexOf('/*') !== -1, label : text('/_admin/roles/label/full'), help : text('/_admin/roles/hint/full') } );
			const fullCheck = fullField.querySelector('input');
			fullCheck.id = 'roles-permissions-full';
			fieldset.appendChild( fullField );

			// A tick for every permission a panel offers, in the groups of the rail and then the ones nobody offers; the
			// group name goes in front of the list, so that it reads in the order the navigation does. The last group is
			// no list of what other roles hold: it has the permissions this role carries and no panel offers, outside the
			// tree (a scoped one the tree names is in the list of the added ones, where it was added)
			const offered = {};
			const groups = [];

			roles._groups.forEach( function( group ) {

				const members = roles._permOptions.filter( function( option ) {
					return option.group === group && ( group !== 'other' || ( chosen.indexOf( option.perm ) !== -1 && tree[option.perm] === undefined ) );
				} );

				if( members.length === 0 )
					return;

				members.forEach( function( option ) { offered[option.perm] = true } );

				const list = Nino.adminUi.checklist( {
					key			: 'perms',
					label		: roles._groupName( group ),
					options	: members.map( function( option ) {
						// A scoped permission by its place in the tree; otherwise a fill key or literal text a module's
						// panel chose, and the bare permission string for one no panel offers (see Users::permOptions())
						return { value : option.perm, label : tree[option.perm] !== undefined ? tree[option.perm] : Nino.adminUi.text( option.label ) };
					} ),
					value		: chosen,
					onChange	: function( ticked ) {
						change( chosen.filter( function( perm ) { return members.some( function( option ) { return option.perm === perm } ) === false } ).concat( ticked ) );
					},
				} );

				list.el.dataset.group = group;
				groups.push( list );
				fieldset.appendChild( list.el );
			} );

			// The finer permissions (see \Nino\Admin\Admin::scoped()) are a path
			// per action and per field. The panels list them (scopes()), so they
			// are picked - area, then action, then field - and never typed: the
			// only strings the Add button can produce are ones out of the tree.
			// Once added they are ticked in a list of their own: a tick taken
			// away is a permission taken away. A project panel that lists none
			// cannot give a role a new one from here; the ones a role holds stay visible
			const entries = roles._areaList( scopes );
			let extras = chosen.filter( function( perm ) { return offered[perm] !== true } );
			let held = null;
			let scoped = null;

			// What the role may do, in words
			const summary = dc.createElement('fieldset');
			summary.id = 'roles-permissions-summary';
			const summaryTitle = dc.createElement('legend');
			summaryTitle.textContent = text('/_admin/roles/summary/title');
			summary.appendChild( summaryTitle );
			const summaryList = dc.createElement('ul');
			summaryList.setAttribute( 'aria-live', 'polite' );
			summary.appendChild( summaryList );

			function refresh() {

				summaryList.innerHTML = '';

				roles.summarize( fullCheck.checked ? [ '/*' ] : chosen, roles._permOptions, scopes ).forEach( function( line ) {
					const item = dc.createElement('li');
					item.textContent = line;
					summaryList.appendChild( item );
				} );
			}

			// The ticks as chosen says they are: after a change, and after a question that was answered No
			function sync() {

				groups.forEach( function( list ) { list.set( chosen ) } );

				if( held !== null )
					held.set( chosen );

				refresh();
			}

			function drawHeld() {

				if( extras.length === 0 )
					return;

				const list = Nino.adminUi.checklist( {
					key			: 'perms',
					label		: text('/_admin/roles/scope/held'),
					options	: extras.map( function( perm ) { return { value : perm, label : tree[perm] !== undefined ? tree[perm] : roles._groupName('other')+ ' · '+ perm } } ),
					value		: chosen,
					onChange	: function( ticked ) { change( chosen.filter( function( perm ) { return extras.indexOf( perm ) === -1 } ).concat( ticked ) ) },
				} );

				if( held === null )
					scoped.appendChild( list.el );
				else
					held.el.replaceWith( list.el );

				held = list;
			}

			// Every change of what is ticked goes through here. The first single permission of a panel changes what the
			// role may do there from everything to what it names, taking the last one away puts the panel back to
			// everything: both are asked about first, and the ticks go back where they were on No
			function change( next, added ) {

				const before = roles.scopeState( chosen, scopes );
				const after = roles.scopeState( next, scopes );
				const entering = scopes.filter( function( scope ) { return before[scope.scope] !== true && after[scope.scope] === true } );
				const leaving = scopes.filter( function( scope ) { return before[scope.scope] === true && after[scope.scope] !== true } );

				const apply = function() {

					chosen = next;

					if( added !== undefined && offered[added] !== true && extras.indexOf( added ) === -1 ) {
						extras = extras.concat( [ added ] );
						drawHeld();
					}

					sync();
				};

				if( entering.length === 0 && leaving.length === 0 ) {
					apply();
					return;
				}

				const answered = function( ok ) {
					if( ok === true )
						apply();
					else
						sync();
				};

				// One question each way; a question that is not asked because another one is open leaves the ticks as they were
				if( entering.length > 0 ) {
					if( Nino.adminUi.confirm( 'big', {
						title			: Nino.content.getText('/_admin/roles/scope/add-title'),
						message		: Nino.adminUi.format( Nino.content.getText('/_admin/roles/scope/warning'), Nino.adminUi.text( entering[0].label ) ),
						visitors	: false,
						yes				: Nino.content.getText('/_admin/roles/scope/add-title'),
						no				: Nino.content.getText('/_admin/common/label/cancel'),
						onChoose	: answered,
					} ) === false )
						sync();

					return;
				}

				if( Nino.adminUi.confirm( 'big', {
					title			: Nino.content.getText('/_admin/roles/scope/leave-title'),
					message		: Nino.adminUi.format( Nino.content.getText('/_admin/roles/scope/warning-leave'), Nino.adminUi.text( leaving[0].label ) ),
					visitors	: false,
					yes				: Nino.content.getText('/_admin/roles/scope/leave-title'),
					no				: Nino.content.getText('/_admin/common/label/cancel'),
					onChoose	: answered,
				} ) === false )
					sync();
			}

			if( entries.length > 0 || extras.length > 0 ) {

				scoped = dc.createElement('fieldset');
				scoped.id = 'roles-permissions-scoped';
				const scopedLegend = dc.createElement('legend');
				scopedLegend.textContent = text('/_admin/roles/scope/title');
				scoped.appendChild( scopedLegend );

				if( entries.length > 0 ) {

					Nino.adminUi.sectionHelp( scopedLegend, scoped, text('/_admin/roles/scope/hint') );

					const picks = dc.createElement('div');
					picks.className = 'admin-perm-scope';
					scoped.appendChild( picks );

					let pick = { area : 0, action : 0, field : 0 };

					// focus : the list the person just used, which is drawn again
					// with the ones that depend on it - and keeps the keyboard where it was
					const drawPicks = function( focus ) {

						picks.innerHTML = '';

						const entry = entries[pick.area];
						const actions = roles._actionList( entry );
						const action = actions[pick.action];
						const fields = roles._fieldList( action );

						const fieldOf = function( key, label, list, index, onChange ) {

							const field = Nino.adminUi.selectField( {
								key 			: key,
								label 		: label,
								options 	: list.map( function( item, at ) { return { value : at, label : item.label } } ),
								value 		: index,
								onChange 	: function( value ) { onChange( parseInt( value, 10 ) ) },
							} );

							// Choosing where to add something is not an edit of the role
							field.querySelector('select').dataset.dirty = 'ignore';

							return field;
						};

						picks.appendChild( fieldOf( 'scope-area', text('/_admin/roles/scope/area'), entries.map( function( item ) {
							return { label : Nino.adminUi.text( item.scope.label )+ ' · '+ item.area.label };
						} ), pick.area, function( value ) { pick = { area : value, action : 0, field : 0 }; drawPicks('scope-area') } ) );

						picks.appendChild( fieldOf( 'scope-action', text('/_admin/roles/scope/action'), actions, pick.action, function( value ) {
							pick = { area : pick.area, action : value, field : 0 };
							drawPicks('scope-action');
						} ) );

						if( fields.length > 0 )
							picks.appendChild( fieldOf( 'scope-field', text('/_admin/roles/scope/field'), fields, pick.field, function( value ) { pick.field = value } ) );

						picks.appendChild( Nino.adminUi.button( text('/_admin/roles/scope/add'), function() { addScoped( roles._permFor( entries, pick ) ) }, { kind : 'secondary' } ) );

						const used = typeof focus === 'string' ? picks.querySelector( '[data-key="'+ focus+ '"]' ) : null;
						if( used !== null )
							used.focus();
					};

					drawPicks();
				}

				drawHeld();
			}

			function addScoped( perm ) {

				// Only what the tree lists, and only once
				if( perm === '' || chosen.indexOf( perm ) !== -1 )
					return;

				change( chosen.concat( [ perm ] ), perm );
			}

			// Full access is every permission there is, so ticking single ones
			// beside it would say something the save does not do. The lists
			// go away for as long as the switch is on rather than greying
			// out, and what the role held is still there when it goes off again
			function applyFullAccess() {

				groups.forEach( function( list ) { list.el.classList.toggle( 'admin-hidden', fullCheck.checked ) } );

				if( scoped !== null )
					scoped.classList.toggle( 'admin-hidden', fullCheck.checked );

				refresh();
			}

			fullCheck.addEventListener( 'change', applyFullAccess );
			applyFullAccess();

			return {
				fieldsets	: [ fieldset, scoped, summary ].filter( function( set ) { return set !== null } ),
				fullCheck	: fullCheck,
				perms			: function() { return chosen.slice() },
			};
		},

		/**
		 *	Whether a permission string is held: the way \Nino\Auth::checkPermission()
		 *	reads a list - the string itself, or '<ancestor>/*' for any of its
		 *	ancestors, '/*' being the last. No DOM, so the rule can be checked
		 *	against the PHP one
		 *
		 *	@param		{Array}		perms					The permissions held
		 *	@param		{string}	perm					The one asked about
		 *
		 *	@return		{boolean}
		 */
		covers : function( perms, perm ) {

			if( perms.indexOf( perm ) !== -1 )
				return true;

			while( perm !== '' ) {

				const at = perm.lastIndexOf('/');
				if( at === -1 )
					return false;

				perm = perm.slice( 0, at );

				if( perms.indexOf( perm+ '/*' ) !== -1 )
					return true;
			}

			return false;
		},

		/**
		 *	Which panels the permissions put in detail mode - \Nino\Admin\Admin::isScoped()'s
		 *	rule: a permission below the panel's scope that is neither the
		 *	blanket ('*') nor just one segment (the door) names a single action,
		 *	and from then on the panel allows what the role names
		 *
		 *	@param		{Array}		perms					The permissions held
		 *	@param		{Array}		scopes				The tree the panels offer
		 *
		 *	@return		{Object}								{ <scope prefix> : boolean }
		 */
		scopeState : function( perms, scopes ) {

			const state = {};

			scopes.forEach( function( scope ) {
				state[scope.scope] = perms.some( function( held ) {

					if( held.indexOf( scope.scope ) !== 0 )
						return false;

					const rest = held.slice( scope.scope.length );

					return rest !== '*' && rest.indexOf('/') !== -1;
				} );
			} );

			return state;
		},

		/**
		 *	Every scoped permission the tree lists, by the place it has in it:
		 *	panel, area, action and field, one after the other. What a
		 *	permission the role holds is called in the picker
		 *
		 *	@param		{Array}		scopes				The tree the panels offer
		 *
		 *	@return		{Object}								{ permission : label }
		 */
		_treeLabels : function( scopes ) {

			const labels = {};

			scopes.forEach( function( scope ) {

				const panel = Nino.adminUi.text( scope.label );

				scope.areas.forEach( function( area ) {

					const base = panel+ ' · '+ area.label;

					if( typeof area.perm === 'string' && labels[area.perm] === undefined )
						labels[area.perm] = base+ ' · '+ Nino.content.getText('/_admin/roles/scope/everything');

					area.actions.forEach( function( action ) {

						const name = base+ ' · '+ Nino.adminUi.text( action.label );
						const fields = action.fields || [];

						if( labels[action.perm] === undefined )
							labels[action.perm] = fields.length > 0 ? name+ ' · '+ Nino.content.getText('/_admin/roles/scope/all-fields') : name;

						fields.forEach( function( field ) {
							if( labels[field.perm] === undefined )
								labels[field.perm] = name+ ' · '+ field.label;
						} );
					} );
				} );
			} );

			return labels;
		},

		/**
		 *	The areas of every scope as one flat list, in the order the panels
		 *	gave them - what the first of the three lists offers
		 *
		 *	@param		{Array}		scopes
		 *
		 *	@return		{Array}									[ { scope, area } ]
		 */
		_areaList : function( scopes ) {

			const list = [];

			scopes.forEach( function( scope ) {
				scope.areas.forEach( function( area ) { list.push( { scope : scope, area : area } ) } );
			} );

			return list;
		},

		/**
		 *	What the second list offers for an area: 'everything in this area'
		 *	where the area has a permission of its own, then its actions. The
		 *	first one has no action (action : null) - it stands for the area
		 *
		 *	@param		{Object}	entry					An entry of _areaList()
		 *
		 *	@return		{Array}									[ { label, action } ]
		 */
		_actionList : function( entry ) {

			const list = [];

			if( typeof entry.area.perm === 'string' )
				list.push( { label : Nino.content.getText('/_admin/roles/scope/everything'), action : null } );

			entry.area.actions.forEach( function( action ) {
				list.push( { label : Nino.adminUi.text( action.label ), action : action } );
			} );

			return list;
		},

		/**
		 *	What the third list offers for an action: 'all fields' - the
		 *	action's own permission - and each field. Nothing for an action
		 *	without fields, or for 'everything in this area'
		 *
		 *	@param		{Object}	item					An entry of _actionList()
		 *
		 *	@return		{Array}									[ { label, field } ], empty when there is no third list
		 */
		_fieldList : function( item ) {

			if( item === undefined || item.action === null || Array.isArray( item.action.fields ) === false || item.action.fields.length === 0 )
				return [];

			return [ { label : Nino.content.getText('/_admin/roles/scope/all-fields'), field : null } ].concat( item.action.fields.map( function( field ) {
				return { label : field.label, field : field };
			} ) );
		},

		/**
		 *	The permission the three lists stand on - always one the tree
		 *	lists, never built from anything typed
		 *
		 *	@param		{Array}		entries				_areaList()
		 *	@param		{Object}	pick					{ area, action, field } - the index in each list
		 *
		 *	@return		{string}								'' when the lists stand on nothing
		 */
		_permFor : function( entries, pick ) {

			const entry = entries[pick.area];
			if( entry === undefined )
				return '';

			const item = Nino.admin.roles._actionList( entry )[pick.action];
			if( item === undefined )
				return '';

			if( item.action === null )
				return entry.area.perm;

			const field = Nino.admin.roles._fieldList( item )[pick.field];

			return field !== undefined && field.field !== null ? field.field.perm : item.action.perm;
		},

		/**
		 *	What a role may do, as lines to read after "This role may …". The
		 *	whole of it: full access; the areas its permissions open; per panel
		 *	in detail mode what it allows there; a panel it holds single
		 *	permissions for but does not open; and every permission nothing
		 *	explains, by its string. No DOM
		 *
		 *	@param		{Array}		perms					The permissions held ('/*' alone for full access)
		 *	@param		{Array}		permOptions		What the panels offer - { perm, label, offered }
		 *	@param		{Array}		scopes				The tree the panels offer
		 *
		 *	@return		{Array}									Lines of text, at least one
		 */
		summarize : function( perms, permOptions, scopes ) {

			const roles = Nino.admin.roles;
			const say = function( key, ...params ) { return Nino.adminUi.format( Nino.content.getText( key ), ...params ) };

			if( perms.indexOf('/*') !== -1 )
				return [ say('/_admin/roles/summary/full') ];

			const lines = [];
			const state = roles.scopeState( perms, scopes );
			const tree = roles._treeLabels( scopes );
			const offered = permOptions.filter( function( option ) { return option.offered === true } );

			const doors = offered.filter( function( option ) { return roles.covers( perms, option.perm ) } ).map( function( option ) { return Nino.adminUi.text( option.label ) } );

			if( doors.length > 0 )
				lines.push( say( '/_admin/roles/summary/doors', doors.join(', ') ) );

			scopes.forEach( function( scope ) {

				if( state[scope.scope] !== true )
					return;

				const panel = Nino.adminUi.text( scope.label );
				const allowed = [];

				scope.areas.forEach( function( area ) {

					if( typeof area.perm === 'string' && roles.covers( perms, area.perm ) === true ) {
						allowed.push( say( '/_admin/roles/summary/area', area.label ) );
						return;
					}

					area.actions.forEach( function( action ) {

						const name = Nino.adminUi.text( action.label );

						if( roles.covers( perms, action.perm ) === true ) {
							allowed.push( say( '/_admin/roles/summary/action', area.label, name ) );
							return;
						}

						const fields = ( action.fields || [] ).filter( function( field ) { return roles.covers( perms, field.perm ) } ).map( function( field ) { return field.label } );

						if( fields.length > 0 )
							allowed.push( say( '/_admin/roles/summary/fields', area.label, name, fields.join(', ') ) );
					} );
				} );

				if( allowed.length > 0 )
					lines.push( say( '/_admin/roles/summary/detail', panel, allowed.join('; ') ) );

				// Single permissions without the door: the panel stays shut
				if( roles.covers( perms, scope.door ) === false )
					lines.push( say( '/_admin/roles/summary/nodoor', panel, scope.door ) );
			} );

			// What nothing above explains: not a door, not in the tree, and not
			// a wildcard over either
			perms.forEach( function( perm ) {

				const wildcard = perm.slice( -2 ) === '/*';
				const known = offered.some( function( option ) { return option.perm === perm } )
					|| tree[perm] !== undefined
					|| ( wildcard === true && ( offered.some( function( option ) { return roles.covers( [ perm ], option.perm ) } )
						|| scopes.some( function( scope ) { return perm.indexOf( scope.scope ) === 0 } ) ) );

				if( known === false )
					lines.push( say( '/_admin/roles/summary/unknown', perm ) );
			} );

			return lines.length > 0 ? lines : [ say('/_admin/roles/summary/none') ];
		},

		/**
		 *	What a permission group is called: the navigation's own heading for
		 *	the four the rail has, and a name of its own for the fifth, which
		 *	is not a group of the rail at all but "held by somebody, offered by
		 *	nothing" (see \Nino\Modules\Users\Admin::permOptions())
		 *
		 *	@param		{string}	group
		 *
		 *	@return		{string}
		 */
		_groupName : function( group ) {

			return group === 'other'
				? Nino.content.getText('/_admin/roles/group/other')
				: Nino.content.getText('/_admin/nav/group/'+ group );
		},

		/**
		 *	Check the fields of the form before a request goes out: each wrong one says what is wrong under it, the line
		 *	of the bar says so once, and the first takes the focus. The forms are novalidate, so the browser's own
		 *	bubble in its own language never speaks
		 *
		 *	@param		{Element}		form
		 *	@param		{Element}		idInput
		 *	@param		{Element}		nameInput
		 *	@param		{Object}		line					The status line of the form's bar
		 *
		 *	@return		{boolean}									Whether every field is fine
		 */
		_check : function( form, idInput, nameInput, line ) {

			Nino.adminUi.clearFieldErrors( form );

			let first = null;

			[ [ idInput, '/_admin/roles/label/id', '/_admin/roles/expect/id' ], [ nameInput, '/_admin/roles/label/name', '' ] ].forEach( function( rule ) {

				const input = rule[0];
				const name = Nino.content.getText( rule[1] );
				const value = input.value.trim();

				// The id of a role that exists is no field to fill
				if( input.disabled === true )
					return;

				// A new role takes no id that a role holds: it would be written over
				const taken = rule[2] !== '' && Nino.admin.roles._current === null && Nino.admin.roles._roles.some( function( role ) { return role.id === value } );

				const sentence = value === '' ? Nino.adminUi.format( Nino.content.getText('/_admin/common/error/missing'), name )
					: ( rule[2] !== '' && /^[a-z][a-z0-9-]{0,39}$/.test( value ) === false ? Nino.adminUi.format( Nino.content.getText('/_admin/users/error/invalid'), name, Nino.content.getText( rule[2] ) )
						: ( taken === true ? Nino.adminUi.format( Nino.content.getText('/_admin/users/error/taken'), name ) : '' ) );

				if( sentence === '' )
					return;

				Nino.adminUi.fieldError( input.closest('label'), input, 'roles-error-'+ input.id, sentence );

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
		 *	Save the form, then reload the list so the counts and the new
		 *	role are what the server holds
		 *
		 *	@param		{Element}	idInput
		 *	@param		{Element}	nameInput
		 *	@param		{Object}	permissions		See _renderPermissions()
		 *	@param		{Function}	[done]			Called once with true when the role was written, false otherwise
		 *
		 *	@return		void
		 */
		_save : function( idInput, nameInput, permissions, done ) {

			if( Nino.admin.roles._check( dc.getElementById('roles-edit-form'), idInput, nameInput, Nino.admin.roles._line ) === false ) {
				if( typeof done === 'function' )
					done( false );
				return;
			}

			Nino.admin.roles._send( idInput.value.trim(), nameInput.value.trim(), permissions.fullCheck.checked ? [ '/*' ] : permissions.perms(), done );
		},

		/**
		 *	Send the role. A refusal is said in the line of the bar and, where it names the id or the name, under that
		 *	field
		 *
		 *	@param		{string}		id
		 *	@param		{string}		label
		 *	@param		{Array}			perms
		 *	@param		{Function}	[done]				Called once with true when the role was written, false otherwise
		 *
		 *	@return		void
		 */
		_send : function( id, label, perms, done ) {

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const line = Nino.admin.roles._line;

			line.saving();

			Nino.admin.roles._apiCall( 'save', Object.assign( { id : id, label : label, perms : perms }, Nino.admin.roles._current === null ? { create : true } : {} ), function( status, response ) {

				if( status !== 200 ) {
					Nino.admin.roles._refused( line, status, response );
					report( false );
					return;
				}

				Nino.admin.roles._apiCall( 'list', {}, function( listStatus, listResponse ) {
					if( listStatus !== 200 || listResponse === null ) {
						// The role is written: only the list failed, and a Save that is
						// reported as failed would be tried again
						Nino.admin.roles._showError( dc.getElementById('roles-list'), listStatus, listResponse );
						report( true );
						return;
					}
					Nino.admin.roles._roles = listResponse.roles;
					Nino.admin.roles._permOptions = listResponse.permOptions;
					Nino.admin.roles._groups = listResponse.groups;
					Nino.admin.roles._scopes = listResponse.scopes || [];
					Nino.admin.roles._renderList();
					Nino.admin.roles._openRole( response.id, true );
					Nino.admin.roles._line.saved();
					report( true );
				} );
			} );
		},

		/**
		 *	Say what the server refused: in the line of the bar and, for the refusals that are about a field,
		 *	under that field
		 *
		 *	@param		{Object}		line					The status line of the form's bar
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_refused : function( line, status, response ) {

			const form = dc.getElementById('roles-edit-form');
			const code = response !== null && typeof response === 'object' ? response.code : '';
			const field = code === 'roles_invalid_id' || code === 'roles_id_taken' ? 'roles-form-id' : ( code === 'roles_name_required' ? 'roles-form-name' : '' );
			const input = field === '' ? null : dc.getElementById( field );

			Nino.adminUi.clearFieldErrors( form );
			line.error( status, response, '/_admin/roles/error/save' );

			if( input !== null )
				Nino.adminUi.fieldError( input.closest('label'), input, 'roles-error-'+ field, Nino.adminUi.api.errorText( status, response, '/_admin/roles/error/save' ) );
		},

		/**
		 *	Delete the current role, after confirmation, and return to the list
		 *
		 *	@return		void
		 */
		_delete : function() {

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/roles/confirm/delete-title'),
				message		: Nino.content.getText('/_admin/roles/confirm/delete'),
				yes				: Nino.content.getText('/_admin/roles/confirm/delete-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					const role = Nino.admin.roles._current;

					Nino.admin.roles._apiCall( 'delete', { id : role.id }, function( status, response ) {

						if( status !== 200 ) {
							Nino.admin.roles._line.error( status, response, '/_admin/roles/error/save' );
							return;
						}

						Nino.admin.roles._roles = Nino.admin.roles._roles.filter( function( r ) { return r.id !== role.id } );
						Nino.admin.roles._current = null;
						Nino.admin.roles._renderList();
						Nino.admin.roles._showList();
						Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/deleted') );
					} );
				},
			} );
		},
	};

	// The shell asks before anything throws the open role's input away (see
	// Nino.admin.dirty). A shell without the registry is simply not asking
	if( typeof Nino.admin.dirty === 'object' )
		Nino.admin.dirty.watchForm( 'roles', function() { return dc.getElementById('roles-form') }, function( done ) {
			const parts = Nino.admin.roles._parts;
			Nino.admin.roles._save( parts.idInput, parts.nameInput, parts.permissions, done );
		} );

})(window, document, document.documentElement, document.body);
