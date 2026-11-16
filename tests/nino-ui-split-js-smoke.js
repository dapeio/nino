/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-split-js-smoke.js	DOM-light checks for Nino.adminUi.split(), the
 *											settings on one side and a preview on the other: the
 *											switch that stands for one of them where the view is
 *											narrow, a preview that is loaded once more without the
 *											settings being touched, and the addresses a frame is
 *											not given.
 *
 *	Usage: node tests/nino-ui-split-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, fills, source, documentOf } = require( './harness.js' );

const sandbox = { console : console, document : documentOf( element('div') ), location : { host : 'localhost:8080', origin : 'http://localhost:8080', href : 'http://localhost:8080/_admin/' }, URL : URL };
sandbox.window = sandbox;
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

function byClass( root, name ) {
	return findAll( root, function( el ) { return String( el.className ).split(' ').indexOf( name ) !== -1 } );
}

// The field the settings were given with
function typed( split ) {
	return findAll( split.settings, function( el ) { return el.tagName === 'INPUT' } )[0];
}

function switchButtons( split ) {
	return byClass( split.el, 'nino-admin-split-switch' )[0].children;
}

const events = [];

function build( options ) {
	const settings = element('div');
	settings.appendChild( element('input') );
	return adminUi.split( Object.assign( {
		settings : settings, preview : { src : '/preview', title : 'The site' },
		onView : function( view ) { events.push( 'view:'+ view ) },
		onReload : function() { events.push('reload') },
		onLoad : function( frame ) { events.push( 'load:'+ ( frame === null ? 'none' : frame.tagName ) ) },
	}, options || {} ) );
}

// Every src the frame was given, in order: what navigated it
function watch( split ) {
	const given = [];
	const set = split.frame.setAttribute;
	split.frame.setAttribute = function( name, value ) {
		if( name === 'src' )
			given.push( value );
		set( name, value );
	};
	return given;
}

console.log( 'Nino.adminUi.split - settings and a preview\n' );

// --- the parts ------------------------------------------------------------------

const split = build();
const [ settingsButton, previewButton ] = switchButtons( split );
const settingsPane = byClass( split.el, 'nino-admin-split-settings' )[0];
const previewPane = byClass( split.el, 'nino-admin-split-preview' )[0];

check( 'the settings and the preview are two panes, and what was given is in the first', settingsPane === split.settings && previewPane === split.preview && typed( split ).tagName === 'INPUT' );
check( 'the preview is a frame with a title that says what it shows', split.frame.tagName === 'IFRAME' && split.frame.title === 'The site' && split.frame.getAttribute('src') === '/preview' );
check( 'the frame has no sandbox unless it is asked for one', split.frame.hasAttribute('sandbox') === false && build( { sandbox : 'allow-scripts' } ).frame.getAttribute('sandbox') === 'allow-scripts' );
check( 'a frame with no title of its own is named by the word for the preview', build( { preview : { src : '/x' } } ).frame.title === 'Preview' );

// --- the switch --------------------------------------------------------------------

check( 'the switch is a named group of two buttons, each controlling its pane',
	byClass( split.el, 'nino-admin-split-switch' )[0].getAttribute('role') === 'group' && byClass( split.el, 'nino-admin-split-switch' )[0].getAttribute('aria-label') === 'View'
	&& settingsButton.getAttribute('aria-controls') === settingsPane.id && previewButton.getAttribute('aria-controls') === previewPane.id );
check( 'it says in words what it switches to', settingsButton.textContent === 'Settings' && previewButton.textContent === 'Preview' );
check( 'the settings are shown to start with, and aria-pressed says so', split.view() === 'settings' && split.el.dataset.view === 'settings' && settingsButton.getAttribute('aria-pressed') === 'true' && previewButton.getAttribute('aria-pressed') === 'false' );
check( 'the view can start on the preview', build( { view : 'preview' } ).view() === 'preview' );

fire( previewButton, 'click' );
check( 'a press shows the other pane and tells the caller', split.view() === 'preview' && split.el.dataset.view === 'preview' && previewButton.getAttribute('aria-pressed') === 'true' && events.join() === 'view:preview' );
split.show( 'settings' );
check( 'show() moves the switch with the view and tells nobody', split.view() === 'settings' && settingsButton.getAttribute('aria-pressed') === 'true' && events.join() === 'view:preview' );
split.show( 'anything' );
check( 'a view that is none is the settings', split.view() === 'settings' );

// Both panes stay in the page: nothing here removes one, so what was typed is still there
typed( split ).value = 'typed';
fire( previewButton, 'click' );
fire( settingsButton, 'click' );
check( 'a switch loses nothing: both panes stay in the page, the settings with what was typed in them',
	split.el.children[1].children[0] === settingsPane && split.el.children[1].children[1] === previewPane && typed( split ).value === 'typed' );

