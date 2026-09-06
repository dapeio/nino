/**
 * Dependency-free structural checks for _admin's shared drill-down lists.
 *
 * Usage: node tests/admin-lists-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

let checks = 0;
let failures = 0;

function check( label, condition ) {
	checks++;
	if( condition ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

function asset( filename ) {
	return fs.readFileSync( path.join( __dirname, '../_admin/assets/', filename ), 'utf8' );
}

// A module's own workbench panel ships beside the module, not in _admin/assets
function moduleAsset( module, filename ) {
	return fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/', module, 'assets', filename ), 'utf8' );
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
const APP_MODULES = [ 'Form', 'Newsletter', 'Navigation', 'Search', 'Design', 'Templates' ];

// The words a panel renders: the workbench's own text/<locale>.php, and a
// module's text() directory beside its panel
function workbenchText( locale ) {
	return fs.readFileSync( path.join( __dirname, '../_admin/text/', locale+ '.php' ), 'utf8' );
}
function moduleText( module, locale ) {
	return fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/', module, 'text', locale+ '.php' ), 'utf8' );
}

console.log('Workbench modules');

// _admin gives the base, the modules fill it: the shell's own assets are the
// design system, its behaviour, the shell script and the two screens that are
// not panels at all (login, recovery), plus the rich-text primitive a panel
// asks for by name. A panel script in here would be a screen the shell knows
// about, which is the thing this layout exists to prevent
check( 'the shell ships no panel of its own - _admin/assets is the base and nothing else',
	fs.readdirSync( path.join( __dirname, '../_admin/assets' ) ).filter( f => f.endsWith('.js') ).sort()
		.join(',') === 'Nino.admin.js,html-editor.js,login.js,recovery.js,script.js' );
check( 'and _admin/panels is gone - every screen is a module directory instead',
	fs.existsSync( path.join( __dirname, '../_admin/panels' ) ) === false && ADMIN_MODULES.length > 0 );
check( 'every workbench module is a panel class the registry can find, with its words beside it',
	ADMIN_MODULES.every( m => fs.existsSync( path.join( __dirname, '../_admin/Nino/Modules', m, 'Admin/Admin.php' ) )
		&& [ 'en_US', 'de_DE' ].every( l => fs.existsSync( path.join( __dirname, '../_admin/Nino/Modules', m, 'text', l+ '.php' ) ) ) ) );

console.log('\nAdmin drill-down lists');

[
	[ 'Element Types', 'Elements', 'types.js' ],
	[ 'Elements', 'Elements', 'admin.js' ],
	[ 'Text Keys', 'Text', 'keys.js' ],
	[ 'Routes', 'Routes', 'admin.js' ],
	[ 'Image Slots', 'Images', 'slots.js' ],
].forEach( function( entry ) {
	check( entry[0]+ ' uses the shared grouped-list component', adminAsset( entry[1], entry[2] ).includes( "ul.className = 'nino-admin-list'" ) );
} );
check( 'Navigations - the Navigation module\'s own panel - uses the shared grouped-list component', moduleAsset( 'Navigation', 'admin.js' ).includes( "ul.className = 'nino-admin-list'" ) );

// Config is deliberately not one of them. It used to be a list of keys drilling
// down into a raw json textarea each; it is now a single grouped form, because
// nine settings are quicker to read on one screen than behind nine clicks - and
// because its two locale keys constrain each other and so have to be saved
// together.
const configSource = adminAsset( 'Config', 'admin.js' );
check( 'Config is one form rather than a drill-down list', configSource.includes( "ul.className = 'nino-admin-list'" ) === false );
// The switch itself lives in Nino.adminUi, because the Element Types editor
// needs the same control for its numbering option - two hand-rolled copies of
// one component is how the written on/off state gets dropped from one of them.
const adminUiSource = fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' );
check( 'the switch is a shared component, not a copy per tool',
	adminUiSource.includes('switchField : function') && adminUiSource.includes( "'nino-admin-switch-track'" ) );
check( 'a switch states its condition in words, not by knob position alone',
	adminUiSource.includes( "'nino-admin-switch-state'" ) );
check( 'Config renders booleans with that shared switch', configSource.includes('Nino.adminUi.switchField(') );
check( 'Config relies on fieldset\'s own shared surface instead of applying card padding twice', configSource.includes( "fieldset.className = 'nino-admin-card'" ) === false );
check( 'Config uses only the shared pinned action bar for its single Save', configSource.includes( "'nino-admin-actionbar'" ) && configSource.includes( 'admin-form-actions' ) === false );
// The search-index rebuild is the Search module's own panel now - Config
// no longer has to know that module exists
const searchSource = moduleAsset( 'Search', 'admin.js' );
check( 'Config carries no search-index action any more', configSource.includes( 'searchindex' ) === false );
check( 'the Search module\'s panel exposes the explicit full search-index rebuild action',
	searchSource.includes( "btn.textContent = Nino.content.getText('/_admin/search/label/create')" ) &&
	searchSource.includes( "action : 'search/createindex'" ) );
check( 'the search-index action has loading, empty and success feedback - every word a fill the module brings in both languages',
	searchSource.includes( "'/_admin/search/msg/creating'" ) &&
	searchSource.includes( "'/_admin/search/msg/none'" ) &&
	searchSource.includes( "'/_admin/search/msg/created'" ) &&
	[ 'en_US', 'de_DE' ].every( locale => [ 'creating', 'none', 'created', 'created-plural' ].every( key => moduleText( 'Search', locale ).includes( "'[[/_admin/search/msg/"+ key+ "]]'" ) ) ) );
check( 'the Search panel puts its one action in the shared action bar and attaches under its nav uri',
	searchSource.includes( 'Nino.adminUi.actionBar(' ) && searchSource.includes( 'Nino.admin.search = {' ) );
check( 'Config no longer edits routes, navigations or asset bundles', [ '/nino/http/routes', '/nino/html/navs', '/nino/html/assets' ].every( function( key ) { return configSource.includes( key ) === false } ) );
check( 'Config renders no raw json textarea any more', configSource.includes( 'config-form-value' ) === false );
// The login throttle and the languages left Config for screens of their own:
// the Users panel's login-protection tab and the Language panel. Both render
// their numbers and switches with the shared components
check( 'Config no longer carries the login throttle or the languages', [ '/nino/auth/', '/nino/locales/', '_addLocale', '_refreshNative' ].every( function( key ) { return configSource.includes( key ) === false } ) );
const lockoutSource = adminAsset( 'Users', 'lockout.js' );
check( 'the login-protection tab renders its two numbers with the shared bounded number field, as Config does', lockoutSource.includes( 'Nino.adminUi.numberField(' ) && configSource.includes( 'Nino.adminUi.numberField(' ) && adminUiSource.includes( 'numberField : function' ) && adminUiSource.includes( 'humanDuration : function' ) );
check( 'the login-protection tab attaches under its tab uri and saves through its own action', lockoutSource.includes( 'Nino.admin.lockout = {' ) && lockoutSource.includes( "action : 'lockout/'+ endpoint" ) );

const languageSource = adminAsset( 'Language', 'admin.js' );
check( 'Language attaches under its nav uri and is one form, not a drill-down list', languageSource.includes( 'Nino.admin.language = {' ) && languageSource.includes( "ul.className = 'nino-admin-list'" ) === false );
check( 'Language keeps its in-flow locale adder separate from fixed list actions', languageSource.includes( "adderRow.className = 'admin-language-adder'" ) && languageSource.includes( "adderRow.className = 'nino-admin-list-actions'" ) === false );
check( 'Language lays out the locale input and its Add button as one in-flow row',
	/#admin-page-wrap \.admin-language-adder \{[^}]*display: grid;[^}]*grid-template-columns: minmax\(0, 1fr\) auto;/s.test( adminAsset( 'Language', 'admin.css' ) ) );
check( 'Language builds the native-language select from the ticked languages', languageSource.includes( '_refreshNative' ) );

// Adding a language writes its text file as a skeleton of the native
// language's keys. The row it produces must stay unticked: the keys are empty,
// so ticking it would put a language that serves blank pages one Save away -
// the exact failure the panel exists to warn about.
const addLocaleStart = languageSource.indexOf('_addLocale : function()');
const addLocaleEnd = languageSource.indexOf('_renderNative : function', addLocaleStart);
const addLocaleSource = languageSource.slice( addLocaleStart, addLocaleEnd );
check( 'Language creates the text file through the backend rather than client-side only', addLocaleSource.includes( "_apiCall( 'addlocale'" ) );
check( 'Language does not switch a freshly created skeleton on', /entry\.active\s*=\s*true/.test( addLocaleSource ) === false );
check( 'Language still re-activates an already translated language directly', addLocaleSource.includes( 'existing.active = true' ) );
check( 'Language says what the Add button writes before it is pressed', languageSource.includes( "adderHint.textContent = Nino.content.getText('/_admin/language/hint/add')" ) && adminModuleText( 'Language', 'en_US' ).includes( 'Creates text/<locale>.php' ) );

const textSource = adminAsset( 'Text', 'keys.js' );
const categoryStart = textSource.indexOf('_renderCategoryList : function()');
const categoryEnd = textSource.indexOf('_openGroup : function', categoryStart);
const categorySource = textSource.slice( categoryStart, categoryEnd );
check( 'Text renders accessible links instead of non-interactive div cards', categorySource.includes( "dc.createElement('a')" ) && categorySource.includes( "dc.createElement('div')" ) === false );
// The scan sits in the shared action bar with the "new key" action rather
// than loose above the list. indexOf() comparisons are not the check for
// that: the two -1s a removed call leaves compare true against anything, so
// the guard that used to read "scan before the list" passed for years after
// the scan stopped being appended at all. Assert what is there, then that
// what replaced it is gone
check( 'Text puts its template scan in the shared action bar, ahead of the primary action',
	/Nino\.adminUi\.listActions\( \[ scanBtn, addBtn \] \)/.test( categorySource )
	&& categorySource.includes( 'wrap.appendChild( scanBtn )' ) === false );
check( 'Image Slots does the same, so the two scan screens read alike',
	/Nino\.adminUi\.listActions\( \[ scanBtn, addBtn \] \)/.test( adminAsset( 'Images', 'slots.js' ) ) );
// A list the account emptied has to stop saying it is empty once it is not.
// The Images panel loads its slots once; the tab that changes them says so
check( 'changing a slot marks the Images panel\'s copy stale, so its empty state cannot outlive the slots',
	adminAsset( 'Images', 'slots.js' ).includes( 'Nino.admin.images._ready = false' ) &&
	adminAsset( 'Images', 'admin.js' ).includes( "wrap.classList.remove( 'nino-admin-list', 'nino-admin-list-buttons' )" ) );

// The Navigations detail level reuses the same component one level deeper,
// for a row that doesn't drill down anywhere - so it carries the label and
// the ↑/↓/× cluster itself rather than an <a>
const navsSource = moduleAsset( 'Navigation', 'admin.js' );
check( 'Navigations reuses the list component for one menu\'s entries too', navsSource.split("ul.className = 'nino-admin-list'").length === 3 );
check( 'Navigations reuses the shared ↑/↓ cluster the Routes list has', navsSource.includes("moveWrap.className = 'admin-page-move'") );
check( 'Navigations uses the shared pinned form toolbar and action bar', navsSource.includes('Nino.admin.formToolbar( backLink )') && navsSource.includes("'nino-admin-actionbar'") && navsSource.includes('admin-form-actions') === false );
check( 'Navigations puts its list-level action in the shared bar', navsSource.includes('Nino.adminUi.listActions( [ addBtn ] )') );
check( 'Navigations carries no locale state or switch - a menu has nothing per-locale', navsSource.includes('_locales') === false && navsSource.includes('selectedLocale') === false && navsSource.includes('data-locale') === false );

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
// The pane's tab strip is the pane's own bar, not a row of buttons in the
// content: the context bar's surface, edge to edge, closing at the top
check( 'a pane\'s tab strip carries the workbench bar surface, full width and flush to the top',
	/#admin-content-wrap > \[data-panel\] > \.admin-panel-tabs \{[^}]*margin: -1rem calc\(var\(--nino-admin-content-inline\) \* -1\)[^}]*background: var\(--editor-bar-bg\);/s.test( workbenchCss ) );
check( 'the workbench stylesheet is one file: the design system first, its own rules after', css.indexOf('@layer nino.system {') > 0 && css.indexOf('@layer nino.system {') < css.indexOf('@layer nino.tool {') && fs.existsSync( path.join( __dirname, '../_admin/assets/Nino.admin.css' ) ) === false );
check( 'shared rows expose a right-facing drill-down arrow', sharedCss.includes('.nino-admin-list li > a::after') && sharedCss.includes('content: "›"') );
check( 'shared rows retain a keyboard focus treatment', sharedCss.includes('.nino-admin-list li > a:focus-visible') );
check( 'Dashboard list rows are plain block links the tile grid does not restyle', adminAsset( 'Dashboard', 'admin.css' ).includes('#admin-dashboard-elements a') );

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

// One way to say "nothing here yet". A hint paragraph in one panel, a bare
// <p> in the next and a table's own empty row in a third is what the screens
// used to do; the component is the design system's, so every list screen and
// every module panel renders the same thing
const emptyStateScripts = [ [ 'Elements', 'types.js' ], [ 'Text', 'keys.js' ], [ 'Images', 'slots.js' ], [ 'Routes', 'admin.js' ],
	[ 'Users', 'roles.js' ], [ 'Backups', 'admin.js' ], [ 'Logs', 'admin.js' ], [ 'Dashboard', 'admin.js' ] ].map( e => adminAsset( e[0], e[1] ) )
	.concat( [ 'Navigation', 'Form', 'Newsletter' ].map( m => moduleAsset( m, 'admin.js' ) ) );
check( 'the empty state is one shared component with a class of its own',
	adminUiSource.includes( 'emptyState : function' ) && adminUiSource.includes( "empty.className = 'nino-admin-empty'" ) && sharedCss.includes( '.nino-admin-empty {' ) );
check( 'every empty list screen renders that component rather than a paragraph of its own',
	emptyStateScripts.every( s => s.includes( 'Nino.adminUi.emptyState(' ) && s.includes( "empty.className = 'nino-admin-hint'" ) === false ) );
check( 'the shared table\'s empty row and the Design panel\'s empty catalogue notes carry the same class',
	adminUiSource.split( "className = 'nino-admin-empty'" ).length === 3 &&
	( fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/Design/templates/panel.tpl' ), 'utf8' ).match( /class="nino-admin-empty theme-hidden"/g ) || [] ).length === 3 );

const usersSource = adminAsset( 'Users', 'admin.js' );
check( 'the user and role forms expose real labels and live status text',
	usersSource.includes("mailLabel.className = 'nino-admin-field'") &&
	usersSource.includes("pwLabel.className = 'nino-admin-field'") &&
	usersSource.includes("roleLabel.className = 'nino-admin-field'") &&
	usersSource.split("setAttribute( 'aria-live', 'polite' )").length >= 4 );
// What a role grants is the roles tab's business: the user form only says
// which role an account holds, through the one action the backend offers
check( 'Users hands out roles and no longer edits permissions of its own',
	usersSource.includes( "_apiCall( 'role'" ) && usersSource.includes( '_renderRole' ) &&
	usersSource.includes( '_renderPermissions' ) === false && usersSource.includes( "'permissions'" ) === false );
const rolesSource = adminAsset( 'Users', 'roles.js' );
// The permissions are the shared multi-reference picker, not a checkbox per
// permission: the list is one entry per panel and tab of every active module
// and grows with the project, so what the role HAS is a short list and finding
// the next one is a search. Unordered, because a permission set has no order -
// offering ↑/↓ would say it did
check( 'the roles tab picks permissions with the shared multi-reference control, as a set rather than a list',
	rolesSource.includes( 'Nino.admin.roles = {' ) &&
	rolesSource.includes( 'Nino.adminUi.elementList(' ) &&
	/ordered\s*:\s*false/.test( rolesSource ) &&
	rolesSource.includes( "check.disabled = hasFullAccess" ) === false );
check( 'it orders them the way the rail is ordered, with the ones no panel offers behind those',
	rolesSource.includes( "[ 'content', 'structure', 'system', 'other' ]" ) &&
	rolesSource.includes( "Nino.content.getText('/_admin/nav/group/'+ group )" ) &&
	rolesSource.includes( "Nino.content.getText('/_admin/roles/group/other')" ) );
// Full access IS every permission, so picking single ones beside it would say
// something the save does not do - and what the role held has to survive the
// switch going on and off again, so the picker is hidden, never emptied
check( 'full access hides the picker instead of emptying it',
	rolesSource.includes( "picker.classList.toggle( 'admin-hidden', fullCheck.checked )" ) &&
	/perms\.filter\(\s*function\(\s*perm\s*\)\s*\{\s*return perm !== '\/\*'/.test( rolesSource ) );
// The control itself must be able to say "a set, not a list" - a permission
// picker that shipped its own copy of the chosen-list would be the third one
const adminUiUnordered = fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' );
check( 'the shared control carries that mode rather than the panel reimplementing it',
	adminUiUnordered.includes( 'const ordered = options.ordered !== false' ) &&
	adminUiUnordered.includes( 'if( ordered === true ) {' ) );
check( 'the roles tab uses the shared grouped-list component and the shared empty state',
	rolesSource.includes( "ul.className = 'nino-admin-list'" ) && rolesSource.includes( 'Nino.adminUi.emptyState(' ) );
// The scoped permissions are a path per action and per field, so there is no
// finite list to offer - they are typed, and land in the same picker as
// everything else rather than in a second control beside it
check( 'a permission can be typed in as well as picked',
	rolesSource.includes( "addInput.placeholder = Nino.content.getText('/_admin/roles/perms/custom-placeholder')" ) &&
	rolesSource.includes( 'picker.replaceWith( rebuilt )' ) );
check( '...validated as a permission string before it is added',
	rolesSource.includes( 'Nino.admin.roles._isValidPerm( perm ) === false' ) );
// Enter in a text input submits the form it sits in - here that would save
// the role instead of adding the permission just typed
check( '...and Enter adds it instead of saving the role',
	/addInput\.addEventListener\( 'keydown'[\s\S]{0,200}ev\.preventDefault\(\)/.test( rolesSource ) );
// '/*' is the switch at the top of the same fieldset; a second way to say it
// is a second place to have to find when it is turned off again
check( '...while full access stays the switch it already is',
	rolesSource.includes( "if( perm === '/*' ) {" ) );

// --- one language, one text system ---------------------------------------
//
// A panel that writes a sentence into the dom says it in whatever language it
// was written in, and no locale file can reach it. The workbench has one text
// system and every screen speaks through it; this is the shape a regression
// takes - a literal assigned straight to what the user reads.
const panelScripts = require('child_process')
	.execSync( "ls _admin/Nino/Modules/*/assets/*.js app/Nino/Modules/*/assets/*.js", { cwd : path.join( __dirname, '..' ) } )
	.toString().trim().split('\n');

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
// most of its own
const panelMarkup = require('child_process')
	.execSync( "ls app/Nino/Modules/*/templates/panel.tpl _admin/templates/page-index.tpl", { cwd : path.join( __dirname, '..' ) } )
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

