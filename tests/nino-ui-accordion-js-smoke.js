/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-accordion-js-smoke.js	DOM-light checks for Nino.adminUi.accordion(),
 *											the sections that open one under another: the
 *											aria a head and its body carry, one section open or
 *											several, the keys that walk the heads, what a closed
 *											body keeps, and the address of the panel.
 *
 *	Usage: node tests/nino-ui-accordion-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, fills, source, documentOf } = require( './harness.js' );

const mount = element('div');
const sandbox = { console : console, document : documentOf( mount ), setTimeout : setTimeout, clearTimeout : clearTimeout, location : { host : 'localhost' } };
sandbox.window = sandbox;
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };

// The shell's router, as far as the accordion asks for it: what it was told, in order
const calls = [];
let current = { panel : '', parts : [] };

function withRouter() {
	sandbox.Nino.admin = { router : {
		go : function( panel, parts ) { calls.push( 'go '+ panel+ '/'+ parts.join('/') ) },
		set : function( panel, parts ) { calls.push( 'set '+ panel+ '/'+ parts.join('/') ) },
		current : function() { return current },
	} };
}

load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

function heads( acc ) {
	return findAll( acc.el, function( el ) { return el.tagName === 'BUTTON' && el.className.indexOf('nino-admin-accordion-button') !== -1 } );
}

function key( button, name ) {
	const event = { prevented : false };
	fire( button, 'keydown', { key : name, preventDefault : function() { event.prevented = true } } );
	return event;
}

function build( options ) {
	return adminUi.accordion( Object.assign( {
		sections : [
			{ key : 'one', label : 'One', content : element('p') },
			{ key : 'two', label : 'Two', content : element('p') },
			{ key : 'three', label : 'Three', content : element('p') },
		],
	}, options || {} ) );
}

console.log( 'Nino.adminUi.accordion - sections that open one under another\n' );

// --- the aria ----------------------------------------------------------------

const acc = build( { open : 'two' } );
const buttons = heads( acc );
const bodies = acc.el.children.filter( function( el ) { return el.className === 'nino-admin-accordion-body' } );

check( 'a head is a button inside a heading', buttons.length === 3 && buttons.every( function( button ) { return button.parentNode.tagName === 'H3' && button.type === 'button' } ) );
check( 'aria-expanded says which section is open', buttons.map( function( button ) { return button.getAttribute('aria-expanded') } ).join() === 'false,true,false' );
check( 'aria-controls names the body of the head, and the body is a region labelled by it',
	buttons.every( function( button, at ) { return button.getAttribute('aria-controls') === bodies[at].id && bodies[at].getAttribute('role') === 'region' && bodies[at].getAttribute('aria-labelledby') === button.id } ) );
check( 'a closed body is hidden and the open one is not', bodies.map( function( body ) { return body.hidden } ).join() === 'true,false,true' );
check( 'the level of the heads is the caller\'s', build( { level : 4 } ).el.children[0].tagName === 'H4' );
check( 'a level that is no heading is not made one', build( { level : 9 } ).el.children[0].tagName === 'H3' );

const other = build();
const ids = heads( acc ).concat( heads( other ) ).map( function( button ) { return button.id } );
check( 'two accordions of one page share no id', new Set( ids ).size === ids.length );

// --- one section open ---------------------------------------------------------

check( 'the open head is aria-disabled and the closed ones have no such attribute',
	buttons[1].getAttribute('aria-disabled') === 'true' && buttons[0].hasAttribute('aria-disabled') === false );

const toggled = [];
const single = build( { open : 'one', onToggle : function( open, pressed ) { toggled.push( open.join()+ '|'+ pressed ) } } );
const singleHeads = heads( single );

fire( singleHeads[2], 'click' );
check( 'pressing a closed head opens it and closes the other', single.get().join() === 'three' && singleHeads[0].getAttribute('aria-expanded') === 'false' );
fire( singleHeads[2], 'click' );
check( 'pressing the open head changes nothing and says nothing', single.get().join() === 'three' && toggled.length === 1 );
check( 'onToggle is told the open keys and the key that was pressed', toggled[0] === 'three|three' );
check( 'the summary of a head shows while it is closed and goes while it is open',
	( function() {
		const s = build( { open : 'one', sections : [ { key : 'one', label : 'One', summary : 'sum' }, { key : 'two', label : 'Two', summary : 'sum' } ] } );
		const summaries = findAll( s.el, function( el ) { return el.className === 'nino-admin-accordion-summary' } );
		return summaries[0].hidden === true && summaries[1].hidden === false;
	} )() );
