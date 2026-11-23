/**
 *	Nino							A compact filesystembased php framework
 *	install-script-js-smoke.js	DOM-light checks for the wizard's step list, the rail, the
 *								bar at the foot and the Back and Next of the shared script - and
 *								for the page as it is drawn: in both languages, whatever step is
 *								shown, no key stands unresolved and no place for a value is left
 *								open, and the choice of the language draws every step that wrote
 *								words by hand again.
 *
 *	Usage: node tests/install-script-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, load, source, element, documentOf, findAll, fire } = require( './harness.js' );

const template = source('_admin/install/templates/page-wizard.tpl');
const stepKeys = [ 'checks', 'setup', 'personalinfos', 'accounts', 'applications', 'finish' ];

// --- the page, as the template has it -------------------------------------------------

const root = element('div');
const wrap = element('div');
wrap.id = 'install-page-wrap';
wrap.dataset.minPassword = '8';
root.appendChild( wrap );

const nav = {};
stepKeys.forEach( function( key ) {
	nav[key] = element('span');
	nav[key].id = 'install-nav-'+ key;
	wrap.appendChild( nav[key] );
} );

const panes = {};
stepKeys.forEach( function( key ) {
	panes[key] = element('div');
	panes[key].id = 'install-content-'+ key;
	const heading = element('h2');
	heading.setAttribute( 'data-text', '/_admin/install/step/'+ key );
	panes[key].appendChild( heading );
	wrap.appendChild( panes[key] );
} );

[ 'checks-results', 'checks-recheck', 'setup-content', 'personalinfos-list', 'accounts-content', 'applications-content', 'finish-content', 'finish-key-wrap', 'finish-done', 'finish-next-steps', 'page-language-mount' ].forEach( function( id ) {
	const mount = element('div');
	mount.id = id;
	( id === 'page-language-mount' ? root : wrap ).appendChild( mount );
} );

const doc = documentOf( root );
doc.documentElement = element('html');
doc.body = element('body');
doc.title = '';
doc.addEventListener = function() {};
doc.removeEventListener = function() {};
doc.createElement = function( tag ) {
	const created = element( tag );
	if( tag === 'dialog' ) {
		created.open = false;
		created.showModal = function() { created.open = true };
		created.close = function() { created.open = false; fire( created, 'close' ) };
	}
	return created;
};

const stored = {};
const readies = [];
const requests = [];
let answers = {};

const Nino = {
	dir : '',
	events : { bindCallback : function( name, fn ) { if( name === 'ready' ) readies.push( fn ) } },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ) } );
		const given = typeof answers[data.action] === 'function' ? answers[data.action]( JSON.parse( data.data ) ) : answers[data.action];
		const answered = given === undefined ? [ 200, {} ] : given;
		callback( { status : answered[0], responseJSON : answered[1] } );
	} },
	content : { text : {}, getText : function( key ) { asked.push( key ); return Nino.content.text[key] || '' } },
};
const asked = [];
const sandbox = { console : console, document : doc, Nino : Nino };
sandbox.window = {
	Nino : Nino, matchMedia : function() { return { matches : false } }, setTimeout : function() { return 0 }, clearTimeout : function() {},
	confirm : function() { throw new Error('the wizard asks with the dialog of the design system') },
	location : { hostname : 'example.test', href : 'http://example.test/_admin/', pathname : '/_admin/', search : '', reload : function() {} },
	localStorage : { getItem : function( key ) { return Object.prototype.hasOwnProperty.call( stored, key ) ? stored[key] : null }, setItem : function( key, value ) { stored[key] = String( value ) } },
};
sandbox.localStorage = sandbox.window.localStorage;
const context = vm.createContext( sandbox );

[ '_admin/assets/Nino.admin.js', '_admin/assets/pagetext.js', '_admin/install/assets/text.js', '_admin/install/assets/script.js', '_admin/install/assets/checks.js', '_admin/install/assets/setup.js',
	'_admin/install/assets/personalinfos.js', '_admin/install/assets/accounts.js', '_admin/install/assets/applications.js', '_admin/install/assets/finish.js' ].forEach( file => load( file, context ) );

const install = Nino.install;
const text = Nino.pageText;
const byId = id => findAll( root, el => el.id === id )[0];

// What the server answers, by action: enough for every step to draw itself
answers = {
	'checks/run' : [ 200, { php : { version : '8.4.0', required : '8.4.0', ok : true }, extensions : { dom : { ok : true, reason : 'English of the server' }, Phar : { ok : false, reason : 'English of the server' }, unknown : { ok : true, reason : 'x' } }, directories : { '.' : { exists : true, writable : true, ok : true }, public : { exists : false, writable : true, ok : true } } } ],
	'setup/library' : [ 200, { locales : [ 'de_DE', 'en_US' ], activeLocales : [ 'en_US' ], nativeLocale : 'en_US', decided : false, modules : { widget : { label : 'Widget', requiresModules : [ 'forms' ], active : false } } } ],
	'personalinfos/list' : [ 200, { locales : [ 'de_DE', 'en_US' ], entries : [
		{ key : '/project/company/general/name', global : true, labels : { de : 'Unternehmen › Name', en : 'Company › Name' }, values : { '*' : 'Acme' } },
		{ key : '/project/company/contact/country', global : false, labels : { de : 'Unternehmen › Land', en : 'Company › Country' }, values : { de_DE : 'Deutschland', en_US : 'Germany' } },
	] } ],
	'accounts/list' : [ 200, { users : [ 'a@example.com' ] } ],
	'applications/list' : [ 200, { applications : [
		{ key : 'site', name : 'Site', description : 'A site', features : [ { key : 'alpha', name : 'Alpha' } ], start : 'starter', applied : false, entry : true },
		{ key : 'shop', name : 'Shop', description : '', features : [], start : null, applied : false, entry : false },
	], missing : [], message : '', reason : '', retry : false, offline : false, startApplied : null } ],
};

console.log('Setup wizard shell\n');

// --- the shell -----------------------------------------------------------------------------

check( 'the wizard is the six steps that install a site', install.STEPS.map( step => step.key ).join() === stepKeys.join() );

// The rail is read top to bottom, so it has to number the steps in the order they actually run: the words of the rail are the keys of the table, in that order
const railKeys = [ ...template.matchAll( /<span id="install-nav-([a-z]+)"[^>]*data-text="(\/_admin\/install\/step\/[a-z]+)"/g ) ];
check( 'the rail has one span for each step, in the order they run, each named by its key of the table', railKeys.map( found => found[1] ).join() === stepKeys.join() && railKeys.every( found => found[2] === '/_admin/install/step/'+ found[1] ) );
check( '...and the words of the rail number them in that order, in both languages', [ 'de', 'en' ].every( lang => stepKeys.every( ( key, index ) => text._tables[lang]['/_admin/install/step/'+ key].indexOf( ( index + 1 )+ '.' ) === 0 ) ) );

// A step is a nav span, a content pane, a script tag and a _commitStep branch. Removing one means removing all four
check( 'every step the rail names has its content pane, and the pane its own heading to take the focus', stepKeys.every( key => template.includes( 'id="install-content-'+ key+ '"' ) && new RegExp( 'id="install-content-'+ key+ '">\\s*<h2 class="nino-admin-sr-only" data-text="/_admin/install/step/'+ key+ '"' ).test( template ) ) );
check( '...the script tags of the template are the page\'s words, the shell and the steps\' modules, in the order their dependencies need', ( template.match( /(?:_admin\/assets|install\/assets)\/([A-Za-z.-]+)\.js"/g ) || [] ).map( src => src.replace( /^.*\/|\.js"$/g, '' ) ).join() === 'Nino.admin,pagetext,text,script,checks,setup,personalinfos,accounts,applications,finish' );

/*	The rail is a progress display, and the progress was shown by the colour
	of one of six words and nothing else - so an operator who cannot see that
	colour has no way of reading which of the six steps the wizard is on.
	Driven rather than read: what matters is that the mark moves with the
	step and that it never sits on two of them at once	*/
