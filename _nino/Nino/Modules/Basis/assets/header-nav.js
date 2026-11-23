/**
 *	Nino - Basis
 *	header-nav.js		While the open menu of the header is an overlay (by the stylesheet: the header is fixed over
 *									the window, drawn, with its button): Escape closes it, the page behind is inert and does not
 *									scroll, a link to a place on the page closes it. The menu of another component is one too when its details says
 *									data-header-menu. Without the script the menu is the stylesheet's.
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
'use strict';

( function() {

	const root = document.documentElement;
	const watcher = new MutationObserver( sync );

	// Held menu, inert for it, all looked at (one a script frees is its), root style before, scroll place
	let held = null;
	let made = [];
	let seen = new Set();
	let before = [];
	let at = null;
	const mark = ( node, on ) => { try { node.toggleAttribute( 'inert', on ) } catch( e ) {} };

	// A manual popover is a toast, no layer
	const layer = () => { try { return document.querySelector( ':modal, :popover-open:not([popover=manual])' ) } catch( e ) { return document.querySelector( ':modal' ) } };

	const dead = node => { for( ; node; node = node.parentNode ) if( node.inert === true && !made.includes( node ) ) return true; return false };

	function overlay() {

		let found = null;

		if( !root.hasAttribute( 'data-nino-preview' ) )
			document.querySelectorAll( '.header-nav-details[open], [data-header-menu][open]' ).forEach( menu => {

				const wrap = menu.closest( '.header-wrap' );
				const button = menu.querySelector( 'summary' );

				if( wrap && button && getComputedStyle( wrap ).position === 'fixed' && getComputedStyle( button ).display !== 'none' && !dead( wrap ) && wrap.checkVisibility() )
					found = menu;
			} );

		return found;
	}

	function sync() {

		let menu = overlay();
		const top = menu && layer();

		if( top && !menu.closest( '.header-wrap' ).contains( top ) ) {
			menu.open = false;
			menu = null;
		}

		if( !menu && !held )
			at = null;

		if( menu !== held ) {

			watcher.disconnect();

			if( held && !held.open && at !== null && window.scrollY !== at )
				window.scrollTo( { left : window.scrollX, top : at, behavior : 'instant' } );

			if( held )
				at = null;

			made.forEach( node => mark( node, false ) );
			before.forEach( old => root.style.setProperty( ...old ) );

			if( root.getAttribute( 'style' ) === '' )
				root.removeAttribute( 'style' );

			made = [];
			before = [];
			seen = new Set();
			held = menu;

			if( !menu )
				return;

			at = at ?? window.scrollY;
			before = [ 'overflow-x', 'overflow-y', 'scrollbar-gutter', 'background-color' ].map( name => [ name, root.style.getPropertyValue( name ), root.style.getPropertyPriority( name ) ] );

			if( window.innerWidth > root.clientWidth ) {
				root.style.setProperty( 'scrollbar-gutter', 'stable' );
				root.style.setProperty( 'background-color', getComputedStyle( menu.closest( '.header-wrap' ) ).backgroundColor );
			}

			root.style.setProperty( 'overflow', 'hidden' );
			watcher.observe( document, { subtree : true, childList : true, attributes : true, attributeFilter : [ 'class', 'open', 'hidden', 'inert', 'style', 'data-header-menu' ] } );
		}

		if( !menu )
			return;

		made = made.filter( node => node.inert === true );

		for( let node = menu.closest( '.header-wrap' ); node !== document.body && node.parentNode; node = node.parentNode )
			[ ...node.parentNode.children ].forEach( other => {

				if( other !== node && !seen.has( other ) ) {
					seen.add( other );

					if( other.inert === false ) {
						made.push( other );
						mark( other, true );
					}
				}
			} );
	}

	Nino.events.bindCallback( 'ready', () => {

		// Escape is the top layer's, and a search field's that has text
		document.addEventListener( 'keydown', event => {

			held && sync();

			const menu = event.key === 'Escape' && !event.defaultPrevented && !layer() && ( event.target.type !== 'search' || !event.target.value ) ? overlay() : null;

			if( menu ) {
				event.preventDefault();
				menu.open = false;
				menu.querySelector( 'summary' ).focus( { preventScroll : true } );
				sync();
			}
		} );

		document.addEventListener( 'pointerdown', () => held && sync(), true );

		document.addEventListener( 'click', event => {

			const button = event.target.closest( 'summary' );
			const link = event.target.closest( 'a[href]' );

			if( !held && button && button.parentNode.matches( '.header-nav-details, [data-header-menu]' ) )
				at = window.scrollY;

			const menu = !link || event.defaultPrevented || event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || ( link.target !== '' && link.target !== '_self' ) ? null : overlay();

			if( menu && menu.contains( link ) && link.href.includes( '#' ) && link.href.split( '#' )[0] === location.href.split( '#' )[0] ) {
				menu.open = false;
				sync();
			}
		} );

		document.addEventListener( 'toggle', sync, true );
		window.addEventListener( 'resize', sync );
		window.addEventListener( 'pageshow', sync );

		sync();
	} );

} )();
