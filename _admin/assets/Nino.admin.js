/**
 *	Nino										A compact filesystembased php framework
 *	Nino.admin.js						The shared behaviour half of the management design system:
 *													small DOM primitives the tool frontends build their
 *													chrome from, the pure table model behind the list
 *													views, and the one way a panel talks to the server:
 *													Nino.adminUi.api, with the status line and the
 *													messages that say what failed. style.css is the
 *													vocabulary, this is what assembles it.
 *
 *													Separate from Nino.js for the same reason Nino.ui.js is:
 *													who needs it. Nino.js is in the public site's script
 *													bundle, so every visitor was being served the complete
 *													table renderer, the field builders and the switch - none
 *													of which a page has any use for. Three files, three
 *													audiences: Nino.js everywhere, Nino.ui.js on the public
 *													site, this one in the tool frontends.
 *
 *													Loaded after Nino.js, which owns the namespace this
 *													extends - on its own it has nothing to attach to.
 *													Names no Nino.admin member itself, which is what lets
 *													the setup wizard load it without the workbench shell
 *													in assets/script.js.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	if( typeof wn.Nino === 'undefined' )
		return;

	/**
	 *	Small DOM primitives shared by Nino's administration surfaces.
	 *	They only assign the common chrome classes from style.css;
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
		 *	The head of the pane an element stands in: the row the shell
		 *	renders over every panel but the Dashboard (see
		 *	\Nino\Admin\Panels::$html) - the panel's name, the registry's tab
		 *	strip beside it, a slot for actions at its end. A panel with tabs
		 *	of its own puts its strip there through tabs(), which takes the
		 *	place after the name and replaces whatever strip stood there, so
		 *	a screen drawn again does not stack two; a panel with buttons
		 *	over its screen appends them to actions. One row across the
		 *	workbench, then, rather than a title and a strip per panel.
		 *
		 *	null where there is no head - the Dashboard, or a script rendered
		 *	somewhere other than a pane - so a panel keeps its strip where it
		 *	drew it
		 *
		 *	@param		{Element}		el						Any element inside the pane - the mount, usually
		 *
		 *	@return		{Object|null}						{ element, title, actions, tabs( strip ) }
		 */
		panelHead : function( el ) {
			const pane = el && typeof el.closest === 'function' ? el.closest('[data-panel]') : null;
			const head = pane ? pane.querySelector(':scope > .admin-panel-head') : null;
			if( !head )
				return null;
			return {
				element	: head,
				title		: head.querySelector(':scope > .admin-panel-title'),
				actions	: head.querySelector(':scope > .admin-panel-actions'),
				tabs		: function( strip ) {
					const before = head.querySelector(':scope > .admin-panel-tabs');
					if( before !== null && before !== strip )
						before.remove();
					// The class the head lays a strip out by, whatever the
					// panel called its own
					strip.classList.add('admin-panel-tabs');
					const title = head.querySelector(':scope > .admin-panel-title');
					if( title !== null )
						title.insertAdjacentElement( 'afterend', strip );
					else
						head.insertBefore( strip, head.firstChild );
					return strip;
				},
			};
		},

		/**
		 *	The one way a panel says "nothing here yet": a list without entries,
		 *	a table without rows, a scan that found nothing. Owns no strings -
		 *	the caller says what is missing, in the interface language
		 *
		 *	@param		{string}	message
		 *
		 *	@return		{Element}
		 */
		emptyState : function( message ) {
			const empty = dc.createElement('p');
			empty.className = 'nino-admin-empty';
			empty.textContent = message;
			return empty;
		},

		// Whether choiceDialog() has a question open - one at a time: a second
		// call while it stands is the same click arriving twice
		_choiceOpen : false,

		/**
		 *	A modal question with more than two answers (a confirm() has two):
		 *	a native <dialog> in the workbench root, opened with showModal(),
		 *	so the page behind keeps everything that was typed into it. Escape
		 *	is the secondary choice, the one that does nothing; focus goes back
		 *	to what had it when the question was asked.
		 *
		 *	Owns no strings and names no Nino.admin member: the caller says
		 *	what to ask and what the answers are called, in the interface
		 *	language. Where the browser has no showModal() the question is
		 *	asked with confirm() - OK picks the 'primary' choice (the 'danger'
		 *	one where there is none), Cancel the 'secondary' one: nothing is
		 *	thrown away by agreeing to the question as it is worded
		 *
		 *	@param		{Object}		options
		 *	@param		{string}		[options.title]
		 *	@param		{string}		options.message
		 *	@param		{Array}			options.choices				[ { value, label, kind } ], kind 'primary', 'danger' or 'secondary'
		 *	@param		{Function}	options.onChoose			Called once with the chosen value
		 *
		 *	@return		{boolean}										false when a question is already open
		 */
		choiceDialog : function( options ) {

			if( Nino.adminUi._choiceOpen === true )
				return false;

			const choices = options.choices;
			const cancel 	= choices.find( function( choice ) { return choice.kind === 'secondary' } ) ?? choices[choices.length - 1];
			const dialog 	= dc.createElement('dialog');

			if( typeof dialog.showModal !== 'function' ) {
				const yes = choices.find( function( choice ) { return choice.kind === 'primary' } ) ?? choices.find( function( choice ) { return choice.kind === 'danger' } ) ?? cancel;
				options.onChoose( wn.confirm( options.message ) === true ? yes.value : cancel.value );
				return true;
			}

			Nino.adminUi._choiceOpen = true;

			const opener = dc.activeElement;
			let chosen = null;

			dialog.className = 'nino-admin-dialog';
			dialog.setAttribute( 'aria-describedby', 'nino-admin-choice-text' );

			const body = dc.createElement('div');
			body.className = 'nino-admin-dialog-body';

			if( typeof options.title === 'string' && options.title !== '' ) {
				const title = dc.createElement('h2');
				title.id = 'nino-admin-choice-title';
				title.className = 'nino-admin-dialog-title';
				title.textContent = options.title;
				body.appendChild( title );
				dialog.setAttribute( 'aria-labelledby', 'nino-admin-choice-title' );
			}

			const text = dc.createElement('p');
			text.id = 'nino-admin-choice-text';
			text.textContent = options.message;
			body.appendChild( text );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-dialog-actions';

			choices.forEach( function( choice ) {
				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'nino-admin-btn-'+ ( choice.kind === 'primary' || choice.kind === 'danger' ? choice.kind : 'secondary' );
				btn.textContent = choice.label;
				btn.addEventListener( 'click', function() {
					chosen = choice;
					dialog.close();
				} );
				actions.appendChild( btn );
			} );

			body.appendChild( actions );
			dialog.appendChild( body );

			// The Escape key closes it too, and says no
			dialog.addEventListener( 'close', function() {
				dialog.remove();
				Nino.adminUi._choiceOpen = false;
				if( opener !== null && typeof opener.focus === 'function' )
					opener.focus();
				options.onChoose( ( chosen ?? cancel ).value );
			} );

			// Inside the workbench root, not the body: the design system's rules
			// are scoped to .nino-admin and would not reach it there
			( dc.getElementById('admin-page-wrap') ?? dc.body ).appendChild( dialog );
			dialog.showModal();

			return true;
		},

		/**
		 *	A label the server sends either as a fill key or as literal text:
		 *	a key ('/…') is looked up in the interface language, anything else
		 *	is shown as it is - the rule every panel applies to a nav label, a
		 *	tile caption, a permission or a field schema
		 *
		 *	@param		{*}				value
		 *
		 *	@return		{string}
		 */
		text : function( value ) {
			value = String( value ?? '' );
			return value.charAt(0) === '/' ? ( Nino.content.getText( value ) || value ) : value;
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

			/*	The panel resizes with the window, with the rail, and with the tab
				the operator is on - watching the box is the only one of those the
				caller does not have to remember to report.

				One observer per port, not one per call. A caller re-fits the same
				box whenever the thing inside it changes - Design rebuilds the
				scaler on every width change and every preview it loads - and each
				of those used to leave the previous observer attached and firing.
				After n calls one drag of the window edge ran fit() n times, and
				nothing ever took one away. The port carries its observer, so a
				second call replaces the first rather than joining it.	*/
			if( typeof wn.ResizeObserver === 'function' ) {
				if( port._ninoScaleObserver )
					port._ninoScaleObserver.disconnect();
				port._ninoScaleObserver = new wn.ResizeObserver( fit );
				port._ninoScaleObserver.observe( port );
			}

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
			const keys 			= Object.keys( buttons ).filter( function( key ) {
				return buttons[key] !== null && typeof buttons[key] !== 'undefined';
			} );

			const paint = function( key ) {
				keys.forEach( function( candidate ) {

					const button = buttons[candidate];
					const on 		= candidate === key;

					button.classList.toggle( 'is-active', on );
					button.setAttribute( attribute, on === true ? 'true' : 'false' );

					/*	A tab strip is one tab stop, not one per tab: the pattern
						reaches a strip with Tab and walks it with the arrows, and a
						row that leaves every tab tabbable makes the operator press
						Tab once per tab to get past a strip they did not want. The
						switch does not do this - aria-pressed buttons are ordinary
						buttons and each is its own stop	*/
					if( attribute === 'aria-selected' )
						button.tabIndex = on === true ? 0 : -1;
				} );
			};

			keys.forEach( function( key ) {
				buttons[key].addEventListener( 'click', function() {
					paint( key );
					if( typeof onSelect === 'function' )
						onSelect( key );
				} );
			} );

			// Only a tablist gets the arrows; a row of aria-pressed switches is
			// a row of ordinary buttons and the browser already walks it
			if( attribute === 'aria-selected' )
				Nino.adminUi.tabKeys( keys.map( function( key ) { return buttons[key] } ), function( at ) {
					paint( keys[at] );
					if( typeof onSelect === 'function' )
						onSelect( keys[at] );
				} );

			paint( active );

			return paint;
		},

		/**
		 *	The keyboard half of a tab strip: Left/Right walk it, Home and End
		 *	jump to its ends, and exactly one tab is in the page's tab order at
		 *	a time.
		 *
		 *	Here rather than in each strip because the workbench has three of
		 *	them - the pane strip the shell renders (see \Nino\Admin\Panels::$html),
		 *	and the Features panel's two - and every one of them was a row of
		 *	buttons a pointer could operate and an arrow key could not. A tab
		 *	is announced as a tab, so it is offered the keys a tab answers to;
		 *	`Nino.ui.js` gives the public site's tabs the same four.
		 *
		 *	@param	{Array}			tabs			The strip's buttons, in the order they are drawn
		 *	@param	{Function}	onMove		Called with the index the operator moved to
		 *
		 *	@return	{Function}						roving( index ), for pointing the one tab stop
		 *													from outside
		 */
		tabKeys : function( tabs, onMove ) {

			const strip = ( tabs || [] ).filter( function( tab ) {
				return tab !== null && typeof tab !== 'undefined';
			} );

			const roving = function( index ) {
				strip.forEach( function( tab, at ) { tab.tabIndex = ( at === index ) ? 0 : -1 } );
			};

			strip.forEach( function( tab, at ) {

				tab.addEventListener( 'keydown', function( ev ) {

					if( [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].indexOf( ev.key ) === -1 )
						return;

					// The arrows scroll the page otherwise, which is the one thing
					// the operator did not ask for by pressing them on a tab
					ev.preventDefault();

					let next = at;
					if( ev.key === 'ArrowLeft' )	next = ( at - 1 + strip.length ) % strip.length;
					if( ev.key === 'ArrowRight' )	next = ( at + 1 ) % strip.length;
					if( ev.key === 'Home' )				next = 0;
					if( ev.key === 'End' )				next = strip.length - 1;

					roving( next );

					if( typeof strip[next].focus === 'function' )
						strip[next].focus();

					if( typeof onMove === 'function' )
						onMove( next );
				} );
			} );

			return roving;
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

			mark.textContent = changed === true ? text : '';
			mark.hidden = changed === false;
			field.classList.toggle( 'is-changed', changed );
		},

		/**
		 *	One boolean setting as the design system's switch. The written
		 *	on/off state next to the knob is the point of it: knob position
		 *	alone is the one signal a low-vision reader loses, so the words
		 *	are part of the component rather than a caller's decision.
		 *
		 *	Owns no strings, same rule as table(): every word the workbench
		 *	shows is a fill, so both the label and the two state words come
		 *	from the caller.
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

			const on 		= options.on ?? ( Nino.content.getText('/_admin/common/label/on') || 'on' );
			const off 	= options.off ?? ( Nino.content.getText('/_admin/common/label/off') || 'off' );
			const state = dc.createElement('span');
			state.className = 'nino-admin-switch-state';
			state.textContent = input.checked ? on : off;
			input.addEventListener( 'change', function() { state.textContent = input.checked ? on : off } );
			label.appendChild( state );

			return label;
		},

		/**
		 *	A whole number in bounds, as a number input the browser limits the
		 *	same way the backend rejects - so an out-of-range value is caught
		 *	before the request, and still caught by the backend if it is not.
		 *	Owns no strings, same rule as switchField(): the label, the hint
		 *	and the unit come from the caller. A duration in seconds is
		 *	unreadable as a number - 3600 says nothing, "1 h" does - so a
		 *	field whose unit is seconds gets a reading aid, recomputed on
		 *	input so the two never disagree. The Config panel, the Users
		 *	panel's login-protection tab and a feature's settings in the
		 *	Features panel render their numbers with it.
		 *
		 *	@param	{Object}	options		{ key, value, min, max, unit, label, hint }
		 *
		 *	@return	{Element}						The label; its input carries data-key
		 */
		numberField : function( options ) {

			const label = dc.createElement('label');
			label.className = 'nino-admin-field';

			// Label, hint and unit may arrive as fill keys - a schema the
			// backend declares once and every language reads (see text())
			const name = dc.createElement('span');
			name.textContent = options.unit
				? Nino.adminUi.text( options.label )+ ' ('+ Nino.adminUi.text( '/_admin/common/unit/'+ options.unit )+ ')'
				: Nino.adminUi.text( options.label );
			label.appendChild( name );

			const input = dc.createElement('input');
			input.type = 'number';
			input.step = '1';
			input.value = options.value;
			if( options.key )
				input.dataset.key = options.key;
			if( options.min !== undefined ) input.min = options.min;
			if( options.max !== undefined ) input.max = options.max;
			label.appendChild( input );

			if( options.hint ) {
				const hint = dc.createElement('small');
				hint.className = 'nino-admin-hint';
				hint.textContent = Nino.adminUi.text( options.hint );
				label.appendChild( hint );
			}

			if( options.unit === 'seconds' ) {
				const human = dc.createElement('small');
				human.className = 'nino-admin-hint';
				const setHuman = function() { human.textContent = Nino.adminUi.humanDuration( input.value ) };
				input.addEventListener( 'input', setHuman );
				setHuman();
				label.appendChild( human );
			}

			return label;
		},

		/**
		 *	"3600" -> "= 1 h". Rounded, not exact: this is a reading aid next
		 *	to the real value, not a second source of truth
		 *
		 *	@param	{string|number}	seconds
		 *
		 *	@return	{string}
		 */
		humanDuration : function( seconds ) {

			const total = parseInt( seconds, 10 );
			if( isNaN( total ) === true || total <= 0 )
				return '';

			if( total < 60 )
				return '= '+ total+ ' s';

			if( total < 3600 )
				return '= '+ Math.round( total / 60 )+ ' min';

			if( total < 86400 )
				return '= '+ ( Math.round( total / 360 ) / 10 )+ ' h';

			return '= '+ ( Math.round( total / 8640 ) / 10 )+ ' d';
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
		 *	English and the workbench translates, so every word comes from the
		 *	caller through options.text.
		 *
		 *	@param	{Object}	options		{ key, label, value, limit, options, ordered, text, onChange }
		 *													- key      the model key, written onto the hidden input
		 *													- value    array of currently chosen uris, in order
		 *													- limit    max entries, 0 for unlimited
		 *													- options  [ { value, label } ] everything selectable
		 *													- ordered  false for a set rather than a list: no move
		 *													           buttons, because the order carries no meaning
		 *													           and offering to change it would say it does
		 *													- text     { search, empty, noMatches, more, missing,
		 *													             up, down, remove, add, full }
		 *
		 *	@return	{Element}						The field wrapper; its hidden input carries
		 *													data-field/data-type and holds the value as json
		 */
		elementList : function( options ) {

			const text 		= options.text || {};
			const limit 	= Math.max( 0, parseInt( options.limit, 10 ) || 0 );
			const all 		= Array.isArray( options.options ) ? options.options : [];
			const ordered = options.ordered !== false;

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
			// The field's own name belongs to the chosen list, not to the box
			// below it - a <div> field, so the <span> above labels nothing at
			// all - and a placeholder is not a name: it is gone the moment
			// somebody types, and never reaches the accessibility tree as one
			search.setAttribute( 'aria-label', text.search || '' );
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
				// Writing the hidden input fires nothing; tell a surrounding form (status line) the value changed
				if( typeof store.dispatchEvent === 'function' && typeof wn.Event === 'function' )
					store.dispatchEvent( new wn.Event( 'change', { bubbles : true } ) );
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
					if( ordered === true ) {
						actions.appendChild( button( '', text.up, '↑', index === 0, function() { move( index, -1 ) } ) );
						actions.appendChild( button( '', text.down, '↓', index === chosen.length - 1, function() { move( index, 1 ) } ) );
					}
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
			// Types.php's FIELD_TYPES and its 'html' flag
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
			 *	correctly in both interface languages without a caller
			 *	passing a translation for it.
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
		 *	the same component reads correctly where a field is labelled by
		 *	its raw model key (the type editor) and where every label is a
		 *	fill (the element form, through Nino.content). Everything that can
		 *	be a number or a glyph - the pager arrows, the row range, a
		 *	boolean cell - is one, so the caller only has to supply two
		 *	actual sentences.
		 *
		 *	What is drawn as a glyph still has to be *called* something: a
		 *	button whose face is '‹' is announced as "button ‹" and the search
		 *	box's placeholder is gone the moment somebody types in it. Those
		 *	three names come from `labels` where the caller has a word, and
		 *	from the workbench's shared fills where it has not - the same rule
		 *	switchField()'s on/off pair follows, and the reason the caller
		 *	still does not have to know them.
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
		 *	@param		{Object}		options.labels				{ search, empty, noMatch } - and optionally
		 *																					{ prev, next, pageSize }, the three names the
		 *																					pager's glyphs carry; each falls back to
		 *																					/_admin/common/label/prevpage, -/nextpage and
		 *																					-/perpage
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

			/*	The three names the pager's glyphs carry: the caller's word where
				it has one, the workbench's shared fill where it has not, English
				where neither is there - the rule switchField()'s on/off pair
				follows. Three literal lookups rather than one built from a key:
				a fill that only a concatenated argument ever names is invisible
				to the static check every workbench script is held to	*/
			const prevLabel	= labels.prev 		?? ( Nino.content.getText('/_admin/common/label/prevpage') || 'Previous page' );
			const nextLabel	= labels.next 		?? ( Nino.content.getText('/_admin/common/label/nextpage') || 'Next page' );
			const sizeLabel	= labels.pageSize	?? ( Nino.content.getText('/_admin/common/label/perpage') || 'Rows per page' );

			mount.innerHTML = '';
			mount.classList.add('nino-admin-table-wrap');

			// --- search -----------------------------------------------
			const toolbar = dc.createElement('div');
			toolbar.className = 'nino-admin-table-toolbar';

			const search = dc.createElement('input');
			search.type = 'search';
			search.className = 'nino-admin-table-search';
			search.placeholder = labels.search || '';
			// A placeholder is not a name: it disappears on the first keystroke
			// and never reaches the accessibility tree as one. The toolbar has
			// no room for a visible label, so the box carries the same word the
			// placeholder shows
			search.setAttribute( 'aria-label', labels.search || '' );
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
			empty.className = 'nino-admin-empty';
			empty.textContent = labels.empty || '';
			mount.appendChild( empty );

			// --- pager ------------------------------------------------
			const pager = dc.createElement('div');
			pager.className = 'nino-admin-table-pager';

			const range = dc.createElement('span');
			range.className = 'nino-admin-table-range';

			const sizeSelect = dc.createElement('select');
			sizeSelect.className = 'nino-admin-table-size';
			sizeSelect.setAttribute( 'aria-label', sizeLabel );
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

			/*	The two arrows are glyphs, and a glyph is not a name: a screen
				reader announced the pager as "button ‹" and "button ›". The
				component owns no strings, so the word comes from the caller
				where there is one and from the shared fills where there is
				not - the rule switchField()'s on/off pair already follows	*/
			const prev = dc.createElement('button');
			prev.type = 'button';
			prev.className = 'nino-admin-table-step';
			prev.textContent = '\u2039';
			prev.setAttribute( 'aria-label', prevLabel );
			prev.addEventListener( 'click', function() { page--; draw() } );

			const next = dc.createElement('button');
			next.type = 'button';
			next.className = 'nino-admin-table-step';
			next.textContent = '\u203a';
			next.setAttribute( 'aria-label', nextLabel );
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

					const sorted = th.dataset.key === sortKey;

					th.classList.toggle( 'is-sorted', sorted );
					th.classList.toggle( 'is-desc', sorted && sortDir < 0 );

					/*	Which column the table is ordered by, said rather than only
						drawn: the ↑/↓ above is a ::after on the sorted header, and a
						generated glyph is one of the two things a screen reader is
						never handed - the other being the colour it is drawn in. A
						header that is not the sorted one says "none", which is what
						tells the reader the column can be sorted at all	*/
					th.setAttribute( 'aria-sort', sorted === false ? 'none' : ( sortDir < 0 ? 'descending' : 'ascending' ) );
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

				// .nino-admin-hidden, not /_admin's own .admin-hidden: this
				// component is shared, and that class is defined in
				// _admin/assets/style.css only - in the localized panels every toggle
				// below was a no-op, leaving the empty hint and the pager on
				// screen for an empty table
				scroller.classList.toggle( 'nino-admin-hidden', view.total === 0 );
				// The toolbar stays put when a search emptied the table -
				// hiding it would trap the user with no way to clear the box
				toolbar.classList.toggle( 'nino-admin-hidden', rows.length === 0 && query === '' );
				empty.classList.toggle( 'nino-admin-hidden', view.total !== 0 );
				pager.classList.toggle( 'nino-admin-hidden', view.total === 0 );
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
		 *	which every layout rule in _admin/assets/style.css hangs off) and
		 *	the "which panel is open" state the shell script switches on.
		 *	Assigning className outright is the obvious way to do the second
		 *	and silently destroys the first, so both shells - the workbench's
		 *	and the setup wizard's - go through here instead.
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

		/**
		 *	Fill a message's placeholders - %s, %d and %n - from the params, in
		 *	the order they stand. A function replaces them rather than a string:
		 *	String.replace() reads '$&' and '$$' in a replacement string, and
		 *	the params are what a person typed (an uri, a key, a file name), so
		 *	"a$&b" went in and "a%sb" came out. A placeholder with no param left
		 *	stays as it is
		 *
		 *	@param		{string}	text					The message, eg. 'Saved at %s.'
		 *	@param		{...*}		params				What goes into it
		 *
		 *	@return		{string}
		 */
		format : function( text, ...params ) {

			let at = 0;

			// A number with a fraction is written the way the interface language
			// writes it: 0,5 MB in German
			const words = function( value ) {

				const plain = String( value );
				if( typeof value !== 'number' || Number.isInteger( value ) === true || typeof Nino.content !== 'object' || Nino.content === null )
					return plain;

				const mark = Nino.content.getText('/_admin/common/unit/decimal') || '.';
				return plain.replace( '.', function() { return mark } );
			};

			return String( text ?? '' ).replace( /%[sdn]/g, function( token ) {
				return at < params.length ? words( params[at++] ) : token;
			} );
		},

		/**
		 *	Say a failed request in a container: what errorText() makes of it, in
		 *	the design system's error text, in place of whatever stood there -
		 *	the load that failed, where there is nothing else to show
		 *
		 *	@param		{Element}		container
		 *	@param		{number}		status					Xhr status code
		 *	@param		{*}					response				Parsed response body, if any
		 *	@param		{string}		[fallbackKey]		The panel's own sentence for this failure
		 *
		 *	@return		{Element}										The paragraph written
		 */
		showError : function( container, status, response, fallbackKey ) {

			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = Nino.adminUi.api.errorText( status, response, fallbackKey );
			container.appendChild( p );
			return p;
		},

		/**
		 *	The limits an image upload runs into, as the shell wrote them onto
		 *	its wrapper (see \Nino\Admin\Admin::handleGet()) - 0 for one it did
		 *	not name, which is no check
		 *
		 *	@return		{Object}									{ bytes, pixels }
		 */
		limits : function() {

			const wrap = typeof dc.getElementById === 'function' ? dc.getElementById('admin-page-wrap') : null;
			const read = function( name ) {
				const value = wrap && wrap.dataset ? parseInt( wrap.dataset[name], 10 ) : NaN;
				return isNaN( value ) === true || value < 1 ? 0 : value;
			};

			return { bytes : read('uploadBytes'), pixels : read('uploadPixels') };
		},

		/**
		 *	What an upload control tells the person before they choose a file:
		 *	the size and the pixels the server will take. Permanent text rather
		 *	than an error to be earned - nobody should have to fail an upload to
		 *	learn the limit
		 *
		 *	@return		{Element|null}						null where the limits are unknown
		 */
		uploadHint : function() {

			const limits = Nino.adminUi.limits();
			if( limits.bytes === 0 || limits.pixels === 0 )
				return null;

			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/common/hint/upload'), Nino.adminUi.megabytes( limits.bytes ), limits.pixels / 1000000 );
			return hint;
		},

		/**
		 *	A size in bytes as the megabytes a sentence names: whole where it
		 *	is whole, one decimal where it is not - the same rounding
		 *	\Nino\Admin\Admin::megabytes() applies on the server
		 *
		 *	@param		{number}	bytes
		 *
		 *	@return		{number}
		 */
		megabytes : function( bytes ) {
			return Math.round( bytes / 104857.6 ) / 10;
		},

		/**
		 *	Check a chosen image against the server's limits before it is sent,
		 *	so a file that cannot work fails at once, in words, instead of after
		 *	an upload of however many megabytes. The server stays the authority
		 *	- this only spares the wait: what it cannot judge (a format the
		 *	browser cannot decode, a limit it was not told) it lets through.
		 *
		 *	The answer has the shape of a failed request's body, so errorText()
		 *	says it exactly as it would say the server's
		 *
		 *	@param		{File}				file
		 *	@param		{Function}		done						Called once with null, or { code, params }
		 *
		 *	@return		void
		 */
		checkImage : function( file, done ) {

			const limits = Nino.adminUi.limits();

			if( limits.bytes > 0 && file.size > limits.bytes ) {
				done( { code : 'image_too_large', params : [ Nino.adminUi.megabytes( limits.bytes ) ] } );
				return;
			}

			if( limits.pixels === 0 || typeof wn.createImageBitmap !== 'function' ) {
				done( null );
				return;
			}

			// The pixels need the picture decoded, which is as much work as
			// the server will do - and the browser answers for a format it
			// cannot read with a rejection that says nothing about the file
			wn.createImageBitmap( file ).then( function( bitmap ) {
				const tooMany = bitmap.width * bitmap.height > limits.pixels;
				if( typeof bitmap.close === 'function' )
					bitmap.close();
				done( tooMany === true ? { code : 'image_too_many_pixels', params : [ limits.pixels / 1000000 ] } : null );
			}, function() {
				done( null );
			} );
		},

		/**
		 *	The state a status line is in, as plain data: which state, and the
		 *	fill key and params of its words. The pure half of status(), so
		 *	the states can be tested without a dom. An error has no key of its
		 *	own - what failed is errorText()'s to say
		 *
		 *	@param		{string}	state					idle, dirty, saving, saved or error
		 *	@param		{Date}		[date]				When it was saved, for 'saved'
		 *
		 *	@return		{Object}									{ state, key, params }
		 */
		statusModel : function( state, date ) {

			const pad = function( number ) { return String( number ).padStart( 2, '0' ) };

			switch( state ) {
				case 'saving':
					return { state : 'saving', key : '/_admin/common/msg/saving', params : [] };
				case 'dirty':
					return { state : 'dirty', key : '/_admin/common/msg/dirty', params : [] };
				case 'saved': {
					const at = date !== null && typeof date === 'object' && typeof date.getHours === 'function' ? date : new Date();
					return { state : 'saved', key : '/_admin/common/msg/savedat', params : [ pad( at.getHours() )+ ':'+ pad( at.getMinutes() ) ] };
				}
				case 'error':
					return { state : 'error', key : '', params : [] };
				default:
					return { state : 'idle', key : '', params : [] };
			}
		},

		/**
		 *	A panel's status line: one element that says whether what is on
		 *	screen is saved - "saving", "saved at hh:mm", "unsaved changes", or
		 *	why it failed - instead of each panel keeping its own "Saved."
		 *	that stayed on screen long after the next keystroke made it a lie.
		 *
		 *	The state is on the element as data-state, for the stylesheet,
		 *	which gives each one a prefix of its own so the meaning does not
		 *	rest on a colour. It is announced as a status, and an error as an
		 *	alert. Owns no strings: its words are the /_admin/common/msg/*
		 *	fills, and a caller that needs others passes them in labels
		 *
		 *	@param		{Element}		el							The line itself - the panel's own, usually in its action bar
		 *	@param		{Object}		[labels]				Text for a state instead of the fill: { saving, saved, dirty }, 'saved' with a %s for the time
		 *
		 *	@return		{Object}										{ element, state, idle( [text] ), saving(), saved( date ), dirty( [text] ), fail( text ), error( status, response, fallbackKey ), bind( form, isDirty ) }
		 */
		status : function( el, labels ) {

			labels = labels || {};

			el.classList.add('nino-admin-status');

			// Set before the first words, so that screen readers have the live
			// region when they arrive
			el.setAttribute( 'role', 'status' );
			el.setAttribute( 'aria-live', 'polite' );

			let scope = null;
			let marked = null;
			let typed = false;

			const unmark = function() {
				if( marked !== null )
					marked.removeAttribute('aria-invalid');
				marked = null;
			};

			const show = function( model, text ) {

				// A refused field is let go with the state it was refused in
				if( model.state !== 'error' )
					unmark();

				const words = typeof labels[model.state] === 'string' ? labels[model.state] : ( model.key === '' ? '' : Nino.content.getText( model.key ) );

				el.dataset.state = model.state;
				el.setAttribute( 'role', model.state === 'error' ? 'alert' : 'status' );
				el.setAttribute( 'aria-live', model.state === 'error' ? 'assertive' : 'polite' );
				el.textContent = typeof text === 'string' ? text : Nino.adminUi.format( words, ...model.params );
				control.state = model.state;
			};

			const control = {

				element : el,
				state		: 'idle',

				idle	 : function( text ) { show( Nino.adminUi.statusModel('idle'), text ) },
				saving : function() {
					typed = false;
					show( Nino.adminUi.statusModel('saving') );
				},

				/**
				 *	Saved - unless something was typed while the save was on its
				 *	way: that is not in what was sent, and the line says so
				 */
				saved	 : function( date ) {
					if( typed === true ) {
						typed = false;
						control.dirty();
						return;
					}
					show( Nino.adminUi.statusModel( 'saved', date ) );
				},

				/**
				 *	Unsaved changes - with the panel's own words where it has
				 *	more to say than that (a copy that has not been named yet)
				 */
				dirty	 : function( text ) { show( Nino.adminUi.statusModel('dirty'), text ) },

				/**
				 *	A refusal the panel made itself, before anything was sent:
				 *	the error state with the panel's own words
				 */
				fail	 : function( text ) {
					unmark();
					show( Nino.adminUi.statusModel('error'), text );
				},

				/**
				 *	A failed request: what errorText() says of it, and - when the
				 *	server named the field - that field marked aria-invalid,
				 *	focused, and let go again by the next thing typed into it
				 */
				error : function( status, response, fallbackKey ) {

					unmark();
					show( Nino.adminUi.statusModel('error'), Nino.adminUi.api.errorText( status, response, fallbackKey ) );

					const name = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';
					const root = scope !== null ? scope : ( typeof el.closest === 'function' ? el.closest('form, [data-panel]') : null );
					if( name === '' || root === null || /^[A-Za-z0-9_.\/-]+$/.test( name ) === false )
						return;

					const field = root.querySelector( '[data-field="'+ name+ '"], [name="'+ name+ '"]' );
					if( field === null )
						return;

					field.setAttribute( 'aria-invalid', 'true' );
					marked = field;
					if( typeof field.focus === 'function' )
						field.focus();
					field.addEventListener( 'input', unmark, { once : true } );
				},

				/**
				 *	Follow a form: what is typed into it turns a saved (or fresh)
				 *	line into "unsaved changes" - except for a file input, whose
				 *	file is saved by its own upload. isDirty() is the panel's own
				 *	word on it - Elements and Text already know which languages
				 *	they hold changes in (_dirtyLocales) - and is asked after
				 *	every change, so a value typed back to what it was can turn
				 *	the line back. Nothing here keeps a second copy of that state
				 */
				bind : function( form, isDirty ) {

					scope = form;

					const follow = function( ev ) {

						// A picked file is not a change to the record: it is saved on
						// its own, by the upload. A search box is a way to find
						// something, not a value
						if( ev && ev.target && ( ev.target.type === 'file' || ev.target.type === 'search' ) )
							return;

						// The line says "saving" until the answer; what is typed meanwhile
						// is remembered for saved()
						if( control.state === 'saving' ) {
							typed = true;
							return;
						}

						if( typeof isDirty === 'function' && isDirty() === false ) {
							if( control.state === 'dirty' )
								control.idle();
							return;
						}

						// A refusal stays until the next save: it names what to fix, and
						// the first keystroke into the form is usually that fix
						if( control.state === 'saved' || control.state === 'idle' )
							control.dirty();
					};

					form.addEventListener( 'input', follow );
					form.addEventListener( 'change', follow );
				},
			};

			return control;
		},

		/**
		 *	The one way a panel talks to the server. Every panel posts one
		 *	action with a json payload to the workbench's single endpoint and
		 *	gets ( status, body ) back, which used to be a copy of the same
		 *	dozen lines in every panel script - and a copy that could not know
		 *	the page had outlived its session.
		 *
		 *	Names no Nino.admin member and touches nothing at load: the setup
		 *	wizard loads this file without the workbench shell, and the panels
		 *	of a project or a feature may be older than the shell that runs
		 *	them, so Nino.http, Nino.dir and Nino.content are looked up when
		 *	a request is made
		 */
		api : {

			// The shell's answer to a session that is gone, if it registered
			// one (see onSessionLost()), and the requests waiting on the
			// check that asks whose session this is now. null while no check
			// is open - a request sent then waits with the others
			_handler	: null,
			_queue		: null,

			/**
			 *	Where the workbench answers: the project's directory plus the
			 *	endpoint with its trailing slash, the way every panel posted it
			 *	(a redirect from the slashless form can end up on the wrong
			 *	scheme behind a reverse proxy, which browsers block for XHR)
			 *
			 *	@return		{string}
			 */
			_uri : function() {
				return ( typeof Nino.dir === 'string' ? Nino.dir : '' )+ '/_admin/';
			},

			/**
			 *	Whether an answer is the end of the session rather than a
			 *	refusal: a 401 with the code 'session' (nobody is logged in) or
			 *	a 403 with the code 'csrf' (the page's token is not this
			 *	session's). Other 401s and 403s are the panel's to read - the
			 *	Users panel answers a 401 for a wrong password
			 *
			 *	@param		{Object}		xhr
			 *
			 *	@return		{boolean}
			 */
			_sessionLost : function( xhr ) {

				const body = xhr.responseJSON;
				if( body === null || typeof body !== 'object' )
					return false;

				return ( xhr.status === 401 && body.code === 'session' ) || ( xhr.status === 403 && body.code === 'csrf' );
			},

			/**
			 *	Whether the request never got an answer: a failed connection
			 *	arrives as a 500 (see Nino.http.sendRequest()), the same
			 *	number a server's own error has, and the only difference is that
			 *	a server's answer has headers. A caller's xhr without the method
			 *	(a test's) is taken to be an answer
			 *
			 *	@param		{Object}		xhr
			 *
			 *	@return		{boolean}
			 */
			_offline : function( xhr ) {
				return typeof xhr.getAllResponseHeaders === 'function' && xhr.getAllResponseHeaders() === '';
			},

			/**
			 *	Hand a request's answer to its caller, synchronously and with
			 *	what the panels have always been given: ( status, body ). A
			 *	request that never reached the server is given the code
			 *	'offline' for a body, which errorText() has a sentence for
			 *
			 *	@param		{Object}		request
			 *	@param		{Object}		xhr
			 *
			 *	@return		void
			 */
			_finish : function( request, xhr ) {
				request.callback( xhr.status, Nino.adminUi.api._offline( xhr ) === true ? { code : 'offline' } : xhr.responseJSON );
			},

			/**
			 *	Post one action
			 *
			 *	@param		{string}		action				Eg. 'users/save'
			 *	@param		{Object}		[payload]			Sent json-encoded as 'data'
			 *	@param		{Function}	callback			Called with ( status, body ), the body parsed
			 *	@param		{Object}		[extra]				Extra multipart fields (eg. { file : File })
			 *
			 *	@return		void
			 */
			call : function( action, payload, callback, extra ) {
				Nino.adminUi.api._send( { action : action, payload : payload, callback : callback, extra : extra, retried : false, xhr : null } );
			},

			/**
			 *	Send a request - or, while a session check is open, let it wait
			 *	with the others
			 *
			 *	@param		{Object}		request
			 *
			 *	@return		void
			 */
			_send : function( request ) {

				const api = Nino.adminUi.api;

				if( api._queue !== null ) {
					api._queue.push( request );
					return;
				}

				Nino.http.sendRequest( api._uri(), 'POST', function( xhr ) {

					// Anything but the end of the session goes straight back, as
					// does a second one for a request already sent again once: that
					// session is not coming back by being asked again
					if( api._handler === null || request.retried === true || api._sessionLost( xhr ) === false ) {
						api._finish( request, xhr );
						return;
					}

					// Another request found the session gone first: this one waits
					// with it for the one check
					request.xhr = xhr;
					if( api._queue !== null ) {
						api._queue.push( request );
						return;
					}

					api._queue = [ request ];
					api._check();
				}, Object.assign( { action : request.action, data : JSON.stringify( request.payload || {} ) }, request.extra || {} ) );
			},

			/**
			 *	Ask the workbench whose session this is now, and what its token
			 *	is - the answer decides what the waiting requests do: with the
			 *	same account they are sent again, with a session that is gone the
			 *	handler asks for a login, with another account they are never sent
			 *	(a form filled in for one account must not be saved by another).
			 *	The token goes into the csrf fields of the page only where the
			 *	page can use it: for a session that ended (the login that follows
			 *	needs the anonymous session's) and for the same account (the
			 *	requests are sent again). With another account the page keeps
			 *	its own, dead token - a request that does not go through this api
			 *	(a feature's panel, still on its own) would otherwise be saved
			 *	by the account that was not the one the form was filled in for
			 *
			 *	@param		{Function}	[done]				Called once it is decided, with 'resumed' (the
			 *																		requests were sent again), 'expired', 'other'
			 *																		or 'failed' and the status of the check
			 *
			 *	@return		void
			 */
			_check : function( done ) {

				const api = Nino.adminUi.api;

				const finish = function( outcome, status ) {
					if( typeof done === 'function' )
						done( outcome, status );
				};

				// The question is asked even when nothing waits any more (a login
				// that was answered after the requests it was for were given up):
				// the login rotated the token, and the page has to learn the new one
				Nino.http.sendRequest( api._uri()+ '?session=1', 'GET', function( xhr ) {

					const info = xhr.status === 200 && xhr.responseJSON !== null && typeof xhr.responseJSON === 'object' ? xhr.responseJSON : null;

					// No answer to the question: the requests that were sent get the
					// answers they had, which is all there is to tell, and the ones
					// that only waited are sent after all
					if( info === null || typeof info.user !== 'string' || typeof info.csrf !== 'string' ) {
						api._release( false );
						finish( 'failed', xhr.status );
						return;
					}

					if( info.user === '' || info.user === api._account() )
						dc.querySelectorAll('input[name="_csrf"]').forEach( function( input ) { input.value = info.csrf } );

					if( info.user === '' || info.user !== api._account() ) {
						const reason = info.user === '' ? 'expired' : 'other';
						api._handler( api._check, reason );
						finish( reason, xhr.status );
						return;
					}

					const waiting = api._queue === null ? [] : api._queue;
					api._queue = null;
					waiting.forEach( function( request ) {
						request.retried = true;
						api._send( request );
					} );
					finish( 'resumed', xhr.status );
				} );
			},

			/**
			 *	Let the waiting requests go without a session: each one gets the
			 *	answer it already had, and one that only waited is sent as it
			 *	is. The panels hear of the failure and take their forms back
			 *
			 *	@param		{boolean}		final					true when the person gave up on a login: a request that
			 *																		only waited is sent once and told what comes back,
			 *																		not held for another check (and another dialog)
			 *
			 *	@return		void
			 */
			_release : function( final ) {

				const api = Nino.adminUi.api;
				const waiting = api._queue === null ? [] : api._queue;
				api._queue = null;

				waiting.forEach( function( request ) {
					if( request.xhr !== null ) {
						api._finish( request, request.xhr );
						return;
					}
					request.retried = final;
					api._send( request );
				} );
			},

			/**
			 *	Whether requests wait for the session to come back - the dialog
			 *	holds the page until they are decided
			 *
			 *	@return		{boolean}
			 */
			waiting : function() {
				return Nino.adminUi.api._queue !== null;
			},

			/**
			 *	The person closed the dialog without logging in: the requests
			 *	that waited get the answer they already had (see _release()), so
			 *	that the panels show their error and let the input be copied out.
			 *	Nothing to do when no request waits
			 *
			 *	@return		void
			 */
			dismiss : function() {
				Nino.adminUi.api._release( true );
			},

			/**
			 *	The account this page was rendered for, as the shell wrote it
			 *	into its rail
			 *
			 *	@return		{string}
			 */
			_account : function() {

				const el = typeof dc.getElementById === 'function' ? dc.getElementById('admin-user-email') : null;

				return el !== null && typeof el.textContent === 'string' ? el.textContent.trim() : '';
			},

			/**
			 *	Register what happens when the session is gone and a login can
			 *	bring it back - the shell's dialog. Called with ( check, reason ):
			 *	check( done ) asks again whose session this is (call it after a
			 *	login) and says what became of the waiting requests through
			 *	done( outcome ), reason is 'expired' for a session that ended or 'other' for one
			 *	that now belongs to another account, where the page can only be
			 *	reloaded. Without a handler (the wizard, a test) a session
			 *	failure is handed back like any other answer
			 *
			 *	@param		{Function}	handler
			 *
			 *	@return		void
			 */
			onSessionLost : function( handler ) {
				Nino.adminUi.api._handler = typeof handler === 'function' ? handler : null;
			},

			/**
			 *	What to tell a person about a failed request. The server's code
			 *	says it in the interface language ('/_admin/error/<code>', with
			 *	the params in it); failing that the server's own message, which
			 *	is content more often than it is a log line - a veto a project's
			 *	callback wrote, the reason a feature cannot be activated - and
			 *	failing that the panel's own sentence for what it was doing
			 *	(fallbackKey, a fill key or text). The status number is in the
			 *	last two only: with a sentence that says what happened, the
			 *	number is noise, and without one it is what somebody reports. A
			 *	request that never reached the server is told as that
			 *
			 *	@param		{number}		status
			 *	@param		{*}					response					Parsed response body, if any
			 *	@param		{string}		[fallbackKey]			Fill key or text for what the panel was doing
			 *
			 *	@return		{string}
			 */
			errorText : function( status, response, fallbackKey ) {

				const body = response !== null && typeof response === 'object' ? response : {};

				if( body.code === 'offline' )
					return Nino.content.getText('/_admin/common/error/offline');

				if( typeof body.code === 'string' && /^[a-z][a-z0-9_]*$/.test( body.code ) === true ) {
					const known = Nino.content.getText( '/_admin/error/'+ body.code );
					if( known !== '' )
						return Nino.adminUi.format( known, ...( Array.isArray( body.params ) === true ? body.params : [] ) );
				}

				const own = typeof fallbackKey === 'string' && fallbackKey !== '' ? Nino.adminUi.text( fallbackKey ) : '';

				return '('+ status+ ') '+ ( ( typeof body.error === 'string' ? body.error : '' ) || own || Nino.content.getText('/_admin/common/error/request') );
			},
		},
	};

})(window, document, document.documentElement, document.body);
