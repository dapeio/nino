/**
 *	Nino										A compact filesystembased php framework
 *	admin-routes-js-smoke.js	DOM-light checks for the Routes panel's script
 *													(_admin/Nino/Modules/Routes/assets/admin.js).
 *
 *													The ↑/↓ buttons of that list reorder the persisted
 *													routes, and equal menu priorities follow route order -
 *													so this list is also what orders every navigation the
 *													pages appear in. A move the server refuses therefore
 *													has to be said out loud rather than swallowed, and
 *													what is on screen afterwards has to be what is on
 *													disk. The checks below are about exactly that.
 *
 *	Usage: node tests/admin-routes-js-smoke.js
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

function classList() {
	const values = new Set();
	return {
		add : function( value ) { values.add( value ) },
		remove : function( value ) { values.delete( value ) },
		contains : function( value ) { return values.has( value ) },
	};
}

const focused = [];

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

function element( tag ) {
	const el = {
		tagName : String( tag ).toUpperCase(),
		className : '',
		textContent : '',
		title : '',
		type : '',
		href : '',
		id : '',
		disabled : false,
		style : {},
		attributes : {},
		dataset : {},
		children : [],
		listeners : {},
		value : '',
		appendChild : function( child ) { el.children.push( child ); return child },
		setAttribute : function( name, value ) { el.attributes[name] = String( value ) },
		removeAttribute : function( name ) { delete el.attributes[name] },
		focus : function() { focused.push( el ) },
		insertBefore : function( child, ref ) { el.children.splice( Math.max( el.children.indexOf( ref ), 0 ), 0, child ); return child },
		querySelector : function( selector ) { return el.querySelectorAll( selector )[0] ?? null },
		querySelectorAll : function( selector ) {
			// Only the attribute selectors the panel asks a row or the form for
			const match = /^\[data-field="(\w+)"\]$/.exec( selector );
			return findAll( el, function( child ) { return match !== null && child.dataset.field === match[1] } );
		},
		addEventListener : function( name, fn ) {
			el.listeners[name] = el.listeners[name] || [];
			el.listeners[name].push( fn );
		},
	};
	el.classList = classList();
	// A select's value is its selected option's, the way a browser reads it
	if( el.tagName === 'SELECT' )
		Object.defineProperty( el, 'value', {
			get : function() {
				const chosen = el.children.filter( function( option ) { return option.selected === true } ).pop() ?? el.children.find( function( option ) { return option.disabled !== true } );
				return chosen === undefined ? '' : chosen.value;
			},
			set : function( value ) { el.children.forEach( function( option ) { option.selected = option.value === value } ) },
		} );
	Object.defineProperty( el, 'innerHTML', {
		get : function() { return '' },
		set : function() { el.children.length = 0 },
	} );
	return el;
}

// Tolerates a control that is not on screen, so a panel that threw its own
// buttons away reports as failed checks rather than as a crashed test
function fire( el, name ) {
	if( el === null || el === undefined )
		return;
	( el.listeners[name] || [] ).forEach( function( fn ) { fn( { preventDefault : function() {} } ) } );
}

/** The route rows on screen, in the order they stand in */
function rows() {
	return findAll( mount, function( el ) { return el.className === 'admin-page-row' } );
}

/** A row is the page's name over its path (the link), then ↗ if it has one, then the arrows */
function rowName( li ) { return li.children[0].children[0].children[0].textContent }
function rowPath( li ) { return li.children[0].children[0].children[1].textContent }

function rowLabels() {
	return rows().map( rowPath );
}

/** One row's ↑ or ↓ button */
function moveButton( httpUri, direction ) {
	const row = rows().find( function( li ) { return rowPath( li ) === httpUri } );
	if( row === undefined )
		return null;
	return row.children.find( function( child ) { return child.className === 'admin-page-move' } ).children[ direction === 'up' ? 0 : 1 ];
}

/** The ↗ link of a row, if it has one */
function openLink( httpUri ) {
	const row = rows().find( function( li ) { return rowPath( li ) === httpUri } );
	return row === undefined ? null : ( row.children.find( function( child ) { return child.className === 'admin-page-open' } ) ?? null );
}

function messageText() {
	const line = findAll( mount, function( el ) { return el.id === 'routes-list-msg' } )[0];
	return line === undefined ? null : line.textContent;
}

function messageClass() {
	const line = findAll( mount, function( el ) { return el.id === 'routes-list-msg' } )[0];
	return line === undefined ? null : line.className;
}


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Routes/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'routes-list';
const form = element('div');
form.id = 'routes-form';

