/**
 *	Nino						A compact filesystembased php framework
 *	_theme/assets/theme.js	Four independent appearance editors: Theme,
 *							Design, Header and Footer.
 *
 *	Theme establishes the complete manifest baseline. Design then writes only
 *	the generated tokens; Header and Footer each replace only their own frame.
 *	The colour maths and frame rendering stay server-side, where the files and
 *	the contrast implementation already live.
 */

( function(wn,dc) {

	wn.Nino = wn.Nino || {};

	Nino.theme = {

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

			Nino.theme.TABS.forEach( function( tab ) {
				const link = dc.getElementById('theme-nav-'+ tab);
				if( link !== null )
					link.addEventListener( 'click', function( event ) {
						event.preventDefault();
						Nino.theme.selectTab( tab );
					} );
			} );

			const save = dc.getElementById('theme-action-save');
			if( save !== null )
				save.addEventListener( 'click', Nino.theme._applyCurrent );

			let initial = 'theme';
			if( typeof wn.URLSearchParams === 'function' ) {
				const requested = new wn.URLSearchParams( wn.location ? ( wn.location.search || '' ) : '' ).get('tab');
				if( Nino.theme.TABS.indexOf( requested ) !== -1 )
					initial = requested;
			}

			Nino.theme.selectTab( initial );
		},

		selectTab : function( tab ) {

			if( Nino.theme.TABS.indexOf( tab ) === -1 )
				return;

			Nino.theme._tab = tab;

			const wrap = dc.getElementById('theme-page-wrap');
			Nino.theme.TABS.forEach( function( current ) {
				if( wrap !== null )
					wrap.classList.toggle( 'show-'+ current, current === tab );

				const link = dc.getElementById('theme-nav-'+ current);
				if( link !== null )
					link.classList.toggle( 'active', current === tab );
			} );

			Nino.theme._updateAction();

			if( tab === 'design' ) {
				Nino.theme._loadDesign();
				return;
			}

			Nino.theme._loadAppearance( false, function() {
				if( tab === 'header' || tab === 'footer' )
					Nino.theme._renderFramePreview( tab );
			} );
		},

		_call : function( action, payload, callback ) {
			Nino.http.sendRequest( '/_theme/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, {
				action : action,
				data : JSON.stringify( payload || {} ),
			} );
		},

		_loadAppearance : function( force, callback ) {

			if( force !== true && Nino.theme._appearanceReady === true ) {
				if( typeof callback === 'function' ) callback();
				return;
			}

			Nino.theme._call( 'appearance/read', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.theme._appearanceReady = false;
					Nino.theme._themes = {};
					Nino.theme._frames = {};
					Nino.theme._renderThemes();
					Nino.theme._renderFrames();
					Nino.theme._setStatus( Nino.theme._tab, Nino.theme._errorText( status, response ), true );
					return;
				}

				Nino.theme._themes = response.themes || {};
				Nino.theme._frames = response.frames || {};
				Nino.theme._activeTheme = response.activeTheme || null;
				Nino.theme._activeFrames = response.activeFrames || {};

				const keys = Object.keys( Nino.theme._themes );
				if( keys.indexOf( Nino.theme._activeTheme ) !== -1 )
					Nino.theme._selectedTheme = Nino.theme._activeTheme;
				else if( keys.indexOf( Nino.theme._selectedTheme ) === -1 )
					Nino.theme._selectedTheme = keys[0] || null;

				Nino.theme._appearanceReady = true;
				Nino.theme._renderThemes();
				Nino.theme._renderFrames();
				Nino.theme._updateAction();

				if( typeof callback === 'function' ) callback();
			} );
		},

		_renderThemes : function() {

			const grid = dc.getElementById('theme-grid');
			const empty = dc.getElementById('theme-empty');
			if( grid === null )
				return;

			grid.innerHTML = '';
			const keys = Object.keys( Nino.theme._themes );

			if( empty !== null )
				empty.classList.toggle( 'theme-hidden', keys.length > 0 );

			keys.forEach( function( key ) {

				const data = Nino.theme._themes[key] || {};
				const tile = dc.createElement('label');
				tile.className = 'theme-tile';
				tile.classList.toggle( 'theme-tile--selected', key === Nino.theme._selectedTheme );
				tile.classList.toggle( 'theme-tile--active', key === Nino.theme._activeTheme );

				const input = dc.createElement('input');
				input.type = 'radio';
				input.name = 'theme-choice';
				input.value = key;
				input.checked = key === Nino.theme._selectedTheme;
				input.addEventListener( 'change', function() {
					Nino.theme._selectedTheme = key;
					Nino.theme._renderThemes();
					Nino.theme._updateAction();
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

				if( key === Nino.theme._activeTheme ) {
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
				const keys = Nino.theme._frames[kind] || [];

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

				const theme = Nino.theme._themes[Nino.theme._activeTheme] || {};
				const wanted = [ Nino.theme._activeFrames[kind], theme[kind] ].filter( function( key ) {
					return key && keys.indexOf( key ) !== -1;
				} );
				select.value = wanted[0] || keys[0] || '';

				if( select.dataset === undefined || select.dataset.themeFrameBound !== '1' ) {
					if( select.dataset !== undefined ) select.dataset.themeFrameBound = '1';
					select.addEventListener( 'change', function() {
						Nino.theme._renderFramePreview( kind );
						Nino.theme._updateAction();
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
			const requestId = ( Nino.theme._framePreviewRequests[kind] || 0 ) + 1;
			Nino.theme._framePreviewRequests[kind] = requestId;

			const payload = {
				kind : kind,
				frame : wanted,
				theme : Nino.theme._activeTheme,
			};
			if( Nino.theme._designStored !== null )
				payload.design = Nino.theme._designStored;

			Nino.theme._call( 'frame/preview', payload, function( status, response ) {

				if( Nino.theme._framePreviewRequests[kind] !== requestId || select.value !== wanted )
					return;

				if( status !== 200 || response === null || typeof response.html !== 'string' ) {
					view.removeAttribute('srcdoc');
					if( Nino.theme._tab === kind )
						Nino.theme._setStatus( kind, Nino.theme._errorText( status, response ), true );
					return;
				}

				view.srcdoc = response.html;
			} );
		},

		_loadDesign : function() {

			Nino.theme._call( 'design/read', {}, function( status, response ) {

				if( status !== 200 || response === null ) {
					Nino.theme._designReady = false;
					Nino.theme._setStatus( 'design', Nino.theme._errorText( status, response ), true );
					return;
				}

				Nino.theme._designSettings = Nino.theme._clone( response.settings || {} );
				Nino.theme._designStored = Nino.theme._clone( response.settings || {} );
				Nino.theme._designChoices = response.choices || {};
				Nino.theme._designReady = true;
				Nino.theme._renderDesign( response );
				Nino.theme._updateAction();
			} );
		},

		_renderDesign : function( response ) {

			Object.keys( Nino.theme._designChoices ).forEach( function( knob ) {
				Nino.theme._renderChoice( knob );
			} );

			[ 'primary', 'secondary' ].forEach( Nino.theme._bindColor );
			Nino.theme._designBound = true;
			Nino.theme._writeDesignInputs();

			if( response.palette ) {
				Nino.theme._paintSurfaces( 'light', response.palette.light || {} );
				Nino.theme._paintSurfaces( 'dark', response.palette.dark || {} );
			}
			if( response.raster )
				Nino.theme._paintSizes( response.raster );
		},

		_renderChoice : function( knob ) {

			const select = dc.getElementById('theme-design-'+ knob);
			if( select === null )
				return;

			select.innerHTML = '';
			( Nino.theme._designChoices[knob] || [] ).forEach( function( value ) {
				const option = dc.createElement('option');
				option.value = value;
				option.textContent = value.charAt(0).toUpperCase()+ value.slice(1);
				select.appendChild( option );
			} );
			select.value = Nino.theme._designSettings[knob] || '';

			if( Nino.theme._designBound === false )
				select.addEventListener( 'change', function() {
					Nino.theme._designSettings[knob] = select.value;
					Nino.theme._scheduleDesignPreview();
				} );
		},

		_bindColor : function( role ) {

			if( Nino.theme._designBound === true )
				return;

			const swatch = dc.getElementById('theme-design-'+ role);
			const hex = dc.getElementById('theme-design-'+ role+ '-hex');
			if( swatch === null || hex === null )
				return;

			swatch.addEventListener( 'input', function() {
				Nino.theme._designSettings[role] = swatch.value;
				hex.value = swatch.value;
				Nino.theme._scheduleDesignPreview();
			} );

			hex.addEventListener( 'input', function() {
				const value = hex.value.trim();
				if( value === '' && role === 'secondary' ) {
					Nino.theme._designSettings[role] = '';
					Nino.theme._scheduleDesignPreview();
					return;
				}
				if( /^#[0-9a-fA-F]{6}$/.test( value ) === false )
					return;

				Nino.theme._designSettings[role] = value.toLowerCase();
				swatch.value = value;
				Nino.theme._scheduleDesignPreview();
			} );
		},

		_writeDesignInputs : function() {

			[ 'primary', 'secondary' ].forEach( function( role ) {
				const swatch = dc.getElementById('theme-design-'+ role);
				const hex = dc.getElementById('theme-design-'+ role+ '-hex');
				const value = Nino.theme._designSettings[role] || '';

				if( hex !== null ) hex.value = value;
				if( swatch !== null ) swatch.value = value || Nino.theme._designSettings.primary || '#000000';
			} );
		},

		_scheduleDesignPreview : function() {

			if( Nino.theme._designTimer !== null )
				wn.clearTimeout( Nino.theme._designTimer );

			Nino.theme._designTimer = wn.setTimeout( function() {
				Nino.theme._designTimer = null;
				Nino.theme._call( 'design/preview', Nino.theme._designSettings, function( status, response ) {
					if( status !== 200 || response === null )
						return;

					if( response.palette ) {
						Nino.theme._paintSurfaces( 'light', response.palette.light || {} );
						Nino.theme._paintSurfaces( 'dark', response.palette.dark || {} );
					}
					if( response.raster ) Nino.theme._paintSizes( response.raster );
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

			const wrap = dc.getElementById('theme-design-sizes');
			if( wrap === null )
				return;

			wrap.innerHTML = '';

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
			wrap.appendChild( type );

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
			wrap.appendChild( spacing );

			const corners = dc.createElement('div');
			corners.className = 'theme-design-corners';
			Object.keys( raster.radius || {} ).forEach( function( step ) {
				const corner = dc.createElement('span');
				corner.className = 'theme-design-radius';
				corner.style.borderRadius = raster.radius[step];
				corner.title = 'radius-'+ step+ ': '+ raster.radius[step];
				corners.appendChild( corner );
			} );
			wrap.appendChild( corners );
		},

		_applyCurrent : function() {

			const tab = Nino.theme._tab;
			if( tab === 'theme' ) return Nino.theme._applyTheme();
			if( tab === 'design' ) return Nino.theme._applyDesign();
			if( tab === 'header' || tab === 'footer' ) return Nino.theme._applyFrame( tab );
		},

		_applyTheme : function() {

			if( Nino.theme._appearanceReady !== true || Nino.theme._selectedTheme === null )
				return;

			Nino.theme._setBusy( true );
			Nino.theme._setStatus( 'theme', 'Applying the complete Theme baseline …', false );

			Nino.theme._call( 'theme/apply', { theme : Nino.theme._selectedTheme }, function( status, response ) {
				Nino.theme._setBusy( false );

				if( status !== 200 || response === null ) {
					Nino.theme._setStatus( 'theme', Nino.theme._errorText( status, response ), true );
					return;
				}

				Nino.theme._activeTheme = response.theme || Nino.theme._selectedTheme;
				Nino.theme._designReady = false;
				Nino.theme._designStored = null;
				Nino.theme._setStatus( 'theme', 'Theme applied. Design, Header and Footer now use its recommended baseline.', false );
				Nino.theme._loadAppearance( true );
			} );
		},

		_applyDesign : function() {

			if( Nino.theme._designReady !== true || Nino.theme._designSettings === null )
				return;

			Nino.theme._setBusy( true );
			Nino.theme._setStatus( 'design', 'Writing Design …', false );
			Nino.theme._call( 'design/save', Nino.theme._designSettings, function( status, response ) {
				Nino.theme._setBusy( false );

				if( status !== 200 || response === null ) {
					Nino.theme._setStatus( 'design', Nino.theme._errorText( status, response ), true );
					return;
				}

				Nino.theme._designSettings = Nino.theme._clone( response.settings || Nino.theme._designSettings );
				Nino.theme._designStored = Nino.theme._clone( Nino.theme._designSettings );
				Nino.theme._writeDesignInputs();
				Nino.theme._setStatus( 'design', 'Design written.', false );
			} );
		},

		_applyFrame : function( kind ) {

			const select = dc.getElementById('theme-frame-'+ kind);
			if( Nino.theme._appearanceReady !== true || select === null || select.value === '' )
				return;

			Nino.theme._setBusy( true );
			Nino.theme._setStatus( kind, 'Applying '+ kind+ ' …', false );
			Nino.theme._call( 'frame/apply', { kind : kind, frame : select.value }, function( status, response ) {
				Nino.theme._setBusy( false );

				if( status !== 200 || response === null ) {
					Nino.theme._setStatus( kind, Nino.theme._errorText( status, response ), true );
					return;
				}

				Nino.theme._activeFrames[kind] = response.frame || select.value;
				select.value = Nino.theme._activeFrames[kind];
				Nino.theme._setStatus( kind, ( kind === 'header' ? 'Header' : 'Footer' )+ ' applied.', false );
			} );
		},

		_setStatus : function( tab, message, error ) {
			Nino.theme._messages[tab] = message;
			Nino.theme._errors[tab] = error === true;
			if( tab === Nino.theme._tab )
				Nino.theme._updateAction();
		},

		_updateAction : function() {

			const status = dc.getElementById('theme-action-status');
			const save = dc.getElementById('theme-action-save');
			const tab = Nino.theme._tab;

			if( status !== null ) {
				status.textContent = Nino.theme._messages[tab] || '';
				status.classList.toggle( 'theme-status-error', Nino.theme._errors[tab] === true );
			}

			if( save === null )
				return;

			save.textContent = Nino.theme.ACTION_LABELS[tab];
			if( tab === 'theme' )
				save.disabled = Nino.theme._appearanceReady !== true || Nino.theme._selectedTheme === null;
			else if( tab === 'design' )
				save.disabled = Nino.theme._designReady !== true;
			else {
				const select = dc.getElementById('theme-frame-'+ tab);
				save.disabled = Nino.theme._appearanceReady !== true || select === null || select.value === '';
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

	Nino.events.bindCallback( 'ready', Nino.theme.init );

} )( window, document );
