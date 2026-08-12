/**
 *	Nino Template Builder — application state, page documents and persistence.
 */

( function(wn,dc) {

	'use strict';

	wn.Nino = wn.Nino || {};
	wn.Nino.templates = wn.Nino.templates || {};

	const model = {
		isComponent : function( segment ) {
			return segment && [ 'section', 'template' ].includes( segment.type );
		},

		sectionIndices : function( segments ) {
			return segments.reduce( function( indices, segment, index ) {
				if( model.isComponent( segment ) )
					indices.push( index );
				return indices;
			}, [] );
		},

		moveSection : function( segments, clientId, direction ) {
			const slots = model.sectionIndices( segments );
			const at = slots.findIndex( function( index ) { return segments[index]._clientId === clientId } );
			const target = at + direction;
			if( at < 0 || target < 0 || target >= slots.length )
				return false;
			const left = slots[at];
			const right = slots[target];
			const swap = segments[left];
			segments[left] = segments[right];
			segments[right] = swap;
			return true;
		},

		removeSection : function( segments, clientId ) {
			const index = segments.findIndex( function( segment ) { return model.isComponent( segment ) && segment._clientId === clientId } );
			if( index < 0 )
				return false;
			segments.splice( index, 1 );
			return true;
		},

		insertSection : function( segments, segment, afterClientId ) {
			if( afterClientId === null ) {
				const slots = model.sectionIndices( segments );
				const footer = segments.findIndex( function( entry ) { return entry.type === 'slot' && entry.slot === 'footer' } );
				const index = footer >= 0
					? footer
					: ( slots.length === 0 ? segments.length : slots[slots.length - 1] + 1 );
				segments.splice( index, 0, segment );
				return index;
			}
			const index = segments.findIndex( function( entry ) { return model.isComponent( entry ) && entry._clientId === afterClientId } );
			if( index < 0 )
				return model.insertSection( segments, segment, null );
			segments.splice( index + 1, 0, segment );
			return index + 1;
		},

		nextId : function( segments, base ) {
			const used = new Set( segments.filter( function( segment ) { return segment.type === 'section' } ).map( function( segment ) { return segment.htmlId } ).filter( Boolean ) );
			const clean = String( base || 'section' ).toLowerCase().replace( /[^a-z0-9-]+/g, '-' ).replace( /^[-0-9]+|-+$/g, '' ) || 'section';
			if( used.has( clean ) === false )
				return clean;
			let suffix = 2;
			while( used.has( clean+ '-'+ suffix ) )
				suffix++;
			return clean+ '-'+ suffix;
		},

		matchesDocument : function( entry, query ) {
			const needle = String( query || '' ).trim().toLowerCase();
			return needle === '' || ( ( entry.displayName || '' )+ ' '+ ( entry.filename || entry.name+ '.tpl' )+ ' '+ entry.name+ ' '+ entry.pageId ).toLowerCase().includes( needle );
		},

		validFilename : function( filename ) {
			return /^page-[A-Za-z0-9][A-Za-z0-9._-]*\.tpl$/.test( String( filename || '' ) ) && String( filename ).includes('..') === false;
		},

		validDisplayName : function( displayName ) {
			const value = String( displayName || '' ).trim();
			return value.length > 0 && value.length <= 160 && /[\x00-\x1F\x7F<>]/.test( value ) === false && value.includes('--') === false;
		},

		displayNameFromFilename : function( filename ) {
			const plain = String( filename || '' ).replace( /^page-/, '' ).replace( /\.tpl$/, '' ).replace( /[._-]+/g, ' ' ).trim();
			return plain.replace( /\b\w/g, function( letter ) { return letter.toUpperCase() } ) || 'Page';
		},

		slotSource : function( slot, path ) {
			return '<!-- nino:template-slot '+ slot+ ' -->\n'+ ( path ? '[template '+ path+ ']\n' : '' );
		},
	};

	Object.assign( Nino.templates, {

		model : model,
		_documents : [],
		_includes : [],
		_library : { presets : [], modules : [], choices : {} },
		_current : null,
		_selectedId : null,
		_dirty : false,
		_saving : false,
		_changeVersion : 0,
		_loadToken : 0,
		_clientCounter : 0,
		_pageMotion : 'off',
		_createNameTouched : false,
		_toastTimer : null,

		apiCall : function( action, payload, callback ) {
			Nino.http.sendRequest( '/_templates/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : action, data : JSON.stringify( payload || {} ) } );
		},

		api : function( action, payload ) {
			return new Promise( function( resolve, reject ) {
				Nino.templates.apiCall( action, payload, function( status, response ) {
					if( status >= 200 && status < 300 && response !== null )
						return resolve( response );
					const error = new Error( ( response && response.error ) || 'Request failed.' );
					error.status = status;
					error.response = response;
					reject( error );
				} );
			} );
		},

		assetUrl : function( path ) {
			const app = dc.getElementById('pd-app');
			return ( app ? app.dataset.dir || '' : '' )+ path;
		},

		/**
		 *	The url of something a browser loads directly - the bundled
		 *	stylesheet a preview renders with, an uploaded image. Those live
		 *	under the public content directory, one level below the project
		 *	root (see \Nino\Filesystem::getPublicDir()), unlike a link into
		 *	/_admin
		 *
		 *	@param		{string}	path			Eg. '/.cache/style.css'
		 *
		 *	@return		{string}
		 */
		publicUrl : function( path ) {
			const app = dc.getElementById('pd-app');
			return ( app ? app.dataset.public || '' : '' )+ path;
		},

		section : function( clientId ) {
			if( Nino.templates._current === null )
				return null;
			return Nino.templates._current.segments.find( function( segment ) {
				return model.isComponent( segment ) && segment._clientId === clientId;
			} ) || null;
		},

		selectedSection : function() {
			return Nino.templates.section( Nino.templates._selectedId );
		},

		sections : function() {
			return Nino.templates._current === null ? [] : Nino.templates._current.segments.filter( function( segment ) { return segment.type === 'section' } );
		},

		components : function() {
			return Nino.templates._current === null ? [] : Nino.templates._current.segments.filter( model.isComponent );
		},

		assignClientIds : function( segments ) {
			segments.forEach( function( segment ) {
				if( model.isComponent( segment ) && !segment._clientId )
					segment._clientId = 'pd-component-'+ (++Nino.templates._clientCounter);
			} );
			return segments;
		},

		select : function( clientId ) {
			Nino.templates._selectedId = clientId;
			if( Nino.templates.sectionsUI ) {
				Nino.templates.sectionsUI.renderCanvas();
				Nino.templates.sectionsUI.renderInspector();
			}
		},

		setDirty : function( dirty ) {
			if( dirty === true )
				Nino.templates._changeVersion++;
			Nino.templates._dirty = dirty;
			const save = dc.getElementById('pd-save');
			const state = dc.getElementById('pd-save-state');
			if( save )
				save.disabled = !dirty || Nino.templates._saving || Nino.templates._current === null || Nino.templates._current.readonly !== null;
			if( state && Nino.templates._saving === false ) {
				state.className = dirty ? 'is-dirty' : '';
				state.textContent = dirty ? 'Unsaved template changes' : ( Nino.templates._current ? 'All template changes saved' : '' );
			}
		},

		showNotice : function( message, error ) {
			const wrap = dc.getElementById('pd-notice');
			if( !wrap )
				return;
			wrap.innerHTML = '';
			if( !message )
				return;
			const notice = dc.createElement('div');
			notice.className = 'pd-notice'+ ( error ? ' is-error' : '' );
			notice.textContent = message;
			wrap.appendChild( notice );
		},

		toast : function( message, error ) {
			const toast = dc.getElementById('pd-toast');
			if( !toast )
				return;
			wn.clearTimeout( Nino.templates._toastTimer );
			toast.textContent = message;
			toast.className = 'is-visible'+ ( error ? ' is-error' : '' );
			Nino.templates._toastTimer = wn.setTimeout( function() { toast.className = '' }, 3200 );
		},

		confirmDiscard : function() {
			return Nino.templates._dirty === false || wn.confirm( 'Discard the unsaved template changes?' );
		},

		renderPages : function() {
			const list = dc.getElementById('pd-page-list');
			const search = dc.getElementById('pd-page-search');
			if( !list )
				return;
			list.innerHTML = '';
			const query = search ? search.value : '';
			const documents = Nino.templates._documents.filter( function( entry ) { return model.matchesDocument( entry, query ) } );

			if( documents.length === 0 ) {
				const empty = dc.createElement('p');
				empty.className = 'admin-hint';
				empty.textContent = Nino.templates._documents.length === 0 ? 'No page-*.tpl templates found.' : 'No matching templates.';
				list.appendChild( empty );
				return;
			}

			documents.forEach( function( entry ) {
				const button = dc.createElement('button');
				button.type = 'button';
				button.className = 'pd-page-button'+ ( Nino.templates._current && Nino.templates._current.name === entry.name ? ' is-active' : '' )+ ( entry.editable ? '' : ' is-locked' );
				button.disabled = entry.editable === false;
				button.title = entry.editable ? entry.name : 'This template has an unmatched section tag and opens read-only.';

				const icon = dc.createElement('span');
				icon.className = 'pd-page-icon';
				icon.textContent = entry.editable ? '▦' : '⚠';

				const copy = dc.createElement('span');
				copy.className = 'pd-page-copy';
				const title = dc.createElement('strong');
				title.textContent = entry.displayName || entry.pageId.replace( /-/g, ' ' );
				const file = dc.createElement('small');
				file.textContent = entry.filename || entry.name+ '.tpl';
				copy.append( title, file );

				const count = dc.createElement('span');
				count.className = 'pd-page-count';
				count.textContent = String( entry.components === undefined ? entry.sections : entry.components );
				count.title = ( entry.sections || 0 )+ ' HTML section(s), '+ ( entry.components === undefined ? entry.sections : entry.components )+ ' canvas item(s)';

				button.append( icon, copy, count );
				button.addEventListener( 'click', function() { Nino.templates.openDocument( entry.name ) } );
				list.appendChild( button );
			} );
		},

		loadDocuments : function() {
			return Nino.templates.api( 'documents/list', {} ).then( function( response ) {
				Nino.templates._documents = response.documents || [];
				Nino.templates.renderPages();
				return response;
			} ).catch( function( error ) {
				Nino.templates.showNotice( '('+ error.status+ ') '+ error.message, true );
			} );
		},

		loadIncludes : function() {
			return Nino.templates.api( 'documents/includes', {} ).then( function( response ) {
				Nino.templates._includes = response.includes || [];
				Nino.templates.renderIncludes();
				Nino.templates.renderTemplateSettings();
				Nino.templates.renderCreateTemplateSettings();
				if( Nino.templates.composer )
					Nino.templates.composer.libraryReady();
				return response;
			} );
		},

		populateTemplateSelect : function( select, currentPath ) {
			if( !select )
				return;
			select.innerHTML = '';
			const none = dc.createElement('option');
			none.value = '';
			none.textContent = 'None';
			select.appendChild( none );

			const groups = {};
			Nino.templates._includes.forEach( function( include ) {
				const kind = include.kind || 'Templates';
				if( !groups[kind] ) {
					groups[kind] = dc.createElement('optgroup');
					groups[kind].label = kind;
					select.appendChild( groups[kind] );
				}
				const option = dc.createElement('option');
				option.value = include.path;
				option.textContent = include.name+ '.tpl'+ ( include.exists ? '' : ' · missing file' );
				groups[kind].appendChild( option );
			} );

			if( currentPath && Nino.templates._includes.some( function( include ) { return include.path === currentPath } ) === false ) {
				const missing = dc.createElement('option');
				missing.value = currentPath;
				missing.textContent = currentPath.split('/').pop()+ '.tpl · not found';
				select.appendChild( missing );
			}
			select.value = currentPath || '';
		},

		renderCreateTemplateSettings : function() {
			const header = dc.getElementById('pd-create-header');
			const footer = dc.getElementById('pd-create-footer');
			if( !header || !footer )
				return;
			Nino.templates.populateTemplateSelect( header, header.dataset.value || '/templates/html-header' );
			Nino.templates.populateTemplateSelect( footer, footer.dataset.value || '/templates/html-footer' );
		},

		openCreateTemplate : function() {
			const dialog = dc.getElementById('pd-create-dialog');
			const filename = dc.getElementById('pd-create-filename');
			const displayName = dc.getElementById('pd-create-name');
			const header = dc.getElementById('pd-create-header');
			const footer = dc.getElementById('pd-create-footer');
			dc.getElementById('pd-create-error').textContent = '';
			filename.value = 'page-';
			displayName.value = '';
			Nino.templates._createNameTouched = false;
			header.dataset.value = '/templates/html-header';
			footer.dataset.value = '/templates/html-footer';
			Nino.templates.renderCreateTemplateSettings();
			dc.getElementById('pd-create-vpa').value = 'off';
			dialog.showModal();
			filename.focus();
			filename.setSelectionRange( filename.value.length, filename.value.length );
		},

		createTemplate : function() {
			if( Nino.templates.confirmDiscard() === false )
				return;
			const filename = dc.getElementById('pd-create-filename').value.trim();
			const displayName = dc.getElementById('pd-create-name').value.trim();
			const error = dc.getElementById('pd-create-error');
			if( model.validFilename( filename ) === false ) {
				error.textContent = 'Use a filename such as page-services.tpl.';
				return;
			}
			if( model.validDisplayName( displayName ) === false ) {
				error.textContent = 'Enter a safe template name.';
				return;
			}
			error.textContent = 'Creating template…';
			Nino.templates.api( 'documents/create', {
				filename : filename,
				displayName : displayName,
				header : dc.getElementById('pd-create-header').value,
				footer : dc.getElementById('pd-create-footer').value,
				pageMotion : dc.getElementById('pd-create-vpa').value,
			} ).then( function( response ) {
				dc.getElementById('pd-create-dialog').close();
				return Nino.templates.loadDocuments().then( function() {
					Nino.templates.openDocument( response.name, true );
					Nino.templates.toast( response.filename+ ' created.', false );
				} );
			} ).catch( function( exception ) {
				error.textContent = exception.message;
			} );
		},

		openInclude : function( context ) {
			if( Nino.templates._current === null )
				return;
			Nino.templates._includeContext = context || { mode : 'insert', afterId : Nino.templates._selectedId };
			dc.getElementById('pd-include-title').textContent = Nino.templates._includeContext.mode === 'replace' ? 'Replace template section' : 'Insert template section';
			dc.getElementById('pd-include-search').value = '';
			Nino.templates.renderIncludes();
			dc.getElementById('pd-include-dialog').showModal();
			dc.getElementById('pd-include-search').focus();
		},

		renderIncludes : function() {
			const list = dc.getElementById('pd-include-list');
			const search = dc.getElementById('pd-include-search');
			if( !list )
				return;
			const query = ( search ? search.value : '' ).trim().toLowerCase();
			list.innerHTML = '';
			Nino.templates._includes.filter( function( include ) {
				if( include.kind === 'Page frame' )
					return false;
				return query === '' || ( include.name+ ' '+ include.label+ ' '+ include.kind ).toLowerCase().includes( query );
			} ).forEach( function( include ) {
				const choice = dc.createElement('button');
				choice.type = 'button';
				choice.className = 'pd-include-choice';
				const icon = dc.createElement('span');
				icon.className = 'pd-include-icon';
				icon.textContent = include.name === 'html-header' ? 'H' : ( include.name === 'html-footer' ? 'F' : '⌘' );
				const copy = dc.createElement('span');
				const title = dc.createElement('strong');
				title.textContent = include.label;
				const detail = dc.createElement('small');
				detail.textContent = include.path+ '.tpl · '+ include.kind+ ( include.exists ? '' : ' · file not present yet' );
				copy.append( title, detail );
				choice.append( icon, copy );
				choice.addEventListener( 'click', function() { Nino.templates.insertInclude( include ) } );
				list.appendChild( choice );
			} );
			if( list.childNodes.length === 0 ) {
				const empty = dc.createElement('p');
				empty.className = 'admin-hint';
				empty.textContent = 'No matching template sections.';
				list.appendChild( empty );
			}
		},

		insertInclude : function( include, context, dialogId ) {
			context = context || Nino.templates._includeContext || { mode : 'insert', afterId : null };
			const segment = {
				type : 'template',
				template : include.name,
				path : include.path,
				htmlId : '',
				source : '[template '+ include.path+ ']\n',
				spec : null,
				fills : [],
				elementTypes : [],
				imageSlots : [],
				_clientId : 'pd-component-'+ (++Nino.templates._clientCounter),
			};
			if( context.mode === 'replace' ) {
				const current = Nino.templates.section( context.targetId );
				if( !current )
					return;
				segment._clientId = current._clientId;
				Nino.templates._current.segments[Nino.templates._current.segments.indexOf( current )] = segment;
			} else
				Nino.templates.model.insertSection( Nino.templates._current.segments, segment, context.afterId || null );
			Nino.templates._selectedId = segment._clientId;
			Nino.templates.setDirty( true );
			Nino.templates.renderDocument();
			const dialog = dc.getElementById( dialogId || 'pd-include-dialog' );
			if( dialog && dialog.open )
				dialog.close();
			Nino.templates.toast( 'Template section '+ include.name+ ' inserted.', false );
		},

		templateSlot : function( name ) {
			if( Nino.templates._current === null )
				return null;
			return Nino.templates._current.segments.find( function( segment ) {
				return segment.type === 'slot' && segment.slot === name;
			} ) || null;
		},

		renderTemplateSettings : function() {
			const nameInput = dc.getElementById('pd-template-name');
			const deleteButton = dc.getElementById('pd-delete-template');
			if( nameInput ) {
				nameInput.value = Nino.templates._current ? Nino.templates._current.displayName || '' : '';
				nameInput.disabled = Nino.templates._current === null || Nino.templates._current.readonly !== null;
			}
			if( deleteButton )
				deleteButton.disabled = Nino.templates._current === null || Nino.templates._current.readonly !== null;
			[ 'header', 'footer' ].forEach( function( name ) {
				const select = dc.getElementById( 'pd-'+ name+ '-template' );
				if( !select )
					return;
				const slot = Nino.templates.templateSlot( name );
				const currentPath = slot ? slot.path || '' : '';
				Nino.templates.populateTemplateSelect( select, currentPath );
				select.disabled = Nino.templates._current === null || Nino.templates._current.readonly !== null;
			} );
		},

		setTemplateName : function( value ) {
			if( !Nino.templates._current || Nino.templates._current.readonly !== null || value === Nino.templates._current.displayName )
				return;
			Nino.templates._current.displayName = value;
			const listed = Nino.templates._documents.find( function( entry ) { return entry.name === Nino.templates._current.name } );
			if( listed )
				listed.displayName = value;
			Nino.templates.setDirty( true );
			Nino.templates.renderPages();
			dc.getElementById('pd-document-title').textContent = value || 'Unnamed template';
		},

		deleteTemplate : function() {
			const current = Nino.templates._current;
			if( !current )
				return;
			const warning = 'Delete "'+ current.filename+ '"?\n\nThis removes the template file. Routes that use it may break, unsaved changes are lost, and recovery requires version control or another external backup.';
			if( wn.confirm( warning ) === false )
				return;

			const button = dc.getElementById('pd-delete-template');
			button.disabled = true;
			Nino.templates.api( 'documents/delete', {
				name : current.name,
				confirmName : current.name,
				revision : current.revision,
			} ).then( function( response ) {
				Nino.templates._current = null;
				Nino.templates._selectedId = null;
				Nino.templates._dirty = false;
				dc.getElementById('pd-add-section').classList.add('pd-hidden');
				dc.getElementById('pd-page-toolbar').classList.add('pd-hidden');
				dc.getElementById('pd-canvas').classList.add('pd-hidden');
				dc.getElementById('pd-empty').classList.remove('pd-hidden');
				dc.getElementById('pd-document-title').textContent = 'No template selected';
				dc.getElementById('pd-document-detail').textContent = 'Choose or create a page template to begin.';
				Nino.templates.setDirty( false );
				if( Nino.templates.sectionsUI )
					Nino.templates.sectionsUI.renderInspector();
				return Nino.templates.loadDocuments().then( function() {
					Nino.templates.toast( response.filename+ ' deleted. Recovery is only available through version control or an external backup.', false );
				} );
			} ).catch( function( error ) {
				button.disabled = false;
				Nino.templates.toast( error.message, true );
			} );
		},

		setTemplateSlot : function( name, path ) {
			const slot = Nino.templates.templateSlot( name );
			if( !slot || ( path && Nino.templates._includes.some( function( include ) { return include.path === path } ) === false ) )
				return;
			slot.path = path;
			slot.template = path ? path.split('/').pop() : '';
			slot.source = model.slotSource( name, path );
			Nino.templates.setDirty( true );
			Nino.templates.toast( ( name === 'header' ? 'Header' : 'Footer' )+ ( path ? ' set to '+ slot.template+ '.' : ' disabled.' ), false );
		},

		openDocument : function( name, force ) {
			if( force !== true && Nino.templates.confirmDiscard() === false )
				return;

			const token = ++Nino.templates._loadToken;
			Nino.templates.showNotice( 'Loading '+ name+ '…', false );
			Nino.templates.api( 'documents/load', { name : name } ).then( function( response ) {
				if( token !== Nino.templates._loadToken )
					return;
				response.segments = Nino.templates.assignClientIds( response.segments || [] );
				Nino.templates._current = response;
				Nino.templates._selectedId = null;
				Nino.templates._pageMotion = [ 'on', 'off' ].includes( response.pageMotion ) ? response.pageMotion : 'off';
				Nino.templates.setDirty( false );
				Nino.templates.renderDocument();
				Nino.templates.renderPages();
				Nino.templates.showNotice( response.readonly || '', Boolean( response.readonly ) );
			} ).catch( function( error ) {
				if( token === Nino.templates._loadToken )
					Nino.templates.showNotice( '('+ error.status+ ') '+ error.message, true );
			} );
		},

		renderDocument : function() {
			const current = Nino.templates._current;
			const empty = dc.getElementById('pd-empty');
			const canvas = dc.getElementById('pd-canvas');
			const toolbar = dc.getElementById('pd-page-toolbar');
			const title = dc.getElementById('pd-document-title');
			const detail = dc.getElementById('pd-document-detail');
			if( !current )
				return;

			empty.classList.add('pd-hidden');
			canvas.classList.remove('pd-hidden');
			toolbar.classList.remove('pd-hidden');
			dc.getElementById('pd-add-section').classList.remove('pd-hidden');
			title.textContent = current.displayName || current.pageId.replace( /-/g, ' ' );
			detail.textContent = 'templates/'+ ( current.filename || current.name+ '.tpl' );

			dc.querySelectorAll('#pd-page-motion button').forEach( function( button ) {
				button.classList.toggle( 'is-active', button.dataset.value === Nino.templates._pageMotion );
				button.disabled = current.readonly !== null;
			} );
			Nino.templates.renderTemplateSettings();

			if( Nino.templates.sectionsUI ) {
				Nino.templates.sectionsUI.renderCanvas();
				Nino.templates.sectionsUI.renderInspector();
			}
		},

		save : function() {
			const current = Nino.templates._current;
			if( current === null || Nino.templates._dirty === false || Nino.templates._saving === true || current.readonly !== null )
				return;

			Nino.templates._saving = true;
			const changeVersion = Nino.templates._changeVersion;
			const state = dc.getElementById('pd-save-state');
			const save = dc.getElementById('pd-save');
			state.className = '';
			state.textContent = 'Saving page…';
			save.disabled = true;

			const payload = {
				name : current.name,
				displayName : String( current.displayName || '' ).trim(),
				pageMotion : Nino.templates._pageMotion,
				revision : current.revision,
				segments : current.segments.map( function( segment ) {
					const payload = { type : segment.type, source : segment.source };
					if( segment.type === 'slot' )
						payload.slot = segment.slot;
					return payload;
				} ),
			};

			if( model.validDisplayName( payload.displayName ) === false ) {
				Nino.templates._saving = false;
				Nino.templates.setDirty( true );
				state.className = 'is-error';
				state.textContent = 'Enter a safe template name before saving.';
				return;
			}

			Nino.templates.api( 'documents/save', payload ).then( function( response ) {
				current.revision = response.revision;
				Nino.templates._saving = false;
				if( Nino.templates._current !== current ) {
					Nino.templates.setDirty( Nino.templates._dirty );
					return;
				}
				const upToDate = Nino.templates._changeVersion === changeVersion;
				if( upToDate ) {
					current.displayName = response.displayName;
					current.pageMotion = response.pageMotion;
					Nino.templates._pageMotion = response.pageMotion;
				}
				Nino.templates.setDirty( upToDate === false );
				const listed = Nino.templates._documents.find( function( entry ) { return entry.name === current.name } );
				if( listed ) {
					listed.displayName = current.displayName;
					listed.pageMotion = Nino.templates._pageMotion;
					listed.sections = Nino.templates.sections().length;
					listed.components = Nino.templates.components().length;
				}
				if( upToDate ) {
					Nino.templates.renderTemplateSettings();
					dc.getElementById('pd-document-title').textContent = response.displayName;
				}
				Nino.templates.renderPages();
				Nino.templates.toast( upToDate ? 'Page template saved.' : 'Earlier changes saved; newer template changes remain unsaved.', false );
			} ).catch( function( error ) {
				Nino.templates._saving = false;
				if( Nino.templates._current !== current ) {
					Nino.templates.setDirty( Nino.templates._dirty );
					return;
				}
				Nino.templates.setDirty( true );
				state.className = 'is-error';
				state.textContent = '('+ error.status+ ') '+ error.message;
				Nino.templates.toast( error.message, true );
			} );
		},

		setPageMotion : function( value ) {
			if( !Nino.templates._current || ![ 'on', 'off' ].includes( value ) || value === Nino.templates._pageMotion )
				return;

			const managed = Nino.templates.sections().filter( function( section ) {
				return section.spec && Nino.templates._library.presets.some( function( preset ) { return preset.key === section.spec.preset } );
			} );
			const buttons = dc.querySelectorAll('#pd-page-motion button');
			buttons.forEach( function( button ) { button.disabled = true } );

			Promise.all( managed.map( function( section ) {
				return Nino.templates.api( 'library/compose', Object.assign( {}, section.spec, { pageMotion : value } ) );
			} ) ).then( function( results ) {
				results.forEach( function( result, index ) {
					const clientId = managed[index]._clientId;
					Object.assign( managed[index], result.segment, { _clientId : clientId } );
				} );
				Nino.templates._pageMotion = value;
				Nino.templates._current.pageMotion = value;
				Nino.templates.setDirty( true );
				Nino.templates.renderDocument();
				Nino.templates.toast( managed.length ? 'VPA updated for managed sections and future inserts.' : 'VPA default updated for future sections.', false );
			} ).catch( function( error ) {
				buttons.forEach( function( button ) { button.disabled = false } );
				Nino.templates.toast( error.message, true );
			} );
		},

		init : function() {
			if( !dc.getElementById('pd-app') )
				return;

			// List-level creation and Save share one predictable bottom action
			// area across all tools. The button stays hidden until a document is
			// open, just as it did inside the document-only settings toolbar.
			const addSection = dc.getElementById('pd-add-section');
			const topActions = dc.getElementById('pd-top-actions');
			dc.getElementById('pd-app').appendChild( topActions );
			addSection.classList.add('pd-hidden');
			topActions.insertBefore( addSection, dc.getElementById('pd-save') );

			dc.getElementById('pd-save').addEventListener( 'click', Nino.templates.save );
			dc.getElementById('pd-reload-pages').addEventListener( 'click', function() {
				Promise.all( [ Nino.templates.loadDocuments(), Nino.templates.loadIncludes() ] ).catch( function( error ) {
					Nino.templates.showNotice( '('+ error.status+ ') '+ error.message, true );
				} );
			} );
			dc.getElementById('pd-new-template').addEventListener( 'click', Nino.templates.openCreateTemplate );
			dc.getElementById('pd-page-search').addEventListener( 'input', Nino.templates.renderPages );
			dc.getElementById('pd-add-section').addEventListener( 'click', function() {
				if( Nino.templates.composer && Nino.templates._current )
					Nino.templates.composer.open( { afterId : Nino.templates._selectedId } );
			} );
			dc.getElementById('pd-create-form').addEventListener( 'submit', function( event ) {
				event.preventDefault();
				Nino.templates.createTemplate();
			} );
			dc.getElementById('pd-create-filename').addEventListener( 'input', function( event ) {
				if( Nino.templates._createNameTouched === false )
					dc.getElementById('pd-create-name').value = model.displayNameFromFilename( event.target.value );
			} );
			dc.getElementById('pd-create-name').addEventListener( 'input', function() {
				Nino.templates._createNameTouched = true;
			} );
			dc.querySelectorAll('.pd-create-close').forEach( function( button ) {
				button.addEventListener( 'click', function() { dc.getElementById('pd-create-dialog').close() } );
			} );
			dc.getElementById('pd-include-search').addEventListener( 'input', Nino.templates.renderIncludes );
			dc.querySelectorAll('.pd-include-close').forEach( function( button ) {
				button.addEventListener( 'click', function() { dc.getElementById('pd-include-dialog').close() } );
			} );
			dc.querySelectorAll('#pd-page-motion button').forEach( function( button ) {
				button.addEventListener( 'click', function() { Nino.templates.setPageMotion( button.dataset.value ) } );
			} );
			[ 'header', 'footer' ].forEach( function( name ) {
				dc.getElementById( 'pd-'+ name+ '-template' ).addEventListener( 'change', function( event ) {
					Nino.templates.setTemplateSlot( name, event.target.value );
				} );
			} );
			dc.getElementById('pd-template-name').addEventListener( 'input', function( event ) {
				Nino.templates.setTemplateName( event.target.value );
			} );
			dc.getElementById('pd-delete-template').addEventListener( 'click', Nino.templates.deleteTemplate );

			wn.addEventListener( 'beforeunload', function( event ) {
				if( Nino.templates._dirty ) {
					event.preventDefault();
					event.returnValue = '';
				}
			} );

			Promise.all( [
				Nino.templates.loadDocuments(),
				Nino.templates.loadIncludes(),
				Nino.templates.api( 'library/list', {} ).then( function( response ) {
					Nino.templates._library = response;
					if( Nino.templates.composer )
						Nino.templates.composer.libraryReady();
				} ),
			] ).catch( function( error ) {
				Nino.templates.showNotice( '('+ error.status+ ') '+ error.message, true );
			} );
		},
	} );

	Nino.events.bindCallback( 'ready', Nino.templates.init );

})(window, document);
