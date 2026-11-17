/**
 *	Nino									A compact filesystembased php framework
 *	nino-ui-previewframe-js-smoke.js	Nino.adminUi.previewFrame(): the loop that keeps a preview in step with a draft that is
 *													not saved - one request at a time, what changes meanwhile sent once more, the token
 *													kept and asked for again before it runs out, the frame loaded only with an answer that
 *													holds, a failure left to the caller, a panel that is left answered by nothing.
 *													The server is a function of the suite, and so are the clock and the timers.
 *
 *	Usage: node tests/nino-ui-previewframe-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, documentOf, fills } = require( './harness.js' );

const clock = { now : 1000000 };
const timers = [];

class FakeDate extends Date {
	static now() { return clock.now }
}

const sandbox = { console : console, document : documentOf( element('div') ), Date : FakeDate, location : { host : 'localhost', origin : 'http://localhost', href : 'http://localhost/_admin/' }, URL : URL };
sandbox.window = sandbox;
sandbox.setTimeout = function( fn, ms ) { timers.push( { fn : fn, ms : ms, live : true } ); return timers.length - 1 };
sandbox.clearTimeout = function( id ) { if( timers[id] !== undefined ) timers[id].live = false };
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

// The pieces the loop is given: a split that records what it is asked, and a server that answers when the suite says so
function build( extra ) {

	const world = { sent : [], loaded : [], said : [], widths : [], pending : [] };

	world.split = { reload : function( src ) { world.loaded.push( src ) }, width : function( px ) { world.widths.push( px ); return px } };
	world.frame = adminUi.previewFrame( Object.assign( {
		split : world.split,
		send : function( token, answer ) { world.sent.push( token ); world.pending.push( answer ) },
		address : function( query ) { return '/page?'+ query },
		onError : function( status ) { world.said.push( 'error:'+ status ) },
		onUnavailable : function() { world.said.push('unavailable') },
		onRenew : function() { world.said.push('renew') },
		onUpdate : function() { world.said.push('update') },
	}, extra || {} ) );

	return world;
}

const draft = function( token, ttl ) { return { token : token, query : 'nino-preview='+ token, ttl : ttl ?? 900 } };

console.log( 'Nino.adminUi.previewFrame - a preview that follows a draft\n' );

// --- one request at a time -----------------------------------------------------------------

{
	const w = build();
	const results = [];

	w.frame.flush( function( ok ) { results.push( ok ) } );
	w.frame.flush( function( ok ) { results.push( ok ) } );
	check( 'a second flush while a request is out sends nothing: one request is in flight', w.sent.length === 1 && w.sent[0] === '' );

	w.pending.shift()( 200, draft( 'aaa' ) );
	check( '...what was asked meanwhile is sent once more, and the frame is not loaded for the answer that is out of date', w.sent.length === 2 && w.loaded.length === 0 && results.length === 0 );
	check( '...with the token the server gave, so that it is renewed and not made again', w.sent[1] === 'aaa' );

	w.pending.shift()( 200, draft( 'bbb' ) );
	check( 'the last answer loads the frame with the address that has its query, tells the caller, and calls everybody who waited', w.loaded.join() === '/page?nino-preview=bbb' && w.said.join() === 'update' && results.join() === 'true,true' );
}

// --- an answer that is none --------------------------------------------------------------------

{
	const w = build();
	const results = [];

	w.frame.flush( function( ok ) { results.push( ok ) } );
	w.pending.shift()( 403, { code : 'no' } );
	check( 'a failed request is told to the caller and loads nothing; the waiting are told it failed', w.said.join() === 'error:403' && w.loaded.length === 0 && results.join() === 'false' );

	w.frame.flush();
	w.pending.shift()( 200, { token : 'x' } );
	check( 'an answer that holds no query is no draft: the last picture stays', w.said.join() === 'error:403,unavailable' && w.loaded.length === 0 );

	w.frame.flush();
	w.pending.shift()( 200, null );
	check( '...nor is an answer that is nothing', w.said.length === 3 && w.loaded.length === 0 );

	w.frame.flush();
	check( 'and a failure does not keep the next request from being sent', w.sent.length === 4 );
}

// --- the token runs out ------------------------------------------------------------------------

{
	const w = build();

	w.frame.flush();
	w.pending.shift()( 200, draft( 'aaa', 60 ) );
	check( 'a token that holds is loaded as it is', w.loaded.length === 1 && w.sent.length === 1 );

	clock.now += 54000;
	w.frame.reload();
	check( 'a reload within the time the token holds loads the frame again', w.loaded.length === 2 && w.sent.length === 1 );

	clock.now += 2000;
	w.frame.reload();
	check( 'a reload within the grace of the end asks for the draft again first - the caller is told - and loads nothing yet', w.said.includes('renew') === true && w.sent.length === 2 && w.sent[1] === 'aaa' && w.loaded.length === 2 );

	w.pending.shift()( 200, draft( 'aaa', 900 ) );
	check( '...and loads the page when the new answer holds', w.loaded.length === 3 );
}

// --- the hand rests -------------------------------------------------------------------------------

{
	const w = build();
	timers.length = 0;

	w.frame.schedule();
	w.frame.schedule( 700 );
	check( 'schedule() waits for the hand to rest: the first wait is taken back by the second, which is as long as it says', timers.length === 2 && timers[0].live === false && timers[1].live === true && timers[0].ms === 150 && timers[1].ms === 700 );

	timers[1].fn();
	check( '...and then sends the draft', w.sent.length === 1 );
	const mine = build();
	timers.length = 0;
	mine.frame.schedule();
	mine.frame.flush();
	check( 'a flush takes back the wait that is still out: a change and an at once are one request, and the wait does not send a second', mine.sent.length === 1 && timers[0].live === false );

	check( 'the rest and the grace can be set', build( { quiet : 20 } ).frame.schedule() === undefined && timers[timers.length - 1].ms === 20 );
}

// --- the width ----------------------------------------------------------------------------

{
	const w = build();

	w.frame.width('tablet');
	w.frame.width('mobile');
	w.frame.width('nothing');
	check( 'width() lays the page out at the width of a name and gives the pane its own where the name is none', w.widths.join() === '768,390,' );
}

// --- the panel is left ----------------------------------------------------------------------------

{
	const w = build();

	w.frame.flush();
	w.frame.stop();
	w.pending.shift()( 200, draft( 'aaa' ) );
	check( 'after stop() an answer loads nothing and tells nobody', w.loaded.length === 0 && w.said.length === 0 );
	const told = [];
	w.frame.flush( function( ok ) { told.push( ok ) } );
	w.frame.schedule();
	check( '...nothing is sent, and whoever waits is told it came to nothing', w.sent.length === 1 && told.join() === 'false' );
}

done();
