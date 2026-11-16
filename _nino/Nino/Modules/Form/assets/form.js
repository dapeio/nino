/**
 *	Nino										A compact filesystembased php framework
 *	Modules\\Form							The script of the contact form and of every form that carries .nino-form: the one
 *													handler of a form that drives itself - it checks what the browser cannot, posts the
 *													fields to the endpoint of the form and writes the answer into the form. It is in
 *													the script bundle for as long as the module Form is switched on. Load order:
 *													Nino.js, then this file (it depends on Nino.content, Nino.http and Nino.events).
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc) {

	wn.Nino.form = {

		/**
		 *	Mark a form field as refused, or take the mark back.
		 *
		 *	The class is what the stylesheet paints, and a colour is the one
		 *	signal a screen reader is never handed and a colour-blind visitor
		 *	may not see. aria-invalid is the same thing in words. The mark also
		 *	has to come off: neither submit handler ever removed it, so a
		 *	visitor who corrected the address they were sent back for submitted
		 *	again with the corrected field still outlined - and, once this
		 *	attribute exists, still announced as invalid.
		 *
		 *	@param		{Element}	field
		 *	@param		{boolean}	invalid
		 *
		 *	@return		void
		 */
		_markField : function( field, invalid ) {

			if( invalid === true ) {
				field.classList.add('nino-is-error');
				field.setAttribute?.( 'aria-invalid', 'true' );
				return;
			}

			field.classList.remove('nino-is-error');
			field.removeAttribute?.( 'aria-invalid' );
		},

		/**
		 *	Make the paragraph a form writes its answer into a live region.
		 *
		 *	The form handler writes the validation error and the server's verdict
		 *	into the form's first <p>, and that paragraph is a project's own
		 *	markup - the shipped contact template's, a template a project wrote
		 *	itself, a page the Builder composed. None of them can be
		 *	asked to remember the attribute, so the component that writes into
		 *	it is what puts it there. Left alone where the markup already says
		 *	something, so a form that wants role="alert" keeps it
		 *
		 *	@param		{Element}	[msg]
		 *
		 *	@return		void
		 */
		_liveMessage : function( msg ) {

			if( msg === null || typeof msg === 'undefined' || typeof msg.setAttribute !== 'function' )
				return;

			const role = typeof msg.getAttribute === 'function' ? msg.getAttribute('role') : null;

			if( role !== null && role !== '' )
				return;

			msg.setAttribute( 'role', 'status' );
			msg.setAttribute( 'aria-live', 'polite' );
		},

		/**
		 *	Bind the submit handler of every form on the page: the contact form, the newsletter signup and the form
		 *	of a feature
		 *
		 *	@return		void
		 */
		onReady : function() {

			const
				e 			= {
					// Both kinds, by either class: a signup written with only
					// .nino-newsletter-form is driven as well, and one comma selector
					// lists a form carrying both classes once
					form			: dc.querySelectorAll( '.nino-form, .nino-newsletter-form' ),
				};

			if( e.form.length > 0 ) {

				let
					/**
					 *	One of the form's own texts
					 *
					 *	@param		{Element}	form						The form, its .texts set when it was bound
					 *	@param		{string}	which						required, email, invalid, success or error
					 *
					 *	@return		{string}							The text, '' where the site has none
					 */
					formText = function( form, which ) {
						return Nino.content.getText( form.texts + which );
					},

					/**
					 *	Handle a form's xhr response: disable the form and
					 *	show a success/error message. Bound as `this.form` before use.
					 *
					 *	@param		{XMLHttpRequest}	xhr				Completed (normalized) xhr
					 *
					 *	@return		void
					 */
					formResponse = function( xhr ){

						this.form.classList.remove('nino-is-pending');

						// Every way the request can end - an answer, a dead connection,
						// a timeout, an abort - lands here, so the button is given back
						// on all of them; a delivered message locks it again below
						if( this.form.btn )
							this.form.btn.disabled = false;

						const ok = ( xhr.status === 200 );

						this.form.classList.remove( ok === true ? 'nino-is-error' : 'nino-is-success' );
						this.form.classList.add( ok === true ? 'nino-is-success' : 'nino-is-error' );

						// A 400 is Form.php repeating the field validation this form
						// already ran - so it gets a field-level message instead of
						// "please try again later", which sends the visitor away to
						// wait out a problem only they can fix. Which message depends
						// on what the form holds: Form::TYPES has url, number, date,
						// select, radio and checkbox in it as well, so a 400 on a form
						// carrying one of those is not necessarily the address, and
						// saying so anyway sent the visitor to look at the one field
						// that was fine.
						// The server names no field on a 400, so the text asks for the
						// entries, not a highlighted one. A site installed before that
						// fill existed has none, and getText() answers '' for it - the
						// address text stays the fallback rather than an empty line.
						// Every other non-200 stays generic on purpose: naming the
						// csrf 403 or the honeypot's 418 tells a spam bot which check
						// it tripped. textContent, not innerHTML - these are
						// editor-editable textfills, and Modules\Jstext json-encodes
						// them precisely so they cannot become markup on the way in
						const typed = Array.prototype.some.call( this.form.fields, function( field ) {
							return field.type === 'url' || field.type === 'number' || field.type === 'date'
								|| field.type === 'radio' || field.type === 'checkbox' || field.tagName === 'SELECT';
						} );

						// A form that asks for no address cannot have been refused for
						// one: its 400 reads the invalid text as well
						const asksAddress = Array.prototype.some.call( this.form.fields, function( field ) {
							return field.type === 'email';
						} );

						this.form.msg.textContent = ( xhr.status === 400 )
							? ( ( typed === true || asksAddress === false ? formText( this.form, 'invalid' ) : '' ) || formText( this.form, 'email' ) )
							: formText( this.form, ok === true ? 'success' : 'error' );

						// Only a delivered message locks the form down. Disabling every
						// field on any response left a visitor who mistyped their address
						// looking at a correctable error in a form they could no longer
						// correct
						if( ok === false )
							return;

						if( this.form.btn )
							this.form.btn.disabled = true;
						for( let i = 0, l = this.form.fields.length; i<l; i++ )
							this.form.fields[i].disabled = true;
					},

					/**
					 *	Validate and submit a form via xhr; blocks submission
					 *	until all required fields are filled and any email field is valid
					 *
					 *	@param		{Event}		e								Submit event
					 *
					 *	@return		void
					 */
					formSubmit = function( e ){
						e.preventDefault();

						/*	One request at a time. A second click or another Enter before
							the answer would post the form again - two mails, two stored
							entries - and the pending mark is exactly "a request is out"	*/
						if( this.classList.contains('nino-is-success') === true || this.classList.contains('nino-is-pending') === true )
							return;

						/*	Every attempt starts from a clean slate. The mark belongs to
							the attempt, not to the form: nothing ever took it off, so a
							visitor sent back for their address and told to correct it
							submitted again with the corrected field still outlined -
							and, now that the outline has a word beside it, still
							announced as invalid	*/
						for( let i = 0, l = this.fields.length; i<l; i++ )
							Nino.form._markField( this.fields[i], false );

						// Loop through inputs
						let error = false, data = {}, radioGroups = {}, firstRefused = null;
						for( let i = 0, l = this.fields.length; i<l; i++) {

							// Stored as typed. This used to strip [<>'";(){}[\]\|] from
							// every field and write the stripped value back into the
							// visible input, which silently rewrote legitimate content:
							// "mary.o'brien@example.com" was submitted - and confirmed -
							// as "mary.obrien@example.com", a different, quite possibly
							// real mailbox, so the confirmation mail and the owner's
							// reply both went to a stranger. Form.php's own comment
							// spells out why it does not escape these values either.
							// Escaping is an output concern and already handled where
							// the values become markup (Form/Newsletter escape on the
							// way into the mail templates); removing characters here
							// protected nothing and corrupted ordinary input
							// A checkbox and a radio are the two controls whose .value says
							// nothing about what the visitor did: an unticked box with no
							// value attribute still reads "on", so posting it
							// unconditionally reported every box as ticked. What either is
							// worth is whether it is checked
							if( this.fields[i].type === 'checkbox' )
								data[this.fields[i].name] = this.fields[i].checked === true ? ( this.fields[i].value || 'on' ) : '';

							else if( this.fields[i].type === 'radio' ) {

								/*	A group of radios shares one name, and this loop writes
									data[name] once per member - so the last member won
									whichever one was ticked, and a visitor who picked the
									first option had the last one submitted, stored and
									mailed. Only the ticked member carries an answer, and an
									unticked one must not overwrite it whatever order they
									sit in.

									The group is also required or not as a group, which no
									single member's .value can say - it is never empty, so
									the required check below never fired for one, and a
									question nobody answered went through as answered.
									Collected here and asked after the loop, where the group
									is complete	*/
								if( this.fields[i].checked === true )
									data[this.fields[i].name] = this.fields[i].value;
								else if( data[this.fields[i].name] === undefined )
									data[this.fields[i].name] = '';

								if( this.fields[i].required === true )
									radioGroups[this.fields[i].name] = true;

								// None of the checks below is a radio's: required is the
								// group's, and an email, url or number is never one
								continue;
							}

							else
								data[this.fields[i].name] = this.fields[i].value;

							// Check required. A checkbox's .value is what it would send,
							// not whether it is ticked - so what it is worth is whether
							// it is ticked
							if( this.fields[i].required === true
								&& ( this.fields[i].type === 'checkbox' ? this.fields[i].checked !== true : this.fields[i].value.length === 0 ) ) {
								Nino.form._markField( this.fields[i], true );
								firstRefused = firstRefused ?? this.fields[i];
								error = formText( this, 'required' );
							}

							// Check email. The local part uses the character set the html
							// spec allows for input[type=email] (and php's
							// FILTER_VALIDATE_EMAIL with it) rather than a hand-picked
							// subset: the old one had no "'" in it, so a real
							// "mary.o'brien@example.com" failed a check the browser's own
							// native validation and Form.php both pass. The
							// dot-plus-tld tail is kept - it is stricter than the spec,
							// deliberately, because a contact form typo'd to "@localhost"
							// helps nobody. An empty value is the required rule's to refuse,
							// as the url and number branch below has it: an address the form
							// does not ask for may stay empty
							if( error === false && this.fields[i].type === 'email' && this.fields[i].value.length > 0 && ( /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/.test(this.fields[i].value) === false ) ) {
								Nino.form._markField( this.fields[i], true );
								firstRefused = firstRefused ?? this.fields[i];
								error = formText( this, 'email' );
							}

							// A url, a number and a date are validated by Form::validate()
							// too (FILTER_VALIDATE_URL, is_numeric, Y-m-d), and the client
							// checked none of them - so the server's 400 came back for a
							// field this form had passed, and the visitor was told their
							// email address was wrong while it was fine. The browser's own
							// verdict rather than a second regex here: it is the rule the
							// visitor already sees in the field, so the two cannot
							// disagree. Absent (a non-browser client, a test stand-in) it
							// checks nothing and the server still answers.
							// badInput is asked whatever .value holds: a date that is
							// half typed, or letters in a number field, read '' - and an
							// empty value is exactly what the length test would pass
							if( error === false && ( this.fields[i].type === 'url' || this.fields[i].type === 'number' || this.fields[i].type === 'date' )
								&& typeof this.fields[i].validity === 'object' && this.fields[i].validity !== null
								&& ( this.fields[i].validity.badInput === true
									|| ( this.fields[i].value.length > 0 && this.fields[i].validity.typeMismatch === true ) ) ) {
								Nino.form._markField( this.fields[i], true );
								firstRefused = firstRefused ?? this.fields[i];
								// The Newsletter feature has no text of that name, and a
								// site installed before the Form module had one has none
								// either: an empty one would refuse the submit and say
								// nothing, so the address text stands in
								error = formText( this, 'invalid' ) || formText( this, 'email' );
							}
						}

						// A required radio group, asked of the group rather than of any one
						// of its members - see the radio case above. Every member is
						// marked, because the answer that is missing is the group's
						if( error === false )
							for( const name in radioGroups )
								if( data[name] === '' ) {
									error = formText( this, 'required' );
									for( let i = 0, l = this.fields.length; i<l; i++)
										if( this.fields[i].type === 'radio' && this.fields[i].name === name ) {
											Nino.form._markField( this.fields[i], true );
											firstRefused = firstRefused ?? this.fields[i];
										}
									break;
								}

						// Catch error
						if( error !== false ) {
							this.msg.textContent = error;
							/*	The message says what is wrong; the caret says where. A
								long form refused for its last field left the visitor at
								the submit button with a sentence above the fold they
								could not see, and no way to the field but hunting for
								it - the one who most needs it being the one who cannot
								see the outline either	*/
							firstRefused?.focus?.();
							return error;
						}

						// One handler bound to this form, not a property written onto
						// the shared function object: `const uniqueResponse =
						// formResponse` was an alias, not a copy, so a second form
						// submitting while the first was still in flight overwrote
						// .form and the first response updated the wrong one.
						// sendRequest() invokes the callback via callback.call(callback,
						// ...), and a bound function's `this` survives that
						const boundResponse = formResponse.bind( { form : this } );
						this.classList.add('nino-is-pending');
						if( this.btn )
							this.btn.disabled = true;

						Nino.http.sendRequest( this.endpoint, 'POST', boundResponse, data );
					};

				for( let i=0, l=e.form.length; i<l; i++ ) {

					e.form[i].fields	= e.form[i].querySelectorAll('input, textarea, select');
					e.form[i].msg 		= e.form[i].querySelectorAll('p')[0];
					e.form[i].btn 		= e.form[i].querySelectorAll('button')[0];

					// Where the words and the answer come from. Without an action a
					// form posts to the endpoint of its own kind, below the
					// project's directory
					const signup = e.form[i].classList.contains('nino-newsletter-form') === true;
					e.form[i].texts		= e.form[i].getAttribute('data-form-texts') || ( signup === true ? '/feature/newsletter/info/' : '/module/form/info/' );
					e.form[i].endpoint	= e.form[i].getAttribute('action') || ( signup === true ? '[[/nino/dir]]/.newsletter' : '[[/nino/dir]]/.form' );

					// Both the validation error and the server's verdict are
					// written into that paragraph, and it is a project's own markup
					Nino.form._liveMessage( e.form[i].msg );

					e.form[i].addEventListener( 'submit', formSubmit );
				}
			}


		},
	};

	Nino.events.bindCallback( 'ready', Nino.form.onReady );

})(window, document);
