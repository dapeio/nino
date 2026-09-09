/**
 *	Nino											A compact filesystembased php framework
 *	admin-submissions-js-smoke.js	DOM-light checks for the Submissions panel's
 *												script (_nino/Nino/Modules/Form/assets/admin.js).
 *
 *												The panel knows no field names of its own: a project
 *												may define any number of forms with fields of their
 *												own (\Nino\Form::FORMS), so what a card shows is
 *												whatever the entry carries, labelled from the form it
 *												belongs to. These checks are about exactly that -
 *												that a form Nino never heard of renders, that a value
 *												whose field was removed from the form still shows,
 *												that the two filters narrow together and the export
 *												takes what they left, and that an entry too old to
 *												have an id offers no delete button rather than one
 *												that could remove the wrong row.
 *
 *	Usage: node tests/admin-submissions-js-smoke.js
 */

'use strict';

const fs 		= require('fs');
const path	= require('path');
const vm 		= require('vm');

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

function source( relative ) {
	return fs.readFileSync( path.join( __dirname, '..', relative ), 'utf8' );
}

/**
 *	The fills of one text file, key => value - enough of the php to read
 *	a `'[[/key]]' => 'value',` line
 */
function fills( relative ) {
	const map = {};
	const re = /'\[\[(\/_admin\/[^\]]+)\]\]'\s*=>\s*'((?:[^'\\]|\\.)*)'/g;
	const text = source( relative );
	let match;
	while( ( match = re.exec( text ) ) )
		map[match[1]] = match[2].replace( /\\'/g, "'" );
	return map;
}


// --- an element stand-in, the same shape the other panel tests build -------

