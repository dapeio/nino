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
 *													What the server says about why there is no offer, and what it
 *													lacks, are codes and keys the table (text.js) has sentences for, in
 *													the language of the page; the names and descriptions of the
 *													applications are the catalogue's own, asked for in that language.
 *													The step draws all it shows from its state, so that a change of
 *													the language draws it again with what was picked.
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

		// What the step shows besides the offer, kept so that it can be drawn again in another language:
		// the notice { reason, missing, retry }, the rows of the progress { name, state } and the failure { feature, status, response }
		_notice : null,
		_rows : null,
		_failure : null,

		// What was picked, between two drawings: the keys that are ticked or chosen
		_picked : [],

		// The words of a state of a row of the progress: the table's key, the state of the badge, the name of the state for whoever reads it
		STATES : {
			waiting	: { key : '/_admin/install/applications/state-waiting', badge : 'muted' },
			working	: { key : '/_admin/install/applications/state-working', badge : 'warn' },
			on			: { key : '/_admin/install/applications/state-on', badge : 'ok' },
			already	: { key : '/_admin/install/applications/state-already', badge : 'ok' },
			failed	: { key : '/_admin/install/applications/state-failed', badge : 'error' },
		},

		showCurrent : function() {

			const self = Nino.install.applications;

			if( dc.getElementById('applications-content').children.length === 0 )
				self._draw();

			if( self._ready === false )
				self.load();
		},

		retext : function() {
			if( dc.getElementById('applications-content').children.length > 0 )
				Nino.install.applications._draw();
		},

		/**
		 *	The keys of the applications that were applied - what the last step reports to the server,
		 *	which believes it only as far as the features are on
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
		 *	says why there are none. The catalogue is asked for the names in the language of the page
		 *
		 *	@param		{boolean}		[quiet]				Asked for in the background, to learn what is set up: nothing is said, and a failure changes nothing
		 *
		 *	@return		void
		 */
		load : function( quiet ) {

			const self = Nino.install.applications;

			if( quiet !== true ) {
				Nino.install.say( Nino.install.t('/_admin/install/applications/loading') );
				self._notice = null;
			}

			Nino.install.apiCall( 'applications/list', { lang : Nino.pageText.language() }, function( status, response ) {

				if( quiet !== true )
					Nino.install.say('');

				// What is asked for in the background to learn what is set up changes nothing where it fails
				if( quiet === true && ( status !== 200 || response === null ) )
					return;

				if( status !== 200 || response === null ) {
					self._offered = [];
					self._notice = { reason : '', missing : [], retry : true, status : status, response : response };
					self._draw();
					return;
				}

				self._offered = response.applications;
				self._startApplied = response.startApplied ?? null;
				self._offline = response.offline === true;
				self._notice = response.reason !== '' || response.message !== '' ? { reason : response.reason, missing : response.missing, retry : response.retry === true, status : 200, response : null } : null;
				self._draw();

				// A failure that was said stays said where it was, with the focus on it: the offer is drawn again around it
				if( self._failure !== null )
					dc.getElementById('applications-failure-text').focus();

				// A sentence that asking again can change is not remembered: the
				// step asks again the next time it is shown
				self._ready = response.retry !== true;
			} );
		},

		/**
		 *	The sentence that says why there is no offer: the one the table has for the code the server gave, a general one
		 *	where the request itself failed
		 *
		 *	@param		{Object}		notice
		 *
		 *	@return		{string}
		 */
		_noticeText : function( notice ) {

			const t = Nino.install.t;

			if( notice.status !== 200 )
				return t( '/_admin/install/applications/failed-load' )+ ' '+ Nino.install.failure( notice.status, notice.response );

			const sentence = t( '/_admin/install/applications/notice-'+ String( notice.reason === '' ? 'install_none' : notice.reason ).replace( /_/g, '-' ) );

			return sentence !== '' ? sentence : t('/_admin/install/applications/failed-load');
		},

		/**
		 *	Draw the step: the notice, the two cards of the offer, the progress and the failure - all of what is in the
		 *	state, in the language of the page. What was picked stays picked
		 *
		 *	@return		void
		 */
		_draw : function() {

			const self = Nino.install.applications;
			const wrap = dc.getElementById('applications-content');

			if( wrap.querySelector('input[data-kind="application"]') !== null )
				self._picked = self._selected();

			wrap.innerHTML = '';

			wrap.appendChild( self._drawNotice() );

			const lists = Nino.adminUi.el( 'div', '' );
			lists.id = 'applications-lists';
			lists.appendChild( self._drawStart() );
			lists.appendChild( self._drawMore() );
			wrap.appendChild( lists );

			wrap.appendChild( self._drawProgress() );
		},

		/**
		 *	The notice: why there is no offer, what the server lacks, and "Try again" where asking again can help
		 *
		 *	@return		{Element}
		 */
		_drawNotice : function() {

			const self = Nino.install.applications;
			const t = Nino.install.t;
			const notice = self._notice;
			const box = Nino.adminUi.el( 'div', notice === null ? 'install-hidden' : '' );

			box.id = 'applications-notice';

			if( notice === null )
				return box;

			const text = Nino.adminUi.notice( self._noticeText( notice ) );
			text.id = 'applications-notice-text';
			box.appendChild( text );

			if( notice.missing.length > 0 ) {

				// What this server lacks, one row each, in the words of the table - a key it has no word for is no row
				const list = Nino.adminUi.list( notice.missing.filter( function( entry ) { return t( '/_admin/install/applications/missing-'+ entry.key ) !== '' } ).map( function( entry ) {
					return { key : entry.key, title : t( '/_admin/install/applications/missing-'+ entry.key ) };
				} ) );

				list.id = 'applications-missing';
				box.appendChild( list );
			}

			if( notice.retry === true ) {
				const actions = Nino.adminUi.el( 'div', 'nino-admin-actions' );
				actions.appendChild( Nino.adminUi.button( t('/_admin/install/applications/retry'), function() { self.load() }, { kind : 'secondary', id : 'applications-reload' } ) );
				box.appendChild( actions );
			}

			return box;
		},

		/**
		 *	One card of the offer: a fieldset with its title and the (?) of the sentence that explains it
		 *
		 *	@param		{string}		id
		 *	@param		{string}		title
		 *	@param		{string}		help
		 *	@param		{boolean}		shown
		 *
		 *	@return		{Element}
		 */
		_card : function( id, title, help, shown ) {

			const card = Nino.adminUi.el( 'fieldset', shown === true ? '' : 'install-hidden' );
			card.id = id;

			const legend = Nino.adminUi.el( 'legend', '', title );
			card.appendChild( legend );
			Nino.adminUi.sectionHelp( legend, card, help );

			return card;
		},

		/**
		 *	The card of the applications that provide the start page: a radio group beginning with "none". An application
		 *	that is set up stays the one: the choice is taken, and said so; the others of the group are not offered
		 *
		 *	@return		{Element}
		 */
		_drawStart : function() {

			const self = Nino.install.applications;
			const t = Nino.install.t;
			const kept = self._picked;
			const starts = self._offered.filter( function( app ) { return app.start !== null } );
			const card = self._card( 'applications-start-card', t('/_admin/install/applications/title-start'), t('/_admin/install/applications/help-start'), starts.length > 0 );

			const set = self._startApplied;
			let setApp = starts.find( function( app ) { return app.key === set } );

			// A larger application being applied has a smaller one on as soon as its features
			// are: that is the way to the choice, which stays as it was made and stays open
			const chosen = starts.find( function( app ) { return kept.indexOf( app.key ) !== -1 } );
			if( setApp !== undefined && chosen !== undefined && chosen.key !== setApp.key
				&& setApp.features.every( function( feature ) { return chosen.features.some( function( own ) { return own.key === feature.key } ) } ) )
				setApp = undefined;

			card.dataset.state = setApp !== undefined ? 'locked' : 'open';

			// Where the catalogue cannot be reached what is set up is all that is known, and nothing further is offered
			const off = self._offline === true;
			const sentence = ( setApp === undefined ? '' : t( '/_admin/install/applications/set-up', setApp.name ) )
				+ ( off ? ( setApp === undefined ? '' : ' ' )+ t('/_admin/install/applications/offline') : '' );
			const locked = Nino.adminUi.hint( sentence );

			locked.id = 'applications-start-locked';
			locked.classList.toggle( 'install-hidden', sentence === '' );
			card.appendChild( locked );

			const group = Nino.adminUi.el( 'div', 'nino-admin-checklist install-apps' );
			group.id = 'applications-start';
			group.setAttribute( 'role', 'radiogroup' );
			group.setAttribute( 'aria-label', t('/_admin/install/applications/title-start') );

			if( starts.length > 0 ) {
				group.appendChild( self._row( 'radio', '', t('/_admin/install/applications/none'), t('/_admin/install/applications/none-note'), [], setApp === undefined && starts.every( function( app ) { return kept.indexOf( app.key ) === -1 } ), setApp !== undefined || off ) );
				starts.forEach( function( app ) {
					group.appendChild( self._row( 'radio', app.key, app.name, app.description, app.features, ( setApp !== undefined && app.key === set ) || ( setApp === undefined && kept.indexOf( app.key ) !== -1 ), setApp !== undefined || off ) );
				} );
			}

			card.appendChild( group );

			return card;
		},

		/**
		 *	The card of the other applications, ticks
		 *
		 *	@return		{Element}
		 */
		_drawMore : function() {

			const self = Nino.install.applications;
			const t = Nino.install.t;
			const kept = self._picked;
			const more = self._offered.filter( function( app ) { return app.start === null } );
			const card = self._card( 'applications-more-card', t('/_admin/install/applications/title-more'), t('/_admin/install/applications/help-more'), more.length > 0 );
			const group = Nino.adminUi.el( 'div', 'nino-admin-checklist install-apps' );

			group.id = 'applications-more';

			// One that is set up is checked and stays so
			more.forEach( function( app ) {
				group.appendChild( self._row( 'checkbox', app.key, app.name, app.description, app.features, app.applied === true || kept.indexOf( app.key ) !== -1, app.applied === true || self._offline === true ) );
			} );

			card.appendChild( group );

			return card;
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
				list.appendChild( dc.createTextNode( Nino.install.t('/_admin/install/applications/switches-on')+ ' ' ) );
				features.forEach( function( feature, index ) {
					if( index > 0 )
						list.appendChild( dc.createTextNode( ', ' ) );
					const featureName = dc.createElement('span');
					featureName.dataset.feature = feature.key;
					featureName.textContent = feature.name;
					list.appendChild( featureName );
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
		 *	The card of the progress: one row of the list of the workbench for each feature with the state as its badge,
		 *	and - where a feature failed - the sentence that says which and why, with both ways on
		 *
		 *	@return		{Element}
		 */
		_drawProgress : function() {

			const self = Nino.install.applications;
			const t = Nino.install.t;
			const card = Nino.adminUi.el( 'fieldset', self._rows === null ? 'install-hidden' : '' );

			card.id = 'applications-progress-card';

			const legend = Nino.adminUi.el( 'legend', '', t('/_admin/install/applications/title-progress') );
			card.appendChild( legend );

			const holder = Nino.adminUi.el( 'div', '' );
			holder.id = 'applications-progress';
			card.appendChild( holder );

			if( self._rows !== null ) {
				const list = Nino.adminUi.list( self._rows.map( function( row ) {
					return { key : row.name, title : row.name, badge : { text : t( self.STATES[row.state].key ), state : self.STATES[row.state].badge } };
				} ) );

				holder.appendChild( list );
				Array.prototype.slice.call( list.querySelectorAll('li') ).forEach( function( li, at ) { li.dataset.state = self._rows[at].state } );
			}

			const box = Nino.adminUi.el( 'div', self._failure === null ? 'install-hidden' : '' );
			box.id = 'applications-failure';

			const text = Nino.adminUi.el( 'p', 'nino-admin-error' );
			text.id = 'applications-failure-text';
			text.setAttribute( 'role', 'alert' );
			text.setAttribute( 'tabindex', '-1' );
			box.appendChild( text );

			if( self._failure !== null ) {

				const failure = self._failure;

				box.dataset.feature = failure.feature === null ? '' : failure.feature.key;
				box.dataset.code = failure.code;
				text.textContent = ( failure.feature === null ? t('/_admin/install/applications/failed-plan') : t( '/_admin/install/applications/failed-feature', failure.feature.name ) )+ ' '+ Nino.install.failure( failure.status, failure.response );

				const actions = Nino.adminUi.el( 'div', 'nino-admin-actions' );
				actions.appendChild( Nino.adminUi.button( t('/_admin/install/applications/retry'), self._retry, { kind : 'secondary', id : 'applications-retry' } ) );
				actions.appendChild( Nino.adminUi.button( t('/_admin/install/applications/skip'), self._skip, { kind : 'secondary', id : 'applications-skip' } ) );
				box.appendChild( actions );
				box.appendChild( Nino.adminUi.hint( t('/_admin/install/applications/stay-on') ) );
			}

			card.appendChild( box );

			return card;
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
				callback( ok, Nino.install.t('/_admin/install/applications/see-step') );
			} );
		},

		/**
		 *	Set the state of a row of the progress and draw it
		 *
		 *	@param		{number}		index
		 *	@param		{string}		state				A key of STATES
		 *
		 *	@return		void
		 */
		_setRow : function( index, state ) {

			const self = Nino.install.applications;

			self._rows[index].state = state;
			self._drawRows();
		},

		/**
		 *	Draw the card of the progress again from its rows, leaving the rest of the step as it is
		 *
		 *	@return		void
		 */
		_drawRows : function() {

			const self = Nino.install.applications;
			const old = dc.getElementById('applications-progress-card');
			const fresh = self._drawProgress();

			old.replaceWith( fresh );
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
			const t = Nino.install.t;

			self._applied = [];
			self._failure = null;
			self._rows = [];
			self._drawRows();
			Nino.install.say( t('/_admin/install/applications/preparing') );

			Nino.install.apiCall( 'applications/plan', { applications : keys, lang : Nino.pageText.language() }, function( status, response ) {

				if( status !== 200 || response === null )
					return self._fail( null, status, response, done );

				self._rows = response.features.map( function( feature ) { return { key : feature.key, name : feature.name, state : 'waiting' } } );
				self._drawRows();

				const step = function( index ) {

					if( index >= response.features.length ) {
						self._applied = keys.slice();
						// What is set up is shown as taken the next time the step is
						self._ready = false;
						Nino.install.say('');
						return done( true );
					}

					const feature = response.features[index];
					self._setRow( index, 'working' );
					Nino.install.say( t( '/_admin/install/applications/applying', feature.name, index + 1, response.features.length ) );

					Nino.install.apiCall( 'applications/apply', { applications : keys, feature : feature.key }, function( featureStatus, featureResponse ) {

						if( featureStatus !== 200 || featureResponse === null ) {
							self._setRow( index, 'failed' );
							return self._fail( feature, featureStatus, featureResponse, done );
						}

						if( featureResponse.activated === true )
							self._switchedOn++;

						self._setRow( index, featureResponse.placed === true || featureResponse.activated === true ? 'on' : 'already' );
						step( index + 1 );
					} );
				};

				step( 0 );
			} );
		},

		/**
		 *	Stay in the step: say which feature failed and why, and give both ways on.
		 *	The feature and the code of the refusal are on the element as data, and
		 *	what is set up by now is asked for, so that the choice shows it
		 *
		 *	@param		{Object|null}	feature		{ key, name }, null where there was no plan yet
		 *	@param		{number}			status
		 *	@param		{*}						response	What the request answered
		 *	@param		{Function}		done
		 *
		 *	@return		void
		 */
		_fail : function( feature, status, response, done ) {

			const self = Nino.install.applications;

			Nino.install.say('');

			self._failure = { feature : feature, status : status, response : response, code : response !== null && typeof response === 'object' && typeof response.code === 'string' ? response.code : '' };
			self._drawRows();

			// The sentence takes the focus, for a reader who cannot see it appear
			dc.getElementById('applications-failure-text').focus();

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
					Nino.install.showStep( Nino.install._index + 1, true );
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
			self._rows = null;
			self._failure = null;
			self._drawRows();

			Nino.install.showStep( Nino.install._index + 1, true );

			if( self._switchedOn > 0 )
				Nino.install.say( Nino.install.t('/_admin/install/applications/stay-on-last') );
		},
	};

})(window, document, document.documentElement, document.body);
