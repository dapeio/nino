

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
 *													holds without a panel behind it. Manager-only, same
 *													gate the backend enforces independently (see
 *													Roles/Roles.php beside it).
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
		// The role on the form, null while a new one is being made
		_current			: null,
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
		 *	called when it is selected, so the hash gets synced to reality
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.roles._ready === false ) {
				Nino.admin.roles.init();
				return;
			}

			if( dc.getElementById('roles-form').classList.contains('admin-hidden') === false )
				return Nino.admin.roles._showForm();

			Nino.admin.roles._showList();
		},

		/**
		 *	Call a roles/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "save", becomes "roles/save")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'roles/'+ endpoint, data : JSON.stringify( payload ) } );
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
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/roles/error/load') );
			container.appendChild( p );
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

				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.roles._openRole( role.id ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			if( Nino.admin.roles._roles.length > 0 )
				wrap.appendChild( ul );

			const add = dc.createElement('button');
			add.type = 'button';
			add.className = 'nino-admin-btn-primary';
			add.textContent = Nino.content.getText('/_admin/roles/label/new');
			add.addEventListener( 'click', function() { Nino.admin.roles._openRole( null ) } );
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
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.roles._showList() } );
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

			if( role === null )
				idInput.focus();
		},

		/**
		 *	The permissions fieldset: a "full access" switch that stands in for
		 *	all of them at once ('/*'), and below it the permissions themselves
		 *	as the shared multi-reference picker (Nino.adminUi.elementList) -
		 *	the chosen ones together at the top with a ✕ each, everything else
		 *	behind one search field.
		 *
		 *	A checkbox per permission was the first shape and does not survive
		 *	the number: the list is one entry per panel and tab of every active
		 *	module, it grows with every module a project adds, and a role
		 *	typically holds a handful of them scattered through three group
		 *	boxes. The picker shows what the role HAS as a short list and makes
		 *	finding the next one a search rather than a scan. Unordered
		 *	(ordered: false): a permission set has no first and no last.
		 *
		 *	Every permission the backend offers is here, the ones no panel is
		 *	offering right now included - see \Nino\Modules\Users\Admin::permOptions().
		 *	Those carry the "other" group name instead of a panel label, so a
		 *	permission from a switched-off module is visible, keepable and
		 *	removable rather than silently dropped on the next save.
		 *
		 *	@param		{Array}		perms					The role's current permissions
		 *
		 *	@return		{Object}								{ fieldset, fullCheck, perms() }
		 */
		_renderPermissions : function( perms ) {

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

			// The rail's own group order, then everything no panel offers -
			// the group name goes in front of each entry, so the search finds
			// a whole group by typing its name and the list reads in the same
			// order the navigation does
			const GROUPS = [ 'content', 'structure', 'system', 'other' ];

			const options = [];
			GROUPS.forEach( function( group ) {
				Nino.admin.roles._permOptions.filter( function( option ) { return option.group === group } ).forEach( function( option ) {
					options.push( {
						value : option.perm,
						// A fill key or literal text a module's panel chose, and
						// the bare permission string for one no panel offers
						// (see Users::permOptions())
						label : Nino.admin.roles._groupName( group )+ ' · '+ Nino.adminUi.text( option.label ),
					} );
				} );
			} );

			// The role's own permissions, minus full access - that one is the
			// switch above, never a row in here
			let chosen = perms.filter( function( perm ) { return perm !== '/*' } );

			// Permissions typed in below. The picker is rebuilt from its
			// options each time one arrives, so they have to outlive it
			const typed = [];

			function permOptions() {

				const list = options.slice();

				typed.forEach( function( perm ) {
					if( list.some( function( option ) { return option.value === perm } ) === false )
						list.push( { value : perm, label : Nino.admin.roles._groupName('other')+ ' · '+ perm } );
				} );

				return list;
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
					onChange 	: function( value ) { chosen = value },
				} );
			}

			let picker = buildPicker();
			fieldset.appendChild( picker );

			// The scoped permissions (see \Nino\Admin\Admin::scoped()) are a
			// path per action and per field - '/_admin/elements/services/update/
			// title', '/_admin/text/update/page-home/*'. There is no finite list
			// of them to offer: it grows with every type, field and text key a
			// project has. So they are typed rather than picked, and once added
			// they sit in the same list as everything else - removable with the
			// same ✕, and offered again next time by permOptions() server-side,
			// which lists every permission a role already holds
			const addRow = dc.createElement('div');
			addRow.className = 'admin-perm-add';

			const addInput = dc.createElement('input');
			addInput.type = 'text';
			addInput.id = 'roles-permissions-custom';
			addInput.autocomplete = 'off';
			addInput.placeholder = Nino.content.getText('/_admin/roles/perms/custom-placeholder');
			addRow.appendChild( addInput );

			const addBtn = dc.createElement('button');
			addBtn.type = 'button';
			addBtn.className = 'nino-admin-btn-secondary';
			addBtn.textContent = Nino.content.getText('/_admin/common/label/add');
			addRow.appendChild( addBtn );

			fieldset.appendChild( addRow );

			const addHint = dc.createElement('p');
			addHint.className = 'nino-admin-hint';
			addHint.setAttribute( 'aria-live', 'polite' );
			addHint.textContent = Nino.content.getText('/_admin/roles/perms/custom-hint');
			fieldset.appendChild( addHint );

			function addTypedPerm() {

				const perm = addInput.value.trim();

				if( perm === '' )
					return;

				// Full access is the switch at the top of this fieldset, and
				// only that switch - a '/*' row would say the same thing in a
				// second place, where turning it off again means finding it
				if( perm === '/*' ) {
					addHint.textContent = Nino.content.getText('/_admin/roles/perms/custom-full');
					return;
				}

				if( Nino.admin.roles._isValidPerm( perm ) === false ) {
					addHint.textContent = Nino.content.getText('/_admin/roles/perms/custom-invalid');
					return;
				}

				addInput.value = '';
				addHint.textContent = Nino.content.getText('/_admin/roles/perms/custom-hint');

				if( typed.indexOf( perm ) === -1 )
					typed.push( perm );

				if( chosen.indexOf( perm ) === -1 )
					chosen = chosen.concat( [ perm ] );

				const rebuilt = buildPicker();
				picker.replaceWith( rebuilt );
				picker = rebuilt;
				applyFullAccess();
			}

			addBtn.addEventListener( 'click', addTypedPerm );
			// Enter in a text input submits the form it sits in - here that
			// would save the role instead of adding the permission just typed
			addInput.addEventListener( 'keydown', function( ev ) {
				if( ev.key !== 'Enter' )
					return;
				ev.preventDefault();
				addTypedPerm();
			} );

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
				addRow.classList.toggle( 'admin-hidden', fullCheck.checked );
				addHint.classList.toggle( 'admin-hidden', fullCheck.checked );
				fullHint.classList.toggle( 'admin-hidden', fullCheck.checked === false );
			}

			fullCheck.addEventListener( 'change', applyFullAccess );
			applyFullAccess();

			return { fieldset : fieldset, fullCheck : fullCheck, perms : function() { return chosen.slice() } };
		},

		/**
		 *	Whether a typed permission is shaped like one: slash-separated
		 *	segments of letters, digits, '_', '-' and '.', with '*' allowed as
		 *	a whole segment - the wildcard \Nino\Auth::checkPermission() reads
		 *	as "and everything below". Nothing here says the permission exists;
		 *	it says the string is one a check could ever match, which is the
		 *	only thing this form can know
		 *
		 *	@param		{string}	perm
		 *
		 *	@return		{boolean}
		 */
		_isValidPerm : function( perm ) {
			return /^\/([A-Za-z0-9_.-]+|\*)(\/([A-Za-z0-9_.-]+|\*))*$/.test( perm );
		},

		/**
		 *	What a permission group is called: the navigation's own heading for
		 *	the three the rail has, and a name of its own for the fourth, which
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
		 *
		 *	@return		void
		 */
		_save : function( idInput, nameInput, permissions ) {

			const msg = dc.getElementById('roles-form-msg');

			const perms = permissions.fullCheck.checked ? [ '/*' ] : permissions.perms();

			msg.textContent = Nino.content.getText('/_admin/roles/msg/pending');

			Nino.admin.roles._apiCall( 'save', { id : idInput.value.trim(), label : nameInput.value.trim(), perms : perms }, function( status, response ) {

				if( status !== 200 ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/roles/error/save') );
					return;
				}

				Nino.admin.roles._apiCall( 'list', {}, function( listStatus, listResponse ) {
					if( listStatus !== 200 || listResponse === null )
						return Nino.admin.roles._showError( dc.getElementById('roles-list'), listStatus, listResponse );
					Nino.admin.roles._roles = listResponse.roles;
					Nino.admin.roles._permOptions = listResponse.permOptions;
					Nino.admin.roles._renderList();
					Nino.admin.roles._openRole( response.id );
					dc.getElementById('roles-form-msg').textContent = Nino.content.getText('/_admin/roles/msg/saved');
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
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/roles/error/save') );
					return;
				}

				Nino.admin.roles._roles = Nino.admin.roles._roles.filter( function( r ) { return r.id !== role.id } );
				Nino.admin.roles._current = null;
				Nino.admin.roles._renderList();
				Nino.admin.roles._showList();
			} );
		},
	};

})(window, document, document.documentElement, document.body);
