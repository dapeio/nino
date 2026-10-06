

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	html-editor.js					Minimal contenteditable rich-text editor for admin "html"
 *													fields: strong/em/span/code/a, always flat - applying a
 *													format to a selection replaces any format already on it,
 *													never nests - and, where the field's format allows them,
 *													line breaks (<br>) and paragraphs and lists (p/ul/ol/li,
 *													see PROFILES). No execCommand; paste is always plain text.
 *													Shared by Text, Text/Keys and Elements in /_admin for
 *													any field whose model/entry has html === true.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	const TAGS = [ 'strong', 'em', 'span', 'code', 'a' ];

	/**
	 *	What each format lets the editor hold, beside the five tags: the same
	 *	three \Nino\Html::sanitizeHtml() knows. 'inline' is one line of text,
	 *	'lines' adds line breaks, 'blocks' paragraphs and lists as well. A format
	 *	this table does not know is read as 'inline' - and 'plain' never gets
	 *	here, a plain field is a textarea
	 */
	const PROFILES = {
		inline : { breaks : false, blocks : false },
		lines : { breaks : true, blocks : false },
		blocks : { breaks : true, blocks : true },
	};

	// The tags that end a run of text and start the next - where load() leaves
	// a space or a break behind them, like the server does
	const BOUNDARIES = [ 'p', 'div', 'li', 'ul', 'ol', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'tr', 'section', 'article' ];

	// What the 'blocks' format turns into a paragraph of its own
	const PARAGRAPHS = [ 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote' ];

	/**
	 *	An href this editor keeps - the same rule \Nino\Html::_safeHref()
	 *	applies on the way in: a fragment, a path that is not protocol-relative,
	 *	or one of the four schemes. Everything else, 'javascript:' first among
	 *	them, is dropped
	 *
	 *	@param		{string}		href
	 *
	 *	@return		{string|null}
	 */
	function safeHref( href ) {

		href = String( href || '' ).trim();

		if( href === '' )
			return null;

		if( href[0] === '#' )
			return href;

		if( href[0] === '/' && href[1] !== '/' && href[1] !== '\\' )
			return href;

		return /^(https?|mailto|tel):/i.test( href ) ? href : null;
	}

	/**
	 *	The tag a keyboard shortcut stands for, or null when the key is not one
	 *	of ours. Ctrl or Cmd with B and I are bold and italic, and U is
	 *	answered with '' - the browser's own underline would put a <u> in the
	 *	field that nothing keeps, so the key is taken and does nothing. Any
	 *	combination with Alt or Shift is somebody else's shortcut (AltGr
	 *	reports Ctrl and Alt together) and is left alone
	 *
	 *	@param		{KeyboardEvent}	ev
	 *
	 *	@return		{string|null}									'strong', 'em', '' for a key that is swallowed, null for any other
	 */
	function shortcutTag( ev ) {

		if( ( ev.ctrlKey !== true && ev.metaKey !== true ) || ev.altKey === true || ev.shiftKey === true )
			return null;

		const key = String( ev.key || '' ).toLowerCase();

		if( key === 'b' )
			return 'strong';

		if( key === 'i' )
			return 'em';

		return key === 'u' ? '' : null;
	}

	/**
	 *	Whether a text ends in white space - or, for a node that has no text of
	 *	its own, its last text does
	 *
	 *	@param		{Node}			node
	 *
	 *	@return		{boolean}
	 */
	function endsWithSpace( node ) {
		return /\s$/.test( node.textContent );
	}

	/**
	 *	What keeps two runs of text apart where a block between them was
	 *	unwrapped - the rule \Nino\Html::_boundary() applies: nothing where
	 *	either side already has a space or a break, otherwise a <br> in a
	 *	format that keeps breaks and a space in one that does not
	 *
	 *	@param		{Element}		to						What the text is being added to
	 *	@param		{string}		next					The text that comes next
	 *	@param		{Object}		profile
	 *
	 *	@return		void
	 */
	function separate( to, next, profile ) {

		const last = to.lastChild;

		if( last === null || /^\s/.test( next ) === true || ( last.nodeType === 1 && last.tagName === 'BR' ) || endsWithSpace( last ) === true )
			return;

		to.appendChild( profile.breaks === true ? dc.createElement('br') : dc.createTextNode(' ') );
	}

	/**
	 *	Add a run of text to an element. In a format that keeps line breaks a
	 *	newline of the text is one (the form of a plain value that was widened),
	 *	and a newline straight after a <br> is the source's own formatting
	 *
	 *	@param		{Element}		to
	 *	@param		{string}		text
	 *	@param		{Object}		profile
	 *	@param		{boolean}		afterBreak		Whether a <br> was the node before this text
	 *
	 *	@return		void
	 */
	function appendText( to, text, profile, afterBreak ) {

		if( profile.breaks !== true ) {
			to.appendChild( dc.createTextNode( text ) );
			return;
		}

		if( afterBreak === true )
			text = text.replace( /^[ \t]*\r?\n/, '' );

		text.split( /\r\n|\r|\n/ ).forEach( function( part, index ) {
			if( index > 0 )
				to.appendChild( dc.createElement('br') );
			if( part !== '' )
				to.appendChild( dc.createTextNode( part ) );
		} );
	}

	/**
	 *	Rebuild a run of nodes into an element as the five tags (and a <br>
	 *	where the profile keeps one), node by node: the walk \Nino\Html::
	 *	_sanitizeChildren() makes, which this mirrors, never assigning markup
	 *
	 *	@param		{Array}			nodes					The nodes of a parsed value
	 *	@param		{Element}		to
	 *	@param		{Object}		profile
	 *	@param		{Object}		state					{ ended } - a block just ended, so the next text is kept apart
	 *	@param		{boolean}		inside				Whether one of the five tags is already open
	 *
	 *	@return		void
	 */
	function walk( nodes, to, profile, state, inside ) {

		nodes.forEach( function( node, index ) {

			if( node.nodeType === 3 ) {

				if( node.nodeValue === '' )
					return;

				if( state.ended === true )
					separate( to, node.nodeValue, profile );

				state.ended = false;

				const before = nodes[index - 1];
				appendText( to, node.nodeValue, profile, before !== undefined && before.nodeType === 1 && before.tagName.toLowerCase() === 'br' );
				return;
			}

			if( node.nodeType !== 1 )
				return;

			const tag = node.tagName.toLowerCase();

			if( tag === 'br' && profile.breaks === true ) {
				to.appendChild( dc.createElement('br') );
				state.ended = false;
				return;
			}

			if( BOUNDARIES.indexOf( tag ) !== -1 ) {
				walk( Array.from( node.childNodes ), to, profile, { ended : to.lastChild !== null }, inside );
				state.ended = to.firstChild !== null;
				return;
			}

			// A tag this editor does not know keeps its text and loses itself -
			// the same thing the server's sanitizer does with one
			if( TAGS.indexOf( tag ) === -1 || inside === true ) {
				walk( Array.from( node.childNodes ), to, profile, state, inside );
				return;
			}

			const el = dc.createElement( tag );

			if( tag === 'a' ) {
				const href = safeHref( node.getAttribute('href') );
				if( href !== null )
					el.setAttribute( 'href', href );
			}

			walk( Array.from( node.childNodes ), el, profile, { ended : false }, true );

			if( el.firstChild === null )
				return;

			if( state.ended === true )
				separate( to, el.textContent, profile );

			state.ended = false;
			to.appendChild( el );
		} );
	}

	/**
	 *	Take the <br> and the white space off both ends of an element - what a
	 *	paragraph, an item or a value of line breaks does not keep, and what a
	 *	caret's placeholder is made of
	 *
	 *	@param		{Element}		el
	 *
	 *	@return		void
	 */
	function trimBreaks( el ) {

		[ [ 'firstChild', /^\s+/ ], [ 'lastChild', /\s+$/ ] ].forEach( function( end ) {

			for( let node = el[end[0]]; node !== null; node = el[end[0]] ) {

				if( node.nodeType === 1 && node.tagName === 'BR' ) {
					node.remove();
					continue;
				}

				if( node.nodeType !== 3 )
					return;

				node.nodeValue = node.nodeValue.replace( end[1], '' );

				if( node.nodeValue !== '' )
					return;

				node.remove();
			}
		} );
	}

	/**
	 *	A value for the 'blocks' format, rebuilt the way \Nino\Html::
	 *	_sanitizeBlocks() reads one: paragraphs and lists at the top, items in
	 *	lists only (one outside a list is a paragraph), loose text and tags
	 *	gathered into paragraphs (a blank line in the text starts one, a newline
	 *	is a <br>), and nothing empty
	 *
	 *	@param		{Element}		into
	 *	@param		{Array}			nodes
	 *
	 *	@return		void
	 */
	function loadBlocks( into, nodes ) {

		const profile = PROFILES.blocks;
		let loose = null;
		// One run of loose content is one walk: what a block in it unwrapped keeps
		// its neighbours apart whichever node they are
		let state = { ended : false };

		function flush() {
			state = { ended : false };
			if( loose === null )
				return;
			trimBreaks( loose );
			if( loose.firstChild !== null )
				into.appendChild( loose );
			loose = null;
		}

		function holder( tag, from ) {
			const el = dc.createElement( tag );
			walk( from, el, profile, { ended : false }, false );
			trimBreaks( el );
			return el;
		}

		nodes.forEach( function( node ) {

			const tag = node.nodeType === 1 ? node.tagName.toLowerCase() : '';

			// An item outside a list is a paragraph, like \Nino\Html does it
			if( PARAGRAPHS.indexOf( tag ) !== -1 || tag === 'li' ) {
				flush();
				const p = holder( 'p', Array.from( node.childNodes ) );
				if( p.firstChild !== null )
					into.appendChild( p );
				return;
			}

			if( tag === 'ul' || tag === 'ol' ) {
				flush();
				const list = dc.createElement( tag );
				Array.from( node.childNodes ).forEach( function( item ) {
					// Whatever else is in a list is an item of its own, so no
					// word of it is lost
					const li = holder( 'li', ( item.nodeType === 1 && item.tagName.toLowerCase() === 'li' ) ? Array.from( item.childNodes ) : [ item ] );
					if( li.firstChild !== null )
						list.appendChild( li );
				} );
				if( list.firstChild !== null )
					into.appendChild( list );
				return;
			}

			if( node.nodeType === 3 ) {

				// A blank line in loose text starts the next paragraph
				node.nodeValue.split( /\r?\n[ \t]*\r?\n/ ).forEach( function( part, index ) {

					if( index > 0 )
						flush();

					if( part === '' )
						return;

					loose = loose ?? dc.createElement('p');

					if( state.ended === true )
						separate( loose, part, profile );

					state.ended = false;
					appendText( loose, part, profile, false );
				} );
				return;
			}

			loose = loose ?? dc.createElement('p');
			walk( [ node ], loose, profile, state, false );
		} );

		flush();
	}

	/**
	 *	A stored value, rebuilt as the five tags this editor knows - and the
	 *	line breaks, paragraphs and lists its format allows - and nothing else.
	 *
	 *	The value arrives from an element or a text file, which AGENTS.md lists
	 *	as untrusted: the save path sanitises, but a record written by hand, by
	 *	an import or by a module that writes elements without the panel does not
	 *	go through it - and assigning such a string to innerHTML ran whatever it
	 *	carried, with the editor's own session. Parsed inertly (no scripts run,
	 *	no images load) and rebuilt node by node instead
	 *
	 *	@param		{Element}		into					The contenteditable, emptied first
	 *	@param		{string}		value					The stored html
	 *	@param		{Object}		[profile]			One of PROFILES, 'inline' where left out
	 *
	 *	@return		void
	 */
	function load( into, value, profile ) {

		profile = profile ?? PROFILES.inline;

		into.textContent = '';

		const parsed = new DOMParser().parseFromString( '<body>'+ String( value || '' ), 'text/html' );
		const nodes = Array.from( parsed.body.childNodes );

		if( profile.blocks === true ) {
			loadBlocks( into, nodes );
			// The caret needs a paragraph to be in
			if( into.firstChild === null ) {
				const p = dc.createElement('p');
				p.appendChild( dc.createElement('br') );
				into.appendChild( p );
			}
			return;
		}

		walk( nodes, into, profile, { ended : false }, false );

		if( profile.breaks === true )
			trimBreaks( into );
	}

	Nino.admin.htmlEditor = {

		/**
		 *	Build the editor (toolbar + contenteditable + char counter) into container
		 *
		 *	@param		{Element}		container			Element to render the editor into (cleared first)
		 *	@param		{string}		value					Initial html value
		 *	@param		{number}		maxlength			Max visible (textContent) character count
		 *	@param		{number}		rows					Minimum height in lines of text, 0 for the stylesheet's default
		 *	@param		{string}		[format]			'inline' (the five tags), 'lines' (and line breaks) or 'blocks' (and paragraphs and lists) - see PROFILES; 'inline' where left out
		 *
		 *	@return		{Object}									{ getValue(), setValue( html ), focus(), mark( state ), destroy() }
		 */
		create : function( container, value, maxlength, rows, format ) {

			const profile = PROFILES[format] ?? PROFILES.inline;

			container.innerHTML = '';
			container.classList.add('nino-admin-richtext');

			const toolbar = dc.createElement('div');
			toolbar.className = 'nino-admin-richtext-toolbar';
			toolbar.setAttribute( 'role', 'toolbar' );
			toolbar.setAttribute( 'aria-label', Nino.content.getText('/_admin/htmleditor/label/formatting') );

			const content = dc.createElement('div');
			content.className = 'nino-admin-richtext-content';
			content.contentEditable = 'true';
			content.setAttribute( 'role', 'textbox' );
			content.setAttribute( 'aria-multiline', profile.breaks === true ? 'true' : 'false' );
			content.setAttribute( 'aria-label', Nino.content.getText('/_admin/htmleditor/label/content') );
			content.setAttribute( 'tabindex', '0' );
			content.spellcheck = true;
			// A field whose model asks for a height (an element type's
			// inputsize) hands the number over; the stylesheet turns it into
			// a height, so the line metrics and the padding stay its business
			if( ( rows ?? 0 ) > 0 )
				content.style.setProperty( '--nino-admin-richtext-rows', String( rows ) );
			load( content, value, profile );

			const linkbar = dc.createElement('div');
			linkbar.className = 'nino-admin-richtext-linkbar';
			linkbar.hidden = true;

			const linkInput = dc.createElement('input');
			// type="text", not "url": relative paths like "/contact" are valid hrefs here
			// (see _safeHref() server-side) but fail native url validation, which would
			// silently block the surrounding form's submit once this field is hidden
			linkInput.type = 'text';
			linkInput.placeholder = Nino.content.getText('/_admin/htmleditor/label/linkplaceholder');
			linkInput.setAttribute( 'aria-label', Nino.content.getText('/_admin/htmleditor/label/linkplaceholder') );

			const linkOk = dc.createElement('button');
			linkOk.type = 'button';
			linkOk.textContent = Nino.content.getText('/_admin/htmleditor/label/linkok');

			const linkCancel = dc.createElement('button');
			linkCancel.type = 'button';
			linkCancel.textContent = Nino.content.getText('/_admin/htmleditor/label/linkcancel');

			linkbar.appendChild( linkInput );
			linkbar.appendChild( linkOk );
			linkbar.appendChild( linkCancel );

			const counter = dc.createElement('div');
			counter.className = 'nino-admin-char-counter';
			counter.setAttribute( 'aria-live', 'polite' );

			let savedRange = null;

			/**
			 *	Return the current selection's range, if it's inside `content`
			 *
			 *	@return		{Range|null}
			 */
			function currentRange() {
				const sel = wn.getSelection();
				if( sel.rangeCount === 0 )
					return null;
				const range = sel.getRangeAt(0);
				return content.contains( range.commonAncestorContainer ) ? range : null;
			}

			/**
			 *	Put the selection on a range
			 *
			 *	@param		{Range}		range
			 *
			 *	@return		void
			 */
			function select( range ) {
				const sel = wn.getSelection();
				sel.removeAllRanges();
				sel.addRange( range );
			}

			/**
			 *	Put the caret at the start of an element - in its first text, so
			 *	what is typed lands inside a tag that opens it
			 *
			 *	@param		{Element}		el
			 *
			 *	@return		void
			 */
			function caretToStart( el ) {

				const range = dc.createRange();
				const first = dc.createTreeWalker( el, NodeFilter.SHOW_TEXT ).nextNode();

				if( first !== null && ( el.firstChild === null || el.firstChild.tagName !== 'BR' ) )
					range.setStart( first, 0 );
				else
					range.setStart( el, 0 );

				range.collapse( true );
				select( range );
			}

			/**
			 *	Find the single allowed tag fully wrapping a range, if any
			 *
			 *	@param		{Range}		range
			 *	@param		{string}	tag
			 *
			 *	@return		{Element|null}
			 */
			function findWrappingTag( range, tag ) {
				let node = range.commonAncestorContainer;
				if( node.nodeType === 3 )
					node = node.parentElement;
				while( node !== null && node !== content ) {
					if( ( node.tagName ?? '' ).toLowerCase() === tag )
						return node;
					node = node.parentElement;
				}
				return null;
			}

			/**
			 *	Replace a wrapping tag with its own text (toggle-off)
			 *
			 *	@param		{Element}	el
			 *
			 *	@return		void
			 */
			function unwrap( el ) {
				const text = dc.createTextNode( el.textContent );
				el.replaceWith( text );
				const range = dc.createRange();
				range.selectNode( text );
				select( range );
			}

			/**
			 *	Flatten a range's content to plain text and wrap it in a fresh tag -
			 *	this is what enforces "no nesting, only one level": any formatting
			 *	already inside the selection is discarded, not preserved
			 *
			 *	@param		{Range}		range
			 *	@param		{string}	tag
			 *	@param		{string|null}	href	Only used for tag === 'a'
			 *
			 *	@return		{Element|null}					The new element
			 */
			function wrapRun( range, tag, href ) {

				const text = range.toString();
				if( text === '' )
					return null;

				range.deleteContents();

				const el = dc.createElement( tag );
				el.textContent = text;
				if( tag === 'a' )
					el.href = href;

				range.insertNode( el );

				return el;
			}

			/**
			 *	The paragraph or item a node is in, null where there is none (the
			 *	field itself, in a format without blocks)
			 *
			 *	@param		{Node}		node
			 *
			 *	@return		{Element|null}
			 */
			function blockOf( node ) {
				for( let el = node.nodeType === 1 ? node : node.parentElement; el !== null && el !== content; el = el.parentElement )
					if( el.tagName === 'P' || el.tagName === 'LI' )
						return el;
				return null;
			}

			/**
			 *	Format a selection: each run of it - the text between two line
			 *	breaks or block boundaries - is wrapped on its own, so a selection
			 *	that crosses one keeps its lines and its paragraphs instead of
			 *	being flattened into a single tag that swallows them
			 *
			 *	@param		{Range}		range
			 *	@param		{string}	tag
			 *	@param		{string|null}	href	Only used for tag === 'a'
			 *
			 *	@return		void
			 */
			function wrapSelection( range, tag, href ) {

				if( profile.breaks === false ) {
					const el = wrapRun( range, tag, href );
					if( el !== null ) {
						const newRange = dc.createRange();
						newRange.selectNodeContents( el );
						select( newRange );
					}
					return;
				}

				// Collected first and cut afterwards: cutting changes the nodes the walk is on
				const runs = [];
				let run = null;
				let lastBlock = null;

				const walker = dc.createTreeWalker( range.commonAncestorContainer.nodeType === 1 ? range.commonAncestorContainer : range.commonAncestorContainer.parentNode, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT );

				for( let node = walker.currentNode; node !== null; node = walker.nextNode() ) {

					if( range.intersectsNode( node ) === false )
						continue;

					if( node.nodeType === 1 ) {
						if( node.tagName === 'BR' )
							run = null;
						continue;
					}

					const block = blockOf( node );

					if( run === null || block !== lastBlock ) {
						run = { first : node, last : node };
						runs.push( run );
					}

					run.last = node;
					lastBlock = block;
				}

				const made = [];

				runs.reverse().forEach( function( part ) {

					const sub = dc.createRange();
					sub.setStart( part.first, part.first === range.startContainer ? range.startOffset : 0 );
					sub.setEnd( part.last, part.last === range.endContainer ? range.endOffset : part.last.length );

					const el = wrapRun( sub, tag, href );
					if( el !== null )
						made.unshift( el );
				} );

				if( made.length === 0 )
					return;

				const newRange = dc.createRange();
				newRange.setStart( made[0], 0 );
				newRange.setEnd( made[made.length - 1], made[made.length - 1].childNodes.length );
				select( newRange );
			}

			function updateCounter() {
				const len = content.textContent.length;
				counter.textContent = len + ' / ' + maxlength;
				counter.classList.toggle( 'is-limit', len >= maxlength );
			}

			/**
			 *	Trim visible text back down to maxlength after an edit, keeping
			 *	whatever formatting survives up to the cut point
			 *
			 *	@return		void
			 */
			function enforceMaxLength() {

				if( content.textContent.length <= maxlength )
					return;

				const walker = dc.createTreeWalker( content, NodeFilter.SHOW_TEXT );
				let remaining = maxlength, node, cutNode = null, cutOffset = 0;

				while( ( node = walker.nextNode() ) !== null ) {
					if( remaining <= node.data.length ) { cutNode = node; cutOffset = remaining; break; }
					remaining -= node.data.length;
				}

				if( cutNode === null || content.lastChild === null )
					return;

				const range = dc.createRange();
				range.setStart( cutNode, cutOffset );
				range.setEndAfter( content.lastChild );
				range.deleteContents();

				const endRange = dc.createRange();
				endRange.selectNodeContents( content );
				endRange.collapse( false );
				select( endRange );
			}

			/**
			 *	Range.deleteContents()/extractContents() can leave an emptied but
			 *	un-removed ancestor tag behind when a range boundary starts/ends
			 *	inside it (eg. selecting "all" from inside a <span> onto plain
			 *	text after it) - clean those up so formatting a selection never
			 *	leaves stray empty tags in the value. Inline tags only: an empty
			 *	paragraph or item is the caret's place, and stays
			 *
			 *	@return		void
			 */
			function removeEmptyTags() {
				content.querySelectorAll( TAGS.join(',') ).forEach( function( el ) {
					if( el.textContent === '' )
						el.remove();
				} );
			}

			/**
			 *	Take out what the browser's own editing leaves behind that this
			 *	editor never makes: a block merge in Chrome carries the text over
			 *	in a <span style="…">, which the server would keep as the
			 *	highlight tag, and Enter outside the script's reach makes <div>.
			 *	Styles go, the tags that are not ours are unwrapped, and in the
			 *	'blocks' format loose text and tags are gathered into a paragraph
			 *	again, so the caret always has one to be in
			 *
			 *	@return		void
			 */
			function tidy() {

				const range = currentRange();
				const held = range === null ? null : [ range.startContainer, range.startOffset, range.endContainer, range.endOffset ];

				// The highlight this editor makes has no attribute; one with a style
				// is the browser's
				content.querySelectorAll('span[style], span[class]').forEach( function( el ) {
					el.replaceWith( ...el.childNodes );
				} );

				content.querySelectorAll('[style], [class]').forEach( function( el ) {
					el.removeAttribute('style');
					el.removeAttribute('class');
				} );

				content.querySelectorAll('font, b, i, u, s, strike').forEach( function( el ) {
					el.replaceWith( ...el.childNodes );
				} );

				if( profile.blocks === true ) {

					content.querySelectorAll('div, h1, h2, h3, h4, h5, h6, blockquote').forEach( function( el ) {
						const p = dc.createElement('p');
						p.append( ...el.childNodes );
						el.replaceWith( p );
					} );

					let loose = null;

					Array.from( content.childNodes ).forEach( function( node ) {

						if( node.nodeType === 1 && ( node.tagName === 'P' || node.tagName === 'UL' || node.tagName === 'OL' ) ) {
							loose = null;
							return;
						}

						if( loose === null ) {
							loose = dc.createElement('p');
							node.before( loose );
						}

						loose.appendChild( node );
					} );

					if( content.firstChild === null ) {
						const p = dc.createElement('p');
						p.appendChild( dc.createElement('br') );
						content.appendChild( p );
						caretToStart( p );
						return;
					}
				}

				if( held !== null && content.contains( held[0] ) === true && content.contains( held[2] ) === true ) {
					const back = dc.createRange();
					back.setStart( held[0], Math.min( held[1], held[0].nodeType === 3 ? held[0].length : held[0].childNodes.length ) );
					back.setEnd( held[2], Math.min( held[3], held[2].nodeType === 3 ? held[2].length : held[2].childNodes.length ) );
					select( back );
				}
			}

			function onChange() {
				tidy();
				removeEmptyTags();
				enforceMaxLength();
				updateCounter();
			}

			function applyFormat( tag ) {

				const range = currentRange();
				if( range === null || range.collapsed === true )
					return;

				const existing = findWrappingTag( range, tag );
				if( existing !== null ) {
					unwrap( existing );
					onChange();
					return;
				}

				if( tag === 'a' ) {
					savedRange = range.cloneRange();
					linkInput.value = '';
					linkbar.hidden = false;
					linkInput.focus();
					return;
				}

				wrapSelection( range, tag, null );
				onChange();
			}

			/**
			 *	Whether a paragraph or an item holds nothing a person sees
			 *
			 *	@param		{Element}		el
			 *
			 *	@return		{boolean}
			 */
			function isBlank( el ) {
				return el.textContent === '';
			}

			/**
			 *	An empty paragraph or item shows no line and cannot hold a caret in
			 *	some browsers: it gets a <br> to stand on, which the value never keeps
			 *
			 *	@param		{Element}		el
			 *
			 *	@return		void
			 */
			function holder( el ) {
				if( isBlank( el ) === true && el.querySelector('br') === null )
					el.appendChild( dc.createElement('br') );
			}

			/**
			 *	Whether nothing but the tags it stands in follows a node inside
			 *	a container
			 *
			 *	@param		{Node}			node
			 *	@param		{Element}		container
			 *
			 *	@return		{boolean}
			 */
			function isLastIn( node, container ) {
				for( ; node !== container && node !== null; node = node.parentNode )
					for( let next = node.nextSibling; next !== null; next = next.nextSibling )
						if( next.nodeType === 1 || next.nodeValue !== '' )
							return false;
				return true;
			}

			/**
			 *	Put a line break at the caret. One at the very end of its line
			 *	shows nothing until something follows it, so it gets a second to
			 *	stand for the new line - the value drops it again (see serialize())
			 *
			 *	@param		{Range}		range
			 *
			 *	@return		void
			 */
			function insertBreak( range ) {

				if( range.collapsed === false )
					range.deleteContents();

				const br = dc.createElement('br');
				range.insertNode( br );

				if( isLastIn( br, blockOf( br ) ?? content ) === true )
					br.after( dc.createElement('br') );

				const after = dc.createRange();
				after.setStartAfter( br );
				after.collapse( true );
				select( after );
			}

			/**
			 *	Cut the paragraph or item at the caret in two. The inline tag the
			 *	caret is in is cut with it, so what is typed next is still bold
			 *	if it was
			 *
			 *	@param		{Element}		block
			 *	@param		{Range}			range			Collapsed
			 *
			 *	@return		{Element}									The block that was made, with the caret at its start
			 */
			function splitBlock( block, range ) {

				const tail = dc.createRange();
				tail.setStart( range.startContainer, range.startOffset );
				tail.setEnd( block, block.childNodes.length );

				const next = dc.createElement( block.tagName.toLowerCase() );
				next.appendChild( tail.extractContents() );
				block.after( next );

				removeEmptyTags();
				holder( block );
				holder( next );
				caretToStart( next );

				return next;
			}

			/**
			 *	Take an item out of its list and make it a paragraph. Items after it
			 *	stay a list of their own, behind the paragraph
			 *
			 *	@param		{Element}		li
			 *
			 *	@return		{Element}									The paragraph
			 */
			function leaveList( li ) {

				const list = li.parentNode;
				const p = dc.createElement('p');
				p.append( ...li.childNodes );

				const rest = [];
				for( let next = li.nextElementSibling; next !== null; next = next.nextElementSibling )
					rest.push( next );

				if( rest.length > 0 ) {
					const tail = dc.createElement( list.tagName.toLowerCase() );
					tail.append( ...rest );
					list.after( tail );
				}

				list.after( p );
				li.remove();

				if( list.querySelector('li') === null )
					list.remove();

				holder( p );
				caretToStart( p );

				return p;
			}

			/**
			 *	Enter. In 'inline' nothing happens - the field is one line. In
			 *	'lines' it is a line break, in 'blocks' it cuts the paragraph or
			 *	item in two (Shift+Enter is the line break there), and Enter in
			 *	an item with nothing in it ends the list
			 *
			 *	@param		{boolean}		lineBreak			Shift+Enter, or the browser's own insertLineBreak
			 *
			 *	@return		void
			 */
			function enter( lineBreak ) {

				if( profile.breaks === false )
					return;

				const range = currentRange();
				if( range === null )
					return;

				if( profile.blocks === false || lineBreak === true ) {
					insertBreak( range );
					onChange();
					return;
				}

				if( range.collapsed === false )
					range.deleteContents();

				const block = blockOf( range.startContainer );
				if( block === null )
					return;

				if( block.tagName === 'LI' && isBlank( block ) === true )
					leaveList( block );
				else
					splitBlock( block, range );

				onChange();
			}

			/**
			 *	Whether the caret is at the very start (or end) of a block, so
			 *	that Backspace (Delete) has a boundary to act on and not a
			 *	character
			 *
			 *	@param		{Range}			range
			 *	@param		{Element}		block
			 *	@param		{boolean}		atEnd
			 *
			 *	@return		{boolean}
			 */
			function atEdge( range, block, atEnd ) {

				const probe = dc.createRange();
				probe.selectNodeContents( block );

				if( atEnd === true )
					probe.setStart( range.endContainer, range.endOffset );
				else
					probe.setEnd( range.startContainer, range.startOffset );

				// No text between the caret and that edge, and no <br> either - bar
				// the one an empty block stands on
				if( probe.toString() !== '' )
					return false;

				return probe.cloneContents().querySelector('br') === null || isBlank( block ) === true;
			}

			/**
			 *	The paragraph or item before or after one, in reading order
			 *
			 *	@param		{Element}		block
			 *	@param		{number}		step					-1 or 1
			 *
			 *	@return		{Element|null}
			 */
			function neighbour( block, step ) {
				const all = Array.from( content.querySelectorAll('p, li') );
				return all[all.indexOf( block ) + step] ?? null;
			}

			/**
			 *	Move a block's content to the end of another and remove it - the
			 *	merge a browser makes with a <span style> of its own
			 *
			 *	@param		{Element}		target
			 *	@param		{Element}		source
			 *
			 *	@return		void
			 */
			function mergeInto( target, source ) {

				// What a blank one stands on goes, it is not content to join onto
				if( isBlank( target ) === true )
					target.replaceChildren();

				const at = target.childNodes.length;
				const list = source.tagName === 'LI' ? source.parentNode : null;

				while( source.firstChild !== null )
					target.appendChild( source.firstChild );

				source.remove();

				if( list !== null && list.querySelector('li') === null )
					list.remove();

				holder( target );

				const caret = dc.createRange();
				caret.setStart( target, Math.min( at, target.childNodes.length ) );
				caret.collapse( true );
				select( caret );
			}

			/**
			 *	Backspace at the start of a block, Delete at the end of one: join it
			 *	with its neighbour, or, for the first item of a list, make it a
			 *	paragraph. Done here because the browser's own merge is what leaves
			 *	the styled spans and the divs - everywhere else the key is left alone
			 *
			 *	@param		{string}		key						'Backspace' or 'Delete'
			 *
			 *	@return		{boolean}									Whether the key was handled
			 */
			function mergeAtBoundary( key ) {

				const range = currentRange();
				if( range === null || range.collapsed === false )
					return false;

				const block = blockOf( range.startContainer );
				if( block === null || atEdge( range, block, key === 'Delete' ) === false )
					return false;

				if( key === 'Delete' ) {
					const next = neighbour( block, 1 );
					if( next === null )
						return true;
					mergeInto( block, next );
					onChange();
					return true;
				}

				if( block.tagName === 'LI' && block.previousElementSibling === null ) {
					leaveList( block );
					onChange();
					return true;
				}

				const previous = neighbour( block, -1 );
				if( previous === null )
					return true;

				mergeInto( previous, block );
				onChange();
				return true;
			}

			/**
			 *	Put text at the caret as it is - never as markup. A newline of it
			 *	follows the format: nothing in 'inline' (a space), a <br> in 'lines',
			 *	and in 'blocks' a <br>, and a blank line a new paragraph
			 *
			 *	@param		{string}		text
			 *
			 *	@return		void
			 */
			function insertPlainText( text ) {

				const range = currentRange();
				if( range === null )
					return;

				if( range.collapsed === false )
					range.deleteContents();

				text = String( text ).replace( /\r\n?/g, '\n' );

				if( profile.breaks === false ) {
					const node = dc.createTextNode( text.replace( /\s*\n\s*/g, ' ' ) );
					range.insertNode( node );
					range.setStartAfter( node );
					range.collapse( true );
					select( range );
					return;
				}

				const paragraphs = profile.blocks === true ? text.split( /\n[ \t]*\n/ ) : [ text ];

				paragraphs.forEach( function( part, index ) {

					if( index > 0 ) {
						const at = currentRange();
						const block = blockOf( at.startContainer );
						if( block === null )
							return;
						splitBlock( block, at );
					}

					const at = currentRange();
					const fragment = dc.createDocumentFragment();

					part.split('\n').forEach( function( line, lineIndex ) {
						if( lineIndex > 0 )
							fragment.appendChild( dc.createElement('br') );
						if( line !== '' )
							fragment.appendChild( dc.createTextNode( line ) );
					} );

					const last = fragment.lastChild;
					at.insertNode( fragment );

					if( last !== null ) {
						const after = dc.createRange();
						after.setStartAfter( last );
						after.collapse( true );
						select( after );
					}
				} );
			}

			/**
			 *	Make the list buttons' choice: the paragraphs and items the selection
			 *	touches become a list of that kind, or - when they all already are
			 *	one - paragraphs again. An item of the other kind of list is moved
			 *	to the one asked for, together with its list
			 *
			 *	@param		{string}		kind					'ul' or 'ol'
			 *
			 *	@return		void
			 */
			function toggleList( kind ) {

				const range = currentRange();
				if( range === null )
					return;

				const blocks = Array.from( content.querySelectorAll('p, li') ).filter( function( block ) { return range.intersectsNode( block ) } );
				if( blocks.length === 0 )
					return;

				const made = [];

				if( blocks.every( function( block ) { return block.tagName === 'LI' && block.parentNode.tagName.toLowerCase() === kind } ) === true ) {
					blocks.forEach( function( li ) { made.push( leaveList( li ) ) } );
				} else {

					blocks.forEach( function( block ) {

						let list = block.parentNode;

						if( block.tagName === 'LI' ) {
							if( list.tagName.toLowerCase() !== kind ) {
								const swapped = dc.createElement( kind );
								swapped.append( ...list.childNodes );
								list.replaceWith( swapped );
								list = swapped;
							}
							made.push( block );
							return;
						}

						const li = dc.createElement('li');
						li.append( ...block.childNodes );

						const before = block.previousElementSibling;

						if( before !== null && before.tagName.toLowerCase() === kind ) {
							before.appendChild( li );
							block.remove();
						} else {
							list = dc.createElement( kind );
							list.appendChild( li );
							block.replaceWith( list );
						}

						holder( li );
						made.push( li );
					} );

					// Two lists of a kind that now touch are one list
					content.querySelectorAll( kind ).forEach( function( list ) {
						const next = list.nextElementSibling;
						if( next !== null && next.tagName.toLowerCase() === kind ) {
							list.append( ...next.childNodes );
							next.remove();
						}
					} );
				}

				const newRange = dc.createRange();
				newRange.setStart( made[0], 0 );
				newRange.setEnd( made[made.length - 1], made[made.length - 1].childNodes.length );
				select( newRange );

				onChange();
			}

			TAGS.forEach( function( tag ) {
				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'nino-admin-richtext-btn';
				btn.dataset.tag = tag;
				btn.textContent = Nino.content.getText('/_admin/htmleditor/label/'+ tag);
				btn.setAttribute( 'aria-pressed', 'false' );
				if( tag === 'strong' )
					btn.setAttribute( 'aria-keyshortcuts', 'Control+B Meta+B' );
				if( tag === 'em' )
					btn.setAttribute( 'aria-keyshortcuts', 'Control+I Meta+I' );
				btn.addEventListener( 'mousedown', function( ev ) { ev.preventDefault() } );
				btn.addEventListener( 'click', function() { applyFormat( tag ) } );
				toolbar.appendChild( btn );
			} );

			// Lists only where the format has them
			if( profile.blocks === true )
				[ 'ul', 'ol' ].forEach( function( kind ) {
					const btn = dc.createElement('button');
					btn.type = 'button';
					btn.className = 'nino-admin-richtext-btn';
					btn.dataset.list = kind;
					btn.textContent = Nino.content.getText('/_admin/htmleditor/label/'+ kind);
					btn.setAttribute( 'aria-pressed', 'false' );
					btn.addEventListener( 'mousedown', function( ev ) { ev.preventDefault() } );
					btn.addEventListener( 'click', function() { toggleList( kind ) } );
					toolbar.appendChild( btn );
				} );

			linkOk.addEventListener( 'click', function() {

				const href = linkInput.value.trim();
				if( href === '' || savedRange === null )
					return;

				select( savedRange );

				wrapSelection( savedRange, 'a', href );
				linkbar.hidden = true;
				savedRange = null;
				onChange();
			} );

			linkCancel.addEventListener( 'click', function() {
				linkbar.hidden = true;
				savedRange = null;
			} );

			content.addEventListener( 'input', onChange );

			content.addEventListener( 'keydown', function( ev ) {

				// A key the browser is composing with (an IME confirming a word with
				// Enter) is the composition's, not an Enter of ours
				if( ev.isComposing === true || ev.keyCode === 229 )
					return;

				const shortcut = shortcutTag( ev );

				if( shortcut !== null ) {
					ev.preventDefault();
					if( shortcut !== '' )
						applyFormat( shortcut );
					return;
				}

				if( ev.key === 'Enter' ) {
					ev.preventDefault();
					enter( ev.shiftKey === true );
					return;
				}

				if( profile.blocks === true && ( ev.key === 'Backspace' || ev.key === 'Delete' ) && mergeAtBoundary( ev.key ) === true )
					ev.preventDefault();
			} );

			// What keydown does not reach: a mobile or IME keyboard reports its
			// Enter here and not there, the context menu and voice input format
			// text on their own, and a drop is an insertion nobody asked this
			// editor about. Formatting is this editor's (flat tags, no <b>, no
			// <u>), so the browser's own is cancelled whichever way it comes
			content.addEventListener( 'beforeinput', function( ev ) {

				const type = ev.inputType || '';

				if( /^format/.test( type ) === true || type === 'insertOrderedList' || type === 'insertUnorderedList' || type === 'insertHorizontalRule' || type === 'insertLink' ) {
					ev.preventDefault();
					return;
				}

				if( type === 'insertParagraph' || type === 'insertLineBreak' ) {
					ev.preventDefault();
					enter( type === 'insertLineBreak' );
					return;
				}

				if( type === 'insertFromDrop' ) {

					ev.preventDefault();

					const text = ev.dataTransfer ? ev.dataTransfer.getData('text/plain') : '';
					const target = typeof ev.getTargetRanges === 'function' ? ev.getTargetRanges()[0] : undefined;

					if( target !== undefined ) {
						const at = dc.createRange();
						at.setStart( target.startContainer, target.startOffset );
						at.setEnd( target.endContainer, target.endOffset );
						select( at );
					}

					insertPlainText( text );
					onChange();
				}
			} );

			// Paste as plain text only - anything richer would need the same
			// sanitizing the server already does, so keep the client simple
			content.addEventListener( 'paste', function( ev ) {

				ev.preventDefault();

				insertPlainText( ( ev.clipboardData || wn.clipboardData ).getData('text/plain') );

				onChange();
			} );

			function onSelectionChange() {
				const range = currentRange();
				toolbar.querySelectorAll('button[data-tag]').forEach( function( btn ) {
					const active = range !== null && range.collapsed === false && findWrappingTag( range, btn.dataset.tag ) !== null;
					btn.classList.toggle( 'active', active );
					btn.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );
				toolbar.querySelectorAll('button[data-list]').forEach( function( btn ) {
					const block = range === null ? null : blockOf( range.startContainer );
					const active = block !== null && block.tagName === 'LI' && block.parentNode.tagName.toLowerCase() === btn.dataset.list;
					btn.classList.toggle( 'active', active );
					btn.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );
			}

			dc.addEventListener( 'selectionchange', onSelectionChange );

			container.appendChild( toolbar );
			container.appendChild( content );
			container.appendChild( linkbar );
			container.appendChild( counter );

			updateCounter();

			/**
			 *	The value as the server will keep it: what the browser leaves in a
			 *	paragraph or an item to stand on - a trailing <br> - and what is
			 *	empty is not part of it, so a field nobody typed into reads as ''
			 *	and an untouched one as what it held
			 *
			 *	@return		{string}
			 */
			function serialize() {

				if( profile.breaks === false )
					return content.innerHTML;

				if( content.textContent === '' )
					return '';

				const copy = content.cloneNode( true );

				if( profile.blocks === true ) {

					copy.querySelectorAll('p, li').forEach( function( block ) {
						trimBreaks( block );
						if( block.firstChild === null )
							block.remove();
					} );

					copy.querySelectorAll('ul, ol').forEach( function( list ) {
						if( list.firstChild === null )
							list.remove();
					} );

				} else
					trimBreaks( copy );

				return copy.innerHTML;
			}

			return {

				getValue : function() {
					return serialize();
				},

				setValue : function( html ) {
					load( content, html, profile );
					updateCounter();
				},

				/**
				 *	Put the caret in the text, where a person who was told this
				 *	field is empty can start typing
				 */
				focus : function() {
					content.focus();
				},

				/**
				 *	Say on the text box itself what the field is: required, refused
				 *	(invalid) and the element that explains why (describedBy, an id,
				 *	'' to let go of it). A state left out stays as it is, false takes
				 *	the attribute off. The box is the role=textbox element, not the
				 *	group around the toolbar - that is what a screen reader lands on
				 *
				 *	@param		{Object}		state					{ required, invalid, describedBy }
				 *
				 *	@return		void
				 */
				mark : function( state ) {

					[ [ 'required', 'aria-required' ], [ 'invalid', 'aria-invalid' ] ].forEach( function( pair ) {
						if( state[pair[0]] === true )
							content.setAttribute( pair[1], 'true' );
						else if( state[pair[0]] === false )
							content.removeAttribute( pair[1] );
					} );

					if( typeof state.describedBy === 'string' ) {
						if( state.describedBy === '' )
							content.removeAttribute('aria-describedby');
						else
							content.setAttribute( 'aria-describedby', state.describedBy );
					}
				},

				destroy : function() {
					dc.removeEventListener( 'selectionchange', onSelectionChange );
				},
			};
		},
	};

})(window, document, document.documentElement, document.body);
