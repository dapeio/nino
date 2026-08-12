

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Config" module: edit a curated whitelist of "soft"
 *													config.php values (error/locales/assets/routes) as raw
 *													json - see _admin/Admin.php's Config class docblock for why
 *													/nino/html/images and /nino/auth/user get their own
 *													dedicated editors (images.js/users.js) instead.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	// key -> short explanation, shown above that key's editor
	const KEY_HINTS = {
		'/nino/error/log' 					: 'true/false - write php errors to a log file.',
		'/nino/error/display' 			: 'true/false - show php errors directly in the frontend (in production: false).',
		'/nino/locales/native' 			: 'Native language of the site, eg. "de_DE".',
		'/nino/locales/available' 	: 'List of available languages, eg. ["de_DE", "en_US"].',
		'/nino/html/assets' 				: 'Bundle name -> list of files bundled together.',
		'/nino/html/navs' 					: 'Menus the page editors offer a checkbox for, eg. ["main", "footer"]. A route joins one via its own "navs" key.',
		'/nino/http/routes' 				: 'Routing table: "METHOD://uri" -> { uri, body, statusCode, locale }.',
	};

	Nino.admin.config = {

		_values 		: {},
		_currentKey : null,
		_ready 			: false,

		/**
		 *	Load every editable value and render the list
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('config-list') === null )
				return;

			Nino.admin.config._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.config._showError( dc.getElementById('config-list'), status, response );

				Nino.admin.config._values = response.values;
				Nino.admin.config._renderList();
				Nino.admin.config._showList();
				Nino.admin.config._ready = true;
			} );
		},

		/**
		 *	Re-show whichever level (list or one key's editor) is currently on
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.config._ready === false )
				return Nino.admin.config.init();

			if( dc.getElementById('config-form').classList.contains('admin-hidden') === false )
				return Nino.admin.config._showForm();

			Nino.admin.config.init();
		},

		/**
		 *	Call a config/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "config/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'config/'+ endpoint, data : JSON.stringify( payload ) } );
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
			p.className = 'admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to load.' );
			container.appendChild( p );
		},

		_showList : function() {
			dc.getElementById('config-list').classList.remove('admin-hidden');
			dc.getElementById('config-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('config-list').classList.add('admin-hidden');
			dc.getElementById('config-form').classList.remove('admin-hidden');
		},

		/**
		 *	Render the editable-key list
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('config-list');
			wrap.innerHTML = '';

			const ul = dc.createElement('ul');
			ul.className = 'admin-drill-list';
			Object.keys( Nino.admin.config._values ).forEach( function( key ) {
				const li 		= dc.createElement('li');
				const link	= dc.createElement('a');
				link.href = '#';
				link.textContent = key;
				link.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.config._openForm( key ) } );
				li.appendChild( link );
				ul.appendChild( li );
			} );
			wrap.appendChild( ul );
		},

		/**
		 *	Open the raw-json editor for one key
		 *
		 *	@param		{string}	key
		 *
		 *	@return		void
		 */
		_openForm : function( key ) {

			Nino.admin.config._currentKey = key;

			const wrap = dc.getElementById('config-form');
			wrap.innerHTML = '';

			const backLink = dc.createElement('a');
			backLink.href = '#';
			backLink.className = 'back-link';
			backLink.textContent = 'Back to list';
			backLink.addEventListener( 'click', function( ev ) { ev.preventDefault(); Nino.admin.config._showList() } );
			wrap.appendChild( backLink );

			const title = dc.createElement('h3');
			title.textContent = key;
			wrap.appendChild( title );

			const hint = dc.createElement('p');
			hint.className = 'admin-hint';
			hint.textContent = KEY_HINTS[key] ?? '';
			wrap.appendChild( hint );

			const form = dc.createElement('form');

			const textarea = dc.createElement('textarea');
			textarea.id = 'config-form-value';
			textarea.rows = 14;
			textarea.spellcheck = false;
			textarea.value = Nino.admin.config._values[key] ?? '';
			form.appendChild( textarea );

			// Save + its message in the shared actions row every module's form
			// ends on - assets/style.css pins that row to the bottom of the
			// viewport, so a long value never puts Save out of reach
			const actions = dc.createElement('div');
			actions.className = 'editor-form-actions';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.textContent = 'Save';
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'config-form-msg';
			actions.appendChild( msg );

			form.appendChild( actions );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.config._save() } );

			wrap.appendChild( form );
			Nino.admin.config._showForm();
		},

		/**
		 *	Save the currently open key
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg = dc.getElementById('config-form-msg');
			const value = dc.getElementById('config-form-value').value;

			msg.textContent = 'Saving …';

			Nino.admin.config._apiCall( 'save', { key : Nino.admin.config._currentKey, value : value }, function( status, response ) {
				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to save.' );
					return;
				}
				msg.textContent = 'Saved.';
				Nino.admin.config.init();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.config.init );

})(window, document, document.documentElement, document.body);