check( 'a key the accordion has not is no section: the first is open, where one has to be', build( { open : 'nine' } ).get().join() === 'one' );
check( 'an accordion told nothing opens its first section', build().get().join() === 'one' && build( { open : [] } ).get().join() === 'one' );
check( '...and an empty one has none to open', adminUi.accordion( { sections : [] } ).get().length === 0 );
check( 'several keys given to a single accordion open the first of them', build( { open : [ 'three', 'one' ] } ).get().join() === 'three' );

// --- several open ---------------------------------------------------------------

const many = build( { multiple : true, open : [ 'one' ] } );
const manyHeads = heads( many );
fire( manyHeads[2], 'click' );
check( 'with several allowed a press opens a section and leaves the others', many.get().join() === 'one,three' );
fire( manyHeads[0], 'click' );
check( '...and a press on an open one closes it', many.get().join() === 'three' );
fire( manyHeads[2], 'click' );
check( '...down to none', many.get().length === 0 );
check( 'no head is aria-disabled where several may be open', manyHeads.every( function( button ) { return button.hasAttribute('aria-disabled') === false } ) );

// --- one section open, and it can be closed ----------------------------------------

const folded = [];
const fold = build( { collapsible : true, open : 'two', onToggle : function( open, pressed ) { folded.push( open.join()+ '|'+ pressed ) } } );
const foldHeads = heads( fold );

check( 'a collapsible accordion has its open section open and no head aria-disabled', fold.get().join() === 'two' && foldHeads.every( function( button ) { return button.hasAttribute('aria-disabled') === false } ) );
fire( foldHeads[2], 'click' );
check( '...a press on a closed head opens it and closes the other: at most one is open', fold.get().join() === 'three' && foldHeads[1].getAttribute('aria-expanded') === 'false' );
fire( foldHeads[2], 'click' );
check( '...a press on the open head closes it, and no section is open', fold.get().length === 0 && foldHeads.every( function( button ) { return button.getAttribute('aria-expanded') === 'false' } ) );
check( '...with its body hidden again and told to onToggle with no key open', fold.body('three').hidden === true && folded.join() === 'three|three,|three' );
check( '...and a press on a head opens it again', ( function() { fire( foldHeads[0], 'click' ); return fold.get().join() === 'one' } )() );
check( 'a collapsible accordion told nothing starts with none open', build( { collapsible : true } ).get().length === 0 && build( { collapsible : true, open : [] } ).get().length === 0 );
check( '...and several keys given to it open the first of them', build( { collapsible : true, open : [ 'three', 'one' ] } ).get().join() === 'three' );
check( '...and a key it has not opens none', build( { collapsible : true, open : 'nine' } ).get().length === 0 );
check( 'collapsible with multiple is the several of multiple', build( { collapsible : true, multiple : true, open : [ 'one', 'two' ] } ).get().join() === 'one,two' );
check( 'collapsible is a switch and nothing else is one', build( { collapsible : 'yes' } ).get().join() === 'one' && build( { collapsible : false } ).get().join() === 'one' );

const foldKeys = build( { collapsible : true, open : 'one' } );
const foldKeyHeads = heads( foldKeys );
foldKeyHeads[0].focus();
fire( foldKeyHeads[0], 'click' );
check( 'Enter and Space are a press of the head (the browser clicks a button for them), and the arrows still walk the heads', ( function() {
	const walked = key( foldKeyHeads[0], 'ArrowDown' );
	return foldKeys.get().length === 0 && foldKeyHeads[1].focused === true && walked.prevented === true;
} )() );

// --- the keyboard -----------------------------------------------------------------

const keyed = build( { open : 'one' } );
const keyedHeads = heads( keyed );
keyedHeads[0].focus();

let event = key( keyedHeads[0], 'ArrowDown' );
check( 'Down moves to the next head and is kept from scrolling the page', keyedHeads[1].focused === true && event.prevented === true );
key( keyedHeads[1], 'ArrowDown' );
key( keyedHeads[2], 'ArrowDown' );
check( 'Down on the last head stays on it', keyedHeads[2].focused === true );
key( keyedHeads[2], 'ArrowUp' );
check( 'Up moves to the previous head', keyedHeads[1].focused === true );
key( keyedHeads[1], 'ArrowUp' );
key( keyedHeads[0], 'ArrowUp' );
check( 'Up on the first head stays on it', keyedHeads[0].focused === true );
key( keyedHeads[0], 'End' );
check( 'End goes to the last head', keyedHeads[2].focused === true );
key( keyedHeads[2], 'Home' );
check( 'Home goes to the first head', keyedHeads[0].focused === true );
check( 'moving the focus opens nothing', keyed.get().join() === 'one' );

event = key( keyedHeads[0], 'a' );
check( 'another key is left to the browser', event.prevented === false && keyedHeads[0].focused === true );