// The Template Builder is the panel this was written for: 3400 lines that used
// to carry every word in the source, and now carry none
[ 'script.js', 'sections.js', 'composer.js', 'area-composer.js' ].forEach( function( file ) {
	const source = fs.readFileSync( path.join( __dirname, '../app/Nino/Modules/Templates/assets/', file ), 'utf8' );
	check( 'the Template Builder\'s '+ file+ ' speaks through the text system', source.includes( "Nino.content.getText('/_admin/templates/" ) );
} );

check( 'destructive Admin controls all use the shared danger treatment',
	[ [ 'Elements', 'types.js' ], [ 'Users', 'admin.js' ], [ 'Users', 'roles.js' ], [ 'Backups', 'admin.js' ] ]
		.every( e => adminAsset( e[0], e[1] ).includes("className = 'nino-admin-btn-danger'") ) );

console.log('\nDesign system contracts');

// --- design-system conventions (see AGENTS.md, "Designing an admin frontend")

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

check( 'the shared design system declares its own cascade layer',
	shared.includes('@layer nino.system {') );

// One namespace: every panel script attaches to Nino.admin.<uri>, and the
// shared html editor is Nino.admin.htmlEditor - a script still speaking the
// old editor's namespace would load fine and fail on first use
const fs2 = require('fs'), path2 = require('path');
const workbenchScripts = fs2.readdirSync( path2.join( __dirname, '../_admin/assets' ) ).filter( f => f.endsWith('.js') ).map( f => asset( f ) )
	.concat( moduleScripts( '_admin/Nino/Modules', ADMIN_MODULES ).map( e => e[1] ) )
	.concat( moduleScripts( 'app/Nino/Modules', APP_MODULES ).map( e => e[1] ) );
