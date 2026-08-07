

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
		_selectedId : null,
		_dirty 			: false,

		/**
		 *	@return		{string|null}				Id of the selected node
		 */
		selectedId : function() {
			return Nino.templates._selectedId;
		},

		/**
		 *	@return		{Object|null}				The selected node itself
		 */
		selectedNode : function() {

			if( Nino.templates._current === null || Nino.templates._selectedId === null )
				return null;

			return Nino.templates.tree.find( Nino.templates._current.nodes, Nino.templates._selectedId );
		},

		/**
		 *	@param		{string|null}	id
		 *
		 *	@return		void
		 */
		select : function( id ) {
			Nino.templates._selectedId = id;
			Nino.templates.canvas.rerender();
			Nino.templates.inspector.render();
		},

		/**
		 *	Mark the open document as having unsaved changes - what enables
		 *	the Save button and arms the "leave without saving?" guards
		 *
		 *	@return		void
		 */
		markDirty : function() {

			Nino.templates._dirty = true;

			const save = dc.getElementById('tb-save');
			if( save !== null )
				save.disabled = false;

			const state = dc.getElementById('tb-bar-state');
			if( state !== null )
				state.textContent = 'unsaved changes';
		},

		/**
		 *	@return		void
		 */
		markClean : function() {

			Nino.templates._dirty = false;

			const save = dc.getElementById('tb-save');
			if( save !== null )
				save.disabled = true;

			const state = dc.getElementById('tb-bar-state');
			if( state !== null )
				state.textContent = '';
		},

		/**
		 *	Run one of the selected block's own actions. Every one of them is
		 *	a pure tree operation (see assets/tree.js) followed by a redraw -
		 *	nothing reaches the filesystem until Save
		 *
		 *	@param		{string}	action			'moveup' | 'movedown' | 'duplicate' | 'remove'
		 *	@param		{string}	nodeId
		 *
		 *	@return		void
		 */
		runAction : function( action, nodeId ) {

			if( Nino.templates._readonly() === true )
				return;

			const nodes = Nino.templates._current.nodes;
			let 	done 	= false;

			if( action === 'moveup' )
				done = Nino.templates.tree.move( nodes, nodeId, -1 );

			if( action === 'movedown' )
				done = Nino.templates.tree.move( nodes, nodeId, 1 );

			if( action === 'duplicate' ) {
				const copy = Nino.templates.tree.duplicate( nodes, nodeId );
				if( copy !== null ) {
					Nino.templates._selectedId = copy.id;
					done = true;
				}
			}

			if( action === 'remove' ) {
				done = Nino.templates.tree.remove( nodes, nodeId );
				if( done === true )
					Nino.templates._selectedId = null;
			}

			if( done === false )
				return;

			Nino.templates.markDirty();
			Nino.templates.canvas.rerender();
			Nino.templates.inspector.render();
		},

		/**
		 *	Insert a library block. Where it lands follows from what is
		 *	selected: inside it if that block takes children, next to it if
		 *	it doesn't, and at the end of the document if nothing is selected
		 *	at all - the same "it goes where you are looking" rule the
		 *	Webpages and Pages lists follow
		 *
		 *	@param		{string}	blockKey
		 *
		 *	@return		void
		 */
		insertBlock : function( blockKey ) {

			if( Nino.templates._readonly() === true )
				return;

			const block = Nino.templates.blocks.get( blockKey );

			if( block === null || block.html === '' )
				return;

			// A block's starting markup goes through the same parser the
			// documents do, on the server - which is what keeps a block.tpl
			// written exactly like template markup, with no special cases
			Nino.templates.apiCall( 'library/parse', { block : blockKey }, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.templates.showError( dc.getElementById('tb-canvas-notice'), status, response );

				const fresh = response.nodes.map( function( node ) { return Nino.templates.tree.assignIds( node ) } );
				const anchor = Nino.templates.selectedNode();

				const mode = ( anchor !== null && Nino.templates._takesChildren( anchor ) === true ) ? 'child' : 'after';
				const anchorId = ( anchor !== null ) ? anchor.id : null;

				if( Nino.templates.tree.insert( Nino.templates._current.nodes, anchorId, mode, fresh ) === false )
					return;

				const inserted = fresh.filter( function( node ) { return node.type === 'element' } )[0];
				if( inserted !== undefined )
					Nino.templates._selectedId = inserted.id;

				Nino.templates.markDirty();
				Nino.templates.canvas.rerender();
				Nino.templates.inspector.render();
			} );
		},

		/**
		 *	@param		{Object}	node
		 *
		 *	@return		{boolean}						Whether this node's block accepts children
		 */
		_takesChildren : function( node ) {

			const block = Nino.templates.blocks.get( Nino.templates.blocks.match( node ) );

			return block !== null && Array.isArray( block.children ) === true && block.children.length > 0;
		},

		/**
		 *	@return		{boolean}						Whether the open document refuses edits
		 */
		_readonly : function() {
			return Nino.templates._current === null
				|| ( Nino.templates._current.readonly !== null && Nino.templates._current.readonly !== undefined );
		},

		/**
		 *	Write the open document back. The whole tree is posted and the
		 *	server serializes it (see Documents::apiSave()) - the client
		 *	never builds .tpl source itself, so there is exactly one
		 *	serializer and the round-trip guarantee has one place to hold
		 *
		 *	@return		void
		 */
		save : function() {

			if( Nino.templates._current === null || Nino.templates._dirty === false )
				return;

			const state = dc.getElementById('tb-bar-state');
			state.textContent = 'Saving …';

			Nino.templates.apiCall( 'documents/save', {
				name 	: Nino.templates._current.name,
				nodes : Nino.templates._current.nodes,
			}, function( status, response ) {

				if( status !== 200 || response === null ) {
					state.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Save failed.' );
					return;
				}

				Nino.templates.markClean();

				// Reload rather than trusting the in-memory tree: this is
				// what the file now actually parses back to, ids included,
				// and any disagreement would show up here rather than on the
				// next edit
				Nino.templates.openDocument( Nino.templates._current.name, true );
			} );
		},

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
		 *	@param		{boolean}	[force]		Skip the unsaved-changes prompt (used after a save)
		 *
		 *	@return		void
		 */
		openDocument : function( name, force ) {

			// Everything the builder does stays in memory until Save, so
			// switching documents is where unsaved work would silently go
			if( force !== true && Nino.templates._dirty === true
				&& wn.confirm( 'Discard unsaved changes to '+ Nino.templates._current.name+ '.tpl?' ) === false )
				return;

			const tree = dc.getElementById('tb-canvas-tree');

			dc.getElementById('tb-canvas-hint').textContent = 'Loading …';

			Nino.templates.apiCall( 'documents/load', { name : name }, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.templates.showError( tree, status, response );

				Nino.templates._current 		= response;
				Nino.templates._selectedId 	= null;

				Nino.templates.markClean();
				dc.getElementById('tb-bar-document').textContent = name+ '.tpl';

				dc.querySelectorAll('#tb-documents-list a').forEach( function( a ) {
					a.classList.toggle( 'active', a.textContent === name );
				} );

				// A read-only document is browsable but not editable, so the
				// palette is greyed out rather than silently doing nothing
				dc.getElementById('tb-palette').classList.toggle( 'tb-disabled', Nino.templates._readonly() );

				Nino.templates.canvas.render( response );
				Nino.templates.inspector.render();
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

					const li 			= dc.createElement('li');
					const button 	= dc.createElement('button');
					const name 		= dc.createElement('span');
					const tag 		= dc.createElement('span');

					name.className = 'tb-palette-name';
					name.textContent = block.name;

					tag.className = 'tb-palette-tag';
					tag.textContent = block.tag;

					button.type = 'button';
					button.className = 'tb-palette-item';
					button.title = 'Insert '+ block.name;
					button.appendChild( name );
					button.appendChild( tag );
					button.addEventListener( 'click', function() { Nino.templates.insertBlock( block.key ) } );

					li.appendChild( button );
					ul.appendChild( li );
				} );

				wrap.appendChild( ul );
			} );
		},
	} );

	Nino.events.bindCallback( 'ready', function() {

		if( dc.getElementById('tb-page-wrap') === null )
			return;

		dc.getElementById('tb-save').addEventListener( 'click', Nino.templates.save );

		// Clicking past every box deselects, rather than leaving the
		// inspector pointed at something the eye has moved on from
		dc.getElementById('tb-canvas').addEventListener( 'click', function() { Nino.templates.select( null ) } );

		wn.addEventListener( 'beforeunload', function( event ) {
			if( Nino.templates._dirty === true )
				event.preventDefault();
		} );

		Nino.templates.init();
	} );

})(window, document, document.documentElement, document.body);
