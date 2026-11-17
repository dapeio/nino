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
// The observers of the suite: what a work surface asks to be told about its size, and the way to tell it
const observed = [];
sandbox.ResizeObserver = class { constructor( fn ) { this.fn = fn } observe( el ) { this.entry = { el : el, fn : this.fn, live : true }; observed.push( this.entry ) } disconnect() { this.entry.live = false } };
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

// A page that takes the place of the one the frame shows does not add a step to the history of the window: it is given to the
// frame's own location.replace(), and the src attribute, which would, is left alone. Where the frame cannot be asked the attribute is the way
function replacing( split, href ) {
	const replaced = [];
	split.frame.contentWindow = { location : { replace : function( address ) { replaced.push( address ) }, reload : function() { replaced.push('reload') } } };
	Object.defineProperty( split.frame.contentWindow.location, 'href', { get : href } );
	return replaced;
}

const stepless = build();
const steplessReplaced = replacing( stepless, function() { return 'http://localhost:8080/preview' } );
const steplessGiven = watch( stepless );
events.length = 0;
stepless.reload( '/other?x=2' );
check( 'another address replaces the one the frame shows, as an absolute address, and no src is set: that would be a step in the history',
	steplessReplaced.join() === 'http://localhost:8080/other?x=2' && steplessGiven.length === 0 && stepless.frame.getAttribute('src') === '/preview' && stepless.frame.getAttribute('aria-busy') === 'true' && events.join() === 'reload' );
stepless.reload( 'sub/page.html' );
check( '...a relative address is made absolute against the workbench, not against the page in the frame', steplessReplaced[1] === 'http://localhost:8080/_admin/sub/page.html' );
stepless.reload();
check( '...and the page that is loaded once more is the last one it was given', steplessReplaced[2] === 'http://localhost:8080/_admin/sub/page.html' );
unsafe.forEach( function( src ) { stepless.reload( src ) } );
check( '...an address that is not allowed is not given to it either', steplessReplaced.length === 3 );

const sameFrame = build();
const sameReplaced = replacing( sameFrame, function() { return 'http://localhost:8080/preview' } );
sameFrame.reload();
check( 'the page the frame shows is reloaded by the frame, and nothing is replaced', sameReplaced.join() === 'reload' );

const refusing = build();
const refusingGiven = watch( refusing );
refusing.frame.contentWindow = { location : { replace : function() { throw new Error('SecurityError') } } };
Object.defineProperty( refusing.frame.contentWindow.location, 'href', { get : function() { return 'http://localhost:8080/elsewhere' } } );
refusing.reload( '/next' );
check( 'a frame that refuses the replacing is given the address as its src', refusingGiven.join() === '/next' && refusing.frame.getAttribute('aria-busy') === 'true' );

check( 'a frame that was refused its first address has nothing to load once more', ( function() {
	const empty = build( { preview : { src : 'javascript:1' } } );
	const emptyGiven = watch( empty );
	empty.reload();
	return emptyGiven.length === 0 && empty.frame.hasAttribute('aria-busy') === false;
} )() );

// --- a page at a layout width ---------------------------------------------------------------------

const scaled = [];
const realScale = adminUi.scaleFrame;
adminUi.scaleFrame = function( frame, port, width, height ) { scaled.push( { frame : frame, port : port, width : width, height : height } ) };

const flat = build();
const flatPort = flat.frame.parentNode;
check( 'the frame stands in a port of its own, in the pane, after the bar', flatPort.className === 'nino-admin-split-port' && flat.preview.children[1] === flatPort && flat.preview.children[0] === byClass( flat.preview, 'nino-admin-split-bar' )[0] );
check( 'with no width it is as it is: not scaled, no width, the port not marked', scaled.length === 0 && flat.width() === null && flatPort.className === 'nino-admin-split-port' && flat.frame.style.transform === undefined );

const wide = build( { preview : { src : '/preview', width : 1280 } } );
const widePort = wide.frame.parentNode;
check( 'preview.width lays the frame out on that width and scales it into the port, which says so', scaled.length === 1 && scaled[0].frame === wide.frame && scaled[0].port === widePort && scaled[0].width === 1280 && scaled[0].height === undefined
	&& widePort.className.split(' ').indexOf('nino-admin-split-port--scaled') !== -1 && wide.width() === 1280 );

