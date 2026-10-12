/**
 * Dependency-free structural checks for _admin: the shared design system and
 * the scripts, stylesheets and words of its panels.
 *
 * Usage: node tests/admin-lists-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { check, done, element, fire } = require( './harness.js' );

function asset( filename ) {
	return fs.readFileSync( path.join( __dirname, '../_admin/assets/', filename ), 'utf8' );
}

// A module's own workbench panel ships beside the module, not in _admin/assets:
// an optional kernel module's below _nino/Nino/Modules/<Name>/, an installed
// feature's below features/<Name>/ (see \Nino\Features)
function moduleDir( module ) {
	const kernel = path.join( __dirname, '../_nino/Nino/Modules/', module );
	return fs.existsSync( kernel ) ? kernel : path.join( __dirname, '../features/', module );
}
function moduleAsset( module, filename ) {
	return fs.readFileSync( path.join( moduleDir( module ), 'assets', filename ), 'utf8' );
}

// ...and so do the workbench's own screens: _admin holds the shell, every
// panel in it is a module under _admin/Nino/Modules/<Name>/ (see
// \Nino\Admin\Admin::modules()). The directory is the list - nothing here
// names the panels, exactly as the registry does not
const ADMIN_MODULES = fs.readdirSync( path.join( __dirname, '../_admin/Nino/Modules' ) ).sort();
function adminAsset( module, filename ) {
	return fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/', module, 'assets', filename ), 'utf8' );
}
function adminModuleText( module, locale ) {
	return fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/', module, 'text', locale+ '.php' ), 'utf8' );
}
// Every .js a module ships, as [ '<Module>/<file>', source ]
function moduleScripts( root, modules ) {
	return modules.flatMap( function( m ) {
		const dir = path.join( __dirname, '..', root, m, 'assets' );
		return fs.existsSync( dir ) ? fs.readdirSync( dir ).filter( f => f.endsWith('.js') )
			.map( f => [ m+ '/'+ f, fs.readFileSync( path.join( dir, f ), 'utf8' ) ] ) : [];
	} );
}
// The optional kernel modules that bring a panel, and every feature the
// checkout carries - the directory is the list, as for the workbench's own
const KERNEL_MODULES = [ 'Form', 'Navigation' ];
const FEATURES = fs.readdirSync( path.join( __dirname, '../features' ), { withFileTypes : true } ).filter( e => e.isDirectory() ).map( e => e.name ).sort();
const APP_MODULES = KERNEL_MODULES.concat( FEATURES );

// The words a panel renders: the workbench's own text/<locale>.php, and a
// module's text() directory beside its panel
function workbenchText( locale ) {
	return fs.readFileSync( path.join( __dirname, '../_admin/text/', locale+ '.php' ), 'utf8' );
}
function moduleText( module, locale ) {
	return fs.readFileSync( path.join( moduleDir( module ), 'text', locale+ '.php' ), 'utf8' );
}

console.log('Workbench modules');

check( 'every workbench module is a panel class the registry can find, with its words beside it',
	ADMIN_MODULES.every( m => fs.existsSync( path.join( __dirname, '../_admin/Nino/Modules', m, 'Admin/Admin.php' ) )
		&& [ 'en_US', 'de_DE' ].every( l => fs.existsSync( path.join( __dirname, '../_admin/Nino/Modules', m, 'text', l+ '.php' ) ) ) ) );

console.log('\nAdmin tab strips, panel gates and stylesheets');

const adminUiSource = fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' );

/*	A tab strip is announced as a tablist, and a tablist is walked with the
	arrows rather than with Tab - the pattern's own keyboard contract, and the
	one half all three of the workbench's strips were missing: the pane strip
	the shell renders and the Features panel's two were reachable with a
	pointer and with Enter, and Left/Right/Home/End did nothing at all. It
	lives in the design system because there are three of them, which is
	exactly how one ends up without it.

	Driven here rather than read: what this is about is which tab ends up
	focused and which one is left in the page's tab order	*/
const tabSandbox = {
	console 	: console,
	document 	: { createElement : element, documentElement : {}, body : {} },
	Nino 			: { content : { getText : function() { return '' } } },
};
tabSandbox.window = tabSandbox;
vm.runInContext( adminUiSource, vm.createContext( tabSandbox ), { filename : 'Nino.admin.js' } );
const adminUi = tabSandbox.Nino.adminUi;

check( 'the design system owns the tab strip\'s keyboard half', typeof adminUi.tabKeys === 'function' );

const strip = [ element('button'), element('button'), element('button') ];
const moved = [];
adminUi.tabKeys( strip, function( at ) { moved.push( at ) } );

let stripPrevented = false;
fire( strip[0], 'keydown', { key : 'ArrowRight', preventDefault : function() { stripPrevented = true } } );
check( 'an arrow on a tab moves the strip rather than the page', stripPrevented === true && moved[0] === 1 && strip[1].focused === true );
check( '...and takes the one tab stop with it', strip[1].tabIndex === 0 && strip[0].tabIndex === -1 && strip[2].tabIndex === -1 );

fire( strip[1], 'keydown', { key : 'End', preventDefault : function() {} } );
check( 'End jumps to the last tab', moved[1] === 2 && strip[2].focused === true && strip[2].tabIndex === 0 );
fire( strip[2], 'keydown', { key : 'ArrowRight', preventDefault : function() {} } );
check( '...and the strip wraps rather than stopping at its end', moved[2] === 0 && strip[0].tabIndex === 0 );

// The row component is what the Features panel builds its two strips with,
// so a row flagged as a tablist has to come out of it with the same contract
const rowButtons = { a : element('button'), b : element('button') };
const rowPicked = [];
adminUi.buttonRow( rowButtons, 'a', function( key ) { rowPicked.push( key ) }, 'aria-selected' );
check( 'a button row flagged as a tablist is one tab stop, on the selected tab', rowButtons.a.tabIndex === 0 && rowButtons.b.tabIndex === -1 );
fire( rowButtons.a, 'keydown', { key : 'ArrowRight', preventDefault : function() {} } );
check( '...and the arrows pick the next tab, not just focus it', rowPicked[0] === 'b' && rowButtons.b.attributes['aria-selected'] === 'true' && rowButtons.b.tabIndex === 0 );

// A switch is not a tablist: aria-pressed buttons are ordinary buttons and
// each of them is its own tab stop
const switchButtons = { on : element('button'), off : element('button') };
adminUi.buttonRow( switchButtons, 'on', function() {} );
check( 'a row of pressed-state buttons keeps every button in the tab order', switchButtons.on.tabIndex === 0 && switchButtons.off.tabIndex === 0
	&& switchButtons.on.listeners.keydown === undefined );