const requests = [];

const confirms = [];
let confirmAnswer = false;

const Nino = {
	admin : {
		assetUrl : function( path ) { return '/site'+ path },
		formToolbar : function( backLink ) { const bar = element('div'); bar.appendChild( backLink ); return bar },
	},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		getElementById : function( id ) {
			if( id === 'routes-list' ) return mount;
			if( id === 'routes-form' ) return form;
			return findAll( mount, function( el ) { return el.id === id } )[0] ?? findAll( form, function( el ) { return el.id === id } )[0] ?? null;
		},
		// Only the three selectors the panel asks the form for
		querySelectorAll : function( selector ) {
			if( selector === '#routes-form-locales [data-locale]' )
				return findAll( form, function( el ) { return el.dataset.locale !== undefined } );
			if( selector === '#routes-form [aria-invalid]' )
				return findAll( form, function( el ) { return 'aria-invalid' in el.attributes } );
			if( selector === '#routes-form [data-nav]' )
				return findAll( form, function( el ) { return el.dataset.nav !== undefined } );
			return [];
		},
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, location : { hash : '' }, confirm : function( message ) { confirms.push( message ); return confirmAnswer } };

const context = vm.createContext( sandbox );
vm.runInContext( source('_admin/assets/Nino.admin.js'), context, { filename : 'Nino.admin.js' } );
vm.runInContext( source('_admin/Nino/Modules/Routes/assets/admin.js'), context, { filename : 'admin.js' } );

const panel = Nino.admin.routes;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

const NAMES = { '/' : 'Start', '/about' : 'Über uns', '/contact' : 'Kontakt' };