readies.forEach( fn => fn() );

check( 'the bar at the foot is the block of the workbench, drawn by the script: one main button, Next, and Back beside it as a button of the second kind', byId('install-actions-wrap') !== undefined && byId('install-actions-wrap').className.includes('nino-admin-actionbar')
	&& byId('install-next').className.includes('nino-admin-btn-primary') && byId('install-back').className.includes('nino-admin-btn-secondary') && findAll( byId('install-actions-wrap'), el => el.className.includes('nino-admin-btn-primary') ).length === 1 );
check( '...and the template draws no bar and no button of its own', /nino-admin-btn-primary|nino-admin-actionbar|<button/.test( template.replace( /<!--[\s\S]*?-->/g, '' ) ) === false );
check( 'the wizard shows the first step when it is ready, and says in words which it is: the mark is on it and on no other', nav.checks.getAttribute('aria-current') === 'step' && stepKeys.slice( 1 ).every( key => nav[key].getAttribute('aria-current') === null ) && nav.checks.classList.contains('active') === true );
check( '...Back is not offered on the first step, and the main button says Next in the words of the page', byId('install-back').classList.contains('install-hidden') === true && byId('install-next').textContent === Nino.install.t('/_admin/install/label/next') );
check( '...the first step does not take the focus: it is the page itself', findAll( panes.checks, el => el.tagName === 'H2' )[0].focused !== true );

