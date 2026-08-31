/**
 *	Nino										A compact filesystembased php framework
 *	NinoAdminJS						The shared behaviour half of the management design system:
 *													small DOM primitives the four tool frontends build their
 *													chrome from, and the pure table model behind the list
 *													views. Nino.admin.css is the vocabulary, this is what
 *													assembles it.
 *
 *													Separate from Nino.js for the same reason Nino.ui.js is:
 *													who needs it. Nino.js is in the public site's script
 *													bundle, so every visitor was being served the complete
 *													table renderer, the field builders and the switch - none
 *													of which a page has any use for. Three files, three
 *													audiences: Nino.js everywhere, Nino.ui.js on the public
 *													site, this one behind the login.
 *
 *													Loaded after Nino.js, which owns the namespace this
 *													extends - on its own it has nothing to attach to.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	if( typeof wn.Nino === 'undefined' )
		return;

	/**
	 *	Small DOM primitives shared by Nino's four administration apps.
	 *	They only assign the common chrome classes from Nino.admin.css;
	 *	application modules keep ownership of labels, state and events.
	 */
	wn.Nino.adminUi = {

		// How many search hits elementList() draws at once. The search itself
		// always runs over the whole catalogue - this caps the rows, so a type
		// with thousands of elements stays a panel rather than a page, and the
		// control says how many more it found
		ELEMENTLIST_RESULTS : 50,


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
		 *	Show a frame at a layout width it does not have room for, scaled to
		 *	the room it does have.
		 *
		 *	A preview panel is half a tool pane wide - around 900px - and a page
		 *	designed for a desktop browser never meets its own layout limits
		 *	there. Nino's widest content ceiling is 160rem, so every setting for
		 *	it rendered identically: the frame was narrower than the narrowest of
		 *	them, and the difference the operator was choosing between could not
		 *	reach the glass.
		 *
		 *	So the frame is given the width a desktop browser has and then scaled
		 *	down as a whole. Everything inside keeps its proportions exactly -
		 *	which is what separates this from shrinking the root font, where the
		 *	type would move against everything measured in px and the preview
		 *	would start lying about the one thing it was just taught to tell the
		 *	truth about.
		 *
		 *	The height is solved rather than set: at scale f, filling a box h tall
		 *	needs h/f of document, so the page gets exactly as much vertical room
		 *	as the panel can show and no more.
		 *
		 *	@param	{Element}		frame			The iframe, absolutely placed in its port
		 *	@param	{Element}		port			The box it is shown inside
		 *	@param	{number}		width			The layout width to render at
		 *	@param	{number}		[height]	The layout height to render at. Given, the
		 *														whole page is fitted into the port; omitted, the
		 *														page is given as much height as the port shows
		 *
		 *	@return	{Function}						fit(), for calling after a layout change
		 */
		scaleFrame : function( frame, port, width, height ) {

			const fit = function() {

				const room = port.getBoundingClientRect();

				if( room.width < 1 || room.height < 1 )
					return;

				/*	Without a height the page is given the panel's, solved back
					through the scale: as much document as the port can show and
					no more. That fits by width alone, so a page taller than the
					port scrolls - and a page whose own lengths are viewport
					relative gets a different viewport on every window size.

					With one, both dimensions are fixed and the scale is
					whichever of the two fits: the whole page is on screen at a
					stable layout, and what changes with the panel is how small
					it is drawn rather than how much of it there is.	*/
				const solved = height > 0 ? height : Math.round( room.height / Math.min( 1, room.width / width ) );

				// Never magnified: on a panel wider than a desktop browser the page
				// should be shown at its own size, not blown up past it
				const factor = Math.min( 1, room.width / width, room.height / solved );

				frame.style.width = width+ 'px';
				frame.style.height = solved+ 'px';
				frame.style.transform = 'scale('+ factor+ ')';

				/*	A page is taller than it is wide and a preview panel is the
					other way round, so fitting the whole page leaves room at the
					sides. Centred rather than left in the corner: the empty half
					of the port then reads as a margin instead of as a page that
					failed to fill it.	*/
				frame.style.left = height > 0 ? Math.round( ( room.width - width * factor ) / 2 )+ 'px' : '0';
				frame.style.top = height > 0 ? Math.round( ( room.height - solved * factor ) / 2 )+ 'px' : '0';
			};

			fit();

			// The panel resizes with the window, with the rail, and with the tab
			// the operator is on - watching the box is the only one of those the
			// caller does not have to remember to report
			if( typeof wn.ResizeObserver === 'function' )
				new wn.ResizeObserver( fit ).observe( port );

			return fit;
		},

		/**
		 *	A row of buttons where exactly one is active - tabs over a pair of
		 *	panels, a light/dark switch over a preview, anything that is one
		 *	choice made by pressing rather than by opening a list.
		 *
		 *	Owns the state and the aria, not the look: the caller passes elements
		 *	it has already styled, and gets back the one function it needs. Which
		 *	is the whole reason this is here - four copies of "toggle is-active on
		 *	one of these and set aria on all of them" is how one of them ends up
		 *	without the aria.
		 *
		 *	@param	{Object}		buttons		key -> element
		 *	@param	{string}		active		The key to start on
		 *	@param	{Function}	onSelect	Called with the newly picked key
		 *	@param	{string}		flag			The aria attribute the row uses:
		 *																'aria-selected' for tabs, 'aria-pressed'
		 *																for a switch. Defaults to pressed.
		 *
		 *	@return	{Function}						select( key ), for setting it from outside
		 */
		buttonRow : function( buttons, active, onSelect, flag ) {

			const attribute = flag || 'aria-pressed';

			const paint = function( key ) {
				Object.keys( buttons ).forEach( function( candidate ) {

					const button = buttons[candidate];

					if( button === null || typeof button === 'undefined' )
						return;

					const on = candidate === key;

					button.classList.toggle( 'is-active', on );
					button.setAttribute( attribute, on === true ? 'true' : 'false' );
				} );
			};

			Object.keys( buttons ).forEach( function( key ) {

				const button = buttons[key];

				if( button === null || typeof button === 'undefined' )
					return;

				button.addEventListener( 'click', function() {
					paint( key );
					if( typeof onSelect === 'function' )
						onSelect( key );
				} );
			} );

			paint( active );

			return paint;
		},

		/**
		 *	One setting picked from a fixed list, as a labelled select.
		 *
		 *	Owns the control and its options, not the wrapper: the caller passes
		 *	the field class its own panel already styles, so a generated field
		 *	sits beside a hand-written one without either having to know about
		 *	the other. Owns no strings either, same rule as table() and
		 *	switchField() - the label, the note and every option's text come
		 *	from the caller.
		 *
		 *	@param	{Object}	options		{ key, className, label, note, hint, options, value, onChange }
		 *
		 *	@return	{Element}						The label; its select carries data-key, and is
		 *													reachable as the label's own .control - so a caller
		 *													writing a value back into it needs no id of its own
		 */
		selectField : function( options ) {

			const field = dc.createElement('label');
			field.className = options.className || 'nino-admin-field';

			const name = dc.createElement('span');
			name.textContent = options.label || options.key;

			// The terse half of what a setting does, beside its name. The long
			// half is the select's title - ten settings with a sentence each
			// under them is a wall of text nobody reads.
			//
			// The trailing space belongs to the label rather than the note: the
			// two are separate inline boxes, so without it the name and the note
			// run together as one word
			if( options.note ) {
				name.textContent += ' ';
				const note = dc.createElement('small');
				note.textContent = options.note;
				name.appendChild( note );
			}

			field.appendChild( name );

			const select = dc.createElement('select');
			select.className = 'nino-admin-input';
			select.setAttribute( 'data-key', options.key );

			if( options.hint )
				select.setAttribute( 'title', options.hint );

			( options.options || [] ).forEach( function( option ) {
				const el = dc.createElement('option');
				el.value = String( option.value );
				el.textContent = option.label;
				select.appendChild( el );
			} );

			select.value = String( options.value );

			select.addEventListener( 'change', function() {
				if( typeof options.onChange === 'function' )
					options.onChange( select.value );
			} );

			field.appendChild( select );

			return field;
		},

		/**
		 *	Mark a field as changed from what is stored, and say what the
		 *	stored value was.
		 *
		 *	A dirty state that only counts is a dirty state the operator has to
		 *	trust: it says something is different but not what, so the only way
		 *	back to a known position is to discard everything. Naming the value
		 *	each field started from turns that into an ordinary comparison -
		 *	which is the whole of "saved vs current" for a pane of settings.
		 *
		 *	The mark lives inside the field's name, beside any note it carries,
		 *	and is created once and then hidden - a marker that is removed and
		 *	rebuilt on every keystroke is a marker a screen reader announces on
		 *	every keystroke.
		 *
		 *	@param	{Element}	field			A field built by selectField(), or any
		 *														label whose first child is its name
		 *	@param	{string}	text			What the value was, or '' for unchanged
		 *
		 *	@return	void
		 */
		fieldChange : function( field, text,  ) {

			if( field === null || typeof field === 'undefined' )
				return;

			const name = field.children[0];

			if( name === null || typeof name === 'undefined' )
				return;

			let mark = field.changeMark;

			if( mark === null || typeof mark === 'undefined' ) {
				// A span rather than a <small>: tools style the notes in their
				// own fields by element, and this is not one of those
				mark = dc.createElement('span');
				mark.className = 'nino-admin-changed';
				name.appendChild( mark );
				field.changeMark = mark;
			}

			const changed = typeof text === 'string' && text !== '';

			// mark.textContent = changed === true ? text : '';
			mark.hidden = changed === false;
			field.classList.toggle( 'is-changed', changed );
		},

		/**
		 *	One boolean setting as the design system's switch. The written
		 *	on/off state next to the knob is the point of it: knob position
		 *	alone is the one signal a low-vision reader loses, so the words
		 *	are part of the component rather than a caller's decision.
		 *
		 *	Owns no strings, same rule as table(): /_admin is English and
		 *	/_editor translates, so both the label and the two state words
		 *	come from the caller.
		 *
		 *	@param	{Object}	options		{ key, checked, label, hint, on, off }
		 *
		 *	@return	{Element}						The label; its input carries data-key
		 */
		switchField : function( options ) {

			const label = dc.createElement('label');
			label.className = 'nino-admin-switch';

			const input = dc.createElement('input');
			input.type = 'checkbox';
			input.checked = options.checked === true;
			if( options.key )
				input.dataset.key = options.key;
			label.appendChild( input );

			const track = dc.createElement('span');
			track.className = 'nino-admin-switch-track';
			label.appendChild( track );

			const copy = dc.createElement('span');
			copy.className = 'nino-admin-switch-copy';
			copy.appendChild( dc.createTextNode( options.label ) );

			if( options.hint ) {
				const hint = dc.createElement('small');
				hint.textContent = options.hint;
				copy.appendChild( hint );
			}
			label.appendChild( copy );

			const on 		= options.on ?? 'on';
			const off 	= options.off ?? 'off';
			const state = dc.createElement('span');
			state.className = 'nino-admin-switch-state';
			state.textContent = input.checked ? on : off;
			input.addEventListener( 'change', function() { state.textContent = input.checked ? on : off } );
			label.appendChild( state );

			return label;
		},

		/**
		 *	Whether one model field holds a list of element references rather
		 *	than a single uri - the client-side half of
		 *	\Nino\Elements::isMultiElement(), and deliberately the same rule:
		 *	presence of a number under 'multiple' is the switch, so a model
		 *	written before the setting existed stays a single reference in both
		 *	element forms exactly as it does in the kernel.
		 *
		 *	@param	{Object}	field		Model field definition
		 *
		 *	@return	{boolean}
		 */
		isMultiElement : function( field ) {
			return ( field || {} ).type === 'element' && typeof ( field || {} ).multiple === 'number';
		},

		/**
		 *	The control an element field uses once its model lets it hold more
		 *	than one reference: the chosen elements as an ordered, scrollable
		 *	list with move/remove per row, and a search field below it that
		 *	offers what may still be added.
		 *
		 *	A multi-select would have been the smaller control and the wrong
		 *	one. Order is part of this value - it is the order a template
		 *	renders the referenced elements in - and a `<select multiple>`
		 *	has no notion of one, nor anywhere to put the up/down that gives
		 *	the operator a way to say it. It also shows the chosen entries
		 *	scattered through the whole catalogue rather than together, which
		 *	is exactly backwards for a field whose question is "which of these,
		 *	in what order".
		 *
		 *	Searching is done here, over the options the caller already
		 *	loaded, rather than against an endpoint of its own. Both element
		 *	forms fetch the referenced type's elements up front to build the
		 *	single-reference select (see either tool's
		 *	_loadReferenceOptions()), so the data is in the page before this
		 *	control is built - a request per keystroke would ask a second time
		 *	for what is already here, and would have to grow a debounce and a
		 *	race guard to do it.
		 *
		 *	Owns no strings, same rule as table() and switchField(): /_admin is
		 *	English and /_editor translates, so every word comes from the
		 *	caller through options.text.
		 *
		 *	@param	{Object}	options		{ key, label, value, limit, options, text, onChange }
		 *													- key      the model key, written onto the hidden input
		 *													- value    array of currently chosen uris, in order
		 *													- limit    max entries, 0 for unlimited
		 *													- options  [ { value, label } ] everything selectable
		 *													- text     { search, empty, noMatches, more, missing,
		 *													             up, down, remove, add, full }
		 *
		 *	@return	{Element}						The field wrapper; its hidden input carries
		 *													data-field/data-type and holds the value as json
		 */
		elementList : function( options ) {

			const text 	= options.text || {};
			const limit = Math.max( 0, parseInt( options.limit, 10 ) || 0 );
			const all 	= Array.isArray( options.options ) ? options.options : [];

			// A stored value survives a target that was deleted since, exactly
			// as the single-reference select does - dropping it here would turn
			// "this points at something gone" into a silent data loss the next
			// save makes permanent
			let chosen = Array.isArray( options.value ) ? options.value.slice() : [];

			const field = dc.createElement('div');
			field.className = 'nino-admin-field nino-admin-elementlist';

			const name = dc.createElement('span');
			name.textContent = options.label || options.key;
			field.appendChild( name );

			// The value itself. A hidden input rather than component state the
			// form would have to know about: every other field in both forms is
			// read back out of the dom by [data-field], and a control that
			// needed its own read path would be the one field that breaks
			// locale switching, dirty tracking and save alike
			const store = dc.createElement('input');
			store.type = 'hidden';
			store.dataset.field = options.key;
			store.dataset.type = 'element';
			store.dataset.multiple = String( limit );
			field.appendChild( store );

			const list = dc.createElement('ul');
			list.className = 'nino-admin-elementlist-chosen';
			field.appendChild( list );

			const counter = dc.createElement('span');
			counter.className = 'nino-admin-elementlist-count';
			field.appendChild( counter );

			const search = dc.createElement('input');
			search.type = 'search';
			search.className = 'nino-admin-input nino-admin-elementlist-search';
			search.placeholder = text.search || '';
			field.appendChild( search );

			const results = dc.createElement('ul');
			results.className = 'nino-admin-elementlist-results';
			field.appendChild( results );

			/**
			 *	The label an option carries, or the uri itself marked as
			 *	missing when nothing on offer matches it any more
			 */
			function labelFor( uri ) {
				const match = all.find( function( option ) { return option.value === uri } );
				return ( match === undefined ) ? uri + ( text.missing ? ' (' + text.missing + ')' : '' ) : match.label;
			}

			function commit() {
				store.value = JSON.stringify( chosen );
				if( typeof options.onChange === 'function' )
					options.onChange( chosen.slice() );
			}

			function move( index, delta ) {
				const target = index + delta;
				if( target < 0 || target >= chosen.length )
					return;
				const moved = chosen[index];
				chosen[index] = chosen[target];
				chosen[target] = moved;
				commit();
				draw();
			}

			function remove( index ) {
				chosen.splice( index, 1 );
				commit();
				draw();
			}

			function add( uri ) {
				// The cap is the model's, so the control refuses past it rather
				// than letting the save be the thing that says no. The kernel
				// checks it again either way - this is the courtesy, not the
				// boundary
				if( chosen.indexOf( uri ) !== -1 || ( limit > 0 && chosen.length >= limit ) )
					return;
				chosen.push( uri );
				commit();
				draw();
			}

			function button( className, title, glyph, disabled, onClick ) {
				const el = dc.createElement('button');
				el.type = 'button';
				el.className = className;
				el.textContent = glyph;
				if( title ) {
					el.title = title;
					el.setAttribute( 'aria-label', title );
				}
				el.disabled = disabled === true;
				el.addEventListener( 'click', onClick );
				return el;
			}

			function drawChosen() {

				list.innerHTML = '';

				if( chosen.length === 0 ) {
					const empty = dc.createElement('li');
					empty.className = 'nino-admin-elementlist-empty';
					empty.textContent = text.empty || '';
					list.appendChild( empty );
					return;
				}

				chosen.forEach( function( uri, index ) {

					const row = dc.createElement('li');

					const rowLabel = dc.createElement('span');
					rowLabel.className = 'nino-admin-elementlist-label';
					rowLabel.textContent = labelFor( uri );
					rowLabel.title = uri;
					row.appendChild( rowLabel );

					const actions = dc.createElement('span');
					actions.className = 'nino-admin-elementlist-actions';
					actions.appendChild( button( '', text.up, '↑', index === 0, function() { move( index, -1 ) } ) );
					actions.appendChild( button( '', text.down, '↓', index === chosen.length - 1, function() { move( index, 1 ) } ) );
					actions.appendChild( button( 'nino-admin-elementlist-remove', text.remove, '✕', false, function() { remove( index ) } ) );
					row.appendChild( actions );

					list.appendChild( row );
				} );
			}

			function drawResults() {

				results.innerHTML = '';

				const full = ( limit > 0 && chosen.length >= limit );

				counter.textContent = ( limit > 0 ) ? chosen.length + ' / ' + limit : String( chosen.length );
				counter.classList.toggle( 'is-limit', full );
				search.disabled = full;

				if( full === true ) {
					const note = dc.createElement('li');
					note.className = 'nino-admin-elementlist-note';
					note.textContent = text.full || '';
					results.appendChild( note );
					return;
				}

				const query = search.value.trim().toLowerCase();

				const matches = all.filter( function( option ) {
					if( chosen.indexOf( option.value ) !== -1 )
						return false;
					if( query === '' )
						return true;
					return ( option.label || '' ).toLowerCase().indexOf( query ) !== -1
						|| ( option.value || '' ).toLowerCase().indexOf( query ) !== -1;
				} );

				if( matches.length === 0 ) {
					const none = dc.createElement('li');
					none.className = 'nino-admin-elementlist-note';
					none.textContent = text.noMatches || '';
					results.appendChild( none );
					return;
				}

				// A catalogue of any size still has to render into a panel. The
				// cap is on the rows drawn, never on what the search looked
				// through, and the note says so - a silently truncated result
				// list would read as "there is nothing else", which is the one
				// thing it must not mean
				matches.slice( 0, Nino.adminUi.ELEMENTLIST_RESULTS ).forEach( function( option ) {
					const row = dc.createElement('li');
					const addButton = button( 'nino-admin-elementlist-add', text.add, '+', false, function() { add( option.value ) } );
					const rowLabel = dc.createElement('span');
					rowLabel.className = 'nino-admin-elementlist-label';
					rowLabel.textContent = option.label;
					rowLabel.title = option.value;
					row.appendChild( addButton );
					row.appendChild( rowLabel );
					results.appendChild( row );
				} );

				if( matches.length > Nino.adminUi.ELEMENTLIST_RESULTS && text.more ) {
					const more = dc.createElement('li');
					more.className = 'nino-admin-elementlist-note';
					more.textContent = text.more.replace( '%d', String( matches.length ) );
					results.appendChild( more );
				}
			}

			function draw() {
				drawChosen();
				drawResults();
			}

			search.addEventListener( 'input', drawResults );

			store.value = JSON.stringify( chosen );
			draw();

			return field;
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

				// A multi element field holds its references as a list. Joined
				// with a separator rather than left to String(), whose comma
				// carries no space and reads as one run-on uri
				if( Array.isArray( value ) === true )
					return value.join(', ');

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
				// An element field holding an empty list is as absent as a blank
				// string, and sort() has to place it with the other absent
				// values rather than among the present ones
				if( Array.isArray( value ) === true )
					return value.length === 0;
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
		}
	};

})(window, document, document.documentElement, document.body);
