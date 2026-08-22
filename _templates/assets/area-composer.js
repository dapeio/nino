/**
 * Nino Template Builder v3 — named area editor.
 * Extends the established preset library for named-area manifests.
 */

( function(wn,dc) {

	'use strict';

	const pd = Nino.templates;
	if( !pd.composer ) return;

	const original = {};
	[ 'resetGeneratedBindings', 'renderSettings', 'renderSummary', 'loadTextValues', 'updateDraft', 'validate', 'submit' ].forEach( function( name ) { original[name] = pd.composer[name] } );

	function preset() {
		return pd._library.presets.find( function( item ) { return item.key === pd.composer._presetKey } ) || null;
	}

	function active() {
		const item = preset();
		return item && Number( item.version ) === 3;
	}

	function node( tag, className, text ) {
		const result = dc.createElement( tag );
		if( className ) result.className = className;
		if( text !== undefined ) result.textContent = text;
		return result;
	}

	function icon( paths ) {
		const svg = dc.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'fill', 'none' );
		svg.setAttribute( 'stroke', 'currentColor' );
		svg.setAttribute( 'stroke-width', '2' );
		svg.setAttribute( 'stroke-linecap', 'round' );
		svg.setAttribute( 'stroke-linejoin', 'round' );
		svg.setAttribute( 'aria-hidden', 'true' );
		paths.forEach( function( value ) {
			const path = dc.createElementNS( 'http://www.w3.org/2000/svg', 'path' );
			path.setAttribute( 'd', value );
			svg.appendChild( path );
		} );
		return svg;
	}

	function iconButton( paths, label, className ) {
		const button = node( 'button', 'pd-v3-action-button'+ ( className ? ' '+ className : '' ) );
		button.type = 'button';
		button.title = label;
		button.setAttribute( 'aria-label', label );
		button.appendChild( icon( paths ) );
		return button;
	}

	function clone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	function humanize( value ) {
		return pd.sectionsUI.humanize( String( value || '' ) );
	}

	function getPath( source, path ) {
		return path.split('.').reduce( function( value, key ) { return value && value[key] }, source );
	}

	function setPath( target, path, value ) {
		const parts = path.split('.');
		const last = parts.pop();
		const parent = parts.reduce( function( value, key ) { value[key] = value[key] || {}; return value[key] }, target );
		parent[last] = value;
	}

	function formField( label, path, options, value, help, wide ) {
		const field = node( 'label', 'pd-form-field'+ ( wide ? ' is-wide' : '' ) );
		field.appendChild( node( 'span', '', label ) );
		let input;
		if( Array.isArray( options ) ) {
			input = node('select');
			options.forEach( function( item ) {
				if( item && Array.isArray( item.options ) ) {
					const group = node('optgroup');
					group.label = item.label;
					item.options.forEach( function( groupedItem ) { group.appendChild( selectOption( groupedItem, value ) ) } );
					input.appendChild( group );
					return;
				}
				input.appendChild( selectOption( item, value ) );
			} );
		} else {
			input = node( options === 'textarea' ? 'textarea' : 'input' );
			if( input.tagName === 'INPUT' ) input.type = options || 'text';
			input.value = value === undefined ? '' : value;
		}
		input.dataset.path = path;
		field.appendChild( input );
		if( help ) field.appendChild( node( 'small', '', help ) );
		return field;
	}

	function selectOption( item, value ) {
		const option = node( 'option', '', typeof item === 'string' ? humanize( item ) : item.label );
		option.value = typeof item === 'string' ? item : item.value;
		option.selected = option.value === String( value );
		return option;
	}

	function textfillOptions( current ) {
		const groups = [
			{ label : 'Content textfills', entries : [] },
			{ label : 'Technical values', entries : [] },
		];
		( pd.composer._textEntries || [] ).forEach( function( entry ) {
			groups[entry.blacklisted ? 1 : 0].entries.push( { value : entry.key, label : entry.key } );
		} );
		if( current && !( pd.composer._textEntries || [] ).some( function( entry ) { return entry.key === current } ) )
			groups.push( { label : 'Current binding', entries : [ { value : current, label : current+ ' · unavailable' } ] } );
		const result = groups.filter( function( group ) { return group.entries.length > 0 } ).map( function( group ) {
			return { label : group.label, options : group.entries };
		} );
		return result.length ? result : [ { value : '', label : 'No textfills available' } ];
	}

	function panel( number, title, description ) {
		const result = node( 'section', 'pd-form-section pd-v3-panel' );
		const heading = node( 'div', 'pd-v3-panel-heading' );
		const marker = node( 'span', 'pd-v3-number' );
		const copy = node( 'div', 'pd-v3-panel-copy' );
		marker.append( node( 'small', '', 'Step' ), node( 'strong', '', String( number ) ) );
		copy.append( node( 'h3', '', title ), node( 'p', '', description ) );
		heading.append( marker, copy );
		result.appendChild( heading );
		return result;
	}

	function sectionLabel( title, description ) {
		const heading = node( 'div', 'pd-v3-section-label' );
		heading.append( node( 'strong', '', title ), node( 'small', '', description ) );
		return heading;
	}

	function areaKeys( item ) {
		return Object.keys( item && item.areas || {} );
	}

	function componentDefinition( item, area, component ) {
		return area.render && area.render[component.type] || item.componentCatalog[component.type];
	}

	function suffix( component, property ) {
		if( component.type === 'image' && property === 'src' ) return component.id;
		if( [ 'title', 'subtitle', 'description', 'text' ].includes( component.type ) && property === 'text' ) return component.id;
		return component.id+ '-'+ property;
	}

	function generatedKey( draft, component, property ) {
		return '/page-'+ draft.pageId+ '/'+ draft.id+ '/'+ suffix( component, property );
	}

	function quickMode() {
		return !pd.composer._context || pd.composer._context.mode !== 'replace';
	}

	function bindingSource( component, property ) {
		return component.bindingSources && component.bindingSources[property] || '';
	}

	function setBindingSource( component, property, source ) {
		component.bindingSources = component.bindingSources || {};
		component.bindingSources[property] = source;
	}

	function backgroundSource( draft ) {
		return draft.frame && draft.frame.backgroundImageSource || '';
	}

	function backgroundKey( draft ) {
		return '/page-'+ draft.pageId+ '/'+ draft.id+ '/background';
	}

	function firstTextfill() {
		const entries = pd.composer._textEntries || [];
		const content = entries.find( function( entry ) { return entry.blacklisted !== true } );
		return content ? content.key : ( entries[0] ? entries[0].key : '' );
	}

	function effectiveLayout( draft, item ) {
		return draft.layout !== 'auto' && item.layouts[draft.layout] ? draft.layout : item.recommend.layout;
	}

	const FRAME_FALLBACK = { screen : 'off', vertical : 'middle', background : 'default', container : 'default', padding : 'default', margin : 'none', focus : '5', overlay : 'dim' };

	/**
	 *	What "auto" settles on for every frame axis: the Layout's recommendation,
	 *	then the preset's, then the compiler's own fallback - the same order
	 *	AreaComposer::effective() resolves in, minus the user's own choice.
	 */
	function recommendedFrame( draft, item ) {
		const layout = item.layouts[effectiveLayout( draft, item )] || {};
		const frame = {};
		Object.keys( FRAME_FALLBACK ).forEach( function( key ) {
			const recommended = layout.frame && layout.frame[key] && layout.frame[key] !== 'auto' ? layout.frame[key] : item.recommend.frame && item.recommend.frame[key];
			frame[key] = recommended && recommended !== 'auto' ? recommended : FRAME_FALLBACK[key];
		} );
		return frame;
	}

	function effectiveFrame( draft, item ) {
		const recommended = recommendedFrame( draft, item );
		const frame = {};
		Object.keys( FRAME_FALLBACK ).forEach( function( key ) {
			frame[key] = draft.frame[key] && draft.frame[key] !== 'auto' ? draft.frame[key] : recommended[key];
		} );
		return frame;
	}

	/**
	 *	"Auto" alone tells nobody what it does. Every select that offers it names
	 *	the value it currently resolves to - Auto (Dim) - so the choice can be
	 *	read without composing the section first.
	 */
	function autoLabel( label ) {
		return 'Auto ('+ label+ ')';
	}

	function frameChoices( key, resolved ) {
		return ( pd._library.choices[key] || [] ).map( function( value ) {
			return value === 'auto' ? { value : 'auto', label : autoLabel( humanize( resolved[key] ) ) } : value;
		} );
	}

	function singleDescriptors( draft, item, kind ) {
		const result = [];
		areaKeys( item ).forEach( function( areaKey ) {
			const area = item.areas[areaKey];
			if( area.source !== 'single' ) return;
			( draft.areas[areaKey].components || [] ).forEach( function( component, index ) {
				const definition = componentDefinition( item, area, component );
				Object.keys( definition.properties || {} ).forEach( function( property ) {
					const propertyDefinition = definition.properties[property];
					if( propertyDefinition.kind !== kind ) return;
					const source = bindingSource( component, property );
					if( source === 'fixed' ) return;
					const generated = generatedKey( draft, component, property );
					const key = component.bindings[property] || generated;
					result.push( {
						area : areaKey, index : index, component : component.id, property : property,
						slot : areaKey+ '.'+ component.id+ '.'+ property,
						label : area.label+ ' · '+ definition.label+ ' · '+ propertyDefinition.label,
						control : propertyDefinition.control, default : propertyDefinition.default || '',
						width : propertyDefinition.width || 0, height : propertyDefinition.height || 0,
						generatedKey : generated, key : key, mode : source === 'new' || key === generated ? 'new' : 'existing',
					} );
				} );
			} );
		} );
		return result;
	}

	function textDescriptors( draft, item ) {
		return [ 'text', 'textarea', 'url' ].reduce( function( result, kind ) { return result.concat( singleDescriptors( draft, item, kind ) ) }, [] );
	}

	function imageDescriptors( draft, item ) {
		const images = singleDescriptors( draft, item, 'image' );
		const generated = backgroundKey( draft );
		if( [ 'cover', 'parallax' ].includes( effectiveFrame( draft, item ).background ) && backgroundSource( draft ) !== 'fixed' ) {
			const key = draft.frame.backgroundImage || generated;
			images.unshift( { area : '', index : -1, component : '', property : 'src', slot : 'background', label : 'Background image', width : 1920, height : 1080, generatedKey : generated, key : key, mode : key === generated ? 'new' : 'existing' } );
		}
		return images;
	}

	function nextComponentId( components, type ) {
		const used = new Set( ( components || [] ).map( function( component ) { return component.id } ) );
		let number = 1;
		let id = type;
		while( used.has( id ) ) id = type+ '-'+ ++number;
		return id;
	}

	function moveComponent( components, index, direction ) {
		const target = index + direction;
		if( !Array.isArray( components ) || target < 0 || target >= components.length ) return components;
		const copy = components.slice();
		const moved = copy.splice( index, 1 )[0];
		copy.splice( target, 0, moved );
		return copy;
	}

	function modelOptions( area, elementType, fieldType ) {
		let model = area.model || {};
		if( elementType ) {
			const existing = ( pd.sectionsUI._types || [] ).find( function( type ) { return type.type === elementType } );
			if( existing ) model = existing.model || {};
		}
		return Object.keys( model ).filter( function( key ) { return ( model[key].type === 'image' ) === ( fieldType === 'image' ) } ).map( function( key ) { return { value : key, label : key+ ' · '+ model[key].type } } );
	}

	function preferredMapping( options, component, property ) {
		if( options.length === 0 ) return '';
		const exact = [ component.id, property, component.id+ property.charAt(0).toUpperCase()+ property.slice(1) ];
		const direct = options.find( function( option ) { return exact.includes( option.value ) } );
		if( direct ) return direct.value;
		const semantic = property === 'src' ? [ 'image', 'photo', 'media' ]
			: property === 'href' ? [ 'href', 'link', 'url', 'uri' ]
			: property === 'label' ? [ 'label', 'title', 'name' ]
			: [ component.type, component.id, property ];
		const match = options.find( function( option ) { return semantic.some( function( word ) { return option.value.toLowerCase().includes( word.toLowerCase() ) } ) } );
		return match ? match.value : options[0].value;
	}

	function normalizeCollectionMappings( areaKey ) {
		const draft = pd.composer._draft;
		const item = preset();
		const area = item.areas[areaKey];
		const source = draft.areas[areaKey].source;
		( draft.areas[areaKey].components || [] ).forEach( function( component ) {
			const definition = componentDefinition( item, area, component );
			Object.keys( definition.properties || {} ).forEach( function( property ) {
				if( bindingSource( component, property ) !== 'field' ) return;
				const options = modelOptions( area, source.elementMode === 'existing' ? source.elementType : '', definition.properties[property].fieldType );
				if( !options.some( function( option ) { return option.value === component.bindings[property] } ) ) component.bindings[property] = preferredMapping( options, component, property );
				setBindingSource( component, property, 'field' );
			} );
		} );
	}

	function resetGeneratedBindings() {
		if( !active() ) return original.resetGeneratedBindings.call( pd.composer );
		const draft = pd.composer._draft;
		const item = preset();
		areaKeys( item ).forEach( function( areaKey ) {
			const area = item.areas[areaKey];
			( draft.areas[areaKey].components || [] ).forEach( function( component ) {
				const definition = componentDefinition( item, area, component );
				if( area.source === 'single' ) Object.keys( definition.properties || {} ).forEach( function( property ) {
					if( definition.properties[property].kind === 'template' ) return;
					if( bindingSource( component, property ) !== 'new' ) return;
					component.bindings[property] = generatedKey( draft, component, property );
					setBindingSource( component, property, 'new' );
				} );
			} );
			if( area.source === 'elements' ) {
				draft.areas[areaKey].source.elementMode = 'new';
				draft.areas[areaKey].source.elementType = draft.pageId+ '-'+ draft.id+ '-'+ areaKey;
				normalizeCollectionMappings( areaKey );
			}
		} );
	}

	function renderSectionPanel( wrap, draft, item ) {
		const quick = quickMode();
		const section = panel( 1, 'Section', quick
			? 'Choose the identity, structure and background needed before the first frontend review.'
			: 'Fine-tune identity, layout, dimensions, spacing and background.' );
		const grid = node( 'div', 'pd-form-grid' );
		grid.appendChild( formField( 'Section ID', 'id', 'text', draft.id, 'Creates /page-'+ draft.pageId+ '/'+ draft.id+ '/… bindings.', Object.keys( item.layouts ).length < 2 ) );
		const recommended = recommendedFrame( draft, item );
		if( Object.keys( item.layouts ).length > 1 ) {
			const layouts = [ { value : 'auto', label : autoLabel( item.layouts[item.recommend.layout].label ) } ].concat( Object.keys( item.layouts ).map( function( key ) { return { value : key, label : item.layouts[key].label } } ) );
			grid.appendChild( formField( 'Layout', 'layout', layouts, draft.layout, 'Changes the preset template, not merely its CSS.' ) );
		}
		if( !quick ) {
			grid.append( formField( 'Height', 'frame.screen', frameChoices( 'screen', recommended ), draft.frame.screen ), formField( 'Width', 'frame.container', frameChoices( 'container', recommended ), draft.frame.container ) );
			if( effectiveFrame( draft, item ).screen !== 'off' ) grid.appendChild( formField( 'Content position', 'frame.vertical', frameChoices( 'vertical', recommended ), draft.frame.vertical, 'Vertical alignment inside the screen cover.', true ) );
			grid.append( formField( 'Margin top / bottom', 'frame.margin', frameChoices( 'margin', recommended ), draft.frame.margin ), formField( 'Padding top / bottom', 'frame.padding', frameChoices( 'padding', recommended ), draft.frame.padding ) );
		}
		grid.appendChild( formField( 'Background', 'frame.background', frameChoices( 'background', recommended ), draft.frame.background, '', true ) );
		if( [ 'cover', 'parallax' ].includes( effectiveFrame( draft, item ).background ) ) {
			grid.append( formField( 'Overlay', 'frame.overlay', frameChoices( 'overlay', recommended ), draft.frame.overlay ), formField( 'Focus', 'frame.focus', frameChoices( 'focus', recommended ), draft.frame.focus, '1–3 top · 4–6 middle · 7–9 bottom.' ) );
			const generated = backgroundKey( draft );
			const imageKey = draft.frame.backgroundImage || generated;
			const mode = backgroundSource( draft );
			const row = node( 'div', 'pd-v3-binding-row' );
			const source = formField( 'Background image', '', [ { value : 'new', label : 'New image slot · 1920×1080' }, { value : 'image', label : 'Existing image slot' }, { value : 'fixed', label : 'Fixed value' } ], mode );
			source.querySelector('select').removeAttribute('data-path'); source.querySelector('select').dataset.backgroundMode = 'true'; row.appendChild( source );
			if( mode === 'image' ) row.appendChild( formField( 'Image slot', 'frame.backgroundImage', ( pd.sectionsUI._images || [] ).map( function( image ) { return { value : image.uri, label : image.uri } } ), imageKey ) );
			else if( mode === 'fixed' ) row.appendChild( formField( 'Image URL', 'frame.backgroundImage', 'text', imageKey === generated ? '' : imageKey, 'A project path such as [[/nino/public]]/images/hero.jpg, or an https URL.' ) );
			grid.appendChild( row );
		}
		section.appendChild( grid );
		wrap.appendChild( section );
	}

	function renderComponentActions( areaDraft, index ) {
		const actions = node( 'div', 'pd-v3-component-actions' );
		[ [ [ 'm18 15-6-6-6 6' ], -1, 'Move up' ], [ [ 'm6 9 6 6 6-6' ], 1, 'Move down' ] ].forEach( function( action ) {
			const button = iconButton( action[0], action[2] );
			button.disabled = index + action[1] < 0 || index + action[1] >= areaDraft.components.length;
			button.addEventListener( 'click', function() {
				areaDraft.components = moveComponent( areaDraft.components, index, action[1] );
				pd.composer.renderSettings(); pd.composer.renderSummary(); pd.composer.requestPreview();
			} );
			actions.appendChild( button );
		} );
		const remove = iconButton( [ 'M18 6 6 18', 'm6 6 12 12' ], 'Remove component', 'is-danger' );
		remove.addEventListener( 'click', function() {
			areaDraft.components.splice( index, 1 );
			pd.composer.renderSettings(); pd.composer.loadTextValues(); pd.composer.renderSummary(); pd.composer.requestPreview();
		} );
		actions.appendChild( remove );
		return actions;
	}

	function renderAddComponent( body, item, areaKey, area, areaDraft ) {
		if( areaDraft.components.length >= area.maxComponents ) return;
		const add = node( 'div', 'pd-v3-add-component' );
		const field = node( 'label', 'pd-form-field pd-v3-add-field' );
		field.appendChild( node( 'span', '', 'Component type' ) );
		const select = node('select');
		area.allowed.forEach( function( type ) {
			const option = node( 'option', '', item.componentCatalog[type].label );
			option.value = type; select.appendChild( option );
		} );
		const button = node( 'button', 'nino-admin-btn-secondary', '+ Add component' );
		button.type = 'button';
		button.addEventListener( 'click', function() { pd.areaComposer.addComponent( areaKey, select.value ) } );
		field.appendChild( select ); add.append( field, button ); body.appendChild( add );
	}

	function renderCollectionSource( body, areaKey, areaDraft ) {
		const sourcePanel = node( 'section', 'pd-v3-source-panel' );
		const sourceGrid = node( 'div', 'pd-form-grid' );
		sourceGrid.appendChild( formField( 'Source', 'areas.'+ areaKey+ '.source.elementMode', [ { value : 'new', label : 'Create a new collection' }, { value : 'existing', label : 'Use an existing collection' } ], areaDraft.source.elementMode ) );
		if( areaDraft.source.elementMode === 'new' ) sourceGrid.appendChild( formField( 'New collection ID', 'areas.'+ areaKey+ '.source.elementType', 'text', areaDraft.source.elementType, 'The complete manifest model is created on insert.' ) );
		else sourceGrid.appendChild( formField( 'Collection', 'areas.'+ areaKey+ '.source.elementType', ( pd.sectionsUI._types || [] ).map( function( type ) { return { value : type.type, label : type.title+ ' · '+ type.type } } ), areaDraft.source.elementType ) );
		sourcePanel.append( sectionLabel( 'Collection source', 'Create the preset model or connect this area to an existing Elements type.' ), sourceGrid );
		body.appendChild( sourcePanel );
	}

	function fixedValueField( propertyDefinition, path, value, wide ) {
		const control = propertyDefinition.control === 'textarea' ? 'textarea' : ( propertyDefinition.control === 'url' ? 'url' : 'text' );
		return formField( propertyDefinition.label+ ' value', path, control, value, 'Stored in the section source and shared by every rendered item.', wide === true );
	}

	/**
	 *	One property is one line: what feeds it on the left, the value it is fed
	 *	with on the right. Appended as separate fields they wrapped onto their
	 *	own rows, which reads as twice as many unrelated controls as there are.
	 */
	function bindingRow( group, label, sourceField, valueField ) {
		const row = node( 'div', 'pd-v3-binding-row' );
		row.setAttribute( 'role', 'group' );
		row.setAttribute( 'aria-label', label );
		if( sourceField ) row.appendChild( sourceField );
		if( valueField ) row.appendChild( valueField );
		group.appendChild( row );
	}

	function sourceField( label, areaKey, index, property, options, mode ) {
		const field = formField( label+ ' source', '', options, mode );
		const select = field.querySelector('select');
		select.removeAttribute('data-path');
		select.dataset.bindingMode = areaKey+ ':'+ index+ ':'+ property;
		return field;
	}

	function renderBindingFields( group, draft, item, areaKey, area, areaDraft, component, index ) {
		const definition = componentDefinition( item, area, component );
		Object.keys( definition.properties || {} ).forEach( function( property ) {
			const propertyDefinition = definition.properties[property];
			const path = 'areas.'+ areaKey+ '.components.'+ index+ '.bindings.'+ property;
			const current = component.bindings[property] || '';
			if( propertyDefinition.kind === 'template' ) {
				const options = [ { value : '', label : 'None' } ].concat( pd._includes.filter( function( include ) { return include.kind !== 'Page frame' } ).map( function( include ) { return { value : include.path, label : include.name+ '.tpl' } } ) );
				group.appendChild( formField( propertyDefinition.label, path, options, current, 'Writes a normal [template] shortcode.', true ) );
				return;
			}

			const mode = bindingSource( component, property );
			if( area.source === 'elements' ) {
				const options = [ { value : 'field', label : 'Collection field' }, { value : 'textfill', label : 'Existing textfill' }, { value : 'fixed', label : 'Fixed value' } ];
				const source = propertyDefinition.fieldType === 'image' ? null : sourceField( propertyDefinition.label, areaKey, index, property, options, mode );
				let value;
				if( mode === 'textfill' ) value = formField( 'Textfill key', path, textfillOptions( current ), current );
				else if( mode === 'fixed' ) value = fixedValueField( propertyDefinition, path, current );
				else value = formField( propertyDefinition.label+ ' maps to', path, modelOptions( area, areaDraft.source.elementMode === 'existing' ? areaDraft.source.elementType : '', propertyDefinition.fieldType ), current );
				bindingRow( group, propertyDefinition.label, source, value );
				return;
			}

			const generated = generatedKey( draft, component, property );
			const sourceOptions = propertyDefinition.kind === 'image'
				? [ { value : 'new', label : 'New image slot' }, { value : 'image', label : 'Existing image slot' } ]
				: [ { value : 'new', label : 'New section value' }, { value : 'textfill', label : 'Existing textfill' }, { value : 'fixed', label : 'Fixed value' } ];
			const source = sourceField( propertyDefinition.label, areaKey, index, property, sourceOptions, mode );
			let value = null;
			if( mode === 'image' ) {
				value = formField( 'Image slot', path, ( pd.sectionsUI._images || [] ).map( function( image ) { return { value : image.uri, label : image.uri } } ), current );
			} else if( mode === 'textfill' ) {
				value = formField( 'Textfill key', path, textfillOptions( current ), current );
			} else if( mode === 'fixed' ) {
				value = fixedValueField( propertyDefinition, path, current );
			} else if( propertyDefinition.kind !== 'image' ) {
				value = node( 'label', 'pd-form-field pd-v3-generated-value' );
				const input = node( propertyDefinition.control === 'textarea' ? 'textarea' : 'input' );
				if( input.tagName === 'INPUT' ) input.type = propertyDefinition.control === 'url' ? 'url' : 'text';
				input.value = Object.prototype.hasOwnProperty.call( pd.composer._textValues, generated ) ? pd.composer._textValues[generated] : propertyDefinition.default;
				input.dataset.textKey = generated; input.placeholder = propertyDefinition.default;
				value.append( node( 'span', '', propertyDefinition.label+ ' value' ), input );
				input.addEventListener( 'input', function() { pd.composer._textValues[generated] = input.value; pd.composer._touched.add( generated ) } );
			}
			bindingRow( group, propertyDefinition.label, source, value );
			if( mode === 'new' && propertyDefinition.kind === 'image' )
				group.appendChild( node( 'p', 'pd-v3-binding-note', 'The image slot is created when the section is inserted and can then be filled in Admin.' ) );
		} );
		if( component.type === 'button' )
			group.appendChild( targetToggle( areaKey, index, component ) );
	}

	/**
	 *	Link target as one checkbox rather than a two-option select: it is a
	 *	single on/off decision, and it belongs after the address it applies to
	 *	instead of above the fields it says nothing about.
	 *
	 *	Built from the composer's own .pd-check, not the design system's
	 *	switch: Nino.admin.css scopes its components to :where(.nino-admin),
	 *	and the composer dialogs deliberately sit outside #pd-app, so a switch
	 *	rendered in here would arrive without its track, its state word or any
	 *	of its behavior.
	 */
	function targetToggle( areaKey, index, component ) {
		const field = node( 'label', 'pd-check pd-v3-binding-toggle' );
		const input = node('input');
		input.type = 'checkbox';
		input.checked = component.settings.target === 'new';
		input.dataset.targetToggle = areaKey+ ':'+ index;
		field.append( input, node( 'span', '', 'Target _blank · opens the link in a new tab' ) );
		return field;
	}

	function renderAreaPanel( wrap, draft, item ) {
		const keys = areaKeys( item );
		const quick = quickMode();
		if( !keys.includes( pd.areaComposer._areaKey ) ) pd.areaComposer._areaKey = keys[0];
		const areaKey = pd.areaComposer._areaKey;
		const area = item.areas[areaKey];
		const areaDraft = draft.areas[areaKey];
		const section = panel( 2, 'Content areas', quick
			? 'Build each named slot from ordered components and connect its initial data.'
			: 'Fine-tune each named slot through its separate Design and Data views.' );
		section.classList.add('pd-v3-areas-panel');
		const workspace = node( 'div', 'pd-v3-area-workspace' );
		const tabs = node( 'div', 'pd-v3-area-tabs' );
		tabs.setAttribute( 'role', 'tablist' );
		tabs.setAttribute( 'aria-label', 'Content areas' );
		keys.forEach( function( key, index ) {
			const button = node( 'button', key === areaKey ? 'is-active' : '' );
			const copy = node( 'span', 'pd-v3-area-tab-copy' );
			button.type = 'button';
			button.id = 'pd-v3-area-tab-'+ key;
			button.setAttribute( 'role', 'tab' );
			button.setAttribute( 'aria-selected', key === areaKey ? 'true' : 'false' );
			button.setAttribute( 'aria-controls', 'pd-v3-area-panel-'+ key );
			button.tabIndex = key === areaKey ? 0 : -1;
			copy.append( node( 'strong', '', item.areas[key].label ), node( 'small', '', item.areas[key].source === 'elements' ? 'Collection' : 'Single content' ) );
			button.append( node( 'span', 'pd-v3-area-index', String( index + 1 ) ), copy );
			button.addEventListener( 'click', function() { pd.composer.captureValues(); pd.areaComposer._areaKey = key; pd.composer.renderSettings() } );
			tabs.appendChild( button );
		} );
		workspace.appendChild( tabs );
		const editor = node( 'div', 'pd-v3-area-editor' );
		editor.id = 'pd-v3-area-panel-'+ areaKey;
		editor.setAttribute( 'role', 'tabpanel' );
		editor.setAttribute( 'aria-labelledby', 'pd-v3-area-tab-'+ areaKey );

		const toolbar = node( 'div', 'pd-v3-area-heading' );
		const copy = node('div');
		copy.append( node( 'strong', '', area.label ), node( 'p', '', area.help || ( quick ? 'Choose the content structure and connect its data.' : 'Configure the ordered components and connect their data.' ) ) );
		if( quick ) {
			toolbar.appendChild( copy ); editor.appendChild( toolbar );
			renderQuickArea( editor, draft, item, areaKey, area, areaDraft );
			workspace.appendChild( editor ); section.appendChild( workspace ); wrap.appendChild( section );
			return;
		}
		const views = node( 'div', 'pd-v3-view-tabs' );
		[ 'design', 'data' ].forEach( function( view ) {
			const button = node( 'button', pd.areaComposer._view === view ? 'is-active' : '', humanize( view ) );
			button.type = 'button';
			button.setAttribute( 'role', 'tab' );
			button.setAttribute( 'aria-selected', pd.areaComposer._view === view ? 'true' : 'false' );
			button.addEventListener( 'click', function() { pd.composer.captureValues(); pd.areaComposer._view = view; pd.composer.renderSettings() } );
			views.appendChild( button );
		} );
		views.setAttribute( 'role', 'tablist' );
		views.setAttribute( 'aria-label', area.label+ ' editor' );
		toolbar.append( copy, views ); editor.appendChild( toolbar );
		if( pd.areaComposer._view === 'data' ) renderData( editor, draft, item, areaKey, area, areaDraft );
		else renderDesign( editor, draft, item, areaKey, area, areaDraft );
		workspace.appendChild( editor );
		section.appendChild( workspace );
		wrap.appendChild( section );
	}

	function renderQuickArea( section, draft, item, areaKey, area, areaDraft ) {
		const body = node( 'div', 'pd-v3-area-body pd-v3-quick' );
		if( area.source === 'elements' ) renderCollectionSource( body, areaKey, areaDraft );
		body.appendChild( sectionLabel( 'Components and data', 'Add, order and fill only what this section needs initially. Visual fine-tuning remains available after insertion.' ) );
		const list = node( 'div', 'pd-v3-quick-components' );
		areaDraft.components.forEach( function( component, index ) {
			const definition = componentDefinition( item, area, component );
			const group = node( 'article', 'pd-v3-binding-group pd-v3-quick-component' );
			const heading = node( 'div', 'pd-v3-binding-heading' );
			const headingCopy = node( 'span', 'pd-v3-binding-copy' );
			const identity = node( 'span', 'pd-v3-quick-identity' );
			identity.append( node( 'strong', '', definition.label ), node( 'code', '', component.id ) );
			headingCopy.append( identity, renderComponentActions( areaDraft, index ) );
			heading.append( node( 'span', 'pd-v3-binding-order', String( index + 1 ) ), headingCopy );
			group.appendChild( heading );
			renderBindingFields( group, draft, item, areaKey, area, areaDraft, component, index );
			list.appendChild( group );
		} );
		if( areaDraft.components.length === 0 ) list.appendChild( node( 'p', 'nino-admin-hint', 'This area is empty. Add a component below if it should render content.' ) );
		body.appendChild( list ); renderAddComponent( body, item, areaKey, area, areaDraft ); section.appendChild( body );
	}

	function renderDesign( section, draft, item, areaKey, area, areaDraft ) {
		const body = node( 'div', 'pd-v3-area-body' );
		const styles = [ { value : 'auto', label : autoLabel( area.styles[area.recommend.style].label ) } ].concat( Object.keys( area.styles ).map( function( key ) { return { value : key, label : area.styles[key].label } } ) );
		body.appendChild( formField( 'Area style', 'areas.'+ areaKey+ '.style', styles, areaDraft.style, 'Columns belong here when they are purely visual.', true ) );
		body.appendChild( sectionLabel( 'Component stack', 'Define what this area contains and in which order it appears.' ) );
		const list = node( 'div', 'pd-v3-components' );
		if( areaDraft.components.length === 0 ) list.appendChild( node( 'p', 'nino-admin-hint', 'This area is empty. Add a component below if it should render content.' ) );
		areaDraft.components.forEach( function( component, index ) {
			const definition = componentDefinition( item, area, component );
			const row = node( 'article', 'pd-v3-component' );
			const identity = node( 'div', 'pd-v3-component-identity' );
			const copy = node( 'span', 'pd-v3-component-copy' );
			copy.append( node( 'strong', '', definition.label ), node( 'small', '', component.id ) );
			identity.append( node( 'span', 'pd-v3-component-order', String( index + 1 ) ), copy );
			const controls = node( 'div', 'pd-v3-component-controls' );
			const styleOptions = definition.styles.map( function( key ) { return { value : key, label : key === 'auto' ? 'Auto' : humanize( key ) } } );
			controls.appendChild( formField( 'Style', 'areas.'+ areaKey+ '.components.'+ index+ '.style', styleOptions, component.style ) );
			row.append( identity, controls, renderComponentActions( areaDraft, index ) ); list.appendChild( row );
		} );
		body.appendChild( list );
		renderAddComponent( body, item, areaKey, area, areaDraft );
		section.appendChild( body );
	}

	function renderData( section, draft, item, areaKey, area, areaDraft ) {
		const body = node( 'div', 'pd-v3-area-body pd-v3-data' );
		if( area.source === 'elements' ) renderCollectionSource( body, areaKey, areaDraft );

		body.appendChild( sectionLabel( 'Data bindings', 'Connect every visible component to its native content value.' ) );
		const bindings = node( 'div', 'pd-v3-bindings' );
		areaDraft.components.forEach( function( component, index ) {
			const definition = componentDefinition( item, area, component );
			const group = node( 'article', 'pd-v3-binding-group' );
			const heading = node( 'div', 'pd-v3-binding-heading' );
			const headingCopy = node( 'span', 'pd-v3-binding-copy' );
			headingCopy.append( node( 'strong', '', definition.label ), node( 'code', '', component.id ) );
			heading.append( node( 'span', 'pd-v3-binding-order', String( index + 1 ) ), headingCopy );
			group.appendChild( heading );
			renderBindingFields( group, draft, item, areaKey, area, areaDraft, component, index );
			bindings.appendChild( group );
		} );
		if( areaDraft.components.length === 0 ) bindings.appendChild( node( 'p', 'nino-admin-hint', 'This area currently has no data-bearing components.' ) );
		body.appendChild( bindings ); section.appendChild( body );
	}

	function bindSettings( wrap ) {
		wrap.querySelectorAll('[data-path]').forEach( function( input ) {
			const event = input.tagName === 'SELECT' || input.type === 'checkbox' ? 'change' : 'input';
			input.addEventListener( event, function() { pd.composer.updateDraft( input, event !== 'input' ) } );
			if( event === 'input' ) input.addEventListener( 'change', function() { pd.composer.updateDraft( input, true ) } );
		} );
		wrap.querySelectorAll('[data-binding-mode]').forEach( function( input ) {
			input.addEventListener( 'change', function() {
				const parts = input.dataset.bindingMode.split(':');
				const area = preset().areas[parts[0]];
				const component = pd.composer._draft.areas[parts[0]].components[Number( parts[1] )];
				const definition = componentDefinition( preset(), area, component ).properties[parts[2]];
				setBindingSource( component, parts[2], input.value );
				if( input.value === 'new' ) component.bindings[parts[2]] = generatedKey( pd.composer._draft, component, parts[2] );
				else if( input.value === 'image' ) component.bindings[parts[2]] = pd.sectionsUI._images && pd.sectionsUI._images[0] ? pd.sectionsUI._images[0].uri : '';
				else if( input.value === 'textfill' ) component.bindings[parts[2]] = firstTextfill();
				else if( input.value === 'fixed' ) component.bindings[parts[2]] = definition.default || '';
				else if( input.value === 'field' ) {
					const source = pd.composer._draft.areas[parts[0]].source;
					component.bindings[parts[2]] = preferredMapping( modelOptions( area, source.elementMode === 'existing' ? source.elementType : '', definition.fieldType ), component, parts[2] );
				}
				pd.composer.renderSettings(); pd.composer.loadTextValues(); pd.composer.renderSummary(); pd.composer.requestPreview();
			} );
		} );
		wrap.querySelectorAll('[data-target-toggle]').forEach( function( input ) {
			input.addEventListener( 'change', function() {
				const parts = input.dataset.targetToggle.split(':');
				const component = pd.composer._draft.areas[parts[0]].components[Number( parts[1] )];
				component.settings = component.settings || {};
				component.settings.target = input.checked ? 'new' : 'same';
				pd.composer.requestPreview();
			} );
		} );
		wrap.querySelectorAll('[data-background-mode]').forEach( function( input ) {
			input.addEventListener( 'change', function() {
				const draft = pd.composer._draft;
				const generated = backgroundKey( draft );
				draft.frame.backgroundImageSource = input.value;
				draft.frame.backgroundImage = input.value === 'fixed'
					? ''
					: ( input.value === 'new' ? generated : ( pd.sectionsUI._images && pd.sectionsUI._images[0] ? pd.sectionsUI._images[0].uri : generated ) );
				pd.composer.renderSettings(); pd.composer.renderSummary(); pd.composer.requestPreview();
			} );
		} );
	}

	function renderSettings() {
		if( !active() ) return original.renderSettings.call( pd.composer );
		const wrap = dc.getElementById('pd-composer-settings');
		const draft = pd.composer._draft;
		const item = preset();
		if( !wrap || !draft ) return;
		pd.composer.captureValues(); wrap.innerHTML = ''; wrap.classList.toggle( 'is-quick', quickMode() );
		renderSectionPanel( wrap, draft, item ); renderAreaPanel( wrap, draft, item ); bindSettings( wrap );
	}

	function updateDraft( input, committed ) {
		if( !active() ) return original.updateDraft.call( pd.composer, input, committed );
		pd.composer.captureValues();
		const draft = pd.composer._draft;
		const path = input.dataset.path;
		const oldId = draft.id;
		setPath( draft, path, input.type === 'checkbox' ? input.checked : input.value );
		if( path === 'id' ) {
			pd.composer._idTouched = true;
			areaKeys( preset() ).forEach( function( areaKey ) {
				const area = preset().areas[areaKey];
				draft.areas[areaKey].components.forEach( function( component ) {
					Object.keys( component.bindings || {} ).forEach( function( property ) {
						const value = component.bindings[property];
						const source = bindingSource( component, property );
						if( area.source === 'single' && source === 'new' && typeof value === 'string' && value.startsWith( '/page-'+ draft.pageId+ '/'+ oldId+ '/' ) ) component.bindings[property] = value.replace( '/'+ oldId+ '/', '/'+ draft.id+ '/' );
					} );
				} );
				const source = draft.areas[areaKey].source;
				if( area.source === 'elements' && source.elementMode === 'new' && source.elementType === draft.pageId+ '-'+ oldId+ '-'+ areaKey ) source.elementType = draft.pageId+ '-'+ draft.id+ '-'+ areaKey;
			} );
			if( draft.frame.backgroundImageSource !== 'fixed' && draft.frame.backgroundImage && draft.frame.backgroundImage.startsWith( '/page-'+ draft.pageId+ '/'+ oldId+ '/' ) ) draft.frame.backgroundImage = draft.frame.backgroundImage.replace( '/'+ oldId+ '/', '/'+ draft.id+ '/' );
		}
		const sourceMatch = path.match( /^areas\.([a-z0-9-]+)\.source\.(elementMode|elementType)$/ );
		if( sourceMatch ) {
			const source = draft.areas[sourceMatch[1]].source;
			if( sourceMatch[2] === 'elementMode' && source.elementMode === 'existing' && pd.sectionsUI._types && pd.sectionsUI._types[0] ) source.elementType = pd.sectionsUI._types[0].type;
			normalizeCollectionMappings( sourceMatch[1] );
		}
		if( committed !== false || input.tagName === 'SELECT' || input.type === 'checkbox' ) {
			pd.composer.renderSettings();
			pd.composer.loadTextValues().then( function() { if( pd.composer._step === 'config' ) pd.composer.renderSettings() } );
		}
		pd.composer.renderSummary(); pd.composer.requestPreview();
	}

	function loadTextValues() {
		if( !active() ) return original.loadTextValues.call( pd.composer );
		const draft = pd.composer._draft;
		if( !draft || !/^[a-z][a-z0-9-]*$/.test( draft.id ) ) return Promise.resolve();
		const fields = textDescriptors( draft, preset() );
		const keys = fields.map( function( field ) { return field.key } );
		if( keys.length === 0 ) return Promise.resolve();
		const token = ++pd.composer._contentToken;
		return pd.api( 'content/fields', { keys : keys } ).then( function( response ) {
			if( token !== pd.composer._contentToken ) return;
			response.fields.forEach( function( entry ) {
				if( pd.composer._touched.has( entry.key ) ) return;
				const definition = fields.find( function( field ) { return field.key === entry.key } );
				pd.composer._textValues[entry.key] = entry.exists ? entry.value : ( definition ? definition.default : '' );
			} );
		} );
	}

	function renderSummary() {
		if( !active() ) return original.renderSummary.call( pd.composer );
		const summary = dc.getElementById('pd-composer-summary');
		const draft = pd.composer._draft;
		const item = preset();
		if( !summary || !draft ) return;
		summary.innerHTML = '';
		const collections = areaKeys( item ).filter( function( key ) { return item.areas[key].source === 'elements' } );
		[ [ 'Layout', item.layouts[effectiveLayout( draft, item )].label ], [ 'Areas', areaKeys( item ).length ], [ 'Components', areaKeys( item ).reduce( function( total, key ) { return total + draft.areas[key].components.length }, 0 ) ], [ 'Collections', collections.length ? collections.map( function( key ) { return draft.areas[key].source.elementType } ).join(', ') : 'None' ], [ 'Text bindings', textDescriptors( draft, item ).length ], [ 'Image bindings', imageDescriptors( draft, item ).length ], [ 'Generated prefix', '/page-'+ draft.pageId+ '/'+ ( draft.id || '…' ) ] ].forEach( function( entry ) {
			const row = node( 'div', 'pd-summary-row' ); row.append( node( 'span', '', entry[0] ), node( 'strong', '', String( entry[1] ) ) ); summary.appendChild( row );
		} );
	}

	function validate() {
		if( !active() ) return original.validate.call( pd.composer );
		const draft = pd.composer._draft;
		const item = preset();
		if( !/^[a-z][a-z0-9-]*$/.test( draft.id ) ) throw new Error( 'Section ID must start with a letter and use lowercase letters, numbers and hyphens.' );
		const duplicate = pd.sections().find( function( section ) { return section.htmlId === draft.id && section._clientId !== pd.composer._context.targetId } );
		if( duplicate ) throw new Error( 'Another section already uses id "'+ draft.id+ '".' );
		areaKeys( item ).forEach( function( areaKey ) {
			const area = item.areas[areaKey];
			const areaDraft = draft.areas[areaKey];
			areaDraft.components.forEach( function( component ) {
				if( component.type === 'template' && !( component.bindings && component.bindings.path ) )
					throw new Error( 'Choose a reusable template for '+ area.label+ '.' );
				const definition = componentDefinition( item, area, component );
				Object.keys( definition.properties || {} ).forEach( function( property ) {
					const source = bindingSource( component, property );
					if( source === 'textfill' && !( pd.composer._textEntries || [] ).some( function( entry ) { return entry.key === component.bindings[property] } ) )
						throw new Error( 'Choose an existing textfill for '+ area.label+ ' · '+ definition.properties[property].label+ '.' );
					if( source === 'image' && !( pd.sectionsUI._images || [] ).some( function( image ) { return image.uri === component.bindings[property] } ) )
						throw new Error( 'Choose an existing image slot for '+ area.label+ '.' );
				} );
			} );
			if( area.source !== 'elements' ) return;
			if( !/^[a-z][a-z0-9_-]*$/.test( areaDraft.source.elementType ) ) throw new Error( 'Choose a valid Elements type for '+ area.label+ '.' );
			const existing = areaDraft.source.elementMode === 'existing' ? ( pd.sectionsUI._types || [] ).find( function( type ) { return type.type === areaDraft.source.elementType } ) : null;
			if( areaDraft.source.elementMode === 'existing' && !existing ) throw new Error( 'Choose an existing Elements type for '+ area.label+ '.' );
			areaDraft.components.forEach( function( component ) {
				const definition = componentDefinition( item, area, component );
				Object.keys( definition.properties || {} ).forEach( function( property ) {
					if( bindingSource( component, property ) !== 'field' ) return;
					const model = existing ? existing.model : area.model;
					const mapped = model && model[component.bindings[property]];
					if( !mapped || ( mapped.type === 'image' ) !== ( definition.properties[property].fieldType === 'image' ) ) throw new Error( 'Map every field in '+ area.label+ ' to a compatible Elements field.' );
				} );
			} );
		} );
	}

	function submit() {
		if( !active() ) return original.submit.call( pd.composer );
		const error = dc.getElementById('pd-composer-error');
		const submitButton = dc.getElementById('pd-compose-submit');
		const back = dc.getElementById('pd-compose-back');
		pd.composer.captureValues(); error.textContent = '';
		try { validate() } catch( exception ) { error.textContent = exception.message; return }
		submitButton.disabled = true; back.disabled = true; error.textContent = 'Preparing areas and native content…';
		let result;
		pd.api( 'library/compose', pd.composer._draft ).then( function( response ) {
			result = response;
			return ( result.content.collections || [] ).reduce( function( chain, collection ) {
				return chain.then( function() {
					if( collection.elementMode !== 'new' ) return;
					if( ( pd.sectionsUI._types || [] ).some( function( type ) { return type.type === collection.elementType } ) ) throw new Error( 'Elements type "'+ collection.elementType+ '" already exists. Choose it as an existing collection.' );
					return pd.api( 'content/type-create', { preset : result.spec.preset, area : collection.area, uri : collection.elementType, title : collection.typeTitle } ).then( function( created ) { pd.sectionsUI._types.push( { type : created.uri, title : created.title, model : created.model } ) } );
				} );
			}, Promise.resolve() );
		} ).then( function() {
			const known = ( pd.sectionsUI._images || [] ).map( function( image ) { return image.uri } );
			return Promise.all( ( result.images || [] ).filter( function( image ) { return image.mode === 'new' && !known.includes( image.key ) } ).map( function( image ) {
				return pd.api( 'content/image-create', { preset : result.spec.preset, slot : image.slot, area : image.area, component : image.component, property : image.property, uri : image.key, label : image.label } ).then( function() { pd.sectionsUI._images.push( { uri : image.key, hasImage : false } ) } );
			} ) );
		} ).then( function() {
			const items = ( result.fields || [] ).filter( function( field ) { return field.mode === 'new' } ).map( function( field ) {
				const entry = pd.composer._textEntries.find( function( item ) { return item.key === field.key } );
				return { key : field.key, value : Object.prototype.hasOwnProperty.call( pd.composer._textValues, field.key ) ? pd.composer._textValues[field.key] : field.default, create : !entry };
			} );
			return items.length ? pd.api( 'content/save', { items : items } ) : null;
		} ).then( function() {
			pd.sectionsUI.insertResult( result, pd.composer._context ); dc.getElementById('pd-composer').close(); pd.toast( pd.composer._context.mode === 'replace' ? 'Section and areas updated.' : 'Section and areas inserted.', false );
		} ).catch( function( exception ) { error.textContent = exception.message } ).finally( function() { submitButton.disabled = false; back.disabled = false } );
	}

	pd.areaComposer = {
		_areaKey : '',
		_view : 'design',
		nextComponentId : nextComponentId,
		moveComponent : moveComponent,
		areaKeys : areaKeys,
		reconcileAvailableCollections : function() {
			const item = preset();
			if( !item || Number( item.version ) !== 3 ) return;
			areaKeys( item ).forEach( function( areaKey ) {
				const area = item.areas[areaKey];
				const source = pd.composer._draft.areas[areaKey].source;
				if( area.source !== 'elements' || source.elementMode !== 'new' ) return;
				if( ( pd.sectionsUI._types || [] ).some( function( type ) { return type.type === source.elementType } ) ) {
					source.elementMode = 'existing';
					normalizeCollectionMappings( areaKey );
				}
			} );
		},
		addComponent : function( areaKey, type ) {
			const item = preset();
			const area = item.areas[areaKey];
			const areaDraft = pd.composer._draft.areas[areaKey];
			if( !area.allowed.includes( type ) || areaDraft.components.length >= area.maxComponents ) return;
			const id = nextComponentId( areaDraft.components, type );
			const definition = componentDefinition( item, area, { type : type } );
			const component = { id : id, type : type, style : definition.styles[0] || 'auto', settings : { target : 'same' }, bindings : {}, bindingSources : {} };
			Object.keys( definition.properties || {} ).forEach( function( property ) {
				if( definition.properties[property].kind === 'template' ) {
					component.bindings[property] = ''; component.bindingSources[property] = 'template';
				} else if( area.source === 'single' ) {
					component.bindings[property] = generatedKey( pd.composer._draft, component, property ); component.bindingSources[property] = 'new';
				}
				else {
					const source = areaDraft.source;
					const options = modelOptions( area, source.elementMode === 'existing' ? source.elementType : '', definition.properties[property].fieldType );
					component.bindings[property] = preferredMapping( options, component, property );
					component.bindingSources[property] = 'field';
				}
			} );
			areaDraft.components.push( component ); pd.composer.renderSettings(); pd.composer.loadTextValues(); pd.composer.renderSummary(); pd.composer.requestPreview();
		},
	};

	pd.composer.resetGeneratedBindings = resetGeneratedBindings;
	pd.composer.renderSettings = renderSettings;
	pd.composer.renderSummary = renderSummary;
	pd.composer.loadTextValues = loadTextValues;
	pd.composer.updateDraft = updateDraft;
	pd.composer.validate = validate;
	pd.composer.submit = submit;

})(window, document);
