/**
 *	Nino										A compact filesystembased php framework
 *	nino-ui-actionbar-js-smoke.js	Nino.adminUi.actionBar(), say() and contextBar(): the bar of a screen in the one order of
 *													the guide, the toast of an action that went well, and the bar with the way back. Structure
 *													and behaviour only - no word, no pixel, no time limit.
 *
 *													Pinned: the order of the bar (the destructive action first, the main button last) and
 *													that there is exactly one main button; that "changed" is one display, which the shell's
 *													registry paints instead of drawing a mark of its own; that saveBar() is built on the
 *													same bar; that at most one toast is on the screen, announced as a status, never for an
 *													error, never in the bar and never over it, holding while the pointer or the focus is on
 *													it, and moving only where the person allows motion; that the way back says where it leads
 *													and the name of the panel stands small over it, the head of the pane giving way.
 *
 *	Usage: node tests/nino-ui-actionbar-js-smoke.js
 */

'use strict';

const vm = require('vm');
const fs = require('fs');
const path = require('path');
const { check, done, load, element, findAll, fire, fills } = require( './harness.js' );

const timers = [];
const page = element('div');
const body = element('body');
page.appendChild( body );

const sandbox = {
	console : console,
	document : {
		// An element that remembers the custom properties it is given
		createElement : function( tag ) {
			const el = element( tag );
			el.style = { props : {}, setProperty : function( name, value ) { el.style.props[name] = value } };
			return el;
		},
		createElementNS : function( space, tag ) { return element( tag ) },
		body : body,
		documentElement : null,
		querySelectorAll : function( selector ) { return body.querySelectorAll( selector ) },
		// What the page listens to: a menu that closes by a click beside it listens only while it is open
		listeners : { click : [] },
		addEventListener : function( name, fn ) { sandbox.document.listeners[name].push( fn ) },
		removeEventListener : function( name, fn ) { sandbox.document.listeners[name] = sandbox.document.listeners[name].filter( held => held !== fn ) },
	},
};
sandbox.setTimeout = function( fn, ms ) { timers.push( { fn : fn, ms : ms, live : true } ); return timers.length - 1 };
sandbox.clearTimeout = function( id ) { if( timers[id] !== undefined ) timers[id].live = false };
sandbox.window = sandbox;
// What the window is listened to for: a menu that closes where the page loses the focus to a frame listens only while it is open
sandbox.listeners = { blur : [] };
sandbox.addEventListener = function( name, fn ) { sandbox.listeners[name].push( fn ) };
sandbox.removeEventListener = function( name, fn ) { sandbox.listeners[name] = sandbox.listeners[name].filter( held => held !== fn ) };
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

const stylesheet = fs.readFileSync( path.join( __dirname, '../_admin/assets/style.css' ), 'utf8' );

const button = ( name, options ) => adminUi.button( name, null, options );
const classes = el => String( el.className ).split(' ');
const thrown = fn => { try { fn() } catch( error ) { return error.name === 'TypeError' } return false };

console.log( 'Nino.adminUi.actionBar - the bar of a screen\n' );

{
	const bar = adminUi.actionBar( {
		primary : button('Save'),
		secondary : [ button('Preview'), button('Duplicate') ],
		danger : button('Delete'),
		status : true,
	} );
	const order = bar.el.children.map( el => el === bar.danger ? 'danger' : ( el === bar.state ? 'state' : ( el === bar.line.element ? 'line' : ( el === bar.primary ? 'primary' : 'side' ) ) ) );

	check( 'the bar is an action bar of the system, in the one order: the destructive action, the state, the line, the side actions, the main button last',
		classes( bar.el ).includes('nino-admin-actionbar') === true && order.join() === 'danger,state,line,side,side,primary' );
	check( '...the destructive action has the class of its kind, the main button the class of its own', classes( bar.danger ).includes('nino-admin-btn-danger') === true && classes( bar.primary ).includes('nino-admin-btn-primary') === true );
}

{
	const bar = adminUi.actionBar( { primary : button('New') } );

	check( 'a bar without a status has neither state nor line: the bar of a list holds its main button alone', bar.state === undefined && bar.line === undefined && bar.el.children.length === 1 );
}

