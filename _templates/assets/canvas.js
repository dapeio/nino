

/**
 *	Nino										A compact filesystembased php framework
 *	Templates								The canvas: draws a parsed template as a tree of nested
 *													boxes.
 *
 *													Deliberately not a preview. Only the two things that are
 *													impossible to judge from a list of class names are drawn
 *													to scale - a grid column's width and a block's vertical
 *													spacing - and everything else is a labelled box showing
 *													the block's name, its html tag and a short value preview.
 *													Colours, fonts and real typography are the theme's job
 *													(see docs/design.md); reproducing them here would mean
 *													maintaining a second renderer that is wrong in a
 *													different way on every project.
 *
 *													Markup the library doesn't describe still gets a box -
 *													labelled with its tag, id and classes, and marked as
 *													unrecognized. Those are read-only: the builder has no
 *													model for what their properties mean, so it offers none,
 *													but it never hides or drops them either.
 *
 *													Nesting depth is also drawn - each level sits on a
 *													slightly stronger tint than the one above it, so the
 *													layers of a deep grid read apart at a glance instead of
 *													as one wall of boxes.
 *
 *													Clicking a box selects it; the inspector then edits
 *													whatever the library says that block has. Selection is
 *													by node id, so it survives the re-render every edit
 *													triggers.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.templates = wn.Nino.templates || {};

	// Grid widths are drawn at their real proportion, read straight off the
	// block's own 'width' setting rather than from a stylesheet - the canvas
	// stays independent of whichever theme the project happens to bundle
	const GRID_WIDTHS = { '25' : 25, '33' : 33.333, '50' : 50, '66' : 66.666, '75' : 75, '100' : 100 };

	// --space-1..6 from _nino/Nino.css, in rem. Only used to draw margins at
	// roughly the right size; a value being a little off changes nothing
	// about what gets written back
	const SPACE = [ 0, 0.75, 1.5, 2.25, 3, 4, 5 ];

	// How far the depth tint deepens before it stops. Without a cap a
	// deeply nested grid would fade to unreadable; six levels is past
	// anything the structure convention produces (see docs/design.md)
	const MAX_TINT_DEPTH = 6;

	Nino.templates.canvas = {

		_styledIds 	: [],
		_document 	: null,

		/**
		 *	@param		{Object}	doc		{ name, nodes, styledIds, readonly }
		 *
		 *	@return		void
		 */
		render : function( doc ) {

			Nino.templates.canvas._document 	= doc;
			Nino.templates.canvas._styledIds 	= doc.styledIds || [];

			const notice 	= dc.getElementById('tb-canvas-notice');
			const hint 		= dc.getElementById('tb-canvas-hint');

			hint.textContent = '';
			notice.innerHTML = '';

			if( doc.readonly !== null && doc.readonly !== undefined ) {
				const p = dc.createElement('p');
				p.className = 'admin-error tb-notice';
				p.textContent = 'Read-only: '+ doc.readonly;
				notice.appendChild( p );
			}

			Nino.templates.canvas.rerender();
		},

		/**
		 *	Redraw the tree from the current document - called after every
		 *	edit. Cheap enough to do wholesale: a page template is a few
		 *	dozen nodes, and rebuilding beats keeping a diff of boxes in
		 *	step with a tree that is itself the state
		 *
		 *	@return		void
		 */
		rerender : function() {

			const tree = dc.getElementById('tb-canvas-tree');

			if( tree === null || Nino.templates.canvas._document === null )
				return;

			tree.innerHTML = '';
			tree.appendChild( Nino.templates.canvas._list( Nino.templates.canvas._document.nodes || [], 0 ) );
		},

		/**
		 *	Update one box's preview text in place, without rebuilding the
		 *	tree - what the inspector calls while a text field is being
		 *	typed into. A full rerender on every keystroke would work too,
		 *	but it moves focus out of the field being typed in
		 *
		 *	@param		{string}	nodeId
		 *
		 *	@return		void
		 */
		refreshPreview : function( nodeId ) {

			const box = dc.querySelector('.tb-node[data-node-id="'+ nodeId+ '"]');

			if( box === null || Nino.templates.canvas._document === null )
				return;

			const node = Nino.templates.tree.find( Nino.templates.canvas._document.nodes, nodeId );

			if( node === null )
				return;

			const blockKey 	= Nino.templates.blocks.match( node );
			const preview 	= box.querySelector('.tb-node-preview');

			if( preview !== null )
				preview.textContent = Nino.templates.canvas._preview( node, blockKey, Nino.templates.blocks.get( blockKey ) );
		},

		/**
		 *	@param		{Array}		nodes
		 *	@param		{number}	depth
		 *
		 *	@return		{Element}
		 */
		_list : function( nodes, depth ) {

			const wrap = dc.createElement('div');
			wrap.className = 'tb-list';

			nodes.forEach( function( node ) {

				// Whitespace between tags carries no meaning worth drawing -
				// it is kept in the model (that's what makes the round-trip
				// byte-exact) but showing it would bury the actual structure
				if( node.type === 'text' && node.value.trim() === '' )
					return;

				wrap.appendChild( Nino.templates.canvas._node( node, depth ) );
			} );

			return wrap;
		},

		/**
		 *	@param		{Object}	node
		 *
		 *	@return		{Element}
		 */
		_node : function( node, depth ) {

			if( node.type === 'text' )
				return Nino.templates.canvas._leaf( 'tb-node--text', 'text', Nino.templates.blocks.showEntities( node.value.trim() ) );

			if( node.type === 'comment' )
				return Nino.templates.canvas._leaf( 'tb-node--comment', 'comment', node.value.trim() );

			const blockKey 	= Nino.templates.blocks.match( node );
			const block 		= Nino.templates.blocks.get( blockKey );
			const box 			= dc.createElement('div');

			box.className = 'tb-node'+ ( block === null ? ' tb-node--unknown' : '' );
			box.dataset.nodeId = node.id;

			// Each nesting level is tinted a little more strongly than its
			// parent, which is what separates the layers of a deep grid
			// visually. Driven by a custom property rather than a stack of
			// descendant selectors, so it works at any depth
			box.style.setProperty( '--tb-depth', Math.min( depth, MAX_TINT_DEPTH ) );

			if( node.id === Nino.templates.selectedId() )
				box.classList.add('tb-node--selected');

			// Selecting the innermost box the click landed in, not every
			// ancestor it bubbled through
			box.addEventListener( 'click', function( event ) {
				event.stopPropagation();
				Nino.templates.select( node.id );
			} );

			if( block !== null )
				Nino.templates.canvas._applyLayout( box, node, blockKey, block );

			box.appendChild( Nino.templates.canvas._header( node, blockKey, block ) );

			// A block with a 'text' setting owns its direct text - the header
			// already previews it, so drawing it again as a child box would
			// show the same string twice. Every other node keeps its text
			// children visible, since for those the text is content the
			// builder has no field for
			const ownsText 	= block !== null && Nino.templates.canvas._hasTextSetting( block );
			const children 	= ( node.children || [] ).filter( function( child ) {
				if( child.type !== 'text' )
					return true;
				return child.value.trim() !== '' && ownsText === false;
			} );

			if( children.length > 0 )
				box.appendChild( Nino.templates.canvas._list( children, depth + 1 ) );

			return box;
		},

		/**
		 *	Draw the two settings that are genuinely spatial - grid width and
		 *	vertical spacing - as real css on the box. Everything else stays
		 *	text in the header, see this file's docblock
		 *
		 *	@param		{Element}	box
		 *	@param		{Object}	node
		 *	@param		{string}	blockKey
		 *	@param		{Object}	block
		 *
		 *	@return		void
		 */
		_applyLayout : function( box, node, blockKey, block ) {

			const values = Nino.templates.blocks.read( node, blockKey );

			if( values.width !== undefined && GRID_WIDTHS[values.width] !== undefined ) {
				box.style.width = GRID_WIDTHS[values.width]+ '%';
				box.classList.add('tb-node--column');
			}

			if( values.mt )
				box.style.marginTop = SPACE[Number( values.mt )]+ 'rem';
			if( values.mb )
				box.style.marginBottom = SPACE[Number( values.mb )]+ 'rem';
			if( values.pt )
				box.style.paddingTop = SPACE[Number( values.pt )]+ 'rem';
			if( values.pb )
				box.style.paddingBottom = SPACE[Number( values.pb )]+ 'rem';

			// A grid row lays its columns out side by side, which is the one
			// case where the boxes have to behave like the markup does
			if( blockKey === 'grid-row' )
				box.classList.add('tb-node--row');
		},

		/**
		 *	One box's label row: block name (or the raw tag, unrecognized),
		 *	the html tag, a value preview, and the id warning where one
		 *	applies
		 *
		 *	@param		{Object}				node
		 *	@param		{string|null}		blockKey
		 *	@param		{Object|null}		block
		 *
		 *	@return		{Element}
		 */
		_header : function( node, blockKey, block ) {

			const header = dc.createElement('div');
			header.className = 'tb-node-header';

			const name = dc.createElement('span');
			name.className = 'tb-node-name';
			name.textContent = ( block !== null ) ? block.name : Nino.templates.canvas._unknownLabel( node );
			header.appendChild( name );

			const tag = dc.createElement('span');
			tag.className = 'tb-node-tag';
			tag.textContent = ( node.tag === 'nino-sc' ) ? '['+ ( node.attrs.name || '' )+ ']' : '<'+ node.tag+ '>';
			header.appendChild( tag );

			const preview = Nino.templates.canvas._preview( node, blockKey, block );
			if( preview !== '' ) {
				const span = dc.createElement('span');
				span.className = 'tb-node-preview';
				span.textContent = preview;
				header.appendChild( span );
			}

			const warning = Nino.templates.canvas._idWarning( node );
			if( warning !== null )
				header.appendChild( warning );

			return header;
		},

		/**
		 *	A short, readable summary of what this node currently holds -
		 *	its text, its link target, its shortcode arguments. Boxes with
		 *	nothing worth previewing get none rather than an empty one
		 *
		 *	@param		{Object}				node
		 *	@param		{string|null}		blockKey
		 *	@param		{Object|null}		block
		 *
		 *	@return		{string}
		 */
		_preview : function( node, blockKey, block ) {

			if( block === null )
				return '';

			const values = Nino.templates.blocks.read( node, blockKey );

			for( const key of [ 'text', 'args', 'src', 'href' ] )
				if( typeof values[key] === 'string' && values[key] !== '' )
					return Nino.templates.canvas._truncate( values[key] );

			return '';
		},

		/**
		 *	The warning the id scan feeds (see Templates.php's Stylesheets
		 *	class): this node carries an id the project's own css targets, so
		 *	a rule bound to that id can override whatever its classes say -
		 *	and the class list is exactly what this builder shows as the
		 *	node's properties. Worth flagging rather than silently drawing
		 *	something the browser won't reproduce
		 *
		 *	@param		{Object}	node
		 *
		 *	@return		{Element|null}
		 */
		_idWarning : function( node ) {

			const id = ( node.attrs || {} ).id || '';

			if( id === '' || Nino.templates.canvas._styledIds.indexOf( id ) === -1 )
				return null;

			const badge = dc.createElement('span');
			badge.className = 'tb-node-warning';
			badge.textContent = '#'+ id;
			badge.title = 'The project stylesheet has rules for #'+ id+ '. Those override the classes shown here, so the rendered page can differ from what this block\'s settings say.';

			return badge;
		},

		/**
		 *	'<div id="hero" class="my-thing">' - what an unrecognized node is
		 *	labelled with. Exactly the information a developer needs to find
		 *	the thing in the .tpl, and nothing that pretends the builder
		 *	understands it
		 *
		 *	@param		{Object}	node
		 *
		 *	@return		{string}
		 */
		_unknownLabel : function( node ) {

			let label = '<'+ node.tag;

			if( ( node.attrs || {} ).id )
				label += ' id="'+ node.attrs.id+ '"';

			if( ( node.classes || [] ).length > 0 )
				label += ' class="'+ node.classes.join(' ')+ '"';

			return label+ '>';
		},

		/**
		 *	@param		{Object}	block
		 *
		 *	@return		{boolean}						Whether any of the block's settings is the node's own text
		 */
		_hasTextSetting : function( block ) {

			const names = Object.keys( block.settings || {} );

			for( let i = 0; i < names.length; i++ )
				if( block.settings[names[i]].type === 'text' )
					return true;

			return false;
		},

		_leaf : function( className, label, value ) {

			const box = dc.createElement('div');
			box.className = 'tb-node tb-node-header '+ className;

			const tag = dc.createElement('span');
			tag.className = 'tb-node-tag';
			tag.textContent = label;
			box.appendChild( tag );

			const span = dc.createElement('span');
			span.className = 'tb-node-preview';
			span.textContent = Nino.templates.canvas._truncate( value );
			box.appendChild( span );

			return box;
		},

		_truncate : function( value ) {
			value = String( value ).replace( /\s+/g, ' ' ).trim();
			return ( value.length > 70 ) ? value.slice( 0, 70 )+ '…' : value;
		},
	};

})(window, document, document.documentElement, document.body);
