/**
 *	Nino										A compact filesystembased php framework
 *	Modules\Text						The text fills and their keys
 *	textkeys.js							What the Text panel and its Keys tab share: how the keys
 *													fall into rows, sections and fields, in what order, under
 *													which names - and the search over them. Functions over
 *													the data the server sent (see \Nino\Modules\Text\Admin::
 *													apiKeys()), pure but for what builds the DOM both tabs
 *													draw alike: the search bar and a hit, and the field of
 *													one key's value. The only thing
 *													they ask the page is the words of the workbench, through
 *													Nino.content.getText() and Nino.adminUi.slugLabel().
 *
 *													A row is what the list shows and a form edits: a page or
 *													any other template with keys of its own, the company, the
 *													words of a feature - /<namespace>/<category> of a key of
 *													the grammar, the first segment of any other. A section is
 *													one <part>, a field one key.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.textKeys = {

		// The details of a page, in the order they are shown. uri is the
		// technical one and has no word here - the Keys tab shows it by its key
		PAGE_FIELDS : [ 'name', 'title', 'description', 'uri' ],

		// The groups the list shows below its two blocks, in this order
		GENERAL_GROUPS : [ 'project', 'common', 'blocks', 'modules', 'features', 'system', 'other' ],

		// The categories of /project the project is shipped with, in this order
		PROJECT_CATEGORIES : [ 'company', 'website', 'mail' ],

		// What a plain text may be and still be one line: the most characters any of its values holds, and the
		// highest limit of its key. A short name or a technical value is a line; a sentence is a field of several
		SINGLE_LINE_LENGTH : 80,
		SINGLE_LINE_LIMIT : 200,

		/**
		 *	A text the way a search compares it: without accents, in lower case.
		 *	Both sides of a comparison go through here
		 *
		 *	@param		{string}	text
		 *
		 *	@return		{string}
		 */
		fold : function( text ) {
			return String( text ?? '' ).normalize('NFD').replace( /[̀-ͯ]/g, '' ).toLowerCase();
		},

		/**
		 *	fold() that also says where every character of the result came
		 *	from, so a match found in the folded text can be marked in the
		 *	original
		 *
		 *	@param		{string}	text
		 *
		 *	@return		{Object}									{ text, from }, from[i] the index in the original of the folded character i
		 */
		foldMapped : function( text ) {

			text = String( text ?? '' );

			let folded = '';
			const from = [];

			for( let at = 0; at < text.length; at++ ) {
				const part = Nino.admin.textKeys.fold( text.charAt( at ) );
				for( let i = 0; i < part.length; i++ )
					from.push( at );
				folded += part;
			}

			return { text : folded, from : from };
		},

		/**
		 *	A value as the text a person reads: the tags gone, the entities
		 *	decoded, white space collapsed. The end of a paragraph, a list item
		 *	and a line break are a space, the tags of a word's formatting nothing
		 *
		 *	@param		{*}				value
		 *
		 *	@return		{string}
		 */
		plain : function( value ) {

			const entities = { amp : '&', lt : '<', gt : '>', quot : '"', apos : "'", nbsp : ' ' };

			return String( value ?? '' )
				.replace( /<\/?(?:br|p|li|ul|ol)\b[^>]*>/gi, ' ' )
				.replace( /<[^>]*>/g, '' )
				.replace( /&(#x?[0-9a-f]+|[a-z]+);/gi, function( all, name ) {
					if( name.charAt(0) === '#' ) {
						const code = name.charAt(1).toLowerCase() === 'x' ? parseInt( name.slice( 2 ), 16 ) : parseInt( name.slice( 1 ), 10 );
						return code > 0 && code < 0x110000 ? String.fromCodePoint( code ) : all;
					}
					return entities[name.toLowerCase()] ?? all;
				} )
				.replace( /\s+/g, ' ' )
				.trim();
		},

		/**
		 *	The text of one key in one language: its one value if it is the
		 *	same in every language, else the one of that language
		 *
		 *	@param		{Object}	entry
		 *	@param		{string}	locale
		 *
		 *	@return		{string}									Stored as it is, '' where there is none
		 */
		valueOf : function( entry, locale ) {
			return String( ( entry.global === true ? entry.values['*'] : entry.values[locale] ) ?? '' );
		},

		/**
		 *	The text of one key in one language as its field shows it: the text as a person types it where the key is a plain
		 *	text stored with its quotes and brackets as entities (the answer's `typed`), else the stored one
		 *
		 *	@param		{Object}	entry
		 *	@param		{string}	locale
		 *
		 *	@return		{string}									'' where there is none
		 */
		typedOf : function( entry, locale ) {

			const typed = ( entry.typed ?? {} )[entry.global === true ? '*' : locale];

			return typeof typed === 'string' ? typed : Nino.admin.textKeys.valueOf( entry, locale );
		},

		/**
		 *	Whether a key still has no text in a language: one per language,
		 *	and nothing in this one
		 *
		 *	@param		{Object}	entry
		 *	@param		{string}	locale
		 *
		 *	@return		{boolean}
		 */
		isEmptyIn : function( entry, locale ) {
			return entry.global === false && Nino.admin.textKeys.plain( entry.values[locale] ) === '';
		},

		/**
		 *	Whether the value of a key is one line of text: a plain text with no line break in any language, no longer
		 *	than a line and with a limit of a line. Every other text is a field of several lines (a text with markup is
		 *	the rich-text field and is not asked)
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{boolean}
		 */
		isSingleLine : function( entry ) {

			const T = Nino.admin.textKeys;

			if( entry.html === true || entry.maxlength > T.SINGLE_LINE_LIMIT )
				return false;

			return Object.keys( entry.values ).every( function( code ) {
				const text = T.typedOf( entry, code );
				return /[\r\n]/.test( text ) === false && text.length <= T.SINGLE_LINE_LENGTH;
			} );
		},

		/**
		 *	How many characters the field of one language of a key counts to and takes: the number the server names for that
		 *	language (the answer's `limits`), the limit of the key where it names none
		 *
		 *	@param		{Object}	entry
		 *	@param		{string}	[code]								The language, '*' for the one text of a global key
		 *
		 *	@return		{number}
		 */
		limitOf : function( entry, code ) {

			const limit = ( entry.limits ?? {} )[code];

			return typeof limit === 'number' ? limit : entry.maxlength;
		},

		/**
		 *	The limit a text of one language is counted against where it is shown - in its field and in the column of the table: the limit of the key
		 *	where that is below what the language may hold (a text that was longer than the limit before it was lowered), else the language's own
		 *
		 *	@param		{Object}	entry
		 *	@param		{string}	[code]								The language, '*' for the one text of a global key
		 *
		 *	@return		{number}
		 */
		shownLimit : function( entry, code ) {

			const limit = Nino.admin.textKeys.limitOf( entry, code );

			return typeof entry.keyLimit === 'number' && entry.keyLimit < limit ? entry.keyLimit : limit;
		},

		/**
		 *	What to say at the field of a text the server turned down: the sentence of the code it names - the limit and the
		 *	length of a text that is too long - else that the text was not saved, or that the account may not change it
		 *
		 *	@param		{Object|undefined}	result			The key's result in the answer of savebatch
		 *
		 *	@return		{string}
		 */
		refusalText : function( result ) {

			if( result !== undefined && result !== null && typeof result.code === 'string' )
				return Nino.adminUi.api.errorText( 200, result, '/_admin/text/error/key' );

			return Nino.content.getText( result !== undefined && result !== null && result.error === 'not allowed' ? '/_admin/text/error/denied' : '/_admin/text/error/key' );
		},

		/**
		 *	How long a text is as its field counts it: the characters of a plain text as they stand (the text as the field shows it), and for a text
		 *	with markup the characters a person sees - the tags gone, the entities decoded, nothing collapsed -, which
		 *	is what the rich-text editor counts (the length of the text of its content) and cuts at the limit
		 *
		 *	@param		{Object}	entry
		 *	@param		{*}				value
		 *
		 *	@return		{number}
		 */
		lengthOf : function( entry, value ) {

			value = String( value ?? '' );

			// A line break is one character in a field, however the text was written
			if( entry.html !== true )
				return value.replace( /\r\n?/g, '\n' ).length;

			const entities = { amp : '&', lt : '<', gt : '>', quot : '"', apos : "'", nbsp : '\u00A0' };

			return value
				.replace( /<[^>]*>/g, '' )
				.replace( /&(#x?[0-9a-f]+|[a-z]+);/gi, function( all, name ) {
					if( name.charAt(0) === '#' ) {
						const code = name.charAt(1).toLowerCase() === 'x' ? parseInt( name.slice( 2 ), 16 ) : parseInt( name.slice( 1 ), 10 );
						return code > 0 && code < 0x110000 ? String.fromCodePoint( code ) : all;
					}
					return entities[name.toLowerCase()] ?? all;
				} ).length;
		},

		/**
		 *	What a row holds, counted in a language: how many texts, how many of them have none in it (isEmptyIn(), a key
		 *	that is one text for every language is never empty in one)
		 *
		 *	@param		{Object}	row
		 *	@param		{string}	locale
		 *
		 *	@return		{Object}									{ texts, empty }
		 */
		rowCounts : function( row, locale ) {

			const T = Nino.admin.textKeys;

			return {
				texts : row.entries.length,
				empty : row.entries.filter( function( entry ) { return T.isEmptyIn( entry, locale ) } ).length,
			};
		},

		/**
		 *	Sort the keys into rows, blocks and groups.
		 *
		 *	A row is, in this order of asking:
		 *
		 *	  - a page: the template of a stored route and everything the
		 *	    templates of the pages' kind carry, with the details of the
		 *	    route in front. A template without a category (the
		 *	    catalogue) has one row per route and only those details
		 *	  - the keys of a template, of /template/common, of a category of
		 *	    /project, of a module or a feature
		 *	  - the details of pages without a stored route, the names of the
		 *	    languages
		 *	  - the keys a project made up, by their first segment
		 *
		 *	/_admin keys are not text of the site and are left out - as are the paths
		 *	of the pages - unless the caller says it wants them (admin), as the Keys
		 *	tab does: it is where somebody comes to see what is hidden.
		 *
		 *	@param		{Object}	data
		 *													entries			the keys, see \Nino\Text::entries()
		 *													pages				[ { uri, httpUri, template, category, templateName } ], null without the Routes module
		 *													templates		category => { file, name }
		 *													features		key => name
		 *													order				key => where a template first reads it
		 *													locale			the language the rows are named in
		 *													admin				true to keep the keys of the workbench and the paths of the pages
		 *
		 *	@return		{Object}									{ rows : id => row, rowOf : key => id, blocks : [ { id, rows | groups } ], order }
		 */
		build : function( data ) {

			const T 				= Nino.admin.textKeys;
			const hasRoutes = Array.isArray( data.pages );
			const pages 		= hasRoutes === true ? data.pages : [];
			const templates = data.templates ?? {};
			const rows 			= Object.create( null );
			const rowOf 		= Object.create( null );
			const pageRow 	= Object.create( null );

			const row = function( id, kind ) {
				rows[id] = rows[id] ?? { id : id, kind : kind, entries : [], routes : [], sections : null };
				return rows[id];
			};

			pages.forEach( function( page ) {
				const category = typeof page.category === 'string' ? page.category : null;
				const target = row( category !== null ? 'template/'+ category : '_pages'+ page.uri, 'page' );
				target.routes.push( page );
				pageRow[page.uri] = target.id;
			} );

			( data.entries ?? [] ).forEach( function( entry ) {

				if( entry.key.indexOf('/_admin/') === 0 && data.admin !== true )
					return;

				const described = Nino.adminUi.describeKey( entry.key );
				let target;

				// The path of a page is no text: only the Keys tab shows it, by its key
				if( described.kind === 'webpage' && described.field === 'uri' && data.admin !== true )
					return;

				if( described.kind === 'grammar' && described.namespace === 'template' ) {
					const id = 'template/'+ described.category;
					target = rows[id] ?? row( id, described.category === 'common' ? 'common'
						: ( hasRoutes === true && described.category.indexOf('page-') === 0 ? 'page' : 'block' ) );
				} else if( described.kind === 'grammar' ) {
					target = row( described.namespace+ '/'+ described.category, described.namespace );
				} else if( described.kind === 'webpage' ) {
					target = pageRow[described.uri] !== undefined ? rows[pageRow[described.uri]] : row( '_nino/webpage', 'orphans' );
				} else if( described.kind === 'locale' ) {
					target = row( '_nino/locale', 'locales' );
				} else {
					const first = entry.key.split('/').filter( Boolean )[0] ?? '-';
					target = row( first, 'free' );
				}

				target.entries.push( entry );
				rowOf[entry.key] = target.id;
			} );

			// A row nobody has a key for has nothing to edit
			Object.keys( rows ).forEach( function( id ) {
				if( rows[id].entries.length === 0 )
					delete rows[id];
			} );

			const model = { rows : rows, rowOf : rowOf, blocks : [], hasRoutes : hasRoutes, order : data.order ?? {}, templates : templates, features : data.features ?? {}, locale : data.locale ?? '' };

			const all = Object.keys( rows ).map( function( id ) { return rows[id] } );
			const byLabel = function( a, b ) { return T.rowLabel( model, a ).localeCompare( T.rowLabel( model, b ) ) };

			const pageRows = all.filter( function( r ) { return r.kind === 'page' } );
			const routed = pageRows.filter( function( r ) { return r.routes.length > 0 } ).sort( function( a, b ) { return T._routeRank( pages, a ) - T._routeRank( pages, b ) || T._routePath( a ).localeCompare( T._routePath( b ) ) } );
			const loose = pageRows.filter( function( r ) { return r.routes.length === 0 } ).sort( byLabel );

			if( routed.length + loose.length > 0 )
				model.blocks.push( { id : 'pages', rows : routed.concat( loose ).map( function( r ) { return r.id } ) } );

			const groupOf = { project : 'project', common : 'common', block : 'blocks', module : 'modules', feature : 'features', locales : 'system', orphans : 'system', free : 'other' };
			const groups = [];

			T.GENERAL_GROUPS.forEach( function( group ) {

				const members = all.filter( function( r ) { return groupOf[r.kind] === group } );

				if( group === 'project' )
					members.sort( function( a, b ) {
						const rank = function( r ) { const at = T.PROJECT_CATEGORIES.indexOf( r.id.split('/')[1] ); return at === -1 ? T.PROJECT_CATEGORIES.length : at };
						return rank( a ) - rank( b ) || byLabel( a, b );
					} );
				else if( group === 'system' )
					members.sort( function( a, b ) { return ( a.kind === 'locales' ? 0 : 1 ) - ( b.kind === 'locales' ? 0 : 1 ) } );
				else
					members.sort( byLabel );

				if( members.length > 0 )
					groups.push( { id : group, rows : members.map( function( r ) { return r.id } ) } );
			} );

			if( groups.length > 0 )
				model.blocks.push( { id : 'general', groups : groups } );

			return model;
		},

		/**
		 *	The path of a row's first route
		 *
		 *	@param		{Object}	row
		 *
		 *	@return		{string}
		 */
		_routePath : function( row ) {
			return row.routes.map( function( route ) { return route.httpUri } ).sort()[0] ?? '';
		},

		/**
		 *	Where a page's row stands among the pages: where the routes are saved,
		 *	which is the order a menu shows them in - a sub-page right behind its
		 *	parent, wherever the parent is. Only how the rows are shown: the data
		 *	stay as they are
		 *
		 *	@param		{Array}		pages
		 *	@param		{Object}	row
		 *
		 *	@return		{number}
		 */
		_routeRank : function( pages, row ) {

			const path = Nino.admin.textKeys._routePath( row );
			let rank = pages.findIndex( function( page ) { return page.httpUri === path } );

			// The route itself is saved too; any parent saved earlier pulls it up behind it
			for( let parent = path.replace( /\/[^\/]*$/, '' ); parent !== ''; parent = parent.replace( /\/[^\/]*$/, '' ) ) {
				const at = pages.findIndex( function( page ) { return page.httpUri === parent } );
				if( at !== -1 && at < rank )
					rank = at;
			}

			return rank;
		},

		/**
		 *	How deep a page's first route lies: 0 for the pages at the top
		 *
		 *	@param		{Object}	row
		 *
		 *	@return		{number}
		 */
		rowDepth : function( row ) {
			return Math.max( 0, Nino.admin.textKeys._routePath( row ).split('/').filter( Boolean ).length - 1 );
		},

		/**
		 *	The name of a row. A page is named after its routes in the language
		 *	- joined by " · " where a template has more than one - else after
		 *	the name its template gives itself (<!-- nino:template-name -->),
		 *	else after its category, as the vocabulary reads it; the groups of
		 *	the system have words of the panel, a feature is named in its
		 *	manifest
		 *
		 *	@param		{Object}	model
		 *	@param		{Object}	row
		 *	@param		{string}	[locale]							Default: the model's
		 *
		 *	@return		{string}
		 */
		rowLabel : function( model, row, locale ) {

			const T 				= Nino.admin.textKeys;
			const parts 		= row.id.split('/');
			const category 	= parts[1] ?? parts[0];
			locale = locale ?? model.locale;

			if( row.kind === 'locales' )
				return Nino.content.getText('/_admin/text/group/locales');

			// Without the list of pages every page's details are a page's without one
			if( row.kind === 'orphans' )
				return Nino.content.getText( model.hasRoutes === true ? '/_admin/text/group/orphans' : '/_admin/text/label/page-details' );

			if( row.kind === 'feature' )
				return ( Object.prototype.hasOwnProperty.call( model.features, category ) === true && model.features[category] ) || Nino.adminUi.slugLabel( category );

			if( row.kind === 'page' || row.kind === 'block' ) {

				const names = row.routes.map( function( route ) {
					const entry = row.entries.find( function( e ) { return e.key === '/_nino/webpage'+ route.uri+ '/name' } );
					return entry === undefined ? '' : T.plain( T.valueOf( entry, locale ) );
				} ).filter( function( name ) { return name !== '' } );

				if( names.length > 0 )
					return names.join(' · ');

				// A page whose template has no category has a row of its own
				if( row.id.indexOf('_pages') === 0 )
					return row.routes[0].templateName || row.routes[0].template || row.routes[0].httpUri;

				if( Object.prototype.hasOwnProperty.call( model.templates, category ) === true && ( model.templates[category].name ?? '' ) !== '' )
					return model.templates[category].name;
			}

			return Nino.adminUi.slugLabel( category );
		},

		/**
		 *	One row's "(N) first text, second text, .." line, in a language
		 *
		 *	@param		{Object}	row
		 *	@param		{string}	locale
		 *
		 *	@return		{string}
		 */
		rowSummary : function( row, locale ) {

			const T 			= Nino.admin.textKeys;
			const texts 	= row.entries.map( function( entry ) { return T.plain( T.valueOf( entry, locale ) ) } ).filter( function( text ) { return text !== '' } );
			const joined 	= texts.join(', ');

			return '('+ row.entries.length+ ') '+ ( joined.length > 150 ? joined.slice( 0, 150 )+ ' ..' : joined );
		},

		/**
		 *	The sections of a row, each with its fields, in the order they are
		 *	shown:
		 *
		 *	  - a page begins with the details of each of its routes
		 *	  - then one section for each <part>, in the order a template
		 *	    first reads its keys; what no template reads comes after, in
		 *	    the alphabet. In /project, 'general' is first
		 *	  - in a section the keys that are the same in every language come
		 *	    first, then the order a template reads them, then the alphabet
		 *
		 *	A section is { id, label, route, entries, fields : [ { entry, label, section } ] }
		 *
		 *	@param		{Object}	model
		 *	@param		{Object}	row
		 *
		 *	@return		{Array}
		 */
		sections : function( model, row ) {

			if( row.sections !== null )
				return row.sections;

			const T 			= Nino.admin.textKeys;
			const rank 		= function( key ) { return model.order[key] ?? Infinity };
			const sections = Object.create( null );
			const list 		= [];

			const section = function( id, label, extra ) {
				if( sections[id] === undefined ) {
					sections[id] = Object.assign( { id : id, label : label, route : '', depth : 0, fields : [], fixed : -1 }, extra ?? {} );
					list.push( sections[id] );
				}
				return sections[id];
			};

			// The details of the routes this row is for, before anything else
			row.routes.slice().sort( function( a, b ) { return a.httpUri.localeCompare( b.httpUri ) } ).forEach( function( route, at ) {
				section( 'webpage:'+ route.uri, Nino.content.getText('/_admin/text/label/page-details'), { route : route.httpUri, depth : Math.max( 0, route.httpUri.split('/').filter( Boolean ).length - 1 ), fixed : at } );
			} );

			row.entries.forEach( function( entry ) {

				const described = Nino.adminUi.describeKey( entry.key );
				let target;
				let label;
				let weight = 0;

				if( described.kind === 'webpage' ) {
					target = section( 'webpage:'+ described.uri, row.kind === 'orphans' ? described.uri : Nino.content.getText('/_admin/text/label/page-details'), { route : row.kind === 'orphans' ? '' : described.uri, fixed : row.kind === 'orphans' ? 0 : -1 } );
					label = Nino.content.getText( '/_admin/text/label/page-'+ described.field ) || described.field;
					weight = T.PAGE_FIELDS.indexOf( described.field );
				} else if( described.kind === 'locale' ) {
					target = section( described.code, described.code );
					label = Nino.adminUi.slugLabel('name');
				} else if( described.kind === 'grammar' ) {
					target = section( described.part, Nino.adminUi.slugLabel( described.part ) );
					label = Nino.adminUi.slugLabel( described.name );
				} else {
					const segments = entry.key.split('/').filter( Boolean );
					const middle = segments.slice( 1, -1 );
					target = section( middle.join('/'), middle.length > 0 ? middle.map( Nino.adminUi.slugLabel ).join(' › ') : T.rowLabel( model, row ) );
					label = Nino.adminUi.slugLabel( segments[segments.length - 1] ?? entry.key );
				}

				target.fields.push( { entry : entry, label : label, section : target, weight : weight } );
			} );

			list.forEach( function( target ) {

				target.fields.sort( function( a, b ) {
					return ( a.entry.global === b.entry.global ? 0 : ( a.entry.global === true ? -1 : 1 ) )
						|| a.weight - b.weight
						|| ( rank( a.entry.key ) === rank( b.entry.key ) ? 0 : ( rank( a.entry.key ) < rank( b.entry.key ) ? -1 : 1 ) )
						|| ( a.entry.key < b.entry.key ? -1 : 1 );
				} );

				target.entries = target.fields.map( function( field ) { return field.entry } );
				target.rank = target.fields.reduce( function( least, field ) { return Math.min( least, rank( field.entry.key ) ) }, Infinity );
			} );

			const projectFirst = row.kind === 'project' ? 'general' : null;

			row.sections = list.filter( function( target ) { return target.fields.length > 0 } ).sort( function( a, b ) {

				// The details of the routes first, in the order of their paths
				if( ( a.fixed === -1 ) !== ( b.fixed === -1 ) )
					return a.fixed === -1 ? 1 : -1;

				if( a.fixed !== -1 )
					return a.fixed - b.fixed || ( a.id < b.id ? -1 : 1 );

				if( projectFirst !== null && ( a.id === projectFirst ) !== ( b.id === projectFirst ) )
					return a.id === projectFirst ? -1 : 1;

				return a.rank === b.rank ? ( a.id < b.id ? -1 : 1 ) : ( a.rank < b.rank ? -1 : 1 );
			} );

			return row.sections;
		},

		/**
		 *	Every field of the model in the order the list shows them, each
		 *	with the path a person reads it by: "Leistungen › Eintrag 1 › Titel"
		 *
		 *	@param		{Object}	model
		 *
		 *	@return		{Array}										[ { row, section, field, path } ]
		 */
		fields : function( model ) {

			const T = Nino.admin.textKeys;
			const out = [];

			model.blocks.forEach( function( block ) {
				const ids = block.rows ?? block.groups.flatMap( function( group ) { return group.rows } );
				ids.forEach( function( id ) {
					const row = model.rows[id];
					const label = T.rowLabel( model, row );
					T.sections( model, row ).forEach( function( section ) {
						section.fields.forEach( function( field ) {
							out.push( { row : row, section : section, field : field, path : [ label, section.label, field.label ].filter( function( part, at, all ) { return at === 0 || part !== all[at - 1] } ) } );
						} );
					} );
				} );
			} );

			return out;
		},

		/**
		 *	Search the keys: by their key, the names of their row, section and
		 *	field, and their texts in every language.
		 *
		 *	  - accents and capitals do not matter
		 *	  - every word has to occur, anywhere in what is searched
		 *	  - a word that begins with a slash is searched in the key only:
		 *	    '/template/page-home' is every text of the home page, '/common/'
		 *	    the common words
		 *
		 *	A hit says which language's text it found the words in, and the
		 *	piece of that text around them. With no words every field is a hit.
		 *
		 *	@param		{Object}	model
		 *	@param		{Object}	options
		 *													query				what was typed
		 *													locale			the language that is on screen, whose text is shown if it matches
		 *													emptyIn			only keys with no text in this language, '' for any
		 *													onlyHidden	only keys hidden from the Text panel
		 *
		 *	@return		{Array}										[ { item, locale, snippet } ] in the order of fields()
		 */
		search : function( model, options ) {

			const T 			= Nino.admin.textKeys;
			const terms 	= String( options.query ?? '' ).split( /\s+/ ).filter( Boolean ).map( T.fold );
			const keyTerms = terms.filter( function( term ) { return term.charAt(0) === '/' } );
			const textTerms = terms.filter( function( term ) { return term.charAt(0) !== '/' } );
			const locale 	= options.locale ?? model.locale;
			const hits 		= [];

			T.fields( model ).forEach( function( item ) {

				const entry = item.field.entry;

				if( options.onlyHidden === true && entry.blacklisted !== true )
					return;

				if( options.emptyIn && T.isEmptyIn( entry, options.emptyIn ) === false )
					return;

				const key = T.fold( entry.key );

				if( keyTerms.some( function( term ) { return key.indexOf( term ) === -1 } ) )
					return;

				const texts = Object.keys( entry.values ).map( function( code ) { return { locale : code, text : T.plain( entry.values[code] ) } } );
				const haystack = key+ '\n'+ T.fold( item.path.join(' ') )+ '\n'+ T.fold( texts.map( function( t ) { return t.text } ).join('\n') );

				if( textTerms.some( function( term ) { return haystack.indexOf( term ) === -1 } ) )
					return;

				// The text that is shown: the language on screen where it holds a
				// word searched for, else the first that does, else - the words
				// are in the key or the names - the one on screen
				const own = texts.find( function( t ) { return t.locale === ( entry.global === true ? '*' : locale ) } );
				const has = function( t ) { return textTerms.some( function( term ) { return T.fold( t.text ).indexOf( term ) !== -1 } ) };
				const shown = textTerms.length > 0 ? ( own !== undefined && has( own ) ? own : ( texts.find( has ) ?? own ) ) : own;

				hits.push( {
					item : item,
					locale : shown !== undefined ? shown.locale : '',
					snippet : shown !== undefined && shown.text !== '' ? T.snippet( shown.text, textTerms ) : [],
				} );
			} );

			return hits;
		},

		/**
		 *	The piece of a text around the first of the words, as the parts
		 *	to draw: [ { text, mark } ], the words marked. The text of each part
		 *	is the original's - accents and capitals as written
		 *
		 *	@param		{string}	text
		 *	@param		{Array}		terms									Folded, see fold()
		 *	@param		{number}	[width]								How many characters to show, default 90
		 *
		 *	@return		{Array}
		 */
		snippet : function( text, terms, width ) {

			const T 			= Nino.admin.textKeys;
			width 				= width ?? 90;
			const folded 	= T.foldMapped( text );
			const ranges 	= [];

			terms.forEach( function( term ) {
				for( let at = folded.text.indexOf( term ); term !== '' && at !== -1; at = folded.text.indexOf( term, at + term.length ) )
					ranges.push( [ folded.from[at], folded.from[at + term.length - 1] + 1 ] );
			} );

			ranges.sort( function( a, b ) { return a[0] - b[0] } );

			const first = ranges[0] ?? [ 0, 0 ];
			const from = Math.max( 0, Math.min( first[0] - Math.floor( ( width - ( first[1] - first[0] ) ) / 3 ), text.length - width ) );
			const to = Math.min( text.length, from + width );

			const parts = [];
			let at = from;

			ranges.forEach( function( range ) {
				const start = Math.max( range[0], at );
				const end = Math.min( range[1], to );
				if( end <= start )
					return;
				if( start > at )
					parts.push( { text : text.slice( at, start ), mark : false } );
				parts.push( { text : text.slice( start, end ), mark : true } );
				at = end;
			} );

			if( at < to )
				parts.push( { text : text.slice( at, to ), mark : false } );

			if( from > 0 && parts.length > 0 )
				parts[0].text = '… '+ parts[0].text.replace( /^\s+/, '' );
			if( to < text.length && parts.length > 0 )
				parts[parts.length - 1].text = parts[parts.length - 1].text.replace( /\s+$/, '' )+ ' …';

			return parts;
		},

		/**
		 *	What the parts of an address behind the Text or the Keys screen name: a
		 *	row - its id is the first part - and, as the second, a key of it. A key
		 *	the row does not hold is no key: the row is what is named
		 *
		 *	@param		{Object}	model
		 *	@param		{Array}		parts									The address behind the screen's name: [ row ] or [ row, key ]
		 *
		 *	@return		{Object|null}							{ row, key } - key null for a row - or null
		 */
		resolve : function( model, parts ) {

			if( parts.length < 1 || parts.length > 2 || model.rows[parts[0]] === undefined )
				return null;

			if( parts.length === 2 && model.rowOf[parts[1]] === parts[0] )
				return { row : parts[0], key : parts[1] };

			return { row : parts[0], key : null };
		},

		/**
		 *	The search box of a list, with what narrows it: filters that are on or off. The language the texts are
		 *	read in is the workbench's own (Nino.admin.workLocale), there is no switch of it here. The words of the
		 *	controls are the panel's - this draws them
		 *
		 *	@param		{Object}	config
		 *													query			what the box holds to begin with
		 *													onQuery		called with what was typed
		 *													chips			[ { id, onToggle } ] - the filters; their text and state are set by the caller
		 *
		 *	@return		{Object}									{ bar, input, chips : id => button }
		 */
		searchBar : function( config ) {

			const chips = Object.create( null );

			( config.chips ?? [] ).forEach( function( chip ) {
				chips[chip.id] = Nino.adminUi.chip( '', { onToggle : chip.onToggle } );
			} );

			const bar = Nino.adminUi.filterBar( {
				search : {
					value : config.query ?? '',
					label : Nino.content.getText('/_admin/text/label/search'),
					placeholder : Nino.content.getText('/_admin/text/placeholder/search'),
					onInput : config.onQuery,
				},
				items : Object.keys( chips ).map( function( id ) { return chips[id] } ),
			} );

			return { bar : bar.el, input : bar.input, chips : chips };
		},

		/**
		 *	The hits of a search as the list of the workbench: a row for each, the path a key is read by over the
		 *	piece of its text with the words marked and the key small, and - where the words are in another language
		 *	than the one on screen - the code of that language as a small button at the end of the row. The row is a
		 *	link to the row of the key. The marks are <mark>s and the rest text nodes, never markup: what a key
		 *	holds is whatever somebody typed into a text file
		 *
		 *	@param		{Array}			hits						See search()
		 *	@param		{string}		locale					The language on screen
		 *	@param		{Object}		handlers				{ href( hit ), onOpen( hit ), onLanguage( code ) }
		 *
		 *	@return		{Element}										What list() answers
		 */
		hitList : function( hits, locale, handlers ) {

			const list = Nino.adminUi.list( hits.map( function( hit ) {

				const key = hit.item.field.entry.key;
				const words = hit.snippet.map( function( part ) { return part.text } ).join('');
				const actions = [];

				if( hit.locale !== '' && hit.locale !== '*' && hit.locale !== locale ) {
					const tag = Nino.adminUi.button( hit.locale, function() { handlers.onLanguage( hit.locale ) }, { kind : 'secondary', small : true } );
					tag.setAttribute( 'aria-label', Nino.adminUi.format( Nino.content.getText('/_admin/text/label/switch-language'), Nino.adminUi.localeName( hit.locale ) ) );
					actions.push( tag );
				}

				return {
					key : key,
					title : hit.item.path.join(' › '),
					detail : words === '' ? key : words+ ' '+ key,
					href : handlers.href( hit ),
					onOpen : function() { handlers.onOpen( hit ) },
					actions : actions,
				};
			} ), { label : Nino.content.getText('/_admin/text/label/search') } );

			// The second line of a row is text; the words found in it are marked, and the key stands small behind it
			Array.from( list.querySelectorAll('li') ).forEach( function( li, at ) {

				const line = li.querySelector('small');
				const hit = hits[at];

				while( line.firstChild !== null && line.firstChild !== undefined )
					line.removeChild( line.firstChild );

				hit.snippet.forEach( function( part ) {
					if( part.mark === true )
						line.appendChild( Nino.adminUi.el( 'mark', '', part.text ) );
					else
						line.appendChild( dc.createTextNode( part.text ) );
				} );

				line.appendChild( dc.createTextNode( ' ' ) );
				line.appendChild( Nino.adminUi.el( 'code', 'admin-text-hit-key', hit.item.field.entry.key ) );
			} );

			return list;
		},

		/**
		 *	The textfills a field takes, as the rows the dialog of the "[ ]" shows: the name of each is its key read
		 *	as words, its notation the one the field reads, its value what it says in the language on screen
		 *
		 *	@param		{Function}	ask						ask( callback ) calls the server's action and hands callback ( status, body )
		 *	@param		{Function}	done					Called once with the rows, or with null where they could not be had
		 *
		 *	@return		void
		 */
		fills : function( ask, done ) {

			ask( function( status, response ) {

				if( status !== 200 || response === null || Array.isArray( response.fills ) === false ) {
					done( null );
					return;
				}

				done( response.fills.map( function( fill ) {
					return {
						name : fill.key.split('/').slice( 2 ).map( function( part ) { return Nino.adminUi.slugLabel( part ) } ).join(' \u00B7 '),
						notation : fill.notation,
						value : fill.value,
					};
				} ) );
			} );
		},

		/**
		 *	The field of one key's value, in the build of every field of the workbench: its name at the left of it, the
		 *	key and the count of what is typed under it. A plain text of one line is a line (textField()), of several
		 *	a field of several (textareaField()), a text with markup the rich-text editor; a text the account may not
		 *	change is shown as text. Where the key is the same in every language the name says so with a badge. The
		 *	"[ ]" of the textfills stands at the name of a field of several lines and of a rich-text field, for the
		 *	account that may change it (options.fills)
		 *
		 *	A refusal is said under the field (fail()) and goes when the field is typed into or the next save begins
		 *
		 *	@param		{Object}		options
		 *													entry				the key, see \Nino\Text::entries()
		 *													value				its text in the language on screen, or the one text of a global key
		 *													label				the name of the field
		 *													id					the id of the field's control
		 *													writable		false for a key this account may not change
		 *													fills				null, or load( done ) answering the textfills to done( list ), null where they could not be had
		 *
		 *	@return		{Object}										{ el, key, control, readonly, read(), write( text ), focus(), fail( sentence ), release(), destroy() }
		 */
		valueField : function( options ) {

			const T 				= Nino.admin.textKeys;
			let entry 			= options.entry;
			const label 		= String( options.label ?? entry.key );
			const value 		= String( options.value ?? '' );
			const writable 	= options.writable !== false;
			const id 				= String( options.id );
			let limit 			= T.limitOf( entry, options.locale );
			let keyLimit 		= typeof entry.keyLimit === 'number' ? entry.keyLimit : limit;

			const meta = Nino.adminUi.el( 'div', 'admin-text-meta' );

			if( options.keyLine !== false )
				meta.appendChild( Nino.adminUi.el( 'code', '', entry.key ) );

			// A field that is the same in every language says so beside its name; the badge describes the field, and
			// its id is one of a note, which an error leaves in the description
			const badge = entry.global === true && options.badge !== false ? Nino.adminUi.el( 'span', 'nino-admin-badge', Nino.content.getText('/_admin/text/label/all-languages') ) : null;

			if( badge !== null )
				badge.id = 'nino-admin-note-'+ id+ '-badge';

			let error = null;

			const handle = {
				entry : entry, key : entry.key, el : null, control : null, readonly : writable === false,
				read : null, write : null, focus : function() {}, destroy : function() {}, fail : null, setLimit : function() {},
				// The sentence under the field, and the field marked: taken away again
				release : function() {
					if( error === null )
						return;
					error.remove();
					error = null;
					handle.unmark();
				},
			};

			// What a refusal puts on the box that is typed into: how the control is marked and let go again
			handle.unmark = function() {};

			// The refusal of a field: under it, until it is typed into
			const refuse = function( wrapper, control, sentence, marks ) {
				handle.release();
				error = Nino.adminUi.fieldError( wrapper, control, id+ '-error', sentence );
				marks( true );
				handle.unmark = function() { marks( false ) };
				wrapper.addEventListener( 'input', handle.release, { once : true } );
			};

			if( writable === false ) {

				const el = Nino.adminUi.el( 'div', 'nino-admin-field admin-text-field' );
				const name = Nino.adminUi.el( 'span', '', label );
				const view = Nino.adminUi.el( 'div', 'admin-text-readonly' );

				name.id = id+ '-name';
				view.setAttribute( 'role', 'group' );
				view.setAttribute( 'aria-labelledby', name.id );

				// Read, not edited: a text with markup is shown as the words it holds, set as text and never as markup -
				// a value that somebody wrote into a file by hand is no markup to run in the page of a restricted account
				view.textContent = entry.html === true ? T.plain( value ) : value;

				el.dataset.key = entry.key;

				if( options.locale !== undefined )
					el.dataset.locale = String( options.locale );

				if( badge !== null )
					name.appendChild( badge );

				el.appendChild( name );
				el.appendChild( view );
				if( meta.firstChild !== null )
					el.appendChild( meta );

				handle.el = el;
				handle.control = view;
				handle.read = function() { return value };
				handle.write = function() {};
				handle.fail = function() {};

				return handle;
			}

			// A field of the editor: no label can hold it, so the name stands in a span the group is named by
			if( entry.html === true ) {

				const el = Nino.adminUi.el( 'div', 'nino-admin-field admin-text-field' );
				const name = Nino.adminUi.el( 'span', '', label );
				const mount = Nino.adminUi.el( 'div', '' );

				name.id = id+ '-name';
				el.dataset.key = entry.key;

				if( options.locale !== undefined )
					el.dataset.locale = String( options.locale );
				el.setAttribute( 'role', 'group' );
				el.setAttribute( 'aria-labelledby', name.id );

				const editor = Nino.admin.htmlEditor.create( mount, value, limit, 0, entry.format );

				if( badge !== null ) {
					name.appendChild( badge );
					editor.mark( { describedBy : badge.id } );
				}

				if( typeof options.fills === 'function' )
					name.appendChild( Nino.adminUi.fillsButton( { load : options.fills, opener : function() { return mount.querySelector('[contenteditable]') ?? mount } }, null, label ) );

				el.appendChild( name );
				el.appendChild( mount );
				if( meta.firstChild !== null )
					el.appendChild( meta );

				handle.el = el;
				handle.control = mount;
				handle.read = function() { return editor.getValue() };
				handle.write = function( text ) { editor.setValue( String( text ) ) };
				handle.focus = function() { editor.focus() };
				handle.destroy = function() { editor.destroy() };
				handle.fail = function( sentence ) {
					refuse( el, null, sentence, function( on ) {
						editor.mark( { invalid : on, describedBy : on === true ? error.id : ( badge === null ? '' : badge.id ) } );
					} );
				};

				return handle;
			}

			const single = T.isSingleLine( entry );
			const field = ( single === true ? Nino.adminUi.textField : Nino.adminUi.textareaField )( {
				id : id,
				label : label,
				value : value,
				maxLength : limit,
				// The textfills are for a text of several lines; a line has none
				fills : single === false && typeof options.fills === 'function' ? { editable : true, load : options.fills } : undefined,
			} );
			const control = field.querySelector( single === true ? 'input' : 'textarea' );

			field.className = 'nino-admin-field admin-text-field';
			field.dataset.key = entry.key;

			if( options.locale !== undefined )
				field.dataset.locale = String( options.locale );
			control.spellcheck = single === false;

			if( badge !== null ) {
				field.children[0].appendChild( badge );
				control.setAttribute( 'aria-describedby', badge.id );
			}

			// A text that is longer than the limit of its key - it was there before the limit was lowered - is counted against the limit of the
			// key, which is what it is over, and says that it can be made shorter and not longer: the field takes what it holds and no more
			const counter = Nino.adminUi.el( 'span', 'nino-admin-char-counter' );
			const over = Nino.adminUi.el( 'p', 'nino-admin-hint admin-text-over' );
			const count = function() {

				const length = control.value.length;
				const shown = keyLimit < limit ? keyLimit : limit;
				const beyond = length > keyLimit && keyLimit < limit;

				counter.textContent = length+ ' / '+ shown;
				counter.classList.toggle( 'is-limit', length >= shown );
				over.hidden = beyond === false;
				over.textContent = beyond === true ? Nino.adminUi.format( Nino.content.getText('/_admin/text/hint/over-limit'), keyLimit ) : '';
			};

			control.addEventListener( 'input', count );
			count();

			meta.appendChild( counter );
			field.appendChild( meta );
			field.appendChild( over );

			// The limit the field counts to and takes: the one the form of the key says before it is saved - a text can be
			// made longer in the save that raises the limit - and the one the server names for the language after a save
			handle.setLimit = function( to, own ) {
				limit = to;
				keyLimit = own ?? to;
				control.maxLength = limit;
				count();
			};

			handle.el = field;
			handle.control = control;
			handle.read = function() { return control.value };
			handle.write = function( text ) { control.value = String( text ); count() };
			handle.focus = function() { control.focus() };
			handle.fail = function( sentence ) {
				refuse( field, control, sentence, function( on ) {
					if( on === true )
						return;
					control.removeAttribute('aria-invalid');
					if( badge === null )
						control.removeAttribute('aria-describedby');
					else
						control.setAttribute( 'aria-describedby', badge.id );
				} );
			};

			return handle;
		},

		/**
		 *	The item of a save for one key: where it is written and the text it was read as, so that the server can tell a
		 *	text that another tab or person wrote since from the one this form was drawn from
		 *
		 *	@param		{Object}	entry
		 *	@param		{string}	locale					The language the form shows
		 *	@param		{string}	value						The text to write
		 *
		 *	@return		{Object}									{ key, locale, value, was }
		 */
		saveItem : function( entry, locale, value ) {
			return { key : entry.key, locale : entry.global === true ? '*' : locale, value : value, was : Nino.admin.textKeys.valueOf( entry, locale ) };
		},

		/**
		 *	What a text is called where the panel shows it: the row, the section and the field, "Start page › Hero › Title"
		 *
		 *	@param		{Object}	model					What build() made
		 *	@param		{string}	key
		 *
		 *	@return		{string}								'' for a key no row of the model has
		 */
		nameOf : function( model, key ) {

			const found = Nino.admin.textKeys.fields( model ).find( function( hit ) { return hit.field.entry.key === key } );

			return found === undefined ? '' : found.path.join(' › ');
		},

		/**
		 *	The parts of an address that name a row, or a key in it: the row's id and the key, each one part
		 *
		 *	@param		{string}		row
		 *	@param		{string|null}	[key]
		 *
		 *	@return		{Array}
		 */
		hashParts : function( row, key ) {
			return ( key ?? null ) !== null ? [ row, key ] : [ row ];
		},
	};

})(window, document, document.documentElement, document.body);
