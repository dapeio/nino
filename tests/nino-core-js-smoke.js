/**
 *	Nino									A compact filesystembased php framework
 *	nino-core-js-smoke.js	Nino.js, which every page loads: the resize/scroll
 *												throttle that every registered callback hangs off and
 *												that runs on every frame of a scroll, a callback that
 *												throws, and the two helpers a panel script and a site
 *												script both use - the slug and the clipboard.
 *
 *	Usage: node tests/nino-core-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, source } = require( './harness.js' );

/**
 *	Nino.js in a page of its own: the listeners it registers are kept, with the
 *	options they were added with, and so are the animation frames and the timers
 *	it asks for, so a test can run them by hand
 *
 *	@param		{Object}	[extra]		{ navigator, document } - what the page has instead of the plain ones; a document is laid over the plain one
 *
 *	@return		{Object}
 */
function page( extra ) {

	extra = extra || {};

	const listeners = {};
	const registered = [];
	const frames = [];
	const timers = [];

	const documentElement = { clientHeight : 640, clientWidth : 1024, scrollLeft : 0, scrollTop : 0, style : {}, classList : { add : function() {}, remove : function() {} } };
	const body = { classList : { add : function() {}, remove : function() {} }, scrollLeft : 0, scrollTop : 0 };

	const document = Object.assign( {
		body : body,
		documentElement : documentElement,
		cookie : '',
		readyState : 'complete',
		getElementById : function() { return null },
		querySelector : function() { return null },
		querySelectorAll : function() { return [] },
		createElement : function() { return { classList : { add : function() {} }, style : {}, setAttribute : function() {}, appendChild : function() {} } },
		addEventListener : function() {},
	}, extra.document || {} );

	const sandbox = {
		console : console,
		document : document,
		navigator : extra.navigator || { userAgent : 'node', language : 'en-US' },
		location : { pathname : '/', origin : 'https://example.com', href : 'https://example.com/' },
		XMLHttpRequest : function() {},
		FormData : function() {},
		TextEncoder : TextEncoder,
		btoa : function( binary ) { return Buffer.from( binary, 'latin1' ).toString('base64') },
		atob : function( b64 ) { return Buffer.from( b64, 'base64' ).toString('latin1') },
		setTimeout : function( fn, delay ) { timers.push( { fn : fn, delay : delay } ); return timers.length },
		clearTimeout : function() {},
		Event : function( type ) { this.type = type },
		dispatchEvent : function( event ) { ( listeners[event.type] || [] ).forEach( function( fn ) { fn( event ) } ) },
		addEventListener : function( type, fn, options ) {
			( listeners[type] = listeners[type] || [] ).push( fn );
			registered.push( { type : type, options : options } );
		},
		requestAnimationFrame : function( fn ) { frames.push( fn ); return frames.length },
		matchMedia : function() { return { matches : false, addEventListener : function() {} } },
	};

	sandbox.window = sandbox;
	sandbox.self = sandbox;

	load( '_nino/Nino.js', vm.createContext( sandbox ) );

	return {
		Nino : sandbox.Nino,
		fire : function( type, event ) { ( listeners[type] || [] ).forEach( function( fn ) { fn( event ) } ) },
		runFrames : function() { const queued = frames.splice( 0, frames.length ); queued.forEach( function( fn ) { fn() } ); return queued.length },
		frames : function() { return frames.length },
		// The timers that were asked for, taken out of the queue: the page's own ready callback, one millisecond out, and every error thrown again
		runTimers : function() { const queued = timers.splice( 0, timers.length ); queued.forEach( function( timer ) { timer.fn() } ); return queued.length },
		timers : timers,
		registered : registered,
	};
}


// --- The resize and scroll throttle ----------------------------------------

console.log( 'Nino.events - one callback round per frame, not one per event\n' );

const scrolling = page();
let scrollRuns = 0;
scrolling.Nino.events.bindCallback( 'scroll', function() { scrollRuns++ } );

scrolling.fire( 'scroll', { type : 'scroll', which : 1 } );
scrolling.fire( 'scroll', { type : 'scroll', which : 2 } );
scrolling.fire( 'scroll', { type : 'scroll', which : 3 } );
check( 'a burst of scroll events asks for one animation frame, not one each', scrolling.frames() === 1 );

