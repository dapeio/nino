

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 3: pick the site's look - one tile per
 *													_install/library/themes/&lt;key&gt; unit, each with the
 *													preview image, title and description its own
 *													manifest.php declares, plus which header/footer frame
 *													the site uses - both go out in one themes/apply post,
 *													see _install/Install.php's Themes class. The colours a
 *													theme was drawn with are the next step's (design.js);
 *													applying a theme installs the design its manifest
 *													declares, so that step opens on exactly what this one
 *													just chose. Driven by the shared Back/Next bar
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
		 *	Fill the two frame selects. A theme names the header/footer it was
		 *	drawn against, so what is already applied wins on a revisit and the
		 *	theme's own declaration only fills in on a fresh install
		 *
		 *	@param		{Object}	active			{ header, footer } as currently installed
		 *
		 *	@return		void
		 */
		_renderFrames : function( active ) {

			const panel = dc.getElementById('themes-frames');
			if( panel === null )
				return;

			const theme = Nino.install.themes._themes[Nino.install.themes._selected] || {};
			let offered = false;

			[ 'header', 'footer' ].forEach( function( kind ) {

				const select = dc.getElementById('themes-frame-'+ kind);
				const keys 	 = Nino.install.themes._frames[kind] || [];

				if( select === null )
					return;

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
				offered = offered || keys.length > 0;

				// Bound once: _renderFrames() runs again on every visit to the
				// step, and a second listener would fire a second render for
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

			panel.classList.toggle( 'install-hidden', offered === false );
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

			if( select === null || view === null || select.value === '' )
				return;

			const wanted = select.value;

			Nino.install.apiCall( 'themes/frame', {
				kind 	 : kind,
				frame  : wanted,
				theme  : Nino.install.themes._selected,
			}, function( status, response ) {

				// Two answers can be in flight after a quick double change;
				// only the one for what is selected now may paint
				if( select.value !== wanted )
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

			// One post, because the two are one decision: a theme whose frames
			// failed to apply is not the look that was picked. The colours are
			// the next step's - applying a theme installs the design it
			// declares, and Design opens on exactly that
			const payload = { theme : Nino.install.themes._selected };

			[ 'header', 'footer' ].forEach( function( kind ) {
				const select = dc.getElementById('themes-frame-'+ kind);
				if( select !== null && select.value !== '' )
					payload[kind] = select.value;
			} );

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
	};

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