check( 'every workbench script speaks the Nino.admin namespace - nothing left of Nino.editor, Nino.design or Nino.templates',
	workbenchScripts.every( s => s.includes('Nino.editor') === false && /Nino\.(?:design|templates)\b/.test( s.replace( /Nino\.admin\.(?:design|templates)/g, '' ) ) === false ) );

check( 'the shared design system owns the rail fold and the workspace layout, so a panel never restates the shell',
	shared.includes('.nino-admin-shell--folded') &&
	shared.includes('.nino-admin-shell--workspace .nino-admin-pane') &&
	shared.includes('.nino-admin-rail-toggle') &&
	shared.includes('.nino-admin-nav-group') &&
	shared.includes('.nino-admin-tools') === false );

const TOOL_ROOTS = {
	'_admin/assets/style.css'                        : '#admin-page-wrap',
	'_admin/install/assets/style.css'                  : '#install-page-wrap',
	'app/Nino/Modules/Templates/assets/style.css'    : '#pd-app',
	'app/Nino/Modules/Design/assets/style.css'       : '#theme-page-wrap',
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

// A module panel's template is a fragment the workbench renders into its
// pane: it links nothing, its files join the workbench's bundles (see the
// panel's assets())
[ 'app/Nino/Modules/Templates/templates/panel.tpl', 'app/Nino/Modules/Design/templates/panel.tpl' ].forEach( function( file ) {
	const markup = read( file );
	check( file+ ' is a fragment with no head, no stylesheet link and no script of its own',
		markup.includes('<html') === false && markup.includes('<link') === false && markup.includes('<script') === false );
} );

Object.keys( TOOL_TEMPLATES ).forEach( function( tool ) {
	TOOL_TEMPLATES[tool].forEach( function( file ) {

		// The workbench's pages share one <head> through a [template] include
		let markup = read( file );
		if( markup.includes('[template /_admin/templates/html-header]') )
			markup += read( '_admin/templates/html-header.tpl' );
		const foreign = ( markup.match(/_(?:admin|editor|install|templates|theme)\/assets\/style\.css/g) || [] )
			.filter( function( reference ) { return reference.startsWith( tool+ '/' ) === false && reference !== '_admin/assets/style.css' } );

		check( file+ ' links no other tool\'s stylesheet', foreign.length === 0, foreign.join(', ') );
		check( file+ ' links the shared design system', markup.includes('_admin/assets/style.css') || markup.includes('/_admin/.cache/style.css') );
	} );
} );

const editorHeader = read('_admin/templates/html-header.tpl');
check( 'the Editor document shell uses current HTML without IE conditionals', /^<!doctype html>\s*<html lang="\[\[\/website\/lang\]\]">/.test( editorHeader )
	&& editorHeader.includes('X-UA-Compatible') === false
	&& editorHeader.includes('<!--[if ') === false
	&& editorHeader.includes('http-equiv="Content-Type"') === false );

// _editor used to ship a second copy of every design token and base control, in
// the same cascade layer as the design system but linked before it - so the copy
// silently lost every tie, while its higher-layer component overrides silently
// won and pulled the Editor away from the other three tools' look
check( 'no tool stylesheet redefines the shared design tokens',
	Object.keys( TOOL_ROOTS ).every( function( file ) { return ownRules( file ).includes('--editor-blue:') === false } ) );

// The select indicator and the space reserved for it must stay in one file, or
// a later layer silently narrows the padding and the option text slides under
// the arrow
check( 'the design system owns both the select chevron and its padding',
	/:where\(\.nino-admin\) select \{[^}]*var\(--nino-admin-select-indicator\)[^}]*background-image:/s.test( shared ) );

