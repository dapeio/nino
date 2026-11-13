/**
 *	Nino										A compact filesystembased php framework
 *	Install									Step 5, "Applications": pick none, one or several of the
 *													applications the official catalogue offers - named sets of
 *													features, each with a place to continue in - and have them
 *													placed and switched on. See _admin/install/Install.php's
 *													Applications class for what is checked on the server.
 *
 *													"Without an application" is the default and keeps the way
 *													on whatever the catalogue answers: a catalogue that cannot be
 *													reached or does not verify leaves a sentence and "Try again",
 *													not a step that cannot be left. An application that provides
 *													the start page is picked from a radio group (at most one, and
 *													"none"), the others are checkboxes.
 *
 *													Picking applications and pressing Next asks for the plan -
 *													the features in the order they are applied - and applies one
 *													feature per request, so every one of them shows as it happens.
 *													A failure stays in this step with the feature named, "Try
 *													again" (which skips what is switched on already) and "Continue
 *													without an application" (which leaves what is on, on). The
 *													keys of the applications that went through are what the last
 *													step tells the server.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.install = wn.Nino.install || {};

	Nino.install.applications = {

		_ready 			: false,
		_running 		: false,
		_offered 		: [],
		_startApplied : null,
		_offline : false,
		_applied 		: [],
		_switchedOn : 0,

		showCurrent : function() {
			if( Nino.install.applications._ready === false )
				Nino.install.applications.load();
		},

		/**
		 *	The keys of the applications that were applied - what the last step
		 *	reports to the server, which believes it only as far as the features
		 *	are on
		 *
		 *	@return		{Array}
		 */
		applied : function() {
			return Nino.install.applications._applied.slice();
		},

		/**
		 *	The applications that went through and have an entry point, named as the
		 *	step shows them - what the last step labels its button with
		 *
		 *	@return		{Array}		[ { key, name } ]
		 */
		appliedEntries : function() {

			const self = Nino.install.applications;

			return self._offered.filter( function( app ) {
				return app.entry === true && self._applied.indexOf( app.key ) !== -1;
			} ).map( function( app ) { return { key : app.key, name : app.name } } );
		},

		/**
		 *	Ask for the applications on offer and draw them, or the sentence that
		 *	says why there are none
		 *
		 *	@return		void
		 */
		load : function( quiet ) {

			const self = Nino.install.applications;
			const msg = dc.getElementById('applications-msg');

			if( quiet !== true ) {
				msg.textContent = 'Loading the applications …';
				self._notice( '', [], false );
			}

			Nino.install.apiCall( 'applications/list', {}, function( status, response ) {

				if( quiet !== true )
					msg.textContent = '';

				// What is asked for in the background to learn what is set up changes nothing where it fails
				if( quiet === true && ( status !== 200 || response === null ) )
					return;

				if( status !== 200 || response === null ) {
					self._offered = [];
					self._render();
					self._notice( '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'The applications could not be loaded.' ), [], true );
					return;
				}

				self._offered = response.applications;
				self._startApplied = response.startApplied ?? null;
				self._offline = response.offline === true;
				self._render();
				self._notice( response.message, response.missing, response.retry === true );

				// A sentence that asking again can change is not remembered: the
				// step asks again the next time it is shown
				self._ready = response.retry !== true;
			} );
		},

		/**
		 *	@param		{string}	text				The sentence, '' for none
		 *	@param		{Array}		missing			What the server lacks, [ { key, text } ]
		 *	@param		{boolean}	retry				Whether asking again can help
		 *
		 *	@return		void
		 */
		_notice : function( text, missing, retry ) {

			const card = dc.getElementById('applications-notice');
			const list = dc.getElementById('applications-missing');

			card.classList.toggle( 'install-hidden', text === '' );
			dc.getElementById('applications-notice-text').textContent = text;

			list.innerHTML = '';
			missing.forEach( function( entry ) {
				const li = dc.createElement('li');
				li.dataset.missing = entry.key;
				li.textContent = entry.text;
				list.appendChild( li );
			} );
			list.classList.toggle( 'install-hidden', missing.length === 0 );

			dc.getElementById('applications-reload').classList.toggle( 'install-hidden', retry !== true );
		},

		/**
		 *	Draw the offer: the applications that provide the start page as a
		 *	radio group beginning with "none", the others as checkboxes. What was
		 *	picked before a reload stays picked
		 *
		 *	@return		void
		 */
		_render : function() {

			const self = Nino.install.applications;
			const kept = self._selected();
			const starts = self._offered.filter( function( app ) { return app.start !== null } );
			const more = self._offered.filter( function( app ) { return app.start === null } );

			const startWrap = dc.getElementById('applications-start');
			const moreWrap = dc.getElementById('applications-more');
			startWrap.innerHTML = '';
			moreWrap.innerHTML = '';

			dc.getElementById('applications-start-card').classList.toggle( 'install-hidden', starts.length === 0 );
			dc.getElementById('applications-more-card').classList.toggle( 'install-hidden', more.length === 0 );

			// An application that provides the start page stays the one once it is set up:
			// the choice is taken, and said so; the others of the group are not offered
			const set = self._startApplied;
			let setApp = starts.find( function( app ) { return app.key === set } );

			// A larger application being applied has a smaller one on as soon as its features
			// are: that is the way to the choice, which stays as it was made and stays open
			const chosen = starts.find( function( app ) { return kept.indexOf( app.key ) !== -1 } );
			if( setApp !== undefined && chosen !== undefined && chosen.key !== setApp.key
				&& setApp.features.every( function( feature ) { return chosen.features.some( function( own ) { return own.key === feature.key } ) } ) )
				setApp = undefined;

			const card = dc.getElementById('applications-start-card');
			const locked = dc.getElementById('applications-start-locked');

			card.dataset.state = setApp !== undefined ? 'locked' : 'open';
			// Where the catalogue cannot be reached what is set up is all that is known, and nothing further is offered
			const off = self._offline === true;
			const sentence = ( setApp === undefined ? '' : setApp.name+ ' is set up. Its features can be switched off, and others switched on, later in the Features panel.' )
				+ ( off ? ( setApp === undefined ? '' : ' ' )+ 'The catalogue cannot be reached at the moment, so nothing further can be chosen.' : '' );
			locked.classList.toggle( 'install-hidden', sentence === '' );
			locked.textContent = sentence;

			if( starts.length > 0 ) {
				startWrap.appendChild( self._row( 'radio', '', 'None', 'The wizard creates the starter pages.', [], setApp === undefined && starts.every( function( app ) { return kept.indexOf( app.key ) === -1 } ), setApp !== undefined || off ) );
				starts.forEach( function( app ) {
					startWrap.appendChild( self._row( 'radio', app.key, app.name, app.description, app.features, ( setApp !== undefined && app.key === set ) || ( setApp === undefined && kept.indexOf( app.key ) !== -1 ), setApp !== undefined || off ) );
				} );
			}

			// One that is set up is checked and stays so
			more.forEach( function( app ) {
				moreWrap.appendChild( self._row( 'checkbox', app.key, app.name, app.description, app.features, app.applied === true || kept.indexOf( app.key ) !== -1, app.applied === true || off ) );
			} );
		},

		/**
		 *	@param		{string}	type				'radio' | 'checkbox'
		 *	@param		{string}	key					The application's key, '' for "none"
		 *	@param		{string}	name
		 *	@param		{string}	description
		 *	@param		{Array}		features		[ { key, name } ]
		 *	@param		{boolean}	checked
		 *	@param		{boolean}	fixed				Whether the choice is taken and the control disabled
		 *
		 *	@return		{Element}
		 */
		_row : function( type, key, name, description, features, checked, fixed ) {

			const label = dc.createElement('label');

			const input = dc.createElement('input');
			input.type = type;
			input.value = key;
			input.checked = checked;
			input.disabled = fixed === true;
			input.dataset.kind = 'application';
			if( type === 'radio' )
				input.name = 'install-application-start';
			label.appendChild( input );

			const copy = dc.createElement('span');
			copy.className = 'install-app-copy';

			const title = dc.createElement('strong');
			title.textContent = name;
			copy.appendChild( title );

			if( description !== '' ) {
				const text = dc.createElement('small');
				text.textContent = description;
				copy.appendChild( text );
			}

			if( features.length > 0 ) {
				const list = dc.createElement('small');
				list.className = 'install-app-features';
				list.appendChild( dc.createTextNode( 'Switches on: ' ) );
				features.forEach( function( feature, index ) {
					if( index > 0 )
						list.appendChild( dc.createTextNode( ', ' ) );
					const name = dc.createElement('span');
					name.dataset.feature = feature.key;
					name.textContent = feature.name;
					list.appendChild( name );
				} );
				copy.appendChild( list );
			}

			label.appendChild( copy );

			return label;
		},

		/**
		 *	@return		{Array}		The keys of the checked applications, '' (the radio "none") left out
		 */
		_selected : function() {
			return Array.prototype.slice.call( dc.querySelectorAll( 'input[data-kind="application"]' ) )
				.filter( function( el ) { return el.checked === true } )
				.map( function( el ) { return el.value } )
				.filter( function( key ) { return key !== '' } );
		},

		/**
		 *	The shared Next button: nothing picked goes on at once, and does so
		 *	whatever the catalogue answered; otherwise the picked applications are
		 *	applied and the step is left only when all of them went through
		 *
		 *	@param		{Function}	callback			Called with ( ok, message )
		 *
		 *	@return		void
		 */
		apply : function( callback ) {

			const self = Nino.install.applications;
			const keys = self._selected();

			// Without the catalogue nothing is planned: what is set up is what goes on
			if( self._offline === true ) {
				self._applied = self._offered.filter( function( app ) { return app.applied === true } ).map( function( app ) { return app.key } );
				return callback( true );
			}

			if( keys.length === 0 ) {
				self._applied = [];
				return callback( true );
			}

			self._run( keys, function( ok ) {
				callback( ok, 'An application could not be applied - see the step.' );
			} );
		},

		/**
		 *	Plan, then apply the features one request each, drawing every one
		 *
		 *	@param		{Array}			keys
		 *	@param		{Function}	done					Called with ( ok )
		 *
		 *	@return		void
		 */
		_run : function( keys, done ) {

			const self = Nino.install.applications;
			const msg = dc.getElementById('applications-msg');
			const list = dc.getElementById('applications-progress');

			self._applied = [];
			list.innerHTML = '';
			dc.getElementById('applications-progress-card').classList.remove( 'install-hidden' );
			dc.getElementById('applications-failure').classList.add( 'install-hidden' );
			msg.textContent = 'Preparing …';

			Nino.install.apiCall( 'applications/plan', { applications : keys }, function( status, response ) {

				if( status !== 200 || response === null )
					return self._fail( null, '('+ status+ ') '+ ( ( response && response.error ) ? response.error : 'The plan could not be made.' ), response && response.code ? response.code : '', done );

				const rows = response.features.map( function( feature ) {
					const row = self._progressRow( feature.name );
					list.appendChild( row.li );
					return row;
				} );

				const step = function( index ) {

					if( index >= response.features.length ) {
						self._applied = keys.slice();
						// What is set up is shown as taken the next time the step is
						self._ready = false;
						msg.textContent = 'Applied.';
						return done( true );
					}

					const feature = response.features[index];
					self._state( rows[index], 'Working …', 'idle', 'working' );
					msg.textContent = 'Applying '+ feature.name+ ' ('+ ( index + 1 )+ ' of '+ response.features.length+ ') …';

					Nino.install.apiCall( 'applications/apply', { applications : keys, feature : feature.key }, function( featureStatus, featureResponse ) {

						if( featureStatus !== 200 || featureResponse === null ) {
							self._state( rows[index], 'Failed', 'fail', 'failed' );
							return self._fail( feature, '('+ featureStatus+ ') '+ ( ( featureResponse && featureResponse.error ) ? featureResponse.error : 'Failed.' ), featureResponse && featureResponse.code ? featureResponse.code : '', done );
						}

						if( featureResponse.activated === true )
							self._switchedOn++;

						if( featureResponse.placed === true || featureResponse.activated === true )
							self._state( rows[index], 'On', 'ok', 'on' );
						else
							self._state( rows[index], 'Already on', 'ok', 'already' );
						step( index + 1 );
					} );
				};

				rows.forEach( function( row ) { self._state( row, 'Waiting', 'idle', 'waiting' ) } );
				step( 0 );
			} );
		},

		/**
		 *	@param		{string}	name
		 *
		 *	@return		{Object}		{ li, status }
		 */
		_progressRow : function( name ) {

			const li = dc.createElement('li');
			li.className = 'install-check-row';

			const label = dc.createElement('span');
			label.className = 'install-check-row-name';
			label.textContent = name;
			li.appendChild( label );

			const status = dc.createElement('span');
			li.appendChild( status );

			return { li : li, status : status };
		},

		/**
		 *	@param		{Object}	row
		 *	@param		{string}	text				A word - the state is never only a colour
		 *	@param		{string}	kind				'idle' | 'ok' | 'fail'
		 *	@param		{string}	state				'waiting' | 'working' | 'on' | 'already' | 'failed', for whoever reads the state and not the word
		 *
		 *	@return		void
		 */
		_state : function( row, text, kind, state ) {
			row.status.className = 'install-check-status install-check-status--'+ kind;
			row.status.textContent = text;
			row.li.dataset.state = state;
		},

		/**
		 *	Stay in the step: say which feature failed and why, and give both ways on.
		 *	The feature and the code of the refusal are on the element as data, and
		 *	what is set up by now is asked for, so that the choice shows it
		 *
		 *	@param		{Object|null}	feature		{ key, name }, null where there was no plan yet
		 *	@param		{string}			reason
		 *	@param		{string}			code			The code of the refusal, '' for none
		 *	@param		{Function}		done
		 *
		 *	@return		void
		 */
		_fail : function( feature, reason, code, done ) {

			const self = Nino.install.applications;

			dc.getElementById('applications-msg').textContent = '';

			const box = dc.getElementById('applications-failure');
			box.classList.remove( 'install-hidden' );
			box.dataset.feature = feature === null ? '' : feature.key;
			box.dataset.code = code;

			const text = dc.getElementById('applications-failure-text');
			text.textContent = ( feature === null ? 'The applications could not be applied: ' : feature.name+ ' could not be applied: ' )+ reason;
			text.setAttribute( 'tabindex', '-1' );
			text.focus();

			done( false );

			self.load( true );
		},

		_retry : function() {

			const self = Nino.install.applications;
			if( Nino.install._busy === true )
				return;

			Nino.install._setBusy( true );
			self._run( self._selected(), function( ok ) {
				Nino.install._setBusy( false );
				if( ok === true )
					Nino.install.showStep( Nino.install._index + 1 );
			} );
		},

		/**
		 *	Leave the step without an application: what is switched on stays on,
		 *	and the last step says so
		 *
		 *	@return		void
		 */
		_skip : function() {

			const self = Nino.install.applications;
			if( Nino.install._busy === true )
				return;

			// What is set up stays chosen - its controls are disabled - and what is not is let go
			Array.prototype.slice.call( dc.querySelectorAll( 'input[data-kind="application"]' ) ).forEach( function( el ) {
				if( el.disabled !== true )
					el.checked = el.type === 'radio' && el.value === '';
			} );

			self._applied = self._offered.filter( function( app ) { return app.applied === true } ).map( function( app ) { return app.key } );
			dc.getElementById('applications-progress-card').classList.add( 'install-hidden' );

			Nino.install.showStep( Nino.install._index + 1 );

			if( self._switchedOn > 0 )
				dc.getElementById('finish-msg').textContent = 'Features switched on in the last step stay on - change them in the Features panel.';
		},
	};

	Nino.events.bindCallback( 'ready', function() {

		const bind = function( id, handler ) {
			const el = dc.getElementById( id );
			if( el !== null )
				el.addEventListener( 'click', handler );
		};

		bind( 'applications-reload', Nino.install.applications.load );
		bind( 'applications-retry', Nino.install.applications._retry );
		bind( 'applications-skip', Nino.install.applications._skip );
	} );

})(window, document, document.documentElement, document.body);
