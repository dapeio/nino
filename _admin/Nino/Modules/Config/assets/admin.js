

/**
 *	Nino										A compact filesystembased php framework
 *	Dev											"Config" module: the project's settings as one form -
 *													error handling, the _admin features that can be
 *													switched off, and the page cache. See
 *													Admin/Admin.php beside it for the field schema this
 *													renders and validates against; the shape of every
 *													field (type, bounds, label, hint) comes from there
 *													rather than being duplicated here, so adding a
 *													setting is one FIELDS entry and nothing in this file.
 *
 *													The login throttle and the site's languages used to
 *													be groups of this form and now sit next to what they
 *													are about: lockout.js under Users, and language.js.
 *
 *													Routes, navigations and asset bundles used to be
 *													editable here as raw json and no longer are - the
 *													first two have real editors of their own (pages.js
 *													and the Navigation module's own panel) and a
 *													second, unvalidated way to write the same data is
 *													a way to corrupt it.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.config = {

		_ready 		: false,
		// Survives the re-render a successful save triggers - see _save()
		_pendingMsg : '',
		_groups 	: {},
		_fields 	: [],

		/**
		 *	Load the schema plus current values and render the form
		 *
		 *	@return		void
		 */
		init : function() {

			const wrap = dc.getElementById('config-form');
			if( wrap === null )
				return;

			Nino.admin.config._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.config._showError( wrap, status, response );

				Nino.admin.config._groups 	= response.groups;
				Nino.admin.config._fields 	= response.fields;
				Nino.admin.config._render();
				Nino.admin.config._ready = true;
			} );
		},

		showCurrent : function() {
			Nino.admin.config.init();
		},

		/**
		 *	Call a config/* dev action
		 *
		 *	@param		{string}		endpoint			Action name (eg. "list", becomes "config/list")
		 *	@param		{Object}		payload				Request payload, sent json-encoded as "data"
		 *	@param		{Function}	callback			Called with ( xhr.status, xhr.responseJSON )
		 *
		 *	@return		void
		 */
		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, { action : 'config/'+ endpoint, data : JSON.stringify( payload ) } );
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
		 *	Render every group in the order Admin.php declared it, each with the
		 *	fields that belong to it
		 *
		 *	@return		void
		 */
		_render : function() {

			const wrap = dc.getElementById('config-form');
			wrap.innerHTML = '';

			const form = dc.createElement('form');

			Object.keys( Nino.admin.config._groups ).forEach( function( groupKey ) {

				const fields = Nino.admin.config._fields.filter( function( field ) { return field.group === groupKey } );
				if( fields.length === 0 )
					return;

				form.appendChild( Nino.admin.config._renderGroup( groupKey, fields ) );
			} );

			// Save + its message in the shared actions row every module's form
			// ends on - style.css pins that row to the bottom of the
			// viewport, so a long form never puts Save out of reach
			const actions = dc.createElement('div');
			actions.className = 'nino-admin-actionbar';

			const saveBtn = dc.createElement('button');
			saveBtn.type = 'submit';
			saveBtn.className = 'nino-admin-btn-primary';
			saveBtn.textContent = Nino.content.getText('/_admin/common/label/save');
			actions.appendChild( saveBtn );

			const msg = dc.createElement('p');
			msg.id = 'config-form-msg';
			msg.className = 'nino-admin-actionbar-status';
			actions.appendChild( msg );

			form.appendChild( actions );
			form.addEventListener( 'submit', function( ev ) { ev.preventDefault(); Nino.admin.config._save() } );

			wrap.appendChild( form );

			// Re-shown after the reload that follows a save, which builds this
			// element fresh and would otherwise wipe the confirmation the moment
			// it appeared
			if( Nino.admin.config._pendingMsg !== '' ) {
				msg.textContent = Nino.admin.config._pendingMsg;
				Nino.admin.config._pendingMsg = '';
			}
		},

		/**
		 *	@param		{string}	groupKey
		 *	@param		{Array}		fields			This group's fields, in schema order
		 *
		 *	@return		{Element}							<fieldset>
		 */
		_renderGroup : function( groupKey, fields ) {

			const group = Nino.admin.config._groups[groupKey];

			const fieldset = dc.createElement('fieldset');

			// Heading and intro come from the schema as fill keys (see
			// Config::GROUPS), so the form reads in the interface language
			const legend = dc.createElement('legend');
			legend.textContent = Nino.adminUi.text( group[0] );
			fieldset.appendChild( legend );

			if( group[1] ) {
				const intro = dc.createElement('p');
				intro.className = 'nino-admin-hint';
				intro.textContent = Nino.adminUi.text( group[1] );
				fieldset.appendChild( intro );
			}

			fields.forEach( function( field ) {
				const el = Nino.admin.config._renderField( field );
				if( el !== null )
					fieldset.appendChild( el );
			} );

			return fieldset;
		},

		/**
		 *	One field, by the type its schema entry declares
		 *
		 *	@param		{Object}	field				{ key, type, label, hint, ... }
		 *
		 *	@return		{Element|null}
		 */
		_renderField : function( field ) {

			if( field.type === 'bool' )
				return Nino.admin.config._renderSwitch( field );

			if( field.type === 'int' )
				return Nino.admin.config._renderNumber( field );

			if( field.type === 'lines' )
				return Nino.admin.config._renderLines( field );

			return null;
		},

		/**
		 *	A list as a textarea, one entry per line. A repeater with an Add
		 *	button would be more machinery for less: these are short uri
		 *	patterns, and typing four of them is a paste, not four clicks.
		 *	The backend splits and trims (see Config::_cleanLines()), so what
		 *	is posted is simply the raw text.
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderLines : function( field ) {

			const label = dc.createElement('label');
			label.className = 'nino-admin-field nino-admin-field-wide';

			const span = dc.createElement('span');
			span.textContent = Nino.adminUi.text( field.label );
			label.appendChild( span );

			const area = dc.createElement('textarea');
			area.rows = 4;
			area.spellcheck = false;
			area.dataset.key = field.key;
			area.value = ( field.value || [] ).join( '\n' );
			label.appendChild( area );

			if( field.hint ) {
				const hint = dc.createElement('small');
				hint.className = 'nino-admin-hint';
				hint.textContent = Nino.adminUi.text( field.hint );
				label.appendChild( hint );
			}

			return label;
		},

		/**
		 *	A boolean as the design system's switch. The Element Types editor
		 *	needs the same control for its numbering option, so the component
		 *	itself lives in Nino.adminUi - this only names the setting.
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderSwitch : function( field ) {
			return Nino.adminUi.switchField( {
				key 			: field.key,
				checked 	: field.value === true,
				label 		: Nino.adminUi.text( field.label ),
				hint 			: Nino.adminUi.text( field.hint ),
			} );
		},

		/**
		 *	An int as the design system's bounded number input, with the
		 *	reading aid a duration gets - shared with the login-protection
		 *	tab, so the component itself lives in Nino.adminUi
		 *
		 *	@param		{Object}	field
		 *
		 *	@return		{Element}
		 */
		_renderNumber : function( field ) {
			return Nino.adminUi.numberField( field );
		},

		/**
		 *	Collect every field and save the form in one request
		 *
		 *	@return		void
		 */
		_save : function() {

			const msg = dc.getElementById('config-form-msg');
			const fields = {};

			// Every switch and number input carries its own key, so collecting
			// them needs no second list to keep in step with the schema
			Array.prototype.slice.call( dc.querySelectorAll('#config-form [data-key]') ).forEach( function( el ) {

				if( el.type === 'checkbox' )
					fields[el.dataset.key] = el.checked;
				else if( el.type === 'number' )
					fields[el.dataset.key] = el.value.trim();
				else
					// A textarea goes as its raw text - Config::_cleanLines()
					// splits and trims it, so the two never disagree about what
					// an empty line means
					fields[el.dataset.key] = el.value;
			} );

			msg.textContent = Nino.content.getText('/_admin/common/msg/saving');

			Nino.admin.config._apiCall( 'save', { fields : fields }, function( status, response ) {

				if( status !== 200 || response === null ) {
					msg.textContent = '('+ status+ ') '+ ( ( response && response.error ) ? response.error : Nino.content.getText('/_admin/common/error/save') );
					return;
				}

				// Held rather than written straight to this element: the reload
				// below replaces it, so assigning here shows the confirmation for
				// exactly as long as the request that follows takes
				Nino.admin.config._pendingMsg = Nino.content.getText('/_admin/common/msg/saved');

				// Reloaded rather than left as typed: the number fields come
				// back as the ints config.php now holds
				Nino.admin.config.init();
			} );
		},
	};

	Nino.events.bindCallback( 'ready', Nino.admin.config.init );

})(window, document, document.documentElement, document.body);