install.showStep( 2, true );
check( 'the mark moves with the step', nav.personalinfos.getAttribute('aria-current') === 'step' && nav.checks.getAttribute('aria-current') === null );
check( '...and the class the stylesheet paints moves with it', nav.personalinfos.classList.contains('active') === true && nav.checks.classList.contains('active') === false );
check( '...the focus goes to the heading of the step that was opened by a button, as after any change of view', findAll( panes.personalinfos, el => el.tagName === 'H2' )[0].focused === true );
check( '...and Back is there from the second step on', byId('install-back').classList.contains('install-hidden') === false );
install.showStep( 0, false );

install._setBusy( true );
check( 'a pending commit disables both shared navigation buttons, and the picker of the language: what is drawn by hand is not drawn again in the middle of a request', byId('install-back').disabled === true && byId('install-next').disabled === true && byId('page-language').disabled === true );

let shown = null;
const realShowStep = install.showStep;
install.showStep = function( index ) { shown = index; install._index = index };
install._index = 2;
install.back();
check( 'Back cannot move the wizard while a commit is pending', shown === null );

install._setBusy( false );
install._index = 3;
install.back();
check( 'Back moves one step towards the first', shown === 2 );
install._index = 0;
shown = null;
install.back();
check( '...and not before it', shown === null );

let commitCallback = null;
install._index = 1;
const realCommit = install._commitStep;
install._commitStep = function( key, callback ) { commitCallback = callback };
install.next();
check( 'Next enters the busy state before waiting for its asynchronous commit', install._busy === true && typeof commitCallback === 'function' );

// Simulate an unrelated index mutation: the callback must still advance from
// the step that started the request, not from this later value.
install._index = 0;
commitCallback( true );
check( 'a successful callback advances from its captured starting index', shown === 2 );
check( 'navigation unlocks after the commit callback', install._busy === false && byId('install-back').disabled === false && byId('install-next').disabled === false && byId('page-language').disabled === false );

// A step that is refused says so in the line of the bar, as an error, and stays
install._index = 1;
shown = null;
install.next();
commitCallback( false, 'Nein.' );
check( 'a step that refuses stays, and says why in the line of the bar: an alert, which the screen reader hears at once', shown === null && install.bar.line.element.textContent === 'Nein.' && install.bar.line.element.getAttribute('role') === 'alert' );
install.next();
commitCallback( false );
check( '...and a refusal with no sentence of its own says the general one of the table', install.bar.line.element.textContent === text.t('/_admin/install/error/step') );

