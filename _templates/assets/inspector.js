

/**
 *	Nino										A compact filesystembased php framework
 *	Templates								The inspector: the selected block's settings as a form,
 *													plus its own actions (move, duplicate, remove).
 *
 *													Every field is generated from the block's manifest -
 *													there is no per-block form code anywhere, and adding a
 *													setting to a manifest is enough to make it editable.
 *													Reading and writing both go through
 *													Nino.templates.blocks, so the inspector never touches a
 *													class name itself; it renders whatever that mapping
 *													reports and hands back whatever the user picked.
 *
 *													A change is applied to the node immediately and the
 *													canvas re-rendered - there is no per-field "apply". The
 *													document is only written to disk by the Save button
 *													(script.js), so nothing here reaches the filesystem on
 *													its own.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.templates = wn.Nino.templates || {};

	Nino.templates.inspector = {

		/**
		 *	Render the panel for whichever node is selected - or the
		 *	"nothing selected" hint when none is
		 *
		 *	@return		void
		 */
		render : function() {

			const wrap = dc.getElementById('tb-inspector');
			if( wrap === null )
				return;

			wrap.innerHTML = '';

			const node = Nino.templates.selectedNode();

			if( node === null ) {
				wrap.appendChild( Nino.templates.inspector._hint( 'Select a block in the canvas to edit it.' ) );
				return;
			}

			const blockKey 	= Nino.templates.blocks.match( node );
			const block 		= Nino.templates.blocks.get( blockKey );

			wrap.appendChild( Nino.templates.inspector._title( node, block ) );

			// Markup the library doesn't describe is structural only: it can
			// be moved and removed like anything else, but the builder has no
			// model for what its properties mean, so it offers none. Saying
			// so beats an empty panel that looks broken
			if( block === null ) {
				wrap.appendChild( Nino.templates.inspector._hint( 'The library does not describe this element, so it has no editable settings. Its markup is preserved exactly as it is.' ) );
				wrap.appendChild( Nino.templates.inspector._actions( node, null ) );
				return;
			}

			const values 	= Nino.templates.blocks.read( node, blockKey );
			const names 	= Object.keys( block.settings );

			if( names.length === 0 )
				wrap.appendChild( Nino.templates.inspector._hint( 'This block has no settings of its own.' ) );

			names.forEach( function( name ) {

				const setting = block.settings[name];

				wrap.appendChild( Nino.templates.inspector._field( node, blockKey, name, setting, values, '' ) );

				// A responsive classenum gets one extra control per
				// breakpoint, labelled with it - eg. "Width (m)" for
				// ui-grid-m-50
				( setting.breakpoints || [] ).forEach( function( bp ) {
					wrap.appendChild( Nino.templates.inspector._field( node, blockKey, name, setting, values, bp ) );
				} );
			} );

			wrap.appendChild( Nino.templates.inspector._actions( node, block ) );
		},

		/**
		 *	@param		{Object}				node
		 *	@param		{Object|null}		block
		 *
		 *	@return		{Element}
		 */
		_title : function( node, block ) {

			const head = dc.createElement('div');
			head.className = 'tb-inspector-title';

			const name = dc.createElement('span');
			name.className = 'tb-inspector-name';
			name.textContent = ( block !== null ) ? block.name : 'Unrecognized';
			head.appendChild( name );

			const tag = dc.createElement('span');
			tag.className = 'tb-node-tag';
			tag.textContent = ( node.tag === 'nino-sc' ) ? '['+ ( node.attrs.name || '' )+ ']' : '<'+ node.tag+ '>';
			head.appendChild( tag );

			return head;
		},

		/**
		 *	One setting as a labelled control. The control type follows from
		 *	the setting type, and its change handler writes straight back
		 *	onto the node through the same mapping that read it
		 *
		 *	@param		{Object}	node
		 *	@param		{string}	blockKey
		 *	@param		{string}	name				Setting name
		 *	@param		{Object}	setting			Its declaration
		 *	@param		{Object}	values			Everything blocks.read() reported
		 *	@param		{string}	bp					Breakpoint suffix, '' for the base control
		 *
		 *	@return		{Element}
		 */
		_field : function( node, blockKey, name, setting, values, bp ) {

			const key 	= ( bp === '' ) ? name : name+ '@'+ bp;
			const label = dc.createElement('label');

			// A breakpoint control is a variant of the setting above it, not
			// a setting of its own - marked so it reads as subordinate rather
			// than as five equal-weight fields called almost the same thing
			label.className = 'tb-field'+ ( bp === '' ? '' : ' tb-field--bp' );

			const span = dc.createElement('span');
			span.textContent = ( setting.label || name )+ ( bp === '' ? '' : ' ('+ bp+ ')' );
			label.appendChild( span );

			const control = Nino.templates.inspector._control( setting, values[key], bp );

			control.addEventListener( 'change', function() {
				const value = ( control.type === 'checkbox' ) ? control.checked : control.value;
				Nino.templates.blocks.write( node, blockKey, key, value );
				Nino.templates.markDirty();
				Nino.templates.canvas.rerender();
			} );

			// A text/attr field also updates as it is typed, so the canvas
			// preview keeps up - 'change' alone would only fire on blur
			if( control.tagName === 'INPUT' && control.type === 'text' )
				control.addEventListener( 'input', function() {
					Nino.templates.blocks.write( node, blockKey, key, control.value );
					Nino.templates.markDirty();
					Nino.templates.canvas.refreshPreview( node.id );
				} );

			label.appendChild( control );

			return label;
		},

		/**
		 *	@param		{Object}	setting
		 *	@param		{*}				value
		 *	@param		{string}	bp
		 *
		 *	@return		{Element}
		 */
		_control : function( setting, value, bp ) {

			if( setting.type === 'classtoggle' ) {
				const input = dc.createElement('input');
				input.type = 'checkbox';
				input.checked = value === true;
				return input;
			}

			if( setting.type === 'classgroup' )
				return Nino.templates.inspector._select(
					Object.keys( setting.options ).map( function( cls ) { return { value : cls, label : setting.options[cls] } } ),
					value
				);

			if( setting.type === 'classenum' ) {
				const options = [ { value : '', label : '—' } ].concat(
					setting.values.map( function( v ) { return { value : String( v ), label : String( v ) } } )
				);
				return Nino.templates.inspector._select( options, value );
			}

			if( setting.type === 'tag' )
				return Nino.templates.inspector._select(
					( setting.values || [] ).map( function( v ) { return { value : v, label : v } } ),
					value
				);

			// An attr with a fixed set of values is a select too - eg. an
			// image's loading="lazy|eager"
			if( setting.type === 'attr' && Array.isArray( setting.values ) === true )
				return Nino.templates.inspector._select(
					setting.values.map( function( v ) { return { value : v, label : ( v === '' ) ? '—' : v } } ),
					value
				);

			const input = dc.createElement('input');
			input.type = 'text';
			input.value = ( value === undefined || value === null ) ? '' : String( value );
			// Text fills belong in these fields as often as plain text does,
			// so nothing here may autocorrect or autocomplete them
			input.spellcheck = false;
			input.autocomplete = 'off';

			return input;
		},

		/**
		 *	@param		{Array}		options			[ { value, label }, ... ]
		 *	@param		{*}				value
		 *
		 *	@return		{Element}
		 */
		_select : function( options, value ) {

			const select = dc.createElement('select');

			options.forEach( function( option ) {
				const el = dc.createElement('option');
				el.value = option.value;
				el.textContent = option.label;
				el.selected = String( option.value ) === String( ( value === undefined || value === null ) ? '' : value );
				select.appendChild( el );
			} );

			return select;
		},

		/**
		 *	The selected block's own actions. Which ones exist is the
		 *	manifest's call ('actions'); an unrecognized node gets the
		 *	structural ones only, since those need no model of what it is
		 *
		 *	@param		{Object}				node
		 *	@param		{Object|null}		block
		 *
		 *	@return		{Element}
		 */
		_actions : function( node, block ) {

			const wrap = dc.createElement('div');
			wrap.className = 'tb-inspector-actions';

			const allowed = ( block !== null ) ? block.actions : [ 'remove', 'moveup', 'movedown' ];

			const actions = [
				{ key : 'moveup', 		label : '↑', 	title : 'Move up' },
				{ key : 'movedown', 	label : '↓', 	title : 'Move down' },
				{ key : 'duplicate', 	label : '⧉', 	title : 'Duplicate' },
				{ key : 'remove', 		label : '✕', 	title : 'Remove' },
			];

			actions.forEach( function( action ) {

				if( allowed.indexOf( action.key ) === -1 )
					return;

				const button = dc.createElement('button');
				button.type = 'button';
				button.textContent = action.label;
				button.title = action.title;
				button.className = 'tb-action'+ ( action.key === 'remove' ? ' tb-action--remove' : '' );
				button.addEventListener( 'click', function() { Nino.templates.runAction( action.key, node.id ) } );

				wrap.appendChild( button );
			} );

			return wrap;
		},

		_hint : function( text ) {
			const p = dc.createElement('p');
			p.className = 'admin-hint tb-inspector-hint';
			p.textContent = text;
			return p;
		},
	};

})(window, document, document.documentElement, document.body);
