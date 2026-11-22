/**
 *	Nino										A compact filesystembased php framework
 *	pagetext.js							The words of the two pages that stand beside the workbench - the setup wizard and the
 *													recovery page - and the choice of the language they are read in. Neither page has
 *													the project's text fills (the project is what the wizard makes, and the recovery page
 *													has to work when the project's text does not), so every sentence of a page is one fixed
 *													table with the same keys in German and in English, which the page registers here: what
 *													its template writes and what its scripts write come from that one place.
 *
 *													The language is one of two: an allow-list, never a path and never a value that reaches
 *													markup. German is the default. The choice is kept in the browser's storage under one
 *													key - no cookie, the two pages share it - and holds without storage as long as the
 *													page is open. The choice is not sent to the server and kept nowhere there: the wizard
 *													sends the language as `lang` ('de' or 'en') with the requests for the names of the
 *													applications (applications/list, applications/plan, finish/complete), where the server
 *													takes it from an allow-list of two and uses it for the answer alone.
 *
 *													The words are also published to Nino.content, under their own keys, so that the blocks of
 *													the workbench (Nino.adminUi.*), which look their own words up there, speak the same
 *													language as the page.
 *
 *													A template marks what it writes with data-text="<key>" (the text of the element),
 *													data-label="<key>" (its aria-label) and data-title="<key>" (its title); apply() sets
 *													all three, the <html> lang and the <title> of the page.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino.pageText = {

		// The two languages a page speaks, as the lang attribute names them: the allow-list of the choice
		LANGUAGES : [ 'de', 'en' ],
		DEFAULT : 'de',

		// The one key of the browser's storage the choice is kept under
		STORAGE_KEY : 'nino-admin-page-language',

		// The sentence of a refusal the table has no word for, and of one that has no code
		FAILURE : '/_admin/page/error/failed',

		_tables : { de : {}, en : {} },
		_current : null,

		// The language the page was in before the last choice: what was said in it is said again in the new one
		_previous : 'de',
		_listeners : [],

		/**
		 *	Register the table of a page: both languages, the same keys in each. The words of the language that is
		 *	current are published to Nino.content, where the blocks of the workbench look theirs up
		 *
		 *	@param		{Object}		tables				{ de : { key : sentence }, en : { key : sentence } }
		 *
		 *	@return		void
		 */
		register : function( tables ) {

			Nino.pageText.LANGUAGES.forEach( function( code ) {
				Object.assign( Nino.pageText._tables[code], tables[code] ?? {} );
			} );

			Nino.pageText._publish();
		},

		/**
		 *	The language the page is read in now: the one that was set or kept, else the default. Only an
		 *	entry of the allow-list is ever returned, whatever the storage holds
		 *
		 *	@return		{string}										'de' or 'en'
		 */
		language : function() {

			if( Nino.pageText._current === null ) {

				let kept = '';

				try {
					kept = String( wn.localStorage.getItem( Nino.pageText.STORAGE_KEY ) ?? '' );
				} catch(e) {}

				Nino.pageText._current = Nino.pageText.LANGUAGES.indexOf( kept ) !== -1 ? kept : Nino.pageText.DEFAULT;
			}

			return Nino.pageText._current;
		},

		/**
		 *	The language the picker offers: the other one
		 *
		 *	@return		{string}
		 */
		other : function() {
			return Nino.pageText.LANGUAGES[ Nino.pageText.language() === Nino.pageText.LANGUAGES[0] ? 1 : 0 ];
		},

		/**
		 *	Choose the language: kept, drawn into the page and told to whoever listens. A value that is no entry of the allow-list
		 *	changes nothing
		 *
		 *	@param		{string}		code
		 *
		 *	@return		{boolean}										Whether the language was taken
		 */
		set : function( code ) {

			if( typeof code !== 'string' || Nino.pageText.LANGUAGES.indexOf( code ) === -1 )
				return false;

			Nino.pageText._previous = Nino.pageText.language();
			Nino.pageText._current = code;

			try {
				wn.localStorage.setItem( Nino.pageText.STORAGE_KEY, code );
			} catch(e) {}

			Nino.pageText._publish();
			Nino.pageText.apply();
			Nino.pageText._listeners.slice().forEach( function( listener ) { listener( code ) } );

			return true;
		},

		/**
		 *	Ask to be told when the language changed: the scripts that drew words by hand draw them again
		 *
		 *	@param		{Function}	listener			Called with the new language
		 *
		 *	@return		void
		 */
		onChange : function( listener ) {
			Nino.pageText._listeners.push( listener );
		},

		/**
		 *	A sentence of the table in the language of the page, its %s filled in the order they stand. A function
		 *	fills them, never a string: String.replace() reads '$&' in a replacement, and what goes in may be typed
		 *
		 *	@param		{string}		key
		 *	@param		{...*}			params
		 *
		 *	@return		{string}										'' for a key the table does not have
		 */
		t : function( key, ...params ) {

			const table = Nino.pageText._tables[ Nino.pageText.language() ];
			let at = 0;

			if( Object.hasOwn( table, key ) === false )
				return '';

			return table[key].replace( /%s/g, function( token ) { return at < params.length ? String( params[at++] ) : token } );
		},

		/**
		 *	The sentence for what a request answered: the one of the table that the code of the answer names, else the
		 *	general one with the status. Nothing the server wrote is shown - its own sentence is English and is for a
		 *	log - and a code that is no word of the table's grammar is no code
		 *
		 *	@param		{number}		status
		 *	@param		{*}					response			The decoded answer, or null
		 *	@param		{...*}			known					What the page itself knows that a sentence may say, after what the answer carries (the length a password has)
		 *
		 *	@return		{string}
		 */
		failure : function( status, response, ...known ) {

			const asked = response !== null && typeof response === 'object' ? response : {};
			const code = typeof asked.code === 'string' && /^[a-z][a-z0-9_-]*$/.test( asked.code ) === true ? asked.code : '';

			// What a sentence may carry of the answer: a number or a short word of a version, a size - never a sentence of the server
			const params = ( Array.isArray( asked.params ) === true ? asked.params : [] ).filter( function( param ) {
				return typeof param === 'number' || ( typeof param === 'string' && /^[0-9A-Za-z._-]{1,40}$/.test( param ) === true );
			} );
			const sentence = code === '' ? '' : Nino.pageText.t( '/_admin/page/error/'+ code.replace( /_/g, '-' ), ...params, ...known );

			return sentence !== '' ? sentence : Nino.pageText.t( Nino.pageText.FAILURE, Number.isInteger( status ) === true ? status : 0 );
		},

		/**
		 *	Publish the words of the current language to Nino.content, so that Nino.adminUi.text() and every block
		 *	that asks for a word of its own finds it
		 *
		 *	@return		void
		 */
		_publish : function() {

			if( typeof Nino.content !== 'object' || Nino.content === null )
				return;

			if( typeof Nino.content.text !== 'object' || Nino.content.text === null )
				Nino.content.text = {};

			Object.assign( Nino.content.text, Nino.pageText._tables[ Nino.pageText.language() ] );
		},

		/**
		 *	Write the words into the page: the <html> lang, the <title>, and every element that names a key - its text
		 *	(data-text), its aria-label (data-label) and its title (data-title) - and the picker, which says the name
		 *	of the other language in that language
		 *
		 *	@param		{Element}		[root]				Where to look for elements, the document by default
		 *
		 *	@return		void
		 */
		apply : function( root ) {

			const scope = root ?? dc;

			dE.lang = Nino.pageText.language();

			[ [ 'data-text', 'text' ], [ 'data-label', 'aria-label' ], [ 'data-title', 'title' ] ].forEach( function( [ attribute, target ] ) {
				scope.querySelectorAll( '['+ attribute+ ']' ).forEach( function( el ) {

					const sentence = Nino.pageText.t( el.getAttribute( attribute ) );

					if( target === 'text' )
						el.textContent = sentence;
					else
						el.setAttribute( target, sentence );
				} );
			} );

			const title = Nino.pageText.t('/_admin/page/title');
			if( title !== '' )
				dc.title = title;

			const picker = dc.getElementById('page-language');
			if( picker !== null && typeof picker.setAttribute === 'function' ) {
				picker.textContent = Nino.pageText.t('/_admin/page/language-other');
				picker.setAttribute( 'lang', Nino.pageText.other() );
			}
		},

		/**
		 *	A sentence that was said in one language, said in the current one: found among the sentences of the table of that language - the
		 *	words with a %s in them as a pattern, whose parts are said again the same way, a label or a number among them - and written with the
		 *	words of the current language. What the table does not know comes back as it was
		 *
		 *	@param		{string}		sentence
		 *	@param		{string}		from					The language it was said in
		 *
		 *	@return		{string}
		 */
		translate : function( sentence, from ) {

			const tables = Nino.pageText._tables;
			const to = Nino.pageText.language();

			// A sentence of the tables is a few hundred characters at the most (the longest has 361): what is longer was not said by a table, and the
			// patterns below are not tried on it - two %s in a pattern take a time that grows with the square of the length
			if( from === to || Object.hasOwn( tables, from ) === false || sentence.length > 2000 )
				return sentence;

			const source = tables[from];
			const keys = Object.keys( source );

			// A whole sentence of the table, as it stands
			const whole = keys.find( function( key ) { return source[key] === sentence && key.indexOf('/error/') !== -1 } ) ?? keys.find( function( key ) { return source[key] === sentence } );

			if( whole !== undefined )
				return tables[to][whole] ?? sentence;

			// A sentence with its parts filled in: the longest pattern that fits
			let best = null;

			keys.forEach( function( key ) {

				if( source[key].indexOf('%s') === -1 || key.indexOf('/error/') === -1 )
					return;

				const pattern = new RegExp( '^'+ source[key].split('%s').map( function( part ) { return part.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) } ).join('(.+?)')+ '$', 's' );
				const found = pattern.exec( sentence );

				if( found !== null && ( best === null || source[key].length > source[best.key].length ) )
					best = { key : key, parts : found.slice( 1 ) };
			} );

			if( best === null )
				return sentence;

			const parts = best.parts.map( function( part ) { return /^[0-9]+$/.test( part ) === true ? part : Nino.pageText.translate( part, from ) } );
			let at = 0;

			return String( tables[to][best.key] ?? sentence ).replace( /%s/g, function() { return parts[ at++ ] ?? '' } );
		},

		/**
		 *	The sentences a form says at its fields (fieldError() of the blocks): taken off the page with the field each stands at, and each able to say
		 *	itself again, in the language the page has by then, at the same field of what was drawn anew
		 *
		 *	@param		{Element}		root
		 *	@param		{Function}	identity			The key a field is known by
		 *
		 *	@return		{Array}										Each with a restore( from ) that puts the sentence back
		 */
		_carryErrors : function( root, identity ) {

			const carried = [];
			const fields = Array.from( root.querySelectorAll('[data-key]') );

			root.querySelectorAll('.nino-admin-field-error').forEach( function( error ) {

				// Where it was put: after a label, or inside what it is said of
				const siblings = Array.from( error.parentNode.children );
				const before = siblings[ siblings.indexOf( error ) - 1 ] ?? null;
				const wrapper = before !== null && before.tagName === 'LABEL' ? before : error.parentNode;
				const inner = fields.find( function( el ) { return el === wrapper || wrapper.contains( el ) } );

				if( inner === undefined )
					return;

				const held = { id : error.id, text : error.textContent, at : identity( inner ), tag : wrapper.tagName, className : wrapper.className };
				const marked = [ wrapper ].concat( Array.from( wrapper.querySelectorAll('[aria-describedby]') ) ).concat( inner === wrapper ? [] : [ inner ] );
				held.control = marked.findIndex( function( el ) { return String( el.getAttribute('aria-describedby') ?? '' ).split(' ').indexOf( error.id ) !== -1 } );
				held.group = marked[ held.control ] === wrapper;
				held.tagOfControl = held.control !== -1 && marked[ held.control ] !== wrapper ? marked[ held.control ].tagName : '';

				carried.push( { restore : function( from ) {

					const now = Array.from( root.querySelectorAll('[data-key]') ).find( function( el ) { return identity( el ) === held.at } );

					if( now === undefined )
						return;

					let field = now;

					while( field !== null && field !== root && ( field.tagName !== held.tag || field.className !== held.className ) )
						field = field.parentNode;

					if( field === null || field === root )
						return;

					const control = held.group === true ? field : ( held.tagOfControl === 'BUTTON' ? field.querySelector('button') : now );

					Nino.adminUi.fieldError( field, control, held.id, Nino.pageText.translate( held.text, from ) );
				} } );
			} );

			return carried;
		},

		/**
		 *	Draw again what a script drew by hand - the words are another language now - and keep what was typed: the
		 *	values of the fields that carry a data-key (the blocks of the workbench write it) are read before and put back
		 *	after, with the focus. A chosen file cannot be put back into a field and is let go
		 *
		 *	@param		{Element}		root					What holds the fields
		 *	@param		{Function}	redraw				Draws it again
		 *
		 *	@return		void
		 */
		keep : function( root, redraw ) {

			const identity = function( el ) {
				return String( el.dataset.key )+ ( el.type === 'checkbox' || el.type === 'radio' ? '\u0000'+ String( el.value ) : '' );
			};
			const held = {};
			const focused = dc.activeElement !== null && typeof dc.activeElement === 'object' && typeof root.contains === 'function' && root.contains( dc.activeElement ) === true && dc.activeElement.dataset !== undefined && dc.activeElement.dataset.key !== undefined ? identity( dc.activeElement ) : null;

			root.querySelectorAll('[data-key]').forEach( function( el ) {
				if( el.type !== 'file' )
					held[ identity( el ) ] = el.type === 'checkbox' || el.type === 'radio' ? el.checked === true : el.value;
			} );

			// The sentences at the fields: taken from the page in the language it had, and said again in the one it has
			const carried = Nino.pageText._carryErrors( root, identity );
			const from = Nino.pageText._previous;

			redraw();

			carried.forEach( function( entry ) { entry.restore( from ) } );

			root.querySelectorAll('[data-key]').forEach( function( el ) {

				const id = identity( el );

				if( el.type !== 'file' && Object.hasOwn( held, id ) === true ) {
					if( el.type === 'checkbox' || el.type === 'radio' )
						el.checked = held[id];
					else
						el.value = held[id];
				}

				if( id === focused )
					el.focus();
			} );
		},

		/**
		 *	Put the picker into the place the template keeps for it, at the top right, and write the words once the
		 *	page is ready. The picker is a button of the kind of a link that names the other language in that language
		 *
		 *	@return		void
		 */
		onReady : function() {

			const mount = dc.getElementById('page-language-mount');

			if( mount !== null && typeof Nino.adminUi === 'object' )
				mount.appendChild( Nino.adminUi.button( '', function() { Nino.pageText.set( Nino.pageText.other() ) }, { kind : 'link', id : 'page-language' } ) );

			Nino.pageText.apply();
		},
	};

	Nino.events.bindCallback( 'ready', Nino.pageText.onReady );

})(window, document, document.documentElement, document.body);
