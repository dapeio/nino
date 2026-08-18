

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 1: environment/permission diagnostics - PHP version,
 *													required extensions, writable directories. See
 *													_install/Install.php's Checks class for what's actually probed.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.checks = {

		_ready : false,

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

				Nino.install.checks._render( response );
				Nino.install.checks._ready = true;
			} );
		},

		showCurrent : function() {
			Nino.install.checks.init();
		},

		/**
		 *	@param		{Object}	data		{ php, extensions, directories }, see Checks::apiRun()
		 *
		 *	@return		void
		 */
		_render : function( data ) {

			const wrap = dc.getElementById('checks-results');
			wrap.innerHTML = '';

			wrap.appendChild( Nino.install.checks._group( 'PHP version', [
				{ name : 'PHP '+ data.php.version, detail : 'required: >= '+ data.php.required, ok : data.php.ok },
			] ) );

			wrap.appendChild( Nino.install.checks._group( 'Extensions', Object.keys( data.extensions ).map( function( name ) {
				const ext = data.extensions[name];
				return { name : name, detail : ext.reason, ok : ext.ok };
			} ) ) );

			wrap.appendChild( Nino.install.checks._group( 'Directories', Object.keys( data.directories ).map( function( path ) {
				const dir = data.directories[path];
				const detail = dir.exists ? ( dir.writable ? 'writable' : 'not writable' ) : ( dir.tracked ? 'missing' : 'created automatically on first use' );
				return { name : path, detail : detail, ok : dir.ok };
			} ) ) );
		},

		/**
		 *	@param		{string}	title
		 *	@param		{Array}		rows			[ { name, detail, ok }, ... ]
		 *
		 *	@return		{Element}
		 */
		_group : function( title, rows ) {

			const group = dc.createElement('section');
			group.className = 'install-check-group';

			const h3 = dc.createElement('h3');
			h3.className = 'nino-admin-eyebrow';
			h3.textContent = title;
			group.appendChild( h3 );

			// Diagnostics are read, never opened - the shared grouped list's
			// dense variant supplies the surface and the row separators
			const list = dc.createElement('ul');
			list.className = 'nino-admin-list nino-admin-list-dense';
			group.appendChild( list );

			rows.forEach( function( row ) {

				const div = dc.createElement('li');
				div.className = 'install-check-row';

				const left = dc.createElement('div');
				const name = dc.createElement('span');
				name.className = 'install-check-row-name';
				name.textContent = row.name;
				left.appendChild( name );

				if( row.detail ) {
					const detail = dc.createElement('span');
					detail.className = 'install-check-row-detail';
					detail.textContent = ' – '+ row.detail;
					left.appendChild( detail );
				}

				div.appendChild( left );

				const status = dc.createElement('span');
				status.className = 'install-check-status '+ ( row.ok ? 'install-check-status--ok' : 'install-check-status--fail' );
				status.textContent = row.ok ? 'OK' : 'FAIL';
				div.appendChild( status );

				list.appendChild( div );
			} );

			return group;
		},
	};

	Nino.events.bindCallback( 'ready', function() {
		const refresh = dc.getElementById('checks-refresh');
		if( refresh !== null )
			refresh.addEventListener( 'click', Nino.install.checks.init );
	} );

})(window, document, document.documentElement, document.body);