// --- loading the preview once more --------------------------------------------------------

const second = build();
const given = watch( second );
const input = typed( second );
input.value = 'typed';
events.length = 0;

second.reload();
check( 'reload() loads the page of the frame once more and tells the caller', given.join() === '/preview' && events.join() === 'reload' );
check( '...and leaves the settings as they are: the same input with what was typed in it', typed( second ) === input && input.value === 'typed' && second.view() === 'settings' );

const button = findAll( second.preview, function( el ) { return el.tagName === 'BUTTON' } )[0];
fire( button, 'click' );
check( 'the button of the preview does the same', given.join() === '/preview,/preview' && events.join() === 'reload,reload' );
check( '...and has a name, since its face is a glyph', button.getAttribute('aria-label') === 'Reload the preview' && button.title === 'Reload the preview' );

second.reload( '/other?x=1' );
check( 'reload( address ) loads another page into the frame', given[2] === '/other?x=1' && second.frame.getAttribute('src') === '/other?x=1' );
second.reload();
check( '...which is the page that is loaded from then on', given[3] === '/other?x=1' );

fire( second.frame, 'load' );
check( 'onLoad is told when the page has loaded, with the frame', events[events.length - 1] === 'load:IFRAME' );

// --- an address a frame is not given ----------------------------------------------------

const unsafe = [ '//evil.test/x', '\\\\evil.test', '/\\evil.test', '\\/evil.test', 'https://evil.test/', 'http:evil.test', 'http://localhost:8080@evil.test/', 'https://localhost:8080.evil.test/', 'https://localhost:8080/', 'HTTPS://LOCALHOST:8080/x', 'http://localhost/x', 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'java\tscript:alert(1)', 'java\nscript:alert(1)', '\u0001javascript:alert(1)',
	'data:text/html,<script>alert(1)</script>', 'blob:http://localhost/x', 'vbscript:x', 'file:///etc/passwd', 'ftp://example.test/', 'about:blank' ];

check( 'an address that is not on this host is not loaded, however it is written',
	unsafe.every( function( src ) {
		const frame = build( { preview : { src : src } } ).frame;
		return frame.hasAttribute('src') === false;
	} ) );

const guarded = build();
const kept = watch( guarded );
unsafe.forEach( function( src ) { guarded.reload( src ) } );
check( 'reload( address ) refuses them too, and the frame stays where it was', kept.length === 0 && guarded.frame.getAttribute('src') === '/preview' );

check( 'a relative address, a query, a fragment and an absolute address of this origin are loaded',
	[ '/a/b', 'page.html', '?x=1', '#top', 'http://localhost:8080/', 'HTTP://LOCALHOST:8080/x?y#z' ].every( function( src ) { return build( { preview : { src : src } } ).frame.getAttribute('src') === src } ) );
check( 'an address with a colon later on is no scheme', build( { preview : { src : '/a:b' } } ).frame.getAttribute('src') === '/a:b' );

// --- the state of the frame -----------------------------------------------------------------

const busy = build();
check( 'a frame that was given a page says it is loading', busy.frame.getAttribute('aria-busy') === 'true' );
fire( busy.frame, 'load' );
check( '...until it has loaded', busy.frame.hasAttribute('aria-busy') === false );
busy.reload();
check( 'reload() says it once more', busy.frame.getAttribute('aria-busy') === 'true' );
check( 'a frame that was refused an address is not busy', build( { preview : { src : 'javascript:1' } } ).frame.hasAttribute('aria-busy') === false );

// A page with a # in its address is a move within it when its src is set to what it is: the frame's own
// reload loads it - when the frame shows that page. Whatever else it shows, the panel's address is the one it goes back to
function shows( split, href ) {
	let reloads = 0;
	split.frame.contentWindow = { location : { reload : function() { reloads++ } } };
	Object.defineProperty( split.frame.contentWindow.location, 'href', typeof href === 'function' ? { get : href } : { value : href } );
	return function() { return reloads };
}

const hashed = build( { preview : { src : '/page#part' } } );
const reloaded = shows( hashed, 'http://localhost:8080/page#part' );
const hashedGiven = watch( hashed );
hashed.reload();
check( 'an address with a # is reloaded by the frame\'s own reload, where the frame shows that page', reloaded() === 1 && hashedGiven.length === 0 );
hashed.reload( '/other#part' );
check( '...and another address is set', hashedGiven.join() === '/other#part' && reloaded() === 1 );

