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
const { check, done, load, element, documentOf, fills, fire } = require( './harness.js' );

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

// --- the marks of the page in the frame ---------------------------------------------------------------

// A page of the frame: a body, the elements of two things - one of them drawn twice, as the pattern of a loop is - and a window that scrolls
function page() {

	const made = function( tag ) {
		const el = element( tag );
		el.attachShadow = function() { el.shadow = element( 'shadow' ); return el.shadow };
		return el;
	};
	const thing = function( key, top ) {
		const el = element( 'div' );
		el.key = key;
		el.getBoundingClientRect = function() { return { top : top, left : 10, width : 200, height : 50 } };
		return el;
	};
	const doc = Object.assign( element( 'document' ), { createElement : made, body : made( 'body' ), documentElement : made( 'html' ) } );
	const win = element( 'window' );

	win.scrollX = 0;
	win.scrollY = 100;
	win.innerHeight = 600;
	win.scrolledTo = [];
	win.frames = [];
	win.requestAnimationFrame = function( fn ) { win.frames.push( fn ) };
	win.scrollTo = function( where ) { win.scrolledTo.push( where ) };
	doc.things = { a : [ thing( 'a', 20 ), thing( 'a', 400 ) ], b : [ thing( 'b', 90 ) ] };

	return { doc : doc, win : win };
}

