/**
 *	Nino Template Builder — application state, page documents and persistence.
 *	The workbench's Templates panel (see Admin/Admin.php).
 */

( function(wn,dc) {

	'use strict';

	wn.Nino = wn.Nino || {};
	wn.Nino.admin = wn.Nino.admin || {};

	// Attached to the workbench's namespace under the panel's uri, which is
	// how the shell finds showCurrent() when the tab is selected (see
	// _admin/assets/script.js); the other three files attach to this
	wn.Nino.admin.templates = wn.Nino.admin.templates || {};

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
			return plain.replace( /\b\w/g, function( letter ) { return letter.toUpperCase() } ) || Nino.content.getText('/_admin/templates/label/page');
		},

		slotSource : function( slot, path ) {
			return '<!-- nino:template-slot '+ slot+ ' -->\n'+ ( path ? '[template '+ path+ ']\n' : '' );
		},
	};

	Object.assign( Nino.admin.templates, {

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
		_loaded : false,

		apiCall : function( action, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : action, data : JSON.stringify( payload || {} ) } );
		},

		api : function( action, payload ) {
			return new Promise( function( resolve, reject ) {
				Nino.admin.templates.apiCall( action, payload, function( status, response ) {
					if( status >= 200 && status < 300 && response !== null )
						return resolve( response );
					const error = new Error( ( response && response.error ) || Nino.content.getText('/_admin/templates/error/request') );
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
		 *	the workbench
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
			if( Nino.admin.templates._current === null )
				return null;
			return Nino.admin.templates._current.segments.find( function( segment ) {
				return model.isComponent( segment ) && segment._clientId === clientId;
			} ) || null;
		},

		selectedSection : function() {
			return Nino.admin.templates.section( Nino.admin.templates._selectedId );
		},

		sections : function() {
			return Nino.admin.templates._current === null ? [] : Nino.admin.templates._current.segments.filter( function( segment ) { return segment.type === 'section' } );
		},

		components : function() {
			return Nino.admin.templates._current === null ? [] : Nino.admin.templates._current.segments.filter( model.isComponent );
		},

		assignClientIds : function( segments ) {
			segments.forEach( function( segment ) {
				if( model.isComponent( segment ) && !segment._clientId )
					segment._clientId = 'pd-component-'+ (++Nino.admin.templates._clientCounter);
			} );
			return segments;
		},

		select : function( clientId ) {
			Nino.admin.templates._selectedId = clientId;
			if( Nino.admin.templates.sectionsUI ) {
				Nino.admin.templates.sectionsUI.renderCanvas();
				Nino.admin.templates.sectionsUI.renderInspector();
			}
		},

		setDirty : function( dirty ) {
			if( dirty === true )
				Nino.admin.templates._changeVersion++;
			Nino.admin.templates._dirty = dirty;
			const save = dc.getElementById('pd-save');
			const state = dc.getElementById('pd-save-state');
			if( save )
				save.disabled = !dirty || Nino.admin.templates._saving || Nino.admin.templates._current === null || Nino.admin.templates._current.readonly !== null;
			if( state && Nino.admin.templates._saving === false ) {
				// toggle, not assign: this element also carries its design-system
				// class (.nino-admin-actionbar-status), which an outright
				// className write would drop
				state.classList.remove('is-error');
				state.classList.toggle( 'is-dirty', dirty );
				state.textContent = dirty ? Nino.content.getText('/_admin/templates/status/dirty') : ( Nino.admin.templates._current ? Nino.content.getText('/_admin/templates/status/saved') : '' );
			}
		},

		/**
		 *	What a reusable template is called on screen. The server sends a
		 *	slug ('frame', 'section', 'partial') rather than a word, because
		 *	the panel both shows this and compares it - a comparison against a
		 *	translated string would hold in one language only
		 *
		 *	@param		{string}	kind
		 *
		 *	@return		{string}
		 */
		includeKind : function( kind ) {
			const named = {
				frame 	: Nino.content.getText('/_admin/templates/label/kind-frame'),
				section : Nino.content.getText('/_admin/templates/label/kind-section'),
				partial : Nino.content.getText('/_admin/templates/label/kind-partial'),
			};
			return named[kind] || kind;
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
			wn.clearTimeout( Nino.admin.templates._toastTimer );
			toast.textContent = message;
			toast.className = 'is-visible'+ ( error ? ' is-error' : '' );
			Nino.admin.templates._toastTimer = wn.setTimeout( function() { toast.className = '' }, 3200 );
		},

		confirmDiscard : function() {
			return Nino.admin.templates._dirty === false || wn.confirm( Nino.content.getText('/_admin/templates/confirm/discard') );
		},

		renderPages : function() {
			const list = dc.getElementById('pd-page-list');
			const search = dc.getElementById('pd-page-search');
			if( !list )
				return;
			list.innerHTML = '';
			const query = search ? search.value : '';
			const documents = Nino.admin.templates._documents.filter( function( entry ) { return model.matchesDocument( entry, query ) } );

			if( documents.length === 0 ) {
				const empty = dc.createElement('p');
				empty.className = 'nino-admin-hint';
				empty.textContent = Nino.admin.templates._documents.length === 0 ? Nino.content.getText('/_admin/templates/empty/documents') : Nino.content.getText('/_admin/templates/empty/documents-search');
				list.appendChild( empty );
				return;
			}

			documents.forEach( function( entry ) {
				const button = dc.createElement('button');
				button.type = 'button';
				button.className = 'pd-page-button'+ ( Nino.admin.templates._current && Nino.admin.templates._current.name === entry.name ? ' is-active' : '' )+ ( entry.editable ? '' : ' is-locked' );
				button.disabled = entry.editable === false;
				button.title = entry.editable ? entry.name : Nino.content.getText('/_admin/templates/hint/readonly');

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
				count.title = Nino.content.getText('/_admin/templates/label/page-count').replace( '%s', String( entry.sections || 0 ) ).replace( '%c', String( entry.components === undefined ? entry.sections : entry.components ) );

				button.append( icon, copy, count );
				button.addEventListener( 'click', function() { Nino.admin.templates.openDocument( entry.name ) } );
				list.appendChild( button );
			} );
		},

		loadDocuments : function() {
			return Nino.admin.templates.api( 'documents/list', {} ).then( function( response ) {
				Nino.admin.templates._documents = response.documents || [];
				Nino.admin.templates.renderPages();
				return response;
			} ).catch( function( error ) {
				Nino.admin.templates.showNotice( '('+ error.status+ ') '+ error.message, true );
			} );
		},

		loadIncludes : function() {
			return Nino.admin.templates.api( 'documents/includes', {} ).then( function( response ) {
				Nino.admin.templates._includes = response.includes || [];
				Nino.admin.templates.renderIncludes();
				Nino.admin.templates.renderTemplateSettings();
				Nino.admin.templates.renderCreateTemplateSettings();
				if( Nino.admin.templates.composer )
					Nino.admin.templates.composer.libraryReady();
				return response;
			} );
		},

		populateTemplateSelect : function( select, currentPath ) {
			if( !select )
				return;
			select.innerHTML = '';
			const none = dc.createElement('option');
			none.value = '';
			none.textContent = Nino.content.getText('/_admin/templates/label/none');
			select.appendChild( none );

			const groups = {};
			Nino.admin.templates._includes.forEach( function( include ) {
				// The server sends a slug, not a word: it is compared below as well
				// as shown, and a comparison against a translated string would hold
				// in one language only (see Documents::apiIncludes())
				const kind = Nino.admin.templates.includeKind( include.kind );
				if( !groups[kind] ) {
					groups[kind] = dc.createElement('optgroup');
					groups[kind].label = kind;
					select.appendChild( groups[kind] );
				}
				const option = dc.createElement('option');
				option.value = include.path;
				option.textContent = include.name+ '.tpl'+ ( include.exists ? '' : ' · '+ Nino.content.getText('/_admin/templates/hint/file-missing') );
				groups[kind].appendChild( option );
			} );

			if( currentPath && Nino.admin.templates._includes.some( function( include ) { return include.path === currentPath } ) === false ) {
				const missing = dc.createElement('option');
				missing.value = currentPath;
				missing.textContent = currentPath.split('/').pop()+ '.tpl · '+ Nino.content.getText('/_admin/templates/hint/file-gone');
				select.appendChild( missing );
			}
			select.value = currentPath || '';
		},

		renderCreateTemplateSettings : function() {
			const header = dc.getElementById('pd-create-header');
			const footer = dc.getElementById('pd-create-footer');
			if( !header || !footer )
				return;
			Nino.admin.templates.populateTemplateSelect( header, header.dataset.value || '/templates/html-header' );
			Nino.admin.templates.populateTemplateSelect( footer, footer.dataset.value || '/templates/html-footer' );
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
			Nino.admin.templates._createNameTouched = false;
			header.dataset.value = '/templates/html-header';
			footer.dataset.value = '/templates/html-footer';
			Nino.admin.templates.renderCreateTemplateSettings();
			dc.getElementById('pd-create-vpa').value = 'off';
			dialog.showModal();
			filename.focus();
			filename.setSelectionRange( filename.value.length, filename.value.length );
		},

		createTemplate : function() {
			if( Nino.admin.templates.confirmDiscard() === false )
				return;
			const filename = dc.getElementById('pd-create-filename').value.trim();
			const displayName = dc.getElementById('pd-create-name').value.trim();
			const error = dc.getElementById('pd-create-error');
			if( model.validFilename( filename ) === false ) {
				error.textContent = Nino.content.getText('/_admin/templates/error/filename');
				return;
			}
			if( model.validDisplayName( displayName ) === false ) {
				error.textContent = Nino.content.getText('/_admin/templates/error/displayname');
				return;
			}
			error.textContent = Nino.content.getText('/_admin/templates/msg/creating');
			Nino.admin.templates.api( 'documents/create', {
				filename : filename,
				displayName : displayName,
				header : dc.getElementById('pd-create-header').value,
				footer : dc.getElementById('pd-create-footer').value,
				pageMotion : dc.getElementById('pd-create-vpa').value,
			} ).then( function( response ) {
				dc.getElementById('pd-create-dialog').close();
				return Nino.admin.templates.loadDocuments().then( function() {
					Nino.admin.templates.openDocument( response.name, true );
					Nino.admin.templates.toast( Nino.content.getText('/_admin/templates/msg/created').replace( '%s', response.filename ), false );
				} );
			} ).catch( function( exception ) {
				error.textContent = exception.message;
			} );
		},

		openInclude : function( context ) {
			if( Nino.admin.templates._current === null )
				return;
			Nino.admin.templates._includeContext = context || { mode : 'insert', afterId : Nino.admin.templates._selectedId };
			dc.getElementById('pd-include-title').textContent = Nino.admin.templates._includeContext.mode === 'replace' ? Nino.content.getText('/_admin/templates/label/include-replace') : Nino.content.getText('/_admin/templates/label/include-insert');
			dc.getElementById('pd-include-search').value = '';
			Nino.admin.templates.renderIncludes();
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
			Nino.admin.templates._includes.filter( function( include ) {
				if( include.kind === 'frame' )
					return false;
				return query === '' || ( include.name+ ' '+ include.label+ ' '+ Nino.admin.templates.includeKind( include.kind ) ).toLowerCase().includes( query );
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
				detail.textContent = include.path+ '.tpl · '+ Nino.admin.templates.includeKind( include.kind )+ ( include.exists ? '' : ' · '+ Nino.content.getText('/_admin/templates/hint/include-missing') );
				copy.append( title, detail );
				choice.append( icon, copy );
				choice.addEventListener( 'click', function() { Nino.admin.templates.insertInclude( include ) } );
				list.appendChild( choice );
			} );
			if( list.childNodes.length === 0 ) {
				const empty = dc.createElement('p');
				empty.className = 'nino-admin-hint';
				empty.textContent = Nino.content.getText('/_admin/templates/empty/includes');
				list.appendChild( empty );
			}
		},

		insertInclude : function( include, context, dialogId ) {
			context = context || Nino.admin.templates._includeContext || { mode : 'insert', afterId : null };
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
				_clientId : 'pd-component-'+ (++Nino.admin.templates._clientCounter),
			};
			if( context.mode === 'replace' ) {
				const current = Nino.admin.templates.section( context.targetId );
				if( !current )
					return;
				segment._clientId = current._clientId;
				Nino.admin.templates._current.segments[Nino.admin.templates._current.segments.indexOf( current )] = segment;
			} else
				Nino.admin.templates.model.insertSection( Nino.admin.templates._current.segments, segment, context.afterId || null );
			Nino.admin.templates._selectedId = segment._clientId;
			Nino.admin.templates.setDirty( true );
			Nino.admin.templates.renderDocument();
			const dialog = dc.getElementById( dialogId || 'pd-include-dialog' );
			if( dialog && dialog.open )
				dialog.close();
			Nino.admin.templates.toast( Nino.content.getText('/_admin/templates/msg/include-inserted').replace( '%s', include.name ), false );
		},

		templateSlot : function( name ) {
			if( Nino.admin.templates._current === null )
				return null;
			return Nino.admin.templates._current.segments.find( function( segment ) {
				return segment.type === 'slot' && segment.slot === name;
			} ) || null;
		},

		renderTemplateSettings : function() {
			const nameInput = dc.getElementById('pd-template-name');
			const deleteButton = dc.getElementById('pd-delete-template');
			if( nameInput ) {
				nameInput.value = Nino.admin.templates._current ? Nino.admin.templates._current.displayName || '' : '';
				nameInput.disabled = Nino.admin.templates._current === null || Nino.admin.templates._current.readonly !== null;
			}
			if( deleteButton )
				deleteButton.disabled = Nino.admin.templates._current === null || Nino.admin.templates._current.readonly !== null;
			[ 'header', 'footer' ].forEach( function( name ) {
				const select = dc.getElementById( 'pd-'+ name+ '-template' );
				if( !select )
					return;
				const slot = Nino.admin.templates.templateSlot( name );
				const currentPath = slot ? slot.path || '' : '';
				Nino.admin.templates.populateTemplateSelect( select, currentPath );
				select.disabled = Nino.admin.templates._current === null || Nino.admin.templates._current.readonly !== null;
			} );
		},

		setTemplateName : function( value ) {
			if( !Nino.admin.templates._current || Nino.admin.templates._current.readonly !== null || value === Nino.admin.templates._current.displayName )
				return;
			Nino.admin.templates._current.displayName = value;
			const listed = Nino.admin.templates._documents.find( function( entry ) { return entry.name === Nino.admin.templates._current.name } );
			if( listed )
				listed.displayName = value;
			Nino.admin.templates.setDirty( true );
			Nino.admin.templates.renderPages();
			dc.getElementById('pd-document-title').textContent = value || Nino.content.getText('/_admin/templates/label/unnamed');
		},

		deleteTemplate : function() {
			const current = Nino.admin.templates._current;
			if( !current )
				return;
			const warning = Nino.content.getText('/_admin/templates/confirm/delete').replace( '%s', current.filename );
			if( wn.confirm( warning ) === false )
				return;

			const button = dc.getElementById('pd-delete-template');
			button.disabled = true;
			Nino.admin.templates.api( 'documents/delete', {
				name : current.name,
				confirmName : current.name,
				revision : current.revision,
			} ).then( function( response ) {
				Nino.admin.templates._current = null;
				Nino.admin.templates._selectedId = null;
				Nino.admin.templates._dirty = false;
				dc.getElementById('pd-add-section').classList.add('pd-hidden');
				dc.getElementById('pd-page-toolbar').classList.add('pd-hidden');
				dc.getElementById('pd-canvas').classList.add('pd-hidden');
				dc.getElementById('pd-empty').classList.remove('pd-hidden');
				dc.getElementById('pd-document-title').textContent = Nino.content.getText('/_admin/templates/empty/title');
				dc.getElementById('pd-document-detail').textContent = Nino.content.getText('/_admin/templates/empty/detail');
				Nino.admin.templates.setDirty( false );
				if( Nino.admin.templates.sectionsUI )
					Nino.admin.templates.sectionsUI.renderInspector();
				return Nino.admin.templates.loadDocuments().then( function() {
					Nino.admin.templates.toast( Nino.content.getText('/_admin/templates/msg/deleted').replace( '%s', response.filename ), false );
				} );
			} ).catch( function( error ) {
				button.disabled = false;
				Nino.admin.templates.toast( error.message, true );
			} );
		},

		setTemplateSlot : function( name, path ) {
			const slot = Nino.admin.templates.templateSlot( name );
			if( !slot || ( path && Nino.admin.templates._includes.some( function( include ) { return include.path === path } ) === false ) )
				return;
			slot.path = path;
			slot.template = path ? path.split('/').pop() : '';
			slot.source = model.slotSource( name, path );
			Nino.admin.templates.setDirty( true );
			Nino.admin.templates.toast( ( path
				? Nino.content.getText('/_admin/templates/msg/slot-set').replace( '%t', slot.template )
				: Nino.content.getText('/_admin/templates/msg/slot-cleared')
			).replace( '%s', name === 'header' ? Nino.content.getText('/_admin/templates/label/header') : Nino.content.getText('/_admin/templates/label/footer') ), false );
		},

		openDocument : function( name, force ) {
			if( force !== true && Nino.admin.templates.confirmDiscard() === false )
				return;

			const token = ++Nino.admin.templates._loadToken;
			Nino.admin.templates.showNotice( Nino.content.getText('/_admin/templates/msg/loading').replace( '%s', name ), false );
			Nino.admin.templates.api( 'documents/load', { name : name } ).then( function( response ) {
				if( token !== Nino.admin.templates._loadToken )
					return;
				response.segments = Nino.admin.templates.assignClientIds( response.segments || [] );
				Nino.admin.templates._current = response;
				Nino.admin.templates._selectedId = null;
				Nino.admin.templates._pageMotion = [ 'on', 'off' ].includes( response.pageMotion ) ? response.pageMotion : 'off';
				Nino.admin.templates.setDirty( false );
				Nino.admin.templates.renderDocument();
				Nino.admin.templates.renderPages();
				Nino.admin.templates.showNotice( response.readonly || '', Boolean( response.readonly ) );
			} ).catch( function( error ) {
				if( token === Nino.admin.templates._loadToken )
					Nino.admin.templates.showNotice( '('+ error.status+ ') '+ error.message, true );
			} );
		},

			renderDocument : function() {
			const current = Nino.admin.templates._current;
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
			dc.getElementById('pd-add-section').disabled = current.readonly !== null;
			title.textContent = current.displayName || current.pageId.replace( /-/g, ' ' );
			detail.textContent = 'templates/'+ ( current.filename || current.name+ '.tpl' );

			dc.querySelectorAll('#pd-page-motion button').forEach( function( button ) {
				button.classList.toggle( 'is-active', button.dataset.value === Nino.admin.templates._pageMotion );
				button.disabled = current.readonly !== null;
			} );
			Nino.admin.templates.renderTemplateSettings();

			if( Nino.admin.templates.sectionsUI ) {
				Nino.admin.templates.sectionsUI.renderCanvas();
				Nino.admin.templates.sectionsUI.renderInspector();
			}
		},

		save : function() {
			const current = Nino.admin.templates._current;
			if( current === null || Nino.admin.templates._dirty === false || Nino.admin.templates._saving === true || current.readonly !== null )
				return;

			Nino.admin.templates._saving = true;
			const changeVersion = Nino.admin.templates._changeVersion;
			const state = dc.getElementById('pd-save-state');
			const save = dc.getElementById('pd-save');
			state.className = '';
			state.textContent = Nino.content.getText('/_admin/templates/msg/saving');
			save.disabled = true;

			const payload = {
				name : current.name,
				displayName : String( current.displayName || '' ).trim(),
				pageMotion : Nino.admin.templates._pageMotion,
				revision : current.revision,
				segments : current.segments.map( function( segment ) {
					const payload = { type : segment.type, source : segment.source };
					if( segment.type === 'slot' )
						payload.slot = segment.slot;
					return payload;
				} ),
			};

			if( model.validDisplayName( payload.displayName ) === false ) {
				Nino.admin.templates._saving = false;
				Nino.admin.templates.setDirty( true );
				state.classList.remove('is-dirty');
				state.classList.add('is-error');
				state.textContent = Nino.content.getText('/_admin/templates/error/displayname-save');
				return;
			}

			Nino.admin.templates.api( 'documents/save', payload ).then( function( response ) {
				current.revision = response.revision;
				Nino.admin.templates._saving = false;
				if( Nino.admin.templates._current !== current ) {
					Nino.admin.templates.setDirty( Nino.admin.templates._dirty );
					return;
				}
				const upToDate = Nino.admin.templates._changeVersion === changeVersion;
				if( upToDate ) {
					current.displayName = response.displayName;
					current.pageMotion = response.pageMotion;
					Nino.admin.templates._pageMotion = response.pageMotion;
				}
				Nino.admin.templates.setDirty( upToDate === false );
				const listed = Nino.admin.templates._documents.find( function( entry ) { return entry.name === current.name } );
				if( listed ) {
					listed.displayName = current.displayName;
					listed.pageMotion = Nino.admin.templates._pageMotion;
					listed.sections = Nino.admin.templates.sections().length;
					listed.components = Nino.admin.templates.components().length;
				}
				if( upToDate ) {
					Nino.admin.templates.renderTemplateSettings();
					dc.getElementById('pd-document-title').textContent = response.displayName;
				}
				Nino.admin.templates.renderPages();
				Nino.admin.templates.toast( upToDate ? Nino.content.getText('/_admin/templates/msg/saved') : Nino.content.getText('/_admin/templates/msg/saved-stale'), false );
			} ).catch( function( error ) {
				Nino.admin.templates._saving = false;
				if( Nino.admin.templates._current !== current ) {
					Nino.admin.templates.setDirty( Nino.admin.templates._dirty );
					return;
				}
				Nino.admin.templates.setDirty( true );
				state.classList.remove('is-dirty');
				state.classList.add('is-error');
				state.textContent = '('+ error.status+ ') '+ error.message;
				Nino.admin.templates.toast( error.message, true );
			} );
		},

		setPageMotion : function( value ) {
			if( !Nino.admin.templates._current || ![ 'on', 'off' ].includes( value ) || value === Nino.admin.templates._pageMotion )
				return;

			const managed = Nino.admin.templates.sections().filter( function( section ) {
				return section.spec && Nino.admin.templates._library.presets.some( function( preset ) { return preset.key === section.spec.preset } );
			} );
			const buttons = dc.querySelectorAll('#pd-page-motion button');
			buttons.forEach( function( button ) { button.disabled = true } );

			Promise.all( managed.map( function( section ) {
				return Nino.admin.templates.api( 'library/compose', Object.assign( {}, section.spec, { pageMotion : value } ) );
			} ) ).then( function( results ) {
				results.forEach( function( result, index ) {
					const clientId = managed[index]._clientId;
					Object.assign( managed[index], result.segment, { _clientId : clientId } );
				} );
				Nino.admin.templates._pageMotion = value;
				Nino.admin.templates._current.pageMotion = value;
				Nino.admin.templates.setDirty( true );
				Nino.admin.templates.renderDocument();
				Nino.admin.templates.toast( managed.length ? Nino.content.getText('/_admin/templates/msg/motion-managed') : Nino.content.getText('/_admin/templates/msg/motion-default'), false );
			} ).catch( function( error ) {
				buttons.forEach( function( button ) { button.disabled = false } );
				Nino.admin.templates.toast( error.message, true );
			} );
		},

		init : function() {
			if( !dc.getElementById('pd-app') )
				return;

			dc.getElementById('pd-save').addEventListener( 'click', Nino.admin.templates.save );
			dc.getElementById('pd-reload-pages').addEventListener( 'click', function() {
				Promise.all( [ Nino.admin.templates.loadDocuments(), Nino.admin.templates.loadIncludes() ] ).catch( function( error ) {
					Nino.admin.templates.showNotice( '('+ error.status+ ') '+ error.message, true );
				} );
			} );
			dc.getElementById('pd-new-template').addEventListener( 'click', Nino.admin.templates.openCreateTemplate );
			dc.getElementById('pd-page-search').addEventListener( 'input', Nino.admin.templates.renderPages );
			dc.getElementById('pd-add-section').addEventListener( 'click', function() {
				if( Nino.admin.templates.composer && Nino.admin.templates._current )
					Nino.admin.templates.composer.open( { afterId : Nino.admin.templates._selectedId } );
			} );
			dc.getElementById('pd-create-form').addEventListener( 'submit', function( event ) {
				event.preventDefault();
				Nino.admin.templates.createTemplate();
			} );
			dc.getElementById('pd-create-filename').addEventListener( 'input', function( event ) {
				if( Nino.admin.templates._createNameTouched === false )
					dc.getElementById('pd-create-name').value = model.displayNameFromFilename( event.target.value );
			} );
			dc.getElementById('pd-create-name').addEventListener( 'input', function() {
				Nino.admin.templates._createNameTouched = true;
			} );
			dc.querySelectorAll('.pd-create-close').forEach( function( button ) {
				button.addEventListener( 'click', function() { dc.getElementById('pd-create-dialog').close() } );
			} );
			dc.getElementById('pd-include-search').addEventListener( 'input', Nino.admin.templates.renderIncludes );
			dc.querySelectorAll('.pd-include-close').forEach( function( button ) {
				button.addEventListener( 'click', function() { dc.getElementById('pd-include-dialog').close() } );
			} );
			dc.querySelectorAll('#pd-page-motion button').forEach( function( button ) {
				button.addEventListener( 'click', function() { Nino.admin.templates.setPageMotion( button.dataset.value ) } );
			} );
			[ 'header', 'footer' ].forEach( function( name ) {
				dc.getElementById( 'pd-'+ name+ '-template' ).addEventListener( 'change', function( event ) {
					Nino.admin.templates.setTemplateSlot( name, event.target.value );
				} );
			} );
			dc.getElementById('pd-template-name').addEventListener( 'input', function( event ) {
				Nino.admin.templates.setTemplateName( event.target.value );
			} );
			dc.getElementById('pd-delete-template').addEventListener( 'click', Nino.admin.templates.deleteTemplate );

			wn.addEventListener( 'beforeunload', function( event ) {
				if( Nino.admin.templates._dirty ) {
					event.preventDefault();
					event.returnValue = '';
				}
			} );
		},

		/**
		 *	Called by the shell when the panel's tab is selected (see
		 *	_admin/assets/script.js): load the templates, the reusable
		 *	sections and the library the first time, and keep every state -
		 *	the open document, the selection, unsaved changes - across a
		 *	switch to another panel and back
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			if( Nino.admin.templates._loaded === true || !dc.getElementById('pd-app') )
				return;

			Nino.admin.templates._loaded = true;

			Promise.all( [
				Nino.admin.templates.loadDocuments(),
				Nino.admin.templates.loadIncludes(),
				Nino.admin.templates.api( 'library/list', {} ).then( function( response ) {
					Nino.admin.templates._library = response;
					if( Nino.admin.templates.composer )
						Nino.admin.templates.composer.libraryReady();
				} ),
			] ).catch( function( error ) {
				Nino.admin.templates.showNotice( '('+ error.status+ ') '+ error.message, true );
			} );
		},
	} );

	Nino.events.bindCallback( 'ready', Nino.admin.templates.init );

})(window, document);