check( 'a bar has exactly one main button: a second one - in the list, as a side action, as the destructive one, or none at all - is refused',
	thrown( () => adminUi.actionBar( { primary : [ button('A'), button('B') ] } ) ) === true
	&& thrown( () => adminUi.actionBar( { primary : button('A'), secondary : [ button( 'B', { kind : 'primary' } ) ] } ) ) === true
	&& thrown( () => adminUi.actionBar( { primary : button('A'), danger : button( 'B', { kind : 'primary' } ) } ) ) === true
	&& thrown( () => adminUi.actionBar( { secondary : [ button('B') ] } ) ) === true );

check( 'the destructive action stands at the left alone: a second one among the side actions is refused', thrown( () => adminUi.actionBar( { primary : button('A'), secondary : [ button( 'D', { kind : 'danger' } ) ] } ) ) === true );

{
	const old = element('div');

	check( 'called with an element, that element becomes the bar and is returned', adminUi.actionBar( old ) === old && classes( old ).includes('nino-admin-actionbar') === true );
}

console.log( '\n... one display of "changed"\n' );

{
	const bar = adminUi.actionBar( { primary : button('Save'), status : true } );
	const shellBar = element('div');

	check( 'the state is one badge, and the shell\'s registry paints it: a bar that has one is told so, a bar that has none is not',
		adminUi.barState( bar.el, { dirty : true } ) === true && bar.state.dataset.state === 'warn' && findAll( bar.el, el => classes( el ).includes('nino-admin-actionbar-state') ).length === 1
		&& adminUi.barState( shellBar, { dirty : true } ) === false );

	adminUi.barState( bar.el, { dirty : false } );
	check( '...and takes it back when what is on the screen is saved again, with a mark and not by the colour alone', bar.state.dataset.state === 'ok' && bar.state.textContent.length > 1 );
}

{
	const w = adminUi.saveBar( { onSave : function() {} } );

	check( 'saveBar() is built on the same bar: its state is the badge of the bar, its Save the one main button, and the registry leaves its badge to it',
		classes( w.el ).includes('nino-admin-bar') === true && classes( w.state ).includes('nino-admin-actionbar-state') === true && w.el.children[w.el.children.length - 1] === w.save
		&& classes( w.save ).includes('nino-admin-btn-primary') === true && ( adminUi.barState( w.el, { dirty : true } ), w.state.dataset.state !== 'warn' ) );
}

console.log( '\nNino.adminUi.say - the toast\n' );

{
	const first = adminUi.say( 'Saved.' );
	const second = adminUi.say( 'Deleted.' );
	const host = findAll( body, el => classes( el ).includes('nino-admin-toasts') );
	const said = findAll( body, el => classes( el ).includes('nino-admin-toast-said') );
	const toasts = findAll( body, el => classes( el ).includes('nino-admin-toast') );

	check( 'at most one toast is on the screen: the new one takes the place of the old', host.length === 1 && toasts.length === 1 && toasts[0] === second && first.parentNode === null );
	check( '...the sentence is said in a live region that is a status and is in the page before the first sentence arrives; the mark, the card\'s own copy of the words and the button that closes are no part of it',
		said.length === 1 && said[0].getAttribute('role') === 'status' && said[0].getAttribute('aria-live') === 'polite' && said[0].parentNode === host[0] && second.parentNode === host[0]
		&& findAll( said[0], el => el.tagName === 'BUTTON' ).length === 0 && said[0].children.length === 1
		&& findAll( second, el => classes( el ).includes('nino-admin-toast-mark') )[0].getAttribute('aria-hidden') === 'true' && findAll( second, el => classes( el ).includes('nino-admin-toast-text') )[0].getAttribute('aria-hidden') === 'true'
		&& findAll( second, el => el.tagName === 'BUTTON' ).length === 1 && second.closest('[role="status"]') === null );
	check( '...and is no part of a bar: nothing in the toast is in an action bar', second.closest('.nino-admin-actionbar') === null );
}

check( 'a toast is for what went well alone: an error, a warning or a kind nobody knows is refused, and nothing is shown for it',
	[ 'error', 'danger', 'warning', 'fail', 'nope' ].every( kind => thrown( () => adminUi.say( 'x', { kind : kind } ) ) ) === true
	&& adminUi.say( 'x', { kind : 'info' } ).dataset.kind === 'info' && findAll( body, el => classes( el ).includes('nino-admin-toast') ).length === 1 );

