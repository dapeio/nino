

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	roles.js								"User roles" tab of the Users panel: the named sets of
 *													permissions an account holds one of. A list of roles,
 *													and a form per role with its name, a full-access switch
 *													and the permissions themselves in the shared
 *													multi-reference picker - every one a panel or tab
 *													offers right now, plus every one this installation
 *													holds without a panel behind it. The finer ones, per
 *													action and field, are added with three dependent
 *													lists out of the tree the panels offer (scopes()),
 *													and a summary says in plain words what the role may
 *													do. Manager-only, same gate the backend enforces
 *													independently (see Roles/Roles.php beside it).
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
		// The order the picker lists the groups in - see Roles::apiList()
		_groups				: [],
		// The scoped permissions the panels offer - see Users\Admin::scopeOptions()
		_scopes				: [],
		// The role on the form, null while a new one is being made
		_current			: null,
		// The controls the form's save reads, as _renderForm() made them
		_parts				: null,
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
		 *	Drill-down navigation: list -> form
		 *
		 *	@return		void
		 */
		_showList : function() {
			dc.getElementById('roles-list').classList.remove('admin-hidden');
			dc.getElementById('roles-form').classList.add('admin-hidden');
			Nino.admin.router.set( 'roles', [] );
		},

		_showForm : function() {
			dc.getElementById('roles-list').classList.add('admin-hidden');
			dc.getElementById('roles-form').classList.remove('admin-hidden');
			Nino.admin.router.set( 'roles', [ Nino.admin.roles._current === null ? 'new' : Nino.admin.roles._current.id ] );
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

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			Nino.admin.roles._roles.forEach( function( role ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';

				const copy = dc.createElement('span');
				copy.className = 'nino-admin-list-copy';
				const title = dc.createElement('strong');
				title.textContent = role.label;
				const descr = dc.createElement('small');
				descr.textContent = role.id+ ' · '+ Nino.content.getText('/_admin/roles/label/users')+ ': '+ role.users+ ' · '
					+ ( role.perms.indexOf('/*') !== -1 ? Nino.content.getText('/_admin/users/label/fullaccess') : role.perms.length+ ' × '+ Nino.content.getText('/_admin/roles/label/permissions') );
				copy.appendChild( title );
				copy.appendChild( descr );
				link.appendChild( copy );

				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.router.go( 'roles', [ role.id ] ); Nino.admin.roles._openRole( role.id ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			if( Nino.admin.roles._roles.length > 0 )
				wrap.appendChild( ul );

			const add = dc.createElement('button');
			add.type = 'button';
			add.className = 'nino-admin-btn-primary';
			add.textContent = Nino.content.getText('/_admin/roles/label/new');
			add.addEventListener( 'click', function() { Nino.admin.router.go( 'roles', [ 'new' ] ); Nino.admin.roles._openRole( null ) } );
			wrap.appendChild( Nino.adminUi.listActions( [ add ] ) );
		},

		/**
		 *	Open one role's form, or an empty one
		 *
		 *	@param		{string|null}	id
		 *
		 *	@return		void
		 */
		_openRole : function( id ) {

			Nino.admin.roles._current = id === null ? null : ( Nino.admin.roles._roles.find( function( r ) { return r.id === id } ) ?? null );
			Nino.admin.roles._renderForm();
			Nino.admin.roles._showForm();
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

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'nino-admin-back-link';
			backLink.textContent = Nino.content.getText('/_admin/roles/label/back');
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.router.go( 'roles', [] ); Nino.admin.roles._showList() } );
			wrap.appendChild( Nino.admin.formToolbar( backLink ) );

			const form = dc.createElement('form');
			form.id = 'roles-edit-form';

			const fieldset = dc.createElement('fieldset');
			const legend = dc.createElement('legend');
			legend.textContent = role === null ? Nino.content.getText('/_admin/roles/label/new') : role.label;
			fieldset.appendChild( legend );

			const idLabel = dc.createElement('label');
			idLabel.className = 'nino-admin-field';
			const idSpan = dc.createElement('span');
			idSpan.textContent = Nino.content.getText('/_admin/roles/label/id');
			idLabel.appendChild( idSpan );
			const idInput = dc.createElement('input');
			idInput.type = 'text';
			idInput.id = 'roles-form-id';
			idInput.required = true;
			idInput.autocomplete = 'off';
			idInput.spellcheck = false;
			idInput.pattern = '[a-z][a-z0-9-]{0,39}';
			idInput.value = role === null ? '' : role.id;
			idInput.disabled = role !== null;
			idLabel.appendChild( idInput );
			const idHint = dc.createElement('small');
			idHint.className = 'nino-admin-hint';
			idHint.textContent = Nino.content.getText('/_admin/roles/label/id-hint');
			idLabel.appendChild( idHint );
			fieldset.appendChild( idLabel );

			const nameLabel = dc.createElement('label');
			nameLabel.className = 'nino-admin-field';
			const nameSpan = dc.createElement('span');
			nameSpan.textContent = Nino.content.getText('/_admin/roles/label/name');
			nameLabel.appendChild( nameSpan );
			const nameInput = dc.createElement('input');
			nameInput.type = 'text';
			nameInput.id = 'roles-form-name';
			nameInput.required = true;
			nameInput.maxLength = 60;
			nameInput.value = role === null ? '' : role.label;
			nameLabel.appendChild( nameInput );
			fieldset.appendChild( nameLabel );

			if( role !== null ) {
				const users = dc.createElement('p');
				users.className = 'nino-admin-hint';
				users.textContent = Nino.content.getText('/_admin/roles/label/users')+ ': '+ role.users;
				fieldset.appendChild( users );
			}

			form.appendChild( fieldset );

			const permissions = Nino.admin.roles._renderPermissions( role === null ? [] : role.perms );
			form.appendChild( permissions.fieldset );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = Nino.content.getText( role === null ? '/_admin/roles/label/create' : '/_admin/roles/label/save' );
			actions.appendChild( saveBtn );

			// A role accounts hold cannot go (the backend refuses too) - the
			// button says so rather than disappearing
			if( role !== null ) {
				const delBtn = dc.createElement('button');
				delBtn.type = 'button';
				delBtn.className = 'nino-admin-btn-danger';
				delBtn.textContent = Nino.content.getText('/_admin/roles/label/delete');
				delBtn.disabled = role.users > 0;
				if( role.users > 0 )
					delBtn.title = Nino.content.getText('/_admin/roles/label/delete-hint');
				delBtn.addEventListener( 'click', function() { Nino.admin.roles._delete() } );
				actions.appendChild( delBtn );
			}

			const msg = dc.createElement('p');
			msg.id = 'roles-form-msg';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			form.appendChild( actions );
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
		 *	The permissions fieldset: a "full access" switch that stands in for
		 *	all of them at once ('/*'), and below it the permissions themselves
		 *	as the shared multi-reference picker (Nino.adminUi.elementList) -
		 *	the chosen ones together at the top with a ✕ each, everything else
		 *	behind one search field. Then the finer permissions, added with
		 *	three dependent lists, and a summary of what the role may do.
		 *
		 *	A checkbox per permission was the first shape and does not survive
		 *	the number: the list is one entry per panel and tab of every active
		 *	module, it grows with every module a project adds, and a role
		 *	typically holds a handful of them scattered through its group
		 *	boxes. The picker shows what the role HAS as a short list and makes
		 *	finding the next one a search rather than a scan. Unordered
		 *	(ordered: false): a permission set has no first and no last.
		 *
		 *	Every permission the backend offers is here, the ones no panel is
		 *	offering right now included - see \Nino\Modules\Users\Admin::permOptions().
		 *	Those carry the "other" group name instead of a panel label, so a
		 *	permission from a switched-off module is visible, keepable and
		 *	removable rather than silently dropped on the next save. A scoped
		 *	permission the panels list (see _treeLabels()) is named by its place
		 *	in the tree instead.
		 *
		 *	@param		{Array}		perms					The role's current permissions
		 *
		 *	@return		{Object}								{ fieldset, fullCheck, perms() }
		 */
		_renderPermissions : function( perms ) {

			const roles = Nino.admin.roles;
			const scopes = roles._scopes;
			const tree = roles._treeLabels( scopes );

			const hasFullAccess = perms.indexOf('/*') !== -1;

			const fieldset = dc.createElement('fieldset');
			fieldset.id = 'roles-form-permissions';
			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/roles/label/permissions');
			fieldset.appendChild( legend );

			const fullLabel = dc.createElement('label');
			fullLabel.className = 'nino-admin-checkbox-field';
			const fullCheck = dc.createElement('input');
			fullCheck.type = 'checkbox';
			fullCheck.id = 'roles-permissions-full';
			fullCheck.checked = hasFullAccess;
			fullLabel.appendChild( fullCheck );
			fullLabel.appendChild( dc.createTextNode( ' '+ Nino.content.getText('/_admin/roles/label/full') ) );
			fieldset.appendChild( fullLabel );

			// In the order roles/list names the groups - the rail's own, then
			// everything no panel offers. The group name goes in front of each
			// entry, so the search finds a whole group by typing its name and
			// the list reads in the same order the navigation does
			const options = [];
			roles._groups.forEach( function( group ) {
				roles._permOptions.filter( function( option ) { return option.group === group } ).forEach( function( option ) {
					options.push( {
						value : option.perm,
						// A scoped permission by its place in the tree; otherwise a
						// fill key or literal text a module's panel chose, and the bare
						// permission string for one no panel offers (see
						// Users::permOptions())
						label : tree[option.perm] !== undefined ? tree[option.perm] : roles._groupName( group )+ ' · '+ Nino.adminUi.text( option.label ),
					} );
				} );
			} );

			// The role's own permissions, minus full access - that one is the
			// switch above, never a row in here
			let chosen = perms.filter( function( perm ) { return perm !== '/*' } );

			// What the picker offers: the options, and whatever was added from
			// the tree since. The picker is rebuilt from its options each time
			// one arrives, so they have to be rebuilt with it
			function permOptions() {

				const list = options.slice();

				chosen.forEach( function( perm ) {
					if( list.some( function( option ) { return option.value === perm } ) === false )
						list.push( { value : perm, label : tree[perm] !== undefined ? tree[perm] : roles._groupName('other')+ ' · '+ perm } );
				} );

				return list;
			}

			// The ✕ of the picker. Taking the last single permission of a panel
			// away puts the panel back to everything - the transition addScoped()
			// asks about, the other way round. On No the picker is drawn again
			// from what the role holds, as the ✕ has already taken the row out
			function removed( value ) {

				const before = roles.scopeState( chosen, scopes );
				const after = roles.scopeState( value, scopes );
				const leaving = scopes.filter( function( scope ) { return before[scope.scope] === true && after[scope.scope] !== true } );

				if( leaving.length > 0 && wn.confirm( Nino.adminUi.format( Nino.content.getText('/_admin/roles/scope/warning-leave'), Nino.adminUi.text( leaving[0].label ) ) ) === false ) {
					rebuildPicker();
					return;
				}

				chosen = value;
				refresh();
			}

			function buildPicker() {
				return Nino.adminUi.elementList( {
					key 			: 'perms',
					label 		: Nino.content.getText('/_admin/roles/label/permissions'),
					value 		: chosen,
					limit 		: 0,
					ordered 	: false,
					options 	: permOptions(),
					text 			: {
						search 		: Nino.content.getText('/_admin/roles/perms/search'),
						empty 		: Nino.content.getText('/_admin/roles/perms/empty'),
						noMatches	: Nino.content.getText('/_admin/roles/perms/nomatches'),
						more 			: Nino.content.getText('/_admin/roles/perms/more'),
						remove 		: Nino.content.getText('/_admin/common/label/remove'),
						add 			: Nino.content.getText('/_admin/common/label/add'),
					},
					onChange 	: removed,
				} );
			}

			let picker = buildPicker();
			fieldset.appendChild( picker );

			function rebuildPicker() {
				const rebuilt = buildPicker();
				picker.replaceWith( rebuilt );
				picker = rebuilt;
				applyFullAccess();
			}

			// The finer permissions (see \Nino\Admin\Admin::scoped()) are a path
			// per action and per field. The panels list them (scopes()), so they
			// are picked - area, then action, then field - and never typed: the
			// only strings the Add button can produce are ones out of the tree.
			// Once added they sit in the same list as everything else, removable
			// with the same ✕. A project panel that lists none cannot give a
			// role a new one from here; the ones a role holds stay visible
			const scopeBox = dc.createElement('div');
			scopeBox.id = 'roles-permissions-scoped';

			const scopeHint = dc.createElement('p');
			scopeHint.className = 'nino-admin-hint';
			scopeHint.textContent = Nino.content.getText('/_admin/roles/scope/hint');
			scopeBox.appendChild( scopeHint );

			const picks = dc.createElement('div');
			picks.className = 'admin-perm-scope';
			scopeBox.appendChild( picks );

			const entries = roles._areaList( scopes );
			let pick = { area : 0, action : 0, field : 0 };

			function selectOf( field ) {
				return field.querySelector('select');
			}

			// focus : the list the person just used, which is drawn again with the
			// ones that depend on it - and keeps the keyboard where it was
			function drawPicks( focus ) {

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
					selectOf( field ).dataset.dirty = 'ignore';

					return field;
				};

				picks.appendChild( fieldOf( 'scope-area', Nino.content.getText('/_admin/roles/scope/area'), entries.map( function( item ) {
					return { label : Nino.adminUi.text( item.scope.label )+ ' · '+ item.area.label };
				} ), pick.area, function( value ) { pick = { area : value, action : 0, field : 0 }; drawPicks('scope-area') } ) );

				picks.appendChild( fieldOf( 'scope-action', Nino.content.getText('/_admin/roles/scope/action'), actions, pick.action, function( value ) {
					pick = { area : pick.area, action : value, field : 0 };
					drawPicks('scope-action');
				} ) );

				if( fields.length > 0 )
					picks.appendChild( fieldOf( 'scope-field', Nino.content.getText('/_admin/roles/scope/field'), fields, pick.field, function( value ) { pick.field = value } ) );

				const addBtn = dc.createElement('button');
				addBtn.type = 'button';
				addBtn.className = 'nino-admin-btn-secondary';
				addBtn.textContent = Nino.content.getText('/_admin/roles/scope/add');
				addBtn.addEventListener( 'click', function() { addScoped( roles._permFor( entries, pick ) ) } );
				picks.appendChild( addBtn );

				const used = typeof focus === 'string' ? picks.querySelector( '[data-key="'+ focus+ '"]' ) : null;
				if( used !== null )
					used.focus();
			}

			if( entries.length > 0 ) {
				drawPicks();
				fieldset.appendChild( scopeBox );
			}

			// A panel in detail mode allows what the role names and nothing
			// else - which is the one thing about these permissions that
			// surprises: say which panels the role has that for already
			const detailHint = dc.createElement('p');
			detailHint.className = 'nino-admin-hint';
			detailHint.setAttribute( 'aria-live', 'polite' );
			fieldset.appendChild( detailHint );

			const summary = dc.createElement('div');
			summary.id = 'roles-permissions-summary';
			const summaryTitle = dc.createElement('p');
			summaryTitle.className = 'nino-admin-hint';
			summary.appendChild( summaryTitle );
			const summaryList = dc.createElement('ul');
			summaryList.setAttribute( 'aria-live', 'polite' );
			summary.appendChild( summaryList );
			fieldset.appendChild( summary );

			function addScoped( perm ) {

				// Only what the tree lists, and only once
				if( perm === '' || chosen.indexOf( perm ) !== -1 )
					return;

				// Adding the first single permission of a panel changes what the
				// role may do there from everything to what it names
				const before = roles.scopeState( chosen, scopes );
				const after = roles.scopeState( chosen.concat( [ perm ] ), scopes );
				const entering = scopes.filter( function( scope ) { return before[scope.scope] !== true && after[scope.scope] === true } );

				if( entering.length > 0 && wn.confirm( Nino.adminUi.format( Nino.content.getText('/_admin/roles/scope/warning'), Nino.adminUi.text( entering[0].label ) ) ) === false )
					return;

				chosen = chosen.concat( [ perm ] );
				rebuildPicker();
			}

			// What the role may do, in words, and which panels are in detail mode
			function refresh() {

				const state = roles.scopeState( chosen, scopes );
				const detail = scopes.filter( function( scope ) { return state[scope.scope] === true } ).map( function( scope ) { return Nino.adminUi.text( scope.label ) } );

				detailHint.textContent = detail.length === 0 ? '' : Nino.adminUi.format( Nino.content.getText('/_admin/roles/scope/detail-hint'), detail.join(', ') );
				detailHint.classList.toggle( 'admin-hidden', detail.length === 0 || fullCheck.checked );

				summaryTitle.textContent = Nino.content.getText('/_admin/roles/summary/title');
				summaryList.innerHTML = '';

				roles.summarize( fullCheck.checked ? [ '/*' ] : chosen, roles._permOptions, scopes ).forEach( function( line ) {
					const item = dc.createElement('li');
					item.textContent = line;
					summaryList.appendChild( item );
				} );
			}

			// Full access is every permission there is, so picking single ones
			// beside it would say something the save does not do. The picker
			// goes away for as long as the switch is on rather than greying
			// out, and what the role held is still there when it goes off again
			const fullHint = dc.createElement('p');
			fullHint.className = 'nino-admin-hint';
			fullHint.textContent = Nino.content.getText('/_admin/roles/label/full-hint');
			fieldset.appendChild( fullHint );

			function applyFullAccess() {
				picker.classList.toggle( 'admin-hidden', fullCheck.checked );
				scopeBox.classList.toggle( 'admin-hidden', fullCheck.checked );
				fullHint.classList.toggle( 'admin-hidden', fullCheck.checked === false );
				refresh();
			}

			fullCheck.addEventListener( 'change', applyFullAccess );
			applyFullAccess();

			return { fieldset : fieldset, fullCheck : fullCheck, perms : function() { return chosen.slice() } };
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

			const report = function( ok ) {
				if( typeof done === 'function' )
					done( ok );
			};

			const msg = dc.getElementById('roles-form-msg');

			const perms = permissions.fullCheck.checked ? [ '/*' ] : permissions.perms();

			msg.textContent = Nino.content.getText('/_admin/roles/msg/pending');

			Nino.admin.roles._apiCall( 'save', { id : idInput.value.trim(), label : nameInput.value.trim(), perms : perms }, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/roles/error/save' );
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
					Nino.admin.roles._openRole( response.id );
					dc.getElementById('roles-form-msg').textContent = Nino.content.getText('/_admin/roles/msg/saved');
					report( true );
				} );
			} );
		},

		/**
		 *	Delete the current role, after confirmation, and return to the list
		 *
		 *	@return		void
		 */
		_delete : function() {

			if( wn.confirm( Nino.content.getText('/_admin/roles/confirm/delete') ) === false )
				return;

			const role = Nino.admin.roles._current;
			const msg 	= dc.getElementById('roles-form-msg');

			Nino.admin.roles._apiCall( 'delete', { id : role.id }, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = Nino.adminUi.api.errorText( status, response, '/_admin/roles/error/save' );
					return;
				}

				Nino.admin.roles._roles = Nino.admin.roles._roles.filter( function( r ) { return r.id !== role.id } );
				Nino.admin.roles._current = null;
				Nino.admin.roles._renderList();
				Nino.admin.roles._showList();
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