function classList( el ) {
	const values = new Set();
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

function matchesSelector( el, selector ) {
	return selector.charAt( 0 ) === '.'
		? String( el.className ).split(' ').indexOf( selector.slice( 1 ) ) !== -1
		: el.tagName === selector.toUpperCase();
}

function element( tag ) {
	const el = {
		tagName : String( tag ).toUpperCase(),
		className : '',
		textContent : '',
		value : '',
		type : '',
		href : '',
		id : '',
		tabIndex : 0,
		// Nothing overflows in a document that has no layout, so no card ever
		// gets the "expand" hint here - which is the honest answer for a
		// stand-in and keeps the check below about what is in the card
		scrollHeight : 0,
		clientHeight : 0,
		style : {},
		dataset : {},
		attributes : {},
		children : [],
		listeners : {},
		appendChild : function( child ) { el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
		focus : function() {},
		querySelector : function( selector ) { return findAll( el, function( node ) { return matchesSelector( node, selector ) } )[0] ?? null },
		querySelectorAll : function( selector ) { return findAll( el, function( node ) { return matchesSelector( node, selector ) } ) },
	};
	el.classList = classList( el );
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

function fire( el, name, event ) {
	( el.listeners[name] || [] ).forEach( function( fn ) { fn( event || { preventDefault : function() {}, stopPropagation : function() {} } ) } );
}

function byTag( root, tag ) {
	return findAll( root, function( el ) { return el.tagName === tag.toUpperCase() } );
}

function hasClass( el, name ) {
	return String( el.className ).split(' ').indexOf( name ) !== -1 || el.classList.contains( name );
}

/** Every card on screen, by the entry id it carries */
function cards() {
	return findAll( mount, function( el ) { return hasClass( el, 'submissions-entry' ) } );
}

function cardIds() {
	return cards().map( function( el ) { return el.dataset.entry } ).join(',');
}

/** One card's label/value pairs, as "label=value" */
function pairs( card ) {
	const list = card.querySelector('.submissions-entry-fields');
	if( list === null )
		return [];
	const out = [];
	for( let i = 0; i < list.children.length; i += 2 )
		out.push( list.children[i].textContent+ '='+ list.children[i + 1].textContent );
	return out;
}


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_nino/Nino/Modules/Form/text/en_US.php');
const moduleDe 		= fills('_nino/Nino/Modules/Form/text/de_DE.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'submissions-list';

const requests = [];
const exported = [];
let confirmAnswer = true;

const Nino = {
	// The two helpers the panel takes from the shell's own script, which is
	// not loadable here (it drives the whole workbench): the escaping a
	// stored value is read back through, and the export
	admin : {
		decodeEntities : function( value ) {
			return String( value ).replace( /&amp;/g, '&' ).replace( /&quot;/g, '"' ).replace( /&#039;/g, "'" ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' );
		},
		exportCsv : function( filename, rows ) { exported.push( { filename : filename, rows : rows } ) },
	},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		getElementById : function( id ) { return id === 'submissions-list' ? mount : findAll( mount, function( el ) { return el.id === id } )[0] ?? null },
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = {
	Nino : Nino,
	confirm : function() { return confirmAnswer },
	location : { hash : '' },
};

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );

vm.runInContext( source('_nino/Nino/Modules/Form/assets/admin.js'), context, { filename : 'admin.js' } );

const panel = Nino.admin.submissions;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	const request = requests[requests.length - 1];
	request.callback( { status : status, responseJSON : body } );
	return request;
}

// Two forms Nino never heard of, one of which lost a field since the
// submission that used it was recorded
const FORMS = {
	contact : { name : 'Contact', fields : [
		{ name : 'name', 		label : 'Name', 		type : 'text' },
		{ name : 'email', 	label : 'E-Mail', 	type : 'email' },
		{ name : 'message', label : 'Message', 	type : 'textarea' },
	] },
	quote : { name : 'Quote', fields : [
		{ name : 'company', label : 'Company', 	type : 'text' },
		{ name : 'mail', 		label : 'Address', 	type : 'email' },
		{ name : 'budget', 	label : 'Budget', 	type : 'number' },
	] },
};

const ENTRIES = [
	{ id : 'aaaaaaaaaaaaaaaa', date : '2026-09-08 10:00:00', form : 'quote', company : 'Acme &amp; Sons', mail : 'buyer@example.com', budget : '5000', legacy : 'kept', ip : '127.0.0.1' },
	{ id : 'bbbbbbbbbbbbbbbb', date : '2026-09-07 09:00:00', form : 'contact', name : 'Jo', email : 'jo@example.com', message : 'Hello there', ip : '127.0.0.1' },
	// Written before submissions carried an id, and read as the first form's
	{ id : '', date : '2020-01-01 00:00:00', form : 'contact', name : 'Old', email : 'old@example.com', message : 'Ancient', ip : '127.0.0.1' },
];

console.log('Submissions panel');

check( 'the script attaches under its nav uri with init and showCurrent', typeof panel === 'object' && typeof panel.init === 'function' && typeof panel.showCurrent === 'function' );

panel.init();
check( 'opening it asks the backend for the list, and nothing else', requests.length === 1 && requests[0].action === 'submissions/list' );

answer( 200, { entries : ENTRIES, forms : FORMS } );


// --- a card is whatever the entry carries ---------------------------------

check( 'every entry is a card, in the order the server sent them', cardIds() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,' );

const quote = cards()[0];

check( 'the header names the date and, with more than one form, which form it is',
	quote.querySelector('.submissions-entry-date').textContent === '2026-09-08 10:00:00'
	&& quote.querySelector('.submissions-entry-cat').textContent === 'Quote' );
check( 'the address to answer at is a mailto link, taken from the form\'s own email field whatever it is called',
	byTag( quote, 'A' )[0].href === 'mailto:buyer@example.com' && byTag( quote, 'A' )[0].textContent === 'buyer@example.com' );
check( 'every other value is a label/value pair, labelled from the form definition and in its field order',
	pairs( quote ).join('|') === 'Company=Acme & Sons|Budget=5000|legacy=kept' );
check( '...the escaping a value was stored with undone for display, and never as markup',
	pairs( quote )[0] === 'Company=Acme & Sons' && quote.querySelector('.submissions-entry-fields').children[1].textContent.indexOf('&amp;') === -1 );
check( 'a value whose field the form no longer declares still shows, at the end and under its own name',
	pairs( quote )[2] === 'legacy=kept' );
check( 'the long text is the one that is clamped rather than listed with the short answers',
	quote.querySelector('.submissions-entry-message') === null && cards()[1].querySelector('.submissions-entry-message').textContent === 'Hello there' );
check( 'and it is not repeated among the pairs', pairs( cards()[1] ).join('|') === 'Name=Jo' );


// --- what can be deleted ---------------------------------------------------

check( 'a card carries a delete button', quote.querySelector('.submissions-entry-delete') !== null
	&& hasClass( quote.querySelector('.submissions-entry-delete'), 'nino-admin-btn-danger' ) );
check( 'an entry too old to have an id carries none - there is nothing to address it by that survives a deletion',
	cards()[2].querySelector('.submissions-entry-delete') === null );

confirmAnswer = false;
const asked = requests.length;
fire( quote.querySelector('.submissions-entry-delete'), 'click' );
check( 'declining the confirmation sends nothing', requests.length === asked );

confirmAnswer = true;
fire( quote.querySelector('.submissions-entry-delete'), 'click' );
check( 'confirming posts submissions/delete with the entry\'s id alone', requests.length === asked + 1
	&& requests[requests.length - 1].action === 'submissions/delete'
	&& JSON.stringify( requests[requests.length - 1].payload ) === '{"id":"aaaaaaaaaaaaaaaa"}' );

answer( 200, { deleted : true } );
check( 'and the list is read again rather than the card taken off screen - what is on disk is what this panel shows',
	requests[requests.length - 1].action === 'submissions/list' );
answer( 200, { entries : ENTRIES, forms : FORMS } );


// --- the two filters -------------------------------------------------------

function formNow() {
	return sandbox.document.getElementById('submissions-form');
}

function searchNow() {
	return sandbox.document.getElementById('submissions-search');
}

check( 'the head offers the form to narrow to and the search over what is left, both labelled from the text system',
	formNow().tagName === 'SELECT' && formNow().attributes['aria-label'] === text('/_admin/submissions/label/form')
	&& searchNow().type === 'search' && searchNow().placeholder === text('/_admin/submissions/label/search') );
check( 'the select offers every form, with the entry that turns it off first',
	byTag( formNow(), 'OPTION' ).map( function( o ) { return o.value } ).join('|') === '|contact|quote'
	&& byTag( formNow(), 'OPTION' )[0].textContent === text('/_admin/submissions/label/form-all') );

formNow().value = 'contact';
fire( formNow(), 'change' );
check( 'picking a form shows its submissions alone', cardIds() === 'bbbbbbbbbbbbbbbb,' );
check( 'and the pick survives the redraw it triggers', formNow().value === 'contact' );

searchNow().value = 'ANCIENT';
fire( searchNow(), 'input' );
check( 'the search reads the values, ignoring case, and narrows what the form left', cardIds() === '' );

searchNow().value = 'address';
fire( searchNow(), 'input' );
check( 'the two narrow together rather than one replacing the other - the Quote form has an "Address", the contact form does not',
	findAll( mount, function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/submissions/nomatch') );

formNow().value = '';
fire( formNow(), 'change' );
check( 'a label is searchable as well as a value, so "address" finds the form that has one', cardIds() === 'aaaaaaaaaaaaaaaa' );

searchNow().value = '';
fire( searchNow(), 'input' );
check( 'cleared, every card is back', cardIds() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,' );


// --- the export is what is on screen ---------------------------------------

formNow().value = 'contact';
fire( formNow(), 'change' );
fire( sandbox.document.getElementById('submissions-export'), 'click' );
check( 'the export takes what the filters left, not what is on disk', exported.length === 1
	&& exported[0].filename === text('/_admin/submissions/label/filename')
	&& exported[0].rows.length === 2 && exported[0].rows[0].id === 'bbbbbbbbbbbbbbbb' );


// --- the states that are not each other ------------------------------------

panel.init();
answer( 200, { entries : [], forms : FORMS } );
check( 'nothing recorded at all says so, and draws no filters for a list that does not exist',
	findAll( mount, function( el ) { return hasClass( el, 'nino-admin-empty' ) } )[0].textContent === text('/_admin/submissions/empty')
	&& formNow() === null && searchNow() === null );

// Narrowed to a form that the next load no longer knows - renamed in the
// builder, or deleted. Its submissions are still on disk and still shown
panel.init();
answer( 200, { entries : ENTRIES, forms : FORMS } );
formNow().value = 'quote';
fire( formNow(), 'change' );
check( 'narrowed to Quote, only its submission is on screen', panel._form === 'quote' && cardIds() === 'aaaaaaaaaaaaaaaa' );

panel.init();
answer( 200, { entries : ENTRIES, forms : { contact : FORMS.contact } } );
check( 'a form that is gone since the last load stops narrowing rather than emptying the list for good',
	panel._form === '' && cardIds() === 'aaaaaaaaaaaaaaaa,bbbbbbbbbbbbbbbb,' );
check( 'and with one form left the badge and the select are dropped - both would say the same word on every card',
	cards()[0].querySelector('.submissions-entry-cat') === null && formNow() === null && searchNow() !== null );

panel.init();
answer( 500, null );
check( 'a failed load is reported through the module\'s own words',
	mount.children.length === 1 && hasClass( mount.children[0], 'nino-admin-error' ) && mount.children[0].textContent === '(500) '+ text('/_admin/submissions/error/load') );


// --- one language, one text system ----------------------------------------

const script = source('_nino/Nino/Modules/Form/assets/admin.js');
const keys = [];
const keyRe = /getText\(\s*'(\/_admin\/[^']+)'\s*\)/g;
let match;
while( ( match = keyRe.exec( script ) ) )
	if( keys.indexOf( match[1] ) === -1 )
		keys.push( match[1] );

const workbenchDe = fills('_admin/text/de_DE.php');
const missing = keys.filter( function( key ) {
	return ( moduleEn[key] || workbenchEn[key] ) === undefined || ( moduleDe[key] || workbenchDe[key] ) === undefined;
} );
check( 'every fill the script asks for exists in both interface languages'+ ( missing.length ? ' - missing: '+ missing.join(', ') : '' ), keys.length > 0 && missing.length === 0 );
check( 'the module\'s two text files declare the same keys', Object.keys( moduleEn ).sort().join(',') === Object.keys( moduleDe ).sort().join(',') );
check( 'no value is written into the page as markup - a stored value is escaped, and this is what keeps it that way',
	/innerHTML\s*=\s*[^']/.test( script.replace( /innerHTML\s*=\s*''/g, '' ) ) === false );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
