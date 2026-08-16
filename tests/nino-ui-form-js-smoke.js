/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-form-js-smoke.js	DOM-light checks for the .ui-form submit handler:
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

function field( name, type, value, required ) {
	return {
		tagName : type === 'textarea' ? 'TEXTAREA' : 'INPUT',
		name : name,
		type : type,
		value : value,
		required : required === true,
		disabled : false,
		classList : classList(),
		parentNode : { classList : classList() },
		listeners : {},
		addEventListener : function( event, callback ) { this.listeners[event] = callback },
	};
}

// One .ui-form: the fields page-contact.tpl ships, honeypot included
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
		classList : classList( [ 'ui-form' ] ),
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
		return selector === '.ui-form:not(.js-newsletter-form)' ? forms : [];
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
		},
		getText : function( key ) { return sandbox.Nino.content.text[key] || '' },
	},
	http : {
		sendRequest : function( uri, method, callback, data ) {
			sent.push( { uri : uri, method : method, callback : callback, data : data } );
		},
	},
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_nino/Nino.ui.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'Nino.ui.js' }
);

sandbox.Nino.ui.onReady();

check( 'every .ui-form on the page gets a submit handler', typeof forms[0].listeners.submit === 'function' && typeof forms[1].listeners.submit === 'function' );


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

const source = fs.readFileSync( path.join( __dirname, '../_nino/Nino.ui.js' ), 'utf8' );
check( 'no form handler writes a textfill through innerHTML', /msg\.innerHTML\s*=/.test( source ) === false );
check( 'no form handler strips characters out of a field value', /value\.replace\(\s*\/\[<>/.test( source.replace( /\s/g, '' ) ) === false && source.includes( "value.replace( /[<>'\";(){}[\\]\\\\|]/g, '' )" ) === false );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
