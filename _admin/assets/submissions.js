

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	submissions.js					Admin "Anfragen" panel: read-only view of every contact-form
 *													submission \Nino\Shortcodes\Form records (in addition to
 *													the mail itself) - see _admin/Admin.php's Submissions class.
 *													Nothing here writes anything, it only lists.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.submissions = {

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

				Nino.admin.submissions._renderList( response.entries );
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
			p.className = 'admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Fehler beim Laden.' );
			wrap.appendChild( p );
		},

		/**
		 *	Render the submissions, most recent first (already sorted that
		 *	way by the server)
		 *
		 *	@param		{Array}		entries				[ { date, name, email, message, cat, ip }, ... ]
		 *
		 *	@return		void
		 */
		_renderList : function( entries ) {

			const wrap = dc.getElementById('submissions-list');
			wrap.innerHTML = '';

			if( entries.length === 0 ) {
				const p = dc.createElement('p');
				p.textContent = 'Noch keine Anfragen.';
				wrap.appendChild( p );
				return;
			}

			const exportBtn = dc.createElement('button');
			exportBtn.type = 'button';
			exportBtn.id = 'submissions-export';
			exportBtn.textContent = 'Als CSV exportieren';
			exportBtn.addEventListener( 'click', function() {
				Nino.admin.exportCsv( 'anfragen.csv', entries );
			} );
			wrap.appendChild( exportBtn );

			const ul = dc.createElement('ul');
			ul.id = 'submissions-entries';

			entries.forEach( function( entry ) {
				ul.appendChild( Nino.admin.submissions._renderEntry( entry ) );
			} );

			wrap.appendChild( ul );

			// Only show the "expand" hint on cards whose message actually
			// overflows its collapsed (line-clamped) height - can only be
			// measured once the card is in the document
			ul.querySelectorAll('.submissions-entry').forEach( function( li ) {
				const message = li.querySelector('.submissions-entry-message');
				if( message.scrollHeight > message.clientHeight + 1 )
					li.classList.add('has-overflow');
			} );
		},

		/**
		 *	Render one submission as a clickable card: date/category header,
		 *	name + mailto link, message (collapsed to a few lines until the
		 *	card is clicked/toggled - long messages would otherwise make the
		 *	list unreadable once there are many of them)
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{Element}
		 */
		_renderEntry : function( entry ) {

			const li = dc.createElement('li');
			li.className = 'submissions-entry';
			li.tabIndex = 0;

			const header = dc.createElement('div');
			header.className = 'submissions-entry-header';

			const date = dc.createElement('span');
			date.className = 'submissions-entry-date';
			date.textContent = entry.date ?? '';
			header.appendChild( date );

			if( entry.cat ) {
				const cat = dc.createElement('span');
				cat.className = 'submissions-entry-cat';
				// Form::callbackResponse() already ran htmlspecialchars() on every
				// field before storing it (same escaped value the email itself
				// uses) - decodeEntities() + textContent (not innerHTML) undoes
				// that encoding for display without ever parsing the result as
				// markup, so this stays safe even if a future field somehow
				// skipped the server-side escaping step
				cat.textContent = Nino.admin.decodeEntities( entry.cat );
				header.appendChild( cat );
			}

			li.appendChild( header );

			const name = dc.createElement('div');
			name.className = 'submissions-entry-name';
			const mailLink = dc.createElement('a');
			mailLink.href = 'mailto:'+ ( entry.email ?? '' );
			mailLink.textContent = Nino.admin.decodeEntities( entry.name ?? '' )+ ' <'+ Nino.admin.decodeEntities( entry.email ?? '' )+ '>';
			// Following the mailto link shouldn't also toggle the card
			mailLink.addEventListener( 'click', function( ev ) { ev.stopPropagation(); } );
			name.appendChild( mailLink );
			li.appendChild( name );

			const message = dc.createElement('p');
			message.className = 'submissions-entry-message';
			message.textContent = Nino.admin.decodeEntities( entry.message ?? '' );
			li.appendChild( message );

			const toggle = dc.createElement('span');
			toggle.className = 'submissions-entry-toggle';
			toggle.textContent = 'Mehr anzeigen';
			li.appendChild( toggle );

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
		 *	Expand/collapse a submission card (bound as its click listener,
		 *	so `this` is the card)
		 *
		 *	@return		void
		 */
		_toggleEntry : function() {

			this.classList.toggle('expanded');

			const toggle = this.querySelector('.submissions-entry-toggle');
			if( toggle !== null )
				toggle.textContent = this.classList.contains('expanded') ? 'Weniger anzeigen' : 'Mehr anzeigen';
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.submissions.init );

})(window, document, document.documentElement, document.body);