// An accordion inside a section of another: the arrows stay in the heads of their own
const innerAcc = build( { level : 4, open : 'one' } );
const outerAcc = build( { open : 'one', sections : [ { key : 'one', label : 'One', content : innerAcc.el }, { key : 'two', label : 'Two' } ] } );
const innerHeads = heads( innerAcc );
innerHeads[2].focus();
key( innerHeads[2], 'ArrowDown' );
check( 'the arrows of an inner accordion do not leave it', innerHeads[2].focused === true && heads( outerAcc )[1].focused !== true );
check( 'the outer one walks its own two heads, not the inner ones',
	( function() { const outerOnly = heads( outerAcc ).filter( function( button ) { return button.parentNode.parentNode === outerAcc.el } ); key( outerOnly[0], 'ArrowDown' ); return outerOnly[1].focused === true } )() );

// --- what a closed body keeps ------------------------------------------------------

let made = 0;
const field = element('input');
const lazy = build( { sections : [ { key : 'a', label : 'A', content : function() { made++; return field } }, { key : 'b', label : 'B', content : element('p') } ], open : 'b' } );
check( 'a body given as a function is not made while its section is closed', made === 0 );
fire( heads( lazy )[0], 'click' );
check( '...and is made when it opens', made === 1 && lazy.body('a').children[0] === field );
field.value = 'typed';
fire( heads( lazy )[1], 'click' );
fire( heads( lazy )[0], 'click' );
check( '...once: it is hidden, not removed, so what was typed in it is there when it is opened a second time',
	made === 1 && lazy.body('a').children[0] === field && field.value === 'typed' );

// --- the address -------------------------------------------------------------------

withRouter();
const addressed = build( { address : { panel : 'themes', parts : [ 'craft' ] }, open : 'one' } );
const addressedHeads = heads( addressed );

fire( addressedHeads[1], 'click' );
check( 'a press is a move of the person - a new entry - and then the address says it',
	calls.join() === 'go themes/craft/two,set themes/craft/two' );

calls.length = 0;
addressed.set( 'three' );
check( 'set() moves the sections and writes no address', addressed.get().join() === 'three' && calls.length === 0 );

const addressedMany = build( { multiple : true, address : { panel : 'themes', parts : [ 'craft' ] } } );
fire( heads( addressedMany )[0], 'click' );
fire( heads( addressedMany )[2], 'click' );
check( 'several open keys are one part of the address, joined by a comma', calls[calls.length - 1] === 'set themes/craft/one,three' );
fire( heads( addressedMany )[0], 'click' );
fire( heads( addressedMany )[2], 'click' );
check( 'no section open is the address of the level alone', calls[calls.length - 1] === 'set themes/craft' );

current = { panel : 'themes', parts : [ 'craft', 'two' ] };
calls.length = 0;
addressed.follow();
check( 'follow() opens what the address of its own level names, and writes nothing', addressed.get().join() === 'two' && calls.length === 0 );

current = { panel : 'themes', parts : [ 'craft', 'nine' ] };
addressed.follow();
check( '...a key it has not opens the one it started with', addressed.get().join() === 'one' );

current = { panel : 'themes', parts : [ 'craft' ] };
addressed.set( 'one' );
addressed.follow();
check( '...and so does the address of the level alone: Back to the level leaves one open', addressed.get().join() === 'one' );

const foldAddress = { panel : 'themes', parts : [ 'craft' ] };
const addressedFold = build( { collapsible : true, address : foldAddress, open : 'one' } );
calls.length = 0;
fire( heads( addressedFold )[0], 'click' );
check( 'a collapsible accordion that closes its section writes the address of the level alone', addressedFold.get().length === 0 && calls.join() === 'go themes/craft,set themes/craft' );
current = { panel : 'themes', parts : [ 'craft', 'two' ] };
addressedFold.follow();
check( '...follow() opens the section the address names', addressedFold.get().join() === 'two' );
current = { panel : 'themes', parts : [ 'craft' ] };
addressedFold.follow();
check( '...and the address of the level alone closes them all, where any other has one open', addressedFold.get().length === 0 && heads( addressedFold ).every( function( button ) { return button.getAttribute('aria-expanded') === 'false' } ) );
current = { panel : 'themes', parts : [ 'craft', 'nine' ] };
addressedFold.set( 'one' );
addressedFold.follow();
check( '...and so does a key it has not', addressedFold.get().length === 0 );
addressedFold.set( 'two' );
check( 'set() opens a section of it, and set( [] ) closes it', addressedFold.get().join() === 'two' && ( function() { addressedFold.set( [] ); return addressedFold.get().length === 0 } )() );

