/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js 								Admin "Images" panel: developer-fixed image slots
 *													(/nino/html/images in config.php) - the standard directory
 *													of the workbench (Nino.adminUi.directory(): the group to
 *													narrow to, the search, the choice of what to show, the
 *													table with a picture, the name and the key of the slot, its
 *													group, its target size, where it is used and the state of its
 *													alt text in each language, and the paging) with the record of
 *													one slot over it (Nino.adminUi.record(), address
 *													#images/images/<the slot's uri>, the uri ONE part:
 *													%2Flogo). Slots can't be added/removed here, only the file
 *													each currently points to changes - or goes away.
 *
 *													Everything in the record is saved with its "Save"
 *													(or "Save and continue"): a file that is chosen is shown as
 *													a preview in the browser - a data address, which the policy of
 *													the workbench lets an <img> load - and goes to the server with
 *													the save, together with the alt texts that were changed, one
 *													request each and the picture first; what is closed or
 *													discarded has left nothing on the server. "Remove image" is an
 *													action of its own, asked about first, that acts at once. What
 *													the server refuses is said at the field it belongs to, and the
 *													old file stays where it is until the new one is written (see
 *													\Nino\Modules\Images\Admin::apiUpload()).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.images = {

		// What the last list answered, as rows: one for each slot - what the table shows of it and what the record reads
		_rows					: [],
		_locales			: [],
		_directory		: null,
		_record				: null,
		// The groups the directory was drawn for, as one string: a group that comes or goes is another select
		_shape				: '',
		_ready				: false,
		_loading			: false,
		// What the person sees of the directory - the group, the search, the choice, the sort, the page - kept while it is drawn again
		_state				: {},
		// What the templates and the slots were when the list was read (see Nino.admin.touch()): each slot says which pages use it, and a template or a slot written
		// since is not in it
		_stamp				: '',
		// The moment a slot's picture was replaced, by uri: the stored name is the same for every picture of a slot, so that the address of the picture carries it
		_stamps				: {},

		/**
		 *	Load every image slot and draw them
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('images-list') === null )
				return;

			Nino.admin.images._load( false );
		},

		/**
		 *	The panel is shown: the address names a slot - the record opens, or follows - or none - the record is closed - and the directory that was drawn
		 *	stays as it is. The slots are read again, without losing what was chosen, where a template or a slot was written since they were read
		 *
		 *	@return		void
		 */
		showCurrent : function() {

			const panel = Nino.admin.images;

			if( panel._ready === false )
				return panel.init();

			// The record reads the address itself, and answers only one that is this panel's: it closes by itself where the address leads away
			if( panel._record.follow() === true || panel._record.isOpen() === true )
				return;

			if( panel._stampNow() !== panel._stamp )
				panel._load( true );
		},

		/**
		 *	What the templates and the slots are: the two counters of the shell, as one value
		 *
		 *	@return		{string}
		 */
		_stampNow : function() {
			return Nino.admin.stamp( 'templates' )+ ':'+ Nino.admin.stamp( 'slots' );
		},

		/**
		 *	Tell the shell that a slot was written, and take it as read by this panel: what the panel wrote itself is on screen
		 *
		 *	@return		void
		 */
		_wrote : function() {

			const panel = Nino.admin.images;
			const same = panel._stampNow() === panel._stamp;

			Nino.admin.touch( 'slots' );

			if( same === true )
				panel._stamp = panel._stampNow();
		},

		/**
		 *	Call an images/* admin action - this panel's name for
		 *	Nino.adminUi.api.call(), which owns where the request goes and why
		 *	extra multipart fields (eg. a File) just work
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "images/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *	@param		{Object}		[extra]				Extra multipart fields (eg. { file : File })
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback, extra ) {
			Nino.adminUi.api.call( 'images/'+ endpoint, payload, callback, extra );
		},

		/**
		 *	Read the list. Drawn the first time with the sentence that it is loading; read again without it, the directory takes the new rows where it
		 *	stands - its group, its search, its choice, its sort and its page are what a person chose
		 *
		 *	@param		{boolean}		quiet				A list that is drawn already is read again
		 *	@param		{Function}	[then]			Called with true once the list is drawn, with false where it could not be read
		 *
		 *	@return		void
		 */
		_load : function( quiet, then ) {

			const panel = Nino.admin.images;
			const told = function( ok ) { if( typeof then === 'function' ) then( ok ) };

			if( panel._loading === true )
				return told( false );

			panel._loading = true;

			// A template written while the answer is on its way is not in it: the stamp is the one from before the request went out
			const stamp = panel._stampNow();

			if( quiet === false || panel._directory === null ) {
				panel._frame();
				panel._directory.loading( Nino.content.getText('/_admin/images/loading') );
			}

			panel._apiCall( 'list', {}, function( status, response ) {

				panel._loading = false;

				if( status !== 200 || response === null ) {
					panel._directory.fail( status, response, '/_admin/images/error/load' );
					return told( false );
				}

				panel._locales = response.locales || [];
				panel._rows = ( response.slots || [] ).map( panel._row );
				panel._stamp = stamp;
				panel._draw();
				told( true );
			} );
		},

		/**
		 *	The room the list is drawn in: the directory and the record over it
		 *
		 *	@return		void
		 */
		_frame : function() {

			const panel = Nino.admin.images;

			if( panel._directory !== null )
				return;

			const wrap = dc.getElementById('images-list');

			wrap.innerHTML = '';

			const mount = dc.createElement('div');
			mount.id = 'images-directory';
			wrap.appendChild( mount );

			panel._shape = '';
			panel._directory = panel._build( mount, panel._state );
			panel._record = panel._makeRecord();
		},

		/**
		 *	Draw what the last list answered. A group that comes or goes is another select: the directory is drawn again, as it stood
		 *
		 *	@return		void
		 */
		_draw : function() {

			const panel = Nino.admin.images;
			const shape = panel._groups().map( function( group ) { return group.key+ '='+ group.label } ).join('|')+ '#'+ panel._locales.join(',');

			if( panel._shape !== shape ) {

				panel._state = panel._directory.state();

				// A group that is gone would hide every row with nothing on screen saying why
				if( panel._groups().some( function( group ) { return group.key === panel._state.category } ) === false )
					panel._state = Object.assign( {}, panel._state, { category : '' } );

				panel._directory = panel._build( dc.getElementById('images-directory'), panel._state );
				panel._shape = shape;
			}

			// The states of the languages follow the languages the site has now
			panel._rows = panel._rows.map( panel._derive );
			panel._directory.setRows( panel._rows, { keep : true } );
			panel._ready = true;

			panel._record.follow();
		},

		/**
		 *	The key of the group of a slot: the server's, which is the page's template, the feature's key or "general" - never the name, so that two pages of one
		 *	name are two groups and a page called "General" is not the general group
		 *
		 *	@param		{Object}		group					{ key, type, name, address } as the list answers it
		 *
		 *	@return		{string}
		 */
		_groupKey : function( group ) {
			return group !== null && typeof group === 'object' && typeof group.key === 'string' && group.key !== '' ? group.key : 'general';
		},

		/**
		 *	What a group is called: the name the server gave it - the page, the feature, the area a slot belongs to, in the language of the workbench - or, for
		 *	the slots that belong to no place of their own (the logo, the head and the foot of every page), the word "General"
		 *
		 *	@param		{Object}		group					{ type, name } as the list answers it
		 *
		 *	@return		{string}
		 */
		_groupName : function( group ) {

			const name = group !== null && typeof group === 'object' && typeof group.name === 'string' ? group.name : '';

			return name === '' ? Nino.content.getText('/_admin/images/group/general') : name;
		},

		/**
		 *	The groups the rows are in, by key and with the number of slots in each: "General" first, the others in the order of their names. Where two groups
		 *	would be called the same the address of the page is added after the name - a group that is no page says its key, and the general group is the one that
		 *	keeps the plain word
		 *
		 *	@return		{Array}									[ { key, label } ]
		 */
		_groups : function() {

			const panel = Nino.admin.images;
			const found = {};

			panel._rows.forEach( function( row ) {
				found[row.group] = found[row.group] ?? { key : row.group, name : row.groupName, address : row.groupAddress, count : 0 };
				found[row.group].count++;
			} );

			const list = Object.keys( found ).map( function( key ) { return found[key] } );
			const labels = list.map( function( one ) { return one.name } );

			return list.sort( function( a, b ) {

				if( a.key === 'general' || b.key === 'general' )
					return a.key === b.key ? 0 : ( a.key === 'general' ? -1 : 1 );

				return a.name.localeCompare( b.name, undefined, { numeric : true, sensitivity : 'base' } ) || a.key.localeCompare( b.key );
			} ).map( function( one ) {

				const same = labels.filter( function( name ) { return name === one.name } ).length > 1;

				return { key : one.key, label : one.name+ ( same === true && one.key !== 'general' ? ' ('+ ( one.address !== '' ? one.address : one.key )+ ')' : '' )+ ' ('+ one.count+ ')' };
			} );
		},

		/**
		 *	The name of a language in a badge: its code's language, "DE" - and where two languages of the site share it, the whole code, "PT-BR"
		 *
		 *	@param		{string}		code
		 *
		 *	@return		{string}
		 */
		_short : function( code ) {

			const panel = Nino.admin.images;
			const language = String( code ).split('_')[0];
			const shared = panel._locales.filter( function( other ) { return String( other ).split('_')[0] === language } ).length > 1;

			return ( shared === true ? String( code ).replace( '_', '-' ) : language ).toUpperCase();
		},

		/**
		 *	The row of a slot as the list answered it: what the table shows of it and what the record reads
		 *
		 *	@param		{Object}		slot					{ uri, label, width, height, url, file, bytes, shown, alt, usage }
		 *
		 *	@return		{Object}
		 */
		_row : function( slot ) {

			const panel = Nino.admin.images;
			const pages = ( slot.usage && slot.usage.pages ) || [];
			const stamp = panel._stamps[slot.uri];

			return panel._derive( {
				key			: slot.uri,
				uri			: slot.uri,
				label		: String( slot.label ?? slot.uri ),
				group		: panel._groupKey( slot.group ),
				groupName	: panel._groupName( slot.group ),
				groupAddress : slot.group !== null && typeof slot.group === 'object' && typeof slot.group.address === 'string' ? slot.group.address : '',
				everywhere	: slot.everywhere === true,
				width		: slot.width,
				height	: slot.height,
				size		: slot.width+ ' × '+ slot.height+ ' px',
				// The address of the picture, with the moment it was replaced: the stored name is the same for every picture of the slot
				url			: typeof slot.url === 'string' && slot.url !== '' ? slot.url+ ( stamp === undefined ? '' : ( slot.url.indexOf('?') === -1 ? '?' : '&' )+ 't='+ stamp ) : '',
				file		: typeof slot.file === 'string' ? slot.file : '',
				bytes		: Number( slot.bytes ) || 0,
				shown		: slot.shown !== undefined && slot.shown !== null ? slot.shown : null,
				alt			: slot.alt !== null && typeof slot.alt === 'object' && Array.isArray( slot.alt ) === false ? slot.alt : {},
				pages		: pages.map( function( page ) { return { name : String( page.name ), httpUri : String( page.httpUri ) } } ),
			} );
		},

		/**
		 *	What a row says that follows from the rest of it: the group's name, the state of the alt text in each language of the site, where the slot is used
		 *
		 *	@param		{Object}		row
		 *
		 *	@return		{Object}									The row, with the derived values set
		 */
		_derive : function( row ) {

			const panel = Nino.admin.images;

						row.hasImage = row.url !== '';
			row.unused = row.pages.length === 0;
			// Where a slot is used is what the cell says, and the words that say that none does are what an unused slot has there: a cell with no text would be drawn as empty
			row.usedText = row.unused === true ? Nino.content.getText('/_admin/images/label/unused') : ( row.everywhere === true ? Nino.content.getText('/_admin/images/label/everywhere') : Nino.admin.images._names( row ).join(', ') );
			row.states = panel._locales.map( function( code ) {
				return { locale : code, label : panel._short( code ), name : Nino.adminUi.localeName( code, panel._locales ), ok : typeof row.alt[code] === 'string' && row.alt[code] !== '' };
			} );
			// What a slot with a picture still lacks: an alt text in a language. A slot without a picture shows nothing there is to describe
			row.altMissing = row.hasImage === true && row.states.some( function( state ) { return state.ok === false } );

			return row;
		},

		/**
		 *	The row of a key, or null
		 *
		 *	@param		{string}		key
		 *
		 *	@return		{Object|null}
		 */
		_find : function( key ) {
			return Nino.admin.images._rows.find( function( row ) { return row.key === key } ) ?? null;
		},

		/**
		 *	Put a row in the place of the row of the same key, and have the directory draw it anew where it stands
		 *
		 *	@param		{Object}		next
		 *
		 *	@return		void
		 */
		_replace : function( next ) {

			const panel = Nino.admin.images;

			panel._rows = panel._rows.map( function( row ) { return row.key === next.key ? next : row } );
			panel._directory.update( panel._rows, next.key );
		},

		/**
		 *	The directory: the group to narrow to, the search, the choice of what to show - slots without a picture, with an alt text missing, that no page
		 *	uses -, the table and its paging. There is no box to choose rows by: nothing here is done to several slots at once
		 *
		 *	@param		{Element}		mount
		 *	@param		{Object}		[state]				What the person saw before
		 *
		 *	@return		{Object}
		 */
		_build : function( mount, state ) {

			const panel = Nino.admin.images;
			const word = function( key ) { return Nino.content.getText( key ) };
			const groups = panel._groups();

			return Nino.adminUi.directory( {
				mount : mount,
				rowKey : 'key',
				state : state,
				onState : function( now ) { panel._state = now },
				columns : [
					{ key : 'url', label : word('/_admin/images/col/preview'), cell : 'thumb' },
					{ key : 'label', label : word('/_admin/images/col/name'), type : 'string', primary : true, render : panel._nameCell },
					{ key : 'groupName', label : word('/_admin/images/col/group'), type : 'string', narrow : false },
					{ key : 'size', label : word('/_admin/images/label/target'), type : 'string', narrow : false },
					{ key : 'usedText', label : word('/_admin/images/label/usedon'), type : 'string', render : panel._usedCell, absent : function( row ) { return row.unused } },
					{ key : 'states', label : word('/_admin/images/label/alt'), cell : 'locales', words : { ok : word('/_admin/images/col/alt-ok'), missing : word('/_admin/images/col/alt-missing') } },
				],
				labels : { search : word('/_admin/images/label/search'), empty : word('/_admin/images/empty'), noMatch : word('/_admin/images/nomatch') },
				// The group is the select left of the search - where there is more than one group; with one it would be a control with one option
				category : groups.length > 1 ? {
					id : 'images-group',
					label : word('/_admin/images/col/group'),
					value : '',
					options : [ { value : '', label : word('/_admin/images/label/group-all') } ].concat( groups.map( function( group ) { return { value : group.key, label : group.label } } ) ),
					match : function( row, value ) { return value === '' || row.group === value },
				} : undefined,
				filters : [ {
					id : 'images-show',
					label : word('/_admin/images/label/show'),
					value : '',
					options : [
						{ value : '', label : word('/_admin/images/label/show-all') },
						{ value : 'none', label : word('/_admin/images/label/show-none') },
						{ value : 'alt', label : word('/_admin/images/label/show-alt') },
						{ value : 'unused', label : word('/_admin/images/label/unused') },
					],
					match : function( row, value ) {
						return value === '' || ( value === 'none' ? row.hasImage === false : ( value === 'alt' ? row.altMissing === true : row.unused === true ) );
					},
				} ],
				// What a person looks for, whatever the cells show of it: the name, the key, the file, the group, every page that uses the slot - by its name and its address
				searchText : function( row ) {
					return [ row.label, row.uri, row.file, row.groupName, row.group ].concat( row.pages.map( function( page ) { return page.name+ ' '+ page.httpUri } ) ).join(' ');
				},
				actions : function( row ) {
					return [ Nino.adminUi.iconButton( 'pencil', Nino.adminUi.format( word('/_admin/images/row/open'), row.label ), function( event ) {
						panel._record.open( row.key, { opener : event.currentTarget } );
					}, { small : true } ) ];
				},
				// A row is opened by its first button as well: the row is not a stop of the Tab key where it holds buttons
				onOpen : function( row ) { panel._record.open( row.key, {} ) },
			} );
		},

		/**
		 *	The cell of the name: the name of the slot, strong, and its key under it
		 *
		 *	@param		{string}		value
		 *	@param		{Object}		row
		 *
		 *	@return		{Element}
		 */
		_nameCell : function( value, row ) {

			const cell = Nino.adminUi.el( 'span', 'images-name' );

			const label = Nino.adminUi.el( 'strong', 'images-name-label', row.label );
			const key = Nino.adminUi.el( 'span', 'images-name-key', row.uri );

			// What an ellipsis cuts is whole in the tooltip
			label.title = row.label;
			key.title = row.uri;
			cell.appendChild( label );
			cell.appendChild( key );

			return cell;
		},

		/**
		 *	The names of the pages that show a slot, each once - the address of a page that has no name
		 *
		 *	@param		{Object}		row
		 *
		 *	@return		{Array}
		 */
		_names : function( row ) {

			const names = [];

			row.pages.forEach( function( page ) {

				const name = page.name === '' ? page.httpUri : page.name;

				if( names.indexOf( name ) === -1 )
					names.push( name );
			} );

			return names;
		},

		/**
		 *	The cell of "used on": the pages that show the slot - or a badge that none does
		 *
		 *	@param		{string}		value
		 *	@param		{Object}		row
		 *
		 *	@return		{Element}
		 */
		_usedCell : function( value, row ) {

			if( row.unused === true ) {
				const badge = Nino.adminUi.el( 'span', 'nino-admin-badge', Nino.content.getText('/_admin/images/label/unused') );

				badge.dataset.state = 'warn';

				return badge;
			}

			// The pages by their names - the address of a page that has no name - and each once: two languages of a page are two routes
			const names = Nino.admin.images._names( row );

			const used = Nino.adminUi.el( 'span', 'images-used', row.everywhere === true ? Nino.content.getText('/_admin/images/label/everywhere') : names.length > 2 ? Nino.adminUi.format( Nino.content.getText('/_admin/images/label/used-more'), names.slice( 0, 2 ).join(', '), names.length - 2 ) : names.join(', ') );

			// Every page by its name, where the cell names only two of them
			used.title = names.join(', ');

			return used;
		},

		/**
		 *	The size of a stored picture and its weight, as the line under the picture says them: "500 × 100 px · 12 KB"
		 *
		 *	@param		{Object|null}	size					{ width, height }
		 *	@param		{number}		bytes
		 *
		 *	@return		{string}
		 */
		_dimensions : function( size, bytes ) {

			const parts = [];

			if( size !== null && size !== undefined )
				parts.push( size.width+ ' × '+ size.height+ ' px' );

			if( bytes > 0 )
				parts.push( bytes < 1048576 ? Math.max( 1, Math.round( bytes / 1024 ) )+ ' KB' : Nino.adminUi.megabytes( bytes )+ ' MB' );

			return parts.join(' · ');
		},

		/**
		 *	What the picture of the record is: the one the slot has - with its file, its size and its weight under it - or, where a file was chosen, the preview of
		 *	that one with what it will do said under it: nothing was sent yet
		 *
		 *	@param		{Object}		row
		 *	@param		{Object|null}	chosen				{ file, src, size } of the choice, null for none
		 *
		 *	@return		{Element}
		 */
		_frameOf : function( row, chosen ) {

			const panel = Nino.admin.images;
			const word = function( key ) { return Nino.content.getText( key ) };

			if( chosen !== null ) {

				// The weight stands beside the name in the field of the file: said once
				const facts = [ chosen.file.name ];
				const measured = panel._dimensions( chosen.size, 0 );

				if( measured !== '' )
					facts.push( measured );

				return Nino.adminUi.imageFrame( { src : chosen.src, alt : '', none : word('/_admin/images/msg/no-preview'), caption : Nino.adminUi.format( word('/_admin/images/msg/chosen'), facts.join(' · ') ) } );
			}

			const stored = row.hasImage === true ? Nino.adminUi.format( word('/_admin/images/msg/current'), [ row.file, panel._dimensions( row.shown, row.bytes ) ].filter( function( part ) { return part !== '' } ).join(' · ') ) : '';

			return Nino.adminUi.imageFrame( { src : row.url, alt : '', none : word('/_admin/images/msg/none'), caption : stored } );
		},

		/**
		 *	The record: a slot in a dialog of its own, with the step to the one before and the one after it in the rows the directory shows, "Save", "Save and
		 *	continue", and "Remove image" at the left of its bar
		 *
		 *	@return		{Object}
		 */
		_makeRecord : function() {

			const panel = Nino.admin.images;
			const word = function( key ) { return Nino.content.getText( key ) };

			return Nino.adminUi.record( {
				screen : 'images',
				// The directory it opened from, whatever it is drawn as at the moment
				directory : {
					rows : function() { return panel._directory.rows() },
					reveal : function( key ) { return panel._directory.reveal( key ) },
					focusRow : function( key ) { return panel._directory.focusRow( key ) },
					focusSearch : function() { return panel._directory.focusSearch() },
				},
				keyOf : function( row ) { return row.key },
				find : panel._find,
				title : function( row ) { return row.label },
				subtitle : function( row ) { return row.uri },
				saveNext : true,
				labels : {
					prev : word('/_admin/images/record/prev'), next : word('/_admin/images/record/next'),
					saved : word('/_admin/images/msg/saved'), deleted : word('/_admin/images/msg/removed'),
				},
				body : panel._body,
				save : panel._save,
				danger : {
					label : word('/_admin/images/label/remove'),
					// A slot that has no picture has none to take away
					hidden : function( row ) { return row.hasImage === false },
					// The slot stays and so does the record: it shows the empty place, and a picture is often chosen at once
					stay : true,
					run : panel._remove,
				},
				missing : function() { panel._directory.notice( word('/_admin/images/record/missing') ) },
			} );
		},

		/**
		 *	The content of the record: the picture, the target size, where the slot is used, the file to choose and the alt text of every language. What it keeps in
		 *	ctx - the file field, the alt texts, the place of the picture - is what the save finds there
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *
		 *	@return		{Element}
		 */
		_body : function( row, ctx ) {

			const panel = Nino.admin.images;
			const word = function( key ) { return Nino.content.getText( key ) };
			const fields = Nino.adminUi.el( 'div', 'nino-admin-dialog-fields' );
			const stage = Nino.adminUi.el( 'div', '' );
			const limits = Nino.adminUi.limits();
			const hint = Nino.adminUi.uploadHint();

			ctx.chosen = null;
			ctx.paint = function() {
				stage.innerHTML = '';
				stage.appendChild( panel._frameOf( panel._find( row.key ) ?? row, ctx.chosen ) );
			};
			ctx.paint();

			fields.appendChild( stage );
			fields.appendChild( Nino.adminUi.readLine( { label : word('/_admin/images/label/target'), value : row.size } ) );

			// Where the slot is used - or, as a notice, that no page uses it: a picture put there never appears on the website. The words carry the warning, not only the colour
			if( row.unused === true )
				fields.appendChild( Nino.adminUi.readLine( { label : word('/_admin/images/label/usedon'), node : Nino.adminUi.notice( word('/_admin/images/msg/unused') ) } ) );
			else
				fields.appendChild( Nino.adminUi.readLine( { label : word('/_admin/images/label/usedon'), value : row.everywhere === true ? word('/_admin/images/label/everywhere') : row.pages.map( function( page ) { return page.name === page.httpUri ? page.httpUri : page.name+ ' ('+ page.httpUri+ ')' } ).filter( function( text, at, all ) { return all.indexOf( text ) === at } ).join(', ') } ) );

			// A picture smaller than the target size is scaled up: said before it is sent, and - where the server tells it - after
			const warning = Nino.adminUi.notice('');

			warning.hidden = true;
			ctx.warning = warning;

			ctx.file = Nino.adminUi.fileField( {
				label : word('/_admin/images/label/image'),
				help : word('/_admin/images/hint/image'),
				note : hint === null ? undefined : hint.textContent,
				accept : '.jpg,.jpeg,.png,.webp,.gif',
				maxBytes : limits.bytes,
				onChange : function( chosen ) { panel._choose( row, ctx, chosen ) },
			} );

			fields.appendChild( ctx.file.el );
			fields.appendChild( warning );

			// An alt text for every language of the site, the working language first; empty says that the picture is decoration
			ctx.alts = Nino.adminUi.localeRows( {
				key : 'alt',
				label : word('/_admin/images/label/alt'),
				help : word('/_admin/images/hint/alt'),
				locales : panel._locales,
				values : row.alt,
				maxLength : 250,
			} );
			fields.appendChild( ctx.alts.el );

			return fields;
		},

		/**
		 *	A file was chosen - or taken away: the preview is drawn in the browser, nothing is sent. A file the server would refuse at once - too large, too many
		 *	pixels - is refused here, with the reason, at the field and is not taken
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{File|null}	file
		 *
		 *	@return		void
		 */
		_choose : function( row, ctx, file ) {

			const panel = Nino.admin.images;

			ctx.warning.hidden = true;

			if( file === null ) {
				ctx.chosen = null;
				ctx.paint();
				ctx.touch( false );
				return;
			}

			Nino.adminUi.checkImage( file, function( rejection ) {

				// The field holds what the person chose last: a check that was answered after another file was chosen is no news
				if( ctx.file.value() !== file )
					return;

				if( rejection !== null ) {
					ctx.file.clear();
					ctx.file.error( Nino.adminUi.api.errorText( 400, rejection ) );
					ctx.chosen = null;
					ctx.paint();
					ctx.touch( false );
					return;
				}

				ctx.touch();
				ctx.chosen = { file : file, src : '', size : null };
				ctx.paint();

				// The preview is the file itself, read in the browser as a data address - the policy of the workbench lets an <img> load that and not a blob: address
				// - and measured on the way
				const reader = new wn.FileReader();

				reader.onload = function() {

					if( ctx.file.value() !== file )
						return;

					// The kind of picture the browser is told is the one the name says where the system gave the file none
					const kinds = { jpg : 'image/jpeg', jpeg : 'image/jpeg', png : 'image/png', webp : 'image/webp', gif : 'image/gif' };
					const kind = kinds[ String( file.name ).split('.').pop().toLowerCase() ];
					const read = typeof reader.result === 'string' ? reader.result : '';
					const src = kind !== undefined && /^data:(?:application\/octet-stream)?;/.test( read ) === true ? 'data:'+ kind+ read.slice( read.indexOf(';') ) : read;
					const probe = dc.createElement('img');

					probe.onload = function() {

						if( ctx.file.value() !== file )
							return;

						ctx.chosen = { file : file, src : src, size : { width : probe.naturalWidth, height : probe.naturalHeight } };
						ctx.paint();

						const current = panel._find( row.key ) ?? row;

						if( probe.naturalWidth < current.width || probe.naturalHeight < current.height ) {
							ctx.warning.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/images/msg/below-pending'), probe.naturalWidth+ ' × '+ probe.naturalHeight+ ' px' );
							ctx.warning.hidden = false;
						}
					};

					// What the browser cannot decode as a picture - a text with the ending .jpg - is refused at once, at the field, as the server would refuse it, and is not taken
					probe.onerror = function() {

						if( ctx.file.value() !== file )
							return;

						ctx.file.clear();
						ctx.file.error( Nino.adminUi.api.errorText( 400, { code : 'image_type', params : [] } ) );
						ctx.chosen = null;
						ctx.paint();
						ctx.touch( false );
					};

					probe.src = src;
				};

				reader.readAsDataURL( file );
			} );
		},

		/**
		 *	The save: what was chosen and what was changed, and nothing else - a record with no change sends nothing. The picture goes first, in a request of its
		 *	own; a file the server refuses stands at the field, with the alt texts that were typed still in theirs, and nothing was written. Then the alt texts of
		 *	the languages that were changed, in one request - the server takes them all or none. A picture that was written and alt texts that were not is said
		 *	at the alt text, and the row shows the picture that is there
		 *
		 *	@param		{Object}		row
		 *	@param		{Object}		ctx
		 *	@param		{Function}	done					done( ok, { row, error } )
		 *
		 *	@return		void
		 */
		_save : function( row, ctx, done ) {

			const panel = Nino.admin.images;
			const word = function( key ) { return Nino.content.getText( key ) };
			const current = panel._find( row.key ) ?? row;
			const file = ctx.file.value();
			const typed = ctx.alts.value();
			const changed = Object.keys( typed ).filter( function( code ) { return typed[code] !== ( current.alt[code] ?? '' ) } );
			const failed = function( text, now ) { return done( false, { error : text ?? word('/_admin/images/error/save'), row : now ?? undefined } ) };

			// An answer that never came: what the server did is not known. The list is read again, the entry shows what the server holds, and the person is told so
			const lost = function( response ) { return response !== null && typeof response === 'object' && response.code === 'offline' };
			const unknown = function( where ) {
				panel._load( true, function( read ) {

					const fresh = read === true ? panel._find( current.key ) : null;

					if( fresh !== null ) {
						ctx.file.clear();
						ctx.chosen = null;
						ctx.touch( false );
						ctx.paint();
					}

					const sentence = word( read === true ? '/_admin/images/error/unknown' : '/_admin/images/error/unknown-load' );

					where( sentence );
					failed( sentence, fresh ?? undefined );
				} );
			};

			ctx.file.clearError();
			ctx.alts.clear();

			// Said before anything is sent: an alt text is one short line
			const long = changed.find( function( code ) { return typed[code].length > 250 } );

			if( long !== undefined ) {
				ctx.alts.error( long, Nino.adminUi.format( Nino.content.getText('/_admin/error/images_alt_long'), 250 ) );
				return failed();
			}

			let saved = Object.assign( {}, current );

			const finish = function() {
				saved = panel._derive( saved );
				panel._replace( saved );
				done( true, { row : saved } );
			};

			const alts = function() {

				if( changed.length === 0 )
					return finish();

				const map = {};

				changed.forEach( function( code ) { map[code] = typed[code] } );

				panel._apiCall( 'alt', { uri : current.uri, alt : map }, function( status, response ) {

					if( lost( response ) === true )
						return unknown( function( sentence ) { ctx.alts.error( changed[0], sentence ) } );

					if( status !== 200 || response === null ) {

						const reason = Nino.adminUi.api.errorText( status, response, '/_admin/images/error/alt' );

						// What was written before stays written, and is said: the entry is the one the server holds now, and the typed texts are still unsaved
						if( file !== null ) {
							const sentence = Nino.adminUi.format( word('/_admin/images/error/partial'), reason );

							ctx.alts.error( changed[0], sentence );
							return failed( sentence, panel._derive( Object.assign( {}, saved ) ) );
						}

						ctx.alts.error( changed[0], reason );
						return failed();
					}

					panel._wrote();
					saved.alt = response.alt !== null && typeof response.alt === 'object' && Array.isArray( response.alt ) === false ? response.alt : {};

					// What the server stored is what the fields hold: it is cleaned
					changed.forEach( function( code ) { ctx.alts.set( code, saved.alt[code] ?? '' ) } );
					finish();
				} );
			};

			if( file === null )
				return alts();

			panel._apiCall( 'upload', { uri : current.uri }, function( status, response ) {

				if( lost( response ) === true )
					return unknown( function( sentence ) { ctx.file.error( sentence ) } );

				if( status !== 200 || response === null ) {
					ctx.file.error( Nino.adminUi.api.errorText( status, response, '/_admin/images/error/save' ) );
					return failed();
				}

				panel._wrote();
				panel._stamps[current.uri] = Date.now();

				saved.url = response.url+ ( response.url.indexOf('?') === -1 ? '?' : '&' )+ 't='+ panel._stamps[current.uri];
				saved.file = String( response.filename );
				saved.bytes = Number( response.bytes ) || 0;
				saved.shown = { width : current.width, height : current.height };

				// What was chosen is what the slot holds now
				ctx.file.clear();
				ctx.chosen = null;
				ctx.touch( false );
				panel._replace( panel._derive( Object.assign( {}, saved ) ) );
				ctx.paint();

				// A picture smaller than the target was scaled up: said where it stays in view
				if( response.belowTarget === true && response.source ) {
					ctx.warning.textContent = Nino.adminUi.format( word('/_admin/images/msg/below-target'), response.source.width+ ' × '+ response.source.height+ ' px' );
					ctx.warning.hidden = false;
				}
				else
					ctx.warning.hidden = true;

				alts();
			}, { file : file } );
		},

		/**
		 *	Take the image out of a slot, after asking - the question names the slot and what follows: the file is deleted and the website shows nothing there. It acts
		 *	at once, as an action of its own: the save does not hold it. The record stays and shows the empty place; input that is typed and not saved is given up with
		 *	the picture it was typed for - the question says so where there is any. A cancelled question sends nothing
		 *
		 *	@param		{Object}		row
		 *	@param		{Element}		opener					The button that asked, which has the focus again when the question is closed
		 *	@param		{Function}	done						done( true ) where it was removed, done( null ) where the answer was no, done( false, { error } )
		 *
		 *	@return		void
		 */
		_remove : function( row, opener, done ) {

			const panel = Nino.admin.images;
			const word = function( key ) { return Nino.content.getText( key ) };
			const unsaved = typeof Nino.admin.dirty === 'object' && Nino.admin.dirty.isDirty( [ 'images' ] ) === true;
			const current = panel._find( row.key ) ?? row;

			Nino.adminUi.confirm( 'destructive', {
				title			: word('/_admin/images/confirm/remove-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/images/confirm/remove'), current.label )+ ( unsaved === true ? ' '+ word('/_admin/images/confirm/remove-unsaved') : '' ),
				yes				: word('/_admin/images/confirm/remove-title'),
				no				: word('/_admin/common/label/cancel'),
				nested		: true,
				opener		: opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return done( null );

					panel._apiCall( 'remove', { uri : current.uri }, function( status, response ) {

						if( status !== 200 || response === null )
							return done( false, { error : Nino.adminUi.api.errorText( status, response, '/_admin/images/error/remove' ) } );

						panel._wrote();
						delete panel._stamps[current.uri];

						panel._replace( panel._derive( Object.assign( {}, current, { url : '', file : '', bytes : 0, shown : null } ) ) );
						done( true );
					} );
				},
			} );
		},
	};

})(window, document, document.documentElement, document.body);
