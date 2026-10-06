

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	script.js								The workbench shell (/_admin): the url-hash router, the
 *													theme and locale chrome, the panel rail and the csv
 *													export - and the Nino.admin namespace itself, which
 *													every panel's own assets/admin.js attaches to (see
 *													\Nino\Admin\Panels). Loads after Nino.admin.js, whose
 *													primitives it builds on, and before any panel file.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = {

		/**
		 *	Minimal url-hash "router": lets a panel persist which drill-down
		 *	level it's on into the hash, so a refresh restores the exact view
		 *	instead of resetting to the panel's top level. Uses
		 *	history.replaceState and pushState (not location.hash=) so it never
		 *	scroll-jumps and never fires its own hashchange event.
		 *
		 *	What the browser's Back and Forward walk is the workbench: a
		 *	person's own move - the rail, a tab, a row opened, a back link -
		 *	adds an entry (go(), or set() while the shell asked for it), and
		 *	everything that only keeps the address true to the screen - a
		 *	panel showing its level again, the arrow keys of a tab strip, a
		 *	save that names the new element - replaces the entry it is on. A
		 *	step through the history is a hashchange, which the shell answers
		 *	by selecting the panel or tab the hash names and the panel by
		 *	following the rest (see showCurrent() of the drill-down panels).
		 *
		 *	Every panel's init() runs unconditionally on page load and each
		 *	ends by calling its own "show my current state" function - which is
		 *	also what set() is called from. Without a guard, whichever panel's
		 *	background load finishes last would stomp the hash with its own
		 *	default state, regardless of which panel the hash actually pointed
		 *	at. set() only writes when `panel` is the one currently on screen;
		 *	onReady() below resolves that race by selecting the hash's target
		 *	tab synchronously, before any panel's async data can arrive.
		 */
		router : {

			// The shell's one-shot "this switch is the person's move" - set by
			// selectTab() for the length of the switch, consumed by the first
			// write that changes the address (see set())
			_push : false,

			// The shell brings a panel on screen as it stands (a form that failed
			// to save, with its errors): leave() then keeps the level in memory
			// and does not move on to the one the address names - that move is the
			// very question that was just answered with a Save that failed
			_keep : false,

			// The entries the shell wrote carry their position in history.state,
			// { nino : n }: a step through the history is then a number of
			// entries one way or the other (see _step), and one that is refused
			// can be taken back with history.go() instead of a write over the
			// entry the browser has already moved to. _index is the entry the
			// browser is on, _settled the one the screen was last made true to.
			// An entry the browser made itself (an address typed, a link followed)
			// has no number and is numbered when it is met; one from before the
			// stamping has none, and a step to it cannot be measured
			_index : null,
			_settled : null,

			// The step through the history being answered right now: { delta,
			// from }, the entries it moved and the number of the entry it came
			// from. Set for the length of the hashchange that carries it, and
			// held by leave() while a question about unsaved input is open
			_step : null,

			// A traversal the shell asked for to take a refused step back:
			// { index }, the entry it is going to. Until it arrives the address
			// is not written to - what a panel shows again belongs to the entry
			// that is about to be the current one again
			_undoing : null,

			/**
			 *	Whether `panel` names a pane the shell rendered - the panes come
			 *	from the server's panel registry (see Admin::panels()), so the
			 *	DOM is the list, and a name that has no pane is no panel. A tab
			 *	of a pane (see Panels::panesHtml()) is a panel of its own
			 *
			 *	@param		{string}	panel
			 *
			 *	@return		{boolean}
			 */
			exists : function( panel ) {
				return typeof panel === 'string' && /^[a-z][a-z0-9-]*$/.test( panel )
					&& ( dc.getElementById( 'admin-content-'+ panel ) !== null || dc.getElementById( 'admin-tab-'+ panel ) !== null );
			},

			/**
			 *	Read the current #hash into { panel, parts[] }
			 *
			 *	@return		{Object}
			 */
			current : function() {
				const raw = wn.location.hash.replace(/^#/, '');
				try {
					const parts = raw === '' ? [] : raw.split('/').map( function(p) { return decodeURIComponent(p) } );
					return { panel : parts[0] ?? '', parts : parts.slice(1) };
				} catch(e) {
					// A hand-edited hash with a stray '%' must not abort all editor
					// initialization with decodeURIComponent()'s URIError.
					return { panel : '', parts : [] };
				}
			},

			/**
			 *	Whether `panel` is the tab currently on screen
			 *
			 *	@param		{string}	panel
			 *
			 *	@return		{boolean}
			 */
			isActive : function( panel ) {
				return Nino.admin.router.exists( panel ) && dc.getElementById('admin-page-wrap').classList.contains( 'show-'+ panel );
			},

			/**
			 *	The hash for #panel/part/part/...
			 *
			 *	@param		{string}	panel
			 *	@param		{Array}		[parts]
			 *
			 *	@return		{string}
			 */
			_hash : function( panel, parts ) {
				return '#'+ [ panel ].concat( parts || [] ).map( encodeURIComponent ).join('/');
			},

			/**
			 *	Write #panel/part/part/... into the address: replacing the entry
			 *	the browser is on - or adding one, when the shell flagged the
			 *	switch in progress as the person's own move (the one-shot _push)
			 *	- a no-op while `panel` isn't the currently visible tab (see
			 *	class docblock) and when the address already says it
			 *
			 *	@param		{string}	panel
			 *	@param		{Array}		[parts]
			 *
			 *	@return		void
			 */
			set : function( panel, parts ) {

				if( Nino.admin.router.isActive( panel ) === false )
					return;

				const hash = Nino.admin.router._hash( panel, parts );
				if( wn.location.hash === hash )
					return;

				const push = Nino.admin.router._push === true;
				Nino.admin.router._push = false;

				if( Nino.admin.router._holdsAddress() === true )
					return;

				Nino.admin.router._write( push, hash );
			},

			/**
			 *	A move the person made inside a panel - a row opened, a back
			 *	link, the next element: a new entry for Back to return to. The
			 *	same no-op rules as set(), so asking for the address the bar
			 *	already shows (a level followed from the hash, a restore after
			 *	a reload) adds nothing. Called before the panel's own set(), which
			 *	then finds the address true
			 *
			 *	@param		{string}	panel
			 *	@param		{Array}		[parts]
			 *
			 *	@return		void
			 */
			go : function( panel, parts ) {

				if( Nino.admin.router.isActive( panel ) === false )
					return;

				const hash = Nino.admin.router._hash( panel, parts );
				if( wn.location.hash !== hash && Nino.admin.router._holdsAddress() === false )
					Nino.admin.router._write( true, hash );
			},

			/**
			 *	Write the address: a new entry or over the one the browser is on,
			 *	either way numbered
			 *
			 *	@param		{boolean}	push
			 *	@param		{string}	hash
			 *
			 *	@return		void
			 */
			_write : function( push, hash ) {

				const router = Nino.admin.router;
				const index = ( router._index ?? 0 ) + ( push === true ? 1 : 0 );

				wn.history[ push === true ? 'pushState' : 'replaceState' ]( { nino : index }, '', hash );

				router._index = index;
				router._settled = index;
			},

			/**
			 *	The number the entry the browser is on carries, or null where it
			 *	has none
			 *
			 *	@return		{number|null}
			 */
			_stateIndex : function() {
				const state = wn.history.state;
				return state !== null && typeof state === 'object' && Number.isInteger( state.nino ) === true ? state.nino : null;
			},

			/**
			 *	Whether a traversal the shell asked for is still on its way, so
			 *	that a write would land on the entry the browser is leaving. One
			 *	that has arrived is over
			 *
			 *	@return		{boolean}
			 */
			_holdsAddress : function() {

				const router = Nino.admin.router;

				if( router._undoing === null )
					return false;

				if( router._stateIndex() !== router._undoing.index )
					return true;

				router._index = router._settled = router._undoing.index;
				router._undoing = null;

				return false;
			},

			/**
			 *	Take the entries as they are when the shell starts: the one the
			 *	page was loaded on is numbered if it was not, so that a step back
			 *	to it can be measured
			 *
			 *	@return		void
			 */
			start : function() {

				const router = Nino.admin.router;
				const index = router._stateIndex();

				router._index = index ?? 0;
				router._settled = router._index;

				if( index === null && typeof wn.history.replaceState === 'function' )
					wn.history.replaceState( { nino : 0 }, '' );
			},

			/**
			 *	The browser stepped through its history: a hashchange. Works out how
			 *	many entries it moved - by the numbers the entries carry - and holds
			 *	that as the step being answered while the panels follow the address
			 *	(see leave()). The traversal that takes a refused step back is
			 *	recognised and ignored. Returns whether the panels are to follow
			 *
			 *	@return		{boolean}
			 */
			arrive : function() {

				const router = Nino.admin.router;
				const to = router._stateIndex();

				if( router._undoing !== null ) {

					if( to !== router._undoing.index )
						return false;

					router._index = router._settled = to;
					router._undoing = null;
					router._step = null;

					return false;
				}

				// The browser made this entry (an address typed, a link followed):
				// it comes after the one the page was on
				if( to === null ) {
					router._index = ( router._index ?? 0 ) + 1;

					if( typeof wn.history.replaceState === 'function' )
						wn.history.replaceState( { nino : router._index }, '' );

					router._step = null;
					router._settled = router._index;

					return true;
				}

				const from = router._settled;

				router._step = from !== null && to !== from ? { delta : to - from, from : from } : null;
				router._index = router._settled = to;

				return true;
			},

			/**
			 *	Take the step being answered back, if it can be measured: the
			 *	browser has moved to another entry, and the screen did not follow.
			 *	history.go() puts it back on the entry the screen is true to,
			 *	rather than a write that would lay the form's address over the entry
			 *	the browser has just stepped to. The hashchange this causes is
			 *	ignored (see arrive())
			 *
			 *	@param		{Object|null}	step			{ delta, from }, as held in _step
			 *
			 *	@return		{boolean}									Whether the history is being put back
			 */
			_undo : function( step ) {

				const router = Nino.admin.router;

				if( step === null || step === undefined || router._undoing !== null || typeof wn.history.go !== 'function' )
					return false;

				router._undoing = { index : step.from };

				// A traversal that never arrives must not keep the address shut
				if( typeof wn.setTimeout === 'function' )
					wn.setTimeout( function() {
						if( router._undoing !== null && router._undoing.index === step.from )
							router._undoing = null;
					}, 1500 );

				wn.history.go( -step.delta );

				return true;
			},

			/**
			 *	A panel will not follow the address (a save is running): the step
			 *	through the history that brought it here is taken back
			 *
			 *	@return		{boolean}
			 */
			refuse : function() {
				return Nino.admin.router._undo( Nino.admin.router._step );
			},

			/**
			 *	A panel follows the address to another level (see showCurrent()
			 *	of the drill-down panels) - a step through the browser's history
			 *	changed the bar and nothing else. Leaving a form that holds
			 *	input nobody saved asks first, as its back link does (see
			 *	Nino.admin.dirty.guard()); a Cancel puts the address back to what
			 *	the screen shows - by taking the step through the history back
			 *	where it can be measured (see _undo()), and by `resync`, which shows
			 *	the level in memory again
			 *
			 *	@param		{Array}			names				The dirty entries the form answers to
			 *	@param		{boolean}		leaving			A form is on screen and the move leaves it
			 *	@param		{Function}	proceed			Shows the level the address names
			 *	@param		{Function}	resync			Shows the level in memory again, writing it into the address
			 *
			 *	@return		void
			 */
			leave : function( names, leaving, proceed, resync ) {

				// The step through the history that is being answered now: the
				// question may stay open after the hashchange that carries it is over
				const step = Nino.admin.router._step;

				// A move that is not made - Cancel, a Save that failed, a form brought
				// on screen as it stands - puts the history back where the screen is
				const refuse = function() {
					Nino.admin.router._undo( step );
					resync();
				};

				if( Nino.admin.router._keep === true ) {
					refuse();
					return;
				}

				if( leaving === true && typeof Nino.admin.dirty === 'object' ) {
					Nino.admin.dirty.guard( names, proceed, refuse );
					return;
				}

				proceed();
			},

			/**
			 *	The shell's own write, after a panel or a tab was opened: its
			 *	name and nothing behind it, unless the hash already names it.
			 *
			 *	A panel that keeps drill-down state writes the hash itself, with
			 *	the parts that state needs (see set() and Elements' showCurrent()).
			 *	The others never wrote it at all, so the address kept naming
			 *	whatever panel had last written - open Images, then Routes, and
			 *	the bar still said #images, and a reload went back to Images.
			 *	Asked after the panel's own showCurrent() ran, so a panel that
			 *	wrote a deeper address keeps it: this only fills the silence
			 *
			 *	@param		{string}	name					A panel or one of its tabs
			 *
			 *	@return		void
			 */
			settle : function( name ) {
				if( Nino.admin.router.current().panel !== name )
					Nino.admin.router.set( name, [] );
			},
		},

		/**
		 *	Shared "which locale am I currently working in" cache, so switching
		 *	it in Elements also applies the next time Text opens an item (and
		 *	vice versa) - each panel keeping its own copy would go stale the
		 *	moment the OTHER one changed it, since both only fetch their
		 *	initial value once, from their own init(). Persisted server-side
		 *	(POST admin/locale) so it also survives a reload.
		 */
		sessionLocale : {

			current : null,

			/**
			 *	Called by each panel's init() with its own initial locale, once
			 *	it's known - first one in wins, so a later-resolving panel's
			 *	initial value never overwrites one the user already changed
			 *
			 *	@param		{string}	locale
			 *
			 *	@return		void
			 */
			init : function( locale ) {
				if( Nino.admin.sessionLocale.current === null )
					Nino.admin.sessionLocale.current = locale;
			},

			/**
			 *	Called when the user changes a locale switch in any panel -
			 *	fire and forget, nothing in the UI depends on the request's result
			 *
			 *	@param		{string}	locale
			 *
			 *	@return		void
			 */
			set : function( locale ) {
				Nino.admin.sessionLocale.current = locale;
				Nino.adminUi.api.call( 'admin/locale', { locale : locale }, function() {} );
			},
		},

		/**
		 *	Unsaved input, and the questions asked before it is lost. A panel
		 *	that keeps a form registers itself here and says when that form
		 *	holds something nobody has saved; the shell asks Save, Discard or
		 *	Cancel before anything it does would throw that away - leaving by
		 *	logout, the interface language, a back link - and the browser asks
		 *	before the page itself goes (beforeunload). A panel that does not
		 *	register is simply not asked about: the registration is opt-in,
		 *	and a panel guards its own exits (a link to another record, a
		 *	reload) with guard().
		 *
		 *	A name is the panel's or the tab's uri, the id of its pane without
		 *	the admin-content- or admin-tab- in front. An entry is
		 *	{ isDirty(), save( done ), discard(), bar() }: save() reports with
		 *	done( true ) or done( false ) on every way it can end, discard()
		 *	forgets the input (the form is about to be left or drawn again),
		 *	bar() answers the action bar that carries the marker, if the
		 *	first one in the pane is not it.
		 *
		 *	Every listener is installed by init(), from onReady(): a shell
		 *	script loaded without a dom (a test's first context) has none.
		 */
		dirty : {

			// name -> entry
			_entries : {},
			// watchForm(): name -> the function that answers its form, and what
			// that form held when snapshot() last looked
			_forms : {},
			_baseline : {},
			// name -> the marker span in its action bar
			_marks : {},
			// A question is open: a second click on the same exit is the same click
			_asking : false,
			// How many Saves answered to a question are running. A save that needs
			// to ask about something of its own (an Element Types save drops the
			// element form next door) is let through to ask - a second click is
			// not, so it is only here that a question may be asked while one stands
			_saving : 0,
			// An entry's save() is being called right now: a guard() that comes
			// from inside it is the save asking, any other while a Save runs
			// (a back link, the logout, the language picker) is a stray click
			_inSave : false,
			// A failed Save of a nested question has brought its form on screen;
			// the Save around it need not bring its own over it
			_failShown : false,
			// The page is on its way out and has decided what to do with its input
			_leaving : false,
			// Whether the browser's own question is installed (see _sync())
			_unload : false,
			_frame : false,
			// A back link that was asked about and may now go through
			_bypass : null,
			// Set by init(): bring the panel or tab that owns a name on screen
			_show : null,
			// The timer that takes _leaving back if the page is still here
			_stayTimer : null,

			/**
			 *	@param		{string}		name
			 *	@param		{Object}		entry				{ isDirty, save, discard, bar }
			 *
			 *	@return		void
			 */
			register : function( name, entry ) {
				Nino.admin.dirty._entries[name] = entry;
			},

			/**
			 *	The registered names in scope that hold unsaved input
			 *
			 *	@param		{Array|null}	[names]			null for every entry
			 *
			 *	@return		{Array<string>}
			 */
			dirtyNames : function( names ) {
				const entries = Nino.admin.dirty._entries;
				return ( Array.isArray( names ) === true ? names : Object.keys( entries ) ).filter( function( name ) {
					return entries[name] !== undefined && entries[name].isDirty() === true;
				} );
			},

			/**
			 *	@param		{Array|null}	[names]
			 *
			 *	@return		{boolean}
			 */
			isDirty : function( names ) {
				return Nino.admin.dirty.dirtyNames( names ).length > 0;
			},

			/**
			 *	Run proceed() - but ask first when something in scope is unsaved.
			 *	Save runs the entries in order and goes on only when every one
			 *	reports ok; the first that does not is brought on screen with
			 *	its errors and nothing goes on. Discard lets every entry forget
			 *	and goes on. Cancel, and a Save that failed, call onCancel() so
			 *	that whatever started this (a select, a checkbox) can undo
			 *	itself. While a question is open, further calls are ignored - and
			 *	call onCancel() too - except from inside a Save the question itself
			 *	started, which may have one of its own; of two failed Saves only the
			 *	innermost is brought on screen. A save() that throws ends the
			 *	question and the Save, and the error goes on up
			 *
			 *	@param		{Array|null}	names				The entries the exit would lose, null for all
			 *	@param		{Function}		proceed
			 *	@param		{Function}		[onCancel]
			 *	@param		{boolean}			[leaves]		proceed() leaves the page at once, so the browser has nothing to ask
			 *
			 *	@return		void
			 */
			guard : function( names, proceed, onCancel, leaves ) {

				const open = Nino.admin.dirty.dirtyNames( names );

				if( open.length === 0 ) {
					proceed();
					return;
				}

				// A question asked from inside a Save of the one standing
				const nested = Nino.admin.dirty._asking === true;

				const cancel = function() {
					if( typeof onCancel === 'function' )
						onCancel();
				};

				// Ignored - whatever started this undoes itself (a select goes back)
				if( nested === true && ( Nino.admin.dirty._saving === 0 || Nino.admin.dirty._inSave !== true ) ) {
					cancel();
					return;
				}

				// Every way on ends here: the question is over, the markers follow
				const go = function() {
					Nino.admin.dirty._asking = nested;
					if( nested === false )
						Nino.admin.dirty._leaving = leaves === true;
					Nino.admin.dirty.refresh();
					proceed();
					// An exit that does not happen (another script's own question keeps
					// the page, the language change is refused) must not leave the
					// browser's protection off
					if( nested === false && leaves === true && typeof wn.setTimeout === 'function' ) {
						wn.clearTimeout( Nino.admin.dirty._stayTimer );
						Nino.admin.dirty._stayTimer = wn.setTimeout( Nino.admin.dirty._stay, 10000 );
					}
				};

				// An entry that cannot save offers nothing to save
				const choices = [];
				if( open.every( function( name ) { return typeof Nino.admin.dirty._entries[name].save === 'function' } ) === true )
					choices.push( { value : 'save', label : Nino.content.getText('/_admin/common/label/save'), kind : 'primary' } );
				choices.push( { value : 'discard', label : Nino.content.getText('/_admin/common/label/discard'), kind : 'danger' } );
				choices.push( { value : 'cancel', label : Nino.content.getText('/_admin/common/label/cancel'), kind : 'secondary' } );

				Nino.admin.dirty._asking = true;

				const asked = Nino.adminUi.choiceDialog( {
					title		: Nino.content.getText('/_admin/common/msg/dirty'),
					message	: Nino.content.getText('/_admin/common/confirm/unsaved'),
					choices	: choices,
					onChoose : function( value ) {

						if( value === 'discard' ) {
							open.forEach( function( name ) {
								if( typeof Nino.admin.dirty._entries[name].discard === 'function' )
									Nino.admin.dirty._entries[name].discard();
							} );
							go();
							return;
						}

						if( value !== 'save' ) {
							Nino.admin.dirty._asking = nested;
							cancel();
							return;
						}

						let at = 0;
						let running = true;
						Nino.admin.dirty._saving++;

						// Once, whichever way this Save ends - also when a save() throws
						const stop = function() {
							if( running === false )
								return false;
							running = false;
							Nino.admin.dirty._saving--;
							return true;
						};

						const next = function() {

							if( at >= open.length ) {
								stop();
								go();
								return;
							}

							const name = open[at++];
							const was = Nino.admin.dirty._inSave;
							Nino.admin.dirty._inSave = true;
							try {
								Nino.admin.dirty._entries[name].save( function( ok ) {
									if( ok === true ) {
										next();
										return;
									}
									stop();
									Nino.admin.dirty._asking = nested;
									if( Nino.admin.dirty._failShown === false && typeof Nino.admin.dirty._show === 'function' ) {
										Nino.admin.dirty._show( name );
										Nino.admin.dirty._focusProblem( name );
									}
									Nino.admin.dirty._failShown = Nino.admin.dirty._saving > 0;
									Nino.admin.dirty.refresh();
									cancel();
								} );
							}
							catch( error ) {
								// Not swallowed - but the exits must not stay shut
								if( stop() === true ) {
									Nino.admin.dirty._asking = nested;
									cancel();
								}
								throw error;
							}
							finally {
								Nino.admin.dirty._inSave = was;
							}
						};
						next();
					},
				} );

				// A question that could not be opened is not one that is waiting
				if( asked === false ) {
					Nino.admin.dirty._asking = nested;
					cancel();
				}
			},

			/**
			 *	Put the focus on the first field a refused Save marked invalid in
			 *	a pane. The Save asked for it while the pane was still hidden, where
			 *	a focus does nothing; it is asked for again once the pane is shown
			 *
			 *	@param		{string}		name
			 *
			 *	@return		void
			 */
			_focusProblem : function( name ) {

				const pane = dc.getElementById( 'admin-tab-'+ name ) ?? dc.getElementById( 'admin-content-'+ name );
				const field = pane === null || pane === undefined ? null : pane.querySelector('[aria-invalid="true"]');

				if( field !== null && typeof field.focus === 'function' )
					field.focus();
			},

			/**
			 *	Make the page say what is unsaved: the marker in each registered
			 *	form's action bar, and the browser's question on leaving. Cheap
			 *	enough to be asked after every input, change and click (see
			 *	init()), and by a panel at the end of its own save
			 *
			 *	@return		void
			 */
			refresh : function() {

				Object.keys( Nino.admin.dirty._entries ).forEach( function( name ) {
					Nino.admin.dirty._mark( name, Nino.admin.dirty._entries[name].isDirty() === true );
				} );

				Nino.admin.dirty._sync();
			},

			/**
			 *	Put the marker in one entry's action bar, or take it out. Not a
			 *	p and not role=status, so the phone rule that hides the status
			 *	line leaves it alone. A bar that has a status line of its own
			 *	(Nino.adminUi.status()) says "unsaved changes" there as well; the
			 *	style sheet hides the marker in it, except where the phone rule hides
			 *	that line. A bar drawn again takes a new span - the old one went
			 *	with the old bar
			 *
			 *	@param		{string}		name
			 *	@param		{boolean}		unsaved
			 *
			 *	@return		void
			 */
			_mark : function( name, unsaved ) {

				const marks = Nino.admin.dirty._marks;
				const bar = Nino.admin.dirty._bar( name );
				let span = marks[name] ?? null;

				if( bar === null ) {
					if( span !== null )
						span.hidden = true;
					return;
				}

				if( span === null || span.parentNode !== bar ) {
					if( unsaved === false )
						return;
					span = dc.createElement('span');
					span.className = 'nino-admin-actionbar-dirty';
					span.textContent = Nino.content.getText('/_admin/common/msg/dirty');
					bar.appendChild( span );
					marks[name] = span;
				}

				span.hidden = unsaved === false;
			},

			/**
			 *	The action bar that carries an entry's marker: its own, else the
			 *	one in its form, else the first form bar in its pane
			 *
			 *	@param		{string}		name
			 *
			 *	@return		{Element|null}
			 */
			_bar : function( name ) {

				const entry = Nino.admin.dirty._entries[name];

				if( typeof entry.bar === 'function' )
					return entry.bar() ?? null;

				if( typeof Nino.admin.dirty._forms[name] === 'function' ) {
					const form = Nino.admin.dirty._forms[name]();
					return form === null || form === undefined ? null : form.querySelector('.nino-admin-actionbar');
				}

				const pane = dc.getElementById( 'admin-tab-'+ name ) ?? dc.getElementById( 'admin-content-'+ name );
				return pane === null || pane === undefined ? null : pane.querySelector('.nino-admin-actionbar:not(.nino-admin-list-actions)');
			},

			/**
			 *	The browser's own question when the page is closed or reloaded
			 *	with unsaved input - installed only while there is some, so a
			 *	clean page keeps the browser's fast back and forward
			 *
			 *	@return		void
			 */
			_sync : function() {

				const want = Nino.admin.dirty.isDirty() === true && Nino.admin.dirty._leaving === false;

				if( want === Nino.admin.dirty._unload || typeof wn.addEventListener !== 'function' )
					return;

				Nino.admin.dirty._unload = want;
				wn[want === true ? 'addEventListener' : 'removeEventListener']( 'beforeunload', Nino.admin.dirty._beforeUnload );
			},

			/**
			 *	The page is still here: whatever was leaving it did not, or it is
			 *	shown again (the browser's back). The browser's question comes back
			 *	with the input it protects
			 *
			 *	@return		void
			 */
			_stay : function() {
				Nino.admin.dirty._leaving = false;
				Nino.admin.dirty.refresh();
			},

			/**
			 *	@param		{Event}			ev
			 *
			 *	@return		void
			 */
			_beforeUnload : function( ev ) {

				if( Nino.admin.dirty._leaving === true || Nino.admin.dirty.isDirty() === false )
					return;

				ev.preventDefault();
				ev.returnValue = '';
			},

			/**
			 *	What a form holds, as one string to compare with later: the
			 *	value of every field in it - a checkbox or a radio by its state -
			 *	and the content of every rich-text field. Not what a person did
			 *	not type: a file input (its file is saved by its own upload), a
			 *	search box (it filters, it stores nothing), a password the
			 *	browser may fill in by itself and a field that says data-dirty="ignore"
			 *	(a confirmation typed to unlock a button is not an edit)
			 *
			 *	@param		{Element}		form
			 *
			 *	@return		{string}
			 */
			_serialize : function( form ) {

				const fields = Array.from( form.querySelectorAll('input, textarea, select') ).filter( function( el ) {
					return el.type !== 'file' && el.type !== 'search' && el.autocomplete !== 'current-password' && ( el.dataset === undefined || el.dataset.dirty !== 'ignore' );
				} );

				return JSON.stringify( fields.map( function( el ) {
					return el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value;
				} ).concat( Array.from( form.querySelectorAll('[contenteditable]') ).map( function( el ) { return el.innerHTML } ) ) );
			},

			/**
			 *	Whether a form is on screen as far as a drill-down goes: no level
			 *	above it is hidden with admin-hidden. A pane that is hidden because
			 *	another panel is open does not count - the form is still there, with
			 *	what was typed into it
			 *
			 *	@param		{Element}		form
			 *
			 *	@return		{boolean}
			 */
			_shown : function( form ) {

				for( let el = form; el !== null && el !== undefined; el = el.parentNode )
					if( el.classList !== undefined && el.classList.contains('admin-hidden') === true )
						return false;

				return true;
			},

			/**
			 *	Whether a form holds anything to type into. A panel that failed to
			 *	load writes its error into the very container that is watched; no
			 *	fields are left to have been edited, so that is not unsaved input
			 *
			 *	@param		{Element}		form
			 *
			 *	@return		{boolean}
			 */
			_hasFields : function( form ) {
				return form.querySelector('input, textarea, select, [contenteditable]') !== null;
			},

			/**
			 *	Take what a watched form holds now as what is saved - after it
			 *	was drawn, and after a save went through
			 *
			 *	@param		{string}		name
			 *
			 *	@return		void
			 */
			snapshot : function( name ) {

				const form = typeof Nino.admin.dirty._forms[name] === 'function' ? Nino.admin.dirty._forms[name]() : null;

				Nino.admin.dirty._baseline[name] = form === null || form === undefined ? undefined : Nino.admin.dirty._serialize( form );
				Nino.admin.dirty.refresh();
			},

			/**
			 *	Register a panel whose form is plain fields: it is dirty when the
			 *	form it answers now differs from what snapshot() saw, and clean
			 *	while that form is not on screen - drawn but under a drill-down
			 *	level that is hidden (admin-hidden), or not drawn - or holds no
			 *	fields (an error message took its place). Compared when
			 *	asked, not on every key. Its discard() takes the form as it stands
			 *	for saved: the form is about to be left or drawn again
			 *
			 *	@param		{string}		name
			 *	@param		{Function}	formGetter			Answers the form drawn last, or null
			 *	@param		{Function}	[save]					save( done ), as an entry has it
			 *	@param		{Function}	[bar]						Answers the action bar that carries the marker, where the
			 *																			first one in the form is not the form's own (a pane that
			 *																			holds more than one form, or more than one bar)
			 *
			 *	@return		void
			 */
			watchForm : function( name, formGetter, save, bar ) {

				Nino.admin.dirty._forms[name] = formGetter;
				Nino.admin.dirty.register( name, {
					bar			: typeof bar === 'function' ? bar : undefined,
					isDirty : function() {
						const form = formGetter();
						return form !== null && form !== undefined && Nino.admin.dirty._baseline[name] !== undefined && Nino.admin.dirty._hasFields( form ) === true && Nino.admin.dirty._shown( form ) === true && Nino.admin.dirty._serialize( form ) !== Nino.admin.dirty._baseline[name];
					},
					save		: save,
					discard : function() { Nino.admin.dirty.snapshot( name ) },
				} );
			},

			/**
			 *	The name of the pane a node stands in: the nearest ancestor whose
			 *	id starts admin-tab- (a tab of a pane) or admin-content- (the
			 *	panel). Not data-tab - a feature's own markup uses that too
			 *
			 *	@param		{Element}		node
			 *
			 *	@return		{string|null}
			 */
			_owner : function( node ) {

				for( let el = node.parentNode; el !== null && el !== undefined; el = el.parentNode ) {
					const id = typeof el.id === 'string' ? el.id : '';
					if( id.indexOf('admin-tab-') === 0 )
						return id.slice( 10 );
					if( id.indexOf('admin-content-') === 0 )
						return id.slice( 14 );
				}

				return null;
			},

			/**
			 *	Wire the page: a back link out of a form that holds unsaved input
			 *	asks first, and whatever is typed, changed or clicked makes the
			 *	markers follow. Many changes fire neither input nor change (a
			 *	reference list's commit, the rich-text toolbar), which is why a
			 *	click counts as well
			 *
			 *	@param		{Function}	show				Brings the panel or tab that owns a name on screen
			 *
			 *	@return		void
			 */
			init : function( show ) {

				const wrap = dc.getElementById('admin-content-wrap');

				Nino.admin.dirty._show = show;

				if( typeof wn.addEventListener === 'function' )
					wn.addEventListener( 'pageshow', Nino.admin.dirty._stay );

				if( wrap === null || wrap === undefined )
					return;

				wrap.addEventListener( 'click', function( ev ) {

					const link = ev.target && typeof ev.target.closest === 'function' ? ev.target.closest('a.nino-admin-back-link') : null;
					if( link === null || link === undefined || Nino.admin.dirty._bypass === link )
						return;

					const owner = Nino.admin.dirty._owner( link );
					if( owner === null || Nino.admin.dirty.isDirty( [ owner ] ) === false )
						return;

					ev.preventDefault();
					ev.stopImmediatePropagation();
					Nino.admin.dirty.guard( [ owner ], function() {
						Nino.admin.dirty._bypass = link;
						link.click();
						Nino.admin.dirty._bypass = null;
					} );
				}, true );

				const later = function() {

					if( Nino.admin.dirty._frame === true )
						return;

					Nino.admin.dirty._frame = true;
					( typeof wn.requestAnimationFrame === 'function' ? wn.requestAnimationFrame : function( fn ) { fn() } )( function() {
						Nino.admin.dirty._frame = false;
						Nino.admin.dirty.refresh();
					} );
				};

				[ 'input', 'change', 'click' ].forEach( function( type ) { wrap.addEventListener( type, later ) } );
			},
		},

		/**
		 *	What the shell does when a request finds the session gone (see
		 *	Nino.adminUi.api): a dialog to log in again, over the page and
		 *	everything typed into it. The api decides whether the requests that
		 *	waited are sent again - with the same account - or not; this is
		 *	the dialog and the login that stand between.
		 *
		 *	The login is posted from here rather than through Nino.auth.login(),
		 *	which redirects on success: the page must stay exactly as it is.
		 *	It goes out with the token of the anonymous session the api just
		 *	wrote into the page's csrf field - the one the dead session's
		 *	token on the page would have been refused for
		 */
		sessionDialog : {

			// The api's "whose session is this now" while the dialog is open
			_check : null,

			// Why the dialog was opened last: whether Escape may close it
			_reason : 'expired',

			/**
			 *	Register the dialog with the api - if the page has one. A shell
			 *	without it simply gets a session failure handed back like any
			 *	other answer
			 *
			 *	@return		void
			 */
			init : function() {

				const el = Nino.admin.sessionDialog._elements();

				if( el === null || typeof Nino.adminUi !== 'object' || typeof Nino.adminUi.api !== 'object' || typeof Nino.adminUi.api.onSessionLost !== 'function' )
					return;

				// Escape would give up the requests that wait for a login; while one
				// can still bring them back it does not. Where it cannot (another
				// account, or nothing waits any more) the dialog may be closed
				el.dialog.addEventListener( 'cancel', function( ev ) {
					if( Nino.admin.sessionDialog._reason === 'expired' && Nino.adminUi.api.waiting() === true )
						ev.preventDefault();
				} );

				// However it was closed, the requests that waited are told: the
				// panels show their error and give their forms back, and the input
				// can be copied out
				el.dialog.addEventListener( 'close', function() {
					el.pw.value = '';
					Nino.adminUi.api.dismiss();
				} );
				el.close.addEventListener( 'click', function() { Nino.admin.sessionDialog._close( el ) } );
				el.reload.addEventListener( 'click', function() { wn.location.reload() } );
				el.form.addEventListener( 'submit', function( ev ) {
					ev.preventDefault();
					Nino.admin.sessionDialog._login( el, Nino.admin.sessionDialog._check );
				} );

				Nino.adminUi.api.onSessionLost( Nino.admin.sessionDialog._open );
			},

			/**
			 *	The dialog's parts, or null where the page has none
			 *
			 *	@return		{Object|null}
			 */
			_elements : function() {

				const get = function( name ) { return dc.getElementById( 'admin-session-'+ name ) };
				const el = { dialog : get('dialog'), form : get('form'), title : get('title'), text : get('text'), user : get('user'), pw : get('pw'), msg : get('msg'), submit : get('submit'), reload : get('reload'), close : get('close') };

				return Object.keys( el ).every( function( name ) { return el[name] !== null && typeof el[name] !== 'undefined' } ) ? el : null;
			},

			/**
			 *	Show the dialog: asking for a login where the session ended, or
			 *	only offering the reload where another account has logged in
			 *	since - a form filled in for one account must not be saved by
			 *	another, so there is nothing to log in for
			 *
			 *	@param		{Function}	check					The api's "whose session is this now", for after a login
			 *	@param		{string}		reason				'expired' or 'other'
			 *
			 *	@return		void
			 */
			_open : function( check, reason ) {

				const el = Nino.admin.sessionDialog._elements();
				const other = reason === 'other';

				Nino.admin.sessionDialog._check = check;
				Nino.admin.sessionDialog._reason = reason;

				el.title.textContent = Nino.content.getText( other ? '/_admin/common/session/other_title' : '/_admin/common/session/title' );
				el.text.textContent = Nino.content.getText( other ? '/_admin/common/session/other' : '/_admin/common/session/expired' );
				el.user.parentNode.hidden = other;
				el.pw.parentNode.hidden = other;
				el.submit.hidden = other;
				el.msg.textContent = '';

				if( el.dialog.open !== true ) {
					if( typeof el.dialog.showModal === 'function' )
						el.dialog.showModal();
					else
						el.dialog.setAttribute( 'open', '' );
				}

				( other ? el.reload : el.user ).focus();
			},

			/**
			 *	Close the dialog without a login: the requests that waited are
			 *	released by the dialog's close (see init()), or here where the
			 *	browser has no dialog element to fire one
			 *
			 *	@param		{Object}		el
			 *
			 *	@return		void
			 */
			_close : function( el ) {

				if( typeof el.dialog.close === 'function' ) {
					el.dialog.close();
					return;
				}

				el.dialog.removeAttribute('open');
				el.pw.value = '';
				Nino.adminUi.api.dismiss();
			},

			/**
			 *	Log in from the dialog, then let the api ask again whose session
			 *	this is
			 *
			 *	@param		{Object}		el
			 *	@param		{Function}	check
			 *
			 *	@return		void
			 */
			_login : function( el, check ) {

				const say = function( key, ...params ) { el.msg.textContent = Nino.adminUi.format( Nino.content.getText( key ), ...params ) };

				if( el.user.value === '' || el.pw.value === '' ) {
					say( '/_admin/common/session/wrong' );
					return;
				}

				// Ask whose session this is now, and let the dialog follow. A
				// session that is still gone after the login (the status is the
				// login's) is said, not left as a button that did nothing
				const ask = function( loginStatus ) {
					check( function( outcome, status ) {

						el.submit.disabled = false;

						if( outcome === 'resumed' )
							el.dialog.close();
						else if( outcome === 'failed' )
							say( '/_admin/common/session/error', status );
						else if( outcome === 'expired' )
							say( '/_admin/common/session/error', loginStatus );
					} );
				};

				el.msg.textContent = '';
				el.submit.disabled = true;

				Nino.http.sendRequest( Nino.dir+ '/.nino/auth/login', 'POST', function( xhr ) {

					// The same account signed in elsewhere while the dialog was open
					// and rotated the token: trying again with this one cannot
					// succeed, the check fetches the new one (and may find the
					// session back)
					if( xhr.status === 403 ) {
						ask( xhr.status );
						return;
					}

					if( xhr.status !== 200 ) {
						el.submit.disabled = false;
						if( xhr.status === 401 )
							say( '/_admin/common/session/wrong' );
						else
							say( '/_admin/common/session/error', xhr.status );
						return;
					}

					// Signing in rotated the session and its token; the api asks for
					// both again and sends the waiting requests with them
					el.pw.value = '';
					ask( xhr.status );
				}, {}, { user : el.user.value, pw : el.pw.value } );
			},
		},

		/**
		 *	Manual light/dark override for the admin dashboard, independent
		 *	of the device's OS-level dark mode setting - some admins prefer
		 *	dark everywhere except here. Purely a local browser preference
		 *	(localStorage), not site data, so it never touches the server.
		 *	Defaults to following the OS setting (no override stored).
		 */
		theme : {

			STORAGE_KEY : 'nino-admin-theme',

			/**
			 *	Apply whatever's stored (if anything) and wire up the toggle
			 *	button's click handler
			 *
			 *	@return		void
			 */
			init : function() {

				const btn = dc.getElementById('admin-theme-toggle');
				if( btn === null )
					return;

				Nino.admin.theme._apply( Nino.admin.theme._stored() );
				btn.addEventListener( 'click', Nino.admin.theme._toggle );
			},

			/**
			 *	Read the stored override, if any
			 *
			 *	@return		{string}	'light' | 'dark' | '' (follow OS setting)
			 */
			_stored : function() {
				try {
					return wn.localStorage.getItem( Nino.admin.theme.STORAGE_KEY ) || '';
				} catch(e) {
					return '';
				}
			},

			/**
			 *	Set (or clear) the override and reflect it on <html> and the
			 *	toggle button's label
			 *
			 *	@param		{string}	value		'light' | 'dark' | ''
			 *
			 *	@return		void
			 */
			_apply : function( value ) {

				if( value === '' ) {
					dE.removeAttribute('data-theme');
					bd.className = '';
					return;
				}

				dE.setAttribute( 'data-theme', value );
				bd.className = 'theme-'+ value;
			},

			/**
			 *	Cycle: follow OS -> light -> dark -> follow OS
			 *
			 *	@return		void
			 */
			_toggle : function() {

				const current = Nino.admin.theme._stored();
				const next = ( current === '' ) ? 'light' : ( current === 'light' ? 'dark' : '' );

				try {
					if( next === '' )
						wn.localStorage.removeItem( Nino.admin.theme.STORAGE_KEY );
					else
						wn.localStorage.setItem( Nino.admin.theme.STORAGE_KEY, next );
				} catch(e) {}

				Nino.admin.theme._apply( next );
			},
		},

		/**
		 *	The bar's theme settings menu (theme toggle + locale picker) -
		 *	click/tap-toggled, not :hover: touch devices have no real hover
		 *	state (only inconsistent tap-simulated ones, with no equivalent
		 *	"un-hover" gesture to close it again), and a :hover-only menu
		 *	build from a plain <div> is also unreachable by keyboard on any
		 *	device. Open/closed state is the .admin-hidden utility class
		 *	every other panel's drill-down levels already use.
		 */
		navUi : {

			/**
			 *	Wire up the toggle button, click-outside, and Escape
			 *
			 *	@return		void
			 */
			init : function() {

				const wrap 		= dc.getElementById('admin-nav-ui');
				const toggle	= dc.getElementById('admin-nav-ui-toggle');
				const menu 		= dc.getElementById('admin-nav-ui-menu');

				if( wrap === null || toggle === null || menu === null )
					return;

				toggle.addEventListener( 'click', function( ev ) {
					ev.stopPropagation();
					const isOpen = menu.classList.toggle('admin-hidden') === false;
					toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
				} );

				dc.addEventListener( 'click', function( ev ) {
					if( wrap.contains( ev.target ) === false )
						Nino.admin.navUi.close();
				} );

				dc.addEventListener( 'keydown', function( ev ) {
					if( ev.key === 'Escape' )
						Nino.admin.navUi.close();
				} );
			},

			/**
			 *	Close the menu (safe to call whether it's open or not)
			 *
			 *	@return		void
			 */
			close : function() {
				const toggle	= dc.getElementById('admin-nav-ui-toggle');
				const menu 		= dc.getElementById('admin-nav-ui-menu');
				if( menu === null || toggle === null )
					return;
				menu.classList.add('admin-hidden');
				toggle.setAttribute( 'aria-expanded', 'false' );
			},
		},

		/**
		 *	The rail's group headings and the phone's select. A heading is a
		 *	button that folds the links under it (aria-expanded says which
		 *	state it is in); the fold is a preference of the browser, kept in
		 *	localStorage like the rail's own, and everything is open until
		 *	somebody closes it. The panel on screen always has its group open -
		 *	selectTab() asks for that through show(). On a folded rail the headings
		 *	are dividers: nothing is hidden there and they are not in the tab
		 *	order; below the sidebar breakpoint the headings are not drawn at all.
		 *
		 *	Which links belong to which heading is the order the server rendered
		 *	them in (see Panels::navHtml()): a heading, then its links. Without
		 *	headings - an account that sees one group - there is nothing to fold.
		 *
		 *	Below 64rem the strip of links gives way to a select built from the
		 *	same rail, one optgroup per heading; picking an entry is the same
		 *	move as clicking its link. The stylesheet hides the strip only when
		 *	the select exists (the nav's --select class), the select only on the
		 *	desktop.
		 */
		navGroups : {

			STORAGE_KEY : 'nino-admin-nav-groups',

			_nav			: null,
			_select		: null,
			// [ { name, button, links[] } ] in the rail's order
			_groups		: [],

			/**
			 *	Read the rail the server rendered, build the select and apply the
			 *	stored state
			 *
			 *	@param		{Function}	choose					Called with a panel name when the select changes
			 *
			 *	@return		void
			 */
			init : function( choose ) {

				const nav = dc.getElementById('admin-nav-wrap');
				if( nav === null )
					return;

				const plain = [];
				let group = null;

				Array.from( nav.children ).forEach( function( child ) {

					if( child.classList.contains('nino-admin-nav-group') ) {
						const entry = { name : child.dataset.group, button : child, links : [] };
						group = entry;
						Nino.admin.navGroups._groups.push( entry );
						child.addEventListener( 'click', function() { Nino.admin.navGroups.toggle( entry.name ) } );
						return;
					}

					if( child.dataset.panel === undefined )
						return;

					if( group === null )
						plain.push( child );
					else
						group.links.push( child );
				} );

				Nino.admin.navGroups._nav = nav;

				const option = function( link ) {
					const label = link.querySelector('.nino-admin-nav-label');
					const el = dc.createElement('option');
					el.value = link.dataset.panel;
					el.textContent = label === null ? link.dataset.panel : label.textContent;
					return el;
				};

				const select = dc.createElement('select');
				select.className = 'nino-admin-nav-select';
				select.setAttribute( 'aria-label', nav.getAttribute('aria-label') ?? '' );
				plain.forEach( function( link ) { select.appendChild( option( link ) ) } );
				Nino.admin.navGroups._groups.forEach( function( entry ) {
					const optgroup = dc.createElement('optgroup');
					optgroup.label = entry.button.textContent;
					entry.links.forEach( function( link ) { optgroup.appendChild( option( link ) ) } );
					select.appendChild( optgroup );
				} );
				select.addEventListener( 'change', function() { choose( select.value ) } );

				nav.insertBefore( select, nav.firstChild );
				nav.classList.add('nino-admin-nav--select');
				Nino.admin.navGroups._select = select;

				Nino.admin.navGroups.apply();
			},

			/**
			 *	The names of the groups folded in this browser
			 *
			 *	@return		{Array<string>}
			 */
			_stored : function() {
				try {
					const names = JSON.parse( wn.localStorage.getItem( Nino.admin.navGroups.STORAGE_KEY ) || '[]' );
					return Array.isArray( names ) ? names : [];
				} catch(e) {
					return [];
				}
			},

			/**
			 *	@param		{Array<string>}	names
			 *
			 *	@return		void
			 */
			_store : function( names ) {
				try {
					if( names.length === 0 )
						wn.localStorage.removeItem( Nino.admin.navGroups.STORAGE_KEY );
					else
						wn.localStorage.setItem( Nino.admin.navGroups.STORAGE_KEY, JSON.stringify( names ) );
				} catch(e) {}
			},

			/**
			 *	Fold a group, or open it again
			 *
			 *	@param		{string}		name
			 *
			 *	@return		void
			 */
			toggle : function( name ) {

				// A folded rail shows every link; there is nothing to fold
				if( Nino.admin.rail.folded() === true )
					return;

				const names = Nino.admin.navGroups._stored();
				const at = names.indexOf( name );

				if( at === -1 )
					names.push( name );
				else
					names.splice( at, 1 );

				Nino.admin.navGroups._store( names );
				Nino.admin.navGroups.apply();
			},

			/**
			 *	Say which panel is on screen: its group is opened - folded or not -
			 *	and the select shows it
			 *
			 *	@param		{string}		panel
			 *
			 *	@return		void
			 */
			show : function( panel ) {

				const groups = Nino.admin.navGroups;

				if( groups._select !== null )
					groups._select.value = panel;

				const entry = groups._groups.find( function( g ) {
					return g.links.some( function( link ) { return link.dataset.panel === panel } );
				} );

				const names = groups._stored();
				if( entry !== undefined && names.indexOf( entry.name ) !== -1 ) {
					groups._store( names.filter( function( name ) { return name !== entry.name } ) );
					groups.apply();
				}
			},

			/**
			 *	Put the stored state on the headings and their links. Not on a
			 *	folded rail, where every link stays and the headings are dividers
			 *	nobody tabs to
			 *
			 *	@return		void
			 */
			apply : function() {

				const groups = Nino.admin.navGroups;
				if( groups._nav === null )
					return;

				const folded = Nino.admin.rail.folded();
				const names = groups._stored();

				groups._groups.forEach( function( entry ) {

					const closed = folded === false && names.indexOf( entry.name ) !== -1;

					entry.button.setAttribute( 'aria-expanded', closed ? 'false' : 'true' );
					entry.button.tabIndex = folded ? -1 : 0;
					entry.links.forEach( function( link ) { link.classList.toggle( 'nino-admin-nav-collapsed', closed ) } );
				} );
			},
		},

		/**
		 *	The rail's fold - desktop only, below the sidebar breakpoint the
		 *	rail is a top bar and the classes set here are inert (see
		 *	style.css). Two states an account can pin, open or folded,
		 *	kept in localStorage exactly like the theme override, and one it
		 *	gets without asking: open on a page panel, folded on a workspace
		 *	panel (see layout() in the panel contract), so the Template
		 *	Builder opens wide without a click and the dashboard opens with
		 *	its labels. selectTab() reports which kind is on screen.
		 */
		rail : {

			STORAGE_KEY : 'nino-admin-rail',
			_workspace : false,

			/**
			 *	Wire up the fold button and apply the stored state
			 *
			 *	@return		void
			 */
			init : function() {

				const btn = dc.getElementById('admin-rail-toggle');
				if( btn === null )
					return;

				btn.addEventListener( 'click', Nino.admin.rail.toggle );
				Nino.admin.rail._apply();
			},

			/**
			 *	Read the pinned state, if any
			 *
			 *	@return		{string}	'open' | 'folded' | '' (whatever the panel implies)
			 */
			_stored : function() {
				try {
					return wn.localStorage.getItem( Nino.admin.rail.STORAGE_KEY ) || '';
				} catch(e) {
					return '';
				}
			},

			/**
			 *	Told by selectTab() which kind of panel is on screen
			 *
			 *	@param		{boolean}	workspace		true for a layout() === 'workspace' panel
			 *
			 *	@return		void
			 */
			layout : function( workspace ) {
				Nino.admin.rail._workspace = workspace === true;
				Nino.admin.rail._apply();
			},

			/**
			 *	Whether the rail is folded right now: the pinned state when
			 *	there is one, else what the panel on screen implies
			 *
			 *	@return		{boolean}
			 */
			folded : function() {
				const stored = Nino.admin.rail._stored();
				return stored === 'folded' || ( stored === '' && Nino.admin.rail._workspace === true );
			},

			/**
			 *	Reflect layout and fold on the shell and the button
			 *
			 *	@return		void
			 */
			_apply : function() {

				const wrap = dc.getElementById('admin-page-wrap');
				if( wrap === null )
					return;

				const folded = Nino.admin.rail.folded();
				wrap.classList.toggle( 'nino-admin-shell--workspace', Nino.admin.rail._workspace );
				wrap.classList.toggle( 'nino-admin-shell--folded', folded );

				const btn = dc.getElementById('admin-rail-toggle');
				if( btn !== null )
					btn.setAttribute( 'aria-pressed', folded ? 'true' : 'false' );

				// The group headings are dividers on a folded rail, and what they
				// folded is open again until it is unfolded
				Nino.admin.navGroups.apply();
			},

			/**
			 *	Pin the opposite of what is on screen. Pinning what the panel
			 *	would have implied anyway unpins instead, so a rail folded by
			 *	hand on the dashboard and opened again by hand is back to
			 *	following the panels
			 *
			 *	@return		void
			 */
			toggle : function() {

				const next 		= Nino.admin.rail.folded() ? 'open' : 'folded';
				const implied	= Nino.admin.rail._workspace === true ? 'folded' : 'open';

				try {
					if( next === implied )
						wn.localStorage.removeItem( Nino.admin.rail.STORAGE_KEY );
					else
						wn.localStorage.setItem( Nino.admin.rail.STORAGE_KEY, next );
				} catch(e) {}

				Nino.admin.rail._apply();
			},
		},

		/**
		 *	Build a url to a file the framework itself serves (eg. an uploaded
		 *	image) - reads the site's deploy-path prefix from #admin-page-wrap's
		 *	data-dir (filled server-side from /nino/dir), since
		 *	that's not otherwise known to js and can be a subdirectory rather
		 *	than site root
		 *
		 *	@param		{string}	path					Path starting with "/" (eg. "/images/x.jpg")
		 *
		 *	@return		{string}
		 */
		assetUrl : function( path ) {
			return ( dc.getElementById('admin-page-wrap').dataset.dir ?? '' )+ path;
		},

		/**
		 *	The url of something a browser loads directly - an uploaded image,
		 *	a bundled stylesheet. Those live under the public content
		 *	directory, one level below the project root (see
		 *	\Nino\Filesystem::getPublicDir()), unlike a link into /_admin
		 *
		 *	@param		{string}	path			Eg. '/images/hero.jpg'
		 *
		 *	@return		{string}
		 */
		publicUrl : function( path ) {
			return ( dc.getElementById('admin-page-wrap').dataset.public ?? '' )+ path;
		},

		/**
		 *	The common pinned context row used by every drill-down level.
		 *	Locale selects can be appended after creation by the calling module.
		 *
		 *	@param		{Element}	backLink
		 *
		 *	@return		{Element}
		 */
		formToolbar : function( backLink ) {
			return Nino.adminUi.contextBar( backLink );
		},

		/**
		 *	Decode html entities back to plain text (eg. "&amp;" -> "&") -
		 *	Submissions/Newsletter fields are stored htmlspecialchars()-encoded
		 *	(see Modules\Form/Newsletter::callbackResponse()), so anything
		 *	rendering their raw value for display or export needs this first.
		 *	Safe regardless of what the string contains: assigning to a
		 *	detached <textarea>'s innerHTML never executes markup, it's
		 *	always treated as literal text content - unlike assigning to a
		 *	real element's innerHTML, this never depends on the string
		 *	actually being fully escaped to stay safe
		 *
		 *	@param		{string}	str
		 *
		 *	@return		{string}
		 */
		decodeEntities : function( str ) {
			const el = dc.createElement('textarea');
			el.innerHTML = str;
			return el.value;
		},

		/**
		 *	Trigger a client-side CSV download from an array of plain, flat
		 *	objects - shared by the Form and Newsletter modules' panels (see
		 *	their assets/admin.js), no server endpoint needed since both panels
		 *	already have the full entries array loaded for their list view.
		 *	Column order follows the order the keys first appear in
		 *
		 *	@param		{string}	filename			Download filename (eg. "newsletter.csv")
		 *	@param		{Array}		rows					Array of plain objects; rows may differ in shape
		 *
		 *	@return		void
		 */
		exportCsv : function( filename, rows ) {

			if( rows.length === 0 )
				return;

			// The union of every row's keys, in the order they first appear,
			// not the first row's alone. The Submissions panel deliberately
			// lists several forms in one view ("All forms"), so the first
			// entry's fields are not the file's columns: every field the other
			// forms carry and it does not was dropped from the export without
			// a word, and one entry recorded before ids existed - no 'id', no
			// 'form' - took those two columns down for every row below it
			const headers = [];
			rows.forEach( function( row ) {
				Object.keys( row ).forEach( function( key ) {
					if( headers.indexOf( key ) === -1 )
						headers.push( key );
				} );
			} );
			const lines = [ headers.map( Nino.admin.csvCell ).join(',') ].concat(
				rows.map( function( row ) { return headers.map( function( key ) { return Nino.admin.csvCell( row[key] ) } ).join(',') } )
			);

			// Leading BOM so Excel (still the most common CSV consumer) detects
			// UTF-8 instead of guessing a local codepage and mangling umlauts
			const blob = new Blob( [ '\uFEFF'+ lines.join('\r\n') ], { type : 'text/csv;charset=utf-8;' } );
			const url = URL.createObjectURL( blob );
			const a = dc.createElement('a');
			a.href = url;
			a.download = filename;
			dc.body.appendChild( a );
			a.click();
			a.remove();
			URL.revokeObjectURL( url );
		},

		/**
		 *	Escape one CSV cell and neutralize spreadsheet formulas. Prefixing an
		 *	apostrophe is the convention spreadsheet apps use for explicit text;
		 *	it also covers formula markers hidden behind whitespace.
		 *
		 *	@param		{*}	value
		 *
		 *	@return		{string}
		 */
		csvCell : function( value ) {
			let str = Nino.admin.decodeEntities( String( value ?? '' ) );
			if( /^[\t\r\n ]*[=+\-@]/.test( str ) === true )
				str = "'"+ str;
			return /["\r\n,]/.test( str ) ? '"'+ str.replace( /"/g, '""' )+ '"' : str;
		},

		/**
		 *	Wire up the workbench shell: logout button, and the nav that
		 *	switches between the panels the registry rendered
		 *
		 *	@return		void
		 */
		onReady : function() {

			Nino.admin.theme.init();
			Nino.admin.navUi.init();
			Nino.admin.rail.init();
			Nino.admin.sessionDialog.init();

			// The picker's <option> values are already real ?locale=xx
			// targets (see Admin::_localePickerHtml()) - navigating there
			// is all that's needed, Admin::init() reads it back server-side
			const localePicker = dc.getElementById('admin-localepicker');
			if( localePicker !== null )
				localePicker.addEventListener( 'change', function() {

					const picker = this;

					// Another interface language reloads the page and every form on it
					// The #hash goes along: the panel and the level the page was on
					// are what the person comes back to in the other language
					Nino.admin.dirty.guard( null, function() { wn.location.href = picker.value + wn.location.hash }, function() {
						Array.from( picker.options ).forEach( function( option ) { option.selected = option.defaultSelected } );
					}, true );
				} );

			const el = {
				'pageWrap'		: dc.getElementById('admin-page-wrap'),
				'userLogout'	: dc.getElementById('admin-user-logout'),
			};

			// The panels are whatever the server rendered into the rail (see
			// Admin::panels() - core and module panels alike): one nav link
			// per panel, carrying its name in data-panel, and one pane with
			// the same name. A panel's script attaches to Nino.admin.<name>
			// and answers showCurrent() when its tab is selected - a panel
			// without a script (or without that function) simply shows its
			// pane. A pane may hold tabs (see Panels::panesHtml()): a strip
			// of buttons and one tab pane per tab, each tab a panel of its
			// own with its own script and hash prefix. Nothing in here names
			// a panel, and nothing in here decides who sees one: the server
			// rendered only the panels this account may use (see
			// Admin::visiblePanels()).
			//
			// panel name (matches the hash) -> [ nav link, pane ]
			const panels = {};
			dc.querySelectorAll('#admin-nav-wrap a[data-panel]').forEach( function( link ) {
				panels[link.dataset.panel] = [ link, dc.getElementById( 'admin-content-'+ link.dataset.panel ) ];
			} );

			// tab name -> the panel whose pane holds it, and per panel the tab
			// last open, so coming back to a panel lands where you left it.
			// Only a pane's direct children count: a panel's own markup (the
			// Template Builder's, say) may use data-tab for its own purposes
			const tabOwner = {};
			const lastTab  = {};
			Object.keys( panels ).forEach( function( panel ) {
				if( panels[panel][1] !== null )
					panels[panel][1].querySelectorAll(':scope > div[data-tab]').forEach( function( pane ) { tabOwner[pane.dataset.tab] = panel } );
			} );

			const links = Object.keys( panels ).map( function( panel ) { return panels[panel][0] } );

			/**
			 *	Hand a panel, or one of its tabs, the screen: its state class
			 *	first (router.set() ignores writes from a panel that isn't on
			 *	screen), then its own "re-show whatever it's currently on",
			 *	which is also what re-syncs the hash
			 *
			 *	@param		{string}	name					A panel or tab uri
			 *
			 *	@return		void
			 */
			function show( name ) {
				// Swap the state class without touching the rest: the shell also
				// carries its design-system classes (see style.css), and
				// assigning className outright dropped them
				Nino.adminUi.setStateClass( el.pageWrap, 'show-'+ name );
				const module = Nino.admin[name];
				if( module && typeof module.showCurrent === 'function' )
					module.showCurrent();
			}

			/**
			 *	Switch panel and mark the clicked link active - each panel keeps
			 *	its own drill-down state, switching never resets it, so jumping
			 *	back and forth is always exactly where you left it. A pane with
			 *	tabs opens on the requested one, else the one last open, else
			 *	its first
			 *
			 *	@param		{string}	panel					Panel name
			 *	@param		{string}	[tab]					One of its tabs
			 *	@param		{boolean}	[push]				The person's own move - a click on the rail, a tab or the phone's select - so it adds a history entry for Back to return to. Left out, the address only follows (the hash on load, a step through the history, the arrow keys of a tab strip)
			 *
			 *	@return		void
			 */
			function selectTab( panel, tab, push ) {

				// Consumed by the first write to the address, wherever it comes from:
				// a panel's own showCurrent() or settle() below
				Nino.admin.router._push = push === true;

				try {
					openTab( panel, tab );
				}
				finally {
					Nino.admin.router._push = false;
				}
			}

			/**
			 *	What selectTab() does, without the history entry
			 *
			 *	@param		{string}	panel
			 *	@param		{string}	[tab]
			 *
			 *	@return		void
			 */
			function openTab( panel, tab ) {
				const target = panels[panel];
				if( target === undefined )
					return;
				/*	Which panel is open, said as well as drawn: the rail marked it
					with a class the stylesheet paints and nothing else, so a screen
					reader read the navigation as twelve links with nothing to tell
					them apart. aria-current is removed rather than set to "false"
					on the others - "false" is a value the attribute has, and it
					reads as "this one is not the current page", which is not
					something eleven links need to say	*/
				links.forEach( function( t ) {

					const open = t === target[0];

					t.classList.toggle( 'active', open );

					if( open === true )
						t.setAttribute( 'aria-current', 'page' );
					else
						t.removeAttribute( 'aria-current' );
				} );
				// The panes start hidden (see Panels::panesHtml()); showing one
				// is a plain attribute flip, so no stylesheet has to know the
				// panel names either
				dc.querySelectorAll('#admin-content-wrap > [data-panel]').forEach( function( pane ) { pane.hidden = pane.dataset.panel !== panel } );
				// A workspace panel (layout() in the panel contract, carried
				// on the link as data-layout) gets the whole width and, unless
				// the account pinned the rail, a folded one - see rail
				Nino.admin.rail.layout( target[0].dataset.layout === 'workspace' );
				// ...and its group is open, whatever was folded
				Nino.admin.navGroups.show( panel );

				const pane 			= target[1];
				const tabPanes	= pane === null ? [] : Array.from( pane.querySelectorAll(':scope > div[data-tab]') );

				if( tabPanes.length === 0 ) {
					show( panel );
					Nino.admin.router.settle( panel );
					return;
				}

				const names 	= tabPanes.map( function( p ) { return p.dataset.tab } );
				const current = names.indexOf( tab ) !== -1 ? tab : ( names.indexOf( lastTab[panel] ) !== -1 ? lastTab[panel] : names[0] );
				lastTab[panel] = current;

				tabPanes.forEach( function( p ) { p.hidden = p.dataset.tab !== current } );
				pane.querySelectorAll(':scope > .admin-panel-head > .admin-panel-tabs > button[data-tab]').forEach( function( btn ) {
					const active = btn.dataset.tab === current;
					btn.classList.toggle( 'is-active', active );
					btn.setAttribute( 'aria-selected', active ? 'true' : 'false' );
					// One tab stop per strip, the tab that is open - see
					// Nino.adminUi.tabKeys(), which moves it from the arrows
					btn.tabIndex = active ? 0 : -1;
				} );

				show( current );
				Nino.admin.router.settle( current );
			}

			/**
			 *	Select whichever panel or tab the current #hash points at,
			 *	defaulting to the first panel in the rail when there's none (or
			 *	it doesn't name a real one) - the panel's own init() separately
			 *	drills into the exact sub-state once its data has loaded.
			 *
			 *	The first panel rather than a named one: every screen is a
			 *	module (see Admin::modules()), Dashboard included, and a
			 *	workbench delivered without it - or an account that may not
			 *	see it - still has to land somewhere. With no panel at all
			 *	there is nothing to select and nothing to do
			 *
			 *	@return		void
			 */
			function selectTabFromHash() {
				const name = Nino.admin.router.current().panel;
				if( panels[name] !== undefined )
					return selectTab( name );
				if( tabOwner[name] !== undefined )
					return selectTab( tabOwner[name], name );
				const first = Object.keys( panels )[0];
				if( first !== undefined )
					selectTab( first );
			}

			// Bind events - the rail itself always stays visible, local
			// "‹ Back" links inside each panel handle drilling back up a level
			// The logout request goes out first and the page leaves in its answer,
			// so the browser's own question would come after the session is gone
			el.userLogout.addEventListener( 'click', function() {
				Nino.admin.dirty.guard( null, function() { Nino.auth.logout( '[[/nino/dir]]/_admin' ) }, undefined, true );
			} );
			// The link is a real one (href="#<panel>"): a click that asks the
			// browser for something else - a new tab, a new window, a download -
			// is its own to answer, the plain one is the workbench's
			Object.keys( panels ).forEach( function( panel ) {
				panels[panel][0].addEventListener( 'click', function( ev ) {
					if( ev.defaultPrevented === true || ( ev.button || 0 ) !== 0 || ev.metaKey === true || ev.ctrlKey === true || ev.shiftKey === true || ev.altKey === true )
						return;
					ev.preventDefault();
					selectTab( panel, undefined, true );
				} );
			} );
			// The strip stands in the pane's head, beside the panel's name
			// (see Panels::panesHtml()) - a strip a panel's own script puts
			// there later (Nino.adminUi.panelHead()) drives itself
			dc.querySelectorAll('#admin-content-wrap > [data-panel] > .admin-panel-head > .admin-panel-tabs').forEach( function( strip ) {

				const owner 	= strip.closest('[data-panel]').dataset.panel;
				const buttons	= Array.from( strip.querySelectorAll(':scope > button[data-tab]') );

				buttons.forEach( function( btn, at ) {

					btn.addEventListener( 'click', function() { selectTab( owner, btn.dataset.tab, true ) } );

					/*	A strip nobody has opened yet still has to say which of its
						tabs is the one on screen. The panes are rendered with every
						button aria-selected="false" (see Panels::$html) and only the
						panel the shell opens on ever went through selectTab(), so
						every other strip announced a tablist with no selected tab and
						handed out one tab stop per tab. The first one is what
						selectTab() will open when the panel is reached, so the strip
						says so from the start	*/
					btn.classList.toggle( 'is-active', at === 0 );
					btn.setAttribute( 'aria-selected', at === 0 ? 'true' : 'false' );
					btn.tabIndex = at === 0 ? 0 : -1;
				} );

				// The arrows, Home and End, from the design system - the strip is
				// announced as a tablist, so it answers to the keys one answers to
				Nino.adminUi.tabKeys( buttons, function( at ) { selectTab( owner, buttons[at].dataset.tab ) } );
			} );

			// The group headings and, below 64rem, the select that stands for the
			// strip - read from the rail as rendered, before the first panel is
			// selected so that one finds its group and its entry
			Nino.admin.navGroups.init( function( panel ) { selectTab( panel, undefined, true ) } );

			// Restore the panel from a refresh/deep link, and react to manual hash
			// edits or the browser back/forward buttons (our own updates use
			// pushState and replaceState, which never fire hashchange, so this only
			// ever reacts to real user navigation - and a step through the history
			// never adds an entry itself: selectTab() is not asked to push here)
			Nino.admin.router.start();
			selectTabFromHash();
			wn.addEventListener( 'hashchange', function() {
				try {
					if( Nino.admin.router.arrive() === true )
						selectTabFromHash();
				}
				finally {
					// What was answered synchronously is over; a question that is open
					// holds its own reference to the step (see router.leave())
					Nino.admin.router._step = null;
				}
			} );
			// A traversal between two entries of the same address fires no
			// hashchange; the number of the entry the browser is on still follows
			wn.addEventListener( 'popstate', function() {
				Nino.admin.router._index = Nino.admin.router._stateIndex() ?? Nino.admin.router._index;
			} );

			// A form that failed to save is brought on screen by the name of
			// its panel or tab - a tab first, since 'elements' is both
			Nino.admin.dirty.init( function( name ) {
				Nino.admin.router._keep = true;
				try {
					if( tabOwner[name] !== undefined )
						selectTab( tabOwner[name], name );
					else
						selectTab( name );
				}
				finally {
					Nino.admin.router._keep = false;
				}
			} );

		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.onReady );

})(window, document, document.documentElement, document.body);
