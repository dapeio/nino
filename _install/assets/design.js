

/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 4: the values every stylesheet reads from - a brand
 *													colour, an optional second accent, and the two steps
 *													that decide how far each is pushed. /_design generates
 *													the tokens from them and this step writes the
 *													stylesheet, see _install/Install.php's Themes class.
 *
 *													It opens on whatever the previous step just installed:
 *													applying a theme also applies the design its manifest
 *													declares, so this step starts from the look the theme's
 *													preview promised rather than from a neutral default.
 *
 *													Every value shown here is asked for rather than
 *													computed - design/preview returns the real palette. The
 *													colour maths lives in /_design, and a second copy of it
 *													in javascript would be one to keep in step.
 *
 *													Driven by the shared Back/Next bar (script.js) rather
 *													than its own save button - apply() is exposed for Next
 *													to call, not wired to a button here.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.design = {

		_ready 		: false,
		// Null means "this delivery has no /_design", which is a different
		// thing from "not loaded yet" - the step steps aside rather than
		// rendering controls that would post into nothing
		_settings : null,
		_choices 	: {},
		_bound 		: false,
		_timer 		: null,

		/**
		 *	Read what the previous step installed and render the controls on it
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('design-controls');
			if( wrap === null )
				return;

			Nino.install.apiCall( 'design/read', {}, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );

				Nino.install.design._settings = response.settings || null;
				Nino.install.design._choices 	= response.choices || {};
				Nino.install.design._ready 		= true;

				Nino.install.design._render();
			} );
		},

		/**
		 *	Re-read on every visit rather than once: the operator may have gone
		 *	back and picked a different theme, and this step's whole starting
		 *	point is what that theme declared
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.install.design.init();
		},

		_render : function() {

			const controls 		= dc.getElementById('design-controls');
			const unavailable = dc.getElementById('design-unavailable');

			if( Nino.install.design._settings === null ) {
				controls.classList.add('install-hidden');
				if( unavailable !== null )
					unavailable.classList.remove('install-hidden');
				return;
			}

			if( unavailable !== null )
				unavailable.classList.add('install-hidden');

			controls.classList.remove('install-hidden');

			Object.keys( Nino.install.design._choices ).forEach( function( knob ) {
				Nino.install.design._renderChoice( knob );
			} );

			[ 'primary', 'secondary' ].forEach( function( role ) {
				Nino.install.design._bindColor( role );
			} );

			Nino.install.design._bound = true;
			Nino.install.design._writeInputs();
			Nino.install.design._schedulePreview();
		},

		/**
		 *	One select, filled from the server's vocabulary. Every knob the
		 *	server names gets its control here if the markup has one, so
		 *	adding a knob is a change in /_design plus one <select> - not a
		 *	second list to keep in step
		 *
		 *	@param		{string}	knob
		 *
		 *	@return		void
		 */
		_renderChoice : function( knob ) {

			const select = dc.getElementById('themes-design-'+ knob);
			if( select === null )
				return;

			select.innerHTML = '';

			( Nino.install.design._choices[knob] || [] ).forEach( function( value ) {
				const option = dc.createElement('option');
				option.value = value;
				option.textContent = value.charAt(0).toUpperCase()+ value.slice(1);
				select.appendChild( option );
			} );

			select.value = Nino.install.design._settings[knob] || '';

			if( Nino.install.design._bound === true )
				return;

			select.addEventListener( 'change', function() {
				Nino.install.design._settings[knob] = select.value;
				Nino.install.design._schedulePreview();
			} );
		},

		/**
		 *	One colour role, driven by two inputs that have to stay in step: a
		 *	native picker and a hex field. The field is the one that can hold
		 *	an incomplete value while it is being typed, so it only reports up
		 *	once what it holds is a full #rrggbb - otherwise every keystroke
		 *	past the '#' would send a different, half-finished colour
		 *
		 *	@param		{string}	role				'primary' or 'secondary'
		 *
		 *	@return		void
		 */
		_bindColor : function( role ) {

			if( Nino.install.design._bound === true )
				return;

			const swatch = dc.getElementById('themes-design-'+ role);
			const hex 	 = dc.getElementById('themes-design-'+ role+ '-hex');

			if( swatch === null || hex === null )
				return;

			swatch.addEventListener( 'input', function() {
				Nino.install.design._settings[role] = swatch.value;
				hex.value = swatch.value;
				Nino.install.design._schedulePreview();
			} );

			hex.addEventListener( 'input', function() {

				const value = hex.value.trim();

				// Secondary is legitimately empty - it then follows Primary,
				// which is a different thing from "not filled in yet"
				if( value === '' && role === 'secondary' ) {
					Nino.install.design._settings[role] = '';
					Nino.install.design._schedulePreview();
					return;
				}

				if( /^#[0-9a-fA-F]{6}$/.test( value ) === false )
					return;

				Nino.install.design._settings[role] = value.toLowerCase();
				swatch.value = value;
				Nino.install.design._schedulePreview();
			} );
		},

		/**
		 *	Push the settings back into the controls - used on every visit,
		 *	where the values may have changed without anyone touching the
		 *	inputs that show them
		 *
		 *	@return		void
		 */
		_writeInputs : function() {

			if( Nino.install.design._settings === null )
				return;

			[ 'primary', 'secondary' ].forEach( function( role ) {

				const swatch = dc.getElementById('themes-design-'+ role);
				const hex 	 = dc.getElementById('themes-design-'+ role+ '-hex');
				const value  = Nino.install.design._settings[role] || '';

				if( hex !== null )
					hex.value = value;

				// A colour input has no empty state, so an unset Secondary
				// shows the colour it would fall back to rather than black
				if( swatch !== null )
					swatch.value = value !== '' ? value : ( Nino.install.design._settings.primary || '#000000' );
			} );
		},

		/**
		 *	Ask the server what the current settings produce and paint it.
		 *	Debounced because a colour input fires on every pointer move
		 *
		 *	@return		void
		 */
		_schedulePreview : function() {

			if( Nino.install.design._timer !== null )
				wn.clearTimeout( Nino.install.design._timer );

			Nino.install.design._timer = wn.setTimeout( function() {

				Nino.install.design._timer = null;

				if( Nino.install.design._settings === null )
					return;

				Nino.install.apiCall( 'design/preview', { design : Nino.install.design._settings }, function( status, response ) {

					if( status !== 200 || response === null )
						return;

					if( response.palette )
						Nino.install.design._paintSurfaces( response.palette.light || {} );

					if( response.raster )
						Nino.install.design._paintSizes( response.raster );
				} );
			}, 150 );
		},

		/**
		 *	One chip per surface, each showing the pair it really is - the
		 *	generated background with the generated text colour on it. A row
		 *	of backgrounds alone would look fine in every setting, including
		 *	the ones that fail
		 *
		 *	@param		{Object}	palette			surface -> { bg, on, ... }
		 *
		 *	@return		void
		 */
		_paintSurfaces : function( palette ) {

			const strip = dc.getElementById('themes-design-preview');
			if( strip === null )
				return;

			strip.innerHTML = '';

			Object.keys( palette ).forEach( function( surface ) {

				const values = palette[surface] || {};
				const chip 	 = dc.createElement('span');

				chip.className = 'install-theme-chip';
				chip.style.backgroundColor = values.bg || 'transparent';
				chip.style.color = values.on || 'inherit';
				chip.style.borderColor = values.border || 'transparent';
				chip.textContent = surface;

				strip.appendChild( chip );
			} );
		},

		/**
		 *	The size raster, drawn at the sizes it really produces. Listing the
		 *	rem values would be quicker to build and useless to look at - what
		 *	a scale does is only visible as type next to type.
		 *
		 *	@param		{Object}	raster			{ text, space, radius, lineHeight }
		 *
		 *	@return		void
		 */
		_paintSizes : function( raster ) {

			const wrapVolume = dc.getElementById('themes-design-volume-preview');
			if( wrapVolume === null )
				return;

			wrapVolume.innerHTML = '';

			// Type: the two ends of the scale plus body copy, which is the one
			// step a reader actually spends time in
			const type = dc.createElement('div');
			type.className = 'install-design-type';
			type.style.lineHeight = raster.lineHeight || '1.5';

			[ [ 6, 'Headline' ], [ 4, 'Subhead' ], [ 1, 'Body copy at its generated size' ] ].forEach( function( pair ) {
				const line = dc.createElement('p');
				line.className = 'install-design-type-line';
				line.style.fontSize = ( raster.text || {} )[pair[0]] || '1rem';
				line.textContent = pair[1];
				type.appendChild( line );
			} );

			wrapVolume.appendChild( type );

			// One bar per spacing step, at a fixed height: a gap is a distance,
			// not an area, and drawing it square makes the top of the scale
			// three times taller than anything it is telling you

			const wrapSpacing = dc.getElementById('themes-design-spacing-preview');
			if( wrapSpacing === null )
				return;

			wrapSpacing.innerHTML = '';

			const spacing = dc.createElement('div');
			spacing.className = 'install-design-spacing';

			Object.keys( raster.space || {} ).forEach( function( step ) {

				const row = dc.createElement('span');
				row.className = 'install-design-space-row';

				const bar = dc.createElement('span');
				bar.className = 'install-design-space';
				bar.style.width = raster.space[step];

				const label = dc.createElement('small');
				label.textContent = raster.space[step];

				row.appendChild( bar );
				row.appendChild( label );
				spacing.appendChild( row );
			} );

			wrapSpacing.appendChild( spacing );

			// Radii are areas, so these stay boxes - a corner only reads
			// against the two edges it joins
			const wrapShaping = dc.getElementById('themes-design-shaping-preview');
			if( wrapShaping === null )
				return;

			wrapShaping.innerHTML = '';
			const corners = dc.createElement('div');
			corners.className = 'install-design-corners';

			Object.keys( raster.radius || {} ).forEach( function( step ) {
				const corner = dc.createElement('span');
				corner.className = 'install-design-radius';
				corner.style.borderRadius = raster.radius[step];
				corner.title = 'radius-'+ step+ ': '+ raster.radius[step];
				corners.appendChild( corner );
			} );

			wrapShaping.appendChild( corners );
		},

		/**
		 *	Write the stylesheet - called by the shared Next button, not by a
		 *	button of its own. A delivery without /_design has nothing to write
		 *	and advances rather than blocking
		 *
		 *	@param		{Function}	callback			Called with ( success )
		 *
		 *	@return		void
		 */
		apply : function( callback ) {

			const msg = dc.getElementById('design-msg');

			if( Nino.install.design._ready !== true ) {
				msg.textContent = 'The design is still loading.';
				callback( false );
				return;
			}

			if( Nino.install.design._settings === null ) {
				callback( true );
				return;
			}

			msg.textContent = 'Writing …';

			Nino.install.apiCall( 'design/apply', { design : Nino.install.design._settings }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Failed to write the design.' );
					callback( false );
					return;
				}

				msg.textContent = 'Design written.';
				callback( true );
			} );
		},
	};

})(window, document, document.documentElement, document.body);
