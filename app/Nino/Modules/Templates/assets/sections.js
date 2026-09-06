/**
 *	Nino Template Builder — section canvas, inspector and HTML+ escape hatch.
 */

( function(wn,dc) {

	'use strict';

	/*	The shortcode this panel is about, spelled out here rather than inside a
		fill. A fill value is substituted into the page before \Nino\Html's
		shortcode pass runs, and the [jstext] payload carries the same stored
		value, so a literal '[template]' in a locale file is executed as the
		shortcode and replaced with nothing - in both languages, on both paths.
		A .js file is a static asset and is never rendered, so the token is
		safe here and the fills carry a %s.	*/
	const SHORTCODE = '[template]';

	const pd = Nino.admin.templates;

	function element( tag, className, text ) {
		const node = dc.createElement( tag );
		if( className )
			node.className = className;
		if( text !== undefined )
			node.textContent = text;
		return node;
	}

	function button( label, title, callback, className ) {
		const node = element( 'button', className || '', label );
		node.type = 'button';
		node.title = title;
		node.setAttribute( 'aria-label', title );
		node.addEventListener( 'click', function( event ) {
			event.preventDefault();
			event.stopPropagation();
			callback();
		} );
		return node;
	}

	function presetFor( spec ) {
		return spec ? pd._library.presets.find( function( preset ) { return preset.key === spec.preset } ) || null : null;
	}

	function humanize( value ) {
		return String( value || '' ).replace( /[-_]+/g, ' ' ).replace( /\b\w/g, function( char ) { return char.toUpperCase() } );
	}

	function sectionLabel( section, index ) {
		if( section.type === 'template' )
			return section.template || 'template-section-'+ ( index + 1 );
		return section.htmlId || ( section.spec && section.spec.id ) || 'section-'+ ( index + 1 );
	}

	function isAreaSpec( spec ) {
		return !!spec && Number( spec.version ) === 3 && spec.areas && typeof spec.areas === 'object';
	}

	function effectiveFrameValue( spec, preset, key ) {
		const fallback = { screen : 'off', vertical : 'middle', background : 'default', container : 'default', padding : 'default', margin : 'none', focus : '5', overlay : 'dim' };
		if( spec.frame && spec.frame[key] && spec.frame[key] !== 'auto' )
			return spec.frame[key];
		const layoutKey = spec.layout && spec.layout !== 'auto' && preset.layouts[spec.layout] ? spec.layout : preset.recommend.layout;
		const layout = preset.layouts[layoutKey] || {};
		if( layout.frame && layout.frame[key] && layout.frame[key] !== 'auto' )
			return layout.frame[key];
		if( preset.recommend.frame && preset.recommend.frame[key] && preset.recommend.frame[key] !== 'auto' )
			return preset.recommend.frame[key];
		return fallback[key] || 'auto';
	}

	function areaStyle( specArea, area ) {
		const key = specArea && specArea.style && specArea.style !== 'auto' ? specArea.style : area.recommend.style;
		return area.styles[key] || {};
	}

	function areaColumns( specArea, area ) {
		const style = areaStyle( specArea, area );
		const value = String( style.class || '' )+ ' '+ String( specArea && specArea.style || '' );
		if( /(?:nino-grid-m-25|four)/.test( value ) ) return 4;
		if( /(?:nino-grid-m-33|three)/.test( value ) ) return 3;
		if( /(?:nino-grid-m-50|two)/.test( value ) ) return 2;
		return 1;
	}

	function appendComponentPreview( wrap, type ) {
		if( type === 'image' )
			wrap.appendChild( element( 'span', 'pd-preview-media' ) );
		else if( type === 'title' )
			wrap.appendChild( element( 'span', 'pd-preview-title' ) );
		else if( type === 'subtitle' || type === 'description' || type === 'text' || type === 'price' || type === 'number' )
			wrap.appendChild( element( 'span', 'pd-preview-line' ) );
		else if( type === 'button' )
			wrap.appendChild( element( 'span', 'pd-preview-button' ) );
		else if( type === 'template' )
			wrap.appendChild( element( 'span', 'pd-preview-line pd-preview-template' ) );
	}

	function areaPreview( spec, preset ) {
		const wrap = element('div');
		Object.keys( preset.areas || {} ).forEach( function( areaKey ) {
			const area = preset.areas[areaKey];
			const specArea = spec.areas[areaKey] || {};
			const components = specArea.components || [];
			if( area.source === 'elements' ) {
				const items = element( 'div', 'pd-preview-items' );
				const columns = areaColumns( specArea, area );
				items.style.setProperty( '--pd-items', String( columns ) );
				for( let i = 0; i < columns; i++ )
					items.appendChild( element( 'span', 'pd-preview-item'+ ( components.some( function( component ) { return component.type === 'image' } ) ? ' has-image' : '' ) ) );
				wrap.appendChild( items );
				return;
			}
			components.forEach( function( component ) { appendComponentPreview( wrap, component.type ) } );
		} );
		if( wrap.childNodes.length === 0 )
			wrap.appendChild( element( 'span', 'pd-preview-line' ) );
		return Array.from( wrap.childNodes );
	}

	function preview( spec, compact ) {
		const areaPreset = isAreaSpec( spec ) ? presetFor( spec ) : null;
		if( areaPreset )
			return areaPreview( spec, areaPreset );
		const wrap = element('div');
		const header = spec && spec.header || 'title';
		const content = spec && spec.content || 'custom';
		const layout = spec && spec.layout || 'auto';

		if( header !== 'none' ) {
			if( header.includes('subtitle') )
				wrap.appendChild( element( 'span', 'pd-preview-kicker' ) );
			wrap.appendChild( element( 'span', 'pd-preview-title' ) );
			if( header.includes('description') )
				wrap.appendChild( element( 'span', 'pd-preview-line' ) );
		}

		if( content === 'text' ) {
			wrap.append( element( 'span', 'pd-preview-line' ), element( 'span', 'pd-preview-line' ) );
		} else if( [ 'media-split', 'feature-list', 'contact' ].includes( content ) ) {
			const split = element( 'div', 'pd-preview-split' );
			const media = element( 'span', 'pd-preview-media' );
			const lines = element( 'span', 'pd-preview-lines' );
			lines.append( element( 'span', 'pd-preview-title' ), element( 'span', 'pd-preview-line' ), element( 'span', 'pd-preview-line' ) );
			if( [ 'media-right', 'media-right-full' ].includes( layout ) )
				split.append( lines, media );
			else
				split.append( media, lines );
			wrap.appendChild( split );
		} else if( [ 'articles', 'articles-image', 'cards', 'profiles', 'stats', 'features', 'pricing', 'timeline' ].includes( content ) ) {
			const items = element( 'div', 'pd-preview-items' );
			items.style.setProperty( '--pd-items', layout === 'spotlight' ? '1' : ( [ '2', '3', '4' ].includes( layout ) ? layout : '3' ) );
			for( let i = 0; i < Number( items.style.getPropertyValue('--pd-items') ); i++ )
				items.appendChild( element( 'span', 'pd-preview-item' ) );
			wrap.appendChild( items );
		} else if( [ 'slider', 'media-slider' ].includes( content ) || ( content === 'testimonials' && layout === 'slider' ) ) {
			const items = element( 'div', 'pd-preview-items' );
			items.style.setProperty( '--pd-items', compact ? '2' : '2' );
			items.append( element( 'span', 'pd-preview-item' ), element( 'span', 'pd-preview-item' ) );
			wrap.appendChild( items );
		} else if( content === 'testimonials' ) {
			const items = element( 'div', 'pd-preview-items' );
			items.style.setProperty( '--pd-items', layout === 'spotlight' ? '1' : ( [ '2', '3' ].includes( layout ) ? layout : '3' ) );
			for( let i = 0; i < Number( items.style.getPropertyValue('--pd-items') ); i++ )
				items.appendChild( element( 'span', 'pd-preview-item' ) );
			wrap.appendChild( items );
		} else if( [ 'logos', 'badges', 'gallery' ].includes( content ) ) {
			const items = element( 'div', 'pd-preview-items' );
			items.style.setProperty( '--pd-items', '4' );
			for( let i = 0; i < 4; i++ )
				items.appendChild( element( 'span', 'pd-preview-item' ) );
			wrap.appendChild( items );
		} else if( [ 'lists', 'accordion', 'tabs', 'comparison', 'data-table' ].includes( content ) ) {
			for( let i = 0; i < 3; i++ )
				wrap.appendChild( element( 'span', 'pd-preview-line' ) );
		} else if( [ 'video', 'video-embed' ].includes( content ) ) {
			wrap.appendChild( element( 'span', 'pd-preview-media' ) );
		} else if( content === 'notice' ) {
			wrap.append( element( 'span', 'pd-preview-line' ), element( 'span', 'pd-preview-line' ) );
		} else if( content === 'newsletter' ) {
			wrap.append( element( 'span', 'pd-preview-line' ), element( 'span', 'pd-preview-button' ) );
		} else if( content === 'custom' ) {
			wrap.append( element( 'span', 'pd-preview-line' ), element( 'span', 'pd-preview-line' ), element( 'span', 'pd-preview-line' ) );
		}

		if( spec && spec.action && spec.action !== 'none' )
			wrap.appendChild( element( 'span', 'pd-preview-button' ) );
		if( spec && spec.action === 'dual-buttons' )
			wrap.appendChild( element( 'span', 'pd-preview-button' ) );

		return Array.from( wrap.childNodes );
	}

	function rawLabel( source, position ) {
		if( source.trim() === '' )
			return Nino.content.getText('/_admin/templates/label/raw-spacing');
		return Nino.content.getText('/_admin/templates/label/raw-frame').replace( '%d', String( position + 1 ) );
	}

	function detachMetadata( source ) {
		return String( source || '' ).replace( /[\t ]*<!--\s*nino:section\s+\{[^\r\n]*\}\s*-->[\t ]*(?:\r?\n)?/, '' );
	}

	function createCard( section, sectionIndex ) {
		const templateSection = section.type === 'template';
		const spec = section.spec;
		const preset = presetFor( spec );
		const managed = spec !== null && spec !== undefined && preset !== null;
		const areas = managed && isAreaSpec( spec );
		const card = element( 'article', 'pd-section-card'+ ( pd._selectedId === section._clientId ? ' is-selected' : '' ) );
		card.dataset.kind = templateSection ? 'template' : ( managed ? 'managed' : 'custom' );
		card.dataset.content = templateSection ? 'template' : ( managed ? ( areas ? 'areas' : spec.content ) : 'custom' );
		card.tabIndex = 0;
		card.setAttribute( 'aria-label', Nino.content.getText('/_admin/templates/label/section-aria').replace( '%s', sectionLabel( section, sectionIndex ) ) );
		card.addEventListener( 'click', function() { pd.select( section._clientId ) } );
		card.addEventListener( 'keydown', function( event ) {
			if( event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				pd.select( section._clientId );
			}
		} );

		card.appendChild( element( 'span', 'pd-section-accent' ) );
		const main = element( 'div', 'pd-section-main' );
		const copy = element( 'div', 'pd-section-copy' );
		const meta = element( 'div', 'pd-section-meta' );
		meta.appendChild( element( 'span', 'pd-badge', templateSection ? Nino.content.getText('/_admin/templates/label/badge-template') : ( managed ? Nino.adminUi.text( preset.category ) : Nino.content.getText('/_admin/templates/label/badge-custom') ) ) );
		meta.appendChild( element( 'span', 'pd-badge is-neutral', templateSection ? '[template]' : ( managed ? humanize( areas ? effectiveFrameValue( spec, preset, 'background' ) : spec.surface ) : '<section>' ) ) );
		copy.appendChild( meta );
		copy.appendChild( element( 'h3', '', sectionLabel( section, sectionIndex ) ) );
		copy.appendChild( element( 'p', '', templateSection ? ( section.path || '/templates/'+ section.template )+ '.tpl' : ( managed ? Nino.adminUi.text( preset.name ) : Nino.content.getText('/_admin/templates/hint/code-authored') ) ) );

		const bindings = element( 'div', 'pd-binding-row' );
		if( templateSection ) {
			const binding = element( 'span', 'pd-binding' );
			binding.append( element( 'b', '', '⌘' ), element( 'span', '', Nino.content.getText('/_admin/templates/label/binding-include') ) );
			bindings.appendChild( binding );
		}
		if( section.fills.length ) {
			const binding = element( 'span', 'pd-binding' );
			binding.append( element( 'b', '', 'T' ), element( 'span', '', ( section.fills.length === 1 ? Nino.content.getText('/_admin/templates/label/binding-fill') : Nino.content.getText('/_admin/templates/label/binding-fills') ).replace( '%d', String( section.fills.length ) ) ) );
			bindings.appendChild( binding );
		}
		if( section.elementTypes.length ) {
			const binding = element( 'span', 'pd-binding' );
			binding.append( element( 'b', '', 'E' ), element( 'span', '', section.elementTypes.join(', ') ) );
			bindings.appendChild( binding );
		}
		if( section.imageSlots.length ) {
			const binding = element( 'span', 'pd-binding' );
			binding.append( element( 'b', '', 'I' ), element( 'span', '', ( section.imageSlots.length === 1 ? Nino.content.getText('/_admin/templates/label/binding-slot') : Nino.content.getText('/_admin/templates/label/binding-slots') ).replace( '%d', String( section.imageSlots.length ) ) ) );
			bindings.appendChild( binding );
		}
		if( bindings.childNodes.length === 0 )
			bindings.appendChild( element( 'span', 'pd-binding', Nino.content.getText('/_admin/templates/label/binding-none') ) );
		copy.appendChild( bindings );

		const visual = element( 'div', 'pd-card-preview' );
		visual.dataset.surface = managed ? ( areas ? effectiveFrameValue( spec, preset, 'background' ) : spec.surface ) : 'default';
		if( templateSection ) {
			const templatePreview = element( 'div', 'pd-template-preview' );
			const icon = element( 'span', 'pd-template-preview-icon', section.template === 'html-header' ? 'HEAD' : ( section.template === 'html-footer' ? 'FOOT' : 'TPL' ) );
			const lines = element( 'span', 'pd-template-preview-lines' );
			lines.append( element( 'span', 'pd-preview-title' ), element( 'span', 'pd-preview-line' ) );
			templatePreview.append( icon, lines );
			visual.appendChild( templatePreview );
		} else
			preview( managed ? spec : { header : 'title', content : 'custom', action : 'none' }, true ).forEach( function( node ) { visual.appendChild( node ) } );
		main.append( copy, visual );

		const actions = element( 'div', 'pd-section-actions' );
		actions.append(
			button( '↑', Nino.content.getText('/_admin/templates/label/move-up'), function() { Nino.admin.templates.sectionsUI.move( section._clientId, -1 ) } ),
			button( '↓', Nino.content.getText('/_admin/templates/label/move-down'), function() { Nino.admin.templates.sectionsUI.move( section._clientId, 1 ) } ),
			button( '✎', templateSection ? Nino.content.getText('/_admin/templates/label/include-replace') : ( managed ? Nino.content.getText('/_admin/templates/label/edit-settings') : Nino.content.getText('/_admin/templates/label/edit-source') ), function() { Nino.admin.templates.sectionsUI.edit( section._clientId ) } ),
			button( '⎘', Nino.content.getText('/_admin/templates/label/duplicate-item'), function() { Nino.admin.templates.sectionsUI.duplicate( section._clientId ) } ),
			button( '×', Nino.content.getText('/_admin/templates/label/remove-item'), function() { Nino.admin.templates.sectionsUI.remove( section._clientId ) }, 'is-danger' )
		);

		card.append( main, actions );
		return card;
	}

	Object.assign( pd, { sectionsUI : {

		_inspectorToken : 0,
		_types : null,
		_images : null,
		_codeContext : null,

		preview : preview,
		humanize : humanize,
		detachMetadata : detachMetadata,

		renderCanvas : function() {
			const canvas = dc.getElementById('pd-canvas');
			if( !canvas || pd._current === null )
				return;
			canvas.innerHTML = '';
			const sectionCount = pd.model.sectionIndices( pd._current.segments ).length;
			let sectionIndex = 0;

			pd._current.segments.forEach( function( segment, segmentIndex ) {
				if( segment.type === 'slot' )
					return;
				if( segment.type === 'raw' ) {
					if( segment.source.trim() !== '' )
						canvas.appendChild( element( 'div', 'pd-raw-boundary', rawLabel( segment.source, segmentIndex ) ) );
					return;
				}

				canvas.appendChild( createCard( segment, sectionIndex++ ) );
				if( sectionIndex < sectionCount ) {
					const between = element( 'div', 'pd-add-between' );
					between.appendChild( button( '+', Nino.content.getText('/_admin/templates/label/add-here'), function() { pd.composer.open( { afterId : segment._clientId } ) } ) );
					canvas.appendChild( between );
				}
			} );

			if( sectionIndex === 0 ) {
				const empty = element( 'div', 'pd-empty-state' );
				empty.style.minHeight = '24rem';
				empty.append( element( 'h1', '', Nino.content.getText('/_admin/templates/empty/sections') ), element( 'p', '', Nino.content.getText('/_admin/templates/empty/sections-detail').replace( '%s', SHORTCODE ) ) );
				const actions = element( 'div', 'pd-toolbar-actions' );
				actions.style.marginTop = '1rem';
				actions.appendChild( button( Nino.content.getText('/_admin/templates/label/add-first'), Nino.content.getText('/_admin/templates/label/add-first-title'), function() { pd.composer.open( { afterId : null } ) }, 'nino-admin-btn-primary' ) );
				empty.appendChild( actions );
				canvas.appendChild( empty );
			}
		},

		move : function( clientId, direction ) {
			if( pd._current && pd.model.moveSection( pd._current.segments, clientId, direction ) ) {
				pd.setDirty( true );
				pd.sectionsUI.renderCanvas();
			}
		},

		remove : function( clientId ) {
			const section = pd.section( clientId );
			const label = section && section.type === 'template' ? section.template : ( section && ( section.htmlId || Nino.content.getText('/_admin/templates/label/without-id') ) );
			if( !section || !wn.confirm( Nino.content.getText('/_admin/templates/confirm/remove-section').replace( '%s', label ) ) )
				return;
			if( pd.model.removeSection( pd._current.segments, clientId ) ) {
				if( pd._selectedId === clientId )
					pd._selectedId = null;
				pd.setDirty( true );
				pd.sectionsUI.renderCanvas();
				pd.sectionsUI.renderInspector();
			}
		},

		edit : function( clientId ) {
			const section = pd.section( clientId );
			if( !section )
				return;
			if( section.type === 'template' )
				return pd.openInclude( { mode : 'replace', targetId : clientId } );
			if( section.spec && presetFor( section.spec ) )
				return pd.composer.open( { mode : 'replace', targetId : clientId, spec : section.spec } );
			pd.sectionsUI.openCode( { mode : 'replace', targetId : clientId, source : section.source } );
		},

		duplicate : function( clientId ) {
			const section = pd.section( clientId );
			if( !section )
				return;
			if( section.type === 'template' ) {
				const copy = Object.assign( {}, section, { _clientId : 'pd-component-'+ (++pd._clientCounter) } );
				pd.model.insertSection( pd._current.segments, copy, clientId );
				pd._selectedId = copy._clientId;
				pd.setDirty( true );
				pd.renderDocument();
				return;
			}
			const suggested = pd.model.nextId( pd._current.segments, ( section.htmlId || 'section' )+ '-copy' );
			if( section.spec && presetFor( section.spec ) )
				return pd.composer.open( { mode : 'insert', afterId : clientId, spec : Object.assign( {}, section.spec, { id : suggested } ) } );

			let source = section.source;
			if( section.htmlId )
				source = source.replace( /(\bid\s*=\s*["'])[^"']*(["'])/i, '$1'+ suggested+ '$2' );
			pd.sectionsUI.openCode( { mode : 'insert', afterId : clientId, source : source, title : Nino.content.getText('/_admin/templates/label/duplicate-source') } );
		},

		insertResult : function( result, context ) {
			const segment = Object.assign( {}, result.segment );
			segment._clientId = 'pd-component-'+ (++pd._clientCounter);

			if( context.mode === 'replace' ) {
				const current = pd.section( context.targetId );
				if( !current )
					return false;
				segment._clientId = current._clientId;
				const index = pd._current.segments.indexOf( current );
				pd._current.segments[index] = segment;
				pd._selectedId = segment._clientId;
			} else {
				pd.model.insertSection( pd._current.segments, segment, context.afterId || null );
				pd._selectedId = segment._clientId;
			}

			pd.setDirty( true );
			pd.sectionsUI.renderCanvas();
			pd.sectionsUI.renderInspector();
			return true;
		},

		openCode : function( context ) {
			const dialog = dc.getElementById('pd-code-dialog');
			pd.sectionsUI._codeContext = context;
			dc.getElementById('pd-code-title').textContent = context.title || ( context.mode === 'replace' ? Nino.content.getText('/_admin/templates/label/edit-source') : Nino.content.getText('/_admin/templates/label/insert-source') );
			const note = dc.getElementById('pd-code-note');
			note.textContent = context.detachManaged === true
				? Nino.content.getText('/_admin/templates/hint/detach')
				: Nino.content.getText('/_admin/templates/hint/one-section');
			const source = context.detachManaged === true ? detachMetadata( context.source ) : context.source;
			dc.getElementById('pd-code-source').value = source || '<section id="section-id" class="nino-section">\n\t<div class="nino-grid-row">\n\t</div>\n</section>\n';
			dc.getElementById('pd-code-error').textContent = '';
			dialog.showModal();
			dc.getElementById('pd-code-source').focus();
		},

		submitCode : function() {
			const context = pd.sectionsUI._codeContext;
			const source = dc.getElementById('pd-code-source').value;
			const message = dc.getElementById('pd-code-error');
			message.textContent = Nino.content.getText('/_admin/templates/msg/checking-section');
			pd.api( 'documents/inspect', { source : source } ).then( function( response ) {
				const duplicate = pd.sections().find( function( section ) {
					return section.htmlId && section.htmlId === response.segment.htmlId && section._clientId !== context.targetId;
				} );
				if( duplicate )
					throw new Error( Nino.content.getText('/_admin/templates/error/duplicate-id').replace( '%s', response.segment.htmlId ) );
				pd.sectionsUI.insertResult( response, context );
				dc.getElementById('pd-code-dialog').close();
				pd.toast( context.mode === 'replace' ? Nino.content.getText('/_admin/templates/msg/source-updated') : Nino.content.getText('/_admin/templates/msg/source-inserted'), false );
			} ).catch( function( error ) {
				message.textContent = error.message;
			} );
		},

		renderInspector : function() {
			const empty = dc.getElementById('pd-inspector-empty');
			const content = dc.getElementById('pd-inspector-content');
			const section = pd.selectedSection();
			const token = ++pd.sectionsUI._inspectorToken;
			content.innerHTML = '';

			if( !section ) {
				empty.classList.remove('pd-hidden');
				content.classList.add('pd-hidden');
				return;
			}

			empty.classList.add('pd-hidden');
			content.classList.remove('pd-hidden');
			const spec = section.spec;
			const preset = presetFor( spec );
			const templateSection = section.type === 'template';
			const title = element( 'div', 'pd-inspector-title' );
			const titleCopy = element('div');
			titleCopy.append(
				element( 'span', 'pd-eyebrow', templateSection ? Nino.content.getText('/_admin/templates/label/badge-template') : ( preset ? Nino.adminUi.text( preset.name ) : Nino.content.getText('/_admin/templates/label/badge-custom') ) ),
				element( 'h2', '', templateSection ? section.template : ( section.htmlId || Nino.content.getText('/_admin/templates/label/section-noid') ) )
			);
			title.append( titleCopy, button( '✎', Nino.content.getText('/_admin/templates/label/edit-section'), function() { pd.sectionsUI.edit( section._clientId ) }, 'pd-icon-button' ) );
			content.appendChild( title );

			if( templateSection ) {
				const include = element( 'section', 'pd-inspector-section' );
				include.append( element( 'h3', '', Nino.content.getText('/_admin/templates/label/include-heading').replace( '%s', SHORTCODE ) ), element( 'code', 'pd-template-code', section.source.trim() ) );
				const includeActions = element( 'div', 'pd-inspector-actions' );
				includeActions.style.marginTop = '.7rem';
				includeActions.append(
					button( Nino.content.getText('/_admin/templates/label/replace'), Nino.content.getText('/_admin/templates/label/replace-title'), function() { pd.sectionsUI.edit( section._clientId ) } ),
					button( Nino.content.getText('/_admin/templates/label/duplicate'), Nino.content.getText('/_admin/templates/label/duplicate-include'), function() { pd.sectionsUI.duplicate( section._clientId ) } )
				);
				include.appendChild( includeActions );
				content.appendChild( include );

				const templateLifecycle = element( 'section', 'pd-inspector-section' );
				templateLifecycle.appendChild( element( 'h3', '', Nino.content.getText('/_admin/templates/label/include-actions') ) );
				const templateActions = element( 'div', 'pd-inspector-actions' );
				templateActions.append(
					button( Nino.content.getText('/_admin/templates/label/moveup'), Nino.content.getText('/_admin/templates/label/moveup-include'), function() { pd.sectionsUI.move( section._clientId, -1 ) } ),
					button( Nino.content.getText('/_admin/templates/label/remove'), Nino.content.getText('/_admin/templates/label/remove-include'), function() { pd.sectionsUI.remove( section._clientId ) }, 'is-danger' )
				);
				templateLifecycle.appendChild( templateActions );
				content.appendChild( templateLifecycle );
				return;
			}

			if( preset ) {
				const structure = element( 'section', 'pd-inspector-section' );
				structure.appendChild( element( 'h3', '', Nino.content.getText('/_admin/templates/label/structure') ) );
				const grid = element( 'div', 'pd-spec-grid' );
				const details = isAreaSpec( spec )
					? [
						[ Nino.content.getText('/_admin/templates/label/background'), effectiveFrameValue( spec, preset, 'background' ) ],
						[ Nino.content.getText('/_admin/templates/label/layout'), spec.layout === 'auto' ? preset.recommend.layout : spec.layout ],
						[ Nino.content.getText('/_admin/templates/label/areas'), Object.keys( spec.areas ).length ],
						[ Nino.content.getText('/_admin/templates/label/components'), Object.keys( spec.areas ).reduce( function( count, key ) { return count + ( spec.areas[key].components || [] ).length }, 0 ) ],
						[ Nino.content.getText('/_admin/templates/label/collections'), Object.keys( preset.areas ).filter( function( key ) { return preset.areas[key].source === 'elements' } ).length ],
						[ Nino.content.getText('/_admin/templates/label/motion'), spec.pageMotion ],
					]
					: [ [ Nino.content.getText('/_admin/templates/label/surface'), spec.surface ], [ Nino.content.getText('/_admin/templates/label/header-slot'), spec.header ], [ Nino.content.getText('/_admin/templates/label/content'), spec.content ], [ Nino.content.getText('/_admin/templates/label/layout'), spec.layout ], [ Nino.content.getText('/_admin/templates/label/motion'), spec.motion ], [ Nino.content.getText('/_admin/templates/label/action'), spec.action ] ];
				details.forEach( function( item ) {
					const cell = element( 'div', 'pd-spec-item' );
					cell.append( element( 'small', '', item[0] ), element( 'strong', '', humanize( item[1] ) ) );
					grid.appendChild( cell );
				} );
				structure.appendChild( grid );
				const actions = element( 'div', 'pd-inspector-actions' );
				actions.style.marginTop = '.7rem';
				actions.append(
					button( Nino.content.getText('/_admin/templates/label/settings'), Nino.content.getText('/_admin/templates/label/edit-settings'), function() { pd.sectionsUI.edit( section._clientId ) } ),
					button( 'HTML+', Nino.content.getText('/_admin/templates/label/detach'), function() { pd.sectionsUI.openCode( { mode : 'replace', targetId : section._clientId, source : section.source, detachManaged : true } ) } )
				);
				structure.appendChild( actions );
				content.appendChild( structure );
			}

			pd.sectionsUI.renderNativeContent( content, section, token );
			pd.sectionsUI.renderResources( content, section, token );

			const lifecycle = element( 'section', 'pd-inspector-section' );
			lifecycle.appendChild( element( 'h3', '', Nino.content.getText('/_admin/templates/label/section-actions') ) );
			const actions = element( 'div', 'pd-inspector-actions' );
			actions.append(
				button( Nino.content.getText('/_admin/templates/label/duplicate'), Nino.content.getText('/_admin/templates/label/duplicate-section'), function() { pd.sectionsUI.duplicate( section._clientId ) } ),
				button( Nino.content.getText('/_admin/templates/label/remove'), Nino.content.getText('/_admin/templates/label/remove-section'), function() { pd.sectionsUI.remove( section._clientId ) }, 'is-danger' )
			);
			lifecycle.appendChild( actions );
			content.appendChild( lifecycle );
		},

		renderNativeContent : function( container, section, token ) {
			if( section.fills.length === 0 )
				return;
			const panel = element( 'section', 'pd-inspector-section' );
			panel.appendChild( element( 'h3', '', Nino.content.getText('/_admin/templates/label/native-content') ) );
			const status = element( 'p', 'nino-admin-hint', Nino.content.getText('/_admin/templates/msg/loading-fills') );
			panel.appendChild( status );
			container.appendChild( panel );

			pd.api( 'content/fields', { keys : section.fills } ).then( function( response ) {
				if( token !== pd.sectionsUI._inspectorToken )
					return;
				status.remove();
				const fields = element( 'div', 'pd-content-fields' );
				response.fields.forEach( function( entry ) {
					const field = element( 'label', 'pd-content-field' );
					const label = element('span');
					const suffix = entry.key.split('/').pop();
					label.append( element( 'b', '', humanize( suffix ) ), element( 'small', '', entry.global ? Nino.content.getText('/_admin/templates/label/fill-global') : ( entry.exists ? response.nativeLocale : Nino.content.getText('/_admin/templates/label/fill-new').replace( '%s', response.nativeLocale ) ) ) );
					const long = [ 'description', 'content', 'subtitle', 'quote', 'address' ].includes( suffix );
					const input = element( long ? 'textarea' : 'input' );
					input.value = entry.value;
					input.dataset.key = entry.key;
					input.dataset.create = entry.exists ? 'false' : 'true';
					field.append( label, input );
					fields.appendChild( field );
				} );
				panel.appendChild( fields );
				const footer = element( 'div', 'pd-content-footer' );
				const message = element( 'span', '', Nino.content.getText('/_admin/templates/hint/native-locale') );
				const save = button( Nino.content.getText('/_admin/templates/label/save-content'), Nino.content.getText('/_admin/templates/label/save-content-title'), function() {
					save.disabled = true;
					message.textContent = Nino.content.getText('/_admin/templates/msg/saving-content');
					const items = Array.from( fields.querySelectorAll('[data-key]') ).map( function( input ) { return { key : input.dataset.key, value : input.value, create : input.dataset.create === 'true' } } );
					pd.api( 'content/save', { items : items } ).then( function() {
						save.disabled = false;
						message.textContent = Nino.content.getText('/_admin/templates/msg/content-saved');
						pd.toast( Nino.content.getText('/_admin/templates/msg/content-saved-toast'), false );
					} ).catch( function( error ) {
						save.disabled = false;
						message.textContent = error.message;
					} );
				}, 'nino-admin-btn-primary' );
				footer.append( message, save );
				panel.appendChild( footer );
			} ).catch( function( error ) {
				if( token === pd.sectionsUI._inspectorToken ) {
					status.className = 'nino-admin-error';
					status.textContent = error.message;
				}
			} );
		},

		ensureTypes : function() {
			if( pd.sectionsUI._types !== null )
				return Promise.resolve( pd.sectionsUI._types );
			return pd.api( 'content/types', {} ).then( function( response ) {
				pd.sectionsUI._types = response.types || [];
				return pd.sectionsUI._types;
			} );
		},

		ensureImages : function() {
			if( pd.sectionsUI._images !== null )
				return Promise.resolve( pd.sectionsUI._images );
			return pd.api( 'content/images', {} ).then( function( response ) {
				pd.sectionsUI._images = response.slots || [];
				return pd.sectionsUI._images;
			} );
		},

		renderResources : function( container, section, token ) {
			const preset = presetFor( section.spec );
			const areaSpec = isAreaSpec( section.spec ) && preset;
			if( section.imageSlots.length ) {
				const images = element( 'section', 'pd-inspector-section' );
				images.appendChild( element( 'h3', '', Nino.content.getText('/_admin/templates/label/image-slots') ) );
				const imageList = element( 'div', 'pd-resource-list' );
				imageList.appendChild( element( 'p', 'nino-admin-hint', Nino.content.getText('/_admin/templates/msg/checking-slots') ) );
				images.appendChild( imageList );
				container.appendChild( images );

				pd.sectionsUI.ensureImages().then( function( slots ) {
					if( token !== pd.sectionsUI._inspectorToken )
						return;
					imageList.innerHTML = '';
					section.imageSlots.forEach( function( uri ) {
						const existing = slots.find( function( slot ) { return slot.uri === uri } );
						const row = element( 'div', 'pd-resource' );
						row.appendChild( element( 'code', '', uri ) );
						if( existing ) {
							const link = element( 'a', '', existing.hasImage ? Nino.content.getText('/_admin/templates/label/edit-image') : Nino.content.getText('/_admin/templates/label/upload-image') );
							link.href = pd.assetUrl( '/_admin/?tab=images' );
							row.appendChild( link );
						} else {
							const request = areaSpec ? pd.sectionsUI.areaImageRequest( section.spec, preset, uri ) : { uri : uri, label : humanize( uri.split('/').slice(-2).join(' ') ) };
							if( request ) row.appendChild( button( Nino.content.getText('/_admin/templates/label/create-slot'), Nino.content.getText('/_admin/templates/label/create-slot-title').replace( '%s', uri ), function() {
								pd.api( 'content/image-create', request ).then( function() {
									pd.sectionsUI._images.push( { uri : uri, hasImage : false } );
									pd.toast( Nino.content.getText('/_admin/templates/msg/slot-created'), false );
									pd.sectionsUI.renderInspector();
								} ).catch( function( error ) { pd.toast( error.message, true ) } );
							} ) );
							else {
								const link = element( 'a', '', Nino.content.getText('/_admin/templates/label/create-in-admin') );
								link.href = pd.assetUrl( '/_admin/?tab=images' );
								row.appendChild( link );
							}
						}
						imageList.appendChild( row );
					} );
				} ).catch( function( error ) {
					imageList.innerHTML = '';
					imageList.appendChild( element( 'p', 'nino-admin-error', error.message ) );
				} );
			}

			if( section.elementTypes.length === 0 )
				return;
			const elements = element( 'section', 'pd-inspector-section' );
			elements.appendChild( element( 'h3', '', Nino.content.getText('/_admin/templates/label/collections-heading') ) );
			const list = element( 'div', 'pd-resource-list' );
			list.appendChild( element( 'p', 'nino-admin-hint', Nino.content.getText('/_admin/templates/msg/checking-types') ) );
			elements.appendChild( list );
			container.appendChild( elements );

			pd.sectionsUI.ensureTypes().then( function( types ) {
				if( token !== pd.sectionsUI._inspectorToken )
					return;
				list.innerHTML = '';
				section.elementTypes.forEach( function( uri ) {
					const existing = types.find( function( entry ) { return entry.type === uri } );
					const row = element( 'div', 'pd-resource' );
					row.appendChild( element( 'code', '', uri ) );
					if( existing ) {
						const link = element( 'a', '', Nino.content.getText('/_admin/templates/label/edit-elements') );
						link.href = pd.assetUrl( '/_admin/#elements/'+ encodeURIComponent( uri ) );
						row.appendChild( link );
					} else if( areaSpec ) {
						const area = Object.keys( preset.areas ).find( function( key ) {
							return preset.areas[key].source === 'elements' && section.spec.areas[key] && section.spec.areas[key].source.elementType === uri;
						} );
						if( area ) row.appendChild( button( Nino.content.getText('/_admin/templates/label/create-type'), Nino.content.getText('/_admin/templates/label/create-type-title').replace( '%s', uri ), function() {
							pd.api( 'content/type-create', { preset : section.spec.preset, area : area, uri : uri, title : humanize( uri ) } ).then( function( response ) {
								pd.sectionsUI._types.push( { type : response.uri, title : response.title, model : response.model } );
								pd.toast( Nino.content.getText('/_admin/templates/msg/type-created').replace( '%s', uri ), false );
								pd.sectionsUI.renderInspector();
							} ).catch( function( error ) { pd.toast( error.message, true ) } );
						} ) );
						else {
							const link = element( 'a', '', Nino.content.getText('/_admin/templates/label/create-in-admin') );
							link.href = pd.assetUrl( '/_admin/?tab=types' );
							row.appendChild( link );
						}
					} else if( section.spec && section.spec.content ) {
						row.appendChild( button( Nino.content.getText('/_admin/templates/label/create-type'), Nino.content.getText('/_admin/templates/label/create-type-title').replace( '%s', uri ), function() {
							pd.api( 'content/type-create', { module : section.spec.content, uri : uri, title : humanize( uri ) } ).then( function( response ) {
								pd.sectionsUI._types.push( { type : response.uri, title : response.title, model : response.model } );
								pd.toast( Nino.content.getText('/_admin/templates/msg/type-created').replace( '%s', uri ), false );
								pd.sectionsUI.renderInspector();
							} ).catch( function( error ) { pd.toast( error.message, true ) } );
						} ) );
					} else {
						const link = element( 'a', '', Nino.content.getText('/_admin/templates/label/create-in-admin') );
						link.href = pd.assetUrl( '/_admin/?tab=types' );
						row.appendChild( link );
					}
					list.appendChild( row );
				} );
			} ).catch( function( error ) {
				list.innerHTML = '';
				list.appendChild( element( 'p', 'nino-admin-error', error.message ) );
			} );
		},

		areaImageRequest : function( spec, preset, uri ) {
			const generatedPrefix = '/page-'+ spec.pageId+ '/'+ spec.id+ '/';
			if( uri === generatedPrefix+ 'background' )
				return { preset : spec.preset, slot : 'background', uri : uri, label : Nino.content.getText('/_admin/templates/label/background-image') };
			for( const areaKey of Object.keys( preset.areas || {} ) ) {
				const area = preset.areas[areaKey];
				if( area.source !== 'single' || !spec.areas[areaKey] ) continue;
				for( const component of spec.areas[areaKey].components || [] ) {
					if( component.type === 'image' && component.bindings && component.bindings.src === uri && uri === generatedPrefix+ component.id )
						return { preset : spec.preset, slot : areaKey+ '.'+ component.id+ '.src', area : areaKey, component : component.id, property : 'src', uri : uri, label : Nino.adminUi.text( area.label )+ ' · '+ Nino.content.getText('/_admin/templates/label/image') };
				}
			}
			return null;
		},

		init : function() {
			const form = dc.getElementById('pd-code-form');
			if( !form )
				return;
			form.addEventListener( 'submit', function( event ) {
				event.preventDefault();
				pd.sectionsUI.submitCode();
			} );
			dc.querySelectorAll('.pd-code-close').forEach( function( close ) {
				close.addEventListener( 'click', function() { dc.getElementById('pd-code-dialog').close() } );
			} );
			const source = dc.getElementById('pd-code-source');
			source.addEventListener( 'keydown', function( event ) {
				if( event.key !== 'Tab' )
					return;
				event.preventDefault();
				const start = source.selectionStart;
				source.value = source.value.slice( 0, start )+ '\t'+ source.value.slice( source.selectionEnd );
				source.selectionStart = source.selectionEnd = start + 1;
			} );
		},
	} } );

	Nino.events.bindCallback( 'ready', pd.sectionsUI.init );

})(window, document);
