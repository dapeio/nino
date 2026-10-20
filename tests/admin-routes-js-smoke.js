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
	return row.children.find( function( child ) { return child.className === 'nino-admin-move' } ).children[ direction === 'up' ? 0 : 1 ];
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

/** Whether an element is the root or stands somewhere below it */
function inside( root, el ) {
	for( let node = el; node !== null; node = node.parentNode )
		if( node === root )
			return true;
	return false;
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

const requests = [];

const confirms = [];
let confirmAnswer = false;

// The shell's counters (see Nino.admin.touch()): what the panel says it wrote,
// and the stamp of the templates it reads back
const touched = [];
const stamps = { templates : 0 };

const Nino = {
	admin : {
		assetUrl : function( path ) { return '/site'+ path },
		formToolbar : function( backLink ) { const bar = element('div'); bar.appendChild( backLink ); return bar },
		touch : function( topic ) { touched.push( topic ) },
		stamp : function( topic ) { return stamps[topic] ?? 0 },
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
		get activeElement() { return active },
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, location : { hash : '' }, confirm : function( message ) { confirms.push( message ); return confirmAnswer } };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Routes/assets/admin.js', context );

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
check( '...and tells the other panels that routes were written', touched.join() === 'routes' );

/*	A move the server refuses. It used to end in a bare return: the row did
	not move, nothing appeared anywhere, and the arrow read as a button that
	does nothing at all. The order on screen is a guess from that moment on -
	somebody else may have deleted the very route this list still draws - so
	it is read back, and the reason is said.	*/
fire( moveButton( '/about', 'up' ), 'click' );
answer( 409, { error : 'route was removed in the meantime' } );

check( 'a refused move reads the list back from the server', requests.length === 4 && requests[3].action === 'routes/list' );
check( '...and says nothing of routes written: nothing was', touched.join() === 'routes' );
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

const opening = rows().map( function( li ) { return li.children[0] } );
check( 'a row opens a form and writes no address, so it is a button and not a link to nowhere', opening.length === 4 && opening.every( function( el ) { return el.tagName === 'BUTTON' && el.type === 'button' && el.className === 'nino-admin-list-link' && el.href === '' } ) );

const withOpen = openLink( '/about' );
check( 'the link to the page is a real link, in a tab of its own, under the project directory', withOpen !== null && withOpen.href === '/site/about' && withOpen.target === '_blank' && withOpen.rel === 'noopener'
	&& withOpen.attributes['aria-label'] === text('/_admin/routes/label/open') && withOpen.textContent === '↗' );
check( '...but none for a path that would leave the site or is no path', openLink( '//evil.example' ) === null && openLink( 'relative' ) === null );
check( 'the ↑/↓ cluster is still on every row', rows().every( function( li ) { return li.children.some( function( child ) { return child.className === 'nino-admin-move' } ) } ) );

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

// The bar of the form has a status line, and a bar that has one shows no other mark of unsaved input
fire( listActionButton(), 'click' );
check( 'a form that was just opened is not marked as unsaved', field('routes-form-msg').dataset.state !== 'dirty' );
fire( findAll( form, function( el ) { return el.tagName === 'FORM' } )[0], 'input', { target : field('routes-form-uri') } );
check( '...an edit turns the line of the bar to unsaved, which is where the screen says it', field('routes-form-msg').dataset.state === 'dirty' );

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
check( '...which is an alert of the status line, not a paragraph nobody hears', field('routes-form-msg').dataset.state === 'error' && field('routes-form-msg').attributes.role === 'alert' );

function sentences() {
	return findAll( form, function( el ) { return el.className === 'nino-admin-field-error' } );
}
const empty = sentences();
check( 'each missing field points at a sentence of its own under its label, which says what is wrong', [ 'routes-form-uri', 'routes-form-http-uri' ].every( function( id ) {
	const sentence = empty.find( function( el ) { return el.id === field( id ).attributes['aria-describedby'] } );
	const label = field( id ).parentNode;
	return sentence !== undefined && sentence.textContent === text('/_admin/common/error/required') && label.parentNode.children.indexOf( sentence ) === label.parentNode.children.indexOf( label ) + 1;
} ) && text('/_admin/common/error/required') !== '' );
check( '...the name and the title of a language stand in one row and point at one sentence', [ 'de_DE', 'en_US' ].every( function( locale ) {
	const id = localeInput( locale, 'name' ).attributes['aria-describedby'];
	return id === localeInput( locale, 'title' ).attributes['aria-describedby'] && empty.filter( function( el ) { return el.id === id } ).length === 1;
} ) && empty.length === 4 );
check( '...and a field that is not missing points at nothing', localeInput( 'de_DE', 'description' ).attributes['aria-describedby'] === undefined && field('routes-form-template').attributes['aria-describedby'] === undefined );

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
check( '...and the sentences of what was fixed are gone with their marks', sentences().length === 1 && sentences()[0].id === localeInput( 'en_US', 'name' ).attributes['aria-describedby']
	&& field('routes-form-uri').attributes['aria-describedby'] === undefined && localeInput( 'de_DE', 'title' ).attributes['aria-describedby'] === undefined );

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
field('routes-form-http-uri').focus();
save();
check( 'a complete form is sent, an empty description with it', requests.length === before + 1 && requests[before].action === 'routes/save'
	&& requests[before].payload.template === 'page-blank' && requests[before].payload.text.en_US.description === '' && requests[before].payload.originalHttpUri === '' );
touched.length = 0;
answer( 200, {} );
check( 'a save that went through tells the other panels that routes were written', touched.join() === 'routes' );
answer( 200, listing( [ '/', '/about', '/contact' ], { templates : [ 'page-about', 'page-blank', 'page-contact' ] } ) );
check( 'a save returns to the list, and the focus that stood in the form goes to the name of the panel', mount.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true && active === paneTitle );

/*	The focus goes into a form that is opened, and back to the row that opened
	it. It used to fall to the body both ways: the control that was pressed is
	hidden with the level it stood in	*/
function openRoute( httpUri ) {
	rows().find( function( li ) { return rowPath( li ) === httpUri } ).children[0].listeners.click[0]( { preventDefault : function() {} } );
}
function backLink() {
	return findAll( form, function( el ) { return el.className === 'nino-admin-back-link' } )[0];
}

openRoute( '/about' );
check( 'opening a form moves the focus into it', form.classList.contains('admin-hidden') === false && mount.classList.contains('admin-hidden') === true && inside( form, active ) && active.tagName === 'A' );
fire( backLink(), 'click' );
check( 'Back shows the list and puts the focus on the row that was opened', mount.classList.contains('admin-hidden') === false && form.classList.contains('admin-hidden') === true
	&& active === rows().find( function( li ) { return rowPath( li ) === '/about' } ).children[0] );

fire( listActionButton(), 'click' );
check( 'a new route opens the same way', inside( form, active ) );
fire( backLink(), 'click' );
check( '...and Back returns to the New route button', active === listActionButton() );

/*	The delete question names what stays: the texts and the template, and for
	the template whether other routes use it.	*/
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
	&& confirms[0].includes('“/about”') && confirms[0].includes('/_nino/webpage/site-about/name|title (de_DE, en_US)') && confirms[0].includes('/_nino/webpage/site-about/uri') );
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
touched.length = 0;
answer( 200, {} );
check( '...and a delete that went through tells the other panels that routes were written', touched.join() === 'routes' );
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

/*	The pages a feature routes at runtime: listed under the persisted ones, with
	no arrows and no way to delete, and a form of the path - read only - and the
	three texts per language, saved as routes/savetexts. A project with none
	draws nothing for them	*/
const featurePages = listing( [ '/', '/contact' ], { runtime : [
	{ uri : '/blog/post', httpUri : '/blog/*', body : '[template /templates/page-post]', text : { de_DE : { name : 'Beitrag', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } },
	{ uri : '/.newsletter', httpUri : '/.newsletter', body : '[template /templates/page-newsletter]', text : { de_DE : { name : '', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } },
] } );
panel._pages = featurePages.pages;
panel._runtime = [];
panel._renderList();
check( 'with no feature route nothing is drawn for them: no heading, no second list', findAll( mount, function( el ) { return el.tagName === 'H3' } ).length === 0 && rows().length === 2 );

panel._runtime = featurePages.runtime;
panel._renderList();
const runtimeHeading = findAll( mount, function( el ) { return el.tagName === 'H3' } );
check( 'feature routes have a heading of their own and a sentence saying what they are', runtimeHeading.length === 1 && runtimeHeading[0].textContent === text('/_admin/routes/runtime/title') && text('/_admin/routes/runtime/title') !== ''
	&& findAll( mount, function( el ) { return el.className === 'nino-admin-hint' && el.textContent === text('/_admin/routes/runtime/hint') } ).length === 1 );
const featureRows = rows().slice( 2 );
check( 'they stand below the pages, a row each, named by what was written and by the path', rows().length === 4 && rowPath( featureRows[0] ) === '/blog/*' && rowName( featureRows[0] ) === 'Beitrag' && rowName( featureRows[1] ) === '/.newsletter' );
check( '...without the arrows and without a link out: none of them is the project\'s to move, and a placeholder is no page', featureRows.every( function( li ) { return li.children.length === 1 } ) );

featureRows[1].children[0].listeners.click[0]( { preventDefault : function() {} } );
check( 'a feature route opens a form of the path, which is read only', field('routes-form-runtime-path').value === '/.newsletter' && field('routes-form-runtime-path').readOnly === true
	&& field('routes-form-uri') === undefined && field('routes-form-template') === undefined );
check( '...with a name, a title and a description per language', [ 'de_DE', 'en_US' ].every( function( locale ) { return [ 'name', 'title', 'description' ].every( function( name ) { return localeInput( locale, name ) !== undefined } ) } ) );
check( '...and no menu, no status code and no delete', findAll( form, function( el ) { return el.dataset.nav !== undefined || el.id === 'routes-form-status' || el.className === 'nino-admin-btn-danger' } ).length === 0 );
fire( findAll( form, function( el ) { return el.tagName === 'FORM' } )[0], 'input', { target : localeInput( 'de_DE', 'name' ) } );
check( '...whose line follows an edit too: unsaved', field('routes-form-msg').dataset.state === 'dirty' );

const beforeRuntime = requests.length;
save();
check( 'a name and a title are required in every language here as well', requests.length === beforeRuntime && localeInput( 'de_DE', 'name' ).attributes['aria-invalid'] === 'true' && localeInput( 'en_US', 'title' ).attributes['aria-invalid'] === 'true'
	&& field('routes-form-msg').textContent === text('/_admin/routes/error/required') );
[ 'de_DE', 'en_US' ].forEach( function( locale ) { localeInput( locale, 'name' ).value = 'Newsletter'; localeInput( locale, 'title' ).value = 'Abonnieren' } );
localeInput( 'de_DE', 'description' ).value = 'Alle drei Monate.';
save();
check( 'a complete form is sent as routes/savetexts for the Element-URI, and only that', requests.length === beforeRuntime + 1 && requests[beforeRuntime].action === 'routes/savetexts'
	&& requests[beforeRuntime].payload.uri === '/.newsletter' && Object.keys( requests[beforeRuntime].payload ).sort().join() === 'text,uri'
	&& requests[beforeRuntime].payload.text.de_DE.description === 'Alle drei Monate.' && requests[beforeRuntime].payload.text.en_US.title === 'Abonnieren' );
touched.length = 0;
answer( 200, { runtime : [] } );
check( 'the texts of a feature route are written like a page: the other panels are told', touched.join() === 'routes' );
answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact' ] } ) );
check( 'once saved the list is read again', requests[requests.length - 1].action === 'routes/list' );

// After a feature's form the next page opened is a page again: the form of a
// persisted route saves as routes/save
openRoute( '/contact' );
check( 'the form of a persisted route is its own again - uri, template and all', field('routes-form-uri') !== undefined && field('routes-form-runtime-path') === undefined );
const beforePage = requests.length;
[ 'de_DE', 'en_US' ].forEach( function( locale ) { localeInput( locale, 'name' ).value = 'K'; localeInput( locale, 'title' ).value = 'T' } );
save();
check( '...and is saved as routes/save', requests.length === beforePage + 1 && requests[beforePage].action === 'routes/save' );
answer( 200, {} );
answer( 200, listing( [ '/', '/contact' ] ) );

// A page of a module that has a route per language - the imprint of the Legal
// module - is one row, shown with every path it is reached at, and its form says the same
panel._pages = listing( [ '/', '/contact' ] ).pages;
panel._runtime = [ { uri : '/legal/imprint', httpUri : '/impressum', httpUris : [ '/impressum', '/imprint' ], body : '[template /templates/page-legal-imprint]',
	text : { de_DE : { name : 'Impressum', title : 'Impressum', description : '' }, en_US : { name : 'Imprint', title : 'Imprint', description : '' } } } ];
panel._renderList();
const perLanguageRows = rows().slice( 2 );
check( 'a page with a route per language is one row, named by what was written and shown with all its paths', perLanguageRows.length === 1 && rowName( perLanguageRows[0] ) === 'Impressum' && rowPath( perLanguageRows[0] ) === '/impressum, /imprint' );
perLanguageRows[0].children[0].listeners.click[0]( { preventDefault : function() {} } );
check( '...and its form shows them read only, in one field', field('routes-form-runtime-path').value === '/impressum, /imprint' && field('routes-form-runtime-path').readOnly === true );
panel._runtime = [ { uri : '/.newsletter', httpUri : '/.newsletter', body : '', text : { de_DE : { name : '', title : '', description : '' }, en_US : { name : '', title : '', description : '' } } } ];
panel._renderList();
check( 'a page without a list of paths - an older server\'s answer - shows its one path', rowPath( rows().slice( 2 )[0] ) === '/.newsletter' );
panel._runtime = [];
panel._renderList();

/*	A page template written since the list was read - the Builder creates them -
	is one the form's select has to offer. The panel remembers the stamp of the
	templates it asked for and, when it is shown again, reads the list again if
	that moved: not while a form is open, which would be drawn over, and then at
	the showing that finds none	*/
panel.init();
answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact' ] } ) );
const idle = requests.length;
panel.showCurrent();
check( 'shown again with no template written since, the panel asks for nothing', requests.length === idle && panel._stamp === 0 );

stamps.templates++;
fire( listActionButton(), 'click' );
panel.showCurrent();
check( 'a template written while a form is open is not read now: the form is not drawn over', requests.length === idle && form.classList.contains('admin-hidden') === false );

fire( findAll( form, function( el ) { return el.className === 'nino-admin-back-link' } )[0], 'click' );
check( '...going back to the list is no showing of the panel, and asks for nothing either', requests.length === idle );
panel.showCurrent();
check( '...the showing that finds no form open reads the list again', requests.length === idle + 1 && requests[idle].action === 'routes/list' );
check( '...and until the answer is there it is what the panel remembers that is old, not the stamp: a second showing does not take the first for done', ( function() { panel.showCurrent(); return requests.length === idle + 2 } )() );

answer( 200, listing( [ '/', '/contact' ], { templates : [ 'page-contact', 'page-new' ] } ) );
check( '...the template that was written is on offer in the form of a new page', ( function() {
	fire( listActionButton(), 'click' );
	return field('routes-form-template').children.map( function( option ) { return option.value } ).indexOf('page-new') !== -1;
} )() );
const settled = requests.length;
panel._showList();
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
			admin : { dirty : { watchForm : ( name, getter, save ) => watched.push( { name : name, getter : getter, save : save } ), snapshot : name => calls.push( 'snapshot '+ name ) }, touch : topic => calls.push( 'touch '+ topic ), stamp : () => 0 },
			events : { bindCallback : function() {} },
			http : { sendRequest : ( uri, method, callback, data ) => sent.push( { action : data.action, callback : callback } ) },
			content : { getText : key => key },
			adminUi : { clearFieldErrors : () => {}, api : { errorText : status => 'error '+ status, call : ( endpoint, payload, callback ) => sent.push( { action : endpoint, callback : callback } ) } },
		},
	};
	box.window = box;
	load( '_admin/Nino/Modules/Routes/assets/admin.js', vm.createContext( box ) );

	// The status line of the form that is open, which the form draws: here it writes down what it is told
	const said = [];
	box.Nino.admin.routes._line = { saving : () => said.push( 'saving' ), saved : () => said.push( 'saved' ), fail : () => said.push( 'fail' ), error : status => said.push( 'error '+ status ) };

	check( 'the page form registers with the shell under the panel\'s name', watched.length === 1 && watched[0].name === 'routes' && watched[0].getter() === node('routes-form') );

	const outcomes = [];
	box.Nino.admin.routes._save( ok => outcomes.push( ok ) );
	sent[sent.length - 1].callback( 500, null );
	check( 'a save that fails reports false and takes nothing for stored', outcomes.join() === 'false' && calls.length === 0 );
	check( '...and says in the status line that it is saving and then why it failed', said.join() === 'saving,error 500' );

	box.Nino.admin.routes._save( ok => outcomes.push( ok ) );
	sent[sent.length - 1].callback( 200, {} );
	check( 'a save that goes through says what it wrote, takes the form as it stands for the stored one, and reports true', outcomes.join() === 'false,true' && calls.join() === 'touch routes,snapshot routes' && said.slice( 2 ).join() === 'saving,saved' );
	check( '...the shell\'s Save runs the same save', typeof watched[0].save === 'function' );
}

done();
