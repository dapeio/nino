/**
 *	Nino						A compact filesystembased php framework
 *	_design/assets/design.js	Four independent appearance editors: Theme,
 *							Design, Header and Footer.
 *
 *	Theme establishes the complete manifest baseline. Design then writes only
 *	the generated tokens; Header and Footer each replace only their own frame.
 *	The colour maths and frame rendering stay server-side, where the files and
 *	the contrast implementation already live.
 */

( function(wn,dc) {

	wn.Nino = wn.Nino || {};

	Nino.design = {

		TABS : [ 'theme', 'design', 'header', 'footer' ],
		ACTION_LABELS : {
			theme 	: 'Apply Theme',
			design 	: 'Save Design',
			header 	: 'Apply Header',
			footer 	: 'Apply Footer',
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
		_designChoices : {},
		_designBound : false,
		_designTimer : null,

		init : function() {

			const wrap = dc.getElementById('theme-page-wrap');
			if( wrap === null )
				return;

			Nino.design.TABS.forEach( function( tab ) {
				const link = dc.getElementById('theme-nav-'+ tab);
				if( link !== null )
					link.addEventListener( 'click', function( event ) {
						event.preventDefault();
						Nino.design.selectTab( tab );
					} );
			} );

			const save = dc.getElementById('theme-action-save');
			if( save !== null )
				save.addEventListener( 'click', Nino.design._applyCurrent );

			let initial = 'theme';
			if( typeof wn.URLSearchParams === 'function' ) {
				const requested = new wn.URLSearchParams( wn.location ? ( wn.location.search || '' ) : '' ).get('tab');
				if( Nino.design.TABS.indexOf( requested ) !== -1 )
					initial = requested;
			}

			Nino.design.selectTab( initial );
		},

		selectTab : function( tab ) {

			if( Nino.design.TABS.indexOf( tab ) === -1 )
				return;

			Nino.design._tab = tab;

			const wrap = dc.getElementById('theme-page-wrap');
			Nino.design.TABS.forEach( function( current ) {
				if( wrap !== null )
					wrap.classList.toggle( 'show-'+ current, current === tab );

				const link = dc.getElementById('theme-nav-'+ current);
				if( link !== null )
					link.classList.toggle( 'active', current === tab );
			} );

			Nino.design._updateAction();

			if( tab === 'design' ) {
				Nino.design._loadDesign();
				return;
			}

			Nino.design._loadAppearance( false, function() {
				if( tab === 'header' || tab === 'footer' )
					Nino.design._renderFramePreview( tab );
			} );
		},

		_call : function( action, payload, callback ) {
			Nino.http.sendRequest( '/_design/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, {
				action : action,
				data : JSON.stringify( payload || {} ),
			} );
		},

		_loadAppearance : function( force, callback ) {

			if( force !== true && Nino.design._appearanceReady === true ) {
				if( typeof callback === 'function' ) callback();
				return;
			}

			Nino.design._call( 'appearance/read', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.design._appearanceReady = false;
					Nino.design._themes = {};
					Nino.design._frames = {};
					Nino.design._renderThemes();
					Nino.design._renderFrames();
					Nino.design._setStatus( Nino.design._tab, Nino.design._errorText( status, response ), true );
					return;
				}

				Nino.design._themes = response.themes || {};
				Nino.design._frames = response.frames || {};
				Nino.design._activeTheme = response.activeTheme || null;
				Nino.design._activeFrames = response.activeFrames || {};

				const keys = Object.keys( Nino.design._themes );
				if( keys.indexOf( Nino.design._activeTheme ) !== -1 )
					Nino.design._selectedTheme = Nino.design._activeTheme;
				else if( keys.indexOf( Nino.design._selectedTheme ) === -1 )
					Nino.design._selectedTheme = keys[0] || null;

				Nino.design._appearanceReady = true;
				Nino.design._renderThemes();
				Nino.design._renderFrames();
				Nino.design._updateAction();

				if( typeof callback === 'function' ) callback();
			} );
		},

		_renderThemes : function() {

			const grid = dc.getElementById('theme-grid');
			const empty = dc.getElementById('theme-empty');
			if( grid === null )
				return;

			grid.innerHTML = '';
			const keys = Object.keys( Nino.design._themes );

			if( empty !== null )
				empty.classList.toggle( 'theme-hidden', keys.length > 0 );

			keys.forEach( function( key ) {

				const data = Nino.design._themes[key] || {};
				const tile = dc.createElement('label');
				tile.className = 'theme-tile';
				tile.classList.toggle( 'theme-tile--selected', key === Nino.design._selectedTheme );
				tile.classList.toggle( 'theme-tile--active', key === Nino.design._activeTheme );

				const input = dc.createElement('input');
				input.type = 'radio';
				input.name = 'theme-choice';
				input.value = key;
				input.checked = key === Nino.design._selectedTheme;
				input.addEventListener( 'change', function() {
					Nino.design._selectedTheme = key;
					Nino.design._renderThemes();
					Nino.design._updateAction();
				} );
				tile.appendChild( input );

				if( data.preview ) {
					const preview = dc.createElement('img');
					preview.className = 'theme-tile-preview';
					preview.src = data.preview;
					preview.alt = ( data.label || key )+ ' preview';
					preview.loading = 'lazy';
					tile.appendChild( preview );
				}

				const body = dc.createElement('span');
				body.className = 'theme-tile-body';

				const title = dc.createElement('strong');
				title.textContent = data.label || key;
				body.appendChild( title );

				if( key === Nino.design._activeTheme ) {
					const active = dc.createElement('small');
					active.className = 'theme-tile-state';
					active.textContent = 'Active';
					body.appendChild( active );
				}

				if( data.description ) {
					const description = dc.createElement('span');
					description.textContent = data.description;
					body.appendChild( description );
				}

				tile.appendChild( body );
				grid.appendChild( tile );
			} );
		},

		_renderFrames : function() {

			[ 'header', 'footer' ].forEach( function( kind ) {

				const select = dc.getElementById('theme-frame-'+ kind);
				const panel = dc.getElementById('theme-frame-'+ kind+ '-panel');
				const empty = dc.getElementById('theme-frame-'+ kind+ '-empty');
				const keys = Nino.design._frames[kind] || [];

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

				const theme = Nino.design._themes[Nino.design._activeTheme] || {};
				const wanted = [ Nino.design._activeFrames[kind], theme[kind] ].filter( function( key ) {
					return key && keys.indexOf( key ) !== -1;
				} );
				select.value = wanted[0] || keys[0] || '';

				if( select.dataset === undefined || select.dataset.themeFrameBound !== '1' ) {
					if( select.dataset !== undefined ) select.dataset.themeFrameBound = '1';
					select.addEventListener( 'change', function() {
						Nino.design._renderFramePreview( kind );
						Nino.design._updateAction();
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
			const requestId = ( Nino.design._framePreviewRequests[kind] || 0 ) + 1;
			Nino.design._framePreviewRequests[kind] = requestId;

			const payload = {
				kind : kind,
				frame : wanted,
				theme : Nino.design._activeTheme,
			};
			if( Nino.design._designStored !== null )
				payload.design = Nino.design._designStored;

			Nino.design._call( 'frame/preview', payload, function( status, response ) {

				if( Nino.design._framePreviewRequests[kind] !== requestId || select.value !== wanted )
					return;

				if( status !== 200 || response === null || typeof response.html !== 'string' ) {
					view.removeAttribute('srcdoc');
					if( Nino.design._tab === kind )
						Nino.design._setStatus( kind, Nino.design._errorText( status, response ), true );
					return;
				}

				view.srcdoc = response.html;
			} );
		},

		_loadDesign : function() {

			Nino.design._call( 'design/read', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.design._designReady = false;
					Nino.design._setStatus( 'design', Nino.design._errorText( status, response ), true );
					return;
				}

				Nino.design._designSettings = Nino.design._clone( response.settings || {} );
				Nino.design._designStored = Nino.design._clone( response.settings || {} );
				Nino.design._designChoices = response.choices || {};
				Nino.design._designReady = true;
				Nino.design._renderDesign( response );
				Nino.design._updateAction();
			} );
		},

		_renderDesign : function( response ) {

			Object.keys( Nino.design._designChoices ).forEach( function( knob ) {
				Nino.design._renderChoice( knob );
			} );

			[ 'primary', 'secondary' ].forEach( Nino.design._bindColor );
			Nino.design._designBound = true;
			Nino.design._writeDesignInputs();

			if( response.palette ) {
				Nino.design._paintSurfaces( 'light', response.palette.light || {} );
				Nino.design._paintSurfaces( 'dark', response.palette.dark || {} );
			}
			if( response.raster )
				Nino.design._paintSizes( response.raster );
		},

		_renderChoice : function( knob ) {

			const select = dc.getElementById('theme-design-'+ knob);
			if( select === null )
				return;

			select.innerHTML = '';
			( Nino.design._designChoices[knob] || [] ).forEach( function( value ) {
				const option = dc.createElement('option');
				option.value = value;
				option.textContent = value.charAt(0).toUpperCase()+ value.slice(1);
				select.appendChild( option );
			} );
			select.value = Nino.design._designSettings[knob] || '';

			if( Nino.design._designBound === false )
				select.addEventListener( 'change', function() {
					Nino.design._designSettings[knob] = select.value;
					Nino.design._scheduleDesignPreview();
				} );
		},

		_bindColor : function( role ) {

			if( Nino.design._designBound === true )
				return;

			const swatch = dc.getElementById('theme-design-'+ role);
			const hex = dc.getElementById('theme-design-'+ role+ '-hex');
			if( swatch === null || hex === null )
				return;

			swatch.addEventListener( 'input', function() {
				Nino.design._designSettings[role] = swatch.value;
				hex.value = swatch.value;
				Nino.design._scheduleDesignPreview();
			} );

			hex.addEventListener( 'input', function() {
				const value = hex.value.trim();
				if( value === '' && role === 'secondary' ) {
					Nino.design._designSettings[role] = '';
					Nino.design._scheduleDesignPreview();
					return;
				}
				if( /^#[0-9a-fA-F]{6}$/.test( value ) === false )
					return;

				Nino.design._designSettings[role] = value.toLowerCase();
				swatch.value = value;
				Nino.design._scheduleDesignPreview();
			} );
		},

		_writeDesignInputs : function() {

			[ 'primary', 'secondary' ].forEach( function( role ) {
				const swatch = dc.getElementById('theme-design-'+ role);
				const hex = dc.getElementById('theme-design-'+ role+ '-hex');
				const value = Nino.design._designSettings[role] || '';

				if( hex !== null ) hex.value = value;
				if( swatch !== null ) swatch.value = value || Nino.design._designSettings.primary || '#000000';
			} );
		},

		_scheduleDesignPreview : function() {

			if( Nino.design._designTimer !== null )
				wn.clearTimeout( Nino.design._designTimer );

			Nino.design._designTimer = wn.setTimeout( function() {
				Nino.design._designTimer = null;
				Nino.design._call( 'design/preview', Nino.design._designSettings, function( status, response ) {
					if( status !== 200 || response === null )
						return;

					if( response.palette ) {
						Nino.design._paintSurfaces( 'light', response.palette.light || {} );
						Nino.design._paintSurfaces( 'dark', response.palette.dark || {} );
					}
					if( response.raster ) Nino.design._paintSizes( response.raster );
				} );
			}, 150 );
		},

		_paintSurfaces : function( mode, palette ) {

			const wrap = dc.getElementById('theme-design-preview-'+ mode);
			if( wrap === null )
				return;

			wrap.innerHTML = '';
			Object.keys( palette ).forEach( function( surface ) {
				const values = palette[surface] || {};
				const chip = dc.createElement('span');
				chip.className = 'theme-design-chip';
				chip.style.backgroundColor = values.bg || 'transparent';
				chip.style.color = values.on || 'inherit';
				chip.style.borderColor = values.border || 'transparent';
				chip.textContent = surface;
				wrap.appendChild( chip );
			} );
		},

		_paintSizes : function( raster ) {

			const wrapVolume = dc.getElementById('theme-design-volume-preview');
			if( wrapVolume === null )
				return;
			wrapVolume.innerHTML = '';

			const type = dc.createElement('div');
			type.className = 'theme-design-type';
			type.style.lineHeight = raster.lineHeight || '1.5';
			[ [ 6, 'Headline' ], [ 4, 'Subhead' ], [ 1, 'Body copy at its generated size' ] ].forEach( function( pair ) {
				const line = dc.createElement('p');
				line.className = 'theme-design-type-line';
				line.style.fontSize = ( raster.text || {} )[pair[0]] || '1rem';
				line.textContent = pair[1];
				type.appendChild( line );
			} );
			wrapVolume.appendChild( type );

			const wrapSpacing = dc.getElementById('theme-design-spacing-preview');
			if( wrapSpacing === null )
				return;
			wrapSpacing.innerHTML = '';
			
			const spacing = dc.createElement('div');
			spacing.className = 'theme-design-spacing';
			Object.keys( raster.space || {} ).forEach( function( step ) {
				const row = dc.createElement('span');
				row.className = 'theme-design-space-row';
				const bar = dc.createElement('span');
				bar.className = 'theme-design-space';
				bar.style.width = raster.space[step];
				const label = dc.createElement('small');
				label.textContent = raster.space[step];
				row.appendChild( bar );
				row.appendChild( label );
				spacing.appendChild( row );
			} );
			wrapSpacing.appendChild( spacing );

			const wrapShaping = dc.getElementById('theme-design-shaping-preview');
			if( wrapShaping === null )
				return;
			wrapShaping.innerHTML = '';
			
			const corners = dc.createElement('div');
			corners.className = 'theme-design-corners';
			Object.keys( raster.radius || {} ).forEach( function( step ) {
				const corner = dc.createElement('span');
				corner.className = 'theme-design-radius';
				corner.style.borderRadius = raster.radius[step];
				corner.title = 'radius-'+ step+ ': '+ raster.radius[step];
				corners.appendChild( corner );
			} );
			wrapShaping.appendChild( corners );
		},

		_applyCurrent : function() {

			const tab = Nino.design._tab;
			if( tab === 'theme' ) return Nino.design._applyTheme();
			if( tab === 'design' ) return Nino.design._applyDesign();
			if( tab === 'header' || tab === 'footer' ) return Nino.design._applyFrame( tab );
		},

		_applyTheme : function() {

			if( Nino.design._appearanceReady !== true || Nino.design._selectedTheme === null )
				return;

			Nino.design._setBusy( true );
			Nino.design._setStatus( 'theme', 'Applying the complete Theme baseline …', false );

			Nino.design._call( 'theme/apply', { theme : Nino.design._selectedTheme }, function( status, response ) {
				Nino.design._setBusy( false );

				if( status !== 200 || response === null ) {
					Nino.design._setStatus( 'theme', Nino.design._errorText( status, response ), true );
					return;
				}

				Nino.design._activeTheme = response.theme || Nino.design._selectedTheme;
				Nino.design._designReady = false;
				Nino.design._designStored = null;
				Nino.design._setStatus( 'theme', 'Theme applied. Design, Header and Footer now use its recommended baseline.', false );
				Nino.design._loadAppearance( true );
			} );
		},

		_applyDesign : function() {

			if( Nino.design._designReady !== true || Nino.design._designSettings === null )
				return;

			Nino.design._setBusy( true );
			Nino.design._setStatus( 'design', 'Writing Design …', false );
			Nino.design._call( 'design/save', Nino.design._designSettings, function( status, response ) {
				Nino.design._setBusy( false );

				if( status !== 200 || response === null ) {
					Nino.design._setStatus( 'design', Nino.design._errorText( status, response ), true );
					return;
				}

				Nino.design._designSettings = Nino.design._clone( response.settings || Nino.design._designSettings );
				Nino.design._designStored = Nino.design._clone( Nino.design._designSettings );
				Nino.design._writeDesignInputs();
				Nino.design._setStatus( 'design', 'Design written.', false );
			} );
		},

		_applyFrame : function( kind ) {

			const select = dc.getElementById('theme-frame-'+ kind);
			if( Nino.design._appearanceReady !== true || select === null || select.value === '' )
				return;

			Nino.design._setBusy( true );
			Nino.design._setStatus( kind, 'Applying '+ kind+ ' …', false );
			Nino.design._call( 'frame/apply', { kind : kind, frame : select.value }, function( status, response ) {
				Nino.design._setBusy( false );

				if( status !== 200 || response === null ) {
					Nino.design._setStatus( kind, Nino.design._errorText( status, response ), true );
					return;
				}

				Nino.design._activeFrames[kind] = response.frame || select.value;
				select.value = Nino.design._activeFrames[kind];
				Nino.design._setStatus( kind, ( kind === 'header' ? 'Header' : 'Footer' )+ ' applied.', false );
			} );
		},

		_setStatus : function( tab, message, error ) {
			Nino.design._messages[tab] = message;
			Nino.design._errors[tab] = error === true;
			if( tab === Nino.design._tab )
				Nino.design._updateAction();
		},

		_updateAction : function() {

			const status = dc.getElementById('theme-action-status');
			const save = dc.getElementById('theme-action-save');
			const tab = Nino.design._tab;

			if( status !== null ) {
				status.textContent = Nino.design._messages[tab] || '';
				status.classList.toggle( 'theme-status-error', Nino.design._errors[tab] === true );
			}

			if( save === null )
				return;

			save.textContent = Nino.design.ACTION_LABELS[tab];
			if( tab === 'theme' )
				save.disabled = Nino.design._appearanceReady !== true || Nino.design._selectedTheme === null;
			else if( tab === 'design' )
				save.disabled = Nino.design._designReady !== true;
			else {
				const select = dc.getElementById('theme-frame-'+ tab);
				save.disabled = Nino.design._appearanceReady !== true || select === null || select.value === '';
			}
		},

		_setBusy : function( busy ) {
			const save = dc.getElementById('theme-action-save');
			if( save !== null ) save.disabled = busy === true;
		},

		_errorText : function( status, response ) {
			return '('+ status+ ') '+ ( response && response.error ? response.error : 'Request failed.' );
		},

		_clone : function( value ) {
			return JSON.parse( JSON.stringify( value ) );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.design.init );

} )( window, document );
