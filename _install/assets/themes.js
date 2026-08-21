

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Steps 3, 5 and 6: pick the site's look - one tile per
 *													_install/library/themes/&lt;key&gt; unit, each with the
 *													preview image, title and description its own
 *													manifest.php declares. Applying the Theme installs its
 *													declared Design and frame defaults; after Design, Header
 *													and Footer each get their own full preview step and apply
 *													only that frame. See _install/Install.php's Themes class.
 *													Driven by the shared Back/Next bar
 *													(script.js) rather than its own save button - apply() is
 *													exposed for Next to call, not wired to a button here.
 *
 *													A tile's preview opens in a plain overlay lightbox
 *													(one reused #themes-lightbox element, see
 *													page-wizard.tpl): the tiles themselves are small
 *													enough that a theme's actual layout/type only really
 *													reads at full size.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.themes = {

		_ready 		: false,
		_themes 	: {},
		_selected : null,
		_frames 	: {},
		_framePreviewRequests : {},

		/**
		 *	Load every theme unit - each flagged with whether it's the one
		 *	currently applied - and render the picker grid
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('themes-grid');
			if( wrap === null )
				return;

			Nino.install.apiCall( 'themes/list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );

				Nino.install.themes._themes = response.themes || {};
				Nino.install.themes._frames = response.frames || {};

				// Pre-select the applied theme, falling back to the first
				// one listed - "Next" applies whatever is selected, and a
				// grid with nothing selected would just reject it
				const keys = Object.keys( Nino.install.themes._themes );
				Nino.install.themes._selected = ( response.activeTheme !== null && keys.indexOf( response.activeTheme ) !== -1 )
					? response.activeTheme
					: ( keys[0] ?? null );

				Nino.install.themes._render();
				Nino.install.themes._renderFrames( response.activeFrames || {} );
				Nino.install.themes._ready = true;
			} );
		},

		showCurrent : function() {
			if( Nino.install.themes._ready === false )
				Nino.install.themes.init();
		},

		_render : function() {

			const wrap = dc.getElementById('themes-grid');
			wrap.innerHTML = '';

			Object.keys( Nino.install.themes._themes ).forEach( function( key ) {
				wrap.appendChild( Nino.install.themes._tile( key, Nino.install.themes._themes[key] ) );
			} );
		},

		/**
		 *	One theme tile: preview image (click to enlarge), title,
		 *	description. The whole tile is the radio label, so clicking
		 *	anywhere but the preview picks the theme
		 *
		 *	@param		{string}	key
		 *	@param		{Object}	theme				{ label, description, preview }
		 *
		 *	@return		{Element}
		 */
		_tile : function( key, theme ) {

			const tile = dc.createElement('label');
			tile.className = 'install-theme-tile';
			tile.classList.toggle( 'install-theme-tile--active', key === Nino.install.themes._selected );

			const input = dc.createElement('input');
			input.type = 'radio';
			input.name = 'install-theme';
			input.value = key;
			input.checked = key === Nino.install.themes._selected;
			input.addEventListener( 'change', function() {
				Nino.install.themes._selected = key;
				Array.prototype.slice.call( dc.querySelectorAll('.install-theme-tile') ).forEach( function( el ) {
					el.classList.toggle( 'install-theme-tile--active', el.contains( input ) );
				} );
				Nino.install.themes._adoptTheme( key );
			} );
			tile.appendChild( input );

			if( theme.preview ) {

				const preview = dc.createElement('img');
				preview.className = 'install-theme-preview';
				preview.src = theme.preview;
				preview.alt = theme.label+ ' preview';
				preview.loading = 'lazy';

				// The tile is a <label>, so a plain click here would also
				// toggle the radio - enlarging a preview is deliberately not
				// the same gesture as picking the theme it belongs to
				preview.addEventListener( 'click', function( event ) {
					event.preventDefault();
					Nino.install.themes._openLightbox( theme );
				} );

				tile.appendChild( preview );
			}

			const body = dc.createElement('div');
			body.className = 'install-theme-body';

			const title = dc.createElement('span');
			title.className = 'install-theme-title';
			title.textContent = theme.label || key;
			body.appendChild( title );

			if( theme.description ) {
				const description = dc.createElement('span');
				description.className = 'install-theme-description';
				description.textContent = theme.description;
				body.appendChild( description );
			}

			tile.appendChild( body );

			return tile;
		},

		/**
		 *	Fill the two later frame steps. A theme names the header/footer it
		 *	drew against, so what is already applied wins on a revisit and the
		 *	theme's own declaration only fills in on a fresh install.
		 *
		 *	@param		{Object}	active			{ header, footer } as currently installed
		 *
		 *	@return		void
		 */
		_renderFrames : function( active ) {

			const theme = Nino.install.themes._themes[Nino.install.themes._selected] || {};

			[ 'header', 'footer' ].forEach( function( kind ) {

				const select 		= dc.getElementById('themes-frame-'+ kind);
				const panel 		= dc.getElementById('themes-frame-'+ kind+ '-panel');
				const unavailable = dc.getElementById('themes-frame-'+ kind+ '-unavailable');
				const keys 			= Nino.install.themes._frames[kind] || [];

				if( select === null )
					return;

				if( panel !== null )
					panel.classList.toggle( 'install-hidden', keys.length === 0 );

				if( unavailable !== null )
					unavailable.classList.toggle( 'install-hidden', keys.length > 0 );

				select.innerHTML = '';

				keys.forEach( function( key ) {
					const option = dc.createElement('option');
					option.value = key;
					option.textContent = key;
					select.appendChild( option );
				} );

				const wanted = [ active[kind], theme[kind] ].filter( function( candidate ) {
					return candidate && keys.indexOf( candidate ) !== -1;
				} );

				select.value = wanted.length > 0 ? wanted[0] : ( keys[0] || '' );

				// Bound once: _renderFrames() can run again after a reload, and a
				// second listener would fire a second render for
				// every change from then on
				if( select.dataset === undefined || select.dataset.framePreviewBound !== '1' ) {
					if( select.dataset !== undefined )
						select.dataset.framePreviewBound = '1';
					select.addEventListener( 'change', function() {
						Nino.install.themes._renderFramePreview( kind );
					} );
				}

				Nino.install.themes._renderFramePreview( kind );
			} );
		},

		/**
		 *	Refresh one of the dedicated frame steps when it becomes visible.
		 *	The Design step immediately before it may have changed the token
		 *	settings since themes/list first rendered the hidden previews.
		 *
		 *	@param		{string}	kind				'header' or 'footer'
		 *
		 *	@return		void
		 */
		showFrame : function( kind ) {

			if( Nino.install.themes._ready === false ) {
				Nino.install.themes.init();
				return;
			}

			Nino.install.themes._renderFramePreview( kind );
		},

		/**
		 *	Show one frame as it will really look. The document comes from the
		 *	server rather than being assembled here: it needs the library's
		 *	templates, its text files and the theme's stylesheet, none of which
		 *	the browser has, and most of which are not in the project yet at
		 *	this point in the wizard.
		 *
		 *	Into a sandboxed iframe rather than into the page: a frame's
		 *	stylesheet styles bare element selectors and sets things like
		 *	'body main { padding-top }', which would land on the installer's
		 *	own shell.
		 *
		 *	@param		{string}	kind				'header' or 'footer'
		 *
		 *	@return		void
		 */
		_renderFramePreview : function( kind ) {

			const select = dc.getElementById('themes-frame-'+ kind);
			const view 	 = dc.getElementById('themes-frame-'+ kind+ '-preview');

			if( select === null || view === null )
				return;

			const requestId = ( Nino.install.themes._framePreviewRequests[kind] || 0 ) + 1;
			Nino.install.themes._framePreviewRequests[kind] = requestId;

			if( select.value === '' ) {
				view.removeAttribute('srcdoc');
				return;
			}

			const wanted = select.value;
			const payload = {
				kind 	 : kind,
				frame  : wanted,
				theme  : Nino.install.themes._selected,
			};

			// Header/Footer follow Design in the wizard, so their preview must
			// use what the controls just settled on rather than the theme's
			// manifest defaults that happened to be stored when Themes opened.
			if( Nino.install.design !== undefined && Nino.install.design._settings !== null && typeof Nino.install.design._settings === 'object' )
				payload.design = Nino.install.design._settings;

			Nino.install.apiCall( 'themes/frame', payload, function( status, response ) {

				// The initial hidden render and the refresh when its own step opens
				// can ask for the same frame with different Design values. Selection
				// alone cannot distinguish those responses, so only the newest
				// request for this kind may paint.
				if( Nino.install.themes._framePreviewRequests[kind] !== requestId || select.value !== wanted )
					return;

				if( status !== 200 || response === null || typeof response.html !== 'string' ) {
					view.removeAttribute('srcdoc');
					return;
				}

				view.srcdoc = response.html;
			} );
		},

		/**
		 *	Picking a theme adopts what that theme was drawn against - its
		 *	frames. A theme tile is a promise about how the site will look,
		 *	and it can only keep it if choosing it also moves the frames its
		 *	preview was rendered with
		 *
		 *	@param		{string}	key
		 *
		 *	@return		void
		 */
		_adoptTheme : function( key ) {

			const theme = Nino.install.themes._themes[key] || {};

			[ 'header', 'footer' ].forEach( function( kind ) {
				const select = dc.getElementById('themes-frame-'+ kind);
				const keys 	 = Nino.install.themes._frames[kind] || [];
				if( select !== null && theme[kind] && keys.indexOf( theme[kind] ) !== -1 && select.value !== theme[kind] ) {
					select.value = theme[kind];
					// Setting .value does not fire 'change', so the preview
					// would keep showing the frame the previous theme named
					Nino.install.themes._renderFramePreview( kind );
				}
			} );
		},

		/**
		 *	@param		{Object}	theme				{ label, description, preview }
		 *
		 *	@return		void
		 */
		_openLightbox : function( theme ) {

			const box = dc.getElementById('themes-lightbox');
			if( box === null )
				return;

			dc.getElementById('themes-lightbox-image').src 					= theme.preview;
			dc.getElementById('themes-lightbox-image').alt 					= theme.label+ ' preview';
			dc.getElementById('themes-lightbox-caption').textContent = theme.label;

			box.classList.remove('install-hidden');
		},

		closeLightbox : function() {

			const box = dc.getElementById('themes-lightbox');
			if( box === null )
				return;

			box.classList.add('install-hidden');
			dc.getElementById('themes-lightbox-image').src = '';
		},

		/**
		 *	Send the picked theme to themes/apply - called by the shared
		 *	Next button, not by a button of its own
		 *
		 *	@param		{Function}	callback			Called with ( success )
		 *
		 *	@return		void
		 */
		apply : function( callback ) {

			const msg = dc.getElementById('themes-msg');

			if( Nino.install.themes._ready !== true ) {
				msg.textContent = 'Themes are still loading.';
				callback( false );
				return;
			}

			if( Nino.install.themes._selected === null ) {
				msg.textContent = 'Select a theme first.';
				callback( false );
				return;
			}

			msg.textContent = 'Applying …';

			const payload = { theme : Nino.install.themes._selected };

			Nino.install.apiCall( 'themes/apply', payload, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to apply.' );
					callback( false );
					return;
				}

				const theme = Nino.install.themes._themes[response.theme] || {};
				msg.textContent = 'Applied theme: '+ ( theme.label || response.theme )+ '.';

				callback( true );
			} );
		},

		/**
		 *	Apply one frame from its own wizard step. This endpoint deliberately
		 *	does not reapply the theme or Design: the operator has already
		 *	settled both in the preceding steps.
		 *
		 *	@param		{string}	kind				'header' or 'footer'
		 *	@param		{Function}	callback		Called with ( success )
		 *
		 *	@return		void
		 */
		applyFrame : function( kind, callback ) {

			const msg 	 = dc.getElementById(kind+ '-msg');
			const select = dc.getElementById('themes-frame-'+ kind);

			if( [ 'header', 'footer' ].indexOf( kind ) === -1 ) {
				callback( false );
				return;
			}

			if( Nino.install.themes._ready !== true ) {
				if( msg !== null ) msg.textContent = 'Frame variants are still loading.';
				callback( false );
				return;
			}

			// A delivery may intentionally ship one of the two kinds without
			// variants. Its pane says so and remains a valid, skippable step.
			if( select === null || select.value === '' ) {
				callback( true );
				return;
			}

			if( msg !== null ) msg.textContent = 'Applying …';

			Nino.install.apiCall( 'themes/frame/apply', { kind : kind, frame : select.value }, function( status, response ) {

				if( status !== 200 || response === null ) {
					if( msg !== null ) msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to apply.' );
					callback( false );
					return;
				}

				if( typeof response.frame === 'string' )
					select.value = response.frame;

				if( msg !== null ) msg.textContent = 'Applied '+ kind+ ': '+ select.value+ '.';
				callback( true );
			} );
		},
	};

	// Header and Footer are independent wizard steps, but their mechanics are
	// the same and stay next to the theme/frame state they share.
	[ 'header', 'footer' ].forEach( function( kind ) {
		Nino.install[kind] = {
			showCurrent : function() { Nino.install.themes.showFrame( kind ); },
			apply : function( callback ) { Nino.install.themes.applyFrame( kind, callback ); },
		};
	} );

	Nino.events.bindCallback( 'ready', function() {

		const box = dc.getElementById('themes-lightbox');
		if( box === null )
			return;

		box.addEventListener( 'click', Nino.install.themes.closeLightbox );
		dc.addEventListener( 'keydown', function( event ) {
			if( event.key === 'Escape' )
				Nino.install.themes.closeLightbox();
		} );
	} );

})(window, document, document.documentElement, document.body);
