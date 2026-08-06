/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	newsletter.js						Admin "Newsletter" panel: view of every signup
 *													\Nino\Shortcodes\Newsletter records - see _editor/Editor.php's
 *													Newsletter class - plus a one-line, copyable BCC address
 *													field, since the actual send always happens elsewhere (own
 *													mail client, or a project-specific ESP), never from Nino
 *													itself. The only write this panel does is delete - there's
 *													deliberately no self-service unsubscribe (see
 *													Shortcodes\Newsletter's docblock), this "Löschen" button is
 *													the only way an entry is ever removed.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.editor = wn.Nino.editor || {};

	Nino.editor.newsletter = {

		/**
		 *	Load the recorded signups and render them. Same "always
		 *	re-fetch" shape as logs.js - there's no drill-down state to
		 *	preserve, and re-fetching on every tab switch keeps the list
		 *	current with whatever arrived since it was last open
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('newsletter-list') === null )
				return;

			Nino.editor.newsletter._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.editor.newsletter._showError( status, response );

				Nino.editor.newsletter._renderList( response.entries );
			} );
		},

		/**
		 *	Re-fetch and re-show the list when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.editor.newsletter.init();
		},

		/**
		 *	Call a newsletter/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "newsletter/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_editor/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'newsletter/'+ endpoint, data : JSON.stringify( payload ) } );
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
			const wrap = dc.getElementById('newsletter-list');
			wrap.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'editor-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'Fehler beim Laden.' );
			wrap.appendChild( p );
		},

		/**
		 *	Render the subscriber count, a copyable BCC address line and
		 *	the individual entries, most recent first (already sorted that
		 *	way by the server)
		 *
		 *	@param		{Array}		entries				[ { email, date, ip }, ... ]
		 *
		 *	@return		void
		 */
		_renderList : function( entries ) {

			const wrap = dc.getElementById('newsletter-list');
			wrap.innerHTML = '';

			if( entries.length === 0 ) {
				const p = dc.createElement('p');
				p.textContent = 'Noch keine Anmeldungen.';
				wrap.appendChild( p );
				return;
			}

			const summary = dc.createElement('p');
			summary.id = 'newsletter-summary';
			summary.textContent = entries.length+ ' Abonnent'+ ( entries.length === 1 ? '' : 'en' );
			wrap.appendChild( summary );

			wrap.appendChild( Nino.editor.newsletter._renderBcc( entries ) );

			const ul = dc.createElement('ul');
			ul.id = 'newsletter-entries';

			entries.forEach( function( entry ) {
				ul.appendChild( Nino.editor.newsletter._renderEntry( entry ) );
			} );

			wrap.appendChild( ul );
		},

		/**
		 *	Render one subscriber row: email (mailto link), date and a
		 *	"Löschen" button
		 *
		 *	@param		{Object}	entry
		 *
		 *	@return		{Element}
		 */
		_renderEntry : function( entry ) {

			const li = dc.createElement('li');

			const mailLink = dc.createElement('a');
			mailLink.href = 'mailto:'+ ( entry.email ?? '' );
			mailLink.textContent = entry.email ?? '';
			li.appendChild( mailLink );

			// date + delete grouped together so the li's own space-between
			// still just splits it two ways (email left, this group right)
			const meta = dc.createElement('div');
			meta.className = 'newsletter-entry-meta';

			const date = dc.createElement('span');
			date.className = 'newsletter-entry-date';
			date.textContent = entry.date ?? '';
			meta.appendChild( date );

			const deleteBtn = dc.createElement('button');
			deleteBtn.type = 'button';
			deleteBtn.className = 'newsletter-entry-delete';
			deleteBtn.textContent = 'Löschen';
			deleteBtn.addEventListener( 'click', function() { Nino.editor.newsletter._delete( entry.email ) } );
			meta.appendChild( deleteBtn );

			li.appendChild( meta );

			return li;
		},

		/**
		 *	Delete one subscriber, after a confirm prompt, then re-fetch
		 *	the list - simplest way to keep the BCC field/summary count in
		 *	sync, same "always re-fetch" shape the rest of this panel uses
		 *
		 *	@param		{string}	email
		 *
		 *	@return		void
		 */
		_delete : function( email ) {

			if( wn.confirm( email+ ' wirklich aus dem Newsletter löschen?' ) === false )
				return;

			Nino.editor.newsletter._apiCall( 'delete', { email : email }, function( status, response ) {
				if( status !== 200 )
					return Nino.editor.newsletter._showError( status, response );

				Nino.editor.newsletter.init();
			} );
		},

		/**
		 *	Build the read-only BCC textarea (every current email, comma-
		 *	separated - a standard BCC field's own separator) plus its
		 *	copy-to-clipboard button - the actual send still happens
		 *	outside Nino (own mail client for a small list, a project's
		 *	ESP for a larger one), this only gets the addresses there
		 *
		 *	@param		{Array}		entries				[ { email, date, ip }, ... ]
		 *
		 *	@return		{Element}
		 */
		_renderBcc : function( entries ) {

			const wrap = dc.createElement('div');
			wrap.id = 'newsletter-bcc';

			const label = dc.createElement('label');
			label.htmlFor = 'newsletter-bcc-field';
			label.textContent = 'Adressen als BCC-Zeile';
			wrap.appendChild( label );

			const field = dc.createElement('textarea');
			field.id = 'newsletter-bcc-field';
			field.readOnly = true;
			field.value = entries.map( function( entry ) { return entry.email ?? '' } ).join(', ');
			field.addEventListener( 'click', function() { this.select() } );
			wrap.appendChild( field );

			const actions = dc.createElement('div');
			actions.id = 'newsletter-bcc-actions';

			const copyBtn = dc.createElement('button');
			copyBtn.type = 'button';
			copyBtn.textContent = 'Kopieren';
			copyBtn.addEventListener( 'click', function() {
				field.select();
				navigator.clipboard.writeText( field.value ).then( function() {
					copied.classList.remove('editor-hidden');
					setTimeout( function() { copied.classList.add('editor-hidden') }, 2000 );
				} );
			} );
			actions.appendChild( copyBtn );

			const copied = dc.createElement('span');
			copied.id = 'newsletter-bcc-copied';
			copied.className = 'editor-hidden';
			copied.textContent = 'Kopiert!';
			actions.appendChild( copied );

			const exportBtn = dc.createElement('button');
			exportBtn.type = 'button';
			exportBtn.id = 'newsletter-export';
			exportBtn.textContent = 'Als CSV exportieren';
			exportBtn.addEventListener( 'click', function() {
				Nino.editor.exportCsv( 'newsletter.csv', entries );
			} );
			actions.appendChild( exportBtn );

			wrap.appendChild( actions );

			return wrap;
		},
	};

	Nino.events.bindCallback( 'ready', Nino.editor.newsletter.init );

})(window, document, document.documentElement, document.body);