const moved = build( { preview : { src : '/page#part' } } );
const movedReloads = shows( moved, 'http://localhost:8080/elsewhere' );
const movedGiven = watch( moved );
events.length = 0;
fire( moved.frame, 'load' );
moved.reload();
check( 'after a click in the preview the frame shows another page: the panel\'s address is set, not the page\'s reloaded',
	movedGiven.join() === '/page#part' && movedReloads() === 0 && moved.frame.getAttribute('aria-busy') === 'true' && events.indexOf('reload') !== -1 );
fire( moved.frame, 'load' );
check( '...and it is busy until the next load', moved.frame.hasAttribute('aria-busy') === false );

const foreign = build( { preview : { src : '/page#part' } } );
const foreignReloads = shows( foreign, function() { throw new Error('SecurityError') } );
const foreignGiven = watch( foreign );
events.length = 0;
foreign.reload();
check( 'a frame that cannot be asked (a page of another origin) is sent to the panel\'s address, busy, and the caller is told',
	foreignGiven.join() === '/page#part' && foreignReloads() === 0 && foreign.frame.getAttribute('aria-busy') === 'true' && events.join() === 'reload' );

// --- the bar and the words ----------------------------------------------------------------------

const barred = build();
check( 'the bar over the preview has a place for the panel\'s tools, and gives it', barred.tools === byClass( barred.preview, 'nino-admin-split-tools' )[0] );
const tool = element('div');
check( '...which can be filled at the build as well', build( { tools : tool } ).tools.children[0] === tool );

const word = build();
const wordFrame = word.frame;
const wordGiven = watch( word );
word.words( { settings : 'Controls', preview : 'Result' } );
check( 'words() says the switch and the title of the bar differently, and nothing else is touched',
	switchButtons( word )[0].textContent === 'Controls' && switchButtons( word )[1].textContent === 'Result' && byClass( word.preview, 'nino-admin-split-title' )[0].textContent === 'Result'
	&& word.frame === wordFrame && wordGiven.length === 0 && word.view() === 'settings' );
word.words( { preview : 'Page' } );
check( '...one word at a time', switchButtons( word )[0].textContent === 'Controls' && switchButtons( word )[1].textContent === 'Page' );

// --- nothing to preview -----------------------------------------------------------------------------

const solo = build( { preview : undefined } );
check( 'with no preview there is no switch, no bar and no pane: the settings alone',
	solo.preview === null && solo.frame === null && solo.tools === null && byClass( solo.el, 'nino-admin-split-switch' ).length === 0
	&& byClass( solo.el, 'nino-admin-split-bar' ).length === 0 && findAll( solo.el, function( el ) { return el.tagName === 'BUTTON' } ).length === 0 );
check( '...and says so in a class, for the one column', solo.el.className.indexOf('nino-admin-split--solo') !== -1 );
solo.reload();
solo.words( { preview : 'x' } );
solo.show( 'preview' );
check( '...whatever it is asked: the view is the settings', solo.view() === 'settings' && solo.el.dataset.view === 'settings' );

// --- a preview the caller draws -----------------------------------------------------------

const slot = element('div');
const drawn = build( { preview : slot } );
events.length = 0;
drawn.reload();
check( 'an element is the preview where the caller draws it: no frame, and reload() only tells the caller', drawn.frame === null && drawn.preview.children.indexOf( slot ) !== -1 && events.join() === 'reload' );
check( '{ slot } is the same', build( { preview : { slot : slot } } ).preview.children.indexOf( slot ) !== -1 );

// --- the words -----------------------------------------------------------------------------

const worded = build( { text : { settings : 'Controls', preview : 'Result', reload : 'Again', view : 'Show' } } );
check( 'the words can be said differently', switchButtons( worded )[0].textContent === 'Controls' && switchButtons( worded )[1].textContent === 'Result' && byClass( worded.el, 'nino-admin-split-switch' )[0].getAttribute('aria-label') === 'Show' );

const css = source( '_admin/assets/style.css' );
check( 'the switch is for the width the view has, not the window\'s: the root is the container the narrow rules ask',
	/\.nino-admin-split\s*\{[^}]*container:\s*nino-split\s*\/\s*inline-size/.test( css ) && /@container\s+nino-split\s*\(max-width/.test( css ) );
const block = css.slice( css.indexOf('@container nino-split') );
const narrow = block.slice( 0, block.indexOf('\n}\n') );
check( 'narrow, the switch is drawn', /nino-admin-split-switch\s*\{[^}]*display:\s*flex/.test( narrow ) );
check( '...and the pane the switch has not chosen is not', /\[data-view="settings"\][^{]*-preview,[^{]*\[data-view="preview"\][^{]*-settings\s*\{\s*display:\s*none/.test( narrow ) );

done();
