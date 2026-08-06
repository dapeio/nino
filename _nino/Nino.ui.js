

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
					arrowDown	: dc.querySelectorAll( '.ui-atf-arrowdown' ),
					autoheight	: dc.querySelectorAll( '.ui-autoheight' ),
					backToTop	: dc.querySelectorAll( '.js-back-to-top' ),
					cookieBanner	: dc.querySelectorAll( '.js-cookie-banner' ),
					cover			: dc.querySelectorAll( '.js-cover' ),
					// .js-newsletter-form opts out - it keeps .ui-form only for the
					// shared success/error/pending styling, its own submit handler
					// below binds it separately (needs its own "already subscribed"
					// vs "new signup" messaging, not just a generic success/error pair)
					form			: dc.querySelectorAll( '.ui-form:not(.js-newsletter-form)' ),
					modal			: dc.querySelectorAll( '.js-modal' ),
					modalTrigger	: dc.querySelectorAll( '.js-modal-trigger' ),
					newsletterForm	: dc.querySelectorAll( '.js-newsletter-form' ),
					parallex	: dc.querySelectorAll( '.js-parallex' ),
					preloader	: dc.querySelectorAll( '.js-preloader' ),
					slider		: dc.querySelectorAll( '.js-slider' ),
					statCounter	: dc.querySelectorAll( '.js-stat-counter' ),
					tabs			: dc.querySelectorAll( '.js-tabs' ),
					toastTrigger	: dc.querySelectorAll( '.js-toast-trigger' ),
					vpa				: dc.querySelectorAll( '.js-vpa' ),
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
			 *	ui-autoheight - equalize the height of every element sharing the
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
			 *	js-cover
			 */
			if( e.cover.length > 0 ) {
				// Resize each .js-cover element to fill its configured percentage of the viewport
				ui._onResize.push( function( wH, wW ) {
					for( let i=0, l=e.cover.length; i<l; i++ ) {
						let
							w 			= e.cover[i].getAttribute( 'data-cover-width' ) ?? 100,
							h 			= e.cover[i].getAttribute( 'data-cover-height' ) ?? 90,
							padH		= ( parseInt(window.getComputedStyle(e.cover[i]).getPropertyValue('margin-top').slice(0,-2)) ?? 0 ) + ( parseInt(window.getComputedStyle(e.cover[i]).getPropertyValue('margin-bottom').slice(0,-2)) ?? 0 ),
							padW		= ( parseInt(window.getComputedStyle(e.cover[i]).getPropertyValue('margin-left').slice(0,-2)) ?? 0 ) + ( parseInt(window.getComputedStyle(e.cover[i]).getPropertyValue('margin-right').slice(0,-2)) ?? 0 ),
							wrapH		= e.cover[i].querySelector('div').offsetHeight ?? 0;

						if( h !== null ) e.cover[i].style.height = Math.max( ( ( wH / 100 * h ) - padH ), 50 + wrapH ) + 'px';
						if( w !== null ) e.cover[i].style.width = ( ( wW / 100 * w ) - padW ) + 'px';
					}
				} );
			}
			/*
			 *	js-parallex
			 */
			if( e.parallex.length > 0 ) {
				for( let i=0, l=e.parallex.length; i<l; i++ )
					e.parallex[i].img = e.parallex[i].querySelector('img');

				/**
				 *	Apply a parallax offset to .js-parallex images based on scroll position
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
			 *	js-preloader
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
			 *	js-cookie-banner - reveal only if no consent choice was made
			 *	yet (see Nino.ui.cookieConsent below). Buttons carry the
			 *	choice in data-cookie-consent rather than two separate click
			 *	handlers, since accept/decline only differ in which value
			 *	gets stored
			 */
			if( e.cookieBanner.length > 0 && ui.cookieConsent.get() === null ) {
				const banner = e.cookieBanner[0];
				wn.requestAnimationFrame( () => banner.classList.add('js-cookie-banner--visible') );
				banner.querySelectorAll('[data-cookie-consent]').forEach( function( btn ) {
					btn.addEventListener( 'click', function() {
						ui.cookieConsent.set( this.getAttribute('data-cookie-consent') );
						banner.classList.remove('js-cookie-banner--visible');
					} );
				} );
			}


			/*
			 *	js-vpa
			 */
			if( e.vpa.length > 0 ) {
				for( let i=0, ad, al, l=e.vpa.length; i<l; i++ ) {
					if( ad = e.vpa[i].getAttribute('data-vpa-delay') )
						e.vpa[i].style.transitionDelay = ad;
					if( al = e.vpa[i].getAttribute('data-vpa-duration') )
						e.vpa[i].style.transitionDuration = al;
				}

				/**
				 *	Toggle "visible"/"visible-once" classes on .js-vpa elements
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
							e.vpa[i].vpa = ! e.vpa[i].classList.add('js-vpa--visible');
						else if ( e.vpa[i].vpa === true && ( br.top > wh || br.bottom < 0 ) )
							e.vpa[i].vpa = !! e.vpa[i].classList.remove('js-vpa--visible');
						if( e.vpa[i].vpat === false && e.vpa[i].vpa === true )
							e.vpa[i].vpat = ! e.vpa[i].classList.add('js-vpa--visible-once');
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
			 *	js-stat-counter - counts up from 0 to data-stat-counter-to once
			 *	the element scrolls into view
			 */
			if( e.statCounter.length > 0 ) {

				for( let i=0, l=e.statCounter.length; i<l; i++ )
					e.statCounter[i].counted = false;

				/**
				 *	Animate a .js-stat-counter's text content from 0 up to its
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
				 *	Trigger the count-up animation on each .js-stat-counter as it
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
			 *	Scroll state - toggle body.js-scroll-atf/-btf (above/below the
			 *	fold) and body.js-scroll-up/-down (scroll direction) so any
			 *	element (.js-scroll-header, a back-to-top button, ...) can
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
					bd.classList.add('js-scroll-btf');
					bd.classList.remove('js-scroll-atf');
					headerScroll.fixed = true;
				}
				else if ( headerScroll.fixed === true && t < 96 ) {
					bd.classList.add('js-scroll-atf');
					bd.classList.remove('js-scroll-btf');
					headerScroll.fixed = false;
				}

				if( headerScroll.init === false )
					return;

				if( t > headerScroll.to + 30 ) {
					bd.classList.add('js-scroll-down');
					bd.classList.remove('js-scroll-up');
				} else if( t < headerScroll.to - 30 || t < 96 ) {
					bd.classList.add('js-scroll-up');
					bd.classList.remove('js-scroll-down');
				} else
					return;

				headerScroll.to = t;
			};

			headerScroll.to		= bd.scrollTop || dE.scrollTop;
			headerScroll.fixed	= false;
			headerScroll.init	= false;

			bd.classList.add('js-scroll-atf');
			ui._onResize.push( headerScroll );
			ui._onScroll.push( headerScroll );

			// Ignore the initial resize/scroll settling before tracking direction
			setTimeout( function() { headerScroll.init = true; }, 1000 );

			/*
			 *	js-back-to-top - visibility is handled in CSS via the
			 *	body.js-scroll-atf/-btf classes above, JS just handles the click
			 */
			for( let i=0, l=e.backToTop.length; i<l; i++ )
				e.backToTop[i].addEventListener( 'click', function() { wn.scrollTo( { top: 0, behavior: 'smooth' } ) } );


			/*
			 *	ui-atf-arrowdown - scrolls to whichever element its own
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
			 *	js-slider
			 */
			if( e.slider.length > 0 ) {

				let
					/**
					 *	Recalculate slide widths and re-position each .js-slider track
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

						wrap.lis[wrap.pos].classList.remove('active');
						wrap.ips?.[wrap.pos]?.classList.remove('active');

						wrap.pos = pos;

						wrap.lis[wrap.pos].classList.add('active');
						wrap.ips?.[wrap.pos]?.classList.add('active');

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


				for( let i=0, l=e.slider.length, touchStartX=0, touchEndX=0, slider; i<l; i++ ) {

					slider				= e.slider[i];
					slider.stage	= slider.getElementsByTagName('ul')[0];
					slider.lis 		= slider.stage.getElementsByTagName('li');
					slider.pos 		= parseInt(slider.getAttribute( 'data-slider-pos' )) ?? Math.floor( slider.lis.length / 2 );

					// Controls: prev button, dot pagination, next button - grouped
					// in one wrapper so they lay out as a single centered row
					// regardless of how wide the slider itself is
					slider.controls = document.createElement('DIV');
					slider.controls.className = 'js-slider-controls';
					slider.appendChild( slider.controls );

					slider.prevButton = document.createElement('DIV');
					slider.prevButton.className = 'js-slider-button prev';
					slider.prevButton.innerHTML = '‹';
					slider.prevButton.addEventListener( 'click', function(){ sliderClick( slider, -1 ) } );
					slider.controls.appendChild( slider.prevButton );

					slider.pWrap = document.createElement('UL');
					slider.pWrap.className = 'js-slider-points';
					slider.controls.appendChild( slider.pWrap );
					slider.ips = [];
					for( let ip=0, lp=slider.lis.length; ip<lp; ip++ ) {
						slider.ips[ip] = document.createElement('LI');
						slider.ips[ip].addEventListener( 'click', function(){ sliderMove( slider, ip ) } );
						slider.pWrap.appendChild( slider.ips[ip] );
					}

					slider.nextButton = document.createElement('DIV');
					slider.nextButton.className = 'js-slider-button next';
					slider.nextButton.innerHTML = '›';
					slider.nextButton.addEventListener( 'click', function(){ sliderClick( slider, 1 ) } );
					slider.controls.appendChild( slider.nextButton );

					// Touch swipe support
					if( ( slider.getAttribute( 'data-slider-touch' ) ?? 'true' ) !== 'false' ) {
						// Drag the track along with the finger while swiping
						slider.addEventListener( 'touchmove', function(e) { e.preventDefault(); this.stage.style.left = ( this.posLeft - (touchStartX - e.changedTouches[0].screenX))+'px' } );
						// Remember the swipe start position
						slider.addEventListener( 'touchstart', function(e) { this.classList.add('touch'); touchStartX = e.changedTouches[0].screenX }, false );
						// On swipe end, advance the slider if the swipe distance was large enough
						slider.addEventListener( 'touchend', function(e) {

							this.classList.remove('touch');

							touchEndX = e.changedTouches[0].screenX;

							if ( touchEndX + 50 < touchStartX )
								sliderClick( slider, 1);
							else if ( touchEndX - 50 > touchStartX )
								sliderClick( slider, -1);

							sliderResize();

						}, false);
					}

					e.slider[i].lis[e.slider[i].pos].classList.add('active');
					e.slider[i].ips[e.slider[i].pos].classList.add('active');
				}
				ui._onResize.push( sliderResize );
			}


			/*
			 *	ui-form
			 */
			if( e.form.length > 0 ) {

				let
					/**
					 *	Handle a .ui-form's xhr response: disable the form and
					 *	show a success/error message. Bound as `this.form` before use.
					 *
					 *	@param		{XMLHttpRequest}	xhr				Completed (normalized) xhr
					 *
					 *	@return		void
					 */
					formResponse = function( xhr ){

						this.form.classList.remove('pending');

						let result = ( xhr.status === 200 ) ? 'success' : 'error';

						this.form.classList.add(result);
						this.form.msg.innerHTML = Nino.content.getText('/form/info/'+ result);

						this.form.btn.disabled = true;
						for( let i = 0, l = this.form.fields.length; i<l; i++ )
							this.form.fields[i].disabled = true;
					},

					/**
					 *	Validate and submit a .ui-form via xhr; blocks submission
					 *	until all required fields are filled and any email field is valid
					 *
					 *	@param		{Event}		e								Submit event
					 *
					 *	@return		void
					 */
					formSubmit = function( e ){
						e.preventDefault();

						if( this.classList.contains('success') === true )
							return;

						// Loop through inputs
						let error = false, data = {};
						for( let i = 0, l = this.fields.length; i<l; i++) {

							// Store value
							data[this.fields[i].name] = this.fields[i].value = this.fields[i].value.replace( /[<>'";(){}[\]\\|]/g, '' );

							// Check required
							if( this.fields[i].required === true && this.fields[i].value.length === 0 )
								this.fields[i].classList.add('error') || ( error = Nino.content.getText('/form/info/required') );

							// Check email
							if( error === false && this.fields[i].type === 'email' && ( /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(this.fields[i].value) === false ) )
								this.fields[i].classList.add('error') || ( error = Nino.content.getText('/form/info/email') );
						}
						// Catch error
						if( error !== false )
							return this.msg.innerHTML = error;

						// Send form
						const uniqueResponse = formResponse;
						uniqueResponse.form = this;
						this.classList.add('pending');

						// action defaults to '/' (the contact form's own POST
						// handler) - a form targeting a different endpoint (eg.
						// the newsletter signup) sets its own action="..."
						Nino.http.sendRequest( this.getAttribute('action') || '/.form', 'POST', formResponse, data );
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
			 *	js-newsletter-form - same validate-then-xhr shape as ui-form
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
					 *	Handle a .js-newsletter-form's xhr response: disable the
					 *	form and show the matching success/error message.
					 *	Bound as `this.form` before use.
					 *
					 *	@param		{XMLHttpRequest}	xhr				Completed (normalized) xhr
					 *
					 *	@return		void
					 */
					newsletterResponse = function( xhr ){

						this.form.classList.remove('pending');

						let result = ( xhr.status === 200 ) ? 'success' : 'error';

						this.form.classList.add(result);
						this.form.msg.innerHTML = Nino.content.getText('/newsletter/info/'+ result);

						this.form.btn.disabled = true;
						for( let i = 0, l = this.form.fields.length; i<l; i++ )
							this.form.fields[i].disabled = true;
					},

					/**
					 *	Validate and submit a .js-newsletter-form via xhr
					 *
					 *	@param		{Event}		e								Submit event
					 *
					 *	@return		void
					 */
					newsletterSubmit = function( e ){
						e.preventDefault();

						if( this.classList.contains('success') === true || this.classList.contains('existing') === true )
							return;

						let error = false, data = {};
						for( let i = 0, l = this.fields.length; i<l; i++) {

							data[this.fields[i].name] = this.fields[i].value = this.fields[i].value.replace( /[<>'";(){}[\]\\|]/g, '' );

							if( this.fields[i].required === true && this.fields[i].value.length === 0 )
								this.fields[i].classList.add('error') || ( error = Nino.content.getText('/newsletter/info/required') );

							if( error === false && this.fields[i].type === 'email' && ( /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(this.fields[i].value) === false ) )
								this.fields[i].classList.add('error') || ( error = Nino.content.getText('/newsletter/info/email') );
						}
						if( error !== false )
							return this.msg.innerHTML = error;

						const uniqueResponse = newsletterResponse;
						uniqueResponse.form = this;
						this.classList.add('pending');

						Nino.http.sendRequest( this.getAttribute('action') || '/.newsletter', 'POST', newsletterResponse, data );
					};

				for( let i=0, l=e.newsletterForm.length; i<l; i++ ) {

					e.newsletterForm[i].fields	= e.newsletterForm[i].querySelectorAll('input, textarea, select');
					e.newsletterForm[i].msg 		= e.newsletterForm[i].querySelectorAll('p')[0];
					e.newsletterForm[i].btn 		= e.newsletterForm[i].querySelectorAll('button')[0];

					e.newsletterForm[i].addEventListener( 'submit', newsletterSubmit );
				}
			}


			/*
			 *	js-tabs
			 */
			if( e.tabs.length > 0 ) {
				for( let i=0, l=e.tabs.length; i<l; i++ ) {

					const
						tabs		= e.tabs[i].querySelectorAll('.js-tabs-tab'),
						panels	= e.tabs[i].querySelectorAll('.js-tabs-panel');

					for( let t=0, tl=tabs.length; t<tl; t++ )
						tabs[t].addEventListener( 'click', function() {

							const target = this.getAttribute('data-tabs-target');

							for( let x=0, xl=tabs.length; x<xl; x++ )
								tabs[x].classList.toggle( 'active', tabs[x] === this );
							for( let p=0, pl=panels.length; p<pl; p++ )
								panels[p].classList.toggle( 'active', panels[p].id === target );
						} );
				}
			}


			/*
			 *	js-modal - opens/closes a native <dialog>, see js-modal-trigger
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
						// either the ::backdrop or the .js-modal-close button was hit
						if( ev.target === this || ev.target.closest('.js-modal-close') !== null )
							this.close();
					} );
			}


			/*
			 *	js-toast-trigger - declarative wrapper around Nino.ui.toast()
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

			let container = dc.querySelector('.js-toast-container');
			if( container === null ) {
				container = dc.createElement('div');
				container.className = 'js-toast-container';
				bd.appendChild( container );
			}

			const el = dc.createElement('div');
			el.className = 'js-toast' + ( type ? ' js-toast--' + type : '' );
			el.textContent = message;
			container.appendChild( el );

			wn.requestAnimationFrame( () => el.classList.add('js-toast--visible') );

			setTimeout( () => {
				el.classList.remove('js-toast--visible');
				setTimeout( () => el.remove(), 300 );
			}, 4000 );
		},

		/**
		 *	Cookie consent state, backed by Nino.cookie (Nino.js) - the
		 *	.js-cookie-banner itself only ever calls set(), this is the
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
