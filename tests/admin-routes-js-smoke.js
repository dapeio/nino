/**
 *	Nino										A compact filesystembased php framework
 *	admin-routes-js-smoke.js	DOM-light checks for the Routes panel's script
 *													(_admin/Nino/Modules/Routes/assets/admin.js).
 *
 *													Every view has an address - the list, a route, a new
 *													one, a page a feature routes - which survives a reload,
 *													and a save leaves the person in the view it was made
 *													in. The rows are the block's (list()): a link that
 *													opens the form and, at the end, the link to the page and
 *													the buttons that reorder the persisted routes. The
 *													order is saved at once, which a toast says; a move the
 *													server refuses has to be said out loud rather than
 *													swallowed, and what is on screen afterwards has to be
 *													what is on disk.
 *
 *													A page has one address for every language or one for
 *													each: a switch says which, the form draws the one field
 *													or a row per language, and what the fields hold is
 *													what is sent. The name, the title and the addresses are
 *													one row per language; the description is the one of the
 *													language the workbench edits content in. The form
 *													checks nothing by itself (novalidate): what is wrong -
 *													here or in the server's answer - is said at the field.
 *
 *	Usage: node tests/admin-routes-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, element, findAll, fire } = require( './harness.js' );

// Every focus() the panel asks for, in order: a check counts them, which the
// element's own focused flag cannot - and the one that has it now, which is what a page answers to activeElement
const focused = [];
let active = null;

function control( tag ) {
	const el = element( tag );
	el.focus = function() { focused.push( el ); active = el };
	return el;
}

/** The rows of the list on screen, in the order they stand in: the pages of the project first, then the pages of features */
function rows() {
	return findAll( mount, function( el ) { return el.classList.contains('nino-admin-list-row') } );
}

function rowName( li ) { return findAll( li, function( el ) { return el.tagName === 'STRONG' } )[0].textContent }
function rowPath( li ) { return findAll( li, function( el ) { return el.tagName === 'SMALL' } )[0].textContent }
function rowLabels() { return rows().map( rowPath ) }

/** The row whose address line is the given one */
function rowOf( httpUri ) {
	return rows().find( function( li ) { return rowPath( li ) === httpUri } );
}

/** The link that opens the form of a row */
function rowLink( li ) {
	return findAll( li, function( el ) { return el.classList.contains('nino-admin-row-open') } )[0];
}

/** The buttons and links at the end of a row, in the order they stand in */
function rowButtons( li ) {
	return findAll( li, function( el ) { return el.classList.contains('nino-admin-row-actions') } )[0].children;
}

/** One row's up or down button */
function moveButton( httpUri, direction ) {
	const row = rowOf( httpUri );
	return row === undefined ? null : rowButtons( row ).find( function( el ) { return el.tagName === 'BUTTON' && el.attributes['aria-label'] === text( direction === 'up' ? '/_admin/routes/label/moveup' : '/_admin/routes/label/movedown' ) } ) ?? null;
}

/** The link to the page itself of a row, if it has one */
function openLink( httpUri ) {
	const row = rowOf( httpUri );
	return row === undefined ? null : ( rowButtons( row ).find( function( el ) { return el.tagName === 'A' } ) ?? null );
}

function messageLine() {
	return findAll( mount, function( el ) { return el.id === 'routes-list-msg' } )[0];
}

/** Whether an element is the root or stands somewhere below it */
function inside( root, el ) {
	for( let node = el; node !== null; node = node.parentNode )
		if( node === root )
			return true;
	return false;
}


// --- the words -------------------------------------------------------------

const moduleEn 		= fills('_admin/Nino/Modules/Routes/text/en_US.php');
const workbenchEn	= fills('_admin/text/en_US.php');