const narrowed = wide.width( 768 );
check( 'width( px ) lays it out on the new width, and answers it', narrowed === 768 && wide.width() === 768 && scaled.length === 2 && scaled[1].width === 768 && scaled[1].port === widePort );

wide.frame.style.width = '768px';
wide.frame.style.height = '900px';
wide.frame.style.transform = 'scale(.5)';
wide.frame.style.left = '10px';
wide.frame.style.top = '5px';
widePort._ninoScaleObserver = { disconnect : function() { events.push('disconnected') } };
events.length = 0;
wide.width( null );
check( 'width( null ) takes the frame back to the width of the pane: no inline size, no transform, the observer let go, the port not marked',
	wide.width() === null && [ 'width', 'height', 'transform', 'left', 'top' ].every( function( name ) { return wide.frame.style[name] === '' } )
	&& events.join() === 'disconnected' && widePort._ninoScaleObserver === null && widePort.className === 'nino-admin-split-port' && scaled.length === 2 );

[ 0, -5, 0.4, 10001, NaN, Infinity, '1280', {}, true, undefined ].forEach( function( bad ) {
	wide.width( 768 );
	wide.width( bad );
	check( 'width( '+ String( bad ) + ' ) is no width: it is none', wide.width() === null && widePort.className === 'nino-admin-split-port' );
} );
check( '...and a number of the range is taken, rounded to a whole px', wide.width( 400.4 ) === 400 && wide.width( 1 ) === 1 && wide.width( 10000 ) === 10000 );

// What has no frame has nothing to lay out
const scaledBefore = scaled.length;
check( 'a preview the caller draws, and the settings alone, take width() and keep none', build( { preview : element('div') } ).width( 1280 ) === null && build( { preview : undefined } ).width( 1280 ) === null && scaled.length === scaledBefore );
adminUi.scaleFrame = realScale;

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

// --- a view in the form it always had ----------------------------------------------------

{
	const plain = build();

	check( 'without options the view is the block it was: no work surface, no side column, a bar over the preview with its place for tools',
		plain.el.className === 'nino-admin-split' && plain.aside === null && plain.el.dataset.aside === undefined && byClass( plain.preview, 'nino-admin-split-bar' ).length === 1 && plain.tools !== null
		&& observed.every( entry => entry.el !== plain.el ) );
}

// --- the work surface -----------------------------------------------------------------

{
	const surface = build( { fill : true } );
	const pane = element('main');
	pane.className = 'nino-admin-pane';
	pane.appendChild( surface.el );
	const told = observed.find( entry => entry.el === surface.el );
	const shown = ( on ) => { surface.el.getClientRects = () => on ? [ {} ] : []; told.fn() };

	check( 'fill makes the form of a work surface: a class for the stylesheet, and no bar over the preview - its tools stand in the context bar',
		surface.el.className.split(' ').includes('nino-admin-split--fill') === true && byClass( surface.preview, 'nino-admin-split-bar' ).length === 0 && surface.tools === null && surface.frame.tagName === 'IFRAME' );

	shown( true );
	check( 'a surface that is on the screen marks the pane it stands in, which the stylesheet takes the scroll of the page from', pane.classList.contains('nino-admin-pane--surface') === true );

	const other = build( { fill : true } );
	pane.appendChild( other.el );
	const second = observed.find( entry => entry.el === other.el );
	other.el.getClientRects = () => [ {} ];
	second.fn();
	shown( false );
	check( '...a surface that is hidden - a level that was left - lets go of it, unless another one in the pane is still on the screen', pane.classList.contains('nino-admin-pane--surface') === true );
	other.el.getClientRects = () => [];
	second.fn();
	check( '...and the pane is a page again when none is', pane.classList.contains('nino-admin-pane--surface') === false );

	shown( true );
	pane.removeChild( surface.el );
	told.fn();
	check( 'a surface that leaves the page lets go of the pane', pane.classList.contains('nino-admin-pane--surface') === false );
	check( '...and its observer is let go of with it, once it was in the page', observed.find( entry => entry.el === surface.el ).live === false );

	const early = build( { fill : true } );
	early.el.removed = true;
	observed.find( entry => entry.el === early.el ).fn();
	check( 'a surface that is not in the page yet - the first call of the observer comes before it is put there - is watched still', observed.find( entry => entry.el === early.el ).live === true );
}

