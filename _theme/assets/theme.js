/**
 *	Nino						A compact filesystembased php framework
 *	_theme/assets/theme.js		Reads the generated palette and renders it.
 *
 *	The colour maths deliberately stays in PHP: mirroring it here would be a
 *	second implementation of the contrast guarantee, and the two would drift.
 *	Every knob movement asks the server and paints what comes back.
 */

( function(wn,dc) {

	wn.Nino = wn.Nino || {};

	Nino.theme = {

		_settings : null,

		init : function() {

			if( dc.getElementById('theme-pane') === null )
				return;

			Nino.theme._call( 'read', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.theme._error( status, response );

				Nino.theme._settings = response.settings;
				Nino.theme._render( response.palette );
			} );
		},

		_call : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_theme/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, {
				action : 'design/'+ endpoint,
				data : JSON.stringify( payload ),
			} );
		},

		_error : function( status, response ) {
			const pane = dc.getElementById('theme-pane');
			pane.innerHTML = '';
			const message = dc.createElement('p');
			message.className = 'nino-admin-error';
			message.textContent = '('+ status+ ') '
				+ ( response && response.error ? response.error : 'Request failed.' );
			pane.appendChild( message );
		},

		/**
		 *	Every surface is drawn on itself, with its own values as chips.
		 *	A pairing that reads badly is then visible rather than asserted.
		 */
		_render : function( palette ) {

			const pane = dc.getElementById('theme-pane');
			pane.innerHTML = '';

			const heading = dc.createElement('h1');
			heading.textContent = 'Design';
			pane.appendChild( heading );

			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint nino-admin-hint-lead';
			hint.textContent = 'Generated from the brand colours and the four knobs below. '
				+ 'Every pair on this page is measured, not estimated.';
			pane.appendChild( hint );

			[ 'light', 'dark' ].forEach( function( mode ) {

				const title = dc.createElement('h2');
				title.textContent = mode === 'light' ? 'Light' : 'Dark';
				pane.appendChild( title );

				const grid = dc.createElement('div');
				grid.className = 'theme-surfaces';

				Object.keys( palette[mode] ).forEach( function( surface ) {
					grid.appendChild( Nino.theme._surface( surface, palette[mode][surface] ) );
				} );

				pane.appendChild( grid );
			} );
		},

		_surface : function( name, values ) {

			const card = dc.createElement('div');
			card.className = 'theme-surface';
			card.style.backgroundColor = values.bg;
			card.style.color = values.on;

			const head = dc.createElement('div');
			head.className = 'theme-surface-head';

			const label = dc.createElement('span');
			label.className = 'theme-surface-name';
			label.textContent = name;
			head.appendChild( label );

			const muted = dc.createElement('span');
			muted.style.color = values['on-muted'];
			muted.textContent = 'Secondary text on this surface';
			head.appendChild( muted );

			const link = dc.createElement('a');
			link.href = '#';
			link.style.color = values.link;
			link.textContent = 'A link on this surface';
			link.addEventListener( 'click', function( ev ) { ev.preventDefault() } );
			head.appendChild( link );

			card.appendChild( head );

			const list = dc.createElement('div');
			list.className = 'theme-surface-values';
			list.style.borderTopColor = values.border;

			Object.keys( values ).forEach( function( key ) {
				const chip = dc.createElement('span');
				chip.className = 'theme-chip';
				chip.style.borderColor = values.border;
				chip.textContent = key+ ' ' + values[key];
				list.appendChild( chip );
			} );

			card.appendChild( list );

			return card;
		},
	};

	Nino.events.bindCallback( 'ready', Nino.theme.init );

} )( window, document );
