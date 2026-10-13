/**
 *	Nino
 *	harness.js		The shared half of the JavaScript smoke tests: check() and
 *								the summary line, the readers of a source file and of the
 *								fills of a text file, and the element stand-in the panel
 *								suites draw their screens in. Every *-js-smoke.js loads
 *								this instead of carrying the same few lines itself;
 *								harness.php is its PHP counterpart.
 *
 *								It is not a test: the CI loop runs tests/*-js-smoke.js,
 *								and this file does not match that.
 *
 *								A suite takes what it uses, check()s away and ends with
 *								done():
 *
 *									const { check, done, element } = require( './harness.js' );
 *
 *									check( label, condition )			One assertion, one line of output
 *									done()								The summary line, and the exit status
 *									source( relative )					The text of a file of the checkout
 *									fills( relative )					The [[/key]] => 'value' lines of a text file
 *									load( relative, context, filename )	A script of the checkout, run in a context
 *									classList( el, initial )			A class list over a Set, one with className
 *									element( tag )						An element stand-in: tree, listeners, matcher
 *									findAll( root, predicate )			Every descendant the predicate accepts
 *									fire( el, name, event )				Calls the listeners of an event
 *									matches( el, selector )				Whether an element answers a selector
 *									documentOf( mount )					A document stand-in over one mount element
 *
 *	Usage: require it from tests/<name>-js-smoke.js and run that with node.
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const started = Date.now();

let checks = 0;
let failures = 0;

// The element that has the focus: one in the whole suite, as a page has one -
// a suite that builds several pages shares it, so a focus() in one page takes
// the focus from an element of another
let inFocus = null;


// --- the verdict -----------------------------------------------------------

/**
 *	One assertion, one line of output. Only true passes: a truthy value that
 *	is no boolean is a condition written wrongly, not a pass
 *
 *	@param		{string}	label
 *	@param		{boolean}	condition
 *
 *	@return		{void}
 */