function listing( order, extra ) {
	return Object.assign( {
		pages 		: order.map( function( uri ) {
			return { httpUri : uri, uri : uri, template : 'page'+ uri.replace('/','-'), body : '[template /templates/page'+ uri.replace('/','-')+ ']',
				text : { de_DE : { name : NAMES[uri] || '', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } };
		} ),
		templates 			: [],
		defaultTemplate : '',
		locales 				: [ 'de_DE', 'en_US' ],
		selectedLocale 	: 'de_DE',
		navs 						: [],
	}, extra || {} );
}

console.log('Admin Routes');

panel.init();
check( 'the panel asks for the route list', requests.length === 1 && requests[0].action === 'routes/list' );

answer( 200, listing( [ '/', '/about', '/contact' ] ) );
check( 'every route is a row, in the persisted order', rowLabels().join(',') === '/,/about,/contact' );
check( 'the list carries the line a refused move reports into', messageText() === '' && messageClass() === '' );

/*	A move the server accepts: the answer is the new order, and the list is
	drawn from it rather than from a local guess	*/
fire( moveButton( '/contact', 'up' ), 'click' );
check( 'the move is sent for the row whose arrow was pressed', requests.length === 2
	&& requests[1].action === 'routes/move'
	&& requests[1].payload.httpUri === '/contact' && requests[1].payload.direction === 'up' );

answer( 200, { pages : listing( [ '/', '/contact', '/about' ] ).pages } );
check( 'an accepted move redraws the list in the order the server answers with', rowLabels().join(',') === '/,/contact,/about' );
check( '...and says nothing, because nothing went wrong', messageText() === '' );

/*	A move the server refuses. It used to end in a bare return: the row did
	not move, nothing appeared anywhere, and the arrow read as a button that
	does nothing at all. The order on screen is a guess from that moment on -
	somebody else may have deleted the very route this list still draws - so
	it is read back, and the reason is said.	*/
fire( moveButton( '/about', 'up' ), 'click' );
answer( 409, { error : 'route was removed in the meantime' } );

check( 'a refused move reads the list back from the server', requests.length === 4 && requests[3].action === 'routes/list' );
answer( 200, listing( [ '/', '/contact' ] ) );

check( '...so what is on screen afterwards is what is on disk', rowLabels().join(',') === '/,/contact' );
check( '...and the reason the server gave is under the list', messageText() === '(409) route was removed in the meantime'
	&& messageClass() === 'nino-admin-error' );

// An answer with no reason of its own still says what happened
fire( moveButton( '/contact', 'up' ), 'click' );
answer( 500, null );
answer( 200, listing( [ '/', '/contact' ] ) );
check( 'an answer with no reason falls back to the shared sentence', messageText() === '(500) '+ text('/_admin/common/error/save')
	&& text('/_admin/common/error/save') !== '' );
check( '...and the list is still a list of routes', rowLabels().join(',') === '/,/contact' );

// A reload that fails too must not leave the panel silent about the move
fire( moveButton( '/contact', 'up' ), 'click' );
answer( 403, { error : 'no permission' } );
answer( 403, null );
check( 'a move error survives a reload that fails as well', messageText() === '(403) no permission' );

/*	What a row says: the page's name over its path, and a way to the page
	itself. The template and the Element URI are in the form, not on the row.	*/
panel.init();
answer( 200, listing( [ '/', '/about', '//evil.example', 'relative' ], { templates : [ 'page-about', 'page-blank', 'page-contact' ], defaultTemplate : 'page-blank' } ) );
check( 'a row names the page in the language the workbench is on, and shows its path under it', rows().map( rowName ).slice( 0, 2 ).join() === 'Start,Über uns' && rowLabels().slice( 0, 2 ).join() === '/,/about' );
check( '...and a page nobody named in any language is called by its path', rowName( rows()[2] ) === '//evil.example' );

const withOpen = openLink( '/about' );
check( 'the link to the page is a real link, in a tab of its own, under the project directory', withOpen !== null && withOpen.href === '/site/about' && withOpen.target === '_blank' && withOpen.rel === 'noopener'
	&& withOpen.attributes['aria-label'] === text('/_admin/routes/label/open') && withOpen.textContent === '↗' );
check( '...but none for a path that would leave the site or is no path', openLink( '//evil.example' ) === null && openLink( 'relative' ) === null );
check( 'the ↑/↓ cluster is still on every row', rows().every( function( li ) { return li.children.some( function( child ) { return child.className === 'admin-page-move' } ) } ) );

// A name is the language the workbench is on first, and then any other
panel._selectedLocale = 'en_US';
check( 'a page with a name only in another language is called by that one', rowName( rows()[0] ) === 'Start' );
panel._selectedLocale = 'de_DE';

// Markup in a name is text on the row
const hostile = listing( [ '/x' ] );
hostile.pages[0].text.de_DE.name = '<img src=x onerror=alert(1)>';
panel._pages = hostile.pages;
panel._renderList();
check( 'a name is put on the row as text, whatever it holds', rowName( rows()[0] ) === '<img src=x onerror=alert(1)>' );
panel._pages = listing( [ '/', '/about', '//evil.example', 'relative' ] ).pages;
panel._renderList();

/*	A new route starts empty. The one thing it is given is the blank page when
	the project has one - and nothing otherwise, so the person has to choose.	*/
function listActionButton() {
	return findAll( mount, function( el ) { return el.className === 'nino-admin-btn-primary' } )[0];
}
function field( id ) {
	return findAll( form, function( el ) { return el.id === id } )[0];
}
function localeInput( locale, name ) {
	return findAll( form, function( el ) { return el.dataset.locale === locale } )[0].querySelector('[data-field="'+ name+ '"]');
}
function save() {
	fire( findAll( form, function( el ) { return el.tagName === 'FORM' } )[0], 'submit' );
}

fire( listActionButton(), 'click' );
check( 'a new route has no uri and no http uri filled in', field('routes-form-uri').value === '' && field('routes-form-http-uri').value === '' );
check( '...and starts on the blank page the project has, nothing else', field('routes-form-template').value === 'page-blank' );
check( '...with a name and a title field per language, and no filler in them', [ 'de_DE', 'en_US' ].every( function( locale ) {
	return [ 'name', 'title', 'description' ].every( function( name ) { return localeInput( locale, name ).value === '' } );
} ) );
check( 'every field of a language says which one it is, for a screen reader', localeInput( 'de_DE', 'name' ).attributes['aria-label'] === 'Name (de_DE)'
	&& localeInput( 'en_US', 'title' ).attributes['aria-label'] === 'HTML title (en_US)'
	&& localeInput( 'en_US', 'description' ).attributes['aria-label'] === 'HTML description (en_US)' );
check( '...the form is not left to the browser\'s own validation bubble', findAll( form, function( el ) { return el.tagName === 'FORM' } )[0].noValidate === true );

// No blank page: no proposal, a placeholder that is a choice to make
panel._defaultTemplate = '';
fire( listActionButton(), 'click' );
const chooser = field('routes-form-template');
const placeholder = chooser.children[0];
check( 'without a blank page the template select asks for a choice: a disabled, selected placeholder first', placeholder.value === '' && placeholder.disabled === true && placeholder.selected === true
	&& placeholder.textContent === text('/_admin/routes/label/choose') && chooser.value === '' );
check( '...and it is not the first template on offer in disguise', chooser.children.length === panel._templates.length + 1 );
panel._defaultTemplate = 'page-blank';

// Required fields: nothing is sent, the fields are marked, the first takes the focus
fire( listActionButton(), 'click' );
const before = requests.length;
focused.length = 0;
save();
check( 'a save with the required fields empty sends no request', requests.length === before );
check( '...marks every field that is missing: both uris and every name and title', [ 'routes-form-uri', 'routes-form-http-uri' ].every( function( id ) { return field( id ).attributes['aria-invalid'] === 'true' } )
	&& [ 'de_DE', 'en_US' ].every( function( locale ) { return [ 'name', 'title' ].every( function( name ) { return localeInput( locale, name ).attributes['aria-invalid'] === 'true' } ) } )
	&& localeInput( 'de_DE', 'description' ).attributes['aria-invalid'] === undefined && field('routes-form-template').attributes['aria-invalid'] === undefined );
check( '...puts the focus on the first of them', focused.length === 1 && focused[0] === field('routes-form-uri') );
check( '...and says it in the workbench\'s language, in the line of the form', field('routes-form-msg').textContent === text('/_admin/routes/error/required') && text('/_admin/routes/error/required') !== '' );

field('routes-form-uri').value = '/new';
field('routes-form-http-uri').value = '/new';
localeInput( 'de_DE', 'name' ).value = 'Neu';
localeInput( 'de_DE', 'title' ).value = 'Neu';
localeInput( 'en_US', 'name' ).value = '   ';
localeInput( 'en_US', 'title' ).value = 'New';
focused.length = 0;
save();
check( 'only what is still missing stays marked, and a blank one counts as missing', requests.length === before
	&& field('routes-form-uri').attributes['aria-invalid'] === undefined && localeInput( 'de_DE', 'name' ).attributes['aria-invalid'] === undefined
	&& localeInput( 'en_US', 'name' ).attributes['aria-invalid'] === 'true' && localeInput( 'en_US', 'title' ).attributes['aria-invalid'] === undefined );
check( '...with the focus on it', focused.length === 1 && focused[0] === localeInput( 'en_US', 'name' ) );

// The template placeholder counts as missing too
panel._defaultTemplate = '';
fire( listActionButton(), 'click' );
field('routes-form-uri').value = '/new';
field('routes-form-http-uri').value = '/new';
[ 'de_DE', 'en_US' ].forEach( function( locale ) { localeInput( locale, 'name' ).value = 'N'; localeInput( locale, 'title' ).value = 'T' } );
save();
check( 'a template nobody chose is refused as well', requests.length === before && field('routes-form-template').attributes['aria-invalid'] === 'true' );
panel._defaultTemplate = 'page-blank';

// Complete: one request, with the description allowed to stay empty
fire( listActionButton(), 'click' );
field('routes-form-uri').value = '/new';
field('routes-form-http-uri').value = '/new';
[ 'de_DE', 'en_US' ].forEach( function( locale ) { localeInput( locale, 'name' ).value = 'N'; localeInput( locale, 'title' ).value = 'T' } );
save();
check( 'a complete form is sent, an empty description with it', requests.length === before + 1 && requests[before].action === 'routes/save'
	&& requests[before].payload.template === 'page-blank' && requests[before].payload.text.en_US.description === '' && requests[before].payload.originalHttpUri === '' );
answer( 200, {} );
answer( 200, listing( [ '/', '/about', '/contact' ], { templates : [ 'page-about', 'page-blank', 'page-contact' ] } ) );

/*	The delete question names what stays: the texts and the template, and for
	the template whether other routes use it.	*/
function openRoute( httpUri ) {
	rows().find( function( li ) { return rowPath( li ) === httpUri } ).children[0].listeners.click[0]( { preventDefault : function() {} } );
}
function deleteButton() {
	return findAll( form, function( el ) { return el.className === 'nino-admin-btn-danger' } )[0];
}

const shared = listing( [ '/', '/about', '/contact' ], { templates : [ 'page-about', 'page-blank', 'page-contact' ] } );
shared.pages[0].template = shared.pages[1].template = shared.pages[2].template = 'page-shared';
shared.pages[1].uri = '/site-about';
shared.pages[1].text.en_US.title = 'About';
panel._pages = shared.pages;
panel._renderList();
openRoute( '/about' );
confirms.length = 0;
fire( deleteButton(), 'click' );
check( 'the delete question names the path, the texts that stay with their languages, and the path fill', confirms.length === 1
	&& confirms[0].includes('“/about”') && confirms[0].includes('/webpage/site-about/name|title (de_DE, en_US)') && confirms[0].includes('/webpage/site-about/uri') );
check( '...and the template, with how many other routes use it', confirms[0].includes('“page-shared”') && confirms[0].includes('used by 2 other routes') );
check( 'a refused question sends nothing', requests.length === before + 2 );

shared.pages[2].template = 'page-contact';
panel._pages = shared.pages;
openRoute( '/about' );
confirms.length = 0;
fire( deleteButton(), 'click' );
check( 'one other route is said in the singular', confirms[0].includes('used by one other route') );

shared.pages[0].template = 'page-home';
panel._pages = shared.pages;
openRoute( '/about' );
confirms.length = 0;
fire( deleteButton(), 'click' );
check( 'a template nobody else uses is said to be used by no other route', confirms[0].includes('“page-shared”') && confirms[0].includes('used by no other route') );

shared.pages[1].template = '';
shared.pages[1].body = '[template /templates/page-[[/nino/http/response/locale]]]';
panel._pages = shared.pages;
openRoute( '/about' );
confirms.length = 0;
fire( deleteButton(), 'click' );
check( 'a route that picks its template at runtime names its body instead', confirms[0].includes('the body “[template /templates/page-[[/nino/http/response/locale]]]”') && confirms[0].includes('“page-') === false );

confirmAnswer = true;
confirms.length = 0;
fire( deleteButton(), 'click' );
check( 'a confirmed question deletes the route', requests[requests.length - 1].action === 'routes/delete' && requests[requests.length - 1].payload.httpUri === '/about' );
confirmAnswer = false;
answer( 200, {} );
answer( 200, listing( [ '/', '/contact' ] ) );

// A route that picks its template at runtime has no template to choose: the
// disabled select is not a missing field
const runtimeRoute = listing( [ '/legal' ], { templates : [ 'page-about' ] } );
runtimeRoute.pages[0].template = '';
runtimeRoute.pages[0].body = '[template /templates/page-legal.[[/nino/http/response/locale]]]';
runtimeRoute.pages[0].text.de_DE.name = 'Rechtliches';
panel._pages = runtimeRoute.pages;
panel._renderList();
openRoute( '/legal' );
const sentBefore = requests.length;
[ 'de_DE', 'en_US' ].forEach( function( locale ) { localeInput( locale, 'name' ).value = 'R'; localeInput( locale, 'title' ).value = 'T' } );
save();
check( 'a route with a runtime body saves without choosing a template', requests.length === sentBefore + 1 && requests[sentBefore].payload.template === '' );
answer( 200, {} );
answer( 200, listing( [ '/', '/contact' ] ) );

/*	The open page's form is watched by the shell (Nino.admin.dirty), which asks
	before anything throws what was typed into it away. A second context, with
	a registry that records what it is told, since the one above has none	*/
{
	const calls = [];
	const watched = [];
	const sent = [];
	const nodes = {};
	const node = id => nodes[id] = nodes[id] ?? { id : id, value : 'x', textContent : '' };
	const box = {
		console : console,
		document : { getElementById : node, querySelectorAll : () => [], documentElement : null, body : null },
		Nino : {
			admin : { dirty : { watchForm : ( name, getter, save ) => watched.push( { name : name, getter : getter, save : save } ), snapshot : name => calls.push( 'snapshot '+ name ) } },
			events : { bindCallback : function() {} },
			http : { sendRequest : ( uri, method, callback, data ) => sent.push( { action : data.action, callback : callback } ) },
			content : { getText : key => key },
			adminUi : { api : { errorText : status => 'error '+ status, call : ( endpoint, payload, callback ) => sent.push( { action : endpoint, callback : callback } ) } },
		},
	};
	box.window = box;
	vm.runInContext( source('_admin/Nino/Modules/Routes/assets/admin.js'), vm.createContext( box ), { filename : 'admin.js' } );

	check( 'the page form registers with the shell under the panel\'s name', watched.length === 1 && watched[0].name === 'routes' && watched[0].getter() === node('routes-form') );

	const outcomes = [];
	box.Nino.admin.routes._save( ok => outcomes.push( ok ) );
	sent[sent.length - 1].callback( 500, null );
	check( 'a save that fails reports false and takes nothing for stored', outcomes.join() === 'false' && calls.length === 0 );

	box.Nino.admin.routes._save( ok => outcomes.push( ok ) );
	sent[sent.length - 1].callback( 200, {} );
	check( 'a save that goes through takes the form as it stands for the stored one, and reports true', outcomes.join() === 'false,true' && calls.join() === 'snapshot routes' );
	check( '...the shell\'s Save runs the same save', typeof watched[0].save === 'function' );
}

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
