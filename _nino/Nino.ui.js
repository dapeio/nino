

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	Nino.ui.js							Frontend design-system behaviors (cover, parallax, vpa,
 *													autoheight, slider, scroll-header, generic forms, tabs,
 *													modal/lightbox, toast) - split out of Nino.js since
 *													only the public site needs this, never _editor/_admin.
 *													Load order: Nino.js, then this file (depends on
 *													Nino.client/content/dom/events/http).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.ui = {

		_onResize			: [],
		_onResizeDone 		: false,
		_onScroll			: [],
		_onScrollTicking	: false,
		_onViewable			: [],
		_onViewableDelay	: 500,
		_sliderTouchThreshold : 8,

		/**
		 *	Decide whether a slider touch gesture is horizontal or vertical.
		 *	Small movements remain undecided; equal diagonal movement deliberately
		 *	counts as vertical so the page keeps its native scrolling behaviour.
		 *
		 *	@param		{number}	deltaX				Horizontal distance from touchstart
		 *	@param		{number}	deltaY				Vertical distance from touchstart
		 *
		 *	@return		{string|null}				'x', 'y', or null below the threshold
		 */
		_sliderTouchAxis : function( deltaX, deltaY ) {

			const
				distanceX = Math.abs( deltaX ),
				distanceY = Math.abs( deltaY );

			if( Math.max( distanceX, distanceY ) < Nino.ui._sliderTouchThreshold )
				return null;

			return distanceX > distanceY ? 'x' : 'y';
		},

		/**
		 *	Width available to a cover inside its actual containing block.
		 *	Cover height is intentionally viewport-relative, but its width must
		 *	respect layouts that reserve part of that viewport for a side rail.
		 *
		 *	@param		{Element}	cover				Cover element being resized
		 *	@param		{number}	viewportWidth	Fallback when there is no measurable parent
		 *
		 *	@return		{number}					Parent content-box width in pixels
		 */
		_coverContainingWidth : function( cover, viewportWidth ) {

			const parent = cover.parentElement;
			if( parent === null || Number.isFinite( Number( parent.clientWidth ) ) === false )
				return viewportWidth;

			const style = wn.getComputedStyle( parent );
			const padding = ( parseFloat( style.getPropertyValue('padding-left') ) || 0 )
				+ ( parseFloat( style.getPropertyValue('padding-right') ) || 0 );

			return Math.max( 0, Number( parent.clientWidth ) - padding );
		},

		/**
		 *	Initialize all ui behaviors (cover, parallax, vpa, autoheight,
		 *	scroll header, slider, form) for the elements found on the
		 *	current page
		 *
		 *	@return		void
		 */
		onReady : function() {


			const
				ui			= Nino.ui,
				e 			= {
					arrowDown	: dc.querySelectorAll( '.nino-atf-arrowdown' ),
					autoheight	: dc.querySelectorAll( '.nino-autoheight' ),
					backToTop	: dc.querySelectorAll( '.nino-back-to-top' ),
					cookieBanner	: dc.querySelectorAll( '.nino-cookie-banner' ),
					cover			: dc.querySelectorAll( '.nino-cover' ),
					filter		: dc.querySelectorAll( '.nino-filter' ),
					// .nino-newsletter-form opts out - it keeps .nino-form only for the
					// shared success/error/pending styling, its own submit handler
					// below binds it separately (needs its own "already subscribed"
					// vs "new signup" messaging, not just a generic success/error pair)
					form			: dc.querySelectorAll( '.nino-form:not(.nino-newsletter-form)' ),
					modal			: dc.querySelectorAll( '.nino-modal' ),
					modalTrigger	: dc.querySelectorAll( '.nino-modal-trigger' ),
					newsletterForm	: dc.querySelectorAll( '.nino-newsletter-form' ),
					parallex	: dc.querySelectorAll( '.nino-parallex' ),
					preloader	: dc.querySelectorAll( '.nino-preloader' ),
					slider		: dc.querySelectorAll( '.nino-slider' ),
					statCounter	: dc.querySelectorAll( '.nino-stat-counter' ),
					tabs			: dc.querySelectorAll( '.nino-tabs' ),
					toastTrigger	: dc.querySelectorAll( '.nino-toast-trigger' ),
					vpa				: dc.querySelectorAll( '.nino-vpa' ),
				};

			/*
			 *	Store client details
			 */

			bd.classList.add( Nino.client.isMobile ? 'client-mobile' : 'client-desktop' );

			/*
			 *	Catch hashchange smooth scroll
			 */
			dE.style.scrollBehavior = "smooth";

			/**
			 *	Smooth-scroll to the element referenced by the current url hash
			 *
			 *	@param		{Event}		[ev]						(optional) hashchange event, prevented if scrollable
			 *
			 *	@return		void
			 */
			const fnHashchange = function( ev ) {
				const el = dc.getElementById( wn.location.hash.substr(1) );
				if( el === null || typeof el.scrollIntoView === 'undefined' )
					return;
				if( typeof ev !== 'undefined' && typeof ev.preventDefault !== 'undefined' )
					ev.preventDefault();

				el.scrollIntoView({ behavior: 'smooth' });
			};
			wn.addEventListener( 'hashchange', fnHashchange );

			if( wn.location.hash.length > 1 ) {
				ui._onViewableDelay += 500;
				ui._onViewable.push( fnHashchange );
			}

			/*
			 *	nino-autoheight - equalize the height of every element sharing the
			 *	same data-autoheight-group, so eg. a row of cards with uneven
			 *	text lengths still line up. Opt out per-element on mobile via
			 *	data-autoheight-mobile (any non-empty value)
			 */
			if( e.autoheight.length > 0 ) {

				for( let i=0, l=e.autoheight.length; i<l; i++ )
					e.autoheight[i].grp = e.autoheight[i].getAttribute('data-autoheight-group') ?? 0;

				ui._onResize.push( function( wH, wW ) {

					const max = {};
					for( let i=0, l=e.autoheight.length; i<l; i++ ) {
						if( ( e.autoheight[i].getAttribute('data-autoheight-mobile') ?? '' ) !== '' && Nino.client.isMobile === true )
							continue;

						e.autoheight[i].style.height = 'auto';
						max[e.autoheight[i].grp] = Math.max( e.autoheight[i].getBoundingClientRect().height, ( max[e.autoheight[i].grp] ?? 0 ) );
					}

					for( let i=0, l=e.autoheight.length; i<l; i++ )
						e.autoheight[i].style.height = max[e.autoheight[i].grp] + 'px';
				} );
			}

			/*
			 *	nino-cover
			 */
			if( e.cover.length > 0 ) {
				// Height is a percentage of the viewport. Width is a percentage of
				// the containing content box: a persistent side navigation (Header
				// v6) already removes its rail from <main>, so adding 100vw inside
				// that narrower main would overflow by exactly the rail width.
				ui._onResize.push( function( wH, wW ) {
					for( let i=0, l=e.cover.length; i<l; i++ ) {
						let
							w 			= e.cover[i].getAttribute( 'data-cover-width' ) ?? 100,
							h 			= e.cover[i].getAttribute( 'data-cover-height' ) ?? 90,
							style		= wn.getComputedStyle( e.cover[i] ),
							marginH		= ( parseFloat( style.getPropertyValue('margin-top') ) || 0 ) + ( parseFloat( style.getPropertyValue('margin-bottom') ) || 0 ),
							marginW		= ( parseFloat( style.getPropertyValue('margin-left') ) || 0 ) + ( parseFloat( style.getPropertyValue('margin-right') ) || 0 ),
							contW		= ui._coverContainingWidth( e.cover[i], wW ),
							wrapH		= e.cover[i].querySelector('div')?.offsetHeight ?? 0;

						if( h !== null ) e.cover[i].style.height = Math.max( ( ( wH / 100 * h ) - marginH ), 50 + wrapH ) + 'px';
						if( w !== null ) e.cover[i].style.width = ( ( contW * w / 100 ) - marginW ) + 'px';
					}
				} );
			}
			/*
			 *	nino-parallex
			 */
			if( e.parallex.length > 0 ) {
				for( let i=0, l=e.parallex.length; i<l; i++ )
					e.parallex[i].img = e.parallex[i].querySelector('img');

				/**
				 *	Apply a parallax offset to .nino-parallex images based on scroll position
				 *
				 *	@param		{number}	wh							Viewport height
				 *	@param		{number}	ww							Viewport width
				 *	@param		{number}	st							Scroll top
				 *	@param		{number}	sl							Scroll left
				 *
				 *	@return		void
				 */
				const parallexScroll = function( wh, ww, st, sl ) {
					for( let i=0, l=e.parallex.length; i<l; i++ ) {
						let br = e.parallex[i].getBoundingClientRect();
						if( br.top < wh && br.bottom > 0 )
							e.parallex[i].img.style.top = 0 - ( wh / 8 ) - ( br.top / 1.1 ) + 'px';
					}
				};

				ui._onScroll.push( parallexScroll );
			}


			/*
			 *	nino-preloader
			 */
			if( e.preloader.length > 0 ) {
				wn.addEventListener( 'load', function() {
					e.preloader[0].style.opacity = '0';
					setTimeout( function() {
						e.preloader[0].remove();
					}, 1100 );
				} );
			}


			/*
			 *	nino-cookie-banner - reveal only if no consent choice was made
			 *	yet (see Nino.ui.cookieConsent below). Buttons carry the
			 *	choice in data-cookie-consent rather than two separate click
			 *	handlers, since accept/decline only differ in which value
			 *	gets stored
			 */
			if( e.cookieBanner.length > 0 && ui.cookieConsent.get() === null ) {
				const banner = e.cookieBanner[0];
				wn.requestAnimationFrame( () => banner.classList.add('nino-cookie-banner--visible') );
				banner.querySelectorAll('[data-cookie-consent]').forEach( function( btn ) {
					btn.addEventListener( 'click', function() {
						ui.cookieConsent.set( this.getAttribute('data-cookie-consent') );
						banner.classList.remove('nino-cookie-banner--visible');
					} );
				} );
			}


			/*
			 *	nino-vpa
			 */
			if( e.vpa.length > 0 ) {
				for( let i=0, ad, al, l=e.vpa.length; i<l; i++ ) {
					if( ad = e.vpa[i].getAttribute('data-vpa-delay') )
						e.vpa[i].style.transitionDelay = ad;
					if( al = e.vpa[i].getAttribute('data-vpa-duration') )
						e.vpa[i].style.transitionDuration = al;
				}

				/**
				 *	Toggle "visible"/"visible-once" classes on .nino-vpa elements
				 *	as they enter/leave the viewport
				 *
				 *	@param		{number}	wh							Viewport height
				 *	@param		{number}	ww							Viewport width
				 *	@param		{number}	st							Scroll top
				 *	@param		{number}	sl							Scroll left
				 *
				 *	@return		void
				 */
				const vpaScroll = function( wh, ww, st, sl ) {
					for( let i=0, l=e.vpa.length; i<l; i++ ) {
						let br = e.vpa[i].getBoundingClientRect();
						if( e.vpa[i].vpa === false && br.top < wh && br.bottom > 0 )
							e.vpa[i].vpa = ! e.vpa[i].classList.add('nino-vpa--visible');
						else if ( e.vpa[i].vpa === true && ( br.top > wh || br.bottom < 0 ) )
							e.vpa[i].vpa = !! e.vpa[i].classList.remove('nino-vpa--visible');
						if( e.vpa[i].vpat === false && e.vpa[i].vpa === true )
							e.vpa[i].vpat = ! e.vpa[i].classList.add('nino-vpa--visible-once');
					}
				};
				for( let i=0, l=e.vpa.length; i<l; i++ ) {
					e.vpa[i].vpa = false;
					e.vpa[i].vpat = false;
				}

				ui._onResize.push( vpaScroll );
				ui._onScroll.push( vpaScroll );
			}


			/*
			 *	nino-stat-counter - counts up from 0 to data-stat-counter-to once
			 *	the element scrolls into view
			 */
			if( e.statCounter.length > 0 ) {

				for( let i=0, l=e.statCounter.length; i<l; i++ )
					e.statCounter[i].counted = false;

				/**
				 *	Animate a .nino-stat-counter's text content from 0 up to its
				 *	data-stat-counter-to value
				 *
				 *	@param		{Element}	el							Stat counter element
				 *
				 *	@return		void
				 */
				const statCounterRun = function( el ) {

					let
						to				= parseFloat( el.getAttribute('data-stat-counter-to') ?? '0' ),
						suffix		= el.getAttribute('data-stat-counter-suffix') ?? '',
						duration	= parseInt( el.getAttribute('data-stat-counter-duration') ?? '1500' ),
						decimals	= ( to % 1 !== 0 ) ? ( to.toString().split('.')[1]?.length ?? 0 ) : 0,
						start			= wn.performance.now();

					const step = function( now ) {
						let progress = Math.min( ( now - start ) / duration, 1 );
						el.textContent = ( to * progress ).toFixed( decimals ) + suffix;
						if( progress < 1 )
							wn.requestAnimationFrame( step );
					};
					wn.requestAnimationFrame( step );
				};

				/**
				 *	Trigger the count-up animation on each .nino-stat-counter as it
				 *	scrolls into view (once)
				 *
				 *	@param		{number}	wh							Viewport height
				 *
				 *	@return		void
				 */
				const statCounterScroll = function( wh ) {
					for( let i=0, l=e.statCounter.length; i<l; i++ ) {
						if( e.statCounter[i].counted === true )
							continue;
						let br = e.statCounter[i].getBoundingClientRect();
						if( br.top < wh && br.bottom > 0 ) {
							e.statCounter[i].counted = true;
							statCounterRun( e.statCounter[i] );
						}
					}
				};

				ui._onResize.push( statCounterScroll );
				ui._onScroll.push( statCounterScroll );
			}


			/*
			 *	Scroll state - toggle body.nino-scroll-atf/-btf (above/below the
			 *	fold) and body.nino-scroll-up/-down (scroll direction) so any
			 *	element (.nino-scroll-header, a back-to-top button, ...) can
			 *	react to scroll position/direction in CSS
			 */
			/**
			 *	Toggle scroll-state classes on <body> based on scroll position
			 *
			 *	@param		{number}	h								Viewport height (unused)
			 *	@param		{number}	w								Viewport width (unused)
			 *	@param		{number}	t								Scroll top
			 *	@param		{number}	l								Scroll left (unused)
			 *
			 *	@return		void
			 */
			const headerScroll = function( h, w, t, l ) {

				if( headerScroll.fixed === false && t > 96 ) {
					bd.classList.add('nino-scroll-btf');
					bd.classList.remove('nino-scroll-atf');
					headerScroll.fixed = true;
				}
				else if ( headerScroll.fixed === true && t < 96 ) {
					bd.classList.add('nino-scroll-atf');
					bd.classList.remove('nino-scroll-btf');
					headerScroll.fixed = false;
				}

				if( headerScroll.init === false )
					return;

				if( t > headerScroll.to + 30 ) {
					bd.classList.add('nino-scroll-down');
					bd.classList.remove('nino-scroll-up');
				} else if( t < headerScroll.to - 30 || t < 96 ) {
					bd.classList.add('nino-scroll-up');
					bd.classList.remove('nino-scroll-down');
				} else
					return;

				headerScroll.to = t;
			};

			headerScroll.to		= bd.scrollTop || dE.scrollTop;
			headerScroll.fixed	= false;
			headerScroll.init	= false;

			bd.classList.add('nino-scroll-atf');
			ui._onResize.push( headerScroll );
			ui._onScroll.push( headerScroll );

			// Ignore the initial resize/scroll settling before tracking direction
			setTimeout( function() { headerScroll.init = true; }, 1000 );

			/*
			 *	nino-back-to-top - visibility is handled in CSS via the
			 *	body.nino-scroll-atf/-btf classes above, JS just handles the click
			 */
			for( let i=0, l=e.backToTop.length; i<l; i++ )
				e.backToTop[i].addEventListener( 'click', function() { wn.scrollTo( { top: 0, behavior: 'smooth' } ) } );


			/*
			 *	nino-atf-arrowdown - scrolls to whichever element its own
			 *	data-arrow-target (a css selector, eg. "#next-section") points
			 *	at. The icon itself is a background-image (see Nino.css), so
			 *	using it never needs more than the one data attribute.
			 */
			for( let i=0, l=e.arrowDown.length; i<l; i++ )
				e.arrowDown[i].addEventListener( 'click', function() {
					if( ! this.dataset.arrowTarget )
						return;
					const target = dc.querySelector( this.dataset.arrowTarget );
					if( target !== null )
						target.scrollIntoView({ behavior: 'smooth' });
				} );


			/*
			 *	nino-slider
			 */
			if( e.slider.length > 0 ) {

				let
					/**
					 *	Recalculate slide widths and re-position each .nino-slider track
					 *	around its currently active slide
					 *
					 *	@return		void
					 */
					sliderResize = function() {

						for( let i=0, l=e.slider.length; i<l; i++ ) {

							let
								wW	= e.slider[i].getBoundingClientRect().width,
								mW	= e.slider[i].getAttribute('data-slider-min') ?? '0',
								sW	= e.slider[i].getAttribute('data-slider-width') ?? '75%',
								liW = ( sW.slice(-1) === '%' ) ? wW * ( sW.slice(0,-1) / 100 ) : sW.slice(0,-2);
							mW = ( mW.slice(-1) === '%' ) ? wW * ( mW.slice(0,-1) / 100 ) : mW.slice(0,-2);

							liW = Math.min( wW, Math.max(liW,mW) );

							for( let lii=0, lil = e.slider[i].lis.length; lii<lil; lii++ )
								e.slider[i].lis[lii].style.width = liW + 'px';

							e.slider[i].posLeft 						= 0 - ( ( liW * e.slider[i].pos ) - ((wW-liW)/2)  );
							e.slider[i].stage.style.left 		= e.slider[i].posLeft + 'px';
							e.slider[i].stage.style.width 	= (liW * e.slider[i].lis.length) + 'px';
							e.slider[i].style.height 				= e.slider[i].stage.getBoundingClientRect().height + 'px';
						}
					},

					/**
					 *	Move a slider to a specific slide and re-render it
					 *
					 *	@param		{Element}	wrap						Slider wrap element
					 *	@param		{number}	pos							Slide index to activate
					 *
					 *	@return		void
					 */
					sliderMove = function( wrap, pos ) {

						wrap.lis[wrap.pos].classList.remove('nino-is-active');
						wrap.ips?.[wrap.pos]?.classList.remove('nino-is-active');

						wrap.pos = pos;

						wrap.lis[wrap.pos].classList.add('nino-is-active');
						wrap.ips?.[wrap.pos]?.classList.add('nino-is-active');

						sliderResize();
					},

					/**
					 *	Advance a slider by one step (wrapping around the ends,
					 *	respecting offsetLeft/offsetRight) and re-render it
					 *
					 *	@param		{Element}	wrap						Slider wrap element
					 *	@param		{number}	dir							Direction to move, 1 or -1
					 *
					 *	@return		void
					 */
					sliderClick = function( wrap, dir ) {

						let
							oL	= parseInt( wrap.getAttribute('data-slider-offsetLeft') ?? '0' ),
							oR	= parseInt( wrap.getAttribute('data-slider-offsetRight') ?? '0' ),
							pos	= wrap.pos + dir;

						if ( pos < oL )
							pos = wrap.lis.length - 1 - oR;
						else if ( pos >= wrap.lis.length -oR )
							pos = oL;

						sliderMove( wrap, pos );
					};


				for( let i=0, l=e.slider.length, slider; i<l; i++ ) {

					slider				= e.slider[i];
					slider.stage	= slider.getElementsByTagName('ul')[0];
					slider.lis 		= slider.stage.getElementsByTagName('li');
					slider.pos 		= parseInt(slider.getAttribute( 'data-slider-pos' )) ?? Math.floor( slider.lis.length / 2 );

					let
						touchStartX = 0,
						touchStartY = 0,
						touchAxis = null;

					// Controls: prev button, dot pagination, next button - grouped
					// in one wrapper so they lay out as a single centered row
					// regardless of how wide the slider itself is
					slider.controls = document.createElement('DIV');
					slider.controls.className = 'nino-slider-controls';
					slider.appendChild( slider.controls );

					slider.prevButton = document.createElement('DIV');
					slider.prevButton.className = 'nino-slider-button prev';
					slider.prevButton.innerHTML = '‹';
					slider.prevButton.addEventListener( 'click', function(){ sliderClick( slider, -1 ) } );
					slider.controls.appendChild( slider.prevButton );

					slider.pWrap = document.createElement('UL');
					slider.pWrap.className = 'nino-slider-points';
					slider.controls.appendChild( slider.pWrap );
					slider.ips = [];
					for( let ip=0, lp=slider.lis.length; ip<lp; ip++ ) {
						slider.ips[ip] = document.createElement('LI');
						slider.ips[ip].addEventListener( 'click', function(){ sliderMove( slider, ip ) } );
						slider.pWrap.appendChild( slider.ips[ip] );
					}

					slider.nextButton = document.createElement('DIV');
					slider.nextButton.className = 'nino-slider-button next';
					slider.nextButton.innerHTML = '›';
					slider.nextButton.addEventListener( 'click', function(){ sliderClick( slider, 1 ) } );
					slider.controls.appendChild( slider.nextButton );

					// Touch swipe support
					if( ( slider.getAttribute( 'data-slider-touch' ) ?? 'true' ) !== 'false' ) {
						// Let the browser own vertical scrolling and pinch zoom. Horizontal
						// gestures remain available to the custom slider implementation.
						slider.style.touchAction = 'pan-y pinch-zoom';

						// Remember both axes, but do not enter dragging mode until the
						// visitor's intended direction is clear.
						slider.addEventListener( 'touchstart', function(e) {

							const touch = e.changedTouches[0];
							if( typeof touch === 'undefined' )
								return;
							if( typeof e.touches !== 'undefined' && e.touches.length > 1 ) {
								this.classList.remove('nino-is-touch');
								touchAxis = 'y';
								sliderResize();
								return;
							}

							touchStartX = touch.clientX;
							touchStartY = touch.clientY;
							touchAxis = null;
						}, false );

						// Prevent the browser default only after a horizontal swipe has
						// been identified. A vertical move therefore keeps scrolling the page.
						slider.addEventListener( 'touchmove', function(e) {

							const touch = e.changedTouches[0];
							if( typeof touch === 'undefined' )
								return;
							if( typeof e.touches !== 'undefined' && e.touches.length > 1 )
								return;

							const
								deltaX = touch.clientX - touchStartX,
								deltaY = touch.clientY - touchStartY;

							if( touchAxis === null )
								touchAxis = ui._sliderTouchAxis( deltaX, deltaY );

							if( touchAxis !== 'x' )
								return;

							e.preventDefault();
							this.classList.add('nino-is-touch');
							this.stage.style.left = ( this.posLeft + deltaX ) +'px';
						}, { passive : false } );

						// On swipe end, advance the slider if the horizontal distance was
						// large enough. Vertical gestures never change the active slide.
						slider.addEventListener( 'touchend', function(e) {

							this.classList.remove('nino-is-touch');

							const touch = e.changedTouches[0];
							if( typeof touch !== 'undefined' ) {
								const
									deltaX = touch.clientX - touchStartX,
									deltaY = touch.clientY - touchStartY;

								if( touchAxis === null )
									touchAxis = ui._sliderTouchAxis( deltaX, deltaY );

								if( touchAxis === 'x' && deltaX < -50 )
									sliderClick( this, 1 );
								else if( touchAxis === 'x' && deltaX > 50 )
									sliderClick( this, -1 );
							}

							sliderResize();
							touchAxis = null;

						}, false );

						// Native scrolling or pinch zoom can cancel the touch sequence.
						// Restore the centered track in that case as well.
						slider.addEventListener( 'touchcancel', function() {

							this.classList.remove('nino-is-touch');
							touchAxis = null;
							sliderResize();
						}, false );
					}

					e.slider[i].lis[e.slider[i].pos].classList.add('nino-is-active');
					e.slider[i].ips[e.slider[i].pos].classList.add('nino-is-active');
				}
				ui._onResize.push( sliderResize );
			}


			/*
			 *	nino-form
			 */
			if( e.form.length > 0 ) {

				let
					/**
					 *	Handle a .nino-form's xhr response: disable the form and
					 *	show a success/error message. Bound as `this.form` before use.
					 *
					 *	@param		{XMLHttpRequest}	xhr				Completed (normalized) xhr
					 *
					 *	@return		void
					 */
					formResponse = function( xhr ){

						this.form.classList.remove('nino-is-pending');

						const ok = ( xhr.status === 200 );

						this.form.classList.remove( ok === true ? 'nino-is-error' : 'nino-is-success' );
						this.form.classList.add( ok === true ? 'nino-is-success' : 'nino-is-error' );

						// A 400 is Form.php repeating the field validation this form
						// already ran, ie. an address the regex below accepted and
						// FILTER_VALIDATE_EMAIL did not - so it gets the field-level
						// message instead of "please try again later", which sends the
						// visitor away to wait out a problem only they can fix. Every
						// other non-200 stays generic on purpose: naming the csrf 403
						// or the honeypot's 418 tells a spam bot which check it tripped.
						// textContent, not innerHTML - these are editor-editable
						// textfills, and Modules\Jstext json-encodes them precisely so
						// they cannot become markup on the way in
						this.form.msg.textContent = ( xhr.status === 400 )
							? Nino.content.getText('/form/info/email')
							: Nino.content.getText('/form/info/'+ ( ok === true ? 'success' : 'error' ));

						// Only a delivered message locks the form down. Disabling every
						// field on any response left a visitor who mistyped their address
						// looking at a correctable error in a form they could no longer
						// correct
						if( ok === false )
							return;

						this.form.btn.disabled = true;
						for( let i = 0, l = this.form.fields.length; i<l; i++ )
							this.form.fields[i].disabled = true;
					},

					/**
					 *	Validate and submit a .nino-form via xhr; blocks submission
					 *	until all required fields are filled and any email field is valid
					 *
					 *	@param		{Event}		e								Submit event
					 *
					 *	@return		void
					 */
					formSubmit = function( e ){
						e.preventDefault();

						if( this.classList.contains('nino-is-success') === true )
							return;

						// Loop through inputs
						let error = false, data = {};
						for( let i = 0, l = this.fields.length; i<l; i++) {

							// Stored as typed. This used to strip [<>'";(){}[\]\|] from
							// every field and write the stripped value back into the
							// visible input, which silently rewrote legitimate content:
							// "mary.o'brien@example.com" was submitted - and confirmed -
							// as "mary.obrien@example.com", a different, quite possibly
							// real mailbox, so the confirmation mail and the owner's
							// reply both went to a stranger. Form.php's own comment
							// spells out why it does not escape these values either.
							// Escaping is an output concern and already handled where
							// the values become markup (Form/Newsletter escape on the
							// way into the mail templates); removing characters here
							// protected nothing and corrupted ordinary input
							data[this.fields[i].name] = this.fields[i].value;

							// Check required
							if( this.fields[i].required === true && this.fields[i].value.length === 0 )
								this.fields[i].classList.add('nino-is-error') || ( error = Nino.content.getText('/form/info/required') );

							// Check email. The local part uses the character set the html
							// spec allows for input[type=email] (and php's
							// FILTER_VALIDATE_EMAIL with it) rather than a hand-picked
							// subset: the old one had no "'" in it, so a real
							// "mary.o'brien@example.com" failed a check the browser's own
							// native validation and Form.php both pass. The
							// dot-plus-tld tail is kept - it is stricter than the spec,
							// deliberately, because a contact form typo'd to "@localhost"
							// helps nobody
							if( error === false && this.fields[i].type === 'email' && ( /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/.test(this.fields[i].value) === false ) )
								this.fields[i].classList.add('nino-is-error') || ( error = Nino.content.getText('/form/info/email') );
						}
						// Catch error
						if( error !== false )
							return this.msg.textContent = error;

						// One handler bound to this form, not a property written onto
						// the shared function object: `const uniqueResponse =
						// formResponse` was an alias, not a copy, so a second form
						// submitting while the first was still in flight overwrote
						// .form and the first response updated the wrong one.
						// sendRequest() invokes the callback via callback.call(callback,
						// ...), and a bound function's `this` survives that
						const boundResponse = formResponse.bind( { form : this } );
						this.classList.add('nino-is-pending');

						// action defaults to '/' (the contact form's own POST
						// handler) - a form targeting a different endpoint (eg.
						// the newsletter signup) sets its own action="..."
						Nino.http.sendRequest( this.getAttribute('action') || '[[/nino/dir]]/.form', 'POST', boundResponse, data );
					},
					/**
					 *	Toggle the "empty" class on a form field's wrapper,
					 *	based on the field's current value. Bound via `this` on blur/focus.
					 *
					 *	@return		void
					 */
					formInputChange = function() {
						if( this.value.length === 0 )
							this.parentNode.classList.add('empty');
					};

				for( let i=0, l=e.form.length; i<l; i++ ) {

					e.form[i].fields	= e.form[i].querySelectorAll('input, textarea, select');
					e.form[i].msg 		= e.form[i].querySelectorAll('p')[0];
					e.form[i].btn 		= e.form[i].querySelectorAll('button')[0];

					for( let ieI = 0, ieL = e.form[i].fields.length; ieI<ieL; ieI++ ) {

						if(e.form[i].fields[ieI].tagName === 'INPUT' || e.form[i].fields[ieI].tagName === 'TEXTAREA' ) {
							e.form[i].fields[ieI].addEventListener( 'blur', formInputChange );
							e.form[i].fields[ieI].addEventListener( 'focus', function(){ this.parentNode.classList.remove('empty') } );
							formInputChange.call(e.form[i].fields[ieI]);
						}

					}

					e.form[i].addEventListener( 'submit', formSubmit );
				}
			}


			/*
			 *	nino-newsletter-form - same validate-then-xhr shape as nino-form
			 *	above, kept separate because the signup is disabled after a
			 *	successful submit and shows /newsletter/info/success.
			 *	The endpoint deliberately answers the same way whether or not
			 *	the address was already on the list - anything else lets anyone
			 *	test whether a given address is subscribed - so there is no
			 *	'existing' case to distinguish here either.
			 */
			if( e.newsletterForm.length > 0 ) {

				let
					/**
					 *	Handle a .nino-newsletter-form's xhr response: disable the
					 *	form and show the matching success/error message.
					 *	Bound as `this.form` before use.
					 *
					 *	@param		{XMLHttpRequest}	xhr				Completed (normalized) xhr
					 *
					 *	@return		void
					 */
					newsletterResponse = function( xhr ){

						this.form.classList.remove('nino-is-pending');

						const ok = ( xhr.status === 200 );

						this.form.classList.remove( ok === true ? 'nino-is-error' : 'nino-is-success' );
						this.form.classList.add( ok === true ? 'nino-is-success' : 'nino-is-error' );

						// Same split as the .nino-form handler above: a 400 is the
						// address itself, anything else stays generic. The
						// "already subscribed" case deliberately answers 200 like
						// any other signup (see this block's docblock), so it
						// still never reaches here as its own outcome
						this.form.msg.textContent = ( xhr.status === 400 )
							? Nino.content.getText('/newsletter/info/email')
							: Nino.content.getText('/newsletter/info/'+ ( ok === true ? 'success' : 'error' ));

						if( ok === false )
							return;

						this.form.btn.disabled = true;
						for( let i = 0, l = this.form.fields.length; i<l; i++ )
							this.form.fields[i].disabled = true;
					},

					/**
					 *	Validate and submit a .nino-newsletter-form via xhr
					 *
					 *	@param		{Event}		e								Submit event
					 *
					 *	@return		void
					 */
					newsletterSubmit = function( e ){
						e.preventDefault();

						if( this.classList.contains('nino-is-success') === true || this.classList.contains('nino-is-existing') === true )
							return;

						let error = false, data = {};
						for( let i = 0, l = this.fields.length; i<l; i++) {

							// Stored as typed - see the .nino-form handler above for why
							// the character strip that used to sit here was removed
							data[this.fields[i].name] = this.fields[i].value;

							if( this.fields[i].required === true && this.fields[i].value.length === 0 )
								this.fields[i].classList.add('nino-is-error') || ( error = Nino.content.getText('/newsletter/info/required') );

							// Same character set as the .nino-form check above
							if( error === false && this.fields[i].type === 'email' && ( /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/.test(this.fields[i].value) === false ) )
								this.fields[i].classList.add('nino-is-error') || ( error = Nino.content.getText('/newsletter/info/email') );
						}
						if( error !== false )
							return this.msg.textContent = error;

						// Bound per submit - see the .nino-form handler above
						const boundResponse = newsletterResponse.bind( { form : this } );
						this.classList.add('nino-is-pending');

						Nino.http.sendRequest( this.getAttribute('action') || '[[/nino/dir]]/.newsletter', 'POST', boundResponse, data );
					};

				for( let i=0, l=e.newsletterForm.length; i<l; i++ ) {

					e.newsletterForm[i].fields	= e.newsletterForm[i].querySelectorAll('input, textarea, select');
					e.newsletterForm[i].msg 		= e.newsletterForm[i].querySelectorAll('p')[0];
					e.newsletterForm[i].btn 		= e.newsletterForm[i].querySelectorAll('button')[0];

					e.newsletterForm[i].addEventListener( 'submit', newsletterSubmit );
				}
			}


			/*
			 *	nino-tabs
			 */
			if( e.tabs.length > 0 ) {
				for( let i=0, l=e.tabs.length; i<l; i++ ) {

					const
						tabs		= e.tabs[i].querySelectorAll('.nino-tabs-tab'),
						panels	= e.tabs[i].querySelectorAll('.nino-tabs-panel');

					if( tabs.length === 0 || panels.length === 0 )
						continue;

					const activateTab = function( activeTab, focus ) {
						const target = activeTab.getAttribute('data-tabs-target');

						for( let x=0, xl=tabs.length; x<xl; x++ ) {
							const active = tabs[x] === activeTab;
							tabs[x].classList.toggle( 'nino-is-active', active );
							tabs[x].setAttribute( 'aria-selected', active ? 'true' : 'false' );
							tabs[x].tabIndex = active ? 0 : -1;
						}
						for( let p=0, pl=panels.length; p<pl; p++ ) {
							const active = panels[p].id === target;
							panels[p].classList.toggle( 'nino-is-active', active );
							panels[p].hidden = active === false;
							panels[p].setAttribute( 'aria-hidden', active ? 'false' : 'true' );
						}
						if( focus === true )
							activeTab.focus();
					};

					e.tabs[i].querySelector('.nino-tabs-nav')?.setAttribute( 'role', 'tablist' );

					for( let t=0, tl=tabs.length; t<tl; t++ ) {
						const target = tabs[t].getAttribute('data-tabs-target');
						const panel = dc.getElementById( target );
						tabs[t].setAttribute( 'role', 'tab' );
						if( tabs[t].id === '' )
							tabs[t].id = target+ '-trigger';
						tabs[t].setAttribute( 'aria-controls', target );
						if( panel !== null ) {
							panel.setAttribute( 'role', 'tabpanel' );
							panel.setAttribute( 'aria-labelledby', tabs[t].id );
						}
						tabs[t].addEventListener( 'click', function() { activateTab( this, false ) } );
						tabs[t].addEventListener( 'keydown', function( ev ) {
							const keys = [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ];
							if( keys.includes( ev.key ) === false )
								return;
							ev.preventDefault();
							let next = t;
							if( ev.key === 'ArrowLeft' ) next = ( t - 1 + tabs.length ) % tabs.length;
							if( ev.key === 'ArrowRight' ) next = ( t + 1 ) % tabs.length;
							if( ev.key === 'Home' ) next = 0;
							if( ev.key === 'End' ) next = tabs.length - 1;
							activateTab( tabs[next], true );
						} );
					}

					activateTab( Array.from( tabs ).find( function( tab ) { return tab.classList.contains('nino-is-active') } ) || tabs[0], false );
				}
			}


			/*
			 *	nino-filter - a category filter for a card grid. .nino-filter is
			 *	the shared root (wraps both the button row and the cards - the two
			 *	loops live in one DOM subtree so a single querySelectorAll() finds
			 *	both); a .nino-filter-btn's data-filter-value ('' means "show all")
			 *	is compared against each .nino-filter-item's data-filter-item.
			 *	Buttons are plain <button>s with aria-pressed, not a tablist/panel
			 *	pair - Tab/Enter/Space already work natively, no roving tabindex
			 *	needed. Visibility uses the native hidden attribute, same as the
			 *	nino-tabs panels above.
			 */
			if( e.filter.length > 0 ) {
				for( let i=0, l=e.filter.length; i<l; i++ ) {

					const
						buttons	= e.filter[i].querySelectorAll('.nino-filter-btn'),
						items		= e.filter[i].querySelectorAll('.nino-filter-item');

					if( buttons.length === 0 || items.length === 0 )
						continue;

					const applyFilter = function( value ) {
						for( let b=0, bl=buttons.length; b<bl; b++ ) {
							const active = buttons[b].getAttribute('data-filter-value') === value;
							buttons[b].classList.toggle( 'nino-is-active', active );
							buttons[b].setAttribute( 'aria-pressed', active ? 'true' : 'false' );
						}
						for( let x=0, xl=items.length; x<xl; x++ ) {
							const match = value === '' || items[x].getAttribute('data-filter-item') === value;
							items[x].hidden = match === false;
						}
					};

					for( let b=0, bl=buttons.length; b<bl; b++ )
						buttons[b].addEventListener( 'click', function() {
							applyFilter( this.getAttribute('data-filter-value') ?? '' );
						} );
				}
			}


			/*
			 *	nino-modal - opens/closes a native <dialog>, see nino-modal-trigger
			 *	and data-modal-target below
			 */
			if( e.modalTrigger.length > 0 ) {
				for( let i=0, l=e.modalTrigger.length; i<l; i++ )
					e.modalTrigger[i].addEventListener( 'click', function( ev ) {
						ev.preventDefault();
						const modal = dc.getElementById( this.getAttribute('data-modal-target') );
						if( modal !== null )
							modal.showModal();
					} );
			}
			if( e.modal.length > 0 ) {
				for( let i=0, l=e.modal.length; i<l; i++ )
					e.modal[i].addEventListener( 'click', function( ev ) {
						// A click on the <dialog> element itself (not its content) means
						// either the ::backdrop or the .nino-modal-close button was hit
						if( ev.target === this || ev.target.closest('.nino-modal-close') !== null )
							this.close();
					} );
			}


			/*
			 *	nino-toast-trigger - declarative wrapper around Nino.ui.toast()
			 *	for static markup (CSP blocks inline onclick, so this is the
			 *	supported way to fire a toast without writing JS)
			 */
			if( e.toastTrigger.length > 0 ) {
				for( let i=0, l=e.toastTrigger.length; i<l; i++ )
					e.toastTrigger[i].addEventListener( 'click', function() {
						ui.toast( this.getAttribute('data-toast-message'), this.getAttribute('data-toast-type') ?? undefined );
					} );
			}

			setTimeout( () => {
				ui.onResize();
				ui.onScroll();
				ui.onViewable();
			}, 200 );
		},

		/**
		 *	Show a floating, auto-dismissing toast notification
		 *
		 *	@param		{string}	message					Text to display
		 *	@param		{string}	[type]					(optional) 'success' or 'error', styles the toast
		 *
		 *	@return		void
		 */
		toast : function( message, type ) {

			let container = dc.querySelector('.nino-toast-container');
			if( container === null ) {
				container = dc.createElement('div');
				container.className = 'nino-toast-container';
				bd.appendChild( container );
			}

			const el = dc.createElement('div');
			el.className = 'nino-toast' + ( type ? ' nino-toast--' + type : '' );
			el.textContent = message;
			container.appendChild( el );

			wn.requestAnimationFrame( () => el.classList.add('nino-toast--visible') );

			setTimeout( () => {
				el.classList.remove('nino-toast--visible');
				setTimeout( () => el.remove(), 300 );
			}, 4000 );
		},

		/**
		 *	Cookie consent state, backed by Nino.cookie (Nino.js) - the
		 *	.nino-cookie-banner itself only ever calls set(), this is the
		 *	public read side a project's own analytics/tracking script gates
		 *	on before loading anything non-essential. Also dispatches
		 *	'nino:cookieconsent' on document so a script loaded later (eg.
		 *	after the banner already resolved on a previous visit) can still
		 *	pick up the current value without polling
		 */
		cookieConsent : {

			_key : 'nino_consent',

			/**
			 *	@return		{string|null}							'accepted', 'declined', or null if no choice was made yet
			 */
			get : function() {
				return Nino.cookie.get( Nino.ui.cookieConsent._key );
			},

			/**
			 *	@return		{boolean}									True once the visitor accepted
			 */
			isAccepted : function() {
				return Nino.ui.cookieConsent.get() === 'accepted';
			},

			/**
			 *	@param		{string}	value						'accepted' or 'declined'
			 *
			 *	@return		void
			 */
			set : function( value ) {
				Nino.cookie.set( Nino.ui.cookieConsent._key, value );
				dc.dispatchEvent( new CustomEvent( 'nino:cookieconsent', { detail : { consent : value } } ) );
			},

		},


		/**
		 *	Read the current viewport/scroll metrics once
		 *
		 *	@return		{number[]}								[ viewportHeight, viewportWidth, scrollTop, scrollLeft ]
		 */
		_metrics : function() {
			return [
				( dE.clientHeight || wn.innerHeight  ),
				( dE.clientWidth || wn.innerWidth ),
				( bd.scrollTop || dE.scrollTop ),
				( bd.scrollLeft || dE.scrollLeft ),
			];
		},

		/**
		 *	After the configured delay, fire all callbacks waiting for
		 *	elements to become viewable (eg. the initial hash-scroll)
		 *
		 *	@return		void
		 */
		onViewable : function() {
			if( Nino.ui._onViewable.length > 0 )
				setTimeout( function() {
					const [ wH, wW, st, sl ] = Nino.ui._metrics();
					Nino.ui._onViewable.forEach( ( cb ) => { cb( wH, wW, st, sl ) } );
				}, Nino.ui._onViewableDelay );
		},

		/**
		 *	Notify all registered resize callbacks with the current viewport/
		 *	scroll metrics. Runs once only on mobile after the first call.
		 *
		 *	@return		void
		 */
		onResize : function() {
			if( Nino.ui._onResize.length === 0 || ( Nino.client.isMobile === true && Nino.ui._onResizeDone === true ) )
				return;

			const [ wH, wW, st, sl ] = Nino.ui._metrics();
			Nino.ui._onResize.forEach( ( cb ) => { cb( wH, wW, st, sl ) } );
			Nino.ui._onResizeDone = true;
		},

		/**
		 *	Notify all registered scroll callbacks with the current viewport/
		 *	scroll metrics, throttled to one call per animation frame
		 *
		 *	@return		void
		 */
		onScroll : function() {

			if( Nino.ui._onScrollTicking === true )
				return;

			wn.requestAnimationFrame( () => {
				const [ wH, wW, st, sl ] = Nino.ui._metrics();
				Nino.ui._onScroll.forEach( (cb) => { cb( wH, wW, st, sl ) } );
				Nino.ui._onScrollTicking = false;
			});

			Nino.ui._onScrollTicking = true;
		},
	};

	Nino.events.bindCallback( 'ready', Nino.ui.onReady );
	Nino.events.bindCallback( 'scroll', Nino.ui.onScroll );
	Nino.events.bindCallback( 'resize', Nino.ui.onResize );

})(window, document, document.documentElement, document.body);
