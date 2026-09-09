

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	editor.js								The Form module's /_admin panel, "Submissions": every
 *													submission \Nino\Form records (in addition to the mail
 *													itself) - see Modules\Form\Admin beside this file.
 *													Ships with the module: it is in the editor bundle
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
		// Which form the list is narrowed to, '' for all of them, and what
		// the search box holds - kept across a re-render, dropped by a reload
		// that no longer knows the form
		_form 	 : '',
		_filter  : '',

		/**
		 *	Load the recorded submissions and render them. Same "always
		 *	re-fetch" shape as logs.js - there's no drill-down state to
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
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'submissions/'+ endpoint, data : JSON.stringify( payload ) } );
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
			const wrap = dc.getElementById('submissions-list');
			wrap.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/submissions/error/load') );
			wrap.appendChild( p );
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
			} ).concat( [ entry.date || '' ] ).join( ' ' );

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
		 *	The head above the list: the form to narrow to and the search
		 *	over what is left. The select is drawn only where there is more
		 *	than one form to choose between - on the single-form project this
		 *	framework ships as, it would be a control with one option
		 *
		 *	@return		{Element}							<div class="nino-admin-table-toolbar">
		 */
		_renderHead : function() {

			const head = dc.createElement('div');
			head.className = 'nino-admin-table-toolbar';

			const keys = Object.keys( Nino.admin.submissions._forms );

			if( keys.length > 1 ) {

				const select = dc.createElement('select');
				select.id = 'submissions-form';
				select.className = 'nino-admin-input submissions-form';
				select.setAttribute( 'aria-label', Nino.content.getText('/_admin/submissions/label/form') );

				const all = dc.createElement('option');
				all.value = '';
				all.textContent = Nino.content.getText('/_admin/submissions/label/form-all');
				select.appendChild( all );

				keys.forEach( function( key ) {
					const option = dc.createElement('option');
					option.value = key;
					option.textContent = Nino.admin.submissions._forms[key].name || key;
					select.appendChild( option );
				} );

				select.value = Nino.admin.submissions._form;
				select.addEventListener( 'change', function() {
					Nino.admin.submissions._form = select.value;
					Nino.admin.submissions._renderList();
					const next = dc.getElementById('submissions-form');
					if( next !== null )
						next.focus();
				} );

				head.appendChild( select );
			}

			const search = dc.createElement('input');
			search.type = 'search';
			search.id = 'submissions-search';
			search.className = 'nino-admin-table-search';
			search.value = Nino.admin.submissions._filter;
			search.placeholder = Nino.content.getText('/_admin/submissions/label/search');
			search.setAttribute( 'aria-label', Nino.content.getText('/_admin/submissions/label/search') );

			// The list is drawn again per keystroke, and the focus put back
			// where it was - the element that had it is gone by then
			search.addEventListener( 'input', function() {
				Nino.admin.submissions._filter = search.value;
				Nino.admin.submissions._renderList();
				const next = dc.getElementById('submissions-search');
				if( next !== null ) {
					next.focus();
					if( typeof next.setSelectionRange === 'function' )
						next.setSelectionRange( next.value.length, next.value.length );
				}
			} );

			head.appendChild( search );

			return head;
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

			const all 	= Nino.admin.submissions._entries;
			const shown	= all.filter( Nino.admin.submissions._matches );

			// Nothing recorded at all, and nothing matching - two different
			// things, and the second one must not read as the first
			if( all.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/submissions/empty') ) );
				return;
			}

			wrap.appendChild( Nino.admin.submissions._renderHead() );

			if( shown.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/submissions/nomatch') ) );
				return;
			}

			const exportBtn = dc.createElement('button');
			exportBtn.type = 'button';
			exportBtn.id = 'submissions-export';
			exportBtn.classList.add('nino-admin-btn-primary');
			exportBtn.textContent = Nino.content.getText('/_admin/submissions/label/export');
			// What is on screen, not what is on disk: an export taken while a
			// form is selected is the export of that form
			exportBtn.addEventListener( 'click', function() {
				Nino.admin.exportCsv( Nino.content.getText('/_admin/submissions/label/filename'), shown );
			} );

			const ul = dc.createElement('ul');
			ul.id = 'submissions-entries';
			ul.className = 'nino-admin-list nino-admin-list-dense';

			shown.forEach( function( entry ) {
				ul.appendChild( Nino.admin.submissions._renderEntry( entry ) );
			} );

			wrap.appendChild( ul );
			wrap.appendChild( Nino.adminUi.listActions( [ exportBtn ] ) );

			// Only show the "expand" hint on cards whose long value actually
			// overflows its collapsed (line-clamped) height - can only be
			// measured once the card is in the document
			ul.querySelectorAll('.submissions-entry').forEach( function( li ) {
				const message = li.querySelector('.submissions-entry-message');
				if( message !== null && message.scrollHeight > message.clientHeight + 1 )
					li.classList.add('has-overflow');
			} );
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
			date.textContent = entry.date ?? '';
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

				const remove = dc.createElement('button');
				remove.type = 'button';
				remove.className = 'nino-admin-btn-danger submissions-entry-delete';
				remove.textContent = Nino.content.getText('/_admin/common/label/delete');
				remove.addEventListener( 'click', function( ev ) {
					ev.stopPropagation();
					Nino.admin.submissions._delete( entry );
				} );

				li.appendChild( remove );
			}

			li.addEventListener( 'click', Nino.admin.submissions._toggleEntry );
			li.addEventListener( 'keydown', function( ev ) {
				if( ev.key !== 'Enter' && ev.key !== ' ' )
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
		 *
		 *	@return		void
		 */
		_delete : function( entry ) {

			if( wn.confirm( Nino.content.getText('/_admin/submissions/confirm/delete').replace( '%s', entry.date || '' ) ) === false )
				return;

			Nino.admin.submissions._apiCall( 'delete', { id : entry.id }, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.submissions._showError( status, response );

				Nino.admin.submissions.init();
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