function text( key ) {
	return moduleEn[key] || workbenchEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

// The pane the shell draws: the name of the panel in its head, and the two levels beside it
const pane = element('div');
pane.dataset.panel = 'routes';
const head = element('div');
head.className = 'admin-panel-head';
const paneTitle = control('h2');
paneTitle.className = 'admin-panel-title';
head.appendChild( paneTitle );
pane.appendChild( head );
const mount = element('div');
mount.id = 'routes-list';
pane.appendChild( mount );
const form = element('div');
form.id = 'routes-form';
pane.appendChild( form );

function everything() {
	return [ mount, form ].concat( findAll( mount, function() { return true } ), findAll( form, function() { return true } ) );
}

const requests = [];

// The questions asked, with what they were about, and the answer the next one gets
const questions = [];
let questionAnswer = false;

// The shell's counters (see Nino.admin.touch()): what the panel says it wrote,
// and the stamp of the templates it reads back
const touched = [];
// ...and what each said it was about: the address of the page, where the writer has one
const touchedUris = [];
const stamps = { templates : 0 };

// The address the page was loaded on, and what the panel wrote into it
let hash = { panel : 'routes', parts : [] };
const written = [];
const moved = [];
const leaves = [];

// The shell's registry of unsaved input: what the panel says to it
const snapshots = [];
const watched = [];
const followers = [];
const workLocale = { current : null, native : 'de_DE', follow : function( name, handler ) { followers.push( { name : name, handler : handler } ) } };

const Nino = {
	admin : {
		assetUrl : function( path ) { return '/site'+ path },
		touch : function( topic, uri ) { touched.push( topic ); touchedUris.push( uri ) },
		stamp : function( topic ) { return stamps[topic] ?? 0 },
		workLocale : workLocale,
		router : {
			current : function() { return hash },
			go : function( panel, parts ) { moved.push( JSON.stringify( [ panel ].concat( parts ) ) ) },
			set : function( panel, parts ) { written.push( JSON.stringify( [ panel ].concat( parts ) ) ) },
			leave : function( names, open, proceed, resync ) { leaves.push( { open : open, proceed : proceed, resync : resync } ) },
			href : function( name, parts ) { return '#'+ [ name ].concat( parts || [] ).map( encodeURIComponent ).join('/') },
		},
		dirty : {
			watchForm : function( name, getter, save ) { watched.push( { name : name, getter : getter, save : save } ) },
			snapshot : function( name ) { snapshots.push( name ) },
			refresh : function() {},
		},
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
		createElement : control,
		createTextNode : function( content ) { return Object.assign( element('#text'), { textContent : String( content ) } ) },
		getElementById : function( id ) { return everything().find( function( el ) { return el.id === id } ) ?? null },
		createElementNS : function( space, tag ) { return element( tag ) },
		documentElement : null,
		body : null,
		get activeElement() { return active },
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, location : { hash : '' }, setTimeout : function() { return 1 }, clearTimeout : function() {} };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );

// The languages the shell named when it started
Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English' } };

// What is said as a toast, and the questions: collected, not drawn
const toasts = [];
Nino.adminUi.say = function( words ) { toasts.push( words ) };
Nino.adminUi.confirm = function( level, options ) {
	questions.push( Object.assign( { level : level }, options ) );
	options.onChoose( questionAnswer );
	return true;
};
load( '_admin/Nino/Modules/Routes/assets/admin.js', context );

const panel = Nino.admin.routes;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

const NAMES = { '/' : 'Start', '/about' : 'Über uns', '/contact' : 'Kontakt' };

function page( uri, extra ) {
	return Object.assign( { httpUri : uri, httpUris : { '' : uri }, uri : uri, template : 'page'+ uri.replace('/','-'), body : '[template /templates/page'+ uri.replace('/','-')+ ']', navs : [],
		text : { de_DE : { name : NAMES[uri] || '', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } }, extra || {} );
}

function listing( order, extra ) {
	return Object.assign( {
		pages 		: order.map( function( uri ) { return page( uri ) } ),
		runtime 	: [],
		templates 			: [],
		defaultTemplate : '',
		locales 				: [ 'de_DE', 'en_US' ],
		localeNames 		: { de_DE : 'Deutsch', en_US : 'English' },
		selectedLocale 	: 'de_DE',
		navs 						: [],
		navNames 				: {},
	}, extra || {} );
}

/** The form that is open: its fields by the name the server marks them by, and the bar */
function fieldNamed( name ) {
	return findAll( form, function( el ) { return el.dataset.field === name } )[0];
}
function field( id ) {
	return findAll( form, function( el ) { return el.id === id } )[0];
}
function rowControl( name, locale ) {
	return findAll( form, function( el ) { return el.dataset.field === name && el.dataset.locale === locale } )[0];
}
function addressControls() {
	return findAll( form, function( el ) { return el.dataset.address !== undefined } );
}
function addressOf( locale ) {
	return addressControls().find( function( el ) { return el.dataset.address === locale } );
}
function sentences() {
	return findAll( form, function( el ) { return el.className === 'nino-admin-field-error' } );
}
function theForm() {
	return findAll( form, function( el ) { return el.tagName === 'FORM' } )[0];
}
function save() {
	fire( theForm(), 'submit' );
}
function bar() {
	return findAll( form, function( el ) { return el.classList.contains('nino-admin-actionbar') } )[0];
}
function barButtons() {
	return bar().children.filter( function( el ) { return el.tagName === 'BUTTON' } );
}
function deleteButton() {
	return barButtons().find( function( el ) { return el.classList.contains('nino-admin-btn-danger') } );
}
function primaryButton() {
	return barButtons().find( function( el ) { return el.classList.contains('nino-admin-btn-primary') } );
}
function status() {
	return field('routes-form-msg');
}
function backLink() {
	return findAll( form, function( el ) { return el.classList.contains('nino-admin-back-link') } )[0];
}
function newButton() {
	return findAll( mount, function( el ) { return el.classList.contains('nino-admin-btn-primary') } )[0];
}
function oneSwitch() {
	return findAll( form, function( el ) { return el.dataset.key === 'one-address' } )[0];
}
/** The checkboxes of the list of navigations */
function navTicks() {
	return findAll( form, function( el ) { return el.dataset.key === 'navs' } );
}

console.log('Admin Routes');

panel.init();
check( 'the panel asks for the route list', requests.length === 1 && requests[0].action === 'routes/list' );

answer( 200, listing( [ '/', '/about', '/contact' ] ) );
check( 'every route is a row, in the persisted order', rowLabels().join(',') === '/,/about,/contact' );
check( 'a fresh load on the address of the list shows the list, at its address', mount.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true && written.at(-1) === '["routes"]' );
check( 'the list carries the line a refused move reports into, empty', messageLine() !== undefined && messageLine().textContent === '' );

check( 'the New route button is the one main button of the list, in the bar of the list', newButton() !== undefined && newButton().textContent === text('/_admin/routes/label/new')
	&& findAll( mount, function( el ) { return el.classList.contains('nino-admin-btn-primary') } ).length === 1 );

/*	A move the server accepts: the answer is the new order, and the list is
	drawn from it rather than from a local guess	*/
fire( moveButton( '/contact', 'up' ), 'click' );
check( 'the move is sent for the row whose button was pressed', requests.length === 2
	&& requests[1].action === 'routes/move'
	&& requests[1].payload.uri === '/contact' && requests[1].payload.direction === 'up' );

answer( 200, { pages : listing( [ '/', '/contact', '/about' ] ).pages } );
check( 'an accepted move redraws the list in the order the server answers with', rowLabels().join(',') === '/,/contact,/about' );
check( '...the focus stays on the button that was pressed, which stands in the row the page moved to', active === moveButton( '/contact', 'up' ) );
check( '...says so as a toast, because the order is saved at once', toasts.length === 1 && toasts[0] === text('/_admin/routes/msg/moved') && text('/_admin/routes/msg/moved') !== '' );
check( '...and tells the other panels that routes were written, of no page in particular', touched.join() === 'routes' && touchedUris.length === 1 && touchedUris[0] === undefined );

/*	A move the server refuses: the row did not move, and the order on screen is
	a guess from that moment on - somebody else may have deleted the very route
	this list still draws - so it is read back, and the reason is said	*/
fire( moveButton( '/about', 'up' ), 'click' );
answer( 409, { error : 'route was removed in the meantime' } );

check( 'a refused move reads the list back from the server', requests.length === 4 && requests[3].action === 'routes/list' );
check( '...and says nothing of routes written, nor as a toast: nothing was', touched.join() === 'routes' && toasts.length === 1 );
answer( 200, listing( [ '/', '/contact' ] ) );

check( '...so what is on screen afterwards is what is on disk', rowLabels().join(',') === '/,/contact' );
check( '...and the reason the server gave is in the line under the list, as an alert', messageLine().textContent === '(409) route was removed in the meantime'
	&& messageLine().dataset.state === 'error' && messageLine().attributes.role === 'alert' );

// An answer with no reason of its own still says what happened
fire( moveButton( '/contact', 'up' ), 'click' );
answer( 500, null );
answer( 200, listing( [ '/', '/contact' ] ) );
check( 'an answer with no reason falls back to the shared sentence', messageLine().textContent === '(500) '+ text('/_admin/common/error/save') && text('/_admin/common/error/save') !== '' );
check( '...and the list is still a list of routes', rowLabels().join(',') === '/,/contact' );

// A reload that fails too must not leave the panel silent about the move
fire( moveButton( '/contact', 'up' ), 'click' );
answer( 403, { error : 'no permission' } );
answer( 403, null );
check( 'a move error survives a reload that fails as well', messageLine().textContent === '(403) no permission' );

/*	What a row says: the page's name over its path, and a way to the page itself.
	The template and the Element URI are in the form, not on the row.	*/
panel.init();
answer( 200, listing( [ '/', '/about', '//evil.example', 'relative' ], { templates : [ 'page-about', 'page-blank', 'page-contact' ], defaultTemplate : 'page-blank' } ) );
check( 'a row names the page in the language the workbench is on, and shows its path under it', rows().map( rowName ).slice( 0, 2 ).join() === 'Start,Über uns' && rowLabels().slice( 0, 2 ).join() === '/,/about' );
check( '...and a page nobody named in any language is called by its path', rowName( rows()[2] ) === '//evil.example' );

check( 'a row is a link that writes the address of its form, and opens the form through the router', rows().every( function( li ) {
	const link = rowLink( li );
	return link.tagName === 'A' && link.href === Nino.admin.router.href( 'routes', [ li.dataset.row ] );
} ) && rows()[1].dataset.row === '/about' && rowLink( rows()[1] ).href === '#routes/%2Fabout' );

const withOpen = openLink( '/about' );
check( 'the link to the page is a real link in a tab of its own, under the project directory, named for a screen reader', withOpen !== null && withOpen.href === '/site/about' && withOpen.target === '_blank' && withOpen.rel === 'noopener'
	&& withOpen.attributes['aria-label'] === text('/_admin/routes/label/open') && text('/_admin/routes/label/open') !== '' && withOpen.textContent === '' );
check( '...but none for a path that would leave the site or is no path', openLink( '//evil.example' ) === null && openLink( 'relative' ) === null );
check( 'a row has the link to the page, then up and down - three buttons at the most, the arrows named as such', rowButtons( rowOf( '/about' ) ).map( function( el ) { return el.tagName }).join() === 'A,BUTTON,BUTTON'
	&& rowButtons( rowOf( '//evil.example' ) ).length === 2 && moveButton( '/about', 'up' ) !== null && moveButton( '/about', 'down' ) !== null );
check( '...and the first row cannot go up, the last cannot go down', moveButton( '/', 'up' ).disabled === true && moveButton( 'relative', 'down' ).disabled === true && moveButton( '/about', 'up' ).disabled === false );

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


/*	Opening a form: the address of the page is written, the focus goes into the form, and Back returns to the row	*/
fire( rowLink( rowOf( '/about' ) ), 'click' );
check( 'a row opens the form of its page through the router and writes its address', moved.at(-1) === '["routes","/about"]' && written.at(-1) === '["routes","/about"]' );
check( '...the form is shown in place of the list, and the focus is in it', form.classList.contains('admin-hidden') === false && mount.classList.contains('admin-hidden') === true && inside( form, active ) );
check( '...a form is novalidate and has exactly one main button and the way back, and Delete at the left', theForm().noValidate === true && barButtons().filter( function( el ) { return el.classList.contains('nino-admin-btn-primary') } ).length === 1
	&& barButtons()[0] === deleteButton() && barButtons().at(-1) === primaryButton() && backLink() !== undefined );
check( '...the Element URI is a required field that holds the page\'s own', fieldNamed('uri').value === '/about' && fieldNamed('uri').attributes['aria-required'] === 'true' );
check( '...and the name of every language is a row of its own, the language of the workbench first', [ 'de_DE', 'en_US' ].every( function( locale ) { return rowControl( 'name', locale ) !== undefined && rowControl( 'title', locale ) !== undefined } )
	&& rowControl( 'name', 'de_DE' ).value === 'Über uns' && rowControl( 'name', 'en_US' ).value === '' );
check( '...the main button of a form of a route that exists is Save', primaryButton().textContent === text('/_admin/common/label/save') );
check( '...none of the fields has a help sentence in the page that stays open: they are behind a (?) at the name', findAll( form, function( el ) { return el.classList.contains('nino-admin-hint') } ).length === 0 );
fire( backLink(), 'click' );
check( 'Back shows the list, writes its address and puts the focus on the row that was opened', mount.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true
	&& moved.at(-1) === '["routes"]' && written.at(-1) === '["routes"]' && active === rowLink( rowOf( '/about' ) ) );

/*	A new route starts empty. The one thing it is given is the blank page when
	the project has one - and nothing otherwise, so the person has to choose.	*/
fire( newButton(), 'click' );
check( 'a new route has an address of its own, #routes/@new', moved.at(-1) === '["routes","@new"]' && written.at(-1) === '["routes","@new"]' );
check( '...no Element URI and no address filled in, one field for the address', fieldNamed('uri').value === '' && addressControls().length === 1 && addressControls()[0].value === '' );
check( '...and starts on the blank page the project has, nothing else', fieldNamed('template').value === 'page-blank' );
check( '...with a name and a title per language and no filler in them, and no Delete: there is nothing to delete', [ 'de_DE', 'en_US' ].every( function( locale ) {
	return rowControl( 'name', locale ).value === '' && rowControl( 'title', locale ).value === '';
} ) && deleteButton() === undefined && fieldNamed('description').value === '' );
check( '...the main button of a form that creates is worded as creating, not as saving: another fill than the form of a route that exists has', primaryButton().textContent === text('/_admin/routes/label/create')
	&& text('/_admin/routes/label/create') !== text('/_admin/common/label/save') && text('/_admin/routes/label/create') !== '' );
check( '...and the state of the bar has nothing to say about an input nobody made', status().dataset.state !== 'dirty' );
fire( theForm(), 'input', { target : fieldNamed('uri') } );
check( '...an edit turns the line of the bar to unsaved, which is where the screen says it', status().dataset.state === 'dirty' );
fire( backLink(), 'click' );
check( 'Back returns to the New route button', active === newButton() );

// No blank page: no proposal, a placeholder that is a choice to make
panel._defaultTemplate = '';
fire( newButton(), 'click' );
const chooser = fieldNamed('template');
check( 'without a blank page the template select asks for a choice: a disabled, selected placeholder first', chooser.children[0].value === '' && chooser.children[0].disabled === true && chooser.value === '' && chooser.children[0].selected === true );
check( '...and it is not the first template on offer in disguise', chooser.children.length === panel._templates.length + 1 );
panel._defaultTemplate = 'page-blank';

/*	Required fields: nothing is sent, the fields say so where they stand, the first takes the focus	*/
fire( newButton(), 'click' );
const before = requests.length;
focused.length = 0;
save();
check( 'a save with the required fields empty sends no request', requests.length === before );
check( '...says what is missing at every field: the Element URI, the address, and the name and the title of every language', [ fieldNamed('uri'), addressControls()[0] ].every( function( el ) { return el.attributes['aria-invalid'] === 'true' } )
	&& [ 'de_DE', 'en_US' ].every( function( locale ) { return [ 'name', 'title' ].every( function( name ) { return rowControl( name, locale ).attributes['aria-invalid'] === 'true' } ) } )
	&& fieldNamed('description').attributes['aria-invalid'] === undefined && fieldNamed('template').attributes['aria-invalid'] === undefined );
check( '...puts the focus on the first of them, and on that alone', focused.length === 1 && focused[0] === fieldNamed('uri') );
check( '...and a line in the bar, an alert of the status line', status().dataset.state === 'error' && status().attributes.role === 'alert' && status().textContent === text('/_admin/routes/error/fields') && text('/_admin/routes/error/fields') !== '' );
check( 'each missing field points at a sentence of its own that says what is missing', sentences().length === 6 && [ fieldNamed('uri'), addressControls()[0], rowControl( 'name', 'de_DE' ), rowControl( 'title', 'en_US' ) ].every( function( el ) {
	return sentences().some( function( sentence ) { return el.attributes['aria-describedby'].split(' ').indexOf( sentence.id ) !== -1 } );
} ) && sentences().every( function( sentence ) { return sentence.textContent.length > 0 } ) );

fieldNamed('uri').value = '/new';
addressControls()[0].value = '/new';
rowControl( 'name', 'de_DE' ).value = 'Neu';
rowControl( 'title', 'de_DE' ).value = 'Neu';
rowControl( 'name', 'en_US' ).value = '   ';
rowControl( 'title', 'en_US' ).value = 'New';
focused.length = 0;
save();
check( 'only what is still missing stays marked, and a blank value counts as missing, with the focus on it', requests.length === before
	&& fieldNamed('uri').attributes['aria-invalid'] === undefined && rowControl( 'name', 'de_DE' ).attributes['aria-invalid'] === undefined
	&& rowControl( 'name', 'en_US' ).attributes['aria-invalid'] === 'true' && rowControl( 'title', 'en_US' ).attributes['aria-invalid'] === undefined
	&& focused.length === 1 && focused[0] === rowControl( 'name', 'en_US' ) && sentences().length === 1 );

// The template placeholder counts as missing too
panel._defaultTemplate = '';
fire( backLink(), 'click' );
fire( newButton(), 'click' );
fieldNamed('uri').value = '/new';
addressControls()[0].value = '/new';
[ 'de_DE', 'en_US' ].forEach( function( locale ) { rowControl( 'name', locale ).value = 'N'; rowControl( 'title', locale ).value = 'T' } );
save();
check( 'a template nobody chose is refused as well', requests.length === before && fieldNamed('template').attributes['aria-invalid'] === 'true' );
panel._defaultTemplate = 'page-blank';

// Complete: one request, with the description allowed to stay empty
fire( backLink(), 'click' );
fire( newButton(), 'click' );
fieldNamed('uri').value = '/new';
addressControls()[0].value = '/new';
[ 'de_DE', 'en_US' ].forEach( function( locale ) { rowControl( 'name', locale ).value = 'N'; rowControl( 'title', locale ).value = 'T' } );
save();
check( 'a complete form is sent, an empty description with it', requests.length === before + 1 && requests[before].action === 'routes/save'
	&& requests[before].payload.template === 'page-blank' && requests[before].payload.text.en_US.description === '' && requests[before].payload.originalUri === ''
	&& requests[before].payload.uri === '/new' && JSON.stringify( requests[before].payload.httpUris ) === '{"":"/new"}' && Array.isArray( requests[before].payload.navs ) && requests[before].payload.statusCode === '200' );
check( '...and the line says it is saving', status().dataset.state === 'saving' );
touched.length = 0; touchedUris.length = 0; toasts.length = 0;
answer( 200, { pages : listing( [ '/', '/about', '//evil.example', 'relative', '/new' ] ).pages } );
check( 'a save that went through tells the other panels that routes were written, and names the page it saved by its address', touched.join() === 'routes' && touchedUris.join() === '/new' );
check( '...and says it was saved as a toast, once', toasts.length === 1 && toasts[0] === text('/_admin/common/msg/saved') );
check( '...and the person stays in the form: it is the page\'s own now, at its address, with Delete and a line that says nothing', form.classList.contains('admin-hidden') === false && written.at(-1) === '["routes","/new"]'
	&& deleteButton() !== undefined && fieldNamed('uri').value === '/new' && status().textContent === '' );
check( '...the list behind it has the page, and the focus is on the main button the person pressed', rowLabels().indexOf('/new') !== -1 && active === primaryButton() );
check( '...and what the form holds is what is saved', snapshots.at(-1) === 'routes' );

/*	A refusal of the server that names a field is said at that field - an address of a language, a name - and not only in the bar	*/
fire( backLink(), 'click' );
fire( newButton(), 'click' );
fieldNamed('uri').value = '/dup';
addressControls()[0].value = '/dup';
[ 'de_DE', 'en_US' ].forEach( function( locale ) { rowControl( 'name', locale ).value = 'N'; rowControl( 'title', locale ).value = 'T' } );
save();
answer( 400, { error : 'invalid uri', code : 'routes_invalid_uri', params : [ '/dup' ], field : 'uri' } );
check( 'a refusal that names the Element URI marks that field, says it under it and puts the focus there', fieldNamed('uri').attributes['aria-invalid'] === 'true' && active === fieldNamed('uri')
	&& sentences().length === 1 && sentences()[0].textContent === text('/_admin/error/routes_invalid_uri').replace( '%s', '/dup' ) );
check( '...and in the line of the bar, as an alert, with the same sentence', status().dataset.state === 'error' && status().textContent === sentences()[0].textContent );
check( '...and the form stays as it is: no list, no toast', form.classList.contains('admin-hidden') === false && toasts.length === 1 );

save();
answer( 400, { error : 'missing name', code : 'routes_missing_name', params : [ 'en_US' ], field : 'name' } );
check( 'a refusal that names a name of a language marks the row of that language, with the language written as the workbench writes it', rowControl( 'name', 'en_US' ).attributes['aria-invalid'] === 'true' && rowControl( 'name', 'de_DE' ).attributes['aria-invalid'] === undefined
	&& active === rowControl( 'name', 'en_US' ) && sentences().length === 1 && sentences()[0].textContent.indexOf('English') !== -1 && sentences()[0].textContent.indexOf('en_US') === -1 );
fire( backLink(), 'click' );


/*	The delete question: what goes and what stays, as the two groups of its list - the addresses of the page, and the
	texts and the template that stay, with how many other routes use the template	*/
const shared = listing( [ '/', '/about', '/contact' ], { templates : [ 'page-about', 'page-blank', 'page-contact' ] } );
shared.pages[0].template = shared.pages[1].template = shared.pages[2].template = 'page-shared';
shared.pages[1].uri = '/site-about';
shared.pages[1].text.en_US.title = 'About';
panel._pages = shared.pages;
panel._renderList();
fire( rowLink( rowOf( '/about' ) ), 'click' );
const askedBefore = requests.length;
questions.length = 0;
fire( deleteButton(), 'click' );
const asked = questions.at(-1);
check( 'the delete question is a question of the destructive level, with a title and the answer worded like it', questions.length === 1 && asked.level === 'destructive' && asked.title === asked.yes && asked.title === text('/_admin/routes/confirm/delete-title') && text('/_admin/routes/confirm/delete-title') !== '' );
check( '...the languages of the texts that stay are named as the workbench names them, never by their code', asked.details[1].lines[0].includes('de_DE') === false && asked.details[1].lines[0].includes('en_US') === false );
check( '...and has two groups under it: what is deleted - every address - and what stays', Array.isArray( asked.details ) && asked.details.length === 2
	&& asked.details[0].title === text('/_admin/routes/confirm/deleted') && asked.details[0].lines.join() === '/about' && asked.details[1].title === text('/_admin/routes/confirm/kept') );
check( '...what stays: the texts with their languages, the path fill, and the template with how many other routes use it', asked.details[1].lines.length === 2
	&& asked.details[1].lines[0].includes('/_nino/webpage/site-about/name|title (Deutsch, English)') && asked.details[1].lines[0].includes('/_nino/webpage/site-about/uri')
	&& asked.details[1].lines[1].includes('page-shared') && asked.details[1].lines[1].includes('2') );
check( 'a refused question sends nothing', requests.length === askedBefore );

shared.pages[2].template = 'page-contact';
panel._pages = shared.pages;
panel._renderList();
fire( backLink(), 'click' );
fire( rowLink( rowOf( '/about' ) ), 'click' );
questions.length = 0;
fire( deleteButton(), 'click' );
check( 'one other route and none are said by a sentence of their own each', questions.at(-1).details[1].lines[1] === Nino.adminUi.format( text('/_admin/routes/confirm/stays-template-one'), 'page-shared' ) );

shared.pages[0].template = 'page-home';
panel._pages = shared.pages;
panel._renderList();
fire( backLink(), 'click' );
fire( rowLink( rowOf( '/about' ) ), 'click' );
questions.length = 0;
fire( deleteButton(), 'click' );
check( '...a template nobody else uses is said to be used by no other route', questions.at(-1).details[1].lines[1] === Nino.adminUi.format( text('/_admin/routes/confirm/stays-template'), 'page-shared' ) );

shared.pages[1].template = '';
shared.pages[1].body = '[template /templates/page-[[/nino/http/response/locale]]]';
panel._pages = shared.pages;
panel._renderList();
fire( backLink(), 'click' );
fire( rowLink( rowOf( '/about' ) ), 'click' );
questions.length = 0;
fire( deleteButton(), 'click' );
check( 'a route that picks its template at runtime names its body instead', questions.at(-1).details[1].lines[1] === Nino.adminUi.format( text('/_admin/routes/confirm/stays-body'), '[template /templates/page-[[/nino/http/response/locale]]]' ) );

questionAnswer = true;
toasts.length = 0;
fire( deleteButton(), 'click' );
check( 'a confirmed question deletes the route, by its Element URI', requests.at(-1).action === 'routes/delete' && requests.at(-1).payload.uri === '/site-about' && status().dataset.state === 'idle' );
questionAnswer = false;
touched.length = 0; touchedUris.length = 0;
answer( 200, { pages : listing( [ '/', '/contact' ] ).pages } );
check( '...and a delete that went through tells the other panels that routes were written, with no address: the page is gone', touched.join() === 'routes' && touchedUris.length === 1 && touchedUris[0] === undefined );
check( '...says it was deleted as a toast, and goes back to the list, which has lost the page, at its address', toasts.length === 1 && toasts[0] === text('/_admin/common/msg/deleted') && mount.classList.contains('admin-hidden') === false
	&& form.classList.contains('admin-hidden') === true && rowLabels().join() === '/,/contact' && written.at(-1) === '["routes"]' );
check( '...with the focus on a row that is left - the one after the page that went, here the one that was before it', active === rowLink( rowOf( '/contact' ) ) || active === rowLink( rowOf( '/' ) ) );

/*	A delete the server refuses is said in the line of the form, and the form stays	*/
panel._pages = listing( [ '/', '/contact' ] ).pages;
panel._renderList();
fire( rowLink( rowOf( '/contact' ) ), 'click' );
questionAnswer = true;
fire( deleteButton(), 'click' );
questionAnswer = false;
answer( 409, { error : 'locked' } );
check( 'a refused delete is said in the line of the bar, as an alert, and the form stays open', status().dataset.state === 'error' && status().textContent === '(409) locked' && form.classList.contains('admin-hidden') === false );
fire( backLink(), 'click' );

/*	The address a window beside the workbench goes to: that of the language the panel edits,
	else the one of every language, else the first there is - always a path with its slash	*/
const selectedBefore = panel._selectedLocale;
panel._selectedLocale = 'en_US';
check( 'the address of a page is the one of the language the panel is working in', panel._publicPath( { de_DE : '/ueber-uns', en_US : '/about' } ) === '/about' );
check( '...else the one of every language', panel._publicPath( { de_DE : '/ueber-uns', '' : '/shared' } ) === '/shared' );
check( '...else the first there is, an empty one passed over', panel._publicPath( { de_DE : '  ', fr_FR : '/accueil' } ) === '/accueil' );
check( '...with the slash it lacks, and none for a page that has no address', panel._publicPath( { en_US : ' about ' } ) === '/about' && panel._publicPath( {} ) === '' && panel._publicPath( { en_US : '' } ) === '' );
panel._selectedLocale = selectedBefore;

// The list names its pages in the language the workbench edits content in, where the website serves it: the one source, not the answer the server gave when the list was read
const sourceBefore = workLocale.current;
panel._selectedLocale = 'de_DE';
workLocale.current = 'en_US';
check( 'the address and the name on the list are the workbench\'s language where the website has it', panel._language() === 'en_US' && panel._publicPath( { de_DE : '/ueber-uns', en_US : '/about' } ) === '/about' );
workLocale.current = 'it_IT';
check( '...and the one the server named where it has not - a language the website does not serve yet', panel._language() === 'de_DE' && panel._publicPath( { de_DE : '/ueber-uns', en_US : '/about' } ) === '/ueber-uns' );
workLocale.current = sourceBefore;

// A route that picks its template at runtime has no template to choose: the
// disabled select is not a missing field, and the reason it is locked is behind its (?)
const runtimeRoute = listing( [ '/legal' ], { templates : [ 'page-about' ] } );
runtimeRoute.pages[0].template = '';
runtimeRoute.pages[0].body = '[template /templates/page-legal.[[/nino/http/response/locale]]]';
runtimeRoute.pages[0].text.de_DE.name = 'Rechtliches';
panel._pages = runtimeRoute.pages;
panel._renderList();
fire( rowLink( rowOf( '/legal' ) ), 'click' );
check( 'a route whose body decides its template has the select locked on an option of its own, and the reason behind the (?) at the select', fieldNamed('template').disabled === true && fieldNamed('template').value === ''
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-help-text') && el.textContent.includes('page-legal.[[/nino/http/response/locale]]') } ).length === 1 );
const sentBefore = requests.length;
[ 'de_DE', 'en_US' ].forEach( function( locale ) { rowControl( 'name', locale ).value = 'R'; rowControl( 'title', locale ).value = 'T' } );
save();
check( 'a route with a runtime body saves without choosing a template', requests.length === sentBefore + 1 && requests[sentBefore].payload.template === '' && requests[sentBefore].payload.originalUri === '/legal' );
answer( 200, { pages : runtimeRoute.pages } );
fire( backLink(), 'click' );
panel._pages = listing( [ '/', '/contact' ] ).pages;
panel._renderList();

/*	A page has one address for every language, or one for each. The switch
	says which: the form draws the one field, or a row per language named by
	its language, and what the fields hold is what is sent. The list names every
	address a page has, and a move or a delete names the page by its Element URI	*/
function oneBox() {
	return oneSwitch();
}
function turnSwitch( on ) {
	oneBox().checked = on;
	fire( oneBox(), 'change' );
}
/** What names an address row: the badge of the language in the row of the control */
function addressName( locale ) {
	return addressOf( locale ).parentNode.parentNode.children[0].textContent;
}
function fillTexts() {
	[ 'de_DE', 'en_US' ].forEach( function( locale ) { rowControl( 'name', locale ).value = 'N'; rowControl( 'title', locale ).value = 'T' } );
}
function openPath( path ) {
	fire( backLink(), 'click' );
	fire( rowLink( rowOf( path ) ), 'click' );
}

const addressed = listing( [ '/', '/about', '/contact' ], { templates : [ 'page-about', 'page-blank', 'page-contact' ], defaultTemplate : 'page-blank', localeNames : { de_DE : 'Deutsch', en_US : 'English' } } );
addressed.pages[1].httpUri = '/ueber-uns';
addressed.pages[1].httpUris = { de_DE : '/ueber-uns', en_US : '/about' };
addressed.pages[2].httpUri = '/kontakt';
addressed.pages[2].httpUris = { '' : '/contact', de_DE : '/kontakt' };
panel.init();
answer( 200, addressed );

check( 'a page with an address per language is one row, and the row names every address - the one several languages share too', rowLabels().join(' | ') === '/ | /ueber-uns, /about | /contact, /kontakt' );
check( '...and the link out goes to the address the server gave the page as its own', openLink( '/ueber-uns, /about' ).href === '/site/ueber-uns' && openLink( '/contact, /kontakt' ).href === '/site/kontakt' );

fire( moveButton( '/ueber-uns, /about', 'down' ), 'click' );
check( 'a move names the page by its Element URI and not by one of its addresses', JSON.stringify( requests.at(-1).payload ) === '{"uri":"/about","direction":"down"}' );
answer( 200, { pages : addressed.pages } );
touched.length = 0; touchedUris.length = 0;

fire( rowLink( rowOf( '/' ) ), 'click' );
check( 'a page with one address for every language opens with the switch on and the one field, named as the address and not drawn as a group', oneBox().checked === true && addressControls().length === 1 && addressControls()[0].dataset.address === ''
	&& addressControls()[0].value === '/' && addressControls()[0].dataset.field === 'httpUri' && field('routes-form-addresses').children[0].tagName === 'LABEL' );

const switchCopy = findAll( form, function( el ) { return el.className === 'nino-admin-switch-copy' } )[0];
const switchSentence = findAll( switchCopy, function( el ) { return el.className === 'nino-admin-help-text' } )[0];
check( 'the switch says what it is for in the words of the panel, and what it does behind its (?)', findAll( switchCopy, function( el ) { return el.className === 'nino-admin-field-text' } )[0].children[0].textContent === text('/_admin/routes/label/one-address')
	&& switchSentence !== undefined && switchSentence.textContent === text('/_admin/routes/hint/one-address') && oneBox().attributes['aria-describedby'] === switchSentence.id
	&& text('/_admin/routes/label/one-address') !== '' && text('/_admin/routes/hint/one-address') !== '' );

turnSwitch( false );
check( 'turned off on a page that has one address for every language, every language starts from it, one row each', addressControls().map( function( el ) { return el.dataset.address } ).join() === 'de_DE,en_US'
	&& addressOf( 'de_DE' ).value === '/' && addressOf( 'en_US' ).value === '/' );

openPath( '/ueber-uns, /about' );
check( 'a page with an address per language opens with the switch off and a row for each language, in the order of the languages', oneBox().checked === false
	&& addressControls().map( function( el ) { return el.dataset.address } ).join() === 'de_DE,en_US' );
check( '...each named by its language and not by its code, and holding that language\'s own address', addressName( 'de_DE' ) === 'Deutsch' && addressName( 'en_US' ) === 'English'
	&& addressOf( 'de_DE' ).value === '/ueber-uns' && addressOf( 'en_US' ).value === '/about' );
check( '...each named for the server by the field it marks when it refuses that language\'s address', addressControls().every( function( el ) { return el.dataset.field === 'httpUri-'+ el.dataset.address } ) );
check( '...in a required group that the address names', ( function() {
	const group = addressOf( 'de_DE' ).parentNode.parentNode.parentNode;
	return group.attributes.role === 'group' && group.attributes['aria-required'] === 'true';
} )() );

openPath( '/contact, /kontakt' );
check( 'a page with an address for every language and one of its own for a language shows that one in the row of the language, and the shared one in the others', oneBox().checked === false
	&& addressOf( 'de_DE' ).value === '/kontakt' && addressOf( 'en_US' ).value === '/contact' );

fire( backLink(), 'click' );
panel._open( { kind : 'page', uri : '/about', entry : Object.assign( {}, addressed.pages[1], { httpUris : { de_DE : '/ueber-uns' } } ) }, true );
check( 'a language the page has no address for starts empty, to be filled in', addressOf( 'de_DE' ).value === '/ueber-uns' && addressOf( 'en_US' ).value === '' );

openPath( '/ueber-uns, /about' );
turnSwitch( true );
check( 'turned to one address for every language, the rows of the languages give way to the one field', addressControls().length === 1 && addressControls()[0].dataset.address === '' && addressControls()[0].dataset.field === 'httpUri' );
check( '...which starts from the address of the first language that has one', field('routes-form-http-uri').value === '/ueber-uns' );
field('routes-form-http-uri').value = '/everywhere';
turnSwitch( false );
check( 'turned off again, the languages hold what they held: nothing typed is lost between two turns', addressOf( 'de_DE' ).value === '/ueber-uns' && addressOf( 'en_US' ).value === '/about' );
addressOf( 'en_US' ).value = '/en-about';
turnSwitch( true );
check( '...and the one field shows again what was typed into it before', field('routes-form-http-uri').value === '/everywhere' );
turnSwitch( false );
check( '...and so does a language what was typed into its row', addressOf( 'en_US' ).value === '/en-about' && addressOf( 'de_DE' ).value === '/ueber-uns' );

// A new page, with an address for each language: the map is what is sent
fire( backLink(), 'click' );
fire( newButton(), 'click' );
check( 'a new page starts with the switch on and the one field empty', oneBox().checked === true && addressControls().length === 1 && addressControls()[0].dataset.address === '' && addressControls()[0].value === '' );
fieldNamed('uri').value = '/trip';
field('routes-form-http-uri').value = '/trip';
fillTexts();
turnSwitch( false );
check( 'turned off, every language starts from what the one field held', addressOf( 'de_DE' ).value === '/trip' && addressOf( 'en_US' ).value === '/trip' );
addressOf( 'en_US' ).value = '/reise';
const beforeMap = requests.length;
save();
check( 'a page with an address per language is sent as a map of language to address', requests.length === beforeMap + 1 && requests[beforeMap].action === 'routes/save'
	&& JSON.stringify( requests[beforeMap].payload.httpUris ) === '{"de_DE":"/trip","en_US":"/reise"}' );
check( '...beside the Element URI, the one the page had - none, for a new page - and none of the keys of the shape before', Object.keys( requests[beforeMap].payload ).sort().join() === 'httpUris,navs,originalUri,statusCode,template,text,uri'
	&& requests[beforeMap].payload.originalUri === '' && requests[beforeMap].payload.uri === '/trip' );

answer( 400, { error : 'missing', code : 'routes_missing_http_uri', params : [ 'en_US' ], field : 'httpUri-en_US' } );
check( 'a refusal that names the address of a language marks that row and puts the focus on it, and no other', addressOf( 'en_US' ).attributes['aria-invalid'] === 'true' && active === addressOf( 'en_US' )
	&& addressOf( 'de_DE' ).attributes['aria-invalid'] === undefined && sentences().length === 1 );
check( '...and the line of the form tells it in the words of the code, with the language written as the workbench writes it', text('/_admin/error/routes_missing_http_uri') !== ''
	&& status().textContent === Nino.adminUi.format( text('/_admin/error/routes_missing_http_uri'), 'English' ) );

turnSwitch( true );
const beforeOne = requests.length;
save();
check( 'with the switch on the same page is sent as the one address of every language', requests.length === beforeOne + 1 && JSON.stringify( requests[beforeOne].payload.httpUris ) === '{"":"/trip"}' );
answer( 409, { error : 'taken', code : 'routes_http_uri_taken', params : [ '/trip' ], field : 'httpUri' } );
check( 'a refusal that names the address of every language marks the one field', field('routes-form-http-uri').attributes['aria-invalid'] === 'true' && active === field('routes-form-http-uri') );

/*	A request gives an address once. Fields of languages that hold the same address are the address of those languages together - that of
	every language with none of its own, which the server writes as the one route without a language - and are sent as that, the empty
	key. The server's refusal of that address is for the first of those fields: no field of the form is called for every language	*/
fire( backLink(), 'click' );
fire( newButton(), 'click' );
fieldNamed('uri').value = '/trip';
field('routes-form-http-uri').value = '/trip';
fillTexts();
turnSwitch( false );
const beforeFold = requests.length;
save();
check( 'rows per language that hold one address are sent as the address of every language, once - what the switch turned on would send', requests.length === beforeFold + 1
	&& JSON.stringify( requests[beforeFold].payload.httpUris ) === '{"":"/trip"}' );
answer( 400, { error : 'taken', code : 'routes_duplicate_http_uri', params : [ '/trip' ], field : 'httpUri' } );
check( 'a refusal of that address marks and focuses the first of the rows it was sent for, and no other', addressOf( 'de_DE' ).attributes['aria-invalid'] === 'true' && active === addressOf( 'de_DE' )
	&& addressOf( 'en_US' ).attributes['aria-invalid'] === undefined );

// A page with an address for one language and one for the others, opened with the switch off, is saved as it is
const threeLanguages = [ 'de_DE', 'en_US', 'fr_FR' ];
panel._locales = threeLanguages;
Nino.adminUi.locales.names.fr_FR = 'Français';
fire( backLink(), 'click' );
panel._open( { kind : 'page', uri : '/trip', entry : { uri : '/trip', httpUri : '/reise', httpUris : { de_DE : '/reise', '' : '/trip' }, template : 'page-about', body : '[template /templates/page-about]', text : {} } }, true );
check( 'a page that has an address of its own for a language and one for the others shows the one for the others in each of their rows', oneBox().checked === false
	&& addressOf( 'de_DE' ).value === '/reise' && addressOf( 'en_US' ).value === '/trip' && addressOf( 'fr_FR' ).value === '/trip' );
threeLanguages.forEach( function( locale ) { rowControl( 'name', locale ).value = 'N'; rowControl( 'title', locale ).value = 'T' } );
const beforeThree = requests.length;
save();
check( '...and saved unchanged it sends the shared address as the empty key and the language of its own as itself: the same page', requests.length === beforeThree + 1
	&& JSON.stringify( requests[beforeThree].payload.httpUris ) === '{"":"/trip","de_DE":"/reise"}' && requests[beforeThree].payload.originalUri === '/trip' );
answer( 200, { pages : addressed.pages } );

// Two pairs of languages that each hold an address: one is the address of its languages, the other cannot say so, and the server refuses it for its later field
const fourLanguages = [ 'de_DE', 'de_AT', 'en_US', 'en_GB' ];
panel._locales = fourLanguages;
Object.assign( Nino.adminUi.locales.names, { de_AT : 'Österreich', en_GB : 'Britain' } );
fire( backLink(), 'click' );
fire( newButton(), 'click' );
fieldNamed('uri').value = '/pairs';
field('routes-form-http-uri').value = '/kontakt';
fourLanguages.forEach( function( locale ) { rowControl( 'name', locale ).value = 'N'; rowControl( 'title', locale ).value = 'T' } );
turnSwitch( false );
addressOf( 'en_US' ).value = '/contact';
addressOf( 'en_GB' ).value = ' /contact ';
const beforePairs = requests.length;
save();
check( 'two pairs of rows that each hold one address: the first is sent as the empty key, the second as it stands', requests.length === beforePairs + 1
	&& JSON.stringify( requests[beforePairs].payload.httpUris ) === '{"":"/kontakt","en_US":"/contact","en_GB":" /contact "}' );
answer( 400, { error : 'twice', code : 'routes_duplicate_http_uri', params : [ '/contact' ], field : 'httpUri-en_GB' } );
check( '...and the refusal, which names the later of the second pair, marks and focuses that row', addressOf( 'en_GB' ).attributes['aria-invalid'] === 'true' && active === addressOf( 'en_GB' )
	&& [ 'de_DE', 'de_AT', 'en_US' ].every( function( locale ) { return addressOf( locale ).attributes['aria-invalid'] === undefined } ) );
panel._locales = [ 'de_DE', 'en_US' ];
fire( backLink(), 'click' );

// The map a save sends, by itself
const fieldsOf = function( pairs ) { return pairs.map( function( pair ) { return { locale : pair[0], value : pair[1] } } ) };
const mapOf = function( pairs ) { return JSON.stringify( panel._addressMap( fieldsOf( pairs ) ) ) };
check( 'fields that hold different addresses are sent as they are, and the field of every language as the empty key', mapOf( [ [ 'de_DE', '/a' ], [ 'en_US', '/b' ] ] ) === '{"httpUris":{"de_DE":"/a","en_US":"/b"},"folded":[]}'
	&& mapOf( [ [ '', '/a' ] ] ) === '{"httpUris":{"":"/a"},"folded":[]}' );
check( '...fields that hold one address are folded into the empty key, in the order they stand in, with the languages that were', mapOf( [ [ 'de_DE', '/a' ], [ 'en_US', '/b' ], [ 'fr_FR', '/b' ] ] ) === '{"httpUris":{"":"/b","de_DE":"/a"},"folded":["en_US","fr_FR"]}' );
check( '...a blank around an address is none of it', mapOf( [ [ 'de_DE', ' /a' ], [ 'en_US', '/a ' ] ] ) === '{"httpUris":{"":"/a"},"folded":["de_DE","en_US"]}' );
check( '...nor is the slash the server adds in front or drops behind: a, /a and /a/ are one address, sent as the server reads it', mapOf( [ [ 'de_DE', 'a' ], [ 'en_US', '/a/' ], [ 'fr_FR', '/a' ] ] ) === '{"httpUris":{"":"/a"},"folded":["de_DE","en_US","fr_FR"]}'
	&& mapOf( [ [ 'de_DE', '/' ], [ 'en_US', '/' ] ] ) === '{"httpUris":{"":"/"},"folded":["de_DE","en_US"]}' );
check( '...fields that are empty are not an address they share', mapOf( [ [ 'de_DE', '' ], [ 'en_US', '' ] ] ) === '{"httpUris":{"de_DE":"","en_US":""},"folded":[]}' );
check( '...and only one address is folded, the first one that two fields hold: the rest stand as they are for the server to refuse', mapOf( [ [ 'de_DE', '/a' ], [ 'de_AT', '/b' ], [ 'en_US', '/b' ], [ 'en_GB', '/a' ] ] )
	=== '{"httpUris":{"":"/a","de_AT":"/b","en_US":"/b"},"folded":["de_DE","en_GB"]}' );
const refusedHttpUri = { error : 'taken', code : 'routes_http_uri_taken', params : [ '/a' ], field : 'httpUri' };
check( 'a refusal naming the empty key\'s field is made the first of the folded fields', JSON.stringify( panel._refusedField( refusedHttpUri, [ 'en_US', 'fr_FR' ] ) ) === JSON.stringify( Object.assign( {}, refusedHttpUri, { field : 'httpUri-en_US' } ) ) && refusedHttpUri.field === 'httpUri' );
check( '...and stays as it is where nothing was folded, names another field, or is no answer', panel._refusedField( refusedHttpUri, [] ) === refusedHttpUri && panel._refusedField( null, [ 'en_US' ] ) === null
	&& panel._refusedField( { field : 'httpUri-fr_FR' }, [ 'en_US' ] ).field === 'httpUri-fr_FR' );

// Every address row is a required one: marked, told, and the first of them takes the focus once the Element URI is there
fire( newButton(), 'click' );
turnSwitch( false );
focused.length = 0;
save();
check( 'a row per language that is empty is marked, each with a sentence of its own', addressControls().length === 2 && addressControls().every( function( el ) {
	const sentence = sentences().find( function( each ) { return el.attributes['aria-describedby'].split(' ').indexOf( each.id ) !== -1 } );
	return el.attributes['aria-invalid'] === 'true' && sentence !== undefined && sentence.textContent.length > 0;
} ) && new Set( addressControls().map( function( el ) { return el.attributes['aria-describedby'] } ) ).size === 2 );
check( '...and the Element URI, which stands first, has the focus', focused.length === 1 && focused[0] === fieldNamed('uri') );

fieldNamed('uri').value = '/x';
fillTexts();
focused.length = 0;
save();
check( 'once that is filled the focus goes to the first address that is missing', focused.length === 1 && focused[0] === addressOf( 'de_DE' ) && fieldNamed('uri').attributes['aria-invalid'] === undefined );
addressOf( 'de_DE' ).value = '/x';
focused.length = 0;
save();
check( 'only the address that is still missing stays marked, with the focus', addressOf( 'de_DE' ).attributes['aria-invalid'] === undefined && addressOf( 'en_US' ).attributes['aria-invalid'] === 'true'
	&& focused.length === 1 && focused[0] === addressOf( 'en_US' ) );

// A page with several addresses is deleted whole, and the question names them all
openPath( '/ueber-uns, /about' );
questions.length = 0;
fire( deleteButton(), 'click' );
check( 'the delete question names every address of the page, a line each', questions.length === 1 && questions[0].details[0].lines.join() === '/ueber-uns,/about' );
questionAnswer = true;
fire( deleteButton(), 'click' );
check( 'a confirmed question deletes the page by its Element URI - one request, the routes of every language with it', requests.at(-1).action === 'routes/delete'
	&& JSON.stringify( requests.at(-1).payload ) === '{"uri":"/about"}' );
questionAnswer = false;
answer( 200, { pages : listing( [ '/', '/contact' ] ).pages } );


/*	The pages a feature routes at runtime: listed under the persisted ones, with
	no buttons and no way to delete, and a form of the path - read only - and the
	three texts per language, saved as routes/savetexts. A project with none
	draws nothing for them	*/
const featurePages = listing( [ '/', '/contact' ], { templates : [ 'page-contact' ], runtime : [
	{ uri : '/blog/post', httpUri : '/blog/*', httpUris : [ '/blog/*' ], body : '[template /templates/page-post]', text : { de_DE : { name : 'Beitrag', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } },
	{ uri : '/newsletter', httpUri : '/.newsletter', httpUris : [ '/.newsletter' ], body : '[template /templates/page-newsletter]', text : { de_DE : { name : '', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } },
] } );
panel.init();
answer( 200, Object.assign( {}, featurePages, { runtime : [] } ) );
check( 'with no feature route nothing is drawn for them: no heading, no second list', findAll( mount, function( el ) { return el.classList.contains('nino-admin-section-title') } ).length === 0 && rows().length === 2 );

panel.init();
answer( 200, featurePages );
const runtimeTitle = findAll( mount, function( el ) { return el.classList.contains('nino-admin-section-title') } );
check( 'feature routes have a title of their own, with the sentence that says what they are behind a (?)', runtimeTitle.length === 1 && runtimeTitle[0].textContent === text('/_admin/routes/runtime/title') && text('/_admin/routes/runtime/title') !== ''
	&& findAll( runtimeTitle[0], function( el ) { return el.classList.contains('nino-admin-help-text') && el.textContent === text('/_admin/routes/runtime/help') } ).length === 1 && text('/_admin/routes/runtime/help') !== ''
	&& findAll( mount, function( el ) { return el.classList.contains('nino-admin-hint') } ).length === 0 );
const featureRows = rows().slice( 2 );
check( 'they stand below the pages, a row each, named by what was written and by the path', rows().length === 4 && rowPath( featureRows[0] ) === '/blog/*' && rowName( featureRows[0] ) === 'Beitrag' && rowName( featureRows[1] ) === '/.newsletter' );
check( '...without buttons and without a link out: none of them is the project\'s to move, and a placeholder is no page', featureRows.every( function( li ) { return findAll( li, function( el ) { return el.classList.contains('nino-admin-row-actions') } ).length === 0 } ) );
check( '...a row opens the form at an address of its own: #routes/feature/<Element-URI>', rowLink( featureRows[1] ).href === '#routes/feature/%2Fnewsletter' );

fire( rowLink( featureRows[1] ), 'click' );
check( 'a feature route opens a form of the path, which is read only, at its address', field('routes-form-runtime-path').value === '/.newsletter' && field('routes-form-runtime-path').readOnly === true
	&& written.at(-1) === '["routes","feature","/newsletter"]' && fieldNamed('uri') === undefined && fieldNamed('template') === undefined );
check( '...with a name, a title and a description per language, the description of the language of the workbench', [ 'de_DE', 'en_US' ].every( function( locale ) { return rowControl( 'name', locale ) !== undefined && rowControl( 'title', locale ) !== undefined } ) && fieldNamed('description') !== undefined );
check( '...and no navigations, no status code and no delete', navTicks().length === 0 && fieldNamed('statusCode') === undefined && deleteButton() === undefined );
fire( theForm(), 'input', { target : rowControl( 'name', 'de_DE' ) } );
check( '...whose line follows an edit too: unsaved', status().dataset.state === 'dirty' );

const beforeRuntime = requests.length;
save();
check( 'a name and a title are required in every language here as well', requests.length === beforeRuntime && rowControl( 'name', 'de_DE' ).attributes['aria-invalid'] === 'true' && rowControl( 'title', 'en_US' ).attributes['aria-invalid'] === 'true'
	&& status().textContent === text('/_admin/routes/error/fields') );
[ 'de_DE', 'en_US' ].forEach( function( locale ) { rowControl( 'name', locale ).value = 'Newsletter'; rowControl( 'title', locale ).value = 'Abonnieren' } );
fieldNamed('description').value = 'Alle drei Monate.';
save();
check( 'a complete form is sent as routes/savetexts for the Element-URI, and only that', requests.length === beforeRuntime + 1 && requests[beforeRuntime].action === 'routes/savetexts'
	&& requests[beforeRuntime].payload.uri === '/newsletter' && Object.keys( requests[beforeRuntime].payload ).sort().join() === 'text,uri'
	&& requests[beforeRuntime].payload.text.de_DE.description === 'Alle drei Monate.' && requests[beforeRuntime].payload.text.en_US.title === 'Abonnieren' );
touched.length = 0; touchedUris.length = 0; toasts.length = 0;
answer( 200, { runtime : featurePages.runtime } );
check( 'the texts of a feature route are written like a page: the other panels are told, with the address the feature routes it at', touched.join() === 'routes' && touchedUris.join() === '/.newsletter' );
check( '...a toast says so, and the person stays in the form, at its address', toasts.length === 1 && form.classList.contains('admin-hidden') === false && written.at(-1) === '["routes","feature","/newsletter"]' && status().textContent === '' );

// A page a feature routes by a pattern is no address a window can go to: saved, it is written and says no page
fire( backLink(), 'click' );
fire( rowLink( rows().slice( 2 )[0] ), 'click' );
[ 'de_DE', 'en_US' ].forEach( function( locale ) { rowControl( 'name', locale ).value = 'B'; rowControl( 'title', locale ).value = 'T' } );
touched.length = 0; touchedUris.length = 0;
save();
answer( 200, { runtime : featurePages.runtime } );
check( 'a feature page routed by a pattern is saved and names no page: /blog/* is no address', touched.join() === 'routes' && touchedUris.length === 1 && ! touchedUris[0] );
fire( backLink(), 'click' );

// After a feature's form the next page opened is a page again: the form of a persisted route saves as routes/save
fire( rowLink( rowOf( '/contact' ) ), 'click' );
check( 'the form of a persisted route is its own again - Element URI, template and all', fieldNamed('uri') !== undefined && field('routes-form-runtime-path') === undefined );
const beforePage = requests.length;
fillTexts();
save();
check( '...and is saved as routes/save', requests.length === beforePage + 1 && requests[beforePage].action === 'routes/save' );
toasts.length = 0;
answer( 200, { pages : listing( [ '/', '/contact' ] ).pages } );
check( 'a route that was saved again says so as a toast and the person stays in its form, at its address, with a line that says nothing', toasts.length === 1 && toasts[0] === text('/_admin/common/msg/saved')
	&& form.classList.contains('admin-hidden') === false && written.at(-1) === '["routes","/contact"]' && status().textContent === '' && status().dataset.state === 'saved' && deleteButton() !== undefined );
fire( backLink(), 'click' );

// A page of a module that has a route per language - the imprint of the Legal
// module - is one row, shown with every path it is reached at, and its form says the same
panel.init();
answer( 200, listing( [ '/', '/contact' ], { runtime : [ { uri : '/legal/imprint', httpUri : '/impressum', httpUris : [ '/impressum', '/imprint' ], body : '[template /templates/page-legal-imprint]',
	text : { de_DE : { name : 'Impressum', title : 'Impressum', description : '' }, en_US : { name : 'Imprint', title : 'Imprint', description : '' } } } ] } ) );
const perLanguageRows = rows().slice( 2 );
check( 'a page with a route per language is one row, named by what was written and shown with all its paths', perLanguageRows.length === 1 && rowName( perLanguageRows[0] ) === 'Impressum' && rowPath( perLanguageRows[0] ) === '/impressum, /imprint' );
fire( rowLink( perLanguageRows[0] ), 'click' );
check( '...and its form shows them read only, in one field', field('routes-form-runtime-path').value === '/impressum, /imprint' && field('routes-form-runtime-path').readOnly === true );
fire( backLink(), 'click' );
panel.init();
answer( 200, listing( [ '/', '/contact' ], { runtime : [ { uri : '/.newsletter', httpUri : '/.newsletter', body : '', text : { de_DE : { name : '', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } } ] } ) );
check( 'a page without a list of paths - an older server\'s answer - shows its one path', rowPath( rows().slice( 2 )[0] ) === '/.newsletter' );

/*	Every view has an address which a reload keeps: the panel opens the level the address names, and one it does not hold is the list	*/
function loadAt( parts, extra ) {
	hash = { panel : 'routes', parts : parts };
	panel._ready = false;
	panel.init();
	answer( 200, listing( [ '/', '/about', '/contact' ], Object.assign( { templates : [ 'page-about', 'page-blank', 'page-contact' ], defaultTemplate : 'page-blank',
		runtime : [ { uri : '/newsletter', httpUri : '/.newsletter', httpUris : [ '/.newsletter' ], body : '', text : { de_DE : { name : 'Brief', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } } ] }, extra || {} ) ) );
}
loadAt( [ '@new' ] );
check( 'a fresh load on #routes/@new opens the form of a new route', form.classList.contains('admin-hidden') === false && mount.classList.contains('admin-hidden') === true && fieldNamed('uri') !== undefined && fieldNamed('uri').value === '' && deleteButton() === undefined );
check( '...without moving the focus the person did not put there', written.at(-1) === '["routes","@new"]' && inside( form, active ) === false );
loadAt( [ '/about' ] );
check( 'a fresh load on the address of a route opens its form', fieldNamed('uri') !== undefined && fieldNamed('uri').value === '/about' && deleteButton() !== undefined );
loadAt( [ 'feature', '/newsletter' ] );
check( 'a fresh load on the address of a page a feature routes opens its form', field('routes-form-runtime-path') !== undefined && field('routes-form-runtime-path').value === '/.newsletter' );
loadAt( [ '/nothing-there' ] );
check( 'an address that names no route is the list', mount.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true && written.at(-1) === '["routes"]' );
loadAt( [ 'feature', '/about' ] );
check( '...and so does the address of a route as a page of a feature', mount.classList.contains('admin-hidden') === false );

/*	A step through the browser's history changes the address and nothing else: the panel follows it, and asks about unsaved input first (the router's leave())	*/
loadAt( [] );
hash = { panel : 'routes', parts : [ '/about' ] };
leaves.length = 0;
panel.showCurrent();
check( 'a showing with an address that names another level asks the router to leave this one, and moves when it is let go', leaves.length === 1 && leaves[0].open === false && mount.classList.contains('admin-hidden') === false );
leaves[0].proceed();
check( '...to the form of the route', form.classList.contains('admin-hidden') === false && fieldNamed('uri').value === '/about' );
leaves.length = 0;
panel.showCurrent();
check( 'a showing with an address that is the level on screen leaves nothing to ask', leaves.length === 0 && form.classList.contains('admin-hidden') === false );
hash = { panel : 'routes', parts : [] };
panel.showCurrent();
check( 'the address of the list while a form is open asks with the form as the one that would be left', leaves.length === 1 && leaves[0].open === true );
leaves[0].proceed();
check( '...and goes to the list', mount.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true && written.at(-1) === '["routes"]' );
hash = { panel : 'users', parts : [] };
fire( rowLink( rowOf( '/about' ) ), 'click' );
leaves.length = 0;
panel.showCurrent();
check( 'an address that names another panel leaves the level in memory as it is', leaves.length === 0 && form.classList.contains('admin-hidden') === false );
fire( backLink(), 'click' );
hash = { panel : 'routes', parts : [] };

/*	A page template written since the list was read - the Builder creates them -
	is one the form's select has to offer. The panel remembers the stamp of the
	templates it asked for and, when it is shown again, reads the list again if
	that moved: not while a form is open, which would be drawn over, and then at
	the showing that finds none	*/
panel._ready = false;
panel.init();
answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact' ] } ) );
const idle = requests.length;
panel.showCurrent();
check( 'shown again with no template written since, the panel asks for nothing', requests.length === idle && panel._stamp === 0 );

stamps.templates++;
fire( newButton(), 'click' );
panel.showCurrent();
check( 'a template written while a form is open is not read now: the form is not drawn over', requests.length === idle && form.classList.contains('admin-hidden') === false );

fire( backLink(), 'click' );
check( '...going back to the list is no showing of the panel, and asks for nothing either', requests.length === idle );
panel.showCurrent();
check( '...the showing that finds no form open reads the list again', requests.length === idle + 1 && requests[idle].action === 'routes/list' );
check( '...and until the answer is there nothing more is asked: a second showing does not start a second read', ( function() { panel.showCurrent(); return requests.length === idle + 1 } )() );

answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-new' ] } ) );
check( '...the template that was written is on offer in the form of a new page', ( function() {
	fire( newButton(), 'click' );
	return fieldNamed('template').children.map( function( option ) { return option.value } ).indexOf('page-new') !== -1;
} )() );
fire( backLink(), 'click' );
const settled = requests.length;
panel.showCurrent();
check( '...and with nothing written after that, the next showing asks for nothing', requests.length === settled && panel._stamp === 1 );

// A template written while the request is out is not in its answer
stamps.templates++;
panel.showCurrent();
const outRequests = requests.length;
stamps.templates++;
answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-new', 'page-newer' ] } ) );
check( 'the stamp is the one from before the request went out', panel._stamp === 2 );
panel.showCurrent();
check( '...so a template written while it was out is asked for at the next showing', requests.length === outRequests + 1 );
answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-new', 'page-newer' ] } ) );

/*	The navigations a page stands in are one list of ticks, each named by its name where it has one and by its key
	behind it, quietly; the keys that are ticked are what is sent. A project without the Navigation module has none	*/
panel.init();
answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact' ], navs : [ 'main', 'footer', 'legal' ], navNames : { main : 'Hauptmenü', footer : '' } } ) );
panel._pages[1].navs = [ 'footer' ];
fire( rowLink( rowOf( '/contact' ) ), 'click' );
const navGroup = findAll( form, function( el ) { return el.tagName === 'FIELDSET' && el.classList.contains('nino-admin-checklist-field') } )[0];
const navRows = findAll( navGroup, function( el ) { return el.tagName === 'LABEL' } );
check( 'the navigations are the ticks of one group, one for each the project has, the ones the page stands in ticked', navTicks().map( function( el ) { return el.value } ).join() === 'main,footer,legal' && navTicks().map( function( el ) { return el.checked } ).join() === 'false,true,false' );
check( '...a navigation with a name is told by the name and its key quietly beside it, one without by its key alone', navRows[0].children[1].textContent === 'Hauptmenü' && navRows[0].children[2]?.textContent === 'main'
	&& navRows[1].children[1].textContent === 'footer' && navRows[1].children.length === 2 && navRows[2].children.length === 2 );
check( '...the group has the name and the (?) of a field, and no tick stands by itself in the form', navGroup.children[0].tagName === 'LEGEND' && findAll( navGroup, function( el ) { return el.classList.contains('nino-admin-help-text') } ).length === 1 && findAll( form, function( el ) { return el.type === 'checkbox' && el.dataset.key !== 'navs' && el.dataset.key !== 'one-address' } ).length === 0 );
navTicks()[0].checked = true;
navTicks()[1].checked = false;
fillTexts();
save();
check( 'the keys that are ticked, in the order of the list, are what is sent', requests.at(-1).payload.navs.join() === 'main' );
answer( 200, { pages : panel._pages } );
fire( backLink(), 'click' );
panel.init();
answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact' ] } ) );
fire( rowLink( rowOf( '/contact' ) ), 'click' );
check( 'without a navigation - the Navigation module is off - the form has no such group, and sends none', navTicks().length === 0 && ( fillTexts(), save(), requests.at(-1).payload.navs.length === 0 ) );
answer( 200, { pages : panel._pages } );
fire( backLink(), 'click' );

/*	The description is the one of the language the workbench edits content in; the other languages keep theirs. The language is
	the shell's to move (Nino.admin.workLocale), and the form follows it - the shell has asked about unsaved input already	*/
panel.init();
const withDescriptions = listing( [ '/', '/contact' ], { templates : [ 'page-contact' ] } );
withDescriptions.pages[1].text.de_DE = { name : 'Kontakt', title : 'Kontakt', description : 'Schreib uns.' };
withDescriptions.pages[1].text.en_US = { name : 'Contact', title : 'Contact', description : 'Write to us.' };
answer( 200, withDescriptions );
workLocale.current = 'de_DE';
fire( rowLink( rowOf( '/contact' ) ), 'click' );
check( 'the form shows the description of the language of the workbench, and says nothing of the language while it is the native one', fieldNamed('description').value === 'Schreib uns.'
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-locale-badge') && el.tagName === 'SPAN' } ).length === 0 );
workLocale.current = 'en_US';
followers.find( function( follower ) { return follower.name === 'routes' } ).handler( 'en_US' );
check( 'it follows the language when the workbench moves it: the form shows the description of the new one, with the badge of the language in its bar', fieldNamed('description').value === 'Write to us.'
	&& findAll( form, function( el ) { return el.classList.contains('nino-admin-locale-badge') && el.tagName === 'SPAN' } ).length === 1 && snapshots.at(-1) === 'routes' );
fieldNamed('description').value = 'Write to us now.';
save();
check( 'a save sends the description of that language as it stands and the other one as it was', requests.at(-1).payload.text.en_US.description === 'Write to us now.' && requests.at(-1).payload.text.de_DE.description === 'Schreib uns.' );
answer( 200, { pages : withDescriptions.pages.map( function( entry, at ) { return at === 1 ? Object.assign( {}, entry, { text : Object.assign( {}, entry.text, { en_US : Object.assign( {}, entry.text.en_US, { description : 'Write to us now.' } ) } ) } ) : entry } ) } );
workLocale.current = 'de_DE';
followers.find( function( follower ) { return follower.name === 'routes' } ).handler( 'de_DE' );
check( 'what was saved is what the form shows when the language moves on, in both languages', fieldNamed('description').value === 'Schreib uns.' );
workLocale.current = 'en_US';
followers.find( function( follower ) { return follower.name === 'routes' } ).handler( 'en_US' );
check( '...and the other language shows what was saved there', fieldNamed('description').value === 'Write to us now.' );
workLocale.current = 'de_DE';
followers.find( function( follower ) { return follower.name === 'routes' } ).handler( 'de_DE' );
fire( backLink(), 'click' );

/*	The shell asks before it throws the input of the open form away: the form is registered with it under the panel's name,
	and its Save is the form's own, which ends in done( ok ) every way it can end	*/
check( 'the form registers with the shell under the name of the panel, as the form the panel has drawn', watched.length === 1 && watched[0].name === 'routes' && watched[0].getter() === form );
fire( rowLink( rowOf( '/contact' ) ), 'click' );
rowControl( 'name', 'de_DE' ).value = '';
const outcomes = [];
const beforeShellSave = requests.length;
watched[0].save( function( ok ) { outcomes.push( ok ) } );
check( 'the shell\'s Save of a form that is not complete reports false and sends nothing', outcomes.join() === 'false' && requests.length === beforeShellSave );
rowControl( 'name', 'de_DE' ).value = 'Kontakt';
watched[0].save( function( ok ) { outcomes.push( ok ) } );
answer( 500, null );
check( '...one the server refuses reports false', outcomes.join() === 'false,false' && status().dataset.state === 'error' );
watched[0].save( function( ok ) { outcomes.push( ok ) } );
answer( 200, { pages : withDescriptions.pages } );
check( '...and one that is written reports true', outcomes.join() === 'false,false,true' );
fire( backLink(), 'click' );


/*	A route whose Element URI is "new" (written by hand, no slash) keeps an address of its own: the one of a new route has an at sign,
	which no Element URI has	*/
loadAt( [ 'new' ], { pages : [ page( 'new' ), page( '/contact' ) ] } );
check( 'a route called new has its own address and a fresh load on it opens that route, not the form of a new one', rowLink( rows()[0] ).href === '#routes/new' && fieldNamed('uri').value === 'new' && deleteButton() !== undefined );

/*	An Element URI that is changed is the address of the form from then on, and the next save names the page by it	*/
loadAt( [ '/contact' ] );
fieldNamed('uri').value = '/kontakt-neu';
fillTexts();
save();
const renamedPages = listing( [ '/', '/about', '/kontakt-neu' ], { templates : [ 'page-about', 'page-blank', 'page-contact' ] } ).pages;
renamedPages[2].template = 'page-contact';
answer( 200, { pages : renamedPages } );
check( 'a route whose Element URI was changed stays open at the new address, and a second save names the page by it', written.at(-1) === '["routes","/kontakt-neu"]' && ( save(), requests.at(-1).payload.originalUri === '/kontakt-neu' ) );
answer( 200, { pages : renamedPages } );
fire( backLink(), 'click' );

/*	The navigations are read again when the Navigations panel wrote them, as the templates are: the ticks are the ones that are there	*/
hash = { panel : 'routes', parts : [] };
panel._ready = false;
panel.init();
answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact' ] } ) );
const quiet = requests.length;
panel.showCurrent();
check( 'shown again with no navigation written since, the panel asks for nothing', requests.length === quiet );
stamps.navigations = ( stamps.navigations ?? 0 ) + 1;
panel.showCurrent();
check( 'a navigation written since the list was read is read again at the next showing, where no form is open', requests.length === quiet + 1 && requests.at(-1).action === 'routes/list' );
answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact' ], navs : [ 'recht' ], navNames : { recht : 'Rechtliches' } } ) );
check( '...and the form offers the navigations as they are now, by their new names', ( fire( rowLink( rowOf( '/contact' ) ), 'click' ), navTicks().map( function( el ) { return el.value } ).join() === 'recht' ) );

/*	A tick for a navigation that is gone is refused by the server and said at the group of ticks, not dropped with a toast	*/
fillTexts();
navTicks()[0].checked = true;
save();
answer( 400, { error : 'unknown navigation', code : 'routes_unknown_nav', params : [ 'legal' ], field : 'navs' } );
check( 'a refusal that names the navigations is said at the group of ticks, with the focus on its first tick, and no toast', findAll( form, function( el ) { return el.tagName === 'FIELDSET' && el.classList.contains('nino-admin-checklist-field') } )[0].attributes['aria-invalid'] === 'true'
	&& active === navTicks()[0] && sentences().length === 1 && text('/_admin/error/routes_unknown_nav') !== '' );

done();