check( 'the select chevron is one seam-free image rather than two touching gradients',
	shared.includes('--nino-admin-select-chevron: url("data:image/svg+xml') &&
	/:where\(\.nino-admin\) select \{[^}]*background-image: var\(--nino-admin-select-chevron\)/s.test( shared ) );

check( 'the locale switch is a shared class, not a per-tool id',
	shared.includes('.nino-admin-locale-select') &&
	[ '_admin/assets/style.css', '_admin/install/assets/style.css' ]
		.every( f => /#(personalinfos|elements-form|text-form)-locale-select\s*[,{]/.test( read( f ) ) === false ) );

const adminTemplate = read('_admin/templates/page-index.tpl');
const adminLoginTemplate = read('_admin/templates/page-login.tpl');
check( 'the shell and the login page link the shared design system - through the bundle, whose first file it is',
	[ adminTemplate, adminLoginTemplate, editorHeader ].some( source => source.includes('/_admin/.cache/style.css') || source.includes('_admin/assets/style.css') ) );

check( 'the shell renders its navigation and panes from the registry and carries the fold control',
	adminTemplate.includes('[[/_admin/nav]]') &&
	adminTemplate.includes('[[/_admin/panes]]') &&
	adminTemplate.includes('id="admin-rail-toggle"') &&
	adminTemplate.includes('admin-tools') === false );

check( 'the shared tile is a complete surface rather than a spacing-only refinement',
	/:where\(\.nino-admin\) \.nino-admin-tile \{[^}]*display: flex;[^}]*border: 1px solid[^}]*background: var\(--editor-bg-elevated\);[^}]*color: var\(--editor-text\);/s.test( shared ) );

