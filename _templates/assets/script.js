

/**
 *	Nino										A compact filesystembased php framework
 *	Templates								Builder shell: loads the block library once, lists the
 *													project's /templates/*.tpl files and hands whichever one
 *													is picked to the canvas. Also the shared api-call/error
 *													helpers the other files build on - same shape as
 *													_install/assets/script.js.
 *
 *													There is no login handling here: /_templates is gated by
 *													_admin's session flag, and the login form it serves when
 *													that gate is closed is _admin's own (see page-login.tpl).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.templates = wn.Nino.templates || {};

	Object.assign( wn.Nino.templates, {

		_documents 	: [],
		_current 		: null,

		/**
		 *	Call a /_templates api action
		 *
		 *	@param		{string}		action			Action name (eg. "documents/load")
		 *	@param		{Object}		payload			Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback		Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		apiCall : function( action, payload, callback ) {
			Nino.http.sendRequest( '/_templates/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : action, data : JSON.stringify( payload ) } );
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
		showError : function( container, status, response ) {
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Request failed.' );
			container.appendChild( p );
		},

		/**
		 *	Load the block library, then the document list - in that order,
		 *	since the canvas can't label a single node before it knows what
		 *	the library describes
		 *
		 *	@return		void
		 */
		init : function() {

			const list = dc.getElementById('tb-documents-list');

			Nino.templates.apiCall( 'library/blocks', {}, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.templates.showError( list, status, response );

				Nino.templates.blocks.setAll( response.blocks );
				Nino.templates.renderPalette();
				Nino.templates.loadDocuments();
			} );
		},

		loadDocuments : function() {

			const list = dc.getElementById('tb-documents-list');

			Nino.templates.apiCall( 'documents/list', {}, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.templates.showError( list, status, response );

				Nino.templates._documents = response.documents;
				Nino.templates.renderDocuments();
			} );
		},

		renderDocuments : function() {

			const list = dc.getElementById('tb-documents-list');
			const ul 	 = dc.createElement('ul');

			Nino.templates._documents.forEach( function( doc ) {

				const li = dc.createElement('li');
				const a  = dc.createElement('a');

				a.href = '#';
				a.textContent = doc.name;
				a.className = ( doc.editable === true ) ? '' : 'tb-document--locked';
				a.title = ( doc.editable === true ) ? doc.name+ '.tpl' : doc.name+ '.tpl - not editable';

				a.addEventListener( 'click', function( ev ) {
					ev.preventDefault();
					Nino.templates.openDocument( doc.name );
				} );

				li.appendChild( a );
				ul.appendChild( li );
			} );

			list.innerHTML = '';
			list.appendChild( ul );
		},

		/**
		 *	@param		{string}	name			Template name, without the .tpl extension
		 *
		 *	@return		void
		 */
		openDocument : function( name ) {

			const tree = dc.getElementById('tb-canvas-tree');

			dc.getElementById('tb-canvas-hint').textContent = 'Loading …';

			Nino.templates.apiCall( 'documents/load', { name : name }, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.templates.showError( tree, status, response );

				Nino.templates._current = response;
				dc.getElementById('tb-bar-document').textContent = name+ '.tpl';

				dc.querySelectorAll('#tb-documents-list a').forEach( function( a ) {
					a.classList.toggle( 'active', a.textContent === name );
				} );

				Nino.templates.canvas.render( response );
			} );
		},

		/**
		 *	The block palette, grouped by each manifest's own 'category'.
		 *	Inserting is patch 2 - for now this is the catalogue the library
		 *	actually resolved, which is what makes a missing or misspelled
		 *	manifest visible at a glance
		 *
		 *	@return		void
		 */
		renderPalette : function() {

			const wrap 		= dc.getElementById('tb-palette-list');
			const blocks 	= Nino.templates.blocks.all();
			const groups 	= {};

			Object.keys( blocks ).forEach( function( key ) {
				const block = blocks[key];
				groups[block.category] = groups[block.category] || [];
				groups[block.category].push( block );
			} );

			wrap.innerHTML = '';

			Object.keys( groups ).sort().forEach( function( category ) {

				const h4 = dc.createElement('h4');
				h4.textContent = category;
				wrap.appendChild( h4 );

				const ul = dc.createElement('ul');

				groups[category].forEach( function( block ) {

					const li 		= dc.createElement('li');
					const name 	= dc.createElement('span');
					const tag 	= dc.createElement('span');

					name.className = 'tb-palette-name';
					name.textContent = block.name;

					tag.className = 'tb-palette-tag';
					tag.textContent = block.tag;

					li.appendChild( name );
					li.appendChild( tag );
					ul.appendChild( li );
				} );

				wrap.appendChild( ul );
			} );
		},
	} );

	Nino.events.bindCallback( 'ready', function() {
		if( dc.getElementById('tb-page-wrap') !== null )
			Nino.templates.init();
	} );

})(window, document, document.documentElement, document.body);