scrolling.runFrames();
check( '...and the callbacks run once for the burst', scrollRuns === 1 );

scrolling.fire( 'scroll', { type : 'scroll', which : 4 } );
check( 'the next event after that frame is taken again', scrolling.frames() === 1 );
scrolling.runFrames();
check( '...and runs the callbacks again', scrollRuns === 2 );

const resizing = page();
let resizeEvent = null;
resizing.Nino.events.bindCallback( 'resize', function( event ) { resizeEvent = event } );

resizing.fire( 'resize', { type : 'resize' } );
resizing.fire( 'resize', { type : 'resize' } );
check( 'a burst of resize events asks for one animation frame too', resizing.frames() === 1 );

resizing.runFrames();
check( '...and a resize callback is handed the resize event, not the last scroll', resizeEvent !== null && resizeEvent.type === 'resize' );

const listening = page();
const listenersOf = function( type ) { return listening.registered.filter( function( entry ) { return entry.type === type } ) };
check( 'the scroll and the resize listeners are passive: no callback of theirs cancels anything', listenersOf('scroll').length === 1 && listenersOf('scroll')[0].options.passive === true
	&& listenersOf('resize').length === 1 && listenersOf('resize')[0].options.passive === true );

const phone = page( { navigator : { userAgent : 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', language : 'en-US' } } );
const phoneListens = function( type ) { return phone.registered.filter( function( entry ) { return entry.type === type } ) };
check( '...on a phone the orientation listener is passive too, and stands in for the resize one', phoneListens('deviceorientation').length === 1 && phoneListens('deviceorientation')[0].options.passive === true && phoneListens('resize').length === 0 );


// --- A callback that throws ------------------------------------------------

console.log( '\nNino.events - one callback that throws does not stop the ones after it\n' );

/**
 *	Run what a page has queued in timers and hand back the errors that were
 *	thrown again: each one a timer asked for with no delay
 *
 *	@param		{Object}		stub		A page()
 *
 *	@return		{Object}						{ messages, delays }
 */
function thrownAgain( stub ) {

	const messages = [];
	const delays = [];

	stub.timers.splice( 0, stub.timers.length ).forEach( function( timer ) {
		delays.push( timer.delay );
		try { timer.fn() } catch( error ) { messages.push( error.message ) }
	} );

	return { messages : messages, delays : delays };
}

const starting = page();
const readyOrder = [];
let framesBeforeReady = -1;
starting.Nino.events.bindCallback( 'ready', function() { framesBeforeReady = starting.frames(); readyOrder.push( 'first' ); throw new Error( 'a ready callback of somebody elses' ) } );
starting.Nino.events.bindCallback( 'ready', function() { readyOrder.push( 'second' ) } );
starting.runTimers();
check( 'a ready callback that throws does not stop the ones after it', readyOrder.join() === 'first,second' );

const readyErrors = thrownAgain( starting );
check( '...its error is thrown again from a timer with no delay, which is where the browser reports it', readyErrors.messages.join() === 'a ready callback of somebody elses' && readyErrors.delays.join() === '0' );
check( '...and the first resize and scroll of the page were dispatched before any ready callback ran', framesBeforeReady === 2 );

let lateEvent = null;
starting.Nino.events.bindCallback( 'ready', function( event ) { lateEvent = event } );
check( 'a callback bound after the page was ready is called at once', lateEvent !== null );

let lateReturned = false;
let lateThrew = null;
try {
	starting.Nino.events.bindCallback( 'ready', function() { throw new Error( 'a late ready callback of somebody elses' ) } );
	lateReturned = true;
} catch( error ) { lateThrew = error.message }
const lateErrors = thrownAgain( starting );
check( '...one that throws is thrown again from a timer with no delay, like a queued one, and the binding call returns', lateReturned === true && lateThrew === null
	&& lateErrors.messages.join() === 'a late ready callback of somebody elses' && lateErrors.delays.join() === '0' );

// A page that has started: its own ready callback ran and the frames that asked for are done
const ticking = page();
ticking.runTimers();
ticking.runFrames();
const tickOrder = [];
ticking.Nino.events.bindCallback( 'scroll', function() { tickOrder.push( 'scroll first' ); throw new Error( 'a scroll callback of somebody elses' ) } );
ticking.Nino.events.bindCallback( 'scroll', function() { tickOrder.push( 'scroll second' ) } );
ticking.Nino.events.bindCallback( 'resize', function() { tickOrder.push( 'resize first' ); throw new Error( 'a resize callback of somebody elses' ) } );
ticking.Nino.events.bindCallback( 'resize', function() { tickOrder.push( 'resize second' ) } );

ticking.fire( 'scroll', { type : 'scroll' } );
ticking.fire( 'resize', { type : 'resize' } );
let frameThrew = null;
try { ticking.runFrames() } catch( error ) { frameThrew = error.message }
check( 'a scroll callback and a resize callback that throw do not stop the ones after them', tickOrder.join() === 'scroll first,scroll second,resize first,resize second' );
check( '...the frame itself does not throw, the errors are thrown again from timers', frameThrew === null && thrownAgain( ticking ).messages.join() === 'a scroll callback of somebody elses,a resize callback of somebody elses' );

ticking.fire( 'scroll', { type : 'scroll' } );
check( '...and the next scroll is taken again', ticking.frames() === 1 );


// --- The slug --------------------------------------------------------------

console.log( '\nNino.slug - the twin of \\Nino\\Feature::slug()\n' );

const core = page().Nino;

check( 'the umlauts and the sharp s are spelled out, every other run is one hyphen', core.slug( 'Äpfel & Öl, groß!' ) === 'aepfel-oel-gross' );
check( '...a letter ascii lacks is written the way the table says', core.slug( 'Ærøskøbing' ) === 'aeroskobing' && core.slug( 'Crème brûlée' ) === 'creme-brulee' && core.slug( 'ÄÖÜ Straße É' ) === 'aeoeue-strasse-e' );
check( '...no hyphen at either end', core.slug( '  --Hello,  World--  ' ) === 'hello-world' );

check( 'the cut never leaves a hyphen at the end of the slug', core.slug( 'a'.repeat( 59 ) + ' b' ) === 'a'.repeat( 59 ) && core.slug( 'alpha beta gamma', 6 ) === 'alpha' );
check( '...at most the longest asked for', core.slug( 'abcdef', 3 ) === 'abc' && core.slug( 'alpha beta', 10 ) === 'alpha-beta' );
check( '...60 without one, and for anything that is no positive whole number', core.slug( 'a'.repeat( 100 ) ).length === 60 && [ 0, -3, 2.5, '5', null, NaN ].every( function( max ) { return core.slug( 'a'.repeat( 100 ), max ).length === 60 } ) );

check( 'nothing but hyphens, no text, and no string at all are no slug', core.slug( '---' ) === '' && core.slug( ' -- !? ' ) === '' && core.slug( '' ) === '' && core.slug( null ) === '' && core.slug( undefined ) === '' && core.slug( 42 ) === '' );

// The two tables are one: every letter of the PHP constant comes out as the
// letters the constant gives it, and the JS table holds those pairs, in that
// order, and no other - a letter only one side knows would slug differently
// in the browser and on the server
const table = /public const array TRANSLITERATIONS = \[([\s\S]*?)\n\t\t\];/.exec( source( '_nino/Nino/Feature/Feature.php' ) );
const pairs = table === null ? [] : Array.from( table[1].matchAll( /'([^']+)' => '([a-z]+)'/g ), function( match ) { return match[1]+ '='+ match[2] } );
check( 'every letter of \\Nino\\Feature::TRANSLITERATIONS is slugged to the letters the table gives it', pairs.length > 0 && pairs.every( function( pair ) { return core.slug( pair.split('=')[0] ) === pair.split('=')[1] } ) );
const twin = /const TRANSLITERATIONS = \{([\s\S]*?)\n\t\};/.exec( source( '_nino/Nino.js' ) );
const twinPairs = twin === null ? [] : Array.from( twin[1].matchAll( /'([^']+)' : '([a-z]+)'/g ), function( match ) { return match[1]+ '='+ match[2] } );
check( '...and the table of Nino.js is that constant pair for pair, in its order, with no pair of its own', twinPairs.length > 0 && twinPairs.join( ' ' ) === pairs.join( ' ' ) );


// --- The clipboard ---------------------------------------------------------

console.log( '\nNino.clipboard - the api first, the older way when there is none or it refuses\n' );

/**
 *	A page with a clipboard of its own, or none, and a copy command that
 *	answers what the test says. What the textarea looked like while the command
 *	ran is kept: it is gone by the time the answer arrives
 *
 *	@param		{Object|undefined}	clipboard		navigator.clipboard
 *	@param		{Function}					command			Called with the command's name, answers a boolean or throws
 *
 *	@return		{Object}
 */
function copying( clipboard, command ) {

	const body = element('body');
	const made = [];
	const commands = [];
	const seen = [];

	const stub = page( {
		navigator : { userAgent : 'node', language : 'en-US', clipboard : clipboard },
		document : {
			body : body,
			createElement : function( tag ) { const el = element( tag ); made.push( el ); return el },
			execCommand : function( name ) {
				const field = made[made.length - 1];
				commands.push( name );
				seen.push( { inBody : body.children.indexOf( field ) !== -1, value : field.value, readonly : field.attributes.readonly, hidden : field.attributes['aria-hidden'], position : field.style.position, top : field.style.top } );
				return command( name );
			},
		},
	} );

	return { Nino : stub.Nino, body : body, made : made, commands : commands, seen : seen };
}

const written = [];
const accepting = { writeText : function( text ) { written.push( text ); return Promise.resolve() } };
const refusing = { writeText : function( text ) { written.push( text ); return Promise.reject( new Error( 'denied' ) ) } };
const forbidden = { writeText : function( text ) { written.push( text ); throw new Error( 'not allowed in this frame' ) } };

( async function() {

	let c = copying( accepting, function() { return false } );
	let answer = await c.Nino.clipboard( 'Ein Text' );
	check( 'where the api takes the text the answer is true, and nothing else was done', answer === true && written.join() === 'Ein Text' && c.made.length === 0 && c.commands.length === 0 );

	written.length = 0;
	c = copying( refusing, function() { return true } );
	answer = await c.Nino.clipboard( 'Ein Text' );
	check( 'where the api refuses, the older way is tried and its answer is the answer', answer === true && written.join() === 'Ein Text' && c.commands.join() === 'copy' );
	check( '...it copies from a textarea of the moment: in the page, read only, hidden from a screen reader and out of sight, holding the text', c.seen.length === 1 && c.seen[0].inBody === true && c.seen[0].value === 'Ein Text'
		&& c.seen[0].readonly === '' && c.seen[0].hidden === 'true' && c.seen[0].position === 'fixed' && c.seen[0].top === '-1000px' );
	check( '...and the textarea is gone again', c.body.children.length === 0 );

	c = copying( forbidden, function() { return true } );
	answer = await c.Nino.clipboard( 'Ein Text' );
	check( 'an api that throws on the call is a refusal too', answer === true && c.commands.length === 1 && c.body.children.length === 0 );

	c = copying( undefined, function() { return true } );
	answer = await c.Nino.clipboard( 'Ein Text' );
	check( 'without the api the older way is the only one: true when the command copied', answer === true && c.commands.length === 1 && c.seen[0].value === 'Ein Text' && c.body.children.length === 0 );

	c = copying( undefined, function() { return false } );
	answer = await c.Nino.clipboard( 'Ein Text' );
	check( '...false when the command did not', answer === false && c.body.children.length === 0 );

	c = copying( undefined, function() { throw new Error( 'the command is not allowed here' ) } );
	answer = await c.Nino.clipboard( 'Ein Text' );
	check( '...and false, not a rejection, when it throws - the textarea is gone in every case', answer === false && c.body.children.length === 0 );

	c = copying( refusing, function() { return false } );
	answer = await c.Nino.clipboard( 'Ein Text' );
	check( 'an api that refuses and a command that fails together are false', answer === false && c.body.children.length === 0 );

	written.length = 0;
	c = copying( accepting, function() { return true } );
	await c.Nino.clipboard( 42 );
	await c.Nino.clipboard( null );
	check( 'what is no string is copied as one: a number as its digits, no text as nothing', written.length === 2 && written[0] === '42' && written[1] === '' );

	done();
} )();
