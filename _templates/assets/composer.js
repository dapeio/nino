/**
 *	Nino Template Builder — visual Section Library and progressive composer.
 */

( function(wn,dc) {

	'use strict';

	const pd = Nino.templates;
	// Scripts are still removed and denied by CSP. allow-scripts only prevents
	// browser extensions from producing one sandbox warning per srcdoc frame;
	// omitting allow-same-origin keeps every preview in an opaque origin.
	const PREVIEW_SANDBOX = 'allow-scripts';

	function element( tag, className, text ) {
		const node = dc.createElement( tag );
		if( className )
			node.className = className;
		if( text !== undefined )
			node.textContent = text;
		return node;
	}

	function clone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	function humanize( value ) {
		const labels = {
			button : 'Button (primary)',
			'dual-buttons' : 'Button (primary) + Button (outline)',
			'image-static' : 'Static image',
			'image-cover' : 'Cover image',
			'media-left-full' : 'Media left · full bleed',
			'media-right-full' : 'Media right · full bleed',
		};
		if( labels[value] )
			return labels[value];
		return pd.sectionsUI.humanize( value );
	}

	function matchesPreset( preset, query, category ) {
		const categoryMatch = category === 'All' || preset.category === category;
		const needle = String( query || '' ).trim().toLowerCase();
		const haystack = [ preset.name, preset.description, preset.category ].concat( preset.tags || [] ).join(' ').toLowerCase();
		return categoryMatch && ( needle === '' || haystack.includes( needle ) );
	}

	function isAreaPreset( preset ) {
		return Number( preset && preset.version ) === 3;
	}

	function reusableIncludes() {
		return pd._includes.filter( function( include ) { return include.kind !== 'Page frame' } );
	}

	function matchesInclude( include, query, category ) {
		const categoryMatch = category === 'All' || category === 'Templates';
		const needle = String( query || '' ).trim().toLowerCase();
		const haystack = [ include.name, include.label, include.kind, 'template tpl shortcode reusable' ].join(' ').toLowerCase();
		return categoryMatch && ( needle === '' || haystack.includes( needle ) );
	}

	function moduleFor( content ) {
		return pd._library.modules.find( function( module ) { return module.key === content } ) || { key : 'none', source : 'none', layouts : [ 'auto' ], fields : [], images : [], model : {} };
	}

	function selectedPreset() {
		return pd._library.presets.find( function( preset ) { return preset.key === pd.composer._presetKey } ) || null;
	}

	function presetKind( preset ) {
		return Object.keys( preset && preset.areas || {} ).length+ ' areas';
	}

	function selectedInclude() {
		return reusableIncludes().find( function( include ) { return include.path === pd.composer._includePath } ) || null;
	}

	function escapeAttribute( value ) {
		return String( value || '' ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' );
	}

	function escapeStyleText( value ) {
		return String( value || '' ).replace( /<\/style/gi, '<\\/style' );
	}

	function sanitizePreviewMarkup( markup ) {
		return String( markup || '' )
			.replace( /<script\b[^>]*>[\s\S]*?<\/script\s*>/gi, '' )
			.replace( /<script\b[^>]*>[\s\S]*$/gi, '' )
			.replace( /<\/?script\b[^>]*>/gi, '' )
			.replace( /\s+on[a-z0-9:_-]+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi, '' )
			.replace( /\s+(href|src|action|formaction|xlink:href)\s*=\s*(["'])\s*javascript:[\s\S]*?\2/gi, ' $1="#"' )
			.replace( /\s+(href|src|action|formaction|xlink:href)\s*=\s*javascript:[^\s>]*/gi, ' $1="#"' );
	}

	function previewDocument( markup ) {
		const origin = wn.location && /^https?:$/.test( wn.location.protocol ) ? wn.location.origin : '';
		const projectSource = origin ? ' '+ origin : '';
		const policy = "default-src 'none'; style-src 'unsafe-inline'; img-src data:"+ projectSource+ '; font-src data:'+ projectSource+ '; media-src'+ projectSource+ "; script-src 'none'; frame-src 'none'; connect-src 'none'; form-action 'none'; base-uri 'none'";
		const projectCss = escapeStyleText( pd._library && pd._library.previewCss || '' );
		const previewCss = 'html,body{min-height:100%;margin:0}body{overflow:auto}a,button,input,textarea,select,form{pointer-events:none!important}'
			+ '[data-cover-height="50"]{min-height:50vh!important}[data-cover-height="75"]{min-height:75vh!important}'
			+ '[data-cover-height="90"]{min-height:90vh!important}[data-cover-height="100"]{min-height:100vh!important}'
			+ '.js-parallex>img{top:0!important;height:100%!important;transform:none!important}';
		return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta http-equiv="Content-Security-Policy" content="'+ escapeAttribute( policy )+ '">'
			+ '<style>'+ projectCss+ '\n'+ previewCss+ '</style>'
			+ '</head><body>'+ sanitizePreviewMarkup( markup )+ '</body></html>';
	}

	function fitPreviewFrame( frame ) {
		if( !frame )
			return;
		const iframe = frame.querySelector('iframe');
		if( !iframe || frame.clientWidth === 0 )
			return;
		const width = Number( frame.dataset.viewportWidth || 1200 );
		const height = Number( frame.dataset.viewportHeight || 760 );
		const scale = frame.clientWidth / width;
		iframe.style.width = width+ 'px';
		iframe.style.height = height+ 'px';
		iframe.style.transform = 'scale('+ scale+ ')';
		iframe.setAttribute( 'sandbox', PREVIEW_SANDBOX );
		frame.style.height = Math.max( 1, Math.round( height * scale ) )+ 'px';
	}

	function fitPreviewFrames() {
		dc.querySelectorAll('.pd-real-preview').forEach( fitPreviewFrame );
	}

	function setPreviewFrame( frame, markup, title ) {
		if( !frame )
			return;
		const iframe = frame.querySelector('iframe');
		if( !iframe )
			return;
		const source = previewDocument( markup );
		iframe.title = title || 'Section preview';
		if( iframe._pdSource !== source ) {
			iframe._pdSource = source;
			iframe.srcdoc = source;
		}
		wn.requestAnimationFrame( function() { fitPreviewFrame( frame ) } );
	}

	function formField( label, key, values, value, wide, help ) {
		const field = element( 'label', 'pd-form-field'+ ( wide ? ' is-wide' : '' ) );
		field.appendChild( element( 'span', '', label ) );
		let input;
		if( Array.isArray( values ) ) {
			input = element('select');
			values.forEach( function( optionValue ) {
				const option = element( 'option', '', humanize( optionValue ) );
				option.value = optionValue;
				option.selected = optionValue === value;
				input.appendChild( option );
			} );
		} else {
			input = element('input');
			input.type = values || 'text';
			input.value = value === undefined ? '' : value;
			if( input.type === 'number' ) {
				input.min = '1';
				input.max = '12';
			}
		}
		input.dataset.key = key;
		field.appendChild( input );
		if( help )
			field.appendChild( element( 'small', '', help ) );
		return field;
	}

	function summaryRow( label, value ) {
		const row = element( 'div', 'pd-summary-row' );
		row.append( element( 'span', '', label ), element( 'strong', '', value ) );
		return row;
	}

	function fieldSuffixes( draft ) {
		const fields = [];
		if( draft.header !== 'none' )
			fields.push('title');
		if( [ 'title-subtitle', 'title-subtitle-description' ].includes( draft.header ) )
			fields.push('subtitle');
		if( draft.header === 'title-subtitle-description' )
			fields.push('description');
		( moduleFor( draft.content ).fields || [] ).forEach( function( suffix ) { fields.push( suffix ) } );
		if( draft.action !== 'none' )
			fields.push( 'cta-label', 'cta-uri' );
		if( draft.action === 'dual-buttons' )
			fields.push( 'secondary-cta-label', 'secondary-cta-uri' );
		return Array.from( new Set( fields ) );
	}

	function imageSuffixes( draft ) {
		const images = [];
		if( draft.background !== 'none' )
			images.push('background');
		( moduleFor( draft.content ).images || [] ).forEach( function( suffix ) { images.push( suffix ) } );
		return Array.from( new Set( images ) );
	}

	function contentKey( draft, suffix ) {
		return '/page-'+ draft.pageId+ '/'+ draft.id+ '/'+ suffix;
	}

	Object.assign( pd, { composer : {

		matchesPreset : matchesPreset,
		isAreaPreset : isAreaPreset,
		matchesInclude : matchesInclude,
		previewDocument : previewDocument,
		fieldSuffixes : fieldSuffixes,
		_context : null,
		_presetKey : null,
		_includePath : null,
		_category : 'All',
		_draft : null,
		_step : 'library',
		_autoElementType : '',
		_idTouched : false,
		_previewTimer : null,
		_previewToken : 0,
		_contentTimer : null,
		_contentToken : 0,
		_contentValues : {},
		_contentEntries : {},
		_contentTouched : new Set(),
		_contentLoadedSignature : '',
		_nativeLocale : '',
		_createElementType : null,
		_createImages : null,
		_textEntries : [],
		_textValues : {},
		_touched : new Set(),

		libraryReady : function() {
			if( pd.composer._presetKey === null && pd._library.presets.length )
				pd.composer._presetKey = ( pd._library.presets.find( isAreaPreset ) || pd._library.presets[0] ).key;
			const dialog = dc.getElementById('pd-composer');
			if( dialog && dialog.open && pd.composer._step === 'library' ) {
				pd.composer.renderCategories();
				pd.composer.renderLibrary();
			}
		},

		open : function( context ) {
			if( pd._current === null || pd._library.presets.length === 0 ) {
				pd.toast( 'The Section Library is still loading.', true );
				return;
			}

			context = Object.assign( { mode : 'insert', afterId : null, targetId : null, spec : null }, context || {} );
			pd.composer._context = context;
			pd.composer._includePath = null;
			pd.composer._idTouched = context.spec !== null;
			pd.composer._category = 'All';
			pd.composer._step = context.mode === 'replace' ? 'config' : 'library';
			pd.composer._contentValues = {};
			pd.composer._contentEntries = {};
			pd.composer._contentTouched = new Set();
			pd.composer._contentLoadedSignature = '';
			pd.composer._nativeLocale = '';
			pd.composer._createElementType = null;
			pd.composer._createImages = null;
			pd.composer._textEntries = [];
			pd.composer._textValues = {};
			pd.composer._touched = new Set();
			if( pd.areaComposer ) {
				pd.areaComposer._areaKey = '';
				pd.areaComposer._view = 'design';
			}

			const fallback = ( pd._library.presets.find( isAreaPreset ) || pd._library.presets[0] ).key;
			const requested = context.spec && context.spec.preset ? context.spec.preset : fallback;
			pd.composer._presetKey = pd._library.presets.some( function( preset ) { return preset.key === requested } ) ? requested : fallback;
			const preset = selectedPreset();
			const suggestedId = pd.model.nextId( pd._current.segments, preset.key );
			pd.composer._draft = clone( context.spec || preset.defaults );
			pd.composer._draft.preset = preset.key;
			pd.composer._draft.pageId = pd._current.pageId;
			pd.composer._draft.pageMotion = pd._pageMotion;
			pd.composer._draft.id = context.spec && context.spec.id ? context.spec.id : suggestedId;
			if( !context.spec )
				pd.composer.resetGeneratedBindings();
			pd.composer._autoElementType = pd._current.pageId+ '-'+ pd.composer._draft.id;
			if( !pd.composer._draft.elementType && moduleFor( pd.composer._draft.content ).source === 'elements' )
				pd.composer._draft.elementType = pd.composer._autoElementType;

			const dialog = dc.getElementById('pd-composer');
			dialog.classList.toggle( 'is-edit', context.mode === 'replace' );
			dialog.classList.toggle( 'is-add', context.mode !== 'replace' );
			dc.getElementById('pd-composer-title').textContent = context.mode === 'replace' ? 'Edit section' : 'Add section';
			dc.getElementById('pd-compose-submit').textContent = context.mode === 'replace' ? 'Update section' : 'Insert section';
			dc.getElementById('pd-composer-error').textContent = '';
			dc.getElementById('pd-library-search').value = '';
			pd.composer.render();
			dialog.showModal();
			wn.requestAnimationFrame( fitPreviewFrames );

			const activeContext = context;
			Promise.all( [ pd.sectionsUI.ensureTypes(), pd.sectionsUI.ensureImages(), pd.api( 'content/keys', {} ) ] ).then( function( responses ) {
				pd.composer._textEntries = responses[2].entries || [];
				if( pd.areaComposer )
					pd.areaComposer.normalizeExistingSources();
				if( pd.composer._context === activeContext && pd.composer._step === 'config' )
					pd.composer.renderSettings();
			} ).catch( function() {} );
		},

		selectPreset : function( key ) {
			const preset = pd._library.presets.find( function( entry ) { return entry.key === key } );
			if( !preset )
				return;
			const wasInclude = pd.composer._includePath !== null;
			pd.composer._includePath = null;
			if( key === pd.composer._presetKey && wasInclude === false ) {
				pd.composer.renderLibrary();
				return;
			}

			const keep = pd.composer._draft || {};
			pd.composer._presetKey = key;
			const suggestedId = pd.model.nextId( pd._current.segments, key );
			pd.composer._draft = clone( preset.defaults );
			pd.composer._draft.preset = key;
			pd.composer._draft.pageId = pd._current.pageId;
			pd.composer._draft.pageMotion = pd._pageMotion;
			pd.composer._draft.id = pd.composer._idTouched ? keep.id : suggestedId;
			pd.composer.resetGeneratedBindings();
			pd.composer._autoElementType = pd._current.pageId+ '-'+ pd.composer._draft.id;
			if( moduleFor( pd.composer._draft.content ).source === 'elements' )
				pd.composer._draft.elementType = pd.composer._autoElementType;
			pd.composer._contentValues = {};
			pd.composer._contentEntries = {};
			pd.composer._contentTouched = new Set();
			pd.composer._contentLoadedSignature = '';
			pd.composer._createElementType = null;
			pd.composer._createImages = null;
			pd.composer._textValues = {};
			pd.composer._touched = new Set();
			pd.composer.renderLibrary();
		},

		selectInclude : function( path ) {
			const include = reusableIncludes().find( function( entry ) { return entry.path === path } );
			if( !include )
				return;
			pd.composer._includePath = path;
			pd.composer.renderLibrary();
			pd.composer.renderStep();
		},

		continueFromLibrary : function() {
			pd.composer.setStep('config');
		},

		setStep : function( step ) {
			if( ![ 'library', 'config' ].includes( step ) )
				return;
			if( step === 'library' && pd.composer._context && pd.composer._context.mode === 'replace' )
				return;
			pd.composer.captureNativeInputs();
			pd.composer._step = step;
			pd.composer.renderStep();
			if( step === 'config' ) {
				pd.composer.renderConfiguration();
				pd.composer.loadNativeContent();
				wn.requestAnimationFrame( fitPreviewFrames );
			} else {
				pd.composer.renderLibrary();
				dc.getElementById('pd-library-search').focus();
			}
		},

		render : function() {
			pd.composer.renderCategories();
			pd.composer.renderLibrary();
			pd.composer.renderStep();
			if( pd.composer._step === 'config' ) {
				pd.composer.renderConfiguration();
				pd.composer.loadNativeContent();
			}
		},

		renderStep : function() {
			const library = dc.getElementById('pd-composer-library-step');
			const config = dc.getElementById('pd-composer-config-step');
			const back = dc.getElementById('pd-compose-back');
			const next = dc.getElementById('pd-compose-next');
			const submit = dc.getElementById('pd-compose-submit');
			const onLibrary = pd.composer._step === 'library';
			const editing = pd.composer._context && pd.composer._context.mode === 'replace';
			library.classList.toggle( 'pd-hidden', !onLibrary );
			config.classList.toggle( 'pd-hidden', onLibrary );
			back.classList.toggle( 'pd-hidden', onLibrary || editing );
			next.classList.toggle( 'pd-hidden', !onLibrary );
			next.textContent = selectedInclude() ? 'Insert template' : 'Configure section';
			submit.classList.toggle( 'pd-hidden', onLibrary );
			dc.getElementById('pd-step-library').classList.toggle( 'is-active', onLibrary );
			dc.getElementById('pd-step-config').classList.toggle( 'is-active', !onLibrary );
			if( onLibrary ) {
				dc.getElementById('pd-step-library').setAttribute( 'aria-current', 'step' );
				dc.getElementById('pd-step-config').removeAttribute('aria-current');
			} else {
				dc.getElementById('pd-step-library').removeAttribute('aria-current');
				dc.getElementById('pd-step-config').setAttribute( 'aria-current', 'step' );
			}
		},

		renderCategories : function() {
			const wrap = dc.getElementById('pd-library-categories');
			wrap.innerHTML = '';
			const includes = [];
			const scopedPresets = pd._library.presets.filter( isAreaPreset );
			const categories = [ 'All' ].concat( Array.from( new Set( scopedPresets.map( function( preset ) { return preset.category } ) ) ).sort() );
			if( includes.length && categories.includes('Templates') === false )
				categories.push('Templates');
			categories.forEach( function( category ) {
				const count = category === 'All'
					? scopedPresets.length + includes.length
					: ( category === 'Templates' ? includes.length : scopedPresets.filter( function( preset ) { return preset.category === category } ).length );
				const button = element( 'button', 'pd-chip'+ ( pd.composer._category === category ? ' is-active' : '' ) );
				button.type = 'button';
				button.append( element( 'span', '', category ), element( 'small', '', String( count ) ) );
				button.addEventListener( 'click', function() {
					pd.composer._category = category;
					pd.composer.renderCategories();
					pd.composer.renderLibrary();
				} );
				wrap.appendChild( button );
			} );
		},

		renderLibrary : function() {
			const wrap = dc.getElementById('pd-library-list');
			const search = dc.getElementById('pd-library-search');
			if( !wrap || !search )
				return;
			const presets = pd._library.presets.filter( function( preset ) { return isAreaPreset( preset ) && matchesPreset( preset, search.value, pd.composer._category ) } );
			const includes = [];
			wrap.innerHTML = '';
			if( presets.length === 0 && includes.length === 0 ) {
				const empty = element( 'div', 'pd-library-empty' );
				empty.append( element( 'strong', '', 'No matching section' ), element( 'p', '', 'Try a broader term or choose another category.' ) );
				wrap.appendChild( empty );
				return;
			}

			presets.forEach( function( preset ) {
				const active = pd.composer._includePath === null && preset.key === pd.composer._presetKey;
				const card = element( 'article', 'pd-preset'+ ( active ? ' is-active' : '' ) );
				const frame = element( 'div', 'pd-real-preview' );
				frame.dataset.viewportWidth = '1200';
				frame.dataset.viewportHeight = String( preset.previewHeight || ( preset.shell === 'hero' ? 760 : 680 ) );
				const iframe = element('iframe');
				iframe.loading = 'lazy';
				iframe.tabIndex = -1;
				iframe.setAttribute( 'sandbox', PREVIEW_SANDBOX );
				frame.appendChild( iframe );
				card.appendChild( frame );

				const copy = element( 'div', 'pd-preset-copy' );
				const meta = element( 'div', 'pd-preset-meta' );
				meta.appendChild( element( 'span', 'pd-preset-category', preset.category ) );
				const facts = [ presetKind( preset ), preset.layouts && preset.layouts[preset.recommend.layout] ? preset.layouts[preset.recommend.layout].label : '' ];
				const visibleFacts = facts.filter( function( fact ) { return fact && ![ 'none', 'auto' ].includes( fact ) } );
				if( visibleFacts.length < 2 && ( preset.tags || [] ).length )
					visibleFacts.push( preset.tags[0] );
				visibleFacts.slice( 0, 2 ).forEach( function( fact ) { meta.appendChild( element( 'span', '', humanize( fact ) ) ); } );
				copy.append( meta, element( 'strong', '', preset.name ), element( 'p', '', preset.description ) );
				card.appendChild( copy );

				const choose = element( 'button', 'pd-preset-select' );
				choose.type = 'button';
				choose.setAttribute( 'aria-label', 'Choose '+ preset.name );
				choose.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				choose.addEventListener( 'click', function() { pd.composer.selectPreset( preset.key ) } );
				card.appendChild( choose );
				wrap.appendChild( card );
				setPreviewFrame( frame, preset.preview || '', preset.name+ ' preview' );
			} );
			includes.forEach( function( include ) {
				const active = include.path === pd.composer._includePath;
				const card = element( 'article', 'pd-preset pd-template-preset'+ ( active ? ' is-active' : '' ) );
				const visual = element( 'div', 'pd-template-library-preview' );
				visual.append(
					element( 'span', 'pd-template-library-icon', 'TPL' ),
					element( 'code', '', '[template '+ include.path+ ']' )
				);
				card.appendChild( visual );

				const copy = element( 'div', 'pd-preset-copy' );
				const meta = element( 'div', 'pd-preset-meta' );
				meta.append( element( 'span', 'pd-preset-category', 'Template' ), element( 'span', '', include.kind ) );
				copy.append(
					meta,
					element( 'strong', '', include.name+ '.tpl' ),
					element( 'p', '', 'Insert this reusable [template] shortcode directly. It has no Section Wizard settings.' )
				);
				card.appendChild( copy );

				const choose = element( 'button', 'pd-preset-select' );
				choose.type = 'button';
				choose.setAttribute( 'aria-label', 'Choose '+ include.name+ '.tpl' );
				choose.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				choose.addEventListener( 'click', function() { pd.composer.selectInclude( include.path ) } );
				card.appendChild( choose );
				wrap.appendChild( card );
			} );
			wn.requestAnimationFrame( fitPreviewFrames );
		},

		renderConfiguration : function() {
			pd.composer.renderSelectedPreset();
			pd.composer.renderSettings();
			pd.composer.renderSummary();
			pd.composer.requestPreview( true );
		},

		renderSelectedPreset : function() {
			const wrap = dc.getElementById('pd-selected-preset');
			const preset = selectedPreset();
			if( !wrap || !preset )
				return;
			wrap.innerHTML = '';
			const copy = element('div');
			copy.append( element( 'span', 'pd-eyebrow', preset.category+ ' · '+ presetKind( preset ) ), element( 'strong', '', preset.name ), element( 'p', '', preset.description ) );
			if( pd.composer._context && pd.composer._context.mode === 'replace' ) {
				wrap.appendChild( copy );
				return;
			}
			const change = element( 'button', '', 'Change preset' );
			change.type = 'button';
			change.addEventListener( 'click', function() { pd.composer.setStep('library') } );
			wrap.append( copy, change );
		},

		renderSettings : function() {
			const wrap = dc.getElementById('pd-composer-settings');
			const preset = selectedPreset();
			const draft = pd.composer._draft;
			if( !wrap || !preset || !draft )
				return;
			pd.composer.captureNativeInputs();
			wrap.innerHTML = '';
			const activeModule = moduleFor( draft.content );

			const identity = element( 'section', 'pd-form-section' );
			identity.appendChild( element( 'h3', '', '1 · Identity' ) );
			const identityGrid = element( 'div', 'pd-form-grid' );
			identityGrid.appendChild( formField( 'Section ID', 'id', 'text', draft.id, true, 'Creates /page-'+ draft.pageId+ '/'+ draft.id+ '/… textfills.' ) );
			identity.appendChild( identityGrid );
			wrap.appendChild( identity );

			const style = element( 'section', 'pd-form-section' );
			style.appendChild( element( 'h3', '', '2 · Background & heading' ) );
			const styleGrid = element( 'div', 'pd-form-grid' );
			[ [ 'Surface', 'surface' ], [ 'Background', 'background' ], [ 'Heading', 'header' ], [ 'Alignment', 'align' ] ].forEach( function( field ) {
				styleGrid.appendChild( formField( field[0], field[1], preset.allow[field[1]], draft[field[1]], false ) );
			} );
			style.appendChild( styleGrid );
			const images = imageSuffixes( draft );
			if( images.length ) {
				const knownImages = ( pd.sectionsUI._images || [] ).map( function( slot ) { return slot.uri } );
				const missingImages = images.filter( function( suffix ) { return knownImages.includes( contentKey( draft, suffix ) ) === false } );
				const createImages = element( 'label', 'pd-check' );
				const imageCheckbox = element('input');
				imageCheckbox.type = 'checkbox';
				imageCheckbox.id = 'pd-create-image-slots';
				imageCheckbox.checked = pd.composer._createImages === null ? missingImages.length > 0 : pd.composer._createImages;
				imageCheckbox.addEventListener( 'change', function() { pd.composer._createImages = imageCheckbox.checked } );
				createImages.append( imageCheckbox, element( 'span', '', 'Create missing image slots ('+ images.join(', ')+ ')' ) );
				style.appendChild( createImages );
			}
			wrap.appendChild( style );

			const content = element( 'section', 'pd-form-section' );
			content.appendChild( element( 'h3', '', '3 · Content module' ) );
			const contentGrid = element( 'div', 'pd-form-grid' );
			contentGrid.appendChild( formField( 'Type', 'content', preset.allow.content, draft.content, false ) );
			const layouts = preset.allow.layout.filter( function( layout ) { return activeModule.layouts.includes( layout ) } );
			const availableLayouts = layouts.length ? layouts : activeModule.layouts;
			if( availableLayouts.includes( draft.layout ) === false )
				draft.layout = availableLayouts[0];
			contentGrid.appendChild( formField( 'Layout', 'layout', availableLayouts, draft.layout, false ) );
			if( activeModule.source === 'elements' ) {
				contentGrid.appendChild( formField( 'Elements collection', 'elementType', 'text', draft.elementType || '', true, 'Choose an existing type or create the recommended schema automatically.' ) );
				const known = ( pd.sectionsUI._types || [] ).map( function( entry ) { return entry.type } );
				const datalist = element('datalist');
				datalist.id = 'pd-element-types';
				known.forEach( function( uri ) { const option = element('option'); option.value = uri; datalist.appendChild( option ) } );
				contentGrid.querySelector('[data-key="elementType"]').setAttribute( 'list', datalist.id );
				contentGrid.appendChild( datalist );
				const create = element( 'label', 'pd-check is-wide' );
				const checkbox = element('input');
				checkbox.type = 'checkbox';
				checkbox.id = 'pd-create-element-type';
				checkbox.checked = pd.composer._createElementType === null ? known.includes( draft.elementType ) === false : pd.composer._createElementType;
				checkbox.addEventListener( 'change', function() { pd.composer._createElementType = checkbox.checked } );
				create.append( checkbox, element( 'span', '', 'Create the collection if it is missing' ) );
				contentGrid.appendChild( create );
				contentGrid.appendChild( formField( 'Entry limit', 'limit', 'number', draft.limit || 3, false ) );
				contentGrid.appendChild( formField( 'Card style', 'contentStyle', preset.allow.contentStyle, draft.contentStyle, false ) );
			}
			content.appendChild( contentGrid );
			wrap.appendChild( content );

			const action = element( 'section', 'pd-form-section' );
			action.appendChild( element( 'h3', '', '4 · Action & motion' ) );
			const actionGrid = element( 'div', 'pd-form-grid' );
			actionGrid.appendChild( formField( 'CTA', 'action', preset.allow.action, draft.action, false ) );
			actionGrid.appendChild( formField( 'Viewport motion', 'motion', preset.allow.motion, draft.motion, false ) );
			action.appendChild( actionGrid );
			wrap.appendChild( action );

			pd.composer.renderNativeFields( wrap );

			const advanced = element( 'details', 'pd-form-section pd-form-details' );
			advanced.appendChild( element( 'summary', '', 'Advanced spacing & border' ) );
			const advancedGrid = element( 'div', 'pd-form-grid' );
			advancedGrid.style.marginTop = '.7rem';
			[ [ 'Padding', 'padding' ], [ 'Margin', 'margin' ], [ 'Border', 'border' ] ].forEach( function( field ) {
				advancedGrid.appendChild( formField( field[0], field[1], preset.allow[field[1]], draft[field[1]], false ) );
			} );
			advanced.appendChild( advancedGrid );
			wrap.appendChild( advanced );

			wrap.querySelectorAll('[data-key]').forEach( function( input ) {
				if( input.classList.contains('pd-native-input') )
					return;
				if( input.tagName === 'SELECT' || input.type === 'number' )
					input.addEventListener( 'change', function() { pd.composer.updateDraft( input, true ) } );
				else {
					input.addEventListener( 'input', function() { pd.composer.updateDraft( input, false ) } );
					input.addEventListener( 'change', function() { pd.composer.updateDraft( input, true ) } );
				}
			} );
		},

		renderNativeFields : function( wrap ) {
			const draft = pd.composer._draft;
			const suffixes = fieldSuffixes( draft );
			const panel = element( 'section', 'pd-form-section pd-native-section' );
			const heading = element( 'div', 'pd-native-heading' );
			const copy = element('div');
			copy.append( element( 'h3', '', '5 · Native content' ), element( 'p', '', 'Fill the text used by this section now. Existing native values are loaded automatically.' ) );
			if( pd.composer._nativeLocale )
				heading.append( copy, element( 'span', 'pd-locale-badge', pd.composer._nativeLocale ) );
			else
				heading.appendChild( copy );
			panel.appendChild( heading );

			if( suffixes.length === 0 )
				panel.appendChild( element( 'p', 'nino-admin-hint', moduleFor( draft.content ).source === 'elements' ? 'This section gets its repeated content from the Elements collection below.' : 'This configuration has no native textfills.' ) );
			else {
				const fields = element( 'div', 'pd-native-grid' );
				suffixes.forEach( function( suffix ) {
					const key = contentKey( draft, suffix );
					const entry = pd.composer._contentEntries[key];
					const field = element( 'label', 'pd-content-field'+ ( [ 'description', 'content', 'subtitle', 'address' ].includes( suffix ) ? ' is-wide' : '' ) );
					const label = element('span');
					label.append( element( 'b', '', humanize( suffix ) ), element( 'small', '', entry ? ( entry.global ? 'global' : ( entry.exists ? pd.composer._nativeLocale : 'new' ) ) : 'loading…' ) );
					const long = [ 'description', 'content', 'subtitle', 'address' ].includes( suffix );
					const input = element( long ? 'textarea' : 'input', 'pd-native-input' );
					input.value = Object.prototype.hasOwnProperty.call( pd.composer._contentValues, key ) ? pd.composer._contentValues[key] : '';
					input.dataset.contentKey = key;
					input.addEventListener( 'input', function() {
						pd.composer._contentValues[key] = input.value;
						pd.composer._contentTouched.add( key );
					} );
					field.append( label, input );
					fields.appendChild( field );
				} );
				panel.appendChild( fields );
			}

			if( moduleFor( draft.content ).source === 'elements' ) {
				const note = element( 'p', 'pd-elements-note' );
				note.appendChild( dc.createTextNode( 'Repeated entries stay in ' ) );
				const link = element( 'a', '', 'Admin · Elements' );
				link.href = pd.assetUrl( '/_admin/?tab=elements&type='+ encodeURIComponent( draft.elementType || '' ) );
				note.append( link, dc.createTextNode( '; the collection can be created when this section is inserted.' ) );
				panel.appendChild( note );
			}
			wrap.appendChild( panel );
		},

		resetGeneratedBindings : function() {},

		captureValues : function() {
			pd.composer.captureNativeInputs();
			const wrap = dc.getElementById('pd-composer-settings');
			if( !wrap )
				return;
			wrap.querySelectorAll('[data-text-key]').forEach( function( input ) {
				pd.composer._textValues[input.dataset.textKey] = input.value;
			} );
		},

		loadTextValues : function() {
			return pd.composer.loadNativeContent();
		},

		captureNativeInputs : function() {
			const wrap = dc.getElementById('pd-composer-settings');
			if( !wrap )
				return;
			wrap.querySelectorAll('[data-content-key]').forEach( function( input ) {
				pd.composer._contentValues[input.dataset.contentKey] = input.value;
			} );
		},

		updateDraft : function( input, changed ) {
			const key = input.dataset.key;
			const oldId = pd.composer._draft.id;
			pd.composer.captureNativeInputs();
			pd.composer._draft[key] = input.type === 'number' ? Number( input.value ) : input.value;
			if( key === 'id' )
				pd.composer._idTouched = true;
			if( key === 'id' && ( !pd.composer._draft.elementType || pd.composer._draft.elementType === pd.composer._autoElementType ) ) {
				pd.composer._autoElementType = pd._current.pageId+ '-'+ input.value;
				pd.composer._draft.elementType = pd.composer._autoElementType;
			}
			if( key === 'content' ) {
				const module = moduleFor( input.value );
				pd.composer._draft.layout = module.layouts[0];
				if( module.source === 'elements' && !pd.composer._draft.elementType )
					pd.composer._draft.elementType = pd._current.pageId+ '-'+ oldId;
				pd.composer._createElementType = null;
			}

			const rerender = changed && [ 'id', 'background', 'header', 'content', 'action' ].includes( key );
			if( rerender )
				pd.composer.renderSettings();
			if( [ 'id', 'header', 'content', 'action' ].includes( key ) )
				pd.composer.queueNativeContent();
			pd.composer.renderSummary();
			pd.composer.requestPreview();
		},

		queueNativeContent : function() {
			wn.clearTimeout( pd.composer._contentTimer );
			pd.composer._contentTimer = wn.setTimeout( function() { pd.composer.loadNativeContent() }, 220 );
		},

		loadNativeContent : function() {
			const draft = pd.composer._draft;
			if( Number( selectedPreset() && selectedPreset().version ) === 3 && pd.areaComposer )
				return pd.composer.loadTextValues();
			if( !draft || /^[a-z][a-z0-9-]*$/.test( draft.id ) === false )
				return Promise.resolve();
			pd.composer.captureNativeInputs();
			const keys = fieldSuffixes( draft ).map( function( suffix ) { return contentKey( draft, suffix ) } );
			return pd.composer.fetchNativeContent( keys, true );
		},

		fetchNativeContent : function( keys, rerender ) {
			const signature = keys.join('\n');
			const token = ++pd.composer._contentToken;
			if( keys.length === 0 ) {
				pd.composer._contentLoadedSignature = signature;
				if( rerender )
					pd.composer.renderSettings();
				return Promise.resolve();
			}
			return pd.api( 'content/fields', { keys : keys } ).then( function( response ) {
				if( token !== pd.composer._contentToken ) {
					if( rerender === false )
						throw new Error( 'Section content changed while it was being prepared. Please try again.' );
					return;
				}
				pd.composer._nativeLocale = response.nativeLocale || '';
				( response.fields || [] ).forEach( function( entry ) {
					pd.composer._contentEntries[entry.key] = entry;
					if( pd.composer._contentTouched.has( entry.key ) === false )
						pd.composer._contentValues[entry.key] = entry.value || '';
				} );
				pd.composer._contentLoadedSignature = signature;
				if( rerender && pd.composer._step === 'config' )
					pd.composer.renderSettings();
			} ).catch( function( error ) {
				if( token === pd.composer._contentToken && rerender ) {
					dc.getElementById('pd-composer-error').textContent = 'Native content: '+ error.message;
					return;
				}
				throw error;
			} );
		},

		requestPreview : function( immediate ) {
			const draft = pd.composer._draft;
			if( !draft || pd.composer._step !== 'config' )
				return;
			wn.clearTimeout( pd.composer._previewTimer );
			const token = ++pd.composer._previewToken;
			const status = dc.getElementById('pd-preview-status');
			status.textContent = 'Updating…';
			pd.composer._previewTimer = wn.setTimeout( function() {
				pd.api( 'library/preview', draft ).then( function( response ) {
					if( token !== pd.composer._previewToken )
						return;
					setPreviewFrame( dc.getElementById('pd-composer-preview'), response.html || '', selectedPreset().name+ ' live preview' );
					status.textContent = 'Current';
				} ).catch( function( error ) {
					if( token === pd.composer._previewToken )
						status.textContent = error.message;
				} );
			}, immediate ? 0 : 180 );
		},

		renderSummary : function() {
			const draft = pd.composer._draft;
			const summary = dc.getElementById('pd-composer-summary');
			if( !draft || !summary )
				return;
			const fields = fieldSuffixes( draft );
			const images = imageSuffixes( draft );
			const module = moduleFor( draft.content );
			summary.innerHTML = '';
			summary.appendChild( summaryRow( 'Textfills', fields.length ? fields.join(', ') : 'None' ) );
			summary.appendChild( summaryRow( 'Images', images.length ? images.join(', ') : 'None' ) );
			summary.appendChild( summaryRow( 'Content source', module.source === 'elements' ? ( draft.elementType || 'Choose collection' ) : humanize( module.source ) ) );
			summary.appendChild( summaryRow( 'Generated prefix', '/page-'+ draft.pageId+ '/'+ ( draft.id || '…' ) ) );
		},

		validate : function() {
			const draft = pd.composer._draft;
			if( /^[a-z][a-z0-9-]*$/.test( draft.id ) === false )
				throw new Error( 'Section ID must start with a letter and contain only lowercase letters, numbers and hyphens.' );
			const duplicate = pd.sections().find( function( section ) {
				return section.htmlId === draft.id && section._clientId !== pd.composer._context.targetId;
			} );
			if( duplicate )
				throw new Error( 'Another section already uses id "'+ draft.id+ '".' );
			const module = moduleFor( draft.content );
			if( module.source === 'elements' && /^[a-z][a-z0-9_-]*$/.test( draft.elementType || '' ) === false )
				throw new Error( 'Choose a valid Elements collection.' );
			return module;
		},

		createImageSlots : function( result ) {
			const checkbox = dc.getElementById('pd-create-image-slots');
			if( !checkbox || checkbox.checked === false || result.imageSlots.length === 0 )
				return Promise.resolve( result );

			return pd.sectionsUI.ensureImages().then( function( slots ) {
				const existing = new Set( slots.map( function( slot ) { return slot.uri } ) );
				const uris = result.imageSlots.map( function( suffix ) { return contentKey( result.spec, suffix ) } ).filter( function( uri ) { return existing.has( uri ) === false } );
				return Promise.all( uris.map( function( uri ) {
					return pd.api( 'content/image-create', { uri : uri, label : humanize( uri.split('/').slice(-2).join(' ') ) } ).then( function() {
						pd.sectionsUI._images.push( { uri : uri, hasImage : false } );
					} );
				} ) ).then( function() { return result } );
			} );
		},

		ensureNativeContent : function( result ) {
			pd.composer.captureNativeInputs();
			const keys = result.fields.map( function( suffix ) { return contentKey( result.spec, suffix ) } );
			const signature = keys.join('\n');
			const loaded = pd.composer._contentLoadedSignature === signature;
			const ready = loaded ? Promise.resolve() : pd.composer.fetchNativeContent( keys, false );
			return ready.then( function() {
				const items = keys.filter( function( key ) {
					const entry = pd.composer._contentEntries[key];
					return pd.composer._context.mode !== 'replace' || pd.composer._contentTouched.has( key ) || !entry || entry.exists === false;
				} ).map( function( key ) {
					const entry = pd.composer._contentEntries[key];
					return { key : key, value : pd.composer._contentValues[key] || '', create : !entry || entry.exists === false };
				} );
				if( items.length === 0 )
					return result;
				return pd.api( 'content/save', { items : items } ).then( function() { return result } );
			} );
		},

		submit : function() {
			const errorWrap = dc.getElementById('pd-composer-error');
			const submit = dc.getElementById('pd-compose-submit');
			const back = dc.getElementById('pd-compose-back');
			let module;
			try {
				module = pd.composer.validate();
			} catch( error ) {
				errorWrap.textContent = error.message;
				return;
			}

			errorWrap.textContent = 'Building section and content…';
			wn.clearTimeout( pd.composer._contentTimer );
			submit.disabled = true;
			back.disabled = true;
			pd.api( 'library/compose', pd.composer._draft ).then( function( result ) {
				if( module.source !== 'elements' )
					return result;
				const types = pd.sectionsUI._types || [];
				const exists = types.some( function( entry ) { return entry.type === result.spec.elementType } );
				const create = dc.getElementById('pd-create-element-type');
				if( exists )
					return result;
				if( !create || create.checked === false )
					throw new Error( 'The Elements collection does not exist. Enable automatic creation or choose an existing type.' );
				return pd.api( 'content/type-create', {
					module : result.spec.content,
					uri : result.spec.elementType,
					title : humanize( result.spec.elementType ),
				} ).then( function( response ) {
					pd.sectionsUI._types = pd.sectionsUI._types || [];
					pd.sectionsUI._types.push( { type : response.uri, title : response.title, model : response.model } );
					return result;
				} );
			} ).then( pd.composer.createImageSlots ).then( pd.composer.ensureNativeContent ).then( function( result ) {
				pd.sectionsUI.insertResult( result, pd.composer._context );
				dc.getElementById('pd-composer').close();
				pd.toast( pd.composer._context.mode === 'replace' ? 'Section and content updated.' : 'Section and content inserted.', false );
			} ).catch( function( error ) {
				errorWrap.textContent = error.message;
			} ).finally( function() {
				submit.disabled = false;
				back.disabled = false;
			} );
		},

		cancelAsync : function() {
			wn.clearTimeout( pd.composer._previewTimer );
			wn.clearTimeout( pd.composer._contentTimer );
			pd.composer._previewToken++;
			pd.composer._contentToken++;
		},

		init : function() {
			const form = dc.getElementById('pd-composer-form');
			if( !form )
				return;
			form.addEventListener( 'submit', function( event ) {
				event.preventDefault();
				if( pd.composer._step === 'library' )
					pd.composer.continueFromLibrary();
				else
					pd.composer.submit();
			} );
			dc.getElementById('pd-library-search').addEventListener( 'input', pd.composer.renderLibrary );
			dc.getElementById('pd-compose-next').addEventListener( 'click', pd.composer.continueFromLibrary );
			dc.getElementById('pd-compose-back').addEventListener( 'click', function() { pd.composer.setStep('library') } );
			dc.querySelectorAll('.pd-dialog-close').forEach( function( close ) {
				close.addEventListener( 'click', function() { dc.getElementById('pd-composer').close() } );
			} );
			dc.getElementById('pd-composer').addEventListener( 'close', pd.composer.cancelAsync );
			wn.addEventListener( 'resize', function() { wn.requestAnimationFrame( fitPreviewFrames ) } );
			pd.composer.libraryReady();
		},
	} } );

	Nino.events.bindCallback( 'ready', pd.composer.init );

})(window, document);
