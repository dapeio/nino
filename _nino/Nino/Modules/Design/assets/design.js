/**
 *	Nino						A compact filesystembased php framework
 *	Design/assets/design.js	The workbench's Design panel: four independent
 *							appearance editors - Theme, Design, Header and Footer.
 *
 *	Theme establishes the complete manifest baseline. Design then writes only
 *	the generated tokens; Header and Footer each replace only their own frame.
 *	The colour maths and frame rendering stay server-side, where the files and
 *	the contrast implementation already live.
 */

( function(wn,dc) {

	wn.Nino = wn.Nino || {};
	wn.Nino.admin = wn.Nino.admin || {};

	// Attached to the workbench's namespace under the panel's uri, which is
	// how the shell finds showCurrent() when the tab is selected (see
	// _admin/assets/script.js)
	Nino.admin.design = {

		TABS : [ 'theme', 'design', 'header', 'footer' ],
		// Fill keys - what the one action button says on each tab (see
		// text/<locale>.php beside this module)
		ACTION_LABELS : {
			theme 	: '/_admin/design/label/apply-theme',
			design 	: '/_admin/design/label/save-design',
			header 	: '/_admin/design/label/apply-header',
			footer 	: '/_admin/design/label/apply-footer',
		},

		_tab : 'theme',
		_messages : { theme : '', design : '', header : '', footer : '' },
		_errors : { theme : false, design : false, header : false, footer : false },

		_appearanceReady : false,
		_themes : {},
		_selectedTheme : null,
		_activeTheme : null,
		_frames : {},
		_activeFrames : {},
		_framePreviewRequests : {},

		_designReady : false,
		_designSettings : null,
		_designStored : null,
		// Which mode the example is shown in. Not a design setting - it is
		// which half of a design that always has both halves is on screen
		_mode : 'light',
		_rowsBound : false,
		/*	The layout the example is rendered at, and then scaled into the
			panel. A desktop's width, because a page never meets its own layout
			limits in a box half a pane wide.

			The height is off. It fixes both dimensions so the whole page fits
			the port at once, which is worth having - but only for a page that
			is roughly as tall as the panel is deep. The example is a full site
			now, seven bands at the section default spacing, and it measures
			4700-6200px depending on the theme: fitted whole into a 1100x665
			panel that draws at 11%, and at the 2000-2200 it was set to it was
			simply cut in half. So the page is given the panel's height and
			scrolls, the way the site it stands for does.

			Put a height back the moment the example is short enough for one -
			the mechanism is in Nino.adminUi.scaleFrame() and nothing else has
			to change.	*/
		PREVIEW_WIDTH : 2400,
		PREVIEW_HEIGHT : 0,
		_refit : null,
		_designChoices : {},
		_designBound : false,
		_designTimer : null,
		/*	The field elements the settings are rendered into, by key - the
			knobs plus the two colour rows the template carries. Kept so a
			value can be written back into its control without hunting for it,
			and so every saved-value mark can be repainted in one pass	*/
		_designFields : {},

		init : function() {

			const wrap = dc.getElementById('theme-page-wrap');
			if( wrap === null )
				return;

			Nino.admin.design.TABS.forEach( function( tab ) {
				const link = dc.getElementById('theme-nav-'+ tab);
				if( link !== null )
					link.addEventListener( 'click', function( event ) {
						event.preventDefault();
						Nino.admin.design.selectTab( tab );
					} );
			} );

			const save = dc.getElementById('theme-action-save');
			if( save !== null )
				save.addEventListener( 'click', Nino.admin.design._applyCurrent );

			const revert = dc.getElementById('theme-design-reset');
			if( revert !== null )
				revert.addEventListener( 'click', Nino.admin.design._revertDesign );

			/*	The theme preview overlay: a click anywhere on it or Escape
				closes it, which are the two gestures an overlay with no
				chrome of its own can be expected to answer	*/
			const lightbox = dc.getElementById('theme-lightbox');
			if( lightbox !== null ) {
				lightbox.addEventListener( 'click', Nino.admin.design.closeLightbox );
				dc.addEventListener( 'keydown', function( event ) {
					if( event.key === 'Escape' )
						Nino.admin.design.closeLightbox();
				} );
			}

			/*	The one loss this pane cannot undo afterwards. Everything else
				it does is a request the operator can repeat; a closed tab with
				unsaved settings in it is gone	*/
			if( typeof wn.addEventListener === 'function' )
				wn.addEventListener( 'beforeunload', function( event ) {
					if( Nino.admin.design._designChanges().length === 0 )
						return;

					event.preventDefault();
					event.returnValue = '';
				} );

			// The editor to open with comes from the hash (#design/header),
			// the way every panel deep-links (see Nino.admin.router). The
			// loading itself waits for showCurrent(): a panel nobody opens
			// costs no request
			const requested = Nino.admin.router.current();
			if( requested.panel === 'design' && Nino.admin.design.TABS.indexOf( requested.parts[0] ) !== -1 )
				Nino.admin.design._tab = requested.parts[0];
		},

		/**
		 *	Called by the shell when the panel's tab is selected (see
		 *	script.js's selectTab()): open whichever editor is current,
		 *	loading it the first time
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.admin.design.selectTab( Nino.admin.design._tab );
		},

		selectTab : function( tab ) {

			if( Nino.admin.design.TABS.indexOf( tab ) === -1 )
				return;

			Nino.admin.design._tab = tab;
			Nino.admin.router.set( 'design', [ tab ] );

			const wrap = dc.getElementById('theme-page-wrap');
			Nino.admin.design.TABS.forEach( function( current ) {
				if( wrap !== null )
					wrap.classList.toggle( 'show-'+ current, current === tab );

				const link = dc.getElementById('theme-nav-'+ current);
				if( link !== null ) {
					link.classList.toggle( 'is-active', current === tab );
					link.setAttribute( 'aria-selected', current === tab ? 'true' : 'false' );
				}
			} );

			Nino.admin.design._updateAction();

			if( tab === 'design' ) {
				Nino.admin.design._loadDesign( false );
				return;
			}

			Nino.admin.design._loadAppearance( false, function() {
				if( tab === 'header' || tab === 'footer' )
					Nino.admin.design._renderFramePreview( tab );
			} );
		},

		/**
		 *	The previewed mode rides beside the settings rather than inside
		 *	them: it is not a design value and must never reach a save, but the
		 *	server has to know it to stamp the example's document
		 */
		_call : function( action, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, {
				action : action,
				data : JSON.stringify( payload || {} ),
				mode : Nino.admin.design._mode,
			} );
		},

		_loadAppearance : function( force, callback ) {

			if( force !== true && Nino.admin.design._appearanceReady === true ) {
				if( typeof callback === 'function' ) callback();
				return;
			}

			Nino.admin.design._call( 'appearance/read', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.admin.design._appearanceReady = false;
					Nino.admin.design._themes = {};
					Nino.admin.design._frames = {};
					Nino.admin.design._renderThemes();
					Nino.admin.design._renderFrames();
					Nino.admin.design._setStatus( Nino.admin.design._tab, Nino.admin.design._errorText( status, response ), true );
					return;
				}

				Nino.admin.design._themes = response.themes || {};
				Nino.admin.design._frames = response.frames || {};
				Nino.admin.design._activeTheme = response.activeTheme || null;
				Nino.admin.design._activeFrames = response.activeFrames || {};

				const keys = Object.keys( Nino.admin.design._themes );
				if( keys.indexOf( Nino.admin.design._activeTheme ) !== -1 )
					Nino.admin.design._selectedTheme = Nino.admin.design._activeTheme;
				else if( keys.indexOf( Nino.admin.design._selectedTheme ) === -1 )
					Nino.admin.design._selectedTheme = keys[0] || null;

				Nino.admin.design._appearanceReady = true;
				Nino.admin.design._renderThemes();
				Nino.admin.design._renderFrames();
				Nino.admin.design._updateAction();

				if( typeof callback === 'function' ) callback();
			} );
		},

		_renderThemes : function() {

			const grid = dc.getElementById('theme-grid');
			const empty = dc.getElementById('theme-empty');
			if( grid === null )
				return;

			grid.innerHTML = '';
			const keys = Object.keys( Nino.admin.design._themes );

			if( empty !== null )
				empty.classList.toggle( 'theme-hidden', keys.length > 0 );

			keys.forEach( function( key ) {

				const data = Nino.admin.design._themes[key] || {};
				const tile = dc.createElement('label');
				tile.className = 'theme-tile';
				tile.classList.toggle( 'theme-tile--selected', key === Nino.admin.design._selectedTheme );
				tile.classList.toggle( 'theme-tile--active', key === Nino.admin.design._activeTheme );

				const input = dc.createElement('input');
				input.type = 'radio';
				input.name = 'theme-choice';
				input.value = key;
				input.checked = key === Nino.admin.design._selectedTheme;
				input.addEventListener( 'change', function() {
					Nino.admin.design._selectedTheme = key;
					Nino.admin.design._renderThemes();
					Nino.admin.design._updateAction();
				} );
				tile.appendChild( input );

				if( data.preview ) {
					const preview = dc.createElement('img');
					preview.className = 'theme-tile-preview';
					preview.src = data.preview;
					preview.alt = Nino.content.getText('/_admin/design/label/preview').replace( '%s', data.label || key );
					preview.loading = 'lazy';

					/*	The tile is a <label>, so a plain click here would also
						toggle the radio - enlarging a preview is deliberately not
						the same gesture as picking the theme it belongs to. A
						tile is 12rem wide, which is where a theme's layout and
						type stop being legible at all	*/
					preview.addEventListener( 'click', function( event ) {
						event.preventDefault();
						Nino.admin.design._openLightbox( key, data );
					} );

					tile.appendChild( preview );
				}

				const body = dc.createElement('span');
				body.className = 'theme-tile-body';

				const title = dc.createElement('span');
				title.className = 'theme-tile-title';
				title.textContent = data.label || key;
				body.appendChild( title );

				if( key === Nino.admin.design._activeTheme ) {
					const active = dc.createElement('small');
					active.className = 'theme-tile-state';
					active.textContent = Nino.content.getText('/_admin/design/label/active');
					body.appendChild( active );
				}

				if( data.description ) {
					const description = dc.createElement('span');
					description.className = 'theme-tile-description';
					description.textContent = data.description;
					body.appendChild( description );
				}

				tile.appendChild( body );
				grid.appendChild( tile );
			} );
		},

		/**
		 *	Show one theme's preview at a size its layout and type can be read
		 *	at. One reused overlay filled here rather than one per tile - see
		 *	page-index.tpl's #theme-lightbox
		 *
		 *	@param		{string}	key				The theme's catalogue key
		 *	@param		{Object}	data			{ label, description, preview }
		 *
		 *	@return		void
		 */
		_openLightbox : function( key, data ) {

			const box = dc.getElementById('theme-lightbox');
			if( box === null )
				return;

			const image = dc.getElementById('theme-lightbox-image');
			const caption = dc.getElementById('theme-lightbox-caption');
			const label = data.label || key;

			if( image !== null ) {
				image.src = data.preview;
				image.alt = Nino.content.getText('/_admin/design/label/preview').replace( '%s', label );
			}

			if( caption !== null )
				caption.textContent = label;

			box.classList.remove('theme-hidden');
		},

		closeLightbox : function() {

			const box = dc.getElementById('theme-lightbox');
			if( box === null )
				return;

			box.classList.add('theme-hidden');

			// The overlay stays in the document, so the image it last showed
			// would stay decoded and would flash on the next open
			const image = dc.getElementById('theme-lightbox-image');
			if( image !== null )
				image.src = '';
		},

		_renderFrames : function() {

			[ 'header', 'footer' ].forEach( function( kind ) {

				const select = dc.getElementById('theme-frame-'+ kind);
				const panel = dc.getElementById('theme-frame-'+ kind+ '-panel');
				const empty = dc.getElementById('theme-frame-'+ kind+ '-empty');
				const keys = Nino.admin.design._frames[kind] || [];

				if( select === null )
					return;

				if( panel !== null ) panel.classList.toggle( 'theme-hidden', keys.length === 0 );
				if( empty !== null ) empty.classList.toggle( 'theme-hidden', keys.length > 0 );

				select.innerHTML = '';
				keys.forEach( function( key ) {
					const option = dc.createElement('option');
					option.value = key;
					option.textContent = key;
					select.appendChild( option );
				} );

				const theme = Nino.admin.design._themes[Nino.admin.design._activeTheme] || {};
				const wanted = [ Nino.admin.design._activeFrames[kind], theme[kind] ].filter( function( key ) {
					return key && keys.indexOf( key ) !== -1;
				} );
				select.value = wanted[0] || keys[0] || '';

				if( select.dataset === undefined || select.dataset.themeFrameBound !== '1' ) {
					if( select.dataset !== undefined ) select.dataset.themeFrameBound = '1';
					select.addEventListener( 'change', function() {
						Nino.admin.design._renderFramePreview( kind );
						Nino.admin.design._updateAction();
					} );
				}
			} );
		},

		_renderFramePreview : function( kind ) {

			const select = dc.getElementById('theme-frame-'+ kind);
			const view = dc.getElementById('theme-frame-'+ kind+ '-preview');
			if( select === null || view === null || select.value === '' ) {
				if( view !== null ) view.removeAttribute('srcdoc');
				return;
			}

			const wanted = select.value;
			const requestId = ( Nino.admin.design._framePreviewRequests[kind] || 0 ) + 1;
			Nino.admin.design._framePreviewRequests[kind] = requestId;

			const payload = {
				kind : kind,
				frame : wanted,
				theme : Nino.admin.design._activeTheme,
			};
			if( Nino.admin.design._designStored !== null )
				payload.design = Nino.admin.design._designStored;

			Nino.admin.design._call( 'frame/preview', payload, function( status, response ) {

				if( Nino.admin.design._framePreviewRequests[kind] !== requestId || select.value !== wanted )
					return;

				if( status !== 200 || response === null || typeof response.html !== 'string' ) {
					view.removeAttribute('srcdoc');
					if( Nino.admin.design._tab === kind )
						Nino.admin.design._setStatus( kind, Nino.admin.design._errorText( status, response ), true );
					return;
				}

				view.srcdoc = response.html;
			} );
		},

		/**
		 *	Read the stored Design, once.
		 *
		 *	Guarded the same way _loadAppearance() is, and for a reason this
		 *	pane learned the hard way: it used to re-read on every visit, so
		 *	opening Header to check something and coming back replaced whatever
		 *	had been set here with the file on disk - without a word. A read is
		 *	only owed when there is nothing in hand yet, or when something that
		 *	rewrites the file server-side has just run.
		 *
		 *	@param		{boolean}	force			Re-read even if settings are held
		 *
		 *	@return		void
		 */
		_loadDesign : function( force ) {

			if( force !== true && Nino.admin.design._designReady === true ) {
				// The panel was hidden while the window may have changed size,
				// and a scaled frame only knows what its port measured last
				if( typeof Nino.admin.design._refit === 'function' )
					Nino.admin.design._refit();

				Nino.admin.design._updateAction();
				return;
			}

			Nino.admin.design._call( 'design/read', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.admin.design._designReady = false;
					Nino.admin.design._setStatus( 'design', Nino.admin.design._errorText( status, response ), true );
					return;
				}

				Nino.admin.design._designSettings = Nino.admin.design._clone( response.settings || {} );
				Nino.admin.design._designStored = Nino.admin.design._clone( response.settings || {} );
				Nino.admin.design._designChoices = response.choices || {};
				Nino.admin.design._designReady = true;
				Nino.admin.design._renderDesign( response );
				Nino.admin.design._markChanges();
			} );
		},

		_renderDesign : function( response ) {

			Nino.admin.design._designFields = {};
			Nino.admin.design._renderKnobs( response.groups || {} );

			[ 'primary', 'secondary' ].forEach( Nino.admin.design._bindColor );
			Nino.admin.design._designBound = true;
			Nino.admin.design._writeDesignInputs();

			Nino.admin.design._bindRows();
			Nino.admin.design._paintExample( response.example );
		},

		/**
		 *	Build one control per knob the server named, into the panel it
		 *	named. Nothing here knows what a knob is called or how many there
		 *	are - adding one is a change in the module's Tokens and nothing else,
		 *	which is the only way ten settings stay maintainable.
		 *
		 *	@param		{Object}	groups			group key -> heading
		 *
		 *	@return		void
		 */
		_renderKnobs : function( groups ) {

			Object.keys( groups ).forEach( function( group ) {
				const panel = dc.getElementById('theme-design-knobs-'+ group);
				if( panel !== null )
					panel.innerHTML = '';
			} );

			Object.keys( Nino.admin.design._designChoices ).forEach( function( knob ) {

				const meta = Nino.admin.design._designChoices[knob] || {};
				const panel = dc.getElementById('theme-design-knobs-'+ ( meta.group || '' ));

				if( panel === null )
					return;

				const field = Nino.adminUi.selectField( {
					key       : knob,
					className : 'theme-field',
					label     : Nino.admin.design._knobText( knob, 'label', meta.label ),
					note      : Nino.admin.design._knobText( knob, 'note', meta.note ),
					hint      : Nino.admin.design._knobText( knob, 'hint', meta.hint ),
					options   : Nino.admin.design._knobOptions( knob, meta ),
					value     : Nino.admin.design._designSettings[knob],
					onChange  : function( value ) {
						Nino.admin.design._designSettings[knob] = parseInt( value, 10 );
						Nino.admin.design._changed();
					},
				} );

				Nino.admin.design._designFields[knob] = field;
				panel.appendChild( field );
			} );
		},

		/**
		 *	One option per position: the number is what gets stored and the
		 *	name is what the operator reads, so neither side has to translate
		 *	the other's vocabulary
		 *
		 *	@param		{string}	knob			The knob's key ('harmony', …)
		 *	@param		{Object}	meta			{ min, max, steps }
		 *
		 *	@return		{Array}							[ { value, label } ]
		 */
		_knobOptions : function( knob, meta ) {

			const options = [];

			for( let step = meta.min; step <= meta.max; step++ )
				options.push( { value : step, label : Nino.admin.design._knobText( knob, 'step/'+ step, ( meta.steps || [] )[step - meta.min] || String( step ) ) } );

			return options;
		},

		/**
		 *	What a knob is called in the interface language.
		 *
		 *	The schema in \Nino\Modules\Design\Tokens is one source for two
		 *	surfaces: this panel and the setup wizard, which renders the very
		 *	same knobs and has no text system at all - no fills reach it (see
		 *	_admin/install/templates/page-wizard.tpl, which carries no
		 *	[jstext]). So the schema keeps its English, and the panel asks for
		 *	a fill of its own first. A knob added to Tokens therefore appears
		 *	here at once, in the schema's own words, until a fill names it -
		 *	which is the property that made the schema the single source in the
		 *	first place.
		 *
		 *	@param		{string}	knob			The knob's key ('harmony', …)
		 *	@param		{string}	part			'label', 'note', 'hint' or 'step/<position>'
		 *	@param		{string}	fallback	What the schema calls it
		 *
		 *	@return		{string}
		 */
		_knobText : function( knob, part, fallback ) {
			return Nino.content.getText( '/_admin/design/knob/'+ knob+ '/'+ part ) || String( fallback ?? '' );
		},

		_bindColor : function( role ) {

			// The two colour rows are markup rather than rendered, so they are
			// registered here instead of at render time - the marks and the
			// reset need the same handle on them the knobs hand over themselves
			Nino.admin.design._designFields[role] = dc.getElementById('theme-design-'+ role+ '-field');

			if( Nino.admin.design._designBound === true )
				return;

			const swatch = dc.getElementById('theme-design-'+ role);
			const hex = dc.getElementById('theme-design-'+ role+ '-hex');
			if( swatch === null || hex === null )
				return;

			swatch.addEventListener( 'input', function() {
				Nino.admin.design._designSettings[role] = swatch.value;
				hex.value = swatch.value;
				Nino.admin.design._hexState( hex, true );
				Nino.admin.design._changed();
			} );

			hex.addEventListener( 'input', function() {
				const value = hex.value.trim();
				if( value === '' && role === 'secondary' ) {
					Nino.admin.design._hexState( hex, true );
					Nino.admin.design._designSettings[role] = '';
					Nino.admin.design._changed();
					return;
				}

				/*	A half-typed colour is not a colour, so it cannot be sent -
					but silence at that point reads as "accepted", and the
					operator walks away from a field showing something the
					design does not have. It says so instead	*/
				if( /^#[0-9a-fA-F]{6}$/.test( value ) === false ) {
					Nino.admin.design._hexState( hex, false );
					return;
				}

				Nino.admin.design._hexState( hex, true );
				Nino.admin.design._designSettings[role] = value.toLowerCase();
				swatch.value = value;
				Nino.admin.design._changed();
			} );

			// Leaving an incomplete value behind would leave the field lying
			// about the setting for as long as the pane is open
			hex.addEventListener( 'blur', function() {

				const held = Nino.admin.design._designSettings === null ? '' : ( Nino.admin.design._designSettings[role] || '' );

				if( hex.value.trim() === held )
					return;

				hex.value = held;
				Nino.admin.design._hexState( hex, true );
			} );
		},

		/**
		 *	Say whether a colour field holds a colour.
		 *
		 *	Never on the class alone: aria-invalid is what a screen reader
		 *	reads, and the sentence in the action bar is what says why - a red
		 *	border on its own is a colour telling a colour-blind operator that
		 *	their colour is wrong.
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

			Nino.admin.design._setStatus( 'design', now === true ? Nino.content.getText('/_admin/design/error/colour') : '', now );
		},

		_writeDesignInputs : function() {

			[ 'primary', 'secondary' ].forEach( function( role ) {
				const swatch = dc.getElementById('theme-design-'+ role);
				const hex = dc.getElementById('theme-design-'+ role+ '-hex');
				const value = Nino.admin.design._designSettings[role] || '';

				if( hex !== null ) {
					hex.value = value;
					Nino.admin.design._hexState( hex, true );
				}
				if( swatch !== null ) swatch.value = value || Nino.admin.design._designSettings.primary || '#000000';
			} );

			/*	The knobs are written back too: a reset moves values the
				operator changed, and a select does not read its own model.

				Over the choices rather than over every field, because a label
				hands out its own .control - and for the two colour rows that
				is the swatch, which is not the thing being written here	*/
			Object.keys( Nino.admin.design._designChoices ).forEach( function( knob ) {

				const field = Nino.admin.design._designFields[knob];

				if( field !== null && typeof field !== 'undefined' && field.control !== null && typeof field.control !== 'undefined' )
					field.control.value = String( Nino.admin.design._designSettings[knob] );
			} );
		},

		/**
		 *	Which settings differ from the ones on disk.
		 *
		 *	Compared as text rather than by identity: a knob comes back from a
		 *	select as a number and a colour as a string, and "3" and 3 are the
		 *	same decision however the control that produced it feels about it.
		 *
		 *	@return		{Array}						The keys that differ
		 */
		_designChanges : function() {

			const changed = [];

			if( Nino.admin.design._designSettings === null || Nino.admin.design._designStored === null )
				return changed;

			Object.keys( Nino.admin.design._designSettings ).forEach( function( key ) {
				if( String( Nino.admin.design._designSettings[key] ) !== String( Nino.admin.design._designStored[key] ) )
					changed.push( key );
			} );

			return changed;
		},

		/**
		 *	What a setting was before it was touched, in the words its control
		 *	uses - the position's name for a knob, the colour for a colour
		 *
		 *	@param		{string}	key				A settings key
		 *
		 *	@return		{string}
		 */
		_savedLabel : function( key ) {

			const saved = Nino.admin.design._designStored[key];
			const meta = Nino.admin.design._designChoices[key];

			if( meta !== null && typeof meta !== 'undefined' )
				return Nino.admin.design._knobText( key, 'step/'+ saved, ( meta.steps || [] )[saved - meta.min] || String( saved ) );

			return saved === '' || saved === null || typeof saved === 'undefined' ? Nino.content.getText('/_admin/design/label/unset') : String( saved );
		},

		/**
		 *	Repaint the saved-value marks and the action bar from the current
		 *	comparison. One pass over every field, so a mark is never left
		 *	behind on a setting that has been put back where it was
		 *
		 *	@return		void
		 */
		_markChanges : function() {

			const changed = Nino.admin.design._designChanges();

			Object.keys( Nino.admin.design._designFields ).forEach( function( key ) {
				Nino.adminUi.fieldChange( Nino.admin.design._designFields[key],
					changed.indexOf( key ) === -1 ? '' : Nino.content.getText('/_admin/design/label/saved').replace( '%s', Nino.admin.design._savedLabel( key ) ) );
			} );

			Nino.admin.design._updateAction();
		},

		/**
		 *	One setting moved: say so, and ask for the picture it produces
		 *
		 *	@return		void
		 */
		_changed : function() {
			Nino.admin.design._markChanges();
			Nino.admin.design._scheduleDesignPreview();
		},

		/**
		 *	Put every setting back to the one on disk.
		 *
		 *	The other half of a dirty state: knowing something is unsaved is
		 *	only useful next to a way back that does not mean remembering ten
		 *	positions by hand
		 *
		 *	@return		void
		 */
		_revertDesign : function() {

			if( Nino.admin.design._designReady !== true || Nino.admin.design._designStored === null )
				return;

			if( Nino.admin.design._designChanges().length === 0 )
				return;

			Nino.admin.design._designSettings = Nino.admin.design._clone( Nino.admin.design._designStored );
			Nino.admin.design._writeDesignInputs();
			Nino.admin.design._setStatus( 'design', Nino.content.getText('/_admin/design/msg/reverted'), false );
			Nino.admin.design._changed();
		},

		_scheduleDesignPreview : function() {

			if( Nino.admin.design._designTimer !== null )
				wn.clearTimeout( Nino.admin.design._designTimer );

			Nino.admin.design._designTimer = wn.setTimeout( function() {
				Nino.admin.design._designTimer = null;
				Nino.admin.design._call( 'design/preview', Nino.admin.design._designSettings, function( status, response ) {
					if( status !== 200 || response === null )
						return;

					Nino.admin.design._paintExample( response.example );
				} );
			}, 150 );
		},

		/**
		 *	Show the page these settings produce.
		 *
		 *	The server builds the document because it is the only side that has
		 *	the pieces: the framework stylesheet, the generated tokens for
		 *	settings that are not stored yet, and whatever the project itself has
		 *	bundled. srcdoc rather than a src, so nothing is written to disk to
		 *	preview something the operator may not keep.
		 *
		 *	@param		{string}	html			A complete document
		 *
		 *	@return		void
		 */
		_paintExample : function( html ) {

			const frame = dc.getElementById('theme-design-example');

			if( frame === null || typeof html !== 'string' || html === '' )
				return;

			frame.srcdoc = html;
		},

		/**
		 *	The two button rows this pane has: which set of settings is on
		 *	screen, and which mode the example is shown in.
		 *
		 *	Bound once. Both rows are markup rather than rendered, so a second
		 *	call would stack a second listener on every button.
		 *
		 *	@return		void
		 */
		_bindRows : function() {

			if( Nino.admin.design._rowsBound === true )
				return;

			Nino.admin.design._rowsBound = true;

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

					const panel = dc.getElementById('theme-design-panel-'+ candidate);

					if( panel !== null )
						panel.hidden = candidate !== group;
				} );
			};

			Nino.adminUi.buttonRow( {
				colour : dc.getElementById('theme-design-tab-colour'),
				raster : dc.getElementById('theme-design-tab-raster'),
			}, 'colour', show, 'aria-selected' );

			show( 'colour' );

			// Which mode the example is shown in. A server question: the frame is
			// sandboxed, so it has an opaque origin and nothing out here can reach
			// in to stamp data-nino-mode on it
			/*	The page renders at a desktop's layout width and is scaled into
				the panel. Without it the panel is narrower than the narrowest
				content ceiling Width offers, so all three of its settings
				produced the same picture	*/
			const frame = dc.getElementById('theme-design-example');
			const port = dc.getElementById('theme-design-example-port');

			if( frame !== null && port !== null )
				Nino.admin.design._refit = Nino.adminUi.scaleFrame( frame, port, Nino.admin.design.PREVIEW_WIDTH, Nino.admin.design.PREVIEW_HEIGHT );

			Nino.adminUi.buttonRow( {
				light : dc.getElementById('theme-design-mode-light'),
				dark : dc.getElementById('theme-design-mode-dark'),
			}, Nino.admin.design._mode, function( mode ) {
				Nino.admin.design._mode = mode;
				Nino.admin.design._scheduleDesignPreview();
			} );
		},

		_applyCurrent : function() {

			const tab = Nino.admin.design._tab;
			if( tab === 'theme' ) return Nino.admin.design._applyTheme();
			if( tab === 'design' ) return Nino.admin.design._applyDesign();
			if( tab === 'header' || tab === 'footer' ) return Nino.admin.design._applyFrame( tab );
		},

		_applyTheme : function() {

			if( Nino.admin.design._appearanceReady !== true || Nino.admin.design._selectedTheme === null )
				return;

			/*	A theme apply writes the design its manifest declares, so
				anything set in the Design pane and not saved is about to be
				overwritten on disk. The one place in this tool where a loss is
				unavoidable is the one place it has to be asked about	*/
			if( Nino.admin.design._designChanges().length > 0
				&& typeof wn.confirm === 'function'
				&& wn.confirm( Nino.content.getText('/_admin/design/confirm/theme') ) === false )
				return;

			Nino.admin.design._setBusy( true );
			Nino.admin.design._setStatus( 'theme', Nino.content.getText('/_admin/design/msg/applying-theme'), false );

			Nino.admin.design._call( 'theme/apply', { theme : Nino.admin.design._selectedTheme }, function( status, response ) {
				Nino.admin.design._setBusy( false );

				if( status !== 200 || response === null ) {
					Nino.admin.design._setStatus( 'theme', Nino.admin.design._errorText( status, response ), true );
					return;
				}

				Nino.admin.design._activeTheme = response.theme || Nino.admin.design._selectedTheme;
				Nino.admin.design._designReady = false;
				Nino.admin.design._designStored = null;
				Nino.admin.design._setStatus( 'theme', Nino.content.getText('/_admin/design/msg/theme-applied'), false );
				Nino.admin.design._loadAppearance( true );
			} );
		},

		_applyDesign : function() {

			if( Nino.admin.design._designReady !== true || Nino.admin.design._designSettings === null )
				return;

			Nino.admin.design._setBusy( true );
			Nino.admin.design._setStatus( 'design', Nino.content.getText('/_admin/design/msg/writing'), false );
			Nino.admin.design._call( 'design/save', Nino.admin.design._designSettings, function( status, response ) {
				Nino.admin.design._setBusy( false );

				if( status !== 200 || response === null ) {
					Nino.admin.design._setStatus( 'design', Nino.admin.design._errorText( status, response ), true );
					return;
				}

				Nino.admin.design._designSettings = Nino.admin.design._clone( response.settings || Nino.admin.design._designSettings );
				Nino.admin.design._designStored = Nino.admin.design._clone( Nino.admin.design._designSettings );
				Nino.admin.design._writeDesignInputs();
				Nino.admin.design._setStatus( 'design', Nino.content.getText('/_admin/design/msg/written'), false );
				Nino.admin.design._markChanges();
			} );
		},

		_applyFrame : function( kind ) {

			const select = dc.getElementById('theme-frame-'+ kind);
			if( Nino.admin.design._appearanceReady !== true || select === null || select.value === '' )
				return;

			Nino.admin.design._setBusy( true );
			Nino.admin.design._setStatus( kind, Nino.content.getText('/_admin/design/msg/applying-frame').replace( '%s', Nino.content.getText('/_admin/design/label/'+ kind ) ), false );
			Nino.admin.design._call( 'frame/apply', { kind : kind, frame : select.value }, function( status, response ) {
				Nino.admin.design._setBusy( false );

				if( status !== 200 || response === null ) {
					Nino.admin.design._setStatus( kind, Nino.admin.design._errorText( status, response ), true );
					return;
				}

				Nino.admin.design._activeFrames[kind] = response.frame || select.value;
				select.value = Nino.admin.design._activeFrames[kind];
				Nino.admin.design._setStatus( kind, Nino.content.getText('/_admin/design/msg/frame-applied').replace( '%s', Nino.content.getText('/_admin/design/label/'+ kind ) ), false );
			} );
		},

		_setStatus : function( tab, message, error ) {
			Nino.admin.design._messages[tab] = message;
			Nino.admin.design._errors[tab] = error === true;
			if( tab === Nino.admin.design._tab )
				Nino.admin.design._updateAction();
		},

		_updateAction : function() {

			const status = dc.getElementById('theme-action-status');
			const save = dc.getElementById('theme-action-save');
			const revert = dc.getElementById('theme-design-reset');
			const tab = Nino.admin.design._tab;
			const changed = tab === 'design' && Nino.admin.design._designReady === true ? Nino.admin.design._designChanges().length : 0;

			if( status !== null ) {
				/*	The count outranks the last thing that happened, because
					it is the thing that is still true: "Design written." beside
					three moved settings is a sentence about the past	*/
				const dirty = changed > 0 && Nino.admin.design._errors[tab] !== true;

				status.textContent = dirty === true
					? Nino.content.getText( changed === 1 ? '/_admin/design/msg/dirty' : '/_admin/design/msg/dirty-plural' ).replace( '%d', String( changed ) )
					: ( Nino.admin.design._messages[tab] || '' );
				status.classList.toggle( 'theme-status-error', Nino.admin.design._errors[tab] === true );
				status.classList.toggle( 'theme-status-dirty', dirty );
			}

			// Shown only where it means something, which is the one pane that
			// has something of its own to lose
			if( revert !== null )
				revert.hidden = changed === 0;

			if( save === null )
				return;

			save.textContent = Nino.content.getText( Nino.admin.design.ACTION_LABELS[tab] );
			if( tab === 'theme' )
				save.disabled = Nino.admin.design._appearanceReady !== true || Nino.admin.design._selectedTheme === null;
			else if( tab === 'design' )
				// Nothing moved is nothing to write - and a Save that is live
				// when there is nothing to save is a Save nobody reads
				save.disabled = Nino.admin.design._designReady !== true || changed === 0;
			else {
				const select = dc.getElementById('theme-frame-'+ tab);
				save.disabled = Nino.admin.design._appearanceReady !== true || select === null || select.value === '';
			}
		},

		_setBusy : function( busy ) {
			const save = dc.getElementById('theme-action-save');
			if( save !== null ) save.disabled = busy === true;

			// Both ways out of an unsaved state, not just the one: a revert
			// landing mid-write would race the answer it is reverting from
			const revert = dc.getElementById('theme-design-reset');
			if( revert !== null ) revert.disabled = busy === true;
		},

		_errorText : function( status, response ) {
			return '('+ status+ ') '+ ( response && response.error ? response.error : Nino.content.getText('/_admin/common/error/request') );
		},

		_clone : function( value ) {
			return JSON.parse( JSON.stringify( value ) );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.design.init );

} )( window, document );
