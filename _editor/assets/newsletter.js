/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	newsletter.js						Admin "Newsletter" panel: view of every signup
 *													\Nino\Modules\Newsletter records - see _editor/Editor.php's
 *													Newsletter class - plus a one-line, copyable BCC address
 *													field, since the actual send always happens elsewhere (own
 *													mail client, or a project-specific ESP), never from Nino
 *													itself. The only write this panel does is delete - there's
 *													deliberately no self-service unsubscribe (see
 *													Modules\Newsletter's docblock), this "Löschen" button is
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
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_editor/newsletter/error/load') );
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
				p.textContent = Nino.content.getText('/_editor/newsletter/empty');
				wrap.appendChild( p );
				return;
			}

			const summary = dc.createElement('p');
			summary.id = 'newsletter-summary';
			summary.textContent = entries.length+ ' '+ Nino.content.getText( entries.length === 1 ? '/_editor/newsletter/label/subscriber' : '/_editor/newsletter/label/subscribers' );
			wrap.appendChild( summary );

			wrap.appendChild( Nino.editor.newsletter._renderBcc( entries ) );

			const table = dc.createElement('div');
			table.id = 'newsletter-entries';
			wrap.appendChild( table );

			// Subscribers are records, not cards - the shared table gives this
			// list search, sorting and paging that it never had. The mail column
			// and the per-row delete are drawn through the column render hook,
			// so both stay sortable/searchable on their plain values
			Nino.adminUi.table( {
				mount 	: table,
				rows 		: entries,
				rowKey 	: 'email',
				columns : [
					{ key : 'email', label : Nino.content.getText('/_editor/newsletter/label/mail'), type : 'string',
					  render : function( value ) {
							const link = dc.createElement('a');
							link.href = 'mailto:'+ ( value ?? '' );
							link.textContent = value ?? '';
							return link;
						} },
					{ key : 'date', label : Nino.content.getText('/_editor/newsletter/label/date'), type : 'datetime' },
					{ key : 'email', label : '', type : 'string',
					  render : function( value ) {
							const btn = dc.createElement('button');
							btn.type = 'button';
							btn.className = 'nino-admin-btn-danger newsletter-entry-delete';
							btn.textContent = Nino.content.getText('/_editor/newsletter/label/delete');
							btn.addEventListener( 'click', function() { Nino.editor.newsletter._delete( value ) } );
							return btn;
						} },
				],
				labels 	: {
					search 	: Nino.content.getText('/_editor/newsletter/label/search'),
					empty 	: Nino.content.getText('/_editor/newsletter/empty'),
					noMatch : Nino.content.getText('/_editor/newsletter/nomatch'),
				},
			} );
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

			if( wn.confirm( email+ Nino.content.getText('/_editor/newsletter/confirm/delete') ) === false )
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
			wrap.className = 'nino-admin-card';

			const label = dc.createElement('label');
			label.htmlFor = 'newsletter-bcc-field';
			label.textContent = Nino.content.getText('/_editor/newsletter/label/bcc');
			wrap.appendChild( label );

			const field = dc.createElement('textarea');
			field.id = 'newsletter-bcc-field';
			field.readOnly = true;
			field.value = entries.map( function( entry ) { return entry.email ?? '' } ).join(', ');
			field.addEventListener( 'click', function() { this.select() } );
			wrap.appendChild( field );

			const actions = dc.createElement('div');
			actions.id = 'newsletter-bcc-actions';
			actions.className = 'nino-admin-actionbar';

			const copyBtn = dc.createElement('button');
			copyBtn.type = 'button';
			copyBtn.textContent = Nino.content.getText('/_editor/newsletter/label/copy');
			copyBtn.addEventListener( 'click', function() {
				field.select();
				const write = wn.navigator.clipboard && typeof wn.navigator.clipboard.writeText === 'function'
					? wn.navigator.clipboard.writeText( field.value )
					: new Promise( function( resolve, reject ) {
						try {
							dc.execCommand('copy') === true ? resolve() : reject();
						} catch(e) { reject(e) }
					} );
				write.then( function() {
					copied.textContent = Nino.content.getText('/_editor/newsletter/label/copied');
					copied.classList.remove('text-import-error');
					copied.classList.remove('editor-hidden');
					setTimeout( function() { copied.classList.add('editor-hidden') }, 2000 );
				} ).catch( function() {
					copied.textContent = Nino.content.getText('/_editor/newsletter/error/copy');
					copied.classList.add('text-import-error');
					copied.classList.remove('editor-hidden');
				} );
			} );
			actions.appendChild( copyBtn );

			const copied = dc.createElement('span');
			copied.id = 'newsletter-bcc-copied';
			copied.className = 'editor-hidden';
			copied.setAttribute( 'aria-live', 'polite' );
			copied.textContent = Nino.content.getText('/_editor/newsletter/label/copied');
			actions.appendChild( copied );

			const exportBtn = dc.createElement('button');
			exportBtn.type = 'button';
			exportBtn.id = 'newsletter-export';
			exportBtn.textContent = Nino.content.getText('/_editor/newsletter/label/export');
			exportBtn.addEventListener( 'click', function() {
				Nino.editor.exportCsv( 'newsletter.csv', entries );
			} );
			actions.appendChild( exportBtn );

			wrap.appendChild( actions );

			return wrap;
		},
	};

})(window, document, document.documentElement, document.body);