// --- no settings ------------------------------------------------------------------------

{
	const outline = element('div');
	const bare = adminUi.split( { id : 'bare-a', preview : { src : '/preview', title : 'The site' }, aside : { content : outline, label : 'Outline' } } );
	const grid = bare.el.children.find( el => el.className === 'nino-admin-split-grid' );
	const [ first, second ] = switchButtons( bare );

	check( 'a view without settings has no column for them: the grid is the side column and the preview, and there are no settings to hand out',
		bare.settings === null && bare.el.dataset.bare === 'true' && grid.children.length === 2 && grid.children[0] === bare.aside.el && grid.children[1] === bare.preview );
	check( '...the switch of a narrow view is then between the side column and the preview, named by what the caller says the column holds, and controls those',
		first.textContent === 'Outline' && first.getAttribute('aria-controls') === bare.aside.el.id && second.getAttribute('aria-controls') === bare.preview.id );
	check( 'a view with settings keeps its three columns and the word for them', build( { aside : { content : element('div'), label : 'Outline' } } ).el.dataset.bare === undefined
		&& switchButtons( build( { aside : { content : element('div'), label : 'Outline' } } ) )[0].textContent === 'Settings' );

	const alone = adminUi.split( { preview : { src : '/preview' } } );
	check( 'with neither settings nor a side column the view is the preview alone - on the preview, with no switch that leads to nothing',
		alone.settings === null && alone.view() === 'preview' && byClass( alone.el, 'nino-admin-split-switch' ).length === 0 && ( alone.show( 'settings' ), alone.view() === 'preview' ) );

	const sheet = source( '_admin/assets/style.css' ).replace( /\/\*[\s\S]*?\*\//g, '' );

	check( 'the stylesheet gives such a view two tracks, the preview taking all the rest of the width, and never a switch to an empty view',
		/\[data-bare\]\[data-aside="open"\]\s*>\s*\.nino-admin-split-grid\s*\{\s*grid-template-columns:\s*var\(--nino-admin-aside-width\) minmax\(0, 1fr\)/.test( sheet )
		&& /\[data-bare\]\[data-aside="folded"\]\s*>\s*\.nino-admin-split-grid\s*\{\s*grid-template-columns:\s*var\(--nino-admin-aside-rail\) minmax\(0, 1fr\)/.test( sheet )
		&& /\[data-bare\] \.nino-admin-split-aside-body\[hidden\]\s*\{\s*display:\s*block/.test( sheet ) );
}

// --- the side column ------------------------------------------------------------------

{
	const content = element('div');
	const rail = element('div');
	const side = build( { id : 'aside-a', aside : { content : content, label : 'Outline', rail : rail } } );
	const column = side.aside;
	const grid = side.el.children.find( el => byClass( el, 'nino-admin-split-grid' ).length > 0 || el.className === 'nino-admin-split-grid' );

	check( 'the side column is the first of the columns, and holds what it was given in its body and the folded strip\'s content in its rail',
		grid.children[0] === column.el && grid.children[1] === side.settings && column.body.children[0] === content && column.rail.children[0] === rail );
	check( 'its button is named by the caller, tells whether the column is open, and controls the body',
		column.toggle.tagName === 'BUTTON' && column.toggle.getAttribute('aria-label') === 'Outline' && column.toggle.getAttribute('aria-expanded') === 'true' && column.toggle.getAttribute('aria-controls') === column.body.id && column.body.id !== ''
		&& findAll( column.toggle, el => el.tagName === 'SVG' ).length === 2 );
	check( '...open the body is shown and the strip is not', column.collapsed() === false && column.body.hidden === false && column.rail.hidden === true && side.el.dataset.aside === 'open' );

	column.toggle.focus();
	fire( column.toggle, 'click' );
	check( 'a press folds it: the strip is shown, the body is not, the button says so - and it is the same button, with the focus on it',
		column.collapsed() === true && column.body.hidden === true && column.rail.hidden === false && side.el.dataset.aside === 'folded' && column.toggle.getAttribute('aria-expanded') === 'false'
		&& column.toggle.focused === true && column.el.children[0].children[0] === column.toggle );

	column.collapse( false );
	check( 'collapse() does it from outside', column.collapsed() === false && column.toggle.getAttribute('aria-expanded') === 'true' );

	column.collapse( true );
	check( 'a view with the same id finds the column as it was left - held for the session, for each view - and another view does not',
		build( { id : 'aside-a', aside : { content : element('div'), label : 'Outline' } } ).aside.collapsed() === true && build( { id : 'aside-b', aside : { content : element('div'), label : 'Outline' } } ).aside.collapsed() === false );
	check( 'where nothing is held it starts as it is asked to', build( { id : 'aside-c', aside : { content : element('div'), label : 'Outline', collapsed : true } } ).aside.collapsed() === true && build( { aside : { content : element('div'), label : 'Outline' } } ).aside.collapsed() === false );
	check( 'a view without an id holds nothing: the next one starts as it is asked to', ( build( { aside : { content : element('div'), label : 'Outline' } } ).aside.collapse( true ), build( { aside : { content : element('div'), label : 'Outline' } } ).aside.collapsed() === false ) );
}

const css = source( '_admin/assets/style.css' );

{
	const sheet = css.replace( /\/\*[\s\S]*?\*\//g, '' );
	const rule = ( selector ) => ( sheet.split('}').map( text => text.split('{') ).find( parts => parts.length === 2 && parts[0].trim() === selector ) ?? [ '', '' ] )[1];

	check( 'in the form of a work surface the page does not scroll - the pane that holds one is a column with no overflow - and the surface takes what the context bar leaves',
		/overflow:\s*hidden/.test( rule('.nino-admin .nino-admin-pane.nino-admin-pane--surface') ) && /flex-direction:\s*column/.test( rule('.nino-admin .nino-admin-pane.nino-admin-pane--surface') )
		&& /flex:\s*1 1 0/.test( rule('.nino-admin .nino-admin-pane--surface .nino-admin-split--fill') ) );
	check( '...and what scrolls are the columns: the settings, the body of the side column, each for itself',
		/overflow-y:\s*auto/.test( rule('.nino-admin .nino-admin-split--fill .nino-admin-split-settings') ) && /overflow-y:\s*auto/.test( rule(':where(.nino-admin) .nino-admin-split-aside-body') ) );
	check( 'a block of a level that is hidden is not made a column by the rule that gives the surface its height', /pane--surface :has\(\.nino-admin-split--fill\):not\(\[hidden\]\)\s*\{/.test( sheet ) );
	check( '...with no frame of the card and no gap between the columns',
		/border:\s*0/.test( rule('.nino-admin .nino-admin-split--fill .nino-admin-split-preview') ) && /gap:\s*0/.test( rule('.nino-admin .nino-admin-split--fill > .nino-admin-split-grid') ) );
	check( 'the folded column is the strip of 48 px, a token, and the open one a column of a fixed width',
		/--nino-admin-aside-rail:\s*48px/.test( sheet ) && /grid-template-columns:\s*var\(--nino-admin-aside-rail\)/.test( sheet ) && /grid-template-columns:\s*var\(--nino-admin-aside-width\)/.test( sheet ) );
}
check( 'the switch is for the width the view has, not the window\'s: the root is the container the narrow rules ask',
	/\.nino-admin-split\s*\{[^}]*container:\s*nino-split\s*\/\s*inline-size/.test( css ) && /@container\s+nino-split\s*\(max-width/.test( css ) );
const block = css.slice( css.indexOf('@container nino-split') );
const narrow = block.slice( 0, block.indexOf('\n}\n') );
check( 'narrow, the switch is drawn', /nino-admin-split-switch\s*\{[^}]*display:\s*flex/.test( narrow ) );
check( '...and the pane the switch has not chosen is not', /\[data-view="settings"\][^{]*-preview,[^{]*\[data-view="preview"\][^{]*-settings\s*\{\s*display:\s*none/.test( narrow ) );

check( 'a scaled port is the box the frame is placed in and cut by',
	/\.nino-admin-split-port--scaled\s*\{[^}]*position:\s*relative[^}]*overflow:\s*hidden/.test( css ) );
check( '...and the frame in it is placed by the scaler, from its corner, and not held to the width of the pane',
	/\.nino-admin-split-port--scaled\s*>\s*\.nino-admin-split-frame\s*\{[^}]*position:\s*absolute[^}]*max-width:\s*none[^}]*transform-origin:\s*0 0/.test( css ) );

done();
