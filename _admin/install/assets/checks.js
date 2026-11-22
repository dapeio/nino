/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 1: environment/permission diagnostics - PHP version,
 *													required extensions, writable directories. See
 *													_admin/install/Install.php's Checks class for what's actually probed.
 *													The rows are the list of the workbench (Nino.adminUi.list()), each
 *													with the badge of its state: the word and a sign, never a colour alone.
 *													What the server answers is a name and a state; the sentence that says
 *													what an extension is for is the table's (text.js), in the language
 *													of the page.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.checks = {

		_ready : false,

		// The last answer of the server, which a change of the language draws again
		_data : null,

		/**
		 *	Run every check and render the result
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('checks-results');
			if( wrap === null )
				return;

			Nino.install.apiCall( 'checks/run', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );

				Nino.install.checks._data = response;
				Nino.install.checks._render();
				Nino.install.checks._ready = true;
			} );
		},

		showCurrent : function() {

			const mount = dc.getElementById('checks-recheck');

			// The one button of the step besides the bar: it asks the server again
			if( mount !== null && mount.children.length === 0 )
				mount.appendChild( Nino.adminUi.button( Nino.install.t('/_admin/install/checks/recheck'), Nino.install.checks.init, { kind : 'secondary', id : 'checks-refresh' } ) );

			Nino.install.checks.init();
		},

		retext : function() {

			const button = dc.getElementById('checks-refresh');

			if( button !== null )
				button.textContent = Nino.install.t('/_admin/install/checks/recheck');

			if( Nino.install.checks._data !== null )
				Nino.install.checks._render();
		},

		/**
		 *	@return		void
		 */
		_render : function() {

			const data = Nino.install.checks._data;
			const t = Nino.install.t;
			const wrap = dc.getElementById('checks-results');
			wrap.innerHTML = '';

			wrap.appendChild( Nino.install.checks._group( t('/_admin/install/checks/php'), [
				{ name : 'PHP '+ data.php.version, detail : t( '/_admin/install/checks/php-required', data.php.required ), ok : data.php.ok },
			] ) );

			wrap.appendChild( Nino.install.checks._group( t('/_admin/install/checks/extensions'), Object.keys( data.extensions ).map( function( name ) {
				// What an extension is for is the table's word; an extension the table does not know stands with its name alone
				return { name : name, detail : t( '/_admin/install/checks/extension-'+ name.toLowerCase() ), ok : data.extensions[name].ok };
			} ) ) );

			wrap.appendChild( Nino.install.checks._group( t('/_admin/install/checks/directories'), Object.keys( data.directories ).map( function( path ) {
				const dir = data.directories[path];
				return { name : path, detail : t( dir.exists ? ( dir.writable ? '/_admin/install/checks/writable' : '/_admin/install/checks/not-writable' ) : '/_admin/install/checks/created' ), ok : dir.ok };
			} ) ) );
		},

		/**
		 *	@param		{string}	title
		 *	@param		{Array}		rows			[ { name, detail, ok }, ... ]
		 *
		 *	@return		{Element}
		 */
		_group : function( title, rows ) {

			const t = Nino.install.t;

			// Diagnostics are read, never opened: the list of the workbench, a badge at the end of each row says its state
			return Nino.adminUi.list( rows.map( function( row ) {
				return {
					key		: row.name,
					title	: row.name,
					detail: row.detail,
					badge	: { text : t( row.ok ? '/_admin/install/checks/ok' : '/_admin/install/checks/fail' ), state : row.ok ? 'ok' : 'error' },
				};
			} ), { title : title } );
		},
	};

})(window, document, document.documentElement, document.body);