// A step that said what is wrong at its field and in the bar calls back without a sentence: the bar keeps what the step said, and never points at a message that is not there
install.next();
install.say( text.t('/_admin/install/error/fields'), true );
commitCallback( false );
check( '...and a refusal after the step said its own sentence keeps that sentence, and does not say there is a message above', install.bar.line.element.textContent === text.t('/_admin/install/error/fields') && text.t('/_admin/install/error/step').indexOf( 'above' ) === -1 && text._tables.en['/_admin/install/error/step'].indexOf( 'above' ) === -1 );

install.showStep = realShowStep;
install._commitStep = realCommit;
install.showStep( 0, false );
check( 'the first step refuses to be left before its checks ran, in the words of the table', ( function() {
	install.checks._ready = false;
	let said = null;
	install._commitStep( 'checks', function( ok, message ) { said = [ ok, message ] } );
	return said[0] === false && said[1] === text.t('/_admin/install/checks/running');
} )() );

/*	The last step ends the wizard itself: its button is the one of the bar, and there is no step after it to show	*/
install.showStep( 5, false );
check( 'on the last step the one main button ends the wizard, in its own words, and is no longer Next', byId('install-next').textContent === text.t('/_admin/install/finish/submit') && byId('install-next').dataset.application === undefined );
install.showStep( 0, false );

// --- the page drawn, in both languages ------------------------------------------------------

/*	Every word of the page is a key the table has, in the language the page is read in: asked of the blocks of the workbench, which look their own up in the same
	table, and of the table itself. A key that is asked for and has no sentence is an empty place on the page, or - where a block shows the key where it has no word - the
	key itself. The drawn page has neither, and no %s that was not filled in	*/
function drawn( scope ) {

	const found = [];

	( function walk( node ) {
		if( node.tagName === '#TEXT' || ( typeof node.textContent === 'string' && node.children.length === 0 ) )
			found.push( node.textContent );
		[ 'aria-label', 'title', 'placeholder' ].forEach( name => { if( node.attributes[name] !== undefined ) found.push( node.attributes[name] ) } );
		node.children.forEach( walk );
	} )( scope );

	return found;
}