function check( label, condition ) {
	checks++;
	if( condition === true ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

/**
 *	Print the summary and set the exit status: 0 when every check passed. The
 *	status is set and not exited with, so that what the suite still has queued
 *	runs; a suite whose script leaves a timer behind that it must not wait for
 *	ends with process.exit() after this
 *
 *	@return		{void}
 */
function done() {
	const seconds = ( ( Date.now() - started ) / 1000 ).toFixed( 1 );
	console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed, '+ seconds+ 's' );
	process.exitCode = failures === 0 ? 0 : 1;
}


// --- the files -------------------------------------------------------------

/**
 *	The text of a file of the checkout, by its path from the checkout root:
 *	the directory above tests/, where this file is, wherever the suite is
 *	run from. A file that is not there throws
 *
 *	@param		{string}	relative		A path from the checkout root, eg. '_admin/assets/Nino.admin.js'
 *
 *	@return		{string}							Its text
 */
function source( relative ) {
	return fs.readFileSync( path.join( __dirname, '..', relative ), 'utf8' );
}

/**
 *	The fills of one text file, key => value - enough of the php to read
 *	a `'[[/key]]' => 'value',` line, with the two escapes a single-quoted
 *	string has, \' and \\
 *
 *	@param		{string}	relative		A path from the checkout root
 *
 *	@return		{Object}
 */
function fills( relative ) {
	const map = {};
	const re = /'\[\[(\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g;
	const text = source( relative );
	let match;
	while( ( match = re.exec( text ) ) )
		map[match[1]] = match[2].replace( /\\(['\\])/g, '$1' );
	return map;
}

/**
 *	Run a script of the checkout in a context, the way a browser runs it
 *
 *	@param		{string}	relative		A path from the checkout root
 *	@param		{Object}	context			A vm.createContext() context
 *	@param		{string}	filename		What a stack trace calls it, the file's name by default
 *
 *	@return		{*}
 */
function load( relative, context, filename ) {
	return vm.runInContext( source( relative ), context, { filename : filename || path.basename( relative ) } );
}


// --- an element stand-in: enough tree to find things in --------------------

/**
 *	The class list of an element, over a Set. Given an element it is a view of
 *	its className, so a class assigned there and a class added here are one
 *	list, as in a page, and every change is written back to className; without
 *	one it holds the classes itself (a body, a card). has() is the Set's own
 *	name for contains()
 *
 *	@param		{Object|null}	el				The element it belongs to
 *	@param		{string[]}		initial		The classes it starts with
 *
 *	@return		{Object}
 */
function classList( el, initial ) {

	const held = new Set( initial || [] );

	if( el && initial )
		el.className = [ ...held ].join(' ');

	const values = function() { return el ? new Set( String( el.className ).split(/\s+/).filter( Boolean ) ) : held };
	const write = function( set ) { if( el ) el.className = [ ...set ].join(' ') };

	const list = {
		add : function( value ) { const set = values(); set.add( value ); write( set ) },
		remove : function( value ) { const set = values(); set.delete( value ); write( set ) },
		contains : function( value ) { return values().has( value ) },
		toggle : function( value, force ) {
			const set = values();
			if( force === undefined ? set.has( value ) === false : Boolean( force ) )
				set.add( value );
			else
				set.delete( value );
			write( set );
			return set.has( value );
		},
	};
	list.has = list.contains;

	return list;
}

/**
 *	@param		{string}	name				A data-* attribute, eg. 'data-original-key'
 *
 *	@return		{string}							The key of its dataset entry, 'originalKey'
 */
function datasetKey( name ) {
	return name.slice( 5 ).replace( /-([a-z])/g, function( match, letter ) { return letter.toUpperCase() } );
}

/**
 *	Whether an element answers a selector: a tag, #id, .class (several), [name]
 *	or [name="value"] - alone or one after the other, as in input.wide[name="x"] -
 *	and a comma list of those. A data-* attribute is read from the dataset as
 *	well. No combinators: a suite that needs a descendant selector asks with
 *	findAll() or keeps a matcher of its own. Whatever else a selector holds - a
 *	combinator, :not(), a value without quotes - makes its part match nothing,
 *	without a word: a query that finds nothing may be one the stand-in cannot read
 *
 *	@param		{Object}	el
 *	@param		{string}	selector
 *
 *	@return		{boolean}
 */
function matches( el, selector ) {
	return selector.split(',').some( function( part ) {
		part = part.trim();
		const token = /([a-z][a-z0-9-]*|\*)|#([\w-]+)|\.([\w-]+)|\[([\w-]+)(?:="([^"]*)")?\]/iy;
		let at = 0;
		while( at < part.length ) {
			token.lastIndex = at;
			const found = token.exec( part );
			if( found === null )
				return false;
			at = token.lastIndex;
			if( found[1] !== undefined && found[1] !== '*' && el.tagName !== found[1].toUpperCase() )
				return false;
			if( found[2] !== undefined && el.id !== found[2] )
				return false;
			if( found[3] !== undefined && String( el.className ).split(/\s+/).indexOf( found[3] ) === -1 )
				return false;
			if( found[4] !== undefined ) {
				const own = found[4] in el.attributes ? el.attributes[found[4]] : undefined;
				const data = found[4].indexOf('data-') === 0 ? el.dataset[datasetKey( found[4] )] : undefined;
				const value = own !== undefined ? own : data;
				if( value === undefined || ( found[5] !== undefined && value !== found[5] ) )
					return false;
			}
		}
		return part !== '';
	} );
}

/**
 *	Every descendant of an element stand-in that a predicate accepts: the
 *	tree walked through children, depth first. The root itself is not asked
 *
 *	@param		{Object}		root
 *	@param		{Function}	predicate		Called with each descendant; true keeps it
 *
 *	@return		{Object[]}								The descendants it kept, depth first
 */
function findAll( root, predicate ) {
	const found = [];
	( function walk( node ) {
		( node.children || [] ).forEach( function( child ) {
			if( predicate( child ) === true )
				found.push( child );
			walk( child );
		} );
	} )( root );
	return found;
}

/**
 *	Call the listeners an element holds for an event, the way a page would:
 *	each one with the element as `this` and an event that names its type and
 *	its target and can be prevented and stopped, plus whatever `event` adds -
 *	a key, or a target further down. Tolerates a control that is not on
 *	screen, so that a panel that threw its own buttons away reports as failed
 *	checks rather than as a crashed test
 *
 *	@param		{Object|null}	el
 *	@param		{string}			name			The event, eg. 'click'
 *	@param		{Object}			event
 *
 *	@return		{void}
 */
function fire( el, name, event ) {
	if( el === null || el === undefined )
		return;
	( el.listeners[name] || [] ).slice().forEach( function( fn ) {
		fn.call( el, Object.assign( { type : name, preventDefault : function() {}, stopPropagation : function() {}, target : el }, event ) );
	} );
}

/**
 *	A listener that takes itself off once it has run
 *
 *	@param		{Object}		el
 *	@param		{string}		name
 *	@param		{Function}	fn
 *
 *	@return		{Function}
 */
function once( el, name, fn ) {
	const wrapper = function( event ) {
		el.removeEventListener( name, wrapper );
		return fn.call( this, event );
	};
	wrapper.listener = fn;
	return wrapper;
}

/**
 *	What is an attribute and a property in one: id, class and a data-* entry.
 *	Null takes it off again
 *
 *	@param		{Object}				el
 *	@param		{string}				name			The attribute
 *	@param		{string|null}		value
 *
 *	@return		{void}
 */
function reflect( el, name, value ) {
	if( name === 'id' )
		el.id = value ?? '';
	else if( name === 'class' )
		el.className = value ?? '';
	else if( name.indexOf('data-') === 0 && value === null )
		delete el.dataset[datasetKey( name )];
	else if( name.indexOf('data-') === 0 )
		el.dataset[datasetKey( name )] = value;
}

/**
 *	One element: what a panel script reads and writes of one, a tree to put it
 *	in and a matcher to find it again. A text node is one of these too -
 *	element('#text')
 *
 *	@param		{string}	tag
 *
 *	@return		{Object}
 */
function element( tag ) {

	const el = {
		tagName : String( tag ).toUpperCase(),
		className : '',
		textContent : '',
		type : '',
		id : '',
		href : '',
		title : '',
		placeholder : '',
		src : '',
		maxLength : -1,
		rows : 0,
		checked : false,
		selected : false,
		disabled : false,
		hidden : false,
		tabIndex : 0,
		focused : false,
		selectionStart : 0,
		selectionEnd : 0,
		// Nothing overflows in a document that has no layout, so a script that asks
		// whether a box does - the "expand" hint of a card - is told no, which is
		// the honest answer for a stand-in
		scrollHeight : 0,
		clientHeight : 0,
		style : {},
		dataset : {},
		attributes : {},
		children : [],
		listeners : {},
		// The options the last listener of each event was added with
		listenerOptions : {},
		parentNode : null,
		appendChild : function( child ) { child.parentNode = el; el.children.push( child ); return child },
		removeChild : function( child ) {
			const at = el.children.indexOf( child );
			if( at !== -1 )
				el.children.splice( at, 1 );
			child.parentNode = null;
			return child;
		},
		insertBefore : function( child, reference ) {
			const at = reference ? el.children.indexOf( reference ) : -1;
			child.parentNode = el;
			if( at === -1 )
				el.children.push( child );
			else
				el.children.splice( at, 0, child );
			return child;
		},
		insertAdjacentElement : function( where, child ) {
			if( where !== 'afterend' )
				throw new Error( 'only afterend is stood in for' );
			child.parentNode = el.parentNode;
			el.parentNode.children.splice( el.parentNode.children.indexOf( el ) + 1, 0, child );
			return child;
		},
		replaceWith : function( other ) {
			el.parentNode.children[ el.parentNode.children.indexOf( el ) ] = other;
			other.parentNode = el.parentNode;
			el.parentNode = null;
		},
		remove : function() { if( el.parentNode !== null ) el.parentNode.removeChild( el ) },
		setAttribute : function( name, value ) {
			el.attributes[name] = String( value );
			reflect( el, name, String( value ) );
		},
		getAttribute : function( name ) { return el.attributes[name] ?? null },
		hasAttribute : function( name ) { return name in el.attributes },
		removeAttribute : function( name ) {
			delete el.attributes[name];
			reflect( el, name, null );
		},
		addEventListener : function( name, fn, options ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( options && options.once === true ? once( el, name, fn ) : fn );
			el.listenerOptions[name] = options;
		},
		removeEventListener : function( name, fn ) {
			const list = el.listeners[name] || [];
			const at = list.findIndex( function( held ) { return held === fn || held.listener === fn } );
			if( at !== -1 )
				list.splice( at, 1 );
		},
		// An event goes from its target up through the parents when it bubbles, as in a page
		dispatchEvent : function( event ) {
			for( let node = el; node !== null; node = event.bubbles === true ? node.parentNode : null )
				fire( node, event.type, Object.assign( {}, event, { target : el } ) );
			return true;
		},
		querySelector : function( selector ) { return el.querySelectorAll( selector )[0] ?? null },
		// ':scope > ' in front of a selector limits the question to the children: how the panel head is asked for
		querySelectorAll : function( selector ) {
			if( selector.indexOf(':scope > ') === 0 )
				return el.children.filter( function( child ) { return matches( child, selector.slice( 9 ) ) } );
			return findAll( el, function( node ) { return matches( node, selector ) } );
		},
		closest : function( selector ) {
			for( let node = el; node !== null; node = node.parentNode )
				if( matches( node, selector ) )
					return node;
			return null;
		},
		// Taking the focus takes it from the element that had it
		focus : function() {
			if( inFocus !== null && inFocus !== el )
				inFocus.focused = false;
			inFocus = el;
			el.focused = true;
		},
		blur : function() {
			el.focused = false;
			if( inFocus === el )
				inFocus = null;
		},
		click : function() { fire( el, 'click' ) },
		setSelectionRange : function( start, end ) {
			el.selectionStart = start;
			el.selectionEnd = end;
		},
		// Nothing to select in a document that has no layout; a script that selects the text of a field before it copies it (Nino.clipboard()) is let through
		select : function() {},
	};

	el.classList = classList( el );

	// A control's value is always a string in a browser, whatever was assigned;
	// an option that was given none reads its text, and a select reads its
	// selected option's - the first one that can be chosen when none is
	// selected, but for an assignment that matched no option: that leaves none
	// selected, and the value empty, until the options change
	let held = null;
	let unmatched = null;
	Object.defineProperty( el, 'value', {
		enumerable : true,
		configurable : true,
		get : function() {
			if( el.tagName !== 'SELECT' )
				return held ?? ( el.tagName === 'OPTION' ? String( el.textContent ) : '' );
			const unchanged = unmatched !== null && unmatched.length === el.children.length && unmatched.every( function( option, at ) { return option === el.children[at] } );
			const chosen = el.children.filter( function( option ) { return option.selected === true } ).pop() ?? ( unchanged ? undefined : el.children.find( function( option ) { return option.disabled !== true } ) );
			return chosen === undefined ? '' : chosen.value;
		},
		set : function( next ) {
			if( el.tagName !== 'SELECT' ) {
				held = String( next );
				return;
			}
			el.children.forEach( function( option ) { option.selected = option.value === String( next ) } );
			unmatched = el.children.some( function( option ) { return option.selected === true } ) ? null : el.children.slice();
		},
	} );

	// Assigning innerHTML empties the element; the markup itself is not parsed
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );

	Object.defineProperty( el, 'firstChild', { get : function() { return el.children[0] ?? null } } );

	return el;
}

/**
 *	A document over one mount element: createElement() is element(),
 *	createTextNode() makes a text node of one (see element()) that holds the
 *	text, getElementById() finds the mount, or what is drawn under it, and the
 *	queries run over the mount. No root, no body, nothing in focus
 *
 *	@param		{Object}	mount
 *
 *	@return		{Object}
 */
function documentOf( mount ) {
	return {
		createElement : element,
		createTextNode : function( text ) { return Object.assign( element('#text'), { textContent : String( text ) } ) },
		getElementById : function( id ) {
			return mount.id === id ? mount : ( findAll( mount, function( el ) { return el.id === id } )[0] ?? null );
		},
		querySelector : function( selector ) { return mount.querySelector( selector ) },
		querySelectorAll : function( selector ) { return mount.querySelectorAll( selector ) },
		documentElement : null,
		body : null,
		activeElement : null,
	};
}

module.exports = { check, done, source, fills, load, classList, element, findAll, fire, matches, documentOf };