check( 'the shared field header owns its complete horizontal layout',
	/:where\(\.nino-admin\) \.nino-admin-field-header \{[^}]*display: flex;[^}]*align-items: baseline;[^}]*justify-content: space-between;/s.test( shared ) );

check( 'ordinary shared field labels are block rows above their controls',
	/:where\(\.nino-admin\) \.nino-admin-field > span,\s*:where\(\.nino-admin\) \.nino-admin-field-name \{[^}]*display: block;/s.test( shared ) );

check( 'Admin form toolbars use the shared context bar without a dead local class',
	asset('script.js').includes('Nino.adminUi.contextBar( backLink )') &&
	asset('script.js').includes('admin-form-toolbar') === false );

check( 'the shared action bar is fixed to the viewport and clears the common rail',
	/\.nino-admin \.nino-admin-actionbar \{[^}]*position: fixed;/s.test( shared ) &&
	/@media \(min-width: 64rem\)[\s\S]*?\.nino-admin \.nino-admin-actionbar \{[^}]*left: var\(--nino-admin-sidebar-width\);/s.test( shared ) );

check( 'Admin does not replace shared action bars, danger buttons, tiles or field headers',
	workbenchCss.includes('.nino-admin-actionbar') === false &&
	/\.nino-admin-btn-danger[^\{]*\{[^}]*(?:background|color|box-shadow):/s.test( workbenchCss ) === false &&
	/\.nino-admin-tile[^\{]*\{[^}]*(?:display|background|box-shadow):/s.test( workbenchCss ) === false &&
	/\.nino-admin-field-header[^\{]*\{[^}]*(?:display|align-items|justify-content):/s.test( workbenchCss ) === false );

check( 'Admin carries no obsolete sticky action bar or second rail width',
	workbenchCss.includes('position: sticky') === false &&
	workbenchCss.includes('17rem') === false );

const adminAssets = [ [ 'Config', 'admin.js' ], [ 'Text', 'keys.js' ], [ 'Routes', 'admin.js' ], [ 'Elements', 'admin.js' ], [ 'Images', 'slots.js' ],
	[ 'Elements', 'types.js' ], [ 'Users', 'roles.js' ], [ 'Users', 'lockout.js' ], [ 'Language', 'admin.js' ] ]
	.map( e => adminAsset( e[0], e[1] ) ).concat( [ moduleAsset( 'Navigation', 'admin.js' ) ] ).join('\n');
check( 'Admin forms use the shared action and back-link vocabulary',
	adminAssets.includes('admin-form-actions') === false &&
	adminAssets.includes("className = 'back-link'") === false &&
	adminAssets.includes("'nino-admin-actionbar'") &&
	adminAssets.includes("'nino-admin-back-link'") );

console.log('\nOne language');

// Every word a panel renders is a fill: the workbench's own strings live in
// _admin/text/<locale>.php, a module's in the text() directory beside its
// panel, and [jstext] hands all of them to Nino.content.getText(). A literal
// English sentence in a script is what the German interface used to show
// halfway down a screen. The recovery page is English by design (it has no
// session to take a language from) and the Template Builder is not part of
// this yet - neither is checked here.
const localizedScripts = fs2.readdirSync( path2.join( __dirname, '../_admin/assets' ) ).filter( f => f.endsWith('.js') && f !== 'recovery.js' ).map( f => [ f, asset( f ) ] )
	.concat( moduleScripts( '_admin/Nino/Modules', ADMIN_MODULES ) )
	.concat( moduleScripts( 'app/Nino/Modules', [ 'Form', 'Newsletter', 'Navigation', 'Search', 'Design' ] ) );
const literalSentence = /\.(?:textContent|placeholder|title|alt) = '[A-Z][^']*'|(?:confirm|alert)\( '[A-Z]|innerHTML = '<[^']*>[A-Za-z]{3,}/;
const literals = localizedScripts.filter( e => literalSentence.test( e[1] ) ).map( e => e[0] );
check( 'no workbench script renders a literal English sentence - every word is a fill'+ ( literals.length ? ' - found in '+ literals.join(', ') : '' ), literals.length === 0 );

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
  ...[ 'Navigation', 'Search', 'Design' ].map( m => read( 'app/Nino/Modules/'+ m+ '/Admin/Admin.php' ) ) ].forEach( php => {
	for( const m of php.matchAll( /'(\/_admin\/(?:nav|dashboard|config|lockout|language|users)\/[^'\s]+)'/g ) )
		if( m[1].endsWith('/manage') === false )
			usedKeys.add( m[1] );
} );
const undefinedKeys = [ ...usedKeys ].filter( k => [ 'en_US', 'de_DE' ].some( locale => fillSources[locale].includes( "'[["+ k+ "]]'" ) === false ) );
check( 'every fill key the workbench asks for is defined in English and in German'+ ( undefinedKeys.length ? ' - missing: '+ undefinedKeys.join(', ') : '' ), undefinedKeys.length === 0 );
check( 'the checks above saw the whole vocabulary', usedKeys.size > 200 );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
