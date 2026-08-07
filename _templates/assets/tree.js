

/**
 *	Nino										A compact filesystembased php framework
 *	Templates								Structural edits on a parsed node tree: insert, remove,
 *													move, duplicate.
 *
 *													Touches no dom and knows nothing about rendering - the
 *													tree is the same plain-array shape Parser produces and
 *													Serializer consumes (see _templates/Templates.php), and
 *													everything here is a pure transformation of it. That
 *													split is what makes this file testable without a
 *													browser; tests/templates-js-smoke.js exercises it
 *													directly.
 *
 *													**Whitespace is the whole difficulty.** The tree keeps
 *													the exact text between tags, because that is what makes
 *													an untouched template round-trip byte for byte. So an
 *													insert has to bring its own indentation along, and a
 *													remove has to take its own indentation with it - or
 *													every edit leaves the file a little more crooked than
 *													it found it. Rather than computing indentation from a
 *													depth counter, every operation *copies the whitespace
 *													its new neighbour already has*: whatever the file uses,
 *													tabs or spaces, however deeply the block sits, the
 *													inserted markup lines up with what is around it.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn) {

	wn.Nino = wn.Nino || {};
	wn.Nino.templates = wn.Nino.templates || {};

	// Every node created here gets an id of its own, so the canvas can key
	// its boxes and the inspector can hold a selection across a re-render.
	// Ids are per-session and never persisted - Parser assigns fresh ones
	// ('n1', 'n2', ...) on every load, and these ('c1', 'c2', ...) can never
	// collide with those
	let counter = 0;

	Nino.templates.tree = {

		/**
		 *	One indent level. Only ever used when there is no existing
		 *	neighbour to copy indentation from - an empty parent getting its
		 *	first child. Everywhere else the file's own whitespace wins,
		 *	whatever it happens to be
		 *
		 *	@return		{string}
		 */
		INDENT : '\t',

		/**
		 *	Locate a node by id, with the chain of lists that leads to it.
		 *
		 *	@param		{Array}		nodes			The document's node list
		 *	@param		{string}	id
		 *
		 *	@return		{Array|null}				[ { list, index }, ... ] root-first, or null
		 */
		path : function( nodes, id ) {

			for( let i = 0; i < nodes.length; i++ ) {

				const node = nodes[i];

				if( node.id === id )
					return [ { list : nodes, index : i } ];

				if( node.type !== 'element' )
					continue;

				const deeper = Nino.templates.tree.path( node.children || [], id );

				if( deeper !== null )
					return [ { list : nodes, index : i } ].concat( deeper );
			}

			return null;
		},

		/**
		 *	@param		{Array}		nodes
		 *	@param		{string}	id
		 *
		 *	@return		{Object|null}
		 */
		find : function( nodes, id ) {

			const path = Nino.templates.tree.path( nodes, id );

			if( path === null )
				return null;

			const last = path[path.length - 1];

			return last.list[last.index];
		},

		/**
		 *	Insert nodes relative to an anchor.
		 *
		 *	@param		{Array}		nodes			The document's node list
		 *	@param		{string}	anchorId	Node to insert relative to - null appends at the root
		 *	@param		{string}	mode			'child' (append inside) | 'after' (next sibling)
		 *	@param		{Array}		fresh			Nodes to insert, already id-assigned
		 *
		 *	@return		{boolean}						Whether the insert happened
		 */
		insert : function( nodes, anchorId, mode, fresh ) {

			if( fresh.length === 0 )
				return false;

			if( anchorId === null )
				return Nino.templates.tree._appendTo( nodes, fresh, Nino.templates.tree._listIndent( nodes, '' ) );

			const path = Nino.templates.tree.path( nodes, anchorId );

			if( path === null )
				return false;

			const last 		= path[path.length - 1];
			const anchor 	= last.list[last.index];

			// The indentation the anchor itself sits at - the whitespace text
			// node right before it, which is exactly what a new sibling needs
			const ownIndent = Nino.templates.tree._indentBefore( last.list, last.index );

			if( mode === 'after' ) {
				last.list.splice( last.index + 1, 0, Nino.templates.tree._text( ownIndent ), ...fresh );
				return true;
			}

			if( anchor.type !== 'element' )
				return false;

			anchor.children = anchor.children || [];

			// A child sits one level deeper than its parent. Where the parent
			// already has children, copy theirs - their indentation is the
			// truth about this file, whatever convention it follows.
			//
			// An *empty* parent's whitespace must not be copied, though: the
			// only text node in <div>\n\t\t</div> is the closing tag's own
			// indent, which sits at the parent's level, not a child's. Using
			// it would file every first child one level too shallow, so that
			// case derives from the parent's indent instead
			const hasChildren = Nino.templates.tree._hasElement( anchor.children );

			const childIndent = ( hasChildren === true )
				? Nino.templates.tree._listIndent( anchor.children, ownIndent+ Nino.templates.tree.INDENT )
				: ownIndent+ Nino.templates.tree.INDENT;

			return Nino.templates.tree._appendTo( anchor.children, fresh, childIndent, ownIndent );
		},

		/**
		 *	Remove a node, and the indentation that belonged to it
		 *
		 *	@param		{Array}		nodes
		 *	@param		{string}	id
		 *
		 *	@return		{boolean}
		 */
		remove : function( nodes, id ) {

			const path = Nino.templates.tree.path( nodes, id );

			if( path === null )
				return false;

			const last = path[path.length - 1];

			last.list.splice( last.index, 1 );

			// The whitespace that indented it is now a blank line, so it goes
			// with the node
			const before = last.index - 1;

			if( before >= 0 && Nino.templates.tree._isSpace( last.list[before] ) === true && Nino.templates.tree._hasElement( last.list ) === true )
				last.list.splice( before, 1 );

			// Removing the last element leaves nothing but the indentation
			// its siblings used plus the closing tag's own - two whitespace
			// nodes in a row, which serialize to a stray blank line inside an
			// otherwise empty element. Only the last one means anything now
			if( Nino.templates.tree._hasElement( last.list ) === false && last.list.length > 1 )
				last.list.splice( 0, last.list.length - 1 );

			return true;
		},

		/**
		 *	Swap a node with its previous/next element sibling. Whitespace
		 *	nodes stay where they are - they carry indentation, which does
		 *	not move with the element
		 *
		 *	@param		{Array}		nodes
		 *	@param		{string}	id
		 *	@param		{number}	direction		-1 up, +1 down
		 *
		 *	@return		{boolean}
		 */
		move : function( nodes, id, direction ) {

			const path = Nino.templates.tree.path( nodes, id );

			if( path === null )
				return false;

			const last 		= path[path.length - 1];
			const list 		= last.list;
			const slots 	= Nino.templates.tree._elementSlots( list );
			const at 			= slots.indexOf( last.index );
			const swapWith = at + direction;

			if( at === -1 || swapWith < 0 || swapWith >= slots.length )
				return false;

			const a = slots[at];
			const b = slots[swapWith];

			const carry = list[a];
			list[a] = list[b];
			list[b] = carry;

			return true;
		},

		/**
		 *	Insert a deep copy of a node right after it
		 *
		 *	@param		{Array}		nodes
		 *	@param		{string}	id
		 *
		 *	@return		{Object|null}				The copy, or null
		 */
		duplicate : function( nodes, id ) {

			const original = Nino.templates.tree.find( nodes, id );

			if( original === null || original.type !== 'element' )
				return null;

			const copy = Nino.templates.tree.assignIds( JSON.parse( JSON.stringify( original ) ) );

			return Nino.templates.tree.insert( nodes, id, 'after', [ copy ] ) === true ? copy : null;
		},

		/**
		 *	Give a node and everything under it fresh ids
		 *
		 *	@param		{Object}	node
		 *
		 *	@return		{Object}						The same node, for chaining
		 */
		assignIds : function( node ) {

			if( node.type === 'element' ) {
				node.id = 'c'+ ( ++counter );
				( node.children || [] ).forEach( Nino.templates.tree.assignIds );
			}

			return node;
		},

		/**
		 *	Append into a list, indenting the new nodes to match whatever is
		 *	already in it. An empty list additionally gets a closing
		 *	whitespace node, so the parent's closing tag keeps its own line
		 *	instead of ending up jammed against the new child
		 *
		 *	@param		{Array}		list
		 *	@param		{Array}		fresh
		 *	@param		{string}	indent				Indentation for the new nodes
		 *	@param		{string}	[closeIndent]		Indentation for the parent's closing tag
		 *
		 *	@return		{boolean}
		 */
		_appendTo : function( list, fresh, indent, closeIndent ) {

			const wasEmpty = Nino.templates.tree._hasElement( list ) === false;

			// A list that only holds whitespace (an empty <div> written over
			// two lines) is rebuilt rather than appended to - its single
			// whitespace node is the closing tag's indentation, and the new
			// child has to go in front of it
			if( wasEmpty === true ) {
				list.length = 0;
				list.push( Nino.templates.tree._text( indent ), ...fresh );
				if( closeIndent !== undefined )
					list.push( Nino.templates.tree._text( closeIndent ) );
				return true;
			}

			// Append before the trailing whitespace, so the closing tag is
			// not left indented as if it were a child
			const tail = ( Nino.templates.tree._isSpace( list[list.length - 1] ) === true ) ? list.length - 1 : list.length;

			list.splice( tail, 0, Nino.templates.tree._text( indent ), ...fresh );

			return true;
		},

		/**
		 *	The whitespace a list's own entries are indented with - read off
		 *	the first whitespace node in it, so it matches the file rather
		 *	than a convention
		 *
		 *	@param		{Array}		list
		 *	@param		{string}	fallback
		 *
		 *	@return		{string}
		 */
		_listIndent : function( list, fallback ) {

			for( let i = 0; i < list.length; i++ )
				if( Nino.templates.tree._isSpace( list[i] ) === true && list[i].value.indexOf( '\n' ) !== -1 )
					return list[i].value;

			return ( fallback !== '' ) ? '\n'+ fallback : '\n';
		},

		/**
		 *	The whitespace node immediately before an index, as a string
		 *
		 *	@param		{Array}		list
		 *	@param		{number}	index
		 *
		 *	@return		{string}
		 */
		_indentBefore : function( list, index ) {

			if( index > 0 && Nino.templates.tree._isSpace( list[index - 1] ) === true )
				return list[index - 1].value;

			return Nino.templates.tree._listIndent( list, '' );
		},

		/**
		 *	Positions of the element nodes in a list - what move() swaps
		 *	between, skipping the whitespace and comments in between
		 *
		 *	@param		{Array}		list
		 *
		 *	@return		{Array}
		 */
		_elementSlots : function( list ) {

			const slots = [];

			for( let i = 0; i < list.length; i++ )
				if( list[i].type === 'element' )
					slots.push( i );

			return slots;
		},

		_hasElement : function( list ) {

			for( let i = 0; i < list.length; i++ )
				if( list[i].type === 'element' || list[i].type === 'comment' )
					return true;

			return false;
		},

		_isSpace : function( node ) {
			return node !== undefined && node.type === 'text' && node.value.trim() === '';
		},

		_text : function( value ) {
			return { type : 'text', value : value };
		},
	};

})( typeof window !== 'undefined' ? window : globalThis );
