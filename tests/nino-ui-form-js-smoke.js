/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-form-js-smoke.js	DOM-light checks for the .nino-form submit handler:
 *								what actually reaches the server, which message a
 *								given status code produces, and which of them locks
 *								the form down.
 *
 *	Usage: node tests/nino-ui-form-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let checks = 0;
let failures = 0;

function check( label, condition ) {
	checks++;
	if( condition === true ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

function classList( initial ) {
	const values = new Set( initial || [] );
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
		toggle : function( value, force ) {
			if( force === true ) values.add( value );
			else if( force === false ) values.delete( value );
			else if( values.has( value ) ) values.delete( value );
			else values.add( value );
			return values.has( value );
		},
	};
}

function field( name, type, value, required, checked ) {
	return {
		tagName : type === 'textarea' ? 'TEXTAREA' : 'INPUT',
		name : name,
		type : type,
		value : value,
		// A real checkbox carries both: .value is what it would submit, .checked
		// whether it submits at all
		checked : checked === true,
		required : required === true,
		disabled : false,
		classList : classList(),
		parentNode : { classList : classList() },
		listeners : {},
		addEventListener : function( event, callback ) { this.listeners[event] = callback },
	};
}

// One .nino-form: the fields page-contact.tpl ships, honeypot included
function form() {
	const fields = [
		field( '_csrf', 'hidden', 'token-value', false ),
		field( 'name', 'text', '', true ),
		field( 'email', 'email', '', true ),
		field( 'message', 'textarea', '', true ),
		field( 'location', 'text', '', false ),
	];
	const msg = { textContent : '', innerHTML : '' };
	const btn = { disabled : false };

	return {
		fieldList : fields,
		msg : msg,
		btn : btn,
		classList : classList( [ 'nino-form' ] ),
		listeners : {},
		attributes : {},
		addEventListener : function( event, callback ) { this.listeners[event] = callback },
		getAttribute : function( name ) { return this.attributes[name] ?? null },
		querySelectorAll : function( selector ) {
			if( selector === 'input, textarea, select' ) return fields;
			if( selector === 'p' ) return [ msg ];
			if( selector === 'button' ) return [ btn ];
			return [];
		},
		fill : function( values ) {
			Object.keys( values ).forEach( function( key ) {
				const target = fields.find( function( f ) { return f.name === key } );
				if( target !== undefined ) target.value = values[key];
			} );
		},
		submit : function() { this.listeners.submit.call( this, { preventDefault : function() {} } ) },
	};
}

const forms = [ form(), form() ];

// Every request sendRequest() would have made, plus the callback it was
// handed - so a response can be delivered later, and out of order
const sent = [];

// Exactly how Nino.js's sendRequest() invokes it: callback.call( callback, xhr ).
// That detail is the whole point of one of the checks below - it is what let
// the handler carry its target form on the shared function object in the first
// place, and a bound callback keeps its own `this` through it
function respond( index, status ) {
	const entry = sent[index];
	entry.callback.call( entry.callback, { status : status } );
}

const documentElement = { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {} };
const body = { classList : classList(), scrollLeft : 0, scrollTop : 0 };
const document = {
	body : body,
	documentElement : documentElement,
	getElementById : function() { return null },
	querySelector : function() { return null },
	querySelectorAll : function( selector ) {
		return selector === '.nino-form:not(.nino-newsletter-form)' ? forms : [];
	},
	createElement : function() { return { classList : classList(), style : {}, setAttribute : function() {}, appendChild : function() {} } },
	addEventListener : function() {},
};

const sandbox = {
	console : console,
	document : document,
	innerHeight : 640,
	innerWidth : 1024,
	location : { hash : '' },
	requestAnimationFrame : function() {},
	setTimeout : function() {},
	addEventListener : function() {},
};
sandbox.window = sandbox;
sandbox.Nino = {
	client : { isMobile : false },
	events : { bindCallback : function() {} },
	content : {
		text : {
			'/form/info/success'  : 'Thank you – your message has been sent.',
			'/form/info/error'    : 'Your message could not be sent. Please try again later.',
			'/form/info/email'    : 'Please enter a valid email address.',
			'/form/info/required' : 'Please fill in every required field.',
			'/form/info/invalid'  : 'Please check your entries.',
		},
		getText : function( key ) { return sandbox.Nino.content.text[key] || '' },
	},
	http : {
		sendRequest : function( uri, method, callback, data ) {
			sent.push( { uri : uri, method : method, callback : callback, data : data } );
		},
	},
};

const source = fs.readFileSync( path.join( __dirname, '../_nino/Nino.ui.js' ), 'utf8' );

// Nino.ui.js is itself rendered through the normal textfill pipeline before
// the browser executes it. Mirror that production step here: running the raw
// source would post to the literal string "[[/nino/dir]]/.form" and test the
// fixture rather than the form handler. This sandbox represents a root install.
const renderedSource = source.replaceAll( '[[/nino/dir]]', '' );

vm.runInContext(
	renderedSource,
	vm.createContext( sandbox ),
	{ filename : 'Nino.ui.js' }
);

sandbox.Nino.ui.onReady();

check( 'every .nino-form on the page gets a submit handler', typeof forms[0].listeners.submit === 'function' && typeof forms[1].listeners.submit === 'function' );


// --- What actually reaches the server -----------------------------------
//
// The handler used to strip [<>'";(){}[\]\|] out of every field and write the
// stripped value back into the visible input, so "mary.o'brien@example.com"
// was submitted - and confirmed - as a different, quite possibly real mailbox.

const typedName    = "Mary O'Brien (Ltd.)";
const typedEmail   = "mary.o'brien@example.com";
const typedMessage = 'Hi; I\'d like a quote for <5 pages> — costs? {urgent}';

forms[0].fill( { name : typedName, email : typedEmail, message : typedMessage } );
forms[0].submit();

check( 'a filled form posts to /.form', sent.length === 1 && sent[0].uri === '/.form' && sent[0].method === 'POST' );
check( 'an apostrophe in an address survives to the request', sent[0].data.email === typedEmail );
check( 'punctuation in a name survives to the request', sent[0].data.name === typedName );
check( 'punctuation in a message survives to the request', sent[0].data.message === typedMessage );
check( 'the visible field is not rewritten underneath the visitor', forms[0].fieldList[2].value === typedEmail );
check( 'the csrf field is posted along with the rest', sent[0].data._csrf === 'token-value' );
check( 'the empty honeypot is posted as empty', sent[0].data.location === '' );

// A checkbox is the one control whose .value says nothing about what the
// visitor did: an unticked box with no value attribute still reads "on", so
// posting it unconditionally reported every box as ticked
const consentOff = field( 'consent', 'checkbox', 'on', false, false );
const consentOn  = field( 'consent', 'checkbox', 'yes', false, true );

forms[0].fieldList.push( consentOff );
forms[0].fields = forms[0].fieldList;
sent.length = 0;
forms[0].classList.remove('nino-is-success');
forms[0].submit();
check( 'an unticked checkbox is posted as empty, not as its value', sent.length === 1 && sent[0].data.consent === '' );

forms[0].fieldList[ forms[0].fieldList.length - 1 ] = consentOn;
forms[0].fields = forms[0].fieldList;
sent.length = 0;
forms[0].classList.remove('nino-is-success');
forms[0].submit();
check( 'a ticked one is posted as the value it carries', sent.length === 1 && sent[0].data.consent === 'yes' );

forms[0].fieldList.pop();
forms[0].fields = forms[0].fieldList;

// A radio is the other control whose .value says nothing about what the
// visitor did, and unlike a checkbox a group of them shares one name. The
// loop writes data[name] once per member, so without a case of its own the
// last member won whichever one was ticked - a visitor picking the first
// option had the last one submitted, stored and mailed. And .value is never
// empty on a radio, so a required group nobody answered passed the required
// check and reached the server as an answer it had not been given
const planSmall = field( 'plan', 'radio', 'small', true, false );
const planLarge = field( 'plan', 'radio', 'large', true, true );
const planHuge  = field( 'plan', 'radio', 'huge',  true, false );

forms[0].fieldList.push( planSmall, planLarge, planHuge );
forms[0].fields = forms[0].fieldList;
sent.length = 0;
forms[0].classList.remove('nino-is-success');
forms[0].submit();
check( 'the ticked radio is posted, not the last one of its group', sent.length === 1 && sent[0].data.plan === 'large' );

// The ticked one first this time: order must not decide the answer either way
forms[0].fieldList[ forms[0].fieldList.length - 3 ] = field( 'plan', 'radio', 'small', true, true );
forms[0].fieldList[ forms[0].fieldList.length - 2 ] = field( 'plan', 'radio', 'large', true, false );
forms[0].fields = forms[0].fieldList;
sent.length = 0;
forms[0].classList.remove('nino-is-success');
forms[0].submit();
check( '...and a group whose first member is the ticked one posts that one', sent.length === 1 && sent[0].data.plan === 'small' );

// Nobody answered a required group: it is the group that is empty, not any
// one member, and .value tells nothing about it
forms[0].fieldList[ forms[0].fieldList.length - 3 ] = field( 'plan', 'radio', 'small', true, false );
forms[0].fields = forms[0].fieldList;
sent.length = 0;
forms[0].classList.remove('nino-is-success');
forms[0].submit();
check( 'a required radio group nobody answered is refused rather than sent', sent.length === 0 && forms[0].msg.textContent === 'Please fill in every required field.' );

// An optional group nobody answered is empty, and that is an answer
forms[0].fieldList[ forms[0].fieldList.length - 3 ] = field( 'plan', 'radio', 'small', false, false );
forms[0].fieldList[ forms[0].fieldList.length - 2 ] = field( 'plan', 'radio', 'large', false, false );
forms[0].fieldList[ forms[0].fieldList.length - 1 ] = field( 'plan', 'radio', 'huge',  false, false );
forms[0].fields = forms[0].fieldList;
sent.length = 0;
forms[0].classList.remove('nino-is-success');
forms[0].msg.textContent = '';
forms[0].submit();
check( '...while an optional one nobody answered is posted as empty', sent.length === 1 && sent[0].data.plan === '' );

forms[0].fieldList.pop();
forms[0].fieldList.pop();
forms[0].fieldList.pop();
forms[0].fields = forms[0].fieldList;
sent.length = 0;
forms[0].classList.remove('nino-is-success');
forms[0].msg.textContent = '';
forms[0].fill( { name : typedName, email : typedEmail, message : typedMessage } );
forms[0].submit();


// --- Which message a status code produces -------------------------------

forms[1].fill( { name : 'Someone', email : 'someone@example.com', message : 'Hello.' } );
forms[1].submit();
check( 'a second form submits independently', sent.length === 2 );

// Answered in reverse order on purpose: the callback used to be a property on
// one shared function object, so the second submit overwrote the first's
// target and this response landed on the wrong form
respond( 1, 400 );
check( 'a 400 answers the form it belongs to', forms[1].msg.textContent === 'Please enter a valid email address.' );
check( '...and not the other one still in flight', forms[0].msg.textContent === '' );
check( 'a 400 names the field instead of asking the visitor to try later', forms[1].msg.textContent !== 'Your message could not be sent. Please try again later.' );
check( 'a rejected form stays editable', forms[1].btn.disabled === false && forms[1].fieldList.every( function( f ) { return f.disabled === false } ) );

/*	...unless the form carries a field the client never checked.
	Form::TYPES has url, number and select in it too, and validate()
	refuses all three - so a 400 there is not necessarily the address, and
	saying it is sends the visitor to look at the one field that was fine	*/
forms[1].fieldList.push( field( 'website', 'url', 'my site', false, false ) );
forms[1].fields = forms[1].fieldList;
forms[1].classList.remove('nino-is-error');
forms[1].msg.textContent = '';
forms[1].submit();
respond( sent.length - 1, 400 );
check( 'a 400 on a form with a url field asks the visitor to check their entries, not their address', forms[1].msg.textContent === 'Please check your entries.' );

// A site installed before that fill existed has no '/form/info/invalid',
// and getText() answers '' for a key it does not have - the address text
// is the fallback then, as it was before, rather than an empty line
delete sandbox.Nino.content.text['/form/info/invalid'];
forms[1].classList.remove('nino-is-error');
forms[1].msg.textContent = '';
forms[1].submit();
respond( sent.length - 1, 400 );
check( '...and a site without that fill is told about the address rather than nothing', forms[1].msg.textContent === 'Please enter a valid email address.' );
sandbox.Nino.content.text['/form/info/invalid'] = 'Please check your entries.';

// The browser's own verdict is what the client refuses on, so a stand-in
// without one submits and lets the server answer - which is what just
// happened above
forms[1].fieldList[ forms[1].fieldList.length - 1 ].validity = { typeMismatch : true, badInput : false };
forms[1].fields = forms[1].fieldList;
forms[1].classList.remove('nino-is-error');
forms[1].msg.textContent = '';
const beforeTyped = sent.length;
forms[1].submit();
check( 'a url the browser calls invalid is refused before the request', sent.length === beforeTyped
	&& forms[1].msg.textContent === 'Please check your entries.' );
check( '...and can be submitted again', forms[1].classList.contains('success') === false );

respond( 0, 200 );
check( 'a 200 shows the success text', forms[0].msg.textContent === 'Thank you – your message has been sent.' );
check( 'a delivered message locks the form', forms[0].btn.disabled === true && forms[0].fieldList.every( function( f ) { return f.disabled === true } ) );
check( 'the pending state is cleared either way', forms[0].classList.contains('pending') === false && forms[1].classList.contains('pending') === false );

// Anything that is not the visitor's own field value stays generic - naming
// the csrf 403 or the honeypot's 418 tells a spam bot which check it tripped
const generic = form();
forms.push( generic );
sandbox.Nino.ui.onReady();
generic.fill( { name : 'Bot', email : 'bot@example.com', message : 'spam', location : 'filled' } );
generic.submit();
respond( sent.length - 1, 418 );
check( 'a honeypot rejection stays on the generic message', generic.msg.textContent === 'Your message could not be sent. Please try again later.' );


// --- Editor-authored text is never treated as markup --------------------

check( 'messages are written as text, not html', forms[0].msg.innerHTML === '' && forms[1].msg.innerHTML === '' );

check( 'the default endpoint remains relative to the rendered project root', source.includes( "'[[/nino/dir]]/.form'" ) );
check( 'no form handler writes a textfill through innerHTML', /msg\.innerHTML\s*=/.test( source ) === false );
check( 'no form handler strips characters out of a field value', /value\.replace\(\s*\/\[<>/.test( source.replace( /\s/g, '' ) ) === false && source.includes( "value.replace( /[<>'\";(){}[\\]\\\\|]/g, '' )" ) === false );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
