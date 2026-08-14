

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Users" module: create/delete admin accounts - the "set"
 *													half of what _editor's Users panel edits (mail/password for
 *													an existing account). Same split as elementtypes.js/
 *													_editor's Elements. No mail/password edit here on purpose,
 *													same reasoning as _editor's Users panel already documents
 *													for itself.
 *
 *													Permissions are the exception: a raw json textarea per
 *													user, unwhitelisted unlike _editor's own checkbox editor -
 *													see Admin.php's Users class docblock for why.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.users = {

		_ready : false,

		/**
		 *	Load every admin user and render the list + add-user form
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('users-list') === null )
				return;

			Nino.admin.users._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.users._showError( status, response );

				Nino.admin.users._render( response.users );
				Nino.admin.users._ready = true;
			} );
		},

		/**
		 *	Re-fetch and re-show the list when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.admin.users.init();
		},

		/**
		 *	Call a devusers/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "devusers/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'devusers/'+ endpoint, data : JSON.stringify( payload ) } );
		},

		/**
		 *	Show a failed request's status/error
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( status, response ) {
			const wrap = dc.getElementById('users-list');
			wrap.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			wrap.appendChild( p );
		},

		/**
		 *	Render the user list, then the add-user form
		 *
		 *	@param		{Object[]}	users
		 *
		 *	@return		void
		 */
		_render : function( users ) {

			const wrap = dc.getElementById('users-list');
			wrap.innerHTML = '';

			const ul = dc.createElement('ul');
			ul.className = 'nino-admin-list';
			users.forEach( function( user ) {
				const li = dc.createElement('li');

				const span = dc.createElement('span');
				span.textContent = user.mail+ ( user.isManager ? ' (Verwaltung)' : '' );
				li.appendChild( span );

				const permsLink = dc.createElement('a');
				permsLink.href = '#';
				permsLink.textContent = 'Permissions';
				permsLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.users._togglePermissions( user, permsBox ) } );
				li.appendChild( permsLink );

				const delBtn = dc.createElement('button');
				delBtn.type = 'button';
				delBtn.className = 'red';
				delBtn.textContent = 'Delete';
				delBtn.addEventListener( 'click', function() { Nino.admin.users._delete( user.mail ) } );
				li.appendChild( delBtn );

				const permsBox = dc.createElement('div');
				permsBox.className = 'admin-hidden';
				li.appendChild( permsBox );

				ul.appendChild( li );
			} );
			wrap.appendChild( ul );

			wrap.appendChild( Nino.admin.users._renderAddForm() );
		},

		/**
		 *	Show/hide a user's raw permissions editor (built lazily on first
		 *	open, then just toggled)
		 *
		 *	@param		{Object}	user
		 *	@param		{Element}	box
		 *
		 *	@return		void
		 */
		_togglePermissions : function( user, box ) {

			if( box.classList.contains('admin-hidden') === false ) {
				box.classList.add('admin-hidden');
				return;
			}

			if( box.childNodes.length === 0 )
				Nino.admin.users._renderPermissions( user, box );

			box.classList.remove('admin-hidden');
		},

		/**
		 *	Build a user's permissions editor: a raw json textarea (no
		 *	whitelist, unlike _editor's own checkbox editor - this is the
		 *	developer bypass, see Admin.php's Users class docblock)
		 *
		 *	@param		{Object}	user
		 *	@param		{Element}	box
		 *
		 *	@return		void
		 */
		_renderPermissions : function( user, box ) {

			const form = dc.createElement('form');

			const textarea = dc.createElement('textarea');
			textarea.rows = 4;
			textarea.spellcheck = false;
			textarea.value = JSON.stringify( user.perms ?? [], null, 2 );
			form.appendChild( textarea );

			const msg = dc.createElement('p');
			form.appendChild( msg );

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save permissions';
			form.appendChild( saveBtn );

			form.addEventListener( 'submit', function( ev ) {
				ev.preventDefault();

				let perms;
				try {
					perms = JSON.parse( textarea.value );
				} catch( e ) {
					msg.textContent = 'Invalid json: '+ e.message;
					return;
				}

				msg.textContent = 'Saving …';

				Nino.admin.users._apiCall( 'permissions', { mail : user.mail, perms : perms }, function( status, response ) {
					if( status !== 200 || response === null ) {
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
						return;
					}
					user.perms = response.perms;
					msg.textContent = 'Saved.';
				} );
			} );

			box.appendChild( form );
		},

		/**
		 *	Build the "add user" form
		 *
		 *	@return		{Element}
		 */
		_renderAddForm : function() {

			const form = dc.createElement('form');
			form.id = 'users-add-form';
			form.className = 'nino-admin-card';

			const mailInput = dc.createElement('input');
			mailInput.type = 'email';
			mailInput.placeholder = 'Email';
			mailInput.required = true;
			form.appendChild( mailInput );

			const pwInput = dc.createElement('input');
			pwInput.type = 'password';
			pwInput.placeholder = 'Password (min. 8 characters)';
			pwInput.required = true;
			pwInput.minLength = 8;
			form.appendChild( pwInput );

			const managerLabel = dc.createElement('label');
			const managerCheck = dc.createElement('input');
			managerCheck.type = 'checkbox';
			managerLabel.appendChild( managerCheck );
			managerLabel.appendChild( dc.createTextNode(' Verwaltungsrechte (darf andere Nutzer bearbeiten)') );
			form.appendChild( managerLabel );

			const msg = dc.createElement('p');
			msg.id = 'users-add-msg';
			form.appendChild( msg );

			const submitBtn = dc.createElement('button');
			submitBtn.type = 'submit';
			submitBtn.textContent = 'Create user';
			form.appendChild( submitBtn );

			form.addEventListener( 'submit', function( ev ) {
				ev.preventDefault();
				msg.textContent = 'Creating …';

				Nino.admin.users._apiCall( 'create', {
					mail 				: mailInput.value,
					password 		: pwInput.value,
					isManager 	: managerCheck.checked,
				}, function( status, response ) {
					if( status !== 200 || response === null ) {
						msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to create.' );
						return;
					}
					Nino.admin.users.init();
				} );
			} );

			return form;
		},

		/**
		 *	Confirm, then delete a user
		 *
		 *	@param		{string}	mail
		 *
		 *	@return		void
		 */
		_delete : function( mail ) {

			if( wn.confirm( 'Really delete user '+ mail+ '?' ) === false )
				return;

			Nino.admin.users._apiCall( 'delete', { mail : mail }, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.users._showError( status, response );
				Nino.admin.users.init();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.users.init );

})(window, document, document.documentElement, document.body);
