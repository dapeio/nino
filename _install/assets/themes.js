

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 3: pick the site's look - one tile per
 *													_install/library/themes/&lt;key&gt; unit, each with the
 *													preview image, title and description its own
 *													manifest.php declares. Applying copies that theme's
 *													files into the project and bundles its stylesheet, see
 *													_install/Install.php's Themes class. Driven by the
 *													shared Back/Next bar (script.js) rather than its own
 *													save button - apply() is exposed for Next to call, not
 *													wired to a button here.
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

				// Pre-select the applied theme, falling back to the first
				// one listed - "Next" applies whatever is selected, and a
				// grid with nothing selected would just reject it
				const keys = Object.keys( Nino.install.themes._themes );
				Nino.install.themes._selected = ( response.activeTheme !== null && keys.indexOf( response.activeTheme ) !== -1 )
					? response.activeTheme
					: ( keys[0] ?? null );

				Nino.install.themes._render();
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

			Nino.install.apiCall( 'themes/apply', { theme : Nino.install.themes._selected }, function( status, response ) {

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