{
	const first = page();
	const frame = element( 'iframe' );
	const hovered = [];

	frame.contentDocument = first.doc;
	frame.contentWindow = first.win;

	const marks = adminUi.frameMarks( {
		frame : frame,
		elements : function( key ) { return frame.contentDocument.things[key] ?? [] },
		keyAt : function( target ) { return target.key ?? null },
		onHover : function( key ) { hovered.push( key ) },
	} );
	const layer = function( doc ) { return doc.body.children[0].shadow.children[1] };

	check( 'the page is given one element, in the shadow of which the marks are drawn, hidden from a screen reader, with no box as long as nothing is marked',
		first.doc.body.children.length === 1 && first.doc.body.children[0].tagName === 'NINO-UI-MARKS' && first.doc.body.children[0].getAttribute( 'aria-hidden' ) === 'true' && layer( first.doc ).children.length === 0 );

	marks.highlight( 'a' );
	check( 'a key is marked with a box on every element that stands for it, where it is in the viewport of the page, and nobody is told',
		layer( first.doc ).children.map( function( box ) { return box.style.top }).join() === '20px,400px' && layer( first.doc ).children[0].style.width === '200px' && hovered.length === 0 );
	marks.highlight( [ 'b' ] );
	marks.highlight( null );
	check( 'a list of keys and null replace what was marked, and null takes every box away', layer( first.doc ).children.length === 0 && first.win.scrolledTo.length === 0 );
	marks.highlight( 'b', { scroll : true } );
	check( 'with scroll the page rolls to the first of them, one third of the height from the top', first.win.scrolledTo.length === 1 && first.win.scrolledTo[0].top === 0 );

	first.doc.documentElement.dispatchEvent( { type : 'pointerleave' } );
	fire( first.doc, 'pointerover', { target : first.doc.things.a[1] } );
	fire( first.doc, 'pointerover', { target : first.doc.things.a[0] } );
	fire( first.doc, 'pointerover', { target : first.doc.things.b[0] } );
	first.doc.documentElement.dispatchEvent( { type : 'pointerleave' } );
	check( 'the pointer over a thing of the page is told with its key, once for each change - the two drawings of one key are one thing - and null where it leaves', hovered.join() === 'a,b,' );

	first.win.dispatchEvent( { type : 'resize' } );
	check( 'a page that is laid out again has its boxes drawn again', layer( first.doc ).children.length === 1 );

	// the page scrolls: the elements are where they are in the viewport now, and the boxes with them - once for the scrolls of one frame
	first.doc.things.b[0].getBoundingClientRect = function() { return { top : -40, left : 10, width : 200, height : 50 } };
	first.win.dispatchEvent( { type : 'scroll' } );
	first.win.dispatchEvent( { type : 'scroll' } );
	check( 'a page that scrolls has its boxes drawn where the elements are now, once for the scrolls that come before the next frame, and a host that is fixed in the viewport',
		first.win.frames.length === 1 && ( first.win.frames.shift()(), layer( first.doc ).children[0].style.top === '-40px' ) && first.doc.body.children[0].shadow.children[0].textContent.indexOf( 'position:fixed' ) !== -1 );

	// the frame loads another page: the marks come back, on it alone
	const second = page();
	frame.contentDocument = second.doc;
	frame.contentWindow = second.win;
	frame.dispatchEvent( { type : 'load' } );
	check( 'a page the frame loads has the marks as well - the key that was marked - and the page that is gone has no listener left',
		second.doc.body.children.length === 1 && layer( second.doc ).children.length === 1 && ( hovered.length = 0, fire( first.doc, 'pointerover', { target : first.doc.things.a[0] } ), hovered.length === 0 ) );
	fire( second.doc, 'pointerover', { target : second.doc.things.a[0] } );
	check( 'and the new page tells what the pointer is over', hovered.join() === 'a' );

	// a page of another origin, or the page of an error: its window answers nothing
	second.win.removeEventListener = function() { throw new Error( 'SecurityError: blocked a frame' ) };
	frame.contentDocument = null;
	frame.contentWindow = element( 'foreign' );
	let thrown = null;
	try {
		frame.dispatchEvent( { type : 'load' } );
		marks.highlight( 'a' );
		marks.refresh();
	}
	catch( error ) {
		thrown = error;
	}
	check( 'a frame that lands on a page that is not the workbench\'s - the listeners of the last page cannot be taken off its window - throws nothing, and the marks are silent', thrown === null && second.doc.body.children.length === 0 );

	// many repetitions: the measures are read before the first box is written
	const third = page();
	const log = [];
	const crowd = [];
	for( let at = 0; at < 400; at++ ) {
		const el = element( 'div' );
		el.key = 'many';
		el.getBoundingClientRect = function() { log.push( 'read' ); return { top : at, left : 0, width : 10, height : 10 } };
		crowd.push( el );
	}
	third.doc.things.many = crowd;
	const append = third.doc.createElement;
	third.doc.createElement = function( tag ) {
		const el = append( tag );
		if( tag === 'div' )
			el.style = new Proxy( el.style, { set : function( target, name, value ) { log.push( 'write' ); target[name] = value; return true } } );
		return el;
	};
	frame.contentDocument = third.doc;
	frame.contentWindow = third.win;
	frame.dispatchEvent( { type : 'load' } );
	log.length = 0;
	marks.highlight( 'many' );
	check( 'a key that stands for four hundred elements is read in one go and then drawn: no measure is read after the first box was written', log.filter( function( entry ) { return entry === 'read' } ).length === 400 && log.indexOf( 'write' ) > log.lastIndexOf( 'read' ) );

	marks.stop();
	frame.dispatchEvent( { type : 'load' } );
	check( 'after stop() the marks and the listeners are gone and a load draws nothing', second.doc.body.children.length === 0 && ( fire( second.doc, 'pointerover', { target : second.doc.things.b[0] } ), hovered.join() === 'a' ) );
}

