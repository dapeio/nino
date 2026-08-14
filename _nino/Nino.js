/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	NinoJS									Basic js functionality & helper - client detection, cookies,
 *													dom-ready/resize/scroll events, xhr requests, auth,
 *													jstext lookups. Used by _editor/_admin and the public site
 *													alike. Frontend design-system behaviors (cover, parallax,
 *													vpa, autoheight, slider, generic forms) live separately
 *													in Nino.ui.js, since only the public site needs those.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino = {

		/**
		 *	Small DOM primitives shared by Nino's four administration apps.
		 *	They only assign the common chrome classes from Nino.admin.css;
		 *	application modules keep ownership of labels, state and events.
		 */
		adminUi : {

			contextBar : function( backLink, controls ) {
				const bar = dc.createElement('div');
				bar.className = 'nino-admin-contextbar';
				if( backLink )
					bar.appendChild( backLink );
				( Array.isArray( controls ) ? controls : ( controls ? [ controls ] : [] ) ).forEach( function( control ) {
					bar.appendChild( control );
				} );
				return bar;
			},

			actionBar : function( bar ) {
				bar.classList.add('nino-admin-actionbar');
				return bar;
			},

			listActions : function( buttons ) {
				const bar = dc.createElement('div');
				bar.className = 'nino-admin-actionbar nino-admin-list-actions';
				buttons.forEach( function( button ) { bar.appendChild( button ) } );
				return bar;
			},

			/**
			 *	The data table's pure half: filtering, sorting, paging and cell
			 *	formatting as plain functions over arrays, with no DOM and no
			 *	state. table() below is the rendering half and owns neither.
			 *	Kept separate so the behaviour that is easy to get wrong - type
			 *	aware comparison, page clamping - is directly testable.
			 */
			tableModel : {

				// Field types that fit in a cell. 'image' and 'array' do not,
				// and a 'string' carrying html:true is markup, not text - see
				// Admin.php's FIELD_TYPES and its 'html' flag
				DISPLAYABLE : [ 'string', 'integer', 'double', 'boolean', 'date', 'datetime', 'element' ],

				/**
				 *	Whether one model field belongs in the table
				 *
				 *	@param		{Object}	field			Model field definition
				 *
				 *	@return		{boolean}
				 */
				isDisplayable : function( field ) {
					const type = ( field || {} ).type || '';
					if( type === 'string' && ( field || {} ).html === true )
						return false;
					return Nino.adminUi.tableModel.DISPLAYABLE.indexOf( type ) !== -1;
				},

				/**
				 *	One cell's text. Deliberately language-neutral: a boolean is
				 *	a glyph rather than "yes"/"no", so the same component reads
				 *	correctly in _admin (English-only) and in _editor (localized)
				 *	without either passing a translation for it.
				 *
				 *	@param		{*}				value
				 *	@param		{string}	type			Model field type
				 *
				 *	@return		{string}
				 */
				format : function( value, type ) {

					if( value === null || value === undefined || value === '' )
						return '';

					if( type === 'boolean' )
						return ( value === true || value === 1 || value === '1' ) ? '\u2713' : '\u2013';

					return String( value );
				},

				/**
				 *	Type-aware comparison. A plain string compare puts 10 before
				 *	9 and sorts booleans by the words "false"/"true", so each
				 *	type that is not text gets its own ordering.
				 *
				 *	@param		{*}				a
				 *	@param		{*}				b
				 *	@param		{string}	type
				 *
				 *	@return		{number}
				 */
				isEmpty : function( value ) {
					return value === null || value === undefined || value === '';
				},

				/**
				 *	Order two present values of one type. Absent values are not
				 *	handled here on purpose - see sort(), which has to place them
				 *	outside the direction flip.
				 *
				 *	@param		{*}				a
				 *	@param		{*}				b
				 *	@param		{string}	type
				 *
				 *	@return		{number}
				 */
				compare : function( a, b, type ) {

					if( type === 'integer' || type === 'double' )
						return Number( a ) - Number( b );

					if( type === 'boolean' )
						return ( a === true ? 1 : 0 ) - ( b === true ? 1 : 0 );

					// Dates are stored ISO-first, so their lexical order is
					// already chronological - compared as plain strings on
					// purpose rather than parsed into Date objects
					return String( a ).localeCompare( String( b ), undefined, { numeric : true, sensitivity : 'base' } );
				},

				/**
				 *	@param		{Array}		rows
				 *	@param		{string}	key				Column key, '' to leave the order alone
				 *	@param		{string}	type			That column's model type
				 *	@param		{number}	dir				1 ascending, -1 descending
				 *
				 *	@return		{Array}								A new array; the input is not reordered
				 */
				sort : function( rows, key, type, dir ) {

					if( !key )
						return rows.slice();

					const model = Nino.adminUi.tableModel;

					return rows.slice().sort( function( rowA, rowB ) {

						const a = rowA[key], b = rowB[key];
						const emptyA = model.isEmpty( a ), emptyB = model.isEmpty( b );

						// Absent sorts last whichever way the column runs - an
						// unset value is not "smaller", it is missing, and
						// flipping it with the direction opens every descending
						// sort on a screenful of blanks. Deliberately decided
						// before the direction is applied, not inside compare()
						if( emptyA === true || emptyB === true )
							return emptyA === emptyB ? 0 : ( emptyA === true ? 1 : -1 );

						return model.compare( a, b, type ) * ( dir < 0 ? -1 : 1 );
					} );
				},

				/**
				 *	Substring match across the given columns, case-insensitive.
				 *	One box over every visible column rather than a filter per
				 *	column: this is the "where is that entry" case, not a query
				 *	builder.
				 *
				 *	@param		{Array}		rows
				 *	@param		{Array}		columns		[ { key, type }, ... ]
				 *	@param		{string}	query
				 *
				 *	@return		{Array}
				 */
				filter : function( rows, columns, query ) {

					const needle = String( query || '' ).trim().toLowerCase();
					if( needle === '' )
						return rows.slice();

					return rows.filter( function( row ) {
						return columns.some( function( column ) {
							return Nino.adminUi.tableModel
								.format( row[column.key], column.type )
								.toLowerCase()
								.indexOf( needle ) !== -1;
						} );
					} );
				},

				/**
				 *	@param		{number}	total
				 *	@param		{number}	pageSize
				 *
				 *	@return		{number}						At least 1, so an empty table still has a page 1
				 */
				pageCount : function( total, pageSize ) {
					return Math.max( 1, Math.ceil( total / Math.max( 1, pageSize ) ) );
				},

				/**
				 *	One page of rows, with the page number clamped into range -
				 *	filtering down to fewer results than the current page starts
				 *	at must not show an empty table
				 *
				 *	@param		{Array}		rows
				 *	@param		{number}	pageSize
				 *	@param		{number}	page			1-based
				 *
				 *	@return		{Object}						{ rows, page, pages, from, to, total }
				 */
				page : function( rows, pageSize, page ) {

					const total = rows.length;
					const pages = Nino.adminUi.tableModel.pageCount( total, pageSize );
					const at 		= Math.min( Math.max( 1, page | 0 || 1 ), pages );
					const start = ( at - 1 ) * pageSize;

					return {
						rows 	: rows.slice( start, start + pageSize ),
						page 	: at,
						pages : pages,
						from 	: total === 0 ? 0 : start + 1,
						to 		: Math.min( start + pageSize, total ),
						total : total,
					};
				},
			},

			/**
			 *	A sortable, searchable, paged data table.
			 *
			 *	Owns no strings. Every word it can show comes from `labels`, so
			 *	the same component reads correctly in _admin (English-only, field
			 *	labelled by its raw model key) and in _editor (localized through
			 *	Nino.content). Everything that can be a number or a glyph - the
			 *	pager arrows, the row range, a boolean cell - is one, so the
			 *	caller only has to supply two actual sentences.
			 *
			 *	The whole set is passed in and paged here rather than fetched a
			 *	page at a time: an element type is one file that is read whole
			 *	on every request (see Nino\Elements::queryElements), so a page
			 *	request costs exactly what the full set costs - and searching
			 *	locally is instant instead of a round trip per keystroke.
			 *
			 *	@param		{Object}		options
			 *	@param		{Element}		options.mount					Container; emptied and taken over
			 *	@param		{Array}			options.columns				[ { key, label, type, render }, ... ]
			 *																					`render(value, row)` may return an Element to
			 *																					put in the cell instead of text - what a column
			 *																					holding a mailto link or a per-row action needs.
			 *																					Such a column is still searched and sorted on its
			 *																					plain value, so behaviour does not depend on how
			 *																					a cell happens to be drawn.
			 *	@param		{Array}			options.rows					The complete set
			 *	@param		{Object}		options.labels				{ search, empty, noMatch }
			 *	@param		{number}		[options.pageSize]		Initial rows per page (default 50)
			 *	@param		{Array}			[options.pageSizes]		Offered sizes (default [50,100,150])
			 *	@param		{Function}	[options.onRowClick]	Called with the row object
			 *	@param		{string}		[options.rowKey]			Row property used as the row's identity
			 *
			 *	@return		{Object}													{ setRows, destroy }
			 */
			table : function( options ) {

				const mount 		= options.mount;
				const columns 	= options.columns || [];
				const labels 		= options.labels || {};
				const pageSizes = options.pageSizes || [ 50, 100, 150 ];
				const rowKey 		= options.rowKey || 'uri';

				let rows 			= options.rows || [];
				let pageSize 	= options.pageSize || pageSizes[0];
				let page 			= 1;
				let sortKey 	= '';
				let sortDir 	= 1;
				let query 		= '';

				const model = Nino.adminUi.tableModel;

				mount.innerHTML = '';
				mount.classList.add('nino-admin-table-wrap');

				// --- search -----------------------------------------------
				const toolbar = dc.createElement('div');
				toolbar.className = 'nino-admin-table-toolbar';

				const search = dc.createElement('input');
				search.type = 'search';
				search.className = 'nino-admin-table-search';
				search.placeholder = labels.search || '';
				search.addEventListener( 'input', function() { query = search.value; page = 1; draw() } );
				toolbar.appendChild( search );
				mount.appendChild( toolbar );

				// --- table ------------------------------------------------
				const scroller = dc.createElement('div');
				scroller.className = 'nino-admin-table-scroll';

				const table = dc.createElement('table');
				table.className = 'nino-admin-table';

				const thead = dc.createElement('thead');
				const headRow = dc.createElement('tr');

				columns.forEach( function( column ) {

					const th = dc.createElement('th');
					th.dataset.key = column.key;
					if( column.type === 'integer' || column.type === 'double' )
						th.classList.add('nino-admin-table-num');

					const btn = dc.createElement('button');
					btn.type = 'button';
					btn.className = 'nino-admin-table-sort';
					btn.textContent = column.label;
					btn.addEventListener( 'click', function() {
						sortDir = ( sortKey === column.key ) ? -sortDir : 1;
						sortKey = column.key;
						page = 1;
						draw();
					} );

					th.appendChild( btn );
					headRow.appendChild( th );
				} );

				thead.appendChild( headRow );
				table.appendChild( thead );

				const tbody = dc.createElement('tbody');
				table.appendChild( tbody );
				scroller.appendChild( table );
				mount.appendChild( scroller );

				const empty = dc.createElement('p');
				empty.className = 'nino-admin-hint nino-admin-table-empty';
				empty.textContent = labels.empty || '';
				mount.appendChild( empty );

				// --- pager ------------------------------------------------
				const pager = dc.createElement('div');
				pager.className = 'nino-admin-table-pager';

				const range = dc.createElement('span');
				range.className = 'nino-admin-table-range';

				const sizeSelect = dc.createElement('select');
				sizeSelect.className = 'nino-admin-table-size';
				pageSizes.forEach( function( size ) {
					const option = dc.createElement('option');
					option.value = String( size );
					option.textContent = String( size );
					option.selected = ( size === pageSize );
					sizeSelect.appendChild( option );
				} );
				sizeSelect.addEventListener( 'change', function() {
					pageSize = parseInt( sizeSelect.value, 10 ) || pageSizes[0];
					page = 1;
					draw();
				} );

				const prev = dc.createElement('button');
				prev.type = 'button';
				prev.className = 'nino-admin-table-step';
				prev.textContent = '\u2039';
				prev.addEventListener( 'click', function() { page--; draw() } );

				const next = dc.createElement('button');
				next.type = 'button';
				next.className = 'nino-admin-table-step';
				next.textContent = '\u203a';
				next.addEventListener( 'click', function() { page++; draw() } );

				pager.appendChild( sizeSelect );
				pager.appendChild( range );
				pager.appendChild( prev );
				pager.appendChild( next );
				mount.appendChild( pager );

				function draw() {

					const matched = model.filter( rows, columns, query );
					const sorted 	= model.sort( matched, sortKey, ( columns.find( function( c ) { return c.key === sortKey } ) || {} ).type, sortDir );
					const view 		= model.page( sorted, pageSize, page );

					page = view.page;

					headRow.querySelectorAll('th').forEach( function( th ) {
						th.classList.toggle( 'is-sorted', th.dataset.key === sortKey );
						th.classList.toggle( 'is-desc', th.dataset.key === sortKey && sortDir < 0 );
					} );

					tbody.innerHTML = '';
					view.rows.forEach( function( row ) {

						const tr = dc.createElement('tr');
						if( typeof options.onRowClick === 'function' ) {
							tr.tabIndex = 0;
							tr.classList.add('is-clickable');
							tr.addEventListener( 'click', function() { options.onRowClick( row ) } );
							tr.addEventListener( 'keydown', function( ev ) {
								if( ev.key === 'Enter' || ev.key === ' ' ) { ev.preventDefault(); options.onRowClick( row ) }
							} );
						}

						columns.forEach( function( column ) {

							const td = dc.createElement('td');
							const text = model.format( row[column.key], column.type );

							if( typeof column.render === 'function' ) {
								const cell = column.render( row[column.key], row );
								// A returned node goes in as-is; anything else is
								// still treated as text, never as markup
								if( cell instanceof Object && cell.nodeType )
									td.appendChild( cell );
								else
									td.textContent = cell === undefined ? text : String( cell );
							}
							else
								// textContent throughout: every value here is content
								// someone typed, and some of it is deliberately html
								td.textContent = text;
							if( column.type === 'integer' || column.type === 'double' )
								td.classList.add('nino-admin-table-num');
							if( column.type === 'element' || column.key === rowKey )
								td.classList.add('nino-admin-table-uri');
							if( text === '' )
								td.classList.add('is-empty');
							tr.appendChild( td );
						} );

						tbody.appendChild( tr );
					} );

					// "nothing here yet" and "nothing matched" are different facts
					// and read differently in every language, so the caller
					// supplies both rather than one doing duty for the other
					empty.textContent = ( rows.length > 0 && query !== '' )
						? ( labels.noMatch || labels.empty || '' )
						: ( labels.empty || '' );

					// A range and a page count read the same in every language
					range.textContent = view.total === 0
						? '0'
						: view.from+ '\u2013'+ view.to+ ' / '+ view.total;

					prev.disabled = ( view.page <= 1 );
					next.disabled = ( view.page >= view.pages );

					scroller.classList.toggle( 'admin-hidden', view.total === 0 );
					// The toolbar stays put when a search emptied the table -
					// hiding it would trap the user with no way to clear the box
					toolbar.classList.toggle( 'admin-hidden', rows.length === 0 && query === '' );
					empty.classList.toggle( 'admin-hidden', view.total !== 0 );
					pager.classList.toggle( 'admin-hidden', view.total === 0 );
				}

				draw();

				return {
					setRows : function( next ) { rows = next || []; page = 1; draw() },
					destroy : function() { mount.innerHTML = ''; mount.classList.remove('nino-admin-table-wrap') },
				};
			},

			/**
			 *	Put one "show-<panel>" state class on an app shell, replacing
			 *	whichever one it currently carries and leaving everything else
			 *	alone.
			 *
			 *	The shell element carries two unrelated things at once: the
			 *	design system's own classes (.nino-admin, .nino-admin-shell -
			 *	which every layout rule in _nino/Nino.admin.css hangs off) and
			 *	the "which panel is open" state each tool switches on. Assigning
			 *	className outright is the obvious way to do the second and
			 *	silently destroys the first, so all three shells go through here
			 *	instead.
			 *
			 *	@param		{Element}	shell					The tool's own page wrapper
			 *	@param		{string}	stateClass		Eg. "show-elements"
			 *
			 *	@return		void
			 */
			setStateClass : function( shell, stateClass ) {

				if( shell === null || shell === undefined )
					return;

				Array.from( shell.classList ).forEach( function( name ) {
					if( name.indexOf('show-') === 0 )
						shell.classList.remove( name );
				} );

				if( stateClass )
					shell.classList.add( stateClass );
			},
		},

		auth : {

			/**
			 *	Log in a user and redirect on success
			 *
			 *	@param		{string}		user					Username / mail
			 *	@param		{string}		pw						Password
			 *	@param		{string}		redirect			Uri to redirect to on success
			 *	@param		{Function}	[onError]			(optional) Called with the xhr on a failed login
			 *
			 *	@return		void
			 */
			login : function( user, pw, redirect, onError ) {
				onError = ( typeof onError === 'function' ) ? onError : function(){};
				Nino.http.sendRequest(
					'/.nino/auth/login',
					'POST',
					function( xhr ) { if( xhr.status !== 200 ) return onError( xhr ); wn.location.replace( redirect ) },
					{},
					{ 'user' : user, 'pw' : pw }
				);
			},

			/**
			 *	Log out the current user and redirect
			 *
			 *	@param		{string}		redirect			Uri to redirect to afterwards
			 *
			 *	@return		void
			 */
			logout : function( redirect ) {
				Nino.http.sendRequest(
					'/.nino/auth/logout',
					'POST',
					function(){ window.location.replace( redirect ) }
				);
			},

		},

		client : {
			/**
			 *	Detect and store whether the current client is a mobile device
			 *
			 *	@return		void
			 */
			init : function() {
				Nino.client.isMobile = /Mobi|Android|iPhone|Android|webOS|iPhone|iPod|Blackberry|iPad/i.test( navigator.userAgent );
			},
			isMobile : false,


		},

		content : {

			/**
			 *	Return a translated text for a jstext key
			 *
			 *	@param		{string}		key						Text key, eg. '/form/info/success'
			 *
			 *	@return		{string}									Translated text or an empty string
			 */
			getText : function( key ) {
				return Nino.content.text[key] || '';
			},

		},


		controller : {

			/**
			 *	Bootstrap Nino: load js text translations, capture the current
			 *	request and initialize client detection and the event system
			 *
			 *	@return		void
			 */
			init : function() {
				Nino.content.text = ( typeof wn.NinoJstext !== 'undefined' ) ? wn.NinoJstext : [];
				Nino.http.request	= {
					'uri'						: wn.location.pathname,
					'domain'				: wn.location.origin,
					'query'					: Nino.http.readQueryVars(),
				};
				Nino.client.init();
				Nino.events.init();
			},
		},

		cookie : {

			/**
			 *	Set a cookie for one year, valid for the whole site
			 *
			 *	@param		{string}		key						Cookie name
			 *	@param		{string}		val						Cookie value (will be uri-encoded)
			 *
			 *	@return		void
			 */
			set : function(key,val) {
        dc.cookie = key + '=' + encodeURIComponent(val) + '; expires=' + ( new Date(Date.now() + 365 * 864e5).toUTCString() ) + '; path=/';
			},

			/**
			 *	Read a cookie value by name
			 *
			 *	@param		{string}		key						Cookie name
			 *
			 *	@return		{string|null}							Cookie value, or null if not set
			 */
			get : function(key) {
				if(!key) return null;
			  const match = document.cookie.match( new RegExp('(?:^|; )' + key.replace(/([.*+?^${}()|[\]\\])/g, '\\$1') + '=([^;]*)') );
			  return match ? decodeURIComponent(match[1]) : null;
			},

		},

		events : {

			_events : {
				'ready'	: [],
				'resize' : [],
				'scroll' : [],
			},
			_calls : {
				'ready' : false,
				'resize' : false,
				'scroll' : false,
			},

			/**
			 *	Register a callback for a lifecycle event ('ready'/'resize'/'scroll').
			 *	If the event already fired once, the callback is invoked immediately.
			 *
			 *	@param		{string}		evt						Event name ('ready', 'resize' or 'scroll')
			 *	@param		{Function}	cb						Callback to register
			 *
			 *	@return		void
			 */
			bindCallback : function( evt, cb ) {

				if( typeof Nino.events._events[evt] === 'undefined' )
					return;

				Nino.events._events[evt].push( cb );

				if( Nino.events._calls[evt] !== false )
					cb.call( cb, Nino.events._calls[evt] );
			},

			/**
			 *	Wire up the ready/resize/scroll lifecycle events and dispatch an
			 *	initial resize/scroll once the document is ready
			 *
			 *	@return		void
			 */
			init : function() {

				// domReady
				/**
				 *	Fire all registered 'ready' callbacks once, either on
				 *	DOMContentLoaded or immediately if already ready
				 *
				 *	@param		{Event}		e							domReady event (or the setTimeout call)
				 *
				 *	@return		void
				 */
				const cbReady = function( e ) {
					if( Nino.events._calls['ready'] !== false )
						return;

					wn.dispatchEvent( new Event( 'resize' ) );
					wn.dispatchEvent( new Event( 'scroll' ) );

					Nino.events._calls['ready'] = e;
					Nino.events._events['ready'].forEach( cb => { cb(Nino.events._calls['ready']) } );
				};

				if( dc.readyState === 'complete' || dc.readyState === 'interactive' )
					setTimeout( cbReady, 1 );
				else
					dc.addEventListener( 'DOMContentLoaded', cbReady );

				// onResize
				const resizeEvent = ( Nino.client.isMobile === false ) ? 'resize' : 'deviceorientation';
				// Throttle native resize/orientation events and notify registered 'resize' callbacks
				wn.addEventListener( resizeEvent, ( e ) => {
					if( Nino.events._calls['resize'] !== false )
						return;

					Nino.events._calls['resize'] = e;
					window.requestAnimationFrame( () => { Nino.events._events['resize'].forEach( cb => { cb.call(cb,Nino.events._calls['scroll']) } ) } );
					Nino.events._calls['resize'] = false;
				} );

				// onScroll
				// Throttle native scroll events and notify registered 'scroll' callbacks
				wn.addEventListener( 'scroll', ( e ) => {
					if( Nino.events._calls['scroll'] !== false )
						return;

					Nino.events._calls['scroll'] = e;
					window.requestAnimationFrame( () => { Nino.events._events['scroll'].forEach( cb => { cb.call(cb,Nino.events._calls['scroll']) } ) } );
					Nino.events._calls['scroll'] = false;
				} );
			},
		},


		http : {

			/**
			 *	Navigate the browser to a uri
			 *
			 *	@param		{string}		uri						Target uri
			 *
			 *	@return		void
			 */
			redirect : function( uri ) {
				wn.location.replace(uri);
			},

			/**
			 *	Parse the current location's query string into a key/value array
			 *
			 *	@return		{Array}										Parsed query vars
			 */
			readQueryVars : function( ) {
				let
					result = [],
					tmp = [],
					a = location.search.substr(1).split("&"),
					l = a.length;
				for( let i = 0; i < l; i++ ) {
						tmp = a[i].split("=");
						result[tmp[0]] = decodeURIComponent(tmp[1]);
				}

				return result;
			},

			/**
			 *	Send an xhr request with optional form data and basic-auth
			 *	credentials, normalizing the response/error into one callback.
			 *	The page's csrf token (from the [csrf] shortcode) is attached
			 *	to `data` automatically unless already set. The parsed json
			 *	body (or the raw text, if it isn't json) is passed back on
			 *	xhr.responseJSON, since the native xhr.response is read-only.
			 *
			 *	@param		{string}		uri						Target uri
			 *	@param		{string}		method				Http method
			 *	@param		{Function}	callback			Called with the (normalized) xhr once done
			 *	@param		{Object}		[data]				(optional) Key/value pairs sent as form data
			 *	@param		{Object}		[auth]				(optional) { user, pw } sent as basic-auth header
			 *
			 *	@return		void
			 */
			sendRequest : function( uri, method, callback, data, auth ) {
				let
					formdata		= new FormData(),
					xhr 				= new XMLHttpRequest(),
					/**
					 *	Normalize the xhr result (parse a json body, map network
					 *	error types to a status code) before calling back
					 *
					 *	@param		{Event}		e							Load/error/timeout/abort event
					 *
					 *	@return		void
					 */
					responseFn = function(e) {

					  const xhrCopy = xhr;
					  // xhr.response is a read-only accessor (assigning to it is a silent no-op),
					  // so the parsed body goes on a separate .responseJSON property instead
					  try { xhrCopy.responseJSON = JSON.parse( xhrCopy.responseText ); } catch (e) { xhrCopy.responseJSON = xhrCopy.responseText; }

					  if( e.type === 'error' ) xhrCopy.status = 500;
					  if( e.type === 'timeout' ) xhrCopy.status = 408;
					  if( e.type === 'abort' ) xhrCopy.status = 499;

					  callback.call( callback, xhrCopy );
					};

				xhr.open( method, uri, true );
				xhr.onload				= responseFn;
			  xhr.onerror 			= responseFn;
			  xhr.ontimeout 		= responseFn;
			  xhr.onabort 			= responseFn;

				// Data
				data = ( data && typeof data === 'object' ) ? data : {};

				// Auto-attach the page's csrf token (added by the [csrf] shortcode) to every POST,
				// so callers like Nino.auth.login/logout don't each have to know about it
				if( typeof data._csrf === 'undefined' ) {
					const csrfInput = dc.querySelector( 'input[name="_csrf"]' );
					if( csrfInput !== null )
						data._csrf = csrfInput.value;
				}

				Object.keys( data ).forEach( function( key ) { formdata.append( key, data[key] ) } );

				// Auth
				if( typeof auth !== 'undefined' && typeof auth.user !== 'undefined' && typeof auth.pw !== 'undefined' )
					xhr.setRequestHeader( 'Authorization', "Basic " + btoa( auth.user + ':' + auth.pw ) );

				xhr.send( formdata );
			},
		},
	};

	Nino.controller.init();

})(window, document, document.documentElement, document.body);
