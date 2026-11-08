/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	admin.js    						Admin "Dashboard" panel - the landing tab: a handful
 *													of read-only numbers already available from the other
 *													panels (see Admin/Admin.php beside it), pulled
 *													into one overview. Doesn't write anything, and the tiles
 *													are just #hash links into the panel they summarize -
 *													Nino.admin.onReady()'s own hashchange listener does the
 *													actual tab switch, nothing dashboard-specific needed there.
 *													The card above the notices greets the account: a line for
 *													the hour of the browser's clock, a sentence picked at random
 *													from those that fit the hour, the weekday and the day of the
 *													year, and a line of facts the server delivers (see greeting).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.dashboard = {

		/**
		 *	Load the summary and render it. Same "always re-fetch" shape as
		 *	the Log panel's admin.js - there's no drill-down state to preserve,
		 *	and re-fetching on every tab switch keeps the numbers current
		 *
		 *	@return		void
		 */
		init : function() {

			if( dc.getElementById('dashboard-list') === null )
				return;

			Nino.admin.dashboard._apiCall( 'summary', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.dashboard._showError( status, response );

				Nino.admin.dashboard._render( response );
			} );
		},

		/**
		 *	Re-fetch and re-show when the tab is switched to
		 *
		 *	@return		void
		 */
		showCurrent : function() {
			Nino.admin.router.set( 'dashboard', [] );
			Nino.admin.dashboard.init();
		},

		/**
		 *	Call a dashboard/* admin action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "summary", becomes "dashboard/summary")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( status, body )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.adminUi.api.call( 'dashboard/'+ endpoint, payload, callback );
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
			Nino.adminUi.showError( dc.getElementById('dashboard-list'), status, response, '/_admin/dashboard/error/load' );
		},

		/**
		 *	Render the whole panel: the greeting, the notices, a row of stat
		 *	tiles, an element-per-type breakdown, then recent activity
		 *
		 *	@param		{Object}	data					{ greeting?, notices?, elements, lastBackup, tiles, recentActivity? }
		 *
		 *	@return		void
		 */
		_render : function( data ) {

			const wrap = dc.getElementById('dashboard-list');
			wrap.innerHTML = '';

			// The greeting stands first of all, and is not there when the server
			// has none to give: all three of its lines switched off
			const greeting = Nino.admin.dashboard.greeting.render( data.greeting );
			if( greeting !== null )
				wrap.appendChild( greeting );

			// What needs attention comes first (see the panel's apiSummary()):
			// a fill key with its values, and the #panel where it is dealt
			// with, '' for none
			( Array.isArray( data.notices ) ? data.notices : [] ).forEach( function( notice ) {
				wrap.appendChild( Nino.admin.dashboard._notice( notice ) );
			} );

			const tiles = dc.createElement('div');
			tiles.id = 'admin-dashboard-tiles';
			tiles.className = 'nino-admin-tiles';
			// One tile per panel that reports a number (see the panel
			// contract's summary() and Dashboard::apiSummary()) - only in the
			// response at all if this admin holds that panel's permission,
			// omitted rather than empty, so nothing here has to know which
			// panels exist. recentActivity is gated the same way.
			( Array.isArray( data.tiles ) ? data.tiles : [] ).forEach( function( tile ) {
				tiles.appendChild( Nino.admin.dashboard._tile( String( tile.panel ), String( tile.value ), Nino.adminUi.text( tile.label ) ) );
			} );
			// Both of these come from another module (see the panel's
			// apiSummary()), so both are drawn only when the answer carried
			// them: a workbench without Backups has no last backup to show,
			// one without Elements has no types to chart
			// Named after the panel whose number it is, like every other tile:
			// that is what gives it the #backups link and the class
			// nino-admin-tile--backups (see _tile()). An account that cannot
			// open that panel still sees the date - basic operational info,
			// deliberately ungated (see the panel's apiSummary()) - and its
			// link lands on the first panel it does have, the same as any
			// hash that names no visible pane
			if( data.lastBackup !== undefined )
				tiles.appendChild( Nino.admin.dashboard._tile( 'backups', data.lastBackup || '–', Nino.content.getText('/_admin/dashboard/label/lastbackup') ) );
			wrap.appendChild( tiles );

			if( Array.isArray( data.elements ) )
				wrap.appendChild( Nino.admin.dashboard._elementsSection( data.elements ) );
			if( data.recentActivity !== undefined )
				wrap.appendChild( Nino.admin.dashboard._activitySection( data.recentActivity ) );
		},

		/**
		 *	The card the dashboard opens with: three lines, each of which the
		 *	server may switch off (greeting.show), and the card is not there when
		 *	all three are. The server delivers facts and nothing else - the name,
		 *	the time of the visit before this one, what the log and the backups
		 *	say, and the sentences with when each fits - and the hour, the weekday
		 *	and the dice are the browser's: the admin does not sit in the time zone
		 *	of the server. _now() and _random() are the two things a test replaces
		 */
		greeting : {

			// The last sentence shown, in the session of the browser tab - nothing that outlives it
			_memory : 'nino-admin-greeting',

			/**
			 *	@return		{Date}										The clock of the browser
			 */
			_now : function() {
				return new Date();
			},

			/**
			 *	@return		{number}									A number from 0 up to, not including, 1
			 */
			_random : function() {
				return Math.random();
			},

			/**
			 *	The time of day an hour belongs to: early 5-7, morning 8-10, noon
			 *	11-13, afternoon 14-16, evening 17-21, night 22-4
			 *
			 *	@param		{number}	hour							0-23
			 *
			 *	@return		{string}
			 */
			phase : function( hour ) {

				if( hour >= 5 && hour <= 7 )
					return 'early';
				if( hour >= 8 && hour <= 10 )
					return 'morning';
				if( hour >= 11 && hour <= 13 )
					return 'noon';
				if( hour >= 14 && hour <= 16 )
					return 'afternoon';
				if( hour >= 17 && hour <= 21 )
					return 'evening';

				return 'night';
			},

			/**
			 *	The day of the year that is an occasion: 24-26 December, and
			 *	31 December with 1 January
			 *
			 *	@param		{Date}		date
			 *
			 *	@return		{string}									'christmas', 'newyear' or 'none'
			 */
			occasion : function( date ) {

				const month = date.getMonth();
				const day = date.getDate();

				if( month === 11 && day >= 24 && day <= 26 )
					return 'christmas';
				if( ( month === 11 && day === 31 ) || ( month === 0 && day === 1 ) )
					return 'newyear';

				return 'none';
			},

			/**
			 *	The weekday pool a day is in: Monday, Friday, the weekend - and
			 *	'none' for the other days, which have no pool of their own
			 *
			 *	@param		{Date}		date
			 *
			 *	@return		{string}
			 */
			day : function( date ) {

				const day = date.getDay();

				if( day === 1 )
					return 'monday';
				if( day === 5 )
					return 'friday';

				return day === 0 || day === 6 ? 'weekend' : 'none';
			},

			/**
			 *	The sentences that fit a moment: on an occasion only the ones of
			 *	the occasion, otherwise those of the hour's time of day or of any,
			 *	and of the weekday's pool or of any. Without a name, only the ones
			 *	that need none (no %s)
			 *
			 *	@param		{Array}		sentences					[ { text, phase, day, occasion }, ... ]
			 *	@param		{Date}		date
			 *	@param		{string}	name
			 *
			 *	@return		{Array}
			 */
			fitting : function( sentences, date, name ) {

				const phase = Nino.admin.dashboard.greeting.phase( date.getHours() );
				const day = Nino.admin.dashboard.greeting.day( date );
				const occasion = Nino.admin.dashboard.greeting.occasion( date );

				return ( Array.isArray( sentences ) ? sentences : [] ).filter( function( sentence ) {

					if( sentence === null || typeof sentence !== 'object' || typeof sentence.text !== 'string' || sentence.text === '' )
						return false;

					if( name === '' && sentence.text.indexOf('%s') !== -1 )
						return false;

					if( occasion !== 'none' )
						return sentence.occasion === occasion;

					return ( sentence.occasion === undefined || sentence.occasion === 'none' )
						&& ( sentence.phase === undefined || sentence.phase === 'any' || sentence.phase === phase )
						&& ( sentence.day === undefined || sentence.day === 'any' || sentence.day === day );
				} );
			},

			/**
			 *	One of the sentences, by the dice - never the one shown the last
			 *	time, as long as there is another
			 *
			 *	@param		{Array}		fitting						The sentences that fit
			 *
			 *	@return		{string}									Its text, '' where none fits
			 */
			pick : function( fitting ) {

				let last = '';
				try {
					last = wn.sessionStorage.getItem( Nino.admin.dashboard.greeting._memory ) || '';
				} catch( error ) {
					last = '';
				}

				const others = fitting.filter( function( sentence ) { return sentence.text !== last } );
				const from = others.length > 0 ? others : fitting;

				if( from.length === 0 )
					return '';

				const text = from[ Math.min( from.length - 1, Math.max( 0, Math.floor( Nino.admin.dashboard.greeting._random() * from.length ) ) ) ].text;

				try {
					wn.sessionStorage.setItem( Nino.admin.dashboard.greeting._memory, text );
				} catch( error ) {
					// A browser that keeps nothing: the next one may be the same
				}

				return text;
			},

			/**
			 *	The days between two calendar days of the browser's time zone
			 *
			 *	@param		{number}	previous					A timestamp in seconds
			 *	@param		{Date}		now
			 *
			 *	@return		{number}									0 for today, 1 for yesterday
			 */
			days : function( previous, now ) {

				const then = new Date( previous * 1000 );
				const was = Date.UTC( then.getFullYear(), then.getMonth(), then.getDate() );
				const is = Date.UTC( now.getFullYear(), now.getMonth(), now.getDate() );

				return Math.max( 0, Math.round( ( is - was ) / 86400000 ) );
			},

			/**
			 *	The facts line: up to two of the last visit, the log since it and
			 *	the age of the last backup, in this order. Of three, the visit
			 *	and the backup stay - an old backup is what somebody acts on
			 *
			 *	@param		{Object}	greeting					The server's answer
			 *	@param		{Date}		now
			 *
			 *	@return		{string[]}
			 */
			facts : function( greeting, now ) {

				const format = Nino.adminUi.format;
				let visit = null;
				let activity = null;
				let backup = null;

				if( greeting.previousLogin === 0 )
					visit = Nino.content.getText('/_admin/dashboard/greet/fact/first');
				else if( Number.isInteger( greeting.previousLogin ) === true && greeting.previousLogin > 0 ) {

					const days = Nino.admin.dashboard.greeting.days( greeting.previousLogin, now );

					if( days === 0 )
						visit = Nino.content.getText('/_admin/dashboard/greet/fact/today');
					else if( days === 1 )
						visit = Nino.content.getText('/_admin/dashboard/greet/fact/yesterday');
					else
						visit = format( Nino.content.getText('/_admin/dashboard/greet/fact/days'), days );
				}

				if( Number.isInteger( greeting.activitySince ) === true && greeting.activitySince >= 1 )
					activity = greeting.activitySince === 1
						? Nino.content.getText('/_admin/dashboard/greet/fact/activity-one')
						: format( Nino.content.getText('/_admin/dashboard/greet/fact/activity'), greeting.activitySince );

				if( Number.isInteger( greeting.backupDays ) === true && greeting.backupDays >= 8 )
					backup = format( Nino.content.getText('/_admin/dashboard/greet/fact/backup'), greeting.backupDays );

				if( visit !== null && activity !== null && backup !== null )
					activity = null;

				return [ visit, activity, backup ].filter( function( fact ) { return fact !== null && fact !== '' } );
			},

			/**
			 *	The card: one paragraph for each line that is on and has something
			 *	to say. Everything the server sent, the name above all, is set as
			 *	text
			 *
			 *	@param		{Object|undefined}	greeting		The server's answer, undefined for none
			 *
			 *	@return		{Element|null}									Null where there is nothing to draw
			 */
			render : function( greeting ) {

				if( greeting === null || typeof greeting !== 'object' )
					return null;

				const show = greeting.show !== null && typeof greeting.show === 'object' ? greeting.show : {};
				const name = typeof greeting.name === 'string' ? greeting.name : '';
				const now = Nino.admin.dashboard.greeting._now();
				const lines = [];

				if( show.hello !== false ) {

					const hello = Nino.content.getText('/_admin/dashboard/greet/hello/'+ Nino.admin.dashboard.greeting.phase( now.getHours() ) );

					if( hello !== '' )
						lines.push( [ 'hello', hello+ ( name !== '' ? ', '+ name : '' )+ '.' ] );
				}

				if( show.sentence !== false ) {

					const text = Nino.admin.dashboard.greeting.pick( Nino.admin.dashboard.greeting.fitting( greeting.sentences, now, name ) );

					if( text !== '' )
						lines.push( [ 'sentence', Nino.adminUi.format( text, name ) ] );
				}

				if( show.facts !== false ) {

					const facts = Nino.admin.dashboard.greeting.facts( greeting, now );

					if( facts.length > 0 )
						lines.push( [ 'facts', facts.join(' · ') ] );
				}

				if( lines.length === 0 )
					return null;

				const card = dc.createElement('div');
				card.id = 'admin-dashboard-greeting';
				card.className = 'nino-admin-card nino-admin-greeting';

				lines.forEach( function( line ) {
					const p = dc.createElement('p');
					p.className = 'nino-admin-greeting-'+ line[0];
					p.textContent = line[1];
					card.appendChild( p );
				} );

				return card;
			},
		},

		/**
		 *	One notice: its text with every %s filled, in order, from the
		 *	values - the values are text, never markup - and a link into the
		 *	panel it names
		 *
		 *	@param		{Object}	notice				{ text, values, link }
		 *
		 *	@return		{Element}
		 */
		_notice : function( notice ) {

			const values = ( Array.isArray( notice.values ) ? notice.values : [] ).map( String );
			const link = typeof notice.link === 'string' && notice.link !== ''
				? { href : notice.link, label : Nino.content.getText('/_admin/dashboard/notice/open') }
				: null;

			return Nino.adminUi.notice( Nino.adminUi.format( Nino.adminUi.text( String( notice.text ) ), ...values ), link );
		},

		/**
		 *	One clickable stat tile - #hash link into the panel it
		 *	summarizes, same tab switch the nav bar itself uses. The class
		 *	nino-admin-tile--<panel> is there for a project's own stylesheet
		 *	(layer nino.local) to set one tile apart; the workbench's own
		 *	stylesheets do not use it
		 *
		 *	@param		{string}	panel					Target panel name (eg. "elements")
		 *	@param		{string}	value					Big figure
		 *	@param		{string}	label					Caption below it
		 *
		 *	@return		{Element}
		 */
		_tile : function( panel, value, label ) {

			const a = dc.createElement('a');
			a.href = '#'+ panel;
			a.className = 'nino-admin-tile nino-admin-tile--'+ panel;

			const val = dc.createElement('span');
			val.className = 'nino-admin-tile-value';
			val.textContent = value;
			a.appendChild( val );

			const lbl = dc.createElement('span');
			lbl.className = 'nino-admin-tile-label';
			lbl.textContent = label;
			a.appendChild( lbl );

			return a;
		},

		/**
		 *	"Elements by type": one meter bar per type, width relative to
		 *	whichever type has the most entries, clicking a row jumps to
		 *	that type in the Elements panel
		 *
		 *	@param		{Array}		elements			[ { type, title, count }, ... ]
		 *
		 *	@return		{Element}
		 */
		_elementsSection : function( elements ) {

			const section = dc.createElement('div');
			section.className = 'nino-admin-card';

			const title = dc.createElement('h2');
			title.textContent = Nino.content.getText('/_admin/dashboard/label/elements');
			section.appendChild( title );

			if( elements.length === 0 ) {
				section.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/dashboard/empty/elements') ) );
				return section;
			}

			const max = Math.max.apply( null, elements.map( function( e ) { return e.count } ).concat( [1] ) );

			const ul = dc.createElement('ul');
			ul.id = 'admin-dashboard-elements';

			elements.forEach( function( e ) {

				const li = dc.createElement('li');

				const a = dc.createElement('a');
				a.href = '#elements/'+ e.type;

				const row = dc.createElement('span');
				row.className = 'nino-admin-meter-row';

				const label = dc.createElement('span');
				label.className = 'nino-admin-meter-label';
				label.textContent = e.title;
				row.appendChild( label );

				const count = dc.createElement('span');
				count.className = 'nino-admin-meter-count';
				count.textContent = e.count;
				row.appendChild( count );

				a.appendChild( row );

				const track = dc.createElement('span');
				track.className = 'nino-admin-meter-track';
				const fill = dc.createElement('span');
				fill.className = 'nino-admin-meter-fill';
				fill.style.width = ( e.count / max * 100 )+ '%';
				track.appendChild( fill );
				a.appendChild( track );

				li.appendChild( a );
				ul.appendChild( li );
			} );

			section.appendChild( ul );

			return section;
		},

		/**
		 *	"Recent activity": the handful of most recent log lines
		 *	(server already limits + orders them), same list markup as
		 *	the Log panel's own full list
		 *
		 *	@param		{string[]}	lines
		 *
		 *	@return		{Element}
		 */
		_activitySection : function( lines ) {

			const section = dc.createElement('div');
			section.className = 'nino-admin-card';

			const title = dc.createElement('h2');
			title.textContent = Nino.content.getText('/_admin/dashboard/label/activity');
			section.appendChild( title );

			if( lines.length === 0 ) {
				section.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/dashboard/empty/activity') ) );
				return section;
			}

			const ul = dc.createElement('ul');
			ul.id = 'admin-dashboard-activity';

			lines.forEach( function( line ) {
				const li = dc.createElement('li');
				li.textContent = line;
				ul.appendChild( li );
			} );

			section.appendChild( ul );

			const more = dc.createElement('a');
			more.href = '#logs';
			more.className = 'admin-dashboard-more';
			more.textContent = Nino.content.getText('/_admin/dashboard/label/all')+ ' →';
			section.appendChild( more );

			return section;
		},
	};

})(window, document, document.documentElement, document.body);