// The thing under the pointer, quietly: the option hover
{
	const first = page();
	const frame = element( 'iframe' );
	const hovered = [];

	frame.contentDocument = first.doc;
	frame.contentWindow = first.win;

	// A thing inside a thing of the same key: the innermost one has the pointer in it
	const outer = first.doc.things.a[0];
	const inner = element( 'div' );
	inner.key = 'a';
	inner.getBoundingClientRect = function() { return { top : 40, left : 30, width : 100, height : 20 } };
	outer.appendChild( inner );
	first.doc.things.a.push( inner );
	first.doc.things.c = [ outer ];

	const build = function( hover ) {
		return adminUi.frameMarks( {
			frame : frame,
			hover : hover,
			elements : function( key ) { return frame.contentDocument.things[key] ?? [] },
			keyAt : function( target ) { return target.key ?? null },
			onHover : function( key ) { hovered.push( key ) },
		} );
	};
	const layer = function( doc ) { return doc.body.children[doc.body.children.length - 1].shadow.children[1] };
	const boxes = function() { return layer( first.doc ).children.map( function( box ) { return box.className+ ':'+ box.style.top+ ' '+ box.style.left+ ' '+ box.style.width+ ' '+ box.style.height } ) };

	// Without the option the page is marked as it always was
	let marks = build( undefined );
	fire( first.doc, 'pointerover', { target : first.doc.things.b[0] } );
	check( 'without the option the thing under the pointer has no box, and is told as before', boxes().length === 0 && hovered.join() === 'b' );
	marks.stop();
	hovered.length = 0;

	marks = build( true );
	check( 'with the option nothing is drawn as long as the pointer is over nothing', boxes().length === 0 );

	fire( first.doc, 'pointerover', { target : inner } );
	check( 'the pointer over a thing draws a frame on the innermost element that stands for the key it is over, on exactly its box, as a box of its own kind - and tells the key as before',
		boxes().join() === 'hover:40px 30px 100px 20px' && hovered.join() === 'a' );

	fire( first.doc, 'pointerover', { target : first.doc.things.b[0] } );
	check( 'the frame goes to the next thing the pointer comes over', boxes().join() === 'hover:90px 10px 200px 50px' && hovered.join() === 'a,b' );

	fire( first.doc, 'pointerover', { target : first.doc } );
	check( 'a pointer over what is no thing takes it away', boxes().length === 0 );

	fire( first.doc, 'pointerover', { target : first.doc.things.b[0] } );
	first.doc.documentElement.dispatchEvent( { type : 'pointerleave' } );
	check( 'a pointer that leaves the page takes it away and tells null', boxes().length === 0 && hovered.slice( -1 )[0] === null );

	marks.highlight( 'b' );
	fire( first.doc, 'pointerover', { target : first.doc.things.b[0] } );
	check( 'where the thing is the marked one the solid box stays and no frame is drawn on it', boxes().join() === 'mark:90px 10px 200px 50px' );

	fire( first.doc, 'pointerover', { target : inner } );
	check( 'a marked thing and another under the pointer have both: the solid box first, the quiet frame after it', boxes().join( ' | ' ) === 'mark:90px 10px 200px 50px | hover:40px 30px 100px 20px' );

	// the page scrolls: the frame follows, like the boxes
	inner.getBoundingClientRect = function() { return { top : 5, left : 30, width : 100, height : 20 } };
	first.win.dispatchEvent( { type : 'scroll' } );
	first.win.frames.shift()();
	check( 'a page that scrolls has the frame drawn where the element is now', boxes()[1] === 'hover:5px 30px 100px 20px' );

	// the element is taken out of the page while the pointer is over it
	inner.removed = true;
	marks.refresh();
	check( 'an element that has left the page has no frame', boxes().length === 1 );
	inner.removed = false;

	// the colours are the workbench's, read when the page is marked: the ground inside, the ink outside
	const style = first.doc.body.children[first.doc.body.children.length - 1];
	check( 'the frame has two rings of the colours of the workbench - the ground of the workbench inside and its ink outside - and the host takes them from the tokens',
		first.doc.body.children[0].shadow.children[0].textContent.indexOf( 'border:1px solid var(--nino-hover-ink)' ) !== -1 && first.doc.body.children[0].shadow.children[0].textContent.indexOf( 'inset 0 0 0 1px var(--nino-hover-ground)' ) !== -1
		&& Object.prototype.hasOwnProperty.call( style.style, '--nino-hover-ink' ) === true && Object.prototype.hasOwnProperty.call( style.style, '--nino-hover-ground' ) === true );

	// a page the frame loads has no frame of the page that is gone
	const second = page();
	frame.contentDocument = second.doc;
	frame.contentWindow = second.win;
	frame.dispatchEvent( { type : 'load' } );
	check( 'a page the frame loads has no frame from the one before: the marks come back, the pointer is over nothing', layer( second.doc ).children.map( function( box ) { return box.className } ).join() === 'mark' );

	marks.stop();
	check( 'after stop() the page has no frame', second.doc.body.children.length === 0 );
}

{
	const w = build();
	w.split.frame = element( 'iframe' );
	w.split.frame.contentDocument = null;
	const marks = w.frame.marks( { elements : function() { return [] }, keyAt : function() { return null } } );
	check( 'previewFrame() gives the marks of the frame of its split: a page that is not there yet is no error, and one that comes is marked', typeof marks.highlight === 'function' && ( marks.highlight( 'a' ), marks.refresh(), marks.stop(), true ) );
}

done();
