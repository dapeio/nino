/**
 *	Nino									A compact filesystembased php framework
 *	nino-ui-helpers-js-smoke.js	The three helpers of Nino.ui that a feature's site
 *												script calls: reducedMotion(), options() and
 *												whenVisible(). Each one stands in for a copy that two
 *												or more features carried, so what is pinned is what
 *												the copies agreed on and what the kernel's rule for
 *												a flag changed.
 *
 *	Usage: node tests/nino-ui-helpers-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, source } = require( './harness.js' );

/**
 *	Nino.ui.js in a page of its own, with whatever the browser has - or lacks -
 *	given by the test
 *
 *	@param		{Object}	[extra]		Globals of the page: matchMedia, IntersectionObserver
 *
 *	@return		{Object}						Nino.ui
 */
function world( extra ) {

	const sandbox = Object.assign( {
		console : console,
		document : { body : null, documentElement : null },
	}, extra || {} );

	sandbox.window = sandbox;
	sandbox.Nino = { client : { isMobile : false }, events : { bindCallback : function() {} } };

	load( '_nino/Nino.ui.js', vm.createContext( sandbox ) );

	return sandbox.Nino.ui;
}

/**
 *	An element that has attributes and children it can be asked for by selector,
 *	and nothing else: no dataset, which an option must not be read from
 *
 *	@param		{Object}		attributes		name => value
 *	@param		{Object[]}	[children]		What querySelectorAll() finds for '.item'
 *
 *	@return		{Object}
 */
function holder( attributes, children ) {
	return {
		getAttribute : function( name ) { return name in attributes ? attributes[name] : null },
		querySelectorAll : function( selector ) {
			if( selector.indexOf('[[') !== -1 )
				throw new SyntaxError( 'not a selector' );
			return selector === '.item' ? ( children || [] ) : [];
		},
	};
}


// --- reducedMotion ---------------------------------------------------------

console.log( 'Nino.ui.reducedMotion - what the visitor asked their system for\n' );

check( 'a browser that cannot be asked is not asking for less motion', world().reducedMotion() === false );

let reduce = true;
const motion = world( { matchMedia : function( query ) { return { matches : query === '(prefers-reduced-motion: reduce)' && reduce } } } );
check( 'a visitor who asked for less motion is told so', motion.reducedMotion() === true );
reduce = false;
check( '...and the setting is asked each time, not read once: it can change while the page is open', motion.reducedMotion() === false );
check( '...a browser that matches nothing is not asking for less motion', world( { matchMedia : function() { return { matches : false } } } ).reducedMotion() === false );


// --- options ---------------------------------------------------------------

console.log( '\nNino.ui.options - the data attributes of an element, read against a schema\n' );

const ui = world();

const text = ui.options( holder( { 'data-x-label' : 'Hello', 'data-x-empty' : '', 'data-x-void' : '' } ), 'x', {
	label : { type : 'text', fallback : 'none' },
	missing : { type : 'text', fallback : 'none' },
	empty : { type : 'text', fallback : 'none' },
	void : { type : 'text', fallback : '|', empty : true },
} );
check( 'text: the attribute as it is', text.label === 'Hello' );
check( '...a missing attribute and an empty one give the fallback', text.missing === 'none' && text.empty === 'none' );
check( '...unless the empty string is a value of that setting', text.void === '' );

const ints = ui.options( holder( { 'data-x-a' : '5', 'data-x-b' : '0', 'data-x-c' : '-1', 'data-x-d' : 'soon', 'data-x-e' : '' } ), 'x', {
	a : { type : 'int', fallback : 9 }, b : { type : 'int', fallback : 9 }, c : { type : 'int', fallback : 9 },
	d : { type : 'int', fallback : 9 }, e : { type : 'int', fallback : 9 }, f : { type : 'int', fallback : 9 },
} );
check( 'int: a whole number, 0 allowed', ints.a === 5 && ints.b === 0 );
check( '...below 0, no number, no value and no attribute give the fallback', ints.c === 9 && ints.d === 9 && ints.e === 9 && ints.f === 9 );

const numbers = ui.options( holder( { 'data-x-a' : '1.5', 'data-x-b' : '0', 'data-x-c' : '-2', 'data-x-d' : 'fast', 'data-x-e' : '40' } ), 'x', {
	a : { type : 'number', fallback : 3 }, b : { type : 'number', fallback : 3 }, c : { type : 'number', fallback : 3 },
	d : { type : 'number', fallback : 3 }, e : { type : 'number', fallback : 3 }, f : { type : 'number', fallback : 3 },
} );
check( 'number: a number above 0, a fraction too', numbers.a === 1.5 && numbers.e === 40 );
check( '...0, below 0, no number and no attribute give the fallback', numbers.b === 3 && numbers.c === 3 && numbers.d === 3 && numbers.f === 3 );

