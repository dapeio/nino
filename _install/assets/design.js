

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
		_groups 	: {},
		// Which half of the design is on screen. Not a design setting, so it
		// never reaches _settings and never reaches the config
		_mode 		: 'light',
		_rowsBound	: false,
		PREVIEW_WIDTH : 2400,
		_refit : null,
		_example 	: '',
		_bound 		: false,
		_timer 		: null,
		// What the theme declared, kept beside what is on screen: the two are
		// what "changed" means in a wizard that has not written anything yet
		_baseline : null,
		// The theme the baseline was read for. A re-read is owed when that
		// changes and at no other time - see showCurrent()
		_theme 		: null,
		// The field elements the settings are rendered into, by key
		_fields 	: {},

		/**
		 *	Read what the previous step installed and render the controls on it
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('design-controls');
			if( wrap === null )
				return;

			const previous = Nino.install.design._theme;

			Nino.install.apiCall( 'design/read', {}, function( status, response ) {

				if( status !== 200 || response === null )
					return Nino.install.showError( wrap, status, response );

				Nino.install.design._settings = response.settings || null;
				Nino.install.design._baseline = response.settings ? JSON.parse( JSON.stringify( response.settings ) ) : null;
				Nino.install.design._choices 	= response.choices || {};
				Nino.install.design._groups 	= response.groups || {};
				Nino.install.design._example 	= response.example || '';
				Nino.install.design._theme 		= Nino.install.design._selectedTheme();
				Nino.install.design._ready 		= true;

				Nino.install.design._render();

				// A reload the operator did not ask for is a reload they get
				// told about - it is their own values that were replaced
				const msg = dc.getElementById('design-msg');
				if( msg !== null && previous !== null && previous !== Nino.install.design._theme )
					msg.textContent = 'The theme changed, so these values were read from it again.';
			} );
		},

		/**
		 *	Which theme the previous step has settled on, or null where the
		 *	step is not there to ask
		 *
		 *	@return		{?string}
		 */
		_selectedTheme : function() {

			const themes = wn.Nino.install.themes;

			return themes && typeof themes._selected !== 'undefined' ? themes._selected : null;
		},

		/**
		 *	Re-read when the theme changed, and only then.
		 *
		 *	This step's starting point is what the theme declared, so a trip
		 *	back to pick a different one has to be followed here. It used to
		 *	re-read on every visit for that reason - which also meant a trip
		 *	back to correct a typo two steps earlier threw away every setting
		 *	made here, without a word. The theme is the thing that changed the
		 *	answer, so the theme is what the re-read hangs on.
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.install.design._ready === true && Nino.install.design._selectedTheme() === Nino.install.design._theme ) {
				// The step was off screen while the window may have changed
				// size, and a scaled frame only knows what its port measured
				if( typeof Nino.install.design._refit === 'function' )
					Nino.install.design._refit();

				return;
			}

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

			Nino.install.design._fields = {};
			Nino.install.design._renderKnobs();

			[ 'primary', 'secondary' ].forEach( function( role ) {
				Nino.install.design._bindColor( role );
			} );

			Nino.install.design._bindRows();
			Nino.install.design._bound = true;
			Nino.install.design._writeInputs();
			Nino.install.design._markChanges();
			Nino.install.design._paintExample( Nino.install.design._example );
		},

		/**
		 *	One control per knob the server named, in the panel it named.
		 *
		 *	Nothing here knows what a knob is called or how many there are, so
		 *	a knob added to /_design's Tokens appears in this step without the
		 *	wizard template gaining a line - which is what keeps the installer
		 *	and /_design showing the same set rather than drifting apart.
		 *
		 *	@return		void
		 */
		_renderKnobs : function() {

			Object.keys( Nino.install.design._groups ).forEach( function( group ) {
				const panel = dc.getElementById('themes-design-knobs-'+ group);
				if( panel !== null )
					panel.innerHTML = '';
			} );

			Object.keys( Nino.install.design._choices ).forEach( function( knob ) {

				const meta = Nino.install.design._choices[knob] || {};
				const panel = dc.getElementById('themes-design-knobs-'+ ( meta.group || '' ));

				if( panel === null )
					return;

				const field = Nino.adminUi.selectField( {
					key       : knob,
					className : 'install-theme-field',
					label     : meta.label,
					note      : meta.note,
					hint      : meta.hint,
					options   : Nino.install.design._knobOptions( meta ),
					value     : Nino.install.design._settings[knob],
					onChange  : function( value ) {
						Nino.install.design._settings[knob] = parseInt( value, 10 );
						Nino.install.design._changed();
					},
				} );

				Nino.install.design._fields[knob] = field;
				panel.appendChild( field );
			} );
		},

		/**
		 *	One option per position: the number is what gets stored and the
		 *	name is what the operator reads
		 *
		 *	@param		{Object}	meta			{ min, max, steps }
		 *
		 *	@return		{Array}							[ { value, label } ]
		 */
		_knobOptions : function( meta ) {

			const options = [];

			for( let step = meta.min; step <= meta.max; step++ )
				options.push( { value : step, label : ( meta.steps || [] )[step - meta.min] || String( step ) } );

			return options;
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

			// The colour rows are markup rather than rendered, so they are
			// registered here rather than at render time
			Nino.install.design._fields[role] = dc.getElementById('themes-design-'+ role+ '-field');

			if( Nino.install.design._bound === true )
				return;

			const swatch = dc.getElementById('themes-design-'+ role);
			const hex 	 = dc.getElementById('themes-design-'+ role+ '-hex');

			if( swatch === null || hex === null )
				return;

			swatch.addEventListener( 'input', function() {
				Nino.install.design._settings[role] = swatch.value;
				hex.value = swatch.value;
				Nino.install.design._hexState( hex, true );
				Nino.install.design._changed();
			} );

			hex.addEventListener( 'input', function() {

				const value = hex.value.trim();

				// Secondary is legitimately empty - it then follows Primary,
				// which is a different thing from "not filled in yet"
				if( value === '' && role === 'secondary' ) {
					Nino.install.design._hexState( hex, true );
					Nino.install.design._settings[role] = '';
					Nino.install.design._changed();
					return;
				}

				/*	Half a colour cannot be sent - but saying nothing about it
					reads as "accepted", and the field is left showing a value
					the design does not have	*/
				if( /^#[0-9a-fA-F]{6}$/.test( value ) === false ) {
					Nino.install.design._hexState( hex, false );
					return;
				}

				Nino.install.design._hexState( hex, true );
				Nino.install.design._settings[role] = value.toLowerCase();
				swatch.value = value;
				Nino.install.design._changed();
			} );

			// Leaving an incomplete value behind would leave the field lying
			// about the setting for as long as the step is open
			hex.addEventListener( 'blur', function() {

				const held = Nino.install.design._settings === null ? '' : ( Nino.install.design._settings[role] || '' );

				if( hex.value.trim() === held )
					return;

				hex.value = held;
				Nino.install.design._hexState( hex, true );
			} );
		},

		/**
		 *	Say whether a colour field holds a colour - in the field for the
		 *	eye, in aria-invalid for a screen reader, and in words beneath,
		 *	because a red border alone is a colour telling a colour-blind
		 *	operator that their colour is wrong
		 *
		 *	@param		{Element}	input			The hex field
		 *	@param		{boolean}	valid			Whether it holds a full #rrggbb
		 *
		 *	@return		void
		 */
		_hexState : function( input, valid ) {

			const was = input.getAttribute('aria-invalid') === 'true';
			const now = valid !== true;

			// The attribute is the state and the styling hook both - a class
			// beside it would be a second place to keep the same fact
			input.setAttribute( 'aria-invalid', now === true ? 'true' : 'false' );

			if( now === was )
				return;

			const msg = dc.getElementById('design-msg');

			if( msg === null )
				return;

			msg.textContent = now === true ? 'A colour needs a full #rrggbb - this one is kept out of the design until it is.' : '';
			msg.classList.toggle( 'is-error', now );
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

				if( hex !== null ) {
					hex.value = value;
					Nino.install.design._hexState( hex, true );
				}

				// A colour input has no empty state, so an unset Secondary
				// shows the colour it would fall back to rather than black
				if( swatch !== null )
					swatch.value = value !== '' ? value : ( Nino.install.design._settings.primary || '#000000' );
			} );

			/*	The knobs too, for the reset - a select does not read its own
				model. Over the choices rather than over every field: a label
				hands out its own .control, and for the colour rows that is the
				swatch, which is not what is being written here	*/
			Object.keys( Nino.install.design._choices ).forEach( function( knob ) {

				const field = Nino.install.design._fields[knob];

				if( field !== null && typeof field !== 'undefined' && field.control !== null && typeof field.control !== 'undefined' )
					field.control.value = String( Nino.install.design._settings[knob] );
			} );
		},

		/**
		 *	Which settings differ from the ones the theme declared.
		 *
		 *	Compared as text: a knob comes back from a select as a number and a
		 *	colour as a string, and "3" and 3 are the same decision however the
		 *	control that produced it feels about it
		 *
		 *	@return		{Array}						The keys that differ
		 */
		_changes : function() {

			const changed = [];

			if( Nino.install.design._settings === null || Nino.install.design._baseline === null )
				return changed;

			Object.keys( Nino.install.design._settings ).forEach( function( key ) {
				if( String( Nino.install.design._settings[key] ) !== String( Nino.install.design._baseline[key] ) )
					changed.push( key );
			} );

			return changed;
		},

		/**
		 *	What a setting was when the theme handed it over, in the words its
		 *	own control uses
		 *
		 *	@param		{string}	key				A settings key
		 *
		 *	@return		{string}
		 */
		_baseLabel : function( key ) {

			const base = Nino.install.design._baseline[key];
			const meta = Nino.install.design._choices[key];

			if( meta !== null && typeof meta !== 'undefined' )
				return ( meta.steps || [] )[base - meta.min] || String( base );

			return base === '' || base === null || typeof base === 'undefined' ? 'none' : String( base );
		},

		/**
		 *	Repaint the marks and the reset button from the current comparison.
		 *
		 *	Nothing is written until Next, so there is no "saved" to compare
		 *	against here - the theme's own values are the position the operator
		 *	started from and the one they can ask to be put back
		 *
		 *	@return		void
		 */
		_markChanges : function() {

			const changed = Nino.install.design._changes();

			Object.keys( Nino.install.design._fields ).forEach( function( key ) {
				Nino.adminUi.fieldChange( Nino.install.design._fields[key],
					changed.indexOf( key ) === -1 ? '' : 'nino-admin-changed' );
			} );

			const reset = dc.getElementById('design-reset');

			if( reset !== null )
				reset.hidden = changed.length === 0;
		},

		/**
		 *	One setting moved: say so, and ask for the picture it produces
		 *
		 *	@return		void
		 */
		_changed : function() {
			Nino.install.design._markChanges();
			Nino.install.design._schedulePreview();
		},

		/**
		 *	Put every setting back where the theme had it
		 *
		 *	@return		void
		 */
		_reset : function() {

			if( Nino.install.design._baseline === null || Nino.install.design._changes().length === 0 )
				return;

			Nino.install.design._settings = JSON.parse( JSON.stringify( Nino.install.design._baseline ) );
			Nino.install.design._writeInputs();

			const msg = dc.getElementById('design-msg');

			if( msg !== null )
				msg.textContent = 'Back to the theme\u2019s own values.';

			Nino.install.design._changed();
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

					Nino.install.design._paintExample( response.example );
				}, { mode : Nino.install.design._mode } );
			}, 150 );
		},

		/**
		 *	Show the page these settings produce.
		 *
		 *	The document is built by /_design - one answer to "what does this
		 *	look like", borrowed rather than reimplemented here. srcdoc rather
		 *	than a src, so nothing is written to disk to preview something the
		 *	operator has not pressed Next on yet.
		 *
		 *	@param		{string}	html			A complete document
		 *
		 *	@return		void
		 */
		_paintExample : function( html ) {

			const frame = dc.getElementById('themes-design-example');

			if( frame === null || typeof html !== 'string' || html === '' )
				return;

			frame.srcdoc = html;
		},

		/**
		 *	The two button rows this step has: which set of settings is on
		 *	screen, and which mode the example is shown in.
		 *
		 *	Bound once. The step is re-entered on every visit and both rows are
		 *	markup rather than rendered, so a second call would stack a second
		 *	listener on every button.
		 *
		 *	@return		void
		 */
		_bindRows : function() {

			if( Nino.install.design._rowsBound === true )
				return;

			Nino.install.design._rowsBound = true;

			/*	Which settings are on screen. Hidden rather than unmounted, so
				the panel the operator is not looking at keeps its controls,
				their values and their listeners.

				The opening state is set here rather than left to the template's
				hidden attribute. The markup still carries it, so the panel is
				right before this runs and right without javascript at all - but
				two places deciding one thing is two places that can disagree,
				and the one that loses is the one nobody looks at.	*/
			const show = function( group ) {

				[ 'colour', 'raster' ].forEach( function( candidate ) {

					const panel = dc.getElementById('themes-design-panel-'+ candidate);

					if( panel !== null )
						panel.hidden = candidate !== group;
				} );
			};

			Nino.adminUi.buttonRow( {
				colour : dc.getElementById('themes-design-tab-colour'),
				raster : dc.getElementById('themes-design-tab-raster'),
			}, 'colour', show, 'aria-selected' );

			show( 'colour' );

			// Which mode the example is shown in. A server question: the frame is
			// sandboxed, so it has an opaque origin and nothing out here can reach
			// in to stamp data-nino-mode on it
			/*	The page renders at a desktop's layout width and is scaled into
				the panel. Without it the panel is narrower than the narrowest
				content ceiling Width offers, so all three of its settings
				produced the same picture	*/
			const frame = dc.getElementById('themes-design-example');
			const port = dc.getElementById('themes-design-example-port');

			if( frame !== null && port !== null )
				Nino.install.design._refit = Nino.adminUi.scaleFrame( frame, port, Nino.install.design.PREVIEW_WIDTH );

			Nino.adminUi.buttonRow( {
				light : dc.getElementById('themes-design-mode-light'),
				dark : dc.getElementById('themes-design-mode-dark'),
			}, Nino.install.design._mode, function( mode ) {
				Nino.install.design._mode = mode;
				Nino.install.design._schedulePreview();
			} );

			const reset = dc.getElementById('design-reset');

			if( reset !== null )
				reset.addEventListener( 'click', Nino.install.design._reset );
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