const started = build( { address : { panel : 'themes', parts : [ 'craft' ] }, open : 'two' } );
started.set( 'three' );
current = { panel : 'themes', parts : [ 'craft' ] };
started.follow();
check( '...and the one it started with is the one it goes back to, not the first', started.get().join() === 'two' );
current = { panel : 'themes', parts : [ 'craft', 'nine' ] };
started.set( 'three' );
started.follow();
check( '...by an unknown key as well', started.get().join() === 'two' );

const unstarted = build( { address : { panel : 'themes', parts : [ 'craft' ] } } );
unstarted.set( 'three' );
unstarted.follow();
check( '...and with none it started with, the first', unstarted.get().join() === 'one' );
unstarted.set( 'nine' );
check( 'set() settles the same way', unstarted.get().join() === 'one' );

current = { panel : 'themes', parts : [ 'other', 'two' ] };
addressed.set( 'one' );
addressed.follow();
check( 'an address of another level is left alone', addressed.get().join() === 'one' );

current = { panel : 'text', parts : [ 'craft', 'two' ] };
addressed.follow();
check( '...and so is one of another panel', addressed.get().join() === 'one' );

current = { panel : 'themes', parts : [ 'craft', 'one,three' ] };
addressedMany.follow();
check( 'a comma in the address opens several, where several may open', addressedMany.get().join() === 'one,three' );

current = { panel : 'themes', parts : [ 'craft', 'one,three' ] };
addressed.follow();
check( '...and the first of them where one may', addressed.get().join() === 'one' );

delete sandbox.Nino.admin;
const loose = build( { address : { panel : 'themes', parts : [] }, open : 'one' } );
fire( heads( loose )[1], 'click' );
loose.follow();
check( 'without the shell\'s router the address is left alone and nothing throws', loose.get().join() === 'two' );

// --- what is put in --------------------------------------------------------------------

const hostile = '<img src=x onerror=alert(1)>';
const odd = build( { sections : [ { key : '"><x a="', label : hostile, summary : hostile, badge : hostile }, { key : 'a b', label : 'B' } ], open : '"><x a="' } );
const oddHeads = heads( odd );
check( 'a label, a summary and a badge are text, whatever they hold',
	findAll( odd.el, function( el ) { return el.textContent === hostile } ).length === 3 && findAll( odd.el, function( el ) { return el.tagName === 'IMG' } ).length === 0 );
check( 'a key is never in an id or an attribute', oddHeads.every( function( button ) { return /^[\w-]+$/.test( button.id ) && /^[\w-]+$/.test( button.getAttribute('aria-controls') ) } ) );
check( 'a key of any characters opens its section', odd.get().join() === '"><x a="' && odd.head('a b') === oddHeads[1] );

odd.update( '"><x a="', { summary : 'new', badge : '' } );
const badges = findAll( odd.el, function( el ) { return el.className.indexOf('nino-admin-accordion-badge') !== -1 } );
check( 'update() sets a summary and takes a badge away', badges[0].hidden === true && findAll( odd.el, function( el ) { return el.textContent === 'new' } ).length === 1 );
check( 'update() of a key the accordion has not does nothing', ( function() { odd.update( 'nine', { summary : 'x' } ); return odd.head('nine') === null && odd.body('nine') === null } )() );

// --- the recipe's examples --------------------------------------------------------------

// The four examples of the recipe's section on settings screens, each run on its own in a panel as it stands
const recipe = source( 'docs/recipes/admin-panel.md' );
const section = recipe.slice( recipe.indexOf('### Settings screens'), recipe.indexOf('### Three traps') );
const examples = [ ...section.matchAll( /```js\n([\s\S]*?)```/g ) ].map( function( match ) { return match[1] } );
const wanted = [ 'nino-admin-accordion', 'nino-admin-steps', 'nino-admin-split', 'nino-admin-color' ];

check( 'the recipe has an example for each of the four blocks', examples.length === 4 );

examples.forEach( function( code, at ) {

	const panel = element('div');
	const page = { console : console, document : documentOf( panel ), mount : panel, location : { host : 'localhost' }, setTimeout : setTimeout, clearTimeout : clearTimeout };
	page.window = page;
	page.Nino = sandbox.Nino;

	let failed = null;
	try {
		vm.runInContext( code, vm.createContext( page ) );
	} catch( error ) {
		failed = error.message;
	}

	check( 'example '+ ( at + 1 ) + ' runs on its own and draws its block' + ( failed === null ? '' : ': '+ failed ),
		failed === null && findAll( panel, function( el ) { return String( el.className ).split(' ').indexOf( wanted[at] ) !== -1 } ).length > 0 );
} );

done();