// The words of a flag are the kernel's: the ones a component's bool attribute is read by.
// They are read from \Nino\Modules\Components, so that a word added there and not
// here is red; the words after them are the same ones in the shapes a person writes
const listed = function( name ) {
	const found = new RegExp( 'public const array '+ name+ ' = \\[([^\\]]*)\\];' ).exec( source( '_nino/Nino/Modules/Components/Components.php' ) );
	return found === null ? [] : Array.from( found[1].matchAll( /'([^']+)'/g ) ).map( function( word ) { return word[1] } );
};
const on = listed( 'ON' ).concat( [ 'TRUE', 'Yes', '  on  ', ' 1' ] );
const off = listed( 'OFF' ).concat( [ 'FALSE', 'No', '  off ', '0 ' ] );
const flagged = function( word, fallback ) {
	return ui.options( holder( word === null ? {} : { 'data-x-f' : word } ), 'x', { f : { type : 'flag', fallback : fallback } } ).f;
};
check( 'flag: every word of the on list is true, whatever its case and the space around it - with either fallback', listed( 'ON' ).length > 0 && on.every( function( word ) { return flagged( word, false ) === true && flagged( word, true ) === true } ) );
check( '...and every word of the off list is false', listed( 'OFF' ).length > 0 && off.every( function( word ) { return flagged( word, true ) === false && flagged( word, false ) === false } ) );
check( '...any other word is no answer: the fallback, which is what changed from "anything not off is on"', flagged( 'loop', false ) === false && flagged( 'loop', true ) === true && flagged( 'enabled', true ) === true && flagged( 'ja', false ) === false );
check( '...no word at all, an empty one and a blank one give the fallback too', flagged( null, true ) === true && flagged( '', true ) === true && flagged( '   ', false ) === false && flagged( '', false ) === false );

const enums = ui.options( holder( { 'data-x-a' : 'load', 'data-x-b' : 'later', 'data-x-c' : '' } ), 'x', {
	a : { type : 'enum', values : [ 'load', 'view' ], fallback : 'view' }, b : { type : 'enum', values : [ 'load', 'view' ], fallback : 'view' },
	c : { type : 'enum', values : [ 'load', 'view' ], fallback : 'view' }, d : { type : 'enum', values : [ 'load', 'view' ], fallback : 'view' },
} );
check( 'enum: the attribute when it is one of the values', enums.a === 'load' );
check( '...any other value, an empty one and no attribute give the fallback', enums.b === 'view' && enums.c === 'view' && enums.d === 'view' );

const items = [ element('li'), element('li') ];
const picked = ui.options( holder( { 'data-x-lines' : '.item', 'data-x-bad' : '[[', 'data-x-none' : '' }, items ), 'x', {
	lines : { type : 'selector', fallback : '.other' }, bad : { type : 'selector', fallback : '.item' },
	none : { type : 'selector', fallback : '.item' }, absent : { type : 'selector', fallback : '.item' },
	loose : { type : 'selector', fallback : null }, broken : { type : 'selector', fallback : '[[' },
} );
check( 'selector: the elements the attribute\'s selector finds, as an array', Array.isArray( picked.lines ) === true && picked.lines.length === 2 && picked.lines[0] === items[0] && picked.lines[1] === items[1] );
check( '...a selector the browser refuses gives the elements of the fallback, which is a selector too', picked.bad.length === 2 && picked.bad[1] === items[1] );
check( '...so do an empty attribute and none', picked.none.length === 2 && picked.absent.length === 2 );
check( '...a fallback that is no selector gives no elements, and so does one the browser refuses', Array.isArray( picked.loose ) === true && picked.loose.length === 0 && Array.isArray( picked.broken ) === true && picked.broken.length === 0 );

check( 'the answer has every key of the schema, whatever the markup holds', Object.keys( picked ).join() === 'lines,bad,none,absent,loose,broken' && Object.keys( ints ).join() === 'a,b,c,d,e,f' );

const hyphen = ui.options( holder( { 'data-my-feature-speed' : '7' } ), 'my-feature', { speed : { type : 'int', fallback : 1 } } );
check( 'a prefix with a hyphen in it is read the way the markup has it: the element is only asked for the attribute', hyphen.speed === 7 );

// The harness's own element, for the case a real one is asked: its dataset camel-cases a data- name
const real = element('div');
real.setAttribute( 'data-my-feature-speed', '9' );
check( '...on an element with a dataset too', ui.options( real, 'my-feature', { speed : { type : 'int', fallback : 1 } } ).speed === 9 );


// --- whenVisible -----------------------------------------------------------

console.log( '\nNino.ui.whenVisible - once, when the element has been in the viewport\n' );

const observers = [];
function Observer( callback ) {
	this.callback = callback;
	this.observed = [];
	this.disconnected = 0;
	observers.push( this );
}
Observer.prototype.observe = function( el ) { this.observed.push( el ) };
Observer.prototype.disconnect = function() { this.disconnected++ };

const watching = world( { IntersectionObserver : Observer } );
const target = element('div');
let calls = 0;
let disconnectedWhenCalled = -1;
watching.whenVisible( target, function() { calls++; disconnectedWhenCalled = observers[0].disconnected } );

check( 'one observer watches the element, and nothing is called back yet', observers.length === 1 && observers[0].observed.length === 1 && observers[0].observed[0] === target && calls === 0 );

observers[0].callback( [ { isIntersecting : false } ] );
check( '...an entry that does not intersect changes nothing', calls === 0 && observers[0].disconnected === 0 );

observers[0].callback( [ { isIntersecting : false }, { isIntersecting : true }, { isIntersecting : true } ] );
check( '...the first that does lets go of the element and then calls back, once', calls === 1 && disconnectedWhenCalled === 1 && observers[0].disconnected === 1 );

let immediate = 0;
world().whenVisible( target, function() { immediate++ } );
check( 'without IntersectionObserver there is nothing to wait for: the callback runs at once', immediate === 1 );

done();
