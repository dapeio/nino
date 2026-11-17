/**
 *	Nino										A compact filesystembased php framework
 *	Nino.admin.js						The shared behaviour half of the management design system:
 *													small DOM primitives the tool frontends build their
 *													chrome from, the pure table model behind the list
 *													views, the lifecycle of a panel, and the one way a
 *													panel talks to the server: Nino.adminUi.api, with the
 *													status line and the messages that say what failed.
 *													style.css is the vocabulary, this is what assembles it.
 *
 *													Separate from Nino.js for the reason of who needs it. Nino.js is in the public site's script
 *													bundle, so every visitor was being served the complete
 *													table renderer, the field builders and the switch - none
 *													of which a page has any use for. Two files, two
 *													audiences: Nino.js everywhere, this one in the tool
 *													frontends.
 *
 *													Loaded after Nino.js, which owns the namespace this
 *													extends - on its own it has nothing to attach to.
 *													Names no Nino.admin member at load, which is what lets
 *													the setup wizard load it without the workbench shell
 *													in assets/script.js: panel() and levels() look the
 *													shell's dirty registry and router up when a panel asks
 *													for them, feature-detected, as api looks Nino.http up
 *													when a request is made, and work without them.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	if( typeof wn.Nino === 'undefined' )
		return;

	/**
	 *	The workbench shell's namespace, looked up when a panel asks for it and
	 *	never at load: this file runs on the setup wizard's page as well, where
	 *	script.js - and with it Nino.admin - does not exist, and a panel of a
	 *	project or a feature may be older than the shell that runs it. Whoever
	 *	asks feature-detects what it wants of it (the dirty registry, the router)
	 *
	 *	@return		{Object}									Nino.admin, or an empty object where there is none
	 */
	const shell = function() {
		return typeof Nino.admin === 'object' && Nino.admin !== null ? Nino.admin : {};
	};

	/**
	 *	Small DOM primitives shared by Nino's administration surfaces.
	 *	They only assign the common chrome classes from style.css;
	 *	application modules keep ownership of labels, state and events.
	 */
	// The counter behind the ids the building blocks make when they are given none
	let serial = 0;

	// How long the line of a saveBar() holds the sentence "unsaved changes" before it goes: the badge says the state from then on
	const SAID_FOR = 4000;

	wn.Nino.adminUi = {

		// How many search hits elementList() draws at once. The search itself
		// always runs over the whole catalogue - this caps the rows, so a type
		// with thousands of elements stays a panel rather than a page, and the
		// control says how many more it found
		ELEMENTLIST_RESULTS : 50,

		// How long color() waits for the typing to rest before it tells a screen reader the
		// contrast line, in milliseconds: a code typed digit by digit passes through valid ones
		// that a live region would otherwise read out one by one
		COLOR_QUIET : 400,


		/**
		 *	The pinned row at the top of a drill-down level: the back link, then
		 *	the controls that belong to the level (a locale select, a button).
		 *	Its class is .nino-admin-contextbar
		 *
		 *	@param		{Element}				[backLink]			The link out of the level
		 *	@param		{Element|Array}	[controls]			One element or a list of them
		 *
		 *	@return		{Element}											The row
		 */
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

		/**
		 *	Show one level of a drill-down and move the focus into it. The levels
		 *	of a pane are elements whose ids start alike (<prefix>-list,
		 *	<prefix>-form - see levels()), and the pane shows one at a time: the
		 *	level asked for loses the shell's admin-hidden class and its siblings,
		 *	the other children of its parent with an id that begins the same, gain
		 *	it. What every panel left out was the focus. The control that opened a
		 *	level is hidden with the level it stood in, the focus falls to the
		 *	body, and the next Tab starts again at the top of the page
		 *
		 *	The focus goes to options.focus where that is an element; else to the
		 *	first heading of the level, and where the level has none to its back
		 *	link, and where it has none either to the name of the panel. A target
		 *	that is no control gets tabindex="-1" - it can take the focus and is no
		 *	stop of the Tab key.
		 *
		 *	false is for a level that is shown again, or drawn when its answer
		 *	arrives - not one the person opened: the focus stays where it is.
		 *	Unless it stood in a level that is hidden now. Nothing keeps it
		 *	there, and it goes to the name of the panel, where the person started.
		 *	Owns no strings
		 *
		 *	@param		{Element|string}	level				The level, or its id
		 *	@param		{Object}					[options]		{ focus } - an element to focus instead of the heading, or false to keep the focus where it is
		 *
		 *	@return		{Element|null}									The element that took the focus, null where it stayed
		 */
		showLevel : function( level, options ) {

			options = options || {};

			if( typeof level === 'string' )
				level = dc.getElementById( level );

			if( level === null || level === undefined )
				return null;

			const others	= [];
			const stem		= level.id.lastIndexOf('-') > 0 ? level.id.slice( 0, level.id.lastIndexOf('-')+ 1 ) : '';

			if( stem !== '' && level.parentNode !== null && level.parentNode !== undefined )
				Array.from( level.parentNode.children ).forEach( function( other ) {
					if( other !== level && other.id.indexOf( stem ) === 0 )
						others.push( other );
				} );

			// Asked before the siblings are hidden: a browser takes the focus
			// from a hidden element a moment later, and by then nothing says where it was
			const held	= dc.activeElement ?? null;
			const lost	= held !== null && others.some( function( other ) {
				for( let node = held; node !== null && node !== undefined; node = node.parentNode )
					if( node === other )
						return true;
				return false;
			} );

			others.forEach( function( other ) { other.classList.add('admin-hidden') } );
			level.classList.remove('admin-hidden');

			if( options.focus === false && lost === false )
				return null;

			const pane		= level.closest('[data-panel]');
			const title		= pane === null ? null : pane.querySelector('.admin-panel-title');
			let target		= typeof options.focus === 'object' ? options.focus : null;

			if( target === null && options.focus === false )
				target = title;
			else if( target === null )
				target = level.querySelector('h1, h2, h3, h4, h5, h6, [role="heading"]') ?? level.querySelector('.nino-admin-back-link') ?? title;

			if( target === null || typeof target.focus !== 'function' )
				return null;

			if( target.hasAttribute('tabindex') === false && /^(A|BUTTON|INPUT|SELECT|TEXTAREA|SUMMARY)$/.test( target.tagName ) === false )
				target.setAttribute( 'tabindex', '-1' );

			target.focus();

			return target;
		},

		/**
		 *	Back to the level a drill-down came from, and the focus to the control
		 *	that opened the one that is left - the row or the button - where that
		 *	is still in the page; else to what showLevel() picks. A list that is
		 *	drawn again while the form is open has a new row for it, which is the
		 *	one to pass
		 *
		 *	@param		{Element|string}	level				The level to go back to, or its id
		 *	@param		{Element}					[opener]		The control that opened the level that is left
		 *
		 *	@return		{Element|null}									The element that took the focus
		 */
		backTo : function( level, opener ) {
			return Nino.adminUi.showLevel( level, { focus : opener !== null && opener !== undefined && opener.isConnected === true ? opener : true } );
		},

		/**
		 *	Turn an element into the fixed bar at the bottom of a screen, where
		 *	its Save and its status line stand: it gets the class
		 *	.nino-admin-actionbar and nothing else is touched. The caller puts
		 *	the buttons and the status line in
		 *
		 *	@param		{Element}		bar						The element that becomes the bar, usually a div
		 *
		 *	@return		{Element}										The same element
		 */
		actionBar : function( bar ) {
			bar.classList.add('nino-admin-actionbar');
			return bar;
		},

		/**
		 *	The fixed bar above a list that holds the buttons for the list as
		 *	a whole - "New", an import (.nino-admin-actionbar
		 *	.nino-admin-list-actions)
		 *
		 *	@param		{Array}			buttons				The buttons, in the order they stand
		 *
		 *	@return		{Element}										The bar, the buttons in it
		 */
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
		 *	Where the head already holds the registry's strip and the screen
		 *	is one of its tabs, tabs() leaves that strip alone and puts the
		 *	panel's own at the top of the tab's pane.
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
					/*	The head already holds the registry's strip - buttons the
						shell wires through data-tab - and the screen asking is one
						of its tabs: a strip of its own goes to the top of that
						tab's pane instead, and replaces the one it drew there
						before. Replacing the head's would take the tabs away
						(the Settings tab a feature's panel gets is one of them)	*/
					let tabPane = el;
					while( tabPane && tabPane.parentNode !== pane )
						tabPane = tabPane.parentNode;
					if( tabPane && tabPane.dataset && tabPane.dataset.tab !== undefined && head.querySelector(':scope > .admin-panel-tabs > button[data-tab]') !== null ) {
						const own = tabPane.querySelector(':scope > .admin-panel-tabs');
						if( own !== null && own !== strip )
							own.remove();
						strip.classList.add('admin-panel-tabs');
						tabPane.insertBefore( strip, tabPane.firstChild );
						return strip;
					}
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

		/**
		 *	The one way a screen says "this needs your attention": a failing
		 *	mail, texts without a translation, a site that shows its
		 *	maintenance page. A line of text that stays until what it names is
		 *	fixed, with a link to where that is done where there is one. Owns
		 *	no strings - the caller says what and where, in the interface
		 *	language; the text is set as text, never as markup
		 *
		 *	@param		{string}		message
		 *	@param		{Object}		[link]					{ href, label } - drawn only for an href that is a #panel of the workbench
		 *
		 *	@return		{Element}
		 */
		notice : function( message, link ) {

			const notice = dc.createElement('p');
			notice.className = 'nino-admin-notice';
			notice.setAttribute( 'role', 'status' );
			notice.textContent = message;

			if( typeof link === 'object' && link !== null && /^#[a-z][a-z0-9\/_-]*$/.test( String( link.href ) ) === true ) {
				const a = dc.createElement('a');
				a.href = link.href;
				a.textContent = String( link.label ?? '' );
				notice.appendChild( dc.createTextNode(' ') );
				notice.appendChild( a );
			}

			return notice;
		},

		/**
		 *	One line of explanation: a paragraph in the hint's quiet type, and in
		 *	the error colour as well for a hint that is a refusal. The words are
		 *	used as they are - a caller that has a fill key runs it through text()
		 *	itself. A line that is announced when it changes is status() or
		 *	notice(), which exist. Owns no strings
		 *
		 *	@param		{string}		text
		 *	@param		{string}		[kind]					'error' for the error colour too
		 *
		 *	@return		{Element}										The paragraph
		 */
		hint : function( text, kind ) {

			const hint = dc.createElement('p');
			hint.className = kind === 'error' ? 'nino-admin-hint nino-admin-error' : 'nino-admin-hint';
			hint.textContent = text;
			return hint;
		},

		/**
		 *	Say at a field what is wrong with it: a paragraph in the field error
		 *	type after the field, the control marked aria-invalid and pointed at
		 *	the paragraph with aria-describedby - so the sentence is read where
		 *	the person stands and not only seen. It goes after a label, not into
		 *	it: inside, it would be part of the control's name as well. The
		 *	sentences are not alerts - a form with six empty fields would announce
		 *	six at once - the form's status line says the one summary
		 *	(status().fail()) and the focus goes to the first field, which reads
		 *	its own. Owns no strings
		 *
		 *	@param		{Element}				wrapper			What the field stands in - its label, or the block around the control
		 *	@param		{Element|null}	control			The control that is invalid; null where the caller marks it itself (an editor)
		 *	@param		{string}				id					The paragraph's id, which is what the control's aria-describedby names
		 *	@param		{string}				text				The sentence
		 *
		 *	@return		{Element}											The paragraph
		 */
		fieldError : function( wrapper, control, id, text ) {

			const error = dc.createElement('p');
			error.id = id;
			error.className = 'nino-admin-field-error';
			error.textContent = text;

			if( wrapper.tagName.toLowerCase() === 'label' )
				wrapper.after( error );
			else
				wrapper.appendChild( error );

			if( control !== null ) {
				control.setAttribute( 'aria-invalid', 'true' );
				control.setAttribute( 'aria-describedby', id );
			}

			return error;
		},

		/**
		 *	Take what fieldError() put on a form off again: the sentences go, and
		 *	so do aria-invalid and the aria-describedby that goes with it
		 *
		 *	@param		{Element}		root				The form, or whatever holds its fields
		 *
		 *	@return		void
		 */
		clearFieldErrors : function( root ) {

			root.querySelectorAll('.nino-admin-field-error').forEach( function( el ) { el.remove() } );
			root.querySelectorAll('[aria-invalid]').forEach( function( el ) {
				el.removeAttribute('aria-invalid');
				el.removeAttribute('aria-describedby');
			} );
		},

		// Whether choiceDialog() has a question open - one at a time: a second
		// call while it stands is the same click arriving twice
		_choiceOpen : false,

		/**
		 *	A modal question with more than two answers (a confirm() has two):
		 *	a native <dialog> in the workbench root, opened with showModal(),
		 *	so the page behind keeps everything that was typed into it. Escape
		 *	is the secondary choice, the one that does nothing; focus goes back
		 *	to what had it when the question was asked. The focus opens on the
		 *	first button, unless the question has a 'danger' answer and no
		 *	'primary' one: then it opens on the secondary answer.
		 *
		 *	Owns no strings and names no Nino.admin member: the caller says
		 *	what to ask and what the answers are called, in the interface
		 *	language. Where the browser has no showModal() the question is
		 *	asked with confirm() - OK picks the 'primary' choice (the 'danger'
		 *	one where there is none), Cancel the 'secondary' one: nothing is
		 *	thrown away by agreeing to the question as it is worded
		 *
		 *	A `note` is a second paragraph under the message - what the caller
		 *	has to add to the question without making it another one (see
		 *	confirm()); a browser without <dialog> is asked the two with a blank
		 *	line between them
		 *
		 *	The same dialog holds a text that is read rather than answered, a
		 *	manual (see manualDialog()): `content` is drawn in place of the
		 *	message, and the dialog keeps its title and its answers in view while
		 *	what is between them scrolls
		 *
		 *	@param		{Object}		options
		 *	@param		{string}		[options.title]
		 *	@param		{string}		options.message				The question - what a browser without <dialog> is asked, with a `content`
		 *	@param		{Element}		[options.content]			Drawn in place of the message
		 *	@param		{string}		[options.className]		Added to the dialog's own class
		 *	@param		{string}		[options.note]				A second paragraph under the message
		 *	@param		{Element}		[options.opener]			What gets the focus back when the dialog closes, where it is not what had it when the dialog opened: a button that was clicked has the focus in Chromium and Firefox and not in Safari
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
			const hasNote = typeof options.note === 'string' && options.note !== '' && ( options.content === undefined || options.content === null );

			if( typeof dialog.showModal !== 'function' ) {
				const yes = choices.find( function( choice ) { return choice.kind === 'primary' } ) ?? choices.find( function( choice ) { return choice.kind === 'danger' } ) ?? cancel;
				options.onChoose( wn.confirm( hasNote === true ? options.message+ '\n\n'+ options.note : options.message ) === true ? yes.value : cancel.value );
				return true;
			}

			Nino.adminUi._choiceOpen = true;

			const opener = options.opener ?? dc.activeElement;
			const hasContent = options.content !== undefined && options.content !== null;
			let chosen = null;

			dialog.className = 'nino-admin-dialog'+ ( typeof options.className === 'string' && options.className !== '' ? ' '+ options.className : '' );

			// A message is what the dialog is about and is read out with it; a
			// manual is as long as it is and is read when it is looked at
			if( hasContent === false )
				dialog.setAttribute( 'aria-describedby', hasNote === true ? 'nino-admin-choice-text nino-admin-choice-note' : 'nino-admin-choice-text' );

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

			if( hasContent === true ) {
				const scroll = dc.createElement('div');
				scroll.className = 'nino-admin-dialog-content';
				scroll.appendChild( options.content );
				body.appendChild( scroll );
			}
			else {
				const text = dc.createElement('p');
				text.id = 'nino-admin-choice-text';
				text.textContent = options.message;
				body.appendChild( text );

				if( hasNote === true ) {
					const note = dc.createElement('p');
					note.id = 'nino-admin-choice-note';
					note.textContent = options.note;
					body.appendChild( note );
				}
			}

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-dialog-actions';

			// A question whose way on is a 'danger' one and has no 'primary' one - the
			// one that destroys what is there, with nothing safe to press at its
			// side - opens with the focus on the answer that does nothing: Enter
			// on a dialog that has just appeared must not be the destroying
			// answer. Any other question keeps what showModal() does, the first
			// button; that is also where a Save stands beside a Discard
			const cautious = choices.some( function( choice ) { return choice.kind === 'danger' } ) === true && choices.some( function( choice ) { return choice.kind === 'primary' } ) === false;

			choices.forEach( function( choice ) {
				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'nino-admin-btn-'+ ( choice.kind === 'primary' || choice.kind === 'danger' ? choice.kind : 'secondary' );
				btn.textContent = choice.label;
				btn.autofocus = cautious === true && choice === cancel;
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
		 *	A question with two answers, asked as heavily as the workbench's mode
		 *	asks it. `level` says what the action is worth - 'routine' is done all
		 *	day, 'big' reaches visitors or many records at once, 'destructive'
		 *	cannot be taken back - and the shell, which is the policy
		 *	(Nino.admin.mode.policy()), answers whether the question is asked at
		 *	all and what has to be added to it. A site that is being built asks
		 *	only before a destructive action, and onChoose( true ) comes at once,
		 *	before confirm() returns, where the answer is not asked for. Where
		 *	there is no shell with a policy - the setup wizard, a test, a shell
		 *	older than the policy - every question is asked, as confirm() always
		 *	asked.
		 *
		 *	The question is choiceDialog()'s and owns no words: `yes` and `no`
		 *	are what the answers are called, in the interface language. The note
		 *	the policy adds is drawn under the message as a paragraph of its own.
		 *
		 *	@param		{string}		level									'routine', 'big' or 'destructive'; anything else is taken for the last
		 *	@param		{Object}		options
		 *	@param		{string}		[options.title]
		 *	@param		{string}		options.message					The question
		 *	@param		{string}		options.yes							The label of the answer that goes on
		 *	@param		{string}		options.no							The label of the answer that does not
		 *	@param		{string}		[options.kind]					'danger' or 'primary' for the answer that goes on: 'danger' for a destructive level, else 'primary'. Where something goes away or is switched off, the caller says 'danger' - what the level costs a visitor and what the action costs the person are two questions
		 *	@param		{boolean}		[options.visitors]			false where the action does not touch what visitors see (an account, a role): the policy's note that visitors see the change at once is left out
		 *	@param		{Element}		[options.opener]				See choiceDialog()
		 *	@param		{Function}	options.onChoose				Called once with true or false - not at all when false is returned
		 *
		 *	@return		{boolean}												false when another question is already open and this one was not asked, which is the same click arriving twice
		 */
		confirm : function( level, options ) {

			const mode 		= shell().mode;
			const policy 	= typeof mode === 'object' && mode !== null && typeof mode.policy === 'function' ? mode.policy( level ) : { ask : true, note : '' };

			if( policy.ask === false ) {
				options.onChoose( true );
				return true;
			}

			return Nino.adminUi.choiceDialog( {
				title			: options.title,
				message		: options.message,
				note			: options.visitors === false ? '' : policy.note,
				opener		: options.opener,
				choices		: [
					{ value : 'yes', label : options.yes, kind : options.kind ?? ( level === 'routine' || level === 'big' ? 'primary' : 'danger' ) },
					{ value : 'no', label : options.no, kind : 'secondary' },
				],
				onChoose	: function( value ) { options.onChoose( value === 'yes' ) },
			} );
		},

		/**
		 *	A manual, drawn: what a screen is for and how it is used, as sections
		 *	of "a handle and what it does" or as running text. The one drawing
		 *	the "?" of every panel opens in a dialog (see manualDialog()), and the
		 *	one the Features panel draws a feature's manual with on its own
		 *	screen.
		 *
		 *	A section is { heading, entries }, an entry { handle, text }. A
		 *	handle is what a developer types and is set as code; a manual written
		 *	for the people who edit with a panel names it in words instead
		 *	(options.named). An entry with no handle says something about the
		 *	whole section and keeps the column the others stand in. A section
		 *	with no entries says so rather than being left out - "no callbacks"
		 *	is an answer, and a reader who does not find the question has to go
		 *	and read the source to learn that the answer was nothing - unless the
		 *	caller has no sentence for it.
		 *
		 *	Everything is set as text: a manual comes out of a manifest or a text
		 *	file, so there is no markup in it to get wrong. The running text,
		 *	which is the form a manifest written before the sections existed has,
		 *	has one mark: a `backticked` span is code, and an unclosed backtick
		 *	is text - shown as it was written instead of turning the rest of the
		 *	paragraph into code.
		 *
		 *	No box of its own and no cap on its height: what holds it decides how
		 *	it gets out of the way. Owns no strings
		 *
		 *	@param		{Array|string}	content					[ { heading, entries } ], or the running text - a blank line starts a paragraph, a single one is where the text wrapped its own line
		 *	@param		{Object}				[options]
		 *	@param		{string}				[options.none]			What a section with no entries says; such a section is left out where there is none
		 *	@param		{Function}			[options.named]			named( handle ) answers the words a handle is shown as, instead of code
		 *	@param		{number}				[options.level]			The heading level of the sections, 3 where it is not given - one under the dialog's own
		 *
		 *	@return		{Element|null}									<div class="nino-admin-manual">; null where there is nothing to show
		 */
		manual : function( content, options ) {

			options = options ?? {};

			const body = dc.createElement('div');
			body.className = 'nino-admin-manual';

			if( typeof content === 'string' )
				Nino.adminUi._manualProse( body, content.trim() );
			else if( Array.isArray( content ) === true )
				content.forEach( function( section ) { Nino.adminUi._manualSection( body, section, options ) } );

			return body.children.length === 0 ? null : body;
		},

		/**
		 *	One section of manual(): its heading, then a line per entry - the
		 *	handle beside what it does. One description list for the whole
		 *	section is what puts every handle of it in one column and every line
		 *	in another: one grid per entry - which is what a row of <p> is -
		 *	aligns each entry with itself and with nothing else, which is not a
		 *	column and is no faster to read than prose
		 *
		 *	@param		{Element}	body
		 *	@param		{Object}	section				{ heading, entries }
		 *	@param		{Object}	options				See manual()
		 *
		 *	@return		void
		 */
		_manualSection : function( body, section, options ) {

			const entries = Array.isArray( section.entries ) === true ? section.entries : [];
			const none		= typeof options.none === 'string' ? options.none : '';

			if( entries.length === 0 && none === '' )
				return;

			if( typeof section.heading === 'string' && section.heading !== '' ) {
				const level = Math.min( 6, Math.max( 2, Math.floor( Number( options.level ) ) || 3 ) );
				const heading = dc.createElement( 'h'+ level );
				heading.className = 'nino-admin-manual-heading';
				heading.textContent = section.heading;
				body.appendChild( heading );
			}

			if( entries.length === 0 ) {
				const empty = dc.createElement('p');
				empty.className = 'nino-admin-manual-none';
				empty.textContent = none;
				body.appendChild( empty );
				return;
			}

			const named = typeof options.named === 'function' ? options.named : null;
			const list	= dc.createElement('dl');
			list.className = 'nino-admin-manual-section'+ ( named === null ? '' : ' nino-admin-manual-section--terms' );

			entries.forEach( function( entry ) {

				const term = dc.createElement('dt');

				if( entry.handle ) {
					if( named !== null )
						term.textContent = named( String( entry.handle ) );
					else {
						const code = dc.createElement('code');
						code.textContent = entry.handle;
						term.appendChild( code );
					}
				}

				// A line with no handle of its own - one that says something about
				// the whole section - keeps the column the other lines are in
				const said = dc.createElement('dd');
				said.textContent = entry.text || '';

				list.appendChild( term );
				list.appendChild( said );
			} );

			body.appendChild( list );
		},

		/**
		 *	The running text of manual(): paragraphs, with `backticked` spans as
		 *	code
		 *
		 *	@param		{Element}	body
		 *	@param		{string}	text
		 *
		 *	@return		void
		 */
		_manualProse : function( body, text ) {

			// A blank line starts a paragraph; a single one is where the text
			// wrapped its own line and means nothing here
			text.split( /\n[ \t]*\n/ ).forEach( function( block ) {

				const flowed = block.trim().replace( /\s*\n\s*/g, ' ' );

				if( flowed === '' )
					return;

				const paragraph = dc.createElement('p');
				const pieces = flowed.split( '`' );

				// The backtick is the only markup there is, and it makes an
				// element rather than a string of html. An unclosed one is prose,
				// not markup
				if( pieces.length % 2 === 0 )
					paragraph.textContent = flowed;
				else
					pieces.forEach( function( piece, index ) {

						if( piece === '' )
							return;

						if( index % 2 === 0 ) {
							paragraph.appendChild( dc.createTextNode( piece ) );
							return;
						}

						const code = dc.createElement('code');
						code.textContent = piece;
						paragraph.appendChild( code );
					} );

				body.appendChild( paragraph );
			} );
		},

		/**
		 *	A manual in a dialog: the panel's name over it, a button to close it
		 *	under it, and what is between them scrolls. The dialog is
		 *	choiceDialog()'s - it opens, closes on Escape, and gives the focus
		 *	back the way every question of the workbench does, with one answer
		 *	and no question. The focus goes into the dialog on opening
		 *
		 *	@param		{string}		title					What the manual is of - the panel's name
		 *	@param		{Element}		content				What manual() answered
		 *	@param		{Element}		[opener]			The button that asked, which gets the focus back
		 *
		 *	@return		{boolean}									false when a question is already open
		 */
		manualDialog : function( title, content, opener ) {

			return Nino.adminUi.choiceDialog( {
				title			: title,
				message		: content.textContent,
				content		: content,
				className	: 'nino-admin-dialog--manual',
				opener		: opener,
				choices		: [ { value : 'close', label : Nino.content.getText('/_admin/common/label/close'), kind : 'secondary' } ],
				onChoose	: function() {},
			} );
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
		 *	What a text key is made of, read from its form alone - the one
		 *	function the Text panel, its Keys tab and the Images panel share.
		 *	A key is one of
		 *
		 *	  - a key of the grammar, /<namespace>/<category>/<part>/<name>:
		 *	    { kind : 'grammar', namespace, category, part, name }, with
		 *	    { list, id } added when the part has a hyphen - 'category-necessary'
		 *	    is the list 'category' and the entry 'necessary'. Whether the part
		 *	    really is a list is the vocabulary's call (see slugLabel())
		 *	  - the details of a page the system writes after its Element-URI,
		 *	    /_nino/webpage<uri>/<field>: { kind : 'webpage', uri, field }, the
		 *	    uri read from the right, since it may hold slashes and dots
		 *	  - the name of a language, /_nino/locale/<code>/name: { kind : 'locale', code }
		 *	  - anything else - a key a project made up, one of the workbench:
		 *	    { kind : 'free' }
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Object}
		 */
		describeKey : function( key ) {

			key = String( key ?? '' );

			let match = new RegExp( '^/_nino/webpage(/.+)/(name|title|description|uri)$' ).exec( key );
			if( match !== null )
				return { kind : 'webpage', uri : match[1], field : match[2] };

			match = /^\/_nino\/locale\/([^\/]+)\/name$/.exec( key );
			if( match !== null )
				return { kind : 'locale', code : match[1] };

			match = /^\/(template|project|feature|module)\/([a-z0-9]+(?:-[a-z0-9]+)*)\/([a-z0-9]+(?:-[a-z0-9]+)*)\/([a-z0-9]+(?:-[a-z0-9]+)*)$/.exec( key );
			if( match === null )
				return { kind : 'free' };

			const described = { kind : 'grammar', namespace : match[1], category : match[2], part : match[3], name : match[4] };
			const dash = match[3].indexOf('-');

			if( dash > 0 ) {
				described.list = match[3].slice( 0, dash );
				described.id = match[3].slice( dash + 1 );
			}

			return described;
		},

		/**
		 *	A slug the way a person reads it when no word of the vocabulary
		 *	names it: the first letter in capitals, hyphens and underscores as
		 *	spaces
		 *
		 *	@param		{string}	slug
		 *
		 *	@return		{string}
		 */
		humanize : function( slug ) {
			slug = String( slug ?? '' ).replace( /[-_]+/g, ' ' ).trim();
			return slug.charAt(0).toUpperCase() + slug.slice(1);
		},

		/**
		 *	The name of one segment of a text key, in the language of the
		 *	interface: a word of the closed vocabulary (/_admin/common/word/<slug>,
		 *	one per slug, no aliases), put together from words where the slug
		 *	is a compound, humanized where it is not. In this order:
		 *
		 *	  1. the slug is a word: 'intro' is "Einleitung"
		 *	  2. it is <word>-<number>: 'item-3' is "Eintrag 3"
		 *	  3. it is split at every hyphen from the left, and where what is
		 *	     right of the hyphen is a word, the two read "<left> · <word>" -
		 *	     the left part looked up as well, humanized if it is no word:
		 *	     'unit-day' is "Einheit · Tag", 'cta-label' "Cta · Beschriftung",
		 *	     'hero-image-alt' "Hero image · Alternativtext"
		 *	  4. otherwise it is humanized: 'tagline' is "Tagline"
		 *
		 *	@param		{string}	slug
		 *
		 *	@return		{string}
		 */
		slugLabel : function( slug ) {

			slug = String( slug ?? '' );

			const word = function( candidate ) { return Nino.content.getText( '/_admin/common/word/'+ candidate ) };

			if( word( slug ) !== '' )
				return word( slug );

			const numbered = /^(.+)-(\d+)$/.exec( slug );
			if( numbered !== null )
				return Nino.adminUi.slugLabel( numbered[1] )+ ' '+ numbered[2];

			for( let dash = slug.indexOf('-'); dash !== -1; dash = slug.indexOf( '-', dash + 1 ) ) {
				const right = word( slug.slice( dash + 1 ) );
				if( dash > 0 && right !== '' ) {
					const left = slug.slice( 0, dash );
					return ( word( left ) || Nino.adminUi.humanize( left ) )+ ' · '+ right;
				}
			}

			return Nino.adminUi.humanize( slug );
		},

		/**
		 *	Whether a slug is covered by the vocabulary: the words the interface
		 *	names a part of a key with, /_admin/common/word/<slug>. It is one,
		 *	or <word>-<number> (a list's entry: 'item-3'), or <word>-<word> -
		 *	the slug falls apart at a hyphen into two words, as 'unit-day' does.
		 *	That is the rule of every key the kernel ships (admin-text-smoke
		 *	holds them to it), and what slugLabel() gives a slug that is none of
		 *	it is only a guess at how to read it: 'cta' is shown as "Cta"
		 *
		 *	@param		{string}	slug
		 *
		 *	@return		{boolean}
		 */
		isWord : function( slug ) {

			slug = String( slug ?? '' );

			const word = function( candidate ) { return Nino.content.getText( '/_admin/common/word/'+ candidate ) !== '' };

			if( word( slug ) === true )
				return true;

			const numbered = /^(.+)-\d+$/.exec( slug );
			if( numbered !== null )
				return Nino.adminUi.isWord( numbered[1] );

			for( let dash = slug.indexOf('-'); dash !== -1; dash = slug.indexOf( '-', dash + 1 ) )
				if( dash > 0 && word( slug.slice( 0, dash ) ) === true && word( slug.slice( dash + 1 ) ) === true )
					return true;

			return false;
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
		 *	An element with a class and a text, in one call: the tag, the class
		 *	set only when there is one, the text set as text - never as markup -
		 *	only when there is one. Owns no strings
		 *
		 *	@param		{string}		tag
		 *	@param		{string}		[className]
		 *	@param		{string}		[text]
		 *
		 *	@return		{Element}
		 */
		el : function( tag, className, text ) {

			const el = dc.createElement( tag );

			if( typeof className === 'string' && className !== '' )
				el.className = className;

			if( text !== undefined )
				el.textContent = text;

			return el;
		},

		/**
		 *	A button: a real button, which does not submit unless it is asked to,
		 *	showing a word or a glyph. A glyph is no name - a screen reader says
		 *	"button ↑" - so a button whose face is one is given its name, which
		 *	becomes its title and its aria-label both. The look is the caller's
		 *	class (nino-admin-btn-primary, nino-admin-btn--small...). Owns no
		 *	strings: the face and the name are the caller's
		 *
		 *	@param		{string}		text						What the button shows
		 *	@param		{Function}	[onClick]				Called on a click
		 *	@param		{Object}		[options]				{ className, name, disabled, id, type } - type 'submit' for a button that submits
		 *
		 *	@return		{Element}
		 */
		button : function( text, onClick, options ) {

			options = options || {};

			const button = dc.createElement('button');
			button.type = options.type === 'submit' ? 'submit' : 'button';

			if( options.className )
				button.className = options.className;

			button.textContent = text;

			if( options.name ) {
				button.title = options.name;
				button.setAttribute( 'aria-label', options.name );
			}

			button.disabled = options.disabled === true;

			if( options.id )
				button.id = options.id;

			if( typeof onClick === 'function' )
				button.addEventListener( 'click', onClick );

			return button;
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
		 *	is announced as a tab, so it is offered the keys a tab answers to.
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

		// What tabBadge() was told, by tab: { text, state }. The record of the chips,
		// whether the button that carries one is in the page or not
		_tabBadges : new Map(),

		/**
		 *	A short word as a chip on the button of a tab in the pane's strip - how
		 *	many of something wait in that tab, what state it is in. The button is
		 *	the registry's (id admin-tabbutton-<tab>, see \Nino\Admin\Panels::$html),
		 *	and the kernel has no place in its label for a word of a panel's, so the
		 *	panel's script puts one there: the workbench's own pill, .nino-admin-badge,
		 *	after the label. One chip at most: a second call replaces the first, and an
		 *	empty text - or null - takes it away. Asked with the tab alone, it answers
		 *	the word it was given, '' where there is none.
		 *
		 *	The shell does not draw its strip again - a move between panels and tabs
		 *	changes the classes and attributes of the buttons, never the buttons - so a
		 *	chip stays where it was put. The word is recorded all the same, by tab, and
		 *	is answered from the record: also for a tab that has no button in the page
		 *	(the account lacks its permission), where nothing is drawn. A chip of a
		 *	script's own on the same button - a class of its own beside nino-admin-badge -
		 *	is left as it is
		 *
		 *	@param		{string}				tab							The uri of the tab, a slug
		 *	@param		{string|number}	[text]					The word; empty or null removes the chip; left out, the call answers
		 *	@param		{string}				[state]					ok, warn, error or muted, the colours of .nino-admin-badge; none, or any other, draws it in the colour of the label
		 *
		 *	@return		{string|undefined}									The word, for a call with the tab alone
		 */
		tabBadge : function( tab, text, state ) {

			const badges = Nino.adminUi._tabBadges;

			if( arguments.length < 2 )
				return badges.has( tab ) === true ? badges.get( tab ).text : '';

			if( typeof tab !== 'string' || /^[a-z][a-z0-9-]*$/.test( tab ) === false )
				return;

			const word = text === null || text === undefined ? '' : String( text ).trim();

			if( word === '' )
				badges.delete( tab );
			else
				badges.set( tab, { text : word, state : state } );

			const button = dc.getElementById( 'admin-tabbutton-'+ tab );

			if( button === null )
				return;

			const before = button.querySelector(':scope > .nino-admin-tab-badge');

			if( before !== null )
				before.remove();

			if( word === '' )
				return;

			const chip = Nino.adminUi.el( 'span', 'nino-admin-badge nino-admin-tab-badge', word );

			if( [ 'ok', 'warn', 'error', 'muted' ].indexOf( state ) !== -1 )
				chip.setAttribute( 'data-state', state );

			button.appendChild( chip );
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
		 *	One line of text as a labelled input, beside selectField(),
		 *	switchField() and numberField() and by the select's rule: the words
		 *	are used as they are, so a caller that has a fill key runs it through
		 *	text() itself. A required field is marked the way the workbench marks
		 *	every one: the asterisk behind its name, drawn aria-hidden, and
		 *	aria-required on the control. Owns no strings, the asterisk apart
		 *
		 *	@param	{Object}	options		{ key, type, value, placeholder, label, hint, required, maxLength, minLength, autocomplete, id, onInput, className }
		 *													- type       the input's type, text by default
		 *													- key        written onto the input as data-key
		 *													- onInput    called with the input's value on every input
		 *													- className  the label's class instead of nino-admin-field
		 *
		 *	@return	{Element}						The label; the control is its input
		 */
		textField : function( options ) {

			const field = dc.createElement('label');
			field.className = options.className || 'nino-admin-field';

			const name = dc.createElement('span');
			name.appendChild( dc.createTextNode( options.label ?? '' ) );

			if( options.required === true ) {
				const star = dc.createElement('span');
				star.className = 'nino-admin-required';
				star.setAttribute( 'aria-hidden', 'true' );
				star.textContent = '*';
				name.appendChild( star );
			}

			field.appendChild( name );

			const input = dc.createElement('input');
			input.className = 'nino-admin-input';
			input.type = options.type || 'text';

			if( options.key )
				input.dataset.key = options.key;

			if( options.value !== undefined && options.value !== null )
				input.value = options.value;

			if( options.placeholder )
				input.placeholder = options.placeholder;

			if( typeof options.maxLength === 'number' )
				input.maxLength = options.maxLength;

			if( typeof options.minLength === 'number' )
				input.minLength = options.minLength;

			if( options.autocomplete )
				input.autocomplete = options.autocomplete;

			if( options.id )
				input.id = options.id;

			if( options.required === true ) {
				input.required = true;
				input.setAttribute( 'aria-required', 'true' );
			}

			input.addEventListener( 'input', function() {
				if( typeof options.onInput === 'function' )
					options.onInput( input.value );
			} );

			field.appendChild( input );

			if( options.hint ) {
				const hint = dc.createElement('small');
				hint.className = 'nino-admin-hint';
				hint.textContent = options.hint;
				field.appendChild( hint );
			}

			return field;
		},

		/**
		 *	The "which translation am I looking at" switch of a form: a select of
		 *	the locale codes, the current one picked. It says what the form below
		 *	it shows rather than collecting a value, so it is the compact pill of
		 *	the design system (.nino-admin-locale-select) and sits in the pinned
		 *	context bar. What a change does is the caller's - the Elements and
		 *	Text panels also tell the session (Nino.admin.sessionLocale), the Keys
		 *	tab does not. The select is named: the caller's label, or the shared
		 *	word for a translation where it has none - a combobox of bare codes
		 *	is announced as nothing else
		 *
		 *	@param		{Object}		options					{ locales, inactive, current, label, id, onChange }
		 *														- locales    the codes, which are also what the options say
		 *														- inactive   the codes of the languages the website does not serve yet,
		 *														             which an option says with the word Inactive in parentheses behind its code
		 *														- current    the code that is picked
		 *														- label      the select's aria-label, /_admin/common/label/locale by default
		 *														- onChange   called with the picked code on every change
		 *
		 *	@return		{Element}										The select
		 */
		localeSelect : function( options ) {

			const select = dc.createElement('select');

			if( options.id )
				select.id = options.id;

			select.className = 'nino-admin-locale-select nino-admin-contextbar-select';

			select.setAttribute( 'aria-label', options.label || Nino.adminUi.text('/_admin/common/label/locale') );

			( options.locales || [] ).forEach( function( locale ) {
				const option = dc.createElement('option');
				option.value = locale;
				option.textContent = ( options.inactive || [] ).indexOf( locale ) === -1 ? locale : locale+ ' ('+ Nino.adminUi.text('/_admin/common/word/inactive')+ ')';
				option.selected = ( locale === options.current );
				select.appendChild( option );
			} );

			select.addEventListener( 'change', function() {
				if( typeof options.onChange === 'function' )
					options.onChange( select.value );
			} );

			return select;
		},

		/**
		 *	Whether one model field holds a list of element references rather
		 *	than a single uri - the client-side half of
		 *	\Nino\Elements::isMultiElement(), and deliberately the same rule:
		 *	presence of a number under 'multiple' is the switch, so a model
		 *	written before the setting existed stays a single reference in the
		 *	element form exactly as it does in the kernel.
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
		 *	loaded, rather than against an endpoint of its own. The element
		 *	form fetches the referenced type's elements up front to build the
		 *	single-reference select (see _loadReferenceOptions() in
		 *	_admin/Nino/Modules/Elements/assets/admin.js), and the Roles tab
		 *	holds every permission already, so the data is in the page before
		 *	this control is built - a request per keystroke would ask a second
		 *	time for what is already here, and would have to grow a debounce
		 *	and a race guard to do it.
		 *
		 *	Owns no strings, same rule as table() and switchField(): every word
		 *	comes from the caller through options.text.
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
			// form would have to know about: every other field in the element form is
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
						actions.appendChild( Nino.adminUi.button( '↑', function() { move( index, -1 ) }, { name : text.up, disabled : index === 0 } ) );
						actions.appendChild( Nino.adminUi.button( '↓', function() { move( index, 1 ) }, { name : text.down, disabled : index === chosen.length - 1 } ) );
					}
					actions.appendChild( Nino.adminUi.button( '✕', function() { remove( index ) }, { className : 'nino-admin-elementlist-remove', name : text.remove } ) );
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
					const addButton = Nino.adminUi.button( '+', function() { add( option.value ) }, { className : 'nino-admin-elementlist-add', name : text.add } );
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
					more.textContent = Nino.adminUi.format( text.more, matches.length );
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
		 *	The control an element field of type array uses while its value is a
		 *	plain list of texts: one row per text, with move and remove per row and
		 *	an add button, instead of a JSON field a person has to write brackets
		 *	and quotes into. A value that is anything else (objects, numbers, a
		 *	list inside a list) keeps the JSON textarea - this edits strings and
		 *	cannot tell the rest what it is.
		 *
		 *	Like elementList(), the value is a hidden input carrying the JSON, so
		 *	the form reads, compares and saves it the way it does every other field.
		 *	It starts as exactly the JSON the value is stored as, and only a
		 *	change rewrites it: an untouched list is not an edited one. The rows'
		 *	own inputs carry no data-field - the form takes the first element it
		 *	finds under a key as the field. A row that is empty is not an entry,
		 *	and is left out of the value.
		 *
		 *	Owns no strings, same rule as elementList(): every word comes from the
		 *	caller through options.text.
		 *
		 *	@param	{Object}	options		{ key, label, value, text, onChange }
		 *													- key      the model key, written onto the hidden input
		 *													- value    array of strings, absent for none
		 *													- text     { add, remove, up, down, empty, item } - item
		 *													           names a row for a screen reader, with %d for its
		 *													           number; the field's label is the fallback
		 *
		 *	@return	{Element}						The field wrapper; its hidden input carries
		 *													data-field/data-type and holds the value as json
		 */
		stringList : function( options ) {

			const text = options.text || {};
			const rows = Array.isArray( options.value ) ? options.value.map( String ) : [];

			const field = dc.createElement('div');
			field.className = 'nino-admin-field nino-admin-stringlist';

			const name = dc.createElement('span');
			name.textContent = options.label || options.key;
			field.appendChild( name );

			const store = dc.createElement('input');
			store.type = 'hidden';
			store.dataset.field = options.key;
			store.dataset.type = 'array';
			store.value = JSON.stringify( Array.isArray( options.value ) ? options.value : [] );
			field.appendChild( store );

			const list = dc.createElement('ul');
			list.className = 'nino-admin-stringlist-rows';
			field.appendChild( list );

			function commit() {
				store.value = JSON.stringify( rows.filter( function( row ) { return row.trim() !== '' } ) );
				// Writing the hidden input fires nothing; tell a surrounding form (status line) the value changed
				if( typeof store.dispatchEvent === 'function' && typeof wn.Event === 'function' )
					store.dispatchEvent( new wn.Event( 'change', { bubbles : true } ) );
				if( typeof options.onChange === 'function' )
					options.onChange( JSON.parse( store.value ) );
			}

			function move( index, delta ) {
				const target = index + delta;
				if( target < 0 || target >= rows.length )
					return;
				const moved = rows[index];
				rows[index] = rows[target];
				rows[target] = moved;
				commit();
				// The button that was pressed is redrawn: the one in its place is
				// where the focus stays, so the row can be moved again
				draw( target, delta < 0 ? 'up' : 'down' );
			}

			function remove( index ) {
				rows.splice( index, 1 );
				commit();
				// The row that took its place offers its own remove button next
				draw( Math.min( index, rows.length - 1 ), 'remove' );
				if( rows.length === 0 && typeof add.focus === 'function' )
					add.focus();
			}

			// focusIndex is the row to put the focus in and focusPart the control
			// of it: 'up', 'down', 'remove', or the text input where it is left out
			function draw( focusIndex, focusPart ) {

				list.innerHTML = '';

				if( rows.length === 0 ) {
					const empty = dc.createElement('li');
					empty.className = 'nino-admin-stringlist-empty';
					empty.textContent = text.empty || '';
					list.appendChild( empty );
					return;
				}

				rows.forEach( function( row, index ) {

					const item = dc.createElement('li');

					const input = dc.createElement('input');
					input.type = 'text';
					input.className = 'nino-admin-stringlist-input';
					input.value = row;
					input.setAttribute( 'aria-label', text.item ? Nino.adminUi.format( text.item, index + 1 ) : ( options.label || options.key ) );
					input.addEventListener( 'input', function() {
						rows[index] = input.value;
						commit();
					} );
					item.appendChild( input );

					const actions = dc.createElement('span');
					actions.className = 'nino-admin-stringlist-actions';
					const up 			= Nino.adminUi.button( '↑', function() { move( index, -1 ) }, { name : text.up, disabled : index === 0 } );
					const down 		= Nino.adminUi.button( '↓', function() { move( index, 1 ) }, { name : text.down, disabled : index === rows.length - 1 } );
					const removal = Nino.adminUi.button( '✕', function() { remove( index ) }, { className : 'nino-admin-stringlist-remove', name : text.remove } );
					actions.appendChild( up );
					actions.appendChild( down );
					actions.appendChild( removal );
					item.appendChild( actions );

					list.appendChild( item );

					if( focusIndex !== index )
						return;

					// A move button that has nowhere left to go is disabled and cannot
					// hold the focus: the other one is next, then the text
					const wanted = { up : [ up, down ], down : [ down, up ], remove : [ removal ] }[focusPart] || [];
					const target = wanted.concat( [ input ] ).find( function( control ) { return control.disabled !== true } );

					if( typeof target.focus === 'function' )
						target.focus();
				} );
			}

			const add = Nino.adminUi.button( text.add || '', function() {
				rows.push( '' );
				draw( rows.length - 1 );
			}, { className : 'nino-admin-stringlist-add' } );
			field.appendChild( add );

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
			 *	Whether a cell has no value: null, undefined, '' or - for a field
			 *	that holds a list of elements - an empty list. sort() places such
			 *	a cell with the other absent ones, last whichever way the column
			 *	runs
			 *
			 *	@param		{*}				value
			 *
			 *	@return		{boolean}
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
			 *	Order two present values of one type. A plain string compare puts
			 *	10 before 9 and sorts booleans by the words "false"/"true", so each
			 *	type that is not text gets its own ordering. Absent values are not
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
			 *	Order rows by one column: the present values by compare(), the
			 *	absent ones (see isEmpty()) after them whichever way the column
			 *	runs
			 *
			 *	@param		{Array}		rows
			 *	@param		{string}	key				Column key, '' to leave the order alone
			 *	@param		{string}	type			That column's model type
			 *	@param		{number}	dir				1 ascending, -1 descending
			 *	@param		{Function}	[absent]		absent( row ) is true for a row whose cell is to count as absent although it holds a value - a text a column only shows in place of another's
			 *
			 *	@return		{Array}								A new array; the input is not reordered
			 */
			sort : function( rows, key, type, dir, absent ) {

				if( !key )
					return rows.slice();

				const model = Nino.adminUi.tableModel;

				return rows.slice().sort( function( rowA, rowB ) {

					const a = rowA[key], b = rowB[key];
					const emptyA = model.isEmpty( a ) || ( typeof absent === 'function' && absent( rowA ) === true );
					const emptyB = model.isEmpty( b ) || ( typeof absent === 'function' && absent( rowB ) === true );

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
			 *	How many pages a number of rows make at a page size
			 *
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
		 *	Owns no strings. Every word it can show comes from the caller
		 *	through `labels`, translated through Nino.content or not, so the
		 *	component is never half in one language and half in another.
		 *	Everything that can be a number or a glyph - the pager arrows, the
		 *	row range, a boolean cell - is one, so the caller only has to
		 *	supply two actual sentences.
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
		 *	@param		{Array}			options.columns				[ { key, label, type, render, sortable }, ... ]
		 *																					A column is sorted by the button its label is;
		 *																					`sortable` is true for a column that has a label
		 *																					and false for one that has none - the column of
		 *																					a row's own buttons - unless the caller says.
		 *																					`absent(row)` says that a row's cell counts as empty
		 *																					when the column is sorted, though it shows a value
		 *																					(searching still reads what is shown).
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
		 *	@param		{Function}	[options.onRowClick]	Called with the row object, on a click of the row and on Enter or Space
		 *																					with the row focused. The row is a stop of the Tab
		 *																					key only where no control stands in it: a row with
		 *																					buttons or links of its own is reached through them
		 *	@param		{string}		[options.rowKey]			Row property used as the row's identity - also written on the row as data-row, where a
		 *																					caller finds it again after the table was drawn anew
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

				// A sort button with no word on it is announced as nothing and
				// offers an order nobody can name: a column without a label has none
				if( column.sortable === undefined ? String( column.label ?? '' ) === '' : column.sortable !== true ) {
					th.textContent = column.label ?? '';
					headRow.appendChild( th );
					return;
				}

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
				const sortColumn = columns.find( function( c ) { return c.key === sortKey } ) || {};
				const sorted 	= model.sort( matched, sortKey, sortColumn.type, sortDir, sortColumn.absent );
				const view 		= model.page( sorted, pageSize, page );

				page = view.page;

				headRow.querySelectorAll('th').forEach( function( th ) {

					// "none" tells the reader a column can be sorted: not one that cannot
					if( th.querySelector('button') === null )
						return;

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
					if( row[rowKey] !== undefined && row[rowKey] !== null )
						tr.dataset.row = String( row[rowKey] );
					if( typeof options.onRowClick === 'function' ) {
						tr.classList.add('is-clickable');
						tr.addEventListener( 'click', function() { options.onRowClick( row ) } );
						tr.addEventListener( 'keydown', function( ev ) {
							// A key pressed on a button in the row is the button's, not the row's
							if( ev.target === tr && ( ev.key === 'Enter' || ev.key === ' ' ) ) { ev.preventDefault(); options.onRowClick( row ) }
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

					// Its own stop of the Tab key only where nothing inside it is one: a
					// row that holds its buttons or links is reached through them, and
					// would be a second stop for the same thing
					if( typeof options.onRowClick === 'function' && tr.querySelector('a, button, input, select, textarea') === null )
						tr.tabIndex = 0;

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

				// .nino-admin-hidden, not the workbench's own .admin-hidden:
				// this component belongs to the design system, which uses
				// nino-admin-* classes only and does not lean on a class of
				// the nino.tool layer (see _admin/assets/style.css)
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
		 *	The levels of a panel: its types, its list and its form are elements of
		 *	the pane, ids that start alike (<prefix>-list, <prefix>-form), and the
		 *	panel shows one of them at a time. Nearly every panel wrote the same
		 *	function to hide the others - toggling the shell's admin-hidden class,
		 *	the one every copy and the panel recipe use, which is not the
		 *	design system's own nino-admin-hidden - and several of them wrote the
		 *	level into the url hash as well.
		 *
		 *	Only that: a redraw, a refresh or a dropped working copy that goes
		 *	with showing a level stays with the panel that needs it. Where the
		 *	shell has its router, show() writes the level into the hash when it
		 *	is given the parts; where it has not (the wizard, a test) it leaves
		 *	the address alone. Owns no strings
		 *
		 *	@param		{string}		prefix					The ids' beginning: 'catalog' for catalog-list
		 *	@param		{Array}			names						The levels, in the ids' endings: [ 'list', 'form' ]
		 *
		 *	@return		{Object}										{ show( name, parts ) } - shows the level of that name and hides the others (an id the page does not have is skipped); with parts, an array, it also asks the router to write #<prefix>/<parts>
		 */
		levels : function( prefix, names ) {

			return {
				show : function( name, parts ) {

					names.forEach( function( level ) {
						const el = dc.getElementById( prefix+ '-'+ level );
						if( el !== null )
							el.classList.toggle( 'admin-hidden', level !== name );
					} );

					const router = shell().router;
					if( Array.isArray( parts ) === true && typeof router === 'object' && router !== null )
						router.set( prefix, parts );
				},
			};
		},

		/**
		 *	An accordion: sections one above the other, each a heading with a
		 *	button that opens it. One section is open (the way a screen of
		 *	settings reads: a list of groups, the one in hand spread out) or, with
		 *	options.multiple, as many as the person opens, or - with
		 *	options.collapsible - at most one: a press on the open head closes it, so
		 *	that the head below it can be reached, and none is open then. An accordion stands in
		 *	the body of a section of another - two levels is what the panels use -
		 *	and each is its own: the arrows walk the heads of one accordion and
		 *	never cross into the one inside.
		 *
		 *	The head is a button in a heading (options.level) carrying
		 *	aria-expanded and aria-controls, the body a region labelled by it.
		 *	Where one section is open at a time and the accordion is not collapsible
		 *	its head is aria-disabled - it says it is open and a press changes
		 *	nothing - and stays in the tab order; a head that can be closed is not.
		 *	Enter and Space press it, Up and Down walk the heads, Home and
		 *	End go to the first and the last. A closed body is hidden, not
		 *	removed: what a person entered into a section is still there when it
		 *	opens a second time, and a body given as a function is made when its section
		 *	opens for the first time.
		 *
		 *	The open sections are the address of the panel where it is given
		 *	options.address: the parts of the level the accordion stands in, to
		 *	which the open key follows (the keys joined by a comma where several
		 *	may be open, so a key holds none); with no section open the address is
		 *	the level's own, and follow() on such an address closes every section
		 *	of a collapsible accordion (any other has one open at all times). A press is the person's move - a
		 *	new entry for Back to return to - and set() and follow() move without
		 *	writing, which is what a panel does when the address has changed under
		 *	it. Without the shell's router the address is left alone, as levels()
		 *	does. Owns no strings: the labels, the summary and the badge are the
		 *	caller's
		 *
		 *	@param		{Object}		options					{ id, sections, open, multiple, collapsible, level, address, onToggle }
		 *														- id          the beginning of the ids it makes, one the page has not
		 *														- sections    [ { key, label, summary, badge, content } ] - content an Element, or a function that answers one when the section is first opened; summary is read on the closed head, badge a chip beside the label (a state such as "changed")
		 *														- open        the key open at the start, or an array of them
		 *														- multiple    several sections may be open
		 *														- collapsible at most one section is open and a press on it closes it; none may be open, so without open none is at the start. Ignored with multiple
		 *														- level       the heading level of the heads, 3 by default; 4 for an accordion inside a section
		 *														- address     { panel, parts } - the router's panel and the parts before the open key
		 *														- onToggle    called with the open keys and the key that was pressed, after a press
		 *
		 *	@return		{Object}										{ el, get(), set( keys ), follow(), update( key, { summary, badge } ), head( key ), body( key ) }
		 */
		accordion : function( options ) {

			const prefix		= options.id || 'nino-admin-accordion-'+ ( ++serial );
			const multiple	= options.multiple === true;
			const collapsible	= multiple === false && options.collapsible === true;
			const level			= Number.isInteger( options.level ) === true && options.level >= 1 && options.level <= 6 ? options.level : 3;
			const root			= dc.createElement('div');
			root.className	= 'nino-admin-accordion';

			let open = [];
			const parts = [];

			const keys = options.sections.map( function( section ) { return section.key } );

			const known = function( wanted ) {
				const list = ( Array.isArray( wanted ) === true ? wanted : [ wanted ] ).filter( function( key ) { return keys.indexOf( key ) !== -1 } );
				return multiple === true ? list : list.slice( 0, 1 );
			};

			// Where one section is open at a time one is, unless the accordion is collapsible:
			// what was asked for, else the one the accordion started with, else the first - a
			// Back to the level with no section in its address, or a key the accordion has not,
			// leaves no screen of closed heads
			const settle = function( wanted ) {

				const list = known( wanted );

				if( list.length > 0 || multiple === true || collapsible === true )
					return list;

				const first = known( options.open ?? [] );

				return first.length > 0 ? first : keys.slice( 0, 1 );
			};

			const paint = function() {
				parts.forEach( function( part, at ) {

					const on = open.indexOf( part.key ) !== -1;

					part.button.setAttribute( 'aria-expanded', on === true ? 'true' : 'false' );

					if( multiple === false && collapsible === false && on === true )
						part.button.setAttribute( 'aria-disabled', 'true' );
					else
						part.button.removeAttribute('aria-disabled');

					part.chevron.textContent = on === true ? '▾' : '▸';
					part.summary.hidden = on === true;
					part.body.hidden = on === false;
					part.head.classList.toggle( 'is-open', on );

					if( on === true && part.made === false ) {
						part.made = true;
						const content = typeof options.sections[at].content === 'function' ? options.sections[at].content() : options.sections[at].content;
						if( content !== null && content !== undefined )
							part.body.appendChild( content );
					}
				} );
			};

			// The router the shell has, if it has one: the address is the panel's, not the accordion's
			const write = function() {

				const address = options.address;
				const router = shell().router;

				if( typeof address !== 'object' || address === null || typeof router !== 'object' || router === null )
					return;

				const at = address.parts.slice();

				if( open.length > 0 )
					at.push( open.join(',') );

				if( typeof router.go === 'function' )
					router.go( address.panel, at );

				if( typeof router.set === 'function' )
					router.set( address.panel, at );
			};

			const press = function( key ) {

				if( multiple === true )
					open = open.indexOf( key ) === -1 ? open.concat( [ key ] ) : open.filter( function( other ) { return other !== key } );
				else if( open[0] === key && collapsible === false )
					return;
				else if( open[0] === key )
					open = [];
				else
					open = [ key ];

				paint();
				write();

				if( typeof options.onToggle === 'function' )
					options.onToggle( open.slice(), key );
			};

			options.sections.forEach( function( section, at ) {

				const id = prefix+ '-'+ at;

				const heading = dc.createElement( 'h'+ level );
				heading.className = 'nino-admin-accordion-head';

				const button = dc.createElement('button');
				button.type = 'button';
				button.className = 'nino-admin-accordion-button';
				button.id = id+ '-head';
				button.setAttribute( 'aria-controls', id+ '-body' );

				const label = Nino.adminUi.el( 'span', 'nino-admin-accordion-label', section.label );
				const badge = Nino.adminUi.el( 'span', 'nino-admin-badge nino-admin-accordion-badge', section.badge ?? '' );
				badge.dataset.state = 'warn';
				badge.hidden = ( section.badge ?? '' ) === '';

				const summary = Nino.adminUi.el( 'span', 'nino-admin-accordion-summary', section.summary ?? '' );
				const chevron = Nino.adminUi.el( 'span', 'nino-admin-accordion-chevron' );
				chevron.setAttribute( 'aria-hidden', 'true' );

				[ label, badge, summary, chevron ].forEach( function( piece ) { button.appendChild( piece ) } );
				heading.appendChild( button );

				const body = dc.createElement('div');
				body.id = id+ '-body';
				body.className = 'nino-admin-accordion-body';
				body.setAttribute( 'role', 'region' );
				body.setAttribute( 'aria-labelledby', id+ '-head' );

				button.addEventListener( 'click', function() { press( section.key ) } );

				button.addEventListener( 'keydown', function( ev ) {

					let next = -1;
					if( ev.key === 'ArrowDown' )	next = at + 1;
					if( ev.key === 'ArrowUp' )		next = at - 1;
					if( ev.key === 'Home' )				next = 0;
					if( ev.key === 'End' )				next = parts.length - 1;

					if( [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ].indexOf( ev.key ) === -1 )
						return;

					// The arrows scroll the page otherwise, and a head has no other use for them
					ev.preventDefault();

					if( next >= 0 && next < parts.length )
						parts[next].button.focus();
				} );

				root.appendChild( heading );
				root.appendChild( body );

				parts.push( { key : section.key, head : heading, button : button, badge : badge, summary : summary, chevron : chevron, body : body, made : false } );
			} );

			open = settle( options.open ?? [] );
			paint();

			const part = function( key ) {
				return parts.find( function( candidate ) { return candidate.key === key } ) ?? null;
			};

			return {

				el : root,

				get : function() {
					return open.slice();
				},

				// Open what the address, or the caller, says: no address written, no callback
				set : function( wanted ) {
					open = settle( wanted ?? [] );
					paint();
				},

				// Take the open sections from the router's current address, where it
				// is the level this accordion stands in
				follow : function() {

					const address = options.address;
					const router = shell().router;

					if( typeof address !== 'object' || address === null || typeof router !== 'object' || router === null || typeof router.current !== 'function' )
						return;

					const current = router.current();
					const own = address.parts;

					if( current.panel !== address.panel || own.some( function( value, at ) { return current.parts[at] !== value } ) === true )
						return;

					const named = current.parts[own.length];
					open = settle( typeof named === 'string' ? named.split(',') : [] );
					paint();
				},

				update : function( key, changes ) {

					const found = part( key );
					if( found === null )
						return;

					if( changes.summary !== undefined )
						found.summary.textContent = changes.summary;

					if( changes.badge !== undefined ) {
						found.badge.textContent = changes.badge;
						found.badge.hidden = changes.badge === '';
					}
				},

				head : function( key ) {
					const found = part( key );
					return found === null ? null : found.button;
				},

				body : function( key ) {
					const found = part( key );
					return found === null ? null : found.body;
				},
			};
		},

		/**
		 *	A choice among a few steps, drawn as one strip: -2 to +2, or named
		 *	steps. A radiogroup of buttons in the pattern's way - one tab stop
		 *	(the chosen step, or the first where none is), the arrows move the
		 *	choice and the focus with it and wrap at the ends, Home and End go
		 *	to the first and the last. The step that is the default says so
		 *	by a mark and by its name ("0 (Default)"), so that the way back is
		 *	visible and a screen reader reads it; the chosen one is filled.
		 *
		 *	The strip does not own the value: it reports a choice made by hand
		 *	through onChange and takes one from outside through set(), which
		 *	reports nothing. A step may be disabled alone, the strip as a whole
		 *	through disable(). Owns no strings, the word for the default apart
		 *
		 *	@param		{Object}		options					{ label, labelledby, steps, value, standard, disabled, id, wide, onChange }
		 *														- label       the group's name for a screen reader; or labelledby, the id of what names it
		 *														- steps       the steps, in order: a number or a string (a number is written -2, 0, +2), or { value, label, title, disabled }
		 *														- value       the chosen step's value; none is chosen where it matches no step
		 *														- standard    the value of the step that is the default
		 *														- disabled    the strip cannot be operated
		 *														- wide        the steps share the width, where the strip is otherwise as wide as they are
		 *														- onChange    called with the value and its index after a choice made by hand
		 *
		 *	@return		{Object}										{ el, get(), set( value ), disable( bool ), focus() }
		 */
		steps : function( options ) {

			const written = function( value ) {
				if( typeof value !== 'number' )
					return String( value );
				return value < 0 ? '−'+ Math.abs( value ) : ( value > 0 ? '+'+ value : '0' );
			};

			const steps = options.steps.map( function( step ) {
				return typeof step === 'object' && step !== null
					? { value : step.value, label : step.label ?? written( step.value ), title : step.title, disabled : step.disabled === true }
					: { value : step, label : written( step ), title : undefined, disabled : false };
			} );

			const group = dc.createElement('div');
			group.className = 'nino-admin-steps'+ ( options.wide === true ? ' nino-admin-steps--wide' : '' );
			group.setAttribute( 'role', 'radiogroup' );

			if( options.labelledby )
				group.setAttribute( 'aria-labelledby', options.labelledby );
			else if( options.label )
				group.setAttribute( 'aria-label', options.label );

			if( options.id )
				group.id = options.id;

			let chosen = -1;
			let off = options.disabled === true;

			const buttons = steps.map( function( step, at ) {

				const button = dc.createElement('button');
				button.type = 'button';
				button.className = 'nino-admin-steps-step';
				button.setAttribute( 'role', 'radio' );
				button.appendChild( Nino.adminUi.el( 'span', '', step.label ) );

				if( step.title )
					button.title = step.title;

				if( options.standard !== undefined && step.value === options.standard ) {
					button.dataset.standard = 'true';
					button.appendChild( Nino.adminUi.el( 'span', 'nino-admin-sr-only', ' ('+ Nino.adminUi.text('/_admin/common/label/default')+ ')' ) );
				}

				button.addEventListener( 'click', function() { choose( at, true ) } );
				button.addEventListener( 'keydown', function( ev ) {

					if( [ 'ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End' ].indexOf( ev.key ) === -1 )
						return;

					ev.preventDefault();

					const free = steps.map( function( other, index ) { return other.disabled === true ? -1 : index } ).filter( function( index ) { return index !== -1 } );
					if( free.length === 0 )
						return;

					const here = Math.max( 0, free.indexOf( at ) );
					let next = here;

					if( ev.key === 'ArrowRight' || ev.key === 'ArrowDown' )	next = ( here + 1 ) % free.length;
					if( ev.key === 'ArrowLeft' || ev.key === 'ArrowUp' )		next = ( here - 1 + free.length ) % free.length;
					if( ev.key === 'Home' )																	next = 0;
					if( ev.key === 'End' )																	next = free.length - 1;

					choose( free[next], true );
					buttons[free[next]].focus();
				} );

				group.appendChild( button );

				return button;
			} );

			const paint = function() {

				// The one tab stop is the chosen step - or, where none is or it is disabled, the first that can be chosen
				const stop = chosen !== -1 && steps[chosen].disabled === false ? chosen : steps.findIndex( function( step ) { return step.disabled === false } );

				buttons.forEach( function( button, at ) {
					button.setAttribute( 'aria-checked', at === chosen ? 'true' : 'false' );
					button.classList.toggle( 'is-active', at === chosen );
					button.tabIndex = at === stop ? 0 : -1;
					button.disabled = off === true || steps[at].disabled === true;
				} );

				if( off === true )
					group.setAttribute( 'aria-disabled', 'true' );
				else
					group.removeAttribute('aria-disabled');
			};

			const choose = function( at, byHand ) {

				if( off === true || steps[at].disabled === true )
					return;

				const changed = chosen !== at;
				chosen = at;
				paint();

				if( byHand === true && changed === true && typeof options.onChange === 'function' )
					options.onChange( steps[at].value, at );
			};

			chosen = steps.findIndex( function( step ) { return step.value === options.value } );
			paint();

			return {

				el : group,

				get : function() {
					return chosen === -1 ? null : steps[chosen].value;
				},

				set : function( value ) {
					chosen = steps.findIndex( function( step ) { return step.value === value } );
					paint();
				},

				disable : function( on ) {
					off = on === true;
					paint();
				},

				focus : function() {
					const stop = buttons.find( function( button ) { return button.tabIndex === 0 } );
					if( stop !== undefined )
						stop.focus();
				},
			};
		},

		/**
		 *	A split view: what a person sets on one side, the result on the
		 *	other - a column of controls and a preview beside it. Where the
		 *	view is too narrow for both (the container's, not the window's: the
		 *	pane is narrower than the screen by the rail) a switch of two
		 *	buttons, "Settings" and "Preview", shows one and keeps the other
		 *	in the page, so that neither is rebuilt by a switch and what was
		 *	typed is still there. Where there is room the switch is not drawn
		 *	and both stand side by side.
		 *
		 *	The preview is a page in an iframe (options.preview.src) or an
		 *	element the caller fills (an Element, or { slot }: a preview that is
		 *	a drawing, not a page). reload() loads the frame once more, or another
		 *	address into it, and touches nothing else (the frame says aria-busy until
		 *	it has loaded): the controls are not
		 *	redrawn, which is what a preview that has to be asked for a second time after
		 *	every change needs. Another address replaces the one the frame shows in the
		 *	history of the window - a preview that is reloaded after every change would
		 *	otherwise put a step into it for each, and the Back key would walk through
		 *	them before it came to the address of the panel. Only the project's own
		 *	origin is loaded into the frame - a relative address or one on this host; //host, \\host, other
		 *	schemes and other hosts are not: an address a person or a file could
		 *	have written is not allowed to run in the workbench's origin.
		 *	options.sandbox, where given, is the frame's sandbox.
		 *
		 *	With preview.width, or width( px ), the page is laid out as a browser that
		 *	wide would lay it out and scaled down as a whole into the room the pane has
		 *	(see scaleFrame()), so that a page of 1280px is seen on a pane of 600
		 *	with everything in proportion. With none the frame is as wide as the pane
		 *
		 *	@param		{Object}		options					{ id, settings, preview, view, sandbox, previewTitle, text, onView, onReload, onLoad }
		 *														- settings    the controls, an Element (more can be put into split.settings later)
		 *														- preview     { src, title, width } for a page, an Element for a preview the caller draws; width is the layout width of the page in px, 1 to 10000
		 *														- view        'settings' (the default) or 'preview', which one the narrow view starts on
		 *														- sandbox     the iframe's sandbox attribute, as a string
		 *														- tools       an Element for the bar over the preview, beside its title (split.tools is the place, where it is made later)
		 *														- text        { settings, preview, reload, view } to say the four words differently
		 *														- onView      called with the view after a switch
		 *														- onReload    called when the preview is asked to load once more, by the button or by reload()
		 *														- onLoad      called with the frame when the page in it has loaded
		 *
		 *	@return		{Object}										{ el, settings, preview, frame, tools, view(), show( view ), reload( [src] ), width( [px] ), words( { settings, preview } ) } - width( px ) lays the page out at px and scales it into the pane, width( null ) takes it back to the pane's own width, width() answers the one that is set (null for none); a number that is not 1 to 10000 is none. With no preview given the view is the settings alone: no switch, no bar, and preview, frame and tools are null
		 */
		split : function( options ) {

			const prefix	= options.id || 'nino-admin-split-'+ ( ++serial );
			const text		= options.text || {};
			const word		= function( key ) { return text[key] ?? Nino.adminUi.text( '/_admin/common/split/'+ key ) };

			const root = dc.createElement('div');
			root.className = 'nino-admin-split';
			root.dataset.view = options.view === 'preview' ? 'preview' : 'settings';

			const settings = dc.createElement('div');
			settings.className = 'nino-admin-split-settings';
			settings.id = prefix+ '-settings';

			if( options.settings )
				settings.appendChild( options.settings );

			const pane = dc.createElement('div');
			pane.className = 'nino-admin-split-preview';
			pane.id = prefix+ '-preview';

			const bar = dc.createElement('div');
			bar.className = 'nino-admin-split-bar';

			// The place in the bar for what the panel wants beside the title: the widths of the preview, a line of advice
			const tools = dc.createElement('div');
			tools.className = 'nino-admin-split-tools';

			if( options.tools )
				tools.appendChild( options.tools );

			const preview = options.preview;
			// An element is a slot even where it has a src of its own - an image, a frame the caller made
			const isPage = typeof preview === 'object' && preview !== null && preview.nodeType !== 1 && typeof preview.src === 'string';
			let frame = null;
			let port = null;
			let layout = null;

			// The preview is a page of the project itself, so only the project's own origin is
			// loaded into the frame: a relative address, or an absolute one on this host. What
			// the browser takes for a scheme ignores the tabs, the line breaks and the other
			// control characters in it (java<TAB>script: runs) and takes a backslash for a
			// slash (/\evil is //evil), so both are read the way it reads them
			const allowed = function( src ) {

				const plain = String( src ).replace( /[\u0000-\u0020]/g, '' ).replace( /\\/g, '/' );

				if( /^[a-z][a-z0-9+.-]*:/i.test( plain ) === false )
					return plain.indexOf('//') !== 0;

				// The origin as the browser reads it: another scheme is another origin, and an
				// address it cannot read is none of ours
				try {
					return /^https?:\/\//i.test( plain ) === true && new URL( plain, wn.location.href ).origin === wn.location.origin;
				} catch( e ) {
					return false;
				}
			};

			// The address the panel asked the frame to show last: the page that is loaded once more
			let shown = '';

			const reload = function( src ) {

				if( frame !== null ) {

					const next = typeof src === 'string' ? src : shown;

					// A frame that was refused its first address has nothing to load once more
					if( next !== '' && allowed( next ) === true ) {

						// Set to the address it shows, an address with a # is not loaded: the browser
						// takes it for a move within the page, so the frame reloads itself - when it
						// shows exactly this page. After a click in the preview it shows another, and
						// one of another origin cannot be asked: either way the panel's address is
						// set. aria-busy ends with the next load
						let same = false;

						try {
							same = frame.contentWindow.location.href === new URL( next, wn.location.href ).href;
						} catch( e ) {}

						frame.setAttribute( 'aria-busy', 'true' );
						shown = next;

						if( same === true )
							frame.contentWindow.location.reload();
						else {

							// The address takes the place of the one the frame shows: a src
							// set to another address is a step of its own in the history of the window, one for
							// every change of the panel. A frame that cannot be asked (another
							// origin, none yet) is given the attribute
							try {
								frame.contentWindow.location.replace( new URL( next, wn.location.href ).href );
							} catch( e ) {
								frame.setAttribute( 'src', next );
							}
						}
					}
				}

				if( typeof options.onReload === 'function' )
					options.onReload();
			};

			const title = Nino.adminUi.el( 'span', 'nino-admin-split-title', word('preview') );
			const refresh = Nino.adminUi.button( '⟳', function() { reload() }, { className : 'nino-admin-btn--small', name : word('reload') } );

			bar.appendChild( title );
			bar.appendChild( tools );
			bar.appendChild( refresh );
			pane.appendChild( bar );

			if( isPage === true ) {

				frame = dc.createElement('iframe');
				frame.className = 'nino-admin-split-frame';
				frame.title = options.previewTitle ?? preview.title ?? word('preview');

				if( typeof options.sandbox === 'string' )
					frame.setAttribute( 'sandbox', options.sandbox );

				frame.addEventListener( 'load', function() {
					frame.removeAttribute('aria-busy');
					if( typeof options.onLoad === 'function' )
						options.onLoad( frame );
				} );

				// The frame stands in a box of its own, which it is scaled inside where the page
				// has a layout width: moving it there later would reload it
				port = dc.createElement('div');
				port.className = 'nino-admin-split-port';
				port.appendChild( frame );
				pane.appendChild( port );

				if( allowed( preview.src ) === true ) {
					frame.setAttribute( 'aria-busy', 'true' );
					frame.setAttribute( 'src', preview.src );
					shown = preview.src;
				}

			} else if( typeof preview === 'object' && preview !== null ) {

				const slot = preview.nodeType === 1 ? preview : preview.slot;
				if( typeof slot === 'object' && slot !== null )
					pane.appendChild( slot );
			}

			const switchBar = dc.createElement('div');
			switchBar.className = 'nino-admin-steps nino-admin-steps--wide nino-admin-split-switch';
			switchBar.setAttribute( 'role', 'group' );
			switchBar.setAttribute( 'aria-label', word('view') );

			const buttons = {
				settings	: Nino.adminUi.button( word('settings'), null, { className : 'nino-admin-steps-step' } ),
				preview		: Nino.adminUi.button( word('preview'), null, { className : 'nino-admin-steps-step' } ),
			};

			switchBar.appendChild( buttons.settings );
			switchBar.appendChild( buttons.preview );

			buttons.settings.setAttribute( 'aria-controls', settings.id );
			buttons.preview.setAttribute( 'aria-controls', pane.id );

			const paint = Nino.adminUi.buttonRow( buttons, root.dataset.view, function( view ) {
				root.dataset.view = view;
				if( typeof options.onView === 'function' )
					options.onView( view );
			} );

			// The grid is a box of its own inside the root: the root is the container the
			// narrow rules ask about, and a container's own box is not what they style
			const grid = dc.createElement('div');
			grid.className = 'nino-admin-split-grid';
			grid.appendChild( settings );
			grid.appendChild( pane );

			// The page at a layout width, scaled into the pane; none lets the frame be as wide as the pane
			const width = function( px ) {

				if( arguments.length === 0 || frame === null )
					return layout;

				layout = typeof px === 'number' && Number.isFinite( px ) === true && px >= 1 && px <= 10000 ? Math.round( px ) : null;

				port.classList.toggle( 'nino-admin-split-port--scaled', layout !== null );

				if( layout !== null )
					Nino.adminUi.scaleFrame( frame, port, layout );
				else {

					if( port._ninoScaleObserver )
						port._ninoScaleObserver.disconnect();
					port._ninoScaleObserver = null;

					[ 'width', 'height', 'transform', 'left', 'top' ].forEach( function( name ) { frame.style[name] = '' } );
				}

				return layout;
			};

			if( isPage === true && preview.width !== undefined )
				width( preview.width );

			// With nothing to preview there is nothing to switch to, to reload or to name
			const solo = isPage === false && ( typeof preview !== 'object' || preview === null );

			if( solo === true ) {
				root.className += ' nino-admin-split--solo';
				root.dataset.view = 'settings';
				grid.removeChild( pane );
			} else {
				root.appendChild( switchBar );
			}

			root.appendChild( grid );

			return {

				el : root,
				settings : settings,
				preview : solo === true ? null : pane,
				frame : frame,
				tools : solo === true ? null : tools,

				// The words of the switch and of the bar, said after the build - the frame is not touched
				words : function( given ) {

					if( given.settings !== undefined )
						buttons.settings.textContent = given.settings;

					if( given.preview !== undefined ) {
						buttons.preview.textContent = given.preview;
						title.textContent = given.preview;
					}
				},

				view : function() {
					return root.dataset.view;
				},

				show : function( view ) {

					if( solo === true )
						return;

					root.dataset.view = view === 'preview' ? 'preview' : 'settings';
					paint( root.dataset.view );
				},

				reload : reload,
				width : width,
			};
		},

		/**
		 *	The loop that keeps a preview in step with a draft that is not saved: the draft is sent once the hand has rested,
		 *	one request at a time - what changes while one is out is sent in the next, once -, the token is asked for again
		 *	before it runs out, and the frame of a split view (split()) is loaded with the page when an answer holds. A failure
		 *	leaves the last picture where it is and is told to the caller; the draft is the person's, a failed request takes nothing
		 *	of it. What the draft is and how it is sent is the caller's: send() does it, with the request of its own panel.
		 *
		 *	The answer of send() is { token, query, ttl } - the token, the query that makes a request for the page ask for it
		 *	(\Nino\Preview::QUERY and the token) and how many seconds it holds. The token goes to the next call of send(), so that
		 *	the server renews it instead of making a new one; it is counted from the answer on the clock of this page, so that a
		 *	clock that is off cannot ask for ever. Owns no strings: what an error or a renewal says is the caller's
		 *
		 *	@param		{Object}		options					{ split, send, address, widths, quiet, grace, onError, onUnavailable, onRenew, onUpdate }
		 *														- split         a split() with a page: reload( src ) and width( px ) are what is called
		 *														- send          send( token, done ) asks the server for the draft; done( status, response ) is its answer
		 *														- address       address( query ) is the address of the page the frame shows, with the query that asks for the draft
		 *														- widths        { name : px } the widths of the preview, mobile, tablet and desktop by default
		 *														- quiet         how long the hand rests before the draft is sent, in milliseconds, 150
		 *														- grace         how long before a token runs out - in milliseconds - a new one is asked for, 5000
		 *														- onError       called with ( status, response ) after a request that failed
		 *														- onUnavailable called after an answer that is no draft: the last picture stays
		 *														- onRenew       called when the token had run out and the draft is asked for again
		 *														- onUpdate      called after an answer that held, the frame loaded
		 *
		 *	@return		{Object}										{ schedule( [quiet] ), flush( [then] ), reload(), width( name ), token(), stop() } - flush( then ) calls then( true ) after the last request of a run that held and then( false ) where one failed
		 */
		previewFrame : function( options ) {

			const quiet		= Number.isInteger( options.quiet ) === true && options.quiet >= 0 ? options.quiet : 150;
			const grace		= Number.isInteger( options.grace ) === true && options.grace >= 0 ? options.grace : 5000;
			const widths	= options.widths ?? { mobile : 390, tablet : 768, desktop : 1280 };
			const tell		= function( name, ...args ) {
				if( typeof options[name] === 'function' )
					options[name]( ...args );
			};

			let token = '';
			let query = '';
			let until = 0;
			let timer = null;
			let inFlight = false;
			let again = false;
			let stopped = false;
			let waiting = [];
			let width = '';

			const finish = function( ok ) {
				const calls = waiting;
				waiting = [];
				calls.forEach( function( call ) { call( ok ) } );
			};

			const load = function() {

				if( stopped === true || query === '' )
					return;

				// A token that has run out draws the page as the website has it, and says nothing: the draft is asked for again, which gives a new one
				if( until > 0 && Date.now() >= until ) {
					tell('onRenew');
					flush();
					return;
				}

				options.split.reload( options.address( query ) );
			};

			const flush = function( then ) {

				if( typeof then === 'function' )
					waiting.push( then );

				// A wait that is still out is this send: it is taken back, so that a change and an at once make one request
				wn.clearTimeout( timer );

				if( stopped === true ) {
					finish( false );
					return;
				}

				if( inFlight === true ) {
					again = true;
					return;
				}

				inFlight = true;
				again = false;

				options.send( token, function( status, response ) {

					inFlight = false;

					if( stopped === true ) {
						finish( false );
						return;
					}

					if( status !== 200 ) {
						again = false;
						tell( 'onError', status, response );
						finish( false );
						return;
					}

					// An answer that is no draft is no page to load: the last picture stays
					if( response === null || typeof response !== 'object' || typeof response.token !== 'string' || typeof response.query !== 'string' || typeof response.ttl !== 'number' ) {
						again = false;
						tell('onUnavailable');
						finish( false );
						return;
					}

					token = response.token;
					query = response.query;
					until = Date.now() + response.ttl * 1000 - grace;

					if( again === true ) {
						flush();
						return;
					}

					load();
					tell( 'onUpdate', response );
					finish( true );
				} );
			};

			return {

				// The hand has moved: the draft is sent when it rests - longer where the hand types a text
				schedule : function( rest ) {
					wn.clearTimeout( timer );
					timer = wn.setTimeout( function() { flush() }, rest ?? quiet );
				},

				flush : flush,
				reload : load,
				token : function() { return token },

				// The page is laid out at the width of that name and scaled into the pane; a name that is none gives the pane its own width back
				width : function( name ) {
					width = Object.prototype.hasOwnProperty.call( widths, name ) === true ? name : '';
					return options.split.width( width === '' ? null : widths[width] );
				},

				// The panel is left: what is out is not answered, nothing is sent
				stop : function() {
					stopped = true;
					wn.clearTimeout( timer );
				},
			};
		},

		/**
		 *	The question a refused save asks - another tab or another person wrote what this one was about to write: Overwrite
		 *	does it once more over what is there now, Reload drops the work of this tab and shows what is there - after a question of its
		 *	own, since it cannot be taken back - and Cancel leaves everything as it is. Escape is Cancel, and the focus goes back
		 *	to the opener. The words are /_admin/common/conflict/*, and the caller's where it gives them
		 *
		 *	@param		{Object}		options					{ message, onOverwrite, onReload, onCancel, opener, text }
		 *														- message     what the server said of the refusal, usually Nino.adminUi.api.errorText( status, response )
		 *														- onOverwrite called to write over what is there; the caller sends the save again with the state the refusal was about
		 *														- onReload    called after the second question was answered with yes: the caller drops its work and loads what the server holds
		 *														- onCancel    called when nothing is done: Cancel, Escape, or no to the second question
		 *														- opener      what gets the focus back, see choiceDialog()
		 *														- text        { title, overwrite, reload, reloadTitle, reloadMessage, cancel }
		 *
		 *	@return		{boolean}										false when a question is already open and this one was not asked
		 */
		conflict : function( options ) {

			const text = options.text || {};
			const word = function( key, fill ) { return text[key] ?? Nino.adminUi.text( fill ) };
			const call = function( name ) {
				if( typeof options[name] === 'function' )
					options[name]();
			};

			return Nino.adminUi.choiceDialog( {
				title		: word( 'title', '/_admin/common/conflict/title' ),
				message	: options.message,
				opener	: options.opener,
				choices	: [
					{ value : 'overwrite', label : word( 'overwrite', '/_admin/common/conflict/overwrite' ), kind : 'danger' },
					{ value : 'cancel', label : word( 'cancel', '/_admin/common/label/cancel' ), kind : 'secondary' },
					{ value : 'reload', label : word( 'reload', '/_admin/common/conflict/reload' ), kind : 'secondary' },
				],
				onChoose : function( value ) {

					if( value === 'overwrite' ) {
						call('onOverwrite');
						return;
					}

					if( value !== 'reload' ) {
						call('onCancel');
						return;
					}

					Nino.adminUi.confirm( 'destructive', {
						title		: word( 'reloadTitle', '/_admin/common/conflict/reload-title' ),
						message	: word( 'reloadMessage', '/_admin/common/conflict/reload-message' ),
						yes			: word( 'reload', '/_admin/common/conflict/reload' ),
						no			: word( 'cancel', '/_admin/common/label/cancel' ),
						kind		: 'danger',
						opener	: options.opener,
						onChoose : function( ok ) { call( ok === true ? 'onReload' : 'onCancel' ) },
					} );
				},
			} );
		},

		/**
		 *	The bar at the foot of a screen that saves what is on it as a whole: the state - "● Changed" or "✓ Saved" -, the
		 *	status line (status()) that says what the last action did, the buttons of the caller (undo, redo...), "Restore the
		 *	last state" once there is one and nothing unsaved, and Save. Save is pressed once at a time: while the save is on its way
		 *	it is disabled, and where the caller says what keeps it from being saved, the line says it and Save waits. The
		 *	caller says what is the case, with set(); the bar draws it and does nothing of its own accord. The first change of a
	 *	saved screen is said once on the line - a status, which a screen reader reads out - and not again while the changes stand; the
	 *	sentence is for the screen reader - the stylesheet keeps it off the screen - and goes after four seconds, the badge says the state.
	 *	What the caller says (an error, a reason, words of its own) stays on the line until the caller changes it. A save that
	 *	ends with done( false ) leaves the line as the caller left it: where it still says "Saving" it is back to "unsaved
	 *	changes", and a caller that has an error to say says it before it ends the save
		 *
		 *	press() is the Save of the keyboard - a panel hangs it on Ctrl+S through the shell's dirty registry (entry() gives
		 *	the entry of that registry, with the bar as the one the marker is drawn in). Where the registry is there, set() tells it
		 *	that the state changed. The words are /_admin/common/savebar/*, and the caller's where it gives them
		 *
		 *	@param		{Object}		options					{ id, text, buttons, restore, onSave, onRestore }
		 *														- id          the beginning of the ids it makes
		 *														- text        { changed, saved, save, restore, restoreHint } to say the words differently
		 *														- buttons     [ { key, label, name, onPress } ] put between the line and Restore, in this order
		 *														- restore     false for a bar that has no last state to go back to
		 *														- onSave      onSave( done ) saves, and says with done( true ) or done( false ) how it ended - on every way it can end
		 *														- onRestore   onRestore( opener ) is called by Restore, which is on the screen only where set() says there is a last state
		 *
		 *	@return		{Object}										{ el, line, state, save, restore, buttons, set( { dirty, blocked, restorable, calm } ), press( [done] ), entry( [extra] ) } - buttons is key => element, line the status() control. calm is a third, quiet state for a screen whose saved state is something else than "saved" ("Applied"): its word, shown with a check mark where nothing is changed; '' takes it back
		 */
		saveBar : function( options ) {

			const prefix	= options.id || 'nino-admin-savebar-'+ ( ++serial );
			const text		= options.text || {};
			const word		= function( key, fill ) { return text[key] ?? Nino.adminUi.text( fill ) };

			const bar			= Nino.adminUi.actionBar( Nino.adminUi.el( 'div', 'nino-admin-savebar' ) );
			bar.id				= prefix;

			const state		= Nino.adminUi.el( 'span', 'nino-admin-badge nino-admin-savebar-state' );
			const line		= Nino.adminUi.status( Nino.adminUi.el('p') );
			const buttons	= {};

			state.id = prefix+ '-state';
			bar.appendChild( state );
			bar.appendChild( line.element );

			( Array.isArray( options.buttons ) === true ? options.buttons : [] ).forEach( function( given ) {

				const button = Nino.adminUi.button( given.label, given.onPress, { name : given.name, id : prefix+ '-'+ given.key } );

				buttons[given.key] = button;
				bar.appendChild( button );
			} );

			const restore = Nino.adminUi.button( word( 'restore', '/_admin/common/savebar/restore' ), function() {
				if( typeof options.onRestore === 'function' )
					options.onRestore( restore );
			}, { id : prefix+ '-restore' } );

			const save = Nino.adminUi.button( word( 'save', '/_admin/common/savebar/save' ), function() { press() }, { className : 'nino-admin-btn-primary', id : prefix+ '-save' } );

			restore.hidden = true;

			if( options.restore !== false )
				bar.appendChild( restore );

			bar.appendChild( save );

			let dirty = false;
			let blocked = '';
			let said = '';
			let calm = '';
			let restorable = false;
			let busy = false;
			let typed = false;
			let heard = null;

			// The line says "unsaved changes" once, for the screen reader (the stylesheet keeps it off the screen: the badge shows
			// the state), and the sentence goes after SAID_FOR where nobody has put another in its place
			const tell = function() {

				wn.clearTimeout( heard );
				line.dirty();
				line.element.dataset.once = 'dirty';

				const words = line.element.textContent;

				heard = wn.setTimeout( function() {

					delete line.element.dataset.once;

					if( line.state === 'dirty' && line.element.textContent === words )
						line.idle();
				}, SAID_FOR );
			};

			const paint = function() {

				state.dataset.state = dirty === true ? 'warn' : ( calm !== '' ? 'muted' : 'ok' );
				state.textContent = dirty === true ? '\u25CF '+ word( 'changed', '/_admin/common/savebar/changed' ) : '\u2713 '+ ( calm !== '' ? calm : word( 'saved', '/_admin/common/savebar/saved' ) );
				save.disabled = busy === true || dirty === false || blocked !== '';
				restore.hidden = restorable === false;
				restore.disabled = busy === true || dirty === true;
				restore.title = dirty === true ? word( 'restoreHint', '/_admin/common/savebar/restore-hint' ) : '';

				// What keeps the draft from being saved is said where the person is looking; it goes when the cause does
				if( blocked !== '' && said !== blocked ) {
					said = blocked;
					line.fail( blocked );
				}
				else if( blocked === '' && said !== '' ) {
					said = '';

					if( dirty === true )
						tell();
					else
						line.idle();
				}
			};

			const refresh = function() {
				if( typeof shell().dirty === 'object' && shell().dirty !== null && typeof shell().dirty.refresh === 'function' )
					shell().dirty.refresh();
			};

			const press = function( done ) {

				if( busy === true || dirty === false || blocked !== '' || typeof options.onSave !== 'function' ) {
					if( typeof done === 'function' )
						done( false );
					return;
				}

				busy = true;
				typed = false;
				line.saving();
				paint();

				options.onSave( function( ok ) {

					busy = false;

					// What was changed while the save was on its way is not in what was sent: it stays a change
					if( ok === true ) {
						dirty = typed;

						if( typed === true )
							tell();
						else
							line.saved();
					}
					else if( line.state === 'saving' )
						tell();

					paint();
					refresh();

					if( typeof done === 'function' )
						done( ok === true );
				} );
			};

			paint();

			return {

				el : bar,
				line : line,
				state : state,
				save : save,
				restore : restore,
				buttons : buttons,
				press : press,

				// What is the case now: the screen holds changes, something keeps it from being saved (a sentence, '' for nothing), there is a last state to go back to
				set : function( now ) {

					if( typeof now.dirty === 'boolean' ) {

						// The first change of a saved screen is said, once: the line is a status that is read out
						if( now.dirty === true && dirty === false && busy === false && blocked === '' && ( typeof now.blocked !== 'string' || now.blocked === '' ) && line.state !== 'error' )
							tell();

						// What the line said of changes is not true of a screen that is back to what is saved
						if( now.dirty === false && dirty === true && busy === false && line.state === 'dirty' )
							line.idle();

						dirty = now.dirty;
						typed = busy === true && now.dirty === true ? true : typed;
					}

					if( typeof now.calm === 'string' )
						calm = now.calm;

					if( typeof now.blocked === 'string' )
						blocked = now.blocked;

					if( typeof now.restorable === 'boolean' )
						restorable = now.restorable;

					paint();
					refresh();
				},

				// The entry of the shell's registry of unsaved input (Nino.admin.dirty.register()), with what else the caller adds to it
				entry : function( extra ) {
					return Object.assign( {
						isDirty : function() { return dirty },
						save : function( done ) { press( done ) },
						bar : function() { return bar },
					}, extra || {} );
				},
			};
		},

		/**
		 *	A tree: entries one under another, each a row with a label, a quiet second line of
		 *	detail, a sign that carries a hint, and buttons of its own - the components of a page,
		 *	the folders of a store. The caller gives the entries as data and draws nothing itself;
		 *	the tree is the pattern of the same name: role tree, treeitem and group, aria-level,
		 *	aria-expanded on the entries that have children, aria-selected, and exactly one entry
		 *	in the tab order (the one that last had the focus, else the chosen one, else the first),
		 *	the others reached by the keys.
		 *
		 *	Up and Down walk the entries that can be seen, Right opens a closed entry and goes to the
		 *	first child of an open one, Left closes an open entry and goes to the parent of a closed
		 *	one, Home and End go to the first and the last, Enter and Space choose, a letter goes to
		 *	the next entry whose label begins with it (wrapping). A click on the arrow opens or closes,
		 *	a click on the row chooses. The buttons of a row are in the tab order only while the row
		 *	is the one that has the stop, so that a tree of fifty rows is one stop and not two hundred;
		 *	Tab from the row reaches them, a mouse reaches all of them. A locked entry can be walked to,
		 *	opened and closed but not chosen, and says so in a word, seen and in its description (not by
		 *	aria-disabled, which would take its buttons from a screen reader too); what its buttons may do
		 *	is the caller's, through disabled
		 *
		 *	The tree does not own the data and writes no address: the caller binds the chosen entry
		 *	to its own address, as levels() and accordion() leave theirs to the panel. What it keeps
		 *	is what the eye needs between two drawings - which entries are open, which is chosen,
		 *	where the stop is - and update() gives them back where the ids stay: an id the tree has
		 *	seen keeps its open state (item.open is read for an id it has not), the stop and the focus
		 *	stay on their entry and, where the focus was on a button of it that is still there and can be
		 *	pressed, on that button, else on the row. An id follows the component, not its place: where an
		 *	entry is gone the stop and the focus go to its next sibling, else the previous one, else its
		 *	parent. A person's move of the stop, and this one, is told through onFocus; select(), focus() and
		 *	update() otherwise call nobody back. An update with no entries leaves the focus nowhere. Owns no strings: the labels,
		 *	the details, the hints and the names of the buttons are the caller's, the words for a locked
		 *	and a marked entry are fills
		 *
		 *	@param		{Object}		options					{ id, label, labelledby, items, selected, text, onSelect, onToggle, onAction, onFocus }
		 *														- id          the beginning of the ids it makes, a token (a space in it becomes a hyphen)
		 *														- label       the tree's name for a screen reader; or labelledby, the id of what names it
		 *														- items       [ { id, label, detail, children, open, marked, locked, hint, actions } ] - id a string, unique in the tree; detail the quiet second line; children the same shape; open whether it is open when the tree first sees it; marked says that the entry is the one shown elsewhere (the preview); locked as above; hint { text, state } - a sign and its sentence under the label, state warn, error or muted; actions [ { key, label, name, disabled, danger } ] - label the glyph, name what a screen reader and a title say, danger draws it as one that destroys; locked and marked show as words beside the row
		 *														- selected    the id chosen at the start
		 *														- text        { locked, marked } to say the words differently
		 *														- onSelect    called with the id and the item after a choice made by hand, also of the entry that is chosen already
		 *														- onToggle    called with the id, whether it is open or closed after the move, and the item after a hand opened or closed it
		 *														- onAction    called with the key of the button, the id and the item after a press
		 *														- onFocus     called with the id and the item when the stop moves to another entry, by a hand or because its entry is gone
		 *
		 *	@return		{Object}										{ el, update( items ), select( id ), focus( [id] ), get() } - select() chooses an entry (not a locked one: it answers false), opens what is above it and scrolls it into view, null chooses none; focus() puts the focus on the entry (the one with the stop by default); both answer whether the id is there; get() is { selected, stop, open }
		 */
		tree : function( options ) {

			// The id is the beginning of the ids it makes, which are listed in aria-labelledby: a token, with no space in it
			const prefix	= typeof options.id === 'string' && options.id !== '' ? options.id.replace( /\s+/g, '-' ) : 'nino-admin-tree-'+ ( ++serial );
			const text		= options.text || {};
			const word		= function( key, fill ) { return text[key] ?? Nino.adminUi.text( fill ) };

			const root = Nino.adminUi.el( 'ul', 'nino-admin-tree' );
			root.id = prefix;
			root.setAttribute( 'role', 'tree' );

			if( typeof options.labelledby === 'string' && options.labelledby !== '' )
				root.setAttribute( 'aria-labelledby', options.labelledby );
			else if( typeof options.label === 'string' )
				root.setAttribute( 'aria-label', options.label );

			let order = [];
			let byId = new Map();
			let parts = new Map();
			let drawn = 0;

			const open = new Set();
			let selected = typeof options.selected === 'string' ? options.selected : null;
			let stop = null;

			// What stands for an element of the tree: the entry it is of, and which piece of the row it is
			const locate = function( target ) {

				for( let node = target; node !== null && node !== undefined; node = node.parentNode ) {

					const found = parts.get( node );

					if( found !== undefined )
						return found;

					if( node === root )
						break;
				}

				return null;
			};

			const inside = function( node ) {

				for( ; node !== null && node !== undefined; node = node.parentNode )
					if( node === root )
						return true;

				return false;
			};

			const shown = function( entry ) {

				for( let up = entry.parent; up !== null; up = up.parent )
					if( open.has( up.id ) === false )
						return false;

				return true;
			};

			const visible = function() {
				return order.filter( shown );
			};

			const paintOpen = function( entry ) {

				if( entry.group === null )
					return;

				const on = open.has( entry.id );

				entry.li.setAttribute( 'aria-expanded', on === true ? 'true' : 'false' );
				entry.toggle.textContent = on === true ? '▾' : '▸';
				entry.group.hidden = on === false;
			};

			const paintSelected = function() {
				order.forEach( function( entry ) {
					entry.li.setAttribute( 'aria-selected', entry.id === selected ? 'true' : 'false' );
					entry.row.classList.toggle( 'is-selected', entry.id === selected );
				} );
			};

			// The one entry in the tab order, and with it the buttons of its row
			const setStop = function( entry, told ) {

				const moved = stop !== entry;

				if( stop !== null && stop !== entry ) {
					stop.li.tabIndex = -1;
					stop.buttons.forEach( function( button ) { button.tabIndex = -1 } );
				}

				stop = entry;

				if( entry === null )
					return;

				entry.li.tabIndex = 0;
				entry.buttons.forEach( function( button ) { button.tabIndex = 0 } );

				if( moved === true && told === true && typeof options.onFocus === 'function' )
					options.onFocus( entry.id, entry.item );
			};

			const setOpen = function( entry, on, told ) {

				if( entry.group === null || open.has( entry.id ) === on )
					return;

				const held = inside( dc.activeElement );

				if( on === true )
					open.add( entry.id );
				else
					open.delete( entry.id );

				paintOpen( entry );

				// A closed entry hides what is under it: the stop, and the focus with it, stay with the entry
				if( stop !== null && shown( stop ) === false ) {
					setStop( entry );

					if( held === true )
						go( entry );
				}

				if( told === true && typeof options.onToggle === 'function' )
					options.onToggle( entry.id, on, entry.item );
			};

			const choose = function( entry ) {

				if( entry.item.locked === true )
					return;

				selected = entry.id;
				paintSelected();

				if( typeof options.onSelect === 'function' )
					options.onSelect( entry.id, entry.item );
			};

			// What is above an entry is opened for it, without a word to the caller
			const reveal = function( entry ) {
				for( let up = entry.parent; up !== null; up = up.parent )
					setOpen( up, true, false );
			};

			// The entry holds the whole open subtree, so a focus that scrolls would leave the row out of sight: the row is scrolled to
			const show = function( entry, target ) {
				target.focus( { preventScroll : true } );
				entry.row.scrollIntoView( { block : 'nearest' } );
			};

			const go = function( entry ) {
				setStop( entry, true );
				show( entry, entry.li );
			};

			const make = function( list, parent, level, into ) {

				list.forEach( function( item, at ) {

					const id = String( item.id );
					const n = ++drawn;
					const kids = Array.isArray( item.children ) === true ? item.children : [];
					const hint = typeof item.hint === 'object' && item.hint !== null && typeof item.hint.text === 'string' && item.hint.text !== '' ? item.hint : null;
					const locked = item.locked === true;
					const marked = item.marked === true;
					const detail = typeof item.detail === 'string' ? item.detail : '';

					const li = Nino.adminUi.el( 'li', 'nino-admin-tree-item' );
					const row = Nino.adminUi.el( 'div', 'nino-admin-tree-row' );
					const toggle = Nino.adminUi.el( 'span', 'nino-admin-tree-toggle' );
					const label = Nino.adminUi.el( 'span', 'nino-admin-tree-label', String( item.label ?? '' ) );

					const entry = { id : id, item : item, parent : parent, level : level, li : li, row : row, toggle : toggle, label : label, group : null, buttons : [], keyed : new Map() };

					li.setAttribute( 'role', 'treeitem' );
					li.setAttribute( 'aria-level', String( level ) );
					li.setAttribute( 'aria-setsize', String( list.length ) );
					li.setAttribute( 'aria-posinset', String( at + 1 ) );
					li.setAttribute( 'aria-labelledby', prefix+ '-'+ n+ '-label' );
					li.dataset.id = id;
					li.tabIndex = -1;

					li.dataset.locked = locked === true ? 'true' : 'false';
					li.dataset.marked = marked === true ? 'true' : 'false';

					// The indent is the level's: a rule of the stylesheet cannot count levels
					row.style.paddingInlineStart = ( .3 + ( level - 1 ) * 1.1 ).toFixed(2)+ 'rem';

					label.id = prefix+ '-'+ n+ '-label';
					label.title = label.textContent;
					toggle.setAttribute( 'aria-hidden', 'true' );
					row.appendChild( toggle );

					// The label, and under it - where there is one - the sentence of the hint
					const column = Nino.adminUi.el( 'span', 'nino-admin-tree-text' );
					column.appendChild( label );
					row.appendChild( column );

					const says = [];

					if( detail !== '' ) {
						const quiet = Nino.adminUi.el( 'span', 'nino-admin-tree-detail', detail );
						quiet.title = detail;
						quiet.setAttribute( 'aria-hidden', 'true' );
						row.appendChild( quiet );
						says.push( detail );
					}

					// Words, not signs: the state of an entry is said where the eye is, quietly, and also in the description
					[ [ marked, 'marked' ], [ locked, 'locked' ] ].forEach( function( state ) {

						if( state[0] !== true )
							return;

						const said = word( state[1], '/_admin/common/tree/'+ state[1] );
						const chip = Nino.adminUi.el( 'span', 'nino-admin-tree-state', said );
						chip.dataset.state = state[1];
						chip.setAttribute( 'aria-hidden', 'true' );
						row.appendChild( chip );
						says.push( said );
					} );

					if( hint !== null ) {
						const line = Nino.adminUi.el( 'span', 'nino-admin-tree-hint' );
						const sign = Nino.adminUi.el( 'span', 'nino-admin-tree-sign', '!' );
						sign.dataset.state = [ 'error', 'muted' ].indexOf( hint.state ) !== -1 ? hint.state : 'warn';
						line.setAttribute( 'aria-hidden', 'true' );
						line.appendChild( sign );
						line.appendChild( Nino.adminUi.el( 'span', 'nino-admin-tree-hint-text', hint.text ) );
						line.title = hint.text;
						column.appendChild( line );
						says.push( hint.text );
					}

					// A screen reader reads the name, then what the row says besides
					if( says.length > 0 ) {
						const info = Nino.adminUi.el( 'span', 'nino-admin-sr-only', says.join( ', ' ) );
						info.id = prefix+ '-'+ n+ '-info';
						li.setAttribute( 'aria-describedby', info.id );
						row.appendChild( info );
					}

					const actions = Array.isArray( item.actions ) === true ? item.actions : [];

					if( actions.length > 0 ) {

						const bar = Nino.adminUi.el( 'span', 'nino-admin-tree-actions' );

						actions.forEach( function( action ) {

							const button = Nino.adminUi.button( String( action.label ?? '' ), function() {
								if( typeof options.onAction === 'function' )
									options.onAction( action.key, id, item );
							}, { className : 'nino-admin-tree-action'+ ( action.danger === true ? ' nino-admin-tree-action--danger' : '' ), name : action.name, disabled : action.disabled === true } );

							button.tabIndex = -1;
							entry.buttons.push( button );
							entry.keyed.set( action.key, button );
							parts.set( button, { entry : entry, part : 'action' } );
							bar.appendChild( button );
						} );

						parts.set( bar, { entry : entry, part : 'actions' } );
						row.appendChild( bar );
					}

					li.appendChild( row );
					into.appendChild( li );

					parts.set( li, { entry : entry, part : 'item' } );
					parts.set( toggle, { entry : entry, part : 'toggle' } );
					order.push( entry );
					byId.set( id, entry );

					if( kids.length > 0 ) {
						entry.group = Nino.adminUi.el( 'ul', 'nino-admin-tree-group' );
						entry.group.setAttribute( 'role', 'group' );
						li.appendChild( entry.group );
						make( kids, entry, level + 1, entry.group );
					}
				} );
			};

			const draw = function( list ) {

				const held = inside( dc.activeElement ) === true ? keyOf( dc.activeElement ) : null;
				const known = new Set( byId.keys() );
				const was = stop === null ? null : stop.id;

				// Where the stop's entry is gone the stop goes to its next sibling, else the previous one, else its parent
				const near = [];

				if( stop !== null ) {
					const siblings = order.filter( function( other ) { return other.parent === stop.parent } );
					const at = siblings.indexOf( stop );
					[ siblings[at + 1], siblings[at - 1], stop.parent ].forEach( function( other ) { if( other !== undefined && other !== null ) near.push( other.id ) } );
				}

				while( root.firstChild !== null )
					root.removeChild( root.firstChild );

				order = [];
				byId = new Map();
				parts = new Map();
				stop = null;

				make( list, null, 1, root );

				// An id the tree has seen keeps what the hand did; one it has not takes what the item says
				order.forEach( function( entry ) {
					if( entry.group !== null && known.has( entry.id ) === false && entry.item.open === true )
						open.add( entry.id );
				} );

				Array.from( open ).forEach( function( id ) {
					if( byId.has( id ) === false || byId.get( id ).group === null )
						open.delete( id );
				} );

				if( selected !== null && byId.has( selected ) === false )
					selected = null;

				order.forEach( paintOpen );
				paintSelected();

				const seen = visible();
				const kept = was !== null && byId.has( was ) === true && shown( byId.get( was ) ) === true ? byId.get( was ) : null;
				const chosen = selected !== null && shown( byId.get( selected ) ) === true ? byId.get( selected ) : null;

				const neighbour = near.map( function( id ) { return byId.get( id ) } ).find( function( other ) { return other !== undefined && shown( other ) === true } ) ?? null;

				setStop( kept ?? neighbour ?? chosen ?? seen[0] ?? null, held !== null && kept === null );

				// The focus goes back where it was: on the button of the row where it stood on one, if it is still there and can be pressed
				if( held !== null && stop !== null ) {

					const back = held.part === 'action' && held.entry.id === stop.id && stop.keyed.has( held.key ) === true ? stop.keyed.get( held.key ) : null;

					show( stop, back !== null && back.disabled === false ? back : stop.li );
				}
			};

			const keyOf = function( node ) {

				const found = locate( node );

				if( found === null || found.part !== 'action' )
					return found;

				const key = Array.from( found.entry.keyed.keys() ).find( function( name ) { return found.entry.keyed.get( name ) === node } );

				return { entry : found.entry, part : 'action', key : key };
			};

			root.addEventListener( 'keydown', function( ev ) {

				const found = parts.get( ev.target );

				if( found === undefined || found.part !== 'item' || ev.ctrlKey === true || ev.altKey === true || ev.metaKey === true )
					return;

				const entry = found.entry;
				const seen = visible();
				const at = seen.indexOf( entry );
				let next = null;

				if( ev.key === 'ArrowDown' )	next = seen[at + 1] ?? null;
				else if( ev.key === 'ArrowUp' )	next = seen[at - 1] ?? null;
				else if( ev.key === 'Home' )		next = seen[0];
				else if( ev.key === 'End' )			next = seen[seen.length - 1];
				else if( ev.key === 'ArrowRight' ) {

					if( entry.group !== null && open.has( entry.id ) === false )
						setOpen( entry, true, true );
					else if( entry.group !== null )
						next = order[order.indexOf( entry ) + 1] ?? null;
				}
				else if( ev.key === 'ArrowLeft' ) {

					if( entry.group !== null && open.has( entry.id ) === true )
						setOpen( entry, false, true );
					else
						next = entry.parent;
				}
				else if( ev.key === 'Enter' || ev.key === ' ' )
					choose( entry );
				else if( typeof ev.key === 'string' && ev.key.length === 1 ) {

					const letter = ev.key.toLowerCase();
					const ring = seen.slice( at + 1 ).concat( seen.slice( 0, at ) );

					next = ring.find( function( other ) { return String( other.item.label ?? '' ).toLowerCase().indexOf( letter ) === 0 } ) ?? null;

					if( next === null )
						return;
				}
				else
					return;

				// The arrows and Space scroll the page otherwise
				ev.preventDefault();

				if( next !== null )
					go( next );
			} );

			root.addEventListener( 'click', function( ev ) {

				const found = locate( ev.target );

				if( found === null || found.part === 'action' || found.part === 'actions' )
					return;

				if( found.part === 'toggle' ) {
					setOpen( found.entry, open.has( found.entry.id ) === false, true );
					return;
				}

				go( found.entry );
				choose( found.entry );
			} );

			// The stop follows the hand: a click or a Tab that lands in a row makes it the row of the tab order
			root.addEventListener( 'focusin', function( ev ) {

				const found = locate( ev.target );

				if( found !== null && found.entry !== stop )
					setStop( found.entry, true );
			} );

			draw( Array.isArray( options.items ) === true ? options.items : [] );

			return {

				el : root,

				update : function( items ) {
					draw( Array.isArray( items ) === true ? items : [] );
				},

				select : function( id ) {

					if( id === null ) {
						selected = null;
						paintSelected();
						return true;
					}

					const entry = byId.get( String( id ) );

					if( entry === undefined || entry.item.locked === true )
						return false;

					reveal( entry );
					selected = entry.id;
					paintSelected();
					setStop( entry );
					entry.row.scrollIntoView( { block : 'nearest' } );

					return true;
				},

				focus : function( id ) {

					const entry = id === undefined ? stop : byId.get( String( id ) );

					if( entry === undefined || entry === null )
						return false;

					reveal( entry );
					go( entry );

					return true;
				},

				get : function() {
					return { selected : selected, stop : stop === null ? null : stop.id, open : Array.from( open ) };
				},
			};
		},

		/**
		 *	A field for a colour: the hex code in a text field, the system's own
		 *	colour picker beside it, a surface that shows the colour, and - where
		 *	options.against names a second colour - the contrast between them as
		 *	a number, with whether it reaches the ratio a text needs (4.5 : 1 by
		 *	default, the level WCAG asks of ordinary text). The surface shows the
		 *	second colour as text on this one, so the number can be checked by eye.
		 *	The verdict is a word and a mark, not a colour alone.
		 *
		 *	What is typed is kept as it was typed while it is being typed: a
		 *	valid code (#rrggbb, #rgb, the # may be left out) is taken at once and
		 *	reported through onChange as #rrggbb; a code that is not one says so
		 *	at the field - aria-invalid, the sentence pointed at by aria-describedby -
		 *	and reports through onInvalid. The picker and the field follow each
		 *	other. A short code is written out in full when the field is left. The field
		 *	takes whatever is pasted into it - parse() alone says what is a colour, so
		 *	#11223344 and rgb(37, 99, 235) are none, with the sentence - and trims the
		 *	space round it. The contrast line is written once the typing has rested
		 *	(COLOR_QUIET) for a screen reader - a copy of it in a live region of its
		 *	own - while the line itself follows the typing; set() tells it at once and shows
		 *	an error as a typed one would, without telling the caller; against() takes the
		 *	way of the typing.
		 *	The pure half, parsing and the contrast, is colorModel. Owns no
		 *	strings, the words of the contrast and of the error apart
		 *
		 *	@param		{Object}		options					{ label, value, against, minimum, required, id, text, onChange, onInvalid }
		 *														- label       the field's name
		 *														- value       the colour at the start
		 *														- against     the colour the contrast is measured against; none draws no contrast
		 *														- minimum     the ratio that is enough, 4.5
		 *														- required    the field may not be empty: an empty field is an error with the required field's sentence, and without it an empty field is no colour and no error (onChange is told '')
		 *														- id          the text field's id, and the beginning of the ids around it
		 *														- text        { invalid, pick, contrast, enough, short } to say the words differently
		 *														- onChange    called with #rrggbb after a valid code, with '' after the emptying of a field that is not required
		 *														- onInvalid   called with what stands in the field after one that is not
		 *
		 *	@return		{Object}										{ el, input, get(), set( value ), against( value ) } - get() is #rrggbb, or null where the field holds no colour
		 */
		color : function( options ) {

			const model		= Nino.adminUi.colorModel;
			const prefix	= options.id || 'nino-admin-color-'+ ( ++serial );
			const text		= options.text || {};
			const word		= function( key ) { return text[key] ?? Nino.adminUi.text( '/_admin/common/color/'+ key ) };
			const minimum	= typeof options.minimum === 'number' ? options.minimum : model.ENOUGH;

			const root = dc.createElement('div');
			root.className = 'nino-admin-color';

			let value = null;
			let second = model.parse( options.against ?? '' );

			const field = Nino.adminUi.textField( {
				label : options.label, id : prefix, value : options.value ?? '', autocomplete : 'off', required : options.required === true,
				onInput : function( typed ) { typedIn( typed, true ) },
			} );

			const input = field.querySelector('input');
			input.spellcheck = false;
			input.className += ' nino-admin-color-hex';

			const picker = dc.createElement('input');
			picker.type = 'color';
			picker.className = 'nino-admin-color-picker';
			picker.setAttribute( 'aria-label', ( options.label ? options.label+ ' – ' : '' )+ word('pick') );

			const swatch = Nino.adminUi.el( 'span', 'nino-admin-color-swatch', 'Aa' );
			swatch.setAttribute( 'aria-hidden', 'true' );

			const contrast = dc.createElement('p');
			contrast.className = 'nino-admin-color-contrast';
			contrast.id = prefix+ '-contrast';
			contrast.hidden = true;

			// What a screen reader is told: a copy of the line in a live region of its own, written
			// when the typing has rested - the line itself follows the typing and is no live region
			const announce = Nino.adminUi.el( 'span', 'nino-admin-sr-only' );
			announce.setAttribute( 'aria-live', 'polite' );

			const mark = Nino.adminUi.el( 'span', 'nino-admin-color-mark' );
			mark.setAttribute( 'aria-hidden', 'true' );

			const verdict = dc.createElement('span');

			contrast.appendChild( mark );
			contrast.appendChild( verdict );

			// What the field is described by: its error, and the contrast where that is shown - a
			// hidden line that is still named is read out with the sentence that says it is stale
			const describe = function() {

				const ids = [];

				if( root.querySelector('.nino-admin-field-error') !== null )
					ids.push( prefix+ '-error' );

				if( contrast.hidden === false )
					ids.push( contrast.id );

				if( ids.length === 0 )
					input.removeAttribute('aria-describedby');
				else
					input.setAttribute( 'aria-describedby', ids.join(' ') );
			};

			const clear = function() {
				const old = root.querySelector('.nino-admin-field-error');
				if( old !== null )
					old.remove();
				input.removeAttribute('aria-invalid');
			};

			const write = function() {

				const ratio = model.contrast( value, second );
				const enough = ratio >= minimum;

				contrast.dataset.state = enough === true ? 'ok' : 'error';
				mark.textContent = enough === true ? '✓' : '✕';
				verdict.textContent = Nino.adminUi.format( word('contrast'), model.truncate( ratio ) )+ ' · '+ Nino.adminUi.format( word( enough === true ? 'enough' : 'short' ), minimum );
				contrast.hidden = false;
				describe();
			};

			let quiet = null;

			// The line is seen as it is typed; what is read out is written once the typing has rested -
			// a code typed digit by digit, or a picker dragged beside it, passes through many valid ones
			const settle = function( now ) {

				wn.clearTimeout( quiet );
				quiet = null;

				if( value === null || second === null ) {
					contrast.hidden = true;
					announce.textContent = '';
					return;
				}

				write();

				if( now === true )
					announce.textContent = verdict.textContent;
				else
					quiet = wn.setTimeout( function() { announce.textContent = verdict.textContent }, Nino.adminUi.COLOR_QUIET );
			};

			const draw = function( now ) {

				// What is not a colour is not written anywhere: the picker keeps the last one it showed
				if( value !== null ) {
					picker.value = value;
					swatch.style.backgroundColor = value;
					swatch.style.color = second ?? value;
				}

				swatch.hidden = value === null;
				settle( now );
				describe();
			};

			// What is in the field, by hand (typed or picked: the caller is told) or put there (it is not)
			const typedIn = function( typed, byHand ) {

				clear();

				const parsed = model.parse( typed );

				if( parsed === null ) {

					value = null;
					draw( true );

					if( typed.trim() === '' && options.required !== true ) {
						if( byHand === true && typeof options.onChange === 'function' )
							options.onChange( '' );
						return;
					}

					Nino.adminUi.fieldError( field, input, prefix+ '-error', typed.trim() === '' ? Nino.adminUi.text('/_admin/common/error/required') : word('invalid') );
					describe();

					if( byHand === true && typeof options.onInvalid === 'function' )
						options.onInvalid( typed );

					return;
				}

				value = parsed;
				draw( byHand !== true );

				if( byHand === true && typeof options.onChange === 'function' )
					options.onChange( parsed );
			};

			picker.addEventListener( 'input', function() {
				input.value = picker.value;
				typedIn( picker.value, true );
			} );

			// A short code is the long one when the person is done with the field
			input.addEventListener( 'change', function() {
				if( value !== null )
					input.value = value;
			} );

			root.appendChild( field );
			root.appendChild( picker );
			root.appendChild( swatch );
			root.appendChild( contrast );
			root.appendChild( announce );

			typedIn( String( options.value ?? '' ), false );

			return {

				el : root,
				input : input,

				get : function() {
					return value;
				},

				set : function( next ) {
					input.value = next ?? '';
					typedIn( String( next ?? '' ), false );
				},

				against : function( next ) {
					second = model.parse( next ?? '' );
					draw( false );
				},
			};
		},

		/**
		 *	The pure half of color(): what is a colour, how light it is, how far
		 *	two colours are apart. Needs no DOM, so it is tested on its own and a
		 *	panel that has colours without a field (the pairs a palette is made
		 *	of) asks it the same questions
		 *
		 *	  - ENOUGH       4.5, the ratio WCAG 2.1 asks of ordinary text (AA)
		 *	  - parse        #rrggbb of a code written #rrggbb, rrggbb, #rgb or rgb, in either
		 *	                 case, or null for anything else - a name, rgb(), an alpha, a number
		 *	  - luminance    the relative luminance of a code parse() accepts, 0 to 1
		 *	  - contrast     the contrast ratio of two codes, 1 to 21, or null where one is none
		 *	  - truncate     a ratio cut - not rounded - to two decimals: 4.499 is written 4.49,
		 *	                 so that a number that reads as enough is enough
		 */
		colorModel : {

			ENOUGH : 4.5,

			parse : function( text ) {

				const found = typeof text === 'string' ? /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec( text.trim() ) : null;

				if( found === null )
					return null;

				const code = found[1].length === 3 ? found[1].replace( /./g, '$&$&' ) : found[1];

				return '#'+ code.toLowerCase();
			},

			luminance : function( code ) {

				const hex = Nino.adminUi.colorModel.parse( code );
				if( hex === null )
					return null;

				const channel = function( at ) {
					const value = parseInt( hex.slice( at, at + 2 ), 16 ) / 255;
					return value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
				};

				return 0.2126 * channel( 1 ) + 0.7152 * channel( 3 ) + 0.0722 * channel( 5 );
			},

			contrast : function( first, second ) {

				const one = Nino.adminUi.colorModel.luminance( first );
				const two = Nino.adminUi.colorModel.luminance( second );

				if( one === null || two === null )
					return null;

				return ( Math.max( one, two ) + 0.05 ) / ( Math.min( one, two ) + 0.05 );
			},

			truncate : function( ratio ) {
				return Math.floor( ratio * 100 + 1e-9 ) / 100;
			},
		},


		/**
		 *	The lifecycle of a panel, from a spec: when the panel asks the server
		 *	for its screen, what it does with the answer, what a second showing
		 *	does and how it says that a load failed. Every panel script wrote its
		 *	own init() and showCurrent() for this - the mount that may be missing,
		 *	the request, the failure line, the flag that says the panel was loaded,
		 *	the rule for the next showing - and a panel bound to the ready event
		 *	asked for its list twice on a page opened at its hash: the shell shows
		 *	the panel before the panel's own ready callback runs. This is that
		 *	once, and a request that is in flight is asked for once.
		 *
		 *	The spec:
		 *
		 *	  - uri      the pane's uri, required: the endpoints are <uri>/<action>,
		 *	             the dirty registry knows the panel by it and the default
		 *	             error key is made of it
		 *	  - mount    the id of the element the panel draws into, required:
		 *	             where the page has none, nothing is ever requested
		 *	  - render   render( data, panel ), required: draws the screen from the
		 *	             body of an answer
		 *	  - action   the first endpoint, 'list' by default
		 *	  - payload  an object, or a function answering one, sent with the first
		 *	             request - {} by default
		 *	  - show     what showCurrent() does: 'once' (the default) asks while the
		 *	             panel is not loaded and does nothing afterwards, 'always'
		 *	             asks on every showing, 'keep' asks while the panel is not
		 *	             loaded and afterwards draws the last answer again - unless
		 *	             the dirty registry says the panel holds input nobody has
		 *	             saved, which it leaves alone
		 *	  - stamps   the kinds of data the panel shows and another panel
		 *	             writes, a list of the names touch() of the shell has
		 *	             (templates, texts, elements, slots, routes, navigations):
		 *	             the stamp of each is kept as it was when the request for
		 *	             the screen went out, and a showCurrent() with show 'keep'
		 *	             asks again, instead of drawing the last answer, when one
		 *	             of them has moved since - not while the dirty registry
		 *	             says the panel holds input nobody has saved, which is
		 *	             left alone and answered at the next showing. Nothing
		 *	             moves on a page that has no shell, and 'once' and
		 *	             'always' have their own rule for a second showing
		 *	  - boot     'lazy' (the default): the first showCurrent() starts the
		 *	             panel; 'ready': the ready event does, and a showCurrent()
		 *	             that comes first starts it too, once
		 *	  - dirty    { isDirty, save, discard, bar, shortcut }, registered under the uri at
		 *	             the first init() where the shell has its registry
		 *	  - error    the fill key of a failed load, '/_admin/<uri>/error/load'
		 *	             unless it is given
		 *	  - onError  onError( status, response, panel ), called once the
		 *	             failure is shown
		 *
		 *	It answers a plain object. The script publishes it itself, as
		 *	Nino.admin.<uri> = Nino.adminUi.panel( { ... } ), and may add members
		 *	to it or replace some: a panel that follows the url hash into a level
		 *	of its own keeps its own showCurrent(), since panel() reads nothing of
		 *	the hash (levels() is the helper for the level). Its members:
		 *
		 *	  - uri, ready (false until a load succeeded), loading (true while a
		 *	    request is out), data (the body of the last answer, null before)
		 *	  - mount()               the element, or null
		 *	  - call( action, payload, done, extra )   Nino.adminUi.api.call() for
		 *	                          <uri>/<action>
		 *	  - fail( container, status, response, [key] )   showError() with the
		 *	                          panel's key; answers its paragraph
		 *	  - init( [then] )        asks for the screen: nothing while a request is
		 *	                          out or the mount is missing; on an answer draws
		 *	                          it and calls then(), on a failure shows it. The
		 *	                          request is over before render() or fail() run,
		 *	                          so that a render may ask again
		 *	  - reload( [then] )      init() by the name a caller reads better
		 *	  - showCurrent()         what the shell calls when the panel is shown
		 *
		 *	The dirty registry is the shell's (Nino.admin.dirty), looked up when the
		 *	panel needs it and not at load: without it the panel works the same,
		 *	and 'keep' counts it as clean. Owns no strings
		 *
		 *	@param		{Object}		spec						{ uri, mount, render, action, payload, show, stamps, boot, dirty, error, onError }
		 *
		 *	@return		{Object}										The panel
		 */
		panel : function( spec ) {

			const fallback	= spec.error ?? '/_admin/'+ spec.uri+ '/error/load';
			const topics		= Array.isArray( spec.stamps ) === true ? spec.stamps : [];
			let registered	= false;
			// The stamps of the topics as they were when the screen was asked for
			let seen				= [];

			const dirty = function() {
				const registry = shell().dirty;
				return typeof registry === 'object' && registry !== null && registry.isDirty( [ spec.uri ] ) === true;
			};

			// The stamps now, in the order the spec names the topics: a page that
			// has no shell - the wizard, a test - has no counters, and nothing on
			// it ever moves
			const stamped = function() {
				const registry = shell();
				return topics.map( function( topic ) { return typeof registry.stamp === 'function' ? registry.stamp( topic ) : 0 } );
			};

			const panel = {

				uri			: spec.uri,
				ready		: false,
				loading	: false,
				data		: null,

				mount : function() {
					return dc.getElementById( spec.mount );
				},

				call : function( action, payload, done, extra ) {
					Nino.adminUi.api.call( spec.uri+ '/'+ action, payload, done, extra );
				},

				fail : function( container, status, response, key ) {
					return Nino.adminUi.showError( container, status, response, key || fallback );
				},

				init : function( then ) {

					const mount = panel.mount();

					if( panel.loading === true || mount === null )
						return;

					// The payload is made before the panel counts as loading: a payload
					// function that throws is the script's error to see, not a request
					// that is out for good and blocks every showing after it
					const payload = typeof spec.payload === 'function' ? spec.payload() : ( spec.payload ?? {} );

					panel.loading = true;

					// A write that lands while the request is out is one this answer
					// may not hold: the stamps are the ones from before it went out
					const asked = stamped();

					if( registered === false && typeof spec.dirty === 'object' && spec.dirty !== null && typeof shell().dirty === 'object' && shell().dirty !== null ) {
						shell().dirty.register( spec.uri, spec.dirty );
						registered = true;
					}

					panel.call( spec.action ?? 'list', payload, function( status, response ) {

						panel.loading = false;

						if( status === 200 ) {
							panel.data = response;
							panel.ready = true;
							seen = asked;
							spec.render( panel.data, panel );
							if( typeof then === 'function' )
								then();
							return;
						}

						panel.fail( panel.mount(), status, response );
						panel.ready = false;
						if( typeof spec.onError === 'function' )
							spec.onError( status, response, panel );
					} );
				},

				reload : function( then ) {
					panel.init( then );
				},

				showCurrent : function() {

					if( panel.ready === false || spec.show === 'always' ) {
						panel.init();
						return;
					}

					if( spec.show !== 'keep' || dirty() === true )
						return;

					// What another panel wrote since the answer was asked for is not in
					// it: ask again, and the render of that answer is the redraw
					if( stamped().some( function( stamp, at ) { return stamp !== seen[at] } ) === true ) {
						panel.init();
						return;
					}

					spec.render( panel.data, panel );
				},
			};

			if( spec.boot === 'ready' )
				Nino.events.bindCallback( 'ready', function() { panel.init() } );

			return panel;
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