{
	const toast = adminUi.say( 'Saved.' );
	const timer = () => timers[ timers.length - 1 ];

	check( 'a toast goes by itself', timer().live === true && toast.parentNode !== null );

	fire( toast, 'mouseenter' );
	check( '...not while the pointer rests on it, nor while the focus is in it', timer().live === false );
	fire( toast, 'mouseleave' );
	check( '...and it is on its way again when they leave', timer().live === true );
	fire( toast, 'focusin' );
	check( '...the focus holds it as the pointer does', timer().live === false );
	fire( toast, 'focusout' );
	timer().fn();
	check( '...and when its time is out it is gone', toast.parentNode === null );
}

{
	const toast = adminUi.say( 'Saved.' );
	const close = findAll( toast, el => el.tagName === 'BUTTON' )[0];

	check( 'the toast has a button that closes it, with a name', close.getAttribute('aria-label') !== null && close.getAttribute('aria-label') !== '' );
	fire( close, 'click' );
	check( '...and closes it', toast.parentNode === null );
}

const rule = ( source, selector ) => {
	const at = source.indexOf( selector+ ' {' );
	return at === -1 ? '' : source.slice( at, source.indexOf( '}', at ) );
};

check( 'the toast stands above the bar and never over it: its place is the height of the bar that is on the screen, measured into a custom property, with the height of the token where there is none',
	/bottom: calc\(var\(--nino-admin-toast-rise, var\(--nino-admin-actionbar-height\)\) \+ var\(--nino-admin-space-\d\)\)/.test( rule( stylesheet, '.nino-admin-toast' ) ) === true
	&& Number( /z-index: (\d+)/.exec( rule( stylesheet, '.nino-admin-toasts' ) )?.[1] ) > Number( /z-index: (\d+)/.exec( rule( stylesheet, '.nino-admin .nino-admin-actionbar' ) )?.[1] ) );

{
	const measured = element('div');
	measured.className = 'nino-admin-actionbar';
	measured.offsetHeight = 91;
	body.appendChild( measured );
	const over = adminUi.say( 'Saved.' );
	body.removeChild( measured );
	const alone = adminUi.say( 'Saved.' );

	check( 'the toast is given the height the bar has in the page - a bar of two rows included - and nothing where there is no bar to measure', over.style.props['--nino-admin-toast-rise'] === '91px' && alone.style.props['--nino-admin-toast-rise'] === undefined );
}