[ 'de', 'en' ].forEach( function( language ) {

	text.set( language );
	asked.length = 0;
	requests.length = 0;

	// Every step, with the answers above: the step that loads asks, draws and is drawn again
	[ 'checks', 'setup', 'personalinfos', 'accounts', 'applications' ].forEach( function( key ) {
		install[key]._ready = false;
		if( key === 'setup' ) install.setup._library = null;
		install.showStep( stepKeys.indexOf( key ), false );
	} );
	install.showStep( 5, false );

	const unresolved = asked.filter( key => key.charAt(0) === '/' && ( Nino.content.text[key] === undefined || Nino.content.text[key] === '' ) );
	check( language+ ': every word the blocks of the workbench look up in a drawn step is in the table, in the language of the page'+ ( unresolved.length ? ' - '+ [ ...new Set( unresolved ) ].join(', ') : '' ), asked.length > 5 && unresolved.length === 0 );

	const leftovers = [];
	stepKeys.forEach( key => drawn( panes[key] ).forEach( shownText => {
		if( /^\/_admin\/|%s|\[\[/.test( shownText ) === true )
			leftovers.push( key+ ': '+ JSON.stringify( shownText ) );
	} ) );
	check( language+ ': no step shows a key, a place for a value that was not filled or the mark of a fill'+ ( leftovers.length ? ' - '+ leftovers.slice( 0, 4 ).join(', ') : '' ), leftovers.length === 0 );
	check( language+ ': what the server wrote is shown by nothing: not the English of an extension\'s reason, which is the table\'s word in the language of the page', stepKeys.every( key => drawn( panes[key] ).every( shownText => shownText !== 'English of the server' ) ) );
} );

// An extension the table has no sentence for stands with its name alone; one it knows says what it is for, in the language of the page
text.set('de');
install.checks._render();
const rowsOf = () => findAll( byId('checks-results'), el => el.className.includes('nino-admin-list-copy') ).map( el => el.children.map( child => child.textContent ).join(' | ') );
check( 'an extension the table knows says what it is for, in the language of the page; one it does not know stands with its name alone', rowsOf().some( row => row.startsWith('dom') && row.includes( text._tables.de['/_admin/install/checks/extension-dom'] ) ) && rowsOf().some( row => row.startsWith('unknown') && row.split(' | ').length === 1 ) );
check( '...the state of each row is a badge with its word and a sign, and a refusal is not the colour alone', findAll( byId('checks-results'), el => el.className.includes('nino-admin-badge') ).every( badge => /^[✓●]/.test( badge.textContent ) && [ 'ok', 'error' ].includes( badge.dataset.state ) ) );

// --- the choice of the language draws what was written by hand again ----------------------

text.set('de');
install.showStep( 1, false );
const localeSelect = () => findAll( byId('setup-languages'), el => el.tagName === 'SELECT' );
const labelOf = option => option.textContent;
check( 'the step asks for one language, as one select, and names the languages as the workbench names them - "Deutsch", "English (US)" - in either language of the page',
	localeSelect().length === 1 && findAll( byId('setup-languages'), el => el.tagName === 'INPUT' && el.type === 'checkbox' ).length === 0 && findAll( localeSelect()[0], el => el.tagName === 'OPTION' ).map( labelOf ).join() === 'Deutsch,English (US)' );
check( '...a project that has not answered starts on the language of the page: German in a page in German', localeSelect()[0].value === 'de_DE' );
localeSelect()[0].value = 'en_US';
answers['setup/apply'] = [ 200, {} ];
requests.length = 0;
install.setup.apply( function() {} );
const applied = requests.filter( request => request.action === 'setup/apply' );
check( '...and the step sends that one language - a list of one - and the same one as the native language', applied.length === 1 && JSON.stringify( applied[0].payload.locales ) === '["en_US"]' && applied[0].payload.native === 'en_US' );

install.showStep( 3, false );
byId('accounts-add-mail').value = 'typed@example.com';
install.showStep( 1, false );
byId('install-page-wrap').classList.add('x');
text.set('en');
check( 'the other language draws every step that wrote words by hand again: the bar, the fields, the sections', byId('install-back').textContent === text._tables.en['/_admin/install/label/back'] && byId('install-next').textContent === text._tables.en['/_admin/install/label/next']
	&& findAll( byId('setup-languages'), el => el.tagName === 'SPAN' ).some( node => node.textContent === text._tables.en['/_admin/install/setup/language'] ) );
check( '...what was chosen stays chosen, and what was typed stays typed', localeSelect()[0].value === 'en_US' && byId('accounts-add-mail').value === 'typed@example.com' );
check( '...the choice is kept for the next page, and the page says it in <html lang> and the title', stored['nino-admin-page-language'] === 'en' && doc.documentElement.lang === 'en' && doc.title === text._tables.en['/_admin/page/title'] );
check( '...and the picker names the other one in its own language', byId('page-language').textContent === 'Deutsch' && byId('page-language').getAttribute('lang') === 'de' );

/*	A name the catalogue wrote may have no place to break: what shows one wraps it, or the page grows wide. By rule and by
	selector, in the stylesheet of the wizard: the button of the bar, the links of the next steps, the rows of the choice and the rows of a list	*/
const installStyle = require('fs').readFileSync( require('path').join( __dirname, '../_admin/install/assets/style.css' ), 'utf8' );
const wrapping = ( installStyle.match( /([^{}]+)\{[^{}]*overflow-wrap:\s*anywhere[^{}]*\}/g ) || [] ).join( ' ' );
check( 'the button of the bar, the links of the next steps, the rows of the choice and the rows of a list wrap a name that has no place to break',
	[ '#install-next', '.install-next-step-copy', '.install-app-copy', '.nino-admin-list-copy' ].every( function( selector ) { return wrapping.includes( selector ) } ) );

done();