const languageSource = adminAsset( 'Language', 'admin.js' );
check( 'Language lays out the locale input and its Add button as one in-flow row',
	/#admin-page-wrap \.admin-language-adder \{[^}]*display: grid;[^}]*grid-template-columns: minmax\(0, 1fr\) auto;/s.test( adminAsset( 'Language', 'admin.css' ) ) );

/*	The shell shows a panel by calling its showCurrent(), and a panel that
	builds once gates init() there on `_ready === false`. Language got the
	gate without the flag it reads: `_ready` was set on the first answer and
	never declared, so `undefined === false` was false and the panel built
	zero times rather than once. The gate is the panel's own code; so is the
	declaration - and one is nothing without the other	*/
const gatedPanels = fs.readdirSync( path.join( __dirname, '../_admin/Nino/Modules' ) )
	.filter( function( m ) { return fs.existsSync( path.join( __dirname, '../_admin/Nino/Modules', m, 'assets', 'admin.js' ) ) } )
	.map( function( m ) { return [ m, adminAsset( m, 'admin.js' ) ] } )
	.filter( function( e ) { return /\._ready === false/.test( e[1] ) } );
const undeclaredGates = gatedPanels.filter( function( e ) { return /_ready\s*:\s*false/.test( e[1] ) === false } ).map( function( e ) { return e[0] } );
check( 'every panel that gates init() on _ready declares it'+ ( undeclaredGates.length > 0 ? ' - undeclared in '+ undeclaredGates.join(', ') : '' ), gatedPanels.length >= 3 && undeclaredGates.length === 0 );

// ...and the one that lost its form to that: shown, it asks for its list
const languageSandbox = {
	console 	: console,
	document 	: { getElementById : function() { return {} }, documentElement : {}, body : {} },
	Nino 		: { events : { bindCallback : function() {} }, http : { sendRequest : function() {} } },
};
languageSandbox.window = languageSandbox;
vm.runInContext( languageSource, vm.createContext( languageSandbox ), { filename : 'language.js' } );
const languagePanel = languageSandbox.Nino.admin.language;
let languageLists = 0;
languagePanel._apiCall = function( endpoint ) { if( endpoint === 'list' ) languageLists++ };
languagePanel.showCurrent();
check( 'Language fetches its form when its panel is first shown', languageLists === 1 );
languagePanel._ready = true;
languagePanel.showCurrent();
check( '...and not again once the answer has rendered it', languageLists === 1 );

const css = asset('style.css');
// One stylesheet, two halves: the design system is the nino.system layer at
// the top, the workbench's own rules follow in nino.tool (see its header)
const sharedCss = css.slice( css.indexOf('@layer nino.system {'), css.indexOf('@layer nino.tool {') );
const workbenchCss = css.slice( css.indexOf('@layer nino.tool {') );
// The bar a list screen pins to the bottom carries whole sentences ("Scan the
// templates for missing keys"), and it is fixed: without wrapping, two of them
// on a phone are not scrolled, they are pushed off the left edge and lose
// their beginning
check( 'the list action bar wraps, so a long action label cannot be pushed off a phone screen',
	/\.nino-admin-list-actions \{[^}]*flex-wrap: wrap;/s.test( sharedCss ) );
// The two bar controls in the account row are one hit area each, not one
// glyph each: sized by the button, so the pair cannot drift apart and the
// theme toggle cannot change height with the theme it is showing
check( 'the rail\'s icon buttons share one box, independent of the icon inside them',
	/#admin-nav-ui-toggle, #admin-theme-toggle \{[^}]*width: 2rem;[^}]*height: 2rem;/s.test( workbenchCss ) &&
	/#admin-theme-toggle \.admin-theme-toggle-system \{\s*display:block;/s.test( workbenchCss ) );
// The pane's head is the pane's own bar, not a title floating in the
// content: the context bar's surface, edge to edge, closing at the top. The
// name brings no margin of its own, so the row's padding is the one spacing
// and a pane with a strip sits at the height of one without; the strip
// brings no rule of its own, so the open tab's underline is drawn on the
// head's hairline rather than a step above it; and in a workspace, where
// the pane has no padding to bleed through, the head does not bleed - a
// rule that has to stand in the workbench layer, since a later layer wins
// whatever the selector
check( 'a pane\'s head carries the workbench bar surface, full width and flush to the top',
	/#admin-content-wrap > \[data-panel\] > \.admin-panel-head \{[^}]*margin: -1rem calc\(var\(--nino-admin-content-inline\) \* -1\)[^}]*background: var\(--editor-bar-bg\);/s.test( workbenchCss ) );
check( '...its name brings no margin of its own, and its strip no rule of its own',
	/> \.admin-panel-head > \.admin-panel-title \{[^}]*margin: 0;/s.test( workbenchCss ) && /> \.admin-panel-head > \.admin-panel-tabs \{[^}]*border-bottom: 0;/s.test( workbenchCss ) );
check( '...and in a workspace the head does not bleed, said in the layer that can say it',
	/#admin-page-wrap\.nino-admin-shell--workspace #admin-content-wrap > \[data-panel\] > \.admin-panel-head \{\s*margin: 0;/s.test( workbenchCss ) && sharedCss.includes('.admin-panel-head') === false );

/*	Taking the browser's ring off a control is only allowed where something
	of equal visibility replaces it (WCAG 2.2 SC 2.4.7 and 1.4.11). Two of the
	design system's three `outline: none` rules put an inset box-shadow back;
	the third, on a clickable table row, replaced it with the same tint the
	row already uses for hover - 1.15:1 against the card in the light scheme,
	1.26:1 in the dark one, where the rule asks for 3:1 - so a keyboard could
	walk the rows and could not see which one it was on. The sweep is over
	every rule that removes an outline, since the next one to do it is the
	next one to forget	*/
const unringed = [];
sharedCss.replace( /([^{}]+)\{([^}]*)\}/g, function( rule, selector, body ) {
	if( /outline:\s*(none|0)/.test( body ) === true && /box-shadow:\s*(?!none)/.test( body ) === false )
		unringed.push( selector.trim().split('\n').pop().trim() );
	return rule;
} );
check( 'no shared rule takes a focus ring off without putting one back'+ ( unringed.length ? ' - '+ unringed.join(' | ') : '' ), unringed.length === 0 );
check( 'a clickable table row draws its own ring, inside the row the scroller would clip',
	/:where\(\.nino-admin\) \.nino-admin-table tbody tr:focus-visible \{[^}]*outline: 2px solid var\(--editor-blue\);[^}]*outline-offset: -2px;/s.test( sharedCss ) );

/*	Two tab strips, one class each: the segmented one (equal boxes, the open
	tab filled) and --bar (natural widths, an underline). A :not() weighs as
	much as the class it names, so a segmented rule that excludes only the
	head's --panel strip outweighs every --bar rule with one class fewer, and
	a --bar strip outside the head drew as boxes with an underline's colours -
	which is how three panels came to have three tab designs. Every segmented
	rule excludes --bar as well, and the --bar strip lays itself out, since
	the segmented container no longer does it for it	*/
const segmentedRulesReachingBar = [];
sharedCss.replace( /([^{}]+)\{([^}]*)\}/g, function( rule, selector, body ) {
	selector.trim().split('\n').pop().split(',').forEach( function( one ) {
		if( one.includes('.nino-admin-tabs:not(.nino-admin-tabs--panel)') && one.includes(':not(.nino-admin-tabs--bar)') === false )
			segmentedRulesReachingBar.push( one.trim() );
	} );
	return rule;
} );
check( 'no segmented-strip rule reaches a --bar strip'+ ( segmentedRulesReachingBar.length ? ' - '+ segmentedRulesReachingBar.join(' | ') : '' ), segmentedRulesReachingBar.length === 0 );
check( '...and the --bar strip lays itself out', /\.nino-admin-tabs--bar:not\(\.nino-admin-tabs--panel\) \{[^}]*display: flex;/s.test( sharedCss ) );

// A panel's own rules travel with it, in a stylesheet of the same shape every
// module's has: the layer order first (it is idempotent, and the file may be
// bundled before the shell's one day), then everything inside nino.tool. An
// unbalanced one would swallow the rules of every module bundled after it
const moduleStyles = ADMIN_MODULES.filter( m => fs.existsSync( path.join( __dirname, '../_admin/Nino/Modules', m, 'assets/admin.css' ) ) );
check( 'every module stylesheet opens with the shared layer order and adds only to nino.tool',
	moduleStyles.length > 0 && moduleStyles.every( function( m ) {
		const own = adminAsset( m, 'admin.css' );
		return own.trimStart().startsWith('@layer nino.system, nino.tool, nino.local;')
			&& own.includes('@layer nino.tool {')
			&& own.split('{').length === own.split('}').length;
	} ) );
check( 'and the shell\'s own stylesheet is balanced too, so a module\'s rules land in the layer they say',
	css.split('{').length === css.split('}').length );

/*	...and every selector in one starts at an id of the panel's own. Every
	module stylesheet is bundled into the same page as every other module's,
	so a bare class in one is a rule over the whole workbench - and the class
	that prompted this, .admin-form-actions, sat in Users' sheet. The file
	docblocks have promised the scoping all along; nothing checked it	*/
function unscopedSelectors( styleSheet ) {

	const out		= [];
	const body	= styleSheet.replace( /\/\*[\s\S]*?\*\//g, '' );
	const rules	= /([^{}]+)\{/g;
	let match;

	while( ( match = rules.exec( body ) ) !== null ) {

		const selector = match[1].trim();

		// An at-rule's own prelude (@layer, @media) is not a selector - its
		// block's contents are matched on the next pass round
		if( selector === '' || selector.startsWith('@') === true )
			continue;

		// Split on the commas that separate selectors, not on the ones inside
		// an :is()/:not()/:where() list
		let depth = 0, part = '';
		const parts = [];
		for( const ch of selector ) {
			if( ch === '(' ) depth++;
			else if( ch === ')' ) depth--;
			if( ch === ',' && depth === 0 ) { parts.push( part ); part = ''; continue; }
			part += ch;
		}
		parts.push( part );

		parts.map( p => p.trim() ).filter( p => p !== '' && p.startsWith('#') === false ).forEach( p => out.push( p ) );
	}

	return out;
}

const strayRules = moduleStyles.map( m => [ m, unscopedSelectors( adminAsset( m, 'admin.css' ) ) ] ).filter( e => e[1].length > 0 );
check( 'every selector a module stylesheet adds starts at one of the panel\'s own ids'+ ( strayRules.length === 0 ? '' : ' - '+ strayRules.map( e => e[0]+ ': '+ e[1].join(', ') ).join('; ') ),
	moduleStyles.length > 0 && strayRules.length === 0 );

// --- one language, one text system ---------------------------------------
//
// A panel that writes a sentence into the dom says it in whatever language it
// was written in, and no locale file can reach it. The workbench has one text
// system and every screen speaks through it; this is the shape a regression
// takes - a literal assigned straight to what the user reads.
// A checkout may carry no feature at all, so the feature scripts are listed
// from the directory rather than through a shell glob that would fail empty
const panelScripts = require('child_process')
	.execSync( "ls _admin/Nino/Modules/*/assets/*.js _nino/Nino/Modules/*/assets/*.js", { cwd : path.join( __dirname, '..' ) } )
	.toString().trim().split('\n')
	.concat( moduleScripts( 'features', FEATURES ).map( e => 'features/'+ e[0].replace( '/', '/assets/' ) ) );

const hardcoded = [];
panelScripts.forEach( function( file ) {
	const source = fs.readFileSync( path.join( __dirname, '..', file ), 'utf8' );
	source.split('\n').forEach( function( line, index ) {
		if( /^\s*(\/\/|\*|\/\*)/.test( line ) )
			return;
		// A sentence: two words or more, starting with a capital. Single words
		// are as often a slug, an http verb or a key as they are a label
		const re = /\.(?:textContent|placeholder|title)\s*=\s*'([A-Z][^']*\s[^']*)'/g;
		let match;
		while( ( match = re.exec( line ) ) )
			hardcoded.push( file+ ':'+ ( index + 1 )+ ' '+ JSON.stringify( match[1] ) );
	} );
} );
check( 'no panel writes a sentence of its own into the dom'+ ( hardcoded.length ? ' - '+ hardcoded.slice( 0, 5 ).join( ', ' ) : '' ), hardcoded.length === 0 );

// The same for a panel's markup. A workspace panel renders a whole .tpl into
// its pane rather than building everything from script, and that file is the
// other place a sentence can hide - it was where the Template Builder kept
// most of its own. No kernel module ships one any more; a feature's panel is
// held to this by the feature's own test, since a catalogue install is out of
// this sweep's reach
const panelMarkup = require('child_process')
	.execSync( "ls _nino/Nino/Modules/*/templates/panel.tpl _admin/templates/page-index.tpl 2>/dev/null || ls _admin/templates/page-index.tpl", { cwd : path.join( __dirname, '..' ), shell : '/bin/sh' } )
	.toString().trim().split('\n');

const hardcodedMarkup = [];
panelMarkup.forEach( function( file ) {
	const source = fs.readFileSync( path.join( __dirname, '..', file ), 'utf8' );
	source.split('\n').forEach( function( line, index ) {
		let match;
		// Text between two tags, and the attributes a person reads. A fill and
		// a lone symbol are fine; two words of prose are not
		const between = />([^<>{}\[\]]*[A-Za-zÄÖÜ][a-zäöüß]+\s+[A-Za-z][^<>{}\[\]]*)</g;
		while( ( match = between.exec( line ) ) )
			hardcodedMarkup.push( file+ ':'+ ( index + 1 )+ ' '+ JSON.stringify( match[1].trim() ) );
		const attrs = /(?:placeholder|aria-label|title|alt)="([^"\[]*[A-Za-zÄÖÜ][a-zäöüß]+\s+[A-Za-z][^"]*)"/g;
		while( ( match = attrs.exec( line ) ) )
			hardcodedMarkup.push( file+ ':'+ ( index + 1 )+ ' @'+ JSON.stringify( match[1] ) );
	} );
} );
check( 'no panel writes a sentence of its own into its markup'+ ( hardcodedMarkup.length ? ' - '+ hardcodedMarkup.slice( 0, 5 ).join( ', ' ) : '' ), hardcodedMarkup.length === 0 );

console.log('\nDesign system contracts');

// --- design-system conventions (see docs/recipes/admin-panel.md, "Designing an admin frontend")

const cssRoot = require('path').join(__dirname, '..');
const read = p => require('fs').readFileSync( require('path').join(cssRoot, p), 'utf8' );

const shared = sharedCss;

// Selectors only: hex colours and the prose in the file header are not.
const sharedRules = shared.replace(/\/\*[\s\S]*?\*\//g, '')
	.replace(/url\((?:[^)(]|\([^)]*\))*\)/g, '')
	.replace(/#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/g, '');

check( 'the shared design system uses no id selectors',
	/#[a-zA-Z][a-zA-Z0-9_-]*/.test( sharedRules ) === false );

// .main-title/.main-uri predate the namespace and are still shared
// markup; @layer names are not classes
check( 'the shared design system styles only nino-admin-* classes',
	( sharedRules.match(/\.(?!nino-admin)[a-zA-Z][a-zA-Z0-9_-]*/g) || [] )
		.filter( c => [ '.main-title', '.main-title--withuri', '.main-uri',
		                '.active', '.tool', '.system', '.local' ].includes( c ) === false )
		// State modifiers are the house convention (.is-dirty, .is-error, ...):
		// they qualify a design-system component rather than name one, so they
		// stay unprefixed
		.filter( c => /^\.is-/.test( c ) === false )
		.length === 0 );

const fs2 = require('fs'), path2 = require('path');

const TOOL_ROOTS = {
	'_admin/assets/style.css'                        : '#admin-page-wrap',
	'_admin/install/assets/style.css'                  : '#install-page-wrap',
};

// The workbench's own stylesheet carries the design system in its first half;
// the rules below only concern the half that belongs to one screen
const ownRules = file => file === '_admin/assets/style.css' ? workbenchCss : read( file );

Object.keys( TOOL_ROOTS ).forEach( function( file ) {

	const css = read( file );

	check( file+ ' opens with the shared layer order',
		css.trimStart().startsWith('@layer nino.system, nino.tool, nino.local;') );

	check( file+ ' wraps its own rules in the tool layer',
		css.includes('@layer nino.tool {') && css.includes('@layer nino.local {') );

	// A rule naming a shared class without a scope reaches every screen its file
	// is loaded on - which is how _editor's shell grid once laid out the
	// Template Builder, back when three of the four tools cross-loaded it
	const unscoped = ( ownRules( file ).match(/^[\t ]*\.nino-admin[^{,\n]*[,{]\s*$/gm) || [] );
	check( file+ ' scopes every nino-admin-* rule to its own root', unscoped.length === 0,
		unscoped.slice(0, 3).join(' | ') );
} );

// An element-wide reset belongs to the design system, the one file all four
// tools load. A tool stylesheet defining one used to reach every other tool that
// cross-loaded the file: the select chevron lived in _editor's copy while the
// padding that keeps text off it came from the design system, and the two
// drifted apart. There is no allowed exception left.
const BASE_RESETS = {};

Object.keys( TOOL_ROOTS ).forEach( function( file ) {

	const bare = ( ownRules( file ).match( /^[\t ]*(select|input|textarea|button|fieldset|legend|table|ul|ol|li|a|p|h[1-6])\s*[,{]/gm ) || [] )
		.map( s => s.replace( /[,{]\s*$/, '' ).trim() );

	const unexpected = bare.filter( s => ( BASE_RESETS[file] || [] ).includes( s ) === false );

	check( file+ ' adds no new bare element selector', unexpected.length === 0, unexpected.join(', ') );
} );

// A tool links its own stylesheet and the design system, nothing else. Borrowing
// another tool's complete stylesheet for a handful of its classes is what let
// unrelated rules reach screens they were never written for, and it made every
// /_install and /_templates request pay for two files it barely used. The
// design system is the first half of _admin/assets/style.css, so that file is
// the one every screen links - the workbench through its bundle, the wizard and
// the recovery page directly.
const TOOL_TEMPLATES = {
	'_admin'     : [ '_admin/templates/html-header.tpl', '_admin/templates/page-index.tpl', '_admin/templates/page-login.tpl', '_admin/templates/page-recovery.tpl' ],
	'_install'   : [ '_admin/install/templates/page-wizard.tpl', '_admin/install/templates/page-locked.tpl' ],
};

Object.keys( TOOL_TEMPLATES ).forEach( function( tool ) {
	TOOL_TEMPLATES[tool].forEach( function( file ) {

		// The workbench's pages share one <head> through a [template] include
		let markup = read( file );
		if( markup.includes('[template /_admin/templates/html-header]') )
			markup += read( '_admin/templates/html-header.tpl' );
		const foreign = ( markup.match(/_(?:admin|editor|install|templates|theme)\/assets\/style\.css/g) || [] )
			.filter( function( reference ) { return reference.startsWith( tool+ '/' ) === false && reference !== '_admin/assets/style.css' } );

		check( file+ ' links no other tool\'s stylesheet', foreign.length === 0, foreign.join(', ') );
	} );
} );

/*	A tag carries one attribute of any given name, and the html parser enforces
	that by keeping the first and dropping every repeat as a parse error - in
	silence. Three <svg> of the shell's theme toggle opened with
	class="admin-theme-toggle-*" and carried a second class="lucide ..." at the
	other end of the tag; measured in headless Chromium, they reached the dom
	with ten attributes rather than eleven and not one lucide class between
	them. Nothing reads those classes today, which is why it could sit there
	unnoticed - written the other way round, the same mistake takes the whole
	theme toggle off the screen. So the sweep is over every template rather
	than over that one file.	*/
const templateFiles = [];
( function collect( dir ) {
	fs.readdirSync( path.join( cssRoot, dir ), { withFileTypes : true } ).forEach( function( entry ) {
		const relative = dir === '' ? entry.name : dir+ '/'+ entry.name;
		if( entry.isDirectory() === true ) {
			// data/ is generated, .git/ and node_modules/ are not ours
			if( [ '.git', 'data', 'node_modules' ].indexOf( entry.name ) === -1 )
				collect( relative );
			return;
		}
		if( entry.name.endsWith('.tpl') )
			templateFiles.push( relative );
	} );
} )( '' );

const repeatedAttributes = [];
templateFiles.forEach( function( file ) {
	read( file ).split('\n').forEach( function( line, index ) {
		const tags = /<[a-zA-Z][-\w:]*((?:\s+[-\w:]+(?:\s*=\s*(?:"[^"]*"|'[^']*'))?)*)\s*\/?>/g;
		let tag;
		while( ( tag = tags.exec( line ) ) !== null ) {
			const names = [];
			const attributes = /([-\w:]+)(?:\s*=\s*(?:"[^"]*"|'[^']*'))?/g;
			let attribute;
			while( ( attribute = attributes.exec( tag[1] ) ) !== null )
				names.push( attribute[1].toLowerCase() );
			names.forEach( function( name, at ) {
				if( names.indexOf( name ) !== at )
					repeatedAttributes.push( file+ ':'+ ( index + 1 )+ ' @'+ name );
			} );
		}
	} );
} );

check( 'every template was found and read', templateFiles.length > 10 && templateFiles.indexOf('_admin/templates/page-index.tpl') !== -1 );

/*	A message a script writes after an action is only a message if somebody is
	told it changed. The workbench's own pages carry one paragraph per screen
	that a script fills - the login form's answer, the recovery page's
	"Checking …", the wizard's "Please fix the error above" - and three of
	those were plain paragraphs: the text appeared, and a screen reader said
	nothing at all, because nothing had asked it to watch.

	The list is taken from the scripts rather than written here: every id
	ending in msg or message that a shipped script reaches for is a paragraph
	something writes into, so the next screen to grow one is held to the same
	rule without this test being edited. A paragraph no script names (the two
	locked pages' one sentence) is static text and needs nothing	*/
const writtenMessageIds = new Set();
[ '_admin/assets', '_admin/install/assets' ].forEach( function( dir ) {
	fs.readdirSync( path.join( cssRoot, dir ) ).filter( f => f.endsWith('.js') ).forEach( function( file ) {
		const source = read( dir+ '/'+ file );
		const lookups = /getElementById\(\s*'([-\w]*(?:msg|message))'\s*\)/g;
		let found;
		while( ( found = lookups.exec( source ) ) !== null )
			writtenMessageIds.add( found[1] );
	} );
} );

const mutedMessages = [];
templateFiles.forEach( function( file ) {
	const markup = read( file );
	const tags = /<[a-zA-Z][-\w:]*\s+[^>]*id="([-\w]+)"[^>]*>/g;
	let tag;
	while( ( tag = tags.exec( markup ) ) !== null )
		if( writtenMessageIds.has( tag[1] ) === true && /role="(status|alert)"/.test( tag[0] ) === false )
			mutedMessages.push( file+ ' #'+ tag[1] );
} );
check( 'the sweep found the message paragraphs to hold', writtenMessageIds.has('form-message') && writtenMessageIds.has('install-actions-msg') && writtenMessageIds.size >= 5 );

/*	An action is a button and a place is a link (AGENTS.md, "Templates"). The
	rail's "Log out" and the recovery page's "Close recovery" were written as
	<a href="#">: announced as links to whoever cannot see them, offered to
	Enter and not to Space, and pointing at the page they are already on - so
	a middle-click or a "open in new tab" reloaded the workbench instead of
	logging out. href="#" is what that mistake always looks like, so the
	sweep is for that rather than for the two ids.

	Over the tool's own screens, not over the section library: a preset's
	"Learn more" is an href="#" on purpose - it is where the project puts its
	own address, and nothing is wired to it	*/
const fakeLinks = [];
templateFiles.filter( function( file ) {
	return file.startsWith('_admin/templates/') || file.startsWith('_admin/install/templates/');
} ).forEach( function( file ) {
	if( ( read( file ).match( /<a\s[^>]*href="#"/g ) || [] ).length > 0 )
		fakeLinks.push( file );
} );
check( 'no template writes an action as a link to nowhere'+ ( fakeLinks.length ? ' - '+ fakeLinks.join(', ') : '' ), fakeLinks.length === 0 );

/*	Every screen the tool puts in front of somebody has exactly one <h1>, and
	the levels below it are a ladder without a rung missing. Measured in
	headless Chromium, the workbench shell had no heading of any level at all
	and the wizard ran h3, h3, h3, h1 - so a screen reader asked to list the
	page's headings answered with nothing, or with the wrong shape. The shell
	and the wizard carry a .nino-admin-sr-only <h1>, because neither has a
	place a visible one would belong	*/
const headingShapes = [];
[ '_admin/templates/page-index.tpl', '_admin/templates/page-locked.tpl',
  '_admin/install/templates/page-wizard.tpl', '_admin/install/templates/page-locked.tpl' ].forEach( function( file ) {
	// Comments stripped first - the <h1> a comment explains is not one
	const markup = read( file ).replace( /<!--[\s\S]*?-->/g, '' );
	const levels = ( markup.match( /<h[1-6][\s>]/g ) || [] ).map( function( tag ) { return Number( tag[2] ) } );
	const ones 	= levels.filter( function( level ) { return level === 1 } ).length;
	const gap 	= levels.some( function( level, at ) { return at > 0 && level > levels[at-1] + 1 } );
	// The first heading is the page's own, and it is an h1: the wizard used to
	// run h3, h3, h3 and then reach h1 on its last screen
	if( ones !== 1 || gap === true || levels[0] !== 1 )
		headingShapes.push( file+ ' ['+ levels.join(',')+ ']' );
} );
check( 'every screen of the tool has one <h1> and no missing level under it'+ ( headingShapes.length ? ' - '+ headingShapes.join(', ') : '' ), headingShapes.length === 0 );
check( 'the design system has a heading that is there for whoever is not looking at the screen',
	/:where\(\.nino-admin\) \.nino-admin-sr-only \{[^}]*clip-path: inset\(50%\);/s.test( shared ) );

/*	...and a button that reads as a line of text still has to be big enough to
	hit: "Log out" is one line of .82rem type, 19px tall, under the 24px the
	pointer rule asks for (WCAG 2.2 SC 2.5.8)	*/
check( 'the design system gives a text-shaped action a hit area rather than a line height',
	/:where\(\.nino-admin\) \.nino-admin-linkbutton \{[^}]*min-height: 1\.5rem;/s.test( shared ) );

/*	A button whose face is a glyph has to be called something, and `title` is
	not that something: it is the weakest source the accessibility tree
	accepts, it is shown on hover and on nothing else - not on a touch screen,
	not to a keyboard - and a project's own stylesheet cannot make it visible.
	Nino.adminUi.elementList()'s button() sets both; the five ↑/↓/× pairs the
	panels and the wizard hand-rolled set only the title. Read per variable,
	so what is checked is the button a glyph was written on and not, say, the
	hint on a disabled Delete whose face is a whole word	*/
const glyphOnly = [];
[ [ '_admin/assets', fs.readdirSync( path.join( cssRoot, '_admin/assets' ) ) ],
  [ '_admin/install/assets', fs.readdirSync( path.join( cssRoot, '_admin/install/assets' ) ) ] ]
	.flatMap( function( entry ) { return entry[1].filter( f => f.endsWith('.js') ).map( f => entry[0]+ '/'+ f ) } )
	.concat( ADMIN_MODULES.flatMap( function( m ) {
		const dir = path.join( cssRoot, '_admin/Nino/Modules', m, 'assets' );
		return fs.existsSync( dir ) ? fs.readdirSync( dir ).filter( f => f.endsWith('.js') ).map( f => '_admin/Nino/Modules/'+ m+ '/assets/'+ f ) : [];
	} ) )
	.concat( [ 'Form', 'Navigation' ].flatMap( function( m ) {
		const dir = path.join( cssRoot, '_nino/Nino/Modules', m, 'assets' );
		return fs.existsSync( dir ) ? fs.readdirSync( dir ).filter( f => f.endsWith('.js') ).map( f => '_nino/Nino/Modules/'+ m+ '/assets/'+ f ) : [];
	} ) )
	.forEach( function( file ) {
		const source = read( file );
		const buttons = new Set();
		for( const m of source.matchAll( /(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*dc\.createElement\('button'\)/g ) )
			buttons.add( m[1] );
		buttons.forEach( function( name ) {
			const glyph = new RegExp( '\\b'+ name+ "\\.textContent = '[^A-Za-z0-9\\s]'" ).test( source );
			const named = new RegExp( '\\b'+ name+ "\\.setAttribute\\(\\s*'aria-label'" ).test( source );
			if( glyph === true && named === false )
				glyphOnly.push( file+ ' '+ name );
		} );
	} );
check( 'a button whose face is a glyph carries a name, not only a hover hint'
	+ ( glyphOnly.length ? ' - '+ glyphOnly.join(', ') : '' ), glyphOnly.length === 0 );
check( 'every message paragraph a template ships is announced when a script fills it'
	+ ( mutedMessages.length ? ' - '+ mutedMessages.join(', ') : '' ), mutedMessages.length === 0 );
check( 'no element in any template carries the same attribute twice'
	+ ( repeatedAttributes.length ? ' - '+ repeatedAttributes.slice( 0, 5 ).join(', ') : '' ), repeatedAttributes.length === 0 );

// The select indicator and the space reserved for it must stay in one file, or
// a later layer silently narrows the padding and the option text slides under
// the arrow
check( 'the design system owns both the select chevron and its padding',
	/:where\(\.nino-admin\) select \{[^}]*var\(--nino-admin-select-indicator\)[^}]*background-image:/s.test( shared ) );

check( 'the select chevron is one seam-free image rather than two touching gradients',
	shared.includes('--nino-admin-select-chevron: url("data:image/svg+xml') &&
	/:where\(\.nino-admin\) select \{[^}]*background-image: var\(--nino-admin-select-chevron\)/s.test( shared ) );

check( 'the shared tile is a complete surface rather than a spacing-only refinement',
	/:where\(\.nino-admin\) \.nino-admin-tile \{[^}]*display: flex;[^}]*border: 1px solid[^}]*background: var\(--editor-bg-elevated\);[^}]*color: var\(--editor-text\);/s.test( shared ) );

check( 'the shared field header owns its complete horizontal layout',
	/:where\(\.nino-admin\) \.nino-admin-field-header \{[^}]*display: flex;[^}]*align-items: baseline;[^}]*justify-content: space-between;/s.test( shared ) );

check( 'ordinary shared field labels are block rows above their controls',
	/:where\(\.nino-admin\) \.nino-admin-field > span,\s*:where\(\.nino-admin\) \.nino-admin-field-name \{[^}]*display: block;/s.test( shared ) );

check( 'the shared action bar is fixed to the viewport and clears the common rail',
	/\.nino-admin \.nino-admin-actionbar \{[^}]*position: fixed;/s.test( shared ) &&
	/@media \(min-width: 64rem\)[\s\S]*?\.nino-admin \.nino-admin-actionbar \{[^}]*left: var\(--nino-admin-sidebar-width\);/s.test( shared ) );

console.log('\nOne language');

// Every word a panel renders is a fill: the workbench's own strings live in
// _admin/text/<locale>.php, a module's in the text() directory beside its
// panel, and [jstext] hands all of them to Nino.content.getText(). A literal
// English sentence in a script is what the German interface used to show
// halfway down a screen. The recovery page is English by design (it has no
// session to take a language from), so it is not checked here.
const localizedScripts = fs2.readdirSync( path2.join( __dirname, '../_admin/assets' ) ).filter( f => f.endsWith('.js') && f !== 'recovery.js' ).map( f => [ f, asset( f ) ] )
	.concat( moduleScripts( '_admin/Nino/Modules', ADMIN_MODULES ) )
	.concat( moduleScripts( '_nino/Nino/Modules', [ 'Form', 'Navigation' ] ) )
	.concat( moduleScripts( 'features', FEATURES ) );
const literalSentence = /\.(?:textContent|placeholder|title|alt) = '[A-Z][^']*'|(?:confirm|alert)\( '[A-Z]|innerHTML = '<[^']*>[A-Za-z]{3,}/;
const literals = localizedScripts.filter( e => literalSentence.test( e[1] ) ).map( e => e[0] );
check( 'no workbench script renders a literal English sentence - every word is a fill'+ ( literals.length ? ' - found in '+ literals.join(', ') : '' ), literals.length === 0 );

// The one request helper: a panel's _apiCall is a thin name for
// Nino.adminUi.api.call(), never its own copy of the transport - a copy cannot
// know the page outlived its session, which is what the helper is for - and
// what a failed request says is errorText()'s business, not a status number
// each script writes in front of the server's message itself. Lazily, in the
// body: panel scripts run in suites that load no Nino.admin.js, so nothing
// may ask the helper while the object literal is built
const apiPanels = localizedScripts.filter( e => /_apiCall\s*:/.test( e[1] ) );
const apiOdd = apiPanels.filter( e => /_apiCall : function\( endpoint, payload, callback(?:, extra)? \) \{\n\t\t\tNino\.adminUi\.api\.call\( '[a-z]+\/'\+ endpoint, payload, callback(?:, extra)? \);\n\t\t\},/.test( e[1] ) === false ).map( e => e[0] );
check( 'every workbench panel posts through the one request helper'+ ( apiOdd.length ? ' - not: '+ apiOdd.join(', ') : '' ), apiPanels.length >= 19
	&& apiPanels.every( e => /_apiCall : function\( endpoint, payload, callback(?:, extra)? \) \{\n\t\t\tNino\.adminUi\.api\.call\( '[a-z]+\/'\+ endpoint, payload, callback(?:, extra)? \);\n\t\t\},/.test( e[1] ) ) );
const ownTransport = localizedScripts.filter( e => e[0] !== 'Nino.admin.js' && /Nino\.http\.sendRequest\(\s*'\[\[\/nino\/dir\]\]\/_admin\/'/.test( e[1] ) ).map( e => e[0] );
check( '...and none keeps a transport of its own'+ ( ownTransport.length ? ' - '+ ownTransport.join(', ') : '' ), ownTransport.length === 0 );
const ownPrefix = localizedScripts.filter( e => e[0] !== 'Nino.admin.js' && /'\('\s*\+\s*status\s*\+\s*'\) '/.test( e[1] ) ).map( e => e[0] );
check( '...nor writes the status number in front of an error itself'+ ( ownPrefix.length ? ' - '+ ownPrefix.join(', ') : '' ), ownPrefix.length === 0 );

// ...and every key a script asks for exists in both interface languages,
// whichever file defines it. Keys built at runtime ('/_admin/nav/group/'+
// group) are not checked - they have no literal to check
const fillSources = { en_US : '', de_DE : '' };
[ 'en_US', 'de_DE' ].forEach( locale => {
	fillSources[locale] = workbenchText( locale )
		+ ADMIN_MODULES.map( m => adminModuleText( m, locale ) ).join('\n')
		+ APP_MODULES.map( m => moduleText( m, locale ) ).join('\n');
} );
const usedKeys = new Set();
localizedScripts.forEach( e => { for( const m of e[1].matchAll( /getText\(\s*'(\/_admin\/[^']+)'\s*\)/g ) ) usedKeys.add( m[1] ); } );
// The backend hands the frontend fill keys too: nav labels, tab names,
// dashboard tiles, a schema's labels and hints
[ ...ADMIN_MODULES.flatMap( m => fs2.readdirSync( path2.join( __dirname, '../_admin/Nino/Modules', m ), { recursive : true } )
	.filter( f => String( f ).endsWith('.php') ).map( f => read( '_admin/Nino/Modules/'+ m+ '/'+ f ) ) ),
  ...[ 'Navigation' ].map( m => read( '_nino/Nino/Modules/'+ m+ '/Admin/Admin.php' ) ),
  ...FEATURES.map( m => read( 'features/'+ m+ '/Admin/Admin.php' ) ) ].forEach( php => {
	for( const m of php.matchAll( /'(\/_admin\/(?:nav|dashboard|config|lockout|language|users)\/[^'\s]+)'/g ) )
		if( m[1].endsWith('/manage') === false )
			usedKeys.add( m[1] );
} );
const undefinedKeys = [ ...usedKeys ].filter( k => [ 'en_US', 'de_DE' ].some( locale => fillSources[locale].includes( "'[["+ k+ "]]'" ) === false ) );
check( 'every fill key the workbench asks for is defined in English and in German'+ ( undefinedKeys.length ? ' - missing: '+ undefinedKeys.join(', ') : '' ), undefinedKeys.length === 0 );
check( 'the checks above saw the whole vocabulary', usedKeys.size > 200 );

console.log('\nUnsaved input');

// A form panel tells the shell when it holds input nobody has saved
// (Nino.admin.dirty, in the shell script). The registration is opt-in and
// feature-detected: a panel script runs in suites and on shells without
// script.js, and a feature panel on an older kernel has no registry at all.
// The directory is the list of panels, so the names a script registers under
// are checked against the panes the registry draws: a panel or a tab, named
// like its module directory
const registered = [];
localizedScripts.concat( [ [ 'Maintenance/admin.js', moduleAsset( 'Maintenance', 'admin.js' ) ] ] ).forEach( e => {
	for( const m of e[1].matchAll( /(if\( typeof Nino\.admin\.dirty === 'object' \)\s*)?Nino\.admin\.dirty\.(register|watchForm)\( '([a-z]+)'/g ) )
		registered.push( { file : e[0], name : m[3], guarded : m[1] !== undefined } );
} );
check( '...each behind a check that the shell has the registry', registered.every( r => r.guarded === true ) );
// A tab whose uri is not its directory's name (RecoveryPassword/ is the tab "recoverypw") is named here
const paneUris = new Set( [ 'navs', 'maintenance', 'recoverypw' ] );
ADMIN_MODULES.forEach( m => {
	paneUris.add( m.toLowerCase() );
	fs.readdirSync( path.join( __dirname, '../_admin/Nino/Modules', m ), { withFileTypes : true } )
		.filter( d => d.isDirectory() && [ 'Admin', 'assets', 'text' ].indexOf( d.name ) === -1 ).forEach( d => paneUris.add( d.name.toLowerCase() ) );
} );
check( '...under the uri of a panel or a tab the registry draws - the id of its pane is what the shell finds it by', registered.every( r => paneUris.has( r.name ) ) );

// Maintenance: the panel is read again every time it is shown, which rebuilt
// the form over whatever was typed into it
{
	const calls = [];
	const box = { Nino : { admin : { dirty : { isDirty : () => box.unsaved, watchForm(){}, snapshot(){} } }, events : { bindCallback(){} } }, console : console, document : { getElementById : () => null, documentElement : null, body : null } };
	box.window = box;
	box.unsaved = false;
	vm.runInContext( moduleAsset( 'Maintenance', 'admin.js' ), vm.createContext( box ), { filename : 'maintenance.js' } );
	const panel = box.Nino.admin.maintenance;
	panel.init = () => calls.push( 'init' );
	panel.showCurrent();
	check( 'Maintenance is read again when it is shown and nothing has been drawn yet', calls.length === 1 );
	panel._ready = true;
	panel.showCurrent();
	check( '...and when the form on screen holds nothing unsaved', calls.length === 2 );
	box.unsaved = true;
	panel.showCurrent();
	check( '...but not over a form somebody typed into', calls.length === 2 );
	delete box.Nino.admin.dirty;
	panel.showCurrent();
	check( '...a shell without the registry reads again as it always did', calls.length === 3 );
}

// ...and a read that fails writes its error over the form: the panel is then
// not holding input, whatever the registry still remembers, and reads again
{
	const reads = [];
	const shown = [];
	const wrap = { id : 'maintenance-form', innerHTML : '' };
	let answerWith = 200;
	const box = { Nino : {
		admin : { dirty : { isDirty : () => true, watchForm(){}, snapshot(){} } },
		adminUi : { api : { call : ( endpoint, payload, callback ) => { reads.push( endpoint ); callback( answerWith, answerWith === 200 ? { status : 'off' } : null ) } }, showError : ( container, status ) => shown.push( status ) },
		events : { bindCallback(){} },
	}, console : console, document : { getElementById : id => id === 'maintenance-form' ? wrap : null, documentElement : null, body : null } };
	box.window = box;
	vm.runInContext( moduleAsset( 'Maintenance', 'admin.js' ), vm.createContext( box ), { filename : 'maintenance.js' } );
	const panel = box.Nino.admin.maintenance;
	panel._render = () => {};
	panel.init();
	check( 'Maintenance: the first read draws the form', reads.length === 1 && panel._ready === true );
	answerWith = 500;
	box.Nino.admin.dirty.isDirty = () => false;
	panel.showCurrent();
	check( '...a read that fails shows its error and the panel no longer counts as drawn', reads.length === 2 && shown.join() === '500' && panel._ready === false );
	box.Nino.admin.dirty.isDirty = () => true;
	answerWith = 200;
	panel.showCurrent();
	check( '...so the next time it is shown it reads again, whatever the registry says, and recovers', reads.length === 3 && panel._ready === true );
}

const stylesheetSource = asset( 'style.css' );
check( '...and it is hidden only above the phone width, and only in a bar that has a status line',
	/@media \(min-width: 38\.001rem\) \{\s*\.nino-admin \.nino-admin-actionbar:has\(> \.nino-admin-status\) > \.nino-admin-actionbar-dirty \{\s*display: none;/.test( stylesheetSource ) === true );

const askTexts = [ '/_admin/common/confirm/unsaved', '/_admin/common/label/discard', '/_admin/common/label/cancel' ];
const elementTexts = [ '/_admin/elements/error/json', '/_admin/elements/error/field-required', '/_admin/elements/label/locale-open', '/_admin/elements/error/required' ];
check( 'the words of the question are fills in both interface languages',
	[ 'en_US', 'de_DE' ].every( l => askTexts.every( k => workbenchText( l ).includes( "'[["+ k+ "]]'" ) ) && elementTexts.every( k => adminModuleText( 'Elements', l ).includes( "'[["+ k+ "]]'" ) ) ) );
check( '...and the JSON refusal contains no bracket characters (a fill is parsed as a fill key)',
	[ 'en_US', 'de_DE' ].every( l => { const m = adminModuleText( 'Elements', l ).match( /\[\[\/_admin\/elements\/error\/json\]\]'\s*=>\s*'([^']*)'/ ); return m !== null && /[\[\]]/.test( m[1] ) === false } ) );

console.log('\nOne stylesheet');

// A message beside an image control says what went wrong in the colour the
// design system gives an error - if the script asks for the modifier the
// stylesheet actually knows. A misspelt one shows the failure as ordinary
// secondary text, which is how a failed slot upload in the Images panel
// looked. So every modifier any workbench script puts beside
// .nino-admin-field-image-msg has to be a rule in style.css
const stylesheet = asset( 'style.css' );
const imageMsgModifiers = new Set();
localizedScripts.forEach( e => { for( const m of e[1].matchAll( /nino-admin-field-image-msg ([a-z-]+)/g ) ) imageMsgModifiers.add( m[1] ) } );
const unknownModifiers = Array.from( imageMsgModifiers ).filter( mod => stylesheet.includes( '.nino-admin-field-image-msg.'+ mod ) === false );
check( 'every modifier a script puts beside the image message is one the stylesheet styles'+ ( unknownModifiers.length ? ' - unknown: '+ unknownModifiers.join(', ') : '' ), unknownModifiers.length === 0 );
check( '...and the check saw the modifier both image controls use', imageMsgModifiers.has('is-error') );

// A write that went through is reported as done, whatever the list read after it does:
// the question that asked for the Save would otherwise offer it again, and a second
// create of the same account or role is refused as existing
{
	const box = { console : console, document : { documentElement : null, body : null, getElementById : () => ( { textContent : '' } ) } };
	box.window = box;
	box.Nino = { admin : {}, content : { getText : key => key }, adminUi : { api : { errorText : () => 'x' } } };
	vm.runInContext( adminAsset( 'Users', 'roles.js' ), vm.createContext( box ), { filename : 'roles.js' } );
	const roles = box.Nino.admin.roles;
	roles._showError = () => {};
	const answers = [ [ 200, { id : 'editor' } ], [ 500, null ] ];
	roles._apiCall = ( endpoint, payload, callback ) => callback( ...answers.shift() );
	const reported = [];
	roles._save( { value : 'editor' }, { value : 'Editor' }, { fullCheck : { checked : false }, perms : () => [] }, ok => reported.push( ok ) );
	check( 'a role that was written is reported as saved when only the list read after it fails', reported.join() === 'true' );
	answers.push( [ 400, {} ] );
	roles._save( { value : 'editor' }, { value : 'Editor' }, { fullCheck : { checked : false }, perms : () => [] }, ok => reported.push( ok ) );
	check( '...and one the server refused is not', reported.join() === 'true,false' );
}

done();