check( 'the toast moves only where the person allows motion: its animation is declared for no-preference alone',
	( stylesheet.match( /animation:\s*nino-admin-toast-in/g ) || [] ).length === 1
	&& /@media \(prefers-reduced-motion: no-preference\) \{\s*\.nino-admin-toast \{\s*animation: nino-admin-toast-in/.test( stylesheet ) === true );

{
	const words = [ '_admin/text/en_US.php', '_admin/text/de_DE.php' ].map( file => fills( file ) );

	check( 'the sentence of a save, the one of a deletion and the way back are fills in both languages, the way back with the place for the name',
		words.every( set => [ '/_admin/common/msg/saved', '/_admin/common/msg/deleted' ].every( key => ( set[key] ?? '' ) !== '' ) === true && ( set['/_admin/common/label/back-to'] ?? '' ).includes('%s') === true ) === true );
}

console.log( '\nNino.adminUi.contextBar - the way back\n' );

{
	const pane = element('div');
	pane.dataset.panel = 'routes';
	const head = element('div');
	head.className = 'admin-panel-head';
	const title = element('h2');
	title.className = 'admin-panel-title';
	title.textContent = 'Routes';
	head.appendChild( title );
	pane.appendChild( head );
	const form = element('div');
	pane.appendChild( form );

	const clicks = [];
	const bar = adminUi.contextBar( { back : { to : form, onClick : function() { clicks.push(1) } } } );
	const link = findAll( bar, el => classes( el ).includes('nino-admin-back-link') )[0];

	check( 'the way back says where it leads: the name of the panel the view stands in, which the head of the pane gives', link.textContent.includes('Routes') === true && link.tagName === 'A' );
	check( 'a name that is given is the name', findAll( adminUi.contextBar( { back : { to : 'Menus' } } ), el => classes( el ).includes('nino-admin-back-link') )[0].textContent.includes('Menus') === true );

	let prevented = 0;
	link.listeners.click[0]( { preventDefault : function() { prevented++ } } );
	check( '...it is one link, which asks the view to go back and does not follow its address', clicks.length === 1 && prevented === 1 && findAll( bar, el => classes( el ).includes('nino-admin-back-link') ).length === 1 );

	check( 'without the option the name of the panel is not drawn, and the head of the pane is not asked to give way', findAll( bar, el => classes( el ).includes('nino-admin-contextbar-panel') ).length === 0 && bar.dataset.panel === undefined );

	const surface = adminUi.contextBar( { panel : 'Themes', title : 'Brand', back : { to : 'Themes' }, tools : [ button('Tool'), button('Other') ] } );
	const small = findAll( surface, el => classes( el ).includes('nino-admin-contextbar-panel') )[0];
	const lead = findAll( surface, el => classes( el ).includes('nino-admin-contextbar-lead') )[0];

	check( 'the option panel puts the name of the panel small over the way back, and marks the bar for the stylesheet', small !== undefined && small.textContent === 'Themes' && lead.children[0] === small && lead.children[1].tagName === 'A' && surface.dataset.panel === 'Themes' );
	const unlabelled = adminUi.contextBar( { panel : 'Themes', panelLabel : false, title : 'Brand', back : { to : 'Themes' } } );

	check( 'panelLabel false draws no name of the panel - not hidden, not there - and the bar is still marked, so that the head of the pane gives way',
		findAll( unlabelled, el => classes( el ).includes('nino-admin-contextbar-panel') ).length === 0 && unlabelled.dataset.panel === 'Themes' && findAll( unlabelled, el => el.textContent === 'Themes' ).length === 0 );
	check( '...the way back and the title are what they are', findAll( unlabelled, el => classes( el ).includes('nino-admin-contextbar-lead') )[0].children.length === 1 && findAll( unlabelled, el => el.tagName === 'H3' )[0].textContent === 'Brand' );

	const name = surface.children[1];

	check( '...then the title of the view as a heading in the group of the name, and the tools at the right', classes( name ).includes('nino-admin-contextbar-name') === true && name.children[0].tagName === 'H3' && name.children[0].textContent === 'Brand'
		&& classes( surface.children[2] ).includes('nino-admin-contextbar-toolbox') === true );
}

console.log( '\nNino.adminUi.contextBar - the name of a surface and its tools\n' );

{
	const clicks = [];
	const bar = adminUi.contextBar( { title : 'About us', code : 'about-us', icons : [ { icon : 'panels-top-left', name : 'Frame', onClick : function() { clicks.push('frame') } } ] } );
	const group = bar.children.find( el => classes( el ).includes('nino-admin-contextbar-name') );
	const icon = group.children[2];

	check( 'a quiet identifier stands beside the title, and an icon button - made by iconButton(), so it has a name - beside that: one group',
		classes( group ).includes('nino-admin-contextbar-name') === true && group.children[0].tagName === 'H3' && classes( group.children[1] ).includes('nino-admin-contextbar-code') === true && group.children[1].tagName !== 'H3' && group.children[1].textContent === 'about-us'
		&& icon.tagName === 'BUTTON' && icon.getAttribute('aria-label') === 'Frame' && classes( icon ).includes('nino-admin-btn-icon') === true );

	fire( icon, 'click' );
	check( '...which does what it was given', clicks.join() === 'frame' );
	check( 'an icon button without a name is refused, as iconButton() refuses it', thrown( () => adminUi.contextBar( { icons : [ { icon : 'x' } ] } ) ) );
	check( 'the whole of the title and of the identifier is the title of what shows it, for the one that is cut', group.children[0].title === 'About us' && group.children[1].title === 'about-us' );

	adminUi.locales = { native : 'de_DE', names : {} };
	const badge = adminUi.localeBadge( 'en_US', bar );
	check( 'the badge of the language stands in the group of the name, and so before the "?" at the end of the bar', badge !== null && badge.parentNode === group && bar.children[bar.children.length - 1] !== badge );

	const sheet = stylesheet.replace( /\/\*[\s\S]*?\*\//g, '' );
	const rule = ( selector ) => ( sheet.split('}').map( text => text.split('{') ).find( parts => parts.length === 2 && parts[0].trim() === selector ) ?? [ '', '' ] )[1];
	const shrink = ( selector ) => Number( ( /flex:\s*0 (\d+) auto/.exec( rule( selector ) ) ?? [ 0, 0, 0 ] )[1] );

	check( 'the way back is never cut: the lead of a bar with the name of the panel does not shrink and its link does not wrap', /flex:\s*0 0 auto/.test( rule('.nino-admin .nino-admin-contextbar[data-panel] > .nino-admin-contextbar-lead') )
		&& /white-space:\s*nowrap/.test( rule('.nino-admin .nino-admin-contextbar[data-panel] .nino-admin-contextbar-lead .nino-admin-back-link') ) );
	check( '...a long title gives way and is cut with an ellipsis, the identifier beside it keeps its size', /flex:\s*0 0 auto/.test( rule('.nino-admin .nino-admin-contextbar-code') ) && shrink('.nino-admin .nino-admin-contextbar-title') === 1 && /flex:\s*0 1 auto/.test( rule('.nino-admin .nino-admin-contextbar-title') ) && /text-overflow:\s*ellipsis/.test( rule('.nino-admin .nino-admin-contextbar-title') ) );
	check( 'a bar with neither title, identifier nor icons has no group of the name', adminUi.contextBar( { back : { to : 'x' } } ).children.every( el => classes( el ).includes('nino-admin-contextbar-name') === false ) );
}

{
	const document = sandbox.document;
	const first = button('First');
	const second = button('Second');
	const bar = adminUi.contextBar( { title : 'View', tools : [ first, second ] } );
	const box = bar.children.find( el => classes( el ).includes('nino-admin-contextbar-toolbox') );
	const [ toggle, menu ] = box.children;

	check( 'the tools stand in a group, behind ONE icon button that is named and says whether the menu is open and which menu it is',
		toggle.tagName === 'BUTTON' && toggle.getAttribute('aria-label') === fills( '_admin/text/en_US.php' )['/_admin/common/preview/tools'] && toggle.getAttribute('aria-expanded') === 'false' && toggle.getAttribute('aria-controls') === menu.id && menu.id !== ''
		&& menu.children.length === 2 && menu.children[0] === first && menu.children[1] === second );

	fire( toggle, 'click' );
	check( 'a press opens the menu - the bar says so for the stylesheet, the button says so for a screen reader - and the page is listened to for a click beside it',
		bar.dataset.tools === 'open' && toggle.getAttribute('aria-expanded') === 'true' && document.listeners.click.length === 1 );

	const beside = document.listeners.click[0];
	beside( { target : first } );
	check( 'a click in the menu leaves it open', bar.dataset.tools === 'open' );
	beside( { target : element('div') } );
	check( 'a click beside it closes it and stops listening', bar.dataset.tools === 'closed' && toggle.getAttribute('aria-expanded') === 'false' && document.listeners.click.length === 0 );

	fire( toggle, 'click' );
	let stopped = 0;
	fire( bar, 'keydown', { key : 'Escape', stopPropagation : function() { stopped++ } } );
	check( 'Esc closes it, keeps the key from closing anything else, and the focus is on the button', bar.dataset.tools === 'closed' && stopped === 1 && toggle.focused === true && document.listeners.click.length === 0 );

	fire( bar, 'keydown', { key : 'Escape', stopPropagation : function() { stopped++ } } );
	check( 'Esc with the menu closed is not taken', stopped === 1 );

	fire( toggle, 'click' );
	check( 'the window is listened to for the loss of the focus while the menu is open', sandbox.listeners.blur.length === 1 );
	fire( box, 'focusout', { relatedTarget : first } );
	check( 'the focus moving within the menu leaves it open', bar.dataset.tools === 'open' );
	fire( box, 'focusout', { relatedTarget : null } );
	check( '...and to nothing - a click on a surface - too: that click says it', bar.dataset.tools === 'open' );
	fire( box, 'focusout', { relatedTarget : element('input') } );
	check( 'the focus leaving it for something else closes it', bar.dataset.tools === 'closed' && sandbox.listeners.blur.length === 0 );
	fire( toggle, 'click' );
	sandbox.listeners.blur[0]();
	check( 'the page losing the focus - a click into the preview - closes it', bar.dataset.tools === 'closed' && sandbox.listeners.blur.length === 0 );

	check( 'a bar with no tools has no menu and no button for it', adminUi.contextBar( { title : 'View' } ).children.every( el => classes( el ).includes('nino-admin-contextbar-toolbox') === false ) );

	const sheet = stylesheet.replace( /\/\*[\s\S]*?\*\//g, '' );
	const at = sheet.indexOf('@container nino-contextbar (max-width: 56rem)');
	const narrow = sheet.slice( at, sheet.indexOf( '\n}\n', at ) );

	check( 'the bar is the container of its tools - its own width decides, not the window\'s - and wide the button is out of the way',
		/\.nino-admin-contextbar\s*\{[^}]*container:\s*nino-contextbar\s*\/\s*inline-size/.test( sheet ) && /\.nino-admin-contextbar-tools-toggle\s*\{\s*display:\s*none/.test( sheet ) );
	check( '...narrow the button is there and the tools are a menu that is out of the page until the bar says it is open',
		at !== -1 && /contextbar-tools-toggle\s*\{\s*display:\s*inline-flex/.test( narrow ) && /contextbar-tools\s*\{[^}]*position:\s*absolute[^}]*display:\s*none/.test( narrow ) && /\[data-tools="open"\][^{]*contextbar-tools\s*\{\s*display:\s*flex/.test( narrow ) );
}

// The bar that counts is one on the screen: it is in a child of the pane that is not hidden and has no hidden ancestor of its own (a leaf of a split view, a level left behind), so that a hidden level's bar does not keep the head away
const live = '\\[data-panel\\]:has\\(> :not\\(\\.admin-hidden\\):not\\(\\[hidden\\]\\) \\.nino-admin-contextbar\\[data-panel\\]:not\\(:is\\(\\.admin-hidden, \\[hidden\\]\\) \\*\\)\\) > \\.admin-panel-head';

check( 'where a bar has the name of the panel the head of the pane gives way: out of the page and out of the tab order, its heading left for a screen reader',
	new RegExp( live+ ' \\{[^}]*clip-path: inset\\(50%\\)' ).test( stylesheet ) === true
	&& new RegExp( live+ ' > :not\\(\\.admin-panel-title\\) \\{\\s*display: none' ).test( stylesheet ) === true );
check( '...and a bar in a hidden level - hidden, or inside one that is - is not one of them: the head is there with the list again', stylesheet.split( ':not(:is(.admin-hidden, [hidden]) *)' ).length === 3 );

{
	const back = element('a');
	const tool = element('button');
	const bar = adminUi.contextBar( back, [ tool ] );

	check( 'called with a link and its controls it is the same row as ever', bar.children.length === 2 && bar.children[0] === back && bar.children[1] === tool && classes( bar ).join() === 'nino-admin-contextbar' );
}

{
	const pane = element('div');
	pane.dataset.panel = 'themes';
	const head = element('div');
	head.className = 'admin-panel-head';
	const title = element('h2');
	title.className = 'admin-panel-title';
	title.textContent = 'Themes';
	const manual = element('button');
	manual.className = 'admin-panel-manual';
	manual.dataset.manual = 'themes';
	manual.setAttribute( 'aria-label', 'Manual' );
	head.appendChild( title );
	head.appendChild( manual );
	pane.appendChild( head );
	const list = element('div');
	list.id = 'themes-list';
	const form = element('div');
	form.id = 'themes-form';
	form.classList.add('admin-hidden');
	pane.appendChild( list );
	pane.appendChild( form );

	const bar = adminUi.contextBar( { panel : 'Themes', back : { to : form } } );
	const help = findAll( bar, el => el.tagName === 'BUTTON' )[0];

	check( 'with the name of the panel the "?" of the head goes into the bar, as an icon button at its right end - the same trigger and the same name',
		help !== undefined && help.dataset.manual === 'themes' && help.getAttribute('aria-label') === 'Manual' && bar.children[bar.children.length - 1] === help && findAll( help, el => el.tagName === 'SVG' ).length === 1 );
	check( '...and without the name of the panel there is none', findAll( adminUi.contextBar( { back : { to : form } } ), el => el.tagName === 'BUTTON' ).length === 0 );
	check( '...and with panelLabel false the "?" is still in the bar', findAll( adminUi.contextBar( { panel : 'Themes', panelLabel : false, back : { to : form } } ), el => el.tagName === 'BUTTON' && el.dataset.manual === 'themes' ).length === 1 );

	form.appendChild( bar );
	form.classList.remove('admin-hidden');
	adminUi.showLevel( list );

	check( 'back to the list hides the level that has the bar - the rule that lets the head give way holds for the level on the screen only - and the list is on the screen', form.classList.contains('admin-hidden') === true && list.classList.contains('admin-hidden') === false );
}

console.log( '\n... what a bar keeps to\n' );

{
	const rules = source => ( source.match( /[^{}]+\{[^}]*\}/g ) || [] ).map( text => ( { selector : text.slice( 0, text.indexOf('{') ).trim(), body : text.slice( text.indexOf('{') + 1 ) } ) );
	const all = rules( stylesheet.replace( /\/\*[\s\S]*?\*\//g, '' ) );
	const margin = ( selector ) => all.some( rule => rule.selector.split(',').map( part => part.trim() ).includes( selector ) && /margin-right:\s*auto/.test( rule.body ) );

	check( 'the destructive action stands at the left whatever the line holds: the state - where there is none, the action - takes the free room to its right, so nothing depends on a line that is out of the way',
		margin( '.nino-admin .nino-admin-actionbar.nino-admin-bar > .nino-admin-actionbar-state' ) === true
		&& margin( '.nino-admin .nino-admin-actionbar.nino-admin-bar:not(:has(> .nino-admin-actionbar-state)) > .nino-admin-btn-danger' ) === true
		&& all.some( rule => rule.selector.includes('.nino-admin-actionbar .nino-admin-btn-danger') && /order:\s*-2/.test( rule.body ) ) === true );

	const hiding = all.filter( rule => rule.selector.includes('.nino-admin-bar:not(.nino-admin-savebar) > .nino-admin-status') );

	check( 'what keeps a line out of the way is said of its state "dirty" alone - an error, a saving and an empty line are never hidden by it', hiding.length > 0 && hiding.every( rule => rule.selector.split(',').every( selector => selector.trim().endsWith( '[data-state="dirty"]' ) ) === true ) );

	check( 'a bar of a list is not the bar of a state: it is drawn as every bar of a list is, the same size of button at every width', adminUi.actionBar( { list : true, primary : button('New') } ).el.className.split(' ').includes('nino-admin-bar') === false
		&& classes( adminUi.actionBar( { primary : button('Save'), status : true } ).el ).includes('nino-admin-bar') === true );
}

console.log( '\n... a thing that was never saved says nothing of being saved\n' );

{
	const bar = adminUi.actionBar( { primary : button('Save'), status : { fresh : true } } );
	const shown = () => bar.state.hidden === false && bar.state.textContent !== '';

	check( 'a fresh bar has no badge until the first change: the registry\'s "nothing unsaved" shows nothing either', shown() === false && ( adminUi.barState( bar.el, { dirty : false } ), shown() === false ) );

	adminUi.barState( bar.el, { dirty : true } );
	check( '...the first change says "changed"', shown() === true && bar.state.dataset.state === 'warn' );

	adminUi.barState( bar.el, { dirty : false, fresh : false } );
	check( '...and once it is saved the badge says "saved", which is then true', shown() === true && bar.state.dataset.state === 'ok' );

	const w = adminUi.saveBar( { fresh : true, onSave : function( answer ) { w.answer = answer } } );
	check( 'a saveBar() of a fresh screen is the same: nothing until a change, "saved" after the first save', w.state.hidden === true
		&& ( w.set( { dirty : true } ), w.state.hidden === false && w.state.dataset.state === 'warn' )
		&& ( fire( w.save, 'click' ), w.answer( true ), w.state.hidden === false && w.state.dataset.state === 'ok' ) );
}

done();
