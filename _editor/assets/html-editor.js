

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	html-editor.js					Minimal contenteditable rich-text editor for admin "html"
 *													fields: strong/em/span/code/a only, always flat - applying a
 *													format to a selection replaces any format already on it,
 *													never nests. No execCommand; paste is always plain text.
 *													Shared by Text and Elements in /_editor and /_admin for
 *													any field whose model/entry has html === true.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.editor = wn.Nino.editor || {};

	const TAGS = [ 'strong', 'em', 'span', 'code', 'a' ];

	Nino.editor.htmlEditor = {

		/**
		 *	Build the editor (toolbar + contenteditable + char counter) into container
		 *
		 *	@param		{Element}		container			Element to render the editor into (cleared first)
		 *	@param		{string}		value					Initial html value
		 *	@param		{number}		maxlength			Max visible (textContent) character count
		 *
		 *	@return		{Object}									{ getValue(), setValue( html ), destroy() }
		 */
		create : function( container, value, maxlength ) {

			container.innerHTML = '';
			container.classList.add('nino-admin-richtext');

			const toolbar = dc.createElement('div');
			toolbar.className = 'nino-admin-richtext-toolbar';
			toolbar.setAttribute( 'role', 'toolbar' );
			toolbar.setAttribute( 'aria-label', Nino.content.getText('/_editor/htmleditor/label/formatting') );

			const content = dc.createElement('div');
			content.className = 'nino-admin-richtext-content';
			content.contentEditable = 'true';
			content.setAttribute( 'role', 'textbox' );
			content.setAttribute( 'aria-multiline', 'false' );
			content.setAttribute( 'aria-label', Nino.content.getText('/_editor/htmleditor/label/content') );
			content.setAttribute( 'tabindex', '0' );
			content.spellcheck = true;
			content.innerHTML = value || '';

			const linkbar = dc.createElement('div');
			linkbar.className = 'nino-admin-richtext-linkbar';
			linkbar.hidden = true;

			const linkInput = dc.createElement('input');
			// type="text", not "url": relative paths like "/contact" are valid hrefs here
			// (see _safeHref() server-side) but fail native url validation, which would
			// silently block the surrounding form's submit once this field is hidden
			linkInput.type = 'text';
			linkInput.placeholder = Nino.content.getText('/_editor/htmleditor/label/linkplaceholder');
			linkInput.setAttribute( 'aria-label', Nino.content.getText('/_editor/htmleditor/label/linkplaceholder') );

			const linkOk = dc.createElement('button');
			linkOk.type = 'button';
			linkOk.textContent = Nino.content.getText('/_editor/htmleditor/label/linkok');

			const linkCancel = dc.createElement('button');
			linkCancel.type = 'button';
			linkCancel.textContent = Nino.content.getText('/_editor/htmleditor/label/linkcancel');

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
				const sel = wn.getSelection();
				sel.removeAllRanges();
				sel.addRange( range );
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
			 *	@return		void
			 */
			function wrapSelection( range, tag, href ) {

				const text = range.toString();
				if( text === '' )
					return;

				range.deleteContents();

				const el = dc.createElement( tag );
				el.textContent = text;
				if( tag === 'a' )
					el.href = href;

				range.insertNode( el );

				const newRange = dc.createRange();
				newRange.selectNodeContents( el );
				const sel = wn.getSelection();
				sel.removeAllRanges();
				sel.addRange( newRange );
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
				const sel = wn.getSelection();
				sel.removeAllRanges();
				sel.addRange( endRange );
			}

			/**
			 *	Range.deleteContents()/extractContents() can leave an emptied but
			 *	un-removed ancestor tag behind when a range boundary starts/ends
			 *	inside it (eg. selecting "all" from inside a <span> onto plain
			 *	text after it) - clean those up so formatting a selection never
			 *	leaves stray empty tags in the value
			 *
			 *	@return		void
			 */
			function removeEmptyTags() {
				content.querySelectorAll( TAGS.join(',') ).forEach( function( el ) {
					if( el.textContent === '' )
						el.remove();
				} );
			}

			function onChange() {
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

			TAGS.forEach( function( tag ) {
				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'nino-admin-richtext-btn';
				btn.dataset.tag = tag;
				btn.textContent = Nino.content.getText('/_editor/htmleditor/label/'+ tag);
				btn.setAttribute( 'aria-pressed', 'false' );
				btn.addEventListener( 'mousedown', function( ev ) { ev.preventDefault() } );
				btn.addEventListener( 'click', function() { applyFormat( tag ) } );
				toolbar.appendChild( btn );
			} );

			linkOk.addEventListener( 'click', function() {

				const href = linkInput.value.trim();
				if( href === '' || savedRange === null )
					return;

				const sel = wn.getSelection();
				sel.removeAllRanges();
				sel.addRange( savedRange );

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
				if( ev.key === 'Enter' )
					ev.preventDefault();
			} );

			// Paste as plain text only - anything richer would need the same
			// sanitizing the server already does, so keep the client simple
			content.addEventListener( 'paste', function( ev ) {

				ev.preventDefault();

				const range = currentRange();
				if( range === null )
					return;

				const text = ( ev.clipboardData || wn.clipboardData ).getData('text/plain');
				range.deleteContents();
				const textNode = dc.createTextNode( text );
				range.insertNode( textNode );
				range.setStartAfter( textNode );
				range.collapse( true );
				const sel = wn.getSelection();
				sel.removeAllRanges();
				sel.addRange( range );

				onChange();
			} );

			function onSelectionChange() {
				const range = currentRange();
				toolbar.querySelectorAll('button[data-tag]').forEach( function( btn ) {
					const active = range !== null && range.collapsed === false && findWrappingTag( range, btn.dataset.tag ) !== null;
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

			return {

				getValue : function() {
					return content.innerHTML;
				},

				setValue : function( html ) {
					content.innerHTML = html || '';
					updateCounter();
				},

				destroy : function() {
					dc.removeEventListener( 'selectionchange', onSelectionChange );
				},
			};
		},
	};

})(window, document, document.documentElement, document.body);
