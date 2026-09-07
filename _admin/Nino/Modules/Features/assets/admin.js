/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Features" module: every feature installed under
 *													features/, one block each - what it is, whether it is
 *													switched on, what stands in its way, and the settings
 *													its manifest declares, as a form. See Admin/Admin.php
 *													beside it: the entries arrive with their words already
 *													in the interface language and the settings schema
 *													normalized by \Nino\Features, so this file only knows
 *													how to draw each setting type and collect it back.
 *
 *													Activating and deactivating end in a reload: the rail
 *													is rendered server-side, so the panel a feature brings
 *													- or takes away - is only there once the page is built
 *													again. The hash stays on this panel, so the workbench
 *													comes back where it was.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.features = {

		_ready 			: false,
		// key => message: survives the re-render a successful save triggers - see _save()
		_pendingMsg : {},
		_dir 				: '',
		_features 	: [],

		/**
		 *	Load every feature with its state and settings, and render them
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('features-list');
			if( wrap === null )
				return;

			Nino.admin.features._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.features._showError( wrap, status, response );

				Nino.admin.features._dir 			= response.dir || '';
				Nino.admin.features._features	= response.features || [];
				Nino.admin.features._render();
				Nino.admin.features._ready = true;
			} );
		},

		showCurrent : function() {
			Nino.admin.features.init();
		},

		/**
		 *	Call a features/* action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "features/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'features/'+ endpoint, data : JSON.stringify( payload ) } );
		},

		/**
		 *	Show a failed request's status/error in a container
		 *
		 *	@param		{Element}		container
		 *	@param		{number}		status
		 *	@param		{*}					response
		 *
		 *	@return		void
		 */
		_showError : function( container, status, response ) {
			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/load') );
			container.appendChild( p );
		},

		/**
		 *	The intro naming the directory, then one block per feature - or
		 *	the empty state when the directory holds none
		 *
		 *	@return		void
		 */
		_render : function() {

			const wrap = dc.getElementById('features-list');
			wrap.innerHTML = '';

			const intro = dc.createElement('p');
			intro.className = 'nino-admin-hint nino-admin-hint-lead';
			intro.textContent = Nino.content.getText('/_admin/features/hint/intro').replace( '%s', Nino.admin.features._dir );
			wrap.appendChild( intro );

			if( Nino.admin.features._features.length === 0 ) {
				wrap.appendChild( Nino.adminUi.emptyState( Nino.content.getText('/_admin/features/hint/empty').replace( '%s', Nino.admin.features._dir ) ) );
				return;
			}

			Nino.admin.features._features.forEach( function( feature ) {
				wrap.appendChild( Nino.admin.features._renderFeature( feature ) );
			} );
		},

		/**
		 *	One feature: name, version and status, description, requirements,
		 *	every problem, the buttons its state allows, and - switched on,
		 *	with settings declared - its settings form
		 *
		 *	@param		{Object}	feature			An entry of features/list
		 *
		 *	@return		{Element}							<section>
		 */
		_renderFeature : function( feature ) {

			const card = dc.createElement('section');
			card.className = 'nino-admin-card';
			card.dataset.feature = feature.key;

			const title = dc.createElement('h3');
			title.textContent = feature.name+ ' ';
			title.appendChild( Nino.admin.features._renderStatus( feature ) );
			card.appendChild( title );

			// The manifest's version, and the one this installation recorded
			// when it differs - which is what an update is
			const version = dc.createElement('p');
			version.className = 'nino-admin-hint';
			version.textContent = Nino.content.getText('/_admin/features/label/version').replace( '%s', feature.version )
				+ ( feature.installed !== null && feature.installed !== feature.version
					? ' – '+ Nino.content.getText('/_admin/features/label/installed').replace( '%s', feature.installed )
					: '' );
			card.appendChild( version );

			if( feature.description !== '' ) {
				const description = dc.createElement('p');
				description.className = 'nino-admin-hint';
				description.textContent = feature.description;
				card.appendChild( description );
			}

			if( feature.requires.length > 0 ) {
				const requires = dc.createElement('p');
				requires.className = 'nino-admin-hint';
				requires.textContent = Nino.content.getText('/_admin/features/label/requires').replace( '%s', feature.requires.join( ', ' ) );
				card.appendChild( requires );
			}

			// What stands in the way of switching it on, one line each -
			// the kernel's own words, which is where the check lives
			feature.problems.forEach( function( problem ) {
				const line = dc.createElement('p');
				line.className = 'nino-admin-error';
				line.textContent = problem;
				card.appendChild( line );
			} );

			card.appendChild( Nino.admin.features._renderActions( feature ) );

			if( feature.active === true && feature.settings.length > 0 )
				card.appendChild( Nino.admin.features._renderSettings( feature ) );

			return card;
		},

		/**
		 *	The status in words - a low-vision reader cannot go by a colour,
		 *	so the word is the badge and the class only underlines it
		 *
		 *	@param		{Object}	feature
		 *
		 *	@return		{Element}							<span>
		 */
		_renderStatus : function( feature ) {

			const badge = dc.createElement('span');
			badge.className = 'nino-admin-eyebrow';

			if( feature.problems.length > 0 ) {
				badge.classList.add('nino-admin-error');
				badge.dataset.status = 'incompatible';
				badge.textContent = Nino.content.getText('/_admin/features/status/incompatible');
			}
			else if( feature.update === true ) {
				badge.classList.add('nino-admin-changed');
				badge.dataset.status = 'update';
				badge.textContent = Nino.content.getText('/_admin/features/status/update');
			}
			else if( feature.active === true ) {
				badge.dataset.status = 'active';
				badge.textContent = Nino.content.getText('/_admin/features/status/active');
			}
			else {
				badge.dataset.status = 'inactive';
				badge.textContent = Nino.content.getText('/_admin/features/status/inactive');
			}

			return badge;
		},

		/**
		 *	The buttons a feature's state allows: Activate while it is off
		 *	and nothing stands in the way, Update while its manifest moved
		 *	ahead of the record, Deactivate while it is on - and the message
		 *	line they report into
		 *
		 *	@param		{Object}	feature
		 *
		 *	@return		{Element}							<div>
		 */
		_renderActions : function( feature ) {

			const actions = dc.createElement('div');
			actions.className = 'admin-features-actions';

			const msg = dc.createElement('p');
			msg.className = 'nino-admin-hint';
			msg.setAttribute( 'aria-live', 'polite' );

			if( feature.active === false && feature.problems.length === 0 ) {
				const activate = dc.createElement('button');
				activate.type = 'button';
				activate.className = 'nino-admin-btn-primary';
				activate.textContent = Nino.content.getText('/_admin/features/label/activate');
				activate.addEventListener( 'click', function() { Nino.admin.features._switch( feature, 'activate', activate, msg ) } );
				actions.appendChild( activate );
			}

			if( feature.update === true ) {
				const update = dc.createElement('button');
				update.type = 'button';
				update.className = 'nino-admin-btn-primary';
				update.textContent = Nino.content.getText('/_admin/features/label/update').replace( '%s', feature.version );
				update.addEventListener( 'click', function() { Nino.admin.features._switch( feature, 'update', update, msg ) } );
				actions.appendChild( update );
			}

			if( feature.active === true ) {
				const deactivate = dc.createElement('button');
				deactivate.type = 'button';
				deactivate.className = 'nino-admin-btn-secondary';
				deactivate.textContent = Nino.content.getText('/_admin/features/label/deactivate');
				deactivate.addEventListener( 'click', function() { Nino.admin.features._switch( feature, 'deactivate', deactivate, msg ) } );
				actions.appendChild( deactivate );
			}

			actions.appendChild( msg );

			return actions;
		},

		/**
		 *	Switch a feature on or off, or apply its update - the kernel's
		 *	one step for an update is activating again, so the two post the
		 *	same action. Ends in a reload: the rail is rendered server-side
		 *
		 *	@param		{Object}	feature
		 *	@param		{string}	what				'activate', 'update' or 'deactivate'
		 *	@param		{Element}	btn
		 *	@param		{Element}	msg
		 *
		 *	@return		void
		 */
		_switch : function( feature, what, btn, msg ) {

			let busy, done, fallback;

			if( what === 'deactivate' ) {
				busy 		 = Nino.content.getText('/_admin/features/msg/deactivating');
				done 		 = Nino.content.getText('/_admin/features/msg/deactivated');
				fallback = Nino.content.getText('/_admin/features/error/deactivate');
			}
			else if( what === 'update' ) {
				busy 		 = Nino.content.getText('/_admin/features/msg/updating');
				done 		 = Nino.content.getText('/_admin/features/msg/updated');
				fallback = Nino.content.getText('/_admin/features/error/update');
			}
			else {
				busy 		 = Nino.content.getText('/_admin/features/msg/activating');
				done 		 = Nino.content.getText('/_admin/features/msg/activated');
				fallback = Nino.content.getText('/_admin/features/error/activate');
			}

			btn.disabled = true;
			msg.classList.remove('nino-admin-error');
			msg.textContent = busy;

			Nino.admin.features._apiCall( what === 'deactivate' ? 'deactivate' : 'activate', { key : feature.key }, function( status, response ) {

				if( status !== 200 || response === null ) {
					btn.disabled = false;
					msg.classList.add('nino-admin-error');
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : fallback );
					return;
				}

				msg.textContent = done+ ' '+ Nino.content.getText('/_admin/features/msg/reload');

				// The panel a feature brings only appears - or goes - once the
				// shell is built again; the hash keeps the workbench on this panel
				wn.location.hash = '#features';
				wn.location.reload();
			} );
		},

		/**
		 *	The settings form of one feature: every declared setting by its
		 *	type, one Save posting all of them at once
		 *
		 *	@param		{Object}	feature
		 *
		 *	@return		{Element}							<form>
		 */
		_renderSettings : function( feature ) {

			const form = dc.createElement('form');
			form.dataset.feature = feature.key;

			const fieldset = dc.createElement('fieldset');

			const legend = dc.createElement('legend');
			legend.textContent = Nino.content.getText('/_admin/features/label/settings');
			fieldset.appendChild( legend );

			feature.settings.forEach( function( field ) {
				fieldset.appendChild( Nino.admin.features._renderField( field ) );
			} );

			// One Save per feature, in the flow of its own block - the
			// shared pinned action bar is for a screen with one form
			const actions = dc.createElement('div');
			actions.className = 'admin-features-actions';

			const save = dc.createElement('button');
			save.type = 'submit';
			save.className = 'nino-admin-btn-primary';
			save.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( save );

			const msg = dc.createElement('p');
			msg.className = 'nino-admin-hint';
			msg.setAttribute( 'aria-live', 'polite' );
			actions.appendChild( msg );

			fieldset.appendChild( actions );
			form.appendChild( fieldset );

			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.features._save( feature, form, save, msg ) } );

			// Re-shown after the reload that follows a save, which builds this
			// element fresh and would otherwise wipe the confirmation the moment
			// it appeared
			if( Nino.admin.features._pendingMsg[feature.key] ) {
				msg.textContent = Nino.admin.features._pendingMsg[feature.key];
				delete Nino.admin.features._pendingMsg[feature.key];
			}

			return form;
		},

		/**
		 *	One setting, by the type its schema declares - the control
		 *	carries the setting's name as data-key, which is how _collect()
		 *	finds it again
		 *
		 *	@param		{Object}	field				{ name, type, label, hint, required, min, max, maxlength, unit, options, value | set }
		 *
		 *	@return		{Element}
		 */
		_renderField : function( field ) {

			if( field.type === 'bool' )
				return Nino.adminUi.switchField( {
					key 		: field.name,
					checked : field.value === true,
					label 	: Nino.adminUi.text( field.label ),
					hint 		: Nino.adminUi.text( field.hint ),
				} );

			if( field.type === 'int' )
				return Nino.admin.features._renderNumber( field );

			if( field.type === 'select' )
				return Nino.admin.features._renderSelect( field );

			if( field.type === 'text' || field.type === 'lines' )
				return Nino.admin.features._renderTextarea( field );

			if( field.type === 'secret' )
				return Nino.admin.features._renderSecret( field );

			return Nino.admin.features._renderInput( field );
		},

		/**
		 *	An int as the design system's bounded number input. Its unit is
		 *	the manifest's own word: the component only knows the units the
		 *	workbench has a fill for, so any other is written into the label
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderNumber : function( field ) {

			const unit 	= field.unit || '';
			const known = unit !== '' && Nino.content.getText( '/_admin/common/unit/'+ unit ) !== '';

			return Nino.adminUi.numberField( {
				key 	: field.name,
				value : field.value,
				min 	: field.min === null ? undefined : field.min,
				max 	: field.max === null ? undefined : field.max,
				unit 	: known === true ? unit : undefined,
				label : known === true || unit === '' ? field.label : field.label+ ' ('+ unit+ ')',
				hint 	: field.hint,
			} );
		},

		/**
		 *	A select with the manifest's options. A setting that holds
		 *	nothing yet - no default, or a stored value the schema no longer
		 *	accepts - gets an empty choice first, so the control shows the
		 *	state it is in rather than silently the first option
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderSelect : function( field ) {

			const options = field.options.map( function( option ) {
				return { value : option.value, label : Nino.adminUi.text( option.label ) };
			} );

			if( field.value === '' || field.value === null )
				options.unshift( { value : '', label : Nino.content.getText('/_admin/features/label/none') } );

			return Nino.adminUi.selectField( {
				key 		: field.name,
				label 	: Nino.adminUi.text( field.label ),
				hint 		: Nino.adminUi.text( field.hint ),
				options : options,
				value 	: field.value === null ? '' : field.value,
			} );
		},

		/**
		 *	A labelled field: the name, the control, the hint under it
		 *
		 *	@param		{Object}	field
		 *	@param		{Element}	control			The input, textarea or select
		 *	@param		{string}	extra				A second hint the type itself adds, '' for none
		 *	@param		{boolean}	wide				Whether it opts out of the two-column desktop grid
		 *
		 *	@return		{Element}							<label>
		 */
		_wrap : function( field, control, extra, wide ) {

			const label = dc.createElement('label');
			label.className = wide === true ? 'nino-admin-field nino-admin-field-wide' : 'nino-admin-field';

			const span = dc.createElement('span');
			span.textContent = Nino.adminUi.text( field.label );
			label.appendChild( span );

			control.dataset.key = field.name;
			label.appendChild( control );

			[ Nino.adminUi.text( field.hint ), extra ].forEach( function( text ) {
				if( text === '' )
					return;
				const hint = dc.createElement('small');
				hint.className = 'nino-admin-hint';
				hint.textContent = text;
				label.appendChild( hint );
			} );

			return label;
		},

		/**
		 *	A string, an email or a url as an input of that type - the
		 *	browser then checks the shape the backend checks too
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderInput : function( field ) {

			const input = dc.createElement('input');
			input.type = field.type === 'string' ? 'text' : field.type;
			input.value = field.value === null || field.value === undefined ? '' : String( field.value );
			input.required = field.required === true;
			input.autocomplete = 'off';
			if( field.maxlength )
				input.maxLength = field.maxlength;

			return Nino.admin.features._wrap( field, input, '', false );
		},

		/**
		 *	A secret as a password input that is always empty: the value
		 *	never reaches the browser, and posting the field empty keeps
		 *	what is stored (see Features::validateSettings()). The hint says
		 *	whether there is one
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderSecret : function( field ) {

			const input = dc.createElement('input');
			input.type = 'password';
			input.value = '';
			input.autocomplete = 'new-password';
			if( field.maxlength )
				input.maxLength = field.maxlength;

			return Nino.admin.features._wrap( field, input,
				Nino.content.getText( field.set === true ? '/_admin/features/hint/secret-set' : '/_admin/features/hint/secret-unset' ), false );
		},

		/**
		 *	A text as a textarea; a list as one too, one entry per line -
		 *	the backend splits and trims (see Features::validateSettings()),
		 *	so what is posted is simply the raw text
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderTextarea : function( field ) {

			const area = dc.createElement('textarea');
			area.rows = 4;
			area.spellcheck = field.type === 'text';
			area.value = field.type === 'lines'
				? ( field.value || [] ).join( '\n' )
				: ( field.value === null || field.value === undefined ? '' : String( field.value ) );

			return Nino.admin.features._wrap( field, area, field.type === 'lines' ? Nino.content.getText('/_admin/features/hint/lines') : '', true );
		},

		/**
		 *	Every control of one form by the setting name it carries, so
		 *	collecting them needs no second list to keep in step with the
		 *	schema
		 *
		 *	@param		{Element}	form
		 *
		 *	@return		{Object}							name => value as the control holds it
		 */
		_collect : function( form ) {

			const fields = {};

			Array.prototype.slice.call( form.querySelectorAll('[data-key]') ).forEach( function( el ) {

				if( el.type === 'checkbox' )
					fields[el.dataset.key] = el.checked;
				else if( el.type === 'number' )
					fields[el.dataset.key] = el.value.trim();
				else
					fields[el.dataset.key] = el.value;
			} );

			return fields;
		},

		/**
		 *	Save one feature's settings in one request
		 *
		 *	@param		{Object}	feature
		 *	@param		{Element}	form
		 *	@param		{Element}	save
		 *	@param		{Element}	msg
		 *
		 *	@return		void
		 */
		_save : function( feature, form, save, msg ) {

			save.disabled = true;
			msg.classList.remove('nino-admin-error');
			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.features._apiCall( 'settings', { key : feature.key, fields : Nino.admin.features._collect( form ) }, function( status, response ) {

				save.disabled = false;

				if( status !== 200 || response === null ) {
					msg.classList.add('nino-admin-error');
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
					return;
				}

				// Held rather than written straight to this element: the reload
				// below replaces it, so assigning here shows the confirmation for
				// exactly as long as the request that follows takes
				Nino.admin.features._pendingMsg[feature.key] = Nino.content.getText('/_admin/common/msg/saved');

				// Reloaded rather than left as typed: the values come back as
				// config.php now holds them, and the secret's hint says it is set
				Nino.admin.features.init();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.features.init );

})(window, document, document.documentElement, document.body);
