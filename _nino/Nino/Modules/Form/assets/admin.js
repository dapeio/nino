

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js								The Form module's /_admin panel, "Submissions": every
 *													submission \Nino\Form records (in addition to the mail
 *													itself) - see Modules\Form\Admin beside this file.
 *													Ships with the module: it is in the workbench bundle
 *													exactly while the module is active.
 *
 *													The panel knows no field names of its own. A project
 *													may define any number of forms with fields of their
 *													own (\Nino\Form::FORMS), so what a card shows is
 *													whatever the entry carries, labelled from the form it
 *													belongs to - and a filter for the form plus a search
 *													over the values, because one list holding every form's
 *													inquiries is a long list.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.submissions = {

		// What the last list answered: the entries, most recent first, and
		// key => { name, fields } for the forms they belong to
		_entries : [],
		_forms 	 : {},
		// Which cap of \Nino\Form refuses submissions right now: 'month',
		// 'hour' or '' - the list then says so above the cards
		_capped  : '',
		// Which form the list is narrowed to, '' for all of them, and what
		// the search box holds - kept across a re-render, dropped by a reload
		// that no longer knows the form
		_form 	 : '',
		_filter  : '',
		// Where the focus goes once the list is drawn after a deletion: the place of the card that was deleted, -1 for nowhere
		_after   : -1,

		/**
		 *	Load the recorded submissions and render them. Same "always
		 *	re-fetch" shape as the Log panel's admin.js - there's no drill-down state to
		 *	preserve, and re-fetching on every tab switch keeps the list
		 *	current with whatever arrived since it was last open
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('submissions-list') === null )
				return;

			Nino.admin.submissions._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.submissions._showError( status, response );

				Nino.admin.submissions._entries = response.entries || [];
				Nino.admin.submissions._forms 	= response.forms || {};
				Nino.admin.submissions._capped	= response.capped || '';

				// A form that is gone - renamed, or deleted in the builder -
				// would otherwise hide every card with nothing on screen saying
				// why. Its submissions are still there and still shown
				if( Nino.admin.submissions._form !== '' && Nino.admin.submissions._forms[ Nino.admin.submissions._form ] === undefined )
					Nino.admin.submissions._form = '';

				Nino.admin.submissions._renderList();
			} );
		},

		/**
		 *	Re-fetch and re-show the list when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.admin.submissions.init();
		},

		/**
		 *	Call a submissions/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "submissions/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'submissions/'+ endpoint, payload, callback );
		},

		/**
		 *	Show a failed request's status/error
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( status, response ) {
			Nino.adminUi.showError( dc.getElementById('submissions-list'), status, response, '/_admin/submissions/error/load' );
		},

		/**
		 *	Whether one entry is what the two filters are looking for: the
		 *	form the select names, and the search box over every value it
		 *	carries - the label a value was collected under included, so
		 *	searching for "Subject" finds the cards that have one
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{boolean}
		 */
		_matches : function( entry ) {

			const query = Nino.admin.submissions._filter.trim().toLowerCase();

			if( Nino.admin.submissions._form !== '' && ( entry.form || '' ) !== Nino.admin.submissions._form )
				return false;

			if( query === '' )
				return true;

			const haystack = Nino.admin.submissions._fields( entry ).map( function( field ) {
				return field.label+ ' '+ field.value;
			} ).concat( [ entry.date || '', Nino.admin.submissions._when( entry ) ] ).join( ' ' );

			return haystack.toLowerCase().indexOf( query ) !== -1;
		},

		/**
		 *	One entry's values as the card shows them: everything it carries
		 *	that is not bookkeeping, in the order the form declares its
		 *	fields, each with the label it was collected under.
		 *
		 *	A value whose field the form no longer declares still shows, at
		 *	the end and under its own name - a form that lost a field must
		 *	not take the answers with it. And the decoding is
		 *	decodeEntities() + textContent throughout: \Nino\Form::record()
		 *	stored every value htmlspecialchars()'d, and this undoes that for
		 *	display without ever parsing the result as markup
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{Array}								[ { name, label, type, value }, ... ]
		 */
		_fields : function( entry ) {

			const form 	= Nino.admin.submissions._forms[ entry.form ] || { fields : [] };
			const skip 	= [ 'id', 'date', 'ip', 'form' ];
			const seen 	= [];
			const out 	= [];

			form.fields.forEach( function( field ) {
				if( entry[ field.name ] === undefined )
					return;
				seen.push( field.name );
				out.push( { name : field.name, label : field.label || field.name, type : field.type, value : Nino.admin.decodeEntities( String( entry[ field.name ] ) ) } );
			} );

			Object.keys( entry ).forEach( function( name ) {
				if( skip.indexOf( name ) !== -1 || seen.indexOf( name ) !== -1 )
					return;
				out.push( { name : name, label : name, type : 'text', value : Nino.admin.decodeEntities( String( entry[ name ] ) ) } );
			} );

			return out;
		},

		/**
		 *	The day and the minute an entry came in, as the workbench writes a moment
		 *	(Nino.adminUi.dateText()) - what the card shows, and so what the search finds
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{string}									The text the stored date gives, the stored date itself where it is none
		 */
		_when : function( entry ) {
			return Nino.adminUi.dateText( String( entry.date ?? '' ) ) || String( entry.date ?? '' );
		},

		/**
		 *	The head above the list: the form to narrow to - the select of the filter bar, left of the search - and the search
		 *	over what is left. The select is drawn only where there is more than one form to choose between - on the
		 *	single-form project this framework ships as, it would be a control with one option
		 *
		 *	@return		{Element}							<div class="nino-admin-filterbar">
		 */
		_renderHead : function() {

			const keys = Object.keys( Nino.admin.submissions._forms );
			let filter = null;

			if( keys.length > 1 )
				filter = {
					id			: 'submissions-form',
					label		: Nino.content.getText('/_admin/submissions/label/form'),
					value		: Nino.admin.submissions._form,
					options	: [ { value : '', label : Nino.content.getText('/_admin/submissions/label/form-all') } ].concat( keys.map( function( key ) {
						return { value : key, label : Nino.admin.submissions._forms[key].name || key };
					} ) ),
					onChange : function( value ) {
						// Same as the search below: which form is picked is a filter
						// over the cards that are already there
						Nino.admin.submissions._form = value;
						Nino.admin.submissions._applyFilter();
					},
				};

			/*	The cards are already in the document, so a keystroke only
				decides which of them are shown - it does not draw the list
				again. It used to: every card rebuilt, every value decoded
				again, and then a scrollHeight/clientHeight read per card to
				find the ones that overflow, which forces a layout each time.
				That also took the focus with it, which is why this listener
				used to put it back afterwards. Nothing is replaced now, so
				nothing loses it	*/
			return Nino.adminUi.filterBar( {
				filter : filter,
				search : {
					id			: 'submissions-search',
					label		: Nino.content.getText('/_admin/submissions/label/search'),
					value		: Nino.admin.submissions._filter,
					onInput	: function( query ) {
						Nino.admin.submissions._filter = query;
						Nino.admin.submissions._applyFilter();
					},
				},
			} ).el;
		},

		/**
		 *	Render the submissions the two filters let through, most recent
		 *	first (already sorted that way by the server)
		 *
		 *	@return		void
		 */
		_renderList : function() {

			const wrap = dc.getElementById('submissions-list');
			wrap.innerHTML = '';

			const all = Nino.admin.submissions._entries;

			// A cap that refuses submissions, said before anything else: a full
			// month's list is as long as it gets, and what is refused is not on it
			if( Nino.admin.submissions._capped === 'month' || Nino.admin.submissions._capped === 'hour' ) {
				const capped = Nino.adminUi.notice( Nino.content.getText( '/_admin/submissions/capped/'+ Nino.admin.submissions._capped ) );
				capped.id = 'submissions-capped';
				wrap.appendChild( capped );
			}

			// Nothing recorded at all, and nothing matching - two different
			// things, and the second one must not read as the first. This is
			// the first: no cards to draw and no filters to offer over them
			if( all.length === 0 ) {
				Nino.admin.submissions._after = -1;
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/submissions/empty') ) );
				return;
			}

			wrap.appendChild( Nino.admin.submissions._renderHead() );

			// ...and the second, kept in the document and shown by
			// _applyFilter() when the filters let nothing through
			const nomatch = Nino.adminUi.emptyState( Nino.content.getText('/_admin/submissions/nomatch') );
			nomatch.id = 'submissions-nomatch';
			nomatch.classList.add('admin-hidden');
			wrap.appendChild( nomatch );

			// What is on screen, not what is on disk: an export taken while a
			// form is selected is the export of that form. Read at the moment
			// of the click, since the filters move without the list being
			// drawn again
			const exportBtn = Nino.adminUi.button( Nino.content.getText('/_admin/submissions/label/export'), function() {
				Nino.admin.exportCsv( Nino.content.getText('/_admin/submissions/label/filename'), Nino.admin.submissions._entries.filter( Nino.admin.submissions._matches ) );
			}, { kind : 'primary', id : 'submissions-export' } );

			/*	Every recorded submission gets its card, once. Which of them
				are on screen is a class, not a redraw - see _applyFilter()	*/
			const ul = dc.createElement('ul');
			ul.id = 'submissions-entries';
			ul.className = 'nino-admin-list nino-admin-list-dense';

			all.forEach( function( entry ) {
				ul.appendChild( Nino.admin.submissions._renderEntry( entry ) );
			} );

			wrap.appendChild( ul );

			// The one bar of the list: its one main button is the export
			wrap.appendChild( Nino.adminUi.actionBar( { list : true, id : 'submissions-actions', primary : exportBtn } ).el );

			// Only show the "expand" hint on cards whose long value actually
			// overflows its collapsed (line-clamped) height - can only be
			// measured once the card is in the document, and now measured only
			// once per card rather than once per keystroke
			ul.querySelectorAll('.submissions-entry').forEach( function( li ) {
				const message = li.querySelector('.submissions-entry-message');
				if( message !== null && message.scrollHeight > message.clientHeight + 1 )
					li.classList.add('has-overflow');
			} );

			Nino.admin.submissions._applyFilter();

			if( Nino.admin.submissions._after >= 0 ) {

				const shown = Array.prototype.filter.call( ul.children, function( li ) { return li.classList.contains('admin-hidden') === false } );
				const next = shown[ Math.min( Nino.admin.submissions._after, shown.length - 1 ) ];

				Nino.admin.submissions._after = -1;

				if( next !== undefined )
					next.focus();
				else
					exportBtn.focus();
			}
		},

		/**
		 *	Show the cards the two filters let through and hide the rest -
		 *	the whole of what a keystroke in the search field does. The cards
		 *	themselves are built once, by _renderList()
		 *
		 *	@return		void
		 */
		_applyFilter : function() {

			const ul = dc.getElementById('submissions-entries');

			if( ul === null )
				return;

			const all = Nino.admin.submissions._entries;
			let shown = 0;

			Array.prototype.forEach.call( ul.children, function( li, index ) {

				const on = Nino.admin.submissions._matches( all[index] );

				li.classList.toggle( 'admin-hidden', on === false );

				if( on === true )
					shown++;
			} );

			// The "nothing matches" line, the list and the export row are the
			// three things that differ between "some" and "none"
			const nomatch = dc.getElementById('submissions-nomatch');
			const actions = dc.getElementById('submissions-actions');

			if( nomatch !== null )
				nomatch.classList.toggle( 'admin-hidden', shown > 0 );

			ul.classList.toggle( 'admin-hidden', shown === 0 );

			if( actions !== null )
				actions.classList.toggle( 'admin-hidden', shown === 0 );
		},

		/**
		 *	Render one submission as a card that expands: the date and which
		 *	form it came from, then every value it carries under the label it
		 *	was collected under. The first address becomes a mailto link, and
		 *	the longest text is the one that is clamped until the card is
		 *	opened - a page of message text per card would make a list of
		 *	twenty unreadable
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{Element}
		 */
		_renderEntry : function( entry ) {

			const li = dc.createElement('li');
			li.className = 'submissions-entry';
			li.tabIndex = 0;
			li.dataset.entry = entry.id || '';

			const fields = Nino.admin.submissions._fields( entry );

			const header = dc.createElement('div');
			header.className = 'submissions-entry-header';

			const date = dc.createElement('span');
			date.className = 'submissions-entry-date';
			date.textContent = Nino.admin.submissions._when( entry );
			header.appendChild( date );

			// Which form this is, where a project has more than one. With a
			// single form the badge would say the same word on every card
			if( Object.keys( Nino.admin.submissions._forms ).length > 1 ) {
				const form = dc.createElement('span');
				form.className = 'submissions-entry-cat';
				form.textContent = ( Nino.admin.submissions._forms[ entry.form ] || {} ).name || entry.form || '';
				header.appendChild( form );
			}

			li.appendChild( header );

			// The address to answer at, as a link - the one thing a reader
			// reaches for. Taken from the first email field the form declares,
			// so a form calling it 'contact' works like one calling it 'email'
			const mail = fields.filter( function( field ) { return field.type === 'email' && field.value !== '' } )[0];
			const long = fields.filter( function( field ) { return field.type === 'textarea' } )[0];

			if( mail !== undefined ) {

				const name = dc.createElement('div');
				name.className = 'submissions-entry-name';

				const link = dc.createElement('a');
				link.href = 'mailto:'+ mail.value;
				link.textContent = mail.value;
				// Following the mailto link shouldn't also toggle the card
				link.addEventListener( 'click', function( ev ) { ev.stopPropagation() } );

				name.appendChild( link );
				li.appendChild( name );
			}

			const list = dc.createElement('dl');
			list.className = 'submissions-entry-fields';

			fields.forEach( function( field ) {

				if( field === mail || field === long || field.value === '' )
					return;

				const term = dc.createElement('dt');
				term.textContent = field.label;
				list.appendChild( term );

				const value = dc.createElement('dd');
				value.textContent = field.value;
				list.appendChild( value );
			} );

			if( list.children.length > 0 )
				li.appendChild( list );

			if( long !== undefined ) {
				const message = dc.createElement('p');
				message.className = 'submissions-entry-message';
				message.textContent = long.value;
				li.appendChild( message );
			}

			const toggle = dc.createElement('span');
			toggle.className = 'submissions-entry-toggle';
			toggle.textContent = Nino.content.getText('/_admin/submissions/label/more');
			li.appendChild( toggle );

			// An entry recorded before submissions carried an id cannot be
			// addressed on its own, so it gets no button rather than one that
			// could remove the wrong row (see \Nino\Form::remove())
			if( ( entry.id || '' ) !== '' ) {

				const remove = Nino.adminUi.button( Nino.content.getText('/_admin/common/label/delete'), function( ev ) {
					ev.stopPropagation();
					Nino.admin.submissions._delete( entry, remove );
				}, { kind : 'danger', small : true, className : 'submissions-entry-delete' } );

				li.appendChild( remove );
			}

			li.addEventListener( 'click', Nino.admin.submissions._toggleEntry );
			li.addEventListener( 'keydown', function( ev ) {
				// Only a key pressed on the card itself opens it: Enter on its Delete button or its mail link is theirs
				if( ( ev.key !== 'Enter' && ev.key !== ' ' ) || ev.target !== li )
					return;
				ev.preventDefault();
				Nino.admin.submissions._toggleEntry.call( li );
			} );

			return li;
		},

		/**
		 *	Delete one submission, after asking. The list is read again
		 *	afterwards rather than the card removed here: what is on disk is
		 *	what this panel shows
		 *
		 *	@param		{Object}	entry
		 *	@param		{Element}	[opener]				The button that asked, which has the focus again when the question is closed
		 *
		 *	@return		void
		 */
		_delete : function( entry, opener ) {

			Nino.adminUi.confirm( 'destructive', {
				title			: Nino.content.getText('/_admin/submissions/confirm/delete-title'),
				message		: Nino.adminUi.format( Nino.content.getText('/_admin/submissions/confirm/delete'), Nino.admin.submissions._when( entry ) ),
				yes				: Nino.content.getText('/_admin/submissions/confirm/delete-title'),
				no				: Nino.content.getText('/_admin/common/label/cancel'),
				opener		: opener,
				onChoose	: function( ok ) {

					if( ok === false )
						return;

					Nino.admin.submissions._apiCall( 'delete', { id : entry.id }, function( status, response ) {
						if( status !== 200 || response === null )
							return Nino.admin.submissions._showError( status, response );

						// What was deleted is said, and the focus goes to the card that took its place
						Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/deleted') );
						Nino.admin.submissions._after = Nino.admin.submissions._entries.indexOf( entry );
						Nino.admin.submissions.init();
					} );
				},
			} );
		},

		/**
		 *	Expand/collapse a submission card (bound as its click listener,
		 *	so `this` is the card)
		 *
		 *	@return		void
		 */
		_toggleEntry : function() {

			this.classList.toggle('expanded');

			const toggle = this.querySelector('.submissions-entry-toggle');
			if( toggle !== null )
				toggle.textContent = this.classList.contains('expanded') ? Nino.content.getText('/_admin/submissions/label/less') : Nino.content.getText('/_admin/submissions/label/more');
		},
	};

})(window, document, document.documentElement, document.body);
