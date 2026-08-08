

/**
 *	Nino										A compact filesystembased php framework
 *	Templates								The block layer: which library block a parsed node *is*,
 *													and how that block's settings map onto the node's own
 *													css classes, attributes, tag and text.
 *
 *													This is the one place either direction of that mapping
 *													lives. The server never does it - Library only ships the
 *													declarations (see _templates/Templates.php's Library
 *													class), Parser/Serializer only ever see tags, attributes
 *													and an ordered class list. Keeping read and write in one
 *													file is what makes "open a template and save it again"
 *													a no-op instead of a slow reformat: nothing can read a
 *													class one way and write it back another.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.templates = wn.Nino.templates || {};

	Nino.templates.blocks = {

		_blocks : {},

		/**
		 *	@param		{Object}	blocks			key -> definition, from library/blocks
		 *
		 *	@return		void
		 */
		setAll : function( blocks ) {
			Nino.templates.blocks._blocks = blocks || {};
		},

		get : function( key ) {
			return Nino.templates.blocks._blocks[key] || null;
		},

		all : function() {
			return Nino.templates.blocks._blocks;
		},

		/**
		 *	Which library block a node is, or null for markup the library
		 *	doesn't describe. Every block whose 'match' the node satisfies
		 *	is scored by how *specific* that match was (required classes and
		 *	attributes each count), and the highest score wins - which is
		 *	what lets "Section Title" (h3.ui-section-title) and the generic
		 *	"Heading" (any h1-h6) both exist without the generic one ever
		 *	swallowing the specific one.
		 *
		 *	Recognition is deliberately class-based rather than driven by a
		 *	marker attribute the builder would have to write: it works on
		 *	templates that were hand-written long before this tool existed,
		 *	and the html it saves stays exactly as clean as a hand-written
		 *	one. See docs/_templates.md.
		 *
		 *	@param		{Object}	node			A parsed element node
		 *
		 *	@return		{string|null}				Library block key
		 */
		match : function( node ) {

			if( node === null || node.type !== 'element' )
				return null;

			const tag 		= ( node.tag || '' ).toLowerCase();
			const classes = node.classes || [];
			const attrs 	= node.attrs || {};

			let bestKey 	= null;
			let bestScore = -1;

			Object.keys( Nino.templates.blocks._blocks ).forEach( function( key ) {

				const m = Nino.templates.blocks._blocks[key].match;

				if( m.tags.length > 0 && m.tags.indexOf( tag ) === -1 )
					return;

				for( let i = 0; i < m.not.length; i++ )
					if( classes.indexOf( m.not[i] ) !== -1 )
						return;

				for( let i = 0; i < m.classes.length; i++ )
					if( classes.indexOf( m.classes[i] ) === -1 )
						return;

				if( m.classesAny.length > 0 ) {
					let any = false;
					for( let i = 0; i < m.classesAny.length; i++ )
						if( classes.indexOf( m.classesAny[i] ) !== -1 )
							any = true;
					if( any === false )
						return;
				}

				const attrNames = Object.keys( m.attrs );
				for( let i = 0; i < attrNames.length; i++ )
					if( ( attrs[attrNames[i]] || '' ) !== m.attrs[attrNames[i]] )
						return;

				// A required class is a stronger claim than "any of these",
				// which is stronger than matching on the tag alone
				const score = ( m.classes.length * 10 )+ ( attrNames.length * 10 )
										+ ( m.classesAny.length > 0 ? 5 : 0 )
										+ ( m.tags.length > 0 ? 1 : 0 );

				if( score > bestScore ) {
					bestScore = score;
					bestKey 	= key;
				}
			} );

			return bestKey;
		},

		/**
		 *	Read every setting of a block off a node - the "load" half of the
		 *	mapping. Returns a flat object of setting name -> current value;
		 *	a responsive classenum contributes one entry per breakpoint on
		 *	top of its base ("width", "width@m", ...)
		 *
		 *	@param		{Object}	node
		 *	@param		{string}	blockKey
		 *
		 *	@return		{Object}
		 */
		read : function( node, blockKey ) {

			const block = Nino.templates.blocks.get( blockKey );
			if( block === null )
				return {};

			const values 	= {};
			const classes = node.classes || [];

			Object.keys( block.settings ).forEach( function( name ) {

				const setting = block.settings[name];

				if( setting.type === 'classtoggle' ) {
					values[name] = classes.indexOf( setting.class ) !== -1;
					return;
				}

				if( setting.type === 'classgroup' ) {
					values[name] = '';
					Object.keys( setting.options ).forEach( function( cls ) {
						if( cls !== '' && classes.indexOf( cls ) !== -1 )
							values[name] = cls;
					} );
					return;
				}

				if( setting.type === 'classenum' ) {
					values[name] = Nino.templates.blocks._readEnum( classes, setting.pattern, setting.values );
					( setting.breakpoints || [] ).forEach( function( bp ) {
						values[name+ '@'+ bp] = Nino.templates.blocks._readEnum( classes, Nino.templates.blocks._bp( setting.bpPattern, bp ), setting.values );
					} );
					return;
				}

				if( setting.type === 'attr' ) {
					values[name] = ( node.attrs || {} )[setting.attr] ?? '';
					return;
				}

				if( setting.type === 'attrtoggle' ) {
					values[name] = Object.prototype.hasOwnProperty.call( node.attrs || {}, setting.attr );
					return;
				}

				if( setting.type === 'tag' ) {
					values[name] = ( node.tag || '' ).toLowerCase();
					return;
				}

				if( setting.type === 'text' )
					values[name] = Nino.templates.blocks.readText( node );
			} );

			return values;
		},

		/**
		 *	Write one setting back onto a node - the "save" half. Mutates
		 *	the node in place (its class list, attributes, tag or children)
		 *	and returns nothing: the node *is* the document model, there is
		 *	no second copy of the state to keep in step.
		 *
		 *	A class is replaced at the index it already sat at rather than
		 *	removed and appended, so changing one setting never reshuffles
		 *	the rest of an element's class attribute
		 *
		 *	@param		{Object}	node
		 *	@param		{string}	blockKey
		 *	@param		{string}	name				Setting name, optionally "name@breakpoint"
		 *	@param		{*}				value
		 *
		 *	@return		void
		 */
		write : function( node, blockKey, name, value ) {

			const block = Nino.templates.blocks.get( blockKey );
			if( block === null )
				return;

			const parts 	= name.split('@');
			const setting = block.settings[parts[0]];

			if( setting === undefined )
				return;

			if( setting.type === 'classtoggle' )
				return Nino.templates.blocks._setClass( node, setting.class, value === true );

			if( setting.type === 'classgroup' ) {
				const options = Object.keys( setting.options ).filter( function( cls ) { return cls !== '' } );
				Nino.templates.blocks._swapClass( node, options, ( value === undefined || value === null ) ? '' : String( value ) );
				return;
			}

			if( setting.type === 'classenum' ) {
				const pattern 		= ( parts[1] !== undefined ) ? Nino.templates.blocks._bp( setting.bpPattern, parts[1] ) : setting.pattern;
				const candidates 	= setting.values.map( function( v ) { return pattern.replace( '%s', v ) } );
				const active 			= ( value === '' || value === undefined || value === null ) ? '' : pattern.replace( '%s', String( value ) );
				Nino.templates.blocks._swapClass( node, candidates, active );
				return;
			}

			if( setting.type === 'attr' ) {
				node.attrs = node.attrs || {};
				if( value === '' || value === null || value === undefined )
					delete node.attrs[setting.attr];
				else
					node.attrs[setting.attr] = String( value );
				return;
			}

			if( setting.type === 'attrtoggle' ) {
				node.attrs = node.attrs || {};
				if( value === true )
					node.attrs[setting.attr] = '';
				else
					delete node.attrs[setting.attr];
				return;
			}

			if( setting.type === 'tag' ) {
				if( ( setting.values || [] ).indexOf( value ) !== -1 )
					node.tag = value;
				return;
			}

			if( setting.type === 'text' )
				Nino.templates.blocks.writeText( node, value );
		},

		/**
		 *	A node's own text, as authored. Only direct text children count -
		 *	a wrapper's nested markup is its children's business, not a
		 *	"text" setting's - and entity placeholders are turned back into
		 *	the entities they stand for so the field shows "&shy;" rather
		 *	than the private-use characters Parser::protectEntities() uses
		 *
		 *	@param		{Object}	node
		 *
		 *	@return		{string}
		 */
		readText : function( node ) {

			let text = '';

			( node.children || [] ).forEach( function( child ) {
				if( child.type === 'text' )
					text += child.value;
			} );

			return Nino.templates.blocks.showEntities( text );
		},

		/**
		 *	Replace a node's direct text with a new value, leaving any
		 *	element children alone
		 *
		 *	@param		{Object}	node
		 *	@param		{string}	value
		 *
		 *	@return		void
		 */
		writeText : function( node, value ) {

			const kept 	= ( node.children || [] ).filter( function( child ) { return child.type !== 'text' } );
			const text 	= { type : 'text', value : Nino.templates.blocks.hideEntities( String( value ) ) };

			node.children = [ text ].concat( kept );
		},

		// U+E000/U+E001 - see Parser::protectEntities() for why entities are
		// carried through the tree as placeholders rather than as themselves
		ENTITY_OPEN 	: '\uE000',
		ENTITY_CLOSE 	: '\uE001',

		showEntities : function( text ) {
			return text.replace( /\uE000([^\uE001]*)\uE001/g, '&$1;' );
		},

		hideEntities : function( text ) {
			return text.replace( /&(#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);/g, '\uE000$1\uE001' );
		},

		/**
		 *	@param		{Array}		classes
		 *	@param		{string}	pattern			eg. 'ui-grid-%s'
		 *	@param		{Array}		values
		 *
		 *	@return		{string}								'' when none of them is present
		 */
		_readEnum : function( classes, pattern, values ) {

			let found = '';

			( values || [] ).forEach( function( value ) {
				if( classes.indexOf( pattern.replace( '%s', value ) ) !== -1 )
					found = String( value );
			} );

			return found;
		},

		/**
		 *	@param		{string}	bpPattern		eg. 'ui-grid-%b-%s'
		 *	@param		{string}	bp					eg. 'm'
		 *
		 *	@return		{string}								eg. 'ui-grid-m-%s'
		 */
		_bp : function( bpPattern, bp ) {
			return ( bpPattern || '' ).replace( '%b', bp );
		},

		/**
		 *	Replace whichever of a set of mutually-exclusive classes a node
		 *	currently carries with one specific class - **at the index the
		 *	old one already sat at**, not by removing it and appending the
		 *	new one at the end.
		 *
		 *	That distinction is the whole point of this method. Changing a
		 *	grid column's width must turn 'ui-grid-100 ui-grid-m-50 ui-mb-3'
		 *	into 'ui-grid-50 ui-grid-m-50 ui-mb-3', not into
		 *	'ui-grid-m-50 ui-mb-3 ui-grid-50' - the latter is the same css
		 *	but a different line in the file, so every edited element would
		 *	come back with its class attribute reshuffled and the round-trip
		 *	guarantee would only hold for documents nobody touched.
		 *
		 *	A node carrying two of the candidates at once (hand-written, or
		 *	a stale class left behind) collapses onto the first position.
		 *
		 *	@param		{Object}	node
		 *	@param		{Array}		candidates		The mutually exclusive set
		 *	@param		{string}	active				The one to end up with, '' for none
		 *
		 *	@return		void
		 */
		_swapClass : function( node, candidates, active ) {

			node.classes = node.classes || [];

			const kept = [];
			let placed = false;

			node.classes.forEach( function( cls ) {

				if( candidates.indexOf( cls ) === -1 ) {
					kept.push( cls );
					return;
				}

				if( placed === false && active !== '' ) {
					kept.push( active );
					placed = true;
				}
			} );

			if( placed === false && active !== '' )
				kept.push( active );

			node.classes = kept;
		},

		/**
		 *	Add or remove one class, preserving the order of the rest
		 *
		 *	@param		{Object}	node
		 *	@param		{string}	cls
		 *	@param		{boolean}	on
		 *
		 *	@return		void
		 */
		_setClass : function( node, cls, on ) {

			node.classes = node.classes || [];

			const at = node.classes.indexOf( cls );

			if( on === true && at === -1 )
				node.classes.push( cls );
			else if( on === false && at !== -1 )
				node.classes.splice( at, 1 );
		},
	};

})(window, document, document.documentElement, document.body);
